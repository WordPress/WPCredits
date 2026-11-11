# A track question on student registration, per institution (design, 11 November 2026)

Plugin: WPCredits Program Manager. Live when this was written: 1.123.71 (main had 1.123.74 merged).
Issue: WordPress/WPCredits#304. Builds on the student registration design of 5 November 2026
(`docs/specs/2026-11-05-student-registration-design.md`) and reverses its decision 1.

## 1. Why

Some institutions offer several tracks at once. Rowan University wants the 150-hour and 50-hour
tracks, the Designer Track and the Developer Track, and a second school wants several too (a
program lead's question in #wpcredits-admins, 10 November 2026). The site's registration form has
no track question, so nobody knows which track each student wants, and the school has to send
each student's track by hand.

The product owner decided on 11 November 2026 to ask per school: each institution lists the tracks
it offers, and the form asks a student for a track only when their institution offers more than
one. The product owner has told the program lead it comes in the next plugin release; until then
schools send each student's track and an Administrator assigns it by hand.

## 2. The product owner's decisions (11 November 2026)

1. **Tracks offered.** Each institution has a list of the tracks it offers.
2. **Administrators set it.** An Administrator sets the list when the institution is onboarded, and
   may change it later. The school sees its list on its Institution Dashboard and cannot change it.
3. **Kept on the site only**, with the institution's record on the site, as "Students study in" is.
   No new column in Airtable's Institutions table.
4. **Asked only where it matters.** The registration form asks for a track only when the student's
   institution offers more than one. A school with one track or none listed sees no change.
5. **Required, with "Not sure yet".** Where it is asked, the question must be answered: one of the
   institution's tracks, or "Not sure yet".
6. **The answer goes to Airtable and the Registrations tab.** It is written to a new Students column,
   **Requested track**, and shown on the registration in the Registrations tab. An Administrator
   still assigns the track. Nothing is assigned automatically: the row's `Status` stays empty, as
   today.
7. **The 5 November design's decision 1 is reversed.** That design said a track question was a
   non-goal. Its decision 1 gets a dated note pointing here.
8. **Nothing links to an Airtable form** (the product owner's rule of 10 November 2026).

## 3. Tracks offered

**What can be picked.** The tracks the site runs: the Track Builder's published tracks, as
`WPCPM_Program::labels()` lists them (status => name), shown by name in that order. The list is
kept by each track's Airtable status, the key that stays when a track's name is edited. (Author's
call: a name can change, the status a student is assigned cannot without the base changing too.)

**Where.** A new detail on the Institution Dashboard's details card
(`WPCPM_Institution_Details_Card`), **Tracks offered**, kept in `WPCPM_Institution_Record` under its
own key. An Administrator viewing the institution sets it there (the "set when onboarded" of
decision 2: an Administrator reaches every institution's dashboard through the switcher). It saves
at once and leaves a history row, as an Administrator's correction of any other detail does
(`WPCPM_Institution_Details::write()`, the same audit). A member of the institution sees the list
in the card's read view, with no control to change it. A request to change it is a conversation
with the program, not a form (non-goal).

The detail is neither of the card's two groups today: `GROUP_NOW` is what the school saves itself,
`GROUP_CHECKED` what the school asks for and an Administrator decides. Tracks offered is a third
kind, written by an Administrator only. How the card draws it (its own small form, or a row of the
Administrator's form) is the builder's, within the card's existing look; the fence is
`WPCPM_Institution_Policy`, asked by the handler, never only by the drawing.

**Empty, one, several.** No track listed and one track listed both mean no question. Two or more
mean the question. A listed track the site no longer runs (unpublished in the Track Builder) is
left out when the form reads the list, and kept in the stored list, so it comes back if the track
is published again; when fewer than two runnable tracks remain, the form asks nothing.

**Read elsewhere (builder's call).** A read-only mention on the Institutions screen in wp-admin
(the institution's row or its panel) is welcome where it costs little; it is not required.

## 4. The question on the form

**Where.** In the "Your institution" group, straight after Department / Area of Study.

Moved on 11 November 2026 after the trial: placed after Educational Institution it left an empty cell beside the institution at desktop width.

**Words** (the site's voice; the builder may tighten them, no sentence starts with "Please"):

- Label: **Which track would you like to join?**
- Help: **Your institution offers more than one track. If you have not decided yet, choose Not sure yet.**
- Choices: the institution's runnable tracks by name, in the Track Builder's order, then
  **Not sure yet**.

**Asked only where it matters.** The server decides. A submission whose institution offers two or
more runnable tracks and that carries no track, or a track that institution does not offer, is
refused at the question, with every other answer kept, as the form refuses any answer. A submission
for any other institution ignores a posted track: nothing is stored for it.

**On the page.** With script, the question appears as soon as an institution offering several
tracks is picked, with only that institution's tracks, and disappears (its answer cleared) when the
pick changes to one that offers fewer than two. The tracks per institution reach the page as data
on the form (only institutions with two or more runnable tracks need an entry), read by the forms
script, in the manner of its `data-wpcpm-shows-for` and `data-wpcpm-needs` hooks. Without script,
the builder's call, under one rule: a student at a single-track school is never shown the question
nor refused for it. (One way: the question is drawn only after a refusal that names it, with that
institution's tracks.)

**A changed list.** The form reads the institution's list when it draws and again when it checks.
A student who loaded the page before an Administrator changed the list and picks a track no longer
offered is refused at the question with the current choices, never written with a stale track.

## 5. Where the answer goes

**Airtable.** A new Students column, **Requested track**, of type single line text. It holds the
track's name as the student saw it, or "Not sure yet". (Author's call: text, so a new track needs no
change in Airtable, and the name is what an Administrator reads.) The column is created before the
release, by the product owner or by Claude through the Airtable connector on the product owner's
yes, as "Student report last edited" was on 9 November 2026. It goes into the registration's column
map beside the other registration columns (`WPCPM_Student_Airtable_Row`), so the existing rule
holds: a column the base does not have is left out of the row and named on the registration, and
the rest is written. A release that reaches live before the column exists therefore loses only the
answer, which stays on the site's registration.

Nothing else in the row changes. A registration at a single-track school, or one with no track
asked, leaves the column empty.

**The Registrations tab.** The answer is one of the registration's answers, shown when a
registration is opened, under the same label as the question. A column in the tab's list is the
builder's call.

**The emails.** Unchanged. (Author's call: the "Registration received" email already says an
Administrator will be in touch with the next steps, the course among them.)

## 6. The guides and the earlier design

In the same release, as the Administrator guide convention asks:

- **Administrator guide:** the Tracks offered detail (where to set it, that it decides the question,
  that unpublished tracks drop out), the question on the form, and the Requested track column.
- **Institution guide:** one sentence on the details card that the tracks offered are set by the
  program, and how to ask for a change (write to the program).
- **The 5 November design:** a dated note under its decision 1 that this design reverses it.

## 7. Goals, non-goals and anti-goals

**Goals:** a school offering several tracks gets each student's choice with the registration; an
Administrator assigning a track sees the request in Airtable; single-track schools see no change.

**Non-goals:** assigning the track or writing `Status`; a seat limit per track; the school editing
its own list; a column in Airtable's Institutions table; a track question on the student interest
form, the institution's own student import or the mentor forms; changing the emails.

**Anti-goals (a failure even if it works):**

- a student at a single-track school sees the question, or is refused for it;
- a student is shown, or written with, a track the institution does not offer or the site no longer
  runs;
- a registration fails because Airtable lacks the new column;
- a school member can change the list, by the card or by a hand-made request;
- any new link to an Airtable form.

## 8. Built on what the plugin has

- `WPCPM_Institution_Record` (the site's own details of an institution) and
  `WPCPM_Institution_Details` / `WPCPM_Institution_Details_Card` (the details card, its handlers,
  `write()` and its history row).
- `WPCPM_Institution_Policy`, the fence for who may change an institution's details.
- `WPCPM_Program::labels()`, the tracks the site runs.
- `WPCPM_Student_Registration_Form` (`fields()`, `clean()`, `add_required()`, the redraw with
  answers kept) and `assets/js/forms.js`.
- `WPCPM_Student_Airtable_Row`, the registration's column map and its leave-out rule.
- `WPCPM_Student_Registrations_Tab`, the registration's answers.

## 9. Tests

Each behavior of sections 3 to 5 has a check that fails first, in the suites that already test
these classes (the registration form's and the institution details' suites), Airtable faked:
Tracks offered saved by an Administrator, refused for a member (by a hand-made post), shown read-only
to a member, and audited; the question absent for an institution with zero or one runnable track,
present with exactly the institution's runnable tracks plus Not sure yet for two or more; required
there; a foreign or stale track refused with the answers kept; a posted track ignored elsewhere; the
Airtable cell written as the track's name or "Not sure yet", and left out and named when the column
is missing; the answer shown on the registration in the tab. A trial on the Docker copy (Airtable
faked, mail captured) comes before the release, and the first real registration on live with a
track question uses the owner's test address, on the owner's yes.

## 10. The release

The next free plugin version when the build starts: read the live version and main first, since
other sessions take versions the same day. The Institution Dashboard is next in line for the
dashboard frame redesign in another session; this change touches only the details card's contents
and the registration form, so either can go first, and the second rebases. After the deploy: the
Requested track column exists, one Administrator sets Rowan University's list, and the program lead
is told it is live (the product owner's promise). The changelog names #304; the issue is closed once
the release is live, with a closing comment the product owner approves first.

## 11. Left to the builder

How the details card draws the Administrator-only detail; the key in `WPCPM_Institution_Record`;
the data attribute's shape for the per-institution tracks; the no-script behavior within section
4's rule; the tab's list column; the exact wording of refusals, in the site's voice.

Two facts from the builder check, for the plan: `WPCPM_Institution_Policy`'s details action allows
both the manager and the member grounds, so the Tracks offered handler checks for the manager ground
itself; and the details card's `choices` type reads its options from the institution application's
questions, while these choices come from the Track Builder, so the detail needs its own type. On the
form, `clean()` works one field at a time and the track check depends on the posted institution, so
it belongs where the institution is already resolved (`clean_all()`).

## 12. Open questions

None.
