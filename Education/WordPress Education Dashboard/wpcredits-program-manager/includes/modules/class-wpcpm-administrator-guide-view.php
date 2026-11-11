<?php
/**
 * The Administrator guide in the Administrator Dashboard's frame (1.123.70).
 *
 * The owner, 11 November 2026: the guide "should open in this same layout, with the sidebar menu on the
 * left for navigation, on the right of the content it should list of sections for easy navigation around
 * the guide". The guide is the page at `WPCPM_Settings_Screen::GUIDE_PAGE_PATH`, which bin/build-docs.php
 * builds from docs/sections and which only Administrators can open (its access level needs
 * `WPCPM_Roles::CAP_MANAGE`).
 *
 * Where the theme draws the page as a bare canvas, naming it in `add_theme_support( 'wpcpm-dashboard-frame',
 * array( 'administrator', 'administrator-guide' ) )`, this wraps the page's content in the frame: the
 * Administrator Dashboard's own menu and counts with the guide marked current, the page's title as its one
 * H1 in the serif head, the guide, and beside it the list of its sections. Under any other theme the page is
 * left as the theme draws it.
 *
 * Since 1.123.78 the Sponsor guide is drawn the same way in the Sponsor Dashboard's frame (`guides()`), for the
 * people who act for a sponsor and for Administrators, where the theme names `sponsor-guide`.
 *
 * The list holds the guide's sections (its `h2`s) and, under each, the parts of it (its `h3`s). The script
 * (`assets/js/administrator-guide.js`) marks the part being read, opens only that section's parts, and folds
 * the whole list behind its heading when the main area is too narrow for a column beside the guide.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The guide's page in the frame.
 */
class WPCPM_Administrator_Guide_View {

	/** The page's key in the theme's list and after `--` in the frame's class. */
	const FRAME_PAGE = 'administrator-guide';

	/** The stylesheet's handle. */
	const STYLE = 'wpcpm-administrator-guide';

	/** The script's handle. */
	const SCRIPT = 'wpcpm-administrator-guide';

	/** The list's id, which its heading's button controls on a narrow main area. */
	const LIST_ID = 'wpcpm-guide-list';

	/**
	 * Whether the guide has been drawn on this request: the frame is drawn once, whatever else asks for the
	 * page's content after it.
	 *
	 * @var bool
	 */
	private static $drawn = false;

	/**
	 * Hooks. Called from `WPCPM_Administrators::boot()`.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_assets' ) );

		// After the blocks are rendered (9), the paragraphs (10) and the shortcodes (11), and after the
		// access check (WPCPM_Content_Access, 5), so the content wrapped is the content the reader may see.
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 99 );
	}

	/**
	 * Register (but do not enqueue) the stylesheet and the script.
	 */
	public static function register_assets() {
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style( self::STYLE, WPCPM_PLUGIN_URL . 'assets/css/administrator-guide.css', array( WPCPM_Dashboard_Frame::STYLE ), WPCPM_VERSION );
		}

