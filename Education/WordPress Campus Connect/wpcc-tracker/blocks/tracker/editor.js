( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;

	registerBlockType( 'wpcc-tracker/tracker', {
		edit: function () {
			var blockProps = useBlockProps( {
				style: {
					border: '1px dashed #c3c4c7',
					borderRadius: '8px',
					padding: '28px 24px',
					textAlign: 'center',
					background: '#f4f6fb',
					color: '#1f2733',
				},
			} );

			return el(
				'div',
				blockProps,
				el( 'div', { style: { fontSize: '15px', fontWeight: 700 } }, __( 'WPCC-Tracker', 'wpcc-tracker' ) ),
				el(
					'div',
					{ style: { fontSize: '13px', color: '#64748b', marginTop: '6px' } },
					__( 'Scale · Regions · Map · Timeline · Pipeline · Events - renders with live charts and map on the front end.', 'wpcc-tracker' )
				)
			);
		},
		save: function () {
			return null; // Dynamic block: rendered in PHP.
		},
	} );
} )( window.wp );
