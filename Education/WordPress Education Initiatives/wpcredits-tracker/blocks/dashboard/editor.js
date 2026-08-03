( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;

	registerBlockType( 'wpcredits-tracker/dashboard', {
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
				el( 'div', { style: { fontSize: '15px', fontWeight: 700 } }, __( 'WPCredits-Tracker', 'wpcredits-tracker' ) ),
				el(
					'div',
					{ style: { fontSize: '13px', color: '#64748b', marginTop: '6px' } },
					__( 'Overview · Contributions · Voices — renders with live charts and map on the front end.', 'wpcredits-tracker' )
				)
			);
		},
		save: function () {
			return null; // Dynamic block: rendered in PHP.
		},
	} );
} )( window.wp );
