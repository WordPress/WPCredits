<?php
/**
 * The text pipeline behind the guide index: block markup into passages, text into stemmed terms.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plain PHP, no WordPress function, so the suites load it bare.
 *
 * Two jobs. `passages()` cuts a page's block markup into sections a reader could be pointed at, each
 * with the path of headings above it and the anchor that links to it. `terms()` turns text into the
 * words a search matches on: lowercased, stop words dropped, each stemmed with Porter's algorithm
 * (1980), so a question's "booking" and a guide's "booked" meet at one term.
 *
 * The same functions run on a guide when it is indexed and on a question when it is asked. Change one
 * and the index must be rebuilt, or a word is stored one way and searched another.
 */
final class WPCPM_Guide_Text {

	/**
	 * The most words one passage holds. A longer section is split.
	 */
	const PASSAGE_WORDS = 350;

	/**
	 * A span whose class list holds `glossary-item-hidden-content`, quoted either way, and all it holds,
	 * spans nested in it included (the named group recurses on each inner span).
	 */
	const GLOSSARY_TIP = '~(?(DEFINE)(?<span><span\b[^>]*+>(?:[^<]++|<(?!/?span\b)|(?&span))*+</span\s*+>))'
		. '<span\b[^>]*?\sclass\s*=\s*(?:"[^"]*(?<![\w-])glossary-item-hidden-content(?![\w-])[^"]*"|\'[^\']*(?<![\w-])glossary-item-hidden-content(?![\w-])[^\']*\')[^>]*+>'
		. '(?:[^<]++|<(?!/?span\b)|(?&span))*+</span\s*+>~i';

	/**
	 * The English stop words, `word => true`: the list Lucene's English analyzer uses, plus the words a
	 * question is built from and a guide never is searched by.
	 *
	 * @return array<string, bool>
	 */
	public static function stop_words(): array {
		static $words = null;
		if ( null === $words ) {
			// Lucene's English list, then the question words.
			$list  = explode(
				' ',
				'a an and are as at be but by for if in into is it no not of on or such that the their then there these they this to was will with'
				. ' can do does how i my what when where which who why you your'
			);
			$words = array_fill_keys( $list, true );
		}
		return $words;
	}

