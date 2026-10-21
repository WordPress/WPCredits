<?php
/**
 * The Mentors row an activation writes (1.122.33): `WPCPM_Mentor_Airtable_Row`.
 *
 * Design of 16 October 2026, section 3, decision 7, and section 7: one row per newly active mentor,
 * with the four cells the mentors sync reads and the applicant's answers whose columns exist, and a
 * row that already holds the applicant's address set back to Active and linked instead. What each
 * block pins, and why:
 *
 * - **Each cell is shaped by its column's type, from the base's schema.** `create_records()` sends
 *   no `typecast`, so a value of the wrong kind is a refusal of the whole row: a country is a link,
 *   a choice or text by the column; the languages and the contribution areas are links, choices or
 *   joined text; the hours a number or text; the teacher answer a choice, a checkbox or text; the
 *   free text as typed, never as `&lt;` or `&amp;`.
 * - **What the base does not hold is left out by name and the rest is written**: a language or a
 *   contribution area the base lacks, a column the table lacks, a column of a type the site does
 *   not write. Without the base's columns, the four cells alone.
 * - **A linked table that cannot be read stops the write**, because the site never updates a row
 *   afterwards and a row without its links would stay without them. The linked names are read once
 *   a day each.
 * - **The row is found by the applicant's address first**, and one row is never made where one
 *   holds the address: a returning mentor's own row, or the row found, has its Status set back to
 *   the sync's status; two rows, or a row another account holds, are a person's to settle.
 * - **The check row** (`wp wpcredits mentor_airtable_row --check`): one synthetic row, written with
 *   a Status neither the sync nor the checker reads, read back and deleted, to learn what the live
 *   base keeps. A row already holding its address refuses the check; a create whose answer was lost
 *   is searched for and the row Airtable stored is deleted, and a search or a delete that fails
 *   never reads as "nothing was stored".
 *
 * Runs on the shared stand-in (bin/stubs/mentor-applications.php) with the real
 * WPCPM_Mentor_Application, WPCPM_Mentors_Sync and WPCPM_Typed_Text; the Airtable client, the
 * settings and the form's lists are stood in below, so every Airtable answer comes from here.
 *
 * Run from the plugin root:  php bin/test-mentor-airtable-row.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/mentor-applications.php';

$GLOBALS['settings']  = array();
$GLOBALS['unmodeled'] = array();

/** The settings the row reads, as a check sets them. */
class WPCPM_Settings {
	public static function get() {
		return array_merge(
			array(
				'mentors_table'         => 'tblMENTORS0000001',
				'mentor_status'         => 'Active',
				'checker_source_status' => 'Vetted - positive',
				'checker_target_status' => 'Active',
			),
			$GLOBALS['settings']
		);
	}
}

/** The form's lists, which the column's choices do not hold. */
class WPCPM_Mentor_Application_Form {
	public static function countries() {
		return array( 'Poland' );
	}
	public static function teams() {
		return array( 'Hosting' );
	}
}

/**
 * The Airtable client, at its contract. The answers come from the globals a check sets; every read
 * and write is kept for the check to read. `table_in()`, `formula_in()`, `flatten()` and
 * `is_record_id()` are the real client's, copied.
 */
class WPCPM_Airtable {
	const RECORD_ID_PATTERN = '/^rec[A-Za-z0-9]{14}$/';

	/** The copy the client keeps: the schema as it was, when a check sets a stale one, else the base's. */
	public function cached_schema() {
		if ( $GLOBALS['schema_error'] instanceof WP_Error ) {
			return $GLOBALS['schema_error'];
		}

		return array(
			'schema' => null !== $GLOBALS['schema_stale'] ? $GLOBALS['schema_stale'] : $GLOBALS['schema'],
			'age'    => null !== $GLOBALS['schema_stale'] ? 600 : 0,
		);
	}

	/** The base's schema as it is now: a read, never the kept copy. */
	public function fetch_schema() {
		$GLOBALS['schema_fetches'] = $GLOBALS['schema_fetches'] + 1;

		return $GLOBALS['schema_error'] instanceof WP_Error ? $GLOBALS['schema_error'] : $GLOBALS['schema'];
	}

	public function fetch_all( $table, array $args = array() ) {
		$GLOBALS['reads'][ $table ] = ( isset( $GLOBALS['reads'][ $table ] ) ? $GLOBALS['reads'][ $table ] : 0 ) + 1;

		if ( isset( $GLOBALS['linked_error'][ $table ] ) ) {
			return $GLOBALS['linked_error'][ $table ];
		}

		return isset( $GLOBALS['linked'][ $table ] ) ? $GLOBALS['linked'][ $table ] : array();
	}

	public function fetch_page( $table, array $args = array() ) {
		$GLOBALS['searched'][] = array(
			'table' => $table,
			'args'  => $args,
		);

		$error = $GLOBALS['create_asked'] ? $GLOBALS['refuse_search_after'] : $GLOBALS['refuse_search'];

		if ( $error instanceof WP_Error ) {
			return $error;
		}

		$ids     = ( $GLOBALS['create_asked'] && null !== $GLOBALS['by_email_after'] ) ? $GLOBALS['by_email_after'] : $GLOBALS['by_email'];
		$records = array();

		// A row is named by its ID, and then holds what the check row holds, or is a whole record.
		foreach ( $ids as $id ) {
			$records[] = is_array( $id ) ? $id : array(
				'id'     => $id,
				'fields' => array(
					'Email'     => 'held@example.test',
					'Full Name' => 'Mentor row check',
					'Status'    => 'Inactive',
				),
			);
		}

		return array(
			'records' => $records,
			'offset'  => null,
		);
	}

	public function create_records( $table, array $records ) {
		$GLOBALS['create_asked'] = true;

		if ( $GLOBALS['refuse_create'] instanceof WP_Error ) {
			return $GLOBALS['refuse_create'];
		}

		foreach ( array_values( $records ) as $index => $record ) {
			$GLOBALS['created'][] = $record;

			if ( isset( $GLOBALS['created_ids'][ $index ] ) ) {
				$GLOBALS['stored'][ $GLOBALS['created_ids'][ $index ] ] = $record['fields'];
			}
		}

		return $GLOBALS['created_ids'];
	}

	public function update_records( $table, array $records ) {
		if ( $GLOBALS['refuse_update'] instanceof WP_Error ) {
			return $GLOBALS['refuse_update'];
		}

		$done = array();

		foreach ( $records as $record ) {
			$GLOBALS['updated'][]    = $record;
			$done[ $record['id'] ] = true;
		}

		return $done;
	}

	/** What Airtable reads back: the fields created, less a false checkbox and an empty value, which it leaves out. */
	public function get_record( $table, $record_id ) {
		if ( $GLOBALS['refuse_read'] instanceof WP_Error ) {
			return $GLOBALS['refuse_read'];
		}

		if ( ! isset( $GLOBALS['stored'][ $record_id ] ) ) {
			return new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 404): NOT_FOUND', array( 'status' => 404 ) );
		}

		$fields = array();

		foreach ( $GLOBALS['stored'][ $record_id ] as $column => $value ) {
			if ( in_array( $column, $GLOBALS['drop_on_read'], true ) || false === $value || '' === $value || array() === $value ) {
				continue;
			}

			$fields[ $column ] = $value;
		}

