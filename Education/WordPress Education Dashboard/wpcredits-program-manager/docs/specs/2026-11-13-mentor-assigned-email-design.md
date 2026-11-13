# The site tells a mentor about a new student (design, 13 November 2026)

Plugin: WPCredits Program Manager. Live when this was written: 1.123.88.
Approved by the product owner on 13 November 2026, with two choices made: the switch is on at
release, and the former mentor is not told when a student is moved away.

## 1. Why

The welcome a new mentor receives says: "As soon as the first students are assigned to you, you
will receive the introduction email connecting you with them." Today that email is not the site's.
A mentor learns of a new student only by being copied on the student's welcome email, which an
Airtable automation sends (one per track). When the program leaves Airtable that copy goes, and
the promise would be broken. The site should do this itself.

## 2. What it does

1. When the mentors sync writes the mentors' lists, it finds each **current** student who is on a
   mentor's list and whom that mentor has not been told about.
2. Each such mentor gets **one email per sync**, "Student assigned, to the mentor", listing every
   new student: name, track, institution, start date and email address, then the link to the
   Mentor Report Card and the next step.
3. **Nobody is emailed about students already on a list** when the feature first runs. The first
   run only remembers who is on every list.
4. A student moved over from another mentor counts as assigned for the new mentor. The former
   mentor is not told.
5. A switch on the Settings screen, **on** from this release. While it is off the site keeps
   remembering who is on each list, so switching it on later sends no backlog.
6. The email's text is editable on the Emails screen and it is in the mail log like any other.

## 3. How it remembers

- One stored option, `wpcpm_mentees_told`: for each mentor's Airtable record ID, the Students
  Reports record IDs that mentor has been told about (or that were there at the first run). It
  only grows. It is **not** the mentor's last list: a list that shrinks and comes back (a short
  read from Airtable, a mentor made inactive and active again, a mentor record moved to another
  account) announces nobody twice.
- The first run takes in this run's rows and, beside them, the list every account held before the
  sync touched it, so a mentor the first read happened to miss (not Active that day, or a short
  read) is still remembered. The sync reads those lists at the start of its revoke step, before it
  deletes or replaces any, while no set of this version exists; it keeps them in the run's state
  (record IDs only) and hands them to the first run, which also takes whatever the accounts still
  hold. A run already in flight at the deploy has none in its state and seeds as before. If there is nothing
  to take in at all, nothing is written and the next run with data does the remembering. The
  sync's report says on that run: "The site now remembers who is on every mentor's list. Nobody
  was emailed about students already there."
- An ID is remembered only after its email was accepted for sending, and the option is written
  after each email, so a run that dies midway repeats at most one email. If a write is refused,
  the sending stops there and the rest wait for the next sync.
- The same option keeps a second list, `failed`: the mentors whose email was refused and who have
  not been emailed since. It is written once at the end of a run, only when it changed; a mentor
  emailed, or with nobody new, drops out. A set stored without it reads as an empty list.
- It also keeps `run`: the start of the last sync that emailed and how many emails were accepted
  in it, so a sync whose last step runs again counts the emails it already sent. A set stored
  without it reads as no run.
- A stored set that is damaged is taken in again silently, never read as "everything is new". A
  set in which no mentor's entry holds a usable ID (empty, blank mentor keys, or damaged or empty
  entries only) is seeded again, since the first run never writes one. A mentor's entry that is not a list, or a list with no usable ID in it, is damaged: that
  mentor's list as the run reads it is remembered and nobody is emailed. An empty list is not
  damage: it is a known mentor with no students, whose first student is announced.
- A new enrollment (a second track) is a new Students Reports row and is announced. A student who
  was past on the list and is current again is not (same row). A student who comes back to a
  former mentor is not announced again.

## 4. Who is left out

- A past student (remembered, never announced).
- A student of a test institution (remembered, never announced).
- A mentor with no account yet: nothing is sent or remembered, so they are told once they have one.
- An Administrator who is also a mentor is told like any mentor.

## 5. Two guards

- **A ceiling:** at most 25 mentors are emailed in one sync. The rest are not remembered, so the
  next sync tells them, and the sync's report says how many wait. Mentors whose email was refused
  go last at the next sync, so mentors the mailer keeps refusing cannot hold the others back; among
  themselves they take turns (those not tried at the last sync first), so one whose email works
  again is reached even when more are refused than one sync tries. The
  ceiling holds across a sync whose last step runs again: the emails it already sent count.
- A set that cannot be saved is said in the sync's report, and the next sync tries again. That holds
  for every write that keeps a student: the first run's, the switch-off run's, the one after an
  email, and the run's last one when it holds rows remembered silently (past students, test
  institutions). Only a refused write of the refused mentors' order alone is not said.
