<?php
/**
 * The states the pictures need that no form of the site makes in one sitting, made on the local copy only by
 * `bin/ui/seed.mjs` after its forms (`wp eval-file seed-states.php <json>`): invented items through the
 * plugin's own classes where one offers a door, and post meta copied from an invented item the forms made
 * where none does. Nothing here mails anyone outside the copy (it swallows mail), starts a sync or writes to
 * a sponsor's or school's real record beyond the copy's database, which the snapshot ui-ready keeps and
 * copy.sh restore puts back. Prints one line per state, `made <state>` or `not made <state>: <why>`, and the
 * last line is a JSON list of the states made.
 *
 * The argument is a JSON object: `schools` (name => record ID of the invented schools the forms approved),
 * `apps` (name => application post ID), `logos` (`colour` and `white`: paths under wp-content of two
 * pictures seed.mjs wrote), `icons` (a list of `school`, `record` and `picture`: the path under wp-content each
 * school's site icon is read from, one a picture seed.mjs wrote and one no file at all), `admin` (the copy's
 * Administrator's login) and `rep` (a representative's login).
 *
 * @package WPCreditsProgramManager
 */

if ( 'http://localhost:8900' !== (string) get_option( 'siteurl' ) || 'http://localhost:8900' !== (string) get_option( 'home' ) || ! defined( 'WPCPM_UI_RIG' ) ) {
	echo "refusing: not the prepared local copy\n";
	return;
}

$in    = json_decode( isset( $args[0] ) ? (string) $args[0] : '{}', true );
$in    = is_array( $in ) ? $in : array();
// `only`, a list of section names, makes those sections alone (a rerun of one part on a seeded copy).
$run   = static function ( $section ) use ( $in ) {
	return empty( $in['only'] ) || in_array( $section, (array) $in['only'], true );
};
$made  = array();
$admin = get_user_by( 'login', isset( $in['admin'] ) ? (string) $in['admin'] : '' );
$aid   = $admin ? (int) $admin->ID : 0;
wp_set_current_user( $aid );

$say = static function ( $state, $result ) use ( &$made ) {
	if ( true === $result || ( is_int( $result ) && $result > 0 ) ) {
		$made[] = $state;
		echo "made {$state}\n";
	} else {
		$why = is_wp_error( $result ) ? $result->get_error_code() : ( is_string( $result ) ? $result : 'refused' );
		echo "not made {$state}: {$why}\n";
	}
};

/** A copy of one post with every meta of the plugin's, a few changed; answers the new ID or 0. */
$copy = static function ( $id, array $post = array(), array $meta = array(), array $drop = array() ) {
	$from = get_post( (int) $id );
	if ( ! $from instanceof WP_Post ) {
		return 0;
	}
	$new = wp_insert_post(
		wp_slash(
			array_merge(
				array(
					'post_type'    => $from->post_type,
					'post_status'  => $from->post_status,
					'post_author'  => $from->post_author,
					'post_title'   => $from->post_title,
					'post_content' => $from->post_content,
				),
				$post
			)
		),
		true
	);
	if ( is_wp_error( $new ) || ! $new ) {
		return 0;
	}
	foreach ( get_post_meta( $from->ID ) as $key => $values ) {
		if ( 0 !== strpos( $key, '_wpcpm' ) || in_array( $key, $drop, true ) || array_key_exists( $key, $meta ) ) {
			continue;
		}
		foreach ( $values as $v ) {
			add_post_meta( $new, $key, wp_slash( maybe_unserialize( $v ) ) );
		}
	}
	foreach ( $meta as $key => $v ) {
		update_post_meta( $new, $key, wp_slash( $v ) );
	}
	return (int) $new;
};

$days_ago = static function ( $n ) {
	$t = time() - (int) $n * DAY_IN_SECONDS;
	return array(
		'post_date'     => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $t ) ),
		'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $t ),
	);
};

$schools = isset( $in['schools'] ) && is_array( $in['schools'] ) ? $in['schools'] : array();
$apps    = isset( $in['apps'] ) && is_array( $in['apps'] ) ? $in['apps'] : array();
$design  = isset( $schools['Example School of Design'] ) ? (string) $schools['Example School of Design'] : '';
$inst    = isset( $schools['Example Institute of Technology'] ) ? (string) $schools['Example Institute of Technology'] : '';
$comm    = isset( $schools['Example Community College'] ) ? (string) $schools['Example Community College'] : '';
$univ    = isset( $schools['Example University'] ) ? (string) $schools['Example University'] : '';
// Gate 4: an unbroken word of 40 characters in each field a long value can widen a card with (invented, naming
// nobody): no text a part prints may make its card wider than its container at any width.
$long = array(
	'institution' => 'Exampleinstituteforappliedwebengineering',
	'company'     => 'Examplestudentwebsitehostingcooperatives',
	'event'       => 'Examplecontributorworkshopforfirsttimers',
	'post'        => 'Exampleguidetohostingstudentportfolioson',
	'offer'       => 'Exampleyearlonghostingplanforstudentsite',
	'code'        => 'EXAMPLEADDCODEFORTYCHARACTERSLONG0000004',
	'request'     => 'Exampleparttimeformatfromthenextsemester',
	'returned'    => 'Examplepleaseuploadallfivesignedpagesnow',
	'website'     => 'examplecollegeofdesignandappliedartsnows',
	'person'      => 'Examplerepresentativeofthedesignfacultys',
	'error'       => 'Examplerecordidentifierthatnevermatches0',
);

// ------------------------------------------------------------------ institution applications
if ( $run( 'applications' ) ) :
$waiting = isset( $apps['Example State College'] ) ? (int) $apps['Example State College'] : 0;
$app     = static function ( $name, $city, $email, array $meta, array $drop ) use ( $copy, $waiting ) {
	$fields = get_post_meta( $waiting, WPCPM_Institution_Application::META_FIELDS, true );
	if ( ! is_array( $fields ) ) {
		return 0;
	}
	foreach ( $fields as $k => $v ) {
		if ( 'Example State College' === $v ) {
			$fields[ $k ] = $name;
		} elseif ( 'Exampleford' === $v ) {
			$fields[ $k ] = $city;
		} elseif ( 'alex.example@example.org' === $v ) {
			$fields[ $k ] = $email;
		} elseif ( 'https://state.example.org' === $v || 'state.example.org' === $v ) {
			$fields[ $k ] = 'https://' . sanitize_title( $name ) . '.example.org';
		}
	}
	$meta[ WPCPM_Institution_Application::META_FIELDS ] = $fields;
	$meta[ WPCPM_Institution_Application::META_EMAIL ]  = $email;
	return $copy( $waiting, array( 'post_title' => $name ), $meta, array_merge( $drop, array( WPCPM_Institution_Application::META_REFERENCE, WPCPM_Institution_Application::META_RECORD, WPCPM_Institution_Application::META_USER ) ) );
};
if ( $waiting ) {
	$say( 'application-unconfirmed', $app( 'Example Polytechnic', 'Exampleburg', 'casey.example@example.org', array(), array( WPCPM_Institution_Application::META_VERIFIED ) ) );
	$say( 'application-held', $app( 'Example Academy of Arts', 'Examplefield', 'morgan.example@example.org', array( WPCPM_Institution_Application::META_STATE => WPCPM_Institution_Application::STATE_HELD ), array() ) );
	$say( 'application-rejected', $app( 'Example Technical School', 'Examplehaven', 'riley.example@example.org', array( WPCPM_Institution_Application::META_STATE => WPCPM_Institution_Application::STATE_REJECTED ), array() ) );
	// Gate 4: a waiting application whose name, website and email each hold the 40-character word.
	$say( 'application-long-word', $app( $long['institution'], 'Exampleport', strtolower( $long['institution'] ) . '@example.org', array(), array() ) );
} else {
	$say( 'applications', 'the waiting application is not on the copy' );
}

