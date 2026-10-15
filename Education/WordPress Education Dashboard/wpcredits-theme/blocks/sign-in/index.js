/**
 * Editor registration for the sign-in section: a preview of what the server renders, and no settings.
 *
 * Hand-written against the `wp.*` globals rather than built from JSX, so the theme has no build step.
 * WordPress 7.1 shows a block that has no editor script as one the site "doesn't include support
 * for", so the block registers itself here and lets the server draw it. The texts are the theme's
 * own and translated there; nothing is set in the editor.
 */
( function ( blocks, element, blockEditor, ServerSideRender ) {
	'use strict';

	var el = element.createElement;

	blocks.registerBlockType( 'wpcredits/sign-in', {
		edit: function () {
			// Wide, as the front end draws it: the block sits directly in the template's constrained main, which
			// caps a block without `alignwide` at the content width.
			return el( 'div', blockEditor.useBlockProps( { className: 'alignwide' } ), el( ServerSideRender, { block: 'wpcredits/sign-in' } ) );
		},

		// Server-rendered: nothing is stored in post content.
		save: function () {
			return null;
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.serverSideRender ) );
