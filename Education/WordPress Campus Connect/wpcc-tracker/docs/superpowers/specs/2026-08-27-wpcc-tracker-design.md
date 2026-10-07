# WPCC-Tracker — design

**Date:** 2026-08-27
**Status:** approved, ready for implementation planning

A WordPress plugin that renders WordPress Campus Connect programme data as a set of
blocks, for <https://nisabareportingp2.wpcomstaging.com/>. It is the Campus Connect
counterpart to `wpcredits-tracker`, and reproduces the numbers on the **Stats** tab of
the *INTERNAL WordPress Campus Connect Events Tracking Sheet*.

---

## 1. Where the data comes from

### 1.1 The problem

The Stats sheet reports five headline figures and a per-region table. Three of them
cannot be derived from any public WordPress.org API:

| Stats figure | Public REST API |
| --- | --- |
| Completed, Scheduled | available |
| **In setup & Planning** | **absent** |
| **Total Attendees** (actual) | **absent** |
| **Region** breakdown | **absent** |

The cause is a single design decision upstream. `WordCamp_Loader::get_public_post_statuses()`
returns exactly `['wcpt-scheduled', 'wcpt-closed']`, so the 46 events in the planning
pipeline are never exposed. `/wp-json/wp/v2/wordcamps/<id>` returns `meta: []` and omits
`Actual Attendees` entirely; `Host region` is present but empty on every record, at the
source rather than in any sync.

### 1.2 The source that does have it

