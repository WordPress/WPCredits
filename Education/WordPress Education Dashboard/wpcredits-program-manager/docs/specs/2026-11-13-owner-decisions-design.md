# Five places where the plugin and its words disagreed (design, 13 November 2026)

Plugin: WPCredits Program Manager. Live when this was written: 1.123.88.
Source: the side findings of the Administrator guide rebuild (1.123.86 and 1.123.88). The product
owner answered each on 13 November 2026; those answers are the decisions below. The facts behind
each (where the behavior lives, the tests that pin it, the guide's sentences about it) were read
from the code at 1.123.88 before this was written.

## 1. Why

In five places an Administrator was told one thing and the site did another, or was left with no
way forward. Each one costs a question to somebody who runs the program. This release makes the
behavior and the words agree.

## 2. The decisions

### 2.1 A graduate keeps the Student Report Card

- Today, with "When a student leaves the program" at its default, the students sync takes the
  Student role from everyone at a status under Past students, and the Student Report Card's page
  then refuses them.
- Decision: a graduate keeps the role, and so the card, whatever that setting says. The card works
  for a graduate as it did the day before: the report form included. Booking already tells a past
  student that calls can no longer be booked.
- Who is a graduate: a status under Past students whose name reads as graduated, completed,
  finished or passed and does not say the opposite, such as Not completed. The plugin already has this test for the feedback forms; it moves to one
  shared place. With the default statuses that is "Graduate" and not "Dropped out".
- A student at any other past status, at a status the site does not track, or whose row is gone,
  loses the role as today when the setting says so.
- The rule is for students who graduate from now on (the product owner, 13 November 2026: "this applies only to new graduates"). The sync stops taking the role; it gives nothing back to a graduate who had already lost it, and nobody is invited.
- The setting's help says so in one sentence: "A graduate keeps the role and the Student Report
  Card either way, the account is never deleted, and program details are kept."
- Unchanged: a graduate stays out of the count check and of everything that reads "active"
  (quiet-student flags, sponsor tools); an Administrator's account is never touched; a past row
  never creates an account.

### 2.2 A student moved to another mentor

- Today the students sync takes a moved student off the old mentor's group sessions. One-to-one
  calls with the old mentor stay booked, and count against the student's booking limit with the
  new mentor.
- Decision: once the new pairing has settled (the student's card names the new mentor and that
  mentor's list holds the student), the sync cancels the student's upcoming one-to-one calls with
  a mentor whose list no longer holds them, and emails the old mentor and the student the "Call
  canceled" email, each with the calendar file that takes the call out of their calendar.
- A call that has already started is left alone, as a session under way is.
- The email says who canceled truthfully: "the site, because this student now has another mentor"
  to the mentor, "the site, because you now have another mentor" to the student. Its closing
  sentence is reworded so it is true for every cancellation (builder's call, see 3).
- The call is put in the trash before anybody is written to, so a run that is retried mails nobody
  twice.

### 2.3 A school with no stage is left alone

- Today a school whose Current Stage is empty counts as having left the pipeline: each
  institutions sync deletes its agreement record and, at the default, detaches its people.
- Decision: an empty stage is not a stage outside the list. The sync leaves such a school and its
  people alone until somebody gives it a stage. It is still listed under "No stage" in the
  Pipeline. A school that is missing from Airtable altogether is treated as before.
- People taken off such a school at earlier syncs are not put back: from the first sync after the
  release its agreement counts as recorded again, for whoever is still attached.

### 2.4 "When an institution leaves the pipeline": the words

- Today the second choice reads "Leave their access in place", but either choice closes the
  school to everything except its agreement.
- Decision: the words say what happens. No behavior changes here; what should happen is decided
  with the Institutions move off Airtable.
  - Second choice: "Leave its people in place, able to open only its agreement".
  - Help (one sentence, as every row's help on that screen): "Either way its agreement stops
    counting as recorded, and people left in place can open only the agreement until it is back
    at an active stage."
  - Details: "An institution leaves the pipeline when Airtable moves it to a stage outside the
    ones the program treats as active, which the Advanced tab lists. An institution with no stage
    at all has not left: the sync leaves it and its people alone. The accounts themselves are
    never deleted, and the agreement and the roster are kept."
  - Advanced tab, Institution pipeline stages, help: "An institution at any of these stages is in
    the pipeline, and one moved to a stage outside the list has left it, so the list cannot be
    left empty." Its Details: "An institution with no stage at all is left alone: the sync does
    not close its agreement or take its people off it."

### 2.5 Reinstate on the Institutions screen

- Today Reinstate for a school's revoked Collaboration Agreement is drawn only on the
  Administrator Dashboard's Collaboration Agreements card, and that card shows only while a signed
  agreement waits for review. At other times no screen offers it.
- Decision: the Institutions screen's Agreements tab gets a fifth card, "Revoked agreements", after
  "Agreements in force". It lists every institution with a latest revoked agreement and no
  accepted and no submitted one (so also one that has since generated a new template or had a
  newer submission returned), with the date, the Administrator's note and a Reinstate button on each
  row. The button posts to the existing handler and comes back to this tab. The owner chose this name over "Out of force", which does not say
  what it lists.
- The button asks first, because reinstating emails every member of the school: "Reinstate the
  agreement with %s? It is back in force at once, and everybody at the institution is emailed."
- A school at a stage outside the active ones is listed without the button: reinstated, the next
  institutions sync would close it again. In its place one line: "At the stage "%s", which is
  outside the pipeline. Move it to an active stage in Airtable first: reinstated now, the next
  institutions sync would close it again." A school with no stage keeps the button. The test is
  the institutions sync's own, so the two cannot disagree.
- The dashboard's card is unchanged, and so is the handler.

## 3. Builder's calls (not asked; each is cheap to reverse)

1. **The cancellation email keeps its two templates.** The owner asked for the existing "Call
   canceled" email. Its closing sentences ("your student can book another time whenever suits
   them", "so you can book another time") are false for a student who was moved, so they become
   "The slot is free again and open for booking." (mentor) and "You can book another time on your
   Student Report Card." (student), which are true for every cancellation. No new template.
2. **A call under way is not canceled.** The decision says "future".
3. **A school with no stage gets no semester report draft**, as today: the decision covers the
   lock and the people only.
4. **A school that left and then has its stage emptied reopens** at the next sync, for whoever is
   still attached. The guide says so.
5. **Reinstate on the tab asks first**; the dashboard's does not. Making the two agree is a
   candidate for the clean-up release.
6. **A school with a newer agreement waiting for review is not listed** under Revoked agreements: its
   state is "submitted", and the review is the next step.

## 4. Not in this release

- The email to a mentor when a student is assigned (its own design).
- An Events card that is always there (waits for the sponsorship launch).
- What "leaves the pipeline" should do (the Institutions move, release 2).

## 5. What must stay true

- The sync gives no role back to anybody and sends no email for any of this.
- The count check's two numbers do not move.
- Nothing links to an Airtable form. "Administrators" is the one name for the role.
- The Administrator guide says what the screens do, in the same release.
