<?php
/**
 * Plugin Name: WPCredits UI rig stand-ins (local copy only)
 * Description: Copied into the Docker copy's mu-plugins by bin/ui/copy.sh prepare while the UI rig photographs and measures, and removed by copy.sh restore. It answers the plugin's Airtable requests in memory, puts invented names in place of real institutions' and sponsors' names on the screen, and lets an example.org address through the application form's address check. It does nothing at all unless the site's siteurl and home are both exactly http://localhost:8900. Never install this anywhere else.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Refuse to load anywhere but the local copy: both addresses must be exactly the copy's.
if ( 'http://localhost:8900' !== (string) get_option( 'siteurl' ) || 'http://localhost:8900' !== (string) get_option( 'home' ) ) {
	return;
}

define( 'WPCPM_UI_RIG', true );

/**
 * The invented institutions bin/ui/seed.mjs makes. An application whose title names none of them is in
 * no queue while this plugin is loaded.
 *
 * @return string[]
 */
function wpcpm_ui_rig_invented_schools() {
	return array( 'Example State College', 'Example University', 'Example Institute of Technology', 'Example School of Design', 'Example Community College' );
}

/**
 * The fixed names put in place of any real institution's or sponsor's name in a page.
 *
 * @return string[]
 */
function wpcpm_ui_rig_stand_in_names() {
	return array( 'Sample College A', 'Sample College B', 'Sample College C', 'Sample College D', 'Sample College E', 'Sample College F', 'Sample College G', 'Sample College H', 'Sample College I', 'Sample College J' );
}

/**
 * Whether a value is an Airtable record ID: rec and fourteen letters or digits. A record ID is what a
 * form posts to say which record it is about, so the replacement below never touches one (the
 * prototype's rig rewrote the invented school's own record ID in a form's values).
 *
 * @param string $value A value.
 * @return bool
 */
function wpcpm_ui_rig_is_record_id( $value ) {
	return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) );
}

/**
 * Whether a stored string is a real name to replace: three characters or more, not a record ID, not one
 * of the invented schools and nothing at an example address.
 *
 * @param string $value A stored string.
 * @return bool
 */
function wpcpm_ui_rig_is_name( $value ) {
	$value = trim( (string) $value );
	// The copy's own address (a school's website stored as localhost) names nobody, and replacing it would
	// break every link and script address on the page.
	if ( preg_match( '#^(?:https?://)?(?:localhost|127\.0\.0\.1)(?::\d+)?(?:/.*)?$#i', $value ) ) {
		return false;
	}
	return mb_strlen( $value ) >= 3 && ! wpcpm_ui_rig_is_record_id( $value ) && ! in_array( $value, wpcpm_ui_rig_invented_schools(), true ) && false === stripos( $value, 'example' );
}

/**
 * Log one line to wp-content/wpcpm-ui-rig.log: a kind and a short text, never a token, an address or a value.
 *
 * @param string $kind What happened.
 * @param string $text A short text.
 */
