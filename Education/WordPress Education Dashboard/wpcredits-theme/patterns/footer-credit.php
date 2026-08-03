<?php
/**
 * Title: Footer credit line
 * Slug: wpcredits/footer-credit
 * Categories: wpcredits
 * Inserter: no
 * Description: Copyright year and site name for the footer.
 *
 * A pattern rather than a paragraph in the footer part, because a template part
 * is static HTML and the year is not: hard-coding it means the footer is wrong
 * every January until someone notices.
 *
 * @package WPCredits_Theme
 */

?>
<!-- wp:paragraph {"className":"wpc-footer__copy"} -->
<p class="wpc-footer__copy">
<?php
printf(
	/* translators: 1: current year, 2: site name. */
	esc_html__( '© %1$s %2$s', 'wpcredits-theme' ),
	esc_html( wp_date( 'Y' ) ),
	esc_html( get_bloginfo( 'name', 'display' ) )
);
?>
</p>
<!-- /wp:paragraph -->
