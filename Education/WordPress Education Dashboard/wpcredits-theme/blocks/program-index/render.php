<?php
/**
 * Server render for the program index: its Program cards, and the filter chips.
 *
 * **The cards are the index's inner blocks** (theme 1.32.0): Administrators edit each in the Site
 * Editor, and the index draws them in their order, each by the card block's own render
 * (blocks/program-card/render.php), leaving out a card the hub does not draw: one with no name, and
 * one in development unless the hub shows what is planned. The chips, their counts and their
 * sentences are worked out from the cards it draws. An index that holds no card block and is not marked
 * `editable`, as theme 1.31 and earlier saved it, draws the theme's own programs, so a template saved
 * before 1.32.0 keeps its cards until it is migrated; an index whose cards are all left out prints
 * nothing, so no chip filters nothing. **An index an Administrator emptied draws nothing** (theme
 * 1.32.1): the Site Editor marks the index it writes `editable`, so one saved with every card removed
 * is not the 1.31 index, and the page follows the editor: no chips, no cards and no band of courses.
 *
 * **Everything is in the markup, and shown.** Without JavaScript the cards are the index, one
 * after the other, and the chips that would filter them are printed with `hidden`, so there is no
 * control that does nothing; the view script shows the chips and hides the cards a chip leaves out.
 * Every word the script says comes from here, already translated: each chip carries the sentence its
 * press announces. A filtered chip's sentence names its audience, so two chips never say the same
 * sentence, as two of them show the same number of programs.
 *
 * The band of WordPress Credits' courses under the cards is a block of its own since 1.32.0, the
 * Courses band right after the index (blocks/course-band/render.php), so the chips leave it alone. A
 * 1.31 index alone (`wpcredits_hub_index_is_legacy()`) draws it inside itself, as 1.31.12 did.
 *
 * **Core's block visibility** (WordPress 7.0 and later, theme 1.32.1): the card supports it, so an
 * Administrator can hide a card in the Site Editor. Hidden everywhere, the card is drawn as nothing and
 * never reaches the page, so no chip counts it. Hidden at one size of the window (mobile, tablet or
 * desktop), the card is drawn with the class `wp-block-hidden-<size>` and core's media query hides it
 * there: the server does not know the window, so its sentences count the card, and when a card carries
 * such a class each chip also carries its sentence as a format for every total the cards shown at one
 * size can come to (`data-announce-by-total`), for the view script to fill with the counts it makes where
 * the person is. Without such a card the markup is what it was.
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

$wpcredits_cards = wpcredits_hub_index_cards( $block ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- WordPress hands a block's render file its block.

if ( ! $wpcredits_cards ) {
	return;
}

// A 1.31 index, which holds no card block, draws the band of courses too, inside it under the cards, as
// 1.31.12 did; its render ends with a new line, and the tab after it indents the index's closing tag as
// 1.31.12 printed it, so the page is byte for byte what it was.
$wpcredits_bands = wpcredits_hub_index_is_legacy( $block->parsed_block ) ? do_blocks( wpcredits_hub_course_bands_markup( wpcredits_hub_programs() ) ) : ''; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- WordPress hands a block's render file its block.

$wpcredits_total   = count( $wpcredits_cards );
$wpcredits_filters = array(
	'all'     => __( 'All', 'wpcredits-theme' ),
	'student' => __( 'For students', 'wpcredits-theme' ),
	'faculty' => __( 'For faculty', 'wpcredits-theme' ),
	'career'  => __( 'Career and credentials', 'wpcredits-theme' ),
);

// The cards core's block visibility hides at some size of the window: drawn with the class it adds to
// the card's first tag. Hidden everywhere, a card was drawn as nothing and is not among the cards.
$wpcredits_sized = 0;

foreach ( $wpcredits_cards as $wpcredits_card ) {
	if ( preg_match( '/^<[a-z0-9]+\b[^>]*\sclass="[^"]*\bwp-block-hidden-/i', $wpcredits_card['html'] ) ) {
		++$wpcredits_sized;
	}
}

/**
 * What a chip's press announces, one whole sentence for each chip, so a translator sees it whole and the
 * audience it names is theirs to place: the sentence as a format, its noun in the plural of the total.
 * A filtered chip's sentence names its audience, so two chips never say the same sentence, as two of them
 * show the same number of programs.
 *
 * @param string $filter The chip: all, student, faculty or career.
 * @param int    $total  How many programs are shown at all.
 * @return string The format: `%s` for All, how many there are; else `%1$s`, how many the filter shows,
 *                and `%2$s`, how many there are.
 */
