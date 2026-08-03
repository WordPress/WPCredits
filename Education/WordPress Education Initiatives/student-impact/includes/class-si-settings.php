<?php
/**
 * Admin settings screen: Airtable credentials, manual sync, and status.
 *
 * @package Student_Impact
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SI_Settings {

	/** @var SI_Settings|null */
	private static $instance = null;

	const PAGE  = 'student-impact';
	const GROUP = 'si_settings_group';
	const NONCE = 'si_sync_now';

	/**
	 * Singleton.
	 *
	 * @return SI_Settings
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_post_si_sync_now', array( $this, 'handle_sync_now' ) );
	}

	/**
	 * Add a top-level dashboard menu item.
	 */
	public function add_page() {
		add_menu_page(
			__( 'Student Impact', 'student-impact' ),
			__( 'Student Impact', 'student-impact' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' ),
			'dashicons-awards',
			30
		);
	}

	/**
	 * Register the settings + sanitizer.
	 */
	public function register() {
		register_setting(
			self::GROUP,
			SI_OPT_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	/**
	 * Sanitize + preserve the stored PAT when the field is left blank/masked.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$current = si_get_settings();
		$out     = si_default_settings();

		$out['base_id']        = isset( $input['base_id'] ) ? sanitize_text_field( $input['base_id'] ) : $out['base_id'];
		$out['students_table'] = isset( $input['students_table'] ) ? sanitize_text_field( $input['students_table'] ) : $out['students_table'];
		$out['reports_table']  = isset( $input['reports_table'] ) ? sanitize_text_field( $input['reports_table'] ) : $out['reports_table'];
		$out['teams_table']    = isset( $input['teams_table'] ) ? sanitize_text_field( $input['teams_table'] ) : $out['teams_table'];
		$out['status_value']   = isset( $input['status_value'] ) ? sanitize_text_field( $input['status_value'] ) : $out['status_value'];
		$out['count']          = isset( $input['count'] ) ? max( 1, min( 50, (int) $input['count'] ) ) : $out['count'];

		// Only overwrite the PAT if a new, non-masked value was entered.
		$pat = isset( $input['airtable_pat'] ) ? trim( $input['airtable_pat'] ) : '';
		if ( '' !== $pat && false === strpos( $pat, '•' ) ) {
			$out['airtable_pat'] = $pat;
		} else {
			$out['airtable_pat'] = $current['airtable_pat'];
		}

		return $out;
	}

	/**
	 * Handle the "Sync now" POST.
	 */
	public function handle_sync_now() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'student-impact' ) );
		}
		check_admin_referer( self::NONCE );

		$settings = si_get_settings();
		if ( empty( $settings['airtable_pat'] ) ) {
			$this->redirect_back( 'notoken' );
		}

		SI_Sync::start();
		$this->redirect_back( 'started' );
	}

	/**
	 * Redirect back to the settings page with a status flag.
	 *
	 * @param string $flag Status flag.
	 */
	private function redirect_back( $flag ) {
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'si' => $flag ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings  = si_get_settings();
		$data      = get_option( SI_OPT_DATA, array() );
		$last_sync = (int) get_option( SI_OPT_LASTSYNC, 0 );
		$last_err  = get_option( SI_OPT_LASTERR, '' );
		$running   = SI_Sync::is_running();
		$flag      = isset( $_GET['si'] ) ? sanitize_key( wp_unslash( $_GET['si'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$pat_mask = $settings['airtable_pat'] ? str_repeat( '•', 8 ) . substr( $settings['airtable_pat'], -4 ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Student Impact', 'student-impact' ); ?></h1>

			<?php if ( 'started' === $flag ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Sync started in the background. This page auto-refreshes while it runs.', 'student-impact' ); ?></p></div>
			<?php elseif ( 'notoken' === $flag ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Add your Airtable Personal Access Token first, then run a sync.', 'student-impact' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $last_err ) : ?>
				<div class="notice notice-error"><p><strong><?php esc_html_e( 'Last sync error:', 'student-impact' ); ?></strong> <?php echo esc_html( $last_err ); ?></p></div>
			<?php endif; ?>

			<div class="si-admin-status card" style="max-width:none;padding:12px 16px;">
				<p style="margin:.2em 0;">
					<strong><?php esc_html_e( 'Status:', 'student-impact' ); ?></strong>
					<?php
					if ( $running ) {
						echo '<span style="color:#b26200;">● ' . esc_html( SI_Sync::progress_label() ) . '</span>';
					} else {
						echo '<span style="color:#1a7f37;">● ' . esc_html__( 'Idle', 'student-impact' ) . '</span>';
					}
					?>
				</p>
				<p style="margin:.2em 0;">
					<strong><?php esc_html_e( 'Showcase entries:', 'student-impact' ); ?></strong>
					<?php echo esc_html( is_array( $data ) ? count( $data ) : 0 ); ?>
				</p>
				<p style="margin:.2em 0;">
					<strong><?php esc_html_e( 'Last synced:', 'student-impact' ); ?></strong>
					<?php
					if ( $last_sync > 0 ) {
						echo esc_html( sprintf( /* translators: %s: human time diff. */ __( '%s ago', 'student-impact' ), human_time_diff( $last_sync ) ) );
						echo ' (' . esc_html( wp_date( 'Y-m-d H:i', $last_sync ) ) . ')';
					} else {
						esc_html_e( 'Never (showing bundled snapshot)', 'student-impact' );
					}
					?>
				</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
					<input type="hidden" name="action" value="si_sync_now" />
					<?php wp_nonce_field( self::NONCE ); ?>
					<?php submit_button( $running ? __( 'Sync running…', 'student-impact' ) : __( 'Sync now', 'student-impact' ), 'primary', 'submit', false, $running ? array( 'disabled' => 'disabled' ) : array() ); ?>
				</form>
			</div>

			<form method="post" action="options.php" style="margin-top:20px;">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="si_pat"><?php esc_html_e( 'Airtable Personal Access Token', 'student-impact' ); ?></label></th>
						<td>
							<input type="password" id="si_pat" name="<?php echo esc_attr( SI_OPT_SETTINGS ); ?>[airtable_pat]" value="" class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr( $pat_mask ? $pat_mask : 'pat…' ); ?>" />
							<p class="description">
								<?php esc_html_e( 'Stored in the database and used server-side only. Leave blank to keep the current token.', 'student-impact' ); ?>
								<?php if ( $pat_mask ) : ?><br /><em><?php echo esc_html( sprintf( /* translators: %s: masked token. */ __( 'Current: %s', 'student-impact' ), $pat_mask ) ); ?></em><?php endif; ?>
								<br /><strong><?php esc_html_e( 'Security:', 'student-impact' ); ?></strong> <?php esc_html_e( 'use a token scoped to read-only access to just this base.', 'student-impact' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="si_base"><?php esc_html_e( 'Base ID', 'student-impact' ); ?></label></th>
						<td><input type="text" id="si_base" name="<?php echo esc_attr( SI_OPT_SETTINGS ); ?>[base_id]" value="<?php echo esc_attr( $settings['base_id'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="si_students"><?php esc_html_e( 'Students table (id or name)', 'student-impact' ); ?></label></th>
						<td><input type="text" id="si_students" name="<?php echo esc_attr( SI_OPT_SETTINGS ); ?>[students_table]" value="<?php echo esc_attr( $settings['students_table'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="si_reports"><?php esc_html_e( 'Students Reports table (id or name)', 'student-impact' ); ?></label></th>
						<td><input type="text" id="si_reports" name="<?php echo esc_attr( SI_OPT_SETTINGS ); ?>[reports_table]" value="<?php echo esc_attr( $settings['reports_table'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="si_teams"><?php esc_html_e( 'Contribution areas table (id or name)', 'student-impact' ); ?></label></th>
						<td><input type="text" id="si_teams" name="<?php echo esc_attr( SI_OPT_SETTINGS ); ?>[teams_table]" value="<?php echo esc_attr( $settings['teams_table'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="si_status"><?php esc_html_e( 'Student status to include', 'student-impact' ); ?></label></th>
						<td><input type="text" id="si_status" name="<?php echo esc_attr( SI_OPT_SETTINGS ); ?>[status_value]" value="<?php echo esc_attr( $settings['status_value'] ); ?>" class="regular-text" /> <p class="description"><?php esc_html_e( 'e.g. Graduate', 'student-impact' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="si_count"><?php esc_html_e( 'Number of students to showcase', 'student-impact' ); ?></label></th>
						<td><input type="number" id="si_count" name="<?php echo esc_attr( SI_OPT_SETTINGS ); ?>[count]" value="<?php echo esc_attr( $settings['count'] ); ?>" min="1" max="50" class="small-text" /></td>
					</tr>
				</table>
				<?php submit_button( __( 'Save settings', 'student-impact' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'How to display it', 'student-impact' ); ?></h2>
			<p><strong><?php esc_html_e( 'Student Stories', 'student-impact' ); ?></strong> — <?php esc_html_e( 'the ranked showcase. Insert the "Student Stories" block, or use:', 'student-impact' ); ?></p>
			<p><code>[student_impact count="50" columns="3" filters="yes" title="Student Stories"]</code></p>
			<p class="description"><?php esc_html_e( 'Visitors can filter the showcase by contribution area (Polyglots, Support, …). Set filters="no" to hide the filter bar.', 'student-impact' ); ?></p>
			<p style="margin-top:1em;"><strong><?php esc_html_e( 'Graduate Stats', 'student-impact' ); ?></strong> — <?php esc_html_e( 'class-wide totals across all graduates. Insert the "Graduate Stats" block, or use:', 'student-impact' ); ?></p>
			<p><code>[student_impact_stats title="Class Impact" layout="grid"]</code></p>
			<p class="description"><?php esc_html_e( 'Use layout="inline" for a compact horizontal strip suited to headers or footers.', 'student-impact' ); ?></p>

			<h2><?php esc_html_e( 'How students are selected and ranked', 'student-impact' ); ?></h2>
			<details class="si-help" style="max-width:820px;">
				<summary style="cursor:pointer;font-weight:600;padding:.4em 0;"><?php esc_html_e( 'Show the rules', 'student-impact' ); ?></summary>

				<h3 style="margin-bottom:.3em;"><?php esc_html_e( 'Who qualifies', 'student-impact' ); ?></h3>
				<p><?php esc_html_e( 'A student is shown only if all of these are true:', 'student-impact' ); ?></p>
				<ol style="margin:0 0 .5em 1.5em;">
					<li><?php esc_html_e( 'They are in the Airtable Students table with Status = “Graduate”.', 'student-impact' ); ?></li>
					<li><?php esc_html_e( 'They match (by email) a row in the Students Reports table.', 'student-impact' ); ?></li>
					<li><?php esc_html_e( 'That report has a usable WordPress.org profile (profiles.wordpress.org/user).', 'student-impact' ); ?></li>
				</ol>
				<p class="description"><?php esc_html_e( 'Graduates without a WordPress.org profile are excluded from the showcase, but are still counted in the Graduate Stats totals (headcount and hours).', 'student-impact' ); ?></p>

				<h3 style="margin-bottom:.3em;"><?php esc_html_e( 'Which data comes from where', 'student-impact' ); ?></h3>
				<ul style="margin:0 0 .5em 1.5em;list-style:disc;">
					<li><?php esc_html_e( 'Name, email, Graduate status — Airtable Students table.', 'student-impact' ); ?></li>
					<li><?php esc_html_e( 'WordPress profile URL, Hours, contribution Team — Airtable Students Reports (Hours = the highest value across the student’s reports).', 'student-impact' ); ?></li>
					<li><?php esc_html_e( 'Recent impact score, Contributions, high-impact count, “active” flag — scraped live from the student’s profiles.wordpress.org “Recent impact” panel (last 12 months). Impact is WordPress’s own weighted score: high-impact work (commits, releases, approved translations, props) counts 3× routine activity.', 'student-impact' ); ?></li>
					<li><?php esc_html_e( 'Avatar — Gravatar on the WordPress.org profile.', 'student-impact' ); ?></li>
				</ul>

				<h3 style="margin-bottom:.3em;"><?php esc_html_e( 'How they are ranked', 'student-impact' ); ?></h3>
				<p><?php esc_html_e( 'A composite score, each signal scaled against the cohort’s top value:', 'student-impact' ); ?></p>
				<ul style="margin:0 0 .5em 1.5em;list-style:disc;">
					<li><?php esc_html_e( 'Recent impact — 45%', 'student-impact' ); ?></li>
					<li><?php esc_html_e( 'Contributions — 45%', 'student-impact' ); ?></li>
					<li><?php esc_html_e( 'Hours — 10% (minor tiebreaker only)', 'student-impact' ); ?></li>
				</ul>
				<p class="description"><?php esc_html_e( 'Ties break by impact, then contributions, so hours never decides the top spots. The highest-scoring top N (set above, default 50) are shown, ranked #1 downward.', 'student-impact' ); ?></p>

				<h3 style="margin-bottom:.3em;"><?php esc_html_e( 'Card labels', 'student-impact' ); ?></h3>
				<ul style="margin:0 0 .5em 1.5em;list-style:disc;">
					<li><?php esc_html_e( 'Rank badge (#1–#3 use the highlighted “medal” style).', 'student-impact' ); ?></li>
					<li><?php esc_html_e( '“Active now” — made at least 1 contribution in the last 90 days.', 'student-impact' ); ?></li>
					<li><?php esc_html_e( '“N high-impact” — high-impact contributions in the last 12 months.', 'student-impact' ); ?></li>
					<li><?php esc_html_e( 'Team badge — the student’s main contribution area (also the filter groups).', 'student-impact' ); ?></li>
				</ul>
				<p class="description"><?php esc_html_e( 'Data syncs live from Airtable + WordPress.org (daily, plus the “Sync now” button above). A bundled snapshot is shown until the first sync runs.', 'student-impact' ); ?></p>
			</details>
		</div>

		<?php if ( $running ) : ?>
			<script>setTimeout(function(){ location.href = <?php echo wp_json_encode( admin_url( 'admin.php?page=' . self::PAGE ) ); ?>; }, 5000);</script>
		<?php endif; ?>
		<?php
	}
}