function wpcpm_ui_rig_log( $kind, $text ) {
	file_put_contents( WP_CONTENT_DIR . '/wpcpm-ui-rig.log', gmdate( 'c' ) . "\t" . $kind . "\t" . $text . "\n", FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}

/*
 * 1. Airtable, answered in memory.
 *
 * WPCPM_Airtable::request() calls wp_remote_request(); the only place WordPress lets a request be answered
 * without being made is `pre_http_request`. The copy's own safety plugin (priority 0) and the scrub's offline
 * plugin (PHP_INT_MAX) both answer it with a refusal. This filter is added on `muplugins_loaded`, after both,
 * at PHP_INT_MAX, so it runs last; for a request to api.airtable.com's record endpoints it puts a success in
 * place of the refusal, so no request is ever made. Every other request keeps its refusal.
 */
add_action(
	'muplugins_loaded',
	static function () {
		add_filter( 'pre_http_request', 'wpcpm_ui_rig_airtable', PHP_INT_MAX, 3 );
	}
);

/**
 * An invented record ID in Airtable's shape (rec and fourteen), the same for the same input, starting recRig.
 *
 * @param string $seed What the ID is made from.
 * @return string
 */
function wpcpm_ui_rig_record_id( $seed ) {
	$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
	$hash  = md5( 'ui-rig' . $seed );
	$id    = 'recRig';
	for ( $i = 0; $i < 11; $i++ ) {
		$id .= $chars[ hexdec( substr( $hash, $i * 2, 2 ) ) % strlen( $chars ) ];
	}
	return $id;
}

/**
 * Gate B: one invented student's record, as the program records would answer a read of it, so the live claim
 * (`WPCPM_Institution_Roster::claim()`) and the Student Report Card's route reach what they are for. Answered only for a
 * record the rig invented (`recRig`, the stand-in's own POST) that an account on the copy holds as its record, at an
 * invented address (example.org) and stamped with an institution; every other record keeps `fields: {}`.
 *
 * The readers and what each reads:
 * - the claim, a report record (`TYPE_REPORT`): the Students Reports row's `Students` link, else its `Email`
 *   (class-wpcpm-institution-roster.php, `live_students_row()`); then that Students row's `Educational Institutions` link,
 *   the authoritative decision, and the disclosed columns (`disclosed_keys()`): `Full Name`, `Email`, `Status`,
 *   `Educational Institutions`, `Start Date`, `End Date` (the others left out: no mentor, profile, tutor, field of study);
 * - the student correction (`WPCPM_Institution_Student_Form::diff_cells()`): the claim's Students columns as the baseline,
 *   `Start Date` the one the date rule reads when the start is not posted changed, and the Students Reports row through
 *   `WPCPM_Student_Report_Form::values()` (`Name`, `Students`, `Email`);
 * - the Student Report Card's route (`rest_permission()` then `rest_report()`): the claim (`ACT_VIEW_REPORT`), then the
 *   Students Reports row (`values()`) the card draws, of which `Name` and `Email` are answered and every answer of the
 *   report itself is left empty, so the card draws as a report nobody has filled in yet.
 * The record is one ID in both tables (states.php's accounts write the roster's Students record as the report record).
 *
 * @param string $table  The table's ID, as the request names it.
 * @param string $record The record's ID.
 * @return array Field name => value; none for any other record.
 */
function wpcpm_ui_rig_student_fields( $table, $record ) {
	if ( 0 !== strpos( (string) $record, 'recRig' ) || ! class_exists( 'WPCPM_Settings' ) || ! class_exists( 'WPCPM_Students_Sync' ) || ! class_exists( 'WPCPM_Roster_Index' ) ) {
		return array();
	}
	$users = get_users(
		array(
			'meta_key'   => WPCPM_Students_Sync::META_RECORD_ID, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => $record, // phpcs:ignore WordPress.DB.SlowDBQuery
			'number'     => 2,
		)
	);
	if ( 1 !== count( $users ) || ! preg_match( '/@example\.org$/i', (string) $users[0]->user_email ) ) {
		return array();
	}
	$user        = $users[0];
	$institution = (string) get_user_meta( $user->ID, WPCPM_Students_Sync::META_INSTITUTION, true );
	$rows        = '' !== $institution ? WPCPM_Roster_Index::rows( $institution ) : array();
	$row         = isset( $rows[ $record ] ) && is_array( $rows[ $record ] ) ? $rows[ $record ] : array();
	if ( '' === $institution || array() === $row ) {
		return array();
	}
	$settings = WPCPM_Settings::get();
	$name     = (string) $user->display_name;
	$email    = strtolower( (string) $user->user_email );
	if ( isset( $settings['reports_table'] ) && (string) $settings['reports_table'] === $table ) {
		return array(
			'Name'     => $name,
			'Email'    => $email,
			'Students' => array( $record ),
		);
	}
	if ( isset( $settings['students_table'] ) && (string) $settings['students_table'] === $table ) {
		return array(
			'Full Name'                => $name,
			'Email'                    => $email,
			'Status'                   => isset( $row['status'] ) ? (string) $row['status'] : '',
			'Educational Institutions' => array( $institution ),
			'Start Date'               => isset( $row['start'] ) ? (string) $row['start'] : '',
			'End Date'                 => isset( $row['end'] ) ? (string) $row['end'] : '',
		);
	}
	return array();
}

/**
 * The stand-in itself.
 *
 * @param false|array|WP_Error $pre  What earlier filters answered.
 * @param array                $args The request's arguments.
 * @param string               $url  The request's address.
 * @return false|array|WP_Error
 */
function wpcpm_ui_rig_airtable( $pre, $args, $url ) {
	$parts = wp_parse_url( (string) $url );
	$host  = strtolower( isset( $parts['host'] ) ? (string) $parts['host'] : '' );

	if ( 'api.airtable.com' !== $host ) {
		return $pre;
	}

	$path   = isset( $parts['path'] ) ? (string) $parts['path'] : '';
	$method = strtoupper( isset( $args['method'] ) ? (string) $args['method'] : 'GET' );
	$seg    = array_values(
		array_filter(
			explode( '/', $path ),
			static function ( $s ) {
				return '' !== $s;
			}
		)
	); // v0, base, table[, record].

	// Schema and base listings are not answered: they keep the copy's refusal.
	if ( count( $seg ) < 3 || 'v0' !== $seg[0] || 'meta' === $seg[1] ) {
		wpcpm_ui_rig_log( 'airtable-refused', $method . ' ' . ( isset( $seg[1] ) && 'meta' === $seg[1] ? 'meta' : 'other' ) );
		return $pre;
	}

	$table  = rawurldecode( $seg[2] );
	$record = isset( $seg[3] ) ? rawurldecode( $seg[3] ) : '';
	$body   = isset( $args['body'] ) && is_string( $args['body'] ) ? json_decode( $args['body'], true ) : null;
	$rows   = ( is_array( $body ) && isset( $body['records'] ) && is_array( $body['records'] ) ) ? $body['records'] : array();
	$now    = gmdate( 'Y-m-d\TH:i:s.000\Z' );
	$answer = null;

	if ( 'GET' === $method && '' === $record ) {
		// A list read (the duplicate search before an approval, any other lookup): no rows.
		$answer = array( 'records' => array() );
	} elseif ( 'GET' === $method ) {
		$fields = wpcpm_ui_rig_student_fields( $table, $record );
		$answer = array(
			'id'          => $record,
			'createdTime' => $now,
			'fields'      => array() === $fields ? new stdClass() : $fields,
		);
	} elseif ( 'POST' === $method ) {
		$out = array();
		foreach ( $rows as $i => $row ) {
			$out[] = array(
				'id'          => wpcpm_ui_rig_record_id( $table . wp_json_encode( $row ) . $i . microtime() ),
				'createdTime' => $now,
				'fields'      => isset( $row['fields'] ) ? $row['fields'] : new stdClass(),
			);
		}
		$answer = array( 'records' => $out );
	} elseif ( 'PATCH' === $method || 'PUT' === $method ) {
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'id'          => isset( $row['id'] ) ? (string) $row['id'] : '',
				'createdTime' => $now,
				'fields'      => isset( $row['fields'] ) ? $row['fields'] : new stdClass(),
			);
		}
		$answer = array( 'records' => $out );
	} elseif ( 'DELETE' === $method ) {
		$out = array();
		$q   = isset( $parts['query'] ) ? (string) $parts['query'] : '';
		if ( preg_match_all( '/records(?:%5B%5D|\[\])=([^&]+)/', $q, $m ) ) {
			foreach ( $m[1] as $id ) {
				$out[] = array(
					'id'      => rawurldecode( $id ),
					'deleted' => true,
				);
			}
		}
		$answer = array( 'records' => $out );
	}

	if ( null === $answer ) {
		wpcpm_ui_rig_log( 'airtable-refused', $method );
		return $pre;
	}

	// The table ID and the kind of request only: never a value, a formula or a field.
	wpcpm_ui_rig_log( 'airtable-answered', $method . ' ' . ( '' !== $record ? 'one record' : 'table' ) . ' ' . $table . ' ' . ( isset( $answer['records'] ) ? count( $answer['records'] ) . ' rows' : '1 record' ) );

	return array(
		'headers'  => array( 'content-type' => 'application/json; charset=utf-8' ),
		'body'     => wp_json_encode( $answer ),
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'cookies'  => array(),
		'filename' => null,
	);
}

