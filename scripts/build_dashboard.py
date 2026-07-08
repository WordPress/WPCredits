#!/usr/bin/env python3
"""
WordPress Credits Dashboard Builder

Fetches data from Airtable and builds the dashboard JSON data blob.
"""
import os
import json
import re
import sys
import time
import requests
from datetime import datetime, date
from pathlib import Path

# Configuration
BASE_ID = "appIzQKfwTn5dyPVp"
API_URL = "https://api.airtable.com/v0"

# Table IDs
TABLES = {
    "students_reports": "tbljYkkVGbeoaWEtY",
    "students": "tbla8GZg5x6NY7aWt",
    "mentors": "tblJmEYgBWYxVuzUw",
    "institutions": "tbl4V0FEbzRP7I2w2",
    "sponsors": "tbluji8wknOZr55fa",
    "languages": "tblaxEPaabmlccHWn",
    "contribution_areas": "tblUBEXiS3QKUCXHf",
    "countries": "tbltB7GSRoTtSi4Ps",
    "lessons": "tblGYMK0VpwMv3Bsy",
    "feedback": "tblx3TH6fp4edQJDm",
}

# Field IDs
FIELDS = {
    "students_reports": {
        "name": "fldyXVlkChJaO9Q47",
        "email": "fldGfPd4fF9lxAXYI",
        "status": "fldCMdqqJGAUQ9nbV",
        "institution": "fldRqJlE4nwZQR3QO",
        "hours": "fld7msftOzCxAG5E3",
        "wp_profile": "fld2rGCjmvTZg5DLg",
        "teams": "fldwPGiajTLTu1Vqi",
        "internship_end_date": "fldLwLXupWurmimc7",
        "internship_start_date": "fldeadC0FAkXAxa17",
        "lessons": "fldE1rkXbTWJe8bBq",
        "mentor": "fldSBTwMgno8ecQ2X",
        # Learn course grade fields (non-empty = completed)
        "grade_open_source": "fld8OJCdWSIvt31ay",
        "grade_decisions": "fldo4NPCj2kyRvgiZ",
        "grade_etiquette": "fldER6s99C6hxxwg9",
        "grade_voice": "fld8Utsr9D5roQYo0",
        "grade_conflict": "fldwKJ8RlnXB0nytX",
        "grade_beginner_user": "fldTNFxjYdNligmj5",
        "grade_intermediate_user": "fldqcMjyR2jtdmxyZ",
        "grade_advanced_user": "fldKK2MJbLClz6MUv",
        "grade_beginner_dev": "fldGJ9A04aH2UWxTt",
        "grade_intermediate_theme": "fldy56jmos6xu9FvR",
        "grade_beginner_designer": "fldsxMTOWSK0QUlVS",
    },
    "students": {
        "full_name": "fldvGRKcyRBACeX9t",
        "email": "fldIj9twnzJ0oISpy",
        "start_date": "fldoM2MDWAJAs2seh",
        "field_of_study": "fldwVUA9HZUhZFxJL",
        "internship_end_date": "fld6DQEFvDcaM9PuZ",
        "wp_profile": "fldqZWsRYplXlc8E4",
    },
    "mentors": {
        "full_name": "fldHYNbsylHn4SPQI",
        "wp_profile": "fld8RNVcZ861zDRp5",
        "status": "fldxe86OLwnyWqRSD",
        "country": "fldEdaWeBP8er8XwM",
        "languages": "fldXfYWBQgoo8LNpQ",
        "students": "fld7b9hj4UG14KohI",
        "student_reports": "fldnbYOM2vHPwJjpg",
        "sponsored": "fldFypFl2gQkUszXn",
        "sponsor_company": "fldtdhRzXWpfcb13w",
    },
    "institutions": {
        "name": "fldZQBu7XS2Z29jx4",
        "city": "fldinUAUulxqjZ7d5",
        "country": "fldMZYV5XmC6FbewY",
        "current_stage": "fld4l5x6ScLSLaJZl",
    },
    "sponsors": {
        "company_name": "fldezMq2OBVeqn0DK",
        "status": "fld4woELctFTrNzNa",
    },
    "languages": {
        "name": "flducizfXx3Lz4cid",
    },
    "contribution_areas": {
        "name": "flduk4myvdidZsAlH",
    },
    "countries": {
        "name": "fldtNqCEbpwdo9F2t",
    },
    # Lessons table holds the raw feedback-form submissions. Form 3 (the end-of-
    # program form) is identified by any of these fields being populated; the
    # record's createdTime is the Form 3 submission date.
    "lessons": {
        "f3_impact": "fld5idMQUwwpiljWf",
        "f3_recommend": "fldKXSzK5zkGRe8OC",
        "f3_keep": "fld4yTcZWUxdYiiiz",
    },
    # Student feedback (student-linked layer). Aggregate, experience-only.
    "feedback": {
        "ease": "fldn7M6U4xXurEgJ2",          # rating 1-5
        "satisfaction": "fldJPTEix369b8NOw",   # rating 1-5
        "impact": "fldxtEUsunNgjyKXV",         # rating 1-5
        "confidence": "fldgnTd61qnS22D81",     # select (asked at the mid-program form)
        "recommend": "fldZXbvOTIGad6r90",      # select: likely / neither / unlikely
        "keep": "fld8RZMdCLYKtwcLN",           # select: likely / neither / unlikely
    },
}

