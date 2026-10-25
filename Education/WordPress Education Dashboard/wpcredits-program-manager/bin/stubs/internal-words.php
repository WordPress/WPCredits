<?php
/**
 * The words a school is not meant to read.
 *
 * One list for every suite that holds a page a school reads to it: the Institution Dashboard's shell
 * (`bin/test-institutions-dashboard.php`) and the school's details card, drawn for real in every state it
 * has (`bin/test-institution-details-card.php`), so a word added to the base's vocabulary is refused in
 * both and a card cannot say what the page may not.
 *
 * Loaded with `require_once __DIR__ . '/stubs/internal-words.php';`.
 */

/**
 * The words a school is not meant to read, found in one rendered page or one sentence.
 *
 * Table and view names, a record kind, the program's pipeline stage and the name of the
 * base itself are the base's words, and no sentence on a member's page may hold one. Decoded
 * before it is searched, because the page escapes its apostrophes.
 *
 * @param string $html A rendered page, or one sentence.
 * @return string[] The words found, in this list's order: empty when nothing is.
 */
function internal_words( $html ) {
	$html = html_entity_decode( (string) $html, ENT_QUOTES, 'UTF-8' );
	$out  = array();

	foreach ( array( 'Students table', 'report record', 'Stage:', 'viw', 'the program\'s contact', 'Airtable' ) as $word ) {
		if ( false !== stripos( $html, $word ) ) {
			$out[] = $word;
		}
	}

	return $out;
}
