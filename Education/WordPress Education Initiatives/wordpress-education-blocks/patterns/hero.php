<?php
/**
 * Title: Hero
 * Slug: wp-education-blocks/hero
 * Categories: wpedu
 * Description: Landing hero with headline, lead text, call-to-action buttons and an editable photo slot.
 * Keywords: hero, banner, education
 * Inserter: true
 */
?>
<!-- wp:group {"lock":{"move":true,"remove":true},"tagName":"section","metadata":{"name":"Hero"},"className":"wpedu-hero","align":"full","anchor":"top","layout":{"type":"constrained","contentSize":"1240px"}} -->
<section id="top" class="wp-block-group alignfull wpedu-hero">
	<!-- wp:columns {"verticalAlignment":"center","className":"wpedu-hero__cols"} -->
	<div class="wp-block-columns are-vertically-aligned-center wpedu-hero__cols">
		<!-- wp:column {"lock":{"move":true,"remove":true},"verticalAlignment":"center","className":"wpedu-hero__copy"} -->
		<div class="wp-block-column is-vertically-aligned-center wpedu-hero__copy">
			<!-- wp:heading {"level":1,"className":"wpedu-hero__title","fontSize":"hero"} -->
			<h1 class="wp-block-heading wpedu-hero__title has-hero-font-size">Empowering the next generation <span class="wpedu-hero__hl">with open source</span></h1>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"className":"wpedu-hero__lead"} -->
			<p class="wpedu-hero__lead">WordPress Education Initiatives connect educators, students, and institutions with the tools, resources, and global community to learn, create, and contribute.</p>
			<!-- /wp:paragraph -->

			<!-- wp:buttons {"style":{"spacing":{"blockGap":"14px"}}} -->
			<div class="wp-block-buttons">
				<!-- wp:button {"style":{"typography":{"fontSize":"16px"}}} -->
				<div class="wp-block-button" style="font-size:16px"><a class="wp-block-button__link wp-element-button" href="#programs">Explore programs</a></div>
				<!-- /wp:button -->
				<!-- wp:button {"className":"wpedu-btn-ghost","style":{"typography":{"fontSize":"16px"}}} -->
				<div class="wp-block-button wpedu-btn-ghost" style="font-size:16px"><a class="wp-block-button__link wp-element-button" href="#campus">Bring WordPress to campus</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"lock":{"move":true,"remove":true},"className":"wpedu-hero__visual"} -->
		<div class="wp-block-column wpedu-hero__visual">
			<!-- wp:cover {"customGradient":"linear-gradient(135deg,#e8edfc 0%,#d7f3ea 100%)","isDark":false,"className":"wpedu-hero__photo wpedu-photo","layout":{"type":"constrained"}} -->
			<div class="wp-block-cover is-light wpedu-hero__photo wpedu-photo"><span aria-hidden="true" class="wp-block-cover__background has-background-gradient" style="background:linear-gradient(135deg,#e8edfc 0%,#d7f3ea 100%)"></span><div class="wp-block-cover__inner-container">
				<!-- wp:paragraph {"align":"center","placeholder":"Add a classroom photo"} -->
				<p class="has-text-align-center">Add a classroom photo (students on a laptop)</p>
				<!-- /wp:paragraph -->
			</div></div>
			<!-- /wp:cover -->

			<!-- wp:html -->
			<div class="wpedu-hero__badges">
				<div class="wpedu-float" style="position:absolute;top:24px;left:-6px;width:96px;height:96px;border-radius:50%;background:#3858e9;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 24px rgba(56,88,233,.35);animation:wpe-float 5s ease-in-out infinite">
					<svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true"><path d="M12 4 L22 9 L12 14 L2 9 Z"></path><path d="M6 11 v5 c0 1.3 2.7 2.5 6 2.5 s6-1.2 6-2.5 v-5"></path><path d="M22 9 v6"></path></svg>
				</div>
				<div style="position:absolute;top:70px;right:-16px;width:96px;height:120px;filter:drop-shadow(0 10px 20px rgba(52,193,154,.35))">
					<svg width="96" height="120" viewBox="0 0 96 120" aria-hidden="true"><path d="M48 4 C22 4 4 24 4 48 c0 30 44 68 44 68 s44-38 44-68 C92 24 74 4 48 4Z" fill="#5fd0ab"></path></svg>
					<svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#0e6b52" stroke-width="1.4" style="position:absolute;top:16px;left:20px" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M3 12h18"></path><path d="M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"></path></svg>
				</div>
			</div>
			<!-- /wp:html -->

			<!-- wp:group {"className":"wpedu-hero__quote","layout":{"type":"constrained"}} -->
			<div class="wp-block-group wpedu-hero__quote">
				<!-- wp:paragraph -->
				<p>“WordPress is more than a tool—it's a gateway to skills, creativity, and community.”</p>
				<!-- /wp:paragraph -->
				<!-- wp:paragraph -->
				<p><cite>— Educator</cite></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</section>
<!-- /wp:group -->
