# Emails E4: the Institutions and the Sponsors in the template registry, Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every email of the Institutions and the Sponsors areas editable under WPCredits Program > Tools > Emails, with its text unchanged: each email that says words of its own becomes a template with a catalog id of its own, built from the registry with the values its sender works out, and shown, edited, previewed and tested in the editor the other three areas already have. It is the Emails tool's last release: after it, every email the plugin sends while it runs is in the Templates tab, every one but the inventory sent when the plugin is uninstalled.

**Architecture:** Two new area files under `includes/mail/templates/`, `institutions.php` (17 templates) and `sponsors.php` (fifteen), join `WPCPM_Mail_Templates::AREAS` in the catalog's order, and the registry now reads each area's file in a scope of its own (`area()`). `WPCPM_Mail_Catalog` gains six ids, for the emails that said other words under an id they shared, and names the institution's agreement emails for the Collaboration Agreement in full. One new registry method, `WPCPM_Mail_Templates::mail()`, hands a builder its email as the mail layer takes it: the registry's subject and body filled with the values, beside what stays in the sender's code (a Reply-To, a plain-text subject). The senders in twelve classes stop writing sentences: each chooses its template, works out its values inside the recipient's language, and returns `mail()`. A recording of every email the two areas send, made from 1.122.21 through the real senders before anything moves, holds the move to the same bytes.

**Tech Stack:** WordPress 7.1 plugin PHP (7.4 and 8.x), the plugin's `bin/test-*.php` suites (plain PHP with stubs), `WPCPM_Mail_Templates` and `WPCPM_Mail_Catalog` as 1.122.21 made them, `WPCPM_Mail::send()`, `send_to()` and `reply_to()`, `WPCPM_Institutions::notify_managers()` and `WPCPM_Sponsor_Interests::mail_manager()` for the recipients, which stay as they are.

**Spec:** `docs/specs/2026-10-10-emails-tool-design.md` (approved by the owner on 10 October 2026). This plan covers its fourth release, "1.122.22: Institutions and Sponsors", and follows the plan rulings of 11 October 2026, E4-1 to E4-8 (`.superpowers/sdd/2026-10-11-emails-e4-plan-rulings.md`), the format and proving method of the previous release's plan, `docs/plans/2026-10-11-emails-e3-calls-reports.md`, its ledger's "Ruling:" lines and the deferred minors E4-6 names (`.superpowers/sdd/2026-10-11-emails-e3-calls-reports/progress.md`, `deferred-minors.md`). 1.122.18 to 1.122.21 are live; main is ffd2711.

**Order:** the recording of what every email of the two areas says today, before anything moves (Task 1); the catalog's new ids and names, the registry's two new areas, their shipped text and examples, each area's file read in a scope of its own, and `mail()` (Task 2); the Institutions built from the registry, word for word (Task 3); the Sponsors built from it (Task 4); the editor's refusals, a preview and an edit of a plain-text subject for the new templates (Task 5); the guide, the version, the changelog and the template (Task 6).

**Where the rulings left a choice, this plan chooses:**

- **E4-1, the emails: 28, not 27.** The two areas' 26 ids send 28 emails from 28 send sites. Two ids send two emails each: `agreement-accepted` is also the id of the agreement put back in force (`WPCPM_Institution_Agreement::mail_reinstated()`), and `sponsor-interest` is also the id of a sponsor's request to sponsor a mentor (`WPCPM_Sponsor_Mentors::handle_interest()`, which calls `WPCPM_Sponsor_Interests::mail_manager()` with its default context). The spec's count of 27 missed the second; this plan moves all 28. The uninstall notice (`WPCPM_Institution_Agreement::manifest_kept_files()`, a direct `wp_mail()`) stays out, as E4-1 says, and nothing of any other area moves.
- **E4-2, which emails are different: any sentence in other words is another email** (E3-2's rule): one template can leave a line out when its value is empty, never put one in or say another. Four emails said other words in one case than in another, so each case is a template: the institution's acknowledgement with a call to book and without one (the sentence introducing the link stands a blank line above it, so the registry's colon rule, which takes a bare colon line directly above a line left out, cannot take it); the notice of an upload with the courtesy scan's notes ("noticed: JavaScript.") and without ("noticed nothing."); the revocation with a country contact named and with the neutral sentence; and the sponsor's acknowledgement with the line saying the logo files were not kept. With the two emails that had no id of their own, that is 28 + 4 = 32 templates. Where a name alone falls back to a word, as E3's "your student" did, the fallback is a value, worded in the recipient's language: "a member of your institution" and "somebody at the company" for an uploader with no name, the record ID for an institution or a sponsor the index names nothing, and "An Administrator" for a returned sponsor agreement whose Administrator's account holds no name (until now a second sentence in code, `'An Administrator read your ...'`, beside `'%s read your ...'`, the same words).
- **E4-2, the ids and the names.** Every one of the 26 ids reads true of one of its templates and keeps it: `institution-applied` is the acknowledgement with a call to book, `agreement-landed` the upload with nothing noticed, `agreement-revoked` the revocation with a contact, `sponsor-applied` the acknowledgement with the logo kept, `agreement-accepted` the acceptance and `sponsor-interest` the interests card's. Six ids are new: `institution-applied-no-call`, `agreement-landed-flagged`, `agreement-reinstated`, `agreement-revoked-no-contact`, `sponsor-applied-no-logo` and `sponsor-mentor-interest`, each with its label, area and audience, the variant's the base's. No old id is retired, so none ends " (until 1.122.22)"; the Log's rows of a reinstatement and of a mentor request sent before keep the name of the id they were sent under. The seven names of the institution's agreement emails said "Agreement" alone ("Agreement accepted"); E4-8 asks for the product's full name, and the Templates tab is where these names are now read, so each says "Collaboration Agreement", and "to the institution" where the sponsor's email has the same name ("Collaboration Agreement accepted, to the institution" beside the sponsor's "Collaboration Agreement accepted"). The Log reads names at display, so its rows from before take the new names; the guide says so.
- **E4-3, the recording.** `bin/test-institutions-sponsors-text.php` records every email of the two areas through the real senders and the real mail layer, as `wp_mail()` is handed it: to whom, under which id, its subject, its body with the signature and footer 1.122.21 adds, and its headers. 21 send sites are reached as their handlers and jobs call them (the private ones by reflection, as E3 reached the Mentor calls'), `detach()` and `remind()` among them; seven build their email inside a request handler and are reached through that handler, posted what its form posts, its capability and nonce answered yes, and stopped at the redirect it ends with (the two queues' question and rejection, the interests card, the mentor request and a post's return). The recipients are the real ones (`notify_managers()`, `mail_manager()`, `members_of()`), from accounts the cases give: every Administrator, a named reviewer list of an account without the role, a bare address and an Administrator, a sponsor's assigned Administrator with an account and without, an address that is not one, and a sponsor's or an institution's members, an ended membership among them; and cases where there is nobody to tell (no Administrator, no live member). Typed text comes in as people type it, and, for each email that carries it, as a browser posts a text box too, each line break CRLF; names come in as people type them, as WordPress stores them (an institution application's title and an account's name, "&" written `&amp;` and a "<" `&lt;`) and in letters beyond ASCII. The clock is fixed: `wp_date()` reads 2036-03-10 04:30 UTC for "now", the site keeps Los Angeles' clock, on which it is still March 9, and a moment the code works out from PHP's own clock (an invitation's expiry) is moved onto it; an Administrator and a member keep Tokyo's clock, a day ahead; no email of the two areas shows a time on a recipient's clock, so each date is pinned as the site's. 183 cases, 331 emails, the cases listed in Task 1's suite; a check names each of the 28 send sites and the id it sent under, so a case list that lost one fails.
- **E4-4, values.** A sentence `_n()` used to word whole takes its counted words as one value: `{review_window}` ("3 working days"), and the digest's two, `{agreements_waiting}` ("3 signed agreements are") and `{agreements_overdue}` ("3 signed agreements have been"), each with the verb its sentence takes. The digest's list is one value, `{overdue_list}`, an agreement to a paragraph; its item's words ("%1$s, uploaded %2$s") stay in the sender, as a counted phrase's do. The low codes' two strings counted with `%d`, which a placeholder cannot fill, so they hold `%s` now (`[%1$s] %2$s: %3$s codes left`), the same English bytes, and a new translatable string. A Reply-To and a plain-text subject stay in the sender's code, handed to `mail()` beside the values; no email of the two areas carries an attachment.
- **E4-5, required and body-only.** Required, so no edit can drop it: every link that carries a secret, `{verify_link}` (an application's confirmation) and `{accept_link}` (a colleague's invitation); what an Administrator or a sponsor wrote where it is the email's point, `{question}`, `{note}` and `{interest}`; a returned post's `{post_title}` and `{author_name}`, either of which can be empty (WordPress keeps untitled posts, and the plugin's own lists print "(no title)"; an account made by hand can hold no name), whose lines must stay as the code printed them ("Hello ,"); and the value a variant is sent only with, `{booking_link}`, `{scan_flags}` and `{country_contact}`, as E3 required `{meeting_place}` in the confirmations sent only with one. In the body alone: the two secret links, any text somebody typed (`{question}`, `{note}`, `{interest}`, `{name_asked_for}`, the name an institution typed for its agreement, and an applicant's own answers, `{applicant_email}`, `{city}`, `{website}`, `{contact_name}` and `{contact_email}`) and a code's last four characters, `{code_ending}`. The names and titles today's subjects already carry stay allowed in a subject (an institution's, a company's, an offer's and a post's), so the shipped text fits its own rules. No test, fixture or example holds a real secret: the recording's are `EXAMPLE-NOT-A-REAL-...`, which a check pins. The two rejections keep the reason out, as 1.122.19 made them: neither builder is handed it, neither template has a place for it, and Task 3 pins both.
- **E4-6, first.** `all()` reads each area's file through `area()`, a method of its own, so each file runs in a scope of its own and no file sees another's variables; the registry's suite reads each file alone and checks it raises no warning, and that `area()` holds the class's one `require`. A source check refuses `DAY_IN_SECONDS` and `WEEK_IN_SECONDS` in every area file; the new files work their example days out with `modify()`. The comment above the Warsaw check says it reads Warsaw's clock alone. The examples read true for the presser: an agreement decided today on the site's clock and uploaded yesterday, an invitation that works fourteen days from today, the working days as set, the site's own handlers for the two secret links with fake secrets, invented names (Ada Okonjo, Ben Lindqvist, Cleo Marsh, Example University, Example Hosting).
- **E4-7, the language and the screen.** Every value that is worded is worked out inside the builder, in the recipient's language, as the sentence around it is: an uploader with no name and the day a membership ended were worded in the language of whoever pressed until now (Task 3's Polish check pins the first; the stand-in `wp_date()` names no month in another language, so the second is the code's word alone). The Sponsors' worded values were worked out inside each builder already, and Task 4's Polish check holds them there: "somebody at the company", "An Administrator" and the counted review window. One stays as 1.122.21 made it: the generator's `{reason}`, the template's own refusal, a message made in the requester's language before any email is built. The Templates tab lists the five areas in the catalog's order, each table in its scroll box.
- **One method for every sender.** `WPCPM_Mail_Templates::mail( $id, $values, $extra )` is the registry-or-nothing guard E3's two senders each wrote privately (`mail_of()`, `rendered()`), so the twelve classes of this release share one; E3's two copies are left as they are, since nothing outside the two areas moves.
- **No commit before the last is built.** From Task 2 the Templates tab lists emails whose senders read the registry only from Task 3 (the Institutions) and Task 4 (the Sponsors); an edit saved on a build of Task 2 or 3 would be listed as Edited and not sent. Only the release's last commit is built.


## Global Constraints

- Version **1.122.22** in the plugin header (`Version:`), `WPCPM_VERSION` and readme.txt's `Stable tag`, with a changelog entry and the translation template regenerated (`sh bin/make-pot.sh`).
- Word for word: the shipped text of every email that moves into the registry is what the code wrote before the move, byte for byte, as `wp_mail()` is handed it with the signature and footer 1.122.21 adds and its headers. `bin/test-institutions-sponsors-text.php` records every email of the two areas from 1.122.21, through the real senders and the real mail layer, before anything moves (Task 1), and holds the registry to the recording after (Tasks 3 and 4). The recording is never edited by hand and never made twice: `--record` refuses once the file exists, and the suite pins the file's sha256.
- The catalog stays the one list of names, areas and audiences: a template never repeats a label, an area or an audience, and every template's id is a catalog id.
- A template is a subject and a body. Who receives an email, its Reply-To and whether its subject is plain text stay in the sender's code.
- Every value that is worded (a counted phrase, a fallback for a name) is worked out by the sender inside the recipient's language, except `{reason}`, the template's own message, kept as 1.122.21 made it; a list is one value.
- US English; plain hyphens only, never an em or en dash, in code, comments, strings and docs.
- Full product names: Collaboration Agreement, Institution Dashboard, Sponsor Dashboard, Administrator Dashboard, Student Report Card, Mentor Report Card; the program's managers are "Administrators".
- No translated string anywhere says "module" or "program manager" (bin/test-admin-menu.php refuses both); the screen word is **Area**. The code keeps `module` as the catalog's key.
- Examples and test data use invented names and `@example.test` addresses, never real people or institutions (bin/test-fixtures.php); no fixture holds an address of any kind (the recording writes each "at example.test") or a record ID but the seeds'. No test, fixture or example holds a real secret: the recording's links carry `EXAMPLE-NOT-A-REAL-...`, its codes are `EXAMPLE-CODE-7Q2X` and `SHARED-EXAMPLE-AB12`.
- The repository is mirrored publicly: no file, message or comment names unreleased or private work.
- The log never stores message text (E1's rule, unchanged). The registry's option stores the Administrators' wording of the emails, never a recipient's values.
- Administrators only: no screen or handler is added; the editor's two forms keep asking `WPCPM_Roles::CAP_MANAGE` first, then their nonce.
- Send me a test goes to the address of the account that pressed it, read from the account, never from a field a form posted; it sends the text as saved, with no header.
- An edited template is sent as edited for every recipient; an unedited one is built with `__()` in the recipient's language, as before.
- A choice from a long list is one field that drops down, takes typing and narrows (the owner's rule). The Log's Email field is that field already, and lists the six new names as it lists every name.
- Every method in a new file under `includes/` has a docblock; `sh bin/check-standards.sh` gains no error (main has 2).
- Every task ends with the whole battery: every `bin/test-*.php` in its own process, all passing, and `php bin/check-references.php`, as its last step before the commit shows.
- Never `git stash`. Commit after each task's battery passes.

## Review Focus

1. **Word for word, through the real senders.** Every email of the two areas, to every recipient, against the recording made from 1.122.21 before the move: its subject, its body with the footer, and its headers, byte for byte, under its template's own id, from every one of the 28 send sites, and every optional placeholder that production can leave empty empty in some case. Pinned in Task 1 (the recording, its sha256, `--record` refusing, the check naming the 28 send sites and their ids), Task 3 (the Institutions) and Task 4 (the Sponsors).
2. **One id, one email.** The catalog's six new ids, the 26 old ones each kept for the email it reads true of, the institution's agreement names in full; and the real senders' ids pinned through their handlers, beside the recording. Pinned in Task 2 (the catalog's two area checks), Task 3 (`sent_as()`, and bin/test-institution-agreement.php's flagged and reinstated ids through `handle_upload()` and `handle_reinstate()`) and Task 4 (`sent_as()`, and bin/test-sponsor-cards.php's mentor request through `handle_interest()`).
3. **Secrets and what people wrote.** Each secret link needed and in the body alone, each typed text in the body alone and needed where it is the email's point, a code's end in the body alone, no rejection carrying the reason typed, and no real secret in the recording or the examples. Pinned in Task 1 (the recording's privacy check), Task 2 (each template's required and body-only placeholders, the examples' fake secrets), Task 3 (the rejections) and Task 5 (the editor refusing each such save, the typing given back).
4. **Each recipient's language, and the site's clock.** Every worded value in the recipient's language, and every date the site's. Pinned in Task 1 (the recording's Los Angeles moment, a day behind UTC), Task 2 (the examples on the site's clock), Task 3 (a member who reads Polish) and Task 4 (a sponsor's member and an Administrator who read Polish).
5. **The registry's own rules and the screen.** Each area's file read in a scope of its own and leaning on no other's, no example date built from seconds, the five areas in the catalog's order each in its scroll box, every new template's editor, preview and Send me a test (to the presser alone, with no header, a plain-text subject kept as typed). Pinned in Task 2 (bin/test-mail-templates.php's scope and seconds checks, bin/test-emails-templates.php's five tables and the editor loop over every template) and Task 5 (the refusals, a preview, and the edited subject's test).

## Execution

Subagent-driven, strictly sequential in one worktree (`.worktrees/emails-e4`, branch `feature/emails-e4` from main): the tasks share files (`bin/test-institutions-sponsors-text.php` grows across Tasks 1, 3 and 4, `bin/test-mail-catalog.php` across Tasks 2 and 3, `bin/test-emails-templates.php` across Tasks 2 and 5), so none runs beside another. Every task gets its task review, and the most capable reviewers go to Task 2 (the registry, the catalog and the shipped text) and Tasks 3 and 4 (the senders, word for word).

---

## File Structure

| File | Responsibility |
| --- | --- |
| `bin/test-institutions-sponsors-text.php`, `bin/fixtures/institutions-sponsors-1-122-21.json` (new, Tasks 1, 3, 4) | The word-for-word recording of every email the Institutions and the Sponsors send, made from 1.122.21 through the real senders, and the suite that holds the registry to it. |
| `includes/mail/templates/institutions.php` (new, Task 2) | The Institutions area's 17 emails as shipped, each sentence under the translatable string the sender used, their placeholders and examples. |
| `includes/mail/templates/sponsors.php` (new, Task 2) | The Sponsors area's fifteen emails as shipped. |
| `includes/mail/class-wpcpm-mail-templates.php` (Task 2) | `AREAS` gains the two areas; `all()` reads each area's file through `area()`; `mail()` hands a builder its email. |
| `includes/mail/class-wpcpm-mail-catalog.php` (Task 2) | Gains six ids; the institution's agreement names say Collaboration Agreement in full. |
| `includes/modules/class-wpcpm-institution-application.php`, `class-wpcpm-institutions.php`, `class-wpcpm-institution-invite.php`, `class-wpcpm-institution-agreement.php`, `class-wpcpm-agreement-generate.php`, `class-wpcpm-institution-members.php` (Task 3) | Each builder chooses its template and works out its values, and returns `WPCPM_Mail_Templates::mail()`; `contact_line()` becomes `contact_of()`. |
| `includes/modules/class-wpcpm-sponsor-application.php`, `class-wpcpm-sponsor-agreement.php`, `class-wpcpm-sponsor-interests.php`, `class-wpcpm-sponsor-mentors.php`, `class-wpcpm-sponsor-claims.php`, `class-wpcpm-sponsor-posts.php` (Task 4) | As above, for the Sponsors. |
| `bin/test-mail-catalog.php`, `bin/test-mail-templates.php`, `bin/test-emails-templates.php` (Task 2); `bin/test-mail-catalog.php` and seven Institutions suites (Task 3); seven Sponsors suites (Task 4); `bin/test-emails-templates.php` (Task 5) | The suites, each in the task that changes what it reads. |
| `docs/sections/32-admin-tools.md`, then `docs/administrators.md` and `docs/build/administrators.html`; `wpcredits-program-manager.php`, `readme.txt`, `languages/wpcredits-program-manager.pot` (Task 6) | The Administrator guide, the version, the changelog and the template. |

Every suite is plain PHP run as `php bin/<suite>.php`, printing `ok` / `FAIL` lines and a last line reporting the result (`ALL PASS (N checks)` or `N FAILURE(S) (N checks)` in the suites this plan writes or names with a count; a few older suites print `ALL PASS` or `N FAILURE(S)` alone), and exiting non-zero on a failure. A new suite carries the `ck()` helper below, as every suite does (the one new suite of this plan is written out whole, helper included):

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

How each change is written: "Create `path`" gives a new file whole; "In `path`, replace:" gives the exact lines to find, once, and the lines to put in their place.

---


### Task 1: The recording: what every Institutions and Sponsors email says today, word for word

Before anything moves, a suite records every email the two areas send, through the real senders and the real mail layer, from today's code, into a fixture that is committed and never edited by hand; from then on the same suite sends the same emails from the same inputs and compares them with the recording, byte for byte (ruling E4-3). It changes no code.

**Files:**
- Create: `bin/test-institutions-sponsors-text.php`
- Create, by the suite's `--record` run: `bin/fixtures/institutions-sponsors-1-122-21.json`

**Interfaces:**
- Consumes, as 1.122.21 has them: `WPCPM_Institution_Application::mail_applicant( $post_id )` and `::mail_managers( $post_id )` (private); `WPCPM_Institutions::handle_info()` and `::handle_reject()` (on an instance); `WPCPM_Institution_Invite::mail_invite( $post_id, $token )` (private); `WPCPM_Institution_Agreement::mail_received( $record, $post_id )`, `::mail_landed( $record, $post_id )`, `::mail_accepted( $record, $post_id )`, `::mail_reinstated( $record, $post_id )`, `::mail_returned( $record, $post_id, $note )`, `::mail_revoked( $record, $post_id, $note )` (private) and `::remind()`; `WPCPM_Agreement_Generate::template_needs_attention( $record, $name, $language, WP_Error $error )` (private, ends in `wp_die()`); `WPCPM_Institution_Members::detach( $user_id, $reason, $actor_id )`; `WPCPM_Sponsor_Application::mail_applicant( $post_id )`, `::mail_managers( $post_id )` (private), `::handle_info()` and `::handle_reject()`; `WPCPM_Sponsor_Agreement::mail_received( $record, $post_id )`, `::mail_accepted( $record, $post_id )`, `::mail_returned( $record, $note )`, `::mail_revoked( $record, $note )` and `::mail_reinstated( $record )` (private); `WPCPM_Sponsor_Interests::handle_save()`; `WPCPM_Sponsor_Mentors::handle_interest()`; `WPCPM_Sponsor_Claims::maybe_low_stock( $offer )` and `::report_problem( $offer_id, $user )`; `WPCPM_Sponsor_Posts::handle_return()`; `WPCPM_Mail::send()`, `::send_to()` and `::reply_to()`; `WPCPM_Institutions::notify_managers()`, `WPCPM_Sponsor_Interests::mail_manager()`, `WPCPM_Institution_Members::members_of()` and `WPCPM_Sponsor_Members::members_of()`.
- Produces:
  - `bin/fixtures/institutions-sponsors-1-122-21.json`: `{ "_comment": string, "cases": { "<case>": [ { "to": string, "context": string, "subject": string, "body": string, "headers": string[], "files": int } ] } }`, 183 cases and 331 emails, each in the order it was sent, every address written "at example.test".
  - In the suite: `cases(): array<string,array>`, `memberships(): array`, `reach( $class, $method ): ReflectionMethod`, `post( $id, $type, array $props, array $meta )`, `press( $handler )`, `emails_of( array $case ): array[]`, `recorded_form( array $mail ): array`, and `sent_as( $case, $index, $recorded ): string`, the id each recorded email is logged under now, which answers the recorded id until Task 3 replaces it.
  - `--record` writes the file once: run again, it refuses ("The recording exists and is never made twice.") and exits 1, and the suite pins the file's sha256.

- [ ] **Step 1: Write the suite**

The inputs are invented: Administrators (one whose name holds a quote and a comma, one whose account holds no name, one who is also an institution's member, one who keeps Tokyo's clock, one whose name is as WordPress stores it, one whose name is in letters beyond ASCII), a reviewer named in a setting whose account has no role, institutions' and a sponsor's members (one who is also a mentor, one who is also a student, one whose membership ended, one who keeps Tokyo's clock, one of each whose name is as WordPress stores it), a sponsor's account with no name that wrote a post and one with no address, two claimants, one whose name is as WordPress stores it; every address `example.test`'s; every secret a fake. The cases take every branch the senders have (each listed in `cases()`, by send site), every optional line in and out, every recipient set, counts of one and of several, a typed question, note, reason and message holding "<", "&", both quotes, a "%", a placeholder's name in braces and a line break (and each that an email carries as a browser posts it too, each line break CRLF), names and titles holding "&", quotes, a "%" and a placeholder's name, and as WordPress stores them (an institution application's title, an offer's, a post's and an account's name), text in letters beyond ASCII (an institution, a sponsor, an Administrator, a mentor, an offer, a post and a typed text, through the question, a return, the interest, the low codes, the claim problem and the post's return among others), the highest low mark the Offers form takes, a sponsor's application held or flagged for something other than its logo, a site name holding "&", a quote and a "%", every dashboard page published and none, and each alone, and the cases that send nothing, or less: no address, nobody to tell, nothing waiting, already sent. A check names each of the 28 send sites with the id it sent under, so the recording cannot lose one; another holds the fixture to no address, no real secret and no record ID but the seeds'. The stand-in translations hand English back as written, and mark any other language with its locale, which Task 3's and Task 4's checks of a recipient who reads Polish read.

Create `bin/test-institutions-sponsors-text.php`:

```php
<?php
/**
 * The Institutions and Sponsors emails, word for word: what each says, as 1.122.21 wrote it.
 *
 * These emails move into the template registry in 1.122.22, and the move must change nothing a
 * recipient reads. So before the move this suite recorded, through the real send paths, every email
 * the two areas send for a set of example cases into bin/fixtures/institutions-sponsors-1-122-21.json:
 * who it went to, the id it was logged under, its subject and body as `wp_mail()` was handed them (the
 * signature and footer included, as 1.122.21 adds it), and its headers. From then on it runs the same
 * cases and compares, byte for byte.
 *
 * The send paths, each the code that builds the email and hands it to `WPCPM_Mail`:
 *
 * - reached as their handlers and jobs call them, the private ones by reflection: the institution
 *   application's two (`mail_applicant()`, `mail_managers()`), the institution invitation
 *   (`mail_invite()`), the institution agreement's six (`mail_received()`, `mail_landed()`,
 *   `mail_accepted()`, `mail_reinstated()`, `mail_returned()`, `mail_revoked()`) and its daily
 *   digest (`remind()`), the agreement generator's notice (`template_needs_attention()`, which ends
 *   in `wp_die()`), a membership ending (`WPCPM_Institution_Members::detach()`, which writes to the
 *   Administrators when the last member goes), the sponsor application's two, the sponsor
 *   agreement's five, and the codes' two (`maybe_low_stock()`, `report_problem()`);
 * - seven are built inside a request handler and can be reached no other way, so the suite makes the
 *   request: the institution and sponsor queues' question and rejection (`handle_info()`,
 *   `handle_reject()`), the Sponsor Dashboard's interests card (`handle_save()`) and its form for
 *   sponsoring a mentor (`WPCPM_Sponsor_Mentors::handle_interest()`), and a sponsor post's return
 *   (`handle_return()`). Each is posted what its form posts, its capability and nonce are answered
 *   yes, and the redirect it ends with stops it; who it writes to, and under which id, is recorded as
 *   for every other email.
 *
 * The recipients are the real ones: `WPCPM_Institutions::notify_managers()` (every Administrator, or
 * the accounts and bare addresses a setting names), `WPCPM_Sponsor_Interests::mail_manager()` (the
 * sponsor's assigned Administrator with an account or without one, else the setting, else every
 * Administrator), the members of an institution or a sponsor (`members_of()`, the live members only),
 * an applicant's or an invitee's address, a post's author and a code's claimant.
 *
 * The inputs: every optional line in and out (a call to book, the courtesy scan's notes, a country
 * contact, the logo not kept, each dashboard link), every count of one and of several, and the
 * highest low mark the Offers form takes, a typed question, note, reason and message holding "<",
 * "&", both quotes, a "%", a placeholder's name in braces and a line break (and each that an email
 * carries as a browser posts a text box too, each line break CRLF), names and titles holding "&",
 * quotes and a "%", and as WordPress stores them, their entities written out (an institution
 * application's title, an offer's, a post's and an account's name), letters beyond ASCII (an
 * institution, a sponsor, an Administrator, a mentor, an offer, a post and a typed text), a site
 * name holding "&", a quote and a "%", an institution and a sponsor the index holds no name for, an
 * uploader, an Administrator, a sponsor's member and a post's author with no name, a sponsor's
 * application held or flagged for something other than its logo, every dashboard page published
 * and none, and each alone, and an account that holds two roles. No email of the two areas shows a
 * time on a recipient's clock: each date is the site's, and the site keeps Los Angeles'
 * clock here at a moment when its date is a day behind UTC's, while an Administrator and a member
 * keep Tokyo's, so a date taken from another clock would show. The clock is fixed: `wp_date()` reads
 * the suite's moment, 2036-03-10 04:30 UTC, for "now", and a moment the code works out from PHP's own
 * clock (an invitation's expiry, fourteen days on) is moved onto that moment, so every run records
 * the same dates. The daily digest reads an upload's age on PHP's own clock, so the documents it
 * finds waiting were uploaded early in 2026, and the one it must leave out in 2099.
 *
 * The names are invented; every address is example.test's and is written "at example.test" in the
 * recording, which holds no address; every link that carries a secret carries a fake one
 * ("EXAMPLE-NOT-A-REAL-...") and every code is a fake one. The stand-in translations hand English
 * back as written and mark any other language with its locale, so a later check can tell which
 * language an email was built in; the recording is English throughout.
 *
 * The recording is made once: `--record` refuses when the file is there, and the suite pins the
 * file's sha256, so a recording made again from later code can never stand in for 1.122.21's.
 *
 * Run from the plugin root:  php bin/test-institutions-sponsors-text.php
 * Record (once, against 1.122.21, before the move):  php bin/test-institutions-sponsors-text.php --record
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

/** The suite's moment: 2036-03-10 04:30 UTC, the evening of March 9 on the site's clock. */
define( 'WPCPM_TEST_NOW', 2088736200 );

$GLOBALS['opts']      = array();
$GLOBALS['umeta']     = array();
$GLOBALS['users']     = array();
$GLOBALS['posts']     = array();
$GLOBALS['pmeta']     = array();
$GLOBALS['mail']      = array();
$GLOBALS['uid']       = 0;
$GLOBALS['manage']    = array( 40, 41, 43, 55 );
$GLOBALS['site_name'] = 'WordPress Credits';
$GLOBALS['ids']       = array();

class WP_Error {
	private $code;
	private $message;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array() ) {
		$this->ID           = $id;
		$this->display_name = $name;
		$this->user_email   = $email;
		$this->user_login   = strtolower( preg_replace( '/[^A-Za-z]/', '', $name ) );
		$this->roles        = $roles;
	}
	public function exists() { return $this->ID > 0; }
	public function remove_role( $role ) { $this->roles = array_values( array_diff( $this->roles, array( $role ) ) ); }
	public function set_role( $role ) { $this->roles = array( $role ); }
}
class WP_Post { public $ID = 0, $post_title = '', $post_content = '', $post_type = '', $post_status = 'private', $post_date = '', $post_author = 0; }

/** What a handler ends with, a redirect or `wp_die()`, stops it here. */
class WPCPM_Test_Stop extends Exception {}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
// The language each string is asked in: English as written, any other marked with its locale, so a
// check can tell which language an email was built in. The recording is English throughout.
function __( $s, $d = null ) { return wpcpm_test_in_language( $s ); }
function _n( $a, $b, $n, $d = null ) { return wpcpm_test_in_language( 1 === (int) $n ? $a : $b ); }
function esc_html__( $s, $d = null ) { return __( $s, $d ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wpcpm_test_in_language( $s ) { return 'en_US' === determine_locale() ? $s : '[' . determine_locale() . '] ' . $s; }
function is_email( $s ) { return (bool) filter_var( (string) $s, FILTER_VALIDATE_EMAIL ); }
function sanitize_email( $s ) { return is_email( trim( (string) $s ) ) ? trim( (string) $s ) : ''; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function wp_slash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function get_bloginfo( $k = 'name' ) { return $GLOBALS['site_name']; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
// A signature the site would make with its secret salt, made here with none: plainly not a real one.
function wp_hash( $data, $scheme = 'auth' ) { return 'EXAMPLE-NOT-A-REAL-SIGNATURE' . md5( (string) $data ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_user_by( $f, $v ) {
	if ( 'id' === $f ) {
		return $GLOBALS['users'][ (int) $v ] ?? false;
	}

	// An empty value finds nobody, as core's lookup answers it: an account that holds no address is
	// not the one an empty address names.
	if ( '' === trim( (string) $v ) ) {
		return false;
	}

	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'email' === $f && strtolower( $user->user_email ) === strtolower( trim( (string) $v ) ) ) {
			return $user;
		}
	}

	return false;
}
function get_userdata( $id ) { return get_user_by( 'id', $id ); }
/**
 * `get_users()` as the senders ask it: the accounts holding a capability, or a meta key's value.
 *
 * @param array $args The query.
 * @return WP_User[]
 */
function get_users( $args = array() ) {
	$out = array();

	foreach ( $GLOBALS['users'] as $id => $user ) {
		if ( isset( $args['capability'] ) && ! user_can( $user, $args['capability'] ) ) {
			continue;
		}

		if ( isset( $args['meta_key'] ) && (string) get_user_meta( $id, $args['meta_key'], true ) !== (string) $args['meta_value'] ) {
			continue;
		}

		$out[] = $user;
	}

	return $out;
}
function get_post( $id = null ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
// The documents a query for agreements waiting on review finds, as a case gives them.
function get_posts( $args = array() ) { return $GLOBALS['waiting']; }
function get_post_meta( $id, $k = '', $single = false ) { $rows = $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? array(); $rows = is_array( $rows ) ? $rows : array( $rows ); if ( $single ) { return $rows ? $rows[0] : ''; } return $rows; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = array( $v ); return true; }
function add_post_meta( $id, $k, $v ) { return true; }
function wp_update_post( $post, $wp_error = false ) { return (int) $post['ID']; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
function check_admin_referer( $action = -1, $name = '_wpnonce' ) { return 1; }
function wp_get_referer() { return false; }
function wp_safe_redirect( $to ) { throw new WPCPM_Test_Stop( 'redirect' ); }
function wp_die( $m = '', $c = 0 ) { throw new WPCPM_Test_Stop( 'wp_die' ); }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function wp_timezone_string() { return 'America/Los_Angeles'; }
function wp_timezone() { return new DateTimeZone( wp_timezone_string() ); }
/**
 * `wp_date()` on the suite's clock: "now" is `WPCPM_TEST_NOW`, and a moment worked out from PHP's own
 * clock, as an invitation's expiry is, is moved onto it, so every run records the same dates.
 *
 * @param string            $format A date format.
 * @param int|null          $ts     A moment, or null for now.
 * @param DateTimeZone|null $zone   A clock, or null for the site's.
 * @return string
 */
function wp_date( $format, $ts = null, $zone = null ) {
	$ts = null === $ts ? time() : (int) $ts;

	if ( abs( $ts - time() ) < 400 * DAY_IN_SECONDS ) {
		$ts += WPCPM_TEST_NOW - time();
	}

	$d = new DateTime( '@' . $ts );
	$d->setTimezone( $zone instanceof DateTimeZone ? $zone : wp_timezone() );

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
 * `wp_mail()`, keeping what it was handed and the id the mail log's capture takes.
 */
function wp_mail( $to, $subject, $body, $headers = array(), $attachments = array() ) {
	$GLOBALS['mail'][] = array(
		'to'      => $to,
		'context' => WPCPM_Mail::take_context(),
		'subject' => $subject,
		'body'    => $body,
		'headers' => array_values( (array) $headers ),
		'files'   => count( (array) $attachments ),
	);

	WPCPM_Mail::take_test();

	return true;
}
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/stubs/temp-dir.php';
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/stubs/cleaners.php';

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-capture.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/trait-wpcpm-accounts-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-invite.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-agreement-generate.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-members.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-agreement.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-members.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-interests.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-mentors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-claims.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-posts.php';

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
/** As above, and the Sponsor Dashboard's way back from a press, which stops the handler here. */
class WPCPM_Sponsors_Dashboard {
	public static $url = '';
	public static function page_url() { return self::$url; }
	public static function leave( $status, $card = '', $record = '', $detail = '' ) { throw new WPCPM_Test_Stop( 'leave: ' . $status ); }
}
/** As above. */
class WPCPM_Administrators_Dashboard {
	public static $url = '';
	public static function page_url() { return self::$url; }
}
/** The one-shot outcomes a press leaves: nothing to keep here. */
class WPCPM_Flash {
	public static function set( $channel, $value ) {}
	public static function take( $channel ) { return null; }
	public static function sweep( $test ) {}
}
/** The institutions the index holds by record, as the cases give them. */
class WPCPM_Institutions_Index {
	public static $rows = array();
	public static function row( $record ) { return self::$rows[ $record ] ?? null; }
}
/** The sponsors the index holds by record, and each one's assigned Administrator, as a case gives them. */
class WPCPM_Sponsors_Index {
	public static $rows    = array();
	public static $manager = null;
	public static function row( $record ) { return self::$rows[ $record ] ?? null; }
	public static function manager_of( $record ) { return self::$manager; }
	public static function patch( $record, array $patch ) { return true; }
	public static function empty_row() { return array( 'name' => '', 'support' => array() ); }
}
/** Each country's routing: its contact's address and booking link, as the countries map holds them. */
class WPCPM_Countries {
	public static function routing( $country ) {
		$map = array(
			'PL' => array( 'email' => 'pl-contact@example.test', 'calendly' => 'https://calendly.example.test/pl-contact' ),
			'XX' => array( 'email' => '', 'calendly' => '' ),
		);

		return $map[ $country ] ?? null;
	}
	public static function name_of( $country ) { return 'PL' === $country ? 'Poland' : $country; }
}
/** A record ID, as Airtable writes one, and the mentors looking for a sponsor, as the mentors sync keeps them. */
class WPCPM_Mentors_Sync {
	public static function is_record_id( $value ) { return is_scalar( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
	public static function sponsorship() {
		return array(
			'recSEED0000000201' => array( 'status' => 'Active', 'wants' => true, 'sponsored' => false, 'name' => 'Ben Lindqvist', 'profile' => 'https://profiles.wordpress.org/mentor-two/', 'expertise' => array(), 'user_id' => 0 ),
			'recSEED0000000202' => array( 'status' => 'Active', 'wants' => true, 'sponsored' => false, 'name' => 'Rio "Q&A" O\'Example 100%', 'profile' => 'https://profiles.wordpress.org/rio-example/', 'expertise' => array(), 'user_id' => 0 ),
			'recSEED0000000203' => array( 'status' => 'Active', 'wants' => true, 'sponsored' => true, 'name' => 'Sponsored Already', 'profile' => 'https://profiles.wordpress.org/mentor-three/', 'expertise' => array(), 'user_id' => 0 ),
			'recSEED0000000204' => array( 'status' => 'Active', 'wants' => true, 'sponsored' => false, 'name' => 'Józef Żółćwicz-Núñez', 'profile' => 'https://profiles.wordpress.org/mentor-four/', 'expertise' => array(), 'user_id' => 0 ),
		);
	}
}
/** The Institutions table's columns the queue reads an applicant's address under. */
class WPCPM_Institutions_Sync {
	public static function fields() { return array( 'contact_email' => 'Contact Email' ); }
}
/** The audit log: what a press records, kept nowhere here. */
class WPCPM_Institution_Audit {
	const KIND_MEMBER_REMOVED = 'member_removed';
	const GROUND_MANAGER      = 'manager';
	const GROUND_MEMBER       = 'member';
	const GROUND_SYSTEM       = 'system';
	const EVIDENCE_INDEX      = 'index';
	public static function record( array $row ) { return 1; }
	public static function record_sponsor( array $row ) { return 1; }
}
/** The day's ceilings, never reached here. */
class WPCPM_Ceiling {
	public static function key( $kind, $who ) { return $kind . ':' . $who; }
	public static function claim( $key, $limit, $window ) { return true; }
}
/** The Sponsors table: a history read and written back, as the interests card does. */
class WPCPM_Airtable {
	public function get_record( $table, $record ) { return array( 'fields' => array( 'Sponsorship interests' => '2036-01-05 by Sami Sponsor: note: An earlier line' ) ); }
	public function update_records( $table, array $rows ) { return array(); }
	public static function flatten( $value, $glue = ', ' ) { return is_array( $value ) ? implode( $glue, $value ) : (string) $value; }
}
/** Who may act for a sponsor: the case's sponsor, for the account signed in. */
class WPCPM_Sponsor_Roster {
	const ARG_VIEW = 'wpcpm_sponsor_view';
	public static function claim( $record, $act ) {
		return array(
			'record'   => (string) $record,
			'row'      => WPCPM_Sponsors_Index::row( (string) $record ),
			'decision' => array( 'ground' => 'member' ),
		);
	}
}
/** The sponsor policy's names, and its yes. */
class WPCPM_Sponsor_Policy {
	const ACT_EXPRESS_INTEREST = 'express_interest';
	const ACT_PUBLISH_POST     = 'publish_post';
	const META_POST_SPONSOR    = 'wpcpm_post_sponsor';
	public static function subject_post( $post ) { return $post; }
	public static function decide( $act, $subject ) { return array( 'allowed' => true ); }
}
/** The offers, as a case gives them. */
class WPCPM_Sponsor_Offers {
	const KIND_CODES    = 'codes';
	const META_LOW_SENT = 'wpcpm_offer_low_sent';
	public static $offers = array();
	public static function read( $id ) { return self::$offers[ (int) $id ] ?? null; }
}
/** An offer's pool: how many codes are left, and the fake code a claimant holds. */
class WPCPM_Sponsor_Codes {
	const LOCK_PREFIX  = 'wpcpm_codes_lock_';
	const LOCK_TIMEOUT = 30;
	const SHARED_INDEX = -1;
	public static $available = 0;
	public static function counts( $offer_id ) { return array( 'available' => self::$available ); }
	public static function code_at( $offer_id, $index ) { return 'EXAMPLE-CODE-7Q2X'; }
	public static function shared( $offer_id ) { return 'SHARED-EXAMPLE-AB12'; }
}
/** The Sponsors screen's flash channel. */
class WPCPM_Sponsors {
	const FLASH = 'sponsors_admin';
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
 * The people, the institutions and the sponsors
 * --------------------------------------------------------------------
 */

$GLOBALS['opts']['date_format'] = 'F j, Y';
$GLOBALS['opts']['time_format'] = 'g:i a';

$GLOBALS['users'][40] = new WP_User( 40, 'Ida Admin', 'ida@example.test', array( WPCPM_Roles::ROLE_ADMIN ) );
$GLOBALS['users'][41] = new WP_User( 41, "Omar O'Admin, Ops", 'omar@example.test', array( WPCPM_Roles::ROLE_ADMIN ) );
$GLOBALS['users'][42] = new WP_User( 42, 'Rhea Reviewer', 'rhea@example.test', array() );
// An Administrator whose account holds no name, as an account made by hand can.
$GLOBALS['users'][43] = new WP_User( 43, '', 'nameless@example.test', array( WPCPM_Roles::ROLE_ADMIN ) );
// An Administrator whose name is as WordPress stores an account's on save, "&" written `&amp;` and a
// "<" `&lt;`, and one whose name is in letters beyond ASCII. Each holds the program's capability only
// in a case that adds it (`manage`), so every other case's Administrators are the four above.
$GLOBALS['users'][44] = new WP_User( 44, 'Gus &amp; Gwen &lt;Ops', 'gus@example.test', array( WPCPM_Roles::ROLE_ADMIN ) );
$GLOBALS['users'][45] = new WP_User( 45, 'Zoë Ångström-Núñez', 'zoe@example.test', array( WPCPM_Roles::ROLE_ADMIN ) );
$GLOBALS['users'][50] = new WP_User( 50, 'Ines Example', 'ines@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );
// An institution's member who is also a mentor.
$GLOBALS['users'][51] = new WP_User( 51, 'Noor Example', 'noor@example.test', array( WPCPM_Roles::ROLE_INSTITUTION, WPCPM_Roles::ROLE_MENTOR ) );
// A member whose membership has ended: no longer written to.
$GLOBALS['users'][52] = new WP_User( 52, 'Pia Former', 'pia@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['users'][53] = new WP_User( 53, 'Lena Last', 'lena@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['users'][54] = new WP_User( 54, 'Theo Second', 'theo@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );
// An institution's member who is also an Administrator.
$GLOBALS['users'][55] = new WP_User( 55, 'Una Second', 'una@example.test', array( WPCPM_Roles::ROLE_INSTITUTION, WPCPM_Roles::ROLE_ADMIN ) );
$GLOBALS['users'][56] = new WP_User( 56, 'Vera Third', 'vera@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );
// An institution's member whose name is as WordPress stores it: a member only in a case that makes it one.
$GLOBALS['users'][57] = new WP_User( 57, 'Mia &amp; Max &lt;Team', 'mia@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['users'][60] = new WP_User( 60, 'Sami Sponsor', 'sami@example.test', array( WPCPM_Roles::ROLE_SPONSOR ) );
// A sponsor's member who is also a student.
$GLOBALS['users'][61] = new WP_User( 61, 'Tove Sponsor', 'tove@example.test', array( WPCPM_Roles::ROLE_SPONSOR, WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][62] = new WP_User( 62, 'Ulla Gone', 'ulla@example.test', array( WPCPM_Roles::ROLE_SPONSOR ) );
// A sponsor's account that holds no name, as an account made by hand can: the author of a post sent back.
$GLOBALS['users'][63] = new WP_User( 63, '', 'nameless-sponsor@example.test', array( WPCPM_Roles::ROLE_SPONSOR ) );
// A sponsor's account whose name is as WordPress stores it, and one that holds no address, as an
// account made by hand can: each a member only in a case that makes it one.
$GLOBALS['users'][64] = new WP_User( 64, 'Sid &amp; Sal &lt;Sales', 'sid@example.test', array( WPCPM_Roles::ROLE_SPONSOR ) );
$GLOBALS['users'][65] = new WP_User( 65, 'Wes Noaddress', '', array( WPCPM_Roles::ROLE_SPONSOR ) );
$GLOBALS['users'][70] = new WP_User( 70, "Kai O'Claimant", 'kai@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
// A claimant whose name is as WordPress stores it.
$GLOBALS['users'][71] = new WP_User( 71, 'Kit &amp; Kin &lt;3', 'kit@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );

/**
 * Every live and ended membership, as each case starts with them, and the clock two accounts keep: an
 * Administrator and a member read Tokyo's, a day ahead of the site's here, so a date an email showed on
 * its recipient's clock would differ from the site's one the recording holds.
 */
function memberships() {
	$in = WPCPM_Institution_Members::META_RECORD_ID;
	$on = WPCPM_Institution_Members::META_ACTIVE;
	$sp = WPCPM_Sponsor_Members::META_RECORD_ID;
	$so = WPCPM_Sponsor_Members::META_ACTIVE;
	// WPCPM_Mentor_Availability::META_TIMEZONE, the profile's time zone.
	$tz = 'wpcpm_timezone';

	return array(
		40 => array( $tz => 'Asia/Tokyo' ),
		50 => array( $in => 'recSEED0000000016', $on => 1, $tz => 'Asia/Tokyo' ),
		51 => array( $in => 'recSEED0000000016', $on => 1 ),
		52 => array( $in => 'recSEED0000000016', $on => 0 ),
		53 => array( $in => 'recSEED0000000017', $on => 1 ),
		54 => array( $in => 'recSEED0000000018', $on => 1 ),
		55 => array( $in => 'recSEED0000000018', $on => 1 ),
		56 => array( $in => 'recSEED0000000020', $on => 1 ),
		60 => array( $sp => 'recSEED0000000101', $so => 1 ),
		61 => array( $sp => 'recSEED0000000101', $so => 1 ),
		62 => array( $sp => 'recSEED0000000101', $so => 0 ),
	);
}

/** A question, a note, a reason or a message as a person types one into a box. */
const TYPED = "Could you <3 a Q&A with \"both\" 'quotes' at 100% {note}?\nAnd a second line.";

/** A name or a title as a person types one. */
const NAMED = 'Example University & "Partners" 100%';

/** A name holding a placeholder's name in braces, which is never read as one. */
const NAMED_BRACES = 'Example University & "Partners" 100% {institution_name}';

/** The site's name as WordPress stores it, its entities written out. */
const SITE_NAMED = 'Ada &amp; Ben&#039;s 100% Credits';

/**
 * A name as WordPress stores the title of an application filed by a visitor who is not signed in:
 * "&" written `&amp;`, a "<" `&lt;`.
 */
const STORED = 'Arts &amp; Sciences &lt;Faculty "Uni" 100%';

/** A question, a note, a reason or a message typed in letters beyond ASCII, curly quotes among them. */
const TYPED_NON_ASCII = "Czy możecie przysłać „kopię” <3 & 'Größe' 100% {note}?\nZażółć gęślą jaźń, señor.";

/** An institution's name in letters beyond ASCII. */
const NAMED_NON_ASCII = 'Akademia Przykładowa w Łące & „Żółw” 100%';

/** A sponsor's name in letters beyond ASCII. */
const SPONSOR_NON_ASCII = 'Größe & Söhne Beispiel-Hosting';

/**
 * Every case: what it sends, through which sender, and the world it sends in.
 *
 * Each case names the sender (`send`), who is signed in (`actor`), the dashboard pages published
 * (`pages`, any of student, mentor, institution, sponsor and administrator), the site's name as
 * WordPress stores it (`site`), the settings it changes (`settings`), and what the sender is handed or
 * finds (`args`). Among `args`, a case can give the text typed (`typed`), a name an index row holds
 * in place of its own (`renamed`), a membership beside every case's (`joins`), an Administrator
 * beside the four (`manage`) and the signals a sponsor's application was filed with (`signals`).
 *
 * @return array<string,array>
 */
function cases() {
	$all   = array( 'student', 'mentor', 'institution', 'sponsor', 'administrator' );
	$named = array( 'agreement_notify' => 'rhea@example.test, reviews@example.test,  ida@example.test , rhea@example.test' );
	$snamed = array( 'sponsor_notify' => 'reviews@example.test, omar@example.test' );
	// A live membership of an institution or of the sponsor, for an account a case makes a member.
	$of_16  = array( WPCPM_Institution_Members::META_RECORD_ID => 'recSEED0000000016', WPCPM_Institution_Members::META_ACTIVE => 1 );
	$of_19  = array( WPCPM_Institution_Members::META_RECORD_ID => 'recSEED0000000019', WPCPM_Institution_Members::META_ACTIVE => 1 );
	$of_101 = array( WPCPM_Sponsor_Members::META_RECORD_ID => 'recSEED0000000101', WPCPM_Sponsor_Members::META_ACTIVE => 1 );
	// What an Administrator typed, as a browser posts a text box, each line break CRLF.
	$crlf = str_replace( "\n", "\r\n", TYPED );

	$base = array(
		'actor'    => 40,
		'pages'    => $all,
		'site'     => 'WordPress Credits',
		'settings' => array(),
		'args'     => array(),
	);

	$cases = array(
		// The institution application: the applicant, with and without a call to book, and the
		// Administrators, every one or those a setting names.
		'applied'                                        => array( 'send' => 'inst-applied' ),
		'applied-no-call'                                => array( 'send' => 'inst-applied', 'args' => array( 'calendly' => '' ) ),
		'applied-no-routing'                             => array( 'send' => 'inst-applied', 'args' => array( 'routing' => false ) ),
		'applied-names'                                  => array( 'send' => 'inst-applied', 'site' => SITE_NAMED, 'pages' => array(), 'args' => array( 'name' => NAMED_BRACES ) ),
		'applied-contact-in-the-footer'                  => array( 'send' => 'inst-applied', 'settings' => array( 'mail_contact' => 'program@example.test' ) ),
		'applied-no-address'                             => array( 'send' => 'inst-applied', 'args' => array( 'email' => 'not an address' ) ),
		'application-every-administrator'                => array( 'send' => 'inst-application' ),
		'application-named-reviewers'                    => array( 'send' => 'inst-application', 'settings' => $named ),
		'application-bare-fields'                        => array( 'send' => 'inst-application', 'args' => array( 'city' => '', 'country' => '', 'person' => '' ) ),
		'application-names'                              => array( 'send' => 'inst-application', 'site' => SITE_NAMED, 'args' => array( 'name' => NAMED_BRACES ) ),
		'application-no-page'                            => array( 'send' => 'inst-application', 'pages' => array() ),
		'application-administrator-page-only'            => array( 'send' => 'inst-application', 'pages' => array( 'administrator' ) ),
		'application-no-administrator'                   => array( 'send' => 'inst-application', 'args' => array( 'nobody' => true ) ),
		// The institution's name as WordPress stores the title of an application filed by a visitor who is
		// not signed in, and in letters beyond ASCII.
		'applied-title-as-stored'                        => array( 'send' => 'inst-applied', 'args' => array( 'name' => STORED ) ),
		'application-title-as-stored'                    => array( 'send' => 'inst-application', 'args' => array( 'name' => STORED ) ),
		'applied-non-ascii'                              => array( 'send' => 'inst-applied', 'args' => array( 'name' => NAMED_NON_ASCII ) ),
		'application-non-ascii'                          => array( 'send' => 'inst-application', 'args' => array( 'name' => NAMED_NON_ASCII, 'city' => 'Łódź', 'person' => 'Łucja Żółćwicz' ) ),
		'application-fields-with-quotes'                 => array( 'send' => 'inst-application', 'args' => array( 'city' => "L'Aquila", 'country' => "Côte d'Ivoire", 'person' => "Seán O'Example & Ana" ) ),
		// The institution queue's question and rejection, through their handlers.
		'information'                                    => array( 'send' => 'inst-information' ),
		'information-administrator-with-quotes'          => array( 'send' => 'inst-information', 'actor' => 41, 'site' => SITE_NAMED ),
		'information-administrator-with-no-name'         => array( 'send' => 'inst-information', 'actor' => 43 ),
		'information-no-address'                         => array( 'send' => 'inst-information', 'args' => array( 'email' => '' ) ),
		'information-administrator-name-as-stored'       => array( 'send' => 'inst-information', 'actor' => 44, 'args' => array( 'manage' => array( 44 ) ) ),
		'information-non-ascii'                          => array( 'send' => 'inst-information', 'actor' => 45, 'args' => array( 'manage' => array( 45 ), 'name' => NAMED_NON_ASCII, 'typed' => TYPED_NON_ASCII ) ),
		'information-typed-in-a-browser'                 => array( 'send' => 'inst-information', 'args' => array( 'typed' => $crlf ) ),
		'declined'                                       => array( 'send' => 'inst-declined' ),
		'declined-site-name'                             => array( 'send' => 'inst-declined', 'site' => SITE_NAMED, 'pages' => array() ),
		'declined-no-address'                            => array( 'send' => 'inst-declined', 'args' => array( 'email' => '' ) ),
		// An invitation to join an institution.
		'invite'                                         => array( 'send' => 'inst-invite', 'args' => array( 'record' => 'recSEED0000000016', 'actor' => 50 ) ),
		'invite-unnamed-institution'                     => array( 'send' => 'inst-invite', 'args' => array( 'record' => 'recSEED0000000017', 'actor' => 51 ) ),
		'invite-no-inviter'                              => array( 'send' => 'inst-invite', 'site' => SITE_NAMED, 'args' => array( 'record' => 'recSEED0000000016', 'actor' => 0 ) ),
		'invite-no-address'                              => array( 'send' => 'inst-invite', 'args' => array( 'record' => 'recSEED0000000016', 'actor' => 50, 'email' => 'not an address' ) ),
		'invite-inviter-name-as-stored'                  => array( 'send' => 'inst-invite', 'args' => array( 'record' => 'recSEED0000000016', 'actor' => 57, 'joins' => array( 57 => $of_16 ) ) ),
		'invite-non-ascii'                               => array( 'send' => 'inst-invite', 'args' => array( 'record' => 'recSEED0000000016', 'actor' => 50, 'renamed' => array( 'recSEED0000000016' => NAMED_NON_ASCII ) ) ),
		// The institution's Collaboration Agreement: to its members, and to the Administrators.
		'agreement-received'                             => array( 'send' => 'agr-received', 'args' => array( 'author' => 50 ) ),
		'agreement-received-one-day-no-uploader'         => array( 'send' => 'agr-received', 'settings' => array( 'agreement_review_days' => 1 ), 'args' => array( 'author' => 0 ) ),
		'agreement-received-institution-page-only'       => array( 'send' => 'agr-received', 'pages' => array( 'institution' ), 'args' => array( 'author' => 51 ) ),
		'agreement-received-mentor-page-only'            => array( 'send' => 'agr-received', 'pages' => array( 'mentor' ), 'args' => array( 'author' => 50 ) ),
		'agreement-received-no-members'                  => array( 'send' => 'agr-received', 'args' => array( 'record' => 'recSEED0000000019', 'author' => 0 ) ),
		'agreement-received-uploader-name-as-stored'     => array( 'send' => 'agr-received', 'args' => array( 'author' => 57, 'joins' => array( 57 => $of_16 ) ) ),
		'agreement-received-site-name'                   => array( 'send' => 'agr-received', 'site' => SITE_NAMED, 'args' => array( 'author' => 50 ) ),
		'agreement-landed'                               => array( 'send' => 'agr-landed', 'args' => array( 'author' => 50 ) ),
		'agreement-landed-one-flag'                      => array( 'send' => 'agr-landed', 'settings' => $named, 'args' => array( 'author' => 50, 'flags' => array( 'JavaScript' ) ) ),
		'agreement-landed-three-flags'                   => array( 'send' => 'agr-landed', 'site' => SITE_NAMED, 'args' => array( 'author' => 51, 'flags' => array( 'JavaScript', 'OpenAction', 'EmbeddedFile' ) ) ),
		'agreement-landed-unnamed-no-uploader'           => array( 'send' => 'agr-landed', 'pages' => array(), 'args' => array( 'record' => 'recSEED0000000017', 'author' => 0 ) ),
		'agreement-landed-no-uploader-name'              => array( 'send' => 'agr-landed', 'args' => array( 'author' => 0 ) ),
		'agreement-landed-institution-page-only'         => array( 'send' => 'agr-landed', 'pages' => array( 'institution' ), 'args' => array( 'author' => 51 ) ),
		'agreement-landed-uploader-name-as-stored'       => array( 'send' => 'agr-landed', 'args' => array( 'author' => 57, 'joins' => array( 57 => $of_16 ) ) ),
		'agreement-landed-non-ascii'                     => array( 'send' => 'agr-landed', 'args' => array( 'author' => 50, 'renamed' => array( 'recSEED0000000016' => NAMED_NON_ASCII ) ) ),
		'agreement-accepted'                             => array( 'send' => 'agr-accepted' ),
		'agreement-accepted-no-page'                     => array( 'send' => 'agr-accepted', 'pages' => array() ),
		'agreement-accepted-institution-page-only'       => array( 'send' => 'agr-accepted', 'pages' => array( 'institution' ) ),
		'agreement-accepted-other-pages-only'            => array( 'send' => 'agr-accepted', 'pages' => array( 'student', 'mentor', 'sponsor', 'administrator' ) ),
		'agreement-accepted-student-page-only'           => array( 'send' => 'agr-accepted', 'pages' => array( 'student' ) ),
		'agreement-accepted-site-name'                   => array( 'send' => 'agr-accepted', 'site' => SITE_NAMED ),
		'agreement-reinstated'                           => array( 'send' => 'agr-reinstated', 'site' => SITE_NAMED ),
		'agreement-reinstated-no-page'                   => array( 'send' => 'agr-reinstated', 'pages' => array() ),
		'agreement-reinstated-institution-page-only'     => array( 'send' => 'agr-reinstated', 'pages' => array( 'institution' ) ),
		'agreement-returned'                             => array( 'send' => 'agr-returned' ),
		'agreement-returned-administrator-with-quotes'   => array( 'send' => 'agr-returned', 'actor' => 41, 'pages' => array() ),
		'agreement-returned-administrator-with-no-name'  => array( 'send' => 'agr-returned', 'actor' => 43, 'site' => SITE_NAMED ),
		'agreement-returned-sponsor-page-only'           => array( 'send' => 'agr-returned', 'pages' => array( 'sponsor' ) ),
		'agreement-returned-administrator-name-as-stored' => array( 'send' => 'agr-returned', 'actor' => 44 ),
		'agreement-returned-non-ascii'                   => array( 'send' => 'agr-returned', 'actor' => 45, 'args' => array( 'typed' => TYPED_NON_ASCII ) ),
		'agreement-returned-typed-in-a-browser'          => array( 'send' => 'agr-returned', 'args' => array( 'typed' => $crlf ) ),
		'agreement-revoked-with-a-contact'               => array( 'send' => 'agr-revoked' ),
		'agreement-revoked-contact-with-no-address'      => array( 'send' => 'agr-revoked', 'args' => array( 'record' => 'recSEED0000000018' ) ),
		'agreement-revoked-no-country'                   => array( 'send' => 'agr-revoked', 'actor' => 43, 'args' => array( 'record' => 'recSEED0000000017' ) ),
		'agreement-revoked-unknown-country'              => array( 'send' => 'agr-revoked', 'site' => SITE_NAMED, 'pages' => array(), 'args' => array( 'record' => 'recSEED0000000020' ) ),
		'agreement-revoked-administrator-page-only'      => array( 'send' => 'agr-revoked', 'pages' => array( 'administrator' ) ),
		'agreement-revoked-administrator-name-as-stored' => array( 'send' => 'agr-revoked', 'actor' => 44 ),
		'agreement-revoked-administrator-with-quotes'    => array( 'send' => 'agr-revoked', 'actor' => 41 ),
		'agreement-revoked-non-ascii'                    => array( 'send' => 'agr-revoked', 'actor' => 45, 'args' => array( 'typed' => TYPED_NON_ASCII ) ),
		'agreement-revoked-typed-in-a-browser'           => array( 'send' => 'agr-revoked', 'args' => array( 'typed' => $crlf ) ),
		'agreement-revoked-no-contact-typed-in-a-browser' => array( 'send' => 'agr-revoked', 'args' => array( 'record' => 'recSEED0000000017', 'typed' => $crlf ) ),
		'reminder-one-waiting'                           => array( 'send' => 'agr-reminder', 'args' => array( 'waiting' => array( 741 ), 'later' => array( 744 ) ) ),
		'reminder-three-waiting-one-day'                 => array( 'send' => 'agr-reminder', 'settings' => array( 'agreement_review_days' => 1 ) + $named, 'args' => array( 'waiting' => array( 741, 742, 743 ), 'later' => array( 744 ) ) ),
		'reminder-none-waiting-too-long'                 => array( 'send' => 'agr-reminder', 'args' => array( 'waiting' => array(), 'later' => array( 744 ) ) ),
		'reminder-sent-today'                            => array( 'send' => 'agr-reminder', 'args' => array( 'waiting' => array( 741 ), 'later' => array(), 'reminded' => true ) ),
		'reminder-site-name'                             => array( 'send' => 'agr-reminder', 'site' => SITE_NAMED, 'args' => array( 'waiting' => array( 741 ), 'later' => array( 744 ) ) ),
		'template-needs-attention'                       => array( 'send' => 'agr-template', 'actor' => 50 ),
		'template-needs-attention-names'                 => array( 'send' => 'agr-template', 'actor' => 50, 'site' => SITE_NAMED, 'settings' => $named, 'args' => array( 'name' => 'Example "Uni" <School> & 100%' ) ),
		// A membership ending: the last member, with and without a profile name, and one of two.
		'member-last-removed'                            => array( 'send' => 'member-last', 'args' => array( 'user' => 53, 'reason' => 'removed', 'profile' => NAMED ) ),
		'member-last-left-no-profile'                    => array( 'send' => 'member-last', 'site' => SITE_NAMED, 'settings' => $named, 'args' => array( 'user' => 53, 'reason' => 'left', 'profile' => '' ) ),
		'member-not-last'                                => array( 'send' => 'member-last', 'args' => array( 'user' => 54, 'reason' => 'revoked', 'profile' => 'Second College' ) ),
		'member-last-revoked-by-the-sync'                => array( 'send' => 'member-last', 'actor' => 0, 'args' => array( 'user' => 56, 'reason' => 'revoked', 'profile' => 'Third College' ) ),
		'member-last-name-as-stored'                     => array( 'send' => 'member-last', 'args' => array( 'user' => 57, 'reason' => 'left', 'profile' => '', 'joins' => array( 57 => $of_19 ) ) ),
		// The sponsor application: the applicant, the logo kept and not, and the Administrators.
		'sponsor-applied'                                => array( 'send' => 'spon-applied' ),
		'sponsor-applied-logo-not-kept'                  => array( 'send' => 'spon-applied', 'args' => array( 'skipped' => true ) ),
		'sponsor-applied-names'                          => array( 'send' => 'spon-applied', 'site' => SITE_NAMED, 'pages' => array(), 'args' => array( 'skipped' => true, 'name' => 'Acme & "Sons" 50% Hosting' ) ),
		'sponsor-applied-name-from-the-title'            => array( 'send' => 'spon-applied', 'args' => array( 'name' => '' ) ),
		// A row held or flagged for something other than its logo: the logo's line is for files-skipped alone.
		'sponsor-applied-held-no-logo-sent'              => array( 'send' => 'spon-applied', 'args' => array( 'signals' => array( 'site-ceiling' ) ) ),
		'sponsor-applied-logo-failed'                    => array( 'send' => 'spon-applied', 'args' => array( 'signals' => array( 'logo-failed' ) ) ),
		'sponsor-applied-duplicate'                      => array( 'send' => 'spon-applied', 'args' => array( 'signals' => array( 'duplicate', 'in-base' ) ) ),
		'sponsor-applied-non-ascii'                      => array( 'send' => 'spon-applied', 'args' => array( 'name' => SPONSOR_NON_ASCII ) ),
		'sponsor-applied-no-address'                     => array( 'send' => 'spon-applied', 'args' => array( 'email' => 'not an address' ) ),
		'sponsor-application-every-administrator'        => array( 'send' => 'spon-application' ),
		'sponsor-application-named-reviewers'            => array( 'send' => 'spon-application', 'settings' => $snamed ),
		'sponsor-application-bare-fields'                => array( 'send' => 'spon-application', 'site' => SITE_NAMED, 'pages' => array( 'administrator' ), 'args' => array( 'website' => '', 'person' => '', 'name' => 'Acme & "Sons" 50% {company_name}' ) ),
		'sponsor-application-no-administrator'           => array( 'send' => 'spon-application', 'args' => array( 'nobody' => true ) ),
		'sponsor-application-contact-with-quotes'        => array( 'send' => 'spon-application', 'args' => array( 'person' => "Seán O'Example & Ana" ) ),
		'sponsor-information'                            => array( 'send' => 'spon-information' ),
		'sponsor-information-administrator-with-quotes'  => array( 'send' => 'spon-information', 'actor' => 41, 'site' => SITE_NAMED ),
		'sponsor-information-no-address'                 => array( 'send' => 'spon-information', 'args' => array( 'email' => '' ) ),
		'sponsor-information-administrator-with-no-name' => array( 'send' => 'spon-information', 'actor' => 43 ),
		'sponsor-information-administrator-name-as-stored' => array( 'send' => 'spon-information', 'actor' => 44, 'args' => array( 'manage' => array( 44 ) ) ),
		'sponsor-information-non-ascii'                  => array( 'send' => 'spon-information', 'actor' => 45, 'args' => array( 'manage' => array( 45 ), 'name' => SPONSOR_NON_ASCII, 'typed' => TYPED_NON_ASCII ) ),
		'sponsor-information-typed-in-a-browser'         => array( 'send' => 'spon-information', 'args' => array( 'typed' => $crlf ) ),
		'sponsor-declined'                               => array( 'send' => 'spon-declined' ),
		'sponsor-declined-site-name'                     => array( 'send' => 'spon-declined', 'site' => SITE_NAMED ),
		'sponsor-declined-no-address'                    => array( 'send' => 'spon-declined', 'args' => array( 'email' => '' ) ),
		// The sponsor's Collaboration Agreement: to its assigned Administrator, and to its members.
		'sponsor-agreement-received-assigned'            => array( 'send' => 'sagr-received', 'args' => array( 'manager' => 'ida@example.test', 'author' => 60 ) ),
		'sponsor-agreement-received-assigned-no-account' => array( 'send' => 'sagr-received', 'args' => array( 'manager' => 'pat@example.test', 'author' => 61 ) ),
		'sponsor-agreement-received-unassigned-named'    => array( 'send' => 'sagr-received', 'settings' => $snamed, 'args' => array( 'manager' => null, 'author' => 60 ) ),
		'sponsor-agreement-received-unassigned'          => array( 'send' => 'sagr-received', 'pages' => array(), 'args' => array( 'manager' => null, 'author' => 60 ) ),
		'sponsor-agreement-received-bad-address'         => array( 'send' => 'sagr-received', 'args' => array( 'manager' => 'not an address', 'author' => 60 ) ),
		'sponsor-agreement-received-one-day-unnamed'     => array( 'send' => 'sagr-received', 'site' => SITE_NAMED, 'settings' => array( 'agreement_review_days' => 1 ), 'args' => array( 'manager' => 'ida@example.test', 'author' => 0, 'record' => 'recSEED0000000102' ) ),
		'sponsor-agreement-received-uploader-name-as-stored' => array( 'send' => 'sagr-received', 'args' => array( 'manager' => 'ida@example.test', 'author' => 64, 'joins' => array( 64 => $of_101 ) ) ),
		'sponsor-agreement-accepted'                     => array( 'send' => 'sagr-accepted' ),
		'sponsor-agreement-accepted-no-page'             => array( 'send' => 'sagr-accepted', 'pages' => array() ),
		'sponsor-agreement-accepted-sponsor-page-only'   => array( 'send' => 'sagr-accepted', 'pages' => array( 'sponsor' ) ),
		'sponsor-agreement-accepted-student-page-only'   => array( 'send' => 'sagr-accepted', 'pages' => array( 'student' ) ),
		'sponsor-agreement-accepted-no-members'          => array( 'send' => 'sagr-accepted', 'args' => array( 'record' => 'recSEED0000000102' ) ),
		'sponsor-agreement-accepted-site-name'           => array( 'send' => 'sagr-accepted', 'site' => SITE_NAMED ),
		'sponsor-agreement-returned'                     => array( 'send' => 'sagr-returned', 'settings' => array( 'mail_contact' => 'program@example.test' ) ),
		'sponsor-agreement-returned-no-name-no-page'     => array( 'send' => 'sagr-returned', 'actor' => 43, 'pages' => array() ),
		'sponsor-agreement-returned-with-quotes'         => array( 'send' => 'sagr-returned', 'actor' => 41, 'site' => SITE_NAMED, 'pages' => array( 'sponsor' ) ),
		'sponsor-agreement-returned-administrator-name-as-stored' => array( 'send' => 'sagr-returned', 'actor' => 44 ),
		'sponsor-agreement-returned-non-ascii'           => array( 'send' => 'sagr-returned', 'actor' => 45, 'args' => array( 'typed' => TYPED_NON_ASCII ) ),
		'sponsor-agreement-returned-typed-in-a-browser'  => array( 'send' => 'sagr-returned', 'args' => array( 'typed' => $crlf ) ),
		'sponsor-agreement-revoked'                      => array( 'send' => 'sagr-revoked' ),
		'sponsor-agreement-revoked-no-name'              => array( 'send' => 'sagr-revoked', 'actor' => 43, 'site' => SITE_NAMED, 'pages' => array() ),
		'sponsor-agreement-revoked-mentor-page-only'     => array( 'send' => 'sagr-revoked', 'pages' => array( 'mentor' ) ),
		'sponsor-agreement-revoked-administrator-name-as-stored' => array( 'send' => 'sagr-revoked', 'actor' => 44 ),
		'sponsor-agreement-revoked-typed-in-a-browser'   => array( 'send' => 'sagr-revoked', 'args' => array( 'typed' => $crlf ) ),
		'sponsor-agreement-reinstated'                   => array( 'send' => 'sagr-reinstated' ),
		'sponsor-agreement-reinstated-no-page'           => array( 'send' => 'sagr-reinstated', 'site' => SITE_NAMED, 'pages' => array() ),
		'sponsor-agreement-reinstated-institution-page-only'=> array( 'send' => 'sagr-reinstated', 'pages' => array( 'institution' ) ),
		// The Sponsor Dashboard's interests card, through its handler.
		'interest-everything'                            => array( 'send' => 'interest', 'actor' => 60, 'args' => array( 'manager' => 'ida@example.test', 'choices' => array( 'Sponsor a mentor or multiple mentors', 'Sponsor tools or services' ), 'events' => "WordCamp Example 2037\nExample Summit & \"More\"", 'note' => TYPED ) ),
		'interest-note-only-unassigned'                  => array( 'send' => 'interest', 'actor' => 61, 'pages' => array(), 'args' => array( 'manager' => null, 'choices' => array(), 'events' => '', 'note' => TYPED ) ),
		'interest-choices-only-unnamed'                  => array( 'send' => 'interest', 'actor' => 60, 'args' => array( 'manager' => 'pat@example.test', 'record' => 'recSEED0000000102', 'choices' => array( 'Other (please specify)' ), 'events' => '', 'note' => '' ) ),
		'interest-events-only-named'                     => array( 'send' => 'interest', 'actor' => 60, 'settings' => $snamed, 'args' => array( 'manager' => null, 'choices' => array(), 'events' => 'WordCamp Example 2037', 'note' => '' ) ),
		'interest-site-name'                             => array( 'send' => 'interest', 'actor' => 60, 'site' => SITE_NAMED, 'args' => array( 'manager' => 'ida@example.test', 'choices' => array( 'Provide financial support (for program costs)' ), 'events' => '', 'note' => 'One line.' ) ),
		// What a sponsor typed as a browser posts a text box, each line break CRLF.
		'interest-note-typed-in-a-browser'               => array( 'send' => 'interest', 'actor' => 60, 'args' => array( 'manager' => 'ida@example.test', 'choices' => array(), 'events' => "WordCamp Example 2037\r\nExample Summit", 'note' => str_replace( "\n", "\r\n", TYPED ) ) ),
		'interest-nothing'                               => array( 'send' => 'interest', 'actor' => 60, 'args' => array( 'manager' => 'ida@example.test', 'choices' => array(), 'events' => '', 'note' => '' ) ),
		'interest-member-with-no-name'                   => array( 'send' => 'interest', 'actor' => 63, 'args' => array( 'manager' => 'ida@example.test', 'joins' => array( 63 => $of_101 ), 'choices' => array( 'Sponsor tools or services' ), 'events' => '', 'note' => 'One line.' ) ),
		'interest-member-name-as-stored'                 => array( 'send' => 'interest', 'actor' => 64, 'args' => array( 'manager' => 'ida@example.test', 'joins' => array( 64 => $of_101 ), 'choices' => array(), 'events' => '', 'note' => 'One line.' ) ),
		'interest-non-ascii'                             => array( 'send' => 'interest', 'actor' => 60, 'args' => array( 'manager' => 'ida@example.test', 'renamed' => array( 'recSEED0000000101' => SPONSOR_NON_ASCII ), 'choices' => array(), 'events' => 'WordCamp Łódź 2037', 'note' => TYPED_NON_ASCII ) ),
		'mentor-interest'                                => array( 'send' => 'mentor-interest', 'actor' => 60, 'args' => array( 'manager' => 'ida@example.test', 'mentor' => 'recSEED0000000201' ) ),
		'mentor-interest-names-unassigned'               => array( 'send' => 'mentor-interest', 'actor' => 61, 'site' => SITE_NAMED, 'pages' => array(), 'args' => array( 'manager' => null, 'mentor' => 'recSEED0000000202' ) ),
		'mentor-interest-unnamed-sponsor'                => array( 'send' => 'mentor-interest', 'actor' => 60, 'settings' => array( 'sponsor_notify' => 'reviews@example.test' ), 'args' => array( 'manager' => null, 'record' => 'recSEED0000000102', 'mentor' => 'recSEED0000000201' ) ),
		'mentor-interest-sponsored-already'              => array( 'send' => 'mentor-interest', 'actor' => 60, 'args' => array( 'manager' => 'ida@example.test', 'mentor' => 'recSEED0000000203' ) ),
		// To every Administrator, one of them also an institution's member, with every page published.
		'mentor-interest-unassigned-pages'               => array( 'send' => 'mentor-interest', 'actor' => 61, 'args' => array( 'manager' => null, 'mentor' => 'recSEED0000000202' ) ),
		'mentor-interest-non-ascii'                      => array( 'send' => 'mentor-interest', 'actor' => 60, 'args' => array( 'manager' => 'ida@example.test', 'renamed' => array( 'recSEED0000000101' => SPONSOR_NON_ASCII ), 'mentor' => 'recSEED0000000204' ) ),
		// The codes: running low, to the sponsor's members and its Administrator, and a code that
		// did not work, to its Administrator.
		'low-stock'                                      => array( 'send' => 'low-stock', 'args' => array( 'manager' => 'ida@example.test', 'available' => 4, 'low' => 5 ) ),
		'low-stock-one-left'                             => array( 'send' => 'low-stock', 'site' => SITE_NAMED, 'args' => array( 'manager' => 'pat@example.test', 'available' => 1, 'low' => 3 ) ),
		'low-stock-unassigned-no-page'                   => array( 'send' => 'low-stock', 'pages' => array(), 'args' => array( 'manager' => null, 'available' => 0, 'low' => 2 ) ),
		'low-stock-student-page-only'                    => array( 'send' => 'low-stock', 'pages' => array( 'student' ), 'args' => array( 'manager' => 'ida@example.test', 'available' => 2, 'low' => 5 ) ),
		'low-stock-title'                                => array( 'send' => 'low-stock', 'pages' => array( 'sponsor' ), 'args' => array( 'manager' => 'ida@example.test', 'available' => 3, 'low' => 10, 'title' => 'Hosting &lt;3 &amp; "more" 20% {codes_left}' ) ),
		'low-stock-already-sent'                         => array( 'send' => 'low-stock', 'args' => array( 'manager' => 'ida@example.test', 'available' => 1, 'low' => 5, 'sent' => 2088000000 ) ),
		'low-stock-not-below'                            => array( 'send' => 'low-stock', 'args' => array( 'manager' => 'ida@example.test', 'available' => 5, 'low' => 5 ) ),
		'low-stock-administrator-page-only'              => array( 'send' => 'low-stock', 'pages' => array( 'administrator' ), 'args' => array( 'manager' => 'ida@example.test', 'available' => 1, 'low' => 2 ) ),
		'low-stock-link-offer'                           => array( 'send' => 'low-stock', 'args' => array( 'manager' => 'ida@example.test', 'available' => 0, 'low' => 5, 'kind' => 'link' ) ),
		'low-stock-no-members'                           => array( 'send' => 'low-stock', 'args' => array( 'manager' => 'ida@example.test', 'available' => 1, 'low' => 2, 'record' => 'recSEED0000000102' ) ),
		// The highest low mark the Offers form takes, and the count below it.
		'low-stock-at-the-highest-mark'                  => array( 'send' => 'low-stock', 'args' => array( 'manager' => 'ida@example.test', 'available' => 999, 'low' => 1000 ) ),
		'low-stock-non-ascii'                            => array( 'send' => 'low-stock', 'args' => array( 'manager' => 'ida@example.test', 'available' => 1, 'low' => 2, 'renamed' => array( 'recSEED0000000101' => SPONSOR_NON_ASCII ), 'title' => 'Hosting für Studenten &amp; Schüler „100%”' ) ),
		'claim-problem'                                  => array( 'send' => 'claim-problem', 'actor' => 70, 'args' => array( 'manager' => 'ida@example.test', 'index' => 3 ) ),
		'claim-problem-shared-code-unassigned'           => array( 'send' => 'claim-problem', 'actor' => 70, 'site' => SITE_NAMED, 'pages' => array(), 'args' => array( 'manager' => null, 'index' => -1 ) ),
		'claim-problem-title-assigned-no-account'        => array( 'send' => 'claim-problem', 'actor' => 70, 'args' => array( 'manager' => 'pat@example.test', 'index' => 0, 'title' => 'Hosting &lt;3 &amp; "more" 20% {offer_title}' ) ),
		'claim-problem-not-claimed'                      => array( 'send' => 'claim-problem', 'actor' => 70, 'args' => array( 'manager' => 'ida@example.test', 'index' => null ) ),
		'claim-problem-claimant-name-as-stored'          => array( 'send' => 'claim-problem', 'actor' => 71, 'args' => array( 'manager' => 'ida@example.test', 'index' => 2 ) ),
		'claim-problem-non-ascii'                        => array( 'send' => 'claim-problem', 'actor' => 70, 'args' => array( 'manager' => 'ida@example.test', 'index' => 3, 'title' => 'Hosting für Studenten &amp; Schüler „100%”' ) ),
		// A sponsor post sent back to its author, through its handler.
		'post-returned'                                  => array( 'send' => 'post-returned', 'args' => array( 'author' => 60 ) ),
		'post-returned-two-roles-student-page-only'      => array( 'send' => 'post-returned', 'pages' => array( 'student' ), 'args' => array( 'author' => 61, 'title' => 'Hosting &lt;3 &amp; "more" 20% {post_title}' ) ),
		'post-returned-site-name'                        => array( 'send' => 'post-returned', 'site' => SITE_NAMED, 'pages' => array( 'sponsor' ), 'args' => array( 'author' => 60 ) ),
		'post-returned-untitled'                         => array( 'send' => 'post-returned', 'args' => array( 'author' => 60, 'title' => '' ) ),
		'post-returned-author-with-no-name'              => array( 'send' => 'post-returned', 'args' => array( 'author' => 63 ) ),
		// A note as a browser posts a text box, each line break CRLF.
		'post-returned-note-typed-in-a-browser'          => array( 'send' => 'post-returned', 'args' => array( 'author' => 60, 'note' => str_replace( "\n", "\r\n", TYPED ) ) ),
		'post-returned-no-note'                          => array( 'send' => 'post-returned', 'args' => array( 'author' => 60, 'note' => '' ) ),
		'post-returned-author-gone'                      => array( 'send' => 'post-returned', 'args' => array( 'author' => 99 ) ),
		'post-returned-author-name-as-stored'            => array( 'send' => 'post-returned', 'args' => array( 'author' => 64 ) ),
		'post-returned-non-ascii'                        => array( 'send' => 'post-returned', 'args' => array( 'author' => 60, 'title' => 'Nasz pierwszy wpis: część „1” &amp; więcej', 'note' => TYPED_NON_ASCII ) ),
		'post-returned-author-with-no-address'           => array( 'send' => 'post-returned', 'args' => array( 'author' => 65 ) ),
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
 * A post, with its meta.
 *
 * @param int    $id    Its ID.
 * @param string $type  Its type.
 * @param array  $props Its title, date, author and status.
 * @param array  $meta  Key to value.
 */
function post( $id, $type, array $props, array $meta ) {
	$post            = new WP_Post();
	$post->ID        = $id;
	$post->post_type = $type;

	foreach ( $props as $k => $v ) {
		$post->$k = $v;
	}

	$GLOBALS['posts'][ $id ] = $post;
	$GLOBALS['pmeta'][ $id ] = array();

	foreach ( $meta as $k => $v ) {
		$GLOBALS['pmeta'][ $id ][ $k ] = array( $v );
	}
}

/**
 * Run a handler until the redirect or `wp_die()` it ends with.
 *
 * @param callable $handler The handler.
 */
function press( $handler ) {
	try {
		call_user_func( $handler );
	} catch ( WPCPM_Test_Stop $stop ) {
		return;
	}
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
	$GLOBALS['waiting']   = array();
	$GLOBALS['uid']       = (int) $case['actor'];
	$GLOBALS['site_name'] = $case['site'];
	$GLOBALS['opts']      = array(
		'date_format'              => 'F j, Y',
		'time_format'              => 'g:i a',
		WPCPM_Settings::OPT_NAME   => $case['settings'],
	);
	$GLOBALS['umeta']     = memberships();
	$_POST                = array();

	foreach ( array( 'student' => 'WPCPM_Students_Dashboard', 'mentor' => 'WPCPM_Mentors_Dashboard', 'institution' => 'WPCPM_Institutions_Dashboard', 'sponsor' => 'WPCPM_Sponsors_Dashboard', 'administrator' => 'WPCPM_Administrators_Dashboard' ) as $page => $class ) {
		$class::$url = in_array( $page, $case['pages'], true ) ? 'https://example.test/' . $page . '-dashboard/' : '';
	}

	WPCPM_Institutions_Index::$rows = array(
		'recSEED0000000016' => array( 'name' => NAMED . ' ', 'country' => 'PL' ),
		'recSEED0000000017' => array( 'name' => '  ', 'country' => '' ),
		'recSEED0000000018' => array( 'name' => 'Second College', 'country' => 'XX' ),
		'recSEED0000000020' => array( 'name' => 'Third College', 'country' => 'ZZ' ),
	);
	WPCPM_Sponsors_Index::$rows    = array(
		'recSEED0000000101' => array( 'name' => 'Acme & "Sons" 50% Hosting', 'support' => array() ),
		'recSEED0000000102' => array( 'name' => '', 'support' => array() ),
	);
	WPCPM_Sponsors_Index::$manager = null;

	$args = $case['args'];

	// A name a case gives an institution or a sponsor the index holds, in place of its own.
	foreach ( $args['renamed'] ?? array() as $record => $name ) {
		if ( isset( WPCPM_Institutions_Index::$rows[ $record ] ) ) {
			WPCPM_Institutions_Index::$rows[ $record ]['name'] = $name;
		} else {
			WPCPM_Sponsors_Index::$rows[ $record ]['name'] = $name;
		}
	}

	// A membership a case gives an account, beside every case's.
	foreach ( $args['joins'] ?? array() as $user => $meta ) {
		$GLOBALS['umeta'][ $user ] = $meta + ( $GLOBALS['umeta'][ $user ] ?? array() );
	}

	// Who may manage the program: the four Administrators and any a case adds, or, for a case that
	// says so, nobody.
	$GLOBALS['manage'] = empty( $args['nobody'] ) ? array_merge( array( 40, 41, 43, 55 ), $args['manage'] ?? array() ) : array();

	if ( array_key_exists( 'manager', $args ) && null !== $args['manager'] ) {
		WPCPM_Sponsors_Index::$manager = array( 'email' => $args['manager'] );
	}

	switch ( $case['send'] ) {
		case 'inst-applied':
		case 'inst-application':
		case 'inst-information':
		case 'inst-declined':
			$routing = array_key_exists( 'routing', $args ) ? '' : array( 'email' => 'pl-contact@example.test', 'calendly' => $args['calendly'] ?? 'https://calendly.example.test/pl-contact' );
			post(
				701,
				WPCPM_Institution_Application::POST_TYPE,
				array( 'post_title' => $args['name'] ?? 'Example Polytechnic', 'post_date' => '2036-03-09 18:05:00' ),
				array(
					WPCPM_Institution_Application::META_FIELDS       => array(
						'Name'           => $args['name'] ?? 'Example Polytechnic',
						'City'           => $args['city'] ?? 'Krakow',
						'Contact Person' => $args['person'] ?? 'Ana Applicant',
						'Contact Email'  => $args['email'] ?? 'ana@example.test',
					),
					WPCPM_Institution_Application::META_REFERENCE    => 'APP-2036-0701',
					WPCPM_Institution_Application::META_COUNTRY_NAME => $args['country'] ?? 'Poland',
					WPCPM_Institution_Application::META_MANAGER      => $routing,
					WPCPM_Institution_Application::META_EMAIL        => 'hashed-address',
					WPCPM_Institution_Application::META_STATE        => WPCPM_Institution_Application::STATE_NEW,
				)
			);

			if ( 'inst-applied' === $case['send'] ) {
				reach( 'WPCPM_Institution_Application', 'mail_applicant' )->invoke( null, 701 );
			} elseif ( 'inst-application' === $case['send'] ) {
				reach( 'WPCPM_Institution_Application', 'mail_managers' )->invoke( null, 701 );
			} else {
				$_POST = array(
					WPCPM_Institutions::FIELD_APPLICATION                       => '701',
					'inst-information' === $case['send'] ? 'wpcpm_question' : 'wpcpm_reason' => $args['typed'] ?? TYPED,
				);
				press( array( new WPCPM_Institutions(), 'inst-information' === $case['send'] ? 'handle_info' : 'handle_reject' ) );
			}
			break;

		case 'inst-invite':
			post(
				721,
				WPCPM_Institution_Invite::POST_TYPE,
				array(),
				array(
					WPCPM_Institution_Invite::META_EMAIL       => $args['email'] ?? 'colleague@example.test',
					WPCPM_Institution_Invite::META_INSTITUTION => $args['record'],
					WPCPM_Institution_Invite::META_ACTOR       => $args['actor'],
				)
			);
			reach( 'WPCPM_Institution_Invite', 'mail_invite' )->invoke( null, 721, 'EXAMPLE-NOT-A-REAL-TOKEN-0721' );
			break;

		case 'agr-received':
		case 'agr-landed':
		case 'agr-accepted':
		case 'agr-reinstated':
		case 'agr-returned':
		case 'agr-revoked':
			$record = $args['record'] ?? 'recSEED0000000016';
			post(
				731,
				WPCPM_Institution_Agreement::POST_TYPE,
				array( 'post_date' => '2036-03-02 09:15:00', 'post_author' => $args['author'] ?? 50 ),
				array(
					WPCPM_Institution_Agreement::META_INSTITUTION => $record,
					WPCPM_Institution_Agreement::META_FLAGS       => $args['flags'] ?? array(),
					WPCPM_Institution_Agreement::META_STATE       => 'submitted',
				)
			);
			$method = array(
				'agr-received'   => 'mail_received',
				'agr-landed'     => 'mail_landed',
				'agr-accepted'   => 'mail_accepted',
				'agr-reinstated' => 'mail_reinstated',
				'agr-returned'   => 'mail_returned',
				'agr-revoked'    => 'mail_revoked',
			)[ $case['send'] ];

			if ( in_array( $case['send'], array( 'agr-returned', 'agr-revoked' ), true ) ) {
				reach( 'WPCPM_Institution_Agreement', $method )->invoke( null, $record, 731, sanitize_textarea_field( $args['typed'] ?? TYPED ) );
			} else {
				reach( 'WPCPM_Institution_Agreement', $method )->invoke( null, $record, 731 );
			}
			break;

		case 'agr-reminder':
			// Waiting longer than the review window by PHP's own clock, which the digest reads, and one
			// that never will be.
			$dates = array( 741 => '2026-01-05 10:00:00', 742 => '2026-02-20 23:30:00', 743 => '2026-03-01 08:00:00', 744 => '2099-01-01 00:00:00' );
			$owner = array( 741 => 'recSEED0000000016', 742 => 'recSEED0000000017', 743 => 'recSEED0000000018', 744 => 'recSEED0000000018' );

			foreach ( array_merge( $args['waiting'], $args['later'] ) as $id ) {
				post( $id, WPCPM_Institution_Agreement::POST_TYPE, array( 'post_date' => $dates[ $id ], 'post_author' => 50 ), array( WPCPM_Institution_Agreement::META_INSTITUTION => $owner[ $id ], WPCPM_Institution_Agreement::META_STATE => 'submitted' ) );
			}

			$GLOBALS['waiting'] = array_merge( $args['waiting'], $args['later'] );

			if ( ! empty( $args['reminded'] ) ) {
				$GLOBALS['opts'][ WPCPM_Institution_Agreement::OPT_REMINDED ] = wp_date( 'Y-m-d' );
			}

			WPCPM_Institution_Agreement::remind();
			break;

		case 'agr-template':
			press(
				static function () use ( $args ) {
					reach( 'WPCPM_Agreement_Generate', 'template_needs_attention' )->invoke( null, 'recSEED0000000016', $args['name'] ?? 'Example University', 'en', new WP_Error( 'wpcpm_template_token', 'The template still holds [Signatory Title] after the merge, and a "<token>" can\'t be printed.' ) );
				}
			);
			break;

		case 'member-last':
			if ( '' !== $args['profile'] ) {
				$GLOBALS['umeta'][ $args['user'] ][ WPCPM_Institution_Members::META_PROFILE ] = array( 'name' => $args['profile'] );
			}

			WPCPM_Institution_Members::detach( $args['user'], $args['reason'], (int) $case['actor'] );
			break;

		case 'spon-applied':
		case 'spon-application':
		case 'spon-information':
		case 'spon-declined':
			post(
				801,
				WPCPM_Sponsor_Application::POST_TYPE,
				array( 'post_title' => 'Acme Title &amp; Co', 'post_date' => '2036-03-09 17:40:00' ),
				array(
					WPCPM_Sponsor_Application::META_FIELDS    => array(
						WPCPM_Sponsor_Application::COL_NAME    => $args['name'] ?? 'Acme Hosting',
						WPCPM_Sponsor_Application::COL_WEBSITE => $args['website'] ?? 'https://acme.example.test',
						WPCPM_Sponsor_Application::COL_PERSON  => $args['person'] ?? 'Sol Applicant',
						WPCPM_Sponsor_Application::COL_EMAIL   => $args['email'] ?? 'sol@example.test',
					),
					WPCPM_Sponsor_Application::META_REFERENCE => 'SAPP-2036-0801',
					WPCPM_Sponsor_Application::META_SIGNALS   => $args['signals'] ?? ( ! empty( $args['skipped'] ) ? array( 'site-ceiling', 'files-skipped' ) : array() ),
					WPCPM_Sponsor_Application::META_STATE     => WPCPM_Sponsor_Application::STATE_NEW,
				)
			);

			if ( 'spon-applied' === $case['send'] ) {
				reach( 'WPCPM_Sponsor_Application', 'mail_applicant' )->invoke( null, 801 );
			} elseif ( 'spon-application' === $case['send'] ) {
				reach( 'WPCPM_Sponsor_Application', 'mail_managers' )->invoke( null, 801 );
			} else {
				$_POST = array(
					WPCPM_Sponsor_Application::FIELD_APPLICATION                => '801',
					'spon-information' === $case['send'] ? 'wpcpm_question' : 'wpcpm_reason' => $args['typed'] ?? TYPED,
				);
				press( array( 'WPCPM_Sponsor_Application', 'spon-information' === $case['send'] ? 'handle_info' : 'handle_reject' ) );
			}
			break;

		case 'sagr-received':
		case 'sagr-accepted':
		case 'sagr-returned':
		case 'sagr-revoked':
		case 'sagr-reinstated':
			$record = $args['record'] ?? 'recSEED0000000101';
			post(
				831,
				WPCPM_Sponsor_Agreement::POST_TYPE,
				array( 'post_date' => '2036-03-03 16:20:00', 'post_author' => $args['author'] ?? 60 ),
				array( WPCPM_Sponsor_Agreement::META_SPONSOR => $record )
			);

			if ( 'sagr-received' === $case['send'] || 'sagr-accepted' === $case['send'] ) {
				reach( 'WPCPM_Sponsor_Agreement', 'sagr-received' === $case['send'] ? 'mail_received' : 'mail_accepted' )->invoke( null, $record, 831 );
			} elseif ( 'sagr-reinstated' === $case['send'] ) {
				reach( 'WPCPM_Sponsor_Agreement', 'mail_reinstated' )->invoke( null, $record );
			} else {
				reach( 'WPCPM_Sponsor_Agreement', 'sagr-returned' === $case['send'] ? 'mail_returned' : 'mail_revoked' )->invoke( null, $record, sanitize_textarea_field( $args['typed'] ?? TYPED ) );
			}
			break;

		case 'interest':
			$_POST = array(
				'wpcpm_sponsor' => $args['record'] ?? 'recSEED0000000101',
				'wpcpm_support' => $args['choices'],
				'wpcpm_events'  => $args['events'],
				'wpcpm_note'    => $args['note'],
			);
			press( array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
			break;

		case 'mentor-interest':
			$_POST = array(
				'wpcpm_sponsor' => $args['record'] ?? 'recSEED0000000101',
				'wpcpm_mentor'  => $args['mentor'],
			);
			press( array( 'WPCPM_Sponsor_Mentors', 'handle_interest' ) );
			break;

		case 'low-stock':
			WPCPM_Sponsor_Codes::$available = $args['available'];
			WPCPM_Sponsor_Claims::maybe_low_stock(
				array(
					'id'       => 851,
					'sponsor'  => $args['record'] ?? 'recSEED0000000101',
					'title'    => $args['title'] ?? 'Hosting for students',
					'kind'     => $args['kind'] ?? WPCPM_Sponsor_Offers::KIND_CODES,
					'low'      => $args['low'],
					'low_sent' => $args['sent'] ?? 0,
				)
			);
			break;

		case 'claim-problem':
			WPCPM_Sponsor_Offers::$offers = array(
				852 => array(
					'id'      => 852,
					'sponsor' => 'recSEED0000000101',
					'title'   => $args['title'] ?? 'Hosting for students',
					'kind'    => WPCPM_Sponsor_Offers::KIND_CODES,
				),
			);
			// The claimant is the account signed in.
			if ( null !== $args['index'] ) {
				$GLOBALS['umeta'][ (int) $case['actor'] ][ WPCPM_Sponsor_Claims::META_CLAIMS ] = array( 852 => array( 'i' => $args['index'], 'at' => 2088000000 ) );
			}

			WPCPM_Sponsor_Claims::report_problem( 852, (int) $case['actor'] );
			break;

		case 'post-returned':
			post(
				861,
				'post',
				array( 'post_title' => $args['title'] ?? 'Our &amp; your "first" post', 'post_status' => 'pending', 'post_author' => $args['author'] ),
				array( WPCPM_Sponsor_Policy::META_POST_SPONSOR => 'recSEED0000000101' )
			);
			$_POST = array(
				'wpcpm_post' => '861',
				'wpcpm_note' => $args['note'] ?? TYPED,
			);
			press( array( 'WPCPM_Sponsor_Posts', 'handle_return' ) );
			break;
	}

	return array_map( 'recorded_form', $GLOBALS['mail'] );
}

/**
 * An email as the recording holds it: every address written "at example.test".
 *
 * @param array $mail What `wp_mail()` was handed.
 * @return array
 */
function recorded_form( array $mail ) {
	$plain = static function ( $text ) {
		return preg_replace( '/([A-Za-z0-9._%+-]+)@example\.test/', '$1 at example.test', (string) $text );
	};

	$mail['to']      = $plain( $mail['to'] );
	$mail['body']    = $plain( $mail['body'] );
	$mail['subject'] = $plain( $mail['subject'] );
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

$fixture = __DIR__ . '/fixtures/institutions-sponsors-1-122-21.json';

if ( in_array( '--record', $argv, true ) ) {
	// Made once, from 1.122.21: a recording made again would be made from whatever the code says
	// then, and would prove nothing.
	if ( file_exists( $fixture ) ) {
		fwrite( STDERR, "The recording exists and is never made twice.\n" );
		exit( 1 );
	}

	$recorded = array(
		'_comment' => 'What each Institutions and Sponsors email said in 1.122.21, recorded through the real senders and WPCPM_Mail by bin/test-institutions-sponsors-text.php --record before the emails moved into the template registry. Invented names, every address written "at example.test", and fake secrets and codes. Never edit by hand: the registry is held to these bytes.',
		'cases'    => array(),
	);

	foreach ( cases() as $name => $case ) {
		$recorded['cases'][ $name ] = emails_of( $case );
	}

	// A text JSON cannot hold stops the recording here, before a file is written.
	file_put_contents( $fixture, json_encode( $recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR ) . "\n" );
	printf( "Recorded %d cases, %d emails, in %s\n", count( $recorded['cases'] ), array_sum( array_map( 'count', $recorded['cases'] ) ), basename( $fixture ) );

	exit( 0 );
}

$recorded = is_file( $fixture ) ? json_decode( (string) file_get_contents( $fixture ), true ) : null;
$cases    = cases();

ck( 'the recording is there, and holds every case', is_array( $recorded ) && isset( $recorded['cases'] ) ? array_keys( $recorded['cases'] ) : null, array_keys( $cases ) );
ck( 'the recording is the one made from 1.122.21', is_file( $fixture ) ? hash_file( 'sha256', $fixture ) : null, '7f90af7ea73ceef3d4853b3afbcc7b078c2ec0d63b3fb19305ab2107c65138ff' );

foreach ( $cases as $name => $case ) {
	$want = isset( $recorded['cases'][ $name ] ) ? $recorded['cases'][ $name ] : null;
	$got  = emails_of( $case );

	ck( $name . ': the same emails, to the same people, in the same order', array_column( $got, 'to' ), null === $want ? null : array_column( $want, 'to' ) );

	foreach ( (array) $want as $n => $mail ) {
		$now = isset( $got[ $n ] ) ? $got[ $n ] : array_fill_keys( array_keys( $mail ), null );

		ck( $name . ' #' . $n . ': logged under its own id', $now['context'], sent_as( $name, $n, $mail['context'] ) );
		ck( $name . ' #' . $n . ': the subject, byte for byte', $now['subject'], $mail['subject'] );
		ck( $name . ' #' . $n . ': the body, byte for byte, the signature and footer included', $now['body'], $mail['body'] );
		ck( $name . ' #' . $n . ': the headers, byte for byte, and no file attached', array( $now['headers'], $now['files'] ), array( $mail['headers'], 0 ) );
	}
}

// Every send site of the two areas, each with an email in the recording, under the id it was sent
// under in 1.122.21: 28 send sites, 26 ids.
$sites = array();
foreach ( $cases as $name => $case ) {
	foreach ( isset( $recorded['cases'][ $name ] ) ? $recorded['cases'][ $name ] : array() as $mail ) {
		$sites[ $case['send'] ][ $mail['context'] ] = true;
	}
}
$sites = array_map( 'array_keys', $sites );
ck(
	'the recording holds an email from every one of the 28 send sites of the two areas, under the id each sent under in 1.122.21',
	$sites,
	array(
		'inst-applied'     => array( 'institution-applied' ),
		'inst-application' => array( 'institution-application' ),
		'inst-information' => array( 'institution-information' ),
		'inst-declined'    => array( 'institution-declined' ),
		'inst-invite'      => array( 'institution-invite' ),
		'agr-received'     => array( 'agreement-received' ),
		'agr-landed'       => array( 'agreement-landed' ),
		'agr-accepted'     => array( 'agreement-accepted' ),
		'agr-reinstated'   => array( 'agreement-accepted' ),
		'agr-returned'     => array( 'agreement-returned' ),
		'agr-revoked'      => array( 'agreement-revoked' ),
		'agr-reminder'     => array( 'agreement-reminder' ),
		'agr-template'     => array( 'agreement-template' ),
		'member-last'      => array( 'member-last' ),
		'spon-applied'     => array( 'sponsor-applied' ),
		'spon-application' => array( 'sponsor-application' ),
		'spon-information' => array( 'sponsor-information' ),
		'spon-declined'    => array( 'sponsor-declined' ),
		'sagr-received'    => array( 'sponsor-agreement-received' ),
		'sagr-accepted'    => array( 'sponsor-agreement-accepted' ),
		'sagr-returned'    => array( 'sponsor-agreement-returned' ),
		'sagr-revoked'     => array( 'sponsor-agreement-revoked' ),
		'sagr-reinstated'  => array( 'sponsor-agreement-reinstated' ),
		'interest'         => array( 'sponsor-interest' ),
		'mentor-interest'  => array( 'sponsor-interest' ),
		'low-stock'        => array( 'offer-low-stock' ),
		'claim-problem'    => array( 'claim-problem' ),
		'post-returned'    => array( 'sponsor-post-returned' ),
	)
);

$text = is_file( $fixture ) ? (string) file_get_contents( $fixture ) : '';
preg_match_all( '/[?&]t=([A-Za-z0-9-]+)/', $text, $tokens );
preg_match_all( '/\(ending ([^)]*)\)/', $text, $endings );
ck(
	'and the recording holds no address, no secret but a fake one, no code but a fake one\'s end, and no record ID but the seeds\'',
	is_file( $fixture ) ? array( preg_match( '/[A-Za-z0-9._%+-]*@[A-Za-z0-9-]+\.[A-Za-z]{2,}/', $text ), array_values( array_unique( array_map( function ( $t ) { return substr( $t, 0, 19 ); }, $tokens[1] ) ) ), array_values( array_unique( $endings[1] ) ), preg_match( '/rec(?!SEED0\d{9})[A-Za-z0-9]{14}/', $text ) ) : null,
	array( 0, array( 'EXAMPLE-NOT-A-REAL-' ), array( '7Q2X', 'AB12' ), 0 )
);

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run it to verify it fails, for want of a recording**

Run: `php bin/test-institutions-sponsors-text.php`
Expected: every check fails against a recording that is not there yet, its hash among them, and the suite ends `187 FAILURE(S) (187 checks)`, exiting 1.

- [ ] **Step 3: Record today's emails, once**

Run: `php bin/test-institutions-sponsors-text.php --record`
Expected: `Recorded 183 cases, 331 emails, in institutions-sponsors-1-122-21.json`. The file is written once, here, against the code as it is before this release, and is never edited by hand. `shasum -a 256 bin/fixtures/institutions-sponsors-1-122-21.json` prints `7f90af7ea73ceef3d4853b3afbcc7b078c2ec0d63b3fb19305ab2107c65138ff`, the hash the suite pins: the recording depends on nothing but the code and the suite (the clock is fixed, the reminder's documents waited since early 2026 or wait until 2099), so a different hash means the code or the suite differs from what this plan was proved against, and the task stops there.

Run it again: `php bin/test-institutions-sponsors-text.php --record`
Expected: `The recording exists and is never made twice.`, exit 1, and the file's hash unchanged.

- [ ] **Step 4: Run it to verify it passes, and that the recording is a fixture the privacy checks accept**

Run, one per command: `php bin/test-institutions-sponsors-text.php`, `php bin/test-fixtures.php`
Expected: `ALL PASS (1511 checks)`; `ALL PASS` (no fixture holds an address of any kind, a refused name or a record ID).

- [ ] **Step 5: Run the whole battery**

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 6: Commit**

```bash
git add bin/test-institutions-sponsors-text.php bin/fixtures/institutions-sponsors-1-122-21.json
git commit -m "Emails E4: record what every Institutions and Sponsors email says in 1.122.21, before it moves"
```

---


### Task 2: The catalog's new ids, and the Institutions' and the Sponsors' shipped text

The catalog names the six new emails and says Collaboration Agreement in full in the institution's agreement names; the registry gains the two areas' files, each sentence the translatable string its sender used, reads each area's file in a scope of its own (E4-6), and gains `mail()`. Nothing sends the new templates yet: the senders move in Tasks 3 and 4, word for word, against Task 1's recording.

**Files:**
- Modify: `includes/mail/class-wpcpm-mail-catalog.php` (six ids; seven names)
- Modify: `includes/mail/class-wpcpm-mail-templates.php` (`AREAS`, `all()`, the new `area()` and `mail()`)
- Create: `includes/mail/templates/institutions.php`, `includes/mail/templates/sponsors.php`
- Modify: `bin/test-mail-catalog.php`, `bin/test-mail-templates.php`, `bin/test-emails-templates.php`

**Interfaces:**
- Consumes: `WPCPM_Mail_Templates::all()`, `get()`, `render()`, `example_values()`, `page_of_type()` and the fill rules of 1.122.19; `WPCPM_Mail::site_name()`; `WPCPM_Institution_Application::ACTION_VERIFY`; `WPCPM_Institution_Invite::accept_url( $token ): string`, `::LIFETIME_DAYS` and `::ACTION_ACCEPT`; `WPCPM_Sponsor_Interests::line( array $choices, array $events, $note, $who, $date ): string`; `WPCPM_Settings::get_value( 'agreement_review_days', 3 )`.
- Produces:
  - `WPCPM_Mail_Catalog::emails()` gains `institution-applied-no-call`, `agreement-landed-flagged`, `agreement-reinstated`, `agreement-revoked-no-contact` (area `institutions`) and `sponsor-applied-no-logo`, `sponsor-mentor-interest` (area `sponsors`), each `array{label:string,module:string,audience:string,test:false}`; the labels of `agreement-received`, `agreement-landed`, `agreement-accepted`, `agreement-returned`, `agreement-revoked`, `agreement-reminder` and `agreement-template` say "Collaboration Agreement" in full.
  - `includes/mail/templates/institutions.php` returns 17 entries and `includes/mail/templates/sponsors.php` 15, each `array{trigger:string,subject:string,body:string,eol?:"\r\n",placeholders:array<string,array{means:string,example:string|Closure,required:bool,body_only:bool}>}`.
  - The placeholders the senders of Tasks 3 and 4 fill: `{site_name}`, `{institution_name}`, `{reference}`, `{verify_link}`, `{booking_link}`, `{applicant_email}`, `{city}`, `{country}`, `{contact_name}`, `{contact_email}`, `{queue_link}`, `{question}`, `{inviter_name}`, `{accept_link}`, `{expiry_date}`, `{uploaded_on}`, `{uploaded_by}`, `{review_window}`, `{scan_flags}`, `{accepted_on}`, `{reinstated_on}`, `{returned_on}`, `{revoked_on}`, `{administrator_name}`, `{note}`, `{country_contact}`, `{dashboard_link}`, `{agreements_waiting}`, `{agreements_overdue}`, `{review_days}`, `{overdue_list}`, `{reason}`, `{language}`, `{institution_record}`, `{name_asked_for}`, `{member_name}`, `{left_on}`, `{ended_because}`; and `{company_name}`, `{website}`, `{returned_by}`, `{interest}`, `{mentor_name}`, `{mentor_profile}`, `{offer_title}`, `{codes_left}`, `{low_mark}`, `{offers_link}`, `{claimant_name}`, `{code_ending}`, `{offers_screen_link}`, `{author_name}`, `{post_title}`, `{edit_link}`.
  - `WPCPM_Mail_Templates::AREAS` is `invitations`, `calls`, `institutions`, `reports`, `sponsors`; `private static function area( $file ): mixed`; `public static function mail( $id, array $values, array $extra = array() ): array`, the `render()`ed subject and body merged with `$extra`, or an empty subject and body when the registry has no such template.

- [ ] **Step 1: The catalog's suite**

The count of the plugin's emails reads 69, and two checks name the Institutions' 17 ids and the Sponsors' 15, each with its name and its audience:

In `bin/test-mail-catalog.php`, replace:

```php

$emails = WPCPM_Mail_Catalog::emails();

ck( 'the catalog names the plugin\'s 63 emails: the 42 contexts it sent before 1.122.19, the institution invitation for an agreement on file, the test of the signature and footer, and the 19 Mentor calls emails of their own since 1.122.21', count( array_filter( $emails, function ( $e ) { return ! in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) ), 63 );
ck( 'and reads the same list twice in a request', WPCPM_Mail_Catalog::emails() === $emails, true );

$bad = array();
```

with:

```php

$emails = WPCPM_Mail_Catalog::emails();

ck( 'the catalog names the plugin\'s 69 emails: the 42 contexts it sent before 1.122.19, the institution invitation for an agreement on file, the test of the signature and footer, the 19 Mentor calls emails of their own since 1.122.21, and the 4 Institutions and 2 Sponsors emails of their own since 1.122.22', count( array_filter( $emails, function ( $e ) { return ! in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) ), 69 );
ck( 'and reads the same list twice in a request', WPCPM_Mail_Catalog::emails() === $emails, true );

$bad = array();
```

In `bin/test-mail-catalog.php`, replace:

```php
	)
);

ck( 'the last member leaving an institution is named, as the Administrators\' email it is', WPCPM_Mail_Catalog::get( 'member-last' ), array( 'label' => 'Institution has no members left, to Administrators', 'module' => 'institutions', 'audience' => 'administrator', 'test' => false ) );

ck( 'the areas are the ones the Log lists, in its order', array_values( WPCPM_Mail_Catalog::modules() ), array( 'Invitations', 'Mentor calls', 'Institutions', 'Semester reports', 'Sponsors', 'WordPress', 'Two Factor', 'Other' ) );
```

with:

```php
	)
);

// Each email of the Institutions and the Sponsors areas that says words of its own has an id of its own
// since 1.122.22: the ids each area was sent under keep their emails, their names reading true of
// them, and six new ones name the emails that said other words under them (the acknowledgement with
// no call to book or whose logo was not kept, the upload the courtesy scan noticed something in, the
// revocation with no country contact) or another email altogether (the agreement put back in force,
// sent as an acceptance until then, and the request to sponsor a mentor, sent as a sponsor's
// interest). The institution's agreement emails are named for the Collaboration Agreement in full.
$named = array();
foreach ( $emails as $id => $e ) {
	if ( in_array( $e['module'], array( 'institutions', 'sponsors' ), true ) ) {
		$named[ $e['module'] ][ $id ] = $e['label'] . ' / ' . ( '' === $e['audience'] ? 'the account decides' : $e['audience'] );
	}
}
ck(
	'the Institutions area names its thirteen ids of before 1.122.22 and the four emails of their own since, each with who receives it',
	isset( $named['institutions'] ) ? $named['institutions'] : null,
	array(
		'institution-applied'          => 'Application received, to the applicant / applicant',
		'institution-applied-no-call'  => 'Application received, to an applicant with no call to book / applicant',
		'institution-application'      => 'New institution application, to Administrators / administrator',
		'institution-information'      => 'Question about an application / applicant',
		'institution-declined'         => 'Application declined / applicant',
		'institution-invite'           => 'Invitation to join an institution / institution',
		'agreement-received'           => 'Collaboration Agreement received, to the institution / institution',
		'agreement-landed'             => 'Collaboration Agreement uploaded, to Administrators / administrator',
		'agreement-landed-flagged'     => 'Collaboration Agreement uploaded with the scan\'s notes, to Administrators / administrator',
		'agreement-accepted'           => 'Collaboration Agreement accepted, to the institution / institution',
		'agreement-reinstated'         => 'Collaboration Agreement back in force, to the institution / institution',
		'agreement-returned'           => 'Collaboration Agreement returned with a note, to the institution / institution',
		'agreement-revoked'            => 'Collaboration Agreement taken out of force, to the institution / institution',
		'agreement-revoked-no-contact' => 'Collaboration Agreement taken out of force, to an institution with no country contact / institution',
		'agreement-reminder'           => 'Collaboration Agreements waiting for review / administrator',
		'agreement-template'           => 'Collaboration Agreement could not be generated / administrator',
		'member-last'                  => 'Institution has no members left, to Administrators / administrator',
	)
);
ck(
	'and the Sponsors area its thirteen ids of before 1.122.22 and the two emails of their own since; the low codes go to the sponsor\'s accounts and its Administrator in the same words, so the account decides',
	isset( $named['sponsors'] ) ? $named['sponsors'] : null,
	array(
		'sponsor-applied'              => 'Sponsor application received, to the applicant / applicant',
		'sponsor-applied-no-logo'      => 'Sponsor application received, to an applicant whose logo was not kept / applicant',
		'sponsor-application'          => 'New sponsor application, to Administrators / administrator',
		'sponsor-information'          => 'Question about a sponsor application / applicant',
		'sponsor-declined'             => 'Sponsor application declined / applicant',
		'sponsor-agreement-received'   => 'Collaboration Agreement received, to Administrators / administrator',
		'sponsor-agreement-accepted'   => 'Collaboration Agreement accepted, to the sponsor / sponsor',
		'sponsor-agreement-returned'   => 'Collaboration Agreement returned with a note, to the sponsor / sponsor',
		'sponsor-agreement-revoked'    => 'Collaboration Agreement taken out of force, to the sponsor / sponsor',
		'sponsor-agreement-reinstated' => 'Collaboration Agreement back in force, to the sponsor / sponsor',
		'sponsor-interest'             => 'Sponsor interest / administrator',
		'sponsor-mentor-interest'      => 'Request to sponsor a mentor / administrator',
		'offer-low-stock'              => 'Codes running low / the account decides',
		'claim-problem'                => 'Problem with a claimed code / administrator',
		'sponsor-post-returned'        => 'Sponsor post returned with a note / sponsor',
	)
);

ck( 'the last member leaving an institution is named, as the Administrators\' email it is', WPCPM_Mail_Catalog::get( 'member-last' ), array( 'label' => 'Institution has no members left, to Administrators', 'module' => 'institutions', 'audience' => 'administrator', 'test' => false ) );

ck( 'the areas are the ones the Log lists, in its order', array_values( WPCPM_Mail_Catalog::modules() ), array( 'Invitations', 'Mentor calls', 'Institutions', 'Semester reports', 'Sponsors', 'WordPress', 'Two Factor', 'Other' ) );
```

- [ ] **Step 2: The registry's suite**

The registry lists fifty-nine templates in the areas' order; each area's file is read alone, in a scope of its own, and must raise no warning, and `area()` must hold the class's one `require` (E4-6); no area's file may name `DAY_IN_SECONDS` or `WEEK_IN_SECONDS` (E4-6); the comment above the Warsaw check says it reads Warsaw's clock alone (E4-6); the check that every template is whole reads the new templates' line endings and their required and body-only placeholders; the examples' links and names take the new ones; two new checks pin the new examples' dates on the site's clock and their fake secrets. The suite loads the four classes the examples are worked out with, and a stand-in for `admin_url()`:

In `bin/test-mail-templates.php`, replace:

```php
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
```

with:

```php
 *
 * - **The catalog stays the one list of names.** Every template's id is a catalog id, its name, area
 *   and audience are the catalog's; the Invitations are the four invitations and the institution's
 *   second one, for an institution whose Collaboration Agreement is on file, and the Mentor calls, the
 *   Institutions, the Semester reports and the Sponsors follow, each email that says different words
 *   a template of its own. Each area's file is read in a scope of its own and leans on no other's.
 * - **Every template is whole.** Each placeholder its text uses is one it declares, each required one
 *   is in the body, none that goes in the body alone is in the subject, every declared one says what
 *   it means and has an example, and filled with the examples no placeholder is left. The
 *   set-password link is required in every invitation, in its body; an email about a call needs its
 *   time, and a meeting link, and what a student or a mentor typed, go in the body alone; a link
 *   that carries a secret is needed, in the body alone, and so is what an Administrator or a sponsor
 *   wrote. The examples read as a real email to the Administrator looking would: a call tomorrow at
 *   3 pm on their own clock, an agreement decided today, invented names, links of the site's or of
 *   example.test, and fake secrets; and no area's file works a date out by adding seconds.
 * - **Built once a request for each language**, and an edit whose subject fills to nothing, as the
 *   mail layer will clean it, is sent with the shipped subject, since the mail layer sends no email
 *   without one.
```

In `bin/test-mail-templates.php`, replace:

```php
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function wp_login_url( $r = '' ) { return 'https://example.test/wp-login.php'; }
// Core's two forms, `add_query_arg( $args, $url )`, as the example set-password link is built over the
// login page, and `add_query_arg( $key, $value, $url )`, as a report's address is.
function add_query_arg( ...$args ) {
```

with:

```php
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function wp_login_url( $r = '' ) { return 'https://example.test/wp-login.php'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
// Core's two forms, `add_query_arg( $args, $url )`, as the example set-password link is built over the
// login page, and `add_query_arg( $key, $value, $url )`, as a report's address is.
function add_query_arg( ...$args ) {
```

In `bin/test-mail-templates.php`, replace:

```php
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-availability.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-calls.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-semester-report-screen.php';

/** The one setting the footer reads, Contact address in emails, as a check sets it. */
class WPCPM_Settings {
```

with:

```php
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-availability.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-calls.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-semester-report-screen.php';
// And the Institutions' and the Sponsors' examples: an application's confirmation link, an
// invitation's link and how long it works, a sponsor's dated line, and the sponsor an Offers link names.
require_once __DIR__ . '/../includes/modules/class-wpcpm-institution-application.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-institution-invite.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-interests.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';

/** The one setting the footer reads, Contact address in emails, as a check sets it. */
class WPCPM_Settings {
```

In `bin/test-mail-templates.php`, replace:

```php
$all = WPCPM_Mail_Templates::all();

ck(
	'the registry holds the Invitations, the four invitations and the institution\'s second one, then the Mentor calls, then the Semester reports, in that order',
	array_keys( $all ),
	array(
		'invite-student',
```

with:

```php
$all = WPCPM_Mail_Templates::all();

ck(
	'the registry holds the Invitations, the four invitations and the institution\'s second one, then the Mentor calls, the Institutions, the Semester reports and the Sponsors, in that order',
	array_keys( $all ),
	array(
		'invite-student',
```

In `bin/test-mail-templates.php`, replace:

```php
		'call-reminder-mentor',
		'call-reminder-student',
		'session-reminder',
		'report-drafted',
		'report-approved',
		'report-consent',
	)
);

```

with:

```php
		'call-reminder-mentor',
		'call-reminder-student',
		'session-reminder',
		'institution-applied',
		'institution-applied-no-call',
		'institution-application',
		'institution-information',
		'institution-declined',
		'institution-invite',
		'agreement-received',
		'agreement-landed',
		'agreement-landed-flagged',
		'agreement-accepted',
		'agreement-reinstated',
		'agreement-returned',
		'agreement-revoked',
		'agreement-revoked-no-contact',
		'agreement-reminder',
		'agreement-template',
		'member-last',
		'report-drafted',
		'report-approved',
		'report-consent',
		'sponsor-applied',
		'sponsor-applied-no-logo',
		'sponsor-application',
		'sponsor-information',
		'sponsor-declined',
		'sponsor-agreement-received',
		'sponsor-agreement-accepted',
		'sponsor-agreement-returned',
		'sponsor-agreement-revoked',
		'sponsor-agreement-reinstated',
		'sponsor-interest',
		'sponsor-mentor-interest',
		'offer-low-stock',
		'claim-problem',
		'sponsor-post-returned',
	)
);

```

In `bin/test-mail-templates.php`, replace:

```php
}
ck( 'every template is a catalog id, and its name, area and audience are the catalog\'s', $named, array_fill_keys( array_keys( $all ), true ) );

$areas = array();
foreach ( WPCPM_Mail_Templates::AREAS as $module => $file ) {
	$defined = require __DIR__ . '/../includes/mail/templates/' . $file . '.php';
	foreach ( array_keys( $defined ) as $id ) {
		$areas[ $id ] = array( $module, WPCPM_Mail_Catalog::module_of( $id ) );
	}
}
ck( 'each area\'s file defines that area\'s emails, and every id it defines is the catalog\'s', array_values( array_filter( $areas, function ( $pair ) { return $pair[0] !== $pair[1]; } ) ), array() );
ck( 'and the registry names no email itself: no label, area or audience in a definition', array_values( array_filter( array_map( function ( $file ) { return preg_match( "/'(label|module|audience)'\s*=>/", (string) file_get_contents( __DIR__ . '/../includes/mail/templates/' . $file . '.php' ) ) ? $file : null; }, WPCPM_Mail_Templates::AREAS ) ) ), array() );
ck( 'the institution invitation for an agreement on file is the catalog\'s, in the Invitations area, to institution members', WPCPM_Mail_Catalog::get( 'invite-institution-settled' ), array( 'label' => 'Invitation: institution, agreement on file', 'module' => 'invitations', 'audience' => 'institution', 'test' => false ) );
ck( 'an id the registry does not hold is null', array( WPCPM_Mail_Templates::get( 'call-booked' ), WPCPM_Mail_Templates::render( 'no-such', array() ) ), array( null, null ) );
```

with:

```php
}
ck( 'every template is a catalog id, and its name, area and audience are the catalog\'s', $named, array_fill_keys( array_keys( $all ), true ) );

// Each area's file read alone, in a scope of its own, as the registry reads it: a file that leaned on a
// variable another file set would find it unset here, and say so.
$areas    = array();
$warnings = array();
set_error_handler(
	function ( $number, $message, $file, $line ) use ( &$warnings ) {
		$warnings[] = basename( $file ) . ':' . $line . ': ' . $message;
		return true;
	}
);
foreach ( WPCPM_Mail_Templates::AREAS as $module => $file ) {
	$defined = ( static function ( $path ) {
		return require $path;
	} )( __DIR__ . '/../includes/mail/templates/' . $file . '.php' );
	foreach ( array_keys( (array) $defined ) as $id ) {
		$areas[ $id ] = array( $module, WPCPM_Mail_Catalog::module_of( $id ) );
	}
}
restore_error_handler();
ck( 'each area\'s file defines that area\'s emails, and every id it defines is the catalog\'s', array_values( array_filter( $areas, function ( $pair ) { return $pair[0] !== $pair[1]; } ) ), array() );
ck( 'and each, read alone, leans on nothing another file set: no warning', $warnings, array() );
$registry = (string) file_get_contents( __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php' );
ck( 'and the registry reads each in a scope of its own, through area(), the class\'s one require', array( preg_match_all( '/\brequire(?:_once)?\b/', $registry ), false !== strpos( $registry, 'return require __DIR__ . \'/templates/\' . $file . \'.php\';' ), false !== strpos( $registry, '$defined = self::area( $file );' ) ), array( 1, true, true ) );
// No example day is worked out by adding seconds: a day is not always 86,400 of them, and a date so
// built is an hour off across a change of summer time (1.122.21's fix). Each file moves along the
// clock, with modify().
$by_seconds = array();
foreach ( glob( __DIR__ . '/../includes/mail/templates/*.php' ) as $path ) {
	if ( preg_match( '/\b(?:DAY|WEEK)_IN_SECONDS\b/', (string) file_get_contents( $path ) ) ) {
		$by_seconds[] = basename( $path );
	}
}
ck( 'no area\'s file builds a date by adding seconds', $by_seconds, array() );
ck( 'and the registry names no email itself: no label, area or audience in a definition', array_values( array_filter( array_map( function ( $file ) { return preg_match( "/'(label|module|audience)'\s*=>/", (string) file_get_contents( __DIR__ . '/../includes/mail/templates/' . $file . '.php' ) ) ? $file : null; }, WPCPM_Mail_Templates::AREAS ) ) ), array() );
ck( 'the institution invitation for an agreement on file is the catalog\'s, in the Invitations area, to institution members', WPCPM_Mail_Catalog::get( 'invite-institution-settled' ), array( 'label' => 'Invitation: institution, agreement on file', 'module' => 'invitations', 'audience' => 'institution', 'test' => false ) );
ck( 'an id the registry does not hold is null', array( WPCPM_Mail_Templates::get( 'call-booked' ), WPCPM_Mail_Templates::render( 'no-such', array() ) ), array( null, null ) );
```

In `bin/test-mail-templates.php`, replace:

```php
	);
}
// Each template's line ending, the placeholders it needs, and the ones that go in its body alone: the
// invitations and the two moved emails keep the line ending WordPress gives its own email, as the
// code wrote them; every email about a call needs its time; the mentor's confirmations sent only
// while the mentor has a meeting link need it; and a meeting link and what somebody typed go in the
// body wherever they are.
$shape = array(
	'invite-student'                => array( "\r\n", array( 'set_password_link' ), array( 'set_password_link' ) ),
```

with:

```php
	);
}
// Each template's line ending, the placeholders it needs, and the ones that go in its body alone: the
// invitations, the two moved emails and most of the Institutions' and the Sponsors' keep the line
// ending their code wrote, "\r\n"; every email about a call needs its time; the mentor's
// confirmations sent only while the mentor has a meeting link need it, and so does each email sent
// only with a value its other words lack (a call to book, the scan's notes, a country contact); every
// link that carries a secret is needed, in the body alone; what an Administrator or a sponsor wrote
// is needed where it is the email's point, and goes in the body alone; a post's title and its
// author's name are needed, since either may be empty and its line must stay; and a meeting link, a
// code's last characters and what somebody typed, an applicant's own answers among them, go in the
// body wherever they are.
$shape = array(
	'invite-student'                => array( "\r\n", array( 'set_password_link' ), array( 'set_password_link' ) ),
```

In `bin/test-mail-templates.php`, replace:

```php
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

with:

```php
	'call-reminder-mentor'          => array( "\n", array( 'when' ), array( 'meeting_place', 'topic' ) ),
	'call-reminder-student'         => array( "\n", array( 'when' ), array( 'meeting_place', 'topic' ) ),
	'session-reminder'              => array( "\n", array( 'when' ), array( 'meeting_place', 'session_topic' ) ),
	'institution-applied'           => array( "\r\n", array( 'verify_link', 'booking_link' ), array( 'verify_link', 'applicant_email' ) ),
	'institution-applied-no-call'   => array( "\r\n", array( 'verify_link' ), array( 'verify_link', 'applicant_email' ) ),
	'institution-application'       => array( "\r\n", array(), array( 'city', 'contact_name', 'contact_email' ) ),
	'institution-information'       => array( "\r\n", array( 'question' ), array( 'question' ) ),
	'institution-declined'          => array( "\r\n", array(), array() ),
	'institution-invite'            => array( "\r\n", array( 'accept_link' ), array( 'accept_link' ) ),
	'agreement-received'            => array( "\r\n", array(), array() ),
	'agreement-landed'              => array( "\r\n", array(), array() ),
	'agreement-landed-flagged'      => array( "\r\n", array( 'scan_flags' ), array() ),
	'agreement-accepted'            => array( "\r\n", array(), array() ),
	'agreement-reinstated'          => array( "\r\n", array(), array() ),
	'agreement-returned'            => array( "\r\n", array( 'note' ), array( 'note' ) ),
	'agreement-revoked'             => array( "\r\n", array( 'note', 'country_contact' ), array( 'note' ) ),
	'agreement-revoked-no-contact'  => array( "\r\n", array( 'note' ), array( 'note' ) ),
	'agreement-reminder'            => array( "\r\n", array(), array() ),
	'agreement-template'            => array( "\r\n", array(), array( 'name_asked_for' ) ),
	'member-last'                   => array( "\r\n", array(), array() ),
	'report-drafted'                => array( "\n", array( 'semester' ), array() ),
	'report-approved'               => array( "\n", array( 'semester' ), array() ),
	'report-consent'                => array( "\n", array( 'semester' ), array() ),
	'sponsor-applied'               => array( "\r\n", array(), array( 'applicant_email' ) ),
	'sponsor-applied-no-logo'       => array( "\r\n", array(), array( 'applicant_email' ) ),
	'sponsor-application'           => array( "\r\n", array(), array( 'website', 'contact_name', 'contact_email' ) ),
	'sponsor-information'           => array( "\r\n", array( 'question' ), array( 'question' ) ),
	'sponsor-declined'              => array( "\r\n", array(), array() ),
	'sponsor-agreement-received'    => array( "\r\n", array(), array() ),
	'sponsor-agreement-accepted'    => array( "\r\n", array(), array() ),
	'sponsor-agreement-returned'    => array( "\r\n", array( 'note' ), array( 'note' ) ),
	'sponsor-agreement-revoked'     => array( "\r\n", array( 'note' ), array( 'note' ) ),
	'sponsor-agreement-reinstated'  => array( "\n", array(), array() ),
	'sponsor-interest'              => array( "\n", array( 'interest' ), array( 'interest' ) ),
	'sponsor-mentor-interest'       => array( "\n", array(), array() ),
	'offer-low-stock'               => array( "\n", array(), array() ),
	'claim-problem'                 => array( "\n", array(), array( 'code_ending' ) ),
	'sponsor-post-returned'         => array( "\n", array( 'post_title', 'author_name', 'note' ), array( 'note' ) ),
);
$want_whole = array();
foreach ( $shape as $id => $want ) {
	$want_whole[ $id ] = array( 'trigger' => true, 'undeclared' => array(), 'unused' => array(), 'missing' => array(), 'in_subject' => array(), 'about' => true, 'left' => array(), 'eol' => $want[0], 'link' => array( $want[1], $want[2] ) );
}
ck( 'every template says what sends it, uses only the placeholders it declares and every one it declares, holds its required ones in its body and none that goes in the body in its subject, says what each means with an example, and leaves none unfilled; every invitation needs its set-password link, in its body, every email about a call its time, every link with a secret is needed, in the body alone, and a meeting link and what somebody typed go in the body alone', $whole, $want_whole );

$student = WPCPM_Mail_Templates::render( 'invite-student', WPCPM_Mail_Templates::example_values( 'invite-student' ) );
ck( 'filled with its examples, an invitation carries a marked example link and the dashboard of its audience', array( false !== strpos( $student['body'], 'key=EXAMPLE-KEY-NOT-A-REAL-LINK' ), false !== strpos( $student['body'], "Your Student Report Card:\r\nhttps://example.test/student-report-card/" ), $student['subject'] ), array( true, true, '[WordPress Credits] Welcome to the WordPress Credits Program' ) );
```

In `bin/test-mail-templates.php`, replace:

```php
// The same examples on a clock that changes. An Administrator in Warsaw, whose summer time ends on
// 25 Oct 2026, reads every example session at 3:00 pm, the three of a series and the time a session had
// before it moved, as a real session, kept on its clock and not moved by a count of seconds, would be.
// Read on the real clock, so it can fail only while a change falls within a fortnight of tomorrow, as
// it does in the days before 25 Oct 2026 (and before 1 Nov 2026 for the zones of the United States).
$GLOBALS['umeta'][9]['wpcpm_timezone'] = 'Europe/Warsaw';
$warsaw                                = new DateTimeZone( 'Europe/Warsaw' );
$warsaw_start                          = new DateTimeImmutable( 'tomorrow 15:00', $warsaw );
```

with:

```php
// The same examples on a clock that changes. An Administrator in Warsaw, whose summer time ends on
// 25 Oct 2026, reads every example session at 3:00 pm, the three of a series and the time a session had
// before it moved, as a real session, kept on its clock and not moved by a count of seconds, would be.
// Read on the real clock, and on Warsaw's alone, so it can fail only while Warsaw's clock changes
// within a fortnight of tomorrow, as it does in the days before 25 Oct 2026 and 28 Mar 2027.
$GLOBALS['umeta'][9]['wpcpm_timezone'] = 'Europe/Warsaw';
$warsaw                                = new DateTimeZone( 'Europe/Warsaw' );
$warsaw_start                          = new DateTimeImmutable( 'tomorrow 15:00', $warsaw );
```

In `bin/test-mail-templates.php`, replace:

```php
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
```

with:

```php
		if ( preg_match_all( '#https?://[^\s/]+#', $value, $hosts ) ) {
			$links = array_merge( $links, $hosts[0] );
		}
		if ( in_array( $name, array( 'student_name', 'mentor_name', 'with_whom', 'canceled_by', 'institution_name', 'company_name', 'contact_name', 'inviter_name', 'uploaded_by', 'administrator_name', 'member_name', 'returned_by', 'claimant_name', 'author_name', 'name_asked_for' ), true ) ) {
			$names[] = $value;
		}
	}
}
ck( 'every example link is the site\'s own, example.test\'s or a WordPress.org profile of an invented handle, and every example name is an invented one', array( array_values( array_unique( $links ) ), array_values( array_unique( $names ) ) ), array( array( 'https://example.test', 'https://meet.example.test', 'https://calendly.example.test', 'https://hosting.example.test', 'https://profiles.wordpress.org' ), array( 'Ada Okonjo', 'Ben Lindqvist', 'Example University', 'Cleo Marsh', 'Example Hosting' ) ) );
ck(
	'a semester report\'s examples name the semester running now and its report on the Institution Dashboard, and how many students were still in progress, with their verb',
	array_intersect_key( WPCPM_Mail_Templates::example_values( 'report-drafted' ), array_flip( array( 'semester', 'students_in_progress', 'review_link' ) ) ),
```

In `bin/test-mail-templates.php`, replace:

```php
	)
);

echo "\n=== Built once a request for each language ===\n";

WPCPM_Mail_Templates::all();
```

with:

```php
	)
);

// The Institutions' and the Sponsors' examples read as a real email would on the day it is looked at,
// on the site's clock: an agreement accepted, returned, revoked or put back in force today and
// uploaded yesterday, an invitation that works fourteen days from today, a membership ended today,
// the program's working days as set (three, by default), and every link that carries a secret
// carrying a fake one.
$today    = new DateTimeImmutable( 'now', wp_timezone() );
$examples = array();
foreach ( array( 'agreement-accepted' => 'accepted_on', 'agreement-reinstated' => 'reinstated_on', 'agreement-returned' => 'returned_on', 'agreement-revoked' => 'revoked_on', 'agreement-received' => 'uploaded_on', 'institution-invite' => 'expiry_date', 'member-last' => 'left_on', 'agreement-received ' => 'review_window', 'sponsor-agreement-accepted' => 'accepted_on', 'sponsor-agreement-received' => 'review_window' ) as $id => $name ) {
	$examples[ trim( $id ) . ' ' . $name ] = WPCPM_Mail_Templates::example_values( trim( $id ) )[ $name ];
}
ck(
	'an agreement is decided today and was uploaded yesterday, an invitation works fourteen days from today, a membership ended today, and the program takes the working days set',
	$examples,
	array(
		'agreement-accepted accepted_on'         => $today->format( 'Y-m-d' ),
		'agreement-reinstated reinstated_on'     => $today->format( 'Y-m-d' ),
		'agreement-returned returned_on'         => $today->format( 'Y-m-d' ),
		'agreement-revoked revoked_on'           => $today->format( 'Y-m-d' ),
		'agreement-received uploaded_on'         => $today->modify( '-1 day' )->format( 'Y-m-d' ),
		'institution-invite expiry_date'         => $today->modify( '+14 days' )->format( 'Y-m-d' ),
		'member-last left_on'                    => $today->format( 'F j, Y' ),
		'agreement-received review_window'       => '3 working days',
		'sponsor-agreement-accepted accepted_on' => $today->format( 'Y-m-d' ),
		'sponsor-agreement-received review_window' => '3 working days',
	)
);
$secrets = array();
foreach ( array( 'institution-applied' => 'verify_link', 'institution-invite' => 'accept_link' ) as $id => $name ) {
	parse_str( (string) parse_url( WPCPM_Mail_Templates::example_values( $id )[ $name ], PHP_URL_QUERY ), $query );
	$secrets[ $id ] = array( $query['action'] ?? '', $query['t'] ?? '' );
}
ck(
	'and an application\'s confirmation link and an invitation\'s link are the site\'s own, to the handlers they open, each with a fake secret',
	$secrets,
	array(
		'institution-applied' => array( WPCPM_Institution_Application::ACTION_VERIFY, 'EXAMPLE-NOT-A-REAL-SIGNATURE' ),
		'institution-invite'  => array( WPCPM_Institution_Invite::ACTION_ACCEPT, 'EXAMPLE-NOT-A-REAL-TOKEN' ),
	)
);

echo "\n=== Built once a request for each language ===\n";

WPCPM_Mail_Templates::all();
```

- [ ] **Step 3: The Templates tab's suite**

The tab lists five areas, each in its table and scroll box, the two new areas' rows in the catalog's order, and what sends the first email of each area; the suite loads the four classes the examples use and publishes the Sponsor Dashboard, as it publishes every other dashboard, so the editor loop already in it, which opens and tests every template after the Invitations, finds an example for each Sponsors placeholder:

In `bin/test-emails-templates.php`, replace:

```php
 *   the bar is WordPress's own, Templates, Signature and footer, Log, in that order.
 * - **The Templates tab** lists the templates by area, in the catalog's order, each area in a table
 *   of its own, each row with who receives it, what sends it, where it stands and who edited it last,
 *   and an Edit link to its editor: the Invitations, the Mentor calls and the Semester reports, and
 *   nothing about the areas whose emails are not editable yet.
 * - **The editor**: the boxes hold the shipped text, then the edit read back as it was typed; the
 *   placeholders say what they mean, the required one marked; the preview is filled with the
 *   examples and ends with the footer; Restore asks first, and is there only for an edit.
```

with:

```php
 *   the bar is WordPress's own, Templates, Signature and footer, Log, in that order.
 * - **The Templates tab** lists the templates by area, in the catalog's order, each area in a table
 *   of its own, each row with who receives it, what sends it, where it stands and who edited it last,
 *   and an Edit link to its editor: the Invitations, the Mentor calls, the Institutions, the Semester
 *   reports and the Sponsors, every area of the plugin's emails, since 1.122.22.
 * - **The editor**: the boxes hold the shipped text, then the edit read back as it was typed; the
 *   placeholders say what they mean, the required one marked; the preview is filled with the
 *   examples and ends with the footer; Restore asks first, and is there only for an edit.
```

In `bin/test-emails-templates.php`, replace:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-availability.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-calls.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-semester-report-screen.php';

/** The dashboards, each published at an address of its own. */
class WPCPM_Students_Dashboard {
```

with:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-availability.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-calls.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-semester-report-screen.php';
// And the Institutions' and the Sponsors': an application's confirmation link, an invitation's link
// and how long it works, a sponsor's dated line, and the sponsor an Offers link names.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-invite.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-interests.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-roster.php';

/** The dashboards, each published at an address of its own. */
class WPCPM_Students_Dashboard {
```

In `bin/test-emails-templates.php`, replace:

```php
class WPCPM_Institutions_Dashboard {
	public static function page_url() { return 'https://example.test/institution-dashboard/'; }
}

$fails = 0;
$total = 0;
```

with:

```php
class WPCPM_Institutions_Dashboard {
	public static function page_url() { return 'https://example.test/institution-dashboard/'; }
}
/** As above. */
class WPCPM_Sponsors_Dashboard {
	public static function page_url() { return 'https://example.test/sponsor-dashboard/'; }
}

$fails = 0;
$total = 0;
```

In `bin/test-emails-templates.php`, replace:

```php

preg_match_all( '#<h2>([^<]*)</h2>#', $home, $areas );
preg_match_all( '#<tr><td>([^<]*)</td><td>([^<]*)</td><td>([^<]*)</td><td><span class="wpcpm-emails__state wpcpm-emails__state--([a-z-]+)">([^<]*)</span></td><td>([^<]*)</td><td><a href="([^"]*)">Edit<span class="screen-reader-text"> ([^<]*)</span></a></td></tr>#', $home, $rows, PREG_SET_ORDER );
ck( 'the templates are listed by area, in the catalog\'s order: the Invitations, the Mentor calls and the Semester reports, and nothing about any other', $areas[1], array( 'Invitations', 'Mentor calls', 'Semester reports' ) );
ck(
	'each row names the email, who receives it, what sends it, its state and who edited it last, and links its editor by its name',
	array_map( function ( $row ) { return array( html_entity_decode( $row[1], ENT_QUOTES ), $row[2], $row[5], $row[6], $row[7], $row[8] ); }, array_slice( $rows, 0, 5 ) ),
```

with:

```php

preg_match_all( '#<h2>([^<]*)</h2>#', $home, $areas );
preg_match_all( '#<tr><td>([^<]*)</td><td>([^<]*)</td><td>([^<]*)</td><td><span class="wpcpm-emails__state wpcpm-emails__state--([a-z-]+)">([^<]*)</span></td><td>([^<]*)</td><td><a href="([^"]*)">Edit<span class="screen-reader-text"> ([^<]*)</span></a></td></tr>#', $home, $rows, PREG_SET_ORDER );
ck( 'the templates are listed by area, in the catalog\'s order: the Invitations, the Mentor calls, the Institutions, the Semester reports and the Sponsors', $areas[1], array( 'Invitations', 'Mentor calls', 'Institutions', 'Semester reports', 'Sponsors' ) );
ck(
	'each row names the email, who receives it, what sends it, its state and who edited it last, and links its editor by its name',
	array_map( function ( $row ) { return array( html_entity_decode( $row[1], ENT_QUOTES ), $row[2], $row[5], $row[6], $row[7], $row[8] ); }, array_slice( $rows, 0, 5 ) ),
```

In `bin/test-emails-templates.php`, replace:

```php
	)
);
ck(
	'and after the invitations, every email of the Mentor calls and the Semester reports, each with who receives it, as shipped text, its editor linked by its id',
	array_map( function ( $row ) { return html_entity_decode( $row[1], ENT_QUOTES ) . ' / ' . $row[2] . ' / ' . $row[5] . ' / ' . substr( $row[7], strlen( 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=' ) ); }, array_slice( $rows, 5 ) ),
	array(
		'Call booked, to the mentor / Mentor / Shipped text / call-booked-mentor',
```

with:

```php
	)
);
ck(
	'and after the invitations, every email of the Mentor calls, the Institutions, the Semester reports and the Sponsors, each with who receives it, as shipped text, its editor linked by its id',
	array_map( function ( $row ) { return html_entity_decode( $row[1], ENT_QUOTES ) . ' / ' . $row[2] . ' / ' . $row[5] . ' / ' . substr( $row[7], strlen( 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=' ) ); }, array_slice( $rows, 5 ) ),
	array(
		'Call booked, to the mentor / Mentor / Shipped text / call-booked-mentor',
```

In `bin/test-emails-templates.php`, replace:

```php
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

with:

```php
		'Call reminder, to the mentor / Mentor / Shipped text / call-reminder-mentor',
		'Call reminder, to the student / Student / Shipped text / call-reminder-student',
		'Group session reminder / The people it concerns / Shipped text / session-reminder',
		'Application received, to the applicant / Applicant (no account) / Shipped text / institution-applied',
		'Application received, to an applicant with no call to book / Applicant (no account) / Shipped text / institution-applied-no-call',
		'New institution application, to Administrators / Administrator / Shipped text / institution-application',
		'Question about an application / Applicant (no account) / Shipped text / institution-information',
		'Application declined / Applicant (no account) / Shipped text / institution-declined',
		'Invitation to join an institution / Institution member / Shipped text / institution-invite',
		'Collaboration Agreement received, to the institution / Institution member / Shipped text / agreement-received',
		'Collaboration Agreement uploaded, to Administrators / Administrator / Shipped text / agreement-landed',
		'Collaboration Agreement uploaded with the scan\'s notes, to Administrators / Administrator / Shipped text / agreement-landed-flagged',
		'Collaboration Agreement accepted, to the institution / Institution member / Shipped text / agreement-accepted',
		'Collaboration Agreement back in force, to the institution / Institution member / Shipped text / agreement-reinstated',
		'Collaboration Agreement returned with a note, to the institution / Institution member / Shipped text / agreement-returned',
		'Collaboration Agreement taken out of force, to the institution / Institution member / Shipped text / agreement-revoked',
		'Collaboration Agreement taken out of force, to an institution with no country contact / Institution member / Shipped text / agreement-revoked-no-contact',
		'Collaboration Agreements waiting for review / Administrator / Shipped text / agreement-reminder',
		'Collaboration Agreement could not be generated / Administrator / Shipped text / agreement-template',
		'Institution has no members left, to Administrators / Administrator / Shipped text / member-last',
		'Semester report drafted / Administrator / Shipped text / report-drafted',
		'Semester report approved / Institution member / Shipped text / report-approved',
		'Consent for the semester report / Student / Shipped text / report-consent',
		'Sponsor application received, to the applicant / Applicant (no account) / Shipped text / sponsor-applied',
		'Sponsor application received, to an applicant whose logo was not kept / Applicant (no account) / Shipped text / sponsor-applied-no-logo',
		'New sponsor application, to Administrators / Administrator / Shipped text / sponsor-application',
		'Question about a sponsor application / Applicant (no account) / Shipped text / sponsor-information',
		'Sponsor application declined / Applicant (no account) / Shipped text / sponsor-declined',
		'Collaboration Agreement received, to Administrators / Administrator / Shipped text / sponsor-agreement-received',
		'Collaboration Agreement accepted, to the sponsor / Sponsor member / Shipped text / sponsor-agreement-accepted',
		'Collaboration Agreement returned with a note, to the sponsor / Sponsor member / Shipped text / sponsor-agreement-returned',
		'Collaboration Agreement taken out of force, to the sponsor / Sponsor member / Shipped text / sponsor-agreement-revoked',
		'Collaboration Agreement back in force, to the sponsor / Sponsor member / Shipped text / sponsor-agreement-reinstated',
		'Sponsor interest / Administrator / Shipped text / sponsor-interest',
		'Request to sponsor a mentor / Administrator / Shipped text / sponsor-mentor-interest',
		'Codes running low / The people it concerns / Shipped text / offer-low-stock',
		'Problem with a claimed code / Administrator / Shipped text / claim-problem',
		'Sponsor post returned with a note / Sponsor member / Shipped text / sponsor-post-returned',
	)
);
ck( 'and what sends each is the registry\'s sentence', array( html_entity_decode( $rows[0][3] ?? '', ENT_QUOTES ), html_entity_decode( $rows[5][3] ?? '', ENT_QUOTES ), html_entity_decode( $rows[24][3] ?? '', ENT_QUOTES ), html_entity_decode( $rows[43][3] ?? '', ENT_QUOTES ), html_entity_decode( $rows[58][3] ?? '', ENT_QUOTES ) ), array( WPCPM_Mail_Templates::get( 'invite-student' )['trigger'], WPCPM_Mail_Templates::get( 'call-booked-mentor' )['trigger'], WPCPM_Mail_Templates::get( 'institution-applied' )['trigger'], WPCPM_Mail_Templates::get( 'report-consent' )['trigger'], WPCPM_Mail_Templates::get( 'sponsor-post-returned' )['trigger'] ) );
$templates_css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/emails.css' );
preg_match( '#\.wpcpm-emails__scroll\s*\{([^{}]*)\}#', $templates_css, $scroll_rule );
ck( 'each of the five areas has a table of its own, printed inside a box that scrolls sideways on a narrow screen, held by a position, so the page does not', array( substr_count( $home, '<table' ), preg_match_all( '#<h2>[^<]*</h2><div class="wpcpm-emails__scroll"><table class="widefat striped wpcpm-emails__templates">.*?</table></div>#s', $home ), 1 === preg_match( '/(^|;)\s*overflow-x\s*:\s*auto\s*;/', $scroll_rule[1] ?? '' ), 1 === preg_match( '/(^|;)\s*position\s*:\s*relative\s*;/', $scroll_rule[1] ?? '' ) ), array( 5, 5, true, true ) );
ck( 'no word on the tab says module, and no em or en dash', array( preg_match_all( '/\bmodules?\b/i', words_of( $home ) ), preg_match( '/\x{2013}|\x{2014}/u', $home ) ), array( 0, 0 ) );
ck( 'an Edit link naming an email the registry does not hold opens the list', false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'call-booked' ) ), '<h2>Invitations</h2>' ), true );

```

- [ ] **Step 4: Run them to verify they fail**

Run, one per command: `php bin/test-mail-catalog.php`, `php bin/test-mail-templates.php`, `php bin/test-emails-templates.php`
Expected: `3 FAILURE(S) (25 checks)` (the count and the two areas); `6 FAILURE(S) (87 checks)`, with PHP warnings for the examples the registry does not hold yet (the list, `area()`, the whole-template table, the links and names, and the two new example checks); `4 FAILURE(S) (67 checks)` (the areas, the rows, the triggers and the tables).

- [ ] **Step 5: The catalog**

In `includes/mail/class-wpcpm-mail-catalog.php`, replace:

```php
			'call-reminder-mentor'               => array( __( 'Call reminder, to the mentor', 'wpcredits-program-manager' ), $c, self::TYPE_MENTOR ),
			'call-reminder-student'              => array( __( 'Call reminder, to the student', 'wpcredits-program-manager' ), $c, self::TYPE_STUDENT ),
			'session-reminder'                   => array( __( 'Group session reminder', 'wpcredits-program-manager' ), $c, '' ),
			// Institutions.
			'institution-applied'                => array( __( 'Application received, to the applicant', 'wpcredits-program-manager' ), $n, self::TYPE_APPLICANT ),
			'institution-application'            => array( __( 'New institution application, to Administrators', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			'institution-information'            => array( __( 'Question about an application', 'wpcredits-program-manager' ), $n, self::TYPE_APPLICANT ),
			'institution-declined'               => array( __( 'Application declined', 'wpcredits-program-manager' ), $n, self::TYPE_APPLICANT ),
			'institution-invite'                 => array( __( 'Invitation to join an institution', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-received'                 => array( __( 'Agreement received, to the institution', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-landed'                   => array( __( 'Agreement uploaded, to Administrators', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			'agreement-accepted'                 => array( __( 'Agreement accepted', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-returned'                 => array( __( 'Agreement returned with a note', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-revoked'                  => array( __( 'Agreement taken out of force', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-reminder'                 => array( __( 'Agreements waiting for review', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			'agreement-template'                 => array( __( 'Agreement could not be generated', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			'member-last'                        => array( __( 'Institution has no members left, to Administrators', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			// Semester reports.
			'report-drafted'                     => array( __( 'Semester report drafted', 'wpcredits-program-manager' ), $r, self::TYPE_ADMIN ),
			'report-approved'                    => array( __( 'Semester report approved', 'wpcredits-program-manager' ), $r, self::TYPE_INSTITUTION ),
			'report-consent'                     => array( __( 'Consent for the semester report', 'wpcredits-program-manager' ), $r, self::TYPE_STUDENT ),
			// Sponsors.
			'sponsor-applied'                    => array( __( 'Sponsor application received, to the applicant', 'wpcredits-program-manager' ), $s, self::TYPE_APPLICANT ),
			'sponsor-application'                => array( __( 'New sponsor application, to Administrators', 'wpcredits-program-manager' ), $s, self::TYPE_ADMIN ),
			'sponsor-information'                => array( __( 'Question about a sponsor application', 'wpcredits-program-manager' ), $s, self::TYPE_APPLICANT ),
			'sponsor-declined'                   => array( __( 'Sponsor application declined', 'wpcredits-program-manager' ), $s, self::TYPE_APPLICANT ),
```

with:

```php
			'call-reminder-mentor'               => array( __( 'Call reminder, to the mentor', 'wpcredits-program-manager' ), $c, self::TYPE_MENTOR ),
			'call-reminder-student'              => array( __( 'Call reminder, to the student', 'wpcredits-program-manager' ), $c, self::TYPE_STUDENT ),
			'session-reminder'                   => array( __( 'Group session reminder', 'wpcredits-program-manager' ), $c, '' ),
			// Institutions. Since 1.122.22 each email that says words of its own has an id of its own: the
			// acknowledgement with no call to book, the upload the courtesy scan noticed something in,
			// the agreement put back in force (sent as an acceptance until then) and the revocation with
			// no country contact are new; the agreement's names say Collaboration Agreement in full.
			'institution-applied'                => array( __( 'Application received, to the applicant', 'wpcredits-program-manager' ), $n, self::TYPE_APPLICANT ),
			'institution-applied-no-call'        => array( __( 'Application received, to an applicant with no call to book', 'wpcredits-program-manager' ), $n, self::TYPE_APPLICANT ),
			'institution-application'            => array( __( 'New institution application, to Administrators', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			'institution-information'            => array( __( 'Question about an application', 'wpcredits-program-manager' ), $n, self::TYPE_APPLICANT ),
			'institution-declined'               => array( __( 'Application declined', 'wpcredits-program-manager' ), $n, self::TYPE_APPLICANT ),
			'institution-invite'                 => array( __( 'Invitation to join an institution', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-received'                 => array( __( 'Collaboration Agreement received, to the institution', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-landed'                   => array( __( 'Collaboration Agreement uploaded, to Administrators', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			'agreement-landed-flagged'           => array( __( 'Collaboration Agreement uploaded with the scan\'s notes, to Administrators', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			'agreement-accepted'                 => array( __( 'Collaboration Agreement accepted, to the institution', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-reinstated'               => array( __( 'Collaboration Agreement back in force, to the institution', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-returned'                 => array( __( 'Collaboration Agreement returned with a note, to the institution', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-revoked'                  => array( __( 'Collaboration Agreement taken out of force, to the institution', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-revoked-no-contact'       => array( __( 'Collaboration Agreement taken out of force, to an institution with no country contact', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-reminder'                 => array( __( 'Collaboration Agreements waiting for review', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			'agreement-template'                 => array( __( 'Collaboration Agreement could not be generated', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			'member-last'                        => array( __( 'Institution has no members left, to Administrators', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			// Semester reports.
			'report-drafted'                     => array( __( 'Semester report drafted', 'wpcredits-program-manager' ), $r, self::TYPE_ADMIN ),
			'report-approved'                    => array( __( 'Semester report approved', 'wpcredits-program-manager' ), $r, self::TYPE_INSTITUTION ),
			'report-consent'                     => array( __( 'Consent for the semester report', 'wpcredits-program-manager' ), $r, self::TYPE_STUDENT ),
			// Sponsors. Since 1.122.22 the acknowledgement whose logo was not kept and the request to
			// sponsor a mentor (sent as a sponsor's interest until then) have ids of their own.
			'sponsor-applied'                    => array( __( 'Sponsor application received, to the applicant', 'wpcredits-program-manager' ), $s, self::TYPE_APPLICANT ),
			'sponsor-applied-no-logo'            => array( __( 'Sponsor application received, to an applicant whose logo was not kept', 'wpcredits-program-manager' ), $s, self::TYPE_APPLICANT ),
			'sponsor-application'                => array( __( 'New sponsor application, to Administrators', 'wpcredits-program-manager' ), $s, self::TYPE_ADMIN ),
			'sponsor-information'                => array( __( 'Question about a sponsor application', 'wpcredits-program-manager' ), $s, self::TYPE_APPLICANT ),
			'sponsor-declined'                   => array( __( 'Sponsor application declined', 'wpcredits-program-manager' ), $s, self::TYPE_APPLICANT ),
```

In `includes/mail/class-wpcpm-mail-catalog.php`, replace:

```php
			'sponsor-agreement-revoked'          => array( __( 'Collaboration Agreement taken out of force', 'wpcredits-program-manager' ), $s, self::TYPE_SPONSOR ),
			'sponsor-agreement-reinstated'       => array( __( 'Collaboration Agreement back in force', 'wpcredits-program-manager' ), $s, self::TYPE_SPONSOR ),
			'sponsor-interest'                   => array( __( 'Sponsor interest', 'wpcredits-program-manager' ), $s, self::TYPE_ADMIN ),
			'offer-low-stock'                    => array( __( 'Codes running low', 'wpcredits-program-manager' ), $s, '' ),
			'claim-problem'                      => array( __( 'Problem with a claimed code', 'wpcredits-program-manager' ), $s, self::TYPE_ADMIN ),
			'sponsor-post-returned'              => array( __( 'Sponsor post returned with a note', 'wpcredits-program-manager' ), $s, self::TYPE_SPONSOR ),
```

with:

```php
			'sponsor-agreement-revoked'          => array( __( 'Collaboration Agreement taken out of force, to the sponsor', 'wpcredits-program-manager' ), $s, self::TYPE_SPONSOR ),
			'sponsor-agreement-reinstated'       => array( __( 'Collaboration Agreement back in force, to the sponsor', 'wpcredits-program-manager' ), $s, self::TYPE_SPONSOR ),
			'sponsor-interest'                   => array( __( 'Sponsor interest', 'wpcredits-program-manager' ), $s, self::TYPE_ADMIN ),
			'sponsor-mentor-interest'            => array( __( 'Request to sponsor a mentor', 'wpcredits-program-manager' ), $s, self::TYPE_ADMIN ),
			'offer-low-stock'                    => array( __( 'Codes running low', 'wpcredits-program-manager' ), $s, '' ),
			'claim-problem'                      => array( __( 'Problem with a claimed code', 'wpcredits-program-manager' ), $s, self::TYPE_ADMIN ),
			'sponsor-post-returned'              => array( __( 'Sponsor post returned with a note', 'wpcredits-program-manager' ), $s, self::TYPE_SPONSOR ),
```

Amended on 11 Oct 2026 for the owner's decision: in the same file, the labels of `sponsor-agreement-received`, `sponsor-agreement-accepted` and `sponsor-agreement-returned` gain who receives them as well (", to Administrators" for the first, ", to the sponsor" for the other two), read from each sender's recipients in `includes/modules/class-wpcpm-sponsor-agreement.php`.

- [ ] **Step 6: The registry**

`AREAS` takes the two areas in the catalog's order; `all()` reads each file through `area()`; `mail()` comes before `example_values()`:

In `includes/mail/class-wpcpm-mail-templates.php`, replace:

```php
	 * @var array<string,string>
	 */
	const AREAS = array(
		WPCPM_Mail_Catalog::MODULE_INVITATIONS => 'invitations',
		WPCPM_Mail_Catalog::MODULE_CALLS       => 'calls',
		WPCPM_Mail_Catalog::MODULE_REPORTS     => 'reports',
	);

	/** What a placeholder looks like: a word in braces. */
```

with:

```php
	 * @var array<string,string>
	 */
	const AREAS = array(
		WPCPM_Mail_Catalog::MODULE_INVITATIONS  => 'invitations',
		WPCPM_Mail_Catalog::MODULE_CALLS        => 'calls',
		WPCPM_Mail_Catalog::MODULE_INSTITUTIONS => 'institutions',
		WPCPM_Mail_Catalog::MODULE_REPORTS      => 'reports',
		WPCPM_Mail_Catalog::MODULE_SPONSORS     => 'sponsors',
	);

	/** What a placeholder looks like: a word in braces. */
```

In `includes/mail/class-wpcpm-mail-templates.php`, replace:

```php
		$out = array();

		foreach ( self::AREAS as $file ) {
			$defined = require __DIR__ . '/templates/' . $file . '.php';

			foreach ( (array) $defined as $id => $entry ) {
				$named = WPCPM_Mail_Catalog::get( $id );
```

with:

```php
		$out = array();

		foreach ( self::AREAS as $file ) {
			$defined = self::area( $file );

			foreach ( (array) $defined as $id => $entry ) {
				$named = WPCPM_Mail_Catalog::get( $id );
```

In `includes/mail/class-wpcpm-mail-templates.php`, replace:

```php
		return $out;
	}

	/**
	 * The language the shipped text is built in now: the one WordPress loads translations in, which a
	 * switch into a recipient's language changes.
```

with:

```php
		return $out;
	}

	/**
	 * One area's emails, as its file under includes/mail/templates/ returns them, read in a scope of its
	 * own: a variable one file sets is never seen by another, so each file names its helpers as it
	 * likes and none can lean on another's by accident.
	 *
	 * @param string $file The area's file, without its extension.
	 * @return mixed What the file returns: its emails, by id.
	 */
	private static function area( $file ) {
		return require __DIR__ . '/templates/' . $file . '.php';
	}

	/**
	 * The language the shipped text is built in now: the one WordPress loads translations in, which a
	 * switch into a recipient's language changes.
```

In `includes/mail/class-wpcpm-mail-templates.php`, replace:

```php
		);
	}

	/**
	 * The values an email is previewed and tested with: each placeholder's example, worked out now
	 * where a definition gives a function for it (the site's name, its login page, a dashboard).
```

with:

```php
		);
	}

	/**
	 * An email as a builder of `WPCPM_Mail::send()` hands it to the mail layer: its subject and body as
	 * `render()` fills them with the values given, beside what stays in the sender's code (its Reply-To,
	 * and whether its subject is plain text). With no such template the subject is empty, so the mail
	 * layer sends nothing.
	 *
	 * @param string $id     The email's id.
	 * @param array  $values Each placeholder's value, by name, worked out in the recipient's language.
	 * @param array  $extra  What the builder hands over beside the subject and the body.
	 * @return array
	 */
	public static function mail( $id, array $values, array $extra = array() ) {
		$mail = self::render( $id, $values );

		if ( null === $mail ) {
			$mail = array(
				'subject' => '',
				'body'    => '',
			);
		}

		return array_merge( $mail, $extra );
	}

	/**
	 * The values an email is previewed and tested with: each placeholder's example, worked out now
	 * where a definition gives a function for it (the site's name, its login page, a dashboard).
```

- [ ] **Step 7: The Institutions' emails as shipped**

Create `includes/mail/templates/institutions.php`:

```php
<?php
/**
 * The Institutions area's emails, as the template registry ships them.
 *
 * Read by `WPCPM_Mail_Templates::all()`, which adds each email's name, area and audience from the
 * catalog. Every sentence is the one the code wrote before 1.122.22, under the same translatable
 * string (`WPCPM_Institution_Application`, `WPCPM_Institutions`, `WPCPM_Institution_Invite`,
 * `WPCPM_Institution_Agreement`, `WPCPM_Agreement_Generate` and `WPCPM_Institution_Members`), but
 * the counted ones: a sentence `_n()` used to word whole takes its counted words as one value now,
 * worked out with `_n()` where the email is sent (`{review_window}`, `{agreements_waiting}`,
 * `{agreements_overdue}`), so the words around them can be edited.
 *
 * An email that said other words in one case than in another is two templates: the application's
 * acknowledgement with a call to book and without one, the notice of an upload with the courtesy
 * scan's notes and without them, and the revocation with a country contact named and without one.
 * The agreement put back in force, which was sent under the acceptance's id, has an id of its own.
 *
 * Every body is joined with the line ending the code wrote, "\r\n", and every date is the site's.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Every placeholder an email of the area may have: what each means and its example, a value or a
// function that works one out when a preview or a test asks for it. An email takes the ones it uses
// (`$wpcpm_take`).
$wpcpm_placeholders = static function () {
	// The day an example email is about, on the site's clock, a number of whole days from today.
	$day = static function ( $days = 0 ) {
		return ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( ( $days < 0 ? '-' : '+' ) . abs( (int) $days ) . ' days' )->format( 'Y-m-d' );
	};
	// How many working days the program takes to read an agreement, as set.
	$days = static function () {
		return max( 1, (int) ( class_exists( 'WPCPM_Settings' ) ? WPCPM_Settings::get_value( 'agreement_review_days', 3 ) : 3 ) );
	};

	return array(
		'site_name'          => array(
			'means'   => __( 'The site\'s name.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return WPCPM_Mail::site_name();
			},
		),
		'institution_name'   => array(
			'means'   => __( 'The institution\'s name; its Airtable record when the site holds no name for it.', 'wpcredits-program-manager' ),
			'example' => 'Example University',
		),
		'reference'          => array(
			'means'   => __( 'The application\'s reference, such as APP-2026-0007.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return sprintf( 'APP-%s-0007', wp_date( 'Y' ) );
			},
		),
		'verify_link'        => array(
			'means'     => __( 'The link that confirms the applicant\'s address. It carries a secret, so the email needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => static function () {
				return add_query_arg(
					array(
						'action' => WPCPM_Institution_Application::ACTION_VERIFY,
						'app'    => 7,
						't'      => 'EXAMPLE-NOT-A-REAL-SIGNATURE',
					),
					admin_url( 'admin-post.php' )
				);
			},
			'body_only' => true,
		),
		'booking_link'       => array(
			'means'   => __( 'The link to book a call with the Administrator who looks after the applicant\'s country, which this email is sent only with.', 'wpcredits-program-manager' ),
			'example' => 'https://calendly.example.test/example-contact',
		),
		'applicant_email'    => array(
			'means'     => __( 'The address the application gave, which this email is sent to, as the applicant typed it. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'admissions@example.test',
			'body_only' => true,
		),
		'city'               => array(
			'means'     => __( 'The city the application gave, as the applicant typed it. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'Example City',
			'body_only' => true,
		),
		'country'            => array(
			'means'   => __( 'The country\'s name.', 'wpcredits-program-manager' ),
			'example' => 'Poland',
		),
		'contact_name'       => array(
			'means'     => __( 'The contact person the application gave, as the applicant typed it. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'Ada Okonjo',
			'body_only' => true,
		),
		'contact_email'      => array(
			'means'     => __( 'The contact\'s address, as the applicant typed it. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'ada@example.test',
			'body_only' => true,
		),
		'queue_link'         => array(
			'means'   => __( 'The Institutions screen, where it waits for an Administrator.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return admin_url( 'admin.php?page=wpcpm-institutions' );
			},
		),
		'question'           => array(
			'means'     => __( 'The Administrator\'s question, as they typed it. The email needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => 'Which department would run the internships, and who would sign the Collaboration Agreement?',
			'body_only' => true,
		),
		'inviter_name'       => array(
			'means'   => __( 'The member who sent the invitation.', 'wpcredits-program-manager' ),
			'example' => 'Ada Okonjo',
		),
		'accept_link'        => array(
			'means'     => __( 'The link that accepts the invitation. It carries a secret, so the email needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => static function () {
				return WPCPM_Institution_Invite::accept_url( 'EXAMPLE-NOT-A-REAL-TOKEN' );
			},
			'body_only' => true,
		),
		'expiry_date'        => array(
			'means'   => __( 'The day the invitation\'s link stops working.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $day ) {
				return $day( WPCPM_Institution_Invite::LIFETIME_DAYS );
			},
		),
		'uploaded_on'        => array(
			'means'   => __( 'The day the signed Collaboration Agreement was uploaded.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $day ) {
				return $day( -1 );
			},
		),
		'uploaded_by'        => array(
			'means'   => __( 'Who uploaded it: their name, or, when their account holds none, "a member of your institution" in the institution\'s copy and the institution\'s Airtable record in the Administrators\'.', 'wpcredits-program-manager' ),
			'example' => 'Ada Okonjo',
		),
		'review_window'      => array(
			'means'   => __( 'How long the program takes to read a Collaboration Agreement, counted: "3 working days".', 'wpcredits-program-manager' ),
			'example' => static function () use ( $days ) {
				return sprintf(
					/* translators: %s: a number of working days. */
					_n( '%s working day', '%s working days', $days(), 'wpcredits-program-manager' ),
					number_format_i18n( $days() )
				);
			},
		),
		'scan_flags'         => array(
			'means'   => __( 'What the courtesy scan noticed in the file, which this email is sent only with.', 'wpcredits-program-manager' ),
			'example' => 'JavaScript, OpenAction',
		),
		'accepted_on'        => array(
			'means'   => __( 'The day the Collaboration Agreement was accepted.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $day ) {
				return $day();
			},
		),
		'reinstated_on'      => array(
			'means'   => __( 'The day the Collaboration Agreement was put back in force.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $day ) {
				return $day();
			},
		),
		'returned_on'        => array(
			'means'   => __( 'The day the Collaboration Agreement was sent back.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $day ) {
				return $day();
			},
		),
		'revoked_on'         => array(
			'means'   => __( 'The day the Collaboration Agreement was revoked.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $day ) {
				return $day();
			},
		),
		'administrator_name' => array(
			'means'   => __( 'The Administrator who did it.', 'wpcredits-program-manager' ),
			'example' => 'Cleo Marsh',
		),
		'note'               => array(
			'means'     => __( 'The Administrator\'s note, as they typed it. The email needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => 'Please sign page 2 as well, and date it.',
			'body_only' => true,
		),
		'country_contact'    => array(
			'means'   => __( 'The address of the program\'s contact for the institution\'s country, which this email is sent only with.', 'wpcredits-program-manager' ),
			'example' => 'contact@example.test',
		),
		'dashboard_link'     => array(
			'means'   => __( 'The Institution Dashboard, while its page is published. Empty leaves its line out.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_INSTITUTION );
			},
		),
		'agreements_waiting' => array(
			'means'   => __( 'How many signed Collaboration Agreements are waiting, counted with the verb that goes with the number: "3 signed agreements are".', 'wpcredits-program-manager' ),
			'example' => static function () {
				return sprintf(
					/* translators: %s: a number of signed agreements. */
					_n( '%s signed agreement is', '%s signed agreements are', 2, 'wpcredits-program-manager' ),
					number_format_i18n( 2 )
				);
			},
		),
		'agreements_overdue' => array(
			'means'   => __( 'The same count, with the verb the first line takes: "3 signed agreements have been".', 'wpcredits-program-manager' ),
			'example' => static function () {
				return sprintf(
					/* translators: %s: a number of signed agreements. */
					_n( '%s signed agreement has been', '%s signed agreements have been', 2, 'wpcredits-program-manager' ),
					number_format_i18n( 2 )
				);
			},
		),
		'review_days'        => array(
			'means'   => __( 'How many days the review window is.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $days ) {
				return number_format_i18n( $days() );
			},
		),
		'overdue_list'       => array(
			'means'   => __( 'Each Collaboration Agreement waiting longer than the window, with the day it was uploaded, one to a paragraph.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $day ) {
				$items = array();

				// Two institutions, their agreements uploaded six and five days ago.
				foreach ( array( array( 'Example University', -6 ), array( 'Example College', -5 ) ) as $waiting ) {
					$items[] = sprintf(
						/* translators: 1: institution name, 2: date the agreement was uploaded. */
						__( '%1$s, uploaded %2$s', 'wpcredits-program-manager' ),
						$waiting[0],
						$day( $waiting[1] )
					);
				}

				return implode( "\r\n\r\n", $items );
			},
		),
		'reason'             => array(
			'means'   => __( 'Why the template could not be used, as the site worded it.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return __( 'The agreement template needs attention: it contains a placeholder the generator does not fill in.', 'wpcredits-program-manager' );
			},
		),
		'language'           => array(
			'means'   => __( 'The template\'s language code.', 'wpcredits-program-manager' ),
			'example' => 'en',
		),
		'institution_record' => array(
			'means'   => __( 'The institution\'s Airtable record.', 'wpcredits-program-manager' ),
			'example' => 'recEXAMPLE0000001',
		),
		'name_asked_for'     => array(
			'means'     => __( 'The name the institution asked to print on the Collaboration Agreement, as they typed it. It goes in the body, never in the subject.', 'wpcredits-program-manager' ),
			'example'   => 'Example University',
			'body_only' => true,
		),
		'member_name'        => array(
			'means'   => __( 'The member who is no longer one.', 'wpcredits-program-manager' ),
			'example' => 'Ada Okonjo',
		),
		'left_on'            => array(
			'means'   => __( 'The day the membership ended.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return wp_date( get_option( 'date_format' ) );
			},
		),
		'ended_because'      => array(
			'means'   => __( 'Why the membership ended, as the site records it: removed, left or revoked.', 'wpcredits-program-manager' ),
			'example' => 'left',
		),
	);
};

// The placeholders one email has, in the order given, those named in `$required` required.
$wpcpm_take = static function ( array $names, array $required ) use ( $wpcpm_placeholders ) {
	$all = $wpcpm_placeholders();
	$out = array();

	foreach ( $names as $name ) {
		$out[ $name ] = array(
			'means'     => $all[ $name ]['means'],
			'example'   => $all[ $name ]['example'],
			'required'  => in_array( $name, $required, true ),
			'body_only' => ! empty( $all[ $name ]['body_only'] ),
		);
	}

	return $out;
};

// A body: its paragraphs, a blank line between each two.
$wpcpm_body = static function ( array $paragraphs ) {
	return implode( "\n\n", $paragraphs );
};

// The application's acknowledgement, with the call to book or without it.
$wpcpm_applied = static function ( $call ) use ( $wpcpm_body ) {
	$paragraphs = array(
		sprintf(
			/* translators: %s: the institution's name as it was given on the form. */
			__( 'Thank you for asking about the WordPress Credits Program for %s. Your application is with us.', 'wpcredits-program-manager' ),
			'{institution_name}'
		),
		sprintf(
			/* translators: %s: the application reference, e.g. APP-2026-0007. */
			__( 'Your reference is %s. Please quote it if you write to us about this application.', 'wpcredits-program-manager' ),
			'{reference}'
		),
		__( 'What happens next: an Administrator reads every application by hand and writes back, usually within a few working days. If the program can take your institution, we will set up an account on the program site and send you the Collaboration Agreement to sign.', 'wpcredits-program-manager' ),
		__( 'Please confirm this address by following this link. It tells us we are writing to a real address, and it does nothing else:', 'wpcredits-program-manager' ),
		'{verify_link}',
	);

	if ( $call ) {
		$paragraphs[] = __( 'You can also book a call with the Administrator who looks after your country:', 'wpcredits-program-manager' );
		$paragraphs[] = '{booking_link}';
	}

	$paragraphs[] = sprintf(
		/* translators: 1: the address the message went to, 2: site name. */
		__( 'This message went to %1$s because that address was given on the application form at %2$s. If it was not you, you can ignore it: nothing happens until the address is confirmed.', 'wpcredits-program-manager' ),
		'{applicant_email}',
		'{site_name}'
	);

	return $wpcpm_body( $paragraphs );
};

$wpcpm_applied_subject = sprintf(
	/* translators: 1: site name, 2: the application reference. */
	__( '[%1$s] We have your application: %2$s', 'wpcredits-program-manager' ),
	'{site_name}',
	'{reference}'
);

// The notice of an upload, with what the courtesy scan noticed or with nothing noticed.
$wpcpm_landed = static function ( $flagged ) use ( $wpcpm_body ) {
	return $wpcpm_body(
		array(
			sprintf(
				/* translators: 1: institution name, 2: date, 3: who uploaded it. */
				__( '%1$s uploaded a signed Collaboration Agreement on %2$s (%3$s).', 'wpcredits-program-manager' ),
				'{institution_name}',
				'{uploaded_on}',
				'{uploaded_by}'
			),
			$flagged
				? sprintf(
					/* translators: %s: comma-separated list of PDF feature names. */
					__( 'The courtesy scan noticed: %s. It is a courtesy and not evidence: download the file and open it in a viewer of your choosing.', 'wpcredits-program-manager' ),
					'{scan_flags}'
				)
				: __( 'The courtesy scan noticed nothing. It is a courtesy and not evidence: download the file and open it in a viewer of your choosing.', 'wpcredits-program-manager' ),
			'{queue_link}',
		)
	);
};

$wpcpm_landed_subject = sprintf(
	/* translators: 1: site name, 2: institution name. */
	__( '[%1$s] Signed agreement from %2$s is waiting for review', 'wpcredits-program-manager' ),
	'{site_name}',
	'{institution_name}'
);

// The revocation, with the country's contact named or with the neutral line.
$wpcpm_revoked = static function ( $contact ) use ( $wpcpm_body ) {
	return $wpcpm_body(
		array(
			sprintf(
				/* translators: 1: date, 2: the Administrator's name. */
				__( 'Your Collaboration Agreement was revoked on %1$s by %2$s, with this note:', 'wpcredits-program-manager' ),
				'{revoked_on}',
				'{administrator_name}'
			),
			'{note}',
			__( 'Your account on this site is limited to the agreement panel until an agreement is in force again. Nothing about your students has been deleted.', 'wpcredits-program-manager' ),
			$contact
				? sprintf(
					/* translators: 1: country name, 2: the contact's email address. */
					__( 'Your program contact for %1$s is %2$s.', 'wpcredits-program-manager' ),
					'{country}',
					'{country_contact}'
				)
				: __( 'Write to the Administrator who has been in touch with your institution.', 'wpcredits-program-manager' ),
		)
	);
};

$wpcpm_revoked_subject = sprintf(
	/* translators: %s: site name. */
	__( '[%s] Your agreement has been revoked', 'wpcredits-program-manager' ),
	'{site_name}'
);

return array(
	'institution-applied'          => array(
		'trigger'      => __( 'When an institution applies through the form on the site and its country has a call to book: to the address the application gives.', 'wpcredits-program-manager' ),
		'subject'      => $wpcpm_applied_subject,
		'body'         => $wpcpm_applied( true ),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'institution_name', 'reference', 'verify_link', 'booking_link', 'applicant_email' ), array( 'verify_link', 'booking_link' ) ),
	),
	'institution-applied-no-call'  => array(
		'trigger'      => __( 'When an institution applies through the form on the site and its country has no call to book: to the address the application gives.', 'wpcredits-program-manager' ),
		'subject'      => $wpcpm_applied_subject,
		'body'         => $wpcpm_applied( false ),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'institution_name', 'reference', 'verify_link', 'applicant_email' ), array( 'verify_link' ) ),
	),
	'institution-application'      => array(
		'trigger'      => __( 'When an institution\'s application passes every check: to the addresses in Who reviews agreements, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: institution name. */
			__( '[%1$s] New institution application from %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{institution_name}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'An institution has applied to the program through the form on the site, and the application passed every check.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: institution name, 2: city, 3: country. */
					__( '%1$s, %2$s, %3$s', 'wpcredits-program-manager' ),
					'{institution_name}',
					'{city}',
					'{country}'
				),
				sprintf(
					/* translators: 1: contact person's name, 2: their email address. */
					__( 'Contact: %1$s, %2$s', 'wpcredits-program-manager' ),
					'{contact_name}',
					'{contact_email}'
				),
				__( 'It is on the queue here:', 'wpcredits-program-manager' ),
				'{queue_link}',
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'institution_name', 'city', 'country', 'contact_name', 'contact_email', 'queue_link' ), array() ),
	),
	'institution-information'      => array(
		'trigger'      => __( 'When an Administrator asks an applicant a question about the institution\'s application: to the address the application gives, with the Administrator\'s address to reply to.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: application reference, e.g. APP-2026-0007. */
			__( '[%1$s] A question about your application (%2$s)', 'wpcredits-program-manager' ),
			'{site_name}',
			'{reference}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'Thank you for applying to the WordPress Credits Program. Before an Administrator can take your application further, they have one question:', 'wpcredits-program-manager' ),
				'{question}',
				__( 'Reply to this message and your answer reaches them directly.', 'wpcredits-program-manager' ),
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'reference', 'question' ), array( 'question' ) ),
	),
	'institution-declined'         => array(
		'trigger'      => __( 'When an Administrator rejects an institution\'s application: to the address the application gives. The reason the Administrator wrote stays on the application, and is never sent.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] About your application to the WordPress Credits Program', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'Thank you for applying to the WordPress Credits Program, and for the time you took to write to us.', 'wpcredits-program-manager' ),
				__( 'We have read your institution\'s application, and we cannot accept it into the program at this time. We are sorry not to have better news.', 'wpcredits-program-manager' ),
				__( 'You are welcome to apply again later, for example when your programs change. A new application is read on its own merits, and this decision does not count against it.', 'wpcredits-program-manager' ),
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name' ), array() ),
	),
	'institution-invite'           => array(
		'trigger'      => __( 'When a member of an institution invites a colleague to its account: to the colleague\'s address, with the member\'s address to reply to.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: the institution's name. */
			__( '[%1$s] An invitation to %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{institution_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: the member who invited them, 2: the institution's name, 3: the site's name. */
					__( '%1$s has invited you to see %2$s\'s students in the WordPress Credits Program on %3$s.', 'wpcredits-program-manager' ),
					'{inviter_name}',
					'{institution_name}',
					'{site_name}'
				),
				__( 'Open this link, and press Accept on the page it opens:', 'wpcredits-program-manager' ),
				'{accept_link}',
				sprintf(
					/* translators: %s: the date the invitation stops working. */
					__( 'The link works until %s and can only be used once. If you do not have an account here yet, one is made for you and you get a second email with a link to set your password.', 'wpcredits-program-manager' ),
					'{expiry_date}'
				),
				__( 'If you were not expecting this, ignore this message: opening the link only shows you the invitation, nothing happens until you press Accept, and it stops working on its own. Replying to this message reaches the person who sent it.', 'wpcredits-program-manager' ),
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'inviter_name', 'institution_name', 'accept_link', 'expiry_date' ), array( 'accept_link' ) ),
	),
	'agreement-received'           => array(
		'trigger'      => __( 'When an institution uploads its signed Collaboration Agreement: to every member of the institution.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] We received your signed agreement', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: date the file arrived, 2: who uploaded it. */
					__( 'We received your signed Collaboration Agreement on %1$s, uploaded by %2$s.', 'wpcredits-program-manager' ),
					'{uploaded_on}',
					'{uploaded_by}'
				),
				sprintf(
					/* translators: %s: how long the program takes, counted, such as "3 working days". */
					__( 'An Administrator will read it and you will get an email either way, usually within %s.', 'wpcredits-program-manager' ),
					'{review_window}'
				),
				__( 'If it was the wrong file, withdraw it from the agreement panel on the site and upload the right one.', 'wpcredits-program-manager' ),
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'uploaded_on', 'uploaded_by', 'review_window' ), array() ),
	),
	'agreement-landed'             => array(
		'trigger'      => __( 'When an institution uploads its signed Collaboration Agreement and the courtesy scan notices nothing: to the addresses in Who reviews agreements, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => $wpcpm_landed_subject,
		'body'         => $wpcpm_landed( false ),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'institution_name', 'uploaded_on', 'uploaded_by', 'queue_link' ), array() ),
	),
	'agreement-landed-flagged'     => array(
		'trigger'      => __( 'When an institution uploads its signed Collaboration Agreement and the courtesy scan notices something: to the addresses in Who reviews agreements, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => $wpcpm_landed_subject,
		'body'         => $wpcpm_landed( true ),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'institution_name', 'uploaded_on', 'uploaded_by', 'scan_flags', 'queue_link' ), array( 'scan_flags' ) ),
	),
	'agreement-accepted'           => array(
		'trigger'      => __( 'When an Administrator accepts an institution\'s signed Collaboration Agreement: to every member of the institution.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your agreement is accepted', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: %s: date the agreement was accepted. */
					__( 'Your Collaboration Agreement was accepted on %s. Your account on the site is open.', 'wpcredits-program-manager' ),
					'{accepted_on}'
				),
				__( 'You can now see your students and their progress, open one student to read their Student Report Card, and download your own copy of the signed agreement from the card at the foot of the page.', 'wpcredits-program-manager' ),
				'{dashboard_link}',
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'accepted_on', 'dashboard_link' ), array() ),
	),
	'agreement-reinstated'         => array(
		'trigger'      => __( 'When an Administrator puts an institution\'s revoked Collaboration Agreement back in force: to every member of the institution.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your agreement is in force again', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: %s: date the agreement was put back in force. */
					__( 'Your Collaboration Agreement was put back in force on %s. Your account on the site is open again.', 'wpcredits-program-manager' ),
					'{reinstated_on}'
				),
				__( 'Nothing was removed while it was out of force: your students, their progress and your copy of the signed agreement are where they were.', 'wpcredits-program-manager' ),
				'{dashboard_link}',
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'reinstated_on', 'dashboard_link' ), array() ),
	),
	'agreement-returned'           => array(
		'trigger'      => __( 'When an Administrator sends an institution\'s signed Collaboration Agreement back with a note: to every member of the institution, with the Administrator\'s address to reply to.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your signed agreement needs a change', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: date, 2: the Administrator's name. */
					__( 'Your signed Collaboration Agreement was sent back on %1$s by %2$s, with this note:', 'wpcredits-program-manager' ),
					'{returned_on}',
					'{administrator_name}'
				),
				'{note}',
				__( 'Upload the corrected copy from the agreement panel on the site. Replying to this message reaches the Administrator who wrote the note.', 'wpcredits-program-manager' ),
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'returned_on', 'administrator_name', 'note' ), array( 'note' ) ),
	),
	'agreement-revoked'            => array(
		'trigger'      => __( 'When an Administrator revokes an institution\'s Collaboration Agreement and its country has a program contact: to every member of the institution.', 'wpcredits-program-manager' ),
		'subject'      => $wpcpm_revoked_subject,
		'body'         => $wpcpm_revoked( true ),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'revoked_on', 'administrator_name', 'note', 'country', 'country_contact' ), array( 'note', 'country_contact' ) ),
	),
	'agreement-revoked-no-contact' => array(
		'trigger'      => __( 'When an Administrator revokes an institution\'s Collaboration Agreement and its country has no program contact on file: to every member of the institution.', 'wpcredits-program-manager' ),
		'subject'      => $wpcpm_revoked_subject,
		'body'         => $wpcpm_revoked( false ),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'revoked_on', 'administrator_name', 'note' ), array( 'note' ) ),
	),
	'agreement-reminder'           => array(
		'trigger'      => __( 'Once a day, when signed agreements have waited longer than the review window: to the addresses in Who reviews agreements, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: how many signed agreements, counted with their verb, such as "3 signed agreements are". */
			__( '[%1$s] %2$s waiting for review', 'wpcredits-program-manager' ),
			'{site_name}',
			'{agreements_waiting}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: how many signed agreements, counted with their verb, such as "3 signed agreements have been", 2: number of days in the review window. */
					__( '%1$s waiting longer than the %2$s-day review window.', 'wpcredits-program-manager' ),
					'{agreements_overdue}',
					'{review_days}'
				),
				'{overdue_list}',
				'{queue_link}',
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'agreements_waiting', 'agreements_overdue', 'review_days', 'overdue_list', 'queue_link' ), array() ),
	),
	'agreement-template'           => array(
		'trigger'      => __( 'When an institution asks the site to generate the Collaboration Agreement and the template cannot be used: to the addresses in Who reviews agreements, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] The Collaboration Agreement template needs attention', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'An institution asked the site to generate the Collaboration Agreement and the template could not be used, so nothing was generated and they were told the template needs attention.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: what the template refused with, 2: language code, 3: institution record ID, 4: the name that was asked for. */
					__( 'The reason given was: %1$s (template language %2$s, institution %3$s, name asked for "%4$s").', 'wpcredits-program-manager' ),
					'{reason}',
					'{language}',
					'{institution_record}',
					'{name_asked_for}'
				),
				__( 'The template lives in the plugin, in includes/templates/. A bracketed token other than the institution name is refused on purpose: it would otherwise print on a document somebody signs. Nothing can be generated in this language until it is fixed.', 'wpcredits-program-manager' ),
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'reason', 'language', 'institution_record', 'name_asked_for' ), array() ),
	),
	'member-last'                  => array(
		'trigger'      => __( 'When the last member of an institution leaves or is removed: to the addresses in Who reviews agreements, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: institution name. */
			__( '[%1$s] %2$s has no members left', 'wpcredits-program-manager' ),
			'{site_name}',
			'{institution_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: person, 2: institution name, 3: record ID, 4: reason, 5: date. */
					__( '%1$s is no longer a member of %2$s (%3$s) as of %5$s (%4$s). No account can act for this institution on the site now.', 'wpcredits-program-manager' ),
					'{member_name}',
					'{institution_name}',
					'{institution_record}',
					'{ended_because}',
					'{left_on}'
				),
				__( 'The Airtable record, the agreement, the students and the history are untouched. A former member can be re-added in one click from the Institutions screen, or a new account created there.', 'wpcredits-program-manager' ),
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'member_name', 'institution_name', 'institution_record', 'left_on', 'ended_because' ), array() ),
	),
);
```

- [ ] **Step 8: The Sponsors' emails as shipped**

Create `includes/mail/templates/sponsors.php`:

```php
<?php
/**
 * The Sponsors area's emails, as the template registry ships them.
 *
 * Read by `WPCPM_Mail_Templates::all()`, which adds each email's name, area and audience from the
 * catalog. Every sentence is the one the code wrote before 1.122.22, under the same translatable
 * string (`WPCPM_Sponsor_Application`, `WPCPM_Sponsor_Agreement`, `WPCPM_Sponsor_Interests`,
 * `WPCPM_Sponsor_Mentors`, `WPCPM_Sponsor_Claims` and `WPCPM_Sponsor_Posts`), but two: the program's
 * time to answer, a sentence `_n()` used to word whole, takes its counted words as one value now
 * (`{review_window}`), and the returned agreement's "An Administrator read your Collaboration
 * Agreement", said when the Administrator's account holds no name, is the value `{returned_by}`
 * takes then; and the low codes' two, whose counts the code wrote as numbers (`%d`), hold them as
 * placeholders now (`%s`), the same words.
 *
 * An email that said other words in one case than in another is two templates: the application's
 * acknowledgement with the logo kept and without it. The request to sponsor a mentor, which was sent
 * under the interests card's id, has an id of its own.
 *
 * The emails whose subject carries what a person wrote, an offer's title or a post's, keep that
 * subject as plain text, as their code always did (`plain_subject`, in the sender).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Every placeholder an email of the area may have: what each means and its example, a value or a
// function that works one out when a preview or a test asks for it. An email takes the ones it uses
// (`$wpcpm_take`).
$wpcpm_placeholders = static function () {
	// The day an example email is about, on the site's clock, a number of whole days from today.
	$day = static function ( $days = 0 ) {
		return ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( ( $days < 0 ? '-' : '+' ) . abs( (int) $days ) . ' days' )->format( 'Y-m-d' );
	};

	return array(
		'site_name'          => array(
			'means'   => __( 'The site\'s name.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return WPCPM_Mail::site_name();
			},
		),
		'company_name'       => array(
			'means'   => __( 'The company\'s name; its Airtable record when the site holds no name for it.', 'wpcredits-program-manager' ),
			'example' => 'Example Hosting',
		),
		'reference'          => array(
			'means'   => __( 'The application\'s reference, such as SAPP-2026-0007.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return sprintf( 'SAPP-%s-0007', wp_date( 'Y' ) );
			},
		),
		'applicant_email'    => array(
			'means'     => __( 'The address the application gave, which this email is sent to, as the applicant typed it. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'partners@example.test',
			'body_only' => true,
		),
		'website'            => array(
			'means'     => __( 'The website the application gave, as the applicant typed it. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'https://hosting.example.test',
			'body_only' => true,
		),
		'contact_name'       => array(
			'means'     => __( 'The contact person the application gave, as the applicant typed it. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'Ada Okonjo',
			'body_only' => true,
		),
		'contact_email'      => array(
			'means'     => __( 'The contact\'s address, as the applicant typed it. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'ada@example.test',
			'body_only' => true,
		),
		'queue_link'         => array(
			'means'   => __( 'The Sponsors screen, where it waits for an Administrator.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return admin_url( 'admin.php?page=wpcpm-sponsors&tab=queue' );
			},
		),
		'question'           => array(
			'means'     => __( 'The Administrator\'s question, as they typed it. The email needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => 'Which of your plans would students get, and for how long?',
			'body_only' => true,
		),
		'uploaded_on'        => array(
			'means'   => __( 'The day the signed Collaboration Agreement was uploaded.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $day ) {
				return $day( -1 );
			},
		),
		'uploaded_by'        => array(
			'means'   => __( 'Who uploaded it: their name, or "somebody at the company" when their account holds none.', 'wpcredits-program-manager' ),
			'example' => 'Ada Okonjo',
		),
		'review_window'      => array(
			'means'   => __( 'How long the program takes to answer, counted: "3 working days".', 'wpcredits-program-manager' ),
			'example' => static function () {
				$days = max( 1, (int) ( class_exists( 'WPCPM_Settings' ) ? WPCPM_Settings::get_value( 'agreement_review_days', 3 ) : 3 ) );

				return sprintf(
					/* translators: %s: a number of working days. */
					_n( '%s working day', '%s working days', $days, 'wpcredits-program-manager' ),
					number_format_i18n( $days )
				);
			},
		),
		'accepted_on'        => array(
			'means'   => __( 'The day the Collaboration Agreement was accepted.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $day ) {
				return $day();
			},
		),
		'reinstated_on'      => array(
			'means'   => __( 'The day the Collaboration Agreement was put back in force.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $day ) {
				return $day();
			},
		),
		'returned_by'        => array(
			'means'   => __( 'The Administrator who sent it back, or "An Administrator" when their account holds no name.', 'wpcredits-program-manager' ),
			'example' => 'Cleo Marsh',
		),
		'note'               => array(
			'means'     => __( 'The Administrator\'s note, as they typed it. The email needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => 'Please sign page 2 as well, and date it.',
			'body_only' => true,
		),
		'dashboard_link'     => array(
			'means'   => __( 'The Sponsor Dashboard, while its page is published. Empty leaves its line out.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_SPONSOR );
			},
		),
		'interest'           => array(
			'means'     => __( 'What the sponsor said, as the dated line the Sponsors table keeps: the day, who, the ways ticked, the events and the note as typed. The email needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => static function () {
				return WPCPM_Sponsor_Interests::line( array( 'Sponsor a mentor or multiple mentors' ), array(), 'We could host a study day in the spring.', 'Ada Okonjo', wp_date( 'Y-m-d' ) );
			},
			'body_only' => true,
		),
		'mentor_name'        => array(
			'means'   => __( 'The mentor the sponsor would like to sponsor.', 'wpcredits-program-manager' ),
			'example' => 'Ben Lindqvist',
		),
		'mentor_profile'     => array(
			'means'   => __( 'The mentor\'s WordPress.org profile.', 'wpcredits-program-manager' ),
			'example' => 'https://profiles.wordpress.org/mentor-two/',
		),
		'offer_title'        => array(
			'means'   => __( 'The offer\'s title, as the sponsor wrote it.', 'wpcredits-program-manager' ),
			'example' => 'Hosting for students',
		),
		'codes_left'         => array(
			'means'   => __( 'How many codes the offer has left.', 'wpcredits-program-manager' ),
			'example' => '3',
		),
		'low_mark'           => array(
			'means'   => __( 'The number of codes the offer warns below.', 'wpcredits-program-manager' ),
			'example' => '5',
		),
		'offers_link'        => array(
			'means'   => __( 'The Offers card on the Sponsor Dashboard, for the sponsor the codes are of.', 'wpcredits-program-manager' ),
			'example' => static function () {
				// As the sender builds it: the sponsor named, so an Administrator lands on its card.
				return add_query_arg( WPCPM_Sponsor_Roster::ARG_VIEW, 'recEXAMPLE0000001', WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_SPONSOR ) ) . '#wpcpm-sponsor-offers';
			},
		),
		'claimant_name'      => array(
			'means'   => __( 'The person who claimed the code.', 'wpcredits-program-manager' ),
			'example' => 'Ada Okonjo',
		),
		'code_ending'        => array(
			'means'     => __( 'The code\'s last four characters. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'X7Q2',
			'body_only' => true,
		),
		'offers_screen_link' => array(
			'means'   => __( 'The Offers tab of the Sponsors screen.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return admin_url( 'admin.php?page=wpcpm-sponsors&tab=offers' );
			},
		),
		'author_name'        => array(
			'means'   => __( 'The post\'s author, whose account may hold no name. The email needs it.', 'wpcredits-program-manager' ),
			'example' => 'Ada Okonjo',
		),
		'post_title'         => array(
			'means'   => __( 'The post\'s title, as its author wrote it, which may be empty. The email needs it.', 'wpcredits-program-manager' ),
			'example' => 'Our first study day',
		),
		'edit_link'          => array(
			'means'   => __( 'The list of posts in wp-admin, where the author edits it.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return admin_url( 'edit.php' );
			},
		),
	);
};

// The placeholders one email has, in the order given, those named in `$required` required.
$wpcpm_take = static function ( array $names, array $required ) use ( $wpcpm_placeholders ) {
	$all = $wpcpm_placeholders();
	$out = array();

	foreach ( $names as $name ) {
		$out[ $name ] = array(
			'means'     => $all[ $name ]['means'],
			'example'   => $all[ $name ]['example'],
			'required'  => in_array( $name, $required, true ),
			'body_only' => ! empty( $all[ $name ]['body_only'] ),
		);
	}

	return $out;
};

// A body: its paragraphs, a blank line between each two.
$wpcpm_body = static function ( array $paragraphs ) {
	return implode( "\n\n", $paragraphs );
};

// The application's acknowledgement, with the logo kept or with the line saying it was not.
$wpcpm_applied = static function ( $logo_kept ) use ( $wpcpm_body ) {
	$paragraphs = array(
		sprintf(
			/* translators: %s: the company's name as it was given on the form. */
			__( 'Thank you for offering to sponsor the WordPress Credits Program on behalf of %s. Your application is with us.', 'wpcredits-program-manager' ),
			'{company_name}'
		),
		sprintf(
			/* translators: %s: the application reference, e.g. SAPP-2026-0007. */
			__( 'Your reference is %s. Please quote it if you write to us about this application.', 'wpcredits-program-manager' ),
			'{reference}'
		),
	);

	if ( ! $logo_kept ) {
		$paragraphs[] = __( 'One thing about the logo: the site was taking more applications than usual when yours arrived, so the logo files you attached were not kept. Your answers were, and nothing else about the application was affected. If the program says yes, you can send the logo after your account is set up.', 'wpcredits-program-manager' );
	}

	$paragraphs[] = __( 'What happens next: an Administrator reads every application by hand and checks the company and its products against the program\'s licensing and trademark rules, so a reply takes a few working days. If the program says yes, we will set up an account on the program site and send you a link to choose a password.', 'wpcredits-program-manager' );
	$paragraphs[] = sprintf(
		/* translators: 1: the address the message went to, 2: site name. */
		__( 'This message went to %1$s because that address was given on the sponsor application form at %2$s. If it was not you, you can ignore it: nothing happens until an Administrator decides.', 'wpcredits-program-manager' ),
		'{applicant_email}',
		'{site_name}'
	);

	return $wpcpm_body( $paragraphs );
};

$wpcpm_applied_subject = sprintf(
	/* translators: 1: site name, 2: the application reference. */
	__( '[%1$s] We have your application: %2$s', 'wpcredits-program-manager' ),
	'{site_name}',
	'{reference}'
);

return array(
	'sponsor-applied'              => array(
		'trigger'      => __( 'When a company applies to sponsor the program through the form on the site: to the address the application gives.', 'wpcredits-program-manager' ),
		'subject'      => $wpcpm_applied_subject,
		'body'         => $wpcpm_applied( true ),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'company_name', 'reference', 'applicant_email' ), array() ),
	),
	'sponsor-applied-no-logo'      => array(
		'trigger'      => __( 'When a company applies to sponsor the program through the form on the site while it is taking more applications than usual, so the logo files are not kept: to the address the application gives.', 'wpcredits-program-manager' ),
		'subject'      => $wpcpm_applied_subject,
		'body'         => $wpcpm_applied( false ),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'company_name', 'reference', 'applicant_email' ), array() ),
	),
	'sponsor-application'          => array(
		'trigger'      => __( 'When a sponsor application passes every check: to the addresses in Who is mailed, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: company name. */
			__( '[%1$s] New sponsor application from %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{company_name}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'A company has applied to sponsor the program through the form on the site, and the application passed every check.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: company name, 2: website. */
					__( '%1$s, %2$s', 'wpcredits-program-manager' ),
					'{company_name}',
					'{website}'
				),
				sprintf(
					/* translators: 1: contact person's name, 2: their email address. */
					__( 'Contact: %1$s, %2$s', 'wpcredits-program-manager' ),
					'{contact_name}',
					'{contact_email}'
				),
				__( 'It is on the queue here:', 'wpcredits-program-manager' ),
				'{queue_link}',
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'company_name', 'website', 'contact_name', 'contact_email', 'queue_link' ), array() ),
	),
	'sponsor-information'          => array(
		'trigger'      => __( 'When an Administrator asks a company a question about its sponsor application: to the address the application gives, with the Administrator\'s address to reply to.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: application reference, e.g. SAPP-2026-0007. */
			__( '[%1$s] A question about your application (%2$s)', 'wpcredits-program-manager' ),
			'{site_name}',
			'{reference}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'Thank you for offering to sponsor the WordPress Credits Program. Before an Administrator can take your application further, they have one question:', 'wpcredits-program-manager' ),
				'{question}',
				__( 'Reply to this message and your answer reaches them directly.', 'wpcredits-program-manager' ),
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'reference', 'question' ), array( 'question' ) ),
	),
	'sponsor-declined'             => array(
		'trigger'      => __( 'When an Administrator rejects a sponsor application: to the address the application gives. The reason the Administrator wrote stays on the application, and is never sent.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] About your application to sponsor the WordPress Credits Program', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'Thank you for your interest in sponsoring the WordPress Credits Program, and for the time you took to write to us.', 'wpcredits-program-manager' ),
				__( 'We have read your company\'s application to sponsor the program, and we cannot accept it at this time. We are sorry not to have better news.', 'wpcredits-program-manager' ),
				__( 'You are welcome to apply again later, for example when your products or the program change. A new application is read on its own merits, and this decision does not count against it.', 'wpcredits-program-manager' ),
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name' ), array() ),
	),
	'sponsor-agreement-received'   => array(
		'trigger'      => __( 'When a sponsor uploads its signed Collaboration Agreement: to its assigned Administrator, or, with none, to the addresses in Who is mailed, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: company name. */
			__( '[%1$s] %2$s uploaded a signed Collaboration Agreement', 'wpcredits-program-manager' ),
			'{site_name}',
			'{company_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: 1: company name, 2: date, 3: who uploaded it. */
					__( '%1$s uploaded a signed Collaboration Agreement on %2$s (%3$s).', 'wpcredits-program-manager' ),
					'{company_name}',
					'{uploaded_on}',
					'{uploaded_by}'
				),
				sprintf(
					/* translators: %s: how long the program takes, counted, such as "3 working days". */
					__( 'The program aims to answer within %s.', 'wpcredits-program-manager' ),
					'{review_window}'
				),
				__( 'Accept or return it on the Administrator Dashboard. Read it first on the Sponsors screen:', 'wpcredits-program-manager' ),
				'{queue_link}',
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'company_name', 'uploaded_on', 'uploaded_by', 'review_window', 'queue_link' ), array() ),
	),
	'sponsor-agreement-accepted'   => array(
		'trigger'      => __( 'When an Administrator accepts a sponsor\'s signed Collaboration Agreement: to every account of the sponsor.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your Collaboration Agreement is accepted', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: %s: date the agreement was accepted. */
					__( 'Your Collaboration Agreement was accepted on %s. Nothing about your dashboard changes: it never depended on an agreement.', 'wpcredits-program-manager' ),
					'{accepted_on}'
				),
				__( 'You can download the copy we hold from the agreement card on your dashboard.', 'wpcredits-program-manager' ),
				'{dashboard_link}',
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'accepted_on', 'dashboard_link' ), array() ),
	),
	'sponsor-agreement-returned'   => array(
		'trigger'      => __( 'When an Administrator sends a sponsor\'s signed Collaboration Agreement back with a note: to every account of the sponsor, with the Administrator\'s address to reply to.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your Collaboration Agreement came back with a note', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: %s: the Administrator's name, or "An Administrator" when their account holds none. */
					__( '%s read your Collaboration Agreement and sent it back, with this note:', 'wpcredits-program-manager' ),
					'{returned_by}'
				),
				'{note}',
				__( 'Upload the corrected document from the agreement card on your dashboard, or reply to this message.', 'wpcredits-program-manager' ),
				'{dashboard_link}',
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'returned_by', 'note', 'dashboard_link' ), array( 'note' ) ),
	),
	'sponsor-agreement-revoked'    => array(
		'trigger'      => __( 'When an Administrator takes a sponsor\'s Collaboration Agreement out of force: to every account of the sponsor, with the Administrator\'s address to reply to.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your Collaboration Agreement is no longer in force', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'Your Collaboration Agreement is no longer in force, with this note from the program:', 'wpcredits-program-manager' ),
				'{note}',
				__( 'Your dashboard, your offers and your codes are unchanged: they never depended on an agreement. Reply to this message if you would like to talk about a new one.', 'wpcredits-program-manager' ),
			)
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_take( array( 'site_name', 'note' ), array( 'note' ) ),
	),
	'sponsor-agreement-reinstated' => array(
		'trigger'      => __( 'When an Administrator puts a sponsor\'s Collaboration Agreement back in force: to every account of the sponsor.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your Collaboration Agreement is back in force', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => sprintf(
			/* translators: %s: today's date. */
			__( 'Your Collaboration Agreement is in force again as of %s. Nothing else changed.', 'wpcredits-program-manager' ),
			'{reinstated_on}'
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'reinstated_on' ), array() ),
	),
	'sponsor-interest'             => array(
		'trigger'      => __( 'When a sponsor says on the Sponsor Dashboard what else it would like to support: to its assigned Administrator, or, with none, to the addresses in Who is mailed, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: company name. */
			__( '[WordPress Credits] %s would like to support the program', 'wpcredits-program-manager' ),
			'{company_name}'
		),
		'body'         => sprintf(
			/* translators: 1: company name, 2: the dated line. */
			__( "%1\$s said on the Sponsor Dashboard:\n\n%2\$s\n\nThe full history is in the Sponsorship interests column of the Sponsors table.", 'wpcredits-program-manager' ),
			'{company_name}',
			'{interest}'
		),
		'placeholders' => $wpcpm_take( array( 'company_name', 'interest' ), array( 'interest' ) ),
	),
	'sponsor-mentor-interest'      => array(
		'trigger'      => __( 'When a sponsor says on the Sponsor Dashboard that it would like to sponsor a mentor who is looking for one: to its assigned Administrator, or, with none, to the addresses in Who is mailed, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: company name, 2: mentor name. */
			__( '[WordPress Credits] %1$s would like to sponsor %2$s', 'wpcredits-program-manager' ),
			'{company_name}',
			'{mentor_name}'
		),
		'body'         => sprintf(
			/* translators: 1: company name, 2: mentor name, 3: the mentor's WordPress.org profile. */
			__( '%1$s said on the Sponsor Dashboard that it would like to sponsor the mentor %2$s (%3$s). Please get in touch with both.', 'wpcredits-program-manager' ),
			'{company_name}',
			'{mentor_name}',
			'{mentor_profile}'
		),
		'placeholders' => $wpcpm_take( array( 'company_name', 'mentor_name', 'mentor_profile' ), array() ),
	),
	'offer-low-stock'              => array(
		'trigger'      => __( 'When a claim leaves an offer with fewer codes than it warns below, once until codes are added: to every account of the sponsor, and to its assigned Administrator, or, with none, to the addresses in Who is mailed, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: offer title, 3: codes left. */
			__( '[%1$s] %2$s: %3$s codes left', 'wpcredits-program-manager' ),
			'{site_name}',
			'{offer_title}',
			'{codes_left}'
		),
		'body'         => sprintf(
			/* translators: 1: offer title, 2: codes left, 3: the threshold, 4: the Offers card's address. */
			__( "The offer \"%1\$s\" has %2\$s codes left; it warns below %3\$s.\n\nAdd more codes, or pause the offer, from the Offers card: %4\$s\n\nThis is sent once. Adding codes arms it again.", 'wpcredits-program-manager' ),
			'{offer_title}',
			'{codes_left}',
			'{low_mark}',
			'{offers_link}'
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'offer_title', 'codes_left', 'low_mark', 'offers_link' ), array() ),
	),
	'claim-problem'                => array(
		'trigger'      => __( 'When somebody who claimed a code reports that it did not work: to the sponsor\'s assigned Administrator, or, with none, to the addresses in Who is mailed, or, with none set, to every Administrator, with the claimant\'s address to reply to.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: offer title. */
			__( '[%1$s] A code from %2$s did not work', 'wpcredits-program-manager' ),
			'{site_name}',
			'{offer_title}'
		),
		'body'         => sprintf(
			/* translators: 1: the claimant's name, 2: offer title, 3: the code's last four characters, 4: the Sponsors screen's address. */
			__( "%1\$s reports that their code for \"%2\$s\" (ending %3\$s) did not work.\n\nThe sponsor's offers and the people who claimed from them are on the Sponsors screen: %4\$s\n\nReply to this person from your own mailbox. The site never sends a code by mail.", 'wpcredits-program-manager' ),
			'{claimant_name}',
			'{offer_title}',
			'{code_ending}',
			'{offers_screen_link}'
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'offer_title', 'claimant_name', 'code_ending', 'offers_screen_link' ), array() ),
	),
	'sponsor-post-returned'        => array(
		'trigger'      => __( 'When a sponsor\'s post is sent back to its author as a draft with a note: to the post\'s author.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: the post title. */
			__( 'Your post "%s" needs a change before it is published', 'wpcredits-program-manager' ),
			'{post_title}'
		),
		'body'         => sprintf(
			/* translators: 1: the author's name, 2: the post title, 3: the Administrator's note, 4: the wp-admin posts list URL. */
			__( "Hello %1\$s,\n\nAn Administrator read your post \"%2\$s\" and sent it back as a draft with this note:\n\n%3\$s\n\nEdit it here and submit it for review again: %4\$s", 'wpcredits-program-manager' ),
			'{author_name}',
			'{post_title}',
			'{note}',
			'{edit_link}'
		),
		'placeholders' => $wpcpm_take( array( 'post_title', 'author_name', 'note', 'edit_link' ), array( 'post_title', 'author_name', 'note' ) ),
	),
);
```

- [ ] **Step 9: Run them to verify they pass, and the suites that build the registry**

Run, one per command: `php bin/test-mail-catalog.php`, `php bin/test-mail-templates.php`, `php bin/test-emails-templates.php`, `php bin/test-institutions-sponsors-text.php`, `php bin/test-invitation-text.php`, `php bin/test-calls-reports-text.php`, `php -l includes/mail/templates/institutions.php`, `php -l includes/mail/templates/sponsors.php`
Expected: `ALL PASS (25 checks)`; `ALL PASS (87 checks)`; `ALL PASS (67 checks)`; `ALL PASS (1511 checks)` (no sender reads the registry yet, so every email is as recorded); `ALL PASS (40 checks)`; `ALL PASS (398 checks)`; `No syntax errors detected` twice.

- [ ] **Step 10: Run the whole battery**

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 11: Commit**

```bash
git add includes/mail/class-wpcpm-mail-catalog.php includes/mail/class-wpcpm-mail-templates.php includes/mail/templates/institutions.php includes/mail/templates/sponsors.php bin/test-mail-catalog.php bin/test-mail-templates.php bin/test-emails-templates.php
git commit -m "Emails E4: the catalog's new ids and the Institutions' and the Sponsors' shipped text"
```

---


### Task 3: The Institutions emails built from the template registry, word for word

Every builder of the six Institutions classes chooses its template, works out its values inside the recipient's language, and returns `WPCPM_Mail_Templates::mail()` with the Reply-To it always sent. The recording holds every Institutions email to its bytes, under its own id; the suites that send these emails through stand-ins of the mail layer load the registry, and the one that pins the ids through the handlers names the new ones.

**Files:**
- Modify: `bin/test-institutions-sponsors-text.php` (`sent_as()` for the Institutions; a member who reads Polish; the rejections)
- Modify: `bin/test-mail-catalog.php` (the source scan's count)
- Modify: `bin/test-agreement-generate.php`, `bin/test-institution-agreement.php`, `bin/test-institution-application.php`, `bin/test-institution-invite.php`, `bin/test-institution-members.php`, `bin/test-institution-people.php`, `bin/test-institutions-screen.php` (each loads the registry; bin/test-institution-agreement.php names the flagged upload's and the reinstatement's ids)
- Modify: `includes/modules/class-wpcpm-institution-application.php`, `includes/modules/class-wpcpm-institutions.php`, `includes/modules/class-wpcpm-institution-invite.php`, `includes/modules/class-wpcpm-institution-agreement.php`, `includes/modules/class-wpcpm-agreement-generate.php`, `includes/modules/class-wpcpm-institution-members.php`

**Interfaces:**
- Consumes: `WPCPM_Mail_Templates::mail( $id, array $values, array $extra = array() )` (Task 2); the Institutions templates and their placeholders (Task 2).
- Produces: `WPCPM_Institution_Agreement::MAIL_REINSTATED` (`agreement-reinstated`), `::MAIL_LANDED_FLAGGED` (`agreement-landed-flagged`), `::MAIL_REVOKED_NO_CONTACT` (`agreement-revoked-no-contact`); `private static function contact_of( $record ): array{country:string,email:string}|null`, in place of `contact_line()`; every builder of the six classes returns `WPCPM_Mail_Templates::mail()`.

- [ ] **Step 1: The recording's ids, a member who reads Polish, and the rejections**

`sent_as()` answers the Institutions' new ids for the cases that send them; a member whose account reads Polish is told who uploaded the agreement in Polish; and a rejection, of either area, carries none of the reason typed:

In `bin/test-institutions-sponsors-text.php`, replace:

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
 * The id each recorded email is logged under now: its template's own since 1.122.22, where the
 * recording holds the id the email shared with another that says other words; as recorded for every
 * other.
 *
 * @param string $case     The case.
 * @param int    $index    Which of its emails, from 0.
```

In `bin/test-institutions-sponsors-text.php`, replace:

```php
 * @return string
 */
function sent_as( $case, $index, $recorded ) {
	return $recorded;
}

$fixture = __DIR__ . '/fixtures/institutions-sponsors-1-122-21.json';
```

with:

```php
 * @return string
 */
function sent_as( $case, $index, $recorded ) {
	$ids = array(
		'applied-no-call'                            => 'institution-applied-no-call',
		'applied-no-routing'                         => 'institution-applied-no-call',
		'agreement-landed-one-flag'                  => 'agreement-landed-flagged',
		'agreement-landed-three-flags'               => 'agreement-landed-flagged',
		'agreement-reinstated'                       => 'agreement-reinstated',
		'agreement-reinstated-no-page'               => 'agreement-reinstated',
		'agreement-reinstated-institution-page-only' => 'agreement-reinstated',
		'agreement-revoked-contact-with-no-address'  => 'agreement-revoked-no-contact',
		'agreement-revoked-no-country'               => 'agreement-revoked-no-contact',
		'agreement-revoked-unknown-country'          => 'agreement-revoked-no-contact',
		'agreement-revoked-no-contact-typed-in-a-browser' => 'agreement-revoked-no-contact',
	);

	return isset( $ids[ $case ] ) ? $ids[ $case ] : $recorded;
}

$fixture = __DIR__ . '/fixtures/institutions-sponsors-1-122-21.json';
```

In `bin/test-institutions-sponsors-text.php`, replace:

```php
	)
);

$text = is_file( $fixture ) ? (string) file_get_contents( $fixture ) : '';
preg_match_all( '/[?&]t=([A-Za-z0-9-]+)/', $text, $tokens );
preg_match_all( '/\(ending ([^)]*)\)/', $text, $endings );
```

with:

```php
	)
);

// What the move changes on purpose, and so not in the recording: a value worked out outside an email's
// builder until 1.122.22 was worded in the language of whoever pressed, or of the site for a job, and
// is worded in the recipient's now, as the sentence around it always was. A member whose account reads
// Polish is told who uploaded the agreement in Polish, "a member of your institution" when the
// uploader's account holds no name, while the other member is told in English.
$GLOBALS['user_locale'][50] = 'pl_PL';
$polish                     = emails_of( $cases['agreement-received-one-day-no-uploader'] );
unset( $GLOBALS['user_locale'][50] );
ck(
	'a member who reads Polish is sent the receipt in Polish, an uploader with no name worded in Polish too, and the other member in English',
	array_map(
		function ( $mail ) {
			return strtok( $mail['body'], "\r" );
		},
		$polish
	),
	array(
		'[pl_PL] We received your signed Collaboration Agreement on 2036-03-02, uploaded by [pl_PL] a member of your institution.',
		'We received your signed Collaboration Agreement on 2036-03-02, uploaded by a member of your institution.',
	)
);

// A rejection tells the applicant nothing but that it was decided: the reason the Administrator typed
// reaches neither area's email, and neither template has a place for it.
$reasons = array();
foreach ( array( 'declined', 'declined-site-name', 'sponsor-declined', 'sponsor-declined-site-name' ) as $name ) {
	foreach ( emails_of( $cases[ $name ] ) as $mail ) {
		$reasons[] = false !== strpos( $mail['subject'] . $mail['body'], 'Could you <3' ) || false !== strpos( $mail['subject'] . $mail['body'], 'second line' );
	}
}
ck(
	'and a rejection, of an institution or of a sponsor, carries none of the reason the Administrator typed, and its template has no place for one',
	array( $reasons, array_keys( WPCPM_Mail_Templates::get( 'institution-declined' )['placeholders'] ), array_keys( WPCPM_Mail_Templates::get( 'sponsor-declined' )['placeholders'] ) ),
	array( array( false, false, false, false ), array( 'site_name' ), array( 'site_name' ) )
);

$text = is_file( $fixture ) ? (string) file_get_contents( $fixture ) : '';
preg_match_all( '/[?&]t=([A-Za-z0-9-]+)/', $text, $tokens );
preg_match_all( '/\(ending ([^)]*)\)/', $text, $endings );
```

- [ ] **Step 2: The source scan**

An email chosen between two ids is set from a variable the source scan cannot read, so the scan finds 35 contexts, and the registry's files are where both of each two are read:

In `bin/test-mail-catalog.php`, replace:

```php
$missing = array_values( array_diff( array_keys( $contexts ), array_keys( $emails ) ) );
sort( $missing );
ck( 'every context the source passes to the mail layer is in the catalog', $missing, array() );
ck( 'and the scan found all 37 of them (it is not reading nothing)', count( $contexts ), 37 );

// The template registry's ids, read from its definition files as each file writes them: one at the
// start of each line of the array the file returns. An invitation is named by its template's id,
// and one of them, the institution invitation for an agreement on file, is set from a choice
// between two ids rather than passed as a literal the scan above can read.
$templates = array();
foreach ( glob( __DIR__ . '/../includes/mail/templates/*.php' ) as $file ) {
	preg_match_all( "/^\t'([a-z0-9-]+)'\s*=>\s*array\(/m", (string) file_get_contents( $file ), $t );
```

with:

```php
$missing = array_values( array_diff( array_keys( $contexts ), array_keys( $emails ) ) );
sort( $missing );
ck( 'every context the source passes to the mail layer is in the catalog', $missing, array() );
ck( 'and the scan found all 35 of them (it is not reading nothing)', count( $contexts ), 35 );

// The template registry's ids, read from its definition files as each file writes them: one at the
// start of each line of the array the file returns. An invitation is named by its template's id,
// and one of them, the institution invitation for an agreement on file, is set from a choice
// between two ids rather than passed as a literal the scan above can read; so, since 1.122.22, is
// each email of the Institutions and the Sponsors that says other words in one case than in another
// (the acknowledgement with or without a call to book, or with its logo kept or not; the upload with
// the scan's notes or none; the revocation with a country contact or none), so the registry's files
// are where both of each two are read.
$templates = array();
foreach ( glob( __DIR__ . '/../includes/mail/templates/*.php' ) as $file ) {
	preg_match_all( "/^\t'([a-z0-9-]+)'\s*=>\s*array\(/m", (string) file_get_contents( $file ), $t );
```

- [ ] **Step 3: The Institutions suites load the registry**

Seven suites drive the Institutions senders through a stand-in of the mail layer that calls the builder, so each loads the catalog and the registry the builders now fill their words from. bin/test-institution-agreement.php pins the ids through the real handlers: the upload the scan noticed something in, and the reinstatement, are sent under their own ids:

In `bin/test-agreement-generate.php`, replace:

```php
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';

if ( ! class_exists( 'WPCPM_Institution_Agreement' ) ) {
```

with:

```php
	}
}

// The template registry, which every email's builder fills its words from since 1.122.22.
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';

if ( ! class_exists( 'WPCPM_Institution_Agreement' ) ) {
```

In `bin/test-institution-agreement.php`, replace:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';

if ( ! class_exists( 'WPCPM_Institution_Members' ) ) {
```

with:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
// The template registry, which every email's builder fills its words from since 1.122.22.
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';

if ( ! class_exists( 'WPCPM_Institution_Members' ) ) {
```

In `bin/test-institution-agreement.php`, replace:

```php

ck( 'the option knows both sides, and the gate stays shut', array( $option['site_state'], $option['airtable_status'], $option['settled'] ), array( 'submitted', 'Awaiting review', false ) );

ck( 'every member was told once, and the managers once', mail_log(), array(
	'agreement-received to ola@example.edu',
	'agreement-received to bo@example.edu',
	'agreement-landed to pm@example.org',
) );
ck( 'the managers\' subject is the one the design writes out', $GLOBALS['mail'][2]['subject'], '[WPCredits] Signed agreement from Universidad Example is waiting for review' );
ck( 'and their message says the scan is a courtesy, in the same breath as what it found', array(
```

with:

```php

ck( 'the option knows both sides, and the gate stays shut', array( $option['site_state'], $option['airtable_status'], $option['settled'] ), array( 'submitted', 'Awaiting review', false ) );

// The scan noticed something, so the managers are sent the upload's email in the words that say what,
// under that email's own id since 1.122.22.
ck( 'every member was told once, and the managers once', mail_log(), array(
	'agreement-received to ola@example.edu',
	'agreement-received to bo@example.edu',
	'agreement-landed-flagged to pm@example.org',
) );
ck( 'the managers\' subject is the one the design writes out', $GLOBALS['mail'][2]['subject'], '[WPCredits] Signed agreement from Universidad Example is waiting for review' );
ck( 'and their message says the scan is a courtesy, in the same breath as what it found', array(
```

In `bin/test-institution-agreement.php`, replace:

```php
	WPCPM_Institution_Agreement::option( $rec_a )['airtable_status'],
), array( 'accepted', 'Accepted' ) );
ck( 'so the gate is open on this request, with no sync in between', WPCPM_Institution_Agreement::is_settled( $rec_a ), true );
ck( 'every member was told, once each, under the acceptance context', mail_log(), array( 'agreement-accepted to ola@example.edu', 'agreement-accepted to bo@example.edu' ) );
ck( 'in the reinstated wording, saying nothing was lost meanwhile', array(
	false !== strpos( $GLOBALS['mail'][0]['body'], 'put back in force' ),
	false !== strpos( $GLOBALS['mail'][0]['body'], 'open again' ),
```

with:

```php
	WPCPM_Institution_Agreement::option( $rec_a )['airtable_status'],
), array( 'accepted', 'Accepted' ) );
ck( 'so the gate is open on this request, with no sync in between', WPCPM_Institution_Agreement::is_settled( $rec_a ), true );
ck( 'every member was told, once each, under the reinstatement\'s own id, the acceptance\'s until 1.122.22', mail_log(), array( 'agreement-reinstated to ola@example.edu', 'agreement-reinstated to bo@example.edu' ) );
ck( 'in the reinstated wording, saying nothing was lost meanwhile', array(
	false !== strpos( $GLOBALS['mail'][0]['body'], 'put back in force' ),
	false !== strpos( $GLOBALS['mail'][0]['body'], 'open again' ),
```

In `bin/test-institution-agreement.php`, replace:

```php
ck( 'a rebuild that finds the lock held is not reported as an open account', $outcome, 'agreement-later' );
ck( 'the reinstatement itself stands', array( count( $GLOBALS['patched'] ), get_post_meta( $id, '_wpcpm_agr_state', true ) ), array( 1, 'accepted' ) );
ck( 'the gate is shut until the next sync writes the option', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );
ck( 'and every member was told all the same', mail_log(), array( 'agreement-accepted to ola@example.edu', 'agreement-accepted to bo@example.edu' ) );

/* ---- what the two handlers are, read from the source --------------------- */

```

with:

```php
ck( 'a rebuild that finds the lock held is not reported as an open account', $outcome, 'agreement-later' );
ck( 'the reinstatement itself stands', array( count( $GLOBALS['patched'] ), get_post_meta( $id, '_wpcpm_agr_state', true ) ), array( 1, 'accepted' ) );
ck( 'the gate is shut until the next sync writes the option', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );
ck( 'and every member was told all the same', mail_log(), array( 'agreement-reinstated to ola@example.edu', 'agreement-reinstated to bo@example.edu' ) );

/* ---- what the two handlers are, read from the source --------------------- */

```

In `bin/test-institution-application.php`, replace:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-countries.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-application.php';

```

with:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
// The template registry, which every email's builder fills its words from since 1.122.22.
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-countries.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-application.php';

```

In `bin/test-institution-invite.php`, replace:

```php
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-members.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
```

with:

```php
	}
}

// The template registry, which every email's builder fills its words from since 1.122.22.
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-members.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
```

In `bin/test-institution-members.php`, replace:

```php
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-members.php';

```

with:

```php
	}
}

// The template registry, which every email's builder fills its words from since 1.122.22.
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-members.php';

```

In `bin/test-institution-people.php`, replace:

```php
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-members.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
```

with:

```php
	}
}

// The template registry, which every email's builder fills its words from since 1.122.22.
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-members.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
```

In `bin/test-institutions-screen.php`, replace:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-agreement-template.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-secret.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-private-files.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php';
// Before the module, which uses it: PHP declares a class only once the traits it uses are declared.
```

with:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-agreement-template.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-secret.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-private-files.php';
// The template registry, which every email's builder fills its words from since 1.122.22.
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php';
// Before the module, which uses it: PHP declares a class only once the traits it uses are declared.
```

- [ ] **Step 4: Run them to verify they fail**

Run, one per command: `php bin/test-institutions-sponsors-text.php`, `php bin/test-mail-catalog.php`, `php bin/test-institution-agreement.php`
Expected: `21 FAILURE(S) (1513 checks)`: twenty "logged under its own id" (the acknowledgement with no call to book, twice; the flagged upload, seven; the reinstatement, six; the revocation with no contact, five) and the member who reads Polish, told "a member of your institution" in English; the rejections' check passes, as it does on 1.122.21 (to see it guard, hand the reason to the institution's builder on a scratch copy, `use ( $site, $reason )` and its words after the site's name: it fails, beside the recording's two subjects; or give `sponsor-declined` a second placeholder: it fails alone). `1 FAILURE(S) (25 checks)` (the scan finds 37). `3 FAILURE(S)` (the flagged upload's id and the reinstatement's, twice). The six other suites pass.

- [ ] **Step 5: The institution application**

The acknowledgement chooses between its two templates by the country's call to book, and logs under the one it chose:

In `includes/modules/class-wpcpm-institution-application.php`, replace:

```php
	 * existed an Airtable automation embedded it on `formSubmitted`; no record exists at
	 * submission any more, so the automation cannot fire and the site says it instead.
	 *
	 * @param int $post_id Application post ID.
	 * @return bool Whether the message was handed off.
	 */
```

with:

```php
	 * existed an Airtable automation embedded it on `formSubmitted`; no record exists at
	 * submission any more, so the automation cannot fire and the site says it instead.
	 *
	 * The wording is the template registry's (`WPCPM_Mail_Templates`, the Institutions area): one
	 * email for a country with a call to book and one for a country with none.
	 *
	 * @param int $post_id Application post ID.
	 * @return bool Whether the message was handed off.
	 */
```

In `includes/modules/class-wpcpm-institution-application.php`, replace:

```php
		$calendly  = ( is_array( $routing ) && ! empty( $routing['calendly'] ) ) ? (string) $routing['calendly'] : '';
		$verify    = self::verify_url( $post_id );
		$site      = WPCPM_Mail::site_name();

		$build = function () use ( $site, $name, $reference, $calendly, $verify, $email ) {
			$lines = array(
				sprintf(
					/* translators: %s: the institution's name as it was given on the form. */
					__( 'Thank you for asking about the WordPress Credits Program for %s. Your application is with us.', 'wpcredits-program-manager' ),
					$name
				),
				sprintf(
					/* translators: %s: the application reference, e.g. APP-2026-0007. */
					__( 'Your reference is %s. Please quote it if you write to us about this application.', 'wpcredits-program-manager' ),
					$reference
				),
				__( 'What happens next: an Administrator reads every application by hand and writes back, usually within a few working days. If the program can take your institution, we will set up an account on the program site and send you the Collaboration Agreement to sign.', 'wpcredits-program-manager' ),
				__( 'Please confirm this address by following this link. It tells us we are writing to a real address, and it does nothing else:', 'wpcredits-program-manager' ),
				$verify,
			);

			if ( '' !== $calendly ) {
				$lines[] = __( 'You can also book a call with the Administrator who looks after your country:', 'wpcredits-program-manager' );
				$lines[] = $calendly;
			}

			$lines[] = sprintf(
				/* translators: 1: the address the message went to, 2: site name. */
				__( 'This message went to %1$s because that address was given on the application form at %2$s. If it was not you, you can ignore it: nothing happens until the address is confirmed.', 'wpcredits-program-manager' ),
				$email,
				$site
			);

			return array(
				'subject' => sprintf(
					/* translators: 1: site name, 2: the application reference. */
					__( '[%1$s] We have your application: %2$s', 'wpcredits-program-manager' ),
					$site,
					$reference
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		// No locale is named. The form has no language question, so the only thing known about
		// the applicant's language is the locale the page they filled in was rendered in, which
		// is the one this request is already running in.
		return WPCPM_Mail::send_to( $email, 'institution-applied', $build );
	}

	/**
```

with:

```php
		$calendly  = ( is_array( $routing ) && ! empty( $routing['calendly'] ) ) ? (string) $routing['calendly'] : '';
		$verify    = self::verify_url( $post_id );
		$site      = WPCPM_Mail::site_name();
		$id        = '' !== $calendly ? 'institution-applied' : 'institution-applied-no-call';

		$build = static function () use ( $id, $site, $name, $reference, $calendly, $verify, $email ) {
			return WPCPM_Mail_Templates::mail(
				$id,
				array(
					'site_name'        => $site,
					'institution_name' => $name,
					'reference'        => $reference,
					'verify_link'      => $verify,
					'booking_link'     => $calendly,
					'applicant_email'  => $email,
				)
			);
		};

		// No locale is named. The form has no language question, so the only thing known about
		// the applicant's language is the locale the page they filled in was rendered in, which
		// is the one this request is already running in.
		return WPCPM_Mail::send_to( $email, $id, $build );
	}

	/**
```

In `includes/modules/class-wpcpm-institution-application.php`, replace:

```php
		$link    = self::queue_url( $post_id );
		$site    = WPCPM_Mail::site_name();

		$build = function () use ( $site, $name, $country, $city, $person, $email, $link ) {
			$lines = array(
				__( 'An institution has applied to the program through the form on the site, and the application passed every check.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: institution name, 2: city, 3: country. */
					__( '%1$s, %2$s, %3$s', 'wpcredits-program-manager' ),
					$name,
					$city,
					$country
				),
				sprintf(
					/* translators: 1: contact person's name, 2: their email address. */
					__( 'Contact: %1$s, %2$s', 'wpcredits-program-manager' ),
					$person,
					$email
				),
				__( 'It is on the queue here:', 'wpcredits-program-manager' ),
				$link,
			);

			return array(
				'subject' => sprintf(
					/* translators: 1: site name, 2: institution name. */
					__( '[%1$s] New institution application from %2$s', 'wpcredits-program-manager' ),
					$site,
					$name
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

```

with:

```php
		$link    = self::queue_url( $post_id );
		$site    = WPCPM_Mail::site_name();

		$build = static function () use ( $site, $name, $country, $city, $person, $email, $link ) {
			return WPCPM_Mail_Templates::mail(
				'institution-application',
				array(
					'site_name'        => $site,
					'institution_name' => $name,
					'city'             => $city,
					'country'          => $country,
					'contact_name'     => $person,
					'contact_email'    => $email,
					'queue_link'       => $link,
				)
			);
		};

```

- [ ] **Step 6: The institution queue's question and rejection**

In `includes/modules/class-wpcpm-institutions.php`, replace:

```php
		$said = WPCPM_Typed_Text::mail_text( $question );

		$build = static function () use ( $site, $reference, $said, $manager ) {
			$lines = array(
				__( 'Thank you for applying to the WordPress Credits Program. Before an Administrator can take your application further, they have one question:', 'wpcredits-program-manager' ),
				$said,
				__( 'Reply to this message and your answer reaches them directly.', 'wpcredits-program-manager' ),
			);

			return array(
				'subject' => sprintf(
					/* translators: 1: site name, 2: application reference, e.g. APP-2026-0007. */
					__( '[%1$s] A question about your application (%2$s)', 'wpcredits-program-manager' ),
					$site,
					$reference
				),
				'body'    => implode( "\r\n\r\n", $lines ),
				'headers' => WPCPM_Mail::reply_to( $manager ),
			);
		};

```

with:

```php
		$said = WPCPM_Typed_Text::mail_text( $question );

		$build = static function () use ( $site, $reference, $said, $manager ) {
			return WPCPM_Mail_Templates::mail(
				self::MAIL_INFO,
				array(
					'site_name' => $site,
					'reference' => $reference,
					'question'  => $said,
				),
				array( 'headers' => WPCPM_Mail::reply_to( $manager ) )
			);
		};

```

In `includes/modules/class-wpcpm-institutions.php`, replace:

```php
		// The reason is deliberately not captured by this closure. Keeping it out of the
		// builder's scope is what stops a later edit reaching for it "just for the subject".
		$build = static function () use ( $site ) {
			$lines = array(
				__( 'Thank you for applying to the WordPress Credits Program, and for the time you took to write to us.', 'wpcredits-program-manager' ),
				__( 'We have read your institution\'s application, and we cannot accept it into the program at this time. We are sorry not to have better news.', 'wpcredits-program-manager' ),
				__( 'You are welcome to apply again later, for example when your programs change. A new application is read on its own merits, and this decision does not count against it.', 'wpcredits-program-manager' ),
			);

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] About your application to the WordPress Credits Program', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		if ( is_email( $email ) ) {
```

with:

```php
		// The reason is deliberately not captured by this closure. Keeping it out of the
		// builder's scope is what stops a later edit reaching for it "just for the subject".
		$build = static function () use ( $site ) {
			return WPCPM_Mail_Templates::mail( self::MAIL_DECLINED, array( 'site_name' => $site ) );
		};

		if ( is_email( $email ) ) {
```

- [ ] **Step 7: The invitation**

In `includes/modules/class-wpcpm-institution-invite.php`, replace:

```php
		$sender  = get_user_by( 'id', $actor );
		$headers = WPCPM_Mail::reply_to( $sender instanceof WP_User ? $sender : null );

		$build = function () use ( $site, $school, $who, $link, $until, $headers ) {
			$lines = array(
				sprintf(
					/* translators: 1: the member who invited them, 2: the institution's name, 3: the site's name. */
					__( '%1$s has invited you to see %2$s\'s students in the WordPress Credits Program on %3$s.', 'wpcredits-program-manager' ),
					$who,
					$school,
					$site
				),
				__( 'Open this link, and press Accept on the page it opens:', 'wpcredits-program-manager' ),
				$link,
				sprintf(
					/* translators: %s: the date the invitation stops working. */
					__( 'The link works until %s and can only be used once. If you do not have an account here yet, one is made for you and you get a second email with a link to set your password.', 'wpcredits-program-manager' ),
					$until
				),
				__( 'If you were not expecting this, ignore this message: opening the link only shows you the invitation, nothing happens until you press Accept, and it stops working on its own. Replying to this message reaches the person who sent it.', 'wpcredits-program-manager' ),
			);

			return array(
				'subject' => sprintf(
					/* translators: 1: site name, 2: the institution's name. */
					__( '[%1$s] An invitation to %2$s', 'wpcredits-program-manager' ),
					$site,
					$school
				),
				'body'    => implode( "\r\n\r\n", $lines ),
				'headers' => $headers,
			);
		};

```

with:

```php
		$sender  = get_user_by( 'id', $actor );
		$headers = WPCPM_Mail::reply_to( $sender instanceof WP_User ? $sender : null );

		$build = static function () use ( $site, $school, $who, $link, $until, $headers ) {
			return WPCPM_Mail_Templates::mail(
				self::MAIL_INVITE,
				array(
					'site_name'        => $site,
					'inviter_name'     => $who,
					'institution_name' => $school,
					'accept_link'      => $link,
					'expiry_date'      => $until,
				),
				array( 'headers' => $headers )
			);
		};

```

- [ ] **Step 8: The Collaboration Agreement**

The digest's counted words and list, the receipt's uploader and working days worked out inside the member's language, the upload's two templates chosen by the scan's notes, the revocation's two by the country's contact (`contact_of()`, the facts alone, in place of `contact_line()`), and the reinstatement under its own id:

In `includes/modules/class-wpcpm-institution-agreement.php`, replace:

```php
	const STAGE_CONFIRMED = 'Confirmed';

	/** Mail contexts, so every send is in the log under a name a manager can search for. */
	const MAIL_RECEIVED = 'agreement-received';
	const MAIL_ACCEPTED = 'agreement-accepted';
	const MAIL_RETURNED = 'agreement-returned';
	const MAIL_LANDED   = 'agreement-landed';
	const MAIL_REMINDER = 'agreement-reminder';
	const MAIL_REVOKED  = 'agreement-revoked';

	/**
	 * What the event rows on a document say, in the same voice as the two Phase 2 rows.
```

with:

```php
	const STAGE_CONFIRMED = 'Confirmed';

	/** Mail contexts, so every send is in the log under a name a manager can search for. */
	const MAIL_RECEIVED           = 'agreement-received';
	const MAIL_ACCEPTED           = 'agreement-accepted';
	const MAIL_REINSTATED         = 'agreement-reinstated';
	const MAIL_RETURNED           = 'agreement-returned';
	const MAIL_LANDED             = 'agreement-landed';
	const MAIL_LANDED_FLAGGED     = 'agreement-landed-flagged';
	const MAIL_REMINDER           = 'agreement-reminder';
	const MAIL_REVOKED            = 'agreement-revoked';
	const MAIL_REVOKED_NO_CONTACT = 'agreement-revoked-no-contact';

	/**
	 * What the event rows on a document say, in the same voice as the two Phase 2 rows.
```

In `includes/modules/class-wpcpm-institution-agreement.php`, replace:

```php
		$site  = WPCPM_Mail::site_name();
		$queue = admin_url( 'admin.php?page=wpcpm-institutions' );

		$build = function () use ( $site, $overdue, $queue, $days ) {
			// One plural form and one number that never needs pluralising: the days are
			// written as "the 3-day review window" so that a translator has one rule to
			// follow rather than two counts crossing in one sentence.
			$lines = array(
				sprintf(
					/* translators: 1: number of agreements, 2: number of days in the review window. */
					_n(
						'%1$s signed agreement has been waiting longer than the %2$s-day review window.',
						'%1$s signed agreements have been waiting longer than the %2$s-day review window.',
						count( $overdue ),
						'wpcredits-program-manager'
					),
					number_format_i18n( count( $overdue ) ),
					number_format_i18n( $days )
				),
			);

			foreach ( $overdue as $facts ) {
				$lines[] = sprintf(
					/* translators: 1: institution name, 2: date the agreement was uploaded. */
					__( '%1$s, uploaded %2$s', 'wpcredits-program-manager' ),
					$facts['institution_name'],
```

with:

```php
		$site  = WPCPM_Mail::site_name();
		$queue = admin_url( 'admin.php?page=wpcpm-institutions' );

		$build = static function () use ( $site, $overdue, $queue, $days ) {
			// The count with its verb, in the subject's words and the first line's, and the days
			// as "the 3-day review window", so a translator has one rule to follow rather than two
			// counts crossing in one sentence. The list is one value, an agreement to a paragraph.
			$items = array();

			foreach ( $overdue as $facts ) {
				$items[] = sprintf(
					/* translators: 1: institution name, 2: date the agreement was uploaded. */
					__( '%1$s, uploaded %2$s', 'wpcredits-program-manager' ),
					$facts['institution_name'],
```

In `includes/modules/class-wpcpm-institution-agreement.php`, replace:

```php
				);
			}

			$lines[] = $queue;

			return array(
				'subject' => sprintf(
					/* translators: 1: site name, 2: number of agreements. */
					_n(
						'[%1$s] %2$s signed agreement is waiting for review',
						'[%1$s] %2$s signed agreements are waiting for review',
						count( $overdue ),
						'wpcredits-program-manager'
					),
					$site,
					number_format_i18n( count( $overdue ) )
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

```

with:

```php
				);
			}

			return WPCPM_Mail_Templates::mail(
				self::MAIL_REMINDER,
				array(
					'site_name'          => $site,
					'agreements_waiting' => sprintf(
						/* translators: %s: a number of signed agreements. */
						_n( '%s signed agreement is', '%s signed agreements are', count( $overdue ), 'wpcredits-program-manager' ),
						number_format_i18n( count( $overdue ) )
					),
					'agreements_overdue' => sprintf(
						/* translators: %s: a number of signed agreements. */
						_n( '%s signed agreement has been', '%s signed agreements have been', count( $overdue ), 'wpcredits-program-manager' ),
						number_format_i18n( count( $overdue ) )
					),
					'review_days'        => number_format_i18n( $days ),
					'overdue_list'       => implode( "\r\n\r\n", $items ),
					'queue_link'         => $queue,
				)
			);
		};

```

In `includes/modules/class-wpcpm-institution-agreement.php`, replace:

```php

		$site = WPCPM_Mail::site_name();
		$days = max( 1, (int) WPCPM_Settings::get_value( 'agreement_review_days', 3 ) );
		$who  = '' === $facts['uploaded_by'] ? __( 'a member of your institution', 'wpcredits-program-manager' ) : $facts['uploaded_by'];

		$build = function () use ( $site, $facts, $days, $who ) {
			$lines = array(
				sprintf(
					/* translators: 1: date the file arrived, 2: who uploaded it. */
					__( 'We received your signed Collaboration Agreement on %1$s, uploaded by %2$s.', 'wpcredits-program-manager' ),
					$facts['uploaded_at'],
					$who
				),
				sprintf(
					/* translators: %s: number of working days. */
					_n(
						'An Administrator will read it and you will get an email either way, usually within %s working day.',
						'An Administrator will read it and you will get an email either way, usually within %s working days.',
						$days,
						'wpcredits-program-manager'
					),
					number_format_i18n( $days )
				),
				__( 'If it was the wrong file, withdraw it from the agreement panel on the site and upload the right one.', 'wpcredits-program-manager' ),
			);

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] We received your signed agreement', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

```

with:

```php

		$site = WPCPM_Mail::site_name();
		$days = max( 1, (int) WPCPM_Settings::get_value( 'agreement_review_days', 3 ) );

		// Worked out in the member's language, the uploader's name and the working days alike.
		$build = static function () use ( $site, $facts, $days ) {
			return WPCPM_Mail_Templates::mail(
				self::MAIL_RECEIVED,
				array(
					'site_name'     => $site,
					'uploaded_on'   => $facts['uploaded_at'],
					'uploaded_by'   => '' === $facts['uploaded_by'] ? __( 'a member of your institution', 'wpcredits-program-manager' ) : $facts['uploaded_by'],
					'review_window' => sprintf(
						/* translators: %s: a number of working days. */
						_n( '%s working day', '%s working days', $days, 'wpcredits-program-manager' ),
						number_format_i18n( $days )
					),
				)
			);
		};

```

In `includes/modules/class-wpcpm-institution-agreement.php`, replace:

```php

		$site  = WPCPM_Mail::site_name();
		$queue = admin_url( 'admin.php?page=wpcpm-institutions' );

		$build = function () use ( $site, $facts, $queue ) {
			$lines = array(
				sprintf(
					/* translators: 1: institution name, 2: date, 3: who uploaded it. */
					__( '%1$s uploaded a signed Collaboration Agreement on %2$s (%3$s).', 'wpcredits-program-manager' ),
					$facts['institution_name'],
					$facts['uploaded_at'],
					'' === $facts['uploaded_by'] ? $facts['institution'] : $facts['uploaded_by']
				),
				$facts['flags']
					? sprintf(
						/* translators: %s: comma-separated list of PDF feature names. */
						__( 'The courtesy scan noticed: %s. It is a courtesy and not evidence: download the file and open it in a viewer of your choosing.', 'wpcredits-program-manager' ),
						implode( ', ', $facts['flags'] )
					)
					: __( 'The courtesy scan noticed nothing. It is a courtesy and not evidence: download the file and open it in a viewer of your choosing.', 'wpcredits-program-manager' ),
				$queue,
			);

			return array(
				'subject' => sprintf(
					/* translators: 1: site name, 2: institution name. */
					__( '[%1$s] Signed agreement from %2$s is waiting for review', 'wpcredits-program-manager' ),
					$site,
					$facts['institution_name']
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		return (int) WPCPM_Institutions::notify_managers( self::MAIL_LANDED, $build );
	}

	/**
```

with:

```php

		$site  = WPCPM_Mail::site_name();
		$queue = admin_url( 'admin.php?page=wpcpm-institutions' );
		// What the courtesy scan noticed, or nothing: two emails in other words.
		$id = $facts['flags'] ? self::MAIL_LANDED_FLAGGED : self::MAIL_LANDED;

		$build = static function () use ( $id, $site, $facts, $queue ) {
			return WPCPM_Mail_Templates::mail(
				$id,
				array(
					'site_name'        => $site,
					'institution_name' => $facts['institution_name'],
					'uploaded_on'      => $facts['uploaded_at'],
					'uploaded_by'      => '' === $facts['uploaded_by'] ? $facts['institution'] : $facts['uploaded_by'],
					'scan_flags'       => implode( ', ', $facts['flags'] ),
					'queue_link'       => $queue,
				)
			);
		};

		return (int) WPCPM_Institutions::notify_managers( $id, $build );
	}

	/**
```

In `includes/modules/class-wpcpm-institution-agreement.php`, replace:

```php
		$when = wp_date( 'Y-m-d' );
		$page = WPCPM_Institutions_Dashboard::page_url();

		$build = function () use ( $site, $when, $page ) {
			$lines = array(
				sprintf(
					/* translators: %s: date the agreement was accepted. */
					__( 'Your Collaboration Agreement was accepted on %s. Your account on the site is open.', 'wpcredits-program-manager' ),
					$when
				),
				__( 'You can now see your students and their progress, open one student to read their Student Report Card, and download your own copy of the signed agreement from the card at the foot of the page.', 'wpcredits-program-manager' ),
			);

			if ( '' !== $page ) {
				$lines[] = $page;
			}

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] Your agreement is accepted', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

```

with:

```php
		$when = wp_date( 'Y-m-d' );
		$page = WPCPM_Institutions_Dashboard::page_url();

		$build = static function () use ( $site, $when, $page ) {
			return WPCPM_Mail_Templates::mail(
				self::MAIL_ACCEPTED,
				array(
					'site_name'      => $site,
					'accepted_on'    => $when,
					'dashboard_link' => $page,
				)
			);
		};

```

In `includes/modules/class-wpcpm-institution-agreement.php`, replace:

```php
		$headers = WPCPM_Mail::reply_to( $manager instanceof WP_User ? $manager : null );
		$when    = wp_date( 'Y-m-d' );

		$build = function () use ( $site, $note, $named, $headers, $when ) {
			$lines = array(
				sprintf(
					/* translators: 1: date, 2: the Administrator's name. */
					__( 'Your signed Collaboration Agreement was sent back on %1$s by %2$s, with this note:', 'wpcredits-program-manager' ),
					$when,
					$named
				),
				$note,
				__( 'Upload the corrected copy from the agreement panel on the site. Replying to this message reaches the Administrator who wrote the note.', 'wpcredits-program-manager' ),
			);

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] Your signed agreement needs a change', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
				'headers' => $headers,
			);
		};

```

with:

```php
		$headers = WPCPM_Mail::reply_to( $manager instanceof WP_User ? $manager : null );
		$when    = wp_date( 'Y-m-d' );

		$build = static function () use ( $site, $note, $named, $headers, $when ) {
			return WPCPM_Mail_Templates::mail(
				self::MAIL_RETURNED,
				array(
					'site_name'          => $site,
					'returned_on'        => $when,
					'administrator_name' => $named,
					'note'               => $note,
				),
				array( 'headers' => $headers )
			);
		};

```

In `includes/modules/class-wpcpm-institution-agreement.php`, replace:

```php
	 * their students is deleted by a revocation, and it names the country contact so there is
	 * somebody to write to who is not the address this mail was sent from.
	 *
	 * The note is read back to what was typed, as in `mail_returned()`.
	 *
	 * @param string $record  Institutions record ID.
	 * @param int    $post_id The revoked document.
```

with:

```php
	 * their students is deleted by a revocation, and it names the country contact so there is
	 * somebody to write to who is not the address this mail was sent from.
	 *
	 * The note is read back to what was typed, as in `mail_returned()`. A country with a contact
	 * on file and one with none are two emails in other words (`contact_of()`).
	 *
	 * @param string $record  Institutions record ID.
	 * @param int    $post_id The revoked document.
```

In `includes/modules/class-wpcpm-institution-agreement.php`, replace:

```php
		$manager = wp_get_current_user();
		$named   = $manager instanceof WP_User ? $manager->display_name : '';
		$when    = wp_date( 'Y-m-d' );

		$build = function () use ( $site, $note, $named, $when, $record ) {
			$lines = array(
				sprintf(
					/* translators: 1: date, 2: the Administrator's name. */
					__( 'Your Collaboration Agreement was revoked on %1$s by %2$s, with this note:', 'wpcredits-program-manager' ),
					$when,
					$named
				),
				$note,
				__( 'Your account on this site is limited to the agreement panel until an agreement is in force again. Nothing about your students has been deleted.', 'wpcredits-program-manager' ),
				// Built here rather than above, because `WPCPM_Mail::send()` calls this
				// builder inside the recipient's own locale and a sentence translated
				// outside it would reach them in the site's language.
				self::contact_line( $record ),
			);

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] Your agreement has been revoked', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		return self::mail_members( $record, self::MAIL_REVOKED, $build );
	}

	/**
	 * Tell the institution its agreement is in force again (T9).
	 *
	 * The `agreement-accepted` context with the reinstated wording, because that is what this
	 * is: the same account opening, and a manager searching the mail log for why an
	 * institution was let back in should find it filed with every other acceptance. The
	 * second line answers the question the first raises, which is what happened to everything
	 * while the account was shut.
	 *
	 * @param string $record  Institutions record ID.
	 * @param int    $post_id The reinstated document.
```

with:

```php
		$manager = wp_get_current_user();
		$named   = $manager instanceof WP_User ? $manager->display_name : '';
		$when    = wp_date( 'Y-m-d' );
		$contact = self::contact_of( $record );
		$id      = null === $contact ? self::MAIL_REVOKED_NO_CONTACT : self::MAIL_REVOKED;

		// The country's name and contact read here, as stored; the sentence around them is the
		// template's, worded in the member's language.
		$build = static function () use ( $id, $site, $note, $named, $when, $contact ) {
			return WPCPM_Mail_Templates::mail(
				$id,
				array(
					'site_name'          => $site,
					'revoked_on'         => $when,
					'administrator_name' => $named,
					'note'               => $note,
					'country'            => null === $contact ? '' : WPCPM_Countries::name_of( $contact['country'] ),
					'country_contact'    => null === $contact ? '' : $contact['email'],
				)
			);
		};

		return self::mail_members( $record, $id, $build );
	}

	/**
	 * Tell the institution its agreement is in force again (T9).
	 *
	 * Sent under the `agreement-accepted` context until 1.122.22, as the same account opening; an
	 * email of its own since, `agreement-reinstated`, as every email that says words of its own
	 * is. The second line answers the question the first raises, which is what happened to
	 * everything while the account was shut.
	 *
	 * @param string $record  Institutions record ID.
	 * @param int    $post_id The reinstated document.
```

In `includes/modules/class-wpcpm-institution-agreement.php`, replace:

```php
		$when = wp_date( 'Y-m-d' );
		$page = WPCPM_Institutions_Dashboard::page_url();

		$build = function () use ( $site, $when, $page ) {
			$lines = array(
				sprintf(
					/* translators: %s: date the agreement was put back in force. */
					__( 'Your Collaboration Agreement was put back in force on %s. Your account on the site is open again.', 'wpcredits-program-manager' ),
					$when
				),
				__( 'Nothing was removed while it was out of force: your students, their progress and your copy of the signed agreement are where they were.', 'wpcredits-program-manager' ),
			);

			if ( '' !== $page ) {
				$lines[] = $page;
			}

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] Your agreement is in force again', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		return self::mail_members( $record, self::MAIL_ACCEPTED, $build );
	}

	/**
	 * Who to write to about a revoked agreement, in one sentence.
	 *
	 * The country contact when the base names one, and the panel's own neutral sentence when
	 * it does not, so the two places an institution reads this read the same. Named in the
	 * body and never added as a recipient: that is the rule design spec 7.4 sets for this
	 * address everywhere it appears, and this mail is not the country contact's business
	 * unless the institution decides it is.
	 *
	 * @param string $record Institutions record ID.
	 * @return string
	 */
	private static function contact_line( $record ) {
		$row     = WPCPM_Institutions_Index::row( $record );
		$country = is_array( $row ) ? (string) $row['country'] : '';
		$routing = '' === $country ? null : WPCPM_Countries::routing( $country );

		if ( null === $routing || '' === trim( (string) $routing['email'] ) ) {
			return __( 'Write to the Administrator who has been in touch with your institution.', 'wpcredits-program-manager' );
		}

		return sprintf(
			/* translators: 1: country name, 2: the contact's email address. */
			__( 'Your program contact for %1$s is %2$s.', 'wpcredits-program-manager' ),
			WPCPM_Countries::name_of( $country ),
			trim( (string) $routing['email'] )
		);
	}

```

with:

```php
		$when = wp_date( 'Y-m-d' );
		$page = WPCPM_Institutions_Dashboard::page_url();

		$build = static function () use ( $site, $when, $page ) {
			return WPCPM_Mail_Templates::mail(
				self::MAIL_REINSTATED,
				array(
					'site_name'      => $site,
					'reinstated_on'  => $when,
					'dashboard_link' => $page,
				)
			);
		};

		return self::mail_members( $record, self::MAIL_REINSTATED, $build );
	}

	/**
	 * Who to write to about a revoked agreement: the country contact when the base names one, or
	 * null when it does not, and the email then says the panel's own neutral sentence, so the two
	 * places an institution reads this read the same. Named in the body and never added as a
	 * recipient: that is the rule design spec 7.4 sets for this address everywhere it appears, and
	 * this mail is not the country contact's business unless the institution decides it is.
	 *
	 * @param string $record Institutions record ID.
	 * @return array{country:string,email:string}|null The country and its contact's address.
	 */
	private static function contact_of( $record ) {
		$row     = WPCPM_Institutions_Index::row( $record );
		$country = is_array( $row ) ? (string) $row['country'] : '';
		$routing = '' === $country ? null : WPCPM_Countries::routing( $country );

		if ( null === $routing || '' === trim( (string) $routing['email'] ) ) {
			return null;
		}

		return array(
			'country' => $country,
			'email'   => trim( (string) $routing['email'] ),
		);
	}

```

- [ ] **Step 9: The generator's notice**

In `includes/modules/class-wpcpm-agreement-generate.php`, replace:

```php
		$site   = WPCPM_Mail::site_name();
		$reason = $error->get_error_message();

		$build = function () use ( $site, $record, $name, $language, $reason ) {
			$lines = array(
				__( 'An institution asked the site to generate the Collaboration Agreement and the template could not be used, so nothing was generated and they were told the template needs attention.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: what the template refused with, 2: language code, 3: institution record ID, 4: the name that was asked for. */
					__( 'The reason given was: %1$s (template language %2$s, institution %3$s, name asked for "%4$s").', 'wpcredits-program-manager' ),
					$reason,
					$language,
					$record,
					$name
				),
				__( 'The template lives in the plugin, in includes/templates/. A bracketed token other than the institution name is refused on purpose: it would otherwise print on a document somebody signs. Nothing can be generated in this language until it is fixed.', 'wpcredits-program-manager' ),
			);

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] The Collaboration Agreement template needs attention', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

```

with:

```php
		$site   = WPCPM_Mail::site_name();
		$reason = $error->get_error_message();

		$build = static function () use ( $site, $record, $name, $language, $reason ) {
			return WPCPM_Mail_Templates::mail(
				self::NOTIFY_TEMPLATE,
				array(
					'site_name'          => $site,
					'reason'             => $reason,
					'language'           => $language,
					'institution_record' => $record,
					'name_asked_for'     => $name,
				)
			);
		};

```

- [ ] **Step 10: The last member's notice**

The day the membership ended is written inside the builder, in the Administrator's language:

In `includes/modules/class-wpcpm-institution-members.php`, replace:

```php
		$name    = is_array( $profile ) && ! empty( $profile['name'] ) ? (string) $profile['name'] : $record_id;
		$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$who     = $user->display_name;
		$when    = wp_date( get_option( 'date_format' ) );

		$build = function () use ( $site, $name, $record_id, $who, $reason, $when ) {
			$lines = array(
				sprintf(
					/* translators: 1: person, 2: institution name, 3: record ID, 4: reason, 5: date. */
					__( '%1$s is no longer a member of %2$s (%3$s) as of %5$s (%4$s). No account can act for this institution on the site now.', 'wpcredits-program-manager' ),
					$who,
					$name,
					$record_id,
					$reason,
					$when
				),
				__( 'The Airtable record, the agreement, the students and the history are untouched. A former member can be re-added in one click from the Institutions screen, or a new account created there.', 'wpcredits-program-manager' ),
			);

			return array(
				'subject' => sprintf(
					/* translators: 1: site name, 2: institution name. */
					__( '[%1$s] %2$s has no members left', 'wpcredits-program-manager' ),
					$site,
					$name
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

```

with:

```php
		$name    = is_array( $profile ) && ! empty( $profile['name'] ) ? (string) $profile['name'] : $record_id;
		$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$who     = $user->display_name;

		// The day written here, in the Administrator's language, as the sentence around it is.
		$build = static function () use ( $site, $name, $record_id, $who, $reason ) {
			return WPCPM_Mail_Templates::mail(
				self::NOTIFY_LAST_MEMBER,
				array(
					'site_name'          => $site,
					'member_name'        => $who,
					'institution_name'   => $name,
					'institution_record' => $record_id,
					'left_on'            => wp_date( get_option( 'date_format' ) ),
					'ended_because'      => $reason,
				)
			);
		};

```

- [ ] **Step 11: Run them to verify they pass**

Run, one per command: `php bin/test-institutions-sponsors-text.php`, `php bin/test-mail-catalog.php`, `php bin/test-institution-agreement.php`, `php bin/test-agreement-generate.php`, `php bin/test-institution-application.php`, `php bin/test-institution-invite.php`, `php bin/test-institution-members.php`, `php bin/test-institution-people.php`, `php bin/test-institutions-screen.php`, and `php -l` on each of the six classes
Expected: `ALL PASS (1513 checks)`; `ALL PASS (25 checks)`; `ALL PASS` seven times; `No syntax errors detected` six times.

- [ ] **Step 12: Run the whole battery**

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 13: Commit**

```bash
git add includes/modules/class-wpcpm-institution-application.php includes/modules/class-wpcpm-institutions.php includes/modules/class-wpcpm-institution-invite.php includes/modules/class-wpcpm-institution-agreement.php includes/modules/class-wpcpm-agreement-generate.php includes/modules/class-wpcpm-institution-members.php bin/test-institutions-sponsors-text.php bin/test-mail-catalog.php bin/test-agreement-generate.php bin/test-institution-agreement.php bin/test-institution-application.php bin/test-institution-invite.php bin/test-institution-members.php bin/test-institution-people.php bin/test-institutions-screen.php
git commit -m "Emails E4: the Institutions emails built from the template registry, word for word"
```

---


### Task 4: The Sponsors emails built from the template registry, word for word

As Task 3, for the six Sponsors classes: every builder returns `WPCPM_Mail_Templates::mail()`, with the Reply-To and the plain-text subject it always sent. The recording holds every Sponsors email to its bytes, under its own id; the Sponsors suites load the registry, and the one that pins the mentor request through its handler names its new id.

**Files:**
- Modify: `bin/test-institutions-sponsors-text.php` (`sent_as()` for the Sponsors; a sponsor's member and an Administrator who read Polish)
- Modify: `bin/test-sponsor-agreement.php`, `bin/test-sponsor-application.php`, `bin/test-sponsor-cards.php`, `bin/test-sponsor-claims.php`, `bin/test-sponsor-offers.php`, `bin/test-sponsor-posts.php`, `bin/test-sponsor-tools.php` (each loads the registry; bin/test-sponsor-cards.php names the mentor request's id)
- Modify: `includes/modules/class-wpcpm-sponsor-application.php`, `includes/modules/class-wpcpm-sponsor-agreement.php`, `includes/modules/class-wpcpm-sponsor-interests.php`, `includes/modules/class-wpcpm-sponsor-mentors.php`, `includes/modules/class-wpcpm-sponsor-claims.php`, `includes/modules/class-wpcpm-sponsor-posts.php`

**Interfaces:**
- Consumes: `WPCPM_Mail_Templates::mail()` and the Sponsors templates (Task 2); `WPCPM_Sponsor_Interests::mail_manager( $record, $build, $context = self::MAIL_CONTEXT )`, unchanged.
- Produces: `WPCPM_Sponsor_Application::MAIL_APPLIED_NO_LOGO` (`sponsor-applied-no-logo`); `WPCPM_Sponsor_Mentors::MAIL_CONTEXT` (`sponsor-mentor-interest`), which its handler hands `mail_manager()`; every builder of the six classes returns `WPCPM_Mail_Templates::mail()`.

- [ ] **Step 1: The recording's ids, and a sponsor's member and an Administrator who read Polish**

`sent_as()` answers the Sponsors' new ids for the cases that send them; and, as Task 3's check does for the Institutions, an unedited Sponsors template reaches a recipient who reads Polish in Polish, with every value worded for that recipient in Polish too (E4-7):

In `bin/test-institutions-sponsors-text.php`, replace:

```php
		'agreement-revoked-contact-with-no-address'  => 'agreement-revoked-no-contact',
		'agreement-revoked-no-country'               => 'agreement-revoked-no-contact',
		'agreement-revoked-unknown-country'          => 'agreement-revoked-no-contact',
		'agreement-revoked-no-contact-typed-in-a-browser' => 'agreement-revoked-no-contact',
	);

	return isset( $ids[ $case ] ) ? $ids[ $case ] : $recorded;
```

with:

```php
		'agreement-revoked-contact-with-no-address'  => 'agreement-revoked-no-contact',
		'agreement-revoked-no-country'               => 'agreement-revoked-no-contact',
		'agreement-revoked-unknown-country'          => 'agreement-revoked-no-contact',
		'agreement-revoked-no-contact-typed-in-a-browser' => 'agreement-revoked-no-contact',
		'sponsor-applied-logo-not-kept'              => 'sponsor-applied-no-logo',
		'sponsor-applied-names'                      => 'sponsor-applied-no-logo',
		'mentor-interest'                            => 'sponsor-mentor-interest',
		'mentor-interest-names-unassigned'           => 'sponsor-mentor-interest',
		'mentor-interest-unnamed-sponsor'            => 'sponsor-mentor-interest',
		'mentor-interest-unassigned-pages'           => 'sponsor-mentor-interest',
		'mentor-interest-non-ascii'                  => 'sponsor-mentor-interest',
	);

	return isset( $ids[ $case ] ) ? $ids[ $case ] : $recorded;
```

In `bin/test-institutions-sponsors-text.php`, replace:

```php
	array(
		'[pl_PL] We received your signed Collaboration Agreement on 2036-03-02, uploaded by [pl_PL] a member of your institution.',
		'We received your signed Collaboration Agreement on 2036-03-02, uploaded by a member of your institution.',
	)
);
```

with:

```php
	array(
		'[pl_PL] We received your signed Collaboration Agreement on 2036-03-02, uploaded by [pl_PL] a member of your institution.',
		'We received your signed Collaboration Agreement on 2036-03-02, uploaded by a member of your institution.',
	)
);

// The Sponsors as the Institutions: an unedited template reaches a recipient who reads Polish in
// Polish, and so does each value worded for that recipient. A sponsor's member who reads Polish is
// sent the returned agreement in Polish, "An Administrator" in Polish too when the Administrator's
// account holds no name, while the other member is sent it in English; and an Administrator who reads
// Polish is told of an upload in Polish, "somebody at the company" for an uploader whose account
// holds no name and the review window, counted, in Polish too.
$GLOBALS['user_locale'][60] = 'pl_PL';
$GLOBALS['user_locale'][40] = 'pl_PL';
$returned                   = emails_of( $cases['sponsor-agreement-returned-no-name-no-page'] );
$received                   = emails_of( $cases['sponsor-agreement-received-one-day-unnamed'] );
unset( $GLOBALS['user_locale'][60], $GLOBALS['user_locale'][40] );
$lines = array();
foreach ( $returned as $mail ) {
	$lines[] = $mail['subject'];
	$lines[] = strtok( $mail['body'], "\r" );
}
foreach ( $received as $mail ) {
	$lines = array_merge( $lines, array_slice( explode( "\r\n\r\n", $mail['body'] ), 0, 2 ) );
}
ck(
	'a sponsor\'s member who reads Polish is sent the returned agreement in Polish, "An Administrator" worded in Polish too, and the other member in English; an Administrator who reads Polish is told of the upload in Polish, its uploader with no name and its review window worded in Polish too',
	$lines,
	array(
		'[pl_PL] [WordPress Credits] Your Collaboration Agreement came back with a note',
		'[pl_PL] [pl_PL] An Administrator read your Collaboration Agreement and sent it back, with this note:',
		'[WordPress Credits] Your Collaboration Agreement came back with a note',
		'An Administrator read your Collaboration Agreement and sent it back, with this note:',
		'[pl_PL] recSEED0000000102 uploaded a signed Collaboration Agreement on 2036-03-03 ([pl_PL] somebody at the company).',
		'[pl_PL] The program aims to answer within [pl_PL] 1 working day.',
	)
);
```

- [ ] **Step 2: The Sponsors suites load the registry**

Seven suites drive the Sponsors senders through a stand-in of the mail layer that calls the builder, so each loads the catalog and the registry. bin/test-sponsor-cards.php pins the mentor request's id through the real handler:

In `bin/test-sponsor-agreement.php`, replace:

```php
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/class-wpcpm-request.php';
require_once __DIR__ . '/../includes/class-wpcpm-pdf-check.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
```

with:

```php
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/class-wpcpm-request.php';
require_once __DIR__ . '/../includes/class-wpcpm-pdf-check.php';
// The template registry, which every email's builder fills its words from since 1.122.22.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
```

In `bin/test-sponsor-application.php`, replace:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-guard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-image-upload.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-application.php';

```

with:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-guard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-image-upload.php';
// The template registry, which every email's builder fills its words from since 1.122.22.
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-application.php';

```

In `bin/test-sponsor-cards.php`, replace:

```php
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
```

with:

```php
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
// The template registry, which every email's builder fills its words from since 1.122.22.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
```

In `bin/test-sponsor-cards.php`, replace:

```php
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array();
WPCPM_Sponsors_Index::patch( $A, array( 'manager' => 'recTEAM0000000001' ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_mentor' => $M3 ), array( 'WPCPM_Sponsor_Mentors', 'handle_interest' ) );
ck( 'interest in a mentor is sent to the manager and logged, and lands on the looking card', array( $r[0], $r[1], $GLOBALS['sent'][0][2], end( $GLOBALS['audit'] )['kind'] ), array( 'mentor-interest-sent', 'looking', 'sponsor-interest', 'sponsor_interest_mentor' ) );
ck( 'the mail names the mentor', false !== strpos( $GLOBALS['sent'][0][3]['body'], 'Ana Looking' ), true );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_mentor' => $M1 ), array( 'WPCPM_Sponsor_Mentors', 'handle_interest' ) );
ck( 'a mentor who is not looking cannot be asked for', $r[0], 'mentor-interest-unknown' );
```

with:

```php
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array();
WPCPM_Sponsors_Index::patch( $A, array( 'manager' => 'recTEAM0000000001' ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_mentor' => $M3 ), array( 'WPCPM_Sponsor_Mentors', 'handle_interest' ) );
ck( 'interest in a mentor is sent to the manager under its own id, a sponsor\'s interest\'s until 1.122.22, and logged, and lands on the looking card', array( $r[0], $r[1], $GLOBALS['sent'][0][2], end( $GLOBALS['audit'] )['kind'] ), array( 'mentor-interest-sent', 'looking', 'sponsor-mentor-interest', 'sponsor_interest_mentor' ) );
ck( 'the mail names the mentor', false !== strpos( $GLOBALS['sent'][0][3]['body'], 'Ana Looking' ), true );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_mentor' => $M1 ), array( 'WPCPM_Sponsor_Mentors', 'handle_interest' ) );
ck( 'a mentor who is not looking cannot be asked for', $r[0], 'mentor-interest-unknown' );
```

In `bin/test-sponsor-claims.php`, replace:

```php
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/../includes/class-wpcpm-secret.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
```

with:

```php
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/../includes/class-wpcpm-secret.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
// The template registry, which every email's builder fills its words from since 1.122.22.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
```

In `bin/test-sponsor-offers.php`, replace:

```php
require_once __DIR__ . '/stubs/temp-dir.php';
require_once __DIR__ . '/../includes/class-wpcpm-secret.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
```

with:

```php
require_once __DIR__ . '/stubs/temp-dir.php';
require_once __DIR__ . '/../includes/class-wpcpm-secret.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
// The template registry, which every email's builder fills its words from since 1.122.22.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
```

In `bin/test-sponsor-posts.php`, replace:

```php
// them. Everything below that asks "what does a reader the level refuses see" now says so by
// taking the marker capability away.
require_once __DIR__ . '/../includes/class-wpcpm-content-access.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
```

with:

```php
// them. Everything below that asks "what does a reader the level refuses see" now says so by
// taking the marker capability away.
require_once __DIR__ . '/../includes/class-wpcpm-content-access.php';
// The template registry, which every email's builder fills its words from since 1.122.22.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
```

In `bin/test-sponsor-tools.php`, replace:

```php
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/../includes/class-wpcpm-secret.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
```

with:

```php
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/../includes/class-wpcpm-secret.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
// The template registry, which every email's builder fills its words from since 1.122.22.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
```

- [ ] **Step 3: Run them to verify they fail**

Run, one per command: `php bin/test-institutions-sponsors-text.php`, `php bin/test-sponsor-cards.php`
Expected: `14 FAILURE(S) (1514 checks)`, thirteen "logged under its own id" (the acknowledgement whose logo was not kept, twice; the mentor request, eleven), and the sponsor's member and the Administrator who read Polish: 1.122.21 asks for each of those sentences whole, the fallback or the count inside it, so the stand-in marks each once ("[pl_PL] An Administrator read ...", "... within 1 working day."), where the registry asks for the template's sentence and the value worded for the recipient each in the recipient's language, so each is marked (to see the check guard once Step 5 is done, work "An Administrator" out before the builder on a scratch copy, in the language of whoever pressed: the Polish member's line loses its second mark and the check fails); `1 FAILED (182 checks)` (the mentor request's id). The six other Sponsors suites pass.

- [ ] **Step 4: The sponsor application**

The acknowledgement chooses between its two templates by whether the logo files were kept, and logs under the one it chose:

In `includes/modules/class-wpcpm-sponsor-application.php`, replace:

```php
	 */
	const SIGNAL_HALF_DONE = 'half-done';

	/** The two mails this form sends, named for the mail log. */
	const MAIL_APPLIED  = 'sponsor-applied';
	const MAIL_MANAGERS = 'sponsor-application';

	/**
	 * The six decisions a manager takes, and the deletion behind them.
```

with:

```php
	 */
	const SIGNAL_HALF_DONE = 'half-done';

	/**
	 * The mails this form sends, named for the mail log: the acknowledgement, in its other words
	 * when the logo files were not kept, and the Administrators' notice.
	 */
	const MAIL_APPLIED         = 'sponsor-applied';
	const MAIL_APPLIED_NO_LOGO = 'sponsor-applied-no-logo';
	const MAIL_MANAGERS        = 'sponsor-application';

	/**
	 * The six decisions a manager takes, and the deletion behind them.
```

In `includes/modules/class-wpcpm-sponsor-application.php`, replace:

```php
		// the company is the only one who can send them again: saying so here is the difference
		// between a logo that arrives after approval and one nobody ever asks for.
		$skipped = in_array( 'files-skipped', (array) get_post_meta( $post_id, self::META_SIGNALS, true ), true );

		$build = function () use ( $site, $name, $reference, $email, $skipped ) {
			$lines = array(
				sprintf(
					/* translators: %s: the company's name as it was given on the form. */
					__( 'Thank you for offering to sponsor the WordPress Credits Program on behalf of %s. Your application is with us.', 'wpcredits-program-manager' ),
					$name
				),
				sprintf(
					/* translators: %s: the application reference, e.g. SAPP-2026-0007. */
					__( 'Your reference is %s. Please quote it if you write to us about this application.', 'wpcredits-program-manager' ),
					$reference
				),
				__( 'What happens next: an Administrator reads every application by hand and checks the company and its products against the program\'s licensing and trademark rules, so a reply takes a few working days. If the program says yes, we will set up an account on the program site and send you a link to choose a password.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: the address the message went to, 2: site name. */
					__( 'This message went to %1$s because that address was given on the sponsor application form at %2$s. If it was not you, you can ignore it: nothing happens until an Administrator decides.', 'wpcredits-program-manager' ),
					$email,
					$site
				),
			);

			if ( $skipped ) {
				array_splice(
					$lines,
					2,
					0,
					array( __( 'One thing about the logo: the site was taking more applications than usual when yours arrived, so the logo files you attached were not kept. Your answers were, and nothing else about the application was affected. If the program says yes, you can send the logo after your account is set up.', 'wpcredits-program-manager' ) )
				);
			}

			return array(
				'subject' => sprintf(
					/* translators: 1: site name, 2: the application reference. */
					__( '[%1$s] We have your application: %2$s', 'wpcredits-program-manager' ),
					$site,
					$reference
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		return WPCPM_Mail::send_to( $email, self::MAIL_APPLIED, $build );
	}

	/**
```

with:

```php
		// the company is the only one who can send them again: saying so here is the difference
		// between a logo that arrives after approval and one nobody ever asks for.
		$skipped = in_array( 'files-skipped', (array) get_post_meta( $post_id, self::META_SIGNALS, true ), true );
		$id      = $skipped ? self::MAIL_APPLIED_NO_LOGO : self::MAIL_APPLIED;

		$build = static function () use ( $id, $site, $name, $reference, $email ) {
			return WPCPM_Mail_Templates::mail(
				$id,
				array(
					'site_name'       => $site,
					'company_name'    => $name,
					'reference'       => $reference,
					'applicant_email' => $email,
				)
			);
		};

		return WPCPM_Mail::send_to( $email, $id, $build );
	}

	/**
```

In `includes/modules/class-wpcpm-sponsor-application.php`, replace:

```php
		$link    = self::queue_url( $post_id );
		$site    = WPCPM_Mail::site_name();

		$build = function () use ( $site, $name, $website, $person, $email, $link ) {
			$lines = array(
				__( 'A company has applied to sponsor the program through the form on the site, and the application passed every check.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: company name, 2: website. */
					__( '%1$s, %2$s', 'wpcredits-program-manager' ),
					$name,
					$website
				),
				sprintf(
					/* translators: 1: contact person's name, 2: their email address. */
					__( 'Contact: %1$s, %2$s', 'wpcredits-program-manager' ),
					$person,
					$email
				),
				__( 'It is on the queue here:', 'wpcredits-program-manager' ),
				$link,
			);

			return array(
				'subject' => sprintf(
					/* translators: 1: site name, 2: company name. */
					__( '[%1$s] New sponsor application from %2$s', 'wpcredits-program-manager' ),
					$site,
					$name
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

```

with:

```php
		$link    = self::queue_url( $post_id );
		$site    = WPCPM_Mail::site_name();

		$build = static function () use ( $site, $name, $website, $person, $email, $link ) {
			return WPCPM_Mail_Templates::mail(
				self::MAIL_MANAGERS,
				array(
					'site_name'     => $site,
					'company_name'  => $name,
					'website'       => $website,
					'contact_name'  => $person,
					'contact_email' => $email,
					'queue_link'    => $link,
				)
			);
		};

```

In `includes/modules/class-wpcpm-sponsor-application.php`, replace:

```php
		$said = WPCPM_Typed_Text::mail_text( $question );

		$build = static function () use ( $site, $reference, $said, $manager ) {
			$lines = array(
				__( 'Thank you for offering to sponsor the WordPress Credits Program. Before an Administrator can take your application further, they have one question:', 'wpcredits-program-manager' ),
				$said,
				__( 'Reply to this message and your answer reaches them directly.', 'wpcredits-program-manager' ),
			);

			return array(
				'subject' => sprintf(
					/* translators: 1: site name, 2: application reference, e.g. SAPP-2026-0007. */
					__( '[%1$s] A question about your application (%2$s)', 'wpcredits-program-manager' ),
					$site,
					$reference
				),
				'body'    => implode( "\r\n\r\n", $lines ),
				'headers' => WPCPM_Mail::reply_to( $manager ),
			);
		};

```

with:

```php
		$said = WPCPM_Typed_Text::mail_text( $question );

		$build = static function () use ( $site, $reference, $said, $manager ) {
			return WPCPM_Mail_Templates::mail(
				self::MAIL_INFO,
				array(
					'site_name' => $site,
					'reference' => $reference,
					'question'  => $said,
				),
				array( 'headers' => WPCPM_Mail::reply_to( $manager ) )
			);
		};

```

In `includes/modules/class-wpcpm-sponsor-application.php`, replace:

```php
		// The reason is deliberately not captured by this closure. Keeping it out of the
		// builder's scope is what stops a later edit reaching for it "just for the subject".
		$build = static function () use ( $site ) {
			$lines = array(
				__( 'Thank you for your interest in sponsoring the WordPress Credits Program, and for the time you took to write to us.', 'wpcredits-program-manager' ),
				__( 'We have read your company\'s application to sponsor the program, and we cannot accept it at this time. We are sorry not to have better news.', 'wpcredits-program-manager' ),
				__( 'You are welcome to apply again later, for example when your products or the program change. A new application is read on its own merits, and this decision does not count against it.', 'wpcredits-program-manager' ),
			);

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] About your application to sponsor the WordPress Credits Program', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		if ( is_email( $email ) ) {
```

with:

```php
		// The reason is deliberately not captured by this closure. Keeping it out of the
		// builder's scope is what stops a later edit reaching for it "just for the subject".
		$build = static function () use ( $site ) {
			return WPCPM_Mail_Templates::mail( self::MAIL_DECLINED, array( 'site_name' => $site ) );
		};

		if ( is_email( $email ) ) {
```

- [ ] **Step 5: The sponsor's Collaboration Agreement**

The uploader with no name, the working days and "An Administrator" are worked out inside the builder, in the reader's language:

In `includes/modules/class-wpcpm-sponsor-agreement.php`, replace:

```php
		$days  = max( 1, (int) WPCPM_Settings::get_value( 'agreement_review_days', 3 ) );
		$queue = admin_url( 'admin.php?page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-agreements' );

		$build = function () use ( $site, $facts, $days, $queue ) {
			$lines = array(
				sprintf(
					/* translators: 1: company name, 2: date, 3: who uploaded it. */
					__( '%1$s uploaded a signed Collaboration Agreement on %2$s (%3$s).', 'wpcredits-program-manager' ),
					$facts['sponsor_name'],
					$facts['uploaded_at'],
					'' === $facts['uploaded_by'] ? __( 'somebody at the company', 'wpcredits-program-manager' ) : $facts['uploaded_by']
				),
				sprintf(
					/* translators: %s: number of working days. */
					_n(
						'The program aims to answer within %s working day.',
						'The program aims to answer within %s working days.',
						$days,
						'wpcredits-program-manager'
					),
					number_format_i18n( $days )
				),
				__( 'Accept or return it on the Administrator Dashboard. Read it first on the Sponsors screen:', 'wpcredits-program-manager' ),
				$queue,
			);

			return array(
				'subject' => sprintf(
					/* translators: 1: site name, 2: company name. */
					__( '[%1$s] %2$s uploaded a signed Collaboration Agreement', 'wpcredits-program-manager' ),
					$site,
					$facts['sponsor_name']
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

```

with:

```php
		$days  = max( 1, (int) WPCPM_Settings::get_value( 'agreement_review_days', 3 ) );
		$queue = admin_url( 'admin.php?page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-agreements' );

		$build = static function () use ( $site, $facts, $days, $queue ) {
			return WPCPM_Mail_Templates::mail(
				self::MAIL_RECEIVED,
				array(
					'site_name'     => $site,
					'company_name'  => $facts['sponsor_name'],
					'uploaded_on'   => $facts['uploaded_at'],
					'uploaded_by'   => '' === $facts['uploaded_by'] ? __( 'somebody at the company', 'wpcredits-program-manager' ) : $facts['uploaded_by'],
					'review_window' => sprintf(
						/* translators: %s: a number of working days. */
						_n( '%s working day', '%s working days', $days, 'wpcredits-program-manager' ),
						number_format_i18n( $days )
					),
					'queue_link'    => $queue,
				)
			);
		};

```

In `includes/modules/class-wpcpm-sponsor-agreement.php`, replace:

```php
		$when = wp_date( 'Y-m-d' );
		$page = WPCPM_Sponsors_Dashboard::page_url();

		$build = function () use ( $site, $when, $page ) {
			$lines = array(
				sprintf(
					/* translators: %s: date the agreement was accepted. */
					__( 'Your Collaboration Agreement was accepted on %s. Nothing about your dashboard changes: it never depended on an agreement.', 'wpcredits-program-manager' ),
					$when
				),
				__( 'You can download the copy we hold from the agreement card on your dashboard.', 'wpcredits-program-manager' ),
			);

			if ( '' !== $page ) {
				$lines[] = $page;
			}

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] Your Collaboration Agreement is accepted', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

```

with:

```php
		$when = wp_date( 'Y-m-d' );
		$page = WPCPM_Sponsors_Dashboard::page_url();

		$build = static function () use ( $site, $when, $page ) {
			return WPCPM_Mail_Templates::mail(
				self::MAIL_ACCEPTED,
				array(
					'site_name'      => $site,
					'accepted_on'    => $when,
					'dashboard_link' => $page,
				)
			);
		};

```

In `includes/modules/class-wpcpm-sponsor-agreement.php`, replace:

```php
		$page    = WPCPM_Sponsors_Dashboard::page_url();
		$note    = WPCPM_Typed_Text::mail_text( $note );

		$build = function () use ( $site, $note, $named, $page, $manager ) {
			$lines = array(
				'' === $named
					? __( 'An Administrator read your Collaboration Agreement and sent it back, with this note:', 'wpcredits-program-manager' )
					: sprintf(
						/* translators: %s: the Administrator's name. */
						__( '%s read your Collaboration Agreement and sent it back, with this note:', 'wpcredits-program-manager' ),
						$named
					),
				$note,
				__( 'Upload the corrected document from the agreement card on your dashboard, or reply to this message.', 'wpcredits-program-manager' ),
			);

			if ( '' !== $page ) {
				$lines[] = $page;
			}

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] Your Collaboration Agreement came back with a note', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
				'headers' => WPCPM_Mail::reply_to( $manager instanceof WP_User ? $manager : null ),
			);
		};

```

with:

```php
		$page    = WPCPM_Sponsors_Dashboard::page_url();
		$note    = WPCPM_Typed_Text::mail_text( $note );

		$build = static function () use ( $site, $note, $named, $page, $manager ) {
			return WPCPM_Mail_Templates::mail(
				self::MAIL_RETURNED,
				array(
					'site_name'      => $site,
					// The Administrator by name, or, for an account that holds none, in the member's
					// language.
					'returned_by'    => '' === $named ? __( 'An Administrator', 'wpcredits-program-manager' ) : $named,
					'note'           => $note,
					'dashboard_link' => $page,
				),
				array( 'headers' => WPCPM_Mail::reply_to( $manager instanceof WP_User ? $manager : null ) )
			);
		};

```

In `includes/modules/class-wpcpm-sponsor-agreement.php`, replace:

```php
		$manager = wp_get_current_user();
		$note    = WPCPM_Typed_Text::mail_text( $note );

		$build = function () use ( $site, $note, $manager ) {
			$lines = array(
				__( 'Your Collaboration Agreement is no longer in force, with this note from the program:', 'wpcredits-program-manager' ),
				$note,
				__( 'Your dashboard, your offers and your codes are unchanged: they never depended on an agreement. Reply to this message if you would like to talk about a new one.', 'wpcredits-program-manager' ),
			);

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] Your Collaboration Agreement is no longer in force', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
				'headers' => WPCPM_Mail::reply_to( $manager instanceof WP_User ? $manager : null ),
			);
		};

```

with:

```php
		$manager = wp_get_current_user();
		$note    = WPCPM_Typed_Text::mail_text( $note );

		$build = static function () use ( $site, $note, $manager ) {
			return WPCPM_Mail_Templates::mail(
				self::MAIL_REVOKED,
				array(
					'site_name' => $site,
					'note'      => $note,
				),
				array( 'headers' => WPCPM_Mail::reply_to( $manager instanceof WP_User ? $manager : null ) )
			);
		};

```

In `includes/modules/class-wpcpm-sponsor-agreement.php`, replace:

```php
		$site = WPCPM_Mail::site_name();
		$when = wp_date( 'Y-m-d' );

		$build = function () use ( $site, $when ) {
			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] Your Collaboration Agreement is back in force', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => sprintf(
					/* translators: %s: today's date. */
					__( 'Your Collaboration Agreement is in force again as of %s. Nothing else changed.', 'wpcredits-program-manager' ),
					$when
				),
			);
		};

```

with:

```php
		$site = WPCPM_Mail::site_name();
		$when = wp_date( 'Y-m-d' );

		$build = static function () use ( $site, $when ) {
			return WPCPM_Mail_Templates::mail(
				self::MAIL_REINSTATED,
				array(
					'site_name'     => $site,
					'reinstated_on' => $when,
				)
			);
		};

```

- [ ] **Step 6: The interests card and the mentor request**

The request to sponsor a mentor is sent under its own id, handed to `mail_manager()`:

In `includes/modules/class-wpcpm-sponsor-interests.php`, replace:

```php
		WPCPM_Sponsors_Index::patch( $record, $patch );

		$sponsor = '' === trim( $row['name'] ) ? $record : trim( $row['name'] );
		$build   = static function ( $user ) use ( $sponsor, $said ) {
			return array(
				'subject' => sprintf(
					/* translators: %s: company name. */
					__( '[WordPress Credits] %s would like to support the program', 'wpcredits-program-manager' ),
					$sponsor
				),
				'body'    => sprintf(
					/* translators: 1: company name, 2: the dated line. */
					__( "%1\$s said on the Sponsor Dashboard:\n\n%2\$s\n\nThe full history is in the Sponsorship interests column of the Sponsors table.", 'wpcredits-program-manager' ),
					$sponsor,
					$said
				),
			);
		};

```

with:

```php
		WPCPM_Sponsors_Index::patch( $record, $patch );

		$sponsor = '' === trim( $row['name'] ) ? $record : trim( $row['name'] );
		$build   = static function () use ( $sponsor, $said ) {
			return WPCPM_Mail_Templates::mail(
				self::MAIL_CONTEXT,
				array(
					'company_name' => $sponsor,
					'interest'     => $said,
				)
			);
		};

```

In `includes/modules/class-wpcpm-sponsor-mentors.php`, replace:

```php
	const PER_DAY                = 5;
	const CEILING                = 'sponsor-mentor-interest';
	const LOG_KIND               = 'sponsor_interest_mentor';

	/**
	 * The handler.
```

with:

```php
	const PER_DAY                = 5;
	const CEILING                = 'sponsor-mentor-interest';
	const LOG_KIND               = 'sponsor_interest_mentor';
	/** The email this card sends, named for the mail log: sent as a sponsor's interest until 1.122.22. */
	const MAIL_CONTEXT           = 'sponsor-mentor-interest';

	/**
	 * The handler.
```

In `includes/modules/class-wpcpm-sponsor-mentors.php`, replace:

```php
		}

		$sponsor = '' === trim( $row['name'] ) ? $record : trim( $row['name'] );
		$build   = static function ( $user ) use ( $sponsor, $mentor ) {
			return array(
				'subject' => sprintf(
					/* translators: 1: company name, 2: mentor name. */
					__( '[WordPress Credits] %1$s would like to sponsor %2$s', 'wpcredits-program-manager' ),
					$sponsor,
					$mentor['name']
				),
				'body'    => sprintf(
					/* translators: 1: company name, 2: mentor name, 3: the mentor's WordPress.org profile. */
					__( '%1$s said on the Sponsor Dashboard that it would like to sponsor the mentor %2$s (%3$s). Please get in touch with both.', 'wpcredits-program-manager' ),
					$sponsor,
					$mentor['name'],
					$mentor['profile']
				),
			);
		};

		$mailed = WPCPM_Sponsor_Interests::mail_manager( $record, $build );

		// Logged whether or not anybody could be told: the audit row is the record that the
		// interest was ever raised, the same bargain the sponsor's own interests card
```

with:

```php
		}

		$sponsor = '' === trim( $row['name'] ) ? $record : trim( $row['name'] );
		$build   = static function () use ( $sponsor, $mentor ) {
			return WPCPM_Mail_Templates::mail(
				self::MAIL_CONTEXT,
				array(
					'company_name'   => $sponsor,
					'mentor_name'    => $mentor['name'],
					'mentor_profile' => $mentor['profile'],
				)
			);
		};

		$mailed = WPCPM_Sponsor_Interests::mail_manager( $record, $build, self::MAIL_CONTEXT );

		// Logged whether or not anybody could be told: the audit row is the record that the
		// interest was ever raised, the same bargain the sponsor's own interests card
```

- [ ] **Step 7: The codes**

Each builder keeps its subject plain text, as it did; the codes left and the warning level are handed over as numbers written out:

In `includes/modules/class-wpcpm-sponsor-claims.php`, replace:

```php
		// typed, and the subject is marked plain text, so the mail layer keeps a "<" and an "&" in
		// it as they are.
		$title = WPCPM_Typed_Text::mail_text( $offer['title'] );
		$build = static function ( $recipient ) use ( $user, $title, $last ) {
			return array(
				/* translators: 1: site name, 2: offer title. */
				'subject'       => sprintf( __( '[%1$s] A code from %2$s did not work', 'wpcredits-program-manager' ), WPCPM_Mail::site_name(), $title ),
				'body'          => sprintf(
					/* translators: 1: the claimant's name, 2: offer title, 3: the code's last four characters, 4: the Sponsors screen's address. */
					__( "%1\$s reports that their code for \"%2\$s\" (ending %3\$s) did not work.\n\nThe sponsor's offers and the people who claimed from them are on the Sponsors screen: %4\$s\n\nReply to this person from your own mailbox. The site never sends a code by mail.", 'wpcredits-program-manager' ),
					$user->display_name,
					$title,
					$last,
					admin_url( 'admin.php?page=wpcpm-sponsors&tab=offers' )
				),
				'headers'       => WPCPM_Mail::reply_to( $user ),
				'plain_subject' => true,
			);
		};

```

with:

```php
		// typed, and the subject is marked plain text, so the mail layer keeps a "<" and an "&" in
		// it as they are.
		$title = WPCPM_Typed_Text::mail_text( $offer['title'] );
		$build = static function () use ( $user, $title, $last ) {
			return WPCPM_Mail_Templates::mail(
				self::MAIL_PROBLEM,
				array(
					'site_name'          => WPCPM_Mail::site_name(),
					'offer_title'        => $title,
					'claimant_name'      => $user->display_name,
					'code_ending'        => $last,
					'offers_screen_link' => admin_url( 'admin.php?page=wpcpm-sponsors&tab=offers' ),
				),
				array(
					'headers'       => WPCPM_Mail::reply_to( $user ),
					'plain_subject' => true,
				)
			);
		};

```

In `includes/modules/class-wpcpm-sponsor-claims.php`, replace:

```php
		// a manager's needs it to land on this sponsor.
		$link  = add_query_arg( WPCPM_Sponsor_Roster::ARG_VIEW, $offer['sponsor'], WPCPM_Sponsors_Dashboard::page_url() ) . '#wpcpm-sponsor-offers';
		$title = WPCPM_Typed_Text::mail_text( $offer['title'] );
		$build = static function ( $recipient ) use ( $offer, $title, $available, $link ) {
			return array(
				/* translators: 1: site name, 2: offer title, 3: codes left. */
				'subject'       => sprintf( __( '[%1$s] %2$s: %3$d codes left', 'wpcredits-program-manager' ), WPCPM_Mail::site_name(), $title, $available ),
				'body'          => sprintf(
					/* translators: 1: offer title, 2: codes left, 3: the threshold, 4: the Offers card's address. */
					__( "The offer \"%1\$s\" has %2\$d codes left; it warns below %3\$d.\n\nAdd more codes, or pause the offer, from the Offers card: %4\$s\n\nThis is sent once. Adding codes arms it again.", 'wpcredits-program-manager' ),
					$title,
					$available,
					(int) $offer['low'],
					$link
				),
				// The title read back as in report_problem(), so the subject is plain text.
				'plain_subject' => true,
			);
		};

```

with:

```php
		// a manager's needs it to land on this sponsor.
		$link  = add_query_arg( WPCPM_Sponsor_Roster::ARG_VIEW, $offer['sponsor'], WPCPM_Sponsors_Dashboard::page_url() ) . '#wpcpm-sponsor-offers';
		$title = WPCPM_Typed_Text::mail_text( $offer['title'] );
		$build = static function () use ( $offer, $title, $available, $link ) {
			return WPCPM_Mail_Templates::mail(
				self::MAIL_LOW_STOCK,
				array(
					'site_name'   => WPCPM_Mail::site_name(),
					'offer_title' => $title,
					'codes_left'  => (string) (int) $available,
					'low_mark'    => (string) (int) $offer['low'],
					'offers_link' => $link,
				),
				// The title read back as in report_problem(), so the subject is plain text.
				array( 'plain_subject' => true )
			);
		};

```

- [ ] **Step 8: A returned post**

In `includes/modules/class-wpcpm-sponsor-posts.php`, replace:

```php
			(int) $post->post_author,
			self::MAIL_RETURNED,
			static function ( WP_User $author ) use ( $title, $said ) {
				return array(
					/* translators: %s: the post title. */
					'subject'       => sprintf( __( 'Your post "%s" needs a change before it is published', 'wpcredits-program-manager' ), $title ),
					'body'          => sprintf(
						/* translators: 1: the author's name, 2: the post title, 3: the Administrator's note, 4: the wp-admin posts list URL. */
						__( "Hello %1\$s,\n\nAn Administrator read your post \"%2\$s\" and sent it back as a draft with this note:\n\n%3\$s\n\nEdit it here and submit it for review again: %4\$s", 'wpcredits-program-manager' ),
						$author->display_name,
						$title,
						$said,
						admin_url( 'edit.php' )
					),
					'plain_subject' => true,
				);
			}
		);
```

with:

```php
			(int) $post->post_author,
			self::MAIL_RETURNED,
			static function ( WP_User $author ) use ( $title, $said ) {
				return WPCPM_Mail_Templates::mail(
					self::MAIL_RETURNED,
					array(
						'post_title'  => $title,
						'author_name' => $author->display_name,
						'note'        => $said,
						'edit_link'   => admin_url( 'edit.php' ),
					),
					array( 'plain_subject' => true )
				);
			}
		);
```

- [ ] **Step 9: Run them to verify they pass**

Run, one per command: `php bin/test-institutions-sponsors-text.php`, `php bin/test-sponsor-agreement.php`, `php bin/test-sponsor-application.php`, `php bin/test-sponsor-cards.php`, `php bin/test-sponsor-claims.php`, `php bin/test-sponsor-offers.php`, `php bin/test-sponsor-posts.php`, `php bin/test-sponsor-tools.php`, `php bin/test-mail-catalog.php`, and `php -l` on each of the six classes
Expected: `ALL PASS (1514 checks)`; `ALL PASS (215 checks)`; `ALL PASS (310 checks)`; `ALL PASS (182 checks)`; `ALL PASS (13 checks)`; `ALL PASS (273 checks)`; `ALL PASS (139 checks)`; `ALL PASS (91 checks)`; `ALL PASS (25 checks)` (the scan still finds 35: it no longer reads `sponsor-applied`, chosen between two now, and reads `sponsor-mentor-interest`, a constant); `No syntax errors detected` six times.

- [ ] **Step 10: Run the whole battery**

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 11: Commit**

```bash
git add includes/modules/class-wpcpm-sponsor-application.php includes/modules/class-wpcpm-sponsor-agreement.php includes/modules/class-wpcpm-sponsor-interests.php includes/modules/class-wpcpm-sponsor-mentors.php includes/modules/class-wpcpm-sponsor-claims.php includes/modules/class-wpcpm-sponsor-posts.php bin/test-institutions-sponsors-text.php bin/test-sponsor-agreement.php bin/test-sponsor-application.php bin/test-sponsor-cards.php bin/test-sponsor-claims.php bin/test-sponsor-offers.php bin/test-sponsor-posts.php bin/test-sponsor-tools.php
git commit -m "Emails E4: the Sponsors emails built from the template registry, word for word"
```

---


### Task 5: The editor, the preview and Send me a test for every Institutions and Sponsors email

The editor loop of bin/test-emails-templates.php already opens and tests every template after the Invitations (Task 2 gave it the examples it needs); this task names the four areas in its words, adds to it that a test carries no header, no Reply-To among them, and adds three blocks: a save of an Institutions or a Sponsors email that drops what it must keep, or puts in its subject what goes in the body, is refused with its sentence, nothing stored and the typing given back; an Institutions email's preview reads as its reader would read it, its date the site's; and an edit of a subject that holds a title as plain text is saved, tested as typed, "<" and all, and restored. It changes no code, so every check passes at once; each new check is shown to fail by a mutation (Step 2).

**Files:**
- Modify: `bin/test-emails-templates.php`

**Interfaces:**
- Consumes: `WPCPM_Emails::handle_save()`, `::handle_restore()` and `::render_admin_page()`, as 1.122.19 made them; the registry and its templates (Task 2); the senders (Tasks 3 and 4).
- Produces: nothing in code.

- [ ] **Step 1: The editor's checks for the new templates**

In `bin/test-emails-templates.php`, replace:

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

with:

```php
 *   to the presser's own address and to nobody a press names, and only for the text as saved:
 *   pressed with typing not saved yet, it sends nothing and gives the typing back as a refused save
 *   does.
 * - **The Mentor calls, the Institutions, the Semester reports and the Sponsors** work in the same
 *   editor, with the same buttons: each email's preview and test are filled as a real email to the
 *   presser would be, a call tomorrow at 3 pm on the presser's own clock and an agreement decided
 *   today on the site's among them; a save without a call's time, a link that carries a secret or
 *   what an Administrator or a sponsor wrote, or with any of them, a meeting link or a code's last
 *   characters in the subject, is refused; and an edit is saved, tested and restored, a subject that
 *   holds a title as plain text included.
 *
 * Run from the plugin root:  php bin/test-emails-templates.php
 */
```

In `bin/test-emails-templates.php`, replace:

```php
$GLOBALS['mail'] = array();
ck( 'a press naming an email the screen does not edit goes to the list, saying so, and sends nothing', array( press( 'handle_save', array( 'wpcpm_email' => 'call-booked', 'wpcpm_then' => 'test' ) ), $GLOBALS['mail'], false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails' ) ), 'Nothing was sent: the site has no such email.' ) ), array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates', array(), true ) );

echo "\n=== The Mentor calls and the Semester reports ===\n";

// An Administrator who keeps Tokyo's clock opens the mentor's booking confirmation: an editor as an
// invitation's, its placeholders the call's, the call's time and the meeting link required, and a
```

with:

```php
$GLOBALS['mail'] = array();
ck( 'a press naming an email the screen does not edit goes to the list, saying so, and sends nothing', array( press( 'handle_save', array( 'wpcpm_email' => 'call-booked', 'wpcpm_then' => 'test' ) ), $GLOBALS['mail'], false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails' ) ), 'Nothing was sent: the site has no such email.' ) ), array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates', array(), true ) );

echo "\n=== The Mentor calls, the Institutions, the Semester reports and the Sponsors ===\n";

// An Administrator who keeps Tokyo's clock opens the mentor's booking confirmation: an editor as an
// invitation's, its placeholders the call's, the call's time and the meeting link required, and a
```

In `bin/test-emails-templates.php`, replace:

```php
	array( 'edited', true, null, 'shipped' )
);

// Every email of the two areas, through its editor and its Send me a test, as an Administrator who
// keeps Tokyo's clock opens and presses them. A preview or a test is only filled if each example of a
// placeholder its text uses is in it, where the text puts the placeholder: an empty fill leaves no
// brace, so "no placeholder left" cannot tell it from a full one. Nor may a placeholder its text uses
// have no example here: every one has, the dashboards being published in this suite.
```

with:

```php
	array( 'edited', true, null, 'shipped' )
);

// Every email of the four areas after the Invitations, through its editor and its Send me a test, as
// an Administrator who keeps Tokyo's clock opens and presses them. A preview or a test is only filled if each example of a
// placeholder its text uses is in it, where the text puts the placeholder: an empty fill leaves no
// brace, so "no placeholder left" cannot tell it from a full one. Nor may a placeholder its text uses
// have no example here: every one has, the dashboards being published in this suite.
```

In `bin/test-emails-templates.php`, replace:

```php
			'gone' => $gaps( $template, $examples, $shown_subject[1] ?? '', $shown_body[1] ?? '' ),
		),
		'test'    => array(
			'sent'   => array( $went, count( $test ), $test[0]['to'] ?? null, 0 === strpos( $test[0]['subject'] ?? '', '[Test] ' ), $test[0]['context'] ?? null, $test[0]['test'] ?? null ),
			'braces' => false !== strpbrk( ( $test[0]['subject'] ?? '' ) . ( $test[0]['body'] ?? '' ), '{}' ),
			'gone'   => $gaps( $template, $examples, $test[0]['subject'] ?? '', $test[0]['body'] ?? '' ),
		),
```

with:

```php
			'gone' => $gaps( $template, $examples, $shown_subject[1] ?? '', $shown_body[1] ?? '' ),
		),
		'test'    => array(
			'sent'   => array( $went, count( $test ), $test[0]['to'] ?? null, $test[0]['headers'] ?? null, 0 === strpos( $test[0]['subject'] ?? '', '[Test] ' ), $test[0]['context'] ?? null, $test[0]['test'] ?? null ),
			'braces' => false !== strpbrk( ( $test[0]['subject'] ?? '' ) . ( $test[0]['body'] ?? '' ), '{}' ),
			'gone'   => $gaps( $template, $examples, $test[0]['subject'] ?? '', $test[0]['body'] ?? '' ),
		),
```

In `bin/test-emails-templates.php`, replace:

```php
		'editor'  => true,
		'preview' => array( 'left' => array(), 'gone' => array() ),
		'test'    => array(
			'sent'   => array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=' . $id, 1, $presser->user_email, true, $id, true ),
			'braces' => false,
			'gone'   => array(),
		),
```

with:

```php
		'editor'  => true,
		'preview' => array( 'left' => array(), 'gone' => array() ),
		'test'    => array(
			'sent'   => array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=' . $id, 1, $presser->user_email, array(), true, $id, true ),
			'braces' => false,
			'gone'   => array(),
		),
```

In `bin/test-emails-templates.php`, replace:

```php
		$series_test = $test[0]['body'] ?? '';
	}
}
ck( 'every email of the Mentor calls and the Semester reports opens in the editor, with Save and Send me a test, every placeholder of its text given an example, its preview filled with them; and its test, pressed as drawn, is one email, to the presser alone, marked [Test] and Test, under its own id, with no brace left and each example in it where its text puts the placeholder', $works, $wants );
ck(
	'a series\' test names its three weekly sessions on the presser\'s clock, one to a line',
	false !== strpos(
```

with:

```php
		$series_test = $test[0]['body'] ?? '';
	}
}
ck( 'every email of the Mentor calls, the Institutions, the Semester reports and the Sponsors opens in the editor, with Save and Send me a test, every placeholder of its text given an example, its preview filled with them; and its test, pressed as drawn, is one email, to the presser alone, with no header, no Reply-To among them, marked [Test] and Test, under its own id, with no brace left and each example in it where its text puts the placeholder', $works, $wants );
ck(
	'a series\' test names its three weekly sessions on the presser\'s clock, one to a line',
	false !== strpos(
```

In `bin/test-emails-templates.php`, replace:

```php
	true
);

echo "\n=== The Signature and footer tab ===\n";

new_administrator();
```

with:

```php
	true
);

// A save of an Institutions or a Sponsors email that drops a link carrying a secret, what an
// Administrator or a sponsor wrote or a post's title, or that puts a secret, what somebody wrote or a
// code's last characters in the subject the Log keeps, is refused with its sentence, stores nothing,
// and gives the typing back.
$refused_saves = array();
foreach ( array(
	'the invitation without its link'                => array( 'institution-invite', 'subject', '', "\n\n{accept_link}" ),
	'the invitation\'s link in the subject'          => array( 'institution-invite', 'subject', ' {accept_link}', '' ),
	'the confirmation without its link'              => array( 'institution-applied-no-call', 'body', '', "\n\n{verify_link}" ),
	'the returned agreement without its note'        => array( 'agreement-returned', 'body', '', "\n\n{note}" ),
	'a note in the subject'                          => array( 'sponsor-agreement-returned', 'subject', ' {note}', '' ),
	'a question in the subject'                      => array( 'sponsor-information', 'subject', ' {question}', '' ),
	'a code\'s last characters in the subject'       => array( 'claim-problem', 'subject', ' {code_ending}', '' ),
	'the returned post without its title in the body' => array( 'sponsor-post-returned', 'body', '', '"{post_title}"' ),
	'the sponsor\'s message without its line'        => array( 'sponsor-interest', 'body', '', "\n\n{interest}" ),
) as $what => $change ) {
	$shipped = WPCPM_Mail_Templates::get( $change[0] );
	$typed   = array( $shipped['subject'] . ( 'subject' === $change[1] ? $change[2] : '' ), str_replace( $change[3], '', $shipped['body'] ) );

	if ( '' === $change[3] ) {
		$typed[1] = $shipped['body'];
	}

	new_administrator();
	press( 'handle_save', array( 'wpcpm_email' => $change[0], 'subject' => $typed[0], 'body' => $typed[1] ) );
	$back                   = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => $change[0] ) );
	$refused_saves[ $what ] = array( preg_match( '#<div class="notice notice-error is-dismissible"><p>([^<]*)</p></div>#', $back, $said ) ? html_entity_decode( $said[1], ENT_QUOTES ) : null, box( $back, 'wpcpm-email-subject' ) === $typed[0] && box( $back, 'wpcpm-email-body' ) === $typed[1], WPCPM_Mail_Templates::edit( $change[0] ) );
}
ck(
	'a save of an Institutions or a Sponsors email without a secret link, a note, a question\'s place, a post\'s title or a sponsor\'s message, or with a secret, what somebody wrote or a code\'s end in the subject, is refused, stores nothing, and gives the typing back',
	$refused_saves,
	array(
		'the invitation without its link'                => array( 'Nothing was saved: this email needs {accept_link}.', true, null ),
		'the invitation\'s link in the subject'          => array( 'Nothing was saved: {accept_link} goes in the body, never in the subject.', true, null ),
		'the confirmation without its link'              => array( 'Nothing was saved: this email needs {verify_link}.', true, null ),
		'the returned agreement without its note'        => array( 'Nothing was saved: this email needs {note}.', true, null ),
		'a note in the subject'                          => array( 'Nothing was saved: {note} goes in the body, never in the subject.', true, null ),
		'a question in the subject'                      => array( 'Nothing was saved: {question} goes in the body, never in the subject.', true, null ),
		'a code\'s last characters in the subject'       => array( 'Nothing was saved: {code_ending} goes in the body, never in the subject.', true, null ),
		'the returned post without its title in the body' => array( 'Nothing was saved: this email needs {post_title}.', true, null ),
		'the sponsor\'s message without its line'        => array( 'Nothing was saved: this email needs {interest}.', true, null ),
	)
);

// An agreement returned, previewed as a member of the institution reads it: sent back today, on the
// site's clock, by an invented Administrator, with an example note, and the Institution Dashboard in
// the footer, since the body names none.
new_administrator();
$returned = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'agreement-returned' ) );
preg_match( '#<div class="wpcpm-emails__preview"><p><strong>Subject:</strong> ([^<]*)</p><pre class="wpcpm-emails__text">(.*?)</pre></div>#s', $returned, $shown_returned );
ck(
	'the agreement returned is previewed as a member reads it: sent back today on the site\'s clock, by an invented Administrator, with the note, and the Institution Dashboard in the footer',
	array( html_entity_decode( $shown_returned[1] ?? '', ENT_QUOTES ), str_replace( "\r\n", "\n", html_entity_decode( $shown_returned[2] ?? '', ENT_QUOTES ) ) ),
	array(
		'[WordPress Credits] Your signed agreement needs a change',
		'Your signed Collaboration Agreement was sent back on ' . ( new DateTimeImmutable( 'now', new DateTimeZone( 'Europe/Warsaw' ) ) )->format( 'Y-m-d' ) . " by Cleo Marsh, with this note:\n\nPlease sign page 2 as well, and date it.\n\nUpload the corrected copy from the agreement panel on the site. Replying to this message reaches the Administrator who wrote the note.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/institution-dashboard/\nThis email comes from the WordPress Credits program.",
	)
);

// An edit of a subject that holds a title as plain text, "<3" and all, is saved as the cleaner keeps
// it, its test sends it as typed, and Restore the original drops it.
$post_edit = array(
	'subject' => 'Your post "{post_title}" <3 needs a change',
	'body'    => WPCPM_Mail_Templates::get( 'sponsor-post-returned' )['body'],
);
new_administrator();
press( 'handle_save', array( 'wpcpm_email' => 'sponsor-post-returned' ) + $post_edit );
$post_saved      = WPCPM_Mail_Templates::state( 'sponsor-post-returned' )['state'];
$GLOBALS['mail'] = array();
press( 'handle_save', array( 'wpcpm_email' => 'sponsor-post-returned', 'wpcpm_then' => 'test' ) + $post_edit );
$post_test = $GLOBALS['mail'][0]['subject'] ?? '';
press( 'handle_restore', array( 'wpcpm_email' => 'sponsor-post-returned' ) );
ck(
	'an edit of a returned post\'s subject, a "<" in it, is saved, its test sends it as typed, and Restore the original drops it',
	array( $post_saved, $post_test, WPCPM_Mail_Templates::edit( 'sponsor-post-returned' ) ),
	array( 'edited', '[Test] Your post "Our first study day" <3 needs a change', null )
);

echo "\n=== The Signature and footer tab ===\n";

new_administrator();
```

- [ ] **Step 2: Run it, and see each new check fail when what it guards breaks**

Run: `php bin/test-emails-templates.php`
Expected: `ALL PASS (70 checks)`.

Then, one at a time on a scratch copy, never committed, each new check is seen to fail when what it guards breaks: with `'required'  => false,` in place of `'required'  => in_array( $name, $required, true ),` in `includes/mail/templates/institutions.php`'s `$wpcpm_take`, the refusals' check fails (the saves that drop a secret link or the returned agreement's note are stored), and so does the preview's, which then shows the stored edit; with `'body_only' => true,` taken from the `question` placeholder in `includes/mail/templates/sponsors.php`, the refusals' check fails (the question in the subject is stored); with `'headers' => array( 'Reply-To: x@example.test' ),` added to what `WPCPM_Mail_Templates::test_of()` returns, the loop fails, and so does the invitation's test check above it; with `$day( -1 )` for `returned_on`'s example in `institutions.php`, the preview check fails; and with `'plain_subject' => true,` taken from `test_of()`, the edited subject's test reads `&lt;3` and the last check fails. Each mutation is put back after its run.

- [ ] **Step 3: Run the whole battery**

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 4: Commit**

```bash
git add bin/test-emails-templates.php
git commit -m "Emails E4: the editor, the preview and Send me a test for every Institutions and Sponsors email"
```

---


### Task 6: The guide, the version, the changelog and the template

**Files:**
- Modify: `docs/sections/32-admin-tools.md` (the Emails part: Templates, the editor, Save, Send me a test, the Log)
- Regenerate: `docs/administrators.md` and `docs/build/administrators.html` with `php bin/build-docs.php` (the Administrator guide is the one that holds the Tools part; the student, mentor and sponsor guides do not change)
- Modify: `wpcredits-program-manager.php` (`Version:` at line 6, `WPCPM_VERSION` at line 22), `readme.txt` (`Stable tag` at line 7, the 1.122.22 entry above 1.122.21's)
- Regenerate: `languages/wpcredits-program-manager.pot` with `sh bin/make-pot.sh`

**Interfaces:**
- Consumes: the strings Tasks 2 to 4 added, which the template collects.
- Produces: nothing new in code; the guide, version 1.122.22, its changelog entry and the template.

- [ ] **Step 1: The guide's Emails part**

The Templates part names the five areas and what each holds, says that each of an email's two templates is edited on its own, and says since when each email's shipped text is what it said before; the editor's part says how an Institutions or a Sponsors date and count arrive, and lists what a save must keep and keep out of the subject, as a list where it was one long sentence; Send me a test names every kind of email whose reply address is added only when it is really sent; the Log's part says what the rows of before are named:

In `docs/sections/32-admin-tools.md`, replace:

```markdown

#### Templates

The plugin's emails, grouped by area, each area in a table of its own. In this version there are
three:

- **Invitations**: the student's, the mentor's and the sponsor's invitation, and the institution's
  two, one for an institution whose Collaboration Agreement is not on file yet and one for an
```

with:

```markdown

#### Templates

The plugin's emails, grouped by area, each area in a table of its own. There are five:

- **Invitations**: the student's, the mentor's and the sponsor's invitation, and the institution's
  two, one for an institution whose Collaboration Agreement is not on file yet and one for an
```

In `docs/sections/32-admin-tools.md`, replace:

```markdown
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

with:

```markdown
  student on it, so each is one email. A mentor who has not set a meeting link yet is told so where
  the others are told where to meet, so the mentor's three confirmations, of a call, of a join and of
  a series, each have a second email for that case.
- **Institutions**: what an institution and the Administrators are told from its application to
  its last member: the acknowledgement of an application, the notice of it to the Administrators, an
  Administrator's question and the rejection; a member's invitation to a colleague; the institution's
  Collaboration Agreement received, uploaded, accepted, put back in force, returned with a note and
  revoked, the daily list of agreements waiting too long, and the notice that the agreement's
  template could not be used; and the notice that an institution has no members left. An email that
  says other words in one case than in another is two: the acknowledgement for a country with a call
  to book and for one without, the upload with the courtesy scan's notes and without, and the
  revocation with a country contact named and without one.
- **Semester reports**: the notice to the Administrators that the daily job drafted a report, the
  approval to the institution's accounts, and the question to the students whether the report may
  mention them.
- **Sponsors**: the acknowledgement of a sponsor application (and its twin for an application whose
  logo files were not kept), the notice of it to the Administrators, an Administrator's question and
  the rejection; the sponsor's Collaboration Agreement received, accepted, returned with a note, out
  of force and back in force; what a sponsor says on the Sponsor Dashboard it would like to support,
  and its request to sponsor a mentor; the warning that an offer's codes are running low and a
  claimant's report of a code that did not work; and a sponsor's post sent back to its author.

Where an email is two, as the mentor's confirmations with and without a meeting link and the four
above are, each of the two is edited on its own: an edit of one leaves the other as shipped.

Each row names the email, who receives it and what sends it, its state, and who edited it last and
when. The states:

- **Shipped text**: the email is sent as the plugin ships it, which is word for word what it said
  before it could be edited: before 1.122.19 for the Invitations, before 1.122.21 for the Mentor
  calls and the Semester reports, and before 1.122.22 for the Institutions and the Sponsors.
- **Edited**: it is sent as an Administrator wrote it, to everybody, in that wording.
- **Edited, and the shipped text has changed since**: an update changed the shipped text after the
  edit was saved. The edit is still the one sent, and its editor opens the new shipped text under
```

In `docs/sections/32-admin-tools.md`, replace:

```markdown

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
  so the email stays shipped text. A subject made of placeholders alone, such as `{mentor_name}`,
  is saved; if they all come out empty, the email goes out with its shipped subject rather than with
  none, since an email with no subject is not sent.
- **Send me a test**, beside **Save**, sends the email as it is saved, filled with the example values
  and with "[Test]" before its subject, to your own address and to nobody else. The Log marks it
  **Test**. Save what you typed first: pressed with typing not saved yet, it sends nothing, and the
  editor comes back with your typing and says so. A test carries the subject and the body alone: the
  calendar file and the reply address that a Mentor calls email goes out with are added when it is
  really sent, so a test of one arrives without them.
- **Restore the original** is there once the email is edited. It asks first, then drops the edit,
  and the shipped text is sent from then on.

```

with:

```markdown

An email about a call shows every time on its recipient's own clock: `{when}` is the call's start
and end as the recipient reads them, and `{time_zone}` names that clock, so a mentor in Tokyo and a
student in Warsaw each read the call at their own hour. A date in an Institutions or a Sponsors
email, such as `{accepted_on}`, is the site's, as it always was. A count arrives in words, such as
`{session_count}`, "3 sessions", or `{review_window}`, "3 working days", so no template needs a rule
for one and several.

- **Save** stores the subject and the body. It is refused, nothing is stored, and the editor comes
  back with what you typed, when:
  - a box is empty;
  - a required placeholder is missing. Every invitation needs `{set_password_link}`, the link that
    sets the password, and so does every other link that carries a secret: an application's
    confirmation link, `{verify_link}`, and a colleague's invitation to an institution,
    `{accept_link}`. Every email about a call needs `{when}`, its time (a series, `{session_dates}`),
    and a semester report `{semester}`. What an Administrator or a sponsor wrote, where it is the
    email's point, is needed too: a question to an applicant, `{question}`; a note on a returned or
    revoked agreement or a returned post, `{note}`; and what a sponsor said, `{interest}`. A returned
    post needs its title and its author's name, `{post_title}` and `{author_name}`, either of which
    can be empty. And an email sent only with a value its
    other words lack needs it: the mentor's confirmation sent with a meeting link, `{meeting_place}`;
    the acknowledgement with a call to book, `{booking_link}`; the upload with the scan's notes,
    `{scan_flags}`; the revocation with a country contact, `{country_contact}`;
  - a link or a typed text that goes in the body is in the subject: a link that carries a secret, a
    mentor's meeting link, `{meeting_place}`, which can carry the meeting's passcode, a code's last
    characters, `{code_ending}`, and what a person typed, such as `{topic}`, `{question}` or
    `{note}`, or an applicant's own answers, such as `{applicant_email}` or `{website}`. A subject is
    not a link anybody can follow, and the Log keeps it;
  - a placeholder is not one of the email's (a misspelled one is named as one the email does not
    have; a misspelled required one is reported as missing instead);
  - WordPress would remove part of the text; or
  - the subject is longer than 200 characters or the body longer than 10,000.

  A save of the shipped text as it stands stores nothing, so the email stays shipped text. A subject
  made of placeholders alone, such as `{mentor_name}`, is saved; if they all come out empty, the
  email goes out with its shipped subject rather than with none, since an email with no subject is
  not sent.
- **Send me a test**, beside **Save**, sends the email as it is saved, filled with the example values
  and with "[Test]" before its subject, to your own address and to nobody else. The Log marks it
  **Test**. Save what you typed first: pressed with typing not saved yet, it sends nothing, and the
  editor comes back with your typing and says so. A test carries the subject and the body alone: the
  calendar file a Mentor calls email carries, and the reply address that several emails go out with
  (a Mentor calls email, an Administrator's question to an applicant, an agreement sent back with a
  note, a sponsor's agreement taken out of force, a colleague's invitation, a code that did not
  work), are added when it is really sent, so a test of one arrives without them.
- **Restore the original** is there once the email is edited. It asks first, then drops the edit,
  and the shipped text is sent from then on.

```

In `docs/sections/32-admin-tools.md`, replace:

```markdown
with the count above them. Until 1.122.21 the Mentor calls emails were named by five names that each
covered the mentor's copy and the students' alike, such as **Call booked** and **Call reminder**;
the rows sent before keep those names, which now end "(until 1.122.21)", for the 30 days the log
keeps them.

The log never keeps the text of an email, its attachments, or anything secret such as a sign-in
code or a password link. Entries older than 30 days are deleted every day. WordPress's **Export
```

with:

```markdown
with the count above them. Until 1.122.21 the Mentor calls emails were named by five names that each
covered the mentor's copy and the students' alike, such as **Call booked** and **Call reminder**;
the rows sent before keep those names, which now end "(until 1.122.21)", for the 30 days the log
keeps them. Until 1.122.22 a Collaboration Agreement put back in force was logged as **Agreement
accepted** and a sponsor's request to sponsor a mentor as **Sponsor interest**; each has a name of
its own since, and the institution's agreement emails, the rows sent before included, are named for
the Collaboration Agreement in full, such as **Collaboration Agreement accepted, to the
institution**.

The log never keeps the text of an email, its attachments, or anything secret such as a sign-in
code or a password link. Entries older than 30 days are deleted every day. WordPress's **Export
```

Run: `php bin/build-docs.php` (the plain build, so the committed guides keep their relative image paths), then, one per command, `php bin/test-cli.php`, `php bin/check-spelling.php`, `php bin/test-settings.php`, `php bin/test-admin-menu.php`
Expected: the build prints a line per guide and `Done.`, and `git status --short docs/` lists `docs/administrators.md`, `docs/build/administrators.html` and the section alone; `ALL PASS (14 checks)`; `... US English throughout.`; `ALL PASS`; `ALL PASS` (no guide section says module or program manager, and every menu path steps from WPCredits Program with ">").

- [ ] **Step 2: The version and the entry**

In `wpcredits-program-manager.php`, replace:

```php
 * Plugin Name:       WPCredits Program Manager
 * Plugin URI:        https://github.com/gomp/wpcredits-program-manager
 * Description:       Runs the WPCredits program on WordPress for five audiences - Students, Mentors, Institutions, Sponsors and Administrators - plus a Tools section. Provisions role-based accounts from Airtable, gives each mentor a Mentor Report Card listing the students assigned to them, and includes the Mentor Status Checker.
 * Version:           1.122.21
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Maciej Pilarski
```

with:

```php
 * Plugin Name:       WPCredits Program Manager
 * Plugin URI:        https://github.com/gomp/wpcredits-program-manager
 * Description:       Runs the WPCredits program on WordPress for five audiences - Students, Mentors, Institutions, Sponsors and Administrators - plus a Tools section. Provisions role-based accounts from Airtable, gives each mentor a Mentor Report Card listing the students assigned to them, and includes the Mentor Status Checker.
 * Version:           1.122.22
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Maciej Pilarski
```

In `wpcredits-program-manager.php`, replace:

```php
	exit; // No direct access.
}

define( 'WPCPM_VERSION', '1.122.21' );
define( 'WPCPM_PLUGIN_FILE', __FILE__ );
define( 'WPCPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPCPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
```

with:

```php
	exit; // No direct access.
}

define( 'WPCPM_VERSION', '1.122.22' );
define( 'WPCPM_PLUGIN_FILE', __FILE__ );
define( 'WPCPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPCPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
```

In `readme.txt`, replace:

```text
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.122.21
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

```

with:

```text
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.122.22
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

```

In `readme.txt`, replace:

```text

== Changelog ==

= 1.122.21 =

* WPCredits Program > Tools > Emails, Templates: the Mentor calls and the Semester reports emails can be edited too, each with who receives it, what sends it, its state, Send me a test and Restore the original, as the invitations can. Their shipped text is word for word what they said before; the one line that changes is in the drafted and approved report emails, when the Institution Dashboard page is not published, which no longer leave a second blank line above the signature and footer.
```

with:

```text

== Changelog ==

= 1.122.22 =

* WPCredits Program > Tools > Emails, Templates: the Institutions and the Sponsors emails can be edited too, so every email the plugin sends while it runs is now in the list, in five areas: Invitations, Mentor calls, Institutions, Semester reports and Sponsors. Each has who receives it, what sends it, its state, Send me a test and Restore the original. Their shipped text is word for word what they said before.
* Where one email said other words in one case than in another, each case is an email of its own: the acknowledgement of an institution's application with a call to book and without one, the notice of an uploaded agreement with the courtesy scan's notes and without, the revocation with a country contact named and without, and the acknowledgement of a sponsor application whose logo files were not kept. A Collaboration Agreement put back in force, logged as an acceptance until now, and a sponsor's request to sponsor a mentor, logged as its interest, have names of their own.
* Every link that carries a secret, an application's confirmation link and a colleague's invitation to an institution, is needed in its email and goes in the body, never in the subject. What an Administrator or a sponsor wrote, a question, a note or a sponsor's message, is needed where it is the email's point and goes in the body; so does a code's last four characters, and an applicant's own answers (an address, a website, a contact, a city). A returned post needs its title and its author's name, either of which can be empty. A rejection still carries none of the reason the Administrator wrote.
* An uploader with no name ("a member of your institution") and the day a membership ended are worded in the recipient's language, as the rest of their email always was, where they used the language of whoever pressed.
* The institution's agreement emails are named for the Collaboration Agreement in full in the Templates tab and the Log, such as "Collaboration Agreement accepted, to the institution".
* For developers: the registry's areas gain includes/mail/templates/institutions.php and sponsors.php, each area's file read in a scope of its own (`WPCPM_Mail_Templates::area()`), and `WPCPM_Mail_Templates::mail()` hands a builder its email as the mail layer takes it. `WPCPM_Mail_Catalog` gains six ids, `institution-applied-no-call`, `agreement-landed-flagged`, `agreement-reinstated`, `agreement-revoked-no-contact`, `sponsor-applied-no-logo` and `sponsor-mentor-interest`, and the `wpcpm_mail` filter is handed them as `$context`, where it was handed `institution-applied`, `agreement-landed`, `agreement-accepted`, `agreement-revoked`, `sponsor-applied` and `sponsor-interest` for those emails. `WPCPM_Institution_Agreement::contact_line()` is `contact_of()`, the contact's facts alone. `bin/test-institutions-sponsors-text.php` holds every Institutions and Sponsors email to a recording made from 1.122.21 before the move.

= 1.122.21 =

* WPCredits Program > Tools > Emails, Templates: the Mentor calls and the Semester reports emails can be edited too, each with who receives it, what sends it, its state, Send me a test and Restore the original, as the invitations can. Their shipped text is word for word what they said before; the one line that changes is in the drafted and approved report emails, when the Institution Dashboard page is not published, which no longer leave a second blank line above the signature and footer.
```

- [ ] **Step 3: The template**

Run: `sh bin/make-pot.sh`
Expected: `Success: POT file successfully generated.` with the same warnings as main's (every one about a string whose two callers comment it differently, none new), or, without WP-CLI, gettext's path, which prints nothing on success. 112 strings arrive in the template, each with its translators comment, among them the six new names and the seven institution agreement names in full ("Collaboration Agreement accepted, to the institution"), the triggers, the placeholders' meanings, the counted words (`%s working day` and `%s working days`, `%s signed agreement is`, `%s signed agreement has been`), and the new sentences that take a counted value (`An Administrator will read it and you will get an email either way, usually within %s.`, `The program aims to answer within %s.`, `%1$s waiting longer than the %2$s-day review window.`, `[%1$s] %2$s waiting for review`). Fourteen leave it: the seven agreement names that said "Agreement" alone; the four sentences `_n()` worded whole (`An Administrator will read it ... within %s working day.`, `The program aims to answer within %s working day.`, `%1$s signed agreement has been waiting longer than the %2$s-day review window.`, `[%1$s] %2$s signed agreement is waiting for review`), each with its plural; `An Administrator read your Collaboration Agreement and sent it back, with this note:`, whose words are `%s read your ...` with the value "An Administrator", a string the template already holds; and the low codes' two strings with `%d` (`[%1$s] %2$s: %3$d codes left` and the body's), which hold `%s` now.

- [ ] **Step 4: Run the release's suites, the standards and the dashes**

Run, one per command: `php bin/test-tooling.php`, `php bin/check-spelling.php`, `php bin/test-fixtures.php`, `php bin/test-admin-menu.php`, `php bin/test-mail-templates.php`, `php bin/test-emails-templates.php`, `php bin/test-institutions-sponsors-text.php`, `php bin/test-calls-reports-text.php`, `php bin/test-invitation-text.php`, `sh bin/check-standards.sh`
Expected: `ALL PASS`; `... US English throughout.`; `ALL PASS`; `ALL PASS`; `ALL PASS (87 checks)`; `ALL PASS (70 checks)`; `ALL PASS (1514 checks)`; `ALL PASS (398 checks)`; `ALL PASS (40 checks)`; the standards' last line reads `2 errors and 78 warnings. The standard is the gate: fix the errors.`, both errors main's own (`includes/class-wpcpm-mail.php` line 347 and `includes/modules/class-wpcpm-call-calendar.php` line 899), none in a line this release writes (main reads `2 errors and 85 warnings`; the moved builders took seven warnings with them). Then search the release's diff for dashes, with perl, since macOS's grep has no `-P` and would print nothing whatever the diff holds: `git diff main -- . | perl -CSD -ne 'print if /[\x{2013}\x{2014}]/'` prints nothing.

- [ ] **Step 5: Run the whole battery**

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 6: Commit**

```bash
git add docs/ wpcredits-program-manager.php readme.txt languages/wpcredits-program-manager.pot
git commit -m "1.122.22: the Institutions and Sponsors emails in Tools > Emails; the guide, the version, the changelog and the template"
```

---


## After the tasks (the controller's, not an implementer's)

1. The whole-release review, one fix wave, a scoped re-review.
2. The gate on a frozen copy: no conflict markers, `php -l` on every file, `php bin/check-references.php`, the whole battery (`for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done`, printing nothing), `sh bin/check-standards.sh` with no more errors than 1.122.21 (2), and `php bin/check-dead-annotations.php` with none dead, all green. `php bin/test-institutions-sponsors-text.php` passing is the word-for-word proof: every Institutions and Sponsors email is the recording of 1.122.21, byte for byte.
3. The zip from a clean clone (`bash bin/build ~/GitHub/wpcredits-program-manager.zip`).
4. The local copy (localhost:8883): snapshot the database; install; then try, as an Administrator:
   - WPCredits Program > Tools > Emails opens on **Templates**: five areas, Invitations (five rows), Mentor calls (19), Institutions (17), Semester reports (3) and Sponsors (15), each Shipped text and Not edited, each Edit opening its editor, and at 375px each table scrolling in its own box while the page does not.
   - On **Collaboration Agreement returned with a note, to the institution**: the preview reads as a member's email, sent back today on the site's clock by Cleo Marsh, then the footer with the Institution Dashboard. **Send me a test**: one email, to your own address, "[Test] " before the subject, no Reply-To; in the Log, **Not confirmed** (the copy's safety plugin takes every email over through `pre_wp_mail`), marked **Test**, area Institutions. Save refused, each with its sentence and with what you typed still in the boxes: `{note}` deleted from the body ("this email needs {note}"); `{note}` added to the subject ("goes in the body, never in the subject").
   - With the copy's test institution and test sponsor: submit an application through each form (one country with a call to book), ask each applicant a question and reject one; invite a colleague; upload, accept, return, revoke and reinstate an agreement on each side; run the digest (`studio wp cron event run wpcpm_agreement_reminders`, for an upload older than the review window); register interest on the Sponsor Dashboard and in a mentor; claim codes below an offer's warning level and report one; return a sponsor post. Read each email in the copy's safety log beside the recording's words, and in the Log under its name, the reinstatement as "Collaboration Agreement back in force, to the institution" and the mentor request as "Request to sponsor a mentor".
   - Edit **Codes running low** (add a sentence), Save, claim below the level again (after adding codes, which arms it): the edit arrives to the sponsor's accounts and its Administrator; then **Restore the original**, which asks first.
   - The Log's **Email** field lists the six new names and the agreement names in full, and filters by each.
   Put the database back.
5. The mirror push and the live deploy on the owner's yes, with the usual recipe, the live version read first (`wp plugin get wpcredits-program-manager --field=version` reads 1.122.21). After the install: the Templates tab lists the five areas as Shipped text, `wp option get wpcpm_mail_templates` holds nothing new, and one Send me a test of an Institutions template arrives with "[Test] " and the footer and shows in the Log as **Handed to the mail server**, marked Test, pressed from the Administrator account whose address is the one every test mail on the site goes to (the owner's rule), checked first with `wp user get <id> --field=user_email`. The build changes the Administrator guide alone, so its page (560) is republished from a `--base=` build (E4-8: the deploy republishes the guide pages that changed); the student and mentor guides' pages (558, 559) stay as they are, since their build is unchanged. One read-only check before the install: nothing on the live site keys on `institution-applied`, `agreement-landed`, `agreement-accepted`, `agreement-revoked`, `sponsor-applied` or `sponsor-interest` through the `wpcpm_mail` filter (the site's mu-plugins and its code snippets searched for `wpcpm_mail`), since from this version the filter is handed the six new ids (`institution-applied-no-call`, `agreement-landed-flagged`, `agreement-reinstated`, `agreement-revoked-no-contact`, `sponsor-applied-no-logo`, `sponsor-mentor-interest`) for the emails those six were sent under.
6. For the owner:
   - **Told: the 28th email.** The spec counts 27; the request to sponsor a mentor, sent under the interests card's id, is the 28th, and has its own name now ("Request to sponsor a mentor").
   - **Chosen, told, and asked: the agreement names in full.** The seven names of the institution's agreement emails say "Collaboration Agreement" in the Templates tab and the Log, rows from before included, by E4-8; the plan review agrees, and the owner can keep the short names instead with one line each in the catalog. Answered (owner, 11 Oct 2026): the sponsor's five agreement names also say who receives them (", to Administrators" for the one received, ", to the sponsor" for the other four), read from each sender's recipients, so the Log's Email field, which lists names A to Z with no area, tells them from the institution's.
   - **Chosen, told: four emails as two templates each.** The acknowledgement with or without a call to book, the upload with or without the scan's notes, the revocation with or without a country contact, and the sponsor's acknowledgement with the logo kept or not, as E3 made the mentor's no-link confirmations: the registry leaves a line out, never puts one in. An Administrator who edits one edits its twin separately, which the guide says.
   - **Told: the templates' names**, this plan's suggestions, listed in Task 2's catalog suite; the owner can change any.
   - **Told: a returned post needs its author's name.** `{author_name}` is required, as `{post_title}` is, so an edit of that email must keep it: an author's account can hold no name, and the registry would otherwise leave out the "Hello ," line 1.122.21 sent.
   - **Told, kept word for word, a later release's to fix if wanted:** the low codes' warning says "1 codes left"; with the Sponsor Dashboard page not published its Offers card link is a bare `?wpcpm_sponsor_view=...#wpcpm-sponsor-offers`; an Administrator whose account holds no name signs a returned or revoked institution agreement "by , with this note:"; an invitation sent with no inviter left on the account opens " has invited you"; and the sponsor's interest emails say "[WordPress Credits]" where every other email names the site. An edit can reword three of them in the Templates tab; it cannot count "codes" (a template has no plural rule, so "1 codes left" can be reworded, never made "1 code"), cannot name the site in the interest emails (neither declares `{site_name}`, so an edit can only write another fixed name), and cannot mend the link, which is a value. The plan review's view: fix all five in a later release.
   - **Told, kept word for word, not one of the five: names as WordPress stores them.** The institution's acknowledgement and the Administrators' "New institution application" print the application's title as WordPress stored it for a visitor who was not signed in, so an applicant from "Arts & Sciences" reads `Arts &amp; Sciences`, subject included; and every email that names an account prints its stored name, "&" as `&amp;` and a "<" as `&lt;` ("uploaded by", "sent back ... by", "revoked on ... by", the claimant, the member, the inviter, a post's author, and the name in a Reply-To). The interests card's email alone reads the name back to what was typed. The recording pins each, and the release after E4 can take them with the five.
   - **Told, kept as 1.122.21 made it: the generator's reason.** `{reason}` in "The Collaboration Agreement template needs attention" is the template's own refusal, a message made in the language of the member who asked before any email is built, so an Administrator who reads another language reads that sentence in the member's.
   - **Told, not asked: the low codes to a sponsor's member who is also a student.** That email says the same words to the sponsor's accounts and its Administrator, so its audience is empty and the account decides, as the two shared session emails of the Mentor calls do: such a member's footer names the Student Report Card. A later release can let a builder name the reader's audience.
