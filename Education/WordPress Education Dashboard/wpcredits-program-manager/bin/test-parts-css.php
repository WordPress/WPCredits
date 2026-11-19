<?php
/**
 * parts.css: the shared sheet (design 3.1), checked against what it took over.
 *
 * What each block pins, and why:
 *
 * - **The moved rules are moved, not changed.** The frame's tokens (`--wpcpm-f-brand` to `--wpcpm-f-serif`),
 *   `.wpcpm-frame .wpcpm-btn` with its secondary and `.wpcpm-status` with its tones, as `dashboard-frame.css`
 *   held them at 1.123.90, are a fixed list in this suite. Each declaration is in `parts.css` under the same
 *   selector with the same value (the tokens on `:where( .wpcpm-frame, .wpcpm-dashboard, .wpcpm-wrap )`),
 *   and none is left in the frame's sheet, which keeps only the layout tokens.
 * - **The sheet stays inside its own classes.** One `!important` (the hidden attribute under the root
 *   class), no selector that is or starts with an element name, and every selector names a `wpcpm-ui-` class,
 *   is one of the moved frame selectors, or is scoped under `.wpcpm-ui-on`.
 *
 * Run: php bin/test-parts-css.php
 *
 * @package WPCreditsProgramManager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$fail   = 0;
$checks = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	++$checks;
	$ok = $actual === $expected;
	if ( ! $ok ) {
		$fail++;
	}
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		echo '     expected: ' . var_export( $expected, true ) . "\n     actual:   " . var_export( $actual, true ) . "\n";
	}
}

/**
 * The rules of a sheet, comments stripped: a list of array( selector, declarations ), rules inside an
 * at-rule flattened in. Selectors are whitespace-normalized; declarations are trimmed and without the
 * closing semicolon.
 */
function rules_of( $css ) {
	$css   = preg_replace( '#/\*.*?\*/#s', '', $css );
	$rules = array();
	$head  = '';
	$stack = array();
	$body  = '';
	$len   = strlen( $css );
	for ( $i = 0; $i < $len; $i++ ) {
		$c = $css[ $i ];
		if ( '{' === $c ) {
			$sel = trim( preg_replace( '/\s+/', ' ', $head ) );
			if ( '@' === substr( $sel, 0, 1 ) ) {
				$stack[] = null;
			} else {
				$stack[] = $sel;
				$body    = '';
			}
			$head = '';
		} elseif ( '}' === $c ) {
			$sel = array_pop( $stack );
			if ( null !== $sel ) {
				$decls = array();
				foreach ( explode( ';', $body ) as $d ) {
					$d = trim( preg_replace( '/\s+/', ' ', $d ) );
					if ( '' !== $d ) {
						$decls[] = $d;
					}
				}
				foreach ( preg_split( '/\s*,\s*(?![^()]*\))/', $sel ) as $one ) {
					$rules[] = array( $one, $decls );
				}
			}
			$head = '';
			$body = '';
		} elseif ( empty( $stack ) || null === end( $stack ) ) {
			$head .= $c;
		} else {
			$body .= $c;
		}
	}
	return $rules;
}

$old_tokens = array(
	'--wpcpm-f-brand: var( --wpc-brand, #3858e9 );',
	'--wpcpm-f-brand-tint: var( --wpc-brand-tint, #eff2ff );',
	'--wpcpm-f-brand-border: var( --wpc-brand-border, #c7d1ff );',
	'--wpcpm-f-ink: var( --wpc-ink, #1e1e1e );',
	'--wpcpm-f-ink-2: var( --wpc-ink-70, #40464d );',
	'--wpcpm-f-muted: var( --wpc-ink-60, #656a71 );',
	'--wpcpm-f-icon: #979aa1;',
	'--wpcpm-f-line: var( --wpc-line, #e6e6e6 );',
	'--wpcpm-f-line-soft: #ededed;',
	'--wpcpm-f-control: #8d8d8d;',
	'--wpcpm-f-surface: var( --wpc-surface, #ffffff );',
	'--wpcpm-f-band: var( --wpc-surface-band, #f4f4f4 );',
	'--wpcpm-f-red: var( --wpc-red, #cc1818 );',
	'--wpcpm-f-red-tint: var( --wpc-red-tint, #fff6f5 );',
	'--wpcpm-f-red-border: var( --wpc-red-border, #daa39b );',
	'--wpcpm-f-amber: var( --wpc-amber, #926300 );',
	'--wpcpm-f-amber-tint: var( --wpc-amber-tint, #fff7e1 );',
	'--wpcpm-f-amber-border: var( --wpc-amber-border, #d0b481 );',
	'--wpcpm-f-green: var( --wpc-green, #008030 );',
	'--wpcpm-f-green-tint: var( --wpc-green-tint, #ebffed );',
	'--wpcpm-f-sans: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;',
	'--wpcpm-f-serif: "EB Garamond", Georgia, "Times New Roman", serif;',
);
$old_rules = array(
	'.wpcpm-frame .wpcpm-btn' => array(
		'align-items: center',
		'background: var( --wpcpm-f-brand )',
		'border: 1px solid var( --wpcpm-f-brand )',
		'border-radius: 2px',
		'color: #ffffff',
		'display: inline-flex',
		'font: 600 14px/20px var( --wpcpm-f-sans )',
		'gap: 6px',
		'padding: 9px 16px',
		'text-decoration: none',
		'white-space: nowrap',
	),
	'.wpcpm-frame .wpcpm-btn:hover' => array(
		'background: var( --wpc-brand-dark, #213fd4 )',
		'border-color: var( --wpc-brand-dark, #213fd4 )',
		'color: #ffffff',
	),
	'.wpcpm-frame .wpcpm-btn--secondary' => array(
		'background: var( --wpcpm-f-surface )',
		'color: var( --wpcpm-f-brand )',
	),
	'.wpcpm-frame .wpcpm-btn--secondary:hover' => array(
		'background: var( --wpcpm-f-brand-tint )',
		'color: var( --wpc-brand-dark, #213fd4 )',
	),
	'.wpcpm-status' => array(
		'background: var( --wpcpm-f-band )',
		'border: 1px solid transparent',
		'border-radius: 2px',
		'color: var( --wpcpm-f-ink-2 )',
		'display: inline-block',
		'font: 500 12px/18px var( --wpcpm-f-sans )',
		'letter-spacing: 0.06em',
		'padding: 1px 8px',
		'text-transform: uppercase',
		'white-space: nowrap',
	),
	'.wpcpm-status--red' => array(
		'background: var( --wpcpm-f-red-tint )',
		'border-color: var( --wpcpm-f-red-border )',
		'color: var( --wpcpm-f-red )',
	),
	'.wpcpm-status--amber' => array(
		'background: var( --wpcpm-f-amber-tint )',
		'border-color: var( --wpcpm-f-amber-border )',
		'color: var( --wpcpm-f-amber )',
	),
	'.wpcpm-status--green' => array(
		'background: var( --wpcpm-f-green-tint )',
		'color: var( --wpcpm-f-green )',
	),
);

$parts_css = (string) @file_get_contents( __DIR__ . '/../assets/css/parts.css' );
$frame_css = (string) file_get_contents( __DIR__ . '/../assets/css/dashboard-frame.css' );
ck( 'parts.css exists and is not empty', '' !== trim( $parts_css ), true );

$parts = rules_of( $parts_css );
$frame = rules_of( $frame_css );

// Declarations by selector, across every rule of that selector.
$by_selector = function ( $rules ) {
	$map = array();
	foreach ( $rules as $r ) {
		$map[ $r[0] ] = array_merge( $map[ $r[0] ] ?? array(), $r[1] );
	}
	return $map;
};
$parts_map = $by_selector( $parts );
$frame_map = $by_selector( $frame );

// A selector list is split on its commas above, so a :where() list is read from the raw sheet instead.
$raw       = preg_replace( '#/\*.*?\*/#s', '', $parts_css );
$has_where = 1 === preg_match( '/:where\(\s*\.wpcpm-frame,\s*\.wpcpm-dashboard,\s*\.wpcpm-wrap\s*\)\s*\{([^}]*)\}/', $raw, $m );
ck( 'the tokens are declared on the zero-specificity :where() list', $has_where, true );
$token_decls = array();
if ( $has_where ) {
	foreach ( explode( ';', $m[1] ) as $d ) {
		$d = trim( preg_replace( '/\s+/', ' ', $d ) );
		if ( '' !== $d ) {
			$token_decls[] = $d . ';';
		}
	}
}
foreach ( $old_tokens as $t ) {
	ck( "parts.css has $t", in_array( $t, $token_decls, true ), true );
	$name = substr( $t, 0, strpos( $t, ':' ) );
	ck( "dashboard-frame.css declares no $name", 1 === preg_match( '/' . preg_quote( $name, '/' ) . '\s*:/', preg_replace( '#/\*.*?\*/#s', '', $frame_css ) ), false );
}
foreach ( array( '--wpcpm-f-menu: 256px', '--wpcpm-f-rail: 64px', '--wpcpm-f-top: var( --wp-admin--admin-bar--height, 0px )' ) as $keep ) {
	ck( "dashboard-frame.css keeps $keep", in_array( $keep, $frame_map['.wpcpm-frame'] ?? array(), true ), true );
}

foreach ( $old_rules as $selector => $decls ) {
	foreach ( $decls as $d ) {
		ck( "parts.css: $selector { $d }", in_array( $d, $parts_map[ $selector ] ?? array(), true ), true );
	}
	ck( "dashboard-frame.css no longer has $selector", isset( $frame_map[ $selector ] ), false );
	ck( "parts.css has $selector with no more than it had", count( $parts_map[ $selector ] ?? array() ), count( $decls ) );
}

// The moved selectors keep the order they had in dashboard-frame.css: a tone above its base rule would lose.
$positions = array();
foreach ( $parts as $i => $r ) {
	if ( isset( $old_rules[ $r[0] ] ) && ! isset( $positions[ $r[0] ] ) ) {
		$positions[ $r[0] ] = $i;
	}
}
ck( 'every moved selector is in parts.css', count( $positions ), count( $old_rules ) );
$in_order = array_values( $positions );
$sorted   = $in_order;
sort( $sorted );
ck( 'the moved selectors appear in parts.css in the order they had in dashboard-frame.css', array_keys( $positions ) === array_keys( $old_rules ) && $in_order === $sorted, true );

// Each token's name is declared once in the token block: a second declaration with another value would win.
$token_names = array();
foreach ( $token_decls as $d ) {
	$token_names[] = substr( $d, 0, strpos( $d, ':' ) );
}
$dupes = array_keys( array_filter( array_count_values( $token_names ), function ( $n ) {
	return $n > 1;
} ) );
ck( 'no token name is declared twice in the token block', $dupes, array() );
foreach ( $old_tokens as $t ) {
	$n = substr( $t, 0, strpos( $t, ':' ) );
	ck( "$n is declared exactly once", count( array_keys( $token_names, $n, true ) ), 1 );
}

// The added tokens of design 3.1.
foreach ( array(
	'--wpcpm-f-green-border: var( --wpc-green-border, #8ac894 );',
	'--wpcpm-f-neutral: #f6f6f6;',
	'--wpcpm-f-line-card: #e4e4e4;',
	'--wpcpm-f-primary-hover: var( --wpc-brand-dark, #213fd4 );',
	'--wpcpm-f-field: 14px;',
	'--wpcpm-f-block: 16px;',
	'--wpcpm-f-apart: 24px;',
	'--wpcpm-f-module: 22px;',
	'--wpcpm-f-fold: 10px;',
	'--wpcpm-f-tie: 6px;',
	'--wpcpm-f-gap: 12px;',
) as $t ) {
	ck( "parts.css has $t", in_array( $t, $token_decls, true ), true );
}

// The sheet's own discipline.
ck( '!important appears once', substr_count( $raw, '!important' ), 1 );
ck( 'the one !important is the hidden attribute', 1 === preg_match( '/\.wpcpm-ui-on \[hidden\]\s*\{\s*display:\s*none !important;\s*\}/', $raw ), true );

$moved = array_keys( $old_rules );
$bad_element = array();
$bad_scope   = array();
foreach ( $parts as $r ) {
	$sel = $r[0];
	if ( 0 === strpos( $sel, ':where( .wpcpm-frame' ) || 'wpcpm-wrap )' === $sel || '.wpcpm-dashboard' === $sel ) {
		continue; // A piece of the token selector list, split on its commas.
	}
	if ( 1 === preg_match( '/^(button|input|select|textarea|label|table|summary|details|a)\b/', $sel ) ) {
		$bad_element[] = $sel;
	}
	$ok = in_array( $sel, $moved, true )
		|| false !== strpos( $sel, '.wpcpm-ui-' )
		|| 0 === strpos( $sel, '.wpcpm-ui-on ' );
	if ( ! $ok ) {
		$bad_scope[] = $sel;
	}
}
ck( 'no selector starts with or is an element name', $bad_element, array() );
ck( 'every selector names a wpcpm-ui- class, is a moved frame selector or is under .wpcpm-ui-on', $bad_scope, array() );

// Any rule that names the frame's .wpcpm-btn or .wpcpm-status starts with .wpcpm-ui-on, unless it is one of the
// selectors Task 3 moved in unchanged: a new look of an old shared class never reaches a surface that is off.
$unscoped = array();
foreach ( $parts as $r ) {
	if ( 1 === preg_match( '/\.wpcpm-(btn|status)/', $r[0] ) && 0 !== strpos( $r[0], '.wpcpm-ui-on' ) && ! in_array( $r[0], $moved, true ) ) {
		$unscoped[] = $r[0];
	}
}
ck( 'every selector naming .wpcpm-btn or .wpcpm-status starts with .wpcpm-ui-on, but the ones Task 3 moved in', $unscoped, array() );

// ---- Task 4: the button, the tag, the chip, the heading, the intro and the action row ----
$sel_decls = function ( $selector ) use ( $parts ) {
	$out = array();
	foreach ( $parts as $r ) {
		if ( $r[0] === $selector ) {
			$out = array_merge( $out, $r[1] );
		}
	}
	return $out;
};
$has = function ( $selector, $decl ) use ( $sel_decls ) {
	return in_array( $decl, $sel_decls( $selector ), true );
};
// The guard every button hover rule ends with, :where() so it adds no weight.
$guard = ':where(:not(:disabled):not([aria-disabled="true"]))';
foreach ( array(
	'min-height: 40px',
	'padding: 9px 16px',
	'font: 600 14px/20px var( --wpcpm-f-sans )',
	'border-radius: 2px',
	'white-space: nowrap',
) as $d ) {
	ck( "design 2.1: .wpcpm-ui-on .wpcpm-btn { $d }", $has( '.wpcpm-ui-on .wpcpm-btn', $d ), true );
}
ck( 'design 2.1: the quiet variant is padding 10px 8px', $has( '.wpcpm-ui-on .wpcpm-btn--quiet', 'padding: 10px 8px' ), true );
ck( 'design 2.1: the quiet variant has no border', $has( '.wpcpm-ui-on .wpcpm-btn--quiet', 'border: 0' ), true );
foreach ( array( '.wpcpm-ui-on .button.wpcpm-btn--quiet', '.wpcpm-ui-on .button.wpcpm-btn--danger' ) as $s ) {
	ck( "wp-admin: $s is 13px", $has( $s, 'font-size: 13px' ), true );
	ck( "wp-admin: $s is weight 500", $has( $s, 'font-weight: 500' ), true );
}
ck( 'the danger variant: white fill, red border and words', array( $has( '.wpcpm-ui-on .wpcpm-btn--danger', 'background: var( --wpcpm-f-surface )' ), $has( '.wpcpm-ui-on .wpcpm-btn--danger', 'border-color: var( --wpcpm-f-red )' ), $has( '.wpcpm-ui-on .wpcpm-btn--danger', 'color: var( --wpcpm-f-red )' ) ), array( true, true, true ) );
ck( 'the danger hover is the red tint', $has( '.wpcpm-ui-on .wpcpm-btn--danger:hover' . $guard, 'background: var( --wpcpm-f-red-tint )' ), true );
ck( 'the secondary hover is Deep Blueberry with white words, under .wpcpm-ui-on', array( $has( '.wpcpm-ui-on .wpcpm-btn--secondary:hover' . $guard, 'background: var( --wpcpm-f-primary-hover )' ), $has( '.wpcpm-ui-on .wpcpm-btn--secondary:hover' . $guard, 'color: #ffffff' ) ), array( true, true ) );
ck( 'the quiet hover is Deep Blueberry, underlined 3px off', array( $has( '.wpcpm-ui-on .wpcpm-btn--quiet:hover' . $guard, 'color: var( --wpcpm-f-primary-hover )' ), $has( '.wpcpm-ui-on .wpcpm-btn--quiet:hover' . $guard, 'text-decoration: underline' ), $has( '.wpcpm-ui-on .wpcpm-btn--quiet:hover' . $guard, 'text-underline-offset: 3px' ) ), array( true, true, true ) );
ck( 'the quiet danger hover stays red', $has( '.wpcpm-ui-on .wpcpm-btn--quiet.wpcpm-btn--danger:hover' . $guard, 'color: var( --wpcpm-f-red )' ), true );
ck( 'pressed: secondary takes the tint, danger the red tint, primary Deep Blueberry', array( $has( '.wpcpm-ui-on .wpcpm-btn--secondary[aria-expanded="true"]', 'background: var( --wpcpm-f-brand-tint )' ), $has( '.wpcpm-ui-on .wpcpm-btn--danger[aria-expanded="true"]', 'background: var( --wpcpm-f-red-tint )' ), $has( '.wpcpm-ui-on .wpcpm-btn[aria-expanded="true"]', 'background: var( --wpcpm-f-primary-hover )' ) ), array( true, true, true ) );
ck( 'pressed quiet: the tint of its color', array( $has( '.wpcpm-ui-on .wpcpm-btn--quiet[aria-expanded="true"]', 'background: var( --wpcpm-f-brand-tint )' ), $has( '.wpcpm-ui-on .wpcpm-btn--quiet.wpcpm-btn--danger[aria-expanded="true"]', 'background: var( --wpcpm-f-red-tint )' ) ), array( true, true ) );
foreach ( array( '.wpcpm-ui-on .wpcpm-ui-list__act .wpcpm-btn--quiet', '.wpcpm-ui-on td.wpcpm-ui-cell--action .wpcpm-btn--quiet' ) as $s ) {
	ck( "row action $s: no side padding at rest", $has( $s, 'padding-left: 0' ) && $has( $s, 'padding-right: 0' ), true );
}
foreach ( array( '.wpcpm-ui-on .wpcpm-ui-list__act .wpcpm-btn--quiet[aria-expanded="true"]', '.wpcpm-ui-on td.wpcpm-ui-cell--action .wpcpm-btn--quiet[aria-expanded="true"]' ) as $s ) {
	ck( "row action pressed $s: 8px side padding and -8px side margin", array( $has( $s, 'padding-left: 8px' ), $has( $s, 'padding-right: 8px' ), $has( $s, 'margin-left: -8px' ), $has( $s, 'margin-right: -8px' ) ), array( true, true, true, true ) );
}
$ring = $sel_decls( '.wpcpm-ui-on .wpcpm-btn:focus-visible' );
ck( 'the focus ring is 2px solid Blueberry, 2px off', array( in_array( 'outline: 2px solid var( --wpcpm-f-brand )', $ring, true ), in_array( 'outline-offset: 2px', $ring, true ) ), array( true, true ) );
ck( 'the wp-admin button has the same ring', in_array( 'outline: 2px solid var( --wpcpm-f-brand )', $sel_decls( '.wpcpm-ui-on .button:focus-visible' ), true ), true );
ck( 'disabled: 55% opacity and the not-allowed cursor', array( $has( '.wpcpm-ui-on .wpcpm-btn:disabled', 'opacity: 0.55' ), $has( '.wpcpm-ui-on .wpcpm-btn:disabled', 'cursor: not-allowed' ) ), array( true, true ) );

// A pressed quiet button that is hovered keeps no underline (it is pressed, so it shows the tint).
ck( 'pressed quiet, hovered: no underline', array(
	$has( '.wpcpm-ui-on .wpcpm-btn--quiet[aria-expanded="true"]:hover' . $guard, 'text-decoration: none' ),
	$has( '.wpcpm-ui-on .button.wpcpm-btn--quiet[aria-expanded="true"]:hover' . $guard, 'text-decoration: none' ),
), array( true, true ) );
// A disabled button does not change colour on hover: every hover rule of a button under .wpcpm-ui-on carries
// the guard, which is :where() so it adds no weight and a pressed button (equal weight, later) still wins.
$unguarded = array();
$hovers    = 0;
foreach ( $parts as $r ) {
	if ( 0 === strpos( $r[0], '.wpcpm-ui-on' ) && false !== strpos( $r[0], ':hover' ) && 1 === preg_match( '/\.(wpcpm-btn|button)/', $r[0] ) ) {
		++$hovers;
		if ( substr( $r[0], -strlen( $guard ) ) !== $guard ) {
			$unguarded[] = $r[0];
		}
	}
}
ck( 'the sheet has hover rules for the buttons', $hovers > 5, true );
ck( 'every button hover rule skips a disabled or aria-disabled button', $unguarded, array() );

