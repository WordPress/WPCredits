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

	const OPT_NAME = 'wpcpm_institutions_index';

	const VERSION = 1;

	/**
	 * The option the lock a patch holds while it reads and writes the index is kept under, through
	 * `WPCPM_Lock` (`patch()`). The uninstaller removes it.
	 */
	const OPT_LOCK = 'wpcpm_institutions_index_lock';

	/**
	 * How long a patch's lock holds at most, in seconds: many times one read and one write of an
	 * option, so a lock a request that died left runs out by itself.
	 */
	const LOCK_TTL = 10;

	/** How many times a patch asks for its lock before it gives up. */
	const LOCK_TRIES = 10;

	/** How long a patch waits between two asks for its lock, in microseconds: a tenth of a second. */
	const LOCK_PAUSE = 100000;

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
		$stored = get_option( self::OPT_NAME );

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
	 * Drop this request's copy of the index, so the next read is the table's.
	 *
	 * The option is read through the options cache, which holds what this request read first for the
	 * rest of it. A read under a lock, after another request may have written the index, has to be the
	 * table's, or it is the copy from before that write.
	 */
	public static function forget_cached() {
		wp_cache_delete( self::OPT_NAME, 'options' );
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
			self::OPT_NAME,
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
	 * can attach an account to it and cannot wait for the institutions sync. The read time is
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

		update_option( self::OPT_NAME, $index, false );
	}

	/**
	 * Change a few keys of one row in place, keeping the read time and every other key.
	 *
	 * For a school's details corrected on the site (`WPCPM_Institution_Details::write()`), once
	 * Airtable has taken the same change: the row follows at once instead of waiting for the next
	 * sync, which would write it again from the base and find nothing to disagree about. The read time
	 * is the table's, and one row moving does not make the rest of the table any newer.
	 *
	 * **Only a row the index holds.** A patch never makes a row (that is `insert()`'s, for an
	 * approval), and an index of another version reads as empty (`read()`), so a patch on one finds no
	 * row and writes nothing, rather than graft a row onto a shape this code does not know. A key
	 * outside the row's contract is dropped, and `record_id` cannot be changed: the row stays the one it
	 * is. The new row is shaped as every write shapes it, so a value is typed and nothing else rides in.
	 *
	 * **One patch at a time.** A patch reads the whole index and writes it back, so two at once, of two
	 * schools, would each write the copy it read and drop the other's row. It reads and writes under a
	 * short lock of its own (`OPT_LOCK`, through `WPCPM_Lock`), reading the table and not this request's
	 * copy once it holds it. A lock it cannot take after a short wait (`LOCK_TRIES` asks, `LOCK_PAUSE`
	 * apart) is a patch not made, said as a row the index does not hold is: the change is in Airtable
	 * already, and the next sync brings it here.
	 *
	 * **A sync reading meanwhile.** The institutions sync writes the whole index from what it read of the
	 * base, and a tick that read the table before this patch writes the old values after it. So a patch
	 * made while a tick holds the sync's lock asks for one more run a few minutes later
	 * (`WPCPM_Institutions_Sync::read_again_soon()`), which reads the change from the base.
	 *
	 * @param string $record_id Airtable record ID.
	 * @param array  $fields    Keys of `empty_row()` and their new values.
	 * @return bool Whether the index held the row, and so changed it; false too when the lock could not
	 *              be taken.
	 */
	public static function patch( $record_id, array $fields ) {
		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return false;
		}

		$record_id = trim( (string) $record_id );
		$token     = self::take_lock();

		if ( '' === $token ) {
			return false;
		}

		$patched = false;

		try {
			self::forget_cached();

			$index = self::read();

			if ( isset( $index['rows'][ $record_id ] ) && is_array( $index['rows'][ $record_id ] ) ) {
				$row = $index['rows'][ $record_id ];

				foreach ( array_intersect_key( $fields, self::empty_row() ) as $key => $value ) {
					if ( 'record_id' !== $key ) {
						$row[ $key ] = $value;
					}
				}

				$row['record_id']            = $record_id;
				$index['rows'][ $record_id ] = self::shape( $row );

				update_option( self::OPT_NAME, $index, false );

				$patched = true;
			}
		} finally {
			WPCPM_Lock::release( self::OPT_LOCK, $token );
		}

		if ( $patched ) {
			WPCPM_Institutions_Sync::read_again_soon();
		}

		return $patched;
	}

	/**
	 * Take the lock a patch reads and writes under, asking again a few times while another holds it.
	 *
	 * Another patch holds it for one read and one write, so a short wait is enough; a lock that stays held
	 * past it is a request that died holding it, and is taken over once its `LOCK_TTL` has passed.
	 *
	 * @return string The token `WPCPM_Lock::release()` takes, or '' when the lock could not be taken.
	 */
	private static function take_lock() {
		$tries = 0;

		while ( true ) {
			$token = WPCPM_Lock::take( self::OPT_LOCK, self::LOCK_TTL );
			++$tries;

			if ( '' !== $token || $tries >= self::LOCK_TRIES ) {
				return $token;
			}

			usleep( self::LOCK_PAUSE );
		}
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
