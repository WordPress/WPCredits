<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Variables available: $active_tab, $questions, $teams. phpcs:ignore Squiz.PHP.CommentedOutCode.Found
?>
<div class="wrap ctm-admin-wrap">

	<h1 class="ctm-admin-title">
		<span class="dashicons dashicons-groups"></span>
		<?php esc_html_e( 'Contributor Team Matcher — Settings', 'find-your-team' ); ?>
	</h1>

	<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'find-your-team' ); ?></p></div>
	<?php endif; ?>

	<?php if ( isset( $_GET['reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings reset to defaults.', 'find-your-team' ); ?></p></div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper ctm-nav-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'find-your-team' ); ?>">
		<a
			href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page' => 'contributor-team-matcher',
						'tab'  => 'questions',
					),
					admin_url( 'admin.php' )
				) 
			);
			?>
			"
			class="nav-tab <?php echo 'questions' === $active_tab ? 'nav-tab-active' : ''; ?>"
		>
			<?php esc_html_e( 'Questions', 'find-your-team' ); ?>
			<span class="ctm-count"><?php echo count( $questions ); ?></span>
		</a>
		<a
			href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page' => 'contributor-team-matcher',
						'tab'  => 'teams',
					),
					admin_url( 'admin.php' )
				) 
			);
			?>
			"
			class="nav-tab <?php echo 'teams' === $active_tab ? 'nav-tab-active' : ''; ?>"
		>
			<?php esc_html_e( 'Teams', 'find-your-team' ); ?>
			<span class="ctm-count"><?php echo count( $teams ); ?></span>
		</a>
		<a
			href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page' => 'contributor-team-matcher',
						'tab'  => 'shortcode',
					),
					admin_url( 'admin.php' )
				) 
			);
			?>
			"
			class="nav-tab <?php echo 'shortcode' === $active_tab ? 'nav-tab-active' : ''; ?>"
		>
			<?php esc_html_e( 'Usage', 'find-your-team' ); ?>
		</a>
	</nav>

	<!-- ===================== QUESTIONS TAB ===================== -->

	<?php if ( 'questions' === $active_tab ) : ?>
	<div class="ctm-tab-content" id="tab-questions">

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ctm-questions-form">
			<?php wp_nonce_field( 'conttema_save_questions', 'conttema_questions_nonce' ); ?>
			<input type="hidden" name="action" value="conttema_save_questions">

			<div class="ctm-tab-header">
				<p class="ctm-tab-description">
					<?php esc_html_e( 'Each question is shown to contributors during the quiz. Every answer carries a set of tags that are used to match them with the right team.', 'find-your-team' ); ?>
				</p>
				<div class="ctm-tab-actions">
					<button type="button" class="button button-secondary" id="ctm-expand-all">
						<?php esc_html_e( 'Expand All', 'find-your-team' ); ?>
					</button>
					<button type="button" class="button button-secondary" id="ctm-collapse-all">
						<?php esc_html_e( 'Collapse All', 'find-your-team' ); ?>
					</button>
				</div>
			</div>

			<div
				class="ctm-items-list"
				id="ctm-questions-list"
				data-q-counter="<?php echo count( $questions ); ?>"
			>
				<?php foreach ( $questions as $q_idx => $q ) : ?>
					<?php CONTTEMA_Admin::render_question_item( $q, $q_idx ); ?>
				<?php endforeach; ?>
			</div>

			<div class="ctm-list-footer">
				<button type="button" class="button button-secondary button-large" id="ctm-add-question">
					+ <?php esc_html_e( 'Add Question', 'find-your-team' ); ?>
				</button>
			</div>

			<div class="ctm-submit-bar">
				<?php submit_button( __( 'Save Questions', 'find-your-team' ), 'primary large', 'submit', false ); ?>
				<span class="ctm-submit-sep">|</span>
				<a href="#" class="ctm-reset-link" data-form="questions">
					<?php esc_html_e( 'Reset to defaults', 'find-your-team' ); ?>
				</a>
			</div>
		</form>

		<!-- Hidden reset form -->
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ctm-reset-questions-form" style="display:none">
			<?php wp_nonce_field( 'conttema_reset_questions', 'conttema_reset_questions_nonce' ); ?>
			<input type="hidden" name="action" value="conttema_reset_questions">
		</form>

	</div>
	<?php endif; ?>

	<!-- ===================== TEAMS TAB ===================== -->

	<?php if ( 'teams' === $active_tab ) : ?>
	<div class="ctm-tab-content" id="tab-teams">

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ctm-teams-form">
			<?php wp_nonce_field( 'conttema_save_teams', 'conttema_teams_nonce' ); ?>
			<input type="hidden" name="action" value="conttema_save_teams">

			<div class="ctm-tab-header">
				<p class="ctm-tab-description">
					<?php esc_html_e( 'Teams are the destination cards shown at the end of the quiz. Each team has a set of tag weights — the higher the weight, the stronger the association with that tag.', 'find-your-team' ); ?>
				</p>
				<div class="ctm-tab-actions">
					<button type="button" class="button button-secondary" id="ctm-expand-all">
						<?php esc_html_e( 'Expand All', 'find-your-team' ); ?>
					</button>
					<button type="button" class="button button-secondary" id="ctm-collapse-all">
						<?php esc_html_e( 'Collapse All', 'find-your-team' ); ?>
					</button>
				</div>
			</div>

			<div
				class="ctm-items-list"
				id="ctm-teams-list"
				data-t-counter="<?php echo count( $teams ); ?>"
			>
				<?php foreach ( $teams as $t_idx => $t ) : ?>
					<?php CONTTEMA_Admin::render_team_item( $t, $t_idx ); ?>
				<?php endforeach; ?>
			</div>

			<div class="ctm-list-footer">
				<button type="button" class="button button-secondary button-large" id="ctm-add-team">
					+ <?php esc_html_e( 'Add Team', 'find-your-team' ); ?>
				</button>
			</div>

			<div class="ctm-submit-bar">
				<?php submit_button( __( 'Save Teams', 'find-your-team' ), 'primary large', 'submit', false ); ?>
				<span class="ctm-submit-sep">|</span>
				<a href="#" class="ctm-reset-link" data-form="teams">
					<?php esc_html_e( 'Reset to defaults', 'find-your-team' ); ?>
				</a>
			</div>
		</form>

		<!-- Hidden reset form -->
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ctm-reset-teams-form" style="display:none">
			<?php wp_nonce_field( 'conttema_reset_teams', 'conttema_reset_teams_nonce' ); ?>
			<input type="hidden" name="action" value="conttema_reset_teams">
		</form>

	</div>
	<?php endif; ?>

	<!-- ===================== USAGE TAB ===================== -->

	<?php if ( 'shortcode' === $active_tab ) : ?>
	<div class="ctm-tab-content" id="tab-shortcode">
		<div class="ctm-usage-card">
			<h2><?php esc_html_e( 'Embedding the Quiz', 'find-your-team' ); ?></h2>
			<p><?php esc_html_e( 'Paste this shortcode into any page or post:', 'find-your-team' ); ?></p>
			<div class="ctm-code-block">
				<code>[contributor_team_matcher]</code>
				<button type="button" class="button button-secondary ctm-copy-btn" data-clipboard-text="[contributor_team_matcher]">
					<?php esc_html_e( 'Copy', 'find-your-team' ); ?>
				</button>
			</div>

			<h3><?php esc_html_e( 'How the matching works', 'find-your-team' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Each quiz answer carries a list of tags (e.g. "code", "design", "translation").', 'find-your-team' ); ?></li>
				<li><?php esc_html_e( 'When a contributor finishes the quiz, all chosen tags are collected.', 'find-your-team' ); ?></li>
				<li><?php esc_html_e( 'Each team is scored by summing the weights of matching tags.', 'find-your-team' ); ?></li>
				<li><?php esc_html_e( 'The team with the highest score is shown as the top match, followed by four runners-up.', 'find-your-team' ); ?></li>
			</ol>

			<h3><?php esc_html_e( 'Tips', 'find-your-team' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'Keep tag names consistent across answers and teams — a typo will break the match.', 'find-your-team' ); ?></li>
				<li><?php esc_html_e( 'Tag weights run from 1 (weak association) to 10 (very strong). A difference of 1–2 points is usually enough to separate teams.', 'find-your-team' ); ?></li>
				<li><?php esc_html_e( 'Use "Reset to defaults" on any tab to restore the original built-in data.', 'find-your-team' ); ?></li>
			</ul>
		</div>
	</div>
	<?php endif; ?>

</div><!-- .wrap -->
