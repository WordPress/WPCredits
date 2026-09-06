<?php
/**
 * WPCPM_Pdf_Check: is this a PDF, and what does a bounded scan find inside it?
 *
 * The scanner the Collaboration Agreement grew and the sponsor agreement now shares (design
 * spec of 4 September 2026, section 3 decision 1 and section 8.2). Four things are worth
 * pinning here rather than only through a handler:
 *
 * - **The name check and the byte check are different questions.** `named_pdf()` asks
 *   WordPress's own map about the extension and the declared type; `has_magic()` and
 *   `mime_of()` ask the bytes. A renamed file passes one and fails the other, which is the
 *   whole reason both are called.
 * - **The two refusals, and only two.** `/Encrypt` and `/Launch`. Everything else is a flag.
 * - **The two ways round a naive search.** A name written with `#` escapes, and a name hidden
 *   inside a `FlateDecode` stream. Both are undone before the search.
 * - **The budget is a budget.** A stream that would inflate past it is skipped rather than
 *   read, which is also a limit on what the scan finds, and the design says so out loud.
 *
 * Real temporary files throughout, and the real `finfo` extension: a scanner asserted against
 * a stub of the thing it exists to double-check is a scanner asserted against nothing.
 *
 * Run from the plugin root:  php bin/test-pdf-check.php
 *
 * @package WPCreditsProgramManager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['temp_files'] = array();

/**
 * WordPress's own name-and-type map, reimplemented over the real finfo, as
 * bin/test-institution-agreement.php reimplements it.
 *
 * @param string     $file     Path on disk.
 * @param string     $filename The name the file came with.
 * @param array|null $mimes    The map the caller allows.
 * @return array
 */
function wp_check_filetype_and_ext( $file, $filename, $mimes = null ) {
	$ext  = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
	$real = '';

	if ( function_exists( 'finfo_open' ) && is_readable( $file ) ) {
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$real  = $finfo ? (string) finfo_file( $finfo, $file ) : '';
	}

	if ( 'pdf' !== $ext || ( '' !== $real && 'application/pdf' !== $real ) ) {
		return array( 'ext' => false, 'type' => false, 'proper_filename' => false );
	}

	return array( 'ext' => 'pdf', 'type' => 'application/pdf', 'proper_filename' => false );
}

require_once __DIR__ . '/../includes/class-wpcpm-pdf-check.php';

$fail = 0; $checks = 0;

/**
 * One assertion.
 *
 * @param string $label    What is being asserted.
 * @param mixed  $actual   What the code answered.
 * @param mixed  $expected What it should have answered.
 */
function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	++$checks;
	if ( $actual === $expected ) { echo "ok   $label\n"; return; }
	++$fail;
	echo "FAIL $label\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
}

/**
 * A real file on disk, removed at the end of the run.
 *
 * @param string $bytes     What to write.
 * @param string $extension The extension, without the dot.
 * @return string The path.
 */
function temp_file( $bytes, $extension = 'pdf' ) {
	$path = tempnam( sys_get_temp_dir(), 'wpcpm-pdf-' ) . '.' . $extension;
	file_put_contents( $path, $bytes );
	$GLOBALS['temp_files'][] = $path;

	return $path;
}

/**
 * A minimal PDF every check passes.
 *
 * @param string $extra Anything to append before the trailer.
 * @return string
 */
function pdf_bytes( $extra = '' ) {
	return "%PDF-1.4\n"
		. "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
		. "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
		. "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\n"
		. $extra
		. "trailer<</Root 1 0 R/Size 4>>\n%%EOF\n";
}

/**
 * A PDF object whose stream is deflated, so the scan has to inflate it to see inside.
 *
 * @param string $inside What the stream says once inflated.
 * @return string
 */
function pdf_flate_object( $inside ) {
	$body = gzcompress( $inside );

	return "4 0 obj<</Length " . strlen( $body ) . "/Filter/FlateDecode>>stream\n" . $body . "\nendstream endobj\n";
}

echo "=== Is this a PDF at all ===\n";
$good = temp_file( pdf_bytes() );
$text = temp_file( "Not a PDF at all.\n", 'pdf' );
$png  = temp_file( base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ), 'pdf' );

