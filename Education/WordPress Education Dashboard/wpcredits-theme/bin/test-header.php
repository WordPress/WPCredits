<?php
/**
 * The site header's wrap above 640px (theme 1.24.16), read from the sheet and the part as they are
 * written.
 *
 * The bar was designed to wrap: `.wpc-header__inner { flex-wrap: wrap }`, so a group that does not fit
 * beside the brand drops to a row of its own. That rule never took effect, because the header part's
 * group declares `"flexWrap":"nowrap"` and core prints `flex-wrap: nowrap` for it at the same single
 * class, after this sheet, and the viewer chip of a signed-in Administrator (462px, unable to shrink)
 * pushed the page sideways from 641px to about 806px. The wrap is now said at two classes above 640px.
 *
 * A static check cannot see the cascade; it pins what the cascade rests on: the rule, the class the
 * part gives its group, and the three one-class rules the two-class one must not replace. The render
 * is measured in a browser, at 641, 700, 760, 806, 807, 848 and 900px, in iframes of fixed width.
 *
 * The markup the menu needs (theme 1.24.20): the head mark, the logo link's name, the lone navigation
 * item's marks, and the Menu button, the panel's ID and the class a signed-in reader's header gets,
 * run on the stand-ins of bin/stubs/hub-wp.php over the markup of bin/stubs/header-markup.php.
 *
 * The menu layout (theme 1.24.20), read from the sheet as it is written: each rule the one-row bar, the
 * panel, the account picture, the rows and the signed-out phone row rest on is in the sheet once, in its
 * at-rule, with the declarations the cascade and the layout depend on; every rule of it is under
 * `.wpc-js .wpc-header--menu` and inside the screen query below 1200px; the submenu rules outrank every
 * rule outside the menu layout that reveals a submenu; nothing but the open panel and the picture is made
 * visible; and the layout comes after the header rules it restates, so a tie of specificity goes its way.
 *
 * The menu script (theme 1.24.20): the callbacks hooked on `wp_enqueue_scripts` enqueue it for a
 * signed-in reader alone, deferred, and the handbook launcher's comment names the plugin's class it
 * reads. What the script does on the page is bin/test-header-js.php's to run.
 *
 * Run from the theme root:  php bin/test-header.php
 *
 * @package WPCredits_Theme
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$fail  = 0;
$total = 0;

/**
 * One check.
 *
 * @param string $label    What is asked.
 * @param mixed  $actual   What happened.
 * @param mixed  $expected What should have happened.
 */
function ck( $label, $actual, $expected ) {
	global $fail, $total;

	++$total;

	$ok = $actual === $expected;

	if ( ! $ok ) {
		++$fail;
	}

	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";

	if ( ! $ok ) {
		echo '       expected: ' . var_export( $expected, true ) . "\n";
		echo '       actual:   ' . var_export( $actual, true ) . "\n";
	}
}

/**
 * A file of the theme.
 *
 * @param string $path Below the theme root.
 * @return string
 */
function theme_file( $path ) {
	return (string) file_get_contents( dirname( __DIR__ ) . '/' . $path );
}

require_once __DIR__ . '/stubs/hub-wp.php';
require_once __DIR__ . '/stubs/header-markup.php';
require_once WPCREDITS_THEME_DIR . 'inc/icons.php';
require_once WPCREDITS_THEME_DIR . 'inc/header.php';

/**
 * Every style rule of style.css in the order the sheet has them, each with the at-rules it sits in,
 * comments left out. A rule is a prelude and a body with no braces inside; an at-rule that holds rules
 * (`@media`, `@supports`) is entered, whatever its depth, and one that holds declarations
 * (`@font-face`) is read as a rule of its own.
 *
 * @return array[] Each as `array( 'at' => at-rules joined by " > " or '', 'selector' => the selector
 *                 list, whitespace run together, 'declarations' => the declarations, one per entry,
 *                 whitespace run together )`.
 */
function sheet_rules() {
	$css   = preg_replace( '#/\*.*?\*/#s', '', theme_file( 'style.css' ) );
	$rules = array();
	$stack = array();

	preg_match_all( '/\{|\}|"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|[^{}"\']+/s', $css, $tokens );

	$prelude = '';
	$body    = null;
	$depth   = 0;

	foreach ( $tokens[0] as $token ) {
		if ( '{' === $token ) {
			if ( null !== $body ) {
				// A brace inside a rule's body: not a sheet this reader can read.
				$body .= $token;
				++$depth;
				continue;
			}

			$name = trim( preg_replace( '/\s+/', ' ', $prelude ) );

			if ( preg_match( '/^@(?:media|supports|container|layer)\b/', $name ) ) {
				$stack[] = $name;
				$prelude = '';
			} else {
				$body = '';
			}

			continue;
		}

		if ( '}' === $token ) {
			if ( null !== $body ) {
				if ( $depth > 0 ) {
					--$depth;
					$body .= $token;
					continue;
				}

				$declarations = array();

				foreach ( explode( ';', $body ) as $declaration ) {
					$declaration = trim( preg_replace( '/\s+/', ' ', $declaration ) );

					if ( '' !== $declaration ) {
						$declarations[] = $declaration . ';';
					}
				}

				$rules[] = array(
					'at'           => implode( ' > ', $stack ),
					'selector'     => trim( preg_replace( '/\s+/', ' ', $prelude ) ),
					'declarations' => $declarations,
				);

				$body    = null;
				$prelude = '';
			} else {
				array_pop( $stack );
				$prelude = '';
			}

			continue;
		}

		if ( null !== $body ) {
			$body .= $token;
		} else {
			$prelude .= $token;
		}
	}

	return $rules;
}

