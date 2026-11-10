<?php
/**
 * The Administrator Dashboard: every queue on one page, for program managers.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The page program managers work from.
 *
 * Built exactly like the Institution Dashboard (design of 4 September 2026, decision 1):
 * one page the module creates and adopts by slug, gated to the administrator level with
 * `metadata_exists()`, a block and a shortcode that both reach `render()`, and one
 * `render()` that draws one sentence under the title, the attention strip, the cards in their
 * three groups and, last, the Tools section and the resources. The groups and the order of the
 * cards in each are `groups()`'s.
 */
final class WPCPM_Administrators_Dashboard {
	/** The shortcode, for a page the plugin did not create. */
	const SHORTCODE = 'wpcpm_administrator_dashboard';
	/** The block. */
	const BLOCK = 'wpcpm/administrator-dashboard';
	/** The page the plugin created, by ID. */
	const OPT_PAGE = 'wpcpm_administrator_page_id';
	/** The stylesheet handle; the theme's skin depends on it when it is registered. */
	const STYLE = 'wpcpm-administrator-dashboard';
	/** The slug, which is also the theme template's name: `page-administrator-dashboard.html`. */
	const SLUG = 'administrator-dashboard';
	/** Whether the title rename for TITLE_VERSION has run. */
	const OPT_TITLE_FIXED = 'wpcpm_administrator_page_title_fixed';
	/** Bumped when the product renames the page; `maybe_rename_page()` follows once. */
	const TITLE_VERSION = 1;
	/** Titles a previous version gave the page. None yet. */
	const OLD_TITLES = array();
	/** The module id `WPCPM_Dashboards::nothing_to_show()` knows. */
	const MODULE = 'administrators';
	/** The query argument a view is asked for by, which the cohort timeline asks by too. */
	const VIEW_ARG = 'wpcpm_view';
	/** The Reference view (1.123.69): the program's numbers, the Tools from our sponsors and the resources. */
	const VIEW_REFERENCE = 'reference';
	/**
	 * What this page's groups ask of the dashboards' group printer, `WPCPM_Dashboard_Card::group()`,
	 * besides their own id, heading, sentence and cards: the page's classes, which the theme's
	 * rules for this page key on.
	 */
	const GROUP = array(
		'group_class' => 'wpcpm-administrator__group',
		'title_class' => 'wpcpm-administrator__group-title',
		'lead_class'  => 'wpcpm-administrator__group-lead',
	);

	/**
	 * Hooks. Called from `WPCPM_Administrators::boot()`.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'maybe_rename_page' ), 20 );
	}

	/**
	 * The stylesheet, the shortcode and the block.
	 */
	public static function register() {
		self::register_assets();

		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );

		$block_dir = WPCPM_PLUGIN_DIR . 'blocks/administrator-dashboard';