ck( 'a real PDF named .pdf passes the name check', WPCPM_Pdf_Check::named_pdf( $good, 'signed.pdf' ), true );
ck( 'the same bytes named .exe do not', WPCPM_Pdf_Check::named_pdf( $good, 'signed.exe' ), false );
ck( 'a PNG named .pdf does not: the name says one thing and the bytes another', WPCPM_Pdf_Check::named_pdf( $png, 'signed.pdf' ), false );
ck( 'the magic bytes are read off the file contents', array( WPCPM_Pdf_Check::has_magic( file_get_contents( $good ) ), WPCPM_Pdf_Check::has_magic( file_get_contents( $text ) ), WPCPM_Pdf_Check::has_magic( '' ) ), array( true, false, false ) );
ck( 'finfo names the type', WPCPM_Pdf_Check::mime_of( $good ), 'application/pdf' );
ck( 'and a host with no fileinfo answers nothing rather than a wrong type', function_exists( 'finfo_open' ) ? 'application/pdf' : '', WPCPM_Pdf_Check::mime_of( $good ) );
ck( 'an empty type agrees, because a host without fileinfo may still send an agreement', array( WPCPM_Pdf_Check::mime_agrees( '' ), WPCPM_Pdf_Check::mime_agrees( 'application/pdf' ), WPCPM_Pdf_Check::mime_agrees( 'image/png' ) ), array( true, true, false ) );
ck( 'a missing file is not a PDF and does not warn', array( WPCPM_Pdf_Check::named_pdf( '/no/such/file.pdf', 'x.pdf' ), WPCPM_Pdf_Check::mime_of( '/no/such/file.pdf' ) ), array( false, '' ) );

echo "\n=== inspect(): two refusals, five flags, and the two ways round a naive search ===\n";
ck( 'a minimal PDF passes with nothing to report', WPCPM_Pdf_Check::inspect( pdf_bytes() ), array( 'ok' => true, 'reason' => '', 'flags' => array() ) );
ck( 'an empty string is nothing to report rather than a crash', WPCPM_Pdf_Check::inspect( '' ), array( 'ok' => true, 'reason' => '', 'flags' => array() ) );
ck( '/Encrypt refuses by name', WPCPM_Pdf_Check::inspect( pdf_bytes( "5 0 obj<</Encrypt 6 0 R>>endobj\n" ) ), array( 'ok' => false, 'reason' => 'agreement-encrypted', 'flags' => array() ) );
ck( '/Launch refuses by name', WPCPM_Pdf_Check::inspect( pdf_bytes( "5 0 obj<</S/Launch/F(calc.exe)>>endobj\n" ) ), array( 'ok' => false, 'reason' => 'agreement-launch', 'flags' => array() ) );
ck( 'a hash-escaped name is decoded first', WPCPM_Pdf_Check::inspect( pdf_bytes( "5 0 obj<</S/L#61unch/F(calc.exe)>>endobj\n" ) )['reason'], 'agreement-launch' );
ck( 'and a fully escaped one', WPCPM_Pdf_Check::inspect( pdf_bytes( "5 0 obj<</S/#4C#61#75#6E#63#68>>endobj\n" ) )['reason'], 'agreement-launch' );
ck( 'a name hidden in a deflated stream is inflated first', WPCPM_Pdf_Check::inspect( pdf_bytes( pdf_flate_object( '<</S/Launch/F(calc.exe)>>' ) ) )['reason'], 'agreement-launch' );
ck( 'escaped and deflated at once', WPCPM_Pdf_Check::inspect( pdf_bytes( pdf_flate_object( '<</S/L#61unch>>' ) ) )['reason'], 'agreement-launch' );
ck( '/Encrypt in a stream refuses as well', WPCPM_Pdf_Check::inspect( pdf_bytes( pdf_flate_object( '<</Encrypt 9 0 R>>' ) ) )['reason'], 'agreement-encrypted' );
ck( 'a raw deflate stream with no zlib header is read too', WPCPM_Pdf_Check::inspect( pdf_bytes( "4 0 obj<</Filter/FlateDecode>>stream\n" . gzdeflate( '<</S/Launch>>' ) . "\nendstream endobj\n" ) )['reason'], 'agreement-launch' );

