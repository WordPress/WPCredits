/**
 * Editor registration for "Find where you fit" (theme 1.32.0): the Field of study rows block.
 *
 * Hand-written against the `wp.*` globals rather than built from JSX, so the theme has no build step.
 * The flow is drawn as the page draws it: the header line's three names are edited inline, and the
 * block holds Field of study rows and nothing else, a new list starting with one empty row
 * (blocks/fit-row/index.js). The two labels a screen reader hears in each row, which the page does not
 * show, are in the block's settings; the list hands them to its rows.
 *
 * The block is dynamic: it saves its rows alone, as block comments with no markup of their own, and
 * the server draws the flow from them, so a later change to its markup never leaves the editor saying
 * the block holds unexpected content.
 */
( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var RichText = blockEditor.RichText;

	blocks.registerBlockType( 'wpcredits/fit-list', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = blockEditor.useBlockProps( { className: 'wpc-hub-flow' } );
			var innerBlocksProps = blockEditor.useInnerBlocksProps(
				{ className: 'wpc-hub-flow__rows', role: 'list' },
				{
					allowedBlocks: [ 'wpcredits/fit-row' ],
					template: [ [ 'wpcredits/fit-row' ] ],
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
						{ title: __( 'Read by screen readers', 'wpcredits-theme' ) },
						el( 'p', null, __( 'The page shows the header line\'s names above the rows; a screen reader hears these labels in each row instead.', 'wpcredits-theme' ) ),
						el( components.TextControl, {
							label: __( 'Before the teams', 'wpcredits-theme' ),
							value: attributes.teamsLabel || '',
							onChange: set( 'teamsLabel' ),
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} ),
						el( components.TextControl, {
							label: __( 'Before what the student will have to show', 'wpcredits-theme' ),
							value: attributes.proofLabel || '',
							onChange: set( 'proofLabel' ),
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} )
					)
				),
				el(
					'div',
					blockProps,
					el(
						'div',
						{ className: 'wpc-hub-flow__head' },
						el( RichText, {
							tagName: 'span',
							className: 'wpc-hub-flow__step wpc-hub-flow__step--field',
							value: attributes.fieldHeading,
							onChange: set( 'fieldHeading' ),
							placeholder: __( 'Your field of study', 'wpcredits-theme' ),
							allowedFormats: [],
						} ),
						el( RichText, {
							tagName: 'span',
							className: 'wpc-hub-flow__step wpc-hub-flow__step--teams',
							value: attributes.teamsHeading,
							onChange: set( 'teamsHeading' ),
							placeholder: __( 'The team you join', 'wpcredits-theme' ),
							allowedFormats: [],
						} ),
						el( RichText, {
							tagName: 'span',
							className: 'wpc-hub-flow__step wpc-hub-flow__step--proof',
							value: attributes.proofHeading,
							onChange: set( 'proofHeading' ),
							placeholder: __( 'What you\'ll have to show', 'wpcredits-theme' ),
							allowedFormats: [],
						} )
					),
					el( 'ul', innerBlocksProps )
				)
			);
		},

		// Dynamic: the rows are saved as blocks, and the server draws the flow from them.
		save: function () {
			return el( blockEditor.InnerBlocks.Content );
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n ) );
