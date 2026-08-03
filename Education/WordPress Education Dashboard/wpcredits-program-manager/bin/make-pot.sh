#!/bin/sh
# Regenerate languages/wpcredits-program-manager.pot.
#
# WP-CLI's `wp i18n make-pot` is the canonical tool and is what to use if it is
# installed. This exists so the template can be rebuilt with nothing but GNU
# gettext, which is already on most machines — a translation template that can only
# be regenerated on one laptop stops being regenerated.
#
# Usage:  sh bin/make-pot.sh        (run from the plugin root)

set -e

SLUG="wpcredits-program-manager"
NAME="WPCredits Program Manager"
OUT="languages/$SLUG.pot"

if [ ! -f "$SLUG.php" ]; then
	echo "Run this from the plugin root." >&2
	exit 1
fi

if command -v wp >/dev/null 2>&1; then
	echo "WP-CLI found — using it, which is the canonical path."
	wp i18n make-pot . "$OUT" --slug="$SLUG" --domain="$SLUG"
	exit 0
fi

mkdir -p languages

# Every WordPress translation function, with the argument positions gettext needs:
# `:1,2c` marks argument 2 as the context, `:1,2` marks a singular/plural pair.
# Getting one of these wrong does not error — it silently mistranslates or drops a
# string — so they are listed in full rather than abbreviated.
KEYWORDS="
--keyword=__
--keyword=_e
--keyword=esc_attr__
--keyword=esc_attr_e
--keyword=esc_html__
--keyword=esc_html_e
--keyword=_x:1,2c
--keyword=_ex:1,2c
--keyword=esc_attr_x:1,2c
--keyword=esc_html_x:1,2c
--keyword=_n:1,2
--keyword=_nx:1,2,4c
--keyword=_n_noop:1,2
--keyword=_nx_noop:1,2,3c
"

# PHP and JS are extracted separately because xgettext takes one --language at a
# time, then merged. The block editor scripts hold real strings, so leaving the JS
# pass out would quietly ship a template missing everything a block author sees.
find . -name '*.php' -not -path './languages/*' -not -path './bin/*' | sort > /tmp/wpcpm-php.list
find ./blocks ./assets/js -name '*.js' 2>/dev/null | sort > /tmp/wpcpm-js.list

# shellcheck disable=SC2086
xgettext $KEYWORDS \
	--language=PHP \
	--from-code=UTF-8 \
	--add-comments=translators \
	--files-from=/tmp/wpcpm-php.list \
	--output=/tmp/wpcpm-php.pot \
	--package-name="$NAME" \
	--force-po

# shellcheck disable=SC2086
xgettext $KEYWORDS \
	--language=JavaScript \
	--from-code=UTF-8 \
	--add-comments=translators \
	--files-from=/tmp/wpcpm-js.list \
	--output=/tmp/wpcpm-js.pot \
	--package-name="$NAME" \
	--force-po

# Merged *without* `--use-first`, which is the obvious flag here and the wrong one: for a
# string that appears in both PHP and JS it keeps the PHP entry whole and throws the JS one
# away, references and all. The string is still translatable, but the template then tells a
# translator it only lives in PHP — so a string whose two uses need different wording reads
# as having one use. Plain msgcat combines the references instead.
#
# The tradeoff is conflict markers where two entries disagree. In a template every msgstr is
# empty, so the only disagreement is between the two generated headers — and the header is
# replaced wholesale below, which discards them.
msgcat /tmp/wpcpm-php.pot /tmp/wpcpm-js.pot --output-file=/tmp/wpcpm-merged.pot

# gettext writes a generic header; WordPress tooling and translate.wordpress.org
# expect these specific fields, so the header is replaced wholesale.
YEAR=$(date +%Y)
cat > "$OUT" <<POTHEADER
# Copyright (C) $YEAR $NAME
# This file is distributed under the GPL-2.0-or-later license.
msgid ""
msgstr ""
"Project-Id-Version: $NAME\n"
"Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/$SLUG/\n"
"Last-Translator: FULL NAME <EMAIL@ADDRESS>\n"
"Language-Team: LANGUAGE <LL@li.org>\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"POT-Creation-Date: $(date -u +%Y-%m-%dT%H:%M:%S+00:00)\n"
"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\n"
"X-Generator: bin/make-pot.sh (GNU gettext)\n"
"X-Domain: $SLUG\n"
POTHEADER

# Everything after gettext's own header block.
awk 'f { print } /^$/ { f = 1 }' /tmp/wpcpm-merged.pot >> "$OUT"

rm -f /tmp/wpcpm-php.pot /tmp/wpcpm-js.pot /tmp/wpcpm-merged.pot /tmp/wpcpm-php.list /tmp/wpcpm-js.list

echo "Wrote $OUT ($(grep -c '^msgid' "$OUT") entries including the header)."
