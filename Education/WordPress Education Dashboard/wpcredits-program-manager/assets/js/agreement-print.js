/**
 * Open the print dialog once the generated agreement is on screen.
 *
 * The site does not render a PDF. `WPCPM_Agreement_Generate` echoes the agreement as a
 * standalone HTML document with an A4 print stylesheet, and the institution's own browser
 * turns it into the file it saves and signs. This script is the one line that makes that
 * a single click instead of an instruction nobody reads.
 *
 * On `load` and not on `DOMContentLoaded`, because the print dialog freezes the page as it
 * stands: fired earlier, the fonts the stylesheet asks for may not be resolved yet and the
 * PDF comes out in a fallback face. Nothing here is a control. With JavaScript off, or in a
 * browser that refuses the dialog, the reader gets the whole agreement on screen and prints
 * it themselves, which is the same document.
 */
( function () {
	'use strict';

	function askToPrint() {
		try {
			window.print();
		} catch ( error ) {
			// A browser that will not open the dialog by itself has still rendered the
			// document, so there is nothing to report and nothing to fall back to.
		}
	}

	if ( 'complete' === document.readyState ) {
		askToPrint();
		return;
	}

	window.addEventListener( 'load', askToPrint );
}() );
