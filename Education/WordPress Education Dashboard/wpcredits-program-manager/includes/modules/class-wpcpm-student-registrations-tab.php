<?php
/**
 * Students module - the Registrations tab (1.123.54).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where an Administrator reads the registrations students made on the site (design of 5 November
 * 2026, section 6): the Students screen's Registrations tab, in four views, newest first, each row
 * with what became of it and the answers and consent folded under it.
 *
 * **The presses**, three, each for the states it takes:
 *
 * - Write it now, on a registration that could not be written, or a waiting one held for an
 *   Administrator past the form's 200 a day: it is tried once at once, a held one with its hold taken
 *   off first.
 * - Put back, on spam: it is queued again and tried once at once, like a new registration.
 * - Delete for good, on spam: the registration is removed, after a question.
 *
 * Each press asks the capability first, then the nonce keyed to the press and the row, then the
 * registration, which must be in a state the press takes, and comes back to the view it was made on.
 * The site never changes or deletes the Airtable row of a registration it wrote.
 */
class WPCPM_Student_Registrations_Tab {

	const ACTION_WRITE   = 'wpcpm_sreg_write';
	const ACTION_RESTORE = 'wpcpm_sreg_restore';
	const ACTION_DELETE  = 'wpcpm_sreg_delete';

	/** The field a press names its registration in. */
	const FIELD_REGISTRATION = 'wpcpm_sreg_id';

	/** The query argument the tab's views travel in. */
	const VIEW_ARG = 'sreg_view';

	/** Most registrations a view draws, the newest first. */
	const LIST_MAX = 50;

	/** The flash channel the Students screen reads its outcomes from. */
	const FLASH = 'students_admin';

	/**
	 * The test institutions' record IDs, read once as the tab draws.
	 *
	 * @var string[]
	 */
	private static $tests = array();

	/**
	 * Hooks: the three presses, for a signed-in Administrator alone (`admin_post_`, never `nopriv`).
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_WRITE, array( __CLASS__, 'handle_write' ) );
		add_action( 'admin_post_' . self::ACTION_RESTORE, array( __CLASS__, 'handle_restore' ) );
		add_action( 'admin_post_' . self::ACTION_DELETE, array( __CLASS__, 'handle_delete' ) );
	}

	/**
	 * The tab's four views, slug => the states each lists and its label, in the bar's order. Spam is
	 * listed under Spam alone.
	 *
	 * @return array<string, array{states: string[], label: string}>
	 */
	public static function views() {
		return array(
			'all'     => array(
				'states' => array(
					WPCPM_Student_Registration::STATE_WAITING,
					WPCPM_Student_Registration::STATE_FAILED,
					WPCPM_Student_Registration::STATE_WRITTEN,
					WPCPM_Student_Registration::STATE_ACCOUNT,
					WPCPM_Student_Registration::STATE_ALREADY,
				),
				'label'  => __( 'All', 'wpcredits-program-manager' ),
			),
			'waiting' => array(
				'states' => array( WPCPM_Student_Registration::STATE_WAITING ),
				'label'  => __( 'Waiting to be written', 'wpcredits-program-manager' ),
			),
			'failed'  => array(
				'states' => array( WPCPM_Student_Registration::STATE_FAILED ),
				'label'  => __( 'Could not be written', 'wpcredits-program-manager' ),
			),
			'spam'    => array(
				'states' => array( WPCPM_Student_Registration::STATE_SPAM ),
				'label'  => __( 'Spam', 'wpcredits-program-manager' ),
			),
		);
	}

	/**
	 * The view the request names, or All.
	 *
	 * @return string A key of `views()`.
	 */
	public static function current_view() {
		$view = WPCPM_Request::key( self::VIEW_ARG );

		return isset( self::views()[ $view ] ) ? $view : 'all';
	}

	/**
	 * The tab's address at one view.
	 *
	 * @param string $view A key of `views()`.
	 * @return string
	 */
	public static function view_url( $view = 'all' ) {
		$url = admin_url( 'admin.php?page=wpcpm-students&tab=registrations' );

		return 'all' === $view ? $url : add_query_arg( self::VIEW_ARG, $view, $url );
	}

