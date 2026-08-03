<?php
/**
 * [credits_program_mentors] shortcode.
 *
 * @package CreditsProgramMentors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the front-end shortcode.
 */
class CREDPRME_Shortcode {

	/**
	 * Fields shown by default (in order) when the shortcode/block doesn't
	 * specify its own `fields` list.
	 *
	 * @var string[]
	 */
	const DEFAULT_FIELDS = array(
		'Full Name',
		'Country',
		'Languages',
		'WordPress profile',
		'Sponsor Company Name',
	);

	/**
	 * Mentors are shown when this field equals STATUS_ACTIVE.
	 *
	 * @var string
	 */
	const STATUS_FIELD = 'Status';

	/**
	 * The value of the status field that marks a mentor as active.
	 *
	 * @var string
	 */
	const STATUS_ACTIVE = 'Active';

	/**
	 * Mentors with a value in this field are flagged as sponsored (badge).
	 *
	 * @var string
	 */
	const SPONSOR_FIELD = 'Sponsor Company Name';

	/**
	 * Field used for the Country filter.
	 *
	 * @var string
	 */
	const COUNTRY_FIELD = 'Country';

	/**
	 * Field used for the Language filter (multi-value).
	 *
	 * @var string
	 */
	const LANGUAGE_FIELD = 'Languages';

