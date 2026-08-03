<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CONTTEMA_Shortcode {

	public static function init() {
		add_shortcode( 'conttema_quiz', array( __CLASS__, 'render' ) );
		// Backward-compat aliases for content created with earlier plugin versions.
		add_shortcode( 'contributor_team_matcher', array( __CLASS__, 'render' ) );
		add_shortcode( 'find_your_team', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_conttema_get_results', array( __CLASS__, 'ajax_get_results' ) );
		add_action( 'wp_ajax_nopriv_conttema_get_results', array( __CLASS__, 'ajax_get_results' ) );
	}

	public static function enqueue_assets() {
		wp_register_style(
			'contributor-team-matcher',
			CONTTEMA_PLUGIN_URL . 'assets/css/contributor-team-matcher.css',
			array(),
			CONTTEMA_VERSION
		);

		wp_register_script(
			'contributor-team-matcher',
			CONTTEMA_PLUGIN_URL . 'assets/js/contributor-team-matcher.js',
			array(),
			CONTTEMA_VERSION,
			true
		);

		wp_localize_script(
			'contributor-team-matcher',
			'conttemaData',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'conttema_nonce' ),
				'questions' => CONTTEMA_Quiz_Data::get_questions(),
				'i18n'      => array(
					'next'        => __( 'Next', 'find-your-team' ),
					'back'        => __( 'Back', 'find-your-team' ),
					'seeResults'  => __( 'Find My Team', 'find-your-team' ),
					'restart'     => __( 'Start Over', 'find-your-team' ),
					'yourTopTeam' => __( 'Your Best Match', 'find-your-team' ),
					'otherTeams'  => __( 'Other Great Fits', 'find-your-team' ),
					'visitTeam'   => __( 'Visit Team Page', 'find-your-team' ),
					'selectOne'   => __( 'Please select at least one option to continue.', 'find-your-team' ),
					'selectUpTo'  => __( 'Select up to 3 options.', 'find-your-team' ),
					'loading'     => __( 'Finding your team…', 'find-your-team' ),
					// translators: %1$s is the current question number, %2$s is the total number of questions.
					'questionOf'  => __( 'Question %1$s of %2$s', 'find-your-team' ),
					'exploreMore' => __( 'Explore All Teams', 'find-your-team' ),
					'exploreUrl'  => 'https://make.wordpress.org/',
				),
			)
		);
	}

	public static function render( $atts = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $atts reserved for future shortcode attributes.
		wp_enqueue_style( 'contributor-team-matcher' );
		wp_enqueue_script( 'contributor-team-matcher' );

		ob_start();
		include CONTTEMA_PLUGIN_DIR . 'templates/quiz.php';
		return ob_get_clean();
	}

	public static function ajax_get_results() {
		check_ajax_referer( 'conttema_nonce', 'nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via array_map( 'sanitize_text_field', ... ) on line below.
		$raw_tags = isset( $_POST['tags'] ) ? (array) wp_unslash( $_POST['tags'] ) : array();

		if ( ! is_array( $raw_tags ) ) {
			wp_send_json_error( 'Invalid data.' );
		}

		$tags  = array_map( 'sanitize_text_field', $raw_tags );
		$teams = CONTTEMA_Quiz_Data::score_teams( $tags );

		$response = array(
			'top'    => array_slice( $teams, 0, 1 )[0],
			'others' => array_slice( $teams, 1, 4 ),
		);

		// Strip internal score/tags before sending.
		foreach ( $response['others'] as &$t ) {
			unset( $t['score'], $t['tags'] );
		}
		unset( $response['top']['score'], $response['top']['tags'] );

		wp_send_json_success( $response );
	}
}
