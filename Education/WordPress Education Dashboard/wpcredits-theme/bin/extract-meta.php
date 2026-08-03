<?php
/**
 * Emit a PO fragment for the strings that are not in any function call.
 *
 * WordPress translates three things in a block theme that `xgettext` structurally cannot
 * see, because none of them is a call to `__()`:
 *
 *   - a pattern's `Title` and `Description` headers, via `translate_with_gettext_context()`
 *     in `WP_Block_Patterns_Registry`;
 *   - the `name` of every colour, font size, font family and spacing step in `theme.json`,
 *     via the schema in `wp-includes/theme-i18n.json`;
 *   - the theme's own `Name`, `Description`, `Tags` and `Author` from `style.css`, via
 *     `WP_Theme::translate_header()`.
 *
 * Each one is translated *with a context*, and the context is not decoration — a translator
 * looking at the word "Small" needs to know it names a font size. Getting the context wrong
 * means the string is never found at run time, which looks exactly like a missing
 * translation and is much harder to explain.
 *
 * Writes PO to stdout. `bin/make-pot.sh` merges it with the `xgettext` passes.
 *
 * Usage:  php bin/extract-meta.php > /tmp/meta.pot     (run from the theme root)
 *
 * @package WPCreditsTheme
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

/**
 * PO-escape a string.
 *
 * @param string $value Raw value.
 * @return string
 */
function wpcredits_po_escape( $value ) {
	return str_replace(
		array( '\\', '"', "\t", "\r", "\n" ),
		array( '\\\\', '\"', '\t', '\r', '\n' ),
		(string) $value
	);
}

$entries = array();

/**
 * Queue one entry, keeping the references of a string that appears more than once.
 *
 * @param string $context   gettext context.
 * @param string $msgid     The string.
 * @param string $reference `file:line` or `file`.
 */
function wpcredits_add( $context, $msgid, $reference ) {
	global $entries;

	$msgid = trim( (string) $msgid );

	if ( '' === $msgid ) {
		return;
	}

	$key = $context . "\4" . $msgid;

	if ( ! isset( $entries[ $key ] ) ) {
		$entries[ $key ] = array(
			'context'    => $context,
			'msgid'      => $msgid,
			'references' => array(),
		);
	}

	$entries[ $key ]['references'][] = $reference;
}

/* -- Pattern headers ------------------------------------------------------- */

foreach ( glob( 'patterns/*.php' ) as $file ) {
	$source = file_get_contents( $file );

	// Only the header block, so a `Title:` inside the pattern's own markup is not mistaken
	// for the pattern's title.
	$header = substr( $source, 0, (int) strpos( $source, '?>' ) );

	foreach ( array( 'Title' => 'Pattern title', 'Description' => 'Pattern description' ) as $field => $context ) {
		if ( preg_match( '/^[ \t\/*#@]*' . $field . ':(.*)$/mi', $header, $m ) ) {
			wpcredits_add( $context, trim( $m[1] ), $file );
		}
	}
}

/* -- theme.json ------------------------------------------------------------ */

$theme_json = file_exists( 'theme.json' )
	? json_decode( (string) file_get_contents( 'theme.json' ), true )
	: array();

if ( is_array( $theme_json ) ) {

	// Path in theme.json => the context WordPress translates that node's `name` with. Taken
	// from `wp-includes/theme-i18n.json`; a context invented here would simply never match.
	$named = array(
		'settings.color.palette'            => 'Color name',
		'settings.color.gradients'          => 'Gradient name',
		'settings.color.duotone'            => 'Duotone name',
		'settings.typography.fontSizes'     => 'Font size name',
		'settings.typography.fontFamilies'  => 'Font family name',
		'settings.spacing.spacingSizes'     => 'Space size name',
		'settings.shadow.presets'           => 'Shadow name',
	);

	foreach ( $named as $path => $context ) {
		$node = $theme_json;

		foreach ( explode( '.', $path ) as $step ) {
			$node = isset( $node[ $step ] ) ? $node[ $step ] : null;
		}

		if ( ! is_array( $node ) ) {
			continue;
		}

		foreach ( $node as $item ) {
			if ( isset( $item['name'] ) ) {
				wpcredits_add( $context, $item['name'], 'theme.json' );
			}
		}
	}

	// Custom templates and template parts carry a title rather than a name.
	if ( isset( $theme_json['customTemplates'] ) && is_array( $theme_json['customTemplates'] ) ) {
		foreach ( $theme_json['customTemplates'] as $item ) {
			if ( isset( $item['title'] ) ) {
				wpcredits_add( 'Custom template name', $item['title'], 'theme.json' );
			}
		}
	}

	if ( isset( $theme_json['templateParts'] ) && is_array( $theme_json['templateParts'] ) ) {
		foreach ( $theme_json['templateParts'] as $item ) {
			if ( isset( $item['title'] ) ) {
				wpcredits_add( 'Template part name', $item['title'], 'theme.json' );
			}
		}
	}
}

/* -- style.css headers ----------------------------------------------------- */

if ( file_exists( 'style.css' ) ) {
	$header = (string) file_get_contents( 'style.css', false, null, 0, 8192 );

	$fields = array(
		'Theme Name'  => 'Name of the theme',
		'Description' => 'Description of the theme',
		'Author'      => 'Author of the theme',
		'Tags'        => 'Tags of the theme',
	);

	foreach ( $fields as $field => $context ) {
		if ( preg_match( '/^' . preg_quote( $field, '/' ) . ':(.*)$/mi', $header, $m ) ) {
			wpcredits_add( $context, trim( $m[1] ), 'style.css' );
		}
	}
}

/* -- Output ---------------------------------------------------------------- */

// A header entry, so the fragment is a valid PO file that `msgcat` will accept. The real
// header is written by `make-pot.sh`, which discards this one.
echo "msgid \"\"\n";
echo "msgstr \"\"\n";
echo "\"MIME-Version: 1.0\\n\"\n";
echo "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
echo "\"Content-Transfer-Encoding: 8bit\\n\"\n";

foreach ( $entries as $entry ) {
	echo "\n#: " . implode( ' ', array_unique( $entry['references'] ) ) . "\n";
	echo 'msgctxt "' . wpcredits_po_escape( $entry['context'] ) . "\"\n";
	echo 'msgid "' . wpcredits_po_escape( $entry['msgid'] ) . "\"\n";
	echo "msgstr \"\"\n";
}

fwrite( STDERR, sprintf( "%d entries from patterns, theme.json and style.css.\n", count( $entries ) ) );
