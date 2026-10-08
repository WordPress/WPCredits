<?php
/**
 * Admin screen: settings, status and manual controls.
 *
 * @package WordCamp_Airtable_Connector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the connector's admin page and handles its form posts.
 */
class WCAC_Admin {

	const SLUG = 'wcac';
	const CAP  = 'manage_options';

	/**
	 * Hook everything up.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_wcac_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_wcac_action', array( $this, 'handle_action' ) );
		add_action( 'admin_post_wcac_central', array( $this, 'handle_central' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WCAC_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Add the top-level menu entry.
	 *
	 * @return void
	 */
	public function menu() {
		add_menu_page(
			__( 'WordCamp Airtable Connector', 'wordcamp-airtable-connector' ),
			__( 'WordCamp Sync', 'wordcamp-airtable-connector' ),
			self::CAP,
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-update',
			81
		);
	}

	/**
	 * Add a Settings link on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( $this->url() ), esc_html__( 'Settings', 'wordcamp-airtable-connector' ) )
		);

		return $links;
	}

	/**
	 * Enqueue the page's stylesheet.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function assets( $hook ) {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wcac-admin', WCAC_URL . 'assets/admin.css', array(), WCAC_VERSION );
	}

	/**
	 * URL of the connector page.
	 *
	 * @param array $args Extra query arguments.
	 * @return string
	 */
	protected function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/* ---------------------------------------------------------------------
	 * Form handlers
	 * ------------------------------------------------------------------ */

