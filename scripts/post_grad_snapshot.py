#!/usr/bin/env python3
"""Post-graduation activity snapshotter — issue #137.

Tracks whether graduates keep contributing to WordPress AFTER they graduate, for
at least the first 6 months post-graduation. Owned by @peiraisotta (Aug 2026).

HOW IT WORKS (see the #137 thread for the reasoning)
----------------------------------------------------
Each WordPress.org profile page exposes a "Recent impact" panel with trailing
windows — Last 30 days / Last 90 days / Last 12 months — of contribution counts,
plus a "Credits Graduate · since <Month Year>" badge carrying the graduation
date. Both are in the raw HTML (no JS), so one read-only scrape per graduate
gives us activity + grad date together.

Window length != tracking horizon. A single reading only ever looks back 30/90/
365 days from *today* — there is no 6-month reading. We get a 6-month horizon by
taking a snapshot MONTHLY and tagging each by months-since-graduation; the
per-graduate timeline is then reconstructed from the stored rows. That is why
this needs a persistent store (a private Airtable table — this repo is public).

DEFINITIONS (decided on #137)
-----------------------------
* "A contribution" = ANY wp.org contribution (props, translations, forum,
  commits...) — the panel's own "contributions" number. It cannot be filtered
  to translations only.
* Post-graduation-ONLY rule: a graduate is only counted once the reading window
  is fully after graduation, i.e. today - grad_date >= window length. With the
  90-day window that means grad_date >= 90 days ago; more recent graduates are
  "not yet measurable" (their 90-day window would still overlap the program).
  This grad-date gate is the piece the ESS plugin's raw `recent90 > 0` lacks.

RETROACTIVE vs LONGITUDINAL
---------------------------
wp.org windows are anchored to *today*, not to each grad date, and can't be
decomposed by date/type. So a clean month-by-month post-grad curve can only be
built GOING FORWARD from our own monthly snapshots. For the existing backlog we
can only give a best-effort "active in the last 90 days" figure (gated), clearly
labelled partial.

STORAGE
-------
Raw per-graduate rows are PRIVATE (this repo is public — committing them would
regress the #132 privacy slimming). Production store: a private Airtable table
in the same base, written only when POST_GRAD_TABLE_ID is set and the PAT has
write scope. Absent that, rows fall back to a local .gitignored JSONL for dev.
Only an aggregate summary (no handles) is ever safe to publish.

Reuses build_dashboard.py's Airtable + helper functions (importing it is
side-effect-free — its main() is guarded by __main__).

Usage:
    AIRTABLE_PAT=... python scripts/post_grad_snapshot.py --report-only
    AIRTABLE_PAT=... POST_GRAD_TABLE_ID=tblXXXX python scripts/post_grad_snapshot.py
"""
import argparse
import json
import os
import re
import sys
import time
from datetime import date
from pathlib import Path

import requests

# Reuse the dashboard's building blocks. Safe: build_dashboard only defines
# constants/functions at module scope (main() runs under __main__).
sys.path.insert(0, str(Path(__file__).resolve().parent))
import build_dashboard as bd  # noqa: E402

# Local dev fallback store (PRIVATE — .gitignored). Real store is Airtable.
RAW_STORE = Path(__file__).resolve().parent.parent / "data" / "post_grad_snapshots.jsonl"
SUMMARY_PATH = Path(__file__).resolve().parent.parent / "data" / "post_grad_summary.json"

GRADUATE_STATUS_KEY = bd.status_key("Graduate")

# wp.org "active" window used for the headline gate/metric (days).
ACTIVE_WINDOW_DAYS = 90
# Tracking horizon we commit to observing each graduate across (months).
TRACKING_HORIZON_MONTHS = 6
# Be polite to profiles.wordpress.org.
FETCH_DELAY_SECONDS = 1.0

MONTHS = {m: i for i, m in enumerate(
    ["January", "February", "March", "April", "May", "June", "July",
     "August", "September", "October", "November", "December"], start=1)}


def log(msg=""):
    print(msg, file=sys.stderr)


