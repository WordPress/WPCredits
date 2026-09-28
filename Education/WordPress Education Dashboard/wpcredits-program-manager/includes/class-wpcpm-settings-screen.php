<?php
/**
 * The Settings screen.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Settings screen under the WPCredits Program menu, and the handler every settings form posts
 * to: its seven tabs in WordPress's own tab bar, and the Settings section each tool that keeps
 * settings draws on its own screen (`WPCPM_Tool::render_settings()`).
 *
 * A class of its own rather than more of `WPCPM_Admin`, which registers the menu and draws the
 * Overview and the Tools screen, so the screen that holds the settings and the handler that saves
 * them read as one thing. The rows each form is drawn with are `WPCPM_Settings_Rows`'s.
 */
class WPCPM_Settings_Screen {

	/**
	 * The screen's page slug, which its address names.
	 */
	const SETTINGS_SLUG = 'wpcpm-settings';

	/**
	 * The nonce every settings form carries, a tab's or a tool's Settings section, under its own name,
	 * which is also how the save handler tells that a settings form was posted.
	 */
	const SETTINGS_NONCE = 'wpcpm_save_settings';

	/**
	 * The hidden field a settings form names its scope in: a key of `WPCPM_Settings::scopes()`.
	 */
	const SETTINGS_TAB_FIELD = 'wpcpm_tab';

	/**
	 * The `admin-post.php` action, and its nonce's, of the button on the Connection tab that reads the
	 * Airtable lists again (`handle_refresh_lists()`).
	 */
	const REFRESH_ACTION = 'wpcpm_refresh_lists';

	/**
	 * The path of the page the site publishes the program manager guide at, whose content is the
	 * guide bin/build-docs.php builds: the page each section of the settings screen links its part
	 * of (`settings_guide()`).
	 */
	const GUIDE_PAGE_PATH = 'program-manager-guide';

	/**
	 * The three tools that keep settings of their own, in the order the Connection tab's first line
	 * names them (`render_tools_lede()`): each keeps them in the Settings section of its own screen.
	 * bin/test-admin-menu.php holds the list to the registry's tools that keep settings.
	 */
	const TOOLS_WITH_SETTINGS = array( 'mentor-status-checker', 'duplicate-finder', 'handbook' );

	/**
	 * Boolean settings that no settings form draws yet, neither a tab of this screen nor a tool's
	 * Settings section.
	 *
	 * The save handler reads every other switch of the scope it saves unconditionally, because
	 * an unticked box posts nothing and absent has to mean off. For a switch with no box on the
	 * form absent means nothing, so the handler carries these as stored and the save leaves them
	 * as they were. The form that holds a switch, a tab or a tool's Settings section, takes it off
	 * this list when it draws its box.
	 *
	 * **Empty since 1.85.1, and worth keeping rather than deleting.** It held
	 * `import_enabled` for as long as the import had no screen, and that was right at the
	 * time and wrong the moment the screen shipped: the setting existed, defaulted to off,
	 * and had nowhere to be turned on, so the feature was unreachable and the settings page
	 * gave no hint that it was there. The next switch that ships ahead of its surface goes
	 * here and comes out the same way.
	 */
	const UNRENDERED_SWITCHES = array();

	/**
	 * Hooks: the save every settings form posts, and the button that reads the Airtable lists again.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_settings_save' ) );
		add_action( 'admin_post_' . self::REFRESH_ACTION, array( $this, 'handle_refresh_lists' ) );
	}

	/**
	 * The settings screen's URL, at one of its tabs.
	 *
	 * @param string $tab A key of `settings_tabs()`, or '' for the screen's own address, which
	 *                    shows its first tab.
	 * @return string
	 */
	public static function settings_url( $tab = '' ) {
		$url = admin_url( 'admin.php?page=' . self::SETTINGS_SLUG );

		return '' === (string) $tab ? $url : add_query_arg( 'tab', rawurlencode( (string) $tab ), $url );
	}

