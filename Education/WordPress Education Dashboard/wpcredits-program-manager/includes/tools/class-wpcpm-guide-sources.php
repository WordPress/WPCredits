<?php
/**
 * Which pages feed Need help?'s index, who may read each one, and every trigger that reindexes.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The sources of the guide index (`WPCPM_Guide_Index`): the site's five guides and its FAQ, found by
 * path, and the WordPress Credits handbook, read daily from make.wordpress.org.
 *
 * Two rules here are the security boundary of the whole tool.
 *
 * - **A passage never reaches somebody its page would refuse.** What a passage's `audience` is follows
 *   its page's path (`pages()`); who reads that audience is the page's own access level, asked of
 *   `WPCPM_Content_Access::can_view()` when the question is asked (`audiences_for()`), never when the
 *   page was indexed. A page whose level changes is read by its new audience at the next question. A
 *   page of audience `public`, which everybody reads, is asked the same of itself at each question
 *   (`excluded_for()`), so one gated before its passages leave the index is given to nobody it refuses.
 * - **Only a public page is written.** A page that is not published, or carries a password, is not
 *   indexed and is taken out of the index when it becomes one.
 *
 * Every write runs under one lock (`LOCK`), so a save, a deletion, the daily run, a rebuild and the
 * command line never write the same source at once.
 */
final class WPCPM_Guide_Sources {

	const CRON_DAILY   = 'wpcpm_guide_daily';
	const CRON_REBUILD = 'wpcpm_guide_rebuild';
	const OPT_HANDBOOK = 'wpcpm_guide_handbook_state';
	const LOCK         = 'wpcpm_guide_index_lock';
	const LOCK_TTL     = 300;

	/** The handbook's REST route, and the one request the sync makes of it. */
	const HANDBOOK_ROUTE = 'https://make.wordpress.org/community/wp-json/wp/v2/education-handbook';

	/** The handbook's host and the path its kept pages sit under. */
	const HANDBOOK_HOST  = 'make.wordpress.org';
	const HANDBOOK_UNDER = '/community/handbook/education/credits/';

	/** Pages under these paths are not read: they describe steps being retired. */
	const HANDBOOK_SKIP = array( '/community/handbook/education/credits/program-manager-guide/' );

	/** How long the handbook request waits, in seconds. */
	const HANDBOOK_TIMEOUT = 20;

	/** The audiences a passage can carry. */
	const AUDIENCES = array( 'student', 'mentor', 'institution', 'sponsor', 'administrator', 'public' );

