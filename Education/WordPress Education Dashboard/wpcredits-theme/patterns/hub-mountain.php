<?php
/**
 * Title: Mountain of role words
 * Slug: wpcredits/hub-mountain
 * Categories: wpcredits
 * Inserter: no
 * Description: The homepage hero's mountain of role words, for the picture's column of the Notice and 404 pages.
 * Keywords: mountain, notice, not found
 * Viewport Width: 1400
 *
 * The Notice template (templates/page-notice.html) and the 404 page (patterns/hub-not-found.php) draw it in
 * the hero's picture column (theme 1.32.12). The mountain is the hero's own, `wpcredits_hub_mountain_svg()`,
 * written once, in the hero's picture box, without the hero's editor pieces. Decoration only: hidden from
 * screen readers, with nothing in it to focus. Like the hero's, it is not shown below 900px (hub.css), where
 * the copy takes the whole width.
 *
 * @package WPCredits_Theme
 */

?>
<!-- wp:html -->
<div class="wpc-hub-art" aria-hidden="true"><?php echo wpcredits_hub_mountain_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built (inc/hub-content.php). ?></div>
<!-- /wp:html -->
