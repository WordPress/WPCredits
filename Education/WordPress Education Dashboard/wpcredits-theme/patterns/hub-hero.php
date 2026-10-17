<?php
/**
 * Title: Education Hub hero
 * Slug: wpcredits/hub-hero
 * Categories: wpcredits, featured
 * Description: The Education Hub's opening: what the programs offer, two ways in, a picture made of WordPress editor pieces, and the program's three outcome figures with their source in a row under it.
 * Keywords: hub, hero, education, figures, outcomes
 * Viewport Width: 1400
 *
 * The picture beside the copy is decoration (hidden from screen readers, nothing in it focusable) and
 * every word in it is one the page already says: the pathway's third step, its line and what it
 * unlocks, read from the hub's own step list. Behind the pieces stands a mountain drawn in words: rows
 * of the pathway's step names cut to two peaks by a mask, a switchback trail up to a flag on the higher
 * one, and its snow cap in the brand blue. The figures are program figure blocks, each the plugin's
 * stored figure or nothing: with no figure to show, the figures row is not displayed (hub.css).
 *
 * @package WPCredits_Theme
 */

$wpcredits_steps = wpcredits_hub_steps();
$wpcredits_step  = $wpcredits_steps[2];

// The mountain's words, in the picture's own 520 by 380px space: 42 rows 12px apart, from 96px above
// the picture to 4px under it. Each row is the step names in the hub's order, begun three names
// further on than the row above and set further left, so no two rows line up, and repeated until it
// is 130 characters long, wider than the mountain from any of its starting points.
$wpcredits_names = array_column( $wpcredits_steps, 'name' );
$wpcredits_rows  = '';

for ( $wpcredits_row = 0; $wpcredits_row < 42; $wpcredits_row++ ) {
	$wpcredits_from  = ( $wpcredits_row * 3 ) % count( $wpcredits_names );
	$wpcredits_cycle = implode( ' // ', array_merge( array_slice( $wpcredits_names, $wpcredits_from ), array_slice( $wpcredits_names, 0, $wpcredits_from ) ) );
	$wpcredits_line  = $wpcredits_cycle;

	while ( mb_strlen( $wpcredits_line ) < 130 ) {
		$wpcredits_line .= ' // ' . $wpcredits_cycle;
	}

	$wpcredits_rows .= '<text x="' . ( -14 - $wpcredits_row * 23 % 64 ) . '" y="' . ( -96 + 12 * $wpcredits_row ) . '">' . esc_html( $wpcredits_line ) . '</text>';
}

// The mountain's shape in the same space: the higher peak above the chart, the lower one above the
// toolbar, and the notch between them in the gap over the selected block; the trail from the foot to
// the flag, through the gaps between the pieces; the snow cap's jagged edge under the summit.
$wpcredits_mountain = 'M0 384 L0 58 L110 -30 L227 64 L390 -70 L520 45 L520 384 Z';
$wpcredits_trail    = 'M150 384 L330 316 L296 230 L60 186 L250 66 L330 12 L430 -14 L378 -44 L390 -70';
$wpcredits_cap      = 'M330 -30 L390 -110 L450 -30 L432 -26 L418 -38 L404 -24 L390 -36 L376 -24 L362 -36 L348 -26 Z';
$wpcredits_area     = 'maskUnits="userSpaceOnUse" x="-10" y="-104" width="540" height="494"';
$wpcredits_gap      = '<path d="' . $wpcredits_trail . '" fill="none" stroke="#000" stroke-width="14" stroke-linecap="round" stroke-linejoin="round"/>';