	/**
	 * Whether the stylesheet has been registered.
	 *
	 * @var bool
	 */
	private $assets_registered = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'credits_program_mentors', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register (but don't enqueue) the stylesheet. It's enqueued on demand
	 * when the shortcode actually runs, so pages without it stay lean.
	 */
	public function register_assets() {
		wp_register_style(
			'credits-program-mentors',
			CREDPRME_PLUGIN_URL . 'assets/css/credits-program-mentors.css',
			array(),
			CREDPRME_VERSION
		);
		wp_register_script(
			'credits-program-mentors-filters',
			CREDPRME_PLUGIN_URL . 'assets/js/filters.js',
			array(),
			CREDPRME_VERSION,
			true
		);

		// Bundled Leaflet (same version as the Education Programs Map plugin) and
		// the mentor-countries map script.
		wp_register_style( 'credprme-leaflet', CREDPRME_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.css', array(), '1.9.4' );
		wp_register_script( 'credprme-leaflet', CREDPRME_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.js', array(), '1.9.4', true );
		wp_register_script(
			'credits-program-mentors-map',
			CREDPRME_PLUGIN_URL . 'assets/js/map.js',
			array( 'credprme-leaflet' ),
			CREDPRME_VERSION,
			true
		);

		$this->assets_registered = true;
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'layout'     => 'grid',
				'limit'      => 0,
				'columns'    => 3,
				'country'    => '',
				'language'   => '',
				'search'     => '',
				'fields'     => '',
				'view'       => '',
				'photos'     => 'yes',
				'photo_size' => 116,
				'filters'    => 'yes',
				'map'        => '', // Empty = inherit the "Country map" setting.
			),
			$atts,
			'credits_program_mentors'
		);

		$show_photos  = filter_var( $atts['photos'], FILTER_VALIDATE_BOOLEAN );
		$photo_size   = max( 24, min( 512, (int) $atts['photo_size'] ) );
		$show_filters = filter_var( $atts['filters'], FILTER_VALIDATE_BOOLEAN );

		// Make sure the stylesheet is available even for late-registered contexts.
		if ( ! wp_style_is( 'credits-program-mentors', 'registered' ) ) {
			$this->register_assets();
		}
		wp_enqueue_style( 'credits-program-mentors' );

		$settings = CREDPRME_Settings::get();

		// The map defaults to the global setting; the "map" attribute overrides it.
		$show_map = '' === trim( (string) $atts['map'] )
			? ! empty( $settings['show_map'] )
			: filter_var( $atts['map'], FILTER_VALIDATE_BOOLEAN );
		$client   = new CREDPRME_Airtable_Client( $settings );

		$requested_fields = $this->parse_list( $atts['fields'] );
		if ( empty( $requested_fields ) ) {
			// Restrict to the curated field set by default.
			$requested_fields = apply_filters( 'credprme_default_fields', self::DEFAULT_FIELDS );
		}

		// Fetch every field (not just the displayed ones) so we can filter on
		// Status and detect sponsorship regardless of which fields are shown.
		$records = $client->get_records(
			array(
				'view'        => sanitize_text_field( $atts['view'] ),
				'max_records' => 0, // Filter locally, then apply the limit.
			)
		);

		if ( is_wp_error( $records ) ) {
			return $this->render_error( $records->get_error_message() );
		}

		// Only show mentors whose Status is active.
		$records = $this->only_active( $records );

		$records = $this->filter_records( $records, $atts );

		$limit = (int) $atts['limit'];
		if ( $limit > 0 ) {
			$records = array_slice( $records, 0, $limit );
		}

		if ( empty( $records ) ) {
			return '<div class="credprme-mentors credprme-mentors--empty"><p>' . esc_html__( 'No mentors to display.', 'credits-program-mentors' ) . '</p></div>';
		}

		$field_order = $this->resolve_field_order( $records, $requested_fields );

		if ( 'table' === $atts['layout'] ) {
			$list = $this->render_table( $records, $field_order, $show_photos, $photo_size );
		} else {
			$list = $this->render_grid( $records, $field_order, (int) $atts['columns'], $show_photos, $photo_size );
		}

		$map = $show_map ? CREDPRME_Map::render( $records ) : '';

		// No map and no filters: return the bare list.
		if ( ! $show_filters && '' === $map ) {
			return $list;
		}

		// The filter script powers the filter bar and applies map clicks; load it
		// whenever the map or the filter bar is present.
		if ( ! wp_script_is( 'credits-program-mentors-filters', 'registered' ) ) {
			$this->register_assets();
		}
		wp_enqueue_script( 'credits-program-mentors-filters' );

		// The map needs Leaflet and the map script.
		if ( '' !== $map ) {
			wp_enqueue_style( 'credprme-leaflet' );
			wp_enqueue_script( 'credprme-leaflet' );
			wp_enqueue_script( 'credits-program-mentors-map' );
		}

		$inner = $map;

		if ( $show_filters ) {
			$inner .= $this->render_filter_bar( $records );
			$inner .= $list;
			$inner .= '<p class="credprme-mentors__noresults" hidden>' . esc_html__( 'No mentors match the selected filters.', 'credits-program-mentors' ) . '</p>';
		} else {
			$inner .= $list;
		}

		return '<div class="credprme-mentors-wrap">' . $inner . '</div>';
	}

	/**
	 * Keep only mentors whose Status field marks them as active.
	 *
	 * @param array $records Records from Airtable.
	 * @return array
	 */
	private function only_active( array $records ) {
		$field  = apply_filters( 'credprme_status_field', self::STATUS_FIELD );
		$active = apply_filters( 'credprme_status_active_value', self::STATUS_ACTIVE );

		return array_values(
			array_filter(
				$records,
				function ( $record ) use ( $field, $active ) {
					$fields = isset( $record['fields'] ) ? $record['fields'] : array();
					if ( ! isset( $fields[ $field ] ) ) {
						return false;
					}
					$value = is_array( $fields[ $field ] ) ? implode( ', ', $fields[ $field ] ) : (string) $fields[ $field ];
					return 0 === strcasecmp( trim( $value ), trim( (string) $active ) );
				}
			)
		);
	}

	/**
	 * Whether a record has a sponsor (non-empty Sponsor Company Name).
	 *
	 * @param array $fields Record fields.
	 * @return bool
	 */
	private function is_sponsored( array $fields ) {
		if ( ! isset( $fields[ self::SPONSOR_FIELD ] ) ) {
			return false;
		}
		$value = $fields[ self::SPONSOR_FIELD ];
		if ( is_array( $value ) ) {
			return ! empty( array_filter( $value, 'strlen' ) );
		}
		return '' !== trim( (string) $value );
	}

	/**
	 * The small "Sponsored" badge shown on sponsored mentors.
	 *
	 * @return string
	 */
	private function sponsored_badge() {
		return '<span class="credprme-badge credprme-badge--sponsored"><span class="credprme-badge__dot" aria-hidden="true"></span>' . esc_html__( 'Sponsored', 'credits-program-mentors' ) . '</span>';
	}

	/**
	 * The list of languages for a record.
	 *
	 * @param array $fields Record fields.
	 * @return string[]
	 */
	private function record_languages( array $fields ) {
		if ( ! isset( $fields[ self::LANGUAGE_FIELD ] ) ) {
			return array();
		}
		$value = $fields[ self::LANGUAGE_FIELD ];
		$items = is_array( $value ) ? $value : explode( ',', (string) $value );
		return array_values( array_filter( array_map( 'trim', $items ), 'strlen' ) );
	}

	/**
	 * Build the data-* attributes used by the client-side filter.
	 *
	 * @param array $fields    Record fields.
	 * @param bool  $sponsored Whether the mentor is sponsored.
	 * @return string
	 */
	private function filter_data_attrs( array $fields, $sponsored ) {
		$langs = array();
		foreach ( $this->record_languages( $fields ) as $lang ) {
			$langs[] = strtolower( $lang );
		}
		// Wrap each value in pipes for token matching in JS.
		$lang_attr    = empty( $langs ) ? '' : '|' . implode( '|', $langs ) . '|';
		$country_keys = array_keys( $this->country_tokens( $fields ) );
		$country_attr = empty( $country_keys ) ? '' : '|' . implode( '|', $country_keys ) . '|';

		return sprintf(
			'data-sponsored="%1$s" data-countries="%2$s" data-languages="%3$s"',
			$sponsored ? '1' : '0',
			esc_attr( $country_attr ),
			esc_attr( $lang_attr )
		);
	}

	/**
	 * Resolve a record's countries into token => label pairs.
	 *
	 * Token is the map country id when known (so "United States" and
	 * "United States of America" collapse to one), else the lowercased name.
	 * Multi-country values (comma separated) yield multiple entries.
	 *
	 * @param array $fields Record fields.
	 * @return array token => display label
	 */
	private function country_tokens( array $fields ) {
		if ( ! isset( $fields[ self::COUNTRY_FIELD ] ) ) {
			return array();
		}
		$raw = is_array( $fields[ self::COUNTRY_FIELD ] ) ? implode( ',', $fields[ self::COUNTRY_FIELD ] ) : (string) $fields[ self::COUNTRY_FIELD ];

		$out = array();
		foreach ( explode( ',', $raw ) as $piece ) {
			$piece = trim( $piece );
			if ( '' === $piece ) {
				continue;
			}
			$id            = CREDPRME_Map::resolve_id( $piece );
			$token         = '' !== $id ? $id : strtolower( $piece );
			$label         = ( '' !== $id && '' !== CREDPRME_Map::label( $id ) ) ? CREDPRME_Map::label( $id ) : $piece;
			$out[ $token ] = $label;
		}
		return $out;
	}

	/**
	 * Render the interactive filter bar (Sponsored / Language / Country).
	 *
	 * @param array $records Records being displayed.
	 * @return string
	 */
	private function render_filter_bar( array $records ) {
		$countries    = array();
		$languages    = array();
		$has_sponsored = false;
		$has_plain     = false;

		foreach ( $records as $record ) {
			$fields = isset( $record['fields'] ) ? $record['fields'] : array();

			if ( $this->is_sponsored( $fields ) ) {
				$has_sponsored = true;
			} else {
				$has_plain = true;
			}

			foreach ( $this->country_tokens( $fields ) as $token => $label ) {
				$countries[ $token ] = $label;
			}

			foreach ( $this->record_languages( $fields ) as $lang ) {
				$languages[ strtolower( $lang ) ] = $lang;
			}
		}

		asort( $countries, SORT_NATURAL | SORT_FLAG_CASE );
		asort( $languages, SORT_NATURAL | SORT_FLAG_CASE );

		$controls = '';

		// Sponsored filter — only when there's a mix to choose between.
		if ( $has_sponsored && $has_plain ) {
			$controls .= $this->filter_select(
				'sponsored',
				__( 'Sponsorship', 'credits-program-mentors' ),
				array(
					''  => __( 'All mentors', 'credits-program-mentors' ),
					'1' => __( 'Sponsored', 'credits-program-mentors' ),
					'0' => __( 'Not sponsored', 'credits-program-mentors' ),
				)
			);
		}

		if ( count( $languages ) > 1 ) {
			$lang_options = array( '' => __( 'All languages', 'credits-program-mentors' ) );
			foreach ( $languages as $key => $label ) {
				$lang_options[ $key ] = $label;
			}
			$controls .= $this->filter_select( 'language', __( 'Language', 'credits-program-mentors' ), $lang_options );
		}

		if ( count( $countries ) > 1 ) {
			$country_options = array( '' => __( 'All countries', 'credits-program-mentors' ) );
			foreach ( $countries as $key => $label ) {
				$country_options[ $key ] = $label;
			}
			$controls .= $this->filter_select( 'country', __( 'Country', 'credits-program-mentors' ), $country_options );
		}

		if ( '' === $controls ) {
			return ''; // Nothing meaningful to filter by.
		}

		return '<div class="credprme-filters">' . $controls . '</div>';
	}

	/**
	 * Render one labelled filter <select>.
	 *
	 * @param string $key     Filter key (sponsored|language|country).
	 * @param string $label   Visible label.
	 * @param array  $options value => label pairs.
	 * @return string
	 */
	private function filter_select( $key, $label, array $options ) {
		$id   = 'credprme-filter-' . $key;
		$html = '<div class="credprme-filters__field"><label class="credprme-filters__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		$html .= '<select class="credprme-filters__select" id="' . esc_attr( $id ) . '" data-credprme-filter="' . esc_attr( $key ) . '">';
		foreach ( $options as $value => $text ) {
			$html .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $text ) . '</option>';
		}
		$html .= '</select></div>';
		return $html;
	}

