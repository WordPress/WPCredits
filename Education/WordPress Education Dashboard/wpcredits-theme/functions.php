<?php
/**
 * WPCredits — a block theme, and the front end for the WPCredits Program Manager
 * plugin.
 *
 * Everything the site owner can arrange lives in theme.json, the templates and
 * the patterns. This file is only for what those cannot express: request-time
 * facts (who is signed in, whether the plugin is active), the dashboard skin's
 * conditional loading, and the login screen.
 *
 * The theme adds no program data or behavior of its own. The mentor page is the
 * plugin's markup, restyled, with grouping and search layered over what it has
 * already rendered.
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WPCREDITS_VERSION' ) ) {
	define( 'WPCREDITS_VERSION', '1.7.1' );
}

require_once get_theme_file_path( 'inc/icons.php' );
require_once get_theme_file_path( 'inc/template-tags.php' );
require_once get_theme_file_path( 'inc/dashboard.php' );
require_once get_theme_file_path( 'inc/login.php' );

/**
 * Theme supports.
 *
 * Block themes get most of this for free; what is left is the text domain, the
 * editor stylesheet, and turning off the parts of the block editor this design
 * has no room for.
 */
function wpcredits_setup() {
	load_theme_textdomain( 'wpcredits-theme', get_theme_file_path( 'languages' ) );

	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );

	// The chrome's own styles apply inside the editor, so the Site Editor shows
	// the sticky bar and the landing sections as the front end renders them.
	add_editor_style( array( 'style.css', 'assets/css/editor.css' ) );
}
add_action( 'after_setup_theme', 'wpcredits_setup' );

/**
 * Front-end assets.
 *
 * The theme's own stylesheet is registered as a block-theme style so it loads
 * after theme.json's generated CSS and can rely on the presets it defines.
 *
 * The dashboard skin is not here: inc/dashboard.php owns it, because only that
 * file knows whether the plugin is rendering on this request and which of the
 * plugin's own stylesheets it has to load after.
 */
function wpcredits_assets() {
	wp_enqueue_style( 'wpcredits-style', get_stylesheet_uri(), array(), WPCREDITS_VERSION );
}
add_action( 'wp_enqueue_scripts', 'wpcredits_assets' );

/**
 * Register the theme's own blocks.
 */
function wpcredits_register_blocks() {
	register_block_type( get_theme_file_path( 'blocks/viewer-chip' ) );
	register_block_type( get_theme_file_path( 'blocks/handbook-launcher' ) );
}
add_action( 'init', 'wpcredits_register_blocks' );

/**
 * A category of its own for the program patterns, so they are not lost among
 * core's.
 */
function wpcredits_register_pattern_category() {
	if ( ! function_exists( 'register_block_pattern_category' ) ) {
		return;
	}

	register_block_pattern_category(
		'wpcredits',
		array(
			'label'       => __( 'WPCredits program', 'wpcredits-theme' ),
			'description' => __( 'Sections for the program’s landing page.', 'wpcredits-theme' ),
		)
	);
}
add_action( 'init', 'wpcredits_register_pattern_category' );

/**
 * Block styles the design uses, registered so they are choices in the editor
 * rather than classes someone has to know to type.
 */
function wpcredits_register_block_styles() {
	register_block_style(
		'core/group',
		array(
			'name'  => 'card',
			'label' => __( 'Card', 'wpcredits-theme' ),
		)
	);

	register_block_style(
		'core/group',
		array(
			'name'  => 'band',
			'label' => __( 'Pale band', 'wpcredits-theme' ),
		)
	);

	register_block_style(
		'core/paragraph',
		array(
			'name'  => 'eyebrow',
			'label' => __( 'Eyebrow', 'wpcredits-theme' ),
		)
	);

	register_block_style(
		'core/paragraph',
		array(
			'name'  => 'pill',
			'label' => __( 'Pill', 'wpcredits-theme' ),
		)
	);
}
add_action( 'init', 'wpcredits_register_block_styles' );

/**
 * Pick the word "Education" out of the site title in brand blue.
 *
 * Filtered rather than hard-coded into the header part so the block stays a real
 * Site Title block — renaming the site in Settings keeps working, and a site
 * without the word simply loses the accent.
 *
 * @param string $content Rendered block HTML.
 * @param array  $block   Parsed block.
 * @return string
 */
function wpcredits_accent_site_title( $content, $block ) {
	if ( empty( $block['blockName'] ) || 'core/site-title' !== $block['blockName'] ) {
		return $content;
	}

	$name = trim( wp_strip_all_tags( (string) get_bloginfo( 'name', 'display' ) ) );

	if ( '' === $name || false === strpos( $content, $name ) ) {
		return $content;
	}

	$accented = wpcredits_accent_word( $name );

	if ( $accented === $name ) {
		return $content;
	}

	// Replaces the one occurrence of the title text, not every match: the title
	// can legitimately appear in an attribute on the same element.
	$position = strpos( $content, $name );

	return substr_replace( $content, $accented, $position, strlen( $name ) );
}
add_filter( 'render_block', 'wpcredits_accent_site_title', 10, 2 );

/**
 * Draw the WordPress mark when no site logo has been set.
 *
 * `core/site-logo` renders nothing at all without one, which on this design
 * leaves the site name floating with a gap where the mark belongs. Filtered
 * rather than hard-coded into the header part, so the moment somebody uploads a
 * logo the real block takes over.
 *
 * @param string $content Rendered block HTML.
 * @return string
 */
function wpcredits_site_logo_fallback( $content ) {
	if ( '' !== trim( $content ) || has_custom_logo() ) {
		return $content;
	}

	return sprintf(
		'<div class="wp-block-site-logo is-default-size"><a class="custom-logo-link" href="%1$s" rel="home">%2$s</a></div>',
		esc_url( home_url( '/' ) ),
		wp_kses( wpcredits_get_logo( 24 ), wpcredits_svg_allowed_html() )
	);
}
add_filter( 'render_block_core/site-logo', 'wpcredits_site_logo_fallback' );

/**
 * Body classes the dashboard skin keys off.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function wpcredits_body_class( $classes ) {
	if ( wpcredits_is_dashboard_page() ) {
		$classes[] = 'wpc-dashboard-page';
	}

	if ( wpcredits_is_student_page() ) {
		$classes[] = 'wpc-student-page';
	}

	if ( is_user_logged_in() ) {
		$classes[] = 'wpc-signed-in';
	}

	return $classes;
}
add_filter( 'body_class', 'wpcredits_body_class' );

/**
 * No sharing buttons under a post.
 *
 * Jetpack prints "Share this: X / Facebook / Customize buttons" after the content on
 * every post. This site is a signed-in intranet for a program's students and mentors —
 * nothing on it is public, so every one of those buttons shares a page the recipient
 * cannot open, and "Customize buttons" is an admin control shown to readers.
 *
 * Filtered rather than switched off in Jetpack's settings so the theme carries its own
 * decision: the site is rebuilt from this repository, and a setting toggled in wp-admin
 * would not come with it. Likes and related posts are Jetpack's to configure.
 */
add_filter( 'sharing_show', '__return_false' );
