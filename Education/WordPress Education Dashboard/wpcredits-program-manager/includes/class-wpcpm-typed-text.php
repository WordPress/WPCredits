<?php
/**
 * The rules for text a person typed, once core's text cleaners have read it.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One set of rules for a text that went through `sanitize_text_field()` or
 * `sanitize_textarea_field()`: how long it is as the person typed it, how a box draws it back,
 * whether a box posted back changed it, the box's `maxlength`, how a plain-text mail reads it, and
 * what a post write hands kses.
 *
 * The cleaners do not hand back what was typed. Each writes a "<" that opens no tag as `&lt;`, and
 * the quote marks and the ampersands after it as `&quot;`, `&#039;` and `&amp;`, up to the next "<"
 * or the end. Each takes a "<" followed by anything but white space, with the words up to a ">"
 * that comes before the next "<", for a tag, and drops it. And WordPress runs kses on a post that a
 * person without `unfiltered_html` saves, which writes every other "&" as `&amp;` and every ">" as
 * `&gt;`. So a stored text is longer than its box counted it, a box that draws it as stored shows
 * the entities, a box that reads every entity back can hand the cleaner a tag nobody typed, and a
 * mail that carries it as stored says `&lt;` where the person typed "<".
 *
 * The places that use these rules are the sponsor screens' typed texts: the Offers card's title,
 * text and instructions, with the offer's mails and the usage CSV (`WPCPM_Sponsor_Offers`,
 * `WPCPM_Sponsor_Claims`); the interests card's Anything else and events
 * (`WPCPM_Sponsor_Interests`); the profile's typed texts (`WPCPM_Sponsor_Profile`); the question
 * to an applicant (`WPCPM_Sponsor_Application`); and the notes a sponsor post and a Collaboration
 * Agreement are returned with (`WPCPM_Sponsor_Posts`, `WPCPM_Sponsor_Agreement`). Each counts such a
 * text the way it draws and mails it. A place that measures, draws, compares or mails one by other
 * rules, as the plugin's other screens still do, can count it one way and show or mail it another.
 * What goes to the program records goes as stored, as every writer to the base sends its text.
 *
 * Nothing stored is rewritten by them: they read what is there.
 */
final class WPCPM_Typed_Text {

	/**
	 * The white space that keeps a "<" from opening a tag: a space, a tab, a line break, a vertical
	 * tab or a form feed, the characters PHP's `strip_tags()` reads that way.
	 *
	 * @var string
	 */
	const NOT_A_TAG = " \t\n\r\x0B\x0C";

	/**
	 * How long a text is as the person who typed it counted it, which is what its box counts.
	 *
	 * An entity the cleaner wrote (`&lt;` for a "<" that opens no tag, and `&quot;`, `&#039;` and
	 * `&amp;` for the quote marks and the ampersands after it) is the one character it stands for,
	 * as are the `&lt;` and the `&gt;` `insert_text()` writes for a "<" and a ">" the cleaner left,
	 * and the `&amp;` and the `&gt;` kses writes for the other ampersands and every ">". A line
	 * break is one character, as a text area posts it in two bytes and its box counts it once. So a
	 * typing that fits its box is never refused for what the cleaner made of it. Only the count
	 * changes: the text is stored as cleaned.
	 *
	 * It counts code points. A browser counts a `maxlength` in UTF-16 units, so an emoji is one
	 * character here and two to the box: the box can refuse added text a little sooner than the
	 * store. A person who deletes a held `&gt;` frees more room in the box than in the store, so the
	 * browser can then take a text the store refuses (see `drawn_limit()`).
	 *
	 * @param string $value A text as a cleaner left it, or as it is stored.
	 * @return int
	 */
	public static function typed_length( $value ) {
		return mb_strlen( str_replace( "\r\n", "\n", wp_specialchars_decode( (string) $value, ENT_QUOTES ) ) );
	}

