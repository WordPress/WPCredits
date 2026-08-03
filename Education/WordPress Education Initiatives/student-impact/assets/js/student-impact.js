/**
 * Student Impact — contribution-area filtering.
 * Vanilla JS, no dependencies. Scoped per showcase instance so multiple
 * showcases on one page filter independently.
 */
( function () {
	'use strict';

	function initShowcase( section ) {
		var filterBar = section.querySelector( '.si__filters' );
		if ( ! filterBar ) {
			return;
		}

		var buttons = Array.prototype.slice.call( filterBar.querySelectorAll( '.si-filter' ) );
		var cards = Array.prototype.slice.call( section.querySelectorAll( '.si-card' ) );
		var emptyMsg = section.querySelector( '.si__empty-filter' );

		function apply( filter ) {
			var visible = 0;

			cards.forEach( function ( card ) {
				var team = card.getAttribute( 'data-si-team' ) || '';
				var show = '*' === filter || team === filter;
				card.hidden = ! show;
				if ( show ) {
					visible++;
				}
			} );

			buttons.forEach( function ( btn ) {
				var active = btn.getAttribute( 'data-si-filter' ) === filter;
				btn.classList.toggle( 'is-active', active );
				btn.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
			} );

			if ( emptyMsg ) {
				emptyMsg.hidden = visible !== 0;
			}
		}

		filterBar.addEventListener( 'click', function ( event ) {
			var btn = event.target.closest( '.si-filter' );
			if ( ! btn || ! filterBar.contains( btn ) ) {
				return;
			}
			apply( btn.getAttribute( 'data-si-filter' ) );
		} );
	}

	function init() {
		var showcases = document.querySelectorAll( '.si--has-filters' );
		Array.prototype.forEach.call( showcases, initShowcase );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
