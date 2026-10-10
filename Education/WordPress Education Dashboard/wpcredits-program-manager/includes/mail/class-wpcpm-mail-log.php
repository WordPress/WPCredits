<?php
/**
 * The record of every email the site sends.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One row per recipient of every email, kept 30 days: when, to whom, which email, its subject and
 * whether WordPress handed it to the mail server. Never the message text, the attachments, or
 * anything secret (the owner's decision of 10 October 2026): an email's body can hold a sign-in
 * link or a code, and the log exists to answer "was it sent", which the subject, the address and
 * the status answer.
 *
 * Booted from the plugin's bootstrap (`init()`), not from the Emails tool, so a site that filters
 * the tool out of `wpcpm_tools` still records its email: the capture (`WPCPM_Mail_Capture`), the
 * table's creation and upgrade, the daily cleanup and the privacy tools' hooks start here.
 *
 * The SQL runs on MySQL, where the site lives, and on WordPress's SQLite integration, where its
 * local copies and the suites run: no `DELETE ... LIMIT`, no `SHOW` but `SHOW TABLES LIKE`, and a
 * search escapes LIKE's characters with `$wpdb->esc_like()` and writes no ESCAPE clause, since both
 * read a backslash and the integration adds `ESCAPE '\'` itself.
 */
final class WPCPM_Mail_Log {

	const SCHEMA_VERSION     = 1;
	const OPT_VERSION        = 'wpcpm_mail_log_version';
	const OPT_PURGED         = 'wpcpm_mail_log_purged';
	const OLD_OPTION         = 'wpcpm_mail_log';
	const KEEP_DAYS          = 30;
	const PURGE_BATCH        = 1000;
	const PURGE_HOOK         = 'wpcpm_mail_log_purge';
	const PER_PAGE           = 50;
	const STATUS_SENT        = 'sent';
	const STATUS_FAILED      = 'failed';
	const STATUS_UNCONFIRMED = 'unconfirmed';
	const FILTER_TEST        = 'test';

	/** How many rows one page of the privacy tools handles. */
	const PRIVACY_PAGE = 100;

	/**
	 * Whether the table was found this request. Only a table found is kept: a missing one is looked
	 * for again, so the upgrade that creates it is seen at once.
	 *
	 * @var bool
	 */
	private static $found = false;

