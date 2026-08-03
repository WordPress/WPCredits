( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var ToggleControl = wp.components.ToggleControl;
	var SelectControl = wp.components.SelectControl;
	var ServerSideRender = wp.serverSideRender;

	registerBlockType( 'student-impact/stats', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			var controls = el(
				InspectorControls,
				{ key: 'inspector' },
				el(
					PanelBody,
					{ title: __( 'Stats settings', 'student-impact' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Layout', 'student-impact' ),
						value: attributes.layout,
						options: [
							{ label: __( 'Grid (cards)', 'student-impact' ), value: 'grid' },
							{ label: __( 'Inline strip (for headers/footers)', 'student-impact' ), value: 'inline' },
						],
						onChange: function ( v ) {
							setAttributes( { layout: v } );
						},
					} ),
					el( TextControl, {
						label: __( 'Title', 'student-impact' ),
						value: attributes.title,
						onChange: function ( v ) {
							setAttributes( { title: v } );
						},
					} ),
					el( TextareaControl, {
						label: __( 'Subtitle', 'student-impact' ),
						value: attributes.subtitle,
						onChange: function ( v ) {
							setAttributes( { subtitle: v } );
						},
					} ),
					el( ToggleControl, {
						label: __( 'Show footnote (graduate / profile counts)', 'student-impact' ),
						checked: !! attributes.showNote,
						onChange: function ( v ) {
							setAttributes( { showNote: v } );
						},
					} )
				)
			);

			var preview = el( ServerSideRender, {
				block: 'student-impact/stats',
				attributes: attributes,
			} );

			return el( 'div', blockProps, [ controls, el( 'div', { key: 'preview' }, preview ) ] );
		},
		save: function () {
			return null; // Dynamic block: rendered in PHP.
		},
	} );
} )( window.wp );
