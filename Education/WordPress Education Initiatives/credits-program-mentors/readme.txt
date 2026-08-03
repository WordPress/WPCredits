=== Credits Program Mentors ===
Contributors: gomp
Tags: mentors, airtable, directory, shortcode, block
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display a filterable directory of mentors from an Airtable base, with WordPress.org profile photos and a Sponsored badge.

== Description ==

Credits Program Mentors turns an Airtable table of mentors into a clean, filterable
directory on your site. It pulls records through Airtable's official REST API,
caches them for speed, and renders them as a responsive card grid or a table via
the `[credits_program_mentors]` shortcode or the bundled block.

= Features =

* **Shortcode and block** — `[credits_program_mentors]`, or the "Credits Program Mentors"
  block with a live preview and sidebar controls.
* **Card grid or table** layout, with the detail fields aligned across cards.
* **Active mentors only** — shows mentors whose Airtable `Status` is `Active`.
* **Sponsored badge** — mentors with a value in `Sponsor Company Name` get a
  small "Sponsored" badge.
* **Profile photos** — each mentor's WordPress.org profile avatar, loaded
  directly in the visitor's browser via WordPress.org's official avatar redirect.
* **Front-end filters** — visitors can narrow the list by Sponsorship, Language,
  and Country, entirely in the browser (works on cached pages).
* **Optional country map** — an interactive Leaflet map showing where mentors
  are based, with a marker per country sized by mentor count; enable or disable
  it from the plugin settings. Visitors can zoom and pan, and click a country to
  filter the list. Styled to match the Education Programs Map plugin.
* **Caching** — Airtable records are stored in transients; a "Refresh data"
  button and a configurable cache lifetime are provided.
* **Privacy-aware, escaped output, and translation-ready.**

= Configuration =

Records are requested with Airtable's `cellFormat=string`, so single-selects,
multi-selects, linked records, and URL fields all render cleanly. The Base ID,
Table ID, optional View, cache lifetime, and country map are all configurable
from the **Credits Program Mentors** menu in the admin sidebar.

The optional country map is a Leaflet map with a light CARTO basemap, matching
the Education Programs Map plugin. Country names and marker positions come from
public-domain Natural Earth data (https://www.naturalearthdata.com/) bundled
with the plugin; only the basemap tiles are loaded at runtime (see External
services).

== External services ==

This plugin connects to the following third-party services. It only does so with
data you configure; it does not transmit personal data about your site visitors
beyond what any embedded remote image would.

**1. Airtable API (api.airtable.com)**
Used to fetch the mentor records that the plugin displays. When a page with the
shortcode/block is rendered (and periodically in the background), the plugin
sends a request to Airtable containing your configured Base ID, Table ID,
optional View, and your Airtable Personal Access Token (as a Bearer credential
for authentication). No site-visitor data is sent. This is required for the
plugin to function.
Airtable: https://airtable.com/ — Terms: https://www.airtable.com/company/tos — Privacy: https://www.airtable.com/company/privacy

**2. WordPress.org avatar redirect (wordpress.org)**
Used to display each mentor's profile photo. The plugin outputs an `<img>` whose
source is WordPress.org's official avatar redirect,
`https://wordpress.org/grav-redirect.php?user=USERNAME`, which redirects to that
user's Gravatar. The image is requested by the visitor's browser, so the
visitor's IP address and user agent are sent to WordPress.org (and to Gravatar,
below) when the image loads. The mentor's public WordPress.org username, taken
from your Airtable data, appears in the URL. Photos can be disabled with
`photos="no"`.
WordPress.org: https://wordpress.org/ — Privacy: https://wordpress.org/about/privacy/

**3. Gravatar (secure.gravatar.com)**
The avatar redirect above resolves to a Gravatar image, which the visitor's
browser loads directly from Gravatar (operated by Automattic). Mentors without a
Gravatar get a generated identicon.
Gravatar: https://gravatar.com/ — Privacy: https://automattic.com/privacy/