		if ( ! wp_script_is( self::SCRIPT, 'registered' ) ) {
			wp_register_script( self::SCRIPT, WPCPM_PLUGIN_URL . 'assets/js/administrator-guide.js', array(), WPCPM_VERSION, true );
		}
	}

	/**
	 * The guides drawn in a dashboard's frame, by the path of their page: the frame's key for the page, as the
	 * theme names it, the dashboard whose menu it draws, who reads it in the frame (`manage`, an Administrator;
	 * `sponsor`, an Administrator or somebody who acts for a sponsor) and the title for a page with none.
	 *
	 * @return array<string, array{frame: string, dashboard: string, reader: string, title: string}>
	 */
	public static function guides() {
		return array(
			WPCPM_Settings_Screen::GUIDE_PAGE_PATH => array(
				'frame'     => self::FRAME_PAGE,
				'dashboard' => 'WPCPM_Administrators_Dashboard',
				'reader'    => 'manage',
				'title'     => __( 'Administrator guide', 'wpcredits-program-manager' ),
			),
			// The Sponsor guide (1.123.78), in the Sponsor Dashboard's frame.
			'sponsor-guide'                        => array(
				'frame'     => 'sponsor-guide',
				'dashboard' => 'WPCPM_Sponsors_Dashboard',
				'reader'    => 'sponsor',
				'title'     => __( 'Sponsor guide', 'wpcredits-program-manager' ),
			),
		);
	}

	/**
	 * Which guide a post is: the path of a top-level page `guides()` lists, or ''. The theme asks this too, to
	 * know which page to draw as a bare canvas, and on which template.
	 *
	 * @param mixed $post A post, or anything else.
	 * @return string
	 */
	public static function guide_of( $post ) {
		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type || 0 !== (int) $post->post_parent ) {
			return '';
		}

		return array_key_exists( (string) $post->post_name, self::guides() ) ? (string) $post->post_name : '';
	}

	/**
	 * Whether a post is the Administrator guide's page: the top-level page at the guide's path. Theme 1.32.23
	 * to 1.32.26 ask this; later ones ask `guide_of()`.
	 *
	 * @param mixed $post A post, or anything else.
	 * @return bool
	 */
	public static function is_guide( $post ) {
		return WPCPM_Settings_Screen::GUIDE_PAGE_PATH === self::guide_of( $post );
	}

	/**
	 * The frame a guide's page is drawn in for this reader (1.123.78): the frame's key, as the theme names it
	 * (`administrator-guide`, `sponsor-guide`), or '' when the page is no guide, the theme does not name its
	 * frame, or the reader does not read it there. The theme asks this to pick the page's bare canvas, so a
	 * reader the frame is not drawn for keeps the page's own template.
	 *
	 * @param mixed $post A post, or anything else.
	 * @return string
	 */
	public static function frame_for( $post ) {
		$guide = self::guide_of( $post );

		if ( '' === $guide ) {
			return '';
		}

		$guides = self::guides();
		$found  = $guides[ $guide ];

		if ( ! WPCPM_Dashboard_Frame::owns_page( $found['frame'] ) || ! class_exists( $found['dashboard'] ) || ! self::may_read( $found['reader'] ) ) {
			return '';
		}

		return $found['frame'];
	}

	/**
	 * Whether the reader reads a guide in its dashboard's frame.
	 *
	 * @param string $reader A guide's `reader`, as `guides()` lists it.
	 * @return bool
	 */
	private static function may_read( $reader ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return true;
		}

		return 'sponsor' === $reader && class_exists( 'WPCPM_Sponsor_Members' ) && WPCPM_Sponsor_Members::is_member();
	}

	/**
	 * Whether this call of `the_content` is the guide's page being drawn for an Administrator, in a theme
	 * that leaves the page to the frame.
	 *
	 * @return bool
	 */
	private static function applies() {
		if ( self::$drawn || is_admin() || is_feed() || doing_filter( 'get_the_excerpt' ) ) {
			return false;
		}

		if ( ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) {
			return false;
		}

		// The page the address asked for, not one a Query Loop inside it draws (the updates' review, 1.123.71).
		$post = get_post();

		return $post instanceof WP_Post
			&& (int) get_queried_object_id() === (int) $post->ID
			&& '' !== self::frame_for( $post );
	}

	/**
	 * The guide's content, in the frame.
	 *
	 * @param string $content The page's content, rendered.
	 * @return string
	 */
	public static function filter_content( $content ) {
		if ( ! self::applies() ) {
			return $content;
		}

		self::$drawn = true;

		$post = get_post();

		return self::render( (string) $content, $post instanceof WP_Post ? (string) $post->post_title : '', self::guide_of( $post ) );
	}

	/**
	 * The frame, the head, the list of sections and the guide.
	 *
	 * The list comes before the guide in the page, so a screen reader and a phone meet it first; on a wide
	 * main area the stylesheet sets it in its own column beside the guide.
	 *
	 * @param string $content The guide, rendered.
	 * @param string $title   The page's title.
	 * @param string $guide   The guide's path, a key of `guides()`; the Administrator guide by default.
	 * @return string
	 */
	public static function render( $content, $title, $guide = '' ) {
		$guides = self::guides();
		$guide  = isset( $guides[ $guide ] ) ? $guides[ $guide ] : $guides[ WPCPM_Settings_Screen::GUIDE_PAGE_PATH ];
		$found  = self::sections( $content );

		self::register_assets();
		wp_enqueue_style( self::STYLE );
		wp_enqueue_script( self::SCRIPT );

		ob_start();

		// The guide's dashboard's own menu, with the guide marked as where the reader is.
		WPCPM_Dashboard_Frame::open( call_user_func( array( $guide['dashboard'], 'menu_args' ), 'guide' ) );

		// What the script names a table that scrolls sideways, for a screen reader.
		printf( '<div class="wpcpm-guide" data-wpcpm-table-label="%s">', esc_attr__( 'Table, scrolls sideways', 'wpcredits-program-manager' ) );

		printf(
			'<header class="wpcpm-head wpcpm-guide__head"><div class="wpcpm-head__copy"><h1 class="wpcpm-head__title">%s</h1></div></header>',
			esc_html( '' !== trim( $title ) ? $title : $guide['title'] )
		);

		self::contents( $found['sections'] );

		// The guide as the page holds it, after `the_content`: what a reader may see, already through
		// every filter core and the theme put on it.
		echo '<div class="wpcpm-guide__body">' . $found['content'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The page's own content, filtered by the_content.

		echo '</div>';

		WPCPM_Dashboard_Frame::close();

		return (string) ob_get_clean();
	}

	/**
	 * The guide's sections and their parts, read from its `h2` and `h3` headings, with an id put on any
	 * heading that has none. bin/build-docs.php writes an anchor on every heading it builds, so those are
	 * left exactly as they are, and a link someone saved keeps working; a heading typed into the editor
	 * afterwards gets a slug of its words, the same slug the build would have given it.
	 *
	 * A part that comes before the first section is listed as a section of its own.
	 *
	 * @param string $html The guide, rendered.
	 * @return array{content:string, sections:array<int, array{id:string, text:string, parts:array<int, array{id:string, text:string}>}>}
	 */
	public static function sections( $html ) {
		$taken    = array();
		$sections = array();

		// Every id already on the page claims its slug, so a new one never repeats it.
		foreach ( self::page_ids( (string) $html ) as $id ) {
			$taken[ $id ] = true;
		}

		$replaced = preg_replace_callback(
			// The opening tag's attributes may quote a `>`. Possessive, so a long attribute is one step,
			// not one per character: the release's review (1.123.70) ran a 10,000-character one past
			// PCRE's JIT stack, and the replace returned null.
			'#<h([23])\b((?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+)>(.*?)</h\1>#is',
			static function ( $match ) use ( &$taken, &$sections ) {
				$text = trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $match[3] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );

				if ( '' === $text ) {
					return $match[0];
				}

				$tag  = $match[0];
				$open = '<h' . $match[1] . $match[2] . '>';
				$id   = self::tag_id( $open );

				if ( '' === $id ) {
					$id  = self::slug( $text, $taken );
					$tag = self::with_id( $open, $id ) . $match[3] . '</h' . $match[1] . '>';
				}

				$entry = array(
					'id'   => $id,
					'text' => $text,
				);

				if ( '2' === $match[1] || empty( $sections ) ) {
					$sections[] = $entry + array( 'parts' => array() );
				} else {
					$sections[ count( $sections ) - 1 ]['parts'][] = $entry;
				}

				return $tag;
			},
			(string) $html
		);

		// A replace that fails gives the guide back as it was, with no list, rather than nothing.
		if ( ! is_string( $replaced ) ) {
			return array(
				'content'  => (string) $html,
				'sections' => array(),
			);
		}

		return array(
			'content'  => $replaced,
			'sections' => $sections,
		);
	}

	/**
	 * Whether core's HTML parser reads attributes as a browser does: from WordPress 6.6, whose
	 * `WP_HTML_Decoder` decodes character references by the HTML5 rules. From 6.2 to 6.5 the parser
	 * decoded without them, so `&sol;` stayed as written (the release's review, 1.123.70).
	 *
	 * @return bool
	 */
	private static function parser() {
		return class_exists( 'WP_HTML_Tag_Processor' ) && class_exists( 'WP_HTML_Decoder' );
	}

	/**
	 * Every id on the page, as a browser reads them.
	 *
	 * @param string $html The guide, rendered.
	 * @return string[]
	 */
	private static function page_ids( $html ) {
		$ids = array();

		if ( self::parser() ) {
			$tags = new WP_HTML_Tag_Processor( $html );

			while ( $tags->next_tag() ) {
				$id = $tags->get_attribute( 'id' );

				if ( is_string( $id ) && '' !== $id ) {
					$ids[] = $id;
				}
			}

			return $ids;
		}

		if ( preg_match_all( '#<[a-z][a-z0-9-]*+\b(?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+>#i', $html, $tags ) ) {
			foreach ( $tags[0] as $tag ) {
				$id = self::tag_id( $tag );

				if ( '' !== $id ) {
					$ids[] = $id;
				}
			}
		}

		return $ids;
	}

	/**
	 * A tag's id, as a browser reads it: core's HTML parser where WordPress has one that does (6.6), which
	 * reads every way HTML may write an attribute and its character references (the release's review,
	 * 1.123.70: an `id = "x"`, an `id=` inside another attribute's value, `&#128;`); before it, the attributes
	 * read in order, quotes and all, the first id winning as in a browser.
	 *
	 * @param string $open The opening tag.
	 * @return string The id, or '' for none or an empty one.
	 */
	private static function tag_id( $open ) {
		if ( self::parser() ) {
			$tag = new WP_HTML_Tag_Processor( $open );

			if ( ! $tag->next_tag() ) {
				return '';
			}

			$id = $tag->get_attribute( 'id' );

			return is_string( $id ) ? $id : '';
		}

		$attributes = (string) preg_replace( '#^<[a-z][a-z0-9-]*#i', '', rtrim( $open, '>' ) );

		if ( preg_match_all( '/([^\s"\'>\/=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]*)))?/', $attributes, $found, PREG_SET_ORDER ) ) {
			foreach ( $found as $attribute ) {
				if ( 'id' !== strtolower( $attribute[1] ) ) {
					continue;
				}

				$value = '';

				foreach ( array( 2, 3, 4 ) as $group ) {
					if ( isset( $attribute[ $group ] ) && '' !== $attribute[ $group ] ) {
						$value = $attribute[ $group ];
						break;
					}
				}

				return html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}

		return '';
	}

	/**
	 * An opening tag with its id set, an empty one replaced.
	 *
	 * @param string $open The opening tag.
	 * @param string $id   The id.
	 * @return string
	 */
	private static function with_id( $open, $id ) {
		if ( self::parser() ) {
			$tag = new WP_HTML_Tag_Processor( $open );

			if ( $tag->next_tag() ) {
				$tag->set_attribute( 'id', $id );

				return $tag->get_updated_html();
			}
		}

		return substr( $open, 0, -1 ) . ' id="' . esc_attr( $id ) . '">';
	}

	/**
	 * A link to a heading: its id percent-encoded, so an id holding `%`, a space or anything else a
	 * fragment cannot carry as it is still lands (the release's review, 1.123.70). A slug is left as it is.
	 *
	 * @param string $id The heading's id.
	 * @return string The `href`, escaped.
	 */
	private static function href( $id ) {
		return esc_attr( '#' . rawurlencode( (string) $id ) );
	}

	/**
	 * A heading's slug, the way bin/build-docs.php and the theme's contents list make one: its letters and
	 * digits, lowercased, joined by hyphens, and numbered when the page already has it.
	 *
	 * @param string $text  The heading's words.
	 * @param array  $taken The ids already on the page, as keys, by reference.
	 * @return string
	 */
	private static function slug( $text, array &$taken ) {
		$base = strtolower( trim( (string) preg_replace( '/[^a-z0-9]+/i', '-', $text ), '-' ) );
		$base = '' !== $base ? $base : 'section';
		$slug = $base;
		$n    = 1;

		while ( isset( $taken[ $slug ] ) ) {
			$slug = $base . '-' . ( ++$n );
		}

		$taken[ $slug ] = true;

		return $slug;
	}

	/**
	 * The list of sections. Its heading holds a button the script shows when the list is folded, on a main
	 * area too narrow for the list's own column; without the script the whole list is shown.
	 *
	 * @param array $sections What `sections()` returned under `sections`.
	 */
	private static function contents( array $sections ) {
		if ( count( $sections ) < 2 ) {
			return;
		}

		$label = __( 'On this page', 'wpcredits-program-manager' );

		echo '<nav class="wpcpm-guide__contents" aria-labelledby="wpcpm-guide-contents-title" data-wpcpm-guide-contents>';

		printf(
			'<h2 class="wpcpm-guide__contents-title" id="wpcpm-guide-contents-title"><span class="wpcpm-guide__contents-label">%1$s</span><button class="wpcpm-guide__contents-toggle" type="button" aria-expanded="false" aria-controls="%2$s" hidden>%1$s%3$s</button></h2>',
			esc_html( $label ),
			esc_attr( self::LIST_ID ),
			WPCPM_Icons::svg( 'chevron-down', 16 ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed icon from WPCPM_Icons.
		);

		printf( '<ul class="wpcpm-guide__list" id="%s">', esc_attr( self::LIST_ID ) );

		foreach ( $sections as $section ) {
			echo '<li class="wpcpm-guide__entry">';
			printf( '<a class="wpcpm-guide__link" href="%1$s">%2$s</a>', self::href( $section['id'] ), esc_html( $section['text'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in href().

			if ( ! empty( $section['parts'] ) ) {
				echo '<ul class="wpcpm-guide__parts">';

				foreach ( $section['parts'] as $part ) {
					printf( '<li><a class="wpcpm-guide__link wpcpm-guide__link--part" href="%1$s">%2$s</a></li>', self::href( $part['id'] ), esc_html( $part['text'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in href().
				}

				echo '</ul>';
			}

			echo '</li>';
		}

		echo '</ul></nav>';
	}
}
