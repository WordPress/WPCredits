<?php
/**
 * Title: Four audiences
 * Slug: wpcredits/audiences
 * Categories: wpcredits
 * Description: A pale band with one column per audience the program serves - students, mentors, institutions and program managers.
 * Keywords: audiences, roles, students, mentors
 * Viewport Width: 1400
 *
 * One column per module in WPCredits Program Manager, in the plugin's own order,
 * so the site and the admin menu describe the program the same way.
 *
 * @package WPCredits_Theme
 */

$wpcredits_audiences = array(
	array(
		'icon'  => 'people',
		'tone'  => 'blue',
		'title' => __( 'Students', 'wpcredits-theme' ),
		'text'  => __( 'Join an internship track - In Sensei or In Sensei 50h - with a mentor assigned to you.', 'wpcredits-theme' ),
	),
	array(
		'icon'  => 'comment',
		'tone'  => 'green',
		'title' => __( 'Mentors', 'wpcredits-theme' ),
		'text'  => __( 'Guide your students, keep a note of every call, and file their reports on time.', 'wpcredits-theme' ),
	),
	array(
		'icon'  => 'home',
		'tone'  => 'amber',
		'title' => __( 'Institutions', 'wpcredits-theme' ),
		'text'  => __( 'Recognise open-source work as coursework and place your students with mentors.', 'wpcredits-theme' ),
	),
	array(
		'icon'  => 'cog',
		'tone'  => 'gray',
		'title' => __( 'Program managers', 'wpcredits-theme' ),
		'text'  => __( 'Run the program: sync Airtable, provision accounts, promote vetted mentors.', 'wpcredits-theme' ),
	),
);

?>
<!-- wp:group {"align":"full","className":"wpc-audiences","style":{"spacing":{"padding":{"top":"34px","bottom":"34px"}}},"backgroundColor":"surface-soft","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull wpc-audiences has-surface-soft-background-color has-background" style="padding-top:34px;padding-bottom:34px"><!-- wp:columns {"align":"wide","className":"wpc-audiences__grid","style":{"spacing":{"blockGap":{"left":"0"}}}} -->
<div class="wp-block-columns alignwide wpc-audiences__grid">
<?php foreach ( $wpcredits_audiences as $wpcredits_audience ) : ?>
<!-- wp:column {"className":"wpc-audience"} -->
<div class="wp-block-column wpc-audience"><!-- wp:html -->
<div class="wpc-audience__icon wpc-audience__icon--<?php echo esc_attr( $wpcredits_audience['tone'] ); ?>"><?php wpcredits_icon( $wpcredits_audience['icon'], 20 ); ?></div>
<!-- /wp:html -->

<!-- wp:heading {"level":3,"fontSize":"small","style":{"typography":{"fontWeight":"500","lineHeight":"1.54"},"spacing":{"margin":{"top":"12px","bottom":"5px"}}}} -->
<h3 class="wp-block-heading has-small-font-size" style="margin-top:12px;margin-bottom:5px;font-weight:500;line-height:1.54"><?php echo esc_html( $wpcredits_audience['title'] ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"small","textColor":"ink-60","style":{"typography":{"lineHeight":"1.46"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-ink-60-color has-text-color has-small-font-size" style="margin-top:0;margin-bottom:0;line-height:1.46"><?php echo esc_html( $wpcredits_audience['text'] ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->
<?php endforeach; ?>
</div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
