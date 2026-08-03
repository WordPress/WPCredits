#!/bin/sh
# Regenerate languages/wpcredits-theme.pot.
#
# WP-CLI's `wp i18n make-pot` is the canonical tool and is what to use if it is
# installed. This exists so the template can be rebuilt with nothing but GNU
# gettext, which is already on most machines — a translation template that can only
# be regenerated on one laptop stops being regenerated.
#
# Usage:  sh bin/make-pot.sh        (run from the theme root)

set -e

SLUG="wpcredits-theme"
NAME="WPCredits Theme"
OUT="languages/$SLUG.pot"

if [ ! -f "style.css" ]; then
	echo "Run this from the theme root." >&2
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

find . -name '*.php' -not -path './languages/*' -not -path './bin/*' | sort > /tmp/wpct-php.list

# shellcheck disable=SC2086
xgettext $KEYWORDS \
	--language=PHP \
	--from-code=UTF-8 \
	--add-comments=translators \
	--files-from=/tmp/wpct-php.list \
	--output=/tmp/wpct-php.pot \
	--package-name="$NAME" \
	--force-po

# Pattern headers, theme.json names and the style.css headers. None of these is a function
# call, so xgettext cannot see any of them — and between them they are most of what a
# translator working on a block theme actually sees.
php bin/extract-meta.php > /tmp/wpct-meta.pot

# Merged *without* `--use-first`: for a string that appears in two places that flag keeps
# the first entry whole and throws the second away, references and all, so the template
# would claim a string has one home when it has two. Plain msgcat combines the references.
# It writes conflict markers where two entries disagree, and in a template — every msgstr
# empty — the only disagreement is between the generated headers, which are replaced below.
msgcat /tmp/wpct-php.pot /tmp/wpct-meta.pot --output-file=/tmp/wpct-merged.pot

# gettext writes a generic header; WordPress tooling and translate.wordpress.org
# expect these specific fields, so the header is replaced wholesale.
YEAR=$(date +%Y)
cat > "$OUT" <<POTHEADER
# Copyright (C) $YEAR $NAME
# This file is distributed under the GPL-2.0-or-later license.
msgid ""
msgstr ""
"Project-Id-Version: $NAME\n"
"Report-Msgid-Bugs-To: https://wordpress.org/support/theme/$SLUG/\n"
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
awk 'f { print } /^$/ { f = 1 }' /tmp/wpct-merged.pot >> "$OUT"

rm -f /tmp/wpct-php.pot /tmp/wpct-meta.pot /tmp/wpct-merged.pot /tmp/wpct-php.list

echo "Wrote $OUT ($(grep -c '^msgid' "$OUT") entries including the header)."
