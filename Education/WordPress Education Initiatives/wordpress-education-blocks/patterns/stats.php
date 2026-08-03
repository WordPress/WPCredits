<?php
/**
 * Title: Statistics band
 * Slug: wp-education-blocks/stats
 * Categories: wpedu
 * Description: A tinted band of animated statistics with decorative doodles.
 * Inserter: true
 */
?>
<!-- wp:group {"lock":{"move":true,"remove":true},"tagName":"section","metadata":{"name":"Statistics"},"className":"wpedu-stats","align":"full","backgroundColor":"surface","layout":{"type":"constrained","contentSize":"1080px"}} -->
<section class="wp-block-group alignfull wpedu-stats has-surface-background-color has-background">
	<!-- wp:html -->
	<div aria-hidden="true">
		<svg width="120" height="150" viewBox="0 0 120 150" style="position:absolute;left:24px;top:-70px" fill="none" stroke="#5fd0ab" stroke-width="2.4" stroke-linecap="round"><path d="M60 150 C 60 100 60 70 60 40"></path><path d="M60 96 C 30 90 16 66 20 44 C 44 46 62 70 60 96Z" fill="#d7f3ea"></path><path d="M60 74 C 88 70 102 48 100 28 C 78 30 60 50 60 74Z" fill="#d7f3ea"></path></svg>
		<svg width="150" height="90" viewBox="0 0 150 90" style="position:absolute;left:0;bottom:0" fill="none" stroke="#3858e9" stroke-width="2" stroke-linejoin="round"><rect x="18" y="60" width="120" height="20" rx="2" fill="#e8edfc"></rect><rect x="26" y="42" width="104" height="18" rx="2" fill="#dfe6fb"></rect><rect x="14" y="24" width="112" height="18" rx="2" fill="#eef2fd"></rect><path d="M14 33 h112 M26 51 h104 M18 70 h120"></path></svg>
		<svg width="150" height="170" viewBox="0 0 150 170" style="position:absolute;right:14px;bottom:-6px" fill="none" stroke="#3858e9" stroke-width="2" stroke-linejoin="round"><path d="M40 60 C40 34 60 22 78 22 C96 22 116 34 116 60 v78 c0 8-6 14-14 14 H54 c-8 0-14-6-14-14Z" fill="#eef2fd"></path><path d="M62 60 c0-18 8-26 16-26 s16 8 16 26"></path><path d="M52 96 h52 v30 h-52Z" fill="#dfe6fb"></path><path d="M70 96 v30 M86 96 v30"></path></svg>
	</div>
	<!-- /wp:html -->

	<!-- wp:group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"grid","minimumColumnWidth":"13rem"}} -->
	<div class="wp-block-group">
		<!-- wp:group {"lock":{"move":true,"remove":true},"className":"wpedu-stat has-rule","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"},"style":{"spacing":{"blockGap":"16px"}}} -->
		<div class="wp-block-group wpedu-stat has-rule">
			<!-- wp:html --><span class="wpedu-disc wpedu-fill-blue" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.5"><circle cx="12" cy="12" r="9"></circle><path d="M3 12h18"></path><path d="M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"></path></svg></span><!-- /wp:html -->
			<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
			<div class="wp-block-group">
				<!-- wp:paragraph {"className":"wpedu-stat__num","style":{"color":{"text":"#3858e9"}}} --><p class="wpedu-stat__num has-text-color" style="color:#3858e9">100+</p><!-- /wp:paragraph -->
				<!-- wp:paragraph {"className":"wpedu-stat__label"} --><p class="wpedu-stat__label">Countries</p><!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"lock":{"move":true,"remove":true},"className":"wpedu-stat has-rule","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"},"style":{"spacing":{"blockGap":"16px"}}} -->
		<div class="wp-block-group wpedu-stat has-rule">
			<!-- wp:html --><span class="wpedu-disc wpedu-fill-green" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0e6b52" stroke-width="1.5" stroke-linejoin="round"><path d="M3 21h18M4 21V10l8-5 8 5v11"></path><path d="M8 21v-7h8v7"></path></svg></span><!-- /wp:html -->
			<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
			<div class="wp-block-group">
				<!-- wp:paragraph {"className":"wpedu-stat__num","style":{"color":{"text":"#0e8a63"}}} --><p class="wpedu-stat__num has-text-color" style="color:#0e8a63">1,200+</p><!-- /wp:paragraph -->
				<!-- wp:paragraph {"className":"wpedu-stat__label"} --><p class="wpedu-stat__label">Educational institutions</p><!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"lock":{"move":true,"remove":true},"className":"wpedu-stat has-rule","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"},"style":{"spacing":{"blockGap":"16px"}}} -->
		<div class="wp-block-group wpedu-stat has-rule">
			<!-- wp:html --><span class="wpedu-disc wpedu-fill-yellow" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#7a5a05" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"></circle><path d="M3 20c0-3.3 2.7-5 6-5s6 1.7 6 5"></path><circle cx="17" cy="9" r="2.4"></circle><path d="M15.5 14.2c3 .3 5 2 5 4.8"></path></svg></span><!-- /wp:html -->
			<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
			<div class="wp-block-group">
				<!-- wp:paragraph {"className":"wpedu-stat__num","style":{"color":{"text":"#a97d13"}}} --><p class="wpedu-stat__num has-text-color" style="color:#a97d13">50,000+</p><!-- /wp:paragraph -->
				<!-- wp:paragraph {"className":"wpedu-stat__label"} --><p class="wpedu-stat__label">Students engaged</p><!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"lock":{"move":true,"remove":true},"className":"wpedu-stat","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"},"style":{"spacing":{"blockGap":"16px"}}} -->
		<div class="wp-block-group wpedu-stat">
			<!-- wp:html --><span class="wpedu-disc wpedu-bg-purple" aria-hidden="true"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#8a54d6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 8 L5 12 L9 16"></path><path d="M15 8 L19 12 L15 16"></path></svg></span><!-- /wp:html -->
			<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
			<div class="wp-block-group">
				<!-- wp:paragraph {"className":"wpedu-stat__num","style":{"color":{"text":"#8a54d6"}}} --><p class="wpedu-stat__num has-text-color" style="color:#8a54d6">Endless</p><!-- /wp:paragraph -->
				<!-- wp:paragraph {"className":"wpedu-stat__label"} --><p class="wpedu-stat__label">Opportunities</p><!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
