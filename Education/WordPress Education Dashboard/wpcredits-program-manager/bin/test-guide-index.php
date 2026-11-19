<?php
/**
 * The guide index behind Need help?: the text pipeline, the index, and its sources.
 *
 * This suite grows one part per task of the AI Mentor R1 release. Add a part below the last one; never
 * rewrite an earlier part to make a later one pass.
 *
 * Part 1 - the text pipeline (WPCPM_Guide_Text), plain PHP with no WordPress function:
 *
 * - **Porter's stemmer**, pinned on the 1980 paper's own pairs. Search quality rests on a question's
 *   "booking" and a guide's "booked" reaching the same term, and on no word being stemmed differently
 *   from the one it is matched to, so a drift in one rule is a silent loss of answers.
 * - **Terms**: stop words and one-letter words go, letters of any script stay, order and repeats stay.
 * - **Counts**: a heading's terms count twice, so a section named for the question outranks one that
 *   only mentions it.
 * - **plain()**: a built guide is block markup; what is indexed is the text a reader sees. The handbook's
 *   glossary tooltips are hover text a reader never sees on the page: they go, with all they hold, or
 *   they fuse words ("mentorEvent Supporter") and quote a WordCamp meaning of "mentor" to the model.
 * - **Passages**: where a section starts (h2, h3, h4, a details with an id), the heading path and the
 *   anchor each carries (the anchor becomes the link in an answer's source), and the split of a long
 *   section, which must lose nothing.
 *
 * Part 2 - the index's two tables (WPCPM_Guide_Index) and the ranking (WPCPM_Guide_Search), over the
 * suites' SQLite stand-in of the database:
 *
 * - **The schema as text**, every column and key of the design's section 4, because SQLite never reads
 *   it: the live site's MariaDB does, through dbDelta(). The terms' column compares bytes (utf8mb4_bin),
 *   or "cafe" and "café" in one passage are one key to MariaDB and the passage's terms are refused.
 * - **The upgrade** stamps its version only once both tables are there, so a failed creation is tried
 *   again on the next load.
 * - **Writing a source** writes each passage with its address, hash and length and its terms as counts()
 *   gives them for the page's title and the heading path together (Ruling T2-1: a page titled "Launch
 *   Your WordPress Credits Program" answers "launch a program" in every section, though no section's
 *   heading says so), while the stored heading stays the path; writing it again replaces it, and removing
 *   it leaves no passage, term or state behind. A stale term row is a passage the ranking can still
 *   score; a stale state is a page the daily run skips.
 * - **A passage whose address does not survive esc_url_raw() is not written** (the plan's Ruling 7): an
 *   answer's source link is built from this column.
 * - **A write that fails is recorded as one.** With a table gone, a delete refused or a key too long for
 *   its column, nothing is half written (no new passage beside an old one, no passage without its terms)
 *   and the state's entry carries an error and no modified time. Task 3 skips a source whose modified
 *   time is the page's, so a failure recorded as a success would leave the page unanswered until it is
 *   next edited. The error is a fixed sentence: the database's own message can name tables and values.
 * - **Terms are cut to the column's 40 characters and merged after the cut**, and written 500 rows a
 *   statement: two long terms that cut alike would otherwise break the primary key.
 * - **BM25 as the design gives it** (k1 1.2, b 0.75, idf over every passage of the index, not only the
 *   ones the asker reads), checked against the formula worked out here by hand; a query's repeated terms
 *   count once.
 * - **Nothing above the asker's level** (Review Focus 1): a reader of student and public passages is given
 *   nothing from a corpus of one Mentor guide passage.
 * - **The weights**: the home guide's 1.25 puts the asker's guide first between two passages that score
 *   alike; two passages with one hash give one result, the home guide's; the handbook's 0.8 puts the
 *   site's passage first at equal BM25, and the filter can move it.
 *
 * Part 3 - the sources and every trigger that reindexes (WPCPM_Guide_Sources), over stand-ins of the
 * WordPress functions, the page access levels as the plugin's own classes read them, the lock and the
 * handbook's route (no request is made):
 *
 * - **Who reads which audience** (the security boundary): `public` always, and each of the five whose
 *   page the asker may read, asked of WPCPM_Content_Access::can_view() at the question, so a page whose
 *   level becomes Administrators only is no longer read by a mentor at the next question. A page that
 *   is missing, a draft or password-protected is read by nobody, an Administrator included. The home
 *   guide is the asked one when it is read, else the first of the fixed order.
 * - **The saved page** (Review Focus 4): a revision, an autosave, another page, another type: nothing. A
 *   draft of the Student guide, the FAQ unpublished, a guide moved off its path, a password put on a
 *   guide: its passages leave. A save while the index is locked writes nothing and asks for a rebuild.
 * - **The handbook's keep filter** and the sync from the route's own answer: the twelve kept pages are
 *   written whole, a second run changes nothing, a page that moved is written alone, a failed write is
 *   rewritten, a page gone from the answer is removed.
 * - **A bad answer changes nothing** (Review Focus 5): an error, a 500, a body that is not JSON, an empty
 *   list, an object: every handbook passage stays, and the state says why.
 * - **Rebuild, daily, schedule**: a rebuild writes every source again (the handbook's too, moved or not)
 *   and notes the version; it answers `busy` while the lock is held; the daily run reindexes only what
 *   moved and drops the pages that are gone; a release that moved the version schedules one rebuild.
 * - **Activation, deactivation, uninstall** and the hooks the plugin boots.
 *
 * Part 4 - the outside review's findings and the trial's (the fixes before the release):
 *
 * - **Hidden markup is never a passage.** A comment, a script, a style or an SVG is taken out before the
 *   sections are found, so a heading inside one (`<!-- <h2>Old rule</h2><p>...</p> -->`, a script
 *   template) starts no passage and its text is never searchable.
 * - **A public page is checked at the question** (`excluded_for()`): the index holds the FAQ's passages
 *   as `public`, which everybody reads, so a FAQ made Administrators only by its level alone, while the
 *   lock deferred its removal, would still feed everybody. Each `page:` source of audience `public` is
 *   asked whether it is published, has no password and lets the asker in, and one that fails is left out
 *   of the ranking by its key. The handbook is read by everybody whatever.
 * - **An incomplete handbook entry deletes nothing.** An entry with an id but without its address, its
 *   modified time or its content keeps what the index holds of it, and the sync says how many there
 *   were. An entry with no usable id is counted too, and while there is one the removal sweep is
 *   skipped: nothing is removed, every whole entry is still written, and the sync stays ok.
 * - **A write the database refused fails the sync.** A handbook page whose write or removal failed is
 *   not counted as changed or removed, and the sync's result and stored state say how many failed, so
 *   the command line and the Need help? screen say it failed.
 * - **The section titles of a guide** (`headings()`), for the search-words call: its own guide's
 *   distinct titles, in order, each cut to 80 characters, at most a limit.
 *
 * Run from the plugin root:  php bin/test-guide-index.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$fail = 0;

function ck( $label, $actual, $expected ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-guide-text.php';

// ---------------------------------------------------------------------------------------------
// Part 1: the text pipeline.
// ---------------------------------------------------------------------------------------------

$stem = array( 'WPCPM_Guide_Text', 'stem' );

ck( 'Porter pairs',
    array_map( $stem, array( 'caresses', 'ponies', 'booking', 'booked', 'books', 'mentoring', 'mentors', 'graduation', 'graduated', 'relational', 'generalization' ) ),
    array( 'caress', 'poni', 'book', 'book', 'book', 'mentor', 'mentor', 'graduat', 'graduat', 'relat', 'gener' ) );

// The paper's own list, step by step.
$paper = array(
	'ties' => 'ti', 'caress' => 'caress', 'cats' => 'cat',
	'feed' => 'feed', 'agreed' => 'agre', 'plastered' => 'plaster', 'bled' => 'bled',
	'motoring' => 'motor', 'sing' => 'sing',
	'conflated' => 'conflat', 'troubled' => 'troubl', 'sized' => 'size',
	'hopping' => 'hop', 'tanned' => 'tan', 'falling' => 'fall', 'hissing' => 'hiss', 'fizzed' => 'fizz',
	'failing' => 'fail', 'filing' => 'file',
	'happy' => 'happi', 'sky' => 'sky',
	'conditional' => 'condit', 'rational' => 'ration', 'valenci' => 'valenc', 'hesitanci' => 'hesit',
	'digitizer' => 'digit', 'conformabli' => 'conform', 'radicalli' => 'radic', 'differentli' => 'differ',
	'vileli' => 'vile', 'analogousli' => 'analog', 'vietnamization' => 'vietnam', 'predication' => 'predic',
	'operator' => 'oper', 'feudalism' => 'feudal', 'decisiveness' => 'decis', 'hopefulness' => 'hope',
	'callousness' => 'callous', 'formaliti' => 'formal', 'sensitiviti' => 'sensit', 'sensibiliti' => 'sensibl',
	'triplicate' => 'triplic', 'formative' => 'form', 'formalize' => 'formal', 'electriciti' => 'electr',
	'electrical' => 'electr', 'hopeful' => 'hope', 'goodness' => 'good',
	'revival' => 'reviv', 'allowance' => 'allow', 'inference' => 'infer', 'airliner' => 'airlin',
	'gyroscopic' => 'gyroscop', 'adjustable' => 'adjust', 'defensible' => 'defens', 'irritant' => 'irrit',
	'replacement' => 'replac', 'adjustment' => 'adjust', 'dependent' => 'depend', 'adoption' => 'adopt',
	'homologou' => 'homolog', 'communism' => 'commun', 'activate' => 'activ', 'angulariti' => 'angular',
	'homologous' => 'homolog', 'effective' => 'effect', 'bowdlerize' => 'bowdler',
	'probate' => 'probat', 'rate' => 'rate', 'cease' => 'ceas', 'controll' => 'control', 'roll' => 'roll',
);
foreach ( $paper as $in => $want ) {
	ck( "Porter: $in", WPCPM_Guide_Text::stem( $in ), $want );
}

ck( 'a short word is left alone', array_map( $stem, array( 'a', 'is', 'as', 'by' ) ), array( 'a', 'is', 'as', 'by' ) );
ck( 'a word with a letter outside a to z is returned as it is',
    array_map( $stem, array( 'cómo', 'envío', 'wp-admin', 'Books', 'e2e', "o'clock" ) ),
    array( 'cómo', 'envío', 'wp-admin', 'Books', 'e2e', "o'clock" ) );

$stop = WPCPM_Guide_Text::stop_words();
ck( 'stop words: Lucene English plus the question words',
    array(
        isset( $stop['the'] ), isset( $stop['and'] ), isset( $stop['their'] ), isset( $stop['with'] ),
        isset( $stop['can'] ), isset( $stop['do'] ), isset( $stop['does'] ), isset( $stop['how'] ), isset( $stop['i'] ),
        isset( $stop['my'] ), isset( $stop['what'] ), isset( $stop['when'] ), isset( $stop['where'] ),
        isset( $stop['which'] ), isset( $stop['who'] ), isset( $stop['why'] ), isset( $stop['you'] ), isset( $stop['your'] ),
        isset( $stop['mentor'] ), true === $stop['the'],
    ),
    array_merge( array_fill( 0, 18, true ), array( false, true ) ) );

ck( 'stop words and one-letter words go', WPCPM_Guide_Text::terms( 'How do I book a call with my mentor?' ), array( 'book', 'call', 'mentor' ) );
ck( 'letters of any script are words', WPCPM_Guide_Text::terms( 'Cómo envío mi informe' ), array( 'cómo', 'envío', 'mi', 'inform' ) );
ck( 'case is folded, order and repeats are kept', WPCPM_Guide_Text::terms( 'Mentors, MENTORING and mentor 2 of 10' ), array( 'mentor', 'mentor', 'mentor', '10' ) );
ck( 'punctuation splits words', WPCPM_Guide_Text::terms( 'wp-admin/e-mail_log' ), array( 'wp', 'admin', 'mail', 'log' ) );
ck( 'no text, no terms', WPCPM_Guide_Text::terms( '  ... ' ), array() );

ck( 'a heading counts twice',
    WPCPM_Guide_Text::counts( 'Group sessions', 'A group session is booked.' ),
    array( 'group' => 3, 'session' => 3, 'book' => 1 ) );
ck( 'counts with no heading', WPCPM_Guide_Text::counts( '', 'Book, book and book.' ), array( 'book' => 3 ) );

ck( 'plain: tags, comments, scripts and entities',
    WPCPM_Guide_Text::plain( "<!-- wp:paragraph -->\n<p>Tom &amp; <strong>Jerry</strong>&nbsp;said &quot;hi&quot;.</p>\n<!-- /wp:paragraph --><script>var x = 1;</script><style>p{}</style><svg><path d=\"M0\"/></svg>" ),
    'Tom & Jerry said "hi".' );
ck( 'plain: block ends become lines, spaces collapse, empty lines go',
    WPCPM_Guide_Text::plain( "<ul><li>One   two</li>\n\n<li>  Three</li></ul><p>A<br>B<br />C</p><table><tr><td>x</td></tr><tr><td>y</td></tr></table>" ),
    "One two\nThree\nA\nB\nC\nx\ny" );
ck( 'plain: table cells do not fuse', WPCPM_Guide_Text::plain( '<table><tr><td>Name</td><td>Role</td></tr></table>' ), 'Name Role' );
ck( 'plain: adjacent divs are two lines', WPCPM_Guide_Text::plain( '<div>a</div><div>b</div>' ), "a\nb" );
ck( 'plain: other block ends',
    WPCPM_Guide_Text::plain( '<h1>T</h1><dl><dt>k</dt><dd>v</dd></dl><blockquote>q</blockquote><pre>c</pre><figure><figcaption>f</figcaption></figure><ol><li>x</li></ol>' ),
    "T\nk\nv\nq\nc\nf\nx" );
ck( 'plain: headings and summaries end a line', WPCPM_Guide_Text::plain( '<h2>One</h2><p>Two</p><details><summary>Q?</summary>A</details>' ), "One\nTwo\nQ?\nA" );

// Passages ---------------------------------------------------------------------------------

$fixture = '<h2 id="a">One</h2><p>x</p><h3 id="b">Two</h3><p>y</p><details id="q"><summary>Three?</summary><p>z</p></details>';
$p       = WPCPM_Guide_Text::passages( $fixture );
ck( 'passage headings', array_column( $p, 'heading' ), array( 'One', 'One > Two', 'One > Two > Three?' ) );
ck( 'passage anchors', array_column( $p, 'anchor' ), array( 'a', 'b', 'q' ) );
ck( 'passage bodies', array_column( $p, 'body' ), array( 'x', 'y', 'z' ) );
ck( 'a passage has exactly three keys', array_keys( $p[0] ), array( 'heading', 'anchor', 'body' ) );

$p = WPCPM_Guide_Text::passages( '<p>Intro</p><h2 id="a">One</h2><p>x</p><h3 id="b">Two</h3><p>y</p><h2 id="c">Next</h2><p>w</p><h3 id="d">Under next</h3><p>v</p><h4 id="e">Deep</h4><p>u</p><h3 id="f">Back up</h3><p>t</p>' );
ck( 'text before the first heading has no heading and no anchor',
    array( $p[0]['heading'], $p[0]['anchor'], $p[0]['body'] ), array( '', '', 'Intro' ) );
ck( 'a heading of the same or a higher level replaces the path below it',
    array_column( $p, 'heading' ),
    array( '', 'One', 'One > Two', 'Next', 'Next > Under next', 'Next > Under next > Deep', 'Next > Back up' ) );
ck( 'the anchor is the heading\'s own', array_column( $p, 'anchor' ), array( '', 'a', 'b', 'c', 'd', 'e', 'f' ) );

$p = WPCPM_Guide_Text::passages( '<h2 id="a">One</h2><p>x</p><h2 id="b">Two</h2><h3 id="c">Three</h3><p>y</p>' );
ck( 'a heading with nothing under it is dropped but still names the path below it',
    array( array_column( $p, 'heading' ), array_column( $p, 'anchor' ) ),
    array( array( 'One', 'Two > Three' ), array( 'a', 'c' ) ) );

$p = WPCPM_Guide_Text::passages( '<h2 class="wp-block-heading" id="m"><strong>Mentor</strong> &amp; <code>wp-admin</code> <a href="/x">links</a></h2><p>x</p><h3>No id here</h3><p>y</p>' );
ck( 'heading text strips markup and decodes entities',
    array( array_column( $p, 'heading' ), array_column( $p, 'anchor' ) ),
    array( array( 'Mentor & wp-admin links', 'Mentor & wp-admin links > No id here' ), array( 'm', '' ) ) );

$p = WPCPM_Guide_Text::passages( '<h2 id="a">One</h2><p>before</p><details class="wp-block-details" id="q1"><summary>Q &amp; A?</summary><p>answer one</p></details><p>after</p><details id="q2"><summary>Second?</summary><p>answer two</p></details>' );
ck( 'details: a summary is one level under the heading, and text after it goes back to the heading',
    array( array_column( $p, 'heading' ), array_column( $p, 'anchor' ), array_column( $p, 'body' ) ),
    array(
        array( 'One', 'One > Q & A?', 'One', 'One > Second?' ),
        array( 'a', 'q1', 'a', 'q2' ),
        array( 'before', 'answer one', 'after', 'answer two' ),
    ) );

$p = WPCPM_Guide_Text::passages( '<h2 id="a">One</h2><details><summary>No id?</summary><p>kept</p></details>' );
ck( 'a details without an id is not a section: its text stays in the body',
    array( array_column( $p, 'heading' ), array_column( $p, 'body' ) ),
    array( array( 'One' ), array( "No id?\nkept" ) ) );

ck( 'no markup, no passage', WPCPM_Guide_Text::passages( '<!-- wp:spacer --><div></div>' ), array() );
ck( 'text with no heading is one passage', WPCPM_Guide_Text::passages( '<p>Just this.</p>' ), array( array( 'heading' => '', 'anchor' => '', 'body' => 'Just this.' ) ) );

// A long section is split at line ends, and nothing is lost.
$words = function ( $from, $count ) {
	$out = array();
	for ( $i = $from; $i < $from + $count; $i++ ) {
		$out[] = 'w' . $i;
	}
	return $out;
};
$lines = array();
for ( $i = 0; $i < 9; $i++ ) {
	$lines[] = '<p>' . implode( ' ', $words( $i * 100, 100 ) ) . '</p>';
}
$p = WPCPM_Guide_Text::passages( '<h2 id="long">Long</h2>' . implode( '', $lines ) );
$wc = function ( $s ) {
	return count( preg_split( '/\s+/', trim( $s ), -1, PREG_SPLIT_NO_EMPTY ) );
};
ck( 'a 900-word section gives three passages', count( $p ), 3 );
ck( 'each is at most 350 words', array_map( $wc, array_column( $p, 'body' ) ), array( 300, 300, 300 ) );
ck( 'each keeps the heading and the anchor', array( array_unique( array_column( $p, 'heading' ) ), array_unique( array_column( $p, 'anchor' ) ) ), array( array( 'Long' ), array( 'long' ) ) );
ck( 'nothing is lost',
    preg_split( '/\s+/', implode( ' ', array_column( $p, 'body' ) ) ),
    $words( 0, 900 ) );

// One line over the limit is split at sentence ends, then at the limit.
$sent = array();
for ( $i = 0; $i < 9; $i++ ) {
	$sent[] = implode( ' ', $words( $i * 100, 100 ) ) . '.';
}
$p = WPCPM_Guide_Text::passages( '<h2 id="one">One line</h2><p>' . implode( ' ', $sent ) . '</p>' );
ck( 'one 900-word line is split at sentence ends', array_map( $wc, array_column( $p, 'body' ) ), array( 300, 300, 300 ) );
ck( 'the sentences are all there',
    preg_replace( '/\./', '', implode( ' ', array_column( $p, 'body' ) ) ),
    implode( ' ', $words( 0, 900 ) ) );

$p = WPCPM_Guide_Text::passages( '<h2 id="w">Wall</h2><p>' . implode( ' ', $words( 0, 800 ) ) . '</p>' );
ck( 'one 800-word line with no sentence end is cut at 350 words', array_map( $wc, array_column( $p, 'body' ) ), array( 350, 350, 100 ) );
ck( 'and loses nothing', preg_split( '/\s+/', implode( ' ', array_column( $p, 'body' ) ) ), $words( 0, 800 ) );

$p = WPCPM_Guide_Text::passages( '<h2 id="e">Edge</h2><p>' . implode( ' ', $words( 0, 350 ) ) . '</p>' );
ck( 'exactly 350 words is one passage', count( $p ), 1 );

// The built guides and the FAQ -----------------------------------------------------------------

$students = file_get_contents( WPCPM_PLUGIN_DIR . 'docs/build/students.html' );
$by_head  = array();
foreach ( WPCPM_Guide_Text::passages( (string) $students ) as $row ) {
	$by_head[ $row['heading'] ] = $row;
}
ck( 'the Student guide has Report form under the Student Report Card',
    isset( $by_head['What is on your Student Report Card > Report form'] ) ? $by_head['What is on your Student Report Card > Report form']['anchor'] : null,
    'report-form' );
ck( 'and that passage has text', '' !== ( isset( $by_head['What is on your Student Report Card > Report form'] ) ? $by_head['What is on your Student Report Card > Report form']['body'] : '' ), true );
ck( 'no passage of the Student guide has an empty body or runs past the limit',
    array_filter( WPCPM_Guide_Text::passages( (string) $students ), function ( $row ) use ( $wc ) {
        return '' === $row['body'] || $wc( $row['body'] ) > WPCPM_Guide_Text::PASSAGE_WORDS;
    } ),
    array() );
ck( 'no passage carries a tag or an entity',
    array_filter( WPCPM_Guide_Text::passages( (string) $students ), function ( $row ) {
        return 1 === preg_match( '/<\/?[a-z]|&[a-z]+;|<!--/i', $row['body'] . $row['heading'] );
    } ),
    array() );

$admin_html = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'docs/build/administrators.html' );
$fused      = array();
foreach ( WPCPM_Guide_Text::passages( $admin_html ) as $row ) {
	if ( false !== strpos( $row['body'], 'waitsOverview' ) ) {
		$fused[] = $row['heading'];
	}
}
ck( 'the Administrator guide has no fused cell words', $fused, array() );
$glance = array();
foreach ( WPCPM_Guide_Text::passages( $admin_html ) as $row ) {
	if ( 'Start here > The Administrator Dashboard at a glance' === $row['heading'] ) {
		$glance = array_merge( $glance, WPCPM_Guide_Text::terms( $row['body'] ) );
	}
}
ck( 'the at-a-glance passage indexes wait and overview as words', array( in_array( 'wait', $glance, true ), in_array( 'overview', $glance, true ), in_array( 'waitsoverview', $glance, true ) ), array( true, true, false ) );

$faq     = json_decode( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'bin/fixtures/guides/faq.json' ), true );
$faq_row = null;
foreach ( WPCPM_Guide_Text::passages( (string) $faq['content']['rendered'] ) as $row ) {
	if ( 'Before you apply > Do I need to know how to code?' === $row['heading'] ) {
		$faq_row = $row;
	}
}
preg_match( '/<details[^>]*\sid="([^"]+)"[^>]*>\s*<summary[^>]*>\s*Do I need to know how to code\?/', (string) $faq['content']['rendered'], $faq_id );
ck( 'the FAQ question is a passage under its heading, with its details id',
    null === $faq_row ? null : $faq_row['anchor'],
    isset( $faq_id[1] ) ? $faq_id[1] : false );
ck( 'and carries its answer', null !== $faq_row && '' !== $faq_row['body'] && false === strpos( $faq_row['body'], 'Do I need to know how to code?' ), true );

// The handbook's glossary tooltips: the term stays, the hover text goes.
ck( 'plain: a glossary tooltip keeps its term and loses its hover text, a link inside it included',
    WPCPM_Guide_Text::plain( "<p>How did you become a <span tabindex='0' class='glossary-item-container'>mentor<span class='glossary-item-hidden-content'><span class='glossary-item-header'>Event Supporter</span> <span class='glossary-item-description'>Event Supporter (formerly Mentor) is someone who has <a href='https://example.test/'>organised</a> a WordCamp.</span></span></span>?</p>" ),
    'How did you become a mentor?' );
ck( 'plain: double quotes, another class beside it, and spans nested deeper',
    WPCPM_Guide_Text::plain( '<p>Join <span class="glossary-item-container">Slack<span id="g1" class="tip glossary-item-hidden-content wide"><span class="glossary-item-header">Slack</span> <span class="glossary-item-description">Slack is a <span><em>chat</em> <span>platform</span></span>.</span></span></span> today.</p>' ),
    'Join Slack today.' );
ck( 'plain: two tooltips in a line, and an ordinary span, keep the text between them',
    WPCPM_Guide_Text::plain( "<p><span class='glossary-item-container'>A<span class='glossary-item-hidden-content'><span>x</span></span></span> and <span class='note'>kept</span> <span class='glossary-item-container'>B<span class='glossary-item-hidden-content'>y</span></span>.</p>" ),
    'A and kept B.' );
ck( 'plain: a class that only begins with the name is not a tooltip',
    WPCPM_Guide_Text::plain( '<p>A <span class="glossary-item-hidden-content-x">kept</span> word</p>' ),
    'A kept word' );

$fused = array();
foreach ( json_decode( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'bin/fixtures/guides/handbook.json' ), true ) as $hb_page ) {
	foreach ( WPCPM_Guide_Text::passages( (string) $hb_page['content']['rendered'] ) as $row ) {
		foreach ( array( 'mentorEvent', 'SlackSlack', 'formerly Mentor' ) as $needle ) {
			if ( false !== strpos( $row['heading'] . "\n" . $row['body'], $needle ) ) {
				$fused[] = $needle . ' in ' . $hb_page['link'];
			}
		}
	}
}
ck( 'no handbook passage holds "mentorEvent", "SlackSlack" or a tooltip\'s "formerly Mentor"', array_values( array_unique( $fused ) ), array() );

// ---------------------------------------------------------------------------------------------
// Part 2: the index's tables and the ranking.
// ---------------------------------------------------------------------------------------------

define( 'ARRAY_A', 'ARRAY_A' );
define( 'OBJECT', 'OBJECT' );

$GLOBALS['opts']          = array();
$GLOBALS['autoload']      = array();
$GLOBALS['filters']       = array();
$GLOBALS['dbdelta']       = array();
$GLOBALS['dbdelta_fails'] = false;

function __( $text, $domain = null ) { return $text; }
function apply_filters( $tag, $value, ...$args ) {
	return isset( $GLOBALS['filters'][ $tag ] ) ? call_user_func( $GLOBALS['filters'][ $tag ], $value, ...$args ) : $value;
}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $a; return true; }
function add_option( $k, $v, $dep = '', $a = 'yes' ) { if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; } $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { $had = array_key_exists( $k, $GLOBALS['opts'] ); unset( $GLOBALS['opts'][ $k ] ); return $had; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function absint( $n ) { return abs( (int) $n ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function current_time( $type, $gmt = 0 ) { return 'mysql' === $type ? gmdate( 'Y-m-d H:i:s' ) : time(); }
// Core's contract, as far as it matters here: an address on a protocol WordPress does not allow comes back empty.
function esc_url_raw( $url, $protocols = null ) {
	$url = trim( (string) $url );
	return preg_match( '#^https?://#i', $url ) ? $url : '';
}
// dbDelta() as far as the upgrade reads it: the SQL it was given, and the tables made unless told to fail.
function dbDelta( $sql ) {
	$GLOBALS['dbdelta'][] = $sql;
	if ( empty( $GLOBALS['dbdelta_fails'] ) ) {
		$GLOBALS['wpdb']->create_guide_tables();
	}
	return array();
}

require __DIR__ . '/stubs/sqlite-wpdb.php';
$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();

require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-guide-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-guide-search.php';

/** An empty index on a new database, and no options. */
function fresh_index() {
	$GLOBALS['opts']    = array();
	$GLOBALS['filters'] = array();
	$GLOBALS['wpdb']    = new WPCPM_Test_Wpdb();
	$GLOBALS['wpdb']->create_guide_tables();
}