/*
 * The copy holds no Airtable token, and the client refuses before any request without one. While the rig
 * runs, an empty token reads as an invented placeholder, in memory only: it is never saved, and it goes
 * nowhere, because every request to Airtable is answered above before it is made.
 */
add_filter(
	'option_wpcpm_settings',
	static function ( $value ) {
		if ( is_array( $value ) && empty( $value['api_token'] ) ) {
			$value['api_token'] = 'patUiRigStandIn.NotARealToken';
		}
		return $value;
	}
);

/*
 * 2. Real names off the screen.
 *
 * (a) The program's queues are limited to the invented entries: an application, a registration, a request, a
 * report or an agreement whose title names no invented school or person, and no invented record (every record
 * the stand-in makes starts recRig), is in no list while this plugin is loaded.
 */
add_action(
	'pre_get_posts',
	static function ( $query ) {
		$types = array_intersect( (array) $query->get( 'post_type' ), array( 'wpcpm_inst_app', 'wpcpm_ment_app', 'wpcpm_stu_reg', 'wpcpm_inst_request', 'wpcpm_inst_report', 'wpcpm_agreement' ) );
		if ( empty( $types ) ) {
			return;
		}
		global $wpdb;
		$likes = array();
		$args  = array();
		foreach ( array_merge( wpcpm_ui_rig_invented_schools(), array( 'Example', 'recRig' ) ) as $word ) {
			$likes[] = 'post_title LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $word ) . '%';
		}
		$in  = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ($in) AND (" . implode( ' OR ', $likes ) . ')';
		$to_int = static function ( $v ) {
			return (int) $v;
		};
		$ids    = array_map( $to_int, (array) $wpdb->get_col( $wpdb->prepare( $sql, array_merge( array_values( $types ), $args ) ) ) ); // phpcs:ignore WordPress.DB
		$was    = $query->get( 'post__in' );
		if ( ! empty( $was ) ) {
			$ids = array_values( array_intersect( $ids, array_map( $to_int, (array) $was ) ) );
		}
		$query->set( 'post__in', $ids ? $ids : array( 0 ) );
	}
);

/**
 * (b) The real names, read once per request from the site's own stored lists (the institutions index, the
 * sponsors' rows, the institution records', applications' and sponsors' titles, the schools' website hosts),
 * longest first. Nothing is stored.
 *
 * @return string[]
 */
function wpcpm_ui_rig_real_names() {
	static $names = null;
	if ( null !== $names ) {
		return $names;
	}
	$found = array();
	$add   = static function ( $v ) use ( &$found ) {
		if ( is_string( $v ) ) {
			$v = trim( html_entity_decode( wp_strip_all_tags( $v ), ENT_QUOTES, 'UTF-8' ) );
			if ( wpcpm_ui_rig_is_name( $v ) ) {
				$found[ $v ] = true;
			}
		}
	};
	if ( class_exists( 'WPCPM_Institutions_Index' ) ) {
		foreach ( (array) WPCPM_Institutions_Index::rows() as $row ) {
			foreach ( array( 'name', 'institution', 'institution_name' ) as $k ) {
				if ( isset( $row[ $k ] ) ) {
					$add( $row[ $k ] );
				}
			}
			// A school's website names it too: its host is replaced like its name.
			if ( ! empty( $row['website'] ) && is_string( $row['website'] ) ) {
				$h = preg_replace( '#^(https?://)?(www\\.)?#i', '', strtolower( trim( $row['website'] ) ) );
				$h = preg_replace( '#[/?\\#].*$#', '', (string) $h );
				if ( strlen( (string) $h ) >= 6 && wpcpm_ui_rig_is_name( (string) $h ) ) {
					$found[ (string) $h ] = true;
				}
			}
		}
	}
	if ( class_exists( 'WPCPM_Sponsors_Index' ) ) {
		foreach ( (array) WPCPM_Sponsors_Index::rows() as $row ) {
			foreach ( array( 'name', 'company_name', 'sponsor_name', 'title' ) as $k ) {
				if ( isset( $row[ $k ] ) ) {
					$add( $row[ $k ] );
				}
			}
		}
	}
	global $wpdb;
	$types = array( 'wpcpm_inst_record', 'wpcpm_inst_app', 'wpcpm_sponsor_co', 'wpcpm_sponsor_app', 'wpcpm_agreement', 'wpcpm_sponsor_agr' );
	$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_title FROM {$wpdb->posts} WHERE post_type IN ($in)", $types ) ) as $t ) { // phpcs:ignore WordPress.DB
		$add( $t );
	}
	// Institution applications' stored answers: the school name cell, as real-names.php reads it (a school
	// can be named there in a spelling no index or title holds, and a student's row repeats that spelling).
	if ( class_exists( 'WPCPM_Institution_Application' ) && class_exists( 'WPCPM_Institutions_Sync' ) ) {
		$fields = WPCPM_Institutions_Sync::fields();
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'wpcpm_inst_app' ) ) as $id ) { // phpcs:ignore WordPress.DB
			$f = get_post_meta( (int) $id, WPCPM_Institution_Application::META_FIELDS, true );
			if ( is_array( $f ) && isset( $fields['name'], $f[ $fields['name'] ] ) ) {
				$add( $f[ $fields['name'] ] );
			}
		}
	}
	$names = array_keys( $found );
	usort(
		$names,
		static function ( $a, $b ) {
			return mb_strlen( $b ) - mb_strlen( $a );
		}
	);
	return $names;
}

