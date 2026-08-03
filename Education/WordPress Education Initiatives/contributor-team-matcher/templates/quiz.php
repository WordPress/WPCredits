<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="ctm-quiz" class="ctm-quiz" role="main" aria-label="<?php esc_attr_e( 'Find Your WordPress Contribution Team', 'find-your-team' ); ?>">

	<div class="ctm-intro" id="ctm-intro">
		<div class="ctm-intro__badge"><?php esc_html_e( 'Contributor Quiz', 'find-your-team' ); ?></div>
		<h2 class="ctm-intro__heading"><?php esc_html_e( 'Find Your WordPress Team', 'find-your-team' ); ?></h2>
		<p class="ctm-intro__description">
			<?php esc_html_e( 'Answer a few quick questions and we\'ll match you with the contribution team that fits you best. There are no wrong answers — just your interests!', 'find-your-team' ); ?>
		</p>
		<button class="ctm-btn ctm-btn--primary" id="ctm-start-btn">
			<?php esc_html_e( 'Get Started', 'find-your-team' ); ?>
		</button>
	</div>

	<div class="ctm-quiz__body" id="ctm-quiz-body" hidden>
		<div class="ctm-progress" aria-hidden="true">
			<div class="ctm-progress__bar" id="ctm-progress-bar"></div>
		</div>
		<p class="ctm-progress__label" id="ctm-progress-label"></p>

		<div class="ctm-question" id="ctm-question-container" aria-live="polite">
			<!-- Rendered by JS -->
		</div>

		<div class="ctm-quiz__nav">
			<button class="ctm-btn ctm-btn--ghost" id="ctm-back-btn" hidden>
				<?php esc_html_e( 'Back', 'find-your-team' ); ?>
			</button>
			<button class="ctm-btn ctm-btn--primary" id="ctm-next-btn" disabled>
				<?php esc_html_e( 'Next', 'find-your-team' ); ?>
			</button>
		</div>
	</div>

	<div class="ctm-results" id="ctm-results" hidden aria-live="polite">
		<!-- Rendered by JS -->
	</div>

</div>