// A new look of an old shared class lives only under .wpcpm-ui-on.
$leaks = array();
foreach ( $parts as $r ) {
	if ( 0 === strpos( $r[0], ':where( .wpcpm-frame' ) || 'wpcpm-wrap )' === $r[0] || '.wpcpm-dashboard' === $r[0] ) {
		continue; // A piece of the token selector list, which declares the tokens.
	}
	$text = implode( ';', $r[1] );
	$new  = false !== strpos( $text, '--wpcpm-f-green-border' )
		|| false !== strpos( $text, '--wpcpm-f-primary-hover' )
		|| ( false !== strpos( $r[0], '.wpcpm-status' ) && false !== strpos( $text, 'border-color: var( --wpcpm-f-line )' ) );
	// Only a rule that names one of the old shared classes: the parts' own classes (the fold's cue) may use the token.
	if ( $new && 1 === preg_match( '/\.(wpcpm-btn|wpcpm-status|button)\b/', $r[0] ) && 0 !== strpos( $r[0], '.wpcpm-ui-on' ) ) {
		$leaks[] = $r[0];
	}
}
ck( 'the secondary hover and the two tag borders appear only in rules under .wpcpm-ui-on, wherever a rule names an old shared class', $leaks, array() );
ck( 'the neutral tag border, under .wpcpm-ui-on', $has( '.wpcpm-ui-on .wpcpm-status', 'border-color: var( --wpcpm-f-line )' ), true );
ck( 'the green tag border, under .wpcpm-ui-on', $has( '.wpcpm-ui-on .wpcpm-status--green', 'border-color: var( --wpcpm-f-green-border )' ), true );
ck( 'the red tag keeps its border under .wpcpm-ui-on', $has( '.wpcpm-ui-on .wpcpm-status--red', 'border-color: var( --wpcpm-f-red-border )' ), true );

// The topic chip, the heading, the intro and the action row.
foreach ( array( 'background: var( --wpcpm-f-brand-tint )', 'color: var( --wpcpm-f-ink )', 'font: 500 12px/20px var( --wpcpm-f-sans )', 'padding: 1px 10px', 'border-radius: 999px', 'border: 0' ) as $d ) {
	ck( "design 2.2: .wpcpm-ui-chip { $d }", $has( '.wpcpm-ui-chip', $d ), true );
}
foreach ( array( 'font: 600 16px/22px var( --wpcpm-f-sans )', 'color: var( --wpcpm-f-ink )', 'margin: var( --wpcpm-f-apart ) 0 8px' ) as $d ) {
	ck( "design 2.11: .wpcpm-ui-h { $d }", $has( '.wpcpm-ui-h', $d ), true );
}
ck( 'design 2.11: the first heading takes no room above', $has( '.wpcpm-ui-h:first-child', 'margin-top: 0' ), true );
ck( 'design 2.11: wp-admin heading is 14px/20px', $has( '.wpcpm-wrap .wpcpm-ui-h', 'font-size: 14px' ) && $has( '.wpcpm-wrap .wpcpm-ui-h', 'line-height: 20px' ), true );
foreach ( array( 'color: var( --wpcpm-f-ink-2 )', 'font: 400 14px/20px var( --wpcpm-f-sans )', 'margin: 0 0 8px' ) as $d ) {
	ck( "design 2.5: .wpcpm-ui-intro { $d }", $has( '.wpcpm-ui-intro', $d ), true );
}
ck( 'design 2.5: wp-admin intro is 13px', $has( '.wpcpm-wrap .wpcpm-ui-intro', 'font-size: 13px' ), true );
foreach ( array( 'display: flex', 'flex-wrap: wrap', 'gap: var( --wpcpm-f-gap )', 'margin-top: var( --wpcpm-f-block )' ) as $d ) {
	ck( "design 2.5: .wpcpm-ui-actions { $d }", $has( '.wpcpm-ui-actions', $d ), true );
}
ck( 'design 2.5: the consequence is a muted 14px hint', array( $has( '.wpcpm-ui-consequence', 'color: var( --wpcpm-f-muted )' ), $has( '.wpcpm-ui-consequence', 'font: 400 14px/20px var( --wpcpm-f-sans )' ) ), array( true, true ) );

// ---- Task 5: the field (design 2.4) ----
$ctl = '.wpcpm-ui-field .wpcpm-ui-field__control';
foreach ( array( 'display: block', 'font: 600 14px/20px var( --wpcpm-f-sans )', 'color: var( --wpcpm-f-ink )' ) as $d ) {
	ck( "design 2.4: .wpcpm-ui-field__label { $d }", $has( '.wpcpm-ui-field__label', $d ), true );
}
// Task 18c: the word follows one ordinary space (WPCPM_UI::need_gap()), so its margin is the 6px less that space.
ck( 'design 2.4: the need word is regular weight, muted, 6px away: one space and the 6px less a quarter of an em', array( $has( '.wpcpm-ui-field__need', 'font-weight: 400' ), $has( '.wpcpm-ui-field__need', 'color: var( --wpcpm-f-muted )' ), $has( '.wpcpm-ui-field__need', 'margin-left: calc( var( --wpcpm-f-tie ) - 0.25em )' ) ), array( true, true, true ) );
foreach ( array( 'box-sizing: border-box', 'min-height: 40px', 'border: 1px solid var( --wpcpm-f-control )', 'border-radius: 2px', 'padding: 9px 12px', 'background-color: var( --wpcpm-f-surface )', 'font-family: inherit', 'font-size: 14px', 'font-weight: 400', 'line-height: 20px', 'color: var( --wpcpm-f-ink )', 'width: 100%', 'max-width: 640px' ) as $d ) {
	ck( "design 2.4: $ctl { $d }", $has( $ctl, $d ), true );
}
ck( 'design 2.4: the box hover border is #40464d, not on a disabled box', $has( $ctl . ':hover' . $guard, 'border-color: var( --wpcpm-f-ink-2 )' ), true );
$bare_hover = array();
foreach ( $parts as $r ) {
	if ( false !== strpos( $r[0], '.wpcpm-ui-field__control' ) && false !== strpos( $r[0], ':hover' ) && substr( $r[0], -strlen( $guard ) ) !== $guard ) {
		$bare_hover[] = $r[0];
	}
}
ck( 'a disabled field does not change on hover: every hover rule of the box carries the guard', $bare_hover, array() );
ck( 'design 2.4: the box focus border is Blueberry with the focus ring', array( $has( $ctl . ':focus', 'border-color: var( --wpcpm-f-brand )' ), $has( $ctl . ':focus', 'outline: 2px solid var( --wpcpm-f-brand )' ), $has( $ctl . ':focus', 'outline-offset: 2px' ) ), array( true, true, true ) );
ck( 'design 2.4: a select is as wide as its longest option, 200px to 320px', array( $has( $ctl . '--select', 'width: auto' ), $has( $ctl . '--select', 'min-width: 200px' ), $has( $ctl . '--select', 'max-width: 320px' ) ), array( true, true, true ) );
ck( 'design 2.4: a select has its own chevron 10px from the right and 36px of right padding', array( $has( $ctl . '--select', 'padding-right: 36px' ), $has( $ctl . '--select', 'appearance: none' ), $has( $ctl . '--select', 'background-position: right 10px center' ), false !== strpos( implode( ';', $sel_decls( $ctl . '--select' ) ), 'background-image: url(' ) ), array( true, true, true, true ) );
ck( 'design 2.4: a long select is at most 640px', $has( $ctl . '--long', 'max-width: 640px' ), true );
foreach ( array( '--date', '--number' ) as $m ) {
	ck( "design 2.4: $ctl$m is 200px", array( $has( $ctl . $m, 'width: 200px' ), $has( $ctl . $m, 'max-width: 100%' ) ), array( true, true ) );
}
ck( 'design 2.4: a text area is the card\'s width, at least 96px, resizable up and down', array( $has( $ctl . '--textarea', 'max-width: none' ), $has( $ctl . '--textarea', 'min-height: 96px' ), $has( $ctl . '--textarea', 'resize: vertical' ) ), array( true, true, true ) );
foreach ( array( 'color: var( --wpcpm-f-muted )', 'font: 400 14px/20px var( --wpcpm-f-sans )', 'margin: var( --wpcpm-f-tie ) 0 0' ) as $d ) {
	ck( "design 2.4: .wpcpm-ui-field__hint { $d }", $has( '.wpcpm-ui-field__hint', $d ), true );
}
ck( 'design 2.4: 14px between fields', $has( '.wpcpm-ui-field + .wpcpm-ui-field', 'margin-top: var( --wpcpm-f-field )' ), true );
ck( 'design 2.4: the label and the hint are 13px in wp-admin', array( $has( '.wpcpm-wrap .wpcpm-ui-field__label', 'font-size: 13px' ), $has( '.wpcpm-wrap .wpcpm-ui-field__hint', 'font-size: 13px' ) ), array( true, true ) );


// The box's weight: a rule setting its border, padding, height or radius has at least two classes of weight
// (a class, an attribute or a pseudo-class each count one), its focus rule at least three, so that WordPress's
// own form rules in wp-admin (an element with an attribute; their focus rules heavier still) do not win.
$weight = function ( $selector ) {
	$selector = preg_replace( '/:where\((?:[^()]|\([^()]*\))*\)/', '', $selector );
	$selector = preg_replace( '/::[a-z-]+/', '', $selector );
	return preg_match_all( '/\.[a-z_][\w-]*|\[[^\]]*\]|:[a-z-]+/i', $selector );
};
ck( 'the weight reader: a class plus an attribute plus a pseudo-class is three; :where() adds none', array( $weight( '.a .b' ), $weight( '.a[x="1"]:focus' ), $weight( '.a:hover:where(:not(:disabled))' ), $weight( 'input[type="text"]:focus' ) ), array( 2, 3, 2, 2 ) );
$light = array();
$boxes = 0;
foreach ( $parts as $r ) {
	if ( false === strpos( $r[0], '.wpcpm-ui-field__control' ) ) {
		continue;
	}
	$sets = false;
	foreach ( $r[1] as $d ) {
		if ( 1 === preg_match( '/^(border|border-color|border-radius|padding|padding-right|min-height|height)\s*:/', $d ) ) {
			$sets = true;
		}
	}
	if ( $sets ) {
		++$boxes;
		$need = false !== strpos( $r[0], ':focus' ) ? 3 : 2;
		if ( $weight( $r[0] ) < $need ) {
			$light[] = $r[0];
		}
	}
}
ck( 'the sheet has rules for the box\'s border, padding, height and radius', $boxes >= 5, true );
ck( 'every rule setting the box\'s border, padding, height or radius has two classes of weight, its focus rule three', $light, array() );

// The refusal paragraph (WPCPM_Form_Errors::problem() prints the legacy class): styled under the field's wrapper.
$pr = '.wpcpm-ui-field .wpcpm-field__problem';
ck( 'the refusal: red words, 14px/20px, 6px above', array( $has( $pr, 'color: var( --wpcpm-f-red )' ), $has( $pr, 'font: 400 14px/20px var( --wpcpm-f-sans )' ), $has( $pr, 'margin: var( --wpcpm-f-tie ) 0 0' ) ), array( true, true, true ) );
// No selector names a legacy wpcpm class but the frame, the wrap, the dashboard, the button and the tag the
// sheet takes over, and the refusal paragraph, which only the field's wrapper may name.
$legacy = array();
foreach ( $parts as $r ) {
	if ( $pr === $r[0] ) {
		continue;
	}
	if ( preg_match_all( '/\.(wpcpm-(?!ui-)[\w-]*)/', $r[0], $mm ) ) {
		foreach ( $mm[1] as $c ) {
			if ( ! in_array( $c, array( 'wpcpm-frame', 'wpcpm-wrap', 'wpcpm-dashboard' ), true ) && 1 !== preg_match( '/^wpcpm-(btn|status)(--[\w-]+)?$/', $c ) ) {
				$legacy[] = $r[0];
			}
		}
	}
}
ck( 'the only legacy class the sheet names outside the frame\'s own is the refusal paragraph, inside the field\'s wrapper', $legacy, array() );

// ---- Task 6: the folds, the item header, the table and the list row (design 2.3, 2.7, 2.8, 2.10) ----
// The rules inside one media query, by its text, read from the raw sheet.
$media = function ( $query ) use ( $raw ) {
	$all    = array();
	$offset = 0;
	while ( false !== ( $at = strpos( $raw, '@media ' . $query, $offset ) ) ) {
		$open  = strpos( $raw, '{', $at );
		$depth = 0;
		$len   = strlen( $raw );
		for ( $i = $open; $i < $len; $i++ ) {
			if ( '{' === $raw[ $i ] ) {
				++$depth;
			} elseif ( '}' === $raw[ $i ] && 0 === --$depth ) {
				break;
			}
		}
		$all    = array_merge( $all, rules_of( substr( $raw, $open + 1, $i - $open - 1 ) ) );
		$offset = $i;
	}
	return $all;
};
// Task 18f, fix round 2: every rule of the narrow form, the phone's rules repeated for a main area under 800px.
$narrow = (function () use ( $raw ) {
	$all    = array();
	$offset = 0;
	$q      = '@container wpcpm-main ( max-width: 799.98px )';
	while ( false !== ( $at = strpos( $raw, $q, $offset ) ) ) {
		$open  = strpos( $raw, '{', $at );
		$depth = 0;
		$len   = strlen( $raw );
		for ( $i = $open; $i < $len; $i++ ) {
			if ( '{' === $raw[ $i ] ) {
				++$depth;
			} elseif ( '}' === $raw[ $i ] && 0 === --$depth ) {
				break;
			}
		}
		$all    = array_merge( $all, rules_of( substr( $raw, $open + 1, $i - $open - 1 ) ) );
		$offset = $i;
	}
	return $all;
})();
$in = function ( $rules, $selector, $decl ) {
	foreach ( $rules as $r ) {
		if ( $r[0] === $selector && in_array( $decl, $r[1], true ) ) {
			return true;
		}
	}
	return false;
};

