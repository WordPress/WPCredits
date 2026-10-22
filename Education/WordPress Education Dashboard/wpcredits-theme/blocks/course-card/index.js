/**
 * Editor registration for a course card (theme 1.32.0): one course of a Courses band.
 *
 * Hand-written against the `wp.*` globals rather than built from JSX, so the theme has no build step.
 * The card is drawn as the page draws it, and its words are edited where they are shown: its name, the
 * line above the name (drawn there by the stylesheet) and what it asks, inline as rich text. Its
 * address, the course's public page its name links to, is in the block's settings.
 *
 * The block is dynamic: it saves nothing but its attributes, and the server draws the card
 * (render.php), so a later change to its markup never leaves the editor saying the block holds
 * unexpected content.
 */
( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var RichText = blockEditor.RichText;
	var FORMATS = [ 'core/bold', 'core/italic' ];

	blocks.registerBlockType( 'wpcredits/course-card', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = blockEditor.useBlockProps( { className: 'wpc-hub-course' } );

			function set( key ) {
				return function ( value ) {
					var change = {};

					change[ key ] = value;
					setAttributes( change );
				};
			}

			return el(
				element.Fragment,
				null,
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __( 'Course card', 'wpcredits-theme' ) },
						el( components.TextControl, {
							label: __( 'Address', 'wpcredits-theme' ),
							value: attributes.url || '',
							help: __( 'The course\'s public page, which its name links to. With none, the name is not a link.', 'wpcredits-theme' ),
							onChange: set( 'url' ),
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} )
					)
				),
				el(
					'li',
					blockProps,
					el( RichText, {
						tagName: 'h4',
						className: 'wpc-hub-course__name',
						value: attributes.name,
						onChange: set( 'name' ),
						placeholder: __( 'Course name', 'wpcredits-theme' ),
						allowedFormats: [],
					} ),
					el( RichText, {
						tagName: 'p',
						className: 'wpc-hub-course__meta',
						value: attributes.eyebrow,
						onChange: set( 'eyebrow' ),
						placeholder: __( 'Its hours, or who it is for', 'wpcredits-theme' ),
						allowedFormats: [],
					} ),
					el( RichText, {
						tagName: 'p',
						className: 'wpc-hub-course__line',
						value: attributes.line,
						onChange: set( 'line' ),
						placeholder: __( 'What it asks, in a line', 'wpcredits-theme' ),
						allowedFormats: FORMATS,
					} )
				)
			);
		},

		// Dynamic: nothing is saved but the attributes, and the server draws the card.
		save: function () {
			return null;
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n ) );
