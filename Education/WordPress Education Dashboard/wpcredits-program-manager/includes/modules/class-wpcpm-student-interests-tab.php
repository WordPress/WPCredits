<?php
/**
 * Students module - the Interests tab (1.123.64).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where an Administrator reads the interest students sent through the student interest form: the
 * Students screen's Interests tab, in five views by state, newest first, each row with the
 * student, the institution, the representative to write to and the student's answers.
 *
 * **The presses**, five, each for the states it takes (`WPCPM_Student_Interest::can_move()` and
 * `can_delete()`):
 *
 * - Mark contacted, on a new or a held interest: an Administrator wrote to the representative.
 * - Close, on a new, a held or a contacted one: nothing more to do.
 * - Mark as spam, on a new or a held one.
 * - Put back to New, on a held, a spam or a closed one.
 * - Delete, on a spam or a closed one: the interest is removed for good, after a question.
 *
 * Each press asks the capability first, then the nonce keyed to the press and the row, then the
 * interest, which must be in a state the press takes, and comes back to the view it was made on.
 * No press sends an email or writes anywhere but the interest: following one up is done by hand.
 */
class WPCPM_Student_Interests_Tab {

	const ACTION_CONTACTED = 'wpcpm_sint_contacted';
	const ACTION_CLOSE     = 'wpcpm_sint_close';
	const ACTION_SPAM      = 'wpcpm_sint_spam';
	const ACTION_RESTORE   = 'wpcpm_sint_restore';
	const ACTION_DELETE    = 'wpcpm_sint_delete';

	/** The field a press names its interest in. */
	const FIELD_INTEREST = 'wpcpm_sint_id';

	/** The query argument the tab's views travel in. */
	const VIEW_ARG = 'sint_view';

	/** How many interests a page of a view draws, the newest first. */
	const LIST_MAX = 50;

	/** The query argument the page of a view travels in, from 1. */
	const PAGE_ARG = 'sint_paged';

	/** The flash channel the Students screen reads its outcomes from. */
	const FLASH = 'students_admin';