/**
 * The declarations of every rule whose selector list is exactly the one given, inside exactly the at-rule
 * given (`''` for none), in the order the sheet has them.
 *
 * @param string $selector The selector list, as the sheet writes it.
 * @param string $at       The at-rule, as the sheet writes it, such as `@media ( min-width: 641px )`.
 * @return array[] Each rule's declarations.
 */
function rules_of( $selector, $at = '' ) {
	$found = array();

	foreach ( sheet_rules() as $rule ) {
		if ( $rule['selector'] === $selector && $rule['at'] === $at ) {
			$found[] = $rule['declarations'];
		}
	}

	return $found;
}

/**
 * How many rules of the sheet name a selector list exactly, wherever they sit.
 *
 * @param string $selector The selector list.
 * @return int
 */
function rules_anywhere( $selector ) {
	return count( array_filter( sheet_rules(), static function ( $rule ) use ( $selector ) { return $rule['selector'] === $selector; } ) );
}

echo "=== The wrap above 640px ===\n";

$wide = '@media ( min-width: 641px )';

ck( 'above 640px the bar wraps and its rows sit 4px apart: one rule for `.wpc-header .wpc-header__inner`, two classes deep so it outranks the one-class `flex-wrap: nowrap` and block gap core prints for the part\'s group, in either print order, declaring the wrap and the row gap and nothing else (the column gap stays what it renders with)',
	rules_of( '.wpc-header .wpc-header__inner', $wide ),
	array( array( 'flex-wrap: wrap;', 'row-gap: 4px;' ) ) );

ck( 'that rule is only for the wide bar: no rule of the same two classes outside the 641px query, which would reach the phone block and outrank its one-class padding and height',
	array( rules_anywhere( '.wpc-header .wpc-header__inner' ), rules_of( '.wpc-header .wpc-header__inner' ) ),
	array( 1, array() ) );

echo "\n=== What the wrap rests on ===\n";

$part = new DOMDocument();

libxml_use_internal_errors( true );
$part->loadHTML( '<?xml encoding="UTF-8"><html><body>' . theme_file( 'parts/header.html' ) . '</body></html>' );
libxml_clear_errors();

$xpath  = new DOMXPath( $part );
$has    = static function ( $name ) { return 'contains(concat(" ", normalize-space(@class), " "), " ' . $name . ' ")'; };
$inner  = $xpath->query( '//header[' . $has( 'wpc-header' ) . ']/div[' . $has( 'wp-block-group' ) . '][' . $has( 'wpc-header__inner' ) . ']' );
$inside = $inner->length ? $xpath->query( './div[' . $has( 'wpc-brand' ) . ']', $inner->item( 0 ) )->length : 0;

ck( 'parts/header.html still gives its inner group the class `wpc-header__inner`, as the header\'s one direct child group holding the brand: the hook the wrap rule needs',
	array( $inner->length, $inside, (bool) preg_match( '/<!-- wp:group \{"className":"wpc-header__inner",/', theme_file( 'parts/header.html' ) ) ),
	array( 1, 1, true ) );

ck( 'the bar\'s one-class rules are still one class: the base rule, once, outside any query, with the wrap and the 4px row gap it was designed with, so a later edit does not turn it into a two-class rule that would outrank the phone block\'s',
	array( count( rules_of( '.wpc-header__inner' ) ), array_intersect( array( 'flex-wrap: wrap;', 'row-gap: 4px;', 'column-gap: 40px;' ), rules_of( '.wpc-header__inner' )[0] ?? array() ) ),
	array( 1, array( 'flex-wrap: wrap;', 'row-gap: 4px;', 'column-gap: 40px;' ) ) );

ck( 'the 960px block still narrows the column gap with its one-class `.wpc-header__inner` rule',
	rules_of( '.wpc-header__inner', '@media ( max-width: 960px )' ),
	array( array( 'column-gap: 16px;' ) ) );

$phone = rules_of( '.wpc-header__inner', '@media ( max-width: 640px )' );

ck( 'the 640px block still carries its one-class `.wpc-header__inner` rule, with the height and the padding it sets for the phone\'s two rows',
	array( count( $phone ), array_values( array_intersect( array( 'min-height: 0;', 'padding-bottom: 10px;', 'padding-top: 10px;' ), $phone[0] ?? array() ) ) ),
	array( 1, array( 'min-height: 0;', 'padding-bottom: 10px;', 'padding-top: 10px;' ) ) );

echo "\n=== The markup the menu needs ===\n";

$menu = static function ( $html, $class = 'wpc-header' ) {
	return wpcredits_header_menu( $html, array( 'blockName' => 'core/group', 'attrs' => array( 'className' => $class ) ) );
};

$page_of = static function ( $html ) {
	$doc = new DOMDocument();

	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();

	return new DOMXPath( $doc );
};

$attributes_of = static function ( $element ) {
	$attributes = array();

	foreach ( $element->attributes as $attribute ) {
		$attributes[ $attribute->name ] = $attribute->value;
	}

	return $attributes;
};

$next_element = static function ( $node ) {
	do {
		$node = $node->nextSibling;
	} while ( $node && XML_ELEMENT_NODE !== $node->nodeType );

	return $node;
};

$entries_of = static function ( $html ) use ( $page_of, $has ) {
	$classes = array( 'wp-block-navigation-item__content', 'wpc-ask', 'wpc-viewer__link', 'wpc-viewer__logout' );
	$found   = array();

	foreach ( $page_of( $html )->query( '//*[@id="wpc-header-menu"]//*[' . implode( ' or ', array_map( $has, $classes ) ) . ']' ) as $node ) {
		$found[] = trim( preg_replace( '/\s+/', ' ', $node->textContent ) );
	}

	return $found;
};

