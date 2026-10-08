<?php
/**
 * The rules for text a person typed once core's text cleaners have read it (`WPCPM_Typed_Text`).
 *
 * Core's cleaners do not hand back what was typed. `sanitize_text_field()` and
 * `sanitize_textarea_field()` write a "<" that opens no tag as `&lt;`, and the quote marks and the
 * ampersands after it as `&quot;`, `&#039;` and `&amp;`, up to the next "<" or the end. They take a
 * "<" followed by anything but white space, with the words up to the next ">", for a tag and drop it.
 * And kses, which WordPress runs on a post a person without `unfiltered_html` saves, writes every
 * other "&" as `&amp;` and every ">" as `&gt;`. So a text stored that way is longer than its box
 * counted it, its box shows the entities if it draws the stored text as it is, and a box that reads
 * every entity back hands the cleaner a tag it never saw typed. Each check below stands for one of
 * those, measured on the phrases a person types: "Ages 8 < 12 welcome, adults > 18 pay",
 * "We <3 our <b>team</b> > all", `Q&A: "blocks" & themes, it's free.`, a "<" at the end of a line,
 * an emoji.
 *
 * The cleaners and the decoder are stood in for as core 7.1.2 writes them, not as a `strip_tags()`
 * that writes no entity: a stand-in that never writes `&lt;` would pass every rule here whatever it
 * did. This suite carries no kses, so what a member's post holds after kses is pinned as core 7.1.2
 * stores it (measured on a copy of core), and the rule that hands such a post to kses is checked on
 * its own (`insert_text()`).
 *
 * Run from the plugin root:  php bin/test-typed-text.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['uid']    = 0;
$GLOBALS['grants'] = array();

require_once __DIR__ . '/stubs/caps.php';

/**
 * Core's `wp_specialchars_decode()`, as 7.1.2 writes it: the five entities `&lt;`, `&gt;`, `&amp;`,
 * `&quot;` and `&#039;`, with the numeric forms core lists for each, read back once, and no other
 * entity. A stand-in built on `html_entity_decode()` reads `&copy;` too, which core does not.
 *
 * @param string     $text        The text.
 * @param string|int $quote_style Which quote marks to read back.
 * @return string
 */
function wp_specialchars_decode( $text, $quote_style = ENT_NOQUOTES ) {
	$text = (string) $text;

	if ( '' === $text || false === strpos( $text, '&' ) ) {
		return $text;
	}

	if ( empty( $quote_style ) ) {
		$quote_style = ENT_NOQUOTES;
	} elseif ( ! in_array( $quote_style, array( 0, 2, 3, 'single', 'double' ), true ) ) {
		$quote_style = ENT_QUOTES;
	}

	$single      = array( '&#039;' => '\'', '&#x27;' => '\'' );
	$single_preg = array( '/&#0*39;/' => '&#039;', '/&#x0*27;/i' => '&#x27;' );
	$double      = array( '&quot;' => '"', '&#034;' => '"', '&#x22;' => '"' );
	$double_preg = array( '/&#0*34;/' => '&#034;', '/&#x0*22;/i' => '&#x22;' );
	$others      = array( '&lt;' => '<', '&#060;' => '<', '&gt;' => '>', '&#062;' => '>', '&amp;' => '&', '&#038;' => '&', '&#x26;' => '&' );
	$others_preg = array( '/&#0*60;/' => '&#060;', '/&#0*62;/' => '&#062;', '/&#0*38;/' => '&#038;', '/&#x0*26;/i' => '&#x26;' );

	if ( ENT_QUOTES === $quote_style ) {
		$translation      = array_merge( $single, $double, $others );
		$translation_preg = array_merge( $single_preg, $double_preg, $others_preg );
	} elseif ( ENT_COMPAT === $quote_style || 'double' === $quote_style ) {
		$translation      = array_merge( $double, $others );
		$translation_preg = array_merge( $double_preg, $others_preg );
	} elseif ( 'single' === $quote_style ) {
		$translation      = array_merge( $single, $others );
		$translation_preg = array_merge( $single_preg, $others_preg );
	} else {
		$translation      = $others;
		$translation_preg = $others_preg;
	}

	$text = preg_replace( array_keys( $translation_preg ), array_values( $translation_preg ), $text );

	return strtr( $text, $translation );
}

