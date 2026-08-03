<?php
/**
 * Tool registry.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds the plugin's tools and forwards lifecycle calls to them.
 */
class WPCPM_Tools {

	/**
	 * Instantiated tools, keyed by ID.
	 *
	 * @var WPCPM_Tool[]|null
	 */
	private static $tools = null;

	/**
	 * All tools, in menu order.
	 *
	 * @return WPCPM_Tool[]
	 */
	public static function all() {
		if ( null === self::$tools ) {
			$tools = array( new WPCPM_Header_Notices(), new WPCPM_Handbook(), new WPCPM_Mentor_Checker() );

			/**
			 * Filter the registered tools.
			 *
			 * @param WPCPM_Tool[] $tools Tool instances.
			 */
			$tools = (array) apply_filters( 'wpcpm_tools', $tools );

			self::$tools = array();
			foreach ( $tools as $tool ) {
				if ( $tool instanceof WPCPM_Tool ) {
					self::$tools[ $tool->id() ] = $tool;
				}
			}
		}

		return self::$tools;
	}

	/**
	 * Fetch one tool.
	 *
	 * @param string $id Tool ID.
	 * @return WPCPM_Tool|null
	 */
	public static function get( $id ) {
		$tools = self::all();

		return isset( $tools[ $id ] ) ? $tools[ $id ] : null;
	}

	/**
	 * Boot every tool.
	 */
	public static function boot() {
		foreach ( self::all() as $tool ) {
			$tool->boot();
		}
	}

	/**
	 * Activation hook fan-out.
	 */
	public static function activate() {
		foreach ( self::all() as $tool ) {
			$tool->activate();
		}
	}

	/**
	 * Deactivation hook fan-out.
	 */
	public static function deactivate() {
		foreach ( self::all() as $tool ) {
			$tool->deactivate();
		}
	}

	/**
	 * Uninstall hook fan-out.
	 */
	public static function uninstall() {
		foreach ( self::all() as $tool ) {
			$tool->uninstall();
		}
	}
}
