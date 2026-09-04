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

if [ "$1" = "--fix" ]; then
	"$PHPCBF"
	# phpcbf exits 1 when it fixed something, which is not a failure.
	exit 0
fi

# --dead: list every phpcs:ignore that suppresses nothing (bin/check-dead-annotations.php).
if [ "$1" = "--dead" ]; then
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

"$PHPCS"
