=== WPCredits-Tracker ===
Contributors: gomp, peiraisotta
Tags: wordpress credits, dashboard, contributions, airtable, students
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.8.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A native WordPress rendering of the WordPress Credits program dashboard, with no iframe. Data synced from Airtable and WordPress.org.

== Description ==

This plugin renders the WordPress Credits program dashboard natively on your site, replacing the iframe embed of https://wordpress.github.io/WPCredits/. It is a PHP port of that project's Python build (`scripts/build_dashboard.py`), producing the same public figures on your own server.

The dashboard has two tabs:

* **Overview** - scale & momentum, a month-by-month growth chart, a world map of partner institutions, the field-of-study breakdown, the skills students build, what students produce (first contributions, sites built, teams contributed to), and outcome/quality figures.
* **Voices** - student testimonials, tagged by country.

Display it whole with the **WPCredits-Tracker (full)** block or the shortcode:

`[wpcredits_tracker]`

The old `[education_credits_dashboard]` shortcode from the previous plugin name still works as an alias, so existing pages don't need editing.

Or build it modularly. Each section is its own block (grouped under the **WPCredits-Tracker** category in the inserter), so you can place any subset anywhere, in any order:

* Scale & Momentum
* Growth Chart
* Partner Map
* Field of Study
* Skills
* What Students Produce
* Outcomes & Quality
* Voices
* Last Updated (one line: "Figures last updated on" and the date of the last successful sync; it prints nothing until a sync has succeeded)

The dashboard is designed to sit inside your theme's page, so it has no header of its own.

= How the data works =

On each sync the plugin reads the program tables from Airtable, reads each student's whole contributions timeline on profiles.wordpress.org for translation activity, and computes the same public aggregates as the upstream dashboard. Only anonymous, aggregated data is stored and displayed, with no per-person detail. Syncs run automatically once a week (Monday 06:00 UTC) and on demand via **Sync now** on the WPCredits-Tracker admin screen. A bundled snapshot is shown until the first live sync completes.

Translation activity is added up from every page of a profile's timeline, not from the profile page alone, which shows only the newest ten contributions. A long timeline is read across several sync steps, up to 100 pages per profile, with a 0.2 second pause between pages; the `wpct_timeline_max_pages` filter changes the limit (in pages) and `wpct_timeline_pause` the pause (in microseconds). When a profile cannot be fetched, or its timeline cannot be paged, the admin screen says how many profiles were affected; it never names them.

= Bundled third-party libraries =

* EB Garamond and Inter (SIL Open Font License 1.1, each license in `assets/fonts`) - the headings and figures, and the text.
* Chart.js 4.4.1 (MIT) - the growth chart.
* Leaflet 1.9.4 (BSD-2-Clause) - the partner map. Map tiles are loaded at runtime from OpenStreetMap (tile.openstreetmap.org).

== Installation ==

1. Upload the `wpcredits-tracker` folder to `/wp-content/plugins/`, or install the ZIP via Plugins → Add New → Upload.
2. Activate the plugin. A bundled snapshot renders immediately.
3. Go to **WPCredits-Tracker** in the admin menu, enter your Airtable Personal Access Token (read-only, scoped to the program base), and click **Sync now**.
4. Add the **WPCredits-Tracker** block (or the `[wpcredits_tracker]` shortcode) to a page.

== Frequently Asked Questions ==

= Do I need Airtable access? =
Yes. A Personal Access Token with read access to the WordPress Credits base is required to sync live data. The base and table/field IDs match the upstream project and are built in; the base ID can be changed on the settings screen, and table/field IDs via the `wpct_tables` / `wpct_fields` filters.

= Is any personal student data exposed? =
No. The stored data blob and the front end contain only aggregates and anonymized rows (status, graduate flag, field of study, translation-string count), the same public subset the upstream dashboard emits.

== Changelog ==

= 1.8.2 =
* Section blocks placed one after another keep 32px before a block that opens a new group (What students produce, Skills, What students say), on any page template, instead of leaving that to the page's own styles.

= 1.8.1 =
* The boxes match the Education Directory's own cards: 2px corners on a 1px hairline, no shadow. The skills and the country tags are 2px tags instead of pills, and the highlighted figure is marked by a blue hairline instead of a tinted panel.

= 1.8.0 =
* A track made tomorrow is counted the day its first student has it. The figures now follow the upstream dashboard's rule: every student counts except those whose Status is Interested, Dropped out, Not moving forward, Paused, Fail or SPAM (or empty), instead of a list of the statuses that count, which went stale each time a track was added. A status this version does not know by name is counted and named on the WPCredits-Tracker admin screen, so an outcome counted by mistake does not stay unseen; the `wpct_non_participant_statuses` filter adds one. With today's records the figures do not change.
* The students' quotes can be edited on the WPCredits-Tracker admin screen: the quote, who said it, the country, a country code for the flag and the quote's language. The six that ship with the plugin are shown until a site saves its own. Nothing is read from Airtable for them: publish a quote only when the student has agreed.
* The page no longer carries the count of approved sponsors in its source. It was never shown, but it was in the data every visitor's browser received.

