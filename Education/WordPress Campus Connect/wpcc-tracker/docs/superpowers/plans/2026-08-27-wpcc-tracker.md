# WPCC-Tracker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A WordPress plugin that renders WordPress Campus Connect programme data as six standalone blocks plus a combined one, reproducing the Stats tab of the INTERNAL WordPress Campus Connect Events Tracking Sheet.

**Architecture:** Server-rendered dynamic blocks with no build step, mirroring `Plugins/wpcredits-tracker`. A daily WP-Cron sync reads two Airtable tables, normalises them into one public option blob, and the render layer turns that blob into HTML plus an inline data payload that Chart.js and Leaflet initialise against. All normalisation logic is pure PHP with no WordPress dependency, so it is unit-testable without a WP bootstrap.

**Tech Stack:** PHP 7.4+, WordPress 6.4+, Chart.js 4.4.1 and Leaflet 1.9.4 bundled locally, Airtable REST API v0. No Composer, no npm, no PHPUnit — a hand-rolled assertion harness runs under plain `php`.

> **Superseded after execution (2026-08-27):** the sync moved from weekly to daily, so the
> `WPCCT_CRON_WEEKLY` constant and the custom `weekly` schedule shown in the Task 7 code
> blocks below no longer exist. They are left as written because this document records what
> was built; read the committed source for current behaviour.

## Global Constraints

- Plugin slug and text domain: `wpcc-tracker`. Directory: `Plugins/wpcc-tracker`.
- Code prefixes: `WPCCT_` (classes, constants), `wpcct_` (functions, options, hooks), `.wpcct-` (CSS).
- `Requires at least: 6.4`, `Requires PHP: 7.4`. No PHP 8-only syntax.
- Every file starts with `if ( ! defined( 'ABSPATH' ) ) { exit; }` **except** `includes/class-wpcct-normalize.php` and everything under `tests/` and `bin/`, which must run outside WordPress. (`bin/` added to the exception list 2026-08-27: `bin/build-seed.php` is a CLI script invoked as `php bin/build-seed.php` with no WordPress loaded, so a guard there would make it exit immediately.)
- Airtable base: `appoiPJkMFdnJEmfa`. Existing WordCamps table: `tblLBVsO4rU2oj2Ki`.
- The plugin stores **no** organizer names, e-mail addresses, or any other personal data in `wpcct_data`.
- `Number of Anticipated Attendees` is free text and must never be summed.
- **Version stays `1.0.0` for the whole of this plan.** The project's standing "bump on every change" rule governs released plugins; this plan builds one unreleased initial release, so the plugin header, `WPCCT_VERSION` and `readme.txt` Stable tag all read `1.0.0` in every task, with a single `= 1.0.0 =` changelog entry. Normal per-change bumping resumes after release. (Ruled 2026-08-27.)
- Run `php -l` on every PHP file you create or modify before committing.

---

### Task 1: Fixture capture, test harness, plugin bootstrap

Everything downstream asserts against a frozen copy of the Campus Connect Details report, so that copy and the harness that reads it come first.

**Files:**
- Create: `wpcc-tracker.php`
- Create: `tests/fixtures/campus-connect-details-2026-08-27.tsv`
- Create: `tests/harness.php`
- Create: `tests/run-tests.php`
- Create: `readme.txt`

**Interfaces:**
- Consumes: nothing.
- Produces: `t_eq( $actual, $expected, $label )`, `t_ok( $cond, $label )`, `t_report()` from `tests/harness.php`; `wpcct_load_fixture(): array` returning a list of associative rows keyed by the TSV header; constants `WPCCT_VERSION`, `WPCCT_FILE`, `WPCCT_DIR`, `WPCCT_URL`, `WPCCT_OPT_SETTINGS`, `WPCCT_OPT_DATA`, `WPCCT_OPT_LASTSYNC`, `WPCCT_OPT_LASTERR`, `WPCCT_CRON_WEEKLY`.

- [ ] **Step 1: Capture the fixture from Central**

This needs a browser session logged into central.wordcamp.org as an admin. Navigate to:

```
https://central.wordcamp.org/wp-admin/index.php?page=wordcamp-reports&report=campus-connect-details
```

Leave the date range empty. The field checkboxes come pre-checked with the report's defaults — **uncheck `Organizer Name` and `Tracker URL`** (personal data and an admin-only link, neither of which this plugin displays). Leave the other thirteen checked. Click **Show Results**, not Export CSV.

Extract the results table — it is the second `<table>` on the page — as tab-separated values and save it to `tests/fixtures/campus-connect-details-2026-08-27.tsv`. In the browser console:

```js
(() => {
  const t = [...document.querySelectorAll('table')][1];
  return [...t.rows]
    .map(r => [...r.cells].map(c => c.innerText.trim().replace(/\s+/g, ' ')).join('\t'))
    .join('\n');
})()
```

The first line must be exactly:

```
Start Date (YYYY-mm-dd)	End Date (YYYY-mm-dd)	Status	Name	Institution Name	City	Country	Number of Anticipated Attendees	Actual Attendees	Series Event	Created	URL	ID
```

followed by 142 data lines.

- [ ] **Step 2: Write the test harness**

`tests/harness.php` — no PHPUnit, no autoloader, no WordPress:

```php
<?php
/**
 * Minimal assertion harness. Run with: php tests/run-tests.php
 *
 * @package WPCC_Tracker
 */

$GLOBALS['wpcct_t'] = array( 'pass' => 0, 'fail' => 0, 'fails' => array() );

function t_ok( $cond, $label ) {
	if ( $cond ) {
		$GLOBALS['wpcct_t']['pass']++;
		return;
	}
	$GLOBALS['wpcct_t']['fail']++;
	$GLOBALS['wpcct_t']['fails'][] = $label;
}

function t_eq( $actual, $expected, $label ) {
	$ok = $actual === $expected;
	if ( ! $ok ) {
		$label .= sprintf(
			' (expected %s, got %s)',
			var_export( $expected, true ),
			var_export( $actual, true )
		);
	}
	t_ok( $ok, $label );
}

function t_report() {
	$t = $GLOBALS['wpcct_t'];
	foreach ( $t['fails'] as $f ) {
		echo "FAIL  " . $f . "\n";
	}
	printf( "\n%d passed, %d failed\n", $t['pass'], $t['fail'] );
	exit( $t['fail'] > 0 ? 1 : 0 );
}

/**
 * Load the frozen Campus Connect Details export.
 *
 * @return array List of rows, each an associative array keyed by column header.
 */
function wpcct_load_fixture() {
	static $rows = null;
	if ( null !== $rows ) {
		return $rows;
	}

	$path  = __DIR__ . '/fixtures/campus-connect-details-2026-08-27.tsv';
	$lines = file( $path, FILE_IGNORE_NEW_LINES );
	if ( ! $lines ) {
		fwrite( STDERR, "Fixture missing: $path\n" );
		exit( 1 );
	}

	$head = explode( "\t", array_shift( $lines ) );
	$rows = array();
	foreach ( $lines as $line ) {
		if ( '' === trim( $line ) ) {
			continue;
		}
		$cells = explode( "\t", $line );
		$cells = array_pad( $cells, count( $head ), '' );
		$rows[] = array_combine( $head, array_slice( $cells, 0, count( $head ) ) );
	}
	return $rows;
}
```

- [ ] **Step 3: Write the failing fixture-integrity test**

`tests/run-tests.php`:

```php
<?php
/**
 * Test entry point. Run: php tests/run-tests.php
 *
 * @package WPCC_Tracker
 */

require __DIR__ . '/harness.php';

$rows = wpcct_load_fixture();

t_eq( count( $rows ), 142, 'fixture has 142 events' );

t_eq(
	array_keys( $rows[0] ),
	array(
		'Start Date (YYYY-mm-dd)',
		'End Date (YYYY-mm-dd)',
		'Status',
		'Name',
		'Institution Name',
		'City',
		'Country',
		'Number of Anticipated Attendees',
		'Actual Attendees',
		'Series Event',
		'Created',
		'URL',
		'ID',
	),
	'fixture columns match the report field order'
);

$actual = 0;
foreach ( $rows as $r ) {
	$actual += (int) preg_replace( '/[^0-9]/', '', $r['Actual Attendees'] );
}
t_eq( $actual, 6490, 'actual attendees sum matches the Stats sheet' );

$ids = array_column( $rows, 'ID' );
t_eq( count( array_unique( $ids ) ), 142, 'ID is unique across every row' );

t_report();
```

- [ ] **Step 4: Run it and watch it fail**

```bash
php tests/run-tests.php
```

Expected before the fixture exists: `Fixture missing: .../campus-connect-details-2026-08-27.tsv`. After Step 1 saved it, all four assertions pass. If the counts differ from 142 / 6490, the report has moved on since 2026-08-27 — **do not edit the assertions to match**. Record the new numbers, and update this plan's expected values everywhere in one deliberate pass.