	/**
	 * Save the settings form.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wordcamp-airtable-connector' ) );
		}

		check_admin_referer( 'wcac_save' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
		WCAC_Settings::save( wp_unslash( $_POST ) );

		// save() keeps the previous value for anything it refuses, so a redirect
		// to "saved" would report success for an edit that did not happen, while
		// the box re-renders the old value with nothing on screen to explain it.
		$rejected = WCAC_Settings::last_rejected();

		if ( $rejected ) {
			wp_safe_redirect(
				$this->url(
					array(
						'wcac_notice'   => 'save_rejected',
						'wcac_rejected' => implode( ',', array_map( 'sanitize_key', $rejected ) ),
					)
				)
			);
			exit;
		}

		wp_safe_redirect( $this->url( array( 'wcac_notice' => 'saved' ) ) );
		exit;
	}

	/**
	 * Run one of the manual actions.
	 *
	 * @return void
	 */
	public function handle_action() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wordcamp-airtable-connector' ) );
		}

		check_admin_referer( 'wcac_action' );

		$what = isset( $_POST['what'] ) ? sanitize_key( wp_unslash( $_POST['what'] ) ) : '';
		$sync = new WCAC_Sync();

		switch ( $what ) {
			case 'test':
				$airtable = new WCAC_Airtable();
				$result   = $airtable->ping( WCAC_Settings::table_for( 'wordcamps' ) );
				$notice   = is_wp_error( $result ) ? 'test_failed' : 'test_ok';

				if ( is_wp_error( $result ) ) {
					WCAC_Logger::log( 'error', 'Connection test failed: ' . $result->get_error_message() );
				} else {
					WCAC_Logger::log( 'info', 'Connection test succeeded.' );
				}
				break;

			case 'full':
				$sync->enqueue_full();
				$notice = 'queued';
				break;

			case 'incremental':
				$sync->enqueue_incremental();
				$notice = 'queued';
				break;

			case 'drain':
				$sync->run_slice( 20 );
				$notice = 'drained';
				break;

			case 'clear':
				$sync->clear_queue();
				WCAC_Logger::log( 'warn', 'Queue cleared by hand.' );
				$notice = 'cleared';
				break;

			case 'clear_log':
				WCAC_Logger::clear();
				$notice = 'log_cleared';
				break;

			case 'central_test':
				$notice = $this->test_central( $sync );
				break;

			case 'cc_now':
				// Enqueue only, never run_slice(): the report can take 45s and
				// this runs inside an admin-post.php request, which PHP-FPM may
				// terminate at 30s -- and run_slice() persists the shortened
				// queue before running the job, so the kill would lose it.
				if ( ! WCAC_Settings::campus_connect_ready() ) {
					WCAC_Logger::log( 'warn', 'Campus Connect was not queued: the sync is switched off, or no Central credential is stored.' );
					$notice = '';
					break;
				}

				// The standing block is checked here rather than left to
				// queue_campus_now(). This button asserts nothing about the
				// reason -- an operator who has not scrolled to the red notice
				// may not have read it -- so it must not discard a latch. And
				// queue_campus_now() answers a blocked, unacknowledged call with
				// the same false it uses for "a job was already pending", so the
				// two are told apart here, where they can still be reported apart.
				if ( WCAC_Sync::cc_block_active( $sync->state() ) ) {
					$notice = 'cc_blocked';
					break;
				}

				// false means a Campus Connect job was already pending and has
				// been moved to the head of the queue rather than added. Still
				// worth doing, but it is not the "queued" this used to report.
				$notice = $sync->queue_campus_now() ? 'queued' : 'cc_moved';
				break;

			case 'cc_clear':
				// The acknowledged override, and the only one on this screen.
				// It is reachable from exactly one place: the button inside the
				// red block notice, which prints the reason immediately above
				// it. Pressing it is therefore a person discarding a reason they
				// have just been shown, which is the distinction the latch turns
				// on -- a crontab line cannot reach this case at all. The
				// capability and nonce checks at the top of this method cover it
				// like every other action.
				if ( ! WCAC_Settings::campus_connect_ready() ) {
					WCAC_Logger::log( 'warn', 'The Campus Connect block was not cleared: the sync is switched off, or no Central credential is stored.' );
					$notice = '';
					break;
				}

				$notice = $sync->queue_campus_now( true ) ? 'queued' : 'cc_moved';
				break;

			default:
				$notice = '';
		}

		wp_safe_redirect( $this->url( $notice ? array( 'wcac_notice' => $notice ) : array() ) );
		exit;
	}

	/**
	 * Ask Central for the Campus Connect report and work out why it said no.
	 *
	 * Writes nothing to Airtable -- it is a read of the report route only. A 401
	 * has three quite different causes: the username or application password is
	 * wrong, the password was revoked, or this host strips the Authorization
	 * header before PHP sees it. The two anonymous probes therefore run in that
	 * case alone, and only their verdicts are logged.
	 *
	 * The username is never logged. Log lines get pasted into public tickets and
	 * it is a third party's WordPress.org login; the status code and the route
	 * are enough to act on.
	 *
	 * @param WCAC_Sync $sync Sync instance, for clearing the failure latch.
	 * @return string Notice key.
	 */
	protected function test_central( WCAC_Sync $sync ) {
		if ( ! WCAC_Settings::central_ready() ) {
			// Short-circuit: campus_connect() would answer this with a 401, and
			// reporting "the password may be wrong" for a credential that was
			// never entered sends the operator looking in the wrong place.
			WCAC_Logger::log( 'warn', 'Central access test skipped: no Central credential is stored.' );

			return 'central_failed';
		}

		$source = new WCAC_Source();
		$rows   = $source->campus_connect();

		if ( ! is_wp_error( $rows ) ) {
			WCAC_Logger::log(
				'info',
				sprintf( 'Central access test succeeded: %d Campus Connect events.', count( $rows ) )
			);

			$sync->clear_campus_block();

			return 'central_ok';
		}

		$data   = $rows->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;

		if ( 401 === $status ) {
			$advertised = $source->central_auth_advertised();
			$probe      = $source->central_identity_probe();

			WCAC_Logger::log(
				'error',
				sprintf(
					'Central access test: HTTP 401 from the Campus Connect report. The stored credential %s against wp/v2/users/me (HTTP %d), and the source site advertises %s. Three things cause this: the username or application password is wrong, the password was revoked, or this host strips the Authorization header before PHP sees it.',
					$probe['authenticated'] ? 'DID authenticate' : 'did not authenticate',
					$probe['status'],
					$advertised['ok'] && $advertised['schemes'] ? implode( ', ', $advertised['schemes'] ) : 'no non-cookie authentication'
				)
			);

			return 'central_401';
		}

		if ( 403 === $status ) {
			WCAC_Logger::log( 'error', 'Central access test: HTTP 403. The credential authenticated, but that account on Central holds neither the campus_connect_viewer subrole nor view_wordcamp_reports.' );

			return 'central_403';
		}

		WCAC_Logger::log( 'error', 'Central access test failed: ' . $rows->get_error_message() );

		return 'central_failed';
	}

	/**
	 * Save or remove the Central credential.
	 *
	 * Deliberately its own form and its own handler. Browsers ignore
	 * autocomplete="off" on password fields, so a credential box sitting inside
	 * the settings form invites a password manager to fill this site's own
	 * wp-admin login into a value that gets base64'd into an Authorization
	 * header aimed at central.wordcamp.org -- an off-site leak of the local
	 * admin password, logged against that username on a third party's server.
	 *
	 * Nothing here goes through WCAC_Settings::save(): its checkbox loop is
	 * unconditional, so posting a two-key array to it would silently switch
	 * every sync off.
	 *
	 * @return void
	 */
	public function handle_central() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wordcamp-airtable-connector' ) );
		}

		check_admin_referer( 'wcac_central' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
		$post = wp_unslash( $_POST );
		$what = isset( $post['what'] ) && is_scalar( $post['what'] ) ? sanitize_key( $post['what'] ) : '';

		if ( 'clear_central' === $what ) {
			WCAC_Settings::forget_central();
			WCAC_Logger::log( 'warn', 'Central credential removed.' );

			wp_safe_redirect( $this->url( array( 'wcac_notice' => 'central_cleared' ) ) );
			exit;
		}

		// Only the source is read here; the credential itself is never kept,
		// rendered, logged or compared.
		if ( 'constant' === WCAC_Settings::central_credential()['source'] ) {
			WCAC_Logger::log( 'warn', 'Central credential form ignored: the credential is defined in wp-config.php on this server.' );

			wp_safe_redirect( $this->url( array( 'wcac_notice' => 'central_locked' ) ) );
			exit;
		}

		if ( isset( $post['central_user'] ) && is_scalar( $post['central_user'] ) ) {
			$user = sanitize_user( trim( (string) $post['central_user'] ), false );

			if ( false !== strpos( $user, ':' ) ) {
				WCAC_Logger::log( 'warn', 'Central username rejected: a colon cannot be used in HTTP Basic auth.' );
			} else {
				WCAC_Settings::set_secret( 'central_user', $user );
			}
		}

		// Preserve-on-empty, exactly as the settings form treats the Airtable
		// token: a blank box means "keep what is stored", never "delete it".
		// set_secret() stores the value verbatim, so it is normalised here.
		if ( isset( $post['central_app_password'] ) && is_scalar( $post['central_app_password'] ) ) {
			$password = WCAC_Settings::normalise_secret( $post['central_app_password'] );

			if ( '' !== $password ) {
				WCAC_Settings::set_secret( 'central_app_password', $password );
			}
		}

		wp_safe_redirect( $this->url( array( 'wcac_notice' => 'central_saved' ) ) );
		exit;
	}

	/**
	 * Print the notice for the current request, if any.
	 *
	 * @return void
	 */
	protected function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$key = isset( $_GET['wcac_notice'] ) ? sanitize_key( wp_unslash( $_GET['wcac_notice'] ) ) : '';

		if ( 'save_rejected' === $key ) {
			$this->rejected_notice();

			return;
		}

		$messages = array(
			'saved'           => array( 'success', __( 'Settings saved.', 'wordcamp-airtable-connector' ) ),
			'queued'          => array( 'success', __( 'Sync queued. It will run in the background.', 'wordcamp-airtable-connector' ) ),
			'cc_moved'        => array( 'info', __( 'A Campus Connect job was already pending. It has been moved to the head of the queue, so it runs next.', 'wordcamp-airtable-connector' ) ),
			'cc_blocked'      => array( 'error', __( 'Campus Connect is blocked, so nothing was queued. The reason is in the red notice at the top of this screen; clear it from there once you have put the cause right.', 'wordcamp-airtable-connector' ) ),
			'drained'         => array( 'success', __( 'Processed a slice of the queue.', 'wordcamp-airtable-connector' ) ),
			'cleared'         => array( 'warning', __( 'Pending queue discarded.', 'wordcamp-airtable-connector' ) ),
			'log_cleared'     => array( 'success', __( 'Log cleared.', 'wordcamp-airtable-connector' ) ),
			'test_ok'         => array( 'success', __( 'Connected to Airtable successfully.', 'wordcamp-airtable-connector' ) ),
			'test_failed'     => array( 'error', __( 'Could not reach Airtable. See the log below for the exact error.', 'wordcamp-airtable-connector' ) ),
			'central_saved'   => array( 'success', __( 'Central credential saved.', 'wordcamp-airtable-connector' ) ),
			'central_cleared' => array( 'warning', __( 'Central credential removed. The Campus Connect sync cannot run until a new one is stored.', 'wordcamp-airtable-connector' ) ),
			'central_locked'  => array( 'warning', __( 'The Central credential is defined in wp-config.php, so the form was ignored. Remove those constants to manage it here.', 'wordcamp-airtable-connector' ) ),
			'central_ok'      => array( 'success', __( 'Central accepted the credential and returned the Campus Connect report. Nothing was written to Airtable.', 'wordcamp-airtable-connector' ) ),
			'central_401'     => array( 'error', __( 'Central rejected the credential (HTTP 401). The username or application password may be wrong or revoked, or this host may strip the Authorization header. The log below says which.', 'wordcamp-airtable-connector' ) ),
			'central_403'     => array( 'error', __( 'Central authenticated that account, but it holds neither the campus_connect_viewer subrole nor view_wordcamp_reports (HTTP 403). Ask for campus_connect_viewer, which opens this one report and nothing else.', 'wordcamp-airtable-connector' ) ),
			'central_failed'  => array( 'error', __( 'Could not read the Campus Connect report from Central. See the log below for the exact error.', 'wordcamp-airtable-connector' ) ),
		);

		if ( ! isset( $messages[ $key ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $messages[ $key ][0] ),
			esc_html( $messages[ $key ][1] )
		);
	}

	/**
	 * The notice for a save that refused one of the values it was given.
	 *
	 * Names the setting rather than saying "something was rejected": after the
	 * redirect the box holds the OLD value, so without the name an operator
	 * sees a deliberate edit vanish with no explanation and no remedy -- and
	 * the five other syncs quietly keep crawling the previous host.
	 *
	 * @return void
	 */
	protected function rejected_notice() {
		$labels = array(
			'source_root'  => __( 'Source site', 'wordcamp-airtable-connector' ),
			'central_user' => __( 'Central username', 'wordcamp-airtable-connector' ),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$raw   = isset( $_GET['wcac_rejected'] ) ? sanitize_text_field( wp_unslash( $_GET['wcac_rejected'] ) ) : '';
		$named = array();

		foreach ( explode( ',', $raw ) as $field ) {
			$field = sanitize_key( $field );

			if ( isset( $labels[ $field ] ) ) {
				$named[ $field ] = $labels[ $field ];
			}
		}

		if ( empty( $named ) ) {
			// An unrecognised key still means something was refused; say so
			// rather than falling through to silence.
			$named['source_root'] = $labels['source_root'];
		}

		echo '<div class="notice notice-error"><p>';
		printf(
			/* translators: %s: comma-separated list of setting labels. */
			esc_html__( 'Saved, except this, which was refused and left at its previous value: %s.', 'wordcamp-airtable-connector' ),
			esc_html( implode( ', ', $named ) )
		);
		echo '</p>';

		if ( isset( $named['source_root'] ) ) {
			echo '<p>';
			esc_html_e( 'The source site must be an https URL, typed with its scheme. The Campus Connect sync sends an application password to it, and http would put that credential on the wire in the clear.', 'wordcamp-airtable-connector' );
			echo ' ';
			printf(
				/* translators: %s: name of a PHP constant. */
				esc_html__( 'To point at an http staging copy of Central anyway, define %s as true in wp-config.php on this server first.', 'wordcamp-airtable-connector' ),
				'<code>WCAC_ALLOW_INSECURE_CENTRAL</code>'
			);
			echo '</p>';

			// save() refuses the field for a second reason, under the same key: a
			// blank box while a value is stored. Both land here, so both are named,
			// or an operator who never touched the box reads the https rule and
			// concludes the empty box itself is the fault.
			echo '<p>';
			esc_html_e( 'A blank box is refused as well. It is not a request to clear the field, so the stored site was kept. If the box was already blank when this screen loaded, the stored value cannot be displayed here - the note beside it says what to do.', 'wordcamp-airtable-connector' );
			echo '</p>';
		}

		if ( isset( $named['central_user'] ) ) {
			echo '<p>' . esc_html__( 'A Central username cannot contain a colon: HTTP Basic auth splits on it.', 'wordcamp-airtable-connector' ) . '</p>';
		}

		echo '</div>';
	}

	/* ---------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------ */

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render() {
		$settings = WCAC_Settings::all();
		$sync     = new WCAC_Sync();
		$state    = $sync->state();
		$pending  = $sync->pending();

		// all() carries the Central application password. Two booleans are the
		// whole of what the credential form below needs from it, so the value is
		// dropped here rather than staying alive in a local for the rest of the
		// render.
		$has_db_user = '' !== (string) $settings['central_user'];
		$has_db_pass = '' !== (string) $settings['central_app_password'];

		$settings['central_app_password'] = '';

		// Same treatment for the source site. all() carries the raw stored root,
		// and a legacy row can still hold a user:pass@ authority, so it is reduced
		// here to the two things this screen needs -- what belongs in the box, and
		// whether anything is stored at all -- and then dropped. The box renders
		// source_root(), which is '' for a root that cannot be parsed; without the
		// second flag that empty box is indistinguishable from a site that was
		// never configured, and the operator saves an unrelated setting over it.
		$root_shown      = WCAC_Settings::source_root();
		$root_unreadable = '' === $root_shown && '' !== trim( (string) $settings['source_root'] );

		$settings['source_root'] = '';

		// Readiness has two halves. campus_connect_ready() is the configuration
		// half -- credential, toggle, destination table. The preflight stamp is
		// the half WCAC_Sync actually gates the job on, and leaving it out is why
		// this screen said "ready" and offered a button for a job that could only
		// ever skip. WCAC_CLI::status() branches on the same three inputs, in the
		// same words: its separate "campus preflight" row is not a substitute for
		// the verdict, and the two surfaces must never disagree about it.
		$cc_configured = WCAC_Settings::campus_connect_ready();
		$cc_table      = WCAC_Settings::table_for( 'campus_connect' );
		$cc_stamped    = (int) $this->state_value( $state, 'cc_preflight_ok' );
		$cc_stale      = $cc_stamped > 0 && ( (string) $this->state_value( $state, 'cc_preflight_table' ) !== (string) $cc_table );
		$cc_ready      = $cc_configured && $cc_stamped > 0 && ! $cc_stale;

		// One rule, asked of WCAC_Sync rather than copied here. It is a latch,
		// not a timeout: nothing on a clock lowers it, because this connector
		// has no delete verb and a block released by a wall clock is released
		// onto an unattended run. It comes down when a person takes it down.
		//
		// The kind decides which surfaces may do that. An access-shaped block is
		// evidence about the credential, so credential-shaped evidence -- a
		// successful "Test Central access" -- clears it. A data-shaped block is
		// about volume, creates, partial writes or the report contract, and a
		// working credential is no evidence at all about those, so only the
		// acknowledged button in the red notice below clears it. A block written
		// before this key existed carries no kind and reads as data, which is
		// the safe reading.
		$cc_since  = (int) $this->state_value( $state, 'cc_block' );
		$cc_block  = WCAC_Sync::cc_block_active( $state );
		$cc_access = 'access' === (string) $this->state_value( $state, 'cc_block_kind' );

		// The two counters the block is built from. cc_fails counts runs that
		// failed outright, cc_partial counts runs where the report arrived but
		// rows in it would not write; either reaching five latches the job off.
		// Neither was rendered anywhere, so an operator could only ever see the
		// fifth failure, never the four that led to it.
		$cc_fails   = (int) $this->state_value( $state, 'cc_fails' );
		$cc_partial = (int) $this->state_value( $state, 'cc_partial' );
		$cc_why     = (string) $this->state_value( $state, 'cc_block_why' );

		// The global "Rows created / updated" row below is cumulative and shared
		// with the five other syncs, so a Campus Connect create is invisible in
		// it. Creation is the irreversible direction on that table: its rows are
		// hand-curated, a merge-key miss creates instead of updating, and this
		// connector has no delete verb. It is reported here on its own.
		//
		// Only the created half is read from the state. cc_last_written is
		// created plus updated, so the updated half is derived from it rather
		// than read, and the two halves cannot contradict the total beside them.
		// A state row from a run that predates the split carries no
		// cc_last_created at all; -1 keeps that case honest, because rendering
		// it as "0 created" would be a reassurance about the curated table that
		// this screen cannot support.
		$cc_written = (int) $this->state_value( $state, 'cc_last_written' );
		$cc_created = (int) $this->state_value( $state, 'cc_last_created', -1 );
		$cc_updated = $cc_created >= 0 ? max( 0, $cc_written - $cc_created ) : -1;
		?>
		<div class="wrap wcac">
			<h1><?php esc_html_e( 'WordCamp Airtable Connector', 'wordcamp-airtable-connector' ); ?></h1>
			<?php $this->notice(); ?>

			<?php if ( ! WCAC_Settings::is_configured() ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'Add an Airtable personal access token below to start syncing. The token needs the data.records:read and data.records:write scopes on this base - schema scopes are not required.', 'wordcamp-airtable-connector' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $cc_block ) : ?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e( 'The Campus Connect sync is blocked.', 'wordcamp-airtable-connector' ); ?></strong>
						<?php echo esc_html( $this->state_value( $state, 'cc_block_why' ) ); ?>
					</p>
					<p><?php esc_html_e( 'That reason stands until someone clears it. Nothing on a clock clears it: this connector cannot delete a row it wrongly creates, so the block is never released onto an unattended run. Put the cause right, then use the button below, or run "wp wcac campus-connect --run --clear-block" at a terminal. The counters that led to it are in the status table, under "Campus Connect failure latch". The other five syncs are unaffected.', 'wordcamp-airtable-connector' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'wcac_action' ); ?>
						<input type="hidden" name="action" value="wcac_action" />
						<p><button class="button" name="what" value="cc_clear"><?php esc_html_e( 'Clear the block and sync Campus Connect now', 'wordcamp-airtable-connector' ); ?></button></p>
					</form>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Status', 'wordcamp-airtable-connector' ); ?></h2>
			<table class="widefat wcac-status">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'Jobs pending', 'wordcamp-airtable-connector' ); ?></th>
						<td><strong><?php echo esc_html( number_format_i18n( $pending ) ); ?></strong>
							<?php if ( $pending ) : ?>
								<span class="description"><?php esc_html_e( 'draining in the background', 'wordcamp-airtable-connector' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Current run', 'wordcamp-airtable-connector' ); ?></th>
						<td><?php echo $state['mode'] ? esc_html( $state['mode'] ) : '&mdash;'; ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Rows created / updated', 'wordcamp-airtable-connector' ); ?></th>
						<td><?php echo esc_html( number_format_i18n( $state['created'] ) . ' / ' . number_format_i18n( $state['updated'] ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Errors', 'wordcamp-airtable-connector' ); ?></th>
						<td><?php echo esc_html( number_format_i18n( $state['errors'] ) ); ?>
							<span class="description"><?php esc_html_e( 'cumulative since the last full backfill', 'wordcamp-airtable-connector' ); ?></span>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Last slice', 'wordcamp-airtable-connector' ); ?></th>
						<td><?php echo $state['last_run'] ? esc_html( $this->ago( $state['last_run'] ) ) : '&mdash;'; ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Camps cached locally', 'wordcamp-airtable-connector' ); ?></th>
						<td><?php echo esc_html( number_format_i18n( count( $sync->camps() ) ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Campus Connect', 'wordcamp-airtable-connector' ); ?></th>
						<td>
							<?php
							if ( $cc_block ) {
								esc_html_e( 'blocked', 'wordcamp-airtable-connector' );
							} elseif ( ! $cc_configured ) {
								esc_html_e( 'off', 'wordcamp-airtable-connector' );
							} elseif ( $cc_ready ) {
								esc_html_e( 'ready', 'wordcamp-airtable-connector' );
							} else {
								esc_html_e( 'configured, but the preflight below has not passed', 'wordcamp-airtable-connector' );
							}
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Campus Connect preflight', 'wordcamp-airtable-connector' ); ?></th>
						<td>
							<?php
							if ( ! $cc_stamped ) {
								esc_html_e( 'not run', 'wordcamp-airtable-connector' );
							} elseif ( $cc_stale ) {
								esc_html_e( 'stale: the table ID changed since it was run', 'wordcamp-airtable-connector' );
							} else {
								printf(
									/* translators: %s: human-readable time difference. */
									esc_html__( 'passed %s', 'wordcamp-airtable-connector' ),
									esc_html( $this->ago( $cc_stamped ) )
								);
							}

							if ( ! $cc_stamped || $cc_stale ) {
								?>
								<br />
								<span class="description">
									<?php esc_html_e( 'Until this passes, every Campus Connect job skips without writing anything. It reads the whole report and the destination table in one request, so it is a WP-CLI command rather than a button here:', 'wordcamp-airtable-connector' ); ?>
									<code>wp wcac campus-connect --preflight</code>
								</span>
								<?php
							}
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Campus Connect last run', 'wordcamp-airtable-connector' ); ?></th>
						<td>
							<?php
							// Its own row, because the global "Last slice" above is
							// shared with the five other syncs: a green timestamp
							// there says nothing about this one.
							$cc_ok = (int) $this->state_value( $state, 'cc_last_ok' );

							if ( $cc_ok && $cc_created >= 0 ) {
								printf(
									/* translators: 1: human-readable time difference, 2: rows created, 3: rows updated, 4: rows read. */
									esc_html__( '%1$s, %2$s created / %3$s updated, of %4$s rows read', 'wordcamp-airtable-connector' ),
									esc_html( $this->ago( $cc_ok ) ),
									esc_html( number_format_i18n( $cc_created ) ),
									esc_html( number_format_i18n( $cc_updated ) ),
									esc_html( number_format_i18n( (int) $this->state_value( $state, 'cc_last_rows' ) ) )
								);
							} elseif ( $cc_ok ) {
								printf(
									/* translators: 1: human-readable time difference, 2: rows written, 3: rows read. */
									esc_html__( '%1$s, %2$s of %3$s rows written', 'wordcamp-airtable-connector' ),
									esc_html( $this->ago( $cc_ok ) ),
									esc_html( number_format_i18n( $cc_written ) ),
									esc_html( number_format_i18n( (int) $this->state_value( $state, 'cc_last_rows' ) ) )
								);

								// Deliberately not "0 created": that run is from a build
								// that did not record the two halves apart, and a zero
								// here would assert something about the curated table
								// that this screen has no evidence for.
								printf(
									'<br /><span class="description">%s</span>',
									esc_html__( 'That run did not record how many of those rows it created rather than updated. The next one will.', 'wordcamp-airtable-connector' )
								);
							} else {
								esc_html_e( 'never', 'wordcamp-airtable-connector' );
							}

							if ( $cc_created > 0 ) {
								// A run over rows that already exist creates none: every
								// WordCamp ID in the report matches a row. A create means
								// a merge key missed, and there is no delete verb to undo
								// it, so this is stated in the open rather than folded
								// into a total.
								echo '<br /><strong>';
								printf(
									/* translators: %s: number of rows. */
									esc_html( _n( '%s row was created rather than updated.', '%s rows were created rather than updated.', $cc_created, 'wordcamp-airtable-connector' ) ),
									esc_html( number_format_i18n( $cc_created ) )
								);
								echo '</strong> ';
								esc_html_e( 'Check the destination table before the next run. New rows sit beside the hand-curated ones and this connector cannot delete them.', 'wordcamp-airtable-connector' );
							}

							$cc_error = (string) $this->state_value( $state, 'cc_last_error' );

							if ( '' !== $cc_error ) {
								printf( '<br /><span class="description">%s</span>', esc_html( $cc_error ) );
							}
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Campus Connect failure latch', 'wordcamp-airtable-connector' ); ?></th>
						<td>
							<?php
							// The block banner at the top of the page is the only other
							// place the reason has ever been shown. This row keeps both
							// the state and the reason on screen, and shows the counts
							// while they are still climbing towards the block.
							//
							// There is no "expired" case left to report. A stamp with
							// the latch down cannot occur any more, because every route
							// that lowers the latch zeroes the stamp with it.
							if ( $cc_block ) {
								printf(
									/* translators: %s: human-readable time difference. */
									esc_html__( 'blocked %s', 'wordcamp-airtable-connector' ),
									esc_html( $this->ago( $cc_since ) )
								);
							} else {
								esc_html_e( 'not blocked', 'wordcamp-airtable-connector' );
							}

							echo '<br />';
							printf(
								/* translators: 1: number of consecutive failed runs, 2: number of consecutive runs with rows that would not write. */
								esc_html__( 'Consecutive failed runs: %1$s. Consecutive runs leaving rows unwritten: %2$s. Either reaching five blocks the job.', 'wordcamp-airtable-connector' ),
								esc_html( number_format_i18n( $cc_fails ) ),
								esc_html( number_format_i18n( $cc_partial ) )
							);

							if ( '' !== $cc_why ) {
								printf(
									'<br /><span class="description">%1$s %2$s</span>',
									esc_html__( 'Reason:', 'wordcamp-airtable-connector' ),
									esc_html( $cc_why )
								);
							}

							if ( $cc_block || $cc_fails > 0 || $cc_partial > 0 ) {
								if ( $cc_block && ! $cc_access ) {
									// Volume, creates, partial writes or the report
									// contract. A credential that works is no evidence
									// about any of those, so the credential routes
									// deliberately leave this block standing.
									$cc_clears = __( 'A run that writes every row clears both counters. This block is about the rows rather than the credential, so "Test Central access" will not clear it: use the button in the red notice at the top of this screen, or run "wp wcac campus-connect --run --clear-block" at a terminal.', 'wordcamp-airtable-connector' );
								} elseif ( $cc_block ) {
									$cc_clears = __( 'A run that writes every row clears both counters. This block is about the credential, so a successful "Test Central access" clears it and its reason, without writing anything to Airtable.', 'wordcamp-airtable-connector' );
								} else {
									$cc_clears = __( 'A run that writes every row clears both counters.', 'wordcamp-airtable-connector' );
								}

								printf( '<br /><span class="description">%s</span>', esc_html( $cc_clears ) );
							}
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Campus Connect Status gaps', 'wordcamp-airtable-connector' ); ?></th>
						<td>
							<?php
							$cc_gaps = array_keys(
								array_merge(
									(array) $this->state_value( $state, 'cc_unmapped', array() ),
									(array) $this->state_value( $state, 'cc_absent', array() )
								)
							);

							if ( $cc_gaps ) {
								echo esc_html( implode( ', ', array_map( 'strval', $cc_gaps ) ) );
								?>
								<span class="description"><?php esc_html_e( 'Status was left untouched on those rows', 'wordcamp-airtable-connector' ); ?></span>
								<?php
							} else {
								esc_html_e( 'none', 'wordcamp-airtable-connector' );
							}
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Series Event', 'wordcamp-airtable-connector' ); ?></th>
						<td>
							<?php
							echo $this->state_value( $state, 'cc_series' )
								? esc_html__( 'syncing', 'wordcamp-airtable-connector' )
								: esc_html__( 'not returned by this credential', 'wordcamp-airtable-connector' );
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Next scheduled tick', 'wordcamp-airtable-connector' ); ?></th>
						<td>
							<?php
							$next = wp_next_scheduled( WCAC_CRON_HOOK );
							echo $next ? esc_html( $this->ago( $next ) ) : esc_html__( 'not scheduled', 'wordcamp-airtable-connector' );
							?>
						</td>
					</tr>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wcac-actions">
				<?php wp_nonce_field( 'wcac_action' ); ?>
				<input type="hidden" name="action" value="wcac_action" />
				<button class="button" name="what" value="test"><?php esc_html_e( 'Test connection', 'wordcamp-airtable-connector' ); ?></button>
				<button class="button" name="what" value="central_test"><?php esc_html_e( 'Test Central access', 'wordcamp-airtable-connector' ); ?></button>
				<button class="button button-primary" name="what" value="incremental"><?php esc_html_e( 'Sync changes now', 'wordcamp-airtable-connector' ); ?></button>
				<?php if ( $cc_ready ) : ?>
					<button class="button" name="what" value="cc_now"><?php esc_html_e( 'Sync Campus Connect now', 'wordcamp-airtable-connector' ); ?></button>
				<?php endif; ?>
				<button class="button" name="what" value="full"><?php esc_html_e( 'Queue full backfill', 'wordcamp-airtable-connector' ); ?></button>
				<button class="button" name="what" value="drain"><?php esc_html_e( 'Process queue now', 'wordcamp-airtable-connector' ); ?></button>
				<button class="button button-link-delete" name="what" value="clear"><?php esc_html_e( 'Discard queue', 'wordcamp-airtable-connector' ); ?></button>
			</form>

			<p class="description">
				<?php esc_html_e( 'A full backfill crawls roughly 1,500 camp sites and takes many hours through WP-Cron. On a server with WP-CLI, run it in one go instead:', 'wordcamp-airtable-connector' ); ?>
				<code>wp wcac sync --full</code>
			</p>

			<p class="description">
				<?php esc_html_e( 'Test Central access reads the Campus Connect report and writes nothing. Sync Campus Connect now only queues the job - it is the one job that cannot be split across ticks, so it is left to cron or to WP-CLI rather than run inside this page request:', 'wordcamp-airtable-connector' ); ?>
				<code>wp wcac campus-connect --dry-run</code>
			</p>

			<h2><?php esc_html_e( 'Settings', 'wordcamp-airtable-connector' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wcac_save' ); ?>
				<input type="hidden" name="action" value="wcac_save" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wcac-key"><?php esc_html_e( 'Airtable token', 'wordcamp-airtable-connector' ); ?></label></th>
						<td>
							<input type="password" id="wcac-key" name="api_key" class="regular-text" autocomplete="new-password" spellcheck="false" autocapitalize="off"
								placeholder="<?php echo $settings['api_key'] ? esc_attr__( 'Saved - leave blank to keep', 'wordcamp-airtable-connector' ) : 'pat...'; ?>" />
							<p class="description"><?php esc_html_e( 'Personal access token with data.records:read and data.records:write on this base. Stored in wp_options; never displayed again after saving.', 'wordcamp-airtable-connector' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wcac-base"><?php esc_html_e( 'Base ID', 'wordcamp-airtable-connector' ); ?></label></th>
						<td><input type="text" id="wcac-base" name="base_id" class="regular-text code" value="<?php echo esc_attr( $settings['base_id'] ); ?>" /></td>
					</tr>
					<?php
					$tables = array(
						'tbl_wordcamps'      => __( 'WordCamps table', 'wordcamp-airtable-connector' ),
						'tbl_meetups'        => __( 'Meetups table', 'wordcamp-airtable-connector' ),
						'tbl_sessions'       => __( 'Sessions table', 'wordcamp-airtable-connector' ),
						'tbl_speakers'       => __( 'Speakers table', 'wordcamp-airtable-connector' ),
						'tbl_sponsors'       => __( 'Sponsors table', 'wordcamp-airtable-connector' ),
						'tbl_campus_connect' => __( 'Campus Connect Events table', 'wordcamp-airtable-connector' ),
					);

					foreach ( $tables as $key => $label ) :
						?>
						<tr>
							<th scope="row"><label for="wcac-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><input type="text" id="wcac-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" class="regular-text code" value="<?php echo esc_attr( $settings[ $key ] ); ?>" /></td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'What to sync', 'wordcamp-airtable-connector' ); ?></th>
						<td>
							<fieldset>
								<label><input type="checkbox" name="sync_wordcamps" value="1" <?php checked( $settings['sync_wordcamps'] ); ?> /> <?php esc_html_e( 'WordCamps', 'wordcamp-airtable-connector' ); ?></label><br />
								<label><input type="checkbox" name="sync_meetups" value="1" <?php checked( $settings['sync_meetups'] ); ?> /> <?php esc_html_e( 'Meetups', 'wordcamp-airtable-connector' ); ?></label><br />
								<label><input type="checkbox" name="sync_children" value="1" <?php checked( $settings['sync_children'] ); ?> /> <?php esc_html_e( 'Sessions, Speakers and Sponsors from each camp site', 'wordcamp-airtable-connector' ); ?></label><br />
								<label><input type="checkbox" name="sync_campus_connect" value="1" <?php checked( $settings['sync_campus_connect'] ); ?> /> <?php esc_html_e( 'Campus Connect events (needs the Central credential below)', 'wordcamp-airtable-connector' ); ?></label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wcac-campus-status"><?php esc_html_e( 'Approved Status options', 'wordcamp-airtable-connector' ); ?></label></th>
						<td>
							<textarea id="wcac-campus-status" name="campus_extra_status" class="large-text code" rows="4"><?php echo esc_textarea( $settings['campus_extra_status'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Status options you have added to the Airtable field by hand, one per line. The sync will not write a Status option it has not been told exists, so a label the connector knows but Airtable does not is left untouched rather than created.', 'wordcamp-airtable-connector' ); ?></p>
							<p class="description"><?php esc_html_e( 'One label does not need listing here: "Needs Action" is approved in the code, because it is the only status exclusive to Campus Connect and the Airtable field was built from the WordCamp pipeline without it. Expect that option to appear on the field the first time an event reaches that status.', 'wordcamp-airtable-connector' ); ?></p>
							<p class="description">
								<?php
								$approved = array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', (string) $settings['campus_extra_status'] ) ) );

								printf(
									/* translators: %s: number of approved Status labels. */
									esc_html( _n( '%s approved label stored.', '%s approved labels stored.', count( $approved ), 'wordcamp-airtable-connector' ) ),
									esc_html( number_format_i18n( count( $approved ) ) )
								);
								?>
								<?php esc_html_e( 'A line that is not a label this connector can produce is dropped on save.', 'wordcamp-airtable-connector' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wcac-lookback"><?php esc_html_e( 'Lookback window', 'wordcamp-airtable-connector' ); ?></label></th>
						<td>
							<input type="number" id="wcac-lookback" name="lookback_hours" class="small-text" min="1" max="8760" value="<?php echo esc_attr( $settings['lookback_hours'] ); ?>" />
							<?php esc_html_e( 'hours', 'wordcamp-airtable-connector' ); ?>
							<p class="description"><?php esc_html_e( 'An incremental sync asks Central for records modified inside this window. Keep it comfortably longer than the gap between runs.', 'wordcamp-airtable-connector' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wcac-budget"><?php esc_html_e( 'Time budget per tick', 'wordcamp-airtable-connector' ); ?></label></th>
						<td>
							<input type="number" id="wcac-budget" name="time_budget" class="small-text" min="5" max="120" value="<?php echo esc_attr( $settings['time_budget'] ); ?>" />
							<?php esc_html_e( 'seconds', 'wordcamp-airtable-connector' ); ?>
							<p class="description"><?php esc_html_e( 'How long one cron run may spend draining the queue. Keep it well below the PHP max execution time.', 'wordcamp-airtable-connector' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wcac-root"><?php esc_html_e( 'Source site', 'wordcamp-airtable-connector' ); ?></label></th>
						<td>
							<input type="url" id="wcac-root" name="source_root" class="regular-text code" value="<?php echo esc_attr( $root_shown ); ?>" />
							<p class="description"><?php esc_html_e( 'Only change this to point at a staging copy of WordCamp Central.', 'wordcamp-airtable-connector' ); ?></p>
							<?php if ( $root_unreadable ) : ?>
								<div class="notice notice-warning inline">
									<p>
										<strong><?php esc_html_e( 'A source site is stored, but it cannot be shown here.', 'wordcamp-airtable-connector' ); ?></strong>
										<?php esc_html_e( 'The stored value is not a usable URL - it has no scheme, or no host - and it is not printed on this screen, because a value in that shape can also carry an embedded username and password. Every job that reads from the source site fails until it is replaced.', 'wordcamp-airtable-connector' ); ?>
									</p>
									<p><?php esc_html_e( 'Type the full https URL above to replace it. Saving this page with the box left empty will not erase it: a blank box is refused and the stored value is kept.', 'wordcamp-airtable-connector' ); ?></p>
								</div>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<?php
			// A form of its own, outside the settings form, on purpose. Chrome and
			// Safari fill password fields regardless of autocomplete="off", and a
			// credential box inside the settings form invites this site's own
			// wp-admin login to be filled in and then base64'd into an
			// Authorization header sent to central.wordcamp.org.
			//
			// Only 'source' is kept -- the credential array carries the password,
			// and there is no reason for it to stay alive for the rest of the
			// page. The value itself is never rendered, in any attribute or text
			// node, in any state.
			$locked      = 'constant' === WCAC_Settings::central_credential()['source'];
			$fingerprint = WCAC_Settings::credential_fingerprint();
			?>
			<h2><?php esc_html_e( 'Central credential', 'wordcamp-airtable-connector' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'The Campus Connect report is not public. Reading it needs an application password for an account on central.wordcamp.org that holds the campus_connect_viewer subrole, which opens this one report and nothing else, or view_wordcamp_reports. That is an account on Central - it has nothing to do with the WordPress user you are signed in as here.', 'wordcamp-airtable-connector' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
				<?php wp_nonce_field( 'wcac_central' ); ?>
				<input type="hidden" name="action" value="wcac_central" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wcac-central-user"><?php esc_html_e( 'Central username', 'wordcamp-airtable-connector' ); ?></label></th>
						<td>
							<input type="text" id="wcac-central-user" name="central_user" class="regular-text" autocomplete="off" spellcheck="false" autocapitalize="off"
								value="<?php echo esc_attr( $settings['central_user'] ); ?>" <?php disabled( $locked ); ?> />
							<p class="description"><?php esc_html_e( 'The login name on Central. A colon cannot be used: HTTP Basic auth splits on it.', 'wordcamp-airtable-connector' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wcac-central-pass"><?php esc_html_e( 'Application password', 'wordcamp-airtable-connector' ); ?></label></th>
						<td>
							<input type="password" id="wcac-central-pass" name="central_app_password" class="regular-text" autocomplete="new-password" spellcheck="false" autocapitalize="off"
								placeholder="<?php echo $has_db_pass ? esc_attr__( 'Saved - leave blank to keep', 'wordcamp-airtable-connector' ) : ''; ?>" <?php disabled( $locked ); ?> />
							<p class="description"><?php esc_html_e( 'Created on Central under Users, Profile, Application Passwords. Spaces are optional - they are removed before it is stored. It is never displayed again after saving, and it is never written to the log.', 'wordcamp-airtable-connector' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Stored credential', 'wordcamp-airtable-connector' ); ?></th>
						<td>
							<?php if ( '' !== $fingerprint ) : ?>
								<code><?php echo esc_html( $fingerprint ); ?></code>
								<span class="description">
									<?php
									echo $locked
										? esc_html__( 'from wp-config.php', 'wordcamp-airtable-connector' )
										: esc_html__( 'stored in the database', 'wordcamp-airtable-connector' );
									?>
								</span>
								<p class="description"><?php esc_html_e( 'Twelve characters of a salted hash, not the password, and not reversible. If it changes when you did not change the credential, a password manager has filled the box for you - retype the real one.', 'wordcamp-airtable-connector' ); ?></p>
							<?php else : ?>
								<?php esc_html_e( 'nothing stored', 'wordcamp-airtable-connector' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<?php
				// The Remove button renders whenever a database copy exists, the
				// locked case included. handle_central() answers clear_central
				// BEFORE its constant check for exactly this reason: adding the
				// constants used to hide the only control that can clear the row
				// they override, stranding a live application password in
				// wp_options on a screen that asserts there is not one.
				$has_db_copy = $has_db_user || $has_db_pass;
				?>
				<?php if ( $locked ) : ?>
					<p class="description"><?php esc_html_e( 'Set in wp-config.php on this server. Remove the constants to manage it here.', 'wordcamp-airtable-connector' ); ?></p>
					<?php if ( $has_db_copy ) : ?>
						<p class="description">
							<?php esc_html_e( 'A copy is also stored in the database on this site. The constants override it, so it is not the one being used - but it is still in wp_options, and it travels in every database export and support dump. Remove it.', 'wordcamp-airtable-connector' ); ?>
						</p>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( ! $locked || $has_db_copy ) : ?>
					<p class="submit">
						<?php if ( ! $locked ) : ?>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Save credential', 'wordcamp-airtable-connector' ); ?></button>
						<?php endif; ?>
						<?php if ( $has_db_copy ) : ?>
							<button type="submit" class="button button-link-delete" name="what" value="clear_central"><?php esc_html_e( 'Remove stored credential', 'wordcamp-airtable-connector' ); ?></button>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			</form>

			<h2><?php esc_html_e( 'Log', 'wordcamp-airtable-connector' ); ?></h2>
			<?php $this->render_log(); ?>
		</div>
		<?php
	}

	/**
	 * Render the recent log entries.
	 *
	 * @return void
	 */
	protected function render_log() {
		$entries = WCAC_Logger::recent( 60 );

		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'Nothing logged yet.', 'wordcamp-airtable-connector' ) . '</p>';

			return;
		}
		?>
		<table class="widefat striped wcac-log">
			<thead>
				<tr>
					<th class="wcac-log-when"><?php esc_html_e( 'When', 'wordcamp-airtable-connector' ); ?></th>
					<th class="wcac-log-level"><?php esc_html_e( 'Level', 'wordcamp-airtable-connector' ); ?></th>
					<th><?php esc_html_e( 'Message', 'wordcamp-airtable-connector' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) : ?>
					<tr>
						<td class="wcac-log-when"><?php echo esc_html( $this->ago( $entry['time'] ) ); ?></td>
						<td><span class="wcac-level wcac-level-<?php echo esc_attr( $entry['level'] ); ?>"><?php echo esc_html( $entry['level'] ); ?></span></td>
						<td><?php echo esc_html( $entry['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wcac-actions">
			<?php wp_nonce_field( 'wcac_action' ); ?>
			<input type="hidden" name="action" value="wcac_action" />
			<button class="button" name="what" value="clear_log"><?php esc_html_e( 'Clear log', 'wordcamp-airtable-connector' ); ?></button>
		</form>
		<?php
	}

	/**
	 * One run-state value, tolerating a state row written before 1.1.0.
	 *
	 * WCAC_Sync::state() fills the Campus Connect defaults in, but this screen
	 * is the first thing an operator opens after an upgrade and a missing key
	 * must render as "never" rather than as a notice above the page title.
	 *
	 * @param array  $state    Run state.
	 * @param string $key      State key.
	 * @param mixed  $fallback Value when the key is absent.
	 * @return mixed
	 */
	protected function state_value( array $state, $key, $fallback = '' ) {
		return isset( $state[ $key ] ) ? $state[ $key ] : $fallback;
	}

	/**
	 * A timestamp as "3 minutes ago" or "in 4 minutes".
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	protected function ago( $timestamp ) {
		$now = time();

		if ( $timestamp > $now ) {
			/* translators: %s: human-readable time difference. */
			return sprintf( __( 'in %s', 'wordcamp-airtable-connector' ), human_time_diff( $now, $timestamp ) );
		}

		/* translators: %s: human-readable time difference. */
		return sprintf( __( '%s ago', 'wordcamp-airtable-connector' ), human_time_diff( $timestamp, $now ) );
	}
}
