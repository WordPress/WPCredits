/**
 * Editor registration for the program figure: a preview of what the server renders, and no settings.
 *
 * Hand-written against the `wp.*` globals rather than built from JSX, so the theme has no build step.
 * WordPress 7.1 shows a block that has no editor script as one the site "doesn't include support
 * for", so the block registers itself here and lets the server draw it, as the program index and the
 * pathway do. Unlike theirs, this block has attributes: each instance hands the server its own, so
 * the hero's four figures and the homepage's mentors card each preview their own stat.
 */
( function ( blocks, element, blockEditor, ServerSideRender ) {
	'use strict';

	var el = element.createElement;

	blocks.registerBlockType( 'wpcredits/program-stat', {
		edit: function ( props ) {
			return el( 'div', blockEditor.useBlockProps(), el( ServerSideRender, { block: 'wpcredits/program-stat', attributes: props.attributes } ) );
		},

		// Server-rendered: nothing is stored in post content.
		save: function () {
			return null;
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.serverSideRender ) );
