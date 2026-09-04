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
 * role and the content the people holding it see, and the Overview screen prints that role
 * beside its name. The assistant owns no audience: students, mentors, institutions and
 * program managers all use the same one. Registering it as a module would mean inventing a
 * role for it to name, which is a lie the Overview screen would then repeat.
 *
 * From the outside there is no difference. It has its own screen in the same menu and its
 * own page on the front end.
 */
class WPCPM_Handbook extends WPCPM_Tool {

	/** Admin-post action for the sample question. Kept as a GET-shaped screen, see below. */
	const ARG_ASK = 'wpcpm_ask';

	/** Records which model revision this site has been brought up to. */
	const OPT_MODEL_FIXED = 'wpcpm_handbook_model_fixed';

	/** The current model revision. Bump this whenever the default model changes. */
	const MODEL_VERSION = 2;

	/**
	 * Model names this plugin has shipped as its default.
	 *
	 * Only a value the plugin itself put there is ever replaced. A model somebody typed
	 * deliberately is theirs, even if it is retired - they may be waiting on a replacement, or
	 * pointing at something this list has never heard of.
	 *
	 * Providers retire models on their own schedule and the failure is not subtle: the request
	 * comes back refused and the reader gets nothing. Changing the default in `defaults()` is
	 * not enough, because defaults only reach a site that has never saved its settings.
	 */
	const SHIPPED_MODELS = array( 'gemini-2.0-flash', 'gemini-2.5-flash', 'gemini-1.5-flash', 'gemini-pro', 'llama-3.3-70b-versatile' );

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
	 * One-line description for the Modules screen.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Answers questions from the WordPress documentation, for logged-in mentors and program managers. The AI provider does the searching; nothing is stored on this site.', 'wpcredits-program-manager' );
	}

	/**
	 * Hooks.
	 */
	public function boot() {
		// Before anything can ask the provider anything, and once per revision.
		add_action( 'init', array( __CLASS__, 'maybe_update_model' ), 5 );

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
	 * Move a site off a default model the provider has retired.
	 *
	 * Runs at most once per revision: the marker is written whatever the outcome, so a site
	 * that has chosen its own model is not reconsidered on every request.
	 */
	public static function maybe_update_model() {
		if ( (int) get_option( self::OPT_MODEL_FIXED ) >= self::MODEL_VERSION ) {
			return;
		}

		update_option( self::OPT_MODEL_FIXED, self::MODEL_VERSION, false );

		// A provider that no longer exists. Google AI Studio is the only one now, and leaving
		// the old value in place would read as "configured" while answering nothing.
		if ( 'openai' === (string) WPCPM_Settings::get_value( 'handbook_provider', '' ) ) {
			WPCPM_Settings::save( array( 'handbook_provider' => 'gemini' ) );
		}

		$current = (string) WPCPM_Settings::get_value( 'handbook_model', '' );

		if ( ! in_array( $current, self::SHIPPED_MODELS, true ) ) {
			return;
		}

		$defaults = WPCPM_Settings::defaults();

		if ( $current === $defaults['handbook_model'] ) {
			return;
		}

		WPCPM_Settings::save( array( 'handbook_model' => $defaults['handbook_model'] ) );
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
	 * Ready once a provider can actually answer something.
	 *
	 * There is no local copy any more, so "ready" is no longer "has it been synced" - it is
	 * whether anything at all can produce an answer.
	 *
	 * @return bool
	 */
	public function is_ready() {
		return self::is_enabled() && WPCPM_Handbook_Answer::provider_ready();
	}

	/**
	 * A short status line for the Modules screen.
	 *
	 * @return string
	 */
	public function status_line() {
		if ( ! self::is_enabled() ) {
			return __( 'Switched off.', 'wpcredits-program-manager' );
		}

		if ( ! WPCPM_Handbook_Answer::provider_ready() ) {
			return __( 'No AI provider is configured, so questions cannot be answered.', 'wpcredits-program-manager' );
		}

		return sprintf(
			/* translators: %s: provider name. */
			__( 'Answering through %s.', 'wpcredits-program-manager' ),
			WPCPM_Handbook_Answer::provider_label()
		);
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
				esc_url( WPCPM_Admin::settings_url() ),
				esc_html__( 'Turn it on in Settings', 'wpcredits-program-manager' )
			);
		}

		$this->render_how();
		$this->render_try();

		echo '</div>';
	}

	/**
	 * How it works, and what that costs in guarantees.
	 */
	private function render_how() {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'How answers are produced', 'wpcredits-program-manager' ) . '</h2>';

		echo '<table class="form-table" role="presentation"><tbody>';

		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
			esc_html__( 'Provider', 'wpcredits-program-manager' ),
			WPCPM_Handbook_Answer::provider_ready()
				? esc_html( WPCPM_Handbook_Answer::provider_label() )
				: sprintf(
					'<span class="wpcpm-warning">%1$s</span> <a href="%2$s">%3$s</a>',
					esc_html__( 'None configured - nothing can be answered.', 'wpcredits-program-manager' ),
					esc_url( WPCPM_Admin::settings_url() ),
					esc_html__( 'Add one in Settings', 'wpcredits-program-manager' )
				)
		);

		$hosts = '<ul class="wpcpm-notices">';

		foreach ( WPCPM_Handbook_Answer::allowed_hosts() as $host ) {
			$hosts .= sprintf( '<li><code>%s</code></li>', esc_html( $host ) );
		}

		$hosts .= '</ul>';

		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s<p class="description">%3$s</p></td></tr>',
			esc_html__( 'Sites it is told to use', 'wpcredits-program-manager' ),
			$hosts, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built immediately above from escaped parts.
			esc_html__( 'The provider searches the web itself. Google\'s search tool has no site filter, so this list is asked for in the instructions and then checked against the citations that come back: anything from elsewhere is not shown, and an answer citing nothing from these sites is marked as unverified.', 'wpcredits-program-manager' )
		);

		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
			esc_html__( 'Stored on this site', 'wpcredits-program-manager' ),
			esc_html__( 'Nothing. There is no copy of the documentation, no index, and no scheduled reading - so nothing to refresh and nothing to go stale. The trade is that without a provider there is no answer at all.', 'wpcredits-program-manager' )
		);

		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
			esc_html__( 'Where people ask', 'wpcredits-program-manager' ),
			esc_html__( 'From "Need help?" in the site header, and from the Resources section at the foot of the Mentor Report Card.', 'wpcredits-program-manager' )
		);

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Ask a question here, so a manager can see what people will get.
	 */
	private function render_try() {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Try a question', 'wpcredits-program-manager' ) . '</h2>';

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Exactly what somebody on the program would see, without leaving this screen.', 'wpcredits-program-manager' )
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
				esc_html__( 'This answer cites nothing from the sites listed above, so it cannot be verified against them. Treat it as unconfirmed.', 'wpcredits-program-manager' )
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

		echo '<h3>' . esc_html__( 'Pages it read', 'wpcredits-program-manager' ) . '</h3>';
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
