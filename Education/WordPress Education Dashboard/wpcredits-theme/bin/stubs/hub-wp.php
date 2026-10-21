<?php
/**
 * WordPress, as far as the Education Hub's and the site header's files reach, stood in for their suites.
 *
 * bin/test-hub.php renders the hub's template rule, content, blocks and patterns with these, and
 * bin/test-hub-js.php renders the two blocks' markup with them for its scenarios. bin/test-header.php
 * runs the header's filters on them, over the markup bin/stubs/header-markup.php prints. Nothing here
 * talks to a database or the network: every answer comes from the globals below, which a check sets.
 *
 * - `$GLOBALS['opts']`: option => value; `blogname` is what `get_bloginfo( 'name' )` answers.
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

function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default; }
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

/* ---- rendering ------------------------------------------------------------------- */

/**
 * A block's server render, as WordPress runs its render.php: the attributes, the content and the
 * block in scope, its output captured.
 *
 * @param string $name       The block's name, such as `wpcredits/program-index`.
 * @param array  $attributes Its attributes.
 * @return string
 */
function wpcredits_test_block( $name, array $attributes = array() ) {
	$GLOBALS['rendering'] = $name;

	$render = static function ( $file, $attributes ) {
		$content = '';
		$block   = null;

		ob_start();
		include $file;

		return (string) ob_get_clean();
	};

	$html = $render( WPCREDITS_THEME_DIR . 'blocks/' . substr( $name, strpos( $name, '/' ) + 1 ) . '/render.php', $attributes );

	$GLOBALS['rendering'] = '';

	return $html;
}

/**
 * The hub page, as WordPress draws it from the template: each pattern's content and each block the
 * template places on its own, in the template's order, each dynamic block in them rendered by its
 * render.php. The template parts of the chrome are left out.
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

	return preg_replace_callback(
		'#<!-- wp:(wpcredits/[a-z-]+)( (\{[^}]*\}))? /-->#',
		static function ( $block ) {
			return wpcredits_test_block( $block[1], isset( $block[3] ) ? (array) json_decode( $block[3], true ) : array() );
		},
		$page
	);
}

/**
 * A pattern file's output, as WordPress reads a theme pattern's content.
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
 * A filter callback that answers true, as core's __return_true().
 *
 * @return bool
 */
function wpcredits_test_true() {
	return true;
}
