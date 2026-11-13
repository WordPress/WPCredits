## Who can read what

Every post and page carries a **Program access** control in the editor sidebar.

{{image:admin-access-level|The access control sits with the post's own settings. Administrators can always read every level.}}

The levels come from the roles, so there is one per audience plus the two ends:

| Level | Who can read it |
| --- | --- |
| Public | Everyone, including logged-out visitors. |
| Student level | Accounts holding `wpcpm_view_student_content`, plus Administrators. |
| Mentor level | Accounts holding `wpcpm_view_mentor_content`, plus Administrators. |
| Institution level | Accounts holding `wpcpm_view_institution_content`, plus Administrators. |
| Sponsor level | Accounts holding `wpcpm_view_sponsor_content`, plus Administrators. |
| Students and mentors | Accounts holding `wpcpm_view_student_content` or `wpcpm_view_mentor_content`, plus Administrators. The level sponsor posts default to; an Administrator may widen a post to Public or narrow it in the post's Program access box. |
| Administrators only | Accounts with `wpcpm_manage_program`. |

A level is read by any account holding the capability its row names, whatever its role. A post's author
can always read it, at any level.

**The levels do not nest.** A mentor holds the mentor capability and nothing else, so a mentor cannot
read a Student-level page. That is why documentation written for mentors has to repeat what students
are told rather than link to it.

Gating is applied in four places, so there is no back way in: front-end listings filter restricted
posts out, direct URLs send logged-out visitors to the login form and logged-in ones to an
explanation, the rendered content and excerpt are filtered, and the REST API is filtered too.

### Program updates and announcements

The **Program updates and announcements** column, in the Resources section near the top of both the
Student Report Card and the Mentor Report Card, lists up to five recent posts from the *Updates*
category, filtered by the same access levels. Each card lists Public posts and posts at its own level;
the Student and Mentor Report Cards also list *Students and mentors* posts, so a post set to Mentor
level appears on the mentor's card and on no other. Set the access level on the post and it lands in
the right place; there is nothing else to configure.

The Administrator Dashboard's column lists private posts too: a post in *Updates* whose visibility
is *Private* and whose access level is *Administrators only* appears there for the Administrators,
and on no other card. The biweekly site update is one. A
private post is never emailed to the site's subscribers or shared outside the site, which is why the
update is private rather than published.

An update for sponsors, at the *Sponsor level*, opens for an Administrator in the Sponsor Dashboard's
frame, as it does for a sponsor. Opened from **Program updates** while viewing a sponsor, its menu and the
list beside it keep that sponsor.

### Viewing a page as its owner

The Student Report Card, the Mentor Report Card, the Institution Dashboard and the Sponsor Dashboard
each open, for an Administrator, with a switcher above the page whenever there is more than one to
choose from: **Viewing as student**, **Viewing as mentor**, **Viewing as institution** or **Viewing as
sponsor**. Pick a name in it and press **Show** to open that person's or that organization's page.
Only Administrators are shown the switcher, and the note with it says so.

The switcher is one field, showing the name you are viewing until you pick another. Click it, or
press Down, and the whole list opens under it, with the name in the field highlighted. The list runs
A to Z without regard to capitals or accents, so Álvaro is among the A's, with numbers read as
numbers, so Student 2 comes before Student 10, and with a space before any letter, so Teo
Polytechnic comes before Teodora School. Type in the field and the list narrows to the names that
hold what you typed, anywhere in the name and again without regard to capitals or accents, so
"alvaro" finds Álvaro, with the first match highlighted; the field's placeholder, **Find a
student**, **Find a mentor**, **Find an institution** or **Find a sponsor**, shows while it is
empty. Up and Down move through the list, and Home and End go to its first and last names. Press
Enter, or click a name, to pick it: the field shows it, and **Show** opens its page; picking alone
opens nothing. Click the field again, press Escape or Tab, or click anywhere else, and the list
closes with the name you picked last back in the field. When no name matches, the list says so. Come
back to a page with your browser's Back button and the field shows the name that page is about,
which is the one **Show** opens. Enter in the field never sends the form, and a screen reader hears
how many names the list shows. In a browser running no JavaScript the field is not shown, and the
plain sorted list works on its own.

### The details card on an institution's page

At the head of the **Representatives and agreement** module on an institution's Institution Dashboard,
above the people who act for it and its Collaboration Agreement, is the card **Your institution's
details**, with the line "What the program has on record." It is drawn to every account of an
institution whose agreement is settled and to an Administrator on any institution's page, opened through
the switcher or reached as the page you land on. An institution whose agreement is not settled has no
card: its accounts see the agreement panel alone, where they set its name on the agreement document.

The details read as rows under two small headings, in this order. **Contact and agreement**: the contact
person, their role, who signs the agreement, their title, the languages the students study in, and the
institution's page about WordPress Credits. **Name and address**: the name, the website, the city, the
country and the contact's email. A detail the program holds nothing for reads "Not given yet".

Under each list a closed section opens the form for that group alone, with the application's own
questions, labels and help, and the application's rules:

- **Correct these** (saved at once) is under the first list. Saving writes the contact person to the
  Institutions table in Airtable and to the program records here, and the other answers to the
  institution's record on this site. It adds a line to the institution's history that names the details
  changed and none of their values, emails the people **Who is emailed about institutions** names what
  changed, from what to what, and says "Your institution's details are saved."
- **Ask to correct these** (an Administrator checks first) is under the second. A member's press on **Send
  to an Administrator** writes nothing: it raises a request, shown on the Administrator Dashboard's
  *Requests from institutions* card, emails the same people with a link to it, and says "Sent: an
  Administrator checks the change, and you will see it here once it is decided." An Administrator is
  drawn this second form as **Correct these** (saved at once), with **Save the details**, as the first:
  their press saves at once, in either form, and emails as a save does, since an Administrator is the one
  who checks, and nobody approves their own change. While the school's request waits, an
  Administrator is offered no second form either: under the second list they read "A request from the
  school is waiting. Decide it in Requests from institutions on the Administrator Dashboard.", linked to
  that card while the page is published, and a post of that form is refused with the same sentence. The
  first form stays open to everyone allowed.

Below the two lists the card has a **Tracks** part, and for Administrators an **Assigned Administrator**
part after it. Its row **Tracks offered** gives the tracks it offers that the site runs, by
name, or "None listed yet.". It is set by Administrators alone. An Administrator is drawn a checkbox for
each track the site runs, **Save tracks**, and the line "Students are asked which track they want when
two or more are ticked."; an account of the institution reads the list and "The program sets the tracks
your institution offers. To change them, write to" the program's contact address, with no form. A save
adds a line to the institution's history: "Tracks offered were set:" and the names, or
"Tracks offered were cleared.". The list is kept on
this site and never written to Airtable. A track unpublished in the Track Builder drops out of the list
and of the question, and comes back, still ticked, when it is published again. With two or more ticked,
the registration form at `/student-registration/` asks a student of the institution "Which track would
you like to join?", required, with the ticked tracks and **Not sure yet**; with fewer, it asks nothing.
The answer is written to the Students table's **Requested track** column (the track's name as the
student saw it, or "Not sure yet") and shows under **Answers and consent** on the Registrations tab. If
the column is missing, the row is written without it and the registration says it was left out. The
answer is a request: an Administrator still assigns the track, and Status stays empty.

While a request waits, every account of the institution reads, under each row it would change, "Waiting
for an Administrator:", what was asked and the day it was asked, with **Withdraw it**; an Administrator
reads the same lines without the button, since the request is decided on the Administrator Dashboard. The
second list then offers no form, because an institution has one request at a time. A second account, or a second tab,
that asks meanwhile is told "One change is already waiting for an Administrator." and nothing is made.
Once the request is decided, every account of the institution reads its outcome until the next request is
sent: "Updated on" and the day after an approval; "An Administrator did not make the change you asked for"
after a decline, with a colon and the Administrator's note when there is one; nothing after a withdrawal. Withdraw it brings the account back to this card.

A form posted with nothing changed says "Nothing was changed." and sends no email; a value posted back as
it was drawn is not a change, even one the program holds in a form the application would not accept. A
refused post gives back what was typed, with the problem under the field that has one, and a text that
WordPress would take words from is refused whole, as on the application. If Airtable refuses the contact
person's save, nothing is saved on this site either, the typed values come back, and the card says
"The program records could not be updated, so nothing was saved. Try again in a while." The card
never names Airtable to the institution.

A save at once is made under a lock of the institution's own, the option
`wpcpm_req_lock_details_<record>` its request is raised under, with what the program has on record read
again under it. Two tabs, or two accounts, posting the same correction save it once: the second is told
"Nothing was changed." and writes, emails and records nothing. A save that finds the lock held, and a
request sent while it is held, is told "Someone else is saving your institution's details at this
moment, so nothing was done. Try again in a moment.", saves or makes nothing, and gives back the typed
values; "One change is already waiting for an Administrator." is for a request that truly waits. The
forms leave every rule to the site, so a box the browser would refuse, such as a stored contact's email
that holds two addresses, does not stop a post of the rest.

### A student's card on an institution's page

An institution's accounts open a student's card from the roster, and an Administrator opens the same card
through the switcher. The card shows the student's name and program, whether they have signed in yet, the
internship dates and cohort, the field of study, the tutor, the WordPress.org profile, the contribution
teams and the personal website, then the mentor, the Student Report Card (read only, fetched when it is
opened) and a link that downloads the student's details and course grades as a CSV file.

A mentor is optional in the program, and a student without one reads as normal. The **Mentor** row says
"No mentor yet", in the roster's words, and the **Mentor email** row is left out, since there is nobody to
write to. A mentor known by name and not by address keeps the row, which then reads "Not set". The card
asks for no mentor and offers no way to ask for one.

Under the Student Report Card, a closed section named **Correct this student's details** holds the form
that corrects five details: the name on the Student Report Card, the full name, the start date, the end
date (at most a year after the start) and the field of study, which must be one of the program's own
choices. It is shown to every account of the student's institution and to an Administrator, and to
nobody else; a student with no progress record yet has no Student Report Card and no section. Saving
writes to the program records at once, through a live read that places the student with the institution,
and adds a line to the institution's history. When a value is refused the section names what was refused
and says whether the rest was saved, and the form comes back with what was typed and not saved, for the
account that typed it, while what was saved shows as the program records now hold it. The section opens
by itself when the last save left something to say, so its sentence is not hidden in a closed section.
Nothing on this card asks for a mentor or keeps notes. A mentor request already on record is still on
the Administrator Dashboard's *Requests from institutions* card, where an Administrator closes it as
before.

### The For Administrators section of an institution's page

At the foot of an institution's Institution Dashboard, under its sections and above the line that says
when its records were last read, an Administrator is shown **For Administrators**, with the line "Only
Administrators see this section." It is drawn on any institution's page, opened through the switcher or
reached as the page you land on. Nobody who acts for the institution sees it, or anything in it, whatever
state their agreement is in. It holds two folded cards.

**From the application** lists the answers the institution's application gave that Airtable has no column
for, each under the question it was asked: the contact's role, the languages the students study in, who
will sign the agreement, their title, and the institution's page about WordPress Credits. They are kept
when the application is approved and stay after the application itself is removed. An institution approved
before 1.123.29 has none, and the card says "Nothing kept from the application."

**History** lists the latest 100 things that happened to the institution, newest first. Each row gives
when, in the site's date and time formats; who, as the account's name, "The site" when the site did it
itself, as when an invitation expires, or "A removed account" when the account is gone; and what happened,
in a plain sentence. It covers the institution's members and invitations, with the address each invitation
went to, its application and its agreement from the template being generated to a revoke, which also gives
the note it was revoked with after "Note:", its details (the corrections it saved, and its requests to
correct them), its requests, its imports, the changes made to its students, and the roster's refusals; a
line about a note on a student, if the history holds one, reads "A note about a student was added." or "A
note about a student was deleted." When there are more than 100, the card says "Showing the latest 100."
An institution with nothing recorded says "Nothing has been recorded for this institution yet."

A row reads in today's words whatever the day it was written. A member row names the account as it is
called now, "A former member" when the account is gone, says that it was added as a member or is no longer
one, and then says how it joined or left in the words of the People card ("Added by an Administrator",
"Joined by invitation", "Their access was removed", "They removed their own access"), never the code the row
was stored with. A request that was closed is worded by what became of it: "An Administrator closed a request
as handled.", "An Administrator declined a request.", "An Administrator approved a change to the
institution's details." or, for one taken back, "The institution withdrew a request." (or "An Administrator
withdrew a request." when an Administrator withdrew it), with the Administrator's note after it when the note
can be shown, and the outcome alone when it cannot. A school's request to correct its details reads "The
institution asked to correct its details.", and a correction it saved "Details were corrected:" and the names
of the details. A line written before the Administrators were named is read in the words used now.

### Arranging the Student Report Card

Under a student's profile and mentor columns the Student Report Card is a stack of up to six modules:
**Your first weeks** (for a new student only), **Program updates and resources**, **My course** (**My
hours** when there is no course to open), **Report form and feedback forms**, **My mentor call**, and
**Tools from our sponsors** when its setting on the Sponsors tab of Settings shows it and the student
has offers or claimed tools. A module with nothing to show is left out and has no arrows. Each carries two small arrows at its top right,
and pressing one moves the module up or down at once, the way the block editor moves blocks. The
order belongs to the student: they arrange their own card, and it stays as they left it. When you
open a student's card through the switcher you see their order and can arrange it for them with the
same arrows; there **Tools from our sponsors** is at most one line, saying how many tools they claimed. Nobody else sees the arrows.

### A student's or a mentor's own language

At the top right of the Student Report Card and the Mentor Report Card, above the title, a student or a
mentor sees a small language switcher: the flag and the name of the language the page is in. Pressing it opens a
short list, and pressing a language there keeps it; the page comes back in that language, and the
emails the site sends that person are in it from then on.
The choice is kept on their account, where WordPress keeps an account's language, and is
theirs alone. Administrators are never shown the language switcher, on their own page or on a page they
open for somebody else, and you read the page in your own language.

The list holds only the languages the plugin has a translation for, each named in itself, with the
site's own language first. While there is no translation the switcher is not shown at all, so
nothing changes on a site that has none. Translators are given what students and mentors read, so
a sentence only an Administrator, an institution or a sponsor sees stays in English, and so does
anything not translated yet. A language whose translation a later version takes out goes back to
the site's language by itself.

There is no setting. A translation arrives with a version of the plugin, and the language is on the
list from then on. Dates and WordPress's own words follow too once that language is installed
under Settings > General, in the Site Language list (installing it does not change the site's
language).
A flag is the flag of the country the translation is written for; a language that belongs to no
one country is listed by its name. Where a system draws no flags (Windows), the country's two
letters stand in its place.

### Arranging an institution's page

The Institution Dashboard is three sections, each with two small arrows at its top right, named after the
section, for example "Move Students up". The order belongs to the institution and is the same for everyone
who signs in for it. When you open the page through the switcher you see that institution's order and can
arrange it for them with the same arrows, and your move is theirs from then on. A section with nothing to
show is left out and has no arrows. Only the institution's representatives and Administrators are shown the
arrows; a tutor sees the page in its order but cannot move anything.

The Sponsor Dashboard has nothing to arrange since 1.123.78: it is drawn in the dashboards' frame, with a
menu down the side and a view for each of its parts, **Overview**, **Your sponsorship** (while "Sponsor
tiers" is on), **Offers**, **Students reached**, **Mentors to sponsor**, **Your company** and **Agreement**,
each at its own address. Opened through the switcher, it keeps the sponsor you chose as you move between
views, and the switcher keeps the view when you choose another sponsor. Its Sponsor guide and the updates a
sponsor reads open in the same frame for the people who act for a sponsor; you read the Sponsor guide there
too.

### Group sessions, from a student's card or a mentor's

On a student's card, opened through the switcher, the group sessions they are on are listed under
*My mentor call*, and each one that has not started offers **Take them off the session**. It asks
first, then takes the student off, gives their place back and emails them a file that takes the
session out of their calendar, as their own **Leave the session** would.

On a mentor's card you can change or cancel their group sessions as they can. When you move one, the
mentor is emailed as well as the students on it, with an invitation that moves it in their calendar.