	/**
	 * Apply country/language/search filters to the records.
	 *
	 * @param array $records Records from Airtable.
	 * @param array $atts    Shortcode attributes.
	 * @return array
	 */
	private function filter_records( array $records, array $atts ) {
		$country  = trim( (string) $atts['country'] );
		$language = trim( (string) $atts['language'] );
		$search   = trim( (string) $atts['search'] );

		if ( '' === $country && '' === $language && '' === $search ) {
			return $records;
		}

		return array_values(
			array_filter(
				$records,
				function ( $record ) use ( $country, $language, $search ) {
					$fields = isset( $record['fields'] ) ? $record['fields'] : array();

					if ( '' !== $country && ! $this->field_contains( $fields, 'Country', $country ) ) {
						return false;
					}
					if ( '' !== $language && ! $this->field_contains( $fields, 'Languages', $language ) ) {
						return false;
					}
					if ( '' !== $search ) {
						$haystack = strtolower( wp_json_encode( $fields ) );
						if ( false === strpos( $haystack, strtolower( $search ) ) ) {
							return false;
						}
					}
					return true;
				}
			)
		);
	}

	/**
	 * Case-insensitive "does this field's value contain the needle" test.
	 *
	 * @param array  $fields     Record fields.
	 * @param string $field_name Field to inspect.
	 * @param string $needle     Value to look for.
	 * @return bool
	 */
	private function field_contains( array $fields, $field_name, $needle ) {
		if ( ! isset( $fields[ $field_name ] ) ) {
			return false;
		}
		$value = is_array( $fields[ $field_name ] ) ? implode( ', ', $fields[ $field_name ] ) : (string) $fields[ $field_name ];
		return false !== stripos( $value, $needle );
	}

