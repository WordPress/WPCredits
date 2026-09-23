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

Delete such a row in Airtable and the site is left pointing at nothing. For the student, that
means their Student Report Card stops showing their program, and there is no message anywhere
saying why, on either side.

The finder knows every one of those references. It reads them before it proposes anything, it
**refuses to delete a row that anything on the site points at**, and it checks again in the second
before it deletes. That is the whole reason to use it rather than the Airtable interface. If you
have already deleted rows by hand and a student now sees nothing, say so: the site can be pointed at
the surviving row again, but only by someone who knows which row that was.

#### What a scan does

A scan reads the three tables and writes nothing to Airtable, so running one is always safe.

- **It runs by itself every three hours**, offset from the four syncs so no two of them run in the
  same request.
- **Scan now** runs one while you watch, with a progress bar. Press it if you have just changed
  something in Airtable and want the list to catch up.
- **A scan that fails changes nothing**, whether Airtable did not answer or the site could not read
  which of its own records point at the rows. Its error appears above the list, and the last good
  list stays on screen, so a bad night never leaves you with an empty screen.
- **An address is the same student however it is typed.** Capitals and spaces before or after it
  make no difference, so a row whose address was typed with a space around it is listed, and can be
  deleted, like any other. When a student's rows spell the address differently, their card says so.
- Pressing **Scan now** while a scan is running does nothing except say so. **Review selection** is
  disabled while a scan is running, because the rows underneath the list are about to change.

#### Working through the list

1. Open the finder and read the tiles: how many students are duplicated, how many are **Ready**, how
   many **need a decision**, and how many rows are duplicated in each table.
2. Start with **Ready**. Press **Select all ready**, or tick students one at a time.
3. Move on to **needs a decision**. Each row that may go has its own checkbox and, beside it, the
   sentence saying why it was held back. Read the sentence and decide. Nothing is ever ticked for
   you here.
4. Press **Review selection**. Nothing has been deleted yet.
5. The confirmation lists exactly what will go, table by table, and lists anything it left out with
   the reason. Rows that would leave a student with no row in a table are left out here already,
   not only after you press Delete. Read it. This is the page to turn back from.
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
  named on the notice. A row has changed when it carries more work, answers or hours than the list
  showed, when a value that held it back was replaced (a grade, a note, a screenshot), or when it
  gained a reason to be kept that it did not have.
- **One delete runs at a time.** A Delete pressed while another is running deletes nothing and says
  so; review the selection again once the first has finished. After a delete that was cut off part
  of the way, by a timeout for instance, the same notice can show for up to five minutes from when
  that delete began.
- **If the site cannot read what on it points at the rows, nothing is deleted**, and the notice says
  so.
- **The last row an address has in a table is never deleted.** Whatever else happens, a student
  cannot be erased from a table entirely.
- **Rows go in order: Feedback, then Students Reports, then Students**, so a child row never
  outlives its parent.
- **A sealed copy of every row is written to the site first.** If the copy cannot be written, the
  row is not deleted.
- **If Airtable stops a delete part of the way**, the notice says what was deleted before it stopped,
  with Airtable's own message. The rows it did not confirm stay in the list and can be selected and
  deleted again; each still ends with one copy and one log entry.

#### Deleted rows, and the 30-day copy

The **Deleted rows** list under the students records who deleted which row, from which table, and
when. **View copy** shows that row's cells exactly as they were, so you can type them back into
Airtable by hand.

**There is no automatic restore.** Bringing a row back is a manual job, and a restored row gets a
new record ID, so anything that pointed at the old one still needs fixing. After 30 days the cells
are erased and the entry stays without a name or an address, as a permanent record that something
was deleted.

An entry whose delete Airtable did not confirm says so, and the daily run checks it again: a row
still in Airtable leaves the list, copy and all, since nothing was deleted, and a row that is gone
becomes an ordinary entry. A copy whose delete Airtable never confirmed is erased at 30 days too,
and its entry says the row may still be in Airtable. Look its record ID up there: if the row is
still there, the next scan lists it again.

#### Turning deleting on

**Deleting ships switched off.** Until a program manager turns it on under **WPCredits Program →
Settings**, in the Student Duplicate Finder's card, the whole list is read-only: no checkbox can be
ticked and **Review selection** is grayed out. Scanning and reading work either way, so the list is
worth looking at long before anyone decides to delete from it.

Leaving it off between clean-ups is a reasonable habit, not a sign that something is wrong.

#### On the Administrator Dashboard

The **Duplicated students** tile counts the students the last scan listed, and its card links here.
A tile at zero means the last scan found nothing, not that no scan has run: the card says which.

### Track Builder

