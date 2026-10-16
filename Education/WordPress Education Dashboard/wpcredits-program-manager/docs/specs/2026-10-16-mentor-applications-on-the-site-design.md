# Mentor applications on the site: the design

The mentor sign-up form, the review of applications and every email a mentor gets on the way in move from Airtable to wordpresseducation.org. Design of 16 October 2026, for the WPCredits Program Manager plugin (main at 1.122.29). This is the next step of the Airtable exit the product owner named on 14 October 2026: the mentor welcome emails move to the site, and so does the sign-up form.

## 1. Settled by the product owner

1. **The application lives on the site.** The sign-up form is a page of wordpresseducation.org; an application lands in a review queue on the Mentors screen in wp-admin, and the decision is made there, not in Airtable (16 October 2026).
2. **Every email a mentor gets on the way in comes from the site** and is editable in Tools > Emails: the acknowledgement, the selection email with the course link, the request for more information, the decline, and the invitation to the account (16 October 2026).
3. **One email on becoming a mentor.** The welcome text Airtable sends on Status Active is folded into the site's "Invitation: mentor" email, the mentor coordinator (the program manager who reviews mentor applications; the role is named that way throughout) reviews the wording on the site before it goes live, then the Airtable automation is switched off, so a new mentor gets one email instead of two (the product owner and the mentor coordinator on WordPress/WPCredits#253, 15 October 2026).
4. **Airtable keeps a row per active mentor for now**, because the mentor coordinator assigns students to mentors in the Students table and the syncs read that link. The site writes the row when a mentor becomes active. That write is the only temporary piece of this design; it goes when the students leave Airtable, and everything else built here is the mentor module of the site that has left Airtable (16 October 2026).
5. **Nothing built here is shaped for Airtable.** New data is a site field, never an Airtable column; the Airtable side is a write the site makes, as it fills columns elsewhere (the rule of the sponsors' move, 23 September 2026).
6. **The site publishes a feed of application counts**, by month and state, with no personal data, and the Credits Monthly Report reads it for its mentor figures from launch month on, as both plugins read the sponsors feed since the sponsors' move; the report's release lands before the Airtable form closes (the product owner, 16 October 2026, when asked whether to publish a feed or change the report's definitions).
7. The owner's standing rules apply: no em or en dash anywhere, US English, full product names, every test send to the one address, a version bump and a changelog line per release, the guide pages republished when they change.

## 2. What the code and the base say today

### 2.1 Five emails from one Status column

The Mentors table in Airtable drives five deployed automations, all on its Status single select (Interested, Vetted - positive, Needs more information, Active, Declined, Inactive, Needs second opinion, Dropped out):

| Airtable automation | Fires when | Says |
| --- | --- | --- |
| Mentors 1 - Application Received | the form "WordPress Credits - Call for Mentors" is submitted | thank you, it may take several weeks, we will write |
| Mentors 2 - Course | Status becomes Vetted - positive | selected; commit two hours a week; join the mentors' course through its invitation link; write to us when done |
| Mentors 3 - Welcome | Status becomes Active | congratulations; the site, the invitation that arrives today, the lost-password route; the four student courses with their group invitation links; the private Slack channel; the discount codes; the resource folder |
| Mentors 4 - Decline | Status becomes Declined | the profile did not confirm eligibility; other ways to contribute; reply to appeal |
| Mentors 5 - Need more info | Status becomes Needs more information | tell us more about your involvement with WordPress |

Every one goes out as "WordPress Credits Project" with the program's address as reply-to. The Welcome carries the four course group codes and the Drive folder link, so its content can only ever live in the site's template, typed in on the site, never in the repository or a document.

### 2.2 What the site does today

- **The mentors sync** reads the Mentors table every three hours for rows whose Status equals the "Mentor status to sync" setting (Active), creates or links the WordPress account (username from the WordPress.org profile, the record id, the profile and the active flag as user meta, the Mentor role added, the sponsorship row), and queues the "Invitation: mentor" email when invitations are on. A mentor whose row leaves Active is revoked on the next run (the active flag cleared, the mentees list removed, the role taken away when the setting says so). So today a newly active mentor gets Airtable's Welcome at once and the site's invitation within three hours.
- **The Mentor Status Checker** (a module in Tools) runs daily: it reads the rows whose Status is the checker's source status (Vetted - positive), looks for the mentors' course on each WordPress.org profile through the profile timeline, and writes the target status (Active) on the rows where the course shows, then posts its summary to the owner's Slack webhook. Its settings name the course slug, the completion phrase, the timeline filter, the page and batch limits.
- **Two public forms** already exist, the institution application and the sponsor application: a shortcode and a block on a page the plugin ensures, the shared `WPCPM_Form_Guard` (honeypot, dwell token, per-actor ceiling, site-wide degrade, consent evidence, requiredness, content scoring that holds rather than refuses, duplicate flags that never merge), a row per application in a post type, an acknowledgement carrying a link that confirms the address, a review queue on the module's screen, decisions by a manager, and retention that deletes finished rows after a number of days set in Settings.
- **The Emails tool** holds every email in a catalog by id, module and audience, with an editable template (trigger, subject, body, placeholders), a log of every send, and a test send to the one address. A module sends through `WPCPM_Mail::send()` with a build callback; the recording suite proves every email through the real senders.

### 2.3 The Mentors table's applicant fields

Full Name, WordPress profile, Email, Country (linked), Languages (linked), Contribution Area - Expertise (a multiple select of the contribution teams), Available hours per week, Sponsored (Yes/No), Sponsor company, Wants to be in the looking for sponsors list (Yes/No), Teacher from a participating institution? (Yes/No), GPL & Trademark (a checkbox whose description is the statement), Anything else you'd like us to know?, Privacy Policy Compliance. The form itself, read on 16 October 2026 while M1 was planned, asks eleven of these, in this order: Full Name, Email, the WordPress.org profile URL, Country, the teacher question, Languages, the contribution areas (optional, the one optional answer besides the free text), the hours (a decimal), the compliance declaration with its two links (the license and the trademark policy), the free text, and the privacy consent. It does not ask the three sponsorship questions (Sponsored, Sponsor company, the looking-for-sponsors list): those columns are the mentor coordinator's to fill later. The mentor coordinator confirms the eleven before the form is switched on (section 13, item 1).

## 3. The architecture, in eleven decisions

1. **One application record on the site**, a post type `wpcpm_ment_app` (fourteen characters, the limit `register_post_type()` enforces), one row per submission, with the applicant's answers as meta, the guard's evidence, the duplicate flags, the notes, and the decision history. It is the sponsor application's shape with the mentor's questions.
2. **The states** are `new`, `held`, `spam`, `info` (more information asked for), `second` (second opinion wanted, internal), `selected` (the course is pending), `active` (the account exists), `declined` and `lapsed` (selected, and the course never came). `held` and `spam` are the guard's, `lapsed` is the clock's; the rest are a person's.
3. **The queue is a tab on the Mentors screen**, "Waiting for review", first when it holds rows, the way the Institutions and Sponsors screens do it. It lists `new`, `held`, `info` and `second` rows with the answers, the profile link, the flags and the notes, and offers the decisions. The mentor coordinator decides there; the owner and the other Administrators can.
4. **The decisions are the Status choices the mentor coordinator uses today**, under their own names: Select (to `selected`, the selection email goes), Ask for more information (to `info`, the request goes; the row stays in the queue marked with the date, and a later decision closes it), Second opinion (to `second`, no email, a note for a colleague), Decline (to `declined`, the decline email goes), Mark as spam. A decision that sends an email needs the address confirmed through the acknowledgement's link, as the institution approval does.
5. **The course step reads the site.** The Mentor Status Checker's source becomes the `selected` applications instead of Airtable's Vetted - positive rows; the profile check is unchanged. Where the course shows, the application becomes `active` through the activation step. A manager can also press "The course is completed" on a `selected` row, for the applicant who wrote in before the profile caught up; the press asks first.
6. **Activation is one shared step**, used by the checker and by the manual press: create or link the WordPress account exactly as the mentors sync does (the same username rule, meta and role), write the Mentors row in Airtable (decision 7), store the new record id on the account, and queue the "Invitation: mentor" email. The mentors sync's provisioning code moves into this shared step so there is one way to make a person a mentor.
7. **The write to Airtable is one row on activation**: Full Name, WordPress profile, Email, Status Active, and the applicant's answers whose columns exist (country and languages as links where the names match, the expertise, the hours, the teacher answer, the free text; the three sponsorship columns stay empty, since the form does not ask them). After that the row is Airtable's, as it is today: the mentor coordinator assigns students there, the syncs read it, and a mentor leaving Active there is revoked here on the next run. The site never deletes a row and updates one only for a returning mentor (the author's call after the builder check): an applicant whose account already holds a mentor record id, a former mentor whose row is Inactive or Dropped out, is activated on that account and that row, by setting the row's Status back to Active, the one cell the site ever changes, so the person keeps one row with their feedback, lessons and past students rather than gaining a second. The next mentors sync finds the account by the record id and changes nothing.
8. **Five emails on the site, four of them new.** `mentor-application-received` (on submission, with the confirming link), `mentor-selected` (the course email), `mentor-more-information`, `mentor-declined`, and the existing `invite-mentor`, which gains the welcome paragraphs. A second opinion sends nothing: the colleague is told on the screen. All under a new catalog group, "Mentor applications". The four to the applicant are typed as the applicant's, since the person has no account until M2 activates them (the author's call at planning, 16 October 2026: a test or a preview then names no dashboard in its footer, and the Emails log lists them with the other two forms' applicants); M2 decides whether the invitation, the first email to the account, is the mentor's. Their default text is Airtable's wording as it stands on the day, minus anything that cannot live in the repository.
9. **Administrators hear of an application the way they hear of the other two** (the author's call after the builder check): every `new` row emails the Administrators, or the list a setting names as the sponsor form's does, through a sixth template, `mentor-application-notice`; the Administrator Dashboard gets a "Mentor applications waiting" tile beside the two it has; the Waiting for review tab is the place to act, not the only signal.
10. **A public feed of counts, never of people** (the owner's decision 6): a REST route beside the sponsors feed answering, per calendar month since launch, how many applications were received, selected, activated, declined and lapsed, and how many are open and how many selected and waiting today; no names, addresses, handles or free text, ever, and the fixtures walk checks the feed's suite. The Credits Monthly Report counts new mentor registrations and vetted mentors from it, and the WPCredits Tracker's sync reads it where it read the Status column.
11. **The Airtable form and the five automations are switched off by a person on launch day**, after the mentor coordinator has read the five templates on the site: the form view is closed and every public link to it points at the site's page; Mentors 1, 2, 4 and 5 are switched off when the form moves; Mentors 3 when the invitation carries the welcome. The site cannot see automations and does nothing about them.

## 4. The form

- **Where:** a page the plugin ensures, slug `become-a-mentor`, holding the block (the shortcode as the fallback), not gated, with the theme's form styling as the sponsor form has it. The page's title and the paragraph above the form are the owner's to edit on the page.
- **Questions:** the eleven questions of section 2.3, in the Airtable form's order and with its labels, as single-line text, email, URL, a select of countries and a multiple select of languages (from the site's own lists, not Airtable's linked tables), the contribution areas as checkboxes from the site's list of contribution teams (optional, as the form has it), a number of hours with a decimal allowed, one Yes/No choice (the teacher question), a required checkbox carrying the GPL and trademark statement word for word with its two links, a free-text box, and the privacy consent sentence with its evidence. The WordPress.org profile is required and normalized the way the sync normalizes it (a handle or any form of the profile address); the form refuses a profile that does not resolve to a handle.
- **Guard:** the sponsor form's order of checks exactly, through `WPCPM_Form_Guard`, with the mentor's own actor key. Duplicates flag and hold: an open application with the same address or the same profile handle; an existing mentor account or an Airtable mentor row with that address or handle (read from the site's own mentor accounts and the last sync's record list, never a live Airtable call on a public request).
- **After submission:** the thank-you page names the reference and says the acknowledgement is on its way with a link to confirm the address; the acknowledgement goes out at once through the queue.

## 5. The queue and the decisions

- **The tab** lists each open row: name, profile (a link, with the handle), address (confirmed or not), country, languages, hours, expertise, the teacher answer, the free text, the flags (duplicate, held, unconfirmed), the notes, the dates of each decision so far, and "asked for more information on" when it applies.
- **Select** moves the row to `selected`, sends `mentor-selected`, and the row leaves the queue for the "Selected, course pending" view of the same tab, where the checker's last result for the row shows (checked on, found or not yet) beside "The course is completed".
- **Ask for more information** sends `mentor-more-information` and keeps the row in the queue, dated; the applicant replies by email to the program's address, the way they do today; a note records what came back.
- **Second opinion** marks the row and keeps it in the queue; no email.
- **Decline** sends `mentor-declined` and closes the row; **Mark as spam** closes it silently; **Withdraw**, on a `selected` row whose applicant wrote that they are not continuing, closes it silently with a note (the author's call after the builder check; Airtable's Dropped out has no email either). A declined applicant who appeals by email is a new application if the mentor coordinator asks them to apply again, or a note and Select on the same row, the mentor coordinator's choice.
- Every decision is a logged press by a signed-in Administrator with a nonce, recorded on the row with who and when; the Emails log holds each email.

## 6. The course step and activation

- The checker's daily run reads `selected` rows, newest first, within its batch and page limits, and checks each profile for the course exactly as today; a row where the course shows is activated (section 3, decision 6); the Slack summary names the activated mentors the way it names promotions today.
- "The course is completed" on a `selected` row activates it by hand after a confirmation; the row records who pressed.
- Activation is idempotent: a row already `active` is left alone and reported; an account already holding a mentor record id is a returning mentor and is activated on that account and that Airtable row (section 3, decision 7).
- A `selected` row the course never reaches lapses (the author's call after the builder check): after the number of days a setting holds, default 90, the clock moves it to `lapsed` with a dated note, it leaves the checker's daily queue, and the view lists it under "Lapsed" with Select offered again if the person turns up later; no email goes. Until then every open `selected` row costs the checker up to its page limit of profile pages a day, which is why the clock exists.
- The invitation carries the welcome. Until the mentor coordinator has pasted the course links and the folder link into the "Invitation: mentor" template on the site, the default template says, in one bracketed line where they go, that they are to be added; the launch checklist (section 11) has the mentor coordinator fill them in and read the result as a test send.

## 7. The write to Airtable

- On activation the site creates one Mentors row through the client's record creation, with the columns of decision 7; a refusal from Airtable (a bad token, a missing column, a network failure) leaves the application `active` with the account made and the invitation queued, records the refusal on the row and in the sync's notices, and offers "Write the Airtable row again" on the row until it succeeds, so a mentor is never kept waiting on Airtable.
- The record id written is stored on the account's existing record id meta, so the mentors sync, the Mentor Report Card and every reader of that meta keep working unchanged.
- The site does not update the row afterwards and does not delete it. A mentor made inactive in Airtable is revoked on the site by the sync, as today; a mentor made inactive on the site is not a feature of this design (section 14).

## 8. Emails

| Id | Audience | When | Default text |
| --- | --- | --- | --- |
| `mentor-application-received` | the applicant | on submission | Airtable's Mentors 1, plus the confirming link line the institution acknowledgement uses |
| `mentor-selected` | the applicant | on Select | Airtable's Mentors 2 (the course invitation link is a placeholder filled from the checker's course setting and a new "mentors' course invitation link" setting, which the mentor coordinator fills in Settings) |
| `mentor-more-information` | the applicant | on Ask for more information | Airtable's Mentors 5 |
| `mentor-declined` | the applicant | on Decline | Airtable's Mentors 4 |
| `invite-mentor` (existing) | the mentor | on activation, and from the Mentors screen as today | the current invitation with the Welcome's paragraphs after it: the lost-password route, the two "Start here" posts, the student courses (the links typed in by the mentor coordinator), the private channel, the discount codes, the resource folder, the sign-off |
| `mentor-application-notice` | the Administrators, or the list a setting names | on every `new` row | the sponsor application's notice wording with the mentor's name, profile and the queue's link |

All are editable in Tools > Emails, logged, test-sendable, and recorded through the real senders in the emails recording suite, as the E4 release did for every email. The reply-to is the program's address, as every outward email has it. The Welcome's content that is secret (course group codes, the folder link) is never in a default template, a fixture, a document or a commit: the recording suite's fixture carries placeholders.

## 9. Privacy and retention

- An application holds a person's name, address, profile, country, languages and free text: it is personal data and is treated as the sponsor application's rows are. Retention per state in Settings with the sponsor application's defaults: spam rows deleted after their days, declined and lapsed rows after theirs, `info` and `second` rows kept while open, `active` rows kept as the record of how the mentor came in (their answers feed the account's profile where a field exists).
- The duplicate check reads the site's mentor accounts of every standing, current and former, which is what recognizes a returning mentor; a person declined in Airtable before launch, who never had an account, is not recognized and may apply again, as the decline email itself invites (the author's call after the builder check).
- The duplicate check on a public request reads only the site's own data.
- The fixtures walk refuses any real address, handle or record id in the suites, as it does for every module; the fixtures use example.test addresses and spelled-out ids.

## 10. Tests

- The form suite: every guard check in order, each field's cleaning and requiredness, the profile normalizer's forms, consent evidence, duplicates flagged and held, the thank-you page, the acknowledgement's link.
- The queue suite: each decision from each state, the emails each sends and does not send, the refusal on an unconfirmed address, the notes and dates, the capability and nonce before anything.
- The checker suite: the site as source, the activation of a row where the course shows, the row left alone where it does not, the manual press, idempotence.
- The activation suite: the account made or linked exactly as the sync makes it (the same checks the sync's suite runs, against the shared step), the record id stored, the invitation queued once, the Airtable write's payload through the stand-in client, a refused write leaving the account made and the retry offered.
- The emails recording suite: the four new applicant emails, the Administrators' notice and the changed invitation, through the real senders, with the welcome's placeholders.
- The feed suite: the counts per month and state against planted rows, the empty months, the day's open and waiting counts, and that no field of a person reaches the answer.
- The handbook, references, spelling, dead-annotation, temp-litter and standards checks as every release.

## 11. Launch checklist (a person's steps, in order)

1. The mentor coordinator reads the five templates in Tools > Emails, edits the wording, pastes the course links and the folder link into the invitation, and sends each to the test address.
2. The owner fills the "mentors' course invitation link" setting.
3. The form page is reviewed on the site; the public links to the Airtable form (the website, the handbook, the GitHub repository's pages) are changed to the site's page.
3a. The Credits Monthly Report's release that reads the feed is live, and its methodology note says where the mentor figures come from since launch month.
4. The Airtable form view "WordPress Credits - Call for Mentors" is closed; Mentors 1, 2, 4 and 5 are switched off.
5. Mentors 3 - Welcome is switched off.
6. Applications already in Airtable on launch day are finished in Airtable by hand (the mentor coordinator's choice per row), with their emails from the site's templates sent by hand if needed; nothing migrates.

## 12. Phases

| Phase | What ships | In Airtable |
| --- | --- | --- |
| **M1** | The form page, the application record and its guard, the Waiting for review tab with every decision, the four new applicant emails and the Administrators' notice with the acknowledgement's confirming link, the dashboard tile, retention, the lapse clock and the counts feed. Select sends the course email and parks the row; nothing activates yet. The mentor coordinator tries the form and the queue on the copy of the site, then live with the Airtable form still open. | Nothing |
| **M2** | The checker reads the site, the shared activation step, the invitation with the welcome, the write of the Mentors row, the retry, the Slack summary. Launch: the checklist of section 11. | One row per newly active mentor |

Each phase is planned on its own in `docs/plans/`, bumps the version in every place, gets a changelog entry and a zip, and goes to the mirror; the deploy is on the owner's yes through the release script.

## 13. Open items

1. **Which fields the Airtable form shows, and in which order**: read from the form on 16 October 2026 (section 2.3 lists the eleven in order); the form on the site asks the same questions in the same order, no more; the mentor coordinator's confirmation of the eleven is still open, before the form is switched on.
2. **The thank-you wording and the page's introduction**: the owner's, on the page.
3. **Whether the mentor coordinator wants the applicant told when a second opinion is sought**: the design says no email; one line from the mentor coordinator settles it.
4. **The checker's Slack summary wording for activations from the site**: the owner's webhook message names promotions today; the wording for "activated from an application" is the owner's.
5. **Who counts mentor sign-ups once they stop landing in Airtable: settled 16 October 2026, the feed** (section 1, decision 6, and section 3, decision 10). The Credits Monthly Report's release that reads it is planned with M1 and is a release of that plugin, not of this one.

## 14. Deliberately not in scope

- Moving mentors' student assignments off Airtable: the mentor coordinator keeps assigning in the Students table, and the syncs keep reading it. That is the students' move.
- Making a mentor inactive from the site, or editing a mentor's Airtable row from the site.
- The student welcome emails (the five track automations): the next step after this one, with the same Emails tool shape.
- A mentor's own profile page or self-service edits of their application.
- Automations: the site cannot see them; a person switches them off.
- Translating the templates.
