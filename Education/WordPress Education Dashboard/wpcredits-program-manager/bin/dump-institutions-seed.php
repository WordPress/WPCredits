<?php
/**
 * Refresh bin/fixtures/institutions-index-seed.json from the live base.
 *
 * Runs on the site, through WP-CLI, because the token lives there and nowhere else:
 *
 *   ssh wpcredits-dashboard 'wp eval-file -' < bin/dump-institutions-seed.php \
 *     > bin/fixtures/institutions-index-seed.json
 *
 * Everything personal is left out on purpose: no contact person, no contact email, no
 * department, no prose, and for countries only whether a contact, an email and a Calendly
 * link exist. The fixture is published in a public repository, and institution names,
 * cities and websites are the organisations' public facts; a person's name is not.
 * After a refresh, update the counts bin/test-institutions-index.php pins.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$wpcpm_seed_airtable = new WPCPM_Airtable();
$wpcpm_seed_records  = $wpcpm_seed_airtable->fetch_all(
	'tbl4V0FEbzRP7I2w2',
	array(
		'fields' => array( 'Name', 'Current Stage', 'Country', 'City', 'Website', 'Confirmed on', 'Privacy Policy Compliance', 'Agreement Status', 'Agreement Kind', 'Agreement Accepted On', 'Agreement Signed On', 'Agreement Accepted By', 'Agreement Document', 'Agreement Submitted On', 'Agreement Template Version', 'Contact Email', 'Contact Person' ),
	)
);

if ( is_wp_error( $wpcpm_seed_records ) ) {
	WP_CLI::error( $wpcpm_seed_records->get_error_message() );
}

$wpcpm_seed_rows = array();

foreach ( $wpcpm_seed_records as $wpcpm_seed_record ) {
	$f = $wpcpm_seed_record['fields'];

	$wpcpm_seed_rows[] = array(
		'id'                 => $wpcpm_seed_record['id'],
		'createdTime'        => $wpcpm_seed_record['createdTime'],
		'name'               => isset( $f['Name'] ) ? $f['Name'] : '',
		'stage'              => isset( $f['Current Stage'] ) ? $f['Current Stage'] : '',
		'country'            => isset( $f['Country'] ) ? $f['Country'] : array(),
		'city'               => isset( $f['City'] ) ? $f['City'] : '',
		'website'            => isset( $f['Website'] ) ? $f['Website'] : '',
		'confirmed_on'       => isset( $f['Confirmed on'] ) ? $f['Confirmed on'] : '',
		'consent'            => ! empty( $f['Privacy Policy Compliance'] ),
		'has_contact_email'  => ! empty( $f['Contact Email'] ),
		'has_contact_person' => ! empty( $f['Contact Person'] ),
		'agreement'          => array(
			'status'           => isset( $f['Agreement Status'] ) ? $f['Agreement Status'] : '',
			'kind'             => isset( $f['Agreement Kind'] ) ? $f['Agreement Kind'] : '',
			'accepted_on'      => isset( $f['Agreement Accepted On'] ) ? $f['Agreement Accepted On'] : '',
			'signed_on'        => isset( $f['Agreement Signed On'] ) ? $f['Agreement Signed On'] : '',
			'accepted_by'      => isset( $f['Agreement Accepted By'] ) ? $f['Agreement Accepted By'] : '',
			'has_document'     => ! empty( $f['Agreement Document'] ),
			'submitted_on'     => isset( $f['Agreement Submitted On'] ) ? $f['Agreement Submitted On'] : '',
			'template_version' => isset( $f['Agreement Template Version'] ) ? $f['Agreement Template Version'] : '',
		),
	);
}

$wpcpm_seed_countries = $wpcpm_seed_airtable->fetch_all(
	'tbltB7GSRoTtSi4Ps',
	array(
		'fields' => array( 'Name', 'Person of contact (Team)', 'Email (from Person of contact (Team))', 'Calendly link (from Person of contact (Team))' ),
	)
);

if ( is_wp_error( $wpcpm_seed_countries ) ) {
	WP_CLI::error( $wpcpm_seed_countries->get_error_message() );
}

$wpcpm_seed_country_rows = array();

foreach ( $wpcpm_seed_countries as $wpcpm_seed_country ) {
	$f = $wpcpm_seed_country['fields'];

	$wpcpm_seed_country_rows[] = array(
		'id'           => $wpcpm_seed_country['id'],
		'name'         => isset( $f['Name'] ) ? $f['Name'] : '',
		'has_contact'  => ! empty( $f['Person of contact (Team)'] ),
		'has_email'    => ! empty( $f['Email (from Person of contact (Team))'] ),
		'has_calendly' => ! empty( $f['Calendly link (from Person of contact (Team))'] ),
	);
}

// The counts a reader checks first; the test pins them, so refresh both together.
$wpcpm_seed_stages = array();
$wpcpm_seed_pre    = 0;
$wpcpm_seed_pre_c  = 0;
$wpcpm_seed_pre_t  = 0;
$wpcpm_seed_trail  = 0;
$wpcpm_seed_blank  = 0;

foreach ( $wpcpm_seed_rows as $r ) {
	$k                         = '' === $r['stage'] ? '(empty)' : $r['stage'];
	$wpcpm_seed_stages[ $k ]   = isset( $wpcpm_seed_stages[ $k ] ) ? $wpcpm_seed_stages[ $k ] + 1 : 1;
	if ( $r['createdTime'] < '2026-07-20' ) {
		$wpcpm_seed_pre++;
		if ( 'Confirmed' === $r['stage'] ) {
			$wpcpm_seed_pre_c++;
		}
		if ( $r['consent'] ) {
			$wpcpm_seed_pre_t++;
		}
	}
	if ( '' === $r['name'] ) {
		$wpcpm_seed_blank++;
	} elseif ( rtrim( $r['name'] ) !== $r['name'] ) {
		$wpcpm_seed_trail++;
	}
}
arsort( $wpcpm_seed_stages );

$wpcpm_seed_no_contact = 0;
$wpcpm_seed_by_id      = array();
foreach ( $wpcpm_seed_country_rows as $c ) {
	$wpcpm_seed_by_id[ $c['id'] ] = $c;
	if ( ! $c['has_contact'] ) {
		$wpcpm_seed_no_contact++;
	}
}
$wpcpm_seed_used     = array();
$wpcpm_seed_unrouted = array();
foreach ( $wpcpm_seed_rows as $r ) {
	foreach ( (array) $r['country'] as $cid ) {
		$wpcpm_seed_used[ $cid ] = true;
		if ( isset( $wpcpm_seed_by_id[ $cid ] ) && ! $wpcpm_seed_by_id[ $cid ]['has_contact'] ) {
			$wpcpm_seed_unrouted[ $cid ] = true;
		}
	}
}

echo wp_json_encode(
	array(
		'_comment'           => 'The Institutions table (tbl4V0FEbzRP7I2w2) and the Countries table (tbltB7GSRoTtSi4Ps) as read from the base on ' . gmdate( 'Y-m-d' ) . ', with every personal field removed: no contact person, no contact email, no department, no prose, no manager names or addresses (countries carry only whether a contact, an email and a Calendly link exist). Institution names, cities and websites are the organisations\' public facts. The record recDdomg5W6h410JT is the TEST institution. Seeds the pipeline index in tests; refresh with bin/dump-institutions-seed.php or by hand when the base changes, and update the counts in bin/test-institutions-index.php with it.',
		'read'               => gmdate( 'Y-m-d' ),
		'institutions_table' => 'tbl4V0FEbzRP7I2w2',
		'countries_table'    => 'tbltB7GSRoTtSi4Ps',
		'counts'             => array(
			'institutions'                                 => count( $wpcpm_seed_rows ),
			'by_stage'                                     => $wpcpm_seed_stages,
			'created_before_consent_question'              => $wpcpm_seed_pre,
			'created_before_consent_question_confirmed'    => $wpcpm_seed_pre_c,
			'created_before_consent_question_with_consent' => $wpcpm_seed_pre_t,
			'trailing_space_names'                         => $wpcpm_seed_trail,
			'nameless'                                     => $wpcpm_seed_blank,
			'countries'                                    => count( $wpcpm_seed_country_rows ),
			'countries_without_contact'                    => $wpcpm_seed_no_contact,
			'countries_used_by_institutions'               => count( $wpcpm_seed_used ),
			'countries_used_without_contact'               => count( $wpcpm_seed_unrouted ),
		),
		'institutions'       => $wpcpm_seed_rows,
		'countries'          => $wpcpm_seed_country_rows,
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) . "\n";
