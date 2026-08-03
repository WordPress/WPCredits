<?php
/**
 * Plugin Name: Email Inbox for Jetpack CRM
 * Plugin URI:  https://github.com/maciejpilarski/jpcrm-freescout
 * Description: Brings your FreeScout help desk into the WordPress dashboard and wires it into Jetpack CRM — support tickets on the contact record, activity-log entries, and replying from within WordPress.
 * Version:     1.0.4
 * Author:      Maciej Pilarski
 * License:     GPL-2.0-or-later
 * Text Domain: jpcrm-freescout
 *
 * Requires Jetpack CRM (zero-bs-crm) 5.0+ and a FreeScout install with the
 * free "API & Webhooks" module enabled.
 *
 * @package JPCRM_FreeScout
 */

defined( 'ABSPATH' ) || exit;

define( 'JPCRM_FS_VERSION', '1.0.4' );
define( 'JPCRM_FS_FILE', __FILE__ );
define( 'JPCRM_FS_PATH', plugin_dir_path( __FILE__ ) );
define( 'JPCRM_FS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class.
 *
 * Everything is instantiated on `plugins_loaded` at a late priority so that
 * Jetpack CRM core (and its `$zbs` global) is guaranteed to be up first.
 */
final class JPCRM_FreeScout {

	/**
	 * Singleton instance.
	 *
	 * @var JPCRM_FreeScout|null
	 */
	private static $instance = null;

	/**
	 * FreeScout API client.
	 *
	 * @var JPCRM_FS_API
	 */
	public $api;

	/**
	 * WP user <-> FreeScout agent mapping.
	 *
	 * @var JPCRM_FS_Agents
	 */
	public $agents;

	/**
	 * CRM contact resolution and activity logging.
	 *
	 * @var JPCRM_FS_Contacts
	 */
	public $contacts;

	/**
	 * Admin page slugs.
	 *
	 * @var array
	 */
	public $slugs = array(
		'inbox'    => 'jpcrm-freescout-inbox',
		'settings' => 'freescout',
	);

	/**
	 * Instance accessor.
	 *
	 * @return JPCRM_FreeScout
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'boot' ), 20 );
	}

	/**
	 * Load the plugin, provided Jetpack CRM is present.
	 */
	public function boot() {

		if ( ! $this->crm_is_active() ) {
			add_action( 'admin_notices', array( $this, 'render_missing_crm_notice' ) );
			return;
		}

		require_once JPCRM_FS_PATH . 'includes/class-jpcrm-fs-api.php';
		require_once JPCRM_FS_PATH . 'includes/class-jpcrm-fs-agents.php';
		require_once JPCRM_FS_PATH . 'includes/class-jpcrm-fs-contacts.php';
		require_once JPCRM_FS_PATH . 'includes/class-jpcrm-fs-crm.php';
		require_once JPCRM_FS_PATH . 'includes/class-jpcrm-fs-inbox.php';
		require_once JPCRM_FS_PATH . 'includes/class-jpcrm-fs-ajax.php';
		require_once JPCRM_FS_PATH . 'includes/class-jpcrm-fs-webhooks.php';
		require_once JPCRM_FS_PATH . 'includes/class-jpcrm-fs-settings.php';

		$this->api      = new JPCRM_FS_API();
		$this->agents   = new JPCRM_FS_Agents( $this->api );
		$this->contacts = new JPCRM_FS_Contacts();

		new JPCRM_FS_CRM();
		new JPCRM_FS_Inbox();
		new JPCRM_FS_Ajax();
		new JPCRM_FS_Webhooks();
		new JPCRM_FS_Settings();

		load_plugin_textdomain( 'jpcrm-freescout', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Is Jetpack CRM present?
	 *
	 * Deliberately checks for the class and not for `$zbs` or `ZEROBSCRM_PATH`:
	 * Jetpack CRM includes its core class while its plugin file executes, but
	 * only assigns the `$zbs` global and defines its constants on `init`
	 * priority 0 — both of which are still missing at `plugins_loaded`.
	 *
	 * We must register on `plugins_loaded` regardless, because the CRM applies
	 * `zbs_approved_sources` at `init` priority 10; hooking any later would
	 * miss it. Everything that actually dereferences `$zbs` runs later still
	 * (admin render, AJAX, REST), by which point it exists.
	 *
	 * @return bool
	 */
	public function crm_is_active() {
		return class_exists( 'ZeroBSCRM' );
	}

	/**
	 * Settings accessor.
	 *
	 * @param string $key     Setting key, or empty for the whole array.
	 * @param mixed  $default Fallback when unset.
	 * @return mixed
	 */
	public function get_setting( $key = '', $default = '' ) {

		$settings = get_option( 'jpcrm_fs_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		if ( $key === '' ) {
			return $settings;
		}

		return isset( $settings[ $key ] ) && $settings[ $key ] !== '' ? $settings[ $key ] : $default;
	}

	/**
	 * Is the plugin configured enough to talk to FreeScout?
	 *
	 * @return bool
	 */
	public function is_configured() {
		return $this->get_setting( 'url' ) !== '' && $this->get_setting( 'api_key' ) !== '';
	}

	/**
	 * URL of our tab on the CRM settings screen.
	 *
	 * The page slug is read from the CRM itself rather than hard-coded.
	 *
	 * @return string
	 */
	public function settings_url() {

		global $zbs;

		$page = isset( $zbs->slugs['settings'] ) ? $zbs->slugs['settings'] : 'zerobscrm-plugin-settings';

		return add_query_arg(
			array(
				'page' => $page,
				'tab'  => $this->slugs['settings'],
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Build a link to a conversation in the FreeScout UI.
	 *
	 * @param int $conversation_id FreeScout conversation ID.
	 * @return string
	 */
	public function conversation_url( $conversation_id ) {

		$base = $this->get_setting( 'url' );

		if ( $base === '' ) {
			return '';
		}

		return trailingslashit( $base ) . 'conversation/' . absint( $conversation_id );
	}

	/**
	 * Notice shown when Jetpack CRM isn't available.
	 */
	public function render_missing_crm_notice() {

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Email Inbox for Jetpack CRM needs Jetpack CRM to be installed and activated.', 'jpcrm-freescout' );
		echo '</p></div>';
	}
}

/**
 * Global accessor.
 *
 * @return JPCRM_FreeScout
 */
function jpcrm_fs() {
	return JPCRM_FreeScout::instance();
}

jpcrm_fs();
