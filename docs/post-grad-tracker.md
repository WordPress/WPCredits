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
  **Last 30 days / Last 90 days / Last 12 months**; and
- a **"Credits Graduate · since \<Month Year\>" badge** giving the graduation
  date at month granularity.

One read-only scrape per graduate therefore yields both *activity* and *grad
date*. The graduate roster (and each username) comes from Airtable Students
Reports (status `Graduate`, field `WordPress Profile`); the script reuses
`build_dashboard.py`'s Airtable + parsing helpers.

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
| `Grad Date` | date | Graduation date (month granularity) |
| `Grad Date Source` | select | `badge` / `airtable` / `unknown` |
| `Months Since Grad` | number | Whole months, grad → snapshot |
| `Recent 30d` | number | wp.org "Last 30 days" contributions |
| `Recent 90d` | number | wp.org "Last 90 days" contributions (drives the gated metric) |
| `Recent 365d` | number | wp.org "Last 12 months" contributions |

Grad date preference: **badge first**, Airtable cascade (earliest Form 3 date →
Internship End Date) only as fallback.

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
  `parse_badge_grad_date()`.
- The **badge** depends on graduates actually receiving the "Credits Graduate"
  badge. Missing badge → the script falls back to the Airtable grad date and
  records `Grad Date Source = airtable`.
- Early numbers are **low and noisy** — the graduation wave is recent. Don't
  present a thin number as settled.

## Roadmap

- **Phase 1 (done):** window+gate scraper, grad-date gate, report-only run.
- **Phase 2 (done):** monthly persistence to the private Airtable table.
- **Phase 3:** the 6-month retention curve (share still contributing at months
  1/2/3/6) + an aggregate card on the dashboard, once several monthly snapshots
  have accrued.
