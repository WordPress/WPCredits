/**
 * Credits Program Mentors — country map (Leaflet).
 *
 * Matches the Education Programs Map plugin: CARTO Positron basemap, blue circle
 * markers sized by mentor count, popups, and native zoom/pan. Clicking a marker
 * filters the mentor list to that country (via a custom event the filter script
 * listens for); clicking empty map clears it.
 */
( function () {
	'use strict';

	function escapeHtml( value ) {
		var div = document.createElement( 'div' );
		div.textContent = String( value == null ? '' : value );
		return div.innerHTML;
	}

	function markerRadius( count ) {
		return Math.max( 6, Math.min( 28, 6 + Math.sqrt( count ) * 4 ) );
	}

	function initMap( el ) {
		if ( ! window.L ) {
			return;
		}

		var markers = [];
		try {
			markers = JSON.parse( el.getAttribute( 'data-markers' ) || '[]' );
		} catch ( e ) {
			markers = [];
		}

		var mentorsLabel = el.getAttribute( 'data-label-mentors' ) || 'mentors';
		var wrap         = el.closest ? el.closest( '.credprme-mentors-wrap' ) : null;

		function setCountry( token ) {
			if ( wrap ) {
				wrap.dispatchEvent( new CustomEvent( 'credprme:setcountry', { detail: { token: token } } ) );
			}
		}

		var map = window.L.map( el.id, { worldCopyJump: true } ).setView( [ 20, 0 ], 2 );

		// Same light "Positron" basemap as the Education Programs Map plugin.
		window.L.tileLayer( 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/">CARTO</a>',
			subdomains: 'abcd',
			maxZoom: 19,
		} ).addTo( map );

		var layer = window.L.layerGroup().addTo( map );

		markers.forEach( function ( m ) {
			// Solid blue circle with a white ring, matching the Programs map markers.
			var marker = window.L.circleMarker( [ m.lat, m.lng ], {
				radius: markerRadius( m.count ),
				color: '#fff',
				fillColor: '#3858E9',
				fillOpacity: 0.9,
				weight: 2,
			} );

			var word  = 1 === m.count && /s$/.test( mentorsLabel ) ? mentorsLabel.slice( 0, -1 ) : mentorsLabel;
			var count = m.count + ' ' + word;
			marker.bindPopup( '<strong>' + escapeHtml( m.name ) + '</strong>' + escapeHtml( count ) );
			marker.on( 'click', function () {
				setCountry( m.id );
			} );
			marker.addTo( layer );
		} );

		// Clicking the map background (not a marker) clears the country filter.
		map.on( 'click', function () {
			setCountry( '' );
		} );
	}

	function init() {
		document.querySelectorAll( '.credprme-map__canvas' ).forEach( initMap );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
