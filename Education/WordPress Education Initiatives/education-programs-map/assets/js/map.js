( function () {
	'use strict';

	function initMap( container ) {
		var settings = {
			restUrl: container.getAttribute( 'data-rest-url' ) || '',
			programs: {},
			strings: {
				events: container.getAttribute( 'data-label-events' ) || 'events',
				all: container.getAttribute( 'data-label-all' ) || 'All programs',
			},
		};

		try {
			settings.programs = JSON.parse( container.getAttribute( 'data-programs' ) || '{}' );
		} catch ( e ) {
			settings.programs = {};
		}

		if ( ! settings.restUrl ) {
			return;
		}

		var activeProgram = container.getAttribute( 'data-program' ) || '';

		var map = window.L.map( container.id, {
			worldCopyJump: true,
		} ).setView( [ 20, 0 ], 2 );

		// Same light "Positron" basemap as the education-credits-dashboard plugin's map,
		// instead of the default OpenStreetMap tiles' blue water / tan land colors.
		window.L.tileLayer( 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/">CARTO</a>',
			subdomains: 'abcd',
			maxZoom: 19,
		} ).addTo( map );

		var markersLayer = window.L.layerGroup().addTo( map );

		function markerRadius( eventCount ) {
			return Math.max( 6, Math.min( 24, 6 + Math.sqrt( eventCount ) * 3 ) );
		}

		function popupHtml( institution ) {
			var html = '<strong>' + escapeHtml( institution.name ) + '</strong><br>';
			html += escapeHtml( institution.city );
			if ( institution.country ) {
				html += ', ' + escapeHtml( institution.country );
			}
			html += '<br>' + escapeHtml( ( institution.programLabels || [] ).join( ', ' ) );
			if ( institution.eventCount > 0 ) {
				html += '<br>' + institution.eventCount + ' ' + escapeHtml( settings.strings && settings.strings.events ? settings.strings.events : 'events' );
			}
			if ( institution.website ) {
				html += '<br><a href="' + escapeAttr( institution.website ) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml( institution.website ) + '</a>';
			}
			if ( institution.wpccUrl ) {
				html += '<br><a href="' + escapeAttr( institution.wpccUrl ) + '" target="_blank" rel="noopener noreferrer">WPCC site</a>';
			}
			if ( institution.studentClubUrl ) {
				html += '<br><a href="' + escapeAttr( institution.studentClubUrl ) + '" target="_blank" rel="noopener noreferrer">Student Club site</a>';
			}
			return html;
		}

		function escapeHtml( value ) {
			var div = document.createElement( 'div' );
			div.textContent = String( value == null ? '' : value );
			return div.innerHTML;
		}

		function escapeAttr( value ) {
			return escapeHtml( value ).replace( /"/g, '&quot;' );
		}

		function loadInstitutions( program ) {
			var url = settings.restUrl;
			if ( program ) {
				url += ( url.indexOf( '?' ) === -1 ? '?' : '&' ) + 'program=' + encodeURIComponent( program );
			}

			fetch( url, { credentials: 'same-origin' } )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( institutions ) {
					markersLayer.clearLayers();

					institutions.forEach( function ( institution ) {
						// Solid blue circle with a white ring, matching the education-credits-dashboard plugin's map markers.
						var marker = window.L.circleMarker( [ institution.latitude, institution.longitude ], {
							radius: markerRadius( institution.eventCount ),
							color: '#fff',
							fillColor: '#3858E9',
							fillOpacity: 0.9,
							weight: 2,
						} );

						marker.bindPopup( popupHtml( institution ) );
						marker.addTo( markersLayer );
					} );
				} )
				.catch( function () {
					// Silently ignore network errors; the map remains usable without markers.
				} );
		}

		loadInstitutions( activeProgram );

		var filterBar = document.querySelector( '.epm-map-filters[data-target="' + container.id + '"]' );
		if ( filterBar ) {
			renderFilters( filterBar, settings, activeProgram, function ( program ) {
				activeProgram = program;
				loadInstitutions( program );
			} );
		}
	}

	function renderFilters( filterBar, settings, activeProgram, onChange ) {
		var programs = settings.programs || {};
		var allLabel = settings.strings && settings.strings.all ? settings.strings.all : 'All programs';

		var buttons = [ { key: '', label: allLabel } ];
		Object.keys( programs ).forEach( function ( key ) {
			buttons.push( { key: key, label: programs[ key ] } );
		} );

		buttons.forEach( function ( button ) {
			var el = document.createElement( 'button' );
			el.type = 'button';
			el.className = 'epm-filter-button' + ( button.key === activeProgram ? ' is-active' : '' );
			el.textContent = button.label;
			el.setAttribute( 'data-program', button.key );

			el.addEventListener( 'click', function () {
				filterBar.querySelectorAll( '.epm-filter-button' ).forEach( function ( sibling ) {
					sibling.classList.remove( 'is-active' );
				} );
				el.classList.add( 'is-active' );
				onChange( button.key );
			} );

			filterBar.appendChild( el );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.epm-map' ).forEach( initMap );
	} );
} )();
