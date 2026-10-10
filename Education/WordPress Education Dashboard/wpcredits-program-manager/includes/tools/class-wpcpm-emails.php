<?php
/**
 * Tool - Emails.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The site's email: in this version the Log, every email the site sent in the last 30 days.
 *
 * The tool draws the screen and its status line and nothing more. The recording, the table's
 * creation and upgrade, the daily cleanup and the privacy tools' hooks boot from the plugin's
 * bootstrap (`WPCPM_Mail_Log::init()`), so a site that filters this tool out of `wpcpm_tools` still
 * records its email.
 *
 * Every link to the Log names its tab (`tab=log`), which the screen's filter form carries too, so
 * the links keep working once the screen has other tabs beside it.
 */
class WPCPM_Emails extends WPCPM_Tool {

	/** The Log's tab. */
	const TAB_LOG = 'log';

	/**
	 * Tool ID.
	 *
	 * @return string
	 */
	public function id() {
		return 'emails';
	}

	/**
	 * Tool name.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Emails', 'wpcredits-program-manager' );
	}

	/**
	 * One-line description for the Tools screen.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Every email the site sent in the last 30 days: who it went to, which email, and whether WordPress handed it to the mail server.', 'wpcredits-program-manager' );
	}

	/**
	 * Always ready: the log needs no connection, and says itself when its table is missing.
	 *
	 * @return bool
	 */
	public function is_ready() {
		return true;
	}

	/**
	 * How many emails the log holds for the last 30 days, or that it is not ready yet.
	 *
	 * @return string
	 */
	public function status_line() {
		if ( ! WPCPM_Mail_Log::exists() ) {
			return __( 'The email log is not ready yet.', 'wpcredits-program-manager' );
		}

		$count = WPCPM_Mail_Log::count_since( gmdate( 'Y-m-d H:i:s', time() - WPCPM_Mail_Log::KEEP_DAYS * DAY_IN_SECONDS ) );

		return sprintf(
			/* translators: %s: number of emails. */
			_n( '%s email in the last 30 days.', '%s emails in the last 30 days.', $count, 'wpcredits-program-manager' ),
			number_format_i18n( $count )
		);
	}

