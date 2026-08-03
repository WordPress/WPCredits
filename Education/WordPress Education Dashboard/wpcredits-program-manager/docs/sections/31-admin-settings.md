## Settings

**WPCredits Program → Settings**. One screen, in sections.

{{image:admin-settings|The Airtable connection and the Mentors module, at the top of the settings screen.}}

### Airtable connection

| Setting | What it is for |
| --- | --- |
| **Personal Access Token** | Stored in the database and never sent back to the browser. Leave blank to keep the current token. |
| **Base ID** | The Airtable base holding the program. |
| **Mentors table** | Where mentor records live. |
| **Students Reports table** | Internship dates, links and contribution team — what the mentor page shows. |
| **Students table** | Read only for the Tutor column, which does not exist on Students Reports. |

### Linked tables

Airtable sends linked-record fields as record IDs rather than names, so two more tables are read to
turn those IDs into names: **Institutions** and **Contribution areas**. Each has a name-column
setting, used only when the token lacks the `schema.bases:read` scope — with that scope the primary
column is detected automatically.

### Mentors module

- **Mentor status to sync** — only mentors holding this Airtable status get an account.
- **Currently mentoring** — one status per line. Students holding any of these appear under
  "Currently mentoring" on their mentor's page.
- **Past students** — statuses that mean mentoring has finished. Those students appear in a separate,
  collapsed section. Leave empty to show only current students; a status in both boxes counts as
  current.
- **When a mentor is no longer active** — remove the Mentor role and clear their student list, or
  leave the role in place. The account itself is never deleted either way.
- **Invitation emails** — off by default, and worth leaving off: a first sync creates around ninety
  accounts at once. Invitations are queued and sent a few at a time rather than all inside the sync,
  so a mail limit cannot swallow half of them unnoticed. You can also invite people one at a time
  from the Mentors and Students screens.
- **Daily sync** — sync automatically once a day.
- **Mentor landing page** — send mentors to their Report Card on login and in place of the wp-admin
  Dashboard, with a toolbar link. They keep their own profile screen, and a mentor who followed a
  link somewhere specific still lands there. Administrators are unaffected.

### Students module

Uses the same status lists as the Mentors module — a current student is anyone a mentor is currently
mentoring.

- **When a student leaves the program** — remove the Student role, so they lose access to
  Student-level content, or leave it in place. The account is never deleted, and their program
  details are kept.
- **Student landing page** — the same arrangement as for mentors, pointing at the Student Report Card.

### Need help?

The question box over the WordPress documentation.

- **Switch it on** — off means the box answers nobody, the header button disappears and the page it
  lives on is unpublished. Nothing is deleted, so switching it back on restores all of it.
- **Answer provider** and **API key** — leaving the provider as *None* keeps everything on this site
  and means there are no answers at all. Choosing one sends each question, and the extracts that
  match it, to that company.
- **Model** — leave as `gemini-flash-latest` unless you have a reason not to. It is an alias that
  always points at the current Gemini Flash, so it cannot be retired out from under the site.
- **Who can ask** — mentors and program managers by default; optionally students and institutions as
  well, anybody logged in, or program managers only. Never anybody logged out, whatever this says.
- **Questions per person per hour** — so a free tier cannot be spent in an afternoon. `0` removes the
  limit.

### Mail

A sample-invitation sender — the student and mentor invitations say different things, so you can send
yourself either — and a log of recent mail: bookings, cancellations, reminders and invitations.
"Accepted" means the site handed the message off without complaint; it cannot tell you the message was
delivered or read.