# --------------------------------------------------------------------------- #
# Profile scraping
# --------------------------------------------------------------------------- #
def parse_badge_grad_date(html):
    """Graduation date from the 'Credits Graduate' badge, or None.

    Title looks like: title="Credits Graduate · since October 2025".
    Returns an ISO date string at month granularity (1st of the month).
    """
    m = re.search(
        r'badge-credits-graduate"[^>]*title="[^"]*?since\s+([A-Z][a-z]+)\s+(\d{4})"',
        html,
    )
    if not m:  # tolerate attribute-order / middot-encoding changes
        m = re.search(r'Credits Graduate[^"<]*?since\s+([A-Z][a-z]+)\s+(\d{4})', html)
    if not m:
        return None
    month = MONTHS.get(m.group(1))
    if not month:
        return None
    return date(int(m.group(2)), month, 1).isoformat()


def parse_window(text, label):
    """Contributions count for a Recent-impact window, or None if not present.

    None (vs 0) is meaningful: it flags a profile with no panel OR a markup
    change (canary). The metric treats None as 0 but the run logs the count.
    """
    m = re.search(re.escape(label) + r"\s+([\d,]+)\s+contributions?", text)
    return int(m.group(1).replace(",", "")) if m else None


def scrape_profile(username):
    """One read-only fetch -> grad date (badge) + the three trailing windows."""
    out = {"http": None, "ok": False, "grad_date_badge": None,
           "recent30": None, "recent90": None, "recent365": None}
    url = f"https://profiles.wordpress.org/{username}/"
    try:
        r = requests.get(url, timeout=20, headers={"User-Agent": "WPCredits-Dashboard/1.0"})
    except requests.RequestException as e:
        log(f"  {username}: {e}")
        return out
    out["http"] = r.status_code
    if r.status_code != 200:
        log(f"  {username}: HTTP {r.status_code}")
        return out
    html = r.text
    text = re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", html))
    out["ok"] = True
    out["grad_date_badge"] = parse_badge_grad_date(html)
    out["recent30"] = parse_window(text, "Last 30 days")
    out["recent90"] = parse_window(text, "Last 90 days")
    out["recent365"] = parse_window(text, "Last 12 months")
    return out


# --------------------------------------------------------------------------- #
# Roster (Airtable) — who are the graduates, and their fallback grad date
# --------------------------------------------------------------------------- #
def load_form3_dates(lessons_records):
    """lesson_id -> Form 3 (end-of-program) submission date. Mirrors the build."""
    out = {}
    for rec in lessons_records:
        if any(bd.get_field_value(rec, bd.FIELDS["lessons"][f]) is not None
               for f in ("f3_impact", "f3_recommend", "f3_keep")):
            d = bd.parse_iso_date(rec.get("createdTime"))
            if d:
                out[rec["id"]] = d
    return out


def airtable_grad_date(rec, form3_dates):
    """Fallback grad date (used only when the badge is missing): earliest Form 3
    date -> Internship End Date. Matches build_dashboard's cascade."""
    lesson_ids = bd.get_field_value(rec, bd.FIELDS["students_reports"]["lessons"]) or []
    f3 = [form3_dates[lid] for lid in lesson_ids if lid in form3_dates]
    if f3:
        return min(f3).isoformat()
    d = bd.parse_iso_date(bd.get_field_value(rec, bd.FIELDS["students_reports"]["internship_end_date"]))
    return d.isoformat() if d else None