		return array(
			'id'     => $record_id,
			'fields' => $fields,
		);
	}

	public function delete_records( $table, array $ids ) {
		if ( $GLOBALS['refuse_delete'] instanceof WP_Error ) {
			return $GLOBALS['refuse_delete'];
		}

		$done = array();

		foreach ( $ids as $id ) {
			$GLOBALS['deleted'][] = $id;
			$done[ $id ]          = true;
		}

		return $done;
	}

	public static function table_in( array $schema, $table ) {
		$table = (string) $table;

		if ( '' === $table ) {
			return new WP_Error( 'wpcpm_airtable_no_table', 'No table is set.', array( 'table' => '' ) );
		}

		if ( isset( $schema[ $table ] ) && is_array( $schema[ $table ] ) ) {
			return $schema[ $table ];
		}

		foreach ( $schema as $candidate ) {
			if ( is_array( $candidate ) && isset( $candidate['name'] ) && $table === (string) $candidate['name'] ) {
				return $candidate;
			}
		}

		return new WP_Error( 'wpcpm_airtable_no_table', 'The base has no table ' . $table . '.', array( 'table' => $table ) );
	}

	public function formula_in( $field, array $values, $lower = false, $trim = false ) {
		$values = array_map( 'strval', $values );
		$values = array_values( array_filter( $trim ? array_map( 'trim', $values ) : $values, 'strlen' ) );

		if ( empty( $values ) ) {
			return '';
		}

		$column = '{' . str_replace( array( '\\', '}' ), array( '\\\\', '\\}' ), $field ) . '}';
		$column = $lower ? 'LOWER(' . $column . ')' : $column;
		$column = $trim ? 'TRIM(' . $column . ')' : $column;
		$tests  = array();

		foreach ( $values as $value ) {
			if ( $lower ) {
				$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
			}

			$tests[] = $column . ' = \'' . str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), $value ) . '\'';
		}

		return 1 === count( $tests ) ? $tests[0] : 'OR(' . implode( ',', $tests ) . ')';
	}

	public static function flatten( $value, $glue = ', ' ) {
		if ( is_array( $value ) ) {
			if ( isset( $value['name'] ) && is_scalar( $value['name'] ) ) {
				return (string) $value['name'];
			}

			$parts = array();

			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) {
					$parts[] = (string) $item;
				} elseif ( is_array( $item ) && isset( $item['name'] ) && is_scalar( $item['name'] ) ) {
					$parts[] = (string) $item['name'];
				}
			}

			return implode( $glue, array_filter( $parts, 'strlen' ) );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	public static function is_record_id( $value ) {
		return is_scalar( $value ) && 1 === preg_match( self::RECORD_ID_PATTERN, trim( (string) $value ) );
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-airtable-row.php';

$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/** Every answer the client gives, and everything it was asked, emptied; the site's accounts and the kept names with them. */
function fresh_client() {
	$GLOBALS['settings']            = array();
	$GLOBALS['transients']          = array();
	$GLOBALS['users']               = array();
	$GLOBALS['umeta']               = array();
	$GLOBALS['schema']              = schema();
	$GLOBALS['schema_error']        = null;
	$GLOBALS['schema_stale']       = null;
	$GLOBALS['schema_fetches']     = 0;
	$GLOBALS['linked']              = array(
		'tblCOUNTRIES00001' => array(
			array(
				'id'     => 'recCOUNTRYPL00001',
				'fields' => array( 'Name' => 'Poland' ),
			),
		),
		'tblLANGUAGES00001' => array(
			array(
				'id'     => 'recLANGENGLISH001',
				'fields' => array( 'Name' => 'English' ),
			),
			array(
				'id'     => 'recLANGPOLISH0001',
				'fields' => array( 'Name' => 'Polish' ),
			),
		),
	);
	$GLOBALS['linked_error']        = array();
	$GLOBALS['reads']               = array();
	$GLOBALS['searched']            = array();
	$GLOBALS['by_email']            = array();
	$GLOBALS['by_email_after']      = null;
	$GLOBALS['create_asked']        = false;
	$GLOBALS['created']             = array();
	$GLOBALS['created_ids']         = array( 'recNEWMENTOR00001' );
	$GLOBALS['stored']              = array();
	$GLOBALS['updated']             = array();
	$GLOBALS['deleted']             = array();
	$GLOBALS['drop_on_read']        = array();
	$GLOBALS['refuse_search']       = null;
	$GLOBALS['refuse_search_after'] = null;
	$GLOBALS['refuse_create']       = null;
	$GLOBALS['refuse_update']       = null;
	$GLOBALS['refuse_read']         = null;
	$GLOBALS['refuse_delete']       = null;
}

/** A choice list as the schema keeps one. */
function choices( array $names ) {
	return array(
		'choices' => array_map(
			function ( $name ) {
				return array( 'name' => $name );
			},
			$names
		),
	);
}

/**
 * The bare schema, table id to table, as `fetch_schema()` answers it: the Mentors table and the two
 * tables its links point to. A change replaces that column's spec, or drops the column when null.
 */
function schema( array $changes = array() ) {
	$columns = array(
		'Full Name'                                 => array( 'type' => 'singleLineText' ),
		'WordPress profile'                         => array( 'type' => 'url' ),
		'Email'                                     => array( 'type' => 'email' ),
		'Status'                                    => array(
			'type'    => 'singleSelect',
			'options' => choices( array( 'Interested', 'Vetted - positive', 'Active', 'Inactive' ) ),
		),
		'Country'                                   => array(
			'type'    => 'multipleRecordLinks',
			'options' => array( 'linkedTableId' => 'tblCOUNTRIES00001' ),
		),
		'Languages'                                 => array(
			'type'    => 'multipleRecordLinks',
			'options' => array( 'linkedTableId' => 'tblLANGUAGES00001' ),
		),
		'Contribution Area - Expertise'             => array(
			'type'    => 'multipleSelects',
			'options' => choices( array( 'Polyglots', 'Training' ) ),
		),
		'Available hours per week'                  => array( 'type' => 'number' ),
		'Teacher from a participating institution?' => array(
			'type'    => 'singleSelect',
			'options' => choices( array( 'Yes', 'No' ) ),
		),
		"Anything else you\u{2019}d like us to know?" => array( 'type' => 'multilineText' ),
	);

	foreach ( $changes as $name => $spec ) {
		if ( null === $spec ) {
			unset( $columns[ $name ] );
		} else {
			$columns[ $name ] = $spec;
		}
	}

	$linked = array(
		'name'    => 'Linked',
		'primary' => 'Name',
		'fields'  => array( 'Name' => '' ),
		'columns' => array( 'Name' => array( 'type' => 'singleLineText' ) ),
		'ids'     => array(),
	);

	return array(
		'tblMENTORS0000001' => array(
			'name'    => 'Mentors',
			'primary' => 'Full Name',
			'fields'  => array_map(
				function () {
					return '';
				},
				$columns
			),
			'columns' => $columns,
			'ids'     => array(),
		),
		'tblCOUNTRIES00001' => array_merge( $linked, array( 'name' => 'Countries' ) ),
		'tblLANGUAGES00001' => array_merge( $linked, array( 'name' => 'Languages' ) ),
	);
}

/** A selected application, with the answers the form stores and what a check changes. */
function application( array $more = array() ) {
	$id = WPCPM_Mentor_Application::store(
		array_merge(
			array(
				'name'      => 'Ada Example',
				'email'     => 'ada@example.test',
				'profile'   => 'ada-example',
				'country'   => 'Poland',
				'teacher'   => 'No',
				'languages' => array( 'English', 'Polish' ),
				'expertise' => array( 'Polyglots', 'Training' ),
				'hours'     => '2.5',
				'anything'  => 'Tom & Jerry\'s "quote", and a &lt; b, Zoë &amp; co.',
			),
			$more
		),
		'new',
		array(),
		array()
	);

	WPCPM_Mentor_Application::decide( $id, WPCPM_Mentor_Application::STATE_SELECTED, WPCPM_Mentor_Application::EVENT_SELECTED, 1 );

	return get_post( $id );
}

/** The linked names as the row reads them: through the client, and not kept. */
function names() {
	return function ( $table, $primary ) {
		return WPCPM_Mentor_Airtable_Row::link_names( new WPCPM_Airtable(), $table, $primary );
	};
}

// Every warning, notice and deprecation of the run is kept; the last check names any.
$php_warnings = array();

set_error_handler(
	static function ( $number, $message ) use ( &$php_warnings ) {
		$php_warnings[] = $message;

		return true;
	}
);

echo "=== The names ===\n";

// Read from the contribution areas' own place rather than a fixed offset: the private sponsor tiers
// line names no Sponsor Company Name column (its sponsors live on the site), so its fields() holds
// one entry fewer before them than main's does.
$mentor_fields = WPCPM_Mentors_Sync::fields();
ck( 'five Mentors columns more are named by fields(), after the contribution areas, the last with the right single quotation mark the table spells it with',
	array_slice( $mentor_fields, (int) array_search( 'mentor_expertise', array_keys( $mentor_fields ), true ), 6 ),
	array(
		'mentor_expertise' => 'Contribution Area - Expertise',
		'mentor_country'   => 'Country',
		'mentor_languages' => 'Languages',
		'mentor_hours'     => 'Available hours per week',
		'mentor_teacher'   => 'Teacher from a participating institution?',
		'mentor_anything'  => "Anything else you\u{2019}d like us to know?",
	) );
ck( 'the answers the row carries, by the key of fields() each is written to, in the row\'s order',
	WPCPM_Mentor_Airtable_Row::ANSWERS,
	array(
		'country'   => 'mentor_country',
		'languages' => 'mentor_languages',
		'expertise' => 'mentor_expertise',
		'hours'     => 'mentor_hours',
		'teacher'   => 'mentor_teacher',
		'anything'  => 'mentor_anything',
	) );
ck( 'the linked names are kept under one transient, and the check status is one the base offers', array( WPCPM_Mentor_Airtable_Row::LINKS_TRANSIENT, WPCPM_Mentor_Airtable_Row::CHECK_STATUS ), array( 'wpcpm_mapp_link_names', 'Inactive' ) );

echo "\n=== The cells ===\n";

$anything = "Anything else you\u{2019}d like us to know?";
fresh_client();
$ada = application();
$row = WPCPM_Mentor_Airtable_Row::cells( $ada, schema(), names() );
ck( 'the row an application writes: the four cells the sync reads, then each answer by its column\'s type, the free text as typed',
	$row['cells'],
	array(
		'Full Name'                                 => 'Ada Example',
		'WordPress profile'                         => 'https://profiles.wordpress.org/ada-example/',
		'Email'                                     => 'ada@example.test',
		'Status'                                    => 'Active',
		'Country'                                   => array( 'recCOUNTRYPL00001' ),
		'Languages'                                 => array( 'recLANGENGLISH001', 'recLANGPOLISH0001' ),
		'Contribution Area - Expertise'             => array( 'Polyglots', 'Training' ),
		'Available hours per week'                  => 2.5,
		'Teacher from a participating institution?' => 'No',
		$anything                                   => 'Tom & Jerry\'s "quote", and a < b, Zoë & co.',
	) );
ck( 'and nothing is left out', $row['left_out'], array() );
ck( 'a name typed with quotes, an ampersand and a "<" is sent as typed',
	WPCPM_Mentor_Airtable_Row::cells( application( array( 'name' => 'Zoë "Z" Example & Co &lt;3' ) ), schema(), names() )['cells']['Full Name'], 'Zoë "Z" Example & Co <3' );

$wu = WPCPM_Mentor_Airtable_Row::cells( application( array( 'languages' => array( 'English', 'Wu Chinese (Shanghainese)' ), 'expertise' => array( 'Hosting', 'Polyglots' ) ) ), schema(), names() );
ck( 'a language the base does not hold is left out by name, and the others are linked; so is a contribution area the column does not offer',
	array( $wu['cells']['Languages'], $wu['cells']['Contribution Area - Expertise'], $wu['left_out'] ),
	array( array( 'recLANGENGLISH001' ), array( 'Polyglots' ), array(
		'Languages'                     => array( 'why' => 'unmatched', 'values' => array( 'Wu Chinese (Shanghainese)' ) ),
		'Contribution Area - Expertise' => array( 'why' => 'unmatched', 'values' => array( 'Hosting' ) ),
	) ) );

$no_hours = WPCPM_Mentor_Airtable_Row::cells( $ada, schema( array( 'Available hours per week' => null ) ), names() );
ck( 'an answer whose column the table lacks is left out, and the rest is written', array( array_key_exists( 'Available hours per week', $no_hours['cells'] ), $no_hours['left_out'], count( $no_hours['cells'] ) ), array( false, array( 'Available hours per week' => array( 'why' => 'no-column', 'values' => array() ) ), 9 ) );
ck( 'a column of a type the site does not write is left out with its type',
	WPCPM_Mentor_Airtable_Row::cells( $ada, schema( array( 'Available hours per week' => array( 'type' => 'rating' ) ) ), names() )['left_out'],
	array( 'Available hours per week' => array( 'why' => 'type', 'values' => array( 'rating' ) ) ) );
ck( 'the teacher answer fits its column: a choice, a checkbox, or text',
	array(
		WPCPM_Mentor_Airtable_Row::cells( $ada, schema(), names() )['cells']['Teacher from a participating institution?'],
		WPCPM_Mentor_Airtable_Row::cells( $ada, schema( array( 'Teacher from a participating institution?' => array( 'type' => 'checkbox' ) ) ), names() )['cells']['Teacher from a participating institution?'],
		WPCPM_Mentor_Airtable_Row::cells( application( array( 'teacher' => 'Yes' ) ), schema( array( 'Teacher from a participating institution?' => array( 'type' => 'checkbox' ) ) ), names() )['cells']['Teacher from a participating institution?'],
		WPCPM_Mentor_Airtable_Row::cells( $ada, schema( array( 'Teacher from a participating institution?' => array( 'type' => 'singleLineText' ) ) ), names() )['cells']['Teacher from a participating institution?'],
	),
	array( 'No', false, true, 'No' ) );
ck( 'languages go as text into a text column, and hours stay as typed in one',
	array(
		WPCPM_Mentor_Airtable_Row::cells( $ada, schema( array( 'Languages' => array( 'type' => 'singleLineText' ) ) ), names() )['cells']['Languages'],
		WPCPM_Mentor_Airtable_Row::cells( $ada, schema( array( 'Available hours per week' => array( 'type' => 'singleLineText' ) ) ), names() )['cells']['Available hours per week'],
	),
	array( 'English, Polish', '2.5' ) );
$quiet = WPCPM_Mentor_Airtable_Row::cells( application( array( 'anything' => '', 'expertise' => array() ) ), schema(), names() );
ck( 'an empty answer sends nothing and is not left out', array( array_key_exists( $anything, $quiet['cells'] ), array_key_exists( 'Contribution Area - Expertise', $quiet['cells'] ), $quiet['left_out'] ), array( false, false, array() ) );
$quiet_nocol = WPCPM_Mentor_Airtable_Row::cells( application( array( 'hours' => '' ) ), schema( array( 'Available hours per week' => null ) ), names() );
ck( 'and an empty answer whose column is not there is not left out either', $quiet_nocol['left_out'], array() );

ck( 'a country goes as a link, a choice or text by its column, the name matched trimmed and without regard to case',
	array(
		WPCPM_Mentor_Airtable_Row::cells( application( array( 'country' => ' poland ' ) ), schema(), names() )['cells']['Country'],
		WPCPM_Mentor_Airtable_Row::cells( $ada, schema( array( 'Country' => array( 'type' => 'singleSelect', 'options' => choices( array( 'Germany', 'Poland' ) ) ) ) ), names() )['cells']['Country'],
		WPCPM_Mentor_Airtable_Row::cells( $ada, schema( array( 'Country' => array( 'type' => 'singleLineText' ) ) ), names() )['cells']['Country'],
	),
	array( array( 'recCOUNTRYPL00001' ), 'Poland', 'Poland' ) );
$atlantis = WPCPM_Mentor_Airtable_Row::cells( application( array( 'country' => 'Atlantis' ) ), schema(), names() );
ck( 'a country the base does not hold is left out by name and the rest is written', array( array_key_exists( 'Country', $atlantis['cells'] ), $atlantis['left_out'], count( $atlantis['cells'] ) ), array( false, array( 'Country' => array( 'why' => 'unmatched', 'values' => array( 'Atlantis' ) ) ), 9 ) );
ck( 'and a country that is no choice of a choice column is left out the same way',
	WPCPM_Mentor_Airtable_Row::cells( $ada, schema( array( 'Country' => array( 'type' => 'singleSelect', 'options' => choices( array( 'Germany' ) ) ) ) ), names() )['left_out'],
	array( 'Country' => array( 'why' => 'unmatched', 'values' => array( 'Poland' ) ) ) );
ck( 'the contribution areas go as text into a long-text column, and a column that links matches them by name in its own table',
	array(
		WPCPM_Mentor_Airtable_Row::cells( $ada, schema( array( 'Contribution Area - Expertise' => array( 'type' => 'multilineText' ) ) ), names() )['cells']['Contribution Area - Expertise'],
		WPCPM_Mentor_Airtable_Row::cells( $ada, schema( array( 'Contribution Area - Expertise' => array( 'type' => 'multipleRecordLinks', 'options' => array( 'linkedTableId' => 'tblLANGUAGES00001' ) ) ) ), names() )['left_out'],
	),
	array( 'Polyglots, Training', array( 'Contribution Area - Expertise' => array( 'why' => 'unmatched', 'values' => array( 'Polyglots', 'Training' ) ) ) ) );
ck( 'the free text goes into a single line, a long text or a rich text column alike, as typed, and into no other',
	array(
		WPCPM_Mentor_Airtable_Row::cells( $ada, schema( array( $anything => array( 'type' => 'singleLineText' ) ) ), names() )['cells'][ $anything ],
		WPCPM_Mentor_Airtable_Row::cells( $ada, schema( array( $anything => array( 'type' => 'richText' ) ) ), names() )['cells'][ $anything ],
		WPCPM_Mentor_Airtable_Row::cells( $ada, schema( array( $anything => array( 'type' => 'number' ) ) ), names() )['left_out'],
	),
	array( 'Tom & Jerry\'s "quote", and a < b, Zoë & co.', 'Tom & Jerry\'s "quote", and a < b, Zoë & co.', array( $anything => array( 'why' => 'type', 'values' => array( 'number' ) ) ) ) );

$blind = WPCPM_Mentor_Airtable_Row::cells( $ada, null, names() );
ck( 'without the base\'s columns, the four cells alone, and the answers said to be left out',
	array( array_keys( $blind['cells'] ), $blind['left_out'], WPCPM_Mentor_Airtable_Row::left_out_sentence( $blind['left_out'] ) ),
	array( array( 'Full Name', 'WordPress profile', 'Email', 'Status' ), array( '*' => array( 'why' => 'no-schema', 'values' => array() ) ), 'The answers were left out: the token cannot read the base\'s columns.' ) );
ck( 'and a schema without the Mentors table is read as none', WPCPM_Mentor_Airtable_Row::cells( $ada, array( 'tblOTHER000000001' => array( 'name' => 'Other', 'primary' => 'Name', 'columns' => array() ) ), names() )['left_out'], array( '*' => array( 'why' => 'no-schema', 'values' => array() ) ) );
fresh_client();
$GLOBALS['schema'] = schema();
ck( 'preview() reads the schema cached_schema() holds, not its wrapper, and the names through the client', WPCPM_Mentor_Airtable_Row::preview( $ada, new WPCPM_Airtable() ), $row );
$GLOBALS['schema_error'] = new WP_Error( 'wpcpm_airtable_error', 'This was a schema read, so the token most likely lacks the "schema.bases:read" scope.', array( 'status' => 403, 'error_type' => 'NOT_AUTHORIZED' ) );
ck( 'and a schema the token is refused (a 403, the missing schema scope) is none', WPCPM_Mentor_Airtable_Row::preview( $ada, new WPCPM_Airtable() )['left_out'], array( '*' => array( 'why' => 'no-schema', 'values' => array() ) ) );
$GLOBALS['schema_error'] = new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 401): AUTHENTICATION_REQUIRED', array( 'status' => 401 ) );
ck( 'and so is a 401', array_keys( WPCPM_Mentor_Airtable_Row::preview( $ada, new WPCPM_Airtable() )['left_out'] ), array( '*' ) );
foreach ( array(
	'a timeout'         => new WP_Error( 'http_request_failed', 'Operation timed out after 20000 milliseconds.' ),
	'a 500'             => new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 500): no further detail', array( 'status' => 500, 'error_type' => '' ) ),
	'a 404 on the base' => new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 404): NOT_FOUND', array( 'status' => 404, 'error_type' => 'NOT_FOUND' ) ),
) as $what => $failure ) {
	$GLOBALS['schema_error'] = $failure;
	$unreadable              = WPCPM_Mentor_Airtable_Row::preview( $ada, new WPCPM_Airtable() );
	ck( 'any other failure to read the schema is not "no schema": ' . $what . ' leaves the columns unread, with the error', array( array_keys( $unreadable['cells'] ), $unreadable['left_out'] ), array( array( 'Full Name', 'WordPress profile', 'Email', 'Status' ), array( '*' => array( 'why' => 'unread', 'values' => array( $failure->get_error_message() ) ) ) ) );
}
$GLOBALS['schema_error'] = null;
$GLOBALS['schema']       = array( 'tblOTHER000000001' => array( 'name' => 'Other', 'primary' => 'Name', 'columns' => array() ) );
ck( 'and a base without the Mentors table, a wrong table setting, is unread too, with the client\'s own words', WPCPM_Mentor_Airtable_Row::preview( $ada, new WPCPM_Airtable() )['left_out'], array( '*' => array( 'why' => 'unread', 'values' => array( 'The base has no table tblMENTORS0000001.' ) ) ) );
$GLOBALS['schema'] = schema();
ck( 'the sentence for columns that could not be read', WPCPM_Mentor_Airtable_Row::left_out_sentence( array( '*' => array( 'why' => 'unread', 'values' => array( 'Operation timed out.' ) ) ) ), 'The base\'s columns could not be read.' );
// The preview on the command line asks for the reason too, since nothing else there says it; the
// Activated view does not, since the refusal it prints beside the sentence says it already.
ck( 'and, asked for the reasons, the sentence for what could not be read says why, in the words Airtable or the site gave, closed with a full stop',
	array(
		WPCPM_Mentor_Airtable_Row::left_out_sentence( array( '*' => array( 'why' => 'unread', 'values' => array( 'Operation timed out.' ) ) ), true ),
		WPCPM_Mentor_Airtable_Row::left_out_sentence( array( 'Languages' => array( 'why' => 'unread', 'values' => array( 'Airtable is out of reach' ) ) ), true ),
		WPCPM_Mentor_Airtable_Row::left_out_sentence( array( '*' => array( 'why' => 'unread', 'values' => array() ) ), true ),
		WPCPM_Mentor_Airtable_Row::left_out_sentence( array( 'Languages' => array( 'why' => 'unmatched', 'values' => array( 'Klingon' ) ) ), true ),
	),
	array( 'The base\'s columns could not be read: Operation timed out.', 'Languages: its linked table could not be read: Airtable is out of reach.', 'The base\'s columns could not be read.', 'Languages: not in the base: Klingon.' ) );