# Partner institution map markers, grouped by city. Coordinates geocoded from
# city + country (one-time). Keep in sync with the partner list; if this grows
# often, consider moving locations into Airtable and building markers from there.
INST_MARKERS = [
    {"city": "Dhaka", "country": "Bangladesh", "lat": 23.7644, "lng": 90.389, "institutions": ["Ahmad's Education"]},
    {"city": "Pisa", "country": "Italy", "lat": 43.4715, "lng": 10.6798, "institutions": ["Università di Pisa"]},
    {"city": "San José", "country": "Costa Rica", "lat": 9.9328, "lng": -84.0796, "institutions": ["Universidad Fidélitas"]},
    {"city": "Cartago", "country": "Costa Rica", "lat": 9.8157, "lng": -83.6944, "institutions": ["Liceo HHC Experimental Bilingue José Figueres Ferrer"]},
    {"city": "Albuquerque", "country": "United States", "lat": 35.0841, "lng": -106.651, "institutions": ["Central New Mexico Community College"]},
    {"city": "Madison", "country": "United States", "lat": 40.7598, "lng": -74.4171, "institutions": ["Drew University"]},
    {"city": "Riga", "country": "Latvia", "lat": 56.9494, "lng": 24.1052, "institutions": ["Riga Nordic University"]},
    {"city": "Santa Cruz de la Sierra", "country": "Bolivia", "lat": -17.7834, "lng": -63.1821, "institutions": ["Universidad Privada Franz Tamayo"]},
    {"city": "Cochabamba", "country": "Bolivia", "lat": -17.4012, "lng": -66.1676, "institutions": ["Universidad Privada Franz Tamayo"]},
    {"city": "Kraków", "country": "Poland", "lat": 50.0619, "lng": 19.9369, "institutions": ["Krakow University of Economics", "Cracow University of Technology"]},
    {"city": "Toledo", "country": "Spain", "lat": 39.8559, "lng": -4.0243, "institutions": ["IES Azarquiel", "Escuela de Arte Toledo"]},
    {"city": "Madrid", "country": "Spain", "lat": 40.4168, "lng": -3.7035, "institutions": ["Creative Campus - Universidad Europea", "Universidad de Diseño Innovación y Tecnología - UDIT"]},
    {"city": "Zaragoza", "country": "Spain", "lat": 41.6916, "lng": -0.9101, "institutions": ["Escuela de Arte de Zaragoza", "Zaragoza Dinámica"]},
    {"city": "Huesca", "country": "Spain", "lat": 42.1361, "lng": -0.0298, "institutions": ["Escuela de Arte de Huesca"]},
    {"city": "Estepona", "country": "Spain", "lat": 36.4268, "lng": -5.1468, "institutions": ["Instituto de Educación Secundaria Mar de Alborán"]},
    {"city": "Salamanca", "country": "Spain", "lat": 40.9652, "lng": -5.664, "institutions": ["IES Venancio Blanco"]},
    {"city": "A Coruña", "country": "Spain", "lat": 43.3466, "lng": -8.4127, "institutions": ["CPR Liceo La Paz"]},
    {"city": "Kolhapur", "country": "India", "lat": 16.7028, "lng": 74.2405, "institutions": ["D Y Patil Agriculture and Technical University, Talsande, Kolhapur"]},
    {"city": "Kolkata", "country": "India", "lat": 22.5726, "lng": 88.3639, "institutions": ["ERAP Research and Learning LLP"]},
    {"city": "Pula", "country": "Croatia", "lat": 44.8702, "lng": 13.8455, "institutions": ["Juraj Dobrila University of Pula"]},
    {"city": "Kampala", "country": "Uganda", "lat": 0.3177, "lng": 32.5814, "institutions": ["E-zone School of Computing"]},
    {"city": "Helsinki", "country": "Finland", "lat": 60.1666, "lng": 24.9435, "institutions": ["Haaga-Helia University of Applied Sciences"]},
]


def get_airtable_pat():
    """Get Airtable PAT from environment variable."""
    pat = os.environ.get("AIRTABLE_PAT")
    if not pat:
        raise ValueError("AIRTABLE_PAT environment variable not set")
    return pat