= 1.7.2 =
* The heading over the students' quotes reads "What students say" instead of "Voices from the program".

= 1.7.1 =
* Białystok is on the partner map: its entry in the coordinates list was filed under a spelling WordPress never produces, so 1.7.0 left that one city off.

= 1.7.0 =
* Students on a track are counted. The figures knew only the original program's statuses (In Sensei, In Sensei Self-onboarding, In Sensei 50h, Pending graduation), so a student whose Status is Intro to Contribution, Developer Track, Designer Track, Foundations Track or Foundations Track Haaga-Helia was in neither "Students currently in the program" nor "Students who have joined to date", nor in the growth chart or the field-of-study ring. The `wpct_active_statuses` filter adds a track made after this release.
* The partner map is built at each sync from the confirmed institutions, instead of a list of 22 markers shipped with the plugin. Coordinates come from `data/city-coords.json` (one entry per city; the `wpct_city_coords` filter adds more); nothing is looked up from an outside service. A confirmed institution whose city the list does not know, or whose record names no city, is left off the map and named on the WPCredits-Tracker admin screen. Until the first sync under this version the old list is shown.
* The figures change with the next sync, not with the update.

= 1.6.3 =
* Fixed 1.6.2's sync: it asked Airtable without the base ID and stopped with "HTTP 404". Nothing was stored by the failed runs; the figures on the page were untouched.

= 1.6.2 =
* On a site that also runs the WPCredits Program Manager, a sync uses that plugin's Airtable token when none is saved here: both read the same base, so the site keeps one token. The token is read when a sync needs it and is never copied into this plugin's settings; a token saved here takes its place. The settings screen says which one is in use. The `wpct_airtable_token` filter can replace it.

= 1.6.1 =
* The country tags under the voices carry their flag again, beside the country's name. The flag is decoration: a screen reader skips it, and where a system draws no flag emoji the name still says it.

= 1.6.0 =
* The look is now WordPress.org's own, as the Education Directory (wordpresseducation.org) wears it: headings and figures in EB Garamond at regular weight, text in Inter, one blue for what matters, white cards on hairlines. Both typefaces ship with the plugin, so a page asks no one else for them.
* Section labels are one quiet uppercase eyebrow instead of four colors; skills are pills in one tint; the growth chart draws in blue, charcoal and gray. No text is smaller than 12px.
* New block, WPCredits: Last Updated, and the same line under the combined block: "Figures last updated on" and the date of the last successful sync. It prints nothing until a sync has succeeded.
* The tabs of the combined block are buttons a keyboard reaches, and say which one is selected. The growth chart has a text label, and stands still for a reader who asked for reduced motion. The field-of-study figures are a list.
* The country tags under the voices no longer carry a flag emoji; the country's name was always there.
* No change to the data, the sync or the settings.

= 1.5.3 =
* The partner map draws on OpenStreetMap's standard tiles instead of CARTO's light basemap. CARTO stopped serving anonymous tiles and now stamps "API KEY REQUIRED" across every one of them while still answering with a valid image, so the map kept drawing with the watermark over it and nothing reported an error. The basemap looks different as a result: OpenStreetMap's standard style carries more color than CARTO's near-white one. This was fixed elsewhere in September but the fix never reached this plugin's own copy of the map code.

= 1.5.2 =
* Replaced every em dash and en dash in the plugin's text, code comments and this readme with a plain hyphen or a reworded sentence. The only visible change is the placeholder for a figure that is not available on the Outcomes & Quality cards, now a hyphen. No change in behavior.

= 1.5.1 =
* Translation activity is counted from every page of each profile's contributions timeline. The profile page shows only the newest ten contributions of every kind, so the totals missed all older translation activity; the sync now pages the timeline's Polyglots filter the way the upstream dashboard does since 2 October 2026. Only Polyglots rows are counted, so a commit message or a forum topic that mentions strings no longer adds to the totals.
* A long timeline is read across several sync steps, within each step's time budget, with a 0.2 second pause between pages and a limit of 100 pages per profile.
* The admin screen shows how many profiles the last sync read and how many had translation activity, and warns with counts (never names) when profiles could not be fetched, were counted from their newest rows only, or ran past the page limit.

= 1.5.0 =
* The count of approved sponsors comes from the WordPress Education Dashboard's sponsors feed instead of the Airtable Sponsors table; when the feed does not answer, the last count stays and the settings page says so. The feed's address is a setting, blank for the program's own.

= 1.4.4 =
* Security: fixed a cross-site scripting hole in the dashboard. Text drawn from the
  Airtable base was written into the page without escaping: the field-of-study names
  in the "Who's joining us" chart, and the institution, city and country names in the
  partner map popups. Markup placed in those Airtable fields would run as script for
  every visitor. All text is now escaped before it is rendered, and single-select
  names are stripped of markup as they are synced.

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
* Kept the combined "WPCredits-Tracker (full)" block (and the shortcode), which now composes the same section renderers, so no markup is duplicated.
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
