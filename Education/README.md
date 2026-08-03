# Education

This directory holds the **software** built for the WordPress education programs — the sites,
plugins, themes and brand assets that the WordPress Credits contribution program, WordPress Campus
Connect, Student Clubs and the Community team's event work run on.

The rest of this repository is the program itself: how it works, who it is for, the mentor and
student handbooks, the pitch materials and the public
[WPCredits dashboard](https://wordpress.github.io/WPCredits-Tracker/). This directory is where the
tooling behind it lives.

Each subfolder is a **source mirror**: unpacked, installable plugin and theme source, kept in sync
with its own development repository. No build artifacts, no `.git`, no credentials. Every folder can
be zipped and installed on a WordPress site as it stands.

## What's in here

| Folder | What it is | Status |
| --- | --- | --- |
| [`WordPress Education Initiatives`](./WordPress%20Education%20Initiatives) | The **public site**: one block theme plus five plugins that present the education programs and surface live program data. | Pre-release — [#160](https://github.com/WordPress/WPCredits-Tracker/issues/160), [#161](https://github.com/WordPress/WPCredits-Tracker/issues/161) |
| [`WordPress Education Dashboard`](./WordPress%20Education%20Dashboard) | The **member-facing dashboard**: a plugin and its companion theme giving Credits students and mentors a signed-in view of their own placement, their calls and their report cards. | Pre-release, on staging — [#158](https://github.com/WordPress/WPCredits-Tracker/issues/158), [#159](https://github.com/WordPress/WPCredits-Tracker/issues/159) |
| [`WordPress Community CRM`](./WordPress%20Community%20CRM) | The Community team's **internal CRM**: a block theme whose front page is the sign-in screen, plus a plugin bringing a FreeScout help desk onto the Jetpack CRM contact record. | Pre-release — [#162](https://github.com/WordPress/WPCredits-Tracker/issues/162) |
| [`WordPress Education Media Kit`](./WordPress%20Education%20Media%20Kit) | The **media kit**: logos, partner badges, quotes and paste-ready boilerplate for institutions, sponsors and clubs announcing their involvement. A single self-contained HTML page, [published here](https://wordpress.github.io/WPCredits-Tracker/Education/WordPress%20Education%20Media%20Kit/). |  Published |

Each of the first three folders has its own README with the full component list, versions,
requirements and install steps. Start there — the summaries below are only meant to tell you which
one you want.

### WordPress Education Initiatives — the public site

The outward-facing site for all of the education programs. A Full Site Editing theme
(`wordpress-education-blocks`) carries the design and the page structure; five plugins drop live
data and interactive pieces into it:

- **Education Programs Map** — a world map of WPCC, WPCredits and Student Club activity by city,
  with an admin screen for managing institutions.
- **WPCredits-Tracker** — the program dashboard (scale, growth, partner map, contributions, student
  voices) rendered natively in WordPress instead of an embedded iframe.
- **Student Impact** — the top graduating students ranked by contribution impact, contributions and
  logged hours, plus a class-wide stats summary.
- **Contributor Team Matcher** — a quiz that points a new contributor at the contribution team that
  fits their interests.
- **Credits Program Mentors** — the public sponsored-mentors directory.

The data-driven plugins sync from Airtable and profiles.wordpress.org on a schedule.

### WordPress Education Dashboard — the private dashboard

Where people in the Credits program see their own record. Airtable remains the system of record;
this is the front end for the people in it, who previously could not see their own placement without
asking someone to look it up.

`wpcredits-program-manager` holds the program logic — five prefixed Subscriber-based roles
(students, mentors, institutions, sponsors, administrators), per-post access levels, Report Cards
rendered against the logged-in user, a timezone-aware call calendar, email, and a resumable Airtable
sync that provisions accounts but never deletes one. `wpcredits-theme` is its front end: the landing
page, a branded `wp-login.php`, and the skin that turns the plugin's markup into a usable dashboard.
The plugin works without the theme; the pages are just unstyled.

The plugin's `docs/` folder also holds the three program guides — student, mentor and program
manager — generated from one set of sections by `bin/build-docs.php`.

### WordPress Community CRM — the internal CRM

Not an education program, but the same stack and the same team. A small assembly on top of
[Jetpack CRM](https://wordpress.org/plugins/zero-bs-crm/) for tracking the people, organizations and
sponsors around meetups and WordCamps: `community-crm` is a block theme whose homepage doubles as
the sign-in screen, and `jpcrm-freescout` brings a FreeScout help desk into `wp-admin` over its REST
API, adding a Support Tickets tab to every contact record. Jetpack CRM itself is third-party and is
not mirrored here.

### WordPress Education Media Kit — the brand assets

A single-page kit for anyone announcing a partnership, event or club: initiative logos, "proud
partner" badges, approved quotes and boilerplate copy. It is one self-contained HTML file with its
assets inlined, so it works offline, and an `index.html` redirect makes it openable as a directory
URL on GitHub Pages.

## How to use this directory

**To install something.** Open the relevant subfolder's README, then zip the plugin or theme folder
and upload it through **Plugins → Add New → Upload** or **Appearance → Themes → Add New**. Nothing
here needs a build step.

**To test something.** Each project has an open call-for-testing issue, linked in the table above.
That is the right place for feedback.

**To change something.** These are mirrors. Edits are made in each component's own development
repository and re-synced here, so a patch applied directly to this directory will be overwritten.
Open an issue instead, or say so in the testing thread.

## Requirements

WordPress **6.6+** and PHP **7.4+** covers everything here (individual components are looser — the
dashboard theme's `theme.json` v3 is what sets the 6.6 floor). `jpcrm-freescout` additionally needs
Jetpack CRM 5.0+ and a FreeScout install with the API & Webhooks module enabled.

## Credentials and privacy

Several plugins read from Airtable, profiles.wordpress.org, and — for the dashboard's optional
"Need help?" module — a Google AI Studio key. **No API credentials are stored in this source.** They
are configured per site through settings screens or site constants, and are never committed here.

Documentation screenshots in the dashboard guides are mockups rendered from the plugin's own markup
with invented people, never captures of live Report Cards, which carry students' names, email
addresses, photographs and mentor call notes.

## License

GPL-2.0-or-later, matching WordPress.
