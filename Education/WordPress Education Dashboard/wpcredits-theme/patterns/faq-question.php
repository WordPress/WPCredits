<?php
/**
 * Title: Speech bubble of words
 * Slug: wpcredits/faq-question
 * Categories: wpcredits
 * Description: A speech bubble cut from the homepage's field of role words, a large question mark inside it in the brand blue and one label, for the picture's column of the FAQ page.
 * Keywords: faq, questions, answers, speech bubble, students
 * Viewport Width: 1400
 *
 * The FAQ page draws it in the hero's picture column, in the hero's picture box:
 * `wpcredits_hub_faq_question_svg()`, the graduation cap's words and classes in another shape.
 * Decoration only: hidden from screen readers, with nothing in it to focus. Like the hero's, it is not
 * shown below 900px (hub.css), where the copy takes the whole width.
 *
 * @package WPCredits_Theme
 */

?>
<!-- wp:html -->
<div class="wpc-hub-art" aria-hidden="true"><?php echo wpcredits_hub_faq_question_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built (inc/hub-content.php). ?></div>
<!-- /wp:html -->
