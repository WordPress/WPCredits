<?php
/**
 * What guards a form a stranger can post: the seven checks the institution application grew.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The anti-spam and consent primitives shared by every public form.
 *
 * Extracted from `WPCPM_Institution_Application` for Phase S5 of the Sponsors module (design
 * spec of 4 September 2026, section 3 decision 1 and section 9.1), so that two public forms run
 * one set of checks with one set of numbers. The order a form calls them in is the design, and
 * it is the caller's: this class owns the seven stages and knows nothing about which questions a
 * form asks.
 *
 * The seven stages, cheapest first. The order a handler runs them in is the handler's, and the
 * institution form's is the model: the per-actor ceiling before anything else, even the nonce,
 * so a wrong-nonce POST is counted rather than free; then the honeypot, the dwell token and the
 * consent; link scoring and the site-wide ceiling only once the row is worth keeping; the mail
 * ceiling last, right before the site sends anything.
 *
 * 1. **The honeypot.** A field no visitor can see. Filled, the row is held as spam and the sender
 *    is told nothing, because a bot that learns which attempt was recognized writes a better one.
 * 2. **The dwell token.** Minted when the form is drawn, signed with `wp_hash()` against the
 *    form's nonce, its own scope and twelve random characters of its own, judged on the way back:
 *    `spam` for a form posted faster than anybody can read it, a token never minted here, or one
 *    already used; `stale` for a tab left open overnight, which is not spam; `ok` for the rest.
 *    Single use through `WPCPM_Ceiling` with a limit of one, which is the one ceiling in the
 *    plugin that has to be exact, and the reason the random half exists: without it every
 *    logged-out visitor of a second shared one token and the second to submit was the replay.
 * 3. **The per-actor ceiling**, which refuses: five an hour from one source is nobody's genuine
 *    application, and this is the one layer that stores nothing at all.
 * 4. **The site-wide ceiling**, which degrades: past forty a day the caller keeps the row and
 *    holds it, so a flood cannot close the form to the one real applicant that afternoon.
 * 5. **Consent as a precondition**, with the evidence: the sentence as rendered, the policy's URL
 *    and ID, the policy's own modified time, when, the truncated address and the browser. "They
 *    agreed" is worthless if nobody can say what they agreed to, and the policy page is editable.
 * 6. **Link scoring**, which holds and never refuses.
 * 7. **The mail ceiling**, which caps what an unauthenticated path can make the site send.
 *
 * Nothing here verifies a nonce: that check stays in each handler, because the dwell token is
 * signed against the nonce that was posted and `check_token()` re-derives the signature from the
 * posted nonce after the handler has verified it. Nothing here writes a row a stranger can name:
 * every ceiling key is hashed by `WPCPM_Ceiling`, and the address is hashed before it becomes a
 * key.
 */
final class WPCPM_Form_Guard {

	/** Seconds a human takes to fill a form in at the very least. Below it, the row is spam. */
	const MIN_SECONDS = 6;

	/** How long a dwell token stays usable. Past it the answer is `stale`, which is not spam. */
	const TOKEN_LIFETIME = 12 * HOUR_IN_SECONDS;

	/** Submissions one source may make in an hour before it is refused outright. */
	const PER_HOUR = 5;

	/**
	 * Submissions a whole site takes in a day before every further one is held instead.
	 *
	 * Held, and not refused, and not silent either: the managers are spared and the applicant
	 * is still acknowledged, which is the caller's business and the reason `claim_site()` is a
	 * plain answer rather than an exit.
	 */
	const PER_DAY = 40;

	/**
	 * Acknowledgements a form will send in a day, site-wide, to addresses nobody has proved.
	 *
	 * Deliberately far above `PER_DAY`, and not equal to it: the degrade at forty is what stops
	 * managers being paged, and held rows begin at forty-one, so a mail ceiling of forty would
	 * silence precisely the rows the acknowledgement exists for. This one is a backstop against
	 * the path becoming a mailer, nothing else.
	 */
	const MAIL_PER_DAY = 200;

	/** How many links across the free text before the row is held for a human. */
	const MAX_LINKS = 3;

	/** The three answers `check_token()` gives. */
	const TOKEN_OK    = 'ok';
	const TOKEN_SPAM  = 'spam';
	const TOKEN_STALE = 'stale';

	/*
	 * 1. The honeypot
	 * --------------------------------------------------------------------
	 */

	/**
	 * Whether the field no visitor can see was filled in.
	 *
	 * @param string $field The honeypot's field name.
	 * @return bool
	 */
	public static function honeypot_filled( $field ) {
		return '' !== WPCPM_Request::posted_text( (string) $field );
	}

