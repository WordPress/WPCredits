<?php
/**
 * Settings tab inside Jetpack CRM's own settings screen.
 *
 * Jetpack CRM builds its settings menu from `$zeroBSCRM_extensionsInstalledList`
 * and then calls `zeroBSCRM_extension_name_{key}` / `zeroBSCRM_extensionhtml_settings_{key}`,
 * so registering there gets us a native tab rather than a separate settings page.
 *
 * @package JPCRM_FreeScout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings screen.
 */
class JPCRM_FS_Settings {

	/**
	 * Option name.
	 */
	const OPTION = 'jpcrm_fs_settings';

	/**
	 * Constructor.
	 */
	public function __construct() {

		// Registering by pushing onto $zeroBSCRM_extensionsInstalledList does not
		// survive: Jetpack CRM's postSettingsIncludes() (init priority 10) calls
		// zeroBSCRM_freeExtensionsInit(), which resets that global to array(),
		// and only afterwards reads it into $zbs->extensions. Anything added
		// from plugins_loaded is wiped in between.
		//
		// So hook the filter that builds $zbs->extensions instead — the settings
		// menu reads that, and a filter can't be mis-ordered.
		add_filter( 'zbs_extensions_array', array( $this, 'register_extension' ) );
	}

	/**
	 * Add ourselves to the CRM's extension list so the settings tab appears.
	 *
	 * @param array $extensions Installed extension slugs.
	 * @return array
	 */
	public function register_extension( $extensions ) {

		if ( ! is_array( $extensions ) ) {
			$extensions = array();
		}

		if ( ! in_array( jpcrm_fs()->slugs['settings'], $extensions, true ) ) {
			$extensions[] = jpcrm_fs()->slugs['settings'];
		}

		return $extensions;
	}

