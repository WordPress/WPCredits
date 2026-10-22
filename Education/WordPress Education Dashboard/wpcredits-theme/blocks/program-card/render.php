<?php
/**
 * Server render for a program card (theme 1.32.0): one program of the Program index, drawn from the
 * card block's attributes, which Administrators edit in the Site Editor.
 *
 * The card's status tag, its name, what it is, its time and what you get, and its links, each an
 * address or one of the site's own pages by key (`wpcredits_hub_url()`), with a decorative arrow a
 * screen reader skips. Its ID is `program-` and its slug, the address a pathway step links to; a card
 * with no slug has none. Nothing empty is printed: a card with no name is a draft and draws nothing, a
 * card in development draws nothing unless the hub shows what is planned (`wpcredits_hub_card_shown()`),
 * and a sentence, a fact, a list or a link with nothing in it is left out.
 *
 * The markup is the index's own: the index draws each card it holds by this render, inside its list
 * of cards, and the lines are indented as the index printed them in theme 1.31, so the page's markup
 * for the same texts is byte for byte what it was. A line the card may leave out is wrapped in a
 * condition opened at the start of its line, which prints nothing either way.
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

$wpcredits_card = wpcredits_hub_card( $attributes ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- WordPress hands a block's render file its attributes.

if ( ! wpcredits_hub_card_shown( $wpcredits_card ) ) {
	return;
}

$wpcredits_links = array();

foreach ( $wpcredits_card['links'] as $wpcredits_link ) {
	$wpcredits_href = esc_url( wpcredits_hub_url( $wpcredits_link['url'] ) );

	if ( '' !== $wpcredits_href ) {
		$wpcredits_links[] = array(
			'href'  => $wpcredits_href,
			'label' => $wpcredits_link['label'],
		);
	}
}

$wpcredits_time  = ! wpcredits_hub_blank( $wpcredits_card['time'] );
$wpcredits_facts = $wpcredits_time || $wpcredits_card['gets'];

?>
			<article class="wpc-hub-program"<?php echo '' !== $wpcredits_card['slug'] ? ' id="program-' . esc_attr( $wpcredits_card['slug'] ) . '"' : ''; ?> data-audiences="<?php echo esc_attr( implode( ' ', $wpcredits_card['audiences'] ) ); ?>">
				<p class="wpc-hub-program__meta">
					<?php if ( 'live' === $wpcredits_card['status'] ) : ?>
						<span class="wpc-hub-tag wpc-hub-tag--live"><?php echo esc_html_x( 'Live', 'a program running now', 'wpcredits-theme' ); ?></span>
					<?php else : ?>
						<span class="wpc-hub-tag wpc-hub-tag--development"><?php echo esc_html_x( 'In development', 'a program not offered yet', 'wpcredits-theme' ); ?></span>
					<?php endif; ?>
				</p>
				<h3 class="wpc-hub-program__name"><?php echo wp_kses( $wpcredits_card['name'], wpcredits_hub_text_tags() ); ?></h3>
<?php if ( ! wpcredits_hub_blank( $wpcredits_card['what'] ) ) : ?>
				<p class="wpc-hub-program__what"><?php echo wp_kses( $wpcredits_card['what'], wpcredits_hub_text_tags() ); ?></p>
<?php endif; ?>
<?php if ( $wpcredits_facts ) : ?>
				<dl class="wpc-hub-program__facts">
<?php endif; ?>
<?php if ( $wpcredits_time ) : ?>
					<div><dt><?php esc_html_e( 'Time', 'wpcredits-theme' ); ?></dt><dd><?php echo wp_kses( $wpcredits_card['time'], wpcredits_hub_text_tags() ); ?></dd></div>
<?php endif; ?>
					<?php $wpcredits_gets = wpcredits_hub_items_html( array_map( 'wpcredits_hub_item', $wpcredits_card['gets'] ) ); ?>
					<?php if ( '' !== $wpcredits_gets ) : ?>
						<div><dt><?php esc_html_e( 'You get', 'wpcredits-theme' ); ?></dt><dd><?php echo $wpcredits_gets; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built. ?></dd></div>
					<?php endif; ?>
<?php if ( $wpcredits_facts ) : ?>
				</dl>
<?php endif; ?>
<?php if ( $wpcredits_links ) : ?>
				<p class="wpc-hub-program__links">
					<?php foreach ( $wpcredits_links as $wpcredits_link ) : ?>
						<a href="<?php echo $wpcredits_link['href']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by esc_url() above. ?>"><?php echo esc_html( $wpcredits_link['label'] ); ?><span aria-hidden="true"> →</span></a>
					<?php endforeach; ?>
				</p>
<?php endif; ?>
			</article>
