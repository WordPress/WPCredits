#!/usr/bin/env python3
"""Post-graduation activity snapshotter — issue #137.

Tracks whether graduates keep contributing to WordPress AFTER they graduate, for
at least the first 6 months post-graduation. Owned by @peiraisotta (Aug 2026).

HOW IT WORKS (see the #137 thread for the reasoning)
----------------------------------------------------
Each WordPress.org profile page exposes, all in the raw HTML (no JS), so that one
read-only scrape per graduate gives us everything at once:

* a "Recent impact" panel with trailing windows — Last 30 days / Last 90 days /
  Last 12 months — of contribution counts;
* a "Completed courses" panel, one entry per Learn course with the course link
  and "Completed <Month D, YYYY>" — a DAY-level date, and the course slug says
  WHICH track was completed;
* a "Credits Graduate · since <Month Year>" badge, month-granular only.

GRADUATION DATE — the course panel first, then the badge
--------------------------------------------------------
Completing a Credits track course IS the graduation event, so the course panel's
date is the graduation date. The badge carries only a month, and pinning it to
the month end (the conservative choice, so the 90-day gate cannot overlap a
late-in-month graduation) makes it read LATE: measured against the course date
on 119 real graduate profiles, the badge was a median of 8 days late, mean 10,
max 28, and never early.

The badge is not redundant. On that sample it was present on 69 profiles versus
65 with a dated course, so the cascade is:

    1. course   — day-level, preferred
    2. badge    — month-end, fallback
    3. airtable — Form 3 / internship end, for the ~45% of the backlog with no
                  wp.org evidence at all

`grad_date_source` records which one answered, so the precision of any figure
built on it stays auditable.

TRACK — the profile is the ONLY source
--------------------------------------
Airtable keeps the track in the student's status field ("Developer Track",
"Designer Track", "In Sensei", "In Sensei 50h"), and graduating REPLACES that
status with "Graduate" — there is no track field and no graduation-date field in
the base, so graduation destroys the track. The profile's course slug is
therefore the only way to know which track a graduate completed, which is what
makes a per-track retention curve possible at all.

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
from datetime import date, timedelta
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

# The track registry lives in DATA, not here: more tracks are coming, and the
# plugin's Track Builder (1.100.0) lets a manager define one with an arbitrary
# Learn course URL and key, stored as WordPress posts this job cannot read. So a
# new track is a one-line edit to data/credits_tracks.json and no code change —
# and the two canaries below make an unregistered track LOUD instead of silent.
#
# Completing a track course IS the graduation event, so the profile's "Completed
# <date>" for it is the graduation date at DAY granularity (the badge carries
# only the month). The profile is also the ONLY surviving record of which track a
# graduate completed: Airtable keeps the track in the status field, and
# graduating overwrites that status with "Graduate".
TRACKS_PATH = Path(__file__).resolve().parent.parent / "data" / "credits_tracks.json"


def load_track_registry(path=TRACKS_PATH):
    """Read data/credits_tracks.json -> (slug->key, non_track_slugs, non_track_statuses).

    A missing or malformed registry is fatal rather than silently empty: with no
    registry every graduate would fall back to the month-end badge and every
    track would read as unknown, which looks like a plausible result. Better to
    stop than to publish a quietly degraded number.
    """
    try:
        with open(path, encoding="utf-8") as f:
            reg = json.load(f)
    except (OSError, ValueError) as e:
        raise SystemExit(f"FATAL: cannot read the track registry at {path}: {e}")

    tracks = reg.get("tracks") or {}
    if not tracks:
        raise SystemExit(f"FATAL: the track registry at {path} lists no tracks.")
    slug_to_key = {}
    for slug, spec in tracks.items():
        key = (spec or {}).get("key") if isinstance(spec, dict) else None
        if not key:
            raise SystemExit(f"FATAL: track '{slug}' in {path} has no 'key'.")
        slug_to_key[slug] = key
    return (slug_to_key,
            set(reg.get("non_track_courses") or {}),
            {bd.status_key(s) for s in (reg.get("non_track_statuses") or [])},
            tracks)


(TRACK_COURSE_SLUGS, NON_TRACK_COURSE_SLUGS, NON_TRACK_STATUS_KEYS,
 _REGISTRY_TRACK_SPECS) = load_track_registry()

# Filled by iter_graduates (canary B) and reported in the summary.
UNREGISTERED_TRACK_STATUSES = {}


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
    # The badge is month-granular ("since October 2025") — the exact day is
    # unknown. Pin to the LAST day of that month (the latest possible actual
    # graduation) so the 90-day gate is CONSERVATIVE: a graduate is only
    # measurable once the trailing window is fully clear of even a late-in-month
    # graduation. Using the 1st here instead would let up to ~4 weeks of
    # in-program activity leak past the gate for boundary cases.
    year = int(m.group(2))
    first_next = date(year + 1, 1, 1) if month == 12 else date(year, month + 1, 1)
    return (first_next - timedelta(days=1)).isoformat()


def parse_completed_courses(html):
    """Credits-track completions from the profile's "Completed courses" panel.

    Returns (completions, unregistered) where `completions` is
    [(track_key, ISO date)] earliest first for REGISTERED Credits tracks, and
    `unregistered` is the set of Credits-looking course slugs that are neither
    registered nor listed as non-track — CANARY A (see build_rows).

    The panel is server-rendered, one <li> per course carrying the Learn course
    link and a "Completed <Month D, YYYY>" line, e.g.

        <div class="pp-course-name">
          <a href="https://learn.wordpress.org/course/wordpress-credits">…</a>
        </div>
        <div class="pp-course-date">Completed April 2, 2026</div>

    Measured on 119 real graduate profiles: the panel is present on 97%, and
    every Credits course entry found carried a parseable day-level date (65/65).
    The badge was on 69 of those profiles versus 65 with a dated course, so this
    is the better PRIMARY source but not a replacement — keep the badge as the
    fallback (see build_rows).

    Empty completions are meaningful: no registered track completion is on file,
    so the caller must fall back rather than treat the graduate as ungraduated.
    """
    panel = re.search(r'id="content-courses".*?(?=<div id="content-|\Z)', html, re.S)
    if not panel:
        return [], set()
    found, unregistered = [], set()
    for item in re.findall(r"<li>.*?</li>", panel.group(0), re.S):
        slug_m = re.search(r"learn\.wordpress\.org/course/([a-z0-9-]+)", item)
        if not slug_m:
            continue
        slug = slug_m.group(1)
        if slug not in TRACK_COURSE_SLUGS:
            # Not a known track. If it is Credits-branded and nobody has declared
            # it a non-track, it is probably a NEW TRACK missing from the
            # registry — flag it rather than dropping it, which would silently
            # push these graduates onto the month-end badge with no track.
            if "credits" in slug and slug not in NON_TRACK_COURSE_SLUGS:
                unregistered.add(slug)
            continue
        date_m = re.search(
            r'pp-course-date"[^>]*>\s*Completed\s+([A-Z][a-z]+)\s+(\d{1,2}),\s*(\d{4})', item)
        if not date_m:
            continue
        month = MONTHS.get(date_m.group(1))
        if not month:
            continue
        found.append((TRACK_COURSE_SLUGS[slug],
                      date(int(date_m.group(3)), month, int(date_m.group(2))).isoformat()))
    # Earliest completion is the graduation event: a graduate who later completes
    # a second track has not re-graduated.
    return sorted(found, key=lambda c: c[1]), unregistered


def parse_window(text, label):
    """Contributions count for a Recent-impact window, or None if not present.

    None (vs 0) is meaningful: it flags a profile with no panel OR a markup
    change (canary). The metric treats None as 0 but the run logs the count.
    """
    m = re.search(re.escape(label) + r"\s+([\d,]+)\s+contributions?", text)
    return int(m.group(1).replace(",", "")) if m else None


def scrape_profile(username, verbose=False):
    """One read-only fetch -> grad date (course + badge), track, trailing windows.

    PRIVACY: `verbose` gates the per-graduate error lines. This runs in a
    PUBLIC-repo Action whose logs are world-readable, and a handle plus "HTTP
    404" is still per-student data (it says who is in the program). The default
    log counts failures instead; see build_rows.
    """
    out = {"http": None, "ok": False, "grad_date_badge": None,
           "grad_date_course": None, "track": None, "unregistered_courses": set(),
           "recent30": None, "recent90": None, "recent365": None}
    url = f"https://profiles.wordpress.org/{username}/"
    try:
        r = requests.get(url, timeout=20, headers={"User-Agent": "WPCredits-Dashboard/1.0"})
    except requests.RequestException as e:
        if verbose:
            log(f"  {username}: {e}")
        return out
    out["http"] = r.status_code
    if r.status_code != 200:
        if verbose:
            log(f"  {username}: HTTP {r.status_code}")
        return out
    html = r.text
    text = re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", html))
    out["ok"] = True
    out["grad_date_badge"] = parse_badge_grad_date(html)
    courses, unregistered = parse_completed_courses(html)
    out["unregistered_courses"] = unregistered
    if courses:
        out["track"], out["grad_date_course"] = courses[0]
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


def unregistered_track_statuses(reports):
    """CANARY B: Airtable statuses that are a track but are not in the registry.

    Track Builder lets a manager launch a track with an arbitrary Learn course
    slug, so Canary A (which looks for "credits" in the slug) can miss one
    entirely. The Airtable status cannot be missed: a student on a new track
    carries it. Anything that is neither a declared non-track state nor a
    registered track's status is therefore a track we do not know about.

    This polarity is the point — a new track is automatically "not a declared
    non-track state", so it surfaces without anyone remembering to teach this
    script about it.
    """
    registered = {bd.status_key(spec["airtable_status"])
                  for spec in _REGISTRY_TRACK_SPECS.values()
                  if spec.get("airtable_status")}
    seen = {}
    for rec in reports:
        raw = bd.select_name(bd.get_field_value(
            rec, bd.FIELDS["students_reports"]["status"]))
        if not isinstance(raw, str) or not raw.strip():
            continue
        key = bd.status_key(raw)
        if key in NON_TRACK_STATUS_KEYS or key in registered:
            continue
        seen[raw.strip()] = seen.get(raw.strip(), 0) + 1
    return seen


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

    # Canary B, reported as status names + counts (a status name is not personal
    # data; the handles that carry it are never logged).
    global UNREGISTERED_TRACK_STATUSES
    UNREGISTERED_TRACK_STATUSES = unregistered_track_statuses(reports)
    if UNREGISTERED_TRACK_STATUSES:
        log("  WARNING: Airtable has track status(es) missing from "
            "data/credits_tracks.json — graduates of these tracks will have no "
            "track recorded and will fall back to the month-end badge:")
        for name, n in sorted(UNREGISTERED_TRACK_STATUSES.items()):
            log(f"    - {name!r} ({n} student record(s))")

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


def build_rows(graduates, snapshot_date, today, delay, limit=None, verbose=False):
    """Scrape each graduate and assemble one snapshot row apiece.

    PRIVACY: this runs in a PUBLIC-repo GitHub Action, whose logs are world-
    readable. Per-graduate detail (handle + activity + grad date) is per-student
    private data and must NOT go to the default log — only a bare progress
    counter does. Pass verbose=True (local debugging only) for the detail lines.
    """
    rows = []
    fetch_failures = 0
    unregistered = {}
    targets = graduates[:limit] if limit else graduates
    for i, g in enumerate(targets, 1):
        username = g["wp_username"]
        prof = scrape_profile(username, verbose=verbose)
        if not prof["ok"]:
            fetch_failures += 1
        for slug in prof["unregistered_courses"]:
            unregistered[slug] = unregistered.get(slug, 0) + 1
        # Grad-date cascade, most precise first:
        #   1. course  — the Learn completion date on the profile, DAY-level
        #   2. badge   — month-granular, pinned to month END (so it reads LATE:
        #                median 8d, mean 10d, max 28d against the course date on
        #                a 119-graduate sample, and never early)
        #   3. airtable — Form 3 / internship end, for graduates with no wp.org
        #                 evidence at all (~45% of the backlog)
        grad_iso = (prof["grad_date_course"] or prof["grad_date_badge"]
                    or g["airtable_grad_date"])
        source = ("course" if prof["grad_date_course"]
                  else "badge" if prof["grad_date_badge"]
                  else "airtable" if g["airtable_grad_date"] else "unknown")
        days = (today - bd.parse_iso_date(grad_iso)).days if grad_iso else None
        rows.append({
            "snapshot_date": snapshot_date,
            "wp_username": username,
            "grad_date": grad_iso,
            "grad_date_source": source,
            # Which track was completed. Only the profile knows: Airtable's
            # status field carried it and graduation overwrote it.
            "track": prof["track"],
            "days_since_grad": days,
            "months_since_grad": months_between(grad_iso, today),
            "recent30": prof["recent30"],
            "recent90": prof["recent90"],
            "recent365": prof["recent365"],
            "profile_ok": prof["ok"],
            "http": prof["http"],
        })
        if verbose:  # local debugging only — never in CI (leaks per-student data)
            log(f"  [{i}/{len(targets)}] {username}: 90d={prof['recent90']} "
                f"grad={grad_iso or '?'}({source})")
        elif i % 25 == 0 or i == len(targets):
            log(f"  …scraped {i}/{len(targets)}")
        if delay and i < len(targets):
            time.sleep(delay)
    if fetch_failures:
        # Counter only — naming the profiles would leak the roster (see #165).
        log(f"  WARNING: {fetch_failures} profile(s) could not be read "
            f"(deleted/renamed account, or a bad profile URL in Airtable). "
            f"Re-run locally with --verbose to see which.")
    if unregistered:
        # Canary A. Course slugs are not personal data, so these are safe to name
        # in a public CI log — and naming them is the point: it is the fix.
        log("  WARNING: graduates completed Credits course(s) missing from "
            "data/credits_tracks.json. Add them (or list them under "
            "non_track_courses) so their graduates get a day-level grad date "
            "and a track:")
        for slug, n in sorted(unregistered.items(), key=lambda kv: -kv[1]):
            log(f"    - {slug} ({n} graduate(s))")
    build_rows.unregistered_courses = unregistered
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
        # Where each grad date came from. Publishable (counts only) and the
        # honest precision caveat for any figure built on it: only `course` is
        # day-level. A drop in `course` between runs means wp.org markup moved
        # or badges/courses stopped being awarded — treat it as a canary.
        "grad_date_sources": {
            s: sum(1 for r in rows if r["grad_date_source"] == s)
            for s in ("course", "badge", "airtable", "unknown")
        },
        # Track mix of the graduate cohort, recoverable only from the profile.
        # `null` = no Credits track completion found on wp.org.
        "tracks": {
            t: sum(1 for r in rows if r["track"] == t)
            for t in sorted({r["track"] for r in rows if r["track"]})
        },
        "track_unknown": sum(1 for r in rows if not r["track"]),
        # The two canaries, published so a new track cannot go unnoticed. Both
        # MUST be empty on a healthy run; anything here means a track exists
        # that data/credits_tracks.json has not been told about, and its
        # graduates are silently on the less precise badge date with no track.
        "unregistered_courses": dict(
            sorted(getattr(build_rows, "unregistered_courses", {}).items())),
        "unregistered_track_statuses": dict(sorted(UNREGISTERED_TRACK_STATUSES.items())),
        "registered_tracks": sorted(set(TRACK_COURSE_SLUGS.values())),
    }


# --------------------------------------------------------------------------- #
# Persistence
# --------------------------------------------------------------------------- #
def to_airtable_fields(row):
    """Map a snapshot row to the private Airtable table's field names.

    Field names must match the 'Post-Grad Snapshots' table (tblwTv3G4WYIRztTG).

    NOTE: "Track" needs to exist in that table (single select: 150h/50h/dev/
    design). Airtable's typecast creates missing SELECT CHOICES but not missing
    FIELDS, so a run against a table without it fails the write. "Grad Date
    Source" gains a "course" choice, which typecast does add on its own.
    """
    return {
        "Snapshot": f"{row['wp_username']} · {row['snapshot_date']}",  # primary key field
        "Snapshot Date": row["snapshot_date"],
        "WP Username": row["wp_username"],
        "Grad Date": row["grad_date"],
        "Grad Date Source": row["grad_date_source"],
        "Track": row["track"],
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
    parser.add_argument("--verbose", action="store_true",
                        help="Log per-graduate detail. LOCAL ONLY — leaks per-student "
                             "data into public CI logs; never use in the Action.")
    args = parser.parse_args()

    pat = bd.get_airtable_pat()
    today = date.today()
    snapshot_date = today.isoformat()

    graduates = iter_graduates(pat)
    log(f"Graduates with a WP profile: {len(graduates)}")
    if not graduates:
        log("Nothing to snapshot.")
        return 0

    rows = build_rows(graduates, snapshot_date, today, args.delay, args.limit, args.verbose)
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
