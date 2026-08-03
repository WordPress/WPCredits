# WordPress Education Initiatives

**WordPress Education Initiatives** is the suite of WordPress plugins and the block
theme that power the public site and back-office tooling for the WordPress
education programs — WordPress Campus Connect (WPCC), the WordPress Credits
contribution program, Student Clubs, and the certification path that ties them
together.

This folder is a source mirror of that suite. Each subfolder is the unpacked,
installable source of one plugin or theme, kept in sync with its development
repository.

## What's in here

### Theme

| Folder | Name | Version | What it does |
| --- | --- | --- | --- |
| [`wordpress-education-blocks`](./wordpress-education-blocks) | **WordPress Education Initiatives** | 1.5.0 | Full Site Editing (block) theme — the front end of the initiative. The header, footer, front page and every section are edited in the WordPress Site Editor, with global colours/typography in Styles. Ships block patterns for the hero, feature highlights, animated statistics, programs, campus, resources, testimonials, career paths, an expandable career-path timeline, a "why contribution matters" panel, and the call-to-action. Includes a WordPress Playground blueprint for one-click, in-browser previews. |

### Plugins

| Folder | Name | Version | What it does |
| --- | --- | --- | --- |
| [`contributor-team-matcher`](./contributor-team-matcher) | **Contributor Team Matcher** | 1.0.10 | An interactive quiz that helps a contributor find the right WordPress contribution team based on their interests and skills. |
| [`credits-program-mentors`](./credits-program-mentors) | **Credits Program Mentors** | 1.5.1 | Displays the public "Sponsored mentors" directory (synced from Airtable) via the `[credits_program_mentors]` shortcode. |
| [`education-programs-map`](./education-programs-map) | **Education Programs Map** | 2.1.1 | A world map with city-level markers for WPCC, WPCredits, and Student Club activity, plus a dashboard screen for managing institutions. Implements [wordpress.org#584](https://github.com/WordPress/wordpress.org/issues/584). |
| [`student-impact`](./student-impact) | **Student Impact** | 1.6.1 | Showcases the top graduating students ranked by their WordPress.org contribution impact, contributions and logged hours (synced live from Airtable + profiles.wordpress.org). Provides "Student Stories" and "Graduate Stats" blocks/shortcodes. |
| [`wpcredits-tracker`](./wpcredits-tracker) | **WPCredits-Tracker** | 1.4.4 | A native WordPress rendering of the WordPress Credits program dashboard (scale, growth, partner map, contributions, student voices) — no iframe. Synced weekly from Airtable + profiles.wordpress.org; rendered via a block or the `[wpcredits_tracker]` shortcode. |

## How the pieces fit together

- The **theme** is the public site: it presents the programs, the student
  showcase, career paths and the timeline, and hosts the pages where the
  plugin blocks/shortcodes are placed.
- **Education Programs Map**, **Student Impact** and **WPCredits-Tracker**
  surface live program data (institutions, student impact, contribution
  metrics) as blocks/shortcodes dropped into theme pages.
- **Contributor Team Matcher** and **Credits Program Mentors** support the
  contribution journey — helping students pick a team and connect with mentors.

Together they take a student from a first WordPress lesson, through structured
contribution with a matched mentor, to a certified, contributing member of the
ecosystem.

## Installing

Each subfolder is a standard WordPress plugin or theme. Install any of them by
copying the folder into `wp-content/plugins/` (or `wp-content/themes/` for the
theme) on a WordPress 6.6+ / PHP 7.4+ site and activating it, or by zipping the
folder and uploading it via **Plugins → Add New → Upload** /
**Appearance → Themes → Add New → Upload Theme**.

To preview the theme instantly with no install, use its WordPress Playground
blueprint (see the theme's `readme.txt`).

## Data & privacy

Several plugins sync from Airtable and profiles.wordpress.org. API credentials
are **not** stored in this source — they are configured per-site (via settings
screens or site constants) and never committed to this repository.

## Maintenance

This is a mirror. Changes are made in each component's own development
repository and re-synced here (unpacked source only — no build artifacts,
`.git`, or credentials). Version numbers above reflect the mirrored snapshot.