/** A source's meta as write() takes it. */
function meta( $kind, $audience, $post_id, $title, $url, $modified = '2026-11-19 10:00:00' ) {
	return array( 'kind' => $kind, 'audience' => $audience, 'post_id' => $post_id, 'title' => $title, 'url' => $url, 'modified' => $modified );
}

/** A passage as passages() gives one. */
function passage( $heading, $anchor, $body ) {
	return array( 'heading' => $heading, 'anchor' => $anchor, 'body' => $body );
}

/** One SQL answer, read straight from the stand-in, for what the class does not expose. */
function sql_var( $sql ) {
	return $GLOBALS['wpdb']->get_var( $sql );
}

/** The ids of the passages, oldest first. */
function chunk_ids() {
	return array_map( 'intval', $GLOBALS['wpdb']->get_col( 'SELECT id FROM wp_wpcpm_guide_chunks ORDER BY id' ) );
}

/** The term rows that point at no passage. */
function orphan_terms() {
	return (int) sql_var( 'SELECT COUNT(*) FROM wp_wpcpm_guide_terms WHERE chunk_id NOT IN (SELECT id FROM wp_wpcpm_guide_chunks)' );
}

/** BM25 of one passage for a query, worked out here from the formula, with the index's own numbers. */
function bm25_by_hand( array $query_terms, array $tf, $words, $n, $avg, array $df ) {
	$score = 0.0;
	foreach ( array_unique( $query_terms ) as $t ) {
		if ( empty( $tf[ $t ] ) ) {
			continue;
		}
		$idf    = log( 1 + ( $n - $df[ $t ] + 0.5 ) / ( $df[ $t ] + 0.5 ) );
		$score += $idf * ( $tf[ $t ] * 2.2 ) / ( $tf[ $t ] + 1.2 * ( 0.25 + 0.75 * $words / $avg ) );
	}
	return $score;
}

// The schema, as MariaDB reads it through dbDelta() --------------------------------------------

ck( 'the tables are named from the prefix', array( WPCPM_Guide_Index::chunks_table(), WPCPM_Guide_Index::terms_table() ), array( 'wp_wpcpm_guide_chunks', 'wp_wpcpm_guide_terms' ) );
ck( 'the constants', array( WPCPM_Guide_Index::SCHEMA_VERSION, WPCPM_Guide_Index::OPT_VERSION, WPCPM_Guide_Index::OPT_STATE, WPCPM_Guide_Search::K1, WPCPM_Guide_Search::B, WPCPM_Guide_Search::HOME ), array( 1, 'wpcpm_guide_index_version', 'wpcpm_guide_index_state', 1.2, 0.75, 1.25 ) );

$schema     = WPCPM_Guide_Index::schema();
$statements = array_values( array_filter( array_map( 'trim', explode( ';', $schema ) ) ) );
ck( 'the schema is two CREATE TABLEs, as dbDelta() splits them at semicolons',
    array( count( $statements ), strpos( $statements[0], 'CREATE TABLE wp_wpcpm_guide_chunks (' ), strpos( isset( $statements[1] ) ? $statements[1] : '', 'CREATE TABLE wp_wpcpm_guide_terms (' ) ),
    array( 2, 0, 0 ) );
foreach ( array(
	'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,',
	"source varchar(64) NOT NULL DEFAULT '',",
	"kind varchar(20) NOT NULL DEFAULT '',",
	"audience varchar(20) NOT NULL DEFAULT '',",
	'post_id bigint(20) unsigned NOT NULL DEFAULT 0,',
	"title varchar(255) NOT NULL DEFAULT '',",
	"heading varchar(255) NOT NULL DEFAULT '',",
	"url varchar(500) NOT NULL DEFAULT '',",
	'body mediumtext NOT NULL,',
	'words smallint(5) unsigned NOT NULL DEFAULT 0,',
	"hash char(32) NOT NULL DEFAULT '',",
	'indexed_at datetime NOT NULL,',
	'PRIMARY KEY  (id),',
	'KEY source (source),',
	'KEY hash (hash)',
	') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
) as $part ) {
	ck( 'the passages\' table holds ' . $part, false !== strpos( $statements[0], $part ), true );
}
foreach ( array(
	"term varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '',",
	'chunk_id bigint(20) unsigned NOT NULL DEFAULT 0,',
	'tf smallint(5) unsigned NOT NULL DEFAULT 0,',
	'PRIMARY KEY  (term,chunk_id),',
	'KEY chunk_id (chunk_id)',
	') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
) as $part ) {
	ck( 'the terms\' table holds ' . $part, false !== strpos( isset( $statements[1] ) ? $statements[1] : '', $part ), true );
}
ck( 'one column or key a line, as dbDelta() reads them: 12 and 3 lines, then 3 and 2', array( substr_count( $statements[0], "\n" ) - 1, substr_count( isset( $statements[1] ) ? $statements[1] : '', "\n" ) - 1 ), array( 15, 5 ) );

// The upgrade ----------------------------------------------------------------------------------

ck( 'without the tables, exists() says so', WPCPM_Guide_Index::exists(), false );
ck( 'and the index reads as empty, not as an error', array( WPCPM_Guide_Index::count(), WPCPM_Guide_Search::rank( array( 'book' ), array( 'public' ) ) ), array( 0, array() ) );
$GLOBALS['dbdelta_fails'] = true;
WPCPM_Guide_Index::maybe_upgrade();
ck( 'a failed creation leaves the version unset, so the next load tries again', array( count( $GLOBALS['dbdelta'] ), get_option( WPCPM_Guide_Index::OPT_VERSION, 0 ) ), array( 1, 0 ) );
$GLOBALS['dbdelta_fails'] = false;
WPCPM_Guide_Index::maybe_upgrade();
ck( 'the upgrade gives dbDelta() the schema, makes both tables and stamps the version',
    array( $GLOBALS['dbdelta'][1] === WPCPM_Guide_Index::schema(), WPCPM_Guide_Index::exists(), get_option( WPCPM_Guide_Index::OPT_VERSION ) ),
    array( true, true, 1 ) );
WPCPM_Guide_Index::maybe_upgrade();
ck( 'a second load does nothing more', count( $GLOBALS['dbdelta'] ), 2 );
$GLOBALS['wpdb']->query( 'DROP TABLE wp_wpcpm_guide_terms' );
ck( 'one table missing is no index', WPCPM_Guide_Index::exists(), false );

// Writing a source -----------------------------------------------------------------------------

fresh_index();
$mentor_meta = meta( 'guide', 'mentor', 559, 'Mentor guide', 'https://example.test/mentor-guide/' );
$written     = WPCPM_Guide_Index::write( 'page:559', $mentor_meta, array(
	passage( 'Booking calls', 'booking-calls', 'Students book a call from your calendar.' ),
	passage( 'Booking calls > Group sessions', 'group-sessions', 'A group session is booked like any other call.' ),
	passage( '', '', 'Welcome to the guide.' ),
) );
ck( 'write() returns the passages written', $written, 3 );
ck( 'and count() reads them', WPCPM_Guide_Index::count(), 3 );
$sources = WPCPM_Guide_Index::sources();
ck( 'the state names the source', array_keys( $sources ), array( 'page:559' ) );
ck( 'with its kind, audience, post, title, modified time and passages',
    array_intersect_key( $sources['page:559'], array_flip( array( 'kind', 'audience', 'post_id', 'title', 'modified', 'passages' ) ) ),
    array( 'kind' => 'guide', 'audience' => 'mentor', 'post_id' => 559, 'title' => 'Mentor guide', 'modified' => '2026-11-19 10:00:00', 'passages' => 3 ) );
