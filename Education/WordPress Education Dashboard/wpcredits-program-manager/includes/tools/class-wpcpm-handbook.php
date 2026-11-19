<?php
/**
 * Tool - Handbook assistant.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Education Handbook assistant: sync it, search it, answer from it.
 *
 * **A tool rather than a fifth module.** A module in this plugin is an audience - it owns a
 * role and the content the people holding it see. The assistant owns no audience: students,
 * mentors, institutions and program managers all use the same one. Registering it as a module
 * would mean inventing a role for it that describes nobody.
 *
 * From the outside there is no difference. It has its own screen in the same menu and its
 * own page on the front end.
 */
class WPCPM_Handbook extends WPCPM_Tool {

	/** Admin-post action for the sample question. Kept as a GET-shaped screen, see below. */
	const ARG_ASK = 'wpcpm_ask';

	/** Admin-post action of the Rebuild the index button. */
	const ACTION_REBUILD = 'wpcpm_guide_rebuild';

	/** The channel the rebuild's outcome is flashed on, for the screen it goes back to. */
	const FLASH = 'wpcpm-handbook-rebuild';

	/**
	 * Tool ID.
	 *
	 * @return string
	 */
	public function id() {
		return 'handbook';
	}

	/**
	 * Tool name.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Need help?', 'wpcredits-program-manager' );
	}

	/**
	 * One-line description for the Tools screen.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Answers questions from this site\'s guides and the WordPress Credits handbook, for the logged-in people its Who can ask setting names.', 'wpcredits-program-manager' );
	}

	/**
	 * Hooks.
	 */
	public function boot() {
		// The button that reads every guide again: on the screen whether Need help? is on or off.
		add_action( 'admin_post_' . self::ACTION_REBUILD, array( $this, 'handle_rebuild' ) );

		// A key pasted, changed or removed in Settings > Connectors forgets which provider offers the
		// model, on or off: after Connectors registers its connectors at `init` 15.
		add_action( 'init', array( 'WPCPM_Handbook_Answer', 'watch_keys' ), 20 );

		if ( ! self::is_enabled() ) {
			// Whatever switched it off, the next request also clears the schedules the
			// version that kept a local copy left behind.
			self::clear_legacy_schedules();

			add_action( 'init', array( 'WPCPM_Handbook_Assistant', 'apply_visibility' ), 20 );

			return;
		}

		add_action( 'init', array( 'WPCPM_Handbook_Assistant', 'apply_visibility' ), 20 );

		WPCPM_Handbook_Assistant::init();
	}

	/**
	 * Whether the assistant is switched on.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) WPCPM_Settings::get_value( 'handbook_enabled', true );
	}

	/**
	 * Ready once there is anything to answer from: it is switched on and the index holds a passage. A
	 * site with no AI provider is ready all the same, since its answers are the passages themselves.
	 *
	 * @return bool
	 */
	public function is_ready() {
		return self::is_enabled() && WPCPM_Handbook_Answer::ready();
	}

	/**
	 * A short status line for the Tools screen: why nobody gets an answer, while nobody does, which is
	 * the switch or an index with nothing in it, and otherwise who writes the answers: the provider set
	 * up in Settings > Connectors, or the passages alone.
	 *
	 * @return string
	 */
	public function status_line() {
		if ( ! self::is_enabled() ) {
			return __( 'Switched off.', 'wpcredits-program-manager' );
		}

		if ( ! WPCPM_Handbook_Answer::ready() ) {
			return __( 'No index yet: the guides have not been read.', 'wpcredits-program-manager' );
		}

		switch ( WPCPM_Handbook_Answer::provider_state() ) {
			case 'ready':
				return sprintf(
					/* translators: 1: the AI provider's name, 2: the model's name. */
					__( 'Answering through %1$s with %2$s.', 'wpcredits-program-manager' ),
					WPCPM_Handbook_Answer::provider_label(),
					WPCPM_Handbook_Answer::model()
				);

			case 'not_offered':
				return sprintf(
					/* translators: %s: the model's name. */
					__( 'Answering with passages only: the AI provider does not offer the model %s.', 'wpcredits-program-manager' ),
					WPCPM_Handbook_Answer::model()
				);

			case 'refused':
				return __( 'Answering with passages only: the AI provider refused the request. Check the key in Settings > Connectors.', 'wpcredits-program-manager' );

			case 'unreachable':
				return __( 'Answering with passages only: the AI provider is not answering right now.', 'wpcredits-program-manager' );
		}

		return __( 'Answering with passages only: no AI provider is set up in Settings > Connectors.', 'wpcredits-program-manager' );
	}