- [ ] **Step 5: Write the plugin bootstrap**

`wpcc-tracker.php`:

```php
<?php
/**
 * Plugin Name:       WPCC-Tracker
 * Plugin URI:        https://nisabareportingp2.wpcomstaging.com/
 * Description:       WordPress Campus Connect programme data — events, regions, pipeline, attendance and a world map — rendered as native blocks. Synced weekly from Airtable.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Maciej (Matt) Pilarski
 * Author URI:        https://profiles.wordpress.org/gomp/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpcc-tracker
 *
 * @package WPCC_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPCCT_VERSION', '1.0.0' );
define( 'WPCCT_FILE', __FILE__ );
define( 'WPCCT_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPCCT_URL', plugin_dir_url( __FILE__ ) );

define( 'WPCCT_OPT_SETTINGS', 'wpcct_settings' );
define( 'WPCCT_OPT_DATA', 'wpcct_data' );
define( 'WPCCT_OPT_LASTSYNC', 'wpcct_last_sync' );
define( 'WPCCT_OPT_LASTERR', 'wpcct_last_error' );
define( 'WPCCT_CRON_WEEKLY', 'wpcct_cron_weekly' );

require_once WPCCT_DIR . 'includes/class-wpcct-normalize.php';
```

Later tasks add the remaining `require_once` lines. Create `includes/class-wpcct-normalize.php` as an empty class now so the file loads:

```php
<?php
/**
 * Pure normalisation of Campus Connect report rows. No WordPress dependency —
 * this file must load and run under plain PHP so it can be unit-tested.
 *
 * @package WPCC_Tracker
 */

class WPCCT_Normalize {
}
```

- [ ] **Step 6: Write readme.txt**

```
=== WPCC-Tracker ===
Contributors: gomp
Tags: campus connect, wordpress, education, events, statistics
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress Campus Connect programme data rendered as native blocks.

== Description ==

Six standalone blocks plus a combined one, covering scale, regions, a world map,
a timeline, the organiser pipeline and an event list. Data is synced weekly from
Airtable, which mirrors the Campus Connect Details report on WordCamp Central.

== Changelog ==

= 1.0.0 =
* Initial release.
```

- [ ] **Step 7: Lint and commit**

```bash
php -l wpcc-tracker.php && php -l includes/class-wpcct-normalize.php && php tests/run-tests.php
git add -A
git commit -m "Add fixture, test harness and plugin bootstrap"
```

---

### Task 2: Status buckets

**Files:**
- Modify: `includes/class-wpcct-normalize.php`
- Modify: `tests/run-tests.php`

**Interfaces:**
- Consumes: `wpcct_load_fixture()`.
- Produces: `WPCCT_Normalize::STATUS_COMPLETED`, `::STATUS_SCHEDULED`, `::STATUS_PLANNING`, `::STATUS_EXCLUDED` (string constants `'completed'`, `'scheduled'`, `'planning'`, `'excluded'`); `WPCCT_Normalize::status_bucket( string $status ): string`; `WPCCT_Normalize::filter( string $hook, $value )`.

- [ ] **Step 1: Write the failing test**

Append to `tests/run-tests.php`, above `t_report();`:

```php
require __DIR__ . '/../includes/class-wpcct-normalize.php';

$expect = array(
	'Closed'                                      => 'completed',
	'Scheduled'                                   => 'scheduled',
	'In Pre-Planning'                             => 'planning',
	'Needs Vetting'                               => 'planning',
	'Needs Orientation/Interview'                 => 'planning',
	'Interview/Orientation Scheduled'             => 'planning',
	'Approved for Pre-Planning Pending Agreement' => 'planning',
	'Needs to Fill Out Listing'                   => 'planning',
	'On Hold'                                     => 'planning',
	'Cancelled'                                   => 'excluded',
	'Declined'                                    => 'excluded',
);
foreach ( $expect as $status => $bucket ) {
	t_eq( WPCCT_Normalize::status_bucket( $status ), $bucket, "status '$status' buckets as $bucket" );
}
t_eq( WPCCT_Normalize::status_bucket( 'Something New' ), 'excluded', 'unknown status is excluded, not counted' );

$counts = array( 'completed' => 0, 'scheduled' => 0, 'planning' => 0, 'excluded' => 0 );
foreach ( wpcct_load_fixture() as $r ) {
	$counts[ WPCCT_Normalize::status_bucket( $r['Status'] ) ]++;
}
t_eq( $counts['completed'], 56, 'completed count' );
t_eq( $counts['scheduled'], 15, 'scheduled count' );
t_eq( $counts['planning'], 46, 'planning count matches the sheet regional total' );
t_eq( $counts['excluded'], 25, 'cancelled + declined count' );
t_eq( array_sum( $counts ), 142, 'buckets partition every row' );
```

- [ ] **Step 2: Run it to verify it fails**

```bash
php tests/run-tests.php
```
Expected: `PHP Fatal error: Call to undefined method WPCCT_Normalize::status_bucket()`.

- [ ] **Step 3: Implement**

Replace the body of `class WPCCT_Normalize`:

```php
class WPCCT_Normalize {

	const STATUS_COMPLETED = 'completed';
	const STATUS_SCHEDULED = 'scheduled';
	const STATUS_PLANNING  = 'planning';
	const STATUS_EXCLUDED  = 'excluded';

	/**
	 * Apply a WordPress filter when running inside WordPress; pass the value
	 * through untouched when running under the bare-PHP test harness.
	 *
	 * @param string $hook  Filter name.
	 * @param mixed  $value Value to filter.
	 * @return mixed
	 */
	public static function filter( $hook, $value ) {
		return function_exists( 'apply_filters' ) ? apply_filters( $hook, $value ) : $value;
	}

	/**
	 * Map a Campus Connect Details status to a reporting bucket.
	 *
	 * The report emits eleven statuses. Unknown statuses are excluded rather
	 * than guessed at, so a new upstream status never silently inflates a
	 * headline figure — it shows up as a shortfall instead.
	 *
	 * @param string $status Raw status from the report.
	 * @return string One of the STATUS_* constants.
	 */
	public static function status_bucket( $status ) {
		$map = self::filter(
			'wpcct_status_buckets',
			array(
				'Closed'                                      => self::STATUS_COMPLETED,
				'Scheduled'                                   => self::STATUS_SCHEDULED,
				'In Pre-Planning'                             => self::STATUS_PLANNING,
				'Needs Vetting'                               => self::STATUS_PLANNING,
				'Needs Orientation/Interview'                 => self::STATUS_PLANNING,
				'Interview/Orientation Scheduled'             => self::STATUS_PLANNING,
				'Approved for Pre-Planning Pending Agreement' => self::STATUS_PLANNING,
				'Needs to Fill Out Listing'                   => self::STATUS_PLANNING,
				'On Hold'                                     => self::STATUS_PLANNING,
				'Cancelled'                                   => self::STATUS_EXCLUDED,
				'Declined'                                    => self::STATUS_EXCLUDED,
			)
		);

		$status = trim( (string) $status );

		return isset( $map[ $status ] ) ? $map[ $status ] : self::STATUS_EXCLUDED;
	}
}
```

- [ ] **Step 4: Run to verify it passes**

```bash
php tests/run-tests.php
```
Expected: all assertions pass.

- [ ] **Step 5: Commit**

```bash
php -l includes/class-wpcct-normalize.php
git add -A
git commit -m "Add status bucketing, validated against the Stats sheet"
```

---

### Task 3: Country aliases, regions, and the row denylist

**Files:**
- Modify: `includes/class-wpcct-normalize.php`
- Modify: `tests/run-tests.php`

**Interfaces:**
- Consumes: `WPCCT_Normalize::status_bucket()`, `::filter()`.
- Produces: `WPCCT_Normalize::canonical_country( string $country ): string`; `::region_for( string $country ): string` returning a region name or `''` when unmapped; `::is_denied( array $row ): bool`; `::REGIONS` (ordered list of the five region names).

- [ ] **Step 1: Write the failing test**

Append to `tests/run-tests.php`, above `t_report();`:

```php
t_eq( WPCCT_Normalize::canonical_country( 'Maharashtra, India' ), 'India', 'state-in-country-field aliases to India' );
t_eq( WPCCT_Normalize::canonical_country( '  Spain ' ), 'Spain', 'country is trimmed' );

t_eq( WPCCT_Normalize::region_for( 'India' ), 'Asia', 'India is Asia' );
t_eq( WPCCT_Normalize::region_for( 'Maharashtra, India' ), 'Asia', 'alias resolves before region lookup' );
t_eq( WPCCT_Normalize::region_for( 'Spain' ), 'Europe', 'Spain is Europe' );
t_eq( WPCCT_Normalize::region_for( 'Costa Rica' ), 'Latin America and Caribbean', 'Costa Rica is LatAm' );
t_eq( WPCCT_Normalize::region_for( 'Uganda' ), 'Africa', 'Uganda is Africa' );
t_eq( WPCCT_Normalize::region_for( 'United States' ), 'North America', 'US is North America' );
t_eq( WPCCT_Normalize::region_for( 'Narnia' ), '', 'unmapped country returns empty, never a guess' );
t_eq( WPCCT_Normalize::region_for( '' ), '', 'blank country returns empty' );

t_ok( WPCCT_Normalize::is_denied( array( 'Country' => 'Tomorrowland', 'Institution Name' => 'Tomorrow Campus' ) ), 'Tomorrowland is denied' );
t_ok( WPCCT_Normalize::is_denied( array( 'Country' => 'Atlantis', 'Institution Name' => 'Atlantis Atlantide' ) ), 'Atlantis is denied' );
t_ok( WPCCT_Normalize::is_denied( array( 'Country' => 'Wonderland', 'Institution Name' => '' ) ), 'Wonderland is denied' );
t_ok( ! WPCCT_Normalize::is_denied( array( 'Country' => 'India', 'Institution Name' => 'Aryabhatta College' ) ), 'a real event is not denied' );

// Region roll-up over live (non-excluded, non-denied) events.
$live_by_region = array();
$unmapped       = 0;
foreach ( wpcct_load_fixture() as $r ) {
	if ( WPCCT_Normalize::is_denied( $r ) ) {
		continue;
	}
	if ( 'excluded' === WPCCT_Normalize::status_bucket( $r['Status'] ) ) {
		continue;
	}
	$region = WPCCT_Normalize::region_for( $r['Country'] );
	if ( '' === $region ) {
		$unmapped++;
		continue;
	}
	$live_by_region[ $region ] = ( isset( $live_by_region[ $region ] ) ? $live_by_region[ $region ] : 0 ) + 1;
}
t_eq( $live_by_region['Asia'], 67, 'Asia live events match the sheet' );
t_eq( $live_by_region['Europe'], 19, 'Europe live events match the sheet' );
t_eq( $live_by_region['Latin America and Caribbean'], 11, 'LatAm live events match the sheet' );
t_eq( $live_by_region['North America'], 2, 'North America live events match the sheet' );
t_eq( $live_by_region['Africa'], 15, 'Africa live events (sheet says 16; the extra is a blank-country row)' );
t_eq( array_sum( $live_by_region ), 114, 'mapped live events' );
t_eq( $unmapped, 1, 'exactly one live row has an unmappable country' );
```

- [ ] **Step 2: Run it to verify it fails**

```bash
php tests/run-tests.php
```
Expected: `Call to undefined method WPCCT_Normalize::canonical_country()`.

- [ ] **Step 3: Implement**

Add to `class WPCCT_Normalize`:

```php
	const REGIONS = array(
		'Asia',
		'Europe',
		'Africa',
		'Latin America and Caribbean',
		'North America',
	);

	/**
	 * Resolve a country value that is not quite a country name.
	 *
	 * The report's Country column is free text filled in by organisers, so it
	 * occasionally holds a state or a region instead.
	 *
	 * @param string $country Raw country value.
	 * @return string Canonical country name.
	 */
	public static function canonical_country( $country ) {
		$aliases = self::filter(
			'wpcct_country_aliases',
			array(
				'Maharashtra, India' => 'India',
			)
		);

		$country = trim( (string) $country );

		return isset( $aliases[ $country ] ) ? $aliases[ $country ] : $country;
	}

	/**
	 * Map a country to one of the five reporting regions.
	 *
	 * Returns an empty string for anything unmapped. Callers must count those
	 * separately and surface them rather than bucketing them somewhere — a new
	 * country should appear as an admin notice, not as a silent Asia entry.
	 *
	 * @param string $country Raw or canonical country name.
	 * @return string Region name, or '' when unmapped.
	 */
	public static function region_for( $country ) {
		$map = self::filter(
			'wpcct_region_map',
			array(
				// Asia.
				'India'                => 'Asia',
				'Indonesia'            => 'Asia',
				'Bangladesh'           => 'Asia',
				'Malaysia'             => 'Asia',
				'Philippines'          => 'Asia',
				'Nepal'                => 'Asia',
				'Pakistan'             => 'Asia',
				'Japan'                => 'Asia',
				'Taiwan'               => 'Asia',
				'Hong Kong'            => 'Asia',
				'United Arab Emirates' => 'Asia',
				'Lebanon'              => 'Asia',
				// Europe.
				'Spain'                => 'Europe',
				'Italy'                => 'Europe',
				'Croatia'              => 'Europe',
				'Poland'               => 'Europe',
				'Ukraine'              => 'Europe',
				// Africa.
				'Uganda'               => 'Africa',
				'Nigeria'              => 'Africa',
				'Egypt'                => 'Africa',
				'Namibia'              => 'Africa',
				'Tanzania'             => 'Africa',
				// Latin America and Caribbean.
				'Costa Rica'           => 'Latin America and Caribbean',
				'Guatemala'            => 'Latin America and Caribbean',
				'Nicaragua'            => 'Latin America and Caribbean',
				'Brazil'               => 'Latin America and Caribbean',
				'El Salvador'          => 'Latin America and Caribbean',
				// North America.
				'United States'        => 'North America',
			)
		);

		$country = self::canonical_country( $country );

		return isset( $map[ $country ] ) ? $map[ $country ] : '';
	}

	/**
	 * Is this row a test submission rather than a real event?
	 *
	 * Three events name fictional countries. Two sit in planning statuses, so
	 * without this they inflate both the planning count and the institution
	 * count.
	 *
	 * @param array $row Report row keyed by column header.
	 * @return bool
	 */
	public static function is_denied( $row ) {
		$countries = self::filter(
			'wpcct_excluded_rows',
			array( 'Tomorrowland', 'Atlantis', 'Wonderland' )
		);

		$country = self::canonical_country( isset( $row['Country'] ) ? $row['Country'] : '' );

		return in_array( $country, $countries, true );
	}
```

- [ ] **Step 4: Run to verify it passes**

```bash
php tests/run-tests.php
```
Expected: all assertions pass, including the five regional totals.

- [ ] **Step 5: Commit**

```bash
php -l includes/class-wpcct-normalize.php
git add -A
git commit -m "Add country aliases, region map and test-row denylist"
```

---

### Task 4: Attendance, institutions, and the anticipated-attendees guard

**Files:**
- Modify: `includes/class-wpcct-normalize.php`
- Modify: `tests/run-tests.php`

**Interfaces:**
- Consumes: everything from Tasks 2–3.
- Produces: `WPCCT_Normalize::attendees( array $row ): int`; `::anticipated( array $row ): string`; `::institution_key( array $row ): string`.

- [ ] **Step 1: Write the failing test**

Append to `tests/run-tests.php`, above `t_report();`:

```php
t_eq( WPCCT_Normalize::attendees( array( 'Actual Attendees' => '610' ) ), 610, 'plain integer parses' );
t_eq( WPCCT_Normalize::attendees( array( 'Actual Attendees' => '1,293' ) ), 1293, 'thousands separator parses' );
t_eq( WPCCT_Normalize::attendees( array( 'Actual Attendees' => '' ) ), 0, 'blank attendance is zero' );

// Anticipated is free text and is returned verbatim, never coerced to a number.
t_eq( WPCCT_Normalize::anticipated( array( 'Number of Anticipated Attendees' => '80-100' ) ), '80-100', 'range kept verbatim' );
t_eq( WPCCT_Normalize::anticipated( array( 'Number of Anticipated Attendees' => 'Ideally dozens.' ) ), 'Ideally dozens.', 'prose kept verbatim' );
t_eq( WPCCT_Normalize::anticipated( array( 'Number of Anticipated Attendees' => ' 400 ' ) ), '400', 'number kept as a trimmed string' );
t_ok( ! method_exists( 'WPCCT_Normalize', 'total_anticipated' ), 'no method exists to total anticipated attendees' );

// Attendance is only ever recorded on completed events.
$total = 0;
$non_completed_with_attendance = 0;
foreach ( wpcct_load_fixture() as $r ) {
	$n = WPCCT_Normalize::attendees( $r );
	$total += $n;
	if ( $n > 0 && 'completed' !== WPCCT_Normalize::status_bucket( $r['Status'] ) ) {
		$non_completed_with_attendance++;
	}
}
t_eq( $total, 6490, 'total attendance matches the Stats sheet exactly' );
t_eq( $non_completed_with_attendance, 0, 'attendance only ever appears on completed events' );

t_eq( WPCCT_Normalize::institution_key( array( 'Institution Name' => '  Sophia Girls College ' ) ), 'sophia girls college', 'institution key is trimmed and lowercased' );
t_eq( WPCCT_Normalize::institution_key( array( 'Institution Name' => '' ) ), '', 'blank institution has no key' );
```