	/**
	 * Print the honeypot.
	 *
	 * Hidden with a class rather than `type="hidden"`, because a hidden input is not something a
	 * form-filling script mistakes for a question, and `display: none` in the stylesheet is what
	 * it does mistake for one. `aria-hidden` and `tabindex="-1"` keep it away from anybody
	 * reading the form with a screen reader or a keyboard, and the label says what to do with it
	 * for the one person whose stylesheet never loaded. The class is the institution form's,
	 * which `assets/css/application.css` hides; the sponsor form loads the same stylesheet.
	 *
	 * @param string $field The honeypot's field name.
	 */
	public static function render_honeypot( $field ) {
		printf(
			'<div class="wpcpm-application__confirm" aria-hidden="true"><label for="%1$s">%2$s</label><input type="text" id="%1$s" name="%1$s" value="" tabindex="-1" autocomplete="off" /></div>',
			esc_attr( (string) $field ),
			esc_html__( 'Leave this field empty.', 'wpcredits-program-manager' )
		);
	}

	/*
	 * 2. The dwell token
	 * --------------------------------------------------------------------
	 */

	/**
	 * Mint a dwell token for a form being rendered.
	 *
	 * Signed with `wp_hash()` so it cannot be forged, bound to the form's nonce so a token
	 * harvested from one page cannot be posted with another page's nonce, bound to the form's
	 * scope so a token minted for the institution form is a forgery on the sponsor form, and
	 * carrying the time it was minted so the two ends of the window can be judged. It is not
	 * secret and does not need to be: it says when this form was drawn, and nothing else.
	 *
	 * **And twelve random characters, carried in the token and signed with the rest** (S5
	 * review). Everything else in the signature is the same for everybody: the scope is the
	 * form's, the nonce is one value for every logged-out visitor of a tick, and the time is a
	 * second wide. Two strangers who opened the page in the same second were handed one token,
	 * and `check_token()` is exact about single use, so the second of them to submit had a
	 * genuine application filed as spam. The random half makes every page load its own token.
	 *
	 * @param string $scope  The form's own scope, e.g. `wpcpm-application-dwell`.
	 * @param string $action The form's nonce action; the nonce is minted here so the two agree.
	 * @return string
	 */
	public static function token( $scope, $action ) {
		$issued = time();
		$random = wp_generate_password( 12, false, false );

		return $issued . '.' . $random . '.' . self::sign_token( $scope, $issued, wp_create_nonce( (string) $action ), $random );
	}

	/**
	 * Judge a posted dwell token.
	 *
	 * Three answers, and the difference between them matters. `spam` is a form posted faster
	 * than anybody can read it, a token that was never minted here for this form, or one that
	 * has already been used: the caller stores those rows and holds them as spam, and the sender
	 * is told nothing. `stale` is a token older than half a day, which is an ordinary human with
	 * a tab left open overnight: they are asked to send again, with everything they typed still
	 * in the boxes. `ok` is everything else.
	 *
	 * Single use through `WPCPM_Ceiling` with a limit of one: `add_option()` is one INSERT that
	 * reports failure when the row exists, so two uses of a harvested token cannot both be the
	 * first however close together they arrive. The window is the token's own lifetime, so a
	 * claim outlives the token it stands for.
	 *
	 * Reads the posted `_wpnonce` itself, under `wp_nonce_field()`'s default name, so the form has
	 * to post its nonce under that name and the handler has to have verified it before calling.
	 *
	 * @param string $scope The form's own scope.
	 * @param string $token The posted token.
	 * @return string `ok`, `spam` or `stale`.
	 */
	public static function check_token( $scope, $token ) {
		$token = trim( (string) $token );
		$parts = explode( '.', $token );
		$count = count( $parts );

		// Three parts: time, random, signature. The two-part shape of tokens minted before the
		// S5 fix wave was accepted for one deploy window and expired with it (clean-up 1.98.1).
		if ( 3 !== $count || ! ctype_digit( $parts[0] ) || ! ctype_alnum( (string) $parts[1] ) ) {
			return self::TOKEN_SPAM;
		}

		$random = (string) $parts[1];

		// The nonce this token was signed against. Read here rather than passed in so that a
		// caller cannot check a token against a nonce other than the one that was posted with
		// it; every handler has already verified it by the time this runs.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This *is* the nonce, read to re-derive the signature; it is verified in the handler before this is called.
		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );

		if ( ! hash_equals( self::sign_token( $scope, (int) $parts[0], $nonce, $random ), $parts[ $count - 1 ] ) ) {
			return self::TOKEN_SPAM;
		}

		$age = time() - (int) $parts[0];

		if ( $age < self::MIN_SECONDS ) {
			return self::TOKEN_SPAM;
		}

		if ( $age > self::TOKEN_LIFETIME ) {
			return self::TOKEN_STALE;
		}

