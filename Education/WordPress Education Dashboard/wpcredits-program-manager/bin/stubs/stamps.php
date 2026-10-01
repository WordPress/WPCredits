<?php
/**
 * The stamps an invitation leaves on an account, by the program role each belongs to, as
 * `WPCPM_Mail::STAMPS` keeps them, named once for every suite that stands in for the mail class:
 * the stand-ins read this map, and bin/test-accounts-table.php compares it to the real one, read off
 * the mail class's source. A suite that loads the real class reads `WPCPM_Mail::STAMPS` instead.
 *
 * Keyed by WPCPM_Roles' constants, so it is required once that class is loaded:
 * `require_once __DIR__ . '/stubs/stamps.php';` from a suite in bin/.
 */

if ( ! defined( 'WPCPM_STUB_STAMPS' ) ) {
	define(
		'WPCPM_STUB_STAMPS',
		array(
			WPCPM_Roles::ROLE_STUDENT     => 'wpcpm_student_invited',
			WPCPM_Roles::ROLE_MENTOR      => 'wpcpm_mentor_invited',
			WPCPM_Roles::ROLE_INSTITUTION => 'wpcpm_inst_invited',
			WPCPM_Roles::ROLE_SPONSOR     => 'wpcpm_sponsor_invited',
		)
	);
}