	/**
	 * Determine the ordered list of fields to display.
	 *
	 * @param array $records          Records.
	 * @param array $requested_fields Explicit field list from the shortcode.
	 * @return array
	 */
	private function resolve_field_order( array $records, array $requested_fields ) {
		if ( ! empty( $requested_fields ) ) {
			return $requested_fields;
		}

		// Collect every field name seen across records, preserving first-seen order.
		$order = array();
		foreach ( $records as $record ) {
			if ( empty( $record['fields'] ) ) {
				continue;
			}
			foreach ( array_keys( $record['fields'] ) as $name ) {
				if ( ! in_array( $name, $order, true ) ) {
					$order[] = $name;
				}
			}
		}
		return $order;
	}

	/**
	 * Render the card-grid layout.
	 *
	 * @param array $records     Records.
	 * @param array $field_order Ordered field names.
	 * @param int   $columns     Column count (1-6).
	 * @return string
	 */
	private function render_grid( array $records, array $field_order, $columns, $show_photos = true, $photo_size = 116 ) {
		$columns = min( 6, max( 1, (int) $columns ) );

		// Every card renders the same fixed set of vertical slots — one per
		// field (empty placeholder when a value is missing), plus a photo slot
		// and a sponsored-badge slot — so CSS subgrid can line the fields up
		// on shared row tracks across the cards in each row.
		$rows = count( $field_order ) + 1; // Fields + sponsored-badge slot.
		if ( $show_photos ) {
			$rows++;
		}

		$html = '<div class="credprme-mentors credprme-mentors--grid" style="--credprme-columns:' . esc_attr( $columns ) . ';">';
		foreach ( $records as $record ) {
			$fields    = isset( $record['fields'] ) ? $record['fields'] : array();
			$sponsored = $this->is_sponsored( $fields );
			$html     .= '<article class="credprme-card' . ( $sponsored ? ' credprme-card--sponsored' : '' ) . '" style="--credprme-rows:' . esc_attr( $rows ) . ';" ' . $this->filter_data_attrs( $fields, $sponsored ) . '>';

			if ( $show_photos ) {
				$html .= $this->render_photo( $fields, $photo_size );
			}

			foreach ( $field_order as $name ) {
				$value = array_key_exists( $name, $fields ) ? $fields[ $name ] : '';
				$role  = $this->field_role( $name );

				if ( '' === $value || array() === $value ) {
					$html .= '<div class="credprme-card__row credprme-card__row--empty" aria-hidden="true"></div>';
				} else {
					$html .= $this->render_card_field( $name, $value, $role );
				}

				// Reserve a slot for the "Sponsored" badge under the name in
				// every card (empty when the mentor isn't sponsored) so the
				// following fields stay aligned across cards.
				if ( 'title' === $role ) {
					$html .= $sponsored
						? '<div class="credprme-card__row credprme-card__row--sponsored">' . $this->sponsored_badge() . '</div>'
						: '<div class="credprme-card__row credprme-card__row--empty" aria-hidden="true"></div>';
				}
			}

			$html .= '</article>';
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * Render one field inside a card, styled per its role.
	 *
	 * @param string       $name  Field name.
	 * @param string|array $value Field value.
	 * @param string       $role  Field role.
	 * @return string
	 */
	private function render_card_field( $name, $value, $role ) {
		switch ( $role ) {
			case 'title':
				return '<h3 class="credprme-card__title">' . esc_html( $this->stringify( $value ) ) . '</h3>';

			case 'badge':
				return '<div class="credprme-card__row credprme-card__row--badge"><span class="credprme-badge">' . esc_html( $this->stringify( $value ) ) . '</span></div>';

			case 'tags':
				return '<div class="credprme-card__row credprme-card__row--tags"><span class="credprme-card__label">' . esc_html( $name ) . '</span>' . $this->render_tags( $value ) . '</div>';

			case 'link':
				$url = esc_url( $this->stringify( $value ) );
				if ( '' === $url ) {
					// Keep a slot so subgrid alignment stays consistent.
					return '<div class="credprme-card__row credprme-card__row--empty" aria-hidden="true"></div>';
				}
				return '<div class="credprme-card__row credprme-card__row--link"><a class="credprme-card__link" href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html( $name ) . ' <span aria-hidden="true">↗</span></a></div>';

			default:
				return '<div class="credprme-card__row"><span class="credprme-card__label">' . esc_html( $name ) . '</span><span class="credprme-card__value">' . esc_html( $this->stringify( $value ) ) . '</span></div>';
		}
	}

	/**
	 * Render a mentor's profile photo, resolved from their WordPress.org profile.
	 *
	 * @param array $fields  Record fields.
	 * @param int   $size    Requested image size in pixels.
	 * @param bool  $wrapped Whether to wrap the image in the card photo container.
	 * @return string
	 */
	private function render_photo( array $fields, $display_size, $wrapped = true ) {
		$display_size = max( 24, (int) $display_size );
		$name         = $this->find_name( $fields );
		$profile_url  = $this->find_profile_url( $fields );

		// Request at 2x for crisp rendering on high-DPI screens (Gravatar caps at 512).
		$avatar = '' !== $profile_url ? CREDPRME_Avatars::get_url( $profile_url, min( 512, $display_size * 2 ) ) : '';

		if ( '' === $avatar ) {
			// Not resolved yet (or no profile): show an initials placeholder so the
			// layout stays consistent while the background job fills in real photos.
			$media = $this->render_photo_placeholder( $name, $display_size );
		} else {
			$alt = '' !== $name
				/* translators: %s: mentor name. */
				? sprintf( __( 'Profile photo of %s', 'credits-program-mentors' ), $name )
				: __( 'Mentor profile photo', 'credits-program-mentors' );

			$media = sprintf(
				'<img class="credprme-photo" src="%1$s" width="%2$d" height="%2$d" style="width:%2$dpx;height:%2$dpx;" alt="%3$s" loading="lazy" decoding="async" />',
				esc_url( $avatar ),
				(int) $display_size,
				esc_attr( $alt )
			);
		}

		return $wrapped ? '<div class="credprme-card__photo">' . $media . '</div>' : $media;
	}

	/**
	 * Render an initials placeholder circle for a mentor without a resolved photo.
	 *
	 * @param string $name Mentor name.
	 * @param int    $size Size in pixels.
	 * @return string
	 */
	private function render_photo_placeholder( $name, $size ) {
		$initials = '';
		foreach ( preg_split( '/\s+/', trim( (string) $name ) ) as $word ) {
			if ( '' !== $word ) {
				$initials .= function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1 ) : substr( $word, 0, 1 );
			}
			if ( 2 <= strlen( $initials ) ) {
				break;
			}
		}
		$initials = strtoupper( $initials );

		return sprintf(
			'<span class="credprme-photo credprme-photo--placeholder" style="width:%1$dpx;height:%1$dpx;font-size:%2$dpx;" aria-hidden="true">%3$s</span>',
			(int) $size,
			(int) max( 12, round( $size / 2.5 ) ),
			esc_html( $initials )
		);
	}

	/**
	 * Find the mentor's WordPress.org profile URL in a record.
	 *
	 * Scans every field for a wordpress.org URL, preferring profiles.wordpress.org.
	 * This avoids picking up other link fields (LinkedIn, personal sites, etc.),
	 * which sort before "WordPress profile" for some mentors.
	 *
	 * @param array $fields Record fields.
	 * @return string
	 */
	private function find_profile_url( array $fields ) {
		$fallback = '';

		foreach ( $fields as $value ) {
			$url = $this->stringify( $value );
			if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
				continue;
			}

			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( ! $host || ! preg_match( '/(^|\.)wordpress\.org$/i', $host ) ) {
				continue;
			}

			if ( 0 === strcasecmp( $host, 'profiles.wordpress.org' ) ) {
				return $url; // Best match — the canonical profile host.
			}

			if ( '' === $fallback ) {
				$fallback = $url; // Any other *.wordpress.org URL.
			}
		}

		return $fallback;
	}