$buttons_in = static function ( $html ) use ( $page_of, $has ) {
	return $page_of( $html )->query( '//button[' . $has( 'wpc-menu-toggle' ) . ']' )->length;
};

$hooked_priority_of = static function ( $hook, $function ) {
	foreach ( isset( $GLOBALS['hooks'][ $hook ] ) ? $GLOBALS['hooks'][ $hook ] : array() as $hooked ) {
		if ( $function === $hooked[0] ) {
			return $hooked[1];
		}
	}

	return null;
};

$printed_by = static function ( $function ) {
	ob_start();
	$function();

	return (string) ob_get_clean();
};

$three_with_submenu = array(
	array( 'Homepage', 'https://site.example/' ),
	array( 'About the program', 'https://site.example/about/' ),
	array(
		'Programs',
		'',
		array(
			array( 'WordPress Credits', 'https://site.example/credits/' ),
			array( 'Campus Connect', 'https://site.example/campus/' ),
		),
	),
);
$no_extras          = array(
	'help'    => false,
	'student' => false,
	'mentor'  => false,
);

ck( 'the page is marked as running JavaScript first thing in the head: `wpcredits_js_mark()` on `wp_head` at priority 1, printing one line',
	array( $hooked_priority_of( 'wp_head', 'wpcredits_js_mark' ), trim( $printed_by( 'wpcredits_js_mark' ) ) ),
	array( 1, "<script>document.documentElement.classList.add( 'wpc-js' );</script>" ) );

$GLOBALS['logged_in'] = true;
$GLOBALS['marked']    = true;

$admin   = wpcredits_test_header();
$out     = $menu( $admin );
$page    = $page_of( $out );
$button  = $page->query( '//button[' . $has( 'wpc-menu-toggle' ) . ']' )->item( 0 );
$panel   = $button ? $next_element( $button ) : null;
$icons   = $button ? $page->query( './/svg', $button ) : array();
$undone  = str_replace( array( wpcredits_header_menu_button(), ' id="wpc-header-menu"', ' wpc-header--menu' ), '', $out );
$svg_in  = array( 'class' => array(), 'aria-hidden' => array() );

foreach ( $icons as $icon ) {
	$svg_in['class'][]       = $icon->getAttribute( 'class' );
	$svg_in['aria-hidden'][] = $icon->getAttribute( 'aria-hidden' );
}

$GLOBALS['marked'] = false;

ck( 'a signed-in reader\'s header gets one Menu button, right before the group holding the links, which it names: the button, the panel\'s ID and the header\'s class are all the filter adds',
	array(
		$page->query( '//button[' . $has( 'wpc-menu-toggle' ) . ']' )->length,
		$button ? $attributes_of( $button ) : null,
		$panel ? array( $panel->getAttribute( 'id' ), (bool) $page->query( 'self::*[' . $has( 'wpc-nav__center' ) . ']', $panel )->length ) : null,
		(bool) $page->query( '//header[' . $has( 'wpc-header--menu' ) . ']' )->length,
		$undone === $admin,
	),
	array(
		1,
		array(
			'type'          => 'button',
			'class'         => 'wpc-menu-toggle',
			'aria-controls' => 'wpc-header-menu',
			'aria-expanded' => 'false',
			'aria-label'    => '[Menu]',
		),
		array( 'wpc-header-menu', true ),
		true,
		true,
	) );

ck( 'the button draws the menu icon and the close icon, both hidden from screen readers',
	array( $svg_in['class'], $svg_in['aria-hidden'] ),
	array( array( 'wpc-icon wpc-menu-toggle__open', 'wpc-icon wpc-menu-toggle__close' ), array( 'true', 'true' ) ) );

$chip_children = $page->query( '//*[@id="wpc-header-menu"]//div[' . $has( 'wpc-viewer' ) . ']/*' );
$chip_last     = $chip_children->length ? $chip_children->item( $chip_children->length - 1 )->getAttribute( 'class' ) : null;

ck( 'the links stay the same elements in the same order, inside the panel, the picture last in it; a navigation of three items, one a submenu of two, lists its items in order, the submenu\'s under its parent',
	array( $entries_of( $out ), $chip_last, $entries_of( $menu( wpcredits_test_header( array( 'nav' => $three_with_submenu ) ) ) ) ),
	array(
		array( 'Homepage', 'Need help?', 'Student Report Card', 'Mentor Report Card', 'Log out' ),
		'wpc-viewer__avatar',
		array( 'Homepage', 'About the program', 'Programs', 'WordPress Credits', 'Campus Connect', 'Need help?', 'Student Report Card', 'Mentor Report Card', 'Log out' ),
	) );

$log_out_alone                       = wpcredits_test_header( array_merge( $no_extras, array( 'nav' => array() ) ) );
$homepage_and_log_out                = wpcredits_test_header( $no_extras );
$three_with_submenu_no_help_no_cards = wpcredits_test_header( array_merge( $no_extras, array( 'nav' => $three_with_submenu ) ) );

ck( 'a header that would list one entry, Log out alone, is left as it is; Homepage and Log out are two and get the button; so does the navigation of three items with a submenu and nothing else',
	array( $menu( $log_out_alone ) === $log_out_alone, $buttons_in( $menu( $homepage_and_log_out ) ), $buttons_in( $menu( $three_with_submenu_no_help_no_cards ) ) ),
	array( true, 1, 1 ) );

$GLOBALS['logged_in'] = false;
$signed_out_kept      = $menu( $admin ) === $admin;
$GLOBALS['logged_in'] = true;

