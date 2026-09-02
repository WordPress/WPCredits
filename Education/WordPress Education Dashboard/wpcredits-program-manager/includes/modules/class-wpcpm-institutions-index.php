<?php
/**
 * Institutions module - the pipeline index.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The site's copy of the Institutions table, one row per Airtable record.
 *
 * Written whole by the institutions sync's `records` phase and read by every screen that
 * lists institutions, so no page has to reach Airtable to draw the pipeline. It is also the
 * fence's roll call: `WPCPM_Institution_Members::attach()` refuses a record this index does
 * not hold, which is why the approval handler inserts its row here before it stamps an
 * account. A record the site has never read cannot acquire members.
 *
 * What a row holds is deliberately short: the columns the pipeline table, the consent
 * report and the agreement column draw, and nothing else. No department, no prose, no
 * Drive link (that lives on the per-institution agreement option), because this option is
 * read on ordinary admin requests and printed on a screen every program manager sees.
 *
 * Every read goes through `read()`, which discards a stored copy at the wrong version.
 * A shape change bumps `VERSION`, the next sync rewrites the option, and until then the
 * screens draw an empty pipeline rather than misread an old one.
 */
class WPCPM_Institutions_Index {

	const OPTION = 'wpcpm_institutions_index';

	const VERSION = 1;

	/**
	 * The keys every row carries, with the value each takes when Airtable has none.
	 *
	 * `name` is stored as Airtable holds it, trailing space and all: ten records end in one,
	 * and a comparison that trimmed on one side and not the other is the shape of bug this
	 * module keeps meeting. Renderers trim.
	 *
	 * @return array
	 */
	public static function empty_row() {
		return array(
			'record_id'      => '',
			'name'           => '',
			'stage'          => '',
			'country'        => '',
			'country_name'   => '',
			'city'           => '',
			'website'        => '',
			'contact_person' => '',
			'contact_email'  => '',
			'created'        => '',
			'consent'        => false,
			'confirmed_on'   => '',
			'agreement'      => array(
				'status'           => '',
				'kind'             => '',
				'accepted_on'      => '',
				'signed_on'        => '',
				'accepted_by'      => '',
				'submitted_on'     => '',
				'template_version' => '',
				'has_document'     => false,
			),
		);
	}

	/**
	 * The stored envelope, or the empty shape when there is none worth reading.
	 *
	 * A version mismatch reads as absent on purpose: a row shape this code does not know
	 * is worse than no row, and the next sync writes a fresh copy anyway.
	 *
	 * @return array `array( 'v' => int, 'read' => int, 'rows' => array )`.
	 */
	public static function read() {
		$empty  = array(
			'v'    => self::VERSION,
			'read' => 0,
			'rows' => array(),
		);
		$stored = get_option( self::OPTION );

		if ( ! is_array( $stored ) || ! isset( $stored['v'] ) || self::VERSION !== (int) $stored['v'] ) {
			return $empty;
		}

		return array(
			'v'    => self::VERSION,
			'read' => isset( $stored['read'] ) ? (int) $stored['read'] : 0,
			'rows' => ( isset( $stored['rows'] ) && is_array( $stored['rows'] ) ) ? $stored['rows'] : array(),
		);
	}

	/**
	 * Every row, keyed by Airtable record ID.
	 *
	 * @return array
	 */
	public static function rows() {
		$index = self::read();

		return $index['rows'];
	}

	/**
	 * One institution's row, or null when the index does not hold it.
	 *
	 * A malformed ID is null without a lookup: the rows are keyed by record ID, and an
	 * empty key would otherwise be one more place where "nothing" could match something.
	 *
	 * @param string $record_id Airtable record ID.
	 * @return array|null
	 */
	public static function row( $record_id ) {
		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return null;
		}

		$record_id = trim( (string) $record_id );
		$rows      = self::rows();

