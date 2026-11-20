<?php
/**
 * The tutor's transcript.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One row per student message, AI Mentor reply and tool call of a turn on the tutor chat, kept 90 days
 * and then purged. The rows are the model's history (`recent()`), the card's list and count
 * (`since()`, `count_since()`) and the Administrators' view (`accounts()`, `by_record()`).
 *
 * Booted from the plugin's bootstrap (`init()`), beside `WPCPM_Mail_Log::init()`, not from the Need
 * help? tool, so the table is created, purged and erased whether or not that tool is on. The privacy
 * tools find a person's rows by the account their email belongs to.
 *
 * Built in the shape of `WPCPM_Mail_Log`, and its SQL runs where that runs: on MySQL, on WordPress's
 * SQLite integration and on the suites' stand-in, so no `DELETE ... LIMIT` and no `SHOW` but
 * `SHOW TABLES LIKE`.
 */
final class WPCPM_Tutor_Store {

	const SCHEMA_VERSION = 1;
	const OPT_VERSION    = 'wpcpm_tutor_store_version';
	const KEEP_DAYS      = 90;
	const PURGE_BATCH    = 1000;
	const PURGE_HOOK     = 'wpcpm_tutor_purge';
	const PRIVACY_ID     = 'wpcpm-tutor';
	const PRIVACY_PAGE   = 100;

	const ROLE_STUDENT = 'student';
	const ROLE_MENTOR  = 'mentor_ai';
	const ROLE_TOOL    = 'tool';

	/**
	 * Whether the table was found this request. Only a table found is kept: a missing one is looked
	 * for again, so the upgrade that creates it is seen at once.
	 *
	 * @var bool
	 */
	private static $found = false;

	/**
	 * Hooks: the table's creation and upgrade, the daily purge and WordPress's privacy tools. Called
	 * from `wpcpm_bootstrap()`, beside `WPCPM_Mail_Log::init()`.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( self::PURGE_HOOK, array( __CLASS__, 'run_purge' ) );

		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	/**
	 * The daily purge, scheduled once.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::PURGE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PURGE_HOOK );
		}
	}

	/**
	 * Stop the daily purge, on deactivation (`wpcpm_deactivate()`).
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::PURGE_HOOK );
	}

	/**
	 * The transcript's entry in WordPress's Export Personal Data tool.
	 *
	 * @param array $exporters WordPress's exporters.
	 * @return array The same, with the transcript's.
	 */
	public static function register_exporter( $exporters ) {
		$exporters = is_array( $exporters ) ? $exporters : array();

		$exporters[ self::PRIVACY_ID ] = array(
			'exporter_friendly_name' => __( 'AI Mentor conversation', 'wpcredits-program-manager' ),
			'callback'               => array( __CLASS__, 'export' ),
		);

		return $exporters;
	}

