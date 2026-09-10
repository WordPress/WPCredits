#!/usr/bin/env python3
"""
WordPress Credits — Monthly Metrics Dashboard Builder

Builds `monthly.html`: five headline metrics, each with its change against the
previous month. This is a SEPARATE dashboard from the main one — it reads the
same Airtable base but writes its own page and never touches index.html.

How the month-over-month comparison works
-----------------------------------------
`data/monthly_snapshots.json` holds one frozen entry per calendar month, keyed
`YYYY-MM`. Each run computes today's numbers and stores them under the CURRENT
month's key, then renders the page comparing that entry against the previous
month's. Running on the 1st (as the workflow does) is therefore the intended
path, but a mid-month run is safe: it just refreshes the current month's entry
in place rather than creating a bogus extra period.

The store was seeded from the main dashboard's own git history — every weekly
`index.html` commit is an archive of that week's numbers — so the comparison
worked from the first run instead of waiting a month for a baseline. Metrics
that entered the main build later (the renewal rate, sites, first
contributions) simply have no entry before 2026-08, and render as "—" rather
than being back-filled with guesses.

Metric definitions are kept deliberately identical to scripts/build_dashboard.py
so the two dashboards can never disagree; the plumbing below is imported from it
rather than copied.
"""
import json
import sys
from datetime import date
from pathlib import Path

SNAPSHOT_PATH = Path(__file__).parent.parent / "data" / "monthly_snapshots.json"
TEMPLATE_PATH = Path(__file__).parent / "monthly_template.html"
OUTPUT_PATH = Path(__file__).parent.parent / "monthly.html"
# Embeddable image of the same figures, for pages that can host an <img> but not
# the dashboard itself. The PNG is converted from the SVG by the workflow.
CARD_SVG_PATH = Path(__file__).parent.parent / "monthly-card.svg"
CARD_ALT_PATH = Path(__file__).parent.parent / "monthly-card.txt"

# Institutions tracked as separate Airtable records that are the same partner for
# repeat-cohort purposes. Mirrors build_dashboard.py's INSTITUTION_ALIASES (which
# lives inside its main() and so cannot be imported). Keys are lowercased: names
# reach us through title_case(), which does not preserve acronyms.
INSTITUTION_ALIASES = {
    "cnm ingenuity": "Central New Mexico Community College",
}

def month_key_of(d):
    return f"{d.year:04d}-{d.month:02d}"


def previous_month_key(key):
    y, m = int(key[:4]), int(key[5:7])
    return f"{y - 1:04d}-12" if m == 1 else f"{y:04d}-{m - 1:02d}"


