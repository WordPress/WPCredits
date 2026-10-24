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
the note it was revoked with after "Note:", its requests, the notes about its students, its imports, the
changes made to its students, and the roster's refusals. When there are more than 100, the card says
"Showing the latest 100." An institution with nothing recorded says "Nothing has been recorded for this
institution yet."

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