$unread = WPCPM_Mentor_Airtable_Row::cells( $ada, schema(), function ( $table, $primary ) { return 'tblLANGUAGES00001' === $table ? new WP_Error( 'airtable', 'Airtable is out of reach.' ) : WPCPM_Mentor_Airtable_Row::link_names( new WPCPM_Airtable(), $table, $primary ); } );
ck( 'a linked table that cannot be read leaves its answers out with the error, and the other link is made',
	array( $unread['left_out'], $unread['cells']['Country'], WPCPM_Mentor_Airtable_Row::left_out_sentence( $unread['left_out'] ) ),
	array( array( 'Languages' => array( 'why' => 'unread', 'values' => array( 'Airtable is out of reach.' ) ) ), array( 'recCOUNTRYPL00001' ), 'Languages: its linked table could not be read.' ) );
$no_table = schema();
unset( $no_table['tblLANGUAGES00001'] );
ck( 'and so does a linked table the schema does not hold', array_keys( WPCPM_Mentor_Airtable_Row::cells( $ada, $no_table, names() )['left_out'] ), array( 'Languages' ) );
ck( 'every other reason in a sentence of its own',
	WPCPM_Mentor_Airtable_Row::left_out_sentence( array_merge( $wu['left_out'], array( 'Available hours per week' => array( 'why' => 'type', 'values' => array( 'rating' ) ) ) ) ),
	'Languages: not in the base: Wu Chinese (Shanghainese). Contribution Area - Expertise: not in the base: Hosting. Available hours per week: the column is a rating, which the site does not write.' );