def compute_metrics():
    """Compute the five headline metrics from Airtable.

    Only the three tables these metrics need are fetched, so this build is fast
    (the main dashboard additionally scrapes every student's wp.org profile).

    The metric logic and Airtable plumbing are imported from build_dashboard so
    the two dashboards can never drift apart. The import is deliberately made
    here rather than at module scope: it pulls in `requests`, which --render-only
    has no use for, so template work needs no dependencies installed at all.
    """
    from build_dashboard import (
        BASE_ID,
        FIELDS,
        TABLES,
        fetch_all_records,
        get_airtable_pat,
        get_field_value,
        parse_iso_date,
        status_key,
        title_case,
    )

    pat = get_airtable_pat()
    graduate_status_key = status_key("Graduate")

    institutions = fetch_all_records(
        BASE_ID, TABLES["institutions"], list(FIELDS["institutions"].values()), pat
    )
    reports = fetch_all_records(
        BASE_ID, TABLES["students_reports"], list(FIELDS["students_reports"].values()), pat
    )
    students = fetch_all_records(
        BASE_ID, TABLES["students"], list(FIELDS["students"].values()), pat
    )
    print(
        f"Fetched {len(institutions)} institutions, {len(reports)} reports, "
        f"{len(students)} students",
        file=sys.stderr,
    )

    # --- Partner institutions: distinct CONFIRMED institutions ---
    institutions_lookup = {}
    confirmed = set()
    for rec in institutions:
        name = get_field_value(rec, FIELDS["institutions"]["name"])
        if not name:
            continue
        stage = get_field_value(rec, FIELDS["institutions"]["current_stage"])
        institutions_lookup[rec["id"]] = title_case(name)
        if status_key(stage) == "confirmed":
            confirmed.add(title_case(name))

    # Join key for start dates: email is reliable, names are formatted
    # inconsistently between the two tables.
    students_by_email = {}
    for rec in students:
        email = get_field_value(rec, FIELDS["students"]["email"])
        if email:
            students_by_email[email.strip().lower()] = rec

    graduates = 0
    sites_created = 0
    first_contributions = 0
    inst_quarters = {}  # institution -> set of (year, quarter) it enrolled students in

    for rec in reports:
        if status_key(get_field_value(rec, FIELDS["students_reports"]["status"])) == graduate_status_key:
            graduates += 1

        # An artifact stands even if the student later left, so these are counted
        # across all reports regardless of status — as in the main dashboard.
        if get_field_value(rec, FIELDS["students_reports"]["website_url"]):
            sites_created += 1
        if get_field_value(rec, FIELDS["students_reports"]["first_contribution"]):
            first_contributions += 1

        # Cohorts: the distinct year-quarters each school enrolled students in.
        inst_ids = get_field_value(rec, FIELDS["students_reports"]["institution"]) or []
        if not inst_ids or inst_ids[0] not in institutions_lookup:
            continue
        email = get_field_value(rec, FIELDS["students_reports"]["email"])
        srec = students_by_email.get(email.strip().lower()) if email else None
        start = (
            (parse_iso_date(get_field_value(srec, FIELDS["students"]["start_date"])) if srec else None)
            or parse_iso_date(get_field_value(rec, FIELDS["students_reports"]["internship_start_date"]))
            or parse_iso_date(rec.get("createdTime"))
        )
        if not start:
            continue
        name = institutions_lookup[inst_ids[0]]
        name = INSTITUTION_ALIASES.get(name.strip().lower(), name)
        inst_quarters.setdefault(name, set()).add((start.year, (start.month - 1) // 3))

    # --- Renewal rate: schools with more than one cohort ---
    # A school whose FIRST cohort is the quarter we are currently in is excluded
    # from the denominator: it has not yet had the opportunity to return, so
    # counting it as a non-renewal would understate retention.
    today = date.today()
    current_quarter = (today.year, (today.month - 1) // 3)
    eligible = {n: qs for n, qs in inst_quarters.items() if qs and min(qs) < current_quarter}
    total = len(eligible)
    repeats = sum(1 for qs in eligible.values() if len(qs) >= 2)

    return {
        "asOf": today.isoformat(),
        "source": "airtable",
        "partnerInstitutions": len(confirmed),
        "graduates": graduates,
        "renewalRate": {
            "pct": round(100 * repeats / total) if total else None,
            "count": repeats,
            "total": total,
            "tooNew": len(inst_quarters) - total,
        },
        "sitesCreated": sites_created,
        "firstContributions": first_contributions,
    }


# Each metric: how it is labelled, and how its change should be expressed.
# "count" metrics compare as a percentage change; the renewal rate is itself a
# percentage, so it compares in percentage POINTS — a rate going 56% -> 60% is
# +4 points, not +7.1%, and reporting the latter would overstate the move.
METRIC_SPECS = [
    {
        "key": "partnerInstitutions",
        "label": "Partner institutions",
        "unit": "count",
        "note": "Institutions whose partnership stage is Confirmed.",
    },
    {
        "key": "graduates",
        "label": "Graduates",
        "unit": "count",
        "note": "Students who completed their agreed hours of applied work.",
    },
    {
        "key": "renewalRate",
        "label": "Partner renewal rate",
        "unit": "percent",
        "note": (
            "Share of partners that have run more than one cohort — a new intake at least "
            "a quarter after their first. Schools whose first cohort is the current quarter "
            "are excluded, having had no chance to return yet."
        ),
    },
    {
        "key": "sitesCreated",
        "label": "WordPress sites created",
        "unit": "count",
        "note": "Student project websites built during the program.",
    },
    {
        "key": "firstContributions",
        "label": "First contributions made",
        "unit": "count",
        "note": "Students who recorded their first contribution to the WordPress project.",
    },
]


def value_of(entry, key):
    """Read a metric out of a snapshot entry, flattening the renewal-rate dict."""
    if entry is None:
        return None
    v = entry.get(key)
    if isinstance(v, dict):
        return v.get("pct")
    return v


def build_blob(snapshots, current_key):
    """Assemble the page's data: current value, previous value and change each."""
    prev_key = previous_month_key(current_key)
    current = snapshots.get(current_key)
    previous = snapshots.get(prev_key)
    months = sorted(snapshots)

    metrics = []
    for spec in METRIC_SPECS:
        cur_v = value_of(current, spec["key"])
        prev_v = value_of(previous, spec["key"])

        change = None
        if cur_v is not None and prev_v is not None:
            if spec["unit"] == "percent":
                # Percentage points, rounded to one decimal.
                change = {"kind": "points", "value": round(cur_v - prev_v, 1), "abs": round(cur_v - prev_v, 1)}
            elif prev_v:
                change = {
                    "kind": "percent",
                    "value": round((cur_v - prev_v) / prev_v * 100, 1),
                    "abs": cur_v - prev_v,
                }
            else:
                # Previous month was zero, so a percentage change is undefined
                # (and "+infinity%" is not a number anyone wants on a dashboard).
                # Report the absolute move instead.
                change = {"kind": "absolute", "value": cur_v - prev_v, "abs": cur_v - prev_v}

        # Sparkline series. Only meaningful with at least three points, so
        # two-point metrics render a plain number instead of a two-dot "trend".
        series = [{"month": m, "value": value_of(snapshots[m], spec["key"])} for m in months]
        if sum(1 for p in series if p["value"] is not None) < 3:
            series = []

        entry = {
            "key": spec["key"],
            "label": spec["label"],
            "unit": spec["unit"],
            "note": spec["note"],
            "value": cur_v,
            "previous": prev_v,
            "change": change,
            "series": series,
        }
        # The rate carries its own fraction, which is the honest context for a
        # percentage built on a small denominator.
        if spec["key"] == "renewalRate" and isinstance((current or {}).get("renewalRate"), dict):
            rr = current["renewalRate"]
            entry["detail"] = {"count": rr.get("count"), "total": rr.get("total"), "tooNew": rr.get("tooNew")}
        metrics.append(entry)

    return {
        "generated": date.today().isoformat(),
        "currentKey": current_key,
        "previousKey": prev_key,
        "currentAsOf": (current or {}).get("asOf"),
        "previousAsOf": (previous or {}).get("asOf"),
        "months": months,
        "metrics": metrics,
        "table": [
            {
                "month": m,
                "asOf": snapshots[m].get("asOf"),
                "values": {s["key"]: value_of(snapshots[m], s["key"]) for s in METRIC_SPECS},
            }
            for m in months
        ],
    }


def main():
    snapshots = json.loads(SNAPSHOT_PATH.read_text()) if SNAPSHOT_PATH.exists() else {}

    # `--render-only` rebuilds the page from the stored snapshots without calling
    # Airtable — useful for template work, and for a local preview when no PAT is
    # available.
    if "--render-only" in sys.argv:
        if not snapshots:
            raise SystemExit("No snapshots stored and --render-only given; nothing to render.")
        current_key = max(snapshots)
        print(f"Render-only: using stored snapshot {current_key}", file=sys.stderr)
    else:
        current_key = month_key_of(date.today())
        snapshots[current_key] = compute_metrics()
        SNAPSHOT_PATH.parent.mkdir(parents=True, exist_ok=True)
        SNAPSHOT_PATH.write_text(json.dumps(snapshots, indent=2, sort_keys=True) + "\n")
        print(f"Snapshot for {current_key} written to {SNAPSHOT_PATH}", file=sys.stderr)

    blob = build_blob(snapshots, current_key)

    template = TEMPLATE_PATH.read_text()
    out = template.replace("/*DATA_BLOB*/", json.dumps(blob, separators=(",", ":"), ensure_ascii=False))
    OUTPUT_PATH.write_text(out)
    print(f"Dashboard written to {OUTPUT_PATH}", file=sys.stderr)

    # Embeddable card, drawn from the same blob so it cannot disagree with the
    # page. The alt text is written alongside it because an image drops the
    # numbers for screen readers, and whoever embeds it needs the wording.
    from monthly_card import card_alt_text, render_svg

    CARD_SVG_PATH.write_text(render_svg(blob))
    CARD_ALT_PATH.write_text(card_alt_text(blob) + "\n")
    print(f"Card written to {CARD_SVG_PATH} (alt text: {CARD_ALT_PATH})", file=sys.stderr)

    for m in blob["metrics"]:
        ch = m["change"]
        ch_s = "—" if not ch else (
            f"{ch['value']:+g}{' pts' if ch['kind'] == 'points' else '%'}"
        )
        print(f"  {m['label']}: {m['value']} ({ch_s} vs {blob['previousKey']})", file=sys.stderr)
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        sys.exit(1)
