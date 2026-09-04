<?php
/**
 * Administrators module.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module 4 - Administrators.
 *
 * Program managers use the built-in Administrator role rather than a custom one,
 * so this module adds no role: it grants the program capabilities to
 * Administrator and reports who holds them.
 */
class WPCPM_Administrators extends WPCPM_Module {

	/**
	 * Module ID.
	 *
	 * @return string
	 */
	public function id() {
		return 'administrators';
	}

	/**
	 * Module label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Administrators', 'wpcredits-program-manager' );
	}

	/**
	 * Managed role.
	 *
	 * @return string
	 */
	public function role() {
		return WPCPM_Roles::ROLE_ADMIN;
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Program managers use the built-in WordPress Administrator role. They can read every access level and run every module sync.', 'wpcredits-program-manager' );
	}

	/**
	 * This module needs no provisioning, so its screen is informational rather
	 * than a placeholder for missing work.
	 *
	 * @return bool
	 */
	public function is_implemented() {
		return true;
	}

	/**
	 * Boot the module's front end. The wp-admin screen needs no hooks of its own.
	 */
	public function boot() {
		WPCPM_Administrators_Dashboard::init();
	}

	/**
	 * Activation: the page exists and is gated before anybody can reach it.
	 */
	public function activate() {
		WPCPM_Administrators_Dashboard::ensure_page();
	}

	/**
	 * Uninstall: the page's two options. The page itself is content and stays, as the
	 * other dashboards' pages do.
	 */
	public function uninstall() {
		delete_option( WPCPM_Administrators_Dashboard::OPT_PAGE );
		delete_option( WPCPM_Administrators_Dashboard::OPT_TITLE_FIXED );
	}

	/**
	 * List the administrators and the program capabilities they hold.
	 */
	public function render_admin_page() {
		$admins = get_users(
			array(
				'role'    => WPCPM_Roles::ROLE_ADMIN,
				'orderby' => 'display_name',
				'number'  => 200,
			)
		);

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html( $this->label() ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html( $this->description() ) . '</p>';

		$dashboard = class_exists( 'WPCPM_Administrators_Dashboard' ) ? WPCPM_Administrators_Dashboard::page_url() : '';

		if ( '' !== $dashboard ) {
			printf(
				'<p><a class="button button-primary" href="%1$s">%2$s</a> %3$s</p>',
				esc_url( $dashboard ),
				esc_html__( 'Open the Administrator Dashboard', 'wpcredits-program-manager' ),
				esc_html__( 'Every queue on one page, with its decisions.', 'wpcredits-program-manager' )
			);
		}

		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Program capabilities', 'wpcredits-program-manager' ) . '</h2>';
		echo '<p>' . esc_html__( 'Granted to the Administrator role on activation:', 'wpcredits-program-manager' ) . '</p>';
		echo '<ul class="wpcpm-caps">';
		foreach ( WPCPM_Roles::administrator_caps() as $cap ) {
			echo '<li><code>' . esc_html( $cap ) . '</code></li>';
		}
		echo '</ul>';
		echo '</div>';

		echo '<div class="wpcpm-card">';
		printf(
			'<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>',
			esc_html__( 'Administrator accounts', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( count( $admins ) ) )
		);

		if ( empty( $admins ) ) {
			echo '<p>' . esc_html__( 'No administrators found.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			echo '<table class="widefat striped wpcpm-list"><thead><tr>';
			echo '<th scope="col">' . esc_html__( 'Name', 'wpcredits-program-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Username', 'wpcredits-program-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Can manage program', 'wpcredits-program-manager' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $admins as $admin ) {
				printf(
					'<tr><td><a href="%1$s">%2$s</a></td><td><code>%3$s</code></td><td>%4$s</td></tr>',
					esc_url( get_edit_user_link( $admin->ID ) ),
					esc_html( $admin->display_name ),
					esc_html( $admin->user_login ),
					user_can( $admin->ID, WPCPM_Roles::CAP_MANAGE ) ? esc_html__( 'Yes', 'wpcredits-program-manager' ) : esc_html__( 'No - re-activate the plugin', 'wpcredits-program-manager' )
				);
			}

			echo '</tbody></table>';
		}
		echo '</div>';

		echo '</div>';
	}
}
