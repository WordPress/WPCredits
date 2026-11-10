<?php
/**
 * Title: Mountain with a guide and students
 * Slug: wpcredits/mentors-mountain
 * Categories: wpcredits
 * Description: The homepage hero's mountain with a guide leading three students up its trail, for the picture's column of the mentors' page.
 * Keywords: mountain, mentors, guide, students
 * Viewport Width: 1400
 *
 * The mentors' landing page draws it in the hero's picture column (theme 1.32.14). The mountain is the
 * hero's own, `wpcredits_hub_mountain_svg()`, written once, with the party of
 * `wpcredits_hub_mountain_team_svg()` on its trail. Decoration only: hidden from screen readers, with
 * nothing in it to focus. Like the hero's, it is not shown below 900px (hub.css), where the copy takes
 * the whole width.
 *
 * @package WPCredits_Theme
 */

?>
<!-- wp:html -->
<div class="wpc-hub-art" aria-hidden="true"><?php echo wpcredits_hub_mountain_svg( wpcredits_hub_mountain_team_svg() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built (inc/hub-content.php). ?></div>
<!-- /wp:html -->