ck( 'the column the table lacks has its sentence, and none left out has none', array( WPCPM_Mentor_Airtable_Row::left_out_sentence( $no_hours['left_out'] ), WPCPM_Mentor_Airtable_Row::left_out_sentence( array() ) ), array( 'Available hours per week: the Mentors table has no such column.', '' ) );

echo "\n=== The linked names ===\n";

fresh_client();
$client = new WPCPM_Airtable();
ck( 'a linked table\'s names are its first column\'s, lowercased and trimmed, to the record id', WPCPM_Mentor_Airtable_Row::link_names( $client, 'tblCOUNTRIES00001', 'Name' ), array( 'poland' => 'recCOUNTRYPL00001' ) );
ck( 'read and not kept, unless asked: the next read asks the base again, and nothing is kept', array( WPCPM_Mentor_Airtable_Row::link_names( $client, 'tblCOUNTRIES00001', 'Name' ), $GLOBALS['reads'], isset( $GLOBALS['transients'][ WPCPM_Mentor_Airtable_Row::LINKS_TRANSIENT ] ) ), array( array( 'poland' => 'recCOUNTRYPL00001' ), array( 'tblCOUNTRIES00001' => 2 ), false ) );
fresh_client();
WPCPM_Mentor_Airtable_Row::link_names( $client, 'tblCOUNTRIES00001', 'Name', true );
WPCPM_Mentor_Airtable_Row::link_names( $client, 'tblCOUNTRIES00001', 'Name', true );
ck( 'kept, a table is read once, and the kept names are one transient, table id to when it was read and the names', array( $GLOBALS['reads'], array_keys( $GLOBALS['transients'][ WPCPM_Mentor_Airtable_Row::LINKS_TRANSIENT ] ), array_keys( $GLOBALS['transients'][ WPCPM_Mentor_Airtable_Row::LINKS_TRANSIENT ]['tblCOUNTRIES00001'] ) ), array( array( 'tblCOUNTRIES00001' => 1 ), array( 'tblCOUNTRIES00001' ), array( 'read', 'names' ) ) );
$GLOBALS['transients'][ WPCPM_Mentor_Airtable_Row::LINKS_TRANSIENT ]['tblCOUNTRIES00001']['read'] = time() - DAY_IN_SECONDS - 5;
WPCPM_Mentor_Airtable_Row::link_names( $client, 'tblCOUNTRIES00001', 'Name', true );
ck( 'and read again once a day has passed', $GLOBALS['reads'], array( 'tblCOUNTRIES00001' => 2 ) );
WPCPM_Mentor_Airtable_Row::link_names( $client, 'tblLANGUAGES00001', 'Name', true );
ck( 'each table has its own entry, and another\'s read does not renew it', array_keys( $GLOBALS['transients'][ WPCPM_Mentor_Airtable_Row::LINKS_TRANSIENT ] ), array( 'tblCOUNTRIES00001', 'tblLANGUAGES00001' ) );
fresh_client();
$GLOBALS['linked_error']['tblCOUNTRIES00001'] = new WP_Error( 'airtable', 'Airtable is out of reach.' );
$failed = WPCPM_Mentor_Airtable_Row::link_names( $client, 'tblCOUNTRIES00001', 'Name', true );
ck( 'a read that fails is answered as the error, and nothing is kept', array( is_wp_error( $failed ), $failed->get_error_message(), isset( $GLOBALS['transients'][ WPCPM_Mentor_Airtable_Row::LINKS_TRANSIENT ] ) ), array( true, 'Airtable is out of reach.', false ) );
fresh_client();
$GLOBALS['linked']['tblCOUNTRIES00001'] = array(
	array( 'id' => 'recCOUNTRYPL00001', 'fields' => array( 'Name' => ' Poland ' ) ),
	array( 'id' => 'recCOUNTRYPL00002', 'fields' => array( 'Name' => 'POLAND' ) ),
	array( 'id' => 'recCOUNTRYNONAME1', 'fields' => array() ),
	array( 'fields' => array( 'Name' => 'No id' ) ),
	array( 'id' => 'recCOUNTRYDE00001', 'fields' => array( 'Name' => 'Zoë' ) ),
);
ck( 'a name held twice is the first row\'s, a row with no name or no id is passed over, and letters past ASCII are lowercased', WPCPM_Mentor_Airtable_Row::link_names( $client, 'tblCOUNTRIES00001', 'Name' ), array( 'poland' => 'recCOUNTRYPL00001', 'zoë' => 'recCOUNTRYDE00001' ) );