	/**
	 * The transcript's entry in WordPress's Erase Personal Data tool.
	 *
	 * @param array $erasers WordPress's erasers.
	 * @return array The same, with the transcript's.
	 */
	public static function register_eraser( $erasers ) {
		$erasers = is_array( $erasers ) ? $erasers : array();

		$erasers[ self::PRIVACY_ID ] = array(
			'eraser_friendly_name' => __( 'AI Mentor conversation', 'wpcredits-program-manager' ),
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

		return $wpdb->prefix . 'wpcpm_tutor_turns';
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
user_id bigint(20) unsigned NOT NULL,
student_record varchar(32) NOT NULL DEFAULT '',
role varchar(16) NOT NULL DEFAULT '',
body longtext NOT NULL,
sources longtext NOT NULL,
tool varchar(64) NOT NULL DEFAULT '',
created_gmt datetime NOT NULL,
PRIMARY KEY  (id),
KEY user_created (user_id,created_gmt)
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
	 * the table is there, so a creation that failed is tried again on the next load.
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

		update_option( self::OPT_VERSION, self::SCHEMA_VERSION );
	}

	/**
	 * Write one row, stamped with now.
	 *
	 * @param int    $user_id        The student's account; 0 is refused.
	 * @param string $student_record The student's record the chat was opened on.
	 * @param string $role           `student`, `mentor_ai` or `tool`; any other is refused.
	 * @param string $body           The message, the reply, or a tool call's result.
	 * @param array  $sources        The guides a reply cites, stored as JSON.
	 * @param string $tool           The tool's name, for a `tool` row.
	 * @return int The new row's id, or 0 when nothing was written.
	 */
	public static function add( $user_id, $student_record, $role, $body, array $sources = array(), $tool = '' ) {
		global $wpdb;

		$user_id = (int) $user_id;

		if ( $user_id < 1 || ! in_array( $role, array( self::ROLE_STUDENT, self::ROLE_MENTOR, self::ROLE_TOOL ), true ) || ! self::exists() ) {
			return 0;
		}

		$data = array(
			'user_id'        => $user_id,
			'student_record' => self::cut( $student_record, 32 ),
			'role'           => $role,
			'body'           => wp_check_invalid_utf8( (string) $body, true ),
			'sources'        => (string) wp_json_encode( $sources ),
			'tool'           => self::cut( $tool, 64 ),
			'created_gmt'    => gmdate( 'Y-m-d H:i:s' ),
		);

		$format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( false === $wpdb->insert( self::table(), $data, $format ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The table is ours.
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * The last rows of an account's conversation, for the model's history.
	 *
	 * @param int $user_id The account.
	 * @param int $limit   How many rows at most.
	 * @return array<int,array> `student` and `mentor_ai` rows, oldest first.
	 */
	public static function recent( $user_id, $limit ) {
		global $wpdb;

		$limit = (int) $limit;

		if ( $limit < 1 || ! self::exists() ) {
			return array();
		}

		$table = self::table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d AND role IN ( %s, %s ) ORDER BY created_gmt DESC, id DESC LIMIT %d", (int) $user_id, self::ROLE_STUDENT, self::ROLE_MENTOR, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours.

		return self::shape( array_reverse( is_array( $rows ) ? $rows : array() ) );
	}

	/**
	 * An account's conversation over the last days, for the card.
	 *
	 * @param int $user_id The account.
	 * @param int $days    How many days back.
	 * @return array<int,array> `student` and `mentor_ai` rows, oldest first.
	 */
	public static function since( $user_id, $days = self::KEEP_DAYS ) {
		global $wpdb;

		if ( ! self::exists() ) {
			return array();
		}

		$table = self::table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d AND role IN ( %s, %s ) AND created_gmt >= %s ORDER BY created_gmt ASC, id ASC", (int) $user_id, self::ROLE_STUDENT, self::ROLE_MENTOR, self::cutoff( $days ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours.

		return self::shape( is_array( $rows ) ? $rows : array() );
	}

	/**
	 * How many questions an account asked over the last days.
	 *
	 * @param int $user_id The account.
	 * @param int $days    How many days back.
	 * @return int `student` rows only.
	 */
	public static function count_since( $user_id, $days = self::KEEP_DAYS ) {
		global $wpdb;

		if ( ! self::exists() ) {
			return 0;
		}

		$table = self::table();

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND role = %s AND created_gmt >= %s", (int) $user_id, self::ROLE_STUDENT, self::cutoff( $days ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours.
	}

	/**
	 * The accounts that used the chat over the last days, for the Administrators' view.
	 *
	 * @param int $days How many days back.
	 * @return array<int,array{user_id:int,student_record:string,turns:int,last:string}> One entry per
	 *                                                                               account, newest `last` first; `student_record` is the newest row's, `turns` counts `student` rows.
	 */
	public static function accounts( $days = self::KEEP_DAYS ) {
		global $wpdb;

		if ( ! self::exists() ) {
			return array();
		}

		$table = self::table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT t.user_id AS user_id, SUM( CASE WHEN t.role = %s THEN 1 ELSE 0 END ) AS turns, MAX( t.created_gmt ) AS last, ( SELECT l.student_record FROM {$table} l WHERE l.user_id = t.user_id ORDER BY l.created_gmt DESC, l.id DESC LIMIT 1 ) AS student_record FROM {$table} t WHERE t.created_gmt >= %s GROUP BY t.user_id ORDER BY last DESC, t.user_id DESC", self::ROLE_STUDENT, self::cutoff( $days ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours.
		$out   = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$out[] = array(
				'user_id'        => (int) $row['user_id'],
				'student_record' => (string) $row['student_record'],
				'turns'          => (int) $row['turns'],
				'last'           => (string) $row['last'],
			);
		}

		return $out;
	}

	/**
	 * Everything said on one student's record, tool calls included.
	 *
	 * @param string $record The student's record.
	 * @return array<int,array> All three roles, oldest first.
	 */
	public static function by_record( $record ) {
		global $wpdb;

		if ( ! self::exists() ) {
			return array();
		}

		$table = self::table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE student_record = %s ORDER BY created_gmt ASC, id ASC", (string) $record ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours.

		return self::shape( is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Delete what is more than 90 days old, a thousand rows at a time. A row exactly 90 days old stays.
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
			$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE created_gmt < %s ORDER BY id LIMIT %d", $cutoff, self::PURGE_BATCH ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours.

			if ( ! $ids ) {
				break;
			}

			$removed += (int) $wpdb->query( "DELETE FROM {$table} WHERE id IN (" . implode( ',', $ids ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- Integers only.
		}

		return $removed;
	}

	/**
	 * The daily purge's hook. A WordPress action callback's return value is ignored, so this one
	 * returns nothing; `purge()` keeps its count for the suites.
	 *
	 * @return void
	 */
	public static function run_purge() {
		self::purge();
	}

	/**
	 * WordPress's Export Personal Data tool: an account's conversation, by the address of the account.
	 *
	 * The text is escaped: core prints each value through kses, and a student's message is plain text
	 * that can hold a "<" and a tag.
	 *
	 * @param string $email The address.
	 * @param int    $page  The page, from 1.
	 * @return array{data:array,done:bool}
	 */
	public static function export( $email, $page = 1 ) {
		global $wpdb;

		$user = get_user_by( 'email', trim( (string) $email ) );

		if ( ! $user || empty( $user->ID ) || ! self::exists() ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$table = self::table();
		$page  = max( 1, (int) $page );
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", (int) $user->ID ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours.
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_gmt ASC, id ASC LIMIT %d OFFSET %d", (int) $user->ID, self::PRIVACY_PAGE, ( $page - 1 ) * self::PRIVACY_PAGE ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours.
		$data  = array();

		foreach ( self::shape( is_array( $rows ) ? $rows : array() ) as $row ) {
			$item = array(
				array(
					'name'  => __( 'When', 'wpcredits-program-manager' ),
					'value' => $row['created_gmt'] . ' UTC',
				),
				array(
					'name'  => __( 'Who', 'wpcredits-program-manager' ),
					'value' => self::who( $row['role'] ),
				),
				array(
					'name'  => __( 'Text', 'wpcredits-program-manager' ),
					'value' => esc_html( $row['body'] ),
				),
			);

			if ( self::ROLE_TOOL === $row['role'] ) {
				$item[] = array(
					'name'  => __( 'Tool name', 'wpcredits-program-manager' ),
					'value' => esc_html( $row['tool'] ),
				);
			}

			$data[] = array(
				'group_id'    => self::PRIVACY_ID,
				'group_label' => __( 'AI Mentor conversation', 'wpcredits-program-manager' ),
				'item_id'     => self::PRIVACY_ID . '-' . $row['id'],
				'data'        => $item,
			);
		}

		return array(
			'data' => $data,
			'done' => $page * self::PRIVACY_PAGE >= $total,
		);
	}

	/**
	 * Who said a row, as the export names it to the student: You, the AI Mentor, or a tool call, as the
	 * Administrators' view labels that.
	 *
	 * @param string $role `student`, `mentor_ai` or `tool`.
	 * @return string
	 */
	private static function who( $role ) {
		if ( self::ROLE_STUDENT === $role ) {
			return __( 'You', 'wpcredits-program-manager' );
		}

		return self::ROLE_TOOL === $role ? __( 'Tool call', 'wpcredits-program-manager' ) : __( 'AI Mentor', 'wpcredits-program-manager' );
	}

	/**
	 * WordPress's Erase Personal Data tool: delete an account's conversation, by the address of the
	 * account. One call deletes every row, so it is done at once.
	 *
	 * @param string $email The address.
	 * @param int    $page  The page, from 1; not read, for the reason above.
	 * @return array{items_removed:bool,items_retained:bool,messages:array,done:bool}
	 */
	public static function erase( $email, $page = 1 ) {
		global $wpdb;

		unset( $page );

		$removed = 0;
		$user    = '' === trim( (string) $email ) ? false : get_user_by( 'email', trim( (string) $email ) );

		if ( $user && ! empty( $user->ID ) && self::exists() ) {
			$removed = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE user_id = %d', (int) $user->ID ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours.
		}

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Remove everything the store keeps: the table, its option and the purge job.
	 */
	public static function uninstall() {
		global $wpdb;

		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours.
		self::$found = false;

		delete_option( self::OPT_VERSION );
		wp_clear_scheduled_hook( self::PURGE_HOOK );
	}

	/**
	 * The time a window of days starts, UTC.
	 *
	 * @param int $days How many days back.
	 * @return string `Y-m-d H:i:s`.
	 */
	private static function cutoff( $days ) {
		return gmdate( 'Y-m-d H:i:s', time() - max( 0, (int) $days ) * DAY_IN_SECONDS );
	}

	/**
	 * Rows as the readers return them: typed, with the sources decoded.
	 *
	 * @param array $rows Rows as the database gave them.
	 * @return array<int,array{id:int,user_id:int,student_record:string,role:string,body:string,sources:array,tool:string,created_gmt:string}>
	 */
	private static function shape( array $rows ) {
		$out = array();

		foreach ( $rows as $row ) {
			$sources = json_decode( (string) $row['sources'], true );
			$out[]   = array(
				'id'             => (int) $row['id'],
				'user_id'        => (int) $row['user_id'],
				'student_record' => (string) $row['student_record'],
				'role'           => (string) $row['role'],
				'body'           => (string) $row['body'],
				'sources'        => is_array( $sources ) ? $sources : array(),
				'tool'           => (string) $row['tool'],
				'created_gmt'    => (string) $row['created_gmt'],
			);
		}

		return $out;
	}

	/**
	 * A text made valid UTF-8 and cut to a column's length in characters, never inside a character.
	 *
	 * @param mixed $text  The text.
	 * @param int   $chars The column's length.
	 * @return string
	 */
	private static function cut( $text, $chars ) {
		$text = wp_check_invalid_utf8( (string) $text, true );

		return mb_strlen( $text ) > $chars ? mb_substr( $text, 0, $chars ) : $text;
	}
}
