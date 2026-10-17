# Theme 1.24.20: the site header's menu button below desktop width, Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Below 1200px a signed-in reader's site header is one row, the brand, the account picture and a Menu button, and the button opens a panel under the bar that lists the reader's links in the desktop row's order; signed out, a phone shows the brand and Log in on one row; the desktop row, the header without JavaScript and everything below the header stay as they are.

**Architecture:** A render filter on the header part's outer group (`wpcredits_header_menu()`, in a new `inc/header.php`) gives a signed-in reader's `<header>` the class `wpc-header--menu`, gives the existing `.wpc-nav__center` group (the navigation, Need help? and the account chip) the ID `wpc-header-menu`, and puts a `<button class="wpc-menu-toggle">` right before that group, which becomes the panel. The stylesheet lays the one row and the panel out on screens below 1200px, and only under `.wpc-js`, a mark an inline script puts on the root element first thing in the head, so without JavaScript the wrapped row stays. The account picture, the chip's last element, stays in the markup where it is and is drawn in the bar with `position: fixed` against the header, which `transform: translateX( 0 )` makes its containing block. A small deferred script, `assets/js/header-menu.js`, opens and closes the panel and watches the class `wpcpm-hb-open` the plugin puts on the root element, so focus comes back to the button when the handbook assistant closes. No plugin change.

**Tech Stack:** the WordPress block theme (WordPress 6.6 to 7.1, PHP 7.4 and 8.x), no build step; `WP_HTML_Tag_Processor`; plain JavaScript in the theme's style (an IIFE, `'use strict'`, no dependency), deferred; the theme's suites: `bin/test-header.php` (plain PHP, from this release on the stand-ins of `bin/stubs/hub-wp.php`), a new `bin/test-header-js.php` (node, on the stand-in page of `bin/js/hub-dom.js`), `bin/test-hub.php`, `bin/test-hub-js.php`, `bin/check-selectors.php`, `bin/make-pot.sh` (WP-CLI), phpcs; headless Chrome, the built-in browser, Safari and the iOS Simulator for the trial.

**Spec:** `docs/specs/2026-10-16-theme-header-menu-design.md` in the plugin's repository (plugin main 6203ac79, the design renumbered to 1.24.20 after the Education Hub releases 1.24.18 and 1.24.19), approved on 16 October 2026. Its designer's calls are settled. Read it before any task: every requirement below argues from it.

**Base:** wpcredits-theme main at 41c48f8 (`Merge 1.24.19: ...`), the version live on 17 October 2026. Every anchor below is that tree's text. 1.24.18 and 1.24.19 changed the hub's stylesheet, its patterns and suite, the pathway's render, the bundled fonts (`assets/fonts/`, declared by `@font-face` in `hub.css` alone and applied inside `.wpc-main.wpc-hub` alone), the readme, the template and the version lines; no header rule, header part, `functions.php`, stand-in or harness changed, so the header still renders in the system font on every page, the hub's included. A prototype of this plan applied to 41c48f8 passed every suite (176 hub checks). If main has moved when this plan starts, branch from main as it then is and re-derive each step's expected battery line from the base run (Before Task 1, step 2); take the next free 1.24 number wherever this plan says 1.24.20.

**The owner's answers (17 October 2026):**

