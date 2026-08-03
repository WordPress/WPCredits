# Education Programs Map

Contributors: Maciej Pilarski
Tags: map, education, meetup, wpcc, wpcredits
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Displays a world map with city-level markers for WPCC, WPCredits, and Student Club activity, with a Dashboard settings section for managing institutions.

## Description

Implements https://github.com/WordPress/wordpress.org/issues/584.

This plugin adds an "Education Programs Map" section to the WordPress Dashboard where an administrator can add, edit, and delete institutions (name, city, country, coordinates, one or more programs, and event count) for WordPress Campus Connect (WPCC), WPCredits, and Student Club activity — an institution can take part in any combination of programs, not just one. Coordinates can be set by clicking on an interactive map instead of typing them by hand. Additional program types beyond the built-in three can be added from the Programs screen. Institutions can also link out to their own WPCC and Student Club pages.

Use the `[education_programs_map]` shortcode, or the "Education Programs Map" block in the block editor, on any page or post to display a world map with city-level markers sized by event count, filterable by program, styled to match the existing Meetup map on events.wordpress.org.

The map's on-page size (width and height) can be set once for the whole site under Dashboard > Education Programs Map > Settings, or overridden per shortcode/block instance.

### Shortcode

```
[education_programs_map]
```

Optional attributes:

- `program` — pre-filter the map to one of `wpcc`, `wpcredits`, or `student_club`.
- `height` — CSS height of the map container (defaults to the value set in Education Programs Map > Settings, e.g. `520px`).
- `width` — CSS width of the map container (defaults to the value set in Education Programs Map > Settings, e.g. `100%`).

### Block

Search for "Education Programs Map" in the block inserter to add the map without typing shortcode syntax. The block's sidebar (Settings panel) exposes the same `program`, `height`, and `width` options as the shortcode, with a live preview in the editor.

### Airtable Sync

Dashboard > Education Programs Map > Airtable Sync has an independent connection for each of the three built-in programs (WPCC, WPCredits, Student Club), since each can live in a completely different Airtable base. For each, configure a Personal Access Token (needs `data.records:read` on that base), the base ID, the institutions table name, and the linked table used for country names, then click that program's "Sync Now" — or check "Automatically sync every 7 days" to have it run on its own via WP-Cron. Each institution is matched to its Airtable record, so re-running a sync updates existing entries rather than duplicating them, and only ever affects institutions belonging to that program. Coordinates are looked up automatically from the institution's city and country via the Photon geocoding service (OpenStreetMap data).

