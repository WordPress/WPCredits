<?php
/**
 * Institutions module - the details of an institution a school may correct: what they are, how an
 * answer is cleaned, what a form changes, how a change reads, and the one way it is written.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The eleven things the program holds about a school that the school may correct itself (Institutions
 * release 2), in two groups, and everything about them that is not a screen.
 *
 * **Two groups, by who checks them.**
 *
 * - `GROUP_NOW` saves at once: the contact person, their role, who signs the agreement and their
 *   title, the languages the students study in, and the institution's page about WordPress Credits.
 *   Nothing here is a fact the program has to be sure of.
 * - `GROUP_CHECKED` waits for an Administrator: the name, the website, the city, the country and the
 *   contact's email. The program reaches the school by the email, files it by the country and shows
 *   the name on a public page, so a school asks and an Administrator decides. An Administrator's own
 *   correction needs no second pair of eyes and is written at once, through the same `write()`.
 *
 * **Where each lives.** Six are columns of the Institutions table, written there and in the pipeline
 * index; the contact person is one, which is why it saves at once yet reaches Airtable. The other five
 * are the site's: `WPCPM_Institution_Record`, which has no column in the base to keep them in. A
 * column is named here by the key of `WPCPM_Institutions_Sync::fields()` and never by its spelling,
 * so the sync, the approval and this class cannot disagree about what a column is called.
 *
 * **The application's rules, not a second copy.** An answer is a question the application form
 * asked (`WPCPM_Institution_Application::fields()`), and is cleaned and refused by that form's own
 * `clean()`, `add_required()` and `problem_text()`: the same sentence for the same mistake, and a
 * rule changed there is changed here. The labels are this card's own, and are what the read view, the
 * emails and the Administrator's table all print.
 *
 * **A change is a difference.** `changes()` compares what a form holds with what the program has on
 * record, and an unchanged value, a value with space around it, an email in another case and the
 * same languages in another order are no difference. A request or a save that has no change in it
 * sends and writes nothing.
 *
 * **One way to write.** `write()` is the only code that changes these details, so every correction is
 * a cleaned one and every correction is audited. It cleans each new value again, by the same
 * application rules a typed answer meets (the scheme rule and the 200 characters of a web address
 * among them), so a request stored and replayed later by an approval is refused the way a typed one
 * is, whoever built it. It writes Airtable first and the site after: a base that refuses leaves the
 * site as it was, and the program never shows a school a detail Airtable does not hold. All static,
 * in the shape of `WPCPM_Institution_Record`.
 */
class WPCPM_Institution_Details {

	/** The details that save at once. */
	const GROUP_NOW = 'now';

	/** The details an Administrator checks first. */
	const GROUP_CHECKED = 'checked';

	/** The kind of the history row a write leaves. */
	const LOG_SAVED = 'details_saved';