- [ ] **Step 2: Run it to verify it fails**

```bash
php tests/run-tests.php
```
Expected: `Call to undefined method WPCCT_Normalize::attendees()`.

- [ ] **Step 3: Implement**

Add to `class WPCCT_Normalize`:

```php
	/**
	 * Actual attendance for a row.
	 *
	 * This is the only attendance figure that may be aggregated. It is clean
	 * integer data and is populated only on completed events.
	 *
	 * @param array $row Report row.
	 * @return int
	 */
	public static function attendees( $row ) {
		$raw = isset( $row['Actual Attendees'] ) ? $row['Actual Attendees'] : '';

		return (int) preg_replace( '/[^0-9]/', '', (string) $raw );
	}

	/**
	 * Anticipated attendance, verbatim.
	 *
	 * Deliberately a string. The report's column is free text filled in by
	 * organisers — real values include '80-100', '40-60 attendees' and
	 * 'Ideally dozens.' Summing it produces nonsense (596,477,463 across the
	 * 2026-08-27 export), so this value is displayed per event and never
	 * aggregated. There is intentionally no total_anticipated() counterpart.
	 *
	 * @param array $row Report row.
	 * @return string
	 */
	public static function anticipated( $row ) {
		$raw = isset( $row['Number of Anticipated Attendees'] ) ? $row['Number of Anticipated Attendees'] : '';

		return trim( (string) $raw );
	}

	/**
	 * Comparison key for counting distinct institutions.
	 *
	 * @param array $row Report row.
	 * @return string Empty when the row names no institution.
	 */
	public static function institution_key( $row ) {
		$raw = isset( $row['Institution Name'] ) ? $row['Institution Name'] : '';

		return strtolower( trim( (string) $raw ) );
	}
```

- [ ] **Step 4: Run to verify it passes**

```bash
php tests/run-tests.php
```

- [ ] **Step 5: Commit**

```bash
php -l includes/class-wpcct-normalize.php
git add -A
git commit -m "Add attendance parsing and the anticipated-attendees guard"
```

---

### Task 5: Build the public data blob

**Files:**
- Modify: `includes/class-wpcct-normalize.php`
- Modify: `tests/run-tests.php`

**Interfaces:**
- Consumes: everything from Tasks 2–4.
- Produces: `WPCCT_Normalize::build( array $rows, array $coords = array() ): array`. `$rows` is the report rows; `$coords` maps WordCamp ID (string) to `array( 'lat' => float, 'lng' => float, 'site' => string )`. Returns the `wpcct_data` blob with keys `generated`, `totals`, `regions`, `pipeline`, `timeline`, `markers`, `events`, `unmapped_countries`.

- [ ] **Step 1: Write the failing test**

Append to `tests/run-tests.php`, above `t_report();`:

```php
$blob = WPCCT_Normalize::build(
	wpcct_load_fixture(),
	array( '11067837' => array( 'lat' => 0.4478, 'lng' => 33.2026, 'site' => 'https://events.wordpress.org/campusconnect/2025/jinja/' ) )
);

t_eq( $blob['totals']['completed'], 56, 'blob completed total' );
t_eq( $blob['totals']['scheduled'], 15, 'blob scheduled total' );
t_eq( $blob['totals']['planning'], 44, 'blob planning total (46 raw, less 2 denied test rows)' );
t_eq( $blob['totals']['attendees'], 6490, 'blob attendance total' );
t_eq( $blob['totals']['completed_this_year'], 36, 'blob completed in 2026' );
t_eq( $blob['totals']['countries'], 25, 'blob distinct mapped countries with live events' );

t_eq( count( $blob['regions'] ), 5, 'five regions are always present, even at zero' );
t_ok( isset( $blob['regions']['North America'] ), 'a region with no completed events is still present' );

// Regional buckets must account for every mapped live event. Per-region
// completed/scheduled splits are NOT asserted against the sheet's published
// figures: the sheet is a 2026-08-25 snapshot and the fixture is 2026-08-27,
// and two events changed state in between. Only the invariants hold.
$region_live = 0;
foreach ( $blob['regions'] as $r ) {
	$region_live += $r['completed'] + $r['scheduled'] + $r['planning'];
}
t_eq( $region_live, 114, 'regions account for every mapped live event' );
t_eq( $region_live + array_sum( $blob['unmapped_countries'] ), 115, 'mapped + unmapped = all live events after the denylist' );

// Attendance on the one unmapped row is unknown, so derive the expected
// regional total from the primitives rather than hardcoding it. This checks
// that build() agrees with attendees()/region_for(), which is the actual risk.
$region_attendees = 0;
foreach ( $blob['regions'] as $r ) {
	$region_attendees += $r['attendees'];
}
$expected_region_attendees = 0;
foreach ( wpcct_load_fixture() as $r ) {
	if ( WPCCT_Normalize::is_denied( $r ) ) {
		continue;
	}
	if ( 'excluded' === WPCCT_Normalize::status_bucket( $r['Status'] ) ) {
		continue;
	}
	if ( '' === WPCCT_Normalize::region_for( $r['Country'] ) ) {
		continue;
	}
	$expected_region_attendees += WPCCT_Normalize::attendees( $r );
}
t_eq( $region_attendees, $expected_region_attendees, 'no attendance is lost in the regional roll-up' );

// The pipeline keeps every raw status, including the excluded ones.
t_eq( $blob['pipeline']['Cancelled'], 11, 'pipeline exposes cancelled (12 raw, less 1 denied test row)' );
t_eq( $blob['pipeline']['Declined'], 13, 'pipeline exposes declined' );
t_eq( array_sum( $blob['pipeline'] ), 139, 'pipeline covers every non-denied row' );

t_eq( count( $blob['markers'] ), 1, 'only events with coordinates become markers' );
t_eq( $blob['markers'][0]['id'], '11067837', 'marker carries its WordCamp ID' );

t_eq( $blob['unmapped_countries'], array( '' => 1 ), 'unmapped countries are reported for the admin screen' );

// No personal data may reach the blob.
$json = json_encode( $blob );
foreach ( array( 'Organizer', 'organizer', 'E-mail', 'Email', '@' ) as $needle ) {
	t_ok( false === strpos( $json, $needle ), "blob contains no '$needle'" );
}
```

- [ ] **Step 2: Run it to verify it fails**

```bash
php tests/run-tests.php
```
Expected: `Call to undefined method WPCCT_Normalize::build()`.

- [ ] **Step 3: Implement**

Add to `class WPCCT_Normalize`:

```php
	/**
	 * Normalise report rows into the public data blob.
	 *
	 * The blob is what gets stored in the wpcct_data option and inlined into
	 * pages, so it carries aggregates and a deliberately narrow per-event
	 * field set — never organiser names or contact details.
	 *
	 * @param array $rows   Report rows keyed by column header.
	 * @param array $coords WordCamp ID => array( lat, lng, site ).
	 * @return array
	 */
	public static function build( $rows, $coords = array() ) {
		$year = self::filter( 'wpcct_current_year', gmdate( 'Y' ) );

		$totals = array(
			'completed'           => 0,
			'scheduled'           => 0,
			'planning'            => 0,
			'attendees'           => 0,
			'completed_this_year' => 0,
			'countries'           => 0,
			'institutions'        => 0,
		);

		$regions = array();
		foreach ( self::REGIONS as $region ) {
			$regions[ $region ] = array(
				'completed'    => 0,
				'scheduled'    => 0,
				'planning'     => 0,
				'institutions' => 0,
				'attendees'    => 0,
			);
		}

		$pipeline           = array();
		$timeline           = array();
		$markers            = array();
		$events             = array();
		$unmapped           = array();
		$countries_seen     = array();
		$institutions_seen  = array();
		$region_institutions = array();

		foreach ( $rows as $row ) {
			if ( self::is_denied( $row ) ) {
				continue;
			}

			$status = trim( (string) ( isset( $row['Status'] ) ? $row['Status'] : '' ) );
			$bucket = self::status_bucket( $status );

			// The pipeline block shows every raw status, excluded ones included.
			$pipeline[ $status ] = ( isset( $pipeline[ $status ] ) ? $pipeline[ $status ] : 0 ) + 1;

			if ( self::STATUS_EXCLUDED === $bucket ) {
				continue;
			}

			$country = self::canonical_country( isset( $row['Country'] ) ? $row['Country'] : '' );
			$region  = self::region_for( $country );
			$people  = self::attendees( $row );
			$start   = trim( (string) ( isset( $row['Start Date (YYYY-mm-dd)'] ) ? $row['Start Date (YYYY-mm-dd)'] : '' ) );
			$inst    = self::institution_key( $row );

			$totals[ $bucket ]++;
			$totals['attendees'] += $people;

			if ( self::STATUS_COMPLETED === $bucket && 0 === strpos( $start, (string) $year ) ) {
				$totals['completed_this_year']++;
			}

			if ( '' === $region ) {
				$unmapped[ $country ] = ( isset( $unmapped[ $country ] ) ? $unmapped[ $country ] : 0 ) + 1;
			} else {
				$countries_seen[ $country ] = true;
				$regions[ $region ][ $bucket ]++;
				$regions[ $region ]['attendees'] += $people;
				if ( '' !== $inst ) {
					$region_institutions[ $region ][ $inst ] = true;
				}
			}

			if ( '' !== $inst ) {
				$institutions_seen[ $inst ] = true;
			}

			// Timeline buckets by month; the four rows with no start date drop out.
			if ( preg_match( '/^(\d{4}-\d{2})-\d{2}$/', $start, $m ) ) {
				if ( ! isset( $timeline[ $m[1] ] ) ) {
					$timeline[ $m[1] ] = array( 'completed' => 0, 'scheduled' => 0, 'attendees' => 0 );
				}
				if ( isset( $timeline[ $m[1] ][ $bucket ] ) ) {
					$timeline[ $m[1] ][ $bucket ]++;
				}
				$timeline[ $m[1] ]['attendees'] += $people;
			}

			$id    = trim( (string) ( isset( $row['ID'] ) ? $row['ID'] : '' ) );
			$event = array(
				'id'          => $id,
				'name'        => trim( (string) ( isset( $row['Name'] ) ? $row['Name'] : '' ) ),
				'institution' => trim( (string) ( isset( $row['Institution Name'] ) ? $row['Institution Name'] : '' ) ),
				'city'        => trim( (string) ( isset( $row['City'] ) ? $row['City'] : '' ) ),
				'country'     => $country,
				'region'      => $region,
				'status'      => $status,
				'bucket'      => $bucket,
				'start'       => $start,
				'end'         => trim( (string) ( isset( $row['End Date (YYYY-mm-dd)'] ) ? $row['End Date (YYYY-mm-dd)'] : '' ) ),
				'attendees'   => $people,
				'anticipated' => self::anticipated( $row ),
				'url'         => trim( (string) ( isset( $row['URL'] ) ? $row['URL'] : '' ) ),
			);
			$events[] = $event;

			if ( isset( $coords[ $id ] ) ) {
				$markers[] = array(
					'id'          => $id,
					'lat'         => (float) $coords[ $id ]['lat'],
					'lng'         => (float) $coords[ $id ]['lng'],
					'name'        => $event['name'],
					'institution' => $event['institution'],
					'city'        => $event['city'],
					'country'     => $country,
					'start'       => $start,
					'attendees'   => $people,
					'bucket'      => $bucket,
					'url'         => isset( $coords[ $id ]['site'] ) ? $coords[ $id ]['site'] : $event['url'],
				);
			}
		}

		foreach ( $region_institutions as $region => $set ) {
			$regions[ $region ]['institutions'] = count( $set );
		}

		$totals['countries']    = count( $countries_seen );
		$totals['institutions'] = count( $institutions_seen );

		ksort( $timeline );
		arsort( $pipeline );

		usort(
			$events,
			function ( $a, $b ) {
				return strcmp( $b['start'], $a['start'] );
			}
		);

		return array(
			'generated'          => gmdate( 'c' ),
			'totals'             => $totals,
			'regions'            => $regions,
			'pipeline'           => $pipeline,
			'timeline'           => $timeline,
			'markers'            => $markers,
			'events'             => $events,
			'unmapped_countries' => $unmapped,
		);
	}
```

- [ ] **Step 4: Run to verify it passes**

```bash
php tests/run-tests.php
```

If `countries` comes back as something other than 25, print `array_keys( $countries_seen )` and compare against the region map — a country present in the fixture but missing from the map lands in `unmapped_countries` instead, and that assertion catches it.

- [ ] **Step 5: Commit**

```bash
php -l includes/class-wpcct-normalize.php
git add -A
git commit -m "Build the public data blob from normalised report rows"
```

---

### Task 6: Create and seed the Airtable table, and generate seed.json

**Files:**
- Create: `data/seed.json`
- Create: `bin/build-seed.php`

**Interfaces:**
- Consumes: `WPCCT_Normalize::build()`, `wpcct_load_fixture()`.
- Produces: an Airtable table `Campus Connect Events` in base `appoiPJkMFdnJEmfa` holding 142 records; `data/seed.json` holding the blob for pre-first-sync rendering.

- [ ] **Step 1: Create the Airtable table**

In base `appoiPJkMFdnJEmfa`, create a table named `Campus Connect Events` with these fields, in this order and with these exact names — the sync reads by name:

| Field | Type |
| --- | --- |
| `Name` | Single line text (primary) |
| `WordCamp ID` | Number, precision 0 |
| `Status` | Single select |
| `Start Date` | Date, ISO |
| `End Date` | Date, ISO |
| `Institution Name` | Single line text |
| `City` | Single line text |
| `Country` | Single line text |
| `Anticipated Attendees` | Single line text |
| `Actual Attendees` | Number, precision 0 |
| `Series Event` | Number, precision 0 |
| `Created` | Date, ISO |
| `URL` | URL |

`Anticipated Attendees` is **single line text, not a number** — see Task 4. `Status` needs all eleven choices: Closed, Scheduled, In Pre-Planning, Needs Vetting, Needs Orientation/Interview, Interview/Orientation Scheduled, Approved for Pre-Planning Pending Agreement, Needs to Fill Out Listing, On Hold, Cancelled, Declined.

Add a table description recording provenance:

```
Campus Connect Details report from central.wordcamp.org (wordcamp-reports plugin),
exported 2026-08-27. Merge key: "WordCamp ID" (the report's ID column). Joins to the
WordCamps table on the same value. Read by the WPCC-Tracker WordPress plugin.
```

- [ ] **Step 2: Load the 142 rows**

Write the fixture into the table, mapping TSV columns to fields: `ID` → `WordCamp ID`, `Start Date (YYYY-mm-dd)` → `Start Date`, `End Date (YYYY-mm-dd)` → `End Date`, `Number of Anticipated Attendees` → `Anticipated Attendees`, the rest by matching name. Blank dates must be omitted rather than sent as empty strings, which Airtable rejects.

- [ ] **Step 3: Verify the load**

Read the table back and assert: 142 records; the `Status` distribution is Closed 56, Scheduled 15, In Pre-Planning 7, Needs Vetting 2, Needs Orientation/Interview 12, Interview/Orientation Scheduled 2, Approved for Pre-Planning Pending Agreement 13, Needs to Fill Out Listing 1, On Hold 9, Cancelled 12, Declined 13; the sum of `Actual Attendees` is 6490. If any differ, fix the load — do not proceed.

- [ ] **Step 4: Write the seed generator**

`bin/build-seed.php`:

```php
<?php
/**
 * Regenerate data/seed.json from the frozen report fixture.
 *
 * Run: php bin/build-seed.php
 *
 * @package WPCC_Tracker
 */

require __DIR__ . '/../tests/harness.php';
require __DIR__ . '/../includes/class-wpcct-normalize.php';

$blob         = WPCCT_Normalize::build( wpcct_load_fixture() );
$blob['seed'] = true;

$path = __DIR__ . '/../data/seed.json';
if ( ! is_dir( dirname( $path ) ) ) {
	mkdir( dirname( $path ), 0755, true );
}

file_put_contents( $path, json_encode( $blob, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );

printf( "Wrote %s (%d events, %d markers)\n", $path, count( $blob['events'] ), count( $blob['markers'] ) );
```

- [ ] **Step 5: Generate and sanity-check**