ck( 'and when it was indexed, as Unix time', is_int( $sources['page:559']['indexed_at'] ) && abs( time() - $sources['page:559']['indexed_at'] ) < 60, true );
ck( 'the state is not autoloaded', $GLOBALS['autoload'][ WPCPM_Guide_Index::OPT_STATE ], false );
ck( 'state() holds the sources', WPCPM_Guide_Index::state()['sources'], $sources );

$ids  = chunk_ids();
$rows = WPCPM_Guide_Index::rows( array( $ids[2], $ids[0] ) );
ck( 'rows() gives whole rows in the order asked', array_column( $rows, 'id' ), array( $ids[2], $ids[0] ) );
ck( 'a row is the passage with its source\'s meta',
    array_intersect_key( $rows[1], array_flip( array( 'source', 'kind', 'audience', 'post_id', 'title', 'heading', 'body' ) ) ),
    array( 'source' => 'page:559', 'kind' => 'guide', 'audience' => 'mentor', 'post_id' => 559, 'title' => 'Mentor guide', 'heading' => 'Booking calls', 'body' => 'Students book a call from your calendar.' ) );
ck( 'its address is the page\'s with the section\'s anchor', $rows[1]['url'], 'https://example.test/mentor-guide/#booking-calls' );
ck( 'a passage with no anchor links to the page', $rows[0]['url'], 'https://example.test/mentor-guide/' );
ck( 'the hash is md5 of the body', $rows[1]['hash'], md5( 'Students book a call from your calendar.' ) );
ck( 'words is the passage\'s length in terms, its title and heading counted as their terms are', $rows[1]['words'], array_sum( WPCPM_Guide_Text::counts( 'Mentor guide > Booking calls', 'Students book a call from your calendar.' ) ) );
ck( 'indexed_at is a datetime', 1 === preg_match( '/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $rows[1]['indexed_at'] ), true );
ck( 'rows() of nothing is nothing, and an unknown id is left out', array( WPCPM_Guide_Index::rows( array() ), count( WPCPM_Guide_Index::rows( array( 999, $ids[1] ) ) ) ), array( array(), 1 ) );

$stored = $GLOBALS['wpdb']->get_results( $GLOBALS['wpdb']->prepare( 'SELECT term, tf FROM wp_wpcpm_guide_terms WHERE chunk_id = %d', $ids[0] ), ARRAY_A );
$stored = array_combine( array_column( $stored, 'term' ), array_map( 'intval', array_column( $stored, 'tf' ) ) );
$want   = WPCPM_Guide_Text::counts( 'Mentor guide > Booking calls', 'Students book a call from your calendar.' );
ksort( $stored );
ksort( $want );
ck( 'a passage\'s terms are counts() of its page title with its heading, and its body', $stored, $want );
ck( 'so the title\'s terms count twice, as the heading\'s do (Ruling T2-1)', array( $stored['guid'], $stored['mentor'], $stored['book'] ), array( 2, 2, 3 ) );
ck( 'while the stored heading stays the section\'s path', $rows[1]['heading'], 'Booking calls' );
$untitled = $GLOBALS['wpdb']->get_results( $GLOBALS['wpdb']->prepare( 'SELECT term, tf FROM wp_wpcpm_guide_terms WHERE chunk_id = %d', $ids[2] ), ARRAY_A );
$untitled = array_combine( array_column( $untitled, 'term' ), array_map( 'intval', array_column( $untitled, 'tf' ) ) );
ksort( $untitled );
$want = WPCPM_Guide_Text::counts( 'Mentor guide', 'Welcome to the guide.' );
ksort( $want );
ck( 'a passage with no heading carries its title\'s terms twice', array( $untitled, $untitled['mentor'], $rows[0]['heading'] ), array( $want, 2, '' ) );

ck( 'df() counts passages per term over the index, 0 for a term it lacks', WPCPM_Guide_Index::df( array( 'book', 'group', 'nowher' ) ), array( 'book' => 2, 'group' => 1, 'nowher' => 0 ) );
ck( 'df() of nothing is nothing', WPCPM_Guide_Index::df( array() ), array() );

WPCPM_Guide_Index::write( 'page:558', meta( 'guide', 'student', 558, 'Student guide', 'https://example.test/student-guide/' ), array(
	passage( 'Booking a call', 'booking-a-call', 'Book a call with your mentor.' ),
) );
ck( 'df() reads every audience', WPCPM_Guide_Index::df( array( 'book' ) ), array( 'book' => 3 ) );
$postings = WPCPM_Guide_Index::postings( array( 'book', 'call' ), array( 'student' ) );
ck( 'postings() reads only the audiences asked', array_values( array_unique( array_column( $postings, 'audience' ) ) ), array( 'student' ) );
ck( 'one row a term of a passage', array_column( $postings, 'term' ), array( 'book', 'call' ) );
ck( 'a posting carries term, passage, tf, length, source, kind, audience and hash',
    array_keys( $postings[0] ), array( 'term', 'chunk_id', 'tf', 'words', 'source', 'kind', 'audience', 'hash' ) );
ck( 'with its numbers as numbers', array( is_int( $postings[0]['chunk_id'] ), $postings[0]['tf'], is_int( $postings[0]['words'] ) ), array( true, 3, true ) );
ck( 'postings() of no term or no audience is nothing', array( WPCPM_Guide_Index::postings( array(), array( 'student' ) ), WPCPM_Guide_Index::postings( array( 'book' ), array() ) ), array( array(), array() ) );

$lengths = array_map( 'intval', $GLOBALS['wpdb']->get_col( 'SELECT words FROM wp_wpcpm_guide_chunks' ) );
$stats   = WPCPM_Guide_Index::stats();
ck( 'stats(): the passages and their average length', array( $stats['count'], round( $stats['avg'], 6 ), is_float( $stats['avg'] ) ), array( 4, round( array_sum( $lengths ) / 4, 6 ), true ) );

// Writing a source again replaces it.
$terms_before = (int) sql_var( 'SELECT COUNT(*) FROM wp_wpcpm_guide_terms' );
$again        = WPCPM_Guide_Index::write( 'page:559', meta( 'guide', 'mentor', 559, 'Mentor guide', 'https://example.test/mentor-guide/', '2026-11-20 08:00:00' ), array(
	passage( 'Booking calls', 'booking-calls', 'Students book a call from the Calls screen.' ),
	passage( 'Booking calls > Group sessions', 'group-sessions', 'A group session is booked like any other call.' ),
	passage( '', '', 'Welcome to the guide.' ),
) );
ck( 'writing a source again replaces it: the count is unchanged', array( $again, WPCPM_Guide_Index::count() ), array( 3, 4 ) );
ck( 'the new body is there', (int) sql_var( "SELECT COUNT(*) FROM wp_wpcpm_guide_chunks WHERE body = 'Students book a call from the Calls screen.'" ), 1 );
ck( 'the old body is gone', (int) sql_var( "SELECT COUNT(*) FROM wp_wpcpm_guide_chunks WHERE body = 'Students book a call from your calendar.'" ), 0 );
ck( 'and so are its terms', array( orphan_terms(), WPCPM_Guide_Index::df( array( 'calendar', 'screen' ) ) ), array( 0, array( 'calendar' => 0, 'screen' => 1 ) ) );
ck( 'the state has the new modified time', WPCPM_Guide_Index::sources()['page:559']['modified'], '2026-11-20 08:00:00' );
ck( 'the other source is untouched', array( (int) sql_var( "SELECT COUNT(*) FROM wp_wpcpm_guide_chunks WHERE source = 'page:558'" ), array_keys( WPCPM_Guide_Index::sources() ) ), array( 1, array( 'page:559', 'page:558' ) ) );
ck( 'the ranking finds the new body', array_column( WPCPM_Guide_Search::rank( array( 'calls screen' ), array( 'mentor' ) ), 'body' ), array( 'Students book a call from the Calls screen.', 'A group session is booked like any other call.' ) );

// remove().
WPCPM_Guide_Index::remove( 'page:559' );
ck( 'remove() takes the source\'s passages away', WPCPM_Guide_Index::count(), 1 );
ck( 'and their terms', array( orphan_terms(), (int) sql_var( 'SELECT COUNT(*) FROM wp_wpcpm_guide_terms' ) ), array( 0, count( WPCPM_Guide_Text::counts( 'Student guide > Booking a call', 'Book a call with your mentor.' ) ) ) );
ck( 'and its entry in the state', array_keys( WPCPM_Guide_Index::sources() ), array( 'page:558' ) );
WPCPM_Guide_Index::remove( 'page:404' );
ck( 'removing a source the index lacks changes nothing', WPCPM_Guide_Index::count(), 1 );

// Ruling 7: the address is the link an answer gives.
$bad = WPCPM_Guide_Index::write( 'page:9', meta( 'guide', 'student', 9, 'A page', 'javascript:alert(1)' ), array( passage( 'A', 'a', 'Some text here.' ) ) );
ck( 'a passage whose address esc_url_raw() empties is not written (Ruling 7)', array( $bad, WPCPM_Guide_Index::count(), WPCPM_Guide_Index::sources()['page:9']['passages'] ), array( 0, 1, 0 ) );
$long = WPCPM_Guide_Index::write( 'page:10', meta( 'guide', 'student', 10, 'A page', 'https://example.test/' . str_repeat( 'a', 480 ) . '/' ), array( passage( 'A', 'an-anchor-past-the-column', 'Some text here.' ) ) );
ck( 'nor one whose address is longer than its column, where it would be cut into another link', array( $long, WPCPM_Guide_Index::count() ), array( 0, 1 ) );
ck( 'nor a passage with no text', WPCPM_Guide_Index::write( 'page:11', meta( 'guide', 'student', 11, 'A page', 'https://example.test/p/' ), array( passage( 'A', 'a', "  \n " ) ) ), 0 );

// Long terms, long headings, many terms.
fresh_index();
$digits = str_repeat( '1', 45 );
WPCPM_Guide_Index::write( 'page:1', meta( 'guide', 'student', 1, str_repeat( 'T', 300 ), 'https://example.test/one/' ), array(
	passage( str_repeat( 'Heading ', 40 ), 'h', $digits . 'a ' . $digits . 'b' ),
) );
$cut = $GLOBALS['wpdb']->get_results( 'SELECT term, tf FROM wp_wpcpm_guide_terms WHERE term LIKE \'1%\'', ARRAY_A );
ck( 'terms are cut to the column\'s 40 characters and merged after the cut', array( count( $cut ), $cut[0]['term'], (int) $cut[0]['tf'] ), array( 1, str_repeat( '1', 40 ), 2 ) );
ck( 'and a query term is cut the same way', count( WPCPM_Guide_Search::rank( array( $digits . 'z' ), array( 'student' ) ) ), 1 );
$row = WPCPM_Guide_Index::rows( chunk_ids() )[0];
ck( 'a heading and a title are cut to their columns', array( mb_strlen( $row['heading'] ), mb_strlen( $row['title'] ) ), array( 255, 255 ) );

$many = array();
for ( $i = 0; $i < 1200; $i++ ) {
	$many[] = 'w' . $i;
}
$before = count( $GLOBALS['wpdb']->queries );
WPCPM_Guide_Index::write( 'page:2', meta( 'guide', 'student', 2, 'Many', 'https://example.test/many/' ), array( passage( '', '', implode( ' ', $many ) ) ) );
$inserts = preg_grep( '/^INSERT INTO wp_wpcpm_guide_terms\b/', array_slice( $GLOBALS['wpdb']->queries, $before ) );
ck( 'a passage of 1,200 terms is written 500 term rows a statement', array( count( $inserts ), (int) sql_var( 'SELECT COUNT(*) FROM wp_wpcpm_guide_terms WHERE term LIKE \'w%\'' ) ), array( 3, 1200 ) );

// The ranking ----------------------------------------------------------------------------------

ck( 'the weights: guides and the FAQ 1.0, the handbook 0.8', array( WPCPM_Guide_Search::weight( 'guide' ), WPCPM_Guide_Search::weight( 'faq' ), WPCPM_Guide_Search::weight( 'handbook' ) ), array( 1.0, 1.0, 0.8 ) );
$GLOBALS['filters']['wpcpm_guide_weight'] = function ( $weight, $kind ) {
	return 'handbook' === $kind ? 0.5 : $weight;
};
ck( 'and a filter can change one', array( WPCPM_Guide_Search::weight( 'handbook' ), WPCPM_Guide_Search::weight( 'guide' ) ), array( 0.5, 1.0 ) );

// Review Focus 1: nothing above the asker's level.
fresh_index();
WPCPM_Guide_Index::write( 'page:559', meta( 'guide', 'mentor', 559, 'Mentor guide', 'https://example.test/mentor-guide/' ), array(
	passage( 'Setting your availability', 'availability', 'Set the hours students can book calls with you.' ),
) );
ck( 'a reader of student and public passages is given nothing from a corpus of one Mentor guide passage (Review Focus 1)',
    WPCPM_Guide_Search::rank( array( 'set availability calls hours', 'How do I set my availability for calls?' ), array( 'public', 'student' ), 'student', 5 ), array() );
ck( 'while a mentor is given it', array_column( WPCPM_Guide_Search::rank( array( 'set availability calls hours' ), array( 'public', 'mentor' ), 'mentor' ), 'audience' ), array( 'mentor' ) );
ck( 'no words, or words of stop words alone, or no audience: nothing',
    array( WPCPM_Guide_Search::rank( array(), array( 'mentor' ) ), WPCPM_Guide_Search::rank( array( 'how do I', '' ), array( 'mentor' ) ), WPCPM_Guide_Search::rank( array( 'availability' ), array() ), WPCPM_Guide_Search::rank( array( 'availability' ), array( 'mentor' ), '', 0 ) ),
    array( array(), array(), array(), array() ) );

$result = WPCPM_Guide_Search::rank( array( 'availability' ), array( 'mentor' ) );
ck( 'a result carries id, source, kind, audience, title, heading, address, text and score',
    array_keys( $result[0] ), array( 'id', 'source', 'kind', 'audience', 'title', 'heading', 'url', 'body', 'score' ) );
ck( 'with the passage\'s own values',
    array( is_int( $result[0]['id'] ), $result[0]['source'], $result[0]['kind'], $result[0]['title'], $result[0]['heading'], $result[0]['url'], $result[0]['body'] ),
    array( true, 'page:559', 'guide', 'Mentor guide', 'Setting your availability', 'https://example.test/mentor-guide/#availability', 'Set the hours students can book calls with you.' ) );
ck( 'and a score that is a float of four places', array( is_float( $result[0]['score'] ), $result[0]['score'] === round( $result[0]['score'], 4 ), $result[0]['score'] > 0 ), array( true, true, true ) );

// BM25 by hand.
fresh_index();
$corpus = array(
	array( 'page:558', 'student', passage( 'Booking a call', 'booking-a-call', 'Book a call with your mentor from the Calls screen.' ) ),
	array( 'page:558', 'student', passage( 'Booking a call > Group sessions', 'group-sessions', 'A group session is one call for several students, booked like any other.' ) ),
	array( 'page:559', 'mentor', passage( 'Your availability', 'availability', 'Students book the hours you set. Set them each week, and book a call yourself if a student asks.' ) ),
	array( 'page:559', 'mentor', passage( 'Notes', 'notes', 'Write a note after each call.' ) ),
	array( 'page:2746', 'public', passage( 'Before you apply > Does it cost anything?', 'does-it-cost', 'No. The program is free.' ) ),
);
$by_source = array();
foreach ( $corpus as $c ) {
	$by_source[ $c[0] ]['audience']   = $c[1];
	$by_source[ $c[0] ]['passages'][] = $c[2];
}
foreach ( $by_source as $source => $s ) {
	WPCPM_Guide_Index::write( $source, meta( 'page:2746' === $source ? 'faq' : 'guide', $s['audience'], (int) substr( $source, 5 ), 'A page', 'https://example.test/' . substr( $source, 5 ) . '/' ), $s['passages'] );
}
$all_counts = array();
foreach ( $corpus as $c ) {
	$all_counts[] = WPCPM_Guide_Text::counts( 'A page > ' . $c[2]['heading'], $c[2]['body'] );
}
$lengths = array_map( 'array_sum', $all_counts );
$avg     = array_sum( $lengths ) / count( $lengths );
$query   = WPCPM_Guide_Text::terms( 'book a call, book it' );
$df      = array();
foreach ( array_unique( $query ) as $t ) {
	$df[ $t ] = count( array_filter( $all_counts, function ( $c ) use ( $t ) { return isset( $c[ $t ] ); } ) );
}
$expect = array();
foreach ( $all_counts as $i => $c ) {
	$s = bm25_by_hand( $query, $c, $lengths[ $i ], count( $corpus ), $avg, $df );
	if ( $s > 0 ) {
		$expect[ $corpus[ $i ][2]['body'] ] = round( $s, 4 );
	}
}
arsort( $expect );
$got = array();
foreach ( WPCPM_Guide_Search::rank( array( 'book a call, book it' ), array( 'public', 'student', 'mentor' ) ) as $r ) {
	$got[ $r['body'] ] = $r['score'];
}
ck( 'BM25 (k1 1.2, b 0.75) scores and orders the passages as the formula worked by hand does', $got, $expect );

// The idf is the whole index's, not the reader's share of it.
$got = WPCPM_Guide_Search::rank( array( 'book call' ), array( 'student' ) );
ck( 'the idf and the average length are the whole index\'s, whatever the reader may read',
    array_column( $got, 'score' ),
    array_values( array_intersect_key( $expect, array_flip( array( $corpus[0][2]['body'], $corpus[1][2]['body'] ) ) ) ) );
ck( 'a term repeated, in one string or across the words and the question, counts once',
    array( WPCPM_Guide_Search::rank( array( 'book book book call' ), array( 'student' ) ), WPCPM_Guide_Search::rank( array( 'book call', 'How do I book a call?' ), array( 'student' ) ) ),
    array( $got, $got ) );
ck( 'at most $limit results', array( count( WPCPM_Guide_Search::rank( array( 'book call' ), array( 'public', 'student', 'mentor' ), '', 2 ) ), count( WPCPM_Guide_Search::rank( array( 'book call' ), array( 'public', 'student', 'mentor' ) ) ) ), array( 2, 4 ) );

// The home guide's 1.25, between two passages that score alike.
fresh_index();
WPCPM_Guide_Index::write( 'page:558', meta( 'guide', 'student', 558, 'Student guide', 'https://example.test/student-guide/' ), array( passage( 'Calls', 'calls', 'Book a call today.' ) ) );
WPCPM_Guide_Index::write( 'page:559', meta( 'guide', 'mentor', 559, 'Mentor guide', 'https://example.test/mentor-guide/' ), array( passage( 'Calls', 'calls', 'Book a call early.' ) ) );
$both = array( 'public', 'student', 'mentor' );
$tie  = WPCPM_Guide_Search::rank( array( 'book call' ), $both );
ck( 'two passages that score alike, with no home guide: the lower id first', array( array_column( $tie, 'audience' ), $tie[0]['score'] === $tie[1]['score'] ), array( array( 'student', 'mentor' ), true ) );
$home = WPCPM_Guide_Search::rank( array( 'book call' ), $both, 'mentor' );
ck( 'the home guide\'s 1.25 puts the asker\'s guide first', array( array_column( $home, 'audience' ), round( $home[0]['score'] / $home[1]['score'], 2 ), $home[1]['score'] ), array( array( 'mentor', 'student' ), 1.25, $tie[0]['score'] ) );
ck( 'whichever guide is home', array_column( WPCPM_Guide_Search::rank( array( 'book call' ), $both, 'student' ), 'audience' ), array( 'student', 'mentor' ) );

// One hash, one result: the home guide's.
fresh_index();
WPCPM_Guide_Index::write( 'page:558', meta( 'guide', 'student', 558, 'Student guide', 'https://example.test/student-guide/' ), array( passage( 'Calls', 'calls', 'Book a call with your mentor.' ) ) );
WPCPM_Guide_Index::write( 'page:559', meta( 'guide', 'mentor', 559, 'Mentor guide', 'https://example.test/mentor-guide/' ), array( passage( 'Calls', 'calls', 'Book a call with your mentor.' ) ) );
ck( 'two passages with one hash give one result, the home guide\'s', array_column( WPCPM_Guide_Search::rank( array( 'book call' ), $both, 'mentor' ), 'audience' ), array( 'mentor' ) );
ck( 'the other way round too', array_column( WPCPM_Guide_Search::rank( array( 'book call' ), $both, 'student' ), 'audience' ), array( 'student' ) );
ck( 'and with no home guide, the lower id', array_column( WPCPM_Guide_Search::rank( array( 'book call' ), $both ), 'audience' ), array( 'student' ) );
ck( 'a reader of one of the two is given that one', array_column( WPCPM_Guide_Search::rank( array( 'book call' ), array( 'public', 'mentor' ), 'mentor' ), 'audience' ), array( 'mentor' ) );

// The handbook's 0.8, at equal BM25.
fresh_index();
WPCPM_Guide_Index::write( 'handbook:96111', meta( 'handbook', 'public', 0, 'Student guide', 'https://make.wordpress.org/community/handbook/education/credits/student-guide/' ), array( passage( 'Reports', '', 'Send your report each month.' ) ) );
WPCPM_Guide_Index::write( 'page:2746', meta( 'faq', 'public', 2746, 'Common questions', 'https://example.test/faq/' ), array( passage( 'Reports', 'reports', 'Send your report each week.' ) ) );
$weighed = WPCPM_Guide_Search::rank( array( 'send report' ), array( 'public' ) );
ck( 'the handbook\'s 0.8 puts the site\'s passage first at equal BM25', array( array_column( $weighed, 'kind' ), round( $weighed[1]['score'] / $weighed[0]['score'], 2 ) ), array( array( 'faq', 'handbook' ), 0.8 ) );
ck( 'a handbook passage links to its page', $weighed[1]['url'], 'https://make.wordpress.org/community/handbook/education/credits/student-guide/' );
$GLOBALS['filters']['wpcpm_guide_weight'] = function ( $weight ) {
	return 1.0;
};
ck( 'it is the weight that does it: weighed alike, the lower id comes first', array_column( WPCPM_Guide_Search::rank( array( 'send report' ), array( 'public' ) ), 'kind' ), array( 'handbook', 'faq' ) );

// mark_built() and drop().
WPCPM_Guide_Index::mark_built( '1.123.101' );
ck( 'mark_built() records the version a whole rebuild ran under, and keeps the sources',
    array( WPCPM_Guide_Index::state()['built_for'], array_keys( WPCPM_Guide_Index::sources() ), $GLOBALS['autoload'][ WPCPM_Guide_Index::OPT_STATE ] ),
    array( '1.123.101', array( 'handbook:96111', 'page:2746' ), false ) );
update_option( WPCPM_Guide_Index::OPT_VERSION, 1 );
WPCPM_Guide_Index::drop();
ck( 'drop() takes both tables away, and the schema version and the state with them',
    array( WPCPM_Guide_Index::exists(), get_option( WPCPM_Guide_Index::OPT_VERSION, 'gone' ), get_option( WPCPM_Guide_Index::OPT_STATE, 'gone' ), WPCPM_Guide_Index::count() ),
    array( false, 'gone', 'gone', 0 ) );

// A write that fails is recorded as one, so the next run writes it again.
$failed_write = 'The database refused a write. The next run writes this source again.';
$student_page = meta( 'guide', 'student', 558, 'Student guide', 'https://example.test/student-guide/' );

fresh_index();
$GLOBALS['wpdb']->query( 'DROP TABLE wp_wpcpm_guide_terms' );
$n     = WPCPM_Guide_Index::write( 'page:558', $student_page, array( passage( 'Calls', 'calls', 'Book a call.' ) ) );
$entry = WPCPM_Guide_Index::sources()['page:558'];
ck( 'with the terms\' table gone, write() writes nothing and leaves no passage without its terms', array( $n, WPCPM_Guide_Index::count() ), array( 0, 0 ) );
ck( 'and the state does not carry the page\'s modified time but an error, so the next run writes it again',
    array( $entry['modified'], isset( $entry['error'] ) ? $entry['error'] : null, $entry['passages'] ),
    array( '', $failed_write, 0 ) );
$GLOBALS['wpdb']->create_guide_tables();
$n     = WPCPM_Guide_Index::write( 'page:558', $student_page, array( passage( 'Calls', 'calls', 'Book a call.' ) ) );
$entry = WPCPM_Guide_Index::sources()['page:558'];
ck( 'once the table is back, the next write writes it and clears the error', array( $n, $entry['modified'], isset( $entry['error'] ) ), array( 1, '2026-11-19 10:00:00', false ) );

fresh_index();
WPCPM_Guide_Index::write( 'page:558', $student_page, array( passage( 'Calls', 'calls', 'Book a call.' ), passage( 'Notes', 'notes', 'Write a note.' ) ) );
$GLOBALS['wpdb']->query( 'DROP TABLE wp_wpcpm_guide_terms' );
$n     = WPCPM_Guide_Index::write( 'page:558', $student_page, array( passage( 'Calls', 'calls', 'Book a call from the Calls screen.' ) ) );
$entry = WPCPM_Guide_Index::sources()['page:558'];
ck( 'when the old rows cannot be deleted, nothing new is written beside them',
    array( $n, (int) sql_var( "SELECT COUNT(*) FROM wp_wpcpm_guide_chunks WHERE source = 'page:558'" ), (int) sql_var( "SELECT COUNT(*) FROM wp_wpcpm_guide_chunks WHERE body = 'Book a call from the Calls screen.'" ) ),
    array( 0, 2, 0 ) );
ck( 'and the source is marked for the next run', array( $entry['modified'], isset( $entry['error'] ) ? $entry['error'] : null ), array( '', $failed_write ) );

fresh_index();
WPCPM_Guide_Index::write( 'page:558', $student_page, array( passage( 'Calls', 'calls', 'Book a call.' ) ) );
$GLOBALS['wpdb']->query( 'DROP TABLE wp_wpcpm_guide_terms' );
WPCPM_Guide_Index::remove( 'page:558' );
$entry = isset( WPCPM_Guide_Index::sources()['page:558'] ) ? WPCPM_Guide_Index::sources()['page:558'] : array();
ck( 'a remove() the database refuses keeps the source, marked, while its passage is still there',
    array( WPCPM_Guide_Index::count(), isset( $entry['modified'] ) ? $entry['modified'] : null, isset( $entry['error'] ) ? $entry['error'] : null, isset( $entry['kind'] ) ? $entry['kind'] : null ),
    array( 1, '', $failed_write, 'guide' ) );

$GLOBALS['opts'] = array();
$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();
$n               = WPCPM_Guide_Index::write( 'page:558', $student_page, array( passage( 'Calls', 'calls', 'Book a call.' ) ) );
$entry           = WPCPM_Guide_Index::sources()['page:558'];
ck( 'with no tables at all, the source is recorded as failed, not as indexed with no passages',
    array( $n, $entry['modified'], isset( $entry['error'] ) ? $entry['error'] : null ),
    array( 0, '', $failed_write ) );

fresh_index();
$before = count( $GLOBALS['wpdb']->queries );
$long   = array(
	WPCPM_Guide_Index::write( str_repeat( 's', 65 ), $student_page, array( passage( 'Calls', 'calls', 'Book a call.' ) ) ),
	WPCPM_Guide_Index::write( 'page:1', meta( str_repeat( 'k', 21 ), 'student', 1, 'A page', 'https://example.test/a/' ), array( passage( 'Calls', 'calls', 'Book a call.' ) ) ),
	WPCPM_Guide_Index::write( 'page:2', meta( 'guide', str_repeat( 'a', 21 ), 2, 'A page', 'https://example.test/b/' ), array( passage( 'Calls', 'calls', 'Book a call.' ) ) ),
);
ck( 'a source key over 64 characters, or a kind or an audience over 20, is refused before any SQL', array( $long, count( $GLOBALS['wpdb']->queries ) - $before ), array( array( 0, 0, 0 ), 0 ) );
$entry = WPCPM_Guide_Index::sources()['page:2'];
ck( 'and recorded as a failure, with its own sentence', array( $entry['modified'], $entry['error'], WPCPM_Guide_Index::sources()[ str_repeat( 's', 65 ) ]['error'] ), array( '', 'The source\'s key, kind or audience is too long for the index.', 'The source\'s key, kind or audience is too long for the index.' ) );
ck( 'while a key of 64 and a kind and an audience of 20 are written',
    WPCPM_Guide_Index::write( str_repeat( 's', 64 ), meta( str_repeat( 'k', 20 ), str_repeat( 'a', 20 ), 3, 'A page', 'https://example.test/c/' ), array( passage( 'Calls', 'calls', 'Book a call.' ) ) ),
    1 );

// ---------------------------------------------------------------------------------------------
// Part 3: the sources and every trigger that reindexes.
// ---------------------------------------------------------------------------------------------

define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'WPCPM_VERSION', '1.123.101' );

class WP_Post {
	public $uri, $ID = 0, $post_type = 'page', $post_status = 'publish', $post_title = '', $post_name = '', $post_content = '', $post_modified_gmt = '2026-11-19 09:00:00', $post_password = '', $post_author = 0;
}
class WP_User {
	public $ID = 0;
	public function __construct( $id = 0 ) { $this->ID = $id; }
	public function exists() { return $this->ID > 0; }
}
class WP_Error {
	public function __construct( $code = '', $message = '' ) {}
}

$GLOBALS['posts']       = array();
$GLOBALS['levels']      = array();
$GLOBALS['caps']        = array();
$GLOBALS['revisions']   = array();
$GLOBALS['autosaves']   = array();
$GLOBALS['uris']        = array();
$GLOBALS['cron']        = array();
$GLOBALS['locks']       = array();
$GLOBALS['hooks']       = array();
$GLOBALS['remote']      = null;
$GLOBALS['remote_urls'] = array();
$GLOBALS['current']     = new WP_User( 0 );

function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][] = array( $hook, $callback, $priority, $args ); }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function get_post( $p = null ) { return $p instanceof WP_Post ? $p : ( isset( $GLOBALS['posts'][ $p ] ) ? $GLOBALS['posts'][ $p ] : null ); }
function get_post_meta( $id, $key, $single = false ) { return isset( $GLOBALS['levels'][ $id ] ) ? $GLOBALS['levels'][ $id ] : ''; }
function get_page_by_path( $path, $output = OBJECT, $type = 'page' ) {
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( 'page' === $post->post_type && get_page_uri( $post ) === $path ) {
			return $post;
		}
	}
	return null;
}
function get_page_uri( $post ) { return isset( $post->uri ) ? $post->uri : ( isset( $GLOBALS['uris'][ $post->ID ] ) ? $GLOBALS['uris'][ $post->ID ] : $post->post_name ); }
function get_permalink( $post ) { return 'https://example.test/' . get_page_uri( $post ) . '/'; }
function user_can( $user, $cap ) { return in_array( $cap, isset( $GLOBALS['caps'][ $user ] ) ? $GLOBALS['caps'][ $user ] : array(), true ); }
function get_user_by( $field, $id ) { return new WP_User( (int) $id ); }
function wp_get_current_user() { return $GLOBALS['current']; }
function wp_is_post_revision( $id ) { return ! empty( $GLOBALS['revisions'][ $id ] ); }
function wp_is_post_autosave( $id ) { return ! empty( $GLOBALS['autosaves'][ $id ] ); }
function wp_next_scheduled( $hook ) {
	foreach ( $GLOBALS['cron'] as $event ) {
		if ( $event['hook'] === $hook ) {
			return $event['time'];
		}
	}
	return false;
}
function wp_schedule_event( $time, $recurrence, $hook ) { $GLOBALS['cron'][] = array( 'hook' => $hook, 'time' => $time, 'recurrence' => $recurrence ); return true; }
function wp_schedule_single_event( $time, $hook ) { $GLOBALS['cron'][] = array( 'hook' => $hook, 'time' => $time, 'recurrence' => '' ); return true; }
function wp_clear_scheduled_hook( $hook ) {
	$GLOBALS['cron'] = array_values( array_filter( $GLOBALS['cron'], function ( $e ) use ( $hook ) { return $e['hook'] !== $hook; } ) );
}
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['remote_urls'][] = array( $url, $args );
	return $GLOBALS['remote'];
}
function wp_remote_retrieve_response_code( $r ) { return isset( $r['response']['code'] ) ? $r['response']['code'] : ''; }
function wp_remote_retrieve_body( $r ) { return isset( $r['body'] ) ? $r['body'] : ''; }
function wp_remote_retrieve_header( $r, $header ) { return isset( $r['headers'][ $header ] ) ? $r['headers'][ $header ] : ''; }

