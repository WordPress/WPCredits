# WordPress Education Dashboard

The member-facing site for the **WordPress Credits Program**: a private, signed-in dashboard where
students see their own placement and book calls with their mentor, and mentors see the students
assigned to them.

The program has been run out of Airtable. Airtable stays the system of record — this is the front
end for the people in it, who previously had no way to see their own placement without asking
somebody to look it up for them.

Two components live here, and they are built to work together:

| Folder | What it is | Version |
| --- | --- | --- |
| [`wpcredits-program-manager/`](wpcredits-program-manager/) | The plugin. All of the program logic: roles, access levels, the Airtable sync, the Report Cards, the call calendar, email. | 1.42.0 |
| [`wpcredits-theme/`](wpcredits-theme/) | The block theme. The landing page, the branded login, and the skin that turns the plugin's markup into the dashboard people actually use. | 1.8.1 |

The theme is the front end for the plugin and does not add program data or behaviour of its own.
The plugin works without it — the pages are just unstyled.

---

## Who it is for

Five audiences, each a WordPress role cloned from Subscriber plus one marker capability:

- **Students** — their own placement, their mentor, their calls, their course and report form.
- **Mentors** — the students assigned to them, triaged by what needs attention, with private notes.
- **Institutions** — role and screen reserved; no institution-facing features yet.
- **Sponsors** — role and screen reserved; no sponsor-facing features yet.
- **Administrators** (program managers) — everything, plus the sync, the settings and the tools.

Role slugs are deliberately prefixed (`wpcpm_student`, not `student`): bare `student` and `teacher`
are commonly claimed by LMS plugins, and sharing a role slug means silently sharing its capability
set.

## What it does

**Report Cards.** One page per audience, rendered against the logged-in user rather than one page
per person, so there is no URL to guess at somebody else's record.

- The **Student Report Card** shows their program and track, internship dates, institution, tutor
  and contribution team; lets them correct their own WordPress.org handle, Slack name, contribution
  team and personal website, writing the change straight back to Airtable; and links their course
  and their prefilled report form.
- The **Mentor Report Card** groups their students into *Need a call* (no note in 30 days),
  *Ending soon* (finishing within 60 days) and *On track*, with search across students,
  institutions and teams, per-student notes, and a printable view.

**Per-post access levels.** Every post and page carries a *Program access* level — Public, or one
audience's level, or Administrators only — enforced in four places, because each covers a hole the
others do not: the query filter hides gated posts from listings, a template guard stops direct URL
access (explaining itself rather than 404ing), a content filter covers anything rendering a post
outside a gated query, and a REST filter covers the same ground for the block editor and any
headless read.

**Call calendar.** A mentor publishes weekly availability — their hours, call length, shortest
notice, how far ahead, bookings per student, days off, meeting link — and students book from the
slots it opens. Times are stored in the mentor's timezone, offered in the student's, and both
parties get an email with a calendar invite attached plus a reminder 24 hours before. Either can
cancel, which puts the slot back on the calendar and tells the other.

**Need help?** A question box, signed-in only, that answers from the WordPress documentation —
wordpress.org, make.wordpress.org, learn.wordpress.org and developer.wordpress.org — and shows
which pages it read. There is no local copy of the documentation: the model searches, and every
citation is checked against those sites. An answer that cites nothing recognisable is shown but
marked as unconfirmed.

**Updates.** Announcements are ordinary posts in an `updates` category, listed on each Report Card
and filtered by that card's audience, so a mentor announcement never appears on a student's page.

**Program guides.** Three guides — [student](wpcredits-program-manager/docs/students.md),
[mentor](wpcredits-program-manager/docs/mentors.md) and
[program manager](wpcredits-program-manager/docs/administrators.md) — assembled from one set of
sections by `bin/build-docs.php`, which emits both the Markdown in this repository and the block
markup for the published pages. Each guide repeats what the narrower one says rather than linking
to it, because the access levels do not nest: a mentor cannot open a Student-level page, so a
cross-link would land them on the restricted notice.

Every screenshot in those guides is a **mockup**, rendered from the plugin's own markup and the
theme's stylesheets with invented people. None is a capture of a live Report Card — real ones carry
students' names, email addresses, photographs and call notes.

**Airtable sync.** A resumable cron state machine provisions accounts and refreshes records
without ever deleting a user or touching an administrator's roles. Usernames come from the
WordPress.org profile where there is one and the email local part where there is not. A mentor who
stops being Active loses the role and their student list, and no account is ever removed.

## Requirements

- WordPress **6.6+** (the theme's `theme.json` is v3; the plugin alone needs 6.5+)
- PHP **7.4+**
- An Airtable personal access token with `data.records:read`, plus `data.records:write` for the
  Mentor Status Checker and `schema.bases:read` for field descriptions (both optional — the plugin
  degrades rather than stopping)
- A Google AI Studio API key, if the "Need help?" module is switched on

## Installing

Both folders are plugin/theme source, so they can be zipped and installed as they are:

```bash
cd "Education/WordPress Education Dashboard"
zip -rq wpcredits-program-manager.zip wpcredits-program-manager -x '*/.DS_Store' '*/bin/*'
zip -rq wpcredits-theme.zip wpcredits-theme -x '*/.DS_Store' '*/bin/*'
```

Then install the plugin, activate it — activation creates the pages and registers the roles — and
activate the theme. Add the Airtable token under **WPCredits Program → Settings** and run a sync.

## Development

Test suites live in `wpcredits-program-manager/bin/` and run against a stubbed WordPress, so they
need nothing installed:

```bash
cd wpcredits-program-manager
for t in bin/test-*.php bin/check-references.php; do php "$t" || break; done
```

Two are worth knowing about specifically. `check-references.php` resolves every `self::` and
`WPCPM_X::` reference against what the class actually declares — a constant referenced with
`self::` but declared on another class is a fatal that `php -l` cannot see, and one shipped
undetected for four releases. `test-handlers.php` executes every `admin-post` handler against
stubbed WordPress, because a harness that only tests pure functions proves nothing about the
handlers, and the handlers are where the fatals are.

Coding standard is WordPress-Core via `phpcs`. Translations are regenerated with
`bin/make-pot.sh` in each folder.

## Status

Pre-release. Deployed and being tested on a staging site; see the open issues in this repository
for what is being tested and what is left before the first release.

## License

GPL-2.0-or-later, matching WordPress.