	/**
	 * The assistant's settings, which its screen's Settings section draws and saves.
	 *
	 * @return string[]
	 */
	public function settings_keys() {
		return array( 'handbook_enabled', 'handbook_model', 'handbook_access', 'handbook_limit' );
	}

	/**
	 * What the Settings section says under its heading: what the question box is, and in its fold
	 * where each question goes, which is worth reading before a provider is chosen.
	 *
	 * @return array
	 */
	protected function settings_intro() {
		return array(
			'sentence' => __( 'A question box for people on the program, answered from this site\'s guides and the WordPress Credits handbook.', 'wpcredits-program-manager' ),
			'details'  => __( 'The site keeps an index of its guides, the FAQ and the handbook, finds the passages a question needs, and the AI provider set up in Settings > Connectors writes the answer from them. Each question, and the passages found for it, go to that company.', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The rows of the Settings section: the switch, the model, who may ask, and how often. The provider
	 * and its key are set up in Settings > Connectors, not here.
	 */
	protected function render_settings_rows() {
		$settings = WPCPM_Settings::get();

		$enabled = __( 'Need help?', 'wpcredits-program-manager' );

		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="handbook_enabled" value="1"%2$s> %3$s</label><p class="description">%4$s</p>',
			esc_html( $enabled ),
			checked( ! empty( $settings['handbook_enabled'] ), true, false ),
			esc_html__( 'Switch it on', 'wpcredits-program-manager' ),
			esc_html__( 'Off means the question box answers nobody, the header button disappears and the page it lives on is unpublished.', 'wpcredits-program-manager' )
		);
		WPCPM_Settings_Rows::close_row( $enabled, __( 'Nothing is deleted, so switching it back on restores all of it.', 'wpcredits-program-manager' ) );

		// A choice of models only when the provider lists more than the default: the default alone,
		// beside the model saved, would offer nothing to choose, so the field is then the text input it
		// always was (`WPCPM_Settings_Rows::select_row()` with no list), holding the model saved. As a
		// list, the default first, marked as the default; the model saved, when it is another, after it.
		$models  = array();
		$default = (string) WPCPM_Settings::defaults()['handbook_model'];
		$listed  = WPCPM_Settings_Choices::models();

		if ( array() !== array_diff( array_map( 'strval', array_keys( $listed ) ), array( $default ) ) ) {
			foreach ( $listed as $model => $model_label ) {
				/* translators: %s: the name of the model the plugin uses by default. */
				$models[ $model ] = $default === (string) $model ? sprintf( __( '%s (default)', 'wpcredits-program-manager' ), $model_label ) : $model_label;
			}
		}

		WPCPM_Settings_Rows::select_row(
			'handbook_model',
			__( 'Model', 'wpcredits-program-manager' ),
			$settings['handbook_model'],
			$models,
			__( 'The model\'s name as the provider lists it.', 'wpcredits-program-manager' ),
			'',
			__( 'Leave it as gemini-flash-latest unless you have a reason not to. It is an alias that always points at the current Gemini Flash, so it cannot be retired out from under this site - which has already happened twice to specific version numbers.', 'wpcredits-program-manager' )
		);

		// The people who manage the program are called Administrators, the name every screen gives
		// the people holding the program's capability, whatever role gives it to them.
		$audiences = array(
			'mentor'  => __( 'Mentors and administrators', 'wpcredits-program-manager' ),
			'program' => __( 'Students, institutions and sponsors as well', 'wpcredits-program-manager' ),
			'any'     => __( 'Anybody logged in to this site', 'wpcredits-program-manager' ),
			'manage'  => __( 'Administrators only', 'wpcredits-program-manager' ),
		);

		$radios = '';

		foreach ( $audiences as $value => $label ) {
			$radios .= sprintf(
				'<label><input type="radio" name="handbook_access" value="%1$s"%2$s> %3$s</label><br>',
				esc_attr( $value ),
				checked( $settings['handbook_access'], $value, false ),
				esc_html( $label )
			);
		}

		$who = __( 'Who can ask', 'wpcredits-program-manager' );

		printf(
			'<tr><th scope="row">%1$s</th><td><fieldset>%2$s</fieldset><p class="description">%3$s</p>',
			esc_html( $who ),
			$radios, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built immediately above from escaped parts.
			esc_html__( 'Never anybody logged out, whatever this says.', 'wpcredits-program-manager' )
		);
		WPCPM_Settings_Rows::close_row( $who, __( 'Each person is answered only from the guides their own access level lets them read, and from the FAQ and the WordPress Credits handbook.', 'wpcredits-program-manager' ) );

		$limit = __( 'Questions per person per hour', 'wpcredits-program-manager' );

		printf(
			'<tr><th scope="row"><label for="wpcpm-handbook-limit">%1$s</label></th><td><input type="number" id="wpcpm-handbook-limit" name="handbook_limit" value="%2$d" min="0" max="200" step="1" class="small-text"><p class="description">%3$s</p>',
			esc_html( $limit ),
			(int) $settings['handbook_limit'],
			esc_html__( 'How many questions one person may have answered in an hour; 0 removes this limit.', 'wpcredits-program-manager' )
		);
		WPCPM_Settings_Rows::close_row( $limit, __( 'Past the limit they get the parts of the guides that match their question, without a written answer. Whatever this says, the site writes at most 300 answers a day.', 'wpcredits-program-manager' ) );
	}

	/**
	 * Activation: nothing to build.
	 *
	 * There is no table, no schedule and no page. That is the whole point of the arrangement
	 * - and it is why this method is empty rather than missing.
	 */
	public function activate() {}

	/**
	 * Deactivation: clear what an older version scheduled.
	 */
	public function deactivate() {
		self::clear_legacy_schedules();
	}

	/**
	 * Clear the schedules the local-copy version created.
	 *
	 * Named as literals because the classes that owned them no longer exist. A site upgrading
	 * from that version still has both, and a scheduled hook with no callback fails silently
	 * for ever.
	 */
	private static function clear_legacy_schedules() {
		foreach ( array( 'wpcpm_handbook_sync_daily', 'wpcpm_handbook_sync_tick' ) as $hook ) {
			if ( wp_next_scheduled( $hook ) ) {
				wp_clear_scheduled_hook( $hook );
			}
		}
	}

	/**
	 * Uninstall: remove everything this module ever created, including by earlier versions.
	 */
	public function uninstall() {
		global $wpdb;

		WPCPM_Handbook_Answer::clear_limits();

		// The page, but only if nobody has made it their own. It was created by this module
		// and should go with it; a page somebody has written on is theirs, and deleting that
		// because a plugin was removed would be the worse mistake.
		$page_id = (int) get_option( WPCPM_Handbook_Assistant::OPT_PAGE );
		$page    = $page_id ? get_post( $page_id ) : null;

		if ( $page instanceof WP_Post && '<!-- wp:' . WPCPM_Handbook_Assistant::BLOCK . ' /-->' === trim( $page->post_content ) ) {
			wp_delete_post( $page_id, true );
		}

		delete_option( WPCPM_Handbook_Assistant::OPT_PAGE );
		delete_option( WPCPM_Handbook_Assistant::OPT_APPLIED );

		// Per-person rate counters. They expire within the hour, but "expires eventually" is
		// not the same as removed, and there is one row per person who ever asked anything.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transients are addressable only by exact name; there are as many as there are users.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . WPCPM_Handbook_Answer::RATE_PREFIX ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . WPCPM_Handbook_Answer::RATE_PREFIX ) . '%'
			)
		);

		// Everything the versions that kept a local copy created. None of these classes
		// exists now, so every name here is a literal - and every one of them is still on a
		// site that upgraded through those versions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Our own table, on uninstall; the name is built from the site prefix.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wpcpm_handbook' );

		foreach ( array(
			'wpcpm_handbook_schema',
			'wpcpm_handbook_state',
			'wpcpm_handbook_report',
			'wpcpm_handbook_last',
			'wpcpm_handbook_error',
			'wpcpm_handbook_sources',
			// The marker of the retired-model move this tool made until the settings' version 4.
			'wpcpm_handbook_model_fixed',
		) as $option ) {
			delete_option( $option );
		}

		// An even earlier version kept each page as a post. That post type is not registered
		// any more, so `get_posts()` cannot find them.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The post type is unregistered, so WP_Query has no type object; uninstall only.
		$orphans = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'wpcpm_handbook' ) );

		foreach ( $orphans as $orphan ) {
			wp_delete_post( (int) $orphan, true );
		}

		foreach ( array( '_wpcpm_hb_source', '_wpcpm_hb_link', '_wpcpm_hb_modified', '_wpcpm_hb_passages' ) as $meta_key ) {
			delete_metadata( 'post', 0, $meta_key, '', true );
		}
	}

	/**
	 * Render the tool's screen.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html( $this->label() ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html( $this->description() ) . '</p>';

		if ( ! self::is_enabled() ) {
			printf(
				'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
				esc_html__( 'Need help? is switched off, so it is not answering anybody.', 'wpcredits-program-manager' ),
				esc_url( '#settings' ),
				esc_html__( 'Turn it on in Settings', 'wpcredits-program-manager' )
			);
		}

		$this->render_rebuilt();

		// Its settings at the top, under the notice that sends a manager to the switch, and above how
		// answers are produced.
		$this->render_settings();

		$this->render_how();
		$this->render_try();

		echo '</div>';
	}

	/**
	 * What the last press of Rebuild the index did, once, for the person who pressed it.
	 */
	private function render_rebuilt() {
		$flash = WPCPM_Flash::take( self::FLASH );

		if ( ! is_array( $flash ) || empty( $flash['message'] ) ) {
			return;
		}

		printf(
			'<div class="notice %1$s"><p>%2$s</p></div>',
			esc_attr( ! empty( $flash['busy'] ) ? 'notice-warning' : 'notice-success' ),
			esc_html( (string) $flash['message'] )
		);
	}

	/**
	 * The Rebuild the index button: read every guide, the FAQ and the handbook again, and say how many
	 * passages the index holds, or that another rebuild is running.
	 *
	 * The capability first, then the method, then the nonce, then the rebuild. The button posts: a link
	 * to admin-post.php with the action, followed or prefetched, is refused with a 405 before its nonce
	 * is looked at, so no request but the button's ever starts a rebuild.
	 */
	public function handle_rebuild() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';

		if ( 'POST' !== $method ) {
			wp_die( esc_html__( 'Rebuild the index from its button on the Need help? screen.', 'wpcredits-program-manager' ), 405 );
		}

		check_admin_referer( self::ACTION_REBUILD );

		$result = WPCPM_Guide_Sources::rebuild();
		$busy   = ! empty( $result['busy'] ) || ! empty( $result['lost'] );

		WPCPM_Flash::set(
			self::FLASH,
			array(
				'busy'    => $busy,
				'message' => $busy
					? __( 'A rebuild is already running.', 'wpcredits-program-manager' )
					: sprintf(
						/* translators: %d: the number of passages the index holds. */
						__( 'The index was rebuilt: %d passages.', 'wpcredits-program-manager' ),
						WPCPM_Guide_Index::count()
					),
			)
		);

		wp_safe_redirect( $this->admin_url() );
		exit;
	}

	/**
	 * How answers are produced: which provider writes them and with what model, what the index holds,
	 * and the button that reads every guide again.
	 */
	private function render_how() {
		$state = WPCPM_Handbook_Answer::provider_state();
		$label = WPCPM_Handbook_Answer::provider_label();

		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'How answers are produced', 'wpcredits-program-manager' ) . '</h2>';

		echo '<table class="form-table" role="presentation"><tbody>';

		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
			esc_html__( 'Provider', 'wpcredits-program-manager' ),
			'' !== $label
				? esc_html( $label )
				: sprintf(
					'<span class="wpcpm-warning">%1$s</span> <a href="%2$s">%3$s</a>',
					esc_html__( 'None set up: answers are passages only.', 'wpcredits-program-manager' ),
					esc_url( admin_url( 'options-connectors.php' ) ),
					esc_html__( 'Set one up in Settings > Connectors', 'wpcredits-program-manager' )
				)
		);

		$model_states = array(
			'ready'       => __( 'Offered', 'wpcredits-program-manager' ),
			'not_offered' => __( 'Not offered by the provider', 'wpcredits-program-manager' ),
			'refused'     => __( 'Refused by the provider', 'wpcredits-program-manager' ),
			'unreachable' => __( 'The provider is not answering right now', 'wpcredits-program-manager' ),
			'not_set_up'  => __( 'No provider is set up', 'wpcredits-program-manager' ),
		);

		printf(
			'<tr><th scope="row">%1$s</th><td><code>%2$s</code> - %3$s</td></tr>',
			esc_html__( 'Model', 'wpcredits-program-manager' ),
			esc_html( WPCPM_Handbook_Answer::model() ),
			esc_html( isset( $model_states[ $state ] ) ? $model_states[ $state ] : $model_states['not_set_up'] )
		);

		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
			esc_html__( 'Sources', 'wpcredits-program-manager' ),
			$this->sources_html() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
		);

		ob_start();
		printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_REBUILD ) );
		wp_nonce_field( self::ACTION_REBUILD );
		submit_button( __( 'Rebuild the index', 'wpcredits-program-manager' ), 'secondary', 'submit', false );
		echo '</form>';
		$form = ob_get_clean();

		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s<p class="description">%3$s</p></td></tr>',
			esc_html__( 'Rebuild the index', 'wpcredits-program-manager' ),
			$form, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built immediately above from escaped parts and core's own nonce field and button.
			esc_html__( 'Reads every guide, the FAQ and the handbook again.', 'wpcredits-program-manager' )
		);

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * What the index holds, source by source (its title, its passages, when it was indexed, or what went
	 * wrong), and the handbook's own line: when it last synced and how it went.
	 *
	 * @return string HTML; every printed value escaped.
	 */
	private function sources_html() {
		$format  = trim( (string) get_option( 'date_format', 'j F Y' ) . ' ' . (string) get_option( 'time_format', 'H:i' ) );
		$sources = WPCPM_Guide_Index::sources();
		$html    = '';

		if ( array() === $sources ) {
			$html .= '<p>' . esc_html__( 'None yet: the guides have not been read.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			$html .= '<ul class="wpcpm-notices">';

			foreach ( $sources as $source ) {
				$title = isset( $source['title'] ) ? (string) $source['title'] : '';

				if ( ! empty( $source['error'] ) ) {
					$html .= sprintf(
						'<li>%s</li>',
						esc_html(
							sprintf(
								/* translators: 1: a guide's title, 2: what went wrong when it was indexed. */
								__( '%1$s: not indexed (%2$s)', 'wpcredits-program-manager' ),
								$title,
								(string) $source['error']
							)
						)
					);

					continue;
				}

				$passages = isset( $source['passages'] ) ? (int) $source['passages'] : 0;
				$when     = ! empty( $source['indexed_at'] ) ? wp_date( $format, (int) $source['indexed_at'] ) : '';

				$html .= sprintf(
					'<li>%s</li>',
					esc_html(
						sprintf(
							/* translators: 1: a guide's title, 2: its number of passages, 3: when it was indexed. */
							_n( '%1$s: %2$d passage, indexed %3$s', '%1$s: %2$d passages, indexed %3$s', $passages, 'wpcredits-program-manager' ),
							$title,
							$passages,
							$when
						)
					)
				);
			}

			$html .= '</ul>';
		}

		$handbook = get_option( WPCPM_Guide_Sources::OPT_HANDBOOK, array() );

		if ( ! is_array( $handbook ) || empty( $handbook['synced_at'] ) ) {
			$line = __( 'The handbook has not been read yet.', 'wpcredits-program-manager' );
		} elseif ( ! empty( $handbook['ok'] ) ) {
			$line = sprintf(
				/* translators: 1: when the handbook last synced, 2: the number of its pages. */
				_n( 'The handbook last synced %1$s: %2$d page.', 'The handbook last synced %1$s: %2$d pages.', (int) $handbook['pages'], 'wpcredits-program-manager' ),
				wp_date( $format, (int) $handbook['synced_at'] ),
				(int) $handbook['pages']
			);
		} else {
			$line = sprintf(
				/* translators: 1: when the handbook last tried to sync, 2: what went wrong. */
				__( 'The handbook last tried to sync %1$s and failed: %2$s', 'wpcredits-program-manager' ),
				wp_date( $format, (int) $handbook['synced_at'] ),
				isset( $handbook['error'] ) ? (string) $handbook['error'] : ''
			);
		}

		// Pages the handbook sent without an address, a modified time or text: kept as the index held them.
		$malformed = is_array( $handbook ) && ! empty( $handbook['synced_at'] ) && ! empty( $handbook['malformed'] ) ? (int) $handbook['malformed'] : 0;

		if ( $malformed > 0 ) {
			$line .= ' ' . sprintf(
				/* translators: %d: how many of the handbook's pages came without their address, modified time or text. */
				_n( '%d of its pages came incomplete and was kept as the index held it.', '%d of its pages came incomplete and were kept as the index held them.', $malformed, 'wpcredits-program-manager' ),
				$malformed
			);
		}

		return $html . '<p class="description">' . esc_html( $line ) . '</p>';
	}

	/**
	 * Ask a question here, as the Administrator using the screen: their answer reads every guide, with
	 * the Administrator guide favored, which is not what a student or a mentor would be given.
	 */
	private function render_try() {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Try a question', 'wpcredits-program-manager' ) . '</h2>';

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Asked as you: an Administrator\'s answer can come from every guide, and the Administrator guide comes first.', 'wpcredits-program-manager' )
		);

		// A GET form back to this screen: asking changes nothing, so the question belongs in
		// the URL where it survives a reload.
		printf( '<form method="get" action="%s">', esc_url( admin_url( 'admin.php' ) ) );
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $this->page_slug() ) );
		printf(
			'<p><input type="search" name="%1$s" value="%2$s" class="regular-text" placeholder="%3$s" /> ',
			esc_attr( self::ARG_ASK ),
			esc_attr( WPCPM_Handbook_Assistant::question() ),
			esc_attr__( 'How does a student get their certificate?', 'wpcredits-program-manager' )
		);
		submit_button( __( 'Ask', 'wpcredits-program-manager' ), 'secondary', 'submit', false );
		echo '</p></form>';

		$question = WPCPM_Handbook_Assistant::question();

		if ( '' === $question ) {
			echo '</div>';

			return;
		}

		$answer = WPCPM_Handbook_Answer::ask( $question );

		if ( ! empty( $answer['unsourced'] ) ) {
			printf(
				'<p class="wpcpm-warning">%s</p>',
				esc_html__( 'This answer does not point to a passage of the guides, so it could not be checked against them. Treat it as unconfirmed.', 'wpcredits-program-manager' )
			);
		}

		if ( '' !== $answer['notice'] ) {
			printf( '<p class="description">%s</p>', esc_html( $answer['notice'] ) );
		}

		printf( '<div class="wpcpm-handbook-preview">%s</div>', wp_kses_post( wpautop( WPCPM_Handbook_Answer::to_html( $answer['text'] ) ) ) );

		if ( empty( $answer['sources'] ) ) {
			echo '</div>';

			return;
		}

		echo '<h3>' . esc_html__( 'Sources', 'wpcredits-program-manager' ) . '</h3>';
		echo '<ol>';

		foreach ( $answer['sources'] as $source ) {
			printf(
				'<li><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>%3$s</li>',
				esc_url( $source['link'] ),
				esc_html( $source['title'] ),
				'' !== $source['extract']
					? '<br><span class="description">' . esc_html( $source['extract'] ) . '</span>'
					: ''
			);
		}

		echo '</ol>';
		echo '</div>';
	}
}