	/**
	 * Hooks: the screen's stylesheet. Nothing else: the log boots on its own.
	 */
	public function boot() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * The screen's stylesheet, on this screen only. The Email field's script is enqueued by the
	 * field itself (`WPCPM_Dashboards::render_combo()`).
	 *
	 * @param string $hook The screen's hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, $this->page_slug() ) ) {
			return;
		}

		wp_enqueue_style( 'wpcpm-emails', WPCPM_PLUGIN_URL . 'assets/css/emails.css', array(), WPCPM_VERSION );
	}

	/**
	 * The UTC bounds of a When choice, counted in the site's time zone.
	 *
	 * @param string $when '' (the 30 days kept), 'today', '7', '30' or 'range'.
	 * @param string $from For 'range': a Y-m-d local date, from its midnight.
	 * @param string $to   For 'range': a Y-m-d local date, to the next midnight.
	 * @param int    $now  The time; 0 for now.
	 * @return array{from:string,to:string}
	 */
	public static function range( $when, $from = '', $to = '', $now = 0 ) {
		$now  = $now ? (int) $now : time();
		$zone = wp_timezone();
		$utc  = new DateTimeZone( 'UTC' );
		$out  = array(
			'from' => '',
			'to'   => '',
		);

		switch ( (string) $when ) {
			case 'today':
				$day         = ( new DateTimeImmutable( '@' . $now ) )->setTimezone( $zone )->setTime( 0, 0 );
				$out['from'] = $day->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
				break;
			case '7':
			case '30':
				$out['from'] = gmdate( 'Y-m-d H:i:s', $now - (int) $when * DAY_IN_SECONDS );
				break;
			case 'range':
				foreach ( array(
					'from' => $from,
					'to'   => $to,
				) as $key => $date ) {
					$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $date, $zone );

					if ( false === $parsed || $parsed->format( 'Y-m-d' ) !== (string) $date ) {
						continue;
					}

					$parsed      = 'to' === $key ? $parsed->modify( '+1 day' ) : $parsed;
					$out[ $key ] = $parsed->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
				}
				break;
		}

		return $out;
	}

	/**
	 * The When choice the request means: the one chosen, or, when none was, 'range' as soon as a
	 * From or To date is a date. So dates typed and sent with When left empty apply on their own, and
	 * the When field prints what is applied.
	 *
	 * @return string '' (the 30 days kept), 'today', '7', '30' or 'range'.
	 */
	public static function when_from_request() {
		$when = WPCPM_Request::key( 'when' );

		if ( '' === $when ) {
			$dates = self::range( 'range', WPCPM_Request::text( 'from' ), WPCPM_Request::text( 'to' ) );

			if ( '' !== $dates['from'] || '' !== $dates['to'] ) {
				return 'range';
			}
		}

		return $when;
	}

	/**
	 * The Log's filters from the request, as `WPCPM_Mail_Log::find()` reads them.
	 *
	 * @return array
	 */
	public static function filters_from_request() {
		$range = self::range( self::when_from_request(), WPCPM_Request::text( 'from' ), WPCPM_Request::text( 'to' ) );

		return array(
			'search'         => WPCPM_Request::text( 's' ),
			'module'         => WPCPM_Request::key( 'module' ),
			'recipient_type' => WPCPM_Request::key( 'type' ),
			'status'         => WPCPM_Request::key( 'status' ),
			'template'       => WPCPM_Request::key( 'email' ),
			'from'           => $range['from'],
			'to'             => $range['to'],
		);
	}

	/**
	 * The Email filter's choices: "Every email" first, then every email the catalog names and "Other
	 * email", A to Z as the Viewing as lists are (`WPCPM_Dashboards::compare_names()`).
	 *
	 * @return array<string,string> Value to label.
	 */
	public static function email_options() {
		$labels = array();

		foreach ( WPCPM_Mail_Catalog::emails() as $id => $entry ) {
			$labels[ $id ] = $entry['label'];
		}

		$labels[ WPCPM_Mail_Catalog::OTHER ] = WPCPM_Mail_Catalog::label( WPCPM_Mail_Catalog::OTHER );

		uasort( $labels, array( 'WPCPM_Dashboards', 'compare_names' ) );

		return array( '' => __( 'Every email', 'wpcredits-program-manager' ) ) + $labels;
	}

	/**
	 * The screen: the filters, a form of their own, then the count, the table and what its main
	 * status promises.
	 *
	 * The filter form is a GET form closed before the table, and its search box is its own field
	 * (`s`) rather than the table's `search_box()`, which core draws only while there is a search or
	 * a row: so the box never disappears, and no nonce or referer of the table's joins the filters'
	 * address. The table's page links carry the filters, which are the address's.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		echo '<div class="wrap wpcpm-wrap wpcpm-emails">';
		printf( '<h1>%s</h1>', esc_html( $this->label() ) );

		if ( ! WPCPM_Mail_Log::exists() ) {
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html__( 'The email log is not ready yet. It is created the next time a page of the site loads; if this stays, tell whoever looks after the site.', 'wpcredits-program-manager' ) );
			echo '</div>';

			return;
		}

		WPCPM_Mail_Log::maybe_purge();

		require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-log-table.php';

		$filters = self::filters_from_request();
		$table   = new WPCPM_Mail_Log_Table( $filters );
		$table->prepare_items();

		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: %d: days kept. */
					__( 'Every email the site sent in the last %d days, newest first.', 'wpcredits-program-manager' ),
					WPCPM_Mail_Log::KEEP_DAYS
				)
			)
		);

		echo '<form method="get" class="wpcpm-emails__filters">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $this->page_slug() ) );
		printf( '<input type="hidden" name="tab" value="%s" />', esc_attr( self::TAB_LOG ) );

		printf(
			'<div class="wpcpm-emails__filter wpcpm-emails__search"><label for="wpcpm-emails-search">%1$s</label> <input type="search" id="wpcpm-emails-search" name="s" value="%2$s" /></div>',
			esc_html__( 'Search address, name or subject', 'wpcredits-program-manager' ),
			esc_attr( $filters['search'] )
		);

		self::select( 'module', __( 'Area', 'wpcredits-program-manager' ), WPCPM_Mail_Catalog::modules(), $filters['module'], __( 'Every area', 'wpcredits-program-manager' ) );
		self::select( 'type', __( 'Recipient type', 'wpcredits-program-manager' ), WPCPM_Mail_Catalog::types(), $filters['recipient_type'] );
		self::select(
			'status',
			__( 'Status', 'wpcredits-program-manager' ),
			array(
				WPCPM_Mail_Log::STATUS_SENT        => WPCPM_Mail_Log::status_label( WPCPM_Mail_Log::STATUS_SENT ),
				WPCPM_Mail_Log::STATUS_FAILED      => WPCPM_Mail_Log::status_label( WPCPM_Mail_Log::STATUS_FAILED ),
				WPCPM_Mail_Log::STATUS_UNCONFIRMED => WPCPM_Mail_Log::status_label( WPCPM_Mail_Log::STATUS_UNCONFIRMED ),
				WPCPM_Mail_Log::FILTER_TEST        => __( 'Test', 'wpcredits-program-manager' ),
			),
			$filters['status']
		);

		// The Email filter is the one long list: one field that drops down, takes typing and narrows.
		/* translators: %s: how many emails the Email filter's list shows, as a number. */
		$count = __( 'Emails in the list: %s', 'wpcredits-program-manager' );

		echo '<div class="wpcpm-emails__filter wpcpm-emails__email">';
		WPCPM_Dashboards::render_combo(
			array(
				'id'      => 'wpcpm-emails-email',
				'name'    => 'email',
				'options' => self::email_options(),
				'current' => $filters['template'],
				'label'   => __( 'Email', 'wpcredits-program-manager' ),
				'find'    => __( 'Type to find an email', 'wpcredits-program-manager' ),
				'none'    => __( 'No email has that name.', 'wpcredits-program-manager' ),
				'count'   => $count,
			)
		);
		echo '</div>';

		self::select(
			'when',
			__( 'When', 'wpcredits-program-manager' ),
			array(
				'today' => __( 'Today', 'wpcredits-program-manager' ),
				'7'     => __( 'The last 7 days', 'wpcredits-program-manager' ),
				'30'    => __( 'The last 30 days', 'wpcredits-program-manager' ),
				'range' => __( 'From and to dates', 'wpcredits-program-manager' ),
			),
			self::when_from_request(),
			__( 'Any time', 'wpcredits-program-manager' )
		);

		foreach ( array(
			'from' => __( 'From', 'wpcredits-program-manager' ),
			'to'   => __( 'To', 'wpcredits-program-manager' ),
		) as $name => $label ) {
			printf(
				'<div class="wpcpm-emails__filter"><label for="wpcpm-emails-%1$s">%2$s</label> <input type="date" id="wpcpm-emails-%1$s" name="%1$s" value="%3$s" /></div>',
				esc_attr( $name ),
				esc_html( $label ),
				esc_attr( WPCPM_Request::text( $name ) )
			);
		}

		printf( '<div class="wpcpm-emails__filter"><button type="submit" class="button">%s</button></div>', esc_html__( 'Filter', 'wpcredits-program-manager' ) );
		echo '</form>';

		$total = (int) $table->get_pagination_arg( 'total_items' );

		printf(
			'<p class="wpcpm-emails__count">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: number of emails. */
					_n( '%s email matches.', '%s emails match.', $total, 'wpcredits-program-manager' ),
					number_format_i18n( $total )
				)
			)
		);

		$table->display();

		// Under the table: what the status most rows carry promises.
		printf( '<p class="description">%s</p>', esc_html__( '"Handed to the mail server" means the site passed the email on, not that it reached the inbox.', 'wpcredits-program-manager' ) );
		echo '</div>';
	}

	/**
	 * A plain select for a short list, with its label.
	 *
	 * @param string $name    The query argument.
	 * @param string $label   The label.
	 * @param array  $options Value to label.
	 * @param string $current The value chosen.
	 * @param string $any     The first option's label, for no choice; "All" when empty.
	 */
	private static function select( $name, $label, array $options, $current, $any = '' ) {
		$any = '' !== $any ? $any : __( 'All', 'wpcredits-program-manager' );

		printf( '<div class="wpcpm-emails__filter"><label for="wpcpm-emails-%1$s">%2$s</label> ', esc_attr( $name ), esc_html( $label ) );
		printf( '<select id="wpcpm-emails-%1$s" name="%1$s"><option value="">%2$s</option>', esc_attr( $name ), esc_html( $any ) );

		foreach ( $options as $value => $text ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( (string) $current, (string) $value, false ), esc_html( $text ) );
		}

		echo '</select></div>';
	}
}
