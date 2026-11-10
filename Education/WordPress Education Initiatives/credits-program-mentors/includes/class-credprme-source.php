<?php
/**
 * Where the mentor records come from.
 *
 * @package CreditsProgramMentors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Picks the source of the directory's records: the site's own list of mentors, or Airtable.
 *
 * A site that runs WPCredits Program Manager 1.123.62 or later keeps a public list of its active
 * mentors and offers it through the filter `wpcpm_public_mentors` (null in, the list out). When the
 * list is there the directory reads it and makes no Airtable request at all. Without it (another
 * site, or before that plugin's first sync) the directory reads Airtable as it always has.
 *
 * The "Mentor source" setting can pin either one: `site` never falls back to Airtable, and
 * `airtable` never asks the site.
 */
class CREDPRME_Source {

	/** The filter a site answers with its list of active mentors. */
	const SITE_FILTER = 'wpcpm_public_mentors';

	/** The key that marks a record from the site's list as a sponsored mentor's. */
	const SPONSORED_KEY = '_credprme_sponsored';

	/**
	 * The records to display, in the shape the Airtable client returns them.
	 *
	 * @param array $settings Plugin settings.
	 * @param array $args     Query arguments for the Airtable client (unused for the site's list).
	 * @return array|WP_Error Records ( each: [ 'id' => string, 'fields' => array ] ) or WP_Error.
	 */
	public static function get_records( array $settings, array $args = array() ) {
		$source = isset( $settings['source'] ) ? $settings['source'] : 'auto';

		if ( 'airtable' !== $source ) {
			$list = self::site_list();

			if ( null !== $list ) {
				return self::from_site( $list );
			}

			if ( 'site' === $source ) {
				return new WP_Error( 'credprme_no_site_list', __( 'This site offers no list of mentors yet. It comes from WPCredits Program Manager 1.123.62 or later, after its next mentors sync. Until then, set the mentor source to Automatic or Airtable.', 'credits-program-mentors' ) );
			}
		}

		$client = new CREDPRME_Airtable_Client( $settings );

		return $client->get_records( $args );
	}

	/**
	 * Which source a request would read now: `site` or `airtable`.
	 *
	 * @param array $settings Plugin settings.
	 * @return string
	 */
	public static function current( array $settings ) {
		$source = isset( $settings['source'] ) ? $settings['source'] : 'auto';

		if ( 'airtable' === $source ) {
			return 'airtable';
		}

		return ( 'site' === $source || null !== self::site_list() ) ? 'site' : 'airtable';
	}

	/**
	 * The site's list of active mentors, or null when the site offers none.
	 *
	 * @return array[]|null
	 */
	public static function site_list() {
		$list = apply_filters( self::SITE_FILTER, null );

		return is_array( $list ) ? $list : null;
	}

	/**
	 * Turn the site's list into records with the field names the directory displays.
	 *
	 * @param array[] $rows Rows of `name`, `profile`, `country`, `languages` (string[]), `sponsored` and `sponsors` (string[]).
	 * @return array
	 */
	private static function from_site( array $rows ) {
		$records = array();

		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) || empty( $row['name'] ) || ! is_scalar( $row['name'] ) ) {
				continue;
			}

			$languages = isset( $row['languages'] ) ? $row['languages'] : array();
			$languages = is_array( $languages ) ? implode( ', ', array_filter( array_map( 'strval', array_filter( $languages, 'is_scalar' ) ), 'strlen' ) ) : (string) $languages;

			$fields = array(
				'Full Name' => (string) $row['name'],
				'Status'    => CREDPRME_Shortcode::STATUS_ACTIVE,
			);

			if ( ! empty( $row['country'] ) && is_scalar( $row['country'] ) ) {
				$fields['Country'] = (string) $row['country'];
			}
			if ( '' !== $languages ) {
				$fields['Languages'] = $languages;
			}
			if ( ! empty( $row['profile'] ) && is_scalar( $row['profile'] ) ) {
				$fields['WordPress profile'] = (string) $row['profile'];
			}
			$sponsors = ( isset( $row['sponsors'] ) && is_array( $row['sponsors'] ) ) ? implode( ', ', array_filter( array_map( 'strval', array_filter( $row['sponsors'], 'is_scalar' ) ), 'strlen' ) ) : '';

			if ( '' !== $sponsors ) {
				$fields[ CREDPRME_Shortcode::SPONSOR_FIELD ] = $sponsors;
			}
			if ( ! empty( $row['sponsored'] ) || '' !== $sponsors ) {
				$fields[ self::SPONSORED_KEY ] = '1';
			}

			$records[] = array(
				'id'     => 'site-' . $index,
				'fields' => $fields,
			);
		}

		return $records;
	}
}