ck( 'signed out, and every other group, are left as they are: the inner group, the brand, the part, a group with no class, a class that holds the word only inside another',
	array(
		$signed_out_kept,
		$menu( $admin, 'wpc-header__inner' ) === $admin,
		$menu( $admin, 'wpc-brand' ) === $admin,
		$menu( $admin, 'wpc-header-part' ) === $admin,
		wpcredits_header_menu( $admin, array( 'blockName' => 'core/group', 'attrs' => array() ) ) === $admin,
		$menu( $admin, 'wpc-header-x' ) === $admin,
	),
	array( true, true, true, true, true, true ) );

$from_the_editor = str_replace( 'class="wp-block-group wpc-nav__center', 'id="from-the-editor" class="wp-block-group wpc-nav__center', $admin );
$replaced        = $menu( $from_the_editor );
$replaced_page   = $page_of( $replaced );
$replaced_button = $replaced_page->query( '//button[' . $has( 'wpc-menu-toggle' ) . ']' )->item( 0 );
$replaced_panel  = $replaced_button ? $next_element( $replaced_button ) : null;

ck( 'an ID the Site Editor gave the group is replaced by the one the button names, and the page holds it once',
	array(
		$replaced_panel ? $replaced_panel->getAttribute( 'id' ) : null,
		substr_count( $replaced, 'id="wpc-header-menu"' ),
		$replaced_button ? $replaced_button->getAttribute( 'aria-controls' ) : null,
		array( false !== strpos( $from_the_editor, 'from-the-editor' ), false === strpos( $replaced, 'from-the-editor' ) ),
	),
	array( 'wpc-header-menu', 1, 'wpc-header-menu', array( true, true ) ) );

$lone_and_home = static function ( array $items ) use ( $page_of, $has ) {
	$nav = $page_of( wpcredits_lone_nav_item( wpcredits_test_navigation( $items ) ) );

	return array(
		(bool) $nav->query( '//nav[' . $has( 'wpc-nav--lone' ) . ']' )->length,
		(bool) $nav->query( '//nav[' . $has( 'wpc-nav--home' ) . ']' )->length,
	);
};

ck( 'a navigation of one item is marked lone, and home too when its item leads to the home page, with or without the trailing slash; another lone item, and the three items with a submenu, are not marked home',
	array(
		$lone_and_home( array( array( 'Homepage', 'https://site.example/' ) ) ),
		$lone_and_home( array( array( 'Homepage', 'https://site.example' ) ) ),
		$lone_and_home( array( array( 'About', 'https://site.example/about/' ) ) ),
		$lone_and_home( $three_with_submenu ),
	),
	array( array( true, true ), array( true, true ), array( true, false ), array( false, false ) ) );

/**
 * The PHP files of the theme, below bin/, that hold a text, as paths below the theme's root. Folders
 * of other lines of development and of tools are skipped by the path below the root, so a linked
 * worktree's copy of the theme is never counted, nor a root that is itself one.
 *
 * @param string $needle The text.
 * @return string[] Sorted.
 */
$definitions_of = static function ( $needle ) {
	$root  = dirname( __DIR__ );
	$found = array();

	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		$below = substr( $file->getPathname(), strlen( $root ) );

		if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
			continue;
		}

		foreach ( array( '/.git/', '/.worktrees/', '/.superpowers/', '/node_modules/', '/bin/' ) as $skip ) {
			if ( false !== strpos( $below, $skip ) ) {
				continue 2;
			}
		}

		if ( false !== strpos( (string) file_get_contents( $file->getPathname() ), $needle ) ) {
			$found[] = ltrim( $below, '/' );
		}
	}

	sort( $found );

	return $found;
};

ck( 'functions.php requires inc/header.php right after inc/hub-content.php, and the logo fallback and the navigation filter are each written once, in inc/header.php',
	array(
		false !== strpos( theme_file( 'functions.php' ), "require_once get_theme_file_path( 'inc/hub-content.php' );\nrequire_once get_theme_file_path( 'inc/header.php' );" ),
		$definitions_of( 'function wpcredits_site_logo_fallback(' ),
		$definitions_of( 'function wpcredits_lone_nav_item(' ),
	),
	array( true, array( 'inc/header.php' ), array( 'inc/header.php' ) ) );

$logo_link_attributes = static function ( $html ) use ( $page_of, $has, $attributes_of ) {
	$page = $page_of( $html );
	$link = $page->query( '//a[' . $has( 'custom-logo-link' ) . ']' )->item( 0 );

	if ( ! $link ) {
		return array();
	}

	$svg = $page->query( './/svg', $link )->item( 0 );

	return $attributes_of( $link ) + array( 'svg aria-hidden' => $svg ? $svg->getAttribute( 'aria-hidden' ) : null );
};

$with_blogname = static function ( $name, $filter ) {
	$was                         = $GLOBALS['opts']['blogname'];
	$GLOBALS['opts']['blogname'] = $name;
	$html                        = $filter( '' );
	$GLOBALS['opts']['blogname'] = $was;

	return $html;
};

$core_logo = '<div class="wp-block-site-logo"><a href="https://site.example/" class="custom-logo-link" rel="home"><img width="38" height="38" src="https://site.example/logo.png" class="custom-logo" alt="WordPress Education Dashboard" decoding="async" /></a></div>';

$named = $logo_link_attributes( wpcredits_site_logo_fallback( '' ) );

$GLOBALS['marked'] = true;
$unnamed           = $logo_link_attributes( $with_blogname( '', 'wpcredits_site_logo_fallback' ) );
$GLOBALS['marked'] = false;

