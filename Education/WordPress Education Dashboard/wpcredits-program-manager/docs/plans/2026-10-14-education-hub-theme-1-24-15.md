# Theme 1.24.15: the Education Hub homepage, Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The WPCredits theme gains a page template, "Education Hub home", that draws the alternative homepage built from the designer's hub: a hero with the program's three outcome figures, three doors for students, institutions and companies, every education program in one filterable list with a field-of-study table, the pathway with the students' seven steps and the institutions' four partner levels, and a closing band, in the site's WordPress.org look, complete without JavaScript, and shown on a private preview page while the live homepage stays exactly as it is. The installable zip leaves `bin/` out.

**Architecture:** A template (`templates/page-education-hub.html`, registered in theme.json) holding the front page's chrome and five patterns of core blocks (`patterns/hub-*.php`); two server-rendered theme blocks, `wpcredits/program-index` and `wpcredits/pathway` (`blocks/program-index`, `blocks/pathway`), whose `render.php` prints everything and whose small `view.js` only adds the chips, the switch and the steps' selection; their texts and links in `inc/hub-content.php`; the stylesheet, the front-page rule and the field table's scroll box in `inc/hub.php`; `assets/css/hub.css`; and the program figure block (`blocks/program-stat`) extended with the three outcome figures and their source line, read from plugin 1.122.24 through `wpcredits_outcome_figures()` in `inc/template-tags.php`.

**Tech Stack:** a block theme with no build step, for WordPress 6.6 to 7.1 and PHP 7.4 and 8.x: PHP patterns with translatable strings, `block.json` blocks with `render.php`, hand-written `view.js` and `index.js` with hand-kept `*.asset.php`; `WP_HTML_Tag_Processor` for the table filter; theme.json tokens through style.css's `--wpc-*` aliases; `bin/check-selectors.php`; `bin/make-pot.sh` (WP-CLI); new suites `bin/test-hub.php` (plain PHP on the stand-ins of `bin/stubs/hub-wp.php`) and `bin/test-hub-js.php` (node, on the stand-in DOM of `bin/js/hub-dom.js`).

**Spec:** `docs/specs/2026-10-14-education-hub-homepage-design.md` in the plugin's repository, the owner's approval of 14 October 2026; this plan carries out its sections 6, 7, 8.6, the theme's rows of 9, and 10.3 as far as a suite can, the rest of 10.3 being the checks on the local copy after the tasks. Content source: the designer's draft as the owner handed it over on 14 October 2026 (`.superpowers/session-backups/2026-10-14/WordPress-Education-Hub-v1-update.html` in the plugin's repository). The plugin's plan, `docs/plans/2026-10-14-outcome-figures-1-122-24.md`, ships the figures this theme reads: `WPCPM_Outcome_Figures::published()`.

**Base:** wpcredits-theme main at 28235ce, 1.24.14, the version live on 14 October 2026. Every anchor below is that commit's text.

**Order:** the template, the front-page rule, the stylesheet's registration, the export rule and the selector check's lookup, with the suite's stand-ins (Task 1); the content (Task 2); the program index (Task 3); the pathway (Task 4); the figures (Task 5); the patterns, the page's look and the table's scroll box (Task 6); the version, the changelog and the template (Task 7).

**Where the spec left a choice, this plan chooses:**

- **The Site Editor check of spec 7.1, made while writing this plan from WordPress 7.1.3's own source:** `wp-includes/blocks.php` (`_wp_enqueue_auto_register_blocks()`) and `wp-includes/js/dist/block-library.js` register a server-rendered block in the editor, with a server-side preview, only when its block.json says `supports.autoRegister`; a block with no editor script and no such flag is not registered there, so the Site Editor shows its "doesn't include support" placeholder. So each new block gets a hand-written editor script with a server-side preview and no settings (`index.js` on `wp.serverSideRender`, with its `index.asset.php`), as the spec decides for that case, and so does the program figure block (the owner's ruling of 14 October 2026), which by that reading shows the placeholder on the live homepage's mentors card today. Unlike the two new blocks it has attributes, so its preview hands the server each instance's own (`attributes: props.attributes`): the hero's four figure blocks and post 544's mentors card each preview their own stat. `autoRegister` is not used: it is read from a `window.__unstableAutoRegisterBlocks` list in 7.1, and the theme supports 6.6. The finding was read from core 7.1.3 alone, and the live site also runs the Gutenberg plugin; with an editor script on every one of the three blocks the plan does not depend on it, and after the tasks a local copy with the live site's plugins active confirms the preview.
- **Toggles:** the pathway's switch buttons carry `wpc-hub-switch__option` and share the chips' look; pressed is told by the brand tint, the inset ring and weight 600; in forced colors, `Highlight` and `HighlightText`.
- **The staircase:** a step's number is ink-70, as its line is (ink-60 would be 3.63:1 on the darkest step); the pressed step is drawn on its `li` through `:has()`, and the whole step answers a press (the button's `::after` covers it), its focus ring outside it. The ring around the step, and the button's own outline given up for it, are declared together inside `@supports selector( :has( * ) )`, so a browser without `:has()` keeps the theme's own ring on the button; a door's ring around its card is declared the same way. Below 900px each step is a full-width row whose filled bar, a gradient, widens from 52% to 100%, so the text keeps the whole row.
- **The hint** "Select a step to see what it leads to." is the students' ladder's alone, as spec 6.7 gives it.
- **A link to a card the filter hides** presses All; a link to a card the filter shows leaves the filter as it is. A page opened at a step's address shows its ladder and step and is scrolled to the detail again, since the browser jumped before the script hid the other details.
- **A view script run twice** (the block placed twice through a synced pattern, a script printed twice) binds once: each block is marked `data-wpc-ready`.
- **The source line** is one translatable sentence with its link inside it, run through `wp_kses()` with `a[href]` alone, so a translator sees it whole.
- **The figures' styles** are in hub.css with the rest: the hero is the one place the figures are drawn, and hub.css loads on the hub template.
- **Sections** are padded through each group block's own spacing (editable in the Site Editor): 48px above and below, the hero 56px and 48px; anchored targets carry `wpc-hub-anchored` (`#programs`, `#pathway`, `#wpc-hub-fit`); the hero, the doors and the band stack below 900px through `wpc-hub-stack`. Every hub breakpoint at 900px is written `max-width: 899px` below it and `min-width: 900px` from it, as the spec's "below 900px" and "at 900px and wider" say, so at a 900px window nothing is both stacked and side by side.
- **The program cards' "what" text** is ink-70 at 14px; the doors' and the band's text ink-60 at 16px, as spec 6.3 gives the doors'.
- **The selector check's lookup** comes in Task 1, so every task's selectors are checked as they are written.
- **The suites:** the theme had none; the two new ones live in `bin/`, which the zip leaves out, and the translation template takes no string from them: `bin/make-pot.sh` leaves `bin/` out of its WP-CLI run (`--exclude=bin`, the owner's ruling of 14 October 2026), as its gettext run already did.

## Global Constraints

- Release (spec 5.3, item 2): "**Theme 1.24.15:** the template, `inc/hub.php`, `inc/hub-content.php`, the five patterns, the two blocks, `hub.css`, the figure block's extension, the selector check's addition, the translation template, the version in every spot and the changelog." Version **1.24.15** in style.css's `Version:` and readme.txt's `Stable tag`, with a changelog entry above 1.24.14's; the template regenerated (`sh bin/make-pot.sh`) after the last code change.
- The owner's queued change for this release: a `.gitattributes` line `/bin export-ignore`, so `git archive` leaves `bin/` out of the installable zip while `bin/` stays in the source and the mirror; proven by `git archive` listing no `bin/` entry.
- Decision 1 (spec 2): "**Placement:** in the WPCredits theme, as its own selectable page template, "Education Hub home" (`templates/page-education-hub.html`, registered in `theme.json` `customTemplates`), plus patterns, shown on a private preview page. The live homepage stays as it is until the owner switches it."
- Decision 3 (spec 2): "**Look:** the site's WordPress.org brand through the theme's `theme.json` tokens, type scale and existing component styles, not the draft's EB Garamond, Inter and IBM Plex Mono or its palette."
- Decision 4 (spec 2): "**Interactive parts:** patterns of core blocks for the static sections, plus two small server-rendered theme blocks (a program index with filter chips; a pathway with the student and institution switch, the rising steps and the step detail) whose markup shows everything without JavaScript, and a small view script for filtering and selecting."
- Criterion 1 (spec 1): "the live homepage (page 11, the Site Editor's saved front-page template, post 544) renders byte for byte as before."
- Criterion 2 (spec 1): "With JavaScript off, the page shows all six programs, the field-of-study table, both pathways with all eleven step and level details, and no control that does nothing."
- Criterion 3 (spec 1): "With JavaScript on, the filter chips and the pathway switch and steps work by mouse, touch and keyboard, announce their state, and keep focus where it was."
- Criterion 4 (spec 1): "At 375px wide the document never scrolls sideways; the one scroll box is the field-of-study table's, and it is reachable by keyboard."
- Criterion 6 (spec 1): "Every string is translatable in the `wpcredits-theme` (theme) or `wpcredits-program-manager` (plugin) text domain; every text a core block holds is editable in the Site Editor." Spec 7.4: "No string is assembled from parts a translator cannot see whole; counts use `_n()` and `number_format_i18n()`, and the percent sign is a translatable `%s%%`."
- Criterion 7 (spec 1): "Nothing on the page, in the theme or in this repository says anything about what sponsorship costs or what a sponsor receives."
- Criterion 8 (spec 1): "Every benefit the page states that no public page confirms carries "(planned)" (section 6.11)"; spec 6.11: "`_x( '(planned)', 'a benefit not offered yet', 'wpcredits-theme' )`, kept on the item's line with a non-breaking space so it never wraps alone."
- Width and type (spec 6.1): "Every section is `alignwide` (1240px inside the theme's 40px root padding) and no text carries a character-width cap"; "below 640px the hub's own sections take the header's phone gutter instead (`--wpc-chrome-gutter`, 20px)"; "Sections are padded 48px top and bottom (the hero 56px and 48px, as `wpcredits/hero`), section heads sit 22px above their content, cards are 16px apart. Body text is 16px, card text and controls 14px, eyebrows 12px; nothing is smaller than 12px." "Before shipping, every gap between consecutive blocks is measured at desktop width and at 375px."
- Script (spec 7.1): "plain JavaScript in the theme's style (an IIFE, `'use strict'`, no dependency), loaded by `viewScript` only where the block renders; `view.asset.php` returns no dependencies and the theme's version. The script reads no storage and writes nothing to the URL. Every string it shows comes from the server, already translated, in `data-` attributes (plurals through `_n()`), so the script needs no translation layer."
- Buttons (spec 7.1): "Every toggle is a `<button type="button">` with `aria-pressed`, inside a `role="group"` with an `aria-label`. One button of a group is pressed at a time. Tab moves between buttons, Enter and Space press; pressing never moves focus. A polite live region (`role="status"`, visually hidden) says what changed."
- Motion (spec 7.1): "Nothing animates by default; the one hover lift on a step is declared inside `prefers-reduced-motion: no-preference` only, and the theme's global reduce rule stands. In-page links jump; no smooth scrolling is added."
- Links (spec 6.9): "The site's own addresses are read when a pattern or block renders. If a page is not published (or the plugin is off), its links point to the WordPress Credits handbook, https://make.wordpress.org/community/handbook/education/credits/" and "Every link opens in the same tab; the arrows are decorative (`aria-hidden`)."
- Figures (spec 8.6): "The four count stats (`mentors`, `students`, `institutions`, `sponsors`) keep their markup and behavior byte for byte" and "`fallback` is ignored for outcome figures: they are never typed in."; spec 5.2: guarded "on the class and the method, so a theme on a plugin without them shows no figure."
- Out of scope (spec 3): the designer's Hire, Partner and Sponsor views, their navigation, brand bar, "v1 draft" badge, footer, dark scheme and hash routing; any content about sponsorship levels, amounts or benefits; switching the live homepage; WordPress Student Clubs.
- US English; plain hyphens only, never an em or en dash, in any file (`bin/check-selectors.php` refuses one); full product names (WordPress Credits, Institution Dashboard, Student Report Card, Mentor Report Card); "Administrators". `phpcs` stays at 0 errors and 6 warnings, the 6 of main.
- Never touch the live site, the Studio or Docker copies while building; a mutation or a trial is made in a scratch copy, never in the worktree. Never `git stash`.

## Review Focus

1. **The field-of-study table edited in the Site Editor** (its words, its classes in another order, a style or an anchor added): it is still the keyboard-reachable region named by its heading, and a table without the class is left as it is. Pinned in Task 6 (`bin/test-hub.php`, the filter on edited markup).
2. **One hub block on a page without the other:** the program index alone and the pathway alone each work, and a link to the block that is not there changes nothing and throws nothing. Pinned in Tasks 3 and 4 (`bin/test-hub-js.php`, the index and pathway pages).
3. **An address that names no card or step, or a malformed one** (`#program-nothing`, `#program-`, `#wpc-hub-step-students-99`, `#wpc-hub-step-`, `#wpc-hub-ladder-institutions`): the page opens on its defaults, All pressed, step 3 and level 2. Pinned in Tasks 3 and 4.
4. **A plugin older than 1.122.24, or figures handed over in another shape** (no read time or one as a string, figures not a list, a share over 100, negative or as a string, a zero count, a count over its n, a missing n): no figure and no source line, a typed fallback ignored, the hero's copy at full width, the four counts as before. Pinned in Task 5.
5. **A view script run twice:** each press is answered, and announced, once. Pinned in Tasks 3 and 4.

## Execution

Subagent-driven, strictly sequential in one worktree: the tasks share `assets/css/hub.css`, `bin/test-hub.php`, `bin/stubs/hub-wp.php`, the harness and `functions.php`, so none runs beside another. Every task gets its task review; the most capable reviewers go to Tasks 3 and 4 (the scripts, the controls and what they announce) and Task 5 (the figures, the live homepage's block). Each commit message is the one its step gives, ending with the attribution the executing session's instructions ask for.

---

## File Structure

| File | Responsibility |
| --- | --- |
| `templates/page-education-hub.html`, `theme.json` (Task 1) | The template, registered as "Education Hub home" for pages. |
| `inc/hub.php` (Task 1; the table filter in Task 6) | The `wpcredits-hub` stylesheet registered, enqueued on a page the template draws and added to the editor; the front-page rule; the table's scroll box. |
| `.gitattributes` (Task 1) | `/bin export-ignore`. |
| `bin/check-selectors.php` (Task 1) | The lookup of every `.wpc-hub-*` and `.wpc-figure*` selector. |
| `inc/hub-content.php` (Task 2) | The six programs, seven steps and four levels; `wpcredits_hub_url()`; `wpcredits_hub_items_html()`. |
| `blocks/program-index/*` (Task 3), `blocks/pathway/*` (Task 4) | The two blocks: `block.json`, `render.php`, `view.js`, `view.asset.php`, `index.js`, `index.asset.php`. |
| `blocks/program-stat/block.json`, `blocks/program-stat/render.php`, `blocks/program-stat/index.js`, `blocks/program-stat/index.asset.php`, `inc/template-tags.php` (Task 5) | The figures: `wpcredits_outcome_figures()`, `wpcredits_outcome_stat_html()`; the block's editor preview. |
| `patterns/hub-hero.php`, `hub-doors.php`, `hub-programs.php`, `hub-pathway.php`, `hub-cta.php` (Task 6) | The five sections, core blocks around the three theme blocks. |
| `assets/css/hub.css` (Tasks 1 to 6) | The page, the toggles, the index, the pathway, the figures, the hero, the doors, the table and the band. |
| `functions.php` (Tasks 1 to 4) | The two `inc/` files required; the two blocks registered. |
| `bin/test-hub.php`, `bin/stubs/hub-wp.php` (Task 1; Tasks 2 to 6), `bin/test-hub-js.php`, `bin/js/hub-dom.js`, `bin/js/hub-harness.js` (Task 3; Tasks 4, 6) | The suites. |
| `bin/make-pot.sh`, `style.css`, `readme.txt`, `languages/wpcredits-theme.pot` (Task 7) | `bin/` left out of the template's WP-CLI run; the version, the readme's section and changelog, the template. |

"The theme's checks", run after each task from the theme root, `WPCPM_PLUGIN` naming the plugin's folder when it is not `~/GitHub/wpcredits-program-manager`:

```bash
php bin/test-hub.php | tail -1
php bin/test-hub-js.php | tail -1
php bin/check-selectors.php "${WPCPM_PLUGIN:-$HOME/GitHub/wpcredits-program-manager}" | tail -5
"$(command -v phpcs || echo "$HOME/.composer/vendor/bin/phpcs")" --no-colors --report=summary | grep TOTAL
```

`bin/test-hub.php` prints `ok` / `FAIL` lines and ends `ALL PASS (N checks)` or `N FAILURE(S) (N checks)`; `bin/test-hub-js.php` prints a line per scenario and ends `ALL PASS (N scenarios)` or `N FAILURE(S) (N scenarios)`, or one `skip` line where node is not installed. Each exits non-zero on a failure.

How each change is written: "Create `path`:" gives a whole new file; "In `path`, replace:" gives the exact text to find, once in the file as the steps before left it, and "with:" the text to put in its place; the replacements of a step are made in the order given. A fence of four backticks holds text that has three of its own. Each task's RED is run in the task's tree after its test step and before its code step.

---

### Task 1: The template, the front-page rule, the stylesheet, the export rule, and the selector check's lookup

The hub's template holds the front page's chrome and the five hub patterns (written in Task 6; until then a pattern reference names nothing and draws nothing). `inc/hub.php` registers the hub's stylesheet, enqueues it where the template draws the page, and lets the template draw the front page once the homepage is set to a page that uses it: today's homepage, page 11 with no template, is left to the saved front-page template. The suite and its stand-ins start here.

**Files:**
- Create: `templates/page-education-hub.html`, `inc/hub.php`, `assets/css/hub.css`, `.gitattributes`
- Modify: `theme.json` (`customTemplates`), `functions.php` (the require), `bin/check-selectors.php` (the hub lookup)
- Create: `bin/stubs/hub-wp.php`, `bin/test-hub.php`

**Interfaces:**
- Consumes: the theme's `front-page.html` chrome; `WPCREDITS_VERSION`; the `wpcredits-style` handle.
- Produces:
  - `WPCREDITS_HUB_TEMPLATE = 'page-education-hub'`; the style handle `wpcredits-hub` (`assets/css/hub.css`, after `wpcredits-style`, at `WPCREDITS_VERSION`), registered on `init` at priority 9.
  - `wpcredits_is_hub_page(): bool`, `wpcredits_hub_assets()`, `wpcredits_hub_editor_style()`, `wpcredits_hub_front_page( array $templates ): array` on `frontpage_template_hierarchy`.
  - `bin/check-selectors.php`: `wpcredits_classes_dressed( $css, $pattern = '/\.(wpcpm-[A-Za-z0-9_-]+)/' )`, and the line `N hub and figure classes dressed, M printed by nothing.`
  - `bin/stubs/hub-wp.php`: the stand-ins and `$GLOBALS['opts']`, `$GLOBALS['request']`, `$GLOBALS['marked']`, `$GLOBALS['hooks']`, `$GLOBALS['styles']`, `$GLOBALS['enqueued']`, `$GLOBALS['editor_styles']`, `$GLOBALS['blocks']`; `wpcredits_test_block( $name, array $attributes = array() )`, `wpcredits_test_pattern( $file )`.
  - `bin/test-hub.php`: `ck()`, `request()`, `theme_file()`, `front_hierarchy()`, `hub_enqueued()`, and the summary line before `exit()`, after which every later task adds its checks.

- [ ] **Step 1: Write the suite and its stand-ins**

Create `bin/stubs/hub-wp.php`:

```php
<?php
/**
 * WordPress, as far as the Education Hub's files reach, stood in for the hub's suites.
 *
 * bin/test-hub.php renders the hub's template rule, content, blocks and patterns with these, and
 * bin/test-hub-js.php renders the two blocks' markup with them for its scenarios. Nothing here talks
 * to a database or the network: every answer comes from the globals below, which a check sets.
 *
 * - `$GLOBALS['opts']`: option => value.
 * - `$GLOBALS['request']`: `singular` (the post type a singular request shows, or ''), `id` (the
 *   queried object's ID) and `templates`, page ID => the page template slug it stores.
 * - `$GLOBALS['marked']`: when true, every translated string comes back in brackets, `[like this]`,
 *   so a check can tell a translated text from one that was printed as written.
 * - `$GLOBALS['hooks']`, `$GLOBALS['styles']`, `$GLOBALS['enqueued']`, `$GLOBALS['editor_styles']`
 *   and `$GLOBALS['blocks']`: what the theme hooked, registered and enqueued.
 *
 * Loaded with `require_once __DIR__ . '/stubs/hub-wp.php';` from a suite's header, before the theme's
 * files.
 *
 * @package WPCredits_Theme
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'WPCREDITS_THEME_DIR', dirname( __DIR__, 2 ) . '/' );
define( 'WPCREDITS_VERSION', '9.9.9' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']          = array(
	'date_format' => 'F j, Y',
	'timezone'    => 'Europe/Warsaw',
);
$GLOBALS['request']       = array(
	'singular'  => '',
	'id'        => 0,
	'templates' => array(),
);
$GLOBALS['marked']        = false;
$GLOBALS['hooks']         = array();
$GLOBALS['styles']        = array();
$GLOBALS['enqueued']      = array();
$GLOBALS['editor_styles'] = array();
$GLOBALS['blocks']        = array();
$GLOBALS['rendering']     = '';

/* ---- translation and escaping ------------------------------------------------- */

/**
 * A translated string: as written, or in brackets while `$GLOBALS['marked']` is on.
 *
 * @param string $text The string.
 * @return string
 */
function wpcredits_test_said( $text ) {
	return $GLOBALS['marked'] ? '[' . $text . ']' : $text;
}
function __( $text, $domain = null ) { return wpcredits_test_said( $text ); }
function _x( $text, $context, $domain = null ) { return wpcredits_test_said( $text ); }
function _n( $single, $plural, $number, $domain = null ) { return wpcredits_test_said( 1 === (int) $number ? $single : $plural ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_html__( $text, $domain = null ) { return esc_html( __( $text ) ); }
function esc_attr__( $text, $domain = null ) { return esc_attr( __( $text ) ); }
function esc_html_x( $text, $context, $domain = null ) { return esc_html( _x( $text, $context ) ); }
function esc_html_e( $text, $domain = null ) { echo esc_html__( $text ); }
function esc_attr_e( $text, $domain = null ) { echo esc_attr__( $text ); }
function esc_url( $url ) { return str_replace( array( '&', "'", '"' ), array( '&#038;', '&#039;', '&quot;' ), (string) $url ); }
function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, $decimals ); }

/**
 * Core's wp_kses(), for the one shape the hub hands it: text with links, every other tag taken out.
 *
 * @param string $html    The markup.
 * @param array  $allowed The tags allowed, of which only `a` with `href` is modeled.
 * @return string
 */
function wp_kses( $html, $allowed ) {
	return strip_tags( (string) $html, isset( $allowed['a'] ) ? '<a>' : '' );
}

/* ---- options, dates and the request ------------------------------------------------ */

function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default; }
function wp_timezone() { return new DateTimeZone( (string) get_option( 'timezone', 'UTC' ) ); }
function wp_date( $format, $timestamp = null, $timezone = null ) {
	$at = new DateTimeImmutable( '@' . ( null === $timestamp ? time() : (int) $timestamp ) );

	return $at->setTimezone( $timezone instanceof DateTimeZone ? $timezone : wp_timezone() )->format( $format );
}
function is_singular( $type = '' ) {
	$shown = (string) $GLOBALS['request']['singular'];

	return '' !== $shown && ( '' === $type || $type === $shown );
}
function get_queried_object_id() { return (int) $GLOBALS['request']['id']; }
function get_page_template_slug( $post = null ) {
	$id = (int) $post;

	return isset( $GLOBALS['request']['templates'][ $id ] ) ? (string) $GLOBALS['request']['templates'][ $id ] : '';
}

/* ---- hooks, styles and blocks ----------------------------------------------------- */

function add_action( $hook, $callback, $priority = 10, $accepted = 1 ) { $GLOBALS['hooks'][ $hook ][] = array( $callback, $priority ); return true; }
function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ) { return add_action( $hook, $callback, $priority, $accepted ); }

/**
 * Run a filter's callbacks in priority order, as WordPress does.
 *
 * @param string $hook  The filter.
 * @param mixed  $value The value.
 * @param mixed  ...$more More arguments.
 * @return mixed
 */
function apply_filters( $hook, $value, ...$more ) {
	$callbacks = isset( $GLOBALS['hooks'][ $hook ] ) ? $GLOBALS['hooks'][ $hook ] : array();

	usort( $callbacks, static function ( $a, $b ) { return $a[1] - $b[1]; } );

	foreach ( $callbacks as $callback ) {
		$value = call_user_func( $callback[0], $value, ...$more );
	}

	return $value;
}
function wp_register_style( $handle, $src, $deps = array(), $ver = false ) { $GLOBALS['styles'][ $handle ] = array( $src, $deps, $ver ); return true; }
function wp_enqueue_style( $handle ) { $GLOBALS['enqueued'][] = $handle; }
function add_editor_style( $stylesheet ) { $GLOBALS['editor_styles'][] = $stylesheet; }
function get_theme_file_uri( $file = '' ) { return 'https://site.example/wp-content/themes/wpcredits-theme/' . $file; }
function get_theme_file_path( $file = '' ) { return WPCREDITS_THEME_DIR . $file; }
function register_block_type( $path, $args = array() ) { $GLOBALS['blocks'][] = $path; return true; }

/**
 * The attributes core prints on a block's wrapper: its class, then the block's extra ones.
 *
 * @param array $extra Extra attributes, `class` among them.
 * @return string
 */
function get_block_wrapper_attributes( $extra = array() ) {
	$class = trim( 'wp-block-' . str_replace( '/', '-', $GLOBALS['rendering'] ) . ' ' . ( isset( $extra['class'] ) ? $extra['class'] : '' ) );
	$out   = 'class="' . esc_attr( $class ) . '"';

	foreach ( $extra as $name => $value ) {
		if ( 'class' !== $name ) {
			$out .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}
	}

	return $out;
}

/* ---- rendering ------------------------------------------------------------------- */

/**
 * A block's server render, as WordPress runs its render.php: the attributes, the content and the
 * block in scope, its output captured.
 *
 * @param string $name       The block's name, such as `wpcredits/program-index`.
 * @param array  $attributes Its attributes.
 * @return string
 */
function wpcredits_test_block( $name, array $attributes = array() ) {
	$GLOBALS['rendering'] = $name;

	$render = static function ( $file, $attributes ) {
		$content = '';
		$block   = null;

		ob_start();
		include $file;

		return (string) ob_get_clean();
	};

	$html = $render( WPCREDITS_THEME_DIR . 'blocks/' . substr( $name, strpos( $name, '/' ) + 1 ) . '/render.php', $attributes );

	$GLOBALS['rendering'] = '';

	return $html;
}

/**
 * A pattern file's output, as WordPress reads a theme pattern's content.
 *
 * @param string $file The file's name in patterns/, such as `hub-hero.php`.
 * @return string
 */
function wpcredits_test_pattern( $file ) {
	ob_start();
	include WPCREDITS_THEME_DIR . 'patterns/' . $file;

	return (string) ob_get_clean();
}
```

Create `bin/test-hub.php`:

```php
<?php
/**
 * The Education Hub homepage (theme 1.24.15): its template and the rule that lets the front page be
 * drawn by it, its stylesheet, its content, its two blocks, the figures and its patterns, rendered
 * on the WordPress of bin/stubs/hub-wp.php.
 *
 * What a person meets without JavaScript is pinned here, from the server markup: everything shown,
 * every control printed hidden, every string translated and every link where the design note says.
 * What the view scripts do with that markup is bin/test-hub-js.php's.
 *
 * Run from the theme root:  php bin/test-hub.php
 *
 * @package WPCredits_Theme
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/hub-wp.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub.php';

$fail  = 0;
$total = 0;

/**
 * One check.
 *
 * @param string $label    What is asked.
 * @param mixed  $actual   What happened.
 * @param mixed  $expected What should have happened.
 */
function ck( $label, $actual, $expected ) {
	global $fail, $total;

	++$total;

	$ok = $actual === $expected;

	if ( ! $ok ) {
		++$fail;
	}

	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";

	if ( ! $ok ) {
		echo '       expected: ' . var_export( $expected, true ) . "\n";
		echo '       actual:   ' . var_export( $actual, true ) . "\n";
	}
}

/**
 * A request: what it shows, and the page templates the site's pages store.
 *
 * @param string $singular  The post type a singular request shows, or ''.
 * @param int    $id        The queried object's ID.
 * @param array  $templates Page ID => template slug.
 */
function request( $singular, $id = 0, array $templates = array() ) {
	$GLOBALS['request']  = array(
		'singular'  => $singular,
		'id'        => $id,
		'templates' => $templates,
	);
	$GLOBALS['enqueued'] = array();
}

/**
 * A file of the theme.
 *
 * @param string $path Below the theme root.
 * @return string
 */
function theme_file( $path ) {
	return (string) file_get_contents( WPCREDITS_THEME_DIR . $path );
}

echo "=== The template, and the front page ===\n";

$theme_json = json_decode( theme_file( 'theme.json' ), true );

ck( 'theme.json registers "Education Hub home" for pages, after the two page templates it had',
	array_column( $theme_json['customTemplates'], 'title', 'name' ),
	array( 'page-full-width' => 'Full width page', 'page-documentation' => 'Documentation', 'page-education-hub' => 'Education Hub home' ) );

ck( 'for pages alone',
	end( $theme_json['customTemplates'] )['postTypes'], array( 'page' ) );

$front = explode( "\n", theme_file( 'templates/front-page.html' ) );
$hub   = explode( "\n", theme_file( 'templates/page-education-hub.html' ) );

ck( 'the template holds the front page\'s chrome, line for line: the WordPress.org global header, the site header part, and the global footer',
	array( array_slice( $hub, 0, 4 ), $hub[ count( $hub ) - 2 ] ),
	array( array_slice( $front, 0, 4 ), $front[ count( $front ) - 2 ] ) );

preg_match_all( '#<!-- wp:pattern \{"slug":"([a-z/-]+)"\} /-->#', theme_file( 'templates/page-education-hub.html' ), $patterns );

ck( 'and between them a main of the front page\'s kind, marked as the hub, holding the five hub patterns in the page\'s order and no post content',
	array( $hub[4], $hub[5] === '<main class="wp-block-group wpc-main wpc-hub"><!-- wp:pattern {"slug":"wpcredits/hub-hero"} /-->', $patterns[1], false !== strpos( theme_file( 'templates/page-education-hub.html' ), 'post-content' ) ),
	array( '<!-- wp:group {"tagName":"main","className":"wpc-main wpc-hub","layout":{"type":"constrained"}} -->', true, array( 'wpcredits/hub-hero', 'wpcredits/hub-doors', 'wpcredits/hub-programs', 'wpcredits/hub-pathway', 'wpcredits/hub-cta' ), false ) );

/**
 * The front page's template hierarchy, as the hub's filter leaves it, on a site whose Reading
 * settings and pages are as a check says.
 *
 * @param string $show      `show_on_front`.
 * @param int    $front     `page_on_front`.
 * @param array  $templates Page ID => template slug.
 * @return string[]
 */
function front_hierarchy( $show, $front, array $templates ) {
	$GLOBALS['opts']['show_on_front'] = $show;
	$GLOBALS['opts']['page_on_front'] = $front;
	request( '', 0, $templates );

	return apply_filters( 'frontpage_template_hierarchy', array( 'front-page.php' ) );
}

ck( 'the live homepage, page 11 with no template of its own: the hierarchy is untouched, so the saved front-page template keeps drawing it',
	front_hierarchy( 'page', 11, array( 11 => '' ) ), array( 'front-page.php' ) );

ck( 'a homepage set to a page that uses the hub template is drawn by it',
	front_hierarchy( 'page', 2201, array( 2201 => 'page-education-hub', 11 => '' ) ), array( 'page-education-hub.php', 'front-page.php' ) );

ck( 'a homepage of latest posts, or no page chosen, or a page with another template, is untouched',
	array( front_hierarchy( 'posts', 2201, array( 2201 => 'page-education-hub' ) ), front_hierarchy( 'page', 0, array( 2201 => 'page-education-hub' ) ), front_hierarchy( 'page', 11, array( 11 => 'page-full-width' ) ) ),
	array( array( 'front-page.php' ), array( 'front-page.php' ), array( 'front-page.php' ) ) );

echo "\n=== The stylesheet ===\n";

foreach ( $GLOBALS['hooks']['init'] as $hooked ) {
	call_user_func( $hooked[0] );
}

ck( 'wpcredits-hub is registered on init before the blocks are, from assets/css/hub.css, after the theme\'s own stylesheet, at the theme\'s version',
	array( $GLOBALS['styles']['wpcredits-hub'] ?? null, $GLOBALS['hooks']['init'][0][1] ?? null ),
	array( array( 'https://site.example/wp-content/themes/wpcredits-theme/assets/css/hub.css', array( 'wpcredits-style' ), '9.9.9' ), 9 ) );

/**
 * Whether the hub's stylesheet is enqueued on a request.
 *
 * @param string $singular  What the request shows.
 * @param int    $id        The queried ID.
 * @param array  $templates Page ID => template slug.
 * @return bool
 */
function hub_enqueued( $singular, $id, array $templates ) {
	request( $singular, $id, $templates );

	foreach ( $GLOBALS['hooks']['wp_enqueue_scripts'] as $hooked ) {
		call_user_func( $hooked[0] );
	}

	return in_array( 'wpcredits-hub', $GLOBALS['enqueued'], true );
}

ck( 'it is enqueued on a page the hub template draws, the preview page or the homepage set to it, and on no other request',
	array(
		hub_enqueued( 'page', 2201, array( 2201 => 'page-education-hub' ) ),
		hub_enqueued( 'page', 11, array( 11 => '' ) ),
		hub_enqueued( 'page', 1606, array( 1606 => 'page-documentation' ) ),
		hub_enqueued( 'post', 2201, array( 2201 => 'page-education-hub' ) ),
		hub_enqueued( '', 0, array() ),
	),
	array( true, false, false, false, false ) );

foreach ( $GLOBALS['hooks']['after_setup_theme'] as $hooked ) {
	call_user_func( $hooked[0] );
}

ck( 'and the editor draws the hub with it too',
	$GLOBALS['editor_styles'], array( 'assets/css/hub.css' ) );

ck( 'functions.php loads inc/hub.php with the theme\'s other files',
	false !== strpos( theme_file( 'functions.php' ), "require_once get_theme_file_path( 'inc/guide.php' );\nrequire_once get_theme_file_path( 'inc/hub.php' );" ), true );

ck( 'the installable zip leaves bin/ out: .gitattributes marks it export-ignore, and bin/ stays in the repository',
	array( (bool) preg_match( '#^/bin export-ignore$#m', theme_file( '.gitattributes' ) ), is_file( WPCREDITS_THEME_DIR . 'bin/check-selectors.php' ) ),
	array( true, true ) );

$css = theme_file( 'assets/css/hub.css' );

ck( 'below 640px the hub takes the header\'s phone gutter, 20px, in place of the 40px root padding',
	(bool) preg_match( '/@media \( max-width: 640px \) \{\s*\.wpc-main\.wpc-hub \{\s*padding-left: var\( --wpc-chrome-gutter \);\s*padding-right: var\( --wpc-chrome-gutter \);/', $css ), true );

ck( 'and no rule caps a width in characters',
	(bool) preg_match( '/\d\s*ch\b/', preg_replace( '#/\*.*?\*/#s', '', $css ) ), false );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run it to see it fail**

```bash
php bin/test-hub.php 2>/dev/null | grep -c "Failed opening required '.*inc/hub.php'"
```

Expected: `1` (`inc/hub.php` does not exist yet; PHP stops at its `require_once`).

- [ ] **Step 3: Write the template, its rule and its stylesheet, the export rule and the lookup**

Create `.gitattributes`:

```text
# The development scripts and the suites stay in the repository and its mirror, and out of the
# installable zip `git archive` builds.
/bin export-ignore
```

Create `assets/css/hub.css`:

```css
/*
 * The Education Hub homepage (theme 1.24.15).
 *
 * Loaded on a page the "Education Hub home" template draws, wherever one of the hub's two blocks is
 * placed (`wpcredits-hub` is their block style), and in the editor. Every value is a theme.json token
 * through its `--wpc-*` alias in style.css, or a measure the design note gives; no section carries a
 * character-width cap, so a lede runs to the same right edge as the cards beside it.
 */

/* ==========================================================================
   The page
   ========================================================================== */

/* The root padding is 40px at every width, which at 375px would leave a section 295px. Below 640px
   the hub's sections take the header's phone gutter instead, 20px, so their edge lines up with the
   brand above them. */
@media ( max-width: 640px ) {
	.wpc-main.wpc-hub {
		padding-left: var( --wpc-chrome-gutter );
		padding-right: var( --wpc-chrome-gutter );
	}
}
```

In `bin/check-selectors.php`, replace:

```php
 * looks right after a rollback, is listed in `$kept` with the last plugin version that printed it,
 * and is reported as kept rather than missed.
 *
 * Usage:  php bin/check-selectors.php [plugin-path]      (run from the theme root)
 *
 * The plugin defaults to `../wpcredits-program-manager`, beside the theme's own folder, which
```
with:

```php
 * looks right after a rollback, is listed in `$kept` with the last plugin version that printed it,
 * and is reported as kept rather than missed.
 *
 * The Education Hub's own classes (theme 1.24.15) are looked up the same way, in the theme alone:
 * every `.wpc-hub-*` and `.wpc-figure*` selector has to be printed by one of its patterns, templates,
 * blocks or `inc/` files, since nothing else prints them.
 *
 * Usage:  php bin/check-selectors.php [plugin-path]      (run from the theme root)
 *
 * The plugin defaults to `../wpcredits-program-manager`, beside the theme's own folder, which
```

In `bin/check-selectors.php`, replace:

```php
}

/**
 * Every `.wpcpm-*` class a stylesheet's selectors name, with the line each is on.
 *
 * Comments are blanked first, line for line, so a class mentioned in prose is not counted
 * and the line numbers still point into the file. Only the selector of each rule is read -
 * the text between the previous brace and the next `{` - and at-rules are skipped.
 *
 * @param string $css The stylesheet.
 * @return array[] Rows of `class`, `line`.
 */
function wpcredits_classes_dressed( $css ) {
	$marked = preg_replace_callback(
		'#/\*.*?\*/#s',
		function ( $m ) {
```
with:

```php
}

/**
 * Every `.wpcpm-*` class a stylesheet's selectors name, or every class of another family, with the
 * line each is on.
 *
 * Comments are blanked first, line for line, so a class mentioned in prose is not counted
 * and the line numbers still point into the file. Only the selector of each rule is read -
 * the text between the previous brace and the next `{` - and at-rules are skipped.
 *
 * @param string $css     The stylesheet.
 * @param string $pattern The family, as a pattern whose first group is the class name.
 * @return array[] Rows of `class`, `line`.
 */
function wpcredits_classes_dressed( $css, $pattern = '/\.(wpcpm-[A-Za-z0-9_-]+)/' ) {
	$marked = preg_replace_callback(
		'#/\*.*?\*/#s',
		function ( $m ) {
```

In `bin/check-selectors.php`, replace:

```php
			continue;
		}

		preg_match_all( '/\.(wpcpm-[A-Za-z0-9_-]+)/', $prelude[0], $classes, PREG_OFFSET_CAPTURE );

		foreach ( $classes[1] as $class ) {
			$rows[] = array(
```
with:

```php
			continue;
		}

		preg_match_all( $pattern, $prelude[0], $classes, PREG_OFFSET_CAPTURE );

		foreach ( $classes[1] as $class ) {
			$rows[] = array(
```

In `bin/check-selectors.php`, replace:

```php
	$misses
);

// Every custom property the theme reads must be one it declares. A `var( --wpc-x )` that is
// never defined resolves to its fallback or to nothing, and the browser says nothing: 1.19.2
// found the report card's input borders drawn in the card hairline and the rating stars
```
with:

```php
	$misses
);

// The Education Hub's classes, printed by the theme alone: its patterns, its templates, its blocks
// and its `inc/` files, which is where the hub's markup is written.
$hub_corpus = '';

foreach ( array( 'patterns', 'templates', 'blocks', 'inc' ) as $folder ) {
	if ( is_dir( $theme . '/' . $folder ) ) {
		foreach ( wpcredits_source_files( $theme . '/' . $folder, array( 'php', 'js', 'html', 'json' ) ) as $path ) {
			$hub_corpus .= "\n" . file_get_contents( $path );
		}
	}
}

$hub_dressed = array();

foreach ( $sheets as $sheet ) {
	foreach ( wpcredits_classes_dressed( (string) file_get_contents( $sheet ), '/\.(wpc-(?:hub|figure)(?:[A-Za-z0-9_-]*))/' ) as $row ) {
		$hub_dressed[ $row['class'] ][] = substr( $sheet, strlen( $theme ) + 1 ) . ':' . $row['line'];
	}
}

ksort( $hub_dressed );

$hub_misses = 0;

foreach ( $hub_dressed as $class => $where ) {
	if ( ! wpcredits_printed( $hub_corpus, $class ) ) {
		++$hub_misses;
		printf( "  MISS   .%-44s nothing in the theme prints it  (%s)\n", $class, implode( ', ', $where ) );
	}
}

printf( "%d hub and figure classes dressed, %d printed by nothing.\n", count( $hub_dressed ), $hub_misses );

// Every custom property the theme reads must be one it declares. A `var( --wpc-x )` that is
// never defined resolves to its fallback or to nothing, and the browser says nothing: 1.19.2
// found the report card's input borders drawn in the card hairline and the rating stars
```

In `bin/check-selectors.php`, replace:

```php
}
echo "No em or en dashes in the theme.\n";

exit( $misses ? 1 : 0 );
```
with:

```php
}
echo "No em or en dashes in the theme.\n";

exit( $misses || $hub_misses ? 1 : 0 );
```

In `functions.php`, replace:

```php
require_once get_theme_file_path( 'inc/dashboard.php' );
require_once get_theme_file_path( 'inc/login.php' );
require_once get_theme_file_path( 'inc/guide.php' );

/**
 * Theme supports.
```
with:

```php
require_once get_theme_file_path( 'inc/dashboard.php' );
require_once get_theme_file_path( 'inc/login.php' );
require_once get_theme_file_path( 'inc/guide.php' );
require_once get_theme_file_path( 'inc/hub.php' );

/**
 * Theme supports.
```

Create `inc/hub.php`:

```php
<?php
/**
 * The Education Hub homepage: its stylesheet, and the rule that lets the front page be drawn by its
 * template.
 *
 * The hub is a page template of its own, "Education Hub home" (`templates/page-education-hub.html`,
 * registered in theme.json), made of five patterns and two blocks. It prints no post content: the
 * page it is assigned to is only its address. The live homepage is not changed by any of this; it is
 * shown on a private preview page until the site owner switches the homepage to it.
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The hub template's slug, as theme.json registers it and a page stores it. */
const WPCREDITS_HUB_TEMPLATE = 'page-education-hub';

/**
 * Register the hub's stylesheet.
 *
 * Registered rather than enqueued: the hub template enqueues it on the requests it draws, and the
 * two hub blocks name it as their block style, so it also loads wherever one of them is placed on its
 * own. After the theme's own stylesheet, whose tokens and components it builds on.
 */
function wpcredits_hub_register_style() {
	wp_register_style(
		'wpcredits-hub',
		get_theme_file_uri( 'assets/css/hub.css' ),
		array( 'wpcredits-style' ),
		WPCREDITS_VERSION
	);
}
add_action( 'init', 'wpcredits_hub_register_style', 9 );

/**
 * Whether this request is a page drawn by the hub template: the preview page, or the homepage once
 * the site owner has set it to the page that uses it.
 *
 * @return bool
 */
function wpcredits_is_hub_page() {
	if ( ! is_singular( 'page' ) ) {
		return false;
	}

	return WPCREDITS_HUB_TEMPLATE === get_page_template_slug( get_queried_object_id() );
}

/**
 * Enqueue the hub's stylesheet on a page the hub template draws.
 */
function wpcredits_hub_assets() {
	if ( wpcredits_is_hub_page() ) {
		wp_enqueue_style( 'wpcredits-hub' );
	}
}
add_action( 'wp_enqueue_scripts', 'wpcredits_hub_assets', 20 );

/**
 * The hub's stylesheet in the editor too, so the Site Editor draws the hub's sections as the front
 * end does.
 */
function wpcredits_hub_editor_style() {
	add_editor_style( 'assets/css/hub.css' );
}
add_action( 'after_setup_theme', 'wpcredits_hub_editor_style', 11 );

/**
 * Let the hub template draw the front page, when the page set as the homepage uses it.
 *
 * WordPress draws the front page with the `front-page` template before it looks at the page's own
 * template, so assigning "Education Hub home" to the homepage would change nothing. When the site
 * shows a page on its front and that page uses the hub template, the template goes first; for every
 * other homepage, the live one included, the hierarchy is returned untouched, and the Site Editor's
 * saved front-page template keeps drawing it.
 *
 * @param string[] $templates The front page's template hierarchy.
 * @return string[]
 */
function wpcredits_hub_front_page( $templates ) {
	if ( 'page' !== get_option( 'show_on_front' ) ) {
		return $templates;
	}

	$front = (int) get_option( 'page_on_front' );

	if ( ! $front || WPCREDITS_HUB_TEMPLATE !== get_page_template_slug( $front ) ) {
		return $templates;
	}

	array_unshift( $templates, WPCREDITS_HUB_TEMPLATE . '.php' );

	return $templates;
}
add_filter( 'frontpage_template_hierarchy', 'wpcredits_hub_front_page' );
```

Create `templates/page-education-hub.html`:

```html
<!-- wp:template-part {"slug":"wporg-header","className":"has-display-contents","theme":"wpcredits-theme"} /-->

<!-- wp:template-part {"slug":"header","tagName":"div","className":"wpc-header-part","area":"header"} /-->

<!-- wp:group {"tagName":"main","className":"wpc-main wpc-hub","layout":{"type":"constrained"}} -->
<main class="wp-block-group wpc-main wpc-hub"><!-- wp:pattern {"slug":"wpcredits/hub-hero"} /-->

<!-- wp:pattern {"slug":"wpcredits/hub-doors"} /-->

<!-- wp:pattern {"slug":"wpcredits/hub-programs"} /-->

<!-- wp:pattern {"slug":"wpcredits/hub-pathway"} /-->

<!-- wp:pattern {"slug":"wpcredits/hub-cta"} /--></main>
<!-- /wp:group -->
<!-- wp:template-part {"slug":"wporg-footer","className":"has-display-contents","theme":"wpcredits-theme"} /-->
```

In `theme.json`, replace:

```json
			"postTypes": [
				"page"
			]
		}
	]
}
```
with:

```json
			"postTypes": [
				"page"
			]
		},
		{
			"name": "page-education-hub",
			"title": "Education Hub home",
			"postTypes": [
				"page"
			]
		}
	]
}
```

- [ ] **Step 4: Run the theme's checks**

```bash
php bin/test-hub.php | tail -1
php bin/check-selectors.php "${WPCPM_PLUGIN:-$HOME/GitHub/wpcredits-program-manager}" | tail -5
"$(command -v phpcs || echo "$HOME/.composer/vendor/bin/phpcs")" --no-colors --report=summary | grep TOTAL
```

Expected:

```text
ALL PASS (14 checks)
355 classes dressed across 8 stylesheets: 15 built at run time, 1 kept for an older plugin, 0 printed by nothing.
1 hub and figure classes dressed, 0 printed by nothing.
53 tokens read, 54 declared, 0 undefined.
0 shared table selectors without a dashboard body class.
No em or en dashes in the theme.
A TOTAL OF 0 ERRORS AND 6 WARNINGS WERE FOUND IN 3 FILES
```

`bin/check-selectors.php` now reads eight stylesheets, hub.css among them, and its one hub class, `.wpc-hub`, is the template's; the standards report is main's.

- [ ] **Step 5: Commit, and prove the zip leaves bin/ out**

```bash
git add templates/page-education-hub.html inc/hub.php assets/css/hub.css .gitattributes theme.json functions.php bin/check-selectors.php bin/stubs/hub-wp.php bin/test-hub.php
git commit -q -m "Education Hub: the Education Hub home template, its stylesheet and the rule that lets it draw the front page once the homepage uses it; the selector check looks up the hub's classes; the installable zip leaves bin/ out; 1.24.15"
git archive HEAD | tar -t | grep -c '^bin/'
git archive HEAD | tar -t | grep -c '^\.gitattributes$'
ls bin
```

Expected:

```text
0
1
check-selectors.php
extract-meta.php
make-pot.sh
stubs
test-hub.php
```

The zip `git archive` builds holds no `bin/` entry and keeps `.gitattributes`; `bin/` is still in the repository.

---

### Task 2: The content: six programs, seven steps, four levels

Every text the program index and the pathway print, translated, each benefit its own string with its `planned` flag as spec 6.11 checked it, and every link a target, the site's own pages read when a block renders.

**Files:**
- Create: `inc/hub-content.php`
- Modify: `functions.php` (the require), `bin/test-hub.php`

**Interfaces:**
- Consumes: nothing from Task 1 but the suite.
- Produces:
  - `WPCREDITS_HUB_HANDBOOK = 'https://make.wordpress.org/community/handbook/education/credits/'`.
  - `wpcredits_hub_item( string $text, bool $planned = false ): array{text, planned}`, `wpcredits_hub_link( string $label, string $target ): array{label, target}`.
  - `wpcredits_hub_url( string $target ): string`: an outside address or `#anchor` as it is; `institution_application` and `sponsor_application` from `WPCPM_Institution_Application::page_url()` and `WPCPM_Sponsor_Application::page_url()`, the handbook when either is '' or its class or method is missing.
  - `wpcredits_hub_programs(): array[]`: each `slug`, `name`, `audiences` (of `student`, `faculty`, `career`), `status` (`live` or `development`), `step`, `what`, `time`, `gets` (items), `links`.
  - `wpcredits_hub_steps(): array[]`: each `name`, `by`, `earned`, `items`, `recognition`, `next` (a link); `wpcredits_hub_levels(): array[]`: each `name`, `by`, `reached`, `items`, `next`.
  - `wpcredits_hub_items_html( array $items ): string`: `<ul class="wpc-hub-items"><li>text</li>…</ul>`, a planned item `<li>text&nbsp;<span class="wpc-hub-planned">(planned)</span></li>`, escaped.
  - In `bin/test-hub.php`: `$programs`, `$steps`, `$levels`, `shown_for()`, `benefits_of()`, `links_said()`; the stand-in classes `WPCPM_Institution_Application` and `WPCPM_Sponsor_Application` with a static `$url`, declared from here on.