	/**
	 * Find the mentor's display name (first field whose role is "title").
	 *
	 * @param array $fields Record fields.
	 * @return string
	 */
	private function find_name( array $fields ) {
		foreach ( $fields as $name => $value ) {
			if ( 'title' === $this->field_role( $name ) ) {
				return $this->stringify( $value );
			}
		}
		return '';
	}

	/**
	 * Render a comma/array value as a set of tag pills.
	 *
	 * @param string|array $value Value to render.
	 * @return string
	 */
	private function render_tags( $value ) {
		$items = is_array( $value ) ? $value : array_map( 'trim', explode( ',', (string) $value ) );
		$items = array_filter( array_map( 'trim', $items ), 'strlen' );

		if ( empty( $items ) ) {
			return '';
		}

		$html = '<span class="credprme-tags">';
		foreach ( $items as $item ) {
			$html .= '<span class="credprme-tag">' . esc_html( $item ) . '</span>';
		}
		$html .= '</span>';
		return $html;
	}

	/**
	 * Render the table layout.
	 *
	 * @param array $records     Records.
	 * @param array $field_order Ordered field names.
	 * @return string
	 */
	private function render_table( array $records, array $field_order, $show_photos = true, $photo_size = 116 ) {
		$html  = '<div class="credprme-mentors credprme-mentors--table-wrap">';
		$html .= '<table class="credprme-table">';
		$html .= '<thead><tr>';
		if ( $show_photos ) {
			$html .= '<th scope="col" class="credprme-table__photo-col"><span class="screen-reader-text">' . esc_html__( 'Photo', 'credits-program-mentors' ) . '</span></th>';
		}
		foreach ( $field_order as $name ) {
			$html .= '<th scope="col">' . esc_html( $name ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';

		foreach ( $records as $record ) {
			$fields    = isset( $record['fields'] ) ? $record['fields'] : array();
			$sponsored = $this->is_sponsored( $fields );
			$html     .= '<tr ' . $this->filter_data_attrs( $fields, $sponsored ) . '>';
			if ( $show_photos ) {
				$html .= '<td class="credprme-table__photo-col" data-label="' . esc_attr__( 'Photo', 'credits-program-mentors' ) . '">' . $this->render_photo( $fields, 40, false ) . '</td>';
			}
			foreach ( $field_order as $name ) {
				$value = array_key_exists( $name, $fields ) ? $fields[ $name ] : '';
				$role  = $this->field_role( $name );
				$cell  = $this->render_table_cell( $value, $role );

				// Append the "Sponsored" badge to the mentor's name cell.
				if ( 'title' === $role && $sponsored ) {
					$cell .= ' ' . $this->sponsored_badge();
				}
				$html .= '<td data-label="' . esc_attr( $name ) . '">' . $cell . '</td>';
			}
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		return $html;
	}

	/**
	 * Render a single table cell.
	 *
	 * @param string|array $value Value.
	 * @param string       $role  Field role.
	 * @return string
	 */
	private function render_table_cell( $value, $role ) {
		if ( '' === $value || array() === $value ) {
			return '';
		}
		if ( 'link' === $role ) {
			$url = esc_url( $this->stringify( $value ) );
			if ( '' === $url ) {
				return '';
			}
			return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View', 'credits-program-mentors' ) . ' <span aria-hidden="true">↗</span></a>';
		}
		if ( 'tags' === $role ) {
			return $this->render_tags( $value );
		}
		if ( 'badge' === $role ) {
			return '<span class="credprme-badge">' . esc_html( $this->stringify( $value ) ) . '</span>';
		}
		return esc_html( $this->stringify( $value ) );
	}

	/**
	 * Map a field name to a display role. Known "Sponsored mentors" fields get
	 * tailored treatment; anything else falls back to plain text.
	 *
	 * @param string $name Field name.
	 * @return string One of: title, badge, tags, link, text.
	 */
	private function field_role( $name ) {
		$key = strtolower( trim( $name ) );

		$map = array(
			'full name'         => 'title',
			'name'              => 'title',
			'country'           => 'badge',
			'languages'         => 'tags',
			'language'          => 'tags',
			'wordpress profile' => 'link',
			'profile'           => 'link',
		);

		if ( isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}

		if ( false !== strpos( $key, 'sponsor' ) ) {
			return 'tags';
		}
		if ( false !== strpos( $key, 'profile' ) || false !== strpos( $key, 'url' ) || false !== strpos( $key, 'website' ) || false !== strpos( $key, 'link' ) ) {
			return 'link';
		}

		return apply_filters( 'credprme_field_role', 'text', $name );
	}

	/**
	 * Coerce any value into a display string.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function stringify( $value ) {
		if ( is_array( $value ) ) {
			return implode( ', ', array_map( array( $this, 'stringify' ), $value ) );
		}
		return trim( (string) $value );
	}

	/**
	 * Parse a comma-separated attribute into a trimmed list.
	 *
	 * @param string $raw Raw attribute value.
	 * @return array
	 */
	private function parse_list( $raw ) {
		if ( '' === trim( (string) $raw ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ), 'strlen' ) );
	}

	/**
	 * Render an admin-only error notice; visitors see nothing.
	 *
	 * @param string $message Error message.
	 * @return string
	 */
	private function render_error( $message ) {
		if ( current_user_can( 'manage_options' ) ) {
			return '<div class="credprme-mentors credprme-mentors--error"><strong>' . esc_html__( 'Credits Program Mentors:', 'credits-program-mentors' ) . '</strong> ' . esc_html( $message ) . '</div>';
		}
		return '';
	}
}