?>
<!-- wp:group {"align":"wide","className":"wpc-hub-hero","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide wpc-hub-hero"><!-- wp:columns {"className":"wpc-hub-hero__cols wpc-hub-stack","style":{"spacing":{"padding":{"top":"0","bottom":"52px","left":"0","right":"0"}}}} -->
<div class="wp-block-columns wpc-hub-hero__cols wpc-hub-stack" style="padding-top:0;padding-right:0;padding-bottom:52px;padding-left:0"><!-- wp:column {"width":"52%","className":"wpc-hub-hero__copy"} -->
<div class="wp-block-column wpc-hub-hero__copy" style="flex-basis:52%"><!-- wp:paragraph {"className":"wpc-pill","style":{"spacing":{"margin":{"top":"0","bottom":"20px"}}}} -->
<p class="wpc-pill" style="margin-top:0;margin-bottom:20px"><?php esc_html_e( 'Education programs from the WordPress project', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"wpc-hero__title","fontSize":"display","style":{"spacing":{"margin":{"top":"0","bottom":"18px"}}}} -->
<h1 class="wp-block-heading wpc-hero__title has-display-font-size" style="margin-top:0;margin-bottom:18px"><?php esc_html_e( 'Start learning with WordPress.', 'wpcredits-theme' ); ?> <em><?php esc_html_e( 'Every step counts.', 'wpcredits-theme' ); ?> <span class="wpc-hub-brush"><?php esc_html_e( 'Keep climbing.', 'wpcredits-theme' ); ?></span></em></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hub-lede","textColor":"ink-70","style":{"spacing":{"margin":{"top":"0","bottom":"26px"}}}} -->
<p class="wpc-hub-lede has-ink-70-color has-text-color" style="margin-top:0;margin-bottom:26px"><?php esc_html_e( 'Students earn academic credit, credentials, and a portfolio employers can check, and each program leads to the next. Institutions can start with one workshop or a small pilot, and every group of students they run moves them up a partner level with more support.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"wpc-hero__actions","style":{"spacing":{"blockGap":"12px"}}} -->
<div class="wp-block-buttons wpc-hero__actions"><!-- wp:button {"className":"wpc-button--large"} -->
<div class="wp-block-button wpc-button--large"><a class="wp-block-button__link wp-element-button" href="#programs"><?php esc_html_e( 'Find where to start', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline wpc-button--large"} -->
<div class="wp-block-button is-style-outline wpc-button--large"><a class="wp-block-button__link wp-element-button" href="#pathway"><?php esc_html_e( 'Explore the pathway', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"44%","className":"wpc-hub-hero__art"} -->
<div class="wp-block-column is-vertically-aligned-center wpc-hub-hero__art" style="flex-basis:44%"><!-- wp:html -->
<div class="wpc-hub-art" aria-hidden="true">
	<svg class="wpc-hub-art__mountain" viewBox="-10 -104 540 494" focusable="false"><defs><g id="wpc-hub-mountain-words"><?php echo $wpcredits_rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each row's words are escaped where the row is built. ?></g><mask id="wpc-hub-mountain-body" <?php echo $wpcredits_area; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a literal of this file. ?>><path d="<?php echo esc_attr( $wpcredits_mountain ); ?>" fill="#fff"/><path d="<?php echo esc_attr( $wpcredits_cap ); ?>" fill="#000"/><?php echo $wpcredits_gap; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a literal of this file. ?></mask><mask id="wpc-hub-mountain-cap" <?php echo $wpcredits_area; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a literal of this file. ?>><path d="<?php echo esc_attr( $wpcredits_mountain ); ?>" fill="#fff"/><path d="M-10 -104 H530 V390 H-10 Z <?php echo esc_attr( $wpcredits_cap ); ?>" fill="#000" fill-rule="evenodd"/><?php echo $wpcredits_gap; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a literal of this file. ?></mask></defs><use href="#wpc-hub-mountain-words" class="wpc-hub-art__mountain-body" mask="url(#wpc-hub-mountain-body)"/><use href="#wpc-hub-mountain-words" class="wpc-hub-art__mountain-cap" mask="url(#wpc-hub-mountain-cap)"/><path class="wpc-hub-art__route" d="<?php echo esc_attr( $wpcredits_trail ); ?>"/><path class="wpc-hub-art__pole" d="M390 -70 V-98"/><path class="wpc-hub-art__pennant" d="M390 -98 L414 -90 L390 -82 Z"/></svg>
	<div class="wpc-hub-art__frame"></div>
	<div class="wpc-hub-art__piece wpc-hub-art__toolbar"><span class="wpc-hub-art__tool wpc-hub-art__tool--block"></span><span class="wpc-hub-art__tool wpc-hub-art__tool--more"></span><span class="wpc-hub-art__tool wpc-hub-art__tool--up"></span><span class="wpc-hub-art__tool wpc-hub-art__tool--bold"></span><span class="wpc-hub-art__tool wpc-hub-art__tool--italic"></span><span class="wpc-hub-art__tool wpc-hub-art__tool--link"></span></div>
	<div class="wpc-hub-art__piece wpc-hub-art__step"><span class="wpc-hub-art__small"><?php echo esc_html( sprintf( /* translators: 1: a step's number, 2: how many steps there are. */ __( 'Step %1$s of %2$s', 'wpcredits-theme' ), number_format_i18n( 3 ), number_format_i18n( count( $wpcredits_steps ) ) ) ); ?></span><span class="wpc-hub-art__serif"><?php echo esc_html( $wpcredits_step['name'] ); ?></span><span class="wpc-hub-art__line"><?php echo esc_html( $wpcredits_step['earned'] ); ?></span><span class="wpc-hub-art__handle wpc-hub-art__handle--start"></span><span class="wpc-hub-art__handle wpc-hub-art__handle--end"></span></div>
	<div class="wpc-hub-art__piece wpc-hub-art__chart"><span class="wpc-hub-art__serif"><?php esc_html_e( 'Every step counts.', 'wpcredits-theme' ); ?></span><span class="wpc-hub-art__small"><?php esc_html_e( 'From first workshop to first job', 'wpcredits-theme' ); ?></span><svg viewBox="0 0 200 80" preserveAspectRatio="none" focusable="false"><g stroke="#c7d1ff"><line x1="10" y1="80" x2="10" y2="70"/><line x1="40" y1="80" x2="40" y2="62"/><line x1="70" y1="80" x2="70" y2="54"/><line x1="100" y1="80" x2="100" y2="44"/><line x1="130" y1="80" x2="130" y2="34"/><line x1="160" y1="80" x2="160" y2="22"/><line x1="190" y1="80" x2="190" y2="10"/></g><path d="M10 70 L40 62 L70 54 L100 44 L130 34 L160 22 L190 10" fill="none" stroke="#3858e9" stroke-width="2" vector-effect="non-scaling-stroke"/><circle cx="100" cy="44" r="5" fill="#fff" stroke="#1e1e1e" stroke-width="2" vector-effect="non-scaling-stroke"/></svg></div>
	<div class="wpc-hub-art__piece wpc-hub-art__badge"><span class="wpc-hub-art__mark"></span><span><span class="wpc-hub-art__strong"><?php echo esc_html( $wpcredits_step['items'][0]['text'] ); ?></span><span class="wpc-hub-art__small"><?php esc_html_e( 'On your WordPress.org profile', 'wpcredits-theme' ); ?></span></span></div>
	<div class="wpc-hub-art__piece wpc-hub-art__type"></div>
	<div class="wpc-hub-art__piece wpc-hub-art__plus"></div>
</div>
<!-- /wp:html --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:group {"className":"wpc-hub-figures","layout":{"type":"default"}} -->
<div class="wp-block-group wpc-hub-figures"><!-- wp:wpcredits/program-stat {"stat":"graduation"} /-->

<!-- wp:wpcredits/program-stat {"stat":"recommend"} /-->

<!-- wp:wpcredits/program-stat {"stat":"keep"} /-->

<!-- wp:wpcredits/program-stat {"stat":"outcomes-source"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
