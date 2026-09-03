/**
 * Open the print dialog once the semester report is on screen.
 *
 * The site renders no PDF. `WPCPM_Semester_Report_Screen` echoes the report as a standalone
 * HTML document with a print stylesheet inlined, and the institution's own browser turns it
 * into the file it saves, mails or hands to a rector. This script is the one line that makes
 * that a single click instead of an instruction nobody reads.
 *
 * On `load` and not on `DOMContentLoaded`, because the print dialog freezes the page as it
 * stands: fired earlier, the fonts the stylesheet asks for may not be resolved yet and the
 * PDF comes out in a fallback face, with the page breaks measured against the wrong metrics.
 * A report whose Student Feedback section starts halfway down a sheet because a serif had not
 * loaded is a report somebody prints twice.
 *
 * Nothing here is a control. With JavaScript off, or in a browser that refuses the dialog, the
 * reader gets the whole report on screen and prints it themselves, which is the same document
 * with the same breaks.
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
