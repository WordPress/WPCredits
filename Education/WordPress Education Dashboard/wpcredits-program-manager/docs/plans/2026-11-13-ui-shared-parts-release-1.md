# One set of interface parts, release 1 (the parts, and the Administrator Dashboard on them): Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the shared parts (one stylesheet, one script, one class of PHP helpers, one switch per surface) and move every view, card and block of the Administrator Dashboard onto them, while every other surface stays exactly as it is.

**Architecture:** `assets/css/parts.css` and `assets/js/parts.js` load on both sides of the site; `WPCPM_UI` holds the helpers that print each part and the switch that says, per surface, whether a renderer prints the parts' branch or its old markup. Every renderer of the Administrator Dashboard gains a parts' branch behind `WPCPM_UI::on()` while the `administrator` surface is off; one task turns it on and deletes what only that surface used. The theme's 1.32.28 follows, deleting the rules the plugin no longer matches.

**Tech Stack:** WordPress plugin PHP (stand-alone suites run as `php bin/test-*.php` from the plugin root, with stand-ins for WordPress); node harnesses in `bin/js/` run by a PHP suite (as `bin/test-dashboard-card.php` runs `bin/js/dashboard-open.js`); the guide's builder (`php bin/build-docs.php`, run twice); a picture and measure rig under `bin/ui/` (node, `playwright-core` with Chrome, `ffmpeg`) for the controller's gate on the local Docker copy.

**Spec:** `docs/specs/2026-11-13-ui-shared-parts-design.md` (head `feac11ae`). Section numbers below ("design 2.6") are that document's.

**Branch and worktree:** `feature/ui-shared-parts` in `.worktrees/ui-shared-parts`. **Plugin version: 1.123.92** and **theme version: 1.32.28**, unless a read of live, main and every branch at Task 19 and Task 20 shows them taken; then the next free (1.123.91 is reserved).

## Global Constraints