**4. CARTO basemap tiles (basemaps.cartocdn.com)**
When the optional country map is enabled, the visitor's browser loads map tiles
from CARTO's light "Positron" basemap (built on OpenStreetMap data). This sends
the visitor's IP address and the requested tile coordinates to CARTO as the map
renders. The map can be turned off in the plugin settings.
CARTO: https://carto.com/ — Terms: https://carto.com/legal/ — OpenStreetMap: https://www.openstreetmap.org/copyright

== Installation ==

1. Upload the `credits-program-mentors` folder to `/wp-content/plugins/`, or install
   the ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin through the **Plugins** screen.
3. Open the **Credits Program Mentors** menu in the admin sidebar.
4. Create a read-only **Personal Access Token** at
   https://airtable.com/create/tokens with the `data.records:read` scope and
   access to your base, and paste it in. Set your Base ID and Table ID (the
   Sponsored mentors base IDs are pre-filled as a default).
5. Add `[credits_program_mentors]` to a page or post — or insert the
   **Credits Program Mentors** block.

== Frequently Asked Questions ==

= Do I need an Airtable account and token? =

Yes. The plugin uses Airtable's official REST API, which always requires a
Personal Access Token — even for a base that is shared publicly. Use a
read-only token scoped to `data.records:read`.

= What data leaves my site? =

See the "External services" section above. In short: your configured Airtable
IDs and token go to Airtable (server-side); and mentor avatar images are loaded
by the visitor's browser from WordPress.org's avatar redirect, which resolves to
Gravatar.

= Which mentors are displayed? =

Mentors whose Airtable `Status` field equals `Active`. Mentors that also have a
`Sponsor Company Name` are marked with a "Sponsored" badge.

= Where do the profile photos come from? =

Each photo is the mentor's WordPress.org profile avatar. The plugin points the
image at WordPress.org's official avatar redirect
(`grav-redirect.php?user=USERNAME`), so the visitor's browser loads it directly
and in parallel — there is no server-side fetching or cache to warm up. Mentors
with no Gravatar get a generated identicon; mentors with no WordPress.org profile
URL show their initials.

= Can I show a map of where mentors are based? =

Yes. Enable **Country map** in the plugin settings to show an interactive map
above the list, with a marker per country sized by the number of mentors.
Visitors can zoom and pan, and click a country's marker to filter the list to
mentors from that country (click empty map to clear). It uses the same Leaflet
map and CARTO basemap as the Education Programs Map plugin, for a consistent
look. Override it per placement with the `map="yes|no"` shortcode attribute.

= What shortcode attributes are available? =

`layout` (grid|table), `limit`, `columns`, `country`, `language`, `search`,
`fields`, `view`, `photos` (yes|no), `photo_size`, `filters` (yes|no), and
`map` (yes|no; defaults to the setting).
Example: `[credits_program_mentors layout="grid" columns="3" map="yes"]`

== Screenshots ==

1. Mentor directory as a responsive card grid, with profile photos and the
   Sponsorship / Language / Country filter bar.
2. A sponsored mentor card showing the "Sponsored" badge.
3. The settings screen: Airtable connection, the Display (country map) toggle,
   and the Refresh data control.
4. The Credits Program Mentors block with live preview in the editor.
5. The optional country map highlighting mentor countries above the list.

== Changelog ==

= 1.5.1 =
* Security: the Airtable Personal Access Token is no longer written back into the
  settings page. It was rendered into the token field's value attribute, which put
  the token in view-source and the browser cache even though the field was masked
  on screen. The field now shows only the last four characters as a placeholder and
  posts blank to mean "keep the current token".

= 1.5.0 =
* Country map reworked to match the Education Programs Map plugin: it is now a
  Leaflet map with the CARTO light basemap and a blue circle marker per country
  (sized by mentor count) with popups and native zoom/pan, replacing the inline
  SVG choropleth. Clicking a marker still filters the list to that country.