endif;

// ------------------------------------------------------------------ Collaboration Agreements
if ( $run( 'agreements' ) ) :
$signed = get_posts(
	array(
		'post_type'   => WPCPM_Institution_Agreement::POST_TYPE,
		'post_status' => 'any',
		'numberposts' => 1,
		'fields'      => 'ids',
		'meta_query'  => array(
			array( 'key' => WPCPM_Institution_Agreement::META_INSTITUTION, 'value' => $univ ),
			array( 'key' => WPCPM_Institution_Agreement::META_STATE, 'value' => WPCPM_Institution_Agreement::STATE_SUBMITTED ),
		),
	)
);
$signed = $signed ? (int) $signed[0] : 0;
if ( $signed && $inst && $comm ) {
	$title = static function ( $record ) {
		return 'Signed Collaboration Agreement (' . $record . ')';
	};
	$say( 'agreement-overdue', $copy( $signed, array_merge( array( 'post_title' => $title( $inst ) ), $days_ago( 9 ) ), array( WPCPM_Institution_Agreement::META_INSTITUTION => $inst ) ) );
	$say(
		'agreement-revoked',
		$copy(
			$signed,
			array_merge( array( 'post_title' => $title( $comm ) ), $days_ago( 40 ) ),
			array(
				WPCPM_Institution_Agreement::META_INSTITUTION => $comm,
				WPCPM_Institution_Agreement::META_STATE       => WPCPM_Institution_Agreement::STATE_REVOKED,
				WPCPM_Institution_Agreement::META_NOTE        => 'Revoked at the school\'s request: the signer has left the faculty.',
				WPCPM_Institution_Agreement::META_DECIDED_BY  => $aid,
				WPCPM_Institution_Agreement::META_DECIDED_AT  => gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS ),
			)
		)
	);
	$say(
		'agreement-returned',
		$copy(
			$signed,
			array_merge( array( 'post_title' => $title( $comm ) ), $days_ago( 5 ) ),
			array(
				WPCPM_Institution_Agreement::META_INSTITUTION => $comm,
				WPCPM_Institution_Agreement::META_STATE       => WPCPM_Institution_Agreement::STATE_RETURNED,
				WPCPM_Institution_Agreement::META_NOTE        => "The signature page is missing: the document we received ends at page four, before the signatures.\n\nPlease scan all five pages, the signed page included, and upload the whole document again from your dashboard.",
				WPCPM_Institution_Agreement::META_DECIDED_BY  => $aid,
				WPCPM_Institution_Agreement::META_DECIDED_AT  => gmdate( 'Y-m-d', time() - 2 * DAY_IN_SECONDS ),
			)
		)
	);
	// Gate 3: three returned and five revoked rows in the two inner folds, some with notes and none on some,
	// newest first (the card orders them by their last edit): the returned fold's oldest row is the noted one
	// above (5 days), and the revoked fold's oldest row (60 days) carries a short note, so a noted row is last.
	$more = array(
		array( 'returned-2', WPCPM_Institution_Agreement::STATE_RETURNED, $inst, 3, '', 1 ),
		array( 'returned-3', WPCPM_Institution_Agreement::STATE_RETURNED, $design, 1, 'The scan is too faint to read: please upload a clearer copy. ' . $long['returned'] . '.', 0 ),
		array( 'revoked-2', WPCPM_Institution_Agreement::STATE_REVOKED, $inst, 20, '', 15 ),
		array( 'revoked-3', WPCPM_Institution_Agreement::STATE_REVOKED, $design, 30, 'Revoked when the faculty merged with another; a new agreement is on its way.', 25 ),
		array( 'revoked-4', WPCPM_Institution_Agreement::STATE_REVOKED, $univ, 50, '', 45 ),
		array( 'revoked-5', WPCPM_Institution_Agreement::STATE_REVOKED, $inst, 60, 'Signed by a person who could not commit the school.', 55 ),
	);
	foreach ( $more as $m ) {
		$meta = array(
			WPCPM_Institution_Agreement::META_INSTITUTION => $m[2],
			WPCPM_Institution_Agreement::META_STATE       => $m[1],
			WPCPM_Institution_Agreement::META_DECIDED_BY  => $aid,
			WPCPM_Institution_Agreement::META_DECIDED_AT  => gmdate( 'Y-m-d', time() - $m[5] * DAY_IN_SECONDS ),
		);
		if ( '' !== $m[4] ) {
			$meta[ WPCPM_Institution_Agreement::META_NOTE ] = $m[4];
		}
		$say( 'agreement-' . $m[0], $copy( $signed, array_merge( array( 'post_title' => $title( $m[2] ) ), $days_ago( $m[3] ) ), $meta, '' === $m[4] ? array( WPCPM_Institution_Agreement::META_NOTE ) : array() ) );
	}
} else {
	$say( 'agreements', 'the signed agreement or the two approved schools are not on the copy' );
}

endif;