$GLOBALS['custom_logo'] = true;
$uploaded_kept          = wpcredits_site_logo_fallback( $core_logo ) === $core_logo;
$GLOBALS['custom_logo'] = false;

ck( 'the logo link is named with the site\'s name, or "Home" on a site with none, its mark still hidden from screen readers; an uploaded logo is left as core prints it',
	array( $named, isset( $unnamed['aria-label'] ) ? $unnamed['aria-label'] : null, $uploaded_kept ),
	array(
		array(
			'class'           => 'custom-logo-link',
			'href'            => 'https://site.example/',
			'rel'             => 'home',
			'aria-label'      => 'WordPress Education Dashboard',
			'svg aria-hidden' => 'true',
		),
		'[Home]',
		true,
	) );

/**
 * The classes among those given that a PHP file of the theme does not print in its code, read with the
 * tokenizer without its comments, as whole class names.
 *
 * @param array $files File below the theme root => the classes it prints.
 * @return string[] Each as "file: class".
 */
$classes_missing_from = static function ( array $files ) {
	$missing = array();

	foreach ( $files as $file => $classes ) {
		$code = '';

		foreach ( token_get_all( theme_file( $file ) ) as $token ) {
			if ( ! is_array( $token ) ) {
				$code .= $token;
			} elseif ( ! in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				$code .= $token[1];
			}
		}

		foreach ( $classes as $class ) {
			if ( ! preg_match( '/(?<![A-Za-z0-9_-])' . preg_quote( $class, '/' ) . '(?![A-Za-z0-9_-])/', $code ) ) {
				$missing[] = $file . ': ' . $class;
			}
		}
	}

	return $missing;
};

ck( 'the fixture prints the classes the blocks print: each of wpc-ask, wpc-viewer__link, wpc-viewer__logout, wpc-viewer__avatar and wpc-viewer__signin is in its render.php, read without its comments',
	$classes_missing_from(
		array(
			'blocks/handbook-launcher/render.php' => array( 'wpc-ask' ),
			'blocks/viewer-chip/render.php'       => array( 'wpc-viewer__link', 'wpc-viewer__logout', 'wpc-viewer__avatar', 'wpc-viewer__signin' ),
		)
	),
	array() );

echo "\n=== The menu layout ===\n";

$menu_at   = '@media screen and ( max-width: 1199px )';
$forced_at = '@media screen and ( max-width: 1199px ) and ( forced-colors: active )';
$phone_at  = '@media ( max-width: 640px )';
$m         = '.wpc-js .wpc-header--menu';

/**
 * The rows among those given that are not in the sheet exactly once, in the at-rule given, with every
 * declaration pinned for them.
 *
 * @param array[] $rows Selector list as the sheet writes it => the declarations it must hold.
 * @param string  $at   The at-rule the rules sit in, `''` for none.
 * @return string[] Selector list => what is wrong; empty when all is well.
 */
$unpinned = static function ( array $rows, $at = '' ) {
	$problems = array();

	foreach ( $rows as $selector => $pinned ) {
		$found = rules_of( $selector, $at );

		if ( 1 !== count( $found ) ) {
			$problems[ $selector ] = count( $found ) . ' rules';
		} elseif ( array_diff( $pinned, $found[0] ) ) {
			$problems[ $selector ] = 'missing ' . implode( ' ', array_diff( $pinned, $found[0] ) );
		}
	}

	return $problems;
};

/**
 * The selectors of a selector list, split at the commas outside parentheses and brackets.
 *
 * @param string $list The selector list.
 * @return string[]
 */
$selectors_of = static function ( $list ) {
	$found   = array();
	$depth   = 0;
	$current = '';

	foreach ( str_split( $list ) as $char ) {
		if ( '(' === $char || '[' === $char ) {
			++$depth;
		} elseif ( ')' === $char || ']' === $char ) {
			--$depth;
		}

		if ( ',' === $char && 0 === $depth ) {
			$found[] = trim( $current );
			$current = '';
			continue;
		}

		$current .= $char;
	}

	$found[] = trim( $current );

	return $found;
};

/**
 * What a selector weighs against another: its classes, attribute selectors and pseudo-classes. The
 * argument of `:not()` is counted and `:not` itself is not; type selectors and pseudo-elements are not.
 *
 * @param string $selector One selector.
 * @return int
 */
$specificity = static function ( $selector ) {
	$attributes = preg_match_all( '/\[[^\]]*\]/', $selector );
	$selector   = preg_replace( array( '/\[[^\]]*\]/', '/::[a-z-]+/', '/:not\(/' ), array( '', '', '(' ), $selector );

	return $attributes + preg_match_all( '/\.[A-Za-z_-][\w-]*/', $selector ) + preg_match_all( '/:[a-z-]+/', $selector );
};

$index_of_rule = static function ( $selector, $at ) {
	foreach ( sheet_rules() as $index => $rule ) {
		if ( $rule['selector'] === $selector && $rule['at'] === $at ) {
			return $index;
		}
	}

	return -1;
};

$names_any = static function ( $text, array $names ) {
	foreach ( $names as $name ) {
		if ( false !== strpos( $text, $name ) ) {
			return true;
		}
	}

	return false;
};

$index_of_last_rule_naming = static function ( array $names, array $except ) use ( $names_any ) {
	$last = -1;

	foreach ( sheet_rules() as $index => $rule ) {
		if ( $names_any( $rule['selector'], $names ) && ! $names_any( $rule['selector'], $except ) ) {
			$last = $index;
		}
	}

	return $last;
};

$index_of_first_rule_naming = static function ( $name ) {
	foreach ( sheet_rules() as $index => $rule ) {
		if ( false !== strpos( $rule['selector'], $name ) ) {
			return $index;
		}
	}

	return -1;
};

