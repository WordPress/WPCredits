<?php
/**
 * Module registry.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds the program modules and forwards lifecycle calls to them.
 */
class WPCPM_Modules {

	/**
	 * Instantiated modules, keyed by ID.
	 *
	 * @var WPCPM_Module[]|null
	 */
	private static $modules = null;

	/**
	 * All modules, in menu order.
	 *
	 * @return WPCPM_Module[]
	 */
	public static function all() {
		if ( null === self::$modules ) {
			$modules = array(
				new WPCPM_Students(),
				new WPCPM_Mentors(),
				new WPCPM_Institutions(),
				new WPCPM_Sponsors(),
				new WPCPM_Administrators(),
			);

			self::$modules = array();
			foreach ( $modules as $module ) {
				self::$modules[ $module->id() ] = $module;
			}
		}

		return self::$modules;
	}

	/**
	 * Fetch one module.
	 *
	 * @param string $id Module ID.
	 * @return WPCPM_Module|null
	 */
	public static function get( $id ) {
		$modules = self::all();

		return isset( $modules[ $id ] ) ? $modules[ $id ] : null;
	}

	/**
	 * Boot every module.
	 */
	public static function boot() {
		foreach ( self::all() as $module ) {
			$module->boot();
		}
	}

	/**
	 * Activation hook fan-out.
	 */
	public static function activate() {
		foreach ( self::all() as $module ) {
			$module->activate();
		}
	}

	/**
	 * Deactivation hook fan-out.
	 */
	public static function deactivate() {
		foreach ( self::all() as $module ) {
			$module->deactivate();
		}
	}

	/**
	 * Uninstall hook fan-out.
	 */
	public static function uninstall() {
		foreach ( self::all() as $module ) {
			$module->uninstall();
		}
	}
}