// ------------------------------------------------------------------ requests from institutions
if ( $run( 'requests' ) ) :
$open = WPCPM_Institution_Request::open_requests( 50 );
if ( $open ) {
	$t = time() - 20 * DAY_IN_SECONDS;
	$r = wp_update_post( array( 'ID' => (int) $open[0], 'post_date' => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $t ) ), 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $t ) ), true );
	$say( 'request-overdue', is_wp_error( $r ) ? $r : (int) $r );
	$facts = WPCPM_Institution_Request::facts( (int) $open[0] );
	$student = isset( $facts['student'] ) ? (string) $facts['student'] : (string) get_post_meta( (int) $open[0], WPCPM_Institution_Request::META_STUDENT, true );
	$raised  = WPCPM_Institution_Request::raise( WPCPM_Institution_Request::KIND_MENTOR, $design, $student, $aid );
	if ( is_wp_error( $raised ) ) {
		$say( 'request-closed', $raised );
	} else {
		$settled = WPCPM_Institution_Request::settle( (int) $raised, WPCPM_Institution_Request::STATE_DONE, 'A mentor is assigned.', $aid );
		$say( 'request-closed', is_wp_error( $settled ) ? $settled : ( false === $settled ? 'refused' : true ) );
		// Gate 3: four more closed, so Recently closed lists five, some with a note; the first closed (above, with
		// its note) is the list's last row.
		foreach ( array( array( WPCPM_Institution_Request::KIND_FORMAT, WPCPM_Institution_Request::STATE_DONE, '' ), array( WPCPM_Institution_Request::KIND_MENTOR, WPCPM_Institution_Request::STATE_DECLINED, 'The student is already on the track with another school.' ), array( WPCPM_Institution_Request::KIND_MENTOR, WPCPM_Institution_Request::STATE_DONE, '' ), array( WPCPM_Institution_Request::KIND_FORMAT, WPCPM_Institution_Request::STATE_DONE, 'The format is changed to part time from the next semester: ' . $long['request'] . '.' ) ) as $i => $c ) {
			$r = WPCPM_Institution_Request::raise( $c[0], $design, $student, $aid );
			$s = is_wp_error( $r ) ? $r : WPCPM_Institution_Request::settle( (int) $r, $c[1], $c[2], $aid );
			$say( 'request-closed-' . ( $i + 2 ), is_wp_error( $s ) ? $s : ( false === $s ? 'refused' : true ) );
		}
	}
} else {
	$say( 'request-overdue', 'no open request on the copy' );
}
if ( $design ) {
	$changes = WPCPM_Institution_Details::changes( $design, array( 'website' => 'https://www.' . $long['website'] . '.example.org', 'city' => 'Hillford Bay' ) );
	$say( 'request-details-two-changes', 2 === count( $changes ) ? WPCPM_Institution_Request::raise_details( $design, $changes, $aid ) : 'the details class saw ' . count( $changes ) . ' changes' );
}

endif;

// ------------------------------------------------------------------ semester reports
if ( $run( 'reports' ) ) :
$draft = get_posts( array( 'post_type' => WPCPM_Semester_Report::POST_TYPE, 'post_status' => 'private', 'numberposts' => 1, 'fields' => 'ids' ) );
if ( $draft ) {
	$say(
		'report-approved',
		$copy(
			(int) $draft[0],
			array( 'post_title' => 'Semester report: Example School of Design, July to December 2025' ),
			array(
				WPCPM_Semester_Report::META_COHORT   => '2025-H2',
				WPCPM_Semester_Report::META_STATE    => WPCPM_Semester_Report::STATE_APPROVED,
				WPCPM_Semester_Report::META_APPROVED => array( 'at' => time() - 3 * DAY_IN_SECONDS, 'by' => $aid ),
			)
		)
	);
	// Gate 6: a draft waiting for review that is 441 days old, so the Age column holds "441 days" (an earlier
	// semester of the same school, only its stored drafting time moved back).
	$say(
		'report-441-days',
		$copy(
			(int) $draft[0],
			array( 'post_title' => 'Semester report: Example School of Design, January to June 2025' ),
			array(
				WPCPM_Semester_Report::META_COHORT    => '2025-H1',
				WPCPM_Semester_Report::META_STATE     => WPCPM_Semester_Report::STATE_DRAFT,
				WPCPM_Semester_Report::META_GENERATED => time() - 441 * DAY_IN_SECONDS,
			),
			array( WPCPM_Semester_Report::META_APPROVED )
		)
	);
} else {
	$say( 'report-approved', 'no drafted report on the copy' );
}

endif;

// ------------------------------------------------------------------ sponsors (the copy's own, scrubbed and renamed)
$approved = class_exists( 'WPCPM_Sponsors_Index' ) ? array_keys( WPCPM_Sponsors_Index::approved() ) : array();
$offers   = class_exists( 'WPCPM_Sponsor_Offers' ) ? WPCPM_Sponsor_Offers::all() : array();
$pick     = static function ( $test ) use ( $offers ) {
	foreach ( $offers as $o ) {
		if ( $test( $o ) ) {
			return $o;
		}
	}
	return null;
};
// The sponsor of the accepted agreement is the Sponsor Dashboard's; the others are the ones changed below.
$agr = get_posts( array( 'post_type' => WPCPM_Sponsor_Agreement::POST_TYPE, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => WPCPM_Sponsor_Agreement::META_STATE, 'value' => WPCPM_Sponsor_Agreement::STATE_ACCEPTED ) ) ) );
$from_sponsor = $agr ? (string) get_post_meta( (int) $agr[0], WPCPM_Sponsor_Agreement::META_SPONSOR, true ) : '';
$others       = array_values( array_diff( $approved, array( $from_sponsor ) ) );

if ( $run( 'sponsors' ) ) :
$new_offer = $pick(
	static function ( $o ) {
		return WPCPM_Sponsor_Offers::STATE_DRAFT === $o['state'] && WPCPM_Sponsor_Offers::KIND_CODES === $o['kind'];
	}
);
if ( $new_offer ) {
	WPCPM_Sponsor_Offers::add_codes( (int) $new_offer['id'], "EXAMPLE-NEW-0001\nEXAMPLE-NEW-0002\nEXAMPLE-NEW-0003", 0 );
	// Gate 4: the offer's title holds the 40-character word.
	$say( 'offer-new-long-title', WPCPM_Sponsor_Offers::save( (int) $new_offer['id'], array( 'title' => $long['offer'] . ' for students' ) ) );
	$r = WPCPM_Sponsor_Offers::submit( (int) $new_offer['id'], $aid );
	$say( 'offer-new-to-review', is_wp_error( $r ) ? $r : true );
}
$live_offer = $pick(
	static function ( $o ) {
		return WPCPM_Sponsor_Offers::STATE_LIVE === $o['state'] && WPCPM_Sponsor_Offers::KIND_CODES === $o['kind'] && WPCPM_Sponsor_Codes::counts( (int) $o['id'] )['available'] >= 10;
	}
);
if ( $live_offer ) {
	$r = WPCPM_Sponsor_Offers::propose( (int) $live_offer['id'], array( 'title' => 'One year of hosting for a student project', 'text' => 'A year of hosting for one site, with a domain, for a project a student builds on the track.' ), $aid );
	$c = WPCPM_Sponsor_Offers::add_codes( (int) $live_offer['id'], "EXAMPLE-ADD-0001\nEXAMPLE-ADD-0002\nhttps://checkout.example.com/claim/EXAMPLE-ADD-0003\n" . $long['code'], $aid );
	$say( 'offer-changes-and-codes', is_wp_error( $r ) ? $r : ( is_wp_error( $c ) ? $c : true ) );
}

