# A track question on student registration, per institution: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Administrators list the tracks each institution offers, and the student registration form asks for a track only at institutions offering two or more, writing the answer to Airtable's Students column "Requested track" (WordPress/WPCredits#304).

**Architecture:** One new class, `WPCPM_Institution_Tracks`, owns the list (kept in `WPCPM_Institution_Record`), its audit, the details card's Administrator-only section and its handler, and the answers the form may take. The registration form gains one field, `track`, whose requiredness `clean_all()` decides from the posted institution; `assets/js/forms.js` gains one hook that shows the question and fills its choices. The answer travels as `track` (the posted key) and `track_name` (the text the student saw), the way `institution_name` already travels, into the Airtable row and the Registrations tab.

**Tech Stack:** WordPress plugin PHP (stand-alone suites run as `php bin/test-*.php` from the plugin root), vanilla JS, the plugin's Airtable client.

**Spec:** `docs/specs/2026-11-11-registration-track-question-design.md` (approved by the product owner on 11 November 2026, "Yes, go ahead with the plan and build"). It builds on `docs/specs/2026-11-05-student-registration-design.md`.

**Branch and worktree:** `feature/registration-track-question` in `.worktrees/registration-track-question`, based on main `7c286c1a` (1.123.75). **Version: 1.123.76**, unless a read of live, main and the branches at Task 5 shows it taken; then the next free.

## Global Constraints

- Copy: no em or en dash anywhere (plain hyphens); "Administrators" is the one name for the people who run the program, and "program manager" appears in no string, guide or comment added here; full product names ("Student Report Card", "Institution Dashboard"); US English; no sentence starts with "Please".
- Nothing links to an Airtable form, in a string, a guide or the changelog.
- Every behavior change gets a check that fails first, in the suite named by its task. Before each commit: the task's suites pass; before Task 5's commit: every suite, `php bin/check-references.php`, `php bin/check-spelling.php`, `bash bin/check-standards.sh` with `PATH="$HOME/Projects/wpcredits-release/local-bin:$PATH"` (no errors).
- The handler is the fence: `WPCPM_Institution_Policy::decide( ACT_EDIT_DETAILS, ... )` allows the manager and the member grounds alike, so the Tracks offered handler also requires `GROUND_MANAGER`, whatever the page drew.
- Workers never ssh, never touch the live site, Airtable or Slack, never run `git stash`, never print the `wpcpm_settings` option.
- The registration's Status stays empty: nothing here assigns a track.

## Review Focus

1. **A list that shrinks to one runnable track** (an Administrator unticks one, or the Track Builder unpublishes one): the form asks nothing and refuses nothing for that institution. Pinned in Task 3.
2. **A posted track for an institution that is not asked** (a single-track school, or a hand-made post): ignored, and neither `track` nor `track_name` is stored. Pinned in Task 3.
3. **An Administrator saves the list while a listed track is unpublished:** the unpublished status stays in the stored list and comes back when the track is published again. Pinned in Task 1.
4. **A member's hand-made post to the save action:** refused with 403, nothing written, no audit row. Pinned in Task 2.
5. **A track renamed between the submission and the hourly retry's write:** Airtable gets the name the student saw (`track_name`), not the new one. Pinned in Task 4.

---

### Task 1: `WPCPM_Institution_Tracks`, the list and its audit

