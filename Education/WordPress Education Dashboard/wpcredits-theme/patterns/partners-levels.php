<?php
/**
 * Title: Partners page, the partnership levels
 * Slug: wpcredits/partners-levels
 * Categories: wpcredits
 * Description: The pilot year, the five partnership levels with what each gives, and a table comparing them.
 * Keywords: partners, sponsors, levels, tiers
 * Viewport Width: 1400
 *
 * The cards and the table are drawn from the two lists below, in a program lead's words (21 October
 * 2026): the levels, each with its amount and what it adds, and the table's rows, each with the
 * first level that has it, so the table says Yes from that level up and the two can never disagree.
 * The amounts are the program's own, text on the page; the site handles no payment.
 *
 * @package WPCredits_Theme
 */

$wpcredits_levels = array(
	array(
		'name'   => __( 'Friend', 'wpcredits-theme' ),
		'amount' => __( 'In-kind, through Five for the Future', 'wpcredits-theme' ),
		/* translators: A partnership level's amount in the comparison table's head, short: given in kind, not paid. */
		'short'  => __( 'In-kind', 'wpcredits-theme' ),
		'lead'   => __( 'For companies sponsoring mentors through Five for the Future.', 'wpcredits-theme' ),
		'items'  => array(
			__( 'Company name on the partner page', 'wpcredits-theme' ),
			__( 'Mention in the mentor feature', 'wpcredits-theme' ),
			__( 'Acknowledgement in the annual partner thank you post', 'wpcredits-theme' ),
		),
	),
	array(
		'name'   => __( 'Supporter', 'wpcredits-theme' ),
		/* translators: A partnership level's amount, in US dollars a year. */
		'amount' => __( '$5,000 a year', 'wpcredits-theme' ),
		/* translators: A partnership level's amount in the comparison table's head, in US dollars. */
		'short'  => __( '$5,000', 'wpcredits-theme' ),
		'lead'   => __( 'Everything in Friend, plus:', 'wpcredits-theme' ),
		'items'  => array(
			__( 'Logo and description on the partner page', 'wpcredits-theme' ),
			__( 'A badge for your own site', 'wpcredits-theme' ),
			__( 'The annual program report', 'wpcredits-theme' ),
		),
	),
	array(
		'name'   => __( 'Backer', 'wpcredits-theme' ),
		/* translators: A partnership level's amount, in US dollars a year. */
		'amount' => __( '$10,000 a year', 'wpcredits-theme' ),
		/* translators: A partnership level's amount in the comparison table's head, in US dollars. */
		'short'  => __( '$10,000', 'wpcredits-theme' ),
		'lead'   => __( 'Everything in Supporter, plus:', 'wpcredits-theme' ),
		'items'  => array(
			__( 'Named for the part of the program you fund in program communications', 'wpcredits-theme' ),
			__( 'A report covering that cost line', 'wpcredits-theme' ),
			__( 'One self-written educational post about the sponsored product', 'wpcredits-theme' ),
		),
	),
	array(
		'name'   => __( 'Champion', 'wpcredits-theme' ),
		/* translators: A partnership level's amount, in US dollars a year. */
		'amount' => __( '$15,000 a year', 'wpcredits-theme' ),
		/* translators: A partnership level's amount in the comparison table's head, in US dollars. */
		'short'  => __( '$15,000', 'wpcredits-theme' ),
		'lead'   => __( 'Everything in Backer, plus:', 'wpcredits-theme' ),
		'items'  => array(
			__( 'A second self-written post with factual, value-neutral information about additional products', 'wpcredits-theme' ),
			__( 'An invitation to the annual education partners session', 'wpcredits-theme' ),
			__( 'A webinar or event co-hosted with the education program', 'wpcredits-theme' ),
		),
	),
	array(
		'name'   => _x( 'Partner', 'the highest partnership level, a noun: a company that funds a named program', 'wpcredits-theme' ),
		/* translators: A partnership level's amount, in US dollars a year, the least it starts from. */
		'amount' => __( 'From $30,000 a year', 'wpcredits-theme' ),
		/* translators: A partnership level's amount in the comparison table's head, in US dollars, the least it starts from. */
		'short'  => __( 'From $30,000', 'wpcredits-theme' ),
		'tag'    => __( 'Funds a named program', 'wpcredits-theme' ),
		'lead'   => __( 'Everything in Champion, plus a complete program funded end to end, the kind we have not been able to build yet because we have always had to work within the budget available. Students translating WordPress into languages with thin coverage, students working on accessibility, students building sites for nonprofit organizations that could not otherwise commission them.', 'wpcredits-theme' ),
		'items'  => array(
			__( 'Named as program partner on the program you fund, with the exclusive role in that program', 'wpcredits-theme' ),
			__( 'Named in launch communications', 'wpcredits-theme' ),
			__( 'A report specific to that program', 'wpcredits-theme' ),
		),
	),
);

