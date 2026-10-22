/**
 * Editor registration for a row of "Find where you fit" (theme 1.32.0): one field of study.
 *
 * Hand-written against the `wp.*` globals rather than built from JSX, so the theme has no build step.
 * The row is drawn as the page draws it, its arrows and check mark among it, and its words are edited
 * where they are shown: the field and what the student will have to show inline, as rich text. Its
 * teams, a list, are in the block's settings, one to a line, and shown as tags.
 *
 * The block is dynamic: it saves nothing but its attributes, and the server draws the row
 * (render.php), so a later change to its markup never leaves the editor saying the block holds
 * unexpected content.
 */
( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var RichText = blockEditor.RichText;
	var FORMATS = [ 'core/bold', 'core/italic' ];

	// A list edited one item to a line keeps what is typed while the field has the focus, so a new
	// line can be started; when the field loses the focus, each item is trimmed and an empty one goes.
	function lines( list ) {
		return ( list || [] ).join( '\n' );
	}

	function tidy( list ) {
		return ( list || [] )
			.map( function ( item ) {
				return String( item ).trim();
			} )
			.filter( function ( item ) {
				return '' !== item;
			} );
	}

	function arrow() {
		return el(
			'svg',
			{ className: 'wpc-hub-flow__arrow', viewBox: '0 0 20 20', width: 20, height: 20, fill: 'none', stroke: 'currentColor', strokeWidth: 1.6, 'aria-hidden': 'true', focusable: 'false' },
			el( 'path', { d: 'M1.5 10h16M10.5 3l7 7-7 7' } )
		);
	}

	function check() {
		return el(
			'svg',
			{ className: 'wpc-hub-flow__check', viewBox: '0 0 16 16', width: 16, height: 16, fill: 'none', stroke: 'currentColor', strokeWidth: 2, 'aria-hidden': 'true', focusable: 'false' },
			el( 'path', { d: 'M2.5 8.5 6 12l7.5-8' } )
		);
	}

	blocks.registerBlockType( 'wpcredits/fit-row', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var teams = tidy( attributes.teams );
			var blockProps = blockEditor.useBlockProps( { className: 'wpc-hub-flow__row' } );

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
						{ title: __( 'Field of study row', 'wpcredits-theme' ) },
						el( components.TextareaControl, {
							label: __( 'The teams this field joins, one to a line', 'wpcredits-theme' ),
							value: lines( attributes.teams ),
							onChange: function ( value ) {
								setAttributes( { teams: value.split( '\n' ) } );
							},
							onBlur: function () {
								if ( lines( tidy( attributes.teams ) ) !== lines( attributes.teams ) ) {
									setAttributes( { teams: tidy( attributes.teams ) } );
								}
							},
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el(
					'li',
					blockProps,
					el( RichText, {
						tagName: 'p',
						className: 'wpc-hub-flow__field',
						value: attributes.field,
						onChange: set( 'field' ),
						placeholder: __( 'A field of study', 'wpcredits-theme' ),
						allowedFormats: FORMATS,
					} ),
					arrow(),
					el(
						'div',
						{ className: 'wpc-hub-flow__teams' },
						teams.length ? el(
							'ul',
							{ className: 'wpc-hub-flow__tags', role: 'list' },
							teams.map( function ( team, index ) {
								return el( 'li', { key: index, className: 'wpc-hub-flow__tag' }, team );
							} )
						) : el( 'span', { className: 'wpc-hub-flow__step' }, __( 'Add the teams in the block\'s settings.', 'wpcredits-theme' ) )
					),
					arrow(),
					el(
						'p',
						{ className: 'wpc-hub-flow__proof' },
						check(),
						el( RichText, {
							tagName: 'span',
							className: 'wpc-hub-flow__shown',
							value: attributes.proof,
							onChange: set( 'proof' ),
							placeholder: __( 'What the student will have to show', 'wpcredits-theme' ),
							allowedFormats: FORMATS,
						} )
					)
				)
			);
		},

		// Dynamic: nothing is saved but the attributes, and the server draws the row.
		save: function () {
			return null;
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n ) );