$wpcredits_sentence = static function ( $filter, $total ) {
	$sentences = array(
		/* translators: %s: how many programs there are. */
		'all'     => _n( 'Showing %s program', 'Showing all %s programs', $total, 'wpcredits-theme' ),
		/* translators: 1: how many programs the filter shows, 2: how many there are. */
		'student' => _n( 'Showing %1$s of %2$s program for students', 'Showing %1$s of %2$s programs for students', $total, 'wpcredits-theme' ),
		/* translators: 1: how many programs the filter shows, 2: how many there are. */
		'faculty' => _n( 'Showing %1$s of %2$s program for faculty', 'Showing %1$s of %2$s programs for faculty', $total, 'wpcredits-theme' ),
		/* translators: 1: how many programs the filter shows, 2: how many there are. */
		'career'  => _n( 'Showing %1$s of %2$s program in Career and credentials', 'Showing %1$s of %2$s programs in Career and credentials', $total, 'wpcredits-theme' ),
	);

	return $sentences[ $filter ];
};

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'wpc-hub-index' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already-escaped attribute string from core. ?>>
	<div class="wpc-hub-chips" role="group" aria-label="<?php esc_attr_e( 'Filter programs by audience', 'wpcredits-theme' ); ?>" hidden>
		<?php
		foreach ( $wpcredits_filters as $wpcredits_filter => $wpcredits_label ) :
			$wpcredits_count = 0;

			foreach ( $wpcredits_cards as $wpcredits_card ) {
				if ( 'all' === $wpcredits_filter || in_array( $wpcredits_filter, $wpcredits_card['card']['audiences'], true ) ) {
					++$wpcredits_count;
				}
			}

			$wpcredits_announce = 'all' === $wpcredits_filter
				? sprintf( $wpcredits_sentence( $wpcredits_filter, $wpcredits_total ), number_format_i18n( $wpcredits_total ) )
				: sprintf( $wpcredits_sentence( $wpcredits_filter, $wpcredits_total ), number_format_i18n( $wpcredits_count ), number_format_i18n( $wpcredits_total ) );

			// The sentence for every total the cards shown at one size can come to, from all the cards down to
			// those no size hides, as a format the view script fills; none while no card is hidden at a size.
			$wpcredits_formats = array();

			if ( $wpcredits_sized ) {
				for ( $wpcredits_at = $wpcredits_total - $wpcredits_sized; $wpcredits_at <= $wpcredits_total; ++$wpcredits_at ) {
					$wpcredits_formats[ $wpcredits_at ] = 'all' === $wpcredits_filter
						? sprintf( $wpcredits_sentence( $wpcredits_filter, $wpcredits_at ), '%2$s' )
						: sprintf( $wpcredits_sentence( $wpcredits_filter, $wpcredits_at ), '%1$s', '%2$s' );
				}
			}
			?>
			<button type="button" class="wpc-hub-chip" data-filter="<?php echo esc_attr( $wpcredits_filter ); ?>" aria-pressed="<?php echo 'all' === $wpcredits_filter ? 'true' : 'false'; ?>" data-announce="<?php echo esc_attr( $wpcredits_announce ); ?>"<?php echo $wpcredits_formats ? ' data-announce-by-total="' . esc_attr( wp_json_encode( (object) $wpcredits_formats ) ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The attribute is escaped as it is built. ?>><?php echo esc_html( $wpcredits_label ); ?></button>
		<?php endforeach; ?>
	</div>
	<p class="screen-reader-text" role="status"></p>
	<div class="wpc-hub-programs">
		<?php foreach ( $wpcredits_cards as $wpcredits_card ) : ?>
			<?php echo $wpcredits_card['html'] . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The card block's own render, escaped as it is built. ?>
		<?php endforeach; ?>
	</div>
<?php if ( '' !== $wpcredits_bands ) : ?>
	<?php echo $wpcredits_bands . "\t"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The band blocks' own render, escaped as it is built. ?>
<?php endif; ?>
</div>
