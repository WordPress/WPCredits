<?php
/**
 * A sponsor offer's codes: sealed at rest, handed out one at a time, counted.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One option per offer, `wpcpm_codes_<post_id>`, not autoloaded, versioned.
 *
 * `array( 'v' => 1, 'shared' => sealed or '', 'codes' => array( array( 's' => sealed, 'h' =>
 * fingerprint, 'st' => available|claimed|void, 'by' => user_id, 'at' => time ) ), 'claims' =>
 * array( array( 'u' => user_id, 'i' => index or -1, 'at' => time, 'v' => time or 0 ) ) )`.
 *
 * Sealed means WPCPM_Secret::seal_for_option(): the site key, base64 for the option row. The
 * fingerprint finds duplicates without unsealing, and is keyed because a bare hash of a short
 * code is enumerated in seconds (plan ruling 2). The claims ledger lives here beside the codes
 * so that the sponsor's counts and the manager's claimant list read one option per offer
 * instead of every account's meta (plan ruling 3); the person's own copy is in their
 * `wpcpm_claims` meta (WPCPM_Sponsor_Claims). No custom table: a pool is hundreds of codes,
 * capped at five thousand (spec section 3, decision 4).
 *
 * Nothing here decides who may claim. The pool's lock (lock(), unlock(), LOCK_PREFIX,
 * LOCK_TIMEOUT) guards every rewrite of the pool: add(), set_shared(), void_unclaimed(),
 * void_index() and void_shared_claim() all take it before self::read() and release it on
 * every path out. take() and record_shared() are the two rewrites WPCPM_Sponsor_Claims::claim()
 * makes with that lock already held, so they never take it themselves.
 *
 * What sealing the codes buys, so that a later reader does not over-trust it: protection
 * against a partial exposure of the option row (an options export, a debugging screen that
 * lists options, a stray var_dump), not against a reader of the database, who has the site key
 * in `wpcpm_private_key` in the same table. The agreement files' two-store split, where the
 * sealed bytes and the key never share a store, is the stronger arrangement; a pool of coupon
 * codes did not earn one (final review of Phase S2).
 */
final class WPCPM_Sponsor_Codes {

	const OPT_PREFIX = 'wpcpm_codes_';
	const VERSION    = 1;

	/** A code can be a whole checkout URL. */
	const LINE_MAX = 200;

	/** Above this the sponsor is told to talk to the program (spec section 6.2). */
	const CODES_MAX = 5000;

	/** A refusal names at most this many lines and then counts the rest (deep check FOFFR-1). */
	const ERRORS_MAX = 10;

	/** A shared claim's index: there is no code row to point at. */
	const SHARED_INDEX = -1;

	const ST_AVAILABLE = 'available';
	const ST_CLAIMED   = 'claimed';
	const ST_VOID      = 'void';

	const LOCK_PREFIX  = 'wpcpm_claim_';
	const LOCK_TIMEOUT = 30;

	/**
	 * The option name for one offer's pool.
	 *
	 * @param int $offer_id Offer post ID.
	 * @return string
	 */
	public static function option_name( $offer_id ) {
		return self::OPT_PREFIX . (int) $offer_id;
	}

	/**
	 * A pool with nothing in it yet.
	 *
	 * @return array
	 */
	public static function empty_pool() {
		return array(
			'v'      => self::VERSION,
			'shared' => '',
			'codes'  => array(),
			'claims' => array(),
		);
	}

	/**
	 * The pool, or an empty one for an offer that has none yet or one written by a version
	 * this code does not read.
	 *
	 * @param int $offer_id Offer post ID.
	 * @return array
	 */
	public static function read( $offer_id ) {
		$stored = get_option( self::option_name( $offer_id ) );

		if ( ! is_array( $stored ) || ! isset( $stored['v'] ) || self::VERSION !== (int) $stored['v'] ) {
			return self::empty_pool();
		}

		$pool           = array_merge( self::empty_pool(), $stored );
		$pool['codes']  = is_array( $pool['codes'] ) ? $pool['codes'] : array();
		$pool['claims'] = is_array( $pool['claims'] ) ? $pool['claims'] : array();
		$pool['shared'] = is_string( $pool['shared'] ) ? $pool['shared'] : '';

		return $pool;
	}

