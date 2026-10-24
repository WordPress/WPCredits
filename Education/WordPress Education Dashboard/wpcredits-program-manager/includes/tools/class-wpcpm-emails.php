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
 * The site's email, in three tabs: Templates, the plugin's emails as text an Administrator edits;
 * Signature and footer, the one block every plugin email ends with; and Log, every email the site
 * sent in the last 30 days.
 *
 * The tool draws the screen, its status line and the editor's three buttons (Save and Send me a
 * test, two buttons of one form, and Restore the original) and nothing more. What an email says,
 * and the edits, are the template registry's (`WPCPM_Mail_Templates`), which every email reads as
 * it is sent, so a site that filters this tool out of `wpcpm_tools` loses the screen and keeps
 * sending its emails as they were last saved. The recording, the table's creation and upgrade, the
 * daily cleanup and the privacy tools' hooks boot from the plugin's bootstrap
 * (`WPCPM_Mail_Log::init()`), so the log is kept all the same.
 *
 * Every link to the Log names its tab (`tab=log`), which the screen's filter form carries too; the
 * screen's own address opens Templates.
 *
 * The tool keeps one setting, Contact address in emails, which the footer names. Its Settings section
 * is drawn at the top of the Signature and footer tab, beside the footer, and `settings_url()` says
 * so, so a Save comes back there.
 */
class WPCPM_Emails extends WPCPM_Tool {

	/** The Templates tab, which the screen's own address opens. */
	const TAB_TEMPLATES = 'templates';

	/** The Signature and footer tab. */
	const TAB_FOOTER = 'footer';

	/** The Log's tab. */
	const TAB_LOG = 'log';

	/** Admin-post action of the editor's Save. */
	const ACTION_SAVE = 'wpcpm_email_save';

	/** Admin-post action of Restore the original. */
	const ACTION_RESTORE = 'wpcpm_email_restore';

	/** The field each of the two forms names its email in: a template's id, or the footer's. */
	const FIELD = 'wpcpm_email';

	/**
	 * The name of the Save form's second button, Send me a test, which posts `test` under it: the
	 * test is the Save form's own, so whatever is in the boxes reaches the handler.
	 */
	const THEN = 'wpcpm_then';

