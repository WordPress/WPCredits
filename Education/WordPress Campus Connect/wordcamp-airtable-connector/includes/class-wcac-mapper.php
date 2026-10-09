<?php
/**
 * Translates WordCamp.org REST payloads into Airtable field arrays.
 *
 * @package WordCamp_Airtable_Connector
 */

defined( 'ABSPATH' ) || exit;

/**
 * All knowledge of the source payload shape lives here.
 */
class WCAC_Mapper {

	/**
	 * Flatten a rendered HTML string to a single line of plain text.
	 *
	 * @param mixed $value Raw value, possibly an array( 'rendered' => ... ).
	 * @param int   $limit Maximum characters; 0 for no limit.
	 * @return string
	 */
	public static function text( $value, $limit = 500 ) {
		if ( is_array( $value ) && isset( $value['rendered'] ) ) {
			$value = $value['rendered'];
		}

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$out = html_entity_decode( wp_strip_all_tags( (string) $value, true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$out = trim( preg_replace( '/\s+/u', ' ', $out ) );

		return $limit > 0 ? mb_substr( $out, 0, $limit ) : $out;
	}

	/**
	 * Flatten rendered HTML to plain text, preserving paragraph breaks.
	 *
	 * @param mixed $value Raw value, possibly an array( 'rendered' => ... ).
	 * @param int   $limit Maximum characters.
	 * @return string
	 */
	public static function block( $value, $limit = 20000 ) {
		if ( is_array( $value ) && isset( $value['rendered'] ) ) {
			$value = $value['rendered'];
		}

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$out = html_entity_decode( wp_strip_all_tags( (string) $value, false ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$out = preg_replace( '/[ \t]+/u', ' ', $out );
		$out = preg_replace( '/\n{3,}/u', "\n\n", $out );

		return mb_substr( trim( $out ), 0, $limit );
	}

	/**
	 * Coerce a value to an integer, or null when it is blank.
	 *
	 * Central returns empty strings rather than nulls for unset meta, and an
	 * empty string in an Airtable number cell is an error even with typecast.
	 *
	 * @param mixed $value Raw value.
	 * @return int|null
	 */
	public static function int_or_null( $value ) {
		if ( is_array( $value ) || null === $value || '' === $value ) {
			return null;
		}

		return (int) $value;
	}

	/**
	 * Coerce a value to a float, or null when it is blank.
	 *
	 * @param mixed $value Raw value.
	 * @return float|null
	 */
	public static function float_or_null( $value ) {
		if ( is_array( $value ) || null === $value || '' === $value ) {
			return null;
		}

		return (float) $value;
	}

	/**
	 * A Unix timestamp as a Y-m-d date string, or null.
	 *
	 * The wcpt date meta is named "Start Date (YYYY-mm-dd)" but actually holds
	 * a Unix timestamp -- an easy trap.
	 *
	 * @param mixed $value Raw meta value.
	 * @return string|null
	 */
	public static function stamp_to_date( $value ) {
		$stamp = self::int_or_null( $value );

		return $stamp ? gmdate( 'Y-m-d', $stamp ) : null;
	}

	/**
	 * A Unix timestamp as an ISO-8601 UTC datetime, or null.
	 *
	 * @param mixed $value Raw meta value.
	 * @return string|null
	 */
	public static function stamp_to_datetime( $value ) {
		$stamp = self::int_or_null( $value );

		return $stamp ? gmdate( 'Y-m-d\TH:i:s\Z', $stamp ) : null;
	}

	/**
	 * Central's modified_gmt (which carries no zone suffix) as ISO-8601 UTC.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	public static function gmt_to_datetime( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}

		return $value . 'Z';
	}

	/**
	 * Now, as ISO-8601 UTC.
	 *
	 * @return string
	 */
	public static function now() {
		return gmdate( 'Y-m-d\TH:i:s\Z' );
	}

	/**
	 * Resolve a list of term IDs to a comma-separated list of names.
	 *
	 * @param mixed $ids  Array of term IDs.
	 * @param array $map  Term ID => name.
	 * @return string
	 */
	public static function terms( $ids, array $map ) {
		if ( ! is_array( $ids ) ) {
			return '';
		}

		$names = array();

		foreach ( $ids as $id ) {
			if ( isset( $map[ (int) $id ] ) ) {
				$names[] = $map[ (int) $id ];
			}
		}

		return implode( ', ', $names );
	}

	/**
	 * wcpt post-status slug => the Campus Connect Events "Status" label.
	 *
	 * Slugs verbatim from WordCamp_Loader::get_post_statuses() (wcpt plugin,
	 * production, read 2026-09-02). WordPress caps post_status at 20 characters,
	 * which is why several slugs are truncated mid-word -- do not "correct"
	 * them. It is not wcpt-needs-orientation, not wcpt-more-info-reqd, not
	 * wcpt-needs-budget-review.
	 *
	 * Three labels intentionally differ from wcpt's own: the Airtable field says
	 * Scheduled / Closed / Needs to Fill Out Listing where wcpt says WordCamp
	 * Scheduled / WordCamp Closed / Needs to Fill Out WordCamp Listing.
	 *
	 * wcpt-needs-mentor and wcpt-needs-polldaddy appear elsewhere in that file
	 * but not in get_post_statuses(); they are legacy and are deliberately
	 * absent here, so a post still carrying one is reported as unmapped.
	 *
	 * Nineteen slugs, but a Campus Connect event can only hold the NINE that
	 * WordCamp_Loader::get_campus_connect_statuses() lists: needs-vetting,
	 * needs-action, needs-orientati, more-info-reque, approved-pre-pl,
	 * scheduled, closed, rejected, cancelled. WordCamp_Admin::get_post_statuses()
	 * swaps to that list for a Campus Connect post and unsets wcpt-needs-action
	 * for every other post type, which makes Needs Action the one status that is
	 * exclusive to this report. The other ten entries stay because the report
	 * queries post_status => 'any', so an event bulk-edited into a
	 * WordCamp-pipeline status before the Campus Connect list existed still
	 * resolves rather than being reported as unmapped.
	 *
	 * The labels below are wcpt's GLOBAL ones, and that is correct here: the
	 * REST report emits raw slugs, so these strings are only ever Airtable
	 * option names. They are NOT what a Campus Connect post shows in wp-admin.
	 * get_campus_connect_statuses() renames four of the nine -- Needs
	 * Orientation, Approved For Pre-Planning, WordCamp Scheduled, WordCamp
	 * Closed -- so rebuilding the Airtable options from that dropdown would put
	 * four mappings outside CAMPUS_STATUS_PRESENT at once, and four columns
	 * would quietly stop updating.
	 */
	const CAMPUS_STATUS = array(
		'wcpt-needs-vetting'   => 'Needs Vetting',
		'wcpt-needs-orientati' => 'Needs Orientation/Interview',
		'wcpt-more-info-reque' => 'On Hold',
		'wcpt-interview-sched' => 'Interview/Orientation Scheduled',
		'wcpt-rejected'        => 'Declined',
		'wcpt-cancelled'       => 'Cancelled',
		'wcpt-approved-pre-pl' => 'Approved for Pre-Planning Pending Agreement',
		'wcpt-pre-planning'    => 'In Pre-Planning',
		'wcpt-needs-fill-list' => 'Needs to Fill Out Listing',
		'wcpt-scheduled'       => 'Scheduled',
		'wcpt-closed'          => 'Closed',
		'wcpt-needs-email'     => 'Needs E-mail Address',
		'wcpt-needs-site'      => 'Needs Site',
		'wcpt-needs-pre-plann' => 'Needs to be Added to Pre-Planning Schedule',
		'wcpt-needs-budget-re' => 'Needs Budget Review',
		'wcpt-budget-rev-sche' => 'Budget Review Scheduled',
		'wcpt-needs-contract'  => 'Needs Contract to be Signed',
		'wcpt-needs-schedule'  => 'Needs to be Added to Official Schedule',
		'wcpt-needs-action'    => 'Needs Action',
	);

	/**
	 * Report DISPLAY labels that differ in spelling from the Airtable choice
	 * meaning the same thing.
	 *
	 * The report can hand back display labels rather than slugs, and those
	 * labels are not guaranteed to match the curated singleSelect. On
	 * 2026-10-07 the report began spelling it "Canceled" where Airtable has
	 * "Cancelled", which sent 14 rows down the unmapped path and left their
	 * Status unwritten.
	 *
	 * This is a spelling bridge only. The resolved label still has to appear
	 * in the allowed list, so an alias cannot be used to smuggle in a choice
	 * the destination does not hold, and adding a second spelling to Airtable
	 * would split WPCC-Tracker's grouping across two options meaning the same
	 * thing.
	 */
	const CAMPUS_STATUS_ALIAS = array(
		'Canceled' => 'Cancelled',
	);

	/**
	 * The choices that existed on Status (fldosgc5sA7VIfCns, tbld8niqsLWyNbcVF)
	 * when this was written, read from the live base on 2026-09-02.
	 *
	 * A label outside this list plus the operator's campus_extra_status list is
	 * NOT written: typecast CREATES an unknown singleSelect option rather than
	 * rejecting it, so an unreviewed label would silently pollute the field and
	 * split WPCC-Tracker's grouping.
	 */
	const CAMPUS_STATUS_PRESENT = array(
		'Closed',
		'Scheduled',
		'In Pre-Planning',
		'Needs Vetting',
		'Needs Orientation/Interview',
		'Interview/Orientation Scheduled',
		'Approved for Pre-Planning Pending Agreement',
		'Needs to Fill Out Listing',
		'On Hold',
		'Cancelled',
		'Declined',
	);

	/**
	 * Labels approved to be written before the option exists in Airtable.
	 *
	 * Deliberately separate from CAMPUS_STATUS_PRESENT, which is a factual
	 * snapshot of the live singleSelect. Adding an entry there that is not
	 * actually on the field would make that const a lie, and the next person to
	 * refresh the snapshot from the live base would silently drop it again.
	 *
	 * Needs Action (wcpt-needs-action) is here because it is the one status
	 * exclusive to Campus Connect -- get_campus_connect_statuses() lists it and
	 * WordCamp_Admin::get_post_statuses() unsets it for everything else -- and
	 * the Status field was built from the WordCamp pipeline, so it has no such
	 * option. Without this entry a Campus Connect event sitting in Needs Action
	 * resolves to 'absent' and its Status cell is frozen on every run: no error,
	 * no write, just a column that never moves.
	 *
	 * Typecast creates the option on first write. That is safe only because the
	 * label is verified against upstream rather than echoed from the feed, which
	 * is the distinction campus_allowed_status() exists to enforce. Anything
	 * else belongs in the operator's campus_extra_status setting, not here.
	 */
	const CAMPUS_STATUS_PREAPPROVED = array(
		'Needs Action',
	);

	/**
	 * The keys every Campus Connect report row is expected to carry.
	 *
	 * campus_event() reads by explicit key and pick() returns '' for an absent
	 * one, so a RENAMED key is otherwise a permanent, completely silent no-op:
	 * City and Country would simply stop updating on all 142 rows and the run
	 * would log "0 created, 142 updated". This const is the only thing in the
	 * design that can see that.
	 */
	const CAMPUS_KEYS_REQUIRED = array(
		'ID',
		'Name',
		'Status',
		'Start Date (YYYY-mm-dd)',
		'End Date (YYYY-mm-dd)',
		'Venue Name',
		'_venue_city',
		'_venue_country_name',
		'Number of Anticipated Attendees',
		'Actual Attendees',
		'Created',
		'URL',
	);

	/**
	 * Keys the report sends only to a caller holding manage_options.
	 *
	 * Their presence or absence is the observable form of the 13-vs-14
	 * capability difference, which is why job_campus_connect() persists the
	 * observed key set rather than only branching on it.
	 */
	const CAMPUS_KEYS_OPTIONAL = array(
		'Series Event',
	);

	/**
	 * Keys the report sends that campus_event() deliberately never reads.
	 *
	 * Organizer Name has no counterpart column in tbld8niqsLWyNbcVF, so its
	 * absence cannot stop a column updating and its presence is not drift.
	 * Listing it in neither const would move the same false alarm from
	 * missing to unexpected, raised once per run in the middle of the
	 * runbook.
	 */
	const CAMPUS_KEYS_IGNORED = array(
		'Organizer Name',
	);

	/**
	 * Plain text from a raw report meta value.
	 *
	 * NOT text() above. That runs wp_strip_all_tags() because the wp/v2 payloads
	 * are rendered HTML; the report's values are raw meta, so a venue named
	 * "Class of <2020> Hall" would come out as "Class of Hall" -- non-empty, and
	 * therefore written over the correct hand-imported value. Its 255/128 caps
	 * are a wp/v2 convention too, not an Airtable constraint: singleLineText has
	 * no such limit, and truncating here would replace a fuller hand-imported
	 * Institution Name with a shorter one.
	 *
	 * sanitize_text_field() is deliberately not used either, for exactly the
	 * same reason: it calls wp_strip_all_tags() on any value containing "<", so
	 * it mangles that same venue name, and it deletes percent-encoded octets, so
	 * "Room %20" loses three characters. What is left here is only the part that
	 * cannot corrupt a legitimate value -- invalid UTF-8 is dropped, control
	 * characters become spaces, and runs of whitespace collapse to one.
	 *
	 * @param mixed $value Raw report value.
	 * @param int   $limit Maximum characters; 0 for no limit. Default 0.
	 * @return string
	 */
	public static function campus_text( $value, $limit = 0 ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$out = wp_check_invalid_utf8( (string) $value );

		/*
		 * The report HTML-encodes what it returns, so "St. Xavier's" arrives as
		 * "St. Xavier&#039;s" and would be stored that way. WPCC-Tracker decodes
		 * on read, so the dashboard looked right either way, which is exactly
		 * how the entities sat unnoticed in Airtable, where anyone reading the
		 * table sees them raw.
		 *
		 * Decoding also makes the write idempotent: an encoded value never
		 * matched the decoded cell beside it, so every run reported the same
		 * cell as changed for ever.
		 *
		 * Before the control-character strip, so an entity decoding to one is
		 * still caught, and before the truncation, so a limit can never cut an
		 * entity in half and leave "&am" in the cell.
		 */
		$out = html_entity_decode( $out, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		$out = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $out );
		$out = trim( (string) preg_replace( '/\s+/u', ' ', $out ) );

		return $limit > 0 ? mb_substr( $out, 0, $limit ) : $out;
	}

	/**
	 * A report number, or null when the value is not actually numeric.
	 *
	 * int_or_null() above casts, and (int) 'n/a', (int) 'TBD' and (int) '~300'
	 * are all 0. Zero is neither null nor '', so the omit rule would KEEP the key
	 * and Airtable would overwrite a hand-imported 300 with 0 -- a plausible
	 * wrong number, which is worse than a blank. This returns null instead, so
	 * the key is omitted and the cell is left alone.
	 *
	 * A genuine "0" IS numeric and returns int 0, which must still be written:
	 * see the strict emptiness test in campus_event().
	 *
	 * A JSON boolean is a parsed value, not a miss, and returns 1 or 0.
	 *
	 * @param mixed $value Raw report value.
	 * @return int|null
	 */
	public static function campus_int( $value ) {
		if ( ! is_scalar( $value ) ) {
			return null;
		}

		// (string) false is '', and is_numeric( '' ) is false, so without this a
		// report row of { "Series Event": false } returns null and the key is
		// omitted -- making the field write-once-upward, the exact opposite of
		// the invariant campus_event()'s strict emptiness test is written to
		// guarantee. It is invisible too, because $number()'s miss counter only
		// fires on a non-empty raw string. A bool is parsed, not a miss, so
		// int_unparsed stays correct.
		if ( is_bool( $value ) ) {
			return $value ? 1 : 0;
		}

		$raw = trim( (string) $value );

		return is_numeric( $raw ) ? (int) $raw : null;
	}

	/**
	 * A report date that may arrive as a Unix timestamp, a Y-m-d string, or a
	 * full ISO-8601 datetime with an offset.
	 *
	 * Neither existing helper is safe here. stamp_to_date() routes through
	 * int_or_null() first, so the string '2026-05-14' casts to int 2026 and
	 * gmdate('Y-m-d', 2026) silently returns 1970-01-01 on every row.
	 * gmt_to_datetime() only appends 'Z'. Nothing in this repo settles which
	 * shape the report sends, so accept the three plausible ones -- and convert
	 * an offset datetime to UTC rather than substr()ing it, because
	 * 2026-05-15T00:30:00+02:00 is 2026-05-14 in UTC and a naive substr writes a
	 * one-day-shifted date that no sanity window can catch.
	 *
	 * @param mixed $value Raw report value.
	 * @return string|null Y-m-d, or null when the shape is not recognised or the
	 *                     date is outside 2006-01-01 .. now + 5 years.
	 */
	public static function campus_date( $value ) {
		if ( ! is_scalar( $value ) ) {
			return null;
		}

		$raw = trim( (string) $value );

		if ( '' === $raw ) {
			return null;
		}

		$date = null;

		if ( ctype_digit( $raw ) ) {
			$date = gmdate( 'Y-m-d', (int) $raw );
		} elseif ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $parts ) ) {
			// The sanity window below compares STRINGS, so it cannot see that
			// 2026-02-30 or 2026-13-45 is not a real date -- both sort inside
			// the window. With typecast on, Airtable's lenient parser rolls
			// 2026-02-30 forward to 2026-03-02, so a hand-imported Start Date
			// is replaced by a wrong but entirely plausible one. Leaving $date
			// null drops the key instead, and counts it into date_unparsed.
			if ( checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
				$date = $raw;
			}
		} elseif ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})[T ]/', $raw, $parts ) ) {
			// No offset and no Z means the report is speaking UTC, so say so
			// rather than letting the server's default zone decide.
			$zoned = (bool) preg_match( '/(?:Z|[+-]\d{2}:?\d{2})$/i', $raw );
			$stamp = strtotime( $zoned ? $raw : $raw . ' UTC' );

			// strtotime() ROLLS an impossible calendar date rather than refusing
			// it -- 2026-02-30T00:00:00Z comes back as 2026-03-02 -- so the false
			// check is not the validation it looks like. Check the RAW parts, not
			// the result: a genuine offset shift legitimately moves the day
			// (2026-05-15T00:30:00+02:00 is 2026-05-14 in UTC) and must survive.
			if ( false !== $stamp && checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
				$date = gmdate( 'Y-m-d', $stamp );
			}
		}

