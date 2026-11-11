<?php
/**
 * Regions, the country map and who an institution's country routes to.
 *
 * An Administrator is an account holding `WPCPM_Roles::CAP_MANAGE`. The `institution_areas` setting maps
 * Administrators to countries and to regions: `array( 'countries' => array( record ID => user ID ),
 * 'regions' => array( region key => user ID ) )`, one Administrator per country and per region.
 *
 * - **Seven regions.** Each country belongs to one; the plugin ships the map (`includes/data/country-regions.php`,
 *   by the country's name) and the `institution_regions` setting overrides it per country record ID.
 * - **The most specific rule wins.** A country's Administrator, else its region's. An account that no
 *   longer holds the capability counts as nobody, so the region is asked next.
 * - **Never trusted as stored.** `areas()` drops a malformed entry on every read.
 *
 * This class decides nothing about who may edit the areas: the settings screen asks the capability.
 *
 * @package WPCredits_Program_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Areas of responsibility, and the rule that resolves a country to an Administrator.
 */
class WPCPM_Institution_Areas {

	/** The setting holding each Administrator's countries and regions. */
	const SETTING = 'institution_areas';

	/** The setting holding the per-country region overrides: country record ID => region key. */
	const SETTING_REGIONS = 'institution_regions';

	/**
	 * The shipped country map, read once a request.
	 *
	 * @var array<string, string>|null
	 */
	private static $shipped = null;

