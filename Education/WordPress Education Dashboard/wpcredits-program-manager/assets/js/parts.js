/**
 * The parts' script (design 3.2, jobs 1 and 3): the note panels of a Decide area and the row forms of a list,
 * and the file box; and the pick that loads a page (Task 14).
 *
 * The server prints a page that works with no script: in a Decide area (`.wpcpm-ui-decide`) every note
 * panel is printed open with its form, and the openers in the action row and the Cancel buttons in the
 * panels are printed `hidden`; a list (`.wpcpm-ui-list`) prints each row's form open under its row, its
 * opener and Cancel `hidden` the same way. This turns that into what the design draws:
 *
 * - once the page is parsed, the openers and the Cancels are revealed, and every panel and row form is
 *   folded but the one printed `data-wpcpm-open` (a note the site refused, or typing it kept), whose opener
 *   reads `aria-expanded="true"`. The focus is not moved on load: a refused form's error summary is
 *   focused by forms.js, and nothing here competes with it;
 * - an opener pressed shows its panel, folds every other panel of its own area and sets their openers to
 *   `false`, sets itself `true` and moves the focus to the panel's first field. Pressed again, it folds
 *   the panel and keeps the focus;
 * - a panel with no field (a sentence and its two buttons) takes the focus on its dismissing button, never
 *   on the confirming one;
 * - Cancel, and Escape anywhere inside the area of an open panel (the panel, its buttons, its opener, a press
 *   of the area's row), fold it, set its opener `false` and put the focus back on the opener that opened it;
 * - a shared panel (two openers controlling one panel, a school's request) shows only the submit of the
 *   opener pressed: the submit whose `data-wpcpm-for` is the opener's own (`WPCPM_UI::decide()` prints
 *   the key on both), or, for an opener with no key, the one at its position. Pressing the other opener
 *   while the panel is open switches the
 *   submit and the two `aria-expanded` values; the panel stays open and what was typed stays. A post the
 *   browser makes with the hidden submit (Enter in a one-line box) is made again with the shown one.
 *
 * The file box (`.wpcpm-ui-file`, `WPCPM_UI::file()`) is printed with the browser's own file control shown and
 * its button and the name beside it `hidden`. Once the page is parsed each whole box (a control,
 * a button and a name) has its button and name revealed and its control clipped by a class (never `display:
 * none`, so the label, `required`, the browser's refusal and the upload stay the control's). The control stays the
 * field's one tab stop and the button takes `tabindex="-1"`: the browser focuses the control when it refuses a
 * missing file, and the sheet draws the ring on the button while the control has the focus. The button opens the
 * control (its `click()`, the focus put on the control first); the name follows the choice on `change` and on
 * `cancel`, and when the control is empty it reads the words the server printed in it, so the script holds
 * no words. A box missing a part is left as printed. Listeners are added to the boxes only: a
 * page with none gains none.
 *
 * A form marked `data-wpcpm-pick-submits` (the Institution Dashboard's compact Viewing as institution) is sent
 * when a name is picked in its field: switcher.js announces a pick as a `wpcpm:picked` event on the form, and this
 * listens on each marked form and sends it once a pick (`requestSubmit()` where the browser has it, else
 * `submit()`), never for the name the page is about already, and never twice within a few seconds. Once it listens,
 * and only once switcher.js has shown the field, it hides the form's Show (`data-wpcpm-noscript`), so a page where
 * either script did not load keeps Show, and Show works. An unmarked form is never sent from here.
 *
 * A panel is only an element carrying `data-wpcpm-panel` or the class `wpcpm-ui-list__form`; an opener is
 * a control outside every panel, with `aria-expanded`, whose `aria-controls` names a panel of its own area.
 * So nothing is ever done across two areas, and a widget inside a panel (the type-and-pick box) or a toggle
 * of another script is left alone. One area that fails is left as printed and the others still fold. A
 * page with none of the parts is not changed. Included twice, it runs once; `window.wpcpmParts` is the one
 * name it adds, the function that folds every area not yet folded. It never fetches, stores or writes markup, sends
 * a form only as said above, and it animates nothing.
 */
