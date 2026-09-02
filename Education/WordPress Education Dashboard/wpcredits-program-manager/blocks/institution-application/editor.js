/**
 * Editor script for the "Institution Application Form" block.
 *
 * Written in plain ES5 against the wp.* globals so the plugin ships without a
 * build step. The block is server-rendered, and what the server renders for the
 * editor is deliberately not the form: a live preview would mint a nonce and a
 * single-use dwell token on every keystroke that reloads the block, tokens
 * nobody will ever post and each one claiming a row. So the preview here is the
 * static summary the render callback returns under REST_REQUEST - the groups of
 * questions and how many countries the list offers.
 */
( function ( blocks, blockEditor, components, element, i18n, serverSideRender ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var ServerSideRender = serverSideRender;

	blocks.registerBlockType( 'wpcpm/institution-application', {
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
						{ title: __( 'Heading', 'wpcredits-program-manager' ) },
						el( components.TextControl, {
							label: __( 'Optional heading', 'wpcredits-program-manager' ),
							help: __( 'Shown above the form. Leave blank to hide it.', 'wpcredits-program-manager' ),
							value: props.attributes.title || '',
							onChange: function ( value ) {
								props.setAttributes( { title: value } );
							},
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el(
					components.Disabled,
					null,
					el( ServerSideRender, {
						block: 'wpcpm/institution-application',
						attributes: props.attributes,
						EmptyResponsePlaceholder: function () {
							return el(
								components.Placeholder,
								{ label: __( 'Institution Application Form', 'wpcredits-program-manager' ) },
								__(
									'Nothing to preview. The form is not shown until the site has a published privacy policy and the program is taking applications.',
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
