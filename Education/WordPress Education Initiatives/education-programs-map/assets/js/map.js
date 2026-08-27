( function () {
	'use strict';

	function initMap( container ) {
		var settings = {
			restUrl: container.getAttribute( 'data-rest-url' ) || '',
			programs: {},
			colors: {},
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

		try {
			settings.colors = JSON.parse( container.getAttribute( 'data-program-colors' ) || '{}' );
		} catch ( e ) {
			settings.colors = {};
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

		var DEFAULT_COLOR = '#6e6e6e';

		// The colour map is generated server-side from the theme palette, but it still
		// reaches the browser as a DOM attribute, so anything not a plain hex colour is
		// refused rather than written into a style attribute or an SVG fill.
		function safeColor( value ) {
			return /^#[0-9a-f]{6}$/i.test( String( value || '' ) ) ? value : DEFAULT_COLOR;
		}

		function programColor( key ) {
			return safeColor( settings.colors[ key ] );
		}

		// When the map is filtered to one program, every visible marker is coloured for
		// that program — otherwise an institution in two programs would show a colour
		// that contradicts the filter it was just matched by. Unfiltered, it takes the
		// colour of the first program it belongs to.
		function markerColor( institution, activeProgram ) {
			if ( activeProgram && settings.colors[ activeProgram ] ) {
				return programColor( activeProgram );
			}

			var programs = institution.programs || [];
			for ( var i = 0; i < programs.length; i++ ) {
				if ( settings.colors[ programs[ i ] ] ) {
					return programColor( programs[ i ] );
				}
			}

			return DEFAULT_COLOR;
		}

		function markerRadius( eventCount ) {
			return Math.max( 6, Math.min( 24, 6 + Math.sqrt( eventCount ) * 3 ) );
		}

		function eventsHtml( events ) {
			if ( ! events || ! events.length ) {
				return '';
			}

			var html = '<ul class="epm-popup-events">';

			events.forEach( function ( event ) {
				var label = event.venue || event.dateLabel || '';

				html += '<li>';
				if ( event.url ) {
					html += '<a href="' + escapeAttr( event.url ) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml( label ) + '</a>';
				} else {
					html += escapeHtml( label );
				}
				if ( event.venue && event.dateLabel ) {
					html += '<span class="epm-popup-event-date">' + escapeHtml( event.dateLabel ) + '</span>';
				}
				html += '</li>';
			} );

			return html + '</ul>';
		}

		function popupHtml( institution, color ) {
			var html = '<strong style="color: ' + escapeAttr( safeColor( color ) ) + '">' + escapeHtml( institution.name ) + '</strong><br>';
			html += escapeHtml( institution.city );
			if ( institution.city && institution.country ) {
				html += ', ';
			}
			html += escapeHtml( institution.country );
			html += '<br>' + escapeHtml( ( institution.programLabels || [] ).join( ', ' ) );
			if ( institution.eventCount > 0 ) {
				html += '<br>' + institution.eventCount + ' ' + escapeHtml( settings.strings && settings.strings.events ? settings.strings.events : 'events' );
			}
			html += eventsHtml( institution.events );
			if ( institution.website ) {
				html += '<br><a href="' + escapeAttr( institution.website ) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml( institution.website ) + '</a>';
			}
			// When the event list is present it already links every event, so a
			// separate "WPCC site" link would just repeat its first entry.
			if ( institution.wpccUrl && ! ( institution.events && institution.events.length ) ) {
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
						// Solid circle with a white ring; the fill identifies the program.
						var color = markerColor( institution, program );

						var marker = window.L.circleMarker( [ institution.latitude, institution.longitude ], {
							radius: markerRadius( institution.eventCount ),
							color: '#fff',
							fillColor: color,
							fillOpacity: 0.9,
							weight: 2,
						} );

						marker.bindPopup( popupHtml( institution, color ) );
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

		function colorFor( key ) {
			var value = ( settings.colors || {} )[ key ];
			return /^#[0-9a-f]{6}$/i.test( String( value || '' ) ) ? value : '#6e6e6e';
		}

		var buttons = [ { key: '', label: allLabel } ];
		Object.keys( programs ).forEach( function ( key ) {
			buttons.push( { key: key, label: programs[ key ] } );
		} );

		// The active button is filled with its own program's colour rather than a fixed
		// blue, so the selected filter and the markers it produced always agree. The
		// swatch is hidden while active because the whole button is then the swatch.
		function paint( el, key, isActive ) {
			el.classList.toggle( 'is-active', isActive );

			if ( isActive && key ) {
				el.style.backgroundColor = colorFor( key );
				el.style.borderColor = colorFor( key );
			} else {
				el.style.backgroundColor = '';
				el.style.borderColor = '';
			}
		}

		var elements = [];

		buttons.forEach( function ( button ) {
			var el = document.createElement( 'button' );
			el.type = 'button';
			el.className = 'epm-filter-button';
			el.setAttribute( 'data-program', button.key );

			// A swatch per program turns the existing filter bar into the map's legend,
			// rather than adding a second control that says the same thing.
			if ( button.key ) {
				var swatch = document.createElement( 'span' );
				swatch.className = 'epm-filter-swatch';
				swatch.style.backgroundColor = colorFor( button.key );
				el.appendChild( swatch );
			}

			el.appendChild( document.createTextNode( button.label ) );
			paint( el, button.key, button.key === activeProgram );

			el.addEventListener( 'click', function () {
				elements.forEach( function ( entry ) {
					paint( entry.el, entry.key, entry.el === el );
				} );
				onChange( button.key );
			} );

			elements.push( { el: el, key: button.key } );
			filterBar.appendChild( el );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.epm-map' ).forEach( initMap );
	} );
} )();
