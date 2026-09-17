# Post-graduation activity tracker (#137)

Measures whether WordPress Credits graduates **keep contributing to WordPress
after they graduate** — the strongest evidence of the program's long-term
impact, distinct from the self-reported "plan to keep contributing."

- **Issue:** [#137](https://github.com/WordPress/WPCredits/issues/137)
- **Script:** [`scripts/post_grad_snapshot.py`](../scripts/post_grad_snapshot.py)
- **Workflow:** [`.github/workflows/snapshot-post-grad.yml`](../.github/workflows/snapshot-post-grad.yml)
- **Store:** private Airtable table **`Post-Grad Snapshots`** (`tblwTv3G4WYIRztTG`) in base `appIzQKfwTn5dyPVp`

## How it works

Each WordPress.org profile page carries, in its raw HTML (no JavaScript needed):

- a **"Recent impact" panel** with trailing-window contribution counts —
  **Last 30 days / Last 90 days / Last 12 months**;
- a **"Completed courses" panel**, one entry per Learn course with the course
  link and `Completed <Month D, YYYY>` — a **day-level** date, and the course
  slug says **which track** was completed; and
- a **"Credits Graduate · since \<Month Year\>" badge**, month granularity only.

One read-only scrape per graduate therefore yields *activity*, *grad date* and
*track*. The graduate roster (and each username) comes from Airtable Students
Reports (status `Graduate`, field `WordPress Profile`); the script reuses
`build_dashboard.py`'s Airtable + parsing helpers.

### Graduation date: the courses panel first, then the badge

Completing a Credits track course **is** the graduation event, so the courses
panel's date is the graduation date. The badge carries only a month, and pinning
it to the month end (the conservative choice, so the 90-day gate cannot overlap a
late-in-month graduation) makes it read **late**. Measured against the course
date on **119 real graduate profiles**: median **8 days late**, mean 10, max 28,
and **never early**.

The badge is not redundant — on that sample it was present on 69 profiles versus
65 with a dated course. So the cascade is:

| Order | Source | Precision |
|---|---|---|
| 1 | `course` — Learn completion on the profile | **day** |
| 2 | `badge` — "since \<Month Year\>", pinned to month end | month (reads ~8–10d late) |
| 3 | `airtable` — earliest Form 3 date → Internship End Date | day, but only ~55% coverage |

`Grad Date Source` records which one answered, so the precision behind any
figure stays auditable.

Coverage caveat: only ~58% of today's graduates carry any wp.org evidence at
all, so roughly **45% still fall back to Airtable**. That is a programme
data-quality matter — badges not awarded, or a wrong profile URL — not something
the script can fix.

### Tracks, and adding new ones

Airtable keeps the track in the student's **status** field (`In Sensei`,
`In Sensei 50h`, `Developer Track`, `Designer Track`…), and graduating
**overwrites that status with `Graduate`**. There is no track field and no
graduation-date field in the base, so graduation destroys the track. The
profile's course slug is the only surviving record of it, which is what makes a
per-track retention curve possible at all.