**WPCredits Program → Modules → Track Builder.** A program track is one Airtable status, one form on
the Student Report Card, a key chip in one color and, when the track follows one, a Learn course and
an hours target. Before the Track Builder, a new track was a plugin release. Now it is a draft you
write here, preview, publish to Airtable and switch on, with no developer in the loop.

Two things are worth knowing before you touch it. **Nothing reaches students until a track is
published**, and every notice and the preview say so. And **the four tracks the program runs today
are here too**, as built-in tracks that still run from the plugin's own code; the last part of this
section is how to move one of them onto its definition.

#### The track list

{{image:track-builder-list|In this sample list, the program's four tracks run from their definitions, and a track of your own, the Accessibility Track, stays a draft until you publish it.}}

One row per track, in the order they were made:

| Column | What it shows |
| --- | --- |
| Track | The track's name |
| Status | The Airtable status a student holds to be on this track |
| Runs from | *Its definition*, or *Its hand-written form, so it cannot be edited here* for a built-in track that has not switched yet |
| State | *Draft*, *Published*, *Unpublished changes*, or *Live, from its hand-written form* for a built-in track; under it, for a built-in track, whether its definition is published yet and then whether it matches the plugin's form, a line when the last compile left the track out, and a line when a live track's status is missing from **Currently mentoring** in Settings |
| Students | How many synced students hold its status now |
| Last published | When, and by whom |
| Actions | **Edit**, **Duplicate**, **Preview**, **History** and **Publish** (**Publishing** once it is; **Publish definition** on a built-in track), then the buttons only some tracks get |

**New track** sits above the list. The buttons a row gets only sometimes: **Refresh from the
plugin** on a built-in draft that fell behind a plugin update, **Run from its definition** and **Run
from its hand-written form** on a built-in track, and **Delete** on a track of your own that was
never published. Delete asks first and cannot be undone, and it deletes that draft and nothing else.
A track that was ever published keeps its row, because its columns and its status live on in
Airtable, and a built-in track is never offered it.

#### Starting a track

Three ways, each ending on the new track's page, as a draft.

- **New track** asks for the three things two tracks can never share: the **Name**, the **Airtable
  status** and the **Key**, the short word on the chip, plus the **Learn course** link when the
  track follows one. A name, status or key another track holds is refused, even when that track is
  a draft, since publishing the draft would claim it. With a link, the course's lessons are listed
  beside the questions, and the name is taken from the course when it is left empty. The chip color
  is chosen for you, the first one no other track holds, drafts included. The form starts empty; the
  questions are added on the track's page.
- **Duplicate** copies every question of an existing track, the four built-in ones included, and asks
  for a name, a status and a key of its own, refused as New track's are when another track holds
  them. The copy starts with no Learn course and no hours target; set them on its page. This is the
  usual way to start a track that resembles one you run: duplicate the 150-hour track and change what
  differs.
- *From a Learn course link* is New track with the link filled in. The lessons appear under the
  groups that are their modules, and each lesson offers **Add a question under this lesson**, which
  is how a form gets built lesson by lesson.

#### The track's page

The properties come first: **Name**, **Airtable status**, **Key**, **Learn course**, **Hours
target** and **Key chip color**, which is one of blue, cyan, teal, green, red, pink or purple. The
hours target may stay empty. **Save the track** saves the properties; the questions save themselves
as they are added, edited and moved. Save refuses a name, status or key another track holds, drafts
included, and a refused Save draws the boxes again as you left them.

{{image:track-builder-track|On a track's page you set its properties and build its form group by group and lesson by lesson, and a line above the questions says what publishing would create in Airtable.}}

The **Learn course** row shows what the link resolved to, the course's title and number. **Read the
course again** asks Learn afresh; otherwise the site keeps a day's reading, so a lesson renamed on
Learn shows up here within a day. When Learn cannot be reached, the link the track already has is
kept with a warning, the lessons cannot be listed, and the track still saves and publishes. A new
link is not taken while Learn cannot be reached, and the notice says so; the box keeps the link you
typed, so pressing **Save the track** again once Learn answers takes it.

Changing the link to another course matches every question that carried a lesson against the new
course's lessons by its heading. Only a lesson's first question carries the lesson's title as its
heading, so the other questions of that lesson follow it; only the questions of a lesson none of
whose questions matched are left with no lesson. The notice names the questions matched and the
ones that no longer point at a lesson.

Then **Questions**, by group: Total hours, Onboarding, Project and Wrap-up, the four parts of the
Student Report Card's form. Total hours holds one question, Hours, which students see as the hours
box. Any other question goes in Onboarding, Project or Wrap-up: the question screen offers Total
hours to Hours alone, and Save and Publish refuse anything else. On a track with no Learn course,
students see the hours box in a section of its own, **My hours**.

Each question is a row with what the student reads, its Airtable column and its control, and beside
it **Edit**, **Move up**, **Move down** and **Remove**. The arrows move a question at once; if the
site cannot keep a move, the question goes back and a notice above the list says why. Remove takes
the question off the form; the column, and whatever students wrote in it, stay in Airtable, and the
confirmation says so.

Each group ends with **Add a question**: the Airtable column, what the student reads, the control and,
when the group's module has lessons Learn answered, **Under lesson**. Total hours offers it only
while the track has no Hours question. Adding opens the new question's page.

When the track follows a course, the lessons of each group's module are listed under the group's
questions, with a count such as *Lessons on Learn: 3 of 13 have questions.* Each lesson names the
questions that report on it, or offers **Add a question under this lesson**. A question added that way
is placed after the lesson's last question, carries the lesson, and takes the lesson's title as its
heading when it is the lesson's first question.

A line above the questions says what publishing would create in Airtable, *This track needs no new
Airtable columns.* or *Publishing will create 3 columns in Airtable.*, and how long ago the base was
read.

#### Controls and columns

Ten controls: Text, one line; Text, many lines; Rich text; Web address; Email address; Number;
Checkbox; One choice of several; Screenshot; Contribution team. The control decides the Airtable
column type: a new column is created with the type the control needs, and a column that already
exists has to be of that type, or the publish screen refuses.

A duplicated track shares its columns with the track it came from, and the question's page says so,
naming every other track that writes the column. Rewording a shared question keeps the column.
Changing its control, or the choices of a select, gives it a column of its own, named after the track,
which can still be renamed until the track is published. A different control is a different column.

#### One question

Each question has a page of its own: the column and the control at the top, then the rows that apply
to that control.

{{image:track-builder-question|On a question's page you set its column and its control, then the rows that apply to that control, here for a required line of text under a Learn lesson.}}

| Row | What it does |
| --- | --- |
| What the student reads | The label above the box |
| Group | Onboarding, Project or Wrap-up; Total hours for the Hours question, which goes in no other group |
| Help under the box | A sentence under the control |
| Heading before it | A heading printed before this question; the first question under a lesson carries the lesson's title here |
| Subheading before it | A second heading before this question, drawn like the first |
| Note after the run | A sentence after the run of questions this one ends |
| Row, and Shares one column of its row | Questions with the same row name sit side by side |
| Marked required | Draws the word *Required* beside the label; nothing is enforced |
| Kept off everything an institution reads | The answer is left off the Student Report Card an institution sees |
| Lowest value, Highest value, Step | A number's limits; the step also sets how many decimal places a new column keeps |
| Length limit | A single-line box's limit; a text area has its own |
| Monospace, for code | A text area drawn in a monospace face |
| Choices, one a line | The choices of a select; a column that already exists must offer every one of them |
| Learn lesson | The lesson this question reports on: the course's lessons by module, *None* first, or a number box when Learn cannot be read |
| Developer note | Why a column name looks like a slip; no student sees it |

**Save the question** returns to the track. A refusal redraws the question with what was typed, and
the reason.

**Once a track is published, its questions are locked**: the column, the control and the choices are
fixed, because the column in Airtable holds what students wrote, in that shape. The words can still
change. To ask something differently, remove the question and add a new one with a column of its own.

#### Preview and History

**Preview** draws the draft's form as a student sees it: empty answers, no student's record, the same
renderer and the same stylesheet as the Student Report Card. Nothing typed there is kept. The hours
box sits where the Student Report Card puts it: with the button that opens the course when the track
has a Learn course, side by side on the student's page and one under the other here in wp-admin, and
on its own when the track has none. A built-in track
can be previewed too; its definition is what its form draws.

{{image:track-builder-preview|Preview draws the form a student on the track fills in, the same questions in the same order, and nothing you type there is kept.}}

**History** has three parts: what publishing would change, the published copy against the draft;
every save, newest first and at most twenty, with who saved it, when, and what changed since the one
before; and the publish log, every publish, unpublish, switch, column created and checklist item
ticked, with who and when.

{{image:track-builder-history|History shows you what publishing would change, every save with who made it, and the publish log, here with sample entries for one of the program's four tracks.}}

#### Publishing

**Publish** opens the publish screen. Nothing happens until the button at the bottom is pressed; the
screen first reads the base and says what it found.

{{image:track-builder-publish|Before you publish, the screen lists the columns it will create and the three steps only you can take in Airtable, then asks you to type the track's name.}}

- **This track cannot be published yet** lists what stops it: a column in Airtable with a question's
  name but another type, a control that cannot have a column created for it, a table that would pass
  Airtable's limit of 500 columns, a status, key or name another track holds, a draft's included, a
  question other than Hours in Total hours or Hours in another group, and a **Students Reports
  table** setting that holds anything but the table's ID, its name for example. **Check it against
  Airtable** refuses the same way while that setting is wrong.
- **Worth knowing before you publish** lists what does not stop it: a choice of the Status column on
  Students Reports or Students that nearly matches the track's status but not exactly, which the
  syncs would never match; a Learn course link that does not resolve; a table past 450 columns. A
  choice that is missing altogether is not a warning: the third checklist item below says, for each
  table, whether it has the choice yet.
- **Columns**: every column the track writes to, and whether it exists or will be created. With a
  schema token, the optional second token under **WPCredits Program → Settings**, the site creates
  the missing columns when you publish. Without one, the screen lists the exact columns to create by
  hand, name and type, and Publish waits until the next reading finds them.
- **What the site cannot do**: the three Airtable steps no token can take, with the exact values to
  use. Add the status to the condition of the automation *Add students to Students Reports and
  Feedback*; create the track's welcome email automation, as each of the four tracks has one; add the
  status as a choice of the Status column on both tables. Tick each with **I have done this** once it
  is done, and the tick records who and when. An unticked item never blocks publishing, but until
  the automation item is ticked, students cannot be put on the track from the institution import,
  because they would never get a report row.
- Publishing a track of your own adds its status to **Currently mentoring** in Settings. The four
  built-in statuses are there already, so publishing a built-in definition adds nothing. While a
  track runs from its definition, a Settings save that would take its status out saves everything
  else, leaves Currently mentoring as it was and names the track.

When publishing would create columns in Airtable, Publish comes with a box: type the track's name
exactly as it is written, then press the button. Capitals count; spaces around the name do not. The
site can never remove a column it created, so a press whose text is not the name creates nothing and
says so. The name confirms the columns the screen lists: if the columns publishing would create
change before the button is pressed, nothing is published and the screen comes back with the new
list to read and confirm again. With nothing to create, Publish is one press; a press that finds
columns gone from the base since the page was drawn creates nothing, and the screen comes back to
show them.

Publishing runs its steps one at a time and records each. If Airtable refuses part-way, the notice
carries Airtable's own message, and pressing Publish again picks up at the first step not done. If
the page stops part-way instead, because the server gave up on a long run, the columns made so far
are already recorded, but for up to twenty minutes Publish on every track says another track is
being published. After that, press Publish again: it creates only the columns the base still lacks,
and the log names every column the site made.

What goes live is the track as it stood when Publish was pressed. An edit saved while Publish runs,
in another tab or by another Program Administrator, is not part of that publish: the track then
shows *Unpublished changes* until **Publish the changes** is pressed.

After publishing, the same screen offers **Check it against Airtable**, which reads the base again and
says whether every column is still there with its type and the status is a choice on both tables, and
**Take it off the live site**. On a built-in track that still runs from its hand-written form it
offers **Check it against Airtable** alone: the definition stays published, since the track's
students see the hand-written form either way. Unpublishing is refused while any student holds the
status, with the count. Otherwise the track becomes a draft again, nothing in Airtable changes, and
the status stays in Currently mentoring: removing it there takes the Student role from everybody on
the track, which is a decision of its own.

Editing a published track's words makes it *Unpublished changes*; students keep the published copy
until **Publish the changes** is pressed.

#### The four built-in tracks

The 150-hour, 50-hour, Developer and Designer tracks run from forms written in the plugin's code. Each
has a definition in the Track Builder, shown as *Live, from its hand-written form*, and the line under
the state says whether that definition is published yet and, once it is, whether it is identical to
the form. Moving one onto its definition takes three steps, and students see no change at any of
them:

1. **Publish definition**. Until then the line under the state reads *Its definition is not published
   yet*. If a plugin update changed the form since the definition was made, the row also offers
   **Refresh from the plugin**: press that first, since a definition that differs from its
   hand-written form cannot be published. The preflight should find nothing to create, because every
   column and both choices exist already; that empty preflight is the proof that the definition
   matches the base. The three checklist items were done for these four tracks long ago, so tick them
   as done.
2. Read the line under the state again. *Identical to its hand-written form.* is what you want, and
   only then is **Run from its definition** offered.
3. **Run from its definition**. From then on the Student Report Card draws the form from the
   definition, and the notice says that what students see has not changed, which is what let it
   switch. **Run from its hand-written form** puts it back the same way, as long as the definition
   has not been edited since the switch.

Do this for all four before asking for the hand-written forms to be removed from the plugin. Until a
built-in track has switched, it cannot be edited here: it can be duplicated, previewed and read in
History, and its course read again.

### Need help?

The tool screen for the question box configured under Settings. Its own screen is where the handbook
page lives and where you can see whether a provider is set.
