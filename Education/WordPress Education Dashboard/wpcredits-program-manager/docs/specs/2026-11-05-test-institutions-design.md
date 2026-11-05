# Test institutions on the site (design, 5 November 2026)

Approved by the product owner on 5 November 2026 ("Looks right, write the design"; the document "Approved, write the plan"). Plugin:
WPCredits Program Manager. Version: 1.123.55, after 1.123.54 (student registration).

## 1. Why

The program's Administrators are about to test the Institutions module on the live site, end to
end: an institution applies, an Administrator approves it, its representative signs in, generates
and uploads the Collaboration Agreement, enrolls students, and receives a semester report. They test
on one shared test institution.

Today the site cannot tell a test institution from a real one. Approving a test application writes
a real Airtable row, and from then on the test institution, its agreement, its requests, its
students and its reports are counted with the real ones: on the Administrator Dashboard's tiles and
cards, in the wp-admin menu's bubble, in the Institutions screen's totals, in the sponsor report's
numbers and in the answers the site's abilities give. Accepting its agreement also moves its
Airtable stage to Confirmed, and three public pages outside the plugin list every Confirmed
institution. The testing would distort the program's figures and put a test institution on public
pages.

## 2. The product owner's decisions (5 November 2026)

1. **The rule is the name.** An institution whose name starts with the word "TEST" in capitals is a
   test institution: the rule the site already applies to sponsors,
   `WPCPM_Sponsors_Index::is_test_name()` (trimmed, "TEST" followed by nothing or by a character
   that is neither a letter nor a digit, so "TEST - Example" is a test and "Test College",
   "TESTAMENT" and "TEST2 Labs" are not). Its Airtable row is still written on approval, since its
   Institution Dashboard needs it.
2. **Listed with a Test mark, left out of every count.** Wherever the site lists the test
   institution or something of its (its application, its agreement, its requests, its semester
   report, its students), it is listed as usual with a "Test" mark beside its name, so a tester can
   find it and act on it. No number the site shows or reports includes it.
3. **It stays at Under Review until testing ends.** The site never moves a test institution's
   Airtable stage to Confirmed, so the public pages that list Confirmed institutions never show it.
4. **The test students are removed when testing ends.** Students enrolled through the test
   institution are real rows in Airtable's Students table while testing runs; a person removes them,
   and the test institution's row, at the end.

The rest of this document is the author's account of how, played back to the product owner and
confirmed, with the author's calls marked as such.

## 3. The rule

One helper decides it, everywhere: an institution is a test institution when
`WPCPM_Sponsors_Index::is_test_name()` holds for its name. The name is the institutions index's
`name` for an institution the site holds (the Airtable-synced copy of the Institutions table), and
the institution's name as given on the form for an application not yet approved. The helper takes a
record ID or a name, so a list built from posts that carry only the institution's record ID can ask
it too.

There is no setting or box: the name decides it. While the institutions index holds the
institution, its name there is read when it is needed, so renaming an institution in Airtable (the
next institutions sync) changes what it is.

**The site remembers a test institution after its row is gone** (the author's call, after the
builder check). The site's own records of an institution (its agreements and their files, its
requests, its semester reports, its representatives' memberships) carry only its record ID. When
testing ends and a person removes the test institution's row from Airtable, the next sync drops it
from the index, and a check that read only the index's name would find no name and count those
records again. So the helper keeps a short list of the record IDs it has found to be tests (an
option of the plugin's own, written whenever the rule holds for an indexed institution), and a
record ID on that list stays a test when the index no longer holds it. A name in the index always
wins: an institution renamed away from "TEST" is real again and leaves the list.

## 4. Listed, with a Test mark

Every place in wp-admin and on the Administrator Dashboard that names an institution, or something
of its, keeps listing a test institution in its usual place and order, with a small "Test" mark
beside the institution's name: the Institutions screen's tabs (Waiting for review, Pipeline,
Accounts, Semester reports, Agreements, Sync and storage), the Administrator Dashboard's cards, and
the institution pickers. The mark is plain text styled as the site's other small badges, and it says
"Test" (the author's call on the word and the look). The test institution's own Institution
Dashboard, its roster and its export are drawn as for any institution; whether its dashboard also
says it is a test is left to the builder.

The answers the site's abilities give about one institution (`wpcpm-institution-summary`, found by
name or record ID) say that it is a test institution (the author's call), so a question about it is
answered truthfully rather than refused.

## 5. Left out of every count

No number the site draws, mails or answers includes a test institution or anything of its. That is
every count, total, tile, bubble and figure, among them:

- the Administrator Dashboard's tiles (applications, agreements and their overdue count, semester
  reports and their due count, requests and their overdue count, locked accounts) and its cards'
  totals, the Programs card's students per track and its "N institutions";
- the wp-admin menu's bubble on the Institutions screen and the Waiting for review total;
- the Institutions screen's totals: the Pipeline's total and its count per stage, its folds and
  gaps, the institutions with no member, the consent counts, the Agreements tab's Confirmed
  institutions with no agreement and its agreements in force, the semester reports' counts, the due
  for drafting table's count, the Sync tab's figures, the Accounts tab's counts (an account that
  belongs to a test institution, a representative or a student, is not counted);