	/**
	 * Write the pool. `add_option()` first so the row is created non-autoloaded: an
	 * `update_option()` on a row that does not exist autoloads it, and five thousand sealed
	 * codes on every request is the one thing this store must not do.
	 *
	 * @param int   $offer_id Offer post ID.
	 * @param array $pool     The pool.
	 */
	public static function write( $offer_id, array $pool ) {
		$name      = self::option_name( $offer_id );
		$pool['v'] = self::VERSION;

		if ( false === get_option( $name ) && add_option( $name, $pool, '', false ) ) {
			return;
		}

		update_option( $name, $pool, false );
	}

	/**
	 * Delete an offer's pool entirely.
	 *
	 * @param int $offer_id Offer post ID.
	 */
	public static function delete( $offer_id ) {
		delete_option( self::option_name( $offer_id ) );

		// The pool takes its lock with it: uninstall deletes the offers first, so anything that
		// iterates offers afterwards to find locks finds none (final review, finding 6).
		delete_option( self::LOCK_PREFIX . (int) $offer_id );
	}

	/**
	 * Take the lock that guards every rewrite of the pool, not the claim alone.
	 *
	 * `add_option()` returns false when the row already exists, so the first caller wins. A lock
	 * older than LOCK_TIMEOUT belonged to a request that died holding it, and is taken over by an
	 * UPDATE conditional on the stamp that was read: the takeover used to be a plain get_option()
	 * then update_option(), so two requests that read the same stale stamp were both given the
	 * lock, and take()'s whole-pool rewrite then erased the loser's ledger row. That claimant
	 * holds a code the counts do not know about, is absent from claimants(), and so has no Void
	 * button for a manager to press (deep check FOFFR-3).
	 *
	 * What this is not: a true test-and-set on the `add_option()` path above. Core's add_option()
	 * is a get_option() pre-check followed by an `INSERT ... ON DUPLICATE KEY UPDATE`, so two
	 * lockers whose INSERTs land in different seconds inside one database round trip both
	 * succeed, and both then take the same code, with the same erased ledger row. The window is
	 * milliseconds and this primitive is the one the report generation and the syncs share (spec
	 * section 3, decision 4); a plain `$wpdb->insert()`, which the unique key on `option_name`
	 * makes fail outright, would close it, and is deliberately not done in this release (final
	 * review of Phase S2, finding 4).
	 *
	 * One more gap the takeover leaves silent: the row disappearing between the `add_option()`
	 * check above and the `get_option()` read below, if another request's `unlock()` (a plain
	 * `delete_option()`) lands in that instant. `get_option()` then returns `false`,
	 * `(string) $stale` is an empty string, and the UPDATE's `WHERE option_value = ''` matches no
	 * row, because the row itself is gone, not merely stale, so this caller is refused the lock
	 * the same as if somebody else's fresh stamp had won the race. The next caller finds the row
	 * still absent, so its own `add_option()` succeeds outright and takes the lock cleanly.
	 * Refusing here is the safe direction: this caller cannot tell a gone-and-free row from a
	 * gone-and-contested one, so declining and sending the claimant back to reload costs a click,
	 * not a pool two claimants both believe they hold.
	 *
	 * @param int $offer_id Offer post ID.
	 * @return bool
	 */
	public static function lock( $offer_id ) {
		global $wpdb;

		$name = self::LOCK_PREFIX . (int) $offer_id;

		if ( add_option( $name, time(), '', false ) ) {
			return true;
		}

		$stale = get_option( $name );
		$held  = (int) $stale;

		if ( $held && ( time() - $held ) < self::LOCK_TIMEOUT ) {
			return false;
		}

		// Conditional on the value that was just read, which no options API call can express.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The whole point is to write only while the row still holds the stale stamp; the option's cache entry is dropped below.
		$taken = $wpdb->update(
			$wpdb->options,
			array( 'option_value' => time() ),
			array(
				'option_name'  => $name,
				'option_value' => (string) $stale,
			)
		);

		// Somebody else took the stale lock between the read and the write: theirs, not ours.
		if ( 1 !== (int) $taken ) {
			return false;
		}

		wp_cache_delete( $name, 'options' );

		return true;
	}

