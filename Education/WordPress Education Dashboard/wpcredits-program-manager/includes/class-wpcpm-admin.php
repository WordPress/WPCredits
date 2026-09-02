<?php
/**
 * Admin menu and settings screen.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the top-level "WPCredits Program" menu: one submenu per module, plus
 * an overview and a shared settings screen.
 */
class WPCPM_Admin {

	const MENU_SLUG      = 'wpcpm';
	const TOOLS_SLUG     = 'wpcpm-tools';
	const SETTINGS_SLUG  = 'wpcpm-settings';
	const SETTINGS_NONCE = 'wpcpm_save_settings';

	/**
	 * Boolean settings the settings screen does not render yet.
	 *
	 * The save handler reads every other switch unconditionally, because an unticked box
	 * posts nothing and absent has to mean off. For a switch with no box on the form absent
	 * means nothing, so these are left to `WPCPM_Settings::save()`'s own guard. The
	 * Institutions settings card removes each one from here when it renders it.
	 */
	const UNRENDERED_SWITCHES = array( 'institution_provision', 'institution_home', 'applications_enabled', 'import_enabled' );

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_settings_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the menu and one page per module.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'WPCredits Program', 'wpcredits-program-manager' ),
			__( 'WPCredits Program', 'wpcredits-program-manager' ),
			WPCPM_Roles::CAP_MANAGE,
			self::MENU_SLUG,
			array( $this, 'render_overview' ),
			'dashicons-groups',
			30
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Overview', 'wpcredits-program-manager' ),
			__( 'Overview', 'wpcredits-program-manager' ),
			WPCPM_Roles::CAP_MANAGE,
			self::MENU_SLUG,
			array( $this, 'render_overview' )
		);

		foreach ( WPCPM_Modules::all() as $module ) {
			add_submenu_page(
				self::MENU_SLUG,
				$module->label(),
				// The menu title, not the page title: a module may hang a pending-count bubble on
				// it, and the plain label stays the `<h1>`.
				$module->menu_label(),
				WPCPM_Roles::CAP_MANAGE,
				$module->page_slug(),
				array( $module, 'render_admin_page' )
			);
		}

		// Listed after the four audience screens. Called "Modules" on screen because that
		// is what they are to somebody running the program; the slug and the internal
		// `WPCPM_Tool` vocabulary are unchanged, so bookmarks and page slugs still work.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Modules', 'wpcredits-program-manager' ),
			__( 'Modules', 'wpcredits-program-manager' ),
			WPCPM_Roles::CAP_MANAGE,
			self::TOOLS_SLUG,
			array( $this, 'render_tools' )
		);

		foreach ( WPCPM_Tools::all() as $tool ) {
			add_submenu_page(
				self::MENU_SLUG,
				$tool->label(),
				// Indented so the submenu reads as a tool belonging to Tools rather
				// than as a fifth module.
				'— ' . $tool->label(),
				WPCPM_Roles::CAP_MANAGE,
				$tool->page_slug(),
				array( $tool, 'render_admin_page' )
			);
		}

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'wpcredits-program-manager' ),
			__( 'Settings', 'wpcredits-program-manager' ),
			WPCPM_Roles::CAP_MANAGE,
			self::SETTINGS_SLUG,
			array( $this, 'render_settings' )
		);
	}

	/**
	 * The settings screen's URL.
	 *
	 * @return string
	 */
	public static function settings_url() {
		return admin_url( 'admin.php?page=' . self::SETTINGS_SLUG );
	}

	/**
	 * The Tools screen: one card per tool.
	 */
	public function render_tools() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html__( 'Modules', 'wpcredits-program-manager' ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html__( 'Parts of the program you can switch on, run and configure separately from the audiences above.', 'wpcredits-program-manager' ) . '</p>';

		$tools = WPCPM_Tools::all();

		if ( empty( $tools ) ) {
			echo '<div class="wpcpm-card"><p>' . esc_html__( 'No modules are registered.', 'wpcredits-program-manager' ) . '</p></div>';
			echo '</div>';

			return;
		}

		echo '<div class="wpcpm-modules">';

		foreach ( $tools as $tool ) {
			echo '<div class="wpcpm-module-card">';
			printf(
				'<h2><a href="%1$s">%2$s</a></h2>',
				esc_url( $tool->admin_url() ),
				esc_html( $tool->label() )
			);
			echo '<p>' . esc_html( $tool->description() ) . '</p>';

			$status = $tool->status_line();
			if ( '' !== $status ) {
				echo '<p class="wpcpm-tool-status">' . esc_html( $status ) . '</p>';
			}

			if ( ! $tool->is_ready() ) {
				echo '<p class="wpcpm-warning">' . esc_html__( 'Airtable is not connected yet, so this tool cannot run.', 'wpcredits-program-manager' ) . '</p>';
			}

			printf(
				'<p><a class="button" href="%1$s">%2$s</a></p>',
				esc_url( $tool->admin_url() ),
				esc_html__( 'Open tool', 'wpcredits-program-manager' )
			);

			echo '</div>';
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Load CSS and JS on this plugin's screens only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, self::MENU_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'wpcpm-admin',
			WPCPM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WPCPM_VERSION
		);

		wp_enqueue_script(
			'wpcpm-admin',
			WPCPM_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			WPCPM_VERSION,
			true
		);
	}

	/**
	 * The overview screen: one card per module.
	 */
	public function render_overview() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html__( 'WPCredits Program Manager', 'wpcredits-program-manager' ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html__( 'The program in modules. Each module owns one audience and one user role.', 'wpcredits-program-manager' ) . '</p>';

		if ( ! WPCPM_Settings::is_connected() ) {
			printf(
				'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
				esc_html__( 'Airtable is not connected yet.', 'wpcredits-program-manager' ),
				esc_url( admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ) ),
				esc_html__( 'Add a Personal Access Token', 'wpcredits-program-manager' )
			);
		}

		echo '<div class="wpcpm-modules">';

		$index = 0;
		foreach ( WPCPM_Modules::all() as $module ) {
			++$index;

			printf(
				'<div class="wpcpm-module-card%1$s">',
				$module->is_implemented() ? '' : ' is-pending'
			);

			printf(
				'<h2><span class="wpcpm-module-card__index">%1$s</span> <a href="%2$s">%3$s</a></h2>',
				esc_html( number_format_i18n( $index ) ),
				esc_url( $module->admin_url() ),
				esc_html( $module->label() )
			);

			echo '<p>' . esc_html( $module->description() ) . '</p>';

			echo '<ul class="wpcpm-module-card__meta">';
			printf(
				'<li><strong>%1$s</strong> <code>%2$s</code></li>',
				esc_html__( 'Role:', 'wpcredits-program-manager' ),
				esc_html( $module->role() )
			);
			printf(
				'<li><strong>%1$s</strong> %2$s</li>',
				esc_html__( 'Accounts:', 'wpcredits-program-manager' ),
				esc_html( number_format_i18n( $module->user_count() ) )
			);
			printf(
				'<li><strong>%1$s</strong> %2$s</li>',
				esc_html__( 'Built:', 'wpcredits-program-manager' ),
				$module->is_implemented() ? esc_html__( 'Yes', 'wpcredits-program-manager' ) : esc_html__( 'Role only', 'wpcredits-program-manager' )
			);
			echo '</ul>';

			echo '</div>';
		}

		echo '</div>';

		// Listed after the audiences so the four-audience structure stays the first thing
		// on the screen.
		$tools = WPCPM_Tools::all();

		if ( ! empty( $tools ) ) {
			echo '<h2>' . esc_html__( 'Modules', 'wpcredits-program-manager' ) . '</h2>';
			echo '<p class="wpcpm-lede">' . esc_html__( 'Parts of the program that can be switched on, run and configured on their own.', 'wpcredits-program-manager' ) . '</p>';
			echo '<div class="wpcpm-modules">';

			foreach ( $tools as $tool ) {
				echo '<div class="wpcpm-module-card">';
				printf(
					'<h2><a href="%1$s">%2$s</a></h2>',
					esc_url( $tool->admin_url() ),
					esc_html( $tool->label() )
				);
				echo '<p>' . esc_html( $tool->description() ) . '</p>';

				$status = $tool->status_line();
				if ( '' !== $status ) {
					echo '<p class="wpcpm-tool-status">' . esc_html( $status ) . '</p>';
				}

				echo '</div>';
			}

			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Persist the settings form.
	 */
	public function handle_settings_save() {
		if ( ! isset( $_POST[ self::SETTINGS_NONCE ] ) ) {
			return;
		}

		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		check_admin_referer( self::SETTINGS_NONCE, self::SETTINGS_NONCE );

		// Derived from the defaults rather than hand-listed. The hand-written list was a
		// standing invitation to add a field to the form, add its sanitiser to
		// `WPCPM_Settings::save()`, and forget the third place — at which point the field
		// renders, accepts input, posts it, and is silently discarded. Twenty-one fields were
		// in that state, including the whole mentor-checker card and the AI provider.
		//
		// Safe to iterate every setting because of the `isset()`: a key this form does not
		// render is simply absent from the request and is left alone. The one shape that
		// cannot work that way is a checkbox, which posts nothing at all when unticked - so
		// those are read unconditionally below, which is only correct for the ones this form
		// renders. The switches it does not render yet are listed in UNRENDERED_SWITCHES and
		// skipped: read unconditionally, the first save of this screen would have switched
		// every one of them off. bin/test-settings.php checks the list against the form.
		$input    = array();
		$defaults = WPCPM_Settings::defaults();

		foreach ( $defaults as $key => $default ) {
			if ( is_bool( $default ) ) {
				continue;
			}

			if ( isset( $_POST[ $key ] ) ) {
				$input[ $key ] = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised in WPCPM_Settings::save().
			}
		}

		foreach ( $defaults as $key => $default ) {
			if ( is_bool( $default ) && ! in_array( $key, self::UNRENDERED_SWITCHES, true ) ) {
				$input[ $key ] = ! empty( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to bool on the spot.
			}
		}

		WPCPM_Settings::save( $input );

		WPCPM_Flash::set( 'settings', 'saved' );

		wp_safe_redirect( self::settings_url() );
		exit;
	}

	/**
	 * The shared settings screen.
	 */
	public function render_settings() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$settings = WPCPM_Settings::get();
		$status   = (string) WPCPM_Flash::take( 'settings' );

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html__( 'WPCredits Program — Settings', 'wpcredits-program-manager' ) . '</h1>';

		$messages = array(
			'saved'       => array( 'success', __( 'Settings saved.', 'wpcredits-program-manager' ) ),
			'test-sent'   => array( 'success', __( 'The sample invitation is on its way to your own address.', 'wpcredits-program-manager' ) ),
			'test-failed' => array( 'error', __( 'The sample could not be sent. Whatever handles mail on this site refused it, so a real invitation would not arrive either.', 'wpcredits-program-manager' ) ),
			'log-cleared' => array( 'success', __( 'The mail log is empty.', 'wpcredits-program-manager' ) ),
		);

		if ( isset( $messages[ $status ] ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $messages[ $status ][0] ),
				esc_html( $messages[ $status ][1] )
			);
		}

		echo '<form method="post" action="">';
		wp_nonce_field( self::SETTINGS_NONCE, self::SETTINGS_NONCE );

		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Airtable connection', 'wpcredits-program-manager' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->text_row(
			'api_token',
			__( 'Personal Access Token', 'wpcredits-program-manager' ),
			WPCPM_Settings::masked_token(),
			__( 'Stored in the database and never sent to the browser — leave blank to keep the current token.', 'wpcredits-program-manager' ),
			'password'
		);

		$this->render_scopes_row();

		$this->text_row( 'base_id', __( 'Base ID', 'wpcredits-program-manager' ), $settings['base_id'] );
		$this->text_row( 'mentors_table', __( 'Mentors table', 'wpcredits-program-manager' ), $settings['mentors_table'] );
		$this->text_row( 'reports_table', __( 'Students Reports table', 'wpcredits-program-manager' ), $settings['reports_table'], __( 'Holds the internship dates, links and contribution team shown on the mentor page.', 'wpcredits-program-manager' ) );
		$this->text_row( 'students_table', __( 'Students table', 'wpcredits-program-manager' ), $settings['students_table'], __( 'Read only for the Tutor column, which does not exist on Students Reports.', 'wpcredits-program-manager' ) );

		echo '</tbody></table>';
		echo '</div>';

		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Linked tables', 'wpcredits-program-manager' ) . '</h2>';
		echo '<p>' . esc_html__( 'Airtable sends linked-record fields as record IDs, not names. These two tables are read so those IDs can be shown as names on the mentor page.', 'wpcredits-program-manager' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->text_row( 'institutions_table', __( 'Institutions table', 'wpcredits-program-manager' ), $settings['institutions_table'] );
		$this->text_row(
			'institutions_name_field',
			__( 'Institutions name column', 'wpcredits-program-manager' ),
			$settings['institutions_name_field'],
			__( 'Only used when the token lacks <code>schema.bases:read</code>. With that scope the primary column is detected automatically.', 'wpcredits-program-manager' )
		);
		$this->text_row( 'teams_table', __( 'Contribution areas table', 'wpcredits-program-manager' ), $settings['teams_table'] );
		$this->text_row( 'teams_name_field', __( 'Contribution areas name column', 'wpcredits-program-manager' ), $settings['teams_name_field'] );

		echo '</tbody></table>';
		echo '</div>';

		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Mentors module', 'wpcredits-program-manager' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->text_row( 'mentor_status', __( 'Mentor status to sync', 'wpcredits-program-manager' ), $settings['mentor_status'], __( 'Only mentors holding this Airtable status get an account.', 'wpcredits-program-manager' ) );

		printf(
			'<tr><th scope="row"><label for="wpcpm-student-statuses">%1$s</label></th><td><textarea id="wpcpm-student-statuses" name="student_statuses" rows="3" class="regular-text">%2$s</textarea><p class="description">%3$s</p></td></tr>',
			esc_html__( 'Currently mentoring', 'wpcredits-program-manager' ),
			esc_textarea( implode( "\n", (array) $settings['student_statuses'] ) ),
			esc_html__( 'One status per line. Students holding any of these appear under "Currently mentoring" on their mentor\'s page.', 'wpcredits-program-manager' )
		);

		printf(
			'<tr><th scope="row"><label for="wpcpm-past-statuses">%1$s</label></th><td><textarea id="wpcpm-past-statuses" name="past_statuses" rows="3" class="regular-text">%2$s</textarea><p class="description">%3$s</p></td></tr>',
			esc_html__( 'Past students', 'wpcredits-program-manager' ),
			esc_textarea( implode( "\n", (array) $settings['past_statuses'] ) ),
			esc_html__( 'Statuses that mean mentoring has finished. These students appear in a separate, collapsed section. Leave empty to show only current students. A status listed in both boxes counts as current.', 'wpcredits-program-manager' )
		);

		printf(
			'<tr><th scope="row">%1$s</th><td><fieldset><label><input type="radio" name="on_inactive" value="revoke"%2$s> %3$s</label><br><label><input type="radio" name="on_inactive" value="keep"%4$s> %5$s</label></fieldset><p class="description">%6$s</p></td></tr>',
			esc_html__( 'When a mentor is no longer active', 'wpcredits-program-manager' ),
			checked( $settings['on_inactive'], 'revoke', false ),
			esc_html__( 'Remove the Mentor role and clear their student list', 'wpcredits-program-manager' ),
			checked( $settings['on_inactive'], 'keep', false ),
			esc_html__( 'Leave the role in place', 'wpcredits-program-manager' ),
			esc_html__( 'The account itself is never deleted either way.', 'wpcredits-program-manager' )
		);

		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="send_welcome_email" value="1"%2$s> %3$s</label><p class="description">%4$s</p></td></tr>',
			esc_html__( 'Invitation emails', 'wpcredits-program-manager' ),
			checked( ! empty( $settings['send_welcome_email'] ), true, false ),
			esc_html__( 'Email each new mentor a password-reset link as their account is created', 'wpcredits-program-manager' ),
			esc_html__( 'Off by default. A first sync creates around ninety accounts at once, so leave this off unless you mean to email all of them. Invitations are queued and sent a few at a time rather than all inside the sync, so a mail limit cannot swallow half of them unnoticed. You can also invite people one at a time from the Mentors and Students screens.', 'wpcredits-program-manager' )
		);

		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="auto_sync" value="1"%2$s> %3$s</label><p class="description">%4$s</p></td></tr>',
			esc_html__( 'Automatic sync', 'wpcredits-program-manager' ),
			checked( ! empty( $settings['auto_sync'] ), true, false ),
			esc_html__( 'Read Airtable on a schedule', 'wpcredits-program-manager' ),
			esc_html__( 'Students every three hours, mentors once a day: the student rows carry what people are shown on their cards, and the mentors run costs one WordPress.org profile read per mentor. A run already in progress is left to finish rather than restarted. Either can also be run by hand from the Students and Mentors screens.', 'wpcredits-program-manager' )
		);

		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="mentor_home" value="1"%2$s> %3$s</label><p class="description">%4$s</p>%5$s</td></tr>',
			esc_html__( 'Mentor landing page', 'wpcredits-program-manager' ),
			checked( ! empty( $settings['mentor_home'] ), true, false ),
			esc_html__( 'Use the Mentor Report Card page as the mentor dashboard', 'wpcredits-program-manager' ),
			esc_html__( 'Mentors go there when they log in and in place of the wp-admin Dashboard, and get a "Mentor Report Card" link in the toolbar. They keep access to their own profile screen, and a mentor who followed a link to somewhere specific still lands there instead. Administrators are unaffected.', 'wpcredits-program-manager' ),
			WPCPM_Mentors_Dashboard::page_url()
				? sprintf( '<p class="description"><a href="%1$s">%1$s</a></p>', esc_url( WPCPM_Mentors_Dashboard::page_url() ) )
				: '<p class="description wpcpm-warning">' . esc_html__( 'The page is missing — re-activate the plugin to recreate it.', 'wpcredits-program-manager' ) . '</p>'
		);

		echo '</tbody></table>';
		echo '</div>';

		// Students module. The status lists above are shared: the same two sets
		// decide who is a current student and who has finished.
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Students module', 'wpcredits-program-manager' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Uses the same status lists as the Mentors module above — a current student is anyone a mentor is currently mentoring.', 'wpcredits-program-manager' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';

		printf(
			'<tr><th scope="row">%1$s</th><td><fieldset><label><input type="radio" name="student_on_inactive" value="revoke"%2$s> %3$s</label><br><label><input type="radio" name="student_on_inactive" value="keep"%4$s> %5$s</label></fieldset><p class="description">%6$s</p></td></tr>',
			esc_html__( 'When a student leaves the program', 'wpcredits-program-manager' ),
			checked( $settings['student_on_inactive'], 'revoke', false ),
			esc_html__( 'Remove the Student role, so they lose access to Student-level content', 'wpcredits-program-manager' ),
			checked( $settings['student_on_inactive'], 'keep', false ),
			esc_html__( 'Leave the role in place', 'wpcredits-program-manager' ),
			esc_html__( 'The account itself is never deleted either way, and their program details are kept.', 'wpcredits-program-manager' )
		);

		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="student_home" value="1"%2$s> %3$s</label><p class="description">%4$s</p>%5$s</td></tr>',
			esc_html__( 'Student landing page', 'wpcredits-program-manager' ),
			checked( ! empty( $settings['student_home'] ), true, false ),
			esc_html__( 'Use the My Program page as the student dashboard', 'wpcredits-program-manager' ),
			esc_html__( 'Students go there when they log in and in place of the wp-admin Dashboard, and get a "My Program" link in the toolbar. Same exceptions as for mentors: a requested destination wins, their own profile screen stays reachable, and anyone who can write posts is left alone.', 'wpcredits-program-manager' ),
			WPCPM_Students_Dashboard::page_url()
				? sprintf( '<p class="description"><a href="%1$s">%1$s</a></p>', esc_url( WPCPM_Students_Dashboard::page_url() ) )
				: '<p class="description wpcpm-warning">' . esc_html__( 'The page is missing — re-activate the plugin to recreate it.', 'wpcredits-program-manager' ) . '</p>'
		);

		echo '</tbody></table>';
		echo '</div>';

		$this->render_checker_settings( $settings );

		$this->render_handbook_settings( $settings );

		$this->render_two_factor_settings( $settings );

		submit_button( __( 'Save settings', 'wpcredits-program-manager' ) );
		echo '</form>';

		// After the settings form, not inside it: each control below posts to its own
		// handler, and a form cannot be nested in another.
		$this->render_mail_card();

		echo '</div>';
	}

	/**
	 * Which roles must present a second factor, and how far along each one is.
	 *
	 * Rendered even when the Two Factor plugin is not installed, because a policy that silently
	 * does nothing is worse than one that says why: the card then names the plugin and stops.
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

		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Two-factor authentication', 'wpcredits-program-manager' ) . '</h2>';

		if ( ! $status['available'] ) {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'Nobody is asked for a second factor, because the Two Factor plugin is not active on this site. Install and activate it, and the roles ticked below will be asked for a code as well as a password at their next sign-in.', 'wpcredits-program-manager' )
			);
		} else {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'An account in a ticked role is asked for a code as well as its password, from its next sign-in, with nothing to set up first: the code is emailed. Each person can then set up an authenticator app on their own profile screen, which is quicker and does not depend on their email. Untick everything to ask nobody.', 'wpcredits-program-manager' )
			);
		}

		echo '<table class="wpcpm-table"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Roles that must use it', 'wpcredits-program-manager' ) . '</th><td>';

		// Always sent, so that unticking every box clears the policy rather than being read as
		// a form that did not render the field. Empty values are dropped by the sanitiser.
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

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Students are left off by default: a student account holds that student\'s own work, there are hundreds of them, and there is nobody to unlock the ones who change phone. They can still turn it on for themselves.', 'wpcredits-program-manager' )
		);

		echo '</td></tr>';

		if ( $status['available'] && ! empty( $status['roles'] ) ) {
			echo '<tr><th scope="row">' . esc_html__( 'Where it stands', 'wpcredits-program-manager' ) . '</th><td>';

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
				esc_html__( 'Counted now, on this screen. An account that is covered but has no app is using emailed codes.', 'wpcredits-program-manager' )
			);

			echo '</td></tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * The handbook assistant's source, provider and audience.
	 *
	 * @param array $settings Current settings.
	 */
	private function render_handbook_settings( array $settings ) {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Need help?', 'wpcredits-program-manager' ) . '</h2>';
		printf(
			'<p>%s</p>',
			esc_html__( 'A question box for people on the program, answered from the WordPress documentation. The AI provider below does the searching, so nothing is stored on this site — and without a provider there is no answer at all. Each question, and the pages found for it, go to that company.', 'wpcredits-program-manager' )
		);

		echo '<table class="form-table" role="presentation"><tbody>';

		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="handbook_enabled" value="1"%2$s> %3$s</label><p class="description">%4$s</p></td></tr>',
			esc_html__( 'Need help?', 'wpcredits-program-manager' ),
			checked( ! empty( $settings['handbook_enabled'] ), true, false ),
			esc_html__( 'Switch it on', 'wpcredits-program-manager' ),
			esc_html__( 'Off means the question box answers nobody, the header button disappears and the page it lives on is unpublished. Nothing is deleted, so switching it back on restores all of it.', 'wpcredits-program-manager' )
		);

		printf(
			'<tr><th scope="row">%1$s</th><td><p class="description">%2$s</p></td></tr>',
			esc_html__( 'Where answers come from', 'wpcredits-program-manager' ),
			esc_html__( 'The provider searches wordpress.org, make.wordpress.org, learn.wordpress.org and developer.wordpress.org itself. Nothing is copied to this site, so there is nothing to configure and nothing to refresh — and equally, no answer at all without a provider below.', 'wpcredits-program-manager' )
		);

		// Provider, key and model together: they are useless apart, and a key entered
		// without a provider selected is the kind of thing that looks configured and is not.
		$options = '';

		foreach ( WPCPM_Handbook_Answer::providers() as $slug => $label ) {
			$options .= sprintf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $slug ),
				selected( $settings['handbook_provider'], $slug, false ),
				esc_html( $label )
			);
		}

		printf(
			'<tr><th scope="row"><label for="wpcpm-handbook-provider">%1$s</label></th><td><select id="wpcpm-handbook-provider" name="handbook_provider">%2$s</select><p class="description">%3$s</p></td></tr>',
			esc_html__( 'Answer provider', 'wpcredits-program-manager' ),
			$options, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built immediately above from escaped parts.
			esc_html__( 'Leave as "None" to keep everything on this site. Choosing a provider sends each question, and the extracts that match it, to that company.', 'wpcredits-program-manager' )
		);

		$this->text_row(
			'handbook_key',
			__( 'Provider API key', 'wpcredits-program-manager' ),
			WPCPM_Settings::masked_handbook_key(),
			__( 'Stored in the database and never sent to the browser — leave blank to keep the current key. Get one free at aistudio.google.com for the Gemini provider.', 'wpcredits-program-manager' ),
			'password'
		);

		$this->text_row(
			'handbook_model',
			__( 'Model', 'wpcredits-program-manager' ),
			$settings['handbook_model'],
			__( 'Leave as gemini-flash-latest unless you have a reason not to. It is an alias that always points at the current Gemini Flash, so it cannot be retired out from under this site — which has already happened twice to specific version numbers.', 'wpcredits-program-manager' )
		);

		$audiences = array(
			'mentor'  => __( 'Mentors and program managers', 'wpcredits-program-manager' ),
			'program' => __( 'Students and institutions as well', 'wpcredits-program-manager' ),
			'any'     => __( 'Anybody logged in to this site', 'wpcredits-program-manager' ),
			'manage'  => __( 'Program managers only', 'wpcredits-program-manager' ),
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

		printf(
			'<tr><th scope="row">%1$s</th><td><fieldset>%2$s</fieldset><p class="description">%3$s</p></td></tr>',
			esc_html__( 'Who can ask', 'wpcredits-program-manager' ),
			$radios, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built immediately above from escaped parts.
			esc_html__( 'Never anybody logged out, whatever this says. The documentation describes running the program rather than being on it, which is why students are not included by default.', 'wpcredits-program-manager' )
		);

		printf(
			'<tr><th scope="row"><label for="wpcpm-handbook-limit">%1$s</label></th><td><input type="number" id="wpcpm-handbook-limit" name="handbook_limit" value="%2$d" min="0" max="200" step="1" class="small-text"><p class="description">%3$s</p></td></tr>',
			esc_html__( 'Questions per person per hour', 'wpcredits-program-manager' ),
			(int) $settings['handbook_limit'],
			esc_html__( 'How many questions one person may have answered in an hour, so a free tier cannot be spent in an afternoon. Past the limit they are asked to come back shortly. 0 removes the limit.', 'wpcredits-program-manager' )
		);

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Mail: send yourself a sample, and see what has gone out.
	 */
	private function render_mail_card() {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Mail', 'wpcredits-program-manager' ) . '</h2>';

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

		printf(
			'<p>%s</p>',
			esc_html__( 'Ninety people is a bad audience for a first look at a template. Send yourself the invitation as a student, a mentor or an institution would receive it - the three say different things.', 'wpcredits-program-manager' )
		);

		foreach ( array(
			'student'     => __( 'Email me the student invitation', 'wpcredits-program-manager' ),
			'mentor'      => __( 'Email me the mentor invitation', 'wpcredits-program-manager' ),
			'institution' => __( 'Email me the institution invitation', 'wpcredits-program-manager' ),
		) as $kind => $label ) {
			printf(
				'<form method="post" action="%1$s" class="wpcpm-inline-form">',
				esc_url( admin_url( 'admin-post.php' ) )
			);
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( WPCPM_Mail::ACTION_TEST ) );
			printf( '<input type="hidden" name="kind" value="%s" />', esc_attr( $kind ) );
			wp_nonce_field( WPCPM_Mail::ACTION_TEST, WPCPM_Mail::ACTION_TEST );
			printf( '<button type="submit" class="button">%s</button>', esc_html( $label ) );
			echo '</form> ';
		}

		$this->render_mail_log();

		echo '</div>';
	}

	/**
	 * The recent-mail log.
	 *
	 * Exists to answer one question — "the student says they got nothing" — which was
	 * previously unanswerable, because every caller threw away what `wp_mail()` told them.
	 */
	private function render_mail_log() {
		$log = WPCPM_Mail::log();

		printf( '<h3>%s</h3>', esc_html__( 'Recent mail', 'wpcredits-program-manager' ) );

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
						: '—'
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
			esc_html__( '"Accepted" means the site handed the message off without complaint. It cannot tell you the message was delivered, or read — no sender can.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The scopes the token needs, and what each one is for.
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
				'note'     => __( 'Reading each column\'s description from Airtable. Without it the built-in descriptions are shown instead.', 'wpcredits-program-manager' ),
			),
		);

		printf( '<tr><th scope="row">%s</th><td>', esc_html__( 'Token scopes', 'wpcredits-program-manager' ) );
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
			'<p class="description">%s</p>',
			esc_html__( 'Set these on the token itself at airtable.com/create/tokens, and give it access to the WPCredits base. Scopes cannot be checked from here without writing to the base, so this list is the reference.', 'wpcredits-program-manager' )
		);
		echo '</td></tr>';
	}

	/**
	 * Settings for the Mentor Status Checker tool.
	 *
	 * @param array $settings Current settings.
	 */
	private function render_checker_settings( array $settings ) {
		echo '<div class="wpcpm-card">';
		printf(
			'<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>',
			esc_html__( 'Tool: Mentor Status Checker', 'wpcredits-program-manager' ),
			esc_html__( 'Tool', 'wpcredits-program-manager' )
		);
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->text_row( 'checker_source_status', __( 'Check mentors with status', 'wpcredits-program-manager' ), $settings['checker_source_status'], __( 'Only mentors holding this status are looked up.', 'wpcredits-program-manager' ) );
		$this->text_row( 'checker_target_status', __( 'Promote them to', 'wpcredits-program-manager' ), $settings['checker_target_status'], __( 'Writing this status needs the <code>data.records:write</code> scope on the token.', 'wpcredits-program-manager' ) );
		$this->text_row( 'checker_course_title', __( 'Course title', 'wpcredits-program-manager' ), $settings['checker_course_title'] );
		$this->text_row( 'checker_course_slug', __( 'Course slug', 'wpcredits-program-manager' ), $settings['checker_course_slug'], __( 'The slug in the learn.wordpress.org course URL. This is the reliable signal; the title is only a fallback.', 'wpcredits-program-manager' ) );
		$this->text_row( 'checker_completion_phrase', __( 'Completion phrase', 'wpcredits-program-manager' ), $settings['checker_completion_phrase'], __( 'Both this phrase and the course must appear in the same profile history entry, so someone who merely blogged about the course is not counted.', 'wpcredits-program-manager' ) );

		printf(
			'<tr><th scope="row">%1$s</th><td><fieldset><label><input type="radio" name="checker_timeline_filter" value="meta"%2$s> %3$s</label><br><label><input type="radio" name="checker_timeline_filter" value="all"%4$s> %5$s</label></fieldset><p class="description">%6$s</p></td></tr>',
			esc_html__( 'Profile history filter', 'wpcredits-program-manager' ),
			checked( $settings['checker_timeline_filter'], 'meta', false ),
			esc_html__( 'Milestones only (faster)', 'wpcredits-program-manager' ),
			checked( $settings['checker_timeline_filter'], 'all', false ),
			esc_html__( 'All contributions', 'wpcredits-program-manager' ),
			esc_html__( 'Course completions are always milestone entries, so the faster filter reads roughly 40% fewer pages. Switch to all contributions only if WordPress.org changes and completions stop being found.', 'wpcredits-program-manager' )
		);

		$this->number_row( 'checker_max_pages', __( 'Maximum history pages per mentor', 'wpcredits-program-manager' ), $settings['checker_max_pages'], 1, 100, __( 'A mentor whose history is longer than this is reported as "could not check", never as "not completed" — a false negative would leave them waiting.', 'wpcredits-program-manager' ) );
		$this->number_row( 'checker_batch_size', __( 'Mentors per batch', 'wpcredits-program-manager' ), $settings['checker_batch_size'], 1, 25, __( 'Each mentor can cost several requests to WordPress.org, so smaller batches keep the screen responsive.', 'wpcredits-program-manager' ) );
		$this->number_row( 'checker_request_delay', __( 'Delay between requests (ms)', 'wpcredits-program-manager' ), $settings['checker_request_delay'], 0, 5000 );
		$this->number_row( 'checker_cache_ttl', __( 'Cache profile results for (seconds)', 'wpcredits-program-manager' ), $settings['checker_cache_ttl'], 0, MONTH_IN_SECONDS, __( 'Only settled answers are cached; a failed read is always retried. Set to 0 to disable.', 'wpcredits-program-manager' ) );

		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="checker_cron_enabled" value="1"%2$s> %3$s</label><br><label><input type="checkbox" name="checker_cron_promotes" value="1"%4$s> %5$s</label><p class="description">%6$s</p></td></tr>',
			esc_html__( 'Weekly check', 'wpcredits-program-manager' ),
			checked( ! empty( $settings['checker_cron_enabled'] ), true, false ),
			esc_html__( 'Run the check automatically once a week', 'wpcredits-program-manager' ),
			checked( ! empty( $settings['checker_cron_promotes'] ), true, false ),
			esc_html__( 'Let the weekly check also promote mentors', 'wpcredits-program-manager' ),
			esc_html__( 'Both off by default. An unattended promotion writes to the shared Airtable base, so turn the second one on deliberately.', 'wpcredits-program-manager' )
		);

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * One number input row on the settings form.
	 *
	 * @param string $name        Field name.
	 * @param string $label       Field label.
	 * @param int    $value       Current value.
	 * @param int    $min         Minimum accepted value.
	 * @param int    $max         Maximum accepted value.
	 * @param string $description Optional help text.
	 */
	private function number_row( $name, $label, $value, $min, $max, $description = '' ) {
		printf(
			'<tr><th scope="row"><label for="wpcpm-%1$s">%2$s</label></th><td><input type="number" id="wpcpm-%1$s" name="%1$s" value="%3$d" min="%4$d" max="%5$d" step="1" class="small-text" />',
			esc_attr( $name ),
			esc_html( $label ),
			(int) $value,
			(int) $min,
			(int) $max
		);

		if ( $description ) {
			printf( '<p class="description">%s</p>', wp_kses( $description, array( 'code' => array() ) ) );
		}

		echo '</td></tr>';
	}

	/**
	 * One text input row on the settings form.
	 *
	 * @param string $name        Field name.
	 * @param string $label       Field label.
	 * @param string $value       Current value.
	 * @param string $description Optional help text; may contain <code> tags.
	 * @param string $type        Input type.
	 */
	private function text_row( $name, $label, $value, $description = '', $type = 'text' ) {
		printf(
			'<tr><th scope="row"><label for="wpcpm-%1$s">%2$s</label></th><td><input type="%3$s" id="wpcpm-%1$s" name="%1$s" value="%4$s" class="regular-text" autocomplete="off" />',
			esc_attr( $name ),
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( $value )
		);

		if ( $description ) {
			printf( '<p class="description">%s</p>', wp_kses( $description, array( 'code' => array() ) ) );
		}

		echo '</td></tr>';
	}
}