	/**
	 * What each press can leave, in the Administrator's words.
	 *
	 * @return array<string, array{0: string, 1: string}> Status => notice class and sentence.
	 */
	public static function messages() {
		return array(
			'sreg-written' => array( 'success', __( 'The registration is written to Airtable, and the student is sent the received email.', 'wpcredits-program-manager' ) ),
			'sreg-settled' => array( 'success', __( 'The registration is settled without a new row: the address has an account or is registered already, and the student is sent that email.', 'wpcredits-program-manager' ) ),
			'sreg-waiting' => array( 'warning', __( 'Airtable could not take the registration; it waits, and is tried again every hour.', 'wpcredits-program-manager' ) ),
			'sreg-failed'  => array( 'error', __( 'Airtable refused the registration again. The reason is on it.', 'wpcredits-program-manager' ) ),
			'sreg-busy'    => array( 'warning', __( 'Another request is writing a registration for that address at this moment. Reload the page in a minute.', 'wpcredits-program-manager' ) ),
			'sreg-deleted' => array( 'success', __( 'The registration is deleted for good.', 'wpcredits-program-manager' ) ),
			'sreg-unknown' => array( 'error', __( 'There is no registration with that ID.', 'wpcredits-program-manager' ) ),
			'sreg-state'   => array( 'error', __( 'That registration is not in a state this press applies to. Reload the page to see where it got to.', 'wpcredits-program-manager' ) ),
		);
	}

	/*
	 * The presses
	 * --------------------------------------------------------------------
	 */

	/**
	 * What every press does first: the capability, then the nonce keyed to the press and the row,
	 * then the registration, which must be in a state the press takes.
	 *
	 * @param string   $action The press.
	 * @param string[] $states The states it takes a registration from.
	 * @return WP_Post
	 */
	private static function begin( $action, array $states ) {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( self::FIELD_REGISTRATION );

		check_admin_referer( $action . '_' . $post_id );

		$post = WPCPM_Student_Registration::registration( $post_id );

		if ( null === $post ) {
			self::leave( 'sreg-unknown' );
		}

		if ( ! in_array( WPCPM_Student_Registration::state_of( $post ), $states, true ) ) {
			self::leave( 'sreg-state' );
		}

		return $post;
	}

	/**
	 * Write it now: a registration that could not be written, or a waiting one held for an
	 * Administrator, is tried once at once. The hold comes off first, so a try that does not settle it
	 * leaves it to the hourly job from then on.
	 */
	public static function handle_write() {
		$post = self::begin( self::ACTION_WRITE, array( WPCPM_Student_Registration::STATE_FAILED, WPCPM_Student_Registration::STATE_WAITING ) );
		$held = self::is_held( $post );

		if ( WPCPM_Student_Registration::STATE_WAITING === WPCPM_Student_Registration::state_of( $post ) && ! $held ) {
			self::leave( 'sreg-state' );
		}

		if ( $held ) {
			WPCPM_Student_Registration::remove_signal( (int) $post->ID, WPCPM_Student_Registration_Process::SIGNAL_HELD );
		}

		self::leave( self::outcome_status( WPCPM_Student_Registration_Process::process( (int) $post->ID ) ) );
	}

	/**
	 * Whether a registration is held for an Administrator: the form took it past its 200 a day.
	 *
	 * @param WP_Post $post The registration.
	 * @return bool
	 */
	private static function is_held( WP_Post $post ) {
		return in_array( WPCPM_Student_Registration_Process::SIGNAL_HELD, WPCPM_Student_Registration::signals_of( $post ), true );
	}

	/**
	 * Put back: a spam registration is queued again and tried once at once, as a new one is.
	 */
	public static function handle_restore() {
		$post = self::begin( self::ACTION_RESTORE, array( WPCPM_Student_Registration::STATE_SPAM ) );

		if ( ! WPCPM_Student_Registration::requeue( (int) $post->ID ) ) {
			self::leave( 'sreg-state' );
		}

		self::leave( self::outcome_status( WPCPM_Student_Registration_Process::process( (int) $post->ID ) ) );
	}

