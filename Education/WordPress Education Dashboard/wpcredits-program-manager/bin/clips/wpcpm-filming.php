<?php
/**
 * Plugin Name: WPCredits filming stand-ins (local copy only)
 * Description: Copied into the Docker copy's mu-plugins by bin/clips while how-to clips are filmed, and removed after. It answers the plugin's Airtable requests in memory, keeps real institutions' and sponsors' names off the screen, and lets an example.org address through the application form's address check. It does nothing at all unless the site is exactly http://localhost:8900. Never install this anywhere else.
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

define( 'WPCPM_FILMING', true );

/**
 * The invented institutions the clips are about. An application whose title is not one of these
 * is not in any queue while this plugin is loaded.
 */
function wpcpm_filming_invented_schools() {
	return array( 'Northfield College', 'Example University', 'Example State College', 'Example Institute of Technology', 'Example School of Design', 'Example Community College', 'Example Polytechnic College', 'Example Lakeside College', 'Example Valley College', 'Example Hillside College', 'Example Riverside Institute', 'Example Harbor College' );
}

/**
 * The fixed names put in place of any real institution's or sponsor's name in a page.
 */
function wpcpm_filming_stand_in_names() {
	return array( 'Sample College A', 'Sample College B', 'Sample College C', 'Sample College D', 'Sample College E', 'Sample College F', 'Sample College G', 'Sample College H', 'Sample College I', 'Sample College J' );
}

/**
 * Log one line to wp-content/wpcpm-filming.log: a kind and a short text, never a token, an address or a value.
 */
