<?php
/**
 * Institutions module.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module 3 - Institutions.
 *
 * The program's side of the partnership with a school: the pipeline of institution records
 * read from Airtable, the roster index the students sync builds per institution, the state
 * of each Collaboration Agreement, and the reconciliation between the two Airtable tables
 * that describe the same students.
 *
 * Phase 1 is manager-only. The screen reads the pipeline index, the roster counts, the
 * countries map and the per-institution agreement options, and never Airtable itself: a
 * render that paged the base would take the rate limit away from the syncs, and a number
 * read live cannot say when it was true. Every card prints the read time its numbers came
 * from instead.
 */
class WPCPM_Institutions extends WPCPM_Module {

	const ACTION_SYNC   = 'wpcpm_institutions_sync';
	const ACTION_CANCEL = 'wpcpm_institutions_cancel';
	const ACTION_PROBE  = 'wpcpm_institutions_probe';
	const ACTION_TICK   = 'wpcpm_institutions_tick';

	/** Flash channel for this screen's outcomes. */
	const FLASH = 'institutions';

	/** The one pipeline filter Phase 1 offers, as the `wpcpm_filter` query argument. */
	const FILTER_GAP = 'agreement_gap';

	/**
	 * The day the consent checkbox was added to the Airtable application form.
	 *
	 * Every consent-ticked record was created on or after this date and none before it, so a
	 * record created earlier with no tick was collected before the question existed. The
	 * consent card counts those and says so; it never says consent went missing, because it
	 * was not asked.
	 */
	const CONSENT_QUESTION_ADDED = '2026-07-20';

	/**
	 * The membership stamps `WPCPM_Institution_Members` writes on accounts.
	 *
	 * Named through the class that owns them rather than as literals: `WPCPM_Institution_Members`
	 * is the only writer, `bin/test-institution-members.php` proves it by scanning for the strings,
	 * and a key renamed there would otherwise leave this uninstall list quietly deleting nothing.
	 *
	 * @return string[]
	 */
	public static function member_meta() {
		return array(
			WPCPM_Institution_Members::META_RECORD_ID,
			WPCPM_Institution_Members::META_ACTIVE,
			WPCPM_Institution_Members::META_RECORD_ID_WAS,
			WPCPM_Institution_Members::META_MEMBERSHIP,
			WPCPM_Institution_Members::META_INVITED,
			WPCPM_Institution_Members::META_PROFILE,
		);
	}

	/**
	 * Module ID.
	 *
	 * @return string
	 */
	public function id() {
		return 'institutions';
	}

	/**
	 * Module label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Institutions', 'wpcredits-program-manager' );
	}

	/**
	 * Managed role.
	 *
	 * @return string
	 */
	public function role() {
		return WPCPM_Roles::ROLE_INSTITUTION;
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Educational institution records from Airtable, the students each one has in the program, and the state of every Collaboration Agreement. Institution accounts are based on the Subscriber role, with access to Institution-level content.', 'wpcredits-program-manager' );
	}

	/**
	 * This module is built.
	 *
	 * @return bool
	 */
	public function is_implemented() {
		return true;
	}