	/**
	 * Porter's stemmer, the original 1980 algorithm (steps 1a, 1b with its cleanup, 1c, 2, 3, 4, 5a, 5b).
	 *
	 * Applied to a word of the letters a to z alone. Any other word, and a word of one or two letters,
	 * comes back as it was given.
	 *
	 * @param string $word A word.
	 * @return string The stem.
	 */
	public static function stem( string $word ): string {
		if ( strlen( $word ) <= 2 || 1 !== preg_match( '/^[a-z]+$/', $word ) ) {
			return $word;
		}

		// Step 1a.
		if ( self::ends( $word, 'sses' ) ) {
			$word = substr( $word, 0, -2 );
		} elseif ( self::ends( $word, 'ies' ) ) {
			$word = substr( $word, 0, -2 );
		} elseif ( self::ends( $word, 'ss' ) ) {
			$word = $word;
		} elseif ( self::ends( $word, 's' ) ) {
			$word = substr( $word, 0, -1 );
		}

		// Step 1b.
		if ( self::ends( $word, 'eed' ) ) {
			if ( self::measure( substr( $word, 0, -3 ) ) > 0 ) {
				$word = substr( $word, 0, -1 );
			}
		} else {
			$cut = '';
			if ( self::ends( $word, 'ed' ) && self::has_vowel( substr( $word, 0, -2 ) ) ) {
				$cut = substr( $word, 0, -2 );
			} elseif ( self::ends( $word, 'ing' ) && self::has_vowel( substr( $word, 0, -3 ) ) ) {
				$cut = substr( $word, 0, -3 );
			}
			if ( '' !== $cut ) {
				$word = $cut;
				if ( self::ends( $word, 'at' ) || self::ends( $word, 'bl' ) || self::ends( $word, 'iz' ) ) {
					$word .= 'e';
				} elseif ( self::double_consonant( $word ) && ! in_array( substr( $word, -1 ), array( 'l', 's', 'z' ), true ) ) {
					$word = substr( $word, 0, -1 );
				} elseif ( 1 === self::measure( $word ) && self::cvc( $word ) ) {
					$word .= 'e';
				}
			}
		}

		// Step 1c.
		if ( self::ends( $word, 'y' ) && self::has_vowel( substr( $word, 0, -1 ) ) ) {
			$word = substr( $word, 0, -1 ) . 'i';
		}

		// Step 2.
		$word = self::replace_suffix(
			$word,
			array(
				'ational' => 'ate',
				'tional'  => 'tion',
				'enci'    => 'ence',
				'anci'    => 'ance',
				'izer'    => 'ize',
				'abli'    => 'able',
				'alli'    => 'al',
				'entli'   => 'ent',
				'eli'     => 'e',
				'ousli'   => 'ous',
				'ization' => 'ize',
				'ation'   => 'ate',
				'ator'    => 'ate',
				'alism'   => 'al',
				'iveness' => 'ive',
				'fulness' => 'ful',
				'ousness' => 'ous',
				'aliti'   => 'al',
				'iviti'   => 'ive',
				'biliti'  => 'ble',
			),
			0
		);

		// Step 3.
		$word = self::replace_suffix(
			$word,
			array(
				'icate' => 'ic',
				'ative' => '',
				'alize' => 'al',
				'iciti' => 'ic',
				'ical'  => 'ic',
				'ful'   => '',
				'ness'  => '',
			),
			0
		);

		// Step 4. "ion" goes only after an s or a t.
		$word = self::replace_suffix(
			$word,
			array(
				'al'    => '',
				'ance'  => '',
				'ence'  => '',
				'er'    => '',
				'ic'    => '',
				'able'  => '',
				'ible'  => '',
				'ant'   => '',
				'ement' => '',
				'ment'  => '',
				'ent'   => '',
				'ion'   => '',
				'ou'    => '',
				'ism'   => '',
				'ate'   => '',
				'iti'   => '',
				'ous'   => '',
				'ive'   => '',
				'ize'   => '',
			),
			1
		);

		// Step 5a.
		if ( self::ends( $word, 'e' ) ) {
			$stem = substr( $word, 0, -1 );
			$m    = self::measure( $stem );
			if ( $m > 1 || ( 1 === $m && ! self::cvc( $stem ) ) ) {
				$word = $stem;
			}
		}

		// Step 5b.
		if ( self::measure( $word ) > 1 && self::double_consonant( $word ) && self::ends( $word, 'l' ) ) {
			$word = substr( $word, 0, -1 );
		}

		return $word;
	}

	/**
	 * Text into search terms: lowercased, split on anything that is not a letter or a number, words of one
	 * character and stop words dropped, each stemmed. Order and repeats are kept.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function terms( string $text ): array {
		$stop  = self::stop_words();
		$out   = array();
		$words = preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( $text, 'UTF-8' ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $words ) ) {
			return $out;
		}
		foreach ( $words as $word ) {
			if ( mb_strlen( $word, 'UTF-8' ) < 2 || isset( $stop[ $word ] ) ) {
				continue;
			}
			$out[] = self::stem( $word );
		}
		return $out;
	}

	/**
	 * Term frequencies of a passage: the body's terms once, the heading's twice, so a section named for
	 * the question outranks one that only mentions it.
	 *
	 * @param string $heading The heading path.
	 * @param string $body    The body text.
	 * @return array<string, int> Term => frequency.
	 */
	public static function counts( string $heading, string $body ): array {
		$counts = array();
		foreach ( self::terms( $body ) as $term ) {
			$counts[ $term ] = ( isset( $counts[ $term ] ) ? $counts[ $term ] : 0 ) + 1;
		}
		foreach ( self::terms( $heading ) as $term ) {
			$counts[ $term ] = ( isset( $counts[ $term ] ) ? $counts[ $term ] : 0 ) + 2;
		}
		return $counts;
	}

