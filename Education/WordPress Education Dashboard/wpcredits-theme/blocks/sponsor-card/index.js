/**
 * Editor registration for the sponsors card: a preview of what the server renders, and no settings.
 *
 * Hand-written against the `wp.*` globals rather than built from JSX, so the theme has no build step.
 * WordPress 7.1 shows a block that has no editor script as one the site "doesn't include support for",
 * so the block registers itself here and lets the server draw it, as the sign-in section does. The
 * server draws nothing while the Partners page is not public, so the preview then says why the card is
 * not there, where it would otherwise be an empty box.
 */
( function ( blocks, element, blockEditor, i18n, ServerSideRender ) {
	'use strict';

	var el = element.createElement;

	blocks.registerBlockType( 'wpcredits/sponsor-card', {
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps(),
				el( ServerSideRender, {
					block: 'wpcredits/sponsor-card',
					EmptyResponsePlaceholder: function () {
						return el( 'p', { className: 'wpc-hub-door__text' }, i18n.__( 'The Sponsors card shows here while the Partners page is public.', 'wpcredits-theme' ) );
					},
				} )
			);
		},

		// Server-rendered: nothing is stored in post content.
		save: function () {
			return null;
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n, window.wp.serverSideRender ) );