	/**
	 * Persist a submitted settings form.
	 *
	 * @return string|false Notice HTML, or false when nothing was submitted.
	 */
	public function maybe_save() {

		if ( ! isset( $_POST['jpcrm_fs_settings_nonce'] ) ) {
			return false;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return '<div class="notice notice-error inline"><p>'
				. esc_html__( 'You do not have permission to change these settings.', 'jpcrm-freescout' )
				. '</p></div>';
		}

		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['jpcrm_fs_settings_nonce'] ) ), 'jpcrm_fs_settings' ) ) {
			return '<div class="notice notice-error inline"><p>'
				. esc_html__( 'That form expired — please try again.', 'jpcrm-freescout' )
				. '</p></div>';
		}

		$existing = jpcrm_fs()->get_setting();
		$settings = is_array( $existing ) ? $existing : array();

		$settings['url'] = isset( $_POST['jpcrm_fs_url'] )
			? untrailingslashit( esc_url_raw( wp_unslash( $_POST['jpcrm_fs_url'] ) ) )
			: '';

		// Blank means "leave the stored key alone" so it needn't be re-typed.
		$submitted_key = isset( $_POST['jpcrm_fs_api_key'] )
			? sanitize_text_field( wp_unslash( $_POST['jpcrm_fs_api_key'] ) )
			: '';

		if ( $submitted_key !== '' ) {
			$settings['api_key'] = $submitted_key;
		}

		$settings['mailbox_id'] = isset( $_POST['jpcrm_fs_mailbox_id'] )
			? absint( wp_unslash( $_POST['jpcrm_fs_mailbox_id'] ) )
			: '';

		// Same "blank leaves it alone" rule as the API key, so the secret needn't be
		// rendered back into the page to survive a save. Turning webhooks off is now
		// an explicit checkbox rather than an empty field.
		$submitted_secret = isset( $_POST['jpcrm_fs_webhook_secret'] )
			? sanitize_text_field( wp_unslash( $_POST['jpcrm_fs_webhook_secret'] ) )
			: '';

		if ( isset( $_POST['jpcrm_fs_webhook_clear'] ) ) {
			$settings['webhook_secret'] = '';
		} elseif ( $submitted_secret !== '' && strpos( $submitted_secret, '•' ) === false ) {
			$settings['webhook_secret'] = $submitted_secret;
		}

		$settings['sync_customers'] = isset( $_POST['jpcrm_fs_sync_customers'] ) ? 1 : 0;

		// Agent overrides.
		$previous_map = isset( $settings['agent_map'] ) && is_array( $settings['agent_map'] ) ? $settings['agent_map'] : array();
		$map          = array();

		if ( isset( $_POST['jpcrm_fs_agent'] ) && is_array( $_POST['jpcrm_fs_agent'] ) ) {
			foreach ( wp_unslash( $_POST['jpcrm_fs_agent'] ) as $wp_user_id => $agent_id ) {

				$wp_user_id = absint( $wp_user_id );
				$agent_id   = absint( $agent_id );

				if ( $wp_user_id < 1 ) {
					continue;
				}

				if ( $agent_id > 0 ) {
					$map[ $wp_user_id ] = $agent_id;
				}

				$was = isset( $previous_map[ $wp_user_id ] ) ? absint( $previous_map[ $wp_user_id ] ) : 0;

				// Only re-resolve when the override actually changed, so saving
				// unrelated settings doesn't force a lookup for every admin.
				if ( $was !== $agent_id ) {
					jpcrm_fs()->agents->clear_cache( $wp_user_id );
				}
			}
		}

		$settings['agent_map'] = $map;

		update_option( self::OPTION, $settings );

		jpcrm_fs()->api->flush_cache();

		return '<div class="notice notice-success inline"><p>'
			. esc_html__( 'FreeScout settings saved.', 'jpcrm-freescout' )
			. '</p></div>';
	}

	/**
	 * Render the settings tab.
	 */
	public function render() {

		$notice = $this->maybe_save();

		echo '<div class="jpcrm-fs-settings">';
		echo '<h2>' . esc_html__( 'FreeScout Help Desk', 'jpcrm-freescout' ) . '</h2>';

		if ( $notice ) {
			echo wp_kses_post( $notice );
		}

		// Post back to our own tab so the CRM's settings router keeps us here.
		echo '<form method="post" action="' . esc_url( jpcrm_fs()->settings_url() ) . '">';
		wp_nonce_field( 'jpcrm_fs_settings', 'jpcrm_fs_settings_nonce' );

		echo '<table class="form-table" role="presentation"><tbody>';

		$this->render_connection_fields();
		$this->render_mailbox_field();
		$this->render_webhook_fields();
		$this->render_agent_map();

		echo '</tbody></table>';

		echo '<p class="submit"><button type="submit" class="button button-primary">'
			. esc_html__( 'Save Settings', 'jpcrm-freescout' ) . '</button></p>';

		echo '</form>';
		echo '</div>';
	}

	/**
	 * URL and API key.
	 */
	private function render_connection_fields() {

		$url     = jpcrm_fs()->get_setting( 'url' );
		$has_key = jpcrm_fs()->get_setting( 'api_key' ) !== '';

		echo '<tr><th scope="row"><label for="jpcrm_fs_url">' . esc_html__( 'FreeScout URL', 'jpcrm-freescout' ) . '</label></th><td>';
		echo '<input type="url" name="jpcrm_fs_url" id="jpcrm_fs_url" class="regular-text" value="' . esc_attr( $url ) . '" placeholder="https://support.example.com" />';
		echo '<p class="description">' . esc_html__( 'The base URL of your FreeScout install, with no trailing slash.', 'jpcrm-freescout' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="jpcrm_fs_api_key">' . esc_html__( 'API key', 'jpcrm-freescout' ) . '</label></th><td>';
		echo '<input type="password" name="jpcrm_fs_api_key" id="jpcrm_fs_api_key" class="regular-text" autocomplete="new-password" value="" placeholder="'
			. esc_attr( $has_key ? __( '(saved — leave blank to keep)', 'jpcrm-freescout' ) : '' ) . '" />';
		echo '<p class="description">' . esc_html__( 'From Manage → Settings → API & Webhooks in FreeScout. Enable the free "API & Webhooks" module first.', 'jpcrm-freescout' ) . '</p>';
		echo '</td></tr>';
	}

	/**
	 * Default mailbox picker.
	 */
	private function render_mailbox_field() {

		$selected = absint( jpcrm_fs()->get_setting( 'mailbox_id' ) );

		echo '<tr><th scope="row"><label for="jpcrm_fs_mailbox_id">' . esc_html__( 'Default mailbox', 'jpcrm-freescout' ) . '</label></th><td>';

		if ( ! jpcrm_fs()->is_configured() ) {
			echo '<p class="description">' . esc_html__( 'Save a URL and API key first, then reload this page to pick a mailbox.', 'jpcrm-freescout' ) . '</p>';
			echo '</td></tr>';
			return;
		}

		$mailboxes = jpcrm_fs()->api->get_mailboxes();

		if ( is_wp_error( $mailboxes ) ) {
			echo '<p class="description jpcrm-fs-error">' . esc_html( $mailboxes->get_error_message() ) . '</p>';
			echo '<input type="hidden" name="jpcrm_fs_mailbox_id" value="' . esc_attr( $selected ) . '" />';
			echo '</td></tr>';
			return;
		}

		echo '<select name="jpcrm_fs_mailbox_id" id="jpcrm_fs_mailbox_id">';
		echo '<option value="">' . esc_html__( '— none —', 'jpcrm-freescout' ) . '</option>';

		foreach ( $mailboxes as $mailbox ) {

			$id   = isset( $mailbox['id'] ) ? absint( $mailbox['id'] ) : 0;
			$name = isset( $mailbox['name'] ) ? $mailbox['name'] : ( isset( $mailbox['email'] ) ? $mailbox['email'] : '' );

			if ( $id < 1 ) {
				continue;
			}

			echo '<option value="' . esc_attr( $id ) . '" ' . selected( $selected, $id, false ) . '>' . esc_html( $name ) . '</option>';
		}

		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Used when the inbox is listed and when tickets are opened from a contact record.', 'jpcrm-freescout' ) . '</p>';
		echo '</td></tr>';
	}

	/**
	 * Webhook URL and secret.
	 */
	private function render_webhook_fields() {

		$secret = jpcrm_fs()->get_setting( 'webhook_secret' );

		echo '<tr><th scope="row">' . esc_html__( 'Webhook URL', 'jpcrm-freescout' ) . '</th><td>';
		echo '<code class="jpcrm-fs-webhook-url">' . esc_html( JPCRM_FS_Webhooks::url() ) . '</code>';
		echo '<p class="description">'
			. esc_html__( 'Add this as a webhook in FreeScout (Manage → Settings → API & Webhooks), subscribed to the conversation and customer events you care about.', 'jpcrm-freescout' )
			. '</p>';
		echo '</td></tr>';

		// The secret is the HMAC signing key for inbound deliveries, so it is never
		// rendered back into the page — only a mask of its last four characters.
		// Whoever sets it already holds it, since the same string has to be entered
		// in FreeScout.
		$secret_mask = $secret !== '' ? str_repeat( '•', 8 ) . substr( $secret, -4 ) : '';

		echo '<tr><th scope="row"><label for="jpcrm_fs_webhook_secret">' . esc_html__( 'Webhook secret', 'jpcrm-freescout' ) . '</label></th><td>';
		echo '<input type="password" name="jpcrm_fs_webhook_secret" id="jpcrm_fs_webhook_secret" class="regular-text" value="" autocomplete="new-password" spellcheck="false" placeholder="'
			. esc_attr( $secret_mask !== '' ? $secret_mask : __( 'No secret set — webhooks are off', 'jpcrm-freescout' ) )
			. '" />';
		echo '<p class="description">'
			. esc_html__( 'Must match the secret configured in FreeScout. Deliveries that do not verify against it are rejected.', 'jpcrm-freescout' )
			. '</p>';

		if ( $secret_mask !== '' ) {
			echo '<p class="description"><em>' . esc_html(
				sprintf(
					/* translators: %s: masked webhook secret. */
					__( 'Current: %s — leave blank to keep it.', 'jpcrm-freescout' ),
					$secret_mask
				)
			) . '</em></p>';
			echo '<p><label><input type="checkbox" name="jpcrm_fs_webhook_clear" value="1" /> '
				. esc_html__( 'Remove the secret and turn webhooks off', 'jpcrm-freescout' )
				. '</label></p>';
		}

		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Create missing contacts', 'jpcrm-freescout' ) . '</th><td>';
		echo '<label><input type="checkbox" name="jpcrm_fs_sync_customers" value="1" ' . checked( (bool) jpcrm_fs()->get_setting( 'sync_customers' ), true, false ) . ' /> ';
		echo esc_html__( 'Add a CRM contact when a FreeScout customer has no match', 'jpcrm-freescout' ) . '</label>';
		echo '<p class="description">'
			. esc_html__( 'Off by default — leave it off if you would rather your CRM only hold people you put there deliberately.', 'jpcrm-freescout' )
			. '</p>';
		echo '</td></tr>';
	}

	/**
	 * Agent mapping table.
	 */
	private function render_agent_map() {

		echo '<tr><th scope="row">' . esc_html__( 'Agent mapping', 'jpcrm-freescout' ) . '</th><td>';

		echo '<p class="description">'
			. esc_html__( 'FreeScout attributes every reply to one of its own agents. Each administrator who replies from WordPress is matched to a FreeScout agent by email address; set an ID here to override that.', 'jpcrm-freescout' )
			. '</p>';

		if ( ! jpcrm_fs()->is_configured() ) {
			echo '<p class="description">' . esc_html__( 'Available once the connection is saved.', 'jpcrm-freescout' ) . '</p>';
			echo '</td></tr>';
			return;
		}

		$users = jpcrm_fs()->agents->get_candidate_users();

		if ( empty( $users ) ) {
			echo '<p class="description">' . esc_html__( 'No administrators found.', 'jpcrm-freescout' ) . '</p>';
			echo '</td></tr>';
			return;
		}

		echo '<table class="widefat striped jpcrm-fs-agent-map"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'WordPress user', 'jpcrm-freescout' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Email', 'jpcrm-freescout' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'FreeScout agent ID', 'jpcrm-freescout' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Status', 'jpcrm-freescout' ) . '</th>';
		echo '</tr></thead><tbody>';

		$map = jpcrm_fs()->get_setting( 'agent_map', array() );
		$map = is_array( $map ) ? $map : array();

		foreach ( $users as $user ) {

			$override = isset( $map[ $user->ID ] ) ? absint( $map[ $user->ID ] ) : 0;
			$resolved = jpcrm_fs()->agents->get_agent_id( $user->ID );

			echo '<tr>';
			echo '<td>' . esc_html( $user->display_name ) . '</td>';
			echo '<td>' . esc_html( $user->user_email ) . '</td>';
			echo '<td><input type="number" min="0" step="1" class="small-text" name="jpcrm_fs_agent[' . esc_attr( $user->ID ) . ']" value="'
				. esc_attr( $override > 0 ? $override : '' ) . '" placeholder="'
				. esc_attr( is_wp_error( $resolved ) ? '—' : $resolved ) . '" /></td>';

			if ( is_wp_error( $resolved ) ) {
				echo '<td><span class="jpcrm-fs-error">' . esc_html__( 'No match — cannot reply', 'jpcrm-freescout' ) . '</span></td>';
			} else {
				echo '<td><span class="jpcrm-fs-ok">' . sprintf(
					/* translators: %d: FreeScout agent ID. */
					esc_html__( 'Mapped to agent #%d', 'jpcrm-freescout' ),
					absint( $resolved )
				) . '</span></td>';
			}

			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</td></tr>';
	}
}

/**
 * Tab label in the CRM settings menu.
 *
 * @return string
 */
function zeroBSCRM_extension_name_freescout() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- name dictated by Jetpack CRM.
	return __( 'FreeScout', 'jpcrm-freescout' );
}

/**
 * Tab body in the CRM settings menu.
 */
function zeroBSCRM_extensionhtml_settings_freescout() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- name dictated by Jetpack CRM.

	$settings = new JPCRM_FS_Settings();
	$settings->render();
}