echo "\n=== The search by address ===\n";

fresh_client();
$GLOBALS['by_email'] = array( 'recADOPTED0000001', 'not an id' );
$found               = WPCPM_Mentor_Airtable_Row::find_by_email( ' Ada@Example.test ', new WPCPM_Airtable() );
ck( 'one search of the Mentors table by the address, trimmed and lowercased on both sides, with the Email field alone; what is no record id is dropped',
	array( $found, $GLOBALS['searched'] ),
	array( array( 'recADOPTED0000001' ), array( array( 'table' => 'tblMENTORS0000001', 'args' => array( 'formula' => 'TRIM(LOWER({Email})) = \'ada@example.test\'', 'fields' => array( 'Email' ) ) ) ) ) );
fresh_client();
$nothing = WPCPM_Mentor_Airtable_Row::find_by_email( '  ', new WPCPM_Airtable() );
ck( 'no address is no search: a formula of nothing would read the table', array( is_wp_error( $nothing ), $GLOBALS['searched'] ), array( true, array() ) );
$GLOBALS['refuse_search'] = new WP_Error( 'airtable', 'Airtable is out of reach.' );
ck( 'and a search Airtable refuses is answered as the error', WPCPM_Mentor_Airtable_Row::find_by_email( 'ada@example.test', new WPCPM_Airtable() )->get_error_message(), 'Airtable is out of reach.' );

echo "\n=== The write ===\n";

fresh_client();
$GLOBALS['schema'] = schema();
$wrote             = WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() );
ck( 'with no row holding the address, write() searches by it, then creates one row from the cells and answers its record id',
	array( $wrote['ok'], $wrote['how'], $wrote['record'], $GLOBALS['searched'][0]['table'], $GLOBALS['created'], $GLOBALS['updated'] ),
	array( true, 'created', 'recNEWMENTOR00001', 'tblMENTORS0000001', array( array( 'fields' => $row['cells'] ) ), array() ) );
ck( 'and says what it left out, which is nothing here', array( $wrote['error'], $wrote['left_out'] ), array( '', array() ) );
WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() );
ck( 'the linked tables are read once a day each, not once a row', $GLOBALS['reads'], array( 'tblCOUNTRIES00001' => 1, 'tblLANGUAGES00001' => 1 ) );
fresh_client();
$wu_app = application( array( 'languages' => array( 'English', 'Wu Chinese (Shanghainese)' ) ) );
$wu_row = WPCPM_Mentor_Airtable_Row::write( $wu_app, '', 70, new WPCPM_Airtable() );
ck( 'a row made without a name the base does not hold says which, and is written all the same', array( $wu_row['ok'], $wu_row['left_out'], $GLOBALS['created'][0]['fields']['Languages'] ), array( true, array( 'Languages' => array( 'why' => 'unmatched', 'values' => array( 'Wu Chinese (Shanghainese)' ) ) ), array( 'recLANGENGLISH001' ) ) );
fresh_client();
$back = WPCPM_Mentor_Airtable_Row::write( $ada, 'recRETURNING00001', 70, new WPCPM_Airtable() );
ck( 'for a returning mentor, write() sets their own row\'s Status back to the sync\'s status, searches nothing and creates nothing',
	array( $back['ok'], $back['how'], $back['record'], $GLOBALS['updated'], $GLOBALS['created'], $GLOBALS['searched'] ),
	array( true, 'reactivated', 'recRETURNING00001', array( array( 'id' => 'recRETURNING00001', 'fields' => array( 'Status' => 'Active' ) ) ), array(), array() ) );
fresh_client();
$GLOBALS['settings']['mentor_status'] = 'Mentoring';
WPCPM_Mentor_Airtable_Row::write( $ada, 'recRETURNING00001', 70, new WPCPM_Airtable() );
ck( 'and the status is the one the mentors sync reads, whatever the setting holds', $GLOBALS['updated'][0]['fields'], array( 'Status' => 'Mentoring' ) );
fresh_client();
$GLOBALS['refuse_update'] = new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 403): NOT_AUTHORIZED', array( 'status' => 403, 'error_type' => 'NOT_AUTHORIZED' ) );
$denied                   = WPCPM_Mentor_Airtable_Row::write( $ada, 'recRETURNING00001', 70, new WPCPM_Airtable() );
ck( 'a refusal of that update is answered in Airtable\'s words, with no search and no second row', array( $denied['ok'], $denied['how'], $denied['record'], $denied['error'], $GLOBALS['searched'], $GLOBALS['created'] ), array( false, '', '', 'Airtable request failed (HTTP 403): NOT_AUTHORIZED', array(), array() ) );
fresh_client();
$GLOBALS['by_email'] = array( 'recADOPTED0000001' );
$adopt               = WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() );
ck( 'a row that already holds the address is set back to Active and answered, whatever its Status, and none is created',
	array( $adopt['how'], $adopt['record'], $GLOBALS['updated'], $GLOBALS['created'] ),
	array( 'adopted', 'recADOPTED0000001', array( array( 'id' => 'recADOPTED0000001', 'fields' => array( 'Status' => 'Active' ) ) ), array() ) );