	/**
	 * Release the pool's lock for one offer.
	 *
	 * @param int $offer_id Offer post ID.
	 */
	public static function unlock( $offer_id ) {
		delete_option( self::LOCK_PREFIX . (int) $offer_id );
	}

	/**
	 * The one answer for a pool somebody else is changing this second.
	 *
	 * @return WP_Error
	 */
	public static function busy() {
		return new WP_Error( 'wpcpm_codes_busy', __( 'Another change to this offer was going through. Try again in a moment.', 'wpcredits-program-manager' ) );
	}

	/**
	 * Parse a paste: one code per line, or the first column of a CSV row.
	 *
	 * The lines are counted before a single one is parsed, and the sentences are capped, because
	 * neither was bounded: a megabyte of one repeated code built half a million "Line N repeats
	 * line 1." sentences and died inside this method at a 128 M memory limit, and survived a
	 * 256 M one only to hand a 14 MB message to a screen that clips it at 300 characters (deep
	 * check FOFFR-1). Refusing the count first bounds the split, the map of what has been seen
	 * and the codes themselves, not only the sentences.
	 *
	 * @param string $text What was pasted.
	 * @return array `codes` (the strings), `lines` (each one's line number), `errors` (at most
	 *               ERRORS_MAX sentences), `problems` (how many lines are at fault in all).
	 */
	public static function parse( $text ) {
		$text = (string) $text;
		$out  = array(
			'codes'    => array(),
			'lines'    => array(),
			'errors'   => array(),
			'problems' => 0,
		);
		$seen = array();

		// Counted without splitting: building the array of lines is itself the cost when the
		// paste is a megabyte of two-character lines.
		//
		// Lines carrying something, not lines. A blank line is not a code, and an uploaded .txt
		// with a blank line between each code is an ordinary thing to be handed: counting every
		// line refused a file of 5,000 codes for holding 9,999 "lines", which was true and
		// useless (the whole-branch review of 1.99.0). The pattern matches once per line that
		// holds a non-whitespace character - the alternation is the line break rather than the
		// `m` modifier, so a lone `\r` counts like the split below treats it - and still never
		// builds the array.
		$count = (int) preg_match_all( '/(?:^|\r\n|\r|\n)[^\r\n]*\S/', $text );

		if ( $count > self::CODES_MAX ) {
			/* translators: 1: how many codes were pasted, 2: the most codes an offer holds. */
			$out['errors'][] = sprintf( __( 'This list has %1$d codes; an offer takes at most %2$d.', 'wpcredits-program-manager' ), $count, self::CODES_MAX );
			$out['problems'] = 1;

			return $out;
		}

		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $n => $line ) {
			$number = $n + 1;
			$line   = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			// A CSV row: the code is the first column. A URL is taken whole even with a comma in
			// its query, because a checkout link is a code too and splitting one would keep half.
			if ( false !== strpos( $line, ',' ) && ! preg_match( '#^https?://#i', $line ) ) {
				// The escape argument is given rather than left to its default: PHP 8.4 deprecates
				// the default and is changing its value, so a cell with a backslash before a quote
				// would be stored as a different code on a newer host (deep check FOFFR-5). Both
				// institution exports already ask for the same behavior.
				$cells = str_getcsv( $line, ',', '"', '' );
				$line  = trim( (string) $cells[0] );

				if ( '' === $line ) {
					continue;
				}
			}

			// Every fault is counted, and the sentence for it is built only while there is room
			// for one: the sentences, not the faults, are what the memory went on (FOFFR-1).
			if ( mb_strlen( $line ) > self::LINE_MAX ) {
				++$out['problems'];

				if ( count( $out['errors'] ) < self::ERRORS_MAX ) {
					/* translators: 1: line number, 2: the longest line allowed. */
					$out['errors'][] = sprintf( __( 'Line %1$d is longer than %2$d characters.', 'wpcredits-program-manager' ), $number, self::LINE_MAX );
				}

				continue;
			}

			if ( isset( $seen[ $line ] ) ) {
				++$out['problems'];

				if ( count( $out['errors'] ) < self::ERRORS_MAX ) {
					/* translators: 1: line number, 2: the earlier line number it repeats. */
					$out['errors'][] = sprintf( __( 'Line %1$d repeats line %2$d.', 'wpcredits-program-manager' ), $number, $seen[ $line ] );
				}

				continue;
			}

			$seen[ $line ]  = $number;
			$out['codes'][] = $line;
			$out['lines'][] = $number;
		}

