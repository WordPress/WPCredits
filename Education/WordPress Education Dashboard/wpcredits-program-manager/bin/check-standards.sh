#!/bin/sh
# WordPress Coding Standards for this plugin.
#
# The ruleset lives in phpcs.xml.dist, so `phpcs` with no arguments is the whole check —
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

"$PHPCS"
