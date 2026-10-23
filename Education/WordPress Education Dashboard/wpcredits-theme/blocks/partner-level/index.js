/**
 * Editor registration for a partnership level (theme 1.32.1): one card of the Partner levels.
 *
 * Hand-written against the `wp.*` globals rather than built from JSX, so the theme has no build step.
 * The card is drawn as the page draws it, and its words are edited where they are shown: its name, its
 * amount and the line before its list inline as rich text, the line alone with bold and italic, and its
 * tag after the name, inline too, shown while the card is selected or once it has words, since most
 * levels have none. What cannot be typed in place is in the block's settings: the short amount the
 * Levels at a glance table's head shows, and what the level includes, one to a line, shown on the card
 * as its list and in the table as its rows.
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

	// Whether a text as rich text keeps it says nothing, as the server judges it: no character but
	// white space once its tags are taken out.
	function blank( value ) {
		return '' === String( value || '' ).replace( /<[^>]*>/g, '' ).replace( /&nbsp;|&#160;|\s/g, '' );
	}

	blocks.registerBlockType( 'wpcredits/partner-level', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var items = tidy( attributes.items );
			var tagged = ! blank( attributes.tag );
			var blockProps = blockEditor.useBlockProps( {
				className: 'wpc-partners-level' + ( tagged ? ' wpc-partners-level--program' : '' ),
			} );

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
						{ title: __( 'Partner level', 'wpcredits-theme' ) },
						el( components.TextControl, {
							label: __( 'Short amount', 'wpcredits-theme' ),
							value: attributes.short || '',
							help: __( 'The amount the Levels at a glance table shows under the level\'s name, such as $5,000.', 'wpcredits-theme' ),
							onChange: set( 'short' ),
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} ),
						el( components.TextareaControl, {
							label: __( 'What this level includes, one to a line', 'wpcredits-theme' ),
							value: lines( attributes.items ),
							help: __( 'Each line is also a row of the Levels at a glance table, which says Yes from this level up.', 'wpcredits-theme' ),
							onChange: function ( value ) {
								setAttributes( { items: value.split( '\n' ) } );
							},
							onBlur: function () {
								if ( lines( tidy( attributes.items ) ) !== lines( attributes.items ) ) {
									setAttributes( { items: tidy( attributes.items ) } );
								}
							},
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el(
					'li',
					blockProps,
					el(
						'h3',
						{ className: 'wpc-partners-level__name' },
						el( RichText, {
							tagName: 'span',
							value: attributes.name,
							onChange: set( 'name' ),
							placeholder: __( 'Level name', 'wpcredits-theme' ),
							allowedFormats: [],
						} ),
						tagged || props.isSelected ? ' ' : null,
						tagged || props.isSelected ? el( RichText, {
							tagName: 'span',
							className: 'wpc-partners-tag',
							value: attributes.tag,
							onChange: set( 'tag' ),
							placeholder: __( 'A tag, if the level has one', 'wpcredits-theme' ),
							allowedFormats: [],
						} ) : null
					),
					el( RichText, {
						tagName: 'p',
						className: 'wpc-partners-level__amount',
						value: attributes.amount,
						onChange: set( 'amount' ),
						placeholder: __( 'What it costs', 'wpcredits-theme' ),
						allowedFormats: [],
					} ),
					el( RichText, {
						tagName: 'p',
						className: 'wpc-partners-level__lead',
						value: attributes.lead,
						onChange: set( 'lead' ),
						placeholder: __( 'The line before what it includes', 'wpcredits-theme' ),
						allowedFormats: FORMATS,
					} ),
					items.length ? el(
						'ul',
						{ className: 'wpc-partners-level__items' },
						items.map( function ( item, index ) {
							return el( 'li', { key: index }, item );
						} )
					) : el( 'p', { className: 'wpc-partners-level__hint' }, __( 'Add what this level includes in the block\'s settings, one to a line.', 'wpcredits-theme' ) )
				)
			);
		},

		// Dynamic: nothing is saved but the attributes, and the server draws the card.
		save: function () {
			return null;
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n ) );
