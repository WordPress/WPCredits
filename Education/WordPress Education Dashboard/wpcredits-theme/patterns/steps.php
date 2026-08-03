<?php
/**
 * Title: How an internship runs
 * Slug: wpcredits/steps
 * Categories: wpcredits
 * Description: Four numbered cards walking through an internship, from placement to the final report.
 * Keywords: steps, process, internship
 * Viewport Width: 1400
 *
 * @package WPCredits_Theme
 */

$wpcredits_steps = array(
	array(
		'title' => __( 'Placement', 'wpcredits-theme' ),
		'text'  => __( 'The institution nominates a student; the program assigns a mentor.', 'wpcredits-theme' ),
	),
	array(
		'title' => __( 'Contribution', 'wpcredits-theme' ),
		'text'  => __( 'The student joins a contribution team and starts logging hours.', 'wpcredits-theme' ),
	),
	array(
		'title' => __( 'Check-ins', 'wpcredits-theme' ),
		'text'  => __( 'Mentor and student meet regularly. Every call is noted on the student’s card.', 'wpcredits-theme' ),
	),
	array(
		'title' => __( 'Report', 'wpcredits-theme' ),
		'text'  => __( 'The student files their report form and the institution awards the credit.', 'wpcredits-theme' ),
	),
);

?>
<!-- wp:group {"align":"wide","className":"wpc-section","style":{"spacing":{"padding":{"top":"48px","bottom":"48px"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide wpc-section" style="padding-top:48px;padding-bottom:48px"><!-- wp:group {"className":"wpc-section__head","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"},"style":{"spacing":{"margin":{"bottom":"22px"}}}} -->
<div class="wp-block-group wpc-section__head" style="margin-bottom:22px"><!-- wp:heading {"level":2,"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h2 class="wp-block-heading" style="margin-top:0;margin-bottom:0"><?php esc_html_e( 'How an internship runs', 'wpcredits-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-section__more","fontSize":"small","style":{"typography":{"fontWeight":"500"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="wpc-section__more has-small-font-size" style="margin-top:0;margin-bottom:0;font-weight:500"><a href="#"><?php esc_html_e( 'Read the handbook', 'wpcredits-theme' ); ?> →</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:columns {"className":"wpc-steps"} -->
<div class="wp-block-columns wpc-steps">
<?php foreach ( $wpcredits_steps as $wpcredits_index => $wpcredits_step ) : ?>
<!-- wp:column {"className":"wpc-step","style":{"spacing":{"padding":{"top":"20px","bottom":"20px","left":"20px","right":"20px"}},"border":{"color":"var:preset|color|line","radius":"8px","style":"solid","width":"1px"}}} -->
<div class="wp-block-column wpc-step" style="border-color:var(--wp--preset--color--line);border-radius:8px;border-style:solid;border-width:1px;padding:20px"><!-- wp:paragraph {"className":"wpc-step__num","fontFamily":"mono","fontSize":"x-small","textColor":"brand","style":{"typography":{"fontWeight":"600"},"spacing":{"margin":{"top":"0","bottom":"10px"}}}} -->
<p class="wpc-step__num has-brand-color has-text-color has-mono-font-family has-x-small-font-size" style="margin-top:0;margin-bottom:10px;font-weight:600"><?php echo esc_html( sprintf( '%02d', $wpcredits_index + 1 ) ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3,"fontSize":"small","style":{"typography":{"fontWeight":"500","lineHeight":"1.54"},"spacing":{"margin":{"top":"0","bottom":"6px"}}}} -->
<h3 class="wp-block-heading has-small-font-size" style="margin-top:0;margin-bottom:6px;font-weight:500;line-height:1.54"><?php echo esc_html( $wpcredits_step['title'] ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"small","textColor":"ink-60","style":{"typography":{"lineHeight":"1.46"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-ink-60-color has-text-color has-small-font-size" style="margin-top:0;margin-bottom:0;line-height:1.46"><?php echo esc_html( $wpcredits_step['text'] ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->
<?php endforeach; ?>
</div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
