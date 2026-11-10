/**
 * The dashboards' frame (WPCPM_Dashboard_Frame, 1.123.69): the phone's drawer and the folded menu.
 *
 * On a phone the Menu button opens the menu as a drawer from the side a reader starts from; Close menu,
 * Escape or a press outside closes it, and focus stays inside while it is open and goes back to the button
 * after. On a wide window Collapse menu folds the menu to its icons and the browser remembers it.
 * Without this script the menu is a plain list of links above the page on a phone.
 */
( function () {
	'use strict';

	var KEY = 'wpcpm-frame-folded';

	function remember( folded ) {
		try {
			if ( folded ) {
				window.localStorage.setItem( KEY, '1' );
			} else {
				window.localStorage.removeItem( KEY );
			}
		} catch ( e ) {}
	}

	function focusable( root ) {
		return Array.prototype.slice.call(
			root.querySelectorAll( 'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])' )
		).filter( function ( el ) {
			return el.offsetWidth > 0 || el.offsetHeight > 0;
		} );
	}

	function setup( frame ) {
		var button = frame.querySelector( '.wpcpm-frame__menu-button' );
		var menu = frame.querySelector( '.wpcpm-frame__menu' );
		var close = frame.querySelector( '.wpcpm-frame__close' );
		var fold = frame.querySelector( '.wpcpm-frame__fold' );

		frame.setAttribute( 'data-frame-ready', '' );

		function isOpen() {
			return frame.classList.contains( 'is-open' );
		}

		function openMenu() {
			frame.classList.add( 'is-open' );

			if ( button ) {
				button.setAttribute( 'aria-expanded', 'true' );
			}

			var first = focusable( menu )[ 0 ];

			if ( first ) {
				first.focus();
			}
		}

		function closeMenu( returnFocus ) {
			if ( ! isOpen() ) {
				return;
			}

			frame.classList.remove( 'is-open' );

			if ( button ) {
				button.setAttribute( 'aria-expanded', 'false' );

				if ( returnFocus ) {
					button.focus();
				}
			}
		}

		if ( button && menu ) {
			button.addEventListener( 'click', function () {
				if ( isOpen() ) {
					closeMenu( true );
				} else {
					openMenu();
				}
			} );
		}

		if ( close ) {
			close.addEventListener( 'click', function () {
				closeMenu( true );
			} );
		}

		// A link pressed in the open drawer closes it, so a jump within the page, Watch's, shows where it lands.
		if ( menu ) {
			menu.addEventListener( 'click', function ( event ) {
				var link = event.target.closest( 'a[href]' );

				if ( link && isOpen() ) {
					closeMenu( false );
				}
			} );
		}

		// A press on the shade outside the drawer closes it.
		frame.addEventListener( 'click', function ( event ) {
			if ( isOpen() && menu && ! menu.contains( event.target ) && ( ! button || ! button.contains( event.target ) ) ) {
				closeMenu( true );
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( ! isOpen() ) {
				return;
			}

			if ( 'Escape' === event.key ) {
				event.preventDefault();
				closeMenu( true );

				return;
			}

			if ( 'Tab' === event.key && menu ) {
				var items = focusable( menu );

				if ( ! items.length ) {
					return;
				}

				var first = items[ 0 ];
				var last = items[ items.length - 1 ];

				if ( event.shiftKey && document.activeElement === first ) {
					event.preventDefault();
					last.focus();
				} else if ( ! event.shiftKey && document.activeElement === last ) {
					event.preventDefault();
					first.focus();
				}
			}
		} );

		// Growing past a phone's width with the drawer open closes it.
		window.addEventListener( 'resize', function () {
			if ( isOpen() && window.innerWidth > 782 ) {
				closeMenu( false );
			}
		} );

		if ( fold ) {
			var label = fold.querySelector( '.wpcpm-frame__label' );
			var collapseText = label ? label.textContent : '';
			var expandText = fold.getAttribute( 'data-expand' ) || collapseText;

			var show = function ( folded ) {
				if ( folded ) {
					frame.setAttribute( 'data-folded', '' );
				} else {
					frame.removeAttribute( 'data-folded' );
				}

				fold.setAttribute( 'aria-pressed', folded ? 'true' : 'false' );

				if ( label ) {
					label.textContent = folded ? expandText : collapseText;
				}
			};

			show( frame.hasAttribute( 'data-folded' ) );

			fold.addEventListener( 'click', function () {
				var folded = ! frame.hasAttribute( 'data-folded' );

				show( folded );
				remember( folded );
			} );
		}
	}

	function init() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-wpcpm-frame]' ), setup );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