// The card fold.
ck( 'design 2.3: the card is a 1px #e6e6e6 box, 2px corners, white', array( $has( '.wpcpm-ui-fold', 'border: 1px solid var( --wpcpm-f-line )' ), $has( '.wpcpm-ui-fold', 'border-radius: 2px' ), $has( '.wpcpm-ui-fold', 'background: var( --wpcpm-f-surface )' ) ), array( true, true, true ) );
ck( 'design 2.3: the card head is 56px high, padding 8px 16px, gap 12px, one row', array( $has( '.wpcpm-ui-fold__head', 'min-height: 56px' ), $has( '.wpcpm-ui-fold__head', 'padding: 8px 16px' ), $has( '.wpcpm-ui-fold__head', 'gap: var( --wpcpm-f-gap )' ), $has( '.wpcpm-ui-fold__head', 'display: flex' ) ), array( true, true, true, true ) );
ck( 'design 2.3: the title is 600 13px/20px, uppercase, 0.06em', array( $has( '.wpcpm-ui-fold__title', 'font: 600 13px/20px var( --wpcpm-f-sans )' ), $has( '.wpcpm-ui-fold__title', 'text-transform: uppercase' ), $has( '.wpcpm-ui-fold__title', 'letter-spacing: 0.06em' ) ), array( true, true, true ) );
ck( 'design 2.3: the phrase takes the room between the title and the end group, so the end group sits at the right', array( $has( '.wpcpm-ui-fold__phrase', 'flex: 1 1 auto' ), $has( '.wpcpm-ui-fold__phrase', 'min-width: 0' ), $has( '.wpcpm-ui-count', 'flex: 1 1 auto' ) ), array( true, true, true ) );
ck( 'design 2.3: the phrase is 400 14px/20px muted', array( $has( '.wpcpm-ui-fold__phrase', 'font: 400 14px/20px var( --wpcpm-f-sans )' ), $has( '.wpcpm-ui-fold__phrase', 'color: var( --wpcpm-f-muted )' ) ), array( true, true ) );
ck( 'design 2.3: the cue is the secondary button, 84px wide at least', array( $has( '.wpcpm-ui-fold__cue', 'min-width: 84px' ), $has( '.wpcpm-ui-fold__cue', 'min-height: 40px' ), $has( '.wpcpm-ui-fold__cue', 'color: var( --wpcpm-f-brand )' ), $has( '.wpcpm-ui-fold__cue', 'border: 1px solid var( --wpcpm-f-brand )' ), $has( '.wpcpm-ui-fold__cue', 'padding: 9px 16px' ) ), array( true, true, true, true, true ) );
ck( 'design 2.3: hovering the head gives the cue the secondary hover', array( $has( '.wpcpm-ui-fold__head:hover .wpcpm-ui-fold__cue', 'background: var( --wpcpm-f-primary-hover )' ), $has( '.wpcpm-ui-fold__head:hover .wpcpm-ui-fold__cue', 'color: #ffffff' ) ), array( true, true ) );
ck( 'design 2.3: open, the cue is not shown and keeps its place', $has( '.wpcpm-ui-fold[open] > .wpcpm-ui-fold__head .wpcpm-ui-fold__cue', 'visibility: hidden' ), true );
ck( 'design 2.3: the help slot is 104px, right-aligned, kept when empty', array( $has( '.wpcpm-ui-fold__help-slot', 'width: 104px' ), $has( '.wpcpm-ui-fold__help-slot', 'flex: none' ), $has( '.wpcpm-ui-fold__help', 'width: 104px' ), $has( '.wpcpm-ui-fold__help', 'text-align: right' ) ), array( true, true, true, true ) );
ck( 'design 2.3: the help link is 400 14px/20px muted, underlined in #e4e4e4 3px under the words, Blueberry on hover', array( $has( '.wpcpm-ui-fold__help', 'font: 400 14px/20px var( --wpcpm-f-sans )' ), $has( '.wpcpm-ui-fold__help', 'color: var( --wpcpm-f-muted )' ), $has( '.wpcpm-ui-fold__help', 'text-decoration-color: var( --wpcpm-f-line-card )' ), $has( '.wpcpm-ui-fold__help', 'text-underline-offset: 3px' ), $has( '.wpcpm-ui-fold__help:hover', 'color: var( --wpcpm-f-brand )' ) ), array( true, true, true, true, true ) );
ck( 'design 2.3: the chevron is 20px square, #40464d, a 150ms turn', array( $has( '.wpcpm-ui-fold__chev', 'width: 20px' ), $has( '.wpcpm-ui-fold__chev', 'height: 20px' ), $has( '.wpcpm-ui-fold__chev', 'color: var( --wpcpm-f-ink-2 )' ), $has( '.wpcpm-ui-fold__chev', 'transition: transform 150ms' ) ), array( true, true, true, true ) );
ck( 'design 2.3: the chevron points up while open', $has( '.wpcpm-ui-fold[open] > .wpcpm-ui-fold__head .wpcpm-ui-fold__chev', 'transform: rotate( 180deg )' ), true );
ck( 'design 2.3: no turn under prefers-reduced-motion', $in( $media( '(prefers-reduced-motion: reduce)' ), '.wpcpm-ui-fold__chev', 'transition: none' ), true );
ck( 'design 2.3: the external icon is the chevron\'s size', array( $has( '.wpcpm-ui-fold__ext', 'width: 20px' ), $has( '.wpcpm-ui-fold__ext', 'height: 20px' ) ), array( true, true ) );
ck( 'design 2.3: the open head\'s hairline is the body\'s top edge; the body is inset 16px with 20px above and below', array( $has( '.wpcpm-ui-fold__body', 'border-top: 1px solid var( --wpcpm-f-line )' ), $has( '.wpcpm-ui-fold__body', 'padding: 20px 16px' ) ), array( true, true ) );
ck( 'design 2.3: the body\'s first block has no space above, its last none below, its blocks are 16px apart', array( $has( '.wpcpm-ui-fold__body > :first-child', 'margin-top: 0' ), $has( '.wpcpm-ui-fold__body > :last-child', 'margin-bottom: 0' ), $has( ':where( .wpcpm-ui-fold__body > * + * )', 'margin-top: var( --wpcpm-f-block )' ) ), array( true, true, true ) );
ck( 'design 2.3: 10px between sibling folds, 22px from a group of folds to the next module', array( $has( '.wpcpm-ui-fold + .wpcpm-ui-fold', 'margin-top: var( --wpcpm-f-fold )' ), $has( '.wpcpm-ui-fold:where( :not( .wpcpm-ui-fold--inner ) ) + :where( :not( .wpcpm-ui-fold ) )', 'margin-top: var( --wpcpm-f-module )' ) ), array( true, true ) );
$at = array();
foreach ( $parts as $i => $r ) {
	if ( ! isset( $at[ $r[0] ] ) ) {
		$at[ $r[0] ] = $i;
	}
}
$mod = '.wpcpm-ui-fold:where( :not( .wpcpm-ui-fold--inner ) ) + :where( :not( .wpcpm-ui-fold ) )';
ck( 'design 2.3: the 22px rule weighs one class and comes after every single-class rule of the parts it must tie-break', array( $weight( $mod ), isset( $at[ $mod ] ) && $at[ $mod ] > $at['.wpcpm-ui-list__row'] && $at[ $mod ] > $at['.wpcpm-ui-h'] && $at[ $mod ] > $at['.wpcpm-ui-table'] ), array( 1, true ) );
// A run of inner folds: no 10px gap between them, they share their hairlines; 16px to the blocks around the run.
$run = '.wpcpm-ui-fold--inner + .wpcpm-ui-fold--inner';
ck( 'design 2.3: two adjacent inner folds have no gap and no second hairline, and that rule is after the 10px rule and ties its weight', array(
	$has( $run, 'margin-top: 0' ), $has( $run, 'border-top: 0' ), $at[ $run ] > $at['.wpcpm-ui-fold + .wpcpm-ui-fold'], $weight( $run ) >= $weight( '.wpcpm-ui-fold + .wpcpm-ui-fold' ),
), array( true, true, true, true ) );
ck( 'design 2.3: a run of inner folds is 16px from the block before it and from the block after it', array( $has( '.wpcpm-ui-fold--inner', 'margin: var( --wpcpm-f-block ) 0 0' ), $has( '.wpcpm-ui-fold--inner + :not( .wpcpm-ui-fold--inner )', 'margin-top: var( --wpcpm-f-block )' ) ), array( true, true ) );
// The inner fold.
ck( 'design 2.3: the inner head is 44px high, padding 12px 0', array( $has( '.wpcpm-ui-fold--inner > .wpcpm-ui-fold__head', 'min-height: 44px' ), $has( '.wpcpm-ui-fold--inner > .wpcpm-ui-fold__head', 'padding: 12px 0' ) ), array( true, true ) );
ck( 'design 2.3: the inner fold has no box: a hairline above and below, none doubled', array( $has( '.wpcpm-ui-fold--inner', 'border: 0' ), $has( '.wpcpm-ui-fold--inner', 'border-top: 1px solid var( --wpcpm-f-line )' ), $has( '.wpcpm-ui-fold--inner', 'border-bottom: 1px solid var( --wpcpm-f-line )' ), $has( '.wpcpm-ui-fold--inner + .wpcpm-ui-fold--inner', 'border-top: 0' ), $has( '.wpcpm-ui-table + .wpcpm-ui-fold--inner', 'border-top: 0' ) ), array( true, true, true, true, true ) );
ck( 'design 2.3: the inner title is 600 14px/20px in sentence case, 13px in wp-admin, Blueberry on hover', array( $has( '.wpcpm-ui-fold--inner .wpcpm-ui-fold__title', 'font: 600 14px/20px var( --wpcpm-f-sans )' ), $has( '.wpcpm-ui-fold--inner .wpcpm-ui-fold__title', 'text-transform: none' ), $has( '.wpcpm-wrap .wpcpm-ui-fold--inner .wpcpm-ui-fold__title', 'font-size: 13px' ), $has( '.wpcpm-ui-fold--inner > .wpcpm-ui-fold__head:hover .wpcpm-ui-fold__title', 'color: var( --wpcpm-f-brand )' ) ), array( true, true, true, true ) );
ck( 'design 2.3: the inner body is 12px under the head, 16px under itself, with no hairline of its own', array( $has( '.wpcpm-ui-fold--inner > .wpcpm-ui-fold__body', 'padding: 12px 0 16px' ), $has( '.wpcpm-ui-fold--inner > .wpcpm-ui-fold__body', 'border-top: 0' ) ), array( true, true ) );
ck( 'design 2.3: the second piece is stacked 2px under the title', array( $has( '.wpcpm-ui-fold__second', 'display: block' ), $has( '.wpcpm-ui-fold__second', 'margin-top: 2px' ) ), array( true, true ) );
// The phone: 600px on the front end, 782px under .wpcpm-wrap.
foreach ( array( '(max-width: 600px)' => '', '(max-width: 782px)' => '.wpcpm-wrap ' ) as $q => $pre ) {
	$m = $media( $q );
	ck( "phone $q: the sheet has the query", array() !== $m, true );
	ck( "phone $q: a card head is a grid, padding 12px 16px, gap 6px 12px, the phrase's column free to be narrower than a long word, the tag's column (1fr, least size the tag's own) keeping the tag whole; the title spans a flexible column, so it never sizes one (Task 18f, fix round 1)", array( $in( $m, $pre . '.wpcpm-ui-fold__head', 'grid-template-columns: minmax( 0, auto ) 1fr 20px' ) && $in( $m, $pre . '.wpcpm-ui-fold__head', 'display: grid' ), $in( $m, $pre . '.wpcpm-ui-fold__head', 'padding: 12px 16px' ), $in( $m, $pre . '.wpcpm-ui-fold__head', 'gap: 6px 12px' ) ), array( true, true, true ) );
	ck( "phone $q: no cue and no help link in the head", array( $in( $m, $pre . '.wpcpm-ui-fold__cue', 'display: none' ), $in( $m, $pre . '.wpcpm-ui-fold__help', 'display: none' ), $in( $m, $pre . '.wpcpm-ui-fold__help-slot', 'display: none' ) ), array( true, true, true ) );
	ck( "phone $q: an inner head is two columns, the second a fixed 20px pinned at the top", array( $in( $m, $pre . '.wpcpm-ui-fold--inner > .wpcpm-ui-fold__head', 'grid-template-columns: minmax( 0, 1fr ) 20px' ), $in( $m, $pre . '.wpcpm-ui-fold--inner > .wpcpm-ui-fold__head', 'align-items: start' ) ), array( true, true ) );
	ck( "phone $q: an inner head's chevron is in column 2, pinned at the top right, and the text column is column 1", array( $in( $m, $pre . '.wpcpm-ui-fold--inner .wpcpm-ui-fold__chev', 'grid-column: 2' ), $in( $m, $pre . '.wpcpm-ui-fold--inner .wpcpm-ui-fold__chev', 'grid-row: 1' ), $in( $m, $pre . '.wpcpm-ui-fold--inner .wpcpm-ui-fold__chev', 'align-self: start' ), $in( $m, $pre . '.wpcpm-ui-fold--inner .wpcpm-ui-fold__lead', 'grid-column: 1' ) ), array( true, true, true, true ) );
	ck( "phone $q: a card head's children are placed by the head, with no lead wrapper", array( $in( $m, $pre . '.wpcpm-ui-fold__end', 'display: contents' ), $in( $m, $pre . '.wpcpm-ui-fold__lead', 'display: contents' ) ), array( true, false ) );
	ck( "phone $q: a key and value table prints no label before its value (the row header is the label)", $in( $m, $pre . '.wpcpm-ui-table--keyvalue td[data-label]::before', 'content: none' ), true );
	// Fix round 3: on the front end a stacked row is a one-column grid (so a joining cell can share the header's line).
	ck( "phone $q: a wide table is one block per row, each value under its column's name", array( '' === $pre ? $in( $m, '.wpcpm-ui-table tr', 'display: grid' ) && $in( $m, '.wpcpm-ui-table tr', 'grid-template-columns: minmax( 0, 1fr )' ) : $in( $m, $pre . '.wpcpm-ui-table tr', 'display: block' ), $in( $m, $pre . '.wpcpm-ui-table td', 'display: block' ), $in( $m, $pre . '.wpcpm-ui-table td[data-label]::before', 'content: attr( data-label )' ) ), array( true, true, true ) );
	ck( "phone $q: the action cell reads from the left, number cells too", array( $in( $m, $pre . '.wpcpm-ui-table td.wpcpm-ui-cell--action', 'text-align: left' ), $in( $m, $pre . '.wpcpm-ui-table td.wpcpm-ui-cell--num', 'text-align: left' ) ), array( true, true ) );
	ck( "phone $q: a key and value table stacks the label over the value", array( $in( $m, $pre . '.wpcpm-ui-table--keyvalue th', 'display: block' ), $in( $m, $pre . '.wpcpm-ui-table--keyvalue th', 'width: auto' ) ), array( true, true ) );
	ck( "phone $q: a list row is name and tag, then the facts, then the action", array( $in( $m, $pre . '.wpcpm-ui-list__line', 'grid-template-columns: minmax( 0, 1fr ) auto' ), $in( $m, $pre . '.wpcpm-ui-list__facts', 'grid-column: 1 / -1' ), $in( $m, $pre . '.wpcpm-ui-list__act', 'grid-column: 1 / -1' ), $in( $m, $pre . '.wpcpm-ui-list__act', 'justify-self: start' ), $in( $m, $pre . '.wpcpm-ui-list__facts:empty', 'display: none' ) ), array( true, true, true, true, true ) );
}
// The table (design 2.7).
ck( 'design 2.7: full width, collapsed borders, no outer border, no fill', array( $has( '.wpcpm-ui-table', 'width: 100%' ), $has( '.wpcpm-ui-table', 'border-collapse: collapse' ), $has( '.wpcpm-ui-table', 'border: 0' ), $has( '.wpcpm-ui-table', 'background: none' ) ), array( true, true, true, true ) );
ck( 'design 2.7: cells are 12px, 400 14px/20px ink, top-aligned, a soft hairline under', array( $has( '.wpcpm-ui-table td', 'padding: 12px' ), $has( '.wpcpm-ui-table td', 'font: 400 14px/20px var( --wpcpm-f-sans )' ), $has( '.wpcpm-ui-table td', 'color: var( --wpcpm-f-ink )' ), $has( '.wpcpm-ui-table td', 'vertical-align: top' ), $has( '.wpcpm-ui-table td', 'border-bottom: 1px solid var( --wpcpm-f-line-soft )' ) ), array( true, true, true, true, true ) );
ck( 'design 2.7: the header is 600 12px/18px muted, a #e6e6e6 hairline under it, never wraps', array( $has( '.wpcpm-ui-table th', 'font: 600 12px/18px var( --wpcpm-f-sans )' ), $has( '.wpcpm-ui-table th', 'color: var( --wpcpm-f-muted )' ), $has( '.wpcpm-ui-table th', 'border-bottom: 1px solid var( --wpcpm-f-line )' ), $has( '.wpcpm-ui-table th', 'white-space: nowrap' ) ), array( true, true, true, true ) );
ck( 'design 2.7: cells are 13px in wp-admin', $has( '.wpcpm-wrap .wpcpm-ui-table td', 'font-size: 13px' ), true );
ck( 'design 2.7: inside a card the outer cells have no padding at the table\'s edges', array( $has( '.wpcpm-ui-fold__body .wpcpm-ui-table th:first-child', 'padding-left: 0' ), $has( '.wpcpm-ui-fold__body .wpcpm-ui-table td:first-child', 'padding-left: 0' ), $has( '.wpcpm-ui-fold__body .wpcpm-ui-table td:last-child', 'padding-right: 0' ) ), array( true, true, true ) );
ck( 'design 2.7: numbers are right-aligned in tabular figures and never wrap; dates and short cells never wrap', array( $has( '.wpcpm-ui-table .wpcpm-ui-cell--num', 'text-align: right' ), $has( '.wpcpm-ui-table .wpcpm-ui-cell--num', 'font-variant-numeric: tabular-nums' ), $has( '.wpcpm-ui-table .wpcpm-ui-cell--num', 'white-space: nowrap' ), $has( '.wpcpm-ui-table .wpcpm-ui-cell--date', 'white-space: nowrap' ), $has( '.wpcpm-ui-table .wpcpm-ui-cell--short', 'white-space: nowrap' ), $has( '.wpcpm-ui-table code', 'white-space: nowrap' ) ), array( true, true, true, true, true, true ) );
ck( 'design 2.7: the action cell is right-aligned, centered, 2px above and below', array( $has( '.wpcpm-ui-table td.wpcpm-ui-cell--action', 'text-align: right' ), $has( '.wpcpm-ui-table td.wpcpm-ui-cell--action', 'vertical-align: middle' ), $has( '.wpcpm-ui-table td.wpcpm-ui-cell--action', 'padding-top: 2px' ), $has( '.wpcpm-ui-table td.wpcpm-ui-cell--action', 'padding-bottom: 2px' ) ), array( true, true, true, true ) );
ck( 'design 2.7: key and value, a 320px label column, 600 muted labels; an empty value is muted', array( $has( '.wpcpm-ui-table--keyvalue th', 'width: 320px' ), $has( '.wpcpm-ui-table--keyvalue th', 'font: 600 14px/20px var( --wpcpm-f-sans )' ), $has( '.wpcpm-ui-table--keyvalue th', 'color: var( --wpcpm-f-muted )' ), $has( '.wpcpm-ui-table .wpcpm-ui-cell--empty', 'color: var( --wpcpm-f-muted )' ) ), array( true, true, true, true ) );
ck( 'design 2.7: a table that is the last thing in a fold draws no hairline of its own at the end', $has( '.wpcpm-ui-fold__body > .wpcpm-ui-table:last-child tr:last-child td', 'border-bottom: 0' ), true );
// Task 17: a table that ends its card from inside a form (Students gone quiet) says so by its modifier, and the sheet
// gives it the same declaration by that class alone: no element in front of it, no :has().
$t17_end = array();
foreach ( $parts as $r ) {
	if ( false !== strpos( $r[0], 'wpcpm-ui-table--end' ) ) {
		$t17_end[] = $r[0] . ' { ' . implode( '; ', $r[1] ) . ' }';
	}
}
ck( 'design 2.7: a table marked as ending its card draws no hairline of its own at the end, wherever it sits in the card\'s body, by its modifier class alone; its row header too, and its last row where a stacked row carries the hairline', $t17_end, array( '.wpcpm-ui-fold__body .wpcpm-ui-table--end tr:last-child td { border-bottom: 0 }', '.wpcpm-ui-fold__body .wpcpm-ui-table--end tr:last-child .wpcpm-ui-cell--rowhead { border-bottom: 0 }', '.wpcpm-ui-fold__body .wpcpm-ui-table--end tr:last-child { border-bottom: 0 }' ) );
// Task 17, fix round 1: stacked (on a phone, or a wide table in a narrow main area) the hairline is the row's, so the
// table that is the body's last child drops its last row's too.
ck( 'design 2.7: the body\'s last table drops its last row\'s hairline whether the cells or the stacked row carry it', array( $has( '.wpcpm-ui-fold__body > .wpcpm-ui-table:last-child tr:last-child', 'border-bottom: 0' ), $has( '.wpcpm-ui-fold__body > .wpcpm-ui-table:last-child tr:last-child td', 'border-bottom: 0' ), $has( '.wpcpm-ui-fold__body > .wpcpm-ui-table:last-child tr:last-child .wpcpm-ui-cell--rowhead', 'border-bottom: 0' ) ), array( true, true, true ) );