/** The lock as WPCPM_Lock gives it, over an array instead of the options table. */
class WPCPM_Lock {
	public static function take( $name, $ttl ) {
		if ( isset( $GLOBALS['locks'][ $name ] ) ) {
			return '';
		}
		$GLOBALS['locks'][ $name ] = 'tok' . count( $GLOBALS['locks'] ) . mt_rand();
		return $GLOBALS['locks'][ $name ];
	}
	public static function extend( $name, $token, $ttl ) {
		return ( isset( $GLOBALS['locks'][ $name ] ) && $GLOBALS['locks'][ $name ] === $token ) ? $token : '';
	}
	public static function release( $name, $token ) {
		if ( isset( $GLOBALS['locks'][ $name ] ) && $GLOBALS['locks'][ $name ] === $token ) {
			unset( $GLOBALS['locks'][ $name ] );
			return true;
		}
		return false;
	}
}

// The plugin's own access rules, not stand-ins of them.
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-content-access.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-guide-sources.php';

$hb_json = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'bin/fixtures/guides/handbook.json' );

/** A page of the stand-in site. */
function mk_page( $id, $name, $level, $body, $extra = array() ) {
	$page                = new WP_Post();
	$page->ID            = $id;
	$page->post_name     = $name;
	$page->post_title    = ucfirst( str_replace( '-', ' ', $name ) );
	$page->post_content  = '<h2 id="h' . $id . '">Section ' . $id . '</h2><p>' . $body . '</p>';
	foreach ( $extra as $k => $v ) {
		$page->$k = $v;
	}
	$GLOBALS['posts'][ $id ]  = $page;
	$GLOBALS['levels'][ $id ] = $level;
	return $page;
}

