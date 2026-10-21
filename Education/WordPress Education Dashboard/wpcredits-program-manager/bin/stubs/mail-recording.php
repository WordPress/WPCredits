<?php
/**
 * What a suite's mails say, recorded once, before the event and term emails moved into the template
 * registry, and compared after, byte for byte.
 *
 * A suite hands each mail it captures to `wpcpm_record_mail()` as it captured it: who it went to, the
 * id it was logged under, the subject, the body, the headers and how many files went with it. A body
 * is kept without its signature and footer (`wpcpm_footer_split()`): the mail layer adds the footer
 * to every email where it leaves, once the registry is loaded, and the suites the recording was made
 * by did not load it; whether each mail carries the footer is a check of its own, the suite's.
 *
 * Every address is written "at" its domain, in the recording and in what a run is compared by, so
 * the recording holds no address (bin/test-fixtures.php holds every fixture to that).
 *
 * `wpcpm_recording_checks()` writes the recording when the suite runs with `--record`, and refuses
 * when the file is there, so a recording made from later code can never stand in for the one made
 * before the move; otherwise it answers the checks a suite runs: the same mails in the same order,
 * each byte for byte but for its id, and each id one of the registry's.
 *
 * @package WPCreditsProgramManager
 */

/**
 * A body and its signature and footer, apart: the footer starts at the last line holding "-- " alone,
 * as `WPCPM_Mail_Templates::add_footer()` writes it, in the body's own line ending.
 *
 * @param string $body The body as it was handed on.
 * @return string[] The body before the footer, and the footer from its "-- " line ('' with none).
 */
function wpcpm_footer_split( $body ) {
	$body = (string) $body;

	foreach ( array( "\r\n", "\n" ) as $eol ) {
		$at = strrpos( $body, $eol . $eol . '-- ' . $eol );

		if ( false !== $at ) {
			return array( substr( $body, 0, $at ), substr( $body, $at + 2 * strlen( $eol ) ) );
		}
	}

	return array( $body, '' );
}

/**
 * A text as the recording keeps it: each "@" written " at ", so no address is in it.
 *
 * @param string $text A field of a mail.
 * @return string
 */
function wpcpm_recording_text( $text ) {
	return str_replace( '@', ' at ', (string) $text );
}

/**
 * Keep one mail for the recording.
 *
 * @param string $to      The address, or what stands for the recipient where a suite has none.
 * @param string $context The id it was logged under.
 * @param string $subject The subject.
 * @param string $body    The body, its footer still on it or not.
 * @param array  $headers The headers.
 * @param int    $files   How many files went with it.
 */
function wpcpm_record_mail( $to, $context, $subject, $body, $headers = array(), $files = 0 ) {
	$split = wpcpm_footer_split( $body );

	$GLOBALS['wpcpm_footers'][]  = $split[1];
	$GLOBALS['wpcpm_recorded'][] = array(
		'to'      => wpcpm_recording_text( $to ),
		'context' => (string) $context,
		'subject' => wpcpm_recording_text( $subject ),
		'body'    => wpcpm_recording_text( $split[0] ),
		'headers' => array_values( array_map( 'wpcpm_recording_text', (array) $headers ) ),
		'files'   => (int) $files,
	);
}

/**
 * Write the recording, or the checks that compare a run with it.
 *
 * @param string        $file    The recording's file, under bin/fixtures/.
 * @param string        $sha256  The recording's sha256, pinned, so a recording made again from later
 *                               code can never stand in for it.
 * @param string[]|null $ids     The ids a mail may be logged under now, or null before the move,
 *                               when every mail of the area was logged under one id.
 * @param array         $changes What changes on purpose: mail number to field to pairs of the
 *                               recorded text and the text now.
 * @param string|null   $footer  The signature and footer every mail ends with, from its "-- " line,
 *                               '' for a suite whose mail layer is a stand-in that adds none, or
 *                               null before the move, when the suite loaded no registry to add one.
 * @return array[] Each check: its label, what the run gave and what the check wants.
 */
function wpcpm_recording_checks( $file, $sha256, $ids = null, array $changes = array(), $footer = null ) {
	$path = __DIR__ . '/../fixtures/' . $file;
	$sent = isset( $GLOBALS['wpcpm_recorded'] ) ? $GLOBALS['wpcpm_recorded'] : array();

	if ( in_array( '--record', isset( $GLOBALS['argv'] ) ? (array) $GLOBALS['argv'] : array(), true ) ) {
		if ( file_exists( $path ) ) {
			fwrite( STDERR, "The recording is there already: $file\n" );
			exit( 1 );
		}

		file_put_contents( $path, json_encode( $sent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
		fwrite( STDERR, 'Recorded ' . count( $sent ) . " mails into $file\n" );
		exit( 0 );
	}

	$recorded = json_decode( (string) @file_get_contents( $path ), true );
	$recorded = is_array( $recorded ) ? $recorded : array();
	$stale    = array();
	$wanted   = array();

	foreach ( $recorded as $n => $mail ) {
		foreach ( isset( $changes[ $n ] ) ? $changes[ $n ] : array() as $field => $pairs ) {
			foreach ( $pairs as $old => $new ) {
				$was            = $mail[ $field ];
				$mail[ $field ] = str_replace( (string) $old, (string) $new, $mail[ $field ] );

				if ( $was === $mail[ $field ] ) {
					$stale[] = $n . ' ' . $field . ': ' . $old;
				}
			}
		}

		unset( $mail['context'] );
		$wanted[ $n ] = $mail;
	}

	$got = array();

	foreach ( $sent as $n => $mail ) {
		unset( $mail['context'] );
		$got[ $n ] = $mail;
	}

	$checks = array(
		array( 'the recording made before the move holds the mails of this suite, and is that recording, by its sha256: ' . $file, array( count( $recorded ) > 0, is_file( $path ) ? hash_file( 'sha256', $path ) : '' ), array( true, (string) $sha256 ) ),
		array( 'every mail goes as the recording holds it, to the same people in the same order, byte for byte, the signature and footer apart, but what is listed as changed on purpose', $got, $wanted ),
		array( 'and every change listed is made', $stale, array() ),
	);

	if ( null !== $ids ) {
		$checks[] = array( 'and every mail is logged under an id the registry holds', array_values( array_unique( array_diff( array_column( $sent, 'context' ), $ids ) ) ), array() );
	}

	if ( null !== $footer ) {
		// Written in the body's own line ending, as the mail layer writes it: a body holding a CR LF
		// that somebody typed ends with a footer in CR LF.
		$footers  = array_map(
			static function ( $text ) {
				return str_replace( "\r\n", "\n", (string) $text );
			},
			isset( $GLOBALS['wpcpm_footers'] ) ? $GLOBALS['wpcpm_footers'] : array()
		);
		$checks[] = array( 'and every mail ends with the signature and footer, or, where the mail layer is a stand-in, with none', array_values( array_unique( $footers ) ), array() === $sent ? array() : array( $footer ) );
	}

	return $checks;
}
