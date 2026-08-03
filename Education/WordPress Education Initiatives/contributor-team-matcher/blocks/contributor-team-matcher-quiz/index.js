/* global wp */
( function () {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var createElement     = wp.element.createElement;
	var useBlockProps     = wp.blockEditor.useBlockProps;
	var __               = wp.i18n.__;

	registerBlockType( 'contributor-team-matcher/quiz', {
		edit: function () {
			var blockProps = useBlockProps( { className: 'ctm-block-placeholder' } );

			return createElement(
				'div', blockProps,
				createElement( 'span', {
					className:   'dashicons dashicons-groups ctm-block-placeholder__icon',
					'aria-hidden': 'true',
				} ),
				createElement( 'strong', { className: 'ctm-block-placeholder__title' },
					__( 'Contributor Team Matcher Quiz', 'find-your-team' )
				),
				createElement( 'p', { className: 'ctm-block-placeholder__description' },
					__( 'The interactive contributor quiz will be rendered here on the frontend.', 'find-your-team' )
				)
			);
		},

		save: function () {
			// Server-side rendered via render_callback — save returns null.
			return null;
		},
	} );
} )();
