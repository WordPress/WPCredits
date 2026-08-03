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
| Administrators only | Accounts with `wpcpm_manage_program`. |

**The levels do not nest.** A mentor holds the mentor capability and nothing else, so a mentor cannot
read a Student-level page. That is why documentation written for mentors has to repeat what students
are told rather than link to it.

Gating is applied in four places, so there is no back way in: front-end listings filter restricted
posts out, direct URLs send logged-out visitors to the login form and logged-in ones to an
explanation, the rendered content and excerpt are filtered, and the REST API is filtered too.

### Program updates and announcements

The column at the foot of both Report Cards lists recent posts from the *Updates* category, filtered
by the same access levels — so a post set to Mentor level appears on the mentor's card and on nobody
else's. Set the access level on the post and it lands in the right place; there is nothing else to
configure.
