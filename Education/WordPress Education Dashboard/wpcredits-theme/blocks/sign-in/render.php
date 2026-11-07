<?php
/**
 * Server render for the sign-in section: a card each for the Student Report Card, the Mentor Report
 * Card, the Institution Dashboard and the Sponsor Dashboard, signing in to the page or opening it.
 *
 * **A block and not a pattern, because what each card links to depends on who is looking.** A pattern
 * placed in a template is written into the template as static blocks the moment someone saves the
 * template in the Site Editor, which would freeze one visitor's links for everyone; a block is drawn
 * again for every request. Signed out, a card's link is the login screen with the card's page to
 * return to (the plugin's login redirect honors it, so the visitor lands on that page). Signed in, a
 * card reads "Open" only for a page this reader can open, as the plugin's own tests answer it (a
 * student, a mentor, an institution's or a sponsor's member, or an Administrator, who opens every
 * page); the other cards lead to that role's public way in, the student registration form while it is
 * open, the mentor application, the institution application and, while the Partners page is live, the
 * sponsor application, and a role with no way in right now has no card (theme 1.32.13,
 * WordPress/WPCredits#294: until then every card said "Open" to everyone signed in, and three of them
 * led a mentor to the access notice). A reader who opens nothing reads "Get involved" over the ways in.
 *
 * **A page that is not published has no card, and with none the block prints nothing at all**, not an
 * empty padded box: the section is the block itself, placed in the template, so there is no group
 * around it to be left behind. The grid says how many cards it holds (`--wpc-cards`, 1 to 4) and
 * hub.css gives each a column from 900px, so three cards fill the row as four do.
 *
 * The head is the programs section's (`.wpc-hub-head`) and each card is a door's (`wpc-hub-door`),
 * with the link line stretched over the card and the focus ring around the whole card, so none of
 * their rules is written again. Each link carries its whole name (`aria-label`), one translated
 * sentence that starts with the words the link shows and names its page, "Sign in to your Student
 * Report Card", so a list of links does not hold four that say the same and a person who speaks what
 * they see is understood; nothing describes the link by the card's title as well, which would be heard
 * twice. The block is `supports.multiple` false, so the IDs here, the heading's included, are never
 * printed twice.
 *
 * @package WPCredits_Theme
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Each dashboard's page as the plugin reports it, '' while it is not published, in the order the
// cards are drawn in; a page that is not there leaves its card out.
$wpcredits_pages = array_filter(
	array(
		'students'     => wpcredits_student_page_url(),
		'mentors'      => wpcredits_mentor_page_url(),
		'institutions' => wpcredits_institution_page_url(),
		'sponsors'     => wpcredits_sponsor_page_url(),
	),
	'strlen'
);

if ( ! $wpcredits_pages ) {
	return;
}

$wpcredits_signed_in = is_user_logged_in();

// A card's eyebrow names who it is for, its title the page by its product name.
$wpcredits_cards = array(
	'students'     => array(
		'eyebrow' => __( 'Students', 'wpcredits-theme' ),
		'title'   => __( 'Student Report Card', 'wpcredits-theme' ),
	),
	'mentors'      => array(
		'eyebrow' => __( 'Mentors', 'wpcredits-theme' ),
		'title'   => __( 'Mentor Report Card', 'wpcredits-theme' ),
	),
	'institutions' => array(
		'eyebrow' => __( 'Institutions', 'wpcredits-theme' ),
		'title'   => __( 'Institution Dashboard', 'wpcredits-theme' ),
	),
	'sponsors'     => array(
		'eyebrow' => __( 'Sponsors', 'wpcredits-theme' ),
		'title'   => __( 'Sponsor Dashboard', 'wpcredits-theme' ),
	),
);

// Signed in: which pages this reader opens, by the plugin's own tests, each guarded so an older plugin
// opens nothing; an Administrator opens every page. And each role's public way in for the others,
// '' where there is none right now. A card with neither is left out.
$wpcredits_opens = array();
$wpcredits_ways  = array();

if ( $wpcredits_signed_in ) {
	$wpcredits_manages = wpcredits_plugin_active() && class_exists( 'WPCPM_Roles' ) && current_user_can( WPCPM_Roles::CAP_MANAGE );
	$wpcredits_student = class_exists( 'WPCPM_Students_Dashboard' ) && method_exists( 'WPCPM_Students_Dashboard', 'is_student' ) && (bool) WPCPM_Students_Dashboard::is_student();

	$wpcredits_opens = array(
		'students'     => $wpcredits_manages || $wpcredits_student,
		'mentors'      => $wpcredits_manages || wpcredits_viewer_is_mentor(),
		'institutions' => $wpcredits_manages || wpcredits_partners_acts_for( 'institution' ),
		'sponsors'     => $wpcredits_manages || wpcredits_partners_acts_for( 'sponsor' ),
	);
	$wpcredits_ways  = array(
		'students'     => wpcredits_student_registration_url(),
		'mentors'      => wpcredits_mentor_application_url(),
		'institutions' => wpcredits_hub_url( 'institution_application' ),
		'sponsors'     => wpcredits_partners_live() ? wpcredits_hub_url( 'sponsor_application' ) : '',
	);

	foreach ( $wpcredits_pages as $wpcredits_id => $wpcredits_page ) {
		if ( ! $wpcredits_opens[ $wpcredits_id ] && '' === $wpcredits_ways[ $wpcredits_id ] ) {
			unset( $wpcredits_pages[ $wpcredits_id ] );
		}
	}

	if ( ! $wpcredits_pages ) {
		return;
	}
}

// Of the cards drawn, whether any opens, and whether any is a way in.
$wpcredits_opens_any = (bool) array_filter( array_intersect_key( $wpcredits_opens, $wpcredits_pages ) );
$wpcredits_ways_any  = $wpcredits_signed_in && count( array_filter( array_intersect_key( $wpcredits_opens, $wpcredits_pages ) ) ) < count( $wpcredits_pages );

// Each link's whole name, for the state the reader is in: the words the link shows, then the page it
// is for, as one sentence a translator sees whole. Only the sets a drawn card takes a name from are looked up, so a visitor's and an Administrator's pages look up exactly what they print.
$wpcredits_names = array();

if ( ! $wpcredits_signed_in ) {
	$wpcredits_names['sign-in'] = array(
		/* translators: A link's name for screen readers. Start it with the words the link shows ("Sign in"). */
		'students'     => __( 'Sign in to your Student Report Card', 'wpcredits-theme' ),
		/* translators: A link's name for screen readers. Start it with the words the link shows ("Sign in"). */
		'mentors'      => __( 'Sign in to your Mentor Report Card', 'wpcredits-theme' ),
		/* translators: A link's name for screen readers. Start it with the words the link shows ("Sign in"). */
		'institutions' => __( 'Sign in to your Institution Dashboard', 'wpcredits-theme' ),
		/* translators: A link's name for screen readers. Start it with the words the link shows ("Sign in"). */
		'sponsors'     => __( 'Sign in to your Sponsor Dashboard', 'wpcredits-theme' ),
	);
}

