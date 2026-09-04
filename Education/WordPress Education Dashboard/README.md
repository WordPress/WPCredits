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
| [`wpcredits-program-manager/`](wpcredits-program-manager/) | The plugin. All of the program logic: roles, access levels, the Airtable sync, the Report Cards, the call calendar, the feedback surveys, email. | 1.92.0 |
| [`wpcredits-theme/`](wpcredits-theme/) | The block theme. The landing page, the branded login, and the skin that turns the plugin's markup into the dashboard people actually use. Frames every page with the [official wordpress.org header and footer](../WordPress.org%20Global%20Header%20and%20Footer). | 1.17.0 |

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
  and contribution teams; links their course; carries the running **hours** total as a box of its
  own, since that is the one number they come back to change; and holds their **report form** — the
  grades, reflections and project write-up they file, grouped as onboarding, project and wrap-up,
  with a different set of fields for each of the two tracks. Four of its answers — WordPress.org
  handle, Slack name, contribution team, personal website — are also rows on the cards, so saving
  the report writes them into both cached copies of the student's row rather than leaving the cards
  a sync behind.
- The **Mentor Report Card** groups their students into *Need a call* (no note in 30 days),
  *Ending soon* (finishing within 60 days) and *On track*, with search across students,
  institutions and teams, per-student notes, and a printable view. Mentors also announce **group
  sessions** here — an office hour several students join, with one note afterwards that lands on
  every attendee's card.

**Feedback surveys.** Three short forms on the Student Report Card — at the start, half way and at
the end — plus an exit survey for anyone who leaves without finishing. The question set is the one
settled in [#123](https://github.com/WordPress/WPCredits/issues/123) after analysing 242
responses: three questions repeat word for word at every stage so a student's answers can be read
as a line rather than three unrelated snapshots, two follow-ups appear only when the answer above
them was poor, and the permissions to quote them publicly or contact them later are fenced off in a
box that says it is optional. Answers land one row per student in Airtable. **Mentors do not see
them** — several of the questions are about the mentor.

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
without ever deleting a user or touching an administrator's roles. **Students refresh every three
hours, mentors once a day** — the student rows carry what people are shown on their cards, while the
mentors run costs one WordPress.org profile read per mentor. A run still in progress is left to
finish rather than restarted. Usernames come from the
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

**Pre-release**, deployed and being tested on a staging site.

- [#158 — prerelease testing](https://github.com/WordPress/WPCredits/issues/158): a call
  for testing, open until **10 August 2026**. Students, mentors, people outside European timezones
  and anyone using a screen reader are all especially welcome.
- [#159 — initial release](https://github.com/WordPress/WPCredits/issues/159): what has to
  be true before the first release, and the open decisions still to make.

## License

GPL-2.0-or-later, matching WordPress.