def iter_graduates(pat):
    """Yield {wp_username, airtable_grad_date} for graduates with a WP profile,
    deduplicated by username (keeping the earliest Airtable grad date)."""
    log("Fetching Students Reports + Lessons from Airtable...")
    reports = bd.fetch_all_records(
        bd.BASE_ID, bd.TABLES["students_reports"],
        list(bd.FIELDS["students_reports"].values()), pat)
    lessons = bd.fetch_all_records(
        bd.BASE_ID, bd.TABLES["lessons"], list(bd.FIELDS["lessons"].values()), pat)
    form3_dates = load_form3_dates(lessons)

    by_username = {}
    for rec in reports:
        if bd.status_key(bd.get_field_value(rec, bd.FIELDS["students_reports"]["status"])) != GRADUATE_STATUS_KEY:
            continue
        username = bd.extract_wp_username(bd.get_field_value(rec, bd.FIELDS["students_reports"]["wp_profile"]))
        if not username:
            continue
        at_date = airtable_grad_date(rec, form3_dates)
        prev = by_username.get(username)
        if prev is None or (at_date and (prev["airtable_grad_date"] is None
                                         or at_date < prev["airtable_grad_date"])):
            by_username[username] = {"wp_username": username, "airtable_grad_date": at_date}
    return list(by_username.values())


# --------------------------------------------------------------------------- #
# Snapshot + metric
# --------------------------------------------------------------------------- #
def months_between(grad_iso, today):
    """Whole months from grad date to today (approx; month-granular grad date)."""
    g = bd.parse_iso_date(grad_iso)
    if not g:
        return None
    return (today.year - g.year) * 12 + (today.month - g.month)


def build_rows(graduates, snapshot_date, today, delay, limit=None):
    """Scrape each graduate and assemble one snapshot row apiece."""
    rows = []
    targets = graduates[:limit] if limit else graduates
    for i, g in enumerate(targets, 1):
        username = g["wp_username"]
        prof = scrape_profile(username)
        grad_iso = prof["grad_date_badge"] or g["airtable_grad_date"]
        source = ("badge" if prof["grad_date_badge"]
                  else "airtable" if g["airtable_grad_date"] else "unknown")
        days = (today - bd.parse_iso_date(grad_iso)).days if grad_iso else None
        rows.append({
            "snapshot_date": snapshot_date,
            "wp_username": username,
            "grad_date": grad_iso,
            "grad_date_source": source,
            "days_since_grad": days,
            "months_since_grad": months_between(grad_iso, today),
            "recent30": prof["recent30"],
            "recent90": prof["recent90"],
            "recent365": prof["recent365"],
            "profile_ok": prof["ok"],
            "http": prof["http"],
        })
        log(f"  [{i}/{len(targets)}] {username}: 90d={prof['recent90']} "
            f"grad={grad_iso or '?'}({source})")
        if delay and i < len(targets):
            time.sleep(delay)
    return rows


def compute_metric(rows):
    """Retroactive headline from this run's snapshot.

    Only graduates whose active window is fully post-graduation
    (days_since_grad >= ACTIVE_WINDOW_DAYS) are measurable; among those,
    "active" = recent90 > 0.
    """
    with_profile = [r for r in rows if r["profile_ok"]]
    grad_known = [r for r in with_profile if r["grad_date"]]
    measurable = [r for r in grad_known
                  if r["days_since_grad"] is not None and r["days_since_grad"] >= ACTIVE_WINDOW_DAYS]
    active = [r for r in measurable if (r["recent90"] or 0) > 0]
    # Coverage of the tracking horizon: graduates at/under 6 months post-grad
    # are the population the longitudinal curve will fill in over time.
    within_horizon = [r for r in grad_known
                      if r["months_since_grad"] is not None and 0 <= r["months_since_grad"] <= TRACKING_HORIZON_MONTHS]
    pct = round(100 * len(active) / len(measurable)) if measurable else None
    return {
        "graduates": len(rows),
        "with_profile": len(with_profile),
        "grad_date_known": len(grad_known),
        "measurable_90d": len(measurable),
        "active_90d": len(active),
        "pct_active_post_grad": pct,
        "within_6mo_horizon": len(within_horizon),
        "parse_failures": sum(1 for r in with_profile if r["recent90"] is None),
    }


# --------------------------------------------------------------------------- #
# Persistence
# --------------------------------------------------------------------------- #
def to_airtable_fields(row):
    """Map a snapshot row to the private Airtable table's field names."""
    return {
        "Snapshot Date": row["snapshot_date"],
        "WP Username": row["wp_username"],
        "Grad Date": row["grad_date"],
        "Grad Date Source": row["grad_date_source"],
        "Months Since Grad": row["months_since_grad"],
        "Recent 30d": row["recent30"],
        "Recent 90d": row["recent90"],
        "Recent 365d": row["recent365"],
    }


