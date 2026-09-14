<?php
/**
 * What Learn WordPress says about a course: the course behind a link, and its modules and lessons.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Learn client, with no screen in it (the design's decision 30).
 *
 * Two public, unauthenticated endpoints answer everything the Track Builder needs: the course by
 * its slug (`wp-json/wp/v2/courses?slug=`) and the course's modules and lessons
 * (`sensei-internal/v1/course-structure/<id>`). Each answer is kept for a day, since a course's
 * lessons change rarely and a track's page is opened often; a failed read is not kept, so the next
 * open tries again; and `forget()` drops both for the day a lesson is added on Learn. The parsing
 * is pure and proven on answers Learn actually gave (bin/fixtures/learn-*.json).
 */
final class WPCPM_Learn {

	/** Where Learn's REST API answers. */
	const API = 'https://learn.wordpress.org/wp-json/';

	/** How long an answer is kept. */
	const TTL = DAY_IN_SECONDS;

	/**
	 * A course link, as `WPCPM_Track_Definition::validate()` requires it, with its slug captured.
	 *
	 * The slug's repeat is capped rather than open, so a long address a person pasted is matched
	 * in one pass (the final review of T3c). The two rules are byte for byte the same but for the
	 * capture group, and 120 characters is well past the longest slug Learn has.
	 */
	const COURSE_LINK = '#^https://learn\.wordpress\.org/course/([a-z0-9-]{1,120})/?$#';

	/**
	 * The slug of a course link, or nothing for any other address.
	 *
	 * @param string $url The link.
	 * @return string
	 */
	public static function slug( $url ) {
		return 1 === preg_match( self::COURSE_LINK, (string) $url, $m ) ? $m[1] : '';
	}

	/**
	 * The course behind a link.
	 *
	 * @param string $url A `learn.wordpress.org/course/<slug>/` link.
	 * @return array|WP_Error `id`, `slug` and `title`; or `wpcpm_learn_not_a_course` for a link of
	 *                        another shape, `wpcpm_learn_no_course` when Learn has no course at
	 *                        that address, `wpcpm_learn_unreachable` when Learn did not answer, and
	 *                        `wpcpm_learn_bad_answer` when it answered something else.
	 */
	public static function resolve( $url ) {
		$slug = self::slug( $url );

		if ( '' === $slug ) {
			return new WP_Error( 'wpcpm_learn_not_a_course', __( 'That is not the address of a Learn WordPress course: it should look like https://learn.wordpress.org/course/its-name/.', 'wpcredits-program-manager' ) );
		}

		$key  = 'wpcpm_learn_course_' . $slug;
		$held = get_transient( $key );

		if ( is_array( $held ) ) {
			return $held;
		}

		$data = self::get( 'wp/v2/courses?slug=' . rawurlencode( $slug ) . '&_fields=id,slug,status,link,title' );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$course = self::parse_course( $data );

		if ( is_array( $course ) ) {
			set_transient( $key, $course, self::TTL );
		}

		return $course;
	}

	/**
	 * A course's modules, each with its published lessons.
	 *
	 * @param int $course_id The course's post ID on Learn.
	 * @return array|WP_Error A list of modules, each `id`, `title` and `lessons` (each `id` and
	 *                        `title`), in Learn's order; a lesson outside every module sits in a
	 *                        module with id 0 and no title, at the end. Or `wpcpm_learn_unreachable`
	 *                        or `wpcpm_learn_bad_answer`.
	 */
	public static function structure( $course_id ) {
		$course_id = (int) $course_id;
		$key       = 'wpcpm_learn_structure_' . $course_id;
		$held      = get_transient( $key );

		if ( is_array( $held ) ) {
			return $held;
		}

		$data = self::get( 'sensei-internal/v1/course-structure/' . $course_id );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$modules = self::parse_structure( $data );

		if ( is_array( $modules ) ) {
			set_transient( $key, $modules, self::TTL );
		}

		return $modules;
	}

	/**
	 * Drop what is kept about a course, so the next read asks Learn again.
	 *
	 * @param int    $course_id The course's post ID on Learn, for its structure.
	 * @param string $url       Its link, when known, for the course itself.
	 */
	public static function forget( $course_id, $url = '' ) {
		delete_transient( 'wpcpm_learn_structure_' . (int) $course_id );

		$slug = self::slug( $url );

		if ( '' !== $slug ) {
			delete_transient( 'wpcpm_learn_course_' . $slug );
		}
	}

