# Designer Track and three-hour syncs, 1.98.2: implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the Designer Track as a fourth program with its own Student Report Card report form, organized by the Learn course's modules and lessons, and move every Airtable sync onto a three-hour cadence; ship as plugin 1.98.2 with theme 1.24.2.

**Architecture:** The track is derived from the Airtable status like the Developer Track (one map entry per track in `WPCPM_Program`); the report form gains a `design` field set and two controls (`select`, `image`), the image control reusing `WPCPM_Image_Upload` and the sponsor logo's Airtable attachment write; the two daily syncs adopt the three-hour recurrence the students sync already registers.

**Tech Stack:** PHP 7.4 compatible WordPress plugin; standalone suites under `bin/test-*.php`; the block theme at `/Users/maciejpilarski/GitHub/wpcredits-theme` (Task 3).

**Spec:** `docs/specs/2026-09-07-designer-track-design.md` (this plan argues from it; read it first). The Developer Track's precedent: `docs/specs/2026-08-26-developer-track-program-design.md`.

## Global Constraints

- WordPress coding standards throughout (tabs, Yoda conditions, spaces inside parentheses, `array()`), PHP 7.4 compatible, every string a person reads in US English, no em dash or en dash anywhere (a plain hyphen), full product names ("Student Report Card", "Mentor Report Card", "Administrator Dashboard"), the Required mark is `<span class="wpcpm-field__required">Required</span>`, comments explain why and name the decision behind a rule, docblocks true.
- Airtable field names are keys and are copied exactly from the spec's table, including the typographic apostrophe in `Site’s` and the capitalization of each name.
- Every behavior lands with a check that fails on the code before the change (prove it, quote it in the report) and passes after. The battery stays silent (`for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done` prints nothing); `php bin/check-references.php`, `php bin/check-spelling.php`, `php bin/check-dead-annotations.php` clean; `bash bin/check-standards.sh` with zero lines containing ` ERROR ` (exit 2 means warnings only).
- `bin/` and `docs/` are public on the mirror: nothing personal, no real Airtable record ID, no address outside `maciej@a8c.com`, its plus-addressed forms and `.example` domains.
- Version numbers move in Task 3 only (plugin 1.98.2; theme 1.24.2). Nothing touches the live site; no ssh; no network; no subagents.
- Every commit message starts with "Designer Track:" and names the task.

---

### Task 1: The track, the settings, the fixture, the three-hour syncs

**Files:**
- Modify: `includes/class-wpcpm-program.php` (`STATUS_DESIGN`, `labels()`, `courses()`, `track()`, `hours_targets()`, `badge()`), `includes/class-wpcpm-settings.php` (`defaults()` `student_statuses`; `maybe_upgrade()`: the statuses merge and the cron rescheduling), `includes/modules/class-wpcpm-institutions-sync.php` and `includes/modules/class-wpcpm-sponsors-sync.php` (the recurrence and the offsets), `includes/modules/class-wpcpm-students-sync.php` (only if the recurrence constant or the registrar needs to be shared), `bin/fixtures/reports-table-fields.json`
- Test: `bin/test-student-program.php`, `bin/test-settings.php`, `bin/test-institutions-sync.php`, `bin/test-sponsors-sync.php`, `bin/test-fixtures.php`

**Interfaces produced:** `WPCPM_Program::STATUS_DESIGN`, `WPCPM_Program::track()` returning `'design'`, `WPCPM_Program::badge()` returning `'design'`; the fixture entries Task 2's fields name.

**Rulings:**
- `hours_targets()` gains `'Designer Track' => 150` (spec assumption 6.1), with a comment naming the Learn page as the source and the Developer Track's explicit 0 as the contrast.
- `labels()` gets the same comment the Developer Track entry carries (the map gates `is_track()`).
- `maybe_upgrade()` merges `Designer Track` into a saved `student_statuses` that lacks it, exactly as it does for the Developer Track; check: a stored setting without it gains it, one that has it is untouched, and a stored setting where a manager deliberately removed `Developer Track` is not re-added (read how the existing merge distinguishes the two cases and keep its rule).
- Three-hour syncs: the institutions sync and the sponsors sync schedule on `WPCPM_Students_Sync::EVERY_THREE_HOURS` (registered by `cron_interval()` already; do not register it twice), first runs at the cycle offsets the spec gives (+1:30 institutions, +2:00 sponsors, the students sync stays at +0:30), keeping their hook names; `maybe_upgrade()` (or the version-change path the plugin already uses) unschedules the two daily events and schedules the three-hour ones once, idempotently. Checks: after the upgrade path runs, `wp_get_schedule()` (stubbed) reports the three-hour recurrence for both hooks and no daily event remains; a second run changes nothing; a fresh activation schedules three-hour events directly. The daily housekeeping crons and the weekly mentor checker are untouched (pin one of each).
- The fixture gains the twenty-one fields of spec section 1 with their Airtable types; `php bin/test-fixtures.php` and `php bin/check-references.php` stay clean.

**Steps:**
- [ ] Step 1: Checks first (program map, statuses merge, the schedules, the fixture), run against the current code, quote the failures.
- [ ] Step 2: The changes; suites PASS; battery silent; checks clean.
- [ ] Step 3: Commit: `Designer Track: Task 1 - the track in the program map, the statuses, the fixture, and every sync on the three-hour clock`.

