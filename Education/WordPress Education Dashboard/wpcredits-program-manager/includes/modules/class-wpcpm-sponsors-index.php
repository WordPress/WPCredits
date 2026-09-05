<?php
/**
 * The sponsors index: what the site knows about each sponsor, read nightly from Airtable.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One option holding every sponsor's public facts, one holding the program team, and one
 * per sponsor holding which Media Library attachment is its logo.
 *
 * `WPCPM_Institutions_Index`'s shape: a versioned option a mismatch discards, rows keyed by
 * record ID, names stored as Airtable holds them (renderers trim), and a single whole-index
 * write at the end of the sync's records phase so a run that dies mid-way leaves the previous
 * index untouched. The team option is what turns a `Person of contact` link into a name, an
 * address and a booking link, resolved at read time by `manager_of()` so a team member's
 * renamed row shows on every sponsor the morning after the sync.
 */
final class WPCPM_Sponsors_Index {

	const OPT_NAME        = 'wpcpm_sponsors_index';
	const OPT_TEAM        = 'wpcpm_team_members';
	const OPT_LOGO_PREFIX = 'wpcpm_sponsor_logo_';
	const VERSION         = 1;

	/** The Airtable `Status` that means a sponsor is in the program. */
	const STATUS_APPROVED = 'Approved';

	/**
	 * The row shape, every key. `shape()` writes each of them on every row, so an empty
	 * value means the base holds nothing, never that the index has no answer.
	 *
	 * @return array
	 */
	public static function empty_row() {
		return array(
			'record_id'         => '',
			'name'              => '',
			'website'           => '',
			'status'            => '',
			'option'            => '',
			'support'           => array(),
			'product_type'      => '',
			'offer'             => '',
			'instructions'      => '',
			'more_info'         => '',
			'coupon_link'       => '',
			'anything'          => '',
			'contact_person'    => '',
			'contact_email'     => '',
			'manager'           => '',
			'mentors'           => array(),
			'logo'              => array(),
			'consent'           => false,
			'created'           => '',
			'agreement'         => array(
				'status'       => '',
				'accepted_on'  => '',
				'has_document' => false,
			),
			'interests'         => '',
			'dashboard_account' => false,
		);
	}

	/**
	 * The stored index, or an empty one.
	 *
	 * @return array `v`, `read` (unix time the run started), `rows` keyed by record ID.
	 */
	public static function read() {
		$stored = get_option( self::OPT_NAME );

		if ( ! is_array( $stored ) || ! isset( $stored['v'] ) || self::VERSION !== (int) $stored['v'] || ! isset( $stored['rows'] ) || ! is_array( $stored['rows'] ) ) {
			return array(
				'v'    => self::VERSION,
				'read' => 0,
				'rows' => array(),
			);
		}

		return $stored;
	}

	/**
	 * Every row, keyed by record ID, in Airtable's order.
	 *
	 * @return array[]
	 */
	public static function rows() {
		$index = self::read();

		return $index['rows'];
	}

	/**
	 * One row, or null for a malformed or unknown ID.
	 *
	 * @param string $record Airtable record ID.
	 * @return array|null
	 */
	public static function row( $record ) {
		$record = trim( (string) $record );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return null;
		}

		$rows = self::rows();