// Task 17, fix round 1: a wide table's row header reads as its row's name, a body cell's look, keyed on its own class.
// Every rule naming the class, in the sheet's order, outside the phone queries and inside each.
$t17_rh = static function ( $rules ) {
	$out = array();
	foreach ( $rules as $r ) {
		if ( false !== strpos( $r[0], 'wpcpm-ui-cell--rowhead' ) ) {
			$out[] = $r[0] . ' { ' . implode( '; ', $r[1] ) . ' }';
		}
	}
	return $out;
};
$t17_phone = array_merge( $t17_rh( $media( '(max-width: 600px)' ) ), $t17_rh( $media( '(max-width: 782px)' ) ) );
ck( 'design 2.7: the row header takes a body cell\'s look (ink, 400 14px/20px, wrapping, top-aligned, 12px, the soft hairline), 13px in wp-admin; the two rules that drop the last row\'s hairline reach it as they reach a cell', array_values( array_filter( array_diff( $t17_rh( $parts ), $t17_phone ), static function ( $rule ) { return 0 !== strpos( $rule, '.wpcpm-ui-table--wide' ) && '.wpcpm-ui-table .wpcpm-ui-cell--rowhead { grid-column: 1 / -1 }' !== $rule; } ) ), array(
	'.wpcpm-ui-fold__body > .wpcpm-ui-table:last-child tr:last-child .wpcpm-ui-cell--rowhead { border-bottom: 0 }',
	'.wpcpm-ui-fold__body .wpcpm-ui-table--end tr:last-child .wpcpm-ui-cell--rowhead { border-bottom: 0 }',
	'.wpcpm-ui-table .wpcpm-ui-cell--rowhead { border-bottom: 1px solid var( --wpcpm-f-line-soft ); color: var( --wpcpm-f-ink ); font: 400 14px/20px var( --wpcpm-f-sans ); padding: 12px; text-align: left; vertical-align: top; white-space: normal }',
	'.wpcpm-wrap .wpcpm-ui-table .wpcpm-ui-cell--rowhead { color: var( --wpcpm-f-ink ); font-family: inherit; font-size: 13px }',
) );
ck( 'design 2.7: on a phone the row header is the row\'s first line, a block, 600 14px/20px, no hairline, 2px 0 as the stacked cells (600px; 782px under .wpcpm-wrap)', $t17_phone, array(
	'.wpcpm-ui-table .wpcpm-ui-cell--rowhead { border-bottom: 0; display: block; font: 600 14px/20px var( --wpcpm-f-sans ); grid-column: 1 / -1; grid-row: 1; padding: 2px 0 }',
	'.wpcpm-wrap .wpcpm-ui-table .wpcpm-ui-cell--rowhead { border-bottom: 0; display: block; font-size: 14px; font-weight: 600; line-height: 20px; padding: 2px 0 }',
) );
// The weights that make it win: over `.wpcpm-ui-table th` (and `.wpcpm-wrap .wpcpm-ui-table th`) by one class, under the
// card's edge rules (`.wpcpm-ui-fold__body .wpcpm-ui-table th:first-child`, 2 classes and an element), which keep no
// padding at the card's edge; after the kinds' rules, so a date-kind row header wraps.
$t17_at = static function ( $sel ) use ( $parts ) { foreach ( $parts as $i => $r ) { if ( $r[0] === $sel ) { return $i; } } return -1; };
// Task 17, fix round 1: a wide table (seven columns or more) stacks while the dashboard's main area, the frame's own
// container `wpcpm-main` (dashboard-frame.css), is narrower than 880px: exactly the phone's stacked table, the same
// declarations for the same selectors (the wide modifier in place of the table's class).
$t17_cq = array();
$t17_q  = '@container wpcpm-main ( max-width: 879.98px )';
for ( $t17_o = 0; false !== ( $t17_a = strpos( $raw, $t17_q, $t17_o ) ); $t17_o = $t17_i ) {
	$t17_open = strpos( $raw, '{', $t17_a );
	$t17_d    = 0;
	for ( $t17_i = $t17_open; $t17_i < strlen( $raw ); $t17_i++ ) {
		if ( '{' === $raw[ $t17_i ] ) {
			++$t17_d;
		} elseif ( '}' === $raw[ $t17_i ] && 0 === --$t17_d ) {
			break;
		}
	}
	$t17_cq = array_merge( $t17_cq, rules_of( substr( $raw, $t17_open + 1, $t17_i - $t17_open - 1 ) ) );
}
$t17_map = static function ( $rules, $wide ) {
	$out = array();
	foreach ( $rules as $r ) {
		// The phone's table rules for every table, but a key and value table's own; the container's for the wide one.
		if ( false !== strpos( $r[0], 'keyvalue' ) || 1 !== preg_match( $wide ? '/^\.wpcpm-ui-table--wide(\s|$)/' : '/^\.wpcpm-ui-table(\s|$)/', $r[0] ) ) {
			continue;
		}
		$key         = preg_replace( '/^\.wpcpm-ui-table(--wide)?/', '.TABLE', $r[0] );
		$out[ $key ] = array_merge( $out[ $key ] ?? array(), $r[1] );
	}
	return $out;
};
$t17_phone_t = $t17_map( $media( '(max-width: 600px)' ), false );
$t17_wide_t  = $t17_map( $t17_cq, true );
ck( 'design 2.7: one container query, on the frame\'s main area by its name, below 880px (spelled as every other container query in the plugin), holding only rules of the wide table; and one below 936px for a wide table that needs that much (Task 18d)', array(
	substr_count( $raw, '@container' ) - substr_count( $raw, '@container wpcpm-main ( max-width: 935.98px )' ) - substr_count( $raw, '@container wpcpm-main ( max-width: 799.98px )' ) - preg_match_all( '/@container wpcpm-main \( min-width: \d+px \) and/', $raw ) - substr_count( $raw, '@container wpcpm-main ( min-width: 800px )' ),
	1 === preg_match( '/\.wpcpm-frame__main\s*\{[^}]*container:\s*wpcpm-main\s*\/\s*inline-size/', (string) file_get_contents( __DIR__ . '/../assets/css/dashboard-frame.css' ) ),
	array_values( array_filter( array_map( static function ( $r ) { return $r[0]; }, $t17_cq ), static function ( $sel ) { return 0 !== strpos( $sel, '.wpcpm-ui-table--wide' ); } ) ),
), array( 1, true, array() ) );
ck( 'design 2.7: the wide table\'s stacked rules are the phone\'s, selector for selector and declaration for declaration (thead hidden, a block per row, the row header first, each value under its label, numbers and the action from the left)', array(
	array_keys( $t17_wide_t ),
	$t17_wide_t === $t17_phone_t,
), array( array( '.TABLE thead', '.TABLE', '.TABLE tbody', '.TABLE tr', '.TABLE tr:first-child', '.TABLE td', '.TABLE td[data-label]::before', '.TABLE td.wpcpm-ui-cell--num', '.TABLE td.wpcpm-ui-cell--action', '.TABLE td.wpcpm-ui-cell--blank', '.TABLE .wpcpm-ui-cell--rowhead', '.TABLE td.wpcpm-ui-cell--joins', '.TABLE td.wpcpm-ui-cell--joins::before' ), true ) );
ck( 'design 2.7: the container rules come after the table\'s own, so they win at the same weight; no :has(), no !important in them', array(
	strpos( $raw, $t17_q ) > strpos( $raw, '.wpcpm-ui-table .wpcpm-ui-cell--rowhead' ),
	array_values( array_filter( $t17_cq, static function ( $r ) { return false !== strpos( $r[0], ':has(' ) || false !== strpos( implode( ';', $r[1] ), '!important' ); } ) ),
), array( true, array() ) );
ck( 'design 2.7: the row header\'s rule comes after the kinds\' nowrap rules, and the card\'s edge rules still cover every th', array(
	$t17_at( '.wpcpm-ui-table .wpcpm-ui-cell--rowhead' ) > $t17_at( '.wpcpm-ui-table .wpcpm-ui-cell--date' ),
	$has( '.wpcpm-ui-fold__body .wpcpm-ui-table th:first-child', 'padding-left: 0' ),
	$has( '.wpcpm-ui-fold__body .wpcpm-ui-table th:last-child', 'padding-right: 0' ),
	array_values( array_filter( $t17_rh( $parts ), static function ( $rule ) { return false !== strpos( $rule, ':has(' ) || false !== strpos( $rule, '!important' ) || 1 === preg_match( '/#[0-9a-f]{3,6}\b/i', $rule ); } ) ),
), array( true, true, true, array() ) );
// The list row (design 2.8).
ck( 'design 2.8: a list with a hairline over it, no bullets', array( $has( '.wpcpm-ui-list', 'border-top: 1px solid var( --wpcpm-f-line )' ), $has( '.wpcpm-ui-list', 'list-style: none' ) ), array( true, true ) );
ck( 'design 2.8: each row a soft hairline under it', $has( '.wpcpm-ui-list__row', 'border-bottom: 1px solid var( --wpcpm-f-line-soft )' ), true );
ck( 'design 2.8: the line is a grid of name 1fr, state 160px, facts 1fr, action 200px; 56px high, padding 8px 0, gap 8px 16px', array( $has( '.wpcpm-ui-list__line', 'display: grid' ), $has( '.wpcpm-ui-list__line', 'grid-template-columns: minmax( 0, 1fr ) 160px minmax( 0, 1fr ) 200px' ), $has( '.wpcpm-ui-list__line', 'min-height: 56px' ), $has( '.wpcpm-ui-list__line', 'padding: 8px 0' ), $has( '.wpcpm-ui-list__line', 'gap: 8px 16px' ) ), array( true, true, true, true, true ) );
ck( 'design 2.8: the name is 600 14px/20px; the facts are muted, in tabular figures; the action is at the right, two 16px apart', array( $has( '.wpcpm-ui-list__name', 'font: 600 14px/20px var( --wpcpm-f-sans )' ), $has( '.wpcpm-ui-list__facts', 'color: var( --wpcpm-f-muted )' ), $has( '.wpcpm-ui-list__facts', 'font-variant-numeric: tabular-nums' ), $has( '.wpcpm-ui-list__act', 'justify-self: end' ), $has( '.wpcpm-ui-list__act', 'gap: 16px' ) ), array( true, true, true, true, true ) );
ck( 'design 2.8: the row form is a #f6f6f6 band, a soft hairline above, padding 16px', array( $has( '.wpcpm-ui-list__form', 'background: var( --wpcpm-f-neutral )' ), $has( '.wpcpm-ui-list__form', 'border-top: 1px solid var( --wpcpm-f-line-soft )' ), $has( '.wpcpm-ui-list__form', 'padding: 16px' ) ), array( true, true, true ) );
ck( 'design 2.8: no row left, one muted line', $has( '.wpcpm-ui-list__empty', 'color: var( --wpcpm-f-muted )' ), true );
// Task 11, fix round 1: a row's note on its own line, under the line, its left edge on the name's.
// Task 18c: a note fills its container (DESIGN.md: a paragraph's width is never capped inside a container).
ck( 'a row\'s note: 14px/20px in the second ink, line breaks kept, long words broken, to its container\'s edge, no margin, 6px above it and the row\'s own 8px under it', array(
	$has( '.wpcpm-ui-list__note', 'font: 400 14px/20px var( --wpcpm-f-sans )' ),
	$has( '.wpcpm-ui-list__note', 'color: var( --wpcpm-f-ink-2 )' ),
	$has( '.wpcpm-ui-list__note', 'white-space: pre-wrap' ),
	$has( '.wpcpm-ui-list__note', 'overflow-wrap: anywhere' ),
	! $has( '.wpcpm-ui-list__note', 'max-width: 640px' ),
	$has( '.wpcpm-ui-list__note', 'margin: 0' ),
	$has( '.wpcpm-ui-list__note', 'padding: 6px 0 8px' ),
	$has( '.wpcpm-ui-list__row--note > .wpcpm-ui-list__line', 'padding-bottom: 0' ),
), array_fill( 0, 8, true ) );
// The item header (design 2.10).
ck( 'design 2.10: the name is 600 16px/22px, the reference IBM Plex Mono 400 13px/20px muted with no box', array( $has( '.wpcpm-ui-item__title', 'font: 600 16px/22px var( --wpcpm-f-sans )' ), false !== strpos( implode( ';', $sel_decls( '.wpcpm-ui-item__ref' ) ), 'font: 400 13px/20px var( --wpcpm-f-mono )' ), $has( '.wpcpm-ui-item__ref', 'color: var( --wpcpm-f-muted )' ), $has( '.wpcpm-ui-item__ref', 'background: none' ), $has( '.wpcpm-ui-item__ref', 'padding: 0' ) ), array( true, true, true, true, true ) );
ck( 'design 2.10: the name line wraps, 4px 12px apart', array( $has( '.wpcpm-ui-item__title', 'display: flex' ), $has( '.wpcpm-ui-item__title', 'flex-wrap: wrap' ), $has( '.wpcpm-ui-item__title', 'gap: 4px 12px' ) ), array( true, true, true ) );
ck( 'design 2.10: the facts are 4px under, 400 14px/20px muted', array( $has( '.wpcpm-ui-item__facts', 'margin: 4px 0 0' ), $has( '.wpcpm-ui-item__facts', 'font: 400 14px/20px var( --wpcpm-f-sans )' ), $has( '.wpcpm-ui-item__facts', 'color: var( --wpcpm-f-muted )' ) ), array( true, true, true ) );
$dot_gutter = array(
	'run shifted left by the gutter' => $has( '.wpcpm-ui-item__run', 'margin-left: -16px' ),
	'and clipped 12px in, 4px short of the text origin, and 4px out on the other three sides, so a focus ring is whole' => $has( '.wpcpm-ui-item__run', 'clip-path: inset( -4px -4px -4px 12px )' ),
	'the list facts too' => $has( '.wpcpm-ui-list__facts', 'margin-left: -16px' ) && $has( '.wpcpm-ui-list__facts', 'clip-path: inset( -4px -4px -4px 12px )' ),
	'no 16px clip is left' => false === strpos( $raw, 'inset( 0 0 0 16px )' ),
	'each fact has the gutter on its left' => $has( '.wpcpm-ui-item__fact', 'margin-left: 16px' ) && $has( '.wpcpm-ui-item__fact', 'display: inline-block' ),
	// Task 18d, point 6: the dot's box is the whole 16px gutter, its glyph centered in it, so its centre is 8px from the
	// fact before and 8px from the fact after; at a line's start that centre is 8px out of the text, inside the 12px the
	// clip takes away.
	'its dot is centred in the gutter, midway between the fact before and the fact after, in the clipped part' => $has( '.wpcpm-ui-item__fact::before', 'margin-left: -16px' ) && $has( '.wpcpm-ui-item__fact::before', 'width: 16px' ) && $has( '.wpcpm-ui-item__fact::before', 'margin-right: 0' ) && $has( '.wpcpm-ui-item__fact::before', 'text-align: center' ) && $has( '.wpcpm-ui-item__fact::before', 'color: var( --wpcpm-f-icon )' ),
	'the run takes the room after the tag' => $has( '.wpcpm-ui-item__run', 'flex: 1 1 0' ) && $has( '.wpcpm-ui-item__run', 'min-width: 0' ),
	// Task V1, ruling 11: on a phone the line's first fact follows the tag and hides its dot; nowhere else.
	'no first-child hiding rule is left outside the phone query and its narrow copy' => count( array_filter( $parts, static function ( $r ) { return '.wpcpm-ui-item__fact:first-child::before' === $r[0]; } ) ) === 2 * count( array_filter( $media( '(max-width: 600px)' ), static function ( $r ) { return '.wpcpm-ui-item__fact:first-child::before' === $r[0]; } ) ),
);
ck( 'design 2.10: a middot never starts a line: each fact draws its dot in a gutter on its left, and the run is shifted left by it and clipped inside the gutter, room kept for a focus ring (only the rig proves it on a wrapped line)', array_keys( array_filter( $dot_gutter ) ), array_keys( $dot_gutter ) );
ck( 'design 2.10: the content of the dot is a middot', false !== strpos( implode( ';', $sel_decls( '.wpcpm-ui-item__fact::before' ) ), 'content: "\\00b7"' ), true );
ck( 'design 2.10: in wp-admin the facts line is 16px over the next block', $has( '.wpcpm-wrap .wpcpm-ui-item__facts', 'margin-bottom: var( --wpcpm-f-block )' ), true );
$wrapped = array(
	'.wpcpm-wrap .wpcpm-ui-item__title' => array( 'color: var( --wpcpm-f-ink )', 'font-size: 16px', 'font-weight: 600', 'line-height: 22px' ),
	'.wpcpm-wrap .wpcpm-ui-table td' => array( 'color: var( --wpcpm-f-ink )', 'padding: 12px', 'font-size: 13px' ),
	'.wpcpm-wrap .wpcpm-ui-table th' => array( 'color: var( --wpcpm-f-muted )', 'padding: 12px', 'font-size: 12px' ),
);
foreach ( $wrapped as $sel => $decls ) {
	foreach ( $decls as $d ) {
		ck( "wp-admin: $sel { $d }, written with the wrapper in front", $has( $sel, $d ), true );
	}
}
$wlight = array();
foreach ( $parts as $r ) {
	if ( 1 === preg_match( '/\.wpcpm-ui-(item__title|table)\b/', $r[0] ) && false !== strpos( $r[0], '.wpcpm-wrap' ) && $weight( $r[0] ) < 2 ) {
		$wlight[] = $r[0];
	}
}
ck( 'every wp-admin rule of the item title and the table has two classes of weight', $wlight, array() );
ck( 'the count, said once when there is no phrase', array( $has( '.wpcpm-ui-count', 'color: var( --wpcpm-f-muted )' ), $has( '.wpcpm-ui-count', 'font: 400 14px/20px var( --wpcpm-f-sans )' ) ), array( true, true ) );

// ---- Task 6's re-review, carried into Task 7 ----
// A card head with a title and a tag or a cue but no phrase and no count: the end group takes the free room on its
// left, so it and the chevron sit at the right; a head with no end group gives that room to the chevron or the
// leaving icon. The title may shrink and wrap.
ck( 'fold: the end group takes margin-left: auto', $has( '.wpcpm-ui-fold__end', 'margin-left: auto' ), true );
ck( 'fold: with no end group the chevron or the leaving icon takes it', array( $has( '.wpcpm-ui-fold__head > :not( .wpcpm-ui-fold__end ) + .wpcpm-ui-fold__chev', 'margin-left: auto' ), $has( '.wpcpm-ui-fold__head > :not( .wpcpm-ui-fold__end ) + .wpcpm-ui-fold__ext', 'margin-left: auto' ) ), array( true, true ) );
ck( 'fold: the title may shrink and wrap, it is no longer flex: none', array( $has( '.wpcpm-ui-fold__title', 'flex: 0 1 auto' ), $has( '.wpcpm-ui-fold__title', 'min-width: 0' ), $has( '.wpcpm-ui-fold__title', 'flex: none' ) ), array( true, true, false ) );
ck( 'fold: the chevron itself takes no auto margin when the end group is there (two auto margins would split the room)', array( $has( '.wpcpm-ui-fold__chev', 'margin-left: auto' ), $has( '.wpcpm-ui-fold__ext', 'margin-left: auto' ) ), array( false, false ) );

// ---- Task 7: the Decide area (design 2.6) ----
ck( 'design 2.6: the area is 24px under the block before it', $has( '.wpcpm-ui-decide', 'margin-top: var( --wpcpm-f-apart )' ), true );
ck( 'design 2.6: its heading is the in-card heading, 600 16px/22px, 12px over the row', array( $has( '.wpcpm-ui-h', 'font: 600 16px/22px var( --wpcpm-f-sans )' ), $has( '.wpcpm-ui-decide > .wpcpm-ui-h', 'margin: 0 0 var( --wpcpm-f-gap )' ) ), array( true, true ) );
ck( 'design 2.6: wp-admin draws the heading 14px/20px', array( $has( '.wpcpm-wrap .wpcpm-ui-h', 'font-size: 14px' ), $has( '.wpcpm-wrap .wpcpm-ui-h', 'line-height: 20px' ) ), array( true, true ) );
ck( 'design 2.6: 24px above the area after a run of inner folds too, where 2.3 would give 16px', array( $has( '.wpcpm-ui-fold--inner + .wpcpm-ui-decide', 'margin-top: var( --wpcpm-f-apart )' ), isset( $at['.wpcpm-ui-fold--inner + .wpcpm-ui-decide'], $at['.wpcpm-ui-fold--inner + :not( .wpcpm-ui-fold--inner )'] ) && $at['.wpcpm-ui-fold--inner + .wpcpm-ui-decide'] > $at['.wpcpm-ui-fold--inner + :not( .wpcpm-ui-fold--inner )'] ), array( true, true ) );
ck( 'design 2.6: the row sits right under the heading, its buttons and forms not stretched', array( $has( '.wpcpm-ui-decide > .wpcpm-ui-actions', 'margin-top: 0' ), $has( '.wpcpm-ui-decide > .wpcpm-ui-actions', 'align-items: flex-start' ) ), array( true, true ) );
ck( 'design 2.6: the panel is 16px under the row, a 1px #e6e6e6 hairline, 16px from it to the label', array( $has( '.wpcpm-ui-panel', 'margin-top: var( --wpcpm-f-block )' ), $has( '.wpcpm-ui-panel', 'border-top: 1px solid var( --wpcpm-f-line )' ), $has( '.wpcpm-ui-panel', 'padding-top: var( --wpcpm-f-block )' ) ), array( true, true, true ) );
ck( 'design 2.6: the panel\'s action row is 16px under the field', $has( '.wpcpm-ui-panel .wpcpm-ui-actions', 'margin-top: var( --wpcpm-f-block )' ), true );
ck( 'design 2.6: in wp-admin 16px from the row or an open panel\'s row to the item box\'s edge, two classes of weight', array( $has( '.wpcpm-wrap .wpcpm-ui-decide', 'padding-bottom: var( --wpcpm-f-block )' ), $weight( '.wpcpm-wrap .wpcpm-ui-decide' ) >= 2 ), array( true, true ) );
$dlight = array();
foreach ( $parts as $r ) {
	if ( 1 === preg_match( '/\.wpcpm-ui-(decide|panel)\b/', $r[0] ) && false !== strpos( $r[0], '.wpcpm-wrap' ) && $weight( $r[0] ) < 2 ) {
		$dlight[] = $r[0];
	}
}
ck( 'every wp-admin rule of the Decide area has two classes of weight', $dlight, array() );
ck( 'the Decide rules come before the 22px rule that breaks ties last', isset( $at['.wpcpm-ui-panel'] ) && $at['.wpcpm-ui-panel'] < $at[ $mod ], true );
ck( 'no Decide rule hides or folds a panel: folding is the script\'s, so with no script every panel shows', array_values( array_filter( $parts, function ( $r ) {
	return 1 === preg_match( '/wpcpm-ui-(decide|panel)|data-wpcpm-(panel|cancel|open)/', $r[0] ) && ( in_array( 'display: none', $r[1], true ) || in_array( 'visibility: hidden', $r[1], true ) );
} ) ), array() );

// ---- Task 9: the card fold's section, the help link in the head's slot, and the row that does not open ----
// The help link is a sibling of the disclosure (Review Focus 4: no link inside the summary), laid into the head's
// 104px slot by the section's grid in normal flow, never placed absolutely: the disclosure and the link share the
// grid's first cell, the link at its top right, as high as the 56px head, 16px of padding, the 20px chevron, the
// 12px gap and the 1px border in from the right. Its copy in the body is shown only on the phone.
$fc  = '.wpcpm-ui-fold-card';
$fch = $fc . ' > .wpcpm-ui-fold__help';
ck( 'design 2.3, builder\'s call 4: the card\'s section is a one-column grid, 10px between sibling cards', array( $has( $fc, 'display: grid' ), $has( $fc, 'grid-template-columns: minmax( 0, 1fr )' ), $has( $fc . ' + ' . $fc, 'margin-top: var( --wpcpm-f-fold )' ) ), array( true, true, true ) );
ck( 'the disclosure and the help link share the grid\'s first cell; the link sits at its top right, as high as the head, in the slot\'s place', array(
	$has( $fc . ' > .wpcpm-ui-fold', 'grid-column: 1' ),
	$has( $fc . ' > .wpcpm-ui-fold', 'grid-row: 1' ),
	$has( $fch, 'grid-column: 1' ),
	$has( $fch, 'grid-row: 1' ),
	$has( $fch, 'justify-self: end' ),
	$has( $fch, 'align-self: start' ),
	$has( $fch, 'min-height: 56px' ),
	$has( $fch, 'display: flex' ),
	$has( $fch, 'align-items: center' ),
	$has( $fch, 'justify-content: flex-end' ),
	$has( $fch, 'margin: 1px calc( 16px + 20px + var( --wpcpm-f-gap ) + 1px ) 0 0' ),
), array_fill( 0, 11, true ) );
ck( 'nothing of the card is placed absolutely and the help link outweighs the 22px rule after a fold', array(
	array_values( array_filter( $parts, function ( $r ) { return false !== strpos( $r[0], 'wpcpm-ui-fold-card' ) && ( in_array( 'position: absolute', $r[1], true ) || in_array( 'position: fixed', $r[1], true ) ); } ) ),
	$weight( $fch ) > $weight( $mod ),
), array( array(), true ) );
// Task V1, ruling 2: nothing is printed after the fold, so no rule names an action there (builder's call 21's is gone).
ck( 'V1 ruling 2: no rule names an action after the fold', array_values( array_filter( array_map( static function ( $r ) { return $r[0]; }, $parts ), static function ( $sel ) { return false !== strpos( $sel, 'wpcpm-ui-fold__action' ); } ) ), array() );
// Follow-up round, item 2: the help link's box is laid over the summary's slot, so it lets presses through and
// only the link takes them; the link takes the parts' quiet look (design 2.3) where it kept the theme's colour.
ck( 'the help link\'s paragraph lets presses through to the head and only its link takes them', array( $has( '.wpcpm-ui-fold__help', 'pointer-events: none' ), $has( '.wpcpm-ui-fold__help > *', 'pointer-events: auto' ) ), array( true, true ) );
ck( 'design 2.3: the help link itself is 400 14px/20px muted, underlined in #e4e4e4 3px under the words, Blueberry on hover', array(
	$has( '.wpcpm-ui-fold__help > *', 'color: var( --wpcpm-f-muted )' ),
	$has( '.wpcpm-ui-fold__help > *', 'font: 400 14px/20px var( --wpcpm-f-sans )' ),
	$has( '.wpcpm-ui-fold__help > *', 'text-decoration: underline' ),
	$has( '.wpcpm-ui-fold__help > *', 'text-decoration-color: var( --wpcpm-f-line-card )' ),
	$has( '.wpcpm-ui-fold__help > *', 'text-underline-offset: 3px' ),
	$has( '.wpcpm-ui-fold__help > :hover', 'color: var( --wpcpm-f-brand )' ),
), array( true, true, true, true, true, true ) );
ck( 'the help link\'s copy in the body is hidden at desktop widths, and the block after it takes no room above', array( $has( '.wpcpm-ui-fold__help--phone', 'display: none' ), $has( '.wpcpm-ui-fold__help--phone + *', 'margin-top: 0' ) ), array( true, true ) );
$m6 = $media( '(max-width: 600px)' );
ck( 'phone: the link in the head\'s slot is hidden, and its copy is the body\'s first line, from the left, 16px over the next block', array(
	$in( $m6, $fch, 'display: none' ),
	$in( $m6, '.wpcpm-ui-fold__help--phone', 'display: block' ),
	$in( $m6, '.wpcpm-ui-fold__help--phone', 'text-align: left' ),
	$in( $m6, '.wpcpm-ui-fold__help--phone', 'width: auto' ),
	$in( $m6, '.wpcpm-ui-fold__help--phone + *', 'margin-top: var( --wpcpm-f-block )' ),
), array( true, true, true, true, true ) );
// The rules the phone copy needs come after the rules they override: the head's one class that hides the help
// link on a phone, and the desktop rule that hides the copy.
$raw_at = static function ( $needle ) use ( $raw ) { $p = strpos( $raw, $needle ); return false === $p ? -1 : $p; };
ck( 'the phone copy\'s rule comes after the rule that hides the help link on a phone, which it ties', $raw_at( "\t.wpcpm-ui-fold__help--phone {" ) > $raw_at( "\t.wpcpm-ui-fold__help,\n\t.wpcpm-ui-fold__help-slot {" ) && $raw_at( "\t.wpcpm-ui-fold__help,\n\t.wpcpm-ui-fold__help-slot {" ) > 0, true );
// The row that does not open: the same box and head, its tag, then the Review link at the cues' width, then the
// help slot and the external-link icon in the chevron's place.
ck( 'design 2.3: the row that does not open keeps the head, places the tag, the Review link, the slot and the icon in that order, and draws the link at the cues\' width', array(
	$has( '.wpcpm-ui-fold--row > .wpcpm-ui-fold__head', 'cursor: default' ),
	$has( '.wpcpm-ui-fold--row .wpcpm-ui-fold__end', 'display: contents' ),
	$has( '.wpcpm-ui-fold--row .wpcpm-ui-fold__help-slot', 'order: 1' ),
	$has( '.wpcpm-ui-fold--row .wpcpm-ui-fold__ext', 'order: 2' ),
	$has( '.wpcpm-ui-on .wpcpm-ui-fold--row .wpcpm-btn', 'min-width: 84px' ),
), array( true, true, true, true, true ) );
// Follow-up round, item 1: on a phone the row is two lines like every head (the owner's approved picture): the
// title and the leaving icon, then the phrase and the tag; no Review button to see, the whole row one press. The
// Review link stays the row's one link, its words kept for assistive technology in a 1px box in the icon's cell,
// and its hit area stretched over the row by an empty pseudo-element: the one absolute placement of the parts.
$rl = '.wpcpm-ui-on .wpcpm-ui-fold--row .wpcpm-btn';
ck( 'phone: the row keeps the heads\' two lines: the title on line 1 with the icon at the right, the phrase and the tag on line 2', array(
	$in( $m6, '.wpcpm-ui-fold__title', 'grid-row: 1' ),
	$in( $m6, '.wpcpm-ui-fold__ext', 'grid-column: 3' ),
	$in( $m6, '.wpcpm-ui-fold__ext', 'grid-row: 1' ),
	$in( $m6, '.wpcpm-ui-fold__phrase', 'grid-row: 2' ),
	$in( $m6, '.wpcpm-ui-on .wpcpm-ui-fold--row .wpcpm-ui-fold__end > .wpcpm-status', 'grid-row: 2' ),
), array( true, true, true, true, true ) );
ck( 'phone: the Review link\'s words are out of sight but in the page, in a 1px box in line 1\'s icon cell, so no line is added', array(
	$in( $m6, $rl, 'grid-column: 3' ),
	$in( $m6, $rl, 'grid-row: 1' ),
	$in( $m6, $rl, 'width: 1px' ),
	$in( $m6, $rl, 'height: 1px' ),
	$in( $m6, $rl, 'min-width: 0' ),
	$in( $m6, $rl, 'min-height: 0' ),
	$in( $m6, $rl, 'overflow: hidden' ),
	$in( $m6, $rl, 'padding: 0' ),
	$in( $m6, $rl, 'border: 0' ),
	$in( $m6, $rl, 'position: static' ),
	array_values( array_filter( $m6, function ( $r ) use ( $rl ) { return $r[0] === $rl && ( in_array( 'display: none', $r[1], true ) || in_array( 'visibility: hidden', $r[1], true ) ); } ) ),
), array( true, true, true, true, true, true, true, true, true, true, array() ) );
ck( 'phone: the link\'s hit area is an empty pseudo-element stretched over the row, which is the positioned box; the focus ring is the row\'s', array(
	$in( $m6, '.wpcpm-ui-fold--row', 'position: relative' ),
	$in( $m6, $rl . '::after', 'content: ""' ),
	$in( $m6, $rl . '::after', 'position: absolute' ),
	$in( $m6, $rl . '::after', 'inset: 0' ),
	$in( $m6, '.wpcpm-ui-on .wpcpm-ui-fold--row:has( .wpcpm-btn:focus-visible )', 'outline: 2px solid var( --wpcpm-f-brand )' ),
	$in( $m6, '.wpcpm-ui-on .wpcpm-ui-fold--row:has( .wpcpm-btn:focus-visible )', 'outline-offset: 2px' ),
), array( true, true, true, true, true, true ) );
$fold_abs  = function ( $rules ) { return array_values( array_map( function ( $r ) { return $r[0]; }, array_filter( $rules, function ( $r ) { return false !== strpos( $r[0], 'wpcpm-ui-fold' ) && in_array( 'position: absolute', $r[1], true ); } ) ) ); };
$row_extra = function ( $rules ) { return count( array_filter( $rules, function ( $r ) { return false !== strpos( $r[0], 'wpcpm-ui-fold--row' ) && ( in_array( 'position: relative', $r[1], true ) || false !== strpos( $r[0], '::after' ) || false !== strpos( $r[0], ':has(' ) ); } ) ); };
// Task 18f: the phone query's and the 800px container query's copies (the same rules), and nowhere else.
ck( 'the folds\' only absolute placement is that hit area, and the row\'s positioning, hit area and ring are all inside the phone query and its copy for a main area under 800px, so at desktop widths the row is as before', array( $fold_abs( $parts ), $fold_abs( $m6 ), $row_extra( $parts ) === 2 * $row_extra( $m6 ), $row_extra( $m6 ) ), array( array( $rl . '::after', $rl . '::after' ), array( $rl . '::after' ), true, 3 ) );
ck( 'phone: the row\'s help link is not shown (the rule that hides every card\'s copy in the head\'s slot)', $in( $m6, $fch, 'display: none' ), true );