- The report's count is every email accepted under the sync, so a last step that runs again after
  a fatal still counts the ones its earlier call sent, also when the switch was turned off in between.
- **A brake:** if one scheduled run would tell more than half of the mentors who have students,
  and at least 20 mentors, something is wrong with the data rather than with the week (for example
  every record ID changed). Nobody is emailed and nothing is remembered; the report says so and
  names both ways out:
  - the assignments are real: an Administrator starts a mentors sync by hand. Each sync started
    by hand passes the brake and emails up to 25 mentors (the ceiling still applies), so the
    Administrator starts another until none wait; a scheduled sync in between holds again while
    more than half of the mentors who have students, and at least 20, still wait;
  - they are not: switch the setting off, run a sync and switch it on again. With the setting off
    the site takes every student in without a word, so nobody is emailed about them.
- The notices, word for word:
  - waiting: `%d more mentor has students assigned and is emailed at the next sync.` /
    `%d more mentors have students assigned and are emailed at the next sync.`
  - refused: `%d mentor could not be emailed about students assigned to them; the next sync tries again.` /
    `%d mentors could not be emailed about students assigned to them; the next sync tries again.`
  - not saved: `The site could not save its record of the students mentors have been emailed about, so the next sync tries again. Leave "Students assigned to a mentor" as it is until a sync ends without this line.`
  - the brake: `%d mentors would have been emailed about students assigned to them at once, more than half of the mentors who have students, so nobody was emailed. If the assignments are real, start a mentors sync by hand: each sync started by hand emails up to 25 mentors, so start another until none wait. If they are not, switch "Students assigned to a mentor" off (Settings, Students and mentors, Accounts), run a sync and switch it on again: nobody is emailed about them.`
- The sync's report row: `Mentors emailed about assigned students`.
- A run counts as scheduled only when the plugin's own scheduled hook started it; this does not
  depend on how the host runs its cron.

## 6. The email

- ID `student-assigned-mentor`, in the Emails screen's **Invitations** area, after the mentor's
  welcome, to the mentor, in the mentor's language. No Reply-To of its own.
- Subject: `[{site_name}] {count} assigned to you`, where `{count}` reads "1 student" or
  "3 students". No student's name is in the subject, because the mail log keeps subjects.
- Body:

  ```
  {count} assigned to you:

  {students_list}

  The next step is a first call. Write to each student to introduce yourself and agree a time, or wait for their message.

  Their details, your notes and your calendar are on your Mentor Report Card:
  {dashboard_link}

  Thank you for mentoring.
  ```

- One line per student: `Name, track, institution, starts 2 December 2026: address`. A start date
  before today (the site's date), as for a student moved over mid-semester, reads
  `started 2 September 2026`; today or later keeps `starts`. A missing institution or start date
  leaves its part out. Line breaks inside a value are collapsed.

## 7. The switch

Settings, Students and mentors tab, Accounts section, after the invitation emails row.
- Row label: `Students assigned to a mentor` (not "New students": the same tab's section **New
  students** is the first-week checklist, where new means new to the program)
- Checkbox: `Email a mentor when a student is assigned to them`
- Help: `One email per mentor per sync lists every student just assigned to them, and nobody is emailed about students already on their list.`
- Details: `A student moved over from another mentor counts as assigned, and the former mentor is not told. Past students and students of a test institution are left out. Until the welcome emails Airtable sends are switched off, the mentor is also copied on the student's welcome email. The text is "Student assigned, to the mentor" on the Emails screen.`
- Key `mentor_assigned_email`, default on.

## 8. Builder's calls

1. The Invitations area, not a new area: it sits beside the welcome that promises it.
2. No per-student link in the list: one link to the Mentor Report Card under it.
3. The next step does not claim the student was told to write first; it is true whatever the
   student's welcome says.
4. A send that fails is tried again at every sync, with no time limit.
5. The ceiling is 25 and the brake is "more than half, and at least 20"; both are constants.

## 9. Not in this release

- Telling the former mentor.
- Moving the students' welcome emails from Airtable to the site (the Students move).
- Changing the mentor welcome's sentence: it stays, and is now kept.

## 10. What must stay true

- The first run after the release emails nobody.
- A mentor is never told twice about the same enrollment.
- No student's name or address in a subject or in a sync notice.
- When the program's records leave Airtable, the remembered IDs must be carried over to the new
  IDs in the same step, or the setting switched off for the first sync after it; otherwise the
  brake holds every email (which is the safe side).