	/**
	 * Delete for good: a spam registration is removed.
	 */
	public static function handle_delete() {
		$post = self::begin( self::ACTION_DELETE, array( WPCPM_Student_Registration::STATE_SPAM ) );

		wp_delete_post( (int) $post->ID, true );

		self::leave( 'sreg-deleted' );
	}

	/**
	 * The notice a try at writing leaves, by what the process answered.
	 *
	 * @param string $answer What `WPCPM_Student_Registration_Process::process()` answered.
	 * @return string A key of `messages()`.
	 */
	private static function outcome_status( $answer ) {
		switch ( $answer ) {
			case WPCPM_Student_Registration::STATE_WRITTEN:
				return 'sreg-written';
			case WPCPM_Student_Registration::STATE_ACCOUNT:
			case WPCPM_Student_Registration::STATE_ALREADY:
				return 'sreg-settled';
			case WPCPM_Student_Registration::STATE_WAITING:
				return 'sreg-waiting';
			case WPCPM_Student_Registration::STATE_FAILED:
				return 'sreg-failed';
			case 'busy':
				return 'sreg-busy';
		}

		return 'sreg-state';
	}

	/**
	 * Back to the tab with a one-shot outcome, at the view the press was made on.
	 *
	 * @param string $status A key of `messages()`.
	 */
	private static function leave( $status ) {
		$view = WPCPM_Request::posted_key( self::VIEW_ARG, 'all' );

		WPCPM_Flash::set( self::FLASH, $status );
		wp_safe_redirect( self::view_url( isset( self::views()[ $view ] ) ? $view : 'all' ) );
		exit;
	}

	/*
	 * The tab
	 * --------------------------------------------------------------------
	 */

	/**
	 * The Registrations tab: the views with their counts, then the view's registrations, newest
	 * first. The capability is asked here as well as by the screen: every answer a stranger typed is
	 * printed below.
	 */
	public static function render() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		$view  = self::current_view();
		$views = self::views();
		// The test institutions' record IDs, read once: their registrations are listed but left out of
		// every count. With none, the queries and the output are what they always were.
		self::$tests = WPCPM_Test_Institutions::test_ids();
		$total       = WPCPM_Student_Registration::count_in( $views[ $view ]['states'], self::$tests );
		$rows        = WPCPM_Student_Registration::in_states( $views[ $view ]['states'], self::LIST_MAX );
		$open        = WPCPM_Student_Registration::count_in( array( WPCPM_Student_Registration::STATE_WAITING, WPCPM_Student_Registration::STATE_FAILED ), self::$tests );
		$drawn       = count( $rows );

		if ( ! empty( self::$tests ) ) {
			$drawn = 0;

			foreach ( $rows as $post ) {
				if ( ! WPCPM_Student_Registration::is_test_registration( $post, self::$tests ) ) {
					++$drawn;
				}
			}
		}

		echo '<div class="wpcpm-card" id="wpcpm-student-registrations">';
		printf( '<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>', esc_html__( 'Student registrations', 'wpcredits-program-manager' ), esc_html( number_format_i18n( $open ) ) );
		echo '<p class="description">' . esc_html__( 'Students who registered through the form on the site, newest first. A registration is written to the Airtable Students table unless the address has an account or is registered already; one Airtable could not take waits and is tried again every hour, and after 72 hours shows as Could not be written.', 'wpcredits-program-manager' ) . '</p>';

		echo '<ul class="subsubsub">';

		$links = array();

		foreach ( $views as $slug => $about ) {
			$links[] = sprintf(
				'<li class="%1$s"><a href="%2$s"%3$s>%4$s <span class="count">(%5$s)</span></a>',
				esc_attr( $slug ),
				esc_url( self::view_url( $slug ) ),
				$slug === $view ? ' class="current" aria-current="page"' : '',
				esc_html( $about['label'] ),
				esc_html( number_format_i18n( WPCPM_Student_Registration::count_in( $about['states'], self::$tests ) ) )
			);
		}