$deepest_among = static function ( $needle, $in_menu ) use ( $selectors_of, $specificity ) {
	$deepest = 0;

	foreach ( sheet_rules() as $rule ) {
		foreach ( $selectors_of( $rule['selector'] ) as $selector ) {
			if ( false !== strpos( $selector, $needle ) && ( false !== strpos( $selector, 'wpc-header--menu' ) ) === $in_menu ) {
				$deepest = max( $deepest, $specificity( $selector ) );
			}
		}
	}

	return $deepest;
};

$deepest_outside_menu         = static function ( $needle ) use ( $deepest_among ) {
	return $deepest_among( $needle, false );
};
$deepest_menu_selector_naming = static function ( $needle ) use ( $deepest_among ) {
	return $deepest_among( $needle, true );
};

$selectors_declaring_under = static function ( $class, $declaration ) {
	$found = array();

	foreach ( sheet_rules() as $rule ) {
		if ( false !== strpos( $rule['selector'], $class ) && in_array( $declaration, $rule['declarations'], true ) ) {
			$found[] = $rule['selector'];
		}
	}

	return $found;
};

$at_rules_of_rules_naming = static function ( array $names ) use ( $names_any ) {
	$found = array();

	foreach ( sheet_rules() as $rule ) {
		if ( $names_any( $rule['selector'], $names ) && ! in_array( $rule['at'], $found, true ) ) {
			$found[] = $rule['at'];
		}
	}

	return $found;
};

$selectors_not_starting_with = static function ( $prefix ) use ( $names_any, $selectors_of ) {
	$found = array();

	foreach ( sheet_rules() as $rule ) {
		if ( ! $names_any( $rule['selector'], array( 'wpc-header--menu', 'wpc-header--open' ) ) ) {
			continue;
		}

		foreach ( $selectors_of( $rule['selector'] ) as $selector ) {
			if ( 0 !== strpos( $selector, $prefix ) ) {
				$found[] = $selector;
			}
		}
	}

	return $found;
};

$panel_height = 'calc( 100vh - var( --wpc-header-live, var( --wpc-header-height ) ) - var( --wp-admin--admin-bar--height, 0px ) - var( --wp-global-header-height, 0px ) - var( --wp--style--block-gap, 0px ) )';
$panel_lines  = rules_of( $m . ' .wpc-nav__center', $menu_at )[0] ?? array();

$row_selectors = array(
	$m . ' .wpc-nav .wp-block-navigation-item__content',
	$m . ' .wpc-nav .has-child .wp-block-navigation__submenu-container > .wp-block-navigation-item > .wp-block-navigation-item__content',
	$m . ' .wpc-nav__center .wpc-ask',
	$m . ' .wpc-viewer .wpc-viewer__link',
	$m . ' .wpc-viewer .wpc-viewer__logout',
);
$row_states    = implode(
	', ',
	array_merge(
		array_map( static function ( $selector ) { return $selector . ':hover'; }, $row_selectors ),
		array_map( static function ( $selector ) { return $selector . ':focus-visible'; }, $row_selectors )
	)
);

ck( 'the Menu button takes the navigation toggle\'s rule, 44px square and not displayed until the menu layout shows it, and its two icons follow its state: the menu icon until it is expanded, the close icon after; each rule is in the sheet once, outside any query',
	$unpinned(
		array(
			'.wpc-header .wpc-nav .wp-block-navigation__responsive-container-open:not( .always-shown ), .wpc-header .wpc-menu-toggle' => array( 'display: none;', 'height: 44px;', 'width: 44px;' ),
			'.wpc-menu-toggle .wpc-menu-toggle__close, .wpc-menu-toggle[aria-expanded="true"] .wpc-menu-toggle__open' => array( 'display: none;' ),
			'.wpc-menu-toggle[aria-expanded="true"] .wpc-menu-toggle__close' => array( 'display: block;' ),
		)
	),
	array() );

ck( 'below 1200px the signed-in bar is one row: the header is positioned and transformed, so it is the containing block of the panel and of the fixed picture; the inner group does not wrap and gaps by 12px; the brand keeps 46px clear for the picture, may shrink and wraps its title; the Menu button is shown at the end of the row and tinted while open; each rule once, in the screen query',
	$unpinned(
		array(
			$m => array( 'position: relative;', 'transform: translateX( 0 );' ),
			$m . ' .wpc-header__inner' => array( 'column-gap: 12px;', 'flex-wrap: nowrap;' ),
			$m . ' .wpc-brand' => array( 'flex: 0 1 auto;', 'gap: 12px;', 'margin-right: calc( 34px + 12px );', 'min-width: 0;' ),
			$m . ' .wpc-brand__name' => array( 'min-width: 0;', 'overflow-wrap: anywhere;', 'white-space: normal;' ),
			$m . ' .wpc-menu-toggle' => array( 'display: flex;', 'flex: none;', 'margin-left: auto;' ),
			$m . ' .wpc-menu-toggle[aria-expanded="true"]' => array(),
		),
		$menu_at
	),
	array() );

