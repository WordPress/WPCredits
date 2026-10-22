/**
 * Editor registration for a band of courses (theme 1.32.0): a program's courses under the Program index.
 *
 * Hand-written against the `wp.*` globals rather than built from JSX, so the theme has no build step.
 * The band is drawn as the page draws it, and its words are edited where they are shown: its eyebrow,
 * heading and lede inline, as rich text. It holds Course cards and nothing else, and a new band starts
 * with one empty card (blocks/course-card/index.js). Its anchor, the band's address on the page that a
 * program card's link lands on, is in the block's settings.
 *
 * The block is dynamic: it saves its courses alone, as block comments with no markup of their own,
 * and the server draws the band from them, so a later change to the band's markup never leaves the
 * editor saying the block holds unexpected content.
 */
( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var RichText = blockEditor.RichText;
	var FORMATS = [ 'core/bold', 'core/italic' ];

	blocks.registerBlockType( 'wpcredits/course-band', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = blockEditor.useBlockProps( { className: 'wpc-hub-courses' } );
			var innerBlocksProps = blockEditor.useInnerBlocksProps(
				{ className: 'wpc-hub-courses__grid' },
				{
					allowedBlocks: [ 'wpcredits/course-card' ],
					template: [ [ 'wpcredits/course-card' ] ],
				}
			);

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
						{ title: __( 'Courses band', 'wpcredits-theme' ) },
						el( components.TextControl, {
							label: __( 'Anchor', 'wpcredits-theme' ),
							value: attributes.anchor || '',
							help: __( 'The band\'s address on the page, after a #: a program card\'s link to #wordpress-credits-courses lands on the band anchored wordpress-credits-courses. Lower-case letters, digits and hyphens.', 'wpcredits-theme' ),
							onChange: set( 'anchor' ),
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} )
					)
				),
				el(
					'section',
					blockProps,
					el(
						'div',
						{ className: 'wp-block-group wpc-hub-head' },
						el( RichText, {
							tagName: 'p',
							className: 'is-style-eyebrow',
							value: attributes.eyebrow,
							onChange: set( 'eyebrow' ),
							placeholder: __( 'Eyebrow', 'wpcredits-theme' ),
							allowedFormats: [],
						} ),
						el( RichText, {
							tagName: 'h3',
							value: attributes.heading,
							onChange: set( 'heading' ),
							placeholder: __( 'Heading', 'wpcredits-theme' ),
							allowedFormats: [],
						} ),
						el( RichText, {
							tagName: 'p',
							className: 'wpc-hub-lede',
							value: attributes.lede,
							onChange: set( 'lede' ),
							placeholder: __( 'A sentence on how to choose', 'wpcredits-theme' ),
							allowedFormats: FORMATS,
						} )
					),
					el( 'ul', innerBlocksProps )
				)
			);
		},

		// Dynamic: the courses are saved as blocks, and the server draws the band from them.
		save: function () {
			return el( blockEditor.InnerBlocks.Content );
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n ) );
