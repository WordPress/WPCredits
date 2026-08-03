<?php
/**
 * Sponsors module.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module 4 — Sponsors.
 *
 * Registers the Sponsor role. Sponsor-facing screens and whatever a sponsor is
 * shown about the students they fund are not built yet, so the module reserves
 * its screen the way Institutions does.
 */
class WPCPM_Sponsors extends WPCPM_Module {

	/**
	 * Module ID.
	 *
	 * @return string
	 */
	public function id() {
		return 'sponsors';
	}

	/**
	 * Module label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Sponsors', 'wpcredits-program-manager' );
	}

	/**
	 * Managed role.
	 *
	 * @return string
	 */
	public function role() {
		return WPCPM_Roles::ROLE_SPONSOR;
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Sponsor accounts and their view of the work they support. Based on the Subscriber role, with access to Sponsor-level content.', 'wpcredits-program-manager' );
	}
}
