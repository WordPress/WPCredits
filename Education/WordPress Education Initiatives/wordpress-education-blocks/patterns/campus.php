<?php
/**
 * Title: Campus program
 * Slug: wp-education-blocks/campus
 * Categories: wpedu
 * Description: Two-column campus panel with a checklist and an editable photo.
 * Inserter: true
 */
?>
<!-- wp:group {"lock":{"move":true,"remove":true},"tagName":"section","metadata":{"name":"Campus"},"className":"wpedu-campus","anchor":"campus","style":{"spacing":{"padding":{"top":"64px","bottom":"64px"}}},"layout":{"type":"constrained","contentSize":"1240px"}} -->
<section id="campus" class="wp-block-group wpedu-campus" style="padding-top:64px;padding-bottom:64px">
	<!-- wp:group {"lock":{"move":true,"remove":true},"className":"wpedu-campus__inner","backgroundColor":"surface","layout":{"type":"constrained"}} -->
	<div class="wp-block-group wpedu-campus__inner has-surface-background-color has-background">
		<!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"blockGap":"48px"}}} -->
		<div class="wp-block-columns are-vertically-aligned-center">
			<!-- wp:column {"verticalAlignment":"center"} -->
			<div class="wp-block-column is-vertically-aligned-center">
				<!-- wp:paragraph {"className":"wpedu-eyebrow is-plain"} --><p class="wpedu-eyebrow is-plain">Campus program</p><!-- /wp:paragraph -->
				<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Bring WordPress to your campus</h2><!-- /wp:heading -->
				<!-- wp:paragraph {"className":"wpedu-campus__lead"} --><p class="wpedu-campus__lead">Join hundreds of schools and universities using WordPress to teach real-world web skills, host student work, and connect with the global open-source community.</p><!-- /wp:paragraph -->

				<!-- wp:group {"style":{"spacing":{"blockGap":"14px","margin":{"bottom":"30px"}}},"layout":{"type":"default"}} -->
				<div class="wp-block-group" style="margin-bottom:30px">
					<!-- wp:group {"className":"wpedu-check","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
					<div class="wp-block-group wpedu-check">
						<!-- wp:html --><span class="wpedu-check__mark" aria-hidden="true"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L19 7"></path></svg></span><!-- /wp:html -->
						<!-- wp:paragraph --><p>Free training and onboarding for faculty</p><!-- /wp:paragraph -->
					</div>
					<!-- /wp:group -->
					<!-- wp:group {"className":"wpedu-check","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
					<div class="wp-block-group wpedu-check">
						<!-- wp:html --><span class="wpedu-check__mark" aria-hidden="true"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L19 7"></path></svg></span><!-- /wp:html -->
						<!-- wp:paragraph --><p>Student certification pathways</p><!-- /wp:paragraph -->
					</div>
					<!-- /wp:group -->
					<!-- wp:group {"className":"wpedu-check","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
					<div class="wp-block-group wpedu-check">
						<!-- wp:html --><span class="wpedu-check__mark" aria-hidden="true"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L19 7"></path></svg></span><!-- /wp:html -->
						<!-- wp:paragraph --><p>Access to the global educator network</p><!-- /wp:paragraph -->
					</div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->

				<!-- wp:buttons -->
				<div class="wp-block-buttons">
					<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#community">Apply to the program</a></div><!-- /wp:button -->
				</div>
				<!-- /wp:buttons -->
			</div>
			<!-- /wp:column -->

			<!-- wp:column {"verticalAlignment":"center"} -->
			<div class="wp-block-column is-vertically-aligned-center">
				<!-- wp:cover {"customGradient":"linear-gradient(135deg,#e8edfc 0%,#d7f3ea 100%)","isDark":false,"className":"wpedu-campus__media wpedu-photo"} -->
				<div class="wp-block-cover is-light wpedu-campus__media wpedu-photo"><span aria-hidden="true" class="wp-block-cover__background has-background-gradient" style="background:linear-gradient(135deg,#e8edfc 0%,#d7f3ea 100%)"></span><div class="wp-block-cover__inner-container">
					<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Add a campus / lecture-hall photo</p><!-- /wp:paragraph -->
				</div></div>
				<!-- /wp:cover -->
			</div>
			<!-- /wp:column -->
		</div>
		<!-- /wp:columns -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
