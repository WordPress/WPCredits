# Semester report: drafted by the site, approved by a program manager

**Date:** 4 September 2026
**Component:** `wpcredits-program-manager` 1.90.0, ships as 1.91.0 (no theme change expected)
**Status:** approved design, not yet implemented
**Amends:** section 7.9 and decision 12 of the Institutions module spec of 2 September 2026. Everything that spec says about the report's snapshot, sections, quotes, consent, print document and `ACTION_ASK` stands unless a line below says otherwise.

Phase 6 shipped the semester report as a draft the institution generates and edits on its own dashboard. The product owner has since decided that the report is the program's document, not the school's: the site drafts it, a program manager reviews and edits it, and the institution sees it only once it is approved. This document is the delta.

House rules that apply to every line below: comments explain why and name the bug or decision behind the rule; no em dashes; full product names ("Student Report Card", "Mentor Report Card", "Institution Dashboard", "Administrator Dashboard"); every behaviour worth trusting has an assertion in `bin/test-*.php`.

---

## 1. Settled by the product owner (4 September 2026)

1. **The institution does not create or edit the report.** It reads an approved report on the Institution Dashboard and downloads it as a PDF. Nothing else.
2. **The draft is created automatically when a cohort ends, and on demand.** A daily job drafts the report for every institution cohort that is due; a program manager can also press *Draft now* for any institution and cohort at any time.
3. **Program managers review, edit if needed, and approve.** Approval is what makes the report visible to the institution.
4. **`ACTION_ASK` stays managers only** (decided 3 September 2026). The party that benefits from a yes is not the party that asks for it.
5. **Delivery order:** this piece first, then the Administrator Dashboard, then the Sponsor module. The Administrator Dashboard lists the drafts this piece creates.

---

## 2. What changes, and what does not

| Stays exactly as shipped in 1.89.0 and fixed in 1.90.0 | Changes here |
| --- | --- |
| Post type `wpcpm_inst_report`, one post per institution and cohort, always `private` | Two states, `draft` and `approved`; `final` is upgraded to `approved` |
| The snapshot (`_wpcpm_report_data`), its eight sections, the deliberately-never-generated list | Who may edit: managers only |
| Quote picker, `offered_field()`, translation, `has_quote`, unreleased quotes stored nowhere | The institution's card: a read-only list of approved reports |
| Consent read at generation and again on every render; a failed read stops the document | A daily autodraft job, a Draft now action, an approval action |
| The print document, `ACTION_PRINT`, the byte-identical `unknown()` refusal | Two mails: to managers when a draft lands, to the institution when a report is approved |
| Stale-save stash, revisions, the generation lock | A report log and three data-API methods the Administrator Dashboard reads |
| `ACTION_ASK`, its queue and cron | Settings: `report_autodraft`, `report_autodraft_grace_days`, `report_notify` |

Decision 12 of the Institutions spec ("a generated draft the institution edits") is withdrawn. Decision 11 (cohort comparison is participation counts only) and every privacy rule stand.

---

## 3. States and the upgrade

`WPCPM_Semester_Report`:

- `STATE_DRAFT = 'draft'` stays. `STATE_FINAL` is renamed `STATE_APPROVED = 'approved'`; nothing keeps the old name (`bin/check-references.php` and `bin/check-dead-annotations.php` would both object to an alias nobody reads).
- `set_state()` accepts exactly those two.
- `generate()` is refused on an approved report, as it was on a final one. Reopening is a deliberate act with a button of its own.
- New meta: `META_APPROVED = '_wpcpm_report_approved'` (`array( 'at' => int, 'by' => int )`, the manager's user ID; cleared on reopen) and `META_ORIGIN = '_wpcpm_report_origin'` (`auto` or `manager`, set once at first generation and never changed, so the log can say how a report came to exist).
- **Upgrade.** `OPT_STATE_VERSION = 'wpcpm_report_state_version'`, `STATE_VERSION = 2`. `maybe_upgrade()` on `init` (priority 5, beside the roles and settings upgrades) flips every post of the type whose state meta is `final` to `approved`, stamps `META_APPROVED` with `at` = the post's modified time and `by` = 0 (nobody, and the log says "before approval existed"), and writes the version option. Idempotent; a second run finds nothing to do. The TEST institution holds the only reports today.

---

## 4. The policy

`WPCPM_Institution_Policy::grounds()` changes two rows and gains none:

| Action | Grounds before | Grounds after |
| --- | --- | --- |
| `ACT_VIEW_SEMESTER_REPORT` | manager, member | manager, member (member sees approved reports only; section 6) |
| `ACT_EDIT_SEMESTER_REPORT` | manager, member | **manager** |

The member ground stays behind the agreement gate for viewing, as every non-agreement action is. The state filter for members is not a policy clause: the policy answers "may this account look at this institution's reports at all" and the report screen answers "which of them", because a state is a fact about a document and the policy is a fact about a person. `bin/test-institution-policy.php` asserts the map row by row, so the change is a failing assertion until updated, which is the intended way to change it.

Every handler that edits (`handle_generate`, `handle_save`, `handle_restore`, `handle_refresh_consent`, `handle_approve`, `handle_reopen`, `handle_draft`) asks `ACT_EDIT_SEMESTER_REPORT`. `handle_refresh_consent` used to be reachable by a member on a final report so that a withdrawal could reach an issued document; that need is met by the render-time re-check, which runs for every viewer, so the button becomes a manager tool. `handle_print` asks `ACT_VIEW_SEMESTER_REPORT` and then, for the member ground only, requires the state to be approved; a member printing a draft gets `unknown()`, byte for byte the refusal a nonexistent post gets, so the print route can never confirm that a draft exists. The card confirms that, deliberately and in words (section 6), which is fine: a sentence on the institution's own page is not an oracle a stranger can query.

---

## 5. Drafting

### 5.1 When a cohort is due

A cohort of an institution is **due for a draft** when all of these hold:

1. The institution is in the pipeline index with a stage in `institution_active_stages`, and its roster index (`wpcpm_roster_<record>`) has at least one row in the cohort. Institutions with no roster have nothing to report.
2. The cohort key is not `WPCPM_Cohort::NONE`, and the semester window (`WPCPM_Cohort::range( $key )`) ended before today.
3. The window ended on or after `OPT_AUTODRAFT_SINCE`, an option written once by the upgrade with the day the feature was installed. This is what keeps the first run from drafting 2024-H1 for forty institutions: nothing historical is drafted automatically, and a manager who wants an older report presses Draft now.
4. No report exists for the pair (`find()`).
5. Either every row in the cohort is finished (a status in `past_statuses`, or an end date on or before today), or the window ended at least `report_autodraft_grace_days` days ago (default 45). Rows with an in-progress status and no end date keep a cohort open only until the grace runs out; the notice a manager reads on the draft says how many rows were still in progress, so a cohort that is genuinely late shows as such rather than as finished.

The rule lives in one static method, `WPCPM_Semester_Report::due( $today )`, which returns `array( array( 'institution', 'cohort', 'in_progress', 'window_end' ) )` sorted oldest window first. The Administrator Dashboard reads the same method; the cron and the page can never disagree about what "due" means, only about when it was asked.

### 5.2 The job

`CRON_AUTODRAFT = 'wpcpm_report_autodraft'`, daily, scheduled by the module's self-healing `schedule_cron()` on `init` priority 20 (the pattern 1.90.0 gave every other job). `autodraft_tick()`:

- returns at once when `report_autodraft` is off;
- takes the pairs from `due()`, at most `AUTODRAFT_PER_RUN = 10` per run, so a day with many endings costs at most ten generations' worth of Airtable reads and the rest follow tomorrow;
- for each pair calls `generate()`, which already takes the per-pair `add_option()` lock and aborts whole on any `WP_Error`; a failed pair is logged and retried next run, and a failure never stops the loop;
- stamps `META_ORIGIN = auto`, appends a `drafted` entry to the log, and sends one mail per draft (section 8).

### 5.3 Draft now

`ACTION_DRAFT = 'wpcpm_report_draft'`, an `admin_post_` action, never `nopriv`. Order in `handle_draft()`: `current_user_can( CAP_MANAGE )`, then the policy with `subject_institution( $record )`, then `check_admin_referer( ACTION_DRAFT )`, then shape checks on the record ID (`WPCPM_Airtable::is_record_id()`) and the cohort key (`WPCPM_Cohort::is_key()`). It refuses with a flash when a report already exists for the pair (the message links to it), otherwise calls `generate()`, stamps `META_ORIGIN = manager`, logs `drafted`, and does not mail anyone: the manager who pressed the button is the audience of that mail. The form is drawn on the wp-admin "Semester reports" card (institution select, cohort select listing the keys present in that institution's roster plus the current and previous semester) and on the Administrator Dashboard's due list. Ceiling: `WPCPM_Ceiling::claim( 'report-draft:' . $user_id, 20, DAY_IN_SECONDS )`, because every press is a full set of Airtable reads.

---

## 6. What each party sees

### 6.1 The institution's card

`WPCPM_Semester_Report_Screen::render()` keeps its place in `WPCPM_Institutions_Dashboard::render()` (after the import form) and its collapsible shell. For a viewer on the member ground it draws, in this order:

1. The flash, taken once at the top.
2. **Approved reports**, newest cohort first: the cohort label, "Approved on <date>", a *View* link (the same page with `?wpcpm_report=<key>`, which renders the document read-only through `render_document()`), and a *Download PDF* link (`print_url()`). This is the whole of what a member can do.
3. **In preparation**: for each cohort that has a draft, one sentence: "Your semester report for <label> is being prepared by the program team." No date, no button.
4. When there is neither: "No semester report has been published for your institution yet." (An empty card under a heading reads as something that failed to load; the Updates column learned that.)

No `<form>` is drawn for a member: no nonce, no post ID, no submit path in markup nobody may submit, the rule the Student Report Card already follows.

### 6.2 The manager's editor

Unchanged in substance: `render_editor()` with the generated part above the textarea, the quote picker, revisions, the preview. Two button changes: *Mark as final* becomes **Approve** (`ACTION_APPROVE = 'wpcpm_report_approve'`, replacing `ACTION_FINAL`, nonce keyed `ACTION_APPROVE . '_' . $post->ID`); *Reopen* stays (`ACTION_REOPEN`) and now clears `META_APPROVED`. The header shows the state, the origin ("Drafted automatically on <date>" or "Drafted by <manager> on <date>"), and, for an approved report, who approved it and when. A manager reaches the editor as today, through the switcher on the Institution Dashboard; the wp-admin "Semester reports" card and the Administrator Dashboard link there with `ARG_VIEW`.

### 6.3 The wp-admin "Semester reports" card

Keeps its table (institution, semester, state, generated, last edited, consent) and gains a state filter (drafts first by default), the origin column, the Draft now form of section 5.3, and a short *Due for drafting* list from `due()`.

---

## 7. Approving and reopening

`handle_approve()`: manager capability, policy, nonce, post type check, then `set_state( STATE_APPROVED )`, `META_APPROVED`, log `approved`, mail the institution (section 8), flash "Approved. The institution can now read and download it." A report is approved as it stands; the handler does not regenerate. Approving a report whose consent re-check fails is refused with the read's message, because the document a manager is about to publish could not be drawn in full at that moment, and a report that cannot be drawn must not be published.

`handle_reopen()`: draft again, `META_APPROVED` deleted, log `reopened`, no mail. The institution's card drops the report on the next load; if the institution has already downloaded a copy, that copy is theirs, which is why reopening does not pretend to recall anything.

**Log.** `OPT_LOG = 'wpcpm_report_log'`, non-autoloaded, newest first, capped at `LOG_MAX = 200`, one entry per `drafted` / `approved` / `reopened` / `draft_failed` with institution, cohort, actor (0 for the job) and time. Drawn at the foot of the wp-admin card. No prose from the report ever enters the log.

---

## 8. Mail

Both through `WPCPM_Mail::send()`, so they are logged, masked and locale-switched like every other message.

| Context | To | When | Body |
| --- | --- | --- | --- |
| `report-drafted` | every manager account, or the addresses in `report_notify` when set | a draft lands from the job | institution, cohort label, how many rows were still in progress, a link to the editor through the switcher |
| `report-approved` | every account that is a member of the institution | a manager approves | cohort label, a link to the Institution Dashboard card, one sentence that the PDF is there |

An institution with no member accounts (40 of 42 confirmed institutions today) gets no mail, and the approval flash says so: "No institution account to notify; send the PDF by hand." The contact email in Airtable is a person who has not signed in anywhere, and the site does not mail people who have never met it, the rule the application form set.

`report_notify` takes the semantics `agreement_notify` has: empty means every account that can manage the program, so the queue is never silently nobody's job. The recipient resolution moves into one helper, `WPCPM_Mail::managers( $setting_key )`, and `agreement_notify` uses it too.

---

## 9. The data API the Administrator Dashboard reads

Three static methods on `WPCPM_Semester_Report`, each returning plain arrays with no prose:

- `queue()`: every draft, with institution, cohort, generated time, origin, in-progress count at generation, age in days. Oldest first.
- `due( $today )`: section 5.1.
- `approved_since( $timestamp )`: approved reports with institution, cohort, approved at and by.

Institution names come from the pipeline index by record ID at render time, never stored on the report.

---

## 10. Settings

| Key | Default | Meaning |
| --- | --- | --- |
| `report_autodraft` | `true` | run the daily job |
| `report_autodraft_grace_days` | `45` | days after a window closes before a cohort with unfinished rows is drafted anyway |
| `report_notify` | `''` | who hears about new drafts; empty means every manager |

Saved with the other settings; the two non-boolean keys go through the trimmed and integer save lists; `never_blank()` does not apply (an empty `report_notify` is a meaning, not a mistake).

---

## 11. Data model additions

- Meta on `wpcpm_inst_report`: `_wpcpm_report_approved`, `_wpcpm_report_origin`.
- Options: `wpcpm_report_state_version`, `wpcpm_report_autodraft_since`, `wpcpm_report_log` (all `update_option( ..., false )`).
- Cron: `wpcpm_report_autodraft`.
- Actions: `wpcpm_report_draft`, `wpcpm_report_approve` (replacing `wpcpm_report_final`).
- Ceiling key: `report-draft:<user>`.
- Uninstall: the three options and the cron join `delete_all()`; the post type's teardown is unchanged.
- `docs/sections/34-admin-operations.md`'s "Where the plugin keeps its data" table gains the three options.

---

## 12. Tests

All in `bin/test-semester-report.php`, extending the existing suite so the 225 assertions that pin the snapshot, consent and print behaviour keep running against the same fixture:

- **Grounds:** the map row for editing lists the manager only; a member's `decide( ACT_EDIT_SEMESTER_REPORT )` is refused with the one refusal.
- **Member card:** with one approved and one draft report in the fixture, the member's HTML contains the approved cohort's label, a View link and a print link, contains the "being prepared" sentence for the draft, and contains no `<form`, no `ACTION_GENERATE`, `ACTION_SAVE`, `ACTION_APPROVE` or `ACTION_REOPEN` field name.
- **Manager editor:** contains the Approve form for a draft and the Reopen form for an approved report, never both.
- **State machine:** draft to approved stamps `META_APPROVED`; reopen deletes it; `generate()` on an approved report returns the refusal error; `set_state( 'final' )` is refused.
- **Upgrade:** a fixture post with state `final` reads `approved` after `maybe_upgrade()`, with `by` = 0; a second run changes nothing; the version option reads 2.
- **Due rule:** each of the five conditions in section 5.1 flips the answer on its own: inactive stage, empty roster, `NONE` cohort, window not yet closed, window before the since-date, existing report, an in-progress row inside the grace, the same row past the grace.
- **Job:** `AUTODRAFT_PER_RUN` caps one run; a `WP_Error` from one pair logs `draft_failed` and the next pair still runs; `report_autodraft` off runs nothing; the mail log holds one `report-drafted` entry per draft, none for Draft now.
- **Draft now:** refused without the capability before the post is looked up; refused for an existing pair with a flash naming it; the ceiling refuses the twenty-first press.
- **Print:** a member printing a draft gets output byte-identical to a nonexistent ID; the manager prints it.
- **Approve refusal:** a consent read returning `WP_Error` leaves the state draft and flashes the read's message.
- **Snapshot walk:** unchanged, and it now also walks the log for `is_email()` strings.

`bin/test-institution-policy.php` updates its map assertion. `bin/test-institutions-screen.php` scrapes the Draft now form for its field names and asserts the due list renders from `due()`. `bin/test-handlers.php` sees the two new actions registered and `wpcpm_report_final` gone.

---

## 13. Demonstrates, on the TEST institution

With a fixture-seeded roster whose 2026-H1 rows are all finished: the job drafts 2026-H1 and mails `maciej@a8c.com` (the one address every test send uses); a manager opens the editor through the switcher, edits the overview, approves; the TEST representative's dashboard shows the approved report with View and Download PDF and nothing else; the manager reopens and the representative's card shows "being prepared" instead; Draft now for 2025-H2 is refused because a report exists, and for 2026-H2 it drafts one whose notice says the rows still in progress.

---

## 14. Deliberately not in scope

- Institution-side editing or commenting on a draft. Removed on purpose by decision 1.
- Assigning a draft to a named manager, or a review checklist. The queue is shared, as the application queue is.
- A report for a cohort with no roster rows, or for `NONE`.
- Recalling a PDF the institution has already downloaded.
- Any change to the report's content or privacy rules.
