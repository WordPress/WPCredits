<?php
/**
 * Turning ranked handbook passages into an answer.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Answers a question by having the provider search the WordPress documentation.
 *
 * **One layer, deliberately, and with a floor removed.** An earlier version kept a local copy
 * of every page and quoted it, which meant an answer was always available — with no provider,
 * with a wrong key, with the provider down, out of quota. That copy was a table, a resumable
 * sync, two cron hooks and a progress bar, and it was traded away for this: the provider does
 * its own searching and there is nothing to keep in step.
 *
 * What went with it is the guarantee. The restriction to wordpress.org now exists only because
 * the instructions ask for it, so two things compensate: the sites are named in the system
 * message and repeated, and every citation that comes back is checked against them. An answer
 * grounded in nothing recognisable is shown but marked, because an uncited answer from a model
 * that was told to cite is the one most likely to be invented.
 *
 * **Google AI Studio is the only provider.** It is the one that will search the web for free
 * and return the citations this arrangement depends on; an ordinary chat endpoint answers from
 * memory and returns nothing to check. The `wpcpm_handbook_generate` filter is still there for
 * anyone who wants to add another.
 *
 * With no provider configured there is no answer at all, and `ask()` says so rather than
 * returning an empty one.
 */
class WPCPM_Handbook_Answer {

	/** Transient prefix for the per-user hourly counter. */
	const RATE_PREFIX = 'wpcpm_hb_rate_';

	/** Transient for the site-wide daily counter. */
	const RATE_DAY = 'wpcpm_hb_rate_day';

	/** Site-wide ceiling per day, protecting a free tier from one bad afternoon. */
	const DAILY_CAP = 300;

	/**
	 * How long to wait on the provider, in seconds.
	 *
	 * Generous because a grounded request is not one call: the model searches, reads what it
	 * finds, and only then writes. Measured against this site's own key, the same three
	 * questions took 9.4, 18.7 and 24.1 seconds — so the previous 25 was not a margin, it was
	 * a coin toss, and it lost. The failure is a cURL 28 and no answer.
	 *
	 * Below PHP's `default_socket_timeout` of 60 on this host would be pointless; above it,
	 * unreachable.
	 */
	const TIMEOUT = 60;

	/**
	 * How long the last provider attempt took, in seconds.
	 *
	 * Read by the retry above to decide whether there is time for another go.
	 *
	 * @var float
	 */
	private static $last_attempt = 0.0;

	/**
	 * Whether a failure was the provider being busy rather than something being wrong.
	 *
	 * @param WP_Error $error The failure.
	 * @return bool
	 */
	private static function is_busy( WP_Error $error ) {
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;

		return in_array( $status, array( 429, 500, 502, 503, 504 ), true );
	}

	/**
	 * What to tell the reader about a failure.
	 *
	 * Decided on the HTTP status rather than on the words in the message. Matching words was
	 * how "this model is currently experiencing high demand" — a busy model, which succeeds on
	 * the next attempt — came to be reported as "no longer available, change it in settings":
	 * the message contained "model", and that was the whole test.
	 *
	 * @param WP_Error $error The failure.
	 * @return string
	 */
	private static function failure_text( WP_Error $error ) {
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;

		$message = $error->get_error_message();

		// Gone, or misspelled. The only failure that will never fix itself by waiting, and the
		// only one worth sending somebody to the settings for.
		//
		// Recognised by status *and* by a short list of unmistakable phrases, because Google
		// has answered 404 for one retired model and 400 for another. What is deliberately not
		// used is the bare word "model": that matched "this model is currently experiencing
		// high demand" and told somebody to replace a model that was merely busy.
		$retired = 404 === $status
			|| preg_match( '/no longer available|is not found|not supported|does not exist|unknown name/i', $message );

		if ( $retired ) {
			return __( 'The AI model this site is set to use is not available. A program manager can change it in the plugin settings.', 'wpcredits-program-manager' );
		}

		// Busy, or out of quota for the moment.
		if ( in_array( $status, array( 429, 500, 502, 503, 504 ), true ) ) {
			return __( 'The AI service is busy at the moment. Asking again in a minute usually works.', 'wpcredits-program-manager' );
		}

		if ( 400 === $status || 403 === $status ) {
			return __( 'The AI service refused the request. A program manager may need to check the API key in the plugin settings.', 'wpcredits-program-manager' );
		}

		return __( 'The answer could not be fetched just now. Trying again usually works.', 'wpcredits-program-manager' );
	}