an_account( 71, 'Other Example', 'other@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recADOPTED0000001' ) );
ck( 'but not one another account on the site holds', WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() )['error'], 'The Mentors row with this address is linked to another account on this site, ' . get_userdata( 71 )->user_login . '.' );
an_account( 70, 'Ada Example', 'ada@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recADOPTED0000009' ) );
$GLOBALS['by_email'] = array( 'recADOPTED0000009' );
ck( 'while the account being made a mentor holding the row is no other', WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() )['how'], 'adopted' );
$GLOBALS['by_email'] = array( 'recADOPTED0000001', 'recADOPTED0000002' );
ck( 'and two rows with the address are a person\'s to choose between', array( WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() )['error'], count( $GLOBALS['created'] ) ), array( 'Airtable holds 2 Mentors rows with this address. Keep one, then press Write the Airtable row again.', 0 ) );
fresh_client();
$GLOBALS['by_email']      = array( 'recADOPTED0000001' );
$GLOBALS['refuse_update'] = new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 403): NOT_AUTHORIZED', array( 'status' => 403 ) );
$adopt_denied             = WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() );
ck( 'a row found whose Status Airtable refuses to set is answered as the refusal, and is not adopted', array( $adopt_denied['ok'], $adopt_denied['how'], $adopt_denied['error'] ), array( false, '', 'Airtable request failed (HTTP 403): NOT_AUTHORIZED' ) );
fresh_client();
$GLOBALS['refuse_search'] = new WP_Error( 'airtable', 'Airtable is out of reach.' );
$no_search                = WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() );
ck( 'a search that is refused makes no row: it cannot say none holds the address', array( $no_search['ok'], $no_search['error'], $GLOBALS['created'] ), array( false, 'Airtable is out of reach.', array() ) );
fresh_client();
$GLOBALS['refuse_update'] = new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 404): NOT_FOUND', array( 'status' => 404, 'error_type' => 'NOT_FOUND' ) );
$gone                     = WPCPM_Mentor_Airtable_Row::write( $ada, 'recRETURNING00001', 70, new WPCPM_Airtable() );
ck( 'a returning mentor whose row was deleted in Airtable is searched for by address, and gets a new row', array( $gone['ok'], $gone['how'], $gone['record'], count( $GLOBALS['searched'] ), $GLOBALS['updated'] ), array( true, 'created', 'recNEWMENTOR00001', 1, array() ) );
fresh_client();
$GLOBALS['refuse_update'] = new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 422): ROW_DOES_NOT_EXIST', array( 'status' => 422, 'error_type' => 'ROW_DOES_NOT_EXIST' ) );
ck( 'and so is one Airtable names as a row that does not exist, whatever the status', WPCPM_Mentor_Airtable_Row::write( $ada, 'recRETURNING00001', 70, new WPCPM_Airtable() )['how'], 'created' );
fresh_client();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
ck( 'a refusal is answered, never thrown, and nothing is recorded as made', array( WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() )['error'], $GLOBALS['created'] ), array( 'Airtable refused the write.', array() ) );
// A linked row deleted in Airtable since its name was kept makes every create that names it refused:
// the refusal lets go of the kept names, so the next write reads the linked tables again rather than
// failing for the rest of the day.
fresh_client();
WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() );
$kept_before              = isset( $GLOBALS['transients'][ WPCPM_Mentor_Airtable_Row::LINKS_TRANSIENT ] );
$GLOBALS['refuse_create'] = new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 422): ROW_DOES_NOT_EXIST', array( 'status' => 422, 'error_type' => 'ROW_DOES_NOT_EXIST' ) );
$refused_links            = WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() );
$kept_after               = isset( $GLOBALS['transients'][ WPCPM_Mentor_Airtable_Row::LINKS_TRANSIENT ] );
$GLOBALS['refuse_create'] = null;
$written_again            = WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() );
ck( 'a refused create lets go of the kept linked names, so the next write reads the linked tables again',
	array( $kept_before, $refused_links['ok'], $kept_after, $written_again['ok'], $GLOBALS['reads'] ),
	array( true, false, false, true, array( 'tblCOUNTRIES00001' => 2, 'tblLANGUAGES00001' => 2 ) ) );
fresh_client();
$GLOBALS['created_ids'] = array();
ck( 'and an answer with no record id in it is a refusal too', WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() )['error'], 'Airtable did not answer with a record ID.' );
fresh_client();
$GLOBALS['created_ids'] = array( 'not an id', 'recNEWMENTOR00001' );
ck( 'the first answer that is a record id is the record', WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() )['record'], 'recNEWMENTOR00001' );
fresh_client();
$GLOBALS['schema']                            = schema();
$GLOBALS['linked_error']['tblLANGUAGES00001'] = new WP_Error( 'airtable', 'Airtable is out of reach.' );
$stop                                         = WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() );
ck( 'a linked table that cannot be read stops the write, so no row is left without its links for good', array( $stop['ok'], $stop['error'], $GLOBALS['created'] ), array( false, 'Languages could not be read from Airtable, so nothing was written: Airtable is out of reach.', array() ) );
fresh_client();
$GLOBALS['schema_error'] = new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 403): NOT_AUTHORIZED', array( 'status' => 403, 'error_type' => 'NOT_AUTHORIZED' ) );
$bare                    = WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() );
ck( 'a token refused the schema (a 403) still makes the row, from the four cells alone, and says the answers were left out', array( $bare['ok'], array_keys( $GLOBALS['created'][0]['fields'] ), WPCPM_Mentor_Airtable_Row::left_out_sentence( $bare['left_out'] ) ), array( true, array( 'Full Name', 'WordPress profile', 'Email', 'Status' ), 'The answers were left out: the token cannot read the base\'s columns.' ) );
foreach ( array(
	'a timeout' => new WP_Error( 'http_request_failed', 'Operation timed out after 20000 milliseconds.' ),
	'a 500'     => new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 500): no further detail', array( 'status' => 500, 'error_type' => '' ) ),
	'a 422'     => new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 422): INVALID_REQUEST_UNKNOWN', array( 'status' => 422, 'error_type' => 'INVALID_REQUEST_UNKNOWN' ) ),
) as $what => $failure ) {
	fresh_client();
	$GLOBALS['schema_error'] = $failure;
	$later                   = WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() );
	ck( 'a schema that fails any other way stops the write as an unreadable linked table does, so the row is written whole later: ' . $what,
		array( $later['ok'], $later['how'], $later['error'], $later['left_out'], $GLOBALS['created'], $GLOBALS['updated'] ),
		array( false, '', 'The base\'s columns could not be read from Airtable, so nothing was written: ' . $failure->get_error_message(), array( '*' => array( 'why' => 'unread', 'values' => array( $failure->get_error_message() ) ) ), array(), array() ) );
}
fresh_client();
$GLOBALS['schema_error'] = new WP_Error( 'http_request_failed', 'Operation timed out after 20000 milliseconds.' );
$by_record               = WPCPM_Mentor_Airtable_Row::write( $ada, 'recRETURNING00001', 70, new WPCPM_Airtable() );
ck( 'while a row set back to Active reads no schema at all', array( $by_record['how'], $GLOBALS['schema_fetches'] ), array( 'reactivated', 0 ) );
fresh_client();
$GLOBALS['schema'] = array( 'tblOTHER000000001' => array( 'name' => 'Other', 'primary' => 'Name', 'columns' => array() ) );
$wrong_table       = WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() );
ck( 'a base without the Mentors table, a wrong table setting, stops the write the same way', array( $wrong_table['ok'], $wrong_table['error'], $GLOBALS['created'] ), array( false, 'The base\'s columns could not be read from Airtable, so nothing was written: The base has no table tblMENTORS0000001.', array() ) );
foreach ( array( '', '   ' ) as $blank ) {
	fresh_client();
	$GLOBALS['settings']['mentor_status'] = $blank;
	$no_status                            = array( WPCPM_Mentor_Airtable_Row::write( $ada, 'recRETURNING00001', 70, new WPCPM_Airtable() ), WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() ) );
	ck( 'a blank status setting writes nothing, before any search, update or create, and names the setting' . ( '' === $blank ? '' : ' (spaces)' ),
		array( $no_status[0]['ok'], $no_status[1]['ok'], $no_status[1]['error'], $GLOBALS['searched'], $GLOBALS['updated'], $GLOBALS['created'] ),
		array( false, false, 'The Mentor status to sync setting is blank, so nothing was written to Airtable. Fill it in, then press Write the Airtable row again.', array(), array(), array() ) );
}
ck( 'by_reference() finds an application by the reference it carries, and nothing for one it does not', array( WPCPM_Mentor_Application::by_reference( WPCPM_Mentor_Application::reference_of( $ada ) )->ID, WPCPM_Mentor_Application::by_reference( 'MAPP-2026-9999' ), WPCPM_Mentor_Application::by_reference( '' ) ), array( $ada->ID, null, null ) );

echo "\n=== The check row ===\n";

fresh_client();
$GLOBALS['schema'] = schema();
// Not `$posts` and `$users`: in this scope those are the stand-in's own tables, `$GLOBALS['posts']` and `$GLOBALS['users']`.
$posts_before      = count( $GLOBALS['posts'] );
$users_before      = count( $GLOBALS['users'] );
$check             = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
$expected          = WPCPM_Mentor_Airtable_Row::cells_for( WPCPM_Mentor_Airtable_Row::check_answers( 'owner+check@example.test' ), schema(), names() )['cells'];
$expected['Status'] = 'Inactive';
ck( 'the check row is the row an activation writes but for its Status: Inactive, a choice neither the mentors sync nor the checker reads, and the Active the activation writes is confirmed in the schema, not written',
	array( $GLOBALS['created'][0]['fields'], WPCPM_Mentor_Airtable_Row::CHECK_STATUS, $check['stops'] ),
	array( $expected, 'Inactive', array() ) );
