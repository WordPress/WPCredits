<?php
/**
 * The Countries table, cached: which program manager an institution's country routes to.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Record ID to country name, and to the program manager the country routes to.
 *
 * Every institution record links to one row of the Countries table, and that row
 * carries `Person of contact (Team)`: the program manager who follows the country up.
 * The applicant acknowledgement used to be sent by an Airtable automation embedding
 * that manager's Calendly link. Under the new form no record exists at submission, so
 * the site reproduces the link from this map, which is the only reason the Calendly
 * column is read at all. The rest of the plugin needs the map for a country's name on
 * the pipeline, and for the "for information" contact beside a queue row.
 *
 * **What is cached and why.** One option, written by `refresh()` and read everywhere
 * else, in the shape `WPCPM_Mentors_Sync::lookups()` uses for its own tables: versioned,
 * discarded on a version mismatch, never autoloaded. A read failure returns the error
 * and leaves the last good copy in place, because a routing map that is a few days old
 * is worth more than an empty one, and `WPCPM_Airtable` may be refusing every request
 * while a 429 backoff runs. The rows hold a name, a Team record ID, an address and a URL:
 * no prose, nothing a disclosure could hide in.
 *
 * **A country with no contact is not an error.** On 2 September 2026, 138 of the 196
 * countries carried a contact and 58 did not, three of which an institution names
 * (Nigeria, Thailand, Cambodia). `gaps()` lists them for the manager screen; `routing()`
 * answers null for them; `name_of()` still resolves them, because an institution in
 * Nigeria is still in Nigeria.
 *
 * **Why `manager` is a record ID today.** The Countries table has no lookup of the
 * contact's name: `Person of contact (Team)` is a link to the Team table, and the REST
 * API answers a link with record IDs. The email and Calendly lookups exist, so those are
 * read by name; the name is not, and inventing one from the address is worse than
 * showing the address. `contact_of()` is what a screen should print: the name when the
 * base ever grows a `Name (from Person of contact (Team))` lookup pointed at by
 * `FIELD_CONTACT`, the address until then, so no record ID reaches a page.
 */
class WPCPM_Countries {

	/** The option holding the map. Written with `update_option( ..., false )`, never autoloaded. */
	const OPT_NAME = 'wpcpm_countries';

	/** Bump when the row shape changes; a stored map with another version is discarded on read. */
	const VERSION = 1;

	/**
	 * The link to the Team table. Read for whether a contact exists and for the ID.
	 *
	 * A link field, so the REST API returns record IDs, not names. Should a lookup of the
	 * contact's name be added to the Countries table, point this constant at it and the
	 * `manager` column becomes the name with no other change.
	 */
	const FIELD_CONTACT = 'Person of contact (Team)';

	/** The contact's address, looked up through the link. Spelled with two closing brackets. */
	const FIELD_EMAIL = 'Email (from Person of contact (Team))';

	/** The contact's booking link, looked up through the link. */
	const FIELD_CALENDLY = 'Calendly link (from Person of contact (Team))';

	/** The empty envelope, returned when nothing usable is stored. */
	const EMPTY_SHAPE = array(
		'v'    => self::VERSION,
		'read' => 0,
		'rows' => array(),
	);

	/**
	 * The stored map, or the empty shape.
	 *
	 * A map written by another version of this class is discarded rather than read
	 * around: the version exists so a shape change cannot put stale keys on a screen.
	 *
	 * @return array `array( 'v' => int, 'read' => int, 'rows' => array( record ID => row ) )`.
	 */
	public static function read() {
		$stored = get_option( self::OPT_NAME );

		if ( ! is_array( $stored ) || (int) ( isset( $stored['v'] ) ? $stored['v'] : 0 ) !== self::VERSION ) {
			return self::EMPTY_SHAPE;
		}

		return array(
			'v'    => self::VERSION,
			'read' => isset( $stored['read'] ) ? (int) $stored['read'] : 0,
			'rows' => ( isset( $stored['rows'] ) && is_array( $stored['rows'] ) ) ? $stored['rows'] : array(),
		);
	}

	/**
	 * Every country, keyed by record ID.
	 *
	 * @return array<string, array> Record ID => `array( 'name', 'manager', 'email', 'calendly' )`.
	 */
	public static function all() {
		return self::read()['rows'];
	}

	/**
	 * One country's row, or null when the ID is unknown.
	 *
	 * @param string $record_id Countries record ID.
	 * @return array|null
	 */
	private static function row( $record_id ) {
		$record_id = trim( (string) $record_id );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return null;
		}

		$rows = self::all();

