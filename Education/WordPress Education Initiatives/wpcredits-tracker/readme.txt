=== WPCredits-Tracker ===
Contributors: gomp, peiraisotta
Tags: wordpress credits, dashboard, contributions, airtable, students
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.4.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A native WordPress rendering of the WordPress Credits program dashboard — no iframe. Data synced from Airtable and WordPress.org.

== Description ==

This plugin renders the WordPress Credits program dashboard natively on your site, replacing the iframe embed of https://wordpress.github.io/WPCredits/. It is a PHP port of that project's Python build (`scripts/build_dashboard.py`), producing the same public figures on your own server.

The dashboard has two tabs:

* **Overview** — scale & momentum, a month-by-month growth chart, a world map of partner institutions, the field-of-study breakdown, the skills students build, what students produce (first contributions, sites built, teams contributed to), and outcome/quality figures.
* **Voices** — student testimonials, tagged by country.

Display it whole with the **WPCredits-Tracker (full)** block or the shortcode:

`[wpcredits_tracker]`

The old `[education_credits_dashboard]` shortcode from the previous plugin name still works as an alias, so existing pages don't need editing.

Or build it modularly — each section is its own block (grouped under the **WPCredits-Tracker** category in the inserter), so you can place any subset anywhere, in any order:

* Scale & Momentum
* Growth Chart
* Partner Map
* Field of Study
* Skills
* What Students Produce
* Outcomes & Quality
* Voices

The dashboard is designed to sit inside your theme's page — it has no header of its own.

= How the data works =

On each sync the plugin reads the program tables from Airtable, scrapes each student's profiles.wordpress.org page for translation activity, and computes the same public aggregates as the upstream dashboard. Only anonymous, aggregated data is stored and displayed — no per-person detail. Syncs run automatically once a week (Monday 06:00 UTC) and on demand via **Sync now** on the WPCredits-Tracker admin screen. A bundled snapshot is shown until the first live sync completes.

= Bundled third-party libraries =

* Chart.js 4.4.1 (MIT) — the growth chart.
* Leaflet 1.9.4 (BSD-2-Clause) — the partner map. Map tiles are loaded at runtime from OpenStreetMap / CARTO.

== Installation ==

1. Upload the `wpcredits-tracker` folder to `/wp-content/plugins/`, or install the ZIP via Plugins → Add New → Upload.
2. Activate the plugin. A bundled snapshot renders immediately.
3. Go to **WPCredits-Tracker** in the admin menu, enter your Airtable Personal Access Token (read-only, scoped to the program base), and click **Sync now**.
4. Add the **WPCredits-Tracker** block (or the `[wpcredits_tracker]` shortcode) to a page.

== Frequently Asked Questions ==

= Do I need Airtable access? =
Yes — a Personal Access Token with read access to the WordPress Credits base is required to sync live data. The base and table/field IDs match the upstream project and are built in; the base ID can be changed on the settings screen, and table/field IDs via the `wpct_tables` / `wpct_fields` filters.

= Is any personal student data exposed? =
No. The stored data blob and the front end contain only aggregates and anonymized rows (status, graduate flag, field of study, translation-string count) — the same public subset the upstream dashboard emits.

== Changelog ==

= 1.4.4 =
* Security: fixed a cross-site scripting hole in the dashboard. Text drawn from the
  Airtable base — the field-of-study names in the "Who's joining us" chart, and the
  institution, city and country names in the partner map popups — was written into
  the page without escaping, so markup placed in those Airtable fields would run as
  script for every visitor. All text is now escaped before it is rendered, and
  single-select names are stripped of markup as they are synced.

= 1.4.3 =
* Set the co-author display name to Isotta Peira.

= 1.4.2 =
* Corrected the contributor username to peiraisotta (https://profiles.wordpress.org/peiraisotta/).

= 1.4.1 =
* Kept the old [education_credits_dashboard] shortcode working as an alias of [wpcredits_tracker], so pages built with the previous plugin do not break.

= 1.4.0 =
* Renamed the plugin to WPCredits-Tracker throughout (slug, text domain, PHP prefixes, option keys, block namespace, shortcode, CSS/JS handles, block category).
* Added Isotta as co-author and contributor.
* NOTE: option keys and the block namespace changed, so a prior install re-enters its Airtable token and re-syncs, and existing blocks are re-inserted under the new names.

= 1.3.0 =
* Made the dashboard modular: every section is now its own block (Scale & Momentum, Growth Chart, Partner Map, Field of Study, Skills, What Students Produce, Outcomes & Quality, Voices), grouped under a new "WPCredits-Tracker" block category, so sections can be placed independently anywhere on a page.
* Kept the combined "WPCredits-Tracker (full)" block (and the shortcode), which now composes the same section renderers — no duplicated markup.
* Section blocks on one page share a single data payload and asset load.

= 1.2.0 =
* Reflected upstream changes: folded the Contributions tab into the Overview as a trimmed "What students produce" block (first contributions, sites created, teams contributed to) and dropped the separate tab.
* Voices is now testimonials only, tagged by country (with flag) instead of language; removed the ratings, recommend/keep, and confidence blocks (those figures live on the Overview, where their denominators are stated).
* Dropped the unused feedback "responses" total from the synced data (each metric keeps its own honest n).
* Refreshed the bundled data snapshot.

= 1.1.3 =
* Removed the footer note ("Data sourced from Airtable & WordPress.org profiles").

= 1.1.2 =
* Removed the sponsors strip (markup, styles, bundled logo images, and the block/shortcode option).

= 1.1.1 =
* Styled the "Scale & momentum", "Contributions", and "How students rate the experience" numbers to match the Student Impact (Graduate Stats) block: fluid size, primary blue, weight 800, tabular numerals. The "Outcomes & quality" numbers keep their restrained deep-navy treatment.

= 1.1.0 =
* Removed the dashboard's own header (WordPress logo + mission line) so it sits cleanly inside the theme's page.
* Restyled to match the Educational Initiatives (Education Hub) theme: system fonts (no serif), the theme's cool blue/navy palette, bordered white surfaces, and a transparent container that blends into the page background.
* Removed the block/shortcode `mission` attribute (no longer applicable).

= 1.0.0 =
* Initial release. Native block + shortcode rendering of the WordPress Credits dashboard, weekly Airtable + WordPress.org sync (resumable), bundled Chart.js/Leaflet, and a seed snapshot.
