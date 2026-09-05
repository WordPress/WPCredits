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
	 * `add_option()` returns false when the row already exists, so the first caller wins. A
	 * lock older than LOCK_TIMEOUT belonged to a request that died holding it and is taken over.
	 *
	 * What this is not: a true test-and-set. Core's add_option() is a get_option() pre-check
	 * followed by an `INSERT ... ON DUPLICATE KEY UPDATE`, so two lockers whose INSERTs land in
	 * different seconds inside one database round trip both succeed, and both then take the
	 * same code. The window is milliseconds, the harm (two people given one code) is
	 * recoverable by a manager's void, and the report generation and the syncs share this same
	 * primitive (spec section 3, decision 4). A plain `$wpdb->insert()`, which the unique key
	 * on `option_name` makes fail outright, would close it; deliberately not done in this
	 * release (final review of Phase S2, finding 4).
	 *
	 * @param int $offer_id Offer post ID.
	 * @return bool
	 */
	public static function lock( $offer_id ) {
		$name = self::LOCK_PREFIX . (int) $offer_id;

		if ( add_option( $name, time(), '', false ) ) {
			return true;
		}

		$held = (int) get_option( $name );

		if ( $held && ( time() - $held ) < self::LOCK_TIMEOUT ) {
			return false;
		}

		update_option( $name, time(), false );

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
	 * @param string $text What was pasted.
	 * @return array `codes` (the strings), `lines` (each one's line number), `errors` (sentences).
	 */
	public static function parse( $text ) {
		$out  = array(
			'codes'  => array(),
			'lines'  => array(),
			'errors' => array(),
		);
		$seen = array();

		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $n => $line ) {
			$number = $n + 1;
			$line   = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			// A CSV row: the code is the first column. A URL is taken whole even with a comma in
			// its query, because a checkout link is a code too and splitting one would keep half.
			if ( false !== strpos( $line, ',' ) && ! preg_match( '#^https?://#i', $line ) ) {
				$cells = str_getcsv( $line );
				$line  = trim( (string) $cells[0] );

				if ( '' === $line ) {
					continue;
				}
			}

			if ( mb_strlen( $line ) > self::LINE_MAX ) {
				/* translators: 1: line number, 2: the longest line allowed. */
				$out['errors'][] = sprintf( __( 'Line %1$d is longer than %2$d characters.', 'wpcredits-program-manager' ), $number, self::LINE_MAX );
				continue;
			}

			if ( isset( $seen[ $line ] ) ) {
				/* translators: 1: line number, 2: the earlier line number it repeats. */
				$out['errors'][] = sprintf( __( 'Line %1$d repeats line %2$d.', 'wpcredits-program-manager' ), $number, $seen[ $line ] );
				continue;
			}

			$seen[ $line ]  = $number;
			$out['codes'][] = $line;
			$out['lines'][] = $number;
		}

		return $out;
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
			$known[ (string) $entry['h'] ] = true;
		}

		foreach ( $prints as $k => $print ) {
			if ( isset( $known[ $print ] ) ) {
				/* translators: %d: line number. */
				$parsed['errors'][] = sprintf( __( 'Line %d is already in this offer.', 'wpcredits-program-manager' ), $parsed['lines'][ $k ] );
			}
		}

		if ( ! empty( $parsed['errors'] ) ) {
			self::unlock( $offer_id );

			return new WP_Error( 'wpcpm_codes_refused', implode( ' ', $parsed['errors'] ), $parsed['errors'] );
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
