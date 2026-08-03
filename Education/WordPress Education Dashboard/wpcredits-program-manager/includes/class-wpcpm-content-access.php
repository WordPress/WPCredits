<?php
/**
 * Per-post access levels.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gates content by program level.
 *
 * Every post carries an access level in `_wpcpm_access_level`; absent meta means
 * public. A user reaches a level only when they hold that level's marker cap, so
 * a Mentor sees public plus Mentor-level content and nothing belonging to
 * Students, Institutions or Administrators.
 *
 * Gating is applied in four places on purpose. The query filter hides restricted
 * posts from listings, the template guard stops direct URL access, the content
 * filter covers anything that renders a post outside a gated query (related-post
 * widgets, hand-rolled `WP_Query` with `suppress_filters`), and the REST filter
 * covers the same ground for headless and block-editor reads.
 */
class WPCPM_Content_Access {

	const META_KEY = '_wpcpm_access_level';
	const NONCE    = 'wpcpm_access_level';

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_editor_assets' ) );

		add_action( 'pre_get_posts', array( __CLASS__, 'filter_queries' ) );
		add_action( 'template_redirect', array( __CLASS__, 'guard_singular' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 5 );
		add_filter( 'the_excerpt', array( __CLASS__, 'filter_excerpt' ), 5 );

		foreach ( self::post_types() as $post_type ) {
			add_filter( "rest_prepare_{$post_type}", array( __CLASS__, 'filter_rest' ), 10, 3 );
		}
	}

	/**
	 * The selectable access levels, keyed by stored value.
	 *
	 * @return array<string, array{label: string, cap: string}>
	 */
	public static function levels() {
		$levels = array(
			'public' => array(
				// Short label; "everyone" is spelled out in the description under the
				// control instead. This is for tidiness, not width — "Administrators
				// only" is the longest option either way, and the control is kept
				// inside the sidebar by assets/css/editor.css.
				'label' => __( 'Public', 'wpcredits-program-manager' ),
				'cap'   => '',
			),
		);

		foreach ( WPCPM_Roles::custom_roles() as $slug => $role ) {
			$levels[ $slug ] = array(
				/* translators: %s: role display name, e.g. Mentor. */
				'label' => sprintf( __( '%s level', 'wpcredits-program-manager' ), $role['label'] ),
				'cap'   => $role['cap'],
			);
		}

		$levels[ WPCPM_Roles::ROLE_ADMIN ] = array(
			'label' => __( 'Administrators only', 'wpcredits-program-manager' ),
			'cap'   => WPCPM_Roles::CAP_MANAGE,
		);

		return $levels;
	}