( function () {
	'use strict';

	var AREA = '.wpcpm-ui-decide, .wpcpm-ui-list';
	var PANEL = '[data-wpcpm-panel], .wpcpm-ui-list__form';
	var FILE = '.wpcpm-ui-file';
	var PICKS = 'form[data-wpcpm-pick-submits]';
	// How long after a pick a further pick sends nothing, in ms: longer than a double press, shorter than a reader's wait.
	var PICK_AGAIN = 4000;
	// The submit this script is posting itself, so its own submit event is let through.
	var passing = null;

	if ( 'function' === typeof window.wpcpmParts ) {
		return;
	}

	function ready( fn ) {
		if ( 'loading' !== document.readyState ) {
			fn();
			return;
		}

		document.addEventListener( 'DOMContentLoaded', fn );
	}

	function each( list, fn ) {
		var i;

		for ( i = 0; i < list.length; i++ ) {
			fn( list[ i ], i );
		}
	}

	/**
	 * The panel an opener controls. A panel is only what the parts print as one: an element carrying
	 * `data-wpcpm-panel` (decide()) or the class `wpcpm-ui-list__form` (row()). An opener is a control
	 * outside every panel, with `aria-expanded`, whose `aria-controls` names such a panel of its own area;
	 * a widget inside a panel (the type-and-pick box, whose input controls its list) is never one.
	 *
	 * @param {Element} opener A control with `aria-controls`.
	 * @return {Element|null} The panel, or null.
	 */
	function panelOf( opener ) {
		var area = opener.closest( AREA );
		var id;
		var panel;

		if ( ! area || opener.closest( PANEL ) || ! opener.hasAttribute( 'aria-expanded' ) ) {
			return null;
		}

		id = opener.getAttribute( 'aria-controls' );
		panel = id ? document.getElementById( id ) : null;

		if ( ! panel || ! panel.matches( PANEL ) || panel.closest( AREA ) !== area ) {
			return null;
		}

		return panel;
	}

	// Every opener of an area, in the order printed.
	function openersIn( area ) {
		var out = [];

		each( area.querySelectorAll( '[aria-controls]' ), function ( opener ) {
			if ( opener.closest( AREA ) === area && panelOf( opener ) ) {
				out.push( opener );
			}
		} );

		return out;
	}

	// The openers of one panel, in the order printed.
	function openersOf( area, panel ) {
		return openersIn( area ).filter( function ( opener ) {
			return panelOf( opener ) === panel;
		} );
	}

	// Every panel of an area, each once, in the order of their openers.
	function panelsIn( area ) {
		var out = [];

		each( openersIn( area ), function ( opener ) {
			var panel = panelOf( opener );

			if ( -1 === out.indexOf( panel ) ) {
				out.push( panel );
			}
		} );

		return out;
	}

	// The opener that opened a panel: the one that reads expanded, else its first.
	function activeOpener( area, panel ) {
		var openers = openersOf( area, panel );
		var expanded = openers.filter( function ( opener ) {
			return 'true' === opener.getAttribute( 'aria-expanded' );
		} );

		return expanded[ 0 ] || openers[ 0 ] || null;
	}

	/**
	 * Mark one opener of a panel expanded and its others not, and in a shared panel show only that
	 * opener's submit: the one whose `data-wpcpm-for` is the opener's own (decide() prints the key on
	 * both). An opener with no key falls back to its position: the n-th opener owns the n-th submit. A
	 * panel whose submits do not pair with its openers keeps every submit shown.
	 *
	 * @param {Element} area   The area.
	 * @param {Element} panel  The panel.
	 * @param {Element} opener The opener that opened it.
	 */
	function expand( area, panel, opener ) {
		var openers = openersOf( area, panel );
		var submits = panel.querySelectorAll( '[data-wpcpm-for]' );
		var key = opener ? opener.getAttribute( 'data-wpcpm-for' ) : null;
		var at = openers.indexOf( opener );
		var mine = null;

		each( openers, function ( one ) {
			one.setAttribute( 'aria-expanded', one === opener ? 'true' : 'false' );
		} );

		if ( submits.length < 2 || -1 === at ) {
			return;
		}

		each( submits, function ( submit, i ) {
			if ( null !== key ? submit.getAttribute( 'data-wpcpm-for' ) === key : ( submits.length === openers.length && i === at ) ) {
				mine = mine || submit;
			}
		} );

		if ( mine ) {
			each( submits, function ( submit ) {
				submit.hidden = submit !== mine;
			} );
		}
	}

	// Fold a panel and set its openers to false.
	function fold( area, panel ) {
		panel.hidden = true;

		each( openersOf( area, panel ), function ( opener ) {
			opener.setAttribute( 'aria-expanded', 'false' );
		} );
	}

	function closeOthers( area, panel ) {
		each( panelsIn( area ), function ( other ) {
			if ( other !== panel ) {
				fold( area, other );
			}
		} );
	}

	// The focus goes to the panel's first field a person fills in. Whether there was one.
	function focusFirstField( panel ) {
		var fields = panel.querySelectorAll( 'input, textarea, select' );
		var i;

		for ( i = 0; i < fields.length; i++ ) {
			if ( 'hidden' !== String( fields[ i ].type ).toLowerCase() && ! fields[ i ].disabled ) {
				fields[ i ].focus();
				return true;
			}
		}

		return false;
	}

	// A panel with no field (a sentence and its buttons) takes the focus on its dismissing button, the least
	// destructive control, never on the confirming one.
	function focusDismiss( panel ) {
		var dismiss = panel.querySelector( '[data-wpcpm-cancel]' );

		if ( dismiss ) {
			dismiss.focus();
		}
	}

	function show( area, panel, opener ) {
		closeOthers( area, panel );
		panel.hidden = false;
		expand( area, panel, opener );

		if ( ! focusFirstField( panel ) ) {
			focusDismiss( panel );
		}
	}

	// Fold a panel; with `back`, the focus returns to the opener that opened it.
	function close( area, panel, back ) {
		var opener = activeOpener( area, panel );

		fold( area, panel );

		if ( back && opener ) {
			opener.focus();
		}
	}

	/**
	 * The area and the panel an element is inside, when it is inside one of an area's panels.
	 *
	 * @param {Element} node The element.
	 * @return {Object|null} { area, panel }, or null.
	 */
	function around( node ) {
		var area = node && 'function' === typeof node.closest ? node.closest( AREA ) : null;
		var panels;

		if ( ! area ) {
			return null;
		}

		panels = panelsIn( area );

		for ( ; node && node !== area; node = node.parentElement ) {
			if ( -1 !== panels.indexOf( node ) ) {
				return { area: area, panel: node };
			}
		}

		return null;
	}

	// Fold the open panel of an area, if one is, and put the focus back on the opener that opened it.
	function closeOpenIn( area ) {
		each( panelsIn( area ), function ( panel ) {
			if ( ! panel.hidden ) {
				close( area, panel, true );
			}
		} );
	}

	/**
	 * Fold what the server printed open, in every area not folded yet: reveal the openers and the Cancels
	 * in their panels, fold every panel but the first printed `data-wpcpm-open`, and mark its opener.
	 */
	function foldArea( area ) {
		// Every lookup first, so an area whose lookup fails is left as the server printed it.
		var openers = openersIn( area );
		var panels = panelsIn( area );
		var keep = null;

		each( panels, function ( panel ) {
			if ( ! keep && panel.hasAttribute( 'data-wpcpm-open' ) ) {
				keep = panel;
			}
		} );

		each( openers, function ( opener ) {
			opener.hidden = false;
		} );

		each( panels, function ( panel ) {
			each( panel.querySelectorAll( '[data-wpcpm-cancel]' ), function ( cancel ) {
				cancel.hidden = false;
			} );

			if ( panel === keep ) {
				panel.hidden = false;
				expand( area, panel, activeOpener( area, panel ) );
			} else {
				fold( area, panel );
			}
		} );
	}

	/**
	 * Draw one file box as the design does: reveal its button and its name, clip its control, and tie the
	 * button and the name to the control. A box missing its control, its button or its name is left as printed.
	 *
	 * @param {Element} box A `.wpcpm-ui-file`.
	 */
	function fileBox( box ) {
		var input = box.querySelector( '.wpcpm-ui-file__input' );
		var choose = box.querySelector( '.wpcpm-ui-file__choose' );
		var name = box.querySelector( '.wpcpm-ui-file__name' );
		var empty;

		if ( ! input || ! choose || ! name || 'file' !== String( input.type ).toLowerCase() ) {
			return;
		}

		box.wpcpmPartsFile = true;
		// The words the server printed for no file, translated there.
		empty = name.textContent;

		function follow() {
			name.textContent = input.files && input.files.length ? input.files[ 0 ].name : empty;
		}

		choose.addEventListener( 'click', function () {
			input.focus();
			input.click();
		} );
		input.addEventListener( 'change', follow );
		// A chooser closed with no new choice: a browser that clears the choice sends change, one that keeps it
		// sends cancel; either way the name says what the control holds.
		input.addEventListener( 'cancel', follow );

		// A page brought back with a file still chosen names it.
		follow();
		input.classList.add( 'wpcpm-ui-file__input--hidden' );
		choose.setAttribute( 'tabindex', '-1' );
		choose.hidden = false;
		name.hidden = false;
	}

	/**
	 * Whether a marked form's field is really there: switcher.js shows the field's block (the combobox's parent,
	 * printed `hidden`) as the last step of wiring it, and only a browser with CustomEvent can announce a pick.
	 *
	 * @param {Element} form A form marked `data-wpcpm-pick-submits`.
	 * @return {boolean}
	 */
	function fieldWired( form ) {
		var input = form.querySelector( '[role="combobox"]' );

		return !! input && !! input.parentNode && ! input.parentNode.hidden && ! input.hidden && 'function' === typeof window.CustomEvent;
	}

	/**
	 * Send a marked form once a name is picked in its field, and hide its Show once the field is really there.
	 *
	 * A pick sends the form once. A second pick within a few seconds sends nothing, so Enter and a click on one pick,
	 * or two quick picks, never send it twice; after them, a pick sends it again, so a reader who stopped the page
	 * from loading is not left with a field that does nothing. A page brought back from the back-forward cache is a
	 * page to pick on again at once.
	 *
	 * Show is hidden only while the field is there (`fieldWired()`): asked when this job starts, and, when the field is
	 * not there yet, asked again at the window's load, which comes after every DOMContentLoaded listener has run, so
	 * after switcher.js has wired the field whichever of the two scripts the page loaded first. A field that is not
	 * there then never will be: Show stays, and sends the form as today's switcher does.
	 *
	 * @param {Element} form A form marked `data-wpcpm-pick-submits`.
	 */
	function pickSubmits( form ) {
		var sent = false;
		var again = null;

		form.wpcpmPartsPick = true;

		form.addEventListener( 'wpcpm:picked', function ( event ) {
			var detail = event.detail || {};

			// Once a pick, a name and not the empty choice, and never the name the page is about already.
			if ( sent || ! detail.value || detail.value === detail.own ) {
				return;
			}

			sent = true;
			again = window.setTimeout( function () {
				sent = false;
			}, PICK_AGAIN );

			if ( 'function' === typeof form.requestSubmit ) {
				form.requestSubmit();
			} else {
				form.submit();
			}
		} );

		window.addEventListener( 'pageshow', function ( event ) {
			if ( event.persisted ) {
				window.clearTimeout( again );
				sent = false;
			}
		} );

		function hideShow() {
			if ( ! fieldWired( form ) ) {
				return false;
			}

			each( form.querySelectorAll( '[data-wpcpm-noscript]' ), function ( show ) {
				show.hidden = true;
			} );

			return true;
		}

		if ( ! hideShow() ) {
			window.addEventListener( 'load', hideShow );
		}
	}

	// Report an error and go on: one part that fails does not stop the others.
	function report( error ) {
		if ( window.console && 'function' === typeof window.console.error ) {
			window.console.error( error );
		}
	}

	// One area that fails does not stop the others; it is marked folded only once its work is done, so a
	// later call can try it again. A file box is drawn once.
	function start() {
		each( document.querySelectorAll( AREA ), function ( area ) {
			if ( area.wpcpmPartsFolded ) {
				return;
			}

			try {
				foldArea( area );
				area.wpcpmPartsFolded = true;
			} catch ( error ) {
				report( error );
			}
		} );

		each( document.querySelectorAll( FILE ), function ( box ) {
			if ( box.wpcpmPartsFile ) {
				return;
			}

			try {
				fileBox( box );
			} catch ( error ) {
				report( error );
			}
		} );

		each( document.querySelectorAll( PICKS ), function ( form ) {
			if ( form.wpcpmPartsPick ) {
				return;
			}

			try {
				pickSubmits( form );
			} catch ( error ) {
				report( error );
			}
		} );
	}

	window.wpcpmParts = start;

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;
		var opener;
		var cancel;
		var panel;
		var found;

		if ( ! target || 'function' !== typeof target.closest ) {
			return;
		}

		opener = target.closest( '[aria-controls]' );
		panel = opener ? panelOf( opener ) : null;

		if ( panel ) {
			if ( ! panel.hidden && 'true' === opener.getAttribute( 'aria-expanded' ) ) {
				close( opener.closest( AREA ), panel, false );
			} else {
				show( opener.closest( AREA ), panel, opener );
			}

			return;
		}

		cancel = target.closest( '[data-wpcpm-cancel]' );
		found = cancel ? around( cancel ) : null;

		if ( found ) {
			close( found.area, found.panel, true );
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		var found;
		var area;

		// An Escape a widget inside the panel handled (switcher.js closing its list prevents it), one that
		// ends a composition, and one on a type-and-pick box whose list is open are not the panel's.
		if ( ( 'Escape' !== event.key && 'Esc' !== event.key ) || event.defaultPrevented || event.isComposing ) {
			return;
		}

		if ( event.target && 'function' === typeof event.target.getAttribute && 'combobox' === event.target.getAttribute( 'role' ) && 'true' === event.target.getAttribute( 'aria-expanded' ) ) {
			return;
		}

		found = around( event.target );

		if ( found ) {
			if ( ! found.panel.hidden ) {
				close( found.area, found.panel, true );
			}

			return;
		}

		// Anywhere else inside an area (its opener, a press of its row): its open panel folds, the focus on the
		// opener that opened it.
		area = event.target && 'function' === typeof event.target.closest ? event.target.closest( AREA ) : null;

		if ( area ) {
			closeOpenIn( area );
		}
	} );

	/*
	 * A browser that submits a form by Enter in a one-line box posts it with the form's first submit,
	 * hidden or not; one without `event.submitter` reports none. In a shared panel that could post the
	 * decision that is not shown, so such a post is stopped here, in the capture phase before any other
	 * listener, and made again with the shown submit. Disabling the hidden one is no answer: forms.js
	 * enables controls again after a Back, and a disabled first submit stops Enter from posting at all.
	 */
	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		var found = form && 'function' === typeof form.closest ? around( form ) : null;
		var submits;
		var shown = null;
		var by = event.submitter;

		if ( ! found || passing ) {
			return;
		}

		submits = found.panel.querySelectorAll( '[data-wpcpm-for]' );

		each( submits, function ( submit ) {
			if ( ! shown && ! submit.hidden ) {
				shown = submit;
			}
		} );

		if ( submits.length < 2 || ! shown || by === shown || ( by && ! ( by.hidden && by.hasAttribute( 'data-wpcpm-for' ) ) ) ) {
			return;
		}

		event.preventDefault();
		passing = shown;

		try {
			if ( 'function' === typeof form.requestSubmit ) {
				form.requestSubmit( shown );
			} else {
				shown.click();
			}
		} finally {
			passing = null;
		}
	}, true );

	ready( start );
}() );