- [ ] **Step 1: Write the checks**

In `bin/test-hub.php`, replace:

```php

require_once __DIR__ . '/stubs/hub-wp.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub.php';

$fail  = 0;
$total = 0;
```
with:

```php

require_once __DIR__ . '/stubs/hub-wp.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub-content.php';

$fail  = 0;
$total = 0;
```

In `bin/test-hub.php`, replace:

```php
ck( 'and the editor draws the hub with it too',
	$GLOBALS['editor_styles'], array( 'assets/css/hub.css' ) );

ck( 'functions.php loads inc/hub.php with the theme\'s other files',
	false !== strpos( theme_file( 'functions.php' ), "require_once get_theme_file_path( 'inc/guide.php' );\nrequire_once get_theme_file_path( 'inc/hub.php' );" ), true );

ck( 'the installable zip leaves bin/ out: .gitattributes marks it export-ignore, and bin/ stays in the repository',
	array( (bool) preg_match( '#^/bin export-ignore$#m', theme_file( '.gitattributes' ) ), is_file( WPCREDITS_THEME_DIR . 'bin/check-selectors.php' ) ),
```
with:

```php
ck( 'and the editor draws the hub with it too',
	$GLOBALS['editor_styles'], array( 'assets/css/hub.css' ) );

ck( 'functions.php loads inc/hub.php, then inc/hub-content.php, with the theme\'s other files',
	false !== strpos( theme_file( 'functions.php' ), "require_once get_theme_file_path( 'inc/guide.php' );\nrequire_once get_theme_file_path( 'inc/hub.php' );\nrequire_once get_theme_file_path( 'inc/hub-content.php' );" ), true );

ck( 'the installable zip leaves bin/ out: .gitattributes marks it export-ignore, and bin/ stays in the repository',
	array( (bool) preg_match( '#^/bin export-ignore$#m', theme_file( '.gitattributes' ) ), is_file( WPCREDITS_THEME_DIR . 'bin/check-selectors.php' ) ),
```

In `bin/test-hub.php`, replace:

```php
ck( 'and no rule caps a width in characters',
	(bool) preg_match( '/\d\s*ch\b/', preg_replace( '#/\*.*?\*/#s', '', $css ) ), false );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
```
with:

```php
ck( 'and no rule caps a width in characters',
	(bool) preg_match( '/\d\s*ch\b/', preg_replace( '#/\*.*?\*/#s', '', $css ) ), false );

echo "\n=== The content: six programs, seven steps, four levels ===\n";

$programs = wpcredits_hub_programs();
$steps    = wpcredits_hub_steps();
$levels   = wpcredits_hub_levels();

ck( 'six programs in the designer\'s order, each with the anchor its card is linked by',
	array_column( $programs, 'name', 'slug' ),
	array(
		'campus-connect'                  => 'Campus Connect',
		'wordpress-credits'               => 'WordPress Credits',
		'facilitator-training'            => 'Facilitator Training',
		'learn-wordpress'                 => 'Learn WordPress',
		'ai-leaders'                      => 'AI Leaders',
		'wordpress-builder-certification' => 'WordPress Builder Certification',
	) );

/**
 * How many programs an audience filter shows.
 *
 * @param array[] $programs The programs.
 * @param string  $audience An audience, or 'all'.
 * @return int
 */
function shown_for( array $programs, $audience ) {
	return count( array_filter( $programs, static function ( $program ) use ( $audience ) { return 'all' === $audience || in_array( $audience, $program['audiences'], true ); } ) );
}

ck( 'the filters\' counts: all 6, for students 3, for faculty 2, career and credentials 3',
	array( shown_for( $programs, 'all' ), shown_for( $programs, 'student' ), shown_for( $programs, 'faculty' ), shown_for( $programs, 'career' ) ), array( 6, 3, 2, 3 ) );

ck( 'each program\'s audiences, status, step line and time',
	array_map( static function ( $p ) { return implode( ' ', $p['audiences'] ) . ' | ' . $p['status'] . ' | ' . $p['step'] . ' | ' . $p['time']; }, $programs ),
	array(
		'student | live | Step 1 · Attendee | Half or full day',
		'student career | live | Steps 3 and 4 · Contributor, Graduate | 50h or 150h+, about a semester',
		'faculty | live | Step 6 · Community Leader | Self-paced',
		'student faculty | live | Any step | Self-paced',
		'career | live | Step 7 · Credentialed | Cohort-based',
		'career | development | Step 7 · Credentialed | Exam',
	) );

ck( 'the seven steps and the four levels, in order',
	array( array_column( $steps, 'name' ), array_column( $levels, 'name' ) ),
	array( array( 'Attendee', 'Builder', 'Contributor', 'Graduate', 'Specialist', 'Community Leader', 'Credentialed' ), array( 'Participating', 'Partner', 'Established', 'Flagship' ) ) );

ck( 'each step\'s staircase line is its sentence without the full stop, and so is each level\'s',
	array( array_map( static function ( $s ) { return $s['by'] . '.' === $s['earned']; }, $steps ), array_map( static function ( $l ) { return $l['by'] . '.' === $l['reached']; }, $levels ) ),
	array( array_fill( 0, 7, true ), array_fill( 0, 4, true ) ) );

/**
 * Every benefit of some places, each as `place: text`, the planned ones with ` (planned)`.
 *
 * @param array[] $places The programs, steps or levels.
 * @param string  $key    Where their items are: `gets` or `items`.
 * @param string  $name   Where their names are.
 * @return string[]
 */
function benefits_of( array $places, $key, $name ) {
	$said = array();

	foreach ( $places as $place ) {
		foreach ( $place[ $key ] as $item ) {
			$said[] = $place[ $name ] . ': ' . $item['text'] . ( $item['planned'] ? ' (planned)' : '' );
		}
	}

	return $said;
}

$on_steps  = benefits_of( $steps, 'items', 'name' );
$on_levels = benefits_of( $levels, 'items', 'name' );
$on_cards  = benefits_of( $programs, 'gets', 'name' );
$all       = array_merge( $on_steps, $on_levels, $on_cards );
$planned   = preg_grep( '/ \(planned\)$/', $all );

ck( 'fifty-one benefits, 28 on the steps, 12 on the levels, 11 on the cards: 29 planned and 22 not, the one on the card in development among those, its tag saying it',
	array( count( $all ), count( $on_steps ), count( $on_levels ), count( $on_cards ), count( $planned ), count( $all ) - count( $planned ) ),
	array( 51, 28, 12, 11, 29, 22 ) );

ck( 'every benefit, as the design note\'s 6.11 checked it on 14 October 2026',
	$all,
	array(
		'Attendee: Certificate of participation',
		'Attendee: A seeded WordPress.org profile (planned)',
		'Attendee: A profile badge',
		'Builder: Free .blog or .art domain and a year of hosting (planned)',
		'Builder: The full theme and plugin library',
		'Builder: Showcase eligibility (planned)',
		'Builder: A profile badge (planned)',
		'Contributor: Contribution badge',
		'Contributor: Make team channels',
		'Contributor: Mentor matching',
		'Contributor: Retention support starts here (planned)',
		'Graduate: Foundation certificate',
		'Graduate: Digital badge',
		'Graduate: Verified hours transcript (planned)',
		'Graduate: Alumni directory listing (planned)',
		'Graduate: WordCamp ticket support (planned)',
		'Specialist: Deeper specialization (planned)',
		'Specialist: Scoped project work (planned)',
		'Specialist: Priority mentor matching (planned)',
		'Specialist: A specialist badge (planned)',
		'Community Leader: Listing in the facilitator and speaker directory (planned)',
		'Community Leader: Discounted or free WordCamp access (planned)',
		'Community Leader: Priority on paid teaching leads (planned)',
		'Community Leader: Organizing support',
		'Community Leader: A profile badge',
		'Credentialed: An Open to Work listing on jobs.wordpress.net',
		'Credentialed: Credentials shown on your profile (planned)',
		'Credentialed: Showcase eligibility (planned)',
		'Participating: A listing on the WordPress education page (planned)',
		'Participating: A logo kit (planned)',
		'Partner: A partner badge for your site',
		'Partner: An Institution Dashboard',
		'Partner: An auto-generated impact report each semester',
		'Established: Named regional support (planned)',
		'Established: A WordCamp speaking slot (planned)',
		'Established: Co-marketing (planned)',
		'Flagship: A case study (planned)',
		'Flagship: A Foundation announcement (planned)',
		'Flagship: Input on the program\'s direction (planned)',
		'Flagship: Event hosting (planned)',
		'Campus Connect: Certificate of participation',
		'WordPress Credits: Certificate',
		'WordPress Credits: Badge',
		'WordPress Credits: Hours transcript (planned)',
		'WordPress Credits: Credit',
		'Facilitator Training: Ready-to-run sessions',
		'Facilitator Training: Facilitator listing (planned)',
		'Learn WordPress: Course completions on your profile',
		'AI Leaders: Micro-credential',
		'AI Leaders: $1,000 for completers (planned)',
		'WordPress Builder Certification: Industry certification',
	) );

ck( 'each step\'s academic recognition',
	array_column( $steps, 'recognition' ),
	array(
		'Recognized co-curricular activity or event credit, where the school offers it.',
		'A portfolio piece that can be assessed within an existing course.',
		'Open source work logged toward course or program requirements.',
		'Credit-bearing.',
		'Credit-bearing as an advanced elective, independent study, practicum, or capstone.',
		'A role the school already records: teaching assistant, peer mentor, or student organization leader.',
		'A stackable credential accepted toward a degree, certificate, or transcript notation.',
	) );

echo "\n=== The links ===\n";

/**
 * Some links, each as `label > address`, the targets resolved as a block resolves them.
 *
 * @param array[] $links The links.
 * @return string[]
 */
function links_said( array $links ) {
	return array_map( static function ( $link ) { return $link['label'] . ' > ' . wpcredits_hub_url( $link['target'] ); }, $links );
}

ck( 'the nine links of the program cards, each where the design note\'s 6.5 says',
	links_said( array_merge( ...array_column( $programs, 'links' ) ) ),
	array(
		'Find or host an event > https://events.wordpress.org/campusconnect/',
		'Developer track > https://learn.wordpress.org/course/wordpress-credits-developer-track/',
		'Designer track > https://learn.wordpress.org/course/wordpress-credits-designer-track/',
		'Impact dashboard > https://wordpress.github.io/WPCredits/',
		'Facilitation guides > https://make.wordpress.org/community/handbook/education/wordpress-facilitator-training-program/',
		'Courses > https://learn.wordpress.org/',
		'Browse courses > https://learn.wordpress.org/',
		'Learn more > https://ai-leaders.org/',
		'Learn more > https://wordpresscertifications.com/wordpress-builder-certification/',
	) );

ck( 'the seven steps\' next links',
	links_said( array_column( $steps, 'next' ) ),
	array(
		'Find a Campus Connect event > https://events.wordpress.org/campusconnect/',
		'Learn to build a site > https://learn.wordpress.org/',
		'Start WordPress Credits > #program-wordpress-credits',
		'See the tracks > #program-wordpress-credits',
		'Explore programs > #programs',
		'Try Facilitator Training > #program-facilitator-training',
		'Where this leads: WordPress Jobs > https://jobs.wordpress.net/',
	) );

ck( 'the four levels\' next links, the institution application going to the WordPress Credits handbook while the plugin is not there to say where its page is',
	links_said( array_column( $levels, 'next' ) ),
	array(
		'Request a Campus Connect event > https://events.wordpress.org/campusconnect/organize/',
		'Partner as an institution > https://make.wordpress.org/community/handbook/education/credits/',
		'Try Facilitator Training > #program-facilitator-training',
		'Talk to the program team > https://make.wordpress.org/community/handbook/education/credits/',
	) );

// The plugin's two application pages, stood in from here on: published, or not.
if ( ! class_exists( 'WPCPM_Institution_Application' ) ) {
	/** The institution application's page, as the plugin reports it: '' while it is not published. */
	class WPCPM_Institution_Application {
		public static $url = 'https://wordpresseducation.org/apply/';
		public static function page_url() { return self::$url; }
	}

	/** The sponsorship application's page, the same way. */
	class WPCPM_Sponsor_Application {
		public static $url = 'https://wordpresseducation.org/sponsor-application/';
		public static function page_url() { return self::$url; }
	}
}

$published_links = array( wpcredits_hub_url( 'institution_application' ), wpcredits_hub_url( 'sponsor_application' ) );
WPCPM_Institution_Application::$url = '';
WPCPM_Sponsor_Application::$url     = '';
$unpublished_links = array( wpcredits_hub_url( 'institution_application' ), wpcredits_hub_url( 'sponsor_application' ) );
WPCPM_Institution_Application::$url = 'https://wordpresseducation.org/apply/';
WPCPM_Sponsor_Application::$url     = 'https://wordpresseducation.org/sponsor-application/';

ck( 'with the plugin, the site\'s own pages are read when a block renders: the published applications, or the handbook for one that is not published',
	array( $published_links, $unpublished_links ),
	array(
		array( 'https://wordpresseducation.org/apply/', 'https://wordpresseducation.org/sponsor-application/' ),
		array( 'https://make.wordpress.org/community/handbook/education/credits/', 'https://make.wordpress.org/community/handbook/education/credits/' ),
	) );

echo "\n=== The benefit lists ===\n";

ck( 'a list prints its items one to a line, a planned one followed by "(planned)" in its own span after a no-break space, every text escaped',
	wpcredits_hub_items_html( array( wpcredits_hub_item( 'Certificate' ), wpcredits_hub_item( 'Hours <transcript>', true ) ) ),
	'<ul class="wpc-hub-items"><li>Certificate</li><li>Hours &lt;transcript&gt;&nbsp;<span class="wpc-hub-planned">(planned)</span></li></ul>' );

$GLOBALS['marked'] = true;

$texts = array();

foreach ( array( wpcredits_hub_programs(), wpcredits_hub_steps(), wpcredits_hub_levels() ) as $places ) {
	array_walk_recursive(
		$places,
		static function ( $value, $key ) use ( &$texts ) {
			if ( in_array( $key, array( 'name', 'step', 'what', 'time', 'text', 'label', 'by', 'earned', 'reached', 'recognition' ), true ) ) {
				$texts[] = $value;
			}
		}
	);
}

$untranslated = preg_grep( '/^\[[^\[\]]+\]$/', $texts, PREG_GREP_INVERT );
$mark         = wpcredits_hub_items_html( array( wpcredits_hub_item( 'X', true ) ) );

$GLOBALS['marked'] = false;

ck( 'every text is one translated string, seen whole by a translator: 135 of them, 44 on the cards, 63 on the steps and 28 on the levels, and "(planned)" among them',
	array( count( $texts ), array_values( $untranslated ), false !== strpos( $mark, '<span class="wpc-hub-planned">[(planned)]</span>' ) ),
	array( 135, array(), true ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them to see them fail**

```bash
php bin/test-hub.php 2>/dev/null | grep -c "Failed opening required '.*inc/hub-content.php'"
```

Expected: `1` (`inc/hub-content.php` does not exist yet; PHP stops at its `require_once`).

- [ ] **Step 3: Write the content**

In `functions.php`, replace:

```php
require_once get_theme_file_path( 'inc/login.php' );
require_once get_theme_file_path( 'inc/guide.php' );
require_once get_theme_file_path( 'inc/hub.php' );

/**
 * Theme supports.
```
with:

```php
require_once get_theme_file_path( 'inc/login.php' );
require_once get_theme_file_path( 'inc/guide.php' );
require_once get_theme_file_path( 'inc/hub.php' );
require_once get_theme_file_path( 'inc/hub-content.php' );

/**
 * Theme supports.
```

Create `inc/hub-content.php`:

```php
<?php
/**
 * The Education Hub's content: the six programs of the program index, and the seven student steps
 * and four institution levels of the pathway, every text translated and every link a target.
 *
 * In code rather than in the editor, as the design note's 7.4 decides: the two blocks print the same
 * texts with and without JavaScript, and a translator sees each one whole. The texts the patterns'
 * core blocks hold (the section heads, the doors, the field-of-study table, the closing band) are the
 * patterns' own and editable in the Site Editor.
 *
 * **A benefit no public page confirms is marked planned** (the design note's 6.11): its `planned` is
 * true, and the blocks print "(planned)" after it. The mark comes off here, in a theme release, the
 * day a public page confirms the item.
 *
 * A link's target is an outside address, an in-page anchor, or one of the site's own pages, named by
 * `institution_application` or `sponsor_application` and read when the block renders
 * (`wpcredits_hub_url()`).
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Where a link to one of the site's own pages goes while that page is not published or the plugin is off. */
const WPCREDITS_HUB_HANDBOOK = 'https://make.wordpress.org/community/handbook/education/credits/';

/**
 * One benefit: its text and whether it is only planned.
 *
 * @param string $text    The item, translated.
 * @param bool   $planned Whether no public page confirms it yet.
 * @return array{text: string, planned: bool}
 */
function wpcredits_hub_item( $text, $planned = false ) {
	return array(
		'text'    => (string) $text,
		'planned' => (bool) $planned,
	);
}

/**
 * One link: its label and its target.
 *
 * @param string $label  The label, translated.
 * @param string $target An address, an anchor, `institution_application` or `sponsor_application`.
 * @return array{label: string, target: string}
 */
function wpcredits_hub_link( $label, $target ) {
	return array(
		'label'  => (string) $label,
		'target' => (string) $target,
	);
}

/**
 * Where a link's target goes: the site's institution or sponsorship application while its page is
 * published and the plugin can say where it is, the WordPress Credits handbook otherwise; any other
 * target as it is.
 *
 * @param string $target A link's target.
 * @return string An address, not escaped.
 */
function wpcredits_hub_url( $target ) {
	$pages = array(
		'institution_application' => 'WPCPM_Institution_Application',
		'sponsor_application'     => 'WPCPM_Sponsor_Application',
	);

	if ( ! isset( $pages[ $target ] ) ) {
		return (string) $target;
	}

	$class = $pages[ $target ];
	$url   = class_exists( $class ) && method_exists( $class, 'page_url' ) ? (string) call_user_func( array( $class, 'page_url' ) ) : '';

	return '' !== $url ? $url : WPCREDITS_HUB_HANDBOOK;
}

/**
 * The six programs, in the order the index lists them.
 *
 * @return array[] Each: `slug`, `name`, `audiences` (`student`, `faculty`, `career`), `status`
 *                 (`live` or `development`), `step`, `what`, `time`, `gets` (items) and `links`.
 */
