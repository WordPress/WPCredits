/**
 * Editor registration for the viewer chip.
 *
 * Hand-written against the `wp.*` globals rather than built from JSX, so the
 * theme has no build step. The preview is deliberately static: the real chip
 * depends on who is signed in and whether they hold the Mentor role, and neither
 * is a useful question to ask inside the Site Editor.
 */
( function ( blocks, element, blockEditor, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'wpcredits/viewer-chip', {
		edit: function () {
			var props = blockEditor.useBlockProps( {
				className: 'wpc-viewer wpc-viewer--preview',
			} );

			return el(
				'div',
				props,
				el( 'span', { className: 'wpc-viewer__link is-current' }, __( 'My Students', 'wpcredits-theme' ) ),
				el( 'span', { className: 'wpc-viewer__logout' }, __( 'Log out', 'wpcredits-theme' ) ),
				el( 'span', { className: 'wpc-viewer__avatar', 'data-initials': 'MP' } )
			);
		},

		// Server-rendered: nothing is stored in post content.
		save: function () {
			return null;
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n ) );