	/**
	 * A stored text as its box shows it: the text the person typed, by one rule for every box that
	 * draws one. The result is not yet escaped for the page, and how it is depends on the box: a
	 * text area is drawn with `esc_textarea( typed_text( $value ) )`, and a text input with
	 * `esc_attr( attr_text( $value ) )`. `esc_attr()` keeps an entity it finds, so it cannot be handed
	 * `typed_text()` directly: a `&gt;` the read-back holds would reach the input as ">", and the
	 * cleaner would take the words for a tag when the box is posted.
	 *
	 * Three things write entities into what is stored. The cleaner writes a "<" that opens no tag as
	 * `&lt;`, and escapes the quote marks and the ampersands after it (`&quot;`, `&#039;`, `&amp;`),
	 * up to the next tag or the end. For a person without `unfiltered_html`, `insert_text()` writes
	 * each "<" and ">" the cleaner left as `&lt;` and `&gt;`, and kses, which WordPress runs on what
	 * such a person saves, writes every other "&" as `&amp;` and every other ">" as `&gt;`. A box
	 * escapes its text once more when it is drawn, so each is read back first, once and no deeper:
	 * `&lt;`, `&quot;`, `&#039;` and `&amp;` everywhere, and `&gt;` wherever the ">" it stands for
	 * closes no tag when the box is posted.
	 *
	 * The cleaner takes a "<" followed by anything but white space (a space, a tab or a line break),
	 * with the words up to a ">" that comes before the next "<", for a tag, and drops them. Such a
	 * "<" is left where the person typed one before a tag the cleaner took out, as in "We <3 our
	 * <b>team</b> > all", stored `We &lt;3 our team > all`. Read back in full, that box would post
	 * "<3 our team >", and the cleaner would drop the words. So from such a "<" up to the next one,
	 * every ">" is drawn as `&gt;`, stored as the entity or not: the box shows "We <3 our team &gt;
	 * all", which posts back as that entity, reads the same to `same_text()`, and keeps every word.
	 * After a "<" that white space follows, a ">" is read back: the cleaner keeps the two as typed.
	 *
	 * A raw "<" is one that reached the store as typed, which only a text that met no kses holds: an
	 * administrator's words, which keep each ">" as typed too, a tag kses allows, or a text kept in
	 * post meta. From the first one on, an `&gt;` was typed out, and stays as it is.
	 *
	 * What the box posts back is cleaned to the text it was drawn from. An entity a person typed
	 * out, `&amp;` say, is read back as the character it stands for, which `typed_length()` counts
	 * as well. A text that holds an entity of an entity, `&amp;lt;` typed to show HTML, is the
	 * exception: the box shows `&lt;`, and the unedited save stores the text with that one level
	 * read, so each unedited save reads one level further, until the text holds a single level.
	 *
	 * @param string $value The text as stored, or as a refused form kept it.
	 * @return string
	 */
	public static function typed_text( $value ) {
		$read  = array(
			'&quot;' => '"',
			'&#039;' => "'",
			'&amp;'  => '&',
		);
		$parts = preg_split( '/(<|&lt;)/', (string) $value, -1, PREG_SPLIT_DELIM_CAPTURE );
		$text  = strtr( (string) array_shift( $parts ), $read + array( '&gt;' => '>' ) );
		$raw   = false;

		while ( array() !== $parts ) {
			// Each "<", raw or written `&lt;`, and the text up to the next one. The mark is shifted
			// on a line of its own: inside the `||` below it would be skipped once a raw "<" had
			// been seen, and every part after it would be read as a mark.
			$mark  = array_shift( $parts );
			$part  = (string) array_shift( $parts );
			$raw   = $raw || '<' === $mark;
			$opens = '' === $part || false === strpos( self::NOT_A_TAG, $part[0] );

			if ( $opens ) {
				$more = array( '>' => '&gt;' );
			} else {
				$more = $raw ? array() : array( '&gt;' => '>' );
			}

			$text .= '<' . strtr( $part, $read + $more );
		}

		return $text;
	}

	/**
	 * A stored text as a text input's value, ready for `esc_attr()`: `typed_text()` with each "&"
	 * written `&amp;`.
	 *
	 * `esc_attr()` keeps an entity it finds, so a `&gt;` that `typed_text()` holds would reach the
	 * browser as ">", and posted back, the cleaner would take "<3 our team >" for a tag. With each
	 * "&" written `&amp;` first, `esc_attr()` leaves that ampersand standing and the browser shows
	 * the entity's four characters, as a text area drawn through `esc_textarea()` does. Every other
	 * "&" the text holds comes back as it was typed. Nothing else is escaped here: that is
	 * `esc_attr()`'s.
	 *
	 * @param string $value The text as stored, or as a refused form kept it.
	 * @return string
	 */
	public static function attr_text( $value ) {
		return str_replace( '&', '&amp;', self::typed_text( $value ) );
	}