	/**
	 * Hooks: the capture, the table's creation and upgrade, the daily cleanup, and WordPress's
	 * privacy tools. Called from `wpcpm_bootstrap()`, beside `WPCPM_Mail::init()`.
	 */
	public static function init() {
		WPCPM_Mail_Capture::init();

		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( self::PURGE_HOOK, array( __CLASS__, 'purge' ) );

		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	/**
	 * The daily cleanup, scheduled once.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::PURGE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PURGE_HOOK );
		}
	}

	/**
	 * Stop the daily cleanup, on deactivation (`wpcpm_deactivate()`).
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::PURGE_HOOK );
	}

	/**
	 * The log's entry in WordPress's Export Personal Data tool.
	 *
	 * @param array $exporters WordPress's exporters.
	 * @return array The same, with the log's.
	 */
	public static function register_exporter( $exporters ) {
		$exporters = is_array( $exporters ) ? $exporters : array();

		$exporters['wpcpm-mail-log'] = array(
			'exporter_friendly_name' => __( 'Emails the site sent', 'wpcredits-program-manager' ),
			'callback'               => array( __CLASS__, 'export' ),
		);

		return $exporters;
	}

	/**
	 * The log's entry in WordPress's Erase Personal Data tool.
	 *
	 * @param array $erasers WordPress's erasers.
	 * @return array The same, with the log's.
	 */
	public static function register_eraser( $erasers ) {
		$erasers = is_array( $erasers ) ? $erasers : array();

		$erasers['wpcpm-mail-log'] = array(
			'eraser_friendly_name' => __( 'Emails the site sent', 'wpcredits-program-manager' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * The table's name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'wpcpm_mail_log';
	}

	/**
	 * The table as `dbDelta()` reads it: one column per line, two spaces after PRIMARY KEY.
	 *
	 * @return string
	 */
	public static function schema() {
		global $wpdb;

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		return "CREATE TABLE {$table} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
sent_at datetime NOT NULL,
to_email varchar(191) NOT NULL DEFAULT '',
user_id bigint(20) unsigned DEFAULT NULL,
to_name varchar(191) NOT NULL DEFAULT '',
recipient_type varchar(20) NOT NULL DEFAULT '',
module varchar(20) NOT NULL DEFAULT '',
template varchar(64) NOT NULL DEFAULT '',
subject varchar(255) NOT NULL DEFAULT '',
status varchar(12) NOT NULL DEFAULT '',
error varchar(255) NOT NULL DEFAULT '',
is_test tinyint(1) NOT NULL DEFAULT 0,
delivery varchar(20) DEFAULT NULL,
delivery_at datetime DEFAULT NULL,
PRIMARY KEY  (id),
KEY sent_at (sent_at),
KEY to_email (to_email),
KEY module_sent (module,sent_at),
KEY template (template),
KEY user_id (user_id)
) {$collate};";
	}

	/**
	 * Whether the table is there, asked once a request once it is found.
	 *
	 * @return bool
	 */
	public static function exists() {
		global $wpdb;

		if ( self::$found ) {
			return true;
		}

		$table       = self::table();
		self::$found = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A table of the plugin's own, asked for once a request.

		return self::$found;
	}

	/**
	 * Forget that the table was found. Test-only: a suite that takes the table away calls this.
	 */
	public static function reset() {
		self::$found = false;
	}

	/**
	 * Create or upgrade the table, on `init`, as the plugin's other stores do: updates arrive by
	 * replacing files, so the activation hook cannot be relied on. The version is stamped only once
	 * the table is there, so a creation that failed is tried again on the next load. The old option's
	 * entries move in on the first creation.
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( self::OPT_VERSION, 0 ) >= self::SCHEMA_VERSION ) {
			return;
		}

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		dbDelta( self::schema() );

		if ( ! self::exists() ) {
			return;
		}

		self::migrate_old_option();
		update_option( self::OPT_VERSION, self::SCHEMA_VERSION );
	}

	/**
	 * Move the hundred entries the plugin kept in an option before 1.122.18 into the table, then
	 * forget them. Their addresses stay masked as they were stored, and they have no subject. The
	 * option is deleted before anything is written, and only the request whose delete removed it
	 * writes, so two first requests cannot both move the hundred. A context the catalog does not
	 * know is stored as Other.
	 *
	 * @return int How many entries moved.
	 */
	public static function migrate_old_option() {
		$old = get_option( self::OLD_OPTION, array() );

		if ( ! delete_option( self::OLD_OPTION ) ) {
			return 0;
		}

		$moved = 0;

		foreach ( is_array( $old ) ? $old : array() as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['time'] ) ) {
				continue;
			}

			$context = isset( $entry['context'] ) ? (string) $entry['context'] : '';
			$context = null === WPCPM_Mail_Catalog::get( $context ) ? WPCPM_Mail_Catalog::OTHER : $context;

			$moved += self::add(
				array(
					'sent_at'        => gmdate( 'Y-m-d H:i:s', (int) $entry['time'] ),
					'to_email'       => isset( $entry['to'] ) ? (string) $entry['to'] : '',
					'user_id'        => null,
					'to_name'        => '',
					'recipient_type' => '',
					'module'         => WPCPM_Mail_Catalog::module_of( $context ),
					'template'       => $context,
					'subject'        => '',
					'status'         => empty( $entry['sent'] ) ? self::STATUS_FAILED : self::STATUS_SENT,
					'error'          => '',
					'is_test'        => 0 === strpos( $context, 'test-' ),
				)
			) ? 1 : 0;
		}

		return $moved;
	}