	/**
	 * Hooks.
	 */
	public function boot() {
		// The two private post types this module keeps, registered on `init` the way the
		// Students module boots its helpers.
		WPCPM_Institution_Agreement::init();
		WPCPM_Institution_Audit::init();

		WPCPM_Institutions_Sync::register_cron();

		add_action( 'admin_post_' . self::ACTION_SYNC, array( $this, 'handle_sync' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( $this, 'handle_cancel' ) );
		add_action( 'admin_post_' . self::ACTION_PROBE, array( $this, 'handle_probe' ) );
		add_action( 'wp_ajax_' . self::ACTION_TICK, array( $this, 'handle_tick' ) );
	}

	/**
	 * Activation: the private directory and its probe, the countries map, the sync schedule.
	 *
	 * The countries refresh is best effort. It needs a connected base, and activation must
	 * finish whether or not one is configured yet; the sync's own `countries` phase reads the
	 * table again on its first run anyway.
	 */
	public function activate() {
		WPCPM_Private_Files::ensure();
		WPCPM_Private_Files::probe();

		if ( WPCPM_Settings::is_connected() ) {
			WPCPM_Countries::refresh();
		}

		WPCPM_Institutions_Sync::activate();
	}

	/**
	 * Deactivation: stop scheduled work.
	 */
	public function deactivate() {
		WPCPM_Institutions_Sync::deactivate();
	}

	/**
	 * Uninstall: drop the indexes, the agreement state, the audit log and the stamps.
	 *
	 * Accounts are left alone, they are people. The signed agreement files under uploads are
	 * left alone too, on purpose: the design spec's risk register names them as data the next
	 * site owner must be told about, and a plugin removal is not the moment to shred a signed
	 * document nobody has another copy of.
	 */
	public function uninstall() {
		delete_option( WPCPM_Institutions_Sync::OPT_STATE );
		delete_option( WPCPM_Institutions_Sync::OPT_REPORT );
		delete_option( WPCPM_Institutions_Sync::OPT_LAST );
		delete_option( WPCPM_Institutions_Sync::OPT_ERROR );
		delete_option( WPCPM_Institutions_Sync::OPT_LOCK );

		delete_option( WPCPM_Institutions_Index::OPTION );
		delete_option( WPCPM_Countries::OPTION );
		delete_option( WPCPM_Private_Files::OPTION_PROBE );

		WPCPM_Roster_Index::delete_all();
		WPCPM_Institution_Agreement::delete_all();
		WPCPM_Institution_Audit::delete_all();

		foreach ( self::member_meta() as $meta_key ) {
			delete_metadata( 'user', 0, $meta_key, '', true );
		}
	}

	/*
	 * Handlers
	 * --------------------------------------------------------------------
	 */

	/**
	 * Advance the sync one slice and report progress.
	 */
	public function handle_tick() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ) ), 403 );
		}

		check_ajax_referer( self::ACTION_TICK, 'nonce' );

		if ( WPCPM_Institutions_Sync::is_running() ) {
			WPCPM_Institutions_Sync::tick( WPCPM_Institutions_Sync::BUDGET_AJAX );
		}

		wp_send_json_success( WPCPM_Institutions_Sync::progress() );
	}

	/**
	 * Start a sync.
	 */
	public function handle_sync() {
		$this->verify( self::ACTION_SYNC );

		$result = WPCPM_Institutions_Sync::start();

		$this->redirect_back( is_wp_error( $result ) ? 'error' : 'started' );
	}

	/**
	 * Abandon a stuck sync.
	 */
	public function handle_cancel() {
		$this->verify( self::ACTION_CANCEL );

		WPCPM_Institutions_Sync::cancel();

		$this->redirect_back( 'cancelled' );
	}

	/**
	 * Ask the host what it does with the private directory.
	 */
	public function handle_probe() {
		$this->verify( self::ACTION_PROBE );

		$result = WPCPM_Private_Files::probe();

		$this->redirect_back( '' !== $result['error'] ? 'probe-failed' : 'probed' );
	}

	/**
	 * Capability and nonce check, in that order.
	 *
	 * @param string $action Nonce action.
	 */
	private function verify( $action ) {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		check_admin_referer( $action );
	}

	/**
	 * Return to the module screen with a one-shot outcome.
	 *
	 * A flash rather than a query argument, so the outcome shows once and a reload of the
	 * screen does not say "Sync started" a second time.
	 *
	 * @param string $status Outcome slug.
	 */
	private function redirect_back( $status ) {
		WPCPM_Flash::set( self::FLASH, $status );

		wp_safe_redirect( $this->admin_url() );
		exit;
	}

	/*
	 * Notifications
	 * --------------------------------------------------------------------
	 */

	/**
	 * Tell the program managers something.
	 *
	 * The one mechanism for every queue event: recipients are the `agreement_notify` setting
	 * when it names anybody, otherwise every account holding the management capability that
	 * has an address. Program managers here are WordPress administrators, so the default
	 * reaches the technical ones too; the setting narrows it and should be set before the
	 * first real upload. Every send goes through `WPCPM_Mail`, so it is logged and filtered
	 * like any other message: `send()` for an address that belongs to an account, so the
	 * message is built in that person's language, and `send_to()` for a bare address.
	 *
	 * The country contact is named in a body when a message is about their country, never
	 * added here as a recipient: routing is information, not a mailing list.
	 *
	 * @param string   $context Short label for the mail log, e.g. `agreement-landed`.
	 * @param callable $build   Builder in `WPCPM_Mail::send()`'s shape. It receives a `WP_User`
	 *                          for an account and the address string for a bare address, and
	 *                          returns `subject`, `body` and optionally `headers`.
	 * @return int How many messages were handed off.
	 */
	public static function notify_managers( $context, $build ) {
		if ( ! is_callable( $build ) ) {
			return 0;
		}

		$sent    = 0;
		$setting = trim( (string) WPCPM_Settings::get_value( 'agreement_notify', '' ) );

		if ( '' !== $setting ) {
			$addresses = array_unique( array_filter( array_map( 'trim', explode( ',', $setting ) ) ) );

			foreach ( $addresses as $address ) {
				$user = get_user_by( 'email', $address );

				if ( $user instanceof WP_User && $user->exists() ) {
					$sent += WPCPM_Mail::send( $user, $context, $build ) ? 1 : 0;
				} else {
					$sent += WPCPM_Mail::send_to( $address, $context, $build ) ? 1 : 0;
				}
			}

			return $sent;
		}

		foreach ( self::managers() as $manager ) {
			$sent += WPCPM_Mail::send( $manager, $context, $build ) ? 1 : 0;
		}

		return $sent;
	}

	/**
	 * Every account holding the management capability that has an address.
	 *
	 * The query narrows by capability, which WordPress resolves to the roles that grant it;
	 * `user_can()` then decides per account, so a capability removed from a role by another
	 * plugin, or granted to one account by hand, is answered by the same test the screens use.
	 *
	 * @return WP_User[]
	 */
	public static function managers() {
		$users = get_users(
			array(
				'capability' => WPCPM_Roles::CAP_MANAGE,
				'orderby'    => 'ID',
				'order'      => 'ASC',
				'number'     => 200,
			)
		);

		$managers = array();

		foreach ( (array) $users as $user ) {
			if ( ! $user instanceof WP_User || '' === trim( (string) $user->user_email ) ) {
				continue;
			}

			if ( user_can( $user, WPCPM_Roles::CAP_MANAGE ) ) {
				$managers[] = $user;
			}
		}

		return $managers;
	}

	/*
	 * The screen
	 * --------------------------------------------------------------------
	 */

	/**
	 * Render the Institutions screen.
	 */
	public function render_admin_page() {
		$index    = WPCPM_Institutions_Index::read();
		$counts   = WPCPM_Roster_Index::counts();
		$progress = WPCPM_Institutions_Sync::progress();
		$last     = (int) WPCPM_Institutions_Sync::last_read();
		$filter   = WPCPM_Request::key( 'wpcpm_filter' );

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html( $this->label() ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html( $this->description() ) . '</p>';

		$status = sanitize_key( (string) WPCPM_Flash::take( self::FLASH ) );

		$messages = array(
			'started'      => array( 'success', __( 'Sync started. Progress is shown below and updates as it runs.', 'wpcredits-program-manager' ) ),
			'cancelled'    => array( 'info', __( 'Sync canceled.', 'wpcredits-program-manager' ) ),
			'probed'       => array( 'success', __( 'The probe ran. The storage card says what the host did.', 'wpcredits-program-manager' ) ),
			'probe-failed' => array( 'error', __( 'The probe could not be completed. The storage card says why.', 'wpcredits-program-manager' ) ),
			'error'        => array( 'error', __( 'That action could not be completed.', 'wpcredits-program-manager' ) ),
		);

		if ( isset( $messages[ $status ] ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $messages[ $status ][0] ),
				esc_html( $messages[ $status ][1] )
			);
		}

		if ( ! WPCPM_Settings::is_connected() ) {
			printf(
				'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
				esc_html__( 'Airtable is not connected yet, so no institutions can be synced.', 'wpcredits-program-manager' ),
				esc_url( admin_url( 'admin.php?page=wpcpm-settings' ) ),
				esc_html__( 'Open settings', 'wpcredits-program-manager' )
			);
		}

		if ( ! empty( $progress['error'] ) ) {
			printf(
				'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html__( 'Last sync error:', 'wpcredits-program-manager' ),
				esc_html( (string) $progress['error'] )
			);
		}

		// Read once for the whole screen: the two membership counts live on two different
		// cards, and `members_of()` is a query per institution, so computing them twice
		// would double every one of them for a number that cannot have changed in between.
		$gaps = self::membership_gaps( isset( $index['rows'] ) && is_array( $index['rows'] ) ? $index['rows'] : array() );

		$this->render_sync_panel( $progress, $last );
		$this->render_pipeline( $index, $filter, $gaps );
		$this->render_reconciliation( $counts, $index, $gaps );
		$this->render_consent( $index );
		$this->render_discrepancies( $index );
		$this->render_template( $index );
		$this->render_storage();

		echo '</div>';
	}

	/**
	 * Sync controls and live progress.
	 *
	 * @param array $progress Progress payload from `WPCPM_Institutions_Sync::progress()`.
	 * @param int   $last     Timestamp of the last completed run.
	 */
	private function render_sync_panel( array $progress, $last ) {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Airtable sync', 'wpcredits-program-manager' ) . '</h2>';

		echo '<p class="description">' . esc_html__( 'Reads the Countries table, then every institution record\'s public columns and the eight agreement columns, and rebuilds the pipeline index and each institution\'s agreement state. The prose fields are never read.', 'wpcredits-program-manager' ) . '</p>';

		if ( ! empty( $progress['running'] ) ) {
			printf(
				'<div class="wpcpm-progress" data-wpcpm-progress data-action="%1$s" data-nonce="%2$s" data-poll="3">',
				esc_attr( self::ACTION_TICK ),
				esc_attr( wp_create_nonce( self::ACTION_TICK ) )
			);

			echo '<p class="wpcpm-progress__head"><span class="spinner is-active" aria-hidden="true"></span> ';
			printf( '<strong data-wpcpm-label>%s</strong>', esc_html( isset( $progress['label'] ) ? $progress['label'] : '' ) );
			printf( ' <span class="wpcpm-progress__step" data-wpcpm-step>%s</span>', esc_html( isset( $progress['step_label'] ) ? $progress['step_label'] : '' ) );
			echo '</p>';

			$percent = isset( $progress['percent'] ) ? (int) $progress['percent'] : 0;

			printf(
				'<div class="wpcpm-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="%1$d" aria-label="%2$s" data-wpcpm-bar><div class="wpcpm-bar__fill" style="width:%1$d%%" data-wpcpm-fill></div></div>',
				(int) $percent,
				esc_attr__( 'Sync progress', 'wpcredits-program-manager' )
			);

			echo '<p class="wpcpm-progress__meta">';
			printf( '<span data-wpcpm-percent>%d%%</span> - ', (int) $percent );
			printf( '<span data-wpcpm-detail>%s</span> - ', esc_html( isset( $progress['detail'] ) ? $progress['detail'] : '' ) );
			/* translators: %s: elapsed time as a clock value. */
			$elapsed_label = __( 'running for %s', 'wpcredits-program-manager' );
			printf(
				'<span data-wpcpm-elapsed data-label="%1$s">%2$s</span>',
				esc_attr( $elapsed_label ),
				esc_html( sprintf( $elapsed_label, WPCPM_Mentors::format_duration( isset( $progress['elapsed'] ) ? (int) $progress['elapsed'] : 0 ) ) )
			);
			echo '</p>';

			printf(
				'<p class="wpcpm-progress__stalled" data-wpcpm-stalled%1$s>%2$s</p>',
				! empty( $progress['stalled'] ) ? '' : ' hidden',
				esc_html__( 'No progress for over two minutes. The run may have been interrupted: cancel it and start again.', 'wpcredits-program-manager' )
			);

			echo '<noscript><meta http-equiv="refresh" content="15" /></noscript>';
			echo '</div>';

			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::ACTION_CANCEL );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_CANCEL ) . '" />';
			submit_button( __( 'Cancel sync', 'wpcredits-program-manager' ), 'secondary', 'submit', false );
			echo '</form>';
		} else {
			if ( $last ) {
				printf(
					'<p>%s</p>',
					esc_html(
						sprintf(
							/* translators: 1: date and time, 2: human-readable time difference. */
							__( 'Last completed %1$s (%2$s ago).', 'wpcredits-program-manager' ),
							wp_date( 'Y-m-d H:i', $last ),
							human_time_diff( $last, time() )
						)
					)
				);
			} else {
				echo '<p>' . esc_html__( 'No sync has run yet.', 'wpcredits-program-manager' ) . '</p>';
			}

			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::ACTION_SYNC );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SYNC ) . '" />';
			submit_button( __( 'Sync institutions now', 'wpcredits-program-manager' ), 'primary', 'submit', false );
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * The pipeline: every institution record, grouped by stage.
	 *
	 * @param array  $index  The pipeline index, from `WPCPM_Institutions_Index::read()`.
	 * @param string $filter The `wpcpm_filter` argument, already sanitised.
	 * @param array  $gaps   The membership counts, from `membership_gaps()`.
	 */
	private function render_pipeline( array $index, $filter, array $gaps ) {
		$rows      = isset( $index['rows'] ) && is_array( $index['rows'] ) ? $index['rows'] : array();
		$read      = isset( $index['read'] ) ? (int) $index['read'] : 0;
		$summaries = array();

		foreach ( $rows as $record_id => $row ) {
			$summaries[ $record_id ] = WPCPM_Institution_Agreement::summary( $record_id );
		}

		$gap = self::agreement_gap( $rows, $summaries );

		echo '<div class="wpcpm-card">';
		printf(
			'<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>',
			esc_html__( 'Pipeline', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( count( $rows ) ) )
		);

		$this->read_line( $read, __( 'Pipeline index', 'wpcredits-program-manager' ) );

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No institution records yet. Run a sync to read them.', 'wpcredits-program-manager' ) . '</p>';
			echo '</div>';

			return;
		}

		if ( self::FILTER_GAP === $filter ) {
			printf(
				'<p class="wpcpm-inst-gap">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html(
					sprintf(
						/* translators: %s: number of institutions. */
						_n(
							'Showing the %s Confirmed institution with no agreement recorded.',
							'Showing the %s Confirmed institutions with no agreement recorded.',
							count( $gap ),
							'wpcredits-program-manager'
						),
						number_format_i18n( count( $gap ) )
					)
				),
				esc_url( $this->admin_url() ),
				esc_html__( 'Show every stage', 'wpcredits-program-manager' )
			);
		} else {
			printf(
				'<p class="wpcpm-inst-gap"><a href="%1$s">%2$s <span class="wpcpm-count">%3$s</span></a></p>',
				esc_url( add_query_arg( 'wpcpm_filter', self::FILTER_GAP, $this->admin_url() ) ),
				esc_html__( 'Confirmed with no agreement recorded', 'wpcredits-program-manager' ),
				esc_html( number_format_i18n( count( $gap ) ) )
			);
		}

		$this->render_member_gap( $gaps, $read );
		$this->render_routing_gaps( $rows );

		if ( self::FILTER_GAP === $filter ) {
			$groups = array( 'Confirmed' => $gap );
		} else {
			$groups = WPCPM_Institutions_Index::by_stage();
		}

		foreach ( $groups as $stage => $group ) {
			if ( empty( $group ) ) {
				continue;
			}

			printf(
				'<h3 class="wpcpm-inst-stage">%1$s <span class="wpcpm-count">%2$s</span></h3>',
				esc_html( self::stage_label( $stage ) ),
				esc_html( number_format_i18n( count( $group ) ) )
			);

			echo '<table class="widefat striped wpcpm-list wpcpm-inst-table"><thead><tr>';
			echo '<th scope="col">' . esc_html__( 'Institution', 'wpcredits-program-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Country', 'wpcredits-program-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'City', 'wpcredits-program-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Contact', 'wpcredits-program-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Consent', 'wpcredits-program-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Created', 'wpcredits-program-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Agreement', 'wpcredits-program-manager' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $group as $row ) {
				$record_id = isset( $row['record_id'] ) ? (string) $row['record_id'] : '';
				$summary   = isset( $summaries[ $record_id ] ) ? $summaries[ $record_id ] : WPCPM_Institution_Agreement::summary( $record_id );

				$this->render_pipeline_row( $row, $summary );
			}

			echo '</tbody></table>';
		}

		echo '</div>';
	}

	/**
	 * One institution's row in the pipeline.
	 *
	 * The name prints trimmed and the row says when the stored one was not: ten names in
	 * the base end in a space and two records have none, and a manager searching the grid
	 * for "Sorbonne university" should know why the match is not exact.
	 *
	 * @param array $row     An index row.
	 * @param array $summary The institution's agreement summary.
	 */
	private function render_pipeline_row( array $row, array $summary ) {
		$name    = isset( $row['name'] ) ? (string) $row['name'] : '';
		$trimmed = trim( $name );

		echo '<tr>';

		echo '<td class="wpcpm-inst-name">';

		if ( '' === $trimmed ) {
			printf(
				'<span class="wpcpm-inst-name__text wpcpm-inst-muted">%1$s</span> <span class="wpcpm-inst-mark wpcpm-inst-mark--empty" title="%2$s">%3$s</span>',
				esc_html__( '(no name)', 'wpcredits-program-manager' ),
				esc_attr__( 'This record has no Name in Airtable.', 'wpcredits-program-manager' ),
				esc_html__( 'no name', 'wpcredits-program-manager' )
			);
		} else {
			printf( '<span class="wpcpm-inst-name__text">%s</span>', esc_html( $trimmed ) );

			if ( $name !== $trimmed ) {
				printf(
					' <span class="wpcpm-inst-mark wpcpm-inst-mark--space" title="%1$s">%2$s</span>',
					esc_attr__( 'The Name in Airtable has whitespace around it; it prints trimmed here.', 'wpcredits-program-manager' ),
					esc_html__( 'whitespace', 'wpcredits-program-manager' )
				);
			}
		}

		if ( ! empty( $row['record_id'] ) ) {
			printf( '<br /><code class="wpcpm-inst-record">%s</code>', esc_html( (string) $row['record_id'] ) );
		}

		echo '</td>';

		$country = isset( $row['country'] ) ? (string) $row['country'] : '';

		if ( '' === $country ) {
			printf( '<td><span class="wpcpm-inst-muted">%s</span></td>', esc_html__( 'no country', 'wpcredits-program-manager' ) );
		} else {
			$country_name = WPCPM_Countries::name_of( $country );

			if ( '' === $country_name ) {
				$country_name = isset( $row['country_name'] ) ? (string) $row['country_name'] : '';
			}

			echo '<td>';
			echo esc_html( '' !== $country_name ? $country_name : __( 'unknown country', 'wpcredits-program-manager' ) );

			if ( null === WPCPM_Countries::routing( $country ) ) {
				printf(
					' <span class="wpcpm-inst-mark wpcpm-inst-mark--routing" title="%1$s">%2$s</span>',
					esc_attr__( 'The Countries table names no program manager for this country.', 'wpcredits-program-manager' ),
					esc_html__( 'no contact', 'wpcredits-program-manager' )
				);
			}

			echo '</td>';
		}

		printf( '<td>%s</td>', esc_html( isset( $row['city'] ) ? (string) $row['city'] : '' ) );

		if ( ! empty( $row['contact_email'] ) ) {
			printf( '<td>%s</td>', esc_html__( 'email on record', 'wpcredits-program-manager' ) );
		} else {
			printf( '<td><span class="wpcpm-warning">%s</span></td>', esc_html__( 'no email', 'wpcredits-program-manager' ) );
		}

		printf(
			'<td>%s</td>',
			! empty( $row['consent'] ) ? esc_html__( 'yes', 'wpcredits-program-manager' ) : esc_html__( 'no', 'wpcredits-program-manager' )
		);

		printf( '<td>%s</td>', esc_html( isset( $row['created'] ) ? (string) $row['created'] : '' ) );

		printf(
			'<td class="wpcpm-inst-agreement%1$s">%2$s</td>',
			self::is_settled_summary( $summary ) ? ' wpcpm-inst-agreement--settled' : '',
			esc_html( self::describe_summary( $summary ) )
		);

		echo '</tr>';
	}

	/**
	 * Institutions nobody can act for, and the backstop count that is not shown yet.
	 *
	 * The design calls this "the one that pages a manager", so it prints inside the pipeline
	 * card where a manager looks first rather than among the reconciliation rows at the foot
	 * of the screen. An institution with no live member has nobody at the school who can see
	 * their own roster, upload their signed agreement, or answer for it.
	 *
	 * The third count the design asks for, invitations older than seven days, is a line and
	 * not a number: the invitation post type ships with a later phase, and a zero printed
	 * beside two real counts would read as "none are overdue" rather than "none exist".
	 *
	 * @param array $gaps From `membership_gaps()`.
	 * @param int   $read Unix time the pipeline index was read.
	 */
	private function render_member_gap( array $gaps, $read ) {
		printf(
			'<p class="wpcpm-inst-members">%1$s <span class="wpcpm-count">%2$s</span> <span class="wpcpm-inst-muted">%3$s</span></p>',
			esc_html__( 'Institutions with no live member', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( isset( $gaps['no_member'] ) ? (int) $gaps['no_member'] : 0 ) ),
			esc_html( self::membership_read_line( $read ) )
		);

		echo '<p class="description">' . esc_html__( 'Nobody at these schools can act for them on this site. Adding an account by hand ships with the accounts phase; today the only route in is the sync provisioning an institution\'s Contact Email.', 'wpcredits-program-manager' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Invitations ship with a later phase, so the third backstop count, invitations older than seven days, is not shown: there are none to count yet.', 'wpcredits-program-manager' ) . '</p>';
	}

	/**
	 * Countries that institutions name and the Countries table routes nowhere.
	 *
	 * A routing gap, never an error: the acknowledgement for an applicant from one of these
	 * countries has no manager to name, and the queue shows no contact for information. Read
	 * from the index rows and the countries map, so the list is the three the base has today
	 * and not the fifty-eight countries nobody has applied from.
	 *
	 * @param array $rows Index rows.
	 */
	private function render_routing_gaps( array $rows ) {
		$gaps      = array();
		$countries = WPCPM_Countries::read();
		$read      = isset( $countries['read'] ) ? (int) $countries['read'] : 0;

		foreach ( $rows as $row ) {
			$country = isset( $row['country'] ) ? (string) $row['country'] : '';

			if ( '' === $country || null !== WPCPM_Countries::routing( $country ) ) {
				continue;
			}

			$label = WPCPM_Countries::name_of( $country );

			if ( '' === $label ) {
				$label = isset( $row['country_name'] ) && '' !== (string) $row['country_name']
					? (string) $row['country_name']
					: $country;
			}

			$gaps[ $label ] = isset( $gaps[ $label ] ) ? $gaps[ $label ] + 1 : 1;
		}

		$this->read_line( $read, __( 'Countries map', 'wpcredits-program-manager' ) );

		if ( empty( $gaps ) ) {
			echo '<p>' . esc_html__( 'Every country an institution names has a program manager contact.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		ksort( $gaps );

		$parts = array();

		foreach ( $gaps as $label => $count ) {
			$parts[] = sprintf( '%1$s (%2$s)', $label, number_format_i18n( $count ) );
		}

		printf(
			'<p class="wpcpm-inst-routing">%1$s %2$s</p>',
			esc_html__( 'Countries named by institutions with no program manager contact in the Countries table:', 'wpcredits-program-manager' ),
			esc_html( implode( ', ', $parts ) )
		);
	}

	/**
	 * The reconciliation between the Students table and Students Reports.
	 *
	 * The numbers come from the students sync's last run; the two live counts are the tracked
	 * student accounts carrying no institution stamp, which should be zero and is a broken
	 * sync when it is not, and the institutions whose Contact Email belongs to no member.
	 *
	 * @param array $counts From `WPCPM_Roster_Index::counts()`.
	 * @param array $index  The pipeline index, for the contact addresses' read time.
	 * @param array $gaps   The membership counts, from `membership_gaps()`.
	 */
	private function render_reconciliation( array $counts, array $index, array $gaps ) {
		$read = isset( $counts['read'] ) ? (int) $counts['read'] : 0;
		$rec  = isset( $counts['reconciliation'] ) && is_array( $counts['reconciliation'] ) ? $counts['reconciliation'] : array();

		$without_reports  = isset( $rec['students_without_reports'] ) ? (array) $rec['students_without_reports'] : array();
		$without_students = isset( $rec['reports_without_students'] ) ? (array) $rec['reports_without_students'] : array();
		$disagreements    = isset( $rec['status_disagreements'] ) ? (int) $rec['status_disagreements'] : 0;
		$duplicates       = isset( $rec['duplicate_emails'] ) ? (array) $rec['duplicate_emails'] : array();
		$no_institution   = isset( $rec['no_institution'] ) ? (int) $rec['no_institution'] : 0;
		$no_start         = isset( $rec['no_start_date'] ) ? (array) $rec['no_start_date'] : array();
		$unstamped        = self::unstamped_students();

		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Reconciliation', 'wpcredits-program-manager' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'The Students table and Students Reports describe the same people and are joined by email. These are the rows where the two disagree or one side is missing, from the students sync\'s last run.', 'wpcredits-program-manager' ) . '</p>';

		$this->read_line( $read, __( 'Roster counts', 'wpcredits-program-manager' ) );

		echo '<table class="wpcpm-table wpcpm-inst-recon"><tbody>';

		$this->recon_row(
			__( 'Students rows with no reports row', 'wpcredits-program-manager' ),
			array_sum( array_map( 'intval', $without_reports ) ),
			self::breakdown( $without_reports )
		);
		$this->recon_row(
			__( 'Reports rows with no Students row', 'wpcredits-program-manager' ),
			array_sum( array_map( 'intval', $without_students ) ),
			self::breakdown( $without_students )
		);
		$this->recon_row( __( 'Status disagreements on joined rows', 'wpcredits-program-manager' ), $disagreements, '' );

		$duplicate_parts = array();

		foreach ( $duplicates as $record_id => $count ) {
			$duplicate_parts[] = sprintf( '%1$s (%2$s)', self::institution_name( (string) $record_id ), number_format_i18n( (int) $count ) );
		}

		$this->recon_row(
			__( 'Duplicate emails in the Students table', 'wpcredits-program-manager' ),
			array_sum( array_map( 'intval', $duplicates ) ),
			implode( ', ', $duplicate_parts )
		);
		$this->recon_row( __( 'Students rows with no institution', 'wpcredits-program-manager' ), $no_institution, '' );
		$this->recon_row(
			__( 'Students rows with no start date', 'wpcredits-program-manager' ),
			array_sum( array_map( 'intval', $no_start ) ),
			self::breakdown( $no_start )
		);

		printf(
			'<tr><th scope="row">%1$s</th><td%2$s>%3$s <span class="wpcpm-inst-muted">%4$s</span></td></tr>',
			esc_html__( 'Tracked student accounts with no institution stamp', 'wpcredits-program-manager' ),
			$unstamped > 0 ? ' class="wpcpm-warning"' : '',
			esc_html( number_format_i18n( $unstamped ) ),
			$unstamped > 0
				? esc_html__( '(counted now; should be 0, anything else is a broken sync)', 'wpcredits-program-manager' )
				: esc_html__( '(counted now)', 'wpcredits-program-manager' )
		);

		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s <span class="wpcpm-inst-muted">%3$s</span></td></tr>',
			esc_html__( 'Contacts who are not members', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( isset( $gaps['contact_not_member'] ) ? (int) $gaps['contact_not_member'] : 0 ) ),
			esc_html( self::membership_read_line( isset( $index['read'] ) ? (int) $index['read'] : 0 ) )
		);

		echo '</tbody></table>';

		echo '<p class="description">' . esc_html__( 'A Contact Email that belongs to no member is the address Airtable names for the institution and nobody who can act for them here. Adding that person in one click ships with the accounts phase; until then the sync provisions the address only for an institution that has never had a member, so a removed contact is not re-created every night.', 'wpcredits-program-manager' ) . '</p>';

		$unlinked = WPCPM_Roster_Index::unlinked();

		if ( ! empty( $unlinked ) ) {
			echo '<h3>' . esc_html__( 'Rows with no institution', 'wpcredits-program-manager' ) . '</h3>';
			echo '<ul class="wpcpm-notices wpcpm-inst-unlinked">';

			foreach ( $unlinked as $row ) {
				$status = isset( $row['status'] ) ? trim( (string) $row['status'] ) : '';

				printf(
					'<li>%1$s <span class="wpcpm-inst-muted">%2$s</span></li>',
					esc_html( isset( $row['name'] ) && '' !== trim( (string) $row['name'] ) ? trim( (string) $row['name'] ) : __( '(no name)', 'wpcredits-program-manager' ) ),
					esc_html( '' !== $status ? $status : __( '(no status)', 'wpcredits-program-manager' ) )
				);
			}

			echo '</ul>';
			echo '<p class="description">' . esc_html__( 'Linking a row to an institution from here ships with the next phase. Until then, set Educational Institutions on the row in Airtable.', 'wpcredits-program-manager' ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * One row of the reconciliation table.
	 *
	 * @param string $label     What is counted.
	 * @param int    $count     The count.
	 * @param string $breakdown The split by status, or an empty string.
	 */
	private function recon_row( $label, $count, $breakdown ) {
		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s%3$s</td></tr>',
			esc_html( $label ),
			esc_html( number_format_i18n( (int) $count ) ),
			'' !== $breakdown ? ' <span class="wpcpm-inst-muted">(' . esc_html( $breakdown ) . ')</span>' : ''
		);
	}

	/**
	 * The consent report.
	 *
	 * The brief read 79 records with the required answer and 16 with the tick as consent
	 * dropped between form and record. It is a date boundary: the checkbox was added to the
	 * form on 20 July 2026. So the sentence here is the one the design spec fixes, computed
	 * from the index, and it never says anything went missing, because nothing was asked.
	 *
	 * @param array $index The pipeline index.
	 */
	private function render_consent( array $index ) {
		$rows   = isset( $index['rows'] ) && is_array( $index['rows'] ) ? $index['rows'] : array();
		$read   = isset( $index['read'] ) ? (int) $index['read'] : 0;
		$counts = self::consent_counts( $rows );

		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Consent', 'wpcredits-program-manager' ) . '</h2>';

		$this->read_line( $read, __( 'Pipeline index', 'wpcredits-program-manager' ) );

		printf(
			'<p class="wpcpm-inst-consent">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: number of records, 2: number of those at the Confirmed stage. */
					__( '%1$s institution records were collected before the consent question was added on 20 July 2026, %2$s of them at Confirmed.', 'wpcredits-program-manager' ),
					number_format_i18n( $counts['before'] ),
					number_format_i18n( $counts['before_confirmed'] )
				)
			)
		);

		echo '<p class="description">' . esc_html__( 'None of them carries the Privacy Policy Compliance tick because the form did not ask. For the Confirmed ones the signed Collaboration Agreement is the basis, recorded per institution in the pipeline\'s agreement column.', 'wpcredits-program-manager' ) . '</p>';

		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: number of records. */
					_n(
						'Since then, %s record has been created without the tick, which is how a record entered by hand in the grid looks.',
						'Since then, %s records have been created without the tick, which is how records entered by hand in the grid look.',
						$counts['since_without'],
						'wpcredits-program-manager'
					),
					number_format_i18n( $counts['since_without'] )
				)
			)
		);

		echo '</div>';
	}

	/**
	 * Institutions whose site-side agreement state and Airtable's disagree.
	 *
	 * Each one is locked until the two agree, which is the point: a manager typing Revoked
	 * into the grid must lock, and a site-side revoke must survive the next rebuild.
	 *
	 * @param array $index The pipeline index, for the names.
	 */
	private function render_discrepancies( array $index ) {
		$discrepancies = WPCPM_Institution_Agreement::discrepancies();
		$read          = isset( $index['read'] ) ? (int) $index['read'] : 0;

		echo '<div class="wpcpm-card">';
		printf(
			'<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>',
			esc_html__( 'Agreement discrepancies', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( count( $discrepancies ) ) )
		);
		echo '<p class="description">' . esc_html__( 'An institution is settled only when the site\'s record and Airtable\'s Agreement Status agree. These are the ones where they do not; each is locked until they do.', 'wpcredits-program-manager' ) . '</p>';

		$this->read_line( $read, __( 'Pipeline index', 'wpcredits-program-manager' ) );

		if ( empty( $discrepancies ) ) {
			echo '<p>' . esc_html__( 'The site and Airtable agree on every agreement.', 'wpcredits-program-manager' ) . '</p>';
			echo '</div>';

			return;
		}

		echo '<table class="widefat striped wpcpm-list"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Institution', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'On the site', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'In Airtable', 'wpcredits-program-manager' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $discrepancies as $record_id => $sides ) {
			$site     = isset( $sides['site_state'] ) ? (string) $sides['site_state'] : '';
			$airtable = isset( $sides['airtable_status'] ) ? (string) $sides['airtable_status'] : '';

			printf(
				'<tr><td>%1$s<br /><code>%2$s</code></td><td>%3$s</td><td>%4$s</td></tr>',
				esc_html( self::institution_name( (string) $record_id ) ),
				esc_html( (string) $record_id ),
				esc_html( '' !== $site ? $site : __( '(nothing recorded)', 'wpcredits-program-manager' ) ),
				esc_html( '' !== $airtable ? $airtable : __( '(empty)', 'wpcredits-program-manager' ) )
			);
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * The plugin's copy of the Collaboration Agreement: version, read date, source, checksum.
	 *
	 * No drift button in this phase. The Doc is world-editable, so a drift check is a button a
	 * manager presses on purpose and reads with care, never a schedule; it ships with the
	 * generate path.
	 *
	 * @param array $index The pipeline index, for the institutions listed per signed version.
	 */
	private function render_template( array $index ) {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Agreement template', 'wpcredits-program-manager' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'The program\'s Google Doc is the master for what the agreement says; the plugin\'s copy is the master for what the site generates. The version is the Doc\'s modified date when the copy was taken.', 'wpcredits-program-manager' ) . '</p>';

		$languages = WPCPM_Agreement_Template::languages();
		$generated = array();

		if ( empty( $languages ) ) {
			echo '<p class="wpcpm-warning">' . esc_html__( 'No agreement template file was found.', 'wpcredits-program-manager' ) . '</p>';
		}

		foreach ( $languages as $language ) {
			$template = WPCPM_Agreement_Template::load( $language );

			if ( is_wp_error( $template ) ) {
				printf(
					'<p class="wpcpm-warning"><code>%1$s</code>: %2$s</p>',
					esc_html( $language ),
					esc_html( $template->get_error_message() )
				);

				continue;
			}

			$generated[] = sprintf(
				/* translators: 1: template version, 2: language code. */
				__( '%1$s (%2$s)', 'wpcredits-program-manager' ),
				WPCPM_Agreement_Template::version( $template ),
				$language
			);

			echo '<table class="wpcpm-table"><tbody>';
			printf( '<tr><th scope="row">%1$s</th><td><code>%2$s</code></td></tr>', esc_html__( 'Language', 'wpcredits-program-manager' ), esc_html( $language ) );
			printf( '<tr><th scope="row">%1$s</th><td>%2$s</td></tr>', esc_html__( 'Version', 'wpcredits-program-manager' ), esc_html( WPCPM_Agreement_Template::version( $template ) ) );
			printf( '<tr><th scope="row">%1$s</th><td>%2$s</td></tr>', esc_html__( 'Copied from the Doc on', 'wpcredits-program-manager' ), esc_html( isset( $template['read'] ) ? (string) $template['read'] : '' ) );
			printf(
				'<tr><th scope="row">%1$s</th><td><a href="%2$s" target="_blank" rel="noopener">%3$s</a></td></tr>',
				esc_html__( 'Source', 'wpcredits-program-manager' ),
				esc_url( isset( $template['source'] ) ? (string) $template['source'] : '' ),
				esc_html__( 'Open the Google Doc', 'wpcredits-program-manager' )
			);
			printf(
				'<tr><th scope="row">%1$s</th><td><code>%2$s</code> <span class="wpcpm-inst-muted">%3$s</span></td></tr>',
				esc_html__( 'Checksum', 'wpcredits-program-manager' ),
				esc_html( substr( WPCPM_Agreement_Template::checksum( $template ), 0, 12 ) ),
				esc_html__( '(the first twelve characters of the sha256 the fixture pins)', 'wpcredits-program-manager' )
			);
			echo '</tbody></table>';
		}

		$this->render_template_versions(
			isset( $index['rows'] ) && is_array( $index['rows'] ) ? $index['rows'] : array(),
			isset( $index['read'] ) ? (int) $index['read'] : 0,
			$generated
		);

		echo '</div>';
	}

	/**
	 * The institutions behind each Agreement Template Version, newest version first.
	 *
	 * Step four of keeping the plugin's copy in step with the Doc: after a wording change,
	 * the institutions that signed the old one are a list a manager reads and decides about.
	 * The module reports the split and never asks anybody to re-sign, because whether a
	 * changed sentence is worth a second signature is a program decision, not a plugin's.
	 *
	 * Only rows whose agreement block says something are grouped. A record at First Contact
	 * Made carries an empty block, and counting it beside the bespoke and legacy copies would
	 * claim the program holds an agreement it has never asked for. Among the ones it does
	 * hold, an empty version is a bespoke or a legacy agreement: signed paper that did not
	 * come from this template, and named as such rather than as "unknown".
	 *
	 * @param array $rows      Index rows.
	 * @param int   $read      Unix time the pipeline index was read.
	 * @param array $generated The version the plugin generates today, one entry per language.
	 */
	private function render_template_versions( array $rows, $read, array $generated ) {
		$by_version = array();

		foreach ( $rows as $row ) {
			$agreement = isset( $row['agreement'] ) && is_array( $row['agreement'] ) ? $row['agreement'] : array();

			if ( ! self::has_agreement( $agreement ) ) {
				continue;
			}

			$version = isset( $agreement['template_version'] ) ? trim( (string) $agreement['template_version'] ) : '';

			$by_version[ $version ][] = self::institution_name( isset( $row['record_id'] ) ? (string) $row['record_id'] : '' );
		}

		echo '<h3>' . esc_html__( 'Institutions per template version', 'wpcredits-program-manager' ) . '</h3>';

		if ( ! empty( $generated ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: version and language, e.g. "2025-11-04 (en)". */
						__( 'The site generates %s today, so anything listed below it was signed against wording the program has changed since.', 'wpcredits-program-manager' ),
						implode( ', ', $generated )
					)
				)
			);
		}

		$this->read_line( $read, __( 'Pipeline index', 'wpcredits-program-manager' ) );

		if ( empty( $by_version ) ) {
			echo '<p>' . esc_html__( 'No institution has an agreement recorded, so no template version has been signed yet.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		// Descending as strings: a version is the Doc's modified date, so the newest sorts
		// first, and the empty key lands last on its own without a rule of its own.
		krsort( $by_version, SORT_STRING );

		echo '<table class="wpcpm-table wpcpm-inst-versions"><tbody>';

		foreach ( $by_version as $version => $names ) {
			$version = trim( (string) $version );

			printf(
				'<tr><th scope="row">%1$s</th><td>%2$s <span class="wpcpm-inst-muted">(%3$s)</span></td></tr>',
				'' !== $version
					? esc_html( $version )
					: esc_html__( 'No version recorded (the bespoke and legacy agreements)', 'wpcredits-program-manager' ),
				esc_html( number_format_i18n( count( $names ) ) ),
				esc_html( implode( ', ', $names ) )
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * The storage card: what the host does with a direct request to the private directory.
	 */
	private function render_storage() {
		$result = WPCPM_Private_Files::probe_result();
		$path   = WPCPM_Private_Files::url_path();

		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Storage', 'wpcredits-program-manager' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Signed agreements are stored under the uploads directory, in a folder whose name begins with a dot, and every file is encrypted before it is written. They are handed out only by the plugin, which checks who is asking. The probe writes a throwaway file, requests it the way a stranger would, and records the answer.', 'wpcredits-program-manager' ) . '</p>';

		// The encryption is the control that does not depend on the host, so it is stated first
		// and stated as a fact about this site rather than as a promise about the directory.
		if ( WPCPM_Private_Files::can_encrypt() ) {
			printf(
				'<p class="wpcpm-inst-status wpcpm-inst-status--ok">%s</p>',
				esc_html__( 'Stored files are encrypted with AES-256-GCM. The key is held on this site and never leaves it, so a copy of the file taken from disk is unreadable without it.', 'wpcredits-program-manager' )
			);
		} else {
			printf(
				'<p class="wpcpm-warning wpcpm-inst-status wpcpm-inst-status--warn">%s</p>',
				esc_html__( 'This site cannot encrypt stored files: PHP here has no OpenSSL support for AES-256-GCM. Uploads are refused rather than stored in the clear. Ask the host to enable it.', 'wpcredits-program-manager' )
			);
		}

		if ( null === $result ) {
			echo '<p>' . esc_html__( 'The probe has not run yet.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			$verdict = WPCPM_Private_Files::verdict( $result );
			$when    = $result['time'] ? wp_date( 'Y-m-d H:i', $result['time'] ) : '';
			$control = isset( $result['control_status'] ) ? (int) $result['control_status'] : 0;

			if ( 'blocked' === $verdict ) {
				printf(
					'<p class="wpcpm-inst-status wpcpm-inst-status--ok">%s</p>',
					esc_html(
						sprintf(
							/* translators: 1: HTTP status code, 2: date and time. */
							__( 'The host refuses direct requests to the private directory (HTTP %1$d on %2$s).', 'wpcredits-program-manager' ),
							$result['status'],
							$when
						)
					)
				);

				// Without the control the refusal could be the host blocking all of uploads, and
				// the card would be crediting the directory name for something else entirely.
				if ( $control >= 200 && $control < 300 ) {
					printf(
						'<p class="description">%s</p>',
						esc_html(
							sprintf(
								/* translators: %d: HTTP status code. */
								__( 'The same file in a folder without the leading dot answers HTTP %d on this host, so the dot is what makes the difference. It is a host behaviour rather than a promise, which is why the files are encrypted as well.', 'wpcredits-program-manager' ),
								$control
							)
						)
					);
				}
			} elseif ( 'served' === $verdict ) {
				printf(
					'<p class="wpcpm-warning wpcpm-inst-status wpcpm-inst-status--warn">%s</p>',
					esc_html(
						sprintf(
							/* translators: 1: HTTP status code, 2: date and time, 3: the directory path. */
							__( 'The host hands out files in the private directory to anyone who asks (HTTP %1$d on %2$s). What it hands over is encrypted, and the names are unguessable, so nothing readable is exposed. Even so, %3$s should not be reachable: tell the host, and do not store anything here that is not encrypted by this plugin.', 'wpcredits-program-manager' ),
							$result['status'],
							$when,
							$path
						)
					)
				);
			} else {
				printf(
					'<p class="wpcpm-inst-status wpcpm-inst-status--unknown">%s</p>',
					esc_html(
						'' !== $result['error']
							? sprintf(
								/* translators: 1: date and time, 2: error message. */
								__( 'The probe could not tell what the host does (on %1$s): %2$s', 'wpcredits-program-manager' ),
								$when,
								$result['error']
							)
							: sprintf(
								/* translators: 1: HTTP status code, 2: date and time. */
								__( 'The probe could not tell what the host does: it answered HTTP %1$d on %2$s, which is neither a refusal nor the file.', 'wpcredits-program-manager' ),
								$result['status'],
								$when
							)
					)
				);
			}
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION_PROBE );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_PROBE ) . '" />';
		submit_button( __( 'Run probe', 'wpcredits-program-manager' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '</div>';
	}

	/*
	 * Helpers
	 * --------------------------------------------------------------------
	 */

	/**
	 * Print when a set of numbers was read, so a stale count never looks fresh.
	 *
	 * @param int    $read Unix time the source was read, or 0 for never.
	 * @param string $what What was read, e.g. "Pipeline index".
	 */
	private function read_line( $read, $what ) {
		if ( ! $read ) {
			printf(
				'<p class="wpcpm-inst-read">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: what was read, e.g. "Pipeline index". */
						__( '%s: not read yet.', 'wpcredits-program-manager' ),
						$what
					)
				)
			);

			return;
		}

		printf(
			'<p class="wpcpm-inst-read">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: what was read, 2: date and time, 3: human-readable time difference. */
					__( '%1$s: read %2$s (%3$s ago).', 'wpcredits-program-manager' ),
					$what,
					wp_date( 'Y-m-d H:i', $read ),
					human_time_diff( $read, time() )
				)
			)
		);
	}

	/**
	 * A stage's name as printed, with the empty stage named.
	 *
	 * @param string $stage The stage as stored.
	 * @return string
	 */
	private static function stage_label( $stage ) {
		$stage = trim( (string) $stage );

		return '' !== $stage ? $stage : __( 'No stage', 'wpcredits-program-manager' );
	}

	/**
	 * An institution's name from the index, trimmed, or its record ID when the index has none.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return string
	 */
	private static function institution_name( $record_id ) {
		$row = WPCPM_Institutions_Index::row( $record_id );

		if ( is_array( $row ) && isset( $row['name'] ) && '' !== trim( (string) $row['name'] ) ) {
			return trim( (string) $row['name'] );
		}

		return $record_id;
	}

	/**
	 * Whether a summary is one of the two states that settle an institution.
	 *
	 * @param array $summary From `WPCPM_Institution_Agreement::summary()`.
	 * @return bool
	 */
	private static function is_settled_summary( array $summary ) {
		$state = isset( $summary['state'] ) ? (string) $summary['state'] : '';

		return in_array( $state, array( WPCPM_Institution_Agreement::SUMMARY_ACCEPTED, WPCPM_Institution_Agreement::SUMMARY_ON_FILE ), true );
	}

	/**
	 * The Confirmed rows whose agreement is not recorded, keyed as the index keys them.
	 *
	 * The filter link's count and the filtered view read the same list, so they cannot
	 * disagree. Forty-two rows on day one, every real Confirmed institution being legacy.
	 *
	 * @param array $rows      Index rows.
	 * @param array $summaries Summaries keyed by record ID.
	 * @return array
	 */
	private static function agreement_gap( array $rows, array $summaries ) {
		$gap = array();

		foreach ( $rows as $record_id => $row ) {
			if ( ! isset( $row['stage'] ) || 'Confirmed' !== trim( (string) $row['stage'] ) ) {
				continue;
			}

			$summary = isset( $summaries[ $record_id ] ) && is_array( $summaries[ $record_id ] ) ? $summaries[ $record_id ] : array();

			if ( ! self::is_settled_summary( $summary ) ) {
				$gap[ $record_id ] = $row;
			}
		}

		return $gap;
	}

	/**
	 * Whether an index row's agreement block describes an agreement at all.
	 *
	 * Any one of the eight columns being filled is the base saying there is one; all of them
	 * empty is a record nobody has asked for an agreement from yet.
	 *
	 * @param array $agreement An index row's `agreement` block.
	 * @return bool
	 */
	private static function has_agreement( array $agreement ) {
		foreach ( $agreement as $value ) {
			if ( is_string( $value ) ? '' !== trim( $value ) : ! empty( $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The two membership counts the manager backstop is measured by.
	 *
	 * One pass and one `members_of()` call per institution, because both counts need the
	 * same answer and that call is a query each. `members_of()` returns the accounts whose
	 * stamp names this institution and whose flag is live, so an institution missing from it
	 * has nobody at all: the count the design says pages a manager.
	 *
	 * A contact who is not a member is Airtable naming an address for the institution that
	 * belongs to no account acting for it. Both sides are lowercased and trimmed before they
	 * are compared: the index row's address already is, an account's is whatever the person
	 * typed when they registered, and a case difference is not a different person.
	 *
	 * @param array $rows Index rows.
	 * @return array{no_member: int, contact_not_member: int}
	 */
	private static function membership_gaps( array $rows ) {
		$gaps = array(
			'no_member'          => 0,
			'contact_not_member' => 0,
		);

		foreach ( $rows as $row ) {
			$members = WPCPM_Institution_Members::members_of( isset( $row['record_id'] ) ? (string) $row['record_id'] : '' );

			if ( empty( $members ) ) {
				++$gaps['no_member'];
			}

			$contact = isset( $row['contact_email'] ) ? strtolower( trim( (string) $row['contact_email'] ) ) : '';

			if ( '' === $contact ) {
				continue;
			}

			$is_member = false;

			foreach ( $members as $member ) {
				if ( $member instanceof WP_User && strtolower( trim( (string) $member->user_email ) ) === $contact ) {
					$is_member = true;
					break;
				}
			}

			if ( ! $is_member ) {
				++$gaps['contact_not_member'];
			}
		}

		return $gaps;
	}

	/**
	 * Where a count that joins the index to the live stamps came from.
	 *
	 * Two sources in one number: the institutions and the addresses Airtable names for them
	 * are as the last sync read them, the memberships are as they are right now. Saying only
	 * one of the two would let the stale half look as fresh as the live one.
	 *
	 * @param int $read Unix time the pipeline index was read, or 0 for never.
	 * @return string
	 */
	private static function membership_read_line( $read ) {
		if ( ! $read ) {
			return __( '(the pipeline index has not been read yet; memberships counted now)', 'wpcredits-program-manager' );
		}

		return sprintf(
			/* translators: %s: date and time the pipeline index was read. */
			__( '(from the pipeline index read %s; memberships counted now)', 'wpcredits-program-manager' ),
			wp_date( 'Y-m-d H:i', $read )
		);
	}

	/**
	 * The consent report's numbers, from the index.
	 *
	 * Dates compare as strings: both sides are `Y-m-d`, and a timestamp comparison would let
	 * the site's timezone move a record created near midnight across the boundary.
	 *
	 * @param array $rows Index rows.
	 * @return array{before: int, before_confirmed: int, since_without: int}
	 */
	private static function consent_counts( array $rows ) {
		$counts = array(
			'before'           => 0,
			'before_confirmed' => 0,
			'since_without'    => 0,
		);

		foreach ( $rows as $row ) {
			$created = isset( $row['created'] ) ? (string) $row['created'] : '';
			$consent = ! empty( $row['consent'] );

			if ( $consent || '' === $created ) {
				continue;
			}

			if ( strcmp( $created, self::CONSENT_QUESTION_ADDED ) < 0 ) {
				++$counts['before'];

				if ( isset( $row['stage'] ) && 'Confirmed' === trim( (string) $row['stage'] ) ) {
					++$counts['before_confirmed'];
				}
			} else {
				++$counts['since_without'];
			}
		}

		return $counts;
	}

	/**
	 * A per-status split as one line, e.g. "Not moving forward 15, (empty) 7, Graduate 6".
	 *
	 * @param array $by_status Status => count.
	 * @return string
	 */
	private static function breakdown( array $by_status ) {
		$parts = array();

		arsort( $by_status, SORT_NUMERIC );

		foreach ( $by_status as $status => $count ) {
			$status  = trim( (string) $status );
			$parts[] = sprintf(
				'%1$s %2$s',
				'' !== $status ? $status : __( '(empty)', 'wpcredits-program-manager' ),
				number_format_i18n( (int) $count )
			);
		}

		return implode( ', ', $parts );
	}

	/**
	 * How many accounts the program tracks carry no institution stamp.
	 *
	 * `NOT EXISTS` and not `= ''`: the stamp is deleted rather than written empty when a
	 * student has no institution, precisely so this query has one meaning.
	 *
	 * Tracked is the whole point of the number. Holding the Student role is not enough:
	 * `revoke_departed()` deletes the stamp of a student the last run did not see at all,
	 * sets the active flag to 0 and leaves the role alone whenever `student_on_inactive` is
	 * `keep`, so counting by role would report every departed student as a broken sync
	 * forever. The active flag is the sync's own word for "this account is in the synced set
	 * right now", so that is what is counted.
	 *
	 * **A missing stamp is not by itself a fault.** The sync deletes the stamp whenever it
	 * cannot name one institution: the Students row exists and names none, or the address is
	 * filed under two schools and nobody can say which. Both of those are already counted on
	 * this card, as rows with no institution and as duplicate emails, and reporting them a
	 * second time as a broken sync would send a manager looking for a fault in the code that
	 * is not there. What every one of those outcomes has in common is that the sync wrote
	 * `institution_source` on the program meta: that key is its word for "I looked at this
	 * account and this is what I found". So the number here counts the accounts where the key
	 * is absent altogether - tracked, live, and never described by any run - which is the only
	 * state that means the sync itself did not do its job.
	 *
	 * @return int
	 */
	private static function unstamped_students() {
		$query = new WP_User_Query(
			array(
				'role'        => WPCPM_Roles::ROLE_STUDENT,
				'number'      => -1,
				'count_total' => false,
				'fields'      => 'ID',
				'meta_query'  => array(
					'relation' => 'AND',
					array(
						'key'     => WPCPM_Students_Sync::META_INSTITUTION,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => WPCPM_Students_Sync::META_ACTIVE,
						'value' => '1',
					),
				),
			)
		);

		$unstamped = 0;

		foreach ( (array) $query->get_results() as $user_id ) {
			$program = get_user_meta( (int) $user_id, WPCPM_Students_Sync::META_PROGRAM, true );

			if ( is_array( $program ) && array_key_exists( 'institution_source', $program ) ) {
				continue;
			}

			++$unstamped;
		}

		return $unstamped;
	}

	/**
	 * An agreement summary in words: state, kind, acceptance date, and which route recorded it.
	 *
	 * @param array $summary From `WPCPM_Institution_Agreement::summary()`.
	 * @return string
	 */
	private static function describe_summary( array $summary ) {
		$state = isset( $summary['state'] ) ? (string) $summary['state'] : WPCPM_Institution_Agreement::SUMMARY_NONE;
		$kind  = isset( $summary['kind'] ) ? (string) $summary['kind'] : '';
		$route = isset( $summary['route'] ) ? (string) $summary['route'] : '';

		$states = array(
			WPCPM_Institution_Agreement::SUMMARY_NONE      => __( 'Not started', 'wpcredits-program-manager' ),
			WPCPM_Institution_Agreement::SUMMARY_GENERATED => __( 'Template generated', 'wpcredits-program-manager' ),
			WPCPM_Institution_Agreement::SUMMARY_SUBMITTED => __( 'Awaiting review', 'wpcredits-program-manager' ),
			WPCPM_Institution_Agreement::SUMMARY_RETURNED  => __( 'Returned', 'wpcredits-program-manager' ),
			WPCPM_Institution_Agreement::SUMMARY_REVOKED   => __( 'Revoked', 'wpcredits-program-manager' ),
			WPCPM_Institution_Agreement::SUMMARY_ACCEPTED  => __( 'Accepted', 'wpcredits-program-manager' ),
			WPCPM_Institution_Agreement::SUMMARY_ON_FILE   => __( 'On file', 'wpcredits-program-manager' ),
		);

		$kinds = array(
			WPCPM_Institution_Agreement::KIND_TEMPLATE => __( 'program template', 'wpcredits-program-manager' ),
			WPCPM_Institution_Agreement::KIND_OWN      => __( 'institution-specific', 'wpcredits-program-manager' ),
			WPCPM_Institution_Agreement::KIND_LEGACY   => __( 'legacy', 'wpcredits-program-manager' ),
		);

		$parts = array( isset( $states[ $state ] ) ? $states[ $state ] : $state );

		if ( isset( $kinds[ $kind ] ) ) {
			$parts[] = $kinds[ $kind ];
		}

		if ( ! empty( $summary['accepted_at'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: date. */
				__( 'accepted %s', 'wpcredits-program-manager' ),
				(string) $summary['accepted_at']
			);
		}

		if ( 'grid' === $route ) {
			$parts[] = __( 'recorded in the Airtable grid', 'wpcredits-program-manager' );
		} elseif ( 'site' === $route ) {
			$parts[] = __( 'recorded on the site', 'wpcredits-program-manager' );
		}

		return implode( ', ', $parts );
	}
}