	/**
	 * Whether two texts read the same once every entity either holds is read back, the named ones
	 * included: a text typed with `&copy;` is stored as typed. The test of whether a box drawn by
	 * `typed_text()` and posted back unedited changed anything, so an unedited save writes nothing
	 * and says nothing changed.
	 *
	 * Each line break is read as it was posted: a text kept with the CR LF a text area posted and
	 * the same text with LF are not the same here. `same_lines()` folds them.
	 *
	 * A text that holds an entity of an entity (`&amp;lt;`) is the one that an unedited save does
	 * change: `typed_text()` reads it one level, so the post-back differs from the stored text.
	 *
	 * @param string $one   A text.
	 * @param string $other Another.
	 * @return bool
	 */
	public static function same_text( $one, $other ) {
		return html_entity_decode( (string) $one, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) === html_entity_decode( (string) $other, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Whether a text area posted back reads as the text it was drawn from: `same_text()`, with each
	 * line break read as one LF on both sides first. A browser posts every break as CR LF, while a
	 * stored text may hold LF, CR LF or CR, by whichever store wrote it, so a break kept either way
	 * is the same break, and a text posted back unedited changes nothing.
	 *
	 * @param string $one   The text as cleaned from the post.
	 * @param string $other The text as stored.
	 * @return bool
	 */
	public static function same_lines( $one, $other ) {
		$fold = array( "\r\n", "\r" );

		return self::same_text( str_replace( $fold, "\n", (string) $one ), str_replace( $fold, "\n", (string) $other ) );
	}

	/**
	 * The `maxlength` of a box drawn by `typed_text()`: its limit, and as many characters again as
	 * the drawn text holds beyond what `typed_length()` counts of the text it was drawn from.
	 *
	 * That is three for each ">" drawn as `&gt;`, four characters in the box and one to the count,
	 * and as many for an entity the read-back leaves that the count reads as one character,
	 * `&#062;` say. A browser counts a line break in a box once, as the count does, so a break adds
	 * nothing. So a box drawn this way refuses added text no later than the store would, and with an
	 * emoji, which it counts as two characters, a little sooner (see `typed_length()`). Deleting a
	 * held `&gt;` is the exception: it frees four characters of the box for one of the store, so in
	 * a text at its limit, up to four typed in its place post a text as much as three over, which
	 * the store refuses. A box with nothing held keeps its limit.
	 *
	 * The room is that of a box drawn as `typed_text()` says, a text area through `esc_textarea()`
	 * and a text input through `esc_attr( attr_text() )`, so that it shows the held `&gt;` as the
	 * four characters the room is made for.
	 *
	 * @param string $value The text as stored, or as a refused form kept it.
	 * @param int    $limit The box's limit, as `typed_length()` counts it.
	 * @return int
	 */
	public static function drawn_limit( $value, $limit ) {
		$drawn = str_replace( "\r\n", "\n", self::typed_text( $value ) );

		return (int) $limit + max( 0, mb_strlen( $drawn ) - self::typed_length( $value ) );
	}

	/**
	 * A stored text as a plain-text mail gives it: each entity the cleaner, `insert_text()` and kses
	 * wrote (`&lt;`, `&gt;`, `&quot;`, `&#039;` and `&amp;`) read back as the character it stands
	 * for, once and no deeper. That is the decoder `typed_length()` counts by and the calendar file
	 * reads by (`WPCPM_ICS`), so a mail says what a person typed, as their box counted it.
	 *
	 * A mail is plain text, and a "<" in it is a character, never a tag. A subject that carries such
	 * a text goes to the mail layer marked as plain text (`plain_subject`, `WPCPM_Mail::send()`),
	 * whose cleaner would otherwise take a "<3" with the words up to the next ">" for a tag and drop
	 * them.
	 *
	 * @param string $value The text as stored.
	 * @return string
	 */
	public static function mail_text( $value ) {
		return wp_specialchars_decode( (string) $value, ENT_QUOTES );
	}

	/**
	 * A text as a post write is handed it, where WordPress runs kses on what the person saves: in a
	 * post's title or content written with `wp_insert_post()` or `wp_update_post()`.
	 *
	 * WordPress runs kses for a person without `unfiltered_html` (`kses_init()`), and kses reads a
	 * "<" with the words after it up to the next ">" as a tag, and drops one it does not allow, words
	 * and all. The cleaner leaves a "<" as typed only where white space other than a line feed
	 * follows it and a ">" comes before the next "<". It writes a "<" with no such ">", and one a
	 * line feed follows, as `&lt;`, and takes one followed by anything but white space, with the
	 * words up to that ">", for a tag. So what kses would meet is a "tag" of whatever the person
	 * wrote next: "Ages 8 < 12 welcome, adults > 18 pay" would be stored as `Ages 8  18 pay`. For
	 * such a person each "<" and ">" the cleaner left is written `&lt;` and `&gt;` here, as kses
	 * writes a bare ">" itself, and kses finds no tag. `typed_length()` counts each as the one
	 * character it stands for, and `typed_text()` and `mail_text()` read them back. A person who
	 * holds `unfiltered_html` meets no kses, and their words are handed over as the cleaner left
	 * them.
	 *
	 * Only for a post write: post meta and options meet no kses, and a text kept there is stored as
	 * the cleaner left it.
	 *
	 * @param string $value A text as the cleaner left it.
	 * @return string
	 */
	public static function insert_text( $value ) {
		$value = (string) $value;

		if ( current_user_can( 'unfiltered_html' ) ) {
			return $value;
		}

		return str_replace( array( '<', '>' ), array( '&lt;', '&gt;' ), $value );
	}
}