		return isset( $rows[ $record ] ) ? $rows[ $record ] : null;
	}

	/**
	 * Whether the index holds a record.
	 *
	 * @param string $record Airtable record ID.
	 * @return bool
	 */
	public static function has( $record ) {
		return null !== self::row( $record );
	}

	/**
	 * Replace the whole index.
	 *
	 * @param array $rows Rows keyed by record ID, as the sync built them.
	 * @param int   $read When the run that read them started.
	 */
	public static function write( array $rows, $read ) {
		$shaped = array();

		foreach ( $rows as $record => $row ) {
			$record = trim( (string) $record );

			if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) || ! is_array( $row ) ) {
				continue;
			}

			$row['record_id']  = $record;
			$shaped[ $record ] = self::shape( $row );
		}

		update_option(
			self::OPT_NAME,
			array(
				'v'    => self::VERSION,
				'read' => (int) $read,
				'rows' => $shaped,
			),
			false
		);
	}

	/**
	 * Change a few keys of one row in place, for a fact the site just wrote to the base and
	 * should not wait a night to read back (the `Dashboard account` checkbox).
	 *
	 * @param string $record Sponsor record ID.
	 * @param array  $fields Keys of `empty_row()` and their new values; anything else is ignored.
	 * @return bool Whether the row existed.
	 */
	public static function patch( $record, array $fields ) {
		$index  = self::read();
		$record = trim( (string) $record );

		if ( ! isset( $index['rows'][ $record ] ) ) {
			return false;
		}

		$row = $index['rows'][ $record ];

		foreach ( array_intersect_key( $fields, self::empty_row() ) as $key => $value ) {
			$row[ $key ] = $value;
		}

		$index['rows'][ $record ] = self::shape( $row );

		update_option( self::OPT_NAME, $index, false );

		return true;
	}

	/**
	 * The sponsors in the program.
	 *
	 * @return array[] Rows keyed by record ID.
	 */
	public static function approved() {
		$out = array();

		foreach ( self::rows() as $record => $row ) {
			if ( self::STATUS_APPROVED === $row['status'] ) {
				$out[ $record ] = $row;
			}
		}

		return $out;
	}

	/**
	 * How many sponsors hold each status, in Airtable's order of first appearance.
	 *
	 * @return array<string, int>
	 */
	public static function status_counts() {
		$counts = array();

		foreach ( self::rows() as $row ) {
			$status = '' === $row['status'] ? '' : $row['status'];

			$counts[ $status ] = isset( $counts[ $status ] ) ? $counts[ $status ] + 1 : 1;
		}

		return $counts;
	}

	/**
	 * The program team, keyed by Team Members record ID: `name`, `email`, `calendly`.
	 *
	 * @return array[]
	 */
	public static function team() {
		$stored = get_option( self::OPT_TEAM );

		if ( ! is_array( $stored ) || ! isset( $stored['rows'] ) || ! is_array( $stored['rows'] ) ) {
			return array();
		}

		return $stored['rows'];
	}

	/**
	 * Replace the team.
	 *
	 * @param array $rows Rows keyed by record ID: `name`, `email`, `calendly`.
	 * @param int   $read When the run that read them started.
	 */
	public static function write_team( array $rows, $read ) {
		$shaped = array();

		foreach ( $rows as $record => $row ) {
			$record = trim( (string) $record );

			if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) || ! is_array( $row ) ) {
				continue;
			}

			$shaped[ $record ] = array(
				'name'     => isset( $row['name'] ) ? (string) $row['name'] : '',
				'email'    => isset( $row['email'] ) ? strtolower( trim( (string) $row['email'] ) ) : '',
				'calendly' => isset( $row['calendly'] ) ? trim( (string) $row['calendly'] ) : '',
			);
		}

		update_option(
			self::OPT_TEAM,
			array(
				'v'    => self::VERSION,
				'read' => (int) $read,
				'rows' => $shaped,
			),
			false
		);
	}

	/**
	 * The assigned program manager of a sponsor, resolved from the team at read time.
	 *
	 * @param string $record Sponsor record ID.
	 * @return array|null `name`, `email`, `calendly` (trimmed), or null when none is assigned or known.
	 */
	public static function manager_of( $record ) {
		$row = self::row( $record );

		if ( ! is_array( $row ) || '' === $row['manager'] ) {
			return null;
		}

		$team = self::team();

		if ( ! isset( $team[ $row['manager'] ] ) ) {
			return null;
		}

		return array(
			'name'     => trim( (string) $team[ $row['manager'] ]['name'] ),
			'email'    => trim( (string) $team[ $row['manager'] ]['email'] ),
			'calendly' => trim( (string) $team[ $row['manager'] ]['calendly'] ),
		);
	}

	/**
	 * The option that records a sponsor's logo attachments.
	 *
	 * @param string $record Sponsor record ID.
	 * @return string
	 */
	public static function logo_option( $record ) {
		return self::OPT_LOGO_PREFIX . trim( (string) $record );
	}

	/**
	 * What the site holds for a sponsor's logo.
	 *
	 * `source` says who put it there: `airtable` (the sync copied Airtable's attachment,
	 * `airtable_id` names which) or `site` (the sponsor uploaded it, Phase S4), and the sync
	 * never overwrites a `site` logo with Airtable's (spec section 5.2, phase 3).
	 *
	 * @param string $record Sponsor record ID.
	 * @return array `colour` (attachment ID or 0), `white` (attachment ID or 0), `source` ('' when none), `airtable_id`.
	 */
	public static function logo_record( $record ) {
		$stored = get_option( self::logo_option( $record ) );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'colour'      => isset( $stored['colour'] ) ? (int) $stored['colour'] : 0,
			'white'       => isset( $stored['white'] ) ? (int) $stored['white'] : 0,
			'source'      => isset( $stored['source'] ) ? (string) $stored['source'] : '',
			'airtable_id' => isset( $stored['airtable_id'] ) ? (string) $stored['airtable_id'] : '',
		);
	}

	/**
	 * Record a sponsor's logo attachments.
	 *
	 * @param string $record Sponsor record ID.
	 * @param array  $logo   As `logo_record()` returns it.
	 */
	public static function write_logo_record( $record, array $logo ) {
		update_option( self::logo_option( $record ), array_merge( self::logo_record( $record ), $logo ), false );
	}

	/**
	 * The logo to draw for a sponsor: the site's attachment, never Airtable's URL.
	 *
	 * An Airtable attachment URL expires within hours, so the index's `logo.url` is a fact
	 * about the base and not a thing to put in an `<img>`; the attachment the sync copied is.
	 *
	 * @param string $record Sponsor record ID.
	 * @return array|null `id` and `url`, or null when the site holds no logo for this sponsor.
	 */
	public static function display_logo( $record ) {
		$logo = self::logo_record( $record );
		$id   = $logo['colour'] > 0 ? $logo['colour'] : $logo['white'];

		if ( $id < 1 ) {
			return null;
		}

		$url = wp_get_attachment_image_url( $id, 'medium' );

		if ( ! is_string( $url ) || '' === $url ) {
			return null;
		}

		return array(
			'id'  => $id,
			'url' => $url,
		);
	}

	/**
	 * Delete the index, the team and every logo record. Uninstall only; the attachments stay
	 * in the Media Library, as section 11 of the spec promises.
	 *
	 * By prefix, through `$wpdb`, rather than only the rows the index currently holds: a
	 * sponsor that dropped out of the index (deleted in Airtable, or renamed past
	 * recognition) between a sync and an uninstall would otherwise leave its logo option
	 * behind for ever, an orphan `delete_all()` promised to clear.
	 */
	public static function delete_all() {
		global $wpdb;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::OPT_LOGO_PREFIX ) . '%'
			)
		);

		delete_option( self::OPT_NAME );
		delete_option( self::OPT_TEAM );
	}

	/**
	 * Every key present, every scalar typed, the name untrimmed.
	 *
	 * @param array $row A row as the sync built it.
	 * @return array
	 */
	private static function shape( array $row ) {
		$out = self::empty_row();

		foreach ( $out as $key => $default ) {
			if ( ! array_key_exists( $key, $row ) ) {
				continue;
			}

			if ( 'agreement' === $key ) {
				$given = is_array( $row['agreement'] ) ? $row['agreement'] : array();

				$out['agreement'] = array(
					'status'       => isset( $given['status'] ) ? trim( (string) $given['status'] ) : '',
					'accepted_on'  => isset( $given['accepted_on'] ) ? trim( (string) $given['accepted_on'] ) : '',
					'has_document' => ! empty( $given['has_document'] ),
				);
				continue;
			}

			if ( is_array( $default ) ) {
				$out[ $key ] = is_array( $row[ $key ] ) ? $row[ $key ] : array();
			} elseif ( is_bool( $default ) ) {
				$out[ $key ] = ! empty( $row[ $key ] );
			} else {
				// As stored, trailing space and all, for the name: the index is the one place
				// that must agree with the base byte for byte. Renderers trim.
				$out[ $key ] = (string) $row[ $key ];
			}
		}

		return $out;
	}
}
