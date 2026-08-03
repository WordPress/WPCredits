<?php
/**
 * WordPress Education Initiatives Blocks — theme functions.
 *
 * A block (Full Site Editing) theme. Structure lives in /templates and /parts,
 * global styles in theme.json, and section content in reusable /patterns.
 *
 * @package WP_Education_Blocks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WPEB_VERSION' ) ) {
	define( 'WPEB_VERSION', '1.5.0' );
}

/**
 * Theme setup.
 */
function wpeb_setup() {
	load_theme_textdomain( 'wp-education-blocks', get_template_directory() . '/languages' );

	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array(
		'height'      => 76,
		'width'       => 76,
		'flex-height' => true,
		'flex-width'  => true,
	) );

	// Load the bespoke design CSS inside the editor as well as the front end.
	add_editor_style( 'style.css' );
}
add_action( 'after_setup_theme', 'wpeb_setup' );

/**
 * Enqueue front-end assets.
 */
function wpeb_assets() {
	$style_path = get_theme_file_path( 'style.css' );

	wp_enqueue_style(
		'wp-education-blocks',
		get_stylesheet_uri(),
		array(),
		file_exists( $style_path ) ? filemtime( $style_path ) : WPEB_VERSION
	);

	wp_enqueue_script(
		'wp-education-blocks',
		get_theme_file_uri( 'js/theme.js' ),
		array(),
		WPEB_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'wpeb_assets' );

/**
 * Register a block pattern category for the theme's section patterns.
 */
function wpeb_register_pattern_category() {
	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category( 'wpedu', array(
			'label'       => __( 'Education', 'wp-education-blocks' ),
			'description' => __( 'Sections for the WordPress Education landing page.', 'wp-education-blocks' ),
		) );
	}
}
add_action( 'init', 'wpeb_register_pattern_category' );
