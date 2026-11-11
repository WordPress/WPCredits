<?php
/**
 * The language a signed-in person reads the site in: the one their own profile keeps.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A student's and a mentor's pages in their own language (1.123.73).
 *
 * WordPress keeps a language with every account and reads wp-admin in it, but draws the site's own
 * pages in the site's language whoever is looking. Students and mentors are never in wp-admin: their
 * pages are the Student Report Card and the Mentor Report Card. So two things were missing, and this
 * class is both.
 *
 * **The pages follow the account.** `follow()` answers WordPress's question of which language a
 * request is in (`pre_determine_locale`) with the signed-in person's own, on the site's pages as
 * wp-admin already does. Everything that is loaded after it is in that language with no second
 * step: this plugin's sentences, the theme's and WordPress's own. The emails already followed the
 * account (`WPCPM_Mail`), so a person's choice is one choice.
 *
 * **A place to choose.** `picker()` is a small switcher at the top of the two pages: the flag and
 * the name of the language being read, which opens a short list (the owner, 11 November 2026: "a
 * subtle language switcher flag", not a form with a list and a button). Pressing a language keeps
 * it where WordPress keeps it, the account's own `locale`, and the page comes back in it. It is a
 * `details` element holding a form of buttons, so it opens, closes and saves with no script. It is
 * about the reader's own account, never the one being looked at, as the two-factor prompt is.
 *
 * Only a language this plugin has a translation for is offered, and only such a language is
 * followed: the catalogs in `languages/` are the list. A language whose catalog a release took out
 * (it fell under the mark the download step sets) goes back to the site's language by itself, so
 * nobody is left on a page that is half English because of a choice they made months ago. With no
 * catalog at all, the form is not printed and nothing changes.
 *
 * The sentences a catalog does not hold stay in English: translators are given what students and
 * mentors read (`bin/translators-pot.php`), not the whole plugin.
 */
final class WPCPM_Language {

	/** The form's action, and its nonce's. */
	const ACTION = 'wpcpm_language';

	/** The posted field: a locale, or '' for the site's language. */
	const FIELD = 'wpcpm_language';

	/** Where WordPress keeps an account's language. */
	const META = 'locale';

	/** The switcher's id. */
	const ANCHOR = 'wpcpm-language';

	/**
	 * The country whose flag stands for a language WordPress names without one (`ja`). Only where
	 * one country is beyond argument; any other language is listed by its name alone.
	 */
	const COUNTRIES = array(
		'el' => 'GR',
		'et' => 'EE',
		'fi' => 'FI',
		'hr' => 'HR',
		'id' => 'ID',
		'ja' => 'JP',
		'lo' => 'LA',
		'lv' => 'LV',
		'sq' => 'AL',
		'th' => 'TH',
		'uk' => 'UA',
		'vi' => 'VN',
	);

	/** The one element a flag is printed in, and its two attributes. */
	const FLAG_TAGS = array(
		'span' => array(
			'class'       => true,
			'aria-hidden' => true,
		),
	);

	/**
	 * The locales a catalog was found for, read once a request.
	 *
	 * @var string[]|null
	 */
	private static $catalogs = null;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'pre_determine_locale', array( __CLASS__, 'follow' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * The language of a request on the site's pages: the signed-in person's own, when it is one
	 * this plugin is translated into.
	 *
	 * WordPress already reads the account in wp-admin, so wp-admin is left to it. So are the
	 * command line and the scheduled jobs, which have nobody signed in.
	 *
	 * @param string|null $locale What an earlier filter decided, or null.
	 * @return string|null
	 */
	public static function follow( $locale ) {
		if ( null !== $locale && '' !== $locale ) {
			return $locale;
		}

		// Asked before the plugins have loaded, WordPress cannot say who is signed in yet.
		if ( ! did_action( 'plugins_loaded' ) || ! function_exists( 'is_user_logged_in' ) ) {
			return $locale;
		}

		if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || ! is_user_logged_in() ) {
			return $locale;
		}

		$own = self::chosen( get_current_user_id() );

		return '' !== $own ? $own : $locale;
	}