function wpcpm_filming_log( $kind, $text ) {
	file_put_contents( WP_CONTENT_DIR . '/wpcpm-filming.log', gmdate( 'c' ) . "\t" . $kind . "\t" . $text . "\n", FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}

/*
 * 1. Airtable, answered in memory.
 *
 * WPCPM_Airtable::request() calls wp_remote_request(); the only place WordPress lets a request be
 * answered without being made is `pre_http_request`. The copy's own safety plugin (priority 0) and the
 * scrub's offline plugin (PHP_INT_MAX) both answer it with a refusal. This filter is added on
 * `muplugins_loaded`, after both, at PHP_INT_MAX, so it runs last; for a request to api.airtable.com's
 * record endpoints it puts a success in place of the refusal, so no request is ever made. Every other
 * request keeps its refusal: this filter hands back what it was given.
 */
add_action(
	'muplugins_loaded',
	static function () {
		add_filter( 'pre_http_request', 'wpcpm_filming_airtable', PHP_INT_MAX, 3 );
	}
);

/**
 * An invented record ID in Airtable's shape (rec + 14), the same for the same input.
 */
function wpcpm_filming_record_id( $seed ) {
	$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
	$hash  = md5( 'filming' . $seed );
	$id    = 'recFilm';
	for ( $i = 0; $i < 10; $i++ ) {
		$id .= $chars[ hexdec( substr( $hash, $i * 2, 2 ) ) % strlen( $chars ) ];
	}
	return $id;
}

/**
 * The stand-in itself.
 *
 * @param false|array|WP_Error $pre  What earlier filters answered.
 * @param array                $args The request's arguments.
 * @param string               $url  The request's address.
 * @return false|array|WP_Error
 */
function wpcpm_filming_airtable( $pre, $args, $url ) {
	$parts = wp_parse_url( (string) $url );
	$host  = strtolower( isset( $parts['host'] ) ? (string) $parts['host'] : '' );

	if ( 'api.airtable.com' !== $host ) {
		return $pre;
	}

	$path   = isset( $parts['path'] ) ? (string) $parts['path'] : '';
	$method = strtoupper( isset( $args['method'] ) ? (string) $args['method'] : 'GET' );
	$seg    = array_values( array_filter( explode( '/', $path ), 'strlen' ) ); // v0, base, table[, record].

	// Schema and base listings are not answered: they keep the copy's refusal.
	if ( count( $seg ) < 3 || 'v0' !== $seg[0] || 'meta' === $seg[1] ) {
		wpcpm_filming_log( 'airtable-refused', $method . ' ' . ( isset( $seg[1] ) && 'meta' === $seg[1] ? 'meta' : 'other' ) );
		return $pre;
	}

	$table  = rawurldecode( $seg[2] );
	$record = isset( $seg[3] ) ? rawurldecode( $seg[3] ) : '';
	$body   = isset( $args['body'] ) && is_string( $args['body'] ) ? json_decode( $args['body'], true ) : null;
	$now    = gmdate( 'Y-m-d\TH:i:s.000\Z' );
	$answer = null;

	if ( 'GET' === $method && '' === $record ) {
		// A list read (the duplicate search before an approval, any other lookup): no rows. The institutions sync's
		// own reads of the Institutions and Countries tables, whole and unfiltered, are answered with what the copy
		// already holds, so a sync run before the talks clips switch the record to the site rewrites nothing.
		$query = isset( $parts['query'] ) ? (string) $parts['query'] : '';
		$whole = false === strpos( $query, 'filterByFormula' );
		$echo  = $whole ? wpcpm_filming_echo_table( $table ) : null;
		$answer = null !== $echo ? array( 'records' => $echo ) : array( 'records' => array() );
	} elseif ( 'GET' === $method ) {
		$answer = array( 'id' => $record, 'createdTime' => $now, 'fields' => new stdClass() );
	} elseif ( 'POST' === $method ) {
		$out = array();
		foreach ( ( is_array( $body ) && isset( $body['records'] ) && is_array( $body['records'] ) ) ? $body['records'] : array() as $i => $row ) {
			$out[] = array(
				'id'          => wpcpm_filming_record_id( $table . wp_json_encode( $row ) . $i . microtime() ),
				'createdTime' => $now,
				'fields'      => isset( $row['fields'] ) ? $row['fields'] : new stdClass(),
			);
		}
		$answer = array( 'records' => $out );
	} elseif ( 'PATCH' === $method || 'PUT' === $method ) {
		$out = array();
		foreach ( ( is_array( $body ) && isset( $body['records'] ) && is_array( $body['records'] ) ) ? $body['records'] : array() as $row ) {
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
				$out[] = array( 'id' => rawurldecode( $id ), 'deleted' => true );
			}
		}
		$answer = array( 'records' => $out );
	}

	if ( null === $answer ) {
		wpcpm_filming_log( 'airtable-refused', $method );
		return $pre;
	}

	// The table ID and the kind of request only: never a value, a formula or a field.
	wpcpm_filming_log( 'airtable-answered', $method . ' ' . ( '' !== $record ? 'one record' : 'table' ) . ' ' . $table . ' ' . ( isset( $answer['records'] ) ? count( $answer['records'] ) . ' rows' : '1 record' ) );

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

/**
 * The rows the copy already holds, in Airtable's shape, for one of the two tables the institutions sync reads whole:
 * the site's list of institutions (`WPCPM_Institutions_Index`, with each agreement's Drive link the site keeps) and its
 * countries map (`WPCPM_Countries`). Null for any other table, so it keeps answering no rows. Nothing is read from
 * Airtable and nothing leaves the copy.
 *
 * @param string $table The table ID the request names.
 * @return array[]|null
 */
function wpcpm_filming_echo_table( $table ) {
	if ( ! class_exists( 'WPCPM_Settings' ) || ! class_exists( 'WPCPM_Institutions_Index' ) || ! class_exists( 'WPCPM_Countries' ) || ! class_exists( 'WPCPM_Institutions_Sync' ) ) {
		return null;
	}

	$settings = WPCPM_Settings::get();
	$rows     = array();

	if ( isset( $settings['institutions_table'] ) && (string) $settings['institutions_table'] === $table ) {
		$fields = WPCPM_Institutions_Sync::fields();

		if ( ! empty( $settings['institutions_name_field'] ) ) {
			$fields['name'] = (string) $settings['institutions_name_field'];
		}

		foreach ( (array) WPCPM_Institutions_Index::rows() as $id => $row ) {
			$agreement = isset( $row['agreement'] ) && is_array( $row['agreement'] ) ? $row['agreement'] : array();
			$cells     = array(
				$fields['name']             => (string) $row['name'],
				$fields['stage']            => (string) $row['stage'],
				$fields['country']          => '' !== (string) $row['country'] ? array( (string) $row['country'] ) : array(),
				$fields['city']             => (string) $row['city'],
				$fields['website']          => (string) $row['website'],
				$fields['contact_person']   => (string) $row['contact_person'],
				$fields['contact_email']    => (string) $row['contact_email'],
				$fields['confirmed_on']     => (string) $row['confirmed_on'],
				$fields['consent']          => ! empty( $row['consent'] ),
				$fields['agr_status']       => isset( $agreement['status'] ) ? (string) $agreement['status'] : '',
				$fields['agr_kind']         => isset( $agreement['kind'] ) ? (string) $agreement['kind'] : '',
				$fields['agr_accepted_on']  => isset( $agreement['accepted_on'] ) ? (string) $agreement['accepted_on'] : '',
				$fields['agr_signed_on']    => isset( $agreement['signed_on'] ) ? (string) $agreement['signed_on'] : '',
				$fields['agr_accepted_by']  => isset( $agreement['accepted_by'] ) ? (string) $agreement['accepted_by'] : '',
				$fields['agr_document']     => class_exists( 'WPCPM_Institution_Agreement' ) ? (string) WPCPM_Institution_Agreement::record_document( (string) $id ) : '',
				$fields['agr_submitted_on'] => isset( $agreement['submitted_on'] ) ? (string) $agreement['submitted_on'] : '',
				$fields['agr_template']     => isset( $agreement['template_version'] ) ? (string) $agreement['template_version'] : '',
			);
			$rows[]    = array(
				'id'          => (string) $id,
				'createdTime' => '' !== (string) $row['created'] ? (string) $row['created'] . 'T00:00:00.000Z' : gmdate( 'Y-m-d\TH:i:s.000\Z' ),
				'fields'      => $cells,
			);
		}

		return $rows;
	}

	if ( isset( $settings['countries_table'] ) && (string) $settings['countries_table'] === $table ) {
		$name = ! empty( $settings['countries_name_field'] ) ? (string) $settings['countries_name_field'] : 'Name';

		foreach ( WPCPM_Countries::all() as $id => $country ) {
			$rows[] = array(
				'id'          => (string) $id,
				'createdTime' => gmdate( 'Y-m-d\TH:i:s.000\Z' ),
				'fields'      => array(
					$name                            => (string) $country['name'],
					WPCPM_Countries::FIELD_CONTACT  => (string) $country['manager'],
					WPCPM_Countries::FIELD_EMAIL    => (string) $country['email'],
					WPCPM_Countries::FIELD_CALENDLY => (string) $country['calendly'],
				),
			);
		}

		return $rows;
	}

	return null;
}

/*
 * The copy holds no Airtable token (none is copied from live), and the client refuses before any request
 * without one. While filming, an empty token reads as an invented placeholder, in memory only: it is never
 * saved, and it goes nowhere, because every request to Airtable is answered above before it is made.
 */
add_filter(
	'option_wpcpm_settings',
	static function ( $value ) {
		if ( is_array( $value ) && empty( $value['api_token'] ) ) {
			$value['api_token'] = 'patFilmingStandIn.NotARealToken';
		}
		return $value;
	}
);

/*
 * 2. Real names off the screen.
 *
 * (a) The program's queues are limited to the invented entries made for the clips: an application, a
 * registration, a request, a report or an agreement whose title names no invented school or person, and no
 * invented record (every record the stand-in makes starts "recFilm"), is in no list while this plugin is loaded.
 */
function wpcpm_filming_queued_types() {
	return array( 'wpcpm_inst_app', 'wpcpm_ment_app', 'wpcpm_stu_reg', 'wpcpm_inst_request', 'wpcpm_inst_report', 'wpcpm_agreement' );
}

function wpcpm_filming_invented_words() {
	return array_merge( wpcpm_filming_invented_schools(), array( 'Example', 'recFilm' ) );
}

add_action(
	'pre_get_posts',
	static function ( $query ) {
		$type  = $query->get( 'post_type' );
		$types = array_intersect( (array) $type, wpcpm_filming_queued_types() );
		if ( empty( $types ) ) {
			return;
		}
		global $wpdb;
		$likes = array();
		$args  = array();
		foreach ( wpcpm_filming_invented_words() as $word ) {
			$likes[] = 'post_title LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $word ) . '%';
		}
		$in  = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ($in) AND (" . implode( ' OR ', $likes ) . ')';
		$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, array_merge( array_values( $types ), $args ) ) ) ); // phpcs:ignore WordPress.DB
		$was = $query->get( 'post__in' );
		if ( ! empty( $was ) ) {
			$ids = array_values( array_intersect( $ids, array_map( 'intval', (array) $was ) ) );
		}
		$query->set( 'post__in', $ids ? $ids : array( 0 ) );
	}
);