// Each row of the table: what it recognizes, and the first level, from 0 (Friend), that has it.
$wpcredits_rows = array(
	array( __( 'Name on the partner page', 'wpcredits-theme' ), 0 ),
	array( __( 'Mention in the mentor feature', 'wpcredits-theme' ), 0 ),
	array( __( 'Acknowledged in the partner thank you post', 'wpcredits-theme' ), 0 ),
	array( __( 'Logo and description on the partner page', 'wpcredits-theme' ), 1 ),
	array( __( 'Badge for your own site', 'wpcredits-theme' ), 1 ),
	array( __( 'Annual program report', 'wpcredits-theme' ), 1 ),
	array( __( 'Named for what you fund in program communications', 'wpcredits-theme' ), 2 ),
	array( __( 'Report covering that cost line', 'wpcredits-theme' ), 2 ),
	array( __( 'A self-written educational post about the sponsored product', 'wpcredits-theme' ), 2 ),
	array( __( 'A second self-written post about additional products', 'wpcredits-theme' ), 3 ),
	array( __( 'Invitation to the annual education partners session', 'wpcredits-theme' ), 3 ),
	array( __( 'Co-hosted webinar or event', 'wpcredits-theme' ), 3 ),
	array( __( 'Named as program partner on the program you fund', 'wpcredits-theme' ), 4 ),
	array( __( 'Named in launch communications', 'wpcredits-theme' ), 4 ),
	array( __( 'Program-specific report', 'wpcredits-theme' ), 4 ),
);

$wpcredits_yes = _x( 'Yes', 'a partnership level has this recognition', 'wpcredits-theme' );
$wpcredits_no  = _x( 'No', 'a partnership level does not have this recognition', 'wpcredits-theme' );

