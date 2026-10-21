<?php
/**
 * Server render for the program index: every program as a card, and the filter chips.
 *
 * **Everything is in the markup, and shown.** Without JavaScript the six cards are the index, one
 * after the other, and the chips that would filter them are printed with `hidden`, so there is no
 * control that does nothing; the view script shows the chips and hides the cards a chip leaves out.
 * Every word the script says comes from here, already translated: each chip carries the sentence its
 * press announces. A filtered chip's sentence names its audience, so two chips never say the same
 * sentence, as two of them show the same number of programs.
 *
 * A program with courses, WordPress Credits alone, has a band of them under the cards, its ID the
 * program's slug and `-courses`, the address its card's "Compare the five courses" links to: the
 * programs section's head (an eyebrow, a heading, a lede that says access to a course comes through
 * the institution) and a card each: its name linked to its public page, then the line the stylesheet
 * draws above the name (its hours, or who it is for), so a screen reader that jumps to the heading hears
 * the line after it, and what it asks. The filter chips leave it alone: under "For faculty" it stays,
 * since an institution picks the course.
 *
 * Each card's ID is `program-` and its slug, the address a pathway step links to. The block is
 * `supports.multiple` false, so an ID is never printed twice on a page.
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

$wpcredits_programs = wpcredits_hub_programs_shown();
$wpcredits_total    = count( $wpcredits_programs );
$wpcredits_filters  = array(
	'all'     => __( 'All', 'wpcredits-theme' ),
	'student' => __( 'For students', 'wpcredits-theme' ),
	'faculty' => __( 'For faculty', 'wpcredits-theme' ),
	'career'  => __( 'Career and credentials', 'wpcredits-theme' ),
);

// What a filtered chip's press announces, one whole sentence for each, so a translator sees it whole
// and the audience it names is theirs to place. All's is in the loop below.
$wpcredits_announcements = array(
	/* translators: 1: how many programs the filter shows, 2: how many there are. */
	'student' => _n( 'Showing %1$s of %2$s program for students', 'Showing %1$s of %2$s programs for students', $wpcredits_total, 'wpcredits-theme' ),
	/* translators: 1: how many programs the filter shows, 2: how many there are. */
	'faculty' => _n( 'Showing %1$s of %2$s program for faculty', 'Showing %1$s of %2$s programs for faculty', $wpcredits_total, 'wpcredits-theme' ),
	/* translators: 1: how many programs the filter shows, 2: how many there are. */
	'career'  => _n( 'Showing %1$s of %2$s program in Career and credentials', 'Showing %1$s of %2$s programs in Career and credentials', $wpcredits_total, 'wpcredits-theme' ),
);

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'wpc-hub-index' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already-escaped attribute string from core. ?>>
	<div class="wpc-hub-chips" role="group" aria-label="<?php esc_attr_e( 'Filter programs by audience', 'wpcredits-theme' ); ?>" hidden>
		<?php
		foreach ( $wpcredits_filters as $wpcredits_filter => $wpcredits_label ) :
			$wpcredits_count = 0;

			foreach ( $wpcredits_programs as $wpcredits_program ) {
				if ( 'all' === $wpcredits_filter || in_array( $wpcredits_filter, $wpcredits_program['audiences'], true ) ) {
					++$wpcredits_count;
				}
			}

			$wpcredits_announce = 'all' === $wpcredits_filter
				/* translators: %s: how many programs there are. */
				? sprintf( _n( 'Showing %s program', 'Showing all %s programs', $wpcredits_total, 'wpcredits-theme' ), number_format_i18n( $wpcredits_total ) )
				: sprintf( $wpcredits_announcements[ $wpcredits_filter ], number_format_i18n( $wpcredits_count ), number_format_i18n( $wpcredits_total ) );
			?>
			<button type="button" class="wpc-hub-chip" data-filter="<?php echo esc_attr( $wpcredits_filter ); ?>" aria-pressed="<?php echo 'all' === $wpcredits_filter ? 'true' : 'false'; ?>" data-announce="<?php echo esc_attr( $wpcredits_announce ); ?>"><?php echo esc_html( $wpcredits_label ); ?></button>
		<?php endforeach; ?>
	</div>
	<p class="screen-reader-text" role="status"></p>
	<div class="wpc-hub-programs">
		<?php foreach ( $wpcredits_programs as $wpcredits_program ) : ?>
			<article class="wpc-hub-program" id="program-<?php echo esc_attr( $wpcredits_program['slug'] ); ?>" data-audiences="<?php echo esc_attr( implode( ' ', $wpcredits_program['audiences'] ) ); ?>">
				<p class="wpc-hub-program__meta">
					<?php if ( 'live' === $wpcredits_program['status'] ) : ?>
						<span class="wpc-hub-tag wpc-hub-tag--live"><?php echo esc_html_x( 'Live', 'a program running now', 'wpcredits-theme' ); ?></span>
					<?php else : ?>
						<span class="wpc-hub-tag wpc-hub-tag--development"><?php echo esc_html_x( 'In development', 'a program not offered yet', 'wpcredits-theme' ); ?></span>
					<?php endif; ?>
				</p>
				<h3 class="wpc-hub-program__name"><?php echo esc_html( $wpcredits_program['name'] ); ?></h3>
				<p class="wpc-hub-program__what"><?php echo esc_html( $wpcredits_program['what'] ); ?></p>
				<dl class="wpc-hub-program__facts">
					<div><dt><?php esc_html_e( 'Time', 'wpcredits-theme' ); ?></dt><dd><?php echo esc_html( $wpcredits_program['time'] ); ?></dd></div>
					<?php $wpcredits_gets = wpcredits_hub_items_html( $wpcredits_program['gets'] ); ?>
					<?php if ( '' !== $wpcredits_gets ) : ?>
						<div><dt><?php esc_html_e( 'You get', 'wpcredits-theme' ); ?></dt><dd><?php echo $wpcredits_gets; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built. ?></dd></div>
					<?php endif; ?>
				</dl>
				<p class="wpc-hub-program__links">
					<?php foreach ( $wpcredits_program['links'] as $wpcredits_link ) : ?>
						<a href="<?php echo esc_url( wpcredits_hub_url( $wpcredits_link['target'] ) ); ?>"><?php echo esc_html( $wpcredits_link['label'] ); ?><span aria-hidden="true"> →</span></a>
					<?php endforeach; ?>
				</p>
			</article>
		<?php endforeach; ?>
	</div>
	<?php
	foreach ( $wpcredits_programs as $wpcredits_program ) :
		if ( empty( $wpcredits_program['courses'] ) ) {
			continue;
		}

		$wpcredits_band = $wpcredits_program['slug'] . '-courses';
		?>
		<section class="wpc-hub-courses" id="<?php echo esc_attr( $wpcredits_band ); ?>" aria-labelledby="<?php echo esc_attr( $wpcredits_band ); ?>-title">
			<div class="wp-block-group wpc-hub-head">
				<p class="is-style-eyebrow"><?php esc_html_e( 'Choosing a course', 'wpcredits-theme' ); ?></p>
				<h3 id="<?php echo esc_attr( $wpcredits_band ); ?>-title"><?php esc_html_e( 'Which WordPress Credits course?', 'wpcredits-theme' ); ?></h3>
				<p class="wpc-hub-lede"><?php esc_html_e( 'What each asks of a student, so a school and its students can choose. Access to each course comes through the student\'s institution.', 'wpcredits-theme' ); ?></p>
			</div>
			<ul class="wpc-hub-courses__grid">
				<?php foreach ( $wpcredits_program['courses'] as $wpcredits_course ) : ?>
					<li class="wpc-hub-course">
						<h4 class="wpc-hub-course__name"><a href="<?php echo esc_url( $wpcredits_course['url'] ); ?>"><?php echo esc_html( $wpcredits_course['name'] ); ?><span aria-hidden="true"> →</span></a></h4>
						<p class="wpc-hub-course__meta"><?php echo esc_html( $wpcredits_course['eyebrow'] ); ?></p>
						<p class="wpc-hub-course__line"><?php echo esc_html( $wpcredits_course['line'] ); ?></p>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endforeach; ?>
</div>
