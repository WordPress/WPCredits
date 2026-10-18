<?php
/**
 * How the suites read a box a wide table scrolls sideways in: whether a keyboard reaches it, what a
 * screen reader calls it, and the stylesheet rules that draw it.
 *
 * 1.122.35 made the Administrator Dashboard's tables scroll in boxes that are tab stops and named
 * regions (bin/test-administrators-dashboard.php), and 1.122.36 the Student Duplicate Finder's and
 * the Emails tool's (bin/test-duplicate-finder.php, bin/test-emails-templates.php). The three
 * suites read the boxes the same way, so the reading is here once: `scroll_region_name()` and
 * `scroll_boxes()` off the markup a screen drew, and `scroll_css_rules()` off a stylesheet.
 *
 * Loaded with `require_once __DIR__ . '/stubs/scroll-regions.php';` from a suite's header.
 */

/**
 * The name a screen reader gives a box: the words of the elements its aria-labelledby names, in
 * that order, each looked up among the ids of the same markup; '' for a box with none. No box is
 * named by an aria-label, so none is read here until one is.
 *
 * @param DOMXPath   $xpath The markup's reader.
 * @param DOMElement $box   The box.
 * @return string An id printed twice, or not at all, reads as "(N elements with the id X)".
 */
function scroll_region_name( DOMXPath $xpath, DOMElement $box ) {
	$ids  = preg_split( '/\s+/', trim( (string) $box->getAttribute( 'aria-labelledby' ) ), -1, PREG_SPLIT_NO_EMPTY );
	$name = array();

	foreach ( $ids as $id ) {
		$named  = $xpath->query( '//*[@id="' . $id . '"]' );
		$name[] = 1 === $named->length ? trim( preg_replace( '/\s+/', ' ', $named->item( 0 )->textContent ) ) : '(' . $named->length . ' elements with the id ' . $id . ')';
	}

	return implode( ' ', $name );
}

/**
 * Every box of one class in a piece of markup, as it reads: its tag and class, its tab stop and
 * role, whether a table is the one element in it, and its name (`scroll_region_name()`); and the
 * class of every table of the markup that sits in no such box, so a screen's own layout table (the
 * Settings section's `form-table`, which core stacks on a phone) is named rather than counted.
 *
 * @param string $html  Markup a screen drew.
 * @param string $class The box's class.
 * @return array{boxes: array[], loose: string[]}
 */
function scroll_boxes( $html, $class ) {
	$doc      = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	$xpath = new DOMXPath( $doc );
	$boxes = array();

	foreach ( $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]' ) as $box ) {
		$elements = array();

		foreach ( $box->childNodes as $child ) {
			if ( XML_ELEMENT_NODE === $child->nodeType ) {
				$elements[] = $child->nodeName;
			}
		}

		$boxes[] = array(
			'box'      => $box->nodeName . '.' . $box->getAttribute( 'class' ),
			'tabindex' => $box->getAttribute( 'tabindex' ),
			'role'     => $box->getAttribute( 'role' ),
			'alone'    => array( 'table' ) === $elements,
			'name'     => scroll_region_name( $xpath, $box ),
		);
	}

	$loose = array();

	foreach ( $xpath->query( '//table' ) as $table ) {
		$parent = $table->parentNode;

		if ( ! $parent instanceof DOMElement || false === strpos( ' ' . preg_replace( '/\s+/', ' ', $parent->getAttribute( 'class' ) ) . ' ', ' ' . $class . ' ' ) ) {
			$loose[] = $table->getAttribute( 'class' );
		}
	}

	return array(
		'boxes' => $boxes,
		'loose' => $loose,
	);
}

/**
 * A stylesheet's rules, each with the media query it sits in ('' for none), its selectors and
 * its declarations, read off the comment-stripped source. One level of `@media`, as the plugin's
 * sheets are written.
 *
 * @param string $css The stylesheet.
 * @return array[]
 */
function scroll_css_rules( $css ) {
	$read  = static function ( $source, $media ) {
		preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $source, $found, PREG_SET_ORDER );
		$rules = array();

		foreach ( $found as $rule ) {
			$decls = array();

			foreach ( explode( ';', $rule[2] ) as $decl ) {
				if ( false !== strpos( $decl, ':' ) ) {
					list( $prop, $value )   = explode( ':', $decl, 2 );
					$decls[ trim( $prop ) ] = trim( $value );
				}
			}

			$rules[] = array(
				'media'     => $media,
				'selectors' => array_map( static function ( $s ) { return trim( preg_replace( '/\s+/', ' ', $s ) ); }, explode( ',', $rule[1] ) ),
				'decls'     => $decls,
			);
		}

		return $rules;
	};
	$rules = array();
	$plain = preg_replace_callback(
		'/@media\s*([^{]+)\{((?:[^{}]*\{[^{}]*\})*)\s*\}/',
		static function ( $m ) use ( $read, &$rules ) {
			$rules = array_merge( $rules, $read( $m[2], trim( preg_replace( '/\s+/', ' ', $m[1] ) ) ) );
			return '';
		},
		(string) preg_replace( '#/\*.*?\*/#s', '', $css )
	);

	return array_merge( $read( (string) $plain, '' ), $rules );
}

/**
 * The declarations every rule of a stylesheet gives one selector, outside any media query unless
 * one is named, merged in source order.
 *
 * @param string $css      The stylesheet.
 * @param string $selector The selector, as the rule writes it.
 * @param string $media    The media query the rule sits in, '' for none.
 * @return array<string, string>
 */
function scroll_css_decls( $css, $selector, $media = '' ) {
	$decls = array();

	foreach ( scroll_css_rules( $css ) as $rule ) {
		if ( $media === $rule['media'] && in_array( $selector, $rule['selectors'], true ) ) {
			$decls = array_merge( $decls, $rule['decls'] );
		}
	}

	return $decls;
}