		if ( null === $date ) {
			return null;
		}

		// The first WordCamp was 2006, and nothing is scheduled five years out.
		if ( $date < '2006-01-01' || $date > gmdate( 'Y-m-d', time() + 5 * YEAR_IN_SECONDS ) ) {
			return null;
		}

		return $date;
	}

	/**
	 * A report URL, or '' when it is not a usable absolute http(s) URL.
	 *
	 * esc_url_raw() prepends http:// to a scheme-less value, which would
	 * downgrade a hand-imported https row into a working-looking wrong one, and
	 * it happily normalises drifted garbage into something plausible. Require
	 * the scheme instead.
	 *
	 * @param mixed $value Raw report value.
	 * @return string
	 */
	public static function campus_url( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		// Decoded for the reason campus_text() gives, and for one of its own: an
		// '&amp;' left in a query string survives into the stored URL and takes
		// the link with it.
		$raw = trim( html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

		if ( ! preg_match( '#^https?://#i', $raw ) ) {
			return '';
		}

		$clean = esc_url_raw( $raw, array( 'http', 'https' ) );

		if ( '' === $clean || ! wp_http_validate_url( $clean ) ) {
			return '';
		}

		return $clean;
	}

	/**
	 * The Status labels this site may write: the ones read from the live base at
	 * design time, the ones approved in advance against upstream, plus any the
	 * operator has explicitly approved after adding them to the singleSelect by
	 * hand.
	 *
	 * The intersect with CAMPUS_STATUS is what keeps this a default-deny list: a
	 * label no slug maps to cannot be written however it got into any of the
	 * three sources.
	 *
	 * @return array
	 */
	public static function campus_allowed_status() {
		$extra = preg_split( '/[\r\n]+/', (string) WCAC_Settings::get( 'campus_extra_status' ) );
		$extra = is_array( $extra ) ? array_map( 'trim', $extra ) : array();
		$known = array_values( self::CAMPUS_STATUS );
		$all   = array_merge( self::CAMPUS_STATUS_PRESENT, self::CAMPUS_STATUS_PREAPPROVED, $extra );

		return array_values( array_unique( array_intersect( $all, $known ) ) );
	}

	/**
	 * Resolve a raw report Status value to a writable label.
	 *
	 * Default deny: anything this map does not know, and anything the
	 * destination singleSelect has not been confirmed to hold, resolves to a
	 * null label. campus_event() then omits the key entirely, which in a
	 * PATCH-upsert leaves the cell untouched. Writing the raw slug instead would
	 * mint a new singleSelect option under typecast, and writing null would
	 * clear a curated value.
	 *
	 * @param string $slug    Raw report value.
	 * @param array  $allowed Labels the destination singleSelect will accept:
	 *                        CAMPUS_STATUS_PRESENT plus the operator's approved
	 *                        extras.
	 * @return array array( 'label' => string|null, 'why' => ''|'missing'|'unmapped'|'absent' )
	 */
	public static function campus_status( $slug, array $allowed ) {
		$raw = is_scalar( $slug ) ? trim( (string) $slug ) : '';

		if ( '' === $raw ) {
			return array(
				'label' => null,
				'why'   => 'missing',
			);
		}

		if ( isset( self::CAMPUS_STATUS[ $raw ] ) ) {
			$label = self::CAMPUS_STATUS[ $raw ];

			return array(
				'label' => in_array( $label, $allowed, true ) ? $label : null,
				'why'   => in_array( $label, $allowed, true ) ? '' : 'absent',
			);
		}

		// A display label whose spelling differs from the Airtable choice. Still
		// default-deny: the alias only renames, and the result must be in the
		// allowed list exactly as any other label must.
		if ( isset( self::CAMPUS_STATUS_ALIAS[ $raw ] ) ) {
			$label = self::CAMPUS_STATUS_ALIAS[ $raw ];

			return array(
				'label' => in_array( $label, $allowed, true ) ? $label : null,
				'why'   => in_array( $label, $allowed, true ) ? '' : 'absent',
			);
		}

		// Display-value pass-through. A raw/display split exists in the
		// WordCamp Reports framework and a manage_options caller may already be
		// getting labels, so if the report ever flips from slugs to labels this
		// turns a total silent outage into a no-op.
		if ( in_array( $raw, $allowed, true ) ) {
			return array(
				'label' => $raw,
				'why'   => '',
			);
		}

		return array(
			'label' => null,
			'why'   => 'unmapped',
		);
	}

	/**
	 * Compare an observed report key set against the documented contract.
	 *
	 * A union rather than array_keys( $rows[0] ): one odd row must not raise a
	 * false alarm, and one normal row must not mask a rename that only some rows
	 * show.
	 *
	 * @param array $rows  The report's data list.
	 * @param int   $depth How many rows to union keys over.
	 * @return array array( 'observed' => string[], 'missing' => string[], 'unexpected' => string[], 'series' => bool )
	 */
	public static function campus_key_report( array $rows, $depth = 25 ) {
		$seen  = array();
		$taken = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			foreach ( array_keys( $row ) as $key ) {
				$seen[ (string) $key ] = true;
			}

			++$taken;

			if ( $taken >= (int) $depth ) {
				break;
			}
		}

		$observed = array_keys( $seen );
		sort( $observed );

		$known = array_merge( self::CAMPUS_KEYS_REQUIRED, self::CAMPUS_KEYS_OPTIONAL, self::CAMPUS_KEYS_IGNORED );

		return array(
			'observed'   => $observed,
			'missing'    => array_values( array_diff( self::CAMPUS_KEYS_REQUIRED, $observed ) ),
			'unexpected' => array_values( array_diff( $observed, $known ) ),
			'series'     => in_array( 'Series Event', $observed, true ),
		);
	}

	/**
	 * Map a WordCamp post to the WordCamps table.
	 *
	 * @param array $post REST payload.
	 * @return array
	 */
	public static function wordcamp( array $post ) {
		$coords = isset( $post['_venue_coordinates'] ) && is_array( $post['_venue_coordinates'] )
			? $post['_venue_coordinates']
			: array();

		return array(
			'WordCamp ID'               => (int) $post['id'],
			'Name'                      => self::text( isset( $post['title'] ) ? $post['title'] : '', 255 ),
			'Slug'                      => self::text( isset( $post['slug'] ) ? $post['slug'] : '', 255 ),
			'Status'                    => isset( $post['status'] ) ? (string) $post['status'] : '',
			'Description'               => self::block( isset( $post['content'] ) ? $post['content'] : '', 20000 ),
			'Start Date'                => self::stamp_to_date( self::pick( $post, 'Start Date (YYYY-mm-dd)' ) ),
			'End Date'                  => self::stamp_to_date( self::pick( $post, 'End Date (YYYY-mm-dd)' ) ),
			'Timezone'                  => self::text( self::pick( $post, 'Event Timezone' ), 64 ),
			'Location'                  => self::text( self::pick( $post, 'Location' ), 255 ),
			'City'                      => self::text( self::pick( $post, '_venue_city' ), 128 ),
			'State'                     => self::text( self::pick( $post, '_venue_state' ), 128 ),
			'Country'                   => self::text( self::pick( $post, '_venue_country_name' ), 128 ),
			'Country Code'              => self::text( self::pick( $post, '_venue_country_code' ), 8 ),
			'Latitude'                  => self::float_or_null( isset( $coords['latitude'] ) ? $coords['latitude'] : '' ),
			'Longitude'                 => self::float_or_null( isset( $coords['longitude'] ) ? $coords['longitude'] : '' ),
			'Site URL'                  => esc_url_raw( (string) self::pick( $post, 'URL' ) ),
			'Central Link'              => esc_url_raw( isset( $post['link'] ) ? (string) $post['link'] : '' ),
			'Hashtag'                   => self::text( self::pick( $post, 'WordCamp Hashtag' ), 64 ),
			'Organizer'                 => self::text( self::pick( $post, 'Organizer Name' ), 255 ),
			'Organizer WP.org Username' => self::text( self::pick( $post, 'WordPress.org Username' ), 128 ),
			'Anticipated Attendees'     => self::int_or_null( self::pick( $post, 'Number of Anticipated Attendees' ) ),
			'Max Capacity'              => self::int_or_null( self::pick( $post, 'Maximum Capacity' ) ),
			'Venue Name'                => self::text( self::pick( $post, 'Venue Name' ), 255 ),
			'Venue Address'             => self::block( self::pick( $post, 'Physical Address' ), 2000 ),
			'Virtual Only'              => (bool) self::pick( $post, 'Virtual event only' ),
			'Host Region'               => self::text( self::pick( $post, 'Host region' ), 128 ),
			'Last Modified'             => self::gmt_to_datetime( isset( $post['modified_gmt'] ) ? $post['modified_gmt'] : '' ),
			'Synced At'                 => self::now(),
		);
	}

	/**
	 * Map a Meetup post to the Meetups table.
	 *
	 * Central exposes no meta on this post type, so the row is deliberately thin.
	 *
	 * @param array $post REST payload.
	 * @return array
	 */
	public static function meetup( array $post ) {
		return array(
			'Meetup ID'     => (int) $post['id'],
			'Name'          => self::text( isset( $post['title'] ) ? $post['title'] : '', 255 ),
			'Slug'          => self::text( isset( $post['slug'] ) ? $post['slug'] : '', 255 ),
			'Status'        => isset( $post['status'] ) ? (string) $post['status'] : '',
			'Central Link'  => esc_url_raw( isset( $post['link'] ) ? (string) $post['link'] : '' ),
			'Last Modified' => self::gmt_to_datetime( isset( $post['modified_gmt'] ) ? $post['modified_gmt'] : '' ),
			'Synced At'     => self::now(),
		);
	}

	/**
	 * Map a session post to the Sessions table.
	 *
	 * @param array       $post      REST payload from the camp's own site.
	 * @param int         $wcid      Parent WordCamp post ID on Central.
	 * @param string|null $camp_rec  Airtable record ID of the parent camp, if known.
	 * @param array       $tracks    Term ID => name map for session_track.
	 * @return array
	 */
	public static function session( array $post, $wcid, $camp_rec, array $tracks ) {
		$meta      = isset( $post['meta'] ) && is_array( $post['meta'] ) ? $post['meta'] : array();
		$speakers  = array();
		$duration  = self::int_or_null( isset( $meta['_wcpt_session_duration'] ) ? $meta['_wcpt_session_duration'] : '' );

		if ( isset( $post['session_speakers'] ) && is_array( $post['session_speakers'] ) ) {
			foreach ( $post['session_speakers'] as $speaker ) {
				if ( isset( $speaker['name'] ) ) {
					$speakers[] = self::text( $speaker['name'], 128 );
				}
			}
		}

		$fields = array(
			'Session Key'    => $wcid . ':' . (int) $post['id'],
			'Title'          => self::text( isset( $post['title'] ) ? $post['title'] : '', 255 ),
			'WordCamp ID'    => (int) $wcid,
			'Session ID'     => (int) $post['id'],
			'Session Time'   => self::stamp_to_datetime( isset( $meta['_wcpt_session_time'] ) ? $meta['_wcpt_session_time'] : '' ),
			'Duration (min)' => null === $duration ? null : (int) round( $duration / 60 ),
			'Type'           => isset( $meta['_wcpt_session_type'] ) ? (string) $meta['_wcpt_session_type'] : '',
			'Track'          => self::terms( isset( $post['session_track'] ) ? $post['session_track'] : array(), $tracks ),
			'Categories'     => self::text( isset( $post['session_cats_rendered'] ) ? $post['session_cats_rendered'] : '', 255 ),
			'Speakers'       => mb_substr( implode( ', ', $speakers ), 0, 500 ),
			'Slides URL'     => esc_url_raw( isset( $meta['_wcpt_session_slides'] ) ? (string) $meta['_wcpt_session_slides'] : '' ),
			'Session URL'    => esc_url_raw( isset( $post['link'] ) ? (string) $post['link'] : '' ),
			'Last Modified'  => self::gmt_to_datetime( isset( $post['modified_gmt'] ) ? $post['modified_gmt'] : '' ),
			'Synced At'      => self::now(),
		);

		if ( $camp_rec ) {
			$fields['WordCamp'] = array( $camp_rec );
		}

		return $fields;
	}

	/**
	 * Map a speaker post to the Speakers table.
	 *
	 * @param array       $post     REST payload from the camp's own site.
	 * @param int         $wcid     Parent WordCamp post ID on Central.
	 * @param string|null $camp_rec Airtable record ID of the parent camp, if known.
	 * @return array
	 */
	public static function speaker( array $post, $wcid, $camp_rec ) {
		$meta = isset( $post['meta'] ) && is_array( $post['meta'] ) ? $post['meta'] : array();

		$fields = array(
			'Speaker Key'     => $wcid . ':' . (int) $post['id'],
			'Name'            => self::text( isset( $post['title'] ) ? $post['title'] : '', 255 ),
			'WordCamp ID'     => (int) $wcid,
			'Speaker ID'      => (int) $post['id'],
			'WP.org Username' => self::text( isset( $meta['_wcpt_user_name'] ) ? $meta['_wcpt_user_name'] : '', 128 ),
			'Bio'             => self::block( isset( $post['excerpt'] ) ? $post['excerpt'] : '', 5000 ),
			'Speaker URL'     => esc_url_raw( isset( $post['link'] ) ? (string) $post['link'] : '' ),
			'Last Modified'   => self::gmt_to_datetime( isset( $post['modified_gmt'] ) ? $post['modified_gmt'] : '' ),
			'Synced At'       => self::now(),
		);

		if ( $camp_rec ) {
			$fields['WordCamp'] = array( $camp_rec );
		}

		return $fields;
	}

	/**
	 * Map a sponsor post to the Sponsors table.
	 *
	 * @param array       $post     REST payload from the camp's own site.
	 * @param int         $wcid     Parent WordCamp post ID on Central.
	 * @param string|null $camp_rec Airtable record ID of the parent camp, if known.
	 * @param array       $levels   Term ID => name map for sponsor_level.
	 * @return array
	 */
	public static function sponsor( array $post, $wcid, $camp_rec, array $levels ) {
		$meta = isset( $post['meta'] ) && is_array( $post['meta'] ) ? $post['meta'] : array();

		$fields = array(
			'Sponsor Key'   => $wcid . ':' . (int) $post['id'],
			'Name'          => self::text( isset( $post['title'] ) ? $post['title'] : '', 255 ),
			'WordCamp ID'   => (int) $wcid,
			'Sponsor ID'    => (int) $post['id'],
			'Level'         => self::terms( isset( $post['sponsor_level'] ) ? $post['sponsor_level'] : array(), $levels ),
			'Website'       => esc_url_raw( isset( $meta['_wcpt_sponsor_website'] ) ? (string) $meta['_wcpt_sponsor_website'] : '' ),
			'Sponsor URL'   => esc_url_raw( isset( $post['link'] ) ? (string) $post['link'] : '' ),
			'Last Modified' => self::gmt_to_datetime( isset( $post['modified_gmt'] ) ? $post['modified_gmt'] : '' ),
			'Synced At'     => self::now(),
		);

		if ( $camp_rec ) {
			$fields['WordCamp'] = array( $camp_rec );
		}

		return $fields;
	}

	/**
	 * Map one Campus Connect report row to the Campus Connect Events table.
	 *
	 * UNLIKE every other mapper in this file, this one emits a key ONLY when the
	 * report carries a usable value. Airtable's PATCH-upsert leaves omitted
	 * fields untouched, so this payload cannot clear a cell -- which matters
	 * because the 142 destination rows were imported by hand and hold curated
	 * values the report does not have. Do not "normalise" it to match
	 * wordcamp() above.
	 *
	 * That guarantee is only as good as the coercers: campus_int() exists
	 * because (int) 'n/a' is 0, which is neither null nor '' and would therefore
	 * be WRITTEN. Route new numeric fields through campus_int(), never
	 * int_or_null().
	 *
	 * Synced At IS written here, unconditionally, and is the one deliberate
	 * exception to the omit-unless-usable rule above: it is this plugin's own
	 * stamp rather than a report value, so there is no curated cell for it to
	 * clobber -- the column did not exist until the tracker needed to report
	 * how old the Central data behind these rows is. It is kept out of the
	 * --dry-run field list on purpose, because a cell that changes on every
	 * run would bury the diffs that matter.
	 *
	 * Last Modified is still absent: that field does not exist on this table,
	 * and typecast forgives an unknown value but never an unknown field
	 * NAME -- that is a 422 that kills the whole chunk.
	 *
	 * @param array $row     One entry from the report's `data` list.
	 * @param array $allowed Writable Status labels, from campus_allowed_status().
	 * @return array array(
	 *               'fields' => array,   Airtable field array; empty when unusable.
	 *               'id'     => int,     Merge value; 0 means "drop this row".
	 *               'status' => string,  ''|'missing'|'unmapped'|'absent'
	 *               'slug'   => string,  The raw Status value, for reporting.
	 *               'notes'  => array,   'date_unparsed' => int, 'int_unparsed' => int
	 *             )
	 */
	public static function campus_event( array $row, array $allowed ) {
		$notes = array(
			'date_unparsed' => 0,
			'int_unparsed'  => 0,
		);

		$raw_status = self::pick( $row, 'Status' );
		$resolved   = self::campus_status( $raw_status, $allowed );
		$slug       = is_scalar( $raw_status ) ? trim( (string) $raw_status ) : '';
		$id         = self::campus_int( self::pick( $row, 'ID' ) );
		$id         = null === $id ? 0 : (int) $id;

		if ( $id < 1 ) {
			return array(
				'fields' => array(),
				'id'     => 0,
				'status' => $resolved['why'],
				'slug'   => $slug,
				'notes'  => $notes,
			);
		}

		$fields = array( 'WordCamp ID' => $id );

		// This plugin's own stamp, not a report value, so it is written every
		// run and never withheld. The tracker reads the newest value in this
		// column to say how old the Central data is; a blank column therefore
		// means Central has never been read and the rows are still the manual
		// import. See the note in this method's docblock.
		$fields['Synced At'] = self::now();

		// Strict emptiness, never empty(): int 0 and "0" are genuine values on
		// Actual Attendees and Series Event, and dropping them would mean a
		// wrong non-zero count could never be corrected back down.
		$put = function ( $key, $value ) use ( &$fields ) {
			if ( null !== $value && '' !== $value ) {
				$fields[ $key ] = $value;
			}
		};

		$date = function ( $key ) use ( $row, &$notes ) {
			$raw   = self::pick( $row, $key );
			$value = self::campus_date( $raw );

			if ( null === $value && is_scalar( $raw ) && '' !== trim( (string) $raw ) ) {
				++$notes['date_unparsed'];
			}

			return $value;
		};

		$number = function ( $key ) use ( $row, &$notes ) {
			$raw   = self::pick( $row, $key );
			$value = self::campus_int( $raw );

			if ( null === $value && is_scalar( $raw ) && '' !== trim( (string) $raw ) ) {
				++$notes['int_unparsed'];
			}

			return $value;
		};

		$put( 'Name', self::campus_text( self::pick( $row, 'Name' ), 1000 ) );
		$put( 'Status', $resolved['label'] );
		$put( 'Start Date', $date( 'Start Date (YYYY-mm-dd)' ) );
		$put( 'End Date', $date( 'End Date (YYYY-mm-dd)' ) );
		$put( 'Institution Name', self::campus_text( self::pick( $row, 'Venue Name' ) ) );
		$put( 'City', self::campus_text( self::pick( $row, '_venue_city' ) ) );
		$put( 'Country', self::campus_text( self::pick( $row, '_venue_country_name' ) ) );

		// Anticipated Attendees is fldDFq9WcpkXxWkn3, a singleLineText holding
		// organiser free text such as "80-100". Never campus_int() here.
		$put( 'Anticipated Attendees', self::campus_text( self::pick( $row, 'Number of Anticipated Attendees' ) ) );
		$put( 'Actual Attendees', $number( 'Actual Attendees' ) );

		// Series Event is only sent to a manage_options caller. An absent key
		// must not be read as "not a series event".
		if ( array_key_exists( 'Series Event', $row ) ) {
			$put( 'Series Event', $number( 'Series Event' ) );
		}

		$put( 'Created', $date( 'Created' ) );
		$put( 'URL', self::campus_url( self::pick( $row, 'URL' ) ) );

		return array(
			'fields' => $fields,
			'id'     => $id,
			'status' => $resolved['why'],
			'slug'   => $slug,
			'notes'  => $notes,
		);
	}

	/**
	 * Read a key that may be absent from a _fields-trimmed response.
	 *
	 * @param array  $post Payload.
	 * @param string $key  Key name.
	 * @return mixed Empty string when absent.
	 */
	protected static function pick( array $post, $key ) {
		return array_key_exists( $key, $post ) ? $post[ $key ] : '';
	}
}
