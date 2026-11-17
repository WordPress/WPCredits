<?php
/**
 * The layout harness's side of a suite: keeps the markup a suite draws, by name, for bin/ui/layout/run.mjs.
 *
 * Load it in front of a suite:
 *
 *   WPCPM_LAYOUT_OUT=<cases.json> php -d auto_prepend_file=bin/ui/layout/dump.php bin/test-x.php
 *
 * A suite keeps a case with a guarded call, so it runs as it always did without this file:
 *
 *   if ( function_exists( 'wpcpm_layout_keep' ) ) { wpcpm_layout_keep( 'Students: groups, long', $html ); }
 *
 * Every case is kept with the surface it is drawn on (bin/ui/layout/page.mjs, SURFACES): the third argument names
 * it (`'administrator'`, `'administrator/watch'`, `'sponsor'`); without one, the suite's file names it, from the
 * list below of the suites whose every drawing is one dashboard's; a case from any other suite keeps no surface, and
 * run.mjs refuses it until one is named.
 *
 * When WPCPM_LAYOUT_OUT names a file, every kept case is written there as JSON ({ name: { html, surface } }) as the
 * script ends. Without it nothing is written.
 */
if ( ! function_exists( 'wpcpm_layout_keep' ) ) {
	/**
	 * The surface a suite's every drawing is on, by the suite's file name, or '' when the suite is not listed.
	 *
	 * The Institution Dashboard's suites: its own (test-institution-*, test-institutions-dashboard), the Semester
	 * reports card it holds (test-semester-report, drawn under `WPCPM_UI::enter( 'institution' )`), its Events view
	 * (test-sponsor-event-list, whose `t22_case( ..., 'parts' )` enters `institution`) and the agreement's file box
	 * (test-parts-file).
	 *
	 * @param string $script The suite's path.
	 * @return string
	 */
	function wpcpm_layout_suite_surface( $script ) {
		$file = basename( (string) $script );
		if ( preg_match( '/^test-institutions?-[a-z0-9-]+\.php$/', $file ) || in_array( $file, array( 'test-semester-report.php', 'test-sponsor-event-list.php', 'test-parts-file.php' ), true ) ) {
			return 'institution';
		}
		return '';
	}

	/**
	 * Keeps one case's markup under its name (a name used twice keeps the later markup).
	 *
	 * @param string $name    Case name, as the harness prints it.
	 * @param string $html    The markup the suite drew.
	 * @param string $surface The surface it is drawn on; the suite's own when empty.
	 */
	function wpcpm_layout_keep( $name, $html, $surface = '' ) {
		$script = isset( $_SERVER['SCRIPT_FILENAME'] ) ? (string) $_SERVER['SCRIPT_FILENAME'] : '';
		$GLOBALS['wpcpm_layout_cases'][ (string) $name ] = array(
			'html'    => (string) $html,
			'surface' => '' !== (string) $surface ? (string) $surface : wpcpm_layout_suite_surface( $script ),
		);
	}

	register_shutdown_function(
		static function () {
			$file = getenv( 'WPCPM_LAYOUT_OUT' );
			if ( false === $file || '' === $file ) {
				return;
			}
			$cases = isset( $GLOBALS['wpcpm_layout_cases'] ) ? $GLOBALS['wpcpm_layout_cases'] : array();
			file_put_contents( $file, json_encode( (object) $cases ) );
		}
	);
}