/** An empty index, the six pages and a few people. Ids: the pages 101 to 106 and 107 (another page); the people 11 to 15. */
function world() {
	fresh_index();
	$GLOBALS['posts']       = array();
	$GLOBALS['levels']      = array();
	$GLOBALS['revisions']   = array();
	$GLOBALS['autosaves']   = array();
	$GLOBALS['uris']        = array();
	$GLOBALS['cron']        = array();
	$GLOBALS['locks']       = array();
	$GLOBALS['hooks']       = array();
	$GLOBALS['remote_urls'] = array();
	$GLOBALS['remote']      = array( 'response' => array( 'code' => 200 ), 'body' => $GLOBALS['hb_json'] );
	$GLOBALS['current']     = new WP_User( 0 );
	$GLOBALS['caps']        = array(
		11 => array( WPCPM_Roles::CAP_VIEW_STUDENT ),
		12 => array( WPCPM_Roles::CAP_VIEW_MENTOR ),
		13 => array( WPCPM_Roles::CAP_VIEW_INSTITUTION ),
		14 => array( WPCPM_Roles::CAP_VIEW_SPONSOR ),
		15 => array( WPCPM_Roles::CAP_MANAGE ),
	);
	mk_page( 101, 'student-guide', 'wpcpm_student', 'Students book a calendar call.' );
	mk_page( 102, 'mentor-guide', 'wpcpm_mentor', 'Mentors confirm a calendar call.' );
	mk_page( 103, 'institution-guide', 'wpcpm_institution', 'Institutions sign the agreement.' );
	mk_page( 104, 'sponsor-guide', 'wpcpm_sponsor', 'Sponsors offer a prize.' );
	mk_page( 105, 'administrator-guide', 'administrator', 'Administrators read the zebra ledger.' );
	mk_page( 106, 'faq', 'public', 'Anyone may ask about the program.' );
	mk_page( 107, 'contact', 'public', 'Write to the team.' );
}

/** The sources the state holds, sorted. */
function source_keys() {
	$keys = array_keys( WPCPM_Guide_Index::sources() );
	sort( $keys );
	return $keys;
}

/** The sources of one kind of key. */
function keys_like( $prefix ) {
	return array_values( array_filter( source_keys(), function ( $k ) use ( $prefix ) { return 0 === strpos( $k, $prefix ); } ) );
}

/** The passages in the table for a source. */
function rows_of( $source ) {
	return (int) sql_var( "SELECT COUNT(*) FROM wp_wpcpm_guide_chunks WHERE source = '" . $source . "'" );
}

/** The hooks scheduled, as a list. */
function scheduled_hooks() {
	return array_map( function ( $e ) { return $e['hook']; }, $GLOBALS['cron'] );
}

$student  = new WP_User( 11 );
$mentor   = new WP_User( 12 );
$inst     = new WP_User( 13 );
$sponsor  = new WP_User( 14 );
$admin    = new WP_User( 15 );
$visitor  = new WP_User( 0 );

// pages() and the constants -----------------------------------------------------------------------

world();
ck( 'pages(): the five guides and the FAQ, by path, with audience and kind',
    WPCPM_Guide_Sources::pages(),
    array(
        'student-guide'       => array( 'audience' => 'student', 'kind' => 'guide' ),
        'mentor-guide'        => array( 'audience' => 'mentor', 'kind' => 'guide' ),
        'institution-guide'   => array( 'audience' => 'institution', 'kind' => 'guide' ),
        'sponsor-guide'       => array( 'audience' => 'sponsor', 'kind' => 'guide' ),
        'administrator-guide' => array( 'audience' => 'administrator', 'kind' => 'guide' ),
        'faq'                 => array( 'audience' => 'public', 'kind' => 'faq' ),
    ) );
$GLOBALS['filters']['wpcpm_guide_pages'] = function ( $pages ) {
	$pages['/extra/']      = array( 'audience' => 'student', 'kind' => 'guide' );
	$pages['bad-audience'] = array( 'audience' => 'everyone', 'kind' => 'guide' );
	$pages['bad-kind']     = array( 'audience' => 'student', 'kind' => 'post' );
	$pages['no-kind']      = array( 'audience' => 'student' );
	return $pages;
};
ck( 'the filter can add a page (slashes trimmed); an entry with an unknown audience or kind is dropped', array_keys( WPCPM_Guide_Sources::pages() ),
    array( 'student-guide', 'mentor-guide', 'institution-guide', 'sponsor-guide', 'administrator-guide', 'faq', 'extra' ) );
unset( $GLOBALS['filters']['wpcpm_guide_pages'] );
ck( 'the constants as the brief gives them',
    array( WPCPM_Guide_Sources::CRON_DAILY, WPCPM_Guide_Sources::CRON_REBUILD, WPCPM_Guide_Sources::OPT_HANDBOOK, WPCPM_Guide_Sources::LOCK, WPCPM_Guide_Sources::LOCK_TTL, WPCPM_Guide_Sources::HANDBOOK_ROUTE, WPCPM_Guide_Sources::HANDBOOK_UNDER, WPCPM_Guide_Sources::HANDBOOK_SKIP ),
    array( 'wpcpm_guide_daily', 'wpcpm_guide_rebuild', 'wpcpm_guide_handbook_state', 'wpcpm_guide_index_lock', 300, 'https://make.wordpress.org/community/wp-json/wp/v2/education-handbook', '/community/handbook/education/credits/', array( '/community/handbook/education/credits/program-manager-guide/' ) ) );

// Who reads which audience ------------------------------------------------------------------------

world();
ck( 'a student reads public and student', WPCPM_Guide_Sources::audiences_for( $student ), array( 'public', 'student' ) );
ck( 'a mentor reads public and mentor', WPCPM_Guide_Sources::audiences_for( $mentor ), array( 'public', 'mentor' ) );
ck( 'an institution\'s member reads public and institution', WPCPM_Guide_Sources::audiences_for( $inst ), array( 'public', 'institution' ) );
ck( 'a sponsor reads public and sponsor', WPCPM_Guide_Sources::audiences_for( $sponsor ), array( 'public', 'sponsor' ) );
ck( 'an Administrator reads all six', WPCPM_Guide_Sources::audiences_for( $admin ), array( 'public', 'student', 'mentor', 'institution', 'sponsor', 'administrator' ) );
ck( 'a visitor reads public alone', WPCPM_Guide_Sources::audiences_for( $visitor ), array( 'public' ) );
ck( 'no asker named is the current user: a student signed in reads what a student reads', ( function () use ( $student ) { $GLOBALS['current'] = $student; return WPCPM_Guide_Sources::audiences_for(); } )(), array( 'public', 'student' ) );
$GLOBALS['current'] = new WP_User( 0 );
ck( 'an asker named by ID is read the same', WPCPM_Guide_Sources::audiences_for( 12 ), array( 'public', 'mentor' ) );

$GLOBALS['levels'][102] = 'administrator';
ck( 'a page whose level becomes Administrators only is no longer read by a mentor at the next question', WPCPM_Guide_Sources::audiences_for( $mentor ), array( 'public' ) );
ck( 'while an Administrator still reads it', in_array( 'mentor', WPCPM_Guide_Sources::audiences_for( $admin ), true ), true );
$GLOBALS['levels'][102] = 'public';
ck( 'and a page that becomes public is read by everybody who may ask, a visitor too', WPCPM_Guide_Sources::audiences_for( $visitor ), array( 'public', 'mentor' ) );
$GLOBALS['levels'][102] = 'wpcpm_mentor';

$GLOBALS['posts'][104]->post_status = 'draft';
ck( 'a draft guide is read by nobody, an Administrator included', array( in_array( 'sponsor', WPCPM_Guide_Sources::audiences_for( $sponsor ), true ), in_array( 'sponsor', WPCPM_Guide_Sources::audiences_for( $admin ), true ) ), array( false, false ) );
$GLOBALS['posts'][104]->post_status = 'publish';
$GLOBALS['posts'][103]->post_password = 'secret';
ck( 'and so is a password-protected one', in_array( 'institution', WPCPM_Guide_Sources::audiences_for( $admin ), true ), false );
$GLOBALS['posts'][103]->post_password = '';
unset( $GLOBALS['posts'][101] );
ck( 'a guide that does not exist is read by nobody', in_array( 'student', WPCPM_Guide_Sources::audiences_for( $admin ), true ), false );

// The home guide.
world();
ck( 'home_for: the audience asked from, when the asker reads it', WPCPM_Guide_Sources::home_for( $admin, 'sponsor' ), 'sponsor' );
ck( 'home_for: an audience the asker does not read is not the home; the first the asker reads is (a mentor asking from the Administrator view)', WPCPM_Guide_Sources::home_for( $mentor, 'administrator' ), 'mentor' );
ck( 'home_for: an Administrator with nothing asked is administrator', WPCPM_Guide_Sources::home_for( $admin ), 'administrator' );
ck( 'home_for: a sponsor, an institution, a mentor, a student each have their own', array( WPCPM_Guide_Sources::home_for( $sponsor ), WPCPM_Guide_Sources::home_for( $inst ), WPCPM_Guide_Sources::home_for( $mentor ), WPCPM_Guide_Sources::home_for( $student ) ), array( 'sponsor', 'institution', 'mentor', 'student' ) );
ck( 'home_for: a visitor has none', WPCPM_Guide_Sources::home_for( $visitor ), '' );
ck( 'home_for: "public" and an unknown name are not a home', array( WPCPM_Guide_Sources::home_for( $student, 'public' ), WPCPM_Guide_Sources::home_for( $student, 'nonsense' ) ), array( 'student', 'student' ) );
$GLOBALS['levels'][105] = 'public';
ck( 'home_for: a guide made public does not make a mentor an administrator', WPCPM_Guide_Sources::home_for( $mentor ), 'mentor' );
ck( 'home_for: but the asker may still ask from it', WPCPM_Guide_Sources::home_for( $mentor, 'administrator' ), 'administrator' );
$GLOBALS['levels'][105] = 'administrator';

// A passage never reaches somebody its page would refuse: the ranking, through the audiences.
world();
WPCPM_Guide_Sources::rebuild( false );
$words = array( 'zebra ledger' );
ck( 'the zebra ledger is in the index', rows_of( 'page:105' ), 1 );
ck( 'a mentor is given nothing of the Administrator guide', WPCPM_Guide_Search::rank( $words, WPCPM_Guide_Sources::audiences_for( $mentor ), WPCPM_Guide_Sources::home_for( $mentor ) ), array() );
ck( 'an Administrator is given it', array_column( WPCPM_Guide_Search::rank( $words, WPCPM_Guide_Sources::audiences_for( $admin ), WPCPM_Guide_Sources::home_for( $admin ) ), 'source' ), array( 'page:105' ) );
$GLOBALS['levels'][105] = 'wpcpm_mentor';
ck( 'the page\'s level moved to Mentor: a mentor is given it with no reindex', array_column( WPCPM_Guide_Search::rank( $words, WPCPM_Guide_Sources::audiences_for( $mentor ), '' ), 'source' ), array( 'page:105' ) );

// A saved page (Review Focus 4) ------------------------------------------------------------------

world();
$n = WPCPM_Guide_Sources::index_page( $GLOBALS['posts'][101] );
$e = WPCPM_Guide_Index::sources()['page:101'];
ck( 'index_page writes a published page at a listed path as page:<ID>, with its audience, kind, title and modified time',
    array( $n, $e['kind'], $e['audience'], $e['post_id'], $e['title'], $e['modified'], isset( $e['error'] ) ), array( 1, 'guide', 'student', 101, 'Student guide', '2026-11-19 09:00:00', false ) );
ck( 'its passage links to the page\'s address and the section\'s anchor', sql_var( "SELECT url FROM wp_wpcpm_guide_chunks WHERE source = 'page:101'" ), 'https://example.test/student-guide/#h101' );
ck( 'the FAQ is kind faq and public', ( function () { WPCPM_Guide_Sources::index_page( $GLOBALS['posts'][106] ); $e = WPCPM_Guide_Index::sources()['page:106']; return array( $e['kind'], $e['audience'] ); } )(), array( 'faq', 'public' ) );
ck( 'a page at no listed path is not written', array( WPCPM_Guide_Sources::index_page( $GLOBALS['posts'][107] ), keys_like( 'page:107' ) ), array( 0, array() ) );

$before = $GLOBALS['posts'][101];
$GLOBALS['posts'][101]->post_status = 'draft';
WPCPM_Guide_Sources::on_save( 101, $GLOBALS['posts'][101], true, $before );
ck( 'a draft of the Student guide leaves the index', array( keys_like( 'page:101' ), rows_of( 'page:101' ), orphan_terms() ), array( array(), 0, 0 ) );
$GLOBALS['posts'][101]->post_status = 'publish';
WPCPM_Guide_Sources::on_save( 101, $GLOBALS['posts'][101], true, $before );
ck( 'and publishing it again brings it back', keys_like( 'page:101' ), array( 'page:101' ) );
$GLOBALS['posts'][101]->post_status = 'trash';
WPCPM_Guide_Sources::on_save( 101, $GLOBALS['posts'][101], true, $before );
ck( 'a trashed page leaves it too', keys_like( 'page:101' ), array() );
$GLOBALS['posts'][101]->post_status = 'publish';

WPCPM_Guide_Sources::on_save( 106, $GLOBALS['posts'][106], true, $GLOBALS['posts'][106] );
$GLOBALS['posts'][106]->post_status = 'draft';
WPCPM_Guide_Sources::on_save( 106, $GLOBALS['posts'][106], true, $GLOBALS['posts'][106] );
ck( 'the FAQ unpublished removes it', keys_like( 'page:106' ), array() );
$GLOBALS['posts'][106]->post_status = 'publish';

WPCPM_Guide_Sources::on_save( 101, $GLOBALS['posts'][101], true, $before );
$GLOBALS['posts'][101]->post_password = 'secret';
WPCPM_Guide_Sources::on_save( 101, $GLOBALS['posts'][101], true, $before );
ck( 'a password put on a guide takes it out of the index', keys_like( 'page:101' ), array() );
$GLOBALS['posts'][101]->post_password = '';

$snapshot = WPCPM_Guide_Index::state();
$GLOBALS['revisions'][101] = true;
$GLOBALS['posts'][101]->post_content = '<h2 id="x">Changed</h2><p>Changed text.</p>';
WPCPM_Guide_Sources::on_save( 101, $GLOBALS['posts'][101], true, $before );
ck( 'a revision writes nothing', array( WPCPM_Guide_Index::state(), keys_like( 'page:101' ) ), array( $snapshot, array() ) );
$GLOBALS['revisions'][101] = false;
$GLOBALS['autosaves'][101] = true;
WPCPM_Guide_Sources::on_save( 101, $GLOBALS['posts'][101], true, $before );
ck( 'an autosave writes nothing', array( WPCPM_Guide_Index::state(), keys_like( 'page:101' ) ), array( $snapshot, array() ) );
$GLOBALS['autosaves'][101] = false;
WPCPM_Guide_Sources::on_save( 107, $GLOBALS['posts'][107], true, $GLOBALS['posts'][107] );
ck( 'a page at no listed path, that was at none, writes nothing', array( WPCPM_Guide_Index::state(), keys_like( 'page:107' ) ), array( $snapshot, array() ) );
$post_row = mk_page( 108, 'student-guide-notes', 'public', 'A post.', array( 'post_type' => 'post' ) );
$GLOBALS['uris'][108] = 'student-guide';
WPCPM_Guide_Sources::on_save( 108, $post_row, false, null );
ck( 'a post is not a page, whatever its name', array( WPCPM_Guide_Index::state(), keys_like( 'page:108' ) ), array( $snapshot, array() ) );
ck( 'an object that is not a post is nothing', ( function () use ( $snapshot ) { WPCPM_Guide_Sources::on_save( 101, null, false, null ); return WPCPM_Guide_Index::state(); } )(), $snapshot );

WPCPM_Guide_Sources::on_save( 101, $GLOBALS['posts'][101], true, $before );
ck( 'a saved Student guide is written, with the text as saved', array( keys_like( 'page:101' ), sql_var( "SELECT body FROM wp_wpcpm_guide_chunks WHERE source = 'page:101'" ) ), array( array( 'page:101' ), 'Changed text.' ) );
$moved                = $GLOBALS['posts'][101];
$was                  = clone $moved;
$was->uri             = 'student-guide';
$moved->uri           = 'old-student-guide';
WPCPM_Guide_Sources::on_save( 101, $moved, true, $was );
$moved->uri           = null;
ck( 'a guide moved off its path leaves the index, though the page itself is still published', keys_like( 'page:101' ), array() );
$became      = mk_page( 109, 'draft-name', 'wpcpm_student', 'Became the guide.' );
$was         = clone $became;
$became->uri = 'student-guide';
WPCPM_Guide_Sources::on_save( 109, $became, true, $was );
ck( 'and a page moved onto a listed path is written', keys_like( 'page:109' ), array( 'page:109' ) );
$GLOBALS['uris'] = array();

$GLOBALS['locks'][ WPCPM_Guide_Sources::LOCK ] = 'held';
$snapshot = WPCPM_Guide_Index::state();
WPCPM_Guide_Sources::on_save( 106, $GLOBALS['posts'][106], true, $GLOBALS['posts'][106] );
ck( 'a save while the index is locked writes nothing and asks for one rebuild, which a second save does not double',
    array( WPCPM_Guide_Index::state() === $snapshot, ( function () { WPCPM_Guide_Sources::on_save( 106, $GLOBALS['posts'][106], true, $GLOBALS['posts'][106] ); return scheduled_hooks(); } )(), isset( $GLOBALS['locks'][ WPCPM_Guide_Sources::LOCK ] ) ),
    array( true, array( 'wpcpm_guide_rebuild' ), true ) );
unset( $GLOBALS['locks'][ WPCPM_Guide_Sources::LOCK ] );
WPCPM_Guide_Sources::on_save( 106, $GLOBALS['posts'][106], true, $GLOBALS['posts'][106] );
ck( 'and a save lets the lock go', isset( $GLOBALS['locks'][ WPCPM_Guide_Sources::LOCK ] ), false );