**Files:**
- Create: `includes/modules/class-wpcpm-institution-tracks.php` (required where the other institution modules are, `includes/modules/class-wpcpm-institutions.php` or the plugin's loader; follow how `class-wpcpm-institution-details.php` is loaded)
- Modify: `includes/modules/class-wpcpm-institution-history.php` (the words map near `'details_saved'`, line ~604)
- Test: `bin/test-institution-tracks.php` (new suite, in the style of `bin/test-institution-details.php`)

**Interfaces:**
- Consumes: `WPCPM_Institution_Record::get( $record_id )`, `::set( $record_id, array $answers )` (a blank value clears its key); `WPCPM_Program::labels()` (status => name, filled through the `wpcpm_program_labels` filter); `WPCPM_Institution_Audit::record( array $entry )`; `WPCPM_Mentors_Sync::is_record_id()`.
- Produces (later tasks rely on these exact names):
  - `const RECORD_KEY = 'tracks_offered';` the key in the institution's record, a list of statuses.
  - `const LOG_SAVED = 'tracks_saved';` the audit row's kind.
  - `const NOT_SURE = 'not-sure';` the posted value of "Not sure yet".
  - `const NOT_SURE_TEXT = 'Not sure yet';` the text written to Airtable for it, untranslated.
  - `public static function stored( $record_id ): string[]` the statuses kept, in the order kept; `array()` for none or a bad ID.
  - `public static function runnable( $record_id ): array` status => name, the stored statuses the site runs today, in `WPCPM_Program::labels()` order.
  - `public static function asks( $record_id ): bool` true when `runnable()` holds two or more.
  - `public static function answer_text( $record_id, $posted ): ?string` the track's name for a status in that institution's `runnable()`, `NOT_SURE_TEXT` for `NOT_SURE`, null for anything else.
  - `public static function save( $record_id, array $statuses, $actor ): bool|WP_Error` true when the list changed, false when nothing changed, `WP_Error` when the record refused the write.

- [ ] **Step 1: Write the failing checks** in `bin/test-institution-tracks.php`, with `wpcpm_program_labels` filtered to four tracks (`'In Sensei' => 'WordPress Credits Program 150h'`, `'In Sensei 50h' => 'WordPress Credits Program 50h'`, `'Designer Track' => 'Designer Track'`, `'Developer Track' => 'Developer Track'`) and a stand-in record store:
  - `stored()` of an institution with no record is `array()`; of a bad ID is `array()`.
  - `save( $id, array( 'Developer Track', 'In Sensei 50h' ), 7 )` returns true; `stored()` is those two; `runnable()` is `array( 'In Sensei 50h' => 'WordPress Credits Program 50h', 'Developer Track' => 'Developer Track' )` (labels order, not save order); `asks()` is true.
  - Saving the same list again returns false and writes no second audit row.
  - A status the site does not run is dropped by `save()`: `save( $id, array( 'In Sensei', 'Made Up' ), 7 )` stores `array( 'In Sensei' )`.
  - **Review Focus 3:** with `'Designer Track'` stored and then removed from the labels filter, `runnable()` leaves it out, and `save( $id, array( 'In Sensei' ), 7 )` stores `In Sensei` and `Designer Track` both; restoring the filter shows `Designer Track` in `runnable()` again.
  - One runnable track: `asks()` false; none: `asks()` false.
  - `answer_text( $id, 'Developer Track' )` is `'Developer Track'`; `answer_text( $id, 'not-sure' )` is `'Not sure yet'`; a status of another institution's list, `''` and `'Made Up'` give null.
  - A changed save writes one audit row: kind `tracks_saved`, the institution and subject the record ID, the actor, ground `manager`, `data` `array( 'statuses' => <the new list> )`, and a message naming the tracks by name ("Tracks offered were set: Designer Track, WordPress Credits Program 50h." or "Tracks offered were cleared." for an empty list).
  - The history sentence for kind `tracks_saved` is "The tracks the institution offers were changed." (no record ID, no slug).
  - `save()` answers a `WP_Error` (code `wpcpm_tracks_record`) when the record store refuses, and writes no audit row.

- [ ] **Step 2: Run** `php bin/test-institution-tracks.php`. Expected: FAIL (class not found).

- [ ] **Step 3: Implement the class** with the interfaces above. `save()` keeps `(posted ∩ labels keys) ∪ (stored − labels keys)`, in labels order for the first part and stored order for the second, writes `WPCPM_Institution_Record::set( $id, array( RECORD_KEY => $list ) )` (an empty list clears the key), and audits with ground `WPCPM_Institution_Policy::GROUND_MANAGER` and evidence as `WPCPM_Institution_Details::write()` uses. Add `'tracks_saved'` to the history words map.

- [ ] **Step 4: Run** `php bin/test-institution-tracks.php` and `php bin/test-institution-details.php`. Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "1.123.76: an institution's tracks offered, kept on the site and audited (#304)"`.

### Task 2: The details card's Tracks offered section and its handler

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-tracks.php` (render and handler)
- Modify: `includes/modules/class-wpcpm-institution-details-card.php` (`render()`, after the groups loop and before the closing `</div></details></section>`, around line 200)
- Modify: where the details card's admin-post actions are registered (follow `ACTION_SAVE` of the details card)
- Test: `bin/test-institution-details-card.php`

**Interfaces:**
- Consumes: Task 1's `stored()`, `runnable()`, `save()`; `WPCPM_Institution_Policy::decide()`, `::subject_institution()`, `::refusal()`, `GROUND_MANAGER`.
- Produces:
  - `const ACTION_SAVE = 'wpcpm_institution_tracks_save';` and `const FIELD = 'wpcpm_tracks_offered';` (posted as a list).
  - `public static function render( $record_id, $checker ): void`, called by the card with its `$checker` (true for the manager ground).
  - `public static function handle_save(): void`, hooked to `admin_post_` + `ACTION_SAVE`.

- [ ] **Step 1: Write the failing checks** in `bin/test-institution-details-card.php`:
  - As a member: the card draws a "Tracks offered" subtitle and the names of `runnable()` (or "None listed yet."), the sentence "The program sets the tracks your institution offers. To change them, write to <the program's contact address>.", and no form, no checkbox, no `ACTION_SAVE` field.
  - As an Administrator (manager ground): the same read rows, then a form posting to `admin-post.php` with action `ACTION_SAVE`, a nonce keyed `ACTION_SAVE . '_' . $record`, the record, one checkbox per track of `WPCPM_Program::labels()` named `wpcpm_tracks_offered[]`, ticked for the runnable stored ones, and a "Save tracks" button; a help line "Students are asked which track they want when two or more are ticked."
  - **Review Focus 4:** a member's hand-made post with a valid nonce answers 403 (`wp_die` with the policy's refusal), and `stored()` and the audit are unchanged.
  - An Administrator's post saves the posted list, flashes "The tracks offered are saved." (or "Nothing was changed." when nothing changed, or the record's refusal), and returns to the card's anchor; a posted status the site does not run is not saved.
  - A post with a nonce for another record is refused by `check_admin_referer()`.