	/**
	 * Hook everything up. Called from `wpcpm_bootstrap()`, not by the tool, so the ability and the CLI
	 * find the index whether or not Need help? is switched on.
	 */
	public static function init(): void {
		add_action( 'init', array( 'WPCPM_Guide_Index', 'maybe_upgrade' ), 5 );
		add_action( 'init', array( __CLASS__, 'maybe_schedule' ), 20 );
		add_action( 'wp_after_insert_post', array( __CLASS__, 'on_save' ), 10, 4 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete' ) );
		add_action( self::CRON_DAILY, array( __CLASS__, 'daily' ) );
		add_action( self::CRON_REBUILD, array( __CLASS__, 'run_rebuild' ) );
	}

	/**
	 * The pages the index reads, by path: each one's audience and kind.
	 *
	 * @return array<string, array{audience: string, kind: string}>
	 */
	public static function pages(): array {
		$pages = array(
			'student-guide'       => array(
				'audience' => 'student',
				'kind'     => 'guide',
			),
			'mentor-guide'        => array(
				'audience' => 'mentor',
				'kind'     => 'guide',
			),
			'institution-guide'   => array(
				'audience' => 'institution',
				'kind'     => 'guide',
			),
			'sponsor-guide'       => array(
				'audience' => 'sponsor',
				'kind'     => 'guide',
			),
			'administrator-guide' => array(
				'audience' => 'administrator',
				'kind'     => 'guide',
			),
			'faq'                 => array(
				'audience' => 'public',
				'kind'     => 'faq',
			),
		);

		/**
		 * Filter the pages the guide index reads.
		 *
		 * @param array $pages Path => array( 'audience' => one of the six, 'kind' => 'guide' or 'faq' ).
		 */
		$filtered = apply_filters( 'wpcpm_guide_pages', $pages );
		$out      = array();

		foreach ( is_array( $filtered ) ? $filtered : array() as $path => $spec ) {
			$path = trim( (string) $path, '/' );

			if ( '' === $path || ! is_array( $spec ) || ! isset( $spec['audience'], $spec['kind'] ) ) {
				continue;
			}

			if ( ! in_array( $spec['audience'], self::AUDIENCES, true ) || ! in_array( $spec['kind'], array( 'guide', 'faq' ), true ) ) {
				continue;
			}

			$out[ $path ] = array(
				'audience' => $spec['audience'],
				'kind'     => $spec['kind'],
			);
		}

		return $out;
	}

	/**
	 * The audiences an asker reads: `public` always, and each of the five whose page the asker may read,
	 * as `WPCPM_Content_Access::can_view()` answers now. An audience whose page does not exist, is not
	 * published, or is refused to the asker is not read; where a filter lists two pages for one audience,
	 * the asker must read both.
	 *
	 * @param int|WP_User|null $user The asker; the current user when null.
	 * @return string[]
	 */
	public static function audiences_for( $user = null ): array {
		$reads  = array( 'public' );
		$denied = array();
		$seen   = array();

		foreach ( self::pages() as $path => $spec ) {
			$audience = $spec['audience'];

			if ( 'public' === $audience ) {
				continue;
			}

			$page = self::published_page( $path );

			if ( null === $page || ! WPCPM_Content_Access::can_view( $page, $user ) ) {
				$denied[ $audience ] = true;
				continue;
			}

			$seen[ $audience ] = true;
		}

		foreach ( self::AUDIENCES as $audience ) {
			if ( isset( $seen[ $audience ] ) && ! isset( $denied[ $audience ] ) ) {
				$reads[] = $audience;
			}
		}

		return $reads;
	}

	/**
	 * The site pages of the index this asker may not be given now, by source key: each `page:` source of
	 * audience `public` (the FAQ, or a page a filter adds as public) whose page is gone, is not published,
	 * carries a password, or is refused to the asker by `WPCPM_Content_Access::can_view()`, as
	 * `audiences_for()` asks the guides. `WPCPM_Guide_Search::rank()` leaves their passages out.
	 *
	 * Every asker reads `public`, so the audience alone would hand such a page's passages to everybody until
	 * the page is next written: a save while another run holds the index defers the removal (`on_save()`),
	 * and a level changed by its meta alone waits for the daily sweep. Asked here, at each question, the
	 * page's own access applies at once. The sources are the passages' table's and the state's together,
	 * so neither alone decides what is checked. The handbook (`handbook:`) is read by everybody and is
	 * never in the list.
	 *
	 * @param int|WP_User|null $user The asker; the current user when null.
	 * @return string[] Source keys, sorted.
	 */
	public static function excluded_for( $user = null ): array {
		$sources = WPCPM_Guide_Index::page_sources( 'public' );

		foreach ( WPCPM_Guide_Index::sources() as $source => $entry ) {
			if ( 0 === strpos( (string) $source, 'page:' ) && is_array( $entry ) && isset( $entry['audience'] ) && 'public' === $entry['audience'] ) {
				$sources[] = (string) $source;
			}
		}

		$out = array();

		foreach ( array_unique( $sources ) as $source ) {
			// get_post( 0 ) is the current post, never a page of the index: a key with no ID is left out.
			$id   = (int) substr( $source, 5 );
			$page = $id > 0 ? get_post( $id ) : null;

			if ( ! $page instanceof WP_Post || 'publish' !== $page->post_status || '' !== (string) $page->post_password || ! WPCPM_Content_Access::can_view( $page, $user ) ) {
				$out[] = $source;
			}
		}

		sort( $out );

		return $out;
	}

	/**
	 * The guide an asker's answers favor: the one they asked from when they read it; otherwise the first
	 * of Administrator (the asker manages the program), Sponsor, Institution, Mentor, Student whose
	 * audience they read; otherwise ''.
	 *
	 * @param int|WP_User|null $user  The asker.
	 * @param string           $asked The audience of the dashboard the question was asked from, or ''.
	 * @return string
	 */
	public static function home_for( $user = null, string $asked = '' ): string {
		$reads = self::audiences_for( $user );

		if ( '' !== $asked && 'public' !== $asked && in_array( $asked, self::AUDIENCES, true ) && in_array( $asked, $reads, true ) ) {
			return $asked;
		}

		$account = WPCPM_Roles::resolve_user( $user );
		$manages = $account instanceof WP_User && user_can( $account->ID, WPCPM_Roles::CAP_MANAGE );

		foreach ( array( 'administrator', 'sponsor', 'institution', 'mentor', 'student' ) as $audience ) {
			if ( 'administrator' === $audience && ! $manages ) {
				continue;
			}

			if ( in_array( $audience, $reads, true ) ) {
				return $audience;
			}
		}

		return '';
	}

	/**
	 * Write a page, or take it out. A published page with no password at a listed path is written as
	 * `page:<ID>`; a page in any other state, or at no listed path, is removed.
	 *
	 * @param WP_Post $page The page.
	 * @return int The passages written.
	 */
	public static function index_page( WP_Post $page ): int {
		$source = 'page:' . $page->ID;
		$spec   = self::spec_of( $page );

		if ( null === $spec || ! self::is_open( $page ) ) {
			WPCPM_Guide_Index::remove( $source );
			return 0;
		}

		$link = get_permalink( $page );

		return WPCPM_Guide_Index::write(
			$source,
			array(
				'kind'     => $spec['kind'],
				'audience' => $spec['audience'],
				'post_id'  => (int) $page->ID,
				'title'    => (string) $page->post_title,
				'url'      => is_string( $link ) ? $link : '',
				'modified' => (string) $page->post_modified_gmt,
			),
			WPCPM_Guide_Text::passages( (string) $page->post_content )
		);
	}

	/**
	 * The end of a save (`wp_after_insert_post`): reindex a page at a listed path, or one that was at
	 * one. A revision, an autosave, anything that is not a page, and a page that is at no listed path
	 * and was at none, is nothing.
	 *
	 * @param int          $post_id The post.
	 * @param WP_Post      $post    The post as saved.
	 * @param bool         $update  Whether this updated an existing post.
	 * @param WP_Post|null $before  The post before the save, when it was an update.
	 */
	public static function on_save( $post_id, $post, $update = false, $before = null ): void {
		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( null === self::spec_of( $post ) && ! ( $before instanceof WP_Post && null !== self::spec_of( $before ) ) ) {
			return;
		}

		$token = WPCPM_Lock::take( self::LOCK, self::LOCK_TTL );

		if ( '' === $token ) {
			// A rebuild or the daily run holds the index: it may have passed this page already, so one
			// more rebuild is asked for, shortly.
			if ( ! wp_next_scheduled( self::CRON_REBUILD ) ) {
				wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CRON_REBUILD );
			}
			return;
		}

		try {
			self::index_page( $post );
		} finally {
			WPCPM_Lock::release( self::LOCK, $token );
		}
	}