ck( 'the panel is the group holding the links: a block placed under the bar, as wide as it, scrolling inside itself and capped at the window\'s height less all that lies above it at the top of a page, the bar, the admin bar, the WordPress.org bar and the block gap under it (100vh, then 100svh after it), handing a scroll it cannot take to the page (`overscroll-behavior: auto`, its one overscroll line), hidden by visibility and shown by the open header; each rule once, in the screen query',
	array(
		$unpinned(
			array(
				$m . ' .wpc-nav__center' => array(
					'display: block;',
					'left: 0;',
					'max-height: ' . $panel_height . ';',
					'max-height: ' . str_replace( '100vh', '100svh', $panel_height ) . ';',
					'overflow-y: auto;',
					'position: absolute;',
					'right: 0;',
					'top: calc( 100% + 1px );',
					'visibility: hidden;',
					'z-index: 55;',
				),
				'.wpc-js .wpc-header--menu.wpc-header--open .wpc-nav__center' => array( 'visibility: visible;' ),
			),
			$menu_at
		),
		array_search( 'max-height: ' . $panel_height . ';', $panel_lines, true ) < array_search( 'max-height: ' . str_replace( '100vh', '100svh', $panel_height ) . ';', $panel_lines, true ),
		array_values( preg_grep( '/^overscroll-behavior/', $panel_lines ) ),
	),
	array( array(), true, array( 'overscroll-behavior: auto;' ) ) );

ck( 'the account picture is drawn in the bar: fixed against the header at its middle, 12px left of the Menu button, and shown although the closed panel it sits in is hidden; the chip around it is a block; each rule once, in the screen query',
	$unpinned(
		array(
			$m . ' .wpc-viewer__avatar' => array( 'margin-top: -17px;', 'position: fixed;', 'right: calc( var( --wpc-chrome-gutter ) + 44px + 12px );', 'top: 50%;', 'visibility: visible;' ),
			$m . ' .wpc-viewer' => array( 'display: block;' ),
		),
		$menu_at
	),
	array() );

ck( 'inside the panel the navigation is a plain block list: its own toggle and close button are never displayed, the toggle not even when the navigation is set to always show it, its container, dialog and content stay in the flow, its list and items are blocks, a submenu is listed expanded and indented 12px, and its icon is not displayed; each rule once, in the screen query',
	$unpinned(
		array(
			$m . ' .wpc-nav.wp-block-navigation' => array( 'display: block;', 'position: static;' ),
			$m . ' .wpc-nav .wp-block-navigation__responsive-container-open, ' . $m . ' .wpc-nav .wp-block-navigation__responsive-container-open:not( .always-shown ), ' . $m . ' .wpc-nav .wp-block-navigation__responsive-container-close' => array( 'display: none;' ),
			$m . ' .wpc-nav .wp-block-navigation__responsive-container:not( .is-menu-open )' => array( 'display: block;', 'height: auto;', 'padding: 0;', 'position: static;', 'width: auto;' ),
			$m . ' .wpc-nav .wp-block-navigation__responsive-container:not( .is-menu-open ) .wp-block-navigation__responsive-dialog, ' . $m . ' .wpc-nav .wp-block-navigation__responsive-container:not( .is-menu-open ) .wp-block-navigation__responsive-container-content' => array( 'display: block;', 'padding: 0;', 'position: static;' ),
			$m . ' .wpc-nav .wp-block-navigation__container, ' . $m . ' .wpc-nav .wp-block-navigation-item' => array( 'display: block;', 'width: auto;' ),
			$m . ' .wpc-nav .has-child .wp-block-navigation-submenu__toggle[aria-expanded] ~ .wp-block-navigation__submenu-container, ' . $m . ' .wpc-nav .has-child .wp-block-navigation__submenu-container' => array( 'display: block;', 'height: auto;', 'min-width: 0;', 'opacity: 1;', 'overflow: visible;', 'padding: 0 0 0 12px;', 'position: static;', 'visibility: inherit;', 'width: auto;' ),
			$m . ' .wpc-nav .wp-block-navigation__submenu-icon' => array( 'display: none;' ),
		),
		$menu_at
	),
	array() );

ck( 'every entry is a 44px row (15px type on a 20px line with 12px above and below, a hairline under it, the full width), whatever block prints it; hover and focus turn it brand blue; Log out is last, so it has no hairline; the page being viewed is brand blue; each rule once, in the screen query',
	$unpinned(
		array(
			implode( ', ', $row_selectors ) => array( 'border-bottom: 1px solid var( --wpc-chrome-rule );', 'display: block;', 'font-size: 15px;', 'line-height: 20px;', 'padding: 12px 0;', 'width: 100%;' ),
			$row_states => array(),
			$m . ' .wpc-viewer .wpc-viewer__logout' => array( 'border-bottom: 0;' ),
			$m . ' .wpc-nav [aria-current="page"], ' . $m . ' .wpc-viewer .wpc-viewer__link.is-current' => array(),
		),
		$menu_at
	),
	array() );

ck( 'in forced colors, where the page being viewed loses its color, the menu underlines it: one rule in the forced-colors twin of the screen query',
	$unpinned( array( $m . ' .wpc-nav__center [aria-current="page"]' => array( 'text-decoration: underline;' ) ), $forced_at ),
	array() );

$signed_out_scope = ':has( .wpc-nav--home )';
$signed_out_inner = 'body:not( .wpc-signed-in ) .wpc-header__inner' . $signed_out_scope;
$signed_out_hider = 'body:not( .wpc-signed-in ) .wpc-header .wpc-nav--home';
$signed_out_loose = array();

foreach ( sheet_rules() as $rule ) {
	foreach ( $selectors_of( $rule['selector'] ) as $selector ) {
		if ( false !== strpos( $selector, 'wpc-signed-in' ) && false === strpos( $selector, $signed_out_scope ) && $signed_out_hider !== $selector ) {
			$signed_out_loose[] = $selector;
		}
	}
}

