<?php
/**
 * WordPress, as far as the Education Hub's and the site header's files reach, stood in for their suites.
 *
 * bin/test-hub.php renders the hub's template rule, content, blocks and patterns with these, and
 * bin/test-hub-js.php renders the two blocks' markup with them for its scenarios. bin/test-header.php
 * runs the header's filters on them, over the markup bin/stubs/header-markup.php prints, and
 * bin/test-partners.php draws the Partners page, its levels read from the template in use. Nothing here
 * talks to a database or the network: every answer comes from the globals below, which a check sets.
 *
 * - `$GLOBALS['opts']`: option => value; `blogname` is what `get_bloginfo( 'name' )` answers.
 * - `$GLOBALS['option_reads']`: the key of every `get_option()` call, in order, so a check can tell
 *   that a request read an option, or did not.
 * - `$GLOBALS['request']`: `singular` (the post type a singular request shows, or ''), `id` (the
 *   queried object's ID) and `templates`, page ID => the page template slug it stores.
 * - `$GLOBALS['marked']`: when true, every translated string comes back in brackets, `[like this]`,
 *   so a check can tell a translated text from one that was printed as written.
 * - `$GLOBALS['is_admin']`: when true, `is_admin()` answers true, as it does on the editor's requests;
 *   false, the front end, unless a check sets it.
 * - `$GLOBALS['transients']`: key => value, what `get_transient()` reads and `set_transient()` writes,
 *   so a check can see what the program figure block cached.
 * - `$GLOBALS['role_count']`: role => how many accounts hold it, what `WP_User_Query::get_total()`
 *   answers for the program figure block's counts.
 * - `$GLOBALS['said']`: how many translated strings have been looked up so far (`__()`, `_x()` and
 *   `_n()` each add one), so a check can tell that a render translated nothing.
 * - `$GLOBALS['translated']`: text => the translation a lookup of that text comes back as (before the
 *   brackets of `$GLOBALS['marked']`), so a check can see that a translated string is escaped where
 *   it is printed. A string with a context (`_x()`) is looked up first as "context", an end-of-
 *   transmission character (gettext's separator) and the text, then as the text alone.
 * - `$GLOBALS['logged_in']`: when true, `is_user_logged_in()` answers true, as it does for a signed-in
 *   reader; false, a visitor who is signed out, unless a check sets it. `wp_login_url()` answers with
 *   the login address and the address to return to, then runs the `login_url` filter as core does.
 * - `$GLOBALS['custom_logo']`: when true, `has_custom_logo()` answers true, as it does once a logo is
 *   uploaded; false, a site that has none, unless a check sets it.
 * - `$GLOBALS['hooks']`, `$GLOBALS['styles']`, `$GLOBALS['enqueued']`, `$GLOBALS['editor_styles']`,
 *   `$GLOBALS['scripts']` and `$GLOBALS['blocks']`: what the theme hooked, registered and enqueued;
 *   `hooks` is hook => a list of `array( callback, priority, accepted arguments )`, one per
 *   `add_action()` or `add_filter()` call, and `scripts` is handle => `array( src, deps, ver, args )`.
 * - `$GLOBALS['block_templates']`: template ID (`wpcredits-theme//page-partners`) => the copy saved in
 *   the Site Editor that get_block_template() answers with, as wpcredits_test_saved_template() puts it
 *   there; a template with none is the theme's own file. A template part's saved copy is kept under
 *   `part:` and its ID (`part:wpcredits-theme//header`), and the theme's own file for a part is in
 *   parts/. `$GLOBALS['template_reads']` counts the get_block_template() calls for a template, and
 *   `$GLOBALS['part_reads']` those for a template part, so a check can tell how often a request read
 *   either.
 *
 *
 * Blocks (theme 1.32.0, whose hub blocks hold inner blocks): parse_blocks() is core's parser ported,
 * serialize_block() and get_comment_delimited_block_content() core's serializer as written, and
 * render_block() and do_blocks() render through a WP_Block that holds its inner blocks, fills in the
 * defaults of the theme's block.json files and runs a theme block's render.php after its inner blocks,
 * as core does. Core's blocks are not registered here, so they render as their saved markup; block
 * supports' filters and attribute validation are not modeled, but for core's block visibility, which a
 * suite hooks on `render_block` when it asks for it (wpcredits_test_block_visibility(), theme 1.32.1).
 * Each is described where it is defined.
 *
 * Loaded with `require_once __DIR__ . '/stubs/hub-wp.php';` from a suite's header, before the theme's
 * files.
 *
 * @package WPCredits_Theme
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'WPCREDITS_THEME_DIR', dirname( __DIR__, 2 ) . '/' );
define( 'WPCREDITS_VERSION', '9.9.9' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']          = array(
	'blogname'    => 'WordPress Education Dashboard',
	'date_format' => 'F j, Y',
	'timezone'    => 'Europe/Warsaw',
);
$GLOBALS['request']       = array(
	'singular'  => '',
	'id'        => 0,
	'templates' => array(),
);
$GLOBALS['marked']        = false;
$GLOBALS['translated']    = array();
$GLOBALS['logged_in']     = false;
$GLOBALS['said']          = 0;
$GLOBALS['is_admin']      = false;
$GLOBALS['hooks']         = array();
$GLOBALS['styles']        = array();
$GLOBALS['enqueued']      = array();
$GLOBALS['scripts']       = array();
$GLOBALS['custom_logo']   = false;
$GLOBALS['editor_styles'] = array();
$GLOBALS['blocks']        = array();
$GLOBALS['rendering']     = '';

$GLOBALS['block_templates'] = array();
$GLOBALS['template_reads']  = 0;
$GLOBALS['part_reads']      = 0;

/* ---- translation and escaping ------------------------------------------------- */