// UI shared parts, Task 14. A heading that is the first thing in a card's body takes no room above it, so its line
// sits where every other card's first line sits (the approved picture's larger gap above "To review" was the
// prototype's, not the design's): the body's first block is the one after the phone's copy of the help link, and the
// rule that takes its room away ties the heading's own rule in weight and comes after it. On a phone, 16px under
// that copy. No rule of its own: the Semester reports card and the Sponsor reports card both open on a heading.
$first_at = array_search( '.wpcpm-ui-fold__help--phone + *', array_map( static function ( $r ) { return $r[0]; }, $parts ), true );
ck( 'design 2.11: a heading that opens a card\'s body takes no room above it, as every card\'s first line; the rule that says so ties the heading\'s and comes after it', array(
	$has( '.wpcpm-ui-fold__help--phone + *', 'margin-top: 0' ),
	false !== $first_at && $first_at > $at['.wpcpm-ui-h'],
	$has( '.wpcpm-ui-h:first-child', 'margin-top: 0' ),
), array( true, true, true ) );
// The group of cards (the Overview's Waiting for you, Watch's This week, The program's numbers): its heading in the
// card title's size, weight, caps and tracking (design 2.3) in the second ink, as the approved "WAITING FOR YOU" is;
// its lead muted 4px under it (a modifier the group printer adds when a lead follows, no `:has()`), 12px from the
// lead (or from the heading with none) to the first row, and the sentence under the rows a muted line of the parts,
// 22px from the last row (the design's gap between modules). Set on the heading, the lead and the sentence, since
// the theme sets the cards' own margins (Task 14, fix round 1: rulings 4 and 5).
foreach ( array( 'color: var( --wpcpm-f-ink-2 )', 'font: 600 13px/20px var( --wpcpm-f-sans )', 'letter-spacing: 0.06em', 'text-transform: uppercase', 'margin: 0 0 var( --wpcpm-f-gap )' ) as $d ) {
	ck( "the group's heading: .wpcpm-ui-group__title { $d }", $has( '.wpcpm-ui-group__title', $d ), true );
}
ck( 'the group\'s heading with a lead under it: 4px to the lead, keyed on the modifier the printer adds; no :has() in the group\'s rules', array(
	$has( '.wpcpm-ui-group__title--lead', 'margin-bottom: 4px' ),
	array_values( array_filter( array_map( static function ( $r ) { return $r[0]; }, $parts ), static function ( $sel ) { return false !== strpos( $sel, 'wpcpm-ui-group' ) && false !== strpos( $sel, ':has(' ); } ) ),
), array( true, array() ) );
foreach ( array( 'color: var( --wpcpm-f-muted )', 'font: 400 14px/20px var( --wpcpm-f-sans )', 'margin: 0 0 var( --wpcpm-f-gap )' ) as $d ) {
	ck( "the group's lead: .wpcpm-ui-group__lead { $d }", $has( '.wpcpm-ui-group__lead', $d ), true );
}
foreach ( array( 'color: var( --wpcpm-f-muted )', 'font: 400 14px/20px var( --wpcpm-f-sans )', 'margin: var( --wpcpm-f-module ) 0 0' ) as $d ) {
	ck( "the sentence under the group's rows: .wpcpm-ui-group__note { $d }", $has( '.wpcpm-ui-group__note', $d ), true );
}
// Every card's foot, "Open on the ... screen" (ruling 1): after a 1px #e6e6e6 hairline, 24px above the hairline, 16px
// from it to the link, nothing under it beyond the body's own padding; the link takes the card's link look (the
// parts draw no look for a link in a card body, so it is the page's, as the institution links in the tables are),
// in the parts' 14px/20px. The rule comes after the one that takes room from the body's first block, so a foot keeps
// its 24px wherever it falls.
foreach ( array( 'border-top: 1px solid var( --wpcpm-f-line )', 'margin: var( --wpcpm-f-apart ) 0 0', 'padding: var( --wpcpm-f-block ) 0 0', 'font: 400 14px/20px var( --wpcpm-f-sans )' ) as $d ) {
	ck( "every card's foot: .wpcpm-ui-fold__foot { $d }", $has( '.wpcpm-ui-fold__foot', $d ), true );
}
$foot_at = array_search( '.wpcpm-ui-fold__foot', array_map( static function ( $r ) { return $r[0]; }, $parts ), true );
ck( 'the foot\'s rule comes after the rule that takes the room above the body\'s first block', false !== $foot_at && false !== $first_at && $foot_at > $first_at, true );
// "N more are waiting" (ruling 2): the intro line, muted, 16px under the list it follows, as the body's blocks are.
foreach ( array( 'color: var( --wpcpm-f-muted )', 'margin: var( --wpcpm-f-block ) 0 0' ) as $d ) {
	ck( "the muted note: .wpcpm-ui-intro--muted { $d }", $has( '.wpcpm-ui-intro--muted', $d ), true );
}

// UI shared parts, Task 13d fix round 1: the choice group and a checkbox alone (design 2.4), and the logos part.
foreach ( array(
	'.wpcpm-ui-choices'                             => array( 'border: 0', 'margin: 0', 'min-width: 0', 'padding: 0' ),
	'.wpcpm-ui-field + .wpcpm-ui-choices'           => array( 'margin-top: var( --wpcpm-f-field )' ),
	'.wpcpm-ui-choices + .wpcpm-ui-field'           => array( 'margin-top: var( --wpcpm-f-field )' ),
	'.wpcpm-ui-choices + .wpcpm-ui-choices'         => array( 'margin-top: var( --wpcpm-f-field )' ),
	'.wpcpm-ui-choices__legend'                     => array( 'color: var( --wpcpm-f-ink )', 'font: 600 14px/20px var( --wpcpm-f-sans )', 'margin: 0 0 var( --wpcpm-f-tie )', 'padding: 0' ),
	'.wpcpm-ui-choice'                              => array( 'align-items: flex-start', 'box-sizing: border-box', 'display: flex', 'font: 400 14px/20px var( --wpcpm-f-sans )', 'gap: 8px', 'margin: 0', 'min-height: 24px', 'padding: 2px 0' ),
	'.wpcpm-ui-choice + .wpcpm-ui-choice'           => array( 'margin-top: 8px' ),
	'.wpcpm-ui-choice .wpcpm-ui-choice__input'      => array( 'flex: none', 'height: 16px', 'margin: 2px 0 0', 'width: 16px' ),
	'.wpcpm-ui-choice__text'                        => array( 'display: block', 'min-width: 0' ),
	'.wpcpm-ui-choice__hint'                        => array( 'color: var( --wpcpm-f-muted )', 'display: block' ),
	'.wpcpm-ui-logos'                               => array( 'display: flex', 'flex-wrap: wrap', 'gap: var( --wpcpm-f-block )', 'margin: 8px 0 0' ),
	'.wpcpm-ui-logo'                                => array( 'margin: 0', 'max-width: 160px', 'text-align: center' ),
	'.wpcpm-ui-logo__img'                           => array( 'background: var( --wpcpm-f-surface )', 'border: 1px solid var( --wpcpm-f-line )', 'border-radius: 2px', 'box-sizing: border-box', 'display: block', 'height: auto', 'margin: 0 auto', 'max-width: 100%', 'padding: 8px' ),
	'.wpcpm-ui-logo--white .wpcpm-ui-logo__img'     => array( 'background: var( --wpcpm-f-ink )', 'border-color: var( --wpcpm-f-ink )' ),
	'.wpcpm-ui-logo__caption'                       => array( 'color: var( --wpcpm-f-muted )', 'font: 400 14px/20px var( --wpcpm-f-sans )', 'margin: 4px 0 0' ),
) as $sel => $decls ) {
	foreach ( $decls as $d ) {
		ck( "Task 13d: $sel { $d }", $has( $sel, $d ), true );
	}
}
ck( 'Task 13d: the choice and logo rules name only wpcpm-ui- classes: no element, no :has()', array_values( array_filter( array_map( static function ( $r ) { return $r[0]; }, $parts ), static function ( $sel ) {
	return ( false !== strpos( $sel, 'wpcpm-ui-choice' ) || false !== strpos( $sel, 'wpcpm-ui-logo' ) ) && ( false !== strpos( $sel, ':has(' ) || 1 === preg_match( '/(^|[\s>+~])(input|label|img|figure|figcaption|fieldset|legend)\b/', $sel ) );
} ) ), array() );

// UI shared parts, Task 16 fix round 1: a cell's second line (design 2.7) and a list row's media box (design 2.8).
foreach ( array(
	'.wpcpm-ui-cell__sub'                                       => array( 'color: var( --wpcpm-f-muted )', 'display: block', 'font: 400 14px/20px var( --wpcpm-f-sans )', 'margin-top: 2px', 'white-space: normal' ),
	'.wpcpm-ui-list__row--media'                                => array( 'align-items: start', 'column-gap: var( --wpcpm-f-gap )', 'display: grid', 'grid-template-columns: 40px minmax( 0, 1fr )' ),
	'.wpcpm-ui-list__row--media > .wpcpm-ui-list__line'         => array( 'grid-column: 2' ),
	'.wpcpm-ui-list__row--media > .wpcpm-ui-list__note'         => array( 'grid-column: 2' ),
	'.wpcpm-ui-list__row--media > .wpcpm-ui-list__form'         => array( 'grid-column: 1 / -1' ),
	'.wpcpm-ui-list__media'                                     => array( 'align-items: center', 'background: var( --wpcpm-f-surface )', 'border: 1px solid var( --wpcpm-f-line )', 'border-radius: 2px', 'box-sizing: border-box', 'display: flex', 'grid-column: 1', 'grid-row: 1', 'height: 40px', 'justify-content: center', 'margin-top: 8px', 'overflow: hidden', 'width: 40px' ),
	'.wpcpm-ui-list__media-img'                                 => array( 'display: block', 'height: auto', 'max-height: 100%', 'max-width: 100%', 'object-fit: contain', 'width: auto' ),
	'.wpcpm-ui-list__media-initials'                            => array( 'color: var( --wpcpm-f-ink-2 )', 'font: 600 14px/20px var( --wpcpm-f-sans )' ),
	'.wpcpm-ui-list__name'                                      => array( 'margin: 0', 'font: 600 14px/20px var( --wpcpm-f-sans )', 'color: var( --wpcpm-f-ink )' ),
) as $sel => $decls ) {
	foreach ( $decls as $d ) {
		ck( "Task 16: $sel { $d }", $has( $sel, $d ), true );
	}
}
ck( 'Task 16: the second line and the media rules name only wpcpm-ui- classes: no element, no :has(), no !important; no phone rule moves the media box', array(
	array_values( array_filter( $parts, static function ( $r ) {
		return ( false !== strpos( $r[0], 'wpcpm-ui-cell__sub' ) || false !== strpos( $r[0], 'wpcpm-ui-list__media' ) || false !== strpos( $r[0], 'wpcpm-ui-list__row--media' ) )
			&& ( false !== strpos( $r[0], ':has(' ) || 1 === preg_match( '/(^|[\s>+~])(img|span|td|th|li|div)\b/', $r[0] ) || false !== strpos( implode( ';', $r[1] ), '!important' ) );
	} ) ),
	array_values( array_filter( array_merge( $media( '(max-width: 600px)' ), $media( '(max-width: 782px)' ) ), static function ( $r ) { return false !== strpos( $r[0], 'media' ) || false !== strpos( $r[0], 'cell__sub' ); } ) ),
), array( array(), array() ) );

// Task 17b, ruling 1: a note a person typed (`WPCPM_UI::note()`) shares one declaration block with a row's note: the
// second ink, 400 14px/20px, line breaks kept, long words broken, 640px at most; each keeps its own room.
$t17b_block = static function ( $want ) use ( $raw ) {
	$css = preg_replace( '#/\*.*?\*/#s', '', $raw );
	preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER );
	foreach ( $m as $r ) {
		if ( trim( preg_replace( '/\s+/', ' ', $r[1] ) ) === $want ) {
			return array_values( array_filter( array_map( static function ( $d ) { return trim( preg_replace( '/\s+/', ' ', $d ) ); }, explode( ';', $r[2] ) ) ) );
		}
	}
	return null;
};
ck( 'ruling 1: the note and a row\'s note share one block: the second ink, 400 14px/20px, to the container\'s edge (no cap, Task 18c), long words broken, line breaks kept', $t17b_block( '.wpcpm-ui-note, .wpcpm-ui-list__note' ), array(
	'color: var( --wpcpm-f-ink-2 )',
	'font: 400 14px/20px var( --wpcpm-f-sans )',
	'overflow-wrap: anywhere',
	'white-space: pre-wrap',
) );
ck( 'ruling 1: the note takes only the body\'s 16px above it; a row\'s note keeps no margin and its 6px above, 8px under', array(
	$sel_decls( '.wpcpm-ui-note' ),
	$t17b_block( '.wpcpm-ui-list__note' ),
), array(
	array( 'color: var( --wpcpm-f-ink-2 )', 'font: 400 14px/20px var( --wpcpm-f-sans )', 'overflow-wrap: anywhere', 'white-space: pre-wrap', 'margin: var( --wpcpm-f-block ) 0 0' ),
	array( 'margin: 0', 'padding: 6px 0 8px' ),
) );
// Task 17b, ruling 7: one item of a card from the next, on the parts: the sheet's soft hairline with 24px above and
// below it, the first item none; nothing else of the item's.
ck( 'ruling 7: an item after an item: the soft hairline, 24px above and 24px below it; the first item draws none; no other rule names the item but an inner fold\'s after it (Task 18f)', array(
	$sel_decls( '.wpcpm-ui-item + .wpcpm-ui-item' ),
	array_values( array_filter( array_map( static function ( $r ) { return $r[0]; }, $parts ), static function ( $sel ) { return 1 === preg_match( '/\.wpcpm-ui-item(?![\w-])/', $sel ); } ) ),
), array(
	array( 'border-top: 1px solid var( --wpcpm-f-line-soft )', 'margin-top: var( --wpcpm-f-apart )', 'padding-top: var( --wpcpm-f-apart )' ),
	// Gate B, B2: an item's first block starts at the item's own top, after a hidden field it carries first.
	array( '.wpcpm-ui-item + .wpcpm-ui-fold--inner', '.wpcpm-ui-item + .wpcpm-ui-item', '.wpcpm-ui-item > :first-child', '.wpcpm-ui-item > input[type="hidden"] + :not( input )' ),
) );

// Task 17b fix round, ruling 5, as Task 18a (point 1) made it: the agreement review's checklist is a plain list, not a
// grid: the field rhythm around it (14px), its words 20px in with the browser's own disc markers, and the tie between
// its lines (6px) keyed on the line's own class; no element selector, nothing else.
ck( 'ruling 5: the checklist: 14px above and under it, its words 20px in, no grid; 6px between two lines by the line\'s class; two rules name it, neither an element', array(
	$sel_decls( '.wpcpm-ui-checklist' ),
	$sel_decls( '.wpcpm-ui-checklist__item + .wpcpm-ui-checklist__item' ),
	array_values( array_filter( array_map( static function ( $r ) { return $r[0]; }, $parts ), static function ( $sel ) { return false !== strpos( $sel, 'wpcpm-ui-checklist' ); } ) ),
), array(
	array( 'margin: var( --wpcpm-f-field ) 0', 'padding-left: 20px' ),
	array( 'margin-top: var( --wpcpm-f-tie )' ),
	array( '.wpcpm-ui-checklist', '.wpcpm-ui-checklist__item + .wpcpm-ui-checklist__item' ),
) );