/**
 * Core's `_sanitize_text_fields()`, as 7.1.2 writes it, which both cleaners call: invalid UTF-8
 * read as nothing; a "<" with no ">" before the next "<" or the end escaped as `esc_html()` escapes
 * it, an entity it finds kept (`wp_pre_kses_less_than()`); every tag stripped and the ends trimmed
 * (`wp_strip_all_tags()`); a "<" a line feed follows written `&lt;`; for a one-line field every run
 * of white space one space; and every percent octet removed. One thing of core's is left out: in
 * the stretch it escapes, core also writes a numeric entity typed out with fewer than three digits
 * with three (`&#62;` as `&#062;`). No typing here holds one; with none, this answers as core does
 * (measured on 100,000 random texts).
 *
 * @param string $str           What was posted.
 * @param bool   $keep_newlines Whether the field is a text area.
 * @return string
 */
function wpcpm_test_clean( $str, $keep_newlines ) {
	$filtered = (string) $str;

	if ( ! mb_check_encoding( $filtered, 'UTF-8' ) ) {
		return '';
	}

	if ( false !== strpos( $filtered, '<' ) ) {
		$filtered = preg_replace_callback(
			'%<[^>]*?((?=<)|>|$)%',
			function ( $matches ) {
				return false === strpos( $matches[0], '>' ) ? htmlspecialchars( $matches[0], ENT_QUOTES, 'UTF-8', false ) : $matches[0];
			},
			$filtered
		);
		$filtered = trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $filtered ) ) );
		$filtered = str_replace( "<\n", "&lt;\n", $filtered );
	}

	if ( ! $keep_newlines ) {
		$filtered = preg_replace( '/[\r\n\t ]+/', ' ', $filtered );
	}

	$filtered = trim( $filtered );
	$found    = false;

	while ( preg_match( '/%[a-f0-9]{2}/i', $filtered, $match ) ) {
		$filtered = str_replace( $match[0], '', $filtered );
		$found    = true;
	}

	if ( $found ) {
		$filtered = trim( preg_replace( '/ +/', ' ', $filtered ) );
	}

	return $filtered;
}

function sanitize_text_field( $str ) { return wpcpm_test_clean( $str, false ); }
function sanitize_textarea_field( $str ) { return wpcpm_test_clean( $str, true ); }

/**
 * Core's `esc_attr()` as 7.1.2 has it: `_wp_specialchars()` with `$double_encode` false, so an
 * entity it finds in the text is kept as it is and only a bare "&" is written `&amp;`. A text input
 * drawn from `typed_text()` through it shows a `&gt;` the read-back holds as ">".
 *
 * @param string $text The value.
 * @return string
 */
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
}

/**
 * Core's `esc_textarea()`, which escapes every "&" again, a held `&gt;` included.
 *
 * @param string $text The value.
 * @return string
 */
