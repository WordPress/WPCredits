## Who can read what

Every post and page carries a **Program access** control in the editor sidebar.

{{image:admin-access-level|The access control sits with the post's own settings. Administrators can always read every level.}}

The levels come from the roles, so there is one per audience plus the two ends:

| Level | Who can read it |
| --- | --- |
| Public | Everyone, including logged-out visitors. |
| Student level | Accounts holding `wpcpm_student`, plus administrators. |
| Mentor level | Accounts holding `wpcpm_mentor`, plus administrators. |
| Institution level | Accounts holding `wpcpm_institution`, plus administrators. |
| Sponsor level | Accounts holding `wpcpm_sponsor`, plus administrators. |
| Students and mentors | Accounts holding `wpcpm_student` or `wpcpm_mentor`, plus administrators. The level sponsor posts default to; a manager may widen a post to Public or narrow it in the post's Program access box. |
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

### Arranging the Student Report Card

Under a student's profile and mentor columns the Student Report Card is a stack of modules: the
**program updates with the resources**, **My course**, the **report form with the feedback forms**,
**My mentor call**, and **Tools from our sponsors** when the Sponsors module is on. Each carries two
small arrows at its top right, and pressing one moves the module up or down at once, the way the
block editor moves blocks. The order belongs to the student: they arrange their own card, and it
stays as they left it. When you open a student's card through the switcher you see their order and
can arrange it for them with the same arrows. Nobody else sees the arrows.