/**
 * A translated string: as written, or as `$GLOBALS['translated']` has it, and in brackets while
 * `$GLOBALS['marked']` is on. Each lookup is counted in `$GLOBALS['said']`. A string with a context is
 * a different string from the same text with another, or none, as in gettext: the context's own
 * translation is taken when there is one.
 *
 * @param string $text    The string.
 * @param string $context Its context, or '' for none.
 * @return string
 */
function wpcredits_test_said( $text, $context = '' ) {
	++$GLOBALS['said'];

	if ( '' !== $context && isset( $GLOBALS['translated'][ $context . "\4" . $text ] ) ) {
		$text = $GLOBALS['translated'][ $context . "\4" . $text ];
	} elseif ( isset( $GLOBALS['translated'][ $text ] ) ) {
		$text = $GLOBALS['translated'][ $text ];
	}

	return $GLOBALS['marked'] ? '[' . $text . ']' : $text;
}
function __( $text, $domain = null ) { return wpcredits_test_said( $text ); }
function _x( $text, $context, $domain = null ) { return wpcredits_test_said( $text, $context ); }
function _n( $single, $plural, $number, $domain = null ) { return wpcredits_test_said( 1 === (int) $number ? $single : $plural ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
function esc_html__( $text, $domain = null ) { return esc_html( __( $text ) ); }
function esc_attr__( $text, $domain = null ) { return esc_attr( __( $text ) ); }
function esc_html_x( $text, $context, $domain = null ) { return esc_html( _x( $text, $context ) ); }
function esc_html_e( $text, $domain = null ) { echo esc_html__( $text ); }
function esc_attr_e( $text, $domain = null ) { echo esc_attr__( $text ); }
function esc_url( $url ) { return str_replace( array( '&', "'", '"' ), array( '&#038;', '&#039;', '&quot;' ), (string) $url ); }
function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, $decimals ); }

/**
 * Core's wp_kses(), as far as the tags go: every tag the allowed list names is kept, every other taken
 * out. The attributes a tag may carry are not modeled.
 *
 * @param string $html    The markup.
 * @param array  $allowed The tags allowed, as core's list has them: tag => its attributes.
 * @return string
 */
function wp_kses( $html, $allowed ) {
	$tags = is_array( $allowed ) ? array_keys( $allowed ) : array();

	return strip_tags( (string) $html, $tags ? '<' . implode( '><', $tags ) . '>' : '' );
}

/**
 * Core's wp_strip_all_tags(): the tags out, the script and style elements with their contents, the
 * text trimmed.
 *
 * @param string $text          The text.
 * @param bool   $remove_breaks Whether runs of whitespace become one space.
 * @return string
 */
function wp_strip_all_tags( $text, $remove_breaks = false ) {
	$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
	$text = strip_tags( $text );

	if ( $remove_breaks ) {
		$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
	}

	return trim( $text );
}

/* ---- options, dates and the request ------------------------------------------------ */

function get_option( $key, $default = false ) {
	$GLOBALS['option_reads'][] = $key;

	// A suite that models this request's object cache of options (bin/stubs/migrate-wp.php) reads through it.
	if ( ! empty( $GLOBALS['option_cache'] ) && function_exists( 'wpcredits_test_cached_option' ) ) {
		return wpcredits_test_cached_option( $key, $default );
	}

	return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default;
}
function get_bloginfo( $show = '', $filter = 'raw' ) { return 'name' === $show ? (string) get_option( 'blogname', '' ) : ''; }
function home_url( $path = '' ) { return 'https://site.example' . $path; }
function untrailingslashit( $value ) { return rtrim( (string) $value, '/\\' ); }
function has_custom_logo() { return ! empty( $GLOBALS['custom_logo'] ); }
function wp_timezone() { return new DateTimeZone( (string) get_option( 'timezone', 'UTC' ) ); }
function wp_date( $format, $timestamp = null, $timezone = null ) {
	$at = new DateTimeImmutable( '@' . ( null === $timestamp ? time() : (int) $timestamp ) );

	return $at->setTimezone( $timezone instanceof DateTimeZone ? $timezone : wp_timezone() )->format( $format );
}
function is_admin() { return ! empty( $GLOBALS['is_admin'] ); }
function is_singular( $type = '' ) {
	$shown = (string) $GLOBALS['request']['singular'];

	return '' !== $shown && ( '' === $type || $type === $shown );
}
function get_queried_object_id() { return (int) $GLOBALS['request']['id']; }
function get_page_template_slug( $post = null ) {
	$id = (int) $post;

	return isset( $GLOBALS['request']['templates'][ $id ] ) ? (string) $GLOBALS['request']['templates'][ $id ] : '';
}
function is_user_logged_in() { return ! empty( $GLOBALS['logged_in'] ); }

/**
 * Core's wp_login_url(): the login page with the address to return to, percent-encoded, passed
 * through the `login_url` filter, which a plugin can use to change the address.
 *
 * @param string $redirect The address to return to after signing in.
 * @return string
 */
function wp_login_url( $redirect = '' ) {
	return apply_filters( 'login_url', 'https://site.example/wp-login.php?redirect_to=' . rawurlencode( (string) $redirect ), $redirect );
}

/* ---- hooks, styles and blocks ----------------------------------------------------- */

function add_action( $hook, $callback, $priority = 10, $accepted = 1 ) { $GLOBALS['hooks'][ $hook ][] = array( $callback, $priority, $accepted ); return true; }
function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ) { return add_action( $hook, $callback, $priority, $accepted ); }

/**
 * Take one callback off a hook, the same callback at the same priority, as WordPress does.
 *
 * @param string   $hook     The hook.
 * @param callable $callback The callback added.
 * @param int      $priority The priority it was added at.
 * @return bool Whether it was hooked.
 */