Institutions whose Airtable record no longer matches the filter (e.g. it's no longer "Confirmed," or was deleted) are hidden from the public map rather than deleted — they stay visible in the admin's "All Institutions" list (marked with a "Hidden" badge) and automatically reappear on the map if the record matches again on a later sync. Any institution's visibility can also be toggled by hand from its edit screen.

## Installation

1. Upload the `education-programs-map` folder to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Go to Dashboard > Education Programs Map > Add New to add your first institution.
4. Add `[education_programs_map]` to any page to display the map.

## Changelog

### 2.1.1

- The map popup no longer shows "0 events" for an institution with no recorded events — that line is now omitted entirely when the event count is zero.

### 2.1.0

- Map colors, marker style, and popup styling now match the education-credits-dashboard plugin's palette (mapped from the Educational Initiatives/Education Hub FSE theme): the same primary blue for markers, filter buttons, and popup headings, matching borders/shadows/border-radius/system font, and a solid blue marker with a white ring in place of the previous translucent circle.
- Switched the map's base tiles from the default OpenStreetMap style (blue water, tan land) to the same light CARTO "Positron" basemap the credits dashboard's map uses, so the map itself — not just the markers and UI chrome — matches.

### 2.0.0

- Renamed the plugin from "WP Education Map" to "Education Programs Map." This is a full technical rename: plugin folder and file names, the PHP class/constant prefix (`WEIM_` → `EPM_`), the shortcode (`[wp_education_map]` → `[education_programs_map]`), the Gutenberg block (`weim/education-map` → `epm/education-programs-map`), script/style handles, the text domain, and the database table and option names all changed.
- Sites upgrading from "WP Education Map" migrate automatically the first time this version runs: the institutions table is renamed in place (all rows and indexes preserved, nothing re-imported), and the Programs/Settings/Airtable Sync options are carried over. The old plugin should be deactivated and this one activated in its place; any page using the old `[wp_education_map]` shortcode or the old block will need to be updated to the new shortcode/block.

### 1.8.0

- Airtable Sync is now three independent connections, one per program (WPCC, WPCredits, Student Club), each with its own token, base ID, table names, filter formula, and auto-sync toggle — since each program's institutions can live in a completely different Airtable base. Settings from 1.6.0/1.7.0's single shared connection are not migrated; each program's connection needs to be (re)configured.
- Fixed a cross-program data-safety issue: hiding institutions that fall out of a sync's filter is now scoped to the program being synced, so running one program's sync can never affect another program's institutions.

### 1.7.0

- Institutions synced from Airtable that no longer match the sync filter (e.g. their "Current Stage" changed away from "Confirmed," or the record was deleted) are now automatically hidden from the public map instead of staying visible forever. They remain manageable from the admin list (flagged with a "Hidden" badge) and reappear automatically if they match again on a later sync. A "Visible on Map" checkbox on the institution edit screen allows manual overrides too.
- Add an "Automatically sync every 7 days" option to Airtable Sync settings, using WP-Cron; the admin screen shows the next scheduled run and the outcome (and trigger — manual or automatic) of the last sync.

### 1.6.0

- Add an Airtable Sync screen (Dashboard > Education Programs Map > Airtable Sync) to import institutions from an Airtable base, matched and updated by Airtable record ID on repeat syncs rather than duplicated.
- Institutions imported from Airtable are automatically geocoded (city + country → coordinates) via the Photon geocoding service.

### 1.5.0

- Institutions can now belong to multiple programs at once (e.g. both WPCC and Student Club), instead of exactly one. The Add New / Edit screen now uses checkboxes instead of a single dropdown, and the map's per-program filtering and popups account for institutions with more than one program.
- Add optional "WPCC Site" and "Student Club Site" link fields to institutions, shown in the map popup alongside the existing generic Website link.
- Existing installs are migrated automatically: the old single-value `program` column's data is copied into the new multi-value `programs` column the first time the updated plugin runs.

### 1.4.1

- Fix: the "Education Map" block's `edit()` implementation never called `useBlockProps()`, so Gutenberg had no wrapper element to attach the block's selection outline, toolbar, or preview to — inserting the block showed nothing at all. Added the missing `useBlockProps()` wrapper, a visible min-height so the block always has a footprint in the canvas, and a fallback notice if the preview component itself fails to load.

### 1.4.0

- Add an "Education Map" Gutenberg block (`epm/education-programs-map`) as a wrapper around the `[education_programs_map]` shortcode, so editors can insert the map from the block inserter instead of typing shortcode syntax. The block's sidebar exposes the same program/height/width options, with a live server-rendered preview in the editor.

### 1.3.0

- Pass WordPress-Extra coding standards cleanly (0 errors, 0 warnings): fixed unescaped output, unsanitized `$_POST` reads, formatting/alignment, and pre/post-increment style.
- Pass WordPressVIPMinimum with 0 errors; the only remaining warnings are "direct DB query without object caching," expected for a plugin with its own custom table.

### 1.2.2

- Fix: the map's REST URL and program list were passed to the browser via `wp_localize_script()`, which some hosts' JS-deferral/optimization features strip from the page, leaving the map with no data and no markers. This data now travels as `data-*` attributes on the map container itself, which is more resilient to that kind of output manipulation.

### 1.2.1

- Harden the REST API response by re-validating the `website` field with `esc_url_raw()` before output.

### 1.2.0

- Add a Programs screen (Dashboard > Education Map > Programs) to add or delete custom program types, in addition to the built-in WPCC, WPCredits, and Student Club.
- Add an interactive map picker to the Add New / Edit Institution screen — click or drag a marker to set coordinates instead of typing them by hand.

### 1.1.0

- Add a Settings screen (Dashboard > Education Programs Map > Settings) to control the map's width and height site-wide, with per-shortcode overrides.

### 1.0.0

- Initial release: Dashboard CRUD for institutions, REST endpoint, and frontend map shortcode.
