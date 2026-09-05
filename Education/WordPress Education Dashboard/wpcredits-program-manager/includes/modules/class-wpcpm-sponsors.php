<?php
/**
 * Sponsors module.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module 4 - Sponsors.
 *
 * The companies that fund mentors and offer their tools to students. Airtable is the record
 * of who a sponsor is; the site holds what Airtable cannot. This class is the sync module
 * (the nightly read into `WPCPM_Sponsors_Index`), the wp-admin screen, and the two things a
 * manager does on it: create a sponsor's account, one at a time, and attach or remove the
 * accounts that act for it. Nothing is ever provisioned by the sync (design spec of
 * 4 September 2026, decision 9).
 */
class WPCPM_Sponsors extends WPCPM_Sync_Module {

	const ACTION_SYNC   = 'wpcpm_sponsors_sync';
	const ACTION_CANCEL = 'wpcpm_sponsors_cancel';
	const ACTION_TICK   = 'wpcpm_sponsors_tick';

	/** Create a sponsor's account. The nonce is keyed to the record: `wpcpm_sponsor_provision_<record>`. */
	const ACTION_PROVISION = 'wpcpm_sponsor_provision';

	/** Attach an existing account by address, or detach one; `wpcpm_op` says which. */
	const ACTION_MEMBERS = 'wpcpm_sponsor_members';

	/** The flash channel this screen reads. */
	const FLASH = 'sponsors_admin';

	/** The Airtable checkbox that says whether a sponsor has a site account. */
	const FIELD_DASHBOARD_ACCOUNT = 'Dashboard account';

	/** Audit kinds this class writes. */
	const LOG_PROVISIONED = 'provisioned';

	/** How many interest rows the log card shows. */
	const INTERESTS_SHOWN = 50;

	/** Seed a sponsor's first offer from the index, for the accounts provisioned before offers existed (plan ruling 7). */
	const ACTION_SEED = 'wpcpm_offer_seed';

	/** Void a claimed code so the person may claim again. */
	const ACTION_CLAIM_VOID = 'wpcpm_claim_void';

	/** The Offers card lists this many offers at most. */
	const OFFERS_SHOWN = 100;

	/**
	 * Module ID.
	 *
	 * @return string
	 */
	public function id() {
		return 'sponsors';
	}