// A gated FAQ stops feeding everybody (ruling T3-1): a public page is indexed only while its level is public.
world();
$GLOBALS['posts'][106]->post_content = '<h2 id="q">Quokka</h2><p>The quokka answer.</p>';
WPCPM_Guide_Sources::on_save( 106, $GLOBALS['posts'][106], true, $GLOBALS['posts'][106] );
$quokka = function ( $who ) { return array_column( WPCPM_Guide_Search::rank( array( 'quokka' ), WPCPM_Guide_Sources::audiences_for( $who ), '' ), 'source' ); };
ck( 'a public FAQ is found by a visitor', $quokka( $visitor ), array( 'page:106' ) );
$GLOBALS['levels'][106] = 'administrator';
WPCPM_Guide_Sources::on_save( 106, $GLOBALS['posts'][106], true, $GLOBALS['posts'][106] );
ck( 'the FAQ set to Administrators only: a save takes it out, and a visitor, a student and a mentor are given nothing',
    array( keys_like( 'page:106' ), rows_of( 'page:106' ), $quokka( $visitor ), $quokka( $student ), $quokka( $mentor ) ), array( array(), 0, array(), array(), array() ) );
$GLOBALS['levels'][106] = 'wpcpm_student';
WPCPM_Guide_Sources::index_page( $GLOBALS['posts'][106] );
ck( 'and at a role level too (index_page alone)', keys_like( 'page:106' ), array() );
$GLOBALS['levels'][106] = 'public';
WPCPM_Guide_Sources::on_save( 106, $GLOBALS['posts'][106], true, $GLOBALS['posts'][106] );
ck( 'the level set back to public brings it back at the next save', array( keys_like( 'page:106' ), $quokka( $visitor ) ), array( array( 'page:106' ), array( 'page:106' ) ) );
$GLOBALS['levels'][106] = 'administrator';
WPCPM_Guide_Sources::daily();
ck( 'a level changed by meta alone (the modified time unmoved) is caught by the daily sweep', array( keys_like( 'page:106' ), $quokka( $visitor ) ), array( array(), array() ) );
$GLOBALS['levels'][106] = 'public';
WPCPM_Guide_Sources::daily();
ck( 'and back to public, the daily run writes it again', keys_like( 'page:106' ), array( 'page:106' ) );
$GLOBALS['levels'][106] = 'administrator';
WPCPM_Guide_Sources::rebuild( false );
ck( 'a rebuild leaves a gated FAQ out', keys_like( 'page:106' ), array() );

// A guide deleted for good (`before_delete_post`, while the post is still there) ----------------------

/** Delete a page for good as WordPress does: `before_delete_post` with the post still there, then the row gone. */
function force_delete( $id ) {
	$post = isset( $GLOBALS['posts'][ $id ] ) ? $GLOBALS['posts'][ $id ] : null;
	foreach ( $GLOBALS['hooks'] as $h ) {
		if ( 'before_delete_post' === $h[0] ) {
			call_user_func( $h[1], $id, $post );
		}
	}
	unset( $GLOBALS['posts'][ $id ] );
}

world();
WPCPM_Guide_Sources::rebuild( false );
WPCPM_Guide_Sources::init();
force_delete( 102 );
ck( 'a guide deleted for good leaves the index at once, its passages and their terms with it, and the lock is let go',
    array( keys_like( 'page:102' ), rows_of( 'page:102' ), orphan_terms(), $GLOBALS['locks'], scheduled_hooks() ), array( array(), 0, 0, array(), array() ) );
$snapshot = WPCPM_Guide_Index::state();
force_delete( 107 );
force_delete( 999 );
ck( 'a page the index does not hold is left alone: nothing written, no lock taken, nothing scheduled',
    array( WPCPM_Guide_Index::state() === $snapshot, $GLOBALS['locks'], scheduled_hooks() ), array( true, array(), array() ) );
$GLOBALS['locks'][ WPCPM_Guide_Sources::LOCK ] = 'held';
force_delete( 103 );
ck( 'deleted while another run holds the index: nothing is written, the lock stays the other run\'s, and one rebuild is asked for',
    array( keys_like( 'page:103' ), $GLOBALS['locks'][ WPCPM_Guide_Sources::LOCK ], scheduled_hooks() ), array( array( 'page:103' ), 'held', array( 'wpcpm_guide_rebuild' ) ) );
unset( $GLOBALS['locks'][ WPCPM_Guide_Sources::LOCK ] );
WPCPM_Guide_Sources::run_rebuild();
ck( 'and that rebuild, run once the page is gone, takes it out', array( keys_like( 'page:103' ), rows_of( 'page:103' ) ), array( array(), 0 ) );
$GLOBALS['hooks'] = array();

// The handbook's keep filter --------------------------------------------------------------------

$base = 'https://make.wordpress.org/community/handbook/education/';
ck( 'handbook_keep keeps credits/ and what is under it',
    array( WPCPM_Guide_Sources::handbook_keep( $base . 'credits/' ), WPCPM_Guide_Sources::handbook_keep( $base . 'credits/mentor-guide/mentor-checklist/' ), WPCPM_Guide_Sources::handbook_keep( $base . 'credits/student-guide/' ) ),
    array( true, true, true ) );
ck( 'drops program-manager-guide, Campus Connect, another host and a look-alike path',
    array(
        WPCPM_Guide_Sources::handbook_keep( $base . 'credits/program-manager-guide/courses-management/' ),
        WPCPM_Guide_Sources::handbook_keep( $base . 'credits/program-manager-guide/' ),
        WPCPM_Guide_Sources::handbook_keep( $base . 'campus-connect/wpcc-travel-partner/' ),
        WPCPM_Guide_Sources::handbook_keep( 'https://example.test/community/handbook/education/credits/mentor-guide/' ),
        WPCPM_Guide_Sources::handbook_keep( 'https://make.wordpress.org.example.test/community/handbook/education/credits/' ),
        WPCPM_Guide_Sources::handbook_keep( $base . 'credits-other/' ),
        WPCPM_Guide_Sources::handbook_keep( 'not a link' ),
        WPCPM_Guide_Sources::handbook_keep( '' ),
    ),
    array( false, false, false, false, false, false, false, false ) );
$GLOBALS['filters']['wpcpm_guide_handbook_keep'] = function ( $keep, $link ) { return $keep && false === strpos( $link, 'logos' ); };
ck( 'the filter wpcpm_guide_handbook_keep has the last word', array( WPCPM_Guide_Sources::handbook_keep( $base . 'credits/how-to-participate/wordpress-credits-logos/' ), WPCPM_Guide_Sources::handbook_keep( $base . 'credits/' ) ), array( false, true ) );
unset( $GLOBALS['filters']['wpcpm_guide_handbook_keep'] );

// The handbook sync from the file --------------------------------------------------------------------

world();
$fixture_file = WPCPM_PLUGIN_DIR . 'bin/fixtures/guides/handbook.json';
$r = WPCPM_Guide_Sources::sync_handbook( $fixture_file );
ck( 'sync_handbook from the fixture: twelve kept pages written', array( $r['ok'], $r['pages'], $r['changed'], $r['removed'], $r['error'] ), array( true, 12, 12, 0, '' ) );
ck( 'as handbook:<id> sources, public, kind handbook, and no passage is empty', array( count( keys_like( 'handbook:' ) ), keys_like( 'handbook:' )[0], WPCPM_Guide_Index::sources()['handbook:77962']['audience'], WPCPM_Guide_Index::sources()['handbook:77962']['kind'], (int) sql_var( "SELECT COUNT(*) FROM wp_wpcpm_guide_chunks WHERE source LIKE 'handbook:%' AND body = ''" ) ), array( 12, 'handbook:77962', 'public', 'handbook', 0 ) );
ck( 'the two cut pages (program-manager-guide, campus-connect) are not in it', array( isset( WPCPM_Guide_Index::sources()['handbook:99804'] ), isset( WPCPM_Guide_Index::sources()['handbook:87951'] ) ), array( false, false ) );
ck( 'a handbook passage\'s address is the REST link', sql_var( "SELECT url FROM wp_wpcpm_guide_chunks WHERE source = 'handbook:88194' LIMIT 1" ), 'https://make.wordpress.org/community/handbook/education/credits/how-to-participate/call-for-mentors/' );
ck( 'the title and the modified time are the route\'s', array( WPCPM_Guide_Index::sources()['handbook:88194']['title'], WPCPM_Guide_Index::sources()['handbook:88194']['modified'] ), array( 'Call for Mentors', '2026-11-05T09:51:51' ) );
$hb_state = get_option( WPCPM_Guide_Sources::OPT_HANDBOOK );
ck( 'the state holds when it ran, that it went well, no error, the pages, and that one request read them all', array( is_int( $hb_state['synced_at'] ), $hb_state['ok'], $hb_state['error'], $hb_state['pages'], $hb_state['more'], array_keys( $hb_state ), $GLOBALS['autoload'][ WPCPM_Guide_Sources::OPT_HANDBOOK ] ), array( true, true, '', 12, false, array( 'synced_at', 'ok', 'error', 'pages', 'more' ), false ) );
ck( 'no request was made for a file', $GLOBALS['remote_urls'], array() );

$ids_before = chunk_ids();
$r          = WPCPM_Guide_Sources::sync_handbook( $fixture_file );
ck( 'a second run changes nothing: no page written, no passage rewritten', array( $r['ok'], $r['changed'], $r['removed'], chunk_ids() === $ids_before ), array( true, 0, 0, true ) );

$data = json_decode( $hb_json, true );
$temp = tempnam( sys_get_temp_dir(), 'hb' );
$data[3]['modified_gmt'] = '2026-11-18T10:00:00';
file_put_contents( $temp, json_encode( $data ) );
$r = WPCPM_Guide_Sources::sync_handbook( $temp );
ck( 'a page that moved is written alone', array( $r['changed'], $r['removed'], WPCPM_Guide_Index::sources()['handbook:90164']['modified'] ), array( 1, 0, '2026-11-18T10:00:00' ) );

$state = WPCPM_Guide_Index::state();
$state['sources']['handbook:96111']['modified'] = '';
$state['sources']['handbook:96111']['error']    = 'The database refused a write.';
update_option( WPCPM_Guide_Index::OPT_STATE, $state, false );
$r = WPCPM_Guide_Sources::sync_handbook( $temp );
ck( 'a page whose last write failed is written again, though its time matches', array( $r['changed'], isset( WPCPM_Guide_Index::sources()['handbook:96111']['error'] ) ), array( 1, false ) );

$gone = array_values( array_filter( $data, function ( $p ) { return 88161 !== $p['id']; } ) );
file_put_contents( $temp, json_encode( $gone ) );
$r = WPCPM_Guide_Sources::sync_handbook( $temp );
ck( 'a page dropped from the answer is removed, with its passages and terms', array( $r['ok'], $r['pages'], $r['changed'], $r['removed'], isset( WPCPM_Guide_Index::sources()['handbook:88161'] ), rows_of( 'handbook:88161' ), orphan_terms() ), array( true, 11, 0, 1, false, 0, 0 ) );

$data[0]['title']['rendered'] = 'Q &amp; A: the <em>mentor&#8217;s</em> list';
$data[0]['modified_gmt']      = '2026-11-18T11:00:00';
file_put_contents( $temp, json_encode( $data ) );
WPCPM_Guide_Sources::sync_handbook( $temp );
ck( 'a title is the route\'s title.rendered, tags off and entities decoded', WPCPM_Guide_Index::sources()['handbook:96266']['title'], "Q & A: the mentor\u{2019}s list" );

$data = json_decode( $hb_json, true );
unset( $data[1]['content'] );
file_put_contents( $temp, json_encode( $data ) );
$r = WPCPM_Guide_Sources::sync_handbook( $temp );
ck( 'a kept page the answer sends without content is left as it is, not removed', array( $r['ok'], isset( WPCPM_Guide_Index::sources()['handbook:96113'] ), rows_of( 'handbook:96113' ) > 0 ), array( true, true, true ) );
unlink( $temp );

$missing = WPCPM_Guide_Sources::sync_handbook( '/nonexistent/handbook.json' );
ck( 'a file that cannot be read is a failure with a sentence', array( $missing['ok'], '' !== $missing['error'] ), array( false, true ) );

// A bad answer changes nothing (Review Focus 5) ------------------------------------------------------

world();
WPCPM_Guide_Sources::sync_handbook( $fixture_file );
$held   = chunk_ids();
$held_s = WPCPM_Guide_Index::sources();
$bad    = array(
	'a WP_Error'                          => new WP_Error( 'http_request_failed', 'cURL error 28: https://make.wordpress.org/ timed out' ),
	'a 500'                               => array( 'response' => array( 'code' => 500 ), 'body' => '<html>Server Error</html>' ),
	'a 200 whose body is not JSON'        => array( 'response' => array( 'code' => 200 ), 'body' => '<html>Maintenance</html>' ),
	'an empty list'                       => array( 'response' => array( 'code' => 200 ), 'body' => '[]' ),
	'an object, not a list'               => array( 'response' => array( 'code' => 200 ), 'body' => '{"code":"rest_no_route","message":"No route"}' ),
	'a list with no page worth keeping'   => array( 'response' => array( 'code' => 200 ), 'body' => '[{"id":1,"link":"https://make.wordpress.org/community/handbook/education/campus-connect/","modified_gmt":"2026-01-01T00:00:00","content":{"rendered":"<p>x</p>"}}]' ),
);
foreach ( $bad as $label => $reply ) {
	$GLOBALS['remote'] = $reply;
	$r                 = WPCPM_Guide_Sources::sync_handbook();
	$state             = get_option( WPCPM_Guide_Sources::OPT_HANDBOOK );
	ck( "$label: nothing is written and nothing removed",
	    array( $r['ok'], $r['changed'], $r['removed'], '' !== $r['error'], chunk_ids() === $held, WPCPM_Guide_Index::sources() === $held_s ),
	    array( false, 0, 0, true, true, true ) );
	ck( "$label: the state says why, and that the index still holds twelve pages", array( $state['ok'], $state['error'] === $r['error'], $state['pages'] ), array( false, true, 12 ) );
	ck( "$label: the sentence names no address", array( false !== strpos( $r['error'], 'make.wordpress.org' ), false !== strpos( $r['error'], 'cURL' ) ), array( false, false ) );
}
$GLOBALS['remote']      = array( 'response' => array( 'code' => 200 ), 'body' => $hb_json );
$GLOBALS['remote_urls'] = array();
$r                      = WPCPM_Guide_Sources::sync_handbook();
ck( 'a good answer after the bad ones clears the error', array( $r['ok'], get_option( WPCPM_Guide_Sources::OPT_HANDBOOK )['error'], get_option( WPCPM_Guide_Sources::OPT_HANDBOOK )['ok'] ), array( true, '', true ) );
ck( 'the sync made one request, of the route with its fields, for 20 seconds',
    array( count( $GLOBALS['remote_urls'] ), $GLOBALS['remote_urls'][0][0], $GLOBALS['remote_urls'][0][1]['timeout'] ),
    array( 1, 'https://make.wordpress.org/community/wp-json/wp/v2/education-handbook?per_page=100&_fields=id,link,title,modified_gmt,content', 20 ) );

// More pages than one request reads (X-WP-TotalPages over 1): a page missing from this answer may be on the
// next one, so what came is written and nothing is removed, and the state says the handbook outgrew it.
$data  = json_decode( $hb_json, true );
$first = array_values( array_filter( $data, function ( $p ) { return 88161 !== $p['id']; } ) );
foreach ( $first as $i => $p ) {
	if ( 90164 === $p['id'] ) {
		$first[ $i ]['modified_gmt'] = '2026-11-18T12:00:00';
	}
}
$GLOBALS['remote'] = array( 'response' => array( 'code' => 200 ), 'headers' => array( 'x-wp-totalpages' => '2' ), 'body' => json_encode( $first ) );
$r                 = WPCPM_Guide_Sources::sync_handbook();
ck( 'an answer saying the handbook has more pages than one request reads: the moved page is written, the page it does not carry is kept, none is removed',
    array( $r['ok'], $r['changed'], $r['removed'], isset( WPCPM_Guide_Index::sources()['handbook:88161'] ), rows_of( 'handbook:88161' ) > 0, WPCPM_Guide_Index::sources()['handbook:90164']['modified'], count( keys_like( 'handbook:' ) ) ),
    array( true, 1, 0, true, true, '2026-11-18T12:00:00', 12 ) );
ck( 'and the state records that the handbook holds more pages than one request reads',
    array( get_option( WPCPM_Guide_Sources::OPT_HANDBOOK )['ok'], get_option( WPCPM_Guide_Sources::OPT_HANDBOOK )['more'] ), array( true, true ) );
$GLOBALS['remote'] = array( 'response' => array( 'code' => 200 ), 'headers' => array( 'x-wp-totalpages' => '1' ), 'body' => json_encode( $first ) );
$r                 = WPCPM_Guide_Sources::sync_handbook();
ck( 'with one page of answers a page it no longer carries is removed, as before, and the record is put back',
    array( $r['removed'], isset( WPCPM_Guide_Index::sources()['handbook:88161'] ), get_option( WPCPM_Guide_Sources::OPT_HANDBOOK )['more'] ), array( 1, false, false ) );
$GLOBALS['remote'] = array( 'response' => array( 'code' => 200 ), 'body' => $hb_json );

// The lock, the rebuild, the daily run -------------------------------------------------------------------