	/**
	 * One answer as HTML.
	 *
	 * Providers answer in Markdown whatever the instructions ask for, and `wpautop` alone
	 * knows nothing about it: the panel printed literal `**During weekly syncs:**` and a
	 * column of asterisks where a list belonged. Only the two constructs that actually turn
	 * up are handled — bold and bullets — because anything more is a Markdown parser, and a
	 * wrong one is worse than none.
	 *
	 * The result still goes through `wp_kses_post()` at every call site: this adds tags to
	 * text that is not trusted, so it cannot be the last thing to touch it.
	 *
	 * @param string $text The provider's answer.
	 * @return string HTML, ready for `wpautop()`.
	 */
	public static function to_html( $text ) {
		$blocks = array();
		$items  = array();

		foreach ( preg_split( '/\R/', (string) $text ) as $line ) {
			// `*` or `-`, and only followed by a space: a line opening on `*Something` is
			// emphasis somebody left unclosed, not a bullet.
			if ( preg_match( '/^\s*[*-]\s+(.+)$/', $line, $match ) ) {
				$items[] = '<li>' . self::inline( $match[1] ) . '</li>';

				continue;
			}

			if ( $items ) {
				$blocks[] = '<ul>' . implode( '', $items ) . '</ul>';
				$items    = array();
			}

			$blocks[] = self::inline( $line );
		}

		if ( $items ) {
			$blocks[] = '<ul>' . implode( '', $items ) . '</ul>';
		}

		// Newlines, not paragraphs: blank lines survive as empty blocks, so `wpautop()`
		// still sees the breaks the provider wrote and splits on them.
		return implode( "\n", $blocks );
	}

	/**
	 * Markdown that can appear mid-sentence.
	 *
	 * @param string $text One line.
	 * @return string
	 */
	private static function inline( $text ) {
		// Non-greedy, and anchored on non-space either side, so `**a** and **b**` is two
		// runs rather than one, and a stray `** ` pair spanning a sentence is left alone.
		return preg_replace( '/\*\*(?=\S)(.+?)(?<=\S)\*\*/', '<strong>$1</strong>', $text );
	}

	/**
	 * The sites an answer is supposed to come from.
	 *
	 * Google's search tool has no site filter, so this cannot be enforced on the way out —
	 * only asked for in the instructions and checked on the way back. That check is the whole
	 * reason this list exists: an answer whose citations are all from somewhere else is not a
	 * WordPress documentation answer, and the reader is told so rather than left to assume.
	 *
	 * @return string[] Hostnames.
	 */
	public static function allowed_hosts() {
		/**
		 * Filter the hosts an answer may be grounded in.
		 *
		 * @param string[] $hosts Hostnames.
		 */
		return (array) apply_filters(
			'wpcpm_handbook_hosts',
			array(
				'wordpress.org',
				'make.wordpress.org',
				'learn.wordpress.org',
				'developer.wordpress.org',
			)
		);
	}

	/**
	 * Whether a URL is on one of those sites.
	 *
	 * Matched on the host's tail, so `developer.wordpress.org` counts for `wordpress.org` and
	 * `notwordpress.org` does not — which a bare `strpos` would wave through.
	 *
	 * @param string $url URL to test.
	 * @return bool
	 */
	public static function is_allowed( $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );

		if ( ! $host ) {
			return false;
		}

