<?php
/**
 * The inventory of old classes printed inside the Administrator Dashboard's parts (UI shared parts,
 * Task 17b).
 *
 * Every view of the Administrator Dashboard has a parts branch behind `WPCPM_UI::on()`. A class a
 * parts branch prints that is not a part's own (`wpcpm-ui-*`), not the frame's or the shell's, and
 * not an item's type class or anchor, is an old class: it is styled by a rule the release may
 * delete, by a theme rule hanging on an ancestor the parts no longer print, or by nothing. Each one
 * is named here, once, with why it stays, so a class that appears later fails the suite that drew it.
 *
 * The dashboard's own suite (bin/test-administrators-dashboard.php) draws every view; where it stands
 * in for a module's renderer, its stand-in prints what the real one prints on the parts (no old class of
 * its own, Task 18a), and that module's own suite draws the real one under
 * `WPCPM_UI::enter( 'administrator' )` and checks its own slice. `parts_inventory_pinned()` holds
 * every slice; the design's section 13 lists the same classes.
 *
 * Loaded with `require_once __DIR__ . '/stubs/parts-inventory.php';` from a suite.
 */

/**
 * Whether a class is one the parts are expected to print: the parts' own, the frame's and the
 * shell's, the card's own class (`wpcpm-administrator__card`), and each item's own type class (since Task 17b's fix
 * round the parts' article prints no `wpcpm-administrator__item`, so the inventory would name it).
 *
 * @param string $c A class name.
 * @return bool
 */
function parts_inventory_expected( $c ) {
	if ( 1 === preg_match( '/^(wpcpm-ui-|wpcpm-frame|wpcpm-dashboard$|wpcpm-btn|wpcpm-status|wpcpm-icon)/', $c ) ) {
		return true;
	}
	return in_array(
		$c,
		array(
			'screen-reader-text',
			'wpcpm-administrator__card',
			// Each item's own type class: the item's name for anchors and scripts.
			'wpcpm-agreement',
			'wpcpm-application',
			'wpcpm-request',
			'wpcpm-request--details',
			'wpcpm-sponsor-agreement',
			'wpcpm-sponsor-application',
			'wpcpm-sponsor-event',
			'wpcpm-sponsor-offer',
			'wpcpm-sponsor-post',
		),
		true
	);
}

/**
 * Every class printed inside an element carrying `wpcpm-ui-on` in a piece of markup, the expected
 * ones removed; sorted, each once.
 *
 * @param string $html Markup a renderer drew.
 * @return string[]
 */
function parts_inventory_classes( $html ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
	libxml_clear_errors();
	$found = array();
	foreach ( ( new DOMXPath( $doc ) )->query( '//*[contains(concat(" ", normalize-space(@class), " "), " wpcpm-ui-on ")]//*[@class]' ) as $node ) {
		foreach ( preg_split( '/\s+/', trim( (string) $node->getAttribute( 'class' ) ), -1, PREG_SPLIT_NO_EMPTY ) as $c ) {
			if ( ! parts_inventory_expected( $c ) ) {
				$found[ $c ] = true;
			}
		}
	}
	$found = array_keys( $found );
	sort( $found );
	return $found;
}

/**
 * Keeps a drawing for the inventory: what a module's renderer printed under the Administrator
 * Dashboard's surface, set inside the dashboard's root as the page prints it.
 *
 * @param string|string[] $html  What was drawn, or several drawings.
 * @param bool            $bare  True when the drawing already holds the switch's root.
 */
function parts_inventory_add( $html, $bare = false ) {
	foreach ( (array) $html as $one ) {
		$GLOBALS['parts_inventory_drawn'][] = $bare ? (string) $one : '<div class="wpcpm-dashboard wpcpm-ui-on">' . $one . '</div>';
	}
}

/**
 * Every old class the kept drawings print inside the switch's root (`parts_inventory_classes()`).
 *
 * @return string[]
 */
function parts_inventory_found() {
	return parts_inventory_classes( implode( '', isset( $GLOBALS['parts_inventory_drawn'] ) ? (array) $GLOBALS['parts_inventory_drawn'] : array() ) );
}

/**
 * The pinned inventory, one slice per suite that draws: every old class that suite's drawings print inside the
 * switch's root after Task 17b, sorted, each with why it stays (`parts_inventory_found()` must equal it).
 *
 * @param string $suite The suite's name, as `bin/test-<name>.php`.
 * @return string[]
 */