```bash
php bin/build-seed.php
php -r '$d=json_decode(file_get_contents("data/seed.json"),true); echo $d["totals"]["attendees"], " ", $d["totals"]["completed"], " ", count($d["markers"]), "\n";'
```
Expected: `6490 56 0` — no markers, because the seed is built without coordinates. The map block renders its empty state until the first sync.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Seed the Airtable table and generate data/seed.json"
```

---

### Task 7: Airtable sync

**Files:**
- Create: `includes/class-wpcct-sync.php`
- Modify: `wpcc-tracker.php`

**Interfaces:**
- Consumes: `WPCCT_Normalize::build()`, the option constants.
- Produces: `WPCCT_Sync::instance()`; `WPCCT_Sync::run(): true|WP_Error`; `WPCCT_Sync::settings(): array` with keys `airtable_pat`, `base_id`, `events_table`, `wordcamps_table`; `WPCCT_Sync::data(): array` returning `wpcct_data`, falling back to `data/seed.json`.

**Deviation from the spec, deliberate:** §3.3 called for a resumable, state-machine
sync mirroring `WPCT_Sync`. That machinery exists in WPCredits-Tracker because it walks
eleven tables and scrapes profiles.wordpress.org, which cannot finish inside one request.
This sync reads two tables totalling ~213 records — two or three API pages, a few seconds.
Resumability would be complexity with nothing to buy. The spec's actual requirement,
*"a partial run never overwrites the blob"*, is met differently: the blob is written once,
at the end, only after both fetches have fully succeeded, and a suspiciously small result
is rejected outright. If either fetch fails the old blob is untouched.

**Amended after review (2026-08-27):** the code below shipped with two gaps that the
Task 7 review caught, both since fixed in `124dfc6`. (a) `run()` guarded only the events
fetch, so a broken `filterByFormula` on the coordinates side returned an empty array,
passed silently, and blanked the map while reporting success — it now refuses the write
when `$coords` is empty. (b) `fetch_all()` treated an undecodable HTTP 200 body as an
empty page and stopped paginating without error — it now returns a `WP_Error` when the
body is not an array. Read the committed file, not this block, as the current source.

- [ ] **Step 1: Write the sync class**

`includes/class-wpcct-sync.php`:

```php
<?php
/**
 * Weekly Airtable sync. Reads the Campus Connect Events table for the reporting
 * spine and the WordCamps table for coordinates, joins them on WordCamp ID, and
 * stores the normalised blob in wpcct_data.
 *
 * @package WPCC_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPCCT_Sync {

	const API_URL = 'https://api.airtable.com/v0';

	/** Campus Connect subset of the WordCamps table. */
	const WC_EVENT_TYPE_FIELD = 'fldy8KgSynQemzN1P';
	const WC_ID_FIELD         = 'fldLuWFoqkTj4fwtM';
	const WC_LAT_FIELD        = 'fldatbggVWCplyNBR';
	const WC_LNG_FIELD        = 'fldUmzvFHhXrXGF1P';
	const WC_SITE_FIELD       = 'fldLFLMaxNa4WFnF5';

	/** @var WPCCT_Sync|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( WPCCT_CRON_WEEKLY, array( __CLASS__, 'run' ) );
	}

	/**
	 * Stored settings, with defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		$saved = get_option( WPCCT_OPT_SETTINGS, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return array_merge(
			array(
				'airtable_pat'    => '',
				'base_id'         => 'appoiPJkMFdnJEmfa',
				'events_table'    => 'Campus Connect Events',
				'wordcamps_table' => 'tblLBVsO4rU2oj2Ki',
			),
			$saved
		);
	}

	/**
	 * The blob the front end renders. Falls back to the bundled seed so the
	 * plugin is never blank before its first sync.
	 *
	 * @return array
	 */
	public static function data() {
		$data = get_option( WPCCT_OPT_DATA, array() );
		if ( is_array( $data ) && ! empty( $data['totals'] ) ) {
			return $data;
		}

		$seed = WPCCT_DIR . 'data/seed.json';
		if ( is_readable( $seed ) ) {
			$decoded = json_decode( file_get_contents( $seed ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return array();
	}

	/**
	 * Run a full sync.
	 *
	 * On failure the previous blob is left untouched — a broken sync degrades
	 * to stale data, never to an empty dashboard.
	 *
	 * @return true|WP_Error
	 */
	public static function run() {
		$settings = self::settings();
		if ( empty( $settings['airtable_pat'] ) ) {
			return self::fail( __( 'No Airtable token configured.', 'wpcc-tracker' ) );
		}

		$events = self::fetch_all( $settings, $settings['events_table'] );
		if ( is_wp_error( $events ) ) {
			return self::fail( $events->get_error_message() );
		}

		$coords = self::fetch_coords( $settings );
		if ( is_wp_error( $coords ) ) {
			return self::fail( $coords->get_error_message() );
		}

		$rows = array();
		foreach ( $events as $record ) {
			$f = isset( $record['fields'] ) ? $record['fields'] : array();
			$rows[] = array(
				'Start Date (YYYY-mm-dd)'         => isset( $f['Start Date'] ) ? $f['Start Date'] : '',
				'End Date (YYYY-mm-dd)'           => isset( $f['End Date'] ) ? $f['End Date'] : '',
				'Status'                          => isset( $f['Status'] ) ? $f['Status'] : '',
				'Name'                            => isset( $f['Name'] ) ? $f['Name'] : '',
				'Institution Name'                => isset( $f['Institution Name'] ) ? $f['Institution Name'] : '',
				'City'                            => isset( $f['City'] ) ? $f['City'] : '',
				'Country'                         => isset( $f['Country'] ) ? $f['Country'] : '',
				'Number of Anticipated Attendees' => isset( $f['Anticipated Attendees'] ) ? $f['Anticipated Attendees'] : '',
				'Actual Attendees'                => isset( $f['Actual Attendees'] ) ? $f['Actual Attendees'] : '',
				'Series Event'                    => isset( $f['Series Event'] ) ? $f['Series Event'] : '',
				'Created'                         => isset( $f['Created'] ) ? $f['Created'] : '',
				'URL'                             => isset( $f['URL'] ) ? $f['URL'] : '',
				'ID'                              => isset( $f['WordCamp ID'] ) ? (string) $f['WordCamp ID'] : '',
			);
		}

		if ( count( $rows ) < 50 ) {
			/* A healthy export is ~142 rows. A tiny result means a filter or a
			   renamed field, not a shrunken programme — keep the old blob. */
			return self::fail(
				sprintf(
					/* translators: %d: number of records returned. */
					__( 'Airtable returned only %d events; refusing to overwrite good data.', 'wpcc-tracker' ),
					count( $rows )
				)
			);
		}

		update_option( WPCCT_OPT_DATA, WPCCT_Normalize::build( $rows, $coords ), false );
		update_option( WPCCT_OPT_LASTSYNC, time(), false );
		delete_option( WPCCT_OPT_LASTERR );

		return true;
	}

	/**
	 * Coordinates for the Campus Connect subset of the WordCamps table.
	 *
	 * @param array $settings Settings.
	 * @return array|WP_Error WordCamp ID => array( lat, lng, site ).
	 */
	private static function fetch_coords( $settings ) {
		$records = self::fetch_all(
			$settings,
			$settings['wordcamps_table'],
			array(
				'returnFieldsByFieldId' => 'true',
				'filterByFormula'       => "{" . self::WC_EVENT_TYPE_FIELD . "}='Campus Connect'",
			)
		);
		if ( is_wp_error( $records ) ) {
			return $records;
		}

		$out = array();
		foreach ( $records as $record ) {
			$f = isset( $record['fields'] ) ? $record['fields'] : array();
			if ( empty( $f[ self::WC_ID_FIELD ] ) || ! isset( $f[ self::WC_LAT_FIELD ], $f[ self::WC_LNG_FIELD ] ) ) {
				continue;
			}
			$out[ (string) $f[ self::WC_ID_FIELD ] ] = array(
				'lat'  => (float) $f[ self::WC_LAT_FIELD ],
				'lng'  => (float) $f[ self::WC_LNG_FIELD ],
				'site' => isset( $f[ self::WC_SITE_FIELD ] ) ? $f[ self::WC_SITE_FIELD ] : '',
			);
		}

		return $out;
	}

	/**
	 * Fetch every record from a table, following Airtable's offset pagination.
	 *
	 * @param array  $settings Settings.
	 * @param string $table    Table id or name.
	 * @param array  $extra    Extra query args.
	 * @return array|WP_Error
	 */
	private static function fetch_all( $settings, $table, $extra = array() ) {
		$records = array();
		$offset  = '';
		$guard   = 0;

		do {
			if ( ++$guard > 50 ) {
				return new WP_Error( 'wpcct_pagination', __( 'Airtable pagination did not terminate.', 'wpcc-tracker' ) );
			}

			$args = array_merge( array( 'pageSize' => 100 ), $extra );
			if ( $offset ) {
				$args['offset'] = $offset;
			}

			$url = self::API_URL . '/' . rawurlencode( $settings['base_id'] ) . '/' . rawurlencode( $table )
				. '?' . http_build_query( $args );

			$resp = wp_remote_get(
				$url,
				array(
					'timeout' => 20,
					'headers' => array( 'Authorization' => 'Bearer ' . $settings['airtable_pat'] ),
				)
			);

			if ( is_wp_error( $resp ) ) {
				return new WP_Error( 'wpcct_http', $resp->get_error_message() );
			}

			$code = (int) wp_remote_retrieve_response_code( $resp );
			$body = json_decode( wp_remote_retrieve_body( $resp ), true );

			if ( 200 !== $code ) {
				$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code;
				return new WP_Error( 'wpcct_api', sprintf( 'Airtable (%s): %s', $table, $msg ) );
			}

			if ( ! empty( $body['records'] ) ) {
				$records = array_merge( $records, $body['records'] );
			}

			$offset = isset( $body['offset'] ) ? $body['offset'] : '';
		} while ( $offset );

		return $records;
	}

	/**
	 * Record a failure without disturbing the last good blob.
	 *
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private static function fail( $message ) {
		update_option( WPCCT_OPT_LASTERR, $message, false );

		return new WP_Error( 'wpcct_sync', $message );
	}

	/**
	 * Schedule the weekly sync. Called on activation.
	 */
	public static function activate() {
		if ( ! wp_next_scheduled( WPCCT_CRON_WEEKLY ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', WPCCT_CRON_WEEKLY );
		}
	}

	/**
	 * Clear the schedule. Called on deactivation.
	 */
	public static function deactivate() {
		$ts = wp_next_scheduled( WPCCT_CRON_WEEKLY );
		if ( $ts ) {
			wp_unschedule_event( $ts, WPCCT_CRON_WEEKLY );
		}
	}
}
```

- [ ] **Step 2: Wire it up**

Append to `wpcc-tracker.php`:

```php
require_once WPCCT_DIR . 'includes/class-wpcct-sync.php';

/**
 * WordPress ships no 'weekly' schedule before 6.x guarantees it; register ours.
 *
 * @param array $schedules Existing schedules.
 * @return array
 */
function wpcct_cron_schedules( $schedules ) {
	if ( ! isset( $schedules['weekly'] ) ) {
		$schedules['weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once Weekly', 'wpcc-tracker' ),
		);
	}
	return $schedules;
}
add_filter( 'cron_schedules', 'wpcct_cron_schedules' ); // phpcs:ignore WordPress.WP.CronInterval

add_action( 'plugins_loaded', array( 'WPCCT_Sync', 'instance' ) );
register_activation_hook( WPCCT_FILE, array( 'WPCCT_Sync', 'activate' ) );
register_deactivation_hook( WPCCT_FILE, array( 'WPCCT_Sync', 'deactivate' ) );
```

- [ ] **Step 3: Verify**

```bash
php -l includes/class-wpcct-sync.php && php -l wpcc-tracker.php && php tests/run-tests.php
```

The sync itself is not unit-tested — it is the only networked component and has no seam that a bare-PHP harness can reach. It is exercised end to end in Task 14 against the real site.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "Add the weekly Airtable sync"
```

---

### Task 8: Settings screen

**Files:**
- Create: `includes/class-wpcct-settings.php`
- Modify: `wpcc-tracker.php`

**Interfaces:**
- Consumes: `WPCCT_Sync::settings()`, `::run()`, `::data()`.
- Produces: a Settings → WPCC-Tracker page; nothing other code calls.

- [ ] **Step 1: Implement**

`includes/class-wpcct-settings.php` registers an options page under `options-general.php` with:

- Password field for `airtable_pat`, text fields for `base_id`, `events_table`, `wordcamps_table`, saved through the Settings API into `WPCCT_OPT_SETTINGS` with `sanitize_text_field`.
- A **Sync now** button (`admin_post_wpcct_sync`, nonce `wpcct_sync`, capability `manage_options`) calling `WPCCT_Sync::run()` and redirecting back with the result in a query arg.
- A status panel showing the last sync time, `WPCCT_OPT_LASTERR` if set, and the current totals from `WPCCT_Sync::data()`.
- An **Unmapped countries** panel listing `data()['unmapped_countries']` — country and count — with the note that these are counted in the totals but excluded from the regional roll-up, and are fixed by filtering `wpcct_region_map` or `wpcct_excluded_rows`.

Never echo the PAT back into the field's `value`; render a placeholder such as `••••••••` when one is stored, and treat an empty submission as "leave unchanged".

- [ ] **Step 2: Wire it up**

```php
require_once WPCCT_DIR . 'includes/class-wpcct-settings.php';
add_action( 'plugins_loaded', array( 'WPCCT_Settings', 'instance' ) );
```

- [ ] **Step 3: Verify and commit**

```bash
php -l includes/class-wpcct-settings.php && php tests/run-tests.php
git add -A
git commit -m "Add the settings screen with sync status and unmapped-country report"
```

---

### Task 9: Render core and shared assets

**Files:**
- Create: `includes/class-wpcct-render.php`
- Create: `assets/css/tracker.css`
- Create: `assets/js/tracker.js`
- Create: `assets/lib/chart.umd.min.js`
- Create: `assets/lib/leaflet/` (leaflet.js, leaflet.css, images/)
- Modify: `wpcc-tracker.php`

**Interfaces:**
- Consumes: `WPCCT_Sync::data()`.
- Produces: `WPCCT_Render::section( string $key ): string`; `::render(): string`; `::shortcode( array $atts ): string` for `[wpcc_tracker]`; `::enqueue_assets( array $data )`; `::provenance(): string`. Front-end globals `window.WPCCT_DATA` and the `data-wpcct-sec` mount attribute.

- [ ] **Step 1: Copy the bundled libraries**

```bash
cp ../wpcredits-tracker/assets/lib/chart.umd.min.js assets/lib/
cp -R ../wpcredits-tracker/assets/lib/leaflet assets/lib/
```

- [ ] **Step 2: Implement the render class**

Mirror `../wpcredits-tracker/includes/class-wpct-render.php`. Key differences:

- `section( $key )` emits `<div class="wpcct-tracker" data-wpcct-sec="<key>"></div>` plus the provenance footer, and calls `enqueue_assets()`.
- `enqueue_assets()` registers Chart.js and Leaflet from `assets/lib/`, `assets/css/tracker.css`, `assets/js/tracker.js`, and inlines the blob once per request via `wp_add_inline_script( 'wpcct-tracker', 'window.WPCCT_DATA = ' . wp_json_encode( $data ) . ';', 'before' )`. Guard with a static flag so eight blocks on one page inline it once.
- `provenance()` returns `WordCamp Central via Airtable · synced <date>`, or `seed data` when `data()['seed']` is set, or `WordCamp Central via Airtable · last synced <date> (stale)` when the last sync is older than 14 days.
- When `data()` is empty, every section renders a muted "No Campus Connect data yet" notice, never a PHP warning.

- [ ] **Step 3: Write the JS core**

`assets/js/tracker.js` reads `window.WPCCT_DATA`, finds every `[data-wpcct-sec]` mount, and dispatches on its key to a per-section builder. Define the builder registry and the shared number/date formatters now; Tasks 11–13 fill in the individual builders. Bail silently if `WPCCT_DATA` is undefined.

- [ ] **Step 4: Write the CSS shell**

`assets/css/tracker.css`, everything scoped under `.wpcct-tracker`: stat-tile grid, table styling, chart canvas sizing, map height, provenance footer. Match the WPCredits-Tracker stat treatment — blue, weight 800, fluid `clamp()` — so the two trackers read as one family.

- [ ] **Step 5: Wire it up**

```php
require_once WPCCT_DIR . 'includes/class-wpcct-render.php';
add_shortcode( 'wpcc_tracker', array( 'WPCCT_Render', 'shortcode' ) );
```

- [ ] **Step 6: Verify and commit**

```bash
php -l includes/class-wpcct-render.php && php tests/run-tests.php
git add -A
git commit -m "Add render core, bundled libraries and shared assets"
```

---

### Task 10: Block registration

**Files:**
- Create: `includes/class-wpcct-block.php`
- Create: `blocks/tracker/block.json`
- Create: `blocks/tracker/editor.js`
- Create: `blocks/tracker/editor.asset.php`
- Create: `assets/js/blocks-editor.js`
- Modify: `wpcc-tracker.php`

**Interfaces:**
- Consumes: `WPCCT_Render::section()`, `::render()`.
- Produces: `WPCCT_Block::sections(): array` keyed `scale`, `regions`, `map`, `timeline`, `pipeline`, `events`, each `array( title, description, dashicon )`; blocks `wpcc-tracker/<key>` and `wpcc-tracker/tracker`; block category `wpcc-tracker`.

- [ ] **Step 1: Implement**

Mirror `../wpcredits-tracker/includes/class-wpct-block.php` exactly, substituting the prefix, category slug `wpcc-tracker`, category title `WPCC-Tracker`, and this section list:

```php
	public static function sections() {
		return array(
			'scale'    => array( __( 'WPCC: Scale & Momentum', 'wpcc-tracker' ), __( 'Completed, scheduled, in planning, attendees and countries.', 'wpcc-tracker' ), 'chart-bar' ),
			'regions'  => array( __( 'WPCC: Regions', 'wpcc-tracker' ), __( 'Events, institutions and attendance by world region.', 'wpcc-tracker' ), 'admin-site-alt3' ),
			'map'      => array( __( 'WPCC: Event Map', 'wpcc-tracker' ), __( 'A world map of Campus Connect events.', 'wpcc-tracker' ), 'location-alt' ),
			'timeline' => array( __( 'WPCC: Timeline', 'wpcc-tracker' ), __( 'Events per month and cumulative attendance.', 'wpcc-tracker' ), 'chart-line' ),
			'pipeline' => array( __( 'WPCC: Pipeline', 'wpcc-tracker' ), __( 'Every organiser status, including cancelled and declined.', 'wpcc-tracker' ), 'filter' ),
			'events'   => array( __( 'WPCC: Events', 'wpcc-tracker' ), __( 'Recent and upcoming events with institution and attendance.', 'wpcc-tracker' ), 'list-view' ),
		);
	}
```

`blocks/tracker/block.json` declares `wpcc-tracker/tracker`, title `WPCC-Tracker (full)`, icon `chart-area`, category `wpcc-tracker`, `apiVersion: 3`, `editorScript: file:./editor.js`. `editor.asset.php` returns the same dependency list as WPCredits-Tracker's, with `'version' => WPCCT_VERSION`.

`assets/js/blocks-editor.js` mirrors WPCredits-Tracker's: a `SECTIONS` array of `[ suffix, label, blurb ]` matching the PHP titles, each registering a dashed-border editor placeholder and `save: function () { return null; }`.

- [ ] **Step 2: Wire it up**

```php
require_once WPCCT_DIR . 'includes/class-wpcct-block.php';
add_action( 'plugins_loaded', array( 'WPCCT_Block', 'instance' ) );
```

- [ ] **Step 3: Verify and commit**

Every registered block must appear in the inserter under "WPCC-Tracker" and render its placeholder without a console error.

```bash
php -l includes/class-wpcct-block.php && php tests/run-tests.php
git add -A
git commit -m "Register the six section blocks and the combined block"
```

---

### Task 11: Scale and Regions sections

**Files:**
- Modify: `assets/js/tracker.js`
- Modify: `assets/css/tracker.css`

**Interfaces:**
- Consumes: `window.WPCCT_DATA.totals`, `.regions`.
- Produces: builders `buildScale( data )` and `buildRegions( data )` returning HTML strings, registered under keys `scale` and `regions`.

- [ ] **Step 1: Scale**

Six stat tiles from `data.totals`: completed all time, completed this year (labelled with the year), scheduled now, in setup & planning, total attendees, countries. Format numbers with `toLocaleString()`. Attendees carries the caption "at completed events" so the figure is not read as programme-wide reach.

- [ ] **Step 2: Regions**

A grouped bar chart over `data.regions` — one group per region, three bars (completed, scheduled, in planning) — with the full table beneath: Region, Completed, Scheduled, In Planning, Institutions, Attendees, plus a totals row. Iterate the five regions in `WPCCT_Normalize::REGIONS` order so a zero region still renders.

- [ ] **Step 3: Verify**

Place both blocks on a draft page. Check the invariants, not the sheet's published per-region splits — the sheet is a 2026-08-25 snapshot and the data has moved on:

- The Completed column totals **56**, Scheduled **15**, In Planning **44**.
- The region rows sum to **43** in planning, one short of `totals.planning` 44: one live
  event has a blank Country and is counted in the totals but excluded from the regional
  roll-up. Derive the table's own totals row by summing the region rows so the column
  visibly adds up; do not reconcile it against `totals.planning`.
- Every one of the five regions has a row, including North America at or near zero.
- The Attendees column totals 6490 minus whatever sits on the single unmapped row.

Record the actual per-region figures in the commit message. If they differ from the sheet by more than the two events that changed state since 2026-08-25, stop and reconcile before continuing — that would mean the region map is wrong, not that the data moved.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "Add the Scale and Regions sections"
```

---

### Task 12: Map and Timeline sections

**Files:**
- Modify: `assets/js/tracker.js`
- Modify: `assets/css/tracker.css`

**Interfaces:**
- Consumes: `window.WPCCT_DATA.markers`, `.timeline`.
- Produces: builders `buildMap( data )` and `buildTimeline( data )`, registered under keys `map` and `timeline`.

- [ ] **Step 1: Map**

Leaflet, CARTO/OSM tiles, one marker per entry in `data.markers`, marker colour by `bucket` (completed vs scheduled). Popup: event name linking to `url`, institution, city and country, start date, and attendance when non-zero. Fit bounds to the markers; fall back to a world view when there are none. Render the empty state — "Map data arrives with the first sync" — when `markers` is empty, which is the case for the bundled seed.

- [ ] **Step 2: Timeline**

A Chart.js combo over `data.timeline`: stacked bars for completed and scheduled events per month, plus a line for cumulative attendance on a second axis. Note in a caption that four events have no start date and are omitted.

- [ ] **Step 3: Verify**

Both blocks render with the seed data — the map shows its empty state, the timeline shows months from 2024-10 onward — and neither logs a console error.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "Add the Map and Timeline sections"
```

---

### Task 13: Pipeline and Events sections

**Files:**
- Modify: `assets/js/tracker.js`
- Modify: `assets/css/tracker.css`

**Interfaces:**
- Consumes: `window.WPCCT_DATA.pipeline`, `.events`.
- Produces: builders `buildPipeline( data )` and `buildEvents( data )`, registered under keys `pipeline` and `events`.

- [ ] **Step 1: Pipeline**

A horizontal bar per raw status in `data.pipeline`, ordered by the organiser journey rather than by count: Needs Vetting → Needs Orientation/Interview → Interview/Orientation Scheduled → Approved for Pre-Planning Pending Agreement → Needs to Fill Out Listing → In Pre-Planning → On Hold → Scheduled → Closed, then Cancelled and Declined.

Cancelled and Declined render in a muted colour, visually separated from the live pipeline, under a sub-heading "Did not proceed". Caption the group with its share of all events — 24 of 139 on the seed data — since that is the conversion signal the block exists to show. A status present in the data but missing from the ordering array must still render, appended at the end, so a new upstream status is never silently dropped.

- [ ] **Step 2: Events**

Two tables from `data.events` (already sorted newest first): **Upcoming**, filtered to `bucket === 'scheduled'` and reversed to soonest-first, and **Recent**, filtered to `bucket === 'completed'`, capped at 20 with a count of how many more exist. Columns: date, event name linked to `url`, institution, city and country, attendance. Show `anticipated` verbatim in the upcoming table, labelled "anticipated", never summed.

- [ ] **Step 3: Verify**

The pipeline block must total 139 across all bars, with Cancelled **11** and Declined **13** in the "Did not proceed" group — 24 of 139. (Cancelled is 11, not the raw 12: one cancelled row names a fictional country and is dropped by the denylist.)

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "Add the Pipeline and Events sections"
```

---

### Task 14: Combined block, end-to-end verification, and packaging

**Files:**
- Modify: `assets/js/tracker.js`
- Modify: `readme.txt`
- Modify: `wpcc-tracker.php`

**Interfaces:**
- Consumes: every builder from Tasks 11–13.
- Produces: a working plugin zip at `~/GitHub/wpcc-tracker.zip`.

- [ ] **Step 1: Combined block**

`WPCCT_Render::render()` emits all six mounts in order — scale, regions, map, timeline, pipeline, events — with one provenance footer, composing the same builders rather than duplicating them.

- [ ] **Step 2: Install and run a real sync**

Install on nisabareportingp2.wpcomstaging.com. Note from prior work on that site: it runs the `indice` block theme and is in Coming soon mode, so logged-out checks return the WP.com splash with HTTP 200 — verify while logged in.

Enter the Airtable PAT, click **Sync now**, and confirm the settings panel reports 142 events processed, totals of 56 / 15 / 46 / 6490, and 71 markers. Then confirm `wpcct_data` is populated:

```bash
wp option get wpcct_data --format=json | head -c 400
```

- [ ] **Step 3: Verify the rendered page**

Add the combined block to a draft page and check each section against the Stats sheet. Confirm the map now shows 71 markers rather than the seed's empty state.

- [ ] **Step 4: Confirm no personal data shipped**

```bash
wp option get wpcct_data --format=json | grep -ciE 'organizer|e-?mail|@' 
```
Expected: `0`.

- [ ] **Step 5: Package**

Build `~/GitHub/wpcc-tracker.zip` with a single top-level `wpcc-tracker/` folder, excluding `.git`, `tests/`, `bin/` and `docs/`, per the project's standing packaging rule.

- [ ] **Step 6: Commit and push the mirror**

```bash
php tests/run-tests.php
git add -A
git commit -m "Add the combined block and package v1.0.0"
```

Then mirror the source to `WordPress/WPCredits` trunk, following the standing mirror rule — secret and PII scan first, `rsync --delete`, then commit and push as separate calls.

---

## Follow-up, not part of this plan

The upstream PR adding `$rest_base` + `rest_callback` to `CampusConnect_Details` in `WordPress/wordcamp.org`. It removes the manual export step by letting `wordcamp-airtable-connector` refill the `Campus Connect Events` table directly. It changes nothing in this plugin — the Airtable contract is identical either way — so it is tracked separately.
