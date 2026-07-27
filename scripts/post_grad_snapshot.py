#!/usr/bin/env python3
"""Post-graduation activity snapshotter — issue #137 (PROTOTYPE).

Appends a dated snapshot of each graduate's WordPress.org profile activity to a
time-series store, so we can later measure the headline metric:

    % of graduates who keep contributing to WordPress AFTER they graduate

computed as a *delta from each graduate's baseline* (their activity the first
time we observe them). This is genuinely new work: it can't be back-computed,
because there's no record of a graduate's activity after their graduation date
and the profile feed is capped at 30 items with no pagination.

This script is deliberately NOT wired into build_dashboard.py's render path. The
dashboard only *surfaces* the aggregate later, once there is history. It reuses
build_dashboard.py's Airtable + profile-scraping helpers (importing that module
is side-effect-free — its main() is guarded by __main__).

--------------------------------------------------------------------------------
PROTOTYPE DECISIONS — revisit with @maciejpilarski, who owns #137
--------------------------------------------------------------------------------
These are defensible defaults so there's something concrete to react to, NOT
settled calls. See POST_GRAD_TRACKER_KICKOFF.md "First decisions to make".

1. "A contribution" = translation strings (suggested + translated + reviewed).
   Empirically the only signal this population produces: a 45-student sample
   returned ZERO props. We store the raw components every snapshot so the
   definition can be refined later WITHOUT re-scraping history.

2. Storage = a JSONL time-series (one line per graduate per snapshot). BUT this
   repo is PUBLIC, and a per-graduate activity time-series (username <-> graduate
   <-> activity over time) must not live in public git — that would regress the
   privacy slimming from #132. So:
     * raw per-graduate snapshots -> PRIVATE store (this JSONL is .gitignored;
       in production it should be a private Airtable table in the same base, or
       the private dashboard's domain, #109).
     * only an AGGREGATE summary (headline % + counts, NO handles) is ever
       written to a publishable path.
   Choosing the production raw store (private Airtable table vs private file)
   is decision #1 for the Maciej sync — an Airtable write needs new PAT scope.

3. Baseline = the first snapshot we record for a graduate is their starting
   line (is_baseline=True). Because the feed only shows 30 items, this is a
   starting line, not full history at the grad date — that's fine, the metric
   is delta-from-baseline.

4. Cadence = weekly, piggybacking the existing automation via a sibling Action
   (.github/workflows/snapshot-post-grad.yml). Weekly is noisy but the grad wave
   is recent so numbers are low either way; monthly is a one-line cron change.

Usage:
    AIRTABLE_PAT=... python scripts/post_grad_snapshot.py
    AIRTABLE_PAT=... python scripts/post_grad_snapshot.py --dry-run --limit 20
"""
import argparse
import json
import sys
import time
from datetime import date
from pathlib import Path

# Reuse the dashboard's building blocks. Importing is safe: build_dashboard only
# defines constants/functions at module scope (main() runs under __main__).
sys.path.insert(0, str(Path(__file__).resolve().parent))
import build_dashboard as bd  # noqa: E402

# Raw per-graduate time-series (PRIVATE — see decision #2; .gitignored).
RAW_STORE = Path(__file__).resolve().parent.parent / "data" / "post_grad_snapshots.jsonl"
# Aggregate, publishable summary (no handles).
SUMMARY_PATH = Path(__file__).resolve().parent.parent / "data" / "post_grad_summary.json"

GRADUATE_STATUS_KEY = bd.status_key("Graduate")

# Be polite to profiles.wordpress.org: small delay between profile fetches.
FETCH_DELAY_SECONDS = 1.0


def log(msg):
    print(msg, file=sys.stderr)


def load_form3_dates(lessons_records):
    """lesson_id -> Form 3 (end-of-program) submission date. Mirrors the build."""
    out = {}
    for rec in lessons_records:
        is_form3 = any(
            bd.get_field_value(rec, bd.FIELDS["lessons"][f]) is not None
            for f in ("f3_impact", "f3_recommend", "f3_keep")
        )
        if is_form3:
            d = bd.parse_iso_date(rec.get("createdTime"))
            if d:
                out[rec["id"]] = d
    return out


def graduation_date(rec, form3_dates):
    """Graduation date cascade, matching build_dashboard.py:
    (future "Graduated on" field, not present yet) -> earliest Form 3 date ->
    Internship End Date. Returns a datetime.date or None.
    """
    lesson_ids = bd.get_field_value(rec, bd.FIELDS["students_reports"]["lessons"]) or []
    f3 = [form3_dates[lid] for lid in lesson_ids if lid in form3_dates]
    if f3:
        return min(f3)
    return bd.parse_iso_date(
        bd.get_field_value(rec, bd.FIELDS["students_reports"]["internship_end_date"])
    )


def iter_graduates(pat):
    """Yield {wp_username, grad_date} for each graduate that has a WP profile.

    Deduplicates by username (a person can have multiple reports), keeping the
    earliest graduation date seen.
    """
    log("Fetching Students Reports + Lessons from Airtable...")
    reports = bd.fetch_all_records(
        bd.BASE_ID, bd.TABLES["students_reports"],
        list(bd.FIELDS["students_reports"].values()), pat,
    )
    lessons = bd.fetch_all_records(
        bd.BASE_ID, bd.TABLES["lessons"],
        list(bd.FIELDS["lessons"].values()), pat,
    )
    form3_dates = load_form3_dates(lessons)

    by_username = {}
    for rec in reports:
        if bd.status_key(bd.get_field_value(rec, bd.FIELDS["students_reports"]["status"])) != GRADUATE_STATUS_KEY:
            continue
        wp_profile = bd.get_field_value(rec, bd.FIELDS["students_reports"]["wp_profile"])
        username = bd.extract_wp_username(wp_profile)
        if not username:
            continue
        grad_date = graduation_date(rec, form3_dates)
        grad_iso = grad_date.isoformat() if grad_date else None
        prev = by_username.get(username)
        # Keep the earliest known graduation date for this person.
        if prev is None or (grad_iso and (prev["grad_date"] is None or grad_iso < prev["grad_date"])):
            by_username[username] = {"wp_username": username, "grad_date": grad_iso}
    return list(by_username.values())