- [ ] **Step 2: Run** `php bin/test-institution-details-card.php`. Expected: FAIL.

- [ ] **Step 3: Implement** the section and the handler. The handler's order is the card's own (`handle()`'s doc block): nonce keyed to the posted record, record shape, policy, then `GROUND_MANAGER` required, then `save()`. Draw the section with the card's existing classes (`wpcpm-details__subtitle`, the rows' definition list, the form's button) so it looks like the groups above it; the flash uses the card's own message slot or the plugin's one-shot notice, the builder's call, one outcome line where the section is. The program's contact address is the setting the registration and the emails use (`WPCPM_Mail` contact, or `WPCPM_Student_Registration_Form::CONTACT` if no setting holds one).

- [ ] **Step 4: Run** `php bin/test-institution-details-card.php`, `php bin/test-institution-tracks.php`, `php bin/test-institutions-dashboard.php`. Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "1.123.76: Tracks offered on the institution's details card, set by Administrators (#304)"`.

### Task 3: The question on the registration form

**Files:**
- Modify: `includes/modules/class-wpcpm-student-registration-form.php` (`fields()` after `'institution'` (moved after 'study' on 11 November 2026, see the design); `clean_all()` around line 798; `add_required()` around line 891 or `clean_all()`; `render_field()` around line 1261; the form's data attribute in `render_form()` around line 1169)
- Modify: `assets/js/forms.js` (one new hook, documented in the file's header list)
- Test: `bin/test-student-registration.php` (the form's section)

**Interfaces:**
- Consumes: Task 1's `asks()`, `runnable()`, `answer_text()`, `NOT_SURE`.
- Produces:
  - Field `'track'` in `fields()`: `group` `institution`, `label` "Which track would you like to join?", `type` `track`, `required` false (requiredness is `clean_all()`'s), `help` "Your institution offers more than one track. If you have not decided yet, choose Not sure yet."
  - Cleaned values, when the institution asks: `'track'` (the posted key: a status or `not-sure`) and `'track_name'` (`answer_text()`); when it does not ask, neither key.
  - The form element carries `data-wpcpm-tracks` (JSON: institution record ID => list of `[status, name]` for each offered institution where `asks()` is true, in `runnable()` order) and the track question's wrapper carries `data-wpcpm-tracks-for="<the institution select's name>"`.

- [ ] **Step 1: Write the failing checks** in `bin/test-student-registration.php`, with three institutions offered (A with tracks 50h and Developer, B with one track, C with none) and the labels filter of Task 1:
  - `fields()` has `track` straight after `institution`, with the label, help and group above.
  - A post for A without `track` is refused at `track` ("Choose a track, or Not sure yet."), every other answer kept; with `track` = `Developer Track` it is taken: values hold `track` = `Developer Track`, `track_name` = `Developer Track`; with `not-sure`: `track_name` = `Not sure yet`.
  - A post for A with `track` = `In Sensei` (not in A's list) is refused at `track`, as is a status A held but the site unpublished since the page was drawn.
  - **Review Focus 2:** a post for B or C with `track` = `Developer Track` is taken and its values hold no `track` and no `track_name`.
  - **Review Focus 1:** A with one of its two tracks unpublished asks nothing: a post without `track` is taken.
  - The form's `data-wpcpm-tracks` holds A only, `{"<A>":[["In Sensei 50h","WordPress Credits Program 50h"],["Developer Track","Developer Track"]]}`; B and C are absent.
  - The track question's wrapper is drawn `hidden` on a fresh form; drawn visible, with A's two tracks and Not sure yet as radios and the posted one checked, on a redraw after a refusal whose institution is A.

- [ ] **Step 2: Run** `php bin/test-student-registration.php`. Expected: FAIL.

- [ ] **Step 3: Implement** the field, its drawing (radios in a fieldset with its legend, the help, and the refusal under it, in the form's existing markup), the requiredness and checks in `clean_all()` where the institution is resolved, and the forms.js hook: on load and on the institution select's `change`, when the picked record ID is in `data-wpcpm-tracks`, unhide the wrapper and rebuild its radios from the map (plus Not sure yet), keeping a still-valid checked value; otherwise hide it and uncheck everything, so nothing posts. Without script the wrapper stays hidden until a refusal draws it (the spec's rule: a single-track school's student never sees it).

- [ ] **Step 4: Run** `php bin/test-student-registration.php`. Expected: PASS. (The form in a browser is the trial's, after the tasks.)

- [ ] **Step 5: Commit** `git commit -m "1.123.76: the registration form asks for a track where the institution offers two or more (#304)"`.

### Task 4: Where the answer goes: the Airtable row and the Registrations tab

**Files:**
- Modify: `includes/modules/class-wpcpm-student-airtable-row.php` (`ANSWERS`, `MODES`)
- Modify: `includes/modules/class-wpcpm-mentors-sync.php` (`fields()`, beside `'student_access'`)
- Modify: `includes/modules/class-wpcpm-student-registrations-tab.php` (`render_folded()`, around line 616)
- Modify: `bin/fixtures/students-registration-columns.json` (add `"Requested track": "singleLineText"`, the column this release asks for; keep `read` and note the addition in the fixture's own words if it has a note field)
- Test: `bin/test-student-registration.php` (the columns, row and tab sections)

**Interfaces:**
- Consumes: Task 3's stored `track` and `track_name`.
- Produces: `WPCPM_Mentors_Sync::fields()['student_requested_track'] = 'Requested track'`; `ANSWERS['track_name'] = 'student_requested_track'`; `MODES['track_name'] = array( 'singleLineText' => 'text', 'multilineText' => 'text' )`.

- [ ] **Step 1: Write the failing checks:**
  - The columns section: sixteen keys and the fixture's sixteen columns, `student_requested_track` => `Requested track`, of type `singleLineText` (update the existing "fifteen" checks to sixteen).
  - `cells_for()` with `track_name` = `Developer Track` writes `'Requested track' => 'Developer Track'`; with `Not sure yet`, that text; with no `track_name`, no cell.
  - A Students table without the column: the row is written without it and `left_out` names `Requested track` with `no-column`; the other cells are unchanged.
  - **Review Focus 5:** a registration stored with `track_name` = `Developer Track`, written by the hourly retry after the labels filter renamed that status to `Developer Track (2027)`, writes `Developer Track`.
  - The Registrations tab's folded answers show the track question's label with `track_name`, or "no answer" when the registration has none.

- [ ] **Step 2: Run** `php bin/test-student-registration.php`. Expected: FAIL.

- [ ] **Step 3: Implement** the map entries, the sync field, the fixture column and the tab's row (for type `track`, print `track_name`).

- [ ] **Step 4: Run** `php bin/test-student-registration.php`, `php bin/test-students-sync.php`, `php bin/test-institution-roster.php`. Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "1.123.76: the requested track goes to Airtable's Requested track column and the Registrations tab (#304)"`.

### Task 5: Guides, the earlier design's note, the version

**Files:**
- Modify: the Administrator guide's sections that describe an institution's details card and the registration form (find them with `grep -ln "details card\|Your institution's details\|student-registration\|Registrations tab" docs/sections/3*.md`), then `docs/administrators.md` as `php bin/build-docs.php` writes it
- Modify: `docs/sections/52-institution-details.md` (Institution guide)
- Modify: `docs/specs/2026-11-05-student-registration-design.md` (a dated note under decision 1)
- Modify: `wpcredits-program-manager.php` (header `Version` and `WPCPM_VERSION`), `readme.txt` (`Stable tag` and a `= 1.123.76 =` changelog entry naming WordPress/WPCredits#304), `languages/wpcredits-program-manager.pot` (`bash bin/make-pot.sh`)

- [ ] **Step 1: Read the versions.** `ssh` is the controller's, not the worker's: the controller confirms before this task that live, main and every `release/*` branch leave 1.123.76 free, and gives the worker the number to use.
- [ ] **Step 2: Guides.** Administrator guide: where Tracks offered is set (an institution's details card, reached through the Institution Dashboard's switcher), that two or more ticked tracks make the registration form ask, that an unpublished track drops out and comes back, and that the answer reaches Airtable's Requested track column and the Registrations tab, while an Administrator still assigns the track. Institution guide: one sentence that the program sets the tracks offered, and to write to the program to change them. Run `php bin/build-docs.php` twice (the second run changes nothing).
- [ ] **Step 3: The 5 November design.** Under its decision 1: "Reversed on 11 November 2026: a school offering several tracks asks its students for one. See `docs/specs/2026-11-11-registration-track-question-design.md`."
- [ ] **Step 4: Version and changelog.** The changelog entry, short, in the readme's voice: what an Administrator sets, what a student at a multi-track school sees, where the answer goes, and "(#304)".
- [ ] **Step 5: Every check.** Every `bin/test-*.php` suite passes; `php bin/check-references.php`, `php bin/check-spelling.php`, `bash bin/check-standards.sh` (local-bin on PATH) with no errors.
- [ ] **Step 6: Commit** `git commit -m "1.123.76: the guides, the earlier design's note and the version (#304)"`.

## After the tasks (the controller's, not a worker's)

1. Final whole-branch review; fixes in one wave.
2. Docker trial (`~/Projects/wpcredits-docker`, mail swallowed, Airtable refused by the safety plugin, Airtable faked where a write must be seen): an Administrator sets two tracks for one institution and one for another; the form at 1280 and 375 pixels with and without script; a registration for each; the Registrations tab; a member's view of the card. Report and screenshots under the release folder.
3. `release.py check`, `build`, `scan`, `review` for plugin 1.123.76.
4. The product owner's yes, then: the Airtable column "Requested track" (single line text) in Students, created through the connector on that yes; `mirror` and `deploy`; read back on live.
5. After the deploy: memory and the Now page; the roadmap if an item covers registration; a closing comment on #304 drafted for the product owner's yes; the program lead told by the product owner or on the product owner's word.
