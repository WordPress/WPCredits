#!/usr/bin/env bash
# Privacy guard — block individual-level personal data from this PUBLIC repo.
#
# Why: WordPress Credits handles per-student / per-mentor data. Individual data
# belongs in the PRIVATE Airtable base, never in public git (see #137, #132).
# Aggregates (counts, distributions, totals) are fine and expected.
#
# This scans TRACKED data files (json/jsonl/csv) — code and plugin headers
# legitimately carry author emails, so they are out of scope here; rely on code
# review for those. Runs in CI (.github/workflows/privacy-guard.yml) and can be
# run locally: `bash scripts/privacy_guard.sh`.
#
# If a match is a genuine false positive, add the path to ALLOW below with a
# comment explaining why it is safe.
set -uo pipefail

status=0

# Files that must NEVER be tracked (known raw per-person stores).
DENY_PATHS=(
  "data/post_grad_snapshots.jsonl"
  "Education/WordPress Education Initiatives/student-impact/data/seed.json"
)

# Reviewed-safe data files exempt from the content scan (aggregates only).
ALLOW=(
  "Education/WordPress Education Initiatives/student-impact/data/seed-totals.json"
  "Education/WordPress Education Initiatives/wpcredits-tracker/data/seed.json"
  "data/post_grad_summary.json"
)

is_allowed() {
  local f="$1"
  for a in "${ALLOW[@]}"; do [ "$f" = "$a" ] && return 0; done
  return 1
}

for f in "${DENY_PATHS[@]}"; do
  if git ls-files --error-unmatch "$f" >/dev/null 2>&1; then
    echo "::error file=${f}::Per-person data file must not be committed to this public repo — store it in the private Airtable base."
    status=1
  fi
done

while IFS= read -r f; do
  case "$f" in *.json|*.jsonl|*.csv) ;; *) continue ;; esac
  is_allowed "$f" && continue
  # Individual handles: no public data file should list people by wp.org username.
  if grep -qE '"username"[[:space:]]*:' "$f"; then
    echo "::error file=${f}::contains a \"username\" field — individual handles must live in the private Airtable base, not this public repo."
    status=1
  fi
  # Email addresses = personal contact data.
  if grep -qE '[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}' "$f"; then
    echo "::error file=${f}::contains an email address — personal contact data must not be committed."
    status=1
  fi
  # Named per-person rows: a wp.org profile URL alongside a personal "name".
  if grep -qE 'profiles\.wordpress\.org/[A-Za-z0-9_-]+' "$f" && grep -qE '"name"[[:space:]]*:' "$f"; then
    echo "::error file=${f}::pairs a profiles.wordpress.org handle with a \"name\" — that is per-person data; keep it in the private Airtable base."
    status=1
  fi
done < <(git ls-files)

if [ "$status" -ne 0 ]; then
  echo ""
  echo "Privacy guard FAILED. Remove the individual-level data above, or move it to"
  echo "the private Airtable base. Genuine false positive? Add the path to ALLOW in"
  echo "scripts/privacy_guard.sh with a justification."
else
  echo "Privacy guard passed: no individual-level personal data in tracked data files."
fi
exit "$status"