---

### Task 2: The report form for the track, with the select and image controls

**Files:**
- Modify: `includes/modules/class-wpcpm-student-report-form.php` (`fields( 'design' )`, `render_field()` for `select` and `image`, `clean()` for both, `handle_save()` for the attachment write, the image handlers), `includes/class-wpcpm-image-upload.php` (only if a rule is missing), `uninstall.php` (the new user meta), `assets/css/student.css` (the image control's base rules: a thumbnail at most 240px wide, the Replace and Remove row)
- Create: `bin/test-report-images.php`
- Test: `bin/test-report-form.php`, `bin/test-report-images.php`

**Interfaces consumed:** Task 1's `WPCPM_Program::track()` `'design'` and the fixture entries. **Produced:** the `wpcpm-report__image` markup Task 3 dresses in the theme.

**Rulings:**
- `fields( 'design' )` is exactly spec section 4: the order, the groups, the leads (each practical lesson's lead is the Learn lesson title verbatim), the labels written for the student, the keys copied exactly. `Beginner WordPress Designer` is a required mark under its own lead and is NOT in the optional courses for this track; the user-level marks and the two optional developer courses are absent.
- The `select` control: `<select>` with an empty "Choose one" first option; options from the spec entry `'options' => array( 'WordPress Studio', 'MAAMP', 'DevKinsta' )`; `clean()` accepts an option name or empty, refuses anything else.
- The `image` control: `<input type="file" accept="image/png,image/jpeg,image/webp">` inside the form (the form gains `enctype="multipart/form-data"` when any image field is present); on save, each uploaded file goes through `WPCPM_Image_Upload::accept()` with the shared rules and `store()` into the Media Library (author the student, title the field's label), the attachment ID is kept in user meta `_wpcpm_report_images` (field name => ID, one array per student), and the Airtable cell receives `array( array( 'url' => wp_get_attachment_url( $id ), 'filename' => basename ) )`; a field with no new file is not written. Ceiling: twenty accepted uploads per student per day through `WPCPM_Ceiling`; the twenty-first is refused with a flash naming the limit. "Remove" (a button per image field, `form=` pattern or its own form, nonce, the student themself or a manager) deletes the site's attachment, unsets the meta entry, and writes an empty list to the cell. The card shows the site's copy as a thumbnail linked to the full file; when the sync says the cell holds a file and the site has no copy, the text "1 file on record in Airtable" (pluralized with `_n()`); the sync stores the attachment count per image field in the program row (read the students sync's row shape and add one key, `report_files`, a map of field name to count) so the card never uses an Airtable URL.
- The `CSS` field renders as a textarea with `spellcheck="false"` and a monospace class.
- Every new string a person reads is US English and written for the student; docblocks explain the public-URL decision (spec section 5) and the expiring Airtable URLs.

**Steps:**
- [ ] Step 1: Checks first: the `design` set's order and groups against a fixture list of expected keys; the select clean; the image accept and store (use the image fixtures the sponsor logo suite uses), the ceiling, the Airtable payload, Remove, the "on record" text; run against the current code and quote the failures.
- [ ] Step 2: The changes; suites PASS; battery silent; checks clean.
- [ ] Step 3: Commit: `Designer Track: Task 2 - the report form for the track, with the select and image controls`.

---

### Task 3: The chip, the docs, the theme, the release

**Files:**
- Modify: `assets/css/dashboard.css` or the sheet that styles the track chip in the plugin (the `design` chip); `docs/sections/` (the students guide section on the report form gains a paragraph on the Designer Track; `docs/sections/34-admin-operations.md` gains a paragraph "Syncs every three hours (1.98.2)"), then `php bin/build-docs.php`; `wpcredits-program-manager.php` (header `Version` and `WPCPM_VERSION` 1.98.2), `readme.txt` (Stable tag and a `= 1.98.2 =` entry above the newest); `languages/wpcredits-program-manager.pot` regenerated with `sh bin/make-pot.sh` (WP-CLI is installed and the script prefers it)
- Modify in the theme repo `/Users/maciejpilarski/GitHub/wpcredits-theme`: the chip rule for `design` beside `dev`, the image control's dressing (`.wpcpm-report__image`, thumbnail border `--wpc-line-card`, the Replace and Remove row at the form's control size), `style.css` and `readme.txt` (1.24.2 and a changelog entry); `php bin/check-selectors.php` passes
- Test: the whole battery

**Rulings:**
- The chip copies the `dev` chip's rule with its own color token; no new sizes.
- The changelog entry names the track, the two new controls, the three-hour syncs, and the upgrade step that reschedules the two daily events.
- The theme commit is separate and carries "Designer Track:" too.

**Steps:**
- [ ] Step 1: The plugin edits, `php bin/build-docs.php`, `sh bin/make-pot.sh`, battery, checks, `bash bin/build` (version inside 1.98.2); commit: `Designer Track: Task 3 - the chip, the guides, the translation template; 1.98.2`.
- [ ] Step 2: The theme edits, `php bin/check-selectors.php`, commit in the theme repo: `Designer Track: the track chip and the image control; 1.24.2`.