def write_airtable(rows, pat, table_id):
    """Append snapshot rows to the private Airtable table (batches of 10)."""
    url = f"{bd.API_URL}/{bd.BASE_ID}/{table_id}"
    headers = {"Authorization": f"Bearer {pat}", "Content-Type": "application/json"}
    written = 0
    for i in range(0, len(rows), 10):
        batch = [{"fields": to_airtable_fields(r)} for r in rows[i:i + 10]]
        r = requests.post(url, headers=headers, json={"records": batch, "typecast": True}, timeout=20)
        r.raise_for_status()
        written += len(batch)
    return written


def append_jsonl(rows, path):
    path.parent.mkdir(parents=True, exist_ok=True)
    with open(path, "a") as f:
        for row in rows:
            f.write(json.dumps(row, ensure_ascii=False) + "\n")


# --------------------------------------------------------------------------- #
def main():
    parser = argparse.ArgumentParser(description="Snapshot graduates' post-grad WP activity (#137).")
    parser.add_argument("--report-only", action="store_true",
                        help="Scrape + report the number; write nothing.")
    parser.add_argument("--limit", type=int, default=None, help="Only the first N graduates (testing).")
    parser.add_argument("--delay", type=float, default=FETCH_DELAY_SECONDS,
                        help=f"Seconds between profile fetches (default {FETCH_DELAY_SECONDS}).")
    args = parser.parse_args()

    pat = bd.get_airtable_pat()
    today = date.today()
    snapshot_date = today.isoformat()

    graduates = iter_graduates(pat)
    log(f"Graduates with a WP profile: {len(graduates)}")
    if not graduates:
        log("Nothing to snapshot.")
        return 0

    rows = build_rows(graduates, snapshot_date, today, args.delay, args.limit)
    metric = compute_metric(rows)

    log("")
    log("=== Post-graduation contribution (issue #137) ===")
    log(f"Graduates scanned:           {metric['graduates']}")
    log(f"  with a scrapeable profile: {metric['with_profile']}")
    log(f"  graduation date known:     {metric['grad_date_known']}")
    log(f"Measurable (grad >= {ACTIVE_WINDOW_DAYS}d ago): {metric['measurable_90d']}")
    log(f"  active in last 90 days:    {metric['active_90d']}")
    log(f"  => % still contributing:   {metric['pct_active_post_grad']}")
    log(f"Within the {TRACKING_HORIZON_MONTHS}-month horizon:    {metric['within_6mo_horizon']} "
        f"(longitudinal curve fills in monthly from here)")
    if metric["parse_failures"]:
        log(f"WARNING: {metric['parse_failures']} profiles had no parseable 90-day window "
            f"(inactive, or wp.org markup changed — check the canary).")
    log("Caveat: the graduation wave is recent; early numbers are low and noisy.")

    if args.report_only:
        log("\n--report-only: wrote nothing.")
        return 0

    table_id = os.environ.get("POST_GRAD_TABLE_ID")
    if table_id:
        n = write_airtable(rows, pat, table_id)
        log(f"\nWrote {n} snapshot rows to Airtable table {table_id} (private).")
    else:
        append_jsonl(rows, RAW_STORE)
        log(f"\nPOST_GRAD_TABLE_ID not set — appended {len(rows)} rows to {RAW_STORE} "
            f"(local dev fallback; set POST_GRAD_TABLE_ID for the real private store).")

    summary = {"generated": snapshot_date, "window_days": ACTIVE_WINDOW_DAYS,
               "horizon_months": TRACKING_HORIZON_MONTHS,
               "definition": "any wp.org contribution in the active window, grad-date-gated",
               **metric}
    SUMMARY_PATH.parent.mkdir(parents=True, exist_ok=True)
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