/*
 * (b) Any real institution's or sponsor's name left in a page's text is replaced with a stand-in
 * from the fixed list. The list of real names is read once from the site's own stored lists (the
 * institutions index, the sponsors' rows, the institution records', applications' and sponsors'
 * titles) and kept for the request only. Only the text between tags is touched, never a tag, a
 * script or a style, so no form field or link changes.
 */
function wpcpm_filming_real_names() {
	static $names = null;
	if ( null !== $names ) {
		return $names;
	}
	$found = array();
	$add   = static function ( $v ) use ( &$found ) {
		if ( is_string( $v ) ) {
			$v = trim( html_entity_decode( wp_strip_all_tags( $v ), ENT_QUOTES, 'UTF-8' ) );
			// An invented record's own ID ("recFilm...", the title of its institution record) is not a real name:
			// replacing it would change the record a form posts.
			if ( mb_strlen( $v ) >= 3 && ! in_array( $v, wpcpm_filming_invented_schools(), true ) && 0 !== strpos( $v, 'recFilm' ) ) {
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
	// A school's website names it too: its host is replaced like its name.
	if ( class_exists( 'WPCPM_Institutions_Index' ) ) {
		foreach ( (array) WPCPM_Institutions_Index::rows() as $row ) {
			if ( ! empty( $row['website'] ) && is_string( $row['website'] ) ) {
				$h = preg_replace( '#^(https?://)?(www\\.)?#i', '', strtolower( trim( $row['website'] ) ) );
				$h = preg_replace( '#[/?\\#].*$#', '', $h );
				if ( strlen( $h ) >= 6 && false === strpos( $h, 'example' ) ) {
					$found[ $h ] = true;
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
 * Replace the real names in the text parts of one page.
 *
 * @param string $html The page.
 * @return string
 */
function wpcpm_filming_replace_names( $html ) {
	if ( ! is_string( $html ) || '' === $html || false === stripos( $html, '<html' ) ) {
		return $html;
	}
	// The copy's own two banners (its safety plugin's and the scrub's) are not on the real site: left out of a clip's
	// frame. The screenshots of the layout audit keep them (option wpcpm_filming_keep_banners).
	if ( ! get_option( 'wpcpm_filming_keep_banners' ) ) {
		$html = preg_replace( '#<div class="notice notice-(?:warning|info)"><p><strong>(?:Local copy of wordpresseducation\.org\.|Trial copy: every person\'s name here is a pseudonym\.)</strong>.*?</p></div>#s', '', $html );
	}
	$names = wpcpm_filming_real_names();
	if ( empty( $names ) ) {
		return $html;
	}
	$stand = wpcpm_filming_stand_in_names();
	$pats  = array();
	$jpats = array();
	$jreps = array();
	$reps  = array();
	foreach ( $names as $i => $name ) {
		// As stored, escaped, and as WordPress prints it with curly quotes (wptexturize), in characters and entities.
		$curly = str_replace( "'", "\u{2019}", $name );
		$forms = array_unique( array( $name, esc_html( $name ), htmlspecialchars( $name, ENT_QUOTES, 'UTF-8' ), $curly, str_replace( "'", '&#8217;', $name ), str_replace( "'", '&rsquo;', $name ), wptexturize( $name ), htmlentities( $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), htmlentities( $name, ENT_QUOTES, 'UTF-8' ), mb_encode_numericentity( $name, array( 0x80, 0x10FFFF, 0, 0x1FFFFF ), 'UTF-8' ), mb_encode_numericentity( $name, array( 0x80, 0x10FFFF, 0, 0x1FFFFF ), 'UTF-8', true ), Normalizer::normalize( $name, Normalizer::FORM_D ) ) );
		$stand_in = ( false === strpos( $name, ' ' ) && false !== strpos( $name, '.' ) ) ? 'sample-college.example.org' : $stand[ $i % count( $stand ) ];
		foreach ( $forms as $form ) {
			$pats[] = '/(?<![\p{L}\p{N}])' . preg_quote( $form, '/' ) . '(?![\p{L}\p{N}])/u' . ( mb_strlen( $name ) > 4 ? 'i' : '' );
			$reps[] = $stand_in;
		}
		// Inside JSON a page's script reads to draw a list (accents written \u00e0, slashes \/), in a script or an attribute.
		$json = substr( wp_json_encode( $name ), 1, -1 );
		foreach ( array_unique( array( $json, esc_attr( $json ), str_replace( '\\/', '/', $json ) ) ) as $form ) {
			if ( $form !== $name ) {
				$jpats[] = '/' . preg_quote( $form, '/' ) . '/' . ( mb_strlen( $name ) > 4 ? 'i' : '' );
				$jreps[] = $stand_in;
			}
		}
	}
	// WordPress.org's own footer links to WordPress.com, also a sponsor's name: the footer is left as it is.
	$tail = '';
	$cut  = strpos( $html, '<footer class="global-footer' );
	if ( false !== $cut ) {
		$tail = substr( $html, $cut );
		$html = substr( $html, 0, $cut );
	}
	$parts   = preg_split( '/(<script\b.*?<\/script>|<style\b.*?<\/style>|<textarea\b.*?<\/textarea>|<[^>]*>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	$changed = 0;
	foreach ( $parts as $k => $part ) {
		if ( '' === $part ) {
			continue;
		}
		if ( '<' === $part[0] ) {
			// A field shows its value and placeholder, and a text box its contents: those are replaced too.
			// Never a script, a style, a name, an id or a link.
			if ( 0 === stripos( $part, '<textarea' ) ) {
				$new = preg_replace_callback( '#^(<textarea\b[^>]*>)(.*)(</textarea>)$#is', static function ( $m ) use ( $pats, $reps ) { return $m[1] . preg_replace( $pats, $reps, $m[2] ) . $m[3]; }, $part );
			} elseif ( preg_match( '#^<(input|option)\b#i', $part ) ) {
				$new = preg_replace_callback( '#\b(value|placeholder)="([^"]*)"#i', static function ( $m ) use ( $pats, $reps ) { return $m[1] . '="' . preg_replace( $pats, $reps, $m[2] ) . '"'; }, $part );
			} else {
				continue;
			}
			if ( is_string( $new ) && $new !== $part ) {
				$parts[ $k ] = $new;
				++$changed;
			}
			continue;
		}
		$new = preg_replace( $pats, $reps, $part );
		if ( is_string( $new ) && $new !== $part ) {
			$parts[ $k ] = $new;
			++$changed;
		}
	}
	if ( $changed ) {
		wpcpm_filming_log( 'names-replaced', $changed . ' text parts on ' . strtok( isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '', '?' ) ); // phpcs:ignore WordPress.Security
	}
	$out = implode( '', $parts );
	$out .= $tail;
	// Scripts printed after the footer carry JSON too: the JSON forms are replaced in the whole page.
	if ( ! empty( $jpats ) ) {
		$out = preg_replace( $jpats, $jreps, $out );
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
		ob_start( 'wpcpm_filming_replace_names' );
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
 * The mail to an example.org address is still swallowed by the copy's safety plugin; this only
 * keeps the confirmation link of such a mail, so the invented applicant can confirm off camera.
 * It reads the mail and hands it on unchanged.
 */
add_filter(
	'wp_mail',
	static function ( $atts ) {
		$to = isset( $atts['to'] ) ? ( is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : (string) $atts['to'] ) : '';
		if ( preg_match( '/@example\.org\b/i', $to ) && isset( $atts['message'] ) && preg_match_all( '#http://localhost:8900/[^\s"\'<>]+#', (string) $atts['message'], $m ) ) {
			foreach ( $m[0] as $link ) {
				if ( false !== strpos( $link, 'verify' ) || false !== strpos( $link, 'confirm' ) ) {
					file_put_contents( WP_CONTENT_DIR . '/wpcpm-filming-links.log', $link . "\n", FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				}
			}
		}
		return $atts;
	},
	PHP_INT_MAX
);