	/**
	 * A post about to be deleted for good (`before_delete_post`, while it still exists): when the index
	 * holds it as `page:<ID>`, its passages leave at once rather than at the daily run. A post the index
	 * does not hold is nothing, and takes no lock. Under the lock; when another run holds it, one rebuild
	 * is asked for, as a save does, and that rebuild no longer finds the page.
	 *
	 * @param int $post_id The post being deleted.
	 */
	public static function on_delete( $post_id ): void {
		$source = 'page:' . (int) $post_id;

		if ( (int) $post_id <= 0 || ! isset( WPCPM_Guide_Index::sources()[ $source ] ) ) {
			return;
		}

		$token = WPCPM_Lock::take( self::LOCK, self::LOCK_TTL );

		if ( '' === $token ) {
			self::schedule_rebuild();
			return;
		}

		try {
			WPCPM_Guide_Index::remove( $source );
		} finally {
			WPCPM_Lock::release( self::LOCK, $token );
		}
	}

	/**
	 * Whether a handbook page is read: under the Credits handbook on make.wordpress.org, not under a
	 * skipped path. Filterable, as `wpcpm_guide_handbook_keep`.
	 *
	 * @param string $link The page's address.
	 * @return bool
	 */
	public static function handbook_keep( string $link ): bool {
		$parts = parse_url( $link ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Plain PHP, so the suites run it without WordPress.
		$host  = is_array( $parts ) && isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		$path  = is_array( $parts ) && isset( $parts['path'] ) ? (string) $parts['path'] : '';
		$keep  = self::HANDBOOK_HOST === $host && 0 === strpos( $path, self::HANDBOOK_UNDER );

		foreach ( self::HANDBOOK_SKIP as $skip ) {
			if ( 0 === strpos( $path, $skip ) ) {
				$keep = false;
			}
		}

		/**
		 * Filter whether a handbook page is read by the guide index.
		 *
		 * @param bool   $keep Whether it is kept.
		 * @param string $link The page's address.
		 */
		return (bool) apply_filters( 'wpcpm_guide_handbook_keep', $keep, $link );
	}

	/**
	 * Read the handbook and write what moved. Under the lock; `error` says why when the lock is held.
	 *
	 * @param string $from_file A saved answer of the route, for a copy that cannot reach the network.
	 * @return array{ok: bool, pages: int, changed: int, removed: int, error: string, more: bool, malformed: int, failed: int}
	 */
	public static function sync_handbook( string $from_file = '' ): array {
		$token = WPCPM_Lock::take( self::LOCK, self::LOCK_TTL );

		if ( '' === $token ) {
			return array(
				'ok'        => false,
				'pages'     => 0,
				'changed'   => 0,
				'removed'   => 0,
				'error'     => self::busy_sentence(),
				'more'      => false,
				'malformed' => 0,
				'failed'    => 0,
			);
		}

		try {
			return self::run_handbook( $from_file, false );
		} finally {
			WPCPM_Lock::release( self::LOCK, $token );
		}
	}

	/**
	 * Every listed page, then the handbook, then the version noted. One run under the lock.
	 *
	 * A rebuild writes the handbook's pages again whether or not they moved: it is the run that follows
	 * a release, which may have changed how a page is read.
	 *
	 * @param bool $handbook Whether to read the handbook too.
	 * @return array<string, mixed> Per source, the passages written; `busy` => true when the lock is held;
	 *                              `lost` => true when the lock ran out and another run took it.
	 */
	public static function rebuild( bool $handbook = true ): array {
		$token = WPCPM_Lock::take( self::LOCK, self::LOCK_TTL );

		if ( '' === $token ) {
			return array( 'busy' => true );
		}

		$out = array();

		try {
			$keep = array();

			foreach ( array_keys( self::pages() ) as $path ) {
				$page = self::page_at( $path );

				if ( null === $page ) {
					continue;
				}

				$out[ 'page:' . $page->ID ]  = self::index_page( $page );
				$keep[ 'page:' . $page->ID ] = true;

				$token = WPCPM_Lock::extend( self::LOCK, $token, self::LOCK_TTL );

				if ( '' === $token ) {
					$out['lost'] = true;
					return $out;
				}
			}

			self::remove_gone_pages( $keep );

			if ( $handbook ) {
				self::run_handbook( '', true );

				foreach ( WPCPM_Guide_Index::sources() as $source => $entry ) {
					if ( 0 === strpos( (string) $source, 'handbook:' ) ) {
						$out[ $source ] = isset( $entry['passages'] ) ? (int) $entry['passages'] : 0;
					}
				}
			}

			WPCPM_Guide_Index::mark_built( WPCPM_VERSION );
		} finally {
			WPCPM_Lock::release( self::LOCK, $token );
		}

		return $out;
	}

	/**
	 * The rebuild event's action: a full rebuild, its answer dropped (an action returns nothing).
	 */
	public static function run_rebuild(): void {
		self::rebuild( true );
	}

	/**
	 * The daily event: the handbook, then any listed page whose modified time is not the one it was
	 * indexed at (or that failed to write), then the pages that are gone.
	 */
	public static function daily(): void {
		$token = WPCPM_Lock::take( self::LOCK, self::LOCK_TTL );

		if ( '' === $token ) {
			return;
		}

		try {
			self::run_handbook( '', false );

			$sources = WPCPM_Guide_Index::sources();
			$keep    = array();

			foreach ( array_keys( self::pages() ) as $path ) {
				$page = self::page_at( $path );

				if ( null === $page ) {
					continue;
				}

				if ( ! self::is_open( $page ) ) {
					// Not published, or protected: it is not kept, so the sweep below takes it out.
					continue;
				}

				$source          = 'page:' . $page->ID;
				$keep[ $source ] = true;

				if ( ! self::is_current( $sources, $source, (string) $page->post_modified_gmt ) ) {
					self::index_page( $page );
				}
			}

			self::remove_gone_pages( $keep );
		} finally {
			WPCPM_Lock::release( self::LOCK, $token );
		}
	}

	/**
	 * Schedule the daily event when it is missing, and one rebuild when the plugin's version is not the
	 * one the index was last built under. Runs on `init`, after the tables are made.
	 */
	public static function maybe_schedule(): void {
		self::schedule_daily();

		$state = WPCPM_Guide_Index::state();
		$built = isset( $state['built_for'] ) ? (string) $state['built_for'] : '';

		if ( WPCPM_VERSION !== $built ) {
			self::schedule_rebuild();
		}
	}

	/**
	 * Activation: the tables, then one rebuild.
	 */
	public static function activate(): void {
		WPCPM_Guide_Index::maybe_upgrade();
		self::schedule_daily();
		self::schedule_rebuild();
	}

	/**
	 * Deactivation: both events stop. The index stays.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_DAILY );
		wp_clear_scheduled_hook( self::CRON_REBUILD );
	}

	/**
	 * Uninstall: both tables, the three options, the lock and both events.
	 */
	public static function uninstall(): void {
		WPCPM_Guide_Index::drop();
		delete_option( self::OPT_HANDBOOK );
		delete_option( self::LOCK );
		self::deactivate();
	}

	/**
	 * The handbook's read, without the lock (the caller holds it).
	 *
	 * Everything is read and checked before the first write, so a failed request, a body that is not a
	 * list of pages, or an empty list changes nothing: every handbook passage stays, and the state says
	 * why. A page whose `modified_gmt` is the one indexed (and that wrote cleanly) is skipped, unless
	 * `$force`; a handbook source no longer kept is removed. Unless the answer says the handbook holds
	 * more pages than one request reads (`more`): then a page missing from it may be on a page of the
	 * route this request did not read, so the pages it has are written and none is removed.
	 *
	 * A page the answer sends incomplete keeps what the index holds of it, and `malformed` counts them
	 * (`read_handbook()`). An entry with no usable id is counted too, and while there is one no page is
	 * removed, since which indexed page it stood for cannot be known; every whole entry is still written
	 * and the run is still ok. A write or a removal the database refused is not counted as changed or
	 * removed but as `failed`, and a run with any is no success: `ok` is false and `error` says how many
	 * pages failed, in the result and in the stored state, which the command line and the Need help?
	 * screen read. The failed pages are written again by the next run, which skips no source whose
	 * write failed (`is_current()`).
	 *
	 * @param string $from_file A saved answer of the route, or ''.
	 * @param bool   $force     Write every kept page, moved or not.
	 * @return array{ok: bool, pages: int, changed: int, removed: int, error: string, more: bool, malformed: int, failed: int}
	 */
	private static function run_handbook( string $from_file, bool $force ): array {
		$result    = array(
			'ok'        => false,
			'pages'     => 0,
			'changed'   => 0,
			'removed'   => 0,
			'error'     => '',
			'more'      => false,
			'malformed' => 0,
			'failed'    => 0,
		);
		$error     = '';
		$held      = array();
		$more      = false;
		$malformed = 0;
		$idless    = false;
		$items     = self::read_handbook( $from_file, $error, $held, $more, $malformed, $idless );

		if ( null === $items ) {
			$result['error'] = $error;
			$result['pages'] = self::held_handbook_pages();
			self::record_handbook( $result );
			return $result;
		}

		$sources = WPCPM_Guide_Index::sources();
		$kept    = array();

		foreach ( $held as $id ) {
			$kept[ 'handbook:' . $id ] = true;
		}

		foreach ( $items as $item ) {
			$source          = 'handbook:' . (int) $item['id'];
			$kept[ $source ] = true;

			if ( ! $force && self::is_current( $sources, $source, (string) $item['modified_gmt'] ) ) {
				continue;
			}

			WPCPM_Guide_Index::write(
				$source,
				array(
					'kind'     => 'handbook',
					'audience' => 'public',
					'post_id'  => 0,
					'title'    => self::handbook_title( $item ),
					'url'      => (string) $item['link'],
					'modified' => (string) $item['modified_gmt'],
				),
				WPCPM_Guide_Text::passages( (string) $item['content']['rendered'] )
			);

			// write() records every outcome in the state: an entry with an error is a write that failed.
			$after = WPCPM_Guide_Index::sources();

			if ( ! isset( $after[ $source ] ) || isset( $after[ $source ]['error'] ) ) {
				++$result['failed'];
			} else {
				++$result['changed'];
			}
		}

		// The sweep waits when the answer is not the whole handbook, or when an entry came with no id: which
		// indexed page it stood for cannot be known, so no page is taken out until a whole answer says so.
		foreach ( $more || $idless ? array() : array_keys( $sources ) as $source ) {
			if ( 0 === strpos( (string) $source, 'handbook:' ) && ! isset( $kept[ $source ] ) ) {
				WPCPM_Guide_Index::remove( (string) $source );

				// remove() keeps the entry, marked, when its rows could not be deleted.
				if ( isset( WPCPM_Guide_Index::sources()[ $source ] ) ) {
					++$result['failed'];
				} else {
					++$result['removed'];
				}
			}
		}

		$result['ok']        = 0 === $result['failed'];
		$result['more']      = $more;
		$result['malformed'] = $malformed;
		$result['pages']     = $more || $idless ? self::held_handbook_pages() : count( array_intersect_key( $kept, WPCPM_Guide_Index::sources() ) );

		if ( ! $result['ok'] ) {
			$result['error'] = sprintf(
				/* translators: %d: how many of the handbook's pages the index could not write or take out. */
				__( '%d of the handbook\'s pages could not be updated in the index. The next run tries again.', 'wpcredits-program-manager' ),
				$result['failed']
			);
		}

		self::record_handbook( $result );

		return $result;
	}

	/**
	 * The kept pages of the route's answer, or null with a sentence in `$error` when the answer cannot
	 * be trusted.
	 *
	 * An entry with a usable id (a whole number above 0) but without what a write needs, an address and
	 * a modified time that are text and content to read, is not written and not treated as gone: its id
	 * goes in `$held`, what the index holds of it stays, and it is counted in `$malformed`. An entry whose
	 * address is a page the index does not read is left out, whatever else it lacks. An entry with no
	 * usable id is written nowhere and is counted in `$malformed` too, and since which page of the index
	 * it stood for cannot be known, `$idless` is set so that the run takes no page out.
	 *
	 * @param string $from_file A saved answer, or ''.
	 * @param string $error     Set to the reason on failure.
	 * @param int[]  $held      Set to the ids of kept pages sent incomplete.
	 * @param bool   $more      Set to true when the route's `X-WP-TotalPages` says the handbook holds more
	 *                          pages than this one request read.
	 * @param int    $malformed Set to how many entries came incomplete.
	 * @param bool   $idless    Set to true when any entry came with no usable id.
	 * @return array<int, array>|null
	 */
	private static function read_handbook( string $from_file, string &$error, array &$held, bool &$more, int &$malformed, bool &$idless ): ?array {
		if ( '' !== $from_file ) {
			$body = is_readable( $from_file ) ? file_get_contents( $from_file ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file the command line names, not a URL.

			if ( ! is_string( $body ) ) {
				$error = __( 'The saved handbook file could not be read.', 'wpcredits-program-manager' );
				return null;
			}
		} else {
			$reply = wp_remote_get(
				self::HANDBOOK_ROUTE . '?per_page=100&_fields=id,link,title,modified_gmt,content',
				array( 'timeout' => self::HANDBOOK_TIMEOUT )
			);

			if ( is_wp_error( $reply ) ) {
				$error = __( 'The handbook could not be reached.', 'wpcredits-program-manager' );
				return null;
			}

			$code = (int) wp_remote_retrieve_response_code( $reply );

			if ( 200 !== $code ) {
				/* translators: %d: the HTTP status the handbook answered with. */
				$error = sprintf( __( 'The handbook answered with HTTP %d.', 'wpcredits-program-manager' ), $code );
				return null;
			}

			$body = (string) wp_remote_retrieve_body( $reply );
			$more = (int) wp_remote_retrieve_header( $reply, 'x-wp-totalpages' ) > 1;
		}

		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || array() === $data || array_keys( $data ) !== range( 0, count( $data ) - 1 ) ) {
			$error = __( 'The handbook\'s answer was not a list of pages.', 'wpcredits-program-manager' );
			return null;
		}

		$items = array();

		foreach ( $data as $item ) {
			$id = is_array( $item ) && isset( $item['id'] ) ? self::handbook_id( $item['id'] ) : 0;

			$link = is_array( $item ) && isset( $item['link'] ) && is_string( $item['link'] ) ? $item['link'] : '';

			if ( '' !== $link && ! self::handbook_keep( $link ) ) {
				continue;
			}

			if ( $id < 1 ) {
				$idless = true;
				++$malformed;
				continue;
			}

			$modified = isset( $item['modified_gmt'] ) && is_string( $item['modified_gmt'] ) ? $item['modified_gmt'] : '';
			$content  = isset( $item['content']['rendered'] ) && is_string( $item['content']['rendered'] );

			if ( '' === $link || '' === $modified || ! $content ) {
				$held[] = $id;
				++$malformed;
				continue;
			}

			$item['id'] = $id;
			$items[]    = $item;
		}

		if ( array() === $items && array() === $held ) {
			$error = __( 'The handbook\'s answer held no page to keep.', 'wpcredits-program-manager' );
			return null;
		}

		return $items;
	}

	/**
	 * A handbook entry's id as the index keys it: a whole number above 0, as a number or as digits, or 0
	 * when it is not one.
	 *
	 * @param mixed $id The entry's `id`.
	 * @return int
	 */
	private static function handbook_id( $id ): int {
		if ( is_int( $id ) ) {
			return max( 0, $id );
		}

		return is_string( $id ) && 1 === preg_match( '/^[1-9][0-9]{0,18}$/', $id ) ? (int) $id : 0;
	}

	/**
	 * A handbook page's title: `title.rendered`, tags off and entities decoded.
	 *
	 * @param array $item The route's page.
	 * @return string
	 */
	private static function handbook_title( array $item ): string {
		$title = isset( $item['title']['rendered'] ) && is_string( $item['title']['rendered'] ) ? $item['title']['rendered'] : '';

		return trim( html_entity_decode( strip_tags( $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Plain PHP, so the suites run it without WordPress.
	}

	/**
	 * Keep the handbook's state: when it last ran, how it went, how many pages it holds, and whether the
	 * handbook holds more pages than one request reads, which is the sign the sync has to read on. How
	 * many entries came incomplete (`malformed`) and how many pages the database refused (`failed`) are
	 * kept when there were any, so a clean sync's state is what it always was.
	 *
	 * @param array $result `run_handbook()`'s answer.
	 */
	private static function record_handbook( array $result ): void {
		$state = array(
			'synced_at' => time(),
			'ok'        => (bool) $result['ok'],
			'error'     => (string) $result['error'],
			'pages'     => (int) $result['pages'],
			'more'      => ! empty( $result['more'] ),
		);

		foreach ( array( 'malformed', 'failed' ) as $count ) {
			if ( ! empty( $result[ $count ] ) ) {
				$state[ $count ] = (int) $result[ $count ];
			}
		}

		update_option( self::OPT_HANDBOOK, $state, false );
	}

	/**
	 * How many handbook pages the index holds, from its state.
	 *
	 * @return int
	 */
	private static function held_handbook_pages(): int {
		$count = 0;

		foreach ( array_keys( WPCPM_Guide_Index::sources() ) as $source ) {
			if ( 0 === strpos( (string) $source, 'handbook:' ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Whether a source is as current as its page: written at this modified time and without an error.
	 *
	 * @param array  $sources  The state's sources.
	 * @param string $source   The source's key.
	 * @param string $modified The page's modified time.
	 * @return bool
	 */
	private static function is_current( array $sources, string $source, string $modified ): bool {
		return isset( $sources[ $source ] )
			&& ! isset( $sources[ $source ]['error'] )
			&& isset( $sources[ $source ]['modified'] )
			&& '' !== $modified
			&& (string) $sources[ $source ]['modified'] === $modified;
	}

	/**
	 * Take out every `page:` source whose page was not found at a listed path in this run: a page
	 * deleted, or moved off its path, since it was indexed.
	 *
	 * @param array<string, true> $keep The page sources found.
	 */
	private static function remove_gone_pages( array $keep ): void {
		foreach ( array_keys( WPCPM_Guide_Index::sources() ) as $source ) {
			if ( 0 === strpos( (string) $source, 'page:' ) && ! isset( $keep[ $source ] ) ) {
				WPCPM_Guide_Index::remove( (string) $source );
			}
		}
	}

	/**
	 * The page at a listed path, whatever its status, or null.
	 *
	 * @param string $path The path.
	 * @return WP_Post|null
	 */
	private static function page_at( string $path ): ?WP_Post {
		$page = get_page_by_path( $path, OBJECT, 'page' );

		return $page instanceof WP_Post ? $page : null;
	}

	/**
	 * The published, unprotected page at a listed path, or null.
	 *
	 * @param string $path The path.
	 * @return WP_Post|null
	 */
	private static function published_page( string $path ): ?WP_Post {
		$page = self::page_at( $path );

		return null !== $page && self::is_open( $page ) ? $page : null;
	}

	/**
	 * Whether a page may be indexed: published, with no password, and, when its audience is `public`, at the public level.
	 *
	 * @param WP_Post $page The page.
	 * @return bool
	 */
	private static function is_open( WP_Post $page ): bool {
		if ( 'publish' !== $page->post_status || '' !== (string) $page->post_password ) {
			return false;
		}

		// Every asker reads `public`, so a page of that audience is indexed only while its own level is
		// public: a FAQ set to Administrators only must stop feeding everybody (the controller's ruling T3-1).
		$spec = self::spec_of( $page );

		if ( null !== $spec && 'public' === $spec['audience'] && 'public' !== WPCPM_Content_Access::get_level( $page ) ) {
			return false;
		}

		return true;
	}

	/**
	 * The listing of the page's own path, or null when it is not a listed page.
	 *
	 * @param WP_Post $page The page.
	 * @return array{audience: string, kind: string}|null
	 */
	private static function spec_of( WP_Post $page ): ?array {
		if ( 'page' !== $page->post_type ) {
			return null;
		}

		$uri = get_page_uri( $page );

		if ( ! is_string( $uri ) ) {
			return null;
		}

		$pages = self::pages();

		return isset( $pages[ $uri ] ) ? $pages[ $uri ] : null;
	}

	/**
	 * Schedule the daily event when it is missing.
	 */
	private static function schedule_daily(): void {
		if ( ! wp_next_scheduled( self::CRON_DAILY ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_DAILY );
		}
	}

	/**
	 * Schedule one rebuild, unless one is waiting.
	 */
	private static function schedule_rebuild(): void {
		if ( ! wp_next_scheduled( self::CRON_REBUILD ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CRON_REBUILD );
		}
	}

	/**
	 * The sentence for a run that found the index busy.
	 *
	 * @return string
	 */
	private static function busy_sentence(): string {
		return __( 'The index is being written by another run. Try again in a few minutes.', 'wpcredits-program-manager' );
	}
}
