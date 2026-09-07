# Deep check fixes, 1.99.0: implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix every one of the fifty findings of the 7 September 2026 deep check of 1.98.1 (one high, eight medium, forty-one low), fold in the three items parked by the clean-up release, and ship the result as plugin 1.99.0 with theme 1.24.3. (Theme 1.24.2 shipped with plugin 1.98.2 while this branch was open, so the theme work here lands as 1.24.3.)

**Architecture:** No new modules. Eight tasks, each owning a set of files nobody else touches, each fix landing with a check that fails on the unfixed code and passes after. Task 8 rewrites the seed fixture so the public mirror stops publishing real record IDs and addresses; Task 9 is the release itself.

**Tech Stack:** PHP 7.4 compatible WordPress plugin; standalone suites under `bin/test-*.php` that stub WordPress at their top; `bash bin/check-standards.sh`, `php bin/check-references.php`, `php bin/check-spelling.php`, `php bin/check-dead-annotations.php`; the block theme at `/Users/maciejpilarski/GitHub/wpcredits-theme` (Task 7 only).

**Spec:** the judged findings, `/private/tmp/claude-501/-Users-maciejpilarski-GitHub/25b9f120-1b7e-4cf2-9739-9a4891fc924e/scratchpad/deepcheck/deep-check-judged.json`, rendered per task as `.../deepcheck/task-N-findings.md` (each carries the claim, the concrete scenario, the proof, the refuter's evidence and the reviewer's proposed fix). The reviewers' full narratives sit beside them (`anonymous.md`, `sponsors.md`, `offers.md`, `agreement.md`, `admin.md`, `frontend.md`, `suites.md`) and their probes under `probes-*`; an implementer may re-run a probe to see a failure before fixing it. Where the plan's ruling and the reviewer's fix differ, the ruling wins.

## Global Constraints

- WordPress coding standards throughout: tabs, Yoda conditions, spaces inside parentheses, `array()` not `[]`; PHP 7.4 compatible; every string a person reads in US English; no em dash or en dash anywhere in code, comments, docs or commit messages (a plain hyphen instead); full product names ("Student Report Card", "Mentor Report Card", "Institution Dashboard", "Sponsor Dashboard", "Administrator Dashboard", "Collaboration Agreement"); the Required mark is `<span class="wpcpm-field__required">Required</span>`.
- Every code fix lands with a check in the suite the finding names (or a new one where the finding says "none"), and the implementer proves the check fails on the unfixed code first: apply the check, run it against the code before the fix (a temporary revert or a scratch copy), see it fail, then fix and see it pass. The task report names the failing output.
- The battery stays silent: `for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done` prints nothing. `php bin/check-references.php`, `php bin/check-spelling.php` and `php bin/check-dead-annotations.php` report clean. `bash bin/check-standards.sh` prints zero lines containing ` ERROR ` (its exit status changes in Task 8: until then exit 2 means warnings only).
- `bin/` and `docs/` never ship in the zip but ARE published on the public GitHub mirror. Nothing personal, no real Airtable record ID (`rec` followed by fourteen mixed characters that is not an obvious placeholder), no address outside `maciej@a8c.com`, its plus-addressed forms and reserved `.example` domains may be added to them.
- Version numbers move in Task 9 only (plugin 1.99.0) and in Task 7 for the theme (1.24.3). Nothing touches the live site; no ssh; no network; no subagents.
- Comments explain why and name the bug or the decision behind a rule; a docblock that describes behavior the code does not have is a defect.
- Every commit message starts with "Deep check fixes:" and names the finding ids it closes.

---

### Task 1: Sponsor members: capabilities that survive a detach, the routing clause, the history meta, dead code

**Findings:** FSPON-1 (high), FSPON-3, FSPON-6, FSUIT-7. Brief: `/private/tmp/claude-501/-Users-maciejpilarski-GitHub/25b9f120-1b7e-4cf2-9739-9a4891fc924e/scratchpad/deepcheck/task-1-findings.md`

**Files:**
- Modify: `includes/modules/class-wpcpm-sponsor-members.php` (`detach()` at about line 290 to 335; a new one-time repair)
- Modify: `includes/modules/class-wpcpm-sponsors-dashboard.php` (`should_route()` at about line 265)
- Modify: `includes/modules/class-wpcpm-sponsor-logo.php` (the dead items FSPON-6 names, at about line 498)
- Test: `bin/test-sponsor-members.php`, `bin/test-sponsors-dashboard.php`, `bin/test-sponsor-policy.php`