	/**
	 * The settings screen's tabs, in the order its bar draws them.
	 *
	 * Each tab but one is one form whose slug is the scope it posts (`WPCPM_Settings::scopes()`), so
	 * its Save writes its own settings and leaves every other tab's as they are. Mail saves nothing:
	 * each of its controls is a form of its own, posting to its own handler. No tab holds a tool's
	 * settings: a tool that keeps some draws them on its own screen, in a form posting the tool's own
	 * scope to the same handler (`WPCPM_Tool::render_settings()`).
	 *
	 * @return array<string, string> Slug => label.
	 */
	public static function settings_tabs() {
		return array(
			'connection'   => __( 'Connection', 'wpcredits-program-manager' ),
			'people'       => __( 'Students and mentors', 'wpcredits-program-manager' ),
			'institutions' => __( 'Institutions', 'wpcredits-program-manager' ),
			'sponsors'     => __( 'Sponsors', 'wpcredits-program-manager' ),
			'security'     => __( 'Security', 'wpcredits-program-manager' ),
			'mail'         => __( 'Mail', 'wpcredits-program-manager' ),
			'advanced'     => __( 'Advanced', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The tab the settings screen shows: the one its address names, or Connection when the address
	 * names none or one the screen does not have.
	 *
	 * @return string A key of `settings_tabs()`.
	 */
	private static function current_tab() {
		$tab = WPCPM_Request::key( 'tab' );

		return isset( self::settings_tabs()[ $tab ] ) ? $tab : 'connection';
	}

	/**
	 * Persist a settings form: the settings of the scope it names and no other, whether the form is a
	 * tab of this screen or the Settings section a tool draws on its own screen. A form that names no
	 * scope, or one this version does not have, is refused and writes nothing.
	 *
	 * Hooked on every admin request, so it first asks whether a settings form was posted at all, by
	 * the nonce's field; then the nonce, then the right, the order the screen's other handlers keep.
	 */
	public function handle_settings_save() {
		if ( ! isset( $_POST[ self::SETTINGS_NONCE ] ) ) {
			return;
		}

		check_admin_referer( self::SETTINGS_NONCE, self::SETTINGS_NONCE );

		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		// The scope the form names is the one set of settings this save may write, so Save on one
		// tab cannot reset what another tab holds, and one this version does not have is refused
		// rather than guessed at. Every form of the screen names its own, so a form that names none
		// is a page drawn before the screen had tabs: read as every scope, as that page's single
		// form once was, its Save would switch off every box and reset every leaving rule of every
		// tab it did not draw, so it is refused the same way.
		$scope = WPCPM_Request::posted_text( self::SETTINGS_TAB_FIELD );
		$keys  = WPCPM_Settings::scope_keys( $scope );

		if ( '' === $scope || null === $keys ) {
			self::flash_outcome( 'refused' );

			wp_safe_redirect( self::settings_url() );
			exit;
		}

		// Derived from the defaults, through the scope's list, rather than hand-listed. The
		// hand-written list was a standing invitation to add a field to the form, add its sanitizer
		// to `WPCPM_Settings::save()`, and forget the third place - at which point the field
		// renders, accepts input, posts it, and is silently discarded. Twenty-one fields were in
		// that state, including the whole mentor-checker card and the AI provider.
		//
		// Safe to iterate every setting of the scope because of the `isset()`: a key this form does
		// not render is simply absent from the request and is left alone. The one shape that cannot
		// work that way is a checkbox, which posts nothing at all when unticked - so those are read
		// unconditionally below, which is only correct for the ones this form renders. The switches
		// it does not render yet are listed in UNRENDERED_SWITCHES and carried as stored: read
		// unconditionally, the first save of this screen would have switched every one of them off,
		// and left out, a save of their scope would. bin/test-settings.php checks the list against
		// the form.
		$input    = array();
		$defaults = WPCPM_Settings::defaults();
		$current  = WPCPM_Settings::get();

		foreach ( $keys as $key ) {
			if ( is_bool( $defaults[ $key ] ) ) {
				continue;
			}

			if ( isset( $_POST[ $key ] ) ) {
				$input[ $key ] = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in WPCPM_Settings::save().
			}
		}

		foreach ( $keys as $key ) {
			if ( is_bool( $defaults[ $key ] ) ) {
				$input[ $key ] = in_array( $key, static::UNRENDERED_SWITCHES, true ) ? ! empty( $current[ $key ] ) : ! empty( $_POST[ $key ] );
			}
		}

		// "Currently mentoring" as this page drew it, which is not a setting and so is not among the
		// defaults read above: with it, a textarea nobody changed leaves the stored list alone, and a
		// page drawn before a track was published cannot write that track's status out of the list
		// (the deep check of 1.109.1, BUILDER-3).
		if ( isset( $_POST[ WPCPM_Settings::FIELD_DRAWN_STATUSES ] ) ) {
			$input[ WPCPM_Settings::FIELD_DRAWN_STATUSES ] = wp_unslash( $_POST[ WPCPM_Settings::FIELD_DRAWN_STATUSES ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in WPCPM_Settings::student_statuses_from().
		}

		// A change to the list that would take the status of a track the site runs from its
		// definition out of it is not made: the next students sync would take the Student role from
		// everybody on the track, and the status leaves once the track is off the live site (the
		// design's 7.5, BUILDER-3). Everything else posted is saved as ever and the list stays as
		// stored, with a notice naming the track, rather than the whole save refused and everything
		// typed on the page lost with it (the ruling of BUILDER-3's fix round, 23 September 2026).
		$dropped = WPCPM_Settings::tracks_dropped_by( $input );

		if ( array() !== $dropped ) {
			unset( $input['student_statuses'], $input[ WPCPM_Settings::FIELD_DRAWN_STATUSES ] );

			WPCPM_Flash::set( 'settings-refused', $dropped );
		}

		// The same rule for "Past students": a past status is refused to every track, so the compile
		// below would take a live track holding one off the site with nothing standing in for it,
		// the program's four original tracks included (`WPCPM_Settings::tracks_ended_by()`).
		$ended = WPCPM_Settings::tracks_ended_by( $input );

		if ( array() !== $ended ) {
			unset( $input['past_statuses'] );

			WPCPM_Flash::set( 'settings-past-refused', $ended );
		}

		$saved = WPCPM_Settings::save( $input, $scope );

		// "Currently mentoring" and the past statuses are rules every published track was compiled
		// against, so a save that changes them would otherwise leave the live tracks, and the list
		// of what the last compile left out, answering for the settings as they were (decision 14).
		// A save that left both as they were, as every tab's save but one always does, has nothing
		// to compile.
		//
		// It hangs on this handler, `WPCPM_Settings::save()`'s one caller, rather than on `save()`,
		// whose callers once included the handbook's model migration on `init` priority 5, before
		// the track post type is registered at 10, where a compile was wasted work on an unrelated
		// path (the T2b whole-branch review). That migration writes through
		// `WPCPM_Settings::patch()` now, which asks this same question for itself.
		if ( class_exists( 'WPCPM_Track_Store' ) && WPCPM_Settings::changed( $current, $saved, array( 'student_statuses', 'past_statuses' ) ) ) {
			WPCPM_Track_Store::compile();
		}

		// The Connection tab holds the token and the base every Airtable list is read with, so its
		// Save reads them all again rather than leaving lists read with another token or base, kept
		// for up to a day, or a read that failed with them remembered for five minutes.
		if ( 'connection' === $scope ) {
			WPCPM_Settings_Choices::read_again();
		}

		// Back to where the form was drawn, a tab or a tool's own screen, with a notice naming what
		// was saved.
		self::flash_outcome( 'saved', $scope );

		wp_safe_redirect( self::return_url( $scope ) );
		exit;
	}

	/**
	 * The button on the Connection tab that reads the Airtable lists again: the bases, the statuses and
	 * the stages are kept a day and the tables and the columns fifteen minutes, so a status or a stage
	 * somebody has just added in Airtable could not be chosen until its list was read again, since the
	 * screen no longer takes it typed; and a read that failed is not asked again for five minutes,
	 * which the button asks again at once.
	 *
	 * The nonce first, then the right: the button is drawn only for somebody who may manage the
	 * program, on a screen that already asked.
	 */
	public function handle_refresh_lists() {
		check_admin_referer( self::REFRESH_ACTION );

		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		WPCPM_Settings_Choices::read_again();

		self::flash_outcome( 'lists-read' );

		wp_safe_redirect( self::settings_url( 'connection' ) );
		exit;
	}

	/**
	 * Where the Save of a scope goes back to: the tab its form is drawn on, or, for a tool's settings,
	 * the tool's own screen, whose Settings section is the form.
	 *
	 * A tool's scope names no tab: the address's `tab` is read through `sanitize_key()`, which would
	 * take the colon out of `tool:handbook`, and the screen has no tab for a tool. The scope of a tool
	 * that is not registered, whose screen is therefore not there, goes back to this screen.
	 *
	 * @param string $scope A key of `WPCPM_Settings::scopes()`.
	 * @return string
	 */
	private static function return_url( $scope ) {
		$tool = self::tool_of( $scope );

		if ( null !== $tool ) {
			return $tool->admin_url();
		}

		return 0 === strpos( (string) $scope, 'tool:' ) ? self::settings_url() : self::settings_url( $scope );
	}

	/**
	 * The registered tool a scope names.
	 *
	 * @param string $scope A key of `WPCPM_Settings::scopes()`.
	 * @return WPCPM_Tool|null Null for a tab's scope, and for a tool that is not registered.
	 */
	private static function tool_of( $scope ) {
		$scope = (string) $scope;

		if ( 0 !== strpos( $scope, 'tool:' ) || ! class_exists( 'WPCPM_Tools' ) ) {
			return null;
		}

		$tool = WPCPM_Tools::get( substr( $scope, strlen( 'tool:' ) ) );

		return $tool instanceof WPCPM_Tool ? $tool : null;
	}

	/**
	 * Why the last save left "Currently mentoring" as it was: the change would have taken live
	 * tracks' statuses out of it, and these are the tracks; everything else was saved (BUILDER-3 and
	 * the ruling in its fix round).
	 */
	private function render_refused_notice() {
		$refused = WPCPM_Flash::take( 'settings-refused' );

		if ( ! is_array( $refused ) || array() === $refused ) {
			return;
		}

		$tracks = array();

		foreach ( $refused as $status => $label ) {
			$tracks[] = sprintf(
				/* translators: 1: a track's name, 2: its Airtable status. */
				__( '%1$s runs on "%2$s"', 'wpcredits-program-manager' ),
				(string) $label,
				(string) $status
			);
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: the tracks, each with its Airtable status, separated by semicolons. */
					__( 'Everything else was saved. "Currently mentoring" was left as it was, because %s: taking a status out while its track is live would make the next students sync treat everybody on the track as having left the program. Take the track off the live site in the Track Builder first, then remove its status here.', 'wpcredits-program-manager' ),
					implode( '; ', $tracks )
				)
			)
		);
	}

	/**
	 * Why the last save left "Past students" as it was: the change would have put live tracks'
	 * statuses among the past statuses, which no track runs on, and these are the tracks; everything
	 * else was saved (`WPCPM_Settings::tracks_ended_by()`).
	 */
	private function render_past_refused_notice() {
		$refused = WPCPM_Flash::take( 'settings-past-refused' );

		if ( ! is_array( $refused ) || array() === $refused ) {
			return;
		}

		$tracks = array();

		foreach ( $refused as $status => $label ) {
			$tracks[] = sprintf(
				/* translators: 1: a track's name, 2: its Airtable status. */
				__( '%1$s runs on "%2$s"', 'wpcredits-program-manager' ),
				(string) $label,
				(string) $status
			);
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: the tracks, each with its Airtable status, separated by semicolons. */
					__( 'Everything else was saved. "Past students" was left as it was, because %s: no track runs on a past status, so saving the list would have taken the track off the live site, and its students would have lost its form. Take the track off the live site in the Track Builder first, then add its status here. The program\'s four original tracks always run, so their statuses are never past statuses.', 'wpcredits-program-manager' ),
					implode( '; ', $tracks )
				)
			)
		);
	}

	/**
	 * Queue the outcome a notice of this screen says, and the scope it names, always together: every
	 * writer of the `settings` channel queues through here, this screen's handlers and the Mail tab's
	 * sample invitations (`WPCPM_Mail::handle_test()`).
	 *
	 * The scope is set on every outcome, to nothing for an outcome that names none, a refusal, the
	 * lists read again or a sample sent: a scope still queued from a notice that was never drawn would
	 * otherwise name the next save.
	 *
	 * @param string $outcome A key of `settings_messages()`.
	 * @param string $scope   The scope saved: a key of `WPCPM_Settings::scopes()`, or ''.
	 */
	public static function flash_outcome( $outcome, $scope = '' ) {
		WPCPM_Flash::set( 'settings', $outcome );
		WPCPM_Flash::set( 'settings-scope', $scope );
	}

	/**
	 * The words for each outcome the `settings` flash carries: a notice type, then the sentence.
	 *
	 * A save names what it saved, the tab or the tool, because Save on one tab leaves every other
	 * tab as it was, and a bare "Settings saved." would read as though the whole screen had been.
	 * A tab reads "Sponsors settings saved.", a tool "Settings saved for the Need help? tool.",
	 * since a tool's name can end in a question mark. With no scope the sentence names nothing.
	 *
	 * @param string $scope The scope the save posted: a key of `WPCPM_Settings::scopes()`, or ''.
	 * @return array<string, string[]> Outcome => array( notice type, sentence ).
	 */
	public static function settings_messages( $scope = '' ) {
		$scope = (string) $scope;
		$label = self::scope_label( $scope );
		$saved = __( 'Settings saved.', 'wpcredits-program-manager' );

		if ( '' !== $label && 0 === strpos( $scope, 'tool:' ) ) {
			/* translators: %s: a tool's name, such as Mentor Status Checker. */
			$saved = sprintf( __( 'Settings saved for the %s tool.', 'wpcredits-program-manager' ), $label );
		} elseif ( '' !== $label ) {
			/* translators: %s: the name of a Settings tab, such as Sponsors. */
			$saved = sprintf( __( '%s settings saved.', 'wpcredits-program-manager' ), $label );
		}

		return array(
			'saved'       => array( 'success', $saved ),
			'refused'     => array( 'error', __( 'Nothing was saved: the form asked to save settings this screen does not have. Reload the page and save again.', 'wpcredits-program-manager' ) ),
			'test-sent'   => array( 'success', __( 'The sample invitation is on its way to your own address.', 'wpcredits-program-manager' ) ),
			'test-failed' => array( 'error', __( 'The sample could not be sent. Whatever handles mail on this site refused it, so a real invitation would not arrive either.', 'wpcredits-program-manager' ) ),
			'log-cleared' => array( 'success', __( 'The mail log is empty.', 'wpcredits-program-manager' ) ),
			'lists-read'  => array( 'success', __( 'The lists were read again.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * What a scope is called on screen: its tab's name, or its tool's.
	 *
	 * @param string $scope A key of `WPCPM_Settings::scopes()`.
	 * @return string The name, or '' for no scope and for a scope with no name on screen.
	 */
	private static function scope_label( $scope ) {
		$tabs = self::settings_tabs();

		if ( isset( $tabs[ $scope ] ) ) {
			return $tabs[ $scope ];
		}

		$tool = self::tool_of( $scope );

		return null === $tool ? '' : $tool->label();
	}

	/**
	 * The notice a settings save left for the screen it went back to, naming what it saved: this
	 * screen, or a tool's own, whose Settings section saves through the same handler
	 * (`WPCPM_Tool::render_settings()`); or the notice another outcome of this screen left, a
	 * refusal, the lists read again or a sample invitation. Read once, as every flash is.
	 */
	public static function render_outcome_notice() {
		$status   = (string) WPCPM_Flash::take( 'settings' );
		$scope    = WPCPM_Flash::take( 'settings-scope' );
		$messages = self::settings_messages( is_string( $scope ) ? $scope : '' );

		if ( isset( $messages[ $status ] ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $messages[ $status ][0] ),
				esc_html( $messages[ $status ][1] )
			);
		}
	}

	/**
	 * The settings screen: its notices, the tab bar, and the tab being shown.
	 *
	 * One tab at a time, each tab that saves one form posting its own name, so a save writes that
	 * tab's settings and no other's (`WPCPM_Settings::save()`), and a box another tab draws cannot
	 * be switched off by a form that never drew it.
	 */
	public function render_settings() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$settings = WPCPM_Settings::get();
		$tab      = self::current_tab();

		// Titled as the menu titles the page, under the plugin's own entry.
		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html__( 'Settings', 'wpcredits-program-manager' ) . '</h1>';

		self::render_outcome_notice();
		$this->render_refused_notice();
		$this->render_past_refused_notice();

		$this->render_tab_bar( $tab );

		if ( 'mail' === $tab ) {
			// No form around it: each of its controls is a form posting to its own handler, and a
			// form cannot hold another.
			$this->render_mail_card();
		} else {
			$this->render_tab_form( $tab, $settings );
		}

		echo '</div>';
	}

	/**
	 * The Connection tab's button that reads the Airtable lists again, a form of its own after the
	 * tab's, since a form cannot hold another (`handle_refresh_lists()`).
	 */
	private function render_refresh_lists() {
		printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::REFRESH_ACTION ) );
		wp_nonce_field( self::REFRESH_ACTION );
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'The bases, statuses and stages are read once a day, the tables and columns every fifteen minutes, and all of them again after a Save of this tab.', 'wpcredits-program-manager' )
		);
		submit_button( __( 'Read the lists from Airtable again', 'wpcredits-program-manager' ), 'secondary', 'submit', true, array( 'id' => 'wpcpm-refresh-lists' ) );
		echo '</form>';
	}

	/**
	 * The method that draws each tab's sections, by the tab's slug: every tab of `settings_tabs()` but
	 * Mail, which saves nothing and is drawn on its own.
	 *
	 * @return array<string, string> Slug => the method.
	 */
	private static function tab_sections() {
		return array(
			'connection'   => 'render_tab_connection',
			'people'       => 'render_tab_people',
			'institutions' => 'render_institution_settings',
			'sponsors'     => 'render_sponsor_settings',
			'security'     => 'render_two_factor_settings',
			'advanced'     => 'render_tab_advanced',
		);
	}

	/**
	 * A tab that saves: its form, its sections and its Save, and after the Connection tab's form the
	 * button that reads the Airtable lists again.
	 *
	 * A tab with no sections to draw, which only a tab added to `settings_tabs()` and to
	 * `WPCPM_Settings::scopes()` without its sections here could be, draws no form and says so: a form
	 * holding nothing but a Save would post the tab's scope with none of its switches, and the save
	 * reads a switch it does not receive as off; and another tab's sections drawn in its place would
	 * post that tab's settings under the wrong scope.
	 *
	 * @param string $tab      A key of `settings_tabs()` that is also a scope of `WPCPM_Settings::scopes()`.
	 * @param array  $settings Current settings.
	 */
	private function render_tab_form( $tab, array $settings ) {
		$sections = self::tab_sections();

		if ( ! isset( $sections[ $tab ] ) ) {
			printf(
				'<div class="notice notice-error inline"><p>%s</p></div>',
				esc_html__( 'This tab has nothing to show yet, so it has nothing to save.', 'wpcredits-program-manager' )
			);

			return;
		}

		$draw = $sections[ $tab ];

		self::open_settings_form( $tab );
		$this->$draw( $settings );
		$this->close_settings_form();

		if ( 'connection' === $tab ) {
			$this->render_refresh_lists();
		}
	}

	/**
	 * The tab bar, in WordPress's own markup, with the tab shown marked for the eye and for a screen
	 * reader.
	 *
	 * Core's newer bars' markup: a navigation region named for a screen reader, holding plain links,
	 * where the older bars wrapped the links in a heading, which made the seven tabs one heading to
	 * anybody moving through the screen by its headings. The class is the one core styles.
	 *
	 * @param string $current The tab shown: a key of `settings_tabs()`.
	 */
	private function render_tab_bar( $current ) {
		printf( '<nav class="nav-tab-wrapper wp-clearfix" aria-label="%s">', esc_attr__( 'Secondary menu', 'wpcredits-program-manager' ) );

		foreach ( self::settings_tabs() as $slug => $label ) {
			printf(
				'<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>',
				esc_url( self::settings_url( $slug ) ),
				$slug === $current ? ' nav-tab-active' : '',
				$slug === $current ? ' aria-current="page"' : '',
				esc_html( $label )
			);
		}

		echo '</nav>';
	}

	/**
	 * Open a settings form: the nonce, under its own name, which is also how the save handler tells
	 * that a settings form was posted, and the scope the form posts. Every settings form opens here,
	 * a tab's and the Settings section a tool draws on its own screen (`WPCPM_Tool::render_settings()`),
	 * so what the handler needs from a form is written once.
	 *
	 * @param string $scope A key of `WPCPM_Settings::scopes()`: a tab's slug, or a tool's scope.
	 */
	public static function open_settings_form( $scope ) {
		echo '<form method="post" action="">';
		wp_nonce_field( self::SETTINGS_NONCE, self::SETTINGS_NONCE );
		printf(
			'<input type="hidden" name="%1$s" value="%2$s" />',
			esc_attr( self::SETTINGS_TAB_FIELD ),
			esc_attr( $scope )
		);
	}

	/**
	 * Close a tab's form under its Save button.
	 */
	private function close_settings_form() {
		submit_button( __( 'Save settings', 'wpcredits-program-manager' ) );
		echo '</form>';
	}

	/**
	 * Open one section of a tab: its heading, its intro, and the table its rows go in.
	 *
	 * @param string $title  The heading.
	 * @param string $intro  One sentence saying what the section is for.
	 * @param string $anchor The section's part of the program manager guide (`section_heading()`).
	 */
	private function open_section( $title, $intro, $anchor ) {
		$this->section_heading( $title, $intro, $anchor );
		echo '<table class="form-table" role="presentation"><tbody>';
	}

	/**
	 * Close a section opened by `open_section()`.
	 */
	private function close_section() {
		echo '</tbody></table>';
	}

	/**
	 * A section's heading and its intro: one sentence, then a link to the section's part of the
	 * program manager guide, where what the sentence leaves out is written down, or the sentence
	 * alone when the site publishes no guide to link (`settings_guide()`). The link says Program
	 * manager guide, and to a screen reader which section it opens the guide at, and that it opens a
	 * new tab.
	 *
	 * @param string $title  The heading.
	 * @param string $intro  One sentence saying what the section is for.
	 * @param string $anchor The ID bin/build-docs.php gives the section's heading in
	 *                       docs/sections/31-admin-settings.md: bin/test-settings.php holds each to its
	 *                       section's ID in that part of the built guide.
	 */
	private function section_heading( $title, $intro, $anchor ) {
		printf( '<h2>%s</h2>', esc_html( $title ) );

		$guide = self::settings_guide();

		if ( null === $guide ) {
			printf( '<p class="description wpcpm-settings__intro">%s</p>', esc_html( $intro ) );

			return;
		}

		printf(
			'<p class="description wpcpm-settings__intro">%1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s%4$s</a></p>',
			esc_html( $intro ),
			esc_url( $guide['url'] . '#' . $anchor ),
			esc_html( $guide['label'] ),
			WPCPM_Settings_Rows::screen_reader_about( $title, __( '(opens in a new tab)', 'wpcredits-program-manager' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in screen_reader_about().
		);
	}

	/**
	 * The program manager guide as this site publishes it: the page at `GUIDE_PAGE_PATH`, which holds
	 * the guide bin/build-docs.php builds, with an ID on every heading, so each section's intro opens
	 * the guide at its own part.
	 *
	 * Not the address the Resources section links the program manager guide at
	 * (`WPCPM_Handbook_Assistant::guides()`): that is the program's handbook page, which has none of
	 * these parts, and changing it would move the Administrator Dashboard's Resources button too.
	 * Published, not merely there, as the dashboards' own pages are: a draft or a trashed page is not
	 * the guide. A site that publishes the guide at another address says which through the
	 * `wpcpm_settings_guide_url` filter.
	 *
	 * @return array|null The guide's `url`, without a fragment, and its `label`; null when there is no
	 *                    guide to link, and each intro says its sentence alone.
	 */
	private static function settings_guide() {
		$page = get_page_by_path( self::GUIDE_PAGE_PATH );
		$page = $page instanceof WP_Post ? $page : null;
		$url  = null !== $page && 'publish' === $page->post_status ? (string) get_permalink( $page ) : '';

		/**
		 * Filter the address the settings screen's section intros link the program manager guide at.
		 *
		 * Each intro adds its section's anchor, an ID bin/build-docs.php gives a heading of
		 * docs/sections/31-admin-settings.md, so the address has to hold that guide as built. A
		 * fragment on it is dropped for the section's own.
		 *
		 * @param string       $url  The address of the page published at the path
		 *                           `program-manager-guide`, or '' when there is none, which leaves
		 *                           the intros without a link.
		 * @param WP_Post|null $page The page at that path, published or not, or null.
		 */
		$url = preg_replace( '/#.*$/', '', (string) apply_filters( 'wpcpm_settings_guide_url', $url, $page ) );

		if ( '' === $url ) {
			return null;
		}

		return array(
			'url'   => $url,
			'label' => __( 'Program manager guide', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The Connection tab: where the three tools keep their settings, how the plugin reaches Airtable,
	 * and which table of the base holds what.
	 *
	 * The tutors, feedback, countries and sponsors tables and the name columns of the last two are
	 * drawn here for the first time: they had no control, and a change to the base meant a change
	 * to code.
	 *
	 * @param array $settings Current settings.
	 */
	private function render_tab_connection( array $settings ) {
		$this->render_tools_lede();

		$this->open_section(
			__( 'Airtable', 'wpcredits-program-manager' ),
			__( 'How the plugin reaches the program\'s Airtable base: the token it reads and writes with, the token that creates columns, and the base.', 'wpcredits-program-manager' ),
			'airtable'
		);

		WPCPM_Settings_Rows::text_row(
			'api_token',
			__( 'Personal Access Token', 'wpcredits-program-manager' ),
			WPCPM_Settings::masked_token(),
			__( 'Stored in the database and never sent to the browser - leave blank to keep the current token.', 'wpcredits-program-manager' ),
			'password'
		);

		WPCPM_Settings_Rows::text_row(
			'schema_token',
			__( 'Schema token', 'wpcredits-program-manager' ),
			WPCPM_Settings::masked_schema_token(),
			__( 'Optional, and used by the Track Builder alone: the token that creates a track\'s columns when it is published.', 'wpcredits-program-manager' ),
			'password',
			'',
			'',
			__( 'It needs the "schema.bases:write" scope and must belong to somebody with the base creator role on the base. Leave blank to keep the current one, or type remove to take it away. Without it, publishing lists the columns for somebody to create by hand.', 'wpcredits-program-manager' )
		);

		$this->render_scopes_row();

		WPCPM_Settings_Rows::select_row( 'base_id', __( 'Base ID', 'wpcredits-program-manager' ), $settings['base_id'], WPCPM_Settings_Choices::named_ids( WPCPM_Settings_Choices::bases() ), '', WPCPM_Settings_Rows::typed( 'id', WPCPM_Settings_Choices::why_empty( 'bases' ) ) );

		$this->close_section();

		$this->open_section(
			__( 'Tables', 'wpcredits-program-manager' ),
			__( 'Which table of the base holds each part of the program, by its ID, and which column holds the names of a linked table\'s rows.', 'wpcredits-program-manager' ),
			'tables'
		);

		// Each table is chosen from the base's own, its ID beside its name, and each name column from
		// its own table's columns: the values posted are the IDs and names that were typed here before.
		$tables = WPCPM_Settings_Choices::named_ids( WPCPM_Settings_Choices::tables() );
		$by_id  = WPCPM_Settings_Rows::typed( 'id', WPCPM_Settings_Choices::why_empty( 'tables' ) );

		WPCPM_Settings_Rows::select_row( 'mentors_table', __( 'Mentors table', 'wpcredits-program-manager' ), $settings['mentors_table'], $tables, '', $by_id );
		WPCPM_Settings_Rows::select_row( 'reports_table', __( 'Students Reports table', 'wpcredits-program-manager' ), $settings['reports_table'], $tables, __( 'Holds the internship dates, links and contribution team shown on the mentor page.', 'wpcredits-program-manager' ), $by_id );
		WPCPM_Settings_Rows::select_row( 'students_table', __( 'Students table', 'wpcredits-program-manager' ), $settings['students_table'], $tables, __( 'Read only for the Tutor column, which does not exist on Students Reports.', 'wpcredits-program-manager' ), $by_id );
		WPCPM_Settings_Rows::select_row( 'institutions_table', __( 'Institutions table', 'wpcredits-program-manager' ), $settings['institutions_table'], $tables, '', $by_id );
		WPCPM_Settings_Rows::select_row(
			'institutions_name_field',
			__( 'Institutions name column', 'wpcredits-program-manager' ),
			$settings['institutions_name_field'],
			self::name_columns( $settings['institutions_table'] ),
			__( 'Only used when the token lacks <code>schema.bases:read</code>: with that scope the primary column is detected automatically.', 'wpcredits-program-manager' ),
			WPCPM_Settings_Rows::typed( 'column', WPCPM_Settings_Choices::why_empty( 'columns', $settings['institutions_table'] ) )
		);
		WPCPM_Settings_Rows::select_row( 'teams_table', __( 'Contribution areas table', 'wpcredits-program-manager' ), $settings['teams_table'], $tables, '', $by_id );
		WPCPM_Settings_Rows::select_row( 'teams_name_field', __( 'Contribution areas name column', 'wpcredits-program-manager' ), $settings['teams_name_field'], WPCPM_Settings_Choices::columns( $settings['teams_table'] ), '', WPCPM_Settings_Rows::typed( 'column', WPCPM_Settings_Choices::why_empty( 'columns', $settings['teams_table'] ) ) );
		WPCPM_Settings_Rows::select_row( 'tutors_table', __( 'Tutors table', 'wpcredits-program-manager' ), $settings['tutors_table'], $tables, __( 'Read when an institution enrolls students, for that institution\'s own tutors.', 'wpcredits-program-manager' ), $by_id );
		WPCPM_Settings_Rows::select_row( 'feedback_table', __( 'Feedback table', 'wpcredits-program-manager' ), $settings['feedback_table'], $tables, __( 'Where the students\' survey answers are written: one row per student, with a column per question.', 'wpcredits-program-manager' ), $by_id );
		WPCPM_Settings_Rows::select_row( 'countries_table', __( 'Countries table', 'wpcredits-program-manager' ), $settings['countries_table'], $tables, __( 'Read for the name of each institution\'s country and the program manager who looks after it.', 'wpcredits-program-manager' ), $by_id );
		WPCPM_Settings_Rows::select_row( 'countries_name_field', __( 'Countries name column', 'wpcredits-program-manager' ), $settings['countries_name_field'], self::name_columns( $settings['countries_table'] ), __( 'The column of the Countries table that holds each country\'s name.', 'wpcredits-program-manager' ), WPCPM_Settings_Rows::typed( 'column', WPCPM_Settings_Choices::why_empty( 'columns', $settings['countries_table'] ) ) );
		WPCPM_Settings_Rows::select_row( 'team_members_table', __( 'Team Members table', 'wpcredits-program-manager' ), $settings['team_members_table'], $tables, __( 'Read by the sponsors sync for each sponsor\'s program contact, and not the Contribution areas table above.', 'wpcredits-program-manager' ), $by_id );
		WPCPM_Settings_Rows::select_row( 'sponsors_table', __( 'Sponsors table', 'wpcredits-program-manager' ), $settings['sponsors_table'], $tables, __( 'Read by the sponsors sync, and written to whenever a sponsor\'s record changes on this site, from an approved application to a new logo.', 'wpcredits-program-manager' ), $by_id );
		WPCPM_Settings_Rows::select_row( 'sponsors_name_field', __( 'Sponsors name column', 'wpcredits-program-manager' ), $settings['sponsors_name_field'], self::name_columns( $settings['sponsors_table'] ), __( 'The column of the Sponsors table that holds each company\'s name, read by the sponsors sync and by the mentors sync.', 'wpcredits-program-manager' ), WPCPM_Settings_Rows::typed( 'column', WPCPM_Settings_Choices::why_empty( 'columns', $settings['sponsors_table'] ) ) );

		$this->close_section();
	}

	/**
	 * The choices of a name column whose table is chosen from the base's list, which stores the
	 * table's ID: None first, which saves the setting blank, then the table's columns. A blank is read
	 * as a column of the reader's own, the institutions sync's Name, the countries' Name and the
	 * sponsors sync's Company Name, and the mentors sync, which reads the Institutions and Sponsors
	 * tables' names too, reads the table's primary column, which it knows by the table's ID.
	 *
	 * A table stored by its name, as a typed one could be before the tables were chosen from a list,
	 * has no primary column the mentors sync can find: it asks for the name column itself, and a
	 * blank one would ask Airtable for a column with no name. So such a table's name column is the
	 * text input it was, as it is when no columns can be read, where a blank is typed as it always was.
	 *
	 * @param string $table The table setting's value: the table's ID or its name.
	 * @return array<string, string> The value it saves => what the option says; none, for the text
	 *                               input, when the table is not stored by one of the base's table IDs
	 *                               or its columns cannot be read.
	 */
	private static function name_columns( $table ) {
		$columns = WPCPM_Settings_Choices::columns( $table );

		if ( array() === $columns || ! isset( WPCPM_Settings_Choices::tables()[ (string) $table ] ) ) {
			return array();
		}

		return array( '' => __( 'None (the built-in column)', 'wpcredits-program-manager' ) ) + $columns;
	}

	/**
	 * The Connection tab's first line: the three tools that keep settings of their own keep them on
	 * their own screens, each named with a link to its Settings section there, for the manager who
	 * remembers them on this screen and opens it, on this tab, looking for them.
	 *
	 * Drawn only while the registry holds all three: a tool a site's `wpcpm_tools` filter took away
	 * has no screen to send anybody to, and the sentence names three.
	 */
	private function render_tools_lede() {
		$links = array();

		foreach ( self::TOOLS_WITH_SETTINGS as $id ) {
			$tool = self::tool_of( 'tool:' . $id );

			if ( null === $tool ) {
				return;
			}

			$links[] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $tool->admin_url() . '#settings' ), esc_html( $tool->label() ) );
		}

		$sentence = sprintf(
			/* translators: 1: Mentor Status Checker, 2: Student Duplicate Finder, 3: Need help?, each a tool's name as a link to the Settings section of its screen. */
			esc_html__( 'The %1$s, the %2$s and %3$s keep their settings on their own screens, under WPCredits Program > Tools.', 'wpcredits-program-manager' ),
			$links[0],
			$links[1],
			$links[2]
		);

		printf( '<p class="wpcpm-lede">%s</p>', $sentence ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The sentence escaped above, and each link built from escaped parts.
	}

	/**
	 * The Students and mentors tab: who is on the program, what happens when somebody leaves it,
	 * the accounts, and where each lands after logging in.
	 *
	 * The status lists are shared by both audiences: a current student is anyone a mentor is
	 * currently mentoring, so the same two lists decide who is a current student and who has
	 * finished.
	 *
	 * @param array $settings Current settings.
	 */
	private function render_tab_people( array $settings ) {
		// Both lists in the form they are kept in, which is the form the boxes post, so a status the
		// list keeps sanitized is found again among the boxes.
		$current = WPCPM_Settings::clean_list( (array) $settings['student_statuses'] );
		$past    = WPCPM_Settings::clean_list( (array) $settings['past_statuses'] );
		$choices = WPCPM_Settings_Choices::status_lists( $settings );

		// The list as this page draws it, one hidden field a status, so the save can tell a list
		// somebody changed from one the page only carried back, and keeps whatever the stored list
		// gains meanwhile, a published track's status above all (BUILDER-3). On the form, not in the
		// row, which is a textarea or a checkbox list depending on whether Airtable answers.
		foreach ( (array) $settings['student_statuses'] as $drawn ) {
			printf(
				'<input type="hidden" name="%1$s[]" value="%2$s" />',
				esc_attr( WPCPM_Settings::FIELD_DRAWN_STATUSES ),
				esc_attr( (string) $drawn )
			);
		}

		// A box is locked where the save would refuse its change, judged as each refusal judges it: a
		// live track's status Currently mentoring holds cannot leave it, the status taken in the form
		// the list keeps it in (`WPCPM_Settings::tracks_dropped_by()`), and one Past students lacks
		// cannot join it, folded as the track rules fold it (`WPCPM_Settings::tracks_ended_by()`).
		// Both refusals still hold the save. A Currently mentoring box names the track it is kept
		// for; in Past students every live track's box is locked for the one reason, which its group
		// says once, under its name, rather than each box beside itself. A Past students box the list
		// holds ticked is never locked: taking a status out of it is refused nothing, and the fold
		// cannot see every spelling (a "<" the list keeps as `&lt;` is not the track's "<"), so a
		// track can stay live beside its status ticked there, which must still come out.
		$current_locked = array();
		$past_locked    = array();

		foreach ( WPCPM_Settings_Choices::tracks_statuses() as $status => $track ) {
			$listed = WPCPM_Settings::clean_list( array( $status ) );

			if ( array() === $listed ) {
				continue;
			}

			$listed = $listed[0];
			$ended  = class_exists( 'WPCPM_Track_Definition' ) ? WPCPM_Track_Definition::is_refused_status( (string) $status, $past ) : in_array( $listed, $past, true );

			if ( in_array( $listed, $current, true ) ) {
				/* translators: %s: a track's name. */
				$current_locked[ $listed ] = sprintf( __( 'Kept while %s is live in the Track Builder.', 'wpcredits-program-manager' ), $track );
			}

			if ( ! $ended && ! in_array( $listed, $past, true ) ) {
				$past_locked[ $listed ] = '';
			}
		}

		// The note goes with the group the locked boxes are drawn in, whatever the group is called.
		$past_notes = array();

		foreach ( $choices as $group => $options ) {
			if ( is_array( $options ) && array() !== array_intersect_key( $options, $past_locked ) ) {
				$past_notes[ $group ] = __( 'A live track\'s status cannot be a past status.', 'wpcredits-program-manager' );
			}
		}

		$statuses_why = WPCPM_Settings_Rows::typed( 'statuses', WPCPM_Settings_Choices::why_empty( 'report_statuses', $settings ) );

		$this->open_section(
			__( 'Who is on the program', 'wpcredits-program-manager' ),
			__( 'The Airtable statuses that make a student current or past, and the one that gives a mentor an account.', 'wpcredits-program-manager' ),
			'who-is-on-the-program'
		);

		WPCPM_Settings_Rows::checklist_row(
			'student_statuses',
			__( 'Currently mentoring', 'wpcredits-program-manager' ),
			$current,
			$choices,
			__( 'Students holding any of these appear under "Currently mentoring" on their mentor\'s page.', 'wpcredits-program-manager' ),
			$current_locked,
			$statuses_why
		);

		WPCPM_Settings_Rows::checklist_row(
			'past_statuses',
			__( 'Past students', 'wpcredits-program-manager' ),
			$past,
			$choices,
			__( 'Statuses that mean mentoring has finished: these students appear in a separate, collapsed section.', 'wpcredits-program-manager' ),
			$past_locked,
			$statuses_why,
			array(),
			__( 'With none, only current students are shown. A status in both lists counts as current.', 'wpcredits-program-manager' ),
			$past_notes
		);

		WPCPM_Settings_Rows::select_row( 'mentor_status', __( 'Mentor status to sync', 'wpcredits-program-manager' ), $settings['mentor_status'], WPCPM_Settings_Choices::mentor_statuses( $settings ), __( 'Only mentors holding this Airtable status get an account.', 'wpcredits-program-manager' ), WPCPM_Settings_Rows::typed( 'status', WPCPM_Settings_Choices::why_empty( 'mentor_statuses', $settings ) ) );

		$this->close_section();

		$this->open_section(
			__( 'When someone leaves', 'wpcredits-program-manager' ),
			__( 'What the sync does with the account of a mentor or a student who is no longer on the program.', 'wpcredits-program-manager' ),
			'when-someone-leaves'
		);

		// Each rule's answers are a group a screen reader names by the question it answers, as core's
		// forms name a group of radios: here the row's own label.
		printf(
			'<tr><th scope="row">%1$s</th><td><fieldset><legend class="screen-reader-text"><span>%1$s</span></legend><label><input type="radio" name="on_inactive" value="revoke"%2$s> %3$s</label><br><label><input type="radio" name="on_inactive" value="keep"%4$s> %5$s</label></fieldset><p class="description">%6$s</p></td></tr>',
			esc_html__( 'When a mentor is no longer active', 'wpcredits-program-manager' ),
			checked( $settings['on_inactive'], 'revoke', false ),
			esc_html__( 'Remove the Mentor role and clear their student list', 'wpcredits-program-manager' ),
			checked( $settings['on_inactive'], 'keep', false ),
			esc_html__( 'Leave the role in place', 'wpcredits-program-manager' ),
			esc_html__( 'The account itself is never deleted either way.', 'wpcredits-program-manager' )
		);

		printf(
			'<tr><th scope="row">%1$s</th><td><fieldset><legend class="screen-reader-text"><span>%1$s</span></legend><label><input type="radio" name="student_on_inactive" value="revoke"%2$s> %3$s</label><br><label><input type="radio" name="student_on_inactive" value="keep"%4$s> %5$s</label></fieldset><p class="description">%6$s</p></td></tr>',
			esc_html__( 'When a student leaves the program', 'wpcredits-program-manager' ),
			checked( $settings['student_on_inactive'], 'revoke', false ),
			esc_html__( 'Remove the Student role, so they lose access to Student-level content', 'wpcredits-program-manager' ),
			checked( $settings['student_on_inactive'], 'keep', false ),
			esc_html__( 'Leave the role in place', 'wpcredits-program-manager' ),
			esc_html__( 'The account itself is never deleted either way, and their program details are kept.', 'wpcredits-program-manager' )
		);

		$this->close_section();

		$this->open_section(
			__( 'Accounts', 'wpcredits-program-manager' ),
			__( 'Whether a new account is emailed its invitation, and whether Airtable is read on a schedule.', 'wpcredits-program-manager' ),
			'accounts'
		);

		$invitations = __( 'Invitation emails', 'wpcredits-program-manager' );

		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="send_welcome_email" value="1"%2$s> %3$s</label><p class="description">%4$s</p>',
			esc_html( $invitations ),
			checked( ! empty( $settings['send_welcome_email'] ), true, false ),
			esc_html__( 'Email each new mentor and student a password-reset link as their account is created', 'wpcredits-program-manager' ),
			esc_html__( 'Off by default: a first sync creates around ninety accounts at once, so leave this off unless you mean to email all of them.', 'wpcredits-program-manager' )
		);
		WPCPM_Settings_Rows::close_row( $invitations, __( 'Invitations are queued and sent a few at a time rather than all inside the sync, so a mail limit cannot swallow half of them unnoticed. You can also invite people one at a time from the Mentors and Students screens, or tick several in the Students screen\'s list and invite them together.', 'wpcredits-program-manager' ) );

		// Read by a person on the settings screen, so it says the cadence outright and had to move
		// with it: the mentors run left the daily clock in 1.98.2 and joined the students run's, half
		// an hour behind it. It names the three syncs the switch governs, since a manager cannot tell
		// which from the label, and the sponsors sync, which runs regardless.
		//
		// The WordPress.org profile reads are the *students* run's, phase 2, where a mentor's card is
		// built (`WPCPM_Students_Sync`); the mentors run makes no WordPress.org request at all, it
		// reads three Airtable tables. The fold says which run is the expensive one and why.
		$auto_sync = __( 'Automatic sync', 'wpcredits-program-manager' );

		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="auto_sync" value="1"%2$s> %3$s</label><p class="description">%4$s</p>',
			esc_html( $auto_sync ),
			checked( ! empty( $settings['auto_sync'] ), true, false ),
			esc_html__( 'Read Airtable on a schedule', 'wpcredits-program-manager' ),
			esc_html__( 'Runs the students, mentors and institutions syncs every three hours, the mentors half an hour after the students; the sponsors sync runs regardless.', 'wpcredits-program-manager' )
		);
		WPCPM_Settings_Rows::close_row( $auto_sync, __( 'The student rows carry what people are shown on their cards, and the students run is the expensive one, reading a WordPress.org profile per mentor, cached for twelve hours; the mentors run reads three Airtable tables and no WordPress.org profile. A run already in progress is left to finish rather than restarted. The students and mentors runs can also be run by hand from the Students and Mentors screens.', 'wpcredits-program-manager' ) );

		$this->close_section();

		$this->open_section(
			__( 'Landing pages', 'wpcredits-program-manager' ),
			__( 'Where mentors and students go when they log in.', 'wpcredits-program-manager' ),
			'landing-pages'
		);

		WPCPM_Settings_Rows::landing_row(
			'mentor_home',
			__( 'Mentor landing page', 'wpcredits-program-manager' ),
			! empty( $settings['mentor_home'] ),
			__( 'Use the Mentor Report Card page as the mentor dashboard', 'wpcredits-program-manager' ),
			__( 'Mentors go there when they log in and in place of the wp-admin Dashboard, and get a "Mentor Report Card" link in the toolbar.', 'wpcredits-program-manager' ),
			(string) WPCPM_Mentors_Dashboard::page_url(),
			__( 'They keep access to their own profile screen, and a mentor who followed a link to somewhere specific still lands there instead. Administrators are unaffected.', 'wpcredits-program-manager' )
		);

		WPCPM_Settings_Rows::landing_row(
			'student_home',
			__( 'Student landing page', 'wpcredits-program-manager' ),
			! empty( $settings['student_home'] ),
			__( 'Use the Student Report Card page as the student dashboard', 'wpcredits-program-manager' ),
			__( 'Students go there when they log in and in place of the wp-admin Dashboard, and get a "Student Report Card" link in the toolbar.', 'wpcredits-program-manager' ),
			(string) WPCPM_Students_Dashboard::page_url(),
			__( 'Same exceptions as for mentors: a requested destination wins, their own profile screen stays reachable, and anyone who can write posts is left alone.', 'wpcredits-program-manager' )
		);

		$this->close_section();
	}

	/**
	 * The Institutions tab.
	 *
	 * A tab of its own, and not rows under Students. The application form was once the one
	 * institution setting with a control, drawn at the foot of the Students card, and it read as a
	 * student setting: the heading above it said Students module, and a program manager scanning for
	 * what institutions can do had no reason to look under it.
	 *
	 * Four more had existed since Phase 0 with no control at all, which meant the only way to change
	 * any of them was code. They are here for the same reason: a setting nobody can find is a setting
	 * nobody can use.
	 *
	 * @param array $settings Current settings.
	 */
	private function render_institution_settings( array $settings ) {
		$this->open_section(
			__( 'Applications and enrollment', 'wpcredits-program-manager' ),
			__( 'How schools join the program and put their students on it: the public application form, enrollment lists, and accounts the sync creates.', 'wpcredits-program-manager' ),
			'institution-applications-and-enrollment'
		);

		// The public application form. Off by default and switched on here, because turning it
		// on publishes a page that strangers can post to, which is not a thing to inherit from
		// an update. The privacy policy is named on the same row rather than in a document
		// nobody will read at the moment they need it: the form refuses to render at all
		// without one, so a switch that looks on while the page shows nothing is exactly the
		// confusion this line exists to prevent.
		$policy_url = function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '';
		$apply_url  = class_exists( 'WPCPM_Institution_Application' ) ? (string) WPCPM_Institution_Application::page_url() : '';

		$applications = __( 'Applications from institutions', 'wpcredits-program-manager' );

		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="applications_enabled" value="1"%2$s> %3$s</label><p class="description">%4$s</p>%5$s',
			esc_html( $applications ),
			checked( ! empty( $settings['applications_enabled'] ), true, false ),
			esc_html__( 'Take applications through the form on this site', 'wpcredits-program-manager' ),
			esc_html__( 'A public page anybody can post to.', 'wpcredits-program-manager' ),
			'' === $policy_url
				? '<p class="description wpcpm-warning">' . esc_html__( 'No privacy policy page is set, so the form shows nothing to the public however this is switched. Publish one and choose it under Settings > Privacy.', 'wpcredits-program-manager' ) . '</p>'
				: ''
		);
		WPCPM_Settings_Rows::page_line( $apply_url );
		WPCPM_Settings_Rows::close_row( $applications, __( 'Every submission is stored for a program manager to read on the Institutions screen, and nothing is created until somebody approves it. While this is off the page shows one sentence saying applications are closed.', 'wpcredits-program-manager' ) );

		// Beside the applications switch, because the two are the same kind of decision: both
		// open a route by which people outside the program put names into it, and a site that
		// wants one may well not want the other.
		$enrollment = __( 'Enrollment lists from institutions', 'wpcredits-program-manager' );

		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="import_enabled" value="1"%2$s> %3$s</label><p class="description">%4$s</p>',
			esc_html( $enrollment ),
			checked( ! empty( $settings['import_enabled'] ), true, false ),
			esc_html__( 'Let an institution send a list of students to enroll', 'wpcredits-program-manager' ),
			esc_html__( 'Adds an "Enroll students" section to the Institution Dashboard, where a school chooses the program and the term and then adds one student or sends a CSV.', 'wpcredits-program-manager' )
		);
		WPCPM_Settings_Rows::close_row(
			$enrollment,
			array(
				__( 'The list is read and checked against the program records, and the school sees what was understood before anything is created. While this is off the section does not appear at all.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: checks per hour, 2: rows per day. */
					__( 'Ceilings per institution: %1$s checks an hour and %2$s students a day. Files are read and never stored.', 'wpcredits-program-manager' ),
					class_exists( 'WPCPM_Institution_Import' ) ? number_format_i18n( WPCPM_Institution_Import::CHECKS_PER_HOUR ) : '',
					class_exists( 'WPCPM_Institution_Import' ) ? number_format_i18n( WPCPM_Institution_Import::ROWS_PER_DAY ) : ''
				),
			)
		);

		$provision = __( 'Create accounts automatically', 'wpcredits-program-manager' );

		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="institution_provision" value="1"%2$s> %3$s</label><p class="description">%4$s</p>',
			esc_html( $provision ),
			checked( ! empty( $settings['institution_provision'] ), true, false ),
			esc_html__( 'Let the sync create the first account for a Confirmed institution', 'wpcredits-program-manager' ),
			esc_html__( 'From the Contact Email Airtable holds, and only for a Confirmed institution whose agreement is recorded and that has never had a member.', 'wpcredits-program-manager' )
		);
		WPCPM_Settings_Rows::close_row( $provision, __( 'An address that already belongs to an account is left alone and named on the Institutions screen. With this off, accounts are created only when somebody presses the button there.', 'wpcredits-program-manager' ) );

		$this->close_section();

		$this->open_section(
			__( 'Landing page', 'wpcredits-program-manager' ),
			__( 'Where institution accounts go when they log in.', 'wpcredits-program-manager' ),
			'institution-landing-page'
		);

		WPCPM_Settings_Rows::landing_row(
			'institution_home',
			__( 'Institution landing page', 'wpcredits-program-manager' ),
			! empty( $settings['institution_home'] ),
			__( 'Use the Institution Dashboard page as the institution dashboard', 'wpcredits-program-manager' ),
			__( 'Institution accounts go there when they log in and in place of the wp-admin Dashboard, and get an "Institution Dashboard" link in the toolbar.', 'wpcredits-program-manager' ),
			class_exists( 'WPCPM_Institutions_Dashboard' ) ? (string) WPCPM_Institutions_Dashboard::page_url() : '',
			__( 'Same exceptions as for mentors and students.', 'wpcredits-program-manager' )
		);

		$this->close_section();

		$this->open_section(
			__( 'When an institution leaves the pipeline', 'wpcredits-program-manager' ),
			__( 'What the sync does with the people of an institution that Airtable moves out of the pipeline.', 'wpcredits-program-manager' ),
			'when-an-institution-leaves-the-pipeline'
		);

		// The section's heading says when; the row is what it decides, so the two do not repeat. The
		// answers are a group a screen reader names by the question the section asks, which gives
		// "Its people" its referent there, where the heading above is out of hearing.
		$its_people = __( 'Its people', 'wpcredits-program-manager' );

		printf(
			'<tr><th scope="row">%1$s</th><td><fieldset><legend class="screen-reader-text"><span>%2$s</span></legend><label><input type="radio" name="institution_on_inactive" value="revoke"%3$s> %4$s</label><br><label><input type="radio" name="institution_on_inactive" value="keep"%5$s> %6$s</label></fieldset><p class="description">%7$s</p>',
			esc_html( $its_people ),
			esc_html__( 'When an institution leaves the pipeline', 'wpcredits-program-manager' ),
			checked( $settings['institution_on_inactive'], 'revoke', false ),
			esc_html__( 'Remove its people, so they lose access to its students', 'wpcredits-program-manager' ),
			checked( $settings['institution_on_inactive'], 'keep', false ),
			esc_html__( 'Leave their access in place', 'wpcredits-program-manager' ),
			esc_html__( 'The accounts themselves are never deleted either way, and the agreement and the roster are kept.', 'wpcredits-program-manager' )
		);
		WPCPM_Settings_Rows::close_row( $its_people, __( 'An institution leaves the pipeline when Airtable moves it out of the stages the program treats as active, which the Advanced tab lists.', 'wpcredits-program-manager' ) );

		$this->close_section();

		$this->open_section(
			__( 'Collaboration Agreements', 'wpcredits-program-manager' ),
			__( 'How long a signed agreement may wait for review, who reviews it, and where its wording lives.', 'wpcredits-program-manager' ),
			'collaboration-agreements'
		);

		printf(
			'<tr><th scope="row">%1$s</th><td><input type="number" class="small-text" name="agreement_review_days" min="1" max="60" value="%2$s"> %3$s<p class="description">%4$s</p></td></tr>',
			esc_html__( 'Agreement review', 'wpcredits-program-manager' ),
			esc_attr( (string) $settings['agreement_review_days'] ),
			esc_html__( 'days', 'wpcredits-program-manager' ),
			esc_html__( 'How long a signed agreement may wait before the queue marks it overdue and the nightly digest names it.', 'wpcredits-program-manager' )
		);

		WPCPM_Settings_Rows::reviewers_row(
			'agreement_notify',
			__( 'Who reviews agreements', 'wpcredits-program-manager' ),
			(string) $settings['agreement_notify'],
			__( 'Addresses told when an agreement arrives and sent the overdue digest; with none, every program manager is written to.', 'wpcredits-program-manager' ),
			__( 'That reaches technical administrators as well, so set it before the first real upload.', 'wpcredits-program-manager' )
		);

		$wording = __( 'The agreement wording', 'wpcredits-program-manager' );

		printf(
			'<tr><th scope="row">%1$s</th><td><input type="url" class="regular-text" name="agreement_doc_url" value="%2$s" placeholder="%3$s"><p class="description">%4$s</p>',
			esc_html( $wording ),
			esc_attr( (string) $settings['agreement_doc_url'] ),
			esc_attr__( 'https://docs.google.com/document/d/...', 'wpcredits-program-manager' ),
			esc_html__( 'The Google Doc the plugin\'s copy of the Collaboration Agreement was taken from, used by the Check against the Doc button; Google addresses only.', 'wpcredits-program-manager' )
		);
		WPCPM_Settings_Rows::close_row( $wording, __( 'Held on this site rather than in the code, because the document is editable by anyone holding its link and the plugin\'s source is public.', 'wpcredits-program-manager' ) );

		$this->close_section();

		$this->open_section(
			__( 'Semester reports', 'wpcredits-program-manager' ),
			__( 'When each institution\'s semester report is drafted, and who is told when it is.', 'wpcredits-program-manager' ),
			'semester-report-drafting'
		);

		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="report_autodraft" value="1"%2$s> %3$s</label><p class="description">%4$s</p></td></tr>',
			esc_html__( 'Drafting', 'wpcredits-program-manager' ),
			checked( ! empty( $settings['report_autodraft'] ), true, false ),
			esc_html__( 'Draft each institution\'s semester report when the semester ends', 'wpcredits-program-manager' ),
			esc_html__( 'A daily job drafts a report for every finished semester and tells the program managers; off, drafts are written only when a manager presses Draft now.', 'wpcredits-program-manager' )
		);

		printf(
			'<tr><th scope="row">%1$s</th><td><input type="number" class="small-text" name="report_autodraft_grace_days" min="7" max="365" value="%2$s"> %3$s<p class="description">%4$s</p></td></tr>',
			esc_html__( 'Drafting grace', 'wpcredits-program-manager' ),
			esc_attr( (string) $settings['report_autodraft_grace_days'] ),
			esc_html__( 'days', 'wpcredits-program-manager' ),
			esc_html__( 'How long after a semester ends the job waits for students still in progress before drafting anyway; the draft says how many were.', 'wpcredits-program-manager' )
		);

		WPCPM_Settings_Rows::reviewers_row(
			'report_notify',
			__( 'Who reviews reports', 'wpcredits-program-manager' ),
			(string) $settings['report_notify'],
			__( 'Addresses told when the job drafts a report; with none, every program manager is written to.', 'wpcredits-program-manager' )
		);

		$this->close_section();
	}

	/**
	 * The Sponsors tab.
	 *
	 * @param array $settings Current settings.
	 */
	private function render_sponsor_settings( array $settings ) {
		$this->open_section(
			__( 'Applications', 'wpcredits-program-manager' ),
			__( 'Whether companies can apply to sponsor the program through the public form on this site.', 'wpcredits-program-manager' ),
			'sponsor-applications'
		);

		// The public application form, first on the tab because a sponsor's life on the site begins
		// with it. Off by default and switched on here, because turning it on publishes a page that
		// strangers can post to. The privacy policy is named on the same row: the form refuses to
		// render at all without one, so a switch that looks on while the page shows nothing is
		// exactly the confusion this line exists to prevent.
		$policy_url = function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '';
		$apply_url  = class_exists( 'WPCPM_Sponsor_Application' ) ? (string) WPCPM_Sponsor_Application::page_url() : '';

		$applications = __( 'Applications from sponsors', 'wpcredits-program-manager' );

		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="sponsor_applications_enabled" value="1"%2$s> %3$s</label><p class="description">%4$s</p>%5$s',
			esc_html( $applications ),
			checked( ! empty( $settings['sponsor_applications_enabled'] ), true, false ),
			esc_html__( 'Take sponsor applications through the form on this site', 'wpcredits-program-manager' ),
			esc_html__( 'A public page anybody can post to.', 'wpcredits-program-manager' ),
			'' === $policy_url
				? '<p class="description wpcpm-warning">' . esc_html__( 'No privacy policy page is set, so the form shows nothing to the public however this is switched. Publish one and choose it under Settings > Privacy.', 'wpcredits-program-manager' ) . '</p>'
				: ''
		);
		WPCPM_Settings_Rows::page_line( $apply_url );
		WPCPM_Settings_Rows::close_row( $applications, __( 'Every submission is stored for a program manager to read on the Sponsors screen and on the Administrator Dashboard, and nothing is created in Airtable until somebody approves it. While this is off the page shows one sentence saying applications are closed.', 'wpcredits-program-manager' ) );

		$this->close_section();

		$this->open_section(
			__( 'Landing page', 'wpcredits-program-manager' ),
			__( 'Where sponsor accounts go when they log in.', 'wpcredits-program-manager' ),
			'sponsor-landing-page'
		);

		// Named and drawn as the other three audiences' landing pages are, by the same row: the page's
		// address under the switch, or the sentence saying it is missing.
		WPCPM_Settings_Rows::landing_row(
			'sponsor_home',
			__( 'Sponsor landing page', 'wpcredits-program-manager' ),
			! empty( $settings['sponsor_home'] ),
			__( 'Send sponsor accounts to the Sponsor Dashboard when they log in', 'wpcredits-program-manager' ),
			__( 'Instead of the wp-admin Dashboard; accounts that can also edit content or manage the program are left where WordPress sends them.', 'wpcredits-program-manager' ),
			class_exists( 'WPCPM_Sponsors_Dashboard' ) ? (string) WPCPM_Sponsors_Dashboard::page_url() : ''
		);

		$this->close_section();

		$this->open_section(
			__( 'When a sponsor is no longer Approved', 'wpcredits-program-manager' ),
			__( 'What the sync does with the accounts of a sponsor whose Airtable status is no longer Approved.', 'wpcredits-program-manager' ),
			'when-a-sponsor-is-no-longer-approved'
		);

		$on_inactive = isset( $settings['sponsor_on_inactive'] ) && 'revoke' === $settings['sponsor_on_inactive'] ? 'revoke' : 'keep';

		// The section's heading says when; the row is what it decides, so the two do not repeat. The
		// answers in the order and the words the other three leaving rules use, the one that removes
		// first; the default stays the second, Leave, since a pause is often short. They are a group a
		// screen reader names by the question the section asks, which gives "Its accounts" its
		// referent there.
		$its_accounts = __( 'Its accounts', 'wpcredits-program-manager' );

		printf(
			'<tr><th scope="row">%1$s</th><td><fieldset><legend class="screen-reader-text"><span>%2$s</span></legend><label><input type="radio" name="sponsor_on_inactive" value="revoke"%3$s> %4$s</label><br><label><input type="radio" name="sponsor_on_inactive" value="keep"%5$s> %6$s</label></fieldset><p class="description">%7$s</p>',
			esc_html( $its_accounts ),
			esc_html__( 'When a sponsor is no longer Approved', 'wpcredits-program-manager' ),
			checked( 'revoke', $on_inactive, false ),
			esc_html__( 'Remove its accounts from the sponsor, so they lose access to its Sponsor Dashboard', 'wpcredits-program-manager' ),
			checked( 'keep', $on_inactive, false ),
			esc_html__( 'Leave their access in place', 'wpcredits-program-manager' ),
			esc_html__( 'Nothing is ever deleted, and Paused and Not Moving Forward sponsors keep their accounts by default, because a pause is often short.', 'wpcredits-program-manager' )
		);
		WPCPM_Settings_Rows::close_row( $its_accounts, __( 'Airtable\'s Status is the record: choose the other answer to have the sync remove the accounts from any sponsor whose Status is no longer Approved.', 'wpcredits-program-manager' ) );

		$this->close_section();

		// Named for the mail, not "Mail", which is a tab of its own, and not as its row is named,
		// which says who the mail goes to.
		$this->open_section(
			__( 'Interest mail', 'wpcredits-program-manager' ),
			__( 'Where a sponsor\'s interest, and its other mail for a program manager, goes when no program manager is assigned to the sponsor.', 'wpcredits-program-manager' ),
			'interest-mail'
		);

		// Every mail meant for a sponsor's program manager takes one road, the assigned manager, else
		// these addresses, else every manager (`WPCPM_Sponsor_Interests::mail_manager()`), and a new
		// application, which has no manager yet, comes here first (`WPCPM_Sponsor_Application`).
		WPCPM_Settings_Rows::reviewers_row(
			'sponsor_notify',
			__( 'Who is mailed', 'wpcredits-program-manager' ),
			isset( $settings['sponsor_notify'] ) ? (string) $settings['sponsor_notify'] : '',
			__( 'Addresses mailed in place of a sponsor\'s program manager when it has none; with none here, every program manager is written to.', 'wpcredits-program-manager' ),
			__( 'A sponsor\'s interests, a mentor it would like to sponsor, its signed agreement, a problem reported with one of its codes and a warning that its codes are running low go to its assigned program manager, and to these addresses when it has none. Every new sponsor application comes here too, since a new application has no manager yet.', 'wpcredits-program-manager' )
		);

		$this->close_section();

		$this->open_section(
			__( 'Offers', 'wpcredits-program-manager' ),
			__( 'The sponsors\' logos and offers: how large a logo may be, where students and mentors see the offers, and when a pool of codes is running low.', 'wpcredits-program-manager' ),
			'offers'
		);

		// The sync's copies, the sponsors' own uploads and the logos sent with an application all go
		// through `WPCPM_Image_Upload`, which holds each to this size.
		printf(
			'<tr><th scope="row"><label for="logo_max_kb">%1$s</label></th><td><input type="number" min="100" max="8192" step="1" id="logo_max_kb" name="logo_max_kb" value="%2$d" /> %3$s<p class="description">%4$s</p></td></tr>',
			esc_html__( 'Largest logo', 'wpcredits-program-manager' ),
			isset( $settings['logo_max_kb'] ) ? (int) $settings['logo_max_kb'] : 1024,
			esc_html__( 'KB', 'wpcredits-program-manager' ),
			esc_html__( 'Applies to the logos the sync copies from Airtable, the ones sponsors upload and the ones sent with an application: PNG, JPEG and WebP only, never SVG.', 'wpcredits-program-manager' )
		);

		$sponsor_tools = __( 'Tools from our sponsors', 'wpcredits-program-manager' );

		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="tools_students" value="1"%2$s> %3$s</label><br /><label><input type="checkbox" name="tools_mentors" value="1"%4$s> %5$s</label><p class="description">%6$s</p>',
			esc_html( $sponsor_tools ),
			checked( ! empty( $settings['tools_students'] ), true, false ),
			esc_html__( 'Show the section on the Student Report Card', 'wpcredits-program-manager' ),
			checked( ! empty( $settings['tools_mentors'] ), true, false ),
			esc_html__( 'Show the section on the Mentor Report Card', 'wpcredits-program-manager' ),
			esc_html__( 'The section lists the live offers, each with its claim button.', 'wpcredits-program-manager' )
		);
		WPCPM_Settings_Rows::close_row( $sponsor_tools, __( 'A student always sees offers open to students; a mentor sees the offers whose sponsor opened them to mentors. The Administrator Dashboard shows every live offer whatever these say.', 'wpcredits-program-manager' ) );

		$low_stock = __( 'Low-stock warning', 'wpcredits-program-manager' );

		printf(
			'<tr><th scope="row"><label for="offer_low_stock">%1$s</label></th><td><input type="number" min="1" max="1000" step="1" id="offer_low_stock" name="offer_low_stock" value="%2$d" /> %3$s<p class="description">%4$s</p>',
			esc_html( $low_stock ),
			isset( $settings['offer_low_stock'] ) ? (int) $settings['offer_low_stock'] : 10,
			esc_html__( 'codes left', 'wpcredits-program-manager' ),
			esc_html__( 'When a pool of one-time codes falls below this many, the sponsor and its program manager are mailed once.', 'wpcredits-program-manager' )
		);
		WPCPM_Settings_Rows::close_row( $low_stock, __( 'This is the default for new offers; each offer can set its own.', 'wpcredits-program-manager' ) );

		$this->close_section();
	}

	/**
	 * The Security tab: which roles must present a second factor, and how far along each one is.
	 *
	 * Rendered even when the Two Factor plugin is not installed, because a policy that silently
	 * does nothing is worse than one that says why: the row then names the plugin before its boxes.
	 *
	 * The list posts as checkboxes with an empty hidden field in front of them, so that clearing
	 * every box still sends the key. Without it an all-unticked save would look like "the form
	 * did not render this" and `WPCPM_Settings::save()` would leave the old policy in place,
	 * which is the one shape a security setting must not have.
	 *
	 * @param array $settings Current settings.
	 */
	private function render_two_factor_settings( array $settings ) {
		$status   = WPCPM_Two_Factor::status();
		$required = WPCPM_Two_Factor::required_roles();

		$this->open_section(
			__( 'Two-factor authentication', 'wpcredits-program-manager' ),
			__( 'Which roles must give a code as well as their password when they sign in.', 'wpcredits-program-manager' ),
			'two-factor-authentication'
		);

		$roles_label = __( 'Roles that must use it', 'wpcredits-program-manager' );

		echo '<tr><th scope="row">' . esc_html( $roles_label ) . '</th><td>';

		// Before the boxes, which it points at: the policy below does nothing until the plugin is on.
		if ( ! $status['available'] ) {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'Nobody is asked for a second factor, because the Two Factor plugin is not active on this site.', 'wpcredits-program-manager' )
			);
		}

		// Always sent, so that unticking every box clears the policy rather than being read as
		// a form that did not render the field. Empty values are dropped by the sanitizer.
		echo '<input type="hidden" name="two_factor_roles[]" value="" />';

		// Administrator first and by name, because it is the role this matters most for and the
		// one WordPress owns rather than this plugin.
		$choices = array( WPCPM_Roles::ROLE_ADMIN => __( 'Program managers (administrators)', 'wpcredits-program-manager' ) );

		foreach ( WPCPM_Roles::custom_roles() as $slug => $role ) {
			$choices[ $slug ] = $role['label'];
		}

		foreach ( $choices as $slug => $label ) {
			printf(
				'<label><input type="checkbox" name="two_factor_roles[]" value="%1$s"%2$s> %3$s</label><br>',
				esc_attr( $slug ),
				in_array( $slug, $required, true ) ? ' checked' : '',
				esc_html( $label )
			);
		}

		// One sentence under the boxes, true of the site as it is: what a ticked role is asked while
		// the plugin is active, and what turning it on does while it is not.
		printf(
			'<p class="description">%s</p>',
			$status['available']
				? esc_html__( 'An account in a ticked role is asked for a code as well as its password from its next sign-in, with nothing to set up first: the code is emailed.', 'wpcredits-program-manager' )
				: esc_html__( 'Install and activate the Two Factor plugin, and the roles ticked here are asked for a code as well as a password at their next sign-in.', 'wpcredits-program-manager' )
		);

		$students = __( 'Students are left off by default: a student account holds that student\'s own work, there are hundreds of them, and there is nobody to unlock the ones who change phone. They can still turn it on for themselves.', 'wpcredits-program-manager' );

		WPCPM_Settings_Rows::close_row(
			$roles_label,
			$status['available']
				? array( __( 'Each person can then set up an authenticator app on their own profile screen, which is quicker and does not depend on their email. Untick everything to ask nobody.', 'wpcredits-program-manager' ), $students )
				: $students
		);

		if ( $status['available'] && ! empty( $status['roles'] ) ) {
			$standing = __( 'Where it stands', 'wpcredits-program-manager' );

			echo '<tr><th scope="row">' . esc_html( $standing ) . '</th><td>';

			foreach ( $status['roles'] as $row ) {
				printf(
					'<p>%s</p>',
					esc_html(
						sprintf(
							/* translators: 1: role name, 2: accounts covered, 3: accounts in the role, 4: accounts using an app. */
							__( '%1$s: %2$d of %3$d covered, %4$d using an authenticator app.', 'wpcredits-program-manager' ),
							$row['label'],
							$row['covered'],
							$row['total'],
							$row['app']
						)
					)
				);
			}

			printf(
				'<p class="description">%s</p>',
				esc_html__( 'Counted now, on this screen; an account that is covered but has no app is using emailed codes.', 'wpcredits-program-manager' )
			);

			WPCPM_Settings_Rows::close_row( $standing );
		}

		$this->close_section();
	}

	/**
	 * The Mail tab: what is waiting to be sent, a sample of each invitation sent to yourself, and
	 * what has gone out.
	 *
	 * No Save: nothing here is a setting, and each button is a form of its own posting to the
	 * sample handler, which comes back to this tab.
	 */
	private function render_mail_card() {
		$this->section_heading(
			__( 'Invitations', 'wpcredits-program-manager' ),
			__( 'Send yourself the invitation a student, a mentor, an institution or a sponsor receives, before ninety real people read it: the four say different things.', 'wpcredits-program-manager' ),
			'sending-yourself-an-invitation'
		);

		$queued = WPCPM_Mail::queued();

		if ( $queued ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: number of invitations. */
						_n(
							'%s invitation is waiting to be sent. They go out a few at a time in the background.',
							'%s invitations are waiting to be sent. They go out a few at a time in the background.',
							$queued,
							'wpcredits-program-manager'
						),
						number_format_i18n( $queued )
					)
				)
			);
		}

		foreach ( array(
			'student'     => __( 'Email me the student invitation', 'wpcredits-program-manager' ),
			'mentor'      => __( 'Email me the mentor invitation', 'wpcredits-program-manager' ),
			'institution' => __( 'Email me the institution invitation', 'wpcredits-program-manager' ),
			'sponsor'     => __( 'Email me the sponsor invitation', 'wpcredits-program-manager' ),
		) as $kind => $label ) {
			printf(
				'<form method="post" action="%1$s" class="wpcpm-inline-form">',
				esc_url( admin_url( 'admin-post.php' ) )
			);
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( WPCPM_Mail::ACTION_TEST ) );
			printf( '<input type="hidden" name="kind" value="%s" />', esc_attr( $kind ) );
			// The nonce under the name the handler reads it by, in a field with an id of its own:
			// `wp_nonce_field()` gives its field its name as its id, which four forms on one tab
			// would give to four elements.
			printf(
				'<input type="hidden" id="%1$s" name="%2$s" value="%3$s" />',
				esc_attr( WPCPM_Mail::ACTION_TEST . '-' . $kind ),
				esc_attr( WPCPM_Mail::ACTION_TEST ),
				esc_attr( wp_create_nonce( WPCPM_Mail::ACTION_TEST ) )
			);
			wp_referer_field();
			printf( '<button type="submit" class="button">%s</button>', esc_html( $label ) );
			echo '</form> ';
		}

		$this->render_mail_log();
	}

	/**
	 * The recent-mail log, the Mail tab's second section.
	 *
	 * Exists to answer one question - "the student says they got nothing" - which was
	 * previously unanswerable, because every caller threw away what `wp_mail()` told them.
	 */
	private function render_mail_log() {
		$log = WPCPM_Mail::log();

		$this->section_heading(
			__( 'Recent mail', 'wpcredits-program-manager' ),
			__( 'What the plugin has sent lately, and whether the site accepted each message.', 'wpcredits-program-manager' ),
			'recent-mail'
		);

		if ( empty( $log ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'Nothing sent yet. Bookings, cancellations, reminders and invitations are all recorded here once they are.', 'wpcredits-program-manager' )
			);

			return;
		}

		$failed = WPCPM_Mail::failures();

		if ( $failed ) {
			printf(
				'<p class="wpcpm-warning">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: number of failures. */
						_n(
							'%s of these was refused by whatever handles mail on this site. That is a delivery problem to fix, not a program one.',
							'%s of these were refused by whatever handles mail on this site. That is a delivery problem to fix, not a program one.',
							$failed,
							'wpcredits-program-manager'
						),
						number_format_i18n( $failed )
					)
				)
			);
		}

		echo '<table class="wp-list-table widefat striped">';
		printf(
			'<thead><tr><th scope="col">%1$s</th><th scope="col">%2$s</th><th scope="col">%3$s</th><th scope="col">%4$s</th></tr></thead>',
			esc_html__( 'When', 'wpcredits-program-manager' ),
			esc_html__( 'To', 'wpcredits-program-manager' ),
			esc_html__( 'Message', 'wpcredits-program-manager' ),
			esc_html__( 'Accepted', 'wpcredits-program-manager' )
		);
		echo '<tbody>';

		foreach ( array_slice( $log, 0, 25 ) as $entry ) {
			$when = isset( $entry['time'] ) ? (int) $entry['time'] : 0;

			echo '<tr>';
			printf(
				'<td>%s</td>',
				esc_html(
					$when
						? sprintf(
							/* translators: %s: human-readable time difference, e.g. "2 hours". */
							__( '%s ago', 'wpcredits-program-manager' ),
							human_time_diff( $when )
						)
						: '-'
				)
			);
			printf( '<td>%s</td>', esc_html( isset( $entry['to'] ) ? $entry['to'] : '' ) );
			printf(
				'<td>%1$s<br><span class="description">%2$s</span></td>',
				esc_html( isset( $entry['subject'] ) ? $entry['subject'] : '' ),
				esc_html( isset( $entry['context'] ) ? $entry['context'] : '' )
			);
			printf(
				'<td>%s</td>',
				empty( $entry['sent'] )
					? '<strong>' . esc_html__( 'Refused', 'wpcredits-program-manager' ) . '</strong>'
					: esc_html__( 'Yes', 'wpcredits-program-manager' )
			);
			echo '</tr>';
		}

		echo '</tbody></table>';

		printf(
			'<p class="description">%s</p>',
			esc_html__( '"Accepted" means the site handed the message off without complaint. It cannot tell you the message was delivered, or read - no sender can.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The scopes the token needs, and what each one is for, in the row's Details fold under its one
	 * sentence.
	 *
	 * Spelled out on the connection screen rather than only in the readme: a token
	 * created with read access alone looks perfectly configured here, and the first
	 * sign of the missing write scope would otherwise be a 403 halfway through
	 * promoting a mentor.
	 */
	private function render_scopes_row() {
		$scopes = array(
			array(
				'scope'    => 'data.records:read',
				'required' => __( 'Required', 'wpcredits-program-manager' ),
				'note'     => __( 'Reading mentors, students and tutors.', 'wpcredits-program-manager' ),
			),
			array(
				'scope'    => 'data.records:write',
				'required' => __( 'Required by the Mentor Status Checker', 'wpcredits-program-manager' ),
				'note'     => __( 'Changing a mentor\'s status when you promote them. Without it the tool can still run in report-only mode, but promoting fails.', 'wpcredits-program-manager' ),
			),
			array(
				'scope'    => 'schema.bases:read',
				'required' => __( 'Optional', 'wpcredits-program-manager' ),
				'note'     => __( 'Listing the bases, tables, columns, statuses and stages the Settings tabs and the Mentor Status Checker\'s screen offer, and reading each column\'s description from Airtable. Without it each of those settings is typed, and the built-in descriptions are shown instead.', 'wpcredits-program-manager' ),
			),
		);

		$label = __( 'Token scopes', 'wpcredits-program-manager' );

		printf( '<tr><th scope="row">%s</th><td>', esc_html( $label ) );
		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: the word the fold under the sentence shows, Details, which holds the list of scopes. */
					__( 'Set the scopes listed under %s on the token itself at airtable.com/create/tokens, and give it access to the WPCredits base.', 'wpcredits-program-manager' ),
					WPCPM_Settings_Rows::details_word()
				)
			)
		);

		WPCPM_Settings_Rows::open_details( $label );

		echo '<ul class="wpcpm-scopes">';

		foreach ( $scopes as $scope ) {
			printf(
				'<li><code>%1$s</code> <strong>%2$s</strong><br /><span class="description">%3$s</span></li>',
				esc_html( $scope['scope'] ),
				esc_html( $scope['required'] ),
				esc_html( $scope['note'] )
			);
		}

		echo '</ul>';
		printf(
			'<p>%s</p>',
			esc_html__( 'Scopes cannot be checked from here without writing to the base, so this list is the reference.', 'wpcredits-program-manager' )
		);
		WPCPM_Settings_Rows::close_details();
		WPCPM_Settings_Rows::close_row( $label );
	}

	/**
	 * The Advanced tab: the settings that had no control on any screen, which change rarely and
	 * whose defaults suit almost every site.
	 *
	 * Each is held by `WPCPM_Settings::save()` to the rule it always had, and each number field
	 * gives the same limits, so the browser refuses what the save would clamp without a word
	 * (bin/test-settings.php holds the two together).
	 *
	 * @param array $settings Current settings.
	 */
	private function render_tab_advanced( array $settings ) {
		$this->open_section(
			__( 'Pipeline stages', 'wpcredits-program-manager' ),
			__( 'The Airtable stages of an institution\'s pipeline: the one an approved application starts at, and the ones that count as being in it.', 'wpcredits-program-manager' ),
			'pipeline-stages'
		);

		$stages = WPCPM_Settings_Choices::institution_stages( $settings );
		$why    = WPCPM_Settings_Choices::why_empty( 'institution_stages', $settings );

		WPCPM_Settings_Rows::select_row( 'institution_new_stage', __( 'Institution starting stage', 'wpcredits-program-manager' ), $settings['institution_new_stage'], $stages, __( 'The Current Stage an institution is created at in Airtable when its application is approved; the stage cannot be left blank.', 'wpcredits-program-manager' ), WPCPM_Settings_Rows::typed( 'stage', $why ) );

		WPCPM_Settings_Rows::checklist_row(
			'institution_active_stages',
			__( 'Institution pipeline stages', 'wpcredits-program-manager' ),
			WPCPM_Settings::clean_list( (array) $settings['institution_active_stages'] ),
			$stages,
			__( 'An institution at any of these stages is in the pipeline, and one whose stage leaves the list is treated as having left it, so the list cannot be left empty.', 'wpcredits-program-manager' ),
			array(),
			WPCPM_Settings_Rows::typed( 'stages', $why )
		);

		$this->close_section();

		$this->open_section(
			__( 'Applications', 'wpcredits-program-manager' ),
			__( 'How long a decided institution or sponsor application is kept, and the one proxy the two application forms believe about a sender\'s address.', 'wpcredits-program-manager' ),
			'keeping-applications'
		);

		WPCPM_Settings_Rows::number_row( 'application_spam_days', __( 'Spam kept for (days)', 'wpcredits-program-manager' ), $settings['application_spam_days'], 1, 365, __( 'An application marked as spam is deleted this many days after it was marked.', 'wpcredits-program-manager' ) );
		WPCPM_Settings_Rows::number_row( 'application_rejected_days', __( 'Rejected kept for (days)', 'wpcredits-program-manager' ), $settings['application_rejected_days'], 30, 3650, __( 'A rejected application, or one the checks held, is deleted this many days after the decision: long enough to recognize the same applicant applying again.', 'wpcredits-program-manager' ) );
		WPCPM_Settings_Rows::number_row( 'application_approved_days', __( 'Approved kept for (days)', 'wpcredits-program-manager' ), $settings['application_approved_days'], 0, 3650, __( '0 keeps approved applications forever: they are the record of who was let in.', 'wpcredits-program-manager' ) );
		// One address, not a range: the save keeps one valid address or none, and the forms compare it
		// with the connecting address as one string (`WPCPM_Form_Guard::client_ip()`).
		WPCPM_Settings_Rows::text_row(
			'application_trusted_proxy',
			__( 'Trusted proxy', 'wpcredits-program-manager' ),
			$settings['application_trusted_proxy'],
			__( 'The one IP address, not a range, of the proxy whose forwarded header the application forms believe for a sender\'s address.', 'wpcredits-program-manager' ),
			'text',
			'',
			'',
			__( 'The forms\' limits count by the sender\'s address, so leave it empty unless the site sits behind a proxy: anybody can send that header. Anything but one address, a range included, is saved as empty.', 'wpcredits-program-manager' )
		);

		$this->close_section();

		$this->open_section(
			__( 'Agreements', 'wpcredits-program-manager' ),
			__( 'The limits on uploading and generating Collaboration Agreements, and how long a withdrawn or returned file is kept.', 'wpcredits-program-manager' ),
			'agreement-files'
		);

		WPCPM_Settings_Rows::number_row( 'agreement_max_mb', __( 'Largest upload (MB)', 'wpcredits-program-manager' ), $settings['agreement_max_mb'], 1, 50, __( 'The largest signed agreement an institution or a sponsor can upload.', 'wpcredits-program-manager' ) );
		WPCPM_Settings_Rows::number_row( 'agreement_uploads_per_day', __( 'Uploads per day', 'wpcredits-program-manager' ), $settings['agreement_uploads_per_day'], 1, 50, __( 'How many files one institution or one sponsor can upload in a day.', 'wpcredits-program-manager' ) );
		WPCPM_Settings_Rows::number_row( 'agreement_generations_per_day', __( 'Generations per day', 'wpcredits-program-manager' ), $settings['agreement_generations_per_day'], 1, 100, __( 'How many times one institution can generate its agreement from the template in a day.', 'wpcredits-program-manager' ) );
		WPCPM_Settings_Rows::number_row( 'agreement_discard_days', __( 'Discarded files kept for (days)', 'wpcredits-program-manager' ), $settings['agreement_discard_days'], 7, 365, __( 'A withdrawn or returned file is deleted this many days after it was set aside; an accepted agreement never is.', 'wpcredits-program-manager' ) );

		$this->close_section();

		$this->open_section(
			__( 'Invitations', 'wpcredits-program-manager' ),
			__( 'How long an invitation to join an institution\'s account is kept once it was accepted, canceled or ran out.', 'wpcredits-program-manager' ),
			'lapsed-invitations'
		);

		WPCPM_Settings_Rows::number_row( 'invite_retention_days', __( 'Settled invitations kept for (days)', 'wpcredits-program-manager' ), $settings['invite_retention_days'], 7, 365, __( 'A settled invitation stays listed this many days, so a program manager can still see who was invited and never came.', 'wpcredits-program-manager' ) );

		$this->close_section();
	}
}