		// Hashed rather than used raw, the way `WPCPM_Ceiling` asks: nothing about a claim is
		// readable in the options table.
		if ( ! WPCPM_Ceiling::claim( 'dwell:' . wp_hash( $token ), 1, self::TOKEN_LIFETIME ) ) {
			return self::TOKEN_SPAM;
		}

		return self::TOKEN_OK;
	}

	/**
	 * The signature half of a token.
	 *
	 * One string, one shape: the scope, the time, the random half and the nonce, joined and
	 * hashed, so a token for one form's scope minted at one second is a forgery for any other.
	 *
	 * @param string $scope  The form's own scope.
	 * @param int    $issued When the token was minted.
	 * @param string $nonce  The form's nonce, which the token is bound to.
	 * @param string $random The token's own random half.
	 * @return string
	 */
	private static function sign_token( $scope, $issued, $nonce, $random ) {
		return substr( wp_hash( (string) $scope . '|' . (int) $issued . '|' . (string) $random . '|' . $nonce ), 0, 32 );
	}

	/*
	 * 3. The per-actor ceiling
	 * --------------------------------------------------------------------
	 */

	/**
	 * The address this request came from.
	 *
	 * A forwarded header is believed only when the connecting address is the one named in the
	 * `application_trusted_proxy` setting, because anybody can send that header: trusting it
	 * unconditionally would mean the per-actor ceiling is one line of a request away from being
	 * lifted, and the truncated address stored as consent evidence would be whatever the sender
	 * fancied. An empty setting means the connecting address is the client, which is true on
	 * this host.
	 *
	 * **From the right, not the left.** Every standard edge appends the address it saw the
	 * request arrive from, so the header reads `<whatever the client sent>, <what the edge saw>`:
	 * the rightmost entry is the edge's own word and the leftmost is the client's, who can write
	 * anything there. Reading the left end let an applicant choose their own limiter bucket and
	 * spam the public form once a proxy was configured. The edge itself is skipped when it appears
	 * (a chain of two of ours), an entry that is not an address at all is ignored, and a header
	 * with nothing usable falls back to REMOTE_ADDR.
	 *
	 * @return string An IP address, or '' when there is none to be had.
	 */
	public static function client_ip() {
		$remote = '';

		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$remote = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		$remote = filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';
		$proxy  = trim( (string) WPCPM_Settings::get_value( 'application_trusted_proxy', '' ) );

		if ( '' === $proxy || '' === $remote || $proxy !== $remote ) {
			return $remote;
		}

		$forwarded = '';

		if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		}

		$entries = array_reverse( array_map( 'trim', explode( ',', $forwarded ) ) );

		foreach ( $entries as $candidate ) {
			if ( $candidate === $proxy || ! filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				continue;
			}

			return $candidate;
		}

		return $remote;
	}

	/**
	 * The ceiling key for one source of one form.
	 *
	 * Hashed, so the options table holds no addresses. A request with no address at all shares
	 * one key with every other, which is the safe direction: they are counted together rather
	 * than being uncounted. The whole address and not a truncated one, deliberately: one campus
	 * behind a single NAT is exactly the population these forms are for, and five an hour shared
	 * between all of its staff would refuse the second real applicant of the afternoon to slow
	 * down an attacker who can change address anyway.
	 *
	 * @param string $prefix The form's own prefix, e.g. `apply`.
	 * @return string
	 */
	public static function actor_key( $prefix ) {
		return (string) $prefix . ':' . wp_hash( self::client_ip() );
	}

	/**
	 * Claim one of a source's places in the current hour.
	 *
	 * A refusal, not a hold, and the caller runs it before anything that writes: this is the one
	 * layer that counts a writer with no account, and the refusals below it hand the sender's
	 * writing back through a transient, which is two rows in the options table.
	 *
	 * @param string $key   From `actor_key()`.
	 * @param int    $limit Claims an hour admits.
	 * @return bool Whether the claim was counted.
	 */
	public static function claim_actor( $key, $limit = self::PER_HOUR ) {
		return WPCPM_Ceiling::claim( (string) $key, (int) $limit, HOUR_IN_SECONDS );
	}

	/*
	 * 4. The site-wide ceiling
	 * --------------------------------------------------------------------
	 */

	/**
	 * Claim one of the whole site's places in the current day.
	 *
	 * A plain answer: the caller that hears `false` keeps the row and holds it rather than
	 * refusing, so a flood cannot close the form to the one real applicant that afternoon.
	 *
	 * @param string $key   The form's own site-wide key, e.g. `apply-site`.
	 * @param int    $limit Claims a day admits.
	 * @return bool Whether the claim was counted.
	 */
	public static function claim_site( $key, $limit = self::PER_DAY ) {
		return WPCPM_Ceiling::claim( (string) $key, (int) $limit, DAY_IN_SECONDS );
	}

	/*
	 * 5. Consent
	 * --------------------------------------------------------------------
	 */

	/**
	 * Whether a posted value is a tick.
	 *
	 * The strict rule the report form set: the value the control carries, and nothing else.
	 * `"yes"` is what a hand-made request sends when somebody has read the form and decided the
	 * checkbox is a formality, and `! empty()` would take it; an array is not a tick either.
	 *
	 * @param mixed $raw The posted value.
	 * @return bool
	 */
	public static function consented( $raw ) {
		if ( ! is_scalar( $raw ) ) {
			return false;
		}

		$raw = trim( (string) $raw );

		return '1' === $raw || 'true' === strtolower( $raw );
	}

	/**
	 * The site's privacy policy, or an empty string when it has none.
	 *
	 * @return string
	 */
	public static function policy_url() {
		return function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '';
	}

	/**
	 * What was agreed to, and to which version of it.
	 *
	 * The sentence as it was rendered, the policy's address and post ID, and the policy's own
	 * `post_modified_gmt`. The last one is the point: "they agreed" is worth nothing if nobody
	 * can say what the document said that day, and a privacy policy is an ordinary page somebody
	 * can edit. The address is truncated on the way in, so the record says two applications came
	 * from the same building and never where somebody was.
	 *
	 * @param string $sentence The consent sentence as the form printed it.
	 * @return array{sentence: string, url: string, policy: int, modified: string, at: int, ip: string, agent: string}
	 */
	public static function consent_evidence( $sentence ) {
		$policy_id = (int) get_option( 'wp_page_for_privacy_policy' );
		$policy    = $policy_id ? get_post( $policy_id ) : null;

		return array(
			'sentence' => (string) $sentence,
			'url'      => self::policy_url(),
			'policy'   => $policy_id,
			'modified' => $policy instanceof WP_Post ? (string) $policy->post_modified_gmt : '',
			'at'       => time(),
			'ip'       => self::truncate_ip( self::client_ip() ),
			'agent'    => self::user_agent(),
		);
	}

	/*
	 * 6. Link scoring
	 * --------------------------------------------------------------------
	 */

	/**
	 * How many links a stretch of prose carries.
	 *
	 * @param string $prose Every free-text answer, joined.
	 * @return int
	 */
	public static function links_in( $prose ) {
		return (int) preg_match_all( '#https?://#i', (string) $prose );
	}

	/**
	 * Whether the prose carries enough links to hold the row for a human.
	 *
	 * Holds, never refuses: a wrongly refused application is an applicant who never applies
	 * again and nobody ever knowing.
	 *
	 * @param string $prose Every free-text answer, joined.
	 * @param int    $max   The link count at which a row is held.
	 * @return bool
	 */
	public static function too_many_links( $prose, $max = self::MAX_LINKS ) {
		return self::links_in( $prose ) >= (int) $max;
	}

	/*
	 * 7. The mail ceiling
	 * --------------------------------------------------------------------
	 */

	/**
	 * Claim one of the day's acknowledgements.
	 *
	 * Claimed before the send, not after: a claim that fails leaves the row exactly as it is and
	 * the caller records why, so the queue shows a manager that this applicant is waiting on a
	 * message the site declined to send.
	 *
	 * @param string $key   The form's own mail key, e.g. `apply-mail`.
	 * @param int    $limit Messages a day admits.
	 * @return bool Whether the claim was counted.
	 */
	public static function claim_mail( $key, $limit = self::MAIL_PER_DAY ) {
		return WPCPM_Ceiling::claim( (string) $key, (int) $limit, DAY_IN_SECONDS );
	}

	/*
	 * The helpers
	 * --------------------------------------------------------------------
	 */

	/**
	 * An address as it is kept on the consent record: recognizable, not identifying.
	 *
	 * The last octet of an IPv4 address goes, and everything after the first four groups of an
	 * IPv6 one. Enough to say two applications came from the same building; not enough to be a
	 * record of where somebody was.
	 *
	 * @param string $ip The address.
	 * @return string
	 */
	public static function truncate_ip( $ip ) {
		$ip = trim( (string) $ip );

		if ( '' === $ip ) {
			return '';
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts    = explode( '.', $ip );
			$parts[3] = '0';

			return implode( '.', $parts );
		}

		$groups = explode( ':', $ip );

		return implode( ':', array_slice( $groups, 0, 4 ) ) . '::';
	}

	/**
	 * The submitting browser's description, truncated, for the consent record.
	 *
	 * @return string
	 */
	public static function user_agent() {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}

		return mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 200 );
	}
}
