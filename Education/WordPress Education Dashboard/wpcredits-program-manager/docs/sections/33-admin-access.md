## Who can read what

Every post and page carries a **Program access** control in the editor sidebar.

{{image:admin-access-level|The access control sits with the post's own settings. Administrators can always read every level.}}

The levels come from the roles, so there is one per audience plus the two ends:

| Level | Who can read it |
| --- | --- |
| Public | Everyone, including logged-out visitors. |
| Student level | Accounts holding `wpcpm_student`, plus Administrators. |
| Mentor level | Accounts holding `wpcpm_mentor`, plus Administrators. |
| Institution level | Accounts holding `wpcpm_institution`, plus Administrators. |
| Sponsor level | Accounts holding `wpcpm_sponsor`, plus Administrators. |
| Students and mentors | Accounts holding `wpcpm_student` or `wpcpm_mentor`, plus Administrators. The level sponsor posts default to; an Administrator may widen a post to Public or narrow it in the post's Program access box. |
| Administrators only | Accounts with `wpcpm_manage_program`. |

**The levels do not nest.** A mentor holds the mentor capability and nothing else, so a mentor cannot
read a Student-level page. That is why documentation written for mentors has to repeat what students
are told rather than link to it.

Gating is applied in four places, so there is no back way in: front-end listings filter restricted
posts out, direct URLs send logged-out visitors to the login form and logged-in ones to an
explanation, the rendered content and excerpt are filtered, and the REST API is filtered too.

### Program updates and announcements

The column at the foot of both the Student Report Card and the Mentor Report Card lists recent posts
from the *Updates* category, filtered by the same access levels - so a post set to Mentor level
appears on the mentor's card and on nobody else's. Set the access level on the post and it lands in
the right place; there is nothing else to configure.

The Administrator Dashboard's column lists private posts too: a post in *Updates* whose visibility
is *Private* and whose access level is *Administrators only* appears there for the Administrators,
and on no other card. The biweekly site update is one, filed under *Site updates* as well; the link
to it in the Administrators' Slack channel opens it for anybody signed in as an Administrator. A
private post is never emailed to the site's subscribers or shared outside the site, which is why the
update is private rather than published.

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

While a request waits, every account of the institution reads, under each row it would change, "Waiting
for an Administrator:", what was asked and the day it was asked, with **Withdraw it**; an Administrator
reads the same lines without the button, since the request is decided on the Administrator Dashboard. The
second list then offers no form, because an institution has one request at a time. A second account, or a second tab,
that asks meanwhile is told "One change is already waiting for an Administrator." and nothing is made.
Once the request is decided, every account of the institution reads its outcome until the next request is
sent: "Updated on" and the day after an approval; "An Administrator did not make the change you asked for:"
and the note after a decline; nothing after a withdrawal. Withdraw it brings the account back to this card.

A form posted with nothing changed says "Nothing was changed." and sends no email; a value posted back as
it was drawn is not a change, even one the program holds in a form the application would not accept. A
refused post gives back what was typed, with the problem under the field that has one, and a text that
WordPress would take words from is refused whole, as on the application. If Airtable refuses the contact
person's save, nothing is saved on this site either, the typed values come back, and the card says
"The program records could not be updated, so nothing was saved. Please try again in a while." The card
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

Under a student's profile and mentor columns the Student Report Card is a stack of modules: the
**program updates with the resources**, **My course** (**My hours** when there is no course to open),
the **report form with the feedback forms**, **My mentor call**, and **Tools from our sponsors** when
its setting on the Sponsors tab of Settings shows it. Each carries two small arrows at its top right,
and pressing one moves the module up or down at once, the way the block editor moves blocks. The
order belongs to the student: they arrange their own card, and it stays as they left it. When you
open a student's card through the switcher you see their order and can arrange it for them with the
same arrows. Nobody else sees the arrows.

### Group sessions, from a student's card or a mentor's

On a student's card, opened through the switcher, the group sessions they are on are listed under
*My mentor call*, and each one that has not started offers **Take them off the session**. It asks
first, then takes the student off, gives their place back and emails them a file that takes the
session out of their calendar, as their own **Leave the session** would.

On a mentor's card you can change or cancel their group sessions as they can. When you move one, the
mentor is emailed as well as the students on it, with an invitation that moves it in their calendar.
