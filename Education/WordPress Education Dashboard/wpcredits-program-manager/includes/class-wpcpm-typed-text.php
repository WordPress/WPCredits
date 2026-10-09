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
 * whether a box posted back changed it, the box's `maxlength`, how a plain-text mail reads it, what
 * a post write hands kses, and whether the cleaner would take words from what was posted, with the
 * sentence that refuses such a save and the typing the refused form gets back.
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
 * to an applicant and the reason a rejection is kept with (`WPCPM_Sponsor_Application`); and the
 * notes a sponsor post and a Collaboration Agreement are returned with, and the note an agreement
 * is taken out of force with (`WPCPM_Sponsor_Posts`, `WPCPM_Sponsor_Agreement`). Each counts such a
 * text the way it draws and mails it. On the institution side four boxes refuse a loss and count
 * their text as typed by the same rules: the question to an applicant, mailed as typed, and the
 * reason a rejection is kept with (`WPCPM_Institutions`), the note a signed agreement is returned
 * with (`WPCPM_Institution_Agreement`, whose return and revocation mails read their note as typed),
 * and the note a request from an institution is closed with (`WPCPM_Institution_Request`). Their
 * limits, and those of the sponsor application's question and reason and of the notes a
 * Collaboration Agreement is returned or taken out of force with, are counted on what was typed
 * (`box_length()`), once the loss check has passed it. A place that measures, draws, compares or
 * mails one by other rules, as the plugin's other screens still do, can count it one way and show
 * or mail it another.
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
	 * How long a refused form's typing waits for the form to be drawn again, in seconds: ten minutes,
	 * many times what a refusal takes to land on its page (`kept()`).
	 *
	 * @var int
	 */
	const KEPT_FOR = 600;

	/**
	 * The flash channel a refused form's typing waits on, before the form's own name.
	 *
	 * @var string
	 */
	const KEPT_CHANNEL = 'typed:';

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
	 * How long a typing is as its box counted it: what was posted, each CR LF one character and the
	 * ends trimmed, and every other character as it was typed.
	 *
	 * For the limits of a note box, counted once `cleaner_loses()` has passed the typing, so that
	 * what the cleaner keeps holds the same words. What it keeps cannot be counted so:
	 * `typed_length()` reads its entities back, and an entity the cleaner wrote for a "<" reads the
	 * same as one a person typed out. "Type &lt;b&gt; for bold", typed out, is 23 characters to its
	 * box and here, and 17 to `typed_length()`, so a box that takes no fewer than 20 would see it
	 * refused as too short. After a "<" the cleaner also writes a reference typed out in core's own
	 * form, `&#9;` as `&#009;`, which `typed_length()` counts longer than it was typed. Counted
	 * here, the store measures a typing as its box did, but for white space at its ends, which the
	 * box counts and the cleaner does not keep.
	 *
	 * A text area posts each line break as CR LF and its box counts it once, so a CR LF is one
	 * character. It counts code points, as `typed_length()` does, so an emoji is one character here
	 * and two to the box.
	 *
	 * @param string $typed What was posted for the box, unslashed and not cleaned
	 *                      (`WPCPM_Request::posted_raw()`).
	 * @return int
	 */
	public static function box_length( $typed ) {
		if ( ! is_scalar( $typed ) ) {
			return 0;
		}

		return mb_strlen( trim( str_replace( "\r\n", "\n", (string) $typed ) ) );
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
	 * A stored value as a one-line box is drawn with it: each line break a space.
	 *
	 * A text input drops the line breaks of its value, so a value stored with "Free hosting" and
	 * "for students" on two lines, as a cell written in the base's grid can be, would post back as
	 * "Free hostingfor students" if it were drawn as it is stored. A box drawn this way posts back
	 * the stored value with each break a space, which the box's unedited test reads as the stored
	 * value: the profile's one-line boxes and the Offers card's title. A CR LF is one break.
	 *
	 * @param string $value The value as stored.
	 * @return string
	 */
	public static function one_line( $value ) {
		return (string) preg_replace( '/\r\n|\r|\n/', ' ', (string) $value );
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

	/**
	 * Whether the cleaner would take away more than white space from what was posted, so a save
	 * would lose words without a word said.
	 *
	 * The cleaner takes a "<" followed by anything but white space, with the words up to a ">" that
	 * comes before the next "<", for a tag, and drops it: "Kids <12 free, adults >18 pay" is stored
	 * as `Kids 18 pay`. It drops a "%" followed by two hexadecimal digits as a URL octet: "Get
	 * 10%cashback" is stored as `Get 10shback`. And it empties a text that is not valid UTF-8, which
	 * only a crafted post sends, whole. Whatever the cleaner takes away is a loss here, but for the
	 * white space it folds and trims.
	 *
	 * The cleaner is run on what was posted, and the two are compared once each is read the same
	 * way (`compared_as()`). What the cleaner keeps it can write in other bytes: a "<" that opens no
	 * tag as `&lt;`, the quote marks and the ampersands after it as entities, a reference typed out
	 * after it in core's own form (`&#9;` as `&#009;`, `&#X41;` as `&#x41;`, and the "&" of one core
	 * does not take, `&#0;` or `&COPY;`, as `&amp;`), and in a text input every run of white space as
	 * one space. Read that way, those changes leave the two sides the same, and only a character
	 * the cleaner took away tells them apart.
	 *
	 * @param string $raw  What was posted, unslashed and not yet cleaned.
	 * @param string $kind 'line' for a text input, cleaned by `sanitize_text_field()`, or 'lines'
	 *                     for a text area, cleaned by `sanitize_textarea_field()`. Any other is read
	 *                     as 'line'.
	 * @return bool
	 */
	public static function cleaner_loses( $raw, $kind ) {
		// An array or an object, which only a crafted post sends, is no typed text: the cleaner
		// empties it, and there are no words of it to lose.
		if ( ! is_scalar( $raw ) ) {
			return false;
		}

		$raw   = (string) $raw;
		$lines = 'lines' === $kind;
		$kept  = $lines ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );

		return self::compared_as( $kept, $lines ) !== self::compared_as( $raw, $lines );
	}

	/**
	 * A text as `cleaner_loses()` compares it: each entity read back once, by the decoder
	 * `typed_length()` counts by, every numeric reference then written in one form, and the white
	 * space the cleaner folds folded.
	 *
	 * The read-back alone leaves a numeric reference outside the five it reads as it is, so `&#9;`
	 * as typed and the `&#009;` the cleaner writes for it would differ, and so would `&#X41;` and
	 * `&#x41;`. In one form, with no leading zeros and in small letters, they read the same. A
	 * reference the cleaner does not take (`&#0001;`, `&#XD800;`) comes back from the read-back as
	 * typed but for a small "x", its "&amp;" read once, and the same rule makes the two alike. The
	 * cleaner folds every run of white space in a text input to one space and trims the ends of
	 * either box, so both sides are folded and trimmed the same way.
	 *
	 * No pattern here reads the text as UTF-8, so a text that is not valid UTF-8 is compared byte
	 * for byte: the cleaner's empty answer differs from it.
	 *
	 * @param string $text  A text as posted, or as the cleaner left it.
	 * @param bool   $lines Whether the box is a text area.
	 * @return string
	 */
	private static function compared_as( $text, $lines ) {
		$text = (string) preg_replace_callback(
			'/&#(?:([0-9]+)|[xX]([0-9A-Fa-f]+));/',
			static function ( $matches ) {
				if ( '' !== $matches[1] ) {
					$number = ltrim( $matches[1], '0' );

					return '&#' . ( '' === $number ? '0' : $number ) . ';';
				}

				$number = strtolower( ltrim( $matches[2], '0' ) );

				return '&#x' . ( '' === $number ? '0' : $number ) . ';';
			},
			wp_specialchars_decode( $text, ENT_QUOTES )
		);

		if ( ! $lines ) {
			$text = (string) preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}

		return trim( $text );
	}

	/**
	 * What a save refused for `cleaner_loses()` says: that nothing was done, which box the cleaner
	 * would have taken words from, and how to type them so that it keeps them.
	 *
	 * The sentence is plain text. The label goes in as given, and escaping the sentence for the page
	 * it is shown on is the caller's.
	 *
	 * A form says what its own button does: a save, a send ("Tell the program", "Send this
	 * question"), a return with a note, Take it out of force, Reject, or the two that close a
	 * request from an institution, Mark as handled and Decline. Each action has a whole sentence of
	 * its own, saying what did not happen and what to press again, so that none is built from
	 * pieces a translator meets apart. An action this method does not know reads as a save.
	 *
	 * @param string $label  The name of the box, as its form labels it.
	 * @param string $action What the form's button does: `save`, `send`, `return`, `revoke`,
	 *                       `reject`, `handle` or `decline`. Default `save`.
	 * @return string
	 */
	public static function loss_message( $label, $action = 'save' ) {
		switch ( is_string( $action ) ? $action : 'save' ) {
			case 'send':
				return sprintf(
					/* translators: %s: the name of the box, such as Anything else. */
					__( 'Nothing was sent, because WordPress would remove part of what you typed in "%s". To keep every word, put a space after each "<" (or write "less than") and after each "%%", then send it again.', 'wpcredits-program-manager' ),
					(string) $label
				);
			case 'return':
				return sprintf(
					/* translators: %s: the name of the box, such as A note for the author. */
					__( 'Nothing was returned, because WordPress would remove part of what you typed in "%s". To keep every word, put a space after each "<" (or write "less than") and after each "%%", then return it again.', 'wpcredits-program-manager' ),
					(string) $label
				);
			case 'revoke':
				return sprintf(
					/* translators: %s: the name of the box, such as Why it is out of force, in your own words. */
					__( 'Nothing was revoked, because WordPress would remove part of what you typed in "%s". To keep every word, put a space after each "<" (or write "less than") and after each "%%", then take it out of force again.', 'wpcredits-program-manager' ),
					(string) $label
				);
			case 'reject':
				return sprintf(
					/* translators: %s: the name of the box, such as Why, for the next Administrator who reads this. */
					__( 'Nothing was rejected, because WordPress would remove part of what you typed in "%s". To keep every word, put a space after each "<" (or write "less than") and after each "%%", then reject the application again.', 'wpcredits-program-manager' ),
					(string) $label
				);
			case 'handle':
				return sprintf(
					/* translators: %s: the name of the box, such as What you did about it. */
					__( 'Nothing was marked as handled, because WordPress would remove part of what you typed in "%s". To keep every word, put a space after each "<" (or write "less than") and after each "%%", then mark it as handled again.', 'wpcredits-program-manager' ),
					(string) $label
				);
			case 'decline':
				return sprintf(
					/* translators: %s: the name of the box, such as What you did about it. */
					__( 'Nothing was declined, because WordPress would remove part of what you typed in "%s". To keep every word, put a space after each "<" (or write "less than") and after each "%%", then decline it again.', 'wpcredits-program-manager' ),
					(string) $label
				);
		}

		return sprintf(
			/* translators: %s: the name of the box, such as How to redeem it. */
			__( 'Nothing was saved, because WordPress would remove part of what you typed in "%s". To keep every word, put a space after each "<" (or write "less than") and after each "%%", then save again.', 'wpcredits-program-manager' ),
			(string) $label
		);
	}

	/**
	 * Keep what a person typed into a form that was refused, for the one time the form is drawn
	 * again, so that they make the change the refusal names and lose nothing else.
	 *
	 * It waits in the flash (`WPCPM_Flash`), on a channel of its own per form: one entry per person
	 * and form, which `kept()` hands back once and clears. The form's name says which record it
	 * edits, so the typing comes back in the box it was typed in and in no other.
	 *
	 * Each text is kept as it was typed, a line break read as the one LF its box counts, and cut to
	 * the room given for its box, in characters (`kept_room()`): a crafted post holds no more here
	 * than its box would. A text that is not valid UTF-8 is not kept at all, and neither is a text
	 * given no room. A list, the choices of a group of checkboxes, is kept as its strings, so the
	 * caller hands over only values it has matched against its own choices. When nothing is left to
	 * keep, nothing is stored.
	 *
	 * A typing whose form is never drawn again would stay in the person's user meta for good, hidden
	 * by `kept()` but stored, and it can hold a contact's address or a note. So every keep first
	 * clears, for whatever form, each of the person's kept typings older than `KEPT_FOR`, and any
	 * value on such a channel that is not a kept typing at all (`WPCPM_Flash::sweep()`).
	 *
	 * @param string $form  The form, by what it is and the record it edits, such as
	 *                      `offer:recXXXXXXXXXXXXXX:new`.
	 * @param array  $typed Each field and what was posted for it, unslashed and not cleaned
	 *                      (`WPCPM_Request::posted_raw()`), or a list of chosen values.
	 * @param array  $room  Each text field and the most characters kept for it.
	 */
	public static function keep( $form, array $typed, array $room ) {
		WPCPM_Flash::sweep(
			static function ( $channel, $entry ) {
				if ( 0 !== strpos( $channel, self::KEPT_CHANNEL ) ) {
					return false;
				}

				return ! is_array( $entry ) || ! isset( $entry['at'] ) || time() - (int) $entry['at'] > self::KEPT_FOR;
			}
		);

		$kept = array();

		foreach ( $typed as $field => $value ) {
			if ( is_array( $value ) ) {
				$kept[ $field ] = array_values(
					array_filter(
						$value,
						static function ( $item ) {
							return is_string( $item ) && mb_check_encoding( $item, 'UTF-8' );
						}
					)
				);

				continue;
			}

			if ( ! is_scalar( $value ) || ! isset( $room[ $field ] ) || ! mb_check_encoding( (string) $value, 'UTF-8' ) ) {
				continue;
			}

			$kept[ $field ] = mb_substr( str_replace( "\r\n", "\n", (string) $value ), 0, max( 0, (int) $room[ $field ] ), 'UTF-8' );
		}

		if ( empty( $kept ) ) {
			return;
		}

		WPCPM_Flash::set(
			self::KEPT_CHANNEL . $form,
			array(
				'at'    => time(),
				'typed' => $kept,
			)
		);
	}

	/**
	 * What a refused form kept for its redraw, handed back once and cleared.
	 *
	 * A typing older than `KEPT_FOR` is not handed back: a refusal lands on its page at once, so one
	 * still waiting is from a page that never drew the form, and it must not turn up in the box on a
	 * later visit. It is cleared all the same.
	 *
	 * @param string $form The form, named as `keep()` was given it.
	 * @return array Each field and what was kept for it, or an empty array when nothing is.
	 */
	public static function kept( $form ) {
		$entry = WPCPM_Flash::take( self::KEPT_CHANNEL . $form );

		if ( ! is_array( $entry ) || ! isset( $entry['at'], $entry['typed'] ) || ! is_array( $entry['typed'] ) ) {
			return array();
		}

		if ( time() - (int) $entry['at'] > self::KEPT_FOR ) {
			return array();
		}

		return $entry['typed'];
	}

	/**
	 * Drop what an earlier refusal of a form kept, because the form has been posted again.
	 *
	 * A refusal lands on its page, which draws the form and takes the typing. Should that page not
	 * draw it, the typing would wait for the next time it does, after a save that went through, and
	 * fill the boxes with what that save replaced. Every post of a form calls this first.
	 *
	 * @param string $form The form, named as `keep()` was given it.
	 */
	public static function forget( $form ) {
		WPCPM_Flash::take( self::KEPT_CHANNEL . $form );
	}

	/**
	 * The most characters `keep()` keeps for a box: the room the box was drawn with
	 * (`drawn_limit()`), and never less than the text it was drawn with.
	 *
	 * A box posted back as it was drawn is so kept whole: one that holds a ">" as `&gt;`, and one
	 * drawn with more than its limit, as the base can hold a text written in its grid. Cut there, the
	 * box would come back short, and the next save would store it short.
	 *
	 * @param string $value The text the box was drawn from, as stored, or '' for an empty box.
	 * @param int    $limit The box's limit, as `typed_length()` counts it.
	 * @return int
	 */
	public static function kept_room( $value, $limit ) {
		return max( self::drawn_limit( $value, $limit ), mb_strlen( str_replace( "\r\n", "\n", self::typed_text( $value ) ) ) );
	}

	/**
	 * A kept typing as a text input's value, ready for `esc_attr()`: each "&" written `&amp;`.
	 *
	 * `esc_attr()` keeps an entity it finds, so "Q&amp;A" typed out and drawn through it alone would
	 * come back as "Q&A". With each "&" written `&amp;` first, the box shows what was typed, a typed
	 * entity included. `attr_text()` is not for this: it reads a stored text back first, and a typing
	 * is not one. A text area needs nothing: `esc_textarea()` escapes every "&".
	 *
	 * @param string $value What was typed, as `kept()` hands it back.
	 * @return string
	 */
	public static function kept_attr( $value ) {
		return str_replace( '&', '&amp;', (string) $value );
	}

	/**
	 * The name a form of one box keeps its typing under: what the form does, then the post it acts
	 * on, as in `post-return:42`. `keep_box()`, `kept_box()` and `forget()` are each handed it.
	 *
	 * Such a form is a refusal's one box drawn empty on one post: the note a post or an agreement is
	 * returned with, the note an agreement is taken out of force with, a question to an applicant or
	 * the reason a rejection is kept with. Named by its post, what was typed for one post comes back
	 * in that post's box and in no other. Named by what it does, two forms on the same post keep
	 * apart, so each form's name is its own.
	 *
	 * @param string $form   What the form does, such as `post-return` or `sapp-reason`.
	 * @param int    $record The post the form acts on, by its ID.
	 * @return string
	 */
	public static function box_form( $form, $record ) {
		return (string) $form . ':' . absint( $record );
	}

	/**
	 * Keep what a person typed into a refused form of one box, for the one time the form is drawn
	 * again (`keep()`).
	 *
	 * The box is drawn empty, so its room is its limit (`kept_room()`): the typing is kept as it was
	 * typed, a line break read as the one LF its box counts, and cut at the limit in characters. A
	 * box left empty, or holding only white space, has nothing to give back, and nothing is kept for
	 * it. A box drawn from a stored text keeps through `keep()`, with the room that text gives it.
	 *
	 * @param string $form  The form, as `box_form()` names it.
	 * @param string $field The box's field, which `kept_box()` reads it back by.
	 * @param string $typed What was posted for the box, unslashed and not cleaned
	 *                      (`WPCPM_Request::posted_raw()`).
	 * @param int    $limit The box's limit, as `typed_length()` counts it.
	 */
	public static function keep_box( $form, $field, $typed, $limit ) {
		if ( '' === trim( (string) $typed ) ) {
			return;
		}

		self::keep( $form, array( $field => (string) $typed ), array( $field => self::kept_room( '', $limit ) ) );
	}

	/**
	 * What a refused form of one box kept for its redraw (`keep_box()`), handed back once and
	 * cleared, or null when nothing is.
	 *
	 * Null and not an empty string, so the box can tell a refusal's typing from none, and open the
	 * fold it is drawn in only for a typing. A field that holds anything but a string, as `keep()`
	 * keeps the choices of a group of checkboxes, reads as nothing kept.
	 *
	 * @param string $form  The form, as `box_form()` names it.
	 * @param string $field The box's field, as `keep_box()` was given it.
	 * @return string|null
	 */
	public static function kept_box( $form, $field ) {
		$kept = self::kept( $form );

		return isset( $kept[ $field ] ) && is_string( $kept[ $field ] ) ? $kept[ $field ] : null;
	}
}
