=== WPCC-Tracker ===
Contributors: gomp
Tags: campus connect, wordpress, education, events, statistics
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress Campus Connect programme data rendered as native blocks.

== Description ==

Six standalone blocks plus a combined one, covering scale, regions, a world map,
a timeline, the organiser pipeline and an event list. Data is synced daily from
Airtable, which mirrors the Campus Connect Details report on WordCamp Central.

= Shortcode =

Use `[wpcc_tracker]` to render the combined view - every section in order,
followed by the provenance line - in a classic editor, a widget, or a theme
template. It takes no attributes and is identical to the "WPCC-Tracker (full)"
block. For a single section, use that section's own block.

= Settings =

The plugin adds a top-level **WPCC-Tracker** menu in wp-admin (requires the
`manage_options` capability). There you enter the Airtable personal access
token, base ID and the two table names; the stored token is never displayed
again, and leaving the field blank on save keeps it unchanged. The same screen
has a "Sync now" button with a live progress bar, the time of the last sync,
the last sync error if any, the current headline totals, and a report of any
event countries that could not be matched to one of the five reporting regions.
Until the first successful sync the blocks render bundled seed data.

== Changelog ==

= 1.0.4 =

The bundled seed no longer carries events Central does not publish.

`data/seed.json` ships inside the plugin zip, and the source is now mirrored to
a public repository. It had been built from the whole report, so it held 44
applications still in progress, naming the institution and city behind events
that were declined, cancelled or still being vetted.
`WordCamp_Loader::get_public_post_statuses()` is `['wcpt-scheduled',
'wcpt-closed']`, which is the very reason this plugin exists, and shipping the
rest published exactly what Central withholds.

* Fixed: the seed is built from Closed and Scheduled rows only. Rebuilt from the
  2026-10-07 export: 85 events of 165, with 80 withheld.
* Changed: the seed's planning figure therefore reads 0 until the first real
  sync. That is the honest default for a number this file may not carry, and it
  corrects itself the moment the plugin syncs.
* Changed: `bin/build-seed.php` applies the filter itself rather than leaving it
  to whoever runs it, and says how many rows it withheld and why.

No change to any synced figure. This is the bundled fallback only.

= 1.0.3 =

A Status the bucket map does not recognise is now reported, the way an unmapped
country always has been.

The map's fallback is the excluded bucket, so an unknown Status quietly lands in
no headline figure at all. That is the right direction, because guessing would
be worse, but it was silent. The upstream report renamed "Cancelled" to
"Canceled" between two pulls and nothing announced it. The totals happened to be
unaffected, both spellings belonging in the excluded bucket anyway. Had it been
a planning stage, the planning figure would simply have been short, with nothing
to explain it.

* Added: `unmapped_statuses` in the data blob, and an "Unrecognised statuses"
  table on the settings screen beside the unmapped-countries one. Cancelled and
  Declined are known exclusions and are not listed.
* Added: `WPCCT_Normalize::is_known_status()`, which answers what
  `status_bucket()` cannot, since its fallback makes an unknown value and a
  deliberate exclusion indistinguishable.
* Changed: the bucket map moved to `WPCCT_Normalize::status_map()` so the
  bucketer and the reporter read exactly the same filtered map and cannot
  disagree. The `wpcct_status_buckets` filter is unchanged.
* Nothing is re-bucketed. The surface reports; every total is what it was.
* Added: a second frozen export, `campus-connect-details-2026-10-07.tsv`, with
  assertions pinning the drift between the two. Suite 114 to 151.

= 1.0.2 =

The provenance line now reports how old the Campus Connect data actually is,
rather than when this plugin last read Airtable.

Those are two different hops. WordCamp Central is written to Airtable by the
WordCamp Airtable Connector plugin; this plugin then reads Airtable. Only the
first says anything about the age of the figures, and the footer was reporting
the second. On a dashboard whose Central data had not been refreshed for six
weeks, the footer read "synced yesterday".

* Fixed: the footer reads the newest `Synced At` value on the Airtable rows,
  which the connector stamps whenever it writes from Central, and applies the
  14-day staleness warning to that date instead of to this plugin's own run.
* Fixed: when no row carries a sync stamp, Central has never been read
  successfully. The footer says so plainly and gives the date the rows were
  first loaded, instead of falling back to this plugin's run date and implying
  the figures are current.
* Fixed: a blob with no source dates at all now reads "age of source data
  unknown". It corrects itself on the next sync, and it never borrows a date
  that would overstate freshness.
* Added: the settings screen lists both hops separately, labelled, since the
  front end deliberately shows only the one that matters to a reader.
* Added: 21 assertions covering the provenance branches and the blob plumbing,
  bringing the suite to 114.

= 1.0.1 =
* Replace every em dash with a plain hyphen in the plugin headers, block descriptions, admin notices, front-end strings and code comments.

= 1.0.0 =
* Initial release.