	/**
	 * Write one row.
	 *
	 * @param array $row sent_at (UTC `Y-m-d H:i:s`), to_email, user_id (int or null), to_name,
	 *                   recipient_type, module, template, subject, status, error, is_test (bool).
	 * @return int The new row's id, or 0 when nothing was written (no table, or the insert failed).
	 */
	public static function add( array $row ) {
		global $wpdb;

		if ( ! self::exists() ) {
			return 0;
		}

		$user_id = isset( $row['user_id'] ) && (int) $row['user_id'] > 0 ? (int) $row['user_id'] : null;

		$data = array(
			'sent_at'        => isset( $row['sent_at'] ) ? (string) $row['sent_at'] : gmdate( 'Y-m-d H:i:s' ),
			'to_email'       => self::cut( isset( $row['to_email'] ) ? $row['to_email'] : '', 191 ),
			'user_id'        => $user_id,
			'to_name'        => self::cut( isset( $row['to_name'] ) ? $row['to_name'] : '', 191 ),
			'recipient_type' => self::cut( isset( $row['recipient_type'] ) ? $row['recipient_type'] : '', 20 ),
			'module'         => self::cut( isset( $row['module'] ) ? $row['module'] : '', 20 ),
			'template'       => self::cut( isset( $row['template'] ) ? $row['template'] : '', 64 ),
			'subject'        => self::cut( isset( $row['subject'] ) ? $row['subject'] : '', 255 ),
			'status'         => self::cut( isset( $row['status'] ) ? $row['status'] : '', 12 ),
			'error'          => self::cut( isset( $row['error'] ) ? $row['error'] : '', 255 ),
			'is_test'        => empty( $row['is_test'] ) ? 0 : 1,
		);

		// WordPress writes NULL for a null value whatever its format.
		$format = array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

		if ( false === $wpdb->insert( self::table(), $data, $format ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The table is ours.
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * A text made valid UTF-8 and cut to a column's length in characters, never inside a character.
	 * Invalid bytes are replaced rather than refused: WordPress's insert drops a whole row whose text
	 * the column's character set cannot hold.
	 *
	 * @param mixed $text  The text.
	 * @param int   $chars The column's length.
	 * @return string
	 */
	private static function cut( $text, $chars ) {
		$text = wp_check_invalid_utf8( (string) $text, true );

		return mb_strlen( $text ) > $chars ? mb_substr( $text, 0, $chars ) : $text;
	}

	/**
	 * A LIKE pattern that matches the text anywhere, with LIKE's own characters read literally.
	 *
	 * @param string $text What was typed.
	 * @return string
	 */
	private static function like( $text ) {
		global $wpdb;

		return '%' . $wpdb->esc_like( (string) $text ) . '%';
	}

	/**
	 * The WHERE clause for a set of filters, prepared.
	 *
	 * @param array $filters search, exact_email, module, recipient_type, status, template, from, to.
	 * @return string " WHERE ..." or ''.
	 */
	private static function where( array $filters ) {
		global $wpdb;

		$parts = array();

		if ( isset( $filters['search'] ) && '' !== trim( (string) $filters['search'] ) ) {
			$like    = self::like( trim( (string) $filters['search'] ) );
			$parts[] = $wpdb->prepare( '( to_email LIKE %s OR to_name LIKE %s OR subject LIKE %s )', $like, $like, $like );
		}

		// One address exactly, for the privacy tools.
		if ( isset( $filters['exact_email'] ) && '' !== (string) $filters['exact_email'] ) {
			$parts[] = $wpdb->prepare( 'to_email = %s', (string) $filters['exact_email'] );
		}

		foreach ( array( 'module', 'recipient_type', 'template' ) as $key ) {
			if ( isset( $filters[ $key ] ) && '' !== (string) $filters[ $key ] ) {
				$parts[] = $wpdb->prepare( "{$key} = %s", (string) $filters[ $key ] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The column is one of three names written here.
			}
		}

		if ( isset( $filters['status'] ) && '' !== (string) $filters['status'] ) {
			$parts[] = self::FILTER_TEST === $filters['status'] ? 'is_test = 1' : $wpdb->prepare( 'status = %s', (string) $filters['status'] );
		}

		if ( ! empty( $filters['from'] ) ) {
			$parts[] = $wpdb->prepare( 'sent_at >= %s', (string) $filters['from'] );
		}

		if ( ! empty( $filters['to'] ) ) {
			$parts[] = $wpdb->prepare( 'sent_at < %s', (string) $filters['to'] );
		}

		return $parts ? ' WHERE ' . implode( ' AND ', $parts ) : '';
	}

	/**
	 * One page of rows, newest first, and how many rows the filters match.
	 *
	 * The clause is prepared once, in `where()`, and the page's two numbers are written in as
	 * integers: preparing the clause a second time would read a search's `%` as a placeholder.
	 *
	 * @param array $filters  See `where()`.
	 * @param int   $page     The page, from 1.
	 * @param int   $per_page Rows per page.
	 * @return array{rows:array<int,array>,total:int}
	 */
	public static function find( array $filters, $page = 1, $per_page = self::PER_PAGE ) {
		global $wpdb;

		if ( ! self::exists() ) {
			return array(
				'rows'  => array(),
				'total' => 0,
			);
		}

		$table    = self::table();
		$where    = self::where( $filters );
		$per_page = max( 1, (int) $per_page );
		$offset   = ( max( 1, (int) $page ) - 1 ) * $per_page;

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}{$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours; the clause is prepared in where().
		$rows  = $wpdb->get_results( "SELECT * FROM {$table}{$where} ORDER BY sent_at DESC, id DESC LIMIT " . (int) $per_page . ' OFFSET ' . (int) $offset, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- As above; the two numbers are integers.

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * The newest row.
	 *
	 * @return array|null
	 */
	public static function latest() {
		$found = self::find( array(), 1, 1 );

		return isset( $found['rows'][0] ) ? $found['rows'][0] : null;
	}

	/**
	 * How many emails were written since a time, whatever their status.
	 *
	 * @param string $since_utc UTC `Y-m-d H:i:s`.
	 * @return int
	 */
	public static function count_since( $since_utc ) {
		return self::find( array( 'from' => (string) $since_utc ), 1, 1 )['total'];
	}

	/**
	 * How many emails failed since a time.
	 *
	 * @param string $since_utc UTC `Y-m-d H:i:s`.
	 * @return int
	 */
	public static function failures_since( $since_utc ) {
		return self::find(
			array(
				'status' => self::STATUS_FAILED,
				'from'   => (string) $since_utc,
			),
			1,
			1
		)['total'];
	}

	/**
	 * Delete what is older than 30 days, a thousand rows at a time, and stamp the run.
	 *
	 * @param int $now The time to count back from; 0 for now.
	 * @return int How many rows went.
	 */
	public static function purge( $now = 0 ) {
		global $wpdb;

		$now = $now ? (int) $now : time();

		if ( ! self::exists() ) {
			return 0;
		}

		$table   = self::table();
		$cutoff  = gmdate( 'Y-m-d H:i:s', $now - self::KEEP_DAYS * DAY_IN_SECONDS );
		$removed = 0;

		// At most twenty batches a run: a backlog larger than that finishes on the next.
		for ( $batch = 0; $batch < 20; $batch++ ) {
			$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE sent_at < %s ORDER BY id LIMIT %d", $cutoff, self::PURGE_BATCH ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours.

			if ( ! $ids ) {
				break;
			}

			$removed += (int) $wpdb->query( "DELETE FROM {$table} WHERE id IN (" . implode( ',', $ids ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- Integers only.
		}

		update_option( self::OPT_PURGED, $now, false );

		return $removed;
	}

	/**
	 * Run the cleanup when it has not run for a day, for a site whose scheduled jobs are late.
	 */
	public static function maybe_purge() {
		if ( time() - (int) get_option( self::OPT_PURGED, 0 ) >= DAY_IN_SECONDS ) {
			self::purge();
		}
	}

	/**
	 * WordPress's Export Personal Data tool: a person's entries, by address.
	 *
	 * @param string $email The address.
	 * @param int    $page  The page, from 1.
	 * @return array{data:array,done:bool}
	 */
	public static function export( $email, $page = 1 ) {
		$found = self::find( self::exact( $email ), $page, self::PRIVACY_PAGE );
		$data  = array();

		foreach ( $found['rows'] as $row ) {
			$data[] = array(
				'group_id'    => 'wpcpm-mail-log',
				'group_label' => __( 'Emails the site sent', 'wpcredits-program-manager' ),
				'item_id'     => 'wpcpm-mail-log-' . (int) $row['id'],
				'data'        => array(
					array(
						'name'  => __( 'When', 'wpcredits-program-manager' ),
						'value' => $row['sent_at'] . ' UTC',
					),
					array(
						'name'  => __( 'Email', 'wpcredits-program-manager' ),
						'value' => WPCPM_Mail_Catalog::label( $row['template'] ),
					),
					array(
						'name'  => __( 'Subject', 'wpcredits-program-manager' ),
						'value' => $row['subject'],
					),
					array(
						'name'  => __( 'Status', 'wpcredits-program-manager' ),
						'value' => self::status_label( $row['status'] ),
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => (int) $page * self::PRIVACY_PAGE >= $found['total'],
		);
	}

	/**
	 * WordPress's Erase Personal Data tool: delete a person's entries, by address.
	 *
	 * @param string $email The address.
	 * @param int    $page  The page, from 1; one call deletes them all.
	 * @return array{items_removed:bool,items_retained:bool,messages:array,done:bool}
	 */
	public static function erase( $email, $page = 1 ) {
		global $wpdb;

		$removed = 0;

		if ( self::exists() && '' !== trim( (string) $email ) ) {
			$removed = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE to_email = %s', trim( (string) $email ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours.
		}

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * The filter that matches one address exactly, which `where()` reads as `to_email = %s`.
	 *
	 * @param string $email The address.
	 * @return array
	 */
	private static function exact( $email ) {
		return array( 'exact_email' => trim( (string) $email ) );
	}

	/**
	 * A status in words.
	 *
	 * @param string $status The stored status.
	 * @return string
	 */
	public static function status_label( $status ) {
		switch ( (string) $status ) {
			case self::STATUS_SENT:
				return __( 'Handed to the mail server', 'wpcredits-program-manager' );
			case self::STATUS_FAILED:
				return __( 'Failed', 'wpcredits-program-manager' );
			case self::STATUS_UNCONFIRMED:
				return __( 'Not confirmed', 'wpcredits-program-manager' );
		}

		return __( 'Not recorded', 'wpcredits-program-manager' );
	}

	/**
	 * Remove everything the log keeps: the table, its two options, the old option and the cleanup
	 * job.
	 */
	public static function uninstall() {
		global $wpdb;

		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours.
		self::$found = false;

		delete_option( self::OPT_VERSION );
		delete_option( self::OPT_PURGED );
		delete_option( self::OLD_OPTION );
		wp_clear_scheduled_hook( self::PURGE_HOOK );
	}
}
