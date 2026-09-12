## Modules you run on their own

### Header notices

**WPCredits Program → Modules → Header notices.** One notice per audience - Students, Mentors,
Institutions, Sponsors, Administrators - each in its own editor on one screen, with a single Save
button underneath.

{{image:admin-header-notices|One editor per audience. An empty notice is not shown; there is no separate switch.}}

- **An empty notice is off.** There is no separate switch, because a switch is one more thing to leave
  in the wrong position.
- **Anyone in two audiences sees both notices.** An administrator who also mentors gets the mentor
  notice and the administrator one, mentor first. Audience membership uses the same tests the
  dashboards do, so an administrator matched to an Airtable mentor record counts as a mentor even
  though the sync never gives them the role.
- A notice appears at the top of the content, which in this program's theme is the top of the
  dashboard card - not above the site header.
- Links and simple emphasis survive; scripts and other markup are stripped **on save**, so nothing
  dangerous is stored rather than merely hidden at render time.

### Mentor Status Checker

Promotes mentors from *Vetted - positive* to *Active* in Airtable once their WordPress.org profile
shows the Credits Mentor's Course completion. It reads profiles, matches the badge, and reports what
it would change before it changes anything. It needs the Airtable connection, so it refuses to run
until that is set up.

### Student Duplicate Finder

**WPCredits Program → Modules → Student Duplicate Finder.** Lists every student who has more than
one row in Students, Students Reports or Feedback in Airtable, proposes which rows to delete, and
deletes the ones you tick and confirm.

A second row usually comes from the Airtable automation that creates a student's Students Reports
and Feedback rows. It runs whenever a Students row starts matching its conditions and it never
checks the address, so it runs again for an address that already has rows: a student who did not
move forward applies again, or somebody sets a status back and the automation fires a second time.
Duplicated addresses are what break the update automations, which is the error you see in Airtable.

The finder clears duplicates up; it does not stop them being made. Stopping them at source is a
change to that Airtable automation, written up step by step in
[Stopping duplicate students at the source](https://wordpresseducation.org/duplicate-students-at-the-source/).

#### Delete duplicates here, not by hand in Airtable

This is the part worth reading twice. **The site stores Airtable record IDs**, and deleting the row
one of them names is not something Airtable can warn you about:

- a student's account holds the record ID of their **Students Reports** row and of their
  **Feedback** row, which is how their Student Report Card finds their program;
- a mentor's **call note** and a **booked call** hold the record ID of the student they are about;
- **audit log entries** hold the record IDs of what they describe.

Delete such a row in Airtable and the site is left pointing at nothing. The student's Report Card
stops showing their program, and there is no message anywhere saying why, on either side.

The finder knows every one of those references. It reads them before it proposes anything, it
**refuses to delete a row that anything on the site points at**, and it checks again in the second
before it deletes. That is the whole reason to use it rather than the Airtable interface. If you
have already deleted rows by hand and a student now sees nothing, say so: the site can be pointed at
the surviving row again, but only by someone who knows which row that was.

#### What a scan does

A scan reads the three tables and writes nothing to Airtable, so running one is always safe.

- **It runs by itself every three hours**, offset from the four syncs so the two never collide.
- **Scan now** runs one while you watch, with a progress bar. Press it if you have just changed
  something in Airtable and want the list to catch up.
- **A scan that fails changes nothing.** Its error appears above the list, and the last good list
  stays on screen, so a bad night never leaves you with an empty screen.
- Pressing **Scan now** while a scan is running does nothing except say so. The list cannot be
  selected while a scan is running either, because the rows underneath it are about to change.

#### Working through the list

1. Open the finder and read the tiles: how many students are duplicated, how many are **Ready**, how
   many **need a decision**, and how many rows are duplicated in each table.
2. Start with **Ready**. Press **Select all ready**, or tick students one at a time.
3. Move on to **needs a decision**. Each row that may go has its own checkbox and, beside it, the
   sentence saying why it was held back. Read the sentence and decide. Nothing is ever ticked for
   you here.
4. Press **Review selection**. Nothing has been deleted yet.
5. The confirmation lists exactly what will go, table by table, and lists anything it left out with
   the reason. Read it. This is the page to turn back from.
6. Press **Delete**. Only this button deletes anything.

Take it in small batches the first few times. One confirmation deletes at most 100 rows, and there
is no prize for clearing the list in one afternoon.

#### Why a row is held back

The finder keeps the newest row in each table and proposes the older ones, but only when the older
row has nothing worth keeping on it. When it does, you get the sentence instead of a proposal:

| What the screen says | What it means | What to do |
| --- | --- | --- |
| The site points at it | An account, survey, call note, booked call or audit entry names this row | Delete the other row instead. This row is not selectable at all |
| The site uses an older row for this student, so this newest one may be the copy to delete | The pointers are on an older row, so "newest" is the wrong answer here | Decide which row the student's history really lives on, and keep that one |
| Still at a live status | The older row is at a status the program counts as current | Do not delete it until you know why a live student has two rows |
| Records a graduation | The older row is where this student graduated | Keep it. Graduations are the record of the program working |
| Holds work fields | Grades, reflection posts, hours or the project are filled in on the older row | Keep it, or copy the values across first |
| Holds survey answers | Feedback or semester report consent is on this row | Keep it, or copy the answers across first |
| Has Total hours set | The older row carries an hours total | Keep it, or move the total to the row you keep |
| Has Notes in Airtable | Somebody wrote something on this row by hand | Read the note before deciding |
| The newest row has no institution, mentor, status or Course and this one does | The newer row is emptier than the older one | Fill the gap on the newer row first, then come back |
| Created in the same second | Two rows share a creation time, so "newest" decides nothing | Look at both and pick |
| The newest row is at a status that ended, so this one may be the row to keep | Somebody applied again and did not continue | Usually keep the older, live row |

A student is **Ready** only when every table's older rows are clean deletes. One held row anywhere
moves the whole student to **needs a decision**, which is why that group is the larger one.

#### What happens when you press Delete

- **Every row is read from Airtable again** and every question is asked again. A row that changed
  since the scan, that the site has started pointing at, or that is already gone is left out and
  named on the notice.
- **The last row an address has in a table is never deleted.** Whatever else happens, a student
  cannot be erased from a table entirely.
- **Rows go in order: Feedback, then Students Reports, then Students**, so a child row never
  outlives its parent.
- **A sealed copy of every row is written to the site first.** If the copy cannot be written, the
  row is not deleted.

#### Deleted rows, and the 30-day copy

The **Deleted rows** list under the students records who deleted which row, from which table, and
when. **View copy** shows that row's cells exactly as they were, so you can type them back into
Airtable by hand.

**There is no automatic restore.** Bringing a row back is a manual job, and a restored row gets a
new record ID, so anything that pointed at the old one still needs fixing. After 30 days the cells
are erased and the entry stays without a name or an address, as a permanent record that something
was deleted.

#### Turning deleting on

**Deleting ships switched off.** Until a program manager turns it on under **WPCredits Program →
Settings**, in the Student Duplicate Finder's card, the whole list is read-only: no checkbox can be
ticked and **Review selection** is greyed out. Scanning and reading work either way, so the list is
worth looking at long before anyone decides to delete from it.

Leaving it off between clean-ups is a reasonable habit, not a sign that something is wrong.

#### On the Administrator Dashboard

The **Duplicated students** tile counts the students the last scan listed, and its card links here.
A tile at zero means the last scan found nothing, not that no scan has run: the card says which.

### Need help?

The tool screen for the question box configured under Settings. Its own screen is where the handbook
page lives and where you can see whether a provider is set.
