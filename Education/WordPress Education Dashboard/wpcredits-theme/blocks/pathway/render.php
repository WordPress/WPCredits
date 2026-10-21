<?php
/**
 * Server render for the pathway: the students' seven steps and the institutions' four levels, each a
 * staircase and every step's detail.
 *
 * **Everything is in the markup, and shown.** Without JavaScript both ladders show one after the
 * other: each its heading, its lede, the staircase as a picture of the climb whose steps are in-page
 * links to their details, then every detail as a card. The switch between the two ladders and each
 * ladder's line that says a step or a level can be selected, between its lede and its staircase, are
 * printed with `hidden`, for the view script to show; it turns each step into a button, shows one
 * detail at a time and one ladder at a time. Every step ends with a cue that it opens its detail,
 * outside its link and hidden from screen readers, which hear the link and its line: "See what it
 * unlocks" (or brings), and "Shown below" while it is the one pressed, the stylesheet showing one of
 * the two. Every word the script says comes from here, already translated.
 *
 * The detail's "next" link is the core Button block's markup, so the theme's outline button rules
 * dress it unchanged. The block is `supports.multiple` false, so its IDs are never printed twice.
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

$wpcredits_ladders = array(
	array(
		'key'      => 'students',
		'option'   => __( 'For students', 'wpcredits-theme' ),
		'title'    => __( 'Seven steps from first workshop to first job', 'wpcredits-theme' ),
		'lede'     => __( 'Each step earns a badge on your WordPress.org profile, the same profile employers look at. Enter at whichever step fits.', 'wpcredits-theme' ),
		'hint'     => __( 'Select a step to see what it unlocks.', 'wpcredits-theme' ),
		/* translators: The cue at the foot of each step, for the detail it opens below the steps. */
		'cue'      => __( 'See what it unlocks', 'wpcredits-theme' ),
		'label'    => __( 'Steps for students', 'wpcredits-theme' ),
		'start'    => 3,
		'rungs'    => wpcredits_hub_steps(),
		'columns'  => array(
			'earned'      => __( 'Earned by', 'wpcredits-theme' ),
			'items'       => __( 'Unlocks', 'wpcredits-theme' ),
			'recognition' => __( 'Academic recognition', 'wpcredits-theme' ),
		),
		/* translators: %s: a step's number. */
		'n'        => __( 'Step %s', 'wpcredits-theme' ),
		/* translators: 1: a step's number, 2: how many steps there are, 3: the step's name. */
		'announce' => __( 'Step %1$s of %2$s: %3$s', 'wpcredits-theme' ),
		/* translators: 1: a step's number, 2: how many steps there are. */
		'count'    => __( 'Step %1$s of %2$s', 'wpcredits-theme' ),
		/* translators: 1: the next step's number, 2: its name. */
		'leads'    => __( 'Leads to step %1$s: %2$s', 'wpcredits-theme' ),
		'end'      => __( 'Leads to work', 'wpcredits-theme' ),
	),
	array(
		'key'      => 'institutions',
		'option'   => __( 'For institutions', 'wpcredits-theme' ),
		'title'    => __( 'Four partner levels, measured on student outcomes', 'wpcredits-theme' ),
		'lede'     => __( 'Levels grow with the number of students you put through the programs and get certified, not the number of groups you run. Partners appear in an activity directory sorted by recent activity. We don\'t rank schools.', 'wpcredits-theme' ),
		'hint'     => __( 'Select a level to see what it brings.', 'wpcredits-theme' ),
		/* translators: The cue at the foot of each partner level, for the detail it opens below the levels. */
		'cue'      => __( 'See what it brings', 'wpcredits-theme' ),
		'label'    => __( 'Levels for institutions', 'wpcredits-theme' ),
		'start'    => 2,
		'rungs'    => wpcredits_hub_levels(),
		'columns'  => array(
			'reached' => __( 'Reached by', 'wpcredits-theme' ),
			'items'   => __( 'Gets', 'wpcredits-theme' ),
		),
		/* translators: %s: a partner level's number. */
		'n'        => __( 'Level %s', 'wpcredits-theme' ),
		/* translators: 1: a partner level's number, 2: how many levels there are, 3: the level's name. */
		'announce' => __( 'Level %1$s of %2$s: %3$s', 'wpcredits-theme' ),
		/* translators: 1: a partner level's number, 2: how many levels there are. */
		'count'    => __( 'Level %1$s of %2$s', 'wpcredits-theme' ),
		/* translators: 1: the next level's number, 2: its name. */
		'leads'    => __( 'Leads to level %1$s: %2$s', 'wpcredits-theme' ),
		'end'      => __( 'The top level', 'wpcredits-theme' ),
	),
);

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'wpc-hub-pathway' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already-escaped attribute string from core. ?>>
	<div class="wpc-hub-switch" role="group" aria-label="<?php esc_attr_e( 'Choose a pathway', 'wpcredits-theme' ); ?>" hidden>
		<?php foreach ( $wpcredits_ladders as $wpcredits_at => $wpcredits_ladder ) : ?>
			<button type="button" class="wpc-hub-switch__option" aria-pressed="<?php echo 0 === $wpcredits_at ? 'true' : 'false'; ?>" aria-controls="wpc-hub-ladder-<?php echo esc_attr( $wpcredits_ladder['key'] ); ?>"><?php echo esc_html( $wpcredits_ladder['option'] ); ?></button>
		<?php endforeach; ?>
	</div>
	<p class="screen-reader-text" role="status"></p>
	<?php
	foreach ( $wpcredits_ladders as $wpcredits_ladder ) :
		$wpcredits_key   = $wpcredits_ladder['key'];
		$wpcredits_rungs = $wpcredits_ladder['rungs'];
		$wpcredits_total = count( $wpcredits_rungs );
		?>
		<section class="wpc-hub-ladder" id="wpc-hub-ladder-<?php echo esc_attr( $wpcredits_key ); ?>" aria-labelledby="wpc-hub-ladder-<?php echo esc_attr( $wpcredits_key ); ?>-title" data-start="<?php echo (int) $wpcredits_ladder['start']; ?>">
			<h2 class="wpc-hub-ladder__title" id="wpc-hub-ladder-<?php echo esc_attr( $wpcredits_key ); ?>-title"><?php echo esc_html( $wpcredits_ladder['title'] ); ?></h2>
			<p class="wpc-hub-ladder__lede"><?php echo esc_html( $wpcredits_ladder['lede'] ); ?></p>
			<p class="wpc-hub-ladder__hint" hidden><?php echo esc_html( $wpcredits_ladder['hint'] ); ?></p>
			<ol class="wpc-hub-stairs" aria-label="<?php echo esc_attr( $wpcredits_ladder['label'] ); ?>" style="--wpc-count:<?php echo (int) $wpcredits_total; ?>">
				<?php
				foreach ( $wpcredits_rungs as $wpcredits_i => $wpcredits_rung ) :
					$wpcredits_n    = $wpcredits_i + 1;
					$wpcredits_rise = $wpcredits_total > 1 ? rtrim( rtrim( number_format( $wpcredits_i / ( $wpcredits_total - 1 ), 4, '.', '' ), '0' ), '.' ) : '0';
					?>
					<li class="wpc-hub-stair" style="--wpc-t:<?php echo esc_attr( $wpcredits_rise ); ?>">
						<a class="wpc-hub-stair__pick" href="#wpc-hub-step-<?php echo esc_attr( $wpcredits_key . '-' . $wpcredits_n ); ?>" aria-describedby="wpc-hub-by-<?php echo esc_attr( $wpcredits_key . '-' . $wpcredits_n ); ?>" data-announce="<?php echo esc_attr( sprintf( $wpcredits_ladder['announce'], number_format_i18n( $wpcredits_n ), number_format_i18n( $wpcredits_total ), $wpcredits_rung['name'] ) ); ?>"><span class="wpc-hub-stair__n"><?php echo esc_html( sprintf( $wpcredits_ladder['n'], number_format_i18n( $wpcredits_n ) ) ); ?></span> <span class="wpc-hub-stair__name"><?php echo esc_html( $wpcredits_rung['name'] ); ?></span></a>
						<span class="wpc-hub-stair__by" id="wpc-hub-by-<?php echo esc_attr( $wpcredits_key . '-' . $wpcredits_n ); ?>"><?php echo esc_html( $wpcredits_rung['by'] ); ?></span>
						<span class="wpc-hub-stair__cue" aria-hidden="true"><span class="wpc-hub-stair__see"><?php echo esc_html( $wpcredits_ladder['cue'] ); ?></span><span class="wpc-hub-stair__shown"><?php echo esc_html_x( 'Shown below', 'the cue of the step whose detail is shown under the steps', 'wpcredits-theme' ); ?></span></span>
					</li>
				<?php endforeach; ?>
			</ol>
			<div class="wpc-hub-details">
				<?php
				foreach ( $wpcredits_rungs as $wpcredits_i => $wpcredits_rung ) :
					$wpcredits_n    = $wpcredits_i + 1;
					$wpcredits_next = isset( $wpcredits_rungs[ $wpcredits_i + 1 ] ) ? $wpcredits_rungs[ $wpcredits_i + 1 ] : null;
					?>
					<article class="wpc-hub-detail" id="wpc-hub-step-<?php echo esc_attr( $wpcredits_key . '-' . $wpcredits_n ); ?>">
						<p class="wpc-hub-detail__count"><?php echo esc_html( sprintf( $wpcredits_ladder['count'], number_format_i18n( $wpcredits_n ), number_format_i18n( $wpcredits_total ) ) ); ?></p>
						<h3 class="wpc-hub-detail__name"><?php echo esc_html( $wpcredits_rung['name'] ); ?></h3>
						<dl class="wpc-hub-detail__cols">
							<?php
							foreach ( $wpcredits_ladder['columns'] as $wpcredits_column => $wpcredits_heading ) :
								if ( 'items' === $wpcredits_column ) {
									$wpcredits_said = wpcredits_hub_items_html( $wpcredits_rung['items'] );
								} elseif ( is_array( $wpcredits_rung[ $wpcredits_column ] ) ) {
									$wpcredits_said = wpcredits_hub_line_html( $wpcredits_rung[ $wpcredits_column ] );
								} else {
									$wpcredits_said = esc_html( $wpcredits_rung[ $wpcredits_column ] );
								}

								// A column with nothing the hub prints today (every benefit planned, or a planned
								// line) is left out whole, its heading with it.
								if ( '' === $wpcredits_said ) {
									continue;
								}
								?>
								<div><dt><?php echo esc_html( $wpcredits_heading ); ?></dt><dd><?php echo $wpcredits_said; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each is escaped as it is built. ?></dd></div>
							<?php endforeach; ?>
						</dl>
						<p class="wpc-hub-detail__next"><span class="wpc-hub-detail__leads"><?php echo esc_html( null === $wpcredits_next ? $wpcredits_ladder['end'] : sprintf( $wpcredits_ladder['leads'], number_format_i18n( $wpcredits_n + 1 ), $wpcredits_next['name'] ) ); ?></span>
							<span class="wp-block-button is-style-outline wpc-button--large"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( wpcredits_hub_url( $wpcredits_rung['next']['target'] ) ); ?>"><?php echo esc_html( $wpcredits_rung['next']['label'] ); ?></a></span></p>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endforeach; ?>
</div>