def fetch_all_records(base_id, table_id, field_ids, pat):
    """Fetch all records from Airtable table with pagination."""
    url = f"{API_URL}/{base_id}/{table_id}"
    headers = {"Authorization": f"Bearer {pat}"}
    params = {"fields[]": field_ids, "returnFieldsByFieldId": "true", "pageSize": 100}

    all_records = []
    offset = None

    while True:
        if offset:
            params["offset"] = offset

        try:
            r = requests.get(url, headers=headers, params=params, timeout=10)
            r.raise_for_status()
        except requests.RequestException as e:
            print(f"Error fetching {table_id}: {e}", file=sys.stderr)
            raise

        data = r.json()
        all_records.extend(data.get("records", []))
        offset = data.get("offset")

        if not offset:
            break

    return all_records


def title_case(text):
    """Convert to title case, preserving Spanish prepositions and acronyms."""
    if not text:
        return text

    spanish_lowercase = {"de", "del", "la", "las", "los", "el", "en", "y", "e", "al"}
    words = text.split()
    result = []

    for i, word in enumerate(words):
        # Keep acronyms in parentheses uppercase
        if "(" in word and ")" in word:
            result.append(word)
        # First word is always capitalized
        elif i == 0:
            result.append(word.capitalize())
        # Spanish prepositions lowercase (except first)
        elif word.lower() in spanish_lowercase:
            result.append(word.lower())
        else:
            result.append(word.capitalize())

    return " ".join(result)


def extract_wp_username(url):
    """Extract WordPress username from profile URL."""
    if not url:
        return None
    # Format: https://profiles.wordpress.org/username/
    if "profiles.wordpress.org" in url:
        parts = url.rstrip("/").split("/")
        return parts[-1] if parts else None
    return None


SEASON_ORDER = {"Winter": 0, "Spring": 1, "Summer": 2, "Fall": 3}


def get_cohort_from_date(date_str):
    """Determine cohort (Winter/Spring/Summer/Fall) from internship end date.

    Uses (month, day) boundaries for the northern-hemisphere seasons. Winter
    spans the year boundary and is named for the year it ends in (so an end
    date in Dec 2025 and one in Feb 2026 both fall in "Winter 2026").
    """
    if not date_str:
        return None

    try:
        # Parse date: YYYY-MM-DD (day optional)
        parts = date_str.split("-")
        year = int(parts[0])
        month = int(parts[1])
        day = int(parts[2]) if len(parts) > 2 else 15
    except (ValueError, IndexError):
        return None

    md = (month, day)
    # Winter: Dec 21 – Mar 19
    if md >= (12, 21) or md <= (3, 19):
        return f"Winter {year + 1}" if month == 12 else f"Winter {year}"
    # Spring: Mar 20 – Jun 20
    elif md <= (6, 20):
        return f"Spring {year}"
    # Summer: Jun 21 – Sep 21
    elif md <= (9, 21):
        return f"Summer {year}"
    # Fall: Sep 22 – Dec 20
    return f"Fall {year}"


def cohort_sort_key(cohort):
    """Sort key so cohorts order chronologically (year, then season)."""
    try:
        season, yr = cohort.rsplit(" ", 1)
        return (int(yr), SEASON_ORDER.get(season, 99))
    except (ValueError, AttributeError):
        return (9999, 99)


def parse_iso_date(s):
    """Parse an ISO date or datetime string to a datetime.date (or None)."""
    if not s or not isinstance(s, str) or len(s) < 10:
        return None
    try:
        return date(int(s[0:4]), int(s[5:7]), int(s[8:10]))
    except ValueError:
        return None


def month_key(d):
    """YYYY-MM bucket label for a date."""
    return f"{d.year:04d}-{d.month:02d}" if d else None


def get_field_value(record, field_id):
    """Safely get field value from Airtable record."""
    fields = record.get("fields", {})
    return fields.get(field_id)


def select_name(value):
    """Extract the display name from a single-select value (dict or str)."""
    if isinstance(value, dict):
        return value.get("name")
    return value


def status_key(value):
    """Normalize a select value for tolerant comparison (trim + casefold).

    This lets cosmetic Airtable renames (extra whitespace or capitalization
    changes) not silently break the build's status/stage matching. Compare
    against keys produced by this same function. Returns None when empty.
    """
    name = select_name(value)
    if not isinstance(name, str):
        return None
    return name.strip().casefold()


