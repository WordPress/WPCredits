<?php
/**
 * The Administrator Dashboard's page, as the suites that link into it read it: its address, which a
 * check empties for a page that is missing, and what the class says then. That sentence is the
 * class's to keep, so this one is a sentence of its own: a screen or a block that wrote the real
 * words out itself would print those, not these.
 *
 * Every row of the Institutions screen's queue links into the page (bin/test-institutions-screen.php),
 * and so does the agreement review block on the screen that only reads it
 * (bin/test-institution-panel.php), every item on the Sponsors screen's queue tab
 * (bin/test-sponsors-screen.php) and an opened sponsor application the page's card lists
 * (bin/test-sponsor-application.php). Declared where the run reaches the require, and only when the
 * real class is not loaded.
 *
 * Loaded with `require_once __DIR__ . '/stubs/administrators-dashboard.php';` from a suite.
 */

if ( ! class_exists( 'WPCPM_Administrators_Dashboard' ) ) {
	/** The page's address and the sentence for a missing page. */
	class WPCPM_Administrators_Dashboard {
		public static $url = 'https://example.test/administrator-dashboard/';
		public static function page_url() { return self::$url; }
		public static function page_missing() { return 'The dashboard class says its page is missing.'; }
	}
}
