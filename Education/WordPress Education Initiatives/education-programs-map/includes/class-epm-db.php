<?php
/**
 * Database layer for the institutions table.
 *
 * @package Education_Programs_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPM_DB {

	/**
	 * Allowed program types, including any custom programs added by an admin.
	 *
	 * @return array<string,string>
	 */
	public static function get_programs() {
		return EPM_Programs::get_all();
	}

	/**
	 * Full table name, including the site's prefix.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'epm_institutions';
	}

	/**
	 * Turn a comma-separated "programs" column value into a clean array of keys.
	 *
	 * @param string $programs_csv Raw comma-separated program keys.
	 * @return string[]
	 */
	public static function parse_programs( $programs_csv ) {
		if ( empty( $programs_csv ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $programs_csv ) ) ) );
	}

	/**
	 * Turn an array of program keys into the comma-separated string stored in the DB,
	 * keeping only keys that exist in the current program list.
	 *
	 * @param array $programs Program keys.
	 * @return string
	 */
	public static function format_programs( $programs ) {
		$valid = array_keys( self::get_programs() );
		$clean = array_values( array_intersect( array_map( 'sanitize_key', (array) $programs ), $valid ) );

		return implode( ',', $clean );
	}

	/**
	 * Create the table on first activation, or upgrade it when the schema changes.
	 */
	public static function maybe_upgrade() {
		self::maybe_rename_legacy_table();

		$installed_version = get_option( 'epm_db_version' );

		if ( EPM_DB_VERSION === $installed_version ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			city VARCHAR(191) NOT NULL,
			country VARCHAR(191) NOT NULL DEFAULT '',
			latitude DECIMAL(10,6) NOT NULL,
			longitude DECIMAL(10,6) NOT NULL,
			programs VARCHAR(255) NOT NULL DEFAULT '',
			event_count INT UNSIGNED NOT NULL DEFAULT 0,
			website VARCHAR(255) NOT NULL DEFAULT '',
			wpcc_url VARCHAR(255) NOT NULL DEFAULT '',
			student_club_url VARCHAR(255) NOT NULL DEFAULT '',
			airtable_id VARCHAR(64) NOT NULL DEFAULT '',
			hidden TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY airtable_id (airtable_id)
		) {$charset_collate};";

		dbDelta( $sql );

		self::maybe_migrate_single_program_column();

		update_option( 'epm_db_version', EPM_DB_VERSION );
	}

	/**
	 * Sites upgrading from the plugin's former identity as "WP Education Map"
	 * have their data in a "{prefix}weim_institutions" table; rename it in place
	 * (preserving all rows and indexes) the first time this runs under the new name.
	 */
	private static function maybe_rename_legacy_table() {
		global $wpdb;

		$new_table = self::table_name();
		$old_table = $wpdb->prefix . 'weim_institutions';

		if ( $new_table === $old_table ) {
			return;
		}

		$old_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
				DB_NAME,
				$old_table
			)
		);

		if ( ! $old_exists ) {
			return;
		}

		$new_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
				DB_NAME,
				$new_table
			)
		);

		if ( $new_exists ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $old_table/$new_table are derived from $wpdb->prefix, not user input.
		$wpdb->query( "RENAME TABLE {$old_table} TO {$new_table}" );
	}

	/**
	 * Sites upgrading from the single-program schema (< 1.5.0) have a legacy
	 * "program" column; copy its values into the new "programs" column once.
	 */
	private static function maybe_migrate_single_program_column() {
		global $wpdb;
		$table = self::table_name();

		$legacy_column_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
				DB_NAME,
				$table,
				'program'
			)
		);

		if ( ! $legacy_column_exists ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix, not user input.
		$wpdb->query( "UPDATE {$table} SET programs = program WHERE ( programs = '' OR programs IS NULL ) AND program IS NOT NULL AND program != ''" );
	}

	/**
	 * Insert a new institution.
	 *
	 * @param array $data Sanitized institution data.
	 * @return int|WP_Error Inserted row ID, or WP_Error on failure.
	 */
	public static function insert( $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'name'             => $data['name'],
				'city'             => $data['city'],
				'country'          => $data['country'],
				'latitude'         => $data['latitude'],
				'longitude'        => $data['longitude'],
				'programs'         => self::format_programs( $data['programs'] ),
				'event_count'      => $data['event_count'],
				'website'          => $data['website'],
				'wpcc_url'         => $data['wpcc_url'],
				'student_club_url' => $data['student_club_url'],
				'airtable_id'      => $data['airtable_id'] ?? '',
				'hidden'           => ! empty( $data['hidden'] ) ? 1 : 0,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%s', '%s', '%s', '%f', '%f', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'epm_insert_failed', __( 'Could not save the institution.', 'education-programs-map' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing institution.
	 *
	 * @param int   $id   Institution ID.
	 * @param array $data Sanitized institution data.
	 * @return bool|WP_Error
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		$fields  = array(
			'name'             => $data['name'],
			'city'             => $data['city'],
			'country'          => $data['country'],
			'latitude'         => $data['latitude'],
			'longitude'        => $data['longitude'],
			'programs'         => self::format_programs( $data['programs'] ),
			'event_count'      => $data['event_count'],
			'website'          => $data['website'],
			'wpcc_url'         => $data['wpcc_url'],
			'student_club_url' => $data['student_club_url'],
			'hidden'           => ! empty( $data['hidden'] ) ? 1 : 0,
			'updated_at'       => current_time( 'mysql' ),
		);
		$formats = array( '%s', '%s', '%s', '%f', '%f', '%s', '%d', '%s', '%s', '%s', '%d', '%s' );

		// Only touch airtable_id when the caller explicitly provides it, so manual
		// edits from the admin form (which never set it) don't clear a synced record's link.
		if ( array_key_exists( 'airtable_id', $data ) ) {
			$fields['airtable_id'] = $data['airtable_id'];
			$formats[]             = '%s';
		}

		$updated = $wpdb->update(
			self::table_name(),
			$fields,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'epm_update_failed', __( 'Could not update the institution.', 'education-programs-map' ) );
		}

		return true;
	}

	/**
	 * Delete an institution.
	 *
	 * @param int $id Institution ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Get a single institution by ID.
	 *
	 * @param int $id Institution ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix, not user input.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	/**
	 * Get a single institution by its originating Airtable record ID, if any.
	 *
	 * @param string $airtable_id Airtable record ID.
	 * @return object|null
	 */
	public static function get_by_airtable_id( $airtable_id ) {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix, not user input.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE airtable_id = %s", $airtable_id ) );
	}

	/**
	 * Get all institutions, optionally filtered to those that include a given program.
	 *
	 * @param string $program        Optional program key to filter by.
	 * @param bool   $exclude_hidden Whether to leave out institutions flagged as hidden
	 *                               (e.g. no longer "Confirmed" in a synced Airtable base).
	 * @return array
	 */
	public static function get_all( $program = '', $exclude_hidden = false ) {
		global $wpdb;
		$table = self::table_name();

		$where = array();
		$args  = array();

		if ( $program && array_key_exists( $program, self::get_programs() ) ) {
			$where[] = 'FIND_IN_SET( %s, programs )';
			$args[]  = $program;
		}

		if ( $exclude_hidden ) {
			$where[] = 'hidden = 0';
		}

		$sql = "SELECT * FROM {$table}";
		if ( $where ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		$sql .= ' ORDER BY name ASC';

		if ( $args ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is assembled above from fixed placeholder strings ('FIND_IN_SET( %s, programs )', 'hidden = 0'); $args supplies the only dynamic value.
			return $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql contains no user-controlled data when $args is empty (only the fixed 'hidden = 0' clause, if any).
		return $wpdb->get_results( $sql );
	}

	/**
	 * Hide every Airtable-sourced institution tagged with a given program except
	 * the given record IDs, and make sure those IDs are marked visible again
	 * (e.g. a record that moved back to "Confirmed" after being previously excluded).
	 *
	 * Scoped to a single program because each program can sync from a completely
	 * different Airtable base; without this scoping, syncing one program's base
	 * would wrongly hide institutions that only belong to a different program's sync.
	 *
	 * @param string   $program           Program key this sync run was for.
	 * @param string[] $keep_airtable_ids Airtable record IDs that should stay visible.
	 * @return int Number of institutions newly hidden.
	 */
	public static function hide_airtable_records_except( $program, array $keep_airtable_ids ) {
		global $wpdb;
		$table = self::table_name();

		if ( empty( $keep_airtable_ids ) ) {
			return (int) $wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix, not user input.
					"UPDATE {$table} SET hidden = 1 WHERE FIND_IN_SET( %s, programs ) AND airtable_id != '' AND hidden = 0",
					$program
				)
			);
		}

		$placeholders = implode( ', ', array_fill( 0, count( $keep_airtable_ids ), '%s' ) );
		return (int) $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $table is derived from $wpdb->prefix, not user input; $placeholders is a dynamically-sized list of %s tokens (one per $keep_airtable_ids entry), both consumed by this same prepare() call.
				"UPDATE {$table} SET hidden = 1 WHERE FIND_IN_SET( %s, programs ) AND airtable_id != '' AND hidden = 0 AND airtable_id NOT IN ( {$placeholders} )",
				array_merge( array( $program ), $keep_airtable_ids )
			)
		);
	}

	/**
	 * Count all institutions.
	 *
	 * @return int
	 */
	public static function count_all() {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix, not user input.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Count institutions that include a given program.
	 *
	 * @param string $program Program key.
	 * @return int
	 */
	public static function count_by_program( $program ) {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix, not user input.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE FIND_IN_SET( %s, programs )", $program ) );
	}
}
