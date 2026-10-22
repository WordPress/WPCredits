/**
 * Editor registration for a program card (theme 1.32.0): one program of the Program index.
 *
 * Hand-written against the `wp.*` globals rather than built from JSX, so the theme has no build step.
 * The card is drawn as the page draws it, and its words are edited where they are shown: what it is
 * inline, as rich text with bold and italic, and its name and its time as plain text, inline too, with
 * no format, as the page prints them in a heading and a fact. What cannot be typed in place is
 * in the block's settings: its status, its audiences (the filter chips that show it), its slug (its
 * address on the page), what a student gets, one to a line, and its links, each a label and an
 * address.
 *
 * The block is dynamic: it saves nothing but its attributes, and the server draws the card
 * (render.php), so a later change to the card's markup never leaves the editor saying the block holds
 * unexpected content.
 */
( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var _x = i18n._x;
	var RichText = blockEditor.RichText;
	var FORMATS = [ 'core/bold', 'core/italic' ];
	var AUDIENCES = [
		[ 'student', __( 'For students', 'wpcredits-theme' ) ],
		[ 'faculty', __( 'For faculty', 'wpcredits-theme' ) ],
		[ 'career', __( 'Career and credentials', 'wpcredits-theme' ) ],
	];

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

	function stop( event ) {
		event.preventDefault();
	}

	blocks.registerBlockType( 'wpcredits/program-card', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var audiences = attributes.audiences || [];
			var links = attributes.links || [];
			var gets = tidy( attributes.gets );
			var live = 'development' !== attributes.status;
			var blockProps = blockEditor.useBlockProps( {
				className: 'wpc-hub-program',
				'data-audiences': audiences.join( ' ' ),
			} );

			function set( key ) {
				return function ( value ) {
					var change = {};

					change[ key ] = value;
					setAttributes( change );
				};
			}

			function audience( name ) {
				return function ( checked ) {
					setAttributes( {
						audiences: AUDIENCES.map( function ( one ) {
							return one[ 0 ];
						} ).filter( function ( one ) {
							return one === name ? checked : -1 !== audiences.indexOf( one );
						} ),
					} );
				};
			}

			function setLink( index, key ) {
				return function ( value ) {
					setAttributes( {
						links: links.map( function ( link, at ) {
							var copy = { label: link.label || '', url: link.url || '' };

							if ( at === index ) {
								copy[ key ] = value;
							}

							return copy;
						} ),
					} );
				};
			}

			function removeLink( index ) {
				return function () {
					setAttributes( {
						links: links.filter( function ( link, at ) {
							return at !== index;
						} ),
					} );
				};
			}

			var settings = el(
				blockEditor.InspectorControls,
				null,
				el(
					components.PanelBody,
					{ title: __( 'Program card', 'wpcredits-theme' ) },
					el( components.SelectControl, {
						label: __( 'Status', 'wpcredits-theme' ),
						value: live ? 'live' : 'development',
						options: [
							{ value: 'live', label: _x( 'Live', 'a program running now', 'wpcredits-theme' ) },
							{ value: 'development', label: _x( 'In development', 'a program not offered yet', 'wpcredits-theme' ) },
						],
						help: __( 'A program in development is shown on the page only while the hub shows its planned content.', 'wpcredits-theme' ),
						onChange: set( 'status' ),
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					} ),
					el(
						'fieldset',
						null,
						el( 'legend', null, __( 'Shown under the filter chips', 'wpcredits-theme' ) ),
						AUDIENCES.map( function ( one ) {
							return el( components.CheckboxControl, {
								key: one[ 0 ],
								label: one[ 1 ],
								checked: -1 !== audiences.indexOf( one[ 0 ] ),
								onChange: audience( one[ 0 ] ),
								__nextHasNoMarginBottom: true,
							} );
						} )
					),
					el( components.TextControl, {
						label: __( 'Slug', 'wpcredits-theme' ),
						value: attributes.slug || '',
						help: __( 'The card\'s address on the page is #program- and its slug, as a pathway step links to it: lower-case letters, digits and hyphens.', 'wpcredits-theme' ),
						onChange: set( 'slug' ),
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					} )
				),
				el(
					components.PanelBody,
					{ title: __( 'You get', 'wpcredits-theme' ) },
					el( components.TextareaControl, {
						label: __( 'What a student gets, one to a line', 'wpcredits-theme' ),
						value: lines( attributes.gets ),
						onChange: function ( value ) {
							setAttributes( { gets: value.split( '\n' ) } );
						},
						onBlur: function () {
							if ( lines( tidy( attributes.gets ) ) !== lines( attributes.gets ) ) {
								setAttributes( { gets: tidy( attributes.gets ) } );
							}
						},
						__nextHasNoMarginBottom: true,
					} )
				),
				el(
					components.PanelBody,
					{ title: __( 'Links', 'wpcredits-theme' ) },
					links.map( function ( link, index ) {
						return el(
							'div',
							{ key: index },
							el( components.TextControl, {
								label: __( 'Label', 'wpcredits-theme' ),
								value: link.label || '',
								onChange: setLink( index, 'label' ),
								__nextHasNoMarginBottom: true,
								__next40pxDefaultSize: true,
							} ),
							el( components.TextControl, {
								label: __( 'Address', 'wpcredits-theme' ),
								value: link.url || '',
								help: __( 'An address, an anchor on the page such as #programs, or institution_application, sponsor_application or partners for the site\'s own pages.', 'wpcredits-theme' ),
								onChange: setLink( index, 'url' ),
								__nextHasNoMarginBottom: true,
								__next40pxDefaultSize: true,
							} ),
							el( components.Button, { variant: 'link', isDestructive: true, onClick: removeLink( index ) }, __( 'Remove link', 'wpcredits-theme' ) )
						);
					} ),
					el(
						components.Button,
						{
							variant: 'secondary',
							onClick: function () {
								setAttributes( { links: links.concat( [ { label: '', url: '' } ] ) } );
							},
						},
						__( 'Add link', 'wpcredits-theme' )
					)
				)
			);

			var shownLinks = links.filter( function ( link ) {
				return link && ( link.label || '' ).trim() && ( link.url || '' ).trim();
			} );

			return el(
				element.Fragment,
				null,
				settings,
				el(
					'article',
					blockProps,
					el(
						'p',
						{ className: 'wpc-hub-program__meta' },
						el(
							'span',
							{ className: 'wpc-hub-tag wpc-hub-tag--' + ( live ? 'live' : 'development' ) },
							live ? _x( 'Live', 'a program running now', 'wpcredits-theme' ) : _x( 'In development', 'a program not offered yet', 'wpcredits-theme' )
						)
					),
					el( RichText, {
						tagName: 'h3',
						className: 'wpc-hub-program__name',
						value: attributes.name,
						onChange: set( 'name' ),
						placeholder: __( 'Program name', 'wpcredits-theme' ),
						allowedFormats: [],
					} ),
					el( RichText, {
						tagName: 'p',
						className: 'wpc-hub-program__what',
						value: attributes.what,
						onChange: set( 'what' ),
						placeholder: __( 'What the program is, in a sentence or two', 'wpcredits-theme' ),
						allowedFormats: FORMATS,
					} ),
					el(
						'dl',
						{ className: 'wpc-hub-program__facts' },
						el(
							'div',
							null,
							el( 'dt', null, __( 'Time', 'wpcredits-theme' ) ),
							el( RichText, {
								tagName: 'dd',
								value: attributes.time,
								onChange: set( 'time' ),
								placeholder: __( 'How long it takes', 'wpcredits-theme' ),
								allowedFormats: [],
							} )
						),
						gets.length ? el(
							'div',
							null,
							el( 'dt', null, __( 'You get', 'wpcredits-theme' ) ),
							el(
								'dd',
								null,
								el(
									'ul',
									{ className: 'wpc-hub-items' },
									gets.map( function ( item, index ) {
										return el( 'li', { key: index }, item );
									} )
								)
							)
						) : null
					),
					shownLinks.length ? el(
						'p',
						{ className: 'wpc-hub-program__links' },
						shownLinks.map( function ( link, index ) {
							return el( 'a', { key: index, href: link.url, onClick: stop }, link.label, el( 'span', { 'aria-hidden': 'true' }, ' →' ) );
						} )
					) : null
				)
			);
		},

		// Dynamic: nothing is saved but the attributes, and the server draws the card.
		save: function () {
			return null;
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n ) );
