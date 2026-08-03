( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var RangeControl = wp.components.RangeControl;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var ToggleControl = wp.components.ToggleControl;
	var ServerSideRender = wp.serverSideRender;

	registerBlockType( 'student-impact/showcase', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			var controls = el(
				InspectorControls,
				{ key: 'inspector' },
				el(
					PanelBody,
					{ title: __( 'Showcase settings', 'student-impact' ), initialOpen: true },
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
					el( RangeControl, {
						label: __( 'How many to show (0 = all synced)', 'student-impact' ),
						value: attributes.count,
						min: 0,
						max: 50,
						onChange: function ( v ) {
							setAttributes( { count: v } );
						},
					} ),
					el( RangeControl, {
						label: __( 'Columns', 'student-impact' ),
						value: attributes.columns,
						min: 2,
						max: 4,
						onChange: function ( v ) {
							setAttributes( { columns: v } );
						},
					} ),
					el( ToggleControl, {
						label: __( 'Show contribution-area filters', 'student-impact' ),
						checked: !! attributes.showFilters,
						onChange: function ( v ) {
							setAttributes( { showFilters: v } );
						},
					} )
				)
			);

			var preview = el( ServerSideRender, {
				block: 'student-impact/showcase',
				attributes: attributes,
			} );

			return el( 'div', blockProps, [ controls, el( 'div', { key: 'preview' }, preview ) ] );
		},
		save: function () {
			return null; // Dynamic block: rendered in PHP.
		},
	} );
} )( window.wp );