	/**
	 * Block markup into the text a reader sees, one block to a line.
	 *
	 * The handbook's glossary tooltips go with all they hold: their text shows only on hover, and left in
	 * it fuses with the term ("mentorEvent Supporter") and puts another program's meaning of a word into
	 * the passage. A tooltip the pattern cannot read (an unclosed span) is left as it is.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	public static function plain( string $html ): string {
		$html = self::unhidden( $html );
		$tips = preg_replace( self::GLOSSARY_TIP, '', $html );
		$html = null === $tips ? $html : $tips;
		$html = (string) preg_replace( '~</(?:p|li|h[1-6]|summary|tr|div|dt|dd|blockquote|pre|figcaption|table|ul|ol)\s*>|<br\b[^>]*>~i', "\n", $html );
		$html = (string) preg_replace( '~</t[dh]\s*>~i', ' ', $html );
		$html = strip_tags( $html ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- No WordPress function is available here.
		$html = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$html = (string) preg_replace( '/[ \t\x{A0}]+/u', ' ', $html );

		$lines = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $html ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$lines[] = $line;
			}
		}
		return implode( "\n", $lines );
	}

	/**
	 * Markup without what a reader never sees: every complete comment, and every script, style and SVG
	 * with all it holds. A heading in one of them is not the page's (an old rule left in a comment, a
	 * script's template), so this runs before the sections are found as well as in `plain()`.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	private static function unhidden( string $html ): string {
		return self::without_elements( self::without_comments( $html ) );
	}

	/**
	 * Markup without its complete comments. A `<!--` with no `-->` after it stays, with all that follows.
	 * A plain scan, not a pattern, so a comment of any size cannot make it fail.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	private static function without_comments( string $html ): string {
		$out = '';
		$at  = 0;

		while ( true ) {
			$open  = strpos( $html, '<!--', $at );
			$close = false === $open ? false : strpos( $html, '-->', $open + 4 );

			if ( false === $close ) {
				break;
			}

			$out .= substr( $html, $at, $open - $at );
			$at   = $close + 3;
		}

		return $out . substr( $html, $at );
	}

	/**
	 * Markup without its `script`, `style` and `svg` elements, each from its opening tag through the first
	 * closing tag of its name. An opening tag with no closing tag stays, with all that follows. A plain
	 * scan, not a pattern, so an element of any size cannot make it fail.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	private static function without_elements( string $html ): string {
		$names = array( 'script', 'style', 'svg' );
		$next  = array();
		$out   = '';
		$at    = 0;

		while ( true ) {
			$open = false;
			$name = '';

			foreach ( $names as $candidate ) {
				if ( ! isset( $next[ $candidate ] ) || ( false !== $next[ $candidate ] && $next[ $candidate ] < $at ) ) {
					$next[ $candidate ] = self::opening_tag( $html, $candidate, $at );
				}

				if ( false !== $next[ $candidate ] && ( false === $open || $next[ $candidate ] < $open ) ) {
					$open = $next[ $candidate ];
					$name = $candidate;
				}
			}

			if ( false === $open ) {
				break;
			}

			$tag_end = strpos( $html, '>', $open );
			$end     = false === $tag_end ? false : self::closing_tag( $html, $name, $tag_end + 1 );

			if ( false === $end ) {
				// No closing tag after this one, so none after a later one of its name either: they all stay.
				$next[ $name ] = false;
				continue;
			}

			$out .= substr( $html, $at, $open - $at );
			$at   = $end;
		}

		return $out . substr( $html, $at );
	}

	/**
	 * Where the next opening tag of a name starts, from an offset: `<` and the name (any case) followed
	 * by whitespace, `>` or `/`. False when there is none.
	 *
	 * @param string $html   Markup.
	 * @param string $name   Lower case element name.
	 * @param int    $offset Where to start.
	 * @return int|false
	 */
	private static function opening_tag( string $html, string $name, int $offset ) {
		$length = strlen( $name );

		while ( true ) {
			$pos = stripos( $html, '<' . $name, $offset );

			if ( false === $pos ) {
				break;
			}

			$after = substr( $html, $pos + 1 + $length, 1 );

			if ( '' !== $after && false !== strpos( " \t\n\r\f\v>/", $after ) ) {
				return $pos;
			}

			$offset = $pos + 1;
		}

		return false;
	}

