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
deletes the ones you tick and confirm. A second row usually comes from the Airtable automation that
creates a student's Students Reports and Feedback rows: it runs again for an address that already
has them, for example when a student who did not move forward applies again.

- **It scans every three hours**, after the four syncs, and **Scan now** runs a scan while you
  watch. A scan only reads Airtable. When one fails, its error shows above the last list, which
  stays.
- **Ready** students have an older row in each table with nothing attached to it: one checkbox
  selects them all, and the newest rows stay. Students who **need a decision** have a checkbox on
  each row that may go, with the reason it was held back beside it. Nothing is ticked for you;
  **Select all ready** ticks the Ready students.
- **A row the site uses cannot be deleted.** When a student's account, their surveys, a mentor call
  note, a booked call or an audit log entry points at a row, its checkbox is disabled and says what
  points at it: delete the other row instead.
- **Review selection** shows exactly what will be deleted, table by table, and what was left out and
  why. Only **Delete** on that page deletes anything. Just before it does, the finder reads the rows
  from Airtable again and leaves out any that changed since the scan, and it never deletes the last
  row an address has in a table.
- **Every deleted row is kept on the site, sealed, for 30 days.** The **Deleted rows** list under
  the students says who deleted which row and when, and **View copy** shows the row's cells so you
  can type it back into Airtable by hand. There is no automatic restore. After 30 days the cells are
  erased; the entry stays, without a name or an address.
- **Deleting is switched off until you turn it on** under **WPCredits Program → Settings**, in the
  Student Duplicate Finder's card. While it is off, the finder scans and lists and deletes nothing.

On the Administrator Dashboard, the **Duplicated students** tile counts the students the last scan
listed, and its card links here.

### Need help?

The tool screen for the question box configured under Settings. Its own screen is where the handbook
page lives and where you can see whether a provider is set.
