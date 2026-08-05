/**
 * Mentor dashboard — expand/collapse all, and printing.
 *
 * Each student is a native <details>, so collapsing already works with no
 * JavaScript at all. This only adds the bulk controls, which is why the markup
 * ships them hidden and this script reveals them: buttons that cannot work
 * should never be on screen.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.querySelector( '.wpcpm-dashboard' );

		if ( ! root ) {
			return;
		}

		/**
		 * Every disclosure on the page — individual students and the group that
		 * wraps past students, so "expand all" really does reveal everything.
		 *
		 * @return {Element[]} The <details> elements.
		 */
		function all() {
			return Array.prototype.slice.call(
				root.querySelectorAll( '.wpcpm-mentee__disclosure, .wpcpm-group__disclosure' )
			);
		}

		/**
		 * Open or close every student.
		 *
		 * @param {boolean} open Whether they should end up open.
		 */
		function setAll( open ) {
			all().forEach( function ( details ) {
				details.open = open;
			} );
		}

		var bulk = root.querySelector( '[data-wpcpm-bulk]' );

		if ( bulk && all().length ) {
			bulk.hidden = false;

			var expand = bulk.querySelector( '[data-wpcpm-expand]' );
			var collapse = bulk.querySelector( '[data-wpcpm-collapse]' );

			if ( expand ) {
				expand.addEventListener( 'click', function () {
					setAll( true );
				} );
			}

			if ( collapse ) {
				collapse.addEventListener( 'click', function () {
					setAll( false );
				} );
			}
		}

		// A collapsed <details> does not print. Anyone printing their student list
		// wants all of it, so open everything first and restore afterwards.
		var wasOpen = [];

		window.addEventListener( 'beforeprint', function () {
			wasOpen = all().map( function ( details ) {
				return details.open;
			} );
			setAll( true );
		} );

		window.addEventListener( 'afterprint', function () {
			all().forEach( function ( details, index ) {
				if ( index < wasOpen.length ) {
					details.open = wasOpen[ index ];
				}
			} );
		} );
		/**
		 * A student's report form, fetched the first time its disclosure is opened.
		 *
		 * Not rendered with the page: reading one costs an Airtable request, and a mentor with
		 * sixty students would pay for sixty of them to look at one. Fetched once and kept —
		 * closing and reopening does not ask again.
		 */
		( function reports() {
			var cfg = window.wpcpmDashboard;

			if ( ! cfg || ! cfg.reportEndpoint ) {
				return;
			}

			root.addEventListener( 'toggle', function ( event ) {
				var box = event.target;

				if ( ! box.open || ! box.hasAttribute( 'data-wpcpm-report' ) || box.dataset.wpcpmLoaded ) {
					return;
				}

				var body = box.querySelector( '[data-wpcpm-report-body]' );

				if ( ! body ) {
					return;
				}

				// Set before the request, not after it: a second toggle while the first is in
				// flight would otherwise ask again.
				box.dataset.wpcpmLoaded = '1';

				window.fetch( cfg.reportEndpoint + encodeURIComponent( box.getAttribute( 'data-wpcpm-report' ) ), {
					credentials: 'same-origin',
					headers: { 'X-WP-Nonce': cfg.nonce }
				} )
					.then( function ( response ) {
						if ( ! response.ok ) {
							throw new Error( 'http' );
						}

						return response.json();
					} )
					.then( function ( data ) {
						body.outerHTML = data.html;
					} )
					.catch( function () {
						// The attribute is cleared so the next open tries again — a report that
						// failed once because the network blinked should not stay broken until
						// the page is reloaded.
						delete box.dataset.wpcpmLoaded;
						body.textContent = cfg.strings.failed;
					} );
			}, true );
		}() );

	} );
}() );