		return $out;
	}

	/**
	 * The refusal a faulty list makes: the sentences that were kept, and a closing count when
	 * more lines than ERRORS_MAX are at fault. One sentence per bad line is what turned a
	 * megabyte of repeats into a 14 MB message (deep check FOFFR-1); the count keeps the sponsor
	 * from reading ten sentences and thinking that is all there is.
	 *
	 * @param array $parsed parse()'s answer, with any duplicates add() found already noted.
	 * @return WP_Error
	 */
	public static function refusal( array $parsed ) {
		$errors   = isset( $parsed['errors'] ) ? (array) $parsed['errors'] : array();
		$problems = isset( $parsed['problems'] ) ? (int) $parsed['problems'] : count( $errors );
		$more     = $problems - count( $errors );

		if ( $more > 0 ) {
			/* translators: %d: how many more lines are at fault. */
			$errors[] = sprintf( _n( 'and %d more line has a problem.', 'and %d more lines have problems.', $more, 'wpcredits-program-manager' ), $more );
		}

		return new WP_Error( 'wpcpm_codes_refused', implode( ' ', $errors ), $errors );
	}

	/**
	 * Add codes to an offer. All or nothing: a paste with one fault adds nothing and names the
	 * lines, so the sponsor fixes the paste rather than hunting for what went in.
	 *
	 * @param int    $offer_id Offer post ID.
	 * @param string $text     The paste.
	 * @return int|WP_Error How many were added.
	 */
	public static function add( $offer_id, $text ) {
		// Parsing and fingerprinting touch no pool, so they happen before the lock is taken;
		// the lock is held for the read-check-write alone.
		$parsed = self::parse( $text );
		$prints = array();

		foreach ( $parsed['codes'] as $k => $code ) {
			$print = WPCPM_Secret::fingerprint( $code );

			if ( is_wp_error( $print ) ) {
				return $print;
			}

			$prints[ $k ] = $print;
		}

		if ( ! self::lock( $offer_id ) ) {
			return self::busy();
		}

		$pool  = self::read( $offer_id );
		$known = array();

		foreach ( $pool['codes'] as $entry ) {
			// The state, not just the row: a voided code stays unusable in this offer, and being
			// told a line "is already in this offer" while the card reads 0 available reads as a
			// bug rather than as the rule it is (deep check FOFFR-4).
			$known[ (string) $entry['h'] ] = isset( $entry['st'] ) ? (string) $entry['st'] : '';
		}

		foreach ( $prints as $k => $print ) {
			if ( ! isset( $known[ $print ] ) ) {
				continue;
			}

			++$parsed['problems'];

			// Under the same ceiling as the parse's own sentences (FOFFR-1).
			if ( count( $parsed['errors'] ) >= self::ERRORS_MAX ) {
				continue;
			}

			if ( self::ST_VOID === $known[ $print ] ) {
				/* translators: %d: line number. */
				$parsed['errors'][] = sprintf( __( 'Line %d was voided in this offer earlier.', 'wpcredits-program-manager' ), $parsed['lines'][ $k ] );
			} else {
				/* translators: %d: line number. */
				$parsed['errors'][] = sprintf( __( 'Line %d is already in this offer.', 'wpcredits-program-manager' ), $parsed['lines'][ $k ] );
			}
		}

		if ( ! empty( $parsed['errors'] ) ) {
			self::unlock( $offer_id );

			return self::refusal( $parsed );
		}

		if ( empty( $parsed['codes'] ) ) {
			self::unlock( $offer_id );

			return new WP_Error( 'wpcpm_codes_none', __( 'No codes were found in what you pasted.', 'wpcredits-program-manager' ) );
		}

		if ( count( $pool['codes'] ) + count( $parsed['codes'] ) > self::CODES_MAX ) {
			self::unlock( $offer_id );

			/* translators: %d: the most codes an offer holds. */
			return new WP_Error( 'wpcpm_codes_max', sprintf( __( 'An offer holds at most %d codes. Talk to the program about a larger pool.', 'wpcredits-program-manager' ), self::CODES_MAX ) );
		}

		foreach ( $parsed['codes'] as $k => $code ) {
			$sealed = WPCPM_Secret::seal_for_option( $code );

			if ( is_wp_error( $sealed ) ) {
				self::unlock( $offer_id );

				return $sealed;
			}

			$pool['codes'][] = array(
				's'  => $sealed,
				'h'  => $prints[ $k ],
				'st' => self::ST_AVAILABLE,
				'by' => 0,
				'at' => 0,
			);
		}

		self::write( $offer_id, $pool );
		self::unlock( $offer_id );

		return count( $parsed['codes'] );
	}

	/**
	 * How many codes are in each state.
	 *
	 * @param int $offer_id Offer post ID.
	 * @return array `available`, `claimed`, `void`, `total`.
	 */
	public static function counts( $offer_id ) {
		$pool   = self::read( $offer_id );
		$counts = array(
			'available' => 0,
			'claimed'   => 0,
			'void'      => 0,
			'total'     => count( $pool['codes'] ),
		);

		foreach ( $pool['codes'] as $entry ) {
			$state = isset( $entry['st'] ) ? (string) $entry['st'] : '';

			if ( isset( $counts[ $state ] ) ) {
				++$counts[ $state ];
			}
		}

		return $counts;
	}

	/**
	 * Hand the first available code to a person. Called by WPCPM_Sponsor_Claims::claim() with
	 * the pool lock held; never take it here.
	 *
	 * @param int $offer_id Offer post ID.
	 * @param int $user_id  The claimant.
	 * @return int|WP_Error The code's index.
	 */
	public static function take( $offer_id, $user_id ) {
		$pool = self::read( $offer_id );

		foreach ( $pool['codes'] as $index => $entry ) {
			// The guard counts() uses. Only this class writes rows, so a row without `st` is a
			// bug elsewhere, but a notice raised under the pool's lock in the middle of a claim
			// is the worst place to find out (final review of Phase S2, finding 12).
			if ( ! isset( $entry['st'] ) || self::ST_AVAILABLE !== $entry['st'] ) {
				continue;
			}

			$now = time();

			$pool['codes'][ $index ]['st'] = self::ST_CLAIMED;
			$pool['codes'][ $index ]['by'] = (int) $user_id;
			$pool['codes'][ $index ]['at'] = $now;
			$pool['claims'][]              = array(
				'u'  => (int) $user_id,
				'i'  => (int) $index,
				'at' => $now,
				'v'  => 0,
			);

			self::write( $offer_id, $pool );

			return (int) $index;
		}

		return new WP_Error( 'wpcpm_codes_empty', __( 'This offer has no codes left.', 'wpcredits-program-manager' ) );
	}

	/**
	 * Record a claim of the shared code: nothing to hand out, the ledger only. Called by
	 * WPCPM_Sponsor_Claims::claim() with the pool lock held; never take it here.
	 *
	 * @param int $offer_id Offer post ID.
	 * @param int $user_id  The claimant.
	 */
	public static function record_shared( $offer_id, $user_id ) {
		$pool             = self::read( $offer_id );
		$pool['claims'][] = array(
			'u'  => (int) $user_id,
			'i'  => self::SHARED_INDEX,
			'at' => time(),
			'v'  => 0,
		);

		self::write( $offer_id, $pool );
	}

	/**
	 * The code at an index, unsealed; '' when there is none or it will not open.
	 *
	 * @param int $offer_id Offer post ID.
	 * @param int $index    Its index.
	 * @return string
	 */
	public static function code_at( $offer_id, $index ) {
		$pool = self::read( $offer_id );

		// The sealed value as well as the row: a row without `s` gives '', not a notice.
		if ( ! isset( $pool['codes'][ (int) $index ]['s'] ) ) {
			return '';
		}

		$plain = WPCPM_Secret::unseal_from_option( $pool['codes'][ (int) $index ]['s'] );

		return is_wp_error( $plain ) ? '' : $plain;
	}

	/**
	 * The shared code or link, unsealed; '' when there is none.
	 *
	 * @param int $offer_id Offer post ID.
	 * @return string
	 */
	public static function shared( $offer_id ) {
		$pool = self::read( $offer_id );

		if ( '' === $pool['shared'] ) {
			return '';
		}

		$plain = WPCPM_Secret::unseal_from_option( $pool['shared'] );

		return is_wp_error( $plain ) ? '' : $plain;
	}

	/**
	 * Set or clear the shared code.
	 *
	 * @param int    $offer_id Offer post ID.
	 * @param string $code     The shared code or link; '' clears it.
	 * @return true|WP_Error
	 */
	public static function set_shared( $offer_id, $code ) {
		if ( ! self::lock( $offer_id ) ) {
			return self::busy();
		}

		$pool = self::read( $offer_id );
		$code = trim( (string) $code );

		if ( '' === $code ) {
			$pool['shared'] = '';
		} else {
			$sealed = WPCPM_Secret::seal_for_option( $code );

			if ( is_wp_error( $sealed ) ) {
				self::unlock( $offer_id );

				return $sealed;
			}

			$pool['shared'] = $sealed;
		}

		self::write( $offer_id, $pool );
		self::unlock( $offer_id );

		return true;
	}

	/**
	 * A sponsor voids what nobody holds.
	 *
	 * @param int $offer_id Offer post ID.
	 * @return int|WP_Error How many.
	 */
	public static function void_unclaimed( $offer_id ) {
		if ( ! self::lock( $offer_id ) ) {
			return self::busy();
		}

		$pool = self::read( $offer_id );
		$n    = 0;

		foreach ( $pool['codes'] as $index => $entry ) {
			// Guarded as take() is, and for the same reason.
			if ( isset( $entry['st'] ) && self::ST_AVAILABLE === $entry['st'] ) {
				$pool['codes'][ $index ]['st'] = self::ST_VOID;
				++$n;
			}
		}

		if ( $n > 0 ) {
			self::write( $offer_id, $pool );
		}

		self::unlock( $offer_id );

		return $n;
	}

	/**
	 * A manager voids a claimed code. The row stays void for the count; the ledger row is
	 * flagged, so the person's claim no longer counts and they may claim again.
	 *
	 * @param int $offer_id Offer post ID.
	 * @param int $index    The claimed code's index.
	 * @return bool|WP_Error
	 */
	public static function void_index( $offer_id, $index ) {
		if ( ! self::lock( $offer_id ) ) {
			return self::busy();
		}

		$pool  = self::read( $offer_id );
		$index = (int) $index;

		if ( ! isset( $pool['codes'][ $index ] ) || self::ST_CLAIMED !== $pool['codes'][ $index ]['st'] ) {
			self::unlock( $offer_id );

			return false;
		}

		$pool['codes'][ $index ]['st'] = self::ST_VOID;

		foreach ( $pool['claims'] as $k => $claim ) {
			if ( (int) $claim['i'] === $index && empty( $claim['v'] ) ) {
				$pool['claims'][ $k ]['v'] = time();
			}
		}

		self::write( $offer_id, $pool );
		self::unlock( $offer_id );

		return true;
	}

	/**
	 * A manager frees a person from a shared claim: the ledger row is flagged.
	 *
	 * @param int $offer_id Offer post ID.
	 * @param int $user_id  The claimant.
	 * @return bool|WP_Error Whether a row was found.
	 */
	public static function void_shared_claim( $offer_id, $user_id ) {
		if ( ! self::lock( $offer_id ) ) {
			return self::busy();
		}

		$pool = self::read( $offer_id );
		$done = false;

		foreach ( $pool['claims'] as $k => $claim ) {
			if ( (int) $claim['u'] === (int) $user_id && self::SHARED_INDEX === (int) $claim['i'] && empty( $claim['v'] ) ) {
				$pool['claims'][ $k ]['v'] = time();
				$done                      = true;
			}
		}

		if ( $done ) {
			self::write( $offer_id, $pool );
		}

		self::unlock( $offer_id );

		return $done;
	}

	/**
	 * The ledger.
	 *
	 * @param int $offer_id Offer post ID.
	 * @return array
	 */
	public static function claims( $offer_id ) {
		return self::read( $offer_id )['claims'];
	}
}