	/**
	 * The regions.
	 *
	 * @return array<string, string> Region key => label, in display order.
	 */
	public static function regions() {
		return array(
			'africa'        => __( 'Africa', 'wpcredits-program-manager' ),
			'asia'          => __( 'Asia', 'wpcredits-program-manager' ),
			'europe'        => __( 'Europe', 'wpcredits-program-manager' ),
			'latin-america' => __( 'Latin America and the Caribbean', 'wpcredits-program-manager' ),
			'middle-east'   => __( 'Middle East', 'wpcredits-program-manager' ),
			'north-america' => __( 'North America', 'wpcredits-program-manager' ),
			'oceania'       => __( 'Oceania', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The region the plugin ships for a country name.
	 *
	 * @param string $country_name The name as the Countries table spells it.
	 * @return string A region key, or '' for a name the map does not hold.
	 */
	public static function shipped_region( $country_name ) {
		if ( null === self::$shipped ) {
			$map           = include WPCPM_PLUGIN_DIR . 'includes/data/country-regions.php';
			self::$shipped = is_array( $map ) ? $map : array();
		}

		$country_name = (string) $country_name;

		return isset( self::$shipped[ $country_name ] ) ? (string) self::$shipped[ $country_name ] : '';
	}

	/**
	 * A country's region: the override on the site, else the shipped map's.
	 *
	 * @param string $country_id Countries record ID.
	 * @return string A region key, or '' for none.
	 */
	public static function region_of( $country_id ) {
		$country_id = (string) $country_id;
		$overrides  = WPCPM_Settings::get_value( self::SETTING_REGIONS, array() );

		// A value that is no string, or names no region, is no override: an array here is a malformed
		// setting, and indexing the regions by it would throw.
		if ( is_array( $overrides ) && isset( $overrides[ $country_id ] ) && is_string( $overrides[ $country_id ] ) && isset( self::regions()[ $overrides[ $country_id ] ] ) ) {
			return $overrides[ $country_id ];
		}

		return self::shipped_region( WPCPM_Countries::name_of( $country_id ) );
	}

	/**
	 * Every country grouped by its region.
	 *
	 * @return array<string, array<string, string>> Region key ('' for no region) => country ID => name, A to Z.
	 */
	public static function countries_by_region() {
		$out = array( '' => array() );

		foreach ( array_keys( self::regions() ) as $key ) {
			$out[ $key ] = array();
		}

		foreach ( WPCPM_Countries::all() as $id => $row ) {
			$out[ self::region_of( $id ) ][ (string) $id ] = (string) $row['name'];
		}

		foreach ( $out as $key => $countries ) {
			$out[ $key ] = self::sorted( $countries );
		}

		return $out;
	}

	/**
	 * The stored areas, malformed entries dropped.
	 *
	 * @return array{countries: array<string, int>, regions: array<string, int>}
	 */
	public static function areas() {
		$stored = WPCPM_Settings::get_value( self::SETTING, array() );
		$out    = array(
			'countries' => array(),
			'regions'   => array(),
		);

		if ( ! is_array( $stored ) ) {
			return $out;
		}

		$regions = self::regions();

		foreach ( array( 'countries', 'regions' ) as $kind ) {
			$entries = ( isset( $stored[ $kind ] ) && is_array( $stored[ $kind ] ) ) ? $stored[ $kind ] : array();

			foreach ( $entries as $key => $user_id ) {
				$key = (string) $key;

				if ( 'countries' === $kind ? ! WPCPM_Mentors_Sync::is_record_id( $key ) : ! isset( $regions[ $key ] ) ) {
					continue;
				}

				if ( ! is_scalar( $user_id ) || ! ctype_digit( (string) $user_id ) || (int) $user_id < 1 ) {
					continue;
				}

				$out[ $kind ][ $key ] = (int) $user_id;
			}
		}

		return $out;
	}

	/**
	 * Whether an account is an Administrator.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private static function is_administrator( $user_id ) {
		return $user_id > 0 && user_can( $user_id, WPCPM_Roles::CAP_MANAGE );
	}

	/**
	 * The Administrator a country routes to: its own, else its region's.
	 *
	 * @param string $country_id Countries record ID.
	 * @return int User ID, 0 for none.
	 */
	public static function administrator_for( $country_id ) {
		$country_id = (string) $country_id;
		$areas      = self::areas();

		if ( isset( $areas['countries'][ $country_id ] ) && self::is_administrator( $areas['countries'][ $country_id ] ) ) {
			return $areas['countries'][ $country_id ];
		}

		$region = self::region_of( $country_id );

		if ( '' !== $region && isset( $areas['regions'][ $region ] ) && self::is_administrator( $areas['regions'][ $region ] ) ) {
			return $areas['regions'][ $region ];
		}

		return 0;
	}

	/**
	 * What an Administrator is responsible for.
	 *
	 * @param int $user_id User ID.
	 * @return array{countries: string[], regions: string[]} Country record IDs and region keys.
	 */
	public static function areas_of( $user_id ) {
		$user_id = (int) $user_id;
		$areas   = self::areas();

		return array(
			'countries' => array_keys( $areas['countries'], $user_id, true ),
			'regions'   => array_keys( $areas['regions'], $user_id, true ),
		);
	}

	/**
	 * Whether an account has a country or a region.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function has_area( $user_id ) {
		$mine = self::areas_of( $user_id );

		return array() !== $mine['countries'] || array() !== $mine['regions'];
	}

	/**
	 * Every country no rule gives an Administrator.
	 *
	 * @return array<string, string> Country ID => name, A to Z.
	 */
	public static function gaps() {
		$out = array();

		foreach ( WPCPM_Countries::all() as $id => $row ) {
			if ( 0 === self::administrator_for( $id ) ) {
				$out[ (string) $id ] = (string) $row['name'];
			}
		}

		return self::sorted( $out );
	}

	/**
	 * Clean the areas the settings form posted.
	 *
	 * The screen asks per place, a region's one select and a country's one row, so one Administrator
	 * per region and per country holds by construction. What it can still do is give a country twice,
	 * through the add row while the country already has its row, and that is refused, naming the
	 * country. A key the post does not carry keeps its stored value; one posted empty is taken away.
	 *
	 * @param mixed $raw    The posted `institution_areas`: `regions` and `countries` (key => user ID, or
	 *                      '' for nobody), and `add_country` with `add_user`.
	 * @param array $stored The areas as stored.
	 * @return array|WP_Error The areas as `areas()` holds them, or the refusal: `wpcpm_areas_not_admin`
	 *                        for an account without the manage capability, `wpcpm_areas_twice` for a
	 *                        country added that already has its own Administrator.
	 */
	public static function clean( $raw, array $stored ) {
		$out = array(
			'countries' => isset( $stored['countries'] ) && is_array( $stored['countries'] ) ? $stored['countries'] : array(),
			'regions'   => isset( $stored['regions'] ) && is_array( $stored['regions'] ) ? $stored['regions'] : array(),
		);

		if ( ! is_array( $raw ) ) {
			return $out;
		}

		$regions = self::regions();
		$posted  = array();

		foreach ( array( 'regions', 'countries' ) as $kind ) {
			$rows = ( isset( $raw[ $kind ] ) && is_array( $raw[ $kind ] ) ) ? $raw[ $kind ] : array();

			foreach ( $rows as $key => $user ) {
				$key = (string) $key;

				if ( 'regions' === $kind ? ! isset( $regions[ $key ] ) : ! WPCPM_Mentors_Sync::is_record_id( $key ) ) {
					continue;
				}

				$posted[ $kind ][ $key ] = $user;
			}
		}

		$add_country = isset( $raw['add_country'] ) && is_string( $raw['add_country'] ) ? trim( $raw['add_country'] ) : '';
		$add_user    = isset( $raw['add_user'] ) ? $raw['add_user'] : '';

		// An add row with no Administrator, or with a country the table does not hold, adds nothing.
		if ( '' === $add_country || '' === $add_user || '' === WPCPM_Countries::name_of( $add_country ) ) {
			$add_country = '';
		}

		// Every account the post names must be an Administrator, whichever field it came in.
		$accounts = array();

		foreach ( $posted as $kind => $rows ) {
			foreach ( $rows as $key => $user ) {
				if ( '' !== $user ) {
					$accounts[ $kind . '/' . $key ] = $user;
				}
			}
		}

		if ( '' !== $add_country ) {
			$accounts['add'] = $add_user;
		}

		foreach ( $accounts as $user ) {
			if ( ! is_scalar( $user ) || ! ctype_digit( (string) $user ) || ! self::is_administrator( (int) $user ) ) {
				return new WP_Error( 'wpcpm_areas_not_admin', __( 'That account is not an Administrator, so the areas were not saved.', 'wpcredits-program-manager' ) );
			}
		}

		foreach ( array( 'regions', 'countries' ) as $kind ) {
			if ( empty( $posted[ $kind ] ) ) {
				continue;
			}

			foreach ( $posted[ $kind ] as $key => $user ) {
				if ( '' === $user ) {
					unset( $out[ $kind ][ $key ] );
				} else {
					$out[ $kind ][ $key ] = (int) $user;
				}
			}
		}

		if ( '' !== $add_country ) {
			if ( isset( $out['countries'][ $add_country ] ) ) {
				$holder = get_userdata( (int) $out['countries'][ $add_country ] );

				return new WP_Error(
					'wpcpm_areas_twice',
					sprintf(
						/* translators: 1: a country, 2: the name of the Administrator who has it. */
						__( '%1$s is already given to %2$s. Change it in its own row, so the country has one Administrator.', 'wpcredits-program-manager' ),
						WPCPM_Countries::name_of( $add_country ),
						$holder ? (string) $holder->display_name : '#' . (int) $out['countries'][ $add_country ]
					)
				);
			}

			$out['countries'][ $add_country ] = (int) $add_user;
		}

		return $out;
	}

	/**
	 * Clean the region overrides after a move.
	 *
	 * @param mixed $raw    The posted `institution_regions`: `move_country` (a Countries record ID) and
	 *                      `move_region` (a region key, or '' for the region the plugin ships).
	 * @param array $stored The overrides as stored: country record ID => region key.
	 * @return array<string, string> The overrides after the move; a move back to the shipped region
	 *                               removes the country's override.
	 */
	public static function clean_regions( $raw, array $stored ) {
		$regions = self::regions();
		$out     = array();

		foreach ( $stored as $country_id => $region ) {
			if ( is_string( $region ) && isset( $regions[ $region ] ) && WPCPM_Mentors_Sync::is_record_id( (string) $country_id ) ) {
				$out[ (string) $country_id ] = $region;
			}
		}

		if ( ! is_array( $raw ) || ! isset( $raw['move_country'], $raw['move_region'] ) || ! is_string( $raw['move_country'] ) || ! is_string( $raw['move_region'] ) ) {
			return $out;
		}

		$country_id = trim( $raw['move_country'] );
		$region     = trim( $raw['move_region'] );
		$name       = WPCPM_Countries::name_of( $country_id );

		if ( '' === $name || ( '' !== $region && ! isset( $regions[ $region ] ) ) ) {
			return $out;
		}

		if ( '' === $region || self::shipped_region( $name ) === $region ) {
			unset( $out[ $country_id ] );
		} else {
			$out[ $country_id ] = $region;
		}

		return $out;
	}

	/**
	 * Draw the Areas of responsibility section of Settings > Institutions: its heading, as every
	 * section has it, and the fields the settings form posts, and nothing that saves them (the
	 * screen's own nonce and button do).
	 *
	 * @param array $settings The settings the screen is drawn with; the areas are read through
	 *                        `areas()`, which drops what is malformed.
	 */
	public static function render_settings( array $settings ) {
		$areas     = self::areas();
		$regions   = self::regions();
		$admins    = array();
		$countries = self::sorted( WPCPM_Countries::options() );

		$admins = self::sorted( WPCPM_Settings_Choices::manager_accounts() );

		WPCPM_Settings_Screen::section_heading(
			__( 'Areas of responsibility', 'wpcredits-program-manager' ),
			__( "Which Administrator a new institution is assigned to: the country's own Administrator first, then the region's.", 'wpcredits-program-manager' ),
			'institution-areas-of-responsibility'
		);
		echo '<table class="form-table" role="presentation"><tbody>';

		// One select per region.
		$regions_label = __( 'Regions', 'wpcredits-program-manager' );

		echo '<tr><th scope="row">' . esc_html( $regions_label ) . '</th><td>';

		foreach ( $regions as $key => $label ) {
			$id = 'wpcpm-areas-region-' . $key;

			printf( '<p><label for="%1$s">%2$s</label> ', esc_attr( $id ), esc_html( $label ) );
			self::admin_select( 'institution_areas[regions][' . $key . ']', $id, $admins, isset( $areas['regions'][ $key ] ) ? $areas['regions'][ $key ] : 0 );
			echo '</p>';
		}

		WPCPM_Settings_Rows::close_row( $regions_label );

		// One row per country with its own Administrator, then the row that adds one.
		$countries_label = __( 'Countries', 'wpcredits-program-manager' );

		echo '<tr><th scope="row">' . esc_html( $countries_label ) . '</th><td>';

		foreach ( $countries as $country_id => $name ) {
			if ( ! isset( $areas['countries'][ $country_id ] ) ) {
				continue;
			}

			$id = 'wpcpm-areas-country-' . $country_id;

			printf( '<p><label for="%1$s">%2$s</label> ', esc_attr( $id ), esc_html( $name ) );
			self::admin_select( 'institution_areas[countries][' . $country_id . ']', $id, $admins, $areas['countries'][ $country_id ] );
			echo '</p>';
		}

		echo '<p class="description">' . esc_html__( 'A country listed here goes to its own Administrator, whatever its region says.', 'wpcredits-program-manager' ) . '</p>';
		WPCPM_Settings_Rows::close_row( $countries_label );

		$add_label = __( 'Add a country', 'wpcredits-program-manager' );

		echo '<tr><th scope="row">' . esc_html( $add_label ) . '</th><td><div class="wpcpm-admin-combo">';
		WPCPM_Dashboards::render_combo(
			array(
				'id'      => 'wpcpm-areas-add-country',
				'name'    => 'institution_areas[add_country]',
				'options' => array( '' => __( 'Choose a country', 'wpcredits-program-manager' ) ) + $countries,
				'current' => '',
				'label'   => __( 'Country', 'wpcredits-program-manager' ),
				'find'    => __( 'Type a country', 'wpcredits-program-manager' ),
				'none'    => __( 'No country matches.', 'wpcredits-program-manager' ),
			)
		);
		echo '</div><p><label for="wpcpm-areas-add-user">' . esc_html__( 'Administrator', 'wpcredits-program-manager' ) . '</label> ';
		self::admin_select( 'institution_areas[add_user]', 'wpcpm-areas-add-user', $admins, 0 );
		echo '</p>';
		WPCPM_Settings_Rows::close_row( $add_label );

		// Who has what: a line per Administrator with an area.
		$who_label = __( 'Who has what', 'wpcredits-program-manager' );

		echo '<tr><th scope="row">' . esc_html( $who_label ) . '</th><td>';

		$lines = array();

		foreach ( $admins as $user_id => $display ) {
			$mine  = self::areas_of( (int) $user_id );
			$parts = array();

			foreach ( $mine['regions'] as $key ) {
				$parts[] = $regions[ $key ];
			}

			foreach ( $mine['countries'] as $country_id ) {
				$parts[] = WPCPM_Countries::name_of( $country_id );
			}

			if ( array() !== $parts ) {
				$lines[] = $display . ': ' . implode( '; ', $parts );
			}
		}

		if ( array() === $lines ) {
			echo '<p>' . esc_html__( 'No Administrator has an area yet.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			echo '<ul>';

			foreach ( $lines as $line ) {
				echo '<li>' . esc_html( $line ) . '</li>';
			}

			echo '</ul>';
		}

		WPCPM_Settings_Rows::close_row( $who_label );

		// The move, and under it the map of countries to regions in the row's closed fold.
		$map_label = __( 'Countries by region', 'wpcredits-program-manager' );

		echo '<tr><th scope="row">' . esc_html( $map_label ) . '</th><td><div class="wpcpm-admin-combo">';
		WPCPM_Dashboards::render_combo(
			array(
				'id'      => 'wpcpm-areas-move-country',
				'name'    => 'institution_regions[move_country]',
				'options' => array( '' => __( 'Choose a country', 'wpcredits-program-manager' ) ) + $countries,
				'current' => '',
				'label'   => __( 'Move a country', 'wpcredits-program-manager' ),
				'find'    => __( 'Type a country', 'wpcredits-program-manager' ),
				'none'    => __( 'No country matches.', 'wpcredits-program-manager' ),
			)
		);
		echo '</div><p><label for="wpcpm-areas-move-region">' . esc_html__( 'to', 'wpcredits-program-manager' ) . '</label> ';
		printf( '<select name="institution_regions[move_region]" id="wpcpm-areas-move-region"><option value="">%s</option>', esc_html__( 'The region the plugin ships', 'wpcredits-program-manager' ) );

		foreach ( $regions as $key => $label ) {
			printf( '<option value="%1$s">%2$s</option>', esc_attr( $key ), esc_html( $label ) );
		}

		echo '</select></p>';
		echo '<p class="description">' . esc_html__( "A move is saved with the rest of this tab, and a move back to the plugin's own region takes it away.", 'wpcredits-program-manager' ) . '</p>';

		WPCPM_Settings_Rows::open_details( $map_label );

		foreach ( self::countries_by_region() as $key => $members ) {
			if ( '' === $key && array() === $members ) {
				continue;
			}

			printf(
				'<h3>%1$s</h3><p>%2$s</p>',
				esc_html( '' === $key ? __( 'No region', 'wpcredits-program-manager' ) : $regions[ $key ] ),
				esc_html( implode( ', ', $members ) )
			);
		}

		WPCPM_Settings_Rows::close_details();
		WPCPM_Settings_Rows::close_row( $map_label );

		echo '</tbody></table>';
	}

	/**
	 * A select of the Administrators, with Nobody first.
	 *
	 * @param string                    $name    The posted name.
	 * @param string                    $id      The select's ID, which its label names.
	 * @param array<int|string, string> $admins  User ID => name.
	 * @param int                       $current The user ID chosen, 0 for nobody.
	 */
	private static function admin_select( $name, $id, array $admins, $current ) {
		printf( '<select name="%1$s" id="%2$s"><option value="">%3$s</option>', esc_attr( $name ), esc_attr( $id ), esc_html__( 'Nobody', 'wpcredits-program-manager' ) );

		foreach ( $admins as $user_id => $display ) {
			printf( '<option value="%1$d"%2$s>%3$s</option>', (int) $user_id, selected( (int) $user_id, (int) $current, false ), esc_html( $display ) );
		}

		echo '</select>';
	}

	/**
	 * Names A to Z the way a reader expects them, accents folded ("São Tomé" among the Sa names, not
	 * after Zimbabwe), the same comparison the dashboards' lists use. Two names alike are settled by
	 * their IDs, so the order does not change between reads.
	 *
	 * @param array<int|string, string> $names ID => name.
	 * @return array<int|string, string> The same pairs, sorted by name.
	 */
	private static function sorted( array $names ) {
		uksort(
			$names,
			static function ( $a, $b ) use ( $names ) {
				$order = WPCPM_Dashboards::compare_names( $names[ $a ], $names[ $b ] );

				return 0 !== $order ? $order : strnatcmp( (string) $a, (string) $b );
			}
		);

		return $names;
	}
}
