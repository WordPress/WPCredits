# The Institution Dashboard in the frame, on the shared parts (design, 15 November 2026)

Plugin: WPCredits Program Manager, from main at 1.123.94, with WPCredits theme 1.32.27. Built now, released after
20 November 2026 (schools are testing today's screen until then), on the owner's yes to before and after pictures.

## 1. Why

The owner, 15 November 2026: "Yes, start according to the plan." The Institution Dashboard is the next dashboard
to move into the frame (side menu, a view per item) and onto the shared parts (one button set, fold, field,
Decide row, table, list), as the Administrator Dashboard did (frame 1.123.69, parts 1.123.92) and the Sponsor
Dashboard did for the frame (1.123.78).

The audit of 13 November 2026 reads today's page as one long page outside the frame: the site's header, the name
in 22px sans inside an 8px card, a blue "Viewing as" bar, 56px resource buttons, arrows on every section, three
kinds of fold, fields of four heights in one row, a blue 4px edge on the cohort summary, and a section named
"Semester report" holding a fold of the same name (32 defects for a representative, 6 more for an Administrator,
10 on the report draft). At 390 wide two Enroll students selects run past their card (340 and 342px against
329) and every History meta line does (369px), on today's build and after every gate since.

## 2. Decisions the owner already made, kept here

1. Full width with a side menu, on the right in a right-to-left language.
2. A view per menu item, each with its own address (`?wpcpm_view=`); a menu item never jumps to a column of
   another view; every link to a card goes to the view that holds it.
3. One name per page: the head's H1 only, no back links; the menu is the way back.
4. What waits is a status tag in words on a plain card, never a colored edge. One box per card row.
5. The Ask box goes down the side menu, under Help. Each dashboard's own guide moves into its frame. Program
   updates open in the frame.
6. The parts as approved on 13 November ("All looks good"). Under 800px of main area every part takes its phone
   form. Nothing is wider than its card at any width. Dates whole. A long unbroken word in a name breaks
   (`WPCPM_UI::breakable()`).
7. Dashboards look alike: the same parts and rhythm as the Administrator Dashboard. Rhythm 14 / 16 / 24px,
   nothing under 12px, corners 2px, flat.
8. Secondary controls stay subtle: a rarely changed setting is one small control top right, no Save button.
9. One field to type and pick. "Administrators", full product names, the Education Directory, "Collaboration
   Agreement", no em or en dashes, never "out of force" in a new string.
10. No wp-admin screen, no setting, no restyle of wp-admin. Nothing links to Airtable. A track may have no hours
    target.
11. Every other surface prints and looks exactly as before (the Administrator Dashboard, the Sponsor Dashboard,
    the Report Cards, wp-admin, emails).
12. Behavior does not change: same forms, handlers, messages, permissions, data. Words change only where a part
    needs a label a screen lacks (section 12 lists each).
13. Every card on a view is drawn open (owner, 11 November, 1.123.80, on the Sponsor Dashboard); the disclosures
    inside a card keep their own state.

## 3. The views

By `?wpcpm_view=`, as on the other two dashboards. The page with no argument is the Overview, so every link to the
page that exists today still lands. In the order a school's work runs:

| Menu item | View | What it holds | Who sees it |
|---|---|---|---|
| Overview | (none) | The head, the figures, what waits, the contact at the program | Everybody |
| Students | `students` | The roster (filters, cohort summary, the four groups, Enrollment not recorded yet, the cohort's CSV), and one student's card in place of the roster | Agreement settled (or an Administrator) |
| Enroll students | `enroll` | The enrollment form, and its check, confirm, progress and outcome | Enrollment switched on and the reader may add students (`ACT_ADD_STUDENT`) |
| Semester reports | `reports` | The reports: the list, an approved report to read; for an Administrator the index, Draft now and the editor | `ACT_VIEW_SEMESTER_REPORT` |
| Your institution | `institution` | Your institution's details (with Tracks offered and, for an Administrator, who follows it up), Representatives and tutors (members, invitations, Invite a colleague, Tutors) | Agreement settled (or an Administrator) |
| Agreement | `agreement` | The Collaboration Agreement: the panel while it is not settled, the card once it is; for an Administrator, recording the copy the program holds | Everybody |
| Events | `events` | Events from our sponsors (and the program), with Join and Leave | The school's own representatives and tutors on their own page, while the list holds an event |
| For Administrators | `administrators` | From the application, History | Administrators only |

Then **Program updates**, while there is an update institutions may read, and **Help** (section 8).

Where each of today's blocks goes; nothing is dropped and nothing is added, except the arrows (below):

| Today | Goes to |
|---|---|
| Identity header (icon, name, place, website, Contact, Stage for an Administrator) | The head, every view (section 4) |
| Resources: Program updates and announcements, Slack, Institution guide, Need help?, Your contact at the program | The menu (Program updates; Help: guide, Slack, Write to the program, the Ask box) and the Overview's contact card |
| The Administrator's unsettled banner | The Overview's what waits, and the Agreement view's first line |
| Agreement panel (unsettled) and Collaboration Agreement card (settled) | Agreement |
| Students module: roster, then Enroll students | Students; Enroll students |
| Semester report module | Semester reports |
| Representatives and agreement module: details, people, agreement card | Your institution; Agreement |
| Events from our sponsors | Events |
| For Administrators | For Administrators |
| The read line at the page's foot | The Overview under the figures; the Students view at the roster's foot |
| The section arrows (Move up, Move down) | Gone (builder's call 1) |

**What the menu counts:** each view's count is the number of the Overview's waiting rows that open in it, as the
frame's red tag "%s waits"; the Overview's is their sum (the Sponsor Dashboard's rule and words).

**A view the reader may not open is not in the menu.** The menu is printed before the cards, so each view answers
from the same policy question its cards ask (the table's last column), never by drawing the cards first. A view
whose cards still print nothing says "Nothing to show here yet." (the Sponsor Dashboard's line).

**A locked school** (a representative whose agreement is not settled) gets Overview and Agreement, Program
updates and Help. A tutor of a settled school gets every view but Enroll students and Events (the events are
offered to representatives only today: `$own_page`), and the views show what the cards show a tutor today.

## 4. The head

- The H1 is "Institution Dashboard", printed by the plugin when the theme leaves the page to the frame
  (`WPCPM_Dashboard_Frame::owns_page( 'institution' )`), as the Sponsor Dashboard's `page_name()`.
- On the Overview the title (h2) is the school's name through `WPCPM_UI::breakable()`, with its site icon beside
  it at 48px as today (a favicon is small and square; the Sponsor's 88px logo box is for wordmarks). Under it one
  facts line in the item header's style, each fact its own: city and country, the website as a link, "Contact:"
  and the person, and for an Administrator "Stage:" and the stage. These are today's strings; a missing fact
  takes no separator.
- On every other view the title is the view's name and the lede one sentence on what it holds (section 12).
- **Enroll students**, a secondary button in the head, opens the Enroll students view: the commonest job, while
  that view is in the menu. On a locked page the waiting row is the way to the agreement and the head has no
  button.
- An Administrator's "Viewing as institution" is the small control at the end of the head's first row (section 7).
- The two-factor prompt stays above the head, as on the Sponsor Dashboard.

## 5. The Overview

From data the site already has; nothing new is stored or fetched.

- **Figures** (the homepage's band, as the Sponsor's): for the cohort the Students view opens on (the roster's own
  default, `default_cohort()`): signed up, graduated (`WPCPM_Cohort::participation()`), on the program now and
  waiting for a mentor (the counts of the Current and Waiting groups, `WPCPM_Roster_Index::groups()`). The cohort's
  name is the band's base line. A cell with nothing to count is left out; with all four at zero the band is not
  printed. Under it the roster's read line ("Read from the program records on ..."), once. Not printed for a
  reader the roster refuses.
- **Waiting for you** (the Sponsor Dashboard's rows: the tag "Waits", a title, the sentence that says why, one
  secondary action to the view and card that hold it; "Nothing waits for you." when nothing does):
  - the agreement, while not settled and the school is the one to act (none recorded, generated and not uploaded,
    returned, revoked): to Agreement. A signed copy waiting for review waits for an Administrator, not the
    school, and is not a row; the Agreement view says it.
  - a checked enrollment list not yet created, or a creation in progress (the batch the form already finds):
    to Enroll students.
  - a semester report approved in the last 30 days: to Semester reports, that report.
  - the school's details request answered (approved, or declined with a note) in the last 30 days and while no
    newer request is sent: to Your institution, the details card.
  - for an Administrator only, first: the unsettled banner as a row, its title and today's two sentences (the state
    in words, and Airtable's Agreement Status when it has one): to Agreement.
- **Your contact at the program:** today's block as a plain card: the heading, the name, the address as a mail
  link, Book a call when there is a booking link, and the sentence. Only while an Administrator is assigned.

## 6. The views on the parts

Every card is `WPCPM_Dashboard_Card` (card fold, 56px head: title, phrase, tag, cue, "How to do this"), drawn open
on its view. A card that repeats its view's title prints no head and its body is the view's content (M22). Every
row action is the quiet button; destructive ones quiet red. Every refusal keeps today's sentence, tied to its box
(`WPCPM_Form_Errors`); kept typing stays (`WPCPM_Typed_Text`). Every form posts the same names, values, nonce
actions and `action` as today.

**Tables.** No table on this dashboard is `wide` (none has seven columns), so each one stacks under 800px of main
area by the parts' own rule. The least width is the card's inner width at 390, about 326px (the gate measured
329 on today's page); the rig measures it at 17 widths.

| Table | Columns, kind | Long text | Notes |
|---|---|---|---|
| A student's facts in the roster | Key and value: Program, Dates, Days left, Mentor, WordPress.org, Team, Website, Field of study, Tutor, Hours | WordPress.org, Website (addresses), Team, Field of study, the Mentor sentence | Empty keeps "Not recorded", muted; dates whole |
| A student's card | Key and value: Program, Internship duration, Cohort, Field of study, Tutor, WordPress.org, Contribution teams, Personal website, Mentor, Mentor email | Mentor email (breaks), the addresses, the teams | "Not set", "No mentor yet" muted |
| Contact and agreement | Key and value, 6 rows | Students study in (a list of languages), Page about WordPress Credits (an address) | "Not given yet" muted; the waiting line under its row |
| Name and address | Key and value, 5 rows | Name (a 40-character word breaks), Website, Contact's email | As above |
| From the application | Key and value: the question, the answer | Both (questions as labels wrap in the 320px label column) | Administrators only |
| The enrollment check | Line (num), Name (text), Email address (text), What happens (text) | Name, the address, the sentence | Stacked under 800px of main area; the line number reads from the left |

The report document (the Semester report as it prints) keeps its own markup and `report-print.css`; only the on-screen
monospace rule of its text boxes goes, as the parts' design says.

### 6.1 Students

- **Filter bar:** one row of fields (design 4.4): Cohort (select), Group (select), Search (the search field,
  320px, today's hint as its hint), then Show (secondary), Clear the filters (quiet, while a filter is set) and
  "Download this cohort (CSV)" (quiet link). Still one GET form with today's names; an Administrator's form keeps
  `wpcpm_institution_view`, and now `wpcpm_view=students`. Under 800px of main area the fields stack at full width.
- **Cohort summary:** a plain block, its heading the cohort's name and today's two sentences; the blue edge goes.
- **The four groups:** each a card fold: title the group's name, phrase its count in words ("2 students"), the
  group's sentence the body's first line, "No students in this group." (or "... match the filters.") as the empty
  line. Open as today (builder's call 4): Current and Waiting for a mentor open, Finished and Not on the program now
  closed, a group of more than twelve closed.
- **Each student:** an inner fold (44px): the 44px photo or initials as its media, the name (a link to the card
  when the student has an account), the program's status as a status tag, and the dates and the mentor as its
  second piece; the body the key and value table. Phone: the parts' two-column head, the chevron pinned top right
  (today it changes side with the name's length).
- **Enrollment not recorded yet:** a card fold, phrase its count in words, today's sentence, a list of rows (name,
  status tag; an Administrator sees the address in the facts).
- **A student's card** (`wpcpm_institution_student`): in place of the roster, as today, under the view's head. The
  item header: the photo, the name, the program's status tag, the facts. The sign-in line with Send invitation or
  Resend invitation (secondary). The key and value table. Inner folds "Student Report Card" (fetched on open, as
  today) and "Correct this student's details" (open when its save left a sentence): five fields, label above,
  dates 200px, Field of study a select, Save changes (primary). "Download this student's details and course
  grades as a CSV file" a quiet link. The read line. Then its own card "Finishing this placement": a Decide row,
  Mark as graduated (primary, only while switched on) and Mark as dropped out (danger), each at once with today's
  browser question; today's sentence when it offers nothing. "Back to the roster" is not printed: the menu's
  Students item, while a card is open, leads to the roster with the reader's filters (builder's call 3).

### 6.2 Enroll students

The card "Enroll students", open. Step fields on the parts: Program (select, at most 320px and never past the
card), Start date and End date (200px; "End date" Optional, the rest of today's label its hint), the notified
checkbox its own question (Required), One student (Full name, Email address, Field of study, Tutor) as a field set,
Or a list: the file box (Choose a file, the name beside it) with "Download a sample list" beside it, and the paste
box (a text area at the card's width). Check the list (primary). The check: the count sentence, the ignored columns
line, the check table (section 6), then Create these student records (primary, today's question) and Throw this list
away (danger, today's question). Progress and outcome as today's sentences, Continue (secondary).

### 6.3 Semester reports

The view's card prints no head (M22). The index is a list: each semester a row, its name, its state as a status tag
(Approved green; Draft and the others neutral), its facts ("Approved on" and the day), and its quiet actions: View
and Download PDF for a school; for an Administrator Open, or the Draft now row form already on the parts on the
Administrator Dashboard (`render_draft_form_parts()`). A semester being prepared is a row with today's sentence and
no action. Reading: the item header (the report's title, its tag, its facts), Download PDF (secondary), the document.
The editor (Administrators): the item header, the section headings as in-card headings (no legends), the text boxes
as text areas in the page's face, the quote picker a choice group, Save the report (primary of its form), then one
row of Print this report and Check the students' answers again (secondary), then a Decide row: Approve this report
(primary) or Reopen for editing (secondary), at once with today's questions; Revisions an inner fold. "Back to the
other semesters" is not printed (builder's call 3).

### 6.4 Your institution

- **Your institution's details:** phrase "What the program has on record." Two in-card headings, Contact and
  agreement and Name and address, each its key and value table and then its inner fold, "Correct these" with
  "(saved at once)" as its second piece, "Ask to correct these" with "(an Administrator checks first)". The forms:
  the application's own questions as fields, the languages a choice group, Country the one combobox; Save the
  details (primary) and Send to an Administrator (primary), one per form. While a request waits: "Waiting for an
  Administrator:" and the request's facts under each row it changes, with Withdraw it (quiet red, today's question).
  The outcome line under the second table. Tracks offered: a heading and a list; for an Administrator the inner folds
  Change the tracks (a choice group, one primary) and Change who follows it up (the combobox, one primary).
- **Representatives and tutors:** phrase in words ("3 people"). Today's intro sentence. The people rows (2.8): the
  name, "the institution's contact" as a neutral status tag on that row, the facts under (address, Representative or
  Tutor, how and since when), Remove or Leave (quiet red, today's questions). The named contact with no account a row
  with today's sentence as its note. Invitations waiting: rows with who sent it, when and the day it stops working,
  Resend (quiet) and Cancel invitation (quiet red), 16px apart. Invite a colleague: the paragraph under the button
  becomes the form's intro; Email address (Required, no placeholder), Level a choice group with each option's hint,
  Send invitation (primary). Tutors: rows (name, address), Remove (quiet red); Add a tutor: Name and Email
  (Required), Add (primary). For an Administrator, "Add or re-add an account on the Institutions screen" as a quiet
  link. The read line.

### 6.5 Agreement

The view's title is "Collaboration Agreement"; the card prints no head. A status tag in words leads: Accepted (green),
Waiting for review (amber), Returned and Revoked (red), and for none and generated no tag (the sentence says it).

- **Settled:** "Accepted on" and the day, how it was signed or recorded, Download the accepted agreement
  (secondary); "Replace it ..." as the upload form's intro; while a newer copy waits, its sentence, Download the copy
  waiting for review (secondary) and Withdraw it (danger, today's question). For an Administrator, the Drive link
  (quiet).
- **Not settled:** today's sentence for the state, the three steps as an ordered list of sentences, the generate form
  ("The institution's name" its label, "Exactly as it should print on the agreement." its hint; Generate the
  agreement, primary), Open that document again (secondary) once generated, the already-signed note, Download the
  copy we hold (secondary) and Withdraw it (danger) while it waits, the note an Administrator left (`WPCPM_UI::note()`)
  when returned or revoked. For an Administrator, today's banner sentences first, and recording the copy the program
  holds.
- **The upload form:** "The program's template, signed" and "An agreement of our own, signed" a choice group, the
  signer's checkbox its own question (Required), the file box (PDF), Upload the signed agreement (primary).

### 6.6 Events

The list on the parts' list rows (its parts' branch exists for the Administrator Dashboard): the event's name, the
host and its day and time in the reader's zone, how long and the places left as facts; "You are on it." its note;
Join (quiet), Leave (quiet red), Join the call (quiet link). Heading words as today ("Events from our sponsors", "...
and the program").

### 6.7 For Administrators

Lede "Only Administrators see this section." (today's). Two cards, open: From the application (the key and value
table) and History (a list, facts under: each sentence, then the date and time whole and who, so a long name
breaks and the meta line never runs past the card; today it runs 40px past at 390).

## 7. Viewing as institution

- **Where:** one small control at the end of the head's first row, on every view: today's label "Viewing as
  institution", muted 14px beside the box (the parts' one exception to a label above its box, 2.4), the one combobox (`WPCPM_Dashboards::render_combo()`, 320px,
  40px), and no Show button of its own: choosing a school loads its page on the same view. Without script the
  combobox is the sorted select and today's Show (secondary) is printed for it. "Only Administrators see this
  control." becomes the box's hint for screen readers. On a phone it takes its own line under the H1 at full width.
  It is drawn in a parts' branch of `WPCPM_Dashboards::render_switcher()` taken only on `institution`; the Sponsor
  Dashboard and the Report Cards keep today's switcher.
- **Before a choice:** an Administrator whose address names no school (and who belongs to none: `attach()` refuses
  Administrators) no longer gets the first school with a member. The Overview's head says "Choose an institution"
  and one sentence (section 12), the combobox is the main area's one field, and the menu lists the views with no
  counts, each landing on the same choice. Decided in the dashboard's `render()`, before the record is resolved;
  `WPCPM_Institution_Roster::resolve_institution()` and its fallback are left as they are, since the handlers and
  the exports ask it too.
- **Carried:** every menu link, card link, guide link and update link keeps `wpcpm_institution_view` for an
  Administrator (a `keep_institution()` beside the Sponsor's `keep_sponsor()`); a member's links name none.

## 8. Help, the guide and updates

- **Help:** Institution guide (the frame's "book"), #wpcredits on Slack (the Administrator Dashboard's item, the
  channel today's Resources links), Write to the program (the assigned Administrator's address, while one is
  assigned), and the Ask box under them while the assistant answers institutions (the frame's `ask()`).
- **Program updates:** a menu item while `WPCPM_Updates::posts( 1, 'institution' )` has one; each update drawn in this
  frame with the others beside it. `WPCPM_Administrator_Updates_View::audience_of()` gains `institution`: a member reads
  it there, and an Administrator does when the address names a school or the Administrator Dashboard does not list it
  (the Sponsor's 1.123.79 rule).
- **The Institution guide in the frame:** `WPCPM_Administrator_Guide_View::guides()` gains `institution-guide`
  (frame `institution-guide`, dashboard `WPCPM_Institutions_Dashboard`, reader `institution`: a representative or
  tutor of any school, or an Administrator); `may_read()` learns that reader; the view names the `institution`
  surface while it draws (today it names `administrator` for every guide but the Sponsor's). It gets its "On this
  page" column as the other two, and the institution menu from `WPCPM_Institutions_Dashboard::menu_args( 'guide' )`.

## 9. Links to a card

`WPCPM_Institutions_Dashboard::card_url( $card, $record = '', $item = '' )` with `VIEW_CARDS` and `view_of()`, as the
Sponsor's. Anchors stay where they are: `wpcpm-agreement`, `wpcpm-import`, `wpcpm-report`, `wpcpm-details`,
`wpcpm-people`, `wpcpm-tutors`, `wpcpm-student-form`, `wpcpm-for-administrators` and the rest.

- Every way back that builds the page's address with an anchor goes through `card_url()`: the import form's
  `leave()`, the details card's `finish()`, the invite's `dashboard_url()`, `WPCPM_Institution_People::card_url()`,
  `WPCPM_Institution_Tutors::finish()`, `WPCPM_Semester_Report_Screen::report_url()` and its three other page links,
  `WPCPM_Institution_Assignment::page_url()`, the event list's way back, and the links from the Administrator
  Dashboard and wp-admin that name a card. A handler that returns through `WPCPM_Request::back()` already lands on
  the view it was pressed on.
- **Old addresses:** an address with no `wpcpm_view` that names a student, a roster filter, a batch or a report
  (`wpcpm_institution_student`, `wpcpm_cohort`, `wpcpm_roster_status`, `wpcpm_roster_search`, `wpcpm_batch`,
  `wpcpm_report`) is drawn in the view that holds it, so mails already sent still land. An address with only an
  anchor (the guide's `#wpcpm-import`, a bookmark of `#wpcpm-report`) can only be read by the browser: the Overview
  prints the anchors of the other views with their addresses as data, and the dashboards' opener
  (`dashboard-open.js`) moves to that view when the anchor names nothing on the page (builder's call 5). Only the
  Institution Dashboard prints the data, so the other dashboards behave as before.
- Mails that link the bare page (an agreement accepted or reinstated) open the Overview, as today.

## 10. The theme's part (one theme release after the plugin's)

- `functions.php`: `add_theme_support( 'wpcpm-dashboard-frame', ... )` gains `institution`, `institution-guide` and
  `institution-updates`.
- `inc/dashboard.php`: `wpcredits_is_frame_page()` gains the Institution Dashboard
  (`wpcredits_frame_names( 'institution' ) && wpcredits_is_institution_page()`); the guide and update templates follow
  from the plugin's `frame_for()` and `audience_of()`.
- Templates: `page-institution-dashboard.html` becomes the bare canvas the sponsor's is (`wpc-main wpc-main--app
  wpc-main--institution`, the post content only: no site header part, no post title); new
  `page-institution-guide.html` and `single-institution-update.html`, copies of the sponsor's.
- Body class: `wpc-institution-page` stays (with `wpc-dashboard-page`). The frame's own rules on `.wpc-main--app`
  already serve it; no card rules are added, since the cards print the parts' classes.
- **What goes:** every selector under `.wpc-institution-page` that matches a class the plugin no longer prints on
  the page, each after a search of the plugin at main (the parts' rule N): the movers and modules (about 730 to 880),
  the page's opening shape and switcher (514, 915 to 1009), the group and roster rules (1592 to 1609, 1990), the
  student rows and people forms (3227 to 3323), the phone rules (4503 to 4654). A list that names the Report Cards'
  pages too loses only its institution selector. `bin/check-selectors.php`'s institution pins go with them.
- **What must come with it, if not shipped before:** the choice rows' exception of the parts' design 6.2
  (`.wpcpm-ui-choice` in the `:not()` lists of `label:has( > input[...] )` and `input[type=...]`, theme
  `dashboard.css` about 5023 to 5037). Theme main is at 1.32.27 without it, and this page has four choice groups.

## 11. The switch and the proof

- `WPCPM_UI::LIVE` gains `institution`. `WPCPM_UI::enter( 'institution' )` is already called in `render()` and its
  root already prints `root_class()`; the frame's root takes it too.
- **Shared renderers keep their old branch** for the surfaces still off (the parts' releases 2 and 3 are not built):
  `WPCPM_Institution_People::render_member()` and `WPCPM_Institution_Panel::render_upload_form()` (wp-admin's Manage
  members and manager upload), `WPCPM_Dashboards::render_switcher()` (the Sponsor Dashboard, the Report Cards),
  `WPCPM_Sponsor_Event_List::render()` (the Report Cards' calendars), `WPCPM_Semester_Report_Screen` (wp-admin's
  Semester reports tab), `WPCPM_Dashboard_Card`'s old branch (the Sponsor Dashboard), `WPCPM_Module_Order` (the
  Student Report Card's modules). No old branch is deleted here unless a search shows no surface left on it.
- **The suites:** `bin/test-institutions-dashboard.php` and `bin/test-institution-*.php` rewritten to the new markup
  where it changes, each shared renderer asserted on both branches; new checks for the views, `card_url()` for every
  card, the old-address inference, the menu by reader (representative, tutor, locked, Administrator, no choice), the
  counts, and every handler's way back. What is posted, refused or kept is asserted unchanged, not weakened.
  `bin/test-sponsors-dashboard.php`, `bin/test-administrators-dashboard.php`, `bin/test-dashboard-card.php`,
  `bin/test-ui*.php`, `bin/test-administrator-guide.php`, `bin/test-student-modules.php` pass untouched.
- **The picture rig (`bin/ui/`):** scenes for every view and open state (each card, each inner fold, each form open,
  each agreement state for a member, a refusal kept, the student's card, the check table, the report editor, the
  choice state, the guide and an update in this frame) for a representative, a tutor and an Administrator, at the 17
  widths (390, 601, 700, 716, 768, 782, 783, 880, 900, 927, 928, 1000, 1015, 1024, 1110, 1200, 1280), with the seed's
  40-character words in a school's name, a student's, an address and a tutor's. Before (today) and after.
- **The overflow check at zero:** no OVERFLOW, H-SCROLL or CELL line on any Institution Dashboard scene at any width
  (today's lines at 390, the two Enroll students selects and the History meta lines, among them). Every other scene compares `same`, pixel for pixel.
- Keyboard, no script, refusals and posted fields as the parts' design section 8.

## 12. Words

New strings are marked **new**; the rest exist. Strings that stop printing leave in the same release; the `.pot` is
regenerated.

| Where | New words |
|---|---|
| View ledes | Students: "Your students on the program, by cohort and by where they are, with the list you can download." **new** (today's names the enrollment form, now its own view). Enroll students: "Add students to the program, one at a time or from a list." **new**. Semester reports: today's module lede. Your institution: "What the program has on record about your institution, and who can see this page." **new**. Agreement: "The agreement between your institution and the program, and the signed copy on file." **new**. Events: "Events the program's sponsors host, to join with your colleagues." **new**. For Administrators: today's |
| Figures | "signed up", "graduated", "on the program now", "waiting for a mentor" **new**; base "in %s" **new** |
| Waiting rows | "Collaboration Agreement not signed yet" **new**; "Collaboration Agreement returned", "... revoked", "Open the agreement", "Waits", "Waiting for you", "Nothing waits for you." (exist); "A checked list of students waits to be created" **new**, "Open the list" **new**; "Your semester report for %s is ready" **new**, "Read the report" **new**; "Your request was decided" **new**, "See your details" **new**; Administrator: "The agreement is not settled" **new**; the whys are today's sentences |
| Choice state | "Choose an institution" (exists); "Pick the school whose page you want to see. Only Administrators see this." **new** |
| Card heads | "%s student" / "%s students", "%s person" / "%s people" **new** (the parts' design 5) |
| Fields | "End date" with Optional, hint "If you know it." **new**; "The institution's name", hint "Exactly as it should print on the agreement." **new**; "Choose a file", "No file chosen", "Cancel invitation" (the parts' design 5) |

Stop printing: "Back to the roster", "Back to the other semesters", "Move %s up" and "Move %s down", "Program updates
and announcements", "Nothing new right now.", "All updates", the three module ledes, and today's resources strings the
menu replaces.

## 13. Releases

**One release: plugin (the next free number on the day, read from live), then the theme the same day.** Built in two
stages, each with its own gate, shipped together:

- Stage A: the frame, the views, the head, the Overview, the menu, the choice state, the guide and updates in the
  frame, `card_url()` and every way back, with `institution` still off (the cards print their old markup inside the
  views). Gate A: the routing and the ways back proven by the suites and by pictures; not shipped.
- Stage B: every card on the parts, `institution` on, the old rules deleted after their search. Gate B: before and
  after at 17 widths, overflow zero, every other surface `same`.

Why not two releases, as the Sponsor did (frame first, parts later):

1. The parts' phone forms under 800px of main area are container queries on the frame's main
   (`container: wpcpm-main`, `dashboard-frame.css` 383). Outside the frame they have no narrow form, so the parts
   cannot ship first; and the old cards inside the frame would need a theme copy of their rules for one release
   only, and would show the audit's defects in the frame beside the Administrator Dashboard's parts (a new look for
   the same kind of thing).
2. Schools are testing today's screen to 20 November: one change after it, one guide rewrite, one set of pictures
   for the owner's yes.
3. The split's safety is kept inside the build: stage A's routing is proven before any markup changes.

## 14. Builder's calls

1. The section arrows go, as the Sponsor Dashboard's did in 1.123.78: with a view per item there is no long page to
   arrange. A school's saved order is left in its option, unused; the move handler goes with the arrows.
2. Enroll students is its own view, not the roster's foot: the Overview's commonest job needs an address, the form
   and its check are the longest block of the page, and on a phone they were five boxes deep.
3. No back links (the owner's rule): on a student's card or a report, the menu's item leads back to the list as the
   reader left it (the roster's filters kept).
4. The roster's four groups keep today's open rule (the guide's words, and a group of forty-two would bury the
   view); the 1.123.80 rule opens the view's other cards. Question 1 asks the owner.
5. An anchor-only old link is moved to its view by the opener from data only this page prints (section 9).
6. Events and For Administrators are views of their own, listed only for the readers who see them today.
7. A signed copy waiting for review is not a "Waits" row: it waits for an Administrator.
8. A report and an answered request wait for 30 days, the Sponsor's window for a term ending.
9. The contact card stays on the Overview whole (name, address, Book a call, sentence); Help also has Write to the
   program, as on the Sponsor Dashboard.
10. The site icon stays 48px beside the name.

## 15. Left to the builder

The menu's icons (`home`, `users`, `profile`, `chart`, `building`, `file`, `calendar`, `eye`, `inbox`, `book`,
`slack`, `email`); the order of the waiting rows; making `default_cohort()` reachable for the figures without a
second copy; the anchor map's shape; each column's width in the check table; the suites' names; the plan, after the
release 1 plan's method; the Institution guide's sections 50 to 52 and the Administrator guide's parts that name the
Institution Dashboard (the menu, the views, no arrows, the Enroll students address, viewing as), built twice.

## 16. Questions for the owner

1. **Should the roster's groups open as every other card does?** The 1.123.80 rule says cards open; the guide and
   the roster say Finished, Not on the program now and any group over twelve start closed. Recommendation: keep the
   roster's rule; open every other card.
2. **Should the Sponsor Dashboard's "Viewing as sponsor" follow this compact control?** Until it does the two
   dashboards differ there. Recommendation: yes, in the Sponsor Dashboard's own parts release, not in this one (its
   surface stays untouched here).
3. **Should the site remember the last school an Administrator chose?** Today nothing is stored; the address carries
   it. Recommendation: no; the choice state ends the wrong-school page without new stored data.

## 17. Not in this release

The Mentor and Student Report Cards; the parts' releases 2 and 3 (wp-admin's lists, the rest of the Sponsor
Dashboard); new screenshots for the Institution guide; any change to the report document's print; a school arranging
its menu; DESIGN.md's "Shared blocks" line, which names the Institution Dashboard's rules as the reference and should
name the parts once this ships (the owner's file, outside this repository).