	/**
	 * The details, in the order the card draws them.
	 *
	 * The key is the one the index row and the institution's record use; `question` is the application
	 * form's question for the same answer, which gives it its type and its rules.
	 *
	 * @return array<string, array> Key => `group`, `label`, `source`, `column` and `question`.
	 */
	private static function table() {
		return array(
			'contact_person'    => array(
				'group'    => self::GROUP_NOW,
				'label'    => __( 'Contact person', 'wpcredits-program-manager' ),
				'source'   => 'airtable',
				'column'   => 'contact_person',
				'question' => 'Contact Person',
			),
			'contact_role'      => array(
				'group'    => self::GROUP_NOW,
				'label'    => __( 'Their role', 'wpcredits-program-manager' ),
				'source'   => 'record',
				'column'   => '',
				'question' => 'contact_role',
			),
			'signer_name'       => array(
				'group'    => self::GROUP_NOW,
				'label'    => __( 'Signs the agreement', 'wpcredits-program-manager' ),
				'source'   => 'record',
				'column'   => '',
				'question' => 'signer_name',
			),
			'signer_title'      => array(
				'group'    => self::GROUP_NOW,
				'label'    => __( 'Their title', 'wpcredits-program-manager' ),
				'source'   => 'record',
				'column'   => '',
				'question' => 'signer_title',
			),
			'student_languages' => array(
				'group'    => self::GROUP_NOW,
				'label'    => __( 'Students study in', 'wpcredits-program-manager' ),
				'source'   => 'record',
				'column'   => '',
				'question' => 'student_languages',
			),
			'credits_page'      => array(
				'group'    => self::GROUP_NOW,
				'label'    => __( 'Page about WordPress Credits', 'wpcredits-program-manager' ),
				'source'   => 'record',
				'column'   => '',
				'question' => 'credits_page',
			),
			'name'              => array(
				'group'    => self::GROUP_CHECKED,
				'label'    => __( 'Name', 'wpcredits-program-manager' ),
				'source'   => 'airtable',
				'column'   => 'name',
				'question' => 'Name',
			),
			'website'           => array(
				'group'    => self::GROUP_CHECKED,
				'label'    => __( 'Website', 'wpcredits-program-manager' ),
				'source'   => 'airtable',
				'column'   => 'website',
				'question' => 'Website',
			),
			'city'              => array(
				'group'    => self::GROUP_CHECKED,
				'label'    => __( 'City', 'wpcredits-program-manager' ),
				'source'   => 'airtable',
				'column'   => 'city',
				'question' => 'City',
			),
			'country'           => array(
				'group'    => self::GROUP_CHECKED,
				'label'    => __( 'Country', 'wpcredits-program-manager' ),
				'source'   => 'airtable',
				'column'   => 'country',
				'question' => 'Country',
			),
			'contact_email'     => array(
				'group'    => self::GROUP_CHECKED,
				'label'    => __( 'Contact\'s email', 'wpcredits-program-manager' ),
				'source'   => 'airtable',
				'column'   => 'contact_email',
				'question' => 'Contact Email',
			),
		);
	}

	/**
	 * The details a school may correct: which group, what to call them, where they live, and what
	 * kind of answer each is.
	 *
	 * `source` is `airtable` for a column of the Institutions table, written to Airtable and to the
	 * pipeline index, and `record` for an answer only the site keeps. `column` names an Airtable
	 * detail by its key in `WPCPM_Institutions_Sync::fields()` and is empty for a record one. `type`
	 * is the application form's own for the same question: `text`, `url`, `email`, `country` (a
	 * Countries record ID) or `choices` (a list).
	 *
	 * @return array<string, array{group:string,label:string,source:string,column:string,type:string}>
	 */
	public static function fields() {
		$questions = WPCPM_Institution_Application::fields();
		$out       = array();

		foreach ( self::table() as $key => $row ) {
			$out[ $key ] = array(
				'group'  => $row['group'],
				'label'  => $row['label'],
				'source' => $row['source'],
				'column' => $row['column'],
				'type'   => isset( $questions[ $row['question'] ]['type'] ) ? $questions[ $row['question'] ]['type'] : 'text',
			);
		}

		return $out;
	}

	/**
	 * The application question one detail is asked as: the key of `WPCPM_Institution_Application::fields()`
	 * whose label, help, limit and choices the card's form gives that detail.
	 *
	 * Beside `fields()` and not in it, whose five keys are the shape every caller of the table reads.
	 * The labels `fields()` carries are this card's own, for the read view, the emails and the
	 * Administrator's table; the form a school types into asks the question the application asked.
	 *
	 * @param string $key A key of `fields()`.
	 * @return string The question's key, or an empty string for a key the table does not have.
	 */
	public static function question( $key ) {
		$table = self::table();

		return is_string( $key ) && isset( $table[ $key ] ) ? $table[ $key ]['question'] : '';
	}

