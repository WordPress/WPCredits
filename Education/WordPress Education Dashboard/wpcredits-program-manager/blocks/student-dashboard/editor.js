/**
 * Editor script for the "My Students (Mentor)" block.
 *
 * Written in plain ES5 against the wp.* globals so the plugin ships without a
 * build step. The block is server-rendered, so the editor shows a live preview
 * via ServerSideRender rather than reimplementing the markup in JavaScript.
 */
( function ( blocks, blockEditor, components, element, i18n, serverSideRender ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var ServerSideRender = serverSideRender;

	blocks.registerBlockType( 'wpcpm/student-dashboard', {
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
							help: __( 'Shown above the student list. Leave blank to hide it.', 'wpcredits-program-manager' ),
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
						block: 'wpcpm/student-dashboard',
						attributes: props.attributes,
						EmptyResponsePlaceholder: function () {
							return el(
								components.Placeholder,
								{ label: __( 'My Program', 'wpcredits-program-manager' ) },
								__(
									'Nothing to preview — this list is built from the student viewing the page.',
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