/**
 * Put a stand-in in place of each of the names in the text parts of one page.
 *
 * Touched: the text between tags before WordPress.org's footer, a text field's value and placeholder (an
 * input of no type or of a typing type), a text box's contents, and the JSON forms of a name (as wp_json_encode
 * writes it, escaped for an attribute or with its slashes plain) inside a script's string literals and inside
 * the attributes of a tag that is not a form control, before the footer and after it. Never touched: a tag's
 * name, id, name attribute, value, link or form address; a style; the value of a hidden, checkbox, radio,
 * submit, button or image input; an option's value; any value or JSON string that is a record ID; and the
 * footer itself (`<footer class="global-footer">` to its `</footer>`), which links to WordPress.com, also a
 * sponsor's name.
 *
 * @param string   $html  The page.
 * @param string[] $names The names, longest first.
 * @return string
 */
function wpcpm_ui_rig_replace_names_in( $html, array $names ) {
	if ( ! is_string( $html ) || '' === $html || false === stripos( $html, '<html' ) ) {
		return $html;
	}
	$names = array_values(
		array_filter(
			$names,
			static function ( $n ) {
				return wpcpm_ui_rig_is_name( $n );
			}
		)
	);
	if ( empty( $names ) ) {
		return $html;
	}
	$stand = wpcpm_ui_rig_stand_in_names();
	$pats  = array();
	$reps  = array();
	$jpats = array();
	$jreps = array();
	foreach ( $names as $i => $name ) {
		// As stored, escaped, and as WordPress prints it with curly quotes (wptexturize), in characters and entities.
		$forms = array( $name, esc_html( $name ), htmlspecialchars( $name, ENT_QUOTES, 'UTF-8' ), str_replace( "'", "\u{2019}", $name ), str_replace( "'", '&#8217;', $name ), str_replace( "'", '&rsquo;', $name ), wptexturize( $name ), htmlentities( $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), htmlentities( $name, ENT_QUOTES, 'UTF-8' ) );
		if ( function_exists( 'mb_encode_numericentity' ) ) {
			$forms[] = mb_encode_numericentity( $name, array( 0x80, 0x10FFFF, 0, 0x1FFFFF ), 'UTF-8' );
			$forms[] = mb_encode_numericentity( $name, array( 0x80, 0x10FFFF, 0, 0x1FFFFF ), 'UTF-8', true );
		}
		if ( class_exists( 'Normalizer' ) ) {
			$forms[] = (string) Normalizer::normalize( $name, Normalizer::FORM_D );
		}
		$stand_in = ( false === strpos( $name, ' ' ) && false !== strpos( $name, '.' ) ) ? 'sample-college.example.org' : $stand[ $i % count( $stand ) ];
		foreach ( array_unique( array_filter( $forms ) ) as $form ) {
			$pats[] = '/(?<![\p{L}\p{N}])' . preg_quote( $form, '/' ) . '(?![\p{L}\p{N}])/u' . ( mb_strlen( $name ) > 4 ? 'i' : '' );
			$reps[] = $stand_in;
		}
		// Inside a script's string or an attribute: as stored and escaped for an attribute (a name a script
		// carries as it is, or a search attribute holds), and as JSON (an accent written as a \u escape, a slash
		// as \/). Whole words only, as in the text.
		$json = substr( (string) wp_json_encode( $name ), 1, -1 );
		foreach ( array_unique( array( $name, esc_attr( $name ), $json, esc_attr( $json ), str_replace( '\\/', '/', $json ) ) ) as $form ) {
			$jpats[] = '/(?<![\p{L}\p{N}])' . preg_quote( $form, '/' ) . '(?![\p{L}\p{N}])/u' . ( mb_strlen( $name ) > 4 ? 'i' : '' );
			$jreps[] = $stand_in;
		}
	}
	$swap  = static function ( $text ) use ( $pats, $reps ) {
		if ( wpcpm_ui_rig_is_record_id( $text ) ) {
			return $text;
		}
		$new = preg_replace( $pats, $reps, $text );
		return is_string( $new ) ? $new : $text;
	};
	$jswap = static function ( $text ) use ( $jpats, $jreps ) {
		if ( empty( $jpats ) || wpcpm_ui_rig_is_record_id( $text ) ) {
			return $text;
		}
		$new = preg_replace( $jpats, $jreps, $text );
		return is_string( $new ) ? $new : $text;
	};
	// Each string literal of a script's body, on its own: one that decodes to a record ID is left as it is.
	// The opening tag (its src and its id) is never touched.
	$script = static function ( $code ) use ( $jswap ) {
		$open = strpos( $code, '>' );
		if ( false === $open ) {
			return $code;
		}
		$body = substr( $code, $open + 1 );
		$new  = preg_replace_callback(
			'/"((?:[^"\\\\]|\\\\.)*)"/s',
			static function ( $m ) use ( $jswap ) {
				$decoded = json_decode( '"' . $m[1] . '"' );
				return is_string( $decoded ) && wpcpm_ui_rig_is_record_id( $decoded ) ? $m[0] : '"' . $jswap( $m[1] ) . '"';
			},
			$body
		);
		return is_string( $new ) ? substr( $code, 0, $open + 1 ) . $new : $code;
	};
	// One stretch of the page, split into tags, scripts, styles, text boxes and text.
	$walk = static function ( $html, $with_text ) use ( $swap, $jswap, $script ) {
		$parts = preg_split( '/(<script\b.*?<\/script>|<style\b.*?<\/style>|<textarea\b.*?<\/textarea>|<[^>]*>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( (array) $parts as $k => $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( '<' !== $part[0] ) {
				$parts[ $k ] = $with_text ? $swap( $part ) : $part;
				continue;
			}
			$new = null;
			if ( 0 === stripos( $part, '<script' ) ) {
				$new = $script( $part );
			} elseif ( 0 === stripos( $part, '<style' ) ) {
				continue;
			} elseif ( 0 === stripos( $part, '<textarea' ) ) {
				if ( $with_text ) {
					$new = preg_replace_callback(
						'#^(<textarea\b[^>]*>)(.*)(</textarea>)$#is',
						static function ( $m ) use ( $swap ) {
							return $m[1] . $swap( $m[2] ) . $m[3];
						},
						$part
					);
				}
			} elseif ( preg_match( '#^<input\b#i', $part ) ) {
				// A text field shows its value and placeholder. The value of any other input only posts (or is a
				// button's word, which is posted too), and is left alone.
				if ( $with_text && ! preg_match( '#\btype\s*=\s*["\']?(?:hidden|checkbox|radio|submit|button|image)\b#i', $part ) ) {
					$new = preg_replace_callback(
						'#\b(value|placeholder)="([^"]*)"#i',
						static function ( $m ) use ( $swap ) {
							return $m[1] . '="' . $swap( $m[2] ) . '"';
						},
						$part
					);
				}
			} elseif ( ! preg_match( '#^</?(?:option|select)\b#i', $part ) ) {
				// Another tag: JSON a script reads from a data attribute, never a name, id, value, link or address.
				// A button's and a form's own words are walked too (the question data-wpcpm-confirm asks in the
				// browser, an aria-label), but never what they post or where: their name, value, type, form,
				// method, action and formaction.
				$new = preg_replace_callback(
					'#(\s)([a-z][a-z0-9_:-]*)="([^"]*)"#i',
					static function ( $m ) use ( $jswap ) {
						return in_array( strtolower( $m[2] ), array( 'id', 'name', 'value', 'href', 'src', 'action', 'for', 'class', 'type', 'form', 'method', 'formaction' ), true ) ? $m[0] : $m[1] . $m[2] . '="' . $jswap( $m[3] ) . '"';
					},
					$part
				);
			}
			if ( is_string( $new ) ) {
				$parts[ $k ] = $new;
			}
		}
		return implode( '', (array) $parts );
	};
	// WordPress.org's own footer links to WordPress.com, also a sponsor's name: the footer is left as it is;
	// what is printed after it (scripts carrying JSON) is walked for the JSON forms only.
	$footer = '';
	$after  = '';
	$cut    = strpos( $html, '<footer class="global-footer' );
	if ( false !== $cut ) {
		$close  = stripos( $html, '</footer>', $cut );
		$stop   = false === $close ? strlen( $html ) : $close + strlen( '</footer>' );
		$footer = substr( $html, $cut, $stop - $cut );
		$after  = substr( $html, $stop );
		$html   = substr( $html, 0, $cut );
	}
	$out = $walk( $html, true ) . $footer . ( '' === $after ? '' : $walk( $after, false ) );
	return $out;
}