1. **320px to 359px, "Accept three lines":** the site title takes three lines there and the closed bar is 82px, where the design expected two lines and "a little over 77px"; accepted as drawn (at 320px about 128px are left for the title beside the logo, the picture and the button, and the designer's rule is never to cut or shrink it).
2. **"Name the logo link":** in this release. The theme's logo fallback, a link around an SVG hidden from screen readers, is the header's first tab stop and has no accessible name; Task 1 names it with the site's name (the choice and why: Where the spec left a choice).
3. **"Smaller phone Log in button":** in this release. The 640px rule (14px, 9px 14px padding, as the owner's mockup draws it) has never taken effect, being one class against the base rule's two; Task 2 makes it apply, and the signed-out phone bar becomes 62px.
4. **VoiceOver on a real phone, "Skip it":** waived. The trial keeps its VoiceOver pass in macOS Safari and its reading of the accessibility tree.
5. **Execution:** subagent-driven.

**Order:** the head mark and the markup the menu needs, with the suite's stand-ins (Task 1); the stylesheet (Task 2); the script, the height script's guard, its suite and the selector check's new lookup (Task 3); the version, the readme, the changelog and the translation template (Task 4); the trial on the Studio copy at localhost:8883, by the controller (Task 5).

**Where the spec left a choice, this plan chooses:**

- **Desktop begins at 1200px:** the menu layout is `@media screen and ( max-width: 1199px )`. Measured while writing this plan: a saved render of an Administrator's page from a local copy, with the theme's `style.css` (its header rules are the same in 1.24.17 and 1.24.19), WordPress 7.1's navigation stylesheet and the page's own inline global and layout styles, in headless Chrome 155 on macOS (the system font stack renders SF Pro, as on Apple devices), in iframes of fixed widths at 10px steps from 1100px to 1260px and then pixel by pixel. An Administrator's row (the student-and-mentor's is the same) is one row from 1141px: 80px of gutters, the brand 342px, 40px, then Homepage 99px, 6px, Need help? 106px, 6px and the chip 462px. Where WordPress's 20px block gap wins over the theme's one-class gaps (`.wpc-brand`, `.wpc-nav__center`), it is one row from 1158px. 1200px clears both, with room for a 17px classic scrollbar (the layout is then 1183px wide), and puts every iPad in landscape below 1200px (1024, 1080, 1133, 1180 and 1194px) in the menu, as decision 2 says. The design's trial widths 1099 and 1100 stay; the trial adds 1199 and 1200. `screen` keeps a printed page on today's wrapped row with every link shown (the dashboards' print sheet hides the header, but other pages print it).
- **The menu is a theme-owned disclosure around the existing links, not the navigation block's overlay.** The links are the same elements in the same order as on the desktop row; the panel is the existing `.wpc-nav__center` group; the button comes right before it in the markup, so Tab goes from the button into the open panel, and the desktop row's tab order is unchanged (the button is not displayed there). In the menu layout the navigation's own toggle is never displayed, whatever the navigation holds; its list and items are blocks, so each item is a row, and a submenu is listed expanded under its parent, indented 12px, its rows as tall as the others. Known limit, left until a submenu is added (today's navigation is one flat item): the parent stays the navigation block's own button, so a screen reader hears it as collapsed although its items are listed under it, in order.
- **The filter, not a new block:** the button, the panel's ID and the header's class are added by `wpcredits_header_menu()` on `render_block_core/group`, for the group whose class list holds the word `wpc-header`, because only the outer group's render sees every entry the guard counts. The live site's header part is the theme's file (the one template the Site Editor has saved there is the homepage's), and the filter keys on the classes, so a saved copy of the part would keep working as long as it keeps them.
- **The guard:** the filter counts the entries the panel would list (elements with the class `wp-block-navigation-item`, `wpc-ask`, `wpc-viewer__link` or `wpc-viewer__logout`); under two, the header is left exactly as it was and keeps today's layout.
- **The logo link is named with the site's name** (the owner's answer 2): `wpcredits_site_logo_fallback()`, which draws the WordPress mark when no logo is uploaded, gives its link `aria-label` the site's name (`get_bloginfo( 'name' )`, its tags stripped), or "Home" (translatable) on a site that has none. Naming it, rather than taking it out of the tab order beside the title link that goes to the same place: the owner asked for a name; it is what core's own logo link carries once a logo is uploaded (the image's alt falls back to the site's name), so the header reads the same either way; and both tab orders stay as the design and today's desktop row have them (desktop: the logo, the title, then the links; the menu layout: the logo, the title, Menu). An uploaded logo's markup is left as core prints it. The function moves to `inc/header.php` with the other header code.
- **The phone's Log in button takes its 640px rule** (the owner's answer 3): that rule's selector list is written `.wpc-header .wpc-viewer__signin, .wpc-header .wpc-viewer--guest a`, two classes as the base rule's, and it stays where it is, in the 640px block after the base rule, the theme's habit for a responsive override (the same depth, later in the sheet). It then draws what it always said, 14px type and 9px 14px padding: a button about 68px by 41px, 12px from the brand and 20px from the window's edge, and a signed-out phone bar of 62px (82px at 320px to 359px, where the title takes three lines). From 641px the button is as today (15px, 11px 22px).
- **The account picture is the chip's own, last in the markup** (the designer's call), drawn in the bar: `position: fixed` against the header, which `transform: translateX( 0 )` makes the containing block of its fixed descendants, `top: 50%` with half its height above, 12px left of the button. The closed panel is `visibility: hidden` (out of the tab order and the accessibility tree, and not hit by a tap), and the picture alone takes `visibility: visible` inside it; every other rule inside the panel that sets visibility says `inherit`. The panel's own overflow does not clip the picture, since the picture's containing block is the header, outside the panel.
- **The bar in the menu layout:** one row, `column-gap: 12px`; the brand keeps 46px clear on its right for the picture (`margin-right: calc( 34px + 12px )`) and may wrap its title, as the phone's rule already lets it; its 12px gap is restated at three classes so WordPress's 20px block gap cannot win in either print order. The bar is 65px on a phone (10px, the 44px button, 10px and the rule) and 77px from 641px (the 76px minimum and the rule); from 320px to 359px it is 82px, the title on three lines (the owner's answer 1). The button keeps the navigation toggle's look (44px square, 1px `--wpc-chrome-rule` border, 4px corners), written as one rule for both buttons, and takes the hover tint while open.
- **The icon:** the theme's `menu` icon while closed and its `close` icon while open, as the owner's mockup draws the open state; the label stays "Menu" (`aria-label`, translatable) and `aria-expanded` says the state.
- **The panel's look, within the mockup's lines:** white, under the bar's 1px rule (`top: calc( 100% + 1px )`), the full width of the bar, the bar's line and the shadow under it (`0 12px 24px rgba( 0, 0, 0, .08 )`, as the theme's existing open panel); 4px above the first row, 8px under the last, the gutter at each side so the rows' text lines up with the brand; each entry a row of 15px type on a 20px line with 12px above and below (44px), a 1px `--wpc-chrome-rule` hairline under every row but Log out, which is last and muted; the page being viewed in the brand at weight 500, as on the desktop row, and underlined in forced colors, where its color is lost. The panel is capped at the window's height under the bar and scrolls inside itself, for a phone held sideways or a longer navigation.
- **The script's behavior:** the button toggles; Escape with the menu open closes it and focuses the button; a press outside the panel and the button closes it and moves no focus, and so does a press on the picture, which is drawn in the bar; "a press" is `pointerdown` as well as `click`, because iOS may deliver no `click` to the document for a tap on plain text or a background; a link followed from the panel closes it, whether or not another script prevented its default, but one followed with Ctrl, Cmd, Shift or Alt held (a new tab or window) leaves it open; Need help? closes it and marks the hand-off; when the observer sees the root's `wpcpm-hb-open` class change from present to absent while the hand-off is marked, it clears the mark and focuses the button (the plugin's own attempt, on the hidden Need help?, comes first and does nothing), and the mark is also cleared each time the menu opens; focus moving from the panel or the button to another element closes the menu, and focus moving to nothing (a press Safari does not focus) does not. The document's `pointerdown`, `click` and `keydown` listeners are registered in the capture phase, so a handler that stops the event (the navigation block binds its own keydown handlers in the panel) cannot keep the menu open. Nothing is done on resize or scroll and nothing is stored: an open menu carried across 1200px is hidden by the stylesheet and shown again under it.
- **The head mark** is `<script>document.documentElement.classList.add( 'wpc-js' );</script>`, printed through `wp_print_inline_script_tag()` on `wp_head` at priority 1, for every reader (it says what the page can do, not who reads it).
- **The menu script** (`wpcredits-header-menu`) is enqueued for signed-in readers only, deferred, from `inc/header.php`. **The trade-off, decided:** the head mark lays the menu out at the first paint, so the bar never jumps (the designer's call), while the deferred script runs after the page is parsed. Until it runs the links sit in the closed panel behind a button that does nothing yet, and if the file never loads (blocked, a failed request) they stay there. The spec's "or before it runs" is met for no JavaScript at all, not for that window. The script is not made a dependency of anything and not moved into the head. The trial's report says so to the owner.
- **The height script's stacked mark** is set only while the group holding the links is in the bar's flow: in the menu layout the group is the absolutely placed panel, and the mark would claim a wrapped row.
- **Signed out, a phone hides the navigation only when it is one link to the home page:** `wpcredits_lone_nav_item()` adds `wpc-nav--home` beside `wpc-nav--lone` when the one item's address, without a trailing slash, is `home_url( '/' )`'s; any other lone item stays shown, since the logo and title replace only a link home. The row's gaps (the bar's 12px and the brand's 12px) are restated with `body:not( .wpc-signed-in )` in front, so WordPress's 20px block gap cannot win there either. The function moves from `functions.php` to `inc/header.php` with the other header code, so the suite can load it on its own.
- **Hooked callbacks stay untyped,** as the theme's other filters are: the signatures below describe what each takes and returns, and a `null` handed on by another filter passes through instead of stopping the page under PHP 8.
- **The theme now reads one class the plugin sets,** `wpcpm-hb-open`. `bin/check-selectors.php` gains a lookup that every `wpcpm-` class named in the theme's scripts is set by the plugin, and the handbook launcher's comment, which says the two meet at one data attribute and nothing else, says so too. The plugin's own comment in `assets/js/handbook.js` says the same and is left for the plugin's next release (After the plan).

## Global Constraints

- Release (spec 6): "Version 1.24.20 in `style.css` and the readme, a changelog entry, the translation template regenerated; the zip built with `git archive`; the release through `~/Projects/wpcredits-release`, the live version read first, every outward step on the owner's yes." `Version:` in style.css and `Stable tag:` in readme.txt; the `= 1.24.20 =` entry above 1.24.19's; `sh bin/make-pot.sh` after the last code change.
- Copy (spec 6): "US English, plain hyphens only (never an em or en dash), full product names (Student Report Card, Mentor Report Card), "Administrators"; every new string translatable in `wpcredits-theme`." The new strings are "Menu" and "Home".
- Suites (spec 6): "The theme's suites stay green (`bin/test-header.php` gains the new behavior; `bin/test-hub.php`, `bin/test-hub-js.php`, `bin/check-selectors.php` against the plugin)." phpcs stays at main's 0 errors and 6 warnings in 3 files, with no new warning.
- Spacing (spec 6): "The owner's spacing rule: every gap of the changed header measured at each width before shipping."
- Mirror (spec 6): "The repository's docs are mirrored publicly: this note names roles, never people." Nothing in a file, a comment or a commit message names unreleased or private work.
- No sideways scroll (spec 4): "at any width from 320px up, open or closed."
- Without JavaScript (spec 4): "every link stays reachable: the header falls back to today's wrapped layout rather than hiding links behind a button that cannot open." The window before the deferred script runs is the trade-off decided above.
- Height (spec 4): "the closed bar is one row, and opening the menu does not change what the rest of the page thinks the header height is (the menu drops over the page, under the bar)."
- One button (spec 4): "The release must never show two menu buttons on one bar."
- Keyboard and screen readers (spec 4): "the button is a real button with `aria-expanded` and `aria-controls`; the menu is a list of the same links (no duplicates left in the tab order while it is closed); focus is visible; forced-colors mode shows the button and the menu's lines." Touch targets "at least 44px tall in the menu".
- Non-goals (spec 5): "the step cue (decision 3); the desktop header; the WordPress.org bars; the plugin's assistant panel; any change to which links a reader gets." No plugin change; the hub's fonts stay the hub's.
- Comments state the code's fact and name no plan, task, review or person. Commit messages end with a blank line and the co-author trailer the session names for its model, with its address (held in `$COAUTHOR`, Before Task 1).
- Never `git stash`, never push. Never touch the live site, the Studio copies or the Docker copy while building; the trial (Task 5) is the controller's, on the Studio copy at localhost:8883, inside a database snapshot put back afterwards. Never build into `~/GitHub/*.zip`; every zip goes to `$SCRATCH`.

## Review Focus

1. **Something inside the closed panel made visible:** the navigation block's submenu reveal on hover, focus or a pressed toggle, the theme's own reveal (style.css's `[aria-expanded="true"] ~` submenu rule) or a rule added later would show through the closed menu over the page. Pinned in Task 2 (no menu rule but the open panel's and the picture's says `visible`, and the menu's submenu rule outranks every reveal rule in the theme's sheet) and Task 5 (a navigation of three items with a submenu, a toggle pressed and the menu closed: nothing drawn).
2. **A navigation of two or more items, or with a submenu:** the navigation block's own toggle still never shows, every item is a 44px row in the panel and a submenu is expanded, not toggled, with its focus ring unclipped. Pinned in Task 1 (the fixture renders three items, one a submenu of two: the button, no `wpc-nav--lone`, the entries in order), Task 2 (the navigation's rules and the submenu rows' rule) and Task 5 (measured at 375 and 1024).
3. **A press on the Menu button while focus is in the panel, in a browser that does not focus a pressed button (Safari):** the focus moves to nothing, and the menu must not close and open again under the same press. Pinned in Task 3 ("focus moving to nothing leaves it open", with the stand-in's new `blur()`) and Task 5 (Safari and the iOS Simulator).
4. **A tap outside on iOS:** plain text or a background, where WebKit may send no `click` to the document; the menu must still close. Pinned in Task 3 (a `pointerdown` on the page's text closes, one on a link in the menu does not) and Task 5 (the iOS Simulator).
5. **A lone navigation item that is not the home page, on a signed-out phone:** it stays shown; only a lone link home is left out. Pinned in Task 1 (the `wpc-nav--home` mark) and Task 2 (the rule hides `.wpc-nav--home` alone).

## Execution

Subagent-driven (the owner's answer 5), strictly sequential in one worktree: the tasks share `inc/header.php`, `style.css` and `bin/test-header.php`, and each consumes the names the one before it produced. Every task gets its task review; the most capable reviewers go to Task 3 (focus, the hand-off with the plugin, the capture-phase listeners) and Task 2 (the cascade against core's navigation styles, visibility). Each commit message is the one its step gives.

---

## File Structure

| File | Responsibility |
| --- | --- |
| `inc/header.php` (new, Task 1; the script's enqueue in Task 3) | The head mark; `wpcredits_site_logo_fallback()`, moved from `functions.php`, its link named; `wpcredits_lone_nav_item()`, moved from `functions.php`, with the home mark; the entry count; the button's markup; the header filter; the menu script's enqueue. |
| `functions.php` (Task 1) | Requires `inc/header.php` right after `inc/hub-content.php`; the logo fallback and the navigation filter, with their `add_filter` lines, leave it. |
| `style.css` (Task 2; the version in Task 4) | The toggle rule shared by both buttons, the icon rules, the menu layout on screens below 1200px, its forced-colors rule, the signed-out phone row, the phone's Log in rule at the base rule's depth. |
| `assets/js/header-menu.js` (new, Task 3) | The disclosure. |
| `assets/js/header-height.js` (Task 3) | The stacked mark only while the group is in the bar's flow; the docblock says so. |
| `blocks/handbook-launcher/render.php` (Task 3) | The comment names the class the header's script reads. |
| `bin/check-selectors.php` (Task 3) | The lookup of the plugin classes the theme's scripts read. |
| `bin/test-header.php` (Tasks 1, 2, 3) | The new checks; it loads the stand-ins from Task 1 on; its docblock gains a sentence for each new section. |
| `bin/stubs/hub-wp.php` (Task 1) | The tag processor walks on and can read, test and add a class; `home_url()`, `untrailingslashit()`, `wp_print_inline_script_tag()`, `wp_enqueue_script()`, `get_bloginfo()`, `wp_strip_all_tags()`, `has_custom_logo()`; `wp_kses()` keeps the tags its list names. |
| `bin/stubs/header-markup.php` (new, Task 1) | `wpcredits_test_navigation()` and `wpcredits_test_header()`, the markup core and the theme's blocks render, for both header suites. |
| `bin/test-header-js.php`, `bin/js/header-harness.js` (new, Task 3), `bin/js/hub-dom.js` (Task 3) | The header scripts on the stand-in page; the stand-in gains what they use. |
| `readme.txt`, `languages/wpcredits-theme.pot` (Task 4) | The chrome section's fourth detail, the version, the changelog; the template. |

**The theme's checks**, run from the worktree's root after each task:

```bash
php bin/test-header.php | tail -1
[ -f bin/test-header-js.php ] && php bin/test-header-js.php | tail -1
php bin/test-hub.php | tail -1
php bin/test-hub-js.php | tail -1
php bin/check-selectors.php "$WPCPM_PLUGIN" | tail -6
php -d memory_limit=512M "$HOME/.composer/vendor/bin/phpcs" --no-colors --report=summary -q | grep TOTAL
```

Each suite prints `ok` / `FAIL` lines and ends `ALL PASS (N checks)` or `ALL PASS (N scenarios)`, or `N FAILURE(S) (...)`, and exits non-zero on a failure; a node suite prints one `skip` line where node is not installed. The selector check's last line covers every text file of the theme for an em or en dash.

---

## Before Task 1

- [ ] **Step 1: The worktree, the plugin's path, the scratch folder and the attribution line**

```bash
cd ~/GitHub/wpcredits-theme
git worktree add .worktrees/header-menu-1-24-20 -b header-menu-1-24-20 main
cd .worktrees/header-menu-1-24-20
git log --oneline -1
export WPCPM_PLUGIN="$HOME/GitHub/wpcredits-program-manager"
export SCRATCH="<the session's scratchpad>/theme-1-24-20"
mkdir -p "$SCRATCH"
export COAUTHOR="<the co-author trailer the session names for its model>"
```

Expected: `41c48f8 Merge 1.24.19: ...`. The theme's `.gitignore` already leaves `.worktrees/` out. `bin/check-selectors.php` defaults to the plugin beside the theme's folder, which a worktree does not have, so it is always given `$WPCPM_PLUGIN`. Every later step that writes a zip, a copy or a capture reads `$SCRATCH`; a fresh shell exports the four again.

- [ ] **Step 2: The checks on the base, for the record**

Run the theme's checks. Expected (measured on 41c48f8 while writing this plan):

```text
ALL PASS (6 checks)
ALL PASS (176 checks)
ALL PASS (36 scenarios)
355 classes dressed across 8 stylesheets: 15 built at run time, 1 kept for an older plugin, 0 printed by nothing.
102 hub and figure classes dressed, 0 printed by nothing.
60 tokens read, 61 declared, 0 undefined.
0 shared table selectors without a dashboard body class.
No em or en dashes in the theme.
A TOTAL OF 0 ERRORS AND 6 WARNINGS WERE FOUND IN 3 FILES
```

The template holds 426 msgids (`grep -c '^msgid' languages/wpcredits-theme.pot`); neither "Menu" nor "Home" is among them.

---

### Task 1: The head mark, the logo link's name and the markup the menu needs

**Files:**
- Create: `inc/header.php`, `bin/stubs/header-markup.php`
- Modify: `functions.php` (the `require_once` list; `wpcredits_site_logo_fallback()` and `wpcredits_lone_nav_item()`, with their `add_filter` lines, removed), `bin/stubs/hub-wp.php`, `bin/test-header.php`

**Interfaces** (signatures describe; hooked callbacks carry no type declarations):
- Produces, in `inc/header.php` (each hooked in the file, as the other `inc/` files hook theirs):
  - `wpcredits_js_mark()`, returns nothing, on `wp_head` at priority 1.
  - `wpcredits_site_logo_fallback( $content )`, the HTML string back, on `render_block_core/site-logo` (moved; the link gains `aria-label`).
  - `wpcredits_lone_nav_item( $content )`, the HTML string back, on `render_block_core/navigation` (moved; adds `wpc-nav--lone`, and `wpc-nav--home` when the one item links home).
  - `wpcredits_header_menu_entries( $html )`, an int: the entries the panel would list.
  - `wpcredits_header_menu_button()`, a string: the button's markup.
  - `wpcredits_header_menu( $content, $block )`, the HTML string back, on `render_block_core/group`, two arguments.
  - Markup later tasks rely on: `<header class="... wpc-header--menu">`; the button `.wpc-menu-toggle` with `aria-controls="wpc-header-menu"` and `aria-expanded="false"`, holding `svg.wpc-menu-toggle__open` and `svg.wpc-menu-toggle__close`; the panel `#wpc-header-menu.wpc-nav__center`, the button's next sibling; the navigation's `wpc-nav--home`.
- Produces, for the suites: `wpcredits_test_navigation( array $items ): string` (core's `<nav class="... wpc-nav ...">` markup with its open and close buttons, responsive container, dialog and list; each item `array( label, address )`, or `array( label, address, children )` for a submenu opened on click, printed as WordPress 7.1 prints one: the `li` with `has-child open-on-click`, a `button.wp-block-navigation-item__content.wp-block-navigation-submenu__toggle` with `aria-expanded="false"` and the label's span, the `span.wp-block-navigation__submenu-icon`, then `ul.wp-block-navigation__submenu-container` holding the children's items) and `wpcredits_test_header( array $reader = array() ): string` (the `<header>` element as `render_block_core/group` receives it for the part's outer group: the brand (its logo drawn by `wpcredits_site_logo_fallback( '' )`), the navigation passed through `wpcredits_lone_nav_item()`, the Need help? button, the chip; keys and defaults `signed_in` true, `nav` `array( array( 'Homepage', 'https://site.example/' ) )`, `help` true, `student` true, `mentor` true, `current` `''`, or `'student'` or `'mentor'` for the link that carries `is-current` and `aria-current="page"`). Its shapes are copied from a real render: the header part's three groups, the chip's two links, Log out and the avatar span with its initials, and signed out the guest chip's Log in. `$three_with_submenu` below is `array( array( 'Homepage', 'https://site.example/' ), array( 'About the program', 'https://site.example/about/' ), array( 'Programs', '', array( array( 'WordPress Credits', 'https://site.example/credits/' ), array( 'Campus Connect', 'https://site.example/campus/' ) ) ) )`.

- [ ] **Step 1: The stand-ins**

In `bin/stubs/hub-wp.php`: `WP_HTML_Tag_Processor::next_tag()` searches on from after the tag it last found (a first call from the start, as now); new `has_class( string $name ): bool`, `add_class( string $name ): bool` (appends to the class attribute, or adds one) and `get_attribute( string $name ): ?string` (the decoded value); `home_url( string $path = '' )` returns `'https://site.example' . $path`; `untrailingslashit()` as core's; `wp_print_inline_script_tag( $data )` echoes `<script>` . $data . `</script>` and a newline; `wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = array() )` records `$GLOBALS['scripts'][ $handle ] = array( $src, $deps, $ver, $args )` (initialized to `array()` with the other globals); `get_bloginfo( $show = '', $filter = 'raw' )` returns `$GLOBALS['opts']['blogname']` for `'name'` (set to `'WordPress Education Dashboard'` with the other options); `wp_strip_all_tags()` as core's; `has_custom_logo()` returns `! empty( $GLOBALS['custom_logo'] )` (false by default); `wp_kses()` keeps every tag its allowed list names, not `a` alone (the hub's calls name `a` alone and read as before; the logo's mark names `svg` and `path`). The file's comment names the header's files beside the hub's. Then create `bin/stubs/header-markup.php` with the two fixture functions above.

Run: `php bin/test-hub.php | tail -1` and `php bin/test-hub-js.php | tail -1`. Expected: `ALL PASS (176 checks)`, `ALL PASS (36 scenarios)`.

- [ ] **Step 2: Write the failing checks**

`bin/test-header.php` gains, right after its `ck()` and `theme_file()` helpers, `require_once __DIR__ . '/stubs/hub-wp.php';`, `require_once __DIR__ . '/stubs/header-markup.php';`, `require_once WPCREDITS_THEME_DIR . 'inc/icons.php';` and `require_once WPCREDITS_THEME_DIR . 'inc/header.php';`, and a section `=== The markup the menu needs ===` after the existing ones, with `$menu = static function ( $html, $class = 'wpc-header' ) { return wpcredits_header_menu( $html, array( 'blockName' => 'core/group', 'attrs' => array( 'className' => $class ) ) ); };`, pages read with `DOMDocument`/`DOMXPath` as the existing check reads the part, and `$entries_of( $html )`, the texts of the elements with the class `wp-block-navigation-item__content`, `wpc-ask`, `wpc-viewer__link` or `wpc-viewer__logout` inside `#wpc-header-menu`, in document order (the navigation's own two buttons are not entries). Eleven checks:

```php
ck( 'the page is marked as running JavaScript first thing in the head: `wpcredits_js_mark()` on `wp_head` at priority 1, printing one line',
	array( $hooked_priority_of( 'wp_head', 'wpcredits_js_mark' ), trim( $printed_by( 'wpcredits_js_mark' ) ) ),
	array( 1, "<script>document.documentElement.classList.add( 'wpc-js' );</script>" ) );

// $GLOBALS['logged_in'] = true; $GLOBALS['marked'] = true for this one.
ck( 'a signed-in reader\'s header gets one Menu button, right before the group holding the links, which it names: the button, the panel\'s ID and the header\'s class are all the filter adds',
	array( $buttons, $attributes_of_the_button, $next_element_id_and_class, $header_has_class, $menu_html_without_the_three === $input ),
	array( 1, array( 'type' => 'button', 'class' => 'wpc-menu-toggle', 'aria-controls' => 'wpc-header-menu', 'aria-expanded' => 'false', 'aria-label' => '[Menu]' ), array( 'wpc-header-menu', true ), true, true ) );

ck( 'the button draws the menu icon and the close icon, both hidden from screen readers',
	array( $svg_classes, $svg_aria_hidden ),
	array( array( 'wpc-icon wpc-menu-toggle__open', 'wpc-icon wpc-menu-toggle__close' ), array( 'true', 'true' ) ) );

ck( 'the links stay the same elements in the same order, inside the panel, the picture last in it; a navigation of three items, one a submenu of two, lists its items in order, the submenu\'s under its parent',
	array( $entries_of( $menu( wpcredits_test_header() ) ), $last_child_class_of_the_chip, $entries_of( $menu( wpcredits_test_header( array( 'nav' => $three_with_submenu ) ) ) ) ),
	array( array( 'Homepage', 'Need help?', 'Student Report Card', 'Mentor Report Card', 'Log out' ), 'wpc-viewer__avatar', array( 'Homepage', 'About the program', 'Programs', 'WordPress Credits', 'Campus Connect', 'Need help?', 'Student Report Card', 'Mentor Report Card', 'Log out' ) ) );

ck( 'a header that would list one entry, Log out alone, is left as it is; Homepage and Log out are two and get the button; so does the navigation of three items with a submenu and nothing else',
	array( $menu( $log_out_alone ) === $log_out_alone, $buttons_in( $menu( $homepage_and_log_out ) ), $buttons_in( $menu( $three_with_submenu_no_help_no_cards ) ) ),
	array( true, 1, 1 ) );

ck( 'signed out, and every other group, are left as they are: the inner group, the brand, the part, a group with no class, a class that holds the word only inside another',
	array( /* each output === its input */ ),
	array( true, true, true, true, true, true ) );   // classes: 'wpc-header' signed out, 'wpc-header__inner', 'wpc-brand', 'wpc-header-part', none, 'wpc-header-x'

ck( 'an ID the Site Editor gave the group is replaced by the one the button names, and the page holds it once',
	array( $id_of_the_panel, substr_count( $out, 'id="wpc-header-menu"' ), $aria_controls ),
	array( 'wpc-header-menu', 1, 'wpc-header-menu' ) );

ck( 'a navigation of one item is marked lone, and home too when its item leads to the home page, with or without the trailing slash; another lone item, and the three items with a submenu, are not marked home',
	array( $lone_and_home( array( array( 'Homepage', 'https://site.example/' ) ) ), $lone_and_home( array( array( 'Homepage', 'https://site.example' ) ) ), $lone_and_home( array( array( 'About', 'https://site.example/about/' ) ) ), $lone_and_home( $three_with_submenu ) ),
	array( array( true, true ), array( true, true ), array( true, false ), array( false, false ) ) );

ck( 'functions.php requires inc/header.php right after inc/hub-content.php, and the logo fallback and the navigation filter are each written once, in inc/header.php',
	array( false !== strpos( theme_file( 'functions.php' ), "require_once get_theme_file_path( 'inc/hub-content.php' );\nrequire_once get_theme_file_path( 'inc/header.php' );" ), $definitions_of( 'function wpcredits_site_logo_fallback(' ), $definitions_of( 'function wpcredits_lone_nav_item(' ) ),
	array( true, array( 'inc/header.php' ), array( 'inc/header.php' ) ) );

// $GLOBALS['marked'] = true for the second; $GLOBALS['custom_logo'] = true for the third, with core's own markup as the content.
ck( 'the logo link is named with the site\'s name, or "Home" on a site with none, its mark still hidden from screen readers; an uploaded logo is left as core prints it',
	array( $logo_link_attributes( wpcredits_site_logo_fallback( '' ) ), $logo_link_attributes( $with_blogname( '', 'wpcredits_site_logo_fallback' ) )['aria-label'], wpcredits_site_logo_fallback( $core_logo ) === $core_logo ),
	array( array( 'class' => 'custom-logo-link', 'href' => 'https://site.example/', 'rel' => 'home', 'aria-label' => 'WordPress Education Dashboard', 'svg aria-hidden' => 'true' ), '[Home]', true ) );

ck( 'the fixture prints the classes the blocks print: each of wpc-ask, wpc-viewer__link, wpc-viewer__logout, wpc-viewer__avatar and wpc-viewer__signin is in its render.php, read without its comments',
	$classes_missing_from( array( 'blocks/handbook-launcher/render.php' => array( 'wpc-ask' ), 'blocks/viewer-chip/render.php' => array( 'wpc-viewer__link', 'wpc-viewer__logout', 'wpc-viewer__avatar', 'wpc-viewer__signin' ) ) ),
	array() );
```

`$definitions_of` walks every `.php` file of the theme outside `bin/`, skipping `/.git/`, `/.worktrees/`, `/.superpowers/` and `/node_modules/` on the path below the theme's root, as `bin/check-selectors.php` does, so a linked worktree's copy is never counted. `$classes_missing_from` reads each file with the PHP tokenizer without its comments, as `wpcredits_without_comments()` does in the selector check. The script's enqueue is checked in Task 3, which writes it.

- [ ] **Step 3: Run them to see them fail**

Run: `php bin/test-header.php | tail -3`. Expected: a fatal on the missing `inc/header.php` (the require), so nothing is counted.

- [ ] **Step 4: Write `inc/header.php` and move the navigation filter**

Create `inc/header.php` (its docblock: the site header's request-time markup; the `ABSPATH` guard as `inc/icons.php` has it) with the six functions of the Interfaces block, and move `wpcredits_site_logo_fallback()` and `wpcredits_lone_nav_item()`, with their docblocks and `add_filter` lines, out of `functions.php`, unchanged but for the name and the home mark. Then in `functions.php` add `require_once get_theme_file_path( 'inc/header.php' );` on the line after `inc/hub-content.php`'s.

- `wpcredits_site_logo_fallback()`: moved with its docblock; when it draws the mark, the link is `<a class="custom-logo-link" href="%1$s" rel="home" aria-label="%2$s">`, the label `esc_attr()` of the trimmed `wp_strip_all_tags( get_bloginfo( 'name', 'display' ) )`, or of `__( 'Home', 'wpcredits-theme' )` when that is empty; the mark stays `wp_kses( wpcredits_get_logo( 24 ), wpcredits_svg_allowed_html() )`, hidden from screen readers. Its docblock gains the sentence on the name.
- `wpcredits_lone_nav_item()`: before marking the `NAV`, a second processor reads the first `wp-block-navigation-item__content`'s `href`; `wpc-nav--home` is added beside `wpc-nav--lone` when `untrailingslashit( (string) $href ) === untrailingslashit( home_url( '/' ) )`. Its docblock gains the sentence on the mark's one use.
- `wpcredits_header_menu_entries()`: every tag with any of `wp-block-navigation-item`, `wpc-ask`, `wpc-viewer__link`, `wpc-viewer__logout`, counted once.
- `wpcredits_header_menu_button()` returns exactly this, the two icons as `wpcredits_get_icon()` returns them (each escapes its own attributes, so no further escaping and no `wp_kses()`):

```php
sprintf(
	'<button type="button" class="wpc-menu-toggle" aria-controls="wpc-header-menu" aria-expanded="false" aria-label="%1$s">%2$s%3$s</button>',
	esc_attr__( 'Menu', 'wpcredits-theme' ),
	wpcredits_get_icon( 'menu', 24, 'wpc-menu-toggle__open' ),
	wpcredits_get_icon( 'close', 24, 'wpc-menu-toggle__close' )
)
```

- `wpcredits_header_menu()` changes `$content` only when all of these hold: the block's `className`, split on whitespace, holds `wpc-header`; the reader is signed in; `WP_HTML_Tag_Processor` exists; the entries are two or more; the `HEADER` tag and then the `wpc-nav__center` tag are found. When all hold, one processor adds `wpc-header--menu` to the `HEADER` and sets `id` to `wpc-header-menu` on the group, and the button is inserted at the group's tag, the one non-obvious step:

```php
$html  = $tags->get_updated_html();
$at    = strpos( $html, 'id="wpc-header-menu"' );
$start = false === $at ? false : strrpos( substr( $html, 0, $at ), '<' );

return false === $start ? $content : substr_replace( $html, wpcredits_header_menu_button(), $start, 0 );
```

Core's processor writes a new attribute right after the tag's name and the stand-in at the tag's end; finding the tag's `<` before the ID holds for both. Otherwise it returns `$content` as it came.

- [ ] **Step 5: Run the checks**

Run the theme's checks. Expected: `ALL PASS (17 checks)` for `bin/test-header.php` (6 and 11); the hub suites as on the base; check-selectors as on the base; phpcs `0 ERRORS AND 6 WARNINGS ... IN 3 FILES`.

- [ ] **Step 6: Commit**

```bash
git add inc/header.php functions.php bin/stubs/hub-wp.php bin/stubs/header-markup.php bin/test-header.php
git commit -m "Site header: the logo link is named with the site's name; a signed-in reader's header gets a Menu button right before the group holding its links, which it names, unless it would list one entry; the page is marked as running JavaScript first thing in the head" -m "$COAUTHOR"
```

---

### Task 2: The stylesheet

**Files:**
- Modify: `style.css` (the mobile toggle rule; the 640px block's Log in rule; new rules after the `wpc-header--stacked` block, before the `max-width: 600px` admin bar block), `bin/test-header.php`

**Interfaces:**
- Consumes: Task 1's classes, ID and markup.
- Produces: `.wpc-header--open` on the header shows the panel (Task 3 toggles it); `position: absolute` on `.wpc-nav__center` in the menu layout (Task 3's height guard reads it).

- [ ] **Step 1: Write the failing checks**

A section `=== The menu layout ===` in `bin/test-header.php`, with `$menu_at = '@media screen and ( max-width: 1199px )'` and `$m = '.wpc-js .wpc-header--menu'` (each `$m` below written out in full in the sheet). Each rule of the table is one rule of the sheet with this selector list, character for character, in this at-rule. Its "Pinned" declarations are the ones the cascade, the layout and the 44px arithmetic rest on: the check asserts that the rule exists once and holds every one of them. The "Also written" declarations are written as given and measured in the trial instead.

| Selector list (at-rule) | Pinned | Also written |
| --- | --- | --- |
| `.wpc-header .wpc-nav .wp-block-navigation__responsive-container-open:not( .always-shown ), .wpc-header .wpc-menu-toggle` (none) | `display: none; height: 44px; width: 44px;` | the existing rule's other declarations, as they are |
| `.wpc-menu-toggle .wpc-menu-toggle__close, .wpc-menu-toggle[aria-expanded="true"] .wpc-menu-toggle__open` (none) | `display: none;` | |
| `.wpc-menu-toggle[aria-expanded="true"] .wpc-menu-toggle__close` (none) | `display: block;` | |
| `$m` (`$menu_at`) | `position: relative; transform: translateX( 0 );` | |
| `$m .wpc-header__inner` | `column-gap: 12px; flex-wrap: nowrap;` | |
| `$m .wpc-brand` | `flex: 0 1 auto; gap: 12px; margin-right: calc( 34px + 12px ); min-width: 0;` | |
| `$m .wpc-brand__name` | `min-width: 0; overflow-wrap: anywhere; white-space: normal;` | |
| `$m .wpc-menu-toggle` | `display: flex; flex: none; margin-left: auto;` | `cursor: pointer;` |
| `$m .wpc-menu-toggle[aria-expanded="true"]` | | `background: var( --wpc-chrome-hover );` |
| `$m .wpc-nav__center` | `display: block; left: 0; overflow-y: auto; position: absolute; right: 0; top: calc( 100% + 1px ); visibility: hidden; z-index: 55;` and both `max-height` lines: `calc( 100vh - var( --wpc-header-live, var( --wpc-header-height ) ) - var( --wp-admin--admin-bar--height, 0px ) - var( --wp-global-header-height, 0px ) - var( --wp--style--block-gap, 0px ) )`, then the same with `100svh` (the WordPress.org bar and core's block gap above the header part counted, a correction made in the build's final review) | `background: var( --wpc-surface ); border-bottom: 1px solid var( --wpc-chrome-rule ); box-shadow: 0 12px 24px rgba( 0, 0, 0, .08 ); overscroll-behavior: auto; padding: 4px var( --wpc-chrome-gutter ) 8px;` |
| `.wpc-js .wpc-header--menu.wpc-header--open .wpc-nav__center` | `visibility: visible;` | |
| `$m .wpc-viewer__avatar` | `margin-top: -17px; position: fixed; right: calc( var( --wpc-chrome-gutter ) + 44px + 12px ); top: 50%; visibility: visible;` | |
| `$m .wpc-viewer` | `display: block;` | |
| `$m .wpc-nav.wp-block-navigation` | `display: block; position: static;` | |
| `$m .wpc-nav .wp-block-navigation__responsive-container-open:not( .always-shown ), $m .wpc-nav .wp-block-navigation__responsive-container-close` | `display: none;` | |
| `$m .wpc-nav .wp-block-navigation__responsive-container:not( .is-menu-open )` | `display: block; height: auto; padding: 0; position: static; width: auto;` | `background: none;` |
| `$m .wpc-nav .wp-block-navigation__responsive-container:not( .is-menu-open ) .wp-block-navigation__responsive-dialog, $m .wpc-nav .wp-block-navigation__responsive-container:not( .is-menu-open ) .wp-block-navigation__responsive-container-content` | `display: block; padding: 0; position: static;` | |
| `$m .wpc-nav .wp-block-navigation__container, $m .wpc-nav .wp-block-navigation-item` | `display: block; width: auto;` | |
| `$m .wpc-nav .has-child .wp-block-navigation-submenu__toggle[aria-expanded] ~ .wp-block-navigation__submenu-container, $m .wpc-nav .has-child .wp-block-navigation__submenu-container` | `display: block; height: auto; min-width: 0; opacity: 1; overflow: visible; padding: 0 0 0 12px; position: static; visibility: inherit; width: auto;` | `border: 0; box-shadow: none;` |
| `$m .wpc-nav .wp-block-navigation__submenu-icon` | `display: none;` | |
| the rows: `$m .wpc-nav .wp-block-navigation-item__content, $m .wpc-nav .has-child .wp-block-navigation__submenu-container > .wp-block-navigation-item > .wp-block-navigation-item__content, $m .wpc-nav__center .wpc-ask, $m .wpc-viewer .wpc-viewer__link, $m .wpc-viewer .wpc-viewer__logout` | `border-bottom: 1px solid var( --wpc-chrome-rule ); display: block; font-size: 15px; line-height: 20px; padding: 12px 0; width: 100%;` | `background: none; border-radius: 0; color: var( --wpc-ink ); text-align: left; white-space: normal;` |
| one rule: the five row selectors with `:hover`, then the five with `:focus-visible` | | `color: var( --wpc-brand );` |
| `$m .wpc-viewer .wpc-viewer__logout` | `border-bottom: 0;` | `color: var( --wpc-chrome-muted );` |
| `$m .wpc-nav [aria-current="page"], $m .wpc-viewer .wpc-viewer__link.is-current` | | `color: var( --wpc-brand ); font-weight: 500;` |
| `$m .wpc-nav__center [aria-current="page"]` (`@media screen and ( max-width: 1199px ) and ( forced-colors: active )`) | `text-decoration: underline;` | |
| `body:not( .wpc-signed-in ) .wpc-header__inner` (`@media ( max-width: 640px )`, a block after the menu layout's) | `column-gap: 12px; flex-wrap: nowrap;` | |
| `body:not( .wpc-signed-in ) .wpc-brand` (same) | `flex: 1 1 auto; gap: 12px;` | |
| `body:not( .wpc-signed-in ) .wpc-nav__center` (same) | `flex: none;` | |
| `body:not( .wpc-signed-in ) .wpc-header .wpc-nav--home` (same) | `display: none;` | |
| `.wpc-header .wpc-viewer__signin, .wpc-header .wpc-viewer--guest a` (`@media ( max-width: 640px )`, the existing phone block, the rule that was written one class deep) | `font-size: 14px; padding: 9px 14px;` | |

The submenu rows' selector is seven classes deep, so it outranks the theme's six-deep submenu link rule (14px, 9px 12px) and core's five-deep one; the submenu container's first selector carries `[aria-expanded]`, seven deep, so it outranks the theme's six-deep `[aria-expanded="true"] ~` reveal (`display: flex; width: 240px`) and core's, in any print order.

Write them as eight checks, grouped: the two buttons and their icons; the bar's one row (header, inner, brand, title, toggle, open toggle); the panel and its open state; the picture; the navigation inside the panel (its toggle and close hidden, container, dialog and content, list and items, submenu, submenu icon); the rows, their hover and focus, Log out and the current page; forced colors; the signed-out phone row. Then five more, the first of them the phone's Log in button, with `$specificity( $selector )` counting a selector's classes, attribute selectors and pseudo-classes (the argument of `:not()` counted, `:not` itself not):

```php
ck( 'on a phone the Log in button is 14px with 9px 14px padding, as its 640px rule always said: the rule is two classes deep, as the base rule is, and comes after it; no one-class version is left',
	array( rules_of( '.wpc-header .wpc-viewer__signin, .wpc-header .wpc-viewer--guest a', '@media ( max-width: 640px )' ), $index_of_rule( '.wpc-header .wpc-viewer__signin, .wpc-header .wpc-viewer--guest a', '@media ( max-width: 640px )' ) > $index_of_rule( '.wpc-header .wpc-viewer__signin, .wpc-header .wpc-viewer--guest a', '' ), rules_anywhere( '.wpc-viewer__signin, .wpc-viewer--guest a' ) ),
	array( array( array( 'font-size: 14px;', 'padding: 9px 14px;' ) ), true, 0 ) );

ck( 'nothing in the panel but the picture and the open panel itself is made visible, so nothing shows through the closed menu',
	$selectors_declaring_under( 'wpc-header--menu', 'visibility: visible;' ),
	array( '.wpc-js .wpc-header--menu.wpc-header--open .wpc-nav__center', '.wpc-js .wpc-header--menu .wpc-viewer__avatar' ) );

ck( 'the menu\'s submenu rules are in the sheet and outrank every rule outside the menu layout that reveals or restyles a submenu: deeper than each (6 today), so the closed panel stays closed after a submenu toggle is pressed, and a submenu row keeps the 44px row',
	array( $deepest_menu_selector_naming( '.wp-block-navigation__submenu-container' ) > $deepest_outside_menu( '.wp-block-navigation__submenu-container' ), $deepest_menu_selector_naming( '.wp-block-navigation__submenu-container > .wp-block-navigation-item > .wp-block-navigation-item__content' ) > $deepest_outside_menu( '.wp-block-navigation__submenu-container > .wp-block-navigation-item > .wp-block-navigation-item__content' ) ),
	array( true, true ) );

ck( 'every rule of the menu layout is inside the screen query below 1200px, or its forced-colors twin, and each of its selectors starts with `.wpc-js .wpc-header--menu`, so without JavaScript, signed out, in print and from 1200px nothing of it applies',
	array( $at_rules_of_rules_naming( array( 'wpc-header--menu', 'wpc-header--open' ) ), $selectors_not_starting_with( '.wpc-js .wpc-header--menu' ) ),
	array( array( '@media screen and ( max-width: 1199px )', '@media screen and ( max-width: 1199px ) and ( forced-colors: active )' ), array() ) );

ck( 'the menu layout comes after every header rule it restates, the 640px, 960px and stacked blocks among them, so a tie of specificity goes its way',
	$index_of_first_rule_naming( 'wpc-header--menu' ) > $index_of_last_rule_naming( array( '.wpc-header--stacked', 'wpc-nav--lone', '.wpc-viewer__logout', '.wpc-nav__center' ), array( 'wpc-header--menu', 'wpc-signed-in' ) ),
	true );
```

`$index_of_rule( $selector, $at )` is the index in `sheet_rules()` of the rule with exactly that selector list in exactly that at-rule, or -1. `$deepest_outside_menu( $needle )` is the highest `$specificity()` among the selectors of `sheet_rules()` that contain `$needle` and do not contain `wpc-header--menu`; `$deepest_menu_selector_naming( $needle )` the highest among those that contain both, or 0 when there is none, so the check fails until the rules are written (the container rule's plain selector, five deep, only has to beat the closed state's four-deep `display: none`; its `[aria-expanded] ~` selector, seven deep, is the one that meets the reveals). `$index_of_last_rule_naming( $names, $except )` is the index in `sheet_rules()` of the last rule whose selector contains any of `$names` and none of `$except`; `$index_of_first_rule_naming( $name )` the index of the first rule whose selector contains `$name`. `$selectors_declaring_under( $class, $declaration )` lists, in sheet order, the selectors of the rules whose selector contains `$class` and which hold `$declaration`. The existing six checks stay as they are.

- [ ] **Step 2: Run them to see them fail**

Run: `php bin/test-header.php | tail -1`. Expected: `13 FAILURE(S) (30 checks)`.

- [ ] **Step 3: Write the rules**

In `style.css`, grow the mobile toggle rule's selector list, write the 640px block's Log in rule two classes deep where it stands (its comment: two classes, as the base rule it narrows, so it takes effect), and add the rules of the table, each with its "Pinned" and "Also written" declarations in alphabetical order: the icon rules beside the toggle rule; the menu layout's block, its forced-colors block and the signed-out 640px block after the `wpc-header--stacked` block and before the `max-width: 600px` admin bar block. Every `var()` written `var( --x )`. Each block's comment states its facts: the menu layout is the signed-in bar of one row on screens below 1200px once the page runs JavaScript, the panel is the group holding the links, the header is the containing block of the panel and, through the transform, of the fixed picture, the closed panel is hidden by visibility so the picture inside it can stay shown, and every other visibility inside it inherits; the submenu rules are as deep as they are to outrank the reveal rules; the signed-out phone row leaves out a lone link home and restates its gaps. The comment over `.wpc-header__inner` (the 900px and 720px wrap boundaries) gains one sentence: on screens below 1200px, a signed-in reader with JavaScript has the one row of the menu layout instead.

- [ ] **Step 4: Run the checks**

Run the theme's checks. Expected: `ALL PASS (30 checks)`; check-selectors as on the base; phpcs as on the base.

- [ ] **Step 5: Commit**

```bash
git add style.css bin/test-header.php
git commit -m "Site header: below 1200px a signed-in reader's header is one row, the brand, the account picture and the Menu button, with the links in a panel under the bar; signed out, a phone shows the brand and a smaller Log in button on one row" -m "$COAUTHOR"
```

---

### Task 3: The script

**Files:**
- Create: `assets/js/header-menu.js`, `bin/test-header-js.php`, `bin/js/header-harness.js`
- Modify: `inc/header.php` (the enqueue), `assets/js/header-height.js`, `blocks/handbook-launcher/render.php` (its comment), `bin/check-selectors.php`, `bin/js/hub-dom.js`, `bin/test-header.php`

**Interfaces:**
- Consumes: Task 1's markup and fixtures; Task 2's `.wpc-header--open` and the panel's `position: absolute`.
- Produces: `wpcredits_header_menu_script()`, returns nothing, on `wp_enqueue_scripts`, the handle `wpcredits-header-menu`; the header's class `wpc-header--open` while the menu is open.

- [ ] **Step 1: The stand-in page and the failing scenarios**

`bin/js/hub-dom.js` gains, its comment naming the header's scripts beside the hub's:
- `classList.toggle( name, force )`;
- `focus()` fires `focusout` (bubbling, `relatedTarget` the element gaining focus) on the element losing it, then sets `activeElement`, then fires `focusin` (`relatedTarget` the one losing it), and does nothing when the element has the focus already; `blur()` fires `focusout` with `relatedTarget` `null` and leaves nothing focused;
- the capture phase: `addEventListener( type, listener, true )` or `{ capture: true }` keeps the listener apart, and `dispatch()` first runs the capture listeners from the document down to the target's parent, then the target's own listeners, then the bubble as now; `stopPropagation()` in either phase stops the rest;
- `document.documentElement`, an `html` element holding `body`;
- a `MutationObserver` on the page's `window` whose `observe( target, { attributes: true, attributeFilter } )` gets each `setAttribute()` on the target as an attribute record, delivered in a microtask after the script that made it, as a browser delivers them.

Run `php bin/test-hub-js.php | tail -1`: `ALL PASS (36 scenarios)`.

Create `bin/test-header-js.php` on `bin/test-hub-js.php`'s pattern (the same output, summary and skip contracts; `WPCREDITS_THEME_DIR` passed to the harness), rendering these pages through `wpcredits_header_menu()` with the stand-ins, each the header part around `wpcredits_test_header()` followed by a `<main>` holding a paragraph with a link: `admin` (every entry), `submenu` (the navigation `$three_with_submenu`), `student` (`mentor` false, `current` `'student'`), `one` (Log out alone: no button), `guest` (signed out). Create `bin/js/header-harness.js` on `bin/js/hub-harness.js`'s pattern, with scenarios that may return a promise (awaited before comparing), opening a page with `header-height.js` then `header-menu.js` run in a `vm` context after the markup, and stand-ins for `ResizeObserver` (its callback kept, called by `resize()`), `requestAnimationFrame` (queued, run by `resize()` after the callback) and `getComputedStyle()` (`position` from an element's `position` property, `'static'` by default). The plugin's assistant is stood in for as its footer script behaves, registered on the document (bubble phase) before the theme's scripts: a click on `[data-wpcpm-handbook-open]` is prevented, keeps its trigger as the opener, adds `wpcpm-hb-open` to the root and focuses the page's `<main>`; `closeAssistant()` removes the class, then focuses the opener, as the plugin's `close()` does. Scenarios, each name the line it prints, then what it returns:

```js
'the button opens the menu and closes it again, aria-expanded and the header\'s class saying so, the focus staying on the button'
	// [ [ 'true', true, true ], [ 'false', false, true ] ]
'Escape closes the open menu from any entry in it and puts focus on the Menu button'
	// from Log out: [ 'false', true ]
'Escape still closes it when a listener inside the panel stops the keydown, since the menu listens in the capture phase'
	// a listener on the navigation calling stopPropagation(): [ 'false', true ]
'Escape with the menu closed changes nothing and moves no focus'
	// [ 'false', true ]  (focus still on the page's link)
'a click outside the menu and the button closes it and moves no focus; so does a click on the account picture, drawn in the bar'
	// [ [ 'false', true ], 'false' ]
'a touch outside closes it on pointerdown, as on a phone that sends the document no click for a tap on plain text; a touch on a link in the menu, or on the Menu button, does not'
	// pointerdown on the page's paragraph, then on a menu link, then on Menu: [ 'false', 'true', 'true' ]
'a link followed from the menu closes it, its default prevented or not; one followed with Ctrl, Cmd, Shift or Alt held opens elsewhere and leaves it open'
	// [ [ 'false', 'false' ], [ 'true', 'true', 'true', 'true' ] ]
'Need help? in the open menu closes it, the assistant opens, and when the assistant closes focus comes to the Menu button, after the plugin gave it to Need help?'
	// [ 'false', true, true ]  (after the microtask)
'the assistant opened with the menu closed, as from the desktop row, gets its focus back from the plugin, on Need help?; so does one opened after the menu was opened again and closed by Escape'
	// [ true, true ]
'focus moving from the menu to the page closes it; to the Menu button, or to nothing (blur(), a press Safari does not focus), leaves it open'
	// [ 'false', 'true', 'true' ]
'a navigation of three items with a submenu: the submenu\'s toggle and links are in the panel and close it as the others do'
	// on submenu: a submenu link followed: [ 'false' ]
'a header without the button, signed out or with one entry, is left alone: nothing throws and nothing is marked'
	// [ [ false, false ], [ false, false ] ]  (wpc-header--open, a throw)
'without MutationObserver the menu still opens and closes, and the assistant\'s focus stays the plugin\'s'
	// [ 'true', 'false', true ]
'the bar is marked stacked while the group holding the links sits below the brand in the row, and never while the group is out of the row, as the menu\'s panel is'
	// boxes: brand 10 to 54, group 64 to 300; [ true, false ]  (position static, then absolute)
'the header\'s height is written on the root as before, rounded up'
	// part's box 0 to 64.2: '65px'
```

`bin/test-header.php` gains two checks:

```php
// The callbacks hooked on `wp_enqueue_scripts` run through `$GLOBALS['hooks']`, once with `$GLOBALS['logged_in']` true and once false.
ck( 'the menu script is enqueued for a signed-in reader alone: deferred, no dependency, the theme\'s version',
	array( $scripts_when_signed_in['wpcredits-header-menu'] ?? null, $scripts_when_signed_out ),
	array( array( 'https://site.example/wp-content/themes/wpcredits-theme/assets/js/header-menu.js', array(), '9.9.9', array( 'strategy' => 'defer' ) ), array() ) );

ck( 'the handbook launcher\'s comment names the class the header\'s menu script reads', false !== strpos( theme_file( 'blocks/handbook-launcher/render.php' ), 'wpcpm-hb-open' ), true );
```

- [ ] **Step 2: Run them to see them fail**

Run: `php bin/test-header-js.php | tail -1` and `php bin/test-header.php | tail -1`. Expected: the harness stops on the missing `assets/js/header-menu.js` (`1 FAILURE(S) (0 scenarios)` or the read error), and `2 FAILURE(S) (32 checks)`.

- [ ] **Step 3: Write the script, the guard and the enqueue**

- `assets/js/header-menu.js`, the behavior of "Where the spec left a choice" above, in this shape: find `.wpc-menu-toggle`, the panel by its `aria-controls`, the header by `closest( '.wpc-header' )`, and return if any is missing. `setOpen( open )` writes `aria-expanded`, adds or removes `wpc-header--open`, and on opening clears the hand-off mark. The button's `click` toggles. The panel's `click` closes on `[data-wpcpm-handbook-open]` (marking the hand-off) and on `a[href]` held without a modifier, whatever `defaultPrevented` says. One `outside( event )` handler, registered in the capture phase on the document for both `pointerdown` and `click`, closes on a target outside the panel and the button, or inside `.wpc-viewer__avatar`. The document's `keydown`, in the capture phase, closes on `Escape` and focuses the button. The header's `focusout` closes when `relatedTarget` is an element outside the panel and the button. Where the browser has a `MutationObserver`, one on `document.documentElement` (`attributeFilter: [ 'class' ]`) keeps whether `wpcpm-hb-open` was present and, on a record where it was present and now is not and the hand-off is marked, clears the mark and focuses the button. Every handler but the button's does nothing while the menu is closed. The docblock states what the script does and that it reads the plugin's `wpcpm-hb-open`.
- `assets/js/header-height.js`: in `arrange()`, the stacked condition becomes `'absolute' !== window.getComputedStyle( group ).position && group.getBoundingClientRect().top >= brand.getBoundingClientRect().bottom`; the docblock adds that the group out of the bar's flow, as the menu's panel is below 1200px, never marks the bar.
- `inc/header.php`: `wpcredits_header_menu_script()` enqueues `wpcredits-header-menu` (`get_theme_file_uri( 'assets/js/header-menu.js' )`, no dependency, `WPCREDITS_VERSION`, `array( 'strategy' => 'defer' )`) when `is_user_logged_in()`, on `wp_enqueue_scripts`.
- `blocks/handbook-launcher/render.php`: the docblock's sentence that the two sides meet at the data attribute "and nothing else" becomes: they meet at that data attribute, and the header's menu script also reads the class `wpcpm-hb-open` the plugin puts on the root element while its panel is open, to give focus back to the Menu button when it closes; `bin/check-selectors.php` checks the plugin still sets it.
- `bin/test-header.php`'s docblock gains a sentence for each section the release added (the markup, the menu layout, the script's enqueue).

- [ ] **Step 4: The selector check's new lookup**

`bin/check-selectors.php` gains a family, placed after the shared-table scope family (whose own early exit comes before it) and before the dash check:
- the names: in the theme's `assets/js/*.js` and `blocks/*/view.js`, read through `wpcredits_without_comments()`, every match of `/(?<![A-Za-z0-9_-])wpcpm-[A-Za-z0-9_-]+/` inside a quoted string, so `[data-wpcpm-handbook-open]` is not read as a class (its `wpcpm-` follows a hyphen);
- the corpus: the plugin's PHP, JS, HTML and JSON source alone, read without comments, never the existing corpus of plugin and theme, in which the theme's own script would vouch for itself;
- each name looked up with `wpcredits_printed()`; a miss is listed with its file and line;
- the line printed: `1 plugin class read by the theme's scripts, 0 set by nothing.` (`classes` for more than one);
- the final `exit()` gains the family's misses beside `$misses || $hub_misses`.

The docblock gains the paragraph. Prove it bites: `rsync -a --exclude .git --exclude .worktrees --exclude .superpowers "$WPCPM_PLUGIN/" "$SCRATCH/plugin-renamed/"`, change `wpcpm-hb-open` to `wpcpm-hb-shown` in its `assets/js/handbook.js`, run `php bin/check-selectors.php "$SCRATCH/plugin-renamed"`: exit 1, naming `wpcpm-hb-open` in `assets/js/header-menu.js`. Delete the copy.

- [ ] **Step 5: Run the checks**

Run the theme's checks. Expected: `ALL PASS (32 checks)`, `ALL PASS (15 scenarios)`, the hub suites as on the base, check-selectors with the new line before `No em or en dashes in the theme.`, phpcs as on the base.

- [ ] **Step 6: Commit**

```bash
git add assets/js/header-menu.js assets/js/header-height.js inc/header.php blocks/handbook-launcher/render.php bin/check-selectors.php bin/js/hub-dom.js bin/js/header-harness.js bin/test-header-js.php bin/test-header.php
git commit -m "Site header: the Menu button opens and closes the panel; Escape, a press outside, a link or Need help? closes it, and focus comes back to the button when the assistant closes" -m "$COAUTHOR"
```

---

### Task 4: The version, the readme, the changelog and the template

**Files:**
- Modify: `style.css` (`Version:`), `readme.txt` (`Stable tag:`, "The chrome", `== Changelog ==`), `languages/wpcredits-theme.pot`

- [ ] **Step 1: The version and the readme**

`Version: 1.24.20` and `Stable tag: 1.24.20`. In "= The chrome =", "Three details" becomes "Four details", and a fourth bullet after the sign-in button's:

```text
* Below 1200px a signed-in reader's header is one row: the brand, the account
  picture and a Menu button whose panel lists the navigation, Need help?, the Student
  Report Card and the Mentor Report Card the reader can open, and Log out, in the
  order of the wide row. Without JavaScript the header keeps its wrapped row of links.
```

The changelog entry, above `= 1.24.19 =`:

```text
= 1.24.20 =
* Site header: below 1200px wide, on phones and on iPads in either orientation, a signed-in reader's header is one row: the brand, the account picture and a Menu button. The button opens a panel under the bar that lists, in the order of the wide row, the navigation, Need help? where the handbook assistant is available, the Student Report Card and the Mentor Report Card the reader can open, and Log out, which a phone did not show before; the page being viewed is marked, and each entry is a 44px row. The button, Escape, a tap or click outside, a link or Need help? closes the panel; Escape, and the assistant closing, put the focus back on the button. Opening it does not move the page. Without JavaScript the header keeps its wrapped row of links, and from 1200px the row is as before.
* Site header, signed out: on a phone the brand and Log in share one row, and the navigation's one link, to the home page, is left out there, since the logo and the site title lead home. The Log in button there is the smaller one its phone rule always meant, 14px with 9px and 14px of padding, so the bar is 62px tall.
* Site header: where no logo is uploaded, the WordPress mark's link is named with the site's name for screen readers ("Home" on a site without a name), as an uploaded logo's link already is.
* For developers: `bin/test-header-js.php` runs the header's scripts on the stand-in page, and `bin/check-selectors.php` also checks that every plugin class the theme's scripts read is one the plugin sets.
```

- [ ] **Step 2: The template, last**

Run: `sh bin/make-pot.sh` (WP-CLI is installed: the canonical path). Expected: `Success: POT file successfully generated.`; `grep -A1 'inc/header.php' languages/wpcredits-theme.pot` shows `msgid "Menu"` and `msgid "Home"`; `grep 'Project-Id-Version' languages/wpcredits-theme.pot` reads `WPCredits 1.24.20`; `grep -c '^msgid' languages/wpcredits-theme.pot` is 428 (426 on the base).

- [ ] **Step 3: The battery, and the commit**

Run the theme's checks: as at the end of Task 3. Then:

```bash
git add style.css readme.txt languages/wpcredits-theme.pot
git commit -m "1.24.20: the version, the readme's chrome section, the changelog and the translation template for the site header's menu button below desktop width" -m "$COAUTHOR"
```

- [ ] **Step 4: The zip's contents**

```bash
git archive --format=zip --prefix=wpcredits-theme/ HEAD -o "$SCRATCH/wpcredits-theme-1.24.20.zip"
unzip -p "$SCRATCH/wpcredits-theme-1.24.20.zip" wpcredits-theme/style.css | grep ^Version:
unzip -Z1 "$SCRATCH/wpcredits-theme-1.24.20.zip" | grep -c '^wpcredits-theme/bin/'
unzip -Z1 "$SCRATCH/wpcredits-theme-1.24.20.zip" | grep -E '^wpcredits-theme/(inc/header\.php|assets/js/header-menu\.js|assets/fonts/inter/inter-latin\.woff2)$'
```

Expected: `Version: 1.24.20`; `0`; the three paths, the hub's fonts still in the zip.

---

### Task 5: The trial on the Studio copy at localhost:8883 (the controller's)

On the Studio copy at http://localhost:8883 (`~/Studio/wpcredits-t5`), hardened: its safety must-use plugin holds every email in `wp-content/local-safety-mail.log`, refuses Airtable and every request off the machine, and cron is off. Everything happens inside a snapshot that is put back at the end. The readers are throwaway accounts made inside the snapshot, each at a plus-address of the owner's one test mailbox (the vault names it; it is not written here). Never sign in with a password: each reader's page is rendered on the server as that reader and opened as a file the copy serves. Never press Ask in the assistant (its questions go to an outside service). Scripts and captures live in `.superpowers/sdd/2026-10-16-theme-header-menu-1-24-18/trial/` in the plugin's repository (the release's working folder, ignored, never mirrored); back them up there before the scratchpad is swept.

```bash
export SITE=~/Studio/wpcredits-t5
export WP="$HOME/.local/bin/studio wp --path $SITE"
export TRIAL=~/GitHub/wpcredits-program-manager/.superpowers/sdd/2026-10-16-theme-header-menu-1-24-18/trial
export SNAP=~/GitHub/wpcredits-program-manager/.superpowers/session-backups/$(date +%F)/copy-8883-before-trial-1.24.20
export KIT=~/GitHub/wpcredits-program-manager/.superpowers/session-backups/2026-10-16/sdd-resend-invitation-1-122-30/trial
export SCRATCH="<the session's scratchpad>/theme-1-24-20"
mkdir -p "$TRIAL" "$SNAP" "$SCRATCH"
```

- [ ] **Step 1: Read the copy, and stop if it is not as expected**

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8883/
$WP theme get wpcredits-theme --field=version | tee "$SNAP/theme-version.txt"
$WP plugin get wpcredits-program-manager --field=version | tee "$SNAP/plugin-version.txt"
ls -la "$SITE/wp-content/database/" "$SITE/wp-content/mu-plugins/"
grep -c "DISABLE_WP_CRON" "$SITE/wp-config.php"
$WP eval '$s = WPCPM_Settings::get(); echo ( "" === (string) ( isset( $s["api_token"] ) ? $s["api_token"] : "" ) && "" === (string) ( isset( $s["schema_token"] ) ? $s["schema_token"] : "" ) ) ? "tokens blank\n" : "A TOKEN IS SET\n";'
$WP eval 'echo has_filter( "pre_wp_mail" ) ? "mail held\n" : "MAIL NOT HELD\n";'
wc -l < "$SITE/wp-content/local-safety-mail.log" | tee "$SNAP/mail-log-lines.txt"
```

Expected: `200`; the copy's theme and plugin versions, recorded (the theme of its last trial and a 1.122 plugin; the "before" captures and the restore use what it holds); the database folder holding `.ht.sqlite`; the safety plugin among the must-use plugins; `1`; `tokens blank`; `mail held`; a line count. Never print a token. Any other answer: stop and tell the owner.

- [ ] **Step 2: The snapshot and the error logger**

```bash
(cd "$SITE/wp-content/themes" && zip -qr "$SNAP/theme-before.zip" wpcredits-theme)
~/.local/bin/studio site stop --path "$SITE" > /dev/null 2>&1
cp "$SITE/wp-content/database/.ht.sqlite" "$SNAP/ht.sqlite"
~/.local/bin/studio site start --path "$SITE" > /dev/null 2>&1
cp "$KIT/wpcpm-trial-errors.php" "$SITE/wp-content/mu-plugins/"
```

Start's output is discarded: it prints the local Administrator's password. `wpcpm-trial-errors.php` (the 1.122.30 trial's) sends every PHP error raised from the plugin's or the theme's files to `wp-content/trial-php-errors.log`.

- [ ] **Step 3: The readers and the capture script**

`$TRIAL/trial-header-readers.php` (`$WP eval-file`, the mailbox as its argument) makes six accounts, `header-trial-student`, `-mentor`, `-both`, `-admin`, `-institution`, `-sponsor`, with the roles `wpcpm_student`, `wpcpm_mentor`, both of those, `administrator`, `wpcpm_institution` and `wpcpm_sponsor` (`wp_insert_user()` mails nobody), and prints one line of JSON: each account's ID and, as that account, `WPCPM_Students_Dashboard::is_student()`, `wpcredits_viewer_is_mentor()`, `current_user_can( WPCPM_Roles::CAP_MANAGE )` and `wpcredits_handbook_available()`, which say the entries its menu should hold. `$TRIAL/trial-header-capture.php <user ID or 0> <path> <name>` starts from the 1.24.15 trial's `.superpowers/session-backups/2026-10-15/hub-preview-8883/capture-hub.php` without its `show_admin_bar` filter: it refuses to run unless `siteurl` is `http://localhost:8883`, sets the current user and the request's URI and host, refuses every redirect, runs `$GLOBALS['wp']->main()` and the template loader inside an output buffer, and writes `wp-content/uploads/trial-1-24-20/<name>.html`. WordPress then draws the admin bar for a reader whose toolbar setting is on, the default for a new account, as it would for that reader; the trial records, for each capture, whether the bar is drawn, since the panel's height cap and the 601px to 782px offset read its height. The pages: `/` for each of the seven readers (signed out is 0), `/student-dashboard/` as the student and `/mentor-dashboard/` as the mentor.

- [ ] **Step 4: The pages before, then the build installed**

Capture every page on the copy's theme into `trial-1-24-20/before/`. Then build from the branch: `git -C ~/GitHub/wpcredits-theme/.worktrees/header-menu-1-24-20 archive --format=zip --prefix=wpcredits-theme/ HEAD -o "$SCRATCH/wpcredits-theme-1.24.20.zip"`; `$WP theme install "$SCRATCH/wpcredits-theme-1.24.20.zip" --force`; the version reads 1.24.20; capture every page again.

- [ ] **Step 5: Measure every width and every gap**

A measure page in the same uploads folder (same origin) loads each capture in iframes of fixed width, 320, 375, 390, 414, 640, 641, 768, 820, 900, 1024, 1099, 1100, 1199, 1200 and 1280px, and records, closed and then with the button pressed: the header part's height and `--wpc-header-live`; the rows of the bar; the document's width beyond the window; whether the Menu button and the navigation's own toggle are displayed and how many buttons look like a menu button; the boxes of the brand, the picture and the button, and the title's lines; open, the panel's top against the bar's foot, its padding, each entry's box, type size and hairline, the entries' order, and the tab order (the focusable elements displayed in the header). Run it through headless Chrome (`--dump-dom`) or the built-in browser and keep the JSON. Expected, the scratch render's values while this plan was written:

| | 320 to 359 | 360 to 640 | 641 to 1199 | 1200 and 1280 |
| --- | --- | --- | --- | --- |
| Signed in, closed | one row, 82px (the title on three lines; the owner's answer 1) | one row, 65px | one row, 77px | the "before" row, each item's box equal to the pixel |
| Button | 44px square, its right edge 20px from the window's | the same | 40px from the edge | not displayed |
| Picture | 34px, 12px left of the button, its middle level with the button's | the same | the same | as before |
| Brand to picture | at least 12px | the same | the same | |
| Open | the bar's height and `--wpc-header-live` unchanged; the panel's top at the bar's foot, under its rule; 4px above the first row, 8px under the last; each row 44px and its hairline, Log out 44px and last; the rows' text at the gutter (20px, then 40px) | | | |
| Entries | Administrator and student-and-mentor 5; student 4; mentor 4; institution member and sponsor member 3, or 2 where the assistant is not theirs | | | |
| Tab order | closed: the logo (named with the site's name), the title, Menu; open: Menu, then the entries in order | | | the "before" order, the logo now named |
| Signed out | one row, the brand and Log in, no Homepage (82px at 320, 62px from 360); Log in 14px, about 68 by 41px, 12px from the brand and 20px from the edge | | as before (Log in 15px, 11px 22px) | as before |
| Everywhere | no width beyond the window, open or closed; never two menu buttons; the stacked mark absent in the menu layout | | | |

Where the copy differs (WordPress's 20px block gap or another print order there), record what it draws and say so in the report, gap by gap.

- [ ] **Step 6: A navigation with a submenu**

Inside the snapshot: find the navigation the header renders (`$WP post list --post_type=wp_navigation --fields=ID,post_name,post_status`, the one whose content holds the copy's Homepage link), keep its content in `$TRIAL/navigation-before.txt`, and replace it (`$WP post update <ID> "$TRIAL/navigation-three.html"`) with three items: its Homepage link, a custom link "About the program" to `http://localhost:8883/#about`, and a submenu "Programs" holding two custom links to `#credits` and `#campus`. Capture the Administrator's `/` and measure at 375 and 1024, closed and open: one menu button, the navigation's own toggle not displayed, the submenu expanded under "Programs" and indented 12px, every row 44px and its hairline, no width beyond the window; a focus ring on a submenu link drawn whole (by keyboard); then press the "Programs" toggle, close the menu, and confirm nothing in the panel is drawn and no element in it is hit by a tap. Put the navigation's content back from `navigation-before.txt` and capture `/` again: as in Step 5.

- [ ] **Step 7: The behavior, the keyboard and the readers' tools, in Chrome**

In the built-in browser, on the Administrator's capture at 375 and at 1024, and on the student's Student Report Card capture at 375 (the page marked in the menu): the button opens and closes; Escape from an entry closes and the focus ring is on Menu; a click on the page, and on the picture, closes; a link closes (stop the navigation with a listener on `window` in the bubble phase, which runs after the theme's, so the capture stays); Need help? closes the menu and opens the assistant, whose Escape and whose close button each put the focus on Menu (closed with the mouse, expect the focus on Menu even where the browser draws no ring there; expect the ring on the keyboard paths); Tab past Log out closes; Shift+Tab from the first entry reaches Menu with the menu open; every keyboard focus ring is visible (screenshots). Then, through Chrome's DevTools protocol from a small node script (node's own WebSocket, nothing installed): with scripts disabled (`Emulation.setScriptExecutionDisabled`), no Menu button and each page's header equal in height and links to its "before" capture without scripts; with `forced-colors: active` emulated (`Emulation.setEmulatedMedia`), at 375 open, the button's border and its close mark, the rows' lines and the current row's underline are drawn; the accessibility tree (`Accessibility.getFullAXTree`) holds the logo link named with the copy's site name, one button named "Menu", collapsed, and none of the panel's entries while closed, and expanded with the five entries while open; signed out, the logo link is named the same at 375 and 1280.

- [ ] **Step 8: The same in WebKit: Safari and the iOS Simulator**

In Safari on macOS, Responsive Design Mode at 375, 820 and 1024, and in Mobile Safari in the iOS Simulator (an iPhone at 390 wide, and an iPad in portrait and in landscape), on the Administrator's capture served by the copy: open; close by the button; Escape (a hardware keyboard); a tap on plain page text; a tap on the picture; Need help? and the hand-back to Menu; a press of Menu while focus is in the panel (the menu closes once and does not open again); the picture drawn whole and level with the button, open and closed, and while the open panel is scrolled inside itself (a short window or the phone held sideways); no width beyond the window. Then a VoiceOver pass in macOS Safari, by the owner or with the owner's leave: the logo link read with the site's name, the button read as "Menu", collapsed, then expanded; the entries read only while the menu is open. No pass on a real phone (the owner's answer 4). If a tap on plain text does not close the menu in Mobile Safari, stop and report it: the `pointerdown` listener is there for exactly that.

- [ ] **Step 9: Screenshots**

Every reader at 375, 820 and 1024, closed and open, and the Administrator at 1280, into `$TRIAL/shots/`; the submenu navigation at 375 and 1024, open; Mobile Safari at 390, open.

- [ ] **Step 10: Put the copy back**

```bash
~/.local/bin/studio site stop --path "$SITE" > /dev/null 2>&1
cp "$SNAP/ht.sqlite" "$SITE/wp-content/database/.ht.sqlite"
~/.local/bin/studio site start --path "$SITE" > /dev/null 2>&1
$WP theme install "$SNAP/theme-before.zip" --force
cat "$SITE/wp-content/trial-php-errors.log" 2>/dev/null | wc -l
rm -f "$SITE/wp-content/mu-plugins/wpcpm-trial-errors.php" "$SITE/wp-content/trial-php-errors.log"
rm -rf "$SITE/wp-content/uploads/trial-1-24-20"
$WP theme get wpcredits-theme --field=version
$WP user list --search='header-trial-*' --field=ID | wc -l
wc -l < "$SITE/wp-content/local-safety-mail.log"
```

Expected: the error log holds `0` lines before it goes; the theme reads the version in `theme-version.txt`; `0` trial accounts; the mail log as long as `mail-log-lines.txt` (nothing was sent); Step 1's checks read the same again. Report to the owner: the measurements by width and reader, every gap, the submenu navigation's, WebKit's and VoiceOver's results, the screenshots at 375, 820 and 1024, what differed from the table and why, and the deferred-script window (Where the spec left a choice).

---

## After the plan

- **The merge and the release** are not this plan's: after the final review and the trial, on the owner's yes, the branch merges into theme main (`Merge 1.24.20: below 1200px a signed-in reader's header is one row with a Menu button holding the links; signed out, a phone shows the brand and Log in on one row`), and the release runs through `~/Projects/wpcredits-release` (check, build, scan, mirror, deploy), the live version read first (1.24.19 expected), every outward step on the owner's yes for it. The worktree is removed and the merged branch deleted afterwards.
- **For the plugin's next release:** `assets/js/handbook.js` says the launcher and the panel meet "through one data attribute and nothing else"; the theme's header now also reads `wpcpm-hb-open`, and the comment should say so. Not changed here (no plugin change).
- **Not in this release** (spec 2, decision 3, and 5): the pathway step cue; the desktop header; the WordPress.org bars; the plugin's assistant panel.