function remove_filter( $hook, $callback, $priority = 10 ) {
	foreach ( isset( $GLOBALS['hooks'][ $hook ] ) ? $GLOBALS['hooks'][ $hook ] : array() as $at => $hooked ) {
		if ( $hooked[0] === $callback && $hooked[1] === $priority ) {
			unset( $GLOBALS['hooks'][ $hook ][ $at ] );

			return true;
		}
	}

	return false;
}

/**
 * Run a filter's callbacks in priority order, as WordPress does.
 *
 * @param string $hook  The filter.
 * @param mixed  $value The value.
 * @param mixed  ...$more More arguments.
 * @return mixed
 */
function apply_filters( $hook, $value, ...$more ) {
	$callbacks = isset( $GLOBALS['hooks'][ $hook ] ) ? $GLOBALS['hooks'][ $hook ] : array();

	usort( $callbacks, static function ( $a, $b ) { return $a[1] - $b[1]; } );

	foreach ( $callbacks as $callback ) {
		$value = call_user_func( $callback[0], $value, ...$more );
	}

	return $value;
}
function wp_register_style( $handle, $src, $deps = array(), $ver = false ) { $GLOBALS['styles'][ $handle ] = array( $src, $deps, $ver ); return true; }
function wp_enqueue_style( $handle ) { $GLOBALS['enqueued'][] = $handle; }
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = array() ) { $GLOBALS['scripts'][ $handle ] = array( $src, $deps, $ver, $args ); }
function wp_print_inline_script_tag( $data ) { echo '<script>' . $data . '</script>' . "\n"; }
function add_editor_style( $stylesheet ) { $GLOBALS['editor_styles'][] = $stylesheet; }
function get_theme_file_uri( $file = '' ) { return 'https://site.example/wp-content/themes/wpcredits-theme/' . $file; }
function get_theme_file_path( $file = '' ) { return WPCREDITS_THEME_DIR . $file; }
function register_block_type( $path, $args = array() ) { $GLOBALS['blocks'][] = $path; return true; }

/**
 * The attributes core prints on a block's wrapper: the extra classes first, then the block's own
 * `wp-block-` class, as core joins them (a real render prints `wpc-hub-index wp-block-wpcredits-program-index`).
 *
 * @param array $extra Extra attributes, `class` among them.
 * @return string
 */
function get_block_wrapper_attributes( $extra = array() ) {
	$class = trim( ( isset( $extra['class'] ) ? $extra['class'] : '' ) . ' wp-block-' . str_replace( '/', '-', $GLOBALS['rendering'] ) );
	$out   = 'class="' . esc_attr( $class ) . '"';

	foreach ( $extra as $name => $value ) {
		if ( 'class' !== $name ) {
			$out .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}
	}

	return $out;
}

/* ---- the program's counts, for the program figure block -------------------------- */

$GLOBALS['transients'] = array();
$GLOBALS['role_count'] = array(); // Role => how many accounts hold it.

function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function get_transient( $key ) { return array_key_exists( $key, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $key ] : false; }
function set_transient( $key, $value, $ttl = 0 ) { $GLOBALS['transients'][ $key ] = $value; return true; }

/** Core's user query, as far as a count of one role reaches. */
class WP_User_Query {
	private $role;
	public function __construct( $args ) { $this->role = $args['role']; }
	public function get_total() { return isset( $GLOBALS['role_count'][ $this->role ] ) ? (int) $GLOBALS['role_count'][ $this->role ] : 0; }
}

/* ---- the HTML API, as far as the hub's and the header's filters reach --------------- */

/**
 * Core's tag processor, for the walks the theme makes: on from the tag last found to the next opening
 * tag of a name, a class or both, then its classes and attributes read and set. Attribute values are
 * written as core writes them, double-quoted and escaped; an attribute already on the tag is replaced
 * where it stands, and a new one is added at the end of the tag (core adds it right after the tag's
 * name, so a reader of the result finds the tag by the attribute, not by its end). Attributes are read
 * in any quoting, but only a double-quoted `class` or other attribute is rewritten in place, which is
 * how core prints them.
 */
class WP_HTML_Tag_Processor {
	private $html;
	private $at   = null;
	private $len  = 0;
	private $from = 0;

	public function __construct( $html ) { $this->html = (string) $html; }

	public function next_tag( $query = array() ) {
		$name  = isset( $query['tag_name'] ) ? strtolower( $query['tag_name'] ) : '[a-z][a-z0-9]*';
		$class = isset( $query['class_name'] ) ? $query['class_name'] : '';

		while ( preg_match( '/<(' . $name . ')\b[^>]*>/i', $this->html, $tag, PREG_OFFSET_CAPTURE, $this->from ) ) {
			$this->from = $tag[0][1] + strlen( $tag[0][0] );

			if ( '' === $class || in_array( $class, self::classes_of( $tag[0][0] ), true ) ) {
				$this->at  = $tag[0][1];
				$this->len = strlen( $tag[0][0] );

				return true;
			}
		}

		$this->at = null;

		return false;
	}

	public function has_class( $name ) {
		return null !== $this->at && in_array( $name, self::classes_of( $this->tag() ), true );
	}

	public function add_class( $name ) {
		if ( null === $this->at ) {
			return false;
		}

		if ( $this->has_class( $name ) ) {
			return true;
		}

		$tag     = $this->tag();
		$pattern = '/(\sclass=")([^"]*)(")/i';

		if ( ! preg_match( $pattern, $tag ) ) {
			return $this->set_attribute( 'class', $name );
		}

		$this->replace( preg_replace_callback( $pattern, static function ( $found ) use ( $name ) { return $found[1] . ( '' === trim( $found[2] ) ? '' : $found[2] . ' ' ) . $name . $found[3]; }, $tag, 1 ) );

		return true;
	}

