# Theme 1.32.9: the two buttons in a signed-in reader's Menu panel, and the site title's lead word

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal (the title half dropped on 27 October 2026, see Task 3):** A signed-in reader gets "Become a sponsor" and "Partner as an institution" as rows in the header's Menu panel below 1200px, while the wide signed-in bar from 1200px stays as it is; and the site title "WordPress Education Directory" shows "WordPress" wherever the header has room, and hides it visually, still read by screen readers, where the bar would otherwise not fit on one line.

**Architecture:** The buttons are the hooked blocks `inc/partners.php` already adds for a visitor; for a signed-in reader they are hooked too, on the front end only, with a class that keeps them out of everything but the open Menu panel. The site title is printed through `wpcredits_accent_word()`, which wraps the words before the accent word in a span of their own; style.css hides that span visually inside the width ranges measured to need it.

**Tech Stack:** PHP 7.4+, WordPress 7.1 block theme; the theme's `bin/test-*.php` suites; headless Chrome measurements on the Docker copy of the site.

**Spec:** the product owner's answers of 27 October 2026, in chat. Buttons: "In the Menu panel only: Signed-in readers below 1200px (phones, tablets, small laptops) get both buttons as rows in the Menu panel, where there is room; the wide signed-in bar from 1200px stays as it is, on one line." Title: the site's name becomes "WordPress Education Directory" (the product owner: "Change it to WordPress Education Directory", then "Also the site title"), with "Drop "WordPress" where it doesn't fit".

## Global Constraints

- Version: theme 1.32.9 (live is 1.32.8). Task 1 of this release, already on the branch (`054f827`), moved the homepage's sign-in section last and changed the Partners defaults; it is not part of this plan's work.
- The bar stays on one line at every width for every reader, and the page never scrolls sideways (theme 1.32.6, the product owner: "never display items in two lines, make the width elastic").
- The Site Editor is never offered the hooked buttons: a navigation menu saved there with them would hold them as plain items for every reader at every width.
- No em or en dash anywhere; US English; full product names; never "Education Hub" in text people read; no person's name in code, tests, docs or commit messages (say "the product owner").
- Every theme suite stays green (9 suites: `for f in bin/test-*.php; do php "$f"; done`).
- Measured on the Docker copy only, http://localhost:8900, whose site title was "WordPress Education Directory" for Task 2's measurements and was set back to "Education Directory", live's, when Task 3 was dropped; nothing touches the live site.

## Review Focus

