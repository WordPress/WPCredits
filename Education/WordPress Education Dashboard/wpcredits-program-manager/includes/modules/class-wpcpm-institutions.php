<?php
/**
 * Institutions module.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module 3 — Institutions.
 *
 * Registers the Institution role. Provisioning and the institution-facing
 * screens are not built yet.
 */
class WPCPM_Institutions extends WPCPM_Module {

	/**
	 * Module ID.
	 *
	 * @return string
	 */
	public function id() {
		return 'institutions';
	}

	/**
	 * Module label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Institutions', 'wpcredits-program-manager' );
	}

	/**
	 * Managed role.
	 *
	 * @return string
	 */
	public function role() {
		return WPCPM_Roles::ROLE_INSTITUTION;
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Educational institution accounts and their view of enrolled students. Based on the Subscriber role, with access to Institution-level content.', 'wpcredits-program-manager' );
	}
}