`wordcamp-reports` on central.wordcamp.org ships a purpose-built **Campus Connect
Details** report
([`class-campusconnect-details.php`](https://github.com/WordPress/wordcamp.org/blob/production/public_html/wp-content/plugins/wordcamp-reports/classes/report/class-campusconnect-details.php)).
Its column order is character-for-character the Stats sheet's RawData header, and it
selects on `meta_query event_subtype = campusconnect` — the authoritative discriminator,
not a title match.

Verified 2026-08-27 by running the report on Central: **142 events**, and the sum of
`Actual Attendees` is **6,490** — an exact match with the sheet. The sheet is generated
from this report.

The report is admin-only. `render_admin_page()` gates on `current_user_can( CAPABILITY )`,
and `wordcamp-reports/index.php` registers REST routes and shortcodes only for report
classes declaring `$rest_base` / `$shortcode_tag`. `CampusConnect_Details` and its parent
`WordCamp_Details` declare neither.

### 1.3 Chosen architecture

The plugin reads **Airtable only** and holds no Central credentials.

| Airtable source | Supplies | Rows |
| --- | --- | --- |
| New table `Campus Connect Events`, mirroring the Campus Connect Details columns | Status, dates, Institution Name, City, Country, **Actual Attendees** | 142 |
| Existing `WordCamps` (`tblLBVsO4rU2oj2Ki`) | Latitude, Longitude, Site URL | 71 (Campus Connect subset) |

Both live in **`appoiPJkMFdnJEmfa`** ("WordCamp Central Reports"), so the plugin needs one
base and one PAT and the join stays inside a single base. The alternative — reusing the
`WPCC Manual Upload` base (`appRjSbq3oZYlTwrp`), whose one table already has this exact
shape — was rejected: it would force the plugin to read two bases, and that table is a
dated one-off import ("Last Import 02/12/2026") rather than a maintained surface.

The two join on the WordCamp post ID — the report's `ID` column against the `WordCamp ID`
merge key. Verified: 142 distinct IDs, zero duplicates. No fuzzy name matching.

Only completed and scheduled events carry coordinates, which is exactly the set the map
should plot; planning events have no venue fixed yet and are deliberately unmapped.

The new table is seeded from a report export now. In parallel — and not blocking this
plugin — a PR to `WordPress/wordcamp.org` adds `$rest_base` + `rest_callback` (behind
`current_user_can( CAPABILITY )`) to `CampusConnect_Details`, so `wordcamp-airtable-connector`
can refill the table automatically. The plugin is unchanged either way.

---

## 2. Normalisation

All rules live in code as constants with filters, not in the database.

### 2.1 Status buckets

The report emits 11 statuses. They collapse to three buckets plus an excluded set:

| Bucket | Statuses | Count on 2026-08-27 |
| --- | --- | --- |
| Completed | `Closed` | 56 |
| Scheduled | `Scheduled` | 15 |
| In Planning | `In Pre-Planning`, `Needs Vetting`, `Needs Orientation/Interview`, `Interview/Orientation Scheduled`, `Approved for Pre-Planning Pending Agreement`, `Needs to Fill Out Listing`, `On Hold` | 46 |
| Excluded from headline figures | `Cancelled` (12), `Declined` (13) | 25 |

The In Planning bucket sums to 46, matching the sheet's regional "In Planning" total
exactly. (The sheet's *headline* says 49; its own regional table says 46. The two
disagree in the source spreadsheet. This plugin uses 46, the value that reconciles.)

Completed 56 and Scheduled 15 exceed the sheet's 55 and 14 because the sheet was last
refreshed 2026-08-25 and two events have since changed state.

Filter: `wpcct_status_buckets`.

### 2.2 Country to region

A bundled map produces the sheet's five regions. Validated against the sheet:

| Region | Countries | Live events | Sheet |
| --- | --- | --- | --- |
| Asia | India, Indonesia, Bangladesh, Malaysia, Philippines, Nepal, Pakistan, Japan, Taiwan, Hong Kong, United Arab Emirates, Lebanon | 67 | 67 |
| Europe | Spain, Italy, Croatia, Poland, Ukraine | 19 | 19 |
| Latin America and Caribbean | Costa Rica, Guatemala, Nicaragua, Brazil, El Salvador | 11 | 11 |
| North America | United States | 2 | 2 |
| Africa | Uganda, Nigeria, Egypt, Namibia, Tanzania | 15 | 16 |

Africa is one short; the residual is a row with a blank Country that the sheet's author
assigned by hand. The plugin does not guess — see 2.4.

`"Maharashtra, India"` (a state in the country field) must alias to India for Asia to
reach 67. Aliases live beside the region map.

Filters: `wpcct_region_map`, `wpcct_country_aliases`.

### 2.3 Fields that must not be trusted

`Number of Anticipated Attendees` is free text. Real values include `"80-100"`,
`"40-60 attendees"` and `"Ideally dozens."`. Summing it naively yields 596,477,463.

It is stored as its raw string, shown per event, and **never aggregated**. Attendance
totals come only from `Actual Attendees`, which is clean, integer, and present only on
`Closed` rows.

### 2.4 Rows that must be dropped

Three events name fictional countries — Tomorrowland, Atlantis, Wonderland — with
matching institution names ("Tomorrow Campus", "Atlantis Atlantide"). Two are in planning
statuses, so they inflate both the planning count and the institution count.

A denylist drops them. **These counts are pre-denylist**: the three rows are On Hold, Needs Orientation/Interview and Cancelled, so once dropped the planning bucket is **44**, not 46, and Cancelled is **11**, not 12. The plugin will therefore report 44 in planning where the sheet reports 46 — the difference is exactly the two fictional test events, and the plugin's figure is the more accurate one. Any country absent from both the region map and the denylist is
**excluded from regional roll-ups and listed on the admin screen**, so new junk and new
genuine countries both surface rather than being silently absorbed. Four rows have no
start date and are excluded from the timeline only.

Filter: `wpcct_excluded_rows`.

---

## 3. Components

Mirrors `wpcredits-tracker`: server-rendered blocks, no build step, bundled libraries,
one synced option blob, a seed file so the plugin renders before its first sync.

**Amended 2026-08-27:** the recurring sync runs **daily**, not weekly, on core's built-in
`daily` schedule. The plugin no longer registers a custom `weekly` schedule, and
`WPCCT_Sync::maybe_schedule()` retires the old `wpcct_cron_weekly` event on any install
that still carries it.

```
wpcc-tracker.php            bootstrap, constants
includes/
  class-wpcct-sync.php      Airtable → wpcct_data, daily WP-Cron
  class-wpcct-normalize.php status buckets, region map, aliases, denylist
  class-wpcct-render.php    per-section HTML builders
  class-wpcct-block.php     block + category registration
  class-wpcct-settings.php  PAT, base/table IDs, manual sync, unmapped-country report
assets/
  css/tracker.css           scoped under .wpcct-tracker
  js/tracker.js             per-section initialisers
  lib/                      Chart.js 4.4.1, Leaflet 1.9.4
data/seed.json              snapshot for first render
```

Each unit is independently testable: `class-wpcct-normalize.php` is pure functions over
arrays and carries the reconciliation assertions from section 2; `class-wpcct-sync.php`
is the only code that talks to the network; `class-wpcct-render.php` reads a normalised
blob and never queries.

Prefix `WPCCT_` / `wpcct_` / `.wpcct-`. Text domain `wpcc-tracker`.

### 3.1 Data flow

```
Airtable (2 tables)
  → WPCCT_Sync::run()            daily cron, single write at the end
  → WPCCT_Normalize::build()     buckets, regions, aliases, denylist, joins on ID
  → option wpcct_data            public, anonymised aggregate blob
  → WPCCT_Render::section($key)  server-rendered HTML + inlined data blob
  → tracker.js                   Chart.js / Leaflet initialisers
```

Nothing personal reaches `wpcct_data`. Organizer names and e-mail addresses are available
in the report and are deliberately not imported.

### 3.2 Blocks

Category **WPCC-Tracker**. Each stands alone; a combined block composes the same
renderers rather than duplicating them.

| Block | Shows |
| --- | --- |
| `wpcc-tracker/scale` | Stat tiles: completed all time, completed this year, scheduled now, in setup & planning, total attendees, countries |
| `wpcc-tracker/regions` | The sheet's regional table as a grouped bar chart, table beneath |
| `wpcc-tracker/map` | Leaflet, marker per city, popup with institution, date, attendance |
| `wpcc-tracker/timeline` | Events per month, completed vs scheduled, cumulative attendance line |
| `wpcc-tracker/pipeline` | All 11 raw statuses as a funnel, **including Cancelled and Declined** |
| `wpcc-tracker/events` | Recent and upcoming events: institution, city, date, attendance |
| `wpcc-tracker/full` | Composes all six |

The pipeline block is the only place Cancelled and Declined appear. They are 25 of 142
events and a real signal about conversion, but they stay out of every headline figure,
as the sheet has them.

Every block renders a provenance footer: *"WordCamp Central via Airtable · data
from Central &lt;date&gt;"*.

**Amended 2026-10-07:** the footer reports the age of the **source** data, not of
this plugin's last run. Those are two hops - Central to Airtable, performed by
`wordcamp-airtable-connector`, then Airtable to here - and only the first says
anything about how old the figures are. The original footer reported the second,
which put a one-day-old date over an event list that had not been refreshed from
Central in 41 days.

The source date comes from a `Synced At` column on the Campus Connect Events
table (`fldVFkCpdFRctL8L4`, dateTime, UTC), added 2026-10-07 and stamped by the
connector on every row it writes. The tracker takes the newest value across the
rows. When no row carries one, Central has never been read successfully and the
footer says exactly that, falling back to Airtable's `createdTime` for the date
the rows were first loaded. It never falls back to this plugin's own run date.

### 3.3 Error handling

- **Sync failure** — last good `wpcct_data` is retained and rendered; the error is stored
  in `wpcct_last_error` and shown on the settings screen only. Front-end never shows a
  stack trace or an empty dashboard.
- **No sync yet** — `data/seed.json` renders, footer reads "seed data".
- **Stale data** — when the **source** data is older than 14 days the provenance
  footer says so, with the age in days. It does not hide the numbers. The
  threshold is deliberately applied to the Central-to-Airtable hop, because a
  daily Airtable read can never go stale on its own and reporting it was the
  defect this rule was meant to catch.
- **Source never written** — when no Airtable row carries a `Synced At` value the
  footer is always flagged, whatever the age, because "never refreshed from
  Central" is the fact that matters rather than the number of days.
- **Unmapped country** — excluded from regional roll-ups, counted in totals, listed on
  the settings screen.
- **Unrecognised status** — counted as excluded, so present in no headline figure, and
  listed on the settings screen. Added 2026-10-07. `status_bucket()` falls back to excluded
  for anything it does not know, which is the safe direction but was a silent one: the
  report renamed `Cancelled` to `Canceled` between the two fixtures and nothing said so.
  The totals happened to survive, both spellings belonging in the excluded bucket anyway;
  had it been a planning stage the planning figure would simply have been short.
  `is_known_status()` answers what `status_bucket()` cannot, and `build()` reports it in
  `unmapped_statuses`. It reports only: an unrecognised status never changes where its
  event is counted.
- **Airtable partial page** — the sync is resumable and state-machine driven, as
  `WPCT_Sync` is; a partial run never overwrites the blob.

### 3.4 Testing

- `WPCCT_Normalize` unit tests assert the reconciliation table in section 2.2 and the
  bucket counts in 2.1 against a fixture copy of the 142-row export.
- A regression test asserts `Number of Anticipated Attendees` is never summed.
- A test asserts no field outside the allowed list reaches `wpcct_data`.

---

## 4. Out of scope

- Sponsor, speaker and session data — present in the base, not part of the Stats sheet.
- Per-event detail pages. The `events` block links out to each event's own site.
- Writing anything back to Airtable or to Central.
- Changing `wordcamp-airtable-connector`. The upstream REST PR is tracked separately.

---

## 5. Open questions

None blocking. The upstream PR's shape may change in review, but it does not affect this
plugin's data contract.