ck( 'it is searched for first, read back and deleted, and says which columns the base kept',
	array( count( $GLOBALS['searched'] ), $check['record'], $check['deleted'], $GLOBALS['deleted'], in_array( 'Full Name', $check['kept'], true ), $check['missing'] ),
	array( 1, 'recNEWMENTOR00001', true, array( 'recNEWMENTOR00001' ), true, array() ) );
ck( 'it says what it wrote, and that nothing is wrong with any of it', array( $check['written'], $check['kept'], $check['error'], $check['read_error'], $check['delete_error'], $check['found_before'], $check['cleaned'] ), array( array_keys( $expected ), array_keys( $expected ), '', '', '', array(), array() ) );
ck( 'and nothing on the site changes: no application, no account, no kept names', array( count( $GLOBALS['posts'] ), count( $GLOBALS['users'] ), isset( $GLOBALS['transients'][ WPCPM_Mentor_Airtable_Row::LINKS_TRANSIENT ] ) ), array( $posts_before, $users_before, false ) );
ck( 'a contribution area the base does not hold prints as a note and does not stop the sitting', array( $check['notes'], $check['stops'] ), array( array( 'Contribution Area - Expertise: not in the base: Hosting.' ), array() ) );
ck( 'the synthetic mentor is named for what it is, with the address given, the first country and team the form offers, and no real person\'s answers',
	WPCPM_Mentor_Airtable_Row::check_answers( 'owner+check@example.test' ),
	array(
		'name'      => 'Mentor row check',
		'email'     => 'owner+check@example.test',
		'profile'   => 'mentor-row-check',
		'country'   => 'Poland',
		'languages' => array( 'English' ),
		'expertise' => array( 'Hosting' ),
		'hours'     => '2.5',
		'teacher'   => 'No',
		'anything'  => 'A check row written by the site and deleted at once.',
	) );
fresh_client();
$GLOBALS['schema']       = schema();
$GLOBALS['drop_on_read'] = array( 'Available hours per week' );
$short                   = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'a column sent and not read back is a stop', array( $short['missing'], $short['stops'] ), array( array( 'Available hours per week' ), array( 'Available hours per week: sent, and not read back.' ) ) );
fresh_client();
$GLOBALS['schema'] = schema( array( 'Teacher from a participating institution?' => array( 'type' => 'checkbox' ) ) );
$box               = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'a false checkbox, which Airtable leaves out of a read, counts as kept', array( $GLOBALS['created'][0]['fields']['Teacher from a participating institution?'], in_array( 'Teacher from a participating institution?', $box['kept'], true ), $box['stops'] ), array( false, true, array() ) );
fresh_client();
$GLOBALS['schema'] = schema( array( 'Available hours per week' => null, 'Languages' => array( 'type' => 'rating' ) ) );
$lacks             = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'a column the table lacks and a type the site does not write are stops, the rest of the row is written and deleted',
	array( $lacks['stops'], $lacks['deleted'], isset( $GLOBALS['created'][0] ) ),
	array( array( 'Languages: the column is a rating, which the site does not write.', 'Available hours per week: the Mentors table has no such column.' ), true, true ) );
fresh_client();
$GLOBALS['schema']   = schema();
$GLOBALS['by_email'] = array( 'recLEFTOVER000001' );
$left                = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'a row already holding the check address refuses the check, names the row to delete by hand, and writes nothing',
	array( $left['found_before'], $left['error'], $left['written'], $GLOBALS['created'] ),
	array( array( 'recLEFTOVER000001' ), 'Rows with the check address are already in the Mentors table: recLEFTOVER000001. Delete them by hand, then check again.', array(), array() ) );
fresh_client();
$GLOBALS['refuse_search'] = new WP_Error( 'airtable', 'Airtable is out of reach.' );
$cannot                   = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'a search before the create that is refused stops the check with nothing written', array( $cannot['error'], $GLOBALS['created'] ), array( 'Airtable is out of reach.', array() ) );
fresh_client();
$GLOBALS['refuse_create']  = new WP_Error( 'http_request_failed', 'Operation timed out after 20000 milliseconds.' );
$GLOBALS['by_email_after'] = array( 'recLOSTCHECK00001' );
$lost                      = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'a create whose answer was lost is searched for again, and the row Airtable stored is deleted',
	array( $lost['cleaned'], $GLOBALS['deleted'], $lost['error'] ),
	array( array( 'recLOSTCHECK00001' ), array( 'recLOSTCHECK00001' ), 'Operation timed out after 20000 milliseconds. The search after it found recLOSTCHECK00001 and deleted it.' ) );
fresh_client();
$GLOBALS['refuse_create']  = new WP_Error( 'http_request_failed', 'Operation timed out after 20000 milliseconds.' );
$GLOBALS['by_email_after'] = array( 'recLOSTCHECK00001', 'recLOSTCHECK00002' );
$lost_two                  = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'and every row it finds is deleted', array( $lost_two['cleaned'], $lost_two['error'] ), array( array( 'recLOSTCHECK00001', 'recLOSTCHECK00002' ), 'Operation timed out after 20000 milliseconds. The search after it found recLOSTCHECK00001, recLOSTCHECK00002 and deleted them.' ) );
fresh_client();
$GLOBALS['refuse_create']  = new WP_Error( 'http_request_failed', 'Operation timed out after 20000 milliseconds.' );
$GLOBALS['by_email_after'] = array(
	array( 'id' => 'recREALMENTOR0001', 'fields' => array( 'Email' => 'owner+check@example.test', 'Full Name' => 'Real Mentor', 'Status' => 'Active' ) ),
	'recLOSTCHECK00001',
	array( 'id' => 'recSAMENAME000001', 'fields' => array( 'Email' => 'owner+check@example.test', 'Full Name' => 'Mentor row check', 'Status' => 'Active' ) ),
	array( 'id' => 'recSAMESTATUS0001', 'fields' => array( 'Email' => 'owner+check@example.test', 'Full Name' => 'Another Mentor', 'Status' => 'Inactive' ) ),
);
$mixed = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'the cleanup after a create error deletes only a row with the check row\'s name and status, read in that search, and names every other row holding the address without touching it',
	array( $mixed['cleaned'], $GLOBALS['deleted'], $mixed['error'], $GLOBALS['searched'][1]['args']['fields'] ),
	array( array( 'recLOSTCHECK00001' ), array( 'recLOSTCHECK00001' ), 'Operation timed out after 20000 milliseconds. The search after it found recLOSTCHECK00001 and deleted it. The row recREALMENTOR0001 holds the check address but is not the check row, so it was not deleted: look at it in the Mentors table. The row recSAMENAME000001 holds the check address but is not the check row, so it was not deleted: look at it in the Mentors table. The row recSAMESTATUS0001 holds the check address but is not the check row, so it was not deleted: look at it in the Mentors table.', array( 'Email', 'Full Name', 'Status' ) ) );
fresh_client();
$GLOBALS['refuse_create']  = new WP_Error( 'airtable', 'Airtable refused the write.' );
$GLOBALS['by_email_after'] = array( array( 'id' => 'recREALMENTOR0001', 'fields' => array( 'Email' => 'owner+check@example.test', 'Full Name' => 'Real Mentor', 'Status' => 'Active' ) ) );
$foreign                   = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'a row that is not the check row, alone, is named and never deleted, and the output does not say nothing was stored',
	array( $foreign['cleaned'], $GLOBALS['deleted'], $foreign['error'] ),
	array( array(), array(), 'Airtable refused the write. The row recREALMENTOR0001 holds the check address but is not the check row, so it was not deleted: look at it in the Mentors table.' ) );
fresh_client();
$GLOBALS['refuse_create'] = new WP_Error( 'http_request_failed', 'Operation timed out after 20000 milliseconds.' );
$maybe                    = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'after a transport error a search that finds nothing does not say nothing was stored: Airtable may still store the row, and the answer says the create failed in transport',
	array( $maybe['cleaned'], $GLOBALS['deleted'], $maybe['error'], count( $GLOBALS['searched'] ), $maybe['transport'] ?? null ),
	array( array(), array(), 'Operation timed out after 20000 milliseconds. The search found no row yet, but Airtable may still store it: look for owner+check@example.test in the Mentors table in a minute and delete it by hand.', 2, true ) );
fresh_client();
$GLOBALS['refuse_create']  = new WP_Error( 'http_request_failed', 'Operation timed out after 20000 milliseconds.' );
$GLOBALS['by_email_after'] = array( array( 'id' => 'recREALMENTOR0001', 'fields' => array( 'Email' => 'owner+check@example.test', 'Full Name' => 'Real Mentor', 'Status' => 'Active' ) ) );
$maybe_foreign             = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'and neither does a search that finds only a row that is not the check row',
	$maybe_foreign['error'],
	'Operation timed out after 20000 milliseconds. The row recREALMENTOR0001 holds the check address but is not the check row, so it was not deleted: look at it in the Mentors table. The search found no row yet, but Airtable may still store it: look for owner+check@example.test in the Mentors table in a minute and delete it by hand.' );
