( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var Notice = wp.components.Notice;
	var ServerSideRender = wp.serverSideRender;
	var apiFetch = wp.apiFetch;
	var __ = wp.i18n.__;

	registerBlockType( 'epm/education-programs-map', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps( { className: 'epm-block-editor-preview' } );
			var programsState = useState( {} );
			var programs = programsState[ 0 ];
			var setPrograms = programsState[ 1 ];

			useEffect( function () {
				apiFetch( { path: '/wp-education-map/v1/programs' } )
					.then( function ( result ) {
						setPrograms( result || {} );
					} )
					.catch( function () {
						setPrograms( {} );
					} );
			}, [] );

			var programOptions = [ { label: __( 'All programs', 'wp-education-map' ), value: '' } ];
			Object.keys( programs ).forEach( function ( key ) {
				programOptions.push( { label: programs[ key ], value: key } );
			} );

			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Education Map Settings', 'wp-education-map' ) },
						el( SelectControl, {
							label: __( 'Program', 'wp-education-map' ),
							value: attributes.program,
							options: programOptions,
							onChange: function ( value ) {
								setAttributes( { program: value } );
							},
						} ),
						el( TextControl, {
							label: __( 'Height', 'wp-education-map' ),
							help: __( 'CSS length, e.g. 520px. Leave blank to use the site default from Education Map > Settings.', 'wp-education-map' ),
							value: attributes.height,
							onChange: function ( value ) {
								setAttributes( { height: value } );
							},
						} ),
						el( TextControl, {
							label: __( 'Width', 'wp-education-map' ),
							help: __( 'CSS length, e.g. 100%. Leave blank to use the site default from Education Map > Settings.', 'wp-education-map' ),
							value: attributes.width,
							onChange: function ( value ) {
								setAttributes( { width: value } );
							},
						} )
					)
				),
				el(
					'div',
					blockProps,
					ServerSideRender
						? el( ServerSideRender, {
								block: 'epm/education-programs-map',
								attributes: attributes,
						  } )
						: el(
								Notice,
								{ status: 'warning', isDismissible: false },
								__( 'Education Map: the preview component failed to load. The map will still display on the published page.', 'wp-education-map' )
						  )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