/**
 * The output buffer's callback: the copy's own two banners left out unless asked for, then the names.
 *
 * @param string $html The page.
 * @return string
 */
function wpcpm_ui_rig_replace_names( $html ) {
	if ( ! is_string( $html ) || false === stripos( $html, '<html' ) ) {
		return $html;
	}
	// The copy's own banners (its safety plugin's and the scrub's) are not on the real site. The pictures keep
	// them while the option wpcpm_ui_rig_keep_banners is set, as snap.mjs sets it.
	if ( ! get_option( 'wpcpm_ui_rig_keep_banners' ) ) {
		$html = (string) preg_replace( '#<div class="notice notice-(?:warning|info)"><p><strong>(?:Local copy of [a-z.]+\.|Trial copy: every person\'s name here is a pseudonym\.)</strong>.*?</p></div>#s', '', $html );
	}
	$out = wpcpm_ui_rig_replace_names_in( $html, wpcpm_ui_rig_real_names() );
	if ( $out !== $html ) {
		wpcpm_ui_rig_log( 'names-replaced', strtok( isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '', '?' ) ); // phpcs:ignore WordPress.Security
	}
	return $out;
}

add_action(
	'init',
	static function () {
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( 'GET' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET' ) ) { // phpcs:ignore WordPress.Security
			return;
		}
		ob_start( 'wpcpm_ui_rig_replace_names' );
	},
	0
);