	/**
	 * The course out of Learn's answer to a search by slug.
	 *
	 * @param mixed $data The decoded answer: a list of courses, one at most.
	 * @return array|WP_Error `id`, `slug` and `title`, the title's entities decoded.
	 */
	public static function parse_course( $data ) {
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'wpcpm_learn_bad_answer', __( 'Learn answered something that is not a list of courses.', 'wpcredits-program-manager' ) );
		}

		if ( array() === $data ) {
			return new WP_Error( 'wpcpm_learn_no_course', __( 'Learn has no course at that address.', 'wpcredits-program-manager' ) );
		}

		$course = reset( $data );
		$title  = is_array( $course ) && isset( $course['title']['rendered'] ) ? $course['title']['rendered'] : null;

		if ( ! is_array( $course ) || ! isset( $course['id'] ) || ! is_int( $course['id'] ) || ! is_string( $title ) ) {
			return new WP_Error( 'wpcpm_learn_bad_answer', __( 'Learn answered a course without an id or a title.', 'wpcredits-program-manager' ) );
		}

		return array(
			'id'    => $course['id'],
			'slug'  => isset( $course['slug'] ) ? (string) $course['slug'] : '',
			'title' => self::words( $title ),
		);
	}

	/**
	 * The modules and lessons out of Learn's course structure answer.
	 *
	 * A lesson in draft is left out: no student sees it. A lesson listed outside every module is
	 * kept, under a module with id 0 and no title at the end, so nothing Learn lists is lost.
	 *
	 * @param mixed $data The decoded answer: a list of modules and lessons.
	 * @return array|WP_Error
	 */
	public static function parse_structure( $data ) {
		if ( ! is_array( $data ) || array_values( $data ) !== $data ) {
			return new WP_Error( 'wpcpm_learn_bad_answer', __( 'Learn answered something that is not a course structure.', 'wpcredits-program-manager' ) );
		}

		$modules = array();
		$loose   = array();

		foreach ( $data as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['type'] ) ) {
				continue;
			}

			if ( 'module' === $entry['type'] ) {
				$modules[] = array(
					'id'      => isset( $entry['id'] ) ? (int) $entry['id'] : 0,
					'title'   => isset( $entry['title'] ) ? self::words( $entry['title'] ) : '',
					'lessons' => self::lessons( isset( $entry['lessons'] ) && is_array( $entry['lessons'] ) ? $entry['lessons'] : array() ),
				);
			} elseif ( 'lesson' === $entry['type'] ) {
				$loose[] = $entry;
			}
		}

		if ( array() !== $loose ) {
			$lessons = self::lessons( $loose );

			if ( array() !== $lessons ) {
				$modules[] = array(
					'id'      => 0,
					'title'   => '',
					'lessons' => $lessons,
				);
			}
		}

		return $modules;
	}

	/**
	 * The form's group a module is, or nothing: Learn's three modules are the form's own groups
	 * (the design's 2.6), matched once case and punctuation are folded.
	 *
	 * @param string $title The module's title.
	 * @return string One of `WPCPM_Track_Definition::GROUPS`, or ''.
	 */
	public static function group_of( $title ) {
		$folded = strtolower( preg_replace( '/[^a-z]/i', '', (string) $title ) );

		return in_array( $folded, WPCPM_Track_Definition::GROUPS, true ) ? $folded : '';
	}

	/**
	 * A course's lessons by the form's group, for the track's page: a module that is no group is
	 * left out, and a group with no module is absent.
	 *
	 * @param array $modules What `structure()` answered.
	 * @return array Group => a list of lessons, each `id` and `title`.
	 */
	public static function by_group( array $modules ) {
		$by_group = array();

		foreach ( $modules as $module ) {
			$group = isset( $module['title'] ) ? self::group_of( $module['title'] ) : '';

			if ( '' === $group ) {
				continue;
			}

			if ( ! isset( $by_group[ $group ] ) ) {
				$by_group[ $group ] = array();
			}

			foreach ( isset( $module['lessons'] ) && is_array( $module['lessons'] ) ? $module['lessons'] : array() as $lesson ) {
				$by_group[ $group ][] = $lesson;
			}
		}

		return $by_group;
	}

	/**
	 * A lesson's title, found by its id across the modules; '' when the course has no such lesson.
	 *
	 * @param array $modules What `structure()` answered.
	 * @param int   $lesson_id The lesson.
	 * @return string
	 */
	public static function lesson_title( array $modules, $lesson_id ) {
		$lesson_id = (int) $lesson_id;

		foreach ( $modules as $module ) {
			foreach ( isset( $module['lessons'] ) && is_array( $module['lessons'] ) ? $module['lessons'] : array() as $lesson ) {
				if ( isset( $lesson['id'] ) && (int) $lesson['id'] === $lesson_id ) {
					return isset( $lesson['title'] ) ? (string) $lesson['title'] : '';
				}
			}
		}

		return '';
	}

	/**
	 * The published lessons of a list, each as its id and its title.
	 *
	 * @param array $entries Lesson entries as Learn lists them.
	 * @return array[]
	 */
	private static function lessons( array $entries ) {
		$lessons = array();

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['id'] ) || ! empty( $entry['draft'] ) ) {
				continue;
			}

			$lessons[] = array(
				'id'    => (int) $entry['id'],
				'title' => isset( $entry['title'] ) ? self::words( $entry['title'] ) : '',
			);
		}

		return $lessons;
	}

	/**
	 * A title as words: Learn renders titles with entities, and a heading on the form wants the
	 * characters.
	 *
	 * @param mixed $title The rendered title.
	 * @return string
	 */
	private static function words( $title ) {
		return trim( html_entity_decode( (string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * One read of Learn's API, decoded.
	 *
	 * A body that does not decode is handed on as null rather than refused here: the caller's own
	 * parser then says `wpcpm_learn_bad_answer` in the words of the reading it was making, which is
	 * what a 200 carrying something else is (the final review of T3c). Only the transport and the
	 * status code are this method's to report.
	 *
	 * @param string $path The path under the API root, query included.
	 * @return mixed|WP_Error The decoded JSON, null when the body did not decode, or
	 *                        `wpcpm_learn_unreachable`.
	 */
	private static function get( $path ) {
		$response = wp_remote_get(
			self::API . $path,
			array(
				// The value the old HEAD check used: Learn answers both of these endpoints in well
				// under a second, and a page a person is waiting on should not hold for ten (the
				// final review of T3c).
				'timeout'     => 5,
				'redirection' => 3,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'wpcpm_learn_unreachable',
				sprintf(
					/* translators: %s: what the HTTP client reported. */
					__( 'Learn WordPress did not answer (%s).', 'wpcredits-program-manager' ),
					$response->get_error_message()
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'wpcpm_learn_unreachable',
				sprintf(
					/* translators: %d: an HTTP status code. */
					__( 'Learn WordPress answered with status %d instead of the course.', 'wpcredits-program-manager' ),
					$code
				)
			);
		}

		return json_decode( (string) wp_remote_retrieve_body( $response ), true );
	}
}