ck( 'signed out, a phone is one row only where its lone navigation link, the home page, is left out: the bar does not wrap and gaps by 12px, the brand takes the room and gaps by 12px, the group is as wide as the button, and the navigation of one link home is not displayed; each rule once, in the 640px query, and every rule of the row but the one that leaves the link out is scoped to a bar holding `.wpc-nav--home` with `:has()`, so any other lone link, or a browser without `:has()`, keeps the two rows',
	array(
		$unpinned(
			array(
				$signed_out_inner                        => array( 'column-gap: 12px;', 'flex-wrap: nowrap;' ),
				$signed_out_inner . ' .wpc-brand'        => array( 'flex: 1 1 auto;', 'gap: 12px;' ),
				$signed_out_inner . ' .wpc-nav__center'  => array( 'flex: none;' ),
				$signed_out_hider                        => array( 'display: none;' ),
			),
			$phone_at
		),
		$signed_out_loose,
	),
	array( array(), array() ) );

ck( 'on a phone the Log in button is 14px with 9px 14px padding, as its 640px rule always said: the rule is two classes deep, as the base rule is, and comes after it; no one-class version is left',
	array( rules_of( '.wpc-header .wpc-viewer__signin, .wpc-header .wpc-viewer--guest a', '@media ( max-width: 640px )' ), $index_of_rule( '.wpc-header .wpc-viewer__signin, .wpc-header .wpc-viewer--guest a', '@media ( max-width: 640px )' ) > $index_of_rule( '.wpc-header .wpc-viewer__signin, .wpc-header .wpc-viewer--guest a', '' ), rules_anywhere( '.wpc-viewer__signin, .wpc-viewer--guest a' ) ),
	array( array( array( 'font-size: 14px;', 'padding: 9px 14px;' ) ), true, 0 ) );

ck( 'nothing in the panel but the picture and the open panel itself is made visible, so nothing shows through the closed menu',
	$selectors_declaring_under( 'wpc-header--menu', 'visibility: visible;' ),
	array( '.wpc-js .wpc-header--menu.wpc-header--open .wpc-nav__center', '.wpc-js .wpc-header--menu .wpc-viewer__avatar' ) );

ck( 'the menu\'s submenu rules are in the sheet and outrank every rule outside the menu layout that reveals or restyles a submenu: deeper than each (6 today), so the closed panel stays closed after a submenu toggle is pressed, and a submenu row keeps the 44px row',
	array( $deepest_menu_selector_naming( '.wp-block-navigation__submenu-container' ) > $deepest_outside_menu( '.wp-block-navigation__submenu-container' ), $deepest_menu_selector_naming( '.wp-block-navigation__submenu-container > .wp-block-navigation-item > .wp-block-navigation-item__content' ) > $deepest_outside_menu( '.wp-block-navigation__submenu-container > .wp-block-navigation-item > .wp-block-navigation-item__content' ) ),
	array( true, true ) );

ck( 'every rule of the menu layout is inside the screen query below 1200px, or its forced-colors twin, and each of its selectors starts with `.wpc-js .wpc-header--menu`, so without JavaScript, signed out, in print and from 1200px nothing of it applies',
	array( $at_rules_of_rules_naming( array( 'wpc-header--menu', 'wpc-header--open' ) ), $selectors_not_starting_with( '.wpc-js .wpc-header--menu' ) ),
	array( array( '@media screen and ( max-width: 1199px )', '@media screen and ( max-width: 1199px ) and ( forced-colors: active )' ), array() ) );

ck( 'the menu layout comes after every header rule it restates, the 640px, 960px and stacked blocks among them, so a tie of specificity goes its way',
	$index_of_first_rule_naming( 'wpc-header--menu' ) > $index_of_last_rule_naming( array( '.wpc-header--stacked', 'wpc-nav--lone', '.wpc-viewer__logout', '.wpc-nav__center' ), array( 'wpc-header--menu', 'wpc-signed-in' ) ),
	true );

echo "\n=== The menu script ===\n";

/**
 * The scripts the callbacks hooked on `wp_enqueue_scripts` enqueue, run in priority order through
 * `$GLOBALS['hooks']` for a reader signed in or out; the globals they read and write are put back.
 *
 * @param bool $logged_in Whether the reader is signed in.
 * @return array Handle => `array( src, deps, ver, args )`.
 */
$scripts_enqueued_for = static function ( $logged_in ) {
	$was       = array( $GLOBALS['logged_in'], $GLOBALS['scripts'] );
	$callbacks = isset( $GLOBALS['hooks']['wp_enqueue_scripts'] ) ? $GLOBALS['hooks']['wp_enqueue_scripts'] : array();

	usort( $callbacks, static function ( $a, $b ) { return $a[1] - $b[1]; } );

	$GLOBALS['logged_in'] = $logged_in;
	$GLOBALS['scripts']   = array();

	foreach ( $callbacks as $callback ) {
		call_user_func( $callback[0] );
	}

	$scripts = $GLOBALS['scripts'];

	list( $GLOBALS['logged_in'], $GLOBALS['scripts'] ) = $was;

	return $scripts;
};

$scripts_when_signed_in  = $scripts_enqueued_for( true );
$scripts_when_signed_out = $scripts_enqueued_for( false );

ck( 'the menu script is enqueued for a signed-in reader alone: deferred, no dependency, the theme\'s version',
	array( $scripts_when_signed_in['wpcredits-header-menu'] ?? null, $scripts_when_signed_out ),
	array( array( 'https://site.example/wp-content/themes/wpcredits-theme/assets/js/header-menu.js', array(), '9.9.9', array( 'strategy' => 'defer' ) ), array() ) );

ck( 'the handbook launcher\'s comment names the class the header\'s menu script reads', false !== strpos( theme_file( 'blocks/handbook-launcher/render.php' ), 'wpcpm-hb-open' ), true );

$GLOBALS['logged_in'] = false;

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