def fetch_translation_stats(wp_username):
    """Fetch translation stats from a WordPress.org profile page.

    Parses the activity feed for entries like:
      - 'Suggested N string(s)'
      - 'Translated N string(s)'
      - 'Reviewed N string(s)'

    Returns dict: {suggested, translated, reviewed, total}
    """
    if not wp_username:
        return {"suggested": 0, "translated": 0, "reviewed": 0, "total": 0}

    url = f"https://profiles.wordpress.org/{wp_username}/"
    try:
        r = requests.get(url, timeout=15, headers={"User-Agent": "WPCredits-Dashboard/1.0"})
        if r.status_code != 200:
            print(f"  Profile {wp_username}: HTTP {r.status_code}", file=sys.stderr)
            return {"suggested": 0, "translated": 0, "reviewed": 0, "total": 0}
        html = r.text
    except requests.RequestException as e:
        print(f"  Profile {wp_username}: {e}", file=sys.stderr)
        return {"suggested": 0, "translated": 0, "reviewed": 0, "total": 0}

    suggested = 0
    translated = 0
    reviewed = 0

    # Match patterns like "Suggested 5 strings" or "Translated 1 string"
    for m in re.finditer(r"Suggested (\d+) strings?", html):
        suggested += int(m.group(1))
    for m in re.finditer(r"Translated (\d+) strings?", html):
        translated += int(m.group(1))
    for m in re.finditer(r"Reviewed (\d+) strings?", html):
        reviewed += int(m.group(1))

    total = suggested + translated + reviewed
    return {"suggested": suggested, "translated": translated, "reviewed": reviewed, "total": total}


