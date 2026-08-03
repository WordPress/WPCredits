/*
 * Editor registration for the section blocks (no build step). Each block is
 * server-rendered; in the editor it shows a labelled placeholder. Titles,
 * descriptions, icons and categories come from the PHP registration and are
 * hydrated into the editor automatically.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;

	// [ block name suffix, label, blurb ] — labels mirror the PHP titles.
	var SECTIONS = [
		[ 'scale', 'Scale & Momentum', 'Students in the program, joined to date, and countries.' ],
		[ 'growth', 'Growth Chart', 'Students joining and graduating, month by month.' ],
		[ 'map', 'Partner Map', 'A world map of partner institutions.' ],
		[ 'field-of-study', 'Field of Study', 'The academic backgrounds students bring.' ],
		[ 'skills', 'Skills', 'Technical and transferable skills students build.' ],
		[ 'produce', 'What Students Produce', 'First contributions, sites created, and teams contributed to.' ],
		[ 'outcomes', 'Outcomes & Quality', 'Graduation rate, graduates, impact, and recommendation figures.' ],
		[ 'voices', 'Voices', 'Student testimonials, tagged by country.' ]
	];

	SECTIONS.forEach( function ( s ) {
		registerBlockType( 'wpcredits-tracker/' + s[ 0 ], {
			title: __( 'WPCredits: ' + s[ 1 ], 'wpcredits-tracker' ),
			category: 'wpcredits-tracker',
			edit: function () {
				var blockProps = useBlockProps( {
					style: {
						border: '1px dashed #c3c4c7',
						borderRadius: '8px',
						padding: '20px 22px',
						textAlign: 'center',
						background: '#f4f6fb',
						color: '#1f2733'
					}
				} );
				return el(
					'div',
					blockProps,
					el( 'div', { style: { fontSize: '13px', fontWeight: 700 } }, __( 'WPCredits: ' + s[ 1 ], 'wpcredits-tracker' ) ),
					el( 'div', { style: { fontSize: '12px', color: '#64748b', marginTop: '4px' } }, s[ 2 ] ),
					el( 'div', { style: { fontSize: '11px', color: '#94a3b8', marginTop: '8px' } }, __( 'Renders on the front end.', 'wpcredits-tracker' ) )
				);
			},
			save: function () {
				return null; // Dynamic block: rendered in PHP.
			}
		} );
	} );
} )( window.wp );