$flagged = WPCPM_Pdf_Check::inspect( pdf_bytes( "5 0 obj<</OpenAction 6 0 R/AA 7 0 R>>endobj\n6 0 obj<</S/JavaScript/JS(app.alert\\(1\\))>>endobj\n8 0 obj<</Type/Filespec/EmbeddedFile 9 0 R>>endobj\n" ) );
ck( 'the flags are recorded and never a refusal', array( $flagged['ok'], $flagged['reason'], $flagged['flags'] ), array( true, '', array( '/JavaScript', '/JS', '/OpenAction', '/AA', '/EmbeddedFile' ) ) );
ck( 'the flag names are the ones a reviewer would search for', WPCPM_Pdf_Check::SCAN_FLAGS, array( '/JavaScript', '/JS', '/OpenAction', '/AA', '/EmbeddedFile' ) );

$lookalikes = WPCPM_Pdf_Check::inspect( pdf_bytes( "5 0 obj<</S/JScript/X/AArgh/Y/Launcher>>endobj\n" ) );
ck( 'a name ends at a delimiter: /JSomething, /AArgh and /Launcher are not matches', array( $lookalikes['ok'], $lookalikes['flags'] ), array( true, array() ) );

echo "\n=== The budget ===\n";
$bomb = pdf_bytes( "4 0 obj<</Filter/FlateDecode>>stream\n" . gzcompress( str_repeat( 'A', WPCPM_Pdf_Check::SCAN_MAX_STREAM + 1024 ) . '/Launch' ) . "\nendstream endobj\n" );
$scan = WPCPM_Pdf_Check::inspect( $bomb );
ck( 'a stream that would inflate past the budget is skipped rather than read', $scan['ok'], true );
// The bound sits exactly at SCAN_MAX_STREAM: a stream that inflates to the limit, or to a little
// under it, is still read whole and its refusal found; one byte over is skipped (the review of
// Task 1: the added bound is the one line most worth pinning at its boundary).
$at_limit = WPCPM_Pdf_Check::inspect( pdf_bytes( "4 0 obj<</Filter/FlateDecode>>stream\n" . gzcompress( str_repeat( 'A', WPCPM_Pdf_Check::SCAN_MAX_STREAM - 7 ) . '/Launch' ) . "\nendstream endobj\n" ) );
$under    = WPCPM_Pdf_Check::inspect( pdf_bytes( "4 0 obj<</Filter/FlateDecode>>stream\n" . gzcompress( str_repeat( 'A', WPCPM_Pdf_Check::SCAN_MAX_STREAM - 1024 ) . '/Launch' ) . "\nendstream endobj\n" ) );
$over     = WPCPM_Pdf_Check::inspect( pdf_bytes( "4 0 obj<</Filter/FlateDecode>>stream\n" . gzcompress( str_repeat( 'A', WPCPM_Pdf_Check::SCAN_MAX_STREAM - 6 ) . '/Launch' ) . "\nendstream endobj\n" ) );
ck( 'a stream inflating to exactly the budget, or under it, is still searched and refused; one byte over is skipped', array( $at_limit['reason'], $under['reason'], $over['ok'] ), array( 'agreement-launch', 'agreement-launch', true ) );
ck( 'the budget is the one the constants name', array( WPCPM_Pdf_Check::SCAN_MAX_STREAM, WPCPM_Pdf_Check::SCAN_MAX_TOTAL, WPCPM_Pdf_Check::SCAN_MAX_STREAMS ), array( 2097152, 8388608, 200 ) );
ck( 'the two refusals, and only two', WPCPM_Pdf_Check::SCAN_REFUSALS, array( '/Encrypt' => 'agreement-encrypted', '/Launch' => 'agreement-launch' ) );

echo "\n=== House rules ===\n";
$src = (string) file_get_contents( __DIR__ . '/../includes/class-wpcpm-pdf-check.php' );
ck( 'no em or en dash', preg_match( '/\x{2013}|\x{2014}/u', $src ), 0 );
ck( 'the scanner reads no superglobal of its own', preg_match( '/\$_FILES|\$_POST|\$_GET/', $src ), 0 );
ck( 'and never moves a file on the strength of its claimed type', preg_match( '/wp_handle_upload|move_uploaded_file/', $src ), 0 );

$agreement = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-institution-agreement.php' );
ck( 'the institution class holds no copy of the scan any more', preg_match( '/function (decode_names|names_contain|inflated_streams|inflate)\(/', $agreement ), 0 );
ck( 'and reaches the scanner through the extraction', substr_count( $agreement, 'WPCPM_Pdf_Check::' ) >= 4, true );

foreach ( $GLOBALS['temp_files'] as $temp ) {
	if ( is_file( $temp ) ) { unlink( $temp ); }
}

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