	/**
	 * Hooks: the five presses, for a signed-in Administrator alone (`admin_post_`, never `nopriv`).
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_CONTACTED, array( __CLASS__, 'handle_contacted' ) );
		add_action( 'admin_post_' . self::ACTION_CLOSE, array( __CLASS__, 'handle_close' ) );
		add_action( 'admin_post_' . self::ACTION_SPAM, array( __CLASS__, 'handle_spam' ) );
		add_action( 'admin_post_' . self::ACTION_RESTORE, array( __CLASS__, 'handle_restore' ) );
		add_action( 'admin_post_' . self::ACTION_DELETE, array( __CLASS__, 'handle_delete' ) );
	}

	/**
	 * The tab's five views, one a state, slug => label, in the bar's order. The slug is the state.
	 *
	 * @return array<string, string>
	 */
	public static function views() {
		return array(
			WPCPM_Student_Interest::STATE_NEW       => __( 'New', 'wpcredits-program-manager' ),
			WPCPM_Student_Interest::STATE_HELD      => __( 'Held', 'wpcredits-program-manager' ),
			WPCPM_Student_Interest::STATE_CONTACTED => __( 'Contacted', 'wpcredits-program-manager' ),
			WPCPM_Student_Interest::STATE_CLOSED    => __( 'Closed', 'wpcredits-program-manager' ),
			WPCPM_Student_Interest::STATE_SPAM      => __( 'Spam', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The view the request names, or New.
	 *
	 * @return string A key of `views()`.
	 */
	public static function current_view() {
		$view = WPCPM_Request::key( self::VIEW_ARG );

		return isset( self::views()[ $view ] ) ? $view : WPCPM_Student_Interest::STATE_NEW;
	}

	/**
	 * The tab's address at one view.
	 *
	 * @param string $view A key of `views()`.
	 * @return string
	 */
	public static function view_url( $view = WPCPM_Student_Interest::STATE_NEW ) {
		$url = admin_url( 'admin.php?page=wpcpm-students&tab=interests' );

		return WPCPM_Student_Interest::STATE_NEW === $view || ! isset( self::views()[ $view ] ) ? $url : add_query_arg( self::VIEW_ARG, $view, $url );
	}

	/**
	 * What each press can leave, in the Administrator's words.
	 *
	 * @return array<string, array{0: string, 1: string}> Status => notice class and sentence.
	 */
	public static function messages() {
		return array(
			'sint-contacted' => array( 'success', __( 'The interest is marked as contacted.', 'wpcredits-program-manager' ) ),
			'sint-closed'    => array( 'success', __( 'The interest is closed. It is deleted 365 days from now.', 'wpcredits-program-manager' ) ),
			'sint-spam'      => array( 'success', __( 'The interest is marked as spam. It is deleted 30 days from now.', 'wpcredits-program-manager' ) ),
			'sint-restored'  => array( 'success', __( 'The interest is put back to New.', 'wpcredits-program-manager' ) ),
			'sint-deleted'   => array( 'success', __( 'The interest is deleted for good.', 'wpcredits-program-manager' ) ),
			'sint-unknown'   => array( 'error', __( 'There is no interest with that ID.', 'wpcredits-program-manager' ) ),
			'sint-state'     => array( 'error', __( 'That interest is not in a state this press applies to. Reload the page to see where it got to.', 'wpcredits-program-manager' ) ),
		);
	}

	/*
	 * The presses
	 * --------------------------------------------------------------------
	 */

	/**
	 * What every press does first: the capability, then the nonce keyed to the press and the row,
	 * then the interest, which must be one.
	 *
	 * @param string $action The press.
	 * @return WP_Post
	 */
	private static function begin( $action ) {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( self::FIELD_INTEREST );

		check_admin_referer( $action . '_' . $post_id );

		$post = WPCPM_Student_Interest::interest( $post_id );

		if ( null === $post ) {
			self::leave( 'sint-unknown' );
		}

		return $post;
	}

	/**
	 * One press that moves an interest to a state: refused when the interest's own state does not
	 * lead there, which `WPCPM_Student_Interest::set_state()` decides.
	 *
	 * @param string $action The press.
	 * @param string $state  The state it moves the interest to.
	 * @param string $status The notice it leaves when it does.
	 */
	private static function move( $action, $state, $status ) {
		$post = self::begin( $action );

		if ( ! WPCPM_Student_Interest::set_state( (int) $post->ID, $state, get_current_user_id() ) ) {
			self::leave( 'sint-state' );
		}

		self::leave( $status );
	}

	/**
	 * Mark contacted: a new or a held interest whose representative an Administrator wrote to.
	 */
	public static function handle_contacted() {
		self::move( self::ACTION_CONTACTED, WPCPM_Student_Interest::STATE_CONTACTED, 'sint-contacted' );
	}

	/**
	 * Close: a new, a held or a contacted interest with nothing more to do.
	 */
	public static function handle_close() {
		self::move( self::ACTION_CLOSE, WPCPM_Student_Interest::STATE_CLOSED, 'sint-closed' );
	}

	/**
	 * Mark as spam: a new or a held interest.
	 */
	public static function handle_spam() {
		self::move( self::ACTION_SPAM, WPCPM_Student_Interest::STATE_SPAM, 'sint-spam' );
	}

	/**
	 * Put back to New: a held, a spam or a closed interest. Nobody is emailed again.
	 */
	public static function handle_restore() {
		self::move( self::ACTION_RESTORE, WPCPM_Student_Interest::STATE_NEW, 'sint-restored' );
	}

	/**
	 * Delete: a spam or a closed interest is removed for good.
	 */
	public static function handle_delete() {
		$post = self::begin( self::ACTION_DELETE );

		if ( ! WPCPM_Student_Interest::delete( (int) $post->ID ) ) {
			self::leave( 'sint-state' );
		}

		self::leave( 'sint-deleted' );
	}

	/**
	 * Back to the tab with a one-shot outcome, at the view the press was made on.
	 *
	 * @param string $status A key of `messages()`.
	 */
	private static function leave( $status ) {
		$view = WPCPM_Request::posted_key( self::VIEW_ARG, WPCPM_Student_Interest::STATE_NEW );

		WPCPM_Flash::set( self::FLASH, $status );
		wp_safe_redirect( self::view_url( $view ) );
		exit;
	}

	/*
	 * The tab
	 * --------------------------------------------------------------------
	 */

	/**
	 * The Interests tab: the views with their counts, then the view's interests, newest first. The
	 * capability is asked here as well as by the screen: every answer a stranger typed is printed
	 * below.
	 */
	public static function render() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		$view  = self::current_view();
		$views = self::views();
		// One page of the view, and how many pages there are: every interest is reached, however
		// many a view holds.
		$paged = WPCPM_Student_Interest::page_of( array( $view ), max( 1, WPCPM_Request::id( self::PAGE_ARG ) ), self::LIST_MAX );
		$total = $paged['total'];
		$rows  = $paged['rows'];

		echo '<div class="wpcpm-card" id="wpcpm-student-interests">';
		printf( '<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>', esc_html__( 'Interest from students', 'wpcredits-program-manager' ), esc_html( number_format_i18n( WPCPM_Student_Interest::count_new() ) ) );
		// The sentence, then the help link to the job in the guide, which a site with no guide leaves out.
		$help = WPCPM_Guide_Jobs::link( 'student-interest', __( 'Interest from students', 'wpcredits-program-manager' ) );
		echo '<p class="description">' . esc_html__( 'Students whose institution is not in the program yet, who said through the form on the site that they would like to take part, newest first. Each names a representative of the institution; the site sends the representative nothing, so write to them yourself, then mark the interest as contacted. Nothing here is written to Airtable.', 'wpcredits-program-manager' ) . ( '' === $help ? '' : ' ' . $help ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in esc_html__() and WPCPM_Guide_Jobs::link().

		echo '<ul class="subsubsub">';

		$links = array();

		foreach ( $views as $slug => $label ) {
			$links[] = sprintf(
				'<li class="%1$s"><a href="%2$s"%3$s>%4$s <span class="count">(%5$s)</span></a>',
				esc_attr( $slug ),
				esc_url( self::view_url( $slug ) ),
				$slug === $view ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				esc_html( number_format_i18n( WPCPM_Student_Interest::count_in( array( $slug ) ) ) )
			);
		}

		echo implode( ' |</li>', $links ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each link is built escaped above.
		echo '</ul><div class="clear"></div>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'Nothing here.', 'wpcredits-program-manager' ) . '</p></div>';

			return;
		}

		$pages = self::pages_nav( $view, $paged['page'], $paged['pages'], $total );

		echo $pages; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built escaped by pages_nav().
		echo '<ol class="wpcpm-queue">';

		foreach ( $rows as $post ) {
			self::render_row( $post, $view );
		}

		echo '</ol>';
		echo $pages; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built escaped by pages_nav().
		echo '</div>';
	}

	/**
	 * The line that leads to a view's other pages, or '' for a view of one page: which page this is,
	 * how many interests the view holds, and Newer and Older, each a link only when there is such a
	 * page.
	 *
	 * @param string $view  The view.
	 * @param int    $page  The page drawn, from 1.
	 * @param int    $pages How many pages the view has.
	 * @param int    $total How many interests the view holds.
	 * @return string
	 */
	private static function pages_nav( $view, $page, $pages, $total ) {
		if ( $pages < 2 ) {
			return '';
		}

		$said = sprintf(
			/* translators: 1: the page shown, 2: how many pages there are, 3: how many interests the view holds. */
			__( 'Page %1$s of %2$s, %3$s interests in all.', 'wpcredits-program-manager' ),
			number_format_i18n( $page ),
			number_format_i18n( $pages ),
			number_format_i18n( $total )
		);
		$newer = $page > 1 ? sprintf( '<a href="%1$s">%2$s</a>', esc_url( self::page_url( $view, $page - 1 ) ), esc_html__( 'Newer', 'wpcredits-program-manager' ) ) : '';
		$older = $page < $pages ? sprintf( '<a href="%1$s">%2$s</a>', esc_url( self::page_url( $view, $page + 1 ) ), esc_html__( 'Older', 'wpcredits-program-manager' ) ) : '';

		return '<p class="description wpcpm-queue__pages">' . esc_html( $said ) . ( '' !== $newer ? ' ' . $newer : '' ) . ( '' !== $older ? ' ' . $older : '' ) . '</p>';
	}

	/**
	 * A view's address at one of its pages: the view's own for the first.
	 *
	 * @param string $view A key of `views()`.
	 * @param int    $page The page, from 1.
	 * @return string
	 */
	public static function page_url( $view, $page ) {
		$url = self::view_url( $view );

		return (int) $page > 1 ? add_query_arg( self::PAGE_ARG, (int) $page, $url ) : $url;
	}

	/**
	 * One interest: when, who, where it stands, then the institution, the representative and the
	 * answers, the consent folded, and the presses.
	 *
	 * @param WP_Post $post The interest.
	 * @param string  $view The view to come back to.
	 */
	private static function render_row( WP_Post $post, $view ) {
		$state  = WPCPM_Student_Interest::state_of( $post );
		$fields = WPCPM_Student_Interest::fields_of( $post );
		$name   = WPCPM_Student_Interest::answer( $fields, 'name' );
		$format = (string) get_option( 'date_format', 'F j, Y' );

		printf( '<li class="wpcpm-queue-item" id="%s">', esc_attr( 'wpcpm-sint-' . (int) $post->ID ) );
		printf(
			'<h3 class="wpcpm-queue-title"><span class="wpcpm-inst-name__text">%1$s</span> <span class="wpcpm-inst-muted">%2$s</span></h3>',
			esc_html( '' !== $name ? $name : __( '(no name)', 'wpcredits-program-manager' ) ),
			self::mailto( WPCPM_Student_Interest::answer( $fields, 'email' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- mailto() answers escaped markup.
		);
		printf(
			'<p class="wpcpm-queue-age">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: the date the interest was received, 2: its reference, such as SINT-2026-0503. */
					__( 'Received %1$s. Reference %2$s.', 'wpcredits-program-manager' ),
					wp_date( $format, WPCPM_Student_Interest::created_of( $post ) ),
					WPCPM_Student_Interest::reference_of( $post )
				)
			)
		);

		self::render_state( $post, $state );
		self::render_answers( $fields );
		self::render_consent( $post );
		self::render_presses( $post, $state, $view );

		echo '</li>';
	}

	/**
	 * An address as a link that opens an email to it, or '' for none. A stored address that is no
	 * address is printed as text and never as a link.
	 *
	 * @param string $email The address.
	 * @return string Escaped markup.
	 */
	private static function mailto( $email ) {
		$email = trim( (string) $email );

		if ( '' === $email ) {
			return '';
		}

		if ( ! is_email( $email ) ) {
			return esc_html( $email );
		}

		// Each side of the address is encoded, so the link writes to that mailbox and nothing else:
		// an address may hold `?`, `&` and `=`, which in a mail link would start a Cc or a Bcc.
		$at     = (int) strrpos( $email, '@' );
		$target = rawurlencode( substr( $email, 0, $at ) ) . '@' . rawurlencode( substr( $email, $at + 1 ) );

		return sprintf( '<a href="%1$s">%2$s</a>', esc_url( 'mailto:' . $target ), esc_html( $email ) );
	}

	/**
	 * A state in words.
	 *
	 * @param string $state The stored state.
	 * @return string
	 */
	public static function state_label( $state ) {
		$views = self::views();

		return isset( $views[ $state ] ) ? $views[ $state ] : __( 'Not recorded', 'wpcredits-program-manager' );
	}

	/**
	 * What each signal means, in a sentence an Administrator reads beside the state: the slugs the
	 * form raises (`WPCPM_Student_Interest_Form::handle_submit()`), written out.
	 *
	 * @return array<string, string> Signal slug => sentence.
	 */
	public static function signal_texts() {
		return array(
			'honeypot'     => __( 'A field no person sees was filled in.', 'wpcredits-program-manager' ),
			'dwell'        => __( 'The form\'s time check failed.', 'wpcredits-program-manager' ),
			'dwell-fast'   => __( 'The form was sent within seconds of being opened.', 'wpcredits-program-manager' ),
			'disallowed'   => __( 'It matches the site\'s disallowed comment keys.', 'wpcredits-program-manager' ),
			'links'        => __( 'It holds several links.', 'wpcredits-program-manager' ),
			'site-ceiling' => __( 'More than 40 arrived that day.', 'wpcredits-program-manager' ),
			'duplicate'    => __( 'The same student sent an interest for the same institution in the 30 days before.', 'wpcredits-program-manager' ),
			'mail-ceiling' => __( 'The student was sent no acknowledgement: the day\'s limit on emails was reached.', 'wpcredits-program-manager' ),
			'mail-failed'  => __( 'The acknowledgement to the student could not be handed off.', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * Where the interest stands: its state, who last changed it and when, and what the form's
	 * checks raised, each in a sentence.
	 *
	 * @param WP_Post $post  The interest.
	 * @param string  $state Its state.
	 */
	private static function render_state( WP_Post $post, $state ) {
		$changed = WPCPM_Student_Interest::changed_of( $post );

		echo '<p class="wpcpm-sreg-outcome"><strong>' . esc_html( self::state_label( $state ) ) . '</strong>';

		if ( $changed['at'] > 0 ) {
			$user = $changed['by'] > 0 ? get_userdata( $changed['by'] ) : false;

			echo ' ' . esc_html(
				$user
					? sprintf(
						/* translators: 1: the date and time the state was changed, 2: the name of the Administrator who changed it. */
						__( 'Changed on %1$s by %2$s.', 'wpcredits-program-manager' ),
						self::time_text( $changed['at'] ),
						(string) $user->display_name
					)
					: sprintf(
						/* translators: %s: the date and time the state was changed. */
						__( 'Changed on %s.', 'wpcredits-program-manager' ),
						self::time_text( $changed['at'] )
					)
			);
		}

		echo '</p>';

		$texts = self::signal_texts();

		foreach ( WPCPM_Student_Interest::signals_of( $post ) as $signal ) {
			printf( '<p class="wpcpm-sreg-mail">%s</p>', esc_html( isset( $texts[ $signal ] ) ? $texts[ $signal ] : $signal ) );
		}
	}

	/**
	 * The institution, the representative and the student's answers, in a table.
	 *
	 * @param array $fields The answers.
	 */
	private static function render_answers( array $fields ) {
		// What is left of a list once its empty entries are gone.
		$filled = static function ( $value ) {
			return '' !== (string) $value;
		};

		$place   = implode( ', ', array_filter( array( WPCPM_Student_Interest::answer( $fields, 'city' ), WPCPM_Student_Interest::answer( $fields, 'country_name' ) ), $filled ) );
		$website = WPCPM_Student_Interest::answer( $fields, 'website' );
		$choices = isset( $fields['internships'] ) && is_array( $fields['internships'] ) ? array_map( 'trim', array_map( 'strval', $fields['internships'] ) ) : array();

		$institution = array_filter(
			array(
				esc_html( WPCPM_Student_Interest::answer( $fields, 'institution' ) ),
				esc_html( $place ),
				// A stored address is linked only while it is an http or https one, which is all the
				// form keeps; anything else is printed as text.
				'' !== $website && preg_match( '#^https?://#i', $website )
					? sprintf( '<a href="%1$s" rel="noopener noreferrer nofollow">%2$s</a>', esc_url( $website ), esc_html( $website ) )
					: esc_html( $website ),
			),
			$filled
		);

		$representative = array_filter(
			array(
				esc_html( WPCPM_Student_Interest::answer( $fields, 'representative' ) ),
				self::mailto( WPCPM_Student_Interest::answer( $fields, 'contact_email' ) ),
			),
			$filled
		);

		$cells = array(
			array( __( 'Institution', 'wpcredits-program-manager' ), implode( '<br />', $institution ) ),
			array( __( 'Department', 'wpcredits-program-manager' ), esc_html( WPCPM_Student_Interest::answer( $fields, 'department' ) ) ),
			array( __( 'Representative', 'wpcredits-program-manager' ), implode( '<br />', $representative ) ),
			array( __( 'Internships or practices', 'wpcredits-program-manager' ), esc_html( implode( ', ', $choices ) ) ),
			array( __( 'Anything else', 'wpcredits-program-manager' ), nl2br( esc_html( WPCPM_Student_Interest::answer( $fields, 'anything' ) ) ) ),
		);

		echo '<table class="widefat striped wpcpm-list wpcpm-app-answers"><tbody>';

		foreach ( $cells as $pair ) {
			$cell = '' !== trim( $pair[1] ) ? $pair[1] : '<span class="wpcpm-inst-muted">' . esc_html__( 'no answer', 'wpcredits-program-manager' ) . '</span>';

			printf( '<tr><th scope="row">%1$s</th><td>%2$s</td></tr>', esc_html( $pair[0] ), $cell ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each cell is built escaped above.
		}

		echo '</tbody></table>';
	}

	/**
	 * What the student agreed to, folded under the interest: the two sentences, when, the address
	 * shortened and the browser.
	 *
	 * @param WP_Post $post The interest.
	 */
	private static function render_consent( WP_Post $post ) {
		$consent = WPCPM_Student_Interest::consent_of( $post );

		echo '<details class="wpcpm-sreg-details"><summary>' . esc_html__( 'Consent', 'wpcredits-program-manager' ) . '</summary>';
		echo '<p class="wpcpm-sreg-consent">';

		if ( array() === $consent ) {
			echo esc_html__( 'No consent is kept.', 'wpcredits-program-manager' );
		} else {
			echo esc_html(
				sprintf(
					/* translators: 1: the sentence about the representative the student agreed to, 2: the privacy sentence, 3: when, 4: the address it was agreed from, shortened, 5: the browser. */
					__( 'Agreed to: %1$s %2$s Time: %3$s. Address: %4$s. Browser: %5$s.', 'wpcredits-program-manager' ),
					isset( $consent['told'] ) ? (string) $consent['told'] : '',
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
	 * The presses the interest's state allows, in one order on every row.
	 *
	 * @param WP_Post $post  The interest.
	 * @param string  $state Its state.
	 * @param string  $view  The view to come back to.
	 */
	private static function render_presses( WP_Post $post, $state, $view ) {
		if ( WPCPM_Student_Interest::can_move( $state, WPCPM_Student_Interest::STATE_CONTACTED ) ) {
			self::render_form( $post, $view, self::ACTION_CONTACTED, __( 'Mark contacted', 'wpcredits-program-manager' ), 'button-primary', '' );
		}

		if ( WPCPM_Student_Interest::can_move( $state, WPCPM_Student_Interest::STATE_CLOSED ) ) {
			self::render_form( $post, $view, self::ACTION_CLOSE, __( 'Close', 'wpcredits-program-manager' ), 'button', '' );
		}

		if ( WPCPM_Student_Interest::can_move( $state, WPCPM_Student_Interest::STATE_SPAM ) ) {
			self::render_form( $post, $view, self::ACTION_SPAM, __( 'Mark as spam', 'wpcredits-program-manager' ), 'button', '' );
		}

		if ( WPCPM_Student_Interest::can_move( $state, WPCPM_Student_Interest::STATE_NEW ) ) {
			self::render_form( $post, $view, self::ACTION_RESTORE, __( 'Put back to New', 'wpcredits-program-manager' ), 'button', '' );
		}

		if ( WPCPM_Student_Interest::can_delete( $state ) ) {
			$name = WPCPM_Student_Interest::answer( WPCPM_Student_Interest::fields_of( $post ), 'name' );

			self::render_form(
				$post,
				$view,
				self::ACTION_DELETE,
				__( 'Delete', 'wpcredits-program-manager' ),
				'button',
				sprintf(
					/* translators: %s: the name on the interest. */
					__( 'Delete the interest of %s for good? It cannot be brought back.', 'wpcredits-program-manager' ),
					'' !== $name ? $name : __( '(no name)', 'wpcredits-program-manager' )
				)
			);
		}
	}

	/**
	 * One press: a form with its own nonce that comes back to the view.
	 *
	 * @param WP_Post $post    The interest.
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
		printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( self::FIELD_INTEREST ), (int) $post->ID );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::VIEW_ARG ), esc_attr( $view ) );
		printf( '<button type="submit" class="%1$s">%2$s</button>', esc_attr( $button ), esc_html( $label ) );
		echo '</form>';
	}
}
