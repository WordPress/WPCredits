# Emails E3: the Mentor calls and the Semester reports in the template registry, Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every email of the Mentor calls and the Semester reports areas editable under WPCredits Program > Tools > Emails, with its text unchanged: each email that says words of its own becomes a template with a catalog id of its own, built from the registry with the values its sender works out, and shown, edited, previewed and tested in the editor the invitations already have.

**Architecture:** Two new area files under `includes/mail/templates/`, `calls.php` (nineteen templates) and `reports.php` (three), join `WPCPM_Mail_Templates::AREAS`; each sentence in them is the translatable string the sender wrote, with `{placeholders}` where its values went. `WPCPM_Mail_Catalog` gains a name, an area and an audience for each of the nineteen Mentor calls emails, and keeps the five ids the area was sent under until now, their labels ending " (until 1.122.21)", for the rows they left in the Log. The senders in `WPCPM_Mentor_Calls` and `WPCPM_Semester_Report_Screen` stop writing sentences: each chooses its template, works out its values inside the recipient's language and on the recipient's clock (a time, a date, a list of dates, a count worded with `_n()`), and hands the mail layer the registry's subject and body with the Reply-To and the calendar file it always sent. The registry is built once a request for each language, and an edit whose subject fills to nothing is sent with the shipped subject. A recording of every email the two areas send, made from 1.122.20 through the real senders before anything moves, holds the move to the same bytes.

**Tech Stack:** WordPress 7.1 plugin PHP (7.4 and 8.x), the plugin's `bin/test-*.php` suites (plain PHP with stubs), `WPCPM_Mail_Templates` and `WPCPM_Mail_Catalog` as 1.122.19 made them, `WPCPM_Mentor_Availability` and `WPCPM_Mentor_Calls::format_range()` for every time on a recipient's clock, `WPCPM_ICS` for the calendar files, which stay in code.

**Spec:** `docs/specs/2026-10-10-emails-tool-design.md` (approved by the owner on 10 October 2026). This plan covers its third release, "1.122.21: Mentor calls and Semester reports", and follows the plan rulings of 11 October 2026, E3-1 to E3-10 (`.superpowers/sdd/2026-10-11-emails-e3-plan-rulings.md`), the rules E2's reviews added (`.superpowers/sdd/2026-10-10-emails-e2-templates/progress.md`, its "Ruling:" lines) and the format and proving method of the previous release's plan, `docs/plans/2026-10-10-emails-e2-templates.md`. 1.122.18, 1.122.19 and 1.122.20 are live; main is 76d9535.

**Order:** the recording of what every email of the two areas says today, before anything moves (Task 1); the catalog's new ids and the registry's two new areas, their shipped text and examples, the registry built once a request for each language, and the empty subject's guard (Task 2); the Mentor calls built from the registry, word for word (Task 3); the Semester reports built from it (Task 4); the editor, the preview and Send me a test for every new template (Task 5); the guide, the version, the changelog and the template (Task 6).

**Where the rulings left a choice, this plan chooses:**

