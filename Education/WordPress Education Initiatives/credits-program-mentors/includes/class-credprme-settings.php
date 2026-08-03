<?php
/**
 * Admin settings page.
 *
 * @package CreditsProgramMentors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the "Credits Program Mentors" settings page and stores the plugin options.
 */
class CREDPRME_Settings {

	const OPTION_KEY = 'credprme_settings';

	/**
	 * Constructor. Hooks into WordPress admin.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_credprme_refresh_data', array( $this, 'handle_refresh_data' ) );
	}

	/**
	 * Handle the "Refresh data" button: clear the cached Airtable records.
	 */
	public function handle_refresh_data() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'credits-program-mentors' ) );
		}
		check_admin_referer( 'credprme_refresh_data' );

		CREDPRME_Airtable_Client::flush_cache();

		wp_safe_redirect( add_query_arg( 'credprme_refreshed', '1', admin_url( 'admin.php?page=credits-program-mentors' ) ) );
		exit;
	}

	/**
	 * Default settings, pre-filled with the "Sponsored mentors" base and table IDs.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'api_token' => '',
			'base_id'   => 'appIzQKfwTn5dyPVp',
			'table_id'  => 'tblJmEYgBWYxVuzUw',
			'view_id'   => '',
			'cache_ttl' => HOUR_IN_SECONDS,
			'show_map'  => true,
		);
	}

	/**
	 * Get the merged plugin settings.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Register the top-level admin menu item.
	 */
	public function add_menu() {
		add_menu_page(
			__( 'Credits Program Mentors', 'credits-program-mentors' ),
			__( 'Credits Program Mentors', 'credits-program-mentors' ),
			'manage_options',
			'credits-program-mentors',
			array( $this, 'render_page' ),
			'dashicons-groups',
			58
		);
	}

	/**
	 * Register the setting, section and fields via the Settings API.
	 */
	public function register_settings() {
		register_setting(
			'credprme_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'credprme_main_section',
			__( 'Airtable connection', 'credits-program-mentors' ),
			array( $this, 'render_section_intro' ),
			'credits-program-mentors'
		);

		$fields = array(
			'api_token' => __( 'Personal Access Token', 'credits-program-mentors' ),
			'base_id'   => __( 'Base ID', 'credits-program-mentors' ),
			'table_id'  => __( 'Table ID or name', 'credits-program-mentors' ),
			'view_id'   => __( 'View ID or name (optional)', 'credits-program-mentors' ),
			'cache_ttl' => __( 'Cache lifetime (seconds)', 'credits-program-mentors' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'credprme_field_' . $key,
				$label,
				array( $this, 'render_field' ),
				'credits-program-mentors',
				'credprme_main_section',
				array(
					'key'       => $key,
					'label_for' => 'credprme_field_' . $key,
				)
			);
		}

		add_settings_section(
			'credprme_display_section',
			__( 'Display', 'credits-program-mentors' ),
			'__return_false',
			'credits-program-mentors'
		);

		add_settings_field(
			'credprme_field_show_map',
			__( 'Country map', 'credits-program-mentors' ),
			array( $this, 'render_field' ),
			'credits-program-mentors',
			'credprme_display_section',
			array(
				'key'       => 'show_map',
				'label_for' => 'credprme_field_show_map',
			)
		);
	}

	/**
	 * Section description.
	 */
	public function render_section_intro() {
		echo '<p>' . wp_kses_post(
			__( 'Create a read-only <strong>Personal Access Token</strong> at <a href="https://airtable.com/create/tokens" target="_blank" rel="noopener">airtable.com/create/tokens</a> with the <code>data.records:read</code> scope and access to this base, then paste it below. The Base and Table IDs are already filled in for the Sponsored mentors base.', 'credits-program-mentors' )
		) . '</p>';
	}

	/**
	 * Render an individual settings field.
	 *
	 * @param array $args Field args (expects 'key').
	 */
	public function render_field( $args ) {
		$settings = self::get();
		$key      = $args['key'];
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
		$name     = self::OPTION_KEY . '[' . $key . ']';
		$id       = 'credprme_field_' . $key;

		switch ( $key ) {
			case 'api_token':
				// Never rendered back into the page: a type="password" field still
				// carries its value in the markup, where view-source, the browser
				// cache and anything reading the DOM can all reach it. The field
				// posts blank to mean "keep the stored token".
				$mask = '' !== $value ? str_repeat( '•', 8 ) . substr( $value, -4 ) : '';
				printf(
					'<input type="password" autocomplete="off" spellcheck="false" id="%1$s" name="%2$s" value="" class="regular-text" placeholder="%3$s" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( '' !== $mask ? $mask : 'pat…' )
				);
				echo '<p class="description">'
					. esc_html__( 'Stored in your database and used server-side only. Leave blank to keep the current token.', 'credits-program-mentors' );
				if ( '' !== $mask ) {
					echo '<br /><em>' . esc_html(
						sprintf(
							/* translators: %s: masked token. */
							__( 'Current: %s', 'credits-program-mentors' ),
							$mask
						)
					) . '</em>';
				}
				echo '<br />' . esc_html__( 'Use a token scoped to read-only access.', 'credits-program-mentors' )
					. '</p>';
				break;

			case 'cache_ttl':
				printf(
					'<input type="number" min="0" step="60" id="%1$s" name="%2$s" value="%3$s" class="small-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				echo '<p class="description">' . esc_html__( 'How long to cache Airtable results before refetching. Set to 0 to disable caching.', 'credits-program-mentors' ) . '</p>';
				break;

			case 'show_map':
				printf(
					'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( ! empty( $value ), true, false ),
					esc_html__( 'Show a world map of mentor countries above the list', 'credits-program-mentors' )
				);
				echo '<p class="description">' . esc_html__( 'Highlights the countries mentors come from. Can be overridden per placement with the shortcode attribute map="yes|no".', 'credits-program-mentors' ) . '</p>';
				break;

			default:
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" spellcheck="false" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;
		}
	}

	/**
	 * Sanitize submitted settings and flush the record cache.
	 *
	 * @param array $input Raw submitted values.
	 * @return array
	 */
	public function sanitize( $input ) {
		$defaults = self::defaults();
		$clean    = array();

		// The token field posts blank unless a new one was typed, so an empty
		// submission keeps what is already stored rather than wiping it. The
		// bullet check stops a pasted-back mask from being saved as the token.
		$existing           = self::get();
		$submitted_token    = isset( $input['api_token'] ) ? trim( sanitize_text_field( wp_unslash( $input['api_token'] ) ) ) : '';
		$clean['api_token'] = ( '' !== $submitted_token && false === strpos( $submitted_token, '•' ) )
			? $submitted_token
			: ( isset( $existing['api_token'] ) ? $existing['api_token'] : '' );
		$clean['base_id']   = isset( $input['base_id'] ) ? trim( sanitize_text_field( wp_unslash( $input['base_id'] ) ) ) : $defaults['base_id'];
		$clean['table_id']  = isset( $input['table_id'] ) ? trim( sanitize_text_field( wp_unslash( $input['table_id'] ) ) ) : $defaults['table_id'];
		$clean['view_id']   = isset( $input['view_id'] ) ? trim( sanitize_text_field( wp_unslash( $input['view_id'] ) ) ) : '';
		$clean['cache_ttl'] = isset( $input['cache_ttl'] ) ? max( 0, (int) $input['cache_ttl'] ) : $defaults['cache_ttl'];
		$clean['show_map']  = ! empty( $input['show_map'] );

		// Any settings change may invalidate cached results.
		CREDPRME_Airtable_Client::flush_cache();

		return $clean;
	}

	/**
	 * Render the full settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$refreshed = isset( $_GET['credprme_refreshed'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Credits Program Mentors', 'credits-program-mentors' ); ?></h1>

			<?php if ( $refreshed ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Cached mentor data cleared. The latest records will be fetched from Airtable on the next page view.', 'credits-program-mentors' ); ?></p></div>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'credprme_settings_group' );
				do_settings_sections( 'credits-program-mentors' );
				submit_button();
				?>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'Mentor data', 'credits-program-mentors' ); ?></h2>
			<p><?php echo esc_html__( 'Mentor records are fetched from Airtable and cached for speed. Profile photos load directly from WordPress.org in the visitor’s browser, so they don’t need refreshing. To pull the latest records from Airtable now, clear the cache:', 'credits-program-mentors' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="credprme_refresh_data" />
				<?php wp_nonce_field( 'credprme_refresh_data' ); ?>
				<?php submit_button( __( 'Refresh data', 'credits-program-mentors' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'How to display mentors', 'credits-program-mentors' ); ?></h2>
			<p><?php echo esc_html__( 'Add this shortcode to any post or page (in the Editor, use a “Shortcode” block):', 'credits-program-mentors' ); ?></p>
			<p><code>[credits_program_mentors]</code></p>
			<p><?php echo esc_html__( 'Optional attributes:', 'credits-program-mentors' ); ?></p>
			<ul style="list-style:disc;margin-left:20px;">
				<li><code>layout="grid|table"</code> — <?php echo esc_html__( 'card grid (default) or a plain table.', 'credits-program-mentors' ); ?></li>
				<li><code>limit="10"</code> — <?php echo esc_html__( 'maximum number of mentors to show.', 'credits-program-mentors' ); ?></li>
				<li><code>columns="3"</code> — <?php echo esc_html__( 'number of columns in grid layout (1–6).', 'credits-program-mentors' ); ?></li>
				<li><code>country="Spain"</code> — <?php echo esc_html__( 'only mentors from a given country.', 'credits-program-mentors' ); ?></li>
				<li><code>language="English"</code> — <?php echo esc_html__( 'only mentors who speak a given language.', 'credits-program-mentors' ); ?></li>
				<li><code>fields="Full Name,Country"</code> — <?php echo esc_html__( 'restrict which fields are shown, in order.', 'credits-program-mentors' ); ?></li>
				<li><code>photos="yes|no"</code> — <?php echo esc_html__( 'show each mentor’s WordPress.org profile photo (default yes).', 'credits-program-mentors' ); ?></li>
				<li><code>photo_size="160"</code> — <?php echo esc_html__( 'profile photo size in pixels.', 'credits-program-mentors' ); ?></li>
				<li><code>filters="yes|no"</code> — <?php echo esc_html__( 'show the front-end filter bar (Sponsorship, Language, Country). Default yes.', 'credits-program-mentors' ); ?></li>
				<li><code>map="yes|no"</code> — <?php echo esc_html__( 'show the country map. Defaults to the Country map setting above.', 'credits-program-mentors' ); ?></li>
			</ul>
			<p><em><?php echo esc_html__( 'Example:', 'credits-program-mentors' ); ?></em> <code>[credits_program_mentors layout="grid" columns="3" language="Spanish"]</code></p>
		</div>
		<?php
	}
}
