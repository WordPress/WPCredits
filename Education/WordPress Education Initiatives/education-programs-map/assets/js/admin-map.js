( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var container = document.getElementById( 'epm-location-picker' );
		var latInput = document.getElementById( 'epm-latitude' );
		var lngInput = document.getElementById( 'epm-longitude' );

		if ( ! container || ! latInput || ! lngInput || ! window.L ) {
			return;
		}

		var initialLat = parseFloat( container.getAttribute( 'data-latitude' ) );
		var initialLng = parseFloat( container.getAttribute( 'data-longitude' ) );
		var hasPoint = ! isNaN( initialLat ) && ! isNaN( initialLng );

		var startLat = hasPoint ? initialLat : 20;
		var startLng = hasPoint ? initialLng : 0;
		var startZoom = hasPoint ? 5 : 2;

		var map = window.L.map( container, { worldCopyJump: true } ).setView( [ startLat, startLng ], startZoom );

		window.L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
			maxZoom: 18,
		} ).addTo( map );

		var marker = null;

		function setInputs( lat, lng ) {
			latInput.value = lat.toFixed( 6 );
			lngInput.value = lng.toFixed( 6 );
		}

		function placeMarker( lat, lng ) {
			if ( marker ) {
				marker.setLatLng( [ lat, lng ] );
				return;
			}

			marker = window.L.marker( [ lat, lng ], { draggable: true } ).addTo( map );
			marker.on( 'dragend', function () {
				var pos = marker.getLatLng();
				setInputs( pos.lat, pos.lng );
			} );
		}

		if ( hasPoint ) {
			placeMarker( startLat, startLng );
		}

		map.on( 'click', function ( e ) {
			placeMarker( e.latlng.lat, e.latlng.lng );
			setInputs( e.latlng.lat, e.latlng.lng );
		} );

		function syncFromInputs() {
			var lat = parseFloat( latInput.value );
			var lng = parseFloat( lngInput.value );

			if ( isNaN( lat ) || isNaN( lng ) || lat < -90 || lat > 90 || lng < -180 || lng > 180 ) {
				return;
			}

			placeMarker( lat, lng );
			map.setView( [ lat, lng ], Math.max( map.getZoom(), 5 ) );
		}

		latInput.addEventListener( 'change', syncFromInputs );
		lngInput.addEventListener( 'change', syncFromInputs );
	} );
} )();