* Adds CARTO basemap tiles as a disclosed external service (map tiles load in
  the browser); country names and marker positions remain bundled (Natural
  Earth, public domain).

= 1.4.1 =
* Increased the default profile photo size to 116px.

= 1.4.0 =
* Design: restyled the mentor cards, chips, filter bar, and map to match the
  Education Student Stories plugin, so both read as part of the same site design
  (shared palette, 14px cards with hover lift, ringed circular avatars, navy
  names, primary/accent pills). Default profile photo size is now 96px.

= 1.3.2 =
* Fix: clicking a country on the map did not filter the list. Country selection
  is now detected on pointer release (the map's pan/zoom capture had suppressed
  the click), so tapping a country reliably filters the mentors below it.

= 1.3.1 =
* Map: added zoom and pan controls, and click-a-country to filter the list to
  mentors from that country (synced with the Country filter). Country matching
  is now keyed by country id, so name variants (e.g. "United States" vs "United
  States of America") and multi-country values are handled consistently across
  the map, cards, and filter.

= 1.3.0 =
* New: optional country map. A self-contained SVG world map highlights the
  countries mentors come from (shaded by count), shown above the list. Toggle it
  in the plugin settings, or per placement with `map="yes|no"`. Geometry is
  bundled from Natural Earth (public domain); no external requests are made.

= 1.2.1 =
* Fix: mentors who had another URL field (e.g. a personal site or social link)
  filled in showed an initials placeholder instead of their photo. The plugin
  now specifically locates each mentor's WordPress.org profile URL rather than
  the first link-type field, so far more photos display.
* Moved the admin screen to its own top-level "Credits Program Mentors" menu in
  the sidebar, instead of under Settings.

= 1.2.0 =
* Profile photos now load directly in the visitor's browser via WordPress.org's
  official avatar redirect (`grav-redirect.php`), instead of being scraped and
  cached server-side. This fixes photos not all loading on large mentor lists,
  and removes the background WP-Cron warm-up and avatar cache entirely.
* Settings: replaced "Refresh photos" with "Refresh data" (clears the Airtable
  records cache).

= 1.1.0 =
* Show all mentors with an active Status (not just sponsored ones); add a
  "Sponsored" badge for mentors that have a Sponsor Company Name.
* Add a front-end filter bar (Sponsorship / Language / Country).
* Resolve profile photos in the background (WP-Cron) instead of during page
  render, with initials placeholders and a "Refresh photos" button — fixes
  missing photos at scale.
* Align card detail fields on shared rows via CSS subgrid; stack the Languages
  label above its pills.
* Documented external services; hardened input sanitisation (`wp_unslash`).
* Set plugin author to Maciej Pilarski.

= 1.0.0 =
* Initial release: settings page, Airtable REST client with caching, the
  `[credits_program_mentors]` shortcode, and a matching editor block (with live
  preview) — both offering grid and table layouts.

== Upgrade Notice ==

= 1.5.0 =
The country map is now a Leaflet/CARTO map matching the Education Programs Map
plugin. The map loads basemap tiles from CARTO (see External services).

= 1.4.1 =
Larger default profile photos (116px).

= 1.4.0 =
Restyles the mentor directory to match the Education Student Stories plugin for
a consistent site design.

= 1.3.2 =
Fixes clicking a country on the map not filtering the list.

= 1.3.1 =
The country map can now be zoomed, panned, and clicked to filter the list by
country.

= 1.3.0 =
Adds an optional world map of mentor countries, toggleable in the plugin
settings.

= 1.2.1 =
Fixes profile photos not showing for mentors who also had another link field
filled in.

= 1.2.0 =
Profile photos now load directly from WordPress.org in the browser, fixing
missing photos on large mentor lists and removing the background cache.

= 1.1.0 =
Shows all active mentors with a Sponsored badge, adds front-end filters, and
resolves profile photos in the background to fix missing photos on large lists.