	/**
	 * Where the text after the first closing tag of a name begins, from an offset: `</`, the name (any
	 * case), optional whitespace and `>`. False when there is no closing tag.
	 *
	 * @param string $html   Markup.
	 * @param string $name   Lower case element name.
	 * @param int    $offset Where to start.
	 * @return int|false
	 */
	private static function closing_tag( string $html, string $name, int $offset ) {
		$length = strlen( $name );

		while ( true ) {
			$pos = stripos( $html, '</' . $name, $offset );

			if ( false === $pos ) {
				break;
			}

			$from  = $pos + 2 + $length;
			$from += strspn( $html, " \t\n\r\f\v", $from );

			if ( '>' === substr( $html, $from, 1 ) ) {
				return $from + 1;
			}

			$offset = $pos + 1;
		}

		return false;
	}

	/**
	 * A page's markup into passages.
	 *
	 * A new passage starts at each h2, h3 and h4 and at each details with an id. `heading` is the path of
	 * the headings above it joined with " > " (a details' summary is one level below the heading it sits
	 * under); `anchor` is the heading's or the details' id, or ''. Text before the first heading belongs
	 * to a passage with no heading and no anchor. A body over PASSAGE_WORDS words is split. Comments,
	 * scripts, styles and SVGs are taken out first (`unhidden()`), so a heading inside one starts nothing.
	 *
	 * @param string $html Block markup.
	 * @return array<int, array{heading: string, anchor: string, body: string}>
	 */
	public static function passages( string $html ): array {
		$html = self::unhidden( $html );
		$re   = '~<(h[2-4])\b([^>]*)>(.*?)</\1\s*>|<details\b([^>]*)>|</details\s*>~is';
		if ( false === preg_match_all( $re, $html, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			$found = array();
		}

		$out     = array();
		$levels  = array();     // Heading text by level (2 to 4).
		$h_anch  = '';          // The anchor of the heading last seen.
		$frames  = array();     // One per open details: its context, or null when it has no id.
		$context = array( '', '' );
		$buffer  = '';
		$pos     = 0;

		foreach ( $found as $m ) {
			$tag     = isset( $m[1][0] ) ? $m[1][0] : '';
			$start   = $m[0][1];
			$buffer .= substr( $html, $pos, $start - $pos );
			$pos     = $start + strlen( $m[0][0] );

			if ( '' !== $tag ) {
				// A heading.
				self::emit( $out, $context, $buffer );
				$buffer = '';
				$level  = (int) substr( $tag, 1 );
				foreach ( array_keys( $levels ) as $l ) {
					if ( $l >= $level ) {
						unset( $levels[ $l ] );
					}
				}
				$text = self::line( $m[3][0] );
				if ( '' !== $text ) {
					$levels[ $level ] = $text;
				}
				$h_anch  = self::attr_id( $m[2][0] );
				$context = array( self::path( $levels ), $h_anch );
				continue;
			}

			if ( 0 === stripos( $m[0][0], '</details' ) ) {
				// The end of a details: what follows belongs to the section around it.
				$closed = array_pop( $frames );
				if ( null !== $closed ) {
					self::emit( $out, $context, $buffer );
					$buffer  = '';
					$context = array( self::path( $levels ), $h_anch );
					for ( $i = count( $frames ) - 1; $i >= 0; $i-- ) {
						if ( null !== $frames[ $i ] ) {
							$context = $frames[ $i ];
							break;
						}
					}
				}
				continue;
			}

			// A details opens. Only one with an id is a section of its own.
			$id = self::attr_id( isset( $m[4][0] ) ? $m[4][0] : '' );
			if ( '' === $id ) {
				$frames[] = null;
				continue;
			}
			self::emit( $out, $context, $buffer );
			$buffer = '';
			$title  = '';
			if ( 1 === preg_match( '~\G\s*<summary\b[^>]*>(.*?)</summary\s*>~is', $html, $s, PREG_OFFSET_CAPTURE, $pos ) ) {
				$title = self::line( $s[1][0] );
				$pos   = $s[0][1] + strlen( $s[0][0] );
			}
			$trail = $levels;
			if ( '' !== $title ) {
				$trail[] = $title;
			}
			$context  = array( self::path( $trail ), $id );
			$frames[] = $context;
		}

		$buffer .= substr( $html, $pos );
		self::emit( $out, $context, $buffer );
		return $out;
	}

	/**
	 * Adds the passages of one section to the list: nothing for an empty body, several for a long one.
	 *
	 * @param array    $out     The list so far.
	 * @param string[] $context The heading path and the anchor.
	 * @param string   $markup  The section's markup.
	 */
	private static function emit( array &$out, array $context, string $markup ): void {
		$body = self::plain( $markup );
		if ( '' === $body ) {
			return;
		}
		foreach ( self::split( $body ) as $part ) {
			$out[] = array(
				'heading' => $context[0],
				'anchor'  => $context[1],
				'body'    => $part,
			);
		}
	}

	/**
	 * Splits a body of more than PASSAGE_WORDS words at line ends into parts of at most that many words. A
	 * single line over the limit is split at sentence ends, then at that many words.
	 *
	 * @param string $body Plain text, one block to a line.
	 * @return string[]
	 */
	private static function split( string $body ): array {
		if ( self::words( $body ) <= self::PASSAGE_WORDS ) {
			return array( $body );
		}

		// Pieces, each with the separator that joined it to the one before.
		$pieces = array();
		foreach ( explode( "\n", $body ) as $line ) {
			if ( self::words( $line ) <= self::PASSAGE_WORDS ) {
				$pieces[] = array( "\n", $line );
				continue;
			}
			$first = true;
			foreach ( self::sentences( $line ) as $sentence ) {
				foreach ( self::chunks( $sentence ) as $chunk ) {
					$pieces[] = array( $first ? "\n" : ' ', $chunk );
					$first    = false;
				}
			}
		}

		$parts = array();
		$text  = '';
		$count = 0;
		foreach ( $pieces as $piece ) {
			$n = self::words( $piece[1] );
			if ( '' !== $text && $count + $n > self::PASSAGE_WORDS ) {
				$parts[] = $text;
				$text    = '';
				$count   = 0;
			}
			$text  .= ( '' === $text ? '' : $piece[0] ) . $piece[1];
			$count += $n;
		}
		if ( '' !== $text ) {
			$parts[] = $text;
		}
		return $parts;
	}

	/**
	 * A line at its sentence ends.
	 *
	 * @param string $line A line.
	 * @return string[]
	 */
	private static function sentences( string $line ): array {
		$parts = preg_split( '/(?<=[.!?])\s+/u', $line, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $parts ) && array() !== $parts ? $parts : array( $line );
	}

	/**
	 * A run of words in pieces of at most PASSAGE_WORDS words.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	private static function chunks( string $text ): array {
		$words = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $words ) || count( $words ) <= self::PASSAGE_WORDS ) {
			return array( $text );
		}
		return array_map(
			function ( $chunk ) {
				return implode( ' ', $chunk );
			},
			array_chunk( $words, self::PASSAGE_WORDS )
		);
	}

	/**
	 * The number of words in a text.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private static function words( string $text ): int {
		$words = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $words ) ? count( $words ) : 0;
	}

	/**
	 * A heading's or a summary's markup as one line of text.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	private static function line( string $html ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', self::plain( $html ) ) );
	}

	/**
	 * The headings above a point joined into a path.
	 *
	 * @param string[] $trail Heading texts, shallowest first.
	 * @return string
	 */
	private static function path( array $trail ): string {
		ksort( $trail );
		return implode( ' > ', $trail );
	}

	/**
	 * The id in a tag's attributes, or ''.
	 *
	 * @param string $attributes The attributes.
	 * @return string
	 */
	private static function attr_id( string $attributes ): string {
		if ( 1 === preg_match( '/(?:^|\s)id\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $attributes, $m ) ) {
			return html_entity_decode( isset( $m[2] ) && '' !== $m[2] ? $m[2] : $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
		return '';
	}

	// Porter helpers.

	/**
	 * Whether a word ends with a suffix.
	 *
	 * @param string $word   A word.
	 * @param string $suffix A suffix.
	 * @return bool
	 */
	private static function ends( string $word, string $suffix ): bool {
		$n = strlen( $suffix );
		return strlen( $word ) >= $n && substr( $word, -$n ) === $suffix;
	}

	/**
	 * Whether the letter at a position is a consonant: not a, e, i, o or u, and a y only after a vowel.
	 *
	 * @param string $word A word.
	 * @param int    $i    A position.
	 * @return bool
	 */
	private static function is_consonant( string $word, int $i ): bool {
		$c = $word[ $i ];
		if ( 'a' === $c || 'e' === $c || 'i' === $c || 'o' === $c || 'u' === $c ) {
			return false;
		}
		if ( 'y' === $c ) {
			return 0 === $i ? true : ! self::is_consonant( $word, $i - 1 );
		}
		return true;
	}

	/**
	 * Porter's measure m of a stem: the number of vowel-consonant sequences in [C](VC){m}[V].
	 *
	 * @param string $stem A stem.
	 * @return int
	 */
	private static function measure( string $stem ): int {
		$form = '';
		$n    = strlen( $stem );
		for ( $i = 0; $i < $n; $i++ ) {
			$letter = self::is_consonant( $stem, $i ) ? 'c' : 'v';
			if ( substr( $form, -1 ) !== $letter ) {
				$form .= $letter;
			}
		}
		return substr_count( $form, 'vc' );
	}

	/**
	 * Whether a stem has a vowel.
	 *
	 * @param string $stem A stem.
	 * @return bool
	 */
	private static function has_vowel( string $stem ): bool {
		$n = strlen( $stem );
		for ( $i = 0; $i < $n; $i++ ) {
			if ( ! self::is_consonant( $stem, $i ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a word ends in a double consonant.
	 *
	 * @param string $word A word.
	 * @return bool
	 */
	private static function double_consonant( string $word ): bool {
		$n = strlen( $word );
		return $n >= 2 && $word[ $n - 1 ] === $word[ $n - 2 ] && self::is_consonant( $word, $n - 1 );
	}

	/**
	 * Whether a word ends consonant, vowel, consonant, the last not w, x or y.
	 *
	 * @param string $word A word.
	 * @return bool
	 */
	private static function cvc( string $word ): bool {
		$n = strlen( $word );
		if ( $n < 3 || ! self::is_consonant( $word, $n - 1 ) || self::is_consonant( $word, $n - 2 ) || ! self::is_consonant( $word, $n - 3 ) ) {
			return false;
		}
		return ! in_array( $word[ $n - 1 ], array( 'w', 'x', 'y' ), true );
	}

	/**
	 * Replaces the longest listed suffix a word has, when the stem before it has a measure over the
	 * floor. Only the longest match is tried: if its condition fails, nothing changes.
	 *
	 * @param string                $word  A word.
	 * @param array<string, string> $rules Suffix => replacement.
	 * @param int                   $floor The measure the stem must exceed.
	 * @return string
	 */
	private static function replace_suffix( string $word, array $rules, int $floor ): string {
		uksort(
			$rules,
			function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		foreach ( $rules as $suffix => $replacement ) {
			if ( ! self::ends( $word, $suffix ) ) {
				continue;
			}
			$stem = substr( $word, 0, strlen( $word ) - strlen( $suffix ) );
			if ( 'ion' === $suffix && ! in_array( substr( $stem, -1 ), array( 's', 't' ), true ) ) {
				return $word;
			}
			return self::measure( $stem ) > $floor ? $stem . $replacement : $word;
		}
		return $word;
	}
}
