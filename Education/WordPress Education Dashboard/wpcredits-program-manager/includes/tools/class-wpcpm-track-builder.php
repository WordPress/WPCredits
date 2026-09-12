<?php
/**
 * Tools - the Track Builder.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Track Builder screen: every program track, what state it is in, and what to do about it.
 *
 * A tool rather than a module, so the Modules menu lists it beside the others, and its handlers do
 * their own capability and nonce check in that order rather than borrowing `WPCPM_Sync_Module`'s,
 * which serves modules (the design's decision 3.9). The rows it draws come from the store and the
 * students sync; nothing here reads Airtable, which is what lets this release ship without the
 * schema token that T2c needs.
 */
class WPCPM_Track_Builder extends WPCPM_Tool {

	/** Save a track's properties. */
	const ACTION_SAVE = 'wpcpm_track_save';

	/** Copy a track into a new one. */
	const ACTION_DUPLICATE = 'wpcpm_track_duplicate';

	/** Run a built-in track from its definition. */
	const ACTION_SWITCH_DEFINITION = 'wpcpm_track_switch_definition';

	/** Run a switched track from its hand-written form again. */
	const ACTION_SWITCH_BUILTIN = 'wpcpm_track_switch_builtin';

	/** Put a built-in draft back to the seed the plugin ships. */
	const ACTION_REFRESH = 'wpcpm_track_refresh';

	/** Publish a track: create its columns, then put it live. */
	const ACTION_PUBLISH = 'wpcpm_track_publish';

	/** Take a published track off the live site. */
	const ACTION_UNPUBLISH = 'wpcpm_track_unpublish';

	/** Read the base and say whether a live track still matches it. */
	const ACTION_VERIFY = 'wpcpm_track_verify';

	/** Tick one checklist item. */
	const ACTION_TICK = 'wpcpm_track_tick';

	/** Take a tick back. */
	const ACTION_UNTICK = 'wpcpm_track_untick';

	/** Flash channel for this screen's outcomes. */
	const FLASH = 'track-builder';

	/**
	 * Tool identifier.
	 *
	 * @return string
	 */
	public function id() {
		return 'track-builder';
	}

	/**
	 * Human-readable tool name.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Track Builder', 'wpcredits-program-manager' );
	}

	/**
	 * One-line description for the Modules screen.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Every program track: what state it is in, how many students are on it, and what the last compile left out.', 'wpcredits-program-manager' );
	}

	/**
	 * The list reads the site's own tracks, so it works with no Airtable connection at all.
	 *
	 * @return bool
	 */
	public function is_ready() {
		return true;
	}

	/**
	 * How many tracks there are, and how many the live site runs.
	 *
	 * Reads the store directly rather than through `self::rows()`: a row also asks the students
	 * sync for a count per track, which scans every provisioned student, and this runs for every
	 * registered tool on both the Modules screen and the plugin's own screen, on every load (the
	 * Task 5 review). The state is all a status line needs.
	 *
	 * @return string
	 */
	public function status_line() {
		$ids   = WPCPM_Track_Store::all_ids();
		$total = count( $ids );
		$live  = 0;

		foreach ( $ids as $post_id ) {
			$state = WPCPM_Track_Store::state( $post_id );

			if ( 'published' === $state || 'changed' === $state ) {
				++$live;
			}
		}

		return sprintf(
			/* translators: 1: how many tracks exist, 2: how many of them are published. */
			_n( '%1$s track, %2$s published.', '%1$s tracks, %2$s published.', $total, 'wpcredits-program-manager' ),
			number_format_i18n( $total ),
			number_format_i18n( $live )
		);
	}