if ( $wpcredits_opens_any ) {
	$wpcredits_names['open'] = array(
		/* translators: A link's name for screen readers. Start it with the words the link shows ("Open"). */
		'students'     => __( 'Open your Student Report Card', 'wpcredits-theme' ),
		/* translators: A link's name for screen readers. Start it with the words the link shows ("Open"). */
		'mentors'      => __( 'Open your Mentor Report Card', 'wpcredits-theme' ),
		/* translators: A link's name for screen readers. Start it with the words the link shows ("Open"). */
		'institutions' => __( 'Open your Institution Dashboard', 'wpcredits-theme' ),
		/* translators: A link's name for screen readers. Start it with the words the link shows ("Open"). */
		'sponsors'     => __( 'Open your Sponsor Dashboard', 'wpcredits-theme' ),
	);
}

if ( $wpcredits_ways_any ) {
	$wpcredits_names['way'] = array(
		/* translators: A link's name for screen readers. Start it with the words the link shows ("Register"). */
		'students'     => __( 'Register as a student', 'wpcredits-theme' ),
		/* translators: A link's name for screen readers. Start it with the words the link shows ("Apply"). */
		'mentors'      => __( 'Apply to mentor students', 'wpcredits-theme' ),
		/* translators: A link's name for screen readers. Start it with the words the link shows ("Apply"). */
		'institutions' => __( 'Apply to partner as an institution', 'wpcredits-theme' ),
		/* translators: A link's name for screen readers. Start it with the words the link shows ("Apply"). */
		'sponsors'     => __( 'Apply to become a sponsor', 'wpcredits-theme' ),
	);
}

