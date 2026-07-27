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

## Prototype built — 2026-07-27 (branch `feature/post-grad-tracker`, no PR)

A working prototype exists for @maciejpilarski to react to. **Not merged; still
his call to own/direct.** What's here:

- `scripts/post_grad_snapshot.py` — the snapshotter. Reuses build_dashboard's
  Airtable + `fetch_translation_stats` helpers (import is side-effect-free).
  Finds graduates, computes each one's grad-date via the same cascade the build
  uses, scrapes translation strings, appends a dated snapshot row, and prints
  the headline % (delta-from-baseline). `--dry-run` / `--limit N` for testing.
- `.github/workflows/snapshot-post-grad.yml` — weekly sibling Action, **manual
  dispatch only** for now (schedule commented out) until storage is decided.
- `data/post_grad_snapshots.jsonl` — raw per-graduate store, **.gitignored**
  (this repo is public; see privacy caveat). `data/post_grad_summary.json` —
  the publishable aggregate.

Verified locally: metric math, baseline flagging, JSONL round-trip (stubbed),
and a live `profiles.wordpress.org` scrape. Not yet run against Airtable
(needs the CI `AIRTABLE_PAT`) — `iter_graduates()` mirrors the build's own
graduate/grad-date logic.

**Prototype defaults picked for you to challenge** (all in the script header):
contribution = increase in translation strings vs baseline; storage = private
JSONL now / private Airtable table the likely production home; cadence = weekly;
baseline = first observation.

**Still open for the Maciej sync — the two that block enabling the schedule:**
1. **Raw storage** — the .gitignored JSONL can't persist on ephemeral CI runners
   and can't be committed publicly. Need a private persistent store (private
   Airtable table in the same base is the leading option; needs PAT **write**
   scope, which the current read-only PAT lacks).
2. **Confirm the contribution definition** — strings-only, or also badges/feed
   items dated after graduation?