1. A signed-in reader from 1200px up: the bar exactly as 1.32.8 draws it (no button), on one line, at 1200, 1235, 1280, 1366, 1440 and 1920px.
2. A signed-in reader below 1200px with JavaScript: both buttons in the open panel, right after Partners, drawn as the panel already draws a button's row (style.css, the `.wpc-nav__partner` rules under `.wpc-js .wpc-header--menu`); the closed panel adds nothing to the bar.
3. Signed in without JavaScript, where there is no panel: neither button anywhere.
4. A signed-in Administrator's REST request for the navigation (what the Site Editor reads) and any wp-admin request: neither button hooked.
5. (Task 3's, dropped.) Where "WordPress" is hidden, the header link's accessible name and the document title still say "WordPress Education Directory"; at no width from 320 to 1920px does the name wrap or the bar overflow, for a visitor, a signed-in Administrator and a signed-in student.

---

### Task 2: The two buttons in a signed-in reader's Menu panel

**Files:**
- Modify: `inc/partners.php` (`wpcredits_partners_menu_item()`, `wpcredits_partners_menu_item_block()`, `wpcredits_partners_sponsor_block()`, and the docblocks that say the buttons are a visitor's alone: the one above `wpcredits_partners_menu_item()` and the one above `wpcredits_partners_menu_attrs()`)
- Modify: `style.css` (the wide bar's rules for `.wpc-nav__partner`, and the menu layout's rules for it under `.wpc-js .wpc-header--menu` inside `@media screen and ( max-width: 1199px )`)
- Modify: `readme.txt` (the description of the header's menu, if it says the buttons are for visitors only)
- Test: `bin/test-partners.php` and `bin/test-header.php` (find the checks that pin the visitor-only hook with `grep -n "is_user_logged_in\|signed\|visitor" bin/test-partners.php bin/test-header.php`)

**Interfaces:**
- Produces: for a signed-in reader on the front end, the hooked types at `last_child` of `core/navigation` are the Partners link (while the levels are public), `WPCREDITS_SPONSOR_LINK_HOOK` and `core/navigation-link`, in that order, as for a visitor; during a REST request or a wp-admin request, a signed-in reader is hooked the Partners link alone, as in 1.32.8.
- Produces: a signed-in reader's hooked buttons wear `wpc-nav__partner wpc-nav__panel-only`; a visitor's wear `wpc-nav__partner`, as in 1.32.8.

- [ ] **Step 1: Write the failing tests.**
  - Signed in, front end: the three hooked types above, in order; the two buttons' `className` is `wpc-nav__partner wpc-nav__panel-only`.
  - Signed in, `wp_is_serving_rest_request()` true (and, separately, `is_admin()` true): the Partners link alone (**Review Focus 4**).
  - Signed out: unchanged from 1.32.8, `className` `wpc-nav__partner` (**Review Focus 1**: nothing new for visitors).
  - style.css: `.wpc-nav__panel-only` is not displayed anywhere outside the menu layout (one rule, outside every query, so it holds in the wide bar and without JavaScript), and is displayed as the panel's rows only by a rule under `.wpc-js .wpc-header--menu` inside the 1199px screen query, weighed to win over the hiding rule (**Review Focus 1, 2, 3**). Pin it the way `bin/test-header.php` pins the other menu-layout rules (its helpers read a rule by selector and at-rule).
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement.** In `wpcredits_partners_menu_item()`, hook the two buttons for a visitor, or for a signed-in reader when the request is neither a REST request (`wp_is_serving_rest_request()`, falling back to `defined( 'REST_REQUEST' ) && REST_REQUEST` where the function is missing) nor `is_admin()`. The two block filters add `wpc-nav__panel-only` when the reader is signed in. Rewrite the docblocks to say why: the buttons ask someone with no account yet, so the wide signed-in bar keeps its one line without them, and below 1200px the panel has room (the product owner, 27 October 2026); the Site Editor is used signed in and reads the menu over REST, where they are never hooked, so a menu saved there never holds them.
- [ ] **Step 4: Measure on the Docker copy** (theme installed from this branch's zip: `zip` the worktree with `git archive`, install with `bin/wp theme install <absolute path under ~/Projects/wpcredits-docker/release/> --force`): a signed-in Administrator and a signed-in student, at 375, 600, 782, 961, 1100, 1199 with the panel open and closed, and at 1200, 1280, 1440: the panel's rows (each 44px, the buttons in the brand blue at the button's weight, as the existing rule says), the closed bar unchanged, the wide bar identical to 1.32.8's (compare item boxes). Screenshots of the open panel at 375 and 1100.
- [ ] **Step 5: Run every suite; commit.** "Header: a signed-in reader's Menu panel holds Become a sponsor and Partner as an institution below 1200px"

### Task 3: The site title's lead word, hidden where it does not fit (DROPPED)

> **Dropped on 27 October 2026.** The product owner, after seeing the full name in the header: "Skip the WordPress, does not look good", and the site's title stays "Education Directory". Nothing of this task is built; the steps below are kept as the plan was written.

**Files:**
- Modify: `inc/template-tags.php` (`wpcredits_accent_word()` and its docblock)
- Modify: `style.css` (the `.wpc-brand__name` rules, the 640px block's, and new rules for `.wpc-brand__lead` in the queries the measurement gives)
- Check: `inc/login.php` and `assets/css/login.css`, which print the title through the same function: the login page keeps the full name unless the measurement shows it overflows there
- Test: `bin/test-header.php` (the phone clamp check, which reads the name as "Education Directory", 255.7px at 30px, beside the signed-out reserve of 169.8px and the signed-in 142px), `bin/test-hub.php` (the accent word on a translated title), and the function's own checks wherever the suites hold them (`grep -n "accent_word" bin/*.php`)

**Interfaces:**
- Produces: `wpcredits_accent_word( 'WordPress Education Directory' )` returns `<span class="wpc-brand__lead">WordPress </span><em>Education</em> <i>Directory</i>`: the lead and the space after it in one span, so hiding it leaves no gap; no span when nothing comes before the accent word; everything escaped as today.

- [ ] **Step 1: Measure first, with 1.32.8 as installed on the Docker copy.** Run the one-line sweep (`node sweep.mjs <cookie file or -> <path> <widths>`, in the trial tools' folder the coordinator names) for a visitor, a signed-in Administrator and a signed-in student, on / and /partners/, at every width of `sweep-all.sh`'s list, then at 1px steps around every edge where the name wraps or the bar overflows. Write the ranges in the ledger. The expected shape, to be confirmed: phones up to about 480px for every reader, and a visitor's bar from 961px to somewhere near 1100px.
- [ ] **Step 2: Write the failing tests.** The function's output for: the full title (the Interfaces line); a title starting with the accent word (no span); a title without it (plain, as today); a translated accent word; a lead holding `&` and `<` (escaped inside the span). style.css: `.wpc-header .wpc-brand__lead` visually hidden (position absolute, 1px by 1px, clipped, `overflow: hidden`, `white-space: nowrap`), never `display: none` and never `visibility: hidden`, inside exactly the queries the measurement gave, each query's comment naming the measured edges; outside them, nothing hides it. The phone clamp check: where the lead is hidden the name is "Education Directory" as now; where it shows, the full name's measured width beside the reserves fits.
- [ ] **Step 3: Run them red; implement.** Queries in px. Keep the 1.32.6 phone clamp as it is for the shortened name.
- [ ] **Step 4: Sweep again** with this branch installed: the same readers, pages and widths, plus 1px steps around each query edge: the name on one line, the bar on one line, no sideways scroll, everywhere. Read the header link's accessible name (`computedName` through the DevTools protocol's `Accessibility.getPartialAXTree`, or the link's text content with the span included) at a width where the lead is hidden: it says the full name (**Review Focus 5**). Screenshots of the header for a visitor and an Administrator at 375, 961, 1024, 1100, 1200 and 1440.
- [ ] **Step 5: Run every suite; commit.** "Header: the site title shows WordPress where there is room and hides it, still read aloud, where the bar would not fit"

### Task 4: Version, review, trial, release (the coordinator)

- [ ] 1.32.9: `style.css` header, `WPCREDITS_VERSION`, readme Stable tag and changelog (Task 1's two changes and Tasks 2 and 3), `languages/wpcredits-theme.pot`.
- [ ] A Codex second opinion on the branch's diff.
- [ ] `release.py` check, build, scan; deploy and mirror only on the product owner's yes. The site's title stays "Education Directory" (Task 3 dropped).