def main():
    """Build the dashboard data blob."""
    pat = get_airtable_pat()

    print("Fetching Airtable data...", file=sys.stderr)

    # Fetch all tables
    students_reports_records = fetch_all_records(
        BASE_ID, TABLES["students_reports"],
        list(FIELDS["students_reports"].values()), pat
    )
    students_records = fetch_all_records(
        BASE_ID, TABLES["students"],
        list(FIELDS["students"].values()), pat
    )
    mentors_records = fetch_all_records(
        BASE_ID, TABLES["mentors"],
        list(FIELDS["mentors"].values()), pat
    )
    institutions_records = fetch_all_records(
        BASE_ID, TABLES["institutions"],
        list(FIELDS["institutions"].values()), pat
    )
    sponsors_records = fetch_all_records(
        BASE_ID, TABLES["sponsors"],
        list(FIELDS["sponsors"].values()), pat
    )
    languages_records = fetch_all_records(
        BASE_ID, TABLES["languages"],
        list(FIELDS["languages"].values()), pat
    )
    contribution_areas_records = fetch_all_records(
        BASE_ID, TABLES["contribution_areas"],
        list(FIELDS["contribution_areas"].values()), pat
    )
    countries_records = fetch_all_records(
        BASE_ID, TABLES["countries"],
        list(FIELDS["countries"].values()), pat
    )
    lessons_records = fetch_all_records(
        BASE_ID, TABLES["lessons"],
        list(FIELDS["lessons"].values()), pat
    )
    feedback_records = fetch_all_records(
        BASE_ID, TABLES["feedback"],
        list(FIELDS["feedback"].values()), pat
    )

    print(f"Fetched {len(students_reports_records)} student reports", file=sys.stderr)
    print(f"Fetched {len(mentors_records)} mentors", file=sys.stderr)

    # Build lookup tables
    institutions_lookup = {}
    confirmed_institutions = set()
    for rec in institutions_records:
        inst_id = rec["id"]
        name = get_field_value(rec, FIELDS["institutions"]["name"])
        stage = select_name(get_field_value(rec, FIELDS["institutions"]["current_stage"]))
        if name:
            institutions_lookup[inst_id] = {"name": title_case(name), "stage": stage}
            if status_key(stage) == "confirmed":
                confirmed_institutions.add(title_case(name))

    sponsors_lookup = {}
    for rec in sponsors_records:
        sponsor_id = rec["id"]
        name = get_field_value(rec, FIELDS["sponsors"]["company_name"])
        if name:
            sponsors_lookup[sponsor_id] = {"name": title_case(name)}

    languages_lookup = {}
    for rec in languages_records:
        lang_id = rec["id"]
        name = get_field_value(rec, FIELDS["languages"]["name"])
        if name:
            languages_lookup[lang_id] = {"name": title_case(name)}

    contribution_areas_lookup = {}
    for rec in contribution_areas_records:
        area_id = rec["id"]
        name = get_field_value(rec, FIELDS["contribution_areas"]["name"])
        if name:
            contribution_areas_lookup[area_id] = {"name": title_case(name)}

    countries_lookup = {}
    for rec in countries_records:
        country_id = rec["id"]
        name = get_field_value(rec, FIELDS["countries"]["name"])
        if name:
            countries_lookup[country_id] = {"name": title_case(name)}

    # Build students mappings for field-of-study cross-reference. Email is the
    # reliable join key (the two tables store names in different formats — e.g.
    # "Erick Marin Monge" vs "MARIN MONGE ERICK BERNARDO"), so we match on email
    # first and fall back to normalized name.
    students_by_name = {}
    students_by_email = {}
    for rec in students_records:
        name = get_field_value(rec, FIELDS["students"]["full_name"])
        if name:
            normalized = " ".join(name.strip().lower().split())
            students_by_name[normalized] = rec
        email = get_field_value(rec, FIELDS["students"]["email"])
        if email:
            students_by_email[email.strip().lower()] = rec

    # Statuses that count as real participants. NOTE: keep this in sync with the
    # `activeStatuses` set in scripts/template.html.
    ACTIVE_STATUSES = {
        "In Sensei",
        "In Sensei Self-onboarding",
        "In Sensei 50h",
        "Pending graduation",
    }
    GRADUATE_STATUS = "Graduate"
    INCLUDED_STATUSES = ACTIVE_STATUSES | {GRADUATE_STATUS}
    # Normalized keys for tolerant matching (see status_key()).
    INCLUDED_STATUS_KEYS = {status_key(s) for s in INCLUDED_STATUSES}
    GRADUATE_STATUS_KEY = status_key(GRADUATE_STATUS)
    DROPOUT_KEY = status_key("Dropped out")
    NOT_MOVING_FORWARD_KEY = status_key("Not moving forward")

    # Process students
    students = []
    institutions_set = set()
    team_distribution = {}
    active_count = 0
    graduate_count = 0
    dropout_count = 0
    not_moving_forward_count = 0
    total_hours = 0
    field_of_study_stats = {}
    country_from_mentor = {}  # Track which students have which country via mentor

    for rec in students_reports_records:
        name = get_field_value(rec, FIELDS["students_reports"]["name"])
        status_obj = get_field_value(rec, FIELDS["students_reports"]["status"])
        hours = get_field_value(rec, FIELDS["students_reports"]["hours"]) or 0
        wp_profile = get_field_value(rec, FIELDS["students_reports"]["wp_profile"])
        internship_end_date = get_field_value(rec, FIELDS["students_reports"]["internship_end_date"])
        lessons_ids = get_field_value(rec, FIELDS["students_reports"]["lessons"]) or []
        teams_ids = get_field_value(rec, FIELDS["students_reports"]["teams"]) or []

        # Count completed Learn courses from grade columns (non-empty = completed)
        grade_fields = [
            "grade_open_source", "grade_decisions", "grade_etiquette",
            "grade_voice", "grade_conflict", "grade_beginner_user",
            "grade_intermediate_user", "grade_advanced_user",
            "grade_beginner_dev", "grade_intermediate_theme",
            "grade_beginner_designer",
        ]
        completed_courses = sum(
            1 for gf in grade_fields
            if get_field_value(rec, FIELDS["students_reports"][gf]) is not None
        )

        if not name:
            continue

        # Parse status (keep original for display; normalized key for matching)
        status = select_name(status_obj)
        status_n = status_key(status_obj)

        # Tally exits before filtering them out. These are kept distinct:
        #  - "Dropped out" = student started the program and then left (a true dropout).
        #  - "Not moving forward" = expressed interest or was added by a teacher but
        #    never actually started; NOT a dropout.
        if status_n == DROPOUT_KEY:
            dropout_count += 1
        elif status_n == NOT_MOVING_FORWARD_KEY:
            not_moving_forward_count += 1

        # Skip students with non-active statuses (Not moving forward, SPAM, Dropped out, Paused, etc.)
        if status_n not in INCLUDED_STATUS_KEYS:
            continue

        # Determine if graduate
        is_graduate = status_n == GRADUATE_STATUS_KEY

        # Get institution
        institution_ids = get_field_value(rec, FIELDS["students_reports"]["institution"]) or []
        institution_name = None
        if institution_ids:
            inst_id = institution_ids[0]  # Take first
            if inst_id in institutions_lookup:
                institution_name = institutions_lookup[inst_id]["name"]
                institutions_set.add(institution_name)

        # Get teams
        teams = []
        for team_id in teams_ids:
            if team_id in contribution_areas_lookup:
                team_name = contribution_areas_lookup[team_id]["name"]
                teams.append(team_name)
                team_distribution[team_name] = team_distribution.get(team_name, 0) + 1

        # Get cohort
        cohort = get_cohort_from_date(internship_end_date)

        # Get field of study (cross-reference with Students table). Match on email
        # first (reliable), then fall back to normalized name.
        field_of_study = None
        report_email = get_field_value(rec, FIELDS["students_reports"]["email"])
        email_key = report_email.strip().lower() if report_email else ""
        name_normalized = " ".join(name.strip().lower().split()) if name else ""
        student_rec = students_by_email.get(email_key) or students_by_name.get(name_normalized)
        if student_rec:
            fos_obj = get_field_value(student_rec, FIELDS["students"]["field_of_study"])
            if fos_obj and isinstance(fos_obj, dict):
                field_of_study = fos_obj.get("name")
            elif isinstance(fos_obj, str):
                field_of_study = fos_obj

        # Count statistics
        if is_graduate:
            graduate_count += 1
        else:
            active_count += 1

        total_hours += hours

        # Track field of study stats
        if field_of_study:
            if field_of_study not in field_of_study_stats:
                field_of_study_stats[field_of_study] = {"active": 0, "graduated": 0}
            if is_graduate:
                field_of_study_stats[field_of_study]["graduated"] += 1
            else:
                field_of_study_stats[field_of_study]["active"] += 1

        student_data = {
            "name": title_case(name),
            "status": status,
            "hours": int(hours),
            "institution": institution_name,
            "teams": teams,
            "wp_profile": wp_profile,
            "wp_username": extract_wp_username(wp_profile),
            "is_graduate": is_graduate,
            "courses": completed_courses + (1 if is_graduate else 0),  # +1 for WP Credits course
            "fieldOfStudy": field_of_study,
            "cohort": cohort,
        }
        students.append(student_data)

    # Sort students by name
    students.sort(key=lambda s: s["name"])

    # Fetch translation stats from WordPress.org profiles
    print("Fetching translation stats from WordPress.org profiles...", file=sys.stderr)
    translation_totals_agg = {"suggested": 0, "translated": 0, "reviewed": 0, "total": 0}
    profiles_fetched = 0
    for student in students:
        wp_username = student.get("wp_username")
        if wp_username:
            stats = fetch_translation_stats(wp_username)
            # Throttle to avoid rate limiting (200ms between requests)
            profiles_fetched += 1
            if profiles_fetched % 5 == 0:
                time.sleep(1)
            else:
                time.sleep(0.2)
        else:
            stats = {"suggested": 0, "translated": 0, "reviewed": 0, "total": 0}

        student["suggested"] = stats["suggested"]
        student["translated"] = stats["translated"]
        student["reviewed"] = stats["reviewed"]
        student["total_strings"] = stats["total"]

        translation_totals_agg["suggested"] += stats["suggested"]
        translation_totals_agg["translated"] += stats["translated"]
        translation_totals_agg["reviewed"] += stats["reviewed"]
        translation_totals_agg["total"] += stats["total"]

        if stats["total"] > 0:
            print(f"  {wp_username}: {stats['total']} strings ({stats['suggested']}s/{stats['translated']}t/{stats['reviewed']}r)", file=sys.stderr)

    print(f"Translation totals: {translation_totals_agg['total']} strings from {profiles_fetched} profiles", file=sys.stderr)

    # Process mentors
    mentors = []
    active_mentors = 0
    vetted_mentors = 0  # Vetted but not yet active (pipeline)
    mentor_countries = {}

    for rec in mentors_records:
        name = get_field_value(rec, FIELDS["mentors"]["full_name"])
        status_obj = get_field_value(rec, FIELDS["mentors"]["status"])
        country_ids = get_field_value(rec, FIELDS["mentors"]["country"]) or []
        wp_profile = get_field_value(rec, FIELDS["mentors"]["wp_profile"])
        languages_ids = get_field_value(rec, FIELDS["mentors"]["languages"]) or []
        student_report_ids = get_field_value(rec, FIELDS["mentors"]["student_reports"]) or []
        sponsored_obj = get_field_value(rec, FIELDS["mentors"]["sponsored"])
        sponsor_company = get_field_value(rec, FIELDS["mentors"]["sponsor_company"])

        if not name:
            continue

        # Parse status (normalized key for tolerant matching)
        status_n = status_key(status_obj)

        # Count vetted-but-not-yet-active mentors as a separate pipeline metric.
        if status_n == status_key("Vetted - positive"):
            vetted_mentors += 1

        # Only include currently-active mentors in the displayed list/count.
        if status_n != status_key("Active"):
            continue

        active_mentors += 1

        # Resolve linked Countries records to names (a mentor may span several).
        countries = [
            countries_lookup[cid]["name"]
            for cid in country_ids
            if cid in countries_lookup
        ]
        country_normalized = ", ".join(countries) if countries else None
        for cname in countries:
            mentor_countries[cname] = mentor_countries.get(cname, 0) + 1

        # Resolve languages
        languages = []
        for lang_id in languages_ids:
            if lang_id in languages_lookup:
                lang_name = languages_lookup[lang_id]["name"]
                languages.append(lang_name)

        # Resolve student reports to get student names
        active_students = []
        grad_students = []
        for sr_id in student_report_ids:
            # Find this student report in our list
            for sr in students_reports_records:
                if sr["id"] == sr_id:
                    student_name = get_field_value(sr, FIELDS["students_reports"]["name"])
                    wp_prof = get_field_value(sr, FIELDS["students_reports"]["wp_profile"])
                    wp_user = extract_wp_username(wp_prof)
                    sr_status = get_field_value(sr, FIELDS["students_reports"]["status"])
                    sr_is_grad = status_key(sr_status) == status_key("Graduate")

                    if student_name:
                        student_entry = {
                            "name": title_case(student_name),
                            "wp_username": wp_user,
                        }
                        if sr_is_grad:
                            grad_students.append(student_entry)
                        else:
                            active_students.append(student_entry)
                    break

        # Parse sponsored
        sponsored = status_key(sponsored_obj) == status_key("Yes")

        # Get sponsor companies
        companies = []
        if sponsor_company:
            companies.append(sponsor_company)

        mentor_data = {
            "name": title_case(name),
            "country_normalized": country_normalized,
            "country": country_normalized,
            "wp_profile": wp_profile,
            "wp_username": extract_wp_username(wp_profile),
            "languages": languages,
            "activeCount": len(active_students),
            "gradCount": len(grad_students),
            "activeStudents": active_students,
            "gradStudents": grad_students,
            "sponsored": sponsored,
            "companies": companies,
        }
        mentors.append(mentor_data)

    # Sort mentors by name
    mentors.sort(key=lambda m: m["name"])

    # Count approved sponsors from the Sponsors table (the source of truth).
    approved_sponsors = 0
    for rec in sponsors_records:
        if status_key(get_field_value(rec, FIELDS["sponsors"]["status"])) == status_key("Approved"):
            approved_sponsors += 1

    # Count confirmed partner institutions by country (resolved via Countries table).
    inst_countries = {}
    for rec in institutions_records:
        if status_key(get_field_value(rec, FIELDS["institutions"]["current_stage"])) != "confirmed":
            continue
        country_ids = get_field_value(rec, FIELDS["institutions"]["country"]) or []
        for cid in country_ids:
            if cid in countries_lookup:
                cname = countries_lookup[cid]["name"]
                inst_countries[cname] = inst_countries.get(cname, 0) + 1

    # --- Growth over time (monthly: students joining vs graduating) ---
    # Form 3 (end-of-program) submission dates, keyed by Lessons record id.
    form3_date_by_lesson = {}
    for rec in lessons_records:
        is_form3 = any(
            get_field_value(rec, FIELDS["lessons"][f]) is not None
            for f in ("f3_impact", "f3_recommend", "f3_keep")
        )
        if is_form3:
            d = parse_iso_date(rec.get("createdTime"))
            if d:
                form3_date_by_lesson[rec["id"]] = d

    # Statuses that count as having actually started (joined) the program.
    JOINED_STATUS_KEYS = {
        status_key(s) for s in [
            "In Sensei", "In Sensei Self-onboarding", "In Sensei 50h",
            "Pending graduation", "Graduate", "Dropped out", "Paused", "Fail",
        ]
    }
    current_month = month_key(date.today())

    joined_by_month = {}
    graduated_by_month = {}
    for rec in students_reports_records:
        status_n = status_key(get_field_value(rec, FIELDS["students_reports"]["status"]))
        if status_n not in JOINED_STATUS_KEYS:
            continue
        created = parse_iso_date(rec.get("createdTime"))

        # Intake month, most reliable source first: the student's "Start Date"
        # (Students table, matched by email), then the report's Internship Start
        # Date, then the record's creation month.
        report_email = get_field_value(rec, FIELDS["students_reports"]["email"])
        student_rec = students_by_email.get(report_email.strip().lower()) if report_email else None
        start_date = (
            (parse_iso_date(get_field_value(student_rec, FIELDS["students"]["start_date"])) if student_rec else None)
            or parse_iso_date(get_field_value(rec, FIELDS["students_reports"]["internship_start_date"]))
            or created
        )
        mk = month_key(start_date)
        if mk and mk <= current_month:  # never show future sign-ups
            joined_by_month[mk] = joined_by_month.get(mk, 0) + 1

        # Graduations: "Graduated on" (future field) -> Form 3 date -> end date.
        if status_n == GRADUATE_STATUS_KEY:
            lesson_ids = get_field_value(rec, FIELDS["students_reports"]["lessons"]) or []
            f3 = [form3_date_by_lesson[lid] for lid in lesson_ids if lid in form3_date_by_lesson]
            grad_date = min(f3) if f3 else parse_iso_date(
                get_field_value(rec, FIELDS["students_reports"]["internship_end_date"]))
            mk = month_key(grad_date)
            if mk and mk <= current_month:
                graduated_by_month[mk] = graduated_by_month.get(mk, 0) + 1

    # Continuous monthly series from first activity through the current month,
    # filling empty months with zeros so the timeline has no gaps.
    growth = {"months": [], "joined": [], "graduated": [], "cumulativeJoined": []}
    all_months = set(joined_by_month) | set(graduated_by_month)
    if all_months:
        y, m = int(min(all_months)[:4]), int(min(all_months)[5:7])
        cy, cm = int(current_month[:4]), int(current_month[5:7])
        cum = 0
        while (y, m) <= (cy, cm):
            mk = f"{y:04d}-{m:02d}"
            cum += joined_by_month.get(mk, 0)
            growth["months"].append(mk)
            growth["joined"].append(joined_by_month.get(mk, 0))
            growth["graduated"].append(graduated_by_month.get(mk, 0))
            growth["cumulativeJoined"].append(cum)
            m = m + 1 if m < 12 else 1
            y = y if m != 1 else y + 1

    # --- Student feedback (aggregate, experience-only; no individuals) ---
    def _rating_avg(field):
        vals = [get_field_value(r, FIELDS["feedback"][field]) for r in feedback_records]
        vals = [v for v in vals if isinstance(v, (int, float))]
        return {"avg": round(sum(vals) / len(vals), 1), "n": len(vals)} if vals else {"avg": None, "n": 0}

    def _pct_positive(field, positive_keys):
        keys = [status_key(get_field_value(r, FIELDS["feedback"][field])) for r in feedback_records]
        keys = [k for k in keys if k]
        if not keys:
            return {"pct": None, "n": 0}
        pos = sum(1 for k in keys if k in positive_keys)
        return {"pct": round(100 * pos / len(keys)), "n": len(keys)}

    # Confidence distribution over the four real options (junk options ignored).
    CONF_LABELS = {
        "very confident": "Very confident",
        "confident": "Confident",
        "neutral": "Neutral",
        "not very confident": "Not very confident",
    }
    conf_counts = {label: 0 for label in CONF_LABELS.values()}
    conf_n = 0
    for r in feedback_records:
        k = status_key(get_field_value(r, FIELDS["feedback"]["confidence"]))
        if k in CONF_LABELS:
            conf_counts[CONF_LABELS[k]] += 1
            conf_n += 1

    feedback = {
        "responses": len(feedback_records),
        "ratings": {
            "ease": _rating_avg("ease"),
            "satisfaction": _rating_avg("satisfaction"),
            "impact": _rating_avg("impact"),
        },
        "recommend": _pct_positive("recommend", {"likely"}),
        "keep": _pct_positive("keep", {"likely"}),
        "confidence": {"dist": conf_counts, "n": conf_n},
    }

    # Build global stats
    global_stats = {
        "activeStudents": active_count,
        "graduates": graduate_count,
        "dropouts": dropout_count,
        "notMovingForward": not_moving_forward_count,
        "totalHours": round(total_hours),
        "partnerInstitutions": len(confirmed_institutions),
        "sponsorCount": approved_sponsors,
        "activeMentors": active_mentors,
        "vettedMentors": vetted_mentors,
        "teamDistribution": team_distribution,
        "totalCourses": sum(s["courses"] for s in students),
        "instCountries": inst_countries,
        "mentorCountries": mentor_countries,
        "fieldOfStudy": field_of_study_stats,
    }

    # Translation totals from WordPress.org profile scraping
    translation_totals = translation_totals_agg

    # Sorted, chronological list of cohorts for the filter dropdowns.
    cohorts_list = sorted(
        {s["cohort"] for s in students if s.get("cohort")},
        key=cohort_sort_key,
    )

    # Build final data blob
    data_blob = {
        "global": global_stats,
        "translationTotals": translation_totals,
        "institutions": sorted(confirmed_institutions),
        "cohorts": cohorts_list,
        "growth": growth,
        "feedback": feedback,
        "students": students,
        "mentors": mentors,
    }

    print(f"Built dashboard with {len(students)} students and {len(mentors)} mentors", file=sys.stderr)

    # Read template and inject data
    script_dir = Path(__file__).parent
    template_path = script_dir / "template.html"
    output_path = script_dir.parent / "index.html"

    if not template_path.exists():
        raise FileNotFoundError(f"Template not found: {template_path}")

    with open(template_path, "r") as f:
        template_content = f.read()

    # Replace placeholders
    data_json = json.dumps(data_blob, separators=(",", ":"), ensure_ascii=False)
    inst_markers_json = json.dumps(INST_MARKERS, separators=(",", ":"), ensure_ascii=False)

    output_content = template_content.replace("/*DATA_BLOB*/", data_json)
    output_content = output_content.replace("/*INST_MARKERS*/", inst_markers_json)

    # Write output
    with open(output_path, "w") as f:
        f.write(output_content)

    print(f"Dashboard written to {output_path}", file=sys.stderr)
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        sys.exit(1)
