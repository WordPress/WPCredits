/**
 * Editor UI for the community-crm/login-form block.
 *
 * Written in plain ES5 with wp.element.createElement instead of JSX so the
 * theme needs no build step. The block is registered server-side in
 * inc/login.php; this only supplies the editor preview and its controls.
 *
 * @package Community_CRM
 */
( function ( blocks, element, components, blockEditor, serverSideRender, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;

	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var ToggleControl = components.ToggleControl;
	var ServerSideRender = serverSideRender;

	blocks.registerBlockType( 'community-crm/login-form', {
		edit: function ( props ) {
			var atts = props.attributes;
			var set = props.setAttributes;

			function field( Control, key, label, help, extra ) {
				var config = {
					label: label,
					help: help,
					value: atts[ key ],
					onChange: function ( value ) {
						var next = {};
						next[ key ] = value;
						set( next );
					},
					__nextHasNoMarginBottom: true,
				};

				// TextareaControl has no 40px size opt-in; passing it would
				// leak an unknown attribute onto the <textarea>.
				if ( Control === TextControl ) {
					config.__next40pxDefaultSize = true;
				}

				if ( extra ) {
					Object.keys( extra ).forEach( function ( name ) {
						config[ name ] = extra[ name ];
					} );
				}

				return el( Control, config );
			}

			var inspector = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Sign-in panel', 'community-crm' ), initialOpen: true },
					field(
						TextControl,
						'heading',
						__( 'Heading', 'community-crm' ),
						__( 'Leave empty for “Sign in”.', 'community-crm' )
					),
					field(
						TextareaControl,
						'hint',
						__( 'Hint text', 'community-crm' ),
						__( 'Shown under the heading. Leave empty for the default.', 'community-crm' )
					)
				),
				el(
					PanelBody,
					{ title: __( 'Request access', 'community-crm' ), initialOpen: false },
					el( ToggleControl, {
						label: __( 'Show the request-access panel', 'community-crm' ),
						checked: !! atts.showAccess,
						onChange: function ( value ) {
							set( { showAccess: value } );
						},
						__nextHasNoMarginBottom: true,
					} ),
					atts.showAccess
						? field(
								TextControl,
								'accessHeading',
								__( 'Heading', 'community-crm' ),
								__( 'Leave empty for “Need access?”.', 'community-crm' )
						  )
						: null,
					atts.showAccess
						? field(
								TextareaControl,
								'accessIntro',
								__( 'Intro text', 'community-crm' ),
								__( 'Leave empty for the default.', 'community-crm' )
						  )
						: null,
					atts.showAccess
						? field(
								TextControl,
								'accessUrl',
								__( 'Button URL', 'community-crm' ),
								__( 'Where “Request access” goes. Leave empty to link to the panel on this page.', 'community-crm' ),
								{ type: 'url', placeholder: '#access' }
						  )
						: null
				)
			);

			// The preview is the real PHP render, so the editor shows exactly
			// what visitors get. The inner wrapper is made inert so clicks land
			// on the block wrapper (selecting the block) rather than typing
			// into or submitting the previewed form.
			var preview = el(
				'div',
				useBlockProps(),
				el(
					'div',
					{ style: { pointerEvents: 'none' } },
					el( ServerSideRender, {
						block: 'community-crm/login-form',
						attributes: atts,
						EmptyResponsePlaceholder: function () {
							return el(
								components.Placeholder,
								{ label: __( 'CRM sign-in', 'community-crm' ) },
								__( 'The sign-in form will appear here.', 'community-crm' )
							);
						},
					} )
				)
			);

			return el( element.Fragment, null, inspector, preview );
		},

		// Rendered in PHP.
		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.components,
	window.wp.blockEditor,
	window.wp.serverSideRender,
	window.wp.i18n
);