	/**
	 * The language an account chose, when it is still one that is offered; '' for the site's.
	 *
	 * @param int $user_id The account.
	 * @return string
	 */
	public static function chosen( $user_id ) {
		$own = (string) get_user_meta( (int) $user_id, self::META, true );

		return '' !== $own && in_array( $own, self::catalogs(), true ) ? $own : '';
	}

	/**
	 * The locales this plugin has a translation for: the catalogs in its languages folder.
	 *
	 * @return string[]
	 */
	public static function catalogs() {
		if ( null === self::$catalogs ) {
			self::$catalogs = self::locales_of( (array) glob( WPCPM_PLUGIN_DIR . 'languages/wpcredits-program-manager-*.mo' ) );
		}

		return self::$catalogs;
	}

	/**
	 * The locales a list of catalog paths names, sorted, each once.
	 *
	 * @param string[] $paths Paths or file names.
	 * @return string[]
	 */
	public static function locales_of( array $paths ) {
		$locales = array();

		foreach ( $paths as $path ) {
			if ( preg_match( '/^wpcredits-program-manager-([A-Za-z0-9_]+)\.mo$/', basename( (string) $path ), $m ) && 'en_US' !== $m[1] ) {
				$locales[ $m[1] ] = true;
			}
		}

		$locales = array_keys( $locales );
		sort( $locales );

		return $locales;
	}