fresh_client();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$refused                  = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'a create error with nothing found deletes nothing and says so, only because a search succeeded and found nothing, and a refusal is no transport error', array( $refused['cleaned'], $GLOBALS['deleted'], $refused['error'], $refused['record'], count( $GLOBALS['searched'] ), $refused['transport'] ?? null ), array( array(), array(), 'Airtable refused the write. The search after it found nothing, so nothing was stored and nothing was deleted.', '', 2, false ) );
fresh_client();
$GLOBALS['refuse_create']       = new WP_Error( 'http_request_failed', 'Operation timed out after 20000 milliseconds.' );
$GLOBALS['refuse_search_after'] = new WP_Error( 'airtable', 'Airtable is out of reach.' );
$blind_search                   = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'when the search after a create error fails too, the row may have been stored: it names the address to look for, and never says nothing was stored',
	array( $blind_search['cleaned'], $GLOBALS['deleted'], $blind_search['error'] ),
	array( array(), array(), 'Operation timed out after 20000 milliseconds. Could not search the Mentors table after the create failed, so a row may have been stored: look for owner+check@example.test in the Mentors table and delete it by hand.' ) );
fresh_client();
$GLOBALS['refuse_create']  = new WP_Error( 'http_request_failed', 'Operation timed out after 20000 milliseconds.' );
$GLOBALS['by_email_after'] = array( 'recLOSTCHECK00001' );
$GLOBALS['refuse_delete']  = new WP_Error( 'airtable', 'Airtable refused the delete.' );
$cleanup_refused           = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'and when the delete of a row it found is refused, it names the row to delete by hand and deletes nothing',
	array( $cleanup_refused['cleaned'], $GLOBALS['deleted'], $cleanup_refused['error'] ),
	array( array(), array(), 'Operation timed out after 20000 milliseconds. The row recLOSTCHECK00001 could not be deleted: delete it by hand in the Mentors table.' ) );
fresh_client();
$GLOBALS['created_ids']    = array();
$GLOBALS['by_email_after'] = array( 'recLOSTCHECK00001' );
$no_id                     = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'an answer with no record id may hide a stored row too: it is searched for and deleted', array( $no_id['record'], $no_id['cleaned'], $no_id['error'], $no_id['transport'] ?? null ), array( '', array( 'recLOSTCHECK00001' ), 'Airtable did not answer with a record ID. The search after it found recLOSTCHECK00001 and deleted it.', false ) );
fresh_client();
$GLOBALS['refuse_read'] = new WP_Error( 'airtable', 'Airtable is out of reach.' );
$unread_back            = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'the delete runs even when the read-back fails', array( $unread_back['read_error'], $unread_back['deleted'], $GLOBALS['deleted'] ), array( 'Airtable is out of reach.', true, array( 'recNEWMENTOR00001' ) ) );
ck( 'and a read that failed says nothing of what the base kept', array( $unread_back['kept'], $unread_back['missing'], $unread_back['stops'] ), array( array(), array(), array() ) );
fresh_client();
$GLOBALS['refuse_delete'] = new WP_Error( 'airtable', 'Airtable refused the delete.' );
$stuck                    = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'a delete Airtable refuses is said, with the row to delete by hand', array( $stuck['deleted'], $stuck['delete_error'], $stuck['record'] ), array( false, 'Airtable refused the delete.', 'recNEWMENTOR00001' ) );
fresh_client();
$GLOBALS['schema'] = schema( array( 'Status' => array( 'type' => 'singleSelect', 'options' => choices( array( 'Interested', 'Inactive' ) ) ) ) );
ck( 'an activation status the column does not offer stops the check with nothing written', array( WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() )['error'], $GLOBALS['created'] ), array( 'The activation\'s status, Active, is not a choice of the Status column.', array() ) );
fresh_client();
$GLOBALS['schema'] = schema( array( 'Status' => array( 'type' => 'singleSelect', 'options' => choices( array( 'Interested', 'Active' ) ) ) ) );
ck( 'a check status the column does not offer stops it too', array( WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() )['error'], $GLOBALS['created'] ), array( 'The check row\'s status, Inactive, is one the mentors sync or the checker reads, or no choice of the Status column.', array() ) );
fresh_client();
$GLOBALS['settings']['checker_source_status'] = 'Inactive';
ck( 'and so does a check status the checker reads', array( WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() )['error'], $GLOBALS['created'] ), array( 'The check row\'s status, Inactive, is one the mentors sync or the checker reads, or no choice of the Status column.', array() ) );
fresh_client();
$GLOBALS['settings']['checker_target_status'] = 'inactive ';
ck( 'compared without regard to case or the spaces around it', array( WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() )['error'], $GLOBALS['created'] ), array( 'The check row\'s status, Inactive, is one the mentors sync or the checker reads, or no choice of the Status column.', array() ) );
fresh_client();
$GLOBALS['settings']['mentor_status'] = 'Inactive';
ck( 'and so does the status the mentors sync reads', array( WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() )['error'], $GLOBALS['created'] ), array( 'The check row\'s status, Inactive, is one the mentors sync or the checker reads, or no choice of the Status column.', array() ) );
foreach ( array(
	'a failure'           => new WP_Error( 'airtable', 'The token cannot read the schema.' ),
	'a 403, the scope'    => new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 403): NOT_AUTHORIZED', array( 'status' => 403 ) ),
	'a timeout'           => new WP_Error( 'http_request_failed', 'Operation timed out after 20000 milliseconds.' ),
) as $what => $failure ) {
	fresh_client();
	$GLOBALS['schema_error'] = $failure;
	$no_schema               = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
	ck( 'without the base\'s columns the check has nothing to prove: it prints the read\'s own error and writes nothing (' . $what . ')', array( $no_schema['error'], $no_schema['written'], $GLOBALS['created'], $GLOBALS['searched'] ), array( $failure->get_error_message(), array(), array(), array() ) );
}
fresh_client();
$GLOBALS['schema'] = array( 'tblOTHER000000001' => array( 'name' => 'Other', 'primary' => 'Name', 'columns' => array() ) );
$no_mentors        = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'and neither does a schema without the Mentors table, which says so in the client\'s words', array( $no_mentors['error'], $GLOBALS['created'] ), array( 'The base has no table tblMENTORS0000001.', array() ) );
fresh_client();
$GLOBALS['schema_stale'] = schema( array( 'Available hours per week' => null ) );
$GLOBALS['schema']       = schema();
$changed                 = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'the check reads the schema fresh: a column added to the base since the client kept its copy is tried', array( in_array( 'Available hours per week', $changed['written'], true ), $GLOBALS['created'][0]['fields']['Available hours per week'], $GLOBALS['schema_fetches'], $changed['stops'] ), array( true, 2.5, 1, array() ) );
ck( 'while a preview and a write read the copy the client keeps', array_key_exists( 'Available hours per week', WPCPM_Mentor_Airtable_Row::preview( $ada, new WPCPM_Airtable() )['cells'] ), false );
fresh_client();
$GLOBALS['schema_stale'] = schema();
$GLOBALS['schema']       = schema( array( 'Available hours per week' => null ) );
$dropped                 = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'and a column dropped since is a stop, not a column the check still sends', array( in_array( 'Available hours per week', $dropped['written'], true ), $dropped['stops'] ), array( false, array( 'Available hours per week: the Mentors table has no such column.' ) ) );
fresh_client();
$GLOBALS['linked_error']['tblCOUNTRIES00001'] = new WP_Error( 'airtable', 'Airtable is out of reach.' );
ck( 'a linked table that cannot be read stops the check as it stops a write, with nothing written', array( WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() )['error'], $GLOBALS['created'] ), array( 'Country could not be read from Airtable, so nothing was written: Airtable is out of reach.', array() ) );
fresh_client();
ck( 'an address that is not one writes nothing', array( WPCPM_Mentor_Airtable_Row::check_row( 'not-an-address', new WPCPM_Airtable() )['error'], $GLOBALS['created'] ), array( 'The check needs an email address.', array() ) );

echo "\n=== Nothing else ===\n";

restore_error_handler();

ck( 'nothing was asked of the stand-ins that they do not model', $GLOBALS['unmodeled'], array() );
ck( 'and the run raised no PHP warning, notice or deprecation', $php_warnings, array() );

$source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-airtable-row.php' );
ck( 'the class asks Airtable to coerce nothing, and has one place that updates a row, for the Status cell alone',
	array( false === strpos( $source, "'typecast'" ), substr_count( $source, '->update_records(' ) ),
	array( true, 1 ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