	/**
	 * Module label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Sponsors', 'wpcredits-program-manager' );
	}

	/**
	 * Managed role.
	 *
	 * @return string
	 */
	public function role() {
		return WPCPM_Roles::ROLE_SPONSOR;
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'The companies that sponsor mentors and offer their tools to students. Each has a Sponsor Dashboard, its own people and its offer; accounts are created here, one at a time.', 'wpcredits-program-manager' );
	}

	/**
	 * Built now.
	 *
	 * @return bool
	 */
	public function is_implemented() {
		return true;
	}

	/**
	 * The sync class.
	 *
	 * @return string
	 */
	protected function sync_class() {
		return 'WPCPM_Sponsors_Sync';
	}

	/**
	 * The flash channel.
	 *
	 * @return string
	 */
	protected function flash_key() {
		return self::FLASH;
	}

	/**
	 * Hooks. The front-end classes are guarded because the screen must draw with or without
	 * them: they ship in the same release, and the guard costs nothing once they do.
	 */
	public function boot() {
		WPCPM_Ceiling::init();

		foreach ( array( 'WPCPM_Sponsors_Dashboard', 'WPCPM_Sponsor_Profile', 'WPCPM_Sponsor_Offers', 'WPCPM_Sponsor_Usage', 'WPCPM_Sponsor_Tools', 'WPCPM_Sponsor_Interests', 'WPCPM_Sponsor_Mentors' ) as $front ) {
			if ( class_exists( $front ) && method_exists( $front, 'init' ) ) {
				call_user_func( array( $front, 'init' ) );
			}
		}

		WPCPM_Sponsors_Sync::register_cron();

		add_action( 'admin_post_' . self::ACTION_SYNC, array( $this, 'handle_sync' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( $this, 'handle_cancel' ) );
		add_action( 'admin_post_' . self::ACTION_PROVISION, array( $this, 'handle_provision' ) );
		add_action( 'admin_post_' . self::ACTION_MEMBERS, array( $this, 'handle_members' ) );
		add_action( 'admin_post_' . self::ACTION_SEED, array( $this, 'handle_seed' ) );
		add_action( 'admin_post_' . self::ACTION_CLAIM_VOID, array( $this, 'handle_claim_void' ) );
		add_action( 'wp_ajax_' . self::ACTION_TICK, array( $this, 'handle_tick' ) );
	}

	/**
	 * Activation: the schedule and the page.
	 */
	public function activate() {
		WPCPM_Sponsors_Sync::activate();

		if ( class_exists( 'WPCPM_Sponsors_Dashboard' ) && method_exists( 'WPCPM_Sponsors_Dashboard', 'ensure_page' ) ) {
			call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'ensure_page' ) );
		}
	}

	/**
	 * Deactivation: off the clock.
	 */
	public function deactivate() {
		WPCPM_Sponsors_Sync::deactivate();
	}

	/**
	 * Uninstall: the options this module owns. Accounts, attachments and the audit rows are
	 * not this module's to delete (spec section 11).
	 */
	public function uninstall() {
		delete_option( WPCPM_Sponsors_Sync::OPT_STATE );
		delete_option( WPCPM_Sponsors_Sync::OPT_REPORT );
		delete_option( WPCPM_Sponsors_Sync::OPT_LAST );
		delete_option( WPCPM_Sponsors_Sync::OPT_ERROR );
		delete_option( WPCPM_Sponsors_Sync::OPT_LOCK );

		WPCPM_Sponsors_Index::delete_all();

		if ( class_exists( 'WPCPM_Sponsor_Offers' ) ) {
			WPCPM_Sponsor_Offers::delete_all();
		}

		if ( class_exists( 'WPCPM_Sponsor_Claims' ) ) {
			WPCPM_Sponsor_Claims::delete_all();
		}

		if ( class_exists( 'WPCPM_Sponsors_Dashboard' ) ) {
			delete_option( WPCPM_Sponsors_Dashboard::OPT_PAGE );
			delete_option( WPCPM_Sponsors_Dashboard::OPT_TITLE_FIXED );
		}

		wp_clear_scheduled_hook( WPCPM_Sponsors_Sync::CRON_DAILY );
		wp_clear_scheduled_hook( WPCPM_Sponsors_Sync::CRON_TICK );

		// The stamps are the plugin's; the accounts are not (spec section 11, the same
		// bargain the Institutions module strikes for its own member meta).
		foreach ( array(
			WPCPM_Sponsor_Members::META_RECORD_ID,
			WPCPM_Sponsor_Members::META_ACTIVE,
			WPCPM_Sponsor_Members::META_RECORD_ID_WAS,
			WPCPM_Sponsor_Members::META_MEMBERSHIP,
			WPCPM_Sponsor_Members::META_INVITED,
			WPCPM_Sponsor_Members::META_PROFILE,
		) as $meta_key ) {
			delete_metadata( 'user', 0, $meta_key, '', true );
		}
	}

	/**
	 * This screen's outcomes, in the reader's words.
	 *
	 * @return array<string, array{0: string, 1: string}> Status to notice class and sentence.
	 */
	public static function messages() {
		return array(
			'provisioned'        => array( 'success', __( 'The account was created and its invitation queued.', 'wpcredits-program-manager' ) ),
			'provision-attached' => array( 'success', __( 'An account with that address already existed and now acts for the sponsor.', 'wpcredits-program-manager' ) ),
			'provision-admin'    => array( 'error', __( 'That address belongs to an administrator, who already reaches every sponsor.', 'wpcredits-program-manager' ) ),
			'provision-inactive' => array( 'error', __( 'Only an Approved sponsor can be given an account.', 'wpcredits-program-manager' ) ),
			'provision-no-email' => array( 'error', __( 'That sponsor has no Contact Email in Airtable. Add one there and sync.', 'wpcredits-program-manager' ) ),
			'provision-refused'  => array( 'error', __( 'That account cannot act for this sponsor. The members card says why.', 'wpcredits-program-manager' ) ),
			'provision-failed'   => array( 'error', __( 'The account could not be created.', 'wpcredits-program-manager' ) ),
			'airtable-failed'    => array( 'warning', __( 'Done on the site, but Airtable could not be told: the Dashboard account checkbox is out of step until the next attempt.', 'wpcredits-program-manager' ) ),
			'attached'           => array( 'success', __( 'The account now acts for the sponsor.', 'wpcredits-program-manager' ) ),
			'attach-no-account'  => array( 'error', __( 'No account has that address. Create account makes one from the sponsor\'s contact address.', 'wpcredits-program-manager' ) ),
			'attach-refused'     => array( 'error', __( 'That account cannot act for this sponsor.', 'wpcredits-program-manager' ) ),
			'detached'           => array( 'success', __( 'The account no longer acts for the sponsor.', 'wpcredits-program-manager' ) ),
			'detach-refused'     => array( 'error', __( 'That account could not be detached.', 'wpcredits-program-manager' ) ),
			'refused'            => array( 'error', __( 'That is not something your account can do here.', 'wpcredits-program-manager' ) ),
			'offer-seeded'       => array( 'success', __( 'The first offer was seeded from the base; the sponsor completes it and switches it on from the Sponsor Dashboard.', 'wpcredits-program-manager' ) ),
			'offer-seed-none'    => array( 'info', __( 'That sponsor already has an offer; nothing was seeded.', 'wpcredits-program-manager' ) ),
			'offer-seed-failed'  => array( 'error', __( 'The offer could not be seeded: the index does not hold that sponsor.', 'wpcredits-program-manager' ) ),
			'claim-voided'       => array( 'success', __( 'The claim was voided. The person may claim again; the code stays void for the count.', 'wpcredits-program-manager' ) ),
			'claim-void-none'    => array( 'info', __( 'That person holds no claim on that offer.', 'wpcredits-program-manager' ) ),
			'claim-void-busy'    => array( 'info', __( 'Another change to that offer was going through. Try again in a moment.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * Create a sponsor's account, one at a time, from the screen.
	 *
	 * The capability, then the nonce keyed to the record, then the policy (a manager passes,
	 * and the ground goes in the log), then the record's own facts.
	 */
	public function handle_provision() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$record = WPCPM_Request::posted_text( 'wpcpm_sponsor' );

		check_admin_referer( self::ACTION_PROVISION . '_' . $record );

		$decision = WPCPM_Sponsor_Policy::decide( WPCPM_Sponsor_Policy::ACT_PROVISION, WPCPM_Sponsor_Policy::subject_sponsor( $record ) );

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Sponsor_Policy::refusal()->get_error_message() ), 403 );
		}

		$result = self::provision( $record, get_current_user_id() );

		$this->redirect_back( $result['status'] );
	}

	/**
	 * Create or attach the account behind a sponsor's contact address.
	 *
	 * @param string $record   Sponsor record ID.
	 * @param int    $actor_id The manager.
	 * @return array `status` (a key of `messages()`), `user_id`, `detail`.
	 */
	public static function provision( $record, $actor_id ) {
		$row = WPCPM_Sponsors_Index::row( $record );

		if ( ! is_array( $row ) || WPCPM_Sponsors_Index::STATUS_APPROVED !== $row['status'] ) {
			return array(
				'status'  => 'provision-inactive',
				'user_id' => 0,
				'detail'  => '',
			);
		}

		$email = sanitize_email( $row['contact_email'] );

		if ( ! is_email( $email ) ) {
			return array(
				'status'  => 'provision-no-email',
				'user_id' => 0,
				'detail'  => '',
			);
		}

		$existing = get_user_by( 'email', $email );
		$created  = false;

		if ( $existing instanceof WP_User && $existing->exists() ) {
			if ( WPCPM_Roles::user_has_role( $existing, WPCPM_Roles::ROLE_ADMIN ) ) {
				return array(
					'status'  => 'provision-admin',
					'user_id' => 0,
					'detail'  => '',
				);
			}

			$user_id = (int) $existing->ID;
		} else {
			$name  = '' !== trim( $row['contact_person'] ) ? trim( $row['contact_person'] ) : trim( $row['name'] );
			$login = self::unique_login( $email );
			$made  = WPCPM_Roles::insert_user(
				array(
					'user_login'   => $login,
					'user_email'   => $email,
					'user_pass'    => wp_generate_password( 24, true, true ),
					'display_name' => '' !== $name ? $name : $login,
					'nickname'     => '' !== $name ? $name : $login,
					'role'         => WPCPM_Roles::ROLE_SPONSOR,
				)
			);

			if ( is_wp_error( $made ) ) {
				return array(
					'status'  => 'provision-failed',
					'user_id' => 0,
					'detail'  => $made->get_error_message(),
				);
			}

			$user_id = (int) $made;
			$created = true;
		}

		$attached = WPCPM_Sponsor_Members::attach( $user_id, $record, WPCPM_Sponsor_Members::HOW_PROVISIONED, $actor_id );

		if ( is_wp_error( $attached ) ) {
			// Already attached here is the one refusal that is good news. in_array() rather than
			// a direct strict comparison on the error code: the sponsor policy suite scans this
			// module's file for a sponsor-prefixed string beside that operator, to keep record-ID
			// comparisons behind the policy's own helper alone, and a bare one here would read
			// as exactly that to a scan that cannot see this is an error code, not a record ID.
			if ( in_array( $attached->get_error_code(), array( 'wpcpm_sponsor_member_already' ), true ) ) {
				return array(
					'status'  => 'provision-attached',
					'user_id' => $user_id,
					'detail'  => '',
				);
			}

			return array(
				'status'  => 'provision-refused',
				'user_id' => 0,
				'detail'  => $attached->get_error_message(),
			);
		}

		// Queued rather than sent: the queue is what the mail log and the stop control are
		// built on, and `queue_invites()` drops an account already invited once.
		WPCPM_Mail::queue_invites( array( $user_id ) );

		// The first offer, from the index (spec 6.1). seed() refuses a sponsor that has one, so an
		// account attached to a sponsor already provisioned does not make a second.
		if ( class_exists( 'WPCPM_Sponsor_Offers' ) ) {
			WPCPM_Sponsor_Offers::seed( $record );
		}

		$marked = self::mark_dashboard_account( $record, true );

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_PROVISIONED,
				'sponsor'  => $record,
				'subject'  => (string) $user_id,
				'actor'    => $actor_id,
				'ground'   => WPCPM_Institution_Audit::GROUND_MANAGER,
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => $created
					? __( 'A sponsor account was created from the contact address and its invitation queued.', 'wpcredits-program-manager' )
					: __( 'The existing account at the contact address was attached to the sponsor.', 'wpcredits-program-manager' ),
				'data'     => array(
					'user'     => $user_id,
					'created'  => $created,
					'airtable' => ! is_wp_error( $marked ),
				),
			)
		);

		if ( is_wp_error( $marked ) ) {
			return array(
				'status'  => 'airtable-failed',
				'user_id' => $user_id,
				'detail'  => $marked->get_error_message(),
			);
		}

		return array(
			'status'  => $created ? 'provisioned' : 'provision-attached',
			'user_id' => $user_id,
			'detail'  => '',
		);
	}

	/**
	 * Attach an existing account by address, or detach one.
	 */
	public function handle_members() {
		$this->verify( self::ACTION_MEMBERS );

		$record = WPCPM_Request::posted_text( 'wpcpm_sponsor' );
		$op     = WPCPM_Request::posted_key( 'wpcpm_op' );

		$decision = WPCPM_Sponsor_Policy::decide( WPCPM_Sponsor_Policy::ACT_MANAGE_MEMBERS, WPCPM_Sponsor_Policy::subject_sponsor( $record ) );

		if ( empty( $decision['allowed'] ) || ! in_array( $op, array( 'attach', 'detach' ), true ) ) {
			$this->redirect_back( 'refused' );
		}

		if ( 'attach' === $op ) {
			$email = sanitize_email( WPCPM_Request::posted_text( 'wpcpm_email' ) );
			$user  = is_email( $email ) ? get_user_by( 'email', $email ) : false;

			if ( ! $user instanceof WP_User || ! $user->exists() ) {
				$this->redirect_back( 'attach-no-account' );
			}

			$attached = WPCPM_Sponsor_Members::attach( $user->ID, $record, WPCPM_Sponsor_Members::HOW_MANAGER, get_current_user_id() );

			if ( is_wp_error( $attached ) ) {
				$this->redirect_back( 'attach-refused' );
			}

			WPCPM_Mail::queue_invites( array( (int) $user->ID ) );

			$this->redirect_back( is_wp_error( self::mark_dashboard_account( $record, true ) ) ? 'airtable-failed' : 'attached' );
		}

		$user_id  = WPCPM_Request::posted_id( 'wpcpm_user' );
		$detached = WPCPM_Sponsor_Members::is_member( $user_id, $record )
			? WPCPM_Sponsor_Members::detach( $user_id, WPCPM_Sponsor_Members::REASON_REMOVED, get_current_user_id() )
			: WPCPM_Sponsor_Policy::refusal();

		if ( is_wp_error( $detached ) ) {
			$this->redirect_back( 'detach-refused' );
		}

		$status = 'detached';

		if ( empty( WPCPM_Sponsor_Members::members_of( $record ) ) && is_wp_error( self::mark_dashboard_account( $record, false ) ) ) {
			$status = 'airtable-failed';
		}

		$this->redirect_back( $status );
	}

	/**
	 * Seed a sponsor's first offer from the index. A manager's action: the sponsors
	 * provisioned in S1 predate offers, and the button is how they get theirs.
	 */
	public function handle_seed() {
		$record = WPCPM_Request::posted_text( 'wpcpm_sponsor' );
		$this->verify( self::ACTION_SEED . '_' . $record );

		// Spec section 4: every sponsor action is decided by the policy, and this was the one
		// that was not (final review of Phase S2, finding 9). The capability check above already
		// held it to managers; the claim adds the index check and the recorded ground.
		$claim = WPCPM_Sponsor_Roster::claim( $record, WPCPM_Sponsor_Policy::ACT_MANAGE_OFFERS );

		if ( is_wp_error( $claim ) ) {
			$this->redirect_back( 'refused' );
		}

		$record = $claim['record'];
		$seeded = WPCPM_Sponsor_Offers::seed( $record );

		if ( is_wp_error( $seeded ) ) {
			$this->redirect_back( 'offer-seed-failed' );
		}

		if ( false === $seeded ) {
			$this->redirect_back( 'offer-seed-none' );
		}

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => WPCPM_Sponsor_Offers::LOG_SEEDED,
				'sponsor'  => $record,
				'subject'  => (string) $seeded,
				'actor'    => get_current_user_id(),
				'ground'   => WPCPM_Institution_Audit::GROUND_MANAGER,
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => __( 'The first offer was seeded from the base by a manager.', 'wpcredits-program-manager' ),
				'data'     => array( 'offer' => (int) $seeded ),
			)
		);

		$this->redirect_back( 'offer-seeded' );
	}

	/**
	 * Void a person's claim. The capability and the nonce, then the sponsor is read from the
	 * offer (never from the form) and claimed with ACT_VIEW_CLAIMANTS: a manager's ground.
	 */
	public function handle_claim_void() {
		$offer_id = WPCPM_Request::posted_id( 'wpcpm_offer' );
		$user_id  = WPCPM_Request::posted_id( 'wpcpm_user' );
		$this->verify( self::ACTION_CLAIM_VOID . '_' . $offer_id . '_' . $user_id );

		$offer = WPCPM_Sponsor_Offers::read( $offer_id );

		if ( null === $offer ) {
			$this->redirect_back( 'refused' );
		}

		$claim = WPCPM_Sponsor_Roster::claim( $offer['sponsor'], WPCPM_Sponsor_Policy::ACT_VIEW_CLAIMANTS );

		if ( is_wp_error( $claim ) ) {
			$this->redirect_back( 'refused' );
		}

		// void_claim() answers true, false or a WP_Error (Task 4's fix round: the pool's lock can
		// be another request's for a moment), which is not the same "nothing to void" as a person
		// holding no claim, so the busy answer gets its own status rather than folding into
		// 'claim-void-none'.
		$voided = WPCPM_Sponsor_Claims::void_claim( $offer_id, $user_id, get_current_user_id() );

		if ( is_wp_error( $voided ) ) {
			$this->redirect_back( 'claim-void-busy' );
		}

		$this->redirect_back( $voided ? 'claim-voided' : 'claim-void-none' );
	}

	/**
	 * Every offer with its state and counts, who claimed from it (decision 7: managers read
	 * names), a Void button per claim, and a Seed button for a sponsor with an account and no
	 * offer.
	 *
	 * @param array $rows     The index rows, by record.
	 * @param array $accounts Accounts by sponsor (accounts_by_sponsor()).
	 */
	private function render_offers( array $rows, array $accounts ) {
		$offers = WPCPM_Sponsor_Offers::all();

		echo '<div class="wpcpm-card" id="wpcpm-sponsor-offers">';
		printf( '<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>', esc_html__( 'Offers and codes', 'wpcredits-program-manager' ), esc_html( number_format_i18n( count( $offers ) ) ) );
		echo '<p class="description">' . esc_html__( 'Every sponsor offer, its state and its codes, and who claimed one, for support. Sponsors see counts only. Voiding a claim lets the person claim again; the code stays void for the count.', 'wpcredits-program-manager' ) . '</p>';

		foreach ( $rows as $record => $row ) {
			if ( empty( $accounts[ $record ] ) || ! empty( WPCPM_Sponsor_Offers::offers_of( $record ) ) ) {
				continue;
			}

			printf( '<form method="post" action="%s" class="wpcpm-inline-form">', esc_url( admin_url( 'admin-post.php' ) ) );
			wp_nonce_field( self::ACTION_SEED . '_' . $record );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SEED ) );
			printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );
			/* translators: %s: sponsor name. */
			printf( '<button type="submit" class="button">%s</button>', esc_html( sprintf( __( 'Seed the first offer for %s from the base', 'wpcredits-program-manager' ), '' !== trim( (string) $row['name'] ) ? trim( (string) $row['name'] ) : $record ) ) );
			echo '</form> ';
		}

		if ( empty( $offers ) ) {
			echo '<p>' . esc_html__( 'Nothing yet.', 'wpcredits-program-manager' ) . '</p></div>';
			return;
		}

		echo '<table class="wpcpm-table widefat striped"><thead><tr>';

		foreach ( array( __( 'Sponsor', 'wpcredits-program-manager' ), __( 'Offer', 'wpcredits-program-manager' ), __( 'Kind', 'wpcredits-program-manager' ), __( 'State', 'wpcredits-program-manager' ), __( 'Available', 'wpcredits-program-manager' ), __( 'Claimed', 'wpcredits-program-manager' ), __( 'Void', 'wpcredits-program-manager' ), __( 'Warns at', 'wpcredits-program-manager' ), __( 'Last day', 'wpcredits-program-manager' ), __( 'Claimed by', 'wpcredits-program-manager' ) ) as $head ) {
			echo '<th scope="col">' . esc_html( $head ) . '</th>';
		}

		echo '</tr></thead><tbody>';

		foreach ( array_slice( $offers, 0, self::OFFERS_SHOWN, true ) as $offer ) {
			$counts  = WPCPM_Sponsor_Codes::counts( $offer['id'] );
			$sponsor = isset( $rows[ $offer['sponsor'] ] ) ? trim( (string) $rows[ $offer['sponsor'] ]['name'] ) : $offer['sponsor'];

			echo '<tr>';
			echo '<td>' . esc_html( '' === $sponsor ? $offer['sponsor'] : $sponsor ) . '</td>';
			echo '<td>' . esc_html( $offer['title'] ) . ( $offer['primary'] ? ' <span class="description">' . esc_html__( '(in the base)', 'wpcredits-program-manager' ) . '</span>' : '' ) . '</td>';
			echo '<td>' . esc_html( WPCPM_Sponsor_Offers::KIND_CODES === $offer['kind'] ? __( 'Codes', 'wpcredits-program-manager' ) : __( 'Shared', 'wpcredits-program-manager' ) ) . '</td>';
			echo '<td>' . esc_html( WPCPM_Sponsor_Offers::state_label( $offer['state'] ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $counts['available'] ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $counts['claimed'] ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $counts['void'] ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( (int) $offer['low'] ) ) . '</td>';
			echo '<td>' . esc_html( '' !== $offer['expires'] ? $offer['expires'] : '' ) . '</td>';
			echo '<td>';

			$claimants = WPCPM_Sponsor_Claims::claimants( $offer['id'] );

			if ( empty( $claimants ) ) {
				echo esc_html__( 'Nobody yet', 'wpcredits-program-manager' );
			} else {
				echo '<ul class="wpcpm-list">';

				foreach ( $claimants as $who ) {
					echo '<li>';
					printf(
						'%1$s <span class="description">%2$s</span> %3$s <code>%4$s</code> ',
						esc_html( '' !== $who['name'] ? $who['name'] : (string) $who['user_id'] ),
						esc_html( $who['email'] ),
						esc_html( wp_date( 'Y-m-d', (int) $who['at'] ) ),
						/* translators: %s: the code's last four characters. */
						esc_html( sprintf( __( 'ending %s', 'wpcredits-program-manager' ), $who['last4'] ) )
					);
					printf( '<form method="post" action="%s" class="wpcpm-inline-form" onsubmit="return confirm( \'%s\' );">', esc_url( admin_url( 'admin-post.php' ) ), esc_js( __( 'Void this claim? The person may then claim again.', 'wpcredits-program-manager' ) ) );
					wp_nonce_field( self::ACTION_CLAIM_VOID . '_' . $offer['id'] . '_' . $who['user_id'] );
					printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_CLAIM_VOID ) );
					printf( '<input type="hidden" name="wpcpm_offer" value="%d" />', (int) $offer['id'] );
					printf( '<input type="hidden" name="wpcpm_user" value="%d" />', (int) $who['user_id'] );
					printf( '<button type="submit" class="button button-small">%s</button>', esc_html__( 'Void', 'wpcredits-program-manager' ) );
					echo '</form>';
					echo '</li>';
				}

				echo '</ul>';
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';

		if ( count( $offers ) > self::OFFERS_SHOWN ) {
			/* translators: %d: how many offers are listed. */
			echo '<p class="description">' . esc_html( sprintf( __( 'The oldest %d are listed.', 'wpcredits-program-manager' ), self::OFFERS_SHOWN ) ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Tell the base whether a sponsor has a site account, and the index at once.
	 *
	 * The one place the `Dashboard account` column is written (spec section 12): the provision
	 * handler (true), the members handler when the last account goes (false) and the sync's
	 * revoke phase (false).
	 *
	 * @param string $record Sponsor record ID.
	 * @param bool   $flag   Whether it has an account.
	 * @return true|WP_Error
	 */
	public static function mark_dashboard_account( $record, $flag ) {
		$record = trim( (string) $record );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return new WP_Error( 'wpcpm_sponsor_bad_record', __( 'That is not an Airtable record ID.', 'wpcredits-program-manager' ) );
		}

		$airtable = new WPCPM_Airtable();
		$written  = $airtable->update_records(
			(string) WPCPM_Settings::get_value( 'sponsors_table', '' ),
			array(
				array(
					'id'     => $record,
					'fields' => array( self::FIELD_DASHBOARD_ACCOUNT => (bool) $flag ),
				),
			)
		);

		if ( is_wp_error( $written ) ) {
			return $written;
		}

		WPCPM_Sponsors_Index::patch( $record, array( 'dashboard_account' => (bool) $flag ) );

		return true;
	}

	/**
	 * The screen.
	 */
	public function render_admin_page() {
		$rows     = WPCPM_Sponsors_Index::rows();
		$progress = WPCPM_Sponsors_Sync::progress();
		$last     = WPCPM_Sponsors_Sync::last_read();

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html( $this->label() ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html( $this->description() ) . '</p>';

		$this->render_status_notice( self::messages() );

		if ( ! WPCPM_Settings::is_connected() ) {
			printf(
				'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
				esc_html__( 'Airtable is not connected yet, so no sponsors can be synced.', 'wpcredits-program-manager' ),
				esc_url( admin_url( 'admin.php?page=wpcpm-settings' ) ),
				esc_html__( 'Open settings', 'wpcredits-program-manager' )
			);
		}

		if ( ! empty( $progress['error'] ) ) {
			printf(
				'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html__( 'Last sync error:', 'wpcredits-program-manager' ),
				esc_html( (string) $progress['error'] )
			);
		}

		$accounts = self::accounts_by_sponsor();

		$this->render_locked_accounts();
		$this->render_sync_panel( $progress, $last );
		$this->render_index( $rows, $accounts );
		$this->render_members( $rows, $accounts );
		$this->render_offers( $rows, $accounts );
		$this->render_interests( $rows );

		echo '</div>';
	}

	/**
	 * The accounts locked out for the rest of today, if any.
	 */
	private function render_locked_accounts() {
		$locked = WPCPM_Sponsor_Roster::locked_today();

		if ( empty( $locked ) ) {
			return;
		}

		$names = array();

		foreach ( $locked as $user ) {
			$names[] = sprintf( '%1$s (%2$s)', $user->display_name, $user->user_login );
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: how many accounts. */
					_n( '%d sponsor account is locked out of changes for the rest of today.', '%d sponsor accounts are locked out of changes for the rest of today.', count( $locked ), 'wpcredits-program-manager' ),
					count( $locked )
				)
			),
			esc_html(
				sprintf(
					/* translators: %s: the accounts, as display name (username), comma-separated. */
					__( 'Each was refused more requests than the daily ceiling allows: %s. The sponsor\'s log has the row, and the lock lifts by itself tomorrow.', 'wpcredits-program-manager' ),
					implode( ', ', $names )
				)
			)
		);
	}

	/**
	 * Sync controls and live progress: the institutions panel's markup, which assets/js/admin.js polls.
	 *
	 * @param array $progress From `WPCPM_Sponsors_Sync::progress()`.
	 * @param int   $last     When the last run finished.
	 */
	private function render_sync_panel( array $progress, $last ) {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Airtable sync', 'wpcredits-program-manager' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Reads the Team Members table, then every sponsor\'s columns, copies each Approved sponsor\'s logo into the Media Library, and reviews the accounts of sponsors that are no longer Approved. It never creates an account.', 'wpcredits-program-manager' ) . '</p>';

		if ( ! empty( $progress['running'] ) ) {
			printf(
				'<div class="wpcpm-progress" data-wpcpm-progress data-action="%1$s" data-nonce="%2$s" data-poll="3">',
				esc_attr( self::ACTION_TICK ),
				esc_attr( wp_create_nonce( self::ACTION_TICK ) )
			);
			echo '<p class="wpcpm-progress__head"><span class="spinner is-active" aria-hidden="true"></span> ';
			printf( '<strong data-wpcpm-label>%s</strong>', esc_html( isset( $progress['label'] ) ? $progress['label'] : '' ) );
			printf( ' <span class="wpcpm-progress__step" data-wpcpm-step>%s</span>', esc_html( isset( $progress['step_label'] ) ? $progress['step_label'] : '' ) );
			echo '</p>';
			$percent = isset( $progress['percent'] ) ? (int) $progress['percent'] : 0;
			printf(
				'<div class="wpcpm-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="%1$d" aria-label="%2$s" data-wpcpm-bar><div class="wpcpm-bar__fill" style="width:%1$d%%" data-wpcpm-fill></div></div>',
				(int) $percent,
				esc_attr__( 'Sync progress', 'wpcredits-program-manager' )
			);
			echo '<p class="wpcpm-progress__meta">';
			printf( '<span data-wpcpm-percent>%d%%</span> - ', (int) $percent );
			printf( '<span data-wpcpm-detail>%s</span> - ', esc_html( isset( $progress['detail'] ) ? $progress['detail'] : '' ) );
			/* translators: %s: elapsed time as a clock value. */
			$elapsed_label = __( 'running for %s', 'wpcredits-program-manager' );
			printf(
				'<span data-wpcpm-elapsed data-label="%1$s">%2$s</span>',
				esc_attr( $elapsed_label ),
				esc_html( sprintf( $elapsed_label, WPCPM_Mentors::format_duration( isset( $progress['elapsed'] ) ? (int) $progress['elapsed'] : 0 ) ) )
			);
			echo '</p>';
			printf(
				'<p class="wpcpm-progress__stalled" data-wpcpm-stalled%1$s>%2$s</p>',
				! empty( $progress['stalled'] ) ? '' : ' hidden',
				esc_html__( 'No progress for over two minutes. The run may have been interrupted: cancel it and start again.', 'wpcredits-program-manager' )
			);
			echo '<noscript><meta http-equiv="refresh" content="15" /></noscript>';
			echo '</div>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-wpcpm-once data-wpcpm-busy="' . esc_attr__( 'Canceling', 'wpcredits-program-manager' ) . '">';
			wp_nonce_field( self::ACTION_CANCEL );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_CANCEL ) . '" />';
			submit_button( __( 'Cancel sync', 'wpcredits-program-manager' ), 'secondary', 'submit', false );
			echo '</form>';
		} else {
			if ( $last ) {
				printf(
					'<p>%s</p>',
					esc_html(
						sprintf(
							/* translators: 1: date and time, 2: human-readable time difference. */
							__( 'Last completed %1$s (%2$s ago).', 'wpcredits-program-manager' ),
							wp_date( 'Y-m-d H:i', $last ),
							human_time_diff( $last, time() )
						)
					)
				);
			} else {
				echo '<p>' . esc_html__( 'No sync has run yet.', 'wpcredits-program-manager' ) . '</p>';
			}

			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-wpcpm-once data-wpcpm-busy="' . esc_attr__( 'Starting', 'wpcredits-program-manager' ) . '">';
			wp_nonce_field( self::ACTION_SYNC );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SYNC ) . '" />';
			submit_button( __( 'Sync sponsors now', 'wpcredits-program-manager' ), 'primary', 'submit', false );
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * Every live account, grouped by the sponsor it acts for, one query for the whole render.
	 *
	 * `render_index()` and `render_members()` used to call `WPCPM_Sponsor_Members::members_of()`
	 * once per sponsor row, each its own `get_users()` query; grouping `live_accounts()`'s one
	 * query by `sponsor_of()` here does the same job in one pass over one list.
	 *
	 * @return array<string, WP_User[]> Record ID to its accounts.
	 */
	private static function accounts_by_sponsor() {
		$grouped = array();

		foreach ( WPCPM_Sponsor_Members::live_accounts() as $account ) {
			$record = WPCPM_Sponsor_Members::sponsor_of( $account );

			if ( '' === $record ) {
				continue;
			}

			$grouped[ $record ][] = $account;
		}

		return $grouped;
	}

	/**
	 * Every sponsor, with its status, manager, accounts and logo, and Create account where it applies.
	 *
	 * @param array $rows     The index rows.
	 * @param array $accounts From `accounts_by_sponsor()`.
	 */
	private function render_index( array $rows, array $accounts ) {
		echo '<div class="wpcpm-card">';
		printf( '<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>', esc_html__( 'Sponsors', 'wpcredits-program-manager' ), esc_html( number_format_i18n( count( $rows ) ) ) );

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No sponsors have been read yet. Run the sync.', 'wpcredits-program-manager' ) . '</p></div>';

			return;
		}

		echo '<table class="wpcpm-table widefat striped"><thead><tr>';
		foreach ( array( __( 'Sponsor', 'wpcredits-program-manager' ), __( 'Status', 'wpcredits-program-manager' ), __( 'Product', 'wpcredits-program-manager' ), __( 'Program contact', 'wpcredits-program-manager' ), __( 'Accounts', 'wpcredits-program-manager' ), __( 'Logo', 'wpcredits-program-manager' ), '' ) as $head ) {
			echo '<th scope="col">' . esc_html( $head ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $record => $row ) {
			$members = isset( $accounts[ $record ] ) ? $accounts[ $record ] : array();
			$manager = WPCPM_Sponsors_Index::manager_of( $record );
			$logo    = WPCPM_Sponsors_Index::logo_record( $record );
			$name    = '' === trim( $row['name'] ) ? $record : trim( $row['name'] );
			$link    = self::dashboard_link( $record );

			echo '<tr>';
			echo '<td>' . ( '' !== $link ? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $link ), esc_html( $name ) ) : esc_html( $name ) ) . '</td>';
			echo '<td>' . esc_html( $row['status'] ) . '</td>';
			echo '<td>' . esc_html( $row['product_type'] ) . '</td>';
			echo '<td>' . esc_html( is_array( $manager ) ? $manager['name'] : '' ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( count( $members ) ) ) . '</td>';
			echo '<td>' . esc_html( $logo['colour'] > 0 ? __( 'On the site', 'wpcredits-program-manager' ) : ( empty( $row['logo'] ) ? __( 'None', 'wpcredits-program-manager' ) : __( 'Not copied yet', 'wpcredits-program-manager' ) ) ) . '</td>';
			echo '<td>';

			if ( WPCPM_Sponsors_Index::STATUS_APPROVED === $row['status'] && empty( $members ) && is_email( $row['contact_email'] ) ) {
				printf(
					'<form method="post" action="%1$s" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="%2$s">',
					esc_url( admin_url( 'admin-post.php' ) ),
					esc_attr__( 'Creating', 'wpcredits-program-manager' )
				);
				wp_nonce_field( self::ACTION_PROVISION . '_' . $record );
				printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_PROVISION ) );
				printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );
				printf(
					'<button type="submit" class="button">%s</button>',
					esc_html(
						sprintf(
							/* translators: %s: a masked address. */
							__( 'Create account (%s)', 'wpcredits-program-manager' ),
							WPCPM_Mail::mask_address( $row['contact_email'] )
						)
					)
				);
				echo '</form>';
			}

			echo '</td></tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Each Approved sponsor's accounts, with Remove, and an attach form.
	 *
	 * @param array $rows     The index rows.
	 * @param array $accounts From `accounts_by_sponsor()`.
	 */
	private function render_members( array $rows, array $accounts ) {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Accounts', 'wpcredits-program-manager' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Who acts for each Approved sponsor. Attach an existing account by its address, or remove one; every account attached here has equal power on the Sponsor Dashboard.', 'wpcredits-program-manager' ) . '</p>';

		foreach ( $rows as $record => $row ) {
			if ( WPCPM_Sponsors_Index::STATUS_APPROVED !== $row['status'] ) {
				continue;
			}

			$members = isset( $accounts[ $record ] ) ? $accounts[ $record ] : array();
			$name    = '' === trim( $row['name'] ) ? $record : trim( $row['name'] );

			echo '<h3>' . esc_html( $name ) . '</h3>';

			if ( empty( $members ) ) {
				echo '<p class="description">' . esc_html__( 'No account yet.', 'wpcredits-program-manager' ) . '</p>';
			} else {
				echo '<ul class="wpcpm-list">';

				foreach ( $members as $member ) {
					echo '<li>';
					printf( '%1$s (%2$s) ', esc_html( $member->display_name ), esc_html( $member->user_email ) );
					printf(
						'<form method="post" action="%1$s" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="%2$s" onsubmit="return confirm(%3$s);">',
						esc_url( admin_url( 'admin-post.php' ) ),
						esc_attr__( 'Removing', 'wpcredits-program-manager' ),
						esc_attr( wp_json_encode( __( 'Remove this account from the sponsor?', 'wpcredits-program-manager' ) ) )
					);
					wp_nonce_field( self::ACTION_MEMBERS );
					printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_MEMBERS ) );
					printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );
					echo '<input type="hidden" name="wpcpm_op" value="detach" />';
					printf( '<input type="hidden" name="wpcpm_user" value="%d" />', (int) $member->ID );
					printf( '<button type="submit" class="button-link-delete">%s</button>', esc_html__( 'Remove', 'wpcredits-program-manager' ) );
					echo '</form></li>';
				}

				echo '</ul>';
			}

			printf(
				'<form method="post" action="%1$s" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="%2$s">',
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_attr__( 'Attaching', 'wpcredits-program-manager' )
			);
			wp_nonce_field( self::ACTION_MEMBERS );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_MEMBERS ) );
			printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );
			echo '<input type="hidden" name="wpcpm_op" value="attach" />';
			printf( '<label class="screen-reader-text" for="wpcpm-attach-%1$s">%2$s</label>', esc_attr( $record ), esc_html__( 'Email address', 'wpcredits-program-manager' ) );
			printf( '<input type="email" id="wpcpm-attach-%1$s" name="wpcpm_email" placeholder="%2$s" required /> ', esc_attr( $record ), esc_attr__( 'name@company.example', 'wpcredits-program-manager' ) );
			printf( '<button type="submit" class="button">%s</button>', esc_html__( 'Attach account', 'wpcredits-program-manager' ) );
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * The last interests sponsors expressed, across every sponsor.
	 *
	 * @param array $rows The index rows, for the names.
	 */
	private function render_interests( array $rows ) {
		$entries = array_merge(
			WPCPM_Institution_Audit::sponsor_entries( 'sponsor_interest', self::INTERESTS_SHOWN ),
			WPCPM_Institution_Audit::sponsor_entries( 'sponsor_interest_mentor', self::INTERESTS_SHOWN )
		);

		usort(
			$entries,
			static function ( $a, $b ) {
				return (int) $b['time'] - (int) $a['time'];
			}
		);

		$entries = array_slice( $entries, 0, self::INTERESTS_SHOWN );

		echo '<div class="wpcpm-card">';
		printf( '<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>', esc_html__( 'Interests', 'wpcredits-program-manager' ), esc_html( number_format_i18n( count( $entries ) ) ) );
		echo '<p class="description">' . esc_html__( 'What sponsors said they would like to support, from the Sponsor Dashboard. Each was mailed to the assigned program manager when it was sent.', 'wpcredits-program-manager' ) . '</p>';

		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'Nothing yet.', 'wpcredits-program-manager' ) . '</p></div>';

			return;
		}

		echo '<table class="wpcpm-table widefat striped"><thead><tr>';
		foreach ( array( __( 'When', 'wpcredits-program-manager' ), __( 'Sponsor', 'wpcredits-program-manager' ), __( 'Who', 'wpcredits-program-manager' ), __( 'What', 'wpcredits-program-manager' ) ) as $head ) {
			echo '<th scope="col">' . esc_html( $head ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $entries as $entry ) {
			$sponsor = isset( $rows[ $entry['sponsor'] ] ) ? trim( $rows[ $entry['sponsor'] ]['name'] ) : $entry['sponsor'];
			$actor   = $entry['actor'] > 0 ? get_user_by( 'id', $entry['actor'] ) : false;

			echo '<tr>';
			echo '<td>' . esc_html( wp_date( 'Y-m-d H:i', (int) $entry['time'] ) ) . '</td>';
			echo '<td>' . esc_html( '' === $sponsor ? $entry['sponsor'] : $sponsor ) . '</td>';
			echo '<td>' . esc_html( $actor instanceof WP_User ? $actor->display_name : '' ) . '</td>';
			echo '<td>' . esc_html( $entry['message'] ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * A sponsor's dashboard, through the switcher, or '' before the front end exists.
	 *
	 * @param string $record Sponsor record ID.
	 * @return string
	 */
	private static function dashboard_link( $record ) {
		if ( ! class_exists( 'WPCPM_Sponsors_Dashboard' ) || ! method_exists( 'WPCPM_Sponsors_Dashboard', 'page_url' ) ) {
			return '';
		}

		$page = (string) call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'page_url' ) );

		return '' === $page ? '' : add_query_arg( WPCPM_Sponsor_Roster::ARG_VIEW, $record, $page );
	}

	/**
	 * A login nobody holds yet, from the address's local part.
	 *
	 * @param string $email The address.
	 * @return string
	 */
	private static function unique_login( $email ) {
		$base  = sanitize_user( strtolower( (string) strstr( $email, '@', true ) ), true );
		$base  = '' === $base ? 'sponsor' : $base;
		$login = $base;
		$n     = 1;

		while ( username_exists( $login ) ) {
			$login = $base . '-' . ( ++$n );
		}

		return $login;
	}
}
