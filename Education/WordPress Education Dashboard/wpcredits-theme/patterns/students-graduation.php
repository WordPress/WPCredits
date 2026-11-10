<?php
/**
 * Title: Graduation cap of words
 * Slug: wpcredits/students-graduation
 * Categories: wpcredits
 * Description: A graduation cap cut from the homepage's field of role words, its board in the brand blue and a dashed tassel hanging from it, for the picture's column of the students' page.
 * Keywords: graduation, cap, students, interest
 * Viewport Width: 1400
 *
 * The students' landing page draws it in the hero's picture column (theme 1.32.15), in the hero's
 * picture box: `wpcredits_hub_graduation_svg()`, the mountain's words and classes in another shape.
 * Decoration only: hidden from screen readers, with nothing in it to focus. Like the hero's, it is not
 * shown below 900px (hub.css), where the copy takes the whole width.
 *
 * @package WPCredits_Theme
 */

?>
<!-- wp:html -->
<div class="wpc-hub-art" aria-hidden="true"><?php echo wpcredits_hub_graduation_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built (inc/hub-content.php). ?></div>
<!-- /wp:html -->
