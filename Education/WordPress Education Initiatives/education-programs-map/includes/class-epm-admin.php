<?php
/**
 * Dashboard settings screens for managing institutions.
 *
 * @package Education_Programs_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once EPM_PLUGIN_DIR . 'includes/class-epm-list-table.php';

class EPM_Admin {

	const CAPABILITY = 'manage_options';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_form_submission' ) );
		add_action( 'admin_init', array( $this, 'handle_delete' ) );
		add_action( 'admin_init', array( $this, 'handle_settings_submission' ) );
		add_action( 'admin_init', array( $this, 'handle_add_program' ) );
		add_action( 'admin_init', array( $this, 'handle_delete_program' ) );
		add_action( 'admin_init', array( $this, 'handle_airtable_settings_submission' ) );
		add_action( 'admin_init', array( $this, 'handle_airtable_sync' ) );
		add_action( 'admin_notices', array( $this, 'render_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the top-level "Education Programs Map" Dashboard menu and its submenus.
	 */
	public function register_menu() {
		$hook = add_menu_page(
			__( 'Education Programs Map', 'education-programs-map' ),
			__( 'Education Programs Map', 'education-programs-map' ),
			self::CAPABILITY,
			'epm-institutions',
			array( $this, 'render_list_page' ),
			'dashicons-location-alt',
			58
		);

		add_submenu_page(
			'epm-institutions',
			__( 'Institutions', 'education-programs-map' ),
			__( 'All Institutions', 'education-programs-map' ),
			self::CAPABILITY,
			'epm-institutions',
			array( $this, 'render_list_page' )
		);

		add_submenu_page(
			'epm-institutions',
			__( 'Add New Institution', 'education-programs-map' ),
			__( 'Add New', 'education-programs-map' ),
			self::CAPABILITY,
			'epm-add-new',
			array( $this, 'render_form_page' )
		);

		add_submenu_page(
			'epm-institutions',
			__( 'Programs', 'education-programs-map' ),
			__( 'Programs', 'education-programs-map' ),
			self::CAPABILITY,
			'epm-programs',
			array( $this, 'render_programs_page' )
		);

		add_submenu_page(
			'epm-institutions',
			__( 'Education Programs Map Settings', 'education-programs-map' ),
			__( 'Settings', 'education-programs-map' ),
			self::CAPABILITY,
			'epm-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'epm-institutions',
			__( 'Airtable Sync', 'education-programs-map' ),
			__( 'Airtable Sync', 'education-programs-map' ),
			self::CAPABILITY,
			'epm-airtable',
			array( $this, 'render_airtable_page' )
		);

		add_action( "load-{$hook}", array( $this, 'noop' ) );
	}

	public function noop() {}

	/**
	 * Enqueue admin-only CSS on plugin screens.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'epm-' ) ) {
			return;
		}

		wp_enqueue_style( 'epm-admin', EPM_PLUGIN_URL . 'assets/css/admin.css', array(), EPM_VERSION );

		if ( false !== strpos( $hook_suffix, 'epm-add-new' ) ) {
			wp_enqueue_style( 'epm-leaflet', EPM_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.css', array(), '1.9.4' );
			wp_enqueue_script( 'epm-leaflet', EPM_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.js', array(), '1.9.4', true );
			wp_enqueue_script( 'epm-admin-map', EPM_PLUGIN_URL . 'assets/js/admin-map.js', array( 'epm-leaflet' ), EPM_VERSION, true );
		}
	}

	/**
	 * Render the institutions list ("All Institutions") screen.
	 */
	public function render_list_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'education-programs-map' ) );
		}

		$list_table = new EPM_List_Table();
		$list_table->prepare_items();

		$programs = EPM_DB::get_programs();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter, no state change.
		$current_program = isset( $_GET['program'] ) ? sanitize_key( wp_unslash( $_GET['program'] ) ) : '';
		$add_new_url     = esc_url( admin_url( 'admin.php?page=epm-add-new' ) );
		?>
		<div class="wrap epm-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Education Programs Map — Institutions', 'education-programs-map' ); ?></h1>
			<a href="<?php echo esc_url( $add_new_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'education-programs-map' ); ?></a>
			<hr class="wp-header-end">

			<p><?php esc_html_e( 'Manage the WPCC, WPCredits, and Student Club institutions shown on the public map. Use the shortcode [education_programs_map] to display the map on any page or post.', 'education-programs-map' ); ?></p>

			<ul class="subsubsub">
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=epm-institutions' ) ); ?>" class="<?php echo '' === $current_program ? 'current' : ''; ?>">
						<?php esc_html_e( 'All', 'education-programs-map' ); ?> (<?php echo (int) EPM_DB::count_all(); ?>)
					</a>
				</li>
				<?php foreach ( $programs as $key => $label ) : ?>
					<li> |
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=epm-institutions&program=' . $key ) ); ?>" class="<?php echo $current_program === $key ? 'current' : ''; ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>

			<form method="get">
				<input type="hidden" name="page" value="epm-institutions" />
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the "Add New" / edit form screen.
	 */
	public function render_form_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'education-programs-map' ) );
		}

		$editing     = false;
		$institution = null;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only lookup to pre-fill the edit form, no state change.
		$requested_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( $requested_id ) {
			$institution = EPM_DB::get( $requested_id );
			$editing     = (bool) $institution;
		}

		$programs = EPM_DB::get_programs();

		$values = array(
			'id'               => $institution->id ?? 0,
			'name'             => $institution->name ?? '',
			'city'             => $institution->city ?? '',
			'country'          => $institution->country ?? '',
			'latitude'         => $institution->latitude ?? '',
			'longitude'        => $institution->longitude ?? '',
			'programs'         => EPM_DB::parse_programs( $institution->programs ?? '' ),
			'event_count'      => $institution->event_count ?? 0,
			'website'          => $institution->website ?? '',
			'wpcc_url'         => $institution->wpcc_url ?? '',
			'student_club_url' => $institution->student_club_url ?? '',
			'hidden'           => $institution->hidden ?? 0,
		);
		?>
		<div class="wrap epm-wrap">
			<h1><?php echo $editing ? esc_html__( 'Edit Institution', 'education-programs-map' ) : esc_html__( 'Add New Institution', 'education-programs-map' ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=epm-add-new' ) ); ?>">
				<?php wp_nonce_field( 'epm_save_institution' ); ?>
				<input type="hidden" name="epm_action" value="save_institution" />
				<input type="hidden" name="id" value="<?php echo esc_attr( $values['id'] ); ?>" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="epm-name"><?php esc_html_e( 'Institution Name', 'education-programs-map' ); ?></label></th>
						<td><input required name="name" id="epm-name" type="text" class="regular-text" value="<?php echo esc_attr( $values['name'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="epm-city"><?php esc_html_e( 'City', 'education-programs-map' ); ?></label></th>
						<td><input required name="city" id="epm-city" type="text" class="regular-text" value="<?php echo esc_attr( $values['city'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="epm-country"><?php esc_html_e( 'Country', 'education-programs-map' ); ?></label></th>
						<td><input name="country" id="epm-country" type="text" class="regular-text" value="<?php echo esc_attr( $values['country'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Location', 'education-programs-map' ); ?></th>
						<td>
							<div
								id="epm-location-picker"
								class="epm-location-picker"
								data-latitude="<?php echo esc_attr( $values['latitude'] ); ?>"
								data-longitude="<?php echo esc_attr( $values['longitude'] ); ?>"
							></div>
							<p class="description"><?php esc_html_e( 'Click on the map, or drag the marker, to set the coordinates below.', 'education-programs-map' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="epm-latitude"><?php esc_html_e( 'Latitude', 'education-programs-map' ); ?></label></th>
						<td>
							<input required name="latitude" id="epm-latitude" type="text" inputmode="decimal" class="regular-text" value="<?php echo esc_attr( $values['latitude'] ); ?>" placeholder="e.g. 28.6139" />
							<p class="description"><?php esc_html_e( 'Decimal degrees, between -90 and 90.', 'education-programs-map' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="epm-longitude"><?php esc_html_e( 'Longitude', 'education-programs-map' ); ?></label></th>
						<td>
							<input required name="longitude" id="epm-longitude" type="text" inputmode="decimal" class="regular-text" value="<?php echo esc_attr( $values['longitude'] ); ?>" placeholder="e.g. 77.2090" />
							<p class="description"><?php esc_html_e( 'Decimal degrees, between -180 and 180.', 'education-programs-map' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Programs', 'education-programs-map' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Programs', 'education-programs-map' ); ?></legend>
								<?php foreach ( $programs as $key => $label ) : ?>
									<label style="display: block; margin-bottom: 4px;">
										<input
											type="checkbox"
											name="programs[]"
											value="<?php echo esc_attr( $key ); ?>"
											<?php checked( in_array( $key, $values['programs'], true ) ); ?>
										/>
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php esc_html_e( 'An institution can take part in more than one program.', 'education-programs-map' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="epm-event-count"><?php esc_html_e( 'Number of Events', 'education-programs-map' ); ?></label></th>
						<td><input name="event_count" id="epm-event-count" type="number" min="0" step="1" class="small-text" value="<?php echo esc_attr( $values['event_count'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="epm-website"><?php esc_html_e( 'Website (optional)', 'education-programs-map' ); ?></label></th>
						<td><input name="website" id="epm-website" type="url" class="regular-text" value="<?php echo esc_attr( $values['website'] ); ?>" placeholder="https://" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="epm-wpcc-url"><?php esc_html_e( 'WPCC Site (optional)', 'education-programs-map' ); ?></label></th>
						<td>
							<input name="wpcc_url" id="epm-wpcc-url" type="url" class="regular-text" value="<?php echo esc_attr( $values['wpcc_url'] ); ?>" placeholder="https://" />
							<p class="description"><?php esc_html_e( "Link to this institution's WordPress Campus Connect page.", 'education-programs-map' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="epm-student-club-url"><?php esc_html_e( 'Student Club Site (optional)', 'education-programs-map' ); ?></label></th>
						<td>
							<input name="student_club_url" id="epm-student-club-url" type="url" class="regular-text" value="<?php echo esc_attr( $values['student_club_url'] ); ?>" placeholder="https://" />
							<p class="description"><?php esc_html_e( "Link to this institution's Student Club page.", 'education-programs-map' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Visible on Map', 'education-programs-map' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="visible" value="1" <?php checked( empty( $values['hidden'] ) ); ?> />
								<?php esc_html_e( 'Show this institution on the public map', 'education-programs-map' ); ?>
							</label>
							<?php if ( ! empty( $institution->airtable_id ) ) : ?>
								<p class="description"><?php esc_html_e( 'This institution is linked to Airtable — the next sync will re-hide it automatically if it is no longer "Confirmed" there.', 'education-programs-map' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<?php submit_button( $editing ? __( 'Update Institution', 'education-programs-map' ) : __( 'Add Institution', 'education-programs-map' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the "Settings" screen, where the map's on-page size is configured.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'education-programs-map' ) );
		}

		$settings = EPM_Settings::get();
		?>
		<div class="wrap epm-wrap">
			<h1><?php esc_html_e( 'Education Programs Map Settings', 'education-programs-map' ); ?></h1>
			<p><?php esc_html_e( 'Control how large the map appears wherever the [education_programs_map] shortcode is used. Values must be a CSS length, e.g. 520px, 100%, or 60vh.', 'education-programs-map' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=epm-settings' ) ); ?>">
				<?php wp_nonce_field( 'epm_save_settings' ); ?>
				<input type="hidden" name="epm_action" value="save_epm_settings" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="epm-map-width"><?php esc_html_e( 'Map Width', 'education-programs-map' ); ?></label></th>
						<td>
							<input name="width" id="epm-map-width" type="text" class="regular-text" value="<?php echo esc_attr( $settings['width'] ); ?>" placeholder="100%" />
							<p class="description"><?php esc_html_e( 'CSS length, e.g. 100% or 800px.', 'education-programs-map' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="epm-map-height"><?php esc_html_e( 'Map Height', 'education-programs-map' ); ?></label></th>
						<td>
							<input name="height" id="epm-map-height" type="text" class="regular-text" value="<?php echo esc_attr( $settings['height'] ); ?>" placeholder="520px" />
							<p class="description"><?php esc_html_e( 'CSS length, e.g. 520px or 60vh.', 'education-programs-map' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'education-programs-map' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the "Programs" screen, where custom program types can be added.
	 */
	public function render_programs_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'education-programs-map' ) );
		}

		$programs = EPM_Programs::get_all();
		?>
		<div class="wrap epm-wrap">
			<h1><?php esc_html_e( 'Programs', 'education-programs-map' ); ?></h1>
			<p><?php esc_html_e( 'Programs (such as WPCC, WPCredits, or Student Club) can be assigned to institutions and used to filter the map.', 'education-programs-map' ); ?></p>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Program', 'education-programs-map' ); ?></th>
						<th><?php esc_html_e( 'Institutions', 'education-programs-map' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'education-programs-map' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $programs as $key => $label ) : ?>
						<?php $count = EPM_DB::count_by_program( $key ); ?>
						<tr>
							<td><?php echo esc_html( $label ); ?></td>
							<td><?php echo (int) $count; ?></td>
							<td>
								<?php if ( $count > 0 ) : ?>
									<span class="description"><?php esc_html_e( 'In use — cannot delete', 'education-programs-map' ); ?></span>
								<?php else : ?>
									<?php
									$delete_url = wp_nonce_url(
										add_query_arg(
											array(
												'page'   => 'epm-programs',
												'action' => 'delete_program',
												'key'    => $key,
											),
											admin_url( 'admin.php' )
										),
										'epm_delete_program_' . $key
									);
									?>
									<a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this program?', 'education-programs-map' ) ); ?>');"><?php esc_html_e( 'Delete', 'education-programs-map' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Add New Program', 'education-programs-map' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=epm-programs' ) ); ?>">
				<?php wp_nonce_field( 'epm_add_program' ); ?>
				<input type="hidden" name="epm_action" value="add_program" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="epm-program-label"><?php esc_html_e( 'Program Name', 'education-programs-map' ); ?></label></th>
						<td><input required name="label" id="epm-program-label" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Meetup Chapter', 'education-programs-map' ); ?>" /></td>
					</tr>
				</table>

				<?php submit_button( __( 'Add Program', 'education-programs-map' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the "Airtable Sync" screen: one independent connection, sync
	 * trigger, and result panel per program, since each program can live in a
	 * completely different Airtable base.
	 */
	public function render_airtable_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'education-programs-map' ) );
		}

		$program_labels = EPM_DB::get_programs();
		$fallback_names = array(
			'wpcc'         => __( 'WPCC', 'education-programs-map' ),
			'wpcredits'    => __( 'WPCredits', 'education-programs-map' ),
			'student_club' => __( 'Student Club', 'education-programs-map' ),
		);
		?>
		<div class="wrap epm-wrap">
			<h1><?php esc_html_e( 'Airtable Sync', 'education-programs-map' ); ?></h1>
			<p><?php esc_html_e( 'Pull institutions in from Airtable instead of adding them one by one. Each program below has its own independent connection, since WPCC, WPCredits, and Student Club can each live in a different Airtable base. A synced institution is matched by its Airtable record, so re-running a sync updates existing entries instead of duplicating them, and hides ones that no longer match instead of deleting them.', 'education-programs-map' ); ?></p>

			<?php foreach ( EPM_Airtable::PROGRAM_KEYS as $program ) : ?>
				<?php
				$label         = $program_labels[ $program ] ?? $fallback_names[ $program ];
				$settings      = EPM_Airtable::get_settings( $program );
				$has_token     = '' !== $settings['token'];
				$last_result   = EPM_Airtable::get_last_result( $program );
				$configured    = EPM_Airtable::is_configured( $program );
				$next_auto_run = wp_next_scheduled( EPM_Airtable::CRON_HOOK );
				?>
				<h2><?php echo esc_html( $label ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=epm-airtable' ) ); ?>">
					<?php wp_nonce_field( 'epm_save_airtable_settings_' . $program ); ?>
					<input type="hidden" name="epm_action" value="save_airtable_settings" />
					<input type="hidden" name="program" value="<?php echo esc_attr( $program ); ?>" />

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="epm-airtable-token-<?php echo esc_attr( $program ); ?>"><?php esc_html_e( 'Personal Access Token', 'education-programs-map' ); ?></label></th>
							<td>
								<input name="token" id="epm-airtable-token-<?php echo esc_attr( $program ); ?>" type="password" class="regular-text" autocomplete="off" placeholder="<?php echo $has_token ? esc_attr__( 'Saved — leave blank to keep it', 'education-programs-map' ) : 'patXXXXXXXXXXXXXX.XXXXXXXX'; ?>" />
								<p class="description"><?php esc_html_e( 'Needs data.records:read access to the base below. Leave blank when saving other fields to keep the current token.', 'education-programs-map' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="epm-airtable-base-<?php echo esc_attr( $program ); ?>"><?php esc_html_e( 'Base ID', 'education-programs-map' ); ?></label></th>
							<td><input name="base_id" id="epm-airtable-base-<?php echo esc_attr( $program ); ?>" type="text" class="regular-text" value="<?php echo esc_attr( $settings['base_id'] ); ?>" placeholder="appXXXXXXXXXXXXXX" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="epm-airtable-table-<?php echo esc_attr( $program ); ?>"><?php esc_html_e( 'Institutions Table', 'education-programs-map' ); ?></label></th>
							<td><input name="table_name" id="epm-airtable-table-<?php echo esc_attr( $program ); ?>" type="text" class="regular-text" value="<?php echo esc_attr( $settings['table_name'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="epm-airtable-countries-table-<?php echo esc_attr( $program ); ?>"><?php esc_html_e( 'Countries Table', 'education-programs-map' ); ?></label></th>
							<td>
								<input name="countries_table" id="epm-airtable-countries-table-<?php echo esc_attr( $program ); ?>" type="text" class="regular-text" value="<?php echo esc_attr( $settings['countries_table'] ); ?>" />
								<p class="description"><?php esc_html_e( 'The table the Institutions table\'s "Country" field links to, used to resolve country names.', 'education-programs-map' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="epm-airtable-filter-<?php echo esc_attr( $program ); ?>"><?php esc_html_e( 'Filter Formula', 'education-programs-map' ); ?></label></th>
							<td>
								<input name="filter_formula" id="epm-airtable-filter-<?php echo esc_attr( $program ); ?>" type="text" class="large-text" value="<?php echo esc_attr( $settings['filter_formula'] ); ?>" />
								<p class="description"><?php esc_html_e( 'An Airtable formula; only matching records are imported. Leave blank to import every record.', 'education-programs-map' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Automatic Sync', 'education-programs-map' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="auto_sync" value="1" <?php checked( ! empty( $settings['auto_sync'] ) ); ?> />
									<?php esc_html_e( 'Automatically sync every 7 days', 'education-programs-map' ); ?>
								</label>
								<?php if ( ! empty( $settings['auto_sync'] ) && $next_auto_run ) : ?>
									<p class="description">
										<?php
										printf(
											/* translators: %s: date and time of the next scheduled automatic sync. */
											esc_html__( 'Next automatic sync check: %s.', 'education-programs-map' ),
											esc_html( wp_date( 'Y-m-d H:i', $next_auto_run ) )
										);
										?>
									</p>
								<?php endif; ?>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Connection', 'education-programs-map' ), 'secondary' ); ?>
				</form>

				<?php if ( ! $configured ) : ?>
					<p class="description"><?php esc_html_e( 'Save a token, base ID, and table name above before syncing.', 'education-programs-map' ); ?></p>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=epm-airtable' ) ); ?>">
						<?php wp_nonce_field( 'epm_run_airtable_sync_' . $program ); ?>
						<input type="hidden" name="epm_action" value="run_airtable_sync" />
						<input type="hidden" name="program" value="<?php echo esc_attr( $program ); ?>" />
						<?php submit_button( __( 'Sync Now', 'education-programs-map' ), 'primary', 'submit', false ); ?>
						<p class="description"><?php esc_html_e( 'Fetches matching records from Airtable and looks up coordinates for each one; this can take a moment for larger tables.', 'education-programs-map' ); ?></p>
					</form>
				<?php endif; ?>

				<?php if ( is_array( $last_result ) ) : ?>
					<p>
						<?php
						printf(
							/* translators: 1: sync trigger word, either manually or automatically. 2: date and time. */
							esc_html__( 'Ran %1$s on %2$s.', 'education-programs-map' ),
							'auto' === $last_result['trigger'] ? esc_html__( 'automatically', 'education-programs-map' ) : esc_html__( 'manually', 'education-programs-map' ),
							esc_html( wp_date( 'Y-m-d H:i', $last_result['timestamp'] ) )
						);
						?>
					</p>
					<?php if ( isset( $last_result['error'] ) ) : ?>
						<p><?php echo esc_html( $last_result['error'] ); ?></p>
					<?php else : ?>
						<p>
							<?php
							printf(
								/* translators: 1: number created, 2: number updated, 3: number newly hidden. */
								esc_html__( 'Created %1$d, updated %2$d, hid %3$d that are no longer matching.', 'education-programs-map' ),
								(int) $last_result['created'],
								(int) $last_result['updated'],
								(int) ( $last_result['hidden'] ?? 0 )
							);
							?>
						</p>
						<?php if ( ! empty( $last_result['skipped'] ) ) : ?>
							<p><?php esc_html_e( 'Skipped:', 'education-programs-map' ); ?></p>
							<ul style="list-style: disc; margin-left: 20px;">
								<?php foreach ( $last_result['skipped'] as $reason ) : ?>
									<li><?php echo esc_html( $reason ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					<?php endif; ?>
				<?php endif; ?>
				<hr />
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Handle the add/edit form submission.
	 */
	public function handle_form_submission() {
		if ( ! isset( $_POST['epm_action'] ) || 'save_institution' !== $_POST['epm_action'] ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'education-programs-map' ) );
		}

		check_admin_referer( 'epm_save_institution' );

		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$errors = array();

		$name             = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$city             = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
		$country          = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$programs         = isset( $_POST['programs'] ) ? array_map( 'sanitize_key', wp_unslash( (array) $_POST['programs'] ) ) : array();
		$website          = isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '';
		$wpcc_url         = isset( $_POST['wpcc_url'] ) ? esc_url_raw( wp_unslash( $_POST['wpcc_url'] ) ) : '';
		$student_club_url = isset( $_POST['student_club_url'] ) ? esc_url_raw( wp_unslash( $_POST['student_club_url'] ) ) : '';
		$events           = isset( $_POST['event_count'] ) ? absint( $_POST['event_count'] ) : 0;
		$lat_raw          = isset( $_POST['latitude'] ) ? sanitize_text_field( wp_unslash( $_POST['latitude'] ) ) : '';
		$lng_raw          = isset( $_POST['longitude'] ) ? sanitize_text_field( wp_unslash( $_POST['longitude'] ) ) : '';

		if ( '' === $name ) {
			$errors[] = __( 'Institution name is required.', 'education-programs-map' );
		}

		if ( '' === $city ) {
			$errors[] = __( 'City is required.', 'education-programs-map' );
		}

		$valid_programs = array_intersect( $programs, array_keys( EPM_DB::get_programs() ) );
		if ( empty( $valid_programs ) ) {
			$errors[] = __( 'Select at least one program.', 'education-programs-map' );
		}

		if ( ! is_numeric( $lat_raw ) || (float) $lat_raw < -90 || (float) $lat_raw > 90 ) {
			$errors[] = __( 'Latitude must be a number between -90 and 90.', 'education-programs-map' );
		}

		if ( ! is_numeric( $lng_raw ) || (float) $lng_raw < -180 || (float) $lng_raw > 180 ) {
			$errors[] = __( 'Longitude must be a number between -180 and 180.', 'education-programs-map' );
		}

		if ( ! empty( $errors ) ) {
			set_transient( 'epm_admin_errors', $errors, 60 );
			$redirect_args = array( 'page' => 'epm-add-new' );
			if ( $id ) {
				$redirect_args['id'] = $id;
			}
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
			exit;
		}

		$data = array(
			'name'             => $name,
			'city'             => $city,
			'country'          => $country,
			'latitude'         => (float) $lat_raw,
			'longitude'        => (float) $lng_raw,
			'programs'         => $valid_programs,
			'event_count'      => $events,
			'website'          => $website,
			'wpcc_url'         => $wpcc_url,
			'student_club_url' => $student_club_url,
			'hidden'           => empty( $_POST['visible'] ),
		);

		if ( $id ) {
			$result = EPM_DB::update( $id, $data );
		} else {
			$result = EPM_DB::insert( $data );
		}

		if ( is_wp_error( $result ) ) {
			set_transient( 'epm_admin_errors', array( $result->get_error_message() ), 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=epm-add-new' ) );
			exit;
		}

		set_transient( 'epm_admin_success', $id ? __( 'Institution updated.', 'education-programs-map' ) : __( 'Institution added.', 'education-programs-map' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=epm-institutions' ) );
		exit;
	}

	/**
	 * Handle deletion requests from the list table row actions.
	 */
	public function handle_delete() {
		if ( ! isset( $_GET['page'], $_GET['action'], $_GET['id'] ) || 'epm-institutions' !== $_GET['page'] || 'delete' !== $_GET['action'] ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'education-programs-map' ) );
		}

		$id = absint( $_GET['id'] );
		check_admin_referer( 'epm_delete_institution_' . $id );

		EPM_DB::delete( $id );

		set_transient( 'epm_admin_success', __( 'Institution deleted.', 'education-programs-map' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=epm-institutions' ) );
		exit;
	}

	/**
	 * Handle the settings form submission (map width/height).
	 */
	public function handle_settings_submission() {
		if ( ! isset( $_POST['epm_action'] ) || 'save_epm_settings' !== $_POST['epm_action'] ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'education-programs-map' ) );
		}

		check_admin_referer( 'epm_save_settings' );

		$defaults = EPM_Settings::defaults();
		$width    = isset( $_POST['width'] ) ? sanitize_text_field( wp_unslash( $_POST['width'] ) ) : '';
		$height   = isset( $_POST['height'] ) ? sanitize_text_field( wp_unslash( $_POST['height'] ) ) : '';

		$errors = array();

		if ( ! EPM_Settings::is_valid_dimension( $width ) ) {
			$errors[] = __( 'Map width must be a valid CSS length, e.g. 100% or 800px.', 'education-programs-map' );
			$width    = $defaults['width'];
		}

		if ( ! EPM_Settings::is_valid_dimension( $height ) ) {
			$errors[] = __( 'Map height must be a valid CSS length, e.g. 520px or 60vh.', 'education-programs-map' );
			$height   = $defaults['height'];
		}

		if ( ! empty( $errors ) ) {
			set_transient( 'epm_admin_errors', $errors, 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=epm-settings' ) );
			exit;
		}

		update_option(
			EPM_Settings::OPTION_NAME,
			array(
				'width'  => $width,
				'height' => $height,
			)
		);

		set_transient( 'epm_admin_success', __( 'Settings saved.', 'education-programs-map' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=epm-settings' ) );
		exit;
	}

	/**
	 * Handle submission of the "Add New Program" form.
	 */
	public function handle_add_program() {
		if ( ! isset( $_POST['epm_action'] ) || 'add_program' !== $_POST['epm_action'] ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'education-programs-map' ) );
		}

		check_admin_referer( 'epm_add_program' );

		$label  = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$result = EPM_Programs::add( $label );

		if ( is_wp_error( $result ) ) {
			set_transient( 'epm_admin_errors', array( $result->get_error_message() ), 60 );
		} else {
			set_transient( 'epm_admin_success', __( 'Program added.', 'education-programs-map' ), 60 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=epm-programs' ) );
		exit;
	}

	/**
	 * Handle deletion of a program from the Programs screen.
	 */
	public function handle_delete_program() {
		if ( ! isset( $_GET['page'], $_GET['action'], $_GET['key'] ) || 'epm-programs' !== $_GET['page'] || 'delete_program' !== $_GET['action'] ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'education-programs-map' ) );
		}

		$key = sanitize_key( wp_unslash( $_GET['key'] ) );
		check_admin_referer( 'epm_delete_program_' . $key );

		if ( EPM_DB::count_by_program( $key ) > 0 ) {
			set_transient( 'epm_admin_errors', array( __( 'This program is still assigned to one or more institutions and cannot be deleted.', 'education-programs-map' ) ), 60 );
		} else {
			EPM_Programs::delete( $key );
			set_transient( 'epm_admin_success', __( 'Program deleted.', 'education-programs-map' ), 60 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=epm-programs' ) );
		exit;
	}

	/**
	 * Handle submission of the Airtable connection settings form.
	 */
	public function handle_airtable_settings_submission() {
		if ( ! isset( $_POST['epm_action'] ) || 'save_airtable_settings' !== $_POST['epm_action'] ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'education-programs-map' ) );
		}

		$program = isset( $_POST['program'] ) ? sanitize_key( wp_unslash( $_POST['program'] ) ) : '';
		if ( ! in_array( $program, EPM_Airtable::PROGRAM_KEYS, true ) ) {
			wp_die( esc_html__( 'Unknown program.', 'education-programs-map' ) );
		}

		check_admin_referer( 'epm_save_airtable_settings_' . $program );

		$current = EPM_Airtable::get_settings( $program );

		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		if ( '' === $token ) {
			$token = $current['token']; // Keep the existing token when the field is left blank.
		}

		$settings = array(
			'token'           => $token,
			'base_id'         => isset( $_POST['base_id'] ) ? sanitize_text_field( wp_unslash( $_POST['base_id'] ) ) : '',
			'table_name'      => isset( $_POST['table_name'] ) ? sanitize_text_field( wp_unslash( $_POST['table_name'] ) ) : '',
			'countries_table' => isset( $_POST['countries_table'] ) ? sanitize_text_field( wp_unslash( $_POST['countries_table'] ) ) : '',
			'filter_formula'  => isset( $_POST['filter_formula'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_formula'] ) ) : '',
			'auto_sync'       => ! empty( $_POST['auto_sync'] ),
		);

		EPM_Airtable::save_settings( $program, $settings );
		EPM_Airtable::maybe_schedule();

		set_transient( 'epm_admin_success', __( 'Airtable connection saved.', 'education-programs-map' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=epm-airtable' ) );
		exit;
	}

	/**
	 * Handle a manually triggered Airtable sync.
	 */
	public function handle_airtable_sync() {
		if ( ! isset( $_POST['epm_action'] ) || 'run_airtable_sync' !== $_POST['epm_action'] ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'education-programs-map' ) );
		}

		$program = isset( $_POST['program'] ) ? sanitize_key( wp_unslash( $_POST['program'] ) ) : '';
		if ( ! in_array( $program, EPM_Airtable::PROGRAM_KEYS, true ) ) {
			wp_die( esc_html__( 'Unknown program.', 'education-programs-map' ) );
		}

		check_admin_referer( 'epm_run_airtable_sync_' . $program );

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 180 );
		}

		$result = EPM_Airtable::run_sync( $program );
		EPM_Airtable::store_result( $program, $result, 'manual' );

		if ( is_wp_error( $result ) ) {
			set_transient( 'epm_admin_errors', array( $result->get_error_message() ), 60 );
		} else {
			set_transient(
				'epm_admin_success',
				sprintf(
					/* translators: 1: number created, 2: number updated, 3: number hidden, 4: number skipped. */
					__( 'Airtable sync complete: %1$d created, %2$d updated, %3$d hidden, %4$d skipped.', 'education-programs-map' ),
					(int) $result['created'],
					(int) $result['updated'],
					(int) $result['hidden'],
					count( $result['skipped'] )
				),
				60
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=epm-airtable' ) );
		exit;
	}

	/**
	 * Render success/error notices stored in transients after a redirect.
	 */
	public function render_notices() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'epm-' ) ) {
			return;
		}

		$errors = get_transient( 'epm_admin_errors' );
		if ( $errors ) {
			delete_transient( 'epm_admin_errors' );
			echo '<div class="notice notice-error"><ul>';
			foreach ( (array) $errors as $error ) {
				echo '<li>' . esc_html( $error ) . '</li>';
			}
			echo '</ul></div>';
		}

		$success = get_transient( 'epm_admin_success' );
		if ( $success ) {
			delete_transient( 'epm_admin_success' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $success ) . '</p></div>';
		}
	}
}
