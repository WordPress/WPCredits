<?php
/**
 * Regions, the country map and who an institution's country routes to (Institutions off Airtable, R1).
 *
 * What is pinned here and why:
 *
 * - **Seven regions, in a fixed order.** The areas screen and the map read them by key.
 * - **Every country the base holds has a region.** The shipped map covers all 196 names of the fixture.
 * - **The most specific rule wins.** A country's Administrator before its region's; an account that no
 *   longer holds the manage capability counts as nobody, so the region is asked next.
 * - **A malformed stored area is dropped, not trusted.**
 *
 * Run from the plugin root:  php bin/test-institution-areas.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['settings'] = array();
$GLOBALS['managers'] = array( 11, 12 );
$GLOBALS['countries'] = array();

function __( $s, $d = null ) { return $s; }
function user_can( $id, $cap ) { return 'wpcpm_manage_program' === $cap && in_array( (int) $id, $GLOBALS['managers'], true ); }

class WPCPM_Roles { const CAP_MANAGE = 'wpcpm_manage_program'; }

class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) { return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback; }
}

class WPCPM_Mentors_Sync {
	public static function is_record_id( $value ) { return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
}


class WP_Error {
	public $code, $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function selected( $a, $b, $echo = true ) { $r = (string) $a === (string) $b ? ' selected="selected"' : ''; if ( $echo ) { echo $r; } return $r; }
function wp_unslash( $v ) { return $v; }
// The accent folding WordPress's remove_accents() does, for the names this suite holds.
function remove_accents( $s ) { return strtr( $s, array( 'ã' => 'a', 'é' => 'e', 'í' => 'i', 'ô' => 'o', 'ç' => 'c', 'Å' => 'A', 'ü' => 'u', 'Ü' => 'U', 'ï' => 'i' ) ); }
function get_userdata( $id ) { $names = array( 11 => 'Ana Admin', 12 => 'Bo Boss', 13 => 'Cy Client' ); return isset( $names[ (int) $id ] ) ? (object) array( 'ID' => (int) $id, 'display_name' => $names[ (int) $id ] ) : false; }

class WPCPM_Settings_Screen {
	public static function section_heading( $title, $intro, $anchor ) { echo '<h2>' . esc_html( $title ) . '</h2><p class="description wpcpm-settings__intro">' . esc_html( $intro ) . '</p>'; }
}
class WPCPM_Settings_Rows {
	public static function open_details( $about = '' ) { echo '<details class="wpcpm-settings__details"><summary>Details<span class="screen-reader-text"> about ' . esc_html( $about ) . '</span></summary>'; }
	public static function close_details() { echo '</details>'; }
	public static function close_row( $label, $details = '' ) { echo '</td></tr>'; }
}
class WPCPM_Dashboards {
	public static $combos = array();
	public static function compare_names( $a, $b ) { return strnatcasecmp( mb_strtolower( remove_accents( $a ), 'UTF-8' ), mb_strtolower( remove_accents( $b ), 'UTF-8' ) ); }
	public static function render_combo( array $args ) { self::$combos[] = $args; echo '[combo name="' . esc_attr( $args['name'] ) . '"]'; }
}
class WPCPM_Settings_Choices {
	public static function manager_accounts() { $o = array(); foreach ( $GLOBALS['managers'] as $id ) { $o[ $id ] = get_userdata( $id )->display_name; } return $o; }
}

class WPCPM_Countries {
	public static function all() { return $GLOBALS['countries']; }
	public static function options() { $o = array(); foreach ( $GLOBALS['countries'] as $id => $row ) { $o[ $id ] = $row['name']; } natcasesort( $o ); return $o; }
	public static function name_of( $id ) { return isset( $GLOBALS['countries'][ $id ] ) ? $GLOBALS['countries'][ $id ]['name'] : ''; }
}

$fixture = json_decode( file_get_contents( __DIR__ . '/fixtures/institutions-index-seed.json' ), true );
$ids     = array();
foreach ( $fixture['countries'] as $row ) {
	$GLOBALS['countries'][ $row['id'] ] = array( 'name' => $row['name'] );
	$ids[ $row['name'] ]                = $row['id'];
}

$failures = 0;
$checks   = 0;
function check( $label, $ok ) {
	global $failures, $checks;
	++$checks;
	if ( ! $ok ) {
		++$failures;
		echo "FAIL: {$label}\n";
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-areas.php';

$keys = array( 'africa', 'asia', 'europe', 'latin-america', 'middle-east', 'north-america', 'oceania' );
check( 'regions: seven keys in order', array_keys( WPCPM_Institution_Areas::regions() ) === $keys );
check( 'regions: labels', array(
	'africa' => 'Africa', 'asia' => 'Asia', 'europe' => 'Europe', 'latin-america' => 'Latin America and the Caribbean',
	'middle-east' => 'Middle East', 'north-america' => 'North America', 'oceania' => 'Oceania',
) === WPCPM_Institution_Areas::regions() );

check( 'fixture has 196 countries', 196 === count( $ids ) );
$bad = array();
foreach ( array_keys( $ids ) as $name ) {
	if ( ! in_array( WPCPM_Institution_Areas::shipped_region( $name ), $keys, true ) ) {
		$bad[] = $name;
	}
}
check( 'every country has a region (missing: ' . implode( ', ', $bad ) . ')', array() === $bad );
check( 'unknown name has no region', '' === WPCPM_Institution_Areas::shipped_region( 'Atlantis' ) );

$pinned = array(
	'europe'        => array( 'Poland', 'Romania', 'Turkey', 'Cyprus' ),
	'asia'          => array( 'Pakistan', 'Japan', 'Armenia', 'Hong Kong / Macao' ),
	'africa'        => array( 'Nigeria', 'Egypt', 'Western Sahara' ),
	'latin-america' => array( 'Mexico', 'Brazil', 'Jamaica' ),
	'middle-east'   => array( 'Saudi Arabia', 'Israel', 'Iran', 'Palestine' ),
	'north-america' => array( 'United States', 'Canada' ),
	'oceania'       => array( 'Australia', 'Papua New Guinea' ),
);
foreach ( $pinned as $region => $names ) {
	foreach ( $names as $name ) {
		check( "{$name} is {$region}", $region === WPCPM_Institution_Areas::shipped_region( $name ) );
	}
}

// region_of follows an override.
check( 'region_of: shipped', 'africa' === WPCPM_Institution_Areas::region_of( $ids['Egypt'] ) );
$GLOBALS['settings']['institution_regions'] = array( $ids['Egypt'] => 'middle-east' );
check( 'region_of: override', 'middle-east' === WPCPM_Institution_Areas::region_of( $ids['Egypt'] ) );
check( 'region_of: unknown ID is empty', '' === WPCPM_Institution_Areas::region_of( 'recNOPE' ) );
$GLOBALS['countries']['recXXXXXXXXXXXXXX'] = array( 'name' => 'Atlantis' );
check( 'region_of: a name the map lacks is empty', '' === WPCPM_Institution_Areas::region_of( 'recXXXXXXXXXXXXXX' ) );

$by = WPCPM_Institution_Areas::countries_by_region();
check( 'countries_by_region: Egypt moved to the Middle East', isset( $by['middle-east'][ $ids['Egypt'] ] ) && ! isset( $by['africa'][ $ids['Egypt'] ] ) );
check( 'countries_by_region: unmapped under the empty key', array( 'recXXXXXXXXXXXXXX' => 'Atlantis' ) === $by[''] );
check( 'countries_by_region: A to Z', array_values( $by['europe'] ) === ( function ( $v ) { usort( $v, array( 'WPCPM_Dashboards', 'compare_names' ) ); return $v; } )( array_values( $by['europe'] ) ) );
$GLOBALS['settings']['institution_regions'] = array();
unset( $GLOBALS['countries']['recXXXXXXXXXXXXXX'] );

// Resolution.
$GLOBALS['settings'][ WPCPM_Institution_Areas::SETTING ] = array(
	'countries' => array( $ids['Poland'] => 11 ),
	'regions'   => array( 'europe' => 12 ),
);
check( 'resolve: country rule wins', 11 === WPCPM_Institution_Areas::administrator_for( $ids['Poland'] ) );
check( 'resolve: region rule', 12 === WPCPM_Institution_Areas::administrator_for( $ids['Romania'] ) );
check( 'resolve: nobody', 0 === WPCPM_Institution_Areas::administrator_for( $ids['Japan'] ) );
$GLOBALS['managers'] = array( 12 );
check( 'resolve: lost capability asks the region', 12 === WPCPM_Institution_Areas::administrator_for( $ids['Poland'] ) );
$GLOBALS['managers'] = array( 11, 12 );
$GLOBALS['settings'][ WPCPM_Institution_Areas::SETTING ]['countries'][ $ids['Japan'] ] = 13;
check( 'resolve: account without the capability is nobody', 0 === WPCPM_Institution_Areas::administrator_for( $ids['Japan'] ) );
unset( $GLOBALS['settings'][ WPCPM_Institution_Areas::SETTING ]['countries'][ $ids['Japan'] ] );

check( 'areas_of region', array( 'countries' => array(), 'regions' => array( 'europe' ) ) === WPCPM_Institution_Areas::areas_of( 12 ) );
check( 'areas_of country', array( 'countries' => array( $ids['Poland'] ), 'regions' => array() ) === WPCPM_Institution_Areas::areas_of( 11 ) );
check( 'has_area true', WPCPM_Institution_Areas::has_area( 12 ) );
check( 'has_area false', ! WPCPM_Institution_Areas::has_area( 13 ) );

$gaps = WPCPM_Institution_Areas::gaps();
check( 'gaps hold Japan', isset( $gaps[ $ids['Japan'] ] ) && 'Japan' === $gaps[ $ids['Japan'] ] );
check( 'gaps skip Poland and Romania', ! isset( $gaps[ $ids['Poland'] ] ) && ! isset( $gaps[ $ids['Romania'] ] ) );
check( 'gaps A to Z', array_values( $gaps ) === ( function ( $v ) { usort( $v, array( 'WPCPM_Dashboards', 'compare_names' ) ); return $v; } )( array_values( $gaps ) ) );

// Malformed stored areas are dropped.
$GLOBALS['settings'][ WPCPM_Institution_Areas::SETTING ] = array(
	'countries' => array( $ids['Poland'] => 11, 'notanid' => 11, $ids['Japan'] => 0, $ids['Mexico'] => '12', $ids['Brazil'] => 'x' ),
	'regions'   => array( 'europe' => 12, 'atlantis' => 11, 'asia' => -3 ),
);
check( 'areas: malformed dropped', array(
	'countries' => array( $ids['Poland'] => 11, $ids['Mexico'] => 12 ),
	'regions'   => array( 'europe' => 12 ),
) === WPCPM_Institution_Areas::areas() );
$GLOBALS['settings'][ WPCPM_Institution_Areas::SETTING ] = 'junk';
check( 'areas: junk setting is empty', array( 'countries' => array(), 'regions' => array() ) === WPCPM_Institution_Areas::areas() );

// Sorting: A to Z as a reader expects, accents folded.
$GLOBALS['settings'][ WPCPM_Institution_Areas::SETTING ] = array();
$names = array_values( WPCPM_Institution_Areas::gaps() );
$sam   = array_search( 'Samoa', $names, true );
$sao   = array_search( 'São Tomé and Príncipe', $names, true );
$sau   = array_search( 'Saudi Arabia', $names, true );
check( 'gaps: São Tomé and Príncipe sorts between Samoa and Saudi Arabia', false !== $sam && false !== $sao && false !== $sau && $sam < $sao && $sao < $sau );
$by    = WPCPM_Institution_Areas::countries_by_region();
$afr   = array_values( $by['africa'] );
$sao_a = array_search( 'São Tomé and Príncipe', $afr, true );
check( 'countries_by_region: São Tomé and Príncipe is not last in Africa', false !== $sao_a && $sao_a < count( $afr ) - 1 );

// region_of: an override that is no string, or a region that does not exist, is no override.
$GLOBALS['settings']['institution_regions'] = array( $ids['Egypt'] => array( 'middle-east' ), $ids['Poland'] => 'atlantis', $ids['Japan'] => 7 );
check( 'region_of: array override is ignored', 'africa' === WPCPM_Institution_Areas::region_of( $ids['Egypt'] ) );
check( 'region_of: unknown region key is ignored', 'europe' === WPCPM_Institution_Areas::region_of( $ids['Poland'] ) );
check( 'region_of: integer override is ignored', 'asia' === WPCPM_Institution_Areas::region_of( $ids['Japan'] ) );
$GLOBALS['settings']['institution_regions'] = array();

// clean(): the posted areas.
$empty  = array( 'countries' => array(), 'regions' => array() );
$stored = array( 'countries' => array( $ids['Japan'] => 11 ), 'regions' => array( 'asia' => 12, 'europe' => 11 ) );
$r = WPCPM_Institution_Areas::clean( array( 'regions' => array( 'europe' => '12', 'asia' => '' ) ), $stored );
check( 'clean: Europe kept as posted, Asia dropped', ! is_wp_error( $r ) && array( 'europe' => 12 ) === $r['regions'] );
check( 'clean: countries not posted are kept', ! is_wp_error( $r ) && array( $ids['Japan'] => 11 ) === $r['countries'] );
$r = WPCPM_Institution_Areas::clean( array( 'countries' => array( $ids['Japan'] => '' ) ), $stored );
check( 'clean: a country row posted empty is dropped', ! is_wp_error( $r ) && array() === $r['countries'] );
$r = WPCPM_Institution_Areas::clean( array( 'add_country' => $ids['Poland'], 'add_user' => '11' ), $empty );
check( 'clean: add_country adds the country', ! is_wp_error( $r ) && array( $ids['Poland'] => 11 ) === $r['countries'] );
$r = WPCPM_Institution_Areas::clean( array( 'add_country' => $ids['Poland'], 'add_user' => '' ), $empty );
check( 'clean: an add row with no Administrator adds nothing', ! is_wp_error( $r ) && array() === $r['countries'] );
$r = WPCPM_Institution_Areas::clean( array( 'add_country' => 'recNOPENOPENOPEN', 'add_user' => '11' ), $empty );
check( 'clean: an add row with an unknown country adds nothing', ! is_wp_error( $r ) && array() === $r['countries'] );
$r = WPCPM_Institution_Areas::clean( array( 'countries' => array( $ids['Poland'] => '12' ), 'add_country' => $ids['Poland'], 'add_user' => '11' ), $empty );
check( 'clean: a country given twice is refused', is_wp_error( $r ) && 'wpcpm_areas_twice' === $r->get_error_code() );
check( 'clean: the twice sentence', is_wp_error( $r ) && 'Poland is already given to Bo Boss. Change it in its own row, so the country has one Administrator.' === $r->get_error_message() );
$r = WPCPM_Institution_Areas::clean( array( 'add_country' => $ids['Poland'], 'add_user' => '11' ), array( 'countries' => array( $ids['Poland'] => 12 ), 'regions' => array() ) );
check( 'clean: a country stored with an Administrator is refused too', is_wp_error( $r ) && 'wpcpm_areas_twice' === $r->get_error_code() );
$r = WPCPM_Institution_Areas::clean( array( 'countries' => array( $ids['Poland'] => '' ), 'add_country' => $ids['Poland'], 'add_user' => '11' ), array( 'countries' => array( $ids['Poland'] => 12 ), 'regions' => array() ) );
check( 'clean: a country freed in its row may be added again', ! is_wp_error( $r ) && array( $ids['Poland'] => 11 ) === $r['countries'] );
foreach ( array(
	'region'  => array( 'regions' => array( 'europe' => '13' ) ),
	'country' => array( 'countries' => array( $ids['Poland'] => '13' ) ),
	'add'     => array( 'add_country' => $ids['Poland'], 'add_user' => '13' ),
	'array'   => array( 'regions' => array( 'europe' => array( '11' ) ) ),
) as $label => $post ) {
	$r = WPCPM_Institution_Areas::clean( $post, $empty );
	check( "clean: an account without the capability is refused ({$label})", is_wp_error( $r ) && 'wpcpm_areas_not_admin' === $r->get_error_code() );
	check( "clean: the not-admin sentence ({$label})", is_wp_error( $r ) && 'That account is not an Administrator, so the areas were not saved.' === $r->get_error_message() );
}
$r = WPCPM_Institution_Areas::clean( array( 'regions' => array( 'atlantis' => '11' ), 'countries' => array( 'notanid' => '11' ) ), $empty );
check( 'clean: an unknown region and a malformed country ID are dropped', ! is_wp_error( $r ) && $empty === $r );
$r = WPCPM_Institution_Areas::clean( 'junk', $stored );
check( 'clean: a post that is no array leaves the stored areas', ! is_wp_error( $r ) && $stored === $r );

// clean_regions(): moves.
$r = WPCPM_Institution_Areas::clean_regions( array( 'move_country' => $ids['Egypt'], 'move_region' => 'middle-east' ), array() );
check( 'clean_regions: a move stores the override', array( $ids['Egypt'] => 'middle-east' ) === $r );
$r = WPCPM_Institution_Areas::clean_regions( array( 'move_country' => $ids['Egypt'], 'move_region' => '' ), array( $ids['Egypt'] => 'middle-east', $ids['Japan'] => 'oceania' ) );
check( 'clean_regions: a move to empty removes the override, others stay', array( $ids['Japan'] => 'oceania' ) === $r );
$r = WPCPM_Institution_Areas::clean_regions( array( 'move_country' => $ids['Egypt'], 'move_region' => 'africa' ), array( $ids['Egypt'] => 'middle-east' ) );
check( 'clean_regions: a move back to the shipped region removes the override', array() === $r );
$r = WPCPM_Institution_Areas::clean_regions( array( 'move_country' => $ids['Egypt'], 'move_region' => 'atlantis' ), array( $ids['Japan'] => 'oceania' ) );
check( 'clean_regions: an unknown region changes nothing', array( $ids['Japan'] => 'oceania' ) === $r );
$r = WPCPM_Institution_Areas::clean_regions( array( 'move_country' => 'recNOPENOPENOPEN', 'move_region' => 'asia' ), array() );
check( 'clean_regions: an unknown country changes nothing', array() === $r );
$r = WPCPM_Institution_Areas::clean_regions( array( 'move_country' => '', 'move_region' => '' ), array( $ids['Egypt'] => 'middle-east', $ids['Poland'] => array( 'x' ), $ids['Japan'] => 'atlantis' ) );
check( 'clean_regions: an empty move keeps the stored overrides, malformed ones dropped', array( $ids['Egypt'] => 'middle-east' ) === $r );
$r = WPCPM_Institution_Areas::clean_regions( 'junk', array( $ids['Egypt'] => 'middle-east' ) );
check( 'clean_regions: a post that is no array leaves the overrides', array( $ids['Egypt'] => 'middle-east' ) === $r );

// render_settings().
$GLOBALS['settings'][ WPCPM_Institution_Areas::SETTING ] = array( 'countries' => array( $ids['Poland'] => 11 ), 'regions' => array( 'europe' => 12 ) );
$GLOBALS['settings']['institution_regions'] = array( $ids['Egypt'] => 'middle-east' );
$GLOBALS['countries']['recXXXXXXXXXXXXXX'] = array( 'name' => 'Atlantis' );
WPCPM_Dashboards::$combos = array();
ob_start();
WPCPM_Institution_Areas::render_settings( array() );
$html = ob_get_clean();
check( 'render: heading', false !== strpos( $html, '>Areas of responsibility</h2>' ) );
check( 'render: lead', false !== strpos( $html, esc_html( "Which Administrator a new institution is assigned to: the country's own Administrator first, then the region's." ) ) );
foreach ( $keys as $key ) {
	check( "render: a select for {$key}", 1 === preg_match( '/<select name="institution_areas\[regions\]\[' . $key . '\]"/', $html ) );
}
check( 'render: Europe select offers Nobody and every Administrator, the stored one selected', 1 === preg_match( '/<select name="institution_areas\[regions\]\[europe\]"[^>]*><option value="">Nobody<\/option><option value="11">Ana Admin<\/option><option value="12" selected="selected">Bo Boss<\/option><\/select>/', $html ) );
check( 'render: a non-Administrator is never offered', false === strpos( $html, 'Cy Client' ) );
check( 'render: Poland has its own row, Ana selected', 1 === preg_match( '/<select name="institution_areas\[countries\]\[' . $ids['Poland'] . '\]"[^>]*><option value="">Nobody<\/option><option value="11" selected="selected">/', $html ) );
check( 'render: Romania has no row', false === strpos( $html, 'institution_areas[countries][' . $ids['Romania'] . ']' ) );
check( 'render: Add a country row', false !== strpos( $html, 'Add a country' ) && false !== strpos( $html, '[combo name="institution_areas[add_country]"]' ) && false !== strpos( $html, '<select name="institution_areas[add_user]"' ) );
check( 'render: both combos are drawn inside .wpcpm-admin-combo, which hides the select a script replaces', 1 === substr_count( $html, '<div class="wpcpm-admin-combo">[combo name="institution_areas[add_country]"]</div>' ) && 1 === substr_count( $html, '<div class="wpcpm-admin-combo">[combo name="institution_regions[move_country]"]</div>' ) );
$admin_css_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/admin.css' );
check( 'render: admin.css hides a hidden select under that wrapper, at two classes, over wp-admin\'s .form-table td select at 782px', false !== strpos( $admin_css_src, ".wpcpm-admin-combo select[hidden],\n" ) || false !== strpos( $admin_css_src, ".wpcpm-admin-combo select[hidden] {" ) );
check( 'render: Who has what', false !== strpos( $html, 'Who has what' ) && false !== strpos( $html, 'Bo Boss: Europe' ) && false !== strpos( $html, 'Ana Admin: Poland' ) );
check( 'render: Countries by region is a row with one closed fold, named for it', 1 === preg_match( '/<th scope="row">Countries by region<\/th>.*<details class="wpcpm-settings__details"><summary>Details<span class="screen-reader-text"> about Countries by region<\/span><\/summary>/s', $html ) && 1 === substr_count( $html, '<details' ) );
check( 'render: the fold lists a region\'s countries', false !== strpos( $html, 'Romania' ) && false !== strpos( $html, 'Nigeria' ) );
check( 'render: Egypt is listed under the Middle East, not Africa', 1 === preg_match( '/Middle East.*?Egypt.*?<\/details>/s', $html ) && 0 === preg_match( '/Africa<\/h[0-9]>[^<]*<p>[^<]*Egypt/', $html ) );
check( 'render: No region', false !== strpos( $html, 'No region' ) && false !== strpos( $html, 'Atlantis' ) );
check( 'render: Move a country row', 'Move a country' === ( WPCPM_Dashboards::$combos[1]['label'] ?? '' ) && false !== strpos( $html, '[combo name="institution_regions[move_country]"]' ) && false !== strpos( $html, '<select name="institution_regions[move_region]"' ) );
check( 'render: two combos drawn, each with an ID and a label', 2 === count( WPCPM_Dashboards::$combos ) && '' !== WPCPM_Dashboards::$combos[0]['id'] && '' !== WPCPM_Dashboards::$combos[0]['label'] && WPCPM_Dashboards::$combos[0]['id'] !== WPCPM_Dashboards::$combos[1]['id'] );
check( 'render: no em or en dash', false === strpos( $html, "\xE2\x80\x94" ) && false === strpos( $html, "\xE2\x80\x93" ) );
unset( $GLOBALS['countries']['recXXXXXXXXXXXXXX'] );

echo $failures ? "{$failures} of {$checks} checks failed\n" : "All {$checks} checks passed\n";
exit( $failures ? 1 : 0 );