	/** The flash channel the buttons leave their notice on. */
	const FLASH = 'emails';

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
		return __( 'The plugin\'s emails you can edit, by area. One you have not edited is sent as the plugin ships it. Every email the plugin sends ends with the signature and footer. The Log holds every email the site sent in the last 30 days.', 'wpcredits-program-manager' );
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
	 * Hooks: the screen's stylesheet and the editor's two forms, Save (with Send me a test) and
	 * Restore the original. Nothing else: the log boots on its own, and every email reads the
	 * registry as it is sent.
	 */
	public function boot() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE, array( $this, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_RESTORE, array( $this, 'handle_restore' ) );
	}

	/**
	 * The screen's stylesheet, on this screen only. The Email field's script is enqueued by the
	 * field itself (`WPCPM_Dashboards::render_combo()`), and its rules are admin.css's, which every
	 * screen of the plugin loads (`WPCPM_Admin::enqueue_assets()`).
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
	 * The screen's tabs, in the bar's order.
	 *
	 * @return array<string,string> Slug to label.
	 */
	public static function tabs() {
		return array(
			self::TAB_TEMPLATES => __( 'Templates', 'wpcredits-program-manager' ),
			self::TAB_FOOTER    => __( 'Signature and footer', 'wpcredits-program-manager' ),
			self::TAB_LOG       => __( 'Log', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The tab the address asks for: one of `tabs()`, Templates for none or one the screen does not have.
	 *
	 * @return string
	 */
	public static function current_tab() {
		$tab = WPCPM_Request::key( 'tab' );

		return isset( self::tabs()[ $tab ] ) ? $tab : self::TAB_TEMPLATES;
	}

	/**
	 * Where an email is edited: a template's editor on the Templates tab, or the Signature and footer
	 * tab.
	 *
	 * @param string $id A template's id, or `WPCPM_Mail_Templates::FOOTER`.
	 * @return string
	 */
	public function editor_url( $id ) {
		if ( WPCPM_Mail_Templates::FOOTER === (string) $id ) {
			return add_query_arg( 'tab', self::TAB_FOOTER, $this->admin_url() );
		}

		return add_query_arg(
			array(
				'tab'  => self::TAB_TEMPLATES,
				'edit' => (string) $id,
			),
			$this->admin_url()
		);
	}

	/**
	 * The screen: its heading, the notice a button left, the tab bar, and the tab asked for.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$tab = self::current_tab();

		echo '<div class="wrap wpcpm-wrap wpcpm-emails">';
		printf( '<h1>%s</h1>', esc_html( $this->label() ) );

		$notice = WPCPM_Flash::take( self::FLASH );

		if ( is_array( $notice ) && isset( $notice['type'], $notice['text'] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( (string) $notice['type'] ), esc_html( (string) $notice['text'] ) );
		}

		WPCPM_Screen_Tabs::render( $this->page_slug(), self::tabs(), $tab );

		if ( self::TAB_FOOTER === $tab ) {
			$this->render_footer_tab();
		} elseif ( self::TAB_LOG === $tab ) {
			$this->render_log_tab();
		} else {
			$this->render_templates_tab();
		}

		echo '</div>';
	}

	/**
	 * The Templates tab: the editor of the email the address names, or the list of every template,
	 * grouped by area, each with who receives it, what sends it, where it stands and who edited it
	 * last.
	 */
	private function render_templates_tab() {
		$edit = WPCPM_Request::key( 'edit' );

		if ( '' !== $edit && null !== WPCPM_Mail_Templates::get( $edit ) ) {
			$this->render_editor( $edit );

			return;
		}

		printf( '<p class="description">%s</p>', esc_html__( 'The plugin\'s emails you can edit, by area. One you have not edited is sent as the plugin ships it. Every email the plugin sends ends with the signature and footer.', 'wpcredits-program-manager' ) );

		$by_area = array();

		foreach ( WPCPM_Mail_Templates::all() as $id => $entry ) {
			$by_area[ $entry['module'] ][ $id ] = $entry;
		}

		foreach ( WPCPM_Mail_Catalog::modules() as $module => $area ) {
			if ( empty( $by_area[ $module ] ) ) {
				continue;
			}

			// The area's table scrolls sideways in a box of its own on a screen narrower than its
			// columns (emails.css). The box is a tab stop, so a keyboard reaches it and its arrow keys
			// scroll it (Safari reaches no scroll box that holds no link or button), and a region named
			// by the area's heading, so a screen reader says what scrolls, as the Administrator
			// Dashboard's tables were made in 1.122.35. A tab stop at every width, as those are: an
			// attribute cannot follow the screen's width without a script, and the tab works without
			// one, so where the table fits it is one stop more (1.122.36).
			$title = 'wpcpm-emails-area-' . $module;

			printf( '<h2 id="%1$s">%2$s</h2>', esc_attr( $title ), esc_html( $area ) );
			printf( '<div class="wpcpm-emails__scroll" tabindex="0" role="region" aria-labelledby="%s"><table class="widefat striped wpcpm-emails__templates"><thead><tr>', esc_attr( $title ) );

			foreach ( array( __( 'Email', 'wpcredits-program-manager' ), __( 'Who receives it', 'wpcredits-program-manager' ), __( 'What sends it', 'wpcredits-program-manager' ), __( 'State', 'wpcredits-program-manager' ), __( 'Last edited', 'wpcredits-program-manager' ) ) as $column ) {
				printf( '<th scope="col">%s</th>', esc_html( $column ) );
			}

			printf( '<th scope="col"><span class="screen-reader-text">%s</span></th></tr></thead><tbody>', esc_html__( 'Edit', 'wpcredits-program-manager' ) );

			foreach ( $by_area[ $module ] as $id => $entry ) {
				$state = WPCPM_Mail_Templates::state( $id );

				printf(
					'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td><td>%5$s</td><td><a href="%6$s">%7$s<span class="screen-reader-text"> %8$s</span></a></td></tr>',
					esc_html( $entry['label'] ),
					esc_html( self::audience_label( $entry['audience'] ) ),
					esc_html( $entry['trigger'] ),
					self::state_chip( $state ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
					esc_html( self::edited_by( $state ) ),
					esc_url( $this->editor_url( $id ) ),
					esc_html__( 'Edit', 'wpcredits-program-manager' ),
					esc_html( $entry['label'] )
				);
			}

			echo '</tbody></table></div>';
		}
	}

	/**
	 * The Signature and footer tab: the tool's Settings section, with the contact address the footer
	 * names, then the one block every plugin email ends with, in the editor every template has.
	 */
	private function render_footer_tab() {
		if ( WPCPM_Flash::take( 'emails-contact-refused' ) ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'Contact address in emails was not saved: what was typed is not an email address. The one saved before is kept.', 'wpcredits-program-manager' ) );
		}

		$this->render_settings();
		$this->render_editor( WPCPM_Mail_Templates::FOOTER );
	}

	/**
	 * The tool's one setting, Contact address in emails, which the footer names.
	 *
	 * @return string[]
	 */
	public function settings_keys() {
		return array( 'mail_contact' );
	}

	/**
	 * The Settings section is on the Signature and footer tab, beside the footer that uses it.
	 *
	 * @return string
	 */
	public function settings_url() {
		return add_query_arg( 'tab', self::TAB_FOOTER, $this->admin_url() );
	}

	/**
	 * What the Settings section says under its heading.
	 *
	 * @return array
	 */
	protected function settings_intro() {
		return array(
			'sentence' => __( 'The address every email names for questions, in its signature and footer.', 'wpcredits-program-manager' ),
			'details'  => '',
		);
	}

	/**
	 * The one row: Contact address in emails.
	 */
	protected function render_settings_rows() {
		WPCPM_Settings_Rows::text_row(
			'mail_contact',
			__( 'Contact address in emails', 'wpcredits-program-manager' ),
			(string) WPCPM_Settings::get_value( 'mail_contact', '' ),
			__( 'A mailbox the Administrators read; left empty, the footer leaves out the sentence that names it.', 'wpcredits-program-manager' ),
			'email'
		);
	}

	/**
	 * Who receives an email, as the Templates tab says it.
	 *
	 * @param string $audience A recipient type, or '' for an email to several kinds of people.
	 * @return string
	 */
	private static function audience_label( $audience ) {
		$types = WPCPM_Mail_Catalog::types();

		return isset( $types[ $audience ] ) ? $types[ $audience ] : __( 'The people it concerns', 'wpcredits-program-manager' );
	}

	/**
	 * An email's state as a chip: its words, marked for the eye by its kind.
	 *
	 * @param array $state What `WPCPM_Mail_Templates::state()` answered.
	 * @return string The chip's markup, escaped.
	 */
	private static function state_chip( array $state ) {
		return sprintf(
			'<span class="wpcpm-emails__state wpcpm-emails__state--%1$s">%2$s</span>',
			esc_attr( (string) $state['state'] ),
			esc_html( WPCPM_Mail_Templates::state_label( $state ) )
		);
	}

	/**
	 * Who saved an email's edit and when, in the site's time zone, or that it was never edited.
	 *
	 * @param array $state What `WPCPM_Mail_Templates::state()` answered.
	 * @return string
	 */
	private static function edited_by( array $state ) {
		if ( WPCPM_Mail_Templates::STATE_SHIPPED === $state['state'] ) {
			return __( 'Not edited', 'wpcredits-program-manager' );
		}

		$user = $state['by'] ? get_user_by( 'id', (int) $state['by'] ) : false;
		$name = $user instanceof WP_User ? (string) $user->display_name : __( 'An account since removed', 'wpcredits-program-manager' );

		return sprintf(
			/* translators: 1: who saved the edit, 2: when. */
			__( '%1$s, %2$s', 'wpcredits-program-manager' ),
			$name,
			wp_date( 'j M Y, H:i', (int) $state['at'] )
		);
	}

	/**
	 * One email's editor: what it is and where it stands, a Subject (none for the footer) and a Body
	 * with Save and Send me a test, its placeholders, a preview filled with the example values and
	 * ending with the footer, then, for an edited email, Restore the original and the shipped text.
	 *
	 * Send me a test is the Save form's second submit button, so the handler reads the boxes and
	 * sends the test only while they hold what the editor drew; the form's busy word, "Working",
	 * holds for either button.
	 *
	 * The boxes hold, in this order: what a refused save kept of the typing, the edit as stored, read
	 * back as it was typed, or the shipped text.
	 *
	 * @param string $id A template's id, or `WPCPM_Mail_Templates::FOOTER`.
	 */
	private function render_editor( $id ) {
		$footer = WPCPM_Mail_Templates::FOOTER === $id;
		$entry  = $footer ? null : WPCPM_Mail_Templates::get( $id );
		$label  = $footer ? __( 'Signature and footer', 'wpcredits-program-manager' ) : $entry['label'];
		$state  = WPCPM_Mail_Templates::state( $id );
		$edit   = WPCPM_Mail_Templates::edit( $id );
		$kept   = WPCPM_Typed_Text::kept( self::kept_form( $id ) );
		$ships  = $footer ? array(
			'subject' => '',
			'body'    => WPCPM_Mail_Templates::footer_shipped(),
		) : array(
			'subject' => $entry['subject'],
			'body'    => $entry['body'],
		);

		if ( isset( $kept['body'] ) && is_string( $kept['body'] ) ) {
			$subject = isset( $kept['subject'] ) && is_string( $kept['subject'] ) ? WPCPM_Typed_Text::kept_attr( $kept['subject'] ) : '';
			$body    = $kept['body'];
		} elseif ( null !== $edit ) {
			$subject = WPCPM_Typed_Text::attr_text( $edit['subject'] );
			$body    = WPCPM_Typed_Text::typed_text( $edit['body'] );
		} else {
			$subject = WPCPM_Typed_Text::kept_attr( $ships['subject'] );
			$body    = $ships['body'];
		}

		if ( ! $footer ) {
			printf( '<p><a href="%1$s">%2$s</a></p>', esc_url( add_query_arg( 'tab', self::TAB_TEMPLATES, $this->admin_url() ) ), esc_html__( 'All templates', 'wpcredits-program-manager' ) );
		}

		echo '<div class="wpcpm-card wpcpm-emails__editor">';
		printf( '<h2>%s</h2>', esc_html( $label ) );

		if ( $footer ) {
			printf(
				'<p class="description">%s</p>',
				sprintf(
					/* translators: %s: the separator line, two hyphens and a space, shown as code. */
					esc_html__( 'Every email the plugin sends ends with this block, after a line holding %s. It is not added to the emails WordPress and the Two Factor plugin send themselves.', 'wpcredits-program-manager' ),
					'<code>-- </code>'
				)
			);
		} else {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: who receives the email, such as Student, 2: what sends it, a sentence. */
						__( 'To: %1$s. %2$s', 'wpcredits-program-manager' ),
						self::audience_label( $entry['audience'] ),
						$entry['trigger']
					)
				)
			);
		}

		printf(
			'<p>%1$s %2$s</p>',
			esc_html__( 'State:', 'wpcredits-program-manager' ),
			self::state_chip( $state ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
		);

		if ( WPCPM_Mail_Templates::STATE_SET_ASIDE === $state['state'] ) {
			printf( '<div class="notice notice-warning inline"><p>%s</p></div>', esc_html( $state['reason'] . ' ' . __( 'The shipped text is sent until the edit is saved again in a form this email can use.', 'wpcredits-program-manager' ) ) );
		} elseif ( WPCPM_Mail_Templates::STATE_CHANGED === $state['state'] ) {
			printf( '<div class="notice notice-info inline"><p>%s</p></div>', esc_html__( 'An update changed the shipped text since this edit was saved. Your edit is still the one sent; the new shipped text is under the buttons, to compare. Saving your edit again clears this.', 'wpcredits-program-manager' ) );
		}

		printf(
			'<form method="post" action="%1$s" class="wpcpm-emails__form" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Working', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_SAVE );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SAVE ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::FIELD ), esc_attr( $id ) );

		if ( ! $footer ) {
			printf(
				'<p><label for="wpcpm-email-subject">%1$s</label><br /><input type="text" id="wpcpm-email-subject" name="subject" class="large-text" maxlength="%2$d" value="%3$s" /></p>',
				esc_html__( 'Subject', 'wpcredits-program-manager' ),
				(int) WPCPM_Mail_Templates::SUBJECT_MAX,
				esc_attr( $subject )
			);
		}

		printf(
			'<p><label for="wpcpm-email-body">%1$s</label><br /><textarea id="wpcpm-email-body" name="body" class="large-text code" rows="%2$d" maxlength="%3$d">%4$s</textarea></p>',
			esc_html( $footer ? __( 'Signature and footer', 'wpcredits-program-manager' ) : __( 'Body', 'wpcredits-program-manager' ) ),
			$footer ? 5 : 18,
			(int) WPCPM_Mail_Templates::BODY_MAX,
			esc_textarea( $body )
		);

		// Enter in the Subject presses the first button, Save.
		printf(
			'<p><button type="submit" class="button button-primary">%1$s</button> <button type="submit" class="button" name="%2$s" value="test">%3$s</button></p>',
			esc_html__( 'Save', 'wpcredits-program-manager' ),
			esc_attr( self::THEN ),
			esc_html__( 'Send me a test', 'wpcredits-program-manager' )
		);
		printf( '<p class="description">%s</p>', esc_html__( 'Send me a test sends the email as it is saved, filled with the example values and marked [Test], to your own address and to nobody else. Save what you typed first: pressed with typing not saved yet, it sends nothing.', 'wpcredits-program-manager' ) );
		echo '</form>';

		$this->render_placeholders( $footer ? WPCPM_Mail_Templates::footer_placeholders() : $entry['placeholders'] );
		$this->render_preview( $id );

		if ( null !== $edit ) {
			echo '<div class="wpcpm-emails__buttons">';
			$this->render_button( $id, self::ACTION_RESTORE, __( 'Restore the original', 'wpcredits-program-manager' ), __( 'Restoring', 'wpcredits-program-manager' ), __( 'Restore the original? Your edit is dropped, and the shipped text is sent from now on.', 'wpcredits-program-manager' ) );
			echo '</div>';

			printf( '<details class="wpcpm-emails__shipped"%s><summary>%s</summary>', WPCPM_Mail_Templates::STATE_CHANGED === $state['state'] ? ' open' : '', esc_html__( 'The shipped text', 'wpcredits-program-manager' ) );

			if ( ! $footer ) {
				printf( '<p><strong>%1$s</strong> %2$s</p>', esc_html__( 'Subject:', 'wpcredits-program-manager' ), esc_html( $ships['subject'] ) );
			}

			printf( '<pre class="wpcpm-emails__text">%s</pre></details>', esc_html( $ships['body'] ) );
		}

		echo '</div>';
	}

	/**
	 * The placeholders an email may use, each with what it means and its example, the required ones
	 * marked.
	 *
	 * @param array $placeholders Name to what the registry holds for each.
	 */
	private function render_placeholders( array $placeholders ) {
		printf( '<h3>%s</h3>', esc_html__( 'Placeholders', 'wpcredits-program-manager' ) );
		printf( '<p class="description">%s</p>', esc_html__( 'Each one is replaced by its value as the email is sent. A sentence whose placeholders are all empty is left out, and so is a line left with nothing on it, together with a line ending in a colon directly above it that holds no placeholder. A sentence ends at a full stop followed by a space; a question or exclamation mark does not end one.', 'wpcredits-program-manager' ) );
		echo '<table class="widefat striped wpcpm-emails__placeholders"><thead><tr>';

		foreach ( array( __( 'Placeholder', 'wpcredits-program-manager' ), __( 'What it means', 'wpcredits-program-manager' ), __( 'Example', 'wpcredits-program-manager' ) ) as $column ) {
			printf( '<th scope="col">%s</th>', esc_html( $column ) );
		}

		echo '</tr></thead><tbody>';

		$examples = WPCPM_Mail_Templates::examples_of( $placeholders );

		foreach ( $placeholders as $name => $about ) {
			printf(
				'<tr><td><code>{%1$s}</code>%2$s</td><td>%3$s</td><td>%4$s</td></tr>',
				esc_html( $name ),
				empty( $about['required'] ) ? '' : ' <span class="wpcpm-emails__required">' . esc_html__( 'Required', 'wpcredits-program-manager' ) . '</span>',
				esc_html( (string) $about['means'] ),
				'' === $examples[ $name ] ? esc_html__( 'Empty here', 'wpcredits-program-manager' ) : esc_html( WPCPM_Typed_Text::one_line( $examples[ $name ] ) )
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * The email as a recipient of its audience would read it: the text that goes out, filled with
	 * the example values, and the footer. For the footer, a short line above it, and under the
	 * preview the note on whose footer it shows (`render_dashboards_note()`).
	 *
	 * @param string $id A template's id, or `WPCPM_Mail_Templates::FOOTER`.
	 */
	private function render_preview( $id ) {
		if ( WPCPM_Mail_Templates::FOOTER === $id ) {
			$context = 'footer-test';
			$mail    = array(
				'subject' => '',
				'body'    => __( 'The text of the email.', 'wpcredits-program-manager' ),
			);
		} else {
			$context = $id;
			$mail    = WPCPM_Mail_Templates::render( $id, WPCPM_Mail_Templates::example_values( $id ) );
		}

		$body = WPCPM_Mail_Templates::add_footer( $mail['body'], null, $context, WPCPM_Mail_Templates::footer_examples( $context ) );

		printf( '<h3>%s</h3>', esc_html__( 'Preview', 'wpcredits-program-manager' ) );
		printf( '<p class="description">%s</p>', esc_html__( 'As it is sent now, with the example values and the signature and footer.', 'wpcredits-program-manager' ) );
		echo '<div class="wpcpm-emails__preview">';

		if ( '' !== $mail['subject'] ) {
			printf( '<p><strong>%1$s</strong> %2$s</p>', esc_html__( 'Subject:', 'wpcredits-program-manager' ), esc_html( $mail['subject'] ) );
		}

		printf( '<pre class="wpcpm-emails__text">%s</pre></div>', esc_html( $body ) );

		if ( WPCPM_Mail_Templates::FOOTER === $id ) {
			$this->render_dashboards_note();
		}
	}

	/**
	 * Under the footer's preview: that the preview and the test show the footer as an Administrator
	 * gets it, what `{dashboard_link}` links to for each kind of person whose page is published, by
	 * the page's full name and address, unless an email names that page already, as every
	 * invitation does, and that somebody with no account gets none (since 1.122.37, after
	 * WordPress/WPCredits#255, where an Administrator Dashboard in a footer test read as the address
	 * everybody gets).
	 */
	private function render_dashboards_note() {
		$pages = WPCPM_Mail_Templates::dashboard_pages();

		echo '<div class="wpcpm-emails__note"><p class="description">';
		echo esc_html__( 'The preview and Send me a test show the footer as an Administrator gets it.', 'wpcredits-program-manager' );

		if ( array() !== $pages ) {
			echo ' ';
			printf(
				/* translators: %s: the placeholder {dashboard_link}, shown as code. */
				esc_html__( 'In each email, %s links to the recipient\'s own page, unless the email names that page already:', 'wpcredits-program-manager' ),
				'<code>{dashboard_link}</code>'
			);
			echo '</p><ul class="wpcpm-emails__dashboards">';

			foreach ( $pages as $page ) {
				printf(
					'<li>%s</li>',
					sprintf(
						/* translators: 1: a kind of person, such as Students, 2: the full name of their page, such as Student Report Card, 3: the page's address. */
						esc_html__( '%1$s: %2$s, %3$s', 'wpcredits-program-manager' ),
						esc_html( $page['who'] ),
						esc_html( $page['page'] ),
						'<a href="' . esc_url( $page['url'] ) . '">' . esc_html( $page['url'] ) . '</a>'
					)
				);
			}

			echo '</ul><p class="description">';
		} else {
			echo ' ';
		}

		echo esc_html__( 'Somebody with no account, such as an applicant, gets no dashboard link, and its line is left out.', 'wpcredits-program-manager' );
		echo '</p></div>';
	}

	/**
	 * A button of the editor's that is a form of its own posting the email's id, Restore the
	 * original's: locked at its first press, saying it is busy, and, given a question, asking it
	 * first.
	 *
	 * @param string $id       The email's id.
	 * @param string $action   The admin-post action.
	 * @param string $label    The button's words.
	 * @param string $busy     What it says while the press is on its way.
	 * @param string $question A question to ask first, or '' for none.
	 */
	private function render_button( $id, $action, $label, $busy, $question ) {
		printf(
			'<form method="post" action="%1$s" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="%2$s"%3$s>',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $busy ),
			'' === $question ? '' : ' data-wpcpm-confirm="' . esc_attr( $question ) . '"'
		);
		// The nonce in a field with an id of its own: `wp_nonce_field()` names its field's id after
		// the field, which two forms on one screen would give to two elements.
		printf( '<input type="hidden" id="%1$s" name="_wpnonce" value="%2$s" />', esc_attr( $action . '-nonce' ), esc_attr( wp_create_nonce( $action ) ) );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::FIELD ), esc_attr( $id ) );
		printf( '<button type="submit" class="button">%s</button>', esc_html( $label ) );
		echo '</form> ';
	}

	/**
	 * The name a refused save of an email keeps its typing under (`WPCPM_Typed_Text::keep()`).
	 *
	 * @param string $id The email's id.
	 * @return string
	 */
	private static function kept_form( $id ) {
		return 'email:' . $id;
	}

	/**
	 * The email a press names, when it is one the screen edits: a template of the registry's, or the
	 * footer; '' for anything else.
	 *
	 * @return string
	 */
	private static function posted_email() {
		$id = WPCPM_Request::posted_key( self::FIELD );

		return WPCPM_Mail_Templates::FOOTER === $id || null !== WPCPM_Mail_Templates::get( $id ) ? $id : '';
	}

	/**
	 * What each of the two forms asks first: the right to manage the program, then the nonce.
	 *
	 * @param string $action The button's admin-post action.
	 */
	private static function verify( $action ) {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		check_admin_referer( $action );
	}

	/**
	 * Queue the notice a press leaves, and go back to the email's editor, or to the Templates tab for
	 * an email the screen does not edit.
	 *
	 * @param string $id   The email, or ''.
	 * @param string $type The notice's kind: success, info or error.
	 * @param string $text Its sentence.
	 */
	private function back( $id, $type, $text ) {
		WPCPM_Flash::set(
			self::FLASH,
			array(
				'type' => $type,
				'text' => $text,
			)
		);

		wp_safe_redirect( '' === $id ? add_query_arg( 'tab', self::TAB_TEMPLATES, $this->admin_url() ) : $this->editor_url( $id ) );
		exit;
	}

	/**
	 * Save: store the edit, or refuse it, store nothing, and keep what was typed for the editor to
	 * draw again, as every form of the plugin keeps a refused typing. Pressed with its second
	 * button, Send me a test (`THEN`), it stores nothing and sends the test instead
	 * (`send_test_of()`).
	 */
	public function handle_save() {
		self::verify( self::ACTION_SAVE );

		$id   = self::posted_email();
		$test = 'test' === WPCPM_Request::posted_key( self::THEN );

		if ( '' === $id ) {
			$this->back( '', 'error', $test ? __( 'Nothing was sent: the site has no such email.', 'wpcredits-program-manager' ) : __( 'Nothing was saved: the site has no such email.', 'wpcredits-program-manager' ) );
		}

		$footer  = WPCPM_Mail_Templates::FOOTER === $id;
		$subject = $footer ? null : WPCPM_Request::posted_raw( 'subject' );
		$body    = WPCPM_Request::posted_raw( 'body' );

		WPCPM_Typed_Text::forget( self::kept_form( $id ) );

		if ( $test ) {
			$this->send_test_of( $id, $subject, $body );
		}

		$saved = WPCPM_Mail_Templates::save( $id, $subject, $body, get_current_user_id() );

		if ( is_wp_error( $saved ) ) {
			self::keep_typing( $id, $subject, $body );

			$this->back( $id, 'error', $saved->get_error_message() );
		}

		if ( WPCPM_Mail_Templates::STATE_SHIPPED === WPCPM_Mail_Templates::state( $id )['state'] ) {
			$this->back( $id, 'success', __( 'Saved. It reads as the shipped text, so it is sent as shipped.', 'wpcredits-program-manager' ) );
		}

		$this->back( $id, 'success', $footer ? __( 'Saved. Every email ends with the signature and footer as you wrote it from now on.', 'wpcredits-program-manager' ) : __( 'Saved. The email is sent as you wrote it from now on.', 'wpcredits-program-manager' ) );
	}

	/**
	 * Restore the original: drop the edit, after the question the button asks.
	 */
	public function handle_restore() {
		self::verify( self::ACTION_RESTORE );

		$id = self::posted_email();

		if ( '' === $id ) {
			$this->back( '', 'error', __( 'Nothing was restored: the site has no such email.', 'wpcredits-program-manager' ) );
		}

		if ( WPCPM_Mail_Templates::restore( $id ) ) {
			$this->back( $id, 'success', __( 'The original is back: the shipped text is sent from now on.', 'wpcredits-program-manager' ) );
		}

		$this->back( $id, 'info', __( 'There was no edit to restore: the shipped text is already the one sent.', 'wpcredits-program-manager' ) );
	}

	/**
	 * Send me a test, the Save form's second button: the email as it is saved, filled with its
	 * example values, to the address of the Administrator who pressed and to nobody else, whatever
	 * the press posted. Only while the boxes read as the editor drew them: typing not saved yet is
	 * kept, as a refused save keeps it, and nothing is sent, since the test would show what no
	 * recipient gets.
	 *
	 * @param string      $id      The email's id, or `WPCPM_Mail_Templates::FOOTER`.
	 * @param string|null $subject The subject as posted, unslashed and not cleaned; null for the footer.
	 * @param string      $body    The body as posted, unslashed and not cleaned.
	 */
	private function send_test_of( $id, $subject, $body ) {
		if ( ! self::as_drawn( $id, $subject, $body ) ) {
			self::keep_typing( $id, $subject, $body );

			$this->back( $id, 'error', __( 'Nothing was sent: what you typed is not saved yet. Save it first, then send the test.', 'wpcredits-program-manager' ) );
		}

		$me = wp_get_current_user();

		if ( $me instanceof WP_User && $me->exists() && WPCPM_Mail_Templates::send_test( $id, $me ) ) {
			$this->back(
				$id,
				'success',
				sprintf(
					/* translators: %s: the Administrator's own email address. */
					__( 'The test is on its way to your own address, %s.', 'wpcredits-program-manager' ),
					$me->user_email
				)
			);
		}

		$this->back( $id, 'error', __( 'The test could not be sent: whatever handles mail on this site refused it, so the real email would not arrive either.', 'wpcredits-program-manager' ) );
	}

	/**
	 * Whether the boxes were posted as the editor drew them, when no typing was kept: the edit as
	 * stored, or the shipped text while there is none. What was posted is cleaned as a save would
	 * clean it, and the two are compared with each line break read as one
	 * (`WPCPM_Typed_Text::same_lines()`), the white space at either end let go.
	 *
	 * Typing the cleaner would take more than white space from (a tag, a percent octet) is never
	 * as drawn, even when the cleaned text reads as the stored one: a save of it is refused
	 * (`WPCPM_Typed_Text::cleaner_loses()`), so it is typing not saved yet, which the caller keeps.
	 *
	 * @param string      $id      The email's id, or `WPCPM_Mail_Templates::FOOTER`.
	 * @param string|null $subject The subject as posted; null for the footer.
	 * @param string      $body    The body as posted.
	 * @return bool
	 */
	private static function as_drawn( $id, $subject, $body ) {
		if ( ( null !== $subject && WPCPM_Typed_Text::cleaner_loses( $subject, 'line' ) ) || WPCPM_Typed_Text::cleaner_loses( $body, 'lines' ) ) {
			return false;
		}

		$edit = WPCPM_Mail_Templates::edit( $id );

		if ( null !== $edit ) {
			$drawn = array(
				'subject' => $edit['subject'],
				'body'    => $edit['body'],
			);
		} elseif ( WPCPM_Mail_Templates::FOOTER === $id ) {
			$drawn = array(
				'subject' => '',
				'body'    => WPCPM_Mail_Templates::footer_shipped(),
			);
		} else {
			$entry = WPCPM_Mail_Templates::get( $id );
			$drawn = array(
				'subject' => $entry['subject'],
				'body'    => $entry['body'],
			);
		}

		return WPCPM_Typed_Text::same_lines( sanitize_text_field( (string) $subject ), trim( $drawn['subject'] ) )
			&& WPCPM_Typed_Text::same_lines( sanitize_textarea_field( (string) $body ), trim( $drawn['body'] ) );
	}

	/**
	 * Keep what was typed in the editor's boxes for the editor to draw again, once, as every form of
	 * the plugin keeps a refused typing.
	 *
	 * @param string      $id      The email's id, or `WPCPM_Mail_Templates::FOOTER`.
	 * @param string|null $subject The subject as posted; null for the footer.
	 * @param string      $body    The body as posted.
	 */
	private static function keep_typing( $id, $subject, $body ) {
		WPCPM_Typed_Text::keep(
			self::kept_form( $id ),
			array(
				'subject' => (string) $subject,
				'body'    => $body,
			),
			array(
				'subject' => WPCPM_Mail_Templates::SUBJECT_MAX,
				'body'    => WPCPM_Mail_Templates::BODY_MAX,
			)
		);
	}

	/**
	 * The Log tab: the filters, a form of their own, then the count, the table and what its main
	 * status promises.
	 *
	 * The filter form is a GET form closed before the table, and its search box is its own field
	 * (`s`) rather than the table's `search_box()`, which core draws only while there is a search or
	 * a row: so the box never disappears, and no nonce or referer of the table's joins the filters'
	 * address. The table's page links carry the filters, which are the address's.
	 */
	private function render_log_tab() {
		if ( ! WPCPM_Mail_Log::exists() ) {
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html__( 'The email log is not ready yet. It is created the next time a page of the site loads; if this stays, tell whoever looks after the site.', 'wpcredits-program-manager' ) );

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

		// The Email filter is the one long list: one field that drops down, takes typing and narrows,
		// dressed by admin.css under the wrapper every such field in wp-admin is drawn in.
		/* translators: %s: how many emails the Email filter's list shows, as a number. */
		$count = __( 'Emails in the list: %s', 'wpcredits-program-manager' );

		echo '<div class="wpcpm-emails__filter wpcpm-emails__email wpcpm-admin-combo">';
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