// The head: for a visitor, and for a reader who opens at least one page, "Already in a program?" over
// "Sign in to your page" or "Open your page"; for a signed-in reader who opens none, the ways in alone.
if ( ! $wpcredits_signed_in ) {
	$wpcredits_eyebrow = __( 'Already in a program?', 'wpcredits-theme' );
	$wpcredits_heading = __( 'Sign in to your page', 'wpcredits-theme' );
} elseif ( $wpcredits_opens_any ) {
	$wpcredits_eyebrow = __( 'Already in a program?', 'wpcredits-theme' );
	$wpcredits_heading = __( 'Open your page', 'wpcredits-theme' );
} else {
	$wpcredits_eyebrow = __( 'Not in a program yet?', 'wpcredits-theme' );
	$wpcredits_heading = __( 'Get involved', 'wpcredits-theme' );
}

// The wrapper: wide, and named by the heading, whose ID is fixed because the block is printed once.
$wpcredits_wrapper = array(
	'class'           => 'alignwide wpc-hub-sign-in',
	'aria-labelledby' => 'wpc-hub-sign-in-title',
);

?>
<section <?php echo get_block_wrapper_attributes( $wpcredits_wrapper ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already-escaped attribute string from core. ?>>
	<div class="wp-block-group wpc-hub-head">
		<p class="is-style-eyebrow"><?php echo esc_html( $wpcredits_eyebrow ); ?></p>
		<h2 class="wp-block-heading" id="wpc-hub-sign-in-title"><?php echo esc_html( $wpcredits_heading ); ?></h2>
	</div>
	<div class="wpc-hub-sign-in__grid" style="--wpc-cards:<?php echo (int) count( $wpcredits_pages ); ?>">
		<?php
		foreach ( $wpcredits_pages as $wpcredits_id => $wpcredits_page ) :
			$wpcredits_title_id = 'wpc-hub-sign-in-' . $wpcredits_id;

			if ( ! $wpcredits_signed_in ) {
				$wpcredits_href = wp_login_url( $wpcredits_page );
				/* translators: A card's link when the reader is signed out: a verb, it goes to the login screen and then to the page the card names. */
				$wpcredits_link = _x( 'Sign in', 'a card\'s link to the page it names', 'wpcredits-theme' );
				$wpcredits_name = $wpcredits_names['sign-in'][ $wpcredits_id ];
			} elseif ( $wpcredits_opens[ $wpcredits_id ] ) {
				$wpcredits_href = $wpcredits_page;
				/* translators: A card's link when the reader is signed in: a verb, it opens the page the card names. */
				$wpcredits_link = _x( 'Open', 'a card\'s link to the page it names', 'wpcredits-theme' );
				$wpcredits_name = $wpcredits_names['open'][ $wpcredits_id ];
			} else {
				$wpcredits_href = $wpcredits_ways[ $wpcredits_id ];
				$wpcredits_link = 'students' === $wpcredits_id
					/* translators: The Students card's link for a signed-in reader who is not a student: a verb, it goes to the student registration form. */
					? _x( 'Register', 'a card\'s link to the way into the program it names', 'wpcredits-theme' )
					/* translators: A card's link for a signed-in reader who is not in that role: a verb, it goes to that role's application form. */
					: _x( 'Apply', 'a card\'s link to the way into the program it names', 'wpcredits-theme' );
				$wpcredits_name = $wpcredits_names['way'][ $wpcredits_id ];
			}
			?>
			<div class="wp-block-group is-style-card wpc-hub-door wpc-hub-sign-in__card">
				<p class="is-style-eyebrow"><?php echo esc_html( $wpcredits_cards[ $wpcredits_id ]['eyebrow'] ); ?></p>
				<h3 class="wp-block-heading" id="<?php echo esc_attr( $wpcredits_title_id ); ?>"><?php echo esc_html( $wpcredits_cards[ $wpcredits_id ]['title'] ); ?></h3>
				<p class="wpc-hub-door__go"><a href="<?php echo esc_url( $wpcredits_href ); ?>" aria-label="<?php echo esc_attr( $wpcredits_name ); ?>"><?php echo esc_html( $wpcredits_link ); ?><span aria-hidden="true"> →</span></a></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>