function esc_textarea( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * What a browser shows for the markup of a box: each entity read once, and for a text area a CR LF
 * in the markup read as LF.
 *
 * @param string $markup The escaped value, as written into the page.
 * @param bool   $area   Whether the box is a text area.
 * @return string
 */
function browser_shows( $markup, $area = false ) {
	return html_entity_decode( $area ? str_replace( "\r\n", "\n", (string) $markup ) : (string) $markup, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
}

require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';

$fails = 0;
$total = 0;

/**
 * Assert and report.
 *
 * @param string $label What is being checked.
 * @param mixed  $got   Actual.
 * @param mixed  $want  Expected.
 */
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

/**
 * What a browser posts back for a text area drawn from a text, untouched: the text it shows, each
 * line break as CR LF.
 *
 * @param string $drawn The text the box was drawn with, before `esc_textarea()`.
 * @return string
 */
function posted_back( $drawn ) {
	return (string) preg_replace( "/\r\n|\r|\n/", "\r\n", (string) $drawn );
}

/**
 * The person on the request: a sponsor member, who holds no `unfiltered_html`, or an
 * administrator, who does.
 *
 * @param bool $unfiltered Whether they hold `unfiltered_html`.
 */
function as_person( $unfiltered ) {
	$GLOBALS['uid']    = $unfiltered ? 1 : 2;
	$GLOBALS['grants'] = $unfiltered ? array( 1 => array( 'unfiltered_html' ) ) : array();
}

$limit = 2000;

$ages      = 'Ages 8 < 12 welcome, adults > 18 pay';
$ages_post = 'Ages 8 &lt; 12 welcome, adults &gt; 18 pay';
$team      = 'We <3 our <b>team</b> > all';
$team_meta = 'We &lt;3 our team > all';
$team_post = 'We &lt;3 our team &gt; all';
$team_box  = 'We <3 our team &gt; all';
$qa        = 'Q&A: "blocks" & themes, it\'s free.';
$qa_post   = 'Q&amp;A: "blocks" &amp; themes, it\'s free.';
$qa_after  = 'Ages 8 < 12. Q&A: "blocks" & themes, it\'s free.';
$qa_clean  = 'Ages 8 &lt; 12. Q&amp;A: &quot;blocks&quot; &amp; themes, it&#039;s free.';

echo "=== The stand-ins write what core 7.1.2 writes ===\n";

// Each of these is core's answer, read off core 7.1.2. A stand-in that answered otherwise would
// make every rule below pass or fail for the stand-in's sake.
ck( 'the cleaner keeps "Ages 8 < 12 welcome, adults > 18 pay" as typed: a "<" that a space follows opens no tag',
    array( sanitize_text_field( $ages ), sanitize_textarea_field( $ages ) ), array( $ages, $ages ) );
ck( 'it takes "<b>" and "</b>" out of "We <3 our <b>team</b> > all" and writes the "<" before them as an entity',
    array( sanitize_text_field( $team ), sanitize_textarea_field( $team ) ), array( $team_meta, $team_meta ) );
ck( 'after a "<" with no ">" before the end it writes the quote marks and the ampersands as entities',
    sanitize_textarea_field( $qa_after ), $qa_clean );
ck( 'and without a "<" it leaves them as typed', sanitize_textarea_field( $qa ), $qa );
ck( 'the decoder reads the five entities and their numeric forms back once, and no other',
    array( wp_specialchars_decode( '&lt;&gt;&amp;&quot;&#039;&#62;&#x26;', ENT_QUOTES ), wp_specialchars_decode( '&amp;lt; &copy;', ENT_QUOTES ) ),
    array( '<>&"\'>&', '&lt; &copy;' ) );

echo "\n=== typed_length(): what the person typed, as their box counted it ===\n";

as_person( false );

ck( '"Ages 8 < 12 welcome, adults > 18 pay" counts 36 as typed, as the cleaner left it, and as a member\'s post holds it',
    array( WPCPM_Typed_Text::typed_length( $ages ), WPCPM_Typed_Text::typed_length( sanitize_textarea_field( $ages ) ), WPCPM_Typed_Text::typed_length( $ages_post ) ),
    array( 36, 36, 36 ) );
ck( 'an entity the cleaner wrote is the one character it stands for: the Q&A line after a "<" counts as typed',
    array( WPCPM_Typed_Text::typed_length( $qa_clean ), mb_strlen( $qa_after ) ), array( 47, 47 ) );
ck( 'and so is each "&amp;" kses writes into a member\'s post', array( WPCPM_Typed_Text::typed_length( $qa_post ), mb_strlen( $qa ) ), array( 34, 34 ) );
ck( 'a line break is one character, posted as CR LF or stored as LF',
    array( WPCPM_Typed_Text::typed_length( "Line one\r\nLine two" ), WPCPM_Typed_Text::typed_length( "Line one\nLine two" ) ), array( 17, 17 ) );
ck( 'an emoji is one character, as the store counts code points', array( WPCPM_Typed_Text::typed_length( 'Thanks 🎉' ), WPCPM_Typed_Text::typed_length( 'We &lt;3 WordPress 🎉' ) ), array( 8, 17 ) );
ck( 'a typed-out entity is read once and no deeper: "&amp;lt;" counts as the four characters of "&lt;"',
    WPCPM_Typed_Text::typed_length( '&amp;lt;' ), 4 );
ck( 'an empty text counts nothing', array( WPCPM_Typed_Text::typed_length( '' ), WPCPM_Typed_Text::typed_length( null ) ), array( 0, 0 ) );

echo "\n=== typed_text(): a stored text as its box draws it ===\n";

ck( '"Ages 8 < 12 welcome, adults > 18 pay" draws as typed, as the cleaner left it and as a member\'s post holds it',
    array( WPCPM_Typed_Text::typed_text( sanitize_textarea_field( $ages ) ), WPCPM_Typed_Text::typed_text( $ages_post ) ), array( $ages, $ages ) );
ck( 'the Q&A line draws as typed, with or without the entities the cleaner and kses wrote',
    array( WPCPM_Typed_Text::typed_text( $qa ), WPCPM_Typed_Text::typed_text( $qa_post ), WPCPM_Typed_Text::typed_text( $qa_clean ) ),
    array( $qa, $qa, $qa_after ) );

// The words a typed tag leaves. The cleaner stores the "<" before "<b>" as `&lt;` and keeps the ">"
// after "</b>", so read back in full the box would hold "<3 our team >": a tag to the cleaner, which
// drops it with every word in it when the box is posted. The ">" is drawn as the entity instead.
ck( '"We <3 our <b>team</b> > all" draws "We <3 our team &gt; all", stored by the cleaner alone or by kses for a member',
    array( WPCPM_Typed_Text::typed_text( $team_meta ), WPCPM_Typed_Text::typed_text( $team_post ) ), array( $team_box, $team_box ) );
ck( 'because read back in full the cleaner would take "<3 our team >" for a tag and drop the words',
    array( sanitize_text_field( 'We <3 our team > all' ), sanitize_textarea_field( 'We <3 our team > all' ) ), array( 'We all', 'We  all' ) );
ck( 'drawn with the entity, posted back unedited it is cleaned to the same words, and every word is kept',
    array(
        sanitize_textarea_field( posted_back( $team_box ) ),
        WPCPM_Typed_Text::same_text( sanitize_textarea_field( posted_back( $team_box ) ), $team_meta ),
        WPCPM_Typed_Text::same_text( sanitize_textarea_field( posted_back( $team_box ) ), $team_post ),
    ),
    array( $team_post, true, true ) );
ck( 'every ">" from such a "<" to the next one is held, and none before it',
    WPCPM_Typed_Text::typed_text( 'Adults &gt; 18. We &lt;3 our team > all > the rest' ), 'Adults > 18. We <3 our team &gt; all &gt; the rest' );

// A "<" at the end of a line. The text area cleaner writes one a line feed follows as `&lt;`
// and keeps one a CR or a tab follows as typed; a member's post holds both as entities.
foreach ( array( 'LF' => "\n", 'CR LF' => "\r\n", 'tab' => "\t" ) as $name => $space ) {
	$typed  = 'Ages 8 <' . $space . '12 welcome, adults > 18 pay';
	$meta   = sanitize_textarea_field( $typed );
	$member = WPCPM_Typed_Text::insert_text( $meta );

	ck( sprintf( 'a "<" a %s follows: stored whole by the cleaner and for a member, drawn as typed, counted as typed', $name ),
	    array(
	        WPCPM_Typed_Text::typed_text( $meta ),
	        WPCPM_Typed_Text::typed_text( $member ),
	        WPCPM_Typed_Text::typed_length( $meta ),
	        WPCPM_Typed_Text::typed_length( $member ),
	        WPCPM_Typed_Text::drawn_limit( $meta, $limit ),
	        WPCPM_Typed_Text::drawn_limit( $member, $limit ),
	    ),
	    array( $typed, $typed, 36, 36, $limit, $limit ) );
	ck( sprintf( 'and posted back unedited (a %s) it reads the same as either stored form', $name ),
	    array(
	        WPCPM_Typed_Text::same_lines( sanitize_textarea_field( posted_back( WPCPM_Typed_Text::typed_text( $meta ) ) ), $meta ),
	        WPCPM_Typed_Text::same_lines( sanitize_textarea_field( posted_back( WPCPM_Typed_Text::typed_text( $member ) ) ), $member ),
	    ),
	    array( true, true ) );
}

ck( 'the three stored forms are core\'s: LF as an entity, CR LF and tab as typed, and a member\'s post as entities',
    array(
        sanitize_textarea_field( "Ages 8 <\n12 welcome, adults > 18 pay" ),
        sanitize_textarea_field( "Ages 8 <\r\n12 welcome, adults > 18 pay" ),
        sanitize_textarea_field( "Ages 8 <\t12 welcome, adults > 18 pay" ),
        WPCPM_Typed_Text::insert_text( "Ages 8 <\r\n12 welcome, adults > 18 pay" ),
    ),
    array(
        "Ages 8 &lt;\n12 welcome, adults > 18 pay",
        "Ages 8 <\r\n12 welcome, adults > 18 pay",
        "Ages 8 <\t12 welcome, adults > 18 pay",
        "Ages 8 &lt;\r\n12 welcome, adults &gt; 18 pay",
    ) );

// A "<" that reached the store as typed, which only text that met no kses holds: from it on, an
// `&gt;` was typed out, and is drawn as typed.
ck( 'from a "<" stored as typed on, a typed-out "&gt;" stays as typed',
    WPCPM_Typed_Text::typed_text( 'Ages 8 < 12 welcome, adults > 18 pay, written &gt; in HTML' ), 'Ages 8 < 12 welcome, adults > 18 pay, written &gt; in HTML' );
ck( 'an emoji draws as typed', WPCPM_Typed_Text::typed_text( sanitize_textarea_field( 'We <3 WordPress 🎉' ) ), 'We <3 WordPress 🎉' );
ck( 'an empty text draws nothing', WPCPM_Typed_Text::typed_text( '' ), '' );

echo "\n=== attr_text(): a stored text as a text input carries it ===\n";

// A text input's value goes through esc_attr(), which keeps an entity it finds. Drawn from
// typed_text() alone, the held "&gt;" of "Save <3 on hosting &gt; plans" reaches the browser as
// ">", the box posts "Save <3 on hosting > plans", and the cleaner takes "<3 on hosting >" for a
// tag. attr_text() writes each "&" as "&amp;" first, so esc_attr() leaves the entity's own
// ampersand standing and the browser shows the four characters, as a text area does.
$save_typed = 'Save <3 on <b>hosting</b> > plans';
$save_meta  = sanitize_text_field( $save_typed );
$save_post  = 'Save &lt;3 on hosting &gt; plans';
$save_box   = 'Save <3 on hosting &gt; plans';

ck( 'the stored forms are core\'s: the cleaner leaves the ">" as typed, and a member\'s post holds it as an entity',
    array( $save_meta, WPCPM_Typed_Text::insert_text( $save_meta ) ), array( 'Save &lt;3 on hosting > plans', $save_post ) );

foreach ( array( 'post meta' => $save_meta, 'a member\'s post title' => $save_post ) as $store => $stored ) {
	$plain  = esc_attr( WPCPM_Typed_Text::typed_text( $stored ) );
	$helped = esc_attr( WPCPM_Typed_Text::attr_text( $stored ) );
	$back   = sanitize_text_field( browser_shows( $plain ) );
	$back_h = sanitize_text_field( browser_shows( $helped ) );

	ck( sprintf( 'in %s, esc_attr( typed_text() ) shows the held "&gt;" as ">" and the box posts back "Save plans": the words are lost', $store ),
	    array( $plain, browser_shows( $plain ), $back, WPCPM_Typed_Text::same_text( $back, $stored ) ),
	    array( 'Save &lt;3 on hosting &gt; plans', 'Save <3 on hosting > plans', 'Save plans', false ) );
	ck( sprintf( 'in %s, esc_attr( attr_text() ) shows "Save <3 on hosting &gt; plans" and posts back the stored text', $store ),
	    array( $helped, browser_shows( $helped ), $back_h, WPCPM_Typed_Text::same_text( $back_h, $stored ) ),
	    array( 'Save &lt;3 on hosting &amp;gt; plans', $save_box, $save_post, true ) );
	ck( sprintf( 'in %s, the box with the helper leaves the store\'s room (limit 80: 54), the box without it 57', $store ),
	    array(
	        WPCPM_Typed_Text::drawn_limit( $stored, 80 ) - mb_strlen( browser_shows( $helped ) ),
	        WPCPM_Typed_Text::drawn_limit( $stored, 80 ) - mb_strlen( browser_shows( $plain ) ),
	        80 - WPCPM_Typed_Text::typed_length( $stored ),
	    ),
	    array( 54, 57, 54 ) );
}

ck( 'attr_text() is typed_text() with each "&" written "&amp;", and nothing else is escaped',
    array(
        WPCPM_Typed_Text::attr_text( $save_post ),
        WPCPM_Typed_Text::attr_text( $qa_post ),
        WPCPM_Typed_Text::attr_text( $qa_clean ),
        WPCPM_Typed_Text::attr_text( "Line one\r\nLine two" ),
        WPCPM_Typed_Text::attr_text( '' ),
    ),
    array(
        'Save <3 on hosting &amp;gt; plans',
        'Q&amp;A: "blocks" &amp; themes, it\'s free.',
        'Ages 8 < 12. Q&amp;A: "blocks" &amp; themes, it\'s free.',
        "Line one\r\nLine two",
        '',
    ) );
ck( 'a text input drawn through esc_attr( attr_text() ) shows what a text area drawn through esc_textarea( typed_text() ) shows, for every phrase',
    array_map(
        function ( $stored ) {
            return browser_shows( esc_attr( WPCPM_Typed_Text::attr_text( $stored ) ) ) === browser_shows( esc_textarea( WPCPM_Typed_Text::typed_text( $stored ) ), true )
                && browser_shows( esc_attr( WPCPM_Typed_Text::attr_text( $stored ) ) ) === WPCPM_Typed_Text::typed_text( $stored );
        },
        array( $qa, $qa_post, $qa_clean, $ages, $ages_post, $team_meta, $team_post, 'Thanks 🎉', '' )
    ),
    array_fill( 0, 9, true ) );

echo "\n=== same_text() and same_lines(): whether a box posted back changed anything ===\n";

ck( 'two texts read the same once each entity is read back', array( WPCPM_Typed_Text::same_text( $team_meta, $team_post ), WPCPM_Typed_Text::same_text( $qa_post, $qa ) ), array( true, true ) );
ck( 'and different words do not', WPCPM_Typed_Text::same_text( 'Q&amp;A: themes', 'Q&A: blocks' ), false );
ck( 'same_text() reads a line break as it was posted: CR LF is not LF', WPCPM_Typed_Text::same_text( "Line one\r\nLine two", "Line one\nLine two" ), false );
ck( 'same_lines() reads CR LF, CR and LF as one break on both sides',
    array(
        WPCPM_Typed_Text::same_lines( "Line one\r\nLine two", "Line one\nLine two" ),
        WPCPM_Typed_Text::same_lines( "Line one\rLine two", "Line one\r\nLine two" ),
        WPCPM_Typed_Text::same_lines( "Q&amp;A\r\nthemes", "Q&A\nthemes" ),
    ),
    array( true, true, true ) );
ck( 'but a break added or taken out is a change', WPCPM_Typed_Text::same_lines( "Line one\n\nLine two", "Line one\nLine two" ), false );

echo "\n=== drawn_limit(): the maxlength of a box drawn by typed_text() ===\n";

ck( '"We <3 our <b>team</b> > all" holds one "&gt;", so its box takes the limit and 3',
    array( WPCPM_Typed_Text::drawn_limit( $team_meta, $limit ), WPCPM_Typed_Text::drawn_limit( $team_post, $limit ) ), array( $limit + 3, $limit + 3 ) );
ck( 'two held, 6', WPCPM_Typed_Text::drawn_limit( 'We &lt;3 our team > all > the rest', $limit ), $limit + 6 );
ck( 'nothing held, the limit: the Q&A line, two lines with CR LF, an emoji',
    array(
        WPCPM_Typed_Text::drawn_limit( $qa_clean, $limit ),
        WPCPM_Typed_Text::drawn_limit( "Line one\r\nLine two", $limit ),
        WPCPM_Typed_Text::drawn_limit( 'Thanks 🎉', $limit ),
        WPCPM_Typed_Text::drawn_limit( '', $limit ),
    ),
    array( $limit, $limit, $limit, $limit ) );
ck( 'a typed-out "&gt;" kept after a "<" stored as typed is four characters in the box and one to the count, so 3 again',
    WPCPM_Typed_Text::drawn_limit( 'Ages 8 < 12 welcome, adults > 18 pay, written &gt; in HTML', $limit ), $limit + 3 );

// A box at its limit: what fills its maxlength is what the store accepts, and one more is not.
$room = $limit - mb_strlen( 'We <3 our team > all' );
$full = $team_box . str_repeat( 'x', $room );
ck( 'a box drawn from the held text and filled to its maxlength is a text the store accepts at its limit',
    array( mb_strlen( $full ), WPCPM_Typed_Text::typed_length( sanitize_textarea_field( $full ) ) ), array( $limit + 3, $limit ) );

echo "\n=== A description with CR LF at its limit ===\n";

$line  = $qa_after . "\r\n" . $ages . "\r\n";
$at    = str_repeat( $line, 23 );
$at   .= str_repeat( 'x', $limit - mb_strlen( str_replace( "\r\n", "\n", $at ) ) );
$over  = $at . 'x';
$kept  = sanitize_textarea_field( $at );

ck( 'typed with "<", ">", quote marks, "&" and 46 line breaks to 2,000 characters, it is stored longer and counted 2,000',
    array( mb_strlen( str_replace( "\r\n", "\n", $at ) ), substr_count( $kept, "\r\n" ), mb_strlen( $kept ) > $limit, WPCPM_Typed_Text::typed_length( $kept ) ),
    array( $limit, 46, true, $limit ) );
ck( 'one typed character more counts 2,001', WPCPM_Typed_Text::typed_length( sanitize_textarea_field( $over ) ), $limit + 1 );
ck( 'its box draws it as typed at the limit, and the plain-text mail reads it as typed',
    array( WPCPM_Typed_Text::typed_text( $kept ) === $at, WPCPM_Typed_Text::drawn_limit( $kept, $limit ), WPCPM_Typed_Text::mail_text( $kept ) === $at ),
    array( true, $limit, true ) );
ck( 'and posted back unedited it reads the same',
    WPCPM_Typed_Text::same_lines( sanitize_textarea_field( posted_back( WPCPM_Typed_Text::typed_text( $kept ) ) ), $kept ), true );

echo "\n=== mail_text(): a stored text as a plain-text mail gives it ===\n";

ck( 'each entity the cleaner and kses wrote is read back',
    array( WPCPM_Typed_Text::mail_text( $team_meta ), WPCPM_Typed_Text::mail_text( $team_post ), WPCPM_Typed_Text::mail_text( $qa_clean ), WPCPM_Typed_Text::mail_text( $qa_post ), WPCPM_Typed_Text::mail_text( $ages_post ) ),
    array( 'We <3 our team > all', 'We <3 our team > all', $qa_after, $qa, $ages ) );
ck( 'once and no deeper: a typed-out "&amp;" is mailed as "&amp;" when kses wrote it "&amp;amp;"', WPCPM_Typed_Text::mail_text( 'R&amp;amp;D' ), 'R&amp;D' );
ck( 'an entity outside the five stays as it is', WPCPM_Typed_Text::mail_text( 'Copyright &copy; 2026' ), 'Copyright &copy; 2026' );
ck( 'line breaks and an emoji are kept', WPCPM_Typed_Text::mail_text( "Thanks 🎉\r\nSee you" ), "Thanks 🎉\r\nSee you" );

echo "\n=== insert_text(): what a post write hands kses ===\n";

// kses reads a "<" with the words after it up to the next ">" as a tag and drops one it does not
// allow: core 7.1.2 stores "Ages 8 < 12 welcome, adults > 18 pay" for a member as `Ages 8  18 pay`.
as_person( false );
ck( 'for a person without unfiltered_html each "<" and ">" the cleaner left is written as an entity',
    array( WPCPM_Typed_Text::insert_text( sanitize_text_field( $ages ) ), WPCPM_Typed_Text::insert_text( $team_meta ) ),
    array( $ages_post, $team_post ) );
ck( 'and nothing else: the ampersands and quote marks are kses\'s to write', WPCPM_Typed_Text::insert_text( $qa ), $qa );
ck( 'what it writes is counted, drawn and mailed as typed',
    array( WPCPM_Typed_Text::typed_length( $ages_post ), WPCPM_Typed_Text::typed_text( $ages_post ), WPCPM_Typed_Text::mail_text( $ages_post ) ),
    array( 36, $ages, $ages ) );

as_person( true );
ck( 'a person who holds unfiltered_html meets no kses, and their words are handed over as the cleaner left them',
    array( WPCPM_Typed_Text::insert_text( $ages ), WPCPM_Typed_Text::insert_text( $team_meta ) ), array( $ages, $team_meta ) );
as_person( false );

echo "\n=== Every typing, through the cleaner ===\n";

// Typings made of the phrases' pieces, through the text area cleaner, drawn, posted back unedited
// as a browser posts a text area, and cleaned again. Seeded, so a failure names the same typing on
// every run.
mt_srand( 1122011 );

$pieces  = array( 'Ages', '8', ' < ', ' > ', '<3', '<b>', '</b>', 'team', '"blocks"', "it's", '&', 'Q&A', ' ', "\r\n", "\t", '🎉', 'all', '12', '>' );
$counts  = array(
	'post-backs that change the text' => 0,
	'texts kept whole that draw wrong' => 0,
	'boxes whose room differs from the store\'s' => 0,
);
$checked = 0;
$reached = array(
	'kept whole'               => 0,
	'drawn with a held ">"'    => 0,
	'posted back in new bytes' => 0,
);

for ( $i = 0; $i < 3000; ++$i ) {
	$typed = 'Start';

	for ( $n = mt_rand( 1, 12 ); $n > 0; --$n ) {
		$typed .= $pieces[ mt_rand( 0, count( $pieces ) - 1 ) ];
	}

	$typed .= ' end';
	$stored = sanitize_textarea_field( $typed );
	$drawn  = WPCPM_Typed_Text::typed_text( $stored );

	if ( ! WPCPM_Typed_Text::same_lines( sanitize_textarea_field( posted_back( $drawn ) ), $stored ) ) {
		++$counts['post-backs that change the text'];
	}

	if ( wp_specialchars_decode( $stored, ENT_QUOTES ) === $typed && $drawn !== $typed ) {
		++$counts['texts kept whole that draw wrong'];
	}

	// The room a browser leaves in the box against the room the store leaves under the limit.
	$box_room   = WPCPM_Typed_Text::drawn_limit( $stored, $limit ) - mb_strlen( str_replace( "\r\n", "\n", $drawn ) );
	$store_room = $limit - WPCPM_Typed_Text::typed_length( $stored );

	if ( $box_room !== $store_room ) {
		++$counts['boxes whose room differs from the store\'s'];
	}

	// What the typings reach, so the check above cannot go green over nothing.
	$reached['kept whole']               += (int) ( wp_specialchars_decode( $stored, ENT_QUOTES ) === $typed );
	$reached['drawn with a held ">"']    += (int) ( WPCPM_Typed_Text::drawn_limit( $stored, $limit ) > $limit );
	$reached['posted back in new bytes'] += (int) ( sanitize_textarea_field( posted_back( $drawn ) ) !== $stored );

	++$checked;
}

ck( sprintf( 'of %d typings none changes on an unedited post-back, none kept whole draws wrong, and every box leaves the store\'s room', $checked ),
    $counts, array_fill_keys( array_keys( $counts ), 0 ) );
ck( 'and they reach a text the cleaner keeps whole, a box that holds a ">", and a post-back the cleaner writes in other bytes, a hundred, ten and a hundred times at least',
    array( $reached['kept whole'] >= 100, $reached['drawn with a held ">"'] >= 10, $reached['posted back in new bytes'] >= 100 ),
    array( true, true, true ) );

echo "\n=== Every typing, as a text input ===\n";

// The same typings, one line, through the text field cleaner, as a cleaner leaves them and as a
// member's post holds them, drawn through esc_attr( attr_text() ): the box shows exactly what
// typed_text() says, posts back the text it was drawn from, and leaves the store's room.
mt_srand( 1122012 );

$line_pieces = array( 'Ages', '8', ' < ', ' > ', '<3', '<b>', '</b>', 'team', '"blocks"', "it's", '&', 'Q&A', ' ', '🎉', 'all', '12', '>' );
$counts_in   = array(
	'boxes that show other than typed_text()'   => 0,
	'post-backs that change the text'           => 0,
	'boxes whose room differs from the store\'s' => 0,
);
$reached_in  = array( 'a held ">"' => 0, 'lost without the helper' => 0 );
$checked_in  = 0;

for ( $i = 0; $i < 3000; ++$i ) {
	$typed = 'Start';

	for ( $n = mt_rand( 1, 12 ); $n > 0; --$n ) {
		$typed .= $line_pieces[ mt_rand( 0, count( $line_pieces ) - 1 ) ];
	}

	$cleaned = sanitize_text_field( $typed . ' end' );

	foreach ( array( $cleaned, WPCPM_Typed_Text::insert_text( $cleaned ) ) as $stored ) {
		$markup = esc_attr( WPCPM_Typed_Text::attr_text( $stored ) );
		$shown  = browser_shows( $markup );

		if ( WPCPM_Typed_Text::typed_text( $stored ) !== $shown ) {
			++$counts_in['boxes that show other than typed_text()'];
		}

		if ( ! WPCPM_Typed_Text::same_text( sanitize_text_field( $shown ), $stored ) ) {
			++$counts_in['post-backs that change the text'];
		}

		if ( WPCPM_Typed_Text::drawn_limit( $stored, $limit ) - mb_strlen( $shown ) !== $limit - WPCPM_Typed_Text::typed_length( $stored ) ) {
			++$counts_in['boxes whose room differs from the store\'s'];
		}

		$reached_in['a held ">"']             += (int) ( WPCPM_Typed_Text::drawn_limit( $stored, $limit ) > $limit );
		$reached_in['lost without the helper'] += (int) ( ! WPCPM_Typed_Text::same_text( sanitize_text_field( browser_shows( esc_attr( WPCPM_Typed_Text::typed_text( $stored ) ) ) ), $stored ) );
		++$checked_in;
	}
}

ck( sprintf( 'of %d stored texts none shows other than typed_text(), none changes on an unedited post-back, and every box leaves the store\'s room', $checked_in ),
    $counts_in, array_fill_keys( array_keys( $counts_in ), 0 ) );
ck( 'and they reach a box that holds a ">" and a text that esc_attr( typed_text() ) would have lost, ten times each at least',
    array( $reached_in['a held ">"'] >= 10, $reached_in['lost without the helper'] >= 10 ), array( true, true ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