if ( $agr && count( $others ) >= 4 ) {
	$say( 'sponsor-agreement-waiting', $copy( (int) $agr[0], $days_ago( 1 ), array( WPCPM_Sponsor_Agreement::META_SPONSOR => $others[0], WPCPM_Sponsor_Agreement::META_STATE => WPCPM_Sponsor_Agreement::STATE_SUBMITTED ), array( WPCPM_Sponsor_Agreement::META_DECIDED_BY, WPCPM_Sponsor_Agreement::META_DECIDED_AT ) ) );
	$say( 'sponsor-agreement-revoked', $copy( (int) $agr[0], $days_ago( 50 ), array( WPCPM_Sponsor_Agreement::META_SPONSOR => $others[1], WPCPM_Sponsor_Agreement::META_STATE => WPCPM_Sponsor_Agreement::STATE_REVOKED, WPCPM_Sponsor_Agreement::META_NOTE => 'The sponsorship ended early, at the company\'s request.', WPCPM_Sponsor_Agreement::META_DECIDED_BY => $aid, WPCPM_Sponsor_Agreement::META_DECIDED_AT => gmdate( 'Y-m-d', time() - 20 * DAY_IN_SECONDS ) ) ) );
	// Gate 3: two more out of force, each of an invented company the index holds In review (in no sponsor list of
	// the program): one named in 60 characters, one whose name holds an unbroken word of 40.
	foreach ( array( 'long' => array( 'Example Northern Coast Cloud Hosting and Domains Cooperative', 45 ), 'word' => array( 'Examplecloudhostinganddomainregistration Ltd', 35 ) ) as $k => $c ) {
		$row              = WPCPM_Sponsors_Index::empty_row();
		$row['record_id'] = WPCPM_Sponsors_Index::mint_key();
		$row['name']      = $c[0];
		$row['website']   = 'https://' . $k . '-name.example.com';
		$row['status']    = WPCPM_Sponsors::STATUS_ADDED;
		// Gate 4: the 60-character name's row was uploaded by the representative whose name holds the 40-character word.
		$uploader = 'long' === $k ? get_user_by( 'login', isset( $in['rep'] ) ? (string) $in['rep'] : '' ) : null;
		$say( 'sponsor-agreement-revoked-' . $k . '-name', WPCPM_Sponsors_Index::insert( $row ) ? $copy( (int) $agr[0], array_merge( $days_ago( $c[1] ), $uploader ? array( 'post_author' => (int) $uploader->ID ) : array() ), array( WPCPM_Sponsor_Agreement::META_SPONSOR => $row['record_id'], WPCPM_Sponsor_Agreement::META_STATE => WPCPM_Sponsor_Agreement::STATE_REVOKED, WPCPM_Sponsor_Agreement::META_DECIDED_BY => $aid, WPCPM_Sponsor_Agreement::META_DECIDED_AT => gmdate( 'Y-m-d', time() - 10 * DAY_IN_SECONDS ) ), array( WPCPM_Sponsor_Agreement::META_NOTE ) ) : 'the index did not take the row' );
	}
} else {
	$say( 'sponsor-agreements', 'no accepted sponsor agreement, or too few sponsors, on the copy' );
}

// Gate 4: the first sponsor post waiting for review holds the 40-character word in its title.
$waiting_posts = class_exists( 'WPCPM_Sponsor_Posts' ) ? WPCPM_Sponsor_Posts::pending_all( 1 ) : array();
if ( $waiting_posts ) {
	$r = wp_update_post( array( 'ID' => (int) $waiting_posts[0]['id'], 'post_title' => $long['post'] . ' our servers' ), true );
	$say( 'sponsor-post-long-title', is_wp_error( $r ) ? $r : (int) $r );
} else {
	$say( 'sponsor-post-long-title', 'no sponsor post waits on the copy' );
}