	public function get_attribute( $name ) {
		if ( null === $this->at ) {
			return null;
		}

		$attributes = self::attributes_of( $this->tag() );
		$name       = strtolower( $name );

		return array_key_exists( $name, $attributes ) ? $attributes[ $name ] : null;
	}

	public function set_attribute( $name, $value ) {
		$tag     = $this->tag();
		$old     = '/\s' . preg_quote( $name, '/' ) . '="[^"]*"/i';
		$written = ' ' . $name . '="' . htmlspecialchars( (string) $value, ENT_QUOTES ) . '"';

		$this->replace( preg_match( $old, $tag ) ? preg_replace_callback( $old, static function () use ( $written ) { return $written; }, $tag, 1 ) : substr( $tag, 0, -1 ) . $written . '>' );

		return true;
	}

	public function get_updated_html() { return $this->html; }

	/** The tag the processor is on, as the markup has it now. */
	private function tag() { return substr( $this->html, $this->at, $this->len ); }

	/** Put a new text in place of the tag the processor is on, and carry on from the end of it. */
	private function replace( $new ) {
		$this->html = substr_replace( $this->html, $new, $this->at, $this->len );
		$this->len  = strlen( $new );
		$this->from = $this->at + $this->len;
	}

	/**
	 * The attributes of an opening tag: lower-cased name => decoded value, the first of a name that
	 * is written twice, an attribute with no value as ''.
	 */
	private static function attributes_of( $tag ) {
		$found = array();

		preg_match_all( '/\s([^\s"\'>\/=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+)))?/', (string) preg_replace( '/^<[^\s>\/]+/', '', $tag ), $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$name = strtolower( $match[1] );

			if ( ! array_key_exists( $name, $found ) ) {
				$raw            = isset( $match[4] ) ? $match[4] : ( isset( $match[3] ) && '' !== $match[3] ? $match[3] : ( isset( $match[2] ) ? $match[2] : '' ) );
				$found[ $name ] = html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}

		return $found;
	}

	/** The classes of an opening tag, in the order they are written. */
	private static function classes_of( $tag ) {
		$attributes = self::attributes_of( $tag );

		return isset( $attributes['class'] ) ? preg_split( '/\s+/', trim( $attributes['class'] ), -1, PREG_SPLIT_NO_EMPTY ) : array();
	}
}

/* ---- blocks: parsing, serializing and rendering ---------------------------------- */

/**
 * Core's block parser, WP_Block_Parser, ported line for line as far as its output goes: the same
 * token regex, the same stack of open blocks, freeform HTML between top-level blocks as a block with
 * no name, `innerHTML` the block's own markup with its inner blocks taken out and `innerContent` that
 * markup in pieces with a null where each inner block sits. A closer with no opener, or an opener
 * never closed, is handled as core handles it. Not modeled: the `block_parser_class` filter.
 */
class WP_Block_Parser {
	private $document = '';
	private $offset   = 0;
	private $output   = array();
	private $stack    = array();

	public function parse( $document ) {
		$this->document = (string) $document;
		$this->offset   = 0;
		$this->output   = array();
		$this->stack    = array();

		while ( $this->proceed() ) {
			continue;
		}

		return $this->output;
	}

	private function proceed() {
		list( $type, $name, $attrs, $start, $length ) = $this->next_token();

		$depth   = count( $this->stack );
		$leading = $start > $this->offset ? $this->offset : null;

		switch ( $type ) {
			case 'no-more-tokens':
				if ( 0 === $depth ) {
					$this->add_freeform();
					return false;
				}

				while ( 0 < count( $this->stack ) ) {
					$this->add_block_from_stack();
				}

				return false;

			case 'void-block':
				if ( 0 === $depth ) {
					if ( null !== $leading ) {
						$this->output[] = self::freeform( substr( $this->document, $leading, $start - $leading ) );
					}

					$this->output[] = self::block( $name, $attrs );
					$this->offset   = $start + $length;

					return true;
				}

				$this->add_inner_block( self::block( $name, $attrs ), $start, $length );
				$this->offset = $start + $length;

				return true;

			case 'block-opener':
				$this->stack[] = array(
					'block'        => self::block( $name, $attrs ),
					'token_start'  => $start,
					'token_length' => $length,
					'prev_offset'  => $start + $length,
					'leading'      => $leading,
				);
				$this->offset  = $start + $length;

				return true;

			case 'block-closer':
				if ( 0 === $depth ) {
					$this->add_freeform();
					return false;
				}

				if ( 1 === $depth ) {
					$this->add_block_from_stack( $start );
					$this->offset = $start + $length;

					return true;
				}

				$top                            = array_pop( $this->stack );
				$html                           = substr( $this->document, $top['prev_offset'], $start - $top['prev_offset'] );
				$top['block']['innerHTML']     .= $html;
				$top['block']['innerContent'][] = $html;

				$this->add_inner_block( $top['block'], $top['token_start'], $top['token_length'], $start + $length );
				$this->offset = $start + $length;

				return true;
		}

		$this->add_freeform();

		return false;
	}

