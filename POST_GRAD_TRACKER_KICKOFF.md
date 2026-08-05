# Post-graduation activity tracker — kickoff brief (issue #137)

> Scratch/kickoff doc for a NEW Claude Code session rooted at this repo
> (`/Users/isotta/WPCredits-Tracker`). Not part of the dashboard build.
> Delete or commit as you see fit. Written 2026-07-23.

## Goal

Measure whether graduates **actually keep contributing to WordPress after they
graduate** — the strongest proof of long-term impact. Distinct from the
self-reported "plan to keep contributing" we already show (feedback `keep`,
77%). Headline metric: **% of graduates contributing after graduation.**

## Why it's new work, not a dashboard tweak

It cannot be back-computed. There is no record of a graduate's activity *after*
their graduation date, and without a baseline it can't be reconstructed. This
needs new time-series storage that does not exist yet.

## Home (decided 2026-07-23)

**Inside this repo** (`WPCredits-Tracker`). A new snapshot job + a time-series
data store the weekly Action appends to. NOT part of `build_dashboard.py`'s
render path — the dashboard only *surfaces* the metric later, once there's
history.

## Coordination flag — READ FIRST

Isotta asked **@maciejpilarski** to own #137 (comment on the issue, 2026-07-23).
Sync with Maciej on direction/ownership before building, to avoid parallel work.

## Approach (from the issue)

1. **Baseline at graduation** — capture each graduate's WordPress.org
   contribution state at/near their graduation date.
2. **Periodic snapshots** — recurring (weekly/monthly) snapshots of each
   graduate's profile activity.
3. **Compare** — post-grad activity vs baseline → % contributing after
   graduation (later: volume, time-to-first-contribution, trend).

## What today's investigation (2026-07-23) already settled

These are empirical findings from this repo's data, not assumptions:

- **Props will NOT carry the "a contribution" definition for this population.**
  A 45-student sample of real profiles returned **zero** props. Student
  activity is entirely **Learn-course completions + translation strings**;
  props come from code commits, and the team split is Polyglots 152 / Photos 89
  / Core only 6. So "a contribution" must be defined from **translation
  activity (suggested/translated/reviewed strings), badges, and Learn signals**
  — the exact signals `fetch_translation_stats()` already parses. This
  definition is the crux of the whole tracker.
- **`core.trac.wordpress.org` is behind an anti-bot proof-of-work challenge**
  (403 even on robots.txt). Do NOT try to work around it — changeset/props
  detail is simply not reachable. `profiles.wordpress.org` robots.txt permits
  scraping and the build already scrapes it.
- **The profile activity feed** (`profiles.wordpress.org/{user}/feed/`) is
  **hard-capped at 30 items, no pagination** (`?paged=2` returns the same 30).
  This is *why* you need recurring snapshots rather than one deep scrape — a
  single fetch only ever sees a recent window for active contributors.

## Reusable infrastructure in this repo

- **Profile scraper** — `fetch_translation_stats(wp_username)` in
  `scripts/build_dashboard.py` (~line 334). Fetches
  `https://profiles.wordpress.org/{username}/`, regex-parses
  "Suggested/Translated/Reviewed N strings". Model the snapshotter on this;
  consider also parsing the `/feed/` for dated activity items.
- **Username source** — Students Reports field `wp_profile`
  (`fld2rGCjmvTZg5DLg`); `extract_wp_username(url)` (~line 237) normalises the
  profile URL to a username. ~538 distinct usernames across 658 reports.
- **Graduate identification** — status `"Graduate"` (`GRADUATE_STATUS`, ~line
  488) in Students Reports.
- **Graduation date** — cascade already used in the build (~line 854):
  `"Graduated on"` (future field, may not exist yet) → Form 3 submission date →
  `internship_end_date` (`fldLwLXupWurmimc7`). Use this for each graduate's
  baseline timestamp.
- **Automation** — the weekly Action `.github/workflows/update-dashboard.yml`
  (cron `0 6 * * 1`, `workflow_dispatch`, secret `AIRTABLE_PAT`,
  `pip install requests`). Extend this or add a sibling scheduled workflow.