		return isset( $rows[ $record_id ] ) ? $rows[ $record_id ] : null;
	}

	/**
	 * A country's name, or an empty string when the ID is unknown.
	 *
	 * Resolves whether or not the country has a contact: a routing gap is about who
	 * follows the institution up, not about where it is.
	 *
	 * @param string $record_id Countries record ID.
	 * @return string
	 */
	public static function name_of( $record_id ) {
		$row = self::row( $record_id );

		return null === $row ? '' : (string) $row['name'];
	}

	/**
	 * Who a country routes to, or null when it routes to nobody.
	 *
	 * Null for an unknown ID and null for a known country with no contact, so a caller
	 * that wants to route has one check to make. A caller that wants to know whether
	 * the ID is a country at all asks `name_of()`, which answers for both.
	 *
	 * @param string $record_id Countries record ID.
	 * @return array|null `array( 'name', 'manager', 'email', 'calendly' )`.
	 */
	public static function routing( $record_id ) {
		$row = self::row( $record_id );

		return ( null === $row || ! self::has_contact( $row ) ) ? null : $row;
	}

	/**
	 * What to print for a country's contact.
	 *
	 * The name when the base supplies one, otherwise the address, otherwise nothing.
	 * Screens go through this rather than reading `manager` directly, because today
	 * that column holds a Team record ID (see the class docblock) and a record ID on a
	 * queue row tells a program manager nothing.
	 *
	 * @param string $record_id Countries record ID.
	 * @return string
	 */
	public static function contact_of( $record_id ) {
		$row = self::row( $record_id );

		if ( null === $row ) {
			return '';
		}

		if ( '' !== $row['manager'] && ! WPCPM_Mentors_Sync::is_record_id( $row['manager'] ) ) {
			return $row['manager'];
		}

		return $row['email'];
	}

	/**
	 * Every country with nobody to route to, by name.
	 *
	 * A list for the manager screen, never a reason to refuse anything: an institution
	 * in one of these countries still applies, still confirms and still gets accounts.
	 * It just has no program manager named against it yet.
	 *
	 * @return array<string, array> Record ID => row, sorted by country name.
	 */
	public static function gaps() {
		$gaps = array();

		foreach ( self::all() as $record_id => $row ) {
			if ( ! self::has_contact( $row ) ) {
				$gaps[ $record_id ] = $row;
			}
		}

		uasort(
			$gaps,
			static function ( $a, $b ) {
				return strnatcasecmp( $a['name'], $b['name'] );
			}
		);

		return $gaps;
	}

	/**
	 * Record ID to country name, sorted, for a select.
	 *
	 * The application form's Country field offers exactly this list and validates the
	 * posted value against its keys, so a country without a contact is offered too.
	 *
	 * @return array<string, string> Record ID => name.
	 */
	public static function options() {
		$options = array();

		foreach ( self::all() as $record_id => $row ) {
			$options[ $record_id ] = (string) $row['name'];
		}

		natcasesort( $options );

		return $options;
	}

	/**
	 * Re-read the Countries table and replace the stored map.
	 *
	 * Returns the error and writes nothing when the read fails, when it returns no
	 * records, or when it returns records with no names. The last case is what Airtable
	 * answers when a field name in the request is misspelled: records with no fields at
	 * all, not an error. Treating that as "the table is empty" would replace a good map
	 * with an empty one and every country on the pipeline would go blank, without a
	 * word about why.
	 *
	 * @param WPCPM_Airtable|null $airtable Client to read through; one is built from the
	 *                                      saved settings when none is given.
	 * @return true|WP_Error
	 */
	public static function refresh( $airtable = null ) {
		$settings = WPCPM_Settings::get();
		$table    = isset( $settings['countries_table'] ) ? trim( (string) $settings['countries_table'] ) : '';

		if ( '' === $table ) {
			return new WP_Error( 'wpcpm_countries_no_table', __( 'No Countries table is configured in the plugin settings.', 'wpcredits-program-manager' ) );
		}

		if ( ! $airtable instanceof WPCPM_Airtable ) {
			$airtable = new WPCPM_Airtable( $settings );
		}

		$name_field = self::name_field( $settings );

		// An explicit list, never the whole row: the table also links every student and
		// mentor in the country, and those columns are names nobody here needs.
		$records = $airtable->fetch_all(
			$table,
			array(
				'fields' => array( $name_field, self::FIELD_CONTACT, self::FIELD_EMAIL, self::FIELD_CALENDLY ),
			)
		);

		if ( is_wp_error( $records ) ) {
			return $records;
		}

		if ( ! is_array( $records ) || empty( $records ) ) {
			return new WP_Error(
				'wpcpm_countries_empty',
				sprintf(
					/* translators: %s: Airtable table ID. */
					__( 'The Countries table %s returned no records, so the stored map was kept.', 'wpcredits-program-manager' ),
					$table
				)
			);
		}

		$rows = array();

		foreach ( $records as $record ) {
			if ( ! is_array( $record ) || empty( $record['id'] ) || ! WPCPM_Mentors_Sync::is_record_id( $record['id'] ) ) {
				continue;
			}

			$cells = ( isset( $record['fields'] ) && is_array( $record['fields'] ) ) ? $record['fields'] : array();
			$name  = sanitize_text_field( WPCPM_Airtable::flatten( self::cell( $cells, $name_field ) ) );

			// A country with no name cannot be offered on a form or printed on a row, and
			// the fixture records none. Skipped rather than stored blank.
			if ( '' === $name ) {
				continue;
			}

			$rows[ (string) $record['id'] ] = array(
				'name'     => $name,
				'manager'  => sanitize_text_field( self::first( self::cell( $cells, self::FIELD_CONTACT ) ) ),
				'email'    => self::email( self::first( self::cell( $cells, self::FIELD_EMAIL ) ) ),
				'calendly' => self::url( self::first( self::cell( $cells, self::FIELD_CALENDLY ) ) ),
			);
		}

		if ( empty( $rows ) ) {
			return new WP_Error(
				'wpcpm_countries_no_names',
				sprintf(
					/* translators: 1: Airtable field name, 2: Airtable table ID. */
					__( 'Read %2$s but found no names in the column "%1$s", so the stored map was kept. Check the countries name field setting.', 'wpcredits-program-manager' ),
					$name_field,
					$table
				)
			);
		}

		update_option(
			self::OPT_NAME,
			array(
				'v'    => self::VERSION,
				'read' => time(),
				'rows' => $rows,
			),
			false
		);

		return true;
	}

	/**
	 * The column holding the country's name.
	 *
	 * The table's primary field, `Name` in the base today. From the setting rather than
	 * hard-wired, in the shape of the other three name-field settings, so a renamed
	 * column is a settings change and not a release.
	 *
	 * @param array $settings Plugin settings.
	 * @return string
	 */
	private static function name_field( array $settings ) {
		$field = isset( $settings['countries_name_field'] ) ? trim( (string) $settings['countries_name_field'] ) : '';

		return '' === $field ? 'Name' : $field;
	}

	/**
	 * One cell of a record, or an empty string when the column is absent.
	 *
	 * Airtable omits empty cells from the response rather than sending them null, so a
	 * missing key is the normal shape of "nothing there".
	 *
	 * @param array  $cells The record's fields.
	 * @param string $field Column name.
	 * @return mixed
	 */
	private static function cell( array $cells, $field ) {
		return isset( $cells[ $field ] ) ? $cells[ $field ] : '';
	}

	/**
	 * The first value of a cell, as a string.
	 *
	 * A link and its lookups arrive as arrays even when they hold one item, and the link
	 * is set to prefer a single record. Should a second contact ever be linked, the
	 * first one routes: a mail embeds one booking link and one address, and joining two
	 * with a comma would produce a value that is neither.
	 *
	 * @param mixed $value The cell as Airtable returned it.
	 * @return string
	 */
	private static function first( $value ) {
		if ( is_array( $value ) ) {
			// A lookup of a collaborator field is an array of objects with a `name`, and
			// `flatten()` reads that shape on the single item just as it would the list.
			$value = ( isset( $value['name'] ) && is_scalar( $value['name'] ) ) ? $value : reset( $value );
		}

		return trim( WPCPM_Airtable::flatten( $value ) );
	}

	/**
	 * An address as stored: lowercased, or empty when it is not one.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function email( $value ) {
		$email = sanitize_email( strtolower( trim( (string) $value ) ) );

		return is_email( $email ) ? $email : '';
	}

	/**
	 * A booking link as stored: http or https, or empty.
	 *
	 * Restricted to the two web protocols because the value is embedded in a mail to an
	 * applicant as a link they are asked to click.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function url( $value ) {
		return (string) esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
	}

	/**
	 * Whether a row names anyone to route to.
	 *
	 * The link is the fact; the two lookups follow from it. Any of the three counts, so
	 * a manager whose Team row has no address yet is still a contact and not a gap.
	 *
	 * @param array $row A stored row.
	 * @return bool
	 */
	private static function has_contact( array $row ) {
		foreach ( array( 'manager', 'email', 'calendly' ) as $key ) {
			if ( isset( $row[ $key ] ) && '' !== trim( (string) $row[ $key ] ) ) {
				return true;
			}
		}

		return false;
	}
}