	private function next_token() {
		if ( ! preg_match( '/<!--\s+(?P<closer>\/)?wp:(?P<namespace>[a-z][a-z0-9_-]*\/)?(?P<name>[a-z][a-z0-9_-]*)\s+(?P<attrs>{(?:(?:[^}]+|}+(?=})|(?!}\s+\/?-->).)*+)?}\s+)?(?P<void>\/)?-->/s', $this->document, $matches, PREG_OFFSET_CAPTURE, $this->offset ) ) {
			return array( 'no-more-tokens', null, null, null, null );
		}

		$closer    = isset( $matches['closer'] ) && -1 !== $matches['closer'][1];
		$void      = isset( $matches['void'] ) && -1 !== $matches['void'][1];
		$namespace = isset( $matches['namespace'] ) && -1 !== $matches['namespace'][1] ? $matches['namespace'][0] : 'core/';
		$name      = $namespace . $matches['name'][0];
		$attrs     = isset( $matches['attrs'] ) && -1 !== $matches['attrs'][1] ? json_decode( $matches['attrs'][0], true ) : array();

		if ( $void ) {
			return array( 'void-block', $name, $attrs, $matches[0][1], strlen( $matches[0][0] ) );
		}

		return array( $closer ? 'block-closer' : 'block-opener', $name, $closer ? null : $attrs, $matches[0][1], strlen( $matches[0][0] ) );
	}