function wpcredits_hub_programs() {
	return array(
		array(
			'slug'      => 'campus-connect',
			'name'      => __( 'Campus Connect', 'wpcredits-theme' ),
			'audiences' => array( 'student' ),
			'status'    => 'live',
			/* translators: Where a program sits on the pathway: its step and the step's name. */
			'step'      => __( 'Step 1 · Attendee', 'wpcredits-theme' ),
			'what'      => __( 'A half or full-day hands-on WordPress workshop on campus. No experience needed, and anyone connected to the institution can organize one.', 'wpcredits-theme' ),
			'time'      => __( 'Half or full day', 'wpcredits-theme' ),
			'gets'      => array(
				wpcredits_hub_item( __( 'Certificate of participation', 'wpcredits-theme' ) ),
			),
			'links'     => array(
				wpcredits_hub_link( __( 'Find or host an event', 'wpcredits-theme' ), 'https://events.wordpress.org/campusconnect/' ),
			),
		),
		array(
			'slug'      => 'wordpress-credits',
			'name'      => __( 'WordPress Credits', 'wpcredits-theme' ),
			'audiences' => array( 'student', 'career' ),
			'status'    => 'live',
			/* translators: Where a program sits on the pathway: its two steps and their names. */
			'step'      => __( 'Steps 3 and 4 · Contributor, Graduate', 'wpcredits-theme' ),
			'what'      => __( 'Mentored work on the WordPress open source project, matched to your field of study. Ends with a presentation and a Foundation certificate.', 'wpcredits-theme' ),
			'time'      => __( '50h or 150h+, about a semester', 'wpcredits-theme' ),
			'gets'      => array(
				wpcredits_hub_item( __( 'Certificate', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Badge', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Hours transcript', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Credit', 'wpcredits-theme' ) ),
			),
			'links'     => array(
				wpcredits_hub_link( __( 'Developer track', 'wpcredits-theme' ), 'https://learn.wordpress.org/course/wordpress-credits-developer-track/' ),
				wpcredits_hub_link( __( 'Designer track', 'wpcredits-theme' ), 'https://learn.wordpress.org/course/wordpress-credits-designer-track/' ),
				wpcredits_hub_link( __( 'Impact dashboard', 'wpcredits-theme' ), 'https://wordpress.github.io/WPCredits/' ),
			),
		),
		array(
			'slug'      => 'facilitator-training',
			'name'      => __( 'Facilitator Training', 'wpcredits-theme' ),
			'audiences' => array( 'faculty' ),
			'status'    => 'live',
			/* translators: Where a program sits on the pathway: its step and the step's name. */
			'step'      => __( 'Step 6 · Community Leader', 'wpcredits-theme' ),
			'what'      => __( 'Free, self-guided courses plus step-by-step facilitation guides with timed agendas and activity sheets, so faculty can teach WordPress themselves.', 'wpcredits-theme' ),
			'time'      => __( 'Self-paced', 'wpcredits-theme' ),
			'gets'      => array(
				wpcredits_hub_item( __( 'Ready-to-run sessions', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Facilitator listing', 'wpcredits-theme' ), true ),
			),
			'links'     => array(
				wpcredits_hub_link( __( 'Facilitation guides', 'wpcredits-theme' ), 'https://make.wordpress.org/community/handbook/education/wordpress-facilitator-training-program/' ),
				wpcredits_hub_link( __( 'Courses', 'wpcredits-theme' ), 'https://learn.wordpress.org/' ),
			),
		),
		array(
			'slug'      => 'learn-wordpress',
			'name'      => __( 'Learn WordPress', 'wpcredits-theme' ),
			'audiences' => array( 'student', 'faculty' ),
			'status'    => 'live',
			/* translators: Where a program sits on the pathway: anywhere along it. */
			'step'      => __( 'Any step', 'wpcredits-theme' ),
			'what'      => __( 'Free courses, lessons, and online workshops for every skill level, from building a first site to developing blocks.', 'wpcredits-theme' ),
			'time'      => __( 'Self-paced', 'wpcredits-theme' ),
			'gets'      => array(
				wpcredits_hub_item( __( 'Course completions on your profile', 'wpcredits-theme' ) ),
			),
			'links'     => array(
				wpcredits_hub_link( __( 'Browse courses', 'wpcredits-theme' ), 'https://learn.wordpress.org/' ),
			),
		),
		array(
			'slug'      => 'ai-leaders',
			'name'      => __( 'AI Leaders', 'wpcredits-theme' ),
			'audiences' => array( 'career' ),
			'status'    => 'live',
			/* translators: Where a program sits on the pathway: its step and the step's name. */
			'step'      => __( 'Step 7 · Credentialed', 'wpcredits-theme' ),
			'what'      => __( 'The first Foundation-backed micro-credential. Piloted in 2026 with students from Illinois and Louisiana.', 'wpcredits-theme' ),
			'time'      => __( 'Cohort-based', 'wpcredits-theme' ),
			'gets'      => array(
				wpcredits_hub_item( __( 'Micro-credential', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( '$1,000 for completers', 'wpcredits-theme' ), true ),
			),
			'links'     => array(
				wpcredits_hub_link( __( 'Learn more', 'wpcredits-theme' ), 'https://ai-leaders.org/' ),
			),
		),
		array(
			'slug'      => 'wordpress-builder-certification',
			'name'      => __( 'WordPress Builder Certification', 'wpcredits-theme' ),
			'audiences' => array( 'career' ),
			'status'    => 'development',
			/* translators: Where a program sits on the pathway: its step and the step's name. */
			'step'      => __( 'Step 7 · Credentialed', 'wpcredits-theme' ),
			'what'      => __( 'A certification exam validating the skills to build and run a personal or company website on WordPress.', 'wpcredits-theme' ),
			'time'      => __( 'Exam', 'wpcredits-theme' ),
			// Not marked: the card's "In development" tag already says it is not offered yet.
			'gets'      => array(
				wpcredits_hub_item( __( 'Industry certification', 'wpcredits-theme' ) ),
			),
			'links'     => array(
				wpcredits_hub_link( __( 'Learn more', 'wpcredits-theme' ), 'https://wordpresscertifications.com/wordpress-builder-certification/' ),
			),
		),
	);
}

/**
 * The seven student steps, from the first workshop to the first job.
 *
 * @return array[] Each: `name`, `by` (the staircase's line), `earned` (the detail's sentence),
 *                 `items` (what it unlocks), `recognition` and `next`, a link.
 */
function wpcredits_hub_steps() {
	return array(
		array(
			'name'        => __( 'Attendee', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Attend a Campus Connect event', 'wpcredits-theme' ),
			'earned'      => __( 'Attend a Campus Connect event.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'Certificate of participation', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'A seeded WordPress.org profile', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'A profile badge', 'wpcredits-theme' ) ),
			),
			'recognition' => __( 'Recognized co-curricular activity or event credit, where the school offers it.', 'wpcredits-theme' ),
			'next'        => wpcredits_hub_link( __( 'Find a Campus Connect event', 'wpcredits-theme' ), 'https://events.wordpress.org/campusconnect/' ),
		),
		array(
			'name'        => __( 'Builder', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Launch a live site with real content', 'wpcredits-theme' ),
			'earned'      => __( 'Launch a live site with real content.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'Free .blog or .art domain and a year of hosting', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'The full theme and plugin library', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Showcase eligibility', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'A profile badge', 'wpcredits-theme' ), true ),
			),
			'recognition' => __( 'A portfolio piece that can be assessed within an existing course.', 'wpcredits-theme' ),
			'next'        => wpcredits_hub_link( __( 'Learn to build a site', 'wpcredits-theme' ), 'https://learn.wordpress.org/' ),
		),
		array(
			'name'        => __( 'Contributor', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Get your first contribution accepted', 'wpcredits-theme' ),
			'earned'      => __( 'Get your first contribution accepted.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'Contribution badge', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Make team channels', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Mentor matching', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Retention support starts here', 'wpcredits-theme' ), true ),
			),
			'recognition' => __( 'Open source work logged toward course or program requirements.', 'wpcredits-theme' ),
			'next'        => wpcredits_hub_link( __( 'Start WordPress Credits', 'wpcredits-theme' ), '#program-wordpress-credits' ),
		),
		array(
			'name'        => __( 'Graduate', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Complete a WordPress Credits track', 'wpcredits-theme' ),
			'earned'      => __( 'Complete a WordPress Credits track.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'Foundation certificate', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Digital badge', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Verified hours transcript', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Alumni directory listing', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'WordCamp ticket support', 'wpcredits-theme' ), true ),
			),
			'recognition' => __( 'Credit-bearing.', 'wpcredits-theme' ),
			'next'        => wpcredits_hub_link( __( 'See the tracks', 'wpcredits-theme' ), '#program-wordpress-credits' ),
		),
		array(
			'name'        => __( 'Specialist', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Complete a second or third program', 'wpcredits-theme' ),
			'earned'      => __( 'Complete a second or third program.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'Deeper specialization', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Scoped project work', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Priority mentor matching', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'A specialist badge', 'wpcredits-theme' ), true ),
			),
			'recognition' => __( 'Credit-bearing as an advanced elective, independent study, practicum, or capstone.', 'wpcredits-theme' ),
			'next'        => wpcredits_hub_link( __( 'Explore programs', 'wpcredits-theme' ), '#programs' ),
		),
		array(
			'name'        => __( 'Community Leader', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Facilitate, mentor, organize, or speak', 'wpcredits-theme' ),
			'earned'      => __( 'Facilitate, mentor, organize, or speak.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'Listing in the facilitator and speaker directory', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Discounted or free WordCamp access', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Priority on paid teaching leads', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Organizing support', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'A profile badge', 'wpcredits-theme' ) ),
			),
			'recognition' => __( 'A role the school already records: teaching assistant, peer mentor, or student organization leader.', 'wpcredits-theme' ),
			'next'        => wpcredits_hub_link( __( 'Try Facilitator Training', 'wpcredits-theme' ), '#program-facilitator-training' ),
		),
		array(
			'name'        => __( 'Credentialed', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Earn a micro-credential', 'wpcredits-theme' ),
			'earned'      => __( 'Earn a micro-credential.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'An Open to Work listing on jobs.wordpress.net', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Credentials shown on your profile', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Showcase eligibility', 'wpcredits-theme' ), true ),
			),
			'recognition' => __( 'A stackable credential accepted toward a degree, certificate, or transcript notation.', 'wpcredits-theme' ),
			'next'        => wpcredits_hub_link( __( 'Where this leads: WordPress Jobs', 'wpcredits-theme' ), 'https://jobs.wordpress.net/' ),
		),
	);
}

/**
 * The four institution partner levels.
 *
 * @return array[] Each: `name`, `by` (the staircase's line), `reached` (the detail's sentence),
 *                 `items` (what it gets) and `next`, a link.
 */
function wpcredits_hub_levels() {
	return array(
		array(
			'name'    => __( 'Participating', 'wpcredits-theme' ),
			/* translators: What reaches a level, as the staircase prints it under the level's name. */
			'by'      => __( 'Host one Campus Connect event', 'wpcredits-theme' ),
			'reached' => __( 'Host one Campus Connect event.', 'wpcredits-theme' ),
			'items'   => array(
				wpcredits_hub_item( __( 'A listing on the WordPress education page', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'A logo kit', 'wpcredits-theme' ), true ),
			),
			'next'    => wpcredits_hub_link( __( 'Request a Campus Connect event', 'wpcredits-theme' ), 'https://events.wordpress.org/campusconnect/organize/' ),
		),
		array(
			'name'    => __( 'Partner', 'wpcredits-theme' ),
			/* translators: What reaches a level, as the staircase prints it under the level's name. */
			'by'      => __( 'Sign a partnership agreement and complete one WordPress Credits group', 'wpcredits-theme' ),
			'reached' => __( 'Sign a partnership agreement and complete one WordPress Credits group.', 'wpcredits-theme' ),
			'items'   => array(
				wpcredits_hub_item( __( 'A partner badge for your site', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'An Institution Dashboard', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'An auto-generated impact report each semester', 'wpcredits-theme' ) ),
			),
			'next'    => wpcredits_hub_link( __( 'Partner as an institution', 'wpcredits-theme' ), 'institution_application' ),
		),
		array(
			'name'    => __( 'Established', 'wpcredits-theme' ),
			/* translators: What reaches a level, as the staircase prints it under the level's name. */
			'by'      => __( 'Run recurring groups with growing student numbers, at least one trained faculty facilitator, and students sitting certification exams', 'wpcredits-theme' ),
			'reached' => __( 'Run recurring groups with growing student numbers, at least one trained faculty facilitator, and students sitting certification exams.', 'wpcredits-theme' ),
			'items'   => array(
				wpcredits_hub_item( __( 'Named regional support', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'A WordCamp speaking slot', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Co-marketing', 'wpcredits-theme' ), true ),
			),
			'next'    => wpcredits_hub_link( __( 'Try Facilitator Training', 'wpcredits-theme' ), '#program-facilitator-training' ),
		),
		array(
			'name'    => __( 'Flagship', 'wpcredits-theme' ),
			/* translators: What reaches a level, as the staircase prints it under the level's name. */
			'by'      => __( 'Integrate WordPress into the curriculum, with faculty mentoring across institutions and sustained numbers of certified students', 'wpcredits-theme' ),
			'reached' => __( 'Integrate WordPress into the curriculum, with faculty mentoring across institutions and sustained numbers of certified students.', 'wpcredits-theme' ),
			'items'   => array(
				wpcredits_hub_item( __( 'A case study', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'A Foundation announcement', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Input on the program\'s direction', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Event hosting', 'wpcredits-theme' ), true ),
			),
			'next'    => wpcredits_hub_link( __( 'Talk to the program team', 'wpcredits-theme' ), 'institution_application' ),
		),
	);
}

/**
 * A list of benefits, one to a line, each planned one followed by "(planned)" in its own span, kept
 * on the item's line by a no-break space so it never wraps alone.
 *
 * @param array[] $items Items, as `wpcredits_hub_item()` makes them.
 * @return string Escaped markup.
 */
function wpcredits_hub_items_html( array $items ) {
	$html = '<ul class="wpc-hub-items">';

	foreach ( $items as $item ) {
		$html .= '<li>' . esc_html( $item['text'] );

		if ( ! empty( $item['planned'] ) ) {
			$html .= '&nbsp;<span class="wpc-hub-planned">' . esc_html_x( '(planned)', 'a benefit not offered yet', 'wpcredits-theme' ) . '</span>';
		}

		$html .= '</li>';
	}

	return $html . '</ul>';
}
```

- [ ] **Step 4: Run the theme's checks**

```bash
php bin/test-hub.php | tail -1
php bin/check-selectors.php "${WPCPM_PLUGIN:-$HOME/GitHub/wpcredits-program-manager}" | tail -5
"$(command -v phpcs || echo "$HOME/.composer/vendor/bin/phpcs")" --no-colors --report=summary | grep TOTAL
```

Expected:

```text
ALL PASS (28 checks)
355 classes dressed across 8 stylesheets: 15 built at run time, 1 kept for an older plugin, 0 printed by nothing.
1 hub and figure classes dressed, 0 printed by nothing.
53 tokens read, 54 declared, 0 undefined.
0 shared table selectors without a dashboard body class.
No em or en dashes in the theme.
A TOTAL OF 0 ERRORS AND 6 WARNINGS WERE FOUND IN 3 FILES
```

- [ ] **Step 5: Commit**

```bash
git add inc/hub-content.php functions.php bin/test-hub.php
git commit -q -m "Education Hub: the six programs, the seven student steps and the four partner levels, every text translated, every benefit no public page confirms marked planned, and the site's own pages read when a block renders"
```

---

### Task 3: The program index

`wpcredits/program-index`: every program as a card and the chips that filter them by audience. The server prints every card, shown, and the chips with `hidden`; the view script shows the chips, filters, announces, and presses All before a link opens a card the filter hides. An editor script lets the Site Editor show the server's render. The toggles' look, shared with Task 4's switch, comes here.

**Files:**
- Create: `blocks/program-index/block.json`, `render.php`, `view.js`, `view.asset.php`, `index.js`, `index.asset.php`
- Modify: `functions.php` (the registration), `assets/css/hub.css` (the toggles, the index)
- Modify: `bin/test-hub.php`; Create: `bin/test-hub-js.php`, `bin/js/hub-dom.js`, `bin/js/hub-harness.js`

**Interfaces:**
- Consumes: Task 2's `wpcredits_hub_programs()`, `wpcredits_hub_items_html()`, `wpcredits_hub_url()`; the `wpcredits-hub` style handle.
- Produces:
  - The markup of spec 7.2: `div.wp-block-wpcredits-program-index.wpc-hub-index` > `div.wpc-hub-chips[role=group][aria-label][hidden]` > `button.wpc-hub-chip[type=button][data-filter][aria-pressed][data-announce]` (filters `all`, `student`, `faculty`, `career`); `p.screen-reader-text[role=status]`; `div.wpc-hub-programs` > `article.wpc-hub-program#program-<slug>[data-audiences]`, with `p.wpc-hub-program__meta` (`span.wpc-hub-tag.wpc-hub-tag--live` or `--development`, `span.wpc-hub-program__step`), `h3.wpc-hub-program__name`, `p.wpc-hub-program__what`, `dl.wpc-hub-program__facts`, `p.wpc-hub-program__links`.
  - `view.js`: marks the block `data-wpc-ready`; listens for clicks on `a[href^="#program-"]` anywhere on the page and for `hashchange`.
  - In `bin/test-hub.php`: `dom_of()`, `found()`, `unmarked()`, `color_of()`, `contrast()`, `rules_for()`.
  - In `bin/js/hub-dom.js`: `page( html, hash )`, `pressKey( where, key )`, `makeEvent()`; in `bin/js/hub-harness.js`: `scenario()`, `open( name, scripts, hash )`, `tap()`, `shown()`, `pressed()`, `chip()`, `said()`, the `SCRIPTS` map and its summary line, after which later tasks add scenarios; in `bin/test-hub-js.php`: the `$pages` map (`index`).

- [ ] **Step 1: Write the checks and the scenarios**

Create `bin/js/hub-dom.js`:

```js
'use strict';
/*
 * A stand-in for the page, for running the hub's view scripts in node (bin/test-hub-js.php).
 *
 * The server's own markup is read into a small tree of elements that keep their attributes, classes,
 * children and listeners, and fire events that bubble, as a browser's do. It knows the selectors the
 * hub's scripts ask for (a tag, an ID, a class, an attribute present, equal to, starting with or
 * holding a value, and the descendant and child combinators), focus, the address's hash and its
 * hashchange, and what pressing Enter or Space on a button does: it clicks it. Nothing is laid out:
 * `hidden` is an attribute, as the scripts read it.
 */

const VOID = new Set( [ 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr' ] );

/**
 * Text with its character references read, as a browser reads them.
 *
 * @param {string} text Raw text.
 * @return {string} The text.
 */
function decode( text ) {
	const named = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ' };

	return String( text ).replace( /&(#x[0-9a-f]+|#[0-9]+|[a-z]+);/gi, ( whole, name ) => {
		if ( '#' === name[ 0 ] ) {
			return String.fromCodePoint( 'x' === name[ 1 ].toLowerCase() ? parseInt( name.slice( 2 ), 16 ) : parseInt( name.slice( 1 ), 10 ) );
		}

		return Object.prototype.hasOwnProperty.call( named, name.toLowerCase() ) ? named[ name.toLowerCase() ] : whole;
	} );
}

class Node {
	constructor( document ) {
		this.ownerDocument = document;
		this.parentNode = null;
		this.childNodes = [];
	}

	get firstChild() {
		return this.childNodes[ 0 ] || null;
	}

	get textContent() {
		return this.childNodes.map( ( child ) => child.textContent ).join( '' );
	}

	appendChild( child ) {
		if ( child.parentNode ) {
			child.parentNode.removeChild( child );
		}

		child.parentNode = this;
		this.childNodes.push( child );

		return child;
	}

	removeChild( child ) {
		const at = this.childNodes.indexOf( child );

		if ( -1 !== at ) {
			this.childNodes.splice( at, 1 );
			child.parentNode = null;
		}

		return child;
	}

	replaceChild( fresh, old ) {
		const at = this.childNodes.indexOf( old );

		if ( fresh.parentNode ) {
			fresh.parentNode.removeChild( fresh );
		}

		this.childNodes[ at ] = fresh;
		fresh.parentNode = this;
		old.parentNode = null;

		return old;
	}

	contains( other ) {
		for ( let node = other; node; node = node.parentNode ) {
			if ( node === this ) {
				return true;
			}
		}

		return false;
	}
}

class Text extends Node {
	constructor( document, text ) {
		super( document );
		this.nodeType = 3;
		this.nodeValue = text;
	}

	get textContent() {
		return this.nodeValue;
	}
}

class Element extends Node {
	constructor( document, tag ) {
		super( document );
		this.nodeType = 1;
		this.tagName = tag.toUpperCase();
		this.attributes = new Map();
		this.listeners = {};
		this.textWrites = 0;
	}

	getAttribute( name ) {
		return this.attributes.has( name ) ? this.attributes.get( name ) : null;
	}

	setAttribute( name, value ) {
		this.attributes.set( name, String( value ) );
	}

	removeAttribute( name ) {
		this.attributes.delete( name );
	}

	hasAttribute( name ) {
		return this.attributes.has( name );
	}

	get id() {
		return this.getAttribute( 'id' ) || '';
	}

	get className() {
		return this.getAttribute( 'class' ) || '';
	}

	set className( value ) {
		this.setAttribute( 'class', value );
	}

	get classList() {
		const element = this;
		const list = () => element.className.split( /\s+/ ).filter( Boolean );

		return {
			contains: ( name ) => list().includes( name ),
			add: ( name ) => {
				if ( ! list().includes( name ) ) {
					element.className = list().concat( name ).join( ' ' );
				}
			},
			remove: ( name ) => {
				element.className = list().filter( ( one ) => one !== name ).join( ' ' );
			},
		};
	}

	get hidden() {
		return this.hasAttribute( 'hidden' );
	}

	set hidden( value ) {
		if ( value ) {
			this.setAttribute( 'hidden', '' );
		} else {
			this.removeAttribute( 'hidden' );
		}
	}

	get type() {
		return this.getAttribute( 'type' ) || ( 'BUTTON' === this.tagName ? 'submit' : '' );
	}

	set type( value ) {
		this.setAttribute( 'type', value );
	}

	get hash() {
		const href = this.getAttribute( 'href' ) || '';
		const at = href.indexOf( '#' );

		return -1 === at ? '' : href.slice( at );
	}

	get children() {
		return this.childNodes.filter( ( child ) => 1 === child.nodeType );
	}

	get textContent() {
		return super.textContent;
	}

	set textContent( value ) {
		this.childNodes.forEach( ( child ) => {
			child.parentNode = null;
		} );
		this.childNodes = [];
		this.textWrites++;

		if ( '' !== String( value ) ) {
			this.appendChild( new Text( this.ownerDocument, String( value ) ) );
		}
	}

	matches( selector ) {
		return matchList( this, selector );
	}

	closest( selector ) {
		for ( let node = this; node && 1 === node.nodeType; node = node.parentNode ) {
			if ( node.matches( selector ) ) {
				return node;
			}
		}

		return null;
	}

	querySelectorAll( selector ) {
		const found = [];

		walk( this, ( node ) => {
			if ( node !== this && node.matches( selector ) ) {
				found.push( node );
			}
		} );

		return found;
	}

	querySelector( selector ) {
		return this.querySelectorAll( selector )[ 0 ] || null;
	}

	addEventListener( type, listener ) {
		( this.listeners[ type ] = this.listeners[ type ] || [] ).push( listener );
	}

	dispatchEvent( event ) {
		return dispatch( this, event );
	}

	focus() {
		this.ownerDocument.activeElement = this;
	}

	click() {
		this.dispatchEvent( makeEvent( 'click' ) );
	}

	scrollIntoView() {
		this.ownerDocument.scrolledTo.push( this.id );
	}
}

/**
 * Every element below a node, in document order, the node included.
 *
 * @param {Node}     node     Where to start.
 * @param {Function} callback Called with each element.
 */
function walk( node, callback ) {
	if ( 1 === node.nodeType ) {
		callback( node );
	}

	node.childNodes.slice().forEach( ( child ) => walk( child, callback ) );
}

/**
 * One compound selector, such as `button[data-filter="all"]`, against one element.
 *
 * @param {Element} element  The element.
 * @param {string}  compound The compound selector.
 * @return {boolean} Whether it matches.
 */
function matchCompound( element, compound ) {
	const parts = compound.match( /^[a-z0-9]+|#[\w-]+|\.[\w-]+|\[[^\]]+\]/gi ) || [];

	if ( parts.join( '' ) !== compound ) {
		throw new Error( 'The stand-in cannot read the selector "' + compound + '"' );
	}

	return parts.every( ( part ) => {
		if ( '#' === part[ 0 ] ) {
			return element.id === part.slice( 1 );
		}

		if ( '.' === part[ 0 ] ) {
			return element.classList.contains( part.slice( 1 ) );
		}

		if ( '[' === part[ 0 ] ) {
			const test = part.slice( 1, -1 ).match( /^([\w-]+)(?:([\^*]?=)"([^"]*)")?$/ );
			const value = element.getAttribute( test[ 1 ] );

			if ( null === value ) {
				return false;
			}

			if ( ! test[ 2 ] ) {
				return true;
			}

			return '=' === test[ 2 ] ? value === test[ 3 ] : '^=' === test[ 2 ] ? value.startsWith( test[ 3 ] ) : value.includes( test[ 3 ] );
		}

		return element.tagName === part.toUpperCase();
	} );
}

/**
 * A complex selector, compounds joined by descendant or child combinators, against one element.
 *
 * @param {Element} element  The element.
 * @param {string}  selector The selector.
 * @return {boolean} Whether it matches.
 */
function matchComplex( element, selector ) {
	const tokens = selector.trim().replace( /\s*>\s*/g, ' > ' ).split( /\s+/ );

	const match = ( node, at ) => {
		if ( ! node || 1 !== node.nodeType || ! matchCompound( node, tokens[ at ] ) ) {
			return false;
		}

		if ( 0 === at ) {
			return true;
		}

		if ( '>' === tokens[ at - 1 ] ) {
			return match( node.parentNode, at - 2 );
		}

		for ( let up = node.parentNode; up && 1 === up.nodeType; up = up.parentNode ) {
			if ( match( up, at - 1 ) ) {
				return true;
			}
		}

		return false;
	};

	return match( element, tokens.length - 1 );
}

/**
 * A selector list against one element.
 *
 * @param {Element} element  The element.
 * @param {string}  selector Selectors, comma-separated.
 * @return {boolean} Whether any matches.
 */
function matchList( element, selector ) {
	return selector.split( ',' ).some( ( one ) => matchComplex( element, one ) );
}

/**
 * An event, as the scripts read one.
 *
 * @param {string} type The event's type.
 * @param {Object} more What else it carries, such as `key`.
 * @return {Object} The event.
 */
function makeEvent( type, more ) {
	return Object.assign(
		{
			type,
			bubbles: true,
			defaultPrevented: false,
			stopped: false,
			preventDefault() {
				this.defaultPrevented = true;
			},
			stopPropagation() {
				this.stopped = true;
			},
		},
		more || {}
	);
}

/**
 * Fire an event at an element, then up through its ancestors to the document, as a browser bubbles
 * it, and do what the browser does by default for a link followed: change the address's hash.
 *
 * @param {Element} target The element.
 * @param {Object}  event  The event.
 * @return {boolean} Whether the default was not prevented.
 */
function dispatch( target, event ) {
	event.target = target;

	const path = [];

	for ( let node = target; node; node = node.parentNode ) {
		path.push( node );
	}

	for ( const node of path ) {
		event.currentTarget = node;

		( node.listeners[ event.type ] || [] ).slice().forEach( ( listener ) => listener.call( node, event ) );

		if ( event.stopped || ! event.bubbles ) {
			break;
		}
	}

	if ( 'click' === event.type && ! event.defaultPrevented ) {
		const link = target.closest ? target.closest( 'a[href]' ) : null;

		if ( link && '#' === link.getAttribute( 'href' )[ 0 ] ) {
			target.ownerDocument.defaultView.navigate( link.getAttribute( 'href' ) );
		}
	}

	return ! event.defaultPrevented;
}

/**
 * Whether an element is shown: neither it nor any element around it is hidden.
 *
 * @param {Element} element The element.
 * @return {boolean} Whether it is shown.
 */
function shownOnPage( element ) {
	for ( let node = element; node && 1 === node.nodeType; node = node.parentNode ) {
		if ( node.hidden ) {
			return false;
		}
	}

	return true;
}

/**
 * Read markup into a tree under a body element.
 *
 * @param {Document} document The document.
 * @param {string}   html     The markup.
 * @return {Element} The body.
 */
function parse( document, html ) {
	const body = new Element( document, 'body' );
	const stack = [ body ];
	const pattern = /<!--[\s\S]*?-->|<\/([a-z0-9]+)\s*>|<([a-z0-9]+)((?:\s+[^\s"'>\/=]+(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s"'=<>`]+))?)*)\s*(\/?)>|([^<]+)/gi;
	let token;

	while ( null !== ( token = pattern.exec( html ) ) ) {
		const top = stack[ stack.length - 1 ];

		if ( token[ 1 ] ) {
			const name = token[ 1 ].toUpperCase();
			const at = stack.map( ( element ) => element.tagName ).lastIndexOf( name );

			if ( at > 0 ) {
				stack.length = at;
			}
		} else if ( token[ 2 ] ) {
			const element = new Element( document, token[ 2 ] );
			const attributes = /([^\s"'>\/=]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'=<>`]+)))?/g;
			let attribute;

			while ( null !== ( attribute = attributes.exec( token[ 3 ] || '' ) ) ) {
				const value = undefined !== attribute[ 2 ] ? attribute[ 2 ] : undefined !== attribute[ 3 ] ? attribute[ 3 ] : attribute[ 4 ];

				element.setAttribute( attribute[ 1 ].toLowerCase(), decode( undefined === value ? '' : value ) );
			}

			top.appendChild( element );

			if ( ! VOID.has( token[ 2 ].toLowerCase() ) && ! token[ 4 ] ) {
				stack.push( element );
			}
		} else if ( token[ 5 ] ) {
			top.appendChild( new Text( document, decode( token[ 5 ] ) ) );
		}
	}

	return body;
}

/**
 * A page holding some markup, with its window, at an address's hash.
 *
 * @param {string} html The body's markup.
 * @param {string} hash The address's hash when the page opens, such as `#programs`, or ''.
 * @return {Object} `document` and `window`.
 */
function page( html, hash ) {
	const document = {
		readyState: 'complete',
		activeElement: null,
		scrolledTo: [],
		listeners: {},
		nodeType: 9,
		parentNode: null,
		childNodes: [],
		createElement( tag ) {
			return new Element( document, tag );
		},
		createTextNode( text ) {
			return new Text( document, text );
		},
		getElementById( id ) {
			let found = null;

			walk( document.body, ( node ) => {
				if ( ! found && node.id === id ) {
					found = node;
				}
			} );

			return found;
		},
		querySelectorAll( selector ) {
			return document.body.querySelectorAll( selector );
		},
		querySelector( selector ) {
			return document.body.querySelector( selector );
		},
		addEventListener( type, listener ) {
			( document.listeners[ type ] = document.listeners[ type ] || [] ).push( listener );
		},
	};

	const window = {
		document,
		listeners: {},
		location: { hash: hash || '' },
		addEventListener( type, listener ) {
			( window.listeners[ type ] = window.listeners[ type ] || [] ).push( listener );
		},
		// A browser jumps to the target at once, if it is shown, and fires hashchange after.
		navigate( next ) {
			const target = document.getElementById( next.slice( 1 ) );

			if ( target && shownOnPage( target ) ) {
				document.scrolledTo.push( target.id );
			}

			if ( next !== window.location.hash ) {
				window.location.hash = next;
				( window.listeners.hashchange || [] ).forEach( ( listener ) => listener( makeEvent( 'hashchange' ) ) );
			}
		},
	};

	document.defaultView = window;
	document.body = parse( document, html );
	document.body.parentNode = document;

	return { document, window };
}

/**
 * Press a key on the element that has the focus: a keydown, and for Enter or Space on a button the
 * click a browser makes of it.
 *
 * @param {Object} where The page.
 * @param {string} key   The key, such as 'Enter' or ' '.
 */
function pressKey( where, key ) {
	const element = where.document.activeElement;

	if ( ! element ) {
		throw new Error( 'Nothing has the focus.' );
	}

	const event = makeEvent( 'keydown', { key } );

	element.dispatchEvent( event );

	if ( ! event.defaultPrevented && 'BUTTON' === element.tagName && ( 'Enter' === key || ' ' === key ) ) {
		element.click();
	}
}

module.exports = { page, pressKey, makeEvent };
```

Create `bin/js/hub-harness.js`:

```js
'use strict';
/*
 * The hub's view scripts, run in node on the server's own markup (bin/test-hub-js.php).
 *
 *   node bin/js/hub-harness.js <theme root>  < markup.json
 *
 * The markup comes on stdin, as bin/test-hub-js.php renders it with the theme's own render.php files:
 * a JSON object of named pages. Each scenario opens a page in the stand-in of bin/js/hub-dom.js, runs
 * the scripts as a browser runs a deferred script, after the markup, and drives the page as a person
 * does: a click, a key pressed on the control that has the focus, a link followed, an address with a
 * hash. Prints `ok   <scenario>` or `FAIL <scenario>` with what was expected and what happened, then
 * `N scenarios, M differ`, and exits 1 when any differs.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );
const { page, pressKey } = require( './hub-dom.js' );

const ROOT = path.resolve( process.argv[ 2 ] || path.join( __dirname, '..', '..' ) );
const PAGES = JSON.parse( fs.readFileSync( 0, 'utf8' ) );
const SCRIPTS = {
	index: fs.readFileSync( path.join( ROOT, 'blocks/program-index/view.js' ), 'utf8' ),
};

let ran = 0;
let failed = 0;

function scenario( name, want, build ) {
	let got;

	try {
		got = build();
	} catch ( error ) {
		got = 'threw: ' + error.message;
	}

	const ok = JSON.stringify( got ) === JSON.stringify( want );

	ran++;
	failed += ok ? 0 : 1;
	console.log( ( ok ? 'ok   ' : 'FAIL ' ) + name );

	if ( ! ok ) {
		console.log( '       exp: ' + JSON.stringify( want ) + '  got: ' + JSON.stringify( got ) );
	}
}

/**
 * Open a named page at a hash and run some of the view scripts on it.
 *
 * @param {string}   name    A page of PAGES.
 * @param {string[]} scripts Keys of SCRIPTS, run in order.
 * @param {string}   hash    The address's hash.
 * @return {Object} The page.
 */
function open( name, scripts, hash ) {
	const where = page( PAGES[ name ], hash || '' );
	const context = vm.createContext( { document: where.document, window: where.window } );

	scripts.forEach( ( key ) => vm.runInContext( SCRIPTS[ key ], context ) );

	return where;
}

/**
 * Focus an element, then click it, as a person does with the mouse or a tap.
 *
 * @param {Object}  where   The page.
 * @param {Element} element The element.
 */
function tap( where, element ) {
	element.focus();
	element.click();
}

/* ---- the program index ---------------------------------------------------------- */

const shown = ( where ) => where.document.querySelectorAll( '.wpc-hub-program' ).filter( ( card ) => ! card.hidden ).map( ( card ) => card.id.replace( 'program-', '' ) );
const pressed = ( where ) => where.document.querySelectorAll( '.wpc-hub-chip' ).map( ( chip ) => chip.getAttribute( 'aria-pressed' ) );
const chip = ( where, filter ) => where.document.querySelector( '.wpc-hub-chip[data-filter="' + filter + '"]' );
const said = ( where, root ) => where.document.querySelector( root + ' [role="status"]' ).textContent;

scenario( 'without the script the chips stay hidden and every card is shown', [ true, 6 ], () => {
	const where = open( 'index', [] );

	return [ where.document.querySelector( '.wpc-hub-chips' ).hidden, shown( where ).length ];
} );

scenario( 'the script shows the chips, All pressed, every card shown and nothing said', [ false, [ 'true', 'false', 'false', 'false' ], 6, '' ], () => {
	const where = open( 'index', [ 'index' ] );

	return [ where.document.querySelector( '.wpc-hub-chips' ).hidden, pressed( where ), shown( where ).length, said( where, '.wpc-hub-index' ) ];
} );

scenario( 'each chip pressed shows its programs, is the one pressed, says what it shows, and keeps the focus where it was', [
	[ 'student', [ 'false', 'true', 'false', 'false' ], [ 'campus-connect', 'wordpress-credits', 'learn-wordpress' ], 'Showing 3 of 6 programs', true ],
	[ 'faculty', [ 'false', 'false', 'true', 'false' ], [ 'facilitator-training', 'learn-wordpress' ], 'Showing 2 of 6 programs', true ],
	[ 'career', [ 'false', 'false', 'false', 'true' ], [ 'wordpress-credits', 'ai-leaders', 'wordpress-builder-certification' ], 'Showing 3 of 6 programs', true ],
	[ 'all', [ 'true', 'false', 'false', 'false' ], [ 'campus-connect', 'wordpress-credits', 'facilitator-training', 'learn-wordpress', 'ai-leaders', 'wordpress-builder-certification' ], 'Showing all 6 programs', true ],
], () => {
	const where = open( 'index', [ 'index' ] );

	return [ 'student', 'faculty', 'career', 'all' ].map( ( filter ) => {
		tap( where, chip( where, filter ) );

		return [ filter, pressed( where ), shown( where ), said( where, '.wpc-hub-index' ), where.document.activeElement === chip( where, filter ) ];
	} );
} );

scenario( 'by keyboard: Enter and Space on the chip that has the focus press it', [ [ 'false', 'false', 'true', 'false' ], [ 'false', 'true', 'false', 'false' ] ], () => {
	const where = open( 'index', [ 'index' ] );
	const states = [];

	chip( where, 'faculty' ).focus();
	pressKey( where, 'Enter' );
	states.push( pressed( where ) );
	chip( where, 'student' ).focus();
	pressKey( where, ' ' );
	states.push( pressed( where ) );

	return states;
} );

scenario( 'a link followed to a card the filter hides presses All first, so the card it opens is shown, and says so', [ true, [ 'true', 'false', 'false', 'false' ], 'Showing all 6 programs', '#program-wordpress-credits', [ 'program-wordpress-credits' ] ], () => {
	const where = open( 'index', [ 'index' ] );

	tap( where, chip( where, 'faculty' ) );
	where.document.querySelector( 'a[href="#program-wordpress-credits"]' ).click();

	return [ ! where.document.getElementById( 'program-wordpress-credits' ).hidden, pressed( where ), said( where, '.wpc-hub-index' ), where.window.location.hash, where.document.scrolledTo ];
} );

scenario( 'a link to a card the filter shows leaves the filter as it is', [ [ 'false', 'true', 'false', 'false' ], 'Showing 3 of 6 programs' ], () => {
	const where = open( 'index', [ 'index' ] );

	tap( where, chip( where, 'student' ) );
	where.document.querySelector( 'a[href="#program-wordpress-credits"]' ).click();

	return [ pressed( where ), said( where, '.wpc-hub-index' ) ];
} );

scenario( 'an address changed to a hidden card\'s, by the back button or by hand, presses All too', [ 'true', true ], () => {
	const where = open( 'index', [ 'index' ] );

	tap( where, chip( where, 'career' ) );
	where.window.navigate( '#program-facilitator-training' );

	return [ chip( where, 'all' ).getAttribute( 'aria-pressed' ), ! where.document.getElementById( 'program-facilitator-training' ).hidden ];
} );

scenario( 'a page opened at a card\'s address, or at one no card has, or at #program- alone, opens with All pressed and every card shown', [ [ 6, 'true' ], [ 6, 'true' ], [ 6, 'true' ] ], () => [ '#program-ai-leaders', '#program-nothing', '#program-' ].map( ( hash ) => {
	const where = open( 'index', [ 'index' ], hash );

	return [ shown( where ).length, chip( where, 'all' ).getAttribute( 'aria-pressed' ) ];
} ) );

scenario( 'a link to an address no card has changes nothing', [ [ 'false', 'false', 'true', 'false' ], 2 ], () => {
	const where = open( 'index', [ 'index' ] );

	tap( where, chip( where, 'faculty' ) );
	where.document.querySelector( 'a[href="#program-nothing"]' ).click();

	return [ pressed( where ), shown( where ).length ];
} );

scenario( 'the script run twice, as when it is printed twice, still answers each press once', [ 1, [ 'false', 'true', 'false', 'false' ] ], () => {
	const where = open( 'index', [ 'index', 'index' ] );

	tap( where, chip( where, 'student' ) );

	return [ where.document.querySelector( '.wpc-hub-index [role="status"]' ).textWrites, pressed( where ) ];
} );

console.log( ran + ' scenarios, ' + failed + ' differ' );
process.exit( failed ? 1 : 0 );
```

Create `bin/test-hub-js.php`:

```php
<?php
/**
 * Runs the hub's view scripts on the hub's own server markup, in node (theme 1.24.15).
 *
 * Run from the theme root:  php bin/test-hub-js.php
 *
 * The markup is rendered here, by the blocks' render.php files on the WordPress of
 * bin/stubs/hub-wp.php, and handed to bin/js/hub-harness.js on stdin, which runs each block's
 * view.js on a stand-in for the page (bin/js/hub-dom.js) and drives it as a person does. Each scenario
 * the harness prints is shown here as one line, and its summary, "N scenarios, M differ", is read as
 * well: a harness that stopped early with a status of 0 shows fewer lines than it counts.
 *
 * Every other check of the theme is PHP and needs nothing installed, so a machine without node on the
 * path gets one `skip` line and an exit of 0 rather than a failure.
 *
 * @package WPCredits_Theme
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/hub-wp.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub-content.php';

$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

if ( '' === $node ) {
	echo "skip the hub's view scripts were not run: node is not on the path\n";
	exit( 0 );
}

// The pages the scenarios open: each block on its own, with links of the kind the rest of the page
// holds pointing at it.
$pages = array(
	'index' => '<main>' . wpcredits_test_block( 'wpcredits/program-index' ) . '<nav><a href="#program-wordpress-credits">Start WordPress Credits</a> <a href="#program-nothing">Nowhere</a></nav></main>',
);

$pipes   = array();
$process = proc_open(
	escapeshellarg( $node ) . ' ' . escapeshellarg( __DIR__ . '/js/hub-harness.js' ) . ' ' . escapeshellarg( WPCREDITS_THEME_DIR ),
	array(
		0 => array( 'pipe', 'r' ),
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	),
	$pipes
);

fwrite( $pipes[0], (string) json_encode( $pages ) );
fclose( $pipes[0] );

$out = explode( "\n", trim( stream_get_contents( $pipes[1] ) . "\n" . stream_get_contents( $pipes[2] ) ) );

fclose( $pipes[1] );
fclose( $pipes[2] );

$status  = proc_close( $process );
$ran     = 0;
$differ  = 0;
$summary = null;

foreach ( $out as $line ) {
	if ( 0 === strpos( $line, 'ok   ' ) ) {
		++$ran;
		echo $line . "\n";
	} elseif ( 0 === strpos( $line, 'FAIL ' ) ) {
		++$ran;
		++$differ;
		echo $line . "\n";
	} elseif ( preg_match( '/^(\d+) scenarios, (\d+) differ$/', $line, $found ) ) {
		$summary = array( (int) $found[1], (int) $found[2] );
	} elseif ( '' !== trim( $line ) ) {
		echo $line . "\n";
	}
}

$whole = null !== $summary && $summary[0] === $ran && $summary[1] === $differ && 0 === $differ && 0 === $status && $ran > 0;

printf( "\n%s (%d scenarios)\n", $whole ? 'ALL PASS' : sprintf( '%d FAILURE(S)', max( 1, $differ ) ), $ran );

exit( $whole ? 0 : 1 );
```

In `bin/test-hub.php`, replace:

```php
	return (string) file_get_contents( WPCREDITS_THEME_DIR . $path );
}

echo "=== The template, and the front page ===\n";

$theme_json = json_decode( theme_file( 'theme.json' ), true );
```
with:

```php
	return (string) file_get_contents( WPCREDITS_THEME_DIR . $path );
}

/**
 * A color of theme.json, by its palette slug or its `settings.custom` path, such as `status.green`.
 *
 * @param string $name The slug or the path.
 * @return string `#rrggbb`.
 */
function color_of( $name ) {
	$json = json_decode( theme_file( 'theme.json' ), true );

	foreach ( $json['settings']['color']['palette'] as $color ) {
		if ( $color['slug'] === $name ) {
			return $color['color'];
		}
	}

	$at = $json['settings']['custom'];

	foreach ( explode( '.', $name ) as $key ) {
		$at = $at[ $key ];
	}

	return $at;
}

/**
 * The contrast ratio of two colors, as WCAG 2 defines it, to two decimals.
 *
 * @param string $one A color, `#rrggbb`.
 * @param string $two Another.
 * @return string
 */
function contrast( $one, $two ) {
	$luminance = static function ( $hex ) {
		$channels = array_map(
			static function ( $pair ) {
				$value = hexdec( $pair ) / 255;

				return $value <= 0.03928 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
			},
			str_split( ltrim( $hex, '#' ), 2 )
		);

		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	};

	$a = $luminance( $one );
	$b = $luminance( $two );

	return number_format( ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 ), 2 );
}

/**
 * The declarations of every rule in hub.css whose selector list is exactly the one given, in a media
 * query or not, comments left out, in order.
 *
 * @param string $selector The selector list, as the sheet writes it.
 * @return string[] Each rule's declarations, whitespace run together.
 */
function rules_for( $selector ) {
	$css = preg_replace( array( '#/\*.*?\*/#s', '/@media[^{]*\{/' ), '', theme_file( 'assets/css/hub.css' ) );

	preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $found, PREG_SET_ORDER );

	$rules = array();

	foreach ( $found as $rule ) {
		if ( preg_replace( '/\s+/', ' ', trim( $rule[1] ) ) === $selector ) {
			$rules[] = trim( preg_replace( '/\s+/', ' ', $rule[2] ) );
		}
	}

	return $rules;
}

/**
 * Some markup, parsed, to be asked with XPath.
 *
 * @param string $html The markup.
 * @return DOMXPath
 */
function dom_of( $html ) {
	$document = new DOMDocument();

	libxml_use_internal_errors( true );
	$document->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();

	return new DOMXPath( $document );
}

/**
 * What an XPath query finds: each node's text, whitespace run together, or one attribute of each.
 *
 * @param DOMXPath $xpath     The markup.
 * @param string   $query     The query.
 * @param string   $attribute An attribute's name, or '' for the text.
 * @return string[]
 */
function found( DOMXPath $xpath, $query, $attribute = '' ) {
	$said = array();

	foreach ( $xpath->query( $query ) as $node ) {
		$said[] = '' === $attribute ? trim( preg_replace( '/\s+/u', ' ', $node->textContent ) ) : (string) $node->getAttribute( $attribute );
	}

	return $said;
}

/**
 * The texts and the read-out attributes of some markup that are not one translated string: with the
 * translations marked, every text node and every `aria-label`, `data-announce` and `alt` is one
 * bracketed string, or holds bracketed strings and numbers alone. The decorative arrows are left out.
 *
 * @param string $html The markup, rendered with `$GLOBALS['marked']` on.
 * @return string[]
 */
function unmarked( $html ) {
	$xpath = dom_of( $html );
	$bare  = array();

	foreach ( $xpath->query( '//body//text()[normalize-space()] | //@aria-label | //@data-announce | //@alt' ) as $node ) {
		$text = trim( preg_replace( '/\s+/u', ' ', $node->nodeValue ) );

		if ( '→' === $text || '' === trim( preg_replace( '/\[[^\[\]]*\]|[0-9,.%]+/u', '', $text ) ) ) {
			continue;
		}

		$bare[] = $text;
	}

	return $bare;
}

echo "=== The template, and the front page ===\n";

$theme_json = json_decode( theme_file( 'theme.json' ), true );
```

In `bin/test-hub.php`, replace:

```php
	array( count( $texts ), array_values( $untranslated ), false !== strpos( $mark, '<span class="wpc-hub-planned">[(planned)]</span>' ) ),
	array( 135, array(), true ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
```
with:

```php
	array( count( $texts ), array_values( $untranslated ), false !== strpos( $mark, '<span class="wpc-hub-planned">[(planned)]</span>' ) ),
	array( 135, array(), true ) );

echo "\n=== The program index, without JavaScript ===\n";

$index_json = json_decode( theme_file( 'blocks/program-index/block.json' ), true );

ck( 'block.json: wpcredits/program-index, once a page, server-rendered, with its view script, its editor preview and the hub\'s stylesheet as its style',
	array( $index_json['name'], $index_json['supports']['multiple'], $index_json['render'], $index_json['viewScript'], $index_json['editorScript'], $index_json['style'], $index_json['textdomain'] ),
	array( 'wpcredits/program-index', false, 'file:./render.php', 'file:./view.js', 'file:./index.js', 'wpcredits-hub', 'wpcredits-theme' ) );

ck( 'its view script needs nothing and carries the theme\'s version; its editor preview needs the editor\'s packages and the server render',
	array( include WPCREDITS_THEME_DIR . 'blocks/program-index/view.asset.php', ( include WPCREDITS_THEME_DIR . 'blocks/program-index/index.asset.php' )['dependencies'] ),
	array( array( 'dependencies' => array(), 'version' => '9.9.9' ), array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-server-side-render' ) ) );

ck( 'the theme registers the block with its others',
	false !== strpos( theme_file( 'functions.php' ), "register_block_type( get_theme_file_path( 'blocks/program-index' ) );" ), true );

$index = wpcredits_test_block( 'wpcredits/program-index' );
$xp    = dom_of( $index );

ck( 'the wrapper is the block\'s, marked as the index',
	found( $xp, '/html/body/div', 'class' ), array( 'wp-block-wpcredits-program-index wpc-hub-index' ) );

ck( 'the chips are a labeled group printed hidden, for the script to show: four buttons, All pressed',
	array(
		found( $xp, '//div[@role="group"]', 'aria-label' ),
		found( $xp, '//div[@role="group"]', 'hidden' ),
		found( $xp, '//div[@role="group"]/button' ),
		found( $xp, '//div[@role="group"]/button', 'type' ),
		found( $xp, '//div[@role="group"]/button', 'data-filter' ),
		found( $xp, '//div[@role="group"]/button', 'aria-pressed' ),
	),
	array(
		array( 'Filter programs by audience' ),
		array( '' ),
		array( 'All', 'For students', 'For faculty', 'Career and credentials' ),
		array( 'button', 'button', 'button', 'button' ),
		array( 'all', 'student', 'faculty', 'career' ),
		array( 'true', 'false', 'false', 'false' ),
	) );

ck( 'each chip carries what its press announces: all 6, then 3, 2 and 3 of 6',
	found( $xp, '//button', 'data-announce' ),
	array( 'Showing all 6 programs', 'Showing 3 of 6 programs', 'Showing 2 of 6 programs', 'Showing 3 of 6 programs' ) );

ck( 'a polite status line, empty, for the script to fill',
	array( found( $xp, '//p[@role="status"]', 'class' ), found( $xp, '//p[@role="status"]' ) ),
	array( array( 'screen-reader-text' ), array( '' ) ) );

ck( 'six cards, every one shown: nothing but the chips carries hidden',
	array( found( $xp, '//article', 'id' ), found( $xp, '//article', 'data-audiences' ), count( $xp->query( '//*[@hidden]' ) ) ),
	array(
		array( 'program-campus-connect', 'program-wordpress-credits', 'program-facilitator-training', 'program-learn-wordpress', 'program-ai-leaders', 'program-wordpress-builder-certification' ),
		array( 'student', 'student career', 'faculty', 'student faculty', 'career', 'career' ),
		1,
	) );

ck( 'each card: its status tag, its step line, its name, what it is, its time and what you get',
	array(
		found( $xp, '//article//span[contains(@class,"wpc-hub-tag")]', 'class' ),
		found( $xp, '//article//span[contains(@class,"wpc-hub-tag")]' ),
		found( $xp, '//article/h3' ),
		found( $xp, '//article//dt' ),
	),
	array(
		array( 'wpc-hub-tag wpc-hub-tag--live', 'wpc-hub-tag wpc-hub-tag--live', 'wpc-hub-tag wpc-hub-tag--live', 'wpc-hub-tag wpc-hub-tag--live', 'wpc-hub-tag wpc-hub-tag--live', 'wpc-hub-tag wpc-hub-tag--development' ),
		array( 'Live', 'Live', 'Live', 'Live', 'Live', 'In development' ),
		array( 'Campus Connect', 'WordPress Credits', 'Facilitator Training', 'Learn WordPress', 'AI Leaders', 'WordPress Builder Certification' ),
		array_merge( ...array_fill( 0, 6, array( 'Time', 'You get' ) ) ),
	) );

ck( 'what you get is a list, one item to a line, the three planned ones marked on their own line',
	array( found( $xp, '//article[@id="program-wordpress-credits"]//ul[@class="wpc-hub-items"]/li' ), found( $xp, '//article//span[@class="wpc-hub-planned"]/..' ) ),
	array( array( 'Certificate', 'Badge', 'Hours transcript (planned)', 'Credit' ), array( 'Hours transcript (planned)', 'Facilitator listing (planned)', '$1,000 for completers (planned)' ) ) );

ck( 'the mark is kept on its item\'s line by a no-break space',
	false !== strpos( $index, '<li>Hours transcript&nbsp;<span class="wpc-hub-planned">(planned)</span></li>' ), true );

ck( 'the nine links, each its label and a decorative arrow a screen reader skips, opening in the same tab',
	array( found( $xp, '//article//p[@class="wpc-hub-program__links"]/a', 'href' ), found( $xp, '//article//a/span', 'aria-hidden' ), count( $xp->query( '//a[@target]' ) ) ),
	array(
		array(
			'https://events.wordpress.org/campusconnect/',
			'https://learn.wordpress.org/course/wordpress-credits-developer-track/',
			'https://learn.wordpress.org/course/wordpress-credits-designer-track/',
			'https://wordpress.github.io/WPCredits/',
			'https://make.wordpress.org/community/handbook/education/wordpress-facilitator-training-program/',
			'https://learn.wordpress.org/',
			'https://learn.wordpress.org/',
			'https://ai-leaders.org/',
			'https://wordpresscertifications.com/wordpress-builder-certification/',
		),
		array_fill( 0, 9, 'true' ),
		0,
	) );

ck( 'the toggles: white, the control border, ink-70 at 14px and 36px tall; pressed, the brand tint with an inset brand ring and weight 600; in forced colors, the system\'s highlight',
	array( rules_for( '.wpc-hub-chip' ), rules_for( '.wpc-hub-chip[aria-pressed="true"]' ) ),
	array(
		array( 'align-items: center; background: var( --wpc-surface ); border: 1px solid var( --wpc-control-border ); border-radius: var( --wpc-radius-pill ); color: var( --wpc-ink-70 ); cursor: pointer; display: inline-flex; font: 400 14px/20px var( --wpc-font ); min-height: 36px; padding: 7px 14px;' ),
		array( 'background: var( --wpc-brand-tint ); border-color: var( --wpc-brand ); box-shadow: inset 0 0 0 1px var( --wpc-brand ); color: var( --wpc-ink ); font-weight: 600;', 'background: Highlight; color: HighlightText; forced-color-adjust: none;' ),
	) );

ck( 'the status tags: Live in green on the green tint, In development in amber on the amber tint with the amber border',
	array( rules_for( '.wpc-hub-tag--live' ), rules_for( '.wpc-hub-tag--development' ) ),
	array( array( 'background: var( --wpc-green-tint ); color: var( --wpc-green );' ), array( 'background: var( --wpc-amber-tint ); border-color: var( --wpc-amber-border ); color: var( --wpc-amber );' ) ) );

ck( 'and the contrast of each, from theme.json: green on its tint 4.86, amber on its tint 4.90 (on the amber chip it would be 4.30), the control border 3.32 and the planned mark, ink-60, 5.45 on white',
	array( contrast( color_of( 'status.green' ), color_of( 'status.green-tint' ) ), contrast( color_of( 'status.amber' ), color_of( 'status.amber-tint' ) ), contrast( color_of( 'status.amber' ), color_of( 'status.amber-chip' ) ), contrast( color_of( 'line.control' ), color_of( 'base' ) ), contrast( color_of( 'ink-60' ), color_of( 'base' ) ) ),
	array( '4.86', '4.90', '4.30', '3.32', '5.45' ) );

ck( 'a card or chip the script hides stays out of the layout, whatever display its rule gives it',
	rules_for( '.wpc-hub-index [hidden]' ), array( 'display: none !important;' ) );

$GLOBALS['marked'] = true;
$marked_index      = wpcredits_test_block( 'wpcredits/program-index' );
$GLOBALS['marked'] = false;

ck( 'every word the index prints, and every label and announcement, is a translated string',
	unmarked( $marked_index ), array() );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them to see them fail**

```bash
php bin/test-hub.php 2>/dev/null | tail -1
php bin/test-hub-js.php 2>&1 | tail -1
```

Expected:

```text
16 FAILURE(S) (45 checks)
1 FAILURE(S) (0 scenarios)
```

(The block's files do not exist yet: every index check fails, and the harness stops reading the missing view script before any scenario.)

- [ ] **Step 3: Write the block and its styles**

In `assets/css/hub.css`, replace:

```css
		padding-right: var( --wpc-chrome-gutter );
	}
}
```
with:

```css
		padding-right: var( --wpc-chrome-gutter );
	}
}

/* ==========================================================================
   The toggles: the program index's chips and the pathway's switch
   ========================================================================== */

/* A control the view script has not shown yet stays out of the layout, whatever display a rule
   below gives it; so does a card the script hides. */
.wpc-hub-index [hidden] {
	display: none !important;
}

.wpc-hub-chips {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin: 0 0 22px;
}

/* Not pressed: white, the control border (3.32:1, the boundary of a control), ink-70 at 14px, 36px
   tall. Pressed: the theme's active-filter treatment, brand tint with an inset brand ring, and weight
   600, so the state is told by weight as well as by color. */
.wpc-hub-chip {
	align-items: center;
	background: var( --wpc-surface );
	border: 1px solid var( --wpc-control-border );
	border-radius: var( --wpc-radius-pill );
	color: var( --wpc-ink-70 );
	cursor: pointer;
	display: inline-flex;
	font: 400 14px/20px var( --wpc-font );
	min-height: 36px;
	padding: 7px 14px;
}

.wpc-hub-chip:hover {
	background: var( --wpc-surface-band );
}

.wpc-hub-chip[aria-pressed="true"] {
	background: var( --wpc-brand-tint );
	border-color: var( --wpc-brand );
	box-shadow: inset 0 0 0 1px var( --wpc-brand );
	color: var( --wpc-ink );
	font-weight: 600;
}

@media ( forced-colors: active ) {
	.wpc-hub-chip[aria-pressed="true"] {
		background: Highlight;
		color: HighlightText;
		forced-color-adjust: none;
	}
}

/* ==========================================================================
   The program index
   ========================================================================== */

.wpc-hub-programs {
	display: grid;
	gap: 16px;
	grid-template-columns: repeat( auto-fill, minmax( min( 100%, 300px ), 1fr ) );
}

.wpc-hub-program {
	background: var( --wpc-surface );
	border: 1px solid var( --wpc-line-card );
	border-radius: var( --wpc-radius-card );
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: 0;
	padding: 24px;
	scroll-margin-top: var( --wpc-landing-offset );
}

.wpc-hub-program > * {
	margin: 0;
}

.wpc-hub-program__meta {
	align-items: center;
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

/* The status tag: "Live" in green on the green tint (4.86:1), "In development" in amber on the amber
   tint with the amber border (4.90:1); the amber chip color would be 4.30:1, so it is not used. */
.wpc-hub-tag {
	border: 1px solid transparent;
	border-radius: var( --wpc-radius-panel );
	font: 500 12px/18px var( --wpc-font );
	letter-spacing: .06em;
	padding: 1px 8px;
	text-transform: uppercase;
}

.wpc-hub-tag--live {
	background: var( --wpc-green-tint );
	color: var( --wpc-green );
}

.wpc-hub-tag--development {
	background: var( --wpc-amber-tint );
	border-color: var( --wpc-amber-border );
	color: var( --wpc-amber );
}

.wpc-hub-program__step {
	color: var( --wpc-ink-60 );
	font: 500 12px/18px var( --wpc-font );
	letter-spacing: .06em;
	text-transform: uppercase;
}

.wpc-hub-program__name {
	font-size: 20px;
	line-height: 1.3;
}

.wpc-hub-program__what {
	color: var( --wpc-ink-70 );
	font-size: 14px;
	line-height: 1.55;
}

.wpc-hub-program__facts {
	display: grid;
	gap: 10px;
}

.wpc-hub-program__facts dt {
	color: var( --wpc-ink-60 );
	font: 500 12px/18px var( --wpc-font );
	letter-spacing: .06em;
	text-transform: uppercase;
}

.wpc-hub-program__facts dd {
	color: var( --wpc-ink );
	font-size: 14px;
	line-height: 1.5;
	margin: 2px 0 0;
}

/* A benefit to a line, no bullet, 4px apart: in a card's "You get" and in a step's or a level's
   detail alike. */
.wpc-hub-items {
	display: grid;
	gap: 4px;
	list-style: none;
	margin: 0;
	padding: 0;
}

/* "(planned)": ink-60 at 14px, 5.45:1 on white, on the item's line. */
.wpc-hub-planned {
	color: var( --wpc-ink-60 );
	font-size: 14px;
	white-space: nowrap;
}

.wpc-hub-program__links {
	border-top: 1px solid var( --wpc-line );
	display: flex;
	flex-wrap: wrap;
	gap: 6px 16px;
	margin-top: auto;
	padding-top: 12px;
}

.wpc-hub-program__links a {
	color: var( --wpc-brand );
	font-size: 14px;
	font-weight: 600;
	text-decoration: none;
}

.wpc-hub-program__links a:hover {
	color: var( --wpc-brand-dark );
	text-decoration: underline;
}
```

Create `blocks/program-index/block.json`:

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "wpcredits/program-index",
	"title": "Program index",
	"category": "theme",
	"icon": "index-card",
	"description": "Every education program as a card, with chips that filter them by audience. Without JavaScript every card is shown and no chip is.",
	"keywords": [ "programs", "education", "filter", "hub" ],
	"textdomain": "wpcredits-theme",
	"supports": {
		"html": false,
		"reusable": false,
		"multiple": false,
		"align": false,
		"interactivity": {
			"clientNavigation": false
		}
	},
	"editorScript": "file:./index.js",
	"viewScript": "file:./view.js",
	"style": "wpcredits-hub",
	"render": "file:./render.php"
}
```

Create `blocks/program-index/index.asset.php`:

```php
<?php
/**
 * Dependencies for blocks/program-index/index.js, the editor's preview.
 *
 * Hand-maintained because the theme has no build step; without it WordPress registers the editor
 * script with no dependencies, and `wp.blocks` is undefined by the time it runs.
 *
 * @package WPCredits_Theme
 */

return array(
	'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-server-side-render' ),
	'version'      => WPCREDITS_VERSION,
);
```

Create `blocks/program-index/index.js`:

```js
/**
 * Editor registration for the program index: a preview of what the server renders, and no settings.
 *
 * Hand-written against the `wp.*` globals rather than built from JSX, so the theme has no build step.
 * WordPress 7.1 shows a block that has no editor script as one the site "doesn't include support
 * for", so the block registers itself here and lets the server draw it. The texts are the theme's
 * own and translated there; nothing is set in the editor.
 */
( function ( blocks, element, blockEditor, ServerSideRender ) {
	'use strict';

	var el = element.createElement;

	blocks.registerBlockType( 'wpcredits/program-index', {
		edit: function () {
			return el( 'div', blockEditor.useBlockProps(), el( ServerSideRender, { block: 'wpcredits/program-index' } ) );
		},

		// Server-rendered: nothing is stored in post content.
		save: function () {
			return null;
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.serverSideRender ) );
```

Create `blocks/program-index/render.php`:

```php
<?php
/**
 * Server render for the program index: every program as a card, and the filter chips.
 *
 * **Everything is in the markup, and shown.** Without JavaScript the six cards are the index, one
 * after the other, and the chips that would filter them are printed with `hidden`, so there is no
 * control that does nothing; the view script shows the chips and hides the cards a chip leaves out.
 * Every word the script says comes from here, already translated: each chip carries the sentence its
 * press announces.
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

$wpcredits_programs = wpcredits_hub_programs();
$wpcredits_total    = count( $wpcredits_programs );
$wpcredits_filters  = array(
	'all'     => __( 'All', 'wpcredits-theme' ),
	'student' => __( 'For students', 'wpcredits-theme' ),
	'faculty' => __( 'For faculty', 'wpcredits-theme' ),
	'career'  => __( 'Career and credentials', 'wpcredits-theme' ),
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
				/* translators: 1: how many programs the filter shows, 2: how many there are. */
				: sprintf( _n( 'Showing %1$s of %2$s program', 'Showing %1$s of %2$s programs', $wpcredits_total, 'wpcredits-theme' ), number_format_i18n( $wpcredits_count ), number_format_i18n( $wpcredits_total ) );
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
					<span class="wpc-hub-program__step"><?php echo esc_html( $wpcredits_program['step'] ); ?></span>
				</p>
				<h3 class="wpc-hub-program__name"><?php echo esc_html( $wpcredits_program['name'] ); ?></h3>
				<p class="wpc-hub-program__what"><?php echo esc_html( $wpcredits_program['what'] ); ?></p>
				<dl class="wpc-hub-program__facts">
					<div><dt><?php esc_html_e( 'Time', 'wpcredits-theme' ); ?></dt><dd><?php echo esc_html( $wpcredits_program['time'] ); ?></dd></div>
					<div><dt><?php esc_html_e( 'You get', 'wpcredits-theme' ); ?></dt><dd><?php echo wpcredits_hub_items_html( $wpcredits_program['gets'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built. ?></dd></div>
				</dl>
				<p class="wpc-hub-program__links">
					<?php foreach ( $wpcredits_program['links'] as $wpcredits_link ) : ?>
						<a href="<?php echo esc_url( wpcredits_hub_url( $wpcredits_link['target'] ) ); ?>"><?php echo esc_html( $wpcredits_link['label'] ); ?><span aria-hidden="true"> →</span></a>
					<?php endforeach; ?>
				</p>
			</article>
		<?php endforeach; ?>
	</div>
</div>
```

Create `blocks/program-index/view.asset.php`:

```php
<?php
/**
 * Dependencies for blocks/program-index/view.js: none, at the theme's version.
 *
 * Hand-maintained because the theme has no build step; this is the file `@wordpress/scripts` would
 * otherwise generate.
 *
 * @package WPCredits_Theme
 */

return array(
	'dependencies' => array(),
	'version'      => WPCREDITS_VERSION,
);
```

Create `blocks/program-index/view.js`:

```js
/**
 * The program index's filter chips (theme 1.24.15).
 *
 * The server prints every card, shown, and the chips with `hidden`; this shows the chips, and on a
 * press marks the chip pressed, hides each card whose audiences the chip leaves out and says what it
 * shows in the status line, in the words the chip carries from the server. A link to a card the
 * filter hides, a pathway step's or the page's own address, presses All first, so the card it opens
 * is never hidden. Focus stays where the person put it; nothing is stored or written to the address.
 */
( function () {
	'use strict';

	function each( list, callback ) {
		Array.prototype.forEach.call( list, callback );
	}

	function init( root ) {
		var chips = root.querySelector( '.wpc-hub-chips' );
		var status = root.querySelector( '[role="status"]' );
		var cards = root.querySelectorAll( '.wpc-hub-program' );
		var buttons;
		var all;

		if ( ! chips || root.hasAttribute( 'data-wpc-ready' ) ) {
			return;
		}

		root.setAttribute( 'data-wpc-ready', '' );
		buttons = chips.querySelectorAll( 'button[data-filter]' );
		all = chips.querySelector( 'button[data-filter="all"]' );

		function press( button ) {
			var filter = button.getAttribute( 'data-filter' );

			each( buttons, function ( other ) {
				other.setAttribute( 'aria-pressed', other === button ? 'true' : 'false' );
			} );

			each( cards, function ( card ) {
				var audiences = ( card.getAttribute( 'data-audiences' ) || '' ).split( ' ' );

				card.hidden = 'all' !== filter && -1 === audiences.indexOf( filter );
			} );

			if ( status ) {
				status.textContent = button.getAttribute( 'data-announce' ) || '';
			}
		}

		function reveal( hash ) {
			var target;

			if ( ! all || 'string' !== typeof hash || 0 !== hash.indexOf( '#program-' ) ) {
				return;
			}

			target = document.getElementById( hash.slice( 1 ) );

			if ( target && target.hidden && root.contains( target ) ) {
				press( all );
			}
		}

		each( buttons, function ( button ) {
			button.addEventListener( 'click', function () {
				press( button );
			} );
		} );

		// A link followed: before the browser jumps, so the card it jumps to is shown.
		document.addEventListener( 'click', function ( event ) {
			var link = event.target && event.target.closest ? event.target.closest( 'a[href^="#program-"]' ) : null;

			if ( link ) {
				reveal( link.getAttribute( 'href' ) );
			}
		} );

		window.addEventListener( 'hashchange', function () {
			reveal( window.location.hash );
		} );

		chips.hidden = false;
		reveal( window.location.hash );
	}

	function start() {
		each( document.querySelectorAll( '.wpc-hub-index' ), init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
```

In `functions.php`, replace:

```php
	register_block_type( get_theme_file_path( 'blocks/handbook-launcher' ) );
	register_block_type( get_theme_file_path( 'blocks/guide-toc' ) );
	register_block_type( get_theme_file_path( 'blocks/program-stat' ) );
}
add_action( 'init', 'wpcredits_register_blocks' );

```
with:

```php
	register_block_type( get_theme_file_path( 'blocks/handbook-launcher' ) );
	register_block_type( get_theme_file_path( 'blocks/guide-toc' ) );
	register_block_type( get_theme_file_path( 'blocks/program-stat' ) );
	register_block_type( get_theme_file_path( 'blocks/program-index' ) );
}
add_action( 'init', 'wpcredits_register_blocks' );

```

- [ ] **Step 4: Run the theme's checks**

```bash
php bin/test-hub.php | tail -1
php bin/test-hub-js.php | tail -1
php bin/check-selectors.php "${WPCPM_PLUGIN:-$HOME/GitHub/wpcredits-program-manager}" | tail -5
"$(command -v phpcs || echo "$HOME/.composer/vendor/bin/phpcs")" --no-colors --report=summary | grep TOTAL
```

Expected:

```text
ALL PASS (45 checks)
ALL PASS (10 scenarios)
355 classes dressed across 8 stylesheets: 15 built at run time, 1 kept for an older plugin, 0 printed by nothing.
17 hub and figure classes dressed, 0 printed by nothing.
53 tokens read, 54 declared, 0 undefined.
0 shared table selectors without a dashboard body class.
No em or en dashes in the theme.
A TOTAL OF 0 ERRORS AND 6 WARNINGS WERE FOUND IN 3 FILES
```

- [ ] **Step 5: Commit**

```bash
git add blocks/program-index functions.php assets/css/hub.css bin/test-hub.php bin/test-hub-js.php bin/js
git commit -q -m "Education Hub: the program index, every program a card with chips that filter them by audience, complete without JavaScript, each press announced and a link to a hidden card pressing All"
```

---

### Task 4: The pathway

`wpcredits/pathway`: the switch between the students' seven steps and the institutions' four levels, each a rising staircase and every step's detail. The server prints both ladders whole, each step a link to its detail, and the switch and the hint with `hidden`; the view script turns the steps into buttons, shows one ladder and one detail at a time, announces each press, and follows an address naming a step.

**Files:**
- Create: `blocks/pathway/block.json`, `render.php`, `view.js`, `view.asset.php`, `index.js`, `index.asset.php`
- Modify: `functions.php` (the registration), `assets/css/hub.css` (the switch beside the chips; the pathway)
- Modify: `bin/test-hub.php`, `bin/test-hub-js.php` (the pathway's page, and both blocks together), `bin/js/hub-harness.js` (the pathway's scenarios)

**Interfaces:**
- Consumes: Task 2's `wpcredits_hub_steps()`, `wpcredits_hub_levels()`, `wpcredits_hub_items_html()`, `wpcredits_hub_url()`; Task 3's toggles in hub.css and the suites' helpers.
- Produces:
  - The markup of spec 7.3: `div.wp-block-wpcredits-pathway.wpc-hub-pathway` > `div.wpc-hub-switch[role=group][aria-label][hidden]` > `button.wpc-hub-switch__option[type=button][aria-pressed][aria-controls=wpc-hub-ladder-<key>]`; `p.screen-reader-text[role=status]`; per ladder (`students`, `institutions`) `section.wpc-hub-ladder#wpc-hub-ladder-<key>[aria-labelledby][data-start]` > `h2.wpc-hub-ladder__title#wpc-hub-ladder-<key>-title`, `p.wpc-hub-ladder__lede` (the students' with `span.wpc-hub-ladder__hint[hidden]`), `ol.wpc-hub-stairs[aria-label][style=--wpc-count:N]` > `li.wpc-hub-stair[style=--wpc-t:T]` > `a.wpc-hub-stair__pick[href=#wpc-hub-step-<key>-<n>][aria-describedby][data-announce]` and `span.wpc-hub-stair__by#wpc-hub-by-<key>-<n>`; `div.wpc-hub-details` > `article.wpc-hub-detail#wpc-hub-step-<key>-<n>`; `p.wpc-hub-ladder__key`.
  - `view.js`: marks the block `data-wpc-ready` and `is-interactive`; listens for clicks on `a[href^="#wpc-hub-step-"]` and for `hashchange`.
  - In `bin/test-hub.php`: `under_has_support()`, the rules hub.css declares inside `@supports selector( :has( * ) )`; `rules_for()` reads inside a supports rule as inside a media query. In `bin/test-hub-js.php`: `$pages` gains `pathway` and `page`; in the harness: `ladderShown()`, `detailsShown()`, `stepsPressed()`, `step()`, `option()`, `switchPressed()`.

- [ ] **Step 1: Write the checks and the scenarios**

In `bin/js/hub-harness.js`, replace:

```js
const PAGES = JSON.parse( fs.readFileSync( 0, 'utf8' ) );
const SCRIPTS = {
	index: fs.readFileSync( path.join( ROOT, 'blocks/program-index/view.js' ), 'utf8' ),
};

let ran = 0;
```
with:

```js
const PAGES = JSON.parse( fs.readFileSync( 0, 'utf8' ) );
const SCRIPTS = {
	index: fs.readFileSync( path.join( ROOT, 'blocks/program-index/view.js' ), 'utf8' ),
	pathway: fs.readFileSync( path.join( ROOT, 'blocks/pathway/view.js' ), 'utf8' ),
};

let ran = 0;
```

In `bin/js/hub-harness.js`, replace:

```js
	return [ where.document.querySelector( '.wpc-hub-index [role="status"]' ).textWrites, pressed( where ) ];
} );

console.log( ran + ' scenarios, ' + failed + ' differ' );
process.exit( failed ? 1 : 0 );
```
with:

```js
	return [ where.document.querySelector( '.wpc-hub-index [role="status"]' ).textWrites, pressed( where ) ];
} );

/* ---- the pathway --------------------------------------------------------------- */

const ladderShown = ( where ) => where.document.querySelectorAll( '.wpc-hub-ladder' ).filter( ( ladder ) => ! ladder.hidden ).map( ( ladder ) => ladder.id.replace( 'wpc-hub-ladder-', '' ) );
const detailsShown = ( where ) => where.document.querySelectorAll( '.wpc-hub-detail' ).filter( ( detail ) => ! detail.hidden ).map( ( detail ) => detail.id.replace( 'wpc-hub-step-', '' ) );
const stepsPressed = ( where ) => where.document.querySelectorAll( '.wpc-hub-stair__pick[aria-pressed="true"]' ).map( ( step ) => step.getAttribute( 'aria-controls' ).replace( 'wpc-hub-step-', '' ) );
const step = ( where, ladder, n ) => where.document.querySelector( '.wpc-hub-stair__pick[aria-controls="wpc-hub-step-' + ladder + '-' + n + '"]' );
const option = ( where, ladder ) => where.document.querySelector( '.wpc-hub-switch__option[aria-controls="wpc-hub-ladder-' + ladder + '"]' );
const switchPressed = ( where ) => where.document.querySelectorAll( '.wpc-hub-switch__option' ).map( ( one ) => one.getAttribute( 'aria-pressed' ) );

scenario( 'without the script both ladders show whole: the switch and the hint hidden, every detail shown, each step a link to its detail', [ true, true, [ 'students', 'institutions' ], 11, 11 ], () => {
	const where = open( 'pathway', [] );

	return [ where.document.querySelector( '.wpc-hub-switch' ).hidden, where.document.querySelector( '.wpc-hub-ladder__hint' ).hidden, ladderShown( where ), detailsShown( where ).length, where.document.querySelectorAll( 'a.wpc-hub-stair__pick' ).length ];
} );

scenario( 'the script shows the switch and the hint, marks the block interactive, and opens on the students\' ladder at step 3, the institutions\' keeping level 2', [ false, false, true, [ 'students' ], [ 'students-3', 'institutions-2' ], [ 'students-3', 'institutions-2' ], [ 'true', 'false' ], '' ], () => {
	const where = open( 'pathway', [ 'pathway' ] );

	return [
		where.document.querySelector( '.wpc-hub-switch' ).hidden,
		where.document.querySelector( '.wpc-hub-ladder__hint' ).hidden,
		where.document.querySelector( '.wpc-hub-pathway' ).classList.contains( 'is-interactive' ),
		ladderShown( where ),
		stepsPressed( where ),
		detailsShown( where ),
		switchPressed( where ),
		said( where, '.wpc-hub-pathway' ),
	];
} );

scenario( 'each step\'s link becomes a button of the same content, described by the same line, controlling its detail', [ 0, 11, [ 'BUTTON', 'button', 'Step 1 Attendee', 'wpc-hub-by-students-1', 'wpc-hub-step-students-1', 'Step 1 of 7: Attendee' ] ], () => {
	const where = open( 'pathway', [ 'pathway' ] );
	const first = step( where, 'students', 1 );

	return [
		where.document.querySelectorAll( 'a.wpc-hub-stair__pick' ).length,
		where.document.querySelectorAll( 'button.wpc-hub-stair__pick' ).length,
		[ first.tagName, first.type, first.textContent.replace( /\s+/g, ' ' ).trim(), first.getAttribute( 'aria-describedby' ), first.getAttribute( 'aria-controls' ), first.getAttribute( 'data-announce' ) ],
	];
} );

scenario( 'a step pressed shows its detail alone, is the one pressed, says so, and keeps the focus', [ [ 'students-5', 'institutions-2' ], [ 'students-5', 'institutions-2' ], 'Step 5 of 7: Specialist', true ], () => {
	const where = open( 'pathway', [ 'pathway' ] );

	tap( where, step( where, 'students', 5 ) );

	return [ stepsPressed( where ), detailsShown( where ), said( where, '.wpc-hub-pathway' ), where.document.activeElement === step( where, 'students', 5 ) ];
} );

scenario( 'by keyboard: Enter and Space on the step that has the focus press it', [ [ 'students-1', 'institutions-2' ], [ 'students-7', 'institutions-2' ] ], () => {
	const where = open( 'pathway', [ 'pathway' ] );
	const states = [];

	step( where, 'students', 1 ).focus();
	pressKey( where, 'Enter' );
	states.push( stepsPressed( where ) );
	step( where, 'students', 7 ).focus();
	pressKey( where, ' ' );
	states.push( stepsPressed( where ) );

	return states;
} );

scenario( 'the switch shows its ladder and hides the other, which keeps its pressed step, and says the ladder\'s heading', [
	[ [ 'institutions' ], [ 'false', 'true' ], 'Four partner levels, measured on student outcomes', [ 'students-5', 'institutions-2' ] ],
	[ [ 'students' ], [ 'true', 'false' ], 'Seven steps from first workshop to first job', [ 'students-5', 'institutions-2' ] ],
], () => {
	const where = open( 'pathway', [ 'pathway' ] );
	const states = [];

	tap( where, step( where, 'students', 5 ) );
	tap( where, option( where, 'institutions' ) );
	states.push( [ ladderShown( where ), switchPressed( where ), said( where, '.wpc-hub-pathway' ), stepsPressed( where ) ] );
	tap( where, option( where, 'students' ) );
	states.push( [ ladderShown( where ), switchPressed( where ), said( where, '.wpc-hub-pathway' ), stepsPressed( where ) ] );

	return states;
} );

scenario( 'a page opened at a level\'s address shows that ladder and that level, and lands on its detail', [ [ 'institutions' ], [ 'false', 'true' ], [ 'students-3', 'institutions-2' ], [ 'wpc-hub-step-institutions-2' ] ], () => {
	const where = open( 'pathway', [ 'pathway' ], '#wpc-hub-step-institutions-2' );

	return [ ladderShown( where ), switchPressed( where ), stepsPressed( where ), where.document.scrolledTo ];
} );

scenario( 'a link followed to a step\'s address, with the other ladder shown, shows its ladder and its step before the jump, and says so', [ [ 'students' ], [ 'students-6', 'institutions-4' ], 'Step 6 of 7: Community Leader', [ 'wpc-hub-step-students-6' ] ], () => {
	const where = open( 'pathway', [ 'pathway' ] );

	tap( where, option( where, 'institutions' ) );
	tap( where, step( where, 'institutions', 4 ) );
	where.document.querySelector( 'a[href="#wpc-hub-step-students-6"]' ).click();

	return [ ladderShown( where ), stepsPressed( where ), said( where, '.wpc-hub-pathway' ), where.document.scrolledTo ];
} );

scenario( 'an address that names no step, or no step that exists, opens on the students\' ladder at step 3', [ [ [ 'students' ], [ 'students-3', 'institutions-2' ] ], [ [ 'students' ], [ 'students-3', 'institutions-2' ] ], [ [ 'students' ], [ 'students-3', 'institutions-2' ] ] ], () => [ '#wpc-hub-step-students-99', '#wpc-hub-step-', '#wpc-hub-ladder-institutions' ].map( ( hash ) => {
	const where = open( 'pathway', [ 'pathway' ], hash );

	return [ ladderShown( where ), stepsPressed( where ) ];
} ) );

scenario( 'the script run twice still answers each press once', [ 1, [ 'students-2', 'institutions-2' ] ], () => {
	const where = open( 'pathway', [ 'pathway', 'pathway' ] );

	tap( where, step( where, 'students', 2 ) );

	return [ where.document.querySelector( '.wpc-hub-pathway [role="status"]' ).textWrites, stepsPressed( where ) ];
} );

scenario( 'the pathway with no program index on the page: a step\'s link to the index changes nothing on the pathway, and nothing throws', [ [ 'students-3', 'institutions-2' ], '#program-wordpress-credits' ], () => {
	const where = open( 'pathway', [ 'pathway' ] );

	where.document.querySelector( '.wpc-hub-detail a[href="#program-wordpress-credits"]' ).click();

	return [ stepsPressed( where ), where.window.location.hash ];
} );

/* ---- the page: both blocks ---------------------------------------------------- */

scenario( 'on the page, a step\'s link to a program the filter hides presses All and lands on the card', [ [ 'true', 'false', 'false', 'false' ], true, [ 'program-wordpress-credits' ] ], () => {
	const where = open( 'page', [ 'index', 'pathway' ] );

	tap( where, chip( where, 'faculty' ) );
	where.document.querySelector( '#wpc-hub-step-students-3 a[href="#program-wordpress-credits"]' ).click();

	return [ pressed( where ), ! where.document.getElementById( 'program-wordpress-credits' ).hidden, where.document.scrolledTo ];
} );

scenario( 'and each block\'s presses are its own: a step pressed leaves the filter, a chip pressed leaves the step', [ [ 'false', 'false', 'true', 'false' ], [ 'students-6', 'institutions-2' ], 'Showing 2 of 6 programs', 'Step 6 of 7: Community Leader' ], () => {
	const where = open( 'page', [ 'index', 'pathway' ] );

	tap( where, step( where, 'students', 6 ) );
	tap( where, chip( where, 'faculty' ) );

	return [ pressed( where ), stepsPressed( where ), said( where, '.wpc-hub-index' ), said( where, '.wpc-hub-pathway' ) ];
} );

console.log( ran + ' scenarios, ' + failed + ' differ' );
process.exit( failed ? 1 : 0 );
```

In `bin/test-hub-js.php`, replace:

```php
}

// The pages the scenarios open: each block on its own, with links of the kind the rest of the page
// holds pointing at it.
$pages = array(
	'index' => '<main>' . wpcredits_test_block( 'wpcredits/program-index' ) . '<nav><a href="#program-wordpress-credits">Start WordPress Credits</a> <a href="#program-nothing">Nowhere</a></nav></main>',
);

$pipes   = array();
```
with:

```php
}

// The pages the scenarios open: each block on its own, with links of the kind the rest of the page
// holds pointing at it, and the two together as the hub page holds them.
$pages = array(
	'index'   => '<main>' . wpcredits_test_block( 'wpcredits/program-index' ) . '<nav><a href="#program-wordpress-credits">Start WordPress Credits</a> <a href="#program-nothing">Nowhere</a></nav></main>',
	'pathway' => '<main>' . wpcredits_test_block( 'wpcredits/pathway' ) . '<nav><a href="#wpc-hub-step-students-6">Community Leader</a></nav></main>',
	'page'    => '<main>' . wpcredits_test_block( 'wpcredits/program-index' ) . wpcredits_test_block( 'wpcredits/pathway' ) . '</main>',
);

$pipes   = array();
```

In `bin/test-hub.php`, replace:

```php

/**
 * The declarations of every rule in hub.css whose selector list is exactly the one given, in a media
 * query or not, comments left out, in order.
 *
 * @param string $selector The selector list, as the sheet writes it.
 * @return string[] Each rule's declarations, whitespace run together.
 */
function rules_for( $selector ) {
	$css = preg_replace( array( '#/\*.*?\*/#s', '/@media[^{]*\{/' ), '', theme_file( 'assets/css/hub.css' ) );

	preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $found, PREG_SET_ORDER );

```
with:

```php

/**
 * The declarations of every rule in hub.css whose selector list is exactly the one given, in a media
 * query, in a supports rule or in neither, comments left out, in order.
 *
 * @param string $selector The selector list, as the sheet writes it.
 * @return string[] Each rule's declarations, whitespace run together.
 */
function rules_for( $selector ) {
	$css = preg_replace( array( '#/\*.*?\*/#s', '/@(?:media|supports)[^{]*\{/' ), '', theme_file( 'assets/css/hub.css' ) );

	preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $found, PREG_SET_ORDER );

```

In `bin/test-hub.php`, replace:

```php
	return $rules;
}

/**
 * Some markup, parsed, to be asked with XPath.
 *
```
with:

```php
	return $rules;
}

/**
 * The rules hub.css declares inside `@supports selector( :has( * ) )`, in order, each written
 * `selector { declarations }` with its whitespace run together: a focus ring drawn through `:has()`
 * and the `outline: none` it stands in for are declared together there, so a browser without `:has()`
 * keeps the theme's own ring.
 *
 * @return string[]
 */
function under_has_support() {
	$css = preg_replace( '#/\*.*?\*/#s', '', theme_file( 'assets/css/hub.css' ) );

	preg_match_all( '/@supports selector\( :has\( \* \) \) \{((?:[^{}]*\{[^{}]*\})*)[^{}]*\}/', $css, $blocks );

	$rules = array();

	foreach ( $blocks[1] as $block ) {
		preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $block, $found, PREG_SET_ORDER );

		foreach ( $found as $rule ) {
			$rules[] = preg_replace( '/\s+/', ' ', trim( $rule[1] ) . ' { ' . trim( $rule[2] ) . ' }' );
		}
	}

	return $rules;
}

/**
 * Some markup, parsed, to be asked with XPath.
 *
```

In `bin/test-hub.php`, replace:

```php
/**
 * The texts and the read-out attributes of some markup that are not one translated string: with the
 * translations marked, every text node and every `aria-label`, `data-announce` and `alt` is one
 * bracketed string, or holds bracketed strings and numbers alone. The decorative arrows are left out.
 *
 * @param string $html The markup, rendered with `$GLOBALS['marked']` on.
 * @return string[]
```
with:

```php
/**
 * The texts and the read-out attributes of some markup that are not one translated string: with the
 * translations marked, every text node and every `aria-label`, `data-announce` and `alt` is one
 * bracketed string, or holds bracketed strings, one inside another, and numbers alone. The decorative
 * arrows are left out.
 *
 * @param string $html The markup, rendered with `$GLOBALS['marked']` on.
 * @return string[]
```

In `bin/test-hub.php`, replace:

```php

	foreach ( $xpath->query( '//body//text()[normalize-space()] | //@aria-label | //@data-announce | //@alt' ) as $node ) {
		$text = trim( preg_replace( '/\s+/u', ' ', $node->nodeValue ) );

		if ( '→' === $text || '' === trim( preg_replace( '/\[[^\[\]]*\]|[0-9,.%]+/u', '', $text ) ) ) {
			continue;
		}

```
with:

```php

	foreach ( $xpath->query( '//body//text()[normalize-space()] | //@aria-label | //@data-announce | //@alt' ) as $node ) {
		$text = trim( preg_replace( '/\s+/u', ' ', $node->nodeValue ) );
		$left = $text;

		// A translated string may hold another, a name inside a sentence: the innermost first.
		do {
			$before = $left;
			$left   = preg_replace( '/\[[^\[\]]*\]/u', '', $left );
		} while ( $left !== $before );

		if ( '→' === $text || '' === trim( preg_replace( '/[0-9,.%]+/u', '', $left ) ) ) {
			continue;
		}

```

In `bin/test-hub.php`, replace:

```php
		0,
	) );

ck( 'the toggles: white, the control border, ink-70 at 14px and 36px tall; pressed, the brand tint with an inset brand ring and weight 600; in forced colors, the system\'s highlight',
	array( rules_for( '.wpc-hub-chip' ), rules_for( '.wpc-hub-chip[aria-pressed="true"]' ) ),
	array(
		array( 'align-items: center; background: var( --wpc-surface ); border: 1px solid var( --wpc-control-border ); border-radius: var( --wpc-radius-pill ); color: var( --wpc-ink-70 ); cursor: pointer; display: inline-flex; font: 400 14px/20px var( --wpc-font ); min-height: 36px; padding: 7px 14px;' ),
		array( 'background: var( --wpc-brand-tint ); border-color: var( --wpc-brand ); box-shadow: inset 0 0 0 1px var( --wpc-brand ); color: var( --wpc-ink ); font-weight: 600;', 'background: Highlight; color: HighlightText; forced-color-adjust: none;' ),
```
with:

```php
		0,
	) );

ck( 'the toggles, the chips and the pathway\'s switch alike: white, the control border, ink-70 at 14px and 36px tall; pressed, the brand tint with an inset brand ring and weight 600; in forced colors, the system\'s highlight',
	array( rules_for( '.wpc-hub-chip, .wpc-hub-switch__option' ), rules_for( '.wpc-hub-chip[aria-pressed="true"], .wpc-hub-switch__option[aria-pressed="true"]' ) ),
	array(
		array( 'align-items: center; background: var( --wpc-surface ); border: 1px solid var( --wpc-control-border ); border-radius: var( --wpc-radius-pill ); color: var( --wpc-ink-70 ); cursor: pointer; display: inline-flex; font: 400 14px/20px var( --wpc-font ); min-height: 36px; padding: 7px 14px;' ),
		array( 'background: var( --wpc-brand-tint ); border-color: var( --wpc-brand ); box-shadow: inset 0 0 0 1px var( --wpc-brand ); color: var( --wpc-ink ); font-weight: 600;', 'background: Highlight; color: HighlightText; forced-color-adjust: none;' ),
```

In `bin/test-hub.php`, replace:

```php
	array( contrast( color_of( 'status.green' ), color_of( 'status.green-tint' ) ), contrast( color_of( 'status.amber' ), color_of( 'status.amber-tint' ) ), contrast( color_of( 'status.amber' ), color_of( 'status.amber-chip' ) ), contrast( color_of( 'line.control' ), color_of( 'base' ) ), contrast( color_of( 'ink-60' ), color_of( 'base' ) ) ),
	array( '4.86', '4.90', '4.30', '3.32', '5.45' ) );

ck( 'a card or chip the script hides stays out of the layout, whatever display its rule gives it',
	rules_for( '.wpc-hub-index [hidden]' ), array( 'display: none !important;' ) );

$GLOBALS['marked'] = true;
$marked_index      = wpcredits_test_block( 'wpcredits/program-index' );
```
with:

```php
	array( contrast( color_of( 'status.green' ), color_of( 'status.green-tint' ) ), contrast( color_of( 'status.amber' ), color_of( 'status.amber-tint' ) ), contrast( color_of( 'status.amber' ), color_of( 'status.amber-chip' ) ), contrast( color_of( 'line.control' ), color_of( 'base' ) ), contrast( color_of( 'ink-60' ), color_of( 'base' ) ) ),
	array( '4.86', '4.90', '4.30', '3.32', '5.45' ) );

ck( 'a card, a chip, a ladder or a detail the script hides stays out of the layout, whatever display its rule gives it',
	rules_for( '.wpc-hub-index [hidden], .wpc-hub-pathway [hidden]' ), array( 'display: none !important;' ) );

$GLOBALS['marked'] = true;
$marked_index      = wpcredits_test_block( 'wpcredits/program-index' );
```

In `bin/test-hub.php`, replace:

```php
ck( 'every word the index prints, and every label and announcement, is a translated string',
	unmarked( $marked_index ), array() );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
```
with:

```php
ck( 'every word the index prints, and every label and announcement, is a translated string',
	unmarked( $marked_index ), array() );

echo "\n=== The pathway, without JavaScript ===\n";

$pathway_json = json_decode( theme_file( 'blocks/pathway/block.json' ), true );

ck( 'block.json: wpcredits/pathway, once a page, server-rendered, with its view script, its editor preview and the hub\'s stylesheet as its style',
	array( $pathway_json['name'], $pathway_json['supports']['multiple'], $pathway_json['render'], $pathway_json['viewScript'], $pathway_json['editorScript'], $pathway_json['style'] ),
	array( 'wpcredits/pathway', false, 'file:./render.php', 'file:./view.js', 'file:./index.js', 'wpcredits-hub' ) );

ck( 'its two asset files, as the program index\'s, and the theme registers it',
	array( include WPCREDITS_THEME_DIR . 'blocks/pathway/view.asset.php', ( include WPCREDITS_THEME_DIR . 'blocks/pathway/index.asset.php' )['dependencies'], false !== strpos( theme_file( 'functions.php' ), "register_block_type( get_theme_file_path( 'blocks/pathway' ) );" ) ),
	array( array( 'dependencies' => array(), 'version' => '9.9.9' ), array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-server-side-render' ), true ) );

$pathway = wpcredits_test_block( 'wpcredits/pathway' );
$px      = dom_of( $pathway );

ck( 'the switch is a group labeled Choose a pathway, printed hidden: For students pressed, For institutions not, each naming the ladder it shows',
	array( found( $px, '//div[@role="group"]', 'aria-label' ), count( $px->query( '//div[@role="group"][@hidden]' ) ), found( $px, '//div[@role="group"]/button' ), found( $px, '//div[@role="group"]/button', 'aria-pressed' ), found( $px, '//div[@role="group"]/button', 'aria-controls' ), found( $px, '//div[@role="group"]/button', 'type' ) ),
	array( array( 'Choose a pathway' ), 1, array( 'For students', 'For institutions' ), array( 'true', 'false' ), array( 'wpc-hub-ladder-students', 'wpc-hub-ladder-institutions' ), array( 'button', 'button' ) ) );

ck( 'a polite status line, empty, for the script to fill, and nothing hides it from a screen reader',
	array( found( $px, '//p[@role="status"]', 'class' ), found( $px, '//p[@role="status"]' ), count( $px->query( '//*[@aria-hidden][.//p[@role="status"] or self::p[@role="status"]]' ) ) ),
	array( array( 'screen-reader-text' ), array( '' ), 0 ) );

ck( 'both ladders, each a section named by its heading, opening on step 3 and on level 2',
	array( found( $px, '//section', 'id' ), found( $px, '//section', 'aria-labelledby' ), found( $px, '//section/h2', 'id' ), found( $px, '//section/h2' ), found( $px, '//section', 'data-start' ) ),
	array(
		array( 'wpc-hub-ladder-students', 'wpc-hub-ladder-institutions' ),
		array( 'wpc-hub-ladder-students-title', 'wpc-hub-ladder-institutions-title' ),
		array( 'wpc-hub-ladder-students-title', 'wpc-hub-ladder-institutions-title' ),
		array( 'Seven steps from first workshop to first job', 'Four partner levels, measured on student outcomes' ),
		array( '3', '2' ),
	) );

ck( 'each lede, the students\' with its hint printed hidden for the script to show',
	array( found( $px, '//p[@class="wpc-hub-ladder__lede"]' ), found( $px, '//span[@class="wpc-hub-ladder__hint"]' ), count( $px->query( '//span[@class="wpc-hub-ladder__hint"][@hidden]' ) ) ),
	array(
		array(
			'Each step earns a badge on your WordPress.org profile, the same profile employers look at. Enter at whichever step fits. Select a step to see what it leads to.',
			'Levels grow with the number of students you put through the programs and get certified, not the number of groups you run. Partners appear in an activity directory sorted by recent activity. We don\'t rank schools.',
		),
		array( 'Select a step to see what it leads to.' ),
		1,
	) );

ck( 'the staircases: seven steps and four levels, named, each a link to its detail, described by its line, its rise from 0 to 1',
	array(
		found( $px, '//ol', 'aria-label' ),
		found( $px, '//ol', 'style' ),
		found( $px, '//ol/li', 'style' ),
		found( $px, '//ol/li/a' ),
		found( $px, '//ol/li/a', 'href' ),
		found( $px, '//ol/li/span', 'id' ) === found( $px, '//ol/li/a', 'aria-describedby' ),
	),
	array(
		array( 'Steps for students', 'Levels for institutions' ),
		array( '--wpc-count:7', '--wpc-count:4' ),
		array( '--wpc-t:0', '--wpc-t:0.1667', '--wpc-t:0.3333', '--wpc-t:0.5', '--wpc-t:0.6667', '--wpc-t:0.8333', '--wpc-t:1', '--wpc-t:0', '--wpc-t:0.3333', '--wpc-t:0.6667', '--wpc-t:1' ),
		array( 'Step 1 Attendee', 'Step 2 Builder', 'Step 3 Contributor', 'Step 4 Graduate', 'Step 5 Specialist', 'Step 6 Community Leader', 'Step 7 Credentialed', 'Level 1 Participating', 'Level 2 Partner', 'Level 3 Established', 'Level 4 Flagship' ),
		array( '#wpc-hub-step-students-1', '#wpc-hub-step-students-2', '#wpc-hub-step-students-3', '#wpc-hub-step-students-4', '#wpc-hub-step-students-5', '#wpc-hub-step-students-6', '#wpc-hub-step-students-7', '#wpc-hub-step-institutions-1', '#wpc-hub-step-institutions-2', '#wpc-hub-step-institutions-3', '#wpc-hub-step-institutions-4' ),
		true,
	) );

ck( 'each step carries what its press announces',
	found( $px, '//ol/li/a', 'data-announce' ),
	array( 'Step 1 of 7: Attendee', 'Step 2 of 7: Builder', 'Step 3 of 7: Contributor', 'Step 4 of 7: Graduate', 'Step 5 of 7: Specialist', 'Step 6 of 7: Community Leader', 'Step 7 of 7: Credentialed', 'Level 1 of 4: Participating', 'Level 2 of 4: Partner', 'Level 3 of 4: Established', 'Level 4 of 4: Flagship' ) );

ck( 'eleven details, every one shown, each with the address its step links to, its count, its name, and its columns',
	array(
		found( $px, '//article', 'id' ) === array_map( static function ( $href ) { return substr( $href, 1 ); }, found( $px, '//ol/li/a', 'href' ) ),
		found( $px, '//article/p[@class="wpc-hub-detail__count"]' ),
		found( $px, '//article/h3' ),
		found( $px, '//section[@id="wpc-hub-ladder-students"]//article[1]//dt' ),
		found( $px, '//section[@id="wpc-hub-ladder-institutions"]//article[1]//dt' ),
		count( $px->query( '//*[@hidden]' ) ),
	),
	array(
		true,
		array( 'Step 1 of 7', 'Step 2 of 7', 'Step 3 of 7', 'Step 4 of 7', 'Step 5 of 7', 'Step 6 of 7', 'Step 7 of 7', 'Level 1 of 4', 'Level 2 of 4', 'Level 3 of 4', 'Level 4 of 4' ),
		array( 'Attendee', 'Builder', 'Contributor', 'Graduate', 'Specialist', 'Community Leader', 'Credentialed', 'Participating', 'Partner', 'Established', 'Flagship' ),
		array( 'Earned by', 'Unlocks', 'Academic recognition' ),
		array( 'Reached by', 'Gets' ),
		2,
	) );

ck( 'a detail: what earns the step, what it unlocks as a list with its planned items marked, and the academic recognition',
	array( found( $px, '//article[@id="wpc-hub-step-students-1"]//dd[not(ul)]' ), found( $px, '//article[@id="wpc-hub-step-students-1"]//dd/ul/li' ) ),
	array( array( 'Attend a Campus Connect event.', 'Recognized co-curricular activity or event credit, where the school offers it.' ), array( 'Certificate of participation', 'A seeded WordPress.org profile (planned)', 'A profile badge' ) ) );

ck( 'every planned item of the steps and the levels is marked, 26 of them, and under each ladder one line says what the mark means',
	array( count( $px->query( '//article//span[@class="wpc-hub-planned"]' ) ), found( $px, '//p[@class="wpc-hub-ladder__key"]' ) ),
	array( 26, array( 'Items marked planned are not offered yet.', 'Items marked planned are not offered yet.' ) ) );

ck( 'what each leads to, the last of each ladder saying where it ends',
	found( $px, '//article//span[@class="wpc-hub-detail__leads"]' ),
	array( 'Leads to step 2: Builder', 'Leads to step 3: Contributor', 'Leads to step 4: Graduate', 'Leads to step 5: Specialist', 'Leads to step 6: Community Leader', 'Leads to step 7: Credentialed', 'Leads to work', 'Leads to level 2: Partner', 'Leads to level 3: Established', 'Leads to level 4: Flagship', 'The top level' ) );

ck( 'and each next link is the core outline button\'s markup, where the design note\'s 6.7 says, the institution application read from the plugin',
	array( found( $px, '//article//span[contains(@class,"wp-block-button")]', 'class' ), found( $px, '//article//a[contains(@class,"wp-block-button__link")]', 'href' ) ),
	array(
		array_fill( 0, 11, 'wp-block-button is-style-outline wpc-button--large' ),
		array(
			'https://events.wordpress.org/campusconnect/',
			'https://learn.wordpress.org/',
			'#program-wordpress-credits',
			'#program-wordpress-credits',
			'#programs',
			'#program-facilitator-training',
			'https://jobs.wordpress.net/',
			'https://events.wordpress.org/campusconnect/organize/',
			'https://wordpresseducation.org/apply/',
			'#program-facilitator-training',
			'https://wordpresseducation.org/apply/',
		),
	) );

$GLOBALS['marked'] = true;
$marked_pathway    = wpcredits_test_block( 'wpcredits/pathway' );
$GLOBALS['marked'] = false;

ck( 'every word the pathway prints, and every label and announcement, is a translated string',
	unmarked( $marked_pathway ), array() );

ck( 'the staircase\'s count and rise are declared in the sheet, as every token the theme reads is, and a step\'s color runs from the brand tint to the brand border through its rise',
	array( rules_for( '.wpc-hub-stairs' )[0], rules_for( '.wpc-hub-stair' )[0] ),
	array(
		'--wpc-count: 7; display: grid; gap: 8px; list-style: none; margin: 0; padding: 0;',
		'--wpc-t: 0; background: color-mix( in srgb, var( --wpc-brand-border ) calc( var( --wpc-t ) * 100% ), var( --wpc-brand-tint ) ); border-radius: var( --wpc-radius-panel ); display: flex; flex-direction: column; gap: 6px; padding: 14px; position: relative;',
	) );

ck( 'at 900px and wider the steps rise from 160px to 360px; below, a row each, its bar widening from 52% to 100%',
	array( rules_for( '.wpc-hub-stair' )[1], false !== strpos( rules_for( '.wpc-hub-stair' )[2], 'calc( 52% + 48% * var( --wpc-t ) )' ) ),
	array( 'border-radius: var( --wpc-radius-card ) var( --wpc-radius-card ) 0 0; min-height: calc( 160px + 200px * var( --wpc-t ) );', true ) );

ck( 'the step\'s one movement is declared only for a reader who has not asked for less',
	(bool) preg_match( '/@media \( prefers-reduced-motion: no-preference \) \{\s*\.wpc-hub-pathway\.is-interactive \.wpc-hub-stair \{\s*transition: transform \.15s ease;/', theme_file( 'assets/css/hub.css' ) ), true );

ck( 'a step\'s detail and a program\'s card, each an address a link names, land below the sticky header',
	array_map( static function ( $selector ) { return false !== strpos( implode( ' ', rules_for( $selector ) ), 'scroll-margin-top: var( --wpc-landing-offset );' ); }, array( '.wpc-hub-detail', '.wpc-hub-program' ) ),
	array( true, true ) );

ck( 'the step\'s own focus outline gives way to the ring around the whole step only where :has() draws it; elsewhere the theme\'s own ring stays',
	array_values( preg_grep( '/wpc-hub-stair/', under_has_support() ) ),
	array( '.wpc-hub-stair__pick:focus-visible { outline: none; }', '.wpc-hub-stair:has( > .wpc-hub-stair__pick:focus-visible ) { outline: 2px solid var( --wpc-brand ); outline-offset: 2px; }' ) );

ck( 'and the contrast: ink-70 on the darkest step 6.36 and on the lightest 8.55, white on the brand 5.61, the brand tint on the brand 5.03',
	array( contrast( color_of( 'ink-70' ), color_of( 'brand-border' ) ), contrast( color_of( 'ink-70' ), color_of( 'brand-tint' ) ), contrast( color_of( 'base' ), color_of( 'brand' ) ), contrast( color_of( 'brand-tint' ), color_of( 'brand' ) ) ),
	array( '6.36', '8.55', '5.61', '5.03' ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them to see them fail**

```bash
php bin/test-hub.php 2>/dev/null | tail -1
php bin/test-hub-js.php 2>&1 | tail -1
```

Expected:

```text
21 FAILURE(S) (65 checks)
1 FAILURE(S) (0 scenarios)
```

(The pathway checks fail, and so do the two Task 3 checks whose selector lists now name the switch.)

- [ ] **Step 3: Write the block and its styles**

In `assets/css/hub.css`, replace:

```css
   ========================================================================== */

/* A control the view script has not shown yet stays out of the layout, whatever display a rule
   below gives it; so does a card the script hides. */
.wpc-hub-index [hidden] {
	display: none !important;
}

.wpc-hub-chips {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
```
with:

```css
   ========================================================================== */

/* A control the view script has not shown yet stays out of the layout, whatever display a rule
   below gives it; so does a card, a ladder or a detail the script hides. */
.wpc-hub-index [hidden],
.wpc-hub-pathway [hidden] {
	display: none !important;
}

.wpc-hub-chips,
.wpc-hub-switch {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
```

In `assets/css/hub.css`, replace:

```css
/* Not pressed: white, the control border (3.32:1, the boundary of a control), ink-70 at 14px, 36px
   tall. Pressed: the theme's active-filter treatment, brand tint with an inset brand ring, and weight
   600, so the state is told by weight as well as by color. */
.wpc-hub-chip {
	align-items: center;
	background: var( --wpc-surface );
	border: 1px solid var( --wpc-control-border );
```
with:

```css
/* Not pressed: white, the control border (3.32:1, the boundary of a control), ink-70 at 14px, 36px
   tall. Pressed: the theme's active-filter treatment, brand tint with an inset brand ring, and weight
   600, so the state is told by weight as well as by color. */
.wpc-hub-chip,
.wpc-hub-switch__option {
	align-items: center;
	background: var( --wpc-surface );
	border: 1px solid var( --wpc-control-border );
```

In `assets/css/hub.css`, replace:

```css
	padding: 7px 14px;
}

.wpc-hub-chip:hover {
	background: var( --wpc-surface-band );
}

.wpc-hub-chip[aria-pressed="true"] {
	background: var( --wpc-brand-tint );
	border-color: var( --wpc-brand );
	box-shadow: inset 0 0 0 1px var( --wpc-brand );
```
with:

```css
	padding: 7px 14px;
}

.wpc-hub-chip:hover,
.wpc-hub-switch__option:hover {
	background: var( --wpc-surface-band );
}

.wpc-hub-chip[aria-pressed="true"],
.wpc-hub-switch__option[aria-pressed="true"] {
	background: var( --wpc-brand-tint );
	border-color: var( --wpc-brand );
	box-shadow: inset 0 0 0 1px var( --wpc-brand );
```

In `assets/css/hub.css`, replace:

```css
}

@media ( forced-colors: active ) {
	.wpc-hub-chip[aria-pressed="true"] {
		background: Highlight;
		color: HighlightText;
		forced-color-adjust: none;
```
with:

```css
}

@media ( forced-colors: active ) {
	.wpc-hub-chip[aria-pressed="true"],
	.wpc-hub-switch__option[aria-pressed="true"] {
		background: Highlight;
		color: HighlightText;
		forced-color-adjust: none;
```

In `assets/css/hub.css`, replace:

```css
	color: var( --wpc-brand-dark );
	text-decoration: underline;
}
```
with:

```css
	color: var( --wpc-brand-dark );
	text-decoration: underline;
}

/* ==========================================================================
   The pathway
   ========================================================================== */

/* Without JavaScript both ladders show, one after the other. */
.wpc-hub-ladder + .wpc-hub-ladder {
	margin-top: 48px;
}

.wpc-hub-ladder__title {
	margin: 0 0 12px;
}

.wpc-hub-ladder__lede {
	color: var( --wpc-ink-70 );
	font-size: 16px;
	margin: 0 0 22px;
}

/* The staircase: a picture of the climb, each step a link to its detail (a button once the script
   has run). Its count and each step's rise, 0 at the first and 1 at the last, come from the markup;
   the values here are what a step reads before they are set. */
.wpc-hub-stairs {
	--wpc-count: 7;
	display: grid;
	gap: 8px;
	list-style: none;
	margin: 0;
	padding: 0;
}

.wpc-hub-stair {
	--wpc-t: 0;
	background: color-mix( in srgb, var( --wpc-brand-border ) calc( var( --wpc-t ) * 100% ), var( --wpc-brand-tint ) );
	border-radius: var( --wpc-radius-panel );
	display: flex;
	flex-direction: column;
	gap: 6px;
	padding: 14px;
	position: relative;
}

.wpc-hub-stair__pick {
	background: none;
	border: 0;
	color: var( --wpc-ink );
	cursor: pointer;
	display: flex;
	flex-direction: column;
	font: inherit;
	gap: 2px;
	padding: 0;
	text-align: left;
	text-decoration: none;
}

/* The whole step answers a press, and its focus ring is drawn around the whole step, outside it on
   white. */
.wpc-hub-stair__pick::after {
	content: "";
	inset: 0;
	position: absolute;
}

/* Only where :has() draws the ring around the step does the button give up its own; a browser without
   :has() keeps the theme's ring on the button. */
@supports selector( :has( * ) ) {
	.wpc-hub-stair__pick:focus-visible {
		outline: none;
	}

	.wpc-hub-stair:has( > .wpc-hub-stair__pick:focus-visible ) {
		outline: 2px solid var( --wpc-brand );
		outline-offset: 2px;
	}
}

.wpc-hub-stair__n {
	color: var( --wpc-ink-70 );
	font: 500 12px/18px var( --wpc-font );
	letter-spacing: .06em;
	text-transform: uppercase;
}

.wpc-hub-stair__name {
	font-size: 16px;
	font-weight: 600;
	line-height: 1.3;
}

/* Ink-70 at 13px: 6.36:1 on the darkest step, 8.55:1 on the lightest. */
.wpc-hub-stair__by {
	color: var( --wpc-ink-70 );
	font-size: 13px;
	line-height: 1.35;
}

/* The step pressed: brand, its name white (5.61:1) and its line in the brand tint (5.03:1). */
.wpc-hub-stair:has( > .wpc-hub-stair__pick[aria-pressed="true"] ) {
	background: var( --wpc-brand );
}

.wpc-hub-stair__pick[aria-pressed="true"],
.wpc-hub-stair:has( > .wpc-hub-stair__pick[aria-pressed="true"] ) .wpc-hub-stair__n {
	color: var( --wpc-surface );
}

.wpc-hub-stair:has( > .wpc-hub-stair__pick[aria-pressed="true"] ) .wpc-hub-stair__by {
	color: var( --wpc-brand-tint );
}

@media ( forced-colors: active ) {
	.wpc-hub-stair:has( > .wpc-hub-stair__pick[aria-pressed="true"] ) {
		background: Highlight;
		forced-color-adjust: none;
	}

	.wpc-hub-stair:has( > .wpc-hub-stair__pick[aria-pressed="true"] ) * {
		color: HighlightText;
	}
}

/* At 900px and wider the steps rise side by side from a 2px ink baseline, each at least 160px tall
   at the first and 360px at the last, so it rises and never cuts its text. */
@media ( min-width: 900px ) {
	.wpc-hub-stairs {
		align-items: end;
		border-bottom: 2px solid var( --wpc-ink );
		grid-template-columns: repeat( var( --wpc-count ), minmax( 0, 1fr ) );
	}

	.wpc-hub-stair {
		border-radius: var( --wpc-radius-card ) var( --wpc-radius-card ) 0 0;
		min-height: calc( 160px + 200px * var( --wpc-t ) );
	}
}

/* Below 900px, one full-width row a step, its filled bar widening from 52% to 100% with the climb,
   so nothing scrolls sideways at 375px. */
@media ( max-width: 899px ) {
	.wpc-hub-stair {
		background: linear-gradient( 90deg, color-mix( in srgb, var( --wpc-brand-border ) calc( var( --wpc-t ) * 100% ), var( --wpc-brand-tint ) ) calc( 52% + 48% * var( --wpc-t ) ), var( --wpc-surface-band ) 0 );
	}
}

/* The one movement on the page, a step lifting under the pointer, and only for a reader who has not
   asked for less motion; the theme's global reduce rule stands. */
@media ( prefers-reduced-motion: no-preference ) {
	.wpc-hub-pathway.is-interactive .wpc-hub-stair {
		transition: transform .15s ease;
	}

	.wpc-hub-pathway.is-interactive .wpc-hub-stair:hover {
		transform: translateY( -4px );
	}
}

/* The details: a card each, every one shown without JavaScript, the one selected with it. */
.wpc-hub-details {
	display: grid;
	gap: 16px;
	margin-top: 22px;
}

.wpc-hub-detail {
	background: var( --wpc-surface );
	border: 1px solid var( --wpc-line-card );
	border-radius: var( --wpc-radius-card );
	display: grid;
	gap: 16px;
	padding: 28px;
	scroll-margin-top: var( --wpc-landing-offset );
}

.wpc-hub-detail > * {
	margin: 0;
}

.wpc-hub-detail__count,
.wpc-hub-detail__cols dt,
.wpc-hub-detail__leads {
	color: var( --wpc-ink-60 );
	font: 500 12px/18px var( --wpc-font );
	letter-spacing: .06em;
	text-transform: uppercase;
}

.wpc-hub-detail__name {
	font-size: 26px;
	line-height: 1.23;
}

.wpc-hub-detail__cols {
	display: grid;
	gap: 20px 24px;
}

.wpc-hub-detail__cols dd {
	color: var( --wpc-ink );
	font-size: 14px;
	line-height: 1.55;
	margin: 4px 0 0;
}

/* Three columns side by side for a step, two for a level, at 900px and wider; stacked below. */
@media ( min-width: 900px ) {
	.wpc-hub-detail__cols {
		grid-auto-columns: minmax( 0, 1fr );
		grid-auto-flow: column;
	}
}

.wpc-hub-detail__next {
	align-items: center;
	border-top: 1px solid var( --wpc-line );
	display: flex;
	flex-wrap: wrap;
	gap: 12px 16px;
	justify-content: space-between;
	padding-top: 18px;
}

.wpc-hub-ladder__key {
	color: var( --wpc-ink-60 );
	font-size: 14px;
	margin: 16px 0 0;
}
```

Create `blocks/pathway/block.json`:

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "wpcredits/pathway",
	"title": "Pathway",
	"category": "theme",
	"icon": "chart-line",
	"description": "The pathway from a first workshop to a first job, and an institution's partner levels: a switch between the two, a staircase of steps and each step's detail. Without JavaScript both are shown whole.",
	"keywords": [ "pathway", "steps", "levels", "ladder", "hub" ],
	"textdomain": "wpcredits-theme",
	"supports": {
		"html": false,
		"reusable": false,
		"multiple": false,
		"align": false,
		"interactivity": {
			"clientNavigation": false
		}
	},
	"editorScript": "file:./index.js",
	"viewScript": "file:./view.js",
	"style": "wpcredits-hub",
	"render": "file:./render.php"
}
```

Create `blocks/pathway/index.asset.php`:

```php
<?php
/**
 * Dependencies for blocks/pathway/index.js, the editor's preview.
 *
 * Hand-maintained because the theme has no build step; without it WordPress registers the editor
 * script with no dependencies, and `wp.blocks` is undefined by the time it runs.
 *
 * @package WPCredits_Theme
 */

return array(
	'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-server-side-render' ),
	'version'      => WPCREDITS_VERSION,
);
```

Create `blocks/pathway/index.js`:

```js
/**
 * Editor registration for the pathway: a preview of what the server renders, and no settings.
 *
 * Hand-written against the `wp.*` globals rather than built from JSX, so the theme has no build step.
 * WordPress 7.1 shows a block that has no editor script as one the site "doesn't include support
 * for", so the block registers itself here and lets the server draw it. The texts are the theme's
 * own and translated there; nothing is set in the editor.
 */
( function ( blocks, element, blockEditor, ServerSideRender ) {
	'use strict';

	var el = element.createElement;

	blocks.registerBlockType( 'wpcredits/pathway', {
		edit: function () {
			return el( 'div', blockEditor.useBlockProps(), el( ServerSideRender, { block: 'wpcredits/pathway' } ) );
		},

		// Server-rendered: nothing is stored in post content.
		save: function () {
			return null;
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.serverSideRender ) );
```

Create `blocks/pathway/render.php`:

```php
<?php
/**
 * Server render for the pathway: the students' seven steps and the institutions' four levels, each a
 * staircase and every step's detail.
 *
 * **Everything is in the markup, and shown.** Without JavaScript both ladders show one after the
 * other: each its heading, its lede, the staircase as a picture of the climb whose steps are in-page
 * links to their details, then every detail as a card. The switch between the two ladders and the
 * line that says a step can be selected are printed with `hidden`, for the view script to show; it
 * turns each step into a button, shows one detail at a time and one ladder at a time. Every word the
 * script says comes from here, already translated.
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
		'hint'     => __( 'Select a step to see what it leads to.', 'wpcredits-theme' ),
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
		'hint'     => '',
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
			<p class="wpc-hub-ladder__lede"><?php echo esc_html( $wpcredits_ladder['lede'] ); ?>
				<?php if ( '' !== $wpcredits_ladder['hint'] ) : ?>
					<span class="wpc-hub-ladder__hint" hidden><?php echo esc_html( $wpcredits_ladder['hint'] ); ?></span>
				<?php endif; ?>
			</p>
			<ol class="wpc-hub-stairs" aria-label="<?php echo esc_attr( $wpcredits_ladder['label'] ); ?>" style="--wpc-count:<?php echo (int) $wpcredits_total; ?>">
				<?php
				foreach ( $wpcredits_rungs as $wpcredits_i => $wpcredits_rung ) :
					$wpcredits_n    = $wpcredits_i + 1;
					$wpcredits_rise = $wpcredits_total > 1 ? rtrim( rtrim( number_format( $wpcredits_i / ( $wpcredits_total - 1 ), 4, '.', '' ), '0' ), '.' ) : '0';
					?>
					<li class="wpc-hub-stair" style="--wpc-t:<?php echo esc_attr( $wpcredits_rise ); ?>">
						<a class="wpc-hub-stair__pick" href="#wpc-hub-step-<?php echo esc_attr( $wpcredits_key . '-' . $wpcredits_n ); ?>" aria-describedby="wpc-hub-by-<?php echo esc_attr( $wpcredits_key . '-' . $wpcredits_n ); ?>" data-announce="<?php echo esc_attr( sprintf( $wpcredits_ladder['announce'], number_format_i18n( $wpcredits_n ), number_format_i18n( $wpcredits_total ), $wpcredits_rung['name'] ) ); ?>"><span class="wpc-hub-stair__n"><?php echo esc_html( sprintf( $wpcredits_ladder['n'], number_format_i18n( $wpcredits_n ) ) ); ?></span> <span class="wpc-hub-stair__name"><?php echo esc_html( $wpcredits_rung['name'] ); ?></span></a>
						<span class="wpc-hub-stair__by" id="wpc-hub-by-<?php echo esc_attr( $wpcredits_key . '-' . $wpcredits_n ); ?>"><?php echo esc_html( $wpcredits_rung['by'] ); ?></span>
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
							<?php foreach ( $wpcredits_ladder['columns'] as $wpcredits_column => $wpcredits_heading ) : ?>
								<div><dt><?php echo esc_html( $wpcredits_heading ); ?></dt><dd><?php echo 'items' === $wpcredits_column ? wpcredits_hub_items_html( $wpcredits_rung['items'] ) : esc_html( $wpcredits_rung[ $wpcredits_column ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The list is escaped as it is built. ?></dd></div>
							<?php endforeach; ?>
						</dl>
						<p class="wpc-hub-detail__next"><span class="wpc-hub-detail__leads"><?php echo esc_html( null === $wpcredits_next ? $wpcredits_ladder['end'] : sprintf( $wpcredits_ladder['leads'], number_format_i18n( $wpcredits_n + 1 ), $wpcredits_next['name'] ) ); ?></span>
							<span class="wp-block-button is-style-outline wpc-button--large"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( wpcredits_hub_url( $wpcredits_rung['next']['target'] ) ); ?>"><?php echo esc_html( $wpcredits_rung['next']['label'] ); ?></a></span></p>
					</article>
				<?php endforeach; ?>
			</div>
			<p class="wpc-hub-ladder__key"><?php esc_html_e( 'Items marked planned are not offered yet.', 'wpcredits-theme' ); ?></p>
		</section>
	<?php endforeach; ?>
</div>
```

Create `blocks/pathway/view.asset.php`:

```php
<?php
/**
 * Dependencies for blocks/pathway/view.js: none, at the theme's version.
 *
 * Hand-maintained because the theme has no build step; this is the file `@wordpress/scripts` would
 * otherwise generate.
 *
 * @package WPCredits_Theme
 */

return array(
	'dependencies' => array(),
	'version'      => WPCREDITS_VERSION,
);
```

Create `blocks/pathway/view.js`:

```js
/**
 * The pathway's switch and steps (theme 1.24.15).
 *
 * The server prints both ladders, every detail shown, each step an in-page link to its detail, and
 * the switch and the hint with `hidden`. This shows the switch and the hint, marks the block
 * interactive, turns each step's link into a button of the same content that says which detail it
 * controls and whether it is pressed, presses each ladder's opening step (its `data-start`) and
 * shows that detail alone, and shows the first ladder alone. A step pressed shows its detail and says
 * so; the switch shows its ladder, the other keeping its own pressed step, and says the ladder's
 * heading. An address naming a detail, the page's own or a link followed, selects its ladder and its
 * step. Every word said comes from the server; focus stays where the person put it; nothing is stored
 * or written to the address.
 */
( function () {
	'use strict';

	function each( list, callback ) {
		Array.prototype.forEach.call( list, callback );
	}

	function init( root ) {
		var switcher = root.querySelector( '.wpc-hub-switch' );
		var status = root.querySelector( '[role="status"]' );
		var ladders = root.querySelectorAll( '.wpc-hub-ladder' );
		var options;

		if ( ! switcher || ! ladders.length || root.hasAttribute( 'data-wpc-ready' ) ) {
			return;
		}

		root.setAttribute( 'data-wpc-ready', '' );
		options = switcher.querySelectorAll( 'button[aria-controls]' );

		function say( text ) {
			if ( status ) {
				status.textContent = text;
			}
		}

		function picks( ladder ) {
			return ladder.querySelectorAll( '.wpc-hub-stair__pick' );
		}

		function select( ladder, pick, announce ) {
			var controls = pick.getAttribute( 'aria-controls' );

			each( picks( ladder ), function ( other ) {
				other.setAttribute( 'aria-pressed', other === pick ? 'true' : 'false' );
			} );

			each( ladder.querySelectorAll( '.wpc-hub-detail' ), function ( detail ) {
				detail.hidden = detail.id !== controls;
			} );

			if ( announce ) {
				say( pick.getAttribute( 'data-announce' ) || '' );
			}
		}

		function show( ladder, announce ) {
			var heading = ladder.querySelector( 'h2' );

			each( ladders, function ( other ) {
				other.hidden = other !== ladder;
			} );

			each( options, function ( option ) {
				option.setAttribute( 'aria-pressed', option.getAttribute( 'aria-controls' ) === ladder.id ? 'true' : 'false' );
			} );

			if ( announce ) {
				say( heading ? heading.textContent.trim() : '' );
			}
		}

		// An address naming a detail of this block: its ladder shown and its step pressed.
		function follow( hash, announce ) {
			var detail;
			var ladder;
			var pick;

			if ( 'string' !== typeof hash || 0 !== hash.indexOf( '#wpc-hub-step-' ) ) {
				return null;
			}

			detail = document.getElementById( hash.slice( 1 ) );
			ladder = detail && detail.closest ? detail.closest( '.wpc-hub-ladder' ) : null;

			if ( ! ladder || ! root.contains( ladder ) ) {
				return null;
			}

			pick = ladder.querySelector( '.wpc-hub-stair__pick[aria-controls="' + detail.id + '"]' );

			if ( ! pick ) {
				return null;
			}

			show( ladder, false );
			select( ladder, pick, announce );

			return detail;
		}

		each( ladders, function ( ladder ) {
			var start = parseInt( ladder.getAttribute( 'data-start' ), 10 );
			var all;

			each( picks( ladder ), function ( link ) {
				var button = document.createElement( 'button' );

				button.type = 'button';
				button.className = link.className;
				button.setAttribute( 'aria-describedby', link.getAttribute( 'aria-describedby' ) || '' );
				button.setAttribute( 'aria-controls', ( link.getAttribute( 'href' ) || '#' ).slice( 1 ) );
				button.setAttribute( 'aria-pressed', 'false' );
				button.setAttribute( 'data-announce', link.getAttribute( 'data-announce' ) || '' );

				while ( link.firstChild ) {
					button.appendChild( link.firstChild );
				}

				link.parentNode.replaceChild( button, link );

				button.addEventListener( 'click', function () {
					select( ladder, button, true );
				} );
			} );

			all = picks( ladder );

			if ( all.length ) {
				select( ladder, all[ start >= 1 && start <= all.length ? start - 1 : 0 ], false );
			}
		} );

		each( options, function ( option ) {
			option.addEventListener( 'click', function () {
				var ladder = document.getElementById( option.getAttribute( 'aria-controls' ) );

				if ( ladder && root.contains( ladder ) ) {
					show( ladder, true );
				}
			} );
		} );

		// A link followed to a detail: before the browser jumps, so the detail it jumps to is shown.
		document.addEventListener( 'click', function ( event ) {
			var link = event.target && event.target.closest ? event.target.closest( 'a[href^="#wpc-hub-step-"]' ) : null;

			if ( link ) {
				follow( link.getAttribute( 'href' ), true );
			}
		} );

		window.addEventListener( 'hashchange', function () {
			follow( window.location.hash, true );
		} );

		switcher.hidden = false;
		each( root.querySelectorAll( '.wpc-hub-ladder__hint' ), function ( hint ) {
			hint.hidden = false;
		} );
		root.classList.add( 'is-interactive' );
		show( ladders[ 0 ], false );

		// The page opened at a detail's address: the browser jumped before this ran, while every detail
		// was shown, so the jump is made again to where the detail is now.
		( function ( opened ) {
			if ( opened ) {
				opened.scrollIntoView();
			}
		}( follow( window.location.hash, false ) ) );
	}

	function start() {
		each( document.querySelectorAll( '.wpc-hub-pathway' ), init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
```

In `functions.php`, replace:

```php
	register_block_type( get_theme_file_path( 'blocks/guide-toc' ) );
	register_block_type( get_theme_file_path( 'blocks/program-stat' ) );
	register_block_type( get_theme_file_path( 'blocks/program-index' ) );
}
add_action( 'init', 'wpcredits_register_blocks' );

```
with:

```php
	register_block_type( get_theme_file_path( 'blocks/guide-toc' ) );
	register_block_type( get_theme_file_path( 'blocks/program-stat' ) );
	register_block_type( get_theme_file_path( 'blocks/program-index' ) );
	register_block_type( get_theme_file_path( 'blocks/pathway' ) );
}
add_action( 'init', 'wpcredits_register_blocks' );

```

- [ ] **Step 4: Run the theme's checks**

```bash
php bin/test-hub.php | tail -1
php bin/test-hub-js.php | tail -1
php bin/check-selectors.php "${WPCPM_PLUGIN:-$HOME/GitHub/wpcredits-program-manager}" | tail -5
"$(command -v phpcs || echo "$HOME/.composer/vendor/bin/phpcs")" --no-colors --report=summary | grep TOTAL
```

Expected:

```text
ALL PASS (65 checks)
ALL PASS (23 scenarios)
355 classes dressed across 8 stylesheets: 15 built at run time, 1 kept for an older plugin, 0 printed by nothing.
37 hub and figure classes dressed, 0 printed by nothing.
55 tokens read, 56 declared, 0 undefined.
0 shared table selectors without a dashboard body class.
No em or en dashes in the theme.
A TOTAL OF 0 ERRORS AND 6 WARNINGS WERE FOUND IN 3 FILES
```

- [ ] **Step 5: Commit**

```bash
git add blocks/pathway functions.php assets/css/hub.css bin/test-hub.php bin/test-hub-js.php bin/js
git commit -q -m "Education Hub: the pathway, the students' seven steps and the institutions' four levels as rising staircases with every detail, complete without JavaScript, the switch and each step announced, an address naming a step followed"
```

---

### Task 5: The figures

The program figure block learns `graduation`, `recommend`, `keep` and `outcomes-source`, a `label` attribute, and an editor preview of what the server draws for each instance. Each outcome figure is its number, its label and its base inside the block, from `WPCPM_Outcome_Figures::published()` (plugin 1.122.24) through `wpcredits_outcome_figures()`, or nothing at all; the source line gives the date the figures were read in the site's format and time zone. The four count stats are drawn byte for byte as before.

**Files:**
- Create: `blocks/program-stat/index.js`, `blocks/program-stat/index.asset.php` (the editor's preview)
- Modify: `blocks/program-stat/block.json` (the description, the keywords, `label`, `editorScript`), `blocks/program-stat/render.php` (the outcome branch, before the counts), `inc/template-tags.php` (`wpcredits_outcome_figures()`, `wpcredits_outcome_stat_html()`), `assets/css/hub.css` (the figures)
- Modify: `bin/stubs/hub-wp.php` (transients, `sanitize_key()`, `WP_User_Query`), `bin/test-hub.php`

**Interfaces:**
- Consumes: **`WPCPM_Outcome_Figures::published(): array`, from the plugin's plan (its Task 2):** `array()` when no figure may be shown, otherwise `array( 'read' => int, 'figures' => array( 'graduation' => array( 'share' => int, 'count' => int, 'n' => int ), 'recommend' => array( ... ), 'keep' => array( ... ) ) )`, `read` a Unix time, only the figures that may be shown, in that order. `get_block_wrapper_attributes()`; the `date_format` option and `wp_date()`.
- Produces:
  - `wpcredits_outcome_figures(): array`: the same shape, or `array()` without the class or the method or for anything that is not whole numbers that make sense together (a share from 0 to 100, a count from 1 to its n, a read time above 0).
  - `wpcredits_outcome_stat_html( string $stat, string $label = '' ): string|null`: null for a stat that is not an outcome figure; '' for one with nothing to show; for a figure `<div class="wp-block-wpcredits-program-stat wpc-figure"><p class="wpc-figure__value">88%</p><p class="wpc-figure__label">…</p><p class="wpc-figure__base">…</p></div>`; for `outcomes-source` `<p class="wp-block-wpcredits-program-stat wpc-figure-source">As of October 12, 2026, counted by this site … <a href="https://wordpress.github.io/WPCredits/">WordPress Credits impact dashboard</a>.</p>`.
  - The block's attribute `label` (string, default '').
  - `blocks/program-stat/index.js`: registers `wpcredits/program-stat` in the editor, previewing it through `wp.serverSideRender` with `attributes: props.attributes`, and saving nothing; `index.asset.php` names `wp-blocks`, `wp-block-editor`, `wp-element` and `wp-server-side-render` at the theme's version.
  - In `bin/test-hub.php`: `stat_block()`, `outcome_blocks()`, `$figure_stats`, `$monday`; the stand-in classes `WPCPM_Mentors_Dashboard` and `WPCPM_Outcome_Figures` (a static `$published`), declared from here on.

- [ ] **Step 1: Write the checks**

In `bin/stubs/hub-wp.php`, replace:

```php
	return $out;
}

/* ---- rendering ------------------------------------------------------------------- */

/**
```
with:

```php
	return $out;
}

/* ---- the program's counts, for the program figure block -------------------------- */

$GLOBALS['transients'] = array();
$GLOBALS['role_count'] = array(); // Role => how many accounts hold it.

function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function get_transient( $key ) { return array_key_exists( $key, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $key ] : false; }
function set_transient( $key, $value, $ttl = 0 ) { $GLOBALS['transients'][ $key ] = $value; return true; }

/** Core's user query, as far as a count of one role reaches. */
class WP_User_Query {
	private $role;
	public function __construct( $args ) { $this->role = $args['role']; }
	public function get_total() { return isset( $GLOBALS['role_count'][ $this->role ] ) ? (int) $GLOBALS['role_count'][ $this->role ] : 0; }
}

/* ---- rendering ------------------------------------------------------------------- */

/**
```

In `bin/test-hub.php`, replace:

```php
require_once __DIR__ . '/stubs/hub-wp.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub-content.php';

$fail  = 0;
$total = 0;
```
with:

```php
require_once __DIR__ . '/stubs/hub-wp.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub-content.php';
require_once WPCREDITS_THEME_DIR . 'inc/template-tags.php';

$fail  = 0;
$total = 0;
```

In `bin/test-hub.php`, replace:

```php
}

/**
 * The texts and the read-out attributes of some markup that are not one translated string: with the
 * translations marked, every text node and every `aria-label`, `data-announce` and `alt` is one
 * bracketed string, or holds bracketed strings, one inside another, and numbers alone. The decorative
 * arrows are left out.
 *
 * @param string $html The markup, rendered with `$GLOBALS['marked']` on.
 * @return string[]
 */
function unmarked( $html ) {
	$xpath = dom_of( $html );
	$bare  = array();

	foreach ( $xpath->query( '//body//text()[normalize-space()] | //@aria-label | //@data-announce | //@alt' ) as $node ) {
		$text = trim( preg_replace( '/\s+/u', ' ', $node->nodeValue ) );
		$left = $text;

		// A translated string may hold another, a name inside a sentence: the innermost first.
```
with:

```php
}

/**
 * The texts and the read-out attributes of some markup that are not translated strings: with the
 * translations marked, the text of every paragraph, heading, list item, term, cell and button, and
 * every `aria-label`, `data-announce` and `alt`, is bracketed strings, one inside another where a name
 * sits in a sentence, and numbers alone. A text is read whole where it is written, so a sentence with
 * a link in it is one text; the decorative arrows are left out.
 *
 * @param string $html The markup, rendered with `$GLOBALS['marked']` on.
 * @return string[]
 */
function unmarked( $html ) {
	$xpath = dom_of( $html );
	$texts = array();
	$whole = array( 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'dt', 'dd', 'button', 'th', 'td', 'figcaption' );

	foreach ( $xpath->query( '//body//text()[normalize-space()]' ) as $node ) {
		$holder = $node->parentNode;

		while ( $holder->parentNode && ! in_array( strtolower( $holder->nodeName ), $whole, true ) && 'body' !== strtolower( $holder->parentNode->nodeName ) ) {
			$holder = $holder->parentNode;
		}

		$holder = in_array( strtolower( $holder->nodeName ), $whole, true ) ? $holder : $node;

		$texts[ $holder->getNodePath() ] = $holder->textContent;
	}

	foreach ( $xpath->query( '//@aria-label | //@data-announce | //@alt' ) as $node ) {
		$texts[ $node->getNodePath() ] = $node->nodeValue;
	}

	$bare = array();

	foreach ( $texts as $text ) {
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
		$left = $text;

		// A translated string may hold another, a name inside a sentence: the innermost first.
```

In `bin/test-hub.php`, replace:

```php
			$left   = preg_replace( '/\[[^\[\]]*\]/u', '', $left );
		} while ( $left !== $before );

		if ( '→' === $text || '' === trim( preg_replace( '/[0-9,.%]+/u', '', $left ) ) ) {
			continue;
		}

		$bare[] = $text;
	}

	return $bare;
```
with:

```php
			$left   = preg_replace( '/\[[^\[\]]*\]/u', '', $left );
		} while ( $left !== $before );

		if ( '' !== trim( preg_replace( '/[0-9,.%→\s]+/u', '', $left ) ) ) {
			$bare[] = $text;
		}
	}

	return $bare;
```

In `bin/test-hub.php`, replace:

```php
	array( contrast( color_of( 'ink-70' ), color_of( 'brand-border' ) ), contrast( color_of( 'ink-70' ), color_of( 'brand-tint' ) ), contrast( color_of( 'base' ), color_of( 'brand' ) ), contrast( color_of( 'brand-tint' ), color_of( 'brand' ) ) ),
	array( '6.36', '8.55', '5.61', '5.03' ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
```
with:

```php
	array( contrast( color_of( 'ink-70' ), color_of( 'brand-border' ) ), contrast( color_of( 'ink-70' ), color_of( 'brand-tint' ) ), contrast( color_of( 'base' ), color_of( 'brand' ) ), contrast( color_of( 'brand-tint' ), color_of( 'brand' ) ) ),
	array( '6.36', '8.55', '5.61', '5.03' ) );

echo "\n=== The program figure: the counts, as before ===\n";

/**
 * The program figure block's markup, for some attributes.
 *
 * @param array $attributes The block's attributes.
 * @return string
 */
function stat_block( array $attributes ) {
	return wpcredits_test_block( 'wpcredits/program-stat', $attributes );
}

$counts_without_plugin = array(
	stat_block( array( 'stat' => 'mentors', 'fallback' => '90' ) ),
	stat_block( array( 'stat' => 'students' ) ),
);
$outcome_without_plugin = stat_block( array( 'stat' => 'graduation', 'fallback' => '90' ) );

// The plugin's mentors dashboard, which is how the theme tells the plugin is active.
if ( ! class_exists( 'WPCPM_Mentors_Dashboard' ) ) {
	/** Present, so the plugin reads as active. */
	class WPCPM_Mentors_Dashboard {}
}

$GLOBALS['role_count'] = array( 'wpcpm_mentor' => 88, 'wpcpm_institution' => 1234 );
$counts_with_plugin    = array(
	stat_block( array( 'stat' => 'mentors', 'fallback' => '90' ) ),
	stat_block( array( 'stat' => 'institutions' ) ),
	stat_block( array( 'stat' => 'sponsors', 'fallback' => ' 12 ' ) ),
	stat_block( array( 'stat' => 'students' ) ),
	stat_block( array() ),
	stat_block( array( 'stat' => 'nothing', 'fallback' => '7' ) ),
);

ck( 'the four count stats are drawn as 1.24.14 drew them, byte for byte: a count, or the typed fallback for a zero or with no plugin, or nothing',
	array( $counts_without_plugin, $counts_with_plugin, $GLOBALS['transients'] ),
	array(
		array( '<p class="wp-block-wpcredits-program-stat">90</p>', '' ),
		array( '<p class="wp-block-wpcredits-program-stat">88</p>', '<p class="wp-block-wpcredits-program-stat">1,234</p>', '<p class="wp-block-wpcredits-program-stat">12</p>', '', '<p class="wp-block-wpcredits-program-stat">88</p>', '<p class="wp-block-wpcredits-program-stat">7</p>' ),
		array( 'wpcredits_count_wpcpm_mentor' => 88, 'wpcredits_count_wpcpm_institution' => 1234, 'wpcredits_count_wpcpm_sponsor' => 0, 'wpcredits_count_wpcpm_student' => 0 ),
	) );

echo "\n=== The program figure: the outcome figures ===\n";

$figure_stats = array( 'graduation', 'recommend', 'keep', 'outcomes-source' );

/**
 * What the block prints for each outcome stat, in order.
 *
 * @param array $attributes Attributes every block shares.
 * @return string[]
 */
function outcome_blocks( array $attributes = array() ) {
	global $figure_stats;

	return array_map( static function ( $stat ) use ( $attributes ) { return stat_block( array_merge( $attributes, array( 'stat' => $stat ) ) ); }, $figure_stats );
}

ck( 'with a plugin older than 1.122.24, or none, no figure and no source line, a typed fallback ignored, with the plugin off as with it on',
	array( $outcome_without_plugin, outcome_blocks( array( 'fallback' => '90' ) ), function_exists( 'wpcredits_outcome_figures' ) ? wpcredits_outcome_figures() : 'no wpcredits_outcome_figures()' ), array( '', array( '', '', '', '' ), array() ) );

// The plugin's outcome figures, from here on: what published() hands over is a check's.
if ( ! class_exists( 'WPCPM_Outcome_Figures' ) ) {
	/** What the plugin may show, as a check sets it. */
	class WPCPM_Outcome_Figures {
		public static $published = array();
		public static function published() { return self::$published; }
	}
}

$monday = array(
	'read'    => 1791788400,
	'figures' => array(
		'graduation' => array( 'share' => 87, 'count' => 361, 'n' => 413 ),
		'recommend'  => array( 'share' => 88, 'count' => 277, 'n' => 315 ),
		'keep'       => array( 'share' => 79, 'count' => 245, 'n' => 310 ),
	),
);

WPCPM_Outcome_Figures::$published = $monday;

ck( 'each figure in its own block: the number, the label and the base, the label and base from the stored counts',
	outcome_blocks(),
	array(
		'<div class="wp-block-wpcredits-program-stat wpc-figure"><p class="wpc-figure__value">87%</p><p class="wpc-figure__label">of WordPress Credits students graduate rather than drop out</p><p class="wpc-figure__base">361 of 413 students who graduated or dropped out</p></div>',
		'<div class="wp-block-wpcredits-program-stat wpc-figure"><p class="wpc-figure__value">88%</p><p class="wpc-figure__label">of graduates would recommend the program</p><p class="wpc-figure__base">315 answers to the final survey</p></div>',
		'<div class="wp-block-wpcredits-program-stat wpc-figure"><p class="wpc-figure__value">79%</p><p class="wpc-figure__label">of graduates plan to keep contributing to WordPress</p><p class="wpc-figure__base">310 answers to the final survey</p></div>',
		'<p class="wp-block-wpcredits-program-stat wpc-figure-source">As of October 12, 2026, counted by this site from the program\'s records with the same definitions as the <a href="https://wordpress.github.io/WPCredits/">WordPress Credits impact dashboard</a>.</p>',
	) );

$GLOBALS['opts']['date_format'] = 'j F Y';
$GLOBALS['opts']['timezone']    = 'UTC';
$other_site                     = stat_block( array( 'stat' => 'outcomes-source' ) );
$GLOBALS['opts']['timezone']    = 'Pacific/Honolulu';
$day_before                     = stat_block( array( 'stat' => 'outcomes-source' ) );
$GLOBALS['opts']['date_format'] = 'F j, Y';
$GLOBALS['opts']['timezone']    = 'Europe/Warsaw';

ck( 'the date is the site\'s own: its date format and its time zone',
	array( false !== strpos( $other_site, 'As of 12 October 2026,' ), false !== strpos( $day_before, 'As of 11 October 2026,' ) ), array( true, true ) );

ck( 'a label typed into the block replaces the figure\'s own; one of spaces does not',
	array( stat_block( array( 'stat' => 'keep', 'label' => 'of graduates keep <contributing>' ) ), stat_block( array( 'stat' => 'keep', 'label' => '   ' ) ) === outcome_blocks()[2] ),
	array( '<div class="wp-block-wpcredits-program-stat wpc-figure"><p class="wpc-figure__value">79%</p><p class="wpc-figure__label">of graduates keep &lt;contributing&gt;</p><p class="wpc-figure__base">310 answers to the final survey</p></div>', true ) );

WPCPM_Outcome_Figures::$published = array(
	'read'    => 1791788400,
	'figures' => array( 'graduation' => array( 'share' => 100, 'count' => 1, 'n' => 1 ), 'recommend' => array( 'share' => 100, 'count' => 1, 'n' => 1 ) ),
);

ck( 'a figure the plugin leaves out, under its minimum sample, is not drawn and the others are; one answer or one student reads in the singular',
	array( outcome_blocks()[2], false !== strpos( outcome_blocks()[0], '1 of 1 student who graduated or dropped out' ), false !== strpos( outcome_blocks()[1], '1 answer to the final survey' ), '' !== outcome_blocks()[3] ),
	array( '', true, true, true ) );

WPCPM_Outcome_Figures::$published = array();

ck( 'with nothing the plugin may show, nothing at all: no figure, no source line',
	outcome_blocks(), array( '', '', '', '' ) );

$junk = array(
	'not a list'               => 'figures',
	'no read time'             => array( 'figures' => $monday['figures'] ),
	'a read time as a string'  => array( 'read' => '1791788400', 'figures' => $monday['figures'] ),
	'figures not a list'       => array( 'read' => 1791788400, 'figures' => 'all' ),
	'a share over 100'         => array( 'read' => 1791788400, 'figures' => array( 'graduation' => array( 'share' => 187, 'count' => 361, 'n' => 413 ) ) ),
	'a negative share'         => array( 'read' => 1791788400, 'figures' => array( 'graduation' => array( 'share' => -1, 'count' => 361, 'n' => 413 ) ) ),
	'a share as a string'      => array( 'read' => 1791788400, 'figures' => array( 'graduation' => array( 'share' => '87', 'count' => 361, 'n' => 413 ) ) ),
	'a count over its n'       => array( 'read' => 1791788400, 'figures' => array( 'graduation' => array( 'share' => 87, 'count' => 414, 'n' => 413 ) ) ),
	'a zero count'             => array( 'read' => 1791788400, 'figures' => array( 'graduation' => array( 'share' => 0, 'count' => 0, 'n' => 413 ) ) ),
	'a figure missing its n'   => array( 'read' => 1791788400, 'figures' => array( 'graduation' => array( 'share' => 87, 'count' => 361 ) ) ),
);

$drawn = array();

foreach ( $junk as $what => $published ) {
	WPCPM_Outcome_Figures::$published = $published;
	$drawn[ $what ]                   = implode( '', outcome_blocks() );
}

ck( 'whatever else the plugin hands over draws nothing, never a figure made of it',
	$drawn, array_fill_keys( array_keys( $junk ), '' ) );

WPCPM_Outcome_Figures::$published = $monday;
$GLOBALS['marked']                = true;
$marked_figures                   = implode( '', outcome_blocks() );
$GLOBALS['marked']                = false;

ck( 'every word the figures print is a translated string, the percent sign among them',
	array( unmarked( $marked_figures ), false !== strpos( $marked_figures, '[87%]' ) ), array( array(), true ) );

ck( 'block.json: the label attribute, empty by default, beside the stat and the fallback',
	json_decode( theme_file( 'blocks/program-stat/block.json' ), true )['attributes'],
	array( 'stat' => array( 'type' => 'string', 'default' => 'mentors' ), 'fallback' => array( 'type' => 'string', 'default' => '' ), 'label' => array( 'type' => 'string', 'default' => '' ) ) );

$stat_asset = WPCREDITS_THEME_DIR . 'blocks/program-stat/index.asset.php';

ck( 'block.json: an editor preview, as the program index and the pathway have, its asset file naming the editor\'s packages and the server render at the theme\'s version',
	array( json_decode( theme_file( 'blocks/program-stat/block.json' ), true )['editorScript'] ?? null, is_file( $stat_asset ) ? include $stat_asset : null ),
	array( 'file:./index.js', array( 'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-server-side-render' ), 'version' => '9.9.9' ) ) );

$stat_editor = is_file( WPCREDITS_THEME_DIR . 'blocks/program-stat/index.js' ) ? theme_file( 'blocks/program-stat/index.js' ) : '';

ck( 'the preview registers the block and hands the server each instance\'s own attributes, so the hero\'s four figures and the homepage\'s mentors card each preview their own stat',
	array( false !== strpos( $stat_editor, "blocks.registerBlockType( 'wpcredits/program-stat', {" ), false !== strpos( $stat_editor, "el( ServerSideRender, { block: 'wpcredits/program-stat', attributes: props.attributes } )" ), false !== strpos( $stat_editor, 'return null;' ) ),
	array( true, true, true ) );

ck( 'the figure\'s number: brand, 44px and bold, in tabular figures, 34px below 900px',
	array( rules_for( '.wpc-figure__value' ) ),
	array( array( 'color: var( --wpc-brand ); font-size: 44px; font-variant-numeric: tabular-nums; font-weight: 700; letter-spacing: -.02em; line-height: 1.14;', 'font-size: 34px;' ) ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them to see them fail**

```bash
php bin/test-hub.php 2>/dev/null | grep -E "^FAIL|FAILURE" | cut -c1-140
```

Expected:

```text
FAIL with a plugin older than 1.122.24, or none, no figure and no source line, a typed fallback ignored, with the plugin off as with it on
FAIL each figure in its own block: the number, the label and the base, the label and base from the stored counts
FAIL the date is the site's own: its date format and its time zone
FAIL a label typed into the block replaces the figure's own; one of spaces does not
FAIL a figure the plugin leaves out, under its minimum sample, is not drawn and the others are; one answer or one student reads in the singu
FAIL every word the figures print is a translated string, the percent sign among them
FAIL block.json: the label attribute, empty by default, beside the stat and the fallback
FAIL block.json: an editor preview, as the program index and the pathway have, its asset file naming the editor's packages and the server re
FAIL the preview registers the block and hands the server each instance's own attributes, so the hero's four figures and the homepage's ment
FAIL the figure's number: brand, 44px and bold, in tabular figures, 34px below 900px
10 FAILURE(S) (78 checks)
```

The four count stats already pass, on 1.24.14's render: that check guards them byte for byte through the change.

- [ ] **Step 3: Write the figures**

In `assets/css/hub.css`, replace:

```css
	font-size: 14px;
	margin: 16px 0 0;
}
```
with:

```css
	font-size: 14px;
	margin: 16px 0 0;
}

/* ==========================================================================
   The outcome figures
   ========================================================================== */

/* A figure: its number in brand, tabular so a new week's figure does not shift the column, its
   label and its base under it. The rows are divided by the card hairline. */
.wpc-figure {
	padding: 14px 0;
}

.wpc-figure + .wpc-figure {
	border-top: 1px solid var( --wpc-line-card );
}

.wpc-figure > * {
	margin: 0;
}

.wpc-figure__value {
	color: var( --wpc-brand );
	font-size: 44px;
	font-variant-numeric: tabular-nums;
	font-weight: 700;
	letter-spacing: -.02em;
	line-height: 1.14;
}

.wpc-figure__label {
	color: var( --wpc-ink-70 );
	font-size: 16px;
	line-height: 1.4;
	margin-top: 4px;
}

.wpc-figure__base {
	color: var( --wpc-ink-60 );
	font-size: 14px;
	margin-top: 2px;
}

.wpc-figure-source {
	color: var( --wpc-ink-60 );
	font-size: 14px;
	margin: 10px 0 0;
}

@media ( max-width: 899px ) {
	.wpc-figure__value {
		font-size: 34px;
	}
}
```

In `blocks/program-stat/block.json`, replace:

```json
	"title": "Program figure",
	"category": "theme",
	"icon": "chart-bar",
	"description": "A live count from the program: mentors, students, institutions or sponsors. Falls back to the number written into the block when the plugin is not available.",
	"keywords": [ "count", "mentors", "students", "statistic", "number" ],
	"textdomain": "wpcredits-theme",
	"attributes": {
		"stat": {
```
with:

```json
	"title": "Program figure",
	"category": "theme",
	"icon": "chart-bar",
	"description": "A live count from the program: mentors, students, institutions or sponsors, falling back to the number written into the block when the plugin is not available; or one of the program's outcome figures, or their source line, which show only when the plugin may show them.",
	"keywords": [ "count", "mentors", "students", "statistic", "number", "outcomes", "graduation" ],
	"textdomain": "wpcredits-theme",
	"attributes": {
		"stat": {
```

In `blocks/program-stat/block.json`, replace:

```json
		"fallback": {
			"type": "string",
			"default": ""
		}
	},
	"supports": {
```
with:

```json
		"fallback": {
			"type": "string",
			"default": ""
		},
		"label": {
			"type": "string",
			"default": ""
		}
	},
	"supports": {
```

In `blocks/program-stat/block.json`, replace:

```json
			"clientNavigation": true
		}
	},
	"render": "file:./render.php"
}
```
with:

```json
			"clientNavigation": true
		}
	},
	"editorScript": "file:./index.js",
	"render": "file:./render.php"
}
```

Create `blocks/program-stat/index.asset.php`:

```php
<?php
/**
 * Dependencies for blocks/program-stat/index.js, the editor's preview.
 *
 * Hand-maintained because the theme has no build step; without it WordPress registers the editor
 * script with no dependencies, and `wp.blocks` is undefined by the time it runs.
 *
 * @package WPCredits_Theme
 */

return array(
	'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-server-side-render' ),
	'version'      => WPCREDITS_VERSION,
);
```

Create `blocks/program-stat/index.js`:

```js
/**
 * Editor registration for the program figure: a preview of what the server renders, and no settings.
 *
 * Hand-written against the `wp.*` globals rather than built from JSX, so the theme has no build step.
 * WordPress 7.1 shows a block that has no editor script as one the site "doesn't include support
 * for", so the block registers itself here and lets the server draw it, as the program index and the
 * pathway do. Unlike theirs, this block has attributes: each instance hands the server its own, so
 * the hero's four figures and the homepage's mentors card each preview their own stat.
 */
( function ( blocks, element, blockEditor, ServerSideRender ) {
	'use strict';

	var el = element.createElement;

	blocks.registerBlockType( 'wpcredits/program-stat', {
		edit: function ( props ) {
			return el( 'div', blockEditor.useBlockProps(), el( ServerSideRender, { block: 'wpcredits/program-stat', attributes: props.attributes } ) );
		},

		// Server-rendered: nothing is stored in post content.
		save: function () {
			return null;
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.serverSideRender ) );
```

In `blocks/program-stat/render.php`, replace:

```php
 * Cached for an hour: the number only moves when a sync runs, and the landing page is the most
 * requested page on the site.
 *
 * @package WPCredits_Theme
 *
 * @var array    $attributes Block attributes.
```
with:

```php
 * Cached for an hour: the number only moves when a sync runs, and the landing page is the most
 * requested page on the site.
 *
 * The block also shows the program's three outcome figures and their source line, for the Education
 * Hub's hero (theme 1.24.15): `graduation`, `recommend`, `keep` and `outcomes-source`, read from the
 * plugin through `wpcredits_outcome_figures()` and drawn by `wpcredits_outcome_stat_html()`.
 *
 * @package WPCredits_Theme
 *
 * @var array    $attributes Block attributes.
```

In `blocks/program-stat/render.php`, replace:

```php
$wpcredits_fallback = isset( $attributes['fallback'] ) ? trim( (string) $attributes['fallback'] ) : '';
$wpcredits_count    = null;

if ( isset( $wpcredits_roles[ $wpcredits_stat ] ) && wpcredits_plugin_active() ) {
	$wpcredits_count = wpcredits_program_count( $wpcredits_roles[ $wpcredits_stat ] );
}
```
with:

```php
$wpcredits_fallback = isset( $attributes['fallback'] ) ? trim( (string) $attributes['fallback'] ) : '';
$wpcredits_count    = null;

// The program's outcome figures and their source line (theme 1.24.15): counted by the plugin with the
// public impact dashboard's definitions and never typed in, so `fallback` is not read for them, and a
// figure that may not be shown prints nothing at all, its label and its base with it. Every other
// stat is a count, drawn below as it always was.
$wpcredits_outcome = wpcredits_outcome_stat_html( $wpcredits_stat, isset( $attributes['label'] ) ? (string) $attributes['label'] : '' ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- WordPress hands a block's render file its attributes.

if ( null !== $wpcredits_outcome ) {
	echo $wpcredits_outcome; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built.

	return;
}

if ( isset( $wpcredits_roles[ $wpcredits_stat ] ) && wpcredits_plugin_active() ) {
	$wpcredits_count = wpcredits_program_count( $wpcredits_roles[ $wpcredits_stat ] );
}
```

In `inc/template-tags.php`, replace:

```php

	return $count;
}
```
with:

```php

	return $count;
}

/**
 * The program's three outcome figures, as the plugin may show them (plugin 1.122.24 and later).
 *
 * Read from `WPCPM_Outcome_Figures::published()`, guarded on the class and the method, so a theme on
 * an older plugin, or with none, shows no figure. What the plugin hands over is checked as well: a
 * figure that is not whole numbers that make sense together (a share from 0 to 100, a count above
 * zero and no more than its n) is left out, so nothing but a figure the plugin stored is ever drawn.
 *
 * @return array Empty when no figure may be shown; else `read`, the Unix time the figures were read,
 *               and `figures`, `graduation`, `recommend` and `keep` in that order, those that may be
 *               shown, each `array( 'share' => int, 'count' => int, 'n' => int )`.
 */
function wpcredits_outcome_figures() {
	if ( ! class_exists( 'WPCPM_Outcome_Figures' ) || ! method_exists( 'WPCPM_Outcome_Figures', 'published' ) ) {
		return array();
	}

	$set = WPCPM_Outcome_Figures::published();

	if ( ! is_array( $set ) || ! isset( $set['read'], $set['figures'] ) || ! is_int( $set['read'] ) || $set['read'] <= 0 || ! is_array( $set['figures'] ) ) {
		return array();
	}

	$figures = array();

	foreach ( array( 'graduation', 'recommend', 'keep' ) as $key ) {
		$figure = isset( $set['figures'][ $key ] ) && is_array( $set['figures'][ $key ] ) ? $set['figures'][ $key ] : array();

		if ( ! isset( $figure['share'], $figure['count'], $figure['n'] ) || ! is_int( $figure['share'] ) || ! is_int( $figure['count'] ) || ! is_int( $figure['n'] ) ) {
			continue;
		}

		if ( $figure['share'] < 0 || $figure['share'] > 100 || $figure['count'] < 1 || $figure['count'] > $figure['n'] ) {
			continue;
		}

		$figures[ $key ] = array(
			'share' => $figure['share'],
			'count' => $figure['count'],
			'n'     => $figure['n'],
		);
	}

	if ( array() === $figures ) {
		return array();
	}

	return array(
		'read'    => $set['read'],
		'figures' => $figures,
	);
}

/**
 * An outcome figure, or the figures' source line, as the program figure block prints it.
 *
 * A figure is its number, its label and its base, all inside the block, so a figure that may not be
 * shown takes its words with it; the label is the block's own when one is typed into it. The source
 * line gives the date the figures were read, in the site's date format and time zone, and names the
 * public impact dashboard whose definitions they share.
 *
 * @param string $stat  The block's stat: `graduation`, `recommend`, `keep` or `outcomes-source`.
 * @param string $label The block's label, '' for the figure's own.
 * @return string|null Escaped markup, '' when there is nothing to show, or null for a stat that is not
 *                     an outcome figure.
 */
function wpcredits_outcome_stat_html( $stat, $label = '' ) {
	$labels = array(
		'graduation' => __( 'of WordPress Credits students graduate rather than drop out', 'wpcredits-theme' ),
		'recommend'  => __( 'of graduates would recommend the program', 'wpcredits-theme' ),
		'keep'       => __( 'of graduates plan to keep contributing to WordPress', 'wpcredits-theme' ),
	);

	if ( 'outcomes-source' !== $stat && ! isset( $labels[ $stat ] ) ) {
		return null;
	}

	$set = wpcredits_outcome_figures();

	if ( 'outcomes-source' === $stat ) {
		if ( array() === $set ) {
			return '';
		}

		return sprintf(
			'<p %1$s>%2$s</p>',
			get_block_wrapper_attributes( array( 'class' => 'wpc-figure-source' ) ),
			wp_kses(
				sprintf(
					/* translators: 1: the date the figures were read, 2: the public impact dashboard's address. */
					__( 'As of %1$s, counted by this site from the program\'s records with the same definitions as the <a href="%2$s">WordPress Credits impact dashboard</a>.', 'wpcredits-theme' ),
					esc_html( wp_date( (string) get_option( 'date_format' ), $set['read'] ) ),
					esc_url( 'https://wordpress.github.io/WPCredits/' )
				),
				array( 'a' => array( 'href' => true ) )
			)
		);
	}

	if ( ! isset( $set['figures'][ $stat ] ) ) {
		return '';
	}

	$figure = $set['figures'][ $stat ];
	$label  = trim( (string) $label );

	if ( 'graduation' === $stat ) {
		/* translators: 1: students who graduated, 2: students who graduated or dropped out. */
		$base = sprintf( _n( '%1$s of %2$s student who graduated or dropped out', '%1$s of %2$s students who graduated or dropped out', $figure['n'], 'wpcredits-theme' ), number_format_i18n( $figure['count'] ), number_format_i18n( $figure['n'] ) );
	} else {
		/* translators: %s: how many answered the question on the final survey. */
		$base = sprintf( _n( '%s answer to the final survey', '%s answers to the final survey', $figure['n'], 'wpcredits-theme' ), number_format_i18n( $figure['n'] ) );
	}

	return sprintf(
		'<div %1$s><p class="wpc-figure__value">%2$s</p><p class="wpc-figure__label">%3$s</p><p class="wpc-figure__base">%4$s</p></div>',
		get_block_wrapper_attributes( array( 'class' => 'wpc-figure' ) ),
		/* translators: %s: a share, a whole number from 0 to 100. */
		esc_html( sprintf( _x( '%s%%', 'a share, as a percentage', 'wpcredits-theme' ), number_format_i18n( $figure['share'] ) ) ),
		esc_html( '' !== $label ? $label : $labels[ $stat ] ),
		esc_html( $base )
	);
}
```

- [ ] **Step 4: Run the theme's checks**

```bash
php bin/test-hub.php | tail -1
php bin/test-hub-js.php | tail -1
php bin/check-selectors.php "${WPCPM_PLUGIN:-$HOME/GitHub/wpcredits-program-manager}" | tail -5
"$(command -v phpcs || echo "$HOME/.composer/vendor/bin/phpcs")" --no-colors --report=summary | grep TOTAL
```

Expected:

```text
ALL PASS (78 checks)
ALL PASS (23 scenarios)
355 classes dressed across 8 stylesheets: 15 built at run time, 1 kept for an older plugin, 0 printed by nothing.
42 hub and figure classes dressed, 0 printed by nothing.
55 tokens read, 56 declared, 0 undefined.
0 shared table selectors without a dashboard body class.
No em or en dashes in the theme.
A TOTAL OF 0 ERRORS AND 6 WARNINGS WERE FOUND IN 3 FILES
```

- [ ] **Step 5: Commit**

```bash
git add blocks/program-stat inc/template-tags.php assets/css/hub.css bin/stubs/hub-wp.php bin/test-hub.php
git commit -q -m "Education Hub: the program figure block shows the program's three outcome figures and their source line from plugin 1.122.24, or nothing, a label typed into it replacing a figure's own, and previews each instance in the Site Editor; the four counts as before"
```

---

### Task 6: The patterns, the page's look, and the field table's scroll box

The five patterns the template names, core blocks around the three theme blocks: the hero, the doors, the programs with the field-of-study table, the pathway, and the closing band. `inc/hub.php` makes the table's scroll box a keyboard-reachable region; hub.css gives the sections their spacing, stacking and look.

**Files:**
- Create: `patterns/hub-hero.php`, `patterns/hub-doors.php`, `patterns/hub-programs.php`, `patterns/hub-pathway.php`, `patterns/hub-cta.php`
- Modify: `inc/hub.php` (`wpcredits_hub_fields_region()` on `render_block_core/table`), `assets/css/hub.css` (the anchors, the stacking, the heads and ledes, the hero, the doors, the table, the band)
- Modify: `bin/stubs/hub-wp.php` (`WP_HTML_Tag_Processor`, `wpcredits_test_page()`), `bin/test-hub.php`, `bin/test-hub-js.php` (the page is the template's), `bin/js/hub-harness.js`

**Interfaces:**
- Consumes: Tasks 2 to 5: the three blocks, `wpcredits_hub_url()`, the figures' markup; the theme's `wpc-pill`, `wpc-hero__title`, `wpc-hero__actions`, `is-style-eyebrow`, `is-style-card`, `wpc-button--large`, `is-style-outline`.
- Produces:
  - The patterns `wpcredits/hub-hero`, `wpcredits/hub-doors`, `wpcredits/hub-programs` (`#programs`, the index, `h3#wpc-hub-fit`, `figure.wp-block-table.wpc-hub-fields`), `wpcredits/hub-pathway` (`#pathway`, the pathway), `wpcredits/hub-cta`, each in the `wpcredits` category.
  - `wpcredits_hub_fields_region( string $content ): string`: the `figure` of a table carrying `wpc-hub-fields` gains `tabindex="0" role="region" aria-labelledby="wpc-hub-fit"`.
  - In the stand-ins: `wpcredits_test_page()`, the page as the template draws it.

- [ ] **Step 1: Write the checks and the scenario**

In `bin/js/hub-harness.js`, replace:

```js
	return [ stepsPressed( where ), where.window.location.hash ];
} );

/* ---- the page: both blocks ---------------------------------------------------- */

scenario( 'on the page, a step\'s link to a program the filter hides presses All and lands on the card', [ [ 'true', 'false', 'false', 'false' ], true, [ 'program-wordpress-credits' ] ], () => {
	const where = open( 'page', [ 'index', 'pathway' ] );
```
with:

```js
	return [ stepsPressed( where ), where.window.location.hash ];
} );

/* ---- the page: the template's five patterns and both blocks ------------------------ */

scenario( 'on the page, the hero\'s two buttons and the students\' door jump to their sections', [ 'programs', 'pathway', 'programs' ], () => {
	const where = open( 'page', [ 'index', 'pathway' ] );

	where.document.querySelector( '.wpc-hub-hero a[href="#programs"]' ).click();
	where.document.querySelector( '.wpc-hub-hero a[href="#pathway"]' ).click();
	where.document.querySelector( '.wpc-hub-door__go a[href="#programs"]' ).click();

	return where.document.scrolledTo;
} );

scenario( 'on the page, a step\'s link to a program the filter hides presses All and lands on the card', [ [ 'true', 'false', 'false', 'false' ], true, [ 'program-wordpress-credits' ] ], () => {
	const where = open( 'page', [ 'index', 'pathway' ] );
```

In `bin/stubs/hub-wp.php`, replace:

```php
	public function get_total() { return isset( $GLOBALS['role_count'][ $this->role ] ) ? (int) $GLOBALS['role_count'][ $this->role ] : 0; }
}

/* ---- rendering ------------------------------------------------------------------- */

/**
```
with:

```php
	public function get_total() { return isset( $GLOBALS['role_count'][ $this->role ] ) ? (int) $GLOBALS['role_count'][ $this->role ] : 0; }
}

/* ---- the HTML API, as far as the hub's table filter reaches ------------------------ */

/**
 * Core's tag processor, for the one walk the hub makes: to the first tag of a name carrying a class,
 * then attributes set on it. Attribute values are written as core writes them, double-quoted and
 * escaped; an attribute already on the tag is replaced.
 */
class WP_HTML_Tag_Processor {
	private $html;
	private $at  = null;
	private $len = 0;

	public function __construct( $html ) { $this->html = (string) $html; }

	public function next_tag( $query = array() ) {
		$name  = isset( $query['tag_name'] ) ? strtolower( $query['tag_name'] ) : '[a-z][a-z0-9]*';
		$class = isset( $query['class_name'] ) ? $query['class_name'] : '';

		if ( ! preg_match_all( '/<(' . $name . ')\b[^>]*>/i', $this->html, $tags, PREG_OFFSET_CAPTURE ) ) {
			return false;
		}

		foreach ( $tags[0] as $tag ) {
			$classes = preg_match( '/\sclass="([^"]*)"/i', $tag[0], $found ) ? preg_split( '/\s+/', trim( $found[1] ) ) : array();

			if ( '' === $class || in_array( $class, $classes, true ) ) {
				$this->at  = $tag[1];
				$this->len = strlen( $tag[0] );

				return true;
			}
		}

		return false;
	}

	public function set_attribute( $name, $value ) {
		$tag  = substr( $this->html, $this->at, $this->len );
		$bare = preg_replace( '/\s' . preg_quote( $name, '/' ) . '="[^"]*"/i', '', $tag );
		$new  = substr( $bare, 0, -1 ) . ' ' . $name . '="' . htmlspecialchars( (string) $value, ENT_QUOTES ) . '">';

		$this->html = substr_replace( $this->html, $new, $this->at, $this->len );
		$this->len  = strlen( $new );

		return true;
	}

	public function get_updated_html() { return $this->html; }
}

/* ---- rendering ------------------------------------------------------------------- */

/**
```

In `bin/stubs/hub-wp.php`, replace:

```php
	return $html;
}

/**
 * A pattern file's output, as WordPress reads a theme pattern's content.
 *
```
with:

```php
	return $html;
}

/**
 * The hub page, as WordPress draws it from the template: each pattern's content in the template's
 * order, each dynamic block in it rendered by its render.php, and each table block handed to the
 * filters core runs on a rendered table. The template parts of the chrome are left out.
 *
 * @return string
 */
function wpcredits_test_page() {
	$template = (string) file_get_contents( WPCREDITS_THEME_DIR . 'templates/page-education-hub.html' );

	preg_match_all( '#<!-- wp:pattern \{"slug":"wpcredits/([a-z-]+)"\} /-->#', $template, $slugs );

	$page = '';

	foreach ( $slugs[1] as $slug ) {
		$page .= wpcredits_test_pattern( $slug . '.php' );
	}

	$page = preg_replace_callback(
		'#<!-- wp:(wpcredits/[a-z-]+)( (\{[^}]*\}))? /-->#',
		static function ( $block ) {
			return wpcredits_test_block( $block[1], isset( $block[3] ) ? (array) json_decode( $block[3], true ) : array() );
		},
		$page
	);

	return preg_replace_callback(
		'#(<!-- wp:table [^>]*-->)(.*?)(<!-- /wp:table -->)#s',
		static function ( $table ) {
			return $table[1] . apply_filters( 'render_block_core/table', trim( $table[2] ), array( 'blockName' => 'core/table' ) ) . $table[3];
		},
		$page
	);
}

/**
 * A pattern file's output, as WordPress reads a theme pattern's content.
 *
```

In `bin/test-hub-js.php`, replace:

```php
require_once __DIR__ . '/stubs/hub-wp.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub-content.php';

$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

```
with:

```php
require_once __DIR__ . '/stubs/hub-wp.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub-content.php';
require_once WPCREDITS_THEME_DIR . 'inc/template-tags.php';

$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

```

In `bin/test-hub-js.php`, replace:

```php
}

// The pages the scenarios open: each block on its own, with links of the kind the rest of the page
// holds pointing at it, and the two together as the hub page holds them.
$pages = array(
	'index'   => '<main>' . wpcredits_test_block( 'wpcredits/program-index' ) . '<nav><a href="#program-wordpress-credits">Start WordPress Credits</a> <a href="#program-nothing">Nowhere</a></nav></main>',
	'pathway' => '<main>' . wpcredits_test_block( 'wpcredits/pathway' ) . '<nav><a href="#wpc-hub-step-students-6">Community Leader</a></nav></main>',
	'page'    => '<main>' . wpcredits_test_block( 'wpcredits/program-index' ) . wpcredits_test_block( 'wpcredits/pathway' ) . '</main>',
);

$pipes   = array();
```
with:

```php
}

// The pages the scenarios open: each block on its own, with links of the kind the rest of the page
// holds pointing at it, and the whole hub page, as the template draws it.
$pages = array(
	'index'   => '<main>' . wpcredits_test_block( 'wpcredits/program-index' ) . '<nav><a href="#program-wordpress-credits">Start WordPress Credits</a> <a href="#program-nothing">Nowhere</a></nav></main>',
	'pathway' => '<main>' . wpcredits_test_block( 'wpcredits/pathway' ) . '<nav><a href="#wpc-hub-step-students-6">Community Leader</a></nav></main>',
	'page'    => '<main>' . wpcredits_test_page() . '</main>',
);

$pipes   = array();
```

In `bin/test-hub.php`, replace:

```php
	array( rules_for( '.wpc-figure__value' ) ),
	array( array( 'color: var( --wpc-brand ); font-size: 44px; font-variant-numeric: tabular-nums; font-weight: 700; letter-spacing: -.02em; line-height: 1.14;', 'font-size: 34px;' ) ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
```
with:

```php
	array( rules_for( '.wpc-figure__value' ) ),
	array( array( 'color: var( --wpc-brand ); font-size: 44px; font-variant-numeric: tabular-nums; font-weight: 700; letter-spacing: -.02em; line-height: 1.14;', 'font-size: 34px;' ) ) );

echo "\n=== The patterns ===\n";

$pattern_files = array( 'hub-hero.php', 'hub-doors.php', 'hub-programs.php', 'hub-pathway.php', 'hub-cta.php' );

/**
 * A pattern file's header, as WordPress reads it.
 *
 * @param string $file The file in patterns/.
 * @return array<string, string>
 */
function pattern_header( $file ) {
	$header = array();

	preg_match_all( '/^ \* (Title|Slug|Categories): (.+)$/m', theme_file( 'patterns/' . $file ), $found, PREG_SET_ORDER );

	foreach ( $found as $field ) {
		$header[ $field[1] ] = $field[2];
	}

	return $header;
}

ck( 'five patterns, each in the theme\'s own category, with the slug the template names',
	array_map( 'pattern_header', $pattern_files ),
	array(
		array( 'Title' => 'Education Hub hero', 'Slug' => 'wpcredits/hub-hero', 'Categories' => 'wpcredits, featured' ),
		array( 'Title' => 'Education Hub doors', 'Slug' => 'wpcredits/hub-doors', 'Categories' => 'wpcredits' ),
		array( 'Title' => 'Education Hub programs', 'Slug' => 'wpcredits/hub-programs', 'Categories' => 'wpcredits' ),
		array( 'Title' => 'Education Hub pathway', 'Slug' => 'wpcredits/hub-pathway', 'Categories' => 'wpcredits' ),
		array( 'Title' => 'Education Hub closing band', 'Slug' => 'wpcredits/hub-cta', 'Categories' => 'wpcredits' ),
	) );

/**
 * What is wrong with a pattern's block grammar: a block comment whose attributes are not JSON, a
 * closer that does not close the block open, a block left open, or a block the hub does not use.
 *
 * @param string $html The pattern's output.
 * @return string[]
 */
function grammar_of( $html ) {
	$known = array( 'group', 'columns', 'column', 'paragraph', 'heading', 'buttons', 'button', 'table', 'wpcredits/program-stat', 'wpcredits/program-index', 'wpcredits/pathway' );
	$open  = array();
	$wrong = array();

	preg_match_all( '#<!-- (/?)wp:([a-z0-9/-]+)(?: (\{.*?\}))? (/?)-->#', $html, $comments, PREG_SET_ORDER );

	foreach ( $comments as $comment ) {
		if ( ! in_array( $comment[2], $known, true ) ) {
			$wrong[] = 'unknown ' . $comment[2];
		}

		if ( isset( $comment[3] ) && '' !== $comment[3] && null === json_decode( $comment[3] ) ) {
			$wrong[] = 'not JSON ' . $comment[3];
		}

		if ( '/' === $comment[1] ) {
			if ( array_pop( $open ) !== $comment[2] ) {
				$wrong[] = 'closes ' . $comment[2];
			}
		} elseif ( '/' !== $comment[4] ) {
			$open[] = $comment[2];
		}
	}

	foreach ( $open as $left ) {
		$wrong[] = 'open ' . $left;
	}

	return $wrong;
}

ck( 'every pattern is core blocks and the hub\'s three, its comments JSON, every block closed in order',
	array_map( static function ( $file ) { return grammar_of( wpcredits_test_pattern( $file ) ); }, $pattern_files ), array_fill( 0, 5, array() ) );

ck( 'every section is wide, padded 48px above and below, the hero 56px and 48px',
	array_map(
		static function ( $file ) {
			$xpath = dom_of( wpcredits_test_pattern( $file ) );

			return implode( ' | ', array( found( $xpath, '/html/body/div', 'class' )[0], found( $xpath, '/html/body/div | /html/body/div/div[1]', 'style' )[ 'hub-hero.php' === $file ? 1 : 0 ] ) );
		},
		$pattern_files
	),
	array(
		'wp-block-group alignwide wpc-hub-hero | padding-top:56px;padding-bottom:48px',
		'wp-block-group alignwide wpc-hub-doors | padding-top:48px;padding-bottom:48px',
		'wp-block-group alignwide wpc-hub-anchored | padding-top:48px;padding-bottom:48px',
		'wp-block-group alignwide wpc-hub-anchored | padding-top:48px;padding-bottom:48px',
		'wp-block-group alignwide wpc-hub-cta | padding-top:48px;padding-bottom:48px',
	) );

$hero = wpcredits_test_pattern( 'hub-hero.php' );

ck( 'the hero: its title in two strings, the second in brand blue, and four figure blocks, three figures and their source',
	array( false !== strpos( $hero, '<h1 class="wp-block-heading wpc-hero__title has-display-font-size" style="margin-top:0;margin-bottom:18px">Start learning with WordPress. <em>Every step counts. Keep climbing.</em></h1>' ), preg_match_all( '#<!-- wp:wpcredits/program-stat \{"stat":"([a-z-]+)"\} /-->#', $hero, $stats ), $stats[1] ),
	array( true, 4, array( 'graduation', 'recommend', 'keep', 'outcomes-source' ) ) );

echo "\n=== The page ===\n";

WPCPM_Outcome_Figures::$published = $monday;
$page_html                        = wpcredits_test_page();
$page                             = dom_of( $page_html );

ck( 'without JavaScript the page holds every part: the three figures and their source, the doors, six programs, the field-of-study table, both ladders with all eleven details, and the band',
	array( count( $page->query( '//div[contains(@class,"wpc-figure")]' ) ), count( $page->query( '//p[contains(@class,"wpc-figure-source")]' ) ), count( $page->query( '//div[contains(concat(" ", @class, " ")," wpc-hub-door ")]' ) ), count( $page->query( '//article[@class="wpc-hub-program"]' ) ), count( $page->query( '//figure[contains(@class,"wpc-hub-fields")]//tbody/tr' ) ), count( $page->query( '//section[@class="wpc-hub-ladder"]' ) ), count( $page->query( '//article[@class="wpc-hub-detail"]' ) ), count( $page->query( '//div[contains(@class,"wpc-hub-cta__card")]' ) ) ),
	array( 3, 1, 3, 6, 7, 2, 11, 2 ) );

ck( 'and nothing on it is hidden but the three controls the scripts show: the chips, the switch and the hint',
	found( $page, '//*[@hidden]', 'class' ), array( 'wpc-hub-chips', 'wpc-hub-switch', 'wpc-hub-ladder__hint' ) );

ck( 'the headings in order: the one h1, a heading for every section, the doors\' for screen readers',
	found( $page, '//h1 | //h2' ),
	array( 'Start learning with WordPress. Every step counts. Keep climbing.', 'Who this is for', 'Every education program, in one list', 'Seven steps from first workshop to first job', 'Four partner levels, measured on student outcomes', 'Looking for WordPress talent?', 'Help more students reach the next step' ) );

/**
 * Every link on the page, in order: its words, then where it goes.
 *
 * @param DOMXPath $xpath The page.
 * @return string[]
 */
function links_on( DOMXPath $xpath ) {
	$said = array();

	foreach ( $xpath->query( '//a[@href]' ) as $link ) {
		$said[] = trim( preg_replace( '/\s+/u', ' ', $link->textContent ) ) . ' > ' . $link->getAttribute( 'href' );
	}

	return $said;
}

$apply   = 'https://wordpresseducation.org/apply/';
$sponsor = 'https://wordpresseducation.org/sponsor-application/';

ck( 'every link of the design note\'s 6.9, in the page\'s order, the site\'s own pages read from the plugin, none opening a new tab',
	array( links_on( $page ), count( $page->query( '//a[@target]' ) ) ),
	array(
		array(
			'Find where to start > #programs',
			'Explore the pathway > #pathway',
			'WordPress Credits impact dashboard > https://wordpress.github.io/WPCredits/',
			'Browse programs → > #programs',
			'Become a partner → > ' . $apply,
			'Hire from WordPress → > https://jobs.wordpress.net/',
			'Find or host an event → > https://events.wordpress.org/campusconnect/',
			'Developer track → > https://learn.wordpress.org/course/wordpress-credits-developer-track/',
			'Designer track → > https://learn.wordpress.org/course/wordpress-credits-designer-track/',
			'Impact dashboard → > https://wordpress.github.io/WPCredits/',
			'Facilitation guides → > https://make.wordpress.org/community/handbook/education/wordpress-facilitator-training-program/',
			'Courses → > https://learn.wordpress.org/',
			'Browse courses → > https://learn.wordpress.org/',
			'Learn more → > https://ai-leaders.org/',
			'Learn more → > https://wordpresscertifications.com/wordpress-builder-certification/',
			'Step 1 Attendee > #wpc-hub-step-students-1',
			'Step 2 Builder > #wpc-hub-step-students-2',
			'Step 3 Contributor > #wpc-hub-step-students-3',
			'Step 4 Graduate > #wpc-hub-step-students-4',
			'Step 5 Specialist > #wpc-hub-step-students-5',
			'Step 6 Community Leader > #wpc-hub-step-students-6',
			'Step 7 Credentialed > #wpc-hub-step-students-7',
			'Find a Campus Connect event > https://events.wordpress.org/campusconnect/',
			'Learn to build a site > https://learn.wordpress.org/',
			'Start WordPress Credits > #program-wordpress-credits',
			'See the tracks > #program-wordpress-credits',
			'Explore programs > #programs',
			'Try Facilitator Training > #program-facilitator-training',
			'Where this leads: WordPress Jobs > https://jobs.wordpress.net/',
			'Level 1 Participating > #wpc-hub-step-institutions-1',
			'Level 2 Partner > #wpc-hub-step-institutions-2',
			'Level 3 Established > #wpc-hub-step-institutions-3',
			'Level 4 Flagship > #wpc-hub-step-institutions-4',
			'Request a Campus Connect event > https://events.wordpress.org/campusconnect/organize/',
			'Partner as an institution > ' . $apply,
			'Try Facilitator Training > #program-facilitator-training',
			'Talk to the program team > ' . $apply,
			'Hire from WordPress > https://jobs.wordpress.net/',
			'Sponsor > ' . $sponsor,
			'Partner as an institution > ' . $apply,
		),
		0,
	) );

ck( 'every in-page link lands on something on the page',
	array_values( array_filter( array_map( static function ( $href ) use ( $page ) { return 0 === $page->query( '//*[@id="' . substr( $href, 1 ) . '"]' )->length ? $href : null; }, preg_grep( '/^#/', found( $page, '//a', 'href' ) ) ) ) ),
	array() );

ck( 'and every arrow is decorative, read by no screen reader',
	array( count( $page->query( '//a/span[@aria-hidden="true"]' ) ), substr_count( $page_html, '→' ) ),
	array( 12, 12 ) );

WPCPM_Institution_Application::$url = '';
WPCPM_Sponsor_Application::$url     = '';
$unpublished_page                   = links_on( dom_of( wpcredits_test_page() ) );
WPCPM_Institution_Application::$url = 'https://wordpresseducation.org/apply/';
WPCPM_Sponsor_Application::$url     = 'https://wordpresseducation.org/sponsor-application/';

ck( 'with either application\'s page not published, its five links go to the WordPress Credits handbook',
	array_values( preg_grep( '#community/handbook/education/credits/$#', $unpublished_page ) ),
	array(
		'Become a partner → > https://make.wordpress.org/community/handbook/education/credits/',
		'Partner as an institution > https://make.wordpress.org/community/handbook/education/credits/',
		'Talk to the program team > https://make.wordpress.org/community/handbook/education/credits/',
		'Sponsor > https://make.wordpress.org/community/handbook/education/credits/',
		'Partner as an institution > https://make.wordpress.org/community/handbook/education/credits/',
	) );

echo "\n=== The field-of-study table ===\n";

ck( 'seven fields, three columns, a header section, no fixed layout',
	array( found( $page, '//figure[contains(@class,"wpc-hub-fields")]//th' ), array_chunk( found( $page, '//figure[contains(@class,"wpc-hub-fields")]//tbody/tr/td' ), 3 ), found( $page, '//figure[contains(@class,"wpc-hub-fields")]/table', 'class' ) ),
	array(
		array( 'Field of study', 'Where you\'ll work', 'What you\'ll have to show' ),
		array(
			array( 'Computer science, engineering', 'Core, plugins, testing, accessibility', 'Merged code and reviewed patches' ),
			array( 'Design, arts, UX', 'Design, block patterns, themes', 'Visual work in use on real sites' ),
			array( 'Communications, journalism, marketing', 'Documentation, Learn WordPress, Marketing', 'Published work with a byline' ),
			array( 'Translation, languages', 'Polyglots', 'Published translations' ),
			array( 'Business, events, tourism', 'Community, WordCamps', 'An event you ran, with attendance numbers' ),
			array( 'Education', 'Learn WordPress lesson plans, Training', 'Course material in public use' ),
			array( 'Accessibility, social work', 'Accessibility, testing', 'Audit work on a global platform' ),
		),
		array( '' ),
	) );

ck( 'its box is a region named by the heading above it, with a tab stop, so the scroll box is reached by keyboard',
	array( found( $page, '//figure[contains(@class,"wpc-hub-fields")]', 'tabindex' ), found( $page, '//figure[contains(@class,"wpc-hub-fields")]', 'role' ), found( $page, '//figure[contains(@class,"wpc-hub-fields")]', 'aria-labelledby' ), found( $page, '//*[@id="wpc-hub-fit"]' ) ),
	array( array( '0' ), array( 'region' ), array( 'wpc-hub-fit' ), array( 'Find where you fit in WordPress Credits' ) ) );

ck( 'the filter: a table edited in the Site Editor, its words, its classes and an anchor changed, is still the region; another table is left as it is',
	array(
		apply_filters( 'render_block_core/table', '<figure class="wp-block-table is-style-stripes wpc-hub-fields alignwide" id="fields" tabindex="-1"><table><tbody><tr><td>Law</td></tr></tbody></table></figure>' ),
		apply_filters( 'render_block_core/table', '<figure class="wp-block-table"><table><tbody><tr><td>wpc-hub-fields-like text</td></tr></tbody></table></figure>' ),
		apply_filters( 'render_block_core/table', '<figure class="wp-block-table wpc-hub-fieldset"><table></table></figure>' ),
	),
	array(
		'<figure class="wp-block-table is-style-stripes wpc-hub-fields alignwide" id="fields" tabindex="0" role="region" aria-labelledby="wpc-hub-fit"><table><tbody><tr><td>Law</td></tr></tbody></table></figure>',
		'<figure class="wp-block-table"><table><tbody><tr><td>wpc-hub-fields-like text</td></tr></tbody></table></figure>',
		'<figure class="wp-block-table wpc-hub-fieldset"><table></table></figure>',
	) );

ck( 'below 640px the table keeps its columns and scrolls inside its box',
	(bool) preg_match( '/@media \( max-width: 640px \) \{\s*\.wp-block-table\.wpc-hub-fields table \{\s*min-width: 520px;/', theme_file( 'assets/css/hub.css' ) ), true );

echo "\n=== The words ===\n";

$GLOBALS['marked'] = true;
$marked_page       = wpcredits_test_page();
$GLOBALS['marked'] = false;

ck( 'every word on the page, and every label and announcement, is a translated string',
	unmarked( $marked_page ), array() );

$words = trim( preg_replace( '/\s+/u', ' ', $page->query( '//body' )->item( 0 )->textContent ) );

ck( 'full product names: no "WP Credits", and the Institution Dashboard by its name',
	array( substr_count( $words, 'WP Credits' ), substr_count( strtolower( $words ), 'institution dashboard' ), substr_count( $words, 'Institution Dashboard' ) ),
	array( 0, 1, 1 ) );

ck( 'nothing says what sponsorship costs or what a sponsor receives: the band\'s eyebrow and its button are the only words of sponsors, and the one sum on the page is a program\'s award',
	array( found( $page, '//body//*[not(*)][contains(translate(., "S", "s"), "sponsor")]' ), preg_match_all( '/\$\s?\d[\d,]*/', $words, $sums ), $sums[0] ),
	array( array( 'Sponsors and schools', 'Sponsor' ), 1, array( '$1,000' ) ) );

echo "\n=== The page's look ===\n";

ck( 'with no figure to show, the figures column is not displayed and the copy takes the whole width',
	array( rules_for( '.wpc-hub-hero__cols:not( :has( .wpc-figure ) ) .wpc-hub-hero__figures' ), rules_for( '.wpc-hub-hero__cols:not( :has( .wpc-figure ) ) .wpc-hub-hero__copy' ), rules_for( '.wpc-hub-hero__figures' ) ),
	array( array( 'display: none;' ), array( 'flex-basis: 100% !important;' ), array( 'border-top: 1px solid var( --wpc-ink );' ) ) );

ck( 'the hero, the doors and the band stack below 900px',
	array( rules_for( '.wp-block-columns.wpc-hub-stack' ), rules_for( '.wp-block-columns.wpc-hub-stack > .wp-block-column' ) ),
	array( array( 'flex-wrap: wrap !important;' ), array( 'flex-basis: 100% !important;' ) ) );

ck( 'a door\'s link covers the card, whose border turns brand blue under the pointer and whose outline is the focus ring',
	array( rules_for( '.wpc-hub-door__go a::after' ), rules_for( '.wp-block-group.wpc-hub-door:hover' ), rules_for( '.wp-block-group.wpc-hub-door:has( .wpc-hub-door__go a:focus-visible )' ) ),
	array( array( 'content: ""; inset: 0; position: absolute;' ), array( 'border-color: var( --wpc-brand );' ), array( 'outline: 2px solid var( --wpc-brand ); outline-offset: 2px;' ) ) );

ck( 'the door\'s link gives up its own focus outline for the card\'s ring only where :has() draws it, as a step does; elsewhere the theme\'s own ring stays',
	array_values( preg_grep( '/wpc-hub-door/', under_has_support() ) ),
	array( '.wpc-hub-door__go a:focus-visible { outline: none; }', '.wp-block-group.wpc-hub-door:has( .wpc-hub-door__go a:focus-visible ) { outline: 2px solid var( --wpc-brand ); outline-offset: 2px; }' ) );

preg_match_all( '/@media \( (?:max|min)-width: \d+px \)/', theme_file( 'assets/css/hub.css' ), $breakpoints );
$breakpoints = array_values( array_unique( $breakpoints[0] ) );
sort( $breakpoints );

ck( 'the hub\'s widths: the phone gutter up to 640px, and one breakpoint at 900px, below it stacked and from it side by side, so at a 900px window nothing is both',
	$breakpoints, array( '@media ( max-width: 640px )', '@media ( max-width: 899px )', '@media ( min-width: 900px )' ) );

ck( 'anchored sections land below the sticky header',
	array( rules_for( '.wpc-hub-anchored' ), count( $page->query( '//*[contains(@class,"wpc-hub-anchored")][@id]' ) ) ),
	array( array( 'scroll-margin-top: var( --wpc-landing-offset );' ), 3 ) );

$homepage_files = array(
	'templates/front-page.html' => 'c172f139736a8098a48a68684ff546344856d9a2',
	'patterns/hero.php'         => 'dfbfa1052b95995dcfe4be022f56ea4298aab534',
	'patterns/audiences.php'    => '831a99841c03e31578a3f67a43e3fa05e5c6911b',
	'patterns/steps.php'        => '44559443966fc01246f6f96941cfac6f6001458d',
	'patterns/cta.php'          => '897bc5a00b08bbabcffec41935af3889c44dd539',
);

ck( 'the live homepage\'s template and patterns are as 1.24.14 shipped them, byte for byte',
	array_map( static function ( $file ) { return sha1( theme_file( $file ) ); }, array_combine( array_keys( $homepage_files ), array_keys( $homepage_files ) ) ),
	$homepage_files );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them to see them fail**

```bash
php bin/test-hub.php 2>/dev/null | tail -1
php bin/test-hub-js.php 2>&1 | tail -1
```

Expected:

```text
21 FAILURE(S) (103 checks)
3 FAILURE(S) (24 scenarios)
```

(The patterns do not exist yet: the template draws an empty page, and the three scenarios on the whole page fail. The breakpoint check passes already: it guards the queries this task adds.)

- [ ] **Step 3: Write the patterns, the filter and the page's look**

In `assets/css/hub.css`, replace:

```css
	}
}

/* ==========================================================================
   The toggles: the program index's chips and the pathway's switch
   ========================================================================== */
```
with:

```css
	}
}

/* An anchored section or heading lands below the sticky header. */
.wpc-hub-anchored {
	scroll-margin-top: var( --wpc-landing-offset );
}

/* Two or three columns side by side from 900px, one below: the hero, the doors and the closing band.
   Core stacks columns below 782px; these stack below 900px. */
@media ( max-width: 899px ) {
	.wp-block-columns.wpc-hub-stack {
		flex-wrap: wrap !important;
	}

	.wp-block-columns.wpc-hub-stack > .wp-block-column {
		flex-basis: 100% !important;
	}
}

/* A section's head sits 22px above its content. Body text is 16px and runs to the section's edge.
   Two classes deep, to outrank core's flow-layout margins whatever order the sheets print in. */
.wp-block-group.wpc-hub-head {
	margin-bottom: 22px;
}

.wp-block-group.wpc-hub-head > * {
	margin-bottom: 0;
	margin-top: 0;
}

.wp-block-group.wpc-hub-head > * + * {
	margin-top: 8px;
}

.wpc-hub-lede {
	color: var( --wpc-ink-70 );
	font-size: 16px;
}

/* ==========================================================================
   The hero
   ========================================================================== */

.wpc-hub-hero__cols {
	gap: 44px;
}

/* The figures under a 1px ink rule, a row each. */
.wpc-hub-hero__figures {
	border-top: 1px solid var( --wpc-ink );
}

/* With no figure to show, the figures column is not displayed and the copy takes the whole width. */
.wpc-hub-hero__cols:not( :has( .wpc-figure ) ) .wpc-hub-hero__figures {
	display: none;
}

.wpc-hub-hero__cols:not( :has( .wpc-figure ) ) .wpc-hub-hero__copy {
	flex-basis: 100% !important;
}

@media ( max-width: 899px ) {
	.wpc-hub-hero__cols {
		gap: 28px;
	}
}

/* ==========================================================================
   The doors
   ========================================================================== */

.wpc-hub-doors__grid {
	gap: 16px;
}

/* A card whose link covers it: the border turns brand blue under the pointer, and the focus ring is
   drawn around the whole card. */
.wp-block-group.wpc-hub-door {
	display: flex;
	flex-direction: column;
	gap: 10px;
	height: 100%;
	padding: 24px;
	position: relative;
}

.wp-block-group.wpc-hub-door > * {
	margin: 0;
}

.wp-block-group.wpc-hub-door:hover {
	border-color: var( --wpc-brand );
}

.wpc-hub-door__text {
	color: var( --wpc-ink-60 );
	font-size: 16px;
}

.wp-block-group.wpc-hub-door .wpc-hub-door__go {
	margin-top: auto;
}

.wpc-hub-door__go a {
	color: var( --wpc-brand );
	font-size: 14px;
	font-weight: 600;
	text-decoration: none;
}

.wpc-hub-door__go a::after {
	content: "";
	inset: 0;
	position: absolute;
}

/* Only where :has() draws the ring around the card does the link give up its own; a browser without
   :has() keeps the theme's ring on the link. */
@supports selector( :has( * ) ) {
	.wpc-hub-door__go a:focus-visible {
		outline: none;
	}

	.wp-block-group.wpc-hub-door:has( .wpc-hub-door__go a:focus-visible ) {
		outline: 2px solid var( --wpc-brand );
		outline-offset: 2px;
	}
}

/* ==========================================================================
   The toggles: the program index's chips and the pathway's switch
   ========================================================================== */
```

In `assets/css/hub.css`, replace:

```css
		font-size: 34px;
	}
}
```
with:

```css
		font-size: 34px;
	}
}

/* ==========================================================================
   The field-of-study table
   ========================================================================== */

.wp-block-group.wpc-hub-fit {
	margin-top: 56px;
}

/* A white box, its header cells eyebrows, its body cells 14px ink, the first column bold, the rows
   divided by the rule. Core's Table block makes the figure the scroll container. */
.wp-block-table.wpc-hub-fields {
	background: var( --wpc-surface );
	border: 1px solid var( --wpc-line-card );
	border-radius: var( --wpc-radius-card );
	margin: 0;
}

.wp-block-table.wpc-hub-fields thead {
	border-bottom: 0;
}

.wp-block-table.wpc-hub-fields th,
.wp-block-table.wpc-hub-fields td {
	border: 0;
	border-bottom: 1px solid var( --wpc-line );
	padding: 14px 18px;
	text-align: left;
	vertical-align: top;
}

.wp-block-table.wpc-hub-fields tbody tr:last-child td {
	border-bottom: 0;
}

.wp-block-table.wpc-hub-fields th {
	color: var( --wpc-ink-60 );
	font: 500 12px/18px var( --wpc-font );
	letter-spacing: .06em;
	text-transform: uppercase;
}

.wp-block-table.wpc-hub-fields td {
	color: var( --wpc-ink );
	font-size: 14px;
	line-height: 1.5;
}

.wp-block-table.wpc-hub-fields td:first-child {
	font-weight: 600;
}

/* At 375px the table scrolls inside its box: each column keeps about 170px, the 335px box shows the
   first column and most of the second, and the page itself never widens. */
@media ( max-width: 640px ) {
	.wp-block-table.wpc-hub-fields table {
		min-width: 520px;
	}

	.wp-block-table.wpc-hub-fields th,
	.wp-block-table.wpc-hub-fields td {
		padding: 10px 12px;
	}
}

/* ==========================================================================
   The closing band
   ========================================================================== */

.wpc-hub-cta__grid {
	gap: 16px;
}

.wp-block-group.wpc-hub-cta__card {
	display: flex;
	flex-direction: column;
	gap: 12px;
	height: 100%;
	padding: 28px;
}

.wp-block-group.wpc-hub-cta__card > * {
	margin: 0;
}

.wpc-hub-cta__text {
	color: var( --wpc-ink-60 );
	font-size: 16px;
}
```

In `inc/hub.php`, replace:

```php
<?php
/**
 * The Education Hub homepage: its stylesheet, and the rule that lets the front page be drawn by its
 * template.
 *
 * The hub is a page template of its own, "Education Hub home" (`templates/page-education-hub.html`,
 * registered in theme.json), made of five patterns and two blocks. It prints no post content: the
```
with:

```php
<?php
/**
 * The Education Hub homepage: its stylesheet, the rule that lets the front page be drawn by its
 * template, and the field-of-study table's scroll box.
 *
 * The hub is a page template of its own, "Education Hub home" (`templates/page-education-hub.html`,
 * registered in theme.json), made of five patterns and two blocks. It prints no post content: the
```

In `inc/hub.php`, replace:

```php
	return $templates;
}
add_filter( 'frontpage_template_hierarchy', 'wpcredits_hub_front_page' );
```
with:

```php
	return $templates;
}
add_filter( 'frontpage_template_hierarchy', 'wpcredits_hub_front_page' );

/**
 * Make the field-of-study table's scroll box reachable by keyboard.
 *
 * Below 640px the table is wider than its box and scrolls inside it (core's Table block makes its
 * `<figure>` the scroll container, and hub.css gives the table its minimum width), and a scroll
 * container with nothing focusable in it cannot be reached by keyboard in every browser. So the
 * figure of a table block carrying `wpc-hub-fields`, and of no other, is given a tab stop and the
 * region role, named by the heading above it (`wpc-hub-fit`), and the theme's focus ring. Done on
 * the server, so it holds without JavaScript and after the table's words are edited.
 *
 * @param string $content The table block's markup.
 * @return string
 */
function wpcredits_hub_fields_region( $content ) {
	if ( false === strpos( $content, 'wpc-hub-fields' ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $content;
	}

	$table = new WP_HTML_Tag_Processor( $content );

	if ( ! $table->next_tag(
		array(
			'tag_name'   => 'FIGURE',
			'class_name' => 'wpc-hub-fields',
		)
	) ) {
		return $content;
	}

	$table->set_attribute( 'tabindex', '0' );
	$table->set_attribute( 'role', 'region' );
	$table->set_attribute( 'aria-labelledby', 'wpc-hub-fit' );

	return $table->get_updated_html();
}
add_filter( 'render_block_core/table', 'wpcredits_hub_fields_region' );
```

Create `patterns/hub-cta.php`:

```php
<?php
/**
 * Title: Education Hub closing band
 * Slug: wpcredits/hub-cta
 * Categories: wpcredits
 * Description: Two cards to close the page: companies looking for WordPress talent, and sponsors and schools who want to help.
 * Keywords: hub, call to action, hire, sponsor, partner
 * Viewport Width: 1400
 *
 * The sponsorship and institution applications are the site's own pages, read when the pattern
 * renders.
 *
 * @package WPCredits_Theme
 */

?>
<!-- wp:group {"align":"wide","className":"wpc-hub-cta","style":{"spacing":{"padding":{"top":"48px","bottom":"48px"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide wpc-hub-cta" style="padding-top:48px;padding-bottom:48px"><!-- wp:columns {"className":"wpc-hub-cta__grid wpc-hub-stack"} -->
<div class="wp-block-columns wpc-hub-cta__grid wpc-hub-stack"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className":"is-style-card wpc-hub-cta__card","layout":{"type":"default"}} -->
<div class="wp-block-group is-style-card wpc-hub-cta__card"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php esc_html_e( 'Companies', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"fontSize":"large"} -->
<h2 class="wp-block-heading has-large-font-size"><?php esc_html_e( 'Looking for WordPress talent?', 'wpcredits-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hub-cta__text"} -->
<p class="wpc-hub-cta__text"><?php esc_html_e( 'See what graduates are trained to do and where to find them.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"wpc-button--large"} -->
<div class="wp-block-button wpc-button--large"><a class="wp-block-button__link wp-element-button" href="https://jobs.wordpress.net/"><?php esc_html_e( 'Hire from WordPress', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className":"is-style-card wpc-hub-cta__card","layout":{"type":"default"}} -->
<div class="wp-block-group is-style-card wpc-hub-cta__card"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php esc_html_e( 'Sponsors and schools', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"fontSize":"large"} -->
<h2 class="wp-block-heading has-large-font-size"><?php esc_html_e( 'Help more students reach the next step', 'wpcredits-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hub-cta__text"} -->
<p class="wpc-hub-cta__text"><?php esc_html_e( 'Fund the programs, or bring them to your campus.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline wpc-button--large"} -->
<div class="wp-block-button is-style-outline wpc-button--large"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( wpcredits_hub_url( 'sponsor_application' ) ); ?>"><?php esc_html_e( 'Sponsor', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline wpc-button--large"} -->
<div class="wp-block-button is-style-outline wpc-button--large"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( wpcredits_hub_url( 'institution_application' ) ); ?>"><?php esc_html_e( 'Partner as an institution', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
```

Create `patterns/hub-doors.php`:

```php
<?php
/**
 * Title: Education Hub doors
 * Slug: wpcredits/hub-doors
 * Categories: wpcredits
 * Description: Three cards, one each for students, institutions and companies, each leading to where that reader starts.
 * Keywords: hub, audiences, students, institutions, companies
 * Viewport Width: 1400
 *
 * Each card's link is stretched over the whole card, and the focus ring is drawn around the card
 * (hub.css). The institutions' link is the site's own application, read when the pattern renders.
 *
 * @package WPCredits_Theme
 */

$wpcredits_doors = array(
	array(
		'eyebrow' => __( 'Students', 'wpcredits-theme' ),
		'title'   => __( 'Build a site, make your first contribution, earn credit.', 'wpcredits-theme' ),
		'text'    => __( 'Programs matched to your field of study, from translation and design to engineering.', 'wpcredits-theme' ),
		'link'    => __( 'Browse programs', 'wpcredits-theme' ),
		'href'    => '#programs',
	),
	array(
		'eyebrow' => __( 'Institutions', 'wpcredits-theme' ),
		'title'   => __( 'Bring a free, ready-to-run program to your students.', 'wpcredits-theme' ),
		'text'    => __( 'Start with a one-day workshop or a WordPress Credits pilot. Each new group of students builds a stronger partnership.', 'wpcredits-theme' ),
		'link'    => __( 'Become a partner', 'wpcredits-theme' ),
		'href'    => wpcredits_hub_url( 'institution_application' ),
	),
	array(
		'eyebrow' => __( 'Companies', 'wpcredits-theme' ),
		'title'   => __( 'Hire people with verified WordPress experience.', 'wpcredits-theme' ),
		'text'    => __( 'Browse graduates open to work, or post internships and entry-level roles.', 'wpcredits-theme' ),
		'link'    => __( 'Hire from WordPress', 'wpcredits-theme' ),
		'href'    => 'https://jobs.wordpress.net/',
	),
);

?>
<!-- wp:group {"align":"wide","className":"wpc-hub-doors","style":{"spacing":{"padding":{"top":"48px","bottom":"48px"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide wpc-hub-doors" style="padding-top:48px;padding-bottom:48px"><!-- wp:heading {"className":"screen-reader-text"} -->
<h2 class="wp-block-heading screen-reader-text"><?php esc_html_e( 'Who this is for', 'wpcredits-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:columns {"className":"wpc-hub-doors__grid wpc-hub-stack"} -->
<div class="wp-block-columns wpc-hub-doors__grid wpc-hub-stack">
<?php foreach ( $wpcredits_doors as $wpcredits_door ) : ?>
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className":"is-style-card wpc-hub-door","layout":{"type":"default"}} -->
<div class="wp-block-group is-style-card wpc-hub-door"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php echo esc_html( $wpcredits_door['eyebrow'] ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html( $wpcredits_door['title'] ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hub-door__text"} -->
<p class="wpc-hub-door__text"><?php echo esc_html( $wpcredits_door['text'] ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"wpc-hub-door__go"} -->
<p class="wpc-hub-door__go"><a href="<?php echo esc_url( $wpcredits_door['href'] ); ?>"><?php echo esc_html( $wpcredits_door['link'] ); ?><span aria-hidden="true"> →</span></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->
<?php endforeach; ?>
</div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
```

Create `patterns/hub-hero.php`:

```php
<?php
/**
 * Title: Education Hub hero
 * Slug: wpcredits/hub-hero
 * Categories: wpcredits, featured
 * Description: The Education Hub's opening: what the programs offer, two ways in, and the program's three outcome figures with their source.
 * Keywords: hub, hero, education, figures, outcomes
 * Viewport Width: 1400
 *
 * The figures are program figure blocks, each the plugin's stored figure or nothing: with no figure
 * to show, the figures column is not displayed and the copy takes the whole width (hub.css).
 *
 * @package WPCredits_Theme
 */

?>
<!-- wp:group {"align":"wide","className":"wpc-hub-hero","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide wpc-hub-hero"><!-- wp:columns {"className":"wpc-hub-hero__cols wpc-hub-stack","style":{"spacing":{"padding":{"top":"56px","bottom":"48px"}}}} -->
<div class="wp-block-columns wpc-hub-hero__cols wpc-hub-stack" style="padding-top:56px;padding-bottom:48px"><!-- wp:column {"width":"58%","className":"wpc-hub-hero__copy"} -->
<div class="wp-block-column wpc-hub-hero__copy" style="flex-basis:58%"><!-- wp:paragraph {"className":"wpc-pill","style":{"spacing":{"margin":{"top":"0","bottom":"20px"}}}} -->
<p class="wpc-pill" style="margin-top:0;margin-bottom:20px"><?php esc_html_e( 'Education programs from the WordPress project', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"wpc-hero__title","fontSize":"display","style":{"spacing":{"margin":{"top":"0","bottom":"18px"}}}} -->
<h1 class="wp-block-heading wpc-hero__title has-display-font-size" style="margin-top:0;margin-bottom:18px"><?php esc_html_e( 'Start learning with WordPress.', 'wpcredits-theme' ); ?> <em><?php esc_html_e( 'Every step counts. Keep climbing.', 'wpcredits-theme' ); ?></em></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hub-lede","textColor":"ink-70","style":{"spacing":{"margin":{"top":"0","bottom":"26px"}}}} -->
<p class="wpc-hub-lede has-ink-70-color has-text-color" style="margin-top:0;margin-bottom:26px"><?php esc_html_e( 'Students earn academic credit, credentials, and a portfolio employers can check, and each program leads to the next. Institutions can start with one workshop or a small pilot, and every group of students they run moves them up a partner level with more support.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"wpc-hero__actions","style":{"spacing":{"blockGap":"12px"}}} -->
<div class="wp-block-buttons wpc-hero__actions"><!-- wp:button {"className":"wpc-button--large"} -->
<div class="wp-block-button wpc-button--large"><a class="wp-block-button__link wp-element-button" href="#programs"><?php esc_html_e( 'Find where to start', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline wpc-button--large"} -->
<div class="wp-block-button is-style-outline wpc-button--large"><a class="wp-block-button__link wp-element-button" href="#pathway"><?php esc_html_e( 'Explore the pathway', 'wpcredits-theme' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"42%","className":"wpc-hub-hero__figures"} -->
<div class="wp-block-column wpc-hub-hero__figures" style="flex-basis:42%"><!-- wp:wpcredits/program-stat {"stat":"graduation"} /-->

<!-- wp:wpcredits/program-stat {"stat":"recommend"} /-->

<!-- wp:wpcredits/program-stat {"stat":"keep"} /-->

<!-- wp:wpcredits/program-stat {"stat":"outcomes-source"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
```

Create `patterns/hub-pathway.php`:

```php
<?php
/**
 * Title: Education Hub pathway
 * Slug: wpcredits/hub-pathway
 * Categories: wpcredits
 * Description: The pathway from a first workshop to a first job, and an institution's four partner levels, with a switch between the two.
 * Keywords: hub, pathway, steps, levels
 * Viewport Width: 1400
 *
 * The switch, the staircases and the details are the pathway block's.
 *
 * @package WPCredits_Theme
 */

?>
<!-- wp:group {"anchor":"pathway","align":"wide","className":"wpc-hub-anchored","style":{"spacing":{"padding":{"top":"48px","bottom":"48px"}}},"layout":{"type":"default"}} -->
<div id="pathway" class="wp-block-group alignwide wpc-hub-anchored" style="padding-top:48px;padding-bottom:48px"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php esc_html_e( 'The pathway', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:wpcredits/pathway /--></div>
<!-- /wp:group -->
```

Create `patterns/hub-programs.php`:

```php
<?php
/**
 * Title: Education Hub programs
 * Slug: wpcredits/hub-programs
 * Categories: wpcredits
 * Description: Every education program in one list, filtered by audience, and the fields of study WordPress Credits matches students to.
 * Keywords: hub, programs, education, field of study
 * Viewport Width: 1400
 *
 * The cards are the program index block's; the field-of-study table is a core Table block, so its
 * words are edited in the Site Editor. Below 640px the table scrolls inside its box, and the theme
 * makes that box reachable by keyboard (`wpcredits_hub_fields_region()`).
 *
 * @package WPCredits_Theme
 */

$wpcredits_fields = array(
	array( __( 'Computer science, engineering', 'wpcredits-theme' ), __( 'Core, plugins, testing, accessibility', 'wpcredits-theme' ), __( 'Merged code and reviewed patches', 'wpcredits-theme' ) ),
	array( __( 'Design, arts, UX', 'wpcredits-theme' ), __( 'Design, block patterns, themes', 'wpcredits-theme' ), __( 'Visual work in use on real sites', 'wpcredits-theme' ) ),
	array( __( 'Communications, journalism, marketing', 'wpcredits-theme' ), __( 'Documentation, Learn WordPress, Marketing', 'wpcredits-theme' ), __( 'Published work with a byline', 'wpcredits-theme' ) ),
	array( __( 'Translation, languages', 'wpcredits-theme' ), __( 'Polyglots', 'wpcredits-theme' ), __( 'Published translations', 'wpcredits-theme' ) ),
	array( __( 'Business, events, tourism', 'wpcredits-theme' ), __( 'Community, WordCamps', 'wpcredits-theme' ), __( 'An event you ran, with attendance numbers', 'wpcredits-theme' ) ),
	array( __( 'Education', 'wpcredits-theme' ), __( 'Learn WordPress lesson plans, Training', 'wpcredits-theme' ), __( 'Course material in public use', 'wpcredits-theme' ) ),
	array( __( 'Accessibility, social work', 'wpcredits-theme' ), __( 'Accessibility, testing', 'wpcredits-theme' ), __( 'Audit work on a global platform', 'wpcredits-theme' ) ),
);

?>
<!-- wp:group {"anchor":"programs","align":"wide","className":"wpc-hub-anchored","style":{"spacing":{"padding":{"top":"48px","bottom":"48px"}}},"layout":{"type":"default"}} -->
<div id="programs" class="wp-block-group alignwide wpc-hub-anchored" style="padding-top:48px;padding-bottom:48px"><!-- wp:group {"className":"wpc-hub-head","layout":{"type":"default"}} -->
<div class="wp-block-group wpc-hub-head"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php esc_html_e( 'All programs', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Every education program, in one list', 'wpcredits-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hub-lede"} -->
<p class="wpc-hub-lede"><?php esc_html_e( 'Programs are free unless noted. You don\'t need to take them in order, but each one is designed to lead into the next.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:wpcredits/program-index /-->

<!-- wp:group {"className":"wpc-hub-fit","layout":{"type":"default"}} -->
<div class="wp-block-group wpc-hub-fit"><!-- wp:group {"className":"wpc-hub-head","layout":{"type":"default"}} -->
<div class="wp-block-group wpc-hub-head"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php esc_html_e( 'Matched to your field of study', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3,"anchor":"wpc-hub-fit","className":"wpc-hub-anchored"} -->
<h3 class="wp-block-heading wpc-hub-anchored" id="wpc-hub-fit"><?php esc_html_e( 'Find where you fit in WordPress Credits', 'wpcredits-theme' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hub-lede"} -->
<p class="wpc-hub-lede"><?php esc_html_e( 'You work in the area of WordPress that fits your degree, so a design student shows design work and a CS student shows reviewed code.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:table {"hasFixedLayout":false,"className":"wpc-hub-fields"} -->
<figure class="wp-block-table wpc-hub-fields"><table><thead><tr><th><?php esc_html_e( 'Field of study', 'wpcredits-theme' ); ?></th><th><?php esc_html_e( 'Where you\'ll work', 'wpcredits-theme' ); ?></th><th><?php esc_html_e( 'What you\'ll have to show', 'wpcredits-theme' ); ?></th></tr></thead><tbody>
<?php foreach ( $wpcredits_fields as $wpcredits_field ) : ?>
<tr><td><?php echo esc_html( $wpcredits_field[0] ); ?></td><td><?php echo esc_html( $wpcredits_field[1] ); ?></td><td><?php echo esc_html( $wpcredits_field[2] ); ?></td></tr>
<?php endforeach; ?>
</tbody></table></figure>
<!-- /wp:table --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
```

- [ ] **Step 4: Run the theme's checks**

```bash
php bin/test-hub.php | tail -1
php bin/test-hub-js.php | tail -1
php bin/check-selectors.php "${WPCPM_PLUGIN:-$HOME/GitHub/wpcredits-program-manager}" | tail -5
"$(command -v phpcs || echo "$HOME/.composer/vendor/bin/phpcs")" --no-colors --report=summary | grep TOTAL
```

Expected:

```text
ALL PASS (103 checks)
ALL PASS (24 scenarios)
355 classes dressed across 8 stylesheets: 15 built at run time, 1 kept for an older plugin, 0 printed by nothing.
58 hub and figure classes dressed, 0 printed by nothing.
55 tokens read, 56 declared, 0 undefined.
0 shared table selectors without a dashboard body class.
No em or en dashes in the theme.
A TOTAL OF 0 ERRORS AND 6 WARNINGS WERE FOUND IN 3 FILES
```

- [ ] **Step 5: Commit**

```bash
git add patterns inc/hub.php assets/css/hub.css bin/stubs/hub-wp.php bin/test-hub.php bin/test-hub-js.php bin/js/hub-harness.js
git commit -q -m "Education Hub: the five patterns, hero, doors, programs with the field-of-study table, pathway and closing band, every link where the design note says, the table a keyboard-reachable scroll box on a phone"
```

---

### Task 7: The template's scan, the version, the changelog and the template

**Files:**
- Modify: `bin/test-hub.php` (the template's check), `bin/make-pot.sh` (`--exclude=bin`), `style.css` (`Version:`), `readme.txt` (`Stable tag`, the Education Hub section, the changelog)
- Regenerated: `languages/wpcredits-theme.pot`

**Interfaces:**
- Consumes: everything above, as it is.
- Produces: release 1.24.15.

- [ ] **Step 1: Write the check**

In `bin/test-hub.php`, replace:

```php
	array_map( static function ( $file ) { return sha1( theme_file( $file ) ); }, array_combine( array_keys( $homepage_files ), array_keys( $homepage_files ) ) ),
	$homepage_files );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
```
with:

```php
	array_map( static function ( $file ) { return sha1( theme_file( $file ) ); }, array_combine( array_keys( $homepage_files ), array_keys( $homepage_files ) ) ),
	$homepage_files );

echo "\n=== The translation template ===\n";

$make_pot = theme_file( 'bin/make-pot.sh' );

ck( 'the translation template takes nothing from bin/: the WP-CLI run leaves it out, as the gettext run does',
	array( (bool) preg_match( '#^\s*wp i18n make-pot \. "\$OUT" .*--exclude=bin$#m', $make_pot ), (bool) preg_match( "#^find \. -name '\*\.php' .*-not -path '\./bin/\*'#m", $make_pot ) ),
	array( true, true ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run it to see it fail**

```bash
php bin/test-hub.php 2>/dev/null | grep -E "^FAIL|FAILURE" | cut -c1-140
```

Expected:

```text
FAIL the translation template takes nothing from bin/: the WP-CLI run leaves it out, as the gettext run does
1 FAILURE(S) (104 checks)
```

(`bin/make-pot.sh` names no `--exclude=bin` yet.)

- [ ] **Step 3: The script, the version, the readme and the changelog**

In `bin/make-pot.sh`, replace:

```sh
	exit 1
fi

if command -v wp >/dev/null 2>&1; then
	echo "WP-CLI found - using it, which is the canonical path."
	wp i18n make-pot . "$OUT" --slug="$SLUG" --domain="$SLUG"
	exit 0
fi

```
with:

```sh
	exit 1
fi

# bin/ holds the checks and the suites, none of them shipped or translated: both runs leave it out.
if command -v wp >/dev/null 2>&1; then
	echo "WP-CLI found - using it, which is the canonical path."
	wp i18n make-pot . "$OUT" --slug="$SLUG" --domain="$SLUG" --exclude=bin
	exit 0
fi

```

In `readme.txt`, replace:

```text
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.24.14
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: education, full-site-editing, block-patterns, custom-colors, custom-logo, custom-menu, translation-ready, full-width-template, block-styles
```
with:

```text
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.24.15
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: education, full-site-editing, block-patterns, custom-colors, custom-logo, custom-menu, translation-ready, full-width-template, block-styles
```

In `readme.txt`, replace:

```text
later changes to the pattern, and to anything set as a block attribute rather than in
CSS, stop reaching it. Styling still applies, because that comes from the stylesheet.

= The login screen =

`wp-login.php` is not a template, so the branding is applied through the login
```
with:

```text
later changes to the pattern, and to anything set as a block attribute rather than in
CSS, stop reaching it. Styling still applies, because that comes from the stylesheet.

= The Education Hub homepage =

A page template, "Education Hub home", draws an alternative homepage built from the
program team's hub: a hero with the program's three outcome figures, three doors for
students, institutions and companies, every education program as a card with filter
chips, a field-of-study table, the pathway from a first workshop to a first job beside
an institution's four partner levels, and a closing band. It prints no post content,
so the page it is set on is only its address. Without JavaScript every part of it is
shown and no control is; with it the chips, the switch and the steps show one part at
a time. The figures come from WPCredits Program Manager 1.122.24 or later, and with an
older plugin, or none, the hero shows its copy alone.

The site's homepage is not changed by the template. Set the homepage under Settings >
Reading to a page that uses it, and the theme lets the template draw the front page;
choosing the old homepage again puts it back.

= The login screen =

`wp-login.php` is not a template, so the branding is applied through the login
```

In `readme.txt`, replace:

```text

== Changelog ==

= 1.24.14 =
* Dashboards: the "Viewing as" field that plugin 1.122.15 draws, one box you click to open the names, type into to narrow them and pick from, is dressed as the list was: white background, gray border, 2px corners, 14px type, 230px minimum width, and the WordPress chevron 8px in from its edge with room kept for it, one arrow and not two. It is 34px tall, as **Show** beside it is. **Show**, the labels, the note and the 8px gaps between them are as before. What is typed reads in the page's ink, and the prompt before anything is typed in the theme's muted gray.
* Dashboards: the open list is a white panel with the controls' gray border and 2px corners, the theme's soft shadow and 14px type, 2px under the field and as wide as it. Each name is a 32px row. The highlighted name, the one Enter picks, is on the pale indigo tint with a 2px blue edge on its left; the name under the mouse is light gray; the name being viewed is bold, so it is not told by color alone; and the line that says nothing matches is in the muted gray. In forced-colors mode the highlighted row takes the system's own highlight colors.
```
with:

```text

== Changelog ==

= 1.24.15 =
* Education Hub homepage: a new page template, "Education Hub home", with five patterns: a hero with the program's three outcome figures and their source, three doors for students, institutions and companies, every education program in one list with a field-of-study table, the pathway with the students' seven steps and the institutions' four partner levels, and a closing band. A page set to it shows the hub; the homepage stays as it is until it is set to such a page under Settings > Reading, when the theme lets the template draw the front page.
* Two new blocks, Program index and Pathway: every program as a card with chips that filter them by audience, and a switch between the two ladders with a staircase of steps and each step's detail. Without JavaScript every card, both ladders and all eleven details are shown and no control is; with it each press says what it shows to a screen reader, and focus stays where it was. Every benefit no public page confirms is marked "(planned)".
* The program figure block shows the three outcome figures and their source line, read from WPCredits Program Manager 1.122.24 or later; with an older plugin, or none, it shows neither, and the hero's copy takes the whole width. A new label attribute replaces a figure's own label. In the Site Editor each program figure block now shows what the server draws for its own stat, as the Program index and Pathway blocks do, instead of a placeholder. The four counts, mentors among them, are drawn as before.
* On a phone the field-of-study table scrolls inside its box, which is reached by keyboard; nothing on the page scrolls sideways.
* For developers: `bin/check-selectors.php` also checks that every `.wpc-hub-*` and `.wpc-figure*` selector is printed by the theme. The installable zip built with `git archive` leaves out `bin/` (`.gitattributes`), which keeps the checks and the new suites, `bin/test-hub.php` and `bin/test-hub-js.php`; `bin/make-pot.sh` leaves `bin/` out of the translation template with WP-CLI too, as it already did with gettext alone.

= 1.24.14 =
* Dashboards: the "Viewing as" field that plugin 1.122.15 draws, one box you click to open the names, type into to narrow them and pick from, is dressed as the list was: white background, gray border, 2px corners, 14px type, 230px minimum width, and the WordPress chevron 8px in from its edge with room kept for it, one arrow and not two. It is 34px tall, as **Show** beside it is. **Show**, the labels, the note and the 8px gaps between them are as before. What is typed reads in the page's ink, and the prompt before anything is typed in the theme's muted gray.
* Dashboards: the open list is a white panel with the controls' gray border and 2px corners, the theme's soft shadow and 14px type, 2px under the field and as wide as it. Each name is a 32px row. The highlighted name, the one Enter picks, is on the pale indigo tint with a 2px blue edge on its left; the name under the mouse is light gray; the name being viewed is bold, so it is not told by color alone; and the line that says nothing matches is in the muted gray. In forced-colors mode the highlighted row takes the system's own highlight colors.
```

In `style.css`, replace:

```css
Author: Maciej Pilarski
Author URI: https://example.com
Description: A block theme and the front end for the WPCredits Program Manager plugin. Gives the program a landing page built from patterns, a branded login, and a full-width "Mentor Report Card" template that dresses the plugin's own page as a compact, triaged, printable list. Chrome follows the WordPress Education Initiatives site; inside the dashboard the patterns are wp-admin native.
Version: 1.24.14
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
```
with:

```css
Author: Maciej Pilarski
Author URI: https://example.com
Description: A block theme and the front end for the WPCredits Program Manager plugin. Gives the program a landing page built from patterns, a branded login, and a full-width "Mentor Report Card" template that dresses the plugin's own page as a compact, triaged, printable list. Chrome follows the WordPress Education Initiatives site; inside the dashboard the patterns are wp-admin native.
Version: 1.24.15
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
```

- [ ] **Step 4: Regenerate the template**

```bash
sh bin/make-pot.sh 2>&1 | grep -v Deprecated | tail -1
grep -m1 "Project-Id-Version" languages/wpcredits-theme.pot
grep -c "^msgid" languages/wpcredits-theme.pot
grep -c "^#: bin/" languages/wpcredits-theme.pot
```

Expected:

```text
Success: POT file successfully generated.
"Project-Id-Version: WPCredits 1.24.15\n"
377
0
```

250 strings arrive and one leaves (the program figure block's old description); none comes from `bin/`, which the WP-CLI run now leaves out by its own rule, not because no call there names the theme's domain.

- [ ] **Step 5: Run the theme's checks**

```bash
php bin/test-hub.php | tail -1
php bin/test-hub-js.php | tail -1
php bin/check-selectors.php "${WPCPM_PLUGIN:-$HOME/GitHub/wpcredits-program-manager}" | tail -5
"$(command -v phpcs || echo "$HOME/.composer/vendor/bin/phpcs")" --no-colors --report=summary | grep TOTAL
```

Expected:

```text
ALL PASS (104 checks)
ALL PASS (24 scenarios)
355 classes dressed across 8 stylesheets: 15 built at run time, 1 kept for an older plugin, 0 printed by nothing.
58 hub and figure classes dressed, 0 printed by nothing.
55 tokens read, 56 declared, 0 undefined.
0 shared table selectors without a dashboard body class.
No em or en dashes in the theme.
A TOTAL OF 0 ERRORS AND 6 WARNINGS WERE FOUND IN 3 FILES
```

- [ ] **Step 6: Commit, and list the zip**

```bash
git add bin/test-hub.php bin/make-pot.sh style.css readme.txt languages/wpcredits-theme.pot
git commit -q -m "Education Hub: the readme's section and changelog, and the translation template follows the new strings and leaves bin/ out; 1.24.15"
git archive HEAD | tar -t | grep -c '^bin/'
git archive HEAD | tar -t | grep -cE '^(blocks/(program-index|pathway)/|patterns/hub-|templates/page-education-hub|inc/hub|assets/css/hub)'
```

Expected:

```text
0
23
```

The zip holds the hub's 21 files (the template, the two `inc/` files, hub.css, the five patterns and the two blocks' six files each) and the two blocks' folders, 23 entries, and no `bin/` entry.

---

## Spec coverage

| Spec | Where |
| --- | --- |
| 2 decision 1, 5.2 the template, `customTemplates`, the private preview | Task 1; After the tasks, item 8 |
| 6.1 widths, gutters, spacing, type, anchors | Tasks 1, 6; the gaps measured After the tasks, item 4 |
| 6.2 the hero and its figures column | Tasks 5, 6 |
| 6.3 the doors | Task 6 |
| 6.4 the front-page rule (the switch is the owner's) | Task 1 |
| 6.5 and 7.2 the programs and the program index | Tasks 2, 3, 6 |
| 6.6 the field-of-study table and its scroll box | Task 6 |
| 6.7 and 7.3 the pathway | Tasks 2, 4, 6 |
| 6.8 the closing band | Task 6 |
| 6.9 every link | Tasks 2 to 6 (the page's links in Task 6) |
| 6.10 the wording changed from the draft | Tasks 2, 4, 5, 6 |
| 6.11 the planned marks and the key line | Tasks 2, 3, 4 |
| 7.1 server first, scripts, buttons, toggles, focus, motion, the editor | Tasks 3, 4, 5 (the editor's check above) |
| 7.4 where the texts live | Tasks 2, 6 |
| 8.6 the figure block | Task 5 |
| 9 the theme's rows (no plugin or an older one; no JavaScript) | Tasks 3 to 5 |
| 10.3 the selector check, the translations | Tasks 1, 7 |
| 10.3 render, browser, widths, contrast, figures, screenshots on the local copy | After the tasks, items 1 to 7 |
| The owner's `.gitattributes` | Tasks 1, 7 |

## After the tasks (by the coordinating session, before the owner's yes to deploy)

None of these runs in a suite; each is spec 10.3's, on the local main-line copy (http://localhost:8883) with plugin 1.122.24 and theme 1.24.15 installed and the live site's plugins active, the Gutenberg plugin among them (compare its `wp plugin list --status=active` with the live site's, read-only, first), never on the live site.

1. **The Site Editor,** on that copy with the live site's plugins active: the template is selectable for a page as "Education Hub home"; the Program index, Pathway and Program figure blocks show the server's render (their editor scripts), the hero's four figure blocks each its own figure or source line and post 544's mentors card its count, none a "doesn't include support" placeholder; every core block's text is editable.
2. **Render checks,** server-side with the capture recipe the dashboards use (`wp eval-file`, `wp()`, `template_redirect`, `template_include`), as a signed-in Administrator, on a private page using the template: the six cards with their nine links, the table's seven rows and its `tabindex`, `role` and `aria-labelledby`, both ladders with eleven details and their links, the chips, the switch and the hint with `hidden`, no content with `hidden`, every address of spec 6.9; and the live homepage's markup (the saved template 544) captured before installing 1.24.15 and after, its rendered `main` compared alone, or the whole page once `ver=` query strings and nonces are normalized on both sides as the plugin's deploy probe does (after 1.24.15 the stylesheet links carry `?ver=1.24.15`): identical.
3. **In the browser, JavaScript on and off:** the chip counts (6, 3, 2, 3) and their announcements, `aria-pressed` on each press, focus kept, a pathway link to a hidden card pressing All, step and switch presses, the default steps (3 and 2), a deep link to a level, a keyboard-only run through the page, the focus ring on every control and on the table's scroll box, `prefers-reduced-motion: reduce` emulated; with JavaScript off, everything of the render check readable and no control shown.
4. **At 375px, 640px, 900px and desktop width:** `document.documentElement.scrollWidth` equals the window's width; the table scrolls inside its box at 375px; every gap between consecutive blocks measured and listed (the owner's spacing rule), and any gap off the design note's 48px, 22px, 16px and 56px fixed before the deploy.
5. **Contrast from computed styles:** ink 16.67:1, ink-70 9.54:1 and ink-60 5.45:1 on white (4.95:1 on the band), brand links 5.61:1, white on brand 5.61:1, brand tint on brand 5.03:1, the "by" lines 6.36:1 to 8.55:1 across the steps, green on green tint 4.86:1, amber on amber tint 4.90:1, control borders 3.32:1.
6. **Figures:** the option seeded with invented counts through WP-CLI on the local copy, which refuses Airtable: three figures; one under the minimum sample; a set 30 days old (none shown, the copy at full width); the plugin's class absent (plugin 1.122.23).
7. **Screenshots** at desktop width and at 375px: the hero with and without figures, each chip pressed, both ladders with a step selected, the page without JavaScript, the table's scroll box.
8. **The release** through the release script on the owner's yes (check, build, scan, mirror, deploy, the live version read first; the zip built with `git archive` has no `bin/`), after plugin 1.122.24 and its first `--refresh`; then the preview page (spec 5.3, item 3), on the owner's yes: an Administrator creates "Education Hub (preview)", status Private, template "Education Hub home".