	/**
	 * Post types that carry an access level.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		return (array) apply_filters( 'wpcpm_access_post_types', array( 'post', 'page' ) );
	}

	/**
	 * Register the meta so it is available to the REST API and the block editor.
	 */
	public static function register_meta() {
		foreach ( self::post_types() as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_KEY,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => 'public',
					'show_in_rest'      => true,
					'sanitize_callback' => array( __CLASS__, 'sanitize_level' ),
					'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}

	/**
	 * Clamp a submitted level to a known value.
	 *
	 * @param string $level Raw level.
	 * @return string
	 */
	public static function sanitize_level( $level ) {
		$level = is_string( $level ) ? $level : '';

		return array_key_exists( $level, self::levels() ) ? $level : 'public';
	}

	/**
	 * The access level stored on a post.
	 *
	 * @param int|WP_Post $post Post ID or object.
	 * @return string
	 */
	public static function get_level( $post ) {
		$post = get_post( $post );

		if ( ! $post ) {
			return 'public';
		}

		$level = get_post_meta( $post->ID, self::META_KEY, true );

		return self::sanitize_level( $level );
	}

	/**
	 * Whether a user may read a post.
	 *
	 * @param int|WP_Post      $post Post ID or object.
	 * @param int|WP_User|null $user Optional user; defaults to the current user.
	 * @return bool
	 */
	public static function can_view( $post, $user = null ) {
		$post = get_post( $post );

		if ( ! $post ) {
			return true;
		}

		$level  = self::get_level( $post );
		$levels = self::levels();

		if ( 'public' === $level ) {
			return true;
		}

		$user_obj = WPCPM_Roles::resolve_user( $user );
		$user_id  = $user_obj instanceof WP_User ? $user_obj->ID : 0;

		$allowed = false;

		if ( $user_id ) {
			// Program managers see every level.
			if ( user_can( $user_id, WPCPM_Roles::CAP_MANAGE ) ) {
				$allowed = true;
			} else {
				$cap     = isset( $levels[ $level ]['cap'] ) ? $levels[ $level ]['cap'] : '';
				$allowed = ( '' !== $cap ) && user_can( $user_id, $cap );
			}
		}

		/**
		 * Filter whether a user may read a gated post.
		 *
		 * @param bool   $allowed Whether access is granted.
		 * @param string $level   Required access level.
		 * @param int    $post_id Post ID.
		 * @param int    $user_id User ID, 0 when logged out.
		 */
		return (bool) apply_filters( 'wpcpm_can_view_post', $allowed, $level, $post->ID, $user_id );
	}

	/**
	 * Levels the current user is allowed to see, for use in a meta query.
	 *
	 * @param int $user_id User ID, 0 when logged out.
	 * @return string[]
	 */
	private static function allowed_levels( $user_id ) {
		$allowed = array( 'public' );

		if ( ! $user_id ) {
			return $allowed;
		}

		foreach ( self::levels() as $level => $config ) {
			if ( '' !== $config['cap'] && user_can( $user_id, $config['cap'] ) ) {
				$allowed[] = $level;
			}
		}

		return array_values( array_unique( $allowed ) );
	}

	/**
	 * Hide restricted posts from front-end listings.
	 *
	 * @param WP_Query $query Query being prepared.
	 */
	public static function filter_queries( $query ) {
		if ( is_admin() || ! $query instanceof WP_Query ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( $user_id && user_can( $user_id, WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		// A singular request is handled by guard_singular(), which can explain
		// itself; silently 404ing a gated page a mentor was linked to is worse.
		if ( $query->is_singular() ) {
			return;
		}

		$post_types = (array) $query->get( 'post_type' );
		if ( ! empty( $post_types ) && ! array_intersect( $post_types, self::post_types() ) && ! in_array( 'any', $post_types, true ) ) {
			return;
		}

		$meta_query = $query->get( 'meta_query' );
		$meta_query = is_array( $meta_query ) ? $meta_query : array();

		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => self::META_KEY,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => self::META_KEY,
				'value'   => self::allowed_levels( $user_id ),
				'compare' => 'IN',
			),
		);

		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Stop direct access to a gated post.
	 *
	 * Logged-out visitors are sent to the login form and returned afterwards;
	 * logged-in users who simply lack the level get an explanation, because
	 * bouncing them to a login form they are already past reads as a bug.
	 */
	public static function guard_singular() {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post instanceof WP_Post || self::can_view( $post ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( get_permalink( $post ) ) );
			exit;
		}

		wp_die(
			esc_html( self::denial_message( self::get_level( $post ) ) ),
			esc_html__( 'Restricted content', 'wpcredits-program-manager' ),
			array(
				'response'  => 403,
				'back_link' => true,
			)
		);
	}

	/**
	 * Replace the body of a gated post wherever it is rendered.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function filter_content( $content ) {
		$post = get_post();

		if ( ! $post instanceof WP_Post || is_admin() || self::can_view( $post ) ) {
			return $content;
		}

		return self::notice( self::get_level( $post ) );
	}

	/**
	 * Replace the excerpt of a gated post.
	 *
	 * @param string $excerpt Post excerpt.
	 * @return string
	 */
	public static function filter_excerpt( $excerpt ) {
		$post = get_post();

		if ( ! $post instanceof WP_Post || is_admin() || self::can_view( $post ) ) {
			return $excerpt;
		}

		return '';
	}

	/**
	 * Strip gated content from REST responses.
	 *
	 * @param WP_REST_Response $response Response object.
	 * @param WP_Post          $post     Post being prepared.
	 * @param WP_REST_Request  $request  Request object.
	 * @return WP_REST_Response
	 */
	public static function filter_rest( $response, $post, $request ) {
		unset( $request );

		if ( ! $response instanceof WP_REST_Response || self::can_view( $post ) ) {
			return $response;
		}

		$data = $response->get_data();

		foreach ( array( 'content', 'excerpt' ) as $field ) {
			if ( isset( $data[ $field ] ) && is_array( $data[ $field ] ) ) {
				$data[ $field ]['rendered'] = self::notice( self::get_level( $post ) );
				unset( $data[ $field ]['raw'], $data[ $field ]['protected'] );
			}
		}

		$response->set_data( $data );

		return $response;
	}

	/**
	 * Plain-text denial message for a level.
	 *
	 * @param string $level Access level.
	 * @return string
	 */
	private static function denial_message( $level ) {
		$levels = self::levels();
		$label  = isset( $levels[ $level ]['label'] ) ? $levels[ $level ]['label'] : $level;

		return sprintf(
			/* translators: %s: access level label, e.g. "Mentor level". */
			__( 'This content is limited to: %s. Your account does not have that level of access.', 'wpcredits-program-manager' ),
			$label
		);
	}

	/**
	 * Markup shown in place of gated content.
	 *
	 * @param string $level Access level.
	 * @return string
	 */
	private static function notice( $level ) {
		$message = is_user_logged_in()
			? self::denial_message( $level )
			: __( 'This content is for program members. Please log in to continue.', 'wpcredits-program-manager' );

		$html = '<p class="wpcpm-restricted">' . esc_html( $message ) . '</p>';

		if ( ! is_user_logged_in() ) {
			$html .= '<p class="wpcpm-restricted__action"><a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Log in', 'wpcredits-program-manager' ) . '</a></p>';
		}

		return $html;
	}

	/**
	 * Add the access-level control to the editor.
	 *
	 * A classic meta box is used deliberately: it renders in both the classic and
	 * block editors with no build step, where a sidebar panel would need one.
	 */
	public static function add_meta_box() {
		foreach ( self::post_types() as $post_type ) {
			add_meta_box(
				'wpcpm-access-level',
				__( 'Program access', 'wpcredits-program-manager' ),
				array( __CLASS__, 'render_meta_box' ),
				$post_type,
				'side',
				'default',
				array( '__block_editor_compatible_meta_box' => true )
			);
		}
	}

	/**
	 * Render the access-level select.
	 *
	 * @param WP_Post $post Post being edited.
	 */
	public static function render_meta_box( $post ) {
		$current = self::get_level( $post );

		wp_nonce_field( self::NONCE, self::NONCE );

		echo '<div class="wpcpm-access">';

		printf(
			'<label class="wpcpm-access__label" for="wpcpm-access-level-field">%s</label>',
			esc_html__( 'Who can read this?', 'wpcredits-program-manager' )
		);

		// Not `widefat`: that sets width alone, which a select will still overrun
		// because of its automatic minimum width. See assets/css/editor.css.
		echo '<select name="' . esc_attr( self::META_KEY ) . '" id="wpcpm-access-level-field" class="wpcpm-access__select">';

		foreach ( self::levels() as $level => $config ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $level ),
				selected( $current, $level, false ),
				esc_html( $config['label'] )
			);
		}

		echo '</select>';

		printf(
			'<p class="description wpcpm-access__description">%s</p>',
			esc_html__( 'Public means everyone. Administrators can always read every level.', 'wpcredits-program-manager' )
		);

		echo '</div>';
	}

	/**
	 * Load the meta box stylesheet on the post editor.
	 *
	 * The plugin's admin stylesheet is only enqueued on its own screens, so the
	 * editor previously got no styling for this control at all.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue_editor_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'wpcpm-editor',
			WPCPM_PLUGIN_URL . 'assets/css/editor.css',
			array(),
			WPCPM_VERSION
		);
	}

	/**
	 * Persist the access level.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ] ) ) {
			return; // Not our form — a REST/block-editor save goes through register_post_meta().
		}

		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) || ! in_array( $post->post_type, self::post_types(), true ) ) {
			return;
		}

		$level = isset( $_POST[ self::META_KEY ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::META_KEY ] ) ) : 'public';

		update_post_meta( $post_id, self::META_KEY, self::sanitize_level( $level ) );
	}
}
