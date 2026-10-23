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
 *
 * The five words are the translated strings until another is typed (theme 1.32.1): block.json holds no
 * English default, since a default is stored with the block and never translated, so a word the editor
 * has not saved shows as the translation, as the server draws it (blocks/fit-list/render.php). A word
 * saved empty is a word taken out on purpose, and shows empty.
 */
( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var RichText = blockEditor.RichText;

	// One of the list's words: as saved, or the translated default while none is saved, as the server
	// draws it. Only a missing word falls back; one saved empty stays empty.
	function word( value, fallback ) {
		return undefined === value ? fallback : value;
	}

	// A plain text as rich text keeps it: an ampersand or an angle bracket stays a character.
	function rich( text ) {
		return String( text ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
	}

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
							value: word( attributes.teamsLabel, __( 'The team you join:', 'wpcredits-theme' ) ),
							onChange: set( 'teamsLabel' ),
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} ),
						el( components.TextControl, {
							label: __( 'Before what the student will have to show', 'wpcredits-theme' ),
							value: word( attributes.proofLabel, __( 'What you\'ll have to show:', 'wpcredits-theme' ) ),
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
							value: word( attributes.fieldHeading, rich( __( 'Your field of study', 'wpcredits-theme' ) ) ),
							onChange: set( 'fieldHeading' ),
							placeholder: __( 'Your field of study', 'wpcredits-theme' ),
							allowedFormats: [],
						} ),
						el( RichText, {
							tagName: 'span',
							className: 'wpc-hub-flow__step wpc-hub-flow__step--teams',
							value: word( attributes.teamsHeading, rich( __( 'The team you join', 'wpcredits-theme' ) ) ),
							onChange: set( 'teamsHeading' ),
							placeholder: __( 'The team you join', 'wpcredits-theme' ),
							allowedFormats: [],
						} ),
						el( RichText, {
							tagName: 'span',
							className: 'wpc-hub-flow__step wpc-hub-flow__step--proof',
							value: word( attributes.proofHeading, rich( __( 'What you\'ll have to show', 'wpcredits-theme' ) ) ),
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