- **E3-2, which emails are different: any line in other words is another email.** The rule is E2-2's: an email whose words differ from another's in any sentence, the subject included, is a template of its own, since one template with optional lines could say two sentences only by moving wording back into code; the same words with other values are one template. The code sends twelve emails from twelve places under eight ids, and their words split them into twenty-two templates. In the Mentor calls: a one-to-one call and a group session are told in different words (the subject, the opening, the topic's label), and so are the mentor and the student; a series is told to each in words of its own; a moved session's words differ for the students and for the mentor an Administrator moved it for; a student who left and one the students sync took off are told differently; a canceled call tells the mentor that the student can book again and the student that they can, while a canceled session says the same words to its mentor and to every student on it; and a call's reminder labels the topic differently for each, while a session's reminder says the same words to everybody on it, the mentor told how many are coming where a student is told the mentor's name. That is sixteen. A mentor who has not set a meeting link yet is told so ("You have not set a meeting link yet, so this confirmation cannot tell your student where to go.") where everybody else is told where to meet; the registry leaves a line out when its value is empty but never puts one in, so each of the mentor's three confirmations, of a call, a join and a series, has a second template for that case: nineteen. The Semester reports send three emails in three wordings: three.
- **E3-2, the ids, the names and the audiences.** The nineteen Mentor calls templates take new ids, named for whom each goes to, as the spec's own example `call-booked-student` is: `call-booked-mentor`, `call-booked-mentor-no-link`, `call-booked-student`, `session-joined-mentor`, `session-joined-mentor-no-link`, `session-joined-student`, `series-joined-mentor`, `series-joined-mentor-no-link`, `series-joined-student`, `session-moved-student`, `session-moved-mentor`, `session-left`, `session-released`, `call-cancelled-mentor`, `call-cancelled-student`, `session-cancelled`, `call-reminder-mentor`, `call-reminder-student`, `session-reminder` (the ids keep the spelling of `call-cancelled`; every label says "canceled"). Each has its own label ("Call booked, to the mentor"; "to the student" in the singular wherever a student is told, since each student is sent one email of their own, a moved session's too), the area Mentor calls, and its audience, Mentor or Student; the canceled session and the session's reminder go to the mentor and the students in the same words, so their audience is empty and the account decides, as the catalog's empty audience always meant.
- **E3-2, the five ids of before: kept for the Log, reused for none.** `call-booked`, `series-joined`, `session-moved`, `call-cancelled` and `call-reminder` each named the mentor's and the students' emails alike, of calls and of sessions alike, so no one of the new templates reads true under any of their names, and a label narrowed to one of them would misname the rows the others left. They stay in the catalog, as the four `test-*` ids of 1.122.19 did, so the Log names the rows they left for the 30 days it keeps them, and nothing sends them after Task 3, which the catalog's suite pins. Their labels end " (until 1.122.21)" ("Call booked (until 1.122.21)", "Joined a session series (until 1.122.21)" and so on), since the Log's Email field lists them beside the new names ("Call booked, to the mentor") and a person choosing from it can then tell the old from the new; the same suite pins the five labels. The three Semester reports ids each named one email, so `report-drafted`, `report-approved` and `report-consent` are the templates' ids, their names and audiences unchanged.
- **E3-2, a topic is two placeholders.** What a student typed for a one-to-one call is `{topic}`, and what a mentor typed for a session is `{session_topic}`, since the two mean different things and a placeholder says what it means.
- **E3-3, the recording.** The suite the previous author wrote, `bin/test-calls-reports-text.php`, records through the real senders and the real mail layer, so it holds each email as `wp_mail()` is handed it: to whom, under which id, its subject, its body with the signature and footer 1.122.20 adds, its headers and each calendar file. It covered what E3-3 asks, and this plan keeps it with two inputs more: a topic holding a placeholder's name in braces, `{when}`, which proves a value is never read as a placeholder, and a booking whose topic is spaces alone, which a booking prints and a series or a move leaves out. Its stand-in translations mark a string with any language but English, so a later check can tell which language an email was built in; the recording is English throughout. 43 cases, 87 emails.
- **E3-3, the one line the move changes, by design.** With the Institution Dashboard page not published, 1.122.20's report drafted and report approved bodies end in a blank line (the link's line left out above it), so the footer stands under two blank lines. The registry leaves out a blank line that a line left out would leave at the end of a body, which is its rule since 1.122.19 and the one E2's recording proved, and no template can end in a blank line it leaves out the line above; so those ten emails, of five cases (the drafted and approved report emails, when the Institution Dashboard page is not published, whether or not the Administrator Dashboard page is), lose that one line ending, and nothing else. The suite holds them to the recording with that line ending taken off (`body_as()`), says so in their checks' labels, and pins that the change touches those ten and no other. The page is published on the live site, so no email sent there changes.
- **E3-4, counted words and lists.** A subject or a first line that `_n()` worded whole takes its counted words as one value, computed with `_n()` where the email is sent: `{session_count}` ("3 sessions"), `{group_session_count}` ("3 group sessions"), `{date_count}` ("3 dates"), the reminder's `{with_whom}` for a mentor ("3 students"), and the draft's `{students_in_progress}`, which carries its verb ("3 students in this semester were"), since English agrees the verb with the number. A series' dates are one value, `{session_dates}`, one to a line. "your student" (a call with no student name stored) and "an Administrator" (who canceled, when an Administrator did) are values too, now worded inside the recipient's language, where until now the actor's was used. The calendar file's description stays in code, as an attachment's text: `mail_body()`, whose other branches the templates replace, becomes `calendar_text()`, and `series_mail_body()` goes.
- **E3-5, what is required and what goes in the body alone.** Required, so no edit can drop it: the call's time, `{when}`, in every email about one call or session; `{session_dates}` in the series; `{semester}` in the three reports, which names the report; and the meeting link, `{meeting_place}`, in the mentor's three confirmations that are sent only while the mentor has one. `{meeting_place}` goes in the body alone in every template that has it: a meeting link can carry the meeting's passcode, and the subject is the part of an email the Log keeps. What a student or a mentor typed goes in the body alone too, `{topic}` and `{session_topic}`: the Log keeps the subject and never what a person typed (E1's rule), and the mail layer writes the entities of a typed "<" or "&" into a subject. Everywhere else the meeting link, the report's links (`{review_link}`, `{report_link}`) and the dashboard links are optional, because each can be empty when the email is sent (a mentor with no link, the Institution Dashboard page not published), and the spec's rule, pinned since 1.122.19, never leaves out a line that holds a required placeholder: a required `{review_link}` would print "Review it here:" over nothing. That keeps today's emails; it departs from E3-5's naming of "the report's link" as required, and the owner is asked below. A dashboard link is optional for a second reason: the signature and footer names the recipient's dashboard whenever the body does not. Secrets: the consent email carries none (its link is the Student Report Card's address, behind sign-in); no cancel or join link in these emails carries a token or a nonce (canceling and leaving happen on the dashboards); the meeting link is the one that can carry a passcode, and no test or fixture holds a real one (`pwd=EXAMPLE-NOT-A-REAL-CODE` in the recording, `https://meet.example.test/ben-lindqvist` in the examples).
- **E3-6, the registry built once a request for each language.** `all()` keeps what it built under the locale it was built in, `determine_locale()` (which WordPress's switcher filters, so a send in a recipient's language builds and keeps that language's), or `get_locale()` where that function is missing. The Templates tab's suite reads three tables, one per area, in place of one.
- **E3-7, an edit's subject that fills to nothing: the shipped subject is sent.** `render()` fills the edit's subject, and when it comes out empty as the mail layer's cleaner will leave it (every word of it an optional placeholder that had nothing to say, or a value the cleaner reads as a tag and removes), it fills the shipped subject instead and keeps the edit's body. `hand_off()` sends nothing when the subject is empty (class-wpcpm-mail.php, `if ( '' !== trim( (string) $mail['subject'] ) )`) and tests it before it cleans it, so the email would otherwise be lost, or go out with no subject at all. A refusal at save could not see the values an email is sent with, so it would refuse a subject such as `{student_name}` that fills on almost every send, and let through one that came out empty on the rest.
- **E3-8, the examples.** A call is tomorrow at 3 pm, half an hour long, on the clock of the Administrator looking at the preview or pressing Send me a test (their profile's time zone, the site's when they have none); a moved session was a day later; a series is that call and the same time a week and two weeks on; the names are Ada Okonjo, Ben Lindqvist and Example University; the meeting link is `https://meet.example.test/ben-lindqvist`; the semester is the one running now, and a report's link is its real address on the Institution Dashboard.
- **No commit before the last is built.** From Task 2 the Templates tab lists emails whose senders read the registry only from Task 3 (the Mentor calls) and Task 4 (the Semester reports); an edit saved on a build of Task 2 or 3 would be listed as Edited and not sent. Only the release's last commit is built.


## Global Constraints

- Version **1.122.21** in the plugin header (`Version:`), `WPCPM_VERSION` and readme.txt's `Stable tag`, with a changelog entry and the translation template regenerated (`sh bin/make-pot.sh`).
- Word for word: the shipped text of every email that moves into the registry is what the code wrote before the move, byte for byte, as `wp_mail()` is handed it with the signature and footer 1.122.20 adds, its headers and its calendar file included, but for the one line ending the ten report emails with no Institution Dashboard page lose (the choice above). `bin/test-calls-reports-text.php` records every email of the two areas from 1.122.20, through the real senders and the real mail layer, before anything moves (Task 1), and holds the registry to the recording after (Tasks 3 and 4). The recording is never edited by hand and never made twice: `--record` refuses once the file exists, and the suite pins the file's sha256.
- The catalog stays the one list of names, areas and audiences: a template never repeats a label, an area or an audience, and every template's id is a catalog id.
- A template is a subject and a body. Who receives an email, its Reply-To, its calendar file and the file's cleanup stay in the sender's code, and so do the calendar file's own words (`WPCPM_Mentor_Calls::calendar_text()`).
- Every time and date is worked out by the sender inside the recipient's language and on the recipient's own clock (`WPCPM_Mentor_Availability::viewer_timezone()`), as before; a counted phrase is worded with `_n()` by the sender and handed over as one value; a list is one value, one item to a line.
- US English; plain hyphens only, never an em or en dash, in code, comments, strings and docs.
- Full product names: Administrator Dashboard, Student Report Card, Mentor Report Card, Institution Dashboard, Collaboration Agreement; the program's managers are "Administrators".
- No translated string anywhere says "module" or "program manager" (bin/test-admin-menu.php refuses both); the screen word is **Area**. The code keeps `module` as the catalog's key.
- Examples and test data use invented names and `@example.test` addresses, never real people or institutions (bin/test-fixtures.php); no fixture holds an address of any kind (the recording writes each "at example.test") or a record ID but the seeds'. No test or fixture holds a real meeting link or passcode: the recording's is `https://meet.example.test/ben-lindqvist?pwd=EXAMPLE-NOT-A-REAL-CODE`, the examples' `https://meet.example.test/ben-lindqvist`.
- The log never stores message text (E1's rule, unchanged). The registry's option stores the Administrators' wording of the emails, never a recipient's values.
- Administrators only: no screen or handler is added; the editor's two forms keep asking `WPCPM_Roles::CAP_MANAGE` first, then their nonce.
- Send me a test goes to the address of the account that pressed it, read from the account, never from a field a form posted; and it sends the text as saved.
- An edited template is sent as edited for every recipient; an unedited one is built with `__()` in the recipient's language, as before, and the registry is built once a request for each language it is asked in.
- A choice from a long list is one field that drops down, takes typing and narrows (the owner's rule). The Log's Email field is that field already, and lists the catalog's nineteen new names as it lists every name.
- Every method in a new file under `includes/` has a docblock; `sh bin/check-standards.sh` gains no error (main has 2).
- Every task ends with the whole battery: every `bin/test-*.php` in its own process, all passing, and `php bin/check-references.php`, as its last step before the commit shows.
- Never `git stash`. Commit after each task's battery passes.
- Public text (this repository is mirrored publicly) names no unreleased or private work.

## Review Focus

1. **Word for word, through the real senders.** Every email of the two areas, to every recipient, against the recording made from 1.122.20 before the move: its subject, its body with the footer, its headers and its calendar file, byte for byte, under the template's own id; and the one line the move takes out, in ten report emails with no Institution Dashboard page, pinned as exactly that one line ending in exactly those ten. Pinned in Task 1 (the recording, its sha256), Task 3 (the Mentor calls) and Task 4 (the Semester reports).
2. **One id, one email.** Which of today's emails became which template: the catalog's nineteen new names, areas and audiences, the five old ids kept for the Log and sent by nothing, the Semester reports' three ids unchanged; and an account that is a mentor and a student is sent each email written for the mentor alone as a mentor, the Log reading it as one and the footer adding no Student Report Card under a body that names the Mentor Report Card; the two a group session sends its mentor and its students in the same words (`session-cancelled`, `session-reminder`) keep an empty audience, so there the account decides, as before 1.122.21. Pinned in Task 2 (the catalog's suite) and Task 3 (`sent_as()`, the two roles' check, the retired ids).
3. **What a save must keep, and where a link may go.** Every email about a call needs `{when}` and a series `{session_dates}`; the meeting link, and what a student or a mentor typed, go in the body alone everywhere, and the link is required in the mentor's three confirmations sent only with one; the three reports need `{semester}`; and the links that can be empty when an email is sent stay optional, as the choice above says. Pinned in Task 2 (each template's required and body-only placeholders) and Task 5 (the editor refusing a save without `{when}`, and one with `{meeting_place}` or `{topic}` in the subject, the typing given back).
4. **Each recipient's clock and language.** Every time on its recipient's clock, as recorded; the counted words, "your student" and "an Administrator" worded in the recipient's language; and the registry built once a request for each language, so a mentor who reads Polish gets the reminder in Polish in the same request that sends the students theirs in English. Pinned in Task 1 (the recording's clocks), Task 2 (the registry built again in another language, and not again in the same one) and Task 3 (the mentor in Polish).
5. **The examples, the preview and Send me a test.** Every new template opens in the editor with its preview filled and a test, pressed as drawn, that is one email to the presser alone, marked [Test] and Test, under its own id; the examples read as a real email to the presser would (a call tomorrow at 3 pm on their own clock, invented names, links of the site's or example.test's); and an edited subject that fills to nothing, as the mail layer will clean it, is sent as the shipped subject. Pinned in Task 2 (the examples, the empty subject) and Task 5 (the editors and the tests).

## Execution

Subagent-driven, strictly sequential in one worktree (`.worktrees/emails-e3`, branch `feature/emails-e3` from main): the tasks share files (`bin/test-calls-reports-text.php` grows across Tasks 1, 3 and 4, `bin/test-emails-templates.php` across Tasks 2 and 5, `bin/test-mail-catalog.php` across Tasks 2 and 3), so none runs beside another. Every task gets its task review, and the most capable reviewers go to Task 3 (the senders, word for word) and Task 2 (the registry, the catalog and the shipped text).

---

## File Structure

| File | Responsibility |
| --- | --- |
| `bin/test-calls-reports-text.php`, `bin/fixtures/calls-reports-1-122-20.json` (new, Tasks 1, 3, 4) | The word-for-word recording of every email the Mentor calls and the Semester reports send, made from 1.122.20 through the real senders, and the suite that holds the registry to it. |
| `includes/mail/templates/calls.php` (new, Task 2) | The Mentor calls area's nineteen emails as shipped, each sentence under the translatable string the sender used, their placeholders and examples. |
| `includes/mail/templates/reports.php` (new, Task 2) | The Semester reports area's three emails as shipped. |
| `includes/mail/class-wpcpm-mail-templates.php` (Task 2) | `AREAS` gains the two areas; `all()` is built once a request for each language (`locale()`); `render()` sends the shipped subject when an edit's fills to nothing, as the mail layer will clean it. |
| `includes/mail/class-wpcpm-mail-catalog.php` (Task 2) | Gains the nineteen Mentor calls ids, each with its name and audience; keeps the five of before, their labels ending " (until 1.122.21)". |
| `includes/modules/class-wpcpm-mentor-calls.php` (Task 3) | Each sender chooses its template and works out its values (`call_values()`, `moved_values()`, `series_values()`), and hands the mail layer the registry's text (`mail_of()`); `mail_body()` becomes `calendar_text()`; `series_mail_body()` goes. |
| `includes/modules/class-wpcpm-semester-report-screen.php` (Task 4) | The three builders hand the registry their values (`rendered()`). |
| `bin/test-mail-templates.php`, `bin/test-mail-catalog.php`, `bin/test-emails-templates.php` (Task 2); `bin/test-mail-catalog.php`, `bin/test-mail.php` (Task 3); `bin/test-semester-report.php` (Task 4); `bin/test-emails-templates.php` (Task 5) | The suites, each in the task that changes what it reads. |
| `docs/sections/32-admin-tools.md`, then `docs/administrators.md` and `docs/build/administrators.html`; `wpcredits-program-manager.php`, `readme.txt`, `languages/wpcredits-program-manager.pot` (Task 6) | The Administrator guide, the version, the changelog and the template. |

Every suite is plain PHP run as `php bin/<suite>.php`, printing `ok` / `FAIL` lines and a last line `ALL PASS (N checks)` or `N FAILURE(S)`, and exiting non-zero on a failure. A new suite carries the `ck()` helper below, as every suite does (the one new suite of this plan is written out whole, helper included):

```php
$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}
```

and ends with:

```php
printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

"The whole battery" is every suite in its own process, then the references check, which every task's last step runs:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the references check ends `- all resolve`.

How each change is written: "Create `path`" gives a new file whole; "In `path`, replace:" gives the exact lines to find, once, and the lines to put in their place; "replace the method `name()`, its docblock included" and "delete the method `name()`" act on the whole method, from the `/**` above it to its closing brace.

---


### Task 1: The recording: what every Mentor calls and Semester reports email says today, word for word

Before anything moves, a suite records every email the two areas send, through the real senders and the real mail layer, from today's code, into a fixture that is committed and never edited by hand; from then on the same suite sends the same emails from the same inputs and compares them with the recording, byte for byte (ruling E3-3). It changes no code.

**Files:**
- Create: `bin/test-calls-reports-text.php`
- Create, by the suite's `--record` run: `bin/fixtures/calls-reports-1-122-20.json`

**Interfaces:**
- Consumes, as 1.122.20 has them: `WPCPM_Mentor_Calls::notify_joined( $call_id, WP_User $mentor, $student, $revision = null )`, `::notify_joined_series( array $call_ids, WP_User $mentor, $student, array $revisions = array() )`, `::notify_session_moved( $call_id, $was_start, $revision )`, `::notify_left( $call_id, $student_id, $revision = null, $repaired = false )`, and the private `::notify_booked( $call_id, WP_User $mentor, $student, $revision = null )`, `::notify_cancelled( WP_Post $call, array $facts, $revision = null )` and `::notify_reminder( WP_Post $call, $lead )`, reached by reflection as their handlers and the hourly sweep call them; the private builders `WPCPM_Semester_Report_Screen::drafted_message( $institution, $cohort, $in_progress )`, `::approved_message( $cohort )` and `::ask_message( $cohort )`, each returning a builder for `WPCPM_Mail::send()`; `WPCPM_Mail::send()` and `::send_to()`.
- Produces:
  - `bin/fixtures/calls-reports-1-122-20.json`: `{ "_comment": string, "cases": { "<case>": [ { "to": string, "context": string, "subject": string, "body": string, "headers": string[], "attachments": [ { "name": string, "ics": string } ] } ] } }`, 43 cases and 87 emails, each in the order it was sent; every address written "at example.test", each calendar file unfolded with its `DTSTAMP` written "recorded".
  - In the suite: `cases(): array<string,array>`, `emails_of( array $case ): array[]`, `recorded_form( array $mail ): array`, `reach( $class, $method ): ReflectionMethod`, and `sent_as( $case, $index, $recorded ): string`, the id each recorded email is logged under now, which answers the recorded id until Task 3 replaces it.
  - `--record` writes the file once: run again, it refuses ("The recording exists and is never made twice.") and exits 1, and the suite pins the file's sha256.

- [ ] **Step 1: Write the suite**

The inputs are invented: a mentor, Ben Lindqvist, keeping Tokyo's clock; students keeping New York's, Warsaw's and, for Kai O'Example, none, so the site's (UTC here); an Administrator and two institution accounts; every address `example.test`'s; a meeting link with a fixed fake passcode. Every call is in 2036, so a reminder reads "in" a while, and one of them crosses midnight on Tokyo's clock. The cases take every branch the senders have: a call and a session, a series of one, two and three, a meeting link and none, a topic, none, one of spaces alone and one holding "<", "&", quotes, `{when}` and a line break, the dashboard pages published and not, each person who can cancel or move, counts of one and of several, a site name holding "&", a quote and a "%", a booking with no student name stored, and a draft sent to an account and to an address with none. The calendar files are recorded with the emails, so the move is held to them too. The stand-in translations hand English back as written, and mark any other language with its locale, which Task 3's check of a mentor who reads Polish reads.

Create `bin/test-calls-reports-text.php`:

```php
<?php
/**
 * The Mentor calls and Semester reports emails, word for word: what each says, as 1.122.20 wrote it.
 *
 * These emails move into the template registry in 1.122.21, and the move must change nothing a
 * recipient reads. So before the move this suite recorded, through the real send paths, every email
 * the two areas send for a set of example cases into bin/fixtures/calls-reports-1-122-20.json: who
 * it went to, the id it was logged under, its subject and body as `wp_mail()` was handed them (the
 * signature and footer included, as 1.122.20 adds it), its headers, and each calendar file it
 * carried. From then on it runs the same cases and compares, byte for byte.
 *
 * The send paths: `WPCPM_Mentor_Calls`'s seven senders, booked, joined, joined a series, moved,
 * canceled, left and reminded (the private ones reached by reflection, as they are called from the
 * handlers and the hourly sweep); and the semester report's three builders, handed to the mail layer
 * as the report screen hands them (`notify_managers()` sends to an account, or to an address with
 * none, and the approval and the consent request to accounts).
 *
 * The inputs: a call and a group session, a series of one, two and three sessions, a meeting link
 * set and not, a topic or none (one of spaces alone, which a booking prints and a series or a move
 * leaves out, and one holding "<", "&", quotes, a placeholder's name in braces and a line break), the
 * dashboard pages published and not, a mentor in Tokyo and students in New York, Warsaw and the
 * site's own zone (each recipient reads every time on their own clock), a call that crosses midnight
 * on one of those clocks, a booking with no student name stored, each person who can cancel or move,
 * counts of one and of several, and a site name holding "&", a quote and a "%". The names are
 * invented; every address is example.test's and is written "at example.test" in the recording, which
 * holds no address; a calendar file's DTSTAMP, the moment it was written, is written "recorded".
 * Every call is in 2036, so a reminder reads "in" a while, as it does before a call. The times rest
 * on the time zone rules PHP carried when it was made (timezonedb 2026.1); if a later PHP's rules for
 * 2036 differ, the suite fails on every New York and Warsaw time, and `timezone_version_get()` says
 * why.
 *
 * The recording is made once: `--record` refuses when the file is there, and the suite pins the
 * file's sha256, so a recording made again from later code can never stand in for 1.122.20's.
 *
 * Run from the plugin root:  php bin/test-calls-reports-text.php
 * Record (once, against 1.122.20, before the move):  php bin/test-calls-reports-text.php --record
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['opts']      = array();
$GLOBALS['umeta']     = array();
$GLOBALS['users']     = array();
$GLOBALS['posts']     = array();
$GLOBALS['pmeta']     = array();
$GLOBALS['mail']      = array();
$GLOBALS['uid']       = 0;
$GLOBALS['manage']    = array( 40 );
$GLOBALS['site_name'] = 'WordPress Credits';

class WP_Error {
	private $code;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return ''; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array() ) {
		$this->ID           = $id;
		$this->display_name = $name;
		$this->user_email   = $email;
		$this->user_login   = strtolower( str_replace( array( ' ', "'" ), '', $name ) );
		$this->roles        = $roles;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post { public $ID = 0, $post_content = '', $post_type = '', $post_status = 'private'; }

function is_wp_error( $t ) { return $t instanceof WP_Error; }
// The language each string is asked in: English as written, any other marked with its locale, so a
// check can tell which language an email was built in. The recording is English throughout.
function __( $s, $d = null ) { return wpcpm_test_in_language( $s ); }
function _n( $a, $b, $n, $d = null ) { return wpcpm_test_in_language( 1 === (int) $n ? $a : $b ); }
function wpcpm_test_in_language( $s ) { return 'en_US' === determine_locale() ? $s : '[' . determine_locale() . '] ' . $s; }
function esc_url_raw( $u, $p = null ) { $u = trim( (string) $u ); return preg_match( '#^https?://#i', $u ) ? $u : ''; }
function is_email( $s ) { return (bool) filter_var( (string) $s, FILTER_VALIDATE_EMAIL ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_file_name( $n ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $n ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function get_bloginfo( $k = 'name' ) { return $GLOBALS['site_name']; }
function wp_login_url( $r = '' ) { return 'https://example.test/wp-login.php'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function get_temp_dir() { return wpcpm_test_temp_dir(); }
function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0777, true ); }
function wp_generate_password( $l = 12, $s = true, $e = false ) { return substr( md5( (string) mt_rand() ), 0, (int) $l ); }
function wp_delete_file( $p ) { if ( file_exists( $p ) ) { unlink( $p ); } }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function get_user_by( $f, $v ) { return 'id' === $f ? ( $GLOBALS['users'][ (int) $v ] ?? false ) : false; }
function get_post( $id = null ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
// A single read answers the first row, a rows read answers them all, as WordPress does.
function get_post_meta( $id, $k = '', $single = false ) { $rows = $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? array(); $rows = is_array( $rows ) ? $rows : array( $rows ); if ( $single ) { return $rows ? $rows[0] : ''; } return $rows; }
// Every call was booked in 2033, long before its reminder window opens.
function get_post_time( $f, $gmt = false, $post = null ) { return 2000000000; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
// A fixed span, so a reminder reads the same whenever the suite runs.
function human_time_diff( $a, $b = 0 ) { return '1 day'; }
function wp_timezone_string() { return 'UTC'; }
function wp_timezone() { return new DateTimeZone( 'UTC' ); }
function wp_date( $format, $ts = null, $zone = null ) {
	$d = new DateTime( '@' . (int) $ts );
	$d->setTimezone( $zone instanceof DateTimeZone ? $zone : new DateTimeZone( 'UTC' ) );
	return $d->format( $format );
}
function add_action() {}
function add_filter() {}
function apply_filters( $h, $v ) { return $v; }
function do_action( $h ) {}
// A person's language is the one a check gives their account, English when it gives none; switching
// into it and back is a stack, as WordPress's own switcher keeps one.
$GLOBALS['user_locale']  = array();
$GLOBALS['locale_stack'] = array();
function switch_to_user_locale( $user_id ) {
	if ( ! isset( $GLOBALS['user_locale'][ (int) $user_id ] ) ) {
		return false;
	}

	$GLOBALS['locale_stack'][] = $GLOBALS['user_locale'][ (int) $user_id ];

	return true;
}
function switch_to_locale( $locale ) { return false; }
function restore_previous_locale() { array_pop( $GLOBALS['locale_stack'] ); return true; }
function determine_locale() { return array() === $GLOBALS['locale_stack'] ? 'en_US' : end( $GLOBALS['locale_stack'] ); }
/**
 * Core's `add_query_arg()` in its two forms, a fragment kept at the end, as core keeps it.
 *
 * @param mixed ...$args The arguments, in either form.
 * @return string
 */
function add_query_arg( ...$args ) {
	if ( is_array( $args[0] ) ) {
		$pairs = $args[0];
		$url   = (string) ( $args[1] ?? '' );
	} else {
		$pairs = array( $args[0] => $args[1] );
		$url   = (string) ( $args[2] ?? '' );
	}

	$fragment = '';

	if ( false !== strpos( $url, '#' ) ) {
		$fragment = substr( $url, strpos( $url, '#' ) );
		$url      = substr( $url, 0, strpos( $url, '#' ) );
	}

	foreach ( $pairs as $k => $v ) {
		$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v;
	}

	return $url . $fragment;
}
/**
 * `wp_mail()`, keeping what it was handed and the id the mail log's capture takes, and each
 * attachment's bytes as they are at the moment of sending, since the sender removes the file
 * right after.
 */
function wp_mail( $to, $subject, $body, $headers = array(), $attachments = array() ) {
	$files = array();

	foreach ( (array) $attachments as $path ) {
		$files[] = array(
			'name' => basename( $path ),
			'ics'  => is_file( $path ) ? (string) file_get_contents( $path ) : '',
		);
	}

	$GLOBALS['mail'][] = array(
		'to'          => $to,
		'context'     => WPCPM_Mail::take_context(),
		'subject'     => $subject,
		'body'        => $body,
		'headers'     => array_values( (array) $headers ),
		'attachments' => $files,
	);

	WPCPM_Mail::take_test();

	return true;
}
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/stubs/temp-dir.php';
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/stubs/cleaners.php';

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ics.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-capture.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-availability.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-calls.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-semester-report-screen.php';

/** The dashboards, each at the address a case gives it, '' while its page is not published. */
class WPCPM_Students_Dashboard {
	public static $url = '';
	public static function page_url() { return self::$url; }
}
/** As above. */
class WPCPM_Mentors_Dashboard {
	public static $url = '';
	public static function page_url() { return self::$url; }
}
/** As above. */
class WPCPM_Institutions_Dashboard {
	public static $url = '';
	public static function page_url() { return self::$url; }
}
/** As above. */
class WPCPM_Administrators_Dashboard {
	public static $url = '';
	public static function page_url() { return self::$url; }
}
/** The institutions the index holds by record, as a case gives them. */
class WPCPM_Institutions_Index {
	public static $rows = array();
	public static function row( $record ) { return self::$rows[ $record ] ?? null; }
}
/** The address argument the Institution Dashboard's switcher reads. */
class WPCPM_Institution_Roster {
	const ARG_VIEW = 'wpcpm_institution_view';
}

$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/*
 * The people and the clocks
 * --------------------------------------------------------------------
 */

$GLOBALS['opts']['date_format'] = 'F j, Y';
$GLOBALS['opts']['time_format'] = 'g:i a';

$GLOBALS['users'][20] = new WP_User( 20, 'Ben Lindqvist', 'ben@example.test', array( WPCPM_Roles::ROLE_MENTOR ) );
$GLOBALS['users'][30] = new WP_User( 30, 'Ada Okonjo', 'ada@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][31] = new WP_User( 31, 'Cy Moreau', 'cy@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][32] = new WP_User( 32, "Kai O'Example", 'kai@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][40] = new WP_User( 40, 'Ida Admin', 'ida@example.test', array( WPCPM_Roles::ROLE_ADMIN ) );
$GLOBALS['users'][50] = new WP_User( 50, 'Ines Example', 'ines@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['users'][51] = new WP_User( 51, 'Noor Example', 'noor@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );

// The mentor reads Tokyo's clock, two students their own, and Kai none, so the site's.
$GLOBALS['umeta'][20][ WPCPM_Mentor_Availability::META_TIMEZONE ] = 'Asia/Tokyo';
$GLOBALS['umeta'][30][ WPCPM_Mentor_Availability::META_TIMEZONE ] = 'America/New_York';
$GLOBALS['umeta'][31][ WPCPM_Mentor_Availability::META_TIMEZONE ] = 'Europe/Warsaw';

/**
 * A topic as a student or a mentor types one: markup-like, an ampersand, quotes, a word in braces that
 * is a placeholder's name, and a line break.
 */
const TYPED_TOPIC = "Blocks <3 & \"the\" 'loop' {when}\nand my first plugin";

/**
 * Every case: what it sends, through which sender, and the world it sends in.
 *
 * Each case names the sender (`send`), the calls it makes (`calls`: post ID to its start, end,
 * capacity, attendees, the student name stored on it and its topic), who is signed in (`actor`),
 * whether the mentor has set a meeting link (`link`), whether the dashboard pages are published
 * (`pages`), the site's name as WordPress stores it (`site`), and what the sender is handed (`args`).
 *
 * @return array<string,array>
 */
function cases() {
	$one   = array( 2099745900, 2099747700 );
	$two   = array( 2100350700, 2100352500 );
	$three = array( 2100934800, 2100936600 );
	$early = array( 2099725200, 2099727000 );

	$call = static function ( $when, $attendees, $name, $topic, $capacity = 1 ) {
		return array(
			'start'     => $when[0],
			'end'       => $when[1],
			'capacity'  => $capacity,
			'attendees' => $attendees,
			'name'      => $name,
			'topic'     => $topic,
		);
	};

	$base = array(
		'actor' => 0,
		'link'  => true,
		'pages' => true,
		'site'  => 'WordPress Credits',
		'args'  => array(),
	);

	$cases = array(
		// Booked: a one-to-one call, and a join of a session, which tells both people the same way.
		'booked-call'                       => array( 'send' => 'booked', 'calls' => array( 601 => $call( $one, array( 30 ), 'Ada Okonjo', TYPED_TOPIC ) ) ),
		'booked-call-bare'                  => array( 'send' => 'booked', 'link' => false, 'pages' => false, 'calls' => array( 601 => $call( $early, array( 32 ), '', '' ) ) ),
		'booked-call-site-name'             => array( 'send' => 'booked', 'site' => 'Ada &amp; Ben&#039;s 100% Credits', 'calls' => array( 601 => $call( $one, array( 31 ), 'Cy Moreau', '' ) ) ),
		'booked-call-topic-of-spaces'       => array( 'send' => 'booked', 'calls' => array( 601 => $call( $one, array( 30 ), 'Ada Okonjo', '   ' ) ) ),
		'joined-session'                    => array( 'send' => 'joined', 'args' => array( 31 ), 'calls' => array( 611 => $call( $one, array( 30, 31 ), '', 'Release cycle', 6 ) ) ),
		'joined-session-bare'               => array( 'send' => 'joined', 'args' => array( 32 ), 'link' => false, 'pages' => false, 'calls' => array( 611 => $call( $early, array( 32 ), '', '', 6 ) ) ),
		// A series: one email each, the dates listed, the count in the subject and the first line.
		'series-two'                        => array( 'send' => 'series', 'args' => array( 30 ), 'calls' => array( 621 => $call( $one, array( 30 ), '', TYPED_TOPIC, 6 ), 622 => $call( $two, array( 30 ), '', TYPED_TOPIC, 6 ) ) ),
		'series-one-bare'                   => array( 'send' => 'series', 'args' => array( 32 ), 'link' => false, 'pages' => false, 'site' => 'Ada &amp; Ben&#039;s 100% Credits', 'calls' => array( 621 => $call( $early, array( 32 ), '', '', 6 ) ) ),
		'series-three-topic-of-spaces'      => array( 'send' => 'series', 'args' => array( 31 ), 'link' => false, 'calls' => array( 621 => $call( $one, array( 31 ), '', '   ', 6 ), 622 => $call( $two, array( 31 ), '', '   ', 6 ), 623 => $call( $three, array( 31 ), '', '   ', 6 ) ) ),
		// Moved: the students, and the mentor when somebody else moved it.
		'moved-by-an-administrator'         => array( 'send' => 'moved', 'actor' => 40, 'args' => array( $early[0], 3 ), 'calls' => array( 631 => $call( $one, array( 30, 31 ), '', TYPED_TOPIC, 6 ) ) ),
		'moved-by-the-mentor-bare'          => array( 'send' => 'moved', 'actor' => 20, 'pages' => false, 'args' => array( $early[0], 1 ), 'calls' => array( 631 => $call( $one, array( 32 ), '', '', 6 ) ) ),
		'moved-topic-of-spaces'             => array( 'send' => 'moved', 'actor' => 40, 'args' => array( $two[0], 2 ), 'calls' => array( 631 => $call( $one, array( 30 ), '', '   ', 6 ) ) ),
		// Canceled: everybody but the person who pressed Cancel, each named or "an Administrator".
		'cancelled-call-by-the-student'     => array( 'send' => 'cancelled', 'actor' => 30, 'calls' => array( 641 => $call( $one, array( 30 ), 'Ada Okonjo', TYPED_TOPIC ) ) ),
		'cancelled-call-by-the-mentor'      => array( 'send' => 'cancelled', 'actor' => 20, 'site' => 'Ada &amp; Ben&#039;s 100% Credits', 'calls' => array( 641 => $call( $one, array( 31 ), 'Cy Moreau', '' ) ) ),
		'cancelled-call-by-an-administrator' => array( 'send' => 'cancelled', 'actor' => 40, 'pages' => false, 'calls' => array( 641 => $call( $early, array( 32 ), '', '' ) ) ),
		'cancelled-session-by-the-mentor'   => array( 'send' => 'cancelled', 'actor' => 20, 'args' => array( 4 ), 'calls' => array( 651 => $call( $one, array( 30, 31 ), '', 'Release cycle', 6 ) ) ),
		'cancelled-session-by-an-administrator' => array( 'send' => 'cancelled', 'actor' => 40, 'pages' => false, 'args' => array( 2 ), 'calls' => array( 651 => $call( $early, array( 30, 32 ), '', '', 6 ) ) ),
		// Left: the student who left, and the one the students sync took off for a new mentor.
		'left-session'                      => array( 'send' => 'left', 'args' => array( 30, 5, false ), 'calls' => array( 661 => $call( $one, array( 31 ), '', TYPED_TOPIC, 6 ) ) ),
		'released-from-session-bare'        => array( 'send' => 'left', 'args' => array( 32, 2, true ), 'pages' => false, 'calls' => array( 661 => $call( $early, array(), '', '', 6 ) ) ),
		// Reminded: both people on a call, and everybody on a session, the mentor told how many.
		'reminder-call'                     => array( 'send' => 'reminder', 'calls' => array( 671 => $call( $one, array( 30 ), 'Ada Okonjo', TYPED_TOPIC ) ) ),
		'reminder-call-bare'                => array( 'send' => 'reminder', 'link' => false, 'pages' => false, 'calls' => array( 671 => $call( $early, array( 32 ), '', '' ) ) ),
		'reminder-session-of-one'           => array( 'send' => 'reminder', 'link' => false, 'calls' => array( 681 => $call( $one, array( 31 ), '', '', 6 ) ) ),
		'reminder-session-of-three'         => array( 'send' => 'reminder', 'site' => 'Ada &amp; Ben&#039;s 100% Credits', 'calls' => array( 681 => $call( $two, array( 30, 31, 32 ), '', 'Release cycle', 6 ) ) ),
		// The semester report: drafted (to an account and to an address with none), approved,
		// and the consent request.
		'report-drafted-one-in-progress'    => array( 'send' => 'drafted', 'args' => array( 'recSEED000000016', '2026-H1', 1 ), 'calls' => array() ),
		'report-drafted-three-not-indexed'  => array( 'send' => 'drafted', 'args' => array( 'recSEED000000017', '2026-H2', 3 ), 'calls' => array() ),
		'report-drafted-bare'               => array( 'send' => 'drafted', 'pages' => false, 'args' => array( 'recSEED000000016', '2026-H1', 0 ), 'calls' => array() ),
		'report-drafted-in-progress-bare'   => array( 'send' => 'drafted', 'pages' => false, 'args' => array( 'recSEED000000016', '2026-H2', 2 ), 'calls' => array() ),
		'report-approved'                   => array( 'send' => 'approved', 'args' => array( '2026-H1' ), 'calls' => array() ),
		'report-approved-bare'              => array( 'send' => 'approved', 'pages' => false, 'args' => array( '2026-H2' ), 'calls' => array() ),
		'report-consent'                    => array( 'send' => 'consent', 'args' => array( '2026-H1' ), 'calls' => array() ),
		'report-consent-bare'               => array( 'send' => 'consent', 'pages' => false, 'args' => array( '2026-H2' ), 'calls' => array() ),
	);

	foreach ( $cases as $name => $case ) {
		$cases[ $name ] = array_merge( $base, $case );
	}

	return $cases;
}

/**
 * A private method of a class, as its own class calls it.
 *
 * @param string $class  The class.
 * @param string $method The method.
 * @return ReflectionMethod
 */
function reach( $class, $method ) {
	$reached = new ReflectionMethod( $class, $method );

	if ( PHP_VERSION_ID < 80100 ) {
		$reached->setAccessible( true );
	}

	return $reached;
}

/**
 * One case's emails, through the real senders and the real mail layer.
 *
 * @param array $case One of `cases()`.
 * @return array[] Each email as `wp_mail()` was handed it, in the order it was sent.
 */
function emails_of( array $case ) {
	$GLOBALS['mail']      = array();
	$GLOBALS['posts']     = array();
	$GLOBALS['pmeta']     = array();
	$GLOBALS['uid']       = (int) $case['actor'];
	$GLOBALS['site_name'] = $case['site'];

	WPCPM_Students_Dashboard::$url       = $case['pages'] ? 'https://example.test/student-report-card/' : '';
	WPCPM_Mentors_Dashboard::$url        = $case['pages'] ? 'https://example.test/mentor-report-card/' : '';
	WPCPM_Institutions_Dashboard::$url   = $case['pages'] ? 'https://example.test/institution-dashboard/' : '';
	WPCPM_Administrators_Dashboard::$url = $case['pages'] ? 'https://example.test/administrator-dashboard/' : '';
	WPCPM_Institutions_Index::$rows      = array( 'recSEED000000016' => array( 'name' => 'Example University & "Partners"' ) );

	$GLOBALS['umeta'][20][ WPCPM_Mentor_Availability::META ] = array( 'meeting_url' => $case['link'] ? 'https://meet.example.test/ben-lindqvist?pwd=EXAMPLE-NOT-A-REAL-CODE' : '' );

	foreach ( $case['calls'] as $id => $call ) {
		$post               = new WP_Post();
		$post->ID           = $id;
		$post->post_type    = WPCPM_Mentor_Calls::POST_TYPE;
		$post->post_content = $call['topic'];

		$GLOBALS['posts'][ $id ] = $post;
		$GLOBALS['pmeta'][ $id ] = array(
			WPCPM_Mentor_Calls::META_START    => $call['start'],
			WPCPM_Mentor_Calls::META_END      => $call['end'],
			WPCPM_Mentor_Calls::META_MENTOR   => 20,
			WPCPM_Mentor_Calls::META_STUDENT  => $call['attendees'],
			WPCPM_Mentor_Calls::META_NAME     => $call['name'],
			WPCPM_Mentor_Calls::META_CAPACITY => $call['capacity'],
		);
	}

	$mentor = $GLOBALS['users'][20];
	$ids    = array_keys( $case['calls'] );
	$first  = $ids ? $GLOBALS['posts'][ $ids[0] ] : null;
	$args   = $case['args'];

	switch ( $case['send'] ) {
		case 'booked':
			reach( 'WPCPM_Mentor_Calls', 'notify_booked' )->invoke( null, $first->ID, $mentor, $GLOBALS['users'][ $case['calls'][ $first->ID ]['attendees'][0] ] );
			break;
		case 'joined':
			WPCPM_Mentor_Calls::notify_joined( $first->ID, $mentor, $GLOBALS['users'][ $args[0] ], 2 );
			break;
		case 'series':
			WPCPM_Mentor_Calls::notify_joined_series( $ids, $mentor, $GLOBALS['users'][ $args[0] ], array_fill_keys( $ids, 1 ) );
			break;
		case 'moved':
			WPCPM_Mentor_Calls::notify_session_moved( $first->ID, $args[0], $args[1] );
			break;
		case 'cancelled':
			reach( 'WPCPM_Mentor_Calls', 'notify_cancelled' )->invoke( null, $first, WPCPM_Mentor_Calls::details( $first ), $args ? $args[0] : null );
			break;
		case 'left':
			WPCPM_Mentor_Calls::notify_left( $first->ID, $args[0], $args[1], $args[2] );
			break;
		case 'reminder':
			reach( 'WPCPM_Mentor_Calls', 'notify_reminder' )->invoke( null, $first, WPCPM_Mentor_Calls::REMINDER_LEAD );
			break;
		case 'drafted':
			$build = reach( 'WPCPM_Semester_Report_Screen', 'drafted_message' )->invoke( null, $args[0], $args[1], $args[2] );
			WPCPM_Mail::send( $GLOBALS['users'][40], WPCPM_Semester_Report_Screen::MAIL_DRAFTED, $build );
			WPCPM_Mail::send_to( 'reports@example.test', WPCPM_Semester_Report_Screen::MAIL_DRAFTED, $build );
			break;
		case 'approved':
			foreach ( array( 50, 51 ) as $member ) {
				WPCPM_Mail::send( $GLOBALS['users'][ $member ], WPCPM_Semester_Report_Screen::MAIL_APPROVED, reach( 'WPCPM_Semester_Report_Screen', 'approved_message' )->invoke( null, $args[0] ) );
			}
			break;
		case 'consent':
			foreach ( array( 30, 32 ) as $student ) {
				WPCPM_Mail::send( $student, WPCPM_Semester_Report_Screen::MAIL_CONTEXT, reach( 'WPCPM_Semester_Report_Screen', 'ask_message' )->invoke( null, $args[0] ) );
			}
			break;
	}

	return array_map( 'recorded_form', $GLOBALS['mail'] );
}

/**
 * An email as the recording holds it: every address written "at example.test", and each calendar
 * file unfolded, with the moment it was written as "recorded".
 *
 * @param array $mail What `wp_mail()` was handed.
 * @return array
 */
function recorded_form( array $mail ) {
	$plain = static function ( $text ) {
		return preg_replace( '/([A-Za-z0-9._%+-]+)@example\.test/', '$1 at example.test', (string) $text );
	};

	foreach ( $mail['attachments'] as $n => $file ) {
		$mail['attachments'][ $n ]['ics'] = $plain( preg_replace( '/^DTSTAMP:\d{8}T\d{6}Z\r$/m', "DTSTAMP:recorded\r", str_replace( "\r\n ", '', $file['ics'] ) ) );
	}

	$mail['to']      = $plain( $mail['to'] );
	$mail['headers'] = array_map( $plain, $mail['headers'] );

	return $mail;
}

/**
 * The id each recorded email is logged under now: as recorded, while the emails are the builders'.
 *
 * @param string $case     The case.
 * @param int    $index    Which of its emails, from 0.
 * @param string $recorded The id the recording holds.
 * @return string
 */
function sent_as( $case, $index, $recorded ) {
	return $recorded;
}

$fixture = __DIR__ . '/fixtures/calls-reports-1-122-20.json';

if ( in_array( '--record', $argv, true ) ) {
	// Made once, from 1.122.20: a recording made again would be made from whatever the code says
	// then, and would prove nothing.
	if ( file_exists( $fixture ) ) {
		fwrite( STDERR, "The recording exists and is never made twice.\n" );
		exit( 1 );
	}

	$recorded = array(
		'_comment' => 'What each Mentor calls and Semester reports email said in 1.122.20, recorded through the real senders and WPCPM_Mail by bin/test-calls-reports-text.php --record before the emails moved into the template registry. Invented names, every address written "at example.test", a fake meeting code, and each calendar file unfolded with its DTSTAMP written "recorded". Never edit by hand: the registry is held to these bytes.',
		'cases'    => array(),
	);

	foreach ( cases() as $name => $case ) {
		$recorded['cases'][ $name ] = emails_of( $case );
	}

	file_put_contents( $fixture, json_encode( $recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
	printf( "Recorded %d cases, %d emails, in %s\n", count( $recorded['cases'] ), array_sum( array_map( 'count', $recorded['cases'] ) ), basename( $fixture ) );

	exit( 0 );
}

$recorded = is_file( $fixture ) ? json_decode( (string) file_get_contents( $fixture ), true ) : null;
$cases    = cases();

ck( 'the recording is there, and holds every case', is_array( $recorded ) && isset( $recorded['cases'] ) ? array_keys( $recorded['cases'] ) : null, array_keys( $cases ) );
ck( 'the recording is the one made from 1.122.20', is_file( $fixture ) ? hash_file( 'sha256', $fixture ) : null, '29341e03ddb31ae9bd24d7fce8e8a3add2d9131d348d1d7a3d3bae58c3bf1fcc' );

foreach ( $cases as $name => $case ) {
	$want = isset( $recorded['cases'][ $name ] ) ? $recorded['cases'][ $name ] : array();
	$got  = emails_of( $case );

	ck( $name . ': the same emails, to the same people, in the same order', array_column( $got, 'to' ), array_column( $want, 'to' ) );

	foreach ( $want as $n => $mail ) {
		$now = isset( $got[ $n ] ) ? $got[ $n ] : array_fill_keys( array_keys( $mail ), null );

		ck( $name . ' #' . $n . ': logged under its own id', $now['context'], sent_as( $name, $n, $mail['context'] ) );
		ck( $name . ' #' . $n . ': the subject, byte for byte', $now['subject'], $mail['subject'] );
		ck( $name . ' #' . $n . ': the body, byte for byte, the signature and footer included', $now['body'], $mail['body'] );
		ck( $name . ' #' . $n . ': the headers and the calendar files, byte for byte', array( $now['headers'], $now['attachments'] ), array( $mail['headers'], $mail['attachments'] ) );
	}
}

ck( 'and the recording holds no address, no real meeting code and no record ID but the seeds\'', is_file( $fixture ) ? array( preg_match( '/[A-Za-z0-9._%+-]*@[A-Za-z0-9-]+\.[A-Za-z]{2,}/', (string) file_get_contents( $fixture ) ), preg_match_all( '/pwd=([A-Za-z0-9-]+)/', (string) file_get_contents( $fixture ), $codes ) > 0 ? array_values( array_unique( $codes[1] ) ) : array(), preg_match( '/rec(?!SEED0\d{9})[A-Za-z0-9]{14}/', (string) file_get_contents( $fixture ) ) ) : null, array( 0, array( 'EXAMPLE-NOT-A-REAL-CODE' ), 0 ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run it to verify it fails, for want of a recording**

Run: `php bin/test-calls-reports-text.php`
Expected: every check fails against a recording that is not there yet, its hash among them, and the suite ends `46 FAILURE(S) (46 checks)`, exiting 1.

- [ ] **Step 3: Record today's emails, once**

Run: `php bin/test-calls-reports-text.php --record`
Expected: `Recorded 43 cases, 87 emails, in calls-reports-1-122-20.json`. The file is written once, here, against the code as it is before this release, and is never edited by hand. `shasum -a 256 bin/fixtures/calls-reports-1-122-20.json` prints `29341e03ddb31ae9bd24d7fce8e8a3add2d9131d348d1d7a3d3bae58c3bf1fcc`, the hash the suite pins: the recording depends on nothing but the code and the suite (the clock is fixed, the calendar files' moment is written "recorded"), so a different hash means the code or the suite differs from what this plan was proved against, and the task stops there.

Run it again: `php bin/test-calls-reports-text.php --record`
Expected: `The recording exists and is never made twice.`, exit 1, and the file's hash unchanged.

- [ ] **Step 4: Run it to verify it passes, and that the recording is a fixture the privacy checks accept**

Run, one per command: `php bin/test-calls-reports-text.php`, `php bin/test-fixtures.php`
Expected: `ALL PASS (394 checks)`; `ALL PASS` (no fixture holds an address of any kind, a refused name or a record ID).

- [ ] **Step 5: Run the whole battery**

Run, from the plugin root, every suite in its own process, then the references check:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 6: Commit**

```bash
git add bin/test-calls-reports-text.php bin/fixtures/calls-reports-1-122-20.json
git commit -m "Emails E3: record what every Mentor calls and Semester reports email says in 1.122.20, before it moves"
```

---


### Task 2: The catalog's new ids, and the Mentor calls' and the Semester reports' shipped text

The catalog names each of the nineteen Mentor calls emails and keeps the five ids of before, their labels saying so; the registry gains the two areas' files, each sentence the translatable string its sender used; `all()` is built once a request for each language (ruling E3-6); and `render()` sends the shipped subject when an edit's fills to nothing (ruling E3-7). Nothing sends the new templates yet: the senders move in Tasks 3 and 4, word for word, against Task 1's recording.

**Files:**
- Modify: `includes/mail/class-wpcpm-mail-catalog.php` (the nineteen ids; the five old labels; the catalog's docblock on an empty audience)
- Create: `includes/mail/templates/calls.php`, `includes/mail/templates/reports.php`
- Modify: `includes/mail/class-wpcpm-mail-templates.php` (`AREAS`, `all()`, the new `locale()`, `render()`)
- Modify: `bin/test-mail-catalog.php`, `bin/test-mail-templates.php`, `bin/test-emails-templates.php`

**Interfaces:**
- Consumes: `WPCPM_Mail_Templates::all()`, `get()`, `render()`, `example_values()`, `page_of_type()` and the fill rules of 1.122.19; `WPCPM_Mentor_Calls::format_range( $start, $end, DateTimeZone $zone ): string` and `::relative( $start ): string`; `WPCPM_Mentor_Availability::viewer_timezone( $user_id ): DateTimeZone` and `::zone_label( $name ): string`; `WPCPM_Cohort::current(): string` and `::label( $key ): string`; `WPCPM_Semester_Report_Screen::report_url( $cohort ): string`.
- Produces:
  - `WPCPM_Mail_Catalog::emails()` gains `call-booked-mentor`, `call-booked-mentor-no-link`, `call-booked-student`, `session-joined-mentor`, `session-joined-mentor-no-link`, `session-joined-student`, `series-joined-mentor`, `series-joined-mentor-no-link`, `series-joined-student`, `session-moved-student`, `session-moved-mentor`, `session-left`, `session-released`, `call-cancelled-mentor`, `call-cancelled-student`, `session-cancelled`, `call-reminder-mentor`, `call-reminder-student` and `session-reminder`, each `array{label:string,module:'calls',audience:'mentor'|'student'|'',test:false}`; the five ids of before keep their entries, their labels ending ` (until 1.122.21)`.
  - `includes/mail/templates/calls.php` returns those nineteen entries, `includes/mail/templates/reports.php` returns `report-drafted`, `report-approved` and `report-consent`, each `array{trigger:string,subject:string,body:string,eol?:"\r\n",placeholders:array<string,array{means:string,example:string|Closure,required:bool,body_only?:bool}>}` (the Mentor calls' each say `body_only`, the reports' none, since none of theirs goes in the body alone).
  - The placeholders the senders of Tasks 3 and 4 fill: `{site_name}`, `{student_name}`, `{mentor_name}`, `{with_whom}`, `{when}`, `{was_when}`, `{call_date}`, `{session_dates}`, `{session_count}`, `{group_session_count}`, `{date_count}`, `{in_how_long}`, `{time_zone}`, `{meeting_place}`, `{topic}`, `{session_topic}`, `{canceled_by}`, `{dashboard_link}`; and `{institution_name}`, `{semester}`, `{students_in_progress}`, `{review_link}`, `{report_link}`.
  - `WPCPM_Mail_Templates::AREAS` is `invitations`, `calls`, `reports`; `all()` keeps what it built for each locale for the request; `private static function locale(): string`; `render( $id, array $values ): array{subject:string,body:string}|null` fills the shipped subject when the edit's fills to nothing, as the mail layer will clean it.

- [ ] **Step 1: The catalog's suite**

The count of the plugin's emails reads 63, and two checks name the Mentor calls' twenty-four ids (the five of before, which the account decides and whose labels end " (until 1.122.21)", and the nineteen) and the Semester reports' three:

In `bin/test-mail-catalog.php`, replace:

```php

$emails = WPCPM_Mail_Catalog::emails();

ck( 'the catalog names the plugin\'s 44 emails: the 42 contexts it sent before 1.122.19, the institution invitation for an agreement on file and the test of the signature and footer', count( array_filter( $emails, function ( $e ) { return ! in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) ), 44 );
ck( 'and reads the same list twice in a request', WPCPM_Mail_Catalog::emails() === $emails, true );

$bad = array();
```

with:

```php

$emails = WPCPM_Mail_Catalog::emails();

ck( 'the catalog names the plugin\'s 63 emails: the 42 contexts it sent before 1.122.19, the institution invitation for an agreement on file, the test of the signature and footer, and the 19 Mentor calls emails of their own since 1.122.21', count( array_filter( $emails, function ( $e ) { return ! in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) ), 63 );
ck( 'and reads the same list twice in a request', WPCPM_Mail_Catalog::emails() === $emails, true );

$bad = array();
```

In `bin/test-mail-catalog.php`, replace:

```php

ck( 'a known id reads back', array( WPCPM_Mail_Catalog::label( 'call-booked' ), WPCPM_Mail_Catalog::module_of( 'call-booked' ) ), array( 'Call booked', 'calls' ) );

ck( 'the last member leaving an institution is named, as the Administrators\' email it is', WPCPM_Mail_Catalog::get( 'member-last' ), array( 'label' => 'Institution has no members left, to Administrators', 'module' => 'institutions', 'audience' => 'administrator', 'test' => false ) );

ck( 'the areas are the ones the Log lists, in its order', array_values( WPCPM_Mail_Catalog::modules() ), array( 'Invitations', 'Mentor calls', 'Institutions', 'Semester reports', 'Sponsors', 'WordPress', 'Two Factor', 'Other' ) );
```

with:

```php

ck( 'a known id reads back', array( WPCPM_Mail_Catalog::label( 'call-booked' ), WPCPM_Mail_Catalog::module_of( 'call-booked' ) ), array( 'Call booked (until 1.122.21)', 'calls' ) );

// Each email of the Mentor calls area that says words of its own has an id of its own, named for who
// receives it; the two a group session sends its mentor and its students in the same words are one
// each, and the account decides who it is to. The five ids the area was sent under before 1.122.21
// keep their names with " (until 1.122.21)" after them, so the Log's Email field, which lists them
// beside the new names, tells the old from the new.
$calls = array();
foreach ( $emails as $id => $e ) {
	if ( 'calls' === $e['module'] ) {
		$calls[ $id ] = $e['label'] . ' / ' . ( '' === $e['audience'] ? 'the account decides' : $e['audience'] );
	}
}
ck(
	'the Mentor calls area names its five ids of before 1.122.21, each labeled as one of before, and the nineteen emails it sends since, each with who receives it',
	$calls,
	array(
		'call-booked'                   => 'Call booked (until 1.122.21) / the account decides',
		'series-joined'                 => 'Joined a session series (until 1.122.21) / the account decides',
		'session-moved'                 => 'Group session moved (until 1.122.21) / the account decides',
		'call-cancelled'                => 'Call canceled (until 1.122.21) / the account decides',
		'call-reminder'                 => 'Call reminder (until 1.122.21) / the account decides',
		'call-booked-mentor'            => 'Call booked, to the mentor / mentor',
		'call-booked-mentor-no-link'    => 'Call booked, to a mentor with no meeting link / mentor',
		'call-booked-student'           => 'Call booked, to the student / student',
		'session-joined-mentor'         => 'Group session joined, to the mentor / mentor',
		'session-joined-mentor-no-link' => 'Group session joined, to a mentor with no meeting link / mentor',
		'session-joined-student'        => 'Group session joined, to the student / student',
		'series-joined-mentor'          => 'Session series joined, to the mentor / mentor',
		'series-joined-mentor-no-link'  => 'Session series joined, to a mentor with no meeting link / mentor',
		'series-joined-student'         => 'Session series joined, to the student / student',
		'session-moved-student'         => 'Group session moved, to the student / student',
		'session-moved-mentor'          => 'Group session moved, to the mentor / mentor',
		'session-left'                  => 'Left a group session, to the student / student',
		'session-released'              => 'Place on a group session released, to the student / student',
		'call-cancelled-mentor'         => 'Call canceled, to the mentor / mentor',
		'call-cancelled-student'        => 'Call canceled, to the student / student',
		'session-cancelled'             => 'Group session canceled / the account decides',
		'call-reminder-mentor'          => 'Call reminder, to the mentor / mentor',
		'call-reminder-student'         => 'Call reminder, to the student / student',
		'session-reminder'              => 'Group session reminder / the account decides',
	)
);
ck(
	'and the Semester reports area\'s three ids are its three emails, as they were',
	array_map( function ( $id ) { return WPCPM_Mail_Catalog::get( $id ); }, array( 'report-drafted', 'report-approved', 'report-consent' ) ),
	array(
		array( 'label' => 'Semester report drafted', 'module' => 'reports', 'audience' => 'administrator', 'test' => false ),
		array( 'label' => 'Semester report approved', 'module' => 'reports', 'audience' => 'institution', 'test' => false ),
		array( 'label' => 'Consent for the semester report', 'module' => 'reports', 'audience' => 'student', 'test' => false ),
	)
);

ck( 'the last member leaving an institution is named, as the Administrators\' email it is', WPCPM_Mail_Catalog::get( 'member-last' ), array( 'label' => 'Institution has no members left, to Administrators', 'module' => 'institutions', 'audience' => 'administrator', 'test' => false ) );

ck( 'the areas are the ones the Log lists, in its order', array_values( WPCPM_Mail_Catalog::modules() ), array( 'Invitations', 'Mentor calls', 'Institutions', 'Semester reports', 'Sponsors', 'WordPress', 'Two Factor', 'Other' ) );
```

- [ ] **Step 2: The registry's suite**

The registry lists twenty-seven templates in the areas' order; the check that every template is whole reads each one's line ending and its required and body-only placeholders from a table, where it read the invitations' for all; the dash check reads every area's file; three new blocks pin the examples (a call tomorrow at 3 pm on the clock of the Administrator looking, a series weekly, invented names, links of the site's or example.test's, the semester running now), the registry built once a request for each language (the stand-in `__()` counts what it is asked, and the stand-in `get_locale()` reads a check's locale), and an edit whose subject fills to nothing. The suite loads `WPCPM_Cohort`, `WPCPM_Mentor_Availability`, `WPCPM_Mentor_Calls` and `WPCPM_Semester_Report_Screen`, which the examples are worked out with, and the stand-ins they read:

In `bin/test-mail-templates.php`, replace:

```php
 * The template registry: what each email says, and how a text is filled.
 *
 * - **The catalog stays the one list of names.** Every template's id is a catalog id, its name, area
 *   and audience are the catalog's, and the Invitations are the four invitations and the institution's
 *   second one, for an institution whose Collaboration Agreement is on file.
 * - **Every template is whole.** Each placeholder its text uses is one it declares, each required one
 *   is in the body, none that goes in the body alone is in the subject, every declared one says what
 *   it means and has an example, and filled with the examples no placeholder is left. The
 *   set-password link is required in every invitation, in its body.
 * - **Filling.** A value is never read as a placeholder; an optional sentence with nothing to say
 *   goes, and so does a line left with nothing, the colon line above it and the blank line it leaves
 *   doubled; only a full stop followed by a space ends a sentence; a required placeholder's line
```

with:

```php
 * The template registry: what each email says, and how a text is filled.
 *
 * - **The catalog stays the one list of names.** Every template's id is a catalog id, its name, area
 *   and audience are the catalog's; the Invitations are the four invitations and the institution's
 *   second one, for an institution whose Collaboration Agreement is on file, and the Mentor calls and
 *   the Semester reports follow, each email that says different words a template of its own.
 * - **Every template is whole.** Each placeholder its text uses is one it declares, each required one
 *   is in the body, none that goes in the body alone is in the subject, every declared one says what
 *   it means and has an example, and filled with the examples no placeholder is left. The
 *   set-password link is required in every invitation, in its body; an email about a call needs its
 *   time, and a meeting link, and what a student or a mentor typed, go in the body alone. The
 *   examples read as a real email to the Administrator looking would: a call tomorrow at 3 pm on
 *   their own clock, invented names, and links of the site's or of example.test.
 * - **Built once a request for each language**, and an edit whose subject fills to nothing, as the
 *   mail layer will clean it, is sent with the shipped subject, since the mail layer sends no email
 *   without one.
 * - **Filling.** A value is never read as a placeholder; an optional sentence with nothing to say
 *   goes, and so does a line left with nothing, the colon line above it and the blank line it leaves
 *   doubled; only a full stop followed by a space ends a sentence; a required placeholder's line
```

In `bin/test-mail-templates.php`, replace:

```php
	public function get_error_message() { return $this->message; }
}

$GLOBALS['opts']     = array();
$GLOBALS['autoload'] = array();
$GLOBALS['locales']  = array();

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function wp_login_url( $r = '' ) { return 'https://example.test/wp-login.php'; }
// Core's `add_query_arg( $args, $url )`, as the example set-password link is built over the login page.
function add_query_arg( $args, $url ) { foreach ( $args as $k => $v ) { $url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v; } return $url; }
function get_bloginfo( $k = 'name' ) { $GLOBALS['site_name_read'] = ( $GLOBALS['site_name_read'] ?? 0 ) + 1; return 'WordPress Credits'; }
function is_email( $s ) { return false !== filter_var( (string) $s, FILTER_VALIDATE_EMAIL ) ? $s : false; }
// Nobody here may manage the program, so each account is the kind its role says.
```

with:

```php
	public function get_error_message() { return $this->message; }
}

$GLOBALS['opts']     = array(
	'date_format' => 'F j, Y',
	'time_format' => 'g:i a',
);
$GLOBALS['autoload'] = array();
$GLOBALS['locales']  = array();

// Each string asked for is counted, so a check can tell whether the registry was built again.
function __( $s, $d = null ) { $GLOBALS['translated'] = ( $GLOBALS['translated'] ?? 0 ) + 1; return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function wp_login_url( $r = '' ) { return 'https://example.test/wp-login.php'; }
// Core's two forms, `add_query_arg( $args, $url )`, as the example set-password link is built over the
// login page, and `add_query_arg( $key, $value, $url )`, as a report's address is.
function add_query_arg( ...$args ) {
	$pairs = is_array( $args[0] ) ? $args[0] : array( $args[0] => $args[1] );
	$url   = (string) ( is_array( $args[0] ) ? $args[1] : $args[2] );

	foreach ( $pairs as $k => $v ) {
		$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v;
	}

	return $url;
}
function get_bloginfo( $k = 'name' ) { $GLOBALS['site_name_read'] = ( $GLOBALS['site_name_read'] ?? 0 ) + 1; return 'WordPress Credits'; }
function is_email( $s ) { return false !== filter_var( (string) $s, FILTER_VALIDATE_EMAIL ) ? $s : false; }
// Nobody here may manage the program, so each account is the kind its role says.
```

In `bin/test-mail-templates.php`, replace:

```php
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $autoload; return true; }
function current_user_can( $cap ) { return false; }
// The language the fingerprint of a shipped text is built in, recorded: the site's.
function get_locale() { return 'en_US'; }
function switch_to_locale( $locale ) { $GLOBALS['locales'][] = $locale; return true; }
function restore_previous_locale() { $GLOBALS['locales'][] = 'restored'; return true; }
function add_action() {}
```

with:

```php
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $autoload; return true; }
function current_user_can( $cap ) { return false; }
// The language the fingerprint of a shipped text is built in, recorded: the site's, or the one a
// check sets.
function get_locale() { return $GLOBALS['locale'] ?? 'en_US'; }
// Who is looking at an example, and the clock their account keeps: a call's examples are on it.
$GLOBALS['uid']   = 0;
$GLOBALS['umeta'] = array();
function get_current_user_id() { return $GLOBALS['uid']; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function wp_timezone_string() { return 'Europe/Warsaw'; }
function wp_timezone() { return new DateTimeZone( wp_timezone_string() ); }
function wp_date( $format, $timestamp = null, $zone = null ) { return ( new DateTimeImmutable( '@' . (int) ( null === $timestamp ? time() : $timestamp ) ) )->setTimezone( $zone instanceof DateTimeZone ? $zone : wp_timezone() )->format( $format ); }
function human_time_diff( $from, $to = 0 ) { return round( abs( (int) $to - (int) $from ) / HOUR_IN_SECONDS ) . ' hours'; }
function switch_to_locale( $locale ) { $GLOBALS['locales'][] = $locale; return true; }
function restore_previous_locale() { $GLOBALS['locales'][] = 'restored'; return true; }
function add_action() {}
```

In `bin/test-mail-templates.php`, replace:

```php
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
// Which kind of person an account is, which the footer's dashboard line reads.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-capture.php';

/** The one setting the footer reads, Contact address in emails, as a check sets it. */
class WPCPM_Settings {
```

with:

```php
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
// Which kind of person an account is, which the footer's dashboard line reads.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-capture.php';
// What the examples of the Mentor calls and the Semester reports are worked out with, as their emails
// are: a call's times on a clock, and a semester and its report's address.
require_once __DIR__ . '/../includes/class-wpcpm-cohort.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-availability.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-calls.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-semester-report-screen.php';

/** The one setting the footer reads, Contact address in emails, as a check sets it. */
class WPCPM_Settings {
```

In `bin/test-mail-templates.php`, replace:

```php

$all = WPCPM_Mail_Templates::all();

ck( 'the Invitations are the four invitations and the institution\'s second one, in that order', array_keys( $all ), array( 'invite-student', 'invite-mentor', 'invite-institution', 'invite-institution-settled', 'invite-sponsor' ) );

$named = array();
foreach ( $all as $id => $entry ) {
```

with:

```php

$all = WPCPM_Mail_Templates::all();

ck(
	'the registry holds the Invitations, the four invitations and the institution\'s second one, then the Mentor calls, then the Semester reports, in that order',
	array_keys( $all ),
	array(
		'invite-student',
		'invite-mentor',
		'invite-institution',
		'invite-institution-settled',
		'invite-sponsor',
		'call-booked-mentor',
		'call-booked-mentor-no-link',
		'call-booked-student',
		'session-joined-mentor',
		'session-joined-mentor-no-link',
		'session-joined-student',
		'series-joined-mentor',
		'series-joined-mentor-no-link',
		'series-joined-student',
		'session-moved-student',
		'session-moved-mentor',
		'session-left',
		'session-released',
		'call-cancelled-mentor',
		'call-cancelled-student',
		'session-cancelled',
		'call-reminder-mentor',
		'call-reminder-student',
		'session-reminder',
		'report-drafted',
		'report-approved',
		'report-consent',
	)
);

$named = array();
foreach ( $all as $id => $entry ) {
```

In `bin/test-mail-templates.php`, replace:

```php
		'link'       => array( WPCPM_Mail_Templates::required( $entry['placeholders'] ), WPCPM_Mail_Templates::body_only( $entry['placeholders'] ) ),
	);
}
$want_whole = array_fill_keys(
	array_keys( $all ),
	array( 'trigger' => true, 'undeclared' => array(), 'unused' => array(), 'missing' => array(), 'in_subject' => array(), 'about' => true, 'left' => array(), 'eol' => "\r\n", 'link' => array( array( 'set_password_link' ), array( 'set_password_link' ) ) )
);
ck( 'every template says what sends it, uses only the placeholders it declares and every one it declares, holds its required ones in its body and none that goes in the body in its subject, says what each means with an example, and leaves none unfilled; every invitation needs its set-password link, in its body, and keeps WordPress\'s line ending', $whole, $want_whole );

$student = WPCPM_Mail_Templates::render( 'invite-student', WPCPM_Mail_Templates::example_values( 'invite-student' ) );
ck( 'filled with its examples, an invitation carries a marked example link and the dashboard of its audience', array( false !== strpos( $student['body'], 'key=EXAMPLE-KEY-NOT-A-REAL-LINK' ), false !== strpos( $student['body'], "Your Student Report Card:\r\nhttps://example.test/student-report-card/" ), $student['subject'] ), array( true, true, '[WordPress Credits] Welcome to the WordPress Credits Program' ) );
```

with:

```php
		'link'       => array( WPCPM_Mail_Templates::required( $entry['placeholders'] ), WPCPM_Mail_Templates::body_only( $entry['placeholders'] ) ),
	);
}
// Each template's line ending, the placeholders it needs, and the ones that go in its body alone: the
// invitations and the two moved emails keep the line ending WordPress gives its own email, as the
// code wrote them; every email about a call needs its time; the mentor's confirmations sent only
// while the mentor has a meeting link need it; and a meeting link and what somebody typed go in the
// body wherever they are.
$shape = array(
	'invite-student'                => array( "\r\n", array( 'set_password_link' ), array( 'set_password_link' ) ),
	'invite-mentor'                 => array( "\r\n", array( 'set_password_link' ), array( 'set_password_link' ) ),
	'invite-institution'            => array( "\r\n", array( 'set_password_link' ), array( 'set_password_link' ) ),
	'invite-institution-settled'    => array( "\r\n", array( 'set_password_link' ), array( 'set_password_link' ) ),
	'invite-sponsor'                => array( "\r\n", array( 'set_password_link' ), array( 'set_password_link' ) ),
	'call-booked-mentor'            => array( "\n", array( 'when', 'meeting_place' ), array( 'meeting_place', 'topic' ) ),
	'call-booked-mentor-no-link'    => array( "\n", array( 'when' ), array( 'topic' ) ),
	'call-booked-student'           => array( "\n", array( 'when' ), array( 'meeting_place', 'topic' ) ),
	'session-joined-mentor'         => array( "\n", array( 'when', 'meeting_place' ), array( 'meeting_place', 'session_topic' ) ),
	'session-joined-mentor-no-link' => array( "\n", array( 'when' ), array( 'session_topic' ) ),
	'session-joined-student'        => array( "\n", array( 'when' ), array( 'meeting_place', 'session_topic' ) ),
	'series-joined-mentor'          => array( "\n", array( 'session_dates', 'meeting_place' ), array( 'meeting_place', 'session_topic' ) ),
	'series-joined-mentor-no-link'  => array( "\n", array( 'session_dates' ), array( 'session_topic' ) ),
	'series-joined-student'         => array( "\n", array( 'session_dates' ), array( 'meeting_place', 'session_topic' ) ),
	'session-moved-student'         => array( "\r\n", array( 'when' ), array( 'session_topic' ) ),
	'session-moved-mentor'          => array( "\r\n", array( 'when' ), array( 'session_topic' ) ),
	'session-left'                  => array( "\n", array( 'when' ), array( 'session_topic' ) ),
	'session-released'              => array( "\n", array( 'when' ), array( 'session_topic' ) ),
	'call-cancelled-mentor'         => array( "\n", array( 'when' ), array() ),
	'call-cancelled-student'        => array( "\n", array( 'when' ), array() ),
	'session-cancelled'             => array( "\n", array( 'when' ), array() ),
	'call-reminder-mentor'          => array( "\n", array( 'when' ), array( 'meeting_place', 'topic' ) ),
	'call-reminder-student'         => array( "\n", array( 'when' ), array( 'meeting_place', 'topic' ) ),
	'session-reminder'              => array( "\n", array( 'when' ), array( 'meeting_place', 'session_topic' ) ),
	'report-drafted'                => array( "\n", array( 'semester' ), array() ),
	'report-approved'               => array( "\n", array( 'semester' ), array() ),
	'report-consent'                => array( "\n", array( 'semester' ), array() ),
);
$want_whole = array();
foreach ( $shape as $id => $want ) {
	$want_whole[ $id ] = array( 'trigger' => true, 'undeclared' => array(), 'unused' => array(), 'missing' => array(), 'in_subject' => array(), 'about' => true, 'left' => array(), 'eol' => $want[0], 'link' => array( $want[1], $want[2] ) );
}
ck( 'every template says what sends it, uses only the placeholders it declares and every one it declares, holds its required ones in its body and none that goes in the body in its subject, says what each means with an example, and leaves none unfilled; every invitation needs its set-password link, in its body, every email about a call its time, and a meeting link and what somebody typed go in the body alone', $whole, $want_whole );

$student = WPCPM_Mail_Templates::render( 'invite-student', WPCPM_Mail_Templates::example_values( 'invite-student' ) );
ck( 'filled with its examples, an invitation carries a marked example link and the dashboard of its audience', array( false !== strpos( $student['body'], 'key=EXAMPLE-KEY-NOT-A-REAL-LINK' ), false !== strpos( $student['body'], "Your Student Report Card:\r\nhttps://example.test/student-report-card/" ), $student['subject'] ), array( true, true, '[WordPress Credits] Welcome to the WordPress Credits Program' ) );
```

In `bin/test-mail-templates.php`, replace:

```php
	}
}
ck( 'no trigger or placeholder description says module', array_values( preg_grep( '/\bmodules?\b/i', $words ) ), array() );
ck( 'and no em or en dash in the registry or its definitions', preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php' ) . (string) file_get_contents( __DIR__ . '/../includes/mail/templates/invitations.php' ) ), 0 );

echo "\n=== Filling a text ===\n";
```

with:

```php
	}
}
ck( 'no trigger or placeholder description says module', array_values( preg_grep( '/\bmodules?\b/i', $words ) ), array() );
$sources = (string) file_get_contents( __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php' );
foreach ( WPCPM_Mail_Templates::AREAS as $file ) {
	$sources .= (string) file_get_contents( __DIR__ . '/../includes/mail/templates/' . $file . '.php' );
}
ck( 'and no em or en dash in the registry or its definitions', preg_match( '/\x{2013}|\x{2014}/u', $sources ), 0 );

echo "\n=== The examples ===\n";

// The Administrator looking at a preview, or pressing Send me a test, keeps Tokyo's clock: an email
// about a call is filled with a call tomorrow at 3 pm on that clock, half an hour long.
$GLOBALS['uid']                             = 9;
$GLOBALS['umeta'][9]['wpcpm_timezone']      = 'Asia/Tokyo';
$tokyo                                      = new DateTimeZone( 'Asia/Tokyo' );
$tomorrow                                   = ( new DateTimeImmutable( 'tomorrow 15:00', $tokyo ) )->getTimestamp();
$booked                                     = WPCPM_Mail_Templates::example_values( 'call-booked-mentor' );
$series                                     = WPCPM_Mail_Templates::example_values( 'series-joined-student' );
ck(
	'an email about a call is filled with a call tomorrow at 3 pm on the clock of the Administrator looking at it, that clock named, and the day and the hours to go worked out from it',
	array( $booked['when'], $booked['time_zone'], WPCPM_Mail_Templates::example_values( 'call-cancelled-mentor' )['call_date'], WPCPM_Mail_Templates::example_values( 'call-reminder-mentor' )['in_how_long'] ),
	array( wp_date( 'F j, Y', $tomorrow, $tokyo ) . ' 3:00 pm to 3:30 pm', 'Asia / Tokyo', wp_date( 'F j, Y', $tomorrow, $tokyo ), WPCPM_Mentor_Calls::relative( $tomorrow ) )
);
ck(
	'a series, three weekly sessions from then, one to a line, counted in words',
	array( $series['session_dates'], $series['date_count'], $series['group_session_count'], WPCPM_Mail_Templates::example_values( 'series-joined-mentor' )['session_count'] ),
	array(
		implode(
			"\n",
			array_map(
				function ( $days ) use ( $tomorrow, $tokyo ) {
					return wp_date( 'F j, Y', $tomorrow + $days * DAY_IN_SECONDS, $tokyo ) . ' 3:00 pm to 3:30 pm';
				},
				array( 0, 7, 14 )
			)
		),
		'3 dates',
		'3 group sessions',
		'3 sessions',
	)
);
$GLOBALS['uid'] = 0;
ck( 'and with nobody signed in, on the site\'s clock', WPCPM_Mail_Templates::example_values( 'session-reminder' )['time_zone'], 'Europe / Warsaw' );

$links = array();
$names = array();
foreach ( array_keys( $all ) as $id ) {
	foreach ( WPCPM_Mail_Templates::example_values( $id ) as $name => $value ) {
		if ( preg_match_all( '#https?://[^\s/]+#', $value, $hosts ) ) {
			$links = array_merge( $links, $hosts[0] );
		}
		if ( in_array( $name, array( 'student_name', 'mentor_name', 'with_whom', 'canceled_by', 'institution_name' ), true ) ) {
			$names[] = $value;
		}
	}
}
ck( 'every example link is the site\'s own or example.test\'s, and every example name is an invented one', array( array_values( array_unique( $links ) ), array_values( array_unique( $names ) ) ), array( array( 'https://example.test', 'https://meet.example.test' ), array( 'Ada Okonjo', 'Ben Lindqvist', 'Example University' ) ) );
ck(
	'a semester report\'s examples name the semester running now and its report on the Institution Dashboard, and how many students were still in progress, with their verb',
	array_intersect_key( WPCPM_Mail_Templates::example_values( 'report-drafted' ), array_flip( array( 'semester', 'students_in_progress', 'review_link' ) ) ),
	array(
		'semester'             => WPCPM_Cohort::label( WPCPM_Cohort::current() ),
		'students_in_progress' => '2 students in this semester were',
		'review_link'          => 'https://example.test/institution-dashboard/?wpcpm_report=' . WPCPM_Cohort::current() . '#wpcpm-report',
	)
);

echo "\n=== Built once a request for each language ===\n";

WPCPM_Mail_Templates::all();
$GLOBALS['translated'] = 0;
$again                 = WPCPM_Mail_Templates::all();
$read_again            = $GLOBALS['translated'];
$GLOBALS['locale']     = 'pl_PL';
$GLOBALS['translated'] = 0;
$polish                = WPCPM_Mail_Templates::all();
$read_polish           = $GLOBALS['translated'];
unset( $GLOBALS['locale'] );
ck( 'asked again in the same language, the registry is the one already built, and nothing is translated again; asked in another, it is built again, in that one', array( $read_again, $read_polish > 0, array_keys( $polish ) === array_keys( $again ) ), array( 0, true, true ) );

echo "\n=== Filling a text ===\n";
```

In `bin/test-mail-templates.php`, replace:

```php
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['invite-mentor']['body']    = "Welcome, mentor.\n\n{set_password_link}";
ck( 'and so is one whose subject holds the set-password link, so no subject carries the key: the shipped subject is sent', array( WPCPM_Mail_Templates::state_label( WPCPM_Mail_Templates::state( 'invite-mentor' ) ), WPCPM_Mail_Templates::render( 'invite-mentor', array( 'site_name' => 'WordPress Credits', 'set_password_link' => 'LINK' ) )['subject'] ), array( 'Edit set aside: its subject uses {set_password_link}, which goes in the body.', '[WordPress Credits] Your mentor account is ready' ) );

ck( 'Restore the original drops the edit, and the email is shipped text again', array( WPCPM_Mail_Templates::restore( 'invite-mentor' ), WPCPM_Mail_Templates::edit( 'invite-mentor' ), WPCPM_Mail_Templates::state( 'invite-mentor' )['state'] ), array( true, null, 'shipped' ) );
ck( 'and there is nothing to restore a second time', WPCPM_Mail_Templates::restore( 'invite-mentor' ), false );
```

with:

```php
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['invite-mentor']['body']    = "Welcome, mentor.\n\n{set_password_link}";
ck( 'and so is one whose subject holds the set-password link, so no subject carries the key: the shipped subject is sent', array( WPCPM_Mail_Templates::state_label( WPCPM_Mail_Templates::state( 'invite-mentor' ) ), WPCPM_Mail_Templates::render( 'invite-mentor', array( 'site_name' => 'WordPress Credits', 'set_password_link' => 'LINK' ) )['subject'] ), array( 'Edit set aside: its subject uses {set_password_link}, which goes in the body.', '[WordPress Credits] Your mentor account is ready' ) );

// An edit whose every word is an optional placeholder can fill to nothing, or to a value the mail
// layer's cleaner removes whole, such as a tag: the mail layer sends no email without a subject, so
// the shipped subject is sent, filled, and the edit's body.
$late = array( 'site_name' => 'WordPress Credits', 'call_date' => 'July 15, 2036', 'when' => 'July 15, 2036 9:00 am to 9:30 am' );
ck( 'an edit whose subject is optional placeholders alone saves', WPCPM_Mail_Templates::save( 'call-cancelled-student', '{canceled_by}', 'The call on {when} is off.', 7 ), true );
ck(
	'and while they fill to nothing, the email goes out with the shipped subject and the edit\'s body, and so when they fill to a tag the mail layer would remove; with a value, with the edit\'s subject',
	array( WPCPM_Mail_Templates::render( 'call-cancelled-student', $late ), WPCPM_Mail_Templates::render( 'call-cancelled-student', $late + array( 'canceled_by' => 'Ben Lindqvist' ) )['subject'], WPCPM_Mail_Templates::render( 'call-cancelled-student', $late + array( 'canceled_by' => '<b></b>' ) )['subject'] ),
	array( array( 'subject' => '[WordPress Credits] Call on July 15, 2036 canceled', 'body' => 'The call on July 15, 2036 9:00 am to 9:30 am is off.' ), 'Ben Lindqvist', '[WordPress Credits] Call on July 15, 2036 canceled' )
);
WPCPM_Mail_Templates::restore( 'call-cancelled-student' );

ck( 'Restore the original drops the edit, and the email is shipped text again', array( WPCPM_Mail_Templates::restore( 'invite-mentor' ), WPCPM_Mail_Templates::edit( 'invite-mentor' ), WPCPM_Mail_Templates::state( 'invite-mentor' )['state'] ), array( true, null, 'shipped' ) );
ck( 'and there is nothing to restore a second time', WPCPM_Mail_Templates::restore( 'invite-mentor' ), false );
```

- [ ] **Step 3: The Templates tab's suite**

The tab lists three areas, each in a table of its own (the E2 trial's Minor: line 279 read exactly one table), the invitations' five rows as before and the other twenty-two after them:

In `bin/test-emails-templates.php`, replace:

```php
 *
 * - **The tabs**: the screen's own address opens Templates, a tab it does not have does too, and
 *   the bar is WordPress's own, Templates, Signature and footer, Log, in that order.
 * - **The Templates tab** lists the templates by area, each with who receives it, what sends it,
 *   where it stands and who edited it last, and an Edit link to its editor; the Invitations alone,
 *   and nothing about what is not there.
 * - **The editor**: the boxes hold the shipped text, then the edit read back as it was typed; the
 *   placeholders say what they mean, the required one marked; the preview is filled with the
 *   examples and ends with the footer; Restore asks first, and is there only for an edit.
```

with:

```php
 *
 * - **The tabs**: the screen's own address opens Templates, a tab it does not have does too, and
 *   the bar is WordPress's own, Templates, Signature and footer, Log, in that order.
 * - **The Templates tab** lists the templates by area, in the catalog's order, each area in a table
 *   of its own, each row with who receives it, what sends it, where it stands and who edited it last,
 *   and an Edit link to its editor: the Invitations, the Mentor calls and the Semester reports, and
 *   nothing about the areas whose emails are not editable yet.
 * - **The editor**: the boxes hold the shipped text, then the edit read back as it was typed; the
 *   placeholders say what they mean, the required one marked; the preview is filled with the
 *   examples and ends with the footer; Restore asks first, and is there only for an edit.
```

In `bin/test-emails-templates.php`, replace:

```php

preg_match_all( '#<h2>([^<]*)</h2>#', $home, $areas );
preg_match_all( '#<tr><td>([^<]*)</td><td>([^<]*)</td><td>([^<]*)</td><td><span class="wpcpm-emails__state wpcpm-emails__state--([a-z-]+)">([^<]*)</span></td><td>([^<]*)</td><td><a href="([^"]*)">Edit<span class="screen-reader-text"> ([^<]*)</span></a></td></tr>#', $home, $rows, PREG_SET_ORDER );
ck( 'the templates are listed by area: the Invitations, and nothing about any other', $areas[1], array( 'Invitations' ) );
ck(
	'each row names the email, who receives it, what sends it, its state and who edited it last, and links its editor by its name',
	array_map( function ( $row ) { return array( html_entity_decode( $row[1], ENT_QUOTES ), $row[2], $row[5], $row[6], $row[7], $row[8] ); }, $rows ),
	array(
		array( 'Invitation: student', 'Student', 'Shipped text', 'Not edited', $editor_url, 'Invitation: student' ),
		array( 'Invitation: mentor', 'Mentor', 'Shipped text', 'Not edited', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=invite-mentor', 'Invitation: mentor' ),
```

with:

```php

preg_match_all( '#<h2>([^<]*)</h2>#', $home, $areas );
preg_match_all( '#<tr><td>([^<]*)</td><td>([^<]*)</td><td>([^<]*)</td><td><span class="wpcpm-emails__state wpcpm-emails__state--([a-z-]+)">([^<]*)</span></td><td>([^<]*)</td><td><a href="([^"]*)">Edit<span class="screen-reader-text"> ([^<]*)</span></a></td></tr>#', $home, $rows, PREG_SET_ORDER );
ck( 'the templates are listed by area, in the catalog\'s order: the Invitations, the Mentor calls and the Semester reports, and nothing about any other', $areas[1], array( 'Invitations', 'Mentor calls', 'Semester reports' ) );
ck(
	'each row names the email, who receives it, what sends it, its state and who edited it last, and links its editor by its name',
	array_map( function ( $row ) { return array( html_entity_decode( $row[1], ENT_QUOTES ), $row[2], $row[5], $row[6], $row[7], $row[8] ); }, array_slice( $rows, 0, 5 ) ),
	array(
		array( 'Invitation: student', 'Student', 'Shipped text', 'Not edited', $editor_url, 'Invitation: student' ),
		array( 'Invitation: mentor', 'Mentor', 'Shipped text', 'Not edited', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=invite-mentor', 'Invitation: mentor' ),
```

In `bin/test-emails-templates.php`, replace:

```php
		array( 'Invitation: sponsor', 'Sponsor member', 'Shipped text', 'Not edited', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=invite-sponsor', 'Invitation: sponsor' ),
	)
);
ck( 'and what sends each is the registry\'s sentence', html_entity_decode( $rows[0][3] ?? '', ENT_QUOTES ), WPCPM_Mail_Templates::get( 'invite-student' )['trigger'] );
$templates_css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/emails.css' );
preg_match( '#\.wpcpm-emails__scroll\s*\{([^{}]*)\}#', $templates_css, $scroll_rule );
ck( 'each area\'s table is printed inside a box that scrolls sideways on a narrow screen, held by a position, so the page does not', array( substr_count( $home, '<table' ), preg_match_all( '#<h2>[^<]*</h2><div class="wpcpm-emails__scroll"><table class="widefat striped wpcpm-emails__templates">.*?</table></div>#s', $home ), 1 === preg_match( '/(^|;)\s*overflow-x\s*:\s*auto\s*;/', $scroll_rule[1] ?? '' ), 1 === preg_match( '/(^|;)\s*position\s*:\s*relative\s*;/', $scroll_rule[1] ?? '' ) ), array( 1, 1, true, true ) );
ck( 'no word on the tab says module, and no em or en dash', array( preg_match_all( '/\bmodules?\b/i', words_of( $home ) ), preg_match( '/\x{2013}|\x{2014}/u', $home ) ), array( 0, 0 ) );
ck( 'an Edit link naming an email the registry does not hold opens the list', false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'call-booked' ) ), '<h2>Invitations</h2>' ), true );
```

with:

```php
		array( 'Invitation: sponsor', 'Sponsor member', 'Shipped text', 'Not edited', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=invite-sponsor', 'Invitation: sponsor' ),
	)
);
ck(
	'and after the invitations, every email of the Mentor calls and the Semester reports, each with who receives it, as shipped text, its editor linked by its id',
	array_map( function ( $row ) { return html_entity_decode( $row[1], ENT_QUOTES ) . ' / ' . $row[2] . ' / ' . $row[5] . ' / ' . substr( $row[7], strlen( 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=' ) ); }, array_slice( $rows, 5 ) ),
	array(
		'Call booked, to the mentor / Mentor / Shipped text / call-booked-mentor',
		'Call booked, to a mentor with no meeting link / Mentor / Shipped text / call-booked-mentor-no-link',
		'Call booked, to the student / Student / Shipped text / call-booked-student',
		'Group session joined, to the mentor / Mentor / Shipped text / session-joined-mentor',
		'Group session joined, to a mentor with no meeting link / Mentor / Shipped text / session-joined-mentor-no-link',
		'Group session joined, to the student / Student / Shipped text / session-joined-student',
		'Session series joined, to the mentor / Mentor / Shipped text / series-joined-mentor',
		'Session series joined, to a mentor with no meeting link / Mentor / Shipped text / series-joined-mentor-no-link',
		'Session series joined, to the student / Student / Shipped text / series-joined-student',
		'Group session moved, to the student / Student / Shipped text / session-moved-student',
		'Group session moved, to the mentor / Mentor / Shipped text / session-moved-mentor',
		'Left a group session, to the student / Student / Shipped text / session-left',
		'Place on a group session released, to the student / Student / Shipped text / session-released',
		'Call canceled, to the mentor / Mentor / Shipped text / call-cancelled-mentor',
		'Call canceled, to the student / Student / Shipped text / call-cancelled-student',
		'Group session canceled / The people it concerns / Shipped text / session-cancelled',
		'Call reminder, to the mentor / Mentor / Shipped text / call-reminder-mentor',
		'Call reminder, to the student / Student / Shipped text / call-reminder-student',
		'Group session reminder / The people it concerns / Shipped text / session-reminder',
		'Semester report drafted / Administrator / Shipped text / report-drafted',
		'Semester report approved / Institution member / Shipped text / report-approved',
		'Consent for the semester report / Student / Shipped text / report-consent',
	)
);
ck( 'and what sends each is the registry\'s sentence', array( html_entity_decode( $rows[0][3] ?? '', ENT_QUOTES ), html_entity_decode( $rows[5][3] ?? '', ENT_QUOTES ), html_entity_decode( $rows[26][3] ?? '', ENT_QUOTES ) ), array( WPCPM_Mail_Templates::get( 'invite-student' )['trigger'], WPCPM_Mail_Templates::get( 'call-booked-mentor' )['trigger'], WPCPM_Mail_Templates::get( 'report-consent' )['trigger'] ) );
$templates_css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/emails.css' );
preg_match( '#\.wpcpm-emails__scroll\s*\{([^{}]*)\}#', $templates_css, $scroll_rule );
ck( 'each of the three areas has a table of its own, printed inside a box that scrolls sideways on a narrow screen, held by a position, so the page does not', array( substr_count( $home, '<table' ), preg_match_all( '#<h2>[^<]*</h2><div class="wpcpm-emails__scroll"><table class="widefat striped wpcpm-emails__templates">.*?</table></div>#s', $home ), 1 === preg_match( '/(^|;)\s*overflow-x\s*:\s*auto\s*;/', $scroll_rule[1] ?? '' ), 1 === preg_match( '/(^|;)\s*position\s*:\s*relative\s*;/', $scroll_rule[1] ?? '' ) ), array( 3, 3, true, true ) );
ck( 'no word on the tab says module, and no em or en dash', array( preg_match_all( '/\bmodules?\b/i', words_of( $home ) ), preg_match( '/\x{2013}|\x{2014}/u', $home ) ), array( 0, 0 ) );
ck( 'an Edit link naming an email the registry does not hold opens the list', false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'call-booked' ) ), '<h2>Invitations</h2>' ), true );
```

- [ ] **Step 4: Run them to verify they fail**

Run, one per command: `php bin/test-mail-catalog.php`, `php bin/test-mail-templates.php`, `php bin/test-emails-templates.php`
Expected: `3 FAILURE(S) (23 checks)` (the count, the label of the old id read back, and the Mentor calls' names); `10 FAILURE(S) (81 checks)`, with PHP warnings where the examples' checks read values the registry does not hold yet (the registry, every template whole, the five example checks, the language check, and the two of an empty subject); `4 FAILURE(S) (61 checks)` (the areas, the twenty-two rows, what sends them, the three tables).

- [ ] **Step 5: The catalog's nineteen ids**

Each id named for whom the email goes to, the five of before kept beside them for the Log, and the catalog's docblock's example of an empty audience made one that is still true:

In `includes/mail/class-wpcpm-mail-catalog.php`, replace:

```php
 *
 * `audience` is who an email is for when the account cannot say: an applicant or an invitee has no
 * account, and a person with two roles is shown as the one this email is for. Empty means the email
 * goes to people of several kinds (a call booked mails the mentor and the student) and the account
 * decides.
 */
final class WPCPM_Mail_Catalog {
```

with:

```php
 *
 * `audience` is who an email is for when the account cannot say: an applicant or an invitee has no
 * account, and a person with two roles is shown as the one this email is for. Empty means the email
 * goes to people of several kinds in the same words (a group session canceled mails the mentor and
 * every student on it) and the account decides.
 */
final class WPCPM_Mail_Catalog {
```

In `includes/mail/class-wpcpm-mail-catalog.php`, replace:

```php
			'test-mentor'                        => array( __( 'Sample invitation: mentor', 'wpcredits-program-manager' ), $i, self::TYPE_ADMIN, true ),
			'test-institution'                   => array( __( 'Sample invitation: institution', 'wpcredits-program-manager' ), $i, self::TYPE_ADMIN, true ),
			'test-sponsor'                       => array( __( 'Sample invitation: sponsor', 'wpcredits-program-manager' ), $i, self::TYPE_ADMIN, true ),
			// Mentor calls: to the mentor and the students, so the account decides.
			'call-booked'                        => array( __( 'Call booked', 'wpcredits-program-manager' ), $c, '' ),
			'series-joined'                      => array( __( 'Joined a session series', 'wpcredits-program-manager' ), $c, '' ),
			'session-moved'                      => array( __( 'Group session moved', 'wpcredits-program-manager' ), $c, '' ),
			'call-cancelled'                     => array( __( 'Call canceled', 'wpcredits-program-manager' ), $c, '' ),
			'call-reminder'                      => array( __( 'Call reminder', 'wpcredits-program-manager' ), $c, '' ),
			// Institutions.
			'institution-applied'                => array( __( 'Application received, to the applicant', 'wpcredits-program-manager' ), $n, self::TYPE_APPLICANT ),
			'institution-application'            => array( __( 'New institution application, to Administrators', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
```

with:

```php
			'test-mentor'                        => array( __( 'Sample invitation: mentor', 'wpcredits-program-manager' ), $i, self::TYPE_ADMIN, true ),
			'test-institution'                   => array( __( 'Sample invitation: institution', 'wpcredits-program-manager' ), $i, self::TYPE_ADMIN, true ),
			'test-sponsor'                       => array( __( 'Sample invitation: sponsor', 'wpcredits-program-manager' ), $i, self::TYPE_ADMIN, true ),
			// Mentor calls. Until 1.122.21 these five named every email of the area, to the mentor and to
			// the students alike, about a call and a session alike; they stay so the Log names the rows
			// they left, which it keeps for 30 days, and nothing sends them now. Their labels say so,
			// since the Log's Email field lists them beside the new names.
			'call-booked'                        => array( __( 'Call booked (until 1.122.21)', 'wpcredits-program-manager' ), $c, '' ),
			'series-joined'                      => array( __( 'Joined a session series (until 1.122.21)', 'wpcredits-program-manager' ), $c, '' ),
			'session-moved'                      => array( __( 'Group session moved (until 1.122.21)', 'wpcredits-program-manager' ), $c, '' ),
			'call-cancelled'                     => array( __( 'Call canceled (until 1.122.21)', 'wpcredits-program-manager' ), $c, '' ),
			'call-reminder'                      => array( __( 'Call reminder (until 1.122.21)', 'wpcredits-program-manager' ), $c, '' ),
			// Since 1.122.21, one email each, as the template registry holds them.
			'call-booked-mentor'                 => array( __( 'Call booked, to the mentor', 'wpcredits-program-manager' ), $c, self::TYPE_MENTOR ),
			'call-booked-mentor-no-link'         => array( __( 'Call booked, to a mentor with no meeting link', 'wpcredits-program-manager' ), $c, self::TYPE_MENTOR ),
			'call-booked-student'                => array( __( 'Call booked, to the student', 'wpcredits-program-manager' ), $c, self::TYPE_STUDENT ),
			'session-joined-mentor'              => array( __( 'Group session joined, to the mentor', 'wpcredits-program-manager' ), $c, self::TYPE_MENTOR ),
			'session-joined-mentor-no-link'      => array( __( 'Group session joined, to a mentor with no meeting link', 'wpcredits-program-manager' ), $c, self::TYPE_MENTOR ),
			'session-joined-student'             => array( __( 'Group session joined, to the student', 'wpcredits-program-manager' ), $c, self::TYPE_STUDENT ),
			'series-joined-mentor'               => array( __( 'Session series joined, to the mentor', 'wpcredits-program-manager' ), $c, self::TYPE_MENTOR ),
			'series-joined-mentor-no-link'       => array( __( 'Session series joined, to a mentor with no meeting link', 'wpcredits-program-manager' ), $c, self::TYPE_MENTOR ),
			'series-joined-student'              => array( __( 'Session series joined, to the student', 'wpcredits-program-manager' ), $c, self::TYPE_STUDENT ),
			'session-moved-student'              => array( __( 'Group session moved, to the student', 'wpcredits-program-manager' ), $c, self::TYPE_STUDENT ),
			'session-moved-mentor'               => array( __( 'Group session moved, to the mentor', 'wpcredits-program-manager' ), $c, self::TYPE_MENTOR ),
			'session-left'                       => array( __( 'Left a group session, to the student', 'wpcredits-program-manager' ), $c, self::TYPE_STUDENT ),
			'session-released'                   => array( __( 'Place on a group session released, to the student', 'wpcredits-program-manager' ), $c, self::TYPE_STUDENT ),
			'call-cancelled-mentor'              => array( __( 'Call canceled, to the mentor', 'wpcredits-program-manager' ), $c, self::TYPE_MENTOR ),
			'call-cancelled-student'             => array( __( 'Call canceled, to the student', 'wpcredits-program-manager' ), $c, self::TYPE_STUDENT ),
			'session-cancelled'                  => array( __( 'Group session canceled', 'wpcredits-program-manager' ), $c, '' ),
			'call-reminder-mentor'               => array( __( 'Call reminder, to the mentor', 'wpcredits-program-manager' ), $c, self::TYPE_MENTOR ),
			'call-reminder-student'              => array( __( 'Call reminder, to the student', 'wpcredits-program-manager' ), $c, self::TYPE_STUDENT ),
			'session-reminder'                   => array( __( 'Group session reminder', 'wpcredits-program-manager' ), $c, '' ),
			// Institutions.
			'institution-applied'                => array( __( 'Application received, to the applicant', 'wpcredits-program-manager' ), $n, self::TYPE_APPLICANT ),
			'institution-application'            => array( __( 'New institution application, to Administrators', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
```

- [ ] **Step 6: The Mentor calls' shipped text**

Every sentence is the translatable string `WPCPM_Mentor_Calls` wrote, with its translators comment, and a placeholder where its value went; the labels above a line the registry can leave out carry a comment telling a translator to keep the colon, as the invitations' do (ruling PR-5 of 1.122.19). The examples are worked out when a preview or a test asks for them, on the clock of the Administrator asking (`$wpcpm_example`). The two moved emails keep the line ending they were written with, `"\r\n"`, and end with a line break, as the code wrote them; the others end without one. The meeting link, `{meeting_place}`, and what a student or a mentor typed, `{topic}` and `{session_topic}`, are the placeholders that go in the body alone.

Create `includes/mail/templates/calls.php`:

```php
<?php
/**
 * The Mentor calls area's emails, as the template registry ships them.
 *
 * Read by `WPCPM_Mail_Templates::all()`, which adds each email's name, area and audience from the
 * catalog. Every sentence is the one `WPCPM_Mentor_Calls` wrote before 1.122.21, under the same
 * translatable string, but the counted ones: a subject or a first line that said "3 sessions" with
 * `_n()` around the whole sentence now takes the counted words as one value (`{session_count}`,
 * `{group_session_count}`, `{date_count}`), worked out with `_n()` where the email is sent, so the
 * words around them can be edited.
 *
 * One catalog id sent each of these until 1.122.21 to the mentor and to the students alike, about
 * a one-to-one call and a group session alike; each email that says different words is a template
 * of its own now. A mentor who has not set a meeting link is told so where the others are told
 * where to meet, so the mentor's three confirmations each have a second template for that case.
 *
 * Every time is the recipient's, on their own clock, as the code always wrote it: `{when}` and the
 * other times are worked out where the email is sent, and `{time_zone}` names that clock.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// An example call for a preview and a test: tomorrow at 3 pm, half an hour long, on the clock of the
// Administrator looking at it, so the times read as a real email to them would.
$wpcpm_example = static function ( $days = 0 ) {
	$zone  = WPCPM_Mentor_Availability::viewer_timezone( get_current_user_id() );
	$start = ( new DateTimeImmutable( 'tomorrow 15:00', $zone ) )->getTimestamp() + (int) $days * DAY_IN_SECONDS;

	return array(
		'start' => $start,
		'end'   => $start + 30 * MINUTE_IN_SECONDS,
		'zone'  => $zone,
	);
};

// Every placeholder an email of the area may have, for an email to one audience: what each means and
// its example, a value or a function that works one out when a preview or a test asks for it. An
// email takes the ones it uses (`$wpcpm_take`).
$wpcpm_placeholders = static function ( $audience ) use ( $wpcpm_example ) {
	return array(
		'site_name'           => array(
			'means'   => __( 'The site\'s name.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return WPCPM_Mail::site_name();
			},
		),
		'student_name'        => array(
			'means'   => __( 'The student\'s name; "your student" when the call holds none.', 'wpcredits-program-manager' ),
			'example' => 'Ada Okonjo',
		),
		'mentor_name'         => array(
			'means'   => __( 'The mentor\'s name.', 'wpcredits-program-manager' ),
			'example' => 'Ben Lindqvist',
		),
		'with_whom'           => array(
			'means'   => __( 'Who the session is with: the mentor\'s name, or, in the mentor\'s copy, how many students are coming, such as "3 students".', 'wpcredits-program-manager' ),
			'example' => 'Ben Lindqvist',
		),
		'when'                => array(
			'means'   => __( 'When the call is, from its start to its end, on the recipient\'s own clock. Every email about a call needs it.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $wpcpm_example ) {
				$call = $wpcpm_example();

				return WPCPM_Mentor_Calls::format_range( $call['start'], $call['end'], $call['zone'] );
			},
		),
		'was_when'            => array(
			'means'   => __( 'When the session was before it moved, on the recipient\'s own clock.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $wpcpm_example ) {
				$call = $wpcpm_example( 1 );

				return WPCPM_Mentor_Calls::format_range( $call['start'], $call['end'], $call['zone'] );
			},
		),
		'call_date'           => array(
			'means'   => __( 'The day of the call, on the recipient\'s own clock.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $wpcpm_example ) {
				$call = $wpcpm_example();

				return wp_date( get_option( 'date_format' ), $call['start'], $call['zone'] );
			},
		),
		'session_dates'       => array(
			'means'   => __( 'When each session is, one to a line, on the recipient\'s own clock. The email needs them.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $wpcpm_example ) {
				$dates = array();

				foreach ( array( 0, 7, 14 ) as $days ) {
					$call    = $wpcpm_example( $days );
					$dates[] = WPCPM_Mentor_Calls::format_range( $call['start'], $call['end'], $call['zone'] );
				}

				return implode( "\n", $dates );
			},
		),
		'session_count'       => array(
			'means'   => __( 'How many sessions the student joined, counted: "3 sessions".', 'wpcredits-program-manager' ),
			'example' => static function () {
				return sprintf(
					/* translators: %d: how many sessions. */
					_n( '%d session', '%d sessions', 3, 'wpcredits-program-manager' ),
					3
				);
			},
		),
		'group_session_count' => array(
			'means'   => __( 'How many group sessions the student joined, counted: "3 group sessions".', 'wpcredits-program-manager' ),
			'example' => static function () {
				return sprintf(
					/* translators: %d: how many sessions. */
					_n( '%d group session', '%d group sessions', 3, 'wpcredits-program-manager' ),
					3
				);
			},
		),
		'date_count'          => array(
			'means'   => __( 'How many dates the student joined, counted: "3 dates".', 'wpcredits-program-manager' ),
			'example' => static function () {
				return sprintf(
					/* translators: %d: how many dates. */
					_n( '%d date', '%d dates', 3, 'wpcredits-program-manager' ),
					3
				);
			},
		),
		'in_how_long'         => array(
			'means'   => __( 'How long until the call starts, such as "in 23 hours".', 'wpcredits-program-manager' ),
			'example' => static function () use ( $wpcpm_example ) {
				$call = $wpcpm_example();

				return WPCPM_Mentor_Calls::relative( $call['start'] );
			},
		),
		'time_zone'           => array(
			'means'   => __( 'The recipient\'s time zone, which every time in the email is shown in.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $wpcpm_example ) {
				$call = $wpcpm_example();

				return WPCPM_Mentor_Availability::zone_label( $call['zone']->getName() );
			},
		),
		'meeting_place'       => array(
			'means'          => __( 'The meeting link the mentor set beside their availability. Empty, while the mentor has set none, leaves its lines out. It can carry the meeting\'s passcode, so it goes in the body, never in the subject.', 'wpcredits-program-manager' ),
			// Where an email is sent only while the mentor has a link, and so needs it.
			'means_required' => __( 'The meeting link the mentor set beside their availability, which this email is sent only with. It can carry the meeting\'s passcode, so it goes in the body, never in the subject.', 'wpcredits-program-manager' ),
			'example'        => 'https://meet.example.test/ben-lindqvist',
			// In the body alone: a meeting link can carry the meeting's passcode, and the subject is the
			// one part of an email the mail log keeps, for 30 days.
			'body_only'      => true,
		),
		'topic'               => array(
			'means'     => __( 'What the student said they would like to talk about, as they typed it. Empty leaves its lines out. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'My first plugin, and how to get it reviewed',
			'body_only' => true,
		),
		'session_topic'       => array(
			'means'     => __( 'What the session is about, as the mentor typed it. Empty leaves its lines out. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'The release cycle, and how to follow it',
			'body_only' => true,
		),
		'canceled_by'         => array(
			'means'   => __( 'Who canceled: their name, or "an Administrator" when an Administrator did.', 'wpcredits-program-manager' ),
			'example' => WPCPM_Mail_Catalog::TYPE_MENTOR === $audience ? 'Ada Okonjo' : 'Ben Lindqvist',
		),
		'dashboard_link'      => array(
			'means'   => __( 'The recipient\'s dashboard, while its page is published. Empty leaves its lines out.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $audience ) {
				return WPCPM_Mail_Templates::page_of_type( '' === $audience ? WPCPM_Mail_Catalog::TYPE_STUDENT : $audience );
			},
		),
	);
};

// The placeholders one email has, in the order given, those named in `$required` required: an email
// about a call needs to say when it is, and the mentor's confirmation sent only while the mentor has a
// meeting link needs that link, which its meaning then says.
$wpcpm_take = static function ( $audience, array $names, array $required ) use ( $wpcpm_placeholders ) {
	$all = $wpcpm_placeholders( $audience );
	$out = array();

	foreach ( $names as $name ) {
		$needed       = in_array( $name, $required, true );
		$out[ $name ] = array(
			'means'     => $needed && isset( $all[ $name ]['means_required'] ) ? $all[ $name ]['means_required'] : $all[ $name ]['means'],
			'example'   => $all[ $name ]['example'],
			'required'  => $needed,
			'body_only' => ! empty( $all[ $name ]['body_only'] ),
		);
	}

	return $out;
};

// The sentences more than one email says, each under the translatable string the code used.
$wpcpm_times = sprintf(
	/* translators: %s: timezone name. */
	__( 'Times are shown in %s.', 'wpcredits-program-manager' ),
	'{time_zone}'
);

/* translators: The line above the mentor's meeting link. Keep the colon at the end; the site leaves this line out with the link under it while the mentor has set none. */
$wpcpm_where = __( 'Where you will meet:', 'wpcredits-program-manager' ) . "\n{meeting_place}";

$wpcpm_no_link = __( 'You have not set a meeting link yet, so this confirmation cannot tell your student where to go. You can add one beside your availability on the program site.', 'wpcredits-program-manager' );

/* translators: The line above what the student typed. Keep the colon at the end; the site leaves this line out with the words under it when there are none. */
$wpcpm_to_mentor_topic = __( 'What the student would like to discuss:', 'wpcredits-program-manager' ) . "\n{topic}";

/* translators: The line above what the student typed, in the student's own copy. Keep the colon at the end; the site leaves this line out with the words under it when there are none. */
$wpcpm_to_student_topic = __( 'What you said you would like to discuss:', 'wpcredits-program-manager' ) . "\n{topic}";

/* translators: The line above what the mentor typed about the session. Keep the colon at the end; the site leaves this line out with the words under it when there are none. */
$wpcpm_session_topic = __( 'What the session is about:', 'wpcredits-program-manager' ) . "\n{session_topic}";

/* translators: The line above what the mentor typed about the series. Keep the colon at the end; the site leaves this line out with the words under it when there are none. */
$wpcpm_series_topic = __( 'What the sessions are about:', 'wpcredits-program-manager' ) . "\n{session_topic}";

$wpcpm_moved_topic = sprintf(
	/* translators: %s: what the session is about. The site leaves this line out when the mentor wrote nothing. */
	__( 'What it is about: %s', 'wpcredits-program-manager' ),
	'{session_topic}'
);

// A body: its paragraphs, a blank line between each two, and no line break after the last.
$wpcpm_body = static function ( array $paragraphs ) {
	return implode( "\n\n", $paragraphs );
};

$wpcpm_mentor  = WPCPM_Mail_Catalog::TYPE_MENTOR;
$wpcpm_student = WPCPM_Mail_Catalog::TYPE_STUDENT;

// A booking or a join, as the mentor's copy says it: the opening, the time zone, where to meet (or, for
// `$no_link`, that the mentor has set no link), what the student would like to discuss or what the
// session is about, and the mentor's dashboard.
$wpcpm_booked_mentor = static function ( $opening, $topic, $no_link ) use ( $wpcpm_body, $wpcpm_times, $wpcpm_where, $wpcpm_no_link ) {
	return $wpcpm_body( array( $opening, $wpcpm_times, $no_link ? $wpcpm_no_link : $wpcpm_where, $topic, '{dashboard_link}' ) );
};

$wpcpm_booked_call_opening = sprintf(
	/* translators: 1: student name, 2: date and time. */
	__( '%1$s booked a call with you on %2$s.', 'wpcredits-program-manager' ),
	'{student_name}',
	'{when}'
);

$wpcpm_joined_session_opening = sprintf(
	/* translators: 1: student name, 2: date and time. */
	__( '%1$s joined your group session on %2$s.', 'wpcredits-program-manager' ),
	'{student_name}',
	'{when}'
);

$wpcpm_series_mentor_opening = sprintf(
	/* translators: 1: student name, 2: how many group sessions, counted, such as "3 group sessions". */
	__( '%1$s joined %2$s of yours:', 'wpcredits-program-manager' ),
	'{student_name}',
	'{group_session_count}'
) . "\n{session_dates}";

$wpcpm_series_closing = __( 'The attached calendar file adds all of them to your calendar at once.', 'wpcredits-program-manager' );

return array(
	'call-booked-mentor'            => array(
		'trigger'      => __( 'When a student books a one-to-one call with a mentor who has set a meeting link.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: student name. */
			__( '[%1$s] Call booked with %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{student_name}'
		),
		'body'         => $wpcpm_booked_mentor( $wpcpm_booked_call_opening, $wpcpm_to_mentor_topic, false ),
		'placeholders' => $wpcpm_take( $wpcpm_mentor, array( 'site_name', 'student_name', 'when', 'time_zone', 'meeting_place', 'topic', 'dashboard_link' ), array( 'when', 'meeting_place' ) ),
	),
	'call-booked-mentor-no-link'    => array(
		'trigger'      => __( 'When a student books a one-to-one call with a mentor who has not set a meeting link yet.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: student name. */
			__( '[%1$s] Call booked with %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{student_name}'
		),
		'body'         => $wpcpm_booked_mentor( $wpcpm_booked_call_opening, $wpcpm_to_mentor_topic, true ),
		'placeholders' => $wpcpm_take( $wpcpm_mentor, array( 'site_name', 'student_name', 'when', 'time_zone', 'topic', 'dashboard_link' ), array( 'when' ) ),
	),
	'call-booked-student'           => array(
		'trigger'      => __( 'When the student books a one-to-one call with their mentor.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: mentor name. */
			__( '[%1$s] Your call with %2$s is booked', 'wpcredits-program-manager' ),
			'{site_name}',
			'{mentor_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: mentor name, 2: date and time. */
					__( 'Your call with %1$s is booked for %2$s.', 'wpcredits-program-manager' ),
					'{mentor_name}',
					'{when}'
				),
				$wpcpm_times,
				$wpcpm_where,
				$wpcpm_to_student_topic,
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_student, array( 'site_name', 'mentor_name', 'when', 'time_zone', 'meeting_place', 'topic', 'dashboard_link' ), array( 'when' ) ),
	),
	'session-joined-mentor'         => array(
		'trigger'      => __( 'When a student joins a group session of a mentor who has set a meeting link.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: student name. */
			__( '[%1$s] %2$s joined your group session', 'wpcredits-program-manager' ),
			'{site_name}',
			'{student_name}'
		),
		'body'         => $wpcpm_booked_mentor( $wpcpm_joined_session_opening, $wpcpm_session_topic, false ),
		'placeholders' => $wpcpm_take( $wpcpm_mentor, array( 'site_name', 'student_name', 'when', 'time_zone', 'meeting_place', 'session_topic', 'dashboard_link' ), array( 'when', 'meeting_place' ) ),
	),
	'session-joined-mentor-no-link' => array(
		'trigger'      => __( 'When a student joins a group session of a mentor who has not set a meeting link yet.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: student name. */
			__( '[%1$s] %2$s joined your group session', 'wpcredits-program-manager' ),
			'{site_name}',
			'{student_name}'
		),
		'body'         => $wpcpm_booked_mentor( $wpcpm_joined_session_opening, $wpcpm_session_topic, true ),
		'placeholders' => $wpcpm_take( $wpcpm_mentor, array( 'site_name', 'student_name', 'when', 'time_zone', 'session_topic', 'dashboard_link' ), array( 'when' ) ),
	),
	'session-joined-student'        => array(
		'trigger'      => __( 'When the student joins a group session of their mentor\'s.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: mentor name. */
			__( '[%1$s] You are on the group session with %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{mentor_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: mentor name, 2: date and time. */
					__( 'You are on the group session with %1$s on %2$s.', 'wpcredits-program-manager' ),
					'{mentor_name}',
					'{when}'
				),
				$wpcpm_times,
				$wpcpm_where,
				$wpcpm_session_topic,
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_student, array( 'site_name', 'mentor_name', 'when', 'time_zone', 'meeting_place', 'session_topic', 'dashboard_link' ), array( 'when' ) ),
	),
	'series-joined-mentor'          => array(
		'trigger'      => __( 'When a student joins the sessions of a series at once, with Join all, and the mentor has set a meeting link.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: student name, 3: how many sessions, counted, such as "3 sessions". */
			__( '[%1$s] %2$s joined your series: %3$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{student_name}',
			'{session_count}'
		),
		'body'         => $wpcpm_body( array( $wpcpm_series_mentor_opening, $wpcpm_times, $wpcpm_where, $wpcpm_series_topic, $wpcpm_series_closing, '{dashboard_link}' ) ),
		'placeholders' => $wpcpm_take( $wpcpm_mentor, array( 'site_name', 'student_name', 'session_count', 'group_session_count', 'session_dates', 'time_zone', 'meeting_place', 'session_topic', 'dashboard_link' ), array( 'session_dates', 'meeting_place' ) ),
	),
	'series-joined-mentor-no-link'  => array(
		'trigger'      => __( 'When a student joins the sessions of a series at once, with Join all, and the mentor has not set a meeting link yet.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: student name, 3: how many sessions, counted, such as "3 sessions". */
			__( '[%1$s] %2$s joined your series: %3$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{student_name}',
			'{session_count}'
		),
		'body'         => $wpcpm_body( array( $wpcpm_series_mentor_opening, $wpcpm_times, $wpcpm_no_link, $wpcpm_series_topic, $wpcpm_series_closing, '{dashboard_link}' ) ),
		'placeholders' => $wpcpm_take( $wpcpm_mentor, array( 'site_name', 'student_name', 'session_count', 'group_session_count', 'session_dates', 'time_zone', 'session_topic', 'dashboard_link' ), array( 'session_dates' ) ),
	),
	'series-joined-student'         => array(
		'trigger'      => __( 'When the student joins the sessions of a series at once, with Join all.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: mentor name, 3: how many dates, counted, such as "3 dates". */
			__( '[%1$s] Group sessions with %2$s: %3$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{mentor_name}',
			'{date_count}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: mentor name, 2: how many group sessions, counted, such as "3 group sessions". */
					__( 'You are on %2$s with %1$s:', 'wpcredits-program-manager' ),
					'{mentor_name}',
					'{group_session_count}'
				) . "\n{session_dates}",
				$wpcpm_times,
				$wpcpm_where,
				$wpcpm_series_topic,
				$wpcpm_series_closing,
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_student, array( 'site_name', 'mentor_name', 'date_count', 'group_session_count', 'session_dates', 'time_zone', 'meeting_place', 'session_topic', 'dashboard_link' ), array( 'session_dates' ) ),
	),
	'session-moved-student'         => array(
		'trigger'      => __( 'When a group session the student is on moves to another time.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: mentor name. */
			__( '[%1$s] The group session with %2$s has moved', 'wpcredits-program-manager' ),
			'{site_name}',
			'{mentor_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: the time it used to be at, 2: the time it is at now. Two sentences: the site reads a full stop followed by a space as the end of a sentence. */
					__( 'The group session that was on %1$s has moved. It is now on %2$s.', 'wpcredits-program-manager' ),
					'{was_when}',
					'{when}'
				),
				$wpcpm_times,
				__( 'Your place is kept, and the invitation attached replaces the one already in your calendar. If the new time does not work for you, you can leave the session from your Student Report Card.', 'wpcredits-program-manager' ),
				$wpcpm_moved_topic,
				/* translators: The line above the address of the person's dashboard. Keep the colon at the end; the site leaves this line out with the address under it. */
				__( 'Your Student Report Card:', 'wpcredits-program-manager' ) . "\n{dashboard_link}",
			)
		) . "\n",
		// The two moved emails were written with the line ending WordPress gives its own email.
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( $wpcpm_student, array( 'site_name', 'mentor_name', 'was_when', 'when', 'time_zone', 'session_topic', 'dashboard_link' ), array( 'when' ) ),
	),
	'session-moved-mentor'          => array(
		'trigger'      => __( 'When an Administrator moves a group session of the mentor\'s to another time.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your group session has moved', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: the time it used to be at, 2: the time it is at now. Two sentences: the site reads a full stop followed by a space as the end of a sentence. */
					__( 'An Administrator moved your group session that was on %1$s. It is now on %2$s.', 'wpcredits-program-manager' ),
					'{was_when}',
					'{when}'
				),
				$wpcpm_times,
				__( 'Everybody on the session has been sent a new invitation, and the one attached replaces the one already in your calendar.', 'wpcredits-program-manager' ),
				$wpcpm_moved_topic,
				'{dashboard_link}',
			)
		) . "\n",
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( $wpcpm_mentor, array( 'site_name', 'was_when', 'when', 'time_zone', 'session_topic', 'dashboard_link' ), array( 'when' ) ),
	),
	'session-left'                  => array(
		'trigger'      => __( 'When the student leaves a group session.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: mentor name. */
			__( '[%1$s] You left the group session with %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{mentor_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: mentor name, 2: date and time. */
					__( 'You have left the group session with %1$s on %2$s.', 'wpcredits-program-manager' ),
					'{mentor_name}',
					'{when}'
				),
				$wpcpm_times,
				$wpcpm_session_topic,
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_student, array( 'site_name', 'mentor_name', 'when', 'time_zone', 'session_topic', 'dashboard_link' ), array( 'when' ) ),
	),
	'session-released'              => array(
		'trigger'      => __( 'When the students sync takes the student off a former mentor\'s group session, after the student was given a new mentor.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: the former mentor's name. */
			__( '[%1$s] Your place on the group session with %2$s is released', 'wpcredits-program-manager' ),
			'{site_name}',
			'{mentor_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: the former mentor's name, 2: date and time. */
					__( 'You have a new mentor, so your place on the group session with %1$s on %2$s is released.', 'wpcredits-program-manager' ),
					'{mentor_name}',
					'{when}'
				),
				$wpcpm_times,
				$wpcpm_session_topic,
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_student, array( 'site_name', 'mentor_name', 'when', 'time_zone', 'session_topic', 'dashboard_link' ), array( 'when' ) ),
	),
	'call-cancelled-mentor'         => array(
		'trigger'      => __( 'When the student or an Administrator cancels a one-to-one call.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: date of the canceled call. */
			__( '[%1$s] Call on %2$s canceled', 'wpcredits-program-manager' ),
			'{site_name}',
			'{call_date}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: date and time of the call, 2: who canceled. */
					__( 'The call on %1$s was canceled by %2$s.', 'wpcredits-program-manager' ),
					'{when}',
					'{canceled_by}'
				),
				$wpcpm_times,
				__( 'The slot is free again, and your student can book another time whenever suits them.', 'wpcredits-program-manager' ),
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_mentor, array( 'site_name', 'call_date', 'when', 'canceled_by', 'time_zone', 'dashboard_link' ), array( 'when' ) ),
	),
	'call-cancelled-student'        => array(
		'trigger'      => __( 'When the mentor or an Administrator cancels a one-to-one call.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: date of the canceled call. */
			__( '[%1$s] Call on %2$s canceled', 'wpcredits-program-manager' ),
			'{site_name}',
			'{call_date}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: date and time of the call, 2: who canceled. */
					__( 'The call on %1$s was canceled by %2$s.', 'wpcredits-program-manager' ),
					'{when}',
					'{canceled_by}'
				),
				$wpcpm_times,
				__( 'The slot is free again, so you can book another time.', 'wpcredits-program-manager' ),
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_student, array( 'site_name', 'call_date', 'when', 'canceled_by', 'time_zone', 'dashboard_link' ), array( 'when' ) ),
	),
	'session-cancelled'             => array(
		'trigger'      => __( 'When a group session is canceled: to the mentor and to every student on it, but the person who canceled it.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: date of the canceled session. */
			__( '[%1$s] Group session on %2$s canceled', 'wpcredits-program-manager' ),
			'{site_name}',
			'{call_date}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: date and time of the session, 2: who canceled. */
					__( 'The group session on %1$s was canceled by %2$s.', 'wpcredits-program-manager' ),
					'{when}',
					'{canceled_by}'
				),
				$wpcpm_times,
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( '', array( 'site_name', 'call_date', 'when', 'canceled_by', 'time_zone', 'dashboard_link' ), array( 'when' ) ),
	),
	'call-reminder-mentor'          => array(
		'trigger'      => __( 'A day before a one-to-one call, unless it was booked within that day.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: the other person's name. */
			__( '[%1$s] Reminder: your call with %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{student_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: the other person's name, 2: date and time, 3: "in 4 hours". */
					__( 'Your call with %1$s is %3$s, on %2$s.', 'wpcredits-program-manager' ),
					'{student_name}',
					'{when}',
					'{in_how_long}'
				),
				$wpcpm_times,
				$wpcpm_where,
				$wpcpm_to_mentor_topic,
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_mentor, array( 'site_name', 'student_name', 'in_how_long', 'when', 'time_zone', 'meeting_place', 'topic', 'dashboard_link' ), array( 'when' ) ),
	),
	'call-reminder-student'         => array(
		'trigger'      => __( 'A day before a one-to-one call, unless it was booked within that day.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: the other person's name. */
			__( '[%1$s] Reminder: your call with %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{mentor_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: the other person's name, 2: date and time, 3: "in 4 hours". */
					__( 'Your call with %1$s is %3$s, on %2$s.', 'wpcredits-program-manager' ),
					'{mentor_name}',
					'{when}',
					'{in_how_long}'
				),
				$wpcpm_times,
				$wpcpm_where,
				$wpcpm_to_student_topic,
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_student, array( 'site_name', 'mentor_name', 'in_how_long', 'when', 'time_zone', 'meeting_place', 'topic', 'dashboard_link' ), array( 'when' ) ),
	),
	'session-reminder'              => array(
		'trigger'      => __( 'A day before a group session, unless it was created within that day: to the mentor and to every student on it.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: the mentor's name, or how many students are coming. */
			__( '[%1$s] Reminder: your group session with %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{with_whom}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: the mentor's name, or how many students are coming, 2: date and time, 3: "in 4 hours". */
					__( 'Your group session with %1$s is %3$s, on %2$s.', 'wpcredits-program-manager' ),
					'{with_whom}',
					'{when}',
					'{in_how_long}'
				),
				$wpcpm_times,
				$wpcpm_where,
				$wpcpm_session_topic,
				'{dashboard_link}',
			)
		),
		'placeholders' => $wpcpm_take( '', array( 'site_name', 'with_whom', 'in_how_long', 'when', 'time_zone', 'meeting_place', 'session_topic', 'dashboard_link' ), array( 'when' ) ),
	),
);
```

- [ ] **Step 7: The Semester reports' shipped text**

Create `includes/mail/templates/reports.php`:

```php
<?php
/**
 * The Semester reports area's emails, as the template registry ships them.
 *
 * Read by `WPCPM_Mail_Templates::all()`, which adds each email's name, area and audience from the
 * catalog. Every sentence is the one `WPCPM_Semester_Report_Screen` wrote before 1.122.21, under the
 * same translatable string, but one: the draft's count of students still in progress, a sentence
 * `_n()` used to word whole, is a value now (`{students_in_progress}`), the counted words and their
 * verb, so the rest of the sentence can be edited.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The semester a preview and a test name: the one running now.
$wpcpm_semester = array(
	'means'    => __( 'The semester the report covers, such as "January to June 2026". The email needs it.', 'wpcredits-program-manager' ),
	'example'  => static function () {
		return WPCPM_Cohort::label( WPCPM_Cohort::current() );
	},
	'required' => true,
);

return array(
	'report-drafted'  => array(
		'trigger'      => __( 'When the daily job drafts a semester report for an institution: to the addresses in Who reviews reports, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: institution name. */
			__( 'Semester report drafted: %s', 'wpcredits-program-manager' ),
			'{institution_name}'
		),
		'body'         => implode(
			"\n\n",
			array(
				sprintf(
					/* translators: 1: institution name, 2: a semester, e.g. "January to June 2026". Two sentences: the site reads a full stop followed by a space as the end of a sentence. */
					__( 'A semester report has been drafted for %1$s, covering %2$s. It is waiting for an Administrator to review and approve it; the institution cannot see it until then.', 'wpcredits-program-manager' ),
					'{institution_name}',
					'{semester}'
				),
				sprintf(
					/* translators: %s: how many students, counted with the verb that goes with the number, such as "3 students in this semester were". The site leaves this sentence out when there were none. */
					__( '%s still in progress when the draft was written.', 'wpcredits-program-manager' ),
					'{students_in_progress}'
				),
				/* translators: The line above the link to the draft. Keep the colon at the end; the site leaves this line out with the link under it when the Institution Dashboard page is not published. */
				__( 'Review it here:', 'wpcredits-program-manager' ) . "\n{review_link}",
			)
		) . "\n",
		'placeholders' => array(
			'institution_name'     => array(
				'means'    => __( 'The institution\'s name; its Airtable record when the index holds no name for it.', 'wpcredits-program-manager' ),
				'example'  => 'Example University',
				'required' => false,
			),
			'semester'             => $wpcpm_semester,
			'students_in_progress' => array(
				'means'    => __( 'How many of the semester\'s students were still in progress when the draft was written, counted with the verb that goes with the number: "3 students in this semester were". Empty when none were, which leaves its sentence out.', 'wpcredits-program-manager' ),
				'example'  => static function () {
					return sprintf(
						/* translators: %s: a number of students. */
						_n( '%s student in this semester was', '%s students in this semester were', 2, 'wpcredits-program-manager' ),
						number_format_i18n( 2 )
					);
				},
				'required' => false,
			),
			'review_link'          => array(
				'means'    => __( 'The draft on the Institution Dashboard, as an Administrator reviews it. Empty, while that page is not published, leaves its lines out.', 'wpcredits-program-manager' ),
				'example'  => static function () {
					return WPCPM_Semester_Report_Screen::report_url( WPCPM_Cohort::current() );
				},
				'required' => false,
			),
		),
	),
	'report-approved' => array(
		'trigger'      => __( 'When an Administrator approves an institution\'s semester report: to each of the institution\'s accounts.', 'wpcredits-program-manager' ),
		'subject'      => __( 'Your semester report is ready', 'wpcredits-program-manager' ),
		'body'         => sprintf(
			/* translators: %s: a semester, e.g. "January to June 2026". Two sentences: the site reads a full stop followed by a space as the end of a sentence. */
			__( 'The semester report on %s has been approved by the WordPress Credits program. You can read it on your Institution Dashboard and download it as a PDF.', 'wpcredits-program-manager' ),
			'{semester}'
		) . "\n\n{report_link}\n",
		'placeholders' => array(
			'semester'    => $wpcpm_semester,
			'report_link' => array(
				'means'    => __( 'The report on the Institution Dashboard. Empty, while that page is not published, leaves its line out.', 'wpcredits-program-manager' ),
				'example'  => static function () {
					return WPCPM_Semester_Report_Screen::report_url( WPCPM_Cohort::current() );
				},
				'required' => false,
			),
		),
	),
	'report-consent'  => array(
		'trigger'      => __( 'When an Administrator asks the students of a semester report whether it may mention them; those one press cannot reach are written to a few minutes later.', 'wpcredits-program-manager' ),
		'subject'      => __( 'May your institution mention you in its semester report?', 'wpcredits-program-manager' ),
		'body'         => implode(
			"\n\n",
			array(
				sprintf(
					/* translators: %s: a semester, e.g. "January to June 2026". */
					__( 'Your institution is writing its report on %s.', 'wpcredits-program-manager' ),
					'{semester}'
				),
				__( 'Two questions on your own form decide what it may say about you: whether your institution may list you in the report, and whether it may quote the feedback you wrote. Both are yours to answer, and either answer is fine. Nothing you wrote is shown to your institution until you have said it may be.', 'wpcredits-program-manager' ),
				/* translators: The line above the address of the student's dashboard. Keep the colon at the end; the site leaves this line out with the address under it. */
				__( 'Your answers live on your dashboard:', 'wpcredits-program-manager' ) . "\n{dashboard_link}",
				__( 'If you would rather not answer, you do not have to. You will not be asked about this report again.', 'wpcredits-program-manager' ),
			)
		),
		'placeholders' => array(
			'semester'       => $wpcpm_semester,
			'dashboard_link' => array(
				'means'    => __( 'The student\'s Student Report Card, where the two questions are, while its page is published. Empty leaves its lines out.', 'wpcredits-program-manager' ),
				'example'  => static function () {
					return WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_STUDENT );
				},
				'required' => false,
			),
		),
	),
);
```

- [ ] **Step 8: The registry: the two areas, built once a request for each language, and the empty subject**

The two areas join `AREAS`, in the catalog's order:

In `includes/mail/class-wpcpm-mail-templates.php`, replace:

```php
	 */
	const AREAS = array(
		WPCPM_Mail_Catalog::MODULE_INVITATIONS => 'invitations',
	);

	/** What a placeholder looks like: a word in braces. */
```

with:

```php
	 */
	const AREAS = array(
		WPCPM_Mail_Catalog::MODULE_INVITATIONS => 'invitations',
		WPCPM_Mail_Catalog::MODULE_CALLS       => 'calls',
		WPCPM_Mail_Catalog::MODULE_REPORTS     => 'reports',
	);

	/** What a placeholder looks like: a word in braces. */
```

`all()` keeps what it built under the locale it was built in:

In `includes/mail/class-wpcpm-mail-templates.php`, replace:

```php
	 * name, area and audience, then the registry's own fields. An id the catalog does not hold is left
	 * out, since it has no name to show.
	 *
	 * Built each time it is asked for, so the shipped text is in the language of the moment.
	 *
	 * @return array<string,array{id:string,label:string,module:string,audience:string,trigger:string,subject:string,body:string,eol:string,placeholders:array}>
	 */
	public static function all() {
		$out = array();

		foreach ( self::AREAS as $file ) {
```

with:

```php
	 * name, area and audience, then the registry's own fields. An id the catalog does not hold is left
	 * out, since it has no name to show.
	 *
	 * Built once a request for each language it is asked in, and kept for the rest of the request, so
	 * the shipped text is in the language of the moment: an email is built in its recipient's, and a
	 * request that sends emails in two languages builds the registry twice, once in each.
	 *
	 * @return array<string,array{id:string,label:string,module:string,audience:string,trigger:string,subject:string,body:string,eol:string,placeholders:array}>
	 */
	public static function all() {
		static $built = array();

		$locale = self::locale();

		if ( isset( $built[ $locale ] ) ) {
			return $built[ $locale ];
		}

		$out = array();

		foreach ( self::AREAS as $file ) {
```

and keeps it at the end, and the locale is `determine_locale()`'s, which WordPress's switcher filters, or `get_locale()`'s where that is missing:

In `includes/mail/class-wpcpm-mail-templates.php`, replace:

```php
			}
		}

		return $out;
	}

	/**
	 * One template.
	 *
```

with:

```php
			}
		}

		$built[ $locale ] = $out;

		return $out;
	}

	/**
	 * The language the shipped text is built in now: the one WordPress loads translations in, which a
	 * switch into a recipient's language changes.
	 *
	 * @return string A locale, such as `en_US`; '' where WordPress cannot say.
	 */
	private static function locale() {
		if ( function_exists( 'determine_locale' ) ) {
			return (string) determine_locale();
		}

		return function_exists( 'get_locale' ) ? (string) get_locale() : '';
	}

	/**
	 * One template.
	 *
```

`render()` says, and does, what an edit's empty subject is sent as:

In `includes/mail/class-wpcpm-mail-templates.php`, replace:

```php
	 * when one fits, the shipped text otherwise (`text_of()`). No footer: the signature and footer is
	 * added once, where the email leaves, never by a builder.
	 *
	 * @param string $id     The email's id.
	 * @param array  $values Each placeholder's value, by name, worked out by whatever sends it.
	 * @return array{subject:string,body:string}|null Null when the registry has no such email.
```

with:

```php
	 * when one fits, the shipped text otherwise (`text_of()`). No footer: the signature and footer is
	 * added once, where the email leaves, never by a builder.
	 *
	 * An edit whose subject fills to nothing, each of its words an optional placeholder that came out
	 * empty, or to nothing the mail layer's cleaner would keep, is sent with the shipped subject,
	 * filled: the mail layer sends no email without a subject (`WPCPM_Mail::hand_off()`), so the email
	 * would otherwise not go out at all.
	 *
	 * @param string $id     The email's id.
	 * @param array  $values Each placeholder's value, by name, worked out by whatever sends it.
	 * @return array{subject:string,body:string}|null Null when the registry has no such email.
```

In `includes/mail/class-wpcpm-mail-templates.php`, replace:

```php
			return null;
		}

		$text = self::text_of( $id );

		return array(
			'subject' => self::fill( $text['subject'], $values, $entry['placeholders'], $entry['eol'], false ),
			'body'    => self::fill( $text['body'], $values, $entry['placeholders'], $entry['eol'] ),
		);
	}
```

with:

```php
			return null;
		}

		$text    = self::text_of( $id );
		$subject = self::fill( $text['subject'], $values, $entry['placeholders'], $entry['eol'], false );

		// As the mail layer will clean it: a value the cleaner reads as a tag leaves nothing either.
		if ( '' === trim( function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $subject ) : $subject ) ) {
			$subject = self::fill( $entry['subject'], $values, $entry['placeholders'], $entry['eol'], false );
		}

		return array(
			'subject' => $subject,
			'body'    => self::fill( $text['body'], $values, $entry['placeholders'], $entry['eol'] ),
		);
	}
```

- [ ] **Step 9: Run them to verify they pass**

Run, one per command: `php bin/test-mail-catalog.php`, `php bin/test-mail-templates.php`, `php bin/test-emails-templates.php`, `php bin/test-invitation-text.php`, `php bin/test-calls-reports-text.php`
Expected: `ALL PASS (23 checks)`; `ALL PASS (81 checks)`; `ALL PASS (61 checks)`; `ALL PASS (40 checks)` (the invitations unchanged, the registry read through its memo); `ALL PASS (394 checks)` (nothing sends the new templates yet).

- [ ] **Step 10: Run the whole battery**

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 11: Commit**

```bash
git add includes/mail/ bin/test-mail-catalog.php bin/test-mail-templates.php bin/test-emails-templates.php
git commit -m "Emails E3: the Mentor calls and Semester reports in the template registry, each email under its own id"
```

---


### Task 3: The Mentor calls, built from the registry, word for word

Every sender of `WPCPM_Mentor_Calls` chooses the template its email is, works out the values inside the recipient's language and on the recipient's clock, and hands the mail layer the registry's subject and body with the Reply-To and the calendar file it always sent. Task 1's recording holds every one of the area's 59 recorded emails to its bytes, under its template's own id; the catalog's suite pins that nothing sends the five ids of before; and two checks the recording cannot hold pin what the move changes on purpose: a mentor who is also a student is sent the mentor's booking as a mentor, and a mentor who reads Polish is sent the session's reminder in Polish, its count of students too.

**Files:**
- Modify: `includes/modules/class-wpcpm-mentor-calls.php` (`notify_session_moved()`, `notify_left()`, `notify_booked()`, `notify_joined_series()`, `notify_cancelled()`, `notify_reminder()`, `calendar()`, `calendar_many()`; `mail_body()` becomes `mail_of()`, `call_values()`, `moved_values()`, `series_values()` and `calendar_text()`; `series_mail_body()` goes)
- Modify: `bin/test-calls-reports-text.php` (`sent_as()`, the two roles' check, the language check), `bin/test-mail-catalog.php` (the scan's count, the retired ids), `bin/test-mail.php` (the moved notice's words are read from the template file)

**Interfaces:**
- Consumes: Task 2's templates and their placeholders; `WPCPM_Mail_Templates::render( $id, array $values ): array{subject:string,body:string}|null`; `WPCPM_Mail::send( $recipient, $context, $build ): bool`, `::reply_to( $person ): array`, `::site_name(): string`.
- Produces, in `WPCPM_Mentor_Calls`, each private:
  - `mail_of( $id, array $values, array $extra ): array`, a builder's return: the registry's subject and body, then `$extra` (`headers`, `attachments`, `cleanup`); no subject when the registry has no such template, so nothing is sent.
  - `call_values( array $facts, WP_User $recipient, $to_mentor, $trimmed = false ): array<string,string>`: `site_name`, `when`, `call_date`, `time_zone`, `meeting_place`, `topic`, `session_topic`, `dashboard_link`; `$trimmed` counts a topic of spaces alone as none, as the moved and the series emails always did.
  - `moved_values( array $facts, WP_User $recipient, $to_mentor, $mentor, $was_start, $was_end ): array<string,string>`: those, the mentor's name and `was_when`.
  - `series_values( array $facts_list, WP_User $recipient, $to_mentor ): array<string,string>`: the first session's values, `session_dates`, `session_count`, `group_session_count`, `date_count`.
  - `calendar_text( array $facts, $mentor ): string`, the calendar file's description, as `mail_body( ..., 'calendar' )` wrote it.
  - The ids each email is sent under: the mentor's booking `call-booked-mentor`, or `-no-link` while the mentor has no meeting link, and the student's `call-booked-student`; a session's join `session-joined-mentor` (or `-no-link`) and `session-joined-student`; a series `series-joined-mentor` (or `-no-link`) and `series-joined-student`; a move `session-moved-student` and `session-moved-mentor`; a leave `session-left`, or `session-released` for the students sync's; a cancellation `call-cancelled-mentor`, `call-cancelled-student` or, for a session, `session-cancelled`; a reminder `call-reminder-mentor`, `call-reminder-student` or, for a session, `session-reminder`.

- [ ] **Step 1: The recording, read under the templates' ids, and the two checks it cannot hold**

`sent_as()` answers, for each recorded email of the Mentor calls, the template it is sent as now: one table, case by case, which is this release's map from today's emails to the templates. The Semester reports keep their recorded ids. After the recording's checks come the two roles' check and the language check, each told why it is not in the recording:

In `bin/test-calls-reports-text.php`, replace:

```php
}

/**
 * The id each recorded email is logged under now: as recorded, while the emails are the builders'.
 *
 * @param string $case     The case.
 * @param int    $index    Which of its emails, from 0.
```

with:

```php
}

/**
 * The id each recorded email is logged under now: the Mentor calls' template of its own since
 * 1.122.21, each email that says different words one, where the recording holds the one id its
 * context named for all of them; the Semester reports' as recorded.
 *
 * @param string $case     The case.
 * @param int    $index    Which of its emails, from 0.
```

In `bin/test-calls-reports-text.php`, replace:

```php
 * @return string
 */
function sent_as( $case, $index, $recorded ) {
	return $recorded;
}

$fixture = __DIR__ . '/fixtures/calls-reports-1-122-20.json';
```

with:

```php
 * @return string
 */
function sent_as( $case, $index, $recorded ) {
	$ids = array(
		'booked-call'                           => array( 'call-booked-mentor', 'call-booked-student' ),
		'booked-call-bare'                      => array( 'call-booked-mentor-no-link', 'call-booked-student' ),
		'booked-call-site-name'                 => array( 'call-booked-mentor', 'call-booked-student' ),
		'booked-call-topic-of-spaces'           => array( 'call-booked-mentor', 'call-booked-student' ),
		'joined-session'                        => array( 'session-joined-mentor', 'session-joined-student' ),
		'joined-session-bare'                   => array( 'session-joined-mentor-no-link', 'session-joined-student' ),
		'series-two'                            => array( 'series-joined-mentor', 'series-joined-student' ),
		'series-one-bare'                       => array( 'series-joined-mentor-no-link', 'series-joined-student' ),
		'series-three-topic-of-spaces'          => array( 'series-joined-mentor-no-link', 'series-joined-student' ),
		'moved-by-an-administrator'             => array( 'session-moved-student', 'session-moved-student', 'session-moved-mentor' ),
		'moved-by-the-mentor-bare'              => array( 'session-moved-student' ),
		'moved-topic-of-spaces'                 => array( 'session-moved-student', 'session-moved-mentor' ),
		'cancelled-call-by-the-student'         => array( 'call-cancelled-mentor' ),
		'cancelled-call-by-the-mentor'          => array( 'call-cancelled-student' ),
		'cancelled-call-by-an-administrator'    => array( 'call-cancelled-mentor', 'call-cancelled-student' ),
		'cancelled-session-by-the-mentor'       => array( 'session-cancelled', 'session-cancelled' ),
		'cancelled-session-by-an-administrator' => array( 'session-cancelled', 'session-cancelled', 'session-cancelled' ),
		'left-session'                          => array( 'session-left' ),
		'released-from-session-bare'            => array( 'session-released' ),
		'reminder-call'                         => array( 'call-reminder-mentor', 'call-reminder-student' ),
		'reminder-call-bare'                    => array( 'call-reminder-mentor', 'call-reminder-student' ),
		'reminder-session-of-one'               => array( 'session-reminder', 'session-reminder' ),
		'reminder-session-of-three'             => array( 'session-reminder', 'session-reminder', 'session-reminder', 'session-reminder' ),
	);

	return isset( $ids[ $case ][ $index ] ) ? $ids[ $case ][ $index ] : $recorded;
}

$fixture = __DIR__ . '/fixtures/calls-reports-1-122-20.json';
```

In `bin/test-calls-reports-text.php`, replace:

```php
	}
}

ck( 'and the recording holds no address, no real meeting code and no record ID but the seeds\'', is_file( $fixture ) ? array( preg_match( '/[A-Za-z0-9._%+-]*@[A-Za-z0-9-]+\.[A-Za-z]{2,}/', (string) file_get_contents( $fixture ) ), preg_match_all( '/pwd=([A-Za-z0-9-]+)/', (string) file_get_contents( $fixture ), $codes ) > 0 ? array_values( array_unique( $codes[1] ) ) : array(), preg_match( '/rec(?!SEED0\d{9})[A-Za-z0-9]{14}/', (string) file_get_contents( $fixture ) ) ) : null, array( 0, array( 'EXAMPLE-NOT-A-REAL-CODE' ), 0 ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );
```

with:

```php
	}
}

// The one thing the move changes on purpose, and so not in the recording: each email of the Mentor
// calls written for the mentor alone has the mentor's audience now (the two a session sends everybody
// in the same words keep an empty one), and for an account that holds two roles the audience decides
// which kind of person it is (`WPCPM_Mail_Capture::type_of()`). A mentor who is also a student was
// sent the mentor's booking under an id that went to both, so the footer named the Student Report
// Card under a body that names the Mentor Report Card; under the mentor's own template the footer's
// dashboard is the Mentor Report Card, which the body names already, so the footer leaves it out, and
// the Log reads the recipient as a mentor.
$GLOBALS['users'][20]->roles = array( WPCPM_Roles::ROLE_STUDENT, WPCPM_Roles::ROLE_MENTOR );
$two_roles                   = emails_of( $cases['booked-call'] );
$GLOBALS['users'][20]->roles = array( WPCPM_Roles::ROLE_MENTOR );
ck(
	'a mentor who is also a student is sent the mentor\'s booking as a mentor: the Mentor Report Card once, in the body, and no Student Report Card in the footer',
	array( isset( $two_roles[0] ) ? $two_roles[0]['body'] : null, WPCPM_Mail_Capture::type_of( $GLOBALS['users'][20], WPCPM_Mail_Catalog::get( isset( $two_roles[0] ) ? $two_roles[0]['context'] : '' )['audience'] ?? '' ) ),
	array( $recorded['cases']['booked-call'][0]['body'] ?? '', WPCPM_Mail_Catalog::TYPE_MENTOR )
);

// Each email is built in its recipient's language, its values too, in one request: a mentor whose
// account reads Polish is sent the session's reminder in Polish, the count of students coming
// included, while the students on it are sent theirs in English. The template registry is built once
// for each of the two languages (`WPCPM_Mail_Templates::all()`).
$GLOBALS['user_locale'][20] = 'pl_PL';
$polish                     = emails_of( $cases['reminder-session-of-three'] );
unset( $GLOBALS['user_locale'][20] );
ck(
	'a mentor who reads Polish is sent the reminder in Polish, the students coming counted in Polish too, and the students theirs in English',
	array_column( $polish, 'subject' ),
	array(
		"[pl_PL] [Ada & Ben's 100% Credits] Reminder: your group session with [pl_PL] 3 students",
		"[Ada & Ben's 100% Credits] Reminder: your group session with Ben Lindqvist",
		"[Ada & Ben's 100% Credits] Reminder: your group session with Ben Lindqvist",
		"[Ada & Ben's 100% Credits] Reminder: your group session with Ben Lindqvist",
	)
);

ck( 'and the recording holds no address, no real meeting code and no record ID but the seeds\'', is_file( $fixture ) ? array( preg_match( '/[A-Za-z0-9._%+-]*@[A-Za-z0-9-]+\.[A-Za-z]{2,}/', (string) file_get_contents( $fixture ) ), preg_match_all( '/pwd=([A-Za-z0-9-]+)/', (string) file_get_contents( $fixture ), $codes ) > 0 ? array_values( array_unique( $codes[1] ) ) : array(), preg_match( '/rec(?!SEED0\d{9})[A-Za-z0-9]{14}/', (string) file_get_contents( $fixture ) ) ) : null, array( 0, array( 'EXAMPLE-NOT-A-REAL-CODE' ), 0 ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );
```

- [ ] **Step 2: The catalog's suite: nothing sends the five ids of before**

The scan of the source reads 37 contexts, since the senders that choose between templates pass the id they chose rather than a literal; the templates the registry defines cover those; and the five ids of before join the four samples of 1.122.19 as names the Log keeps for its old rows and that nothing sends:

In `bin/test-mail-catalog.php`, replace:

```php
$missing = array_values( array_diff( array_keys( $contexts ), array_keys( $emails ) ) );
sort( $missing );
ck( 'every context the source passes to the mail layer is in the catalog', $missing, array() );
ck( 'and the scan found all 39 of them (it is not reading nothing)', count( $contexts ), 39 );

// The template registry's ids, read from its definition files as each file writes them: one at the
// start of each line of the array the file returns. An invitation is named by its template's id,
```

with:

```php
$missing = array_values( array_diff( array_keys( $contexts ), array_keys( $emails ) ) );
sort( $missing );
ck( 'every context the source passes to the mail layer is in the catalog', $missing, array() );
ck( 'and the scan found all 37 of them (it is not reading nothing)', count( $contexts ), 37 );

// The template registry's ids, read from its definition files as each file writes them: one at the
// start of each line of the array the file returns. An invitation is named by its template's id,
```

In `bin/test-mail-catalog.php`, replace:

```php
	$templates = array_merge( $templates, $t[1] );
}
ck( 'every template the registry defines is in the catalog', array_values( array_diff( $templates, array_keys( $emails ) ) ), array() );
// The four samples Settings > Mail sent until 1.122.19 stay in the catalog, so the Log names the rows
// they left, which it keeps for 30 days; nothing sends them now: Send me a test logs a test under its
// template's own id.
$retired    = array( 'test-student', 'test-mentor', 'test-institution', 'test-sponsor' );
$plugin_ids = array_values( array_diff( array_keys( array_filter( $emails, function ( $e ) { return ! in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) ), $retired ) );
$sent_ids   = array_unique( array_merge( array_keys( $contexts ), $templates ) );
sort( $plugin_ids );
sort( $sent_ids );
ck( 'and every email of the plugin\'s the catalog names is one the source sends or a template the registry holds, but the four samples Settings > Mail sent before 1.122.19', $plugin_ids, $sent_ids );
ck( 'which nothing sends any more', array_values( array_intersect( $retired, $sent_ids ) ), array() );

$filters = WPCPM_Mail_Catalog::wordpress_filters();
```

with:

```php
	$templates = array_merge( $templates, $t[1] );
}
ck( 'every template the registry defines is in the catalog', array_values( array_diff( $templates, array_keys( $emails ) ) ), array() );
// The four samples Settings > Mail sent until 1.122.19, and the five ids the Mentor calls were sent
// under until 1.122.21, stay in the catalog, so the Log names the rows they left, which it keeps for
// 30 days; nothing sends them now: Send me a test logs a test under its template's own id, and each
// email of the Mentor calls is sent under its own template's.
$retired    = array( 'test-student', 'test-mentor', 'test-institution', 'test-sponsor', 'call-booked', 'series-joined', 'session-moved', 'call-cancelled', 'call-reminder' );
$plugin_ids = array_values( array_diff( array_keys( array_filter( $emails, function ( $e ) { return ! in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) ), $retired ) );
$sent_ids   = array_unique( array_merge( array_keys( $contexts ), $templates ) );
sort( $plugin_ids );
sort( $sent_ids );
ck( 'and every email of the plugin\'s the catalog names is one the source sends or a template the registry holds, but the four samples Settings > Mail sent before 1.122.19 and the five Mentor calls ids of before 1.122.21', $plugin_ids, $sent_ids );
ck( 'which nothing sends any more', array_values( array_intersect( $retired, $sent_ids ) ), array() );

$filters = WPCPM_Mail_Catalog::wordpress_filters();
```

- [ ] **Step 3: The moved notice's words are the template file's**

`bin/test-mail.php` reads, from the source, that the students' move notice names the Student Report Card in full; its words live in the template file now:

In `bin/test-mail.php`, replace:

```php
ck( 'the student\'s welcome and the notice that a group session moved name the Student Report Card',
    array(
        substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/mail/templates/invitations.php' ), "__( 'Your Student Report Card:'" ),
        substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-calls.php' ), "__( 'Your Student Report Card:'" ),
        substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-calls.php' ), 'you can leave the session from your Student Report Card.' ),
    ),
    array( 1, 1, 1 ) );
ck( 'and the Institutions screen says a semester report is approved on the Institution Dashboard, by its name',
```

with:

```php
ck( 'the student\'s welcome and the notice that a group session moved name the Student Report Card',
    array(
        substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/mail/templates/invitations.php' ), "__( 'Your Student Report Card:'" ),
        substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/mail/templates/calls.php' ), "__( 'Your Student Report Card:'" ),
        substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/mail/templates/calls.php' ), 'you can leave the session from your Student Report Card.' ),
    ),
    array( 1, 1, 1 ) );
ck( 'and the Institutions screen says a semester report is approved on the Institution Dashboard, by its name',
```

- [ ] **Step 4: Run them to verify they fail**

Run, one per command: `php bin/test-calls-reports-text.php`, `php bin/test-mail-catalog.php`, `php bin/test-mail.php`
Expected: `61 FAILURE(S) (396 checks)`: each of the 59 recorded Mentor calls emails is "logged under its own id" no more (each still under the one its context named), and the two new checks fail (the mentor who is also a student gets the Student Report Card in the footer, and is read as a student; the mentor who reads Polish is told "3 students" in English, the count worded outside the builder); every subject, body, header and calendar file check passes. `3 FAILURE(S) (23 checks)` (the scan finds 39, and the five old ids are still sent). `ALL PASS`.

- [ ] **Step 5: The move of a session**

The students' email and the mentor's are two templates; the old time and the new on the reader's clock are values (`moved_values()`):

In `includes/modules/class-wpcpm-mentor-calls.php`, replace the method `notify_session_moved()`, its docblock included, with:

```php
	/**
	 * Tell everybody on a session that it has moved, and move it in their calendars.
	 *
	 * Sent only when the time changed, and to the students; to the mentor as well when somebody
	 * else moved it. An Administrator may move a mentor's session, and the mentor, who holds the
	 * session in their calendar from the joins, kept the old time and could turn up at it (the deep
	 * check of 1.109.1, SESSIONS-11). The invitation carries the session's revision as its
	 * `SEQUENCE`, which is what makes a calendar treat it as a move of the entry it already holds
	 * rather than as a second entry: without it a student ends up with the old time and the new one
	 * side by side and no way to tell which is real.
	 *
	 * The old time is named in the message, because "your session has moved" on its own leaves
	 * somebody who has three of them wondering which. The words are the template registry's
	 * (`session-moved-student`, `session-moved-mentor`); the invitation stays here.
	 *
	 * @param int $call_id   Session post ID.
	 * @param int $was_start The start it had before, as a timestamp.
	 * @param int $revision  How many times it has been changed.
	 */
	public static function notify_session_moved( $call_id, $was_start, $revision ) {
		$call = get_post( (int) $call_id );

		if ( ! $call instanceof WP_Post || ! self::mail_enabled( $call ) ) {
			return;
		}

		$facts    = self::details( $call );
		$mentor   = get_user_by( 'id', $facts['mentor_id'] );
		$actor    = get_current_user_id();
		$was_end  = (int) $was_start + ( (int) $facts['end'] - (int) $facts['start'] );
		$revision = (int) $revision;

		foreach ( self::attendees( $call->ID ) as $student_id ) {
			$student = get_user_by( 'id', (int) $student_id );

			// The person who moved it already knows. Everybody else is finding out.
			if ( ! $student instanceof WP_User || (int) $student->ID === (int) $actor ) {
				continue;
			}

			WPCPM_Mail::send(
				$student,
				'session-moved-student',
				function ( $recipient ) use ( $facts, $mentor, $student, $was_start, $was_end, $revision ) {
					$invite = self::calendar( $facts, WPCPM_ICS::METHOD_REQUEST, $mentor, $student, $recipient, $revision );

					return self::mail_of(
						'session-moved-student',
						self::moved_values( $facts, $recipient, false, $mentor, $was_start, $was_end ),
						array(
							'headers'     => WPCPM_Mail::reply_to( $mentor ),
							'attachments' => $invite,
							'cleanup'     => $invite,
						)
					);
				}
			);
		}

		if ( ! $mentor instanceof WP_User || (int) $mentor->ID === (int) $actor ) {
			return;
		}

		// The mentor's copy names the first student, as the mentor's copy of a cancellation does:
		// somebody the mentor already knows, and each student's own file names that student alone.
		$first = get_user_by( 'id', (int) $facts['student_id'] );

		WPCPM_Mail::send(
			$mentor,
			'session-moved-mentor',
			function ( $recipient ) use ( $facts, $mentor, $first, $was_start, $was_end, $revision ) {
				$invite = self::calendar( $facts, WPCPM_ICS::METHOD_REQUEST, $mentor, $first instanceof WP_User ? $first : null, $recipient, $revision );

				return self::mail_of(
					'session-moved-mentor',
					self::moved_values( $facts, $recipient, true, $mentor, $was_start, $was_end ),
					array(
						'attachments' => $invite,
						'cleanup'     => $invite,
					)
				);
			}
		);
	}
```

- [ ] **Step 6: A place on a session released**

In `includes/modules/class-wpcpm-mentor-calls.php`, replace the method `notify_left()`, its docblock included, with:

```php
	/**
	 * Tell one student their place on a session is released.
	 *
	 * Only them: the session goes ahead for everybody else, so telling the rest that somebody left
	 * would be noise - and telling them *who* left would be worse. A student the students sync took
	 * off, their pairing having settled on a new mentor, is told that rather than that they left
	 * (SESSIONS-7): two templates, `session-released` and `session-left`.
	 *
	 * @param int      $call_id    Session post ID.
	 * @param int      $student_id Who left.
	 * @param int|null $revision   The session's calendar version, raised by the leave (SESSIONS-3);
	 *                             null keeps a cancellation's default.
	 * @param bool     $repaired   Whether the students sync took them off for a new mentor.
	 */
	public static function notify_left( $call_id, $student_id, $revision = null, $repaired = false ) {
		$call = get_post( (int) $call_id );

		if ( ! $call instanceof WP_Post || ! self::mail_enabled( $call ) ) {
			return;
		}

		$student = get_user_by( 'id', (int) $student_id );

		if ( ! $student instanceof WP_User ) {
			return;
		}

		$facts  = self::details( $call );
		$mentor = get_user_by( 'id', $facts['mentor_id'] );
		$id     = $repaired ? 'session-released' : 'session-left';

		WPCPM_Mail::send(
			$student,
			$id,
			function ( $recipient ) use ( $id, $facts, $mentor, $student, $revision ) {
				// `METHOD:CANCEL` with the same UID as the invitation, so the entry disappears
				// from their calendar rather than sitting there for a session they left.
				$invite = self::calendar( $facts, WPCPM_ICS::METHOD_CANCEL, $mentor, $student, $recipient, $revision );

				return self::mail_of(
					$id,
					array( 'mentor_name' => $mentor instanceof WP_User ? $mentor->display_name : '' ) + self::call_values( $facts, $recipient, false ),
					array(
						// A reply goes to the mentor, as every other message about the session does
						// (SESSIONS-12: this one had nobody to reply to).
						'headers'     => WPCPM_Mail::reply_to( $mentor ),
						'attachments' => $invite,
						'cleanup'     => $invite,
					)
				);
			}
		);
	}
```

- [ ] **Step 7: A booking, and a join of a session**

The mentor's copy is chosen by the mentor's meeting link as well; "your student", for a call with no student name stored, is worded inside the mentor's language:

In `includes/modules/class-wpcpm-mentor-calls.php`, replace the method `notify_booked()`, its docblock included, with:

```php
	/**
	 * Tell both people a call was booked.
	 *
	 * Plain mail, deliberately: a call nobody is told about is a call nobody attends, and
	 * neither party is necessarily going to revisit the dashboard.
	 *
	 * Each copy is a template of its own (`WPCPM_Mail_Templates`, the Mentor calls area): a call's
	 * and a session's say different words, and the mentor's copy says where to meet while the
	 * mentor has a meeting link and that they have none otherwise, so the mentor's is chosen by the
	 * link as well.
	 *
	 * @param int          $call_id  Call post ID.
	 * @param WP_User      $mentor   Mentor.
	 * @param WP_User|null $student  Student.
	 * @param int|null     $revision A session's calendar version (SESSIONS-3); null for a
	 *                               one-to-one booking, which is sent once.
	 */
	private static function notify_booked( $call_id, WP_User $mentor, $student, $revision = null ) {
		$call = get_post( $call_id );

		if ( ! $call instanceof WP_Post || ! self::mail_enabled( $call ) ) {
			return;
		}

		$facts   = self::details( $call );
		$session = ! empty( $facts['is_group'] );
		$linked  = '' !== WPCPM_Mentor_Availability::meeting_place( (int) $facts['mentor_id'] );

		if ( $session ) {
			$to_mentor  = $linked ? 'session-joined-mentor' : 'session-joined-mentor-no-link';
			$to_student = 'session-joined-student';
		} else {
			$to_mentor  = $linked ? 'call-booked-mentor' : 'call-booked-mentor-no-link';
			$to_student = 'call-booked-student';
		}

		WPCPM_Mail::send(
			$mentor,
			$to_mentor,
			function ( $recipient ) use ( $to_mentor, $facts, $mentor, $student, $revision, $session ) {
				$invite = self::calendar( $facts, WPCPM_ICS::METHOD_REQUEST, $mentor, $student, $recipient, $revision );
				$name   = '' !== $facts['name'] ? $facts['name'] : __( 'your student', 'wpcredits-program-manager' );

				// A session carries no student's name of its own, so its joiner is named from their
				// account: "your student booked a call" told the mentor nothing about who was coming
				// (the deep check of 1.109.1, SESSIONS-12).
				if ( $session && $student instanceof WP_User ) {
					$name = $student->display_name;
				}

				return self::mail_of(
					$to_mentor,
					array( 'student_name' => $name ) + self::call_values( $facts, $recipient, true ),
					array(
						'headers'     => WPCPM_Mail::reply_to( $student ),
						'attachments' => $invite,
						'cleanup'     => $invite,
					)
				);
			}
		);

		if ( ! $student instanceof WP_User ) {
			return;
		}

		WPCPM_Mail::send(
			$student,
			$to_student,
			function ( $recipient ) use ( $to_student, $facts, $mentor, $student, $revision ) {
				$invite = self::calendar( $facts, WPCPM_ICS::METHOD_REQUEST, $mentor, $student, $recipient, $revision );

				return self::mail_of(
					$to_student,
					array( 'mentor_name' => $mentor->display_name ) + self::call_values( $facts, $recipient, false ),
					array(
						'headers'     => WPCPM_Mail::reply_to( $mentor ),
						'attachments' => $invite,
						'cleanup'     => $invite,
					)
				);
			}
		);
	}
```

- [ ] **Step 8: A series joined**

In `includes/modules/class-wpcpm-mentor-calls.php`, replace the method `notify_joined_series()`, its docblock included, with:

```php
	/**
	 * Tell both people a student joined a series: one message each, with one calendar file holding
	 * every session the student was put on (the design's section 6, 1.108.0). The mentor's copy is
	 * chosen by the mentor's meeting link, as a single booking's is.
	 *
	 * @param int[]        $call_ids  The sessions the student was put on, soonest first.
	 * @param WP_User      $mentor    Mentor.
	 * @param WP_User|null $student   Student.
	 * @param int[]        $revisions Each session's calendar version, raised by the join, keyed by
	 *                                its post ID (SESSIONS-3); a session missing here is sent its
	 *                                first version.
	 */
	public static function notify_joined_series( array $call_ids, WP_User $mentor, $student, array $revisions = array() ) {
		$facts_list = array();

		foreach ( $call_ids as $call_id ) {
			$call = get_post( (int) $call_id );

			if ( $call instanceof WP_Post && self::mail_enabled( $call ) ) {
				$facts = self::details( $call );

				// Carried per event: each session of the file has a version of its own.
				$facts['sequence'] = isset( $revisions[ (int) $call_id ] ) ? (int) $revisions[ (int) $call_id ] : null;
				$facts_list[]      = $facts;
			}
		}

		if ( array() === $facts_list || ! $student instanceof WP_User ) {
			return;
		}

		$first     = reset( $facts_list );
		$to_mentor = '' !== WPCPM_Mentor_Availability::meeting_place( (int) $first['mentor_id'] ) ? 'series-joined-mentor' : 'series-joined-mentor-no-link';

		WPCPM_Mail::send(
			$mentor,
			$to_mentor,
			function ( $recipient ) use ( $to_mentor, $facts_list, $mentor, $student ) {
				$invite = self::calendar_many( $facts_list, $mentor, $student, $recipient );

				return self::mail_of(
					$to_mentor,
					array( 'student_name' => $student->display_name ) + self::series_values( $facts_list, $recipient, true ),
					array(
						'headers'     => WPCPM_Mail::reply_to( $student ),
						'attachments' => $invite,
						'cleanup'     => $invite,
					)
				);
			}
		);

		WPCPM_Mail::send(
			$student,
			'series-joined-student',
			function ( $recipient ) use ( $facts_list, $mentor, $student ) {
				$invite = self::calendar_many( $facts_list, $mentor, $student, $recipient );

				return self::mail_of(
					'series-joined-student',
					array( 'mentor_name' => $mentor->display_name ) + self::series_values( $facts_list, $recipient, false ),
					array(
						'headers'     => WPCPM_Mail::reply_to( $mentor ),
						'attachments' => $invite,
						'cleanup'     => $invite,
					)
				);
			}
		);
	}
```

- [ ] **Step 9: A cancellation**

Who canceled is worked out once, as before, and "an Administrator" is worded inside each recipient's language:

In `includes/modules/class-wpcpm-mentor-calls.php`, replace the method `notify_cancelled()`, its docblock included, with:

```php
	/**
	 * Tell everybody else a call was canceled.
	 *
	 * A session's cancellation says the same words to its mentor and to every student on it
	 * (`session-cancelled`); a one-to-one call's tells the mentor that the student can book again and
	 * the student that they can (`call-cancelled-mentor`, `call-cancelled-student`).
	 *
	 * @param WP_Post  $call     Call post.
	 * @param array    $facts    Its facts, read under the booking lock as it was trashed, so the
	 *                           people told are the people who were on it (SESSIONS-5).
	 * @param int|null $revision A session's calendar version, raised by the cancellation
	 *                           (SESSIONS-3); null keeps a cancellation's default.
	 */
	private static function notify_cancelled( WP_Post $call, array $facts, $revision = null ) {
		if ( ! self::mail_enabled( $call ) ) {
			return;
		}

		$actor   = get_current_user_id();
		$mentor  = get_user_by( 'id', $facts['mentor_id'] );
		$student = get_user_by( 'id', $facts['student_id'] );

		// Who to name as having canceled: the mentor or the student by name. An Administrator acting
		// on somebody's behalf is named by their role rather than personally (null here, worded in
		// each recipient's language below): to a student, an Administrator they have never heard of
		// reads as a stranger calling their call off.
		$by = null;

		if ( $actor && ( $actor === (int) $facts['mentor_id'] || $actor === (int) $facts['student_id'] ) ) {
			$by = (string) wp_get_current_user()->display_name;
		}

		foreach ( self::recipients( $facts ) as $person ) {
			// The person who pressed cancel knows; everybody else needs telling.
			if ( (int) $person->ID === $actor ) {
				continue;
			}

			$to_mentor = (int) $person->ID === (int) $facts['mentor_id'];
			$session   = ! empty( $facts['is_group'] );
			// The mentor's copy of a session's cancellation has no one student to reply to: the first
			// to join is one of several (the fix round of SESSIONS-12, as for the reminder).
			$other = $to_mentor ? ( $session ? null : $student ) : $mentor;
			// On a group session each student's file names that student alone, as the move notice
			// does: the first attendee's name and address went to every other student otherwise
			// (the deep check of 1.109.1, SESSIONS-2; 1.109.2). The mentor's copy keeps the first
			// attendee, whom the mentor already knows.
			$named = ( ! $to_mentor && $session ) ? $person : $student;

			if ( $session ) {
				$id = 'session-cancelled';
			} else {
				$id = $to_mentor ? 'call-cancelled-mentor' : 'call-cancelled-student';
			}

			WPCPM_Mail::send(
				$person,
				$id,
				function ( $recipient ) use ( $id, $facts, $by, $to_mentor, $mentor, $named, $other, $revision ) {
					$invite = self::calendar( $facts, WPCPM_ICS::METHOD_CANCEL, $mentor, $named, $recipient, $revision );

					return self::mail_of(
						$id,
						array( 'canceled_by' => null === $by ? __( 'an Administrator', 'wpcredits-program-manager' ) : $by ) + self::call_values( $facts, $recipient, $to_mentor ),
						array(
							'headers'     => WPCPM_Mail::reply_to( $other ),
							'attachments' => $invite,
							'cleanup'     => $invite,
						)
					);
				}
			);
		}
	}
```

- [ ] **Step 10: A reminder**

The mentor's count of students coming and "your student" are worded inside the recipient's language; how long until the call is worked out as the email is built, as it was:

In `includes/modules/class-wpcpm-mentor-calls.php`, replace the method `notify_reminder()`, its docblock included, with:

```php
	/**
	 * Send one call's reminder to both parties.
	 *
	 * A one-to-one call's reminder is the mentor's and the student's own template; a group session's
	 * says the same words to everybody on it, the mentor told how many are coming where a student is
	 * told the mentor's name (`session-reminder`).
	 *
	 * @param WP_Post $call Call post.
	 * @param int     $lead Reminder lead time in hours.
	 */
	private static function notify_reminder( WP_Post $call, $lead ) {
		if ( ! self::mail_enabled( $call ) ) {
			return;
		}

		$facts = self::details( $call );

		// Booked inside the reminder window: the confirmation *is* the reminder, and a second
		// message minutes after the first says nothing new.
		if ( (int) $facts['booked'] > (int) $facts['start'] - ( $lead * HOUR_IN_SECONDS ) ) {
			return;
		}

		$mentor  = get_user_by( 'id', $facts['mentor_id'] );
		$student = get_user_by( 'id', $facts['student_id'] );

		foreach ( self::recipients( $facts ) as $person ) {
			$to_mentor = (int) $person->ID === (int) $facts['mentor_id'];
			// The mentor's copy of a session's reminder has nobody to reply to: the first student to
			// join is one of several, and replying to them alone was wrong (SESSIONS-12).
			$other = $to_mentor ? ( $facts['is_group'] ? null : $student ) : $mentor;

			if ( $facts['is_group'] ) {
				$id = 'session-reminder';
			} else {
				$id = $to_mentor ? 'call-reminder-mentor' : 'call-reminder-student';
			}

			WPCPM_Mail::send(
				$person,
				$id,
				function ( $recipient ) use ( $id, $facts, $to_mentor, $mentor, $other ) {
					$mentor_name = $mentor instanceof WP_User ? $mentor->display_name : '';
					$coming      = count( $facts['attendees'] );

					return self::mail_of(
						$id,
						array(
							'student_name' => '' !== $facts['name'] ? $facts['name'] : __( 'your student', 'wpcredits-program-manager' ),
							'mentor_name'  => $mentor_name,
							// A group session has no single other person to name, so the mentor is told how
							// many are coming instead. Naming one of several attendees would be worse than
							// naming none.
							'with_whom'    => $to_mentor
								? sprintf(
									/* translators: %s: number of students attending. */
									_n( '%s student', '%s students', $coming, 'wpcredits-program-manager' ),
									number_format_i18n( $coming )
								)
								: $mentor_name,
							'in_how_long'  => self::relative( $facts['start'] ),
						) + self::call_values( $facts, $recipient, $to_mentor ),
						array( 'headers' => WPCPM_Mail::reply_to( $other ) )
					);
				}
			);
		}
	}
```

- [ ] **Step 11: The calendar files' description, and the values every email fills in**

The calendar files keep their words, which `calendar_text()` writes as `mail_body()` wrote them for a calendar: the two places that ask for them:

In `includes/modules/class-wpcpm-mentor-calls.php`, replace:

```php
		$description = self::mail_body( $facts, $recipient, $mentor_name, false, 'calendar' );
```

with:

```php
		$description = self::calendar_text( $facts, $mentor_name );
```

In `includes/modules/class-wpcpm-mentor-calls.php`, replace:

```php
			$facts['description'] = self::mail_body( $facts, $recipient, $mentor->display_name, false, 'calendar' );
```

with:

```php
			$facts['description'] = self::calendar_text( $facts, $mentor->display_name );
```

The series' body is the template's now:

In `includes/modules/class-wpcpm-mentor-calls.php`, delete the method `series_mail_body()`, its docblock and the blank line above it included.

and `mail_body()` gives way to the values every email fills in and to the calendar's own text:

In `includes/modules/class-wpcpm-mentor-calls.php`, replace the method `mail_body()`, its docblock included, with:

```php
	/**
	 * One email of the Mentor calls area as the mail layer takes it: its subject and body, the
	 * template registry's (`WPCPM_Mail_Templates`) filled with the values given, and what stays
	 * here, its Reply-To and its calendar file: a template is a subject and a body.
	 *
	 * @param string $id     The template's id.
	 * @param array  $values Each placeholder's value, by name, worked out in the recipient's language
	 *                       and on their clock.
	 * @param array  $extra  The headers, and the attachments and their cleanup.
	 * @return array What a builder of `WPCPM_Mail::send()` returns; with no such template, no subject,
	 *               so nothing is sent.
	 */
	private static function mail_of( $id, array $values, array $extra ) {
		$mail = WPCPM_Mail_Templates::render( $id, $values );

		if ( null === $mail ) {
			$mail = array(
				'subject' => '',
				'body'    => '',
			);
		}

		return array_merge( $mail, $extra );
	}

	/**
	 * The values an email about one call or session fills its placeholders with, for one recipient:
	 * the site's name, the call's time on the recipient's own clock and that clock's name, its day,
	 * where to meet, what it is about, and the recipient's dashboard. A template uses the ones it has.
	 *
	 * Worked out here, inside the recipient's language, for the reason the class's mail always was:
	 * the facts are stated in the same order and on the same clock every time.
	 *
	 * @param array   $facts     Call facts.
	 * @param WP_User $recipient Who is reading it.
	 * @param bool    $to_mentor Whether the reader is the mentor, whose dashboard is the mentor's.
	 * @param bool    $trimmed   Whether a topic of spaces alone counts as none, as the moved and the
	 *                           series emails count it; a booking, a reminder and a leave print it.
	 * @return array<string,string>
	 */
	private static function call_values( array $facts, WP_User $recipient, $to_mentor, $trimmed = false ) {
		$zone  = WPCPM_Mentor_Availability::viewer_timezone( $recipient->ID );
		$topic = (string) $facts['topic'];
		$topic = $trimmed && '' === trim( $topic ) ? '' : $topic;

		return array(
			'site_name'      => WPCPM_Mail::site_name(),
			'when'           => self::format_range( $facts['start'], $facts['end'], $zone ),
			'call_date'      => wp_date( get_option( 'date_format' ), (int) $facts['start'], $zone ),
			'time_zone'      => WPCPM_Mentor_Availability::zone_label( $zone->getName() ),
			'meeting_place'  => WPCPM_Mentor_Availability::meeting_place( (int) $facts['mentor_id'] ),
			'topic'          => $topic,
			'session_topic'  => $topic,
			'dashboard_link' => $to_mentor ? WPCPM_Mentors_Dashboard::page_url() : WPCPM_Students_Dashboard::page_url(),
		);
	}

	/**
	 * The values a moved session's email fills in: `call_values()`'s, the mentor's name, and the time
	 * the session had before, on the recipient's clock.
	 *
	 * @param array        $facts     Call facts, at the new time.
	 * @param WP_User      $recipient Who is reading it.
	 * @param bool         $to_mentor Whether the reader is the mentor.
	 * @param WP_User|null $mentor    The mentor.
	 * @param int          $was_start The start it had before, as a timestamp.
	 * @param int          $was_end   The end it had before.
	 * @return array<string,string>
	 */
	private static function moved_values( array $facts, WP_User $recipient, $to_mentor, $mentor, $was_start, $was_end ) {
		return array(
			'mentor_name' => $mentor instanceof WP_User ? $mentor->display_name : '',
			'was_when'    => self::format_range( $was_start, $was_end, WPCPM_Mentor_Availability::viewer_timezone( $recipient->ID ) ),
		) + self::call_values( $facts, $recipient, $to_mentor, true );
	}

	/**
	 * The values a series email fills in: the first session's, as `call_values()` gives them, every
	 * session's time on the recipient's clock, one to a line, and how many there are, counted in
	 * words, so the template never needs a plural rule (the design's section 6, 1.108.0).
	 *
	 * @param array[] $facts_list Call facts, one per session, soonest first.
	 * @param WP_User $recipient  Who is reading it.
	 * @param bool    $to_mentor  Whether the reader is the mentor.
	 * @return array<string,string>
	 */
	private static function series_values( array $facts_list, WP_User $recipient, $to_mentor ) {
		$zone  = WPCPM_Mentor_Availability::viewer_timezone( $recipient->ID );
		$count = count( $facts_list );
		$dates = array();

		foreach ( $facts_list as $facts ) {
			$dates[] = self::format_range( $facts['start'], $facts['end'], $zone );
		}

		return array(
			'session_dates'       => implode( "\n", $dates ),
			'session_count'       => sprintf(
				/* translators: %d: how many sessions. */
				_n( '%d session', '%d sessions', $count, 'wpcredits-program-manager' ),
				$count
			),
			'group_session_count' => sprintf(
				/* translators: %d: how many sessions. */
				_n( '%d group session', '%d group sessions', $count, 'wpcredits-program-manager' ),
				$count
			),
			'date_count'          => sprintf(
				/* translators: %d: how many dates. */
				_n( '%d date', '%d dates', $count, 'wpcredits-program-manager' ),
				$count
			),
		) + self::call_values( reset( $facts_list ), $recipient, $to_mentor, true );
	}

	/**
	 * What a call's calendar entry says under its title: what it is and with whom, where to meet,
	 * and what it is about. The calendar already shows when it is, so no time is repeated here. A
	 * calendar file is an attachment, so its words stay here, while the emails that carry it are the
	 * template registry's; it is built inside the recipient's language, as the email is.
	 *
	 * @param array  $facts  Call facts.
	 * @param string $mentor The mentor's name.
	 * @return string
	 */
	private static function calendar_text( array $facts, $mentor ) {
		// A group session is not a one-to-one call, and its calendar files are worded as the session
		// they are about (the deep check of 1.109.1, SESSIONS-12).
		$session = ! empty( $facts['is_group'] );
		$lines   = array(
			$session
				? sprintf(
					/* translators: %s: mentor name. */
					__( 'A group session on the WordPress Credits Program with %s.', 'wpcredits-program-manager' ),
					$mentor
				)
				: sprintf(
					/* translators: %s: mentor name. */
					__( 'A mentor call on the WordPress Credits Program with %s.', 'wpcredits-program-manager' ),
					$mentor
				),
		);

		$where = WPCPM_Mentor_Availability::meeting_place( (int) $facts['mentor_id'] );

		if ( '' !== $where ) {
			$lines[] = '';
			$lines[] = __( 'Where you will meet:', 'wpcredits-program-manager' );
			$lines[] = $where;
		}

		if ( '' !== $facts['topic'] ) {
			$lines[] = '';
			// A session's topic is the mentor's, written for everybody on it (SESSIONS-12); a call's
			// is what the student wrote, and the student is addressed as themselves.
			$lines[] = $session
				? __( 'What the session is about:', 'wpcredits-program-manager' )
				: __( 'What you said you would like to discuss:', 'wpcredits-program-manager' );
			$lines[] = $facts['topic'];
		}

		return implode( "\n", $lines );
	}
```

- [ ] **Step 12: Run them to verify they pass**

Run, one per command: `php bin/test-calls-reports-text.php`, `php bin/test-mail-catalog.php`, `php bin/test-mail.php`, `php bin/test-group-sessions.php`, `php bin/test-handlers.php`, `php -l includes/modules/class-wpcpm-mentor-calls.php`
Expected: `ALL PASS (396 checks)`: every Mentor calls email, to every recipient, is the recording's subject, body, headers and calendar files, byte for byte, under its template's own id; `ALL PASS (23 checks)`; `ALL PASS`; `ALL PASS (95 checks)`; `ALL HANDLERS REACHED A NORMAL OUTCOME`; `No syntax errors detected in includes/modules/class-wpcpm-mentor-calls.php`.

- [ ] **Step 13: Run the whole battery**

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 14: Commit**

```bash
git add includes/modules/class-wpcpm-mentor-calls.php bin/test-calls-reports-text.php bin/test-mail-catalog.php bin/test-mail.php
git commit -m "Emails E3: the Mentor calls emails built from the registry, word for word, each under its own id"
```

---


### Task 4: The Semester reports, built from the registry

The three builders of `WPCPM_Semester_Report_Screen` hand the registry their values: the institution, the semester, the count of students still in progress worded with its verb, and the links. Task 1's recording holds the area's 28 recorded emails to their bytes, but for the one line ending the ten with no Institution Dashboard page lose, which the suite expects and pins as exactly that.

**Files:**
- Modify: `includes/modules/class-wpcpm-semester-report-screen.php` (`drafted_message()`, `approved_message()`, `ask_message()`; the new `rendered()`)
- Modify: `bin/test-calls-reports-text.php` (`body_as()`, the body's check, the check of what the move changes), `bin/test-semester-report.php` (it loads the registry, which words the screen's emails now)

**Interfaces:**
- Consumes: Task 2's `report-drafted`, `report-approved` and `report-consent` and their placeholders; `WPCPM_Mail_Templates::render()`.
- Produces: `WPCPM_Semester_Report_Screen::rendered( $id, array $values ): array{subject:string,body:string}` (private), no subject when the registry has no such template, so nothing is sent; in the suite, `body_as( $case, $recorded ): string`, the body each recorded email is sent with now.

- [ ] **Step 1: The recording's one line, and the report suite's registry**

`body_as()` takes the doubled blank line above the footer out of the five cases with no Institution Dashboard page, and from them alone; the body's check says so in its label where it applies, and two checks after the recording's pin the change to one line ending in each of ten emails, and those ten to every recorded email whose footer stands under a doubled blank line:

In `bin/test-calls-reports-text.php`, replace:

```php
	return isset( $ids[ $case ][ $index ] ) ? $ids[ $case ][ $index ] : $recorded;
}

$fixture = __DIR__ . '/fixtures/calls-reports-1-122-20.json';

if ( in_array( '--record', $argv, true ) ) {
```

with:

```php
	return isset( $ids[ $case ][ $index ] ) ? $ids[ $case ][ $index ] : $recorded;
}

/**
 * The body each recorded email is sent with now: as recorded, byte for byte, but for one line in the
 * five cases with the Institution Dashboard page not published (none of the four pages, or the
 * Administrator Dashboard alone). There 1.122.20's report drafted and report approved bodies ended in
 * a blank line, a link's line left out above it, so the footer stood under two blank lines; the
 * registry leaves out a blank line a line left out would leave at the end of a body, as it always
 * has, so one stands there now. The consent request has no such line, and is as recorded in every case.
 *
 * @param string $case     The case.
 * @param string $recorded The body the recording holds.
 * @return string
 */
function body_as( $case, $recorded ) {
	$no_institution_page = array(
		'report-drafted-bare',
		'report-drafted-in-progress-bare',
		'report-drafted-administrator-page-only',
		'report-approved-bare',
		'report-approved-administrator-page-only',
	);

	if ( in_array( $case, $no_institution_page, true ) ) {
		return str_replace( "\n\n\n-- \n", "\n\n-- \n", $recorded );
	}

	return $recorded;
}

$fixture = __DIR__ . '/fixtures/calls-reports-1-122-20.json';

if ( in_array( '--record', $argv, true ) ) {
```

In `bin/test-calls-reports-text.php`, replace:

```php

		ck( $name . ' #' . $n . ': logged under its own id', $now['context'], sent_as( $name, $n, $mail['context'] ) );
		ck( $name . ' #' . $n . ': the subject, byte for byte', $now['subject'], $mail['subject'] );
		ck( $name . ' #' . $n . ': the body, byte for byte, the signature and footer included', $now['body'], $mail['body'] );
		ck( $name . ' #' . $n . ': the headers and the calendar files, byte for byte', array( $now['headers'], $now['attachments'] ), array( $mail['headers'], $mail['attachments'] ) );
	}
}

// The one thing the move changes on purpose, and so not in the recording: each email of the Mentor
// calls written for the mentor alone has the mentor's audience now (the two a session sends everybody
// in the same words keep an empty one), and for an account that holds two roles the audience decides
```

with:

```php

		ck( $name . ' #' . $n . ': logged under its own id', $now['context'], sent_as( $name, $n, $mail['context'] ) );
		ck( $name . ' #' . $n . ': the subject, byte for byte', $now['subject'], $mail['subject'] );
		ck( $name . ' #' . $n . ( body_as( $name, $mail['body'] ) === $mail['body'] ? ': the body, byte for byte, the signature and footer included' : ': the body, byte for byte, the signature and footer included, but the blank line the recording doubles above the footer' ), $now['body'], body_as( $name, $mail['body'] ) );
		ck( $name . ' #' . $n . ': the headers and the calendar files, byte for byte', array( $now['headers'], $now['attachments'] ), array( $mail['headers'], $mail['attachments'] ) );
	}
}

// And the one line the move takes out, in those five cases alone: one line ending in each of their
// ten emails, above the footer.
$changed = array();
$doubled = array();
foreach ( is_array( $recorded ) && isset( $recorded['cases'] ) ? $recorded['cases'] : array() as $name => $mails ) {
	foreach ( $mails as $n => $mail ) {
		if ( body_as( $name, $mail['body'] ) !== $mail['body'] ) {
			$changed[] = $name . ' #' . $n . ': ' . ( strlen( $mail['body'] ) - strlen( body_as( $name, $mail['body'] ) ) );
		}

		if ( false !== strpos( $mail['body'], "\n\n\n-- \n" ) ) {
			$doubled[] = $name . ' #' . $n . ': ' . ( strlen( $mail['body'] ) - strlen( str_replace( "\n\n\n-- \n", "\n\n-- \n", $mail['body'] ) ) );
		}
	}
}
ck(
	'the move takes one line ending out of ten recorded emails, the reports\' with no Institution Dashboard page published, and out of no other',
	$changed,
	array(
		'report-drafted-bare #0: 1',
		'report-drafted-bare #1: 1',
		'report-drafted-in-progress-bare #0: 1',
		'report-drafted-in-progress-bare #1: 1',
		'report-approved-bare #0: 1',
		'report-approved-bare #1: 1',
		'report-drafted-administrator-page-only #0: 1',
		'report-drafted-administrator-page-only #1: 1',
		'report-approved-administrator-page-only #0: 1',
		'report-approved-administrator-page-only #1: 1',
	)
);
ck( 'and those ten are every recorded email whose footer stands under a doubled blank line, in either area', $doubled, $changed );

// The one thing the move changes on purpose, and so not in the recording: each email of the Mentor
// calls written for the mentor alone has the mentor's audience now (the two a session sends everybody
// in the same words keep an empty one), and for an account that holds two roles the audience decides
```

`bin/test-semester-report.php` runs the daily job, which mails the draft through a stand-in that calls the builder; the builder asks the registry from now on, so the suite loads it:

In `bin/test-semester-report.php`, replace:

```php
// land full; the real option-backed class is not loaded here, and nothing this suite exercises
// (class-wpcpm-student-report-form.php's fields() included) calls the real one.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-report-form.php';

// The program map and the report forms are the compiled tracks' (bin/stubs/compiled-seeds.php).
WPCPM_Tracks::init();
```

with:

```php
// land full; the real option-backed class is not loaded here, and nothing this suite exercises
// (class-wpcpm-student-report-form.php's fields() included) calls the real one.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-student-report-form.php';
// The template registry, which words the screen's three emails since 1.122.21, and the catalog that
// names them.
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';

// The program map and the report forms are the compiled tracks' (bin/stubs/compiled-seeds.php).
WPCPM_Tracks::init();
```

- [ ] **Step 2: Run them to verify the recording's check fails**

Run, one per command: `php bin/test-calls-reports-text.php`, `php bin/test-semester-report.php`
Expected: `10 FAILURE(S) (398 checks)`: the ten report emails with no Institution Dashboard page published still end in the doubled blank line; every other check passes, the new check of what the move changes among them, since it reads the recording. `ALL PASS (534 checks)`, as before, which it goes on doing once the builders ask the registry.

- [ ] **Step 3: The draft's notice to the Administrators**

In `includes/modules/class-wpcpm-semester-report-screen.php`, replace the method `drafted_message()`, its docblock included, with:

```php
	/**
	 * The message to the Administrators when the job drafts a report: the template registry's
	 * `report-drafted`, filled with the institution, the semester, how many of its students were still
	 * in progress, and the draft's address.
	 *
	 * @param string $institution Institutions record ID.
	 * @param string $cohort      Cohort key.
	 * @param int    $in_progress Rows still in progress when it was drafted.
	 * @return callable A builder for `WPCPM_Mail::send()`.
	 */
	private static function drafted_message( $institution, $cohort, $in_progress ) {
		return function () use ( $institution, $cohort, $in_progress ) {
			// row() answers null for a record the index has since dropped between due() and
			// this mail being built; the institution's own ID is what the sentence falls back
			// to; a name is never worth a fatal over.
			$row  = class_exists( 'WPCPM_Institutions_Index' ) ? WPCPM_Institutions_Index::row( $institution ) : null;
			$name = is_array( $row ) && isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
			$name = '' !== $name ? $name : $institution;
			$url  = self::report_url( $cohort );
			$url  = '' !== $url ? add_query_arg( WPCPM_Institution_Roster::ARG_VIEW, $institution, $url ) : '';

			return self::rendered(
				self::MAIL_DRAFTED,
				array(
					'institution_name'     => $name,
					'semester'             => WPCPM_Cohort::label( $cohort ),
					// Counted here with the verb that goes with the number, so the template never
					// needs a plural rule; none in progress leaves the sentence out.
					'students_in_progress' => $in_progress > 0
						? sprintf(
							/* translators: %s: a number of students. */
							_n( '%s student in this semester was', '%s students in this semester were', $in_progress, 'wpcredits-program-manager' ),
							number_format_i18n( $in_progress )
						)
						: '',
					'review_link'          => $url,
				)
			);
		};
	}
```

- [ ] **Step 4: The approval, and the screen's one way into the registry**

In `includes/modules/class-wpcpm-semester-report-screen.php`, replace the method `approved_message()`, its docblock included, with:

```php
	/**
	 * The message to an institution's accounts when a report is approved: the template registry's
	 * `report-approved`, filled with the semester and the report's address.
	 *
	 * @param string $cohort Cohort key.
	 * @return callable A builder for `WPCPM_Mail::send()`.
	 */
	private static function approved_message( $cohort ) {
		return function () use ( $cohort ) {
			return self::rendered(
				self::MAIL_APPROVED,
				array(
					'semester'    => WPCPM_Cohort::label( $cohort ),
					'report_link' => self::report_url( $cohort ),
				)
			);
		};
	}

	/**
	 * One of the screen's emails, as the mail layer takes it: the template registry's subject and
	 * body, filled with the values given.
	 *
	 * @param string $id     The template's id.
	 * @param array  $values Each placeholder's value, by name.
	 * @return array{subject:string,body:string} With no such template, no subject, so nothing is sent.
	 */
	private static function rendered( $id, array $values ) {
		$mail = WPCPM_Mail_Templates::render( $id, $values );

		if ( null === $mail ) {
			return array(
				'subject' => '',
				'body'    => '',
			);
		}

		return $mail;
	}
```

- [ ] **Step 5: The consent request**

In `includes/modules/class-wpcpm-semester-report-screen.php`, replace the method `ask_message()`, its docblock included, with:

```php
	/**
	 * The consent message: the template registry's `report-consent`, filled with the semester and
	 * the student's dashboard, where the two questions are.
	 *
	 * **It names no institution and quotes nothing.** The student knows which school they
	 * are at, and a message repeating a sentence they wrote about their term back at them,
	 * in mail, to prompt them to release it, would be the site using their words to ask
	 * permission to use their words.
	 *
	 * @param string $cohort The cohort key, for the semester the report covers.
	 * @return callable A builder for `WPCPM_Mail::send()`. It is handed the recipient and
	 *                  takes no argument: the message says nothing about the person it is
	 *                  written to beyond what their own locale does to the wording.
	 */
	private static function ask_message( $cohort ) {
		return function () use ( $cohort ) {
			return self::rendered(
				self::MAIL_CONTEXT,
				array(
					'semester'       => WPCPM_Cohort::label( $cohort ),
					'dashboard_link' => class_exists( 'WPCPM_Students_Dashboard' ) ? WPCPM_Students_Dashboard::page_url() : '',
				)
			);
		};
	}
```

- [ ] **Step 6: Run them to verify they pass**

Run, one per command: `php bin/test-calls-reports-text.php`, `php bin/test-semester-report.php`, `php -l includes/modules/class-wpcpm-semester-report-screen.php`
Expected: `ALL PASS (398 checks)`: every email of both areas is the recording, byte for byte, but the one line ending of the ten; `ALL PASS (534 checks)`; `No syntax errors detected in includes/modules/class-wpcpm-semester-report-screen.php`.

- [ ] **Step 7: Run the whole battery**

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 8: Commit**

```bash
git add includes/modules/class-wpcpm-semester-report-screen.php bin/test-calls-reports-text.php bin/test-semester-report.php
git commit -m "Emails E3: the Semester reports emails built from the registry, word for word"
```

---


### Task 5: The editor, the preview and Send me a test for every new template

The editor, its preview, Save and Send me a test are 1.122.19's, and take any template the registry holds (ruling E3-6): this task proves it for each of the twenty-two through the real tool, the real registry and the real mail layer, with an Administrator who keeps Tokyo's clock, pins the refusals the new placeholders bring, and saves, tests and restores an edit of one of them. It changes no code: its checks pass at once, which is the proof that nothing on the screen had to change.

**Files:**
- Modify: `bin/test-emails-templates.php` (the stand-ins a call's examples read; the dashboards of a mentor and of an institution; a block for the two areas)

**Interfaces:**
- Consumes: `WPCPM_Emails::render_admin_page()`, `::handle_save()` (its second button, `wpcpm_then=test`), the templates and examples of Task 2, `WPCPM_Mail_Templates::send_test()`.
- Produces: nothing in code; the suite's new block.

- [ ] **Step 1: The checks**

The suite's stand-ins learn a date on a given clock (`wp_date()` takes a zone, as core's does), the site's time zone and the site's date and time formats, and how long until a time; it loads the classes a call's examples are worked out with; and the Mentor Report Card and the Institution Dashboard are published, as the Student Report Card and the Administrator Dashboard already are. The new block opens the mentor's booking confirmation as an Administrator keeping Tokyo's clock, its placeholders, the two required ones marked, and its preview; refuses three saves of it (without the call's time, with the meeting link in the subject, with the typed topic in the subject); saves an edit of it, sends its test and restores it; and opens every new template's editor and presses its Send me a test as drawn:

In `bin/test-emails-templates.php`, replace:

```php
 *   to the presser's own address and to nobody a press names, and only for the text as saved:
 *   pressed with typing not saved yet, it sends nothing and gives the typing back as a refused save
 *   does.
 *
 * Run from the plugin root:  php bin/test-emails-templates.php
 */
```

with:

```php
 *   to the presser's own address and to nobody a press names, and only for the text as saved:
 *   pressed with typing not saved yet, it sends nothing and gives the typing back as a refused save
 *   does.
 * - **The Mentor calls and the Semester reports** work in the same editor, with the same buttons:
 *   each email's preview and test are filled as a real email to the presser would be, a call
 *   tomorrow at 3 pm on the presser's own clock among them; a save without a call's time, or with a
 *   meeting link or a typed topic in the subject, is refused; and an edit is saved, tested and
 *   restored.
 *
 * Run from the plugin root:  php bin/test-emails-templates.php
 */
```

In `bin/test-emails-templates.php`, replace:

```php
class RedirectSignal extends Exception {}
class DieSignal extends Exception {}

$GLOBALS['opts']   = array( 'timezone_string' => 'Europe/Warsaw' );
$GLOBALS['umeta']  = array();
$GLOBALS['hooked'] = array();
$GLOBALS['mail']   = array();
```

with:

```php
class RedirectSignal extends Exception {}
class DieSignal extends Exception {}

$GLOBALS['opts']   = array(
	'timezone_string' => 'Europe/Warsaw',
	'date_format'     => 'F j, Y',
	'time_format'     => 'g:i a',
);
$GLOBALS['umeta']  = array();
$GLOBALS['hooked'] = array();
$GLOBALS['mail']   = array();
```

In `bin/test-emails-templates.php`, replace:

```php
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function wp_timezone() { return new DateTimeZone( (string) get_option( 'timezone_string', 'UTC' ) ); }
function wp_date( $format, $timestamp = null ) { return ( new DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( wp_timezone() )->format( $format ); }
function add_action( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['hooked'][] = $h; return true; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { return true; }
function apply_filters( $h, $v ) { return $v; }
```

with:

```php
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function wp_timezone_string() { return (string) get_option( 'timezone_string', 'UTC' ); }
function wp_timezone() { return new DateTimeZone( wp_timezone_string() ); }
function wp_date( $format, $timestamp = null, $zone = null ) { return ( new DateTimeImmutable( '@' . (int) ( null === $timestamp ? time() : $timestamp ) ) )->setTimezone( $zone instanceof DateTimeZone ? $zone : wp_timezone() )->format( $format ); }
function human_time_diff( $from, $to = 0 ) { return round( abs( (int) $to - (int) $from ) / HOUR_IN_SECONDS ) . ' hours'; }
function add_action( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['hooked'][] = $h; return true; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { return true; }
function apply_filters( $h, $v ) { return $v; }
```

In `bin/test-emails-templates.php`, replace:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-capture.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-emails.php';

/** The dashboards, each published at an address of its own. */
class WPCPM_Students_Dashboard {
```

with:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-capture.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-emails.php';
// What the Mentor calls' and the Semester reports' examples are worked out with, as their emails are.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-availability.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-calls.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-semester-report-screen.php';

/** The dashboards, each published at an address of its own. */
class WPCPM_Students_Dashboard {
```

In `bin/test-emails-templates.php`, replace:

```php
class WPCPM_Administrators_Dashboard {
	public static function page_url() { return 'https://example.test/administrator-dashboard/'; }
}

$fails = 0;
$total = 0;
```

with:

```php
class WPCPM_Administrators_Dashboard {
	public static function page_url() { return 'https://example.test/administrator-dashboard/'; }
}
/** As above. */
class WPCPM_Mentors_Dashboard {
	public static function page_url() { return 'https://example.test/mentor-report-card/'; }
}
/** As above. */
class WPCPM_Institutions_Dashboard {
	public static function page_url() { return 'https://example.test/institution-dashboard/'; }
}

$fails = 0;
$total = 0;
```

In `bin/test-emails-templates.php`, replace:

```php
$GLOBALS['mail'] = array();
ck( 'a press naming an email the screen does not edit goes to the list, saying so, and sends nothing', array( press( 'handle_save', array( 'wpcpm_email' => 'call-booked', 'wpcpm_then' => 'test' ) ), $GLOBALS['mail'], false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails' ) ), 'Nothing was sent: the site has no such email.' ) ), array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates', array(), true ) );

echo "\n=== The Signature and footer tab ===\n";

new_administrator();
```

with:

```php
$GLOBALS['mail'] = array();
ck( 'a press naming an email the screen does not edit goes to the list, saying so, and sends nothing', array( press( 'handle_save', array( 'wpcpm_email' => 'call-booked', 'wpcpm_then' => 'test' ) ), $GLOBALS['mail'], false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails' ) ), 'Nothing was sent: the site has no such email.' ) ), array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates', array(), true ) );

echo "\n=== The Mentor calls and the Semester reports ===\n";

// An Administrator who keeps Tokyo's clock opens the mentor's booking confirmation: an editor as an
// invitation's, its placeholders the call's, the call's time and the meeting link required, and a
// preview of a call tomorrow at 3 pm on that clock.
$tokyo                                                  = new DateTimeZone( 'Asia/Tokyo' );
$tomorrow                                               = ( new DateTimeImmutable( 'tomorrow 15:00', $tokyo ) )->getTimestamp();
$in_tokyo                                               = new_administrator();
$GLOBALS['umeta'][ $in_tokyo->ID ]['wpcpm_timezone']    = 'Asia/Tokyo';
$booking                                                = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'call-booked-mentor' ) );
preg_match_all( '#<tr><td><code>\{([a-z_]+)\}</code>( <span class="wpcpm-emails__required">Required</span>)?</td>#', $booking, $marks, PREG_SET_ORDER );
preg_match( '#<div class="wpcpm-emails__preview"><p><strong>Subject:</strong> ([^<]*)</p><pre class="wpcpm-emails__text">(.*?)</pre></div>#s', $booking, $booked );
ck(
	'the mentor\'s booking confirmation says who receives it and what sends it, and marks the call\'s time and the meeting link required',
	array( false !== strpos( words_of( $booking ), 'To: Mentor. ' . WPCPM_Mail_Templates::get( 'call-booked-mentor' )['trigger'] ), array_map( function ( $mark ) { return $mark[1] . ( empty( $mark[2] ) ? '' : ' (required)' ); }, $marks ) ),
	array( true, array( 'site_name', 'student_name', 'when (required)', 'time_zone', 'meeting_place (required)', 'topic', 'dashboard_link' ) )
);
ck(
	'and its preview is the email as a mentor reads it, the call tomorrow at 3 pm on the Administrator\'s own clock, the Mentor Report Card named once, and the footer',
	array( html_entity_decode( $booked[1] ?? '', ENT_QUOTES ), html_entity_decode( $booked[2] ?? '', ENT_QUOTES ) ),
	array(
		'[WordPress Credits] Call booked with Ada Okonjo',
		'Ada Okonjo booked a call with you on ' . wp_date( 'F j, Y', $tomorrow, $tokyo ) . " 3:00 pm to 3:30 pm.\n\nTimes are shown in Asia / Tokyo.\n\nWhere you will meet:\nhttps://meet.example.test/ben-lindqvist\n\nWhat the student would like to discuss:\nMy first plugin, and how to get it reviewed\n\nhttps://example.test/mentor-report-card/\n\n-- \nWordPress Credits, WordPress Education\nThis email comes from the WordPress Credits program.",
	)
);

// A save that drops the call's time, or puts the meeting link, which can carry the meeting's passcode,
// or the topic the student typed in the subject the Log keeps, is refused with its sentence, and the
// typing comes back.
$shipped_booking = WPCPM_Mail_Templates::get( 'call-booked-mentor' );
$refused_saves   = array();
foreach ( array(
	'the call\'s time dropped'              => array( $shipped_booking['subject'], str_replace( ' on {when}', '', $shipped_booking['body'] ) ),
	'the meeting link in the subject'       => array( $shipped_booking['subject'] . ' {meeting_place}', $shipped_booking['body'] ),
	'the typed topic in the subject'        => array( $shipped_booking['subject'] . ': {topic}', $shipped_booking['body'] ),
) as $what => $typed ) {
	new_administrator();
	press( 'handle_save', array( 'wpcpm_email' => 'call-booked-mentor', 'subject' => $typed[0], 'body' => $typed[1] ) );
	$back                   = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'call-booked-mentor' ) );
	$refused_saves[ $what ] = array( preg_match( '#<div class="notice notice-error is-dismissible"><p>([^<]*)</p></div>#', $back, $said ) ? html_entity_decode( $said[1], ENT_QUOTES ) : null, box( $back, 'wpcpm-email-subject' ) === $typed[0] && box( $back, 'wpcpm-email-body' ) === $typed[1] );
}
ck(
	'a save of a call\'s email without its time, or with its meeting link or a typed topic in the subject, is refused, stores nothing, and gives the typing back',
	array( $refused_saves, WPCPM_Mail_Templates::edit( 'call-booked-mentor' ) ),
	array(
		array(
			'the call\'s time dropped'        => array( 'Nothing was saved: this email needs {when}.', true ),
			'the meeting link in the subject' => array( 'Nothing was saved: {meeting_place} goes in the body, never in the subject.', true ),
			'the typed topic in the subject'  => array( 'Nothing was saved: {topic} goes in the body, never in the subject.', true ),
		),
		null,
	)
);

// A real edit of it saves, is what its test sends, and Restore the original drops it again, as for
// every email.
$edited = array(
	'subject' => $shipped_booking['subject'],
	'body'    => $shipped_booking['body'] . "\n\nSee you there.",
);
new_administrator();
press( 'handle_save', array( 'wpcpm_email' => 'call-booked-mentor' ) + $edited );
$was_edited      = WPCPM_Mail_Templates::state( 'call-booked-mentor' )['state'];
$GLOBALS['mail'] = array();
press( 'handle_save', array( 'wpcpm_email' => 'call-booked-mentor', 'wpcpm_then' => 'test' ) + $edited );
$edit_test = $GLOBALS['mail'][0]['body'] ?? '';
press( 'handle_restore', array( 'wpcpm_email' => 'call-booked-mentor' ) );
ck(
	'a real edit of it is saved, its test sends the edit, and Restore the original drops it',
	array( $was_edited, false !== strpos( $edit_test, "\n\nSee you there.\n\n-- \nWordPress Credits, WordPress Education\n" ), WPCPM_Mail_Templates::edit( 'call-booked-mentor' ), WPCPM_Mail_Templates::state( 'call-booked-mentor' )['state'] ),
	array( 'edited', true, null, 'shipped' )
);

// Every email of the two areas, through its editor and its Send me a test, as an Administrator who
// keeps Tokyo's clock opens and presses them.
$works = array();
$wants = array();
foreach ( WPCPM_Mail_Templates::all() as $id => $template ) {
	if ( WPCPM_Mail_Catalog::MODULE_INVITATIONS === $template['module'] ) {
		continue;
	}

	$presser                                            = new_administrator();
	$GLOBALS['umeta'][ $presser->ID ]['wpcpm_timezone'] = 'Asia/Tokyo';
	$page                                               = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => $id ) );
	$GLOBALS['mail']                                    = array();
	$went                                               = press( 'handle_save', array( 'wpcpm_email' => $id, 'wpcpm_then' => 'test', 'subject' => $template['subject'], 'body' => str_replace( "\n", "\r\n", $template['body'] ), 'to' => 'someone@example.test' ) );
	$test                                               = $GLOBALS['mail'];

	preg_match( '#<div class="wpcpm-emails__preview">(.*?)</div>#s', $page, $shown );
	$works[ $id ] = array(
		'editor'  => false !== strpos( $page, '<h2>' . esc_html( $template['label'] ) . '</h2>' ) && false !== strpos( $page, '>Save</button>' ) && false !== strpos( $page, 'value="test">Send me a test</button>' ),
		'preview' => isset( $shown[1] ) ? WPCPM_Mail_Templates::placeholders_in( html_entity_decode( $shown[1], ENT_QUOTES ) ) : null,
		'test'    => array( $went, count( $test ), $test[0]['to'] ?? null, 0 === strpos( $test[0]['subject'] ?? '', '[Test] ' ), $test[0]['context'] ?? null, $test[0]['test'] ?? null ),
	);
	$wants[ $id ] = array(
		'editor'  => true,
		'preview' => array(),
		'test'    => array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=' . $id, 1, $presser->user_email, true, $id, true ),
	);

	if ( 'series-joined-student' === $id ) {
		$series_test = $test[0]['body'] ?? '';
	}
}
ck( 'every email of the Mentor calls and the Semester reports opens in the editor, with Save and Send me a test, its preview filled; and its test, pressed as drawn, is one email, to the presser alone, marked [Test] and Test, under its own id', $works, $wants );
ck(
	'a series\' test names its three weekly sessions on the presser\'s clock, one to a line',
	false !== strpos(
		$series_test ?? '',
		"You are on 3 group sessions with Ben Lindqvist:\n" . implode(
			"\n",
			array_map(
				function ( $days ) use ( $tomorrow, $tokyo ) {
					return wp_date( 'F j, Y', $tomorrow + $days * DAY_IN_SECONDS, $tokyo ) . ' 3:00 pm to 3:30 pm';
				},
				array( 0, 7, 14 )
			)
		) . "\n\nTimes are shown in Asia / Tokyo."
	),
	true
);

echo "\n=== The Signature and footer tab ===\n";

new_administrator();
```

- [ ] **Step 2: Run it: it passes at once**

Run: `php bin/test-emails-templates.php`
Expected: `ALL PASS (67 checks)`. Each of the twenty-two opens with its name, Save and Send me a test, and a preview with no placeholder left; each test is one email, to the presser's own address whatever the press names, its subject after "[Test] ", under its own id, marked Test; the series' test names its three sessions on the presser's clock; a save without `{when}` is refused "Nothing was saved: this email needs {when}.", one with `{meeting_place}` in the subject "Nothing was saved: {meeting_place} goes in the body, never in the subject.", and one with `{topic}` in it "Nothing was saved: {topic} goes in the body, never in the subject.", each storing nothing and giving the typing back; an edit adding "See you there." is saved as Edited, its test carries the sentence above the footer, and Restore the original leaves the email Shipped text. Nothing in the screen's code changed for any of it.

- [ ] **Step 3: Run the whole battery**

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 4: Commit**

```bash
git add bin/test-emails-templates.php
git commit -m "Emails E3: every Mentor calls and Semester reports email in the editor, its preview and its test"
```

---


### Task 6: The guide, the version, the changelog and the template

**Files:**
- Modify: `docs/sections/32-admin-tools.md` (the Emails part: Templates, the editor, Save, the Log)
- Regenerate: `docs/administrators.md` and `docs/build/administrators.html` with `php bin/build-docs.php` (the Administrator guide is the one that holds the Tools part; the student, mentor and sponsor guides do not change)
- Modify: `wpcredits-program-manager.php` (`Version:` at line 6, `WPCPM_VERSION` at line 22), `readme.txt` (`Stable tag` at line 7, the 1.122.21 entry above 1.122.20's)
- Regenerate: `languages/wpcredits-program-manager.pot` with `sh bin/make-pot.sh`

**Interfaces:**
- Consumes: the strings Tasks 2 to 4 added, which the template collects.
- Produces: nothing new in code; the guide, version 1.122.21, its changelog entry and the template.

- [ ] **Step 1: The guide's Emails part**

The guide calls the menu path "WPCredits Program > Tools > Emails" and says **Area**, never module. The Templates part names the three areas and what each holds, and says since when each email's shipped text is what it said before; the editor's part says how a call's times and counts arrive and which placeholders a save must keep and keep out of the subject, and what an edit's subject that fills to nothing is sent as; the Log's part says what the rows of before 1.122.21 are named:

In `docs/sections/32-admin-tools.md`, replace:

```markdown

#### Templates

The plugin's emails, grouped by area. In this version the area is **Invitations**: the student's,
the mentor's and the sponsor's invitation, and the institution's two, one for an institution whose
Collaboration Agreement is not on file yet and one for an institution whose agreement is on file.
Each row names the email, who receives it and what sends it, its state, and who edited it last and
when. The states:

- **Shipped text**: the email is sent as the plugin ships it, which is word for word what it said
  before 1.122.19.
- **Edited**: it is sent as an Administrator wrote it, to everybody, in that wording.
- **Edited, and the shipped text has changed since**: an update changed the shipped text after the
  edit was saved. The edit is still the one sent, and its editor opens the new shipped text under
```

with:

```markdown

#### Templates

The plugin's emails, grouped by area, each area in a table of its own. In this version there are
three:

- **Invitations**: the student's, the mentor's and the sponsor's invitation, and the institution's
  two, one for an institution whose Collaboration Agreement is not on file yet and one for an
  institution whose agreement is on file.
- **Mentor calls**: what the mentor and the students are told about one-to-one calls, group sessions
  and series of sessions: a booking, a join, a series joined, a session moved, a place left or
  released, a call or a session canceled, and the reminder a day before. Where the mentor and the
  student are told different words, each has an email of its own, named for whom it goes to; a
  group session's cancellation and its reminder say the same words to the mentor and to every
  student on it, so each is one email. A mentor who has not set a meeting link yet is told so where
  the others are told where to meet, so the mentor's three confirmations, of a call, of a join and of
  a series, each have a second email for that case.
- **Semester reports**: the notice to the Administrators that the daily job drafted a report, the
  approval to the institution's accounts, and the question to the students whether the report may
  mention them.

The emails of the Institutions and the Sponsors areas are not in the list yet, and go out as the
plugin writes them. Each row names the email, who receives it and what sends it, its state, and who
edited it last and when. The states:

- **Shipped text**: the email is sent as the plugin ships it, which is word for word what it said
  before it could be edited: before 1.122.19 for the Invitations, before 1.122.21 for the Mentor
  calls and the Semester reports.
- **Edited**: it is sent as an Administrator wrote it, to everybody, in that wording.
- **Edited, and the shipped text has changed since**: an update changed the shipped text after the
  edit was saved. The edit is still the one sent, and its editor opens the new shipped text under
```

In `docs/sections/32-admin-tools.md`, replace:

```markdown
with no contact address set, the whole of it goes, "Thanks!" with it, and in "e.g. " the full stop
ends a sentence.

- **Save** stores the subject and the body. It is refused, nothing is stored, and the editor comes
  back with what you typed, when a box is empty, a required placeholder is missing (every invitation
  needs `{set_password_link}`, the link that sets the password, in its body, never in the subject:
  a subject is not a link anybody can follow, and the Log keeps it), a placeholder is not one of the
  email's (a misspelled one is named as one the email does not have; a misspelled
  `{set_password_link}` is reported as the missing link instead), WordPress would remove part of the
  text, or the subject is longer than 200 characters or the body longer than 10,000. A save of the
  shipped text as it stands stores nothing, so the email stays shipped text.
- **Send me a test**, beside **Save**, sends the email as it is saved, filled with the example values
  and with "[Test]" before its subject, to your own address and to nobody else. The Log marks it
  **Test**. Save what you typed first: pressed with typing not saved yet, it sends nothing, and the
```

with:

```markdown
with no contact address set, the whole of it goes, "Thanks!" with it, and in "e.g. " the full stop
ends a sentence.

An email about a call shows every time on its recipient's own clock: `{when}` is the call's start
and end as the recipient reads them, and `{time_zone}` names that clock, so a mentor in Tokyo and a
student in Warsaw each read the call at their own hour. A count arrives in words, such as
`{session_count}`, "3 sessions", so no template needs a rule for one and several.

- **Save** stores the subject and the body. It is refused, nothing is stored, and the editor comes
  back with what you typed, when a box is empty, a required placeholder is missing (every invitation
  needs `{set_password_link}`, the link that sets the password; every email about a call needs
  `{when}`, its time (a series, `{session_dates}`), the mentor's confirmation sent with a meeting
  link needs `{meeting_place}`, and a semester report `{semester}`), a link or a typed text that goes
  in the body is in the subject (`{set_password_link}`; a mentor's meeting link, `{meeting_place}`,
  which can carry the meeting's passcode; and what a student or a mentor typed, `{topic}` and
  `{session_topic}`: a subject is not a link anybody can follow, and the Log keeps it), a placeholder
  is not one of the email's (a misspelled one is named as one the email does not have; a misspelled
  required one is reported as missing instead), WordPress would remove part of the text, or the
  subject is longer than 200
  characters or the body longer than 10,000. A save of the shipped text as it stands stores nothing,
  so the email stays shipped text. A subject made of placeholders alone, such as `{student_name}`,
  is saved; if they all come out empty, the email goes out with its shipped subject rather than with
  none, since an email with no subject is not sent.
- **Send me a test**, beside **Save**, sends the email as it is saved, filled with the example values
  and with "[Test]" before its subject, to your own address and to nobody else. The Log marks it
  **Test**. Save what you typed first: pressed with typing not saved yet, it sends nothing, and the
```

In `docs/sections/32-admin-tools.md`, replace:

```markdown
list by **Area**, **Recipient type**, **Status**, **Email** or **When** (today, the last 7 days, the
last 30 days, or from and to dates), and press **Filter**. The **Email** filter is one field: type
a few letters of the email's name and pick it from the list. Fifty rows show a page, newest first,
with the count above them.

The log never keeps the text of an email, its attachments, or anything secret such as a sign-in
code or a password link. Entries older than 30 days are deleted every day. WordPress's **Export
```

with:

```markdown
list by **Area**, **Recipient type**, **Status**, **Email** or **When** (today, the last 7 days, the
last 30 days, or from and to dates), and press **Filter**. The **Email** filter is one field: type
a few letters of the email's name and pick it from the list. Fifty rows show a page, newest first,
with the count above them. Until 1.122.21 the Mentor calls emails were named by five names that each
covered the mentor's copy and the students' alike, such as **Call booked** and **Call reminder**;
the rows sent before keep those names, which now end "(until 1.122.21)", for the 30 days the log
keeps them.

The log never keeps the text of an email, its attachments, or anything secret such as a sign-in
code or a password link. Entries older than 30 days are deleted every day. WordPress's **Export
```

Run: `php bin/build-docs.php` (the plain build, so the committed guides keep their relative image paths), then, one per command, `php bin/test-cli.php`, `php bin/check-spelling.php`, `php bin/test-settings.php`, `php bin/test-admin-menu.php`
Expected: the build prints a line per guide and `Done.`, and `git status --short docs/` lists `docs/administrators.md`, `docs/build/administrators.html` and the section alone; `ALL PASS (14 checks)`; `... US English throughout.`; `ALL PASS`; `ALL PASS` (no guide section says module or program manager, and every menu path steps from WPCredits Program with ">").

- [ ] **Step 2: The version and the entry**

In `wpcredits-program-manager.php`, the header (line 6) and the constant (line 22):

In `wpcredits-program-manager.php`, replace:

```php
 * Plugin Name:       WPCredits Program Manager
 * Plugin URI:        https://github.com/gomp/wpcredits-program-manager
 * Description:       Runs the WPCredits program on WordPress for five audiences - Students, Mentors, Institutions, Sponsors and Administrators - plus a Tools section. Provisions role-based accounts from Airtable, gives each mentor a Mentor Report Card listing the students assigned to them, and includes the Mentor Status Checker.
 * Version:           1.122.20
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Maciej Pilarski
```

with:

```php
 * Plugin Name:       WPCredits Program Manager
 * Plugin URI:        https://github.com/gomp/wpcredits-program-manager
 * Description:       Runs the WPCredits program on WordPress for five audiences - Students, Mentors, Institutions, Sponsors and Administrators - plus a Tools section. Provisions role-based accounts from Airtable, gives each mentor a Mentor Report Card listing the students assigned to them, and includes the Mentor Status Checker.
 * Version:           1.122.21
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Maciej Pilarski
```

In `wpcredits-program-manager.php`, replace:

```php
	exit; // No direct access.
}

define( 'WPCPM_VERSION', '1.122.20' );
define( 'WPCPM_PLUGIN_FILE', __FILE__ );
define( 'WPCPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPCPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
```

with:

```php
	exit; // No direct access.
}

define( 'WPCPM_VERSION', '1.122.21' );
define( 'WPCPM_PLUGIN_FILE', __FILE__ );
define( 'WPCPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPCPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
```

In `readme.txt`, the stable tag (line 7) and the entry above 1.122.20's. The entry names the ids it means by `call-booked` alone, since the spelling check reads the readme whole and the ids keep the code's spelling of "cancelled":

In `readme.txt`, replace:

```text
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.122.20
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
```

with:

```text
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.122.21
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
```

In `readme.txt`, replace:

```text

== Changelog ==

= 1.122.20 =

* Every screen, email and guide now says Administrator where it said program manager, or just manager for the same people ("Nothing is waiting for an Administrator right now", "drafted by an Administrator"). That covers the Settings screen, the Tools, the Administrators screen, the Institution Dashboard, the Sponsor Dashboard, the notices, and the emails to students, mentors, institutions, sponsors and applicants ("An Administrator reads every application by hand", "Ask an Administrator"), and the student, mentor, Administrator and sponsor guides. Each sentence means what it meant.
```

with:

```text

== Changelog ==

= 1.122.21 =

* WPCredits Program > Tools > Emails, Templates: the Mentor calls and the Semester reports emails can be edited too, each with who receives it, what sends it, its state, Send me a test and Restore the original, as the invitations can. Their shipped text is word for word what they said before; the one line that changes is in the drafted and approved report emails, when the Institution Dashboard page is not published, which no longer leave a second blank line above the signature and footer.
* Mentor calls: where the mentor and the student are told different words, each now has an email of its own, named for whom it goes to (such as "Call booked, to the mentor" and "Call booked, to the student"), and a one-to-one call and a group session each have their own. A group session's cancellation and its reminder say the same words to the mentor and to every student, so each is one email. The mentor's confirmations of a call, a join and a series each have a second email for a mentor who has not set a meeting link yet. Every time stays on its recipient's own clock.
* Every email about a call needs its time, `{when}`, or a series its dates, `{session_dates}`, and a semester report its semester, `{semester}`; the mentor's confirmation sent with a meeting link needs `{meeting_place}`. A mentor's meeting link, `{meeting_place}`, and what a student or a mentor typed, `{topic}` and `{session_topic}`, go in the body, never in the subject, since a meeting link can carry the meeting's passcode and the Log keeps every subject.
* A mentor who is also a student is sent the emails written for the mentor (a booking, a join, a series, a move, a call's cancellation and its reminder) as a mentor: the Log reads them as one, and the signature and footer no longer adds the Student Report Card under a body that names the Mentor Report Card. A group session's cancellation and reminder, which say the same words to the mentor and to the students, are read by the account as before.
* An edited subject made of placeholders alone that all come out empty is sent as the shipped subject, so the email still goes out.
* The Log names the rows the Mentor calls left before this version by the five names they were sent under then, which end "(until 1.122.21)", such as "Call booked (until 1.122.21)", for the 30 days it keeps them.
* For developers: the registry's areas gain includes/mail/templates/calls.php and reports.php, and `WPCPM_Mail_Templates::all()` is built once a request for each language. `WPCPM_Mail_Catalog` gains the nineteen Mentor calls ids; the five the area was sent under until this version, `call-booked` among them, stay, to name the rows they left, and nothing sends them now. The `wpcpm_mail` filter is handed the new ids as `$context`; a filter keyed on `call-booked` and the four others no longer matches. In `WPCPM_Mentor_Calls`, `mail_body()` is `calendar_text()`, the calendar file's description alone, and `series_mail_body()` is gone. `bin/test-calls-reports-text.php` holds every Mentor calls and Semester reports email to a recording made from 1.122.20 before the move.

= 1.122.20 =

* Every screen, email and guide now says Administrator where it said program manager, or just manager for the same people ("Nothing is waiting for an Administrator right now", "drafted by an Administrator"). That covers the Settings screen, the Tools, the Administrators screen, the Institution Dashboard, the Sponsor Dashboard, the notices, and the emails to students, mentors, institutions, sponsors and applicants ("An Administrator reads every application by hand", "Ask an Administrator"), and the student, mentor, Administrator and sponsor guides. Each sentence means what it meant.
```

- [ ] **Step 3: The template**

Run: `sh bin/make-pot.sh`
Expected: `Success: POT file successfully generated.` The new strings arrive in the template, each with its translators comment, among them the nineteen Mentor calls names ("Call booked, to the mentor"), the five names of before with " (until 1.122.21)" after them ("Call booked (until 1.122.21)"), the triggers and the placeholders' meanings, the counted words (`%d session` and `%d sessions`, `%d group session`, `%d date`, `%s student in this semester was`), the subjects that took a counted value (`[%1$s] %2$s joined your series: %3$s`), and `%s still in progress when the draft was written.`; the labels above a line the registry can leave out carry their comments. Ten strings leave it. Five are the five names of before without their ending (`Call booked`, `Joined a session series`, `Group session moved`, `Call canceled`, `Call reminder`), which come back with " (until 1.122.21)" after them. Five are each one `_n()` worded whole, whose counted words are a value now: the series' two subjects (`[%1$s] %2$s joined your series: %3$d session`, `[%1$s] Group sessions with %2$s: %3$d date`), its two first lines (`%1$s joined %2$d group session of yours:`, `You are on %2$d group session with %1$s:`) and the draft's count (`%s student in this semester was still in progress when the draft was written.`), each with its plural.

- [ ] **Step 4: Run the release's suites, the standards and the dashes**

Run, one per command: `php bin/test-tooling.php`, `php bin/check-spelling.php`, `php bin/test-fixtures.php`, `php bin/test-admin-menu.php`, `php bin/test-mail-templates.php`, `php bin/test-emails-templates.php`, `php bin/test-calls-reports-text.php`, `php bin/test-invitation-text.php`, `sh bin/check-standards.sh`
Expected: `ALL PASS`; `... US English throughout.`; `ALL PASS`; `ALL PASS`; `ALL PASS (81 checks)`; `ALL PASS (67 checks)`; `ALL PASS (398 checks)`; `ALL PASS (40 checks)`; the standards' last line reads `2 errors and 85 warnings. The standard is the gate: fix the errors.`, main's count, both errors main's own (in `includes/class-wpcpm-mail.php` and `includes/modules/class-wpcpm-call-calendar.php`), none in a line this release writes. Then search the release's diff for dashes, with perl, since macOS's grep has no `-P` and would print nothing whatever the diff holds: `git diff main -- . | perl -CSD -ne 'print if /[\x{2013}\x{2014}]/'` prints nothing.

- [ ] **Step 5: Run the whole battery**

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 6: Commit**

```bash
git add docs/ wpcredits-program-manager.php readme.txt languages/wpcredits-program-manager.pot
git commit -m "1.122.21: the Mentor calls and Semester reports emails in Tools > Emails; the guide, the version, the changelog and the template"
```

---


## After the tasks (the controller's, not an implementer's)

1. The whole-release review, one fix wave, a scoped re-review.
2. The gate on a frozen copy: no conflict markers, `php -l` on every file, `php bin/check-references.php`, the whole battery (`for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done`, printing nothing), `sh bin/check-standards.sh` with no more errors than 1.122.20 (2), and `php bin/check-dead-annotations.php` with none dead, all green. `php bin/test-calls-reports-text.php` passing is the word-for-word proof: every Mentor calls and Semester reports email is the recording of 1.122.20, but the one line ending of ten report emails with no Institution Dashboard page.
3. The zip from a clean clone (`bash bin/build ~/GitHub/wpcredits-program-manager.zip`).
4. The local copy (localhost:8883): snapshot the database; install; then try, as an Administrator whose profile keeps a time zone other than the site's:
   - WPCredits Program > Tools > Emails opens on **Templates**: three areas, Invitations (five rows), Mentor calls (nineteen) and Semester reports (three), each Shipped text and Not edited, each Edit opening its editor, and at 375px each table scrolling in its own box while the page does not.
   - On **Call booked, to the mentor**: the preview reads as a mentor's booking of a call tomorrow at 3 pm on your own clock, your time zone named, then the footer with no second copy of the Mentor Report Card's address. **Send me a test**: one email, to your own address, "[Test] " before the subject; in the Log, **Not confirmed** (the copy's safety plugin takes every email over through `pre_wp_mail`), marked **Test**, as "Call booked, to the mentor", area Mentor calls. Save refused, each with its sentence and with what you typed still in the boxes: ` on {when}` deleted from the first line ("this email needs {when}"); `{meeting_place}` added to the subject ("goes in the body, never in the subject").
   - With a test mentor and a test student of the copy: book a one-to-one call, with the mentor's meeting link set and then cleared; join one of the mentor's group sessions; join a series of two; move the session as an Administrator; cancel the call as the student and the session as the mentor; leave a session; and, for a call within the next day booked earlier, run the reminder sweep (`studio wp cron event run wpcpm_send_call_reminders`). Read each email in the copy's safety log beside today's (the words unchanged, every time on its reader's clock, the calendar file attached), and in the Log under its new name, the mentor's as Mentor and the student's as Student.
   - Edit **Call reminder, to the student** (add a sentence), Save, and send it again with the sweep: the edit arrives; then **Restore the original**, which asks first.
   - Semester reports: run the daily job for a report that is due (`studio wp cron event run wpcpm_report_autodraft`), approve the draft, and ask its students for consent; read the three emails in the safety log and the Log ("Semester report drafted", "Semester report approved", "Consent for the semester report").
   - The Log's **Email** field lists the nineteen new names beside "Call booked (until 1.122.21)" and the four other names of before, which end the same way, and filters by each.
   Put the database back.
5. The mirror push and the live deploy on the owner's yes, with the usual recipe, the live version read first (`wp plugin get wpcredits-program-manager --field=version` reads 1.122.20). After the install: the Templates tab lists the three areas as Shipped text, `wp option get wpcpm_mail_templates` holds nothing new, and one Send me a test of a Mentor calls template arrives with "[Test] " and the footer and shows in the Log as **Handed to the mail server**, marked Test, pressed from the Administrator account whose address is the one every test mail on the site goes to (the owner's rule), checked first with `wp user get <id> --field=user_email`. The build changes the Administrator guide alone, so its page (560) is republished from a `--base=` build; the rulings name pages 558, 559 and 560 for a release whose guides change, and the student and mentor guides' pages stay as they are, since their build is unchanged. One read-only check before the install: nothing on the live site keys on the five old ids through the `wpcpm_mail` filter (the site's mu-plugins and its code snippets searched for `wpcpm_mail`), since from this version the filter is handed the new ids as `$context`.
6. For the owner:
   - **Asked: the report's links required?** Ruling E3-5 names "the report's link" as required. This plan keeps `{review_link}` and `{report_link}` optional, as the code treats them: the registry never leaves out a line holding a required placeholder (the spec's rule, pinned since 1.122.19), so with the Institution Dashboard page not published a required link would print "Review it here:" over nothing. Making them required (so no edit can drop them) needs either that cost, or a change to the rule for a required placeholder that is empty when the email is sent (bin/test-mail-templates.php pins today's rule in three checks). What E3-5 wants, that no edit can drop the link, is a save rule, not a send rule: a later release could add a "kept at save" mark, which refuses a save without the placeholder and still leaves its line out when it is empty at send time, and changes no email. The question covers all four such links at once: `{review_link}` and `{report_link}`, the consent email's `{dashboard_link}`, and the student's `{meeting_place}` in the booking, the join, the series and the reminder, each optional for the same reason. The live site has the page published, so neither answer changes an email sent there today.
   - **Chosen, told: the no-link confirmations as their own templates.** A mentor with no meeting link is told so in place of where to meet, so the mentor's three confirmations each have a second template (nineteen Mentor calls templates, where sixteen would do with a rule the registry does not have). A rule for a line said only when a value is empty would be new fill semantics, with a design, tests and guide text of its own; the twins keep E2's proven rules, and their labels say what they are. An Administrator who edits "Call booked, to the mentor" edits its no-link twin separately; a later release can ease that with one line on each twin's editor naming the other.
   - **Told: the templates' names**, which are this plan's suggestions, as the invitations' were. Every label that names a student says "to the student", in the singular, since each student is sent one email ("Group session moved, to the student"), and the five names of before end " (until 1.122.21)", so the Log's Email field tells them from the new ones. The full list is in Task 2's catalog suite; the owner can change any.
   - **Told, not asked: a two-role mentor and the two shared session emails.** They still read such a mentor as a student. A later release can let a builder name the reader's audience (one key of the builder's return, read by `add_footer()` and the capture), which changes no email of a single-role account.
   - **Told, not asked: the ten report emails.** With the Institution Dashboard page not published, whether or not the Administrator Dashboard page is, the semester report drafted and approved emails (ten recorded emails, five cases) lose one blank line above the signature and footer, where E3-3 asked for the same bytes with the footer aside. Today's second blank line came only from the link's line being left out of a body that ended in a blank line; the registry ends every other email with one, as E2's rule has it. The change is pinned (`body_as()` and the check of ten and no other), stated in the readme, and invisible on the live site, where the page is published.
