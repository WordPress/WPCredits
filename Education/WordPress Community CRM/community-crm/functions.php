<?php
/**
 * WordPress Community CRM — theme functions.
 *
 * A block theme needs very little PHP: theme.json carries the design tokens,
 * and /templates, /parts and /patterns are discovered automatically. This file
 * wires up the pieces the front page needs to work as a sign-in screen.
 *
 * @package Community_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CCRM_VERSION', '1.1.5' );

require_once get_theme_file_path( 'inc/login.php' );

if ( ! function_exists( 'ccrm_setup' ) ) {
	/**
	 * Theme setup.
	 */
	function ccrm_setup() {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'custom-logo', array(
			'height'      => 24,
			'width'       => 24,
			'flex-height' => true,
			'flex-width'  => true,
		) );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );

		// Make the editor canvas match the front end.
		add_editor_style( 'style.css' );

		load_theme_textdomain( 'community-crm', get_theme_file_path( 'languages' ) );
	}
}
add_action( 'after_setup_theme', 'ccrm_setup' );

if ( ! function_exists( 'ccrm_enqueue_assets' ) ) {
	/**
	 * Enqueue the theme stylesheet on the front end.
	 */
	function ccrm_enqueue_assets() {
		wp_enqueue_style(
			'community-crm',
			get_stylesheet_uri(),
			array(),
			CCRM_VERSION
		);
	}
}
add_action( 'wp_enqueue_scripts', 'ccrm_enqueue_assets' );

if ( ! function_exists( 'ccrm_body_class' ) ) {
	/**
	 * Flag the sign-in screen. The theme's own layout hangs off the
	 * .ccrm-login-main class on the <main> element, so this class exists as a
	 * hook for site-specific CSS that needs to target the sign-in screen as a
	 * whole.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	function ccrm_body_class( $classes ) {
		if ( is_front_page() ) {
			$classes[] = 'ccrm-login-page';
		}

		return $classes;
	}
}
add_filter( 'body_class', 'ccrm_body_class' );

if ( ! function_exists( 'ccrm_pattern_category' ) ) {
	/**
	 * Register a dedicated pattern category so the theme's sections are easy to
	 * find in the block inserter.
	 */
	function ccrm_pattern_category() {
		if ( ! function_exists( 'register_block_pattern_category' ) ) {
			return;
		}

		register_block_pattern_category(
			'community-crm',
			array(
				'label'       => __( 'Community CRM', 'community-crm' ),
				'description' => __( 'Sections designed for the WordPress Community CRM theme.', 'community-crm' ),
			)
		);
	}
}
add_action( 'init', 'ccrm_pattern_category' );

if ( ! function_exists( 'ccrm_block_styles' ) ) {
	/**
	 * Register the outline button variation used by the design's secondary
	 * "Request access" control.
	 */
	function ccrm_block_styles() {
		register_block_style(
			'core/button',
			array(
				'name'  => 'ccrm-outline',
				'label' => __( 'Outline', 'community-crm' ),
			)
		);
	}
}
add_action( 'init', 'ccrm_block_styles' );

/*
 * No skip link is registered here on purpose: core renders one for block
 * themes via wp_render_block_template_skip_link() on wp_body_open, pointing at
 * #wp--skip-link--target. Every template puts that anchor on its <main>, and
 * style.css styles core's markup.
 */

if ( ! function_exists( 'ccrm_excerpt_more' ) ) {
	/**
	 * Replace the default [...] excerpt tail with an ellipsis.
	 *
	 * @param string $more Excerpt tail.
	 * @return string
	 */
	function ccrm_excerpt_more( $more ) {
		return is_admin() ? $more : '…';
	}
}
add_filter( 'excerpt_more', 'ccrm_excerpt_more' );