	/**
	 * Hooks.
	 */
	public function boot() {
		add_action( 'admin_post_' . self::ACTION_SAVE, array( $this, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_DUPLICATE, array( $this, 'handle_duplicate' ) );
		add_action( 'admin_post_' . self::ACTION_SWITCH_DEFINITION, array( $this, 'handle_switch_definition' ) );
		add_action( 'admin_post_' . self::ACTION_SWITCH_BUILTIN, array( $this, 'handle_switch_builtin' ) );
		add_action( 'admin_post_' . self::ACTION_REFRESH, array( $this, 'handle_refresh' ) );
		add_action( 'admin_post_' . self::ACTION_PUBLISH, array( $this, 'handle_publish' ) );
		add_action( 'admin_post_' . self::ACTION_UNPUBLISH, array( $this, 'handle_unpublish' ) );
		add_action( 'admin_post_' . self::ACTION_VERIFY, array( $this, 'handle_verify' ) );
		add_action( 'admin_post_' . self::ACTION_TICK, array( $this, 'handle_tick' ) );
		add_action( 'admin_post_' . self::ACTION_UNTICK, array( $this, 'handle_untick' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * The screen's own stylesheet, on this screen only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, $this->page_slug() ) ) {
			return;
		}

		wp_enqueue_style( 'wpcpm-track-builder', WPCPM_PLUGIN_URL . 'assets/css/track-builder.css', array( 'wpcpm-admin' ), WPCPM_VERSION );
	}

	/**
	 * One row per track, in the order the tracks were made.
	 *
	 * Everything the list prints, read once here so the screen asks nothing of the store while it
	 * draws: the state, who published it last, how many students hold its status, what the last
	 * compile left out, how its definition compares with its PHP, and whether a built-in draft has
	 * fallen behind the seed the plugin now ships (the design's decision 12).
	 *
	 * @return array[]
	 */
	public static function rows() {
		$skipped = get_option( WPCPM_Track_Store::OPT_SKIPPED, array() );
		$skipped = is_array( $skipped ) ? $skipped : array();
		$seeds   = WPCPM_Track_Store::seeds();
		$rows    = array();

		foreach ( WPCPM_Track_Store::all_ids() as $post_id ) {
			$post_id    = (int) $post_id;
			$definition = WPCPM_Track_Store::get( $post_id );

			if ( ! is_array( $definition ) ) {
				continue;
			}

			$key       = isset( $definition['key'] ) ? (string) $definition['key'] : '';
			$status    = isset( $definition['status'] ) ? (string) $definition['status'] : '';
			$source    = WPCPM_Track_Store::source( $post_id );
			$published = WPCPM_Track_Store::published( $post_id );
			$last      = self::last_publish( WPCPM_Track_Store::log_entries( $post_id ) );

			$rows[] = array(
				'id'           => $post_id,
				'label'        => isset( $definition['label'] ) ? (string) $definition['label'] : '',
				'status'       => $status,
				'key'          => $key,
				'course'       => isset( $definition['course_url'] ) ? (string) $definition['course_url'] : '',
				'source'       => $source,
				'state'        => WPCPM_Track_Store::state( $post_id ),
				'students'     => WPCPM_Students_Sync::count_on_status( $status ),
				'published_by' => (int) $last['by'],
				'published_at' => (int) $last['at'],
				'skipped'      => isset( $skipped[ $post_id ] ) ? (array) $skipped[ $post_id ] : array(),
				'equivalence'  => WPCPM_Track_Store::equivalence( $post_id ),
				'switched'     => WPCPM_Track_Store::switched( $post_id ),
				'stale'        => 'builtin' === $source && ! is_array( $published ) && isset( $seeds[ $key ] ) && $seeds[ $key ] !== $definition,
			);
		}

		return $rows;
	}

	/**
	 * A track's properties, as the form edits them.
	 *
	 * The questions are T3's, so this is what a track *is* rather than what it asks. A built-in
	 * track its PHP still runs is read-only: its equivalence with that PHP is what the switch
	 * rests on (spec section 6), and the store refuses the save in any case.
	 *
	 * `learn_course_id` and `hours_target` default to an empty string, not zero: a track may have
	 * no hours target at all (a standing product decision), and a field that rendered "0" for
	 * "no target" would save back as an explicit zero the moment somebody pressed Save without
	 * touching it - turning a published track into one with unpublished changes for nothing it
	 * asked for (the Task 6 review).
	 *
	 * @param int $post_id The track.
	 * @return array
	 */
	public static function form( $post_id ) {
		$post_id    = (int) $post_id;
		$definition = WPCPM_Track_Store::get( $post_id );
		$definition = is_array( $definition ) ? $definition : array();

		return array(
			'id'              => $post_id,
			'label'           => isset( $definition['label'] ) ? (string) $definition['label'] : '',
			'status'          => isset( $definition['status'] ) ? (string) $definition['status'] : '',
			'key'             => isset( $definition['key'] ) ? (string) $definition['key'] : '',
			'course_url'      => isset( $definition['course_url'] ) ? (string) $definition['course_url'] : '',
			'learn_course_id' => isset( $definition['learn_course_id'] ) ? (int) $definition['learn_course_id'] : '',
			'hours_target'    => isset( $definition['hours_target'] ) ? (int) $definition['hours_target'] : '',
			'hue'             => isset( $definition['hue'] ) ? (string) $definition['hue'] : '',
			'read_only'       => 'builtin' === WPCPM_Track_Store::source( $post_id ),
		);
	}

	/**
	 * What a copy of a track needs of its own: the three things two tracks may never share.
	 *
	 * @param int $post_id The track being copied.
	 * @return array
	 */
	public static function duplicate_form( $post_id ) {
		$definition = WPCPM_Track_Store::get( $post_id );
		$label      = is_array( $definition ) && isset( $definition['label'] ) ? (string) $definition['label'] : '';

		return array(
			'id'     => (int) $post_id,
			'from'   => $label,
			'label'  => '' === $label ? '' : sprintf(
				/* translators: %s: the name of the track being copied. */
				__( '%s copy', 'wpcredits-program-manager' ),
				$label
			),
			'status' => '',
			'key'    => '',
		);
	}

	/**
	 * Render the screen: a copy being started, one track's properties, or the list.
	 */
	public function render_admin_page() {
		$this->require_manager();

		$track     = WPCPM_Request::id( 'wpcpm_track' );
		$duplicate = WPCPM_Request::id( 'wpcpm_duplicate' );
		$flash     = (array) WPCPM_Flash::take( self::FLASH );

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html( $this->label() ) . '</h1>';

		if ( $duplicate > 0 && is_array( WPCPM_Track_Store::get( $duplicate ) ) ) {
			WPCPM_Track_Builder_Screen::render_duplicate(
				array(
					'form'  => self::duplicate_form( $duplicate ),
					'url'   => $this->admin_url(),
					'flash' => $flash,
				)
			);

			echo '</div>';

			return;
		}

		$publish = WPCPM_Request::id( 'wpcpm_publish' );

		if ( $publish > 0 && is_array( WPCPM_Track_Store::get( $publish ) ) ) {
			$held = WPCPM_Track_Store::get( $publish );

			WPCPM_Track_Builder_Screen::render_publish(
				array(
					'track'     => $publish,
					'label'     => isset( $held['label'] ) ? (string) $held['label'] : '',
					'state'     => WPCPM_Track_Store::state( $publish ),
					'preflight' => WPCPM_Track_Publish::preflight( $publish ),
					'checklist' => WPCPM_Track_Publish::checklist( $publish ),
					'can_make'  => WPCPM_Settings::has_schema_token(),
					'url'       => $this->admin_url(),
					'flash'     => $flash,
				)
			);

			echo '</div>';

			return;
		}

		if ( $track > 0 && is_array( WPCPM_Track_Store::get( $track ) ) ) {
			WPCPM_Track_Builder_Screen::render_form(
				array(
					'form'  => self::form( $track ),
					'url'   => $this->admin_url(),
					'flash' => $flash,
				)
			);

			echo '</div>';

			return;
		}

		echo '<p class="wpcpm-lede">' . esc_html( $this->description() ) . '</p>';

		WPCPM_Track_Builder_Screen::render_list(
			array(
				'rows'  => self::rows(),
				'url'   => $this->admin_url(),
				'flash' => $flash,
			)
		);

		echo '</div>';
	}

	/**
	 * Save a track's properties.
	 */
	public function handle_save() {
		$this->verify( self::ACTION_SAVE );

		$post_id = WPCPM_Request::posted_id( 'track' );
		$stored  = WPCPM_Track_Store::get( $post_id );

		if ( ! is_array( $stored ) ) {
			$this->redirect_back(
				array(
					'status'  => 'error',
					'message' => __( 'That track does not exist.', 'wpcredits-program-manager' ),
				)
			);
		}

		$definition = self::posted_definition( $stored );
		$errors     = WPCPM_Track_Store::check( $post_id, $definition );

		if ( array() !== $errors ) {
			$this->refuse( $post_id, (string) $errors[0]['message'], $definition );
		}

		$saved = WPCPM_Track_Store::save( $post_id, $definition );

		if ( is_wp_error( $saved ) ) {
			$this->refuse( $post_id, $saved->get_error_message(), $definition );
		}

		$this->redirect_back(
			array(
				'status'  => 'success',
				'message' => __( 'The track was saved. Nothing reaches students until it is published.', 'wpcredits-program-manager' ),
			),
			array( 'wpcpm_track' => $post_id )
		);
	}

	/**
	 * Copy a track.
	 */
	public function handle_duplicate() {
		$this->verify( self::ACTION_DUPLICATE );

		$from   = WPCPM_Request::posted_id( 'track' );
		$stored = WPCPM_Track_Store::get( $from );

		if ( ! is_array( $stored ) ) {
			$this->redirect_back(
				array(
					'status'  => 'error',
					'message' => __( 'That track does not exist.', 'wpcredits-program-manager' ),
				)
			);
		}

		$definition           = $stored;
		$definition['label']  = WPCPM_Request::posted_text( 'wpcpm_label' );
		$definition['status'] = WPCPM_Request::posted_text( 'wpcpm_status' );
		$definition['key']    = WPCPM_Request::posted_text( 'wpcpm_key' );

		// Checked as a track with nothing locked to it, which is what a copy is, so a status
		// another track already holds is refused before a draft nobody asked for exists.
		$errors = WPCPM_Track_Store::check( 0, $definition );

		if ( array() !== $errors ) {
			$this->redirect_back(
				array(
					'status'  => 'error',
					'message' => (string) $errors[0]['message'],
					'values'  => $definition,
				),
				array( 'wpcpm_duplicate' => $from )
			);
		}

		$copy = WPCPM_Track_Store::duplicate( $from, $definition );

		if ( is_wp_error( $copy ) ) {
			$this->redirect_back(
				array(
					'status'  => 'error',
					'message' => $copy->get_error_message(),
					'values'  => $definition,
				),
				array( 'wpcpm_duplicate' => $from )
			);
		}

		$this->redirect_back(
			array(
				'status'  => 'success',
				'message' => __( 'The copy was made, as a draft. Nothing reaches students until it is published.', 'wpcredits-program-manager' ),
			),
			array( 'wpcpm_track' => (int) $copy )
		);
	}

	/**
	 * The posted properties, on top of the definition the track holds.
	 *
	 * The questions travel untouched, because this form does not show them: an editor that rebuilt
	 * the definition from its fields alone would drop every question the moment somebody renamed a
	 * track.
	 *
	 * @param array $stored The definition as it is stored.
	 * @return array
	 */
	private static function posted_definition( array $stored ) {
		$definition           = $stored;
		$definition['label']  = WPCPM_Request::posted_text( 'wpcpm_label' );
		$definition['status'] = WPCPM_Request::posted_text( 'wpcpm_status' );
		$definition['key']    = WPCPM_Request::posted_text( 'wpcpm_key' );
		$course               = WPCPM_Request::posted_text( 'wpcpm_course_url' );
		$learn                = (int) WPCPM_Request::posted_text( 'wpcpm_learn_course_id' );
		$hours                = WPCPM_Request::posted_text( 'wpcpm_hours_target' );
		$hue                  = WPCPM_Request::posted_text( 'wpcpm_hue' );

		// An empty course url is "no course," the same as no hours target and no Learn course ID
		// below: writing '' here would give a track that never had one a key it did not have (the
		// final review).
		if ( '' === $course ) {
			unset( $definition['course_url'] );
		} else {
			$definition['course_url'] = esc_url_raw( $course );
		}

		if ( $learn > 0 ) {
			$definition['learn_course_id'] = $learn;
		} else {
			unset( $definition['learn_course_id'] );
		}

		// A track may have no hours target at all, which is the Developer Track's answer, so an
		// empty field means no target rather than zero.
		if ( '' === $hours ) {
			unset( $definition['hours_target'] );
		} else {
			$definition['hours_target'] = (int) $hours;
		}

		if ( WPCPM_Track_Palette::is_hue( $hue ) ) {
			$definition['hue'] = $hue;
		}

		return $definition;
	}

	/**
	 * Back to the form with the refusal and what the person typed, so nothing has to be retyped.
	 *
	 * @param int    $post_id    The track.
	 * @param string $message    Why it was refused.
	 * @param array  $definition What was posted.
	 */
	private function refuse( $post_id, $message, array $definition ) {
		$this->redirect_back(
			array(
				'status'  => 'error',
				'message' => $message,
				'values'  => $definition,
			),
			array( 'wpcpm_track' => (int) $post_id )
		);
	}

	/**
	 * Run a built-in track from its definition.
	 */
	public function handle_switch_definition() {
		$this->verify( self::ACTION_SWITCH_DEFINITION );

		$this->report(
			WPCPM_Track_Store::switch_to_definition( WPCPM_Request::posted_id( 'track' ) ),
			__( 'That track now runs from its definition. What students see has not changed, which is what let it switch.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * Run a switched track from its hand-written form again.
	 */
	public function handle_switch_builtin() {
		$this->verify( self::ACTION_SWITCH_BUILTIN );

		$this->report(
			WPCPM_Track_Store::switch_to_builtin( WPCPM_Request::posted_id( 'track' ) ),
			__( 'That track runs from its hand-written form again.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * Flash what the store answered and go back to the list.
	 *
	 * @param int|WP_Error $result  What the store answered.
	 * @param string       $message What to say when it worked.
	 */
	private function report( $result, $message ) {
		if ( is_wp_error( $result ) ) {
			$this->redirect_back(
				array(
					'status'  => 'error',
					'message' => $result->get_error_message(),
				)
			);
		}

		$this->redirect_back(
			array(
				'status'  => 'success',
				'message' => $message,
			)
		);
	}

	/**
	 * Put one built-in draft back to the seed the plugin ships.
	 */
	public function handle_refresh() {
		$this->verify( self::ACTION_REFRESH );

		$result = WPCPM_Track_Store::refresh_builtin( WPCPM_Request::posted_id( 'track' ) );

		if ( is_wp_error( $result ) ) {
			$this->redirect_back(
				array(
					'status'  => 'error',
					'message' => $result->get_error_message(),
				)
			);
		}

		$this->redirect_back(
			array(
				'status'  => 'success',
				'message' => __( 'The track was refreshed from the version this plugin ships.', 'wpcredits-program-manager' ),
			)
		);
	}

	/**
	 * When a track was last published, and by whom.
	 *
	 * @param array[] $entries The track's log, oldest first.
	 * @return array `at` and `by`, both 0 when it has never been published.
	 */
	private static function last_publish( array $entries ) {
		$last = array(
			'at' => 0,
			'by' => 0,
		);

		foreach ( $entries as $entry ) {
			if ( isset( $entry['did'] ) && 'publish' === $entry['did'] ) {
				$last = array(
					'at' => isset( $entry['at'] ) ? (int) $entry['at'] : 0,
					'by' => isset( $entry['by'] ) ? (int) $entry['by'] : 0,
				);
			}
		}

		return $last;
	}

	/**
	 * Publish a track from the screen.
	 */
	public function handle_publish() {
		$this->verify( self::ACTION_PUBLISH );

		$track = WPCPM_Request::posted_id( 'track' );
		$done  = WPCPM_Track_Publish::run( $track, get_current_user_id() );

		if ( is_wp_error( $done ) ) {
			$this->redirect_back(
				array(
					'status'  => 'error',
					'message' => $done->get_error_message(),
				),
				array( 'wpcpm_publish' => $track )
			);
		}

		$made = count( $done['created'] );

		$this->redirect_back(
			array(
				'status'  => 'success',
				'message' => 0 === $made
					? __( 'The track is live. Nothing had to be created in Airtable: every column was already there.', 'wpcredits-program-manager' )
					: sprintf(
						/* translators: %d: how many columns were created. */
						_n( 'The track is live, and %d column was created in Airtable.', 'The track is live, and %d columns were created in Airtable.', $made, 'wpcredits-program-manager' ),
						$made
					),
			),
			array( 'wpcpm_publish' => $track )
		);
	}

	/**
	 * Take a track off the live site.
	 */
	public function handle_unpublish() {
		$this->verify( self::ACTION_UNPUBLISH );

		$track = WPCPM_Request::posted_id( 'track' );

		$this->report(
			WPCPM_Track_Publish::take_down( $track, get_current_user_id() ),
			__( 'The track is a draft again. Nothing was changed in Airtable, and its status is still in "Currently mentoring".', 'wpcredits-program-manager' )
		);
	}

	/**
	 * Check a live track against the base.
	 */
	public function handle_verify() {
		$this->verify( self::ACTION_VERIFY );

		$track = WPCPM_Request::posted_id( 'track' );
		$seen  = WPCPM_Track_Publish::verify( $track );

		if ( is_wp_error( $seen ) ) {
			$this->redirect_back(
				array(
					'status'  => 'error',
					'message' => $seen->get_error_message(),
				),
				array( 'wpcpm_publish' => $track )
			);
		}

		$this->redirect_back( self::verified( $seen ), array( 'wpcpm_publish' => $track ) );
	}

	/**
	 * What a verify found, as a notice.
	 *
	 * @param array $seen What `WPCPM_Track_Publish::verify()` answered.
	 * @return array
	 */
	private static function verified( array $seen ) {
		$trouble = array_merge( $seen['columns']['missing'], $seen['columns']['wrong'] );

		if ( array() !== $trouble ) {
			return array(
				'status'  => 'error',
				'message' => sprintf(
					/* translators: %s: a list of column names. */
					__( 'These columns are missing from the base, or are no longer the type this track needs: %s. A column renamed in Airtable takes its answers with it, so students would be writing into nothing.', 'wpcredits-program-manager' ),
					implode( ', ', $trouble )
				),
			);
		}

		if ( 'ok' !== $seen['choices']['reports'] || 'ok' !== $seen['choices']['students'] ) {
			return array(
				'status'  => 'error',
				'message' => __( 'The track\'s status is not a choice on both tables, so students on it are not synced. "Add the two Status choices" is the checklist item to do.', 'wpcredits-program-manager' ),
			);
		}

		return array(
			'status'  => 'success',
			'message' => __( 'Every column this track writes to is still in the base, with the type it needs, and its status is a choice on both tables.', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * Tick one checklist item.
	 */
	public function handle_tick() {
		$this->verify( self::ACTION_TICK );

		$this->ticked( true );
	}

	/**
	 * Take one tick back.
	 */
	public function handle_untick() {
		$this->verify( self::ACTION_UNTICK );

		$this->ticked( false );
	}

	/**
	 * Record a tick either way, and say so.
	 *
	 * @param bool $on Whether it is now ticked.
	 * @return void
	 */
	private function ticked( $on ) {
		$track = WPCPM_Request::posted_id( 'track' );
		$item  = WPCPM_Request::posted_text( 'item' );
		$done  = $on
			? WPCPM_Track_Publish::tick( $track, $item, get_current_user_id() )
			: WPCPM_Track_Publish::untick( $track, $item, get_current_user_id() );

		if ( is_wp_error( $done ) ) {
			$this->redirect_back(
				array(
					'status'  => 'error',
					'message' => $done->get_error_message(),
				),
				array( 'wpcpm_publish' => $track )
			);
		}

		$this->redirect_back(
			array(
				'status'  => 'success',
				'message' => $on
					? __( 'Ticked, and recorded against your name.', 'wpcredits-program-manager' )
					: __( 'The tick is taken back.', 'wpcredits-program-manager' ),
			),
			array( 'wpcpm_publish' => $track )
		);
	}

	/**
	 * The capability, then the nonce, before a handler does anything.
	 *
	 * @param string $action The action being verified.
	 */
	private function verify( $action ) {
		$this->require_manager();

		check_admin_referer( $action );
	}

	/**
	 * Refuse anybody who does not manage the program.
	 */
	private function require_manager() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}
	}

	/**
	 * Back to the screen, with what happened flashed for the person who pressed.
	 *
	 * @param array $outcome `status`, `message`, and `values` when a form has to come back.
	 * @param array $args    Query arguments naming the screen to come back to; none for the list.
	 */
	private function redirect_back( array $outcome, array $args = array() ) {
		$url = $this->admin_url();

		foreach ( $args as $key => $value ) {
			$url = add_query_arg( $key, $value, $url );
		}

		WPCPM_Flash::set( self::FLASH, $outcome );
		wp_safe_redirect( class_exists( 'WPCPM_Return' ) ? WPCPM_Return::url( $url ) : $url );
		exit;
	}
}