// Tiers on two sponsors' rows: one whose term ended in June with no report (a report due), one ending in
// December (a term ending). The tier is the lowest one whose benefits hold the program's report.
$tier = '';
foreach ( array_keys( WPCPM_Sponsor_Tiers::labels() ) as $k ) {
	if ( WPCPM_Sponsor_Tiers::includes( $k, 'report_program' ) && ( '' === $tier || WPCPM_Sponsor_Tiers::includes( $k, 'events' ) ) ) {
		$tier = $k;
		if ( WPCPM_Sponsor_Tiers::includes( $k, 'events' ) ) {
			break;
		}
	}
}
$set_row = static function ( $record, array $values ) {
	$posts = get_posts( array( 'post_type' => WPCPM_Sponsors_Index::POST_TYPE, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => WPCPM_Sponsors_Index::META_KEY, 'meta_value' => $record ) );
	if ( ! $posts ) {
		return 'no row';
	}
	$row = get_post_meta( (int) $posts[0], WPCPM_Sponsors_Index::META_ROW, true );
	if ( ! is_array( $row ) ) {
		return 'no row';
	}
	update_post_meta( (int) $posts[0], WPCPM_Sponsors_Index::META_ROW, wp_slash( array_merge( $row, $values ) ) );
	wp_cache_flush();
	return true;
};
if ( '' !== $tier && count( $others ) >= 4 ) {
	$say( 'sponsor-report-due', $set_row( $others[2], array( 'tier' => $tier, 'tier_since' => '2025-07-01', 'tier_until' => '2026-06-30' ) ) );
	$say( 'sponsor-term-ending', $set_row( $others[3], array( 'tier' => $tier, 'tier_since' => '2026-01-01', 'tier_until' => '2026-12-31' ) ) );
} else {
	$say( 'sponsor-tiers', 'no tier holds the program report' );
}
$final = get_posts( array( 'post_type' => WPCPM_Sponsor_Report::POST_TYPE, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
if ( $final && isset( $others[0] ) ) {
	$say( 'sponsor-report-draft', $copy( (int) $final[0], array(), array( WPCPM_Sponsor_Report::META_SPONSOR => $others[0], WPCPM_Sponsor_Report::META_STATE => WPCPM_Sponsor_Report::STATE_DRAFT, WPCPM_Sponsor_Report::META_UNTIL => '2026-06-30' ), array( WPCPM_Sponsor_Report::META_FINAL, WPCPM_Sponsor_Report::META_FILE, WPCPM_Sponsor_Report::META_FILE_NAME ) ) );
} else {
	$say( 'sponsor-report-draft', 'no sponsor report on the copy' );
}

// An interest from a sponsor, a message of two lines.
if ( isset( $others[0] ) ) {
	$r = WPCPM_Institution_Audit::record_sponsor(
		array(
			'kind'     => WPCPM_Sponsor_Interests::LOG_KIND,
			'sponsor'  => $others[0],
			'subject'  => $others[0],
			'actor'    => $aid,
			'ground'   => WPCPM_Sponsor_Policy::GROUND_MANAGER,
			'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
			'message'  => "Interested in: a co-hosted event, the track badge.\nWe would like to run a workshop for students in the spring.",
			'data'     => array( 'choices' => array( 'events', 'badge' ), 'events' => 0, 'mailed' => 0 ),
		)
	);
	$say( 'sponsor-interest-two-lines', is_wp_error( $r ) ? $r : (int) $r );
}

endif;

// ------------------------------------------------------------------ sponsor applications
if ( $run( 'sponsor-apps' ) ) :
$logo = static function ( $rel, $title ) {
	$file = WP_CONTENT_DIR . '/' . ltrim( (string) $rel, '/' );
	if ( ! is_file( $file ) ) {
		return 0;
	}
	$id = wp_insert_attachment( array( 'post_title' => $title, 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $file );
	if ( is_wp_error( $id ) || ! $id ) {
		return 0;
	}
	// Its size, written by hand: generating the metadata would make thumbnail files nothing records for restore.
	$size = getimagesize( $file );
	wp_update_attachment_metadata( (int) $id, array( 'width' => $size ? (int) $size[0] : 0, 'height' => $size ? (int) $size[1] : 0, 'file' => ltrim( substr( $file, strlen( wp_get_upload_dir()['basedir'] ) ), '/' ), 'sizes' => array() ) );
	return (int) $id;
};
$logos  = isset( $in['logos'] ) && is_array( $in['logos'] ) ? $in['logos'] : array();
$sapp   = static function ( $company, $person, $email, $state, array $logo_ids, array $signals ) use ( $days_ago ) {
	$fields = array(
		WPCPM_Sponsor_Application::COL_NAME     => $company,
		WPCPM_Sponsor_Application::COL_WEBSITE  => 'https://' . sanitize_title( $company ) . '.example.com',
		WPCPM_Sponsor_Application::COL_PERSON   => $person,
		WPCPM_Sponsor_Application::COL_EMAIL    => $email,
		WPCPM_Sponsor_Application::COL_ANYTHING => 'We host many WordPress sites and would like to give students a place to build theirs.',
		WPCPM_Sponsor_Application::COL_CONSENT  => 'Agreed',
	);
	$id = wp_insert_post( wp_slash( array_merge( array( 'post_type' => WPCPM_Sponsor_Application::POST_TYPE, 'post_status' => 'private', 'post_title' => $company ), $days_ago( 'held' === $state ? 6 : 2 ) ) ), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	update_post_meta( $id, WPCPM_Sponsor_Application::META_FIELDS, wp_slash( $fields ) );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, $state );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_EMAIL, $email );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_SIGNALS, $signals );
	if ( $logo_ids ) {
		update_post_meta( $id, WPCPM_Sponsor_Application::META_LOGOS, $logo_ids );
	}
	return (int) $id;
};
$colour = $logo( isset( $logos['colour'] ) ? $logos['colour'] : '', 'Example Hosting logo, in colour' );
$white  = $logo( isset( $logos['white'] ) ? $logos['white'] : '', 'Example Hosting logo, in white' );
$say( 'sponsor-application-waiting-logos', $sapp( 'Example Hosting Co', 'Jamie Example', 'jamie.example@example.com', WPCPM_Sponsor_Application::STATE_NEW, $colour && $white ? array( 'colour' => $colour, 'white' => $white ) : array(), array( WPCPM_Sponsor_Application::SIGNAL_IN_BASE ) ) );
if ( ! ( $colour && $white ) ) {
	$say( 'sponsor-application-logos', 'the logo pictures were not on the copy' );
}
$say( 'sponsor-application-held', $sapp( 'Example Plugins Ltd', 'Taylor Sample', 'taylor.sample@example.com', WPCPM_Sponsor_Application::STATE_HELD, array(), array() ) );
// Gate 4: a waiting application with no logo and no match, whose company, website and email hold the 40-character word.
$say( 'sponsor-application-long-word', $sapp( $long['company'] . ' Ltd', 'Casey Example', strtolower( $long['company'] ) . '@example.com', WPCPM_Sponsor_Application::STATE_NEW, array(), array() ) );
// A sponsor the site already holds on the waiting application's website host, so "What the site already has"
// lists a match: an invented row in the sponsors index, In review (never Approved: it is in no sponsor list of
// the program), written through the index's own insert().
$match = WPCPM_Sponsors_Index::empty_row();
$match['record_id'] = WPCPM_Sponsors_Index::mint_key();
$match['name']      = 'Example Hosting Company';
$match['website']   = 'https://example-hosting-co.example.com';
$match['status']    = WPCPM_Sponsors::STATUS_ADDED;
$say( 'sponsor-application-matching-sponsor', WPCPM_Sponsors_Index::insert( $match ) && 1 === count( WPCPM_Sponsor_Application::index_matches( 'Example Hosting Co', 'https://example-hosting-co.example.com' ) ) ? true : 'the index did not take the row' );
// Gate 3: two more matches, so the list has three rows: one by the same name at another address, one at the
// same host written another way.
foreach ( array( array( 'Example Hosting Co', 'https://hosting-co-old.example.com' ), array( 'Example Hosting Collective', 'www.example-hosting-co.example.com/about' ) ) as $i => $c ) {
	$m              = WPCPM_Sponsors_Index::empty_row();
	$m['record_id'] = WPCPM_Sponsors_Index::mint_key();
	$m['name']      = $c[0];
	$m['website']   = $c[1];
	$m['status']    = WPCPM_Sponsors::STATUS_ADDED;
	$say( 'sponsor-application-matching-sponsor-' . ( $i + 2 ), WPCPM_Sponsors_Index::insert( $m ) && $i + 2 === count( WPCPM_Sponsor_Application::index_matches( 'Example Hosting Co', 'https://example-hosting-co.example.com' ) ) ? true : 'the index did not take the row, or it does not match' );
}

endif;

// ------------------------------------------------------------------ events
// A call link is checked as core checks a request's address, which resolves its host in DNS: the copy has
// none, so for these three the copy's own name stands for an outside host, in this run only.
add_filter(
	'http_request_host_is_external',
	static function ( $external, $host ) {
		return 'localhost' === $host ? true : $external;
	},
	10,
	2
);
if ( $run( 'events' ) && class_exists( 'WPCPM_Sponsor_Event' ) ) {
	$start  = static function ( $days ) {
		return (int) ( floor( ( time() + $days * DAY_IN_SECONDS ) / HOUR_IN_SECONDS ) * HOUR_IN_SECONDS );
	};
	$fields = static function ( $title, $days ) use ( $start ) {
		return array(
			'title'       => $title,
			'description' => "An hour on how a first contribution is reviewed.\nBring a patch or a question.",
			'start'       => $start( $days ),
			'minutes'     => 60,
			'places'      => 30,
			'link'        => 'http://localhost/call/' . sanitize_title( $title ),
			'zone'        => 'UTC',
		);
	};
	$own = WPCPM_Sponsor_Event::plan_program( $fields( 'Office hours: your first patch', 9 ), $aid );
	$say( 'event-program-own', is_wp_error( $own ) ? $own : (int) $own );
	// Gate 4: the program's own event whose title, and so its call link, holds the 40-character word.
	$lw = WPCPM_Sponsor_Event::plan_program( $fields( $long['event'] . ', in one hour', 20 ), $aid );
	$say( 'event-program-long-title', is_wp_error( $lw ) ? $lw : (int) $lw );
	$as_sponsor = static function ( $id, $record, $state ) {
		update_post_meta( (int) $id, WPCPM_Sponsor_Event::META_KIND, WPCPM_Sponsor_Event::KIND_CO_HOSTED );
		update_post_meta( (int) $id, WPCPM_Sponsor_Event::META_SPONSOR, $record );
		update_post_meta( (int) $id, WPCPM_Sponsor_Event::META_STATE, $state );
		return (int) $id;
	};
	// The Administrator is on the program's own event, so the card shows "You are on it." and Leave; the
	// sponsor's approved event below stays open to Join.
	if ( ! is_wp_error( $own ) && $own ) {
		$mine = WPCPM_Sponsor_Event::join( get_post( (int) $own ), $aid );
		$say( 'event-program-own-joined-by-administrator', is_wp_error( $mine ) ? $mine : true );
	}
	if ( isset( $others[0] ) ) {
		// Waiting: one whose sponsor has no tier with events (no Approve is offered), and, when the sponsor
		// whose term is ending holds one, one of its own whose Approve is offered.
		$w = WPCPM_Sponsor_Event::plan_program( $fields( 'Hosting a student site, step by step', 12 ), $aid );
		$say( 'event-sponsor-waiting', is_wp_error( $w ) ? $w : $as_sponsor( $w, $others[0], WPCPM_Sponsor_Event::STATE_PLANNED ) );
		if ( isset( $others[3] ) ) {
			$wt = WPCPM_Sponsor_Event::plan_program( $fields( 'Reviewing a theme for the directory', 15 ), $aid );
			$say( 'event-sponsor-waiting-with-events-tier', is_wp_error( $wt ) ? $wt : $as_sponsor( $wt, $others[3], WPCPM_Sponsor_Event::STATE_PLANNED ) );
		}
		$a = WPCPM_Sponsor_Event::plan_program( $fields( 'Accessibility checks in practice', 6 ), $aid );
		if ( is_wp_error( $a ) ) {
			$say( 'event-sponsor-approved-joined', $a );
		} else {
			// The sponsor whose term is ending below holds a tier that offers events, so its event can be joined.
			$as_sponsor( $a, $others[3], WPCPM_Sponsor_Event::STATE_PLANNED );
			$ok = WPCPM_Sponsor_Event::approve( get_post( $a ), $aid );
			if ( is_wp_error( $ok ) ) {
				$say( 'event-sponsor-approved', $ok );
			}
			$joiner = get_user_by( 'email', isset( $in['joiner'] ) ? (string) $in['joiner'] : '' );
			// The invented students have no account yet: the first scrubbed account the event is open to joins.
			if ( ! $joiner ) {
				foreach ( get_users( array( 'number' => 300, 'search' => '*@example.test', 'search_columns' => array( 'user_email' ), 'orderby' => 'ID' ) ) as $u ) {
					if ( '' !== (string) WPCPM_Sponsor_Event::audience_of( $u ) && ! user_can( $u, WPCPM_Roles::CAP_MANAGE ) ) {
						$joiner = $u;
						break;
					}
				}
			}
			$j      = $joiner ? WPCPM_Sponsor_Event::join( get_post( $a ), (int) $joiner->ID ) : 'no account to join with';
			$say( 'event-sponsor-approved-joined', is_wp_error( $j ) ? $j : ( is_string( $j ) ? $j : true ) );
			// Gate 3: Who joined with five people on this event and three on the program's own (the Administrator and
			// two more): the next scrubbed accounts the events are open to.
			$more = array();
			foreach ( get_users( array( 'number' => 300, 'search' => '*@example.test', 'search_columns' => array( 'user_email' ), 'orderby' => 'ID' ) ) as $u ) {
				if ( count( $more ) >= 4 ) {
					break;
				}
				if ( ( ! $joiner || (int) $u->ID !== (int) $joiner->ID ) && '' !== (string) WPCPM_Sponsor_Event::audience_of( $u ) && ! user_can( $u, WPCPM_Roles::CAP_MANAGE ) ) {
					$more[] = $u;
				}
			}
			// Gate 4: the representative whose name holds the 40-character word is on Who joined too.
			$long_rep = get_user_by( 'login', isset( $in['rep'] ) ? (string) $in['rep'] : '' );
			if ( $long_rep ) {
				$j = WPCPM_Sponsor_Event::join( get_post( $a ), (int) $long_rep->ID );
				$say( 'event-sponsor-approved-joined-long-name', is_wp_error( $j ) ? $j : true );
			}
			foreach ( $more as $n => $u ) {
				$j = WPCPM_Sponsor_Event::join( get_post( $a ), (int) $u->ID );
				$say( 'event-sponsor-approved-joined-' . ( $n + 2 ), is_wp_error( $j ) ? $j : true );
				if ( $n < 2 && ! is_wp_error( $own ) && $own ) {
					$j = WPCPM_Sponsor_Event::join( get_post( (int) $own ), (int) $u->ID );
					$say( 'event-program-own-joined-' . ( $n + 2 ), is_wp_error( $j ) ? $j : true );
				}
			}
		}
	}
}

// ------------------------------------------------------------------ Watch: students gone quiet, a locked account
if ( $run( 'watch' ) ) :
if ( class_exists( 'WPCPM_Student_Flags' ) ) {
	$o = get_option( 'wpcpm_settings', array() );
	$o = is_array( $o ) ? $o : array();
	remove_all_filters( 'option_wpcpm_settings' );
	$o = get_option( 'wpcpm_settings', array() );
	$o['student_flags_mode'] = WPCPM_Student_Flags::MODE_PREVIEW;
	update_option( 'wpcpm_settings', $o );
	$rows = WPCPM_Student_Flags::rows();
	// Two groups: the first student stays with its school's Administrator, the others go to nobody's.
	foreach ( $rows as $i => $row ) {
		if ( $i > 0 ) {
			$rows[ $i ]['manager_id'] = 0;
		}
	}
	// Gate 4: one quiet student with no reminder state at all, so its Reminder cell is empty.
	if ( isset( $rows[1] ) ) {
		$rows[1]['reminded_at']  = 0;
		$rows[1]['test']         = false;
		$rows[1]['would_remind'] = false;
	}
	update_option( WPCPM_Student_Flags::OPT_SNAPSHOT, array( 'at' => time(), 'rows' => $rows ), false );
	$say( 'quiet-student-no-reminder', isset( $rows[1] ) );
	$say( 'quiet-students-two-groups', count( $rows ) >= 2 ? true : 'only ' . count( $rows ) . ' quiet students' );
}
$rep = get_user_by( 'login', isset( $in['rep'] ) ? (string) $in['rep'] : '' );
// Gate 4: the representative (an invented person) carries the 40-character word in the name the site shows, so
// the locked account, Who joined and the uploaders print it.
if ( $rep ) {
	$r = wp_update_user( array( 'ID' => (int) $rep->ID, 'display_name' => $long['person'] . ' Example' ) );
	$say( 'representative-long-name', is_wp_error( $r ) ? $r : (int) $r );
	$rep = get_user_by( 'id', (int) $rep->ID );
}
// Gate 4: the mentors sync's last error holds a token of 40 characters (an option only: no sync starts).
if ( class_exists( 'WPCPM_Mentors_Sync' ) && defined( 'WPCPM_Mentors_Sync::OPT_ERROR' ) ) {
	update_option( WPCPM_Mentors_Sync::OPT_ERROR, 'The remote service answered 422 for record ' . $long['error'] . '.', false );
	$say( 'sync-error-token', true );
}
// Gate 6: the students sync with no last error, so a stacked row with a blank Last error is drawn (an option only:
// no sync starts).
if ( class_exists( 'WPCPM_Students_Sync' ) && defined( 'WPCPM_Students_Sync::OPT_ERROR' ) ) {
	$say( 'sync-no-error', update_option( WPCPM_Students_Sync::OPT_ERROR, '', false ) || '' === get_option( WPCPM_Students_Sync::OPT_ERROR ) );
}
if ( $rep && class_exists( 'WPCPM_Refusal_Meter' ) ) {
	for ( $i = 0; $i < WPCPM_Refusal_Meter::PER_DAY; $i++ ) {
		WPCPM_Refusal_Meter::refuse( WPCPM_Institution_Roster::METER_SCOPE, $rep );
	}
	$say( 'locked-account', WPCPM_Refusal_Meter::is_locked( WPCPM_Institution_Roster::METER_SCOPE, $rep ) );
}

endif;

// ------------------------------------------------------------------ site icons (1.123.97): one that loads, one that does not
// The Institution Dashboard's head prints a school's site icon from what `WPCPM_Institutions_Dashboard::site_icon()`
// cached for its website's host (the transient `wpcpm_icon_` and the host's md5). On the copy that is always '': the
// lookup is refused before it is made, so no seeded school's head carried the logo's two columns, and no picture
// round drew the head whose icon did not load. Each school handed over (`icons`: its name, its record and a
// picture's path under wp-content) gets an address of the copy itself as its cached icon: a file the seed wrote, which
// loads, or a file that is not there, which the copy answers 404, so the icon takes itself off the page. It is stored
// with no expiry, the entry a page view left and its week removed first, so a ui-ready kept longer than a week still
// draws both heads. Nothing is looked up, and nothing is asked for off this machine.
if ( $run( 'icons' ) ) :
foreach ( isset( $in['icons'] ) && is_array( $in['icons'] ) ? $in['icons'] : array() as $icon ) {
	$school  = isset( $icon['school'] ) ? (string) $icon['school'] : '';
	$record  = isset( $icon['record'] ) ? (string) $icon['record'] : '';
	$picture = isset( $icon['picture'] ) ? (string) $icon['picture'] : '';
	$row     = '' !== $record && class_exists( 'WPCPM_Institutions_Index' ) ? WPCPM_Institutions_Index::row( $record ) : null;
	$site    = is_array( $row ) && isset( $row['website'] ) ? trim( (string) $row['website'] ) : '';
	// The host as the dashboard reads it: a website stored without its scheme is an https address (`website_url()`).
	$host = '' === $site ? '' : strtolower( (string) wp_parse_url( preg_match( '#^https?://#i', $site ) ? $site : 'https://' . $site, PHP_URL_HOST ) );
	if ( '' === $host || 1 !== preg_match( '#^uploads/[A-Za-z0-9/_.-]+\.png$#', $picture ) ) {
		$say( "site-icon {$school}", '' === $host ? 'no website on its record' : 'not a picture under uploads' );
		continue;
	}
	$key = 'wpcpm_icon_' . md5( $host );
	$url = content_url( $picture );
	delete_transient( $key );
	set_transient( $key, $url );
	$say( "site-icon {$school}", get_transient( $key ) === $url && false === get_option( '_transient_timeout_' . $key ) ? true : 'not stored' );
}
endif;

// ------------------------------------------------------------------ the Overview's facts (1.123.98)
// The design school's roster in every state the Overview's facts and figures read, at once: run by seed.mjs once the
// students' accounts are made (`overviewMore()`), on its own pass (`only`: `overview-more`). The facts count end dates
// from `WPCPM_Institutions_Dashboard::today()`, which reads `time()` through the filter `wpcpm_institution_now`; the
// rig does not set that filter, so the dates are written from the real day here, and a round is taken the same day. Every
// student is invented ("Example", at example.org), written straight into the roster index as states.php writes the
// pending students; the accounts are made with every hook of a new account taken off first, so nothing is mailed. Then a
// second school, settled, whose only person is its contact with no account (the people card's "0 people").
if ( $run( 'overview-more' ) ) :
remove_all_actions( 'user_register' );
remove_all_actions( 'profile_update' );
remove_all_actions( 'set_user_role' );
remove_all_actions( 'add_user_role' );
remove_all_actions( 'added_user_meta' );
remove_all_actions( 'updated_user_meta' );
$day    = DAY_IN_SECONDS;
$today  = (string) wp_date( 'Y-m-d' );
$page   = method_exists( 'WPCPM_Institutions_Dashboard', 'today' ) ? WPCPM_Institutions_Dashboard::today() : '';
$on     = static function ( $n ) use ( $today, $day ) {
	return gmdate( 'Y-m-d', (int) strtotime( $today . ' UTC' ) + (int) $n * $day );
};
$since  = class_exists( 'WPCPM_Sign_Ins' ) ? WPCPM_Sign_Ins::since() : 0;
$ok     = '' !== $design && WPCPM_Mentors_Sync::is_record_id( $design ) && $since > 0 && $since < time() - 2 * $day;
echo "overview-more: today {$today}" . ( '' !== $page ? ", the page's today() {$page}" : '' ) . ", sign-ins recorded since {$since}\n";
if ( ! $ok ) {
	$say( 'overview-more', '' === $design ? 'the design school is not on the copy' : 'the copy records no sign-ins from before today' );
} else {
	$rid = static function ( $seed ) {
		$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
		$hash  = md5( 'ui-rig-overview-more' . $seed );
		$id    = 'recOvm';
		for ( $i = 0; $i < 11; $i++ ) {
			$id .= $chars[ hexdec( substr( $hash, $i * 2, 2 ) ) % strlen( $chars ) ];
		}
		return $id;
	};
	// The two students the seed enrolled end in the spring: moved to after the 30 days, so neither counts as ended or finishing.
	foreach ( WPCPM_Roster_Index::rows( $design ) as $row ) {
		$email = isset( $row['email'] ) ? strtolower( (string) $row['email'] ) : '';
		$to    = array( 'drew.example@example.org' => $on( 60 ), 'sky.example@example.org' => $on( 75 ) );
		if ( isset( $to[ $email ] ) ) {
			$say( "overview-more end date {$email}", WPCPM_Roster_Index::update( $design, (string) $row['record_id'], array( 'end' => $to[ $email ] ) ) );
		}
	}
	// key, name, status, mentor, end (days from today), team, hours, account ('' none, 'new', 'old'), signed in, pending stamp (days ago).
	$long_team = 'Examplecontributorteamwithanunbrokenname';
	$people    = array(
		array( 'nia', 'Nia Example', 'In Sensei', 'Harper Example', 90, 'Core', '12', 'new', false, 0 ),
		array( 'oakley', 'Oakley Example', 'In Sensei', 'Indigo Example', -40, 'Core', '30', '', false, 0 ),
		array( 'penn', 'Penn Example', 'In Sensei', 'Marlowe Example', -1, 'Core', '0', '', false, 0 ),
		array( 'quill', 'Quill Example', 'In Sensei', 'Harper Example', 10, 'Core', '8', '', false, 0 ),
		array( 'remy', 'Remy Example', 'In Sensei', 'Indigo Example', 30, 'Accessibility', '', '', false, 0 ),
		array( 'sol', 'Sol Example', 'In Sensei', 'Marlowe Example', 31, 'Accessibility', '5', 'old', false, 0 ),
		array( 'tam', 'Tam Example', 'Pending graduation', 'Harper Example', -20, 'Core', '', 'new', true, 20 ),
		array( 'uma', 'Uma Example', 'Pending graduation', 'Indigo Example', -15, $long_team, '', '', false, 0 ),
		array( 'vale', 'Vale Example', 'Pending graduation', 'Marlowe Example', -10, '', '', 'new', true, 5 ),
		array( 'wren', 'Wren Example', 'In Sensei', '', 120, '', '', '', false, 0 ),
		array( 'xan', 'Xan Example', 'Graduate', 'Harper Example', -60, '', '', '', false, 0 ),
	);
	foreach ( $people as $p ) {
		list( $key, $name, $status, $mentor, $end, $team, $hours, $account, $signed, $stamp ) = $p;
		$email = $key . '.example@example.org';
		$uid   = 0;
		if ( '' !== $account ) {
			$u = get_user_by( 'email', $email );
			if ( $u ) {
				$uid = (int) $u->ID;
			} else {
				$parts = explode( ' ', $name, 2 );
				$uid   = wp_insert_user( array( 'user_login' => $key . '.example', 'user_email' => $email, 'user_pass' => wp_generate_password( 32 ), 'display_name' => $name, 'first_name' => $parts[0], 'last_name' => $parts[1], 'role' => WPCPM_Roles::ROLE_STUDENT ) );
				$uid   = is_wp_error( $uid ) ? 0 : (int) $uid;
			}
			if ( $uid > 0 && 'old' === $account ) {
				// An account made ten days before the site began recording sign-ins: the site cannot speak for it.
				$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->users, array( 'user_registered' => gmdate( 'Y-m-d H:i:s', $since - 10 * $day ) ), array( 'ID' => $uid ) ); // phpcs:ignore WordPress.DB
				clean_user_cache( $uid );
			}
		}
		$record = $rid( 'student-' . $key );
		$row    = array( 'record_id' => $record, 'name' => $name, 'email' => $email, 'status' => $status, 'institution' => $design, 'start' => '2026-02-02', 'end' => $on( $end ), 'has_mentor' => '' !== $mentor, 'username' => '', 'mentor_name' => $mentor, 'team' => $team, 'hours' => $hours, 'reports' => array(), 'user_id' => $uid );
		WPCPM_Roster_Index::insert( $design, $row );
		if ( $uid > 0 ) {
			update_user_meta( $uid, WPCPM_Students_Sync::META_RECORD_ID, $record );
			update_user_meta( $uid, WPCPM_Students_Sync::META_INSTITUTION, $design );
			update_user_meta( $uid, 'wpcpm_student_active', '1' );
			update_user_meta( $uid, 'wpcpm_student_program', array( 'record_id' => $record, 'name' => $name, 'email' => $email, 'program' => $status, 'is_past' => false, 'start' => '2026-02-02', 'end' => $on( $end ), 'institution' => 'Example School of Design', 'profile' => '', 'username' => '', 'slack' => '', 'team' => $team, 'website' => '', 'link' => '', 'tutor' => '', 'field_of_study' => '', 'accessibility' => '' ) );
			if ( $signed ) {
				update_user_meta( $uid, WPCPM_Sign_Ins::META_SIGN_IN, time() - 2 * $day );
			} else {
				delete_user_meta( $uid, WPCPM_Sign_Ins::META_SIGN_IN );
			}
			if ( $stamp > 0 ) {
				update_user_meta( $uid, WPCPM_Students_Sync::META_PENDING, array( 'at' => time() - $stamp * $day ) );
			}
		}
		$say( "overview-more student {$name}", in_array( $record, array_keys( WPCPM_Roster_Index::rows( $design ) ), true ) && ( '' === $account || $uid > 0 ) );
	}
	// The second school: settled (a copy of the design school's accepted agreement, the summary rebuilt from its posts), its
	// contact named on the index row with no account and nobody a member.
	$people0 = $rid( 'school-contact-only' );
	$from    = WPCPM_Institutions_Index::row( $design );
	$summary = WPCPM_Institution_Agreement::option( $design );
	if ( ! is_array( $from ) || ! is_array( $summary ) || empty( $summary['agreement_id'] ) ) {
		$say( 'overview-more school with its contact alone', 'the design school has no index row or no accepted agreement' );
	} else {
		$row                   = $from;
		$row['record_id']      = $people0;
		$row['name']           = 'Example Conservatory of Media';
		$row['city']           = 'Examplemere';
		$row['website']        = 'conservatory.example.org';
		$row['contact_person'] = 'Kai Example';
		$row['contact_email']  = 'kai.example@example.org';
		WPCPM_Institutions_Index::insert( $row );
		WPCPM_Institutions_Index::forget_cached();
		// Titled by the school's invented name: the rig's must-use plugin keeps an agreement in a query only when its title
		// names an invented school or record.
		if ( ! WPCPM_Institution_Agreement::posts_for( $people0 ) ) {
			$copy( (int) $summary['agreement_id'], array( 'post_title' => 'Signed Collaboration Agreement (' . $row['name'] . ')' ), array( WPCPM_Institution_Agreement::META_INSTITUTION => $people0 ) );
		}
		WPCPM_Institution_Agreement::rebuild( $people0, array( 'status' => isset( $summary['airtable_status'] ) ? (string) $summary['airtable_status'] : '' ) );
		$say( 'overview-more school with its contact alone', WPCPM_Institutions_Index::has( $people0 ) && WPCPM_Institution_Agreement::is_settled( $people0 ) && ! get_user_by( 'email', 'kai.example@example.org' ) );
	}
	wp_cache_flush();
}
endif;

echo wp_json_encode( $made ) . "\n";