/*
 * 3. An address at example.org passes the application form's address check (the copy has no DNS).
 */
add_filter(
	'wpcpm_application_domain_takes_mail',
	static function ( $takes, $domain ) {
		return 'example.org' === strtolower( (string) $domain ) ? true : $takes;
	},
	10,
	2
);

/*
 * The mail to an example.org address is still swallowed by the copy's safety plugin; this only keeps the
 * confirmation link of such a mail (and an invitation's accept address), so the invented applicant can confirm off screen. It reads the mail and
 * hands it on unchanged.
 */
add_filter(
	'wp_mail',
	static function ( $atts ) {
		$to = isset( $atts['to'] ) ? ( is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : (string) $atts['to'] ) : '';
		if ( preg_match( '/@example\.org\b/i', $to ) && isset( $atts['message'] ) && preg_match_all( '#http://localhost:8900/[^\s"\'<>]+#', (string) $atts['message'], $m ) ) {
			foreach ( $m[0] as $link ) {
				if ( false !== strpos( $link, 'verify' ) || false !== strpos( $link, 'confirm' ) || false !== strpos( $link, 'wpcpm_accept_invite' ) ) {
					file_put_contents( WP_CONTENT_DIR . '/wpcpm-ui-rig-links.log', $link . "\n", FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				}
			}
		}
		return $atts;
	},
	PHP_INT_MAX
);

/*
 * 4. The clock the relative-time words read, frozen.
 *
 * PHP's own clock cannot be stopped without an extension the copy does not have, but nearly every "N minutes
 * ago" and "in N days" the plugin prints goes through human_time_diff() with time() at one end. While the option
 * wpcpm_ui_rig_now holds a moment (snap.mjs stores it with the invented items, in the snapshot ui-ready), an end
 * that is the live clock is read as that moment instead, so two runs an hour or a week apart print the same
 * words. An end that is a stored time is kept. Counts of whole days the plugin works out itself from time() are
 * not touched: they change only when the date does.
 */
add_filter(
	'human_time_diff',
	static function ( $since, $diff, $from, $to ) {
		static $busy = false;
		$now = (int) get_option( 'wpcpm_ui_rig_now' );
		if ( $busy || $now <= 0 ) {
			return $since;
		}
		$live = time();
		$f    = abs( (int) $from - $live ) <= 5 ? $now : (int) $from;
		$t    = abs( (int) $to - $live ) <= 5 ? $now : (int) $to;
		if ( $f === (int) $from && $t === (int) $to ) {
			return $since;
		}
		$busy = true;
		$out  = human_time_diff( $f, $t );
		$busy = false;
		return $out;
	},
	PHP_INT_MAX,
	4
);

/*
 * 5. A picture's sizes the copy does not hold.
 *
 * The copy's uploads hold the original files of the guide's pictures but none of the sizes WordPress made on the
 * live site, while the attachments' metadata still lists them: the browser would pick a missing size from the
 * srcset and draw a broken picture (which snap.mjs refuses to save). Each source whose file is not on the copy is
 * left out of the srcset; the src, the original, is kept.
 */
add_filter(
	'wp_calculate_image_srcset',
	static function ( $sources ) {
		if ( ! is_array( $sources ) ) {
			return $sources;
		}
		$up = wp_get_upload_dir();
		foreach ( $sources as $w => $s ) {
			$url = isset( $s['url'] ) ? (string) $s['url'] : '';
			if ( 0 === strpos( $url, (string) $up['baseurl'] . '/' ) && ! is_file( $up['basedir'] . substr( $url, strlen( (string) $up['baseurl'] ) ) ) ) {
				unset( $sources[ $w ] );
			}
		}
		return $sources;
	}
);
