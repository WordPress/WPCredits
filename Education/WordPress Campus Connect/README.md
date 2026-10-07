# WordPress Campus Connect

Programme figures for **WordPress Campus Connect**, kept current without anybody exporting a
spreadsheet by hand.

Campus Connect events are tracked on central.wordcamp.org. The numbers people quote in reports
have come from a Google Sheet built from a report somebody ran in a browser and pasted in. That
sheet goes stale the moment it is made, and nothing says when it was last touched.

Two components replace that, and they are built to work together:

| Folder | What it is | Version |
| --- | --- | --- |
| [`wordcamp-airtable-connector/`](wordcamp-airtable-connector/) | Reads WordCamp Central and writes Airtable. Also syncs WordCamps, Meetups, sessions, speakers and sponsors, which predate the Campus Connect work. | 1.1.9 |
| [`wpcc-tracker/`](wpcc-tracker/) | Reads Airtable and renders the figures as native blocks: scale, regions, a world map, a timeline, the application pipeline and an event list. | 1.0.4 |

The tracker never talks to Central and holds no credential for it. It reads Airtable and nothing
else.

---

## Why a connector at all

Three of the figures cannot be read from the public WordPress.org API, and that is deliberate
upstream rather than an oversight:

| Figure | Public API |
| --- | --- |
| Completed, Scheduled | available |
| In setup and Planning | absent |
| Actual attendance | absent |
| Region breakdown | absent |

`WordCamp_Loader::get_public_post_statuses()` returns exactly `['wcpt-scheduled', 'wcpt-closed']`.
Every other status is an application still in progress, and Central does not publish those. The
planning pipeline is therefore invisible to anything reading the public API, which is the whole
reason the connector exists and authenticates.

## Two hops, and only one of them tells you how old the numbers are

```
central.wordcamp.org  --(connector)-->  Airtable  --(tracker)-->  dashboard
```

A dashboard that reports when it last read Airtable is answering the wrong question. The tracker
reads Airtable daily, so that date is always recent, while the data behind it is only as fresh as
the last time Central was read.

So the connector stamps `Synced At` on every row it writes, and the tracker reports the newest of
those, applying its staleness warning to that date. When no row carries one, Central has never
been read and the footer says exactly that rather than implying freshness.

## What is not here

`wpcc-tracker/tests/fixtures/` is withheld. The test suite cannot run from this copy; see the
note in that folder for why.

## Requirements

WordPress 6.6 or newer, PHP 7.4 or newer. The connector needs an Airtable personal access token
and, for Campus Connect, an account on central.wordcamp.org holding `view_wordcamp_reports`.
Neither plugin has a build step.