world();
$GLOBALS['locks'][ WPCPM_Guide_Sources::LOCK ] = 'held';
ck( 'rebuild() answers busy while the lock is held, and writes nothing', array( WPCPM_Guide_Sources::rebuild(), WPCPM_Guide_Index::count(), WPCPM_Guide_Index::state() ), array( array( 'busy' => true ), 0, array( 'sources' => array() ) ) );
$r = WPCPM_Guide_Sources::sync_handbook( $fixture_file );
ck( 'so does sync_handbook(), and the held lock is not let go', array( $r['ok'], '' !== $r['error'], $GLOBALS['locks'][ WPCPM_Guide_Sources::LOCK ], keys_like( 'handbook:' ), $GLOBALS['remote_urls'] ), array( false, true, 'held', array(), array() ) );
WPCPM_Guide_Sources::daily();
ck( 'the daily run does nothing', array( WPCPM_Guide_Index::count(), $GLOBALS['remote_urls'] ), array( 0, array() ) );
unset( $GLOBALS['locks'][ WPCPM_Guide_Sources::LOCK ] );

$r = WPCPM_Guide_Sources::rebuild();
ck( 'rebuild() writes every listed page, then the handbook, and returns the passages per source',
    array( array_slice( $r, 0, 6, true ), count( $r ), isset( $r['handbook:77962'] ), isset( $r['busy'] ) ),
    array( array( 'page:101' => 1, 'page:102' => 1, 'page:103' => 1, 'page:104' => 1, 'page:105' => 1, 'page:106' => 1 ), 18, true, false ) );
ck( 'it notes the version it ran under, and lets the lock go', array( WPCPM_Guide_Index::state()['built_for'], $GLOBALS['locks'] ), array( '1.123.101', array() ) );
ck( 'the pages, the FAQ and the handbook are all in the index', array( count( keys_like( 'page:' ) ), count( keys_like( 'handbook:' ) ) ), array( 6, 12 ) );

$ids_before = chunk_ids();
$r          = WPCPM_Guide_Sources::rebuild();
ck( 'a second rebuild writes the handbook\'s pages again, moved or not (a release may read them differently)', array( count( array_intersect( $ids_before, chunk_ids() ) ), count( chunk_ids() ) === count( $ids_before ) ), array( 0, true ) );
$r = WPCPM_Guide_Sources::rebuild( false );
ck( 'rebuild( false ) leaves the handbook alone', array( count( $r ), count( $GLOBALS['remote_urls'] ) ), array( 6, 2 ) );

$GLOBALS['remote'] = new WP_Error( 'x', 'down' );
$ids_before        = chunk_ids();
WPCPM_Guide_Sources::rebuild();
ck( 'a rebuild while the handbook is down still rebuilds the pages and keeps every handbook passage', array( count( keys_like( 'handbook:' ) ), get_option( WPCPM_Guide_Sources::OPT_HANDBOOK )['ok'], count( chunk_ids() ) === count( $ids_before ) ), array( 12, false, true ) );
$GLOBALS['remote'] = array( 'response' => array( 'code' => 200 ), 'body' => $hb_json );

// A page that became a different one, a page deleted: a rebuild drops what is not at a listed path.
unset( $GLOBALS['posts'][103] );
WPCPM_Guide_Sources::rebuild( false );
ck( 'a rebuild drops a guide that was deleted', array( isset( WPCPM_Guide_Index::sources()['page:103'] ), rows_of( 'page:103' ) ), array( false, 0 ) );

// A lock lost mid-run stops the run.
world();
$GLOBALS['locks'][ WPCPM_Guide_Sources::LOCK ] = 'x';
unset( $GLOBALS['locks'][ WPCPM_Guide_Sources::LOCK ] );
ck( 'rebuild() does not note the version when the lock is lost under it', ( function () {
	$GLOBALS['filters']['wpcpm_guide_pages'] = function ( $pages ) {
		$GLOBALS['locks'][ WPCPM_Guide_Sources::LOCK ] = 'taken over';
		return $pages;
	};
	$r = WPCPM_Guide_Sources::rebuild( false );
	unset( $GLOBALS['filters']['wpcpm_guide_pages'] );
	return array( isset( $r['lost'] ), isset( WPCPM_Guide_Index::state()['built_for'] ), $GLOBALS['locks'][ WPCPM_Guide_Sources::LOCK ] );
} )(), array( true, false, 'taken over' ) );

// Daily.
world();
WPCPM_Guide_Sources::rebuild();
$ids_before = chunk_ids();
$GLOBALS['remote_urls'] = array();
WPCPM_Guide_Sources::daily();
ck( 'the daily run with nothing moved writes nothing, and reads the handbook once', array( chunk_ids() === $ids_before, count( $GLOBALS['remote_urls'] ) ), array( true, 1 ) );
$GLOBALS['posts'][102]->post_modified_gmt = '2026-11-19 12:00:00';
$GLOBALS['posts'][102]->post_content      = '<h2 id="n">New</h2><p>Mentors read a new line.</p>';
WPCPM_Guide_Sources::daily();
ck( 'a page whose modified time moved is reindexed, and only that one',
    array( sql_var( "SELECT body FROM wp_wpcpm_guide_chunks WHERE source = 'page:102'" ), WPCPM_Guide_Index::sources()['page:102']['modified'], count( array_intersect( $ids_before, chunk_ids() ) ) === count( $ids_before ) - 1 ),
    array( 'Mentors read a new line.', '2026-11-19 12:00:00', true ) );
$state = WPCPM_Guide_Index::state();
$state['sources']['page:104']['modified'] = '';
$state['sources']['page:104']['error']    = 'The database refused a write.';
update_option( WPCPM_Guide_Index::OPT_STATE, $state, false );
WPCPM_Guide_Sources::daily();
ck( 'a page whose last write failed is written again, though its time matches', isset( WPCPM_Guide_Index::sources()['page:104']['error'] ), false );
unset( $GLOBALS['posts'][105] );
$GLOBALS['posts'][106]->post_status = 'draft';
WPCPM_Guide_Sources::daily();
ck( 'a page deleted, and one unpublished, leave the index at the daily run', array( keys_like( 'page:105' ), keys_like( 'page:106' ) ), array( array(), array() ) );
ck( 'the daily run lets the lock go', $GLOBALS['locks'], array() );
$GLOBALS['locks'][ WPCPM_Guide_Sources::LOCK ] = 'held';
$snapshot = WPCPM_Guide_Index::state();
WPCPM_Guide_Sources::run_rebuild();
ck( 'the rebuild event\'s action does the rebuild, and with the lock held does nothing', WPCPM_Guide_Index::state() === $snapshot, true );
unset( $GLOBALS['locks'][ WPCPM_Guide_Sources::LOCK ] );
WPCPM_Guide_Sources::run_rebuild();
ck( 'and with it free builds and notes the version', WPCPM_Guide_Index::state()['built_for'], '1.123.101' );

// Schedule --------------------------------------------------------------------------------------------

world();
WPCPM_Guide_Sources::maybe_schedule();
WPCPM_Guide_Sources::maybe_schedule();
ck( 'maybe_schedule: the daily event once, and one rebuild when the version has moved, not two', array_count_values( scheduled_hooks() ), array( 'wpcpm_guide_daily' => 1, 'wpcpm_guide_rebuild' => 1 ) );
ck( 'the daily event repeats daily', array_values( array_map( function ( $e ) { return $e['recurrence']; }, array_filter( $GLOBALS['cron'], function ( $e ) { return 'wpcpm_guide_daily' === $e['hook']; } ) ) ), array( 'daily' ) );
$GLOBALS['cron'] = array();
WPCPM_Guide_Index::mark_built( '1.123.101' );
WPCPM_Guide_Sources::maybe_schedule();
ck( 'and none when the index was built under this version', scheduled_hooks(), array( 'wpcpm_guide_daily' ) );
$GLOBALS['cron'] = array();
WPCPM_Guide_Index::mark_built( '1.123.99' );
WPCPM_Guide_Sources::maybe_schedule();
ck( 'a release that moved the version schedules one again', scheduled_hooks(), array( 'wpcpm_guide_daily', 'wpcpm_guide_rebuild' ) );

// Boot, activation, deactivation, uninstall -----------------------------------------------------------

world();
WPCPM_Guide_Sources::init();
$hooked = array();
foreach ( $GLOBALS['hooks'] as $h ) {
	$hooked[] = $h[0] . ':' . ( is_array( $h[1] ) ? $h[1][0] . '::' . $h[1][1] : $h[1] ) . ':' . $h[2] . ':' . $h[3];
}
ck( 'init() hooks the upgrade at 5, the schedule at 20, the save with four arguments, a deletion with its ID, and the two events',
    $hooked,
    array(
        'init:WPCPM_Guide_Index::maybe_upgrade:5:1',
        'init:WPCPM_Guide_Sources::maybe_schedule:20:1',
        'wp_after_insert_post:WPCPM_Guide_Sources::on_save:10:4',
        'before_delete_post:WPCPM_Guide_Sources::on_delete:10:1',
        'wpcpm_guide_daily:WPCPM_Guide_Sources::daily:10:1',
        'wpcpm_guide_rebuild:WPCPM_Guide_Sources::run_rebuild:10:1',
    ) );

$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();
$GLOBALS['opts'] = array();
WPCPM_Guide_Sources::activate();
ck( 'activate() makes the tables and schedules one rebuild, and the daily event', array( WPCPM_Guide_Index::exists(), get_option( WPCPM_Guide_Index::OPT_VERSION ), scheduled_hooks() ), array( true, WPCPM_Guide_Index::SCHEMA_VERSION, array( 'wpcpm_guide_daily', 'wpcpm_guide_rebuild' ) ) );
WPCPM_Guide_Sources::activate();
ck( 'and does not double either on a second activation', array_count_values( scheduled_hooks() ), array( 'wpcpm_guide_daily' => 1, 'wpcpm_guide_rebuild' => 1 ) );
WPCPM_Guide_Sources::deactivate();
ck( 'deactivate() clears both events and leaves the index', array( scheduled_hooks(), WPCPM_Guide_Index::exists() ), array( array(), true ) );

world();
WPCPM_Guide_Sources::rebuild();
WPCPM_Guide_Sources::maybe_schedule();
$GLOBALS['opts'][ WPCPM_Guide_Sources::LOCK ] = '1';
WPCPM_Guide_Sources::uninstall();
ck( 'uninstall() drops both tables', array( (bool) $GLOBALS['wpdb']->query( 'SELECT 1 FROM wp_wpcpm_guide_chunks' ), (bool) $GLOBALS['wpdb']->query( 'SELECT 1 FROM wp_wpcpm_guide_terms' ) ), array( false, false ) );
ck( 'deletes the three options and the lock row', array( get_option( WPCPM_Guide_Index::OPT_VERSION, 'gone' ), get_option( WPCPM_Guide_Index::OPT_STATE, 'gone' ), get_option( WPCPM_Guide_Sources::OPT_HANDBOOK, 'gone' ), get_option( WPCPM_Guide_Sources::LOCK, 'gone' ) ), array( 'gone', 'gone', 'gone', 'gone' ) );
ck( 'and clears both events', scheduled_hooks(), array() );

// ---------------------------------------------------------------------------------------------
// Part 4: the outside review's findings and the trial's.
// ---------------------------------------------------------------------------------------------

// Hidden markup is never a passage (finding 3) -------------------------------------------------------

$hidden = WPCPM_Guide_Text::passages( '<h2 id="rules">Rules</h2><p>Attendance is optional.</p><!-- <h2>Old rule</h2><p>Attendance is mandatory.</p> --><p>Ask your mentor.</p>' );
ck( 'a heading inside a comment starts no passage, and the comment\'s paragraph is in none',
    $hidden, array( array( 'heading' => 'Rules', 'anchor' => 'rules', 'body' => "Attendance is optional.\nAsk your mentor." ) ) );
$scripted = WPCPM_Guide_Text::passages( '<h2 id="a">Calls</h2><p>Book a call.</p><script type="text/template"><h3 id="t">Template</h3><p>Every student fails.</p></script><style>h2 { color: red; }</style><svg viewBox="0 0 1 1"><foreignObject><h4>Drawn</h4><p>Drawn text.</p></foreignObject></svg><p>Then talk.</p>' );
ck( 'nor does one inside a script template, a style or an SVG, and none of their text is in a passage',
    $scripted, array( array( 'heading' => 'Calls', 'anchor' => 'a', 'body' => "Book a call.\nThen talk." ) ) );
ck( 'block comments around a heading still leave the heading a section of its own',
    array_column( WPCPM_Guide_Text::passages( '<!-- wp:heading --><h2 class="wp-block-heading" id="one">One</h2><!-- /wp:heading --><!-- wp:paragraph --><p>First.</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3 id="two">Two</h3><!-- /wp:heading --><p>Second.</p>' ), 'heading' ),
    array( 'One', 'One > Two' ) );
ck( 'and a FAQ question with a comment before its summary is still a passage under that summary',
    WPCPM_Guide_Text::passages( '<h2 id="q">Questions</h2><!-- wp:details --><details class="wp-block-details" id="cost"><!-- the summary --><summary>Does it cost anything?</summary><!-- wp:paragraph --><p>No.</p><!-- /wp:paragraph --></details><!-- /wp:details -->' ),
    array( array( 'heading' => 'Questions > Does it cost anything?', 'anchor' => 'cost', 'body' => 'No.' ) ) );
ck( 'the glossary tooltip rule still holds inside a section',
    WPCPM_Guide_Text::passages( "<h2 id=\"m\">Mentors</h2><p>Be a <span class='glossary-item-container'>mentor<span class='glossary-item-hidden-content'><span class='glossary-item-header'>Event Supporter</span></span></span>.</p>" ),
    array( array( 'heading' => 'Mentors', 'anchor' => 'm', 'body' => 'Be a mentor.' ) ) );

// Hidden markup is taken out by a scan that cannot fail on size (minor fix 1) --------------------------

$huge   = WPCPM_Guide_Text::passages( '<h2 id="a">First</h2><p>Before the drawing.</p><svg viewBox="0 0 1 1"><text>SVGSECRET ' . str_repeat( 'x', 1200000 ) . '</text></svg><h2 id="b">Second</h2><p>After the drawing.</p>' );
$joined = json_encode( $huge );
ck( 'an SVG of 1.2 million characters between two sections keeps both sections and none of the SVG\'s text',
    array( array_column( $huge, 'heading' ), array_column( $huge, 'body' ), false === strpos( $joined, 'SVGSECRET' ) ),
    array( array( 'First', 'Second' ), array( 'Before the drawing.', 'After the drawing.' ), true ) );
$open   = WPCPM_Guide_Text::passages( '<h2 id="a">First</h2><p>Before the comment.</p><h2 id="b">Second</h2><p>Also kept.</p><!-- never closed ' . str_repeat( 'x', 1200000 ) );
ck( 'an unclosed comment followed by 1.2 million characters leaves the passages before it, not an empty result',
    array_slice( array_column( $open, 'heading' ), 0, 2 ), array( 'First', 'Second' ) );
ck( 'an unclosed script or SVG is left as it is, with the sections before it',
    array( array_column( WPCPM_Guide_Text::passages( '<h2>One</h2><p>Kept.</p><script>var a;' ), 'body' ), array_column( WPCPM_Guide_Text::passages( '<h2>One</h2><p>Kept.</p><svg><g>' ), 'heading' ) ),
    array( array( "Kept.\nvar a;" ), array( 'One' ) ) );
ck( '<scripts> and <svgfoo> are not opening tags, so their text stays',
    WPCPM_Guide_Text::passages( '<h2 id="a">Names</h2><p>One.</p><scripts>Kept script text.</scripts><svgfoo>Kept svg text.</svgfoo><p>Two.</p>' ),
    array( array( 'heading' => 'Names', 'anchor' => 'a', 'body' => "One.\nKept script text.Kept svg text.Two." ) ) );
ck( 'an upper case <SCRIPT> closed by </SCRIPT > is taken out, and so is a <Style> and an <SVG/>-style opening with attributes',
    WPCPM_Guide_Text::passages( '<h2 id="a">Case</h2><p>One.</p><SCRIPT>Hidden script.</SCRIPT ><Style media="all">Hidden style.</STYLE><SVG' . "\t" . 'class="x">Hidden svg.</sVg' . "\n" . '><p>Two.</p>' ),
    array( array( 'heading' => 'Case', 'anchor' => 'a', 'body' => "One.\nTwo." ) ) );

// A public page is checked at the question (finding 1) -----------------------------------------------

world();
$GLOBALS['posts'][106]->post_content = '<h2 id="q">Quokka</h2><p>The quokka answer.</p>';
WPCPM_Guide_Sources::rebuild( false );
WPCPM_Guide_Sources::sync_handbook( $fixture_file );

/** The sources a question's words reach for an asker, as ask() ranks them: what they read, less what is left out. */
function reached( $who, $words = 'quokka' ) {
	return array_column( WPCPM_Guide_Search::rank( array( $words ), WPCPM_Guide_Sources::audiences_for( $who ), WPCPM_Guide_Sources::home_for( $who ), 5, WPCPM_Guide_Sources::excluded_for( $who ) ), 'source' );
}

ck( 'a public FAQ is left out for nobody, and a student, a visitor and an Administrator are given it',
    array( WPCPM_Guide_Sources::excluded_for( $student ), WPCPM_Guide_Sources::excluded_for( $visitor ), reached( $student ), reached( $visitor ), reached( $admin ) ),
    array( array(), array(), array( 'page:106' ), array( 'page:106' ), array( 'page:106' ) ) );

$GLOBALS['levels'][106] = 'administrator';
ck( 'the FAQ made Administrators only by its level alone, with no save and no sweep: its passages are still in the index, as `public`',
    array( rows_of( 'page:106' ), WPCPM_Guide_Index::sources()['page:106']['audience'], in_array( 'public', WPCPM_Guide_Sources::audiences_for( $student ), true ) ),
    array( 1, 'public', true ) );
ck( 'and yet it is left out for a student, a mentor and a visitor at once',
    array( WPCPM_Guide_Sources::excluded_for( $student ), reached( $student ), reached( $mentor ), reached( $visitor ) ),
    array( array( 'page:106' ), array(), array(), array() ) );