	/**
	 * What the program has on record for one institution, key by key.
	 *
	 * The Airtable details come from the pipeline index row and the rest from the institution's record
	 * on the site, and a detail that neither holds is empty: an institution the index has never read,
	 * or approved before the record existed, has the empty value, never a missing key. The country is
	 * its Countries record ID, the languages a list, and every other value text with no space around it
	 * (the index keeps a name as Airtable holds it, trailing space and all, and a screen trims).
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @return array Key => value, in the order of `fields()`.
	 */
	public static function current( $record_id ) {
		$row  = WPCPM_Institutions_Index::row( $record_id );
		$kept = WPCPM_Institution_Record::get( $record_id );
		$out  = array();

		foreach ( self::fields() as $key => $spec ) {
			if ( 'airtable' === $spec['source'] ) {
				$out[ $key ] = self::tidy( $spec['type'], null !== $row && isset( $row[ $spec['column'] ] ) ? $row[ $spec['column'] ] : '' );

				continue;
			}

			$out[ $key ] = self::tidy( $spec['type'], isset( $kept[ $key ] ) ? $kept[ $key ] : null );
		}

		return $out;
	}

	/**
	 * Drop this request's copies of what the program has on record for one institution: the pipeline
	 * index and the institution's record, so the next `current()` reads the tables.
	 *
	 * For a caller that compares again under a lock what it compared before taking it, as the details
	 * card's save at once does: the copies this request read before the lock do not hold what another
	 * request wrote while it waited, and a change it already made would be made a second time.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 */
	public static function forget_cached( $record_id ) {
		WPCPM_Institutions_Index::forget_cached();
		WPCPM_Institution_Record::forget_cached( $record_id );
	}

	/**
	 * Clean what one group's form posted, by the application form's rules.
	 *
	 * Each answer goes through `WPCPM_Institution_Application::clean()` for the question it is, and
	 * the requiredness the form asks for (a role, the languages, the five checked details, and the
	 * signer's name and title as a pair) through its `add_required()`, so a refusal is the form's
	 * sentence. An answer left out of `$raw` is an empty one, as an unticked box and an empty text
	 * field are on a form that posts every field, and a key of the other group is not read at all: a
	 * form for one group cannot change the other's.
	 *
	 * @param string $group `GROUP_NOW` or `GROUP_CHECKED`.
	 * @param array  $raw   What was posted, keyed by the keys of `fields()`.
	 * @return array{values:array,problems:array} Both keyed by key; `values` holds the cleaned answer
	 *                                           of every key that was accepted, `problems` the sentence
	 *                                           for every key that was not. Both are empty for a group
	 *                                           nobody has.
	 */
	public static function clean( $group, array $raw ) {
		$questions = WPCPM_Institution_Application::fields();
		$asked     = array();
		$values    = array();
		$problems  = array();

		foreach ( self::table() as $key => $row ) {
			if ( $group !== $row['group'] ) {
				continue;
			}

			$asked[ $key ] = $row['question'];
			$type          = $questions[ $row['question'] ]['type'];
			$clean         = WPCPM_Institution_Application::clean( $row['question'], isset( $raw[ $key ] ) ? $raw[ $key ] : ( 'choices' === $type ? array() : '' ) );

			if ( empty( $clean['ok'] ) ) {
				$problems[ $row['question'] ] = $clean['problem'];

				continue;
			}

			$values[ $row['question'] ] = $clean['value'];
		}

		$problems = WPCPM_Institution_Application::add_required( $values, $problems, array_values( $asked ) );
		$out      = array(
			'values'   => array(),
			'problems' => array(),
		);

		foreach ( $asked as $key => $question ) {
			if ( isset( $problems[ $question ] ) ) {
				$out['problems'][ $key ] = WPCPM_Institution_Application::problem_text( $problems[ $question ], $questions[ $question ] );

				continue;
			}

			$out['values'][ $key ] = $values[ $question ];
		}

		return $out;
	}