		echo implode( ' |</li>', $links ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each link is built escaped above.
		echo '</ul><div class="clear"></div>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'Nothing here.', 'wpcredits-program-manager' ) . '</p></div>';

			return;
		}

		if ( $drawn < $total ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: how many registrations are drawn, 2: how many there are. */
						__( 'Showing the newest %1$s of %2$s.', 'wpcredits-program-manager' ),
						number_format_i18n( $drawn ),
						number_format_i18n( $total )
					)
				)
			);
		}

		echo '<ol class="wpcpm-queue">';

		foreach ( $rows as $post ) {
			self::render_row( $post, $view );
		}

		echo '</ol></div>';
	}

	/**
	 * One registration: when, who, what became of it, then the answers and consent folded.
	 *
	 * An entry filed as already registered keeps no answers and no name (it is titled for that), so
	 * it is named by the registration that holds the address, which it links.
	 *
	 * @param WP_Post $post The registration.
	 * @param string  $view The view to come back to.
	 */
	private static function render_row( WP_Post $post, $view ) {
		$state  = WPCPM_Student_Registration::state_of( $post );
		$first  = self::first_of( $post );
		$shown  = null !== $first ? $first : $post;
		$fields = WPCPM_Student_Registration::fields_of( $shown );
		$name   = WPCPM_Student_Registration::name_of( $shown );
		$email  = isset( $fields['email'] ) ? trim( (string) $fields['email'] ) : '';
		$when   = (int) get_post_time( 'U', true, $post );
		$format = (string) get_option( 'date_format', 'F j, Y' );

		printf( '<li class="wpcpm-queue-item" id="%s">', esc_attr( 'wpcpm-sreg-' . (int) $post->ID ) );
		printf(
			'<h3 class="wpcpm-queue-title"><span class="wpcpm-inst-name__text">%1$s</span> <span class="wpcpm-inst-muted">%2$s</span></h3>',
			esc_html( '' !== $name ? $name : __( '(no name)', 'wpcredits-program-manager' ) ),
			esc_html( $email )
		);
		printf(
			'<p class="wpcpm-queue-age">%s</p>',
			esc_html(
				trim( wp_date( $format, $when ) . ' ' . self::institution_of( $fields ) )
			)
		);

		self::render_outcome( $post, $state, $first );
		self::render_folded( $post, $state );
		self::render_presses( $post, $state, $view );

		echo '</li>';
	}

	/**
	 * The registration an `already` entry points at, or null for any other.
	 *
	 * @param WP_Post $post The registration.
	 * @return WP_Post|null
	 */
	private static function first_of( WP_Post $post ) {
		if ( WPCPM_Student_Registration::STATE_ALREADY !== WPCPM_Student_Registration::state_of( $post ) ) {
			return null;
		}

		return WPCPM_Student_Registration::registration( (int) get_post_meta( (int) $post->ID, WPCPM_Student_Registration::META_FIRST, true ) );
	}

	/**
	 * The institution's name, or the stored value where the index no longer holds it.
	 *
	 * @param array $fields The answers.
	 * @return string
	 */
	private static function institution_of( array $fields ) {
		$value = isset( $fields['institution'] ) ? trim( (string) $fields['institution'] ) : '';

		if ( '' === $value ) {
			return '';
		}

		$names = WPCPM_Student_Airtable_Row::institution_links();

		$name = isset( $names[ $value ] ) ? (string) $names[ $value ] : $value;

		// A test institution's registration is listed, and its institution reads "(Test)".
		if ( ! empty( self::$tests ) && ( in_array( $value, self::$tests, true ) || WPCPM_Sponsors_Index::is_test_name( $value ) ) ) {
			return WPCPM_Test_Institutions::labeled( $name );
		}

		return $name;
	}

	/**
	 * What happened to the registration, in a sentence with its link, then the columns the row left
	 * out and an email that was not sent.
	 *
	 * @param WP_Post      $post  The registration.
	 * @param string       $state Its state.
	 * @param WP_Post|null $first The registration an `already` entry points at.
	 */
	private static function render_outcome( WP_Post $post, $state, $first ) {
		$outcome = WPCPM_Student_Registration::outcome_of( $post );

		echo '<p class="wpcpm-sreg-outcome">';

		switch ( $state ) {
			case WPCPM_Student_Registration::STATE_WRITTEN:
				echo '<strong>' . esc_html__( 'Written to Airtable', 'wpcredits-program-manager' ) . '</strong>';
				self::print_after( self::record_link( (string) $outcome['record'] ) );
				break;

			case WPCPM_Student_Registration::STATE_ACCOUNT:
				echo '<strong>' . esc_html__( 'Already has an account', 'wpcredits-program-manager' ) . '</strong>';
				self::print_after( self::account_link( (int) $outcome['user'] ) );
				break;

			case WPCPM_Student_Registration::STATE_ALREADY:
				echo '<strong>' . esc_html__( 'Already registered', 'wpcredits-program-manager' ) . '</strong>';

				$records = array_values( array_filter( array_map( 'strval', (array) $outcome['records'] ) ) );

				if ( ! empty( $records ) ) {
					self::print_after( self::record_link( $records[0] ) );
				} elseif ( null !== $first ) {
					self::print_after( sprintf( '<a href="%1$s">%2$s</a>', esc_url( '#wpcpm-sreg-' . (int) $first->ID ), esc_html__( 'an earlier registration', 'wpcredits-program-manager' ) ) );
				}
				break;

			case WPCPM_Student_Registration::STATE_WAITING:
				echo '<strong>' . esc_html__( 'Waiting to be written', 'wpcredits-program-manager' ) . '</strong>: ' . esc_html( self::waiting_why( $post ) );

				if ( (int) $outcome['tries'] > 0 ) {
					echo ' ' . esc_html(
						sprintf(
							/* translators: 1: how many tries have been made, 2: the date and time of the last try. */
							_n( 'Tried %1$s time, last on %2$s.', 'Tried %1$s times, last on %2$s.', (int) $outcome['tries'], 'wpcredits-program-manager' ),
							number_format_i18n( (int) $outcome['tries'] ),
							self::time_text( (int) $outcome['tried_at'] )
						)
					);
				}

				if ( '' !== (string) $outcome['error'] ) {
					echo ' ' . esc_html( (string) $outcome['error'] );
				}
				break;

			case WPCPM_Student_Registration::STATE_FAILED:
				echo '<strong>' . esc_html__( 'Could not be written', 'wpcredits-program-manager' ) . '</strong>';

				if ( '' !== (string) $outcome['error'] ) {
					echo ': ' . esc_html( (string) $outcome['error'] );
				}
				break;

			case WPCPM_Student_Registration::STATE_SPAM:
				echo '<strong>' . esc_html__( 'Spam', 'wpcredits-program-manager' ) . '</strong>';
				break;
		}

		echo '</p>';

		if ( is_array( $outcome['left_out'] ) && array() !== $outcome['left_out'] ) {
			$sentence = WPCPM_Student_Airtable_Row::left_out_sentence( $outcome['left_out'] );

			if ( '' !== $sentence ) {
				printf( '<p class="wpcpm-sreg-leftout">%s</p>', esc_html( $sentence ) );
			}
		}

		$signals = WPCPM_Student_Registration::signals_of( $post );

		if ( in_array( WPCPM_Student_Registration_Process::SIGNAL_MAIL_CEILING, $signals, true ) ) {
			printf( '<p class="wpcpm-sreg-mail">%s</p>', esc_html__( 'The email was not sent: the day\'s limit on emails was reached.', 'wpcredits-program-manager' ) );
		} elseif ( in_array( WPCPM_Student_Registration_Process::SIGNAL_MAIL_FAILED, $signals, true ) ) {
			printf( '<p class="wpcpm-sreg-mail">%s</p>', esc_html__( 'The email could not be handed off.', 'wpcredits-program-manager' ) );
		}
	}

	/**
	 * Why a registration waits, in a sentence: held for an Administrator, past the day's 40, or
	 * Airtable has not taken it yet.
	 *
	 * @param WP_Post $post The registration.
	 * @return string
	 */
	private static function waiting_why( WP_Post $post ) {
		if ( self::is_held( $post ) ) {
			return __( 'Held for an Administrator: more than 200 registrations arrived that day.', 'wpcredits-program-manager' );
		}

		if ( in_array( WPCPM_Student_Registration_Process::SIGNAL_SITE_CEILING, WPCPM_Student_Registration::signals_of( $post ), true ) ) {
			return __( 'Past the day\'s limit of 40: the hourly job writes it.', 'wpcredits-program-manager' );
		}

		return __( 'Airtable could not take it yet; it is tried again every hour.', 'wpcredits-program-manager' );
	}

	/**
	 * Print a link after the outcome's name, if there is one.
	 *
	 * @param string $link Escaped markup, or ''.
	 */
	private static function print_after( $link ) {
		if ( '' !== $link ) {
			echo ': ' . $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built escaped by the caller.
		}
	}

	/**
	 * A link to the Students row in Airtable, or its ID where the base cannot be linked to.
	 *
	 * @param string $record The record ID.
	 * @return string Escaped markup, or ''.
	 */
	private static function record_link( $record ) {
		if ( '' === $record ) {
			return '';
		}

		$url = WPCPM_Settings::airtable_record_url( 'students_table', $record );

		if ( '' === $url ) {
			return esc_html( $record );
		}

		return sprintf( '<a href="%1$s" rel="noopener">%2$s</a>', esc_url( $url ), esc_html__( 'the row', 'wpcredits-program-manager' ) );
	}

	/**
	 * A link to the account's edit screen.
	 *
	 * @param int $user_id The account.
	 * @return string Escaped markup, or ''.
	 */
	private static function account_link( $user_id ) {
		if ( $user_id <= 0 ) {
			return '';
		}

		$link = get_edit_user_link( $user_id );

		if ( ! $link ) {
			return '';
		}

		return sprintf( '<a href="%1$s">%2$s</a>', esc_url( $link ), esc_html__( 'the account', 'wpcredits-program-manager' ) );
	}

	/**
	 * A time the way the tab prints one.
	 *
	 * @param int $time Unix time; 0 for none.
	 * @return string
	 */
	private static function time_text( $time ) {
		if ( $time <= 0 ) {
			return '';
		}

		return wp_date( (string) get_option( 'date_format', 'F j, Y' ) . ' ' . (string) get_option( 'time_format', 'g:i a' ), $time );
	}

	/**
	 * The answers and the consent, folded under the registration.
	 *
	 * @param WP_Post $post  The registration.
	 * @param string  $state Its state.
	 */
	private static function render_folded( WP_Post $post, $state ) {
		$fields  = WPCPM_Student_Registration::fields_of( $post );
		$consent = WPCPM_Student_Registration::consent_of( $post );

		echo '<details class="wpcpm-sreg-details"><summary>' . esc_html__( 'Answers and consent', 'wpcredits-program-manager' ) . '</summary>';

		// An entry filed under an earlier registration keeps none; one a Students row answered keeps them.
		if ( WPCPM_Student_Registration::STATE_ALREADY === $state && array() === $fields ) {
			echo '<p>' . esc_html__( 'No answers are kept for a registration of an address that is registered already.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			echo '<table class="widefat striped wpcpm-list wpcpm-app-answers"><tbody>';

			foreach ( WPCPM_Student_Registration_Form::fields() as $key => $spec ) {
				if ( 'consent' === $spec['type'] ) {
					continue;
				}

				$answer = isset( $fields[ $key ] ) ? $fields[ $key ] : '';

				if ( 'statement' === $spec['type'] ) {
					$text = true === $answer ? __( 'Agreed', 'wpcredits-program-manager' ) : __( 'Not agreed', 'wpcredits-program-manager' );
				} elseif ( 'institution' === $spec['type'] ) {
					$text = self::institution_of( $fields );
				} elseif ( 'track' === $spec['type'] ) {
					// The track's name as the student saw it, never the posted key (a status). A registration
					// that was never asked has none, and the question is not listed for it.
					$text = isset( $fields['track_name'] ) ? (string) $fields['track_name'] : '';

					if ( '' === trim( $text ) ) {
						continue;
					}
				} else {
					$text = is_array( $answer ) ? implode( ', ', array_map( 'strval', $answer ) ) : (string) $answer;
				}

				$cell = '' !== trim( $text ) ? nl2br( esc_html( $text ) ) : '<span class="wpcpm-inst-muted">' . esc_html__( 'no answer', 'wpcredits-program-manager' ) . '</span>';

				printf( '<tr><th scope="row">%1$s</th><td>%2$s</td></tr>', esc_html( $spec['label'] ), $cell ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each cell is built escaped above.
			}

			echo '</tbody></table>';
		}

		echo '<p class="wpcpm-sreg-consent">';

		if ( array() === $consent ) {
			echo esc_html__( 'No consent is kept.', 'wpcredits-program-manager' );
		} else {
			echo esc_html(
				sprintf(
					/* translators: 1: the sentence the student agreed to, 2: when, 3: the address it was agreed from, shortened, 4: the browser. */
					__( 'Agreed to: %1$s Time: %2$s. Address: %3$s. Browser: %4$s.', 'wpcredits-program-manager' ),
					isset( $consent['sentence'] ) ? (string) $consent['sentence'] : '',
					self::time_text( isset( $consent['at'] ) ? (int) $consent['at'] : 0 ),
					isset( $consent['ip'] ) ? (string) $consent['ip'] : '',
					isset( $consent['agent'] ) ? (string) $consent['agent'] : ''
				)
			);
		}

		echo '</p></details>';
	}

	/**
	 * The presses the registration's state allows.
	 *
	 * @param WP_Post $post  The registration.
	 * @param string  $state Its state.
	 * @param string  $view  The view to come back to.
	 */
	private static function render_presses( WP_Post $post, $state, $view ) {
		if ( WPCPM_Student_Registration::STATE_FAILED === $state || ( WPCPM_Student_Registration::STATE_WAITING === $state && self::is_held( $post ) ) ) {
			self::render_form( $post, $view, self::ACTION_WRITE, __( 'Write it now', 'wpcredits-program-manager' ), 'button-primary', '' );
		} elseif ( WPCPM_Student_Registration::STATE_SPAM === $state ) {
			$name = WPCPM_Student_Registration::name_of( $post );

			self::render_form( $post, $view, self::ACTION_RESTORE, __( 'Put back', 'wpcredits-program-manager' ), 'button', '' );
			self::render_form(
				$post,
				$view,
				self::ACTION_DELETE,
				__( 'Delete for good', 'wpcredits-program-manager' ),
				'button',
				sprintf(
					/* translators: %s: the name on the registration. */
					__( 'Delete the registration of %s for good? It cannot be brought back.', 'wpcredits-program-manager' ),
					'' !== $name ? $name : __( '(no name)', 'wpcredits-program-manager' )
				)
			);
		}
	}

	/**
	 * One press: a form with its own nonce that comes back to the view.
	 *
	 * @param WP_Post $post    The registration.
	 * @param string  $view    The view to come back to.
	 * @param string  $action  The press.
	 * @param string  $label   The button's words.
	 * @param string  $button  The button's class.
	 * @param string  $confirm The question to ask first, or ''.
	 */
	private static function render_form( WP_Post $post, $view, $action, $label, $button, $confirm ) {
		printf(
			'<form class="wpcpm-app-action" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s"%3$s>',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Working', 'wpcredits-program-manager' ),
			'' !== $confirm ? ' data-wpcpm-confirm="' . esc_attr( $confirm ) . '"' : ''
		);
		wp_nonce_field( $action . '_' . (int) $post->ID );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );
		printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( self::FIELD_REGISTRATION ), (int) $post->ID );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::VIEW_ARG ), esc_attr( $view ) );
		printf( '<button type="submit" class="%1$s">%2$s</button>', esc_attr( $button ), esc_html( $label ) );
		echo '</form>';
	}
}