ck( 'while an Administrator, whom the level lets in, still reads it',
    array( WPCPM_Guide_Sources::excluded_for( $admin ), reached( $admin ) ), array( array(), array( 'page:106' ) ) );
$GLOBALS['levels'][106] = 'wpcpm_mentor';
ck( 'at a role\'s level, the role reads it and nobody else does', array( reached( $mentor ), reached( $student ), reached( $visitor ) ), array( array( 'page:106' ), array(), array() ) );
$GLOBALS['levels'][106] = 'public';

$GLOBALS['posts'][106]->post_status = 'draft';
ck( 'a FAQ made a draft, not yet swept, is left out for everybody, an Administrator included',
    array( WPCPM_Guide_Sources::excluded_for( $admin ), reached( $admin ), reached( $student ) ), array( array( 'page:106' ), array(), array() ) );
$GLOBALS['posts'][106]->post_status   = 'publish';
$GLOBALS['posts'][106]->post_password = 'secret';
ck( 'and so is a FAQ given a password', array( WPCPM_Guide_Sources::excluded_for( $admin ), reached( $admin ), reached( $visitor ) ), array( array( 'page:106' ), array(), array() ) );
$GLOBALS['posts'][106]->post_password = '';
$faq_page = $GLOBALS['posts'][106];
unset( $GLOBALS['posts'][106] );
ck( 'and so is one deleted while its passages wait to be taken out', array( WPCPM_Guide_Sources::excluded_for( $admin ), reached( $admin ) ), array( array( 'page:106' ), array() ) );
$GLOBALS['posts'][106] = $faq_page;

// A page a filter adds as public is checked the same way.
$GLOBALS['filters']['wpcpm_guide_pages'] = function ( $pages ) {
	$pages['notes'] = array( 'audience' => 'public', 'kind' => 'faq' );
	return $pages;
};
mk_page( 110, 'notes', 'public', 'The wombat note.' );
WPCPM_Guide_Sources::index_page( $GLOBALS['posts'][110] );
ck( 'a page a filter adds as public is read by everybody while it is public', reached( $visitor, 'wombat' ), array( 'page:110' ) );
$GLOBALS['levels'][110] = 'wpcpm_mentor';
ck( 'and given a mentor\'s level, is left out for a student at once and read by a mentor',
    array( WPCPM_Guide_Sources::excluded_for( $student ), reached( $student, 'wombat' ), reached( $mentor, 'wombat' ) ), array( array( 'page:110' ), array(), array( 'page:110' ) ) );
unset( $GLOBALS['filters']['wpcpm_guide_pages'] );
ck( 'even once the filter no longer lists it: a page the index holds is checked whatever lists it', reached( $student, 'wombat' ), array() );

// The handbook is read by everybody; only `page:` sources of audience `public` are checked.
$GLOBALS['levels'][106] = 'administrator';
$handbook_reached = array_values( array_filter( reached( $visitor, 'mentor checklist call' ), function ( $s ) { return 0 === strpos( $s, 'handbook:' ); } ) );
ck( 'the handbook stays readable by a visitor while the FAQ is left out, and no handbook source is ever in the list',
    array( array() !== $handbook_reached, WPCPM_Guide_Sources::excluded_for( $visitor ) ), array( true, array( 'page:106', 'page:110' ) ) );
$GLOBALS['levels'][106] = 'public';
$GLOBALS['levels'][102] = 'administrator';
ck( 'a guide is not in the list: its audience is its own, and audiences_for() already asks its page', WPCPM_Guide_Sources::excluded_for( $student ), array( 'page:110' ) );
$GLOBALS['levels'][102] = 'wpcpm_mentor';

// rank()'s last parameter: the keys left out, each a placeholder, and the scores unmoved.
$full = WPCPM_Guide_Search::rank( array( 'quokka mentor checklist' ), array( 'public' ), '', 5 );
$less = WPCPM_Guide_Search::rank( array( 'quokka mentor checklist' ), array( 'public' ), '', 5, array( 'page:106' ) );
$by   = function ( $rows ) { return array_column( $rows, 'score', 'id' ); };
ck( 'rank() leaves out the sources it is given, and the passages it keeps score as they did (N and df are the whole index\'s)',
    array( in_array( 'page:106', array_column( $full, 'source' ), true ), in_array( 'page:106', array_column( $less, 'source' ), true ), array_intersect_key( $by( $full ), $by( $less ) ) === array_intersect_key( $by( $less ), $by( $full ) ) ),
    array( true, false, true ) );
ck( 'a key is a value, never SQL: a quote in one leaves nothing out', array_column( WPCPM_Guide_Search::rank( array( 'quokka' ), array( 'public' ), '', 5, array( "page:106' OR '1'='1", '' ) ), 'source' ), array( 'page:106' ) );

// An incomplete handbook entry deletes nothing (finding 2) ---------------------------------------------

/** The handbook's route answer with some pages changed, as a saved file sync_handbook() reads. */
function hb_file( array $pages ) {
	$temp = tempnam( sys_get_temp_dir(), 'hb' );
	file_put_contents( $temp, json_encode( array_values( $pages ) ) );
	return $temp;
}

world();
WPCPM_Guide_Sources::sync_handbook( $fixture_file );
$data = json_decode( $hb_json, true );
$ids  = array_column( $data, 'id' );
$at   = array_flip( $ids );
ck( 'the pages these checks change are in the fixture and the index',
    array( isset( $at[90164], $at[96113], $at[88161], $at[96266] ), rows_of( 'handbook:96113' ) > 0, rows_of( 'handbook:88161' ) > 0 ), array( true, true, true ) );

$changed                                 = $data;
$changed[ $at[90164] ]['modified_gmt']   = '2026-11-18T10:00:00';
unset( $changed[ $at[96113] ]['modified_gmt'] );
$rows_96113                              = rows_of( 'handbook:96113' );
$temp                                    = hb_file( $changed );
$r                                       = WPCPM_Guide_Sources::sync_handbook( $temp );
unlink( $temp );
$state = get_option( WPCPM_Guide_Sources::OPT_HANDBOOK );
ck( 'one page moved and an indexed one sent without its modified time: the moved one is written, the other keeps its passages, nothing is removed',
    array( $r['ok'], $r['changed'], $r['removed'], isset( WPCPM_Guide_Index::sources()['handbook:96113'] ), rows_of( 'handbook:96113' ), WPCPM_Guide_Index::sources()['handbook:90164']['modified'] ),
    array( true, 1, 0, true, $rows_96113, '2026-11-18T10:00:00' ) );
ck( 'and the sync\'s result and its stored state say one entry came incomplete',
    array( $r['malformed'], isset( $state['malformed'] ) ? $state['malformed'] : null, $r['pages'], $state['pages'] ), array( 1, 1, 12, 12 ) );

$broken = $data;
$broken[ $at[88161] ]['link']         = array( 'not', 'an', 'address' );
$broken[ $at[96266] ]['modified_gmt'] = 20261118;
unset( $broken[ $at[96113] ]['link'] );
$temp = hb_file( $broken );
$r    = WPCPM_Guide_Sources::sync_handbook( $temp );
unlink( $temp );
ck( 'an address that is not text, a modified time that is not text, an address missing: each keeps its passages and is counted',
    array( $r['ok'], $r['removed'], $r['malformed'], rows_of( 'handbook:88161' ) > 0, rows_of( 'handbook:96266' ) > 0, rows_of( 'handbook:96113' ) > 0, count( keys_like( 'handbook:' ) ) ),
    array( true, 0, 3, true, true, true, 12 ) );

$no_id = $data;
unset( $no_id[ $at[88161] ]['id'] );
$no_id[] = array( 'id' => 'not-a-number', 'modified_gmt' => '2026-11-18T10:00:00' );
$no_id[] = array( 'id' => 0, 'link' => 'https://make.wordpress.org/community/handbook/education/credits/zero/' );
$temp    = hb_file( $no_id );
$r       = WPCPM_Guide_Sources::sync_handbook( $temp );
unlink( $temp );
$rows_88161 = rows_of( 'handbook:88161' );
$state      = get_option( WPCPM_Guide_Sources::OPT_HANDBOOK );
ck( 'an entry with no usable id (removed, \'not-a-number\', 0) is counted as incomplete: the page that lost its id keeps its passages, and nothing is removed',
    array( $r['ok'], $r['malformed'], $r['removed'], $r['changed'], isset( WPCPM_Guide_Index::sources()['handbook:88161'] ), rows_of( 'handbook:88161' ) === $rows_88161 && $rows_88161 > 0, $state['malformed'], $r['pages'] ),
    array( true, 3, 0, 0, true, true, 3, 12 ) );

$no_id_moved                              = $no_id;
$no_id_moved[ $at[90164] ]['modified_gmt'] = '2026-11-19T10:00:00';
unset( $no_id_moved[ $at[96266] ] );
$temp = hb_file( $no_id_moved );
$r    = WPCPM_Guide_Sources::sync_handbook( $temp );
unlink( $temp );
ck( 'with an id-less entry in the answer, a whole entry is still written, a page truly gone is still not removed, and the sync stays ok',
    array( $r['ok'], $r['changed'], $r['removed'], $r['malformed'], WPCPM_Guide_Index::sources()['handbook:90164']['modified'], isset( WPCPM_Guide_Index::sources()['handbook:96266'] ) ),
    array( true, 1, 0, 3, '2026-11-19T10:00:00', true ) );

$temp = hb_file( array_values( array_filter( $data, function ( $p ) { return 96266 !== $p['id']; } ) ) );
$r    = WPCPM_Guide_Sources::sync_handbook( $temp );
unlink( $temp );
ck( 'a following whole answer that truly drops a page removes it, and it alone',
    array( $r['ok'], $r['malformed'], $r['removed'], isset( WPCPM_Guide_Index::sources()['handbook:96266'] ), isset( WPCPM_Guide_Index::sources()['handbook:88161'] ), isset( get_option( WPCPM_Guide_Sources::OPT_HANDBOOK )['malformed'] ) ),
    array( true, 0, 1, false, true, false ) );

$temp = hb_file( $data );
$r    = WPCPM_Guide_Sources::sync_handbook( $temp );
unlink( $temp );
ck( 'a whole answer again writes the page back, counts nothing incomplete, and the state is a clean sync\'s',
    array( $r['ok'], $r['changed'], $r['malformed'], array_keys( get_option( WPCPM_Guide_Sources::OPT_HANDBOOK ) ) ),
    array( true, 1, 0, array( 'synced_at', 'ok', 'error', 'pages', 'more' ) ) );

// A write the database refused fails the sync (finding 4) --------------------------------------------------

/** The suites' database, refusing every passage of one source, as a full disk or a lost connection would. */
class WPCPM_Test_Refusing_Wpdb extends WPCPM_Test_Wpdb {
	public $refuse = '';
	public function insert( $table, array $data, $format = null ) {
		if ( '' !== $this->refuse && isset( $data['source'] ) && $this->refuse === $data['source'] ) {
			$this->last_error = 'refused';
			return false;
		}
		return parent::insert( $table, $data, $format );
	}
}

$hb_failed = function ( $n ) {
	return sprintf( '%d of the handbook\'s pages could not be updated in the index. The next run tries again.', $n );
};

world();
$GLOBALS['wpdb'] = new WPCPM_Test_Refusing_Wpdb();
$GLOBALS['wpdb']->create_guide_tables();
WPCPM_Guide_Sources::sync_handbook( $fixture_file );
$moved                                = $data;
$moved[ $at[90164] ]['modified_gmt']  = '2026-11-18T10:00:00';
$moved[ $at[96113] ]['modified_gmt']  = '2026-11-18T10:00:00';
$GLOBALS['wpdb']->refuse              = 'handbook:90164';
$temp                                 = hb_file( $moved );
$r                                    = WPCPM_Guide_Sources::sync_handbook( $temp );
$state                                = get_option( WPCPM_Guide_Sources::OPT_HANDBOOK );
ck( 'two pages moved and the database refuses one: only the one written counts as changed, and the sync fails, saying how many pages failed',
    array( $r['ok'], $r['changed'], $r['failed'], $r['error'], rows_of( 'handbook:90164' ), isset( WPCPM_Guide_Index::sources()['handbook:90164']['error'] ) ),
    array( false, 1, 1, $hb_failed( 1 ), 0, true ) );
ck( 'and so does the stored state, which the Need help? screen and guides status read',
    array( $state['ok'], $state['error'], isset( $state['failed'] ) ? $state['failed'] : null ), array( false, $hb_failed( 1 ), 1 ) );
$GLOBALS['wpdb']->refuse = '';
$r                       = WPCPM_Guide_Sources::sync_handbook( $temp );
unlink( $temp );
ck( 'the next run writes the refused page again, though its time matches, and the sync is ok',
    array( $r['ok'], $r['changed'], $r['failed'], $r['error'], rows_of( 'handbook:90164' ) > 0, array_keys( get_option( WPCPM_Guide_Sources::OPT_HANDBOOK ) ) ),
    array( true, 1, 0, '', true, array( 'synced_at', 'ok', 'error', 'pages', 'more' ) ) );

$GLOBALS['wpdb']->query( 'DROP TABLE wp_wpcpm_guide_terms' );
$gone = array_values( array_filter( $moved, function ( $p ) { return 88161 !== $p['id']; } ) );
$temp = hb_file( $gone );
$r    = WPCPM_Guide_Sources::sync_handbook( $temp );
unlink( $temp );
ck( 'a page gone from the answer whose removal the database refuses is not counted as removed, and the sync fails',
    array( $r['ok'], $r['removed'], $r['failed'], $r['error'], isset( WPCPM_Guide_Index::sources()['handbook:88161'] ) ),
    array( false, 0, 1, $hb_failed( 1 ), true ) );
$GLOBALS['wpdb']->create_guide_tables();

world();
$GLOBALS['wpdb'] = new WPCPM_Test_Refusing_Wpdb();
$GLOBALS['wpdb']->create_guide_tables();
$GLOBALS['wpdb']->refuse = 'handbook:77962';
$r                       = WPCPM_Guide_Sources::sync_handbook( $fixture_file );
ck( 'a first sync with one page refused: eleven written, one failed, and the result is no success',
    array( $r['ok'], $r['changed'], $r['failed'], $r['error'] ), array( false, 11, 1, $hb_failed( 1 ) ) );
WPCPM_Guide_Sources::daily();
ck( 'the daily run records the same failure', array( get_option( WPCPM_Guide_Sources::OPT_HANDBOOK )['ok'], get_option( WPCPM_Guide_Sources::OPT_HANDBOOK )['failed'] ), array( false, 1 ) );
$GLOBALS['wpdb']->refuse = '';

// The section titles of a guide, for the search-words call (ruling T-1) -------------------------------------

fresh_index();
$long_title = str_repeat( 'Students who have gone quiet and what to do about it ', 3 );
WPCPM_Guide_Index::write( 'page:559', meta( 'guide', 'mentor', 559, 'Mentor guide', 'https://example.test/mentor-guide/' ), array(
	passage( '', '', 'Welcome to the guide.' ),
	passage( 'Your students', 'your-students', 'Who your students are.' ),
	passage( 'Your students > Students who have gone quiet', 'quiet', 'Not seen on the site for two weeks.' ),
	passage( 'Your students > Students who have gone quiet', 'quiet', 'A second part of the same long section.' ),
	passage( $long_title, 'long', 'A section with a long title.' ),
	passage( 'Calls', 'calls', 'Book a call.' ),
) );
WPCPM_Guide_Index::write( 'page:558', meta( 'guide', 'student', 558, 'Student guide', 'https://example.test/student-guide/' ), array( passage( 'Your report', 'report', 'Send it weekly.' ) ) );
WPCPM_Guide_Index::write( 'page:2746', meta( 'faq', 'public', 2746, 'FAQ', 'https://example.test/faq/' ), array( passage( 'Does it cost anything?', 'cost', 'No.' ) ) );
WPCPM_Guide_Index::write( 'handbook:1', meta( 'handbook', 'mentor', 0, 'Handbook', 'https://example.test/h/' ), array( passage( 'A handbook section', '', 'Text.' ) ) );
ck( 'headings(): an audience\'s guide titles, each once, non-empty, in the guide\'s order, each cut to 80 characters',
    WPCPM_Guide_Index::headings( 'mentor' ),
    array( 'Your students', 'Your students > Students who have gone quiet', mb_substr( $long_title, 0, 80 ), 'Calls' ) );
ck( 'only that audience\'s, and only its guide\'s: no other guide, no FAQ, no handbook',
    array( WPCPM_Guide_Index::headings( 'student' ), WPCPM_Guide_Index::headings( 'public' ), WPCPM_Guide_Index::headings( '' ), WPCPM_Guide_Index::headings( 'sponsor' ) ),
    array( array( 'Your report' ), array(), array(), array() ) );
ck( 'at most the limit, the first ones', array( WPCPM_Guide_Index::headings( 'mentor', 2 ), WPCPM_Guide_Index::headings( 'mentor', 0 ) ), array( array( 'Your students', 'Your students > Students who have gone quiet' ), array() ) );
$many = array();
for ( $i = 1; $i <= 260; $i++ ) {
	$many[] = passage( 'Section ' . $i, 's' . $i, 'Body ' . $i . '.' );
}
WPCPM_Guide_Index::write( 'page:560', meta( 'guide', 'administrator', 560, 'Administrator guide', 'https://example.test/administrator-guide/' ), $many );
$cap = WPCPM_Guide_Index::headings( 'administrator' );
ck( 'by default at most 250', array( count( $cap ), $cap[0], $cap[249] ), array( 250, 'Section 1', 'Section 250' ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
