/**
 * Editor script for the "Sponsors" block.
 *
 * Written in plain ES5 against the wp.* globals so the plugin ships without a
 * build step. The block is server-rendered, so the editor shows a live preview
 * via ServerSideRender rather than reimplementing the listing markup in
 * JavaScript.
 */
( function ( blocks, blockEditor, components, element, i18n, serverSideRender ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var ServerSideRender = serverSideRender;

	// The two shapes render() understands; anything else falls back to the full list, which lists
	// every sponsor in one alphabetical list, as the partner page does.
	var SHAPE_OPTIONS = [
		{ label: __( 'Full list', 'wpcredits-program-manager' ), value: 'full' },
		{ label: __( 'Strip of logos', 'wpcredits-program-manager' ), value: 'strip' },
	];

	// The ladder, lowest first, matching WPCPM_Sponsor_Tiers::KEYS: the lowest tier the list shows.
	var FLOOR_OPTIONS = [
		{ label: __( 'Friends', 'wpcredits-program-manager' ), value: 'mentor' },
		{ label: __( 'Supporters', 'wpcredits-program-manager' ), value: 'supporter' },
		{ label: __( 'Backers', 'wpcredits-program-manager' ), value: 'backer' },
		{ label: __( 'Champions', 'wpcredits-program-manager' ), value: 'champion' },
		{ label: __( 'Partners', 'wpcredits-program-manager' ), value: 'partner' },
	];

	blocks.registerBlockType( 'wpcpm/sponsors', {
		edit: function ( props ) {
			var blockProps = blockEditor.useBlockProps();

			return el(
				'div',
				blockProps,
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __( 'Listing', 'wpcredits-program-manager' ) },
						el( components.SelectControl, {
							label: __( 'Shape', 'wpcredits-program-manager' ),
							value: props.attributes.variant,
							options: SHAPE_OPTIONS,
							onChange: function ( value ) {
								props.setAttributes( { variant: value } );
							},
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} ),
						el( components.SelectControl, {
							label: __( 'Lowest tier shown', 'wpcredits-program-manager' ),
							help: __(
								'The strip always shows the logos from Supporter up and counts the Friends.',
								'wpcredits-program-manager'
							),
							value: props.attributes.floor,
							options: FLOOR_OPTIONS,
							onChange: function ( value ) {
								props.setAttributes( { floor: value } );
							},
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} )
					)
				),
				el(
					components.Disabled,
					null,
					el( ServerSideRender, {
						block: 'wpcpm/sponsors',
						attributes: props.attributes,
						EmptyResponsePlaceholder: function () {
							return el(
								components.Placeholder,
								{ label: __( 'Sponsors', 'wpcredits-program-manager' ) },
								__(
									'The sponsors list shows here once Sponsor tiers is switched on in Settings and there is a sponsor to show.',
									'wpcredits-program-manager'
								)
							);
						},
					} )
				)
			);
		},

		// Server-rendered: nothing is stored in post content.
		save: function () {
			return null;
		},
	} );
}(
	window.wp.blocks,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.element,
	window.wp.i18n,
	window.wp.serverSideRender
) );