?>
<!-- wp:group {"align":"wide","className":"wpc-partners-pilot-band","style":{"spacing":{"padding":{"top":"48px","bottom":"0"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide wpc-partners-pilot-band" style="padding-top:48px;padding-bottom:0"><!-- wp:group {"className":"wpc-partners-pilot","layout":{"type":"default"}} -->
<div class="wp-block-group wpc-partners-pilot"><!-- wp:heading {"level":2,"className":"wpc-partners-pilot__title"} -->
<h2 class="wp-block-heading wpc-partners-pilot__title"><?php esc_html_e( '2027 is a pilot year', 'wpcredits-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'This is the first time we have offered partnership levels, and we are treating it as a test. We will run it through 2027, publish what we learn, and adjust or retire whatever does not work. If none of the levels below fits what your company wants to do, write to us and we will shape something that does.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","anchor":"levels","className":"wpc-partners-levels wpc-hub-anchored","style":{"spacing":{"padding":{"top":"48px","bottom":"48px"}}},"layout":{"type":"default"}} -->
<div id="levels" class="wp-block-group alignwide wpc-partners-levels wpc-hub-anchored" style="padding-top:48px;padding-bottom:48px"><!-- wp:group {"className":"wpc-hub-head","layout":{"type":"default"}} -->
<div class="wp-block-group wpc-hub-head"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Partnership levels', 'wpcredits-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"wpc-hub-lede"} -->
<p class="wpc-hub-lede"><?php esc_html_e( 'Five levels, from contributing mentor time through to funding a complete program. Recognition is consistent across all of them: partners are listed in the same format, with descriptions of the same length, in alphabetical order rather than by level.', 'wpcredits-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"wpc-partners-levels__compare"} -->
<p class="wpc-partners-levels__compare"><a href="#glance"><?php esc_html_e( 'Compare all levels', 'wpcredits-theme' ); ?><span aria-hidden="true"> →</span></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:html -->
<ul class="wpc-partners-levels__grid">
<?php foreach ( $wpcredits_levels as $wpcredits_level ) : ?>
	<li class="wpc-partners-level<?php echo isset( $wpcredits_level['tag'] ) ? ' wpc-partners-level--program' : ''; ?>">
		<h3 class="wpc-partners-level__name"><?php echo esc_html( $wpcredits_level['name'] ); ?><?php if ( isset( $wpcredits_level['tag'] ) ) : ?> <span class="wpc-partners-tag"><?php echo esc_html( $wpcredits_level['tag'] ); ?></span><?php endif; ?></h3>
		<p class="wpc-partners-level__amount"><?php echo esc_html( $wpcredits_level['amount'] ); ?></p>
		<p class="wpc-partners-level__lead"><?php echo esc_html( $wpcredits_level['lead'] ); ?></p>
		<ul class="wpc-partners-level__items">
		<?php foreach ( $wpcredits_level['items'] as $wpcredits_item ) : ?>
			<li><?php echo esc_html( $wpcredits_item ); ?></li>
		<?php endforeach; ?>
		</ul>
	</li>
<?php endforeach; ?>
</ul>
<!-- /wp:html --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","anchor":"glance","className":"wpc-partners-glance wpc-hub-anchored","style":{"spacing":{"padding":{"top":"48px","bottom":"48px"}}},"layout":{"type":"default"}} -->
<div id="glance" class="wp-block-group alignwide wpc-partners-glance wpc-hub-anchored" style="padding-top:48px;padding-bottom:48px"><!-- wp:group {"className":"wpc-hub-head","layout":{"type":"default"}} -->
<div class="wp-block-group wpc-hub-head"><!-- wp:heading -->
<h2 class="wp-block-heading" id="wpc-partners-glance-title"><?php esc_html_e( 'Levels at a glance', 'wpcredits-theme' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:html -->
<div class="wpc-partners-glance__scroll" tabindex="0" role="region" aria-labelledby="wpc-partners-glance-title">
	<table class="wpc-partners-glance__table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Recognition', 'wpcredits-theme' ); ?></th>
				<?php foreach ( $wpcredits_levels as $wpcredits_level ) : ?>
					<th scope="col"><span class="wpc-partners-glance__level"><?php echo esc_html( $wpcredits_level['name'] ); ?></span> <span class="wpc-partners-glance__amount"><?php echo esc_html( $wpcredits_level['short'] ); ?></span></th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $wpcredits_rows as $wpcredits_row ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $wpcredits_row[0] ); ?></th>
					<?php foreach ( array_keys( $wpcredits_levels ) as $wpcredits_at ) : ?>
						<?php if ( $wpcredits_at >= $wpcredits_row[1] ) : ?>
							<td class="wpc-partners-glance__yes"><?php echo esc_html( $wpcredits_yes ); ?></td>
						<?php else : ?>
							<td class="wpc-partners-glance__no"><?php echo esc_html( $wpcredits_no ); ?></td>
						<?php endif; ?>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<!-- /wp:html --></div>
<!-- /wp:group -->
