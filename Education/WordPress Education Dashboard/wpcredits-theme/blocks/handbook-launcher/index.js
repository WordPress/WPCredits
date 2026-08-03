/**
 * Editor registration for the handbook launcher.
 *
 * Hand-written against the `wp.*` globals rather than built from JSX, so the theme
 * keeps its no-build-step rule. The preview is static: whether the real button
 * appears depends on the plugin being active, the assistant being switched on and
 * who is signed in, and none of those is a useful question inside the Site Editor.
 */
( function ( blocks, element, blockEditor, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'wpcredits/handbook-launcher', {
		edit: function () {
			var props = blockEditor.useBlockProps( {
				className: 'wpc-ask wpc-ask--preview',
			} );

			return el( 'span', props, __( 'Need help?', 'wpcredits-theme' ) );
		},

		// Server-rendered: nothing is stored in post content.
		save: function () {
			return null;
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n ) );