def load_history(path):
    """Read the JSONL store into a list of snapshot dicts (empty if absent)."""
    if not path.exists():
        return []
    rows = []
    with open(path) as f:
        for line in f:
            line = line.strip()
            if line:
                rows.append(json.loads(line))
    return rows


def baseline_usernames(history):
    """Usernames that already have at least one recorded snapshot."""
    return {row["wp_username"] for row in history}


def compute_metric(history):
    """Headline metric from the full history.

    For each graduate: baseline = earliest snapshot's total; if there is any
    LATER snapshot, they are "contributing after graduation" when that later
    total exceeds the baseline total. Graduates still at only a baseline are
    not yet measurable and are excluded from the denominator.
    """
    by_user = {}
    for row in history:
        by_user.setdefault(row["wp_username"], []).append(row)

    eligible = 0        # have a baseline AND >= 1 later snapshot
    contributing = 0
    for snaps in by_user.values():
        snaps.sort(key=lambda r: r["snapshot_date"])
        baseline_total = snaps[0]["strings"]["total"]
        later = snaps[1:]
        if not later:
            continue
        eligible += 1
        if max(s["strings"]["total"] for s in later) > baseline_total:
            contributing += 1

    pct = round(100 * contributing / eligible) if eligible else None
    return {
        "graduates_tracked": len(by_user),
        "measurable": eligible,          # have a baseline + a later snapshot
        "contributing": contributing,
        "pct_contributing_after_graduation": pct,
    }


def take_snapshot(graduates, history, snapshot_date, delay, limit=None):
    """Scrape each graduate's profile and build snapshot rows for this run."""
    already_baselined = baseline_usernames(history)
    rows = []
    targets = graduates[:limit] if limit else graduates
    for i, g in enumerate(targets, 1):
        username = g["wp_username"]
        stats = bd.fetch_translation_stats(username)  # {suggested, translated, reviewed, total}
        rows.append({
            "snapshot_date": snapshot_date,
            "wp_username": username,
            "grad_date": g["grad_date"],
            "is_baseline": username not in already_baselined,
            "strings": stats,
        })
        log(f"  [{i}/{len(targets)}] {username}: {stats['total']} strings"
            f"{' (baseline)' if username not in already_baselined else ''}")
        if delay and i < len(targets):
            time.sleep(delay)
    return rows


def main():
    parser = argparse.ArgumentParser(description="Snapshot graduates' post-graduation WP activity (#137).")
    parser.add_argument("--dry-run", action="store_true",
                        help="Scrape and report, but do not write the raw store or summary.")
    parser.add_argument("--limit", type=int, default=None,
                        help="Only snapshot the first N graduates (for quick local testing).")
    parser.add_argument("--delay", type=float, default=FETCH_DELAY_SECONDS,
                        help=f"Seconds between profile fetches (default {FETCH_DELAY_SECONDS}).")
    args = parser.parse_args()

    pat = bd.get_airtable_pat()
    snapshot_date = date.today().isoformat()

    graduates = iter_graduates(pat)
    log(f"Graduates with a WP profile: {len(graduates)}")
    if not graduates:
        log("No graduates to snapshot — nothing to do.")
        return 0

    history = load_history(RAW_STORE)
    n_baselines = len(baseline_usernames(history))
    log(f"Existing history: {len(history)} snapshots across {n_baselines} graduates.")

    rows = take_snapshot(graduates, history, snapshot_date, args.delay, args.limit)

    combined = history + rows
    metric = compute_metric(combined)

    # Caveats stay visible on every run (kickoff: don't present a thin number as settled).
    log("")
    log("=== Post-graduation contribution (PROTOTYPE) ===")
    if metric["measurable"] == 0:
        log("This run establishes/extends baselines. The %% becomes measurable once")
        log("graduates have a baseline AND a later snapshot — i.e. from the next run on.")
    log(f"Graduates tracked:        {metric['graduates_tracked']}")
    log(f"Measurable (baseline+1):  {metric['measurable']}")
    log(f"Contributing post-grad:   {metric['contributing']}")
    log(f"Headline %%:               {metric['pct_contributing_after_graduation']}")
    log("Caveat: the graduation wave is recent, so early numbers are low and noisy.")

    if args.dry_run:
        log("\n--dry-run: not writing raw store or summary.")
        return 0

    # Raw per-graduate store — PRIVATE (.gitignored). Append this run's rows.
    RAW_STORE.parent.mkdir(parents=True, exist_ok=True)
    with open(RAW_STORE, "a") as f:
        for row in rows:
            f.write(json.dumps(row, ensure_ascii=False) + "\n")
    log(f"\nAppended {len(rows)} snapshots to {RAW_STORE} (private).")

    # Aggregate summary — publishable (no handles).
    summary = {
        "generated": snapshot_date,
        "definition": "translation strings (suggested+translated+reviewed) increase vs baseline",
        "prototype": True,
        **metric,
    }
    with open(SUMMARY_PATH, "w") as f:
        json.dump(summary, f, indent=2)
        f.write("\n")
    log(f"Wrote aggregate summary to {SUMMARY_PATH} (safe to publish).")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        sys.exit(1)
