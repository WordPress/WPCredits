<?php
/**
 * Title: Ways to take part
 * Slug: wpcredits/hub-roles
 * Categories: wpcredits
 * Description: A band of role cards under the hero, one each for students, mentors, institutions and sponsors: an icon, a title, one line and a link. The sponsors' card shows only while the Partners page is public.
 * Keywords: hub, roles, students, mentors, institutions, sponsors, get involved
 * Viewport Width: 1400
 *
 * The product owner's design of 5 November 2026, in place of the "Who this is for" doors (theme 1.32.12).
 * Each card is the doors' card (`wpc-hub-door`), so its link covers the card, its border turns brand blue
 * under the pointer and its focus ring is drawn around the whole card by the doors' rules, written once.
 * The students', mentors' and institutions' cards are written here, so every word is edited in the Site
 * Editor; the icon each opens with is drawn by hub.css from its class, so the card holds no Custom HTML.
 * The sponsors' card is a block the theme draws (blocks/sponsor-card) only while the Partners page is
 * public, the test the header's Partners link is hooked by, so a saved template never holds the card
 * itself and three cards stand alone while the Partners page is not public. The grid gives each card a
 * column from 900px, three or four, so three read as intended.
 *
 * The mentors' link is the mentor application, and the institutions' the site's own application, read
 * when the pattern renders. The markup is `wpcredits_hub_roles_markup()` (inc/hub-content.php), which the
 * 1.32.12 migration writes into a saved template too, so the two never differ.
 *
 * @package WPCredits_Theme
 */

echo wpcredits_hub_roles_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built (inc/hub-content.php).
