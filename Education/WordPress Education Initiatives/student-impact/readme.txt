=== Student Impact ===
Contributors: gomp
Tags: education, students, showcase, airtable, wordpress-org
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.6.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Showcase the top graduating students of the Educational Initiatives program, ranked by their real WordPress.org contribution impact.

== Description ==

Student Impact displays a "quick showcase" of your highest-impact graduating
students on a page (e.g. **Stories**). Each student is ranked by a weighted
composite that leads with impact and contributions; hours is only a minor factor
(never the top criterion):

* **Recent impact** (weight 45%) — the weighted 12-month contribution score from
  their profiles.wordpress.org page (high-impact work such as commits, releases,
  approved translations and props counts 3× routine activity).
* **Contributions** (weight 45%) — total contributions over the last 12 months.
* **Time commitment** (weight 10%) — logged program hours (from Airtable), used
  only as a gentle tiebreaker.

Data is synced **live**:

1. Graduate students are read from the Airtable **Students** table (status = Graduate).
2. Each is matched (by email) to the **Students Reports** table for their
   WordPress profile URL and logged hours.
3. Each WordPress.org profile is read to capture the impact metrics and avatar.
4. The composite ranking is computed and the top N are stored for display.

The plugin provides two displays, each available as a block and a shortcode:

**1. Student Stories** — the ranked top-N showcase (default 50).

* Block: **Student Stories**.
* Shortcode: `[student_impact count="50" columns="3" title="Student Stories"]`
* Visitors can filter by **contribution area** (Polyglots, Support, Documentation,
  etc.) using the filter bar above the grid.

**2. Graduate Stats** — class-wide totals across **all** graduates (not just the
top N): number of graduates, summed impact, summed contributions, summed hours.

* Block: **Graduate Stats**.
* Shortcode: `[student_impact_stats title="Class Impact"]`

== Configuration ==

Go to the **Student Stories** menu in the WP-Admin sidebar:

* Paste your **Airtable Personal Access Token** (use a token scoped to read-only
  access to just this base).
* Confirm the Base ID, table ids and the status value ("Graduate").
* Set how many students to showcase (default 50).
* Click **Sync now**. A daily background sync also runs automatically.

On activation the plugin seeds a bundled snapshot so the showcase works
immediately, before the first live sync completes.

== How students are selected and ranked ==

Who qualifies — a student is shown only if ALL of these are true:

1. They are in the Airtable Students table with Status = "Graduate".
2. They match (by email) a row in the Students Reports table.
3. That report has a usable WordPress.org profile (profiles.wordpress.org/user).

Graduates without a WordPress.org profile are excluded from the showcase, but
are still counted in the Graduate Stats totals (headcount and hours).

Which data comes from where:

* Name, email, Graduate status — Airtable Students table.
* WordPress profile URL, Hours, contribution Team — Airtable Students Reports
  (Hours = the highest value across the student's reports).
* Recent impact score, Contributions, high-impact count, "active" flag —
  scraped live from the student's profiles.wordpress.org "Recent impact" panel
  (last 12 months). Impact is WordPress's own weighted score: high-impact work
  (commits, releases, approved translations, props) counts 3x routine activity.
* Avatar — Gravatar on the WordPress.org profile.

How they are ranked (order on the page) — a composite score, each signal scaled
against the cohort's top value:

* Recent impact — 45%
* Contributions — 45%
* Hours — 10% (minor tiebreaker only)

Ties break by impact, then contributions, so hours never decides the top spots.
The highest-scoring top N (default 50) are shown, ranked #1 downward.

Card labels:

* Rank badge (#1–#3 use the highlighted "medal" style).
* "Active now" — made at least 1 contribution in the last 90 days.
* "N high-impact" — high-impact contributions in the last 12 months.
* Team badge — the student's main contribution area (also the filter groups).

Freshness — data syncs live from Airtable + WordPress.org (daily, plus a manual
"Sync now"). A bundled snapshot is shown until the first sync runs.

== Shortcode attributes ==

`[student_impact]`

* `count` — number of students to show (0 = all synced). Default 0.
* `columns` — grid columns, 2–4. Default 3.
* `title` — section heading.
* `subtitle` — section subheading.
* `filters` — show the contribution-area filter bar (`yes`/`no`). Default `yes`.

`[student_impact_stats]`

* `title` — section heading. Default "Class Impact".
* `subtitle` — section subheading.
* `note` — show the "across N graduates" footnote (`yes`/`no`). Default `yes`.
* `layout` — `grid` (cards) or `inline` (compact horizontal strip for headers/footers). Default `grid`.

Legacy aliases (kept working for pages built with earlier versions):
`[education_student_stories]` and `[ei_student_stories]` = the showcase;
`[education_graduate_stats]` = the stats block. Same attributes as above.

== Changelog ==

= 1.6.1 =
* Backward compatibility: the previous shortcodes still work as aliases, so pages
  built with an earlier version keep rendering after the rename —
  [education_student_stories] and [ei_student_stories] map to the showcase, and
  [education_graduate_stats] maps to the stats block.

= 1.6.0 =
* Renamed the plugin to "Student Impact". Full re-slug: folder, text domain and
  asset handles → student-impact; prefixes → si_/SI_; CSS → si-; blocks →
  student-impact/showcase and student-impact/stats; shortcodes →
  [student_impact] and [student_impact_stats].

= 1.5.4 =
* Made the student profile photos larger on the showcase cards (88px → 116px).

= 1.5.3 =
* Documented the selection and ranking rules: a new "How students are selected
  and ranked" section here, and a collapsible help panel on the admin screen.

= 1.5.2 =
* Removed the "Current Job" line (e.g. "Student", "Studentessa", "Estudiante")
  from the showcase cards. This field is no longer scraped or displayed.

= 1.5.1 =
* Fixed: WordPress.org profile scraping read every student's impact and
  contributions as 0 (so Graduate Stats totals and the showcase ranking lost
  those signals after a live sync). The profile's impact panel packs the number
  and its label into adjacent tags with no whitespace; the flattener now inserts
  a space between tags so the parser matches. Re-run "Sync now" after updating.

= 1.5.0 =
* Reweighted the showcase ranking so **impact and contributions are the dominant
  criteria** (45% each) and hours is only a minor tiebreaker (10%) — students are
  no longer ranked primarily by hours. Refreshed the bundled snapshot to match.

= 1.4.0 =
* Graduate Stats now supports a `layout` option: the default "grid" of cards, or
  a compact "inline" horizontal strip suited to page headers and footers.
  Selectable in the block sidebar or via the shortcode `layout` attribute.

= 1.3.0 =
* Added a **Graduate Stats** block and `[student_impact_stats]` shortcode
  showing class-wide totals across all graduates: number of graduates, summed
  impact, summed contributions, and summed hours. Totals are computed over the
  full cohort during each sync (not just the top-N showcase).

= 1.2.0 =
* Renamed the plugin to "Student Impact".
* Moved the admin screen from Settings to its own top-level "Student Stories"
  item in the WP-Admin sidebar.

= 1.1.0 =
* Added a contribution-area filter bar (Polyglots, Support, Documentation, …)
  above the showcase, with per-area counts. Toggle via the block setting or the
  shortcode `filters` attribute.
* Increased the default showcase size to 50 students; refreshed the bundled
  snapshot to the top 50.

= 1.0.0 =
* Initial release: live Airtable + WordPress.org sync, ranking engine, Student
  Stories block and [student_impact] shortcode, admin settings with manual
  and daily sync.
