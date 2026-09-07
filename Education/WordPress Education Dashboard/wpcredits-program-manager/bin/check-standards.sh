#!/bin/sh
# WordPress Coding Standards for this plugin.
#
# The ruleset lives in phpcs.xml.dist, so `phpcs` with no arguments is the whole check -
# there is no invocation to remember and no way to run a different standard by accident.
#
# Install once:  composer global require --dev wp-coding-standards/wpcs \
#                  sirbrillig/phpcs-variable-analysis dealerdirect/phpcodesniffer-composer-installer
#
# Usage:  sh bin/check-standards.sh          report
#         sh bin/check-standards.sh --fix    apply the mechanical fixes

set -e

if [ ! -f phpcs.xml.dist ]; then
	echo "Run this from the plugin root." >&2
	exit 1
fi

PHPCS=$(command -v phpcs || echo "$HOME/.composer/vendor/bin/phpcs")
PHPCBF=$(command -v phpcbf || echo "$HOME/.composer/vendor/bin/phpcbf")

if [ ! -x "$PHPCS" ]; then
	echo "phpcs not found. See the install line at the top of this script." >&2
	exit 1
fi

if [ "${1:-}" = "--fix" ]; then
	"$PHPCBF"
	# phpcbf exits 1 when it fixed something, which is not a failure.
	exit 0
fi

# --dead: list every phpcs:ignore that suppresses nothing (bin/check-dead-annotations.php).
if [ "${1:-}" = "--dead" ]; then
	php bin/check-dead-annotations.php
	exit $?
fi

# The house rule: plain hyphens only. An em dash (U+2014) or an en dash (U+2013) anywhere in the
# plugin fails the check, except in the two places where the characters are somebody else's:
# bin/fixtures (Airtable's own field names and choices), the Foundation's agreement text, and
# .superpowers (untracked scratch that never ships).
if grep -rIn --exclude-dir=.git --exclude-dir=node_modules --exclude-dir=fixtures --exclude-dir=.superpowers --exclude=collaboration-agreement-en.php -e $'\xe2\x80\x94' -e $'\xe2\x80\x93' . >/tmp/wpcpm-dashes.txt 2>/dev/null; then
	echo "Em or en dashes found (the house rule is plain hyphens only):" >&2
	head -20 /tmp/wpcpm-dashes.txt >&2
	rm -f /tmp/wpcpm-dashes.txt
	exit 1
fi
rm -f /tmp/wpcpm-dashes.txt

# US English in the text people read, as the WordPress writing style guide asks.
php bin/check-spelling.php

# Plain output: with colors on, "ERROR" arrives wrapped in escape codes and a grep for the word
# reads zero whatever the count (found while shipping 1.95.0).
#
# The status is this script's own, not phpcs's. phpcs exits 2 whenever it reports anything at
# all, warnings included, so its status cannot tell a clean tree from a broken one and every
# caller that tried to use it as a gate failed on every commit (deep check finding FSUIT-11).
# The summary report goes to a file beside the full report, and the error count in it decides:
# zero errors is a pass whatever the warnings, which are printed for the record.
#
# `mktemp -t NAME` is the BSD spelling; GNU coreutils wants a template with at least three
# Xs and rejects it. A CI runner is exactly where this script is meant to be usable.
summary="$(mktemp "${TMPDIR:-/tmp}/wpcpm-phpcs.XXXXXX")"
trap 'rm -f "$summary"' EXIT

set +e
"$PHPCS" --no-colors --report=full --report-summary="$summary"
phpcs_status=$?
set -e

total="$(grep -m1 '^A TOTAL OF' "$summary" || true)"

# phpcs's status cannot decide a pass, but it can say that phpcs ran at all: 0 with nothing to
# report, 1 or 2 when it found something. Anything above that is phpcs failing to work (a
# missing standard, a parse error in its own config), and so is a non-zero status with no
# total line written. Left unchecked, both fall through to an empty summary, an error count
# defaulting to 0, and "0 warnings, no errors." - a gate that passes because its checker is
# broken, which is a worse version of the failure FSUIT-11 was about.
if [ "$phpcs_status" -gt 2 ] || { [ "$phpcs_status" -ne 0 ] && [ -z "$total" ]; }; then
	echo "phpcs could not be run: it exited $phpcs_status. Nothing here can tell a clean tree from a broken run, so this is a failure. See the install line at the top of this script." >&2
	exit 1
fi

errors="$(printf '%s' "$total" | sed -n 's/^A TOTAL OF \([0-9][0-9]*\) ERROR.*/\1/p')"
warnings="$(printf '%s' "$total" | sed -n 's/^A TOTAL OF .* AND \([0-9][0-9]*\) WARNING.*/\1/p')"
errors="${errors:-0}"
warnings="${warnings:-0}"

if [ "$errors" -gt 0 ]; then
	echo "$errors errors and $warnings warnings. The standard is the gate: fix the errors." >&2
	exit 1
fi

echo "$warnings warnings, no errors."