- Copy: plain hyphens only, never an em or en dash, in strings, comments, docs, tests and commit messages. "Administrators" is the one name for the people who run the program; "program manager" appears nowhere added here. Full product names ("Administrator Dashboard", "Sponsor Dashboard", "Institution Dashboard", "Student Report Card", "Mentor Report Card"). Never "out of force" in a new string. US English.
- No people's, schools' or companies' real names in code, tests, fixtures, the rig or docs: invented names and `example.org` / `example.com` addresses only. No personal paths or accounts in committed files: a local tool is found through an environment variable with a default built from the home folder.
- Every form posts the same field names, values, nonce actions and `action` as before. Field `name` attributes never change. A field id that repeats on one page today may become unique per item (design 9); every anchor and id a mail, redirect, link, guide or script points at stays where it is.
- A surface that is off changes by nothing: not its markup, not a pixel, not a hover. Until Task 18, `WPCPM_UI::LIVE` is empty and every page of the site renders as before.
- New looks of the existing shared classes `.wpcpm-btn` and `.wpcpm-status` apply only under `.wpcpm-ui-on` (design 3.1).
- `parts.css` styles only the parts' own classes (`wpcpm-ui-*`) and the frame's `.wpcpm-btn` / `.wpcpm-status` it takes over; never an element by type, never another legacy class; one `!important` (`[hidden]`), no doubled classes.
- Values are the design's (sections 2.1 to 2.13), copied, not re-derived: the rhythm 6, 14, 16, 24, 22, 10 and 12; 40px controls; 56px and 44px fold heads; Blueberry `#3858e9`, Deep Blueberry `#213fd4`, tint `#eff2ff`, red `#cc1818`, red tint `#fff6f5`; 2px corners.
- New strings are translatable (`__()`, `esc_html__()`, `_n()`), with a `/* translators: */` comment where they hold a placeholder.
- Standards: `PATH="$HOME/Projects/wpcredits-release/local-bin:$PATH" sh bin/check-standards.sh` reports 0 errors and no more than 177 warnings. Static analysis (the release script's check) reports no more than 133; no string callbacks: closures.
- Every suite passes before each commit: `for t in bin/test-*.php; do php "$t" > /tmp/out.txt 2>&1 || { echo "FAIL $t"; tail -20 /tmp/out.txt; }; done` prints nothing. When a `docs/sections` file changed: `php bin/build-docs.php` twice, then `php bin/test-guide-labels.php`, `php bin/test-guide-jobs.php`, `php bin/test-build-docs.php`, `php bin/test-administrator-guide.php`.
- Tests first: every behavior and every printed part gets a check that fails before the code is written, in the suite the task names, in the repository's style (a plain PHP script, `ck( $label, $actual, $expected )` printing `ok   ` or `FAIL `, a stand-in for each WordPress function it needs, exit 1 when any check failed).
- Git: never `git add -f`; nothing under `.superpowers` staged; no `git stash`; no push; one commit per task with the message the task gives.
- Implementers never use Docker, ssh, the live site, Airtable or Slack, never run the picture rig against a site, never dispatch subagents. Running the rig is the controller's job (the last section).

## Review Focus

1. **A refused note comes back:** an Administrator who presses "Reject the application" with a reason the site refuses (or a question too long) sees, after the page reloads, that same application's panel open, the typing kept, the refusal sentence tied to the box and the focus on it; no other application's panel is open. Pinned in Task 10 (with `decide()`'s `open` in Task 7).
2. **Two items on one card:** with two applications (or two requests) on the Overview, the second item's opener opens the second item's panel, its focus lands in the second item's box, and its confirming button posts the second item's ID; ids, `aria-controls` and `for` never repeat. Pinned in Task 7 (unique ids) and Task 10 (two applications posting their own IDs).
3. **No script, or a script that failed to load:** every decision is still possible: each panel and its confirming button are on the page and post, nothing a person needs is `hidden`, and no Cancel button that does nothing is shown. Pinned in Task 7 (the server's markup) and Task 8 (what the script changes).
4. **A link from an email or a decision's way back** (`#wpcpm-applications`, `#wpcpm-requests`) opens that card, prints the decision's outcome at the top of its body, and keeps "How to do this" reachable by keyboard and screen reader at 1280 and at 390. Pinned in Task 9.
5. **Every other surface is untouched in release 1:** the Sponsor Dashboard's buttons and tags look and hover as before, an institution application decided on the wp-admin Institutions screen prints its old form, and the Mentor and Student Report Cards print "Tools from our sponsors" and the events' actions as before. Pinned in Task 3 (the scoping under `.wpcpm-ui-on`), Task 10 and Task 16 (old branches asserted off the surface), and the controller's pixel-equal pictures.

---

### Task 1: Start from main at 1.123.90

**Files:**
- Modify: the branch, by a merge of `main` (commit `4c3391e8`, 1.123.90).

**Interfaces:** none.

- [ ] **Step 1:** `git merge --no-edit 4c3391e8`. Expected: no conflict (1.123.90 changed none of the files this plan touches: `includes/mail/*`, `class-wpcpm-mentor-assigned.php`, `class-wpcpm-mentors-sync.php`, `class-wpcpm-mentors.php`, settings files).
- [ ] **Step 2:** Re-verify the lines this plan cites: `grep -n "function render_application(" includes/modules/class-wpcpm-administrators-cards.php` answers 2249; `grep -n "function render_decision_form(" includes/modules/class-wpcpm-institutions.php` answers 4284; `grep -n "public static function open(" includes/class-wpcpm-dashboard-card.php` answers 461. If any differs, note the new lines in the task's report; later tasks find functions by name.
- [ ] **Step 3:** Run every suite (Global Constraints). Expected: all pass.
- [ ] **Step 4:** The merge commit is the commit (its default message).

### Task 2: The picture and measure rig, under `bin/ui/`

The controller's gate (the last section) must be repeatable by anybody with the local Docker copy. This task brings the prototype's rig into the repository, cleaned for a public repository, and adds a comparer. It ships nowhere: `bin/` is in `.distignore`, which `bin/build` reads to build the zip (`bin/build:43-63`, every non-comment line excluded at the root and below).

**Files:**
- Create: `bin/ui/copy.mjs` (the site guard and WP-CLI), `bin/ui/copy.sh` (prepare and restore the copy), `bin/ui/wpcpm-ui-rig.php` (the localhost-only must-use plugin: Airtable stand-ins, invented names in place of real ones), `bin/ui/real-names.php` (collects names to refuse), `bin/ui/seed.mjs` (invented items through the site's own forms), `bin/ui/scenes.mjs` (every screen and open state), `bin/ui/snap.mjs` (capture), `bin/ui/measure.mjs` (computed styles), `bin/ui/compare.mjs` (two picture sets), `bin/ui/README.md` (how the controller runs it, in five lines).
- Source to read, not to copy blindly: the prototype's rig outside this branch (`bin/clips/copy.mjs`, `copy.sh`, `snap.mjs`, `shots/_lib.mjs`, `wpcpm-filming.php`, `real-names.php` in the guides worktree, and the prototype's `measure.mjs`), which the controller hands the implementer as files.
- Test: `bin/test-ui-rig.php` (new), with `bin/js/ui-rig.js` (new) run by it.

**Interfaces:**
- Produces, `bin/ui/copy.mjs`: `export const BASE = 'http://localhost:8900'`; `export function refuseUnlessCopy(base): void` (throws `refusing: ...` unless `base === BASE` and the copy's `siteurl` and `home` both equal `BASE`); `export function wp(args, { all }): string`; `export function cookiesFor(login): Array<cookie>`. WP-CLI is found at `process.env.WPCPM_DOCKER || join(homedir(), 'Projects/wpcredits-docker')` + `/bin/wp`.
- Produces, `bin/ui/scenes.mjs`: `export const SIZES = [{ width: 1280, height: 800, scale: 1 }, { width: 390, height: 844, scale: 2 }, { width: 768, height: 1024, scale: 1 }]` (768 is used only for release 2's wp-admin tabs; release 1 uses the first two); `export function scenes(): Array<{ area, screen, state, path, login?, act?, press? }>` covering: every view of the Administrator Dashboard (Overview closed, all open, each card open alone, each Decide panel open, Watch closed and all open with each card, The program, the cohort timeline view); every other surface as the prototype's rig lists them (Institution Dashboard as an Administrator and as a representative, every Sponsor Dashboard view, both Report Cards, every wp-admin tab of every plugin screen, the Administrator guide's first screen). File names: `<area>-<screen>-<state>-<width>.png`, unique.
- Produces, `bin/ui/snap.mjs`: `node bin/ui/snap.mjs --out <folder> [--only <comma list>] [--skip-seed]`; full-page PNGs, Escape pressed and the pointer parked at 0,0 before each, a capture taller than 16,000px cut into `-part1`, `-part2`; a page whose text holds a name from `real-names.php`'s list is not saved and is listed under "Could not capture". Playwright from `process.env.WPCPM_PLAYWRIGHT || join(homedir(), 'Projects/wpcredits-release/a11y/node_modules/playwright-core/index.mjs')`, `chromium.launch({ channel: 'chrome', headless: true })`.
- Produces, `bin/ui/measure.mjs`: `node bin/ui/measure.mjs --out <file.json> [--only ...]`; per screen the counts design 8 item 4 names (button looks and heights, fold head heights open and closed, field heights, text box minimums, heading styles, every gap off the rhythm, row actions with a border), and a summary printed one line per screen.
- Produces, `bin/ui/compare.mjs`: `node bin/ui/compare.mjs <before folder> <after folder>` prints `same <file>` or `DIFFERS <file> (psnr <value>)` or `MISSING <file>` for each name in either folder, then `N differ`; exit 1 when any differs or is missing. Two images are the same when their sizes match and `ffmpeg -i a -i b -lavfi psnr -f null -` reports `average:inf`. Exported for the harness: `export function verdict(psnrLine: string, sizeA: string, sizeB: string): 'same'|'differs'`.
- Produces, `bin/ui/wpcpm-ui-rig.php`: returns at once unless `get_option( 'siteurl' )` and `get_option( 'home' )` are exactly `http://localhost:8900`; defines `WPCPM_UI_RIG`.

- [ ] **Step 1: Write the failing checks** in `bin/test-ui-rig.php` (reading files, nothing run against a site):
  - `.distignore` holds a line `bin`, and `bin/build` reads `.distignore` (the zip carries nothing of `bin/ui/`).
  - every file in `bin/ui/` exists as listed above.
  - no file in `bin/ui/` holds `/Users/`, `@a8c.com`, `a8c` or a person's surname from the prototype's refuse list; the refuse list is read from the copy at run time, never written in a file.
  - `copy.mjs` holds `http://localhost:8900` exactly once as `BASE`; `wpcpm-ui-rig.php` and `real-names.php` both return or exit unless both `siteurl` and `home` equal it.
  - `snap.mjs`, `measure.mjs` and `compare.mjs` call `refuseUnlessCopy(` before any `chromium.launch(` (compare needs no site and does not call it).
  - node runs `bin/js/ui-rig.js`, which imports `scenes.mjs` and `compare.mjs` and checks: every scene's file names are unique at each width; the Administrator Dashboard's scenes include the cohort timeline view and every card listed in the plan's Tasks 9 to 17; the other surfaces' scenes include both Report Cards, every Sponsor Dashboard view and every wp-admin tab of design 10's list; `verdict('... average:inf ...', '1280x900', '1280x900')` is `'same'`, with `average:42.1` it is `'differs'`, with different sizes it is `'differs'`. The suite skips the node part with a `skip` line when `node` is not on the path, as `bin/test-admin-js.php` does.
- [ ] **Step 2: Run** `php bin/test-ui-rig.php`. Expected: fails (no `bin/ui/`).
- [ ] **Step 3: Implement** the files above from the prototype's rig: same capture logic, outputs to `--out` (a folder outside the repository or under `.superpowers/`, never committed), the hard-coded personal values replaced by environment variables with home-folder defaults, the prototype's cookie and `wpcpm-proto` loading removed, the measure's selectors widened to the parts' classes (`.wpcpm-btn`, `.wpcpm-ui-fold__head`, `.wpcpm-ui-field__control`, `.wpcpm-ui-h`, `.wpcpm-ui-decide`, `.wpcpm-ui-panel`) beside the old ones.
- [ ] **Step 4: Run** `php bin/test-ui-rig.php` and every suite. Expected: all pass.
- [ ] **Step 5: Commit:** `UI rig: pictures, measures and a comparer for the local copy, kept out of the zip`.

### Task 3: The parts' sheet, the switch and the root class, with every surface off

**Files:**
- Create: `assets/css/parts.css`, `includes/class-wpcpm-ui.php`.
- Modify: `wpcredits-program-manager.php` (require `includes/class-wpcpm-ui.php` beside `class-wpcpm-icons.php` at `:38`); `includes/modules/class-wpcpm-mentors-dashboard.php` (`register_assets()`, from `:285`: register `wpcpm-parts` and add it to `dashboard.css`'s dependencies, beside `dashicons`); `includes/class-wpcpm-dashboard-frame.php:51-59` (`dashboard-frame.css` depends on `wpcpm-parts`; register the parts if not registered); `includes/class-wpcpm-admin.php:201-235` (`wpcpm-admin` depends on `wpcpm-parts`); `assets/css/dashboard-frame.css` (the token block at `:15-36`, `.wpcpm-btn` at `:723-751` and `.wpcpm-status` at `:753-787` cut out, moved into `parts.css` value for value with the same selectors).
- Test: `bin/test-ui.php` (new), `bin/test-parts-css.php` (new); neighbors `bin/test-dashboard-card.php`, `bin/test-administrators-dashboard.php`, `bin/test-sponsors-dashboard.php`.

**Interfaces:**
- Produces, `WPCPM_UI` (final class):
  - `const LIVE = array();` the surfaces and wp-admin tabs that are on (empty until Task 18).
  - `const SURFACES = array( 'administrator', 'sponsor', 'institution', 'report-cards' );` wp-admin tabs are named `<page slug>/<tab>`.
  - `public static function enter( string $surface ): void`, `public static function leave(): void` (a stack: `leave()` returns to the surface named before).
  - `public static function surface(): string` ('' when none was named).
  - `public static function on(): bool` (true when `surface()` is in `live()`).
  - `public static function live(): array` (`self::LIVE`, unless `set_live()` replaced it).
  - `public static function set_live( ?array $surfaces ): void` (for the suites only; null puts `LIVE` back; production code never calls it).
  - `public static function root_class(): string` (`' wpcpm-ui-on'` when `on()`, else `''`).
  - `const STYLE = 'wpcpm-parts'`, `const SCRIPT = 'wpcpm-parts'`; `public static function register_assets(): void` (registers both once: `assets/css/parts.css` with no dependency, `assets/js/parts.js` with no dependency, footer, `WPCPM_VERSION`); `public static function enqueue_script(): void`.
- Produces, `parts.css`: the frame's tokens (`--wpcpm-f-brand` to `--wpcpm-f-green-tint`, `--wpcpm-f-sans`, `--wpcpm-f-serif`) on `:where( .wpcpm-frame, .wpcpm-dashboard, .wpcpm-wrap )`; the layout tokens (`--wpcpm-f-menu`, `--wpcpm-f-rail`, `--wpcpm-f-top`) stay in `dashboard-frame.css` on `.wpcpm-frame`. The added tokens of design 3.1 (`--wpcpm-f-green-border`, `--wpcpm-f-neutral`, `--wpcpm-f-line-card`, `--wpcpm-f-primary-hover`, the rhythm `--wpcpm-f-field` 14px, `--wpcpm-f-block` 16px, `--wpcpm-f-apart` 24px, `--wpcpm-f-module` 22px, `--wpcpm-f-fold` 10px, `--wpcpm-f-tie` 6px, `--wpcpm-f-gap` 12px). `.wpcpm-frame .wpcpm-btn`, `.wpcpm-btn--secondary` and `.wpcpm-status` with its tones exactly as they were. `[hidden]` inside the parts: `.wpcpm-ui-on [hidden] { display: none !important; }`. Nothing else yet.

- [ ] **Step 1: Write the failing checks.** In `bin/test-ui.php`: with `LIVE` empty, `on()` is false for every surface; `enter( 'administrator' )` then `surface()` is `'administrator'`; nested `enter( 'report-cards' )` then `leave()` returns to `'administrator'`; `set_live( array( 'administrator' ) )` makes `on()` true there and false after `enter( 'sponsor' )`; `root_class()` is `' wpcpm-ui-on'` only then; `set_live( null )` restores `LIVE`; `LIVE` in the file is `array()` (read the source: the constant's line). `register_assets()` registers `wpcpm-parts` once (a second call registers nothing) with the file `assets/css/parts.css`. With the registry stand-in of `bin/test-dashboard-card.php`: `WPCPM_Mentors_Dashboard::register_assets()` gives `dashboard.css` the dependencies `dashicons` and `wpcpm-parts`; `WPCPM_Dashboard_Frame::register_assets()` gives the frame's sheet the dependency `wpcpm-parts`; `WPCPM_Admin::enqueue_assets( 'toplevel_page_wpcpm' )` gives `wpcpm-admin` the dependency `wpcpm-parts`.
  In `bin/test-parts-css.php` (reads both sheets, comments stripped): every token, `.wpcpm-btn` and `.wpcpm-status` declaration that was in `dashboard-frame.css` at 1.123.90 is in `parts.css` with the same selector and value (the suite holds the old declarations as a fixed list); none of them is left in `dashboard-frame.css`; `!important` appears once in `parts.css`; no selector in `parts.css` starts with or is only an element name (`button`, `input`, `select`, `textarea`, `label`, `table`, `summary`, `details`, `a`); every selector names a `wpcpm-ui-` class, or is one of the moved frame selectors, or is scoped under `.wpcpm-ui-on`.
- [ ] **Step 2: Run** both suites. Expected: they fail (no class, no sheet).
- [ ] **Step 3: Implement** the class, the sheet and the three enqueue changes. Move the frame's rules with their selectors unchanged; do not change a value.
- [ ] **Step 4: Run** the two suites and the neighbors. Expected: all pass.
- [ ] **Step 5: Commit:** `Parts: the shared sheet and the surface switch, every surface off; the frame's tokens, button and tag moved into it unchanged`.

**The controller's check for this task alone** (run before Task 4 starts): with every switch off, `bin/ui/snap.mjs` on the copy with this commit, then `bin/ui/compare.mjs` against the baseline pictures of Task 1's commit: every picture of every surface `same`. A moved rule that now loses to a rule loaded between the two sheets shows here.

### Task 4: The small parts: button, tag, chip, heading, intro, action row

**Files:**
- Modify: `includes/class-wpcpm-ui.php`, `assets/css/parts.css`.
- Test: `bin/test-ui-parts.php` (new), `bin/test-parts-css.php`.

**Interfaces:**
- Consumes: `WPCPM_UI` (Task 3).
- Produces (each returns escaped markup; `side` is `'front'` or `'admin'`, default `is_admin() ? 'admin' : 'front'`):
  - `WPCPM_UI::button( array $args ): string`. Args: `label` (string), `kind` (`primary` default, `secondary`, `danger`, `quiet`, `quiet-danger`), `type` (`submit` default, `button`), `href` (prints an `a`), `name`, `value`, `form`, `confirm` (prints `data-wpcpm-confirm`), `controls` (prints `aria-controls` and `aria-expanded="false"`, or `"true"` with `expanded`), `expanded` (bool), `context` (words in a `screen-reader-text` span after the label, with a leading space inside the span), `aria_label`, `hidden` (bool), `id`, `side`. Front end classes: `wpcpm-btn`, plus `wpcpm-btn--secondary`, `wpcpm-btn--danger`, `wpcpm-btn--quiet`, or `wpcpm-btn--quiet wpcpm-btn--danger`. wp-admin classes: `button` (`button button-primary` for primary) plus `wpcpm-btn--danger` or `wpcpm-btn--quiet` (and both for `quiet-danger`).
  - `WPCPM_UI::tag( string $words, string $tone = '' ): string` (`<span class="wpcpm-status">`, with `wpcpm-status--red|amber|green` for a tone; any other tone is neutral).
  - `WPCPM_UI::chip( string $words ): string` (`<span class="wpcpm-ui-chip">`).
  - `WPCPM_UI::heading( string $text, string $level = 'h4', string $id = '' ): string` (`class="wpcpm-ui-h"`).
  - `WPCPM_UI::intro( string $text ): string` (`<p class="wpcpm-ui-intro">`).
  - `WPCPM_UI::actions( array $buttons, string $consequence = '' ): string` (`<div class="wpcpm-ui-actions">` holding the buttons' markup in the order given, then `<p class="wpcpm-ui-consequence">` when a consequence is given).
- Produces, `parts.css`: design 2.1 (every variant, size per side, states, pressed state with `aria-expanded="true"`, the row action rule as `.wpcpm-ui-list__act .wpcpm-btn--quiet` and `td.wpcpm-ui-cell--action .wpcpm-btn--quiet` with no side padding at rest, and the pressed quiet with 8px side padding and -8px side margin); design 2.2 tag tones and the topic chip; design 2.5; design 2.11's in-card heading (and `:first-child` taking no room above). Every rule that changes `.wpcpm-btn` or `.wpcpm-status` as the frame drew them (the secondary hover to `#213fd4` with white words, the neutral tag's `#e6e6e6` border, the green tag's `#8ac894` border, the danger and quiet variants' interplay with the frame's `.wpcpm-frame .wpcpm-btn`) is written under `.wpcpm-ui-on`.

- [ ] **Step 1: Write the failing checks** in `bin/test-ui-parts.php` (stand-ins: `esc_html`, `esc_attr`, `esc_url` as in `bin/test-dashboard-card.php`, `is_admin()` returning `$GLOBALS['admin']`):
  - `button( array( 'label' => 'Approve' ) )` on the front end is `<button type="submit" class="wpcpm-btn">Approve</button>`; in wp-admin `<button type="submit" class="button button-primary">Approve</button>`.
  - each kind's classes on both sides, as listed above; `kind` unknown falls back to primary.
  - `controls => 'p1'` prints `aria-controls="p1" aria-expanded="false"` and, with `expanded`, `aria-expanded="true"`; `type => 'button'` prints `type="button"`; `hidden` prints the `hidden` attribute.
  - `href` prints `<a class="..." href="...">`, escaped; a label with `<b>` is escaped.
  - `confirm => 'Reject it?'` prints `data-wpcpm-confirm="Reject it?"`; `context => 'Example School'` prints `<span class="screen-reader-text"> Example School</span>` inside the control.
  - `tag( 'Waiting since today', 'red' )` is `<span class="wpcpm-status wpcpm-status--red">Waiting since today</span>`; `tag( 'New' )` has no tone class.
  - `actions( array( $a, $b ), 'They are emailed.' )` holds `$a` before `$b` and ends with `<p class="wpcpm-ui-consequence">They are emailed.</p>`.
  In `bin/test-parts-css.php`: the values of design 2.1 for `.wpcpm-btn` under `.wpcpm-ui-on` (min-height 40px, padding 9px 16px, `font: 600 14px/20px`, radius 2px, nowrap); the quiet variant padding 10px 8px; wp-admin quiet and danger `font-size: 13px`, `font-weight: 500`; the secondary hover and the two tag borders appear only in rules whose selector starts with `.wpcpm-ui-on`; the focus ring `2px solid` Blueberry with `outline-offset: 2px`; disabled `opacity: 0.55`.
- [ ] **Step 2: Run** both suites. Expected: the new checks fail.
- [ ] **Step 3: Implement** the six helpers and their rules.
- [ ] **Step 4: Run** both suites and `bin/test-ui.php`. Expected: all pass.
- [ ] **Step 5: Commit:** `Parts: the button, the tag, the chip, the heading, the intro and the action row`.

### Task 5: The field

**Files:**
- Modify: `includes/class-wpcpm-ui.php`, `assets/css/parts.css`.
- Test: `bin/test-ui-parts.php`, `bin/test-parts-css.php`.

**Interfaces:**
- Consumes: `WPCPM_Form_Errors::attrs( $field_id, $sentence, $hint_id = '' )` and `WPCPM_Form_Errors::problem( $field_id, $sentence )` (`includes/class-wpcpm-form-errors.php:188`, `:216`).
- Produces: `WPCPM_UI::field( array $args ): string`. Args: `id` (required), `name` (required), `label` (required), `type` (`text` default, `email`, `url`, `search`, `date`, `number`, `textarea`, `select`), `value` (string; a `textarea`'s value is printed after a leading line feed, as the renderers do today), `options` (value to label, for a select), `long` (bool, a select up to 640px), `need` (`required`, `optional`, ''), `hint` (string), `rows` (int, a textarea), `attrs` (array of extra attributes: `minlength`, `maxlength`, `placeholder`, `autocomplete`; `required` is printed from `need`), `problem` (the refusal sentence, '' for none), `side`. Markup: `<div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="{id}">{label}<span class="wpcpm-ui-field__need">{Required|Optional}</span></label>{control with class "wpcpm-ui-field__control" and modifier "--date", "--number", "--select", "--long", "--textarea"}<p class="wpcpm-ui-field__hint" id="{id}-hint">{hint}</p>{problem markup}</div>`, the control carrying `aria-describedby="{id}-hint"` when there is a hint and the attributes `WPCPM_Form_Errors::attrs()` gives when there is a problem.
- Produces, `parts.css`: design 2.4 (label, need word, box, widths: 640px, select 200px to 320px or 640px long, date and number 200px, textarea full width and at least 96px, hint 6px under, 14px between fields; wp-admin 13px label and hint).

- [ ] **Step 1: Write the failing checks:** a `textarea` field with `need => 'required'`, a hint and `maxlength` 2000 prints the label with `for`, `Required` in the need span, the box with the same `id`, `name`, `required`, `maxlength="2000"` and `aria-describedby="{id}-hint"`, the hint with that id; `need => 'optional'` prints `Optional` and no `required`; no hint prints no `aria-describedby` and no hint element; a `value` of `"\nline"` keeps both line feeds; `problem => 'Too long.'` prints exactly what `WPCPM_Form_Errors::attrs()` and `::problem()` print for that id (call them in the suite and compare); a `date` field has the `--date` class; a select marks its selected option; label, value and hint with markup are escaped; the class names never include `wpcpm-field` (the legacy class). In `bin/test-parts-css.php`: the widths and gaps above.
- [ ] **Step 2: Run.** Expected: the new checks fail.
- [ ] **Step 3: Implement** `field()` and its rules. `Optional` is a new string: `__( 'Optional', 'wpcredits-program-manager' )`; `Required` reuses the plugin's existing string.
- [ ] **Step 4: Run** both suites. Expected: all pass.
- [ ] **Step 5: Commit:** `Parts: the field, label over the box, the hint tied to it, Required and Optional in words`.

### Task 6: Folds, item header, table and list row

**Files:**
- Modify: `includes/class-wpcpm-ui.php`, `assets/css/parts.css`.
- Test: `bin/test-ui-parts.php`, `bin/test-parts-css.php`.

**Interfaces:**
- Consumes: `WPCPM_Icons::svg( 'chevron-down', 20 )`, `WPCPM_Icons::svg( 'external', 20 )` (`includes/class-wpcpm-icons.php`).
- Produces:
  - `WPCPM_UI::fold_head( array $args ): string`, the inner markup of a head. Args: `size` (`card` or `inner`), `title`, `title_id`, `phrase`, `count` (printed in `<span class="wpcpm-ui-count">` only when `phrase` is ''), `tag` (array `words`, `tone`), `cue` (words), `help_slot` (bool: an empty 104px slot `<span class="wpcpm-ui-fold__help-slot" aria-hidden="true"></span>`), `leaves` (bool: the external icon in the chevron's place), `second` (an inner fold's second piece). Card markup: `<span class="wpcpm-ui-fold__lead"><h3 class="wpcpm-ui-fold__title">{title}</h3><span class="wpcpm-ui-fold__phrase">{phrase}</span></span><span class="wpcpm-ui-fold__end">{tag}{<span class="wpcpm-ui-fold__cue" aria-hidden="true">{cue}</span>}{help slot}</span><span class="wpcpm-ui-fold__chev" aria-hidden="true">{chevron svg}</span>` (or `wpcpm-ui-fold__ext` with the external svg).
  - `WPCPM_UI::inner_open( array $args ): string` (`<details class="wpcpm-ui-fold wpcpm-ui-fold--inner"{ open}{ id}><summary class="wpcpm-ui-fold__head">{fold_head size inner}</summary><div class="wpcpm-ui-fold__body">`; args `title`, `second`, `open`, `id`) and `WPCPM_UI::inner_close(): string` (`</div></details>`).
  - `WPCPM_UI::item_header( array $args ): string`. Args: `name`, `href`, `mark` (already escaped), `ref`, `tag` (array `words`, `tone`), `facts` (list of strings, or `array( 'html' => '...' )` for a link already escaped), `level` (`h4` default). Markup: `<{level} class="wpcpm-ui-item__title">{name or link}{mark}<code class="wpcpm-ui-item__ref">{ref}</code></{level}><p class="wpcpm-ui-item__facts">{tag}<span class="wpcpm-ui-item__fact">{fact}</span>...</p>`.
  - `WPCPM_UI::table_open( array $columns, array $args = array() ): string`, `WPCPM_UI::cell( array $column, string $html, string $kind = '' ): string`, `WPCPM_UI::table_close(): string`. A column: `key`, `label` ('' for an action column, printed as `<span class="screen-reader-text">Action</span>`), `kind` (`text`, `num`, `date`, `short`, `action`), `width` ('' for auto). Args: `keyvalue` (bool, class `wpcpm-ui-table--keyvalue`), `caption`, `labelledby`. Cells carry `data-label="{label}"` and `class="wpcpm-ui-cell--{kind}"`; `kind` `empty` marks an empty value kept in words (`wpcpm-ui-cell--empty`). A `colgroup` is printed when any column has a width.
  - `WPCPM_UI::row( array $args ): string`. Args: `name`, `tag` (array), `facts` (as `item_header()`), `actions` (array of `button()` markup), `form` (markup printed under the row in `<div class="wpcpm-ui-list__form" id="{form_id}">`), `form_id`. Markup: `<li class="wpcpm-ui-list__row"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">...</span>{tag}<span class="wpcpm-ui-list__facts">...</span><span class="wpcpm-ui-list__act">...</span></div>{form}</li>`; `WPCPM_UI::list_open(): string` (`<ul class="wpcpm-ui-list">`), `WPCPM_UI::list_close(): string`.
- Produces, `parts.css`: design 2.3 (both fold sizes, every value, the phone head grid at 600px, the open head's hairline as the body's top edge, the cue's place kept when open, 10px between sibling folds, 22px to the next module, no doubled hairline after a table), 2.7, 2.8, 2.10.

- [ ] **Step 1: Write the failing checks:** a card head with phrase "1 waiting" and count 1 prints the phrase and no count; with no phrase it prints the count; the tag, cue and help slot are in the end group in that order; the chevron is the last child and is `aria-hidden`; with `leaves` the external icon replaces the chevron; an inner fold with `open` prints `open`; an item header with a ref prints the `code`; with a tag the tag is the facts line's first child; a fact given as `html` is printed as given and a plain one escaped; a table with an action column prints the screen-reader name "Action" and each cell's `data-label`; a `num` cell carries `wpcpm-ui-cell--num`; a row with a form prints the form after the line inside the same `li`, with its id. In `bin/test-parts-css.php`: head min-heights 56px and 44px, padding 8px 16px and 12px 0, the chevron 20px, body padding 20px 16px, the 104px help slot, the phone rules at `max-width: 600px` front end and `782px` under `.wpcpm-wrap`.
- [ ] **Step 2: Run.** Expected: the new checks fail.
- [ ] **Step 3: Implement.** "Action" reuses the plugin's existing string.
- [ ] **Step 4: Run** both suites. Expected: all pass.
- [ ] **Step 5: Commit:** `Parts: the two folds, the item header, the table and the list row`.

### Task 7: The Decide area

**Files:**
- Modify: `includes/class-wpcpm-ui.php`, `assets/css/parts.css`.
- Test: `bin/test-ui-decide.php` (new), `bin/test-parts-css.php`.

**Interfaces:**
- Consumes: `button()`, `field()`, `actions()`, `heading()` (Tasks 4 and 5), `enqueue_script()` (Task 3).
- Produces: `WPCPM_UI::decide( array $args ): string`. Args: `id` (the base, unique per item, for example `wpcpm-decide-app-123`), `title` (default `__( 'Decide' )`, a new string), `decisions` (list). A decision: `key` (unique within the area), `label`, `kind`, `form_attrs` (array: `class`, `method`, `action`, `data-wpcpm-once`, `data-wpcpm-busy`, `data-wpcpm-confirm`; printed on the `<form>` as given), `hidden` (callable that prints the hidden inputs; buffered by the helper), `fields` (string from `field()`, '' for a decision with no note), `confirm_label` ('' keeps `label`), `submit_name`, `submit_value` (for a form with two submits), `open` (bool), `panel` (the key of another decision whose panel this one shares, for the request).
  Markup: `<div class="wpcpm-ui-decide" id="{id}">{heading}<div class="wpcpm-ui-actions">` then, per decision: with no fields, its whole `<form>` (hidden inputs and its submit button of its kind) inside the row; with fields, an opener `button( type button, controls {id}-{key}-panel, kind, label, hidden )` (printed `hidden`; the script reveals it). After the row, per decision with fields: `<div class="wpcpm-ui-panel" id="{id}-{key}-panel" data-wpcpm-panel>` holding its `<form>`: hidden inputs, fields, `actions( confirm submit, Cancel button type button kind quiet with attribute hidden and data-wpcpm-cancel )`. A panel is printed without `hidden`; with `open` it carries `data-wpcpm-open`. A shared panel prints one form with each sharer's submit (`name`, `value`), each submit carrying `data-wpcpm-for="{key}"`.
  Every id in the area starts with the area's `id`; a field's id is given by the caller and must be unique per item (`{field name}-{item id}`).
- Produces, `parts.css`: design 2.6 (heading 16px/22px, 24px above and 12px below, wp-admin 14px/20px; 16px from the row to the panel's hairline, 16px under it; the panel's action row 16px under the field; wp-admin 16px to the item box's edge).

- [ ] **Step 1: Write the failing checks** in `bin/test-ui-decide.php`:
  - an area with Approve (no fields), Reject (fields, `confirm_label` "Reject the application") prints, in order: the heading "Decide", the row with Approve's form (its hidden inputs, its button primary) and Reject's opener (`type="button"`, `aria-controls="{id}-reject-panel"`, `aria-expanded="false"`, `hidden`), then Reject's panel (not `hidden`) holding its form, the field, "Reject the application" as a danger submit and Cancel (`type="button"`, `hidden`, `data-wpcpm-cancel`).
  - **Review Focus 3:** with no script nothing a person needs is hidden: every submit button and every field is outside any element carrying `hidden`; only openers and Cancels are hidden.
  - **Review Focus 2:** two areas drawn for two items (ids `wpcpm-decide-app-1` and `-2`) share no id, no `aria-controls` target and no `for`; each panel's form holds its own item's hidden inputs (the suite's callables print `<input type="hidden" name="wpcpm_application" value="1">` and `value="2"`).
  - `open` puts `data-wpcpm-open` on that panel and on no other.
  - a shared panel: two openers (`aria-controls` the same panel), one form, two submits with `name="state"` and `value="done"` / `value="declined"`, each with `data-wpcpm-for`.
  - the form attributes given (`data-wpcpm-once`, `data-wpcpm-busy="Working"`, `data-wpcpm-confirm`) are printed on the form unchanged.
  - an area with any panel calls `enqueue_script()` (the registry stand-in records `wpcpm-parts`); one with none does not.
- [ ] **Step 2: Run.** Expected: fails.
- [ ] **Step 3: Implement** `decide()` and its rules.
- [ ] **Step 4: Run** `php bin/test-ui-decide.php`, `php bin/test-ui-parts.php`, `php bin/test-parts-css.php`. Expected: all pass.
- [ ] **Step 5: Commit:** `Parts: the Decide area, one row and one note panel at a time, every form on the page without the script`.

### Task 8: The script, `assets/js/parts.js`

**Files:**
- Create: `assets/js/parts.js`, `bin/js/parts.js` (the harness).
- Test: `bin/test-parts-js.php` (new; runs the harness as `bin/test-dashboard-card.php` runs `bin/js/dashboard-open.js`, and skips with a `skip` line when node is missing).

**Interfaces:**
- Consumes: the markup of `decide()` (Task 7) and `row()` (Task 6): `[data-wpcpm-panel]`, `[aria-controls]` openers, `[data-wpcpm-cancel]`, `[data-wpcpm-open]`, `[data-wpcpm-for]`.
- Produces: on `DOMContentLoaded`, for each `.wpcpm-ui-decide` and each `.wpcpm-ui-list`: reveal the openers and Cancels (remove `hidden`); hide every panel and row form (`hidden`) except one carrying `data-wpcpm-open`, whose opener gets `aria-expanded="true"`. An opener pressed: hides the area's other panels and sets their openers to `false`; shows its panel, sets itself `true`, moves focus to the panel's first `input:not([type=hidden]), textarea, select`; in a shared panel shows only the submit whose `data-wpcpm-for` is the opener's key and hides the other. Pressed again: closes it. Cancel, and Escape inside a panel: hides it, sets the opener `false`, focus back on the opener. Release 1 needs nothing else of the script (the file box, the in-place filter, the tag fitting and the Pipeline fold come with releases 2 and 3).

- [ ] **Step 1: Write the failing harness** `bin/js/parts.js` on a stand-in page (elements that keep attributes, children and focus, as `bin/js/dashboard-open.js` builds one) with two Decide areas and a shared panel, and these scenarios, each printing `ok` or `FAIL`:
  - after load: openers and Cancels visible; panels hidden; the panel with `data-wpcpm-open` visible and its opener `aria-expanded="true"`.
  - pressing the second area's Reject: its panel visible, focus in its textarea, the first area untouched.
  - pressing the first area's Ask, then its Reject: Ask's panel hidden, its opener `false`; Reject's visible.
  - Cancel: panel hidden, opener `false`, focus on the opener; Escape in the textarea: the same.
  - the shared panel: "Mark as handled" shows the panel with only the `done` submit visible; "Decline" then shows only the `declined` one.
  - two mutation proofs: the script without the line that hides the other panels, and without the focus move, each must make a scenario fail.
  `bin/test-parts-js.php` runs it and checks node's exit status, and also reads `assets/js/parts.js`: no `fetch(`, `XMLHttpRequest`, `localStorage`, `innerHTML =`.
- [ ] **Step 2: Run** `php bin/test-parts-js.php`. Expected: fails (no script).
- [ ] **Step 3: Implement** `assets/js/parts.js` (strict mode, no dependency, no global but one function on `window` if the harness needs it).
- [ ] **Step 4: Run** `php bin/test-parts-js.php`, `php bin/test-dashboard-card.php`, `php bin/test-forms-js.php`. Expected: all pass.
- [ ] **Step 5: Commit:** `Parts: the script that opens one note panel at a time and folds what the server printed open`.

The controller checks in the browser, at Task 18's gate: Enter and Space on a fold head; focus into a panel and back on Cancel and on Escape; `aria-expanded` read out by a screen reader; with JavaScript off, every decision posts.

### Task 9: The card shell, the Administrator Dashboard's frame and the Mentor applications row

**Files:**
- Modify: `includes/class-wpcpm-dashboard-card.php` (`open()` `:461-552`, `close()` `:573-602`); `includes/modules/class-wpcpm-administrators-dashboard.php` (the render that prints the root at `:335`: `WPCPM_UI::enter( 'administrator' )` before, `leave()` after, `root_class()` on the root and on the frame's root through `WPCPM_Dashboard_Frame::open()`; `render_waiting()` `:1045-1116`, the Mentor applications row at `:1080-1090`); `includes/class-wpcpm-dashboard-frame.php` (`open()`: `root_class()` on `.wpcpm-frame`); `includes/modules/class-wpcpm-sponsors-dashboard.php` (`enter( 'sponsor' )` and `leave()` around its render); `includes/modules/class-wpcpm-mentors-dashboard.php` and `class-wpcpm-students-dashboard.php` (`enter( 'report-cards' )` and `leave()`); `includes/modules/class-wpcpm-institutions-dashboard.php` (`enter( 'institution' )`, `leave()`); each wp-admin module screen that draws tabs (`class-wpcpm-institutions.php:2746`, `class-wpcpm-sponsors.php:2897`, `class-wpcpm-mentors.php:414`, `class-wpcpm-students.php:242`, `class-wpcpm-administrators.php:273`, `trait-wpcpm-accounts-screen.php:628`, `includes/tools/class-wpcpm-emails.php:316`): `enter( $page . '/' . $tab )` once the tab is resolved, `leave()` after it is drawn; `assets/css/parts.css` (the card fold's grid placement of the help link).
- Test: `bin/test-dashboard-card.php`, `bin/test-administrators-dashboard.php`; neighbors `bin/test-sponsors-dashboard.php`, `bin/test-cohort-timeline.php`.

**Interfaces:**
- Consumes: `WPCPM_UI::on()`, `fold_head()`, `button()`, `tag()`, `WPCPM_Guide_Jobs::link( 'mentor-application', $about )`.
- Produces: with `on()`, `WPCPM_Dashboard_Card::open()` prints `<section class="{section_class} wpcpm-ui-fold-card" id=...><details class="wpcpm-ui-fold"{ open}><summary class="wpcpm-ui-fold__head">{fold_head card: title, phrase (the count only without one), the age tag, the cue, help_slot}</summary><div class="wpcpm-ui-fold__body {body_class}">` (the anchor on the element it is on today; `wpcpm-group__disclosure` and `wpcpm-group__summary` are not printed); `close()` prints `</div></details>`, then the help link as `<p class="wpcpm-ui-fold__help">{link}</p>` (laid into the head's slot by the section's grid at desktop widths) and a copy as the body's first line `<p class="wpcpm-ui-fold__help wpcpm-ui-fold__help--phone">` printed at the top of the body by `open()` (shown only at 600px or less); on the Overview no action after the fold; on Watch and The program the action after the fold as `button( kind quiet, href )` in `<p class="wpcpm-ui-fold__action">`. Without `on()` both print today's markup byte for byte.
  The Mentor applications row, with `on()`: `<section class="wpcpm-administrator__card wpcpm-ui-fold-card" id="{anchor}"><div class="wpcpm-ui-fold wpcpm-ui-fold--row"><div class="wpcpm-ui-fold__head">{fold_head: title, phrase "%s waiting", tag from the queue's age, help_slot, leaves}{the Review link as button( kind secondary, href, context "on the Mentors screen" )}</div></div>{help link}</section>`.

- [ ] **Step 1: Write the failing checks.** In `bin/test-dashboard-card.php`, with `set_live( array() )`: every existing check passes unchanged (the off branch is today's markup); with `set_live( array( 'administrator' ) )` and `enter( 'administrator' )`: a folded card with phrase, age and cue prints the head in the order of Task 6, the cue `aria-hidden`, no `wpcpm-group__disclosure`, the anchor on the section or the disclosure as `id_on` says; the help link is outside the `summary` (Review Focus 4: no `<a` between `<summary` and `</summary>`), and its phone copy is the body's first child; **Review Focus 4:** a card holding the page's outcome (`hold()`) is drawn open with the outcome as the body's first block after the phone help line; `fold( true, 'Review' )` prints the cue only while folded markup is drawn and never prints "Close"; with `enter( 'sponsor' )` the same call prints today's markup. In `bin/test-administrators-dashboard.php`: the render calls `enter( 'administrator' )` and `leave()` (after it, `surface()` is ''); with the surface on the root carries `wpcpm-ui-on` and the Mentor applications row prints the row fold with the age tag ("Waiting since today" or "%s days waiting"), the help link to the guide's `#a-mentor-applied` job, the external icon and the Review link to `admin.php?page=wpcpm-mentors&tab=queue`; off, the row is today's.
- [ ] **Step 2: Run** both suites. Expected: the new checks fail, every old one passes.
- [ ] **Step 3: Implement** the branches and the `enter()` calls on every surface and tab listed (they change nothing while `LIVE` is empty), and the card's grid rules in `parts.css`.
- [ ] **Step 4: Run** both suites and the neighbors, and every suite. Expected: all pass.
- [ ] **Step 5: Commit:** `Administrator Dashboard: the card shell and the Mentor applications row on the parts, behind the switch`.

### Task 10: Institution applications: the item, the answers fold and the Decide area

**Files:**
- Modify: `includes/modules/class-wpcpm-administrators-cards.php` (`render_application()` `:2249-2303`; the answers fold at `:2288`); `includes/modules/class-wpcpm-institutions.php` (`render_open_decisions()` `:4138-4224`, `render_reopen_form()` `:4233-4242`, `render_purge_form()` `:4252-4266`, `render_decision_form()` `:4284-4340`, `render_application_answers()` `:3552-3590`, `box_problem()` `:4355-4369`, read only).
- Test: `bin/test-administrators-dashboard.php`, `bin/test-institutions-screen.php`, `bin/test-institution-application.php`; neighbors `bin/test-form-errors.php`, `bin/test-return.php`.

**Interfaces:**
- Consumes: `decide()`, `field()`, `item_header()`, `inner_open()`, `inner_close()`, `table_open()`, `cell()`, `tag()`, `WPCPM_UI::on()`.
- Produces, with `on()` (the Administrator Dashboard; the wp-admin Institutions screen is off and prints today's markup):
  - the item through `item_header()`: the name linked to the queue, the test mark, the reference, the state as a neutral tag, then country, "Assigned to %s" and the received date as facts; "Read the application" as `inner_open()`; the held note above the Decide area as today.
  - one `decide()` per application, id `wpcpm-decide-app-{post ID}`: Send the confirmation link again (secondary, no note, first, only while the address is unconfirmed), Approve (primary, no note), Ask a question (secondary, note: label "Ask the applicant something", hint "It is sent as it is written, with your address to reply to.", the panel's button keeps "Send this question"), Reject (danger, note: label "Why, for the next Administrator who reads this", hint "It is never sent to the applicant.", panel "Reject the application"), Reject as spam (danger, no note); Put back in the queue (secondary) and Delete for good (danger) on the states that offer them.
  - the note boxes' ids become `{field}-{post ID}` (`wpcpm_question-123`, `wpcpm_reason-123`); their `name` stays `wpcpm_question` / `wpcpm_reason`; a refused box (`box_problem()`) passes its new id to `field()`'s `problem`, and its panel is `open`.
  - the answers as `table_open( keyvalue )`, the Airtable column name (`<code class="wpcpm-inst-record">`, `:3582`) not printed; an empty answer's cell `empty`.

- [ ] **Step 1: Write the failing checks** in `bin/test-administrators-dashboard.php` (with the surface on; the fixtures the suite already builds for applications):
  - **posted fields:** each of the five forms posts exactly the hidden inputs it posts today (the suite reads today's markup with the surface off and compares the list of `name=value` pairs and the nonce's action, form by form, with the surface on).
  - **Review Focus 2:** two applications on the card: their Decide areas, panels and boxes have distinct ids; each Reject panel's form posts its own application's ID.
  - **Review Focus 1:** a press refused for its reason, left for application 2 (the flash stand-in holds the refusal and `kept_box()` the typing): application 2's Reject panel carries `data-wpcpm-open`, its box holds the typing and is tied to the refusal sentence (`aria-describedby` holds `WPCPM_Form_Errors::problem_id( 'wpcpm_reason-2' )`); application 1's panels carry no `data-wpcpm-open`.
  - the new words of design 5 for this card, exactly; "Approve" is the area's only primary.
  - the answers table holds no `wpcpm-inst-record`.
  In `bin/test-institutions-screen.php`: the Institutions screen's Waiting for review tab (surface off) draws an application past the card's window with today's markup and today's ids (`id="wpcpm_question"`), unchanged (Review Focus 5).
- [ ] **Step 2: Run.** Expected: the new checks fail.
- [ ] **Step 3: Implement** the parts' branch in each renderer; the old branch stays as it is.
- [ ] **Step 4: Run** the three suites and the neighbors. Expected: all pass.
- [ ] **Step 5: Commit:** `Administrator Dashboard: institution applications on the parts, one Decide area per application, behind the switch`.

### Task 11: Signed Collaboration Agreements

**Files:**
- Modify: `includes/modules/class-wpcpm-administrators-cards.php` (`render_agreements()` `:2313-2375`, "Awaiting review" at `:2335`; `render_agreement_list()` `:2385-2413`; `render_reinstate_form()` `:2422-2434`); `includes/modules/class-wpcpm-institution-panel.php` (`render_review()` `:481-549`, `render_accept_form()` `:697-731`, `render_return_form()` `:747-780`).
- Test: `bin/test-administrators-dashboard.php`, `bin/test-institution-panel.php`; neighbor `bin/test-institution-agreement.php`.

**Interfaces:**
- Consumes: `decide()`, `field()`, `item_header()`, `row()`, `button()`.
- Produces, with `on()`: each agreement awaiting review as an item with the tag "Awaiting review" (the existing words) and no heading above it; the review's facts as today's content in the parts' table and list; `decide()` with id `wpcpm-decide-agr-{post ID}`: Accept it (primary, no note, its existing confirm question), Return with a note (secondary, note: label "What has to change", hint "In your own words. It is emailed to everybody at the institution exactly as you write it, with your address to reply to.", `need` required, `minlength` 20, `maxlength` 2000, panel keeps "Return it with this note"; box id `wpcpm-return-{post ID}` as today; `name="wpcpm_agreement_note"`); the returned and revoked lists as `row()`s, Reinstate as a quiet button that posts at once with its existing question. The review's facts are drawn by `render_review( $id, false )` on the wp-admin Institutions screen too: off there, today's markup.

- [ ] **Step 1: Write the failing checks:** posted fields per form unchanged (as Task 10); a kept return note opens its panel with the typing; the words exactly; the Accept and Return buttons are one primary and one secondary; on the wp-admin Institutions screen (surface off) `render_review( $id, false )` prints today's markup.
- [ ] **Step 2: Run.** Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the suites and the neighbor. Expected: pass.
- [ ] **Step 5: Commit:** `Administrator Dashboard: signed agreements on the parts, Accept it or Return with a note, behind the switch`.

### Task 12: Requests from institutions

**Files:**
- Modify: `includes/modules/class-wpcpm-administrators-cards.php` (`render_requests()` `:2575-2655`, the kind at `:2608`; `render_details_request()` from `:2695`); `includes/modules/class-wpcpm-institution-request.php` (`render_decisions()` `:1772-1862`, the form at `:1818-1861`; `note_label()` at `:1940`).
- Test: `bin/test-administrators-dashboard.php`, `bin/test-institution-request.php`.

**Interfaces:**
- Consumes: `decide()` with a shared panel, `field()`, `item_header()`.
- Produces, with `on()`: the item header with the kind as a fact; the student line and the Airtable sentence as today; `decide()` id `wpcpm-decide-req-{post ID}`: openers Mark as handled (primary) and Decline (danger) sharing one panel whose one form posts `state` = `done` or `declined` from two submits "Mark the request as handled" and "Decline the request" (new strings); the field: visible label `note_label()` ("What you did about it"), Optional, hint "For the log. It is not sent to the institution." (new), `name="wpcpm_request_note"`, id `wpcpm-request-note-{post ID}` as today, `maxlength` as today, no placeholder. A details request keeps its own Approve and Decline in the parts' row (no note), as today. `render_decisions()` is called only from the Administrator Dashboard (`class-wpcpm-administrators-cards.php:2622`): its old branch is deleted in Task 18.

- [ ] **Step 1: Write the failing checks:** the panel's one form posts the same hidden inputs and nonce action as today and its two submits post `state=done` and `state=declined`; the placeholder is gone and the label visible; a kept note opens the panel; the words exactly.
- [ ] **Step 2: Run.** Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** both suites. Expected: pass.
- [ ] **Step 5: Commit:** `Administrator Dashboard: requests from institutions on the parts, Mark as handled or Decline with one optional note, behind the switch`.

### Task 13: The sponsor queues: posts, offers, events, agreements, applications, reports

**Files:**
- Modify: `includes/modules/class-wpcpm-administrators-cards.php` (`render_sponsor_agreements()` `:574`, `render_sponsor_posts()` `:675-727`, `render_sponsor_offers()` `:740`, `render_sponsor_events()` `:785`, `render_sponsor_applications()` `:831` with its fold at `:916`, `render_sponsor_reports()` `:1281`); `includes/modules/class-wpcpm-sponsor-posts.php` (`render_decision()` `:1827-1882`); `includes/modules/class-wpcpm-sponsor-offer-review.php` (`render_item()` `:286`, `render_decision()` `:377`, the return fold `:405-406`); `includes/modules/class-wpcpm-sponsor-agreement.php` (`render_decision()` `:526`, the return fold `:574-575`, `render_decision_form()` `:620`); `includes/modules/class-wpcpm-sponsor-application.php` (`render_decision()` `:5215`); `includes/modules/class-wpcpm-sponsor-event-queue.php` (`render_item()` `:458`, its folds `:566`, `:583`, the return fold `:804`, the decisions `:598-690`); `includes/modules/class-wpcpm-sponsor-event-list.php` (`render_actions()` `:250-293`, called at `class-wpcpm-sponsor-event-queue.php:619`).
- Test: `bin/test-administrators-dashboard.php`, `bin/test-sponsor-posts.php`, `bin/test-sponsor-offers.php`, `bin/test-sponsor-agreement.php`, `bin/test-sponsor-application.php`, `bin/test-sponsor-event-queue.php`, `bin/test-sponsor-event-list.php`.

**Interfaces:**
- Consumes: `decide()`, `field()`, `item_header()`, `inner_open()`, `button()`, `tag()`.
- Produces, with `on()`: each item through `item_header()`; "Preview" leaves the post's decisions and is a fact `array( 'html' => '<a href="...">Preview the post</a>' )` (new string); `decide()` per post (Publish primary, no note; Return with a note: label "A note for the author", hint the existing sentence "The post goes back to the sponsor's account as a draft, and your note is sent to its author by email.", panel keeps "Return it with this note", `name="wpcpm_note"`, `required`, panel `open` when a refused note came back); the same shape for offers, agreements and events; the sponsor application's decision form as a Decide area; folds as `inner_open()`; `render_actions()`'s buttons through `button()`. Callers and old branches: the posts', offers', agreements' and events' decisions are called only from the Administrator Dashboard (old branches deleted in Task 18); the sponsor application's decision form is shared with the wp-admin Sponsors screen through `render_open()` (`class-wpcpm-sponsors.php:2983`) and keeps its old branch there; `render_actions()` is shared with the Report Cards' calendars and the Institution Dashboard and keeps its old branch for them.

- [ ] **Step 1: Write the failing checks** in each suite, with the surface on: the posted fields per form unchanged; the words exactly (design 5: "Preview the post", the return labels); a kept note opens its panel; with `enter( 'report-cards' )` `render_actions()` prints today's markup, and with `enter( 'wpcpm-sponsors/queue' )` the sponsor application's form prints today's markup (Review Focus 5).
- [ ] **Step 2: Run.** Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the seven suites. Expected: pass.
- [ ] **Step 5: Commit:** `Administrator Dashboard: the sponsor queues on the parts, behind the switch`.

### Task 14: Semester reports and the Overview's group lines

**Files:**
- Modify: `includes/modules/class-wpcpm-administrators-cards.php` (`render_reports()` `:2446-2563`, `render_more_line()` `:2204`, `render_sponsor_more_line()` `:2232`, `render_strip()` `:2083`, `render_applications()` `:2112`); `includes/class-wpcpm-dashboard-card.php` (`group()` `:367-403`, the heading and lead under `on()`).
- Test: `bin/test-administrators-dashboard.php`, `bin/test-dashboard-card.php`.

**Interfaces:**
- Consumes: `table_open()`, `cell()`, `table_close()`, `button()`, `heading()`.
- Produces, with `on()`: each report table through `table_open()` (age and count columns `num`, dates `date`, the action column `action` holding "Review" as `button( kind quiet, href )`); the in-card headings "To review", "Due for drafting", "Approved this semester" through `heading()` with their ids kept; the card foot ("Open on the ... screen") after a hairline as today; the group's heading and lead with the gaps of design 2.3 (4px heading to lead, 12px lead to the first row, 22px from the last row to the sentence under it).

- [ ] **Step 1: Write the failing checks:** the tables' header cells, `data-label` on each cell, the action column's hidden name "Action", "Review" quiet and linked as today; the headings' ids unchanged; with the surface off, today's markup.
- [ ] **Step 2: Run.** Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: pass.
- [ ] **Step 5: Commit:** `Administrator Dashboard: semester reports and the Overview's lines on the parts, behind the switch`.

### Task 15: Watch: its cards and the cohort timeline's card

**Files:**
- Modify: `includes/modules/class-wpcpm-administrators-cards.php` (`render_offers_low()` `:941`, `render_duplicates()` `:1000`, `render_interests()` `:1075`, `render_tiers_ending()` `:1213`, `render_quiet_students()` `:2926`, `render_health()` `:3211`, its "Locked accounts" heading `:3258`, its scroll box `:3389`); `includes/modules/class-wpcpm-cohort-timeline-view.php` (`render_card()` `:87-130`).
- Test: `bin/test-administrators-dashboard.php`, `bin/test-cohort-timeline.php`.

**Interfaces:**
- Consumes: the card shell (Task 9), `table_open()`, `cell()`, `item_header()`, `heading()`, `tag()`, `button()`.
- Produces, with `on()`: the Watch cards' tables, headings, items and tags on the parts; the scroll box (`wpcpm-administrator__scroll`) replaced by the table part (stacked on a phone, no scroll box); each card's action after the fold as the quiet button (Task 9); the cohort timeline's card head through the card shell and its body's tiles and table on the parts.

- [ ] **Step 1: Write the failing checks:** each Watch card with the surface on prints no `wpcpm-admin-table`, no `wpcpm-administrator__scroll`, no `wpcpm-administrator__subtitle`; its tables' cells carry `data-label`; its action is a quiet button after the fold; the cohort timeline's card prints the parts' head; off, today's markup.
- [ ] **Step 2: Run.** Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: pass.
- [ ] **Step 5: Commit:** `Administrator Dashboard: Watch on the parts, the cohort timeline's card included, behind the switch`.

### Task 16: The program, with "Tools from our sponsors"

**Files:**
- Modify: `includes/modules/class-wpcpm-administrators-cards.php` (`render_sponsors_strip()` `:1126`, `render_programs()` `:2792`, `render_mentor_engagement()` `:3158`); `includes/modules/class-wpcpm-sponsor-tools.php` (`render()` `:400-472`, called at `class-wpcpm-administrators-dashboard.php:1164`).
- Test: `bin/test-administrators-dashboard.php`, `bin/test-sponsor-tools.php`.

**Interfaces:**
- Consumes: `table_open()`, `cell()`, `row()`, `chip()`, `button()`, `heading()`.
- Produces, with `on()`: the program's cards and "Tools from our sponsors" on the parts (rows, chips, buttons). `WPCPM_Sponsor_Tools::render()` is also printed by both Report Cards (`class-wpcpm-mentors-dashboard.php:741`, `class-wpcpm-students-dashboard.php:627`): with `enter( 'report-cards' )` it prints today's markup, for this whole build.

- [ ] **Step 1: Write the failing checks:** with the surface on, the tools' rows and chips; **Review Focus 5:** with `enter( 'report-cards' )` and `set_live( array( 'administrator' ) )`, `render( WPCPM_Sponsor_Tools::AUDIENCE_MENTORS, $viewer )` prints byte for byte what it prints with everything off.
- [ ] **Step 2: Run.** Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: pass.
- [ ] **Step 5: Commit:** `Administrator Dashboard: The program and Tools from our sponsors on the parts, the Report Cards unchanged`.

### Task 17: The cohort timeline view

**Files:**
- Modify: `includes/modules/class-wpcpm-cohort-timeline-view.php` (`render_view()` `:178-234` and what it draws, the quiet line at `:731`); `assets/css/cohort-timeline.css` only if a rule there prints over a part (name it in the report).
- Test: `bin/test-cohort-timeline.php`.

**Interfaces:**
- Consumes: `table_open()`, `cell()`, `heading()`, `tag()`, `button()`.
- Produces, with `on()`: the view's tables, headings, tags and buttons on the parts; the sentence at `:731` ("No students start in the next twelve months yet.") as `<p class="wpcpm-ui-intro wpcpm-ui-intro--muted">`, a muted note (design call 25), not a button; the chart as it is.

- [ ] **Step 1: Write the failing checks:** with the surface on, no `wpcpm-administrator__quiet` and the note's class; the tables' cells carry `data-label`; off, today's markup.
- [ ] **Step 2: Run.** Expected: fail.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: pass.
- [ ] **Step 5: Commit:** `Administrator Dashboard: the cohort timeline view on the parts, behind the switch`.

### Task 18: Turn the Administrator Dashboard on and delete what only it used

**Files:**
- Modify: `includes/class-wpcpm-ui.php` (`const LIVE = array( 'administrator' );`); the old branches only the Administrator Dashboard reached: `WPCPM_Institution_Request::render_decisions()`, `WPCPM_Sponsor_Posts::render_decision()`, `WPCPM_Sponsor_Offer_Review::render_decision()`, `WPCPM_Sponsor_Agreement::render_decision()`, the event queue's decisions, `WPCPM_Institution_Panel::render_accept_form()` and `render_return_form()`, the Mentor applications row, every Watch and The program card body, `WPCPM_Cohort_Timeline_View::render_card()` and `render_view()`; `assets/css/administrator.css` (`:7-127`, `:304-306`, `:373-378`, `:383-489`, `:530-544`); `assets/css/dashboard-frame.css` (`.wpcpm-frame__menu-button` at `:232`, if Task 4's button rules cover it; else name why it stays).
- Kept, and named in the commit: the old branches of `WPCPM_Dashboard_Card` (the Sponsor Dashboard), `render_open_decisions()`, `render_decision_form()`, `render_application_answers()`, `render_review()`'s facts, the sponsor application's decision form (the wp-admin tabs), `WPCPM_Sponsor_Tools::render()` and `WPCPM_Sponsor_Event_List::render_actions()` (the Report Cards and the Institution Dashboard).
- Test: every suite; `bin/test-ui.php` (`LIVE` is now `array( 'administrator' )`).

**Interfaces:** none new.

- [ ] **Step 1: Write the failing check:** `bin/test-ui.php` expects `LIVE` to be `array( 'administrator' )`; each suite of Tasks 9 to 17 drops its "surface off" checks for a renderer whose old branch goes, and keeps them for the kept ones.
- [ ] **Step 2: Run.** Expected: `bin/test-ui.php` fails.
- [ ] **Step 3: Turn it on.** Set `LIVE`. Delete the old branches listed, and the `WPCPM_UI::on()` test around each.
- [ ] **Step 4: The searches.** For each `administrator.css` range, list its classes and run `grep -rn "<class>" includes/ assets/js/` for each; record the classes, the command and what it found in the commit message body. Delete a range only when the only hits are in renderers whose old branch was just deleted or in the parts' own markup; a class the Sponsor Dashboard's renderers still print keeps its selector, named in the message.
- [ ] **Step 5: Run** every suite, the standards and `php bin/check-references.php`. Expected: all pass, 0 errors, warnings no more than 177.
- [ ] **Step 6: Commit:** `Administrator Dashboard: on the parts; what only it used is deleted` (body: the searches).

### Task 19: Words, the guide and the release files

**Files:**
- Modify: `docs/sections/61-admin-steps-institutions.md` (`:31`, `:39`, `:85`, `:133-135`, `:371`, `:380`: Reject and its panel, "Ask a question", "Awaiting review" as a tag, the Decide area), `docs/sections/30-admin-wpadmin.md` (`:508` only if it describes the Overview's card; the wp-admin screens are unchanged in release 1), `docs/sections/64-admin-steps-sponsors.md` and `docs/sections/62-admin-steps-students.md` and `docs/sections/34-admin-operations.md` and `docs/sections/66-admin-where-it-stands.md` wherever a step names "Preview", "Return with a note", "Mark as handled", "Decline", "Read the application" or a card's "Review" on the Overview (find each with `grep -rn` and change only what the Overview now shows); `wpcredits-program-manager.php` (`:6` header, `:22` `WPCPM_VERSION`); `readme.txt` (`:7` Stable tag, a `= 1.123.92 =` entry above `:299`); `languages/` through `bash bin/make-pot.sh`; `docs/build/` through `php bin/build-docs.php` twice.
- Test: the guide's four suites, `bin/test-translators-pot.php`, `bin/check-spelling.php`, `bin/check-references.php`.

- [ ] **Step 1:** Read the version on main and on every branch (`git branch --format='%(refname:short)'` and `git show <branch>:wpcredits-program-manager.php | grep Version`); take 1.123.92 if free (1.123.91 is reserved).
- [ ] **Step 2:** The guide: bold is the exact name on screen; a confirming button's words are the panel's ("Reject the application"); say that a note opens under the row when its button is pressed, and that only one is open at a time. Build twice; run the guide's four suites.
- [ ] **Step 3:** The changelog, in plain words for Administrators, four to six bullets: the Administrator Dashboard's cards drawn alike (one head, one fold, the help link in the head); one Decide area per item, a note opening only when asked for; the request's note first, then Mark as handled or Decline; Preview as a link; the tables on a phone; nothing else changes on any other screen. No names, hyphens only.
- [ ] **Step 4:** The version in the header, the constant and the Stable tag; `bash bin/make-pot.sh`.
- [ ] **Step 5:** The whole battery (Global Constraints), `php bin/check-references.php`, `php bin/check-spelling.php`.
- [ ] **Step 6: Commit:** `1.123.92: the Administrator Dashboard on one set of shared parts`.

### Task 20: The theme, 1.32.28

**Repository:** `~/GitHub/wpcredits-theme`, branch `release/1.32.28-shared-parts` from its `main` (1.32.27, `46f8376`).

**Files:**
- Modify: `assets/css/dashboard.css`: delete `:1701-1730` (the decision buttons and boxes), `:1764-1800` (the return folds), the `.wpcpm-review` rule at `:1921` and `:1953-1962`, `:5594-5611`, `:5622-5661`, `:5677-5689`, `:5693-5705`, and the `.wpc-administrator-page` selectors of `:2095-2200`; where a selector list names both pages (`:5677` `:is( .wpcpm-administrator, .wpcpm-sponsor )`, the lists of `:2095-2200`), keep the `.wpc-sponsor-page` / `.wpcpm-sponsor` half. Add `:not( .wpcpm-ui-choice )` to the label rule at `:5023-5029` and `:not( .wpcpm-ui-choice input )` to the box rule at `:5032-5037` (design 6.2).
- Modify: `style.css:7` and `readme.txt:7` (1.32.28), `readme.txt` changelog entry `= 1.32.28 =` above `:224`; `bin/check-selectors.php`'s `$kept` only if a deleted rule must stay for a rollback (none expected).
- Test: the theme's own suites, `for t in bin/test-*.php; do php "$t"; done` from the theme root, and `php bin/check-selectors.php` with the plugin at this branch's Task 19 commit beside it (the check reads the plugin's source: a theme rule for a class the plugin no longer prints is reported).

- [ ] **Step 1:** Run `php bin/check-selectors.php` against the plugin at Task 19's commit. Expected: it reports the rules above as styling classes no longer printed (the failing check).
- [ ] **Step 2:** Delete the rules and add the two exceptions.
- [ ] **Step 3:** Run `php bin/check-selectors.php` and the theme's suites. Expected: all pass.
- [ ] **Step 4:** The version and the changelog (one bullet: the Administrator Dashboard's old card, decision and row-action rules removed, since WPCredits Program Manager 1.123.92 draws the dashboard with its own parts; the choice rows leave the parts' choice row alone). Hyphens only.
- [ ] **Step 5: Commit** in the theme repository: `1.32.28: the Administrator Dashboard's card and decision rules go, now that the plugin draws them with its parts`.

Deployment order: the plugin 1.123.92 first, the theme 1.32.28 after it is live, the same day (design 6.3).

## The controller's gate (not implementer tasks)

On the local Docker copy only, with the rig of Task 2:

1. **Baseline:** `bin/ui/copy.sh prepare` with the zip of Task 1's commit (1.123.90); `node bin/ui/snap.mjs --out <today>`; `node bin/ui/measure.mjs --out <today>.json`.
2. **Task 3's check:** the zip of Task 3's commit; snap into `<task3>`; `node bin/ui/compare.mjs <today> <task3>`: every file `same`.
3. **After:** the zip of Task 19's commit with the theme of Task 20 on the copy; snap into `<after>`; measure into `<after>.json`.
4. **Untouched surfaces:** `compare.mjs <today> <after>` reports `same` for every file whose area is not `admin-dashboard` (the Institution and Sponsor Dashboards, both Report Cards, every wp-admin tab, the guide).
5. **The moved surface:** every Administrator Dashboard picture, each card and each Decide panel open, at 1280 and 390, read against `DESIGN.md`, the design and the approved pictures; the card head as approved at 1280 and 390 (the help link included); the measures: four button looks and one height, heads 56 and 44, one field height, gaps only on the rhythm, every row action border 0.
6. **Keyboard:** Enter and Space on each fold head; Tab to each opener; an opener moves focus into its panel; Cancel and Escape put it back; `aria-expanded` follows; the focus ring on every control.
7. **No script:** the page with JavaScript off: every panel and confirming button on the page, a decision posted from each card.
8. **Phone:** at 390 no page scrolls sideways and nothing is cut.
9. **Posts:** one press of each decision on the copy, its mail in the mail log and its outcome on its card, as before.
10. **Then:** `release.py` check (standards, static analysis no higher than 133, suites), build, the outside review, the owner's look at the before and after pictures (the views nobody saw on the parts shown here, design 7), and his yes. Mirror and deploy only on that yes: plugin first, theme after.
