<?php
/**
 * The page people ask the handbook questions on.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A private question box over the synced handbook.
 *
 * **The question travels as a query argument, not a form post.** Asking a question changes
 * nothing, so it is a GET: the answer survives a reload, can be sent to somebody, and needs
 * no nonce, no redirect and no one-shot message. The same reasoning the calendar uses for
 * which month it is showing.
 *
 * **Logged in only, and narrower than that by default.** The page is gated by capability
 * before anything is rendered, and the shortcode refuses to draw for a visitor even if
 * somebody drops it on a public page by accident — a private handbook assistant that leaks
 * because the page was set to public would be the whole feature failing at once.
 */
class WPCPM_Handbook_Assistant {

	const SHORTCODE = 'wpcpm_handbook_assistant';
	const BLOCK     = 'wpcpm/handbook-assistant';
	const OPT_PAGE  = 'wpcpm_handbook_page_id';
	const STYLE     = 'wpcpm-handbook';
	const SCRIPT    = 'wpcpm-handbook';

	/** Query argument carrying the question. */
	const ARG = 'wpcpm_ask';

	/** Longest question accepted. */
	const MAX_LENGTH = 400;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		// The panel is printed once per page, at the end, for anybody who may use it. The
		// launcher that opens it is somewhere else entirely — in the theme's header — and
		// the two only know about each other through a data attribute.
		add_action( 'wp_footer', array( __CLASS__, 'render_panel' ) );
	}

	/**
	 * Whether this page should carry the panel and its launcher.
	 *
	 * Public because the theme asks it: the header renders no launcher when there would be
	 * nothing to open, and the theme must not have to reimplement the access rules to know.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return ! is_admin() && self::user_can_ask();
	}

	/**
	 * Register the script and stylesheet, and load them where the assistant can be used.
	 */
	public static function enqueue() {
		if ( ! self::is_available() ) {
			return;
		}

		wp_enqueue_style( self::STYLE );

		wp_enqueue_script(
			self::SCRIPT,
			WPCPM_PLUGIN_URL . 'assets/js/handbook.js',
			array(),
			WPCPM_VERSION,
			true
		);

		wp_localize_script(
			self::SCRIPT,
			'wpcpmHandbook',
			array(
				'endpoint' => esc_url_raw( rest_url( 'wpcpm/v1/handbook/ask' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'strings'  => array(
					'thinking'  => __( 'Searching the WordPress documentation…', 'wpcredits-program-manager' ),
					'patience'  => __( 'Still reading. A thorough answer can take half a minute.', 'wpcredits-program-manager' ),
					/* translators: %s: number of seconds elapsed. */
					'elapsed'   => __( '%ss', 'wpcredits-program-manager' ),
					'slow'      => __( 'That took too long and was given up on. Trying again, or asking something narrower, usually works.', 'wpcredits-program-manager' ),
					'failed'    => __( 'That did not work. Try again in a moment.', 'wpcredits-program-manager' ),
					'sources'   => __( 'Pages it read', 'wpcredits-program-manager' ),
					'unsourced' => __( 'This answer does not cite any WordPress documentation, so it could not be checked against it. Treat it as unconfirmed.', 'wpcredits-program-manager' ),
					'empty'     => __( 'Type a question first.', 'wpcredits-program-manager' ),
				),
			)
		);
	}

	/**
	 * Answer a question over REST.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public static function rest_ask( $request ) {
		$question = trim( mb_substr( (string) $request->get_param( 'question' ), 0, self::MAX_LENGTH ) );

		if ( '' === $question ) {
			return new WP_REST_Response(
				array(
					'text'    => __( 'Type a question first.', 'wpcredits-program-manager' ),
					'sources' => array(),
				),
				200
			);
		}

		$answer = WPCPM_Handbook_Answer::ask( $question );

		return new WP_REST_Response(
			array(
				// Rendered here rather than in the browser: the same Markdown pass, `wpautop`
				// then `wp_kses_post` the page uses, so the panel cannot be the one place
				// where a provider's output reaches the DOM unfiltered.
				'html'      => wp_kses_post( wpautop( WPCPM_Handbook_Answer::to_html( $answer['text'] ) ) ),
				'notice'    => $answer['notice'],
				'generated' => (bool) $answer['generated'],
				// The reader is told when an answer cites nothing from the sites it was
				// supposed to use. Without a local copy this warning is the only thing
				// standing between them and a confident answer from somebody's blog.
				'unsourced' => ! empty( $answer['unsourced'] ),
				'sources'   => $answer['sources'],
			),
			200
		);
	}

	/**
	 * Who may call the endpoint.
	 *
	 * A wrapper rather than pointing the permission callback at `user_can_ask()` directly:
	 * REST hands the callback the request object, which that method would take for a user.
	 *
	 * @return bool
	 */
	public static function rest_permission() {
		return self::user_can_ask();
	}

	/**
	 * Register the answering endpoint.
	 */
	public static function register_route() {
		register_rest_route(
			'wpcpm/v1',
			'/handbook/ask',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_ask' ),
				'permission_callback' => array( __CLASS__, 'rest_permission' ),
				'args'                => array(
					'question' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * The slide-over panel, printed once per page.
	 *
	 * Server-rendered and hidden rather than built in JavaScript, so the shell exists in the
	 * markup, is translated on the server, and is styled before the script has run.
	 */
	public static function render_panel() {
		if ( ! self::is_available() ) {
			return;
		}

		?>
<div class="wpcpm-hb-panel" id="wpcpm-hb-panel" hidden>
	<div class="wpcpm-hb-panel__scrim" data-wpcpm-handbook-close></div>
	<div class="wpcpm-hb-panel__sheet" role="dialog" aria-modal="true" aria-labelledby="wpcpm-hb-panel-title">
		<div class="wpcpm-hb-panel__head">
			<h2 class="wpcpm-hb-panel__title" id="wpcpm-hb-panel-title"><?php esc_html_e( 'Need help?', 'wpcredits-program-manager' ); ?></h2>
			<button type="button" class="wpcpm-hb-panel__close" data-wpcpm-handbook-close aria-label="<?php esc_attr_e( 'Close', 'wpcredits-program-manager' ); ?>">&times;</button>
		</div>
		<form class="wpcpm-hb-panel__form" data-wpcpm-handbook-form>
			<label class="screen-reader-text" for="wpcpm-hb-panel-input"><?php esc_html_e( 'Your question', 'wpcredits-program-manager' ); ?></label>
			<input type="search" id="wpcpm-hb-panel-input" class="wpcpm-hb-panel__input" maxlength="<?php echo (int) self::MAX_LENGTH; ?>" autocomplete="off"
				placeholder="<?php esc_attr_e( 'How does the WordPress Credits Program work?', 'wpcredits-program-manager' ); ?>" />
			<button type="submit" class="wpcpm-button"><?php esc_html_e( 'Ask', 'wpcredits-program-manager' ); ?></button>
		</form>
		<div class="wpcpm-hb-panel__body" data-wpcpm-handbook-body aria-live="polite">
			<p class="wpcpm-hb-panel__hint">
				<?php esc_html_e( 'Ask about the program, or about WordPress itself.', 'wpcredits-program-manager' ); ?>
			</p>
		</div>
		<p class="wpcpm-hb-panel__foot">
			<?php
			// No branch on whether a provider is configured. There is no longer a version of
			// this that keeps the question on the site — without a provider there is no answer
			// at all, so the only true thing to say is that questions leave.
			esc_html_e( 'NOTE: Your question is sent to an external AI service, which searches the WordPress documentation to answer it. Do not type anything confidential.', 'wpcredits-program-manager' );
			?>
		</p>
	</div>
</div>
		<?php
	}

	/**
	 * Register the shortcode, block and stylesheet.
	 */
	public static function register() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );

		wp_register_style(
			self::STYLE,
			WPCPM_PLUGIN_URL . 'assets/css/handbook.css',
			array(),
			WPCPM_VERSION
		);

		if ( function_exists( 'register_block_type' ) ) {
			register_block_type(
				self::BLOCK,
				array(
					'api_version'     => 3,
					'title'           => __( 'Need help?', 'wpcredits-program-manager' ),
					'category'        => 'widgets',
					'render_callback' => array( __CLASS__, 'render' ),
					'supports'        => array( 'html' => false ),
				)
			);
		}
	}

	/**
	 * Who may use the assistant.
	 *
	 * @param int|WP_User|null $user Optional user; defaults to the current user.
	 * @return bool
	 */
	public static function user_can_ask( $user = null ) {
		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		if ( ! WPCPM_Handbook::is_enabled() ) {
			return false;
		}

		$access = (string) WPCPM_Settings::get_value( 'handbook_access', 'mentor' );

		if ( 'manage' === $access ) {
			return user_can( $user->ID, WPCPM_Roles::CAP_MANAGE );
		}

		if ( 'any' === $access ) {
			return true;
		}

		if ( 'program' === $access ) {
			// Everybody the program is for. Deliberately the same recognition the dashboards
			// use, so somebody linked to an Airtable record without holding the role is not
			// turned away from the handbook when they can see everything else.
			return WPCPM_Students_Dashboard::is_student( $user )
				|| WPCPM_Mentors_Dashboard::is_mentor( $user )
				|| WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_INSTITUTION )
				|| WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_SPONSOR )
				|| user_can( $user->ID, WPCPM_Roles::CAP_MANAGE );
		}

		// The default. The handbook is written for the people running the program — most of it
		// describes work a student never does — so mentors and program managers, and nobody
		// else, until somebody widens it deliberately.
		return WPCPM_Mentors_Dashboard::is_mentor( $user )
			|| user_can( $user->ID, WPCPM_Roles::CAP_MANAGE );
	}

	/**
	 * Whether the configured audience includes students.
	 *
	 * The Resources section on the Student Report Card is *for students*, so it follows this
	 * rather than "can the person looking at it ask" — which on the default setting would put
	 * it on a student's page for a program manager inspecting them, and never for the student
	 * whose page it is.
	 *
	 * @return bool
	 */
	public static function audience_includes_students() {
		return in_array(
			(string) WPCPM_Settings::get_value( 'handbook_access', 'mentor' ),
			array( 'program', 'any' ),
			true
		);
	}

	/**
	 * The question on this request, if any.
	 *
	 * @return string
	 */
	public static function question() {
		$question = WPCPM_Request::text( self::ARG );

		return trim( mb_substr( $question, 0, self::MAX_LENGTH ) );
	}

	/**
	 * Render the assistant.
	 *
	 * @return string
	 */
	public static function render() {
		// Switched off is switched off: nothing rendered, for anybody, including a manager.
		// The page itself is unpublished as well — see `apply_visibility()` — so this only
		// matters where the shortcode has been dropped somewhere else.
		if ( ! WPCPM_Handbook::is_enabled() ) {
			return '';
		}

		if ( ! is_user_logged_in() ) {
			return self::notice( __( 'This is for people on the program. Please log in.', 'wpcredits-program-manager' ) );
		}

		if ( ! self::user_can_ask() ) {
			return self::notice( __( 'Your account does not have access to this.', 'wpcredits-program-manager' ) );
		}

		wp_enqueue_style( self::STYLE );

		$question = self::question();

		$out  = '<div class="wpcpm-handbook">';
		$out .= self::render_form( $question );

		if ( '' === $question ) {
			$out .= self::render_empty();
		} else {
			$out .= self::render_answer( WPCPM_Handbook_Answer::ask( $question ) );
		}

		$out .= self::render_footnote();
		$out .= '</div>';

		return $out;
	}

	/**
	 * The handbook guide belonging to each dashboard.
	 *
	 * Two fixed pages rather than anything derived: these are the two documents a mentor and a
	 * student are each told to read, and they are the same for everybody on the program.
	 *
	 * @return array<string, array> Audience => `label` and `url`.
	 */
	public static function guides() {
		/**
		 * Filter the guide linked from each dashboard's Resources section.
		 *
		 * @param array<string, array> $guides Audience => `label` and `url`.
		 */
		return (array) apply_filters(
			'wpcpm_handbook_guides',
			array(
				'student' => array(
					'label' => __( 'Student guide', 'wpcredits-program-manager' ),
					'url'   => 'https://make.wordpress.org/community/handbook/education/credits/student-guide/',
					'slack' => 'https://wordpress.slack.com/archives/C0959D2M3T8',
					// Named for a screen reader, which gets no help at all from the icon.
					'chat'  => __( 'Ask in the students Slack channel', 'wpcredits-program-manager' ),
				),
				'mentor'  => array(
					'label' => __( 'Mentor guide', 'wpcredits-program-manager' ),
					'url'   => 'https://make.wordpress.org/community/handbook/education/credits/mentor-guide/',
					'slack' => 'https://wordpress.slack.com/archives/C09KYQLS7F1',
					'chat'  => __( 'Ask in the mentors Slack channel', 'wpcredits-program-manager' ),
				),
			)
		);
	}

	/**
	 * A "Resources" section for the foot of a dashboard.
	 *
	 * Built from the same classes as the Student Report Card's "My course and report form" —
	 * `wpcpm-student__section`, `__heading`, `__actions` — rather than a look-alike of its own.
	 * The theme already dresses those, so this is the same section rather than a second one
	 * that resembles it and drifts the first time either is restyled.
	 *
	 * **The guide link does not depend on the assistant.** It is a link to a handbook page, so
	 * it shows whether or not an AI provider is configured and whether or not this audience may
	 * ask questions — those govern the "Need help?" button beside it and nothing else. Hiding a
	 * handbook link because an API key is missing would make no sense to anybody.
	 *
	 * @param string $audience `student` or `mentor`, deciding which guide is linked.
	 * @return string
	 */
	public static function render_resources( $audience = '' ) {
		$guides = self::guides();
		$guide  = isset( $guides[ $audience ] ) ? $guides[ $audience ] : null;

		// On a student's own card the question is whether *students* may ask, not whether the
		// person looking at it may — otherwise a program manager inspecting a student would see
		// the button on a page the student never would.
		// A provider as well as permission. Without one the panel can only apologise, so the
		// button would be an invitation to a dead end — and this guard was in the version
		// before the section grew a guide beside it.
		$may_ask = WPCPM_Handbook_Answer::provider_ready()
			&& ( 'student' === $audience
				? self::audience_includes_students() && self::is_available()
				: self::is_available() );

		if ( ! $guide && ! $may_ask ) {
			return '';
		}

		// Two halves of one section rather than two sections: they are both "things to go and
		// read", and stacking them would put two bordered blocks at the foot of a card that
		// already has several.
		$out = '<section class="wpcpm-student__section wpcpm-handbook__resources wpcpm-resources--split">';

		// Updates first, and on the left. What is new changes; the guide and the assistant do
		// not, and a fixed pair of buttons in the first column trains people to skip the half of
		// the section that is actually different each time they open the page. Ordered here
		// rather than turned around in CSS, so what a screen reader hears is what the page shows.
		// It is told whose card this is for the same reason the "Need help?" button is: a program
		// manager may read every access level, so asking what *they* can see would put mentor
		// announcements on a student's card.
		$out .= WPCPM_Updates::render_column( $audience );

		$out .= '<div class="wpcpm-resources__col wpcpm-resources__col--help">';
		$out .= sprintf(
			'<h3 class="wpcpm-student__heading">%s</h3>',
			esc_html__( 'Resources', 'wpcredits-program-manager' )
		);
		$out .= '<p class="wpcpm-student__actions">';

		if ( $guide && ! empty( $guide['slack'] ) ) {
			// Slack's published logo — mark and wordmark — as a bare link and not a button:
			// deliberately none of the `wpcpm-button` classes the two beside it carry, so no
			// border or fill is drawn around it. A box around a logo fights the logo, and the
			// artwork is recognisable enough on its own to read as somewhere to go.
			//
			// `aria-label` carries the name: the wordmark is artwork, so a screen reader would
			// otherwise announce the link and nothing else.
			$out .= sprintf(
				'<a class="wpcpm-resources__slack" href="%1$s" target="_blank" rel="noopener noreferrer" aria-label="%2$s" title="%2$s">%3$s</a>',
				esc_url( $guide['slack'] ),
				esc_attr( $guide['chat'] ),
				// 56px: the height of the buttons beside it, which the theme builds from a 1px
				// border, 15px of padding, a 24px line and the same again. The theme repeats the
				// number in its own stylesheet, since the height is its decision — this is the
				// size for anywhere the theme is not.
				WPCPM_Icons::slack_logo( 56 )
			);
		}

		if ( $guide ) {
			// Filled rather than outlined, and first among the labelled buttons: the guide is
			// the thing to read, the assistant is what you use when it has not answered your
			// question. The same relationship "Open your course" has to "Open your report form".
			$out .= sprintf(
				'<a class="wpcpm-button" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $guide['url'] ),
				esc_html( $guide['label'] )
			);
		}

		if ( $may_ask ) {
			$out .= sprintf(
				'<button type="button" class="wpcpm-button wpcpm-button--secondary" data-wpcpm-handbook-open>%s</button>',
				esc_html__( 'Need help?', 'wpcredits-program-manager' )
			);
		}

		$out .= '</p>';

		if ( $may_ask ) {
			$out .= sprintf(
				'<p class="wpcpm-student__note">%s</p>',
				esc_html__( 'Ask anything about the program or WordPress itself, and get an answer.', 'wpcredits-program-manager' )
			);
		}

		$out .= '</div>';

		return $out . '</section>';
	}

	/**
	 * The question box.
	 *
	 * @param string $question The current question.
	 * @return string
	 */
	private static function render_form( $question ) {
		// Back to the page the block is on, not to a page of its own. That is what lets the
		// block sit on a Report Card and answer there, and it is the no-JS path: with the
		// script running, the panel intercepts and nothing is submitted at all.
		$out = sprintf( '<form class="wpcpm-handbook__form" method="get" action="%s">', esc_url( self::current_url() ) );

		// Whatever else was in the URL travels with the question, so asking something from a
		// dashboard does not throw away which student was being looked at.
		$out .= self::render_carried_args();
		$out .= sprintf(
			'<label class="wpcpm-handbook__label" for="wpcpm-ask">%s</label>',
			esc_html__( 'Need help?', 'wpcredits-program-manager' )
		);
		$out .= '<div class="wpcpm-handbook__row">';
		$out .= sprintf(
			'<input type="search" id="wpcpm-ask" name="%1$s" value="%2$s" maxlength="%3$d" class="wpcpm-handbook__input" placeholder="%4$s" autocomplete="off" />',
			esc_attr( self::ARG ),
			esc_attr( $question ),
			(int) self::MAX_LENGTH,
			esc_attr__( 'How does the WordPress Credits Program work?', 'wpcredits-program-manager' )
		);
		$out .= sprintf(
			'<button type="submit" class="wpcpm-button">%s</button>',
			esc_html__( 'Ask', 'wpcredits-program-manager' )
		);
		$out .= '</div>';
		$out .= '</form>';

		return $out;
	}

	/**
	 * The URL of the page being viewed, without any question already on it.
	 *
	 * @return string
	 */
	private static function current_url() {
		$page_id = get_queried_object_id();

		return $page_id ? (string) get_permalink( $page_id ) : home_url( '/' );
	}

	/**
	 * Hidden fields carrying the rest of the query string through the form.
	 *
	 * @return string
	 */
	private static function render_carried_args() {
		$out = '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view state, re-emitted as it arrived and escaped on the way out.
		foreach ( array_keys( (array) $_GET ) as $name ) {
			$name = sanitize_key( $name );

			if ( '' === $name || self::ARG === $name ) {
				continue;
			}

			$out .= sprintf(
				'<input type="hidden" name="%1$s" value="%2$s" />',
				esc_attr( $name ),
				esc_attr( WPCPM_Request::text( $name ) )
			);
		}

		return $out;
	}

	/**
	 * What to show before anything has been asked.
	 *
	 * @return string
	 */
	private static function render_empty() {
		if ( ! WPCPM_Handbook_Answer::provider_ready() ) {
			return self::notice( __( 'No AI provider is configured, so questions cannot be answered yet. A program manager can add one in the plugin settings.', 'wpcredits-program-manager' ) );
		}

		$out = sprintf(
			'<p class="wpcpm-handbook__hint">%s</p>',
			esc_html__( 'Answers are looked up in the WordPress documentation — the Education Handbook, Learn WordPress, the developer handbooks and wordpress.org — and link back to the pages they came from.', 'wpcredits-program-manager' )
		);

		$examples = array(
			__( 'What does a mentor do in the first week?', 'wpcredits-program-manager' ),
			__( 'How does a student get their certificate?', 'wpcredits-program-manager' ),
			__( 'What is needed to sign up an institution?', 'wpcredits-program-manager' ),
		);

		$out .= '<ul class="wpcpm-handbook__examples">';

		foreach ( $examples as $example ) {
			$out .= sprintf(
				'<li><a href="%1$s">%2$s</a></li>',
				esc_url( add_query_arg( self::ARG, rawurlencode( $example ), self::current_url() ) ),
				esc_html( $example )
			);
		}

		return $out . '</ul>';
	}

	/**
	 * The answer, and the sections it came from.
	 *
	 * @param array $answer From `WPCPM_Handbook_Answer::ask()`.
	 * @return string
	 */
	private static function render_answer( array $answer ) {
		$out = '<div class="wpcpm-handbook__answer">';

		if ( '' !== $answer['notice'] ) {
			$out .= sprintf(
				'<p class="wpcpm-handbook__degraded">%s</p>',
				esc_html( $answer['notice'] )
			);
		}

		// Markdown, then `wpautop`, then `wp_kses_post`: a provider returns text with blank
		// lines and the occasional list, and none of it is trusted markup.
		$out .= sprintf(
			'<div class="wpcpm-handbook__text">%s</div>',
			wp_kses_post( wpautop( WPCPM_Handbook_Answer::to_html( $answer['text'] ) ) )
		);

		if ( ! empty( $answer['unsourced'] ) ) {
			$out .= sprintf(
				'<p class="wpcpm-handbook__unsourced">%s</p>',
				esc_html__( 'This answer does not cite any WordPress documentation, so it could not be checked against it. Treat it as unconfirmed.', 'wpcredits-program-manager' )
			);
		}

		$out .= '</div>';

		if ( empty( $answer['sources'] ) ) {
			return $out;
		}

		$out .= sprintf(
			'<h3 class="wpcpm-handbook__heading">%s</h3>',
			esc_html__( 'Pages it read', 'wpcredits-program-manager' )
		);
		$out .= '<ol class="wpcpm-handbook__sources">';

		foreach ( $answer['sources'] as $source ) {
			$out .= '<li class="wpcpm-handbook__source">';
			$out .= sprintf(
				'<a class="wpcpm-handbook__source-link" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $source['link'] ),
				esc_html( $source['title'] )
			);

			if ( '' !== $source['extract'] ) {
				$out .= sprintf( '<p class="wpcpm-handbook__source-url">%s</p>', esc_html( $source['extract'] ) );
			}

			$out .= '</li>';
		}

		return $out . '</ol>';
	}

	/**
	 * What the reader should know about where their question goes.
	 *
	 * @return string
	 */
	private static function render_footnote() {
		// Said plainly and on the page, not in a policy somewhere. There is no local copy any
		// more, so every question goes out — and somebody typing one about a named student
		// deserves to know that before they press the button, not after.
		return sprintf(
			'<p class="wpcpm-handbook__footnote">%s</p>',
			esc_html__( 'NOTE: Your question is sent to an external AI service, which searches the WordPress documentation to answer it. Do not type anything confidential.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * A standalone message in the assistant's frame.
	 *
	 * @param string $message Message.
	 * @return string
	 */
	private static function notice( $message ) {
		return sprintf(
			'<div class="wpcpm-handbook"><p class="wpcpm-handbook__notice">%s</p></div>',
			esc_html( $message )
		);
	}

	/*
	 * The page
	 * --------------------------------------------------------------------
	 */

	/**
	 * The assistant page's URL.
	 *
	 * @return string
	 */
	public static function page_url() {
		$page_id = (int) get_option( self::OPT_PAGE );

		if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}

		return (string) get_permalink( $page_id );
	}

	/**
	 * Option recording the visibility this class last applied to the page.
	 *
	 * Without it, "keep the page in step with the setting" becomes "force the page to match
	 * the setting on every request", which would silently republish a page an administrator
	 * had unpublished by hand. Only a *change* of setting moves the page.
	 */
	const OPT_APPLIED = 'wpcpm_handbook_page_visible';

	/**
	 * Bring the page's visibility into line with the setting, when the setting changes.
	 *
	 * Switched off has to mean the page is not on the site at all — not a published page that
	 * renders nothing, which is still in menus, still in the sitemap, and still a URL somebody
	 * can land on. Unpublishing is what actually removes it: WordPress drops unpublished pages
	 * from navigation menus and returns 404 for anybody without permission to preview.
	 *
	 * The page is drafted rather than deleted, so its ID, slug and anything written on it
	 * survive being switched off and on again.
	 */
	public static function apply_visibility() {
		$enabled = WPCPM_Handbook::is_enabled();
		$applied = get_option( self::OPT_APPLIED, null );

		// First run on an existing install records the current setting without touching
		// anything, so merely upgrading the plugin never moves a page.
		if ( null === $applied ) {
			update_option( self::OPT_APPLIED, $enabled ? '1' : '0', true );

			return;
		}

		if ( ( '1' === (string) $applied ) === $enabled ) {
			return;
		}

		update_option( self::OPT_APPLIED, $enabled ? '1' : '0', true );

		if ( $enabled ) {
			$page_id = self::ensure_page();

			if ( $page_id && 'draft' === get_post_status( $page_id ) ) {
				wp_update_post(
					array(
						'ID'          => $page_id,
						'post_status' => 'publish',
					)
				);
			}

			return;
		}

		$page_id = (int) get_option( self::OPT_PAGE );

		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			wp_update_post(
				array(
					'ID'          => $page_id,
					'post_status' => 'draft',
				)
			);
		}
	}

	/**
	 * Create the assistant page if it is missing.
	 *
	 * @return int Page ID, or 0.
	 */
	public static function ensure_page() {
		$page_id = (int) get_option( self::OPT_PAGE );

		if ( $page_id ) {
			$existing = get_post( $page_id );

			if ( $existing instanceof WP_Post && 'trash' !== $existing->post_status ) {
				return $page_id;
			}
		}

		$existing = get_page_by_path( 'handbook-assistant' );

		if ( $existing instanceof WP_Post ) {
			update_option( self::OPT_PAGE, $existing->ID, false );

			return (int) $existing->ID;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Need help?', 'wpcredits-program-manager' ),
				'post_name'    => 'handbook-assistant',
				'post_content' => '<!-- wp:' . self::BLOCK . ' /-->',
			),
			true
		);

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return 0;
		}

		update_option( self::OPT_PAGE, (int) $page_id, false );

		return (int) $page_id;
	}
}