function parts_inventory_pinned( $suite ) {
	$pinned = array(
		'administrators-dashboard' => array(
			// The frame's menu item for the view open (`wpcpm-frame__item is-current`): dashboard-frame.css `.wpcpm-frame__item.is-current`.
			'is-current',
			// The held message's tone beside `wpcpm-dashboard__message--success` (ruling 6); no rule of its own reaches it here.
			'is-success',
			// The dashboard's root, beside `wpcpm-dashboard` (the shell): administrator.css `.wpcpm-frame .wpcpm-administrator`; the theme's `:is( .wpcpm-administrator, .wpcpm-sponsor )` rules.
			'wpcpm-administrator',
			// The root's view name (the shell); no rule.
			'wpcpm-administrator--overview',
			'wpcpm-administrator--reference',
			'wpcpm-administrator--watch',
			// A group of cards (the shell): theme `.wpc-administrator-page .wpc-main--app .wpcpm-administrator__group`.
			'wpcpm-administrator__group',
			// The group's name (the shell); no rule.
			'wpcpm-administrator__group--reference',
			// The Tools from our sponsors group (task V1, ruling 5); no rule.
			'wpcpm-administrator__group--tools',
			'wpcpm-administrator__group--waiting',
			'wpcpm-administrator__group--watch',
			// The test mark beside a test institution's name, on every card (`WPCPM_Test_Institutions::MARK_DASHBOARD`, ruling 3): dashboard.css `.wpcpm-badge`; theme `.wpcpm-badge`.
			'wpcpm-badge',
			// The test mark, as above: dashboard.css `.wpcpm-badge--test`.
			'wpcpm-badge--test',
			// The held message (ruling 6): administrator.css `.wpcpm-dashboard__message`.
			'wpcpm-dashboard__message',
			// The held message's tone (ruling 6): theme `.wpc-administrator-page .wpcpm-dashboard__message--success` (dashboard.css:1898); `--error` beside it (:1902).
			'wpcpm-dashboard__message--success',
			// The Overview's figures (the shell's view head): dashboard-frame.css `.wpcpm-figure`.
			'wpcpm-figure',
			// The figures, as above: dashboard-frame.css.
			'wpcpm-figure__base',
			'wpcpm-figure__label',
			'wpcpm-figure__value',
			// The figures, as above: dashboard-frame.css `.wpcpm-figures`.
			'wpcpm-figures',
			// The figures, as above: dashboard-frame.css `.wpcpm-figures--2`.
			'wpcpm-figures--2',
			// The figures, as above: dashboard-frame.css `.wpcpm-figures__source`.
			'wpcpm-figures__source',
			// Each view's head (the shell): dashboard-frame.css `.wpcpm-head`.
			'wpcpm-head',
			// The head, as above: dashboard-frame.css.
			'wpcpm-head__actions',
			'wpcpm-head__copy',
			'wpcpm-head__lede',
			'wpcpm-head__title',
			// Every card's help link in the fold's help slot (`WPCPM_Guide_Jobs::link()`): administrator.css `.wpcpm-job-link` (outside every range Task 18 deletes).
			'wpcpm-job-link',
			// The view's wrapper of groups (the shell): administrator.css `.wpcpm-overview` (outside every range).
			'wpcpm-overview',
			// The wrapper's name (the shell); no rule.
			'wpcpm-overview--wait',
			'wpcpm-overview--watch',
			// The tiles (ruling 6): administrator.css `.wpcpm-programs__name, .wpcpm-programs__l`.
			'wpcpm-programs__l',
			// The tiles (ruling 6): administrator.css `.wpcpm-programs__n`.
			'wpcpm-programs__n',
			// The tiles (ruling 6): administrator.css `.wpcpm-programs__name, .wpcpm-programs__l`.
			'wpcpm-programs__name',
			// The tiles (ruling 6): administrator.css `.wpcpm-programs__tile`; theme `.wpc-administrator-page .wpc-main--app .wpcpm-programs__tile`.
			'wpcpm-programs__tile',
			// The tiles (ruling 6): administrator.css `.wpcpm-programs__tiles`.
			'wpcpm-programs__tiles',
			// The Program view's wrapper (the shell); no rule.
			'wpcpm-reference',
			// Stand-in of the Tools section's anchor; the real section is bin/test-sponsor-tools.php's (ruling 6; on the parts
			// it has no frame of its own since task V1, ruling 5).
			'wpcpm-tools',
		),
		'institution-panel' => array(
			// A field's refusal, printed by `WPCPM_Form_Errors::problem()` through `WPCPM_UI::field()`: dashboard.css `.wpcpm-field__problem`, parts.css `.wpcpm-ui-field .wpcpm-field__problem`. Kept by the controller's ruling (Task 17b fix round).
			'wpcpm-field__problem',
		),
		'institution-request' => array(
			// A field's refusal, printed by `WPCPM_Form_Errors::problem()` through `WPCPM_UI::field()`: dashboard.css `.wpcpm-field__problem`, parts.css `.wpcpm-ui-field .wpcpm-field__problem`. Kept by the controller's ruling (Task 17b fix round).
			'wpcpm-field__problem',
		),
		'semester-report' => array(
		),
		'sponsor-agreement' => array(
			// A field's refusal, printed by `WPCPM_Form_Errors::problem()` through `WPCPM_UI::field()`: dashboard.css `.wpcpm-field__problem`, parts.css `.wpcpm-ui-field .wpcpm-field__problem`. Kept by the controller's ruling (Task 17b fix round).
			'wpcpm-field__problem',
		),
		'sponsor-application' => array(
			// A field's refusal, printed by `WPCPM_Form_Errors::problem()` through `WPCPM_UI::field()`: dashboard.css `.wpcpm-field__problem`, parts.css `.wpcpm-ui-field .wpcpm-field__problem`. Kept by the controller's ruling (Task 17b fix round).
			'wpcpm-field__problem',
		),
		'sponsor-posts' => array(
			// A field's refusal, printed by `WPCPM_Form_Errors::problem()` through `WPCPM_UI::field()`: dashboard.css `.wpcpm-field__problem`, parts.css `.wpcpm-ui-field .wpcpm-field__problem`. Kept by the controller's ruling (Task 17b fix round).
			'wpcpm-field__problem',
		),
		'institutions-screen' => array(
			// A field's refusal, printed by `WPCPM_Form_Errors::problem()` through `WPCPM_UI::field()`: dashboard.css `.wpcpm-field__problem`, parts.css `.wpcpm-ui-field .wpcpm-field__problem`. Kept by the controller's ruling (Task 17b fix round).
			'wpcpm-field__problem',
		),
		'sponsor-offers' => array(
			// A field's refusal, printed by `WPCPM_Form_Errors::problem()` through `WPCPM_UI::field()`: dashboard.css `.wpcpm-field__problem`, parts.css `.wpcpm-ui-field .wpcpm-field__problem`. Kept by the controller's ruling (Task 17b fix round).
			'wpcpm-field__problem',
			// A sponsor offer's details are the key and value table part since task V1, ruling 8: no old class left.
		),
		'sponsor-event-queue' => array(
			// The tone of the manager's own Join or Leave outcome, with `wpcpm-sevent__message`.
			'is-success',
			// A field's refusal, printed by `WPCPM_Form_Errors::problem()` through `WPCPM_UI::field()`: dashboard.css `.wpcpm-field__problem`, parts.css `.wpcpm-ui-field .wpcpm-field__problem`. Kept by the controller's ruling (Task 17b fix round).
			'wpcpm-field__problem',
			// The manager's own Join or Leave outcome under Coming up (`WPCPM_Sponsor_Event_List::render_message()`): theme `.wpc-dashboard-page .wpcpm-sevent__message` and its tones. Kept by the controller's ruling (Task 17b fix round).
			'wpcpm-sevent__message',
		),
		'sponsor-tools' => array(
			// The held message's tone (ruling 6), with `wpcpm-dashboard__message--error`.
			'is-error',
			// The held message's tone (ruling 6), with `wpcpm-dashboard__message--success`.
			'is-success',
			// The held message (ruling 6): administrator.css `.wpcpm-dashboard__message`.
			'wpcpm-dashboard__message',
			// The held message's tone (ruling 6): theme `.wpc-administrator-page .wpcpm-dashboard__message--error` (dashboard.css:1902), beside `--success`.
			'wpcpm-dashboard__message--error',
			// The held message's tone (ruling 6): theme `.wpc-administrator-page .wpcpm-dashboard__message--success` (dashboard.css:1898); `--error` beside it (:1902).
			'wpcpm-dashboard__message--success',
			// Today's offers on the parts (task V1, ruling 5): the one hint and the empty sentence: administrator.css `.wpcpm-student__note`; theme `.wpc-dashboard-page .wpcpm-student__note`.
			'wpcpm-student__note',
			// The Tools section's anchor (ruling 6), read by assets/js/forms.js; no rule.
			'wpcpm-tools',
			// Today's offers on the parts (task V1, ruling 5): dashboard.css `.wpcpm-tools__actions`; theme `.wpc-dashboard-page .wpcpm-tools__actions`.
			'wpcpm-tools__actions',
			// Today's offers on the parts (task V1, ruling 5): dashboard.css `.wpcpm-tools__audience`.
			'wpcpm-tools__audience',
			// Today's offers on the parts (task V1, ruling 5): dashboard.css `.wpcpm-tools__body`.
			'wpcpm-tools__body',
			// Today's offers on the parts (task V1, ruling 5): dashboard.css `.wpcpm-tools__claimed`.
			'wpcpm-tools__claimed',
			// A claimed code (ruling 6): dashboard.css `.wpcpm-tools__code`, `button.wpcpm-tools__code`; theme `.wpc-dashboard-page .wpcpm-tools__code`.
			'wpcpm-tools__code',
			// Today's offers on the parts (task V1, ruling 5): Your codes: dashboard.css `.wpcpm-tools__codes`, `.wpcpm-tools__codes li`; theme `.wpc-dashboard-page .wpcpm-tools__codes li`.
			'wpcpm-tools__codes',
			// Today's offers on the parts (task V1, ruling 5): a code's offer under Your codes; no rule.
			'wpcpm-tools__codes-offer',
			// Today's offers on the parts (task V1, ruling 5): the claim and problem forms: dashboard.css `.wpcpm-tools__form`, `.wpcpm-tools__actions .wpcpm-tools__form`, `.wpcpm-tools__codes .wpcpm-tools__form`.
			'wpcpm-tools__form',
			// Today's offers on the parts (task V1, ruling 5): the problem form, as above; no rule of its own.
			'wpcpm-tools__form--problem',
			// Today's offers on the parts (task V1, ruling 5): dashboard.css `.wpcpm-tools__initials`; theme `.wpc-dashboard-page .wpcpm-tools__initials`.
			'wpcpm-tools__initials',
			// Today's offers on the parts (task V1, ruling 5): dashboard.css `.wpcpm-tools__instructions`.
			'wpcpm-tools__instructions',
			// Today's offers on the parts (task V1, ruling 5): the grid of offers: dashboard.css `.wpcpm-tools__list`; theme `.wpc-dashboard-page .wpcpm-tools__list`.
			'wpcpm-tools__list',
			// Today's offers on the parts (task V1, ruling 5): dashboard.css `.wpcpm-tools__logo`, `.wpcpm-tools__logo img`; theme `.wpc-dashboard-page .wpcpm-tools__logo`, `.wpc-administrator-page .wpc-main--app :is( .wpcpm-tools__offer, .wpcpm-tools__logo, ... )`.
			'wpcpm-tools__logo',
			// Today's offers on the parts (task V1, ruling 5): dashboard.css `.wpcpm-tools__more`, `.wpcpm-tools__actions .wpcpm-tools__more`.
			'wpcpm-tools__more',
			// Today's offers on the parts (task V1, ruling 5): one offer card: dashboard.css `.wpcpm-tools__offer`; theme `.wpc-dashboard-page .wpcpm-tools__offer` and the radius rule above.
			'wpcpm-tools__offer',
			// Today's offers on the parts (task V1, ruling 5): a sponsor's guides (`WPCPM_Sponsor_Posts::render_tools()`): theme `.wpc-dashboard-page .wpcpm-tools__posts`.
			'wpcpm-tools__posts',
			// Today's offers on the parts (task V1, ruling 5): dashboard.css `.wpcpm-tools__sponsor`; theme `.wpc-dashboard-page .wpcpm-tools__sponsor`.
			'wpcpm-tools__sponsor',
			// Today's offers on the parts (task V1, ruling 5): dashboard.css `.wpcpm-tools__subheading`.
			'wpcpm-tools__subheading',
			// Today's offers on the parts (task V1, ruling 5): dashboard.css `.wpcpm-tools__text`.
			'wpcpm-tools__text',
			// Today's offers on the parts (task V1, ruling 5): dashboard.css `.wpcpm-tools__title`; theme `.wpc-dashboard-page .wpcpm-tools__title`.
			'wpcpm-tools__title',
			// Today's offers on the parts (task V1, ruling 5): dashboard.css `.wpcpm-tools__warning`; theme `.wpc-dashboard-page .wpcpm-tools__warning`.
			'wpcpm-tools__warning',
			// Today's offers on the parts (task V1, ruling 5): dashboard.css `.wpcpm-tools__when`.
			'wpcpm-tools__when',
		),
		'cohort-timeline' => array(
			// An intake chip whose month is unknown: cohort-timeline.css `.wpcpm-timeline-intakes__chip.is-unknown` (ruling 6).
			'is-unknown',
			// A month with nobody in a flow: cohort-timeline.css `.wpcpm-timeline__flow .is-zero` (ruling 6).
			'is-zero',
			// The cohort view's own head: dashboard-frame.css `.wpcpm-head*`, as every framed view's head (the view's frame, ruling 6).
			'wpcpm-head',
			'wpcpm-head__copy',
			'wpcpm-head__eyebrow',
			'wpcpm-head__facts',
			'wpcpm-head__lede',
			'wpcpm-head__title',
			// The cohort chart: cohort-timeline.css (ruling 6).
			'wpcpm-timeline',
			'wpcpm-timeline--compact',
			'wpcpm-timeline-icon',
			// The intakes: cohort-timeline.css (ruling 6).
			'wpcpm-timeline-intakes',
			'wpcpm-timeline-intakes__bar',
			'wpcpm-timeline-intakes__chip',
			'wpcpm-timeline-intakes__chips',
			'wpcpm-timeline-intakes__group',
			'wpcpm-timeline-intakes__item',
			'wpcpm-timeline-intakes__label',
			'wpcpm-timeline-intakes__month',
			'wpcpm-timeline-intakes__n',
			'wpcpm-timeline-intakes__split',
			'wpcpm-timeline-intakes__when',
			// The view's notes: cohort-timeline.css `.wpcpm-timeline-notes` (ruling 6).
			'wpcpm-timeline-notes',
			// The four figures: cohort-timeline.css (ruling 6).
			'wpcpm-timeline-stats',
			'wpcpm-timeline-stats--compact',
			'wpcpm-timeline-stats__item',
			'wpcpm-timeline-stats__item--ok',
			'wpcpm-timeline-stats__item--short',
			'wpcpm-timeline-stats__item--tight',
			'wpcpm-timeline-stats__label',
			'wpcpm-timeline-stats__note',
			'wpcpm-timeline-stats__tag',
			'wpcpm-timeline-stats__tag--ok',
			'wpcpm-timeline-stats__tag--short',
			'wpcpm-timeline-stats__tag--tight',
			'wpcpm-timeline-stats__value',
			// The cohort view's own frame and sections: cohort-timeline.css (ruling 6).
			'wpcpm-timeline-view',
			'wpcpm-timeline-view__header',
			'wpcpm-timeline-view__section',
			// The cohort chart: cohort-timeline.css (ruling 6).
			'wpcpm-timeline__area',
			'wpcpm-timeline__axis',
			'wpcpm-timeline__band',
			'wpcpm-timeline__band--even',
			'wpcpm-timeline__band--odd',
			'wpcpm-timeline__bands',
			'wpcpm-timeline__bar',
			'wpcpm-timeline__flag',
			'wpcpm-timeline__flow',
			'wpcpm-timeline__in',
			'wpcpm-timeline__key',
			'wpcpm-timeline__key--places',
			'wpcpm-timeline__label',
			'wpcpm-timeline__legend',
			'wpcpm-timeline__mon',
			'wpcpm-timeline__month',
			'wpcpm-timeline__month--end',
			'wpcpm-timeline__month--ok',
			'wpcpm-timeline__month--short',
			'wpcpm-timeline__month--start',
			'wpcpm-timeline__month--tight',
			'wpcpm-timeline__months',
			'wpcpm-timeline__out',
			'wpcpm-timeline__overlay',
			'wpcpm-timeline__overlay--grid',
			'wpcpm-timeline__overlay--places',
			'wpcpm-timeline__places',
			'wpcpm-timeline__places-label',
			'wpcpm-timeline__plot',
			'wpcpm-timeline__scroll',
			'wpcpm-timeline__seg',
			'wpcpm-timeline__seg--continuing',
			'wpcpm-timeline__seg--review',
			'wpcpm-timeline__seg--starting',
			'wpcpm-timeline__stack',
			'wpcpm-timeline__tick',
			'wpcpm-timeline__tip',
			'wpcpm-timeline__year',
		),
	);
	return isset( $pinned[ $suite ] ) ? $pinned[ $suite ] : array( 'no slice is pinned for ' . $suite );
}