		return isset( $rows[ $record_id ] ) && is_array( $rows[ $record_id ] ) ? $rows[ $record_id ] : null;
	}

	/**
	 * Whether the index holds this institution.
	 *
	 * @param string $record_id Airtable record ID.
	 * @return bool
	 */
	public static function has( $record_id ) {
		return null !== self::row( $record_id );
	}

	/**
	 * Replace the whole index.
	 *
	 * The sync's only write, made once after the last page has been read, so a run that
	 * fails halfway leaves the previous index in place rather than half of a new one.
	 * Rows without a well-formed record ID are dropped: they could never be looked up.
	 *
	 * @param array $rows Rows, keyed by or carrying their record ID.
	 * @param int   $read Unix time the table was read.
	 */
	public static function write( array $rows, $read ) {
		$clean = array();

		foreach ( $rows as $key => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			if ( empty( $row['record_id'] ) && is_string( $key ) ) {
				$row['record_id'] = $key;
			}

			$row = self::shape( $row );

			if ( '' === $row['record_id'] ) {
				continue;
			}

			$clean[ $row['record_id'] ] = $row;
		}

		update_option(
			self::OPTION,
			array(
				'v'    => self::VERSION,
				'read' => (int) $read,
				'rows' => $clean,
			),
			false
		);
	}

	/**
	 * Add or replace one row, keeping the read time.
	 *
	 * For the approval handler, which needs the new institution in the index before it
	 * can attach an account to it and cannot wait for the nightly sync. The read time is
	 * the table's, not this row's: the rest of the index is still as old as it was.
	 *
	 * @param array $row The row.
	 */
	public static function insert( array $row ) {
		$row = self::shape( $row );

		if ( '' === $row['record_id'] ) {
			return;
		}

		$index                              = self::read();
		$index['rows'][ $row['record_id'] ] = $row;

		update_option( self::OPTION, $index, false );
	}

	/**
	 * How many institutions sit at each stage, with `''` for the ones that have none.
	 *
	 * In `by_stage()` order, so a screen can print the two side by side.
	 *
	 * @return array<string, int>
	 */
	public static function stage_counts() {
		$counts = array();

		foreach ( self::by_stage() as $stage => $rows ) {
			$counts[ $stage ] = count( $rows );
		}

		return $counts;
	}

	/**
	 * The rows grouped by stage, in pipeline order.
	 *
	 * The order is the agreement's `STAGE_ORDER` (the path a partnership takes), then the
	 * three terminal stages, then any stage the base has since added, then the records with
	 * no stage at all. Only stages with at least one row are present, so a screen never
	 * prints an empty heading. Within a stage the sync's order is kept.
	 *
	 * @return array<string, array>
	 */
	public static function by_stage() {
		$groups = array();

		foreach ( self::rows() as $row ) {
			$stage = isset( $row['stage'] ) ? (string) $row['stage'] : '';

			if ( ! isset( $groups[ $stage ] ) ) {
				$groups[ $stage ] = array();
			}

			$groups[ $stage ][ $row['record_id'] ] = $row;
		}

		$ordered = array();

		foreach ( array_merge( WPCPM_Institution_Agreement::STAGE_ORDER, WPCPM_Institution_Agreement::TERMINAL_STAGES ) as $stage ) {
			if ( isset( $groups[ $stage ] ) ) {
				$ordered[ $stage ] = $groups[ $stage ];
				unset( $groups[ $stage ] );
			}
		}

		// A stage neither list names is one the base grew after this shipped. Listed, not
		// hidden, after the known ones and before the empty group, in a stable order.
		$unknown = array_keys( $groups );
		sort( $unknown, SORT_STRING );

		foreach ( $unknown as $stage ) {
			if ( '' !== $stage ) {
				$ordered[ $stage ] = $groups[ $stage ];
			}
		}

		if ( isset( $groups[''] ) ) {
			$ordered[''] = $groups[''];
		}

		return $ordered;
	}

	/**
	 * Bring a row to the contract's shape: every key present, nothing else, scalars typed.
	 *
	 * The agreement block is rebuilt key by key for the same reason the row is: a caller
	 * that hands over a raw Airtable cell or a key from another table must not be able to
	 * smuggle it into an option every manager screen reads.
	 *
	 * @param array $row A row in any state of completeness.
	 * @return array
	 */
	private static function shape( array $row ) {
		$empty = self::empty_row();
		$clean = array();

		foreach ( $empty as $key => $default ) {
			if ( 'agreement' === $key ) {
				$given = ( isset( $row['agreement'] ) && is_array( $row['agreement'] ) ) ? $row['agreement'] : array();
				$block = array();

				foreach ( $default as $sub => $sub_default ) {
					$block[ $sub ] = is_bool( $sub_default )
						? ! empty( $given[ $sub ] )
						: ( isset( $given[ $sub ] ) && is_scalar( $given[ $sub ] ) ? (string) $given[ $sub ] : '' );
				}

				$clean['agreement'] = $block;
				continue;
			}

			if ( is_bool( $default ) ) {
				$clean[ $key ] = ! empty( $row[ $key ] );
				continue;
			}

			$clean[ $key ] = ( isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ) ? (string) $row[ $key ] : '';
		}

		$clean['record_id'] = WPCPM_Mentors_Sync::is_record_id( $clean['record_id'] ) ? trim( $clean['record_id'] ) : '';

		return $clean;
	}
}