		foreach ( self::allowed_hosts() as $allowed ) {
			if ( $host === $allowed || substr( $host, -strlen( '.' . $allowed ) ) === '.' . $allowed ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Answer a question.
	 *
	 * **There is no local copy any more.** The provider searches the web itself and is told
	 * which sites to use; what comes back is its answer plus the pages it read. That removed a
	 * table, a resumable sync, two cron hooks and a progress bar — and it removed the floor
	 * underneath them too. Without a provider configured there is nothing to fall back on, and
	 * this says so plainly rather than returning an empty answer.
	 *
	 * @param string $question What was asked.
	 * @param int    $user_id  Who asked, for rate limiting.
	 * @return array `text`, `sources`, `generated`, `notice`, `unsourced`.
	 */
	public static function ask( $question, $user_id = 0 ) {
		$question = trim( (string) $question );
		$user_id  = $user_id ? (int) $user_id : get_current_user_id();

		$answer = array(
			'question'  => $question,
			'text'      => '',
			'sources'   => array(),
			'generated' => false,
			'notice'    => '',
			'unsourced' => false,
		);

		if ( '' === $question ) {
			$answer['text'] = __( 'Ask a question and it will be looked up in the WordPress documentation.', 'wpcredits-program-manager' );

			return $answer;
		}

		if ( ! self::provider_ready() ) {
			// The honest failure. Nothing here can answer anything without a provider, and
			// pretending otherwise would be worse than saying so.
			$answer['text'] = __( 'No AI provider is configured, so questions cannot be answered yet. A program manager can add one in the plugin settings.', 'wpcredits-program-manager' );

			return $answer;
		}

		$allowed = self::within_limits( $user_id );

		if ( is_wp_error( $allowed ) ) {
			$answer['text']   = __( 'Too many questions have been asked recently. Try again in a little while.', 'wpcredits-program-manager' );
			$answer['notice'] = $allowed->get_error_message();

			return $answer;
		}

		$generated = self::generate( $question );

		if ( is_wp_error( $generated ) ) {
			$answer['text']   = self::failure_text( $generated );
			$answer['notice'] = $generated->get_error_message();

			return $answer;
		}

		self::count_use( $user_id );

		$answer['text']      = $generated['text'];
		$answer['sources']   = $generated['sources'];
		$answer['generated'] = true;

		// Grounded in nothing we recognise. The answer still shows — the model may simply not
		// have cited anything — but it is marked, because an uncited answer from a model that
		// was asked to cite is the one most likely to be invented.
		$answer['unsourced'] = empty( $generated['sources'] );

		return $answer;
	}

	/*
	 * Providers
	 * --------------------------------------------------------------------
	 */

	/**
	 * The providers this plugin can talk to.
	 *
	 * @return array<string, string> Slug => label.
	 */
	public static function providers() {
		/**
		 * Filter the available answer providers.
		 *
		 * A provider added here must also hook `wpcpm_handbook_generate` to do the work.
		 *
		 * @param array<string, string> $providers Slug => label.
		 */
		return (array) apply_filters(
			'wpcpm_handbook_providers',
			array(
				''       => __( 'None — questions cannot be answered', 'wpcredits-program-manager' ),
				'gemini' => __( 'Google AI Studio (Gemini)', 'wpcredits-program-manager' ),
			)
		);
	}

	/**
	 * The configured provider's name, for showing on a screen.
	 *
	 * @return string
	 */
	public static function provider_label() {
		$providers = self::providers();
		$provider  = (string) WPCPM_Settings::get_value( 'handbook_provider', '' );

		return isset( $providers[ $provider ] ) ? $providers[ $provider ] : $provider;
	}

	/**
	 * Whether a generative provider is configured and usable.
	 *
	 * @return bool
	 */
	public static function provider_ready() {
		$provider = (string) WPCPM_Settings::get_value( 'handbook_provider', '' );
		$key      = (string) WPCPM_Settings::get_value( 'handbook_key', '' );

		return '' !== $provider && '' !== $key;
	}

	/**
	 * Ask the configured provider to write the answer.
	 *
	 * @param string $question What was asked.
	 * @return array|WP_Error Answer text and its sources, or the reason there is neither.
	 */
	public static function generate( $question ) {
		$provider = (string) WPCPM_Settings::get_value( 'handbook_provider', '' );

		/**
		 * Filter the generated answer, to add a provider of your own.
		 *
		 * Return an array with `text` and `sources`, or a `WP_Error`.
		 * Return null to let the built-in providers handle it.
		 *
		 * @param string|WP_Error|null $answer   The answer so far.
		 * @param string               $question The question.
		 * @param array                $passages Retrieved passages.
		 * @param string               $provider Configured provider slug.
		 */
		$answer = apply_filters( 'wpcpm_handbook_generate', null, $question, $provider );

		if ( null !== $answer ) {
			return is_string( $answer ) || is_wp_error( $answer )
				? $answer
				: new WP_Error( 'wpcpm_handbook_provider', __( 'The provider returned something unusable.', 'wpcredits-program-manager' ) );
		}

		if ( 'gemini' === $provider ) {
			$answer = self::gemini( $question );

			// One retry, and only when the provider said it was busy rather than that anything
			// was wrong. "High demand" is transient by definition, and it fails in a second or
			// two — so there is room to try again inside the same request, which is better than
			// telling somebody to press the button themselves.
			//
			// Guarded on how long the first attempt took: after a slow failure there is no
			// budget left, and a second attempt would only trip the browser's own ceiling.
			if ( is_wp_error( $answer ) && self::is_busy( $answer ) && self::$last_attempt < 15 ) {
				sleep( 2 );

				$answer = self::gemini( $question );
			}

			return $answer;
		}

		return new WP_Error( 'wpcpm_handbook_provider', __( 'No provider is configured.', 'wpcredits-program-manager' ) );
	}

	/**
	 * The rules, as a system message.
	 *
	 * These carry more weight than they used to. With a local copy there was a hard floor: the
	 * model was handed the only text it was allowed to use. Searching the web, the restriction
	 * to wordpress.org exists only because it is asked for here — so it is asked for plainly,
	 * repeated, and checked afterwards against the citations that come back.
	 *
	 * @return string
	 */
	public static function instructions() {
		return implode(
			"\n",
			array(
				'You are answering a question for a mentor or program manager on the WordPress Credits Program.',
				'',
				'Rules:',
				'- Search for and use ONLY these sites: ' . implode( ', ', self::allowed_hosts() ) . '.',
				'- Ignore blogs, forums, tutorials and agency articles even when they look authoritative. If the answer is not on those sites, say so.',
				'- Prefer the WordPress Education Handbook at make.wordpress.org/community/handbook/education/ for anything about the Credits Program itself.',
				'- Say plainly when you are unsure or when the documentation does not cover the question. Do not fill the gap from memory.',
				'- Be brief: a short paragraph, or a short list if there are steps.',
				'- Write in plain English, in the second person, without a preamble.',
			)
		);
	}

	/**
	 * Ask Google AI Studio, with search grounding.
	 *
	 * @param string $question What was asked.
	 * @return array|WP_Error `text` and `sources`.
	 */
	private static function gemini( $question ) {
		$key   = (string) WPCPM_Settings::get_value( 'handbook_key', '' );
		$model = (string) WPCPM_Settings::get_value( 'handbook_model', 'gemini-2.5-flash' );

		if ( '' === $key ) {
			return new WP_Error( 'wpcpm_handbook_key', __( 'no API key is set', 'wpcredits-program-manager' ) );
		}

		$started = microtime( true );

		$response = wp_remote_post(
			sprintf( 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent', rawurlencode( $model ) ),
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Content-Type'   => 'application/json',
					// In a header rather than the query string: a key in a URL ends up in
					// access logs, proxies and error reports.
					'x-goog-api-key' => $key,
				),
				'body'    => wp_json_encode(
					array(
						'systemInstruction' => array(
							'parts' => array( array( 'text' => self::instructions() ) ),
						),
						'contents'          => array(
							array( 'parts' => array( array( 'text' => $question ) ) ),
						),
						// The whole point of this arrangement. Without it the model answers
						// from whatever it remembers about WordPress, which for a program that
						// changed its onboarding last month is worse than no answer.
						'tools'             => array( array( 'google_search' => new stdClass() ) ),
						'generationConfig'  => array(
							'temperature'     => 0.2,
							// Generous on purpose. A grounded request spends tokens searching
							// and reasoning before it writes a word, and a response cut off by
							// this ceiling comes back with `finishReason: MAX_TOKENS` and its
							// `groundingMetadata` **empty** — so a low limit does not shorten
							// the answer, it silently destroys every citation and marks the
							// result unverified. 800 was doing exactly that.
							'maxOutputTokens' => 2048,
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::$last_attempt = microtime( true ) - $started;

			return $response;
		}

		self::$last_attempt = microtime( true ) - $started;

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$message = isset( $body['error']['message'] ) ? (string) $body['error']['message'] : '';

			// The status travels with the error. Guessing from the message text is how "this
			// model is currently experiencing high demand" came to be reported as "the model is
			// no longer available" — a busy model told to be replaced.
			return new WP_Error(
				'wpcpm_handbook_http',
				'' !== $message
					? sanitize_text_field( $message )
					: sprintf(
						/* translators: %d: HTTP status code. */
						__( 'HTTP %d', 'wpcredits-program-manager' ),
						$code
					),
				array( 'status' => $code )
			);
		}

		$text = '';

		foreach ( (array) ( $body['candidates'][0]['content']['parts'] ?? array() ) as $part ) {
			if ( isset( $part['text'] ) ) {
				$text .= (string) $part['text'];
			}
		}

		if ( '' === trim( $text ) ) {
			// A blocked or truncated response comes back 200 with no text, which would
			// otherwise present as a successful empty answer.
			return new WP_Error( 'wpcpm_handbook_empty', __( 'the provider returned nothing', 'wpcredits-program-manager' ) );
		}

		return array(
			'text'    => trim( $text ),
			'sources' => self::grounding( $body['candidates'][0]['groundingMetadata'] ?? array() ),
		);
	}

	/**
	 * The pages a grounded answer actually read, filtered to the sites we asked for.
	 *
	 * Google returns its own redirect URLs for grounding chunks, so the host to judge is the
	 * one in the chunk's title-ish `domain` where it is given, and the URI otherwise. Anything
	 * that cannot be placed on an allowed site is dropped rather than shown — a citation the
	 * reader would follow to somebody's blog is worse than no citation, because it looks
	 * like corroboration.
	 *
	 * @param array $metadata Gemini's `groundingMetadata`.
	 * @return array List of `title`, `link`.
	 */
	private static function grounding( array $metadata ) {
		$sources = array();
		$seen    = array();

		foreach ( (array) ( $metadata['groundingChunks'] ?? array() ) as $chunk ) {
			$web = isset( $chunk['web'] ) ? (array) $chunk['web'] : array();

			if ( empty( $web['uri'] ) ) {
				continue;
			}

			// Resolved to the page it actually points at, which serves two purposes: the
			// reader gets a real address instead of an opaque Google redirect, and the host
			// check is made against where the page *is* rather than against a label Google
			// supplied. The second is the stronger check of the two.
			$resolved = self::resolve( (string) $web['uri'] );
			$host     = '' !== $resolved ? wp_parse_url( $resolved, PHP_URL_HOST ) : self::chunk_host( $web );

			if ( ! $host || ! self::is_allowed( 'https://' . $host ) ) {
				continue;
			}

			$link = '' !== $resolved ? $resolved : (string) $web['uri'];

			// Keyed on the resolved address, so the same page cited several times appears once
			// even though each citation arrives with its own redirect.
			if ( isset( $seen[ $link ] ) ) {
				continue;
			}

			$seen[ $link ] = true;

			$sources[] = array(
				'title'   => self::label( $resolved, $host ),
				'link'    => $link,
				// Shown under the link. The question was for the actual addresses, and a
				// handbook page four levels deep is only identifiable from its path.
				'extract' => '' !== $resolved ? preg_replace( '#^https?://#', '', untrailingslashit( $resolved ) ) : '',
			);
		}

		return $sources;
	}

	/**
	 * The real address behind one of Google's grounding redirects.
	 *
	 * The `uri` on a grounding chunk is a `vertexaisearch.cloud.google.com` redirect that says
	 * nothing about where it goes. One request with redirects switched off returns a 302 whose
	 * `Location` is the page itself — measured at about 0.2 seconds each against this site.
	 *
	 * Not cached: each citation arrives with a freshly minted redirect, so a cache keyed on it
	 * would never be hit and would only add machinery.
	 *
	 * @param string $redirect The redirect URL.
	 * @return string The resolved URL, or an empty string.
	 */
	private static function resolve( $redirect ) {
		// Anything that is not one of Google's redirects is already the real thing.
		if ( false === strpos( $redirect, 'grounding-api-redirect' ) ) {
			return '';
		}

		$response = wp_remote_head(
			$redirect,
			array(
				// Short, and deliberately so: this runs once per citation on top of an answer
				// that has already taken twenty seconds. A slow redirect is not worth waiting
				// for when the fallback is the host on its own.
				'timeout'     => 6,
				'redirection' => 0,
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$location = wp_remote_retrieve_header( $response, 'location' );

		if ( ! $location || ! is_string( $location ) ) {
			return '';
		}

		return esc_url_raw( $location );
	}

	/**
	 * A readable name for a page, from its address.
	 *
	 * Google gives no page title — only the domain — so the last meaningful part of the path
	 * is used instead. "certificate-graduation" reads better than a bare hostname repeated
	 * four times, which is what the list looked like before.
	 *
	 * @param string $url  Resolved URL, possibly empty.
	 * @param string $host Hostname.
	 * @return string
	 */
	private static function label( $url, $host ) {
		$path = '' !== $url ? (string) wp_parse_url( $url, PHP_URL_PATH ) : '';
		$slug = '';

		foreach ( array_reverse( array_filter( explode( '/', $path ) ) ) as $segment ) {
			// Skip a trailing numeral, as in `/lesson/get-your-certificate/2/`.
			if ( ! is_numeric( $segment ) ) {
				$slug = $segment;
				break;
			}
		}

		if ( '' === $slug ) {
			return $host;
		}

		$name = ucfirst( str_replace( '-', ' ', $slug ) );

		return sprintf(
			/* translators: 1: page name, 2: hostname. */
			_x( '%1$s — %2$s', 'handbook citation', 'wpcredits-program-manager' ),
			$name,
			$host
		);
	}

	/**
	 * The host a grounding chunk actually came from.
	 *
	 * Google returns its own `vertexaisearch.cloud.google.com` redirect as the `uri`, so the
	 * URI's host says nothing about where the page lives — judging it would refuse every
	 * citation ever returned.
	 *
	 * What it does return is the source's domain, and **in `title`** rather than in the
	 * `domain` field the documentation describes. This reads `domain` first in case that
	 * changes back, then falls back to `title` when it looks like a hostname: no spaces and at
	 * least one dot. A real page title fails that test, which is the safe way round — an
	 * unplaceable citation is dropped rather than trusted.
	 *
	 * @param array $web One chunk's `web` object.
	 * @return string Hostname, or an empty string.
	 */
	private static function chunk_host( array $web ) {
		$domain = isset( $web['domain'] ) ? trim( (string) $web['domain'] ) : '';

		if ( '' !== $domain ) {
			return $domain;
		}

		$title = isset( $web['title'] ) ? trim( (string) $web['title'] ) : '';

		if ( '' === $title || false !== strpos( $title, ' ' ) || false === strpos( $title, '.' ) ) {
			return '';
		}

		return $title;
	}


	/*
	 * Rate limiting
	 * --------------------------------------------------------------------
	 */

	/**
	 * Whether this person may make another generated request.
	 *
	 * Two ceilings. The per-person one stops one enthusiastic afternoon exhausting the quota
	 * for everybody; the site-wide one stops the free tier being spent in an hour by however
	 * many people are having that afternoon at once. Past either one the reader is told to
	 * come back shortly, which is all there is to offer now that there is no local copy to
	 * quote from.
	 *
	 * @param int $user_id Who is asking.
	 * @return true|WP_Error
	 */
	public static function within_limits( $user_id ) {
		$per_user = (int) WPCPM_Settings::get_value( 'handbook_limit', 20 );

		if ( $per_user > 0 ) {
			$used = (int) get_transient( self::RATE_PREFIX . (int) $user_id );

			if ( $used >= $per_user ) {
				return new WP_Error(
					'wpcpm_handbook_rate',
					__( 'you have asked a lot of questions in the last hour', 'wpcredits-program-manager' )
				);
			}
		}

		if ( (int) get_transient( self::RATE_DAY ) >= self::DAILY_CAP ) {
			return new WP_Error(
				'wpcpm_handbook_rate_day',
				__( 'the assistant has reached its daily limit', 'wpcredits-program-manager' )
			);
		}

		return true;
	}

	/**
	 * Record one generated answer against both ceilings.
	 *
	 * @param int $user_id Who asked.
	 */
	private static function count_use( $user_id ) {
		$key  = self::RATE_PREFIX . (int) $user_id;
		$used = (int) get_transient( $key );

		set_transient( $key, $used + 1, HOUR_IN_SECONDS );

		$today = (int) get_transient( self::RATE_DAY );

		set_transient( self::RATE_DAY, $today + 1, DAY_IN_SECONDS );
	}

	/**
	 * Forget every counter. Called on uninstall.
	 */
	public static function clear_limits() {
		delete_transient( self::RATE_DAY );
	}
}