**Rulings:**
- FSPON-1: `WPCPM_Sponsor_Posts::drop_caps()` is called LAST in `detach()`, after the role block, and the comment names the bug: two `WP_User` objects for one account, the first one writing its stale capability array back whole in `remove_role()`. Add `maybe_repair_detached()` on `admin_init` behind a one-time flag option `wpcpm_sponsor_members_caps_repaired` (autoload yes, the shape of `WPCPM_Sponsor_Application::maybe_backfill_decided()`): for every account with `META_RECORD_ID_WAS` set and `META_RECORD_ID` absent and no role but `subscriber`, remove `edit_posts`, `delete_posts` and `upload_files` and write an audit row of kind `KIND_MEMBER_REMOVED` with `how => 'caps-repaired'`. Delete the flag in `uninstall.php`.
- FSPON-1 suite: the `WP_User` stub in `bin/test-sponsor-members.php` is rewritten on core's semantics (the capability array is read from the `wp_capabilities` meta store once when the object is built; `remove_role()`, `set_role()`, `add_cap()` and `remove_cap()` each write that array back whole; `get_user_by()` builds a fresh object per call), so the check can assert that after `detach()` the stored array holds `subscriber` alone, for both shapes: an account with the sponsor role and the three posting caps, and an account whose only role was the sponsor role (the `set_role( 'subscriber' )` branch). The reviewer's probe under `probes-sponsors/` and the refuter's under `probes-refute-sponsors/p1-detach.php` show a working stub.
- FSPON-3: replace the `edit_posts` clause of `should_route()` with the question it meant to ask: an account is left alone when it holds `WPCPM_Roles::CAP_MANAGE` or `edit_others_posts`; a real sponsor member (the stamp plus exactly the caps `attach()` grants) routes. Check both in `bin/test-sponsors-dashboard.php` by fixture.
- FSUIT-7: one check in `bin/test-sponsor-policy.php` that an account with `META_RECORD_ID_WAS` set and `META_RECORD_ID` absent is refused every sponsor action (the history meta grants nothing), and a grep-shaped check that no class reads `META_RECORD_ID_WAS` on an access path (assert the constant appears only in `detach()`, `attach()`'s re-add branch, the repair and the audit).
- FSPON-6: delete the dead items except `former_members_of()`, which stays with a docblock naming its future consumer (the Sponsor Dashboard people card of the Phase S1 plan) and nothing else changes; `php bin/check-dead-annotations.php` and `php bin/check-references.php` stay clean.

**Steps:**
- [ ] Step 1: In `bin/test-sponsor-members.php`, replace the `WP_User` stand-in with the core-shaped one and add the two detach checks; run the suite: both new checks FAIL on the unfixed code (the stored array still holds the three caps). Quote the failure in the report.
- [ ] Step 2: Move `drop_caps()` below the role block in `detach()`, add the comment, run the suite: PASS.
- [ ] Step 3: Add `maybe_repair_detached()` and its hook in `boot()`/`init()`, the flag option, the uninstall line, and a check that a fixture of two detached accounts (one holding the caps, one clean) ends with both clean and one audit row, and that a second call does nothing (flag set).
- [ ] Step 4: FSPON-3 checks (fail first), then the `should_route()` change.
- [ ] Step 5: FSUIT-7 checks.
- [ ] Step 6: FSPON-6 deletions, then `php bin/check-dead-annotations.php` and `php bin/check-references.php`.
- [ ] Step 7: Battery, checks, commit: `Deep check fixes: FSPON-1 FSPON-3 FSPON-6 FSUIT-7 - a detached sponsor member keeps no posting capability, the dashboard routes a real member, the history meta grants nothing`.

---

### Task 2: Sponsor logo and the roster claim

**Findings:** FSPON-2, FSPON-4, FSPON-5, FSUIT-8. Brief: `.../deepcheck/task-2-findings.md`

**Files:**
- Modify: `includes/modules/class-wpcpm-sponsor-roster.php` (`claim()` at about line 49), `includes/modules/class-wpcpm-sponsor-logo.php` (the ceiling claim at about line 124, Remove at about line 255)
- Test: `bin/test-sponsor-policy.php`, `bin/test-sponsor-logo.php`, `bin/test-image-upload.php`

**Rulings:**
- FSPON-2: `claim()` resolves the record the way `resolve_sponsor()` does: an actor holding `CAP_MANAGE` acts on the record the request names, validated against `WPCPM_Sponsors_Index`, and falls back to the stamp only when the request names none; an actor without it uses the stamp alone. Check: one account with both the manage capability and a stamp on record A, the switcher on record B, a profile save lands on B and the page shows B.
- FSPON-4: the daily upload ceiling is claimed after the `empty( $incoming )` check and before `WPCPM_Image_Upload::accept()`. Check: an empty submission leaves the day's count untouched; a real file still spends one before it is read.
- FSPON-5: Remove refuses with `logo-none` unless `WPCPM_Sponsors_Index::logo_record( $record )['source']` is `site`; Airtable is not PATCHed. Check both branches.
- FSUIT-8: a check in `bin/test-image-upload.php` that a file whose bytes `getimagesize()` rejects is refused even when its declared MIME is an image (mutation: with the cross-check removed the check fails; prove it once on a scratch copy and quote it).

**Steps:**
- [ ] Step 1: Write the four checks, run them against the unfixed code, quote the failures.
- [ ] Step 2: Fix `claim()`, the ceiling order, the Remove guard; run the three suites: PASS.
- [ ] Step 3: Battery, checks, commit: `Deep check fixes: FSPON-2 FSPON-4 FSPON-5 FSUIT-8 - the roster claim honors the switcher for a manager, an empty logo form spends nothing, Remove touches only a site logo`.

---

### Task 3: Codes, claims and the Tools section

**Findings:** FOFFR-1 (medium), FOFFR-2, FOFFR-3, FOFFR-4, FOFFR-5, FOFFR-6, FSUIT-6, FFRNT-6. Brief: `.../deepcheck/task-3-findings.md`

**Files:**
- Modify: `includes/modules/class-wpcpm-sponsor-codes.php` (`parse()` at about line 200 to 245, the lock takeover at about line 164, the duplicate refusal at about line 277, `str_getcsv()` at about line 218), `includes/modules/class-wpcpm-sponsor-offers.php` (`handle_save()`: parse before the offer post is created), `includes/modules/class-wpcpm-sponsor-tools.php` (`may_claim_reason()` and `handle_claim()` at about line 278; the claimed code markup at about line 465), `includes/modules/class-wpcpm-sponsor-claims.php` (a check only), `assets/css/sponsor.css` or the student sheet (the button reset for FFRNT-6)
- Test: `bin/test-sponsor-offers.php`, `bin/test-sponsor-tools.php`, `bin/test-sponsor-claims.php` (create if absent)

**Rulings:**
- FOFFR-1: `parse()` counts lines before it parses: more than `CODES_MAX` lines is refused with one sentence ("This list has %1$d lines; an offer takes at most %2$d.") and no parsing; error sentences are capped at a new `ERRORS_MAX = 10` with a closing sentence "and %d more lines have problems", so a paste of a million repeats costs a bounded string; `handle_save()` parses the codes before it creates the offer post, so a refused paste leaves no orphan draft. Checks: a 6,000-line paste yields one error and no codes; a 100-line paste of one repeated code yields 11 sentences; a refused paste on a new offer creates no post.
- FOFFR-2: the reviewer's first half is refuted (hiding held codes while the section is off is the specified meaning of the switch, spec rule 5). Fix only the stale-form half: `may_claim_reason()` gains a clause for the audience switch (`tools_students` for a student, `tools_mentors` for a mentor or manager) and `handle_claim()` refuses through it, so a form drawn before the switch flipped burns no code. Check: switch off, a stale claim POST, the pool unchanged, the refusal reason named.
- FOFFR-3: the stale-lock takeover becomes conditional on the stamp it read: `$wpdb->update( $wpdb->options, array( 'option_value' => $fresh ), array( 'option_name' => $name, 'option_value' => $stale ) )` and the takeover counts only when one row changed; the docblock describes exactly this and drops the recovery it promised. Check with a `$wpdb` stub that reports zero rows: no takeover.
- FOFFR-4: the refusal names the state ("Line %d was voided in this offer earlier.") and the card's codes block gets one sentence "A voided code cannot be added to the same offer again." Voided codes stay unusable in that offer.
- FOFFR-5: `str_getcsv( $line, ',', '"', '' )`. Check: a quoted cell with an escaped quote parses the same on PHP 7.4 and 8.4.
- FOFFR-6 and FSUIT-6: the checks the findings describe (a second claim under a held lock takes nothing; `code_for()` refuses another student's claim id), written so that deleting the guard fails them (prove once on a scratch copy).
- FFRNT-6: the claimed code is a real `<button type="button" class="wpcpm-tools__code">` wrapping the code text with `aria-label="Select your code"` and `aria-describedby` pointing at the existing hint; the plugin's sheet resets the button's appearance so it looks as it does today. Task 7 restates the reset in the theme's tokens. Check: the markup carries the button, the label and the description.

**Steps:**
- [ ] Step 1: Write every check, run against the unfixed code, quote the failures (FOFFR-1's memory case can be proved with a 100,000-line paste and a 64M memory limit on a scratch run, not in the suite).
- [ ] Step 2: Fix in the order FOFFR-1, FOFFR-5, FOFFR-3, FOFFR-4, FOFFR-2, FFRNT-6; suites PASS.
- [ ] Step 3: Battery, checks, commit: `Deep check fixes: FOFFR-1 to FOFFR-6 FSUIT-6 FFRNT-6 - a bounded codes paste, a conditional lock takeover, the audience switch on the claim path, the claimed code as a button`.

---

### Task 4: Sponsor agreement, the application queue, the sync shrink, the exposure pins

**Findings:** FAGRM-1 (medium), FAGRM-2, FAGRM-3, FAGRM-4, FSUIT-1 (medium), FSUIT-3 (medium), FSUIT-9. Brief: `.../deepcheck/task-4-findings.md`

**Files:**
- Modify: `includes/modules/class-wpcpm-sponsor-agreement.php` (`handle_withdraw()` at about line 1028; every handler that writes the status), `includes/modules/class-wpcpm-sponsors-sync.php` (the index write at about line 576 and `phase_revoke()`), `includes/modules/class-wpcpm-sponsor-application.php` (`render_decision()`, `render_details()` at about line 4016)
- Test: `bin/test-sponsor-agreement.php`, `bin/test-sponsors-sync.php`, `bin/test-sponsor-application.php`, `bin/test-sponsor-posts.php`

**Rulings:**
- FAGRM-1: `handle_withdraw()` sends `self::airtable_status_for( $record )` and rebuilds with the same value; the suite's assertion of `Not started` after a withdrawal (about line 615) becomes two checks: a withdrawal with nothing else standing sends `Not started`, and a withdrawal of a replacement while a returned or accepted document stands sends that document's status. The reviewer's mutation showed the suite never covered the disagreeing case; it does now.
- FAGRM-2: every handler that writes the status itself and succeeds deletes `META_AIRTABLE_PENDING` on the document (withdraw, accept, return, revoke, reinstate), so the mark only ever names a write still owed. Check: a pending mark set, then a successful accept, mark gone, the nightly retry writes nothing.
- FAGRM-3: a complete read that holds fewer than half of the rows the held index has is refused like an empty read: the index is not written, `phase_revoke()` does not run, and a sync message says "The Sponsors table returned %1$d records where the index holds %2$d; nothing was changed. Run the sync again to confirm." A read of half or more proceeds as today. Check both sides of the threshold.
- FAGRM-4: `render_decision()` and `render_details()` return early unless `current_user_can( WPCPM_Roles::CAP_MANAGE )`, matching the two sibling `render_decision()` methods. Check: a non-manager viewer gets an empty string from both.
- FSUIT-1: checks in `bin/test-sponsor-application.php` pinning the registration flags of `wpcpm_sponsor_app` to the values the code sets today (read `register_post_type()` in the class: `public`, `publicly_queryable`, `exclude_from_search`, `show_in_rest`, `show_ui`, `query_var`, `rewrite`; the safe values are false, false, true, false, and the code's own values for the rest). Prove once on a scratch copy that flipping `publicly_queryable` fails the check.
- FSUIT-3: checks in `bin/test-sponsor-posts.php` that call `guard_singular()`, `filter_content()` and `filter_rest()` directly in a stub world with a viewer who may not read a sponsor post at the Students and mentors level: the guard 404s (or redirects, whatever the code does), the content filter returns the gated text, the REST filter refuses; a permitted viewer passes each. Prove once that turning one into a no-op fails its check.
- FSUIT-9: checks that the agreement download nonce is keyed per document (a nonce for document A refused for document B) and that a post of another type with the same ID is refused.

**Steps:**
- [ ] Step 1: Write every check, run against the unfixed code, quote the failures (the pins in FSUIT-1, 3 and 9 pass on the unfixed code by design; for those, quote the scratch-copy mutation failure instead).
- [ ] Step 2: Fix FAGRM-1, FAGRM-2, FAGRM-3, FAGRM-4; suites PASS.
- [ ] Step 3: Battery, checks, commit: `Deep check fixes: FAGRM-1 to FAGRM-4 FSUIT-1 FSUIT-3 FSUIT-9 - a withdrawal never writes backwards, the pending mark names only a write owed, a shrunken read is refused, the queue renders for managers, the exposure flags and gates are pinned`.

---

### Task 5: The anonymous form: the guard, the ceilings, held rows, the PDF budget

**Findings:** FANON-1, FANON-2, FANON-3, FANON-4, FANON-5, FANON-6, FANON-7, FSUIT-2 (medium). Brief: `.../deepcheck/task-5-findings.md`. The live facts in `.../deepcheck/live-checks.md` apply: the site has no IPv6 address today and the form page is served uncached.

**Files:**
- Modify: `includes/class-wpcpm-form-guard.php` (`check_token()` at about line 186 to 214 and its docblocks at 33 to 35 and 167 to 170; `actor_key()` at about line 308; `truncate_ip()` at about line 485), `includes/class-wpcpm-ceiling.php` (the claim key at about line 282), `includes/modules/class-wpcpm-sponsor-application.php` (the submit path at about lines 1342 to 1503, `uploaded()` at about line 1757, the comment at about line 1968, the purge), `includes/modules/class-wpcpm-institution-application.php` (the same dwell outcome at about line 1520 to 1529), `includes/class-wpcpm-pdf-check.php` (a check only)
- Test: `bin/test-form-guard.php`, `bin/test-sponsor-application.php`, `bin/test-institution-application.php`, `bin/test-pdf-check.php`

**Rulings:**
- FANON-1: a token whose signature verifies but whose age is under `MIN_SECONDS` yields the outcome `held` with the signal `dwell-fast`, in both forms, so a genuine fast re-send reaches the queue and the acknowledgement; a token that does not verify stays spam. Check: the exact sequence the refuter ran (submit, bounce, redraw, re-send at age 0) ends held, not spam, with one pending row and the held-row mails.
- FANON-4: `check_token()` refuses a timestamp whose canonical spelling differs (`(string) (int) $parts[0] !== $parts[0]`), and the single-use claim is keyed on the canonical `scope|issued|random|signature`; the docblocks say what single use means (once per twelve-hour bucket, and why that is enough under the five-an-hour ceiling). Checks: sixty leading-zero spellings of one token all refused after the first use; a replay of the identical string refused.
- FANON-2: `actor_key()` keys an IPv6 client on its /64 (`inet_pton()`, mask the last eight bytes to zero, `inet_ntop()`), an IPv4 client on the whole address; the docblock explains the /64 choice. Check: two addresses in one /64 share a key; two /64s do not; IPv4 unchanged.
- FANON-5: `truncate_ip()` works on bytes: `inet_pton()`, keep the first eight bytes of an IPv6 address, the first three of an IPv4 one, treat `::ffff:a.b.c.d` as IPv4, `inet_ntop()`. Checks: the compressed, the uncompressed and the IPv4-mapped forms.
- FANON-3: the site ceiling is claimed before `accept_logos()`; a row the daily degrade holds stores no files and carries the signal `files-skipped`, and its acknowledgement says the company can send its logo after approval; the purge treats held rows like rejected ones (`application_rejected_days`, 365 by default). Checks: past the degrade, zero attachments and the signal; a held row older than the window is purged.
- FANON-6: delete the false sentence of the comment (the row is authored by nobody on purpose; say why).
- FANON-7: `uploaded()` guards each member with `is_scalar()` and treats a non-scalar as `UPLOAD_ERR_NO_FILE`. Check: an array-shaped `$_FILES` field raises no warning (an error handler in the check) and yields the no-file outcome.
- FSUIT-2: a check in `bin/test-pdf-check.php` that a PDF holding more flate streams than `SCAN_MAX_STREAMS` is scanned within the budget (streams visited equals the budget, peak memory under a stated bound) and that a PDF over `SCAN_MAX_TOTAL` bytes of streams stops at the total. Prove once on a scratch copy that removing the loop guard fails the check (the reviewer's 600-stream probe under `probes-suites/` shows the shape).

**Steps:**
- [ ] Step 1: Write every check, run against the unfixed code, quote the failures.
- [ ] Step 2: Fix in the order FANON-4, FANON-2, FANON-5 (the guard), then FANON-1 in both forms, FANON-3, FANON-7, FANON-6; suites PASS.
- [ ] Step 3: Battery, checks, commit: `Deep check fixes: FANON-1 to FANON-7 FSUIT-2 - a fast re-send is held not spammed, canonical single-use tokens, /64 keys, byte-true IP truncation, no files for a held row, the PDF budget pinned`.

---

### Task 6: The institution agreement option, the Administrator Dashboard cards, the report reopen, the student modules

**Findings:** FADMN-1 (medium), FADMN-2, FADMN-3, FADMN-4, FADMN-5, FADMN-6, FADMN-7, FSUIT-4 (medium). Brief: `.../deepcheck/task-6-findings.md`. Plus the three items parked by the clean-up release (P1 to P3 below).

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-agreement.php` (`airtable_block()` at about line 3476 and its docblock), `includes/modules/class-wpcpm-administrators-cards.php` (`collect()`/`applications()` at about line 105, the Recently closed list at about line 1398, `card_open()` at about line 1625, `sponsors()` at about line 676), `includes/class-wpcpm-return.php` (`ANCHORS`), `includes/modules/class-wpcpm-semester-report-screen.php` (`handle_reopen()` at about line 2249), `includes/modules/class-wpcpm-students-dashboard.php` (`render_module()` at about line 472), `includes/modules/class-wpcpm-institution-request.php` (`settle()` stamps the closing time), `includes/modules/class-wpcpm-sponsor-application.php` (`decided_posts()` orderby)
- Test: `bin/test-institution-agreement.php`, `bin/test-administrators-dashboard.php`, `bin/test-semester-report.php`, `bin/test-student-modules.php`, `bin/test-institutions-dashboard.php`, `bin/test-return.php`, `bin/test-sponsor-application.php`

**Rulings:**
- FADMN-1: the default lives inside `airtable_block()`: its `status` is `self::known_airtable_status( $record )` (the option's status when the option exists, the index row's otherwise) and its `document` is the option's Drive link or empty; the docblock is rewritten to say so and why (an empty status counts as open and would let the next Generate write `Template generated` over `Accepted` or `Revoked`, and the option is absent most of the day for an out-of-stage institution because the sync's revoke phase deletes it nightly). The retry's explicit pass from 1.98.1 stays. Checks in `bin/test-institution-agreement.php`: with the option absent and the index row holding `Revoked`, an upload with a failed PATCH, a return of a replacement, and a withdraw each leave the option's status `Revoked`; and the refuter's second consequence, a return while `Accepted` stands leaves `settles()` true.
- FADMN-2: totals come from `query( $states, 'ids' )`; post objects are fetched only for `self::LIMIT` rows, the shape the sponsor application card already uses. Check: with sixty decided rows the card draws fifty and reports sixty, and the query counter in the suite shows no per-row object load beyond fifty.
- FADMN-3: `settle()` stamps `META_CLOSED_AT` (`time()`), the Recently closed list orders by it (`meta_value_num` desc, ID desc) and prints it with the words "closed on"; a row without the stamp (closed before 1.99.0) sorts by its post date and prints "opened on". Check both.
- FADMN-4: `handle_reopen()` bounces with `not-approved` when `STATE_APPROVED !== self::state( $post )`, the way `handle_approve()` bounces with `is-approved`. Check: a draft reopened writes no log entry.
- FADMN-5: `render_module()` builds the rendered list first (each body buffered, empty bodies dropped) and passes the index and the count of THAT list to the mover, so the arrows are always right; the handler is unchanged. Check: with one empty module, the first rendered module has no "up" arrow and the last no "down".
- FADMN-6: the three card ids join `WPCPM_Return::ANCHORS`, so the docblock is true. Check in `bin/test-return.php`.
- FADMN-7: the three member cases the student suite already has (student, mentor, sponsor member cannot move institution modules) added to `bin/test-institutions-dashboard.php`; prove once that removing the manager check fails them.
- FSUIT-4: `bin/test-administrators-dashboard.php` ends with the battery's summary line (`ALL PASS (N checks)`) like every other suite.
- P1 (parked, clean-up): `decided_posts()` orders in SQL by `array( 'meta_value_num' => 'DESC', 'ID' => 'DESC' )`, so the bound is deterministic; the usort stays as belt and braces. Check: two rows with one stamp, limit one, the higher ID wins.
- P2 (parked): `sponsors()` computes the semester start in the site's timezone (`new DateTimeImmutable( $since . ' 00:00:00', wp_timezone() )`) and adds `since_display` in the site's date format (`wp_date( get_option( 'date_format' ), $from )`) for the tile's sentence; `since` keeps `Y-m-d` for the suite. Check: a claim at 23:30 site time on the last day before the semester is not counted; the sentence shows the site format.
- P3 (parked): the `airtable_block()` docblock (covered by FADMN-1).

**Steps:**
- [ ] Step 1: Write every check, run against the unfixed code, quote the failures.
- [ ] Step 2: Fix FADMN-1 first, then the rest; suites PASS.
- [ ] Step 3: Battery, checks, commit: `Deep check fixes: FADMN-1 to FADMN-7 FSUIT-4 and the clean-up's parked P1 P2 - the agreement option never stores an empty status, bounded application totals, closing dates, a guarded reopen, right arrows, deterministic decided order, the semester in site time`.

---

### Task 7: Front end: forms.js, modules.js, administrator.css and the theme's table rule

**Findings:** FFRNT-1, FFRNT-2, FFRNT-3, FFRNT-4, FFRNT-5, FFRNT-7 (theme). Brief: `.../deepcheck/task-7-findings.md`

**Files:**
- Modify: `assets/js/forms.js` (`releaseOnRestore()` at about line 175), `assets/js/modules.js` (`arrange()` at about line 86 and the non-ok branch at about line 147), `assets/css/administrator.css` (about lines 34 and 159)
- Modify in the theme repo `/Users/maciejpilarski/GitHub/wpcredits-theme`: `assets/css/dashboard.css` (the table rules at about line 1327 and the per-dashboard sheets), `style.css` and `readme.txt` (version 1.24.3 and a changelog entry), plus the token restatement of Task 3's code button reset
- Test: `bin/test-submit-guard.php` (FFRNT-1 mutation check); the theme's `php bin/check-selectors.php`

**Rulings:**
- FFRNT-1: `releaseOnRestore()` also releases every button whose `form` attribute names the restored form (`document.querySelectorAll( '[form="' + form.id + '"]' )`). Check in `bin/test-submit-guard.php` as the finding describes (crippling the release loop fails it).
- FFRNT-2: `arrange()` remembers `document.activeElement` and focuses it again after re-inserting, and skips the re-insert entirely when the answer's order already matches the DOM.
- FFRNT-3: on a non-ok answer the module goes back where it was and the live region says "The move was not kept."; the docblock is corrected.
- FFRNT-4: the three rules read at 14px. FFRNT-5: the muted tile paints with one token (`--wpc-ink-60` or the theme's equivalent at 4.5:1 or better), no stacked opacity.
- FFRNT-7: one shared table rule in the theme for every dashboard table, taking the Institution Dashboard's `.wpcpm-mentee__table` values; the per-page sheets keep layout only. Theme 1.24.3 with a readme entry; `php bin/check-selectors.php` in the theme passes. The theme commit is separate from the plugin commit and carries the same "Deep check fixes:" prefix.
- JavaScript has no suite: the implementer states in the report how each change was exercised (a static walk-through of the DOM sequence is acceptable; the controller verifies in a browser after deploy).

**Steps:**
- [ ] Step 1: FFRNT-1 check first (fails), then the fix.
- [ ] Step 2: FFRNT-2, FFRNT-3, FFRNT-4, FFRNT-5 in the plugin; commit: `Deep check fixes: FFRNT-1 to FFRNT-5 - buttons released after Back, focus kept on a module move, a refused move undone, 14px and one-token contrast on the Administrator Dashboard`.
- [ ] Step 3: FFRNT-7 and the code button reset in the theme, version 1.24.3, `php bin/check-selectors.php`, commit in the theme repo: `Deep check fixes: FFRNT-7 - one table rule for every dashboard; 1.24.3`.

---

### Task 8: Fixtures and tooling: what the public mirror publishes, the build, the checks

**Findings:** FSUIT-5 (medium), FSUIT-13, FSUIT-10, FSUIT-11, FSUIT-12. Brief: `.../deepcheck/task-8-findings.md`

**Files:**
- Create: `bin/anonymize-fixtures.php`
- Modify: `bin/fixtures/institutions-index-seed.json` (rewritten by the script), every `bin/test-*.php` that carries a real record ID or a real address, `bin/build`, `bin/check-standards.sh`, `bin/check-spelling.php`
- Test: the whole battery

**Rulings:**
- FSUIT-5 and FSUIT-13: a deterministic anonymizer, `bin/anonymize-fixtures.php`, rewrites the seed and the suites in place and is kept in the repo so the fixture can be regenerated: every Airtable record ID that is not already an obvious placeholder becomes `recSEED` followed by a nine-digit index in order of first appearance (one mapping for the whole of `bin/`), every institution name becomes "Institution <index>", every person name becomes "Person <index>", every email becomes `seed<index>@institution.example`, every website becomes `https://institution-<index>.example/`, phone numbers are removed; the file's `_comment` says it is synthetic, how it was made, and that it holds no real record. Then run the battery and update any check that asserted a real value (search first: `grep -rnoE "\brec[A-Za-z0-9]{14}\b" bin | grep -vE "rec(SEED|STU|INS|SPO|[A-Z]{3}0{5})"` and a grep for the partner university's own mail domain over `bin` must both print nothing at the end). Nothing outside `bin/` changes.
- FSUIT-10: `bin/build` runs with `set -euo pipefail`, checks the zip command's exit status, and the unreachable `style.css` fallback is removed.
- FSUIT-11: `bin/check-standards.sh` exits 0 when phpcs reports no errors and prints "N warnings" for the record, exits 1 on any error; the Global Constraints of later plans read "exit 0 required". Update the sentence in `docs/sections/34-admin-operations.md` if it mentions the exit code (grep for "exit 2").
- FSUIT-12: `bin/check-spelling.php` also scans `blocks/*/block.json`.

**Steps:**
- [ ] Step 1: Write and run the anonymizer; the two greps print nothing; run the battery; fix the checks that break; quote the counts (IDs, addresses, names replaced).
- [ ] Step 2: `bin/build` and `bin/check-standards.sh` changes; run `bash bin/build` once (the zip lands at `/Users/maciejpilarski/GitHub/wpcredits-program-manager.zip`; the version inside is still 1.98.1 at this point, which is expected) and `bash bin/check-standards.sh; echo $?` prints 0.
- [ ] Step 3: `check-spelling.php` scope; commit: `Deep check fixes: FSUIT-5 FSUIT-10 to FSUIT-13 - a synthetic seed fixture and suites with no real record, a build that fails loudly, standards exit 0 when clean, block.json spelled`.

---

### Task 9: Release 1.99.0: versions, changelog, docs

**Files:**
- Modify: `wpcredits-program-manager.php` (header `Version` and `WPCPM_VERSION`), `readme.txt` (Stable tag and a `= 1.99.0 =` entry above the newest), `docs/sections/34-admin-operations.md` (a paragraph "The deep check fixes (1.99.0)" after the clean-up paragraph, naming the high finding and the eight mediums in plain words and the anonymized fixture), then `php bin/build-docs.php`

**Steps:**
- [ ] Step 1: The three edits; the changelog entry lists the fixes by area in one sentence each (members, logo and roster, codes and claims, agreement and queue and sync, the form guard, the institution agreement option and the Administrator Dashboard, the front end, the fixtures and tooling).
- [ ] Step 2: `php bin/build-docs.php`; `grep -rn "1\.98\.1" wpcredits-program-manager.php readme.txt` prints nothing but the changelog history.
- [ ] Step 3: Battery, every check, `bash bin/build` (version inside 1.99.0), commit: `1.99.0: the deep check fixes`.