		if ( function_exists( 'register_block_type' ) && file_exists( $block_dir . '/block.json' ) ) {
			register_block_type( $block_dir, array( 'render_callback' => array( __CLASS__, 'render_block' ) ) );
		}
	}

	/**
	 * Register (but do not enqueue) the stylesheet.
	 *
	 * Public because the Sponsor Dashboard's sheet depends on this one: that page's At a glance strip
	 * wears this page's attention tiles, so it asks this class for the sheet that dresses them, the
	 * way both pages ask the mentors' class for theirs.
	 */
	public static function register_assets() {
		// The Tools section's whole base look lives in the mentors' stylesheet, as it does for
		// the other three dashboards; without this dependency it drew as a plain bulleted list
		// with full-size logos (final review of Phase S2, finding 3).
		WPCPM_Mentors_Dashboard::register_assets();

		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style(
				self::STYLE,
				WPCPM_PLUGIN_URL . 'assets/css/administrator.css',
				array( WPCPM_Mentors_Dashboard::STYLE ),
				WPCPM_VERSION
			);
		}

		// The frame of the version 3 concepts: the top bar, the menu and the main area (1.123.69).
		WPCPM_Dashboard_Frame::register_assets();

		// The Upcoming cohorts card and the cohort timeline view it opens (1.123.68).
		if ( class_exists( 'WPCPM_Cohort_Timeline_View' ) ) {
			WPCPM_Cohort_Timeline_View::register_assets();
		}
	}

	/**
	 * The block's render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		return self::render( is_array( $attributes ) ? $attributes : array() );
	}

	/**
	 * The page title, written once.
	 *
	 * @return string
	 */
	public static function title() {
		return __( 'Administrator Dashboard', 'wpcredits-program-manager' );
	}

	/**
	 * Rename the page once per TITLE_VERSION, only when it still carries an old title.
	 */
	public static function maybe_rename_page() {
		if ( (int) get_option( self::OPT_TITLE_FIXED ) >= self::TITLE_VERSION ) {
			return;
		}

		// Written first, whatever happens next: a rename that fails should not be retried
		// on every request of every visitor.
		update_option( self::OPT_TITLE_FIXED, self::TITLE_VERSION, false );

		$page_id = (int) get_option( self::OPT_PAGE );
		$page    = $page_id ? get_post( $page_id ) : null;

		if ( $page instanceof WP_Post && in_array( (string) $page->post_title, self::OLD_TITLES, true ) ) {
			wp_update_post(
				array(
					'ID'         => $page_id,
					'post_title' => self::title(),
				)
			);
		}
	}

	/**
	 * The page: the stored one, else the one at the slug, else a new one. Gated either way.
	 *
	 * A site that has the page but not the option (restored from a backup, or migrated)
	 * adopts it rather than creating a second one at `administrator-dashboard-2`.
	 *
	 * @return int The page ID, or 0.
	 */
	public static function ensure_page() {
		$page_id = (int) get_option( self::OPT_PAGE );

		if ( $page_id ) {
			$page = get_post( $page_id );

			if ( $page instanceof WP_Post && 'trash' !== $page->post_status ) {
				self::gate_page( $page_id );

				return $page_id;
			}
		}

		$existing = get_page_by_path( self::SLUG );

		if ( $existing instanceof WP_Post && 'trash' !== $existing->post_status ) {
			update_option( self::OPT_PAGE, (int) $existing->ID, false );
			self::gate_page( (int) $existing->ID );

			return (int) $existing->ID;
		}

		// The second argument is what makes a failure a WP_Error rather than 0: without it,
		// is_wp_error( $page_id ) below can never be true, the same way WPCPM_Institutions_
		// Dashboard::ensure_page() asks.
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => self::title(),
				'post_name'    => self::SLUG,
				'post_content' => '<!-- wp:' . self::BLOCK . ' /-->',
			),
			true
		);

		if ( ! $page_id || is_wp_error( $page_id ) ) {
			return 0;
		}

		update_option( self::OPT_PAGE, (int) $page_id, false );
		self::gate_page( (int) $page_id );

		return (int) $page_id;
	}

	/**
	 * Gate the page to the administrator level, once.
	 *
	 * `metadata_exists()` and not the value: the level is registered with a default of
	 * `public`, so `get_post_meta()` reads a brand-new page as deliberately public and never
	 * gates it, which is how the Institution Dashboard first came up on the live site.
	 *
	 * @param int $page_id The page.
	 */
	private static function gate_page( $page_id ) {
		if ( ! metadata_exists( 'post', (int) $page_id, WPCPM_Content_Access::META_KEY ) ) {
			update_post_meta( (int) $page_id, WPCPM_Content_Access::META_KEY, WPCPM_Roles::ROLE_ADMIN );
		}
	}

	/**
	 * The page's address, or '' while there is no published page.
	 *
	 * @return string
	 */
	public static function page_url() {
		$page_id = (int) get_option( self::OPT_PAGE );

		if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}

		return (string) get_permalink( $page_id );
	}

	/**
	 * What a screen says in place of the page's address while `page_url()` has none.
	 *
	 * Here, beside the page it is about, because several screens print it, the Administrators screen,
	 * the Overview and the Institutions and Sponsors screens, where a link to the page would stand,
	 * and a sentence written out on each would be several sentences the day one of them is reworded.
	 *
	 * @return string The sentence, translated and not escaped.
	 */
	public static function page_missing() {
		return __( 'The Administrator Dashboard page is missing. Re-activate the plugin to recreate it.', 'wpcredits-program-manager' );
	}

	/**
	 * The page. The block and the shortcode both land here.
	 *
	 * The capability is checked here as well as by the page's level, because a page can be
	 * reached through the shortcode in some other post.
	 *
	 * @param array $attributes `title`.
	 * @return string
	 */
	public static function render( $attributes = array() ) {
		$atts = shortcode_atts( array( 'title' => '' ), is_array( $attributes ) ? $attributes : array(), self::SHORTCODE );

		wp_enqueue_style( self::STYLE );

		if ( class_exists( 'WPCPM_Cohort_Timeline_View' ) ) {
			wp_enqueue_style( WPCPM_Cohort_Timeline_View::STYLE );
		}

		// Every form on this page prints `data-wpcpm-once`; the guard is inert without the
		// script, and the Institution Dashboard shipped once that way.
		if ( ! wp_script_is( 'wpcpm-forms', 'registered' ) ) {
			wp_register_script( 'wpcpm-forms', WPCPM_PLUGIN_URL . 'assets/js/forms.js', array(), WPCPM_VERSION, true );
		}

		wp_enqueue_script( 'wpcpm-forms' );

		// A tile, a link in a mail or a decision's way back lands on a card's anchor, and a browser
		// that follows a link to a closed card leaves it closed: the dashboards' opener, the one the
		// Sponsor Dashboard's cards use, opens it.
		WPCPM_Dashboard_Card::enqueue_opener();

		if ( ! is_user_logged_in() ) {
			// Same shape as WPCPM_Institutions_Dashboard::render() for a logged-out visitor: a
			// sentence and a way back in, not a dead end nobody without an account can do
			// anything with.
			return self::notice(
				__( 'Log in to see the Administrator Dashboard.', 'wpcredits-program-manager' ),
				wp_login_url( get_permalink() ),
				__( 'Log in', 'wpcredits-program-manager' )
			);
		}

		$viewer = wp_get_current_user();

		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return self::notice( WPCPM_Dashboards::nothing_to_show( self::MODULE, false ) );
		}

		$data   = WPCPM_Administrators_Cards::collect();
		$counts = WPCPM_Administrators_Cards::counts( $data );
		$view   = self::view();

		WPCPM_Dashboard_Card::forget();

		$wait = self::waiting( $data, $counts );

		ob_start();

		// The frame of the version 3 concepts (1.123.69): the top bar, the menu and the main area. The
		// page's own wrapper and classes stay inside the main area, so every rule the cards were dressed
		// by still reaches them.
		WPCPM_Dashboard_Frame::open( self::frame_args( $view, $wait, $counts ) );

		printf( '<div class="wpcpm-dashboard wpcpm-administrator wpcpm-administrator--%s">', esc_attr( $view ) );

		WPCPM_Two_Factor::prompt( $viewer );

		if ( '' !== trim( (string) $atts['title'] ) ) {
			printf( '<h2 class="wpcpm-dashboard__title">%s</h2>', esc_html( $atts['title'] ) );
		}

		if ( 'cohort-timeline' === $view ) {
			self::page_name();
			WPCPM_Cohort_Timeline_View::render_view( ! empty( $data['cohort_timeline'] ) ? (array) $data['cohort_timeline'] : WPCPM_Cohort_Timeline::report() );
		} elseif ( self::VIEW_REFERENCE === $view ) {
			self::render_reference( $data, $viewer );
		} else {
			self::render_overview( $data, $wait );
		}

		echo '</div>';

		WPCPM_Dashboard_Frame::close();

		return WPCPM_Dashboard_Card::settle( (string) ob_get_clean() );
	}

	/**
	 * Which of the page's views this request asks for: the Overview, the cohort timeline (1.123.68) or the
	 * Reference view (1.123.69), each at the page's own address with `?wpcpm_view=`.
	 *
	 * @return string `overview`, `cohort-timeline` or `reference`.
	 */
	private static function view() {
		if ( class_exists( 'WPCPM_Cohort_Timeline_View' ) && WPCPM_Cohort_Timeline_View::is_requested() ) {
			return 'cohort-timeline';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A view to read, which changes nothing.
		if ( isset( $_GET[ self::VIEW_ARG ] ) && self::VIEW_REFERENCE === sanitize_key( wp_unslash( $_GET[ self::VIEW_ARG ] ) ) ) {
			return self::VIEW_REFERENCE;
		}

		return 'overview';
	}

	/**
	 * A view's address: the page's own, with `?wpcpm_view=` for any view but the Overview.
	 *
	 * @param string $view `overview`, `cohort-timeline` or `reference`.
	 * @return string '' while the page has no address.
	 */
	public static function view_url( $view = 'overview' ) {
		$page = self::page_url();

		if ( '' === $page ) {
			$page = (string) get_permalink();
		}

		if ( '' === $page || 'overview' === $view ) {
			return $page;
		}

		return add_query_arg( self::VIEW_ARG, sanitize_key( (string) $view ), $page );
	}

	/**
	 * The queues that wait for an Administrator's decision, in the order the page reads them: each with its
	 * card's title, the screen it is decided on in wp-admin, the strip's tile key and what draws its card.
	 * The mentor applications are decided on the Mentors screen and have no card here (design of 16 October
	 * 2026, decision 9); the Events card is a queue only while Sponsor tiers is switched on.
	 *
	 * @param array $data What `WPCPM_Administrators_Cards::collect()` returned.
	 * @return array<string, array> Keyed by the strip's tile key.
	 */
	private static function queues( array $data ) {
		$institutions = admin_url( 'admin.php?page=wpcpm-institutions' );
		$sponsors     = admin_url( 'admin.php?page=wpcpm-sponsors' );

		$queues = array(
			'applications'         => array(
				'card'   => 'applications',
				'title'  => __( 'Institution applications', 'wpcredits-program-manager' ),
				'screen' => add_query_arg( 'tab', 'queue', $institutions ),
				'rows'   => isset( $data['applications']['open'] ) ? (array) $data['applications']['open'] : array(),
				'draw'   => static function () use ( $data ) {
					WPCPM_Administrators_Cards::render_applications( $data['applications'] );
				},
			),
			'agreements'           => array(
				'card'   => 'agreements',
				'title'  => __( 'Collaboration Agreements', 'wpcredits-program-manager' ),
				'screen' => add_query_arg( 'tab', 'queue', $institutions ),
				'rows'   => isset( $data['agreements']['awaiting'] ) ? (array) $data['agreements']['awaiting'] : array(),
				'draw'   => static function () use ( $data ) {
					WPCPM_Administrators_Cards::render_agreements( $data['agreements'] );
				},
			),
			'reports'              => array(
				'card'   => 'reports',
				'title'  => __( 'Semester reports', 'wpcredits-program-manager' ),
				'screen' => add_query_arg( 'tab', 'reports', $institutions ),
				'rows'   => isset( $data['reports']['queue'] ) ? (array) $data['reports']['queue'] : array(),
				'draw'   => static function () use ( $data ) {
					WPCPM_Administrators_Cards::render_reports( $data['reports'] );
				},
			),
			'requests'             => array(
				'card'   => 'requests',
				'title'  => __( 'Requests from institutions', 'wpcredits-program-manager' ),
				'screen' => add_query_arg( 'tab', 'queue', $institutions ),
				'rows'   => isset( $data['requests']['open'] ) ? (array) $data['requests']['open'] : array(),
				'draw'   => static function () use ( $data ) {
					WPCPM_Administrators_Cards::render_requests( $data['requests'] );
				},
			),
			'mentor_applications'  => array(
				'card'   => 'mentor-applications',
				'title'  => __( 'Mentor applications', 'wpcredits-program-manager' ),
				'screen' => admin_url( 'admin.php?page=wpcpm-mentors&tab=queue' ),
				// Read only while some wait, as posts, so the oldest counts among the queues' oldest.
				'rows'   => class_exists( 'WPCPM_Mentor_Application' ) && ! empty( $data['mentor_applications'] ) ? WPCPM_Mentor_Application::applications( WPCPM_Mentor_Application::open_states() ) : array(),
				'draw'   => null,
			),
			'sponsor_applications' => array(
				'card'   => 'sponsor-applications',
				'title'  => __( 'Sponsor applications', 'wpcredits-program-manager' ),
				'screen' => add_query_arg( 'tab', 'queue', $sponsors ) . '#wpcpm-sponsor-applications',
				'rows'   => isset( $data['sponsor_applications'] ) ? (array) $data['sponsor_applications'] : array(),
				'draw'   => static function () use ( $data ) {
					WPCPM_Administrators_Cards::render_sponsor_applications( isset( $data['sponsor_applications'] ) ? (array) $data['sponsor_applications'] : array(), $data['sponsor_totals']['applications'] );
				},
			),
			'sponsor_posts'        => array(
				'card'   => 'sponsor-posts',
				'title'  => __( 'Sponsor posts to review', 'wpcredits-program-manager' ),
				'screen' => add_query_arg( 'tab', 'queue', $sponsors ) . '#wpcpm-sponsor-posts',
				'rows'   => isset( $data['sponsor_posts'] ) ? (array) $data['sponsor_posts'] : array(),
				'draw'   => static function () use ( $data ) {
					WPCPM_Administrators_Cards::render_sponsor_posts( isset( $data['sponsor_posts'] ) ? (array) $data['sponsor_posts'] : array(), $data['sponsor_totals']['posts'] );
				},
			),
			'sponsor_offers'       => array(
				'card'   => 'sponsor-offers-review',
				'title'  => __( 'Sponsor offers to review', 'wpcredits-program-manager' ),
				'screen' => add_query_arg( 'tab', 'queue', $sponsors ) . '#wpcpm-sponsor-offers-review',
				'rows'   => isset( $data['sponsor_offers'] ) ? (array) $data['sponsor_offers'] : array(),
				'draw'   => static function () use ( $data ) {
					WPCPM_Administrators_Cards::render_sponsor_offers( isset( $data['sponsor_offers'] ) ? (array) $data['sponsor_offers'] : array(), isset( $data['sponsor_totals']['offers'] ) ? $data['sponsor_totals']['offers'] : null );
				},
			),
			'sponsor_events'       => array(
				'card'   => 'sponsor-events',
				'title'  => __( 'Events', 'wpcredits-program-manager' ),
				'screen' => add_query_arg( 'tab', 'queue', $sponsors ) . '#wpcpm-sponsor-events',
				'rows'   => isset( $data['sponsor_events']['waiting'] ) ? (array) $data['sponsor_events']['waiting'] : array(),
				'draw'   => static function () use ( $data ) {
					WPCPM_Administrators_Cards::render_sponsor_events( isset( $data['sponsor_events'] ) ? (array) $data['sponsor_events'] : array() );
				},
			),
			'sponsor_agreements'   => array(
				'card'   => 'sponsor-agreements',
				'title'  => __( 'Sponsor Collaboration Agreements', 'wpcredits-program-manager' ),
				'screen' => add_query_arg( 'tab', 'queue', $sponsors ) . '#wpcpm-sponsor-agreements',
				'rows'   => isset( $data['sponsor_agreements']['review'] ) ? (array) $data['sponsor_agreements']['review'] : array(),
				'draw'   => static function () use ( $data ) {
					WPCPM_Administrators_Cards::render_sponsor_agreements( isset( $data['sponsor_agreements'] ) ? (array) $data['sponsor_agreements'] : array(), $data['sponsor_totals']['agreements'] );
				},
			),
			'sponsor_reports'      => array(
				'card'   => 'sponsor-reports',
				'title'  => __( 'Sponsor reports', 'wpcredits-program-manager' ),
				'screen' => add_query_arg( 'tab', 'reports', $sponsors ),
				// A draft to finish and a term with no report wait from the day the term ended.
				'rows'   => array_merge( isset( $data['sponsor_reports']['queue'] ) ? (array) $data['sponsor_reports']['queue'] : array(), isset( $data['sponsor_reports']['due'] ) ? (array) $data['sponsor_reports']['due'] : array() ),
				'draw'   => static function () use ( $data ) {
					WPCPM_Administrators_Cards::render_sponsor_reports( isset( $data['sponsor_reports'] ) ? (array) $data['sponsor_reports'] : array() );
				},
			),
		);

		// The Events card draws nothing while Sponsor tiers is switched off, so the queue is not one; its data
		// still holds two empty lists then, so the switch is asked itself.
		if ( empty( $data['sponsor_events'] ) || ( class_exists( 'WPCPM_Sponsor_Event_Queue' ) && ! WPCPM_Sponsor_Event_Queue::shown() ) ) {
			unset( $queues['sponsor_events'] );
		}

		if ( ! class_exists( 'WPCPM_Mentor_Application' ) ) {
			unset( $queues['mentor_applications'] );
		}

		return $queues;
	}

	/**
	 * What waits, read once for the head, the menu and the Waiting group: the queues with something in
	 * them, how many decisions wait in all, and how long the oldest of each has waited.
	 *
	 * @param array $data   What `WPCPM_Administrators_Cards::collect()` returned.
	 * @param array $counts What `WPCPM_Administrators_Cards::counts()` returned.
	 * @return array `total`, `queues` (every queue, each with `n`, `anchor` and `since`, 0 for unknown),
	 *               `watch` (how many of the cards to watch have something to look at) and `oldest`, the
	 *               queue key whose item has waited longest, or ''.
	 */
	private static function waiting( array $data, array $counts ) {
		$queues = self::queues( $data );
		$tests  = class_exists( 'WPCPM_Test_Institutions' ) ? array_fill_keys( WPCPM_Test_Institutions::test_ids(), true ) : array();
		$total  = 0;
		$oldest = '';
		$first  = 0;

		foreach ( $queues as $key => $queue ) {
			$tile = isset( $counts[ $key ] ) && is_array( $counts[ $key ] ) ? $counts[ $key ] : array();
			$n    = isset( $tile['n'] ) ? max( 0, (int) $tile['n'] ) : 0;

			$queues[ $key ]['n']      = $n;
			$queues[ $key ]['anchor'] = 'wpcpm-' . sanitize_key( isset( $tile['card'] ) && '' !== (string) $tile['card'] ? (string) $tile['card'] : (string) $queue['card'] );
			$queues[ $key ]['since']  = $n > 0 ? self::since( $queues[ $key ]['rows'], $tests ) : 0;

			$total += $n;

			if ( $queues[ $key ]['since'] > 0 && ( 0 === $first || $queues[ $key ]['since'] < $first ) ) {
				$first  = $queues[ $key ]['since'];
				$oldest = $key;
			}
		}

		// Watch counts the cards that have something to look at: the syncs that need a look and the locked
		// accounts are both Syncs and health's, so they count once.
		$watching = array();

		foreach ( array( 'offers_low', 'duplicates', 'syncs', 'locked', 'quiet_students' ) as $key ) {
			if ( ! empty( $counts[ $key ]['n'] ) ) {
				$watching[ isset( $counts[ $key ]['card'] ) ? (string) $counts[ $key ]['card'] : $key ] = true;
			}
		}

		$watch = count( $watching );

		return array(
			'total'  => $total,
			'queues' => $queues,
			'watch'  => $watch,
			'oldest' => $oldest,
		);
	}

	/**
	 * When the oldest of a queue's rows came in, from whichever date the row carries: a post's date, or
	 * `at`, `planned_at`, `generated`, `uploaded_at` or `until` (the day a sponsor's term ended), a time or a
	 * date, or `age_days`; a time still ahead is not one. A test
	 * institution's rows are left out, as they are of every count.
	 *
	 * @param array $rows  The queue's rows.
	 * @param array $tests Test institutions' record IDs, as keys.
	 * @return int A Unix time, or 0 when no row says.
	 */
	private static function since( array $rows, array $tests ) {
		$oldest = 0;

		foreach ( $rows as $row ) {
			$time = 0;

			if ( $row instanceof WP_Post ) {
				$time = (int) get_post_time( 'U', true, $row );
			} elseif ( is_array( $row ) ) {
				foreach ( array( 'record', 'institution' ) as $field ) {
					if ( isset( $row[ $field ] ) && is_string( $row[ $field ] ) && isset( $tests[ trim( $row[ $field ] ) ] ) ) {
						continue 2;
					}
				}

				foreach ( array( 'at', 'planned_at', 'generated', 'uploaded_at', 'until' ) as $field ) {
					if ( ! empty( $row[ $field ] ) ) {
						$time = is_numeric( $row[ $field ] ) ? (int) $row[ $field ] : (int) strtotime( (string) $row[ $field ] );
						break;
					}
				}

				if ( 0 >= $time && isset( $row['age_days'] ) ) {
					$time = time() - max( 0, (int) $row['age_days'] ) * DAY_IN_SECONDS;
				}
			}

			// A time still ahead, a term that has not ended, has not started to wait.
			if ( $time > 0 && $time <= time() && ( 0 === $oldest || $time < $oldest ) ) {
				$oldest = $time;
			}
		}

		return $oldest;
	}

	/**
	 * How many whole days ago a time was.
	 *
	 * @param int $time A Unix time.
	 * @return int
	 */
	private static function days_since( $time ) {
		return max( 0, (int) floor( ( time() - (int) $time ) / DAY_IN_SECONDS ) );
	}

	/**
	 * The menu: the Overview with the decisions that wait, Watch, the cohort timeline and Reference; the
	 * screens in wp-admin; and the help for Administrators.
	 *
	 * @param string $view   The view drawn.
	 * @param array  $wait   What `waiting()` returned.
	 * @param array  $counts What `WPCPM_Administrators_Cards::counts()` returned.
	 * @return array What `WPCPM_Dashboard_Frame::open()` takes.
	 */
	private static function frame_args( $view, array $wait, array $counts ) {
		$overview = self::view_url( 'overview' );
		$items    = array(
			WPCPM_Dashboard_Frame::item(
				__( 'Overview', 'wpcredits-program-manager' ),
				$overview,
				'home',
				array(
					'current' => 'overview' === $view,
					/* translators: %s: how many decisions wait. */
					'badge'   => $wait['total'] > 0 ? sprintf( _n( '%s waits', '%s wait', $wait['total'], 'wpcredits-program-manager' ), number_format_i18n( $wait['total'] ) ) : '',
					'tone'    => 'red',
				)
			),
			WPCPM_Dashboard_Frame::item(
				__( 'Watch', 'wpcredits-program-manager' ),
				$overview . '#wpcpm-watch',
				'eye',
				array(
					'badge' => $wait['watch'] > 0 ? number_format_i18n( $wait['watch'] ) : '',
					'tone'  => 'amber',
				)
			),
		);

		if ( class_exists( 'WPCPM_Cohort_Timeline_View' ) ) {
			$items[] = WPCPM_Dashboard_Frame::item( __( 'Cohort timeline', 'wpcredits-program-manager' ), self::view_url( 'cohort-timeline' ), 'timeline', array( 'current' => 'cohort-timeline' === $view ) );
		}

		$items[] = WPCPM_Dashboard_Frame::item( __( 'Reference', 'wpcredits-program-manager' ), self::view_url( self::VIEW_REFERENCE ), 'chart', array( 'current' => self::VIEW_REFERENCE === $view ) );

		$mentors = isset( $counts['mentor_applications']['n'] ) ? (int) $counts['mentor_applications']['n'] : 0;
		$admin   = array(
			WPCPM_Dashboard_Frame::item( __( 'Students', 'wpcredits-program-manager' ), admin_url( 'admin.php?page=wpcpm-students' ), 'users', array( 'external' => true ) ),
			WPCPM_Dashboard_Frame::item(
				__( 'Mentors', 'wpcredits-program-manager' ),
				admin_url( 'admin.php?page=wpcpm-mentors' ),
				'profile',
				array(
					'external' => true,
					'badge'    => $mentors > 0 ? number_format_i18n( $mentors ) : '',
				)
			),
			WPCPM_Dashboard_Frame::item( __( 'Institutions', 'wpcredits-program-manager' ), admin_url( 'admin.php?page=wpcpm-institutions' ), 'building', array( 'external' => true ) ),
			WPCPM_Dashboard_Frame::item( __( 'Sponsors and offers', 'wpcredits-program-manager' ), admin_url( 'admin.php?page=wpcpm-sponsors' ), 'tag', array( 'external' => true ) ),
			WPCPM_Dashboard_Frame::item( __( 'Tracks', 'wpcredits-program-manager' ), admin_url( 'admin.php?page=wpcpm-tool-track-builder' ), 'route', array( 'external' => true ) ),
			WPCPM_Dashboard_Frame::item( __( 'Tools', 'wpcredits-program-manager' ), admin_url( 'admin.php?page=wpcpm-tools' ), 'file', array( 'external' => true ) ),
			WPCPM_Dashboard_Frame::item( __( 'Settings', 'wpcredits-program-manager' ), admin_url( 'admin.php?page=wpcpm-settings' ), 'sliders', array( 'external' => true ) ),
		);

		// What the page is for, at length, is in the Administrator guide, which bin/build-docs.php builds from
		// docs/sections/ and which is published as the site's own /administrator-guide/ page: the menu's help
		// links it while it is published.
		$help  = array();
		$guide = get_page_by_path( 'administrator-guide' );

		if ( $guide instanceof WP_Post && 'publish' === $guide->post_status ) {
			$help[] = WPCPM_Dashboard_Frame::item( __( 'Administrator guide', 'wpcredits-program-manager' ), (string) get_permalink( $guide ), 'book' );
		}

		$help[] = WPCPM_Dashboard_Frame::item( __( 'WordPress Credits handbook', 'wpcredits-program-manager' ), 'https://make.wordpress.org/community/handbook/education/credits/', 'file' );
		$help[] = WPCPM_Dashboard_Frame::item( __( '#wpcredits on Slack', 'wpcredits-program-manager' ), 'https://wordpress.slack.com/archives/C0959D2M3T8', 'slack' );

		return array(
			'page'    => 'administrator',
			'label'   => self::title(),
			'context' => array(
				'label' => __( 'Dashboard', 'wpcredits-program-manager' ),
				'title' => self::title(),
			),
			'groups'  => array(
				array(
					'heading' => '',
					'items'   => $items,
				),
				array(
					'heading' => __( 'In wp-admin', 'wpcredits-program-manager' ),
					'items'   => $admin,
				),
			),
			'help'    => $help,
		);
	}

	/**
	 * The page's name, the page's one H1, when the theme leaves the page to the frame; the theme's own
	 * template prints it otherwise.
	 */
	private static function page_name() {
		if ( WPCPM_Dashboard_Frame::owns_page( 'administrator' ) ) {
			printf( '<h1 class="wpcpm-head__eyebrow">%s</h1>', esc_html( self::title() ) );
		}
	}

	/**
	 * The Overview: the head, the program's figures, then what waits beside what to watch.
	 *
	 * @param array $data What `WPCPM_Administrators_Cards::collect()` returned.
	 * @param array $wait What `waiting()` returned.
	 */
	private static function render_overview( array $data, array $wait ) {
		self::render_head( $wait );
		self::render_figures( $data );

		// The outcome of a decision is held for the card it came from, which prints it at the top of its
		// body; the slot says it here, where it was, only when no card did (SDB-02, 1.123.58).
		self::render_messages();
		echo WPCPM_Dashboard_Card::SLOT; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed comment.

		echo '<div class="wpcpm-overview">';
		echo '<div class="wpcpm-overview__col wpcpm-overview__col--wait">';

		WPCPM_Dashboard_Card::group(
			array_merge(
				self::GROUP,
				array(
					'id'    => 'waiting',
					'title' => __( 'Waiting for you', 'wpcredits-program-manager' ),
					'lead'  => __( 'What needs an Administrator\'s decision, oldest first in each.', 'wpcredits-program-manager' ),
					'cards' => static function () use ( $wait ) {
						self::render_waiting( $wait );
					},
				)
			)
		);

		echo '</div>';
		echo '<div class="wpcpm-overview__col wpcpm-overview__col--watch" id="wpcpm-watch">';

		WPCPM_Dashboard_Card::group(
			array_merge(
				self::GROUP,
				array(
					'id'    => 'watch',
					'title' => __( 'Watch', 'wpcredits-program-manager' ),
					'lead'  => __( 'What to keep an eye on this week.', 'wpcredits-program-manager' ),
					'cards' => static function () use ( $data ) {
						// Each card to watch is one row, folded, so the column reads as a list (the concepts).
						WPCPM_Dashboard_Card::fold( true );

						if ( class_exists( 'WPCPM_Cohort_Timeline_View' ) ) {
							WPCPM_Cohort_Timeline_View::render_card( isset( $data['cohort_timeline'] ) ? (array) $data['cohort_timeline'] : array() );
						}

						WPCPM_Administrators_Cards::render_offers_low( isset( $data['offers_low'] ) ? (array) $data['offers_low'] : array() );
						WPCPM_Administrators_Cards::render_duplicates( isset( $data['duplicates'] ) ? (array) $data['duplicates'] : array() );
						WPCPM_Administrators_Cards::render_interests( isset( $data['interests'] ) ? (array) $data['interests'] : array() );
						WPCPM_Administrators_Cards::render_tiers_ending( isset( $data['tiers_ending'] ) ? (array) $data['tiers_ending'] : array() );
						WPCPM_Administrators_Cards::render_health( $data['health'], $data['locked'] );
						WPCPM_Administrators_Cards::render_quiet_students( isset( $data['quiet_students'] ) ? (array) $data['quiet_students'] : array() );

						WPCPM_Dashboard_Card::fold( false );
					},
				)
			)
		);

		echo '</div></div>';
	}

	/**
	 * The head: the page's name, how many decisions wait in the serif, the oldest in words, and the one
	 * action that opens it.
	 *
	 * @param array $wait What `waiting()` returned.
	 */
	private static function render_head( array $wait ) {
		$total  = (int) $wait['total'];
		$oldest = '' !== $wait['oldest'] && isset( $wait['queues'][ $wait['oldest'] ] ) ? $wait['queues'][ $wait['oldest'] ] : array();

		echo '<header class="wpcpm-head"><div class="wpcpm-head__copy">';

		self::page_name();

		if ( 0 === $total ) {
			printf( '<p class="wpcpm-head__title">%s</p>', esc_html__( 'Nothing waits for you today', 'wpcredits-program-manager' ) );
			printf( '<p class="wpcpm-head__lede">%s</p>', esc_html__( 'No queue needs a decision. What to keep an eye on this week is under Watch.', 'wpcredits-program-manager' ) );
			echo '</div></header>';

			return;
		}

		printf(
			'<p class="wpcpm-head__title">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: how many decisions wait. */
					_n( '%s decision waits for you', '%s decisions wait for you', $total, 'wpcredits-program-manager' ),
					number_format_i18n( $total )
				)
			)
		);

		if ( ! empty( $oldest ) ) {
			$days = self::days_since( (int) $oldest['since'] );

			printf(
				'<p class="wpcpm-head__lede">%s</p>',
				esc_html(
					0 === $days
						/* translators: %s: a queue's name, such as "Semester reports". */
						? sprintf( __( 'The oldest came in today, in %s.', 'wpcredits-program-manager' ), $oldest['title'] )
						/* translators: 1: a number of days, 2: a queue's name, such as "Semester reports". */
						: sprintf( _n( 'The oldest has waited %1$s day, in %2$s.', 'The oldest has waited %1$s days, in %2$s.', $days, 'wpcredits-program-manager' ), number_format_i18n( $days ), $oldest['title'] )
				)
			);

			$href = empty( $oldest['draw'] ) ? (string) $oldest['screen'] : '#' . (string) $oldest['anchor'];

			printf(
				'<div class="wpcpm-head__actions"><a class="wpcpm-btn" href="%1$s">%2$s</a></div>',
				esc_url( $href ),
				esc_html__( 'Review the oldest', 'wpcredits-program-manager' )
			);
		}

		echo '</div></header>';
	}

	/**
	 * The program's figures, as the homepage draws them: the two shares with their base, the active
	 * mentors and the programs running, and one line that says when each was read.
	 *
	 * @param array $data What `WPCPM_Administrators_Cards::collect()` returned.
	 */
	private static function render_figures( array $data ) {
		$cells     = array();
		$published = class_exists( 'WPCPM_Outcome_Figures' ) ? WPCPM_Outcome_Figures::published() : array();
		$figures   = isset( $published['figures'] ) ? (array) $published['figures'] : array();

		if ( isset( $figures['graduation'] ) ) {
			$cells[] = array(
				/* translators: %s: a share, a whole number from 0 to 100. */
				'value' => sprintf( _x( '%s%%', 'a share, as a percentage', 'wpcredits-program-manager' ), number_format_i18n( (int) $figures['graduation']['share'] ) ),
				'label' => __( 'of WordPress Credits students graduate rather than drop out', 'wpcredits-program-manager' ),
				/* translators: 1: students who graduated, 2: students who graduated or dropped out. */
				'base'  => sprintf( _n( '%1$s of %2$s student who graduated or dropped out', '%1$s of %2$s students who graduated or dropped out', (int) $figures['graduation']['n'], 'wpcredits-program-manager' ), number_format_i18n( (int) $figures['graduation']['count'] ), number_format_i18n( (int) $figures['graduation']['n'] ) ),
			);
		}

		if ( isset( $figures['recommend'] ) ) {
			$cells[] = array(
				/* translators: %s: a share, a whole number from 0 to 100. */
				'value' => sprintf( _x( '%s%%', 'a share, as a percentage', 'wpcredits-program-manager' ), number_format_i18n( (int) $figures['recommend']['share'] ) ),
				'label' => __( 'of graduates would recommend the program', 'wpcredits-program-manager' ),
				/* translators: %s: how many answered the question on the final survey. */
				'base'  => sprintf( _n( '%s answer to the final survey', '%s answers to the final survey', (int) $figures['recommend']['n'], 'wpcredits-program-manager' ), number_format_i18n( (int) $figures['recommend']['n'] ) ),
			);
		}

		$mentors = isset( $data['mentor_engagement'] ) ? (array) $data['mentor_engagement'] : array();

		if ( isset( $mentors['active'] ) ) {
			$cells[] = array(
				'value' => number_format_i18n( (int) $mentors['active'] ),
				'label' => _n( 'active mentor', 'active mentors', (int) $mentors['active'], 'wpcredits-program-manager' ),
				/* translators: %s: a number of mentors. */
				'base'  => sprintf( _n( '%s signed in during the last 30 days', '%s signed in during the last 30 days', (int) $mentors['seen_30'], 'wpcredits-program-manager' ), number_format_i18n( (int) $mentors['seen_30'] ) ),
			);
		}

		$programs = isset( $data['programs'] ) ? (array) $data['programs'] : array();

		if ( isset( $programs['tracks'] ) ) {
			$running = 0;

			foreach ( (array) $programs['tracks'] as $track ) {
				if ( is_array( $track ) && ! empty( $track['in_progress'] ) ) {
					++$running;
				}
			}

			$institutions = class_exists( 'WPCPM_Test_Institutions' ) && isset( $programs['rows'] ) ? WPCPM_Test_Institutions::count_real_rows( (array) $programs['rows'], 'record' ) : 0;

			$cells[] = array(
				'value' => number_format_i18n( $running ),
				'label' => _n( 'program running', 'programs running', $running, 'wpcredits-program-manager' ),
				/* translators: %s: a number of institutions. */
				'base'  => sprintf( _n( 'at %s institution', 'at %s institutions', $institutions, 'wpcredits-program-manager' ), number_format_i18n( $institutions ) ),
			);
		}

		if ( empty( $cells ) ) {
			return;
		}

		printf( '<section class="wpcpm-figures wpcpm-figures--%1$d" aria-label="%2$s">', count( $cells ), esc_attr__( 'The program\'s numbers', 'wpcredits-program-manager' ) );

		foreach ( $cells as $cell ) {
			printf(
				'<div class="wpcpm-figure"><p class="wpcpm-figure__value">%1$s</p><p class="wpcpm-figure__label">%2$s</p><p class="wpcpm-figure__base">%3$s</p></div>',
				esc_html( $cell['value'] ),
				esc_html( $cell['label'] ),
				esc_html( $cell['base'] )
			);
		}

		if ( ! empty( $published['read'] ) ) {
			$source = sprintf(
				/* translators: %s: the date the shares were read. */
				__( 'Shares as of %s, counted by this site from the program\'s records with the homepage\'s definitions; mentors and programs as of today.', 'wpcredits-program-manager' ),
				wp_date( (string) get_option( 'date_format' ), (int) $published['read'] )
			);
		} else {
			$source = __( 'Mentors and programs as of today, from the program\'s records.', 'wpcredits-program-manager' );
		}

		printf( '<p class="wpcpm-figures__source">%s</p>', esc_html( $source ) );
		echo '</section>';
	}

	/**
	 * The Waiting group's body: a card for each queue with something in it, its row saying how long the
	 * oldest has waited; the mentor applications as a row that opens their screen; and one sentence that
	 * names every empty queue, each name a link to its screen, so nothing seems to have gone. A card whose
	 * queue is empty is drawn after all while it holds the outcome of the decision just taken.
	 *
	 * @param array $wait What `waiting()` returned.
	 */
	private static function render_waiting( array $wait ) {
		// The card the outcome of the decision just taken is held for: `render_messages()` took the flash that
		// names it, so it is read from what it held.
		$target = (string) WPCPM_Dashboard_Card::held_anchor();
		$empty  = array();

		// Every card with work is a row, folded, that opens in place on Review (the concepts' inbox).
		WPCPM_Dashboard_Card::fold( true, __( 'Review', 'wpcredits-program-manager' ) );

		foreach ( $wait['queues'] as $queue ) {
			$held = '' !== $target && $target === $queue['anchor'];

			if ( $queue['n'] <= 0 && ! $held ) {
				$empty[] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( (string) $queue['screen'] ), esc_html( (string) $queue['title'] ) );
				continue;
			}

			if ( $queue['since'] > 0 ) {
				$days = self::days_since( (int) $queue['since'] );

				WPCPM_Dashboard_Card::age(
					$queue['anchor'],
					0 === $days
						? __( 'Waiting since today', 'wpcredits-program-manager' )
						/* translators: %s: a number of days. */
						: sprintf( _n( '%s day waiting', '%s days waiting', $days, 'wpcredits-program-manager' ), number_format_i18n( $days ) )
				);
			}

			if ( is_callable( $queue['draw'] ) ) {
				call_user_func( $queue['draw'] );
				continue;
			}

			// The mentor applications: decided on the Mentors screen, a row that opens it.
			printf(
				'<section class="wpcpm-administrator__card wpcpm-overview__row" id="%1$s"><div class="wpcpm-overview__row-in"><h3 class="wpcpm-group__title">%2$s <span class="wpcpm-group__count">%3$s</span></h3><span class="wpcpm-sponsor__state">%4$s</span><a class="wpcpm-button wpcpm-button--secondary" href="%5$s">%6$s<span class="screen-reader-text"> %7$s</span></a></div></section>',
				esc_attr( (string) $queue['anchor'] ),
				esc_html( (string) $queue['title'] ),
				esc_html( number_format_i18n( (int) $queue['n'] ) ),
				/* translators: %s: how many applications wait. */
				esc_html( sprintf( _n( '%s waiting', '%s waiting', (int) $queue['n'], 'wpcredits-program-manager' ), number_format_i18n( (int) $queue['n'] ) ) ),
				esc_url( (string) $queue['screen'] ),
				esc_html__( 'Review', 'wpcredits-program-manager' ),
				esc_html__( 'on the Mentors screen', 'wpcredits-program-manager' )
			);
		}

		WPCPM_Dashboard_Card::fold( false );

		if ( empty( $empty ) ) {
			return;
		}

		$count = count( $empty );

		printf(
			'<p class="wpcpm-overview__quiet">%s</p>',
			wp_kses(
				sprintf(
					count( $wait['queues'] ) === $count
						/* translators: 1: a number of queues, 2: their names, each a link, as a list. */
						? _n( 'Nothing waits in the %1$s queue: %2$s. Its name opens its screen in wp-admin.', 'Nothing waits in any of the %1$s queues: %2$s. Each name opens its screen in wp-admin.', $count, 'wpcredits-program-manager' )
						/* translators: 1: a number of queues, 2: their names, each a link, as a list. */
						: _n( 'Nothing waits in the %1$s other queue: %2$s. Its name opens its screen in wp-admin.', 'Nothing waits in the %1$s other queues: %2$s. Each name opens its screen in wp-admin.', $count, 'wpcredits-program-manager' ),
					number_format_i18n( $count ),
					wp_sprintf( '%l', $empty )
				),
				array( 'a' => array( 'href' => array() ) )
			)
		);
	}

	/**
	 * The Reference view: the sponsors, the programs running and the mentors' use of the site, then the
	 * Tools from our sponsors and the resources, all of which the Overview leaves out.
	 *
	 * @param array   $data   What `WPCPM_Administrators_Cards::collect()` returned.
	 * @param WP_User $viewer The Administrator.
	 */
	private static function render_reference( array $data, $viewer ) {
		echo '<header class="wpcpm-head"><div class="wpcpm-head__copy">';
		self::page_name();
		printf( '<p class="wpcpm-head__title">%s</p>', esc_html__( 'Reference', 'wpcredits-program-manager' ) );
		printf( '<p class="wpcpm-head__lede">%s</p>', esc_html__( 'The sponsors, the programs running and how the mentors use the site, then the tools from our sponsors and the resources.', 'wpcredits-program-manager' ) );
		echo '</div></header>';

		self::render_messages();
		echo WPCPM_Dashboard_Card::SLOT; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed comment.

		echo '<div class="wpcpm-reference">';

		WPCPM_Dashboard_Card::group(
			array_merge(
				self::GROUP,
				array(
					'id'    => 'reference',
					'title' => __( 'The program\'s numbers', 'wpcredits-program-manager' ),
					'lead'  => __( 'Read from the site and Airtable.', 'wpcredits-program-manager' ),
					'cards' => static function () use ( $data ) {
						WPCPM_Administrators_Cards::render_sponsors_strip( isset( $data['sponsors'] ) ? (array) $data['sponsors'] : array() );
						WPCPM_Administrators_Cards::render_programs( $data['programs'] );
						WPCPM_Administrators_Cards::render_mentor_engagement( isset( $data['mentor_engagement'] ) ? (array) $data['mentor_engagement'] : array() );
					},
				)
			)
		);

		echo '</div>';

		// The group's heading is an h2, so the Tools section's and the resources' h3 headings would read as
		// the group's in an outline: one h2 over both, for a screen reader alone. The two are drawn first,
		// into a buffer, so the heading is printed only over something.
		ob_start();

		// Every live offer with its audience, for the program (spec 6.5). No switch for managers.
		if ( class_exists( 'WPCPM_Sponsor_Tools' ) ) {
			WPCPM_Sponsor_Tools::render( WPCPM_Sponsor_Tools::AUDIENCE_MANAGERS, $viewer );
		}

		self::render_help();

		$after = (string) ob_get_clean();

		if ( '' !== trim( $after ) ) {
			printf( '<h2 class="screen-reader-text">%s</h2>', esc_html__( 'Tools and resources', 'wpcredits-program-manager' ) );
			echo $after; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The Tools section's and the resources' own markup, each escaping what it prints, held until the page knew it had any.
		}
	}

	/**
	 * What the last decision left to say, in the words the wp-admin queue would use.
	 *
	 * Every handler this page posts to flashes on the Institutions screen's channel, so the
	 * message maps of those handlers are merged here, the sponsor agreement's under its own prefix,
	 * since its keys are the institution agreement's. Taken once, so it shows
	 * once, here or there. Draft now (WPCPM_Semester_Report_Screen::ACTION_DRAFT) is the one
	 * decision this page posts that flashes somewhere else, on its own screen's channel, so it
	 * is read separately below rather than merged into this map (since 1.92.0, decision 2 of
	 * the final review: a successful draft never flashes here at all, because it leaves this
	 * page for the editor; only a refusal does). The Sponsor reports card's Draft now (1.115.0)
	 * is not that one: its handler flashes a refusal on this channel when the press came from
	 * this page, so the Sponsors screen's report words are merged into this map, and a draft
	 * that is made leaves for its editor, as the semester report's does. The Events
	 * card's three decisions (1.117.0) are posted from this page alone and flash on this
	 * channel whatever they decide, so their words are merged too; a stale nonce or a missing
	 * capability stops on WordPress's own screen before any decision, and flashes nothing.
	 */
	private static function render_messages() {
		$status = sanitize_key( (string) WPCPM_Flash::take( WPCPM_Institutions::FLASH ) );
		$card   = WPCPM_Dashboard_Card::targeted();
		$held   = false;

		if ( '' !== $status ) {
			$messages = array();

			if ( method_exists( 'WPCPM_Institutions', 'queue_messages' ) ) {
				$messages = array_merge( $messages, (array) WPCPM_Institutions::queue_messages() );
			}

			if ( class_exists( 'WPCPM_Institution_Panel' ) && method_exists( 'WPCPM_Institution_Panel', 'messages' ) ) {
				$messages = array_merge( $messages, (array) WPCPM_Institution_Panel::messages() );
			}

			if ( class_exists( 'WPCPM_Institution_Request' ) && method_exists( 'WPCPM_Institution_Request', 'messages' ) ) {
				$messages = array_merge( $messages, (array) WPCPM_Institution_Request::messages() );
			}

			if ( class_exists( 'WPCPM_Sponsor_Posts' ) && method_exists( 'WPCPM_Sponsor_Posts', 'messages' ) ) {
				$messages = array_merge( $messages, (array) WPCPM_Sponsor_Posts::messages() );
			}

			if ( class_exists( 'WPCPM_Sponsor_Offer_Review' ) && method_exists( 'WPCPM_Sponsor_Offer_Review', 'messages' ) ) {
				$messages = array_merge( $messages, (array) WPCPM_Sponsor_Offer_Review::messages() );
			}

			// The sponsor agreement's keys are the institution agreement's too, each worded for its
			// own audience, so its outcomes come back here under its prefix and its sentences are
			// read under it: neither audience is told the other's words.
			if ( class_exists( 'WPCPM_Sponsor_Agreement' ) && method_exists( 'WPCPM_Sponsor_Agreement', 'manager_messages' ) ) {
				foreach ( (array) WPCPM_Sponsor_Agreement::manager_messages() as $key => $message ) {
					$messages[ WPCPM_Sponsor_Agreement::DASHBOARD_PREFIX . $key ] = $message;
				}
			}

			if ( class_exists( 'WPCPM_Sponsor_Application' ) && method_exists( 'WPCPM_Sponsor_Application', 'manager_messages' ) ) {
				$messages = array_merge( $messages, (array) WPCPM_Sponsor_Application::manager_messages() );
			}

			if ( class_exists( 'WPCPM_Sponsor_Report_Screen' ) && method_exists( 'WPCPM_Sponsor_Report_Screen', 'messages' ) ) {
				$messages = array_merge( $messages, (array) WPCPM_Sponsor_Report_Screen::messages() );
			}

			if ( class_exists( 'WPCPM_Sponsor_Event_Queue' ) && method_exists( 'WPCPM_Sponsor_Event_Queue', 'messages' ) ) {
				$messages = array_merge( $messages, (array) WPCPM_Sponsor_Event_Queue::messages() );
			}

			if ( class_exists( 'WPCPM_Sync_Module' ) && method_exists( 'WPCPM_Sync_Module', 'sync_messages' ) ) {
				$messages = array_merge( $messages, (array) WPCPM_Sync_Module::sync_messages() );
			}

			if ( isset( $messages[ $status ] ) && is_array( $messages[ $status ] ) ) {
				$sentence = (string) $messages[ $status ][1];

				// The sponsor application queue's `sapp-account` sentence names what the site
				// said when the account step failed, and that detail is one-shot: it is read
				// here, on the one status being printed, and never inside the map, which
				// anything that wants a sentence builds.
				if ( class_exists( 'WPCPM_Sponsor_Application' ) && method_exists( 'WPCPM_Sponsor_Application', 'sentence_for' ) ) {
					$sentence = (string) WPCPM_Sponsor_Application::sentence_for( $status, $sentence );
				}

				WPCPM_Dashboard_Card::hold( $card, (string) $messages[ $status ][0], $sentence );

				$held = '' !== trim( $sentence );
			}
		}

		self::render_report_message( $card, $held );
	}

	/**
	 * What a refused Draft now left to say, read on the semester report screen's own flash
	 * channel and in its own words: `message_for()` is the one place that decides what a
	 * status there means, and this page must not spell the same refusal differently.
	 *
	 * @param string $card The card the handler named, or ''.
	 * @param bool   $held Whether a status of the dashboard's own channel is held already, which the
	 *                     refusal is said after.
	 */
	private static function render_report_message( $card, $held ) {
		if ( ! class_exists( 'WPCPM_Semester_Report_Screen' ) || ! method_exists( 'WPCPM_Semester_Report_Screen', 'message_for' ) ) {
			return;
		}

		$flash = WPCPM_Flash::take( WPCPM_Semester_Report_Screen::FLASH );

		if ( ! is_array( $flash ) || empty( $flash['status'] ) ) {
			return;
		}

		list( , $text ) = WPCPM_Semester_Report_Screen::message_for( $flash );

		if ( '' === $text ) {
			return;
		}

		// Only bounce() honors the return field, so every report message that lands here is a
		// refusal: a successful Draft now opens the new draft in the editor instead. It is held as an
		// error, for the card it came from, or said after the status of the page's own channel held
		// already, as both were printed before the outcomes moved into the cards (the fix wave of
		// 1.123.58).
		if ( $held ) {
			WPCPM_Dashboard_Card::also( $card, 'error', $text );
		} else {
			WPCPM_Dashboard_Card::hold( $card, 'error', $text );
		}
	}

	/**
	 * The Updates column, the program manager guide and the way into wp-admin, last on the page.
	 */
	private static function render_help() {
		if ( ! class_exists( 'WPCPM_Handbook_Assistant' ) || ! method_exists( 'WPCPM_Handbook_Assistant', 'render_resources' ) ) {
			return;
		}

		echo WPCPM_Handbook_Assistant::render_resources( 'administrator' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_resources() escapes every value it interpolates.
	}

	/**
	 * One sentence in the dashboard's shell, for the viewer who gets no page, with an optional
	 * button - the shape WPCPM_Institutions_Dashboard::notice() also draws for its own
	 * logged-out visitor.
	 *
	 * @param string $message     Escaped text, or text with the one link `nothing_to_show()` adds.
	 * @param string $action_url  Optional action URL.
	 * @param string $action_text Optional action label.
	 * @return string
	 */
	private static function notice( $message, $action_url = '', $action_text = '' ) {
		$html = '<div class="wpcpm-dashboard wpcpm-dashboard--notice"><p>' . wp_kses( (string) $message, array( 'a' => array( 'href' => array() ) ) ) . '</p>';

		if ( $action_url && $action_text ) {
			$html .= '<p><a class="wpcpm-button" href="' . esc_url( $action_url ) . '">' . esc_html( $action_text ) . '</a></p>';
		}

		return $html . '</div>';
	}
}