	/**
	 * What a form changes: each detail whose cleaned value differs from the one on record.
	 *
	 * Compared as a person would: space around a value is nothing, an email in another case is the
	 * same email (the index keeps it lowercased), and the same languages in another order are the same
	 * languages. A key the table does not have is not a change.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @param array  $values    Cleaned values, keyed by key (`clean()`'s `values`).
	 * @return array<string, array{from:mixed,to:mixed}> Key => what is on record and the new value, in the
	 *                                                  order of `fields()`; empty when nothing differs.
	 */
	public static function changes( $record_id, array $values ) {
		$current = self::current( $record_id );
		$out     = array();

		foreach ( self::fields() as $key => $spec ) {
			if ( ! array_key_exists( $key, $values ) ) {
				continue;
			}

			$to = self::tidy( $spec['type'], $values[ $key ] );

			if ( self::same( $spec['type'], $current[ $key ], $to ) ) {
				continue;
			}

			$out[ $key ] = array(
				'from' => $current[ $key ],
				'to'   => $to,
			);
		}

		return $out;
	}

	/**
	 * Make a correction: Airtable first, then the index, then the record, then the history.
	 *
	 * **In this order so a refusal costs nothing.** Airtable is the one write that can be turned
	 * down for a reason the site cannot see (a column it does not take, a value it will not hold, a
	 * token that lapsed), so it is made first, as one PATCH holding exactly the Airtable details that
	 * changed, the country as a list of one record ID. If it fails, with an error or with nothing
	 * written, the function returns that and the site is as it was: no index row, no record, no history,
	 * and the caller says so and keeps what was typed. Only a base that took the change lets the site
	 * follow, the index at once so a screen shows it without waiting for the next sync, then the
	 * record for the answers the base has no column for, then one history row naming the details that
	 * changed. A change of the record's answers alone sends nothing to Airtable.
	 *
	 * **The column is the sync's.** The name is written under the column the sync reads, which the
	 * `institutions_name_field` setting may rename.
	 *
	 * **Names, never values.** The history row holds the details' keys and labels and none of what
	 * they held or hold: the log is read by every Administrator, and a contact person and an email
	 * are a person's.
	 *
	 * **Refused before the first write.** The institution must be one the program records hold: a
	 * correction of an Airtable detail for an institution the index has not read is refused, since
	 * the index could not follow it and the next sync would be the first the site heard of it. And a
	 * correction of the record's answers alone, which sends nothing to Airtable, is refused when the
	 * site could not keep them, rather than reported as saved and written to the history.
	 *
	 * The caller has decided who may do this (`WPCPM_Institution_Policy`) and, for a school's request,
	 * that an Administrator has approved it; this checks that what it is handed is well-formed, and
	 * cleans each new value again, before it writes anything.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @param array  $changes   Key => `array( 'from' => ..., 'to' => ... )`, as `changes()` returns.
	 * @param int    $actor     The user who made it, for the history.
	 * @param string $ground    The ground the policy allowed it on, one of
	 *                          `WPCPM_Institution_Audit::grounds()`.
	 * @return true|WP_Error True when the change is made, and for a change with nothing in it; the
	 *                       error when it is refused as malformed, for an institution the index does
	 *                       not hold, or when the site could not keep a record-only correction, or
	 *                       Airtable's own when the base turned it down.
	 */
	public static function write( $record_id, array $changes, $actor, $ground ) {
		if ( ! WPCPM_Airtable::is_record_id( $record_id ) ) {
			return new WP_Error( 'wpcpm_details_record', __( 'That institution could not be found, so nothing was changed.', 'wpcredits-program-manager' ) );
		}

		$record_id = trim( (string) $record_id );

		if ( ! in_array( $ground, WPCPM_Institution_Audit::grounds(), true ) ) {
			return new WP_Error( 'wpcpm_details_ground', __( 'The change needs the ground it was allowed on, so nothing was changed.', 'wpcredits-program-manager' ) );
		}

		if ( array() !== array_diff_key( $changes, self::fields() ) ) {
			return new WP_Error( 'wpcpm_details_change', __( 'That is not a detail of an institution, so nothing was changed.', 'wpcredits-program-manager' ) );
		}

		$prepared = self::prepare( $changes );

		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		if ( empty( $prepared['names'] ) ) {
			return true;
		}

		// The index follows an Airtable change at once, and can only follow a row it holds: an
		// institution it has never read would be written to the base and be on the site in no
		// screen until the next sync, with the history saying it was saved.
		if ( ! empty( $prepared['cells'] ) && ! WPCPM_Institutions_Index::has( $record_id ) ) {
			return new WP_Error( 'wpcpm_details_index', __( 'This institution is not in the program records yet, so nothing was saved.', 'wpcredits-program-manager' ) );
		}

		$settings = WPCPM_Settings::get();

		if ( ! empty( $prepared['cells'] ) ) {
			$columns = WPCPM_Institutions_Sync::fields();

			if ( ! empty( $settings['institutions_name_field'] ) ) {
				$columns['name'] = (string) $settings['institutions_name_field'];
			}

			$cells = array();

			foreach ( $prepared['cells'] as $column => $value ) {
				$cells[ $columns[ $column ] ] = $value;
			}

			$airtable = new WPCPM_Airtable( $settings );
			$written  = $airtable->update_records(
				isset( $settings['institutions_table'] ) ? (string) $settings['institutions_table'] : '',
				array(
					array(
						'id'     => $record_id,
						'fields' => $cells,
					),
				)
			);

			if ( is_wp_error( $written ) ) {
				return $written;
			}

			// An empty result is a refusal too: `update_records()` drops a record it cannot send and
			// answers with the ones it did, so "nothing was updated" must not read as saved.
			if ( empty( $written ) ) {
				return new WP_Error( 'wpcpm_details_airtable', __( 'Airtable did not take the change, so nothing was changed here either.', 'wpcredits-program-manager' ) );
			}

			WPCPM_Institutions_Index::patch( $record_id, $prepared['row'] );
		}

		if ( ! empty( $prepared['answers'] ) ) {
			$kept = WPCPM_Institution_Record::set( $record_id, $prepared['answers'] );

			// Alone, the answers are the whole correction, and a site that could not keep them has
			// saved nothing. After an Airtable change they cannot be undone with it, so the change
			// stands and the record is the next correction's to fill.
			if ( ! $kept && empty( $prepared['cells'] ) ) {
				return new WP_Error( 'wpcpm_details_record', __( 'The details could not be kept on the site, so nothing was saved. Please try again in a while.', 'wpcredits-program-manager' ) );
			}
		}

		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_SAVED,
				'institution' => $record_id,
				'subject'     => $record_id,
				'actor'       => (int) $actor,
				'ground'      => $ground,
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'     => sprintf(
					/* translators: %s: the names of the details that were corrected, comma-separated, such as "Website, City". */
					__( 'Details were corrected: %s.', 'wpcredits-program-manager' ),
					implode( ', ', $prepared['labels'] )
				),
				'data'        => array( 'fields' => $prepared['names'] ),
			)
		);

		return true;
	}

	/**
	 * One line to each change, for an email.
	 *
	 * A saved change reads "<label>: <from>, now <to>", and an asked one, a school's request not yet
	 * decided, "<label>: <from>, asked to be <to>". An empty value reads "not given", a list its
	 * values comma-separated, and a country its name. The lines are in the order of `fields()`, one
	 * to a line with the line ending the emails use ("\r\n", as the agreement's list of waiting
	 * agreements is joined), as plain text: the caller that puts them on a page escapes them.
	 *
	 * @param array $changes Key => `array( 'from' => ..., 'to' => ... )`, as `changes()` returns.
	 * @param bool  $asked   Whether the change is one a school asked for rather than one that was made.
	 * @return string The lines, or an empty string for no change.
	 */
	public static function lines( array $changes, $asked = false ) {
		$out = array();

		foreach ( self::fields() as $key => $spec ) {
			if ( ! isset( $changes[ $key ] ) || ! is_array( $changes[ $key ] ) ) {
				continue;
			}

			$from = self::shown( $spec['type'], isset( $changes[ $key ]['from'] ) ? $changes[ $key ]['from'] : '' );
			$to   = self::shown( $spec['type'], isset( $changes[ $key ]['to'] ) ? $changes[ $key ]['to'] : '' );

			$out[] = $asked
				? sprintf(
					/* translators: 1: the name of a detail, 2: what the program has on record, 3: what the institution asked for. */
					__( '%1$s: %2$s, asked to be %3$s', 'wpcredits-program-manager' ),
					$spec['label'],
					$from,
					$to
				)
				: sprintf(
					/* translators: 1: the name of a detail, 2: what the program had on record, 3: what it reads now. */
					__( '%1$s: %2$s, now %3$s', 'wpcredits-program-manager' ),
					$spec['label'],
					$from,
					$to
				);
		}

		return implode( "\r\n", $out );
	}

	/**
	 * One detail's value as a table or a sentence says it: a country by its name, a list by its
	 * values, and nothing as "not given".
	 *
	 * The Administrator's table prints what the program has on record and what a school asked for one
	 * detail at a time, and `lines()` words the same values in an email, so the two read a value
	 * alike. Plain text: the caller that puts it on a page escapes it.
	 *
	 * @param string $key   A key of `fields()`.
	 * @param mixed  $value The value.
	 * @return string The value as it reads, or an empty string for a key the table does not have.
	 */
	public static function show( $key, $value ) {
		$fields = self::fields();

		if ( ! isset( $fields[ $key ] ) ) {
			return '';
		}

		return self::shown( $fields[ $key ]['type'], $value );
	}

	/**
	 * Check a set of changes and sort it into what each store is to be given.
	 *
	 * Nothing is written here; this is the part of `write()` that can refuse, kept apart so a refusal
	 * is complete before the first write. A new value must be text (a list, for the languages), and
	 * is then cleaned again by the application's rules for the same question (`clean()`), the one a
	 * typed answer meets: a web address naming a scheme of its own or longer than 200 characters, an
	 * email that is not one and a country the Countries map does not offer are refused with the
	 * form's sentence, a record ID of another table never reaching the base as this institution's
	 * country. Requiredness is the form's to ask and is not asked again.
	 *
	 * @param array $changes Key => `array( 'from' => ..., 'to' => ... )`, every key one of `fields()`.
	 * @return array|WP_Error `cells` (the sync's column key => the Airtable cell), `row` (the index
	 *                        row's keys), `answers` (the record's keys), and `names` and `labels` of the
	 *                        details, in the order of `fields()`; or the error.
	 */
	private static function prepare( array $changes ) {
		$out = array(
			'cells'   => array(),
			'row'     => array(),
			'answers' => array(),
			'names'   => array(),
			'labels'  => array(),
		);

		$table     = self::table();
		$questions = WPCPM_Institution_Application::fields();

		foreach ( self::fields() as $key => $spec ) {
			if ( ! isset( $changes[ $key ] ) ) {
				continue;
			}

			if ( ! is_array( $changes[ $key ] ) || ! array_key_exists( 'to', $changes[ $key ] ) ) {
				return new WP_Error( 'wpcpm_details_change', __( 'A change needs the value it is to become, so nothing was changed.', 'wpcredits-program-manager' ) );
			}

			$to = $changes[ $key ]['to'];

			if ( 'choices' === $spec['type'] ? ! is_array( $to ) : ! is_scalar( $to ) ) {
				return new WP_Error( 'wpcpm_details_value', __( 'A detail was not text, so nothing was changed.', 'wpcredits-program-manager' ) );
			}

			$clean = WPCPM_Institution_Application::clean( $table[ $key ]['question'], self::tidy( $spec['type'], $to ) );

			if ( empty( $clean['ok'] ) ) {
				return new WP_Error(
					'wpcpm_details_value',
					sprintf(
						/* translators: 1: the name of a detail, 2: the sentence saying what is wrong with it. */
						__( '%1$s: %2$s Nothing was changed.', 'wpcredits-program-manager' ),
						$spec['label'],
						WPCPM_Institution_Application::problem_text( $clean['problem'], $questions[ $table[ $key ]['question'] ] )
					)
				);
			}

			$to = self::tidy( $spec['type'], $clean['value'] );

			$out['names'][]  = $key;
			$out['labels'][] = $spec['label'];

			if ( 'record' === $spec['source'] ) {
				$out['answers'][ $key ] = $to;

				continue;
			}

			// A country is a linked record, written as a list of one record ID, and cleared with an
			// empty list; the index keeps the name beside the ID, as the sync does.
			if ( 'country' === $spec['type'] ) {
				$out['cells'][ $spec['column'] ] = '' === $to ? array() : array( $to );
				$out['row']['country']           = $to;
				$out['row']['country_name']      = '' === $to ? '' : WPCPM_Countries::name_of( $to );

				continue;
			}

			$out['cells'][ $spec['column'] ] = $to;
			$out['row'][ $spec['column'] ]   = 'email' === $spec['type'] ? strtolower( $to ) : $to;
		}

		return $out;
	}

	/**
	 * A value in the shape the table keeps it: text with no space around it, a list of the distinct
	 * non-empty values it holds.
	 *
	 * @param string $type  The detail's type, from `fields()`.
	 * @param mixed  $value The value.
	 * @return string|string[]
	 */
	private static function tidy( $type, $value ) {
		if ( 'choices' === $type ) {
			$out = array();

			foreach ( is_array( $value ) ? $value : array() as $item ) {
				$item = is_scalar( $item ) ? trim( (string) $item ) : '';

				if ( '' !== $item && ! in_array( $item, $out, true ) ) {
					$out[] = $item;
				}
			}

			return $out;
		}

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Whether two tidied values are the same detail.
	 *
	 * @param string          $type The detail's type.
	 * @param string|string[] $one  One value.
	 * @param string|string[] $two  The other.
	 * @return bool
	 */
	private static function same( $type, $one, $two ) {
		if ( 'choices' === $type ) {
			$one = (array) $one;
			$two = (array) $two;

			sort( $one );
			sort( $two );

			return $one === $two;
		}

		if ( 'email' === $type ) {
			return strtolower( (string) $one ) === strtolower( (string) $two );
		}

		return (string) $one === (string) $two;
	}

	/**
	 * A value as a sentence says it: a country by its name, a list by its values, nothing as "not
	 * given".
	 *
	 * A country is named by the Countries map, and else by the name the pipeline index keeps beside the
	 * same ID (`indexed_country_name()`): a country row added in Airtable after the map was last read, or a
	 * map that is empty, would otherwise read as its record ID on the card, in the Administrator's table and
	 * in the emails. A country neither holds reads as its ID, which is at least something to search for.
	 *
	 * @param string $type  The detail's type.
	 * @param mixed  $value The value.
	 * @return string
	 */
	private static function shown( $type, $value ) {
		$value = self::tidy( $type, $value );

		if ( 'choices' === $type ) {
			$value = implode( ', ', $value );
		}

		if ( 'country' === $type && '' !== $value ) {
			$name  = WPCPM_Countries::name_of( $value );
			$name  = '' !== $name ? $name : self::indexed_country_name( $value );
			$value = '' !== $name ? $name : $value;
		}

		return '' === $value ? __( 'not given', 'wpcredits-program-manager' ) : $value;
	}

	/**
	 * The name an index row keeps beside a country's record ID, or ''.
	 *
	 * The institutions sync writes each row's country name with its ID, read from the map the same run
	 * refreshed, so the row of the institution the value is about names its own country, and a country asked
	 * for is named by any row that holds it.
	 *
	 * @param string $country A Countries record ID.
	 * @return string
	 */
	private static function indexed_country_name( $country ) {
		foreach ( WPCPM_Institutions_Index::rows() as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['country'], $row['country_name'] ) || (string) $row['country'] !== (string) $country ) {
				continue;
			}

			$name = trim( (string) $row['country_name'] );

			if ( '' !== $name ) {
				return $name;
			}
		}

		return '';
	}
}