// ---- Task V1: the faults the first pictures of the Administrator Dashboard showed ----
// Ruling 1: on a phone a card head's end group gives up its box, so its status tag would stretch across its grid
// column; every head's tag keeps its own width at the start of that column, not only the row's.
ck( 'V1 ruling 1: phone: the tag in any card head\'s end group keeps its width, at the start of its column', array(
	$in( $m6, '.wpcpm-ui-on .wpcpm-ui-fold__end > .wpcpm-status', 'justify-self: start' ),
	array_values( array_unique( $sel_decls( '.wpcpm-ui-on .wpcpm-ui-fold__end > .wpcpm-status' ) ) ),
), array( true, array( 'justify-self: start' ) ) );
// Ruling 3: the help link carries the part's class; the part's rule outweighs the old sheet's one-class
// `.wpcpm-job-link` (and its hover) by weight, so the sheet order no longer decides. In the head: 400 14px/20px,
// muted, a 1px #e4e4e4 underline 3px under the words, Blueberry on hover, the focus ring. Inside the open card on a
// phone (the body's first line): Blueberry, the underline its own colour, as the approved 390 pictures draw it.
$hl = '.wpcpm-ui-fold__help .wpcpm-ui-fold__help-link';
ck( 'V1 ruling 3: the help link in the head: muted 400 14px/20px, underlined 1px in #e4e4e4 3px under the words, on one line; Blueberry on hover; the focus ring', array(
	$sel_decls( $hl ),
	$sel_decls( $hl . ':hover' ),
	$sel_decls( $hl . ':focus-visible' ),
), array(
	array( 'color: var( --wpcpm-f-muted )', 'font: 400 14px/20px var( --wpcpm-f-sans )', 'text-decoration: underline', 'text-decoration-color: var( --wpcpm-f-line-card )', 'text-decoration-thickness: 1px', 'text-underline-offset: 3px', 'white-space: nowrap' ),
	array( 'color: var( --wpcpm-f-brand )' ),
	array( 'outline: 2px solid var( --wpcpm-f-brand )', 'outline-offset: 2px' ),
) );
ck( 'V1 ruling 3: the part\'s rules outweigh the old sheet\'s .wpcpm-job-link and its hover, whichever sheet loads last', array(
	$weight( $hl ) > $weight( '.wpcpm-job-link' ),
	$weight( $hl . ':hover' ) > $weight( '.wpcpm-job-link:hover' ),
	$weight( $hl . ':focus-visible' ) > $weight( '.wpcpm-job-link:focus-visible' ),
), array( true, true, true ) );
$hlp = '.wpcpm-ui-fold__help--phone .wpcpm-ui-fold__help-link';
ck( 'V1 ruling 3: the phone copy inside the open card is Blueberry, underlined in its own colour, Deep Blueberry on hover; it ties the head\'s rule and comes after it', array(
	$sel_decls( $hlp ),
	$sel_decls( $hlp . ':hover' ),
	$weight( $hlp ) === $weight( $hl ) && $at[ $hlp ] > $at[ $hl ],
), array( array( 'color: var( --wpcpm-f-brand )', 'text-decoration-color: currentColor' ), array( 'color: var( --wpcpm-f-primary-hover )' ), true ) );
// Ruling 7: an in-card heading sits 8px over what it names: the heading's own 8px under it, and the block after a
// heading takes none of its own above (where a card's body sets 16px between blocks).
ck( 'V1 ruling 7: the heading keeps 8px under it, and the block after a heading takes no room above, outweighing the body\'s 16px between blocks', array(
	$has( '.wpcpm-ui-h', 'margin: var( --wpcpm-f-apart ) 0 8px' ),
	$sel_decls( '.wpcpm-ui-h + :where( :not( .wpcpm-ui-h ) )' ),
	$weight( '.wpcpm-ui-h + :where( :not( .wpcpm-ui-h ) )' ) > $weight( ':where( .wpcpm-ui-fold__body > * + * )' ),
), array( true, array( 'margin-top: 0' ), true ) );
// Task 18c: the rule names what may follow a heading by leaving out one thing, a heading, which keeps its own 24px.
// Every block the visual task photographed at 8px under a heading is a part's block that is not a heading, so the rule
// reaches each; its weight is the one class it had, so nothing it reached outweighs it differently.
$h_after = array( 'wpcpm-ui-table', 'wpcpm-ui-list', 'wpcpm-ui-intro wpcpm-ui-intro--muted', 'wpcpm-ui-intro', 'wpcpm-ui-note', 'wpcpm-ui-checklist', 'wpcpm-ui-item', 'wpcpm-ui-fold wpcpm-ui-fold--inner', 'wpcpm-sponsor-application__logos' );
ck( 'Task 18c: a block after a heading takes no room above, a heading after a heading keeps its 24px; the rule weighs one class, as before; no old broad rule is left', array(
	array_map( static function ( $classes ) { return 1 !== preg_match( '/(^| )wpcpm-ui-h( |$)/', $classes ); }, $h_after ),
	1 !== preg_match( '/(^| )wpcpm-ui-h( |$)/', 'wpcpm-ui-h' ),
	$weight( '.wpcpm-ui-h + :where( :not( .wpcpm-ui-h ) )' ),
	$sel_decls( '.wpcpm-ui-h + *' ),
), array( array_fill( 0, count( $h_after ), true ), false, 1, array() ) );
ck( 'Task 18c (from the visual task\'s review): an action row right over a Decide area sits 16px over it', $sel_decls( '.wpcpm-ui-actions + .wpcpm-ui-decide' ), array( 'margin-top: var( --wpcpm-f-block )' ) );
ck( 'Task 18c: the one monospace stack is a token, used by the reference and by a code in a cell', array(
	$has( ':where( .wpcpm-frame, .wpcpm-dashboard, .wpcpm-wrap )', '--wpcpm-f-mono: "IBM Plex Mono", ui-monospace, SFMono-Regular, Menlo, Consolas, monospace' ),
	$has( '.wpcpm-ui-item__ref', 'font: 400 13px/20px var( --wpcpm-f-mono )' ),
	substr_count( (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/parts.css' ), '"IBM Plex Mono"' ),
), array( true, true, 1 ) );
// Ruling 9: the browser's date and time controls grow past a minimum; the box is set to 40px.
ck( 'V1 ruling 9: a date or a time box is 40px high, set, not only its minimum', $sel_decls( $ctl . '--date' ), array( 'max-width: 100%', 'width: 200px', 'height: 40px' ) );
// Ruling 10: a text area is a block, so its hint is 6px under the box, not on its baseline.
ck( 'V1 ruling 10: a text area is a block', $has( $ctl . '--textarea', 'display: block' ), true );
// Ruling 11: on a phone the facts wrap under the tag from the item's left edge, 4px between lines; no middot
// begins a line, and none follows the tag.
ck( 'V1 ruling 11: phone: the facts line wraps, shifted by the 16px gutter and clipped 12px in; the tag back on the edge; the run gives up its box; the first fact draws no dot', array(
	$sel_decls( '.wpcpm-ui-item__facts' ) !== array() && array() !== array_values( array_filter( $m6, static function ( $r ) { return '.wpcpm-ui-item__facts' === $r[0]; } ) ) ? array_values( array_filter( $m6, static function ( $r ) { return '.wpcpm-ui-item__facts' === $r[0]; } ) )[0][1] : null,
	$in( $m6, '.wpcpm-ui-item__facts > :not( .wpcpm-ui-item__run )', 'margin-left: 16px' ),
	$in( $m6, '.wpcpm-ui-item__facts > :not( .wpcpm-ui-item__run )', 'margin-right: -8px' ),
	$in( $m6, '.wpcpm-ui-item__run', 'display: contents' ),
	$in( $m6, '.wpcpm-ui-item__fact:first-child::before', 'visibility: hidden' ),
), array( array( 'clip-path: inset( -4px -4px -4px 12px )', 'flex-wrap: wrap', 'margin-left: -16px', 'row-gap: 4px' ), true, true, true, true ) );
// Ruling 13: one hairline before a card's foot. After a table, a list or an inner fold, whose own last hairline
// stays, the foot draws none and takes no room above it (16px from that hairline to the link, its padding); after
// any other block it keeps its hairline with 24px above. A sibling rule on the parts' classes, no :has().
$foot_after = array( '.wpcpm-ui-table + .wpcpm-ui-fold__foot', '.wpcpm-ui-list + .wpcpm-ui-fold__foot', '.wpcpm-ui-fold--inner + .wpcpm-ui-fold__foot' );
ck( 'V1 ruling 13: after a table, a list or an inner fold the foot draws no hairline and no room above; it outweighs the foot\'s own rule and the 16px after a run of inner folds; no :has()', array(
	array_map( $sel_decls, $foot_after ),
	array_map( static function ( $sel ) use ( $weight, $at ) { return $weight( $sel ) > $weight( '.wpcpm-ui-fold__foot' ) && $at[ $sel ] > $at['.wpcpm-ui-fold--inner + :not( .wpcpm-ui-fold--inner )']; }, $foot_after ),
	$has( '.wpcpm-ui-fold__foot', 'border-top: 1px solid var( --wpcpm-f-line )' ),
	$has( '.wpcpm-ui-fold__foot', 'padding: var( --wpcpm-f-block ) 0 0' ),
	array_values( array_filter( array_map( static function ( $r ) { return $r[0]; }, $parts ), static function ( $sel ) { return false !== strpos( $sel, 'fold__foot' ) && false !== strpos( $sel, ':has(' ); } ) ),
), array( array_fill( 0, 3, array( 'border-top: 0', 'margin-top: 0' ) ), array( true, true, true ), true, true, array() ) );

ck( 'V1 ruling 13: a list that opens a card\'s body (after the phone\'s help copy or not) draws no hairline over it: the body\'s top edge is the one', array(
	$sel_decls( '.wpcpm-ui-fold__body > .wpcpm-ui-list:first-child' ),
	$sel_decls( '.wpcpm-ui-fold__body > .wpcpm-ui-fold__help--phone:first-child + .wpcpm-ui-list' ),
	$weight( '.wpcpm-ui-fold__body > .wpcpm-ui-list:first-child' ) > $weight( '.wpcpm-ui-list' ),
), array( array( 'border-top: 0' ), array( 'border-top: 0' ), true ) );

ck( 'V1 ruling 6: a list that ends a fold\'s body draws no hairline under its last row, outweighing the row\'s own', array(
	$sel_decls( '.wpcpm-ui-fold__body > .wpcpm-ui-list:last-child > .wpcpm-ui-list__row:last-child' ),
	$weight( '.wpcpm-ui-fold__body > .wpcpm-ui-list:last-child > .wpcpm-ui-list__row:last-child' ) > $weight( '.wpcpm-ui-list__row' ),
), array( array( 'border-bottom: 0' ), true ) );

// Ruling 15: a count with a second line (Programs running's In progress) reads from the left, its number in tabular
// figures; it is not a number cell (right-aligned, never wrapping).
ck( 'V1 ruling 15: the count cell: tabular figures, read from the left', $sel_decls( '.wpcpm-ui-table .wpcpm-ui-cell--count' ), array( 'font-variant-numeric: tabular-nums', 'text-align: left' ) );

// Ruling 14: a table of a family lays out its columns by their widths, so the family's columns start at the same places.
ck( 'V1 ruling 14: a family\'s table is laid out by its columns\' widths', $sel_decls( '.wpcpm-ui-table--fixed' ), array( 'table-layout: fixed' ) );

// Ruling 8: a code in a cell is whole on a line of its own, in the reference's face, and wraps inside its cell; it
// outweighs the table's own rule that keeps a code on one line. Rulings 8 and 12: a table or an in-card heading under
// an item's facts line sits 16px under it.
ck( 'V1 ruling 8: a code in a cell: a block line, IBM Plex Mono 13px/20px, 4px apart, wrapping anywhere inside its cell; it outweighs .wpcpm-ui-table code', array(
	$sel_decls( '.wpcpm-ui-table .wpcpm-ui-code' ),
	$weight( '.wpcpm-ui-table .wpcpm-ui-code' ) > $weight( '.wpcpm-ui-table code' ) - 1 && $at['.wpcpm-ui-table .wpcpm-ui-code'] > $at['.wpcpm-ui-table code'],
), array( array( 'display: block', 'font: 400 13px/20px var( --wpcpm-f-mono )', 'margin-top: 4px', 'overflow-wrap: anywhere', 'white-space: normal' ), true ) );
ck( 'V1 rulings 8 and 12: a table or an in-card heading right under an item\'s facts line sits 16px under it, outweighing the heading\'s own 24px', array(
	$sel_decls( '.wpcpm-ui-item__facts + .wpcpm-ui-table' ),
	$sel_decls( '.wpcpm-ui-item__facts + .wpcpm-ui-h' ),
	$weight( '.wpcpm-ui-item__facts + .wpcpm-ui-h' ) > $weight( '.wpcpm-ui-h' ),
	$sel_decls( '.wpcpm-ui-item__facts + .wpcpm-ui-intro' ),
	$sel_decls( '.wpcpm-ui-item__facts + .wpcpm-ui-item__part' ),
), array( array( 'margin-top: var( --wpcpm-f-block )' ), array( 'margin-top: var( --wpcpm-f-block )' ), true, array( 'margin-top: var( --wpcpm-f-block )' ), array( 'margin-top: var( --wpcpm-f-block )' ) ) );
ck( 'V1 ruling 6: a row with no action cell gives the action\'s column to its facts; on a phone they span the row anyway', array(
	$has( '.wpcpm-ui-list__facts:last-child', 'grid-column: 3 / -1' ),
	$in( $m6, '.wpcpm-ui-list__facts', 'grid-column: 1 / -1' ),
	$in( $m6, '.wpcpm-ui-list__facts:last-child', 'grid-column: 1 / -1' ),
), array( true, true, true ) );

ck( 'V1 ruling 7: a muted line right under an in-card heading takes no room above, outweighing its own 16px', array( $sel_decls( '.wpcpm-ui-h + .wpcpm-ui-intro--muted' ), $weight( '.wpcpm-ui-h + .wpcpm-ui-intro--muted' ) > $weight( '.wpcpm-ui-intro--muted' ) ), array( array( 'margin-top: 0' ), true ) );

// Task 18c, point 9: a list whose rows carry no tag (list_open()'s facts 'under', design 2.8's people rows) puts each
// row's facts under its name, the action at the row's end across both lines; wider than a phone only, where the phone
// layout is already this. A list without the modifier keeps the four columns and the facts' column rule.
$m601 = $media( '(min-width: 601px)' );
ck( 'Task 18c: a list with no tag: the name on line 1, the facts on line 2 4px under it, the action across both lines and centered; above the phone width only; the list\'s classes only, no :has()', array(
	// Task 18d: three columns, the name's, a tag's (empty in a list with no tag, the name's room then) and the action's.
	// Task 18e: four columns, the name's, the tag's (its own width), the room left and the action's; no column gap, the
	// tag and the action keep their 16px as their own margins, so an empty column costs nothing.
	$in( $m601, '.wpcpm-ui-list--under .wpcpm-ui-list__line', 'grid-template-columns: minmax( 0, max-content ) auto minmax( 0, 1fr ) auto' ) && $in( $m601, '.wpcpm-ui-list--under .wpcpm-ui-list__line', 'column-gap: 0' ),
	$in( $m601, '.wpcpm-ui-list--under .wpcpm-ui-list__line', 'row-gap: 4px' ),
	$in( $m601, '.wpcpm-ui-list--under .wpcpm-ui-list__name', 'grid-row: 1' ),
	$in( $m601, '.wpcpm-ui-list--under .wpcpm-ui-list__facts', 'grid-row: 2' ),
	// Task 18f, point 6: one rule with or without a tag: the facts at the row's full width.
	$in( $m601, '.wpcpm-ui-list--under .wpcpm-ui-list__facts:last-child', 'grid-column: 1 / -1' ),
	$in( $m601, '.wpcpm-ui-list--under .wpcpm-ui-list__facts:empty', 'display: none' ),
	$in( $m601, '.wpcpm-ui-list--under .wpcpm-ui-list__act', 'grid-row: 1' ) && $in( $m601, '.wpcpm-ui-list--under .wpcpm-ui-list__act', 'grid-column: 4' ) && $in( $m601, '.wpcpm-ui-list--under .wpcpm-ui-list__act', 'margin-left: 16px' ),
	// Task 18f, point 6: the action on the name's line, on its baseline, with or without a tag.
	$in( $m601, '.wpcpm-ui-list--under .wpcpm-ui-list__act', 'align-self: baseline' ),
	$weight( '.wpcpm-ui-list--under .wpcpm-ui-list__facts:last-child' ) > $weight( '.wpcpm-ui-list__facts:last-child' ),
	// Every rule of the list is in the wider query, but the phone's note-before-action rules (Task 18d).
	// Fix round 2: and the phone's rules' narrow copy.
	count( array_filter( $parts, static function ( $r ) { return false !== strpos( $r[0], 'wpcpm-ui-list--under' ); } ) ) === count( $m601 ) + 2 * count( array_filter( $media( '(max-width: 600px)' ), static function ( $r ) { return false !== strpos( $r[0], 'wpcpm-ui-list--under' ); } ) ),
	array_values( array_filter( array_map( static function ( $r ) { return $r[0]; }, $parts ), static function ( $sel ) { return false !== strpos( $sel, 'list--under' ) && ( false !== strpos( $sel, ':has(' ) || 1 === preg_match( '/(^|[\s>+~])[a-z]/', $sel ) ); } ) ),
	$has( '.wpcpm-ui-list__line', 'grid-template-columns: minmax( 0, 1fr ) 160px minmax( 0, 1fr ) 200px' ),
	$has( '.wpcpm-ui-list__facts:last-child', 'grid-column: 3 / -1' ),
), array( true, true, true, true, true, true, true, true, true, true, array(), true, true ) );
// Task 18c, point 10: the fold's chevron is the approved prototype's glyph in the design's #40464d (design 2.3 gives
// no weight): its colour stays the second ink, its box 20px.
ck( 'Task 18c: the fold\'s chevron keeps the design\'s #40464d and 20px box', array( $has( '.wpcpm-ui-fold__chev', 'color: var( --wpcpm-f-ink-2 )' ), $has( '.wpcpm-ui-fold__chev', 'width: 20px' ), $has( '.wpcpm-ui-fold__chev', 'height: 20px' ) ), array( true, true, true ) );

// Task 18d, point 1: the chevron's and the leaving icon's svg carry the icon set's class, which the theme paints gray
// (`.wpc-dashboard-page .wpcpm-icon`, two classes, color #979aa1) and the plugin's dashboard.css dims (`.wpcpm-icon`,
// opacity 0.6). The glyph takes its span's ink at full strength by a rule of three classes, which beats both whichever
// sheet loads last; it names no legacy class and no element (the universal child).
foreach ( array( 'chev', 'ext' ) as $t18d_icon ) {
	$t18d_sel = '.wpcpm-ui-on .wpcpm-ui-fold__head .wpcpm-ui-fold__' . $t18d_icon . ' > *';
	ck( "Task 18d: the fold's $t18d_icon glyph takes its span's colour at opacity 1, outweighing the theme's and the plugin's .wpcpm-icon", array(
		$sel_decls( $t18d_sel ),
		$weight( $t18d_sel ) > $weight( '.wpc-dashboard-page .wpcpm-icon' ),
		$weight( $t18d_sel ) > $weight( '.wpcpm-icon' ),
	), array( array( 'color: inherit', 'opacity: 1' ), true, true ) );
}

// Task 18d, point 5: a key and value table that ends a fold draws no hairline of its own under its last row, under its
// label as under its value (design 2.7: "none of its own when it is the last thing in a fold"); the label is a plain row
// header, which the key and value rule gives a hairline. Scoped to the key and value table, which has no head row, so no
// table's column headers lose theirs.
$t18d_kv = '.wpcpm-ui-fold__body > .wpcpm-ui-table--keyvalue:last-child tr:last-child th';
ck( 'Task 18d: the last label of a key and value table that ends a fold draws no hairline, outweighing the label\'s own', array(
	$sel_decls( $t18d_kv ),
	$weight( $t18d_kv ) > $weight( '.wpcpm-ui-table--keyvalue th' ),
	$sel_decls( '.wpcpm-ui-fold__body > .wpcpm-ui-table:last-child tr:last-child td' ),
), array( array( 'border-bottom: 0' ), true, array( 'border-bottom: 0' ) ) );

// Task 18d, point 7c: an inner fold's body starts 12px under its head and ends 16px over its edge, and a list inside it
// adds no room of its own there: the row that opens the body draws no padding over its line and the row that ends it
// none under, neither with the row's 56px least height, which would centre a one-line name in room of its own. A row
// between keeps both, so names a hairline apart stay evenly spaced.
$t18d_first = '.wpcpm-ui-fold--inner > .wpcpm-ui-fold__body > .wpcpm-ui-list:first-child > .wpcpm-ui-list__row:first-child > .wpcpm-ui-list__line';
$t18d_last  = '.wpcpm-ui-fold--inner > .wpcpm-ui-fold__body > .wpcpm-ui-list:last-child > .wpcpm-ui-list__row:last-child > .wpcpm-ui-list__line';
ck( 'Task 18d: a list that opens or ends an inner fold\'s body takes no room at that end, outweighing the row\'s own and a noted row\'s', array(
	$sel_decls( $t18d_first ),
	$sel_decls( $t18d_last ),
	$weight( $t18d_first ) > $weight( '.wpcpm-ui-list__row--note > .wpcpm-ui-list__line' ),
	$weight( $t18d_last ) > $weight( '.wpcpm-ui-list__row--note > .wpcpm-ui-list__line' ),
	$sel_decls( '.wpcpm-ui-fold--inner > .wpcpm-ui-fold__body' ),
), array( array( 'min-height: 0', 'padding-top: 0' ), array( 'min-height: 0', 'padding-bottom: 0' ), true, true, array( 'border-top: 0', 'padding: 12px 0 16px' ) ) );

// Task 18d, point 8: the facts-under layout extended to a row with a tag (the sponsor agreements out of force): line 1
// the name, its tag right after it and the action at the end; line 2 the facts at the row's full width, under the
// action too. The tag is the frame's shared class, so its rules start with the root class; no :has(), no element.
$m6       = $media( '(max-width: 600px)' );
$t18d_tag = '.wpcpm-ui-on .wpcpm-ui-list--under .wpcpm-ui-list__line > .wpcpm-status';
ck( 'Task 18d: a row with a tag in a list with its facts under the name: name, tag and action on line 1, the facts at the full width on line 2', array(
	$in( $m601, $t18d_tag, 'grid-column: 2' ) && $in( $m601, $t18d_tag, 'grid-row: 1' ) && $in( $m601, $t18d_tag, 'justify-self: start' ),
	// Task 18f, point 6: the facts and the action follow one rule with or without a tag (the list's own rules), so no
	// rule names the action or the facts after the tag.
	$in( $m601, '.wpcpm-ui-list--under .wpcpm-ui-list__facts', 'grid-column: 1 / -1' ),
	$in( $m601, '.wpcpm-ui-list--under .wpcpm-ui-list__act', 'grid-row: 1' ),
	count( array_filter( $parts, static function ( $r ) { return false !== strpos( $r[0], '.wpcpm-status +' ) || false !== strpos( $r[0], '.wpcpm-status ~' ); } ) ),
	$in( $m601, '.wpcpm-ui-list--under .wpcpm-ui-list__name', 'grid-column: 1' ),
), array( true, true, true, 0, true ) );
// On a phone a row of such a list reads name and tag, facts, the note, then the action last: the row is the grid, its
// line giving up its box, so the note (printed after the line) comes before the action, which the grid places last.
$t18d_row = '.wpcpm-ui-list--under .wpcpm-ui-list__row--note';
ck( 'Task 18d: on a phone a row of a list with its facts under the name puts its note before its action, the action last', array(
	$in( $m6, $t18d_row, 'display: grid' ) && $in( $m6, $t18d_row, 'grid-template-columns: minmax( 0, 1fr ) auto' ) && $in( $m6, $t18d_row, 'gap: 8px 16px' ) && $in( $m6, $t18d_row, 'padding: 8px 0' ),
	$in( $m6, $t18d_row . ' > .wpcpm-ui-list__line', 'display: contents' ),
	$in( $m6, $t18d_row . ' > .wpcpm-ui-list__note', 'grid-column: 1 / -1' ) && $in( $m6, $t18d_row . ' > .wpcpm-ui-list__note', 'padding: 0' ),
	$in( $m6, $t18d_row . ' .wpcpm-ui-list__act', 'order: 1' ),
	$in( $m6, $t18d_row . ' > .wpcpm-ui-list__form', 'order: 2' ) && $in( $m6, $t18d_row . ' > .wpcpm-ui-list__form', 'grid-column: 1 / -1' ),
	$weight( $t18d_row . ' > .wpcpm-ui-list__note' ) > $weight( '.wpcpm-ui-list__note' ),
	// Inside an inner fold the first and last rows keep taking no room at the body's ends (point 7c).
	$sel_decls( '.wpcpm-ui-fold--inner > .wpcpm-ui-fold__body > .wpcpm-ui-list:first-child > .wpcpm-ui-list__row:first-child' ),
	$sel_decls( '.wpcpm-ui-fold--inner > .wpcpm-ui-fold__body > .wpcpm-ui-list:last-child > .wpcpm-ui-list__row:last-child' ),
	$weight( '.wpcpm-ui-fold--inner > .wpcpm-ui-fold__body > .wpcpm-ui-list:first-child > .wpcpm-ui-list__row:first-child' ) > $weight( $t18d_row ),
), array( true, true, true, true, true, true, array( 'padding-top: 0' ), array( 'padding-bottom: 0' ), true ) );

// Task 18d, point 9: a wide table that needs about 936px of the main area (Programs running, `wide` 936) stacks while
// that container is under 936px: the same stacked rules as the phone's and the 880px query's, selector for selector,
// for its own modifier; and a run of words kept whole never breaks inside itself.
$t18d_cq = array();
$t18d_q  = '@container wpcpm-main ( max-width: 935.98px )';
$t18d_at = strpos( $raw, $t18d_q );
if ( false !== $t18d_at ) {
	$t18d_open = strpos( $raw, '{', $t18d_at );
	$t18d_d    = 0;
	for ( $t18d_i = $t18d_open; $t18d_i < strlen( $raw ); $t18d_i++ ) {
		if ( '{' === $raw[ $t18d_i ] ) {
			++$t18d_d;
		} elseif ( '}' === $raw[ $t18d_i ] && 0 === --$t18d_d ) {
			break;
		}
	}
	$t18d_cq = rules_of( substr( $raw, $t18d_open + 1, $t18d_i - $t18d_open - 1 ) );
}
$t18d_map = array();
foreach ( $t18d_cq as $r ) {
	$key              = preg_replace( '/^\.wpcpm-ui-table--wide-936/', '.TABLE', $r[0] );
	$t18d_map[ $key ] = array_merge( $t18d_map[ $key ] ?? array(), $r[1] );
}
ck( 'Task 18d: a table that needs 936px stacks below 936px of the main area, exactly as the phone\'s table, once, after the table\'s own rules; a whole run never wraps', array(
	substr_count( $raw, $t18d_q ),
	array_values( array_filter( array_map( static function ( $r ) { return $r[0]; }, $t18d_cq ), static function ( $sel ) { return 1 !== preg_match( '/^\.wpcpm-ui-table--wide-936(\s|$)/', $sel ); } ) ),
	$t18d_map === $t17_phone_t,
	false !== $t18d_at && $t18d_at > strpos( $raw, '.wpcpm-ui-table .wpcpm-ui-cell--rowhead' ),
	$sel_decls( '.wpcpm-ui-whole' ),
), array( 1, array(), true, true, array( 'white-space: nowrap' ) ) );

// Task 18e, points 1 and 2: inside an inner fold no row of a list keeps the 56px least height, which centred a one-line
// name 18px from the hairline in middle rows and 8px in the end rows: every row's line is its 8px over and under (a
// row's own content, an action or a tag, decides the rest the same on both sides), so each hairline has the same room
// above and below and every row-to-row distance is the same; a noted last row ends with its note's text, its 8px gone,
// as on a phone.
$t18e_all  = '.wpcpm-ui-fold--inner > .wpcpm-ui-fold__body > .wpcpm-ui-list > .wpcpm-ui-list__row > .wpcpm-ui-list__line';
// 1.123.102, round 3, V6a: except a last row with a `more` under its note, which keeps the note's 8px, so the two are two
// paragraphs at every width, as on a phone. The unscoped rule is gone.
$t18e_note = '.wpcpm-ui-fold--inner > .wpcpm-ui-fold__body > .wpcpm-ui-list:last-child > .wpcpm-ui-list__row:last-child:not( .wpcpm-ui-list__row--more ) > .wpcpm-ui-list__note';
ck( 'Task 18e: every row of a list inside an inner fold has no least height, and a noted last row adds no room under its note', array(
	$sel_decls( $t18e_all ),
	$weight( $t18e_all ) > $weight( '.wpcpm-ui-list__line' ),
	$has( '.wpcpm-ui-list__line', 'padding: 8px 0' ),
	$sel_decls( $t18e_note ),
	$weight( $t18e_note ) > $weight( '.wpcpm-ui-list__note' ),
	$has( '.wpcpm-ui-list__note', 'padding: 6px 0 8px' ),
	$sel_decls( '.wpcpm-ui-fold--inner > .wpcpm-ui-fold__body > .wpcpm-ui-list:last-child > .wpcpm-ui-list__row:last-child > .wpcpm-ui-list__note' ),
), array( array( 'min-height: 0' ), true, true, array( 'padding-bottom: 0' ), true, true, array() ) );

// Task 18e, point 3: a long name in a row with a tag wraps inside its own column (any long word broken), the tag keeps
// its natural width in a column of its own beside the name's first line, and the action keeps the last column, on line
// 1: name, tag and action share one baseline (the name's first line, the tag's words and the action's words), the
// line's tracks are centred and never stretched, so a one-line name with no action stays centred in its 56px.
ck( 'Task 18e: in a list with its facts under the name, a long name wraps in its column, the tag keeps its width beside the name\'s first line, the action its column on line 1', array(
	// Task 18f: break-word, not anywhere: a long word breaks only where it would overflow, and the name's min-content (a
	// grid's and a flex line's sharing of room) stays its longest word.
	! $in( $m601, '.wpcpm-ui-list--under .wpcpm-ui-list__name', 'overflow-wrap: anywhere' ) && $in( $m601, '.wpcpm-ui-list--under .wpcpm-ui-list__name', 'align-self: baseline' ),
	$in( $m601, '.wpcpm-ui-list--under .wpcpm-ui-list__line', 'align-content: center' ),
	$in( $m601, $t18d_tag, 'grid-column: 2' ) && $in( $m601, $t18d_tag, 'align-self: baseline' ) && $in( $m601, $t18d_tag, 'margin-left: 16px' ),
	$in( $m601, '.wpcpm-ui-list--under .wpcpm-ui-list__act', 'align-self: baseline' ),
), array( true, true, true, true ) );

// Task 18e, point 5: a card body's blocks are 16px apart (`:where( .wpcpm-ui-fold__body > * + * )`, weightless), but an
// intro line's own margin and a list's own zero margin outweigh it: a plain intro line after a table, and a list after
// the muted line, take the block's 16px by rules of the parts' own, heavier than those.
ck( 'Task 18e: an intro line after a table and a list after the muted line sit 16px under it, outweighing their own margins', array(
	$sel_decls( '.wpcpm-ui-table + .wpcpm-ui-intro' ),
	$weight( '.wpcpm-ui-table + .wpcpm-ui-intro' ) > $weight( '.wpcpm-ui-intro' ),
	$sel_decls( '.wpcpm-ui-intro--muted + .wpcpm-ui-list' ),
	$weight( '.wpcpm-ui-intro--muted + .wpcpm-ui-list' ) > $weight( '.wpcpm-ui-list' ),
), array( array( 'margin-top: var( --wpcpm-f-block )' ), true, array( 'margin-top: var( --wpcpm-f-block )' ), true ) );
// Point 6: in a stacked table (the phone's and both container queries) a cell the part marks blank is not drawn, its
// column's name with it; the three blocks stay identical (the 880px and 936px blocks are compared with the phone's).
ck( 'Task 18e: a blank cell is not drawn while its table is stacked, on a phone and below 880px and 936px of the main area', array(
	$in( $media( '(max-width: 600px)' ), '.wpcpm-ui-table td.wpcpm-ui-cell--blank', 'display: none' ),
	isset( $t17_wide_t['.TABLE td.wpcpm-ui-cell--blank'] ) && $t17_wide_t === $t17_phone_t,
	isset( $t18d_map['.TABLE td.wpcpm-ui-cell--blank'] ) && $t18d_map === $t17_phone_t,
	// Fix round 2: the phone's, its narrow copy's, and the two wide tables' blocks.
	count( array_filter( $parts, static function ( $r ) { return false !== strpos( $r[0], 'cell--blank' ) && in_array( 'display: none', $r[1], true ); } ) ) - 1,
), array( true, true, true, 3 ) );
// Point 7: on a phone a list row's facts that wrap are 4px apart, as an item header's are (design 2.10, task V1 ruling
// 11): one 24px step between two lines of facts in one card.
$m6b = $media( '(max-width: 600px)' );
ck( 'Task 18e: on a phone a list row\'s facts wrap with 4px between lines, as an item header\'s', array(
	$in( $m6b, '.wpcpm-ui-list__facts', 'display: flex' ) && $in( $m6b, '.wpcpm-ui-list__facts', 'flex-wrap: wrap' ) && $in( $m6b, '.wpcpm-ui-list__facts', 'row-gap: 4px' ),
	$in( $m6b, '.wpcpm-ui-item__facts', 'row-gap: 4px' ),
), array( true, true ) );

// Task 18f, point 1: no text a part prints can make its card wider than its container. Every text part breaks a word
// only where it would overflow (`overflow-wrap: break-word`, inherited; it leaves an element's min-content its longest
// word, so no grid or flex column shares its room differently for ordinary text); each flex or grid child holding text
// may be narrower than its longest word (`min-width: 0`, or a track whose least size is 0); the key and value table's
// value, its one flexible column beside a 320px label, may break a word anywhere, so a long address shrinks its
// min-content and the table stays in its card. A tag in a list with its facts under the name sits on the name's first
// line on a phone as above it.
$t18f_m6 = $media( '(max-width: 600px)' );
$t18f_bw = array();
// An item and a checklist are always inside a fold's body and inherit the fold's (their own rules stay their own).
foreach ( array( '.wpcpm-ui-fold', '.wpcpm-ui-list', '.wpcpm-ui-table', '.wpcpm-ui-intro', '.wpcpm-ui-decide' ) as $t18f_sel ) {
	$t18f_bw[ $t18f_sel ] = $has( $t18f_sel, 'overflow-wrap: break-word' );
}
ck( 'Task 18f: every text part breaks a word only where it would overflow', $t18f_bw, array_fill_keys( array_keys( $t18f_bw ), true ) );
ck( 'Task 18f: the flex and grid children that hold text may be narrower than a long word; the key and value value breaks anywhere; a phone row\'s tag sits on the name\'s first line', array(
	$has( '.wpcpm-ui-list__name', 'min-width: 0' ),
	$has( '.wpcpm-ui-list__facts', 'min-width: 0' ),
	$has( '.wpcpm-ui-item__fact', 'min-width: 0' ),
	$has( '.wpcpm-ui-item__title', 'overflow-wrap: anywhere' ),
	$has( '.wpcpm-ui-table--keyvalue td', 'overflow-wrap: anywhere' ),
	$in( $t18f_m6, '.wpcpm-ui-list--under .wpcpm-ui-list__name', 'align-self: baseline' ),
	$in( $t18f_m6, '.wpcpm-ui-on .wpcpm-ui-list--under .wpcpm-ui-list__line > .wpcpm-status', 'align-self: baseline' ),
	// Nothing in the sheet but these, the note, a code in a cell and the item title breaks a word anywhere.
	array_values( array_filter( array_map( static function ( $r ) { return $r[0]; }, array_filter( $parts, static function ( $r ) { return in_array( 'overflow-wrap: anywhere', $r[1], true ); } ) ), static function ( $sel ) { return ! in_array( $sel, array( '.wpcpm-ui-note', '.wpcpm-ui-list__note', '.wpcpm-ui-table .wpcpm-ui-code', '.wpcpm-ui-item__title', '.wpcpm-ui-table--keyvalue td', '.wpcpm-ui-table .wpcpm-ui-cell--token' ), true ); } ) ),
), array( true, true, true, true, true, true, true, array() ) );

// Task 18f, point 1b: a cell that prints a token (an address, an email, a code, a machine's error) may break anywhere,
// with a least width so an auto-layout table never squeezes its column to a few characters; every other cell keeps
// today's rule.
ck( 'Task 18f: a token cell breaks anywhere, at least 10em wide', $sel_decls( '.wpcpm-ui-table .wpcpm-ui-cell--token' ), array( 'min-width: 10em', 'overflow-wrap: anywhere' ) );
// Task 18f, point 3: a card head takes its two-line form while the frame's main area is under 800px, by the frame's own
// container, the phone's fold rules repeated selector for selector and declaration for declaration.
$t18f_block = static function ( $needle, $from = 0 ) use ( $raw ) {
	$at = strpos( $raw, $needle, $from );
	if ( false === $at ) {
		return array();
	}
	$open = strpos( $raw, '{', $at );
	$d    = 0;
	for ( $i = $open; $i < strlen( $raw ); $i++ ) {
		if ( '{' === $raw[ $i ] ) {
			++$d;
		} elseif ( '}' === $raw[ $i ] && 0 === --$d ) {
			break;
		}
	}
	return rules_of( substr( $raw, $open + 1, $i - $open - 1 ) );
};
$t18f_head_cq    = $t18f_block( '@container wpcpm-main ( max-width: 799.98px )' );
$t18f_head_phone = $t18f_block( "@media (max-width: 600px) {\n\t.wpcpm-ui-fold__head {" );
ck( 'Task 18f: the fold heads take their phone form under 800px of the main area, the phone\'s fold rules exactly, once', array(
	min( 1, substr_count( $raw, '@container wpcpm-main ( max-width: 799.98px )' ) ),
	count( $t18f_head_cq ) > 10,
	$t18f_head_cq === $t18f_head_phone,
	$in( $t18f_head_cq, '.wpcpm-ui-fold__head', 'display: grid' ),
), array( 1, true, true, true ) );

// Task 18f, point 4: a stacked wide table whose container is at least 600px wide flows a row's label and value pairs in
// columns (each at least 150px, as many as fit), the row's name across the row above them; under 600px the phone's one
// column. For both thresholds.
// Fix round 2: from 480px of container; every table under 800px of main area (a key and value table stays a block).
foreach ( array( '799.98px' => '.wpcpm-ui-table', '879.98px' => '.wpcpm-ui-table--wide', '935.98px' => '.wpcpm-ui-table--wide-936' ) as $t18f_max => $t18f_t ) {
	$t18f_cols = $t18f_block( '@container wpcpm-main ( min-width: 480px ) and ( max-width: ' . $t18f_max . ' )' );
	ck( "Task 18f: a stacked $t18f_t row flows in columns from 480px of container up to its threshold, its name across the row", array(
		$in( $t18f_cols, $t18f_t . ' tr', 'display: grid' ),
		// Fix round 3: equal tracks, content never widens one; the action its own line; a blank cell keeps its place; a
		// value may wrap.
		$in( $t18f_cols, $t18f_t . ' tr', 'grid-template-columns: repeat( 2, minmax( 0, 1fr ) )' ),
		$in( $t18f_cols, $t18f_t . ' tr', 'gap: 8px 16px' ),
		$in( $t18f_cols, $t18f_t . ' .wpcpm-ui-cell--rowhead', 'grid-column: 1 / -1' ),
		$in( $t18f_cols, $t18f_t . ' td.wpcpm-ui-cell--action', 'grid-column: 1 / -1' ),
		$in( $t18f_cols, $t18f_t . ' td.wpcpm-ui-cell--blank:not( .wpcpm-ui-cell--action )', 'display: block' ),
		$in( $t18f_cols, $t18f_t . ' td', 'white-space: normal' ) && ! $in( $t18f_cols, $t18f_t . ' .wpcpm-ui-whole', 'white-space: normal' ),
	), array( true, true, true, true, true, true, true ) );
}
ck( 'Task 18f fix round 2: under 800px of main area a key and value table stays label over value', $in( $t18f_block( '@container wpcpm-main ( min-width: 480px ) and ( max-width: 799.98px )' ), '.wpcpm-ui-table--keyvalue tr', 'display: block' ), true );
// Fix round 3 (b): the lines a row's N pairs take, computed from the sheet's own rules for each N and each width the
// ruling names (the row's width; the main area is 34px more), and the ruling's choice computed independently: all N on
// one line when they fit at 150px and 16px apart; else two lines of ceil(N/2) when they fit and leave no pair alone;
// else the largest count that fits and leaves none alone; else as many as fit. A lone pair is allowed only there.
$t18f_choice = static function ( $n, $w ) {
	$k = (int) floor( ( $w + 16 ) / 166 );
	if ( $n <= $k ) {
		return $n;
	}
	$h = (int) ceil( $n / 2 );
	if ( $h <= $k && 1 !== $n % $h ) {
		return $h;
	}
	for ( $c = $k; $c >= 2; $c-- ) {
		if ( 1 !== $n % $c ) {
			return $c;
		}
	}
	return $k;
};
$t18f_sheet = static function ( $n, $w ) use ( $raw ) {
	$main = $w + 34;
	if ( preg_match_all( '/@container wpcpm-main \( min-width: (\d+)px \) and \( max-width: ([\d.]+)px \) \{(.*?)\n\}/s', $raw, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $b ) {
			if ( $main >= (float) $b[1] && $main <= (float) $b[2] && preg_match( '/\.wpcpm-ui-table--pairs-' . $n . ' tr \{\s*grid-template-columns: repeat\( (\d+), minmax\( 0, 1fr \) \);/', $b[3], $t ) ) {
				return (int) $t[1];
			}
		}
	}
	return null;
};
$t18f_lines = static function ( $n, $c ) {
	$out = array();
	for ( $left = $n; $left > 0; $left -= $c ) {
		$out[] = min( $c, $left );
	}
	return implode( '+', $out );
};
$t18f_got  = array();
$t18f_want = array();
$t18f_lone = array();
foreach ( array( 2, 3, 4, 5, 6, 7 ) as $t18f_n ) {
	foreach ( array( 480, 535, 634, 702, 766, 846, 902 ) as $t18f_w ) {
		$t18f_c                               = $t18f_sheet( $t18f_n, $t18f_w );
		$t18f_got[ $t18f_n . '@' . $t18f_w ]  = null === $t18f_c ? null : $t18f_lines( $t18f_n, $t18f_c );
		// A row 902px wide is a main area of 936px: no table is stacked there (936 is the highest threshold).
		$t18f_want[ $t18f_n . '@' . $t18f_w ] = 902 === $t18f_w ? null : $t18f_lines( $t18f_n, $t18f_choice( $t18f_n, $t18f_w ) );
		if ( null !== $t18f_c && $t18f_n > $t18f_c && 1 === $t18f_n % $t18f_c ) {
			$t18f_lone[] = $t18f_n . '@' . $t18f_w;
		}
	}
}
ck( 'Task 18f fix round 3: each pair count takes the ruling\'s lines at each width', $t18f_got, $t18f_want );
// The only lone pairs are where no count that fits avoids one: three, five and seven pairs at 480px (two fit), seven at
// 535 and 634px (three fit).
ck( 'Task 18f fix round 3: a pair alone on a last line only where nothing that fits avoids it', $t18f_lone, array( '3@480', '5@480', '7@480', '7@535', '7@634' ) );

// Task 18f, point 5: an inner fold right after an item stands 24px off it, as an item off the next.
ck( 'Task 18f: an inner fold after an item is 24px under it, outweighing the inner fold\'s own 16px', array(
	$sel_decls( '.wpcpm-ui-item + .wpcpm-ui-fold--inner' ),
	$weight( '.wpcpm-ui-item + .wpcpm-ui-fold--inner' ) > $weight( '.wpcpm-ui-fold--inner' ),
	$has( '.wpcpm-ui-item + .wpcpm-ui-item', 'margin-top: var( --wpcpm-f-apart )' ),
), array( array( 'margin-top: var( --wpcpm-f-apart )' ), true, true ) );

// Task 18f, point 7: from 601px up an item header's facts are always one block beside its tag: the facts line never
// wraps (no flex-wrap outside the phone query), the run takes the room after the tag and may be narrower than a long
// fact, which breaks inside it (break-word from the fold); only the phone query lets the run give up its box.
$t18f_m6i = $media( '(max-width: 600px)' );
// Fix round 2 (one narrow form): the facts wrap under the tag on a phone and while the main area is under 800px, and
// stay a block beside the tag otherwise.
$t18f_wrapf = static function ( $r ) { return ( '.wpcpm-ui-item__facts' === $r[0] && in_array( 'flex-wrap: wrap', $r[1], true ) ) || ( '.wpcpm-ui-item__run' === $r[0] && in_array( 'display: contents', $r[1], true ) ); };
ck( 'Task 18f: from 800px of main area up the facts stay a block beside the tag; only the phone query and the narrow form wrap them under it', array(
	count( array_filter( $parts, $t18f_wrapf ) ) === count( array_filter( $t18f_m6i, $t18f_wrapf ) ) + count( array_filter( $narrow, $t18f_wrapf ) ) && count( array_filter( $narrow, $t18f_wrapf ) ) > 0,
	$has( '.wpcpm-ui-item__facts', 'display: flex' ),
	$has( '.wpcpm-ui-item__run', 'flex: 1 1 0' ) && $has( '.wpcpm-ui-item__run', 'min-width: 0' ),
	$has( '.wpcpm-ui-fold', 'overflow-wrap: break-word' ),
), array( true, true, true, true ) );

// Task 18f, point 9: after a plain line of text (an intro line, a note, the empty line) the foot's hairline is 16px
// under the text, as the block step; the empty line's own 12px under it counts toward that.
ck( 'Task 18f: the foot after a plain line sits 16px under the text', array(
	$sel_decls( '.wpcpm-ui-intro + .wpcpm-ui-fold__foot' ),
	$sel_decls( '.wpcpm-ui-note + .wpcpm-ui-fold__foot' ),
	$sel_decls( '.wpcpm-ui-list__empty + .wpcpm-ui-fold__foot' ),
	$has( '.wpcpm-ui-list__empty', 'padding: 12px 0' ),
	$weight( '.wpcpm-ui-intro + .wpcpm-ui-fold__foot' ) > $weight( '.wpcpm-ui-fold__foot' ),
), array( array( 'margin-top: var( --wpcpm-f-block )' ), array( 'margin-top: var( --wpcpm-f-block )' ), array( 'margin-top: 4px' ), true, true ) );

// Task 18f, point 10: a stacked table (the phone's, and below 880px and 936px of the main area; key and value tables
// too) adds no room of its own before its first row; the three stacked blocks stay identical.
ck( 'Task 18f: a stacked table\'s first row takes no room above it', array(
	$in( $media( '(max-width: 600px)' ), '.wpcpm-ui-table tr:first-child', 'padding-top: 0' ),
	isset( $t17_wide_t['.TABLE tr:first-child'] ) && $t17_wide_t === $t17_phone_t,
	isset( $t18d_map['.TABLE tr:first-child'] ) && $t18d_map === $t17_phone_t,
), array( true, true, true ) );
// Point 10b: an in-card heading is 24px under the block above it and 8px over the block it heads, whatever the block:
// the empty line's own 12px over and under its words count toward those.
ck( 'Task 18f: under a heading the empty line takes no room of its own above; a heading after it is 24px under its words', array(
	$sel_decls( '.wpcpm-ui-h + .wpcpm-ui-list__empty' ),
	$weight( '.wpcpm-ui-h + .wpcpm-ui-list__empty' ) > $weight( '.wpcpm-ui-list__empty' ),
	$sel_decls( '.wpcpm-ui-list__empty + .wpcpm-ui-h' ),
	$weight( '.wpcpm-ui-list__empty + .wpcpm-ui-h' ) > $weight( '.wpcpm-ui-h' ),
), array( array( 'padding-top: 0' ), true, array( 'margin-top: 12px' ), true ) );

// Task 18f, point 11: in a stacked table the quiet action's 10px under its words count as the row's room, so the
// words sit as far over the row's line as the next name sits under it (12px row padding and 2px cell padding each side).
ck( 'Task 18f: a stacked table\'s action cell gives back the quiet button\'s 10px under its words', array(
	$in( $media( '(max-width: 600px)' ), '.wpcpm-ui-table td.wpcpm-ui-cell--action', 'margin-bottom: -10px' ),
	$has( '.wpcpm-ui-on .wpcpm-btn--quiet', 'padding: 10px 8px' ),
	$t17_wide_t === $t17_phone_t && $t18d_map === $t17_phone_t,
), array( true, true, true ) );

// Task 18f, point 13: a card head's phrase leaves no word alone on its last line.
// Fix round 1 (Minor 4): the phrase's own rule is exactly that; the count, which shares the phrase's first rule, does not
// take it.
ck( 'Task 18f: the phrase wraps pretty, and only the phrase', array(
	in_array( 'text-wrap: pretty', $sel_decls( '.wpcpm-ui-fold__phrase' ), true ),
	in_array( 'text-wrap: pretty', $sel_decls( '.wpcpm-ui-count' ), true ),
	count( array_filter( $parts, static function ( $r ) { return in_array( 'text-wrap: pretty', $r[1], true ); } ) ),
), array( true, false, 1 ) );

// Task 18f, point 14: a wide table's column names may wrap, bottom-aligned; every other table's never wrap.
ck( 'Task 18f: a wide table\'s header may wrap, bottom-aligned, outweighing the header\'s nowrap', array(
	$sel_decls( '.wpcpm-ui-table--wide thead th' ),
	$weight( '.wpcpm-ui-table--wide thead th' ) >= $weight( '.wpcpm-ui-table th' ),
	$has( '.wpcpm-ui-table th', 'white-space: nowrap' ),
), array( array( 'vertical-align: bottom', 'white-space: normal' ), true, true ) );

// Task 18f fix round 1 (Important 2): a fact is an inline-block from 601px up, as wide as its longest word at least
// unless something caps it: it is capped at its run's width less the 16px gutter it stands in, so a word wider than
// that breaks inside the fact (break-word) instead of running past the run's clip; item header and list row alike.
ck( 'Task 18f fix round 1: a fact is never wider than its run less its gutter', array(
	$has( '.wpcpm-ui-item__fact', 'max-width: calc( 100% - 16px )' ),
	$has( '.wpcpm-ui-item__fact', 'margin-left: 16px' ),
	$has( '.wpcpm-ui-item__fact', 'display: inline-block' ),
), array( true, true, true ) );

// Task 18f, fix round 2: one narrow form. Every phone block has its copy for a main area under 800px right after it,
// equal rule for rule; the facts-under list's desktop layout applies only from 800px of main area.
// Fix round 3 (Minor 3): every phone block, however it is spelled (spaces, case, `max-width:600px`, `screen and`).
$t18f_pairs = array();
preg_match_all( '/@media[^{]*\(\s*max-width\s*:\s*600px\s*\)[^{]*\{/i', $raw, $t18f_found, PREG_OFFSET_CAPTURE );
foreach ( $t18f_found[0] as $t18f_hit ) {
	$t18f_phone   = $t18f_block( $t18f_hit[0], $t18f_hit[1] );
	$t18f_next    = strpos( $raw, '@container wpcpm-main ( max-width: 799.98px )', $t18f_hit[1] );
	$t18f_pairs[] = false !== $t18f_next && $t18f_phone === $t18f_block( '@container wpcpm-main ( max-width: 799.98px )', $t18f_next );
}
ck( 'Task 18f fix round 2: every phone block is repeated for a main area under 800px, rule for rule', array( count( $t18f_pairs ), array_unique( $t18f_pairs ) ), array( substr_count( $raw, '@container wpcpm-main ( max-width: 799.98px )' ), array( true ) ) );
ck( 'Task 18f fix round 2: the facts-under list\'s desktop layout applies only from 800px of main area', 1 === preg_match( '/@media \(min-width: 601px\) \{\s*@container wpcpm-main \( min-width: 800px \) \{/', $raw ), true );

// Fix round 4: the cascade, worked as a browser works it, for one element of a stacked table. Every rule of the sheet with
// the conditions it sits under (media and container queries, nested ones too), its order and its weight; a selector
// matched compound by compound against the element and its ancestors (an element name, classes, `:not( .class )`); the
// winner of a property is the matching rule of the highest weight, the latest of equal weight, among those whose
// conditions hold at the window's and the main area's widths.
$t18f_ctx_rules = (function () use ( $raw ) {
	$css   = preg_replace( '#/\*.*?\*/#s', '', $raw );
	$out   = array();
	$stack = array();
	$head  = '';
	$len   = strlen( $css );
	$order = 0;
	for ( $i = 0; $i < $len; $i++ ) {
		$c = $css[ $i ];
		if ( '{' === $c ) {
			$sel = trim( preg_replace( '/\s+/', ' ', $head ) );
			$head = '';
			if ( '@' === substr( $sel, 0, 1 ) ) {
				$stack[] = $sel;
				continue;
			}
			$end  = strpos( $css, '}', $i );
			$body = substr( $css, $i + 1, $end - $i - 1 );
			$decl = array();
			foreach ( explode( ';', $body ) as $d ) {
				$d = trim( preg_replace( '/\s+/', ' ', $d ) );
				if ( '' !== $d && false !== strpos( $d, ':' ) ) {
					list( $k, $v ) = array_map( 'trim', explode( ':', $d, 2 ) );
					$decl[ $k ]    = $v;
				}
			}
			foreach ( preg_split( '/\s*,\s*(?![^()]*\))/', $sel ) as $one ) {
				$out[] = array( 'sel' => $one, 'decl' => $decl, 'cond' => $stack, 'order' => ++$order );
			}
			$i = $end;
		} elseif ( '}' === $c ) {
			array_pop( $stack );
			$head = '';
		} else {
			$head .= $c;
		}
	}
	return $out;
})();
$t18f_cond_holds = static function ( array $conds, $window, $main ) {
	foreach ( $conds as $cond ) {
		$target = 0 === strpos( $cond, '@' . 'media' ) ? $window : $main;
		if ( preg_match_all( '/(min|max)-width\s*:\s*([\d.]+)px/', $cond, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $q ) {
				if ( ( 'min' === $q[1] && $target < (float) $q[2] ) || ( 'max' === $q[1] && $target > (float) $q[2] ) ) {
					return false;
				}
			}
		}
	}
	return true;
};
$t18f_compound = static function ( $compound, array $node ) {
	if ( preg_match( '/^([a-z]+)/', $compound, $el ) && $el[1] !== $node['el'] ) {
		return false;
	}
	$rest = preg_replace( '/:not\(\s*\.([\w-]+)\s*\)/', ' NOT.$1', $compound );
	if ( preg_match_all( '/NOT\.([\w-]+)/', $rest, $nots ) ) {
		foreach ( $nots[1] as $c ) {
			if ( in_array( $c, $node['cls'], true ) ) {
				return false;
			}
		}
	}
	$rest = preg_replace( '/NOT\.[\w-]+/', '', $rest );
	if ( preg_match( '/::|:(?!not)/', $rest ) ) {
		return false;
	}
	preg_match_all( '/\.([\w-]+)/', $rest, $cls );
	foreach ( $cls[1] as $c ) {
		if ( ! in_array( $c, $node['cls'], true ) ) {
			return false;
		}
	}
	return true;
};
$t18f_matches = static function ( $selector, array $chain ) use ( $t18f_compound ) {
	$parts = preg_split( '/\s+(?:>\s+)?(?![^()]*\))/', trim( $selector ) );
	$node  = count( $chain ) - 1;
	if ( ! $t18f_compound( array_pop( $parts ), $chain[ $node ] ) ) {
		return false;
	}
	for ( $p = count( $parts ) - 1; $p >= 0; $p-- ) {
		$found = false;
		for ( --$node; $node >= 0; $node-- ) {
			if ( $t18f_compound( $parts[ $p ], $chain[ $node ] ) ) {
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			return false;
		}
	}
	return true;
};
$t18f_weight = static function ( $selector ) {
	$s = preg_replace( '/:not\(\s*(\.[\w-]+)\s*\)/', '$1', $selector );
	return array( preg_match_all( '/\.[\w-]+|\[[^\]]*\]|:[a-z-]+/', $s ), preg_match_all( '/(?:^|[\s>+~])[a-z]+/', $s ) );
};
$t18f_winner = static function ( array $chain, $property, $window, $main ) use ( $t18f_ctx_rules, $t18f_cond_holds, $t18f_matches, $t18f_weight ) {
	$best = null;
	foreach ( $t18f_ctx_rules as $r ) {
		if ( ! isset( $r['decl'][ $property ] ) || ! $t18f_cond_holds( $r['cond'], $window, $main ) || ! $t18f_matches( $r['sel'], $chain ) ) {
			continue;
		}
		$key = array_merge( $t18f_weight( $r['sel'] ), array( $r['order'] ) );
		if ( null === $best || $key > $best[0] ) {
			$best = array( $key, $r['decl'][ $property ], $r['sel'] );
		}
	}
	return null === $best ? null : $best[1];
};
$t18f_chain = static function ( array $table_cls, array $cell_cls ) {
	return array(
		array( 'el' => 'div', 'cls' => array( 'wpcpm-ui-on' ) ),
		array( 'el' => 'div', 'cls' => array( 'wpcpm-ui-fold__body' ) ),
		array( 'el' => 'table', 'cls' => $table_cls ),
		array( 'el' => 'tbody', 'cls' => array() ),
		array( 'el' => 'tr', 'cls' => array() ),
		array( 'el' => 'td', 'cls' => $cell_cls ),
	);
};
// The winning white-space of a stacked cell of each kind, in the multi-column form of each family (a window of 1280; a
// main area in each family's stacked range), and of each kind as a table (it never wraps there). A whole run stays
// whole in every form.
$t18f_ws = array();
foreach ( array( 'plain 682' => array( array( 'wpcpm-ui-table', 'wpcpm-ui-table--pairs-4' ), 682 ), 'wide 846' => array( array( 'wpcpm-ui-table', 'wpcpm-ui-table--wide', 'wpcpm-ui-table--pairs-6' ), 846 ), 'wide-936 900' => array( array( 'wpcpm-ui-table', 'wpcpm-ui-table--wide', 'wpcpm-ui-table--wide-936', 'wpcpm-ui-table--pairs-6' ), 900 ), 'plain as a table 960' => array( array( 'wpcpm-ui-table', 'wpcpm-ui-table--pairs-4' ), 960 ) ) as $t18f_case => $t18f_spec ) {
	foreach ( array( 'date', 'short', 'num', 'text' ) as $t18f_kind ) {
		$t18f_ws[ $t18f_case . ' ' . $t18f_kind ] = $t18f_winner( $t18f_chain( $t18f_spec[0], array( 'wpcpm-ui-cell--' . $t18f_kind ) ), 'white-space', 1280, $t18f_spec[1] );
	}
	$t18f_whole_chain   = $t18f_chain( $t18f_spec[0], array( 'wpcpm-ui-cell--text' ) );
	$t18f_whole_chain[] = array( 'el' => 'span', 'cls' => array( 'wpcpm-ui-whole' ) );
	$t18f_ws[ $t18f_case . ' whole' ] = $t18f_winner( $t18f_whole_chain, 'white-space', 1280, $t18f_spec[1] );
}
ck( 'Task 18f fix round 4: in every stacked form a date, a short word and a number wrap, by the declaration that wins; as a table they never wrap; a whole run stays whole everywhere', $t18f_ws, array(
	'plain 682 date' => 'normal', 'plain 682 short' => 'normal', 'plain 682 num' => 'normal', 'plain 682 text' => 'normal', 'plain 682 whole' => 'nowrap',
	'wide 846 date' => 'normal', 'wide 846 short' => 'normal', 'wide 846 num' => 'normal', 'wide 846 text' => 'normal', 'wide 846 whole' => 'nowrap',
	'wide-936 900 date' => 'normal', 'wide-936 900 short' => 'normal', 'wide-936 900 num' => 'normal', 'wide-936 900 text' => 'normal', 'wide-936 900 whole' => 'nowrap',
	'plain as a table 960 date' => 'nowrap', 'plain as a table 960 short' => 'nowrap', 'plain as a table 960 num' => 'nowrap', 'plain as a table 960 text' => null, 'plain as a table 960 whole' => 'nowrap',
) );
// Fix round 4, measured: a token cell's 10em least width is a table's; in a row of lines it takes its track's.
$t18f_tok = array();
foreach ( array( 'plain 682' => array( array( 'wpcpm-ui-table', 'wpcpm-ui-table--pairs-4' ), 682 ), 'wide 846' => array( array( 'wpcpm-ui-table', 'wpcpm-ui-table--wide', 'wpcpm-ui-table--pairs-4' ), 846 ), 'wide-936 900' => array( array( 'wpcpm-ui-table', 'wpcpm-ui-table--wide', 'wpcpm-ui-table--wide-936', 'wpcpm-ui-table--pairs-4' ), 900 ), 'as a table 960' => array( array( 'wpcpm-ui-table', 'wpcpm-ui-table--pairs-4' ), 960 ) ) as $t18f_case => $t18f_spec ) {
	$t18f_tok[ $t18f_case ] = $t18f_winner( $t18f_chain( $t18f_spec[0], array( 'wpcpm-ui-cell--token' ) ), 'min-width', 1280, $t18f_spec[1] );
}
ck( 'Task 18f fix round 4: a token cell keeps 10em as a table and gives it up in a row of lines', $t18f_tok, array( 'plain 682' => '0', 'wide 846' => '0', 'wide-936 900' => '0', 'as a table 960' => '10em' ) );
// Fix round 4 (Important 2): the joining cell's declarations, as they win, in the single column (a phone, a narrow main
// area) and in lines: on the header's line (row 1), at its end (the last track, justified to the end), centred on it,
// with no label drawn.
$t18f_join = array();
foreach ( array( 'phone 390' => array( 390, 358 ), 'narrow 470' => array( 1280, 470 ), 'lines 682' => array( 1280, 682 ), 'wide lines 846' => array( 1280, 846 ) ) as $t18f_case => $t18f_wm ) {
	$t18f_jc = $t18f_chain( array( 'wpcpm-ui-table', 'wpcpm-ui-table--wide', 'wpcpm-ui-table--pairs-6' ), array( 'wpcpm-ui-cell--short', 'wpcpm-ui-cell--joins' ) );
	$t18f_out = array();
	foreach ( array( 'grid-row', 'grid-column', 'justify-self', 'align-self' ) as $t18f_prop ) {
		$t18f_out[ $t18f_prop ] = $t18f_winner( $t18f_jc, $t18f_prop, $t18f_wm[0], $t18f_wm[1] );
	}
	$t18f_join[ $t18f_case ] = $t18f_out;
}
ck( 'Task 18f fix round 4: a joining cell sits at the end of the header\'s line in every stacked form', $t18f_join, array_fill_keys( array( 'phone 390', 'narrow 470', 'lines 682', 'wide lines 846' ), array( 'grid-row' => '1', 'grid-column' => '-2 / -1', 'justify-self' => 'end', 'align-self' => 'center' ) ) );
ck( 'Task 18f fix round 4: a joining cell draws no label, in the phone block and its narrow copy and both wide blocks', array(
	$in( $media( '(max-width: 600px)' ), '.wpcpm-ui-table td.wpcpm-ui-cell--joins::before', 'content: none' ),
	$in( $narrow, '.wpcpm-ui-table td.wpcpm-ui-cell--joins::before', 'content: none' ),
	isset( $t17_wide_t['.TABLE td.wpcpm-ui-cell--joins::before'] ) && in_array( 'content: none', $t17_wide_t['.TABLE td.wpcpm-ui-cell--joins::before'], true ),
	isset( $t18d_map['.TABLE td.wpcpm-ui-cell--joins::before'] ) && in_array( 'content: none', $t18d_map['.TABLE td.wpcpm-ui-cell--joins::before'], true ),
), array( true, true, true, true ) );
// Fix round 4 (Minor 1): the lines at every block's edge and a pixel either side, by main area.
$t18f_edges = array();
foreach ( array( 479, 480, 481, 515, 515.98, 516, 517, 681, 681.98, 682, 683, 847, 847.98, 848, 849, 935, 935.98 ) as $t18f_main ) {
	foreach ( array( 3, 4, 5, 6, 7 ) as $t18f_n ) {
		$t18f_c = $t18f_sheet( $t18f_n, $t18f_main - 34 );
		$t18f_edges[ $t18f_n . '@' . $t18f_main ] = $t18f_main < 480 ? ( null === $t18f_c ) : ( $t18f_c === $t18f_choice( $t18f_n, $t18f_main - 34 ) );
	}
}
ck( 'Task 18f fix round 4: at each block\'s edge and a pixel either side the lines are the ruling\'s', array_keys( array_filter( $t18f_edges, static function ( $ok ) { return ! $ok; } ) ), array() );

echo "\n" . ( $fail ? "$fail of $checks checks failed" : "all $checks checks passed" ) . "\n";
exit( $fail ? 1 : 0 );
