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
 * an emoji. And where the cleaner would take words away ("Kids <12 free, adults >18 pay", "Get
 * 10%cashback"), `cleaner_loses()` says so, and says it of nothing else.
 *
 * The cleaners and the decoder are stood in for as core 7.1.2 writes them (bin/stubs/cleaners.php
 * and bin/stubs/specialchars.php, which the sponsor suites load too), not as a `strip_tags()` that
 * writes no entity: a stand-in that never writes `&lt;` would pass every rule here whatever it did.
 * This suite carries no kses, so what a member's post holds after kses is pinned as core 7.1.2
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
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/stubs/cleaners.php';

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
 * Translation: the text as written, with the text domain it was asked through kept for a check.
 *
 * @param string $text   The text.
 * @param string $domain The text domain.
 * @return string
 */
function __( $text, $domain = 'default' ) {
	$GLOBALS['domains'][] = $domain;

	return $text;
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

/**
 * The flash a refused form's typing waits in: one value per user and channel, which `take()` hands
 * back once and clears, and `sweep()` clears unread where its test says so, and nothing for a guest.
 * The real class also remembers within a request what it handed back, and writes a slashed copy that
 * core's user meta unslashes; bin/test-flash.php pins both on it.
 */
class WPCPM_Flash {
	public static function set( $channel, $value, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : (int) $GLOBALS['uid'];
		if ( $user_id ) {
			$GLOBALS['flash'][ $user_id ][ (string) $channel ] = $value;
		}
	}
	public static function take( $channel, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : (int) $GLOBALS['uid'];
		$value   = $GLOBALS['flash'][ $user_id ][ (string) $channel ] ?? '';
		unset( $GLOBALS['flash'][ $user_id ][ (string) $channel ] );
		return $value;
	}
	public static function sweep( $stale, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : (int) $GLOBALS['uid'];
		foreach ( $GLOBALS['flash'][ $user_id ] ?? array() as $channel => $value ) {
			if ( $stale( (string) $channel, $value ) ) {
				unset( $GLOBALS['flash'][ $user_id ][ $channel ] );
			}
		}
	}
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

// A reference typed out after a "<" is not kept as typed: core's esc_html() writes each in its own
// form. A comparison that read "&#9;" and "&#009;" differently would call this a loss on core, and
// a stand-in that kept "&#9;" would never show it.
$refs_typed = 'We <3 &amp; &lt;b&gt; it&#39;s it&#039;s &copy; &#9; &#x2E; &#X41; &#0; &#XD800; &COPY; &apos;';
$refs_clean = 'We &lt;3 &amp; &lt;b&gt; it&#039;s it&#039;s &copy; &#009; &#x2E; &#x41; &amp;#0; &amp;#xD800; &amp;COPY; &amp;apos;';
ck( 'after a "<" it writes a typed-out reference as core does: a number with three digits, a small "x", and the "&" of one core does not take as "&amp;"',
    array( sanitize_text_field( $refs_typed ), sanitize_textarea_field( $refs_typed ) ), array( $refs_clean, $refs_clean ) );
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

echo "\n=== cleaner_loses(): whether the cleaner takes away more than white space ===\n";

// Each typing here loses words to the cleaner, and each cleaned value is core 7.1.2's, read off core.
// A "<" with no white space after it and a ">" before the next "<" is a tag to the cleaner, dropped
// with every word in it ("don't" opens a quote inside that tag, so the ">" after it closes nothing
// and the rest goes too), and a "%" with two hexadecimal digits after it is a URL octet, dropped.
$losses = array(
	'Kids <12 free, adults >18 pay'       => array( 'Kids 18 pay', 'Kids 18 pay' ),
	"Students <18 don't pay > adults pay" => array( 'Students', 'Students' ),
	'Get 10%cashback'                     => array( 'Get 10shback', 'Get 10shback' ),
	'We <3 our team > all'                => array( 'We all', 'We  all' ),
	'a <b>bold</b> word'                  => array( 'a bold word', 'a bold word' ),
);

foreach ( $losses as $typed => $kept ) {
	ck( sprintf( '"%s" is cleaned to "%s", and is a loss in a text input and in a text area', $typed, $kept[0] ),
	    array( sanitize_text_field( $typed ), sanitize_textarea_field( $typed ), WPCPM_Typed_Text::cleaner_loses( $typed, 'line' ), WPCPM_Typed_Text::cleaner_loses( $typed, 'lines' ) ),
	    array( $kept[0], $kept[1], true, true ) );
}

ck( 'text that is not valid UTF-8 is emptied whole by the cleaner, and is a loss',
    array( sanitize_text_field( "Kids \xC0 free" ), WPCPM_Typed_Text::cleaner_loses( "Kids \xC0 free", 'line' ), WPCPM_Typed_Text::cleaner_loses( "Kids \xC0 free", 'lines' ) ),
    array( '', true, true ) );

// Each typing here keeps every word, though the cleaner may write it in other bytes: it writes a
// "<" that opens no tag, and the quote marks and ampersands after it, as entities, writes a
// reference typed out after a "<" in core's own form, and folds the white space of a text input.
$keeps = array(
	'a "<" a space follows'                       => 'Ages 8 < 12 welcome, adults > 18 pay',
	'a "<" a tab follows'                         => "Ages 8 <\t12 welcome, adults > 18 pay",
	'a "<" a line break follows'                  => "Ages 8 <\r\n12 welcome, adults > 18 pay\nand <\nmore",
	'a "<" with no ">" after it'                  => 'We <3 WordPress',
	'a lone "&"'                                  => 'Q&A, R & D, Tom & Jerry',
	'a lone "&" after a "<"'                      => 'We <3 Q&A, R & D',
	'quote marks without a "<"'                   => $qa,
	'quote marks after a "<"'                     => $qa_after,
	'line breaks'                                 => "Line one\r\nLine two\r\n\r\nLine four\r\n",
	'tabs and runs of spaces the cleaner folds'   => "  Lots\tof\t\tspace    here  ",
	'typed-out entities'                          => '&amp; &lt;b&gt; it&#39;s it&#039;s &copy; &#9;',
	'typed-out entities after a "<"'              => $refs_typed,
	'emoji'                                       => 'Thanks 🎉 We <3 WordPress 🎉',
	'a "%" without two hexadecimal digits after it' => '50% off, 100% free, 5%-10%',
	'a "<" at the very end'                       => 'a <',
	'a "<" right after a run of spaces'           => 'Ages 8     < 12 welcome, adults > 18 pay',
	'a "<" a form feed follows'                   => "Ages 8 <\f12 welcome, adults > 18 pay",
	'a "<" a vertical tab follows'                => "Ages 8 <\v12 welcome, adults > 18 pay",
);

foreach ( $keeps as $what => $typed ) {
	ck( sprintf( 'keeps every word: %s', $what ),
	    array( WPCPM_Typed_Text::cleaner_loses( $typed, 'line' ), WPCPM_Typed_Text::cleaner_loses( $typed, 'lines' ) ), array( false, false ) );
}

ck( 'the typed-out entities after a "<" are written in other bytes, and that is not a loss',
    array( sanitize_textarea_field( $refs_typed ) === $refs_typed, WPCPM_Typed_Text::cleaner_loses( $refs_typed, 'lines' ) ), array( false, false ) );
ck( 'nothing typed, or white space alone, loses nothing',
    array( WPCPM_Typed_Text::cleaner_loses( '', 'line' ), WPCPM_Typed_Text::cleaner_loses( null, 'lines' ), WPCPM_Typed_Text::cleaner_loses( " \r\n\t ", 'line' ), WPCPM_Typed_Text::cleaner_loses( " \r\n\t ", 'lines' ) ),
    array( false, false, false, false ) );
// An array or an object, as a crafted post can send, is not cast to a string: casting an array
// warns, and casting an object without __toString() throws.
$warned = 0;
set_error_handler(
	function () use ( &$warned ) {
		++$warned;
		return true;
	}
);
$not_text = array(
	WPCPM_Typed_Text::cleaner_loses( array( 'Kids <12 free, adults >18 pay' ), 'line' ),
	WPCPM_Typed_Text::cleaner_loses( array( 'a <b>bold</b> word' ), 'lines' ),
	WPCPM_Typed_Text::cleaner_loses( new stdClass(), 'line' ),
);
restore_error_handler();
ck( 'a value that is not text, as a crafted post can send, is not read: no words of it are lost, and no warning is raised',
    array( $not_text, $warned ), array( array( false, false, false ), 0 ) );

echo "\n=== loss_message(): the refusal that names the box and the fix ===\n";

$GLOBALS['domains'] = array();
$said               = WPCPM_Typed_Text::loss_message( 'Offer text' );

ck( 'it says nothing was saved, names the box, and says how to keep every word',
    $said, 'Nothing was saved, because WordPress would remove part of what you typed in "Offer text". To keep every word, put a space after each "<" (or write "less than") and after each "%", then save again.' );
ck( 'through the plugin\'s text domain', $GLOBALS['domains'], array( 'wpcredits-program-manager' ) );
ck( 'it is plain text: a label goes in as given, and escaping it is the caller\'s',
    false !== strpos( WPCPM_Typed_Text::loss_message( 'Q&A <b>' ), 'you typed in "Q&A <b>".' ), true );

// A form whose button does something other than save says what did not happen and what to press
// again, in a whole sentence of its own per action, so a translator never meets half of one.
$GLOBALS['domains'] = array();
$actions            = array(
	'send'   => WPCPM_Typed_Text::loss_message( 'Anything else', 'send' ),
	'return' => WPCPM_Typed_Text::loss_message( 'A note for the author', 'return' ),
	'revoke' => WPCPM_Typed_Text::loss_message( 'Why it is out of force, in your own words', 'revoke' ),
	'reject' => WPCPM_Typed_Text::loss_message( 'Why, for the next Administrator who reads this', 'reject' ),
);
ck( 'for a form that sends, it says nothing was sent and to send it again',
    $actions['send'],
    'Nothing was sent, because WordPress would remove part of what you typed in "Anything else". To keep every word, put a space after each "<" (or write "less than") and after each "%", then send it again.' );
ck( 'for a return, that nothing was returned and to return it again',
    $actions['return'],
    'Nothing was returned, because WordPress would remove part of what you typed in "A note for the author". To keep every word, put a space after each "<" (or write "less than") and after each "%", then return it again.' );
ck( 'for Take it out of force, that nothing was revoked and to take it out of force again',
    $actions['revoke'],
    'Nothing was revoked, because WordPress would remove part of what you typed in "Why it is out of force, in your own words". To keep every word, put a space after each "<" (or write "less than") and after each "%", then take it out of force again.' );
ck( 'for a rejection, that nothing was rejected and to reject the application again',
    $actions['reject'],
    'Nothing was rejected, because WordPress would remove part of what you typed in "Why, for the next Administrator who reads this". To keep every word, put a space after each "<" (or write "less than") and after each "%", then reject the application again.' );
ck( 'each through the plugin\'s text domain', $GLOBALS['domains'], array( 'wpcredits-program-manager', 'wpcredits-program-manager', 'wpcredits-program-manager', 'wpcredits-program-manager' ) );
ck( 'and a form that saves, named or not, keeps the first sentence, as does an action the method does not know', array( WPCPM_Typed_Text::loss_message( 'Offer text', 'save' ), WPCPM_Typed_Text::loss_message( 'Offer text' ), WPCPM_Typed_Text::loss_message( 'Offer text', 'publish' ), WPCPM_Typed_Text::loss_message( 'Offer text', true ) ), array( $said, $said, $said, $said ) );
// Each sentence is one msgid, with a translators comment right above it, never built from pieces.
$tt_src  = (string) file_get_contents( __DIR__ . '/../includes/class-wpcpm-typed-text.php' );
$tt_body = substr( $tt_src, (int) strpos( $tt_src, 'public static function loss_message(' ) );
$tt_body = substr( $tt_body, 0, (int) strpos( $tt_body, "\n\t}\n" ) );
ck( 'the five sentences are five whole msgids, each under its own translators comment', array( preg_match_all( "/__\\( 'Nothing was (saved|sent|returned|revoked|rejected), because WordPress would remove part of what you typed in \"%s\"\\. To keep every word, [^']*', 'wpcredits-program-manager' \\)/", $tt_body ), preg_match_all( '#/\\* translators: %s: [^*]*\\*/\\s*__\\( \'Nothing was#', $tt_body ) ), array( 5, 5 ) );

echo "\n=== keep(), kept() and forget(): a refused form gets back what was typed, once ===\n";

// A typing with everything a box can hold: a "<" that would open a tag, quotes, an ampersand typed
// out as an entity, a backslash, a tab, an emoji and a line break as a text area posts it.
$GLOBALS['uid']   = 5;
$GLOBALS['flash'] = array();
$typed_line       = 'Q&amp;A <b>x</b> "quoted" it\'s C:\drafts' . "\t" . "\xF0\x9F\x98\x80";
$typed_area       = "Kids <12 free,\r\nadults >18 pay & 10%cashback";

WPCPM_Typed_Text::keep( 'test:one', array( 'title' => $typed_line, 'text' => $typed_area, 'audience' => array( 'mentors', 'managers' ) ), array( 'title' => 120, 'text' => 500 ) );
$back = WPCPM_Typed_Text::kept( 'test:one' );
ck( 'what was typed comes back exactly, a line break as the one LF a box counts', $back, array( 'title' => $typed_line, 'text' => "Kids <12 free,\nadults >18 pay & 10%cashback", 'audience' => array( 'mentors', 'managers' ) ) );
ck( 'and only once: the form drawn again has nothing kept', WPCPM_Typed_Text::kept( 'test:one' ), array() );
ck( 'nor is anything left waiting', $GLOBALS['flash'][5] ?? array(), array() );

WPCPM_Typed_Text::keep( 'test:two', array( 'title' => 'Mine' ), array( 'title' => 120 ) );
ck( 'another form never sees it', WPCPM_Typed_Text::kept( 'test:other' ), array() );
$GLOBALS['uid'] = 6;
ck( 'nor does another person, on the same form', WPCPM_Typed_Text::kept( 'test:two' ), array() );
$GLOBALS['uid'] = 5;
ck( 'while the one who typed it still has it', WPCPM_Typed_Text::kept( 'test:two' ), array( 'title' => 'Mine' ) );

// Kept for its box, never more: counted in characters, as the box counts them, a line break one.
$at_limit = str_repeat( "Ages 8 < 12 welcome.\r\n", 50 ) . str_repeat( "\xF0\x9F\x98\x80", 10 );
WPCPM_Typed_Text::keep( 'test:room', array( 'text' => $at_limit . 'beyond the box', 'title' => str_repeat( 'é', 130 ) ), array( 'text' => 1060, 'title' => 120 ) );
$back = WPCPM_Typed_Text::kept( 'test:room' );
ck( 'a typing beyond its box is kept up to the box\'s limit, in characters, a line break and an emoji one each', array( $back['text'] === str_replace( "\r\n", "\n", $at_limit ), mb_strlen( $back['text'] ), $back['title'] === str_repeat( 'é', 120 ) ), array( true, 1060, true ) );

WPCPM_Typed_Text::keep( 'test:bytes', array( 'title' => "Bad \xC3\x28 bytes", 'text' => 'Good words', 'note' => 'No room was given for it' ), array( 'title' => 120, 'text' => 500 ) );
ck( 'a typing that is not valid UTF-8 is not kept at all, and neither is a box given no room, while the rest is', WPCPM_Typed_Text::kept( 'test:bytes' ), array( 'text' => 'Good words' ) );
WPCPM_Typed_Text::keep( 'test:none', array( 'title' => "\xC3\x28" ), array( 'title' => 120 ) );
ck( 'and when nothing is left to keep, nothing is stored', isset( $GLOBALS['flash'][5]['typed:test:none'] ), false );
WPCPM_Typed_Text::keep( 'test:blank', array( 'title' => '', 'audience' => array() ), array( 'title' => 120 ) );
ck( 'an empty box and an empty list are kept as what was typed: nothing', WPCPM_Typed_Text::kept( 'test:blank' ), array( 'title' => '', 'audience' => array() ) );

// A refused form whose page never drew it must not bring the typing back the next day.
WPCPM_Typed_Text::keep( 'test:stale', array( 'title' => 'An hour ago' ), array( 'title' => 120 ) );
$GLOBALS['flash'][5]['typed:test:stale']['at'] = time() - WPCPM_Typed_Text::KEPT_FOR - 1;
ck( 'a typing kept longer ago than KEPT_FOR is not given back', WPCPM_Typed_Text::kept( 'test:stale' ), array() );
ck( 'and is cleared all the same', isset( $GLOBALS['flash'][5]['typed:test:stale'] ), false );
WPCPM_Typed_Text::keep( 'test:fresh', array( 'title' => 'Just now' ), array( 'title' => 120 ) );
$GLOBALS['flash'][5]['typed:test:fresh']['at'] = time() - WPCPM_Typed_Text::KEPT_FOR + 5;
ck( 'one kept within it is', WPCPM_Typed_Text::kept( 'test:fresh' ), array( 'title' => 'Just now' ) );
ck( 'ten minutes, the time a refusal takes to land on its page many times over', WPCPM_Typed_Text::KEPT_FOR, 600 );

// A typing whose form is never drawn again does not stay in the person's user meta, a contact
// address or a note among it: the next keep(), for any form, clears every typing older than
// KEPT_FOR, and leaves a fresh one, the person's other channels and anyone else's typing.
$GLOBALS['flash'] = array();
WPCPM_Typed_Text::keep( 'test:abandoned', array( 'email' => 'maciej@a8c.com' ), array( 'email' => 120 ) );
WPCPM_Typed_Text::keep( 'test:waiting', array( 'note' => 'Still on its way' ), array( 'note' => 120 ) );
$GLOBALS['flash'][5]['typed:test:abandoned']['at'] = time() - WPCPM_Typed_Text::KEPT_FOR - 1;
$GLOBALS['flash'][5]['typed:test:waiting']['at']   = time() - WPCPM_Typed_Text::KEPT_FOR + 5;
$GLOBALS['flash'][5]['typed:test:odd']             = 'not a kept typing';
$GLOBALS['flash'][5]['sponsor_dashboard']          = 'offer-saved';
$GLOBALS['flash'][6]['typed:test:abandoned']       = array(
	'at'    => time() - WPCPM_Typed_Text::KEPT_FOR - 1,
	'typed' => array( 'email' => 'maciej+other@a8c.com' ),
);
WPCPM_Typed_Text::keep( 'test:next', array( 'title' => 'Next' ), array( 'title' => 120 ) );
ck( 'the next keep() clears a typing older than KEPT_FOR, and a value on a typing\'s channel that is not one, whatever form they were kept for', array( isset( $GLOBALS['flash'][5]['typed:test:abandoned'] ), isset( $GLOBALS['flash'][5]['typed:test:odd'] ) ), array( false, false ) );
ck( 'while a fresh one for another form, the person\'s other channels and its own typing stay, and so does another person\'s', array( array_keys( $GLOBALS['flash'][5] ), WPCPM_Typed_Text::kept( 'test:waiting' ), isset( $GLOBALS['flash'][6]['typed:test:abandoned'] ) ), array( array( 'typed:test:waiting', 'sponsor_dashboard', 'typed:test:next' ), array( 'note' => 'Still on its way' ), true ) );
$GLOBALS['flash'] = array();

// The form posted again, whatever comes of it, makes what an earlier refusal kept stale.
WPCPM_Typed_Text::keep( 'test:posted', array( 'title' => 'Before' ), array( 'title' => 120 ) );
WPCPM_Typed_Text::forget( 'test:posted' );
ck( 'forget() drops what a form kept', WPCPM_Typed_Text::kept( 'test:posted' ), array() );
$GLOBALS['flash']['5']['typed:test:odd'] = 'not a kept typing';
ck( 'and a value that is not a kept typing reads as nothing kept', WPCPM_Typed_Text::kept( 'test:odd' ), array() );
$GLOBALS['uid'] = 0;
WPCPM_Typed_Text::keep( 'test:guest', array( 'title' => 'Nobody' ), array( 'title' => 120 ) );
ck( 'a guest keeps nothing and reads nothing', array( $GLOBALS['flash'][0] ?? array(), WPCPM_Typed_Text::kept( 'test:guest' ) ), array( array(), array() ) );

echo "\n=== kept_room() and kept_attr(): a kept typing drawn back into its box ===\n";

ck( 'an empty box keeps up to its limit', WPCPM_Typed_Text::kept_room( '', 120 ), 120 );
ck( 'a box that holds a ">" as `&gt;` keeps the room it was drawn with', array( WPCPM_Typed_Text::kept_room( $team_post, 120 ), WPCPM_Typed_Text::drawn_limit( $team_post, 120 ) ), array( 123, 123 ) );
$grid = str_repeat( 'One year of hosting free. ', 10 );
ck( 'and a box drawn with more than its limit, as the base can hold a text, keeps all it was drawn with', WPCPM_Typed_Text::kept_room( $grid, 200 ), 260 );
ck( 'a text area drawn with line breaks counts each as one: fifty lines of "Line" are 250, not the 300 they post as', WPCPM_Typed_Text::kept_room( str_repeat( "Line\r\n", 50 ), 200 ), 250 );

// `esc_attr()` keeps an entity it finds, so a typed-out "&amp;" drawn through it alone would come
// back as "&". Each "&" written `&amp;` first, the box shows what was typed.
$q_amp = 'Q&amp;A <b>x</b>';
ck( 'a text input drawn with esc_attr( kept_attr() ) shows "Q&amp;A <b>x</b>" exactly as it was typed', browser_shows( esc_attr( WPCPM_Typed_Text::kept_attr( $q_amp ) ) ), $q_amp );
ck( 'where esc_attr() alone would show "Q&A <b>x</b>"', browser_shows( esc_attr( $q_amp ) ), 'Q&A <b>x</b>' );
ck( 'and a text area drawn with esc_textarea() shows it exactly with nothing more', browser_shows( esc_textarea( "Q&amp;A <b>x</b>\nline two" ), true ), "Q&amp;A <b>x</b>\nline two" );
ck( 'kept_attr() reads nothing back: a typing is not a stored text', WPCPM_Typed_Text::kept_attr( 'a &gt; b &lt; c "d"' ), 'a &amp;gt; b &amp;lt; c "d"' );

echo "\n=== Every typing, refused or kept ===\n";

// Typings of the pieces that make the cleaner take words and of those that only look like it,
// through both cleaners. A typing loses words exactly when the stand-in cleaner with its steps that
// take text away switched off keeps something the real one does not: an answer that reads no
// entity, so it checks cleaner_loses() from outside. Seeded, so a failure names the same typing on
// every run.
mt_srand( 1122013 );

$loss_pieces = array( 'Kids', 'free', 'team', ' ', '  ', "\r\n", "\n", "\t", '<', ' < ', '>', ' > ', '<3', '<12', '<b>', '</b>', '->', "<\n", '%', '%20', '%ca', '10%', '&', '&amp;', '&lt;', '&#9;', '&#39;', '&#X41;', '&#0;', '&COPY;', '"', "'", '🎉' );
$wrong_loss  = array( 'refused when nothing is lost' => 0, 'kept when words are lost' => 0 );
$reached_l   = array( 'losses' => 0, 'keeps the cleaner writes in other bytes' => 0, 'keeps with a reference written in core\'s form' => 0 );
$checked_l   = 0;

for ( $i = 0; $i < 3000; ++$i ) {
	$typed = '';

	for ( $n = mt_rand( 1, 12 ); $n > 0; --$n ) {
		$typed .= $loss_pieces[ mt_rand( 0, count( $loss_pieces ) - 1 ) ];
	}

	foreach ( array( 'line' => false, 'lines' => true ) as $kind => $area ) {
		$cleaned = wpcpm_test_clean( $typed, $area );
		$lost    = wpcpm_test_clean( $typed, $area, false ) !== $cleaned;
		$says    = WPCPM_Typed_Text::cleaner_loses( $typed, $kind );

		$wrong_loss['refused when nothing is lost'] += (int) ( $says && ! $lost );
		$wrong_loss['kept when words are lost']     += (int) ( ! $says && $lost );

		// What the typings reach, so the check cannot go green over nothing.
		$folded = $area ? trim( $typed ) : trim( preg_replace( '/[\r\n\t ]+/', ' ', $typed ) );

		$reached_l['losses']                                       += (int) $lost;
		$reached_l['keeps the cleaner writes in other bytes']        += (int) ( ! $lost && $cleaned !== $folded );
		$reached_l['keeps with a reference written in core\'s form'] += (int) ( ! $lost && preg_match( '/&#009;|&#x41;|&amp;#0;|&amp;COPY;/', $cleaned ) );
		++$checked_l;
	}
}

ck( sprintf( 'of %d typings, none is refused that loses nothing, and none is kept that loses words', $checked_l ),
    $wrong_loss, array_fill_keys( array_keys( $wrong_loss ), 0 ) );
ck( 'and they reach at least a hundred losses, a hundred keeps in other bytes and ten keeps with a reference in core\'s form',
    array( $reached_l['losses'] >= 100, $reached_l['keeps the cleaner writes in other bytes'] >= 100, $reached_l['keeps with a reference written in core\'s form'] >= 10 ),
    array( true, true, true ) );

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
