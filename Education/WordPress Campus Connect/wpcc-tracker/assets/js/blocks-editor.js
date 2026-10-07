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

	// [ block name suffix, label, blurb ] - labels mirror the PHP titles.
	var SECTIONS = [
		[ 'scale', 'Scale & Momentum', 'Completed, scheduled, in planning, attendees and countries.' ],
		[ 'regions', 'Regions', 'Events, institutions and attendance by world region.' ],
		[ 'map', 'Event Map', 'A world map of Campus Connect events.' ],
		[ 'timeline', 'Timeline', 'Events per month and cumulative attendance.' ],
		[ 'pipeline', 'Pipeline', 'Every organiser status, including cancelled and declined.' ],
		[ 'events', 'Events', 'Recent and upcoming events with institution and attendance.' ]
	];

	SECTIONS.forEach( function ( s ) {
		registerBlockType( 'wpcc-tracker/' + s[ 0 ], {
			title: __( 'WPCC: ' + s[ 1 ], 'wpcc-tracker' ),
			category: 'wpcc-tracker',
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
					el( 'div', { style: { fontSize: '13px', fontWeight: 700 } }, __( 'WPCC: ' + s[ 1 ], 'wpcc-tracker' ) ),
					el( 'div', { style: { fontSize: '12px', color: '#64748b', marginTop: '4px' } }, s[ 2 ] ),
					el( 'div', { style: { fontSize: '11px', color: '#94a3b8', marginTop: '8px' } }, __( 'Renders on the front end.', 'wpcc-tracker' ) )
				);
			},
			save: function () {
				return null; // Dynamic block: rendered in PHP.
			}
		} );
	} );
} )( window.wp );