**More tracks are coming, and the plugin's Track Builder (1.100.0) lets a
manager define one in the WordPress admin with an arbitrary Learn course URL and
an arbitrary key** — there is no slug naming convention to rely on
(`wordpress-credits-designer`, with no `-track` suffix, is an example in the
plugin's own tests), and Track Builder stores its definitions as WordPress posts
that this Python job cannot read.

So the track list is **data, not code**: `data/credits_tracks.json`. Adding a
track is a one-line entry — Learn course slug, the plugin's track key, and the
Airtable status students carry while active — with **no code change**.

Two canaries make a forgotten track loud rather than silent. Both appear in
`data/post_grad_summary.json` and in the run log, and both must be empty on a
healthy run:

- **Canary A** — a graduate completed a Credits-branded Learn course that is
  neither registered nor declared a non-track course. Catches a new track whose
  slug mentions "credits".
- **Canary B** — Airtable holds a status that is neither a declared non-track
  state nor a registered track's status. This one **cannot** be evaded by an
  unusual slug, because a student on a new track always carries its status. The
  polarity is deliberate: a new track is automatically "not a declared non-track
  state", so it surfaces without anyone remembering to update this script.

If a canary fires, the fix is to add the track to `data/credits_tracks.json` (or
list the course under `non_track_courses`). Until then those graduates fall back
to the month-end badge and record no track.

### Key definitions

- **"A contribution" = any wp.org contribution** (props, translations, forum,
  commits…). It is the panel's own "contributions" number and **cannot** be
  filtered to translations only.
- **Post-graduation-only rule (the grad-date gate):** a graduate is only
  *measurable* once the reading window is **fully after** graduation, i.e.
  `today − grad_date ≥ 90 days`. More recent graduates are "not yet measurable"
  because their 90-day window would still overlap the program. This gate is what
  keeps in-program activity out of the number.
- **Window length ≠ tracking horizon.** wp.org offers only 30/90/365-day
  readings — there is *no* 6-month reading. The 6-month horizon comes from
  **monthly snapshots tagged by months-since-graduation**, stitched over time.
  That is why a persistent store is required.

### Retroactive vs longitudinal — important

wp.org's windows are anchored to *today*, not to each grad date, and can't be
decomposed by date or type. Consequences:

- A single scrape's "active in last 90 days" answers **"are they active *now*?"**
  — **not** "did they contribute in their first 6 months post-graduation?"
- For graduates who finished months ago, that 90-day window now sits *past*
  their early post-grad period, so it **misses** early post-grad activity. (Seen
  in practice: April-2026 grads showing `90d = 0` but `365d = 7–17` — they
  contributed earlier, then went quiet.)
- Therefore the clean 6-month retention curve can only be built **going
  forward** from our own monthly snapshots — fully for future graduates, and
  partially (from the current month on) for the existing backlog. Early history
  for the backlog is **unrecoverable**.

## Data model — `Post-Grad Snapshots`

One row per graduate per monthly snapshot.

| Column | Type | Notes |
|---|---|---|
| `Snapshot` | text (primary) | Row key: `<username> · <snapshot date>` |
| `WP Username` | text | wp.org handle |
| `Snapshot Date` | date | When this snapshot was taken |
| `Grad Date` | date | Graduation date — day-level when `Grad Date Source` is `course` |
| `Grad Date Source` | select | `course` / `badge` / `airtable` / `unknown` |
| `Track` | select | Track completed: `150h` / `50h` / `dev` / `design` / … — empty when no registered track completion is on the profile |
| `Months Since Grad` | number | Whole months, grad → snapshot |
| `Recent 30d` | number | wp.org "Last 30 days" contributions |
| `Recent 90d` | number | wp.org "Last 90 days" contributions (drives the gated metric) |
| `Recent 365d` | number | wp.org "Last 12 months" contributions |

Grad date preference: **course → badge → Airtable** (see the cascade table
above).

`Track` values grow as tracks are added. Airtable's `typecast` creates missing
**select choices** automatically, so a new track needs no Airtable change — but
it does **not** create missing **fields**, so `Track` itself must exist on the
table or the write fails.

## Running it

**Automated (default):** the monthly workflow runs at 07:00 UTC on the 1st and
writes one snapshot row per graduate to the Airtable table.

**Manual, report-only (writes nothing) — a quick read of the current number:**
GitHub → Actions → *Snapshot Post-Grad Activity* → **Run workflow** (leave
`report_only` checked).

**Manual, force a real write now:** same, but set `report_only` to `false`.

**Local run (for development):**

```bash
python3 -m venv ~/.wpcredits-venv && ~/.wpcredits-venv/bin/pip install requests
cd /path/to/WPCredits
read -rs AIRTABLE_PAT && export AIRTABLE_PAT && \
  ~/.wpcredits-venv/bin/python scripts/post_grad_snapshot.py --report-only && \
  unset AIRTABLE_PAT
```

Flags: `--report-only` (scrape + print, write nothing), `--limit N` (first N
graduates only), `--delay S` (seconds between profile fetches, default 1.0).
Writing requires the env var `POST_GRAD_TABLE_ID`; without it, rows fall back to
a local `.gitignored` JSONL.

## Automation configuration

| What | Where | Value |
|---|---|---|
| Airtable token | GitHub secret `AIRTABLE_PAT` | Needs `data.records:read` **and** `data.records:write` on base `appIzQKfwTn5dyPVp` |
| Target table id | GitHub **variable** `POST_GRAD_TABLE_ID` | `tblwTv3G4WYIRztTG` |
| Schedule | workflow `cron` | `0 7 1 * *` (monthly) |

## Privacy

This is inherently per-graduate data. The raw table is **private** and must
stay so — this repo is **public**, so per-graduate rows are never committed
here (see `.gitignore`). On any public surface the metric may appear **only as
an aggregate** (a headline % + counts, never a named list), consistent with the
#132 privacy slimming. Per-student detail belongs in the private dashboard
(#109).

## Maintenance & fragility

- **Scraping is brittle.** If wp.org changes the "Recent impact" markup, the
  window regex silently yields `None`; the run logs a **parse-failure count** as
  a canary. If that number spikes, update `parse_window()` /
  `parse_badge_grad_date()` / `parse_completed_courses()`.
- **Watch `grad_date_sources` in the summary.** A fall in `course` (or a rise in
  `badge`) between runs means either the courses-panel markup moved or course
  completions stopped being recorded. Treat it as a canary, not as noise.
- **Watch both track canaries** (`unregistered_courses`,
  `unregistered_track_statuses`). Non-empty means a track exists that
  `data/credits_tracks.json` has not been told about; its graduates are on the
  less precise badge date with no track until it is added.
- The **badge** depends on graduates actually receiving the "Credits Graduate"
  badge. Missing badge *and* no course completion → the script falls back to the
  Airtable grad date and records `Grad Date Source = airtable`.
- **A new track needs one data edit, not a release.** Add it to
  `data/credits_tracks.json`. If a track launches and nobody does, Canary B
  fires on the first run after a student is put on it — which is before any of
  its students can graduate, so there is time to fix it.
- **The `Track` field must exist on the Airtable table.** `typecast` adds
  missing select *choices* but not missing *fields*.
- Early numbers are **low and noisy** — the graduation wave is recent. Don't
  present a thin number as settled.

## Roadmap

- **Phase 1 (done):** window+gate scraper, grad-date gate, report-only run.
- **Phase 2 (done):** monthly persistence to the private Airtable table.
- **Phase 3:** the 6-month retention curve (share still contributing at months
  1/2/3/6) + an aggregate card on the dashboard, once several monthly snapshots
  have accrued.