	private static function block( $name, $attrs ) {
		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	private static function freeform( $html ) {
		return array(
			'blockName'    => null,
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	private function add_freeform() {
		$length = strlen( $this->document ) - $this->offset;

		if ( 0 !== $length ) {
			$this->output[] = self::freeform( substr( $this->document, $this->offset, $length ) );
		}
	}

	private function add_inner_block( array $block, $token_start, $token_length, $last_offset = null ) {
		$parent = count( $this->stack ) - 1;
		$html   = substr( $this->document, $this->stack[ $parent ]['prev_offset'], $token_start - $this->stack[ $parent ]['prev_offset'] );

		$this->stack[ $parent ]['block']['innerBlocks'][] = $block;

		if ( ! empty( $html ) ) {
			$this->stack[ $parent ]['block']['innerHTML']     .= $html;
			$this->stack[ $parent ]['block']['innerContent'][] = $html;
		}

		$this->stack[ $parent ]['block']['innerContent'][] = null;
		$this->stack[ $parent ]['prev_offset']             = $last_offset ? $last_offset : $token_start + $token_length;
	}

	private function add_block_from_stack( $end_offset = null ) {
		$top  = array_pop( $this->stack );
		$html = null === $end_offset ? substr( $this->document, $top['prev_offset'] ) : substr( $this->document, $top['prev_offset'], $end_offset - $top['prev_offset'] );

		if ( ! empty( $html ) ) {
			$top['block']['innerHTML']     .= $html;
			$top['block']['innerContent'][] = $html;
		}

		if ( null !== $top['leading'] ) {
			$this->output[] = self::freeform( substr( $this->document, $top['leading'], $top['token_start'] - $top['leading'] ) );
		}

		$this->output[] = $top['block'];
	}
}

/**
 * Core's parse_blocks(): the parsed blocks of some content.
 *
 * @param string $content The content.
 * @return array[]
 */
function parse_blocks( $content ) {
	return ( new WP_Block_Parser() )->parse( $content );
}

/**
 * Core's serialize_block_attributes(), as written: JSON with slashes and Unicode left as they are,
 * then `--`, `<`, `>`, `&` and an escaped quotation mark written as Unicode escapes, so the JSON can
 * sit in an HTML comment.
 *
 * @param array $attributes The attributes.
 * @return string
 */
function serialize_block_attributes( $attributes ) {
	$encoded = json_encode( $attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	$encoded = preg_replace( '/--/', '\\u002d\\u002d', $encoded );
	$encoded = preg_replace( '/</', '\\u003c', $encoded );
	$encoded = preg_replace( '/>/', '\\u003e', $encoded );
	$encoded = preg_replace( '/&/', '\\u0026', $encoded );

	return preg_replace( '/\\\\"/', '\\u0022', $encoded );
}

/**
 * Core's strip_core_block_namespace(): a core block's name without `core/`.
 *
 * @param string|null $block_name The name.
 * @return string|null
 */
function strip_core_block_namespace( $block_name = null ) {
	return is_string( $block_name ) && 0 === strpos( $block_name, 'core/' ) ? substr( $block_name, 5 ) : $block_name;
}

/**
 * Core's get_comment_delimited_block_content(), as written: a void block for empty content.
 *
 * @param string|null $block_name    The name, or null for freeform HTML.
 * @param array       $block_attributes Its attributes.
 * @param string      $block_content Its content.
 * @return string
 */
function get_comment_delimited_block_content( $block_name, $block_attributes, $block_content ) {
	if ( is_null( $block_name ) ) {
		return $block_content;
	}

	$name       = strip_core_block_namespace( $block_name );
	$attributes = empty( $block_attributes ) ? '' : serialize_block_attributes( $block_attributes ) . ' ';

	if ( empty( $block_content ) ) {
		return sprintf( '<!-- wp:%s %s/-->', $name, $attributes );
	}

	return sprintf( '<!-- wp:%s %s-->%s<!-- /wp:%s -->', $name, $attributes, $block_content, $name );
}

/**
 * Core's serialize_block() and serialize_blocks(): parsed blocks back to markup.
 *
 * @param array $block A parsed block.
 * @return string
 */
function serialize_block( $block ) {
	$content = '';
	$index   = 0;

	foreach ( $block['innerContent'] as $chunk ) {
		$content .= is_string( $chunk ) ? $chunk : serialize_block( $block['innerBlocks'][ $index++ ] );
	}

	return get_comment_delimited_block_content( $block['blockName'], is_array( $block['attrs'] ) ? $block['attrs'] : array(), $content );
}
function serialize_blocks( $blocks ) { return implode( '', array_map( 'serialize_block', $blocks ) ); }

/**
 * What the theme's own blocks are, read from each block.json, as register_block_type() would make
 * them: the attributes' schema, and the render file when the block names one. A block of another
 * namespace, core's among them, is not registered here, so it renders as its saved markup, as a
 * static block does.
 *
 * @param string|null $name A block's name.
 * @return array{attributes: array, render: string, uses_context: string[], provides_context: array, skip_inner_blocks: bool}|null
 */
function wpcredits_test_block_type( $name ) {
	static $types = array();

	if ( ! is_string( $name ) || 0 !== strpos( $name, 'wpcredits/' ) ) {
		return null;
	}

	if ( ! array_key_exists( $name, $types ) ) {
		$folder = WPCREDITS_THEME_DIR . 'blocks/' . substr( $name, strlen( 'wpcredits/' ) ) . '/';
		$json   = is_file( $folder . 'block.json' ) ? json_decode( (string) file_get_contents( $folder . 'block.json' ), true ) : null;

		// What block.json has no key for, the theme passes where it registers the block in functions.php.
		$registered = 'register_block_type( get_theme_file_path( \'blocks/' . substr( $name, strlen( 'wpcredits/' ) ) . '\' ), array( \'skip_inner_blocks\' => true ) );';

		$types[ $name ] = is_array( $json ) ? array(
			'attributes'        => isset( $json['attributes'] ) ? $json['attributes'] : array(),
			'render'            => isset( $json['render'] ) ? $folder . substr( $json['render'], strlen( 'file:./' ) ) : '',
			'uses_context'      => isset( $json['usesContext'] ) ? $json['usesContext'] : array(),
			'provides_context'  => isset( $json['providesContext'] ) ? $json['providesContext'] : array(),
			'skip_inner_blocks' => false !== strpos( (string) file_get_contents( WPCREDITS_THEME_DIR . 'functions.php' ), $registered ),
		) : null;
	}

	return $types[ $name ];
}

/**
 * Core's WP_Block, as far as the theme's render files reach: the parsed block, its name, its inner
 * blocks (core's WP_Block_List, here a plain array: the theme only walks it with foreach and counts
 * it), its attributes with the defaults of its block.json filled in, as
 * WP_Block_Type::prepare_attributes_for_render() fills them (the schema's validation is not modeled),
 * and its context, as core works it out: each name its block.json `usesContext` lists, from the
 * context its parent handed down, a parent handing its children the context it had and, for each
 * name of its `providesContext`, the value of the attribute that names (defaults filled in).
 * render() renders the inner blocks first, as core does, unless the block is registered with
 * `skip_inner_blocks` (read from the theme's functions.php, where it is passed), then hands the result
 * to the block's render file as `$content`, with `$attributes` and `$block`, and runs the `render_block`
 * filters; each render of a theme block's file is counted in `$GLOBALS['renders']`, by name, as it
 * ends. The block supports' filters (layout classes and the like) are not modeled.
 */
class WP_Block {
	public $parsed_block;
	public $name;
	public $block_type;
	public $context       = array();
	public $inner_blocks  = array();
	public $inner_html    = '';
	public $inner_content = array();

	public function __construct( $block, $available_context = array() ) {
		$this->parsed_block  = $block;
		$this->name          = $block['blockName'];
		$this->block_type    = wpcredits_test_block_type( $this->name );
		$this->inner_html    = isset( $block['innerHTML'] ) ? $block['innerHTML'] : '';
		$this->inner_content = isset( $block['innerContent'] ) ? $block['innerContent'] : array();

		foreach ( $this->block_type ? $this->block_type['uses_context'] : array() as $context_name ) {
			if ( array_key_exists( $context_name, $available_context ) ) {
				$this->context[ $context_name ] = $available_context[ $context_name ];
			}
		}

		$child_context = $available_context;
		$attributes    = $this->attributes;

		foreach ( $this->block_type ? $this->block_type['provides_context'] : array() as $context_name => $attribute_name ) {
			if ( array_key_exists( $attribute_name, $attributes ) ) {
				$child_context[ $context_name ] = $attributes[ $attribute_name ];
			}
		}

		foreach ( isset( $block['innerBlocks'] ) ? $block['innerBlocks'] : array() as $inner ) {
			$this->inner_blocks[] = new WP_Block( $inner, $child_context );
		}
	}

	public function __get( $name ) {
		if ( 'attributes' !== $name ) {
			return null;
		}

		$attributes = isset( $this->parsed_block['attrs'] ) && is_array( $this->parsed_block['attrs'] ) ? $this->parsed_block['attrs'] : array();

		foreach ( $this->block_type ? $this->block_type['attributes'] : array() as $key => $schema ) {
			if ( ! array_key_exists( $key, $attributes ) && is_array( $schema ) && array_key_exists( 'default', $schema ) ) {
				$attributes[ $key ] = $schema['default'];
			}
		}

		return $attributes;
	}

	public function render() {
		$content = '';
		$index   = 0;
		$dynamic = $this->block_type && '' !== $this->block_type['render'];

		if ( ! $dynamic || ! $this->block_type['skip_inner_blocks'] ) {
			foreach ( $this->inner_content as $chunk ) {
				$content .= is_string( $chunk ) ? $chunk : $this->inner_blocks[ $index++ ]->render();
			}
		}

		if ( $dynamic ) {
			$previous             = $GLOBALS['rendering'];
			$GLOBALS['rendering'] = $this->name;
			$content              = wpcredits_test_render_file( $this->block_type['render'], $this->attributes, $content, $this );
			$GLOBALS['rendering'] = $previous;

			$GLOBALS['renders'][ $this->name ] = ( isset( $GLOBALS['renders'][ $this->name ] ) ? $GLOBALS['renders'][ $this->name ] : 0 ) + 1;
		}

		if ( null === $this->name ) {
			return $content;
		}

		$content = apply_filters( 'render_block', $content, $this->parsed_block, $this );

		return apply_filters( 'render_block_' . $this->name, $content, $this->parsed_block, $this );
	}
}

/**
 * A block's render file, run as core runs it: the attributes, the content and the block in scope,
 * its output captured.
 *
 * @param string   $file       The render file.
 * @param array    $attributes The block's attributes.
 * @param string   $content    Its inner blocks, rendered.
 * @param WP_Block $block      The block.
 * @return string
 */
function wpcredits_test_render_file( $file, $attributes, $content, $block ) {
	ob_start();
	include $file;

	return (string) ob_get_clean();
}

/**
 * Core's render_block() and do_blocks(): a parsed block rendered, and every block of some content.
 * The `pre_render_block` and `render_block_data` filters, and do_blocks()'s wpautop bookkeeping, are
 * not modeled.
 *
 * @param array $parsed_block A parsed block.
 * @return string
 */
function render_block( $parsed_block ) {
	return ( new WP_Block( $parsed_block ) )->render();
}
function do_blocks( $content ) { return implode( '', array_map( 'render_block', parse_blocks( $content ) ) ); }

/**
 * A block's server render, as WordPress runs its render.php, for a block with no inner blocks: the
 * attributes (block.json's defaults filled in), an empty content and the block in scope.
 *
 * @param string $name       The block's name, such as `wpcredits/program-stat`.
 * @param array  $attributes Its attributes.
 * @return string
 */
function wpcredits_test_block( $name, array $attributes = array() ) {
	return render_block(
		array(
			'blockName'    => $name,
			'attrs'        => $attributes,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		)
	);
}

/**
 * The hub page, as WordPress draws it from the template: each pattern's content and each block the
 * template places on its own, in the template's order, then every block in them rendered, the theme's
 * own by their render.php and core's as their saved markup. The template parts of the chrome, and the
 * plugin's sponsor strip, are left out.
 *
 * @return string
 */
function wpcredits_test_page() {
	$template = (string) file_get_contents( WPCREDITS_THEME_DIR . 'templates/page-education-hub.html' );

	preg_match_all( '#<!-- wp:(?:pattern \{"slug":"wpcredits/([a-z-]+)"\}|(wpcredits/[a-z-]+)) /-->#', $template, $parts, PREG_SET_ORDER );

	$page = '';

	foreach ( $parts as $part ) {
		// A pattern is its file's content; a block is left as its comment, for the render below.
		$page .= '' !== $part[1] ? wpcredits_test_pattern( $part[1] . '.php' ) : $part[0];
	}

	return do_blocks( $page );
}

/**
 * A pattern file's output, as WordPress reads a theme pattern's content: block markup.
 *
 * @param string $file The file's name in patterns/, such as `hub-hero.php`.
 * @return string
 */
function wpcredits_test_pattern( $file ) {
	ob_start();
	include WPCREDITS_THEME_DIR . 'patterns/' . $file;

	return (string) ob_get_clean();
}

/**
 * The first block of a name in some block markup, looked for at every depth, as parse_blocks()
 * reads it.
 *
 * @param string $markup Block markup.
 * @param string $name   A block's name.
 * @return array|null The parsed block, or null when there is none.
 */
function wpcredits_test_block_in( $markup, $name ) {
	$walk = static function ( array $blocks ) use ( &$walk, $name ) {
		foreach ( $blocks as $parsed ) {
			if ( $name === $parsed['blockName'] ) {
				return $parsed;
			}

			$found = $walk( $parsed['innerBlocks'] );

			if ( null !== $found ) {
				return $found;
			}
		}

		return null;
	};

	return $walk( parse_blocks( $markup ) );
}

/**
 * A pattern as a page draws it: its block markup, every block rendered.
 *
 * @param string $file The file's name in patterns/.
 * @return string
 */
function wpcredits_test_drawn_pattern( $file ) {
	return do_blocks( wpcredits_test_pattern( $file ) );
}

/**
 * Core's block visibility support (WordPress 7.0 and later), as far as the markup goes, for a suite that
 * hooks it on `render_block` (`add_filter( 'render_block', 'wpcredits_test_block_visibility', 10, 2 )`):
 * a port of `wp_render_block_visibility_support()` from wp-includes/block-supports/block-visibility.php
 * of WordPress 7.1.3, which the Gutenberg plugin 24.0.0 repeats, read in the Docker copy of the site.
 *
 * An Administrator hides a block in the Site Editor, in the block's menu, and it is saved in the block's
 * comment as `"metadata":{"blockVisibility":...}`. `false` hides it everywhere: the server draws
 * nothing, so the block never reaches the page. An object with `viewport` and a size set to false
 * (`mobile`, `tablet` or `desktop`) hides it at that size: the server draws the block with the class
 * `wp-block-hidden-<size>` added to its first tag, one for each size in alphabetical order, and prints
 * a style rule for it, `display: none !important` inside a media query for the size, so the block is on
 * the page and a script can only tell that it is hidden from its computed style. The sizes' media
 * queries, from the site's breakpoints (theme.json `settings.viewport`, 480px and 782px by default):
 * `mobile` is `width <= 480px`, `tablet` `480px < width <= 782px` and `desktop` `width > 782px`. The
 * style rules are not modeled here, only the markup; bin/js/hub-dom.js models the rules for the scripts.
 *
 * @param string $block_content Rendered block content.
 * @param array  $block         The parsed block.
 * @return string
 */
function wpcredits_test_block_visibility( $block_content, $block ) {
	$visibility = isset( $block['attrs']['metadata']['blockVisibility'] ) ? $block['attrs']['metadata']['blockVisibility'] : null;

	if ( false === $visibility ) {
		return '';
	}

	$viewport = is_array( $visibility ) && isset( $visibility['viewport'] ) && is_array( $visibility['viewport'] ) ? $visibility['viewport'] : array();
	$hidden   = array();

	foreach ( $viewport as $size => $is_visible ) {
		if ( false === $is_visible && in_array( $size, array( 'mobile', 'tablet', 'desktop' ), true ) ) {
			$hidden[] = $size;
		}
	}

	if ( ! $hidden || '' === $block_content ) {
		return $block_content;
	}

	sort( $hidden );

	$classes = implode( ' ', array_map( static function ( $size ) { return 'wp-block-hidden-' . $size; }, $hidden ) );

	// WP_HTML_Tag_Processor::add_class() on the first tag: appended to its class attribute, or one made.
	if ( preg_match( '/^(\s*<[a-z0-9]+\b[^>]*?\sclass=")([^"]*)"/i', $block_content ) ) {
		return (string) preg_replace( '/^(\s*<[a-z0-9]+\b[^>]*?\sclass=")([^"]*)"/i', '$1$2 ' . $classes . '"', $block_content, 1 );
	}

	return (string) preg_replace( '/^(\s*<[a-z0-9]+\b)/i', '$1 class="' . $classes . '"', $block_content, 1 );
}

/* ---- templates and patterns, for the Partners page's levels (theme 1.32.1) ------------- */

/**
 * Core's WP_Block_Template, as far as the theme reads one: its ID, theme and slug, its source
 * (`theme` for the theme's own file, `custom` for a copy saved in the Site Editor), its status and its
 * content.
 */
class WP_Block_Template {
	public $type    = 'wp_template';
	public $id      = '';
	public $theme   = '';
	public $slug    = '';
	public $source  = 'theme';
	public $status  = 'publish';
	public $content = '';
}

/**
 * Core's get_block_template(): the copy of a template or of a template part saved in the Site Editor,
 * in any status, from `$GLOBALS['block_templates']` by its ID, else, as core falls back to it, the
 * theme's own file (get_block_file_template()). Each call is counted in `$GLOBALS['template_reads']`,
 * or in `$GLOBALS['part_reads']` for a template part.
 *
 * @param string $id            The template's ID, `theme//slug`.
 * @param string $template_type `wp_template` or `wp_template_part`.
 * @return WP_Block_Template|null
 */
function get_block_template( $id, $template_type = 'wp_template' ) {
	$part = 'wp_template_part' === $template_type;

	++$GLOBALS[ $part ? 'part_reads' : 'template_reads' ];

	$key = $part ? 'part:' . $id : $id;

	if ( isset( $GLOBALS['block_templates'][ $key ] ) ) {
		return $GLOBALS['block_templates'][ $key ];
	}

	return get_block_file_template( $id, $template_type );
}

/**
 * Core's get_block_file_template(): the theme's own file for a template, in templates/, or for a
 * template part, in parts/, with the source `theme`; null for one the theme has no file for. A copy
 * saved in the Site Editor is not looked at.
 *
 * @param string $id            The template's ID, `theme//slug`.
 * @param string $template_type `wp_template` or `wp_template_part`.
 * @return WP_Block_Template|null
 */
function get_block_file_template( $id, $template_type = 'wp_template' ) {
	$parts = explode( '//', (string) $id, 2 );
	$file  = 2 === count( $parts ) ? WPCREDITS_THEME_DIR . ( 'wp_template_part' === $template_type ? 'parts/' : 'templates/' ) . $parts[1] . '.html' : '';

	if ( '' === $file || ! is_file( $file ) ) {
		return null;
	}

	$template          = new WP_Block_Template();
	$template->type    = $template_type;
	$template->id      = $id;
	$template->theme   = $parts[0];
	$template->slug    = $parts[1];
	$template->content = (string) file_get_contents( $file );

	return $template;
}

/**
 * Put a copy of one of the theme's templates, or of one of its template parts, where
 * get_block_template() finds it, as the Site Editor saves one: the source `custom`, the content given.
 *
 * @param string $slug    The template's slug, such as `page-partners`, or the part's.
 * @param string $content Its saved content.
 * @param string $status  Its post's status: `publish`, the only one the front end draws, `draft` and
 *                        the others it does not, or `trash` for one an Administrator deleted.
 * @param string $type    `wp_template`, or `wp_template_part` for a template part.
 * @return WP_Block_Template
 */
function wpcredits_test_saved_template( $slug, $content, $status = 'publish', $type = 'wp_template' ) {
	$template          = new WP_Block_Template();
	$template->type    = $type;
	$template->id      = 'wpcredits-theme//' . $slug;
	$template->theme   = 'wpcredits-theme';
	$template->slug    = $slug;
	$template->source  = 'custom';
	$template->status  = $status;
	$template->content = (string) $content;

	$GLOBALS['block_templates'][ 'wp_template_part' === $type ? 'part:' . $template->id : $template->id ] = $template;

	return $template;
}

/**
 * Core's block pattern registry, as far as the theme asks it: each of the theme's patterns, registered
 * by the Slug of its file's header, as core registers a theme's patterns, its content the file's
 * output, read when it is asked for.
 */
class WP_Block_Patterns_Registry {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function is_registered( $name ) {
		return '' !== self::file_of( $name );
	}

	public function get_registered( $name ) {
		$file = self::file_of( $name );

		return '' === $file ? null : array(
			'name'    => $name,
			'content' => wpcredits_test_pattern( basename( $file ) ),
		);
	}

	/** The theme's pattern file whose header names this slug, or ''. */
	private static function file_of( $name ) {
		foreach ( glob( WPCREDITS_THEME_DIR . 'patterns/*.php' ) as $file ) {
			if ( preg_match( '/^\s*\*\s*Slug:\s*(\S+)\s*$/m', (string) file_get_contents( $file ), $slug ) && $slug[1] === $name ) {
				return $file;
			}
		}

		return '';
	}
}

/**
 * A filter callback that answers true, as core's __return_true().
 *
 * @return bool
 */
function wpcredits_test_true() {
	return true;
}