- the sponsor report's numbers (students started, finished and active, hours, institutions,
  countries);
- the abilities' answers: `wpcpm-program-counts` and `wpcpm-waiting-for-decision`, and the student
  counts the mentor and student abilities total;
- the Students screen's counts per status and its institution picker's counts, which read the
  student accounts (a test institution's student is not counted);
- the counts in the agreement reminder digest mailed to Administrators.

The inventory of the places found, read from the code on 5 November 2026 and widened by the builder
check, is the appendix. The rule of this section decides anything the appendix missed: a number
that counts institutions, or anything of theirs, leaves a test institution out. Per
institution views (the test institution's own dashboard, roster, export and report) are not counts
and stay as they are, so the test institution remains usable.

## 6. The stage stays

Accepting an agreement moves the institution's Airtable stage to Confirmed when its stage is before
Confirmed (`WPCPM_Institution_Agreement`, the acceptance's stage write, near line 1479 on
5 November 2026). For a test institution the acceptance writes everything it writes today (the
agreement's status, kind, date, who accepted it, the template's version) and leaves the stage out
of the write, so the institution stays at the stage it had. No other code path writes an
institution's stage today; any later one keeps the same rule.

Approving a test application writes its new row at the stage the site gives every new institution
(**institution_new_stage**), which precedes Confirmed. The shared test institution the testers use
is kept at Under Review until testing ends (decision 3).

## 7. Everything else works

Only numbers change (the author's call, played back and confirmed). A test institution's emails are
sent as for any institution, to its representatives and to the Administrators the settings name;
its semester report is drafted by the daily job and by hand; its agreement is generated, uploaded,
accepted and returned; its students are enrolled, invited and synced. That is what the testers
test.

## 8. Test students and the end of testing

Students enrolled through the test institution are real rows in Airtable's Students table, linked
to the test institution. Inside the site they are left out of every count with their institution
(section 5), since the site counts students by institution. Outside the site, the public tracker
and the monthly growth posts read the Students table directly and may count them while testing
runs; the product owner accepted that for the length of the testing (decision 4).

When testing ends, a person removes the test students' rows, their report rows and the test
institution's row in Airtable, as the testing guide's last section says step by step. The site
deletes nothing in Airtable by itself, in this release as before. On the site, the students sync
handles an account whose Airtable row is gone as it does for any removed student; the test
institution's own records stay on the site, uncounted, since the site remembers it (section 3); and
a person removes the test representatives' accounts, as the guide says.

A test student is never given a mentor (a rule of the testing guide, the author's call): the
mentors' figures (the Mentor Report Card's counts, the mentor engagement figures, the daily mentor
promotion check) read the Students table's rows by mentor, with no institution on them, so a test
student under a real mentor would count for that mentor.

## 9. Goals, non-goals and anti-goals

**Goals:** the Administrators test the whole Institutions module on the live site on one shared
test institution, and no figure, total or public page changes because of it; a tester finds every
test item where a real one would be, marked.

**Non-goals:**

- the weekly outcome figures (graduation, recommendation, keeping on): they read Airtable's report
  and feedback rows, which carry no institution, so leaving a test student out would need new
  Airtable reads; the cleanup at the end of testing removes the test reports instead (the author's
  call);
- the public pages outside the plugin (the public WP Credits tracker and map, the WPCredits-Tracker
  plugin, the Education Programs Map): they list Confirmed institutions, and a test institution
  never reaches Confirmed;
- the mentors' figures (the Mentor Report Card's counts, the mentor engagement figures, the daily
  mentor promotion check): they read the Students table by mentor, with no institution, and the
  testing guide keeps test students without a mentor (section 8);
- a test setting or box, or a way to make a test institution from wp-admin;
- deleting anything in Airtable from the site, or the site's own records of a test institution;
- the student registration form, which leaves a test institution off its list by the same helper
  in 1.123.54 (its Task 5 uses `is_test_name()`, a ruling of that release's build).

**Order:** 1.123.55 is built on main once 1.123.54 is merged there, and uses the same helper the
student registration form calls.

**Anti-goals (a failure even if it works):**

- a number on the Administrator Dashboard, the Institutions screen, the menu bubble, the sponsor
  report or an ability's answer that moves because of a test institution;
- a test institution, or something of its, missing from a list a tester needs to act on;
- a test institution moved to Confirmed by the site, or listed on a public page;
- a real institution treated as a test one ("Test College", "Contest Labs", "TESTAMENT").

## 10. Built on what the plugin has

- `WPCPM_Sponsors_Index::is_test_name()` and the way the sponsors' feed and lists apply it
  (`class-wpcpm-sponsors-feed.php`, `class-wpcpm-ability-answers.php`, `class-wpcpm-track-partners.php`,
  `class-wpcpm-sponsors-listing.php`).
- The institutions index (`WPCPM_Institutions_Index`), `WPCPM_Institutions::counted_rows()`, the
  roster index (`WPCPM_Roster_Index::counts()`), and the Administrator Dashboard's
  `WPCPM_Administrators_Cards::collect()` and `counts()`.
- The site's small badges for the mark.

## 11. Tests

Each behavior of sections 3 to 7 has a check, in the style of the repository's suites: the rule's
edge cases (as `is_test_name()`'s own suite has them); for each count of section 5 and the
appendix, the same world with and without a test institution gives the same number; each list of
section 4 shows the test item with its mark; the acceptance's write for a test institution holds no
stage and for a real one still does; the abilities' answer for the test institution says so.

## 12. Docs

The Administrator guide gains a short part on test institutions: the name rule, what is marked,
what is left out, that the stage stays, and the cleanup when testing ends. The testing guide for the
Administrators (drafted separately, outside this release) links it.

## 13. Left to the builder

The helper's name and home, whether the index or each list applies it, the mark's markup and CSS,
whether the test institution's own dashboard says it is a test, and the tests' layout.

## 14. Open questions

None.

## Appendix: where the site counts institutions (read from the code, 5 November 2026)

Line numbers are those of main on 5 November 2026. "Index" is the Airtable-synced institutions
index; "roster" the Airtable-synced students per institution; "posts" the site's own records that
carry an institution's record ID. No institution test check exists anywhere today.

| Where | What it counts | Source |
| --- | --- | --- |
| `class-wpcpm-ability-answers.php` `program_counts()` (112), `waiting()` (228), `institutions()` (335), `students()` and `mentor()` (476, 611, 689, `roster_pool` 1000) | the abilities' counts and summaries | index, roster, posts |
| `class-wpcpm-administrators-cards.php` `collect()` (95; agreements 98-117 and 193, requests 119-135, applications 154-175, reports 192-194, locked 203), `counts()` (293), `programs()` (1821), `programs_phrase()` (3244) | the dashboard's tiles and cards | mixed |
| `class-wpcpm-semester-report.php` `due()` (857), `queue()` (975), `approved_since()` (985), `rows_in_state()` (1057) | reports due and drafted (`due()` also drives the daily drafting) | index, roster, posts |
| `class-wpcpm-semester-report-screen.php` `render_draft_picker()` (677) | the institutions offered for Draft now (a list) | index, roster |
| `class-wpcpm-institutions.php` `menu_label()` and `queue_count()` (390, 2073), `queue_rows()` (2112), `render_pipeline()` (4214; stage counts 4299, 4314), `counted_rows()` (6418), `membership_gaps()` (6532), `consent_counts()` (6602), Agreements tab (4698, 4778), `render_discrepancies()` (5803), Reports card (5880-5920), `render_report_due()` (6024), `render_tab_sync()` and `render_reconciliation()` (2933, 5214), `provision_lists()` and `provision_reasons()` (4954, 5189), `link_choices()` (5708, a list) | the Institutions screen's totals and lists | index, posts, users |
| `class-wpcpm-institutions-index.php` `rows()` (133), `read()` (98, also read directly at ability-answers 119, institutions-table 1042, institutions 2756, 2916, 2935) | every index read | index |
| `class-wpcpm-accounts-table.php` `invite_counts()` (590); `class-wpcpm-institution-roster.php` `locked_today()` (589) | institution and student accounts | users |
| `class-wpcpm-roster-index.php` `counts()` (201), built in `class-wpcpm-students-sync.php` (2286-2330) | students per institution and cohort | roster |
| `class-wpcpm-sponsor-report-numbers.php` `rows()` (225) | the sponsor report's reported numbers | index, roster |
| `class-wpcpm-students-table.php` (counts per status, institution picker, 292) | student accounts by status and institution | users, roster |
| the agreement reminder digest (`class-wpcpm-institution-agreement.php`, `class-wpcpm-institutions.php`) | agreements waiting, mailed to Administrators | posts |
| `class-wpcpm-outcome-figures.php` (566, 903) | the weekly outcome figures (non-goal: no institution on the rows) | Airtable |
| the mentors' figures (Mentor Report Card, mentor engagement, mentor promotion check) | students per mentor (non-goal: by mentor, no institution) | Airtable |

Notes from the reading: filtering `WPCPM_Institutions_Index::rows()` itself would also hide the
test institution from `row()`, `has()`, the institutions sync's own reads and its own dashboard, so
the counting call sites need a counted variant or their own check; the post-based lists
(applications, agreements, requests, reports) carry the record ID or, for an application, the name,
and need the check where each list or count is built, so the menu bubble, the queue and the
dashboard agree.