	/**
	 * A language's name in that language, as a reader looking for theirs would know it.
	 *
	 * PHP's own list of languages (the intl extension) when the server has it, and the locale's code
	 * when it has not or does not know the language: a code is still a choice a person can make.
	 * The country is added only when asked for, which the form does when two of its languages would
	 * otherwise read the same (Portuguese of Brazil and of Portugal).
	 *
	 * @param string $locale  A WordPress locale, `pl_PL`.
	 * @param bool   $country Whether to say the country as well.
	 * @return string
	 */
	public static function name( $locale, $country = false ) {
		$locale = (string) $locale;
		$name   = '';

		if ( class_exists( 'Locale' ) ) {
			// A formal or informal variant (`de_DE_formal`) is the same language to PHP.
			$tag  = (string) preg_replace( '/_(formal|informal)$/', '', $locale );
			$name = (string) ( $country ? Locale::getDisplayName( $tag, $tag ) : Locale::getDisplayLanguage( $tag, $tag ) );

			// PHP answers an unknown language with the code it was given.
			if ( in_array( strtolower( $name ), array( strtolower( $tag ), self::language_of( $locale ) ), true ) ) {
				$name = '';
			}
		}

		if ( '' === $name ) {
			return $locale;
		}

		return function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $name, 0, 1 ) ) . mb_substr( $name, 1 ) : ucfirst( $name );
	}

	/**
	 * The language part of a locale: `pt` of `pt_BR`.
	 *
	 * @param string $locale A WordPress locale.
	 * @return string
	 */
	public static function language_of( $locale ) {
		return strtolower( (string) strtok( (string) $locale, '_' ) );
	}

	/**
	 * The flag of a locale's country, as the two letters every system draws as a flag or, where it
	 * draws none (Windows), prints as the country's code. '' for a language with no one country.
	 *
	 * @param string $locale A WordPress locale.
	 * @return string
	 */
	public static function flag( $locale ) {
		$country = '';

		if ( preg_match( '/^[a-z]{2,3}_([A-Z]{2})(_|$)/', (string) $locale, $m ) ) {
			$country = $m[1];
		} elseif ( isset( self::COUNTRIES[ (string) $locale ] ) ) {
			$country = self::COUNTRIES[ (string) $locale ];
		}

		if ( '' === $country ) {
			return '';
		}

		// A flag is its country's two letters, each as a regional indicator: U+1F1E6 is A.
		$flag = '';

		foreach ( str_split( $country ) as $letter ) {
			$flag .= '&#' . ( 0x1F1E6 + ord( $letter ) - ord( 'A' ) ) . ';';
		}

		return html_entity_decode( $flag, ENT_NOQUOTES, 'UTF-8' );
	}

	/**
	 * What the form lists: the site's own language first, as '', then each catalog, every one
	 * named in itself.
	 *
	 * @param string   $site     The site's locale.
	 * @param string[] $catalogs The locales offered.
	 * @return array<string,array{name:string,lang:string,flag:string}> Keyed by the value the form posts.
	 */
	public static function choices( $site, array $catalogs ) {
		$locales = array_merge( array( (string) $site ), array_values( array_diff( $catalogs, array( (string) $site ) ) ) );
		$shared  = array_count_values( array_map( array( __CLASS__, 'language_of' ), $locales ) );
		$choices = array();

		foreach ( $locales as $index => $locale ) {
			$choices[ 0 === $index ? '' : $locale ] = array(
				'name' => self::name( $locale, $shared[ self::language_of( $locale ) ] > 1 ),
				// What a screen reader is told the name is written in.
				'lang' => str_replace( '_', '-', (string) preg_replace( '/_(formal|informal)$/', '', $locale ) ),
				'flag' => self::flag( $locale ),
			);
		}

		return $choices;
	}

	/**
	 * The switcher a person chooses their language on, printed at the top of their own page.
	 *
	 * Nothing is printed when there is no translation to choose, or to a reader who is not looking
	 * at their own page (an Administrator reading a student's): the choice is the account's own.
	 *
	 * @param WP_User|int|null $user The reader.
	 */
	public static function picker( $user ) {
		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() || ! self::catalogs() || current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		$chosen  = self::chosen( $user->ID );
		$site    = function_exists( 'get_locale' ) ? (string) get_locale() : 'en_US';
		$choices = self::choices( $site, self::catalogs() );
		$current = isset( $choices[ $chosen ] ) ? $choices[ $chosen ] : $choices[''];

		echo '<details class="wpcpm-language" id="' . esc_attr( self::ANCHOR ) . '">';

		// What is being read, small: the flag, then the name in its own language. "Language" is said
		// to a screen reader, which is told nothing by a flag.
		printf(
			'<summary class="wpcpm-language__current"><span class="screen-reader-text">%1$s</span>%2$s<span class="wpcpm-language__name" lang="%3$s">%4$s</span></summary>',
			esc_html__( 'Language:', 'wpcredits-program-manager' ),
			wp_kses( self::flag_markup( $current['flag'] ), self::FLAG_TAGS ),
			esc_attr( $current['lang'] ),
			esc_html( $current['name'] )
		);

		echo '<form class="wpcpm-language__form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		wp_nonce_field( self::ACTION, '_wpnonce', true, true );
		echo '<ul class="wpcpm-language__list">';

		foreach ( $choices as $value => $choice ) {
			printf(
				'<li><button type="submit" class="wpcpm-language__choice" name="%1$s" value="%2$s" lang="%3$s"%4$s>%5$s<span class="wpcpm-language__name">%6$s</span></button></li>',
				esc_attr( self::FIELD ),
				esc_attr( $value ),
				esc_attr( $choice['lang'] ),
				(string) $value === $chosen ? ' aria-current="true"' : '',
				wp_kses( self::flag_markup( $choice['flag'] ), self::FLAG_TAGS ),
				esc_html( $choice['name'] )
			);
		}

		echo '</ul>';
		echo '</form>';
		echo '</details>';
	}

	/**
	 * A flag as the page prints it: hidden from a screen reader, which reads the name beside it.
	 *
	 * @param string $flag What `flag()` answered.
	 * @return string Escaped markup, or '' with no flag.
	 */
	private static function flag_markup( $flag ) {
		return '' === $flag ? '' : '<span class="wpcpm-language__flag" aria-hidden="true">' . esc_html( $flag ) . '</span>';
	}

	/**
	 * Keep the language a person chose.
	 */
	public static function handle() {
		check_admin_referer( self::ACTION );

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Sign in to choose your language.', 'wpcredits-program-manager' ), '', array( 'response' => 403 ) );
		}

		$user_id = get_current_user_id();
		$asked   = WPCPM_Request::posted_exact( self::FIELD );

		// Only what the list offered is kept; anything else leaves the account as it was.
		if ( '' === $asked ) {
			delete_user_meta( $user_id, self::META );
		} elseif ( in_array( $asked, self::catalogs(), true ) ) {
			update_user_meta( $user_id, self::META, $asked );
		}

		// Back to the page, at its top, where the switcher is: the page in the language chosen is
		// what says it was kept.
		wp_safe_redirect( (string) strtok( WPCPM_Request::back( home_url( '/' ) ), '#' ) );
		exit;
	}
}
