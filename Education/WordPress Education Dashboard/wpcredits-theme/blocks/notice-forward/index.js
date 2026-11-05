/**
 * Editor registration for the Notice template's forward: a preview of the line the server draws, and no
 * settings. The countdown never runs in the editor: only the front end loads the view script.
 *
 * Hand-written against the `wp.*` globals rather than built from JSX, so the theme has no build step.
 * WordPress 7.1 shows a block that has no editor script as one the site "doesn't include support for", so
 * the block registers itself here and lets the server draw it, as the sign-in section does.
 */
( function ( blocks, element, blockEditor, ServerSideRender ) {
	'use strict';

	var el = element.createElement;

	blocks.registerBlockType( 'wpcredits/notice-forward', {
		edit: function () {
			return el( 'div', blockEditor.useBlockProps(), el( ServerSideRender, { block: 'wpcredits/notice-forward' } ) );
		},

		// Server-rendered: nothing is stored in post content.
		save: function () {
			return null;
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.serverSideRender ) );