- **Airtable base** `appIzQKfwTn5dyPVp`.

## First decisions to make (with Maciej)

1. **Storage shape** — new Airtable table (dated rows per graduate) vs a
   committed data file (e.g. JSONL/CSV) the Action appends to each run. Airtable
   keeps it queryable and in the same base; a committed file keeps it in git
   history and needs no PAT write scope.
2. **"A contribution" definition** — given props are absent, likely: any
   increase in suggested/translated/reviewed strings since the previous
   snapshot, and/or new feed activity items dated after graduation. Pin this
   down precisely; it defines the headline number.
3. **Snapshot cadence** — weekly (piggyback the existing Action) vs monthly
   (less noise, the grad wave is recent so early numbers will be low anyway).
4. **Baseline capture** — one-time backfill of current graduates' state as
   "baseline", then forward snapshots. Note: because the feed only shows 30
   items, the baseline is a *starting line*, not full history — that's fine, the
   metric is delta-from-baseline.

## Caveats to keep visible

- Graduation wave is recent → early numbers low/noisy. `log()`/note this on any
  output rather than presenting a thin number as settled.
- Profile scraping is brittle and will need maintenance.
- Privacy: this is inherently per-graduate data. On the PUBLIC dashboard it can
  only ever appear as an **aggregate** (the % headline), never a named list —
  consistent with the privacy slimming done in #132. Per-student detail belongs
  in the private dashboard (#109).

## Status — 2026-08-05 (branch `feature/post-grad-tracker`, no PR)

Isotta owns this (not Maciej, confirmed 2026-08-05). Phase 1 rebuilt around the
window+gate design discovered on the #137 thread. What's here:

- `scripts/post_grad_snapshot.py` — the snapshotter. One read-only scrape per
  graduate reads the profile's **"Recent impact" panel** (Last 30/90 days, 12
  months of contribution counts) and the **"Credits Graduate · since <Month
  Year>" badge** for the graduation date. Reuses build_dashboard's Airtable
  helpers for the roster. `--report-only` / `--limit N` for testing.
- `.github/workflows/snapshot-post-grad.yml` — **monthly** sibling Action.
  `workflow_dispatch` defaults to `report_only` (writes nothing) so a first real
  number can be pulled now; schedule commented out until the Airtable table + the
  `POST_GRAD_TABLE_ID` variable exist.

### Design (decided on #137)
- **Window vs horizon.** wp.org offers only 30/90/365-day readings — there is no
  6-month reading. The 6-month horizon comes from **monthly snapshots tagged by
  months-since-graduation**, stitched over time. That is why a persistent store
  is genuinely required (unlike the "active now" headline, which needs none).
- **"A contribution" = any wp.org contribution** (the panel's own number; can't
  be filtered to translations only).
- **Post-grad-ONLY rule = grad-date gate:** count a graduate only when the window
  is fully after graduation (`today − grad_date ≥ 90d`). Recent grads (<90d) are
  "not yet measurable." This is the piece the ESS plugin's raw `recent90>0` lacks.
- **Storage = private Airtable table** in the same base; **cadence = monthly.**

### Verified locally (2026-08-05)
Metric/gate math + Airtable field mapping (unit tests) and **live
`profiles.wordpress.org` scrapes** — badge grad-date correctly overrides the
Airtable fallback; the three windows parse. Not yet run against Airtable
(needs the CI `AIRTABLE_PAT`) — `iter_graduates()` mirrors the build's own
graduate logic.

### Next
1. **Create the private Airtable table** (`Snapshot Date`, `WP Username`, `Grad
   Date`, `Grad Date Source`, `Months Since Grad`, `Recent 30d`, `Recent 90d`,
   `Recent 365d`), set repo var `POST_GRAD_TABLE_ID`, and give `AIRTABLE_PAT`
   **write** scope. Then flip the monthly schedule on.
2. **Pull a first real number** now via the manual Action (report-only).
3. **Phase 3:** the 6-month retention curve + dashboard aggregate card, once a
   few monthly snapshots have accrued.
