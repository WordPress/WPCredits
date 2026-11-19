# AI Mentor R2: a student's question to their mentor, and the first write abilities (design, 19 November 2026)

Plugin: WPCredits Program Manager. Version: the next free number when the plan is written (live runs 1.123.101;
1.123.102 is held by the small fixes release, so 1.123.103 unless that changes). Release R2 of the AI Mentor's
WordPress-native build plan of 8 November 2026, built in-house. Nothing deploys without the product owner's word.

## 1. Why

A program Administrator tried DeepTutor as a new student (WordPress/WPCredits#301, 16 November 2026). Two things asked
for there are
still missing after 1.123.101: example questions a student can try, and being sent to a person when the tool cannot
answer. The product owner answered on #301 (19 November): the AI Mentor will "pass what it can't answer to the
student's mentor". Today a student whose question the guides do not answer reads "Ask your mentor, or in the
program's Slack channel." with no link and no way to reach the mentor from there, and the site has no way for a
student to write to their mentor at all.

R2 closes that gap. When Need help? cannot help, a student sends the question to their mentor with one press: the
mentor gets an email they can answer by replying, and the question waits under that student on the Mentor Report
Card until the mentor marks it answered. Every role sees three example questions in the Need help? box. Underneath,
R2 adds the first two write abilities the AI Mentor's tutor (R3) will call, `wpcpm/post-student-message` and
`wpcpm/add-mentor-note`, both acting only for the signed-in student and answering with record IDs, never names.

## 2. The product owner's decisions

Of 25 October and 8 November 2026, never to re-ask:

1. **Record IDs, never names.** What an ability answers names people by their record IDs alone.
2. **Site only.** Student context never leaves wordpresseducation.org. R2 sends nothing new to Google: the student's
   question goes to their mentor by email, from the site.
3. **The product is the AI Mentor; the tool stays "Need help?"** No screen a mentor or a student sees says "AI Mentor"
   until mentors are told (the communications order of 30 September 2026). R2's words say "Need help?".

Of 19 November 2026 (relayed by the WP Tutor session: "Start R2, keep in mind DeepTutor won't be used [...] Proceed
with the inhouse build"), then answered in this session:

4. **In-house only.** DeepTutor is shut down and plays no part.
5. **The message goes from the student to their mentor** ("Student's question to mentor"). When Need help? cannot
   answer, the student sends the question to their mentor: an email, and the question under that student on the
   Mentor Report Card. A student with no mentor is sent to the Slack channel.
6. **Only for the signed-in student** ("Only for the signed-in student"). Each write ability acts for the student who
   is signed in. Claude's connection lists them, but an Administrator's account cannot post as a student.
7. **Two now, progress later** ("Two now, progress later"). R2 ships the two write abilities, the send-to-mentor
   button and the example questions. `wpcpm/student-progress` ships in the release that brings Learn Link.
8. **Three examples per role, everywhere** ("Three per role, everywhere"), from the trial's test questions, in the
   Need help? box on every dashboard and on the Need help? page.
9. **Off until mentors are told** ("Off until mentors are told"). The switch **Send to mentors** ships off. The Student
   and Mentor guides' sections about sending a question, the questions on the Mentor Report Card and the email are
   held out of this release's guides and published by a small docs release when the product owner switches it on,
   so Need help? never points a student to a control they cannot see.
10. **The email's words: "Approve as written".** Section 4 has them.

## 3. What R2 builds

| Part | What it is | Who sees or calls it |
|---|---|---|
| Student questions | A new private post type, `wpcpm_question`: one row a question sent | The student sends; their mentor and Administrators read |
| `WPCPM_Student_Questions` | The module: send, list, mark answered, the email, the privacy tools, uninstall | Need help?, the Mentor Report Card, the ability |
| Send to my mentor | A control in the Need help? box and on the Need help? page, for a student with a mentor | Students |
| Questions on the Mentor Report Card | Under each student, the questions waiting and the last ones answered, with Mark as answered | The student's mentor, Administrators |
| Need help? notes | `WPCPM_Mentor_Notes` gains a note source: a note the AI Mentor writes is labelled "Need help?" and no count reads it as the mentor's | Mentors, Administrators |
| Two abilities | `wpcpm/post-student-message` and `wpcpm/add-mentor-note`, wrappers over the module and the notes | The signed-in student's own calls (R3); listed by the MCP server |
| Example questions | Three per role in the box and on the page; pressing one asks it | Everyone who may ask |
| A clearer "nothing answers that" | The sentence links the Slack channel and, for a student with a mentor, offers Send to my mentor | Everyone who may ask |

## 4. A student's question to their mentor

**Who may send.** A signed-in account holding the student role, with a Students record ID
(`WPCPM_Mentor_Calls::student_record()`), whose mentor resolves (`WPCPM_Mentor_Calls::mentor_for_student()`), and
whose row in that mentor's list exists and is not past (`is_past` empty): a mentor who resolves from the student's
card but whose list no longer holds the student (the mentors sync empties an inactive mentor's list) is refused too,
so no email goes to a mentor who has left and no question waits where no card shows it. Everybody else is never shown the control and is refused
by the module: an Administrator viewing a student's card, a mentor, an institution, a sponsor, a student with no
mentor, a past student.

**What is sent.** The question as the student typed it in Need help? (at most 400 characters, the box's own
`MAX_LENGTH`), and the sections Need help? showed with it, as links (at most five, each a page of this site or of the
WordPress Credits handbook, the same links the answer listed). Before it goes, the student sees what will be sent and
may add a line of their own, at most 600 characters ("Anything your mentor should know?"). The question is not
rewritten by the model.

**Where it is kept.** One `wpcpm_question` post a question: `post_author` the student, `post_content` the
question, meta for the student's Students record ID, the mentor's user ID and Mentors record ID, the added line, the
links, and the state (`waiting` or `answered`, with when and by whom it was marked). Private, not public, no admin
screen of its own, not in search, no REST endpoint of its own.

**The email.** A new template in **Tools > Emails**, "A student's question", to the mentor's account email, with the
student's address as Reply-To so the mentor answers by replying. It carries the question, the added line, the links,
and a link to the student on the Mentor Report Card. It goes through `WPCPM_Mail_Templates::mail()`, so it is in the
mail log like every other email. The words, approved by the product owner on 19 November 2026 (decision 10):

> Subject: {student_first_name} sent you a question from Need help?
>
> Hi {mentor_first_name},
>
> {student_first_name} asked Need help? a question it could not fully answer:
>
> {question}
>
> {student_note}
>
> Need help? showed them:
> {links}
>
> Reply to this email to answer. Your reply goes to {student_first_name}.
>
> The question is also on your Mentor Report Card: {student_card_url}

`{student_note}` and its blank line drop out when the student added nothing, as the templates' optional
placeholders already do.

**On the Mentor Report Card.** The student's row carries a tag, "Question waiting", while one waits. The open student
shows a short list headed "Questions sent from Need help?": each question with its date, the added line, the links,
and a small **Mark as answered** control, the newest first; answered ones stay listed, greyed, for 30 days. Only the
student's mentor (the account the question was sent to, or any account `WPCPM_Mentor_Notes::user_can_access()` lets
read the student) and Administrators see it. Marking answered is a form post with a nonce, refused to anybody else.
The questions never count as the mentor's notes: "Need a call", the last-note date and the engagement figures read
notes only, and the questions' list carries none of the notes' markup the card's script reads.

**Limits.** At most three questions a student a day (UTC), and the same question (the same words, compared as
`WPCPM_Typed_Text::same_lines()` does) not twice within 24 hours. Past either, the student reads why, and the Slack
channel's link.

**When there is no mentor.** The control is replaced by the Slack channel's link ("Ask in the WordPress Credits
Slack channel", the address the Resources section already uses).

## 5. Need help? notes in the notes list

`WPCPM_Mentor_Notes` gains one meta, `_wpcpm_note_source`: absent means the mentor wrote it, as every note so far;
`need_help` means the AI Mentor did. A `need_help` note:

- is written only by `WPCPM_Mentor_Notes::add_need_help_note( $student_user_id, $text )`, for the student's own
  record, while their mentor resolves and they are not past;
- has `post_author` the student (the account it was written for), the audience `mentor`, and at most 2,000 characters;
- shows in the student's notes list labelled "Need help?", never with an edit or delete control for the mentor;
  Administrators may delete it as they may any note; it is drawn without the date markup the card's triage script
  counts (`assets/js/triage.js` reads every `.wpcpm-note__meta time[datetime]`), or that script skips it, so the
  browser's "Need a call" and last-note date ignore it as the server's counts do;
- is refused while **Send to mentors** is off, past ten a student a day (UTC), and when the same words were written
  for the student in the last 24 hours;
- is left out of `count_notes()` (the card's "Need a call" grouping and the "N notes" line read it) and the notes
  heading's count, by one function they ask (`WPCPM_Mentor_Notes::is_mentors()`); the engagement figures group notes
  by `post_author`, so a note written for the student stays off every mentor's row without a change there.

Nothing on a screen writes one in R2: the ability is its only door, for R3's tutor. The student's export holds their
Need help? notes and their erasure deletes them (section 11).

## 6. The two abilities

Both sit in the existing category, are registered only while the abilities are (`WPCPM_Abilities::enabled()`: the
Claude access switch on and the MCP Adapter at 0.7.1 or later), go through `WPCPM_Abilities::answer()` for the log
and the ceilings, and wrap the module's functions, so Need help?'s control works whatever the switch says.

- **Permission.** Their own callback, not `permitted()`: the signed-in account is a student who may send (section 4).
  An Administrator, Claude's connection included, is refused.
- **`wpcpm/post-student-message`.** Input: `question` (3 to 400 characters), `note` (optional, at most 600),
  `links` (optional, at most five site or handbook addresses; others are dropped). Output: `question_id`,
  `student_record_id`, `mentor_record_id`, `state` (`sent`, `duplicate` or `limit`), `sent_at`. Annotations: not
  readonly, not destructive, not idempotent.
- **`wpcpm/add-mentor-note`.** Input: `text` (3 to 2,000 characters). Output: `note_id`, `student_record_id`,
  `written_at`. The same annotations.
- **Never names.** No output carries a name, an email address or the text written. The log row lists the record IDs
  and the outcome, not the text: `WPCPM_Abilities::answer()` today writes the call's input, cut to 120 characters,
  into the row, so for these two abilities the input is left out of it.
- **Who can reach them.** `show_in_rest` stays false on both, as on every ability, and the MCP address admits only
  the Administrators on its allowed list, whom the permission refuses. In R2 only the site's own code can call them,
  which is R3's tutor.
- **The MCP server lists ten tools:** the seven of Phases A and B, `wpcpm/search-guides`, and these two. The tools'
  descriptions say they act only for the signed-in student.

## 7. Example questions

Three per role, translatable, in the Need help? box on every dashboard (under the hint, before anything is asked) and
on the Need help? page (in place of today's three). Pressing one asks it at once. The role is the box's audience (the
card it is on), as the answers already use it.

| Role | Examples |
|---|---|
| Student | How do I book a call with my mentor? / My status says Pending graduation. Am I still on the program? / Do I need to know how to code? |
| Mentor | How do I set my availability so students can book calls? / What should I talk about in the first check-in with a new student? / How do I check that a student's contributions are genuine? |
| Institution | How do we enroll our students? / When do we get the semester report, and who writes it? / How do we record that a student dropped out? |
| Sponsor | How do I create an offer for students? / What happens when our discount codes run low? / Who approves our posts before they are published? |
| Administrator | How do I approve a mentor application? / How does a student get their certificate? / What is needed to sign up an institution? |

Each was answered with an expected section in the R1 trial; the test set checks they still are.

## 8. When Need help? cannot answer

- **Nothing in the guides answers it** (`found` false, or no passage): the sentence links the Slack channel, and for a
  student who may send, **Send to my mentor** is the answer's main action.
- **A written answer, or the passages without one**: for a student who may send, one small link under the sources,
  "Still stuck? Send this question to your mentor."
- Everybody else keeps the sentence with the channel's link and no control.

## 9. What leaves the site

Nothing new goes to Google. The question goes to one person, the student's mentor, by the site's own email. The
abilities' answers carry record IDs only.

## 10. Settings and migration

One switch on the Need help? screen, **Send to mentors**, off by default (decision 9). While it is off, Send to my
mentor is never shown, both write abilities refuse, the clearer sentence links the Slack channel alone, and the
examples still show. No settings version move beyond adding the key with its default.

## 11. Privacy and uninstall

The questions and the Need help? notes are a student's personal data: one exporter and one eraser, by the student's
account email, beside the mail log's: their questions and their Need help? notes exported, and erased by deleting
those posts. A mentor's own notes are not touched. Uninstall deletes the
post type's rows and its meta, the switch, and the daily counters. The notes' new meta goes with the notes.

## 12. Screens and words

New words a person sees (US English, plain hyphens, to pass through the humanizer with the owner's rules): the
control "Send to my mentor"; the preview's heading "Send this to your mentor" and its field "Anything your mentor
should know?"; the confirmation "Sent. Your mentor gets an email and can reply to you directly."; the limits' two
sentences; "Still stuck? Send this question to your mentor."; the tag "Question waiting"; the heading "Questions sent
from Need help?"; "Mark as answered"; the note label "Need help?"; the switch and its description; the email.

## 13. Open for the product owner

Nothing for this release: decisions 9 and 10 settled the switch and the email. When mentors are told, the product
owner switches **Send to mentors** on and the held guide sections go out in a small docs release (section 16).

## 14. Tests

- **`bin/test-student-questions.php` (new):** who may send and every refusal; the record's fields; the email through
  the stand-in mailer with its Reply-To; the limits; duplicates; marking answered and who may; the list on the card;
  the exporter and eraser; uninstall.
- **`bin/test-mentor-notes.php` (new; the notes have no suite of their own today, only checks in the dashboard,
  screen and group sessions suites):** the source meta; `add_need_help_note()`'s rules; the label; `is_mentors()` and every
  count that must skip a Need help? note.
- **`bin/test-abilities.php`, `bin/test-mcp.php`:** ten names; the two new schemas; the student-only permission; the
  outputs with record IDs and nothing else; the log row without the text; ten tools on the server.
- **`bin/test-handbook.php`:** the examples per role; the control's presence by role, mentor and past; the sentence's
  link; the switch.
- **`bin/test-guide-questions.php`:** the fifteen example questions find an expected section.

## 15. Trial on the Docker copy

On a snapshot with the release's zip, after checking the copy is free (no `snapshots/before-scrub.tgz`, the plugin at
its expected version, no other session's trial in Now.md): the switch on, a test student with a mentor sends a
question from the panel and from the page, the email lands in the copy's swallowed mail log with its Reply-To, the
mentor's test account sees the tag and the question, marks it answered; the limits and the duplicate; a student with
no mentor sees the channel; the two abilities through `wp ability run` as the student and refused as an
Administrator; the MCP address lists ten tools. Google is not needed: a found-false answer is made with the trial's
stand-in. Pictures and the report under `.superpowers/session-backups/<date>/ai-mentor-r2/`.

## 16. Docs

In this release, built with `bin/build-docs.php` and republished by the deploy: the Student guide (Need help?'s
examples), and the Administrator guide (the switch, the email template, the two abilities in the Claude access
reference; the Administrator guide is read by Administrators alone). Held out of this release's guides (decision 9),
kept in the repository where the build does not read them, and published by the small docs release that follows the
switch: the Student guide's Send to my mentor, and the Mentor guide's questions on the Mentor Report Card, the email,
Mark as answered and Need help? notes.

## 17. Goals, non-goals, anti-goals

- **Goals:** a student who is stuck reaches their mentor from where they got stuck; the mentor sees it and can answer
  by replying; every role sees questions worth asking; R3 has its two write abilities.
- **Non-goals:** `wpcpm/student-progress` (with Learn Link); the tutor chat (R3); a reply thread on the site; nudges
  (R6 reuses the student automation of early December, not a second system); messages from the AI Mentor to students.
- **Anti-goals:** a mentor's "Need a call" or engagement figures moved by anything the mentor did not do; any name in
  an ability's answer; a student able to write about anybody but themselves; "AI Mentor" on a screen before mentors
  are told.

## 18. Approaches

- **A. Everything in the notes list.** One list for the mentor, but every reader of notes (Need a call, last-note
  dates, engagement, activation) must learn to skip questions, and a question cannot show it is waiting.
- **B. Questions on their own, AI notes in the list (chosen).** The question has its own record, email and waiting
  state; the notes keep meaning the mentor's own contact; the AI Mentor's notes join the list behind one source rule.
- **C. Email only.** The smallest, but nothing on the card, nothing R3 can read back, no record for the ability to
  answer with.

The author's pick, B, on the product owner's handing over of the how (he chose the recommended answer each time).

## 19. The author's calls (for the product owner to check)

- The question's limits: three a day, no repeat in 24 hours, 400 and 600 characters.
- Answered questions stay listed, greyed, for 30 days.
- A Need help? note is written for the student and counted nowhere; only Administrators delete it.
- The examples are English strings in the translation template, as every other string.
- The Reply-To is the student's address, which the mentor already sees on the Mentor Report Card.
- One switch gates both writes, since both put something in front of a mentor.
- A Need help? note's limits: ten a student a day, no repeat in 24 hours; the student's export and erasure cover
  them (the builder check asked).
- A send is refused when the student's mentor resolves but no longer lists them (the builder check asked).
- The post type is `wpcpm_question`: WordPress caps a type's name at 20 characters (the code map of 19 November).
- The MCP server's description stops saying "Read-only": it names the two writes a signed-in student makes for
  themselves.

## 20. Later

`wpcpm/student-progress` over the Learn Link cache (`WPCPM_Learn_Progress`) once Learn Link lands (the Learn-side endpoint is
still under review on Learn#3732): the MCP server then lists eleven tools. R6's nudges reuse the
student automation's first line (quiet-student flags and reminders, launching in the first week of December).
