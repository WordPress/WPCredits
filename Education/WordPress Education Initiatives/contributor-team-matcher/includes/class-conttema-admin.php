<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CONTTEMA_Admin {

	public static function init() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_conttema_save_questions', array( __CLASS__, 'save_questions' ) );
		add_action( 'admin_post_conttema_save_teams', array( __CLASS__, 'save_teams' ) );
		add_action( 'admin_post_conttema_reset_questions', array( __CLASS__, 'reset_questions' ) );
		add_action( 'admin_post_conttema_reset_teams', array( __CLASS__, 'reset_teams' ) );
	}

	public static function add_menu() {
		add_menu_page(
			__( 'Contributor Team Matcher', 'find-your-team' ),
			__( 'Contributor Team Matcher', 'find-your-team' ),
			'manage_options',
			'contributor-team-matcher',
			array( __CLASS__, 'render_page' ),
			'dashicons-groups',
			30
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( 'toplevel_page_contributor-team-matcher' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'ctm-admin',
			CONTTEMA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			CONTTEMA_VERSION
		);
		wp_enqueue_script(
			'ctm-admin',
			CONTTEMA_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			CONTTEMA_VERSION,
			true
		);
	}

	// -------------------------------------------------------------------------
	// Page render
	// -------------------------------------------------------------------------

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'questions'; // phpcs:ignore WordPress.Security.NonceVerification
		$questions  = CONTTEMA_Quiz_Data::get_questions();
		$teams      = CONTTEMA_Quiz_Data::get_teams();
		include CONTTEMA_PLUGIN_DIR . 'templates/admin-page.php';
	}

	// -------------------------------------------------------------------------
	// Helpers used by the template
	// -------------------------------------------------------------------------

	public static function render_question_item( $q, $q_idx ) {
		$q_id   = isset( $q['id'] ) ? esc_attr( $q['id'] ) : '';
		$q_text = isset( $q['text'] ) ? esc_attr( $q['text'] ) : '';
		$q_type = in_array( $q['type'] ?? 'checkbox', array( 'radio', 'checkbox' ), true )
			? $q['type']
			: 'checkbox';
		$prefix = 'conttema_questions[' . $q_idx . ']';
		$label  = $q_text ? $q_text : __( 'New Question', 'find-your-team' );
		?>
		<div class="ctm-item" data-type="question">
			<div class="ctm-item__header">
				<button type="button" class="ctm-item__toggle" aria-expanded="false">
					<span class="ctm-item__icon dashicons dashicons-arrow-right-alt2"></span>
					<span class="ctm-item__label"><?php echo esc_html( wp_trim_words( $label, 10 ) ); ?></span>
					<span class="ctm-type-badge ctm-type-badge--<?php echo esc_attr( $q_type ); ?>">
						<?php echo 'radio' === $q_type ? esc_html__( 'Single', 'find-your-team' ) : esc_html__( 'Multi', 'find-your-team' ); ?>
					</span>
				</button>
				<button type="button" class="ctm-item__remove button-link-delete" aria-label="<?php esc_attr_e( 'Remove question', 'find-your-team' ); ?>">
					<?php esc_html_e( 'Remove', 'find-your-team' ); ?>
				</button>
			</div>
			<div class="ctm-item__body" hidden>
				<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[id]" value="<?php echo esc_attr( $q_id ); ?>">
				<table class="form-table ctm-form-table">
					<tr>
						<th><label><?php esc_html_e( 'Question Text', 'find-your-team' ); ?></label></th>
						<td>
							<input
								type="text"
								name="<?php echo esc_attr( $prefix ); ?>[text]"
								value="<?php echo esc_attr( $q_text ); ?>"
								class="large-text ctm-q-text-input"
								required
							>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Answer Type', 'find-your-team' ); ?></th>
						<td>
							<div class="ctm-type-toggle" role="group" aria-label="<?php esc_attr_e( 'Answer type', 'find-your-team' ); ?>">
								<input
									type="radio"
									name="<?php echo esc_attr( $prefix ); ?>[type]"
									value="radio"
									id="ctm-q-type-r-<?php echo esc_attr( $q_idx ); ?>"
									class="ctm-type-radio"
									<?php checked( $q_type, 'radio' ); ?>
								>
								<label for="ctm-q-type-r-<?php echo esc_attr( $q_idx ); ?>" class="ctm-type-btn">
									<span class="dashicons dashicons-marker" aria-hidden="true"></span>
									<?php esc_html_e( 'Single answer (radio)', 'find-your-team' ); ?>
								</label>
								<input
									type="radio"
									name="<?php echo esc_attr( $prefix ); ?>[type]"
									value="checkbox"
									id="ctm-q-type-c-<?php echo esc_attr( $q_idx ); ?>"
									class="ctm-type-radio"
									<?php checked( $q_type, 'checkbox' ); ?>
								>
								<label for="ctm-q-type-c-<?php echo esc_attr( $q_idx ); ?>" class="ctm-type-btn">
									<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
									<?php esc_html_e( 'Multiple answers (checkbox, up to 3)', 'find-your-team' ); ?>
								</label>
							</div>
							<p class="description"><?php esc_html_e( 'Single: contributors pick exactly one answer. Multiple: they can pick up to 3.', 'find-your-team' ); ?></p>
						</td>
					</tr>
				</table>

				<div class="ctm-answers-header">
					<strong><?php esc_html_e( 'Answers', 'find-your-team' ); ?></strong>
					<span class="ctm-answers-hint"><?php esc_html_e( 'Tags are comma-separated keywords used to match contributors to teams.', 'find-your-team' ); ?></span>
				</div>

				<div class="ctm-answers-list" data-a-counter="<?php echo count( $q['answers'] ?? array() ); ?>">
					<?php
					foreach ( $q['answers'] as $a_idx => $a ) {
						self::render_answer_row( $a, $prefix, $a_idx );
					}
					?>
				</div>

				<button type="button" class="ctm-add-answer button button-secondary">
					+ <?php esc_html_e( 'Add Answer', 'find-your-team' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	public static function render_answer_row( $a, $q_prefix, $a_idx ) {
		$a_id   = isset( $a['id'] ) ? esc_attr( $a['id'] ) : '';
		$a_text = isset( $a['text'] ) ? esc_attr( $a['text'] ) : '';
		$a_tags = isset( $a['tags'] ) ? esc_attr( implode( ', ', $a['tags'] ) ) : '';
		$prefix = $q_prefix . '[answers][' . $a_idx . ']';
		?>
		<div class="ctm-answer-row">
			<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[id]" value="<?php echo esc_attr( $a_id ); ?>">
			<div class="ctm-answer-row__fields">
				<input
					type="text"
					name="<?php echo esc_attr( $prefix ); ?>[text]"
					value="<?php echo esc_attr( $a_text ); ?>"
					class="ctm-input-answer-text"
					placeholder="<?php esc_attr_e( 'Answer text…', 'find-your-team' ); ?>"
					required
				>
				<input
					type="text"
					name="<?php echo esc_attr( $prefix ); ?>[tags]"
					value="<?php echo esc_attr( $a_tags ); ?>"
					class="ctm-input-answer-tags"
					placeholder="<?php esc_attr_e( 'tag1, tag2, tag3', 'find-your-team' ); ?>"
				>
				<button type="button" class="ctm-remove-answer button-link-delete" aria-label="<?php esc_attr_e( 'Remove answer', 'find-your-team' ); ?>">×</button>
			</div>
		</div>
		<?php
	}

	public static function render_team_item( $t, $t_idx ) {
		$t_id   = isset( $t['id'] ) ? esc_attr( $t['id'] ) : '';
		$t_name = isset( $t['name'] ) ? esc_attr( $t['name'] ) : '';
		$prefix = 'conttema_teams[' . $t_idx . ']';
		$label  = $t_name ? $t_name : __( 'New Team', 'find-your-team' );
		?>
		<div class="ctm-item" data-type="team">
			<div class="ctm-item__header">
				<button type="button" class="ctm-item__toggle" aria-expanded="false">
					<span class="ctm-item__icon dashicons dashicons-arrow-right-alt2"></span>
					<span class="ctm-item__icon-emoji"><?php echo esc_html( $t['icon'] ?? '' ); ?></span>
					<span class="ctm-item__label"><?php echo esc_html( $label ); ?></span>
				</button>
				<button type="button" class="ctm-item__remove button-link-delete" aria-label="<?php esc_attr_e( 'Remove team', 'find-your-team' ); ?>">
					<?php esc_html_e( 'Remove', 'find-your-team' ); ?>
				</button>
			</div>
			<div class="ctm-item__body" hidden>
				<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[id]" value="<?php echo esc_attr( $t_id ); ?>">
				<table class="form-table ctm-form-table">
					<tr>
						<th><label><?php esc_html_e( 'Team Name', 'find-your-team' ); ?></label></th>
						<td>
							<input
								type="text"
								name="<?php echo esc_attr( $prefix ); ?>[name]"
								value="<?php echo esc_attr( $t_name ); ?>"
								class="regular-text ctm-t-name-input"
								required
							>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Icon (emoji)', 'find-your-team' ); ?></label></th>
						<td>
							<input
								type="text"
								name="<?php echo esc_attr( $prefix ); ?>[icon]"
								value="<?php echo esc_attr( $t['icon'] ?? '' ); ?>"
								class="ctm-icon-input"
								placeholder="⚙️"
							>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Team URL', 'find-your-team' ); ?></label></th>
						<td>
							<input
								type="url"
								name="<?php echo esc_attr( $prefix ); ?>[url]"
								value="<?php echo esc_attr( $t['url'] ?? '' ); ?>"
								class="large-text"
							>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Description', 'find-your-team' ); ?></label></th>
						<td>
							<textarea
								name="<?php echo esc_attr( $prefix ); ?>[description]"
								class="large-text"
								rows="3"
							><?php echo esc_textarea( $t['description'] ?? '' ); ?></textarea>
						</td>
					</tr>
				</table>

				<div class="ctm-tags-header">
					<strong><?php esc_html_e( 'Tag Weights', 'find-your-team' ); ?></strong>
					<span class="ctm-answers-hint"><?php esc_html_e( 'Higher weight (1–10) = stronger match for that tag.', 'find-your-team' ); ?></span>
				</div>

				<div class="ctm-tags-list" data-tag-counter="<?php echo count( $t['tags'] ?? array() ); ?>">
					<?php
					foreach ( ( $t['tags'] ?? array() ) as $tag => $weight ) {
						self::render_tag_row( $tag, $weight, $prefix, null );
					}
					?>
				</div>

				<button type="button" class="ctm-add-tag button button-secondary">
					+ <?php esc_html_e( 'Add Tag Weight', 'find-your-team' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	public static function render_tag_row( $tag, $weight, $t_prefix, $idx ) {
		static $static_idx = array();
		// $idx is null when called from PHP loop — derive from static counter per prefix.
		if ( null === $idx ) {
			if ( ! isset( $static_idx[ $t_prefix ] ) ) {
				$static_idx[ $t_prefix ] = 0;
			}
			$idx = $static_idx[ $t_prefix ]++;
		}
		$prefix = $t_prefix . '[tags][' . $idx . ']';
		?>
		<div class="ctm-tag-row">
			<input
				type="text"
				name="<?php echo esc_attr( $prefix ); ?>[tag]"
				value="<?php echo esc_attr( $tag ); ?>"
				class="ctm-input-tag-name"
				placeholder="<?php esc_attr_e( 'tagname', 'find-your-team' ); ?>"
			>
			<input
				type="number"
				name="<?php echo esc_attr( $prefix ); ?>[weight]"
				value="<?php echo esc_attr( $weight ); ?>"
				class="ctm-input-tag-weight"
				min="1"
				max="10"
			>
			<button type="button" class="ctm-remove-tag button-link-delete" aria-label="<?php esc_attr_e( 'Remove tag', 'find-your-team' ); ?>">×</button>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save handlers
	// -------------------------------------------------------------------------

	public static function save_questions() {
		check_admin_referer( 'conttema_save_questions', 'conttema_questions_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'find-your-team' ) );
		}

		$raw       = isset( $_POST['conttema_questions'] ) ? $_POST['conttema_questions'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$questions = array();

		foreach ( array_values( (array) $raw ) as $i => $q ) {
			$text = sanitize_text_field( $q['text'] ?? '' );
			if ( empty( $text ) ) {
				continue;
			}

			$answers = array();
			foreach ( array_values( (array) ( $q['answers'] ?? array() ) ) as $j => $a ) {
				$a_text = sanitize_text_field( $a['text'] ?? '' );
				if ( empty( $a_text ) ) {
					continue;
				}
				$raw_tags  = array_values(
					array_filter(
						array_map( 'sanitize_key', array_map( 'trim', explode( ',', $a['tags'] ?? '' ) ) )
					)
				);
				$answers[] = array(
					'id'   => sanitize_key( $a['id'] ?? '' ) ? sanitize_key( $a['id'] ) : 'a_' . $i . '_' . $j,
					'text' => $a_text,
					'tags' => $raw_tags,
				);
			}

			if ( empty( $answers ) ) {
				continue;
			}

			$raw_type = $q['type'] ?? 'checkbox';
			$type     = in_array( $raw_type, array( 'radio', 'checkbox' ), true ) ? $raw_type : 'checkbox';

			$questions[] = array(
				'id'      => sanitize_key( $q['id'] ?? '' ) ? sanitize_key( $q['id'] ) : 'q_' . $i,
				'type'    => $type,
				'text'    => $text,
				'answers' => $answers,
			);
		}

		update_option( 'conttema_questions', $questions );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'contributor-team-matcher',
					'tab'     => 'questions',
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function save_teams() {
		check_admin_referer( 'conttema_save_teams', 'conttema_teams_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'find-your-team' ) );
		}

		$raw   = isset( $_POST['conttema_teams'] ) ? $_POST['conttema_teams'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$teams = array();

		foreach ( array_values( (array) $raw ) as $i => $t ) {
			$name = sanitize_text_field( $t['name'] ?? '' );
			if ( empty( $name ) ) {
				continue;
			}

			$tags = array();
			foreach ( array_values( (array) ( $t['tags'] ?? array() ) ) as $tw ) {
				$tag    = sanitize_key( $tw['tag'] ?? '' );
				$weight = max( 1, min( 10, intval( $tw['weight'] ?? 3 ) ) );
				if ( $tag ) {
					$tags[ $tag ] = $weight;
				}
			}

			$teams[] = array(
				'id'          => sanitize_key( $t['id'] ?? '' ) ? sanitize_key( $t['id'] ) : 'team_' . $i,
				'name'        => $name,
				'url'         => esc_url_raw( $t['url'] ?? '' ),
				'description' => sanitize_text_field( $t['description'] ?? '' ),
				'icon'        => sanitize_text_field( $t['icon'] ?? '' ),
				'tags'        => $tags,
			);
		}

		update_option( 'conttema_teams', $teams );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'contributor-team-matcher',
					'tab'     => 'teams',
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function reset_questions() {
		check_admin_referer( 'conttema_reset_questions', 'conttema_reset_questions_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'find-your-team' ) );
		}
		delete_option( 'conttema_questions' );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => 'contributor-team-matcher',
					'tab'   => 'questions',
					'reset' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function reset_teams() {
		check_admin_referer( 'conttema_reset_teams', 'conttema_reset_teams_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'find-your-team' ) );
		}
		delete_option( 'conttema_teams' );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => 'contributor-team-matcher',
					'tab'   => 'teams',
					'reset' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
