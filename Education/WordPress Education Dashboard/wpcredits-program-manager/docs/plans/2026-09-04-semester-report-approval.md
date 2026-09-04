# Semester Report Approval Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The site drafts each institution's semester report when a cohort ends (or when a manager presses Draft now), program managers review, edit and approve it, and the institution reads only approved reports and downloads the PDF.

**Architecture:** The data class `WPCPM_Semester_Report` keeps the snapshot, consent and print behaviour as shipped and gains two states (`draft`, `approved` replacing `final`), an approval stamp, an origin, an upgrade, a log, and three pure readers (`due()`, `queue()`, `approved_since()`). The request class `WPCPM_Semester_Report_Screen` gains the daily job, the Draft now and Approve handlers, two mails, and a read-only member view; the policy's edit ground becomes manager-only. The Institutions module wires the cron, the wp-admin card and the settings.

**Tech Stack:** WordPress plugin PHP (7.4+, WP 6.5+), no framework; tests are the plugin's own `bin/test-*.php` suites run with `php`; `bin/check-references.php`, `bash bin/check-standards.sh`, `bash bin/build`.

**Spec:** `docs/specs/2026-09-04-semester-report-approval-design.md` (amends section 7.9 and decision 12 of `docs/specs/2026-09-02-institutions-module-design.md`).

## Global Constraints

- Version ships as **1.91.0**: `Version:` header and `WPCPM_VERSION` in `wpcredits-program-manager.php`, `Stable tag` and a `= 1.91.0 =` changelog entry in `readme.txt`.
- **No em dashes, no en dashes** anywhere (code, comments, strings, docs, tests). `bin/check-standards.sh` and the suite's own dash check fail on U+2013 and U+2014.
- **Full product names**: "Student Report Card", "Mentor Report Card", "Institution Dashboard", "Administrator Dashboard"; the document is "the semester report".
- **Comments explain why** and name the bug or decision behind a rule; prefix conventions are `OPT_` (option name), `META_` (meta key), `ACTION_` (`admin_post_` action and nonce stem), `CRON_` (hook), `STATE_` / `LOG_` / `ORIGIN_` (server vocabularies); text domain `wpcredits-program-manager`.
- **Every behaviour has an assertion** in a `bin/test-*.php` suite using the shared `ck( $label, $actual, $expected )` helper (strict `===`). Suites are plain PHP run from the plugin root, e.g. `php bin/test-semester-report.php`.
- **The one refusal**: a report that is not this reader's reads exactly as one that does not exist (`unknown()` on print, `refused` on a handler).
- **Every test send** uses `maciej@a8c.com` and no other address.
- **Manager-only writes**: every edit, draft, approve and reopen asks `ACT_EDIT_SEMESTER_REPORT`, whose only ground is `manager` after this plan.
- The plugin directory `~/GitHub/wpcredits-program-manager` is **not a git repository**; "commit" in this plan means: keep the suites green, then at the end of the task run `bash bin/build` only if the task says so. The mirror push happens once, in Task 9.

---

### Task 1: Two states, the approval stamp, the origin, the upgrade and the log

**Files:**
- Modify: `includes/modules/class-wpcpm-semester-report.php` (constants block lines 55-215; `init()` line 224; `set_state()` line 576; `delete_all()` line 606; `generate()` line 657; `store()` line 737)
- Test: `bin/test-semester-report.php` (append a block before `echo "\n=== House rules ===\n";`)

**Interfaces:**
- Produces on `WPCPM_Semester_Report`: `const STATE_APPROVED = 'approved'` (replacing `STATE_FINAL`), `const META_APPROVED`, `const META_ORIGIN`, `const META_IN_PROGRESS`, `const ORIGIN_AUTO = 'auto'`, `const ORIGIN_MANAGER = 'manager'`, `const OPT_STATE_VERSION`, `const STATE_VERSION = 2`, `const OPT_AUTODRAFT_SINCE`, `const OPT_LOG`, `const LOG_MAX = 200`, `const LOG_DRAFTED`, `const LOG_DRAFT_FAILED`, `const LOG_APPROVED`, `const LOG_REOPENED`; `approve( WP_Post $post, $user_id ): bool`, `reopen( WP_Post $post ): bool`, `approved_at( WP_Post $post ): array` (`array()` or `array( 'at' => int, 'by' => int )`), `origin_of( WP_Post $post ): string`, `in_progress_of( WP_Post $post ): int`, `maybe_upgrade(): void`, `log( $event, $institution, $cohort, $actor, array $extra = array() ): void`, `log_entries(): array`; `generate( $institution, $cohort, $origin = self::ORIGIN_MANAGER )`.

- [ ] **Step 1: Write the failing tests**

Append to `bin/test-semester-report.php` immediately before the line `echo "\n=== House rules ===\n";`:

```php
echo "\n=== Two states: draft and approved ===\n";

// Everything the earlier blocks generated was removed by delete_all() above, so this block
// stands its own reports up. A manager, acting for nobody in particular.
$GLOBALS['manage']     = true;
$GLOBALS['acting']     = '';
$GLOBALS['uid']        = 3;
$GLOBALS['transients'] = array();
$GLOBALS['fail_table'] = '';
$GLOBALS['flash']      = array();
$GLOBALS['mail']       = array();

$s_post_id = WPCPM_Semester_Report::generate( $A, $COHORT );
ck( 'a fresh report is a draft', WPCPM_Semester_Report::state( get_post( $s_post_id ) ), 'draft' );
ck( 'and its origin is a manager, the default', WPCPM_Semester_Report::origin_of( get_post( $s_post_id ) ), 'manager' );
ck( 'the vocabulary is draft and approved', array( WPCPM_Semester_Report::STATE_DRAFT, WPCPM_Semester_Report::STATE_APPROVED ), array( 'draft', 'approved' ) );
ck( 'final is not a state any more', WPCPM_Semester_Report::set_state( get_post( $s_post_id ), 'final' ), false );
ck( 'nothing on the class still names it', defined( 'WPCPM_Semester_Report::STATE_FINAL' ), false );

ck( 'approve writes the state and the stamp', WPCPM_Semester_Report::approve( get_post( $s_post_id ), 3 ), true );
$stamp = WPCPM_Semester_Report::approved_at( get_post( $s_post_id ) );
ck( 'the stamp names the manager', isset( $stamp['by'] ) ? $stamp['by'] : null, 3 );
ck( 'and a time', ! empty( $stamp['at'] ), true );
ck( 'the state reads approved', WPCPM_Semester_Report::state( get_post( $s_post_id ) ), 'approved' );

$again = WPCPM_Semester_Report::generate( $A, $COHORT );
ck( 'generating an approved report is refused', is_wp_error( $again ) ? $again->get_error_code() : 'no error', 'wpcpm_report_approved' );

ck( 'reopen makes it a draft', WPCPM_Semester_Report::reopen( get_post( $s_post_id ) ), true );
ck( 'and the state says so', WPCPM_Semester_Report::state( get_post( $s_post_id ) ), 'draft' );
ck( 'and the stamp is gone', WPCPM_Semester_Report::approved_at( get_post( $s_post_id ) ), array() );

$auto_id = WPCPM_Semester_Report::generate( $B, $COHORT, WPCPM_Semester_Report::ORIGIN_AUTO );
ck( 'the job\'s origin is recorded', WPCPM_Semester_Report::origin_of( get_post( $auto_id ) ), 'auto' );
WPCPM_Semester_Report::generate( $B, $COHORT, WPCPM_Semester_Report::ORIGIN_MANAGER );
ck( 'and a regeneration by hand does not rewrite it: the origin is how the report came to exist', WPCPM_Semester_Report::origin_of( get_post( $auto_id ) ), 'auto' );

echo "\n=== The upgrade: final becomes approved, once ===\n";

$legacy = wp_insert_post( array( 'post_type' => WPCPM_Semester_Report::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Legacy final' ) );
update_post_meta( $legacy, WPCPM_Semester_Report::META_INSTITUTION, $C );
update_post_meta( $legacy, WPCPM_Semester_Report::META_COHORT, '2025-H2' );
update_post_meta( $legacy, WPCPM_Semester_Report::META_STATE, 'final' );
unset( $GLOBALS['opts'][ WPCPM_Semester_Report::OPT_STATE_VERSION ], $GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] );

WPCPM_Semester_Report::maybe_upgrade();
ck( 'a final report reads approved after the upgrade', WPCPM_Semester_Report::state( get_post( $legacy ) ), 'approved' );
$legacy_stamp = WPCPM_Semester_Report::approved_at( get_post( $legacy ) );
ck( 'approved by nobody, because approval did not exist when it was marked', isset( $legacy_stamp['by'] ) ? $legacy_stamp['by'] : null, 0 );
ck( 'the vocabulary version is stamped', (int) get_option( WPCPM_Semester_Report::OPT_STATE_VERSION ), 2 );
ck( 'and the since-date is today', get_option( WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ), gmdate( 'Y-m-d' ) );
ck( 'the draft was left alone', WPCPM_Semester_Report::state( get_post( $s_post_id ) ), 'draft' );

update_post_meta( $legacy, WPCPM_Semester_Report::META_STATE, 'final' );
$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] = '2020-01-01';
WPCPM_Semester_Report::maybe_upgrade();
ck( 'a second run does nothing', WPCPM_Semester_Report::state( get_post( $legacy ) ), 'final' );
ck( 'and never moves the since-date', get_option( WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ), '2020-01-01' );
update_post_meta( $legacy, WPCPM_Semester_Report::META_STATE, 'approved' );

echo "\n=== The log ===\n";

$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_LOG ] = array();
WPCPM_Semester_Report::log( WPCPM_Semester_Report::LOG_DRAFTED, $A, $COHORT, 0, array( 'in_progress' => 2, 'email' => 'anna@example.test' ) );
WPCPM_Semester_Report::log( WPCPM_Semester_Report::LOG_APPROVED, $A, $COHORT, 3 );
$entries = WPCPM_Semester_Report::log_entries();
ck( 'newest first', array( $entries[0]['event'], $entries[1]['event'] ), array( 'approved', 'drafted' ) );
ck( 'the job is actor 0', $entries[1]['actor'], 0 );
ck( 'the in-progress count travels', $entries[1]['in_progress'], 2 );
ck( 'and nothing else does: an address handed to the log is not kept', array_key_exists( 'email', $entries[1] ), false );
for ( $i = 0; $i < WPCPM_Semester_Report::LOG_MAX + 5; $i++ ) {
	WPCPM_Semester_Report::log( WPCPM_Semester_Report::LOG_REOPENED, $A, $COHORT, 3 );
}
ck( 'the log is capped', count( WPCPM_Semester_Report::log_entries() ), WPCPM_Semester_Report::LOG_MAX );
WPCPM_Semester_Report::log( WPCPM_Semester_Report::LOG_DRAFT_FAILED, $A, $COHORT, 0, array( 'why' => str_repeat( 'x', 500 ) ) );
ck( 'a reason is cut short', strlen( WPCPM_Semester_Report::log_entries()[0]['why'] ), 200 );

WPCPM_Semester_Report::delete_all();
ck( 'uninstall takes the log, the version and the since-date', array(
	get_option( WPCPM_Semester_Report::OPT_LOG, 'gone' ),
	get_option( WPCPM_Semester_Report::OPT_STATE_VERSION, 'gone' ),
	get_option( WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE, 'gone' ),
), array( 'gone', 'gone', 'gone' ) );
```

- [ ] **Step 2: Run the suite to verify it fails**

Run: `php bin/test-semester-report.php 2>&1 | tail -30`
Expected: a PHP fatal at the first reference to `WPCPM_Semester_Report::STATE_APPROVED` (undefined class constant), or `FAIL` lines for every new check.

- [ ] **Step 3: Replace the state constants and add the new ones**

In `includes/modules/class-wpcpm-semester-report.php`, replace the `META_STATE`, `STATE_DRAFT` and `STATE_FINAL` declarations (the whole comment-and-constant pairs) with:

```php
	/** `draft` or `approved`. Version 2 of the vocabulary; `maybe_upgrade()` flips `final`. */
	const META_STATE = '_wpcpm_report_state';
	/**
	 * Who approved the report and when: `array( 'at' => int, 'by' => int )`.
	 *
	 * `by` is 0 for a report that was `final` before approval existed and was flipped by
	 * `maybe_upgrade()`: nobody pressed Approve on it, and a stamp naming the upgrading
	 * request's user would be a claim about a person. Deleted on reopen.
	 *
	 * @var string
	 */
	const META_APPROVED = '_wpcpm_report_approved';
	/**
	 * How the report came to exist: `auto` (the daily job) or `manager` (a press).
	 *
	 * Written once, at the first generation, and never by a regeneration: the log answers
	 * "did the site draft this or did somebody" and a regeneration is neither.
	 *
	 * @var string
	 */
	const META_ORIGIN = '_wpcpm_report_origin';
	/** Rows still in progress when the job drafted it, so the notice can say how late the cohort ran. */
	const META_IN_PROGRESS = '_wpcpm_report_in_progress';
	/** The daily job drafted it. */
	const ORIGIN_AUTO = 'auto';
	/** A program manager pressed Draft now, or generated it on the Institution Dashboard. */
	const ORIGIN_MANAGER = 'manager';
	/** Being written. Everything may be edited, generated and regenerated. Invisible to the institution. */
	const STATE_DRAFT = 'draft';
	/**
	 * Approved by a program manager. The institution reads and prints it.
	 *
	 * Replaces `final` (design of 4 September 2026, decision 1): the institution no longer
	 * writes the document, so "final" stopped meaning "the school has stopped writing" and
	 * started meaning "the program has said this may be read". Only a reopen changes it;
	 * consent is still re-read on every render, so a withdrawal still reaches it.
	 */
	const STATE_APPROVED = 'approved';
```

Then, after the `OPT_EPOCH` declaration, add:

```php
	/** The version of the state vocabulary this site's reports are written in. */
	const OPT_STATE_VERSION = 'wpcpm_report_state_version';
	/** Version 2 renamed `final` to `approved`; `maybe_upgrade()` flips the rows once. */
	const STATE_VERSION = 2;
	/**
	 * The day the drafting job was installed, as `Y-m-d`.
	 *
	 * Nothing whose semester window closed before this day is drafted by the job: the first
	 * run on a site with forty institutions and two years of rosters would otherwise draft
	 * eighty reports nobody asked for. A manager who wants an older one presses Draft now.
	 *
	 * @var string
	 */
	const OPT_AUTODRAFT_SINCE = 'wpcpm_report_autodraft_since';
	/** The log of drafts, approvals and reopenings, newest first, capped at LOG_MAX. */
	const OPT_LOG = 'wpcpm_report_log';
	/** Entries the log keeps. */
	const LOG_MAX = 200;
	/** Log events. */
	const LOG_DRAFTED      = 'drafted';
	const LOG_DRAFT_FAILED = 'draft_failed';
	const LOG_APPROVED     = 'approved';
	const LOG_REOPENED     = 'reopened';
```

- [ ] **Step 4: Hook the upgrade, widen `generate()`, and add the primitives**

Replace `init()`:

```php
	public static function init() {
		// Before the post type, on purpose: the flip queries by post type string and needs no
		// registration, and running first means every later hook of this request sees the
		// new vocabulary.
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
	}
```

Replace `set_state()` and add the four readers and two writers directly after it (before `mark_exported()`):

```php
	/**
	 * Move a report between draft and approved.
	 *
	 * @param WP_Post $post  The report.
	 * @param string  $state `draft` or `approved`.
	 * @return bool Whether the state was written.
	 */
	public static function set_state( WP_Post $post, $state ) {
		if ( self::STATE_DRAFT !== $state && self::STATE_APPROVED !== $state ) {
			return false;
		}

		update_post_meta( (int) $post->ID, self::META_STATE, $state );

		return true;
	}

	/**
	 * Approve a report: the state, and who did it when.
	 *
	 * @param WP_Post $post    The report.
	 * @param int     $user_id The manager pressing Approve; 0 for nobody.
	 * @return bool
	 */
	public static function approve( WP_Post $post, $user_id ) {
		if ( ! self::set_state( $post, self::STATE_APPROVED ) ) {
			return false;
		}

		update_post_meta(
			(int) $post->ID,
			self::META_APPROVED,
			array(
				'at' => time(),
				'by' => max( 0, (int) $user_id ),
			)
		);

		return true;
	}

	/**
	 * Make an approved report a draft again. The stamp goes with the state, so a reopened
	 * report never says who approved a version that no longer exists.
	 *
	 * @param WP_Post $post The report.
	 * @return bool
	 */
	public static function reopen( WP_Post $post ) {
		if ( ! self::set_state( $post, self::STATE_DRAFT ) ) {
			return false;
		}

		delete_post_meta( (int) $post->ID, self::META_APPROVED );

		return true;
	}

	/**
	 * When and by whom a report was approved, or an empty array for a draft.
	 *
	 * @param WP_Post $post The report.
	 * @return array `array()` or `array( 'at' => int, 'by' => int )`.
	 */
	public static function approved_at( WP_Post $post ) {
		$stamp = get_post_meta( (int) $post->ID, self::META_APPROVED, true );

		if ( ! is_array( $stamp ) || empty( $stamp['at'] ) ) {
			return array();
		}

		return array(
			'at' => (int) $stamp['at'],
			'by' => isset( $stamp['by'] ) ? max( 0, (int) $stamp['by'] ) : 0,
		);
	}

	/**
	 * How the report came to exist. Anything but `auto` reads as a manager's, including
	 * the empty value every report written before origins existed carries.
	 *
	 * @param WP_Post $post The report.
	 * @return string ORIGIN_AUTO or ORIGIN_MANAGER.
	 */
	public static function origin_of( WP_Post $post ) {
		return self::ORIGIN_AUTO === (string) get_post_meta( (int) $post->ID, self::META_ORIGIN, true )
			? self::ORIGIN_AUTO
			: self::ORIGIN_MANAGER;
	}

	/**
	 * Rows still in progress when the job drafted the report; 0 for a report drafted by hand.
	 *
	 * @param WP_Post $post The report.
	 * @return int
	 */
	public static function in_progress_of( WP_Post $post ) {
		return max( 0, (int) get_post_meta( (int) $post->ID, self::META_IN_PROGRESS, true ) );
	}
```

In `generate()`, change the signature and the refusal:

```php
	public static function generate( $institution, $cohort, $origin = self::ORIGIN_MANAGER ) {
```

and replace the `STATE_FINAL` refusal block with:

```php
		if ( $post instanceof WP_Post && self::STATE_APPROVED === self::state( $post ) ) {
			return new WP_Error(
				'wpcpm_report_approved',
				__( 'This report has been approved. Reopen it before generating it again.', 'wpcredits-program-manager' )
			);
		}
```

and the last line of `generate()` becomes `return self::store( $post, $institution, $cohort, $built, $origin );`. Update the docblock's "refused on a `final` report" sentence to say approved.

In `store()`, change the signature to `private static function store( $post, $institution, $cohort, array $snapshot, $origin = self::ORIGIN_MANAGER )` and, in the create branch, after `update_post_meta( $post_id, self::META_STATE, self::STATE_DRAFT );` add:

```php
		update_post_meta( $post_id, self::META_ORIGIN, self::ORIGIN_AUTO === $origin ? self::ORIGIN_AUTO : self::ORIGIN_MANAGER );
```

(The update branch writes no origin: a regeneration is not how the report came to exist.)

- [ ] **Step 5: Add the upgrade and the log, and extend `delete_all()`**

After `delete_all()`, add:

```php
	/**
	 * Flip every `final` report to `approved`, once, and record the day the job arrived.
	 *
	 * Runs on `init` at priority 5 for sites updated by dropping in files, the way the roles
	 * and settings upgrades do. Idempotent: the version option is what makes it once.
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( self::OPT_STATE_VERSION ) >= self::STATE_VERSION ) {
			return;
		}

		// Every status, for the reason delete_all() gives: a trashed report is still a report,
		// and a `final` one left in the trash would read as a third state nothing knows.
		$found = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future', 'trash', 'auto-draft', 'inherit' ),
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => self::META_STATE,
						'value' => 'final',
					),
				),
			)
		);

		foreach ( $found as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			update_post_meta( (int) $post->ID, self::META_STATE, self::STATE_APPROVED );

			// `by` 0: nobody pressed Approve. `at` is the last edit, the closest fact to "when
			// it was marked final" a version-1 row holds.
			update_post_meta(
				(int) $post->ID,
				self::META_APPROVED,
				array(
					'at' => (int) get_post_modified_time( 'U', true, $post ),
					'by' => 0,
				)
			);
		}

		// `add_option()` and not `update_option()`: the day the job was installed is a fact
		// about this site that a later upgrade must not move.
		add_option( self::OPT_AUTODRAFT_SINCE, wp_date( 'Y-m-d' ), '', false );

		update_option( self::OPT_STATE_VERSION, self::STATE_VERSION );
	}

	/**
	 * Record a draft, an approval, a reopening or a failed draft.
	 *
	 * Two facts a sentence may need travel with the entry and nothing else: no prose from
	 * the report, no address, no name. A reason is cut to 200 characters because it is
	 * Airtable's own message and is only there to say which read failed.
	 *
	 * @param string $event       One of the LOG_* values.
	 * @param string $institution Institutions record ID.
	 * @param string $cohort      Cohort key.
	 * @param int    $actor       The account, or 0 for the job.
	 * @param array  $extra       Optional `in_progress` (int) and `why` (string).
	 */
	public static function log( $event, $institution, $cohort, $actor, array $extra = array() ) {
		$entries = get_option( self::OPT_LOG );
		$entries = is_array( $entries ) ? $entries : array();

		$entry = array(
			'event'       => (string) $event,
			'institution' => trim( (string) $institution ),
			'cohort'      => (string) $cohort,
			'actor'       => max( 0, (int) $actor ),
			'at'          => time(),
		);

		if ( isset( $extra['in_progress'] ) ) {
			$entry['in_progress'] = max( 0, (int) $extra['in_progress'] );
		}

		if ( isset( $extra['why'] ) ) {
			$entry['why'] = mb_substr( sanitize_text_field( (string) $extra['why'] ), 0, 200 );
		}

		array_unshift( $entries, $entry );

		update_option( self::OPT_LOG, array_slice( $entries, 0, self::LOG_MAX ), false );
	}

	/**
	 * The log, newest first.
	 *
	 * @return array[]
	 */
	public static function log_entries() {
		$entries = get_option( self::OPT_LOG );

		return is_array( $entries ) ? array_values( $entries ) : array();
	}
```

In `delete_all()`, after `delete_option( self::OPT_EPOCH );` add:

```php
		delete_option( self::OPT_LOG );
		delete_option( self::OPT_STATE_VERSION );
		delete_option( self::OPT_AUTODRAFT_SINCE );
```

- [ ] **Step 6: Fix every remaining `STATE_FINAL` and `'final'` reference in the two classes**

Run: `grep -n "STATE_FINAL\|'final'" includes/modules/class-wpcpm-semester-report.php includes/modules/class-wpcpm-semester-report-screen.php includes/modules/class-wpcpm-institutions.php`

In `class-wpcpm-semester-report.php` there should be none left except the string `'final'` inside `maybe_upgrade()`. In the screen class and the module the references are rewritten in Tasks 4, 5 and 6; for now, so the suite loads, change the screen's `ACTION_FINAL` handler check `'final' === $state` (in `render_editor()`, `render_actions()`, `handle_restore()`, `state_label()`) to compare against `WPCPM_Semester_Report::STATE_APPROVED`, and in `class-wpcpm-institutions.php` line 3955 area replace `WPCPM_Semester_Report::STATE_FINAL === ...` with `WPCPM_Semester_Report::STATE_APPROVED === ...` and the printed word `'Final'` with `'Approved'`.

- [ ] **Step 7: Run the suite and the reference check**

Run: `php bin/test-semester-report.php 2>&1 | grep -E "FAIL|PASS|checks" | head -20 && php bin/check-references.php | tail -3`
Expected: `ALL PASS (N checks)` with N at least 254, and the reference check reporting every constant resolves.

---

### Task 2: `due()`, `queue()` and `approved_since()`

**Files:**
- Modify: `includes/modules/class-wpcpm-semester-report.php` (add after `log_entries()`)
- Modify: `bin/test-semester-report.php` (stubs for `WPCPM_Settings::get()` at line 510 and `WPCPM_Institutions_Index::rows()` at line 698; append tests)

**Interfaces:**
- Consumes: `WPCPM_Institutions_Index::rows()` (array of rows with `record_id`, `stage`), `WPCPM_Roster_Index::rows( $record )` (rows with `status`, `start`, `end`), `WPCPM_Cohort::key()`, `range()`, `NOT_SIGNED_UP`, `NONE`, `WPCPM_Settings::get_value( $key, $fallback )`, `find()`.
- Produces: `due( $today ): array` of `array( 'institution', 'cohort', 'in_progress', 'window_end' )` oldest window first; `queue(): array` and `approved_since( $timestamp ): array` of `array( 'post_id', 'institution', 'cohort', 'generated', 'origin', 'in_progress', 'age_days', 'approved_at', 'approved_by' )` oldest generated first.

- [ ] **Step 1: Extend two stubs in the suite**

Replace the `WPCPM_Settings` stub's `get()`:

```php
	public static function get() {
		return array_merge(
			array(
				'students_table'            => 'tblStudents',
				'reports_table'             => 'tblReports',
				'feedback_table'            => 'tblFeedback',
				'institution_active_stages' => array( 'Confirmed', 'Student' ),
				'past_statuses'             => array( 'Graduate', 'Dropped out' ),
			),
			isset( $GLOBALS['settings_extra'] ) && is_array( $GLOBALS['settings_extra'] ) ? $GLOBALS['settings_extra'] : array()
		);
	}
```

Replace the `WPCPM_Institutions_Index` stub's `rows()`:

```php
	public static function rows() { return isset( $GLOBALS['inst_rows'] ) && is_array( $GLOBALS['inst_rows'] ) ? $GLOBALS['inst_rows'] : array(); }
```

- [ ] **Step 2: Write the failing tests**

Append before `echo "\n=== House rules ===\n";`:

```php
echo "\n=== When a cohort is due ===\n";

// The five conditions of design section 5.1, each flipped on its own.
$GLOBALS['manage'] = true;
$GLOBALS['acting'] = '';
$today             = '2026-09-04';
// The fixture's own rosters come back at the end of the job block: the approval block below
// needs an institution whose snapshot names released students, which only the fixture has.
$saved_index       = $GLOBALS['index'];
$finished          = array(
	array( 'record_id' => 'recS0000000000001', 'email' => 'a@example.test', 'status' => 'Graduate', 'start' => '2026-02-10', 'end' => '2026-06-20' ),
	array( 'record_id' => 'recS0000000000002', 'email' => 'b@example.test', 'status' => 'In Sensei', 'start' => '2026-03-01', 'end' => '2026-06-30' ),
);
$GLOBALS['inst_rows'] = array(
	$A => array( 'record_id' => $A, 'name' => 'Uniwersytet Łódzki', 'stage' => 'Confirmed' ),
	$B => array( 'record_id' => $B, 'name' => 'Universidad Beta', 'stage' => 'Confirmed' ),
	$C => array( 'record_id' => $C, 'name' => 'Instituto Chunk', 'stage' => 'Interested' ),
);
$GLOBALS['index'] = array(
	$A => array( 'read' => 1756000000, 'rows' => $finished ),
	$B => array( 'read' => 1756000000, 'rows' => $finished ),
	$C => array( 'read' => 1756000000, 'rows' => $finished ),
);
WPCPM_Semester_Report::delete_all();
$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] = '2026-05-01';

$due = WPCPM_Semester_Report::due( $today );
ck( 'two active institutions with a finished January-to-June cohort are due', array_map( static function ( $d ) { return $d['institution'] . ' ' . $d['cohort']; }, $due ), array( $A . ' 2026-H1', $B . ' 2026-H1' ) );
ck( 'with nobody in progress and the window end named', array( $due[0]['in_progress'], $due[0]['window_end'] ), array( 0, '2026-06-30' ) );
ck( 'an institution outside the active stages is not due', in_array( $C, array_column( $due, 'institution' ), true ), false );

$GLOBALS['index'][ $A ]['rows'] = array();
ck( 'an empty roster is not due', in_array( $A, array_column( WPCPM_Semester_Report::due( $today ), 'institution' ), true ), false );
$GLOBALS['index'][ $A ]['rows'] = array( array( 'record_id' => 'recS0000000000003', 'email' => 'c@example.test', 'status' => 'In Sensei', 'start' => '', 'end' => '' ) );
ck( 'rows with no start date are not a cohort', in_array( $A, array_column( WPCPM_Semester_Report::due( $today ), 'institution' ), true ), false );
$GLOBALS['index'][ $A ]['rows'] = array( array( 'record_id' => 'recS0000000000004', 'email' => 'd@example.test', 'status' => 'Graduate', 'start' => '2026-08-01', 'end' => '2026-09-01' ) );
ck( 'a window that has not closed is not due, however finished its rows', in_array( $A, array_column( WPCPM_Semester_Report::due( $today ), 'institution' ), true ), false );
$GLOBALS['index'][ $A ]['rows'] = $finished;

$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] = '2026-07-15';
ck( 'a window that closed before the since-date is history, not a draft', WPCPM_Semester_Report::due( $today ), array() );
$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] = '2026-05-01';

WPCPM_Semester_Report::generate( $B, '2026-H1' );
ck( 'a cohort with a report is not due again', array_column( WPCPM_Semester_Report::due( $today ), 'institution' ), array( $A ) );

$late = $finished;
$late[] = array( 'record_id' => 'recS0000000000005', 'email' => 'e@example.test', 'status' => 'In Sensei', 'start' => '2026-03-15', 'end' => '' );
$GLOBALS['index'][ $A ]['rows'] = $late;
ck( 'a row still in progress holds the cohort back inside the grace', WPCPM_Semester_Report::due( '2026-07-20' ), array() );
$grace_due = WPCPM_Semester_Report::due( '2026-08-20' );
ck( 'and the cohort is drafted anyway once the grace has run out', array_column( $grace_due, 'institution' ), array( $A ) );
ck( 'saying how many rows were still in progress', $grace_due[0]['in_progress'], 1 );
$GLOBALS['settings_extra'] = array( 'report_autodraft_grace_days' => 10 );
ck( 'the grace is the setting', array_column( WPCPM_Semester_Report::due( '2026-07-20' ), 'institution' ), array( $A ) );
$GLOBALS['settings_extra'] = array();
$GLOBALS['index'][ $A ]['rows'] = $finished;

$spam = $finished;
$spam[] = array( 'record_id' => 'recS0000000000006', 'email' => 'f@example.test', 'status' => 'SPAM', 'start' => '2026-03-15', 'end' => '' );
$GLOBALS['index'][ $A ]['rows'] = $spam;
ck( 'a SPAM row is nobody in progress', WPCPM_Semester_Report::due( '2026-07-20' )[0]['in_progress'], 0 );
$GLOBALS['index'][ $A ]['rows'] = $finished;

ck( 'a malformed today is nothing due', WPCPM_Semester_Report::due( 'yesterday' ), array() );

echo "\n=== The queue and the approved list ===\n";

$q_draft = WPCPM_Semester_Report::generate( $A, '2026-H1', WPCPM_Semester_Report::ORIGIN_AUTO );
update_post_meta( $q_draft, WPCPM_Semester_Report::META_IN_PROGRESS, 1 );
$queue = WPCPM_Semester_Report::queue();
ck( 'the queue lists the drafts, oldest first', array_column( $queue, 'post_id' ), array( WPCPM_Semester_Report::find( $B, '2026-H1' )->ID, $q_draft ) );
ck( 'with institution, cohort, origin and the in-progress count', array( $queue[1]['institution'], $queue[1]['cohort'], $queue[1]['origin'], $queue[1]['in_progress'] ), array( $A, '2026-H1', 'auto', 1 ) );
ck( 'and no approval stamp on a draft', array( $queue[1]['approved_at'], $queue[1]['approved_by'] ), array( 0, 0 ) );
ck( 'nothing approved yet', WPCPM_Semester_Report::approved_since( 0 ), array() );
WPCPM_Semester_Report::approve( get_post( $q_draft ), 3 );
ck( 'the queue shrinks', array_column( WPCPM_Semester_Report::queue(), 'post_id' ), array( WPCPM_Semester_Report::find( $B, '2026-H1' )->ID ) );
$approved = WPCPM_Semester_Report::approved_since( time() - 60 );
ck( 'and the approved list has it, with the stamp', array( count( $approved ), $approved[0]['approved_by'] ), array( 1, 3 ) );
ck( 'a later cut-off leaves it out', WPCPM_Semester_Report::approved_since( time() + 60 ), array() );
$walk = wp_json_encode( array_merge( $queue, $approved, $due ) );
ck( 'none of the three carries an address', preg_match( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,}/', $walk ), 0 );
```

- [ ] **Step 3: Run the suite to verify it fails**

Run: `php bin/test-semester-report.php 2>&1 | grep -E "FAIL|Fatal" | head -5`
Expected: a fatal on `WPCPM_Semester_Report::due` (undefined method).

- [ ] **Step 4: Implement the three readers**

Add after `log_entries()`:

```php
	/*
	 * --------------------------------------------------------------------
	 * What the job and the Administrator Dashboard read
	 * --------------------------------------------------------------------
	 */

	/**
	 * The institution cohorts a draft is owed for (design section 5.1).
	 *
	 * Due when all five hold: the institution is in an active stage and has roster rows in
	 * the cohort; the cohort is a semester (not NONE) whose window closed before today; the
	 * window closed on or after the since-date; no report exists for the pair; and either
	 * every row is finished or the window closed at least the grace ago. One function, read
	 * by the cron and by the screens alike, so they cannot disagree about what "due" means.
	 *
	 * Dates are compared as `Y-m-d` strings, the cohort class's rule: a day boundary is a
	 * calendar fact, and a timestamp would pin it to one timezone's midnight.
	 *
	 * @param string $today Today as `Y-m-d`.
	 * @return array[] `institution`, `cohort`, `in_progress`, `window_end`; oldest window first.
	 */
	public static function due( $today ) {
		$today = (string) $today;

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $today ) ) {
			return array();
		}

		$active = (array) WPCPM_Settings::get_value( 'institution_active_stages', array() );
		$past   = (array) WPCPM_Settings::get_value( 'past_statuses', array() );
		$grace  = max( 0, (int) WPCPM_Settings::get_value( 'report_autodraft_grace_days', 45 ) );
		$since  = (string) get_option( self::OPT_AUTODRAFT_SINCE, '' );
		$due    = array();

		// An empty stage list is nobody active, not everybody: the sync reads the same list
		// as the definition of the pipeline, and a report drafted for an institution the
		// pipeline does not count is a report about nobody's partner.
		if ( empty( $active ) ) {
			return array();
		}

		foreach ( WPCPM_Institutions_Index::rows() as $institution ) {
			if ( ! is_array( $institution ) || empty( $institution['record_id'] ) ) {
				continue;
			}

			$record = (string) $institution['record_id'];
			$stage  = isset( $institution['stage'] ) ? trim( (string) $institution['stage'] ) : '';

			if ( ! in_array( $stage, $active, true ) ) {
				continue;
			}

			$by_cohort = array();

			foreach ( WPCPM_Roster_Index::rows( $record ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$status = isset( $row['status'] ) ? trim( (string) $row['status'] ) : '';

				if ( in_array( $status, WPCPM_Cohort::NOT_SIGNED_UP, true ) ) {
					continue;
				}

				$key = WPCPM_Cohort::key( isset( $row['start'] ) ? $row['start'] : '' );

				if ( WPCPM_Cohort::NONE === $key ) {
					continue;
				}

				if ( ! isset( $by_cohort[ $key ] ) ) {
					$by_cohort[ $key ] = 0;
				}

				$end = isset( $row['end'] ) ? trim( (string) $row['end'] ) : '';

				if ( ! in_array( $status, $past, true ) && ( '' === $end || $end > $today ) ) {
					++$by_cohort[ $key ];
				}
			}

			foreach ( $by_cohort as $key => $in_progress ) {
				$range = WPCPM_Cohort::range( $key );
				$end   = $range['to'];

				if ( '' === $end || $end >= $today ) {
					continue;
				}

				if ( '' !== $since && $end < $since ) {
					continue;
				}

				if ( self::find( $record, $key ) instanceof WP_Post ) {
					continue;
				}

				$days = (int) floor( ( strtotime( $today . ' 00:00:00 UTC' ) - strtotime( $end . ' 00:00:00 UTC' ) ) / DAY_IN_SECONDS );

				if ( $in_progress > 0 && $days < $grace ) {
					continue;
				}

				$due[] = array(
					'institution' => $record,
					'cohort'      => (string) $key,
					'in_progress' => (int) $in_progress,
					'window_end'  => $end,
				);
			}
		}

		usort(
			$due,
			static function ( $a, $b ) {
				$order = strcmp( $a['window_end'], $b['window_end'] );

				return 0 !== $order ? $order : strcmp( $a['institution'], $b['institution'] );
			}
		);

		return $due;
	}

	/**
	 * Every draft, oldest generated first: what the Administrator Dashboard lists to review.
	 *
	 * @return array[]
	 */
	public static function queue() {
		return self::rows_in_state( self::STATE_DRAFT, 0 );
	}

	/**
	 * Every report approved at or after a moment, oldest generated first.
	 *
	 * @param int $timestamp Unix time; 0 for every approved report.
	 * @return array[]
	 */
	public static function approved_since( $timestamp ) {
		return self::rows_in_state( self::STATE_APPROVED, (int) $timestamp );
	}

	/**
	 * Plain rows for one state. No prose, no snapshot: the readers of this list draw a table.
	 *
	 * @param string $state          STATE_DRAFT or STATE_APPROVED.
	 * @param int    $approved_after Keep only reports approved at or after this; 0 keeps all.
	 * @return array[]
	 */
	private static function rows_in_state( $state, $approved_after ) {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => self::META_STATE,
						'value' => (string) $state,
					),
				),
			)
		);

		$rows = array();

		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$approved = self::approved_at( $post );

			if ( $approved_after > 0 && ( empty( $approved['at'] ) || $approved['at'] < $approved_after ) ) {
				continue;
			}

			$generated = self::generated_at( $post );

			$rows[] = array(
				'post_id'     => (int) $post->ID,
				'institution' => self::institution_of( $post ),
				'cohort'      => self::cohort_of( $post ),
				'generated'   => $generated,
				'origin'      => self::origin_of( $post ),
				'in_progress' => self::in_progress_of( $post ),
				'age_days'    => $generated > 0 ? (int) floor( ( time() - $generated ) / DAY_IN_SECONDS ) : 0,
				'approved_at' => isset( $approved['at'] ) ? (int) $approved['at'] : 0,
				'approved_by' => isset( $approved['by'] ) ? (int) $approved['by'] : 0,
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return $a['generated'] <=> $b['generated'];
			}
		);

		return $rows;
	}
```

- [ ] **Step 5: Run the suite**

Run: `php bin/test-semester-report.php 2>&1 | grep -E "FAIL|PASS|checks"`
Expected: `ALL PASS`.

---

### Task 3: The policy's edit ground

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-policy.php:86-100` (`grounds()`)
- Test: `bin/test-institution-policy.php:256-267` (`$expected_map`)

**Interfaces:**
- Produces: `WPCPM_Institution_Policy::grounds()[ ACT_EDIT_SEMESTER_REPORT ] === array( 'manager' )`.

- [ ] **Step 1: Change the expected map in the suite**

In `bin/test-institution-policy.php`, change the row

```php
	'edit_semester_report' => array( 'manager', 'member' ),
```

to

```php
	// Design of 4 September 2026, decision 1: the institution reads the report and never
	// writes it. The member ground stays on viewing, behind the agreement gate as before.
	'edit_semester_report' => array( 'manager' ),
```

- [ ] **Step 2: Run the suite to verify it fails**

Run: `php bin/test-institution-policy.php 2>&1 | grep -E "FAIL" | head -3`
Expected: `FAIL grounds() is exactly the expected map, in order`.

- [ ] **Step 3: Change the map**

In `grounds()`, change

```php
			self::ACT_EDIT_SEMESTER_REPORT => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
```

to

```php
			// Managers only since the approval design of 4 September 2026: the site drafts the
			// report and the program approves it, so a member's account has nothing to write.
			// Viewing keeps the member ground, narrowed to approved reports by the screen, which
			// is a fact about a document rather than about a person.
			self::ACT_EDIT_SEMESTER_REPORT => array( self::GROUND_MANAGER ),
```

- [ ] **Step 4: Run the policy suite and the report suite**

Run: `php bin/test-institution-policy.php 2>&1 | grep -E "FAIL|PASS" | head -3; php bin/test-semester-report.php 2>&1 | grep -E "FAIL|PASS|checks"`
Expected: both `ALL PASS` (the report suite's policy is a stub, so it is unaffected; it is run to be sure nothing else moved).

---

### Task 4: Draft now, the daily job, Approve and Reopen, the two mails

**Files:**
- Modify: `includes/modules/class-wpcpm-semester-report-screen.php` (constants lines 39-105; `init()` line 115; `handle_final()` / `handle_reopen()` / `change_state()` lines 1715-1770; `handle_restore()` line 1771; `messages()` line 2943; `summary_sentence()` line 2874)
- Test: `bin/test-semester-report.php` (stubs: add `WPCPM_Ceiling` and `WPCPM_Institutions` after the `WPCPM_Mail` stub; change `WPCPM_Institution_Members::members_of()`; the `$actions` map at line 1593; append tests)

**Interfaces:**
- Consumes: Task 1 and Task 2 methods; `WPCPM_Ceiling::claim( $key, $limit, $window )` (true when the claim fits); `WPCPM_Institutions::notify_managers( $context, $build, $setting_key )` (Task 6 adds the third argument; this task guards the call with `class_exists`); `WPCPM_Institution_Members::members_of( $record )` returning `WP_User[]`; `WPCPM_Mail::send( $user, $context, $build )`.
- Produces: `const ACTION_APPROVE = 'wpcpm_report_approve'` (replacing `ACTION_FINAL`), `const ACTION_DRAFT = 'wpcpm_report_draft'`, `const CRON_AUTODRAFT = 'wpcpm_report_autodraft'`, `const AUTODRAFT_PER_RUN = 10`, `const DRAFTS_PER_DAY = 20`, `const MAIL_DRAFTED = 'report-drafted'`, `const MAIL_APPROVED = 'report-approved'`; `handle_draft()`, `handle_approve()`, `handle_reopen()`, `autodraft_tick(): int`; flash statuses `drafted`, `draft-exists`, `draft-refused`, `approved` (detail `notified`), `approve-failed` (detail `why`), `is-approved`, `reopened`.

- [ ] **Step 1: Add and change stubs in the suite**

After the `WPCPM_Mail` stub class, add:

```php
class WPCPM_Ceiling {
	public static function claim( $key, $limit, $window, $amount = 1 ) {
		$GLOBALS['ceiling'][ $key ] = ( isset( $GLOBALS['ceiling'][ $key ] ) ? $GLOBALS['ceiling'][ $key ] : 0 ) + max( 1, (int) $amount );
		return $GLOBALS['ceiling'][ $key ] <= (int) $limit;
	}
}

/**
 * The module, reduced to the one thing the job asks of it: telling the managers. What is
 * recorded is the context and which setting named the recipients, which is the whole of the
 * contract the report has with it.
 */
class WPCPM_Institutions {
	public static function notify_managers( $context, $build, $setting_key = 'agreement_notify' ) {
		$message = is_callable( $build ) ? call_user_func( $build, new WP_User( 99, 'Manager', 'maciej@a8c.com' ) ) : array();
		$GLOBALS['mail'][] = array( 'to' => 'managers:' . $setting_key, 'context' => (string) $context, 'subject' => isset( $message['subject'] ) ? $message['subject'] : '', 'body' => isset( $message['body'] ) ? $message['body'] : '' );
		return 1;
	}
}
```

In the `WPCPM_Institution_Members` stub, replace `members_of()`:

```php
	public static function members_of( $record_id ) {
		return isset( $GLOBALS['members'][ (string) $record_id ] ) ? $GLOBALS['members'][ (string) $record_id ] : array();
	}
```

In the `$actions` map near line 1593, replace the `'final'` entry with `'approve' => WPCPM_Semester_Report_Screen::ACTION_APPROVE,` and add `'draft' => WPCPM_Semester_Report_Screen::ACTION_DRAFT,`. In the loop near line 2343 replace `'final'   => WPCPM_Semester_Report_Screen::ACTION_FINAL,` with `'approve' => WPCPM_Semester_Report_Screen::ACTION_APPROVE,`.

- [ ] **Step 2: Write the failing tests**

Append before `echo "\n=== House rules ===\n";`:

```php
echo "\n=== Draft now ===\n";

$GLOBALS['manage']  = true;
$GLOBALS['acting']  = '';
$GLOBALS['uid']     = 3;
$GLOBALS['ceiling'] = array();
$GLOBALS['flash']   = array();
$GLOBALS['mail']    = array();
$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_LOG ] = array();
WPCPM_Semester_Report::delete_all();
$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] = '2026-05-01';
$GLOBALS['index'][ $B ]['rows'] = $finished;

ck( 'drafting writes the report and says so', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_DRAFT, array( 'institution' => $B, 'cohort' => '2026-H1' ) ), 'drafted' );
$b_post = WPCPM_Semester_Report::find( $B, '2026-H1' );
ck( 'as a manager\'s draft', $b_post instanceof WP_Post ? WPCPM_Semester_Report::origin_of( $b_post ) : 'no post', 'manager' );
ck( 'the manager lands on it, as that institution', has( $GLOBALS['redirect'], 'wpcpm_report=2026-H1' ) && has( $GLOBALS['redirect'], WPCPM_Institution_Roster::ARG_VIEW . '=' . $B ), true );
ck( 'and it is logged to the account that pressed', array( WPCPM_Semester_Report::log_entries()[0]['event'], WPCPM_Semester_Report::log_entries()[0]['actor'] ), array( 'drafted', 3 ) );
ck( 'nobody is mailed for a press', $GLOBALS['mail'], array() );
ck( 'a second press finds the report', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_DRAFT, array( 'institution' => $B, 'cohort' => '2026-H1' ) ), 'draft-exists' );
ck( 'no start date is not a semester', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_DRAFT, array( 'institution' => $B, 'cohort' => 'none' ) ), 'bad-cohort' );
ck( 'a malformed record is refused', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_DRAFT, array( 'institution' => 'not-a-record', 'cohort' => '2025-H2' ) ), 'refused' );

$GLOBALS['ceiling'] = array( 'report-draft:3' => WPCPM_Semester_Report_Screen::DRAFTS_PER_DAY );
ck( 'the twenty-first press in a day is refused', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_DRAFT, array( 'institution' => $A, 'cohort' => '2026-H1' ) ), 'draft-refused' );
ck( 'and writes nothing', WPCPM_Semester_Report::find( $A, '2026-H1' ), null );
$GLOBALS['ceiling'] = array();

$GLOBALS['manage'] = false;
$GLOBALS['acting'] = $A;
$GLOBALS['decisions'] = array();
ck( 'a member is refused before the policy is asked', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_DRAFT, array( 'institution' => $A, 'cohort' => '2026-H1' ) ), 'refused' );
ck( 'the capability is the first gate, so the policy never saw the press', $GLOBALS['decisions'], array() );
$GLOBALS['manage'] = true;
$GLOBALS['acting'] = '';

echo "\n=== The daily job ===\n";

WPCPM_Semester_Report::delete_all();
$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] = '2026-05-01';
$GLOBALS['mail']      = array();
$GLOBALS['inst_rows'] = array();
$GLOBALS['index']     = array();
for ( $i = 1; $i <= 12; $i++ ) {
	$rec = sprintf( 'recJOB%011d', $i );
	$GLOBALS['inst_rows'][ $rec ] = array( 'record_id' => $rec, 'name' => 'Job University ' . $i, 'stage' => 'Confirmed' );
	$GLOBALS['index'][ $rec ]     = array( 'read' => 1756000000, 'rows' => $finished );
}
$GLOBALS['transients'] = array();

// The first pair is being generated by somebody this minute.
$first_pair = WPCPM_Semester_Report::due( gmdate( 'Y-m-d' ) )[0];
$GLOBALS['opts'][ 'wpcpm_report_gen_' . md5( $first_pair['institution'] . '|' . $first_pair['cohort'] ) ] = time();

ck( 'one run drafts the cap, skipping the locked pair', WPCPM_Semester_Report_Screen::autodraft_tick(), WPCPM_Semester_Report_Screen::AUTODRAFT_PER_RUN );
ck( 'and mails the managers once per draft, through the report setting', count( array_filter( $GLOBALS['mail'], static function ( $m ) { return 'report-drafted' === $m['context'] && 'managers:report_notify' === $m['to']; } ) ), WPCPM_Semester_Report_Screen::AUTODRAFT_PER_RUN );
ck( 'the mail names the institution, the semester and the review link', has( $GLOBALS['mail'][0]['body'], 'Job University' ) && has( $GLOBALS['mail'][0]['body'], 'January to June 2026' ) && has( $GLOBALS['mail'][0]['body'], 'wpcpm_report=2026-H1' ), true );
ck( 'each draft is the job\'s', WPCPM_Semester_Report::queue()[0]['origin'], 'auto' );
ck( 'and logged to actor 0', WPCPM_Semester_Report::log_entries()[0]['actor'], 0 );
unset( $GLOBALS['opts'][ 'wpcpm_report_gen_' . md5( $first_pair['institution'] . '|' . $first_pair['cohort'] ) ] );
ck( 'the next run drafts the rest', WPCPM_Semester_Report_Screen::autodraft_tick(), 2 );
ck( 'and then there is nothing due', WPCPM_Semester_Report_Screen::autodraft_tick(), 0 );

WPCPM_Semester_Report::delete_all();
$GLOBALS['opts'][ WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE ] = '2026-05-01';
$GLOBALS['fail_table'] = 'tblReports';
$GLOBALS['transients'] = array();
$GLOBALS['mail']       = array();
ck( 'a read that fails drafts nothing and stops nothing', WPCPM_Semester_Report_Screen::autodraft_tick(), 0 );
ck( 'each failure is a log line with the reason', array( WPCPM_Semester_Report::log_entries()[0]['event'], has( WPCPM_Semester_Report::log_entries()[0]['why'], 'tblReports' ) || '' !== WPCPM_Semester_Report::log_entries()[0]['why'] ), array( 'draft_failed', true ) );
ck( 'and no mail', $GLOBALS['mail'], array() );
$GLOBALS['fail_table'] = '';

$GLOBALS['settings_extra'] = array( 'report_autodraft' => false );
ck( 'switched off, the job does nothing', WPCPM_Semester_Report_Screen::autodraft_tick(), 0 );
$GLOBALS['settings_extra'] = array();
$GLOBALS['inst_rows']      = array( $A => array( 'record_id' => $A, 'name' => 'Uniwersytet Łódzki', 'stage' => 'Confirmed' ), $B => array( 'record_id' => $B, 'name' => 'Universidad Beta', 'stage' => 'Confirmed' ) );
$GLOBALS['index']          = $saved_index;

echo "\n=== Approve and reopen ===\n";

WPCPM_Semester_Report::delete_all();
$GLOBALS['transients'] = array();
$GLOBALS['mail']       = array();
$GLOBALS['members']    = array( $E => array( new WP_User( 21, 'Rep One', 'maciej@a8c.com' ), new WP_User( 22, 'Rep Two', 'maciej@a8c.com' ) ) );
// E's fixture snapshot names released students, so approval has to spend a consent read
// and a Feedback read that fails is a refusal; an institution with nobody released would
// pass without reading, which is the early return consent_check() makes on purpose.
$ap_post = WPCPM_Semester_Report::generate( $E, $COHORT );

$GLOBALS['fail_table'] = 'tblFeedback';
$GLOBALS['transients'] = array();
ck( 'approval is refused while the students\' answers cannot be read', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_APPROVE, array( 'report' => $ap_post ) ), 'approve-failed' );
ck( 'and the report stays a draft', WPCPM_Semester_Report::state( get_post( $ap_post ) ), 'draft' );
ck( 'with nobody told', $GLOBALS['mail'], array() );
$GLOBALS['fail_table'] = '';
$GLOBALS['transients'] = array();

ck( 'approval approves', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_APPROVE, array( 'report' => $ap_post ) ), 'approved' );
ck( 'stamped with the manager', WPCPM_Semester_Report::approved_at( get_post( $ap_post ) )['by'], 3 );
ck( 'the institution\'s two accounts are told', array_map( static function ( $m ) { return $m['to'] . ':' . $m['context']; }, $GLOBALS['mail'] ), array( '21:report-approved', '22:report-approved' ) );
ck( 'and the flash carries the count', $GLOBALS['flash'][ WPCPM_Semester_Report_Screen::FLASH ]['detail']['notified'], 2 );
ck( 'logged', WPCPM_Semester_Report::log_entries()[0]['event'], 'approved' );
ck( 'approving again is a refusal that names the state', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_APPROVE, array( 'report' => $ap_post ) ), 'is-approved' );

$GLOBALS['mail']    = array();
$GLOBALS['members'] = array();
ck( 'reopen reopens', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_REOPEN, array( 'report' => $ap_post ) ), 'reopened' );
ck( 'the stamp is gone', WPCPM_Semester_Report::approved_at( get_post( $ap_post ) ), array() );
ck( 'and nobody is mailed for a reopen', $GLOBALS['mail'], array() );
ck( 'approving with no institution account says so in the count', flash_status_after( WPCPM_Semester_Report_Screen::ACTION_APPROVE, array( 'report' => $ap_post ) ) . ':' . $GLOBALS['flash'][ WPCPM_Semester_Report_Screen::FLASH ]['detail']['notified'], 'approved:0' );
$ap_screen = screen_html( $E, $COHORT );
ck( 'and the page tells the manager to send the PDF by hand', has( $ap_screen, 'by hand' ), true );
```

- [ ] **Step 3: Run the suite to verify it fails**

Run: `php bin/test-semester-report.php 2>&1 | grep -E "FAIL|Fatal" | head -5`
Expected: a fatal on `WPCPM_Semester_Report_Screen::ACTION_APPROVE` (undefined class constant).

- [ ] **Step 4: Replace the constants and the hooks**

In the constants block, replace the `ACTION_FINAL` declaration with:

```php
	/** The `admin_post_` action that approves a draft, which is when the institution first sees it. */
	const ACTION_APPROVE = 'wpcpm_report_approve';
	/** The `admin_post_` action a program manager presses to draft one institution's cohort now. */
	const ACTION_DRAFT = 'wpcpm_report_draft';
	/** The daily job that drafts every cohort that is due. Scheduled by `WPCPM_Institutions::schedule_cron()`. */
	const CRON_AUTODRAFT = 'wpcpm_report_autodraft';
	/**
	 * Drafts one run of the job writes; the rest wait for tomorrow.
	 *
	 * Each draft is a full set of Airtable reads, and a day on which twelve cohorts end at
	 * once is a day the base should not be read twelve times in one request.
	 *
	 * @var int
	 */
	const AUTODRAFT_PER_RUN = 10;
	/** Draft now presses one account may make in a day, for the same reason. */
	const DRAFTS_PER_DAY = 20;
```

Replace the `MAIL_CONTEXT` declaration with:

```php
	/** The mail log's labels: the consent request, a draft landing, an approval. */
	const MAIL_CONTEXT  = 'report-consent';
	const MAIL_DRAFTED  = 'report-drafted';
	const MAIL_APPROVED = 'report-approved';
```

In `init()`, replace the `ACTION_FINAL` line with these two and add the cron hook at the end:

```php
		add_action( 'admin_post_' . self::ACTION_APPROVE, array( __CLASS__, 'handle_approve' ) );
		add_action( 'admin_post_' . self::ACTION_DRAFT, array( __CLASS__, 'handle_draft' ) );
```

```php
		add_action( self::CRON_AUTODRAFT, array( __CLASS__, 'autodraft_tick' ) );
```

- [ ] **Step 5: Replace `handle_final()`, `handle_reopen()` and `change_state()` with the three handlers, the job and the two builders**

Delete `handle_final()`, `handle_reopen()` and `change_state()` (lines 1712-1769) and put this in their place:

```php
	/**
	 * Draft one institution's cohort now, from the manager screens.
	 *
	 * The capability first, before anything posted is read: whether this account may press
	 * the button must not depend on what it posted. Then the policy on the institution the
	 * form names, then the nonce, then the shape of the cohort. A member reaching this by
	 * hand gets the one refusal and the policy is never asked.
	 */
	public static function handle_draft() {
		if ( ! class_exists( 'WPCPM_Semester_Report' ) ) {
			self::bounce( 'unavailable' );
		}

		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			self::bounce( 'refused' );
		}

		$institution = WPCPM_Request::posted_text( 'institution' );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
			self::bounce( 'refused' );
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_EDIT_SEMESTER_REPORT,
			WPCPM_Institution_Policy::subject_institution( $institution )
		);

		if ( empty( $decision['allowed'] ) ) {
			self::bounce( 'refused' );
		}

		check_admin_referer( self::ACTION_DRAFT );

		$cohort = WPCPM_Request::posted_text( 'cohort' );

		if ( ! WPCPM_Cohort::is_key( $cohort ) || WPCPM_Cohort::NONE === $cohort ) {
			self::bounce( 'bad-cohort', array(), '', $institution );
		}

		// The existing report opens: the redirect names the cohort, and the message says why.
		if ( WPCPM_Semester_Report::find( $institution, $cohort ) instanceof WP_Post ) {
			self::bounce( 'draft-exists', array(), $cohort, $institution );
		}

		if ( ! WPCPM_Ceiling::claim( 'report-draft:' . get_current_user_id(), self::DRAFTS_PER_DAY, DAY_IN_SECONDS ) ) {
			self::bounce( 'draft-refused', array(), '', $institution );
		}

		if ( ! self::lock( $institution, $cohort ) ) {
			self::bounce( 'locked', array(), $cohort, $institution );
		}

		$generated = WPCPM_Semester_Report::generate( $institution, $cohort, WPCPM_Semester_Report::ORIGIN_MANAGER );

		self::unlock( $institution, $cohort );

		if ( is_wp_error( $generated ) ) {
			WPCPM_Semester_Report::log( WPCPM_Semester_Report::LOG_DRAFT_FAILED, $institution, $cohort, get_current_user_id(), array( 'why' => $generated->get_error_message() ) );
			self::bounce( 'generate-failed', array( 'why' => self::why_for_viewer( $generated ) ), '', $institution );
		}

		// No mail: the manager who pressed the button is the audience of that mail.
		WPCPM_Semester_Report::log( WPCPM_Semester_Report::LOG_DRAFTED, $institution, $cohort, get_current_user_id() );

		self::leave( 'drafted', array(), $cohort, $institution );
	}

	/**
	 * The daily job: draft every cohort that is due, up to the cap, and tell the managers.
	 *
	 * One failure is one log line and the next pair still runs; a pair somebody is generating
	 * by hand this minute is left to them and is due again tomorrow if they gave up. Nothing
	 * here is mailed to an institution: a draft is invisible to it until approval.
	 *
	 * @return int How many drafts were written.
	 */
	public static function autodraft_tick() {
		if ( ! class_exists( 'WPCPM_Semester_Report' ) || ! WPCPM_Settings::get_value( 'report_autodraft', true ) ) {
			return 0;
		}

		$drafted   = 0;
		$attempted = 0;

		foreach ( WPCPM_Semester_Report::due( wp_date( 'Y-m-d' ) ) as $pair ) {
			// The cap counts attempts, because an attempt is what reads the base; a pair
			// somebody else holds the lock on costs nothing and takes no slot.
			if ( $attempted >= self::AUTODRAFT_PER_RUN ) {
				break;
			}

			$institution = (string) $pair['institution'];
			$cohort      = (string) $pair['cohort'];

			if ( ! self::lock( $institution, $cohort ) ) {
				continue;
			}

			++$attempted;

			$generated = WPCPM_Semester_Report::generate( $institution, $cohort, WPCPM_Semester_Report::ORIGIN_AUTO );

			self::unlock( $institution, $cohort );

			if ( is_wp_error( $generated ) ) {
				WPCPM_Semester_Report::log( WPCPM_Semester_Report::LOG_DRAFT_FAILED, $institution, $cohort, 0, array( 'why' => $generated->get_error_message() ) );
				continue;
			}

			update_post_meta( (int) $generated, WPCPM_Semester_Report::META_IN_PROGRESS, (int) $pair['in_progress'] );
			WPCPM_Semester_Report::log( WPCPM_Semester_Report::LOG_DRAFTED, $institution, $cohort, 0, array( 'in_progress' => (int) $pair['in_progress'] ) );

			if ( class_exists( 'WPCPM_Institutions' ) ) {
				WPCPM_Institutions::notify_managers(
					self::MAIL_DRAFTED,
					self::drafted_message( $institution, $cohort, (int) $pair['in_progress'] ),
					'report_notify'
				);
			}

			++$drafted;
		}

		return $drafted;
	}

	/**
	 * Approve a draft. From here the institution reads it and prints it.
	 *
	 * A report whose consent read fails at this moment is not approved: that read is the half
	 * of the document that decides who is named in it, and a report that cannot be drawn in
	 * full must not be published on the strength of a draft that could.
	 */
	public static function handle_approve() {
		$post   = self::state_target( self::ACTION_APPROVE );
		$cohort = self::cohort_of( $post );

		if ( WPCPM_Semester_Report::STATE_APPROVED === WPCPM_Semester_Report::state( $post ) ) {
			self::bounce( 'is-approved', array(), $cohort );
		}

		$consent = WPCPM_Semester_Report::consent_check( $post );

		if ( is_wp_error( $consent ) ) {
			self::bounce( 'approve-failed', array( 'why' => self::why_for_viewer( $consent ) ), $cohort );
		}

		WPCPM_Semester_Report::approve( $post, get_current_user_id() );

		$record = WPCPM_Semester_Report::institution_of( $post );

		WPCPM_Semester_Report::log( WPCPM_Semester_Report::LOG_APPROVED, $record, $cohort, get_current_user_id() );

		// Accounts, never the contact address in Airtable: the site does not write to people
		// who have never signed in to it. Forty of forty-two institutions have no account
		// today, and the flash says so, so the manager sends the PDF by hand.
		$notified = 0;

		foreach ( WPCPM_Institution_Members::members_of( $record ) as $member ) {
			if ( $member instanceof WP_User && WPCPM_Mail::send( $member, self::MAIL_APPROVED, self::approved_message( $cohort ) ) ) {
				++$notified;
			}
		}

		self::leave( 'approved', array( 'detail' => array( 'notified' => $notified ) ), $cohort );
	}

	/**
	 * Make an approved report a draft again. The institution's card drops it on its next load;
	 * a copy already downloaded is theirs, which is why nothing here pretends to recall it.
	 */
	public static function handle_reopen() {
		$post = self::state_target( self::ACTION_REOPEN );

		WPCPM_Semester_Report::reopen( $post );
		WPCPM_Semester_Report::log( WPCPM_Semester_Report::LOG_REOPENED, WPCPM_Semester_Report::institution_of( $post ), self::cohort_of( $post ), get_current_user_id() );

		self::leave( 'reopened', array(), self::cohort_of( $post ) );
	}

	/**
	 * The checks the two state buttons share, in the order that has to be identical.
	 *
	 * The report first, because everything else is derived from it: the institution comes
	 * off the post's own meta and never off the form, so another school's report ID is decided
	 * against that school and gets the one refusal. The decision is cheap and is made before
	 * the nonce, and the nonce is keyed to the report.
	 *
	 * @param string $action The `admin_post_` action, for the nonce.
	 * @return WP_Post The report; a refusal never returns.
	 */
	private static function state_target( $action ) {
		$post = self::posted_report();

		if ( ! $post instanceof WP_Post ) {
			self::bounce( 'refused' );
		}

		$decision = self::decide_edit( $post );

		if ( empty( $decision['allowed'] ) ) {
			self::bounce( 'refused' );
		}

		check_admin_referer( $action . '_' . $post->ID );

		return $post;
	}

	/**
	 * The message to the managers when the job drafts a report.
	 *
	 * @param string $institution Institutions record ID.
	 * @param string $cohort      Cohort key.
	 * @param int    $in_progress Rows still in progress when it was drafted.
	 * @return callable A builder for `WPCPM_Mail::send()`.
	 */
	private static function drafted_message( $institution, $cohort, $in_progress ) {
		return function () use ( $institution, $cohort, $in_progress ) {
			$name = class_exists( 'WPCPM_Institutions_Index' ) ? trim( (string) WPCPM_Institutions_Index::row( $institution )['name'] ) : '';
			$name = '' !== $name ? $name : $institution;
			$url  = self::report_url( $cohort );
			$url  = '' !== $url ? add_query_arg( WPCPM_Institution_Roster::ARG_VIEW, $institution, $url ) : '';

			$body = sprintf(
				/* translators: 1: institution name, 2: a semester, e.g. "January to June 2026". */
				__( 'A semester report has been drafted for %1$s, covering %2$s. It is waiting for a program manager to review and approve it; the institution cannot see it until then.', 'wpcredits-program-manager' ),
				$name,
				WPCPM_Cohort::label( $cohort )
			) . "\n\n";

			if ( $in_progress > 0 ) {
				$body .= sprintf(
					/* translators: %s: a number of students. */
					_n( '%s student in this semester was still in progress when the draft was written.', '%s students in this semester were still in progress when the draft was written.', $in_progress, 'wpcredits-program-manager' ),
					number_format_i18n( $in_progress )
				) . "\n\n";
			}

			if ( '' !== $url ) {
				$body .= __( 'Review it here:', 'wpcredits-program-manager' ) . "\n" . $url . "\n";
			}

			return array(
				'subject' => sprintf(
					/* translators: %s: institution name. */
					__( 'Semester report drafted: %s', 'wpcredits-program-manager' ),
					$name
				),
				'body'    => $body,
			);
		};
	}

	/**
	 * The message to an institution's accounts when a report is approved.
	 *
	 * @param string $cohort Cohort key.
	 * @return callable A builder for `WPCPM_Mail::send()`.
	 */
	private static function approved_message( $cohort ) {
		return function () use ( $cohort ) {
			$url = self::report_url( $cohort );

			$body = sprintf(
				/* translators: %s: a semester, e.g. "January to June 2026". */
				__( 'The semester report on %s has been approved by the WordPress Credits program. You can read it on your Institution Dashboard and download it as a PDF.', 'wpcredits-program-manager' ),
				WPCPM_Cohort::label( $cohort )
			) . "\n\n";

			if ( '' !== $url ) {
				$body .= $url . "\n";
			}

			return array(
				'subject' => __( 'Your semester report is ready', 'wpcredits-program-manager' ),
				'body'    => $body,
			);
		};
	}
```

In `handle_restore()`, replace

```php
		if ( 'final' === WPCPM_Semester_Report::state( $post ) ) {
			self::bounce( 'is-final', array(), self::cohort_of( $post ) );
		}
```

with

```php
		if ( WPCPM_Semester_Report::STATE_APPROVED === WPCPM_Semester_Report::state( $post ) ) {
			self::bounce( 'is-approved', array(), self::cohort_of( $post ) );
		}
```

- [ ] **Step 6: Update the messages and the summary sentences**

In `messages()`, delete the `'is-final'` and `'marked-final'` rows and add these rows (anywhere in the array):

```php
			'drafted'           => __( 'The draft is ready for review. Nothing in it has been sent anywhere, and the institution cannot see it until it is approved.', 'wpcredits-program-manager' ),
			'draft-exists'      => __( 'There is already a report for that semester. It is open below.', 'wpcredits-program-manager' ),
			'draft-refused'     => __( 'That is enough drafts for one day. Try again tomorrow.', 'wpcredits-program-manager' ),
			'is-approved'       => __( 'That report is approved. Reopen it before changing anything.', 'wpcredits-program-manager' ),
			'approved'          => __( 'Approved. The institution can now read and download it.', 'wpcredits-program-manager' ),
			'approve-failed'    => __( 'The report was not approved, because the students\' answers could not be read just now. Nothing changed. Try again shortly.', 'wpcredits-program-manager' ),
```

and change the `'reopened'` row to:

```php
			'reopened'          => __( 'This report is a draft again. The institution no longer sees it until it is approved once more.', 'wpcredits-program-manager' ),
```

In `summary_sentence()`, before the `generate-failed` block, add:

```php
		if ( 'approved' === $key && array_key_exists( 'notified', $detail ) ) {
			$notified = (int) $detail['notified'];

			if ( $notified < 1 ) {
				return __( 'Approved. No institution account to notify; send the PDF by hand.', 'wpcredits-program-manager' );
			}

			return sprintf(
				/* translators: %s: a number of accounts. */
				_n( 'Approved. The institution can now read and download it; %s of its accounts was told.', 'Approved. The institution can now read and download it; %s of its accounts were told.', $notified, 'wpcredits-program-manager' ),
				number_format_i18n( $notified )
			);
		}
```

and add `'approve-failed'` to the list in the condition `( 'generate-failed' === $key || 'consent-failed' === $key || 'ask-unread' === $key )`.

- [ ] **Step 7: Run the suite and the handler check**

Run: `php bin/test-semester-report.php 2>&1 | grep -E "FAIL|PASS|checks"; php bin/test-handlers.php 2>&1 | tail -2`
Expected: both `ALL PASS`.

---

### Task 5: What the institution sees, what the manager sees, and the print refusal

**Files:**
- Modify: `includes/modules/class-wpcpm-semester-report-screen.php` (`render()` line 191; `render_index()` line 297; `render_editor()` line 415; `render_header()` line 474; `render_actions()` line 744; `handle_print()` line 1906; `state_label()` line 2727; add `render_member_index()`, `render_reading()`, `render_draft_form()`)
- Test: `bin/test-semester-report.php` (append tests)

**Interfaces:**
- Consumes: `WPCPM_Institution_Policy::decide()` returning `ground`; `GROUND_MANAGER`; Task 1 readers.
- Produces: `render_draft_form( $record, $cohort, $label = '' )` (public, for the wp-admin card and later the Administrator Dashboard); a member's card with `wpcpm-report-card__print` links; the print route refusing a draft to a member with `unknown()`.

- [ ] **Step 1: Write the failing tests**

Append before `echo "\n=== House rules ===\n";`:

```php
echo "\n=== What the institution sees ===\n";

// B with a roster of its own: one finished semester to approve, one older one left a draft.
WPCPM_Semester_Report::delete_all();
$GLOBALS['transients'] = array();
$GLOBALS['flash']      = array();
$GLOBALS['index'][ $B ]['rows'] = array_merge( $finished, array(
	array( 'record_id' => 'recS0000000000007', 'email' => 'g@example.test', 'status' => 'Graduate', 'start' => '2025-09-10', 'end' => '2025-12-20' ),
) );
$GLOBALS['manage'] = true;
$GLOBALS['acting'] = '';
$m_approved = WPCPM_Semester_Report::generate( $B, '2026-H1' );
$m_draft    = WPCPM_Semester_Report::generate( $B, '2025-H2' );
WPCPM_Semester_Report::approve( get_post( $m_approved ), 3 );

$GLOBALS['manage'] = false;
$GLOBALS['acting'] = $B;
$GLOBALS['uid']    = 21;
$member_card = screen_html( $B, '' );
ck( 'the approved semester is listed', has( $member_card, 'January to June 2026' ), true );
ck( 'with a View link and a PDF link', has( $member_card, '>View<' ) && has( $member_card, '>Download PDF<' ), true );
ck( 'the draft is a sentence, not a report', has( $member_card, 'July to December 2025 is being prepared' ), true );
ck( 'no form at all for a member', has( $member_card, '<form' ), false );
foreach ( array( 'ACTION_GENERATE', 'ACTION_SAVE', 'ACTION_APPROVE', 'ACTION_REOPEN', 'ACTION_DRAFT', 'ACTION_REFRESH_CONSENT' ) as $constant ) {
	ck( 'and no ' . $constant . ' field', has( $member_card, constant( 'WPCPM_Semester_Report_Screen::' . $constant ) ), false );
}
ck( 'no textarea either', has( $member_card, '<textarea' ), false );

$member_reading = screen_html( $B, '2026-H1' );
ck( 'the approved report opens read-only', has( $member_reading, 'Back to the other semesters' ) && has( $member_reading, 'Participation' ), true );
ck( 'with the PDF button and no editor', has( $member_reading, '>Download PDF<' ) && ! has( $member_reading, '<textarea' ), true );
$member_asks_draft = screen_html( $B, '2025-H2' );
ck( 'asking for the draft by address lands on the list', has( $member_asks_draft, 'is being prepared' ) && ! has( $member_asks_draft, 'Participation' ), true );

$GLOBALS['acting'] = $A;
$other_card = screen_html( $A, '' );
ck( 'another institution sees its own empty card', has( $other_card, 'No semester report has been published' ), true );
ck( 'and nothing of B', has( $other_card, 'January to June 2026' ), false );

echo "\n=== What the manager sees ===\n";

$GLOBALS['manage'] = true;
$GLOBALS['acting'] = '';
$GLOBALS['uid']    = 3;
$draft_editor = screen_html( $B, '2025-H2' );
ck( 'a draft offers Approve and not Reopen', has( $draft_editor, 'value="' . WPCPM_Semester_Report_Screen::ACTION_APPROVE . '"' ) && ! has( $draft_editor, 'value="' . WPCPM_Semester_Report_Screen::ACTION_REOPEN . '"' ), true );
ck( 'and the editing form', has( $draft_editor, '<textarea' ), true );
ck( 'and says who drafted it', has( $draft_editor, 'Drafted by a program manager' ), true );
$approved_editor = screen_html( $B, '2026-H1' );
ck( 'an approved report offers Reopen and not Approve', has( $approved_editor, 'value="' . WPCPM_Semester_Report_Screen::ACTION_REOPEN . '"' ) && ! has( $approved_editor, 'value="' . WPCPM_Semester_Report_Screen::ACTION_APPROVE . '"' ), true );
ck( 'and no editing form', has( $approved_editor, '<textarea' ), false );
ck( 'and says who approved it', has( $approved_editor, 'Approved on' ) && has( $approved_editor, 'Person 3' ), true );
ck( 'the state word is Approved', has( $approved_editor, '>Approved<' ), true );
ob_start();
WPCPM_Semester_Report_Screen::render_draft_form( $B, '2024-H2' );
$draft_form = ob_get_clean();
ck( 'the Draft now form posts the action with the two IDs', has( $draft_form, 'value="' . WPCPM_Semester_Report_Screen::ACTION_DRAFT . '"' ) && has( $draft_form, 'name="institution" value="' . $B . '"' ) && has( $draft_form, 'name="cohort" value="2024-H2"' ), true );
ck( 'guarded against a double press', has( $draft_form, 'data-wpcpm-once' ), true );

echo "\n=== Printing ===\n";

$GLOBALS['manage'] = false;
$GLOBALS['acting'] = $B;
$_POST = array();
$_GET  = array( 'report' => $m_draft );
$draft_print = run_handler( WPCPM_Semester_Report_Screen::ACTION_PRINT );
$_GET  = array( 'report' => 999999 );
$ghost_print = run_handler( WPCPM_Semester_Report_Screen::ACTION_PRINT );
ck( 'a member printing a draft reads exactly as a ghost', $draft_print === $ghost_print && '' !== $draft_print, true );
$_GET = array( 'report' => $m_approved );
$approved_print = run_handler( WPCPM_Semester_Report_Screen::ACTION_PRINT );
ck( 'and prints the approved one', has( $approved_print, '<!DOCTYPE html>' ) || has( $approved_print, '<html' ), true );
$GLOBALS['manage'] = true;
$GLOBALS['acting'] = '';
$_GET = array( 'report' => $m_draft );
ck( 'a manager prints a draft', has( run_handler( WPCPM_Semester_Report_Screen::ACTION_PRINT ), '<html' ), true );
$_GET = array();
```

- [ ] **Step 2: Run the suite to verify it fails**

Run: `php bin/test-semester-report.php 2>&1 | grep -E "^FAIL" | head -8`
Expected: failures such as `no form at all for a member`, `the approved report opens read-only`, `a member printing a draft reads exactly as a ghost`, `the Draft now form posts the action`.

- [ ] **Step 3: Split `render()` by ground**

In `render()`, after the `$decision` refusal check, add:

```php
		// The ground decides which page this is. A manager gets the editor and the index;
		// an institution gets its approved reports and a sentence about the rest (design
		// of 4 September 2026, decision 1).
		$manager = isset( $decision['ground'] ) && WPCPM_Institution_Policy::GROUND_MANAGER === $decision['ground'];
```

After `$report = '' === $cohort ? null : WPCPM_Semester_Report::find( $record, $cohort );` add:

```php
		// **A draft does not exist for the institution.** An address naming its cohort lands
		// on the list, which says the report is being prepared; the editor, the forms and the
		// document are a manager's until approval.
		if ( ! $manager && $report instanceof WP_Post && WPCPM_Semester_Report::STATE_APPROVED !== WPCPM_Semester_Report::state( $report ) ) {
			$report = null;
			$cohort = '';
		}
```

Replace the `if ( $report instanceof WP_Post ) { ... } else { ... }` block with:

```php
		if ( $report instanceof WP_Post ) {
			if ( $manager ) {
				self::render_editor( $report, $record );
			} else {
				self::render_reading( $report );
			}
		} elseif ( $manager ) {
			self::render_index( $record, $cohort, true );
		} else {
			self::render_member_index( $record );
		}
```

- [ ] **Step 4: Add the two member renderers and the draft form**

After `render_generate_form()`, add:

```php
	/**
	 * The institution's card: approved reports to read and print, and a sentence per draft.
	 *
	 * No `<form>` is drawn here: no nonce, no post ID, no submit path in markup nobody may
	 * submit, the rule the Student Report Card follows for a viewer who may not save.
	 *
	 * @param string $record Institutions record ID.
	 */
	private static function render_member_index( $record ) {
		$approved = array();
		$drafts   = array();

		foreach ( self::cohorts_of( $record ) as $cohort ) {
			$post = WPCPM_Semester_Report::find( $record, $cohort );

			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			if ( WPCPM_Semester_Report::STATE_APPROVED === WPCPM_Semester_Report::state( $post ) ) {
				$approved[ $cohort ] = $post;
			} else {
				$drafts[] = $cohort;
			}
		}

		// An empty half under a heading reads as something that failed to load; the Updates
		// column learned that, and so does this card.
		if ( empty( $approved ) && empty( $drafts ) ) {
			printf(
				'<p class="wpcpm-report-card__empty">%s</p>',
				esc_html__( 'No semester report has been published for your institution yet.', 'wpcredits-program-manager' )
			);

			return;
		}

		if ( ! empty( $approved ) ) {
			$format = get_option( 'date_format' );

			echo '<ul class="wpcpm-report-card__cohorts">';

			foreach ( $approved as $cohort => $post ) {
				$stamp = WPCPM_Semester_Report::approved_at( $post );
				$url   = self::report_url( $cohort );

				echo '<li class="wpcpm-report-card__cohort">';
				printf( '<span class="wpcpm-report-card__cohort-name">%s</span>', esc_html( WPCPM_Cohort::label( $cohort ) ) );
				printf(
					'<span class="wpcpm-report-card__state">%s</span>',
					esc_html(
						empty( $stamp['at'] )
							? __( 'Approved', 'wpcredits-program-manager' )
							: sprintf(
								/* translators: %s: a date. */
								__( 'Approved on %s', 'wpcredits-program-manager' ),
								wp_date( $format, (int) $stamp['at'] )
							)
					)
				);

				if ( '' !== $url ) {
					printf( '<a class="wpcpm-report-card__open" href="%1$s">%2$s</a>', esc_url( $url ), esc_html__( 'View', 'wpcredits-program-manager' ) );
				}

				printf( '<a class="wpcpm-report-card__print" href="%1$s">%2$s</a>', esc_url( self::print_url( $post->ID ) ), esc_html__( 'Download PDF', 'wpcredits-program-manager' ) );
				echo '</li>';
			}

			echo '</ul>';
		}

		foreach ( $drafts as $cohort ) {
			printf(
				'<p class="wpcpm-report-card__note">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: a semester, e.g. "January to June 2026". */
						__( 'Your semester report for %s is being prepared by the program team.', 'wpcredits-program-manager' ),
						WPCPM_Cohort::label( $cohort )
					)
				)
			);
		}
	}

	/**
	 * One approved report, for the institution to read: the header, the PDF button, the document.
	 *
	 * @param WP_Post $post The report.
	 */
	private static function render_reading( WP_Post $post ) {
		$page = class_exists( 'WPCPM_Institutions_Dashboard' ) ? WPCPM_Institutions_Dashboard::page_url() : '';

		if ( '' !== $page ) {
			printf(
				'<p class="wpcpm-report-card__back"><a href="%1$s">%2$s</a></p>',
				esc_url( remove_query_arg( self::ARG, $page ) . '#wpcpm-report' ),
				esc_html__( 'Back to the other semesters', 'wpcredits-program-manager' )
			);
		}

		self::render_header( $post, WPCPM_Semester_Report::state( $post ), WPCPM_Semester_Report::snapshot( $post ) );

		printf(
			'<p class="wpcpm-report-card__action"><a class="wpcpm-button" href="%1$s">%2$s</a></p>',
			esc_url( self::print_url( $post->ID ) ),
			esc_html__( 'Download PDF', 'wpcredits-program-manager' )
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_document() escapes every value it interpolates; escaping it again would print its own markup.
		echo self::render_document( $post, false );
	}

	/**
	 * The Draft now form, for the manager screens to draw beside a cohort that is due.
	 *
	 * Here rather than in `WPCPM_Institutions` so the action name and the nonce it is keyed
	 * to are written once, beside the handler that checks them. The nonce is not keyed to the
	 * record: the capability is the gate, and one form per due row is what the Administrator
	 * Dashboard draws.
	 *
	 * @param string $record Institutions record ID.
	 * @param string $cohort Cohort key.
	 * @param string $label  The button; "Draft now" when empty.
	 */
	public static function render_draft_form( $record, $cohort, $label = '' ) {
		printf(
			'<form class="wpcpm-report-card__generate" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Reading the program records', 'wpcredits-program-manager' )
		);

		wp_nonce_field( self::ACTION_DRAFT );

		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_DRAFT ) );
		printf( '<input type="hidden" name="institution" value="%s" />', esc_attr( $record ) );
		printf( '<input type="hidden" name="cohort" value="%s" />', esc_attr( $cohort ) );
		printf( '<button type="submit" class="button">%s</button>', esc_html( '' !== $label ? $label : __( 'Draft now', 'wpcredits-program-manager' ) ) );

		echo '</form>';
	}
```

- [ ] **Step 5: Update the editor, the header, the actions, the state label and the print route**

In `render_editor()`, change `$may_edit = self::may_edit( $record ) && 'final' !== $state;` to

```php
		$may_edit = self::may_edit( $record ) && WPCPM_Semester_Report::STATE_APPROVED !== $state;
```

and the read-only note to

```php
			printf(
				'<p class="wpcpm-report-card__note">%s</p>',
				WPCPM_Semester_Report::STATE_APPROVED === $state
					? esc_html__( 'This report is approved and the institution can read it. Reopen it to change anything.', 'wpcredits-program-manager' )
					: esc_html__( 'You can read this report here. Changing it is something a program manager does.', 'wpcredits-program-manager' )
			);
```

In `render_header()`, after the "Read from the program records" block and before `$edited = ...`, add:

```php
		printf(
			'<p class="wpcpm-report-card__fact">%s</p>',
			esc_html(
				WPCPM_Semester_Report::ORIGIN_AUTO === WPCPM_Semester_Report::origin_of( $post )
					? __( 'Drafted automatically when the semester ended.', 'wpcredits-program-manager' )
					: __( 'Drafted by a program manager.', 'wpcredits-program-manager' )
			)
		);

		$approved = WPCPM_Semester_Report::approved_at( $post );

		if ( ! empty( $approved['at'] ) ) {
			$by = ! empty( $approved['by'] ) ? get_user_by( 'id', (int) $approved['by'] ) : false;

			printf(
				'<p class="wpcpm-report-card__fact">%s</p>',
				esc_html(
					$by instanceof WP_User
						? sprintf(
							/* translators: 1: date and time, 2: a program manager's name. */
							__( 'Approved on %1$s by %2$s.', 'wpcredits-program-manager' ),
							wp_date( $format, (int) $approved['at'] ),
							$by->display_name
						)
						: sprintf(
							/* translators: %s: date and time. */
							__( 'Approved on %s.', 'wpcredits-program-manager' ),
							wp_date( $format, (int) $approved['at'] )
						)
				)
			);
		}
```

In `render_actions()`, replace the `if ( 'final' === $state ) { ... } else { ... }` block with:

```php
		if ( WPCPM_Semester_Report::STATE_APPROVED === $state ) {
			self::render_button_form(
				self::ACTION_REOPEN,
				$post->ID,
				__( 'Reopening', 'wpcredits-program-manager' ),
				__( 'Reopen for editing', 'wpcredits-program-manager' )
			);
		} else {
			self::render_button_form(
				self::ACTION_APPROVE,
				$post->ID,
				__( 'Approving', 'wpcredits-program-manager' ),
				__( 'Approve this report', 'wpcredits-program-manager' )
			);
		}
```

and change the comment above the consent button from "Allowed on a final report" to "Allowed on an approved report" (the reason stands).

Replace `state_label()`:

```php
	private static function state_label( $state ) {
		return WPCPM_Semester_Report::STATE_APPROVED === $state
			? __( 'Approved', 'wpcredits-program-manager' )
			: __( 'Draft', 'wpcredits-program-manager' );
	}
```

In `handle_print()`, after the decision refusal and before `check_admin_referer`, add:

```php
		// A member reads approved reports and nothing else, and a draft's print route answers
		// exactly as a nonexistent post's does. The card says a draft is being prepared, in
		// words, on the institution's own page; a route that confirmed it would confirm it to
		// anyone holding a guessed ID.
		if ( ( ! isset( $decision['ground'] ) || WPCPM_Institution_Policy::GROUND_MANAGER !== $decision['ground'] )
			&& WPCPM_Semester_Report::STATE_APPROVED !== WPCPM_Semester_Report::state( $post ) ) {
			self::unknown();
		}
```

- [ ] **Step 6: Run the suite, the reference check and grep for leftovers**

Run: `php bin/test-semester-report.php 2>&1 | grep -E "FAIL|PASS|checks"; php bin/check-references.php | tail -2; grep -n "final" includes/modules/class-wpcpm-semester-report-screen.php | grep -v "finally\|Final" | head`
Expected: `ALL PASS`, references resolve, and the only remaining `final` in the screen is inside prose that describes the old vocabulary (rewrite any such comment to say approved).

---

### Task 6: The module: cron, uninstall, the third argument of `notify_managers()`, the wp-admin card

**Files:**
- Modify: `includes/modules/class-wpcpm-institutions.php` (`schedule_cron()` line 419; `uninstall()` line 448; `notify_managers()` line 1186; `render_semester_reports()` line 3924; `render_semester_report_row()` line 3990)
- Test: `bin/test-institutions-screen.php` (stubs lines 879-907; seed line 1317; assertions lines 1780-1800; uninstall assertion line 2857)

**Interfaces:**
- Consumes: `WPCPM_Semester_Report::due()`, `queue()`, `log_entries()`, `origin_of()`, `STATE_APPROVED`, `WPCPM_Semester_Report_Screen::CRON_AUTODRAFT`, `render_draft_form()`.
- Produces: `WPCPM_Institutions::notify_managers( $context, $build, $setting_key = 'agreement_notify' )`; the cron scheduled and cleared; the card's due list, Draft now forms, origin column and log.

- [ ] **Step 1: Extend the stubs and the assertions in the institutions-screen suite**

In the `WPCPM_Semester_Report` stub, replace `const STATE_FINAL = 'final';` with:

```php
		const STATE_DRAFT    = 'draft';
		const STATE_APPROVED = 'approved';
		const ORIGIN_AUTO    = 'auto';
		public static function origin_of( WP_Post $post ) { return (string) get_post_meta( $post->ID, '_wpcpm_report_origin', true ) === 'auto' ? 'auto' : 'manager'; }
		public static function approved_at( WP_Post $post ) { $s = get_post_meta( $post->ID, '_wpcpm_report_approved', true ); return is_array( $s ) ? $s : array(); }
		public static function due( $today ) { return isset( $GLOBALS['due'] ) ? $GLOBALS['due'] : array(); }
		public static function log_entries() { return isset( $GLOBALS['report_log'] ) ? $GLOBALS['report_log'] : array(); }
```

In the `WPCPM_Semester_Report_Screen` stub, add after `const CRON_ASK`:

```php
		const CRON_AUTODRAFT = 'wpcpm_report_autodraft';
		const ACTION_DRAFT   = 'wpcpm_report_draft';
		public static function render_draft_form( $record, $cohort, $label = '' ) {
			printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
			printf( '<input type="hidden" name="action" value="%s" /><input type="hidden" name="institution" value="%s" /><input type="hidden" name="cohort" value="%s" />', esc_attr( self::ACTION_DRAFT ), esc_attr( $record ), esc_attr( $cohort ) );
			echo '<button type="submit">Draft now</button></form>';
		}
```

Change the seed line `seed_report( 9102, $report_record, '2025-H2', 'final', 1755000000 );` to use `'approved'`, and before `$html = render_screen();` add:

```php
$GLOBALS['due'] = array( array( 'institution' => $report_record, 'cohort' => '2024-H2', 'in_progress' => 0, 'window_end' => '2024-12-31' ) );
$GLOBALS['report_log'] = array( array( 'event' => 'approved', 'institution' => $report_record, 'cohort' => '2025-H2', 'actor' => 1, 'at' => 1755000000 ) );
```

Change `ck( 'and a final report says so', false !== strpos( $report_rows[1], 'Final' ), true );` to look for `'Approved'`, and after it add:

```php
ck( 'drafts come first whatever their date', false !== strpos( $report_rows[0], 'Draft' ), true );
ck( 'the due list offers Draft now for the cohort the job would draft', false !== strpos( $html, 'name="cohort" value="2024-H2"' ) && false !== strpos( $html, 'value="' . WPCPM_Semester_Report_Screen::ACTION_DRAFT . '"' ), true );
ck( 'and the log is drawn', false !== strpos( $html, 'Report log' ) && false !== strpos( $html, 'approved' ), true );
```

In the uninstall assertion, add a seventh element `in_array( 'unschedule:' . WPCPM_Semester_Report_Screen::CRON_AUTODRAFT, $names, true ),` and a seventh `true` to the expected array.

- [ ] **Step 2: Run the suite to verify it fails**

Run: `php bin/test-institutions-screen.php 2>&1 | grep -E "^FAIL" | head -5`
Expected: failures on the Draft now form, the log, and the unschedule.

- [ ] **Step 3: Wire the cron and the uninstall, and widen `notify_managers()`**

In `schedule_cron()`, change the `$nightly` array to:

```php
		$nightly = array(
			self::CRON_PURGE,
			WPCPM_Institution_Agreement::CRON_DISCARD,
			WPCPM_Institution_Agreement::CRON_REMINDERS,
			WPCPM_Institution_Invite::CRON_EXPIRE,
			// The semester report job (design of 4 September 2026), two hours after the last
			// of the others so a slow night never has two jobs reading the base at once.
			WPCPM_Semester_Report_Screen::CRON_AUTODRAFT,
		);
```

In `uninstall()`, after `wp_clear_scheduled_hook( WPCPM_Semester_Report_Screen::CRON_ASK );` add:

```php
		wp_clear_scheduled_hook( WPCPM_Semester_Report_Screen::CRON_AUTODRAFT );
```

Change the signature of `notify_managers()` to `public static function notify_managers( $context, $build, $setting_key = 'agreement_notify' )`, its docblock's first paragraph to say "recipients are the setting named by `$setting_key` (`agreement_notify` for the agreement queue, `report_notify` for the semester report job) when it names anybody, otherwise every account holding the management capability", and the line reading the setting to:

```php
		$setting = trim( (string) WPCPM_Settings::get_value( (string) $setting_key, '' ) );
```

- [ ] **Step 4: Rewrite the card**

Replace `render_semester_reports()` and `render_semester_report_row()` with:

```php
	private function render_semester_reports() {
		// `private` by name rather than `any`: it is the one status a report is ever written
		// with, and `any` would also bring back a report somebody had trashed by hand, which
		// is a document that has been withdrawn rather than one to list.
		$posts = get_posts(
			array(
				'post_type'        => WPCPM_Semester_Report::POST_TYPE,
				'post_status'      => 'private',
				'posts_per_page'   => self::REPORTS_SHOWN,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);

		// Drafts first, whatever their date: this card is the manager's queue since the
		// approval design, and the thing waiting for them belongs above the thing that is done.
		usort(
			$posts,
			static function ( $a, $b ) {
				$a_draft = WPCPM_Semester_Report::STATE_APPROVED !== WPCPM_Semester_Report::state( $a );
				$b_draft = WPCPM_Semester_Report::STATE_APPROVED !== WPCPM_Semester_Report::state( $b );

				if ( $a_draft !== $b_draft ) {
					return $a_draft ? -1 : 1;
				}

				return strcmp( (string) $b->post_modified_gmt, (string) $a->post_modified_gmt );
			}
		);

		$total = 0;

		if ( ! empty( $posts ) ) {
			$tally = wp_count_posts( WPCPM_Semester_Report::POST_TYPE );
			$total = isset( $tally->private ) ? (int) $tally->private : count( $posts );
		}

		echo '<div class="wpcpm-card">';
		printf(
			'<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>',
			esc_html__( 'Semester reports', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( $total ) )
		);
		echo '<p class="description">' . esc_html__( 'The site drafts a report when a semester ends; a program manager reviews and approves it on the institution dashboard, and only then does the institution see it. Each report opens there as that institution.', 'wpcredits-program-manager' ) . '</p>';

		if ( empty( $posts ) ) {
			echo '<p>' . esc_html__( 'No report has been drafted yet.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			if ( $total > count( $posts ) ) {
				printf(
					'<p class="description">%s</p>',
					esc_html(
						sprintf(
							/* translators: %s: how many reports are listed. */
							__( 'The %s most recently edited are listed.', 'wpcredits-program-manager' ),
							number_format_i18n( count( $posts ) )
						)
					)
				);
			}

			// Design spec section 14, open question 2: the consent request is a program manager's
			// to send and nobody else's, so this is the only screen it is offered on.
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: a number of messages. */
						__( 'Asking writes one short message to each student in that semester who wrote feedback and has not said whether it may be used. Nobody is asked twice in thirty days, and at most %s go out at a time; the rest follow shortly.', 'wpcredits-program-manager' ),
						number_format_i18n( WPCPM_Semester_Report_Screen::ASK_PER_RUN )
					)
				)
			);

			echo '<table class="widefat striped wpcpm-list"><thead><tr>';
			echo '<th scope="col">' . esc_html__( 'Institution', 'wpcredits-program-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Semester', 'wpcredits-program-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'State', 'wpcredits-program-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Drafted', 'wpcredits-program-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Last edited', 'wpcredits-program-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Consent', 'wpcredits-program-manager' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $posts as $post ) {
				$this->render_semester_report_row( $post );
			}

			echo '</tbody></table>';
		}

		$this->render_report_due();
		$this->render_report_log();

		echo '</div>';
	}

	/**
	 * One row of the semester reports card.
	 *
	 * @param WP_Post $post The report.
	 */
	private function render_semester_report_row( WP_Post $post ) {
		$record = WPCPM_Semester_Report::institution_of( $post );
		$cohort = WPCPM_Semester_Report::cohort_of( $post );
		$name   = '' !== $record ? self::institution_name( $record ) : '';
		$name   = '' !== $name ? $name : __( '(institution not in the index)', 'wpcredits-program-manager' );

		// The switcher argument, because `resolve_institution()` is what places a manager on
		// somebody else's dashboard and it reads that one argument and nothing else.
		$url = WPCPM_Semester_Report_Screen::report_url( $cohort );
		$url = ( '' !== $url && '' !== $record ) ? add_query_arg( array( WPCPM_Institution_Roster::ARG_VIEW => $record ), $url ) : '';

		$label     = '' !== $cohort ? WPCPM_Cohort::label( $cohort ) : __( '(no semester recorded)', 'wpcredits-program-manager' );
		$generated = WPCPM_Semester_Report::generated_at( $post );
		$edited    = (int) get_post_modified_time( 'U', true, $post );
		$approved  = WPCPM_Semester_Report::STATE_APPROVED === WPCPM_Semester_Report::state( $post );
		$format    = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$origin    = WPCPM_Semester_Report::ORIGIN_AUTO === WPCPM_Semester_Report::origin_of( $post )
			? __( 'by the site', 'wpcredits-program-manager' )
			: __( 'by a manager', 'wpcredits-program-manager' );

		printf(
			'<tr><td>%1$s<br /><code>%2$s</code></td><td>%3$s%4$s</td><td>%5$s</td><td>%6$s<br /><span class="description">%7$s</span></td><td>%8$s</td><td>',
			'' !== $url
				? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $name ) )
				: esc_html( $name ),
			esc_html( '' !== $record ? $record : '-' ),
			esc_html( $label ),
			'' !== $cohort ? sprintf( ' <code>%s</code>', esc_html( $cohort ) ) : '',
			esc_html( $approved ? __( 'Approved', 'wpcredits-program-manager' ) : __( 'Draft', 'wpcredits-program-manager' ) ),
			esc_html( $generated ? wp_date( $format, $generated ) : __( 'not yet', 'wpcredits-program-manager' ) ),
			esc_html( $origin ),
			esc_html( $edited ? wp_date( $format, $edited ) : __( 'never', 'wpcredits-program-manager' ) )
		);

		// Offered whatever state the report is in, including approved, and deliberately: this
		// asks students a question, it changes no word of the document, and the answers reach
		// the document through the consent re-check every render makes.
		WPCPM_Semester_Report_Screen::render_ask_form( $post->ID );

		echo '</td></tr>';
	}

	/**
	 * The cohorts the job would draft tonight, each with a Draft now button.
	 */
	private function render_report_due() {
		$due = WPCPM_Semester_Report::due( wp_date( 'Y-m-d' ) );

		printf( '<h3>%s</h3>', esc_html__( 'Due for drafting', 'wpcredits-program-manager' ) );

		if ( empty( $due ) ) {
			echo '<p class="description">' . esc_html__( 'Nothing is waiting: every finished semester has a report, or is not finished yet.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		echo '<table class="widefat striped wpcpm-list"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Institution', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Semester', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Still in progress', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col"></th>';
		echo '</tr></thead><tbody>';

		foreach ( $due as $pair ) {
			$name = self::institution_name( $pair['institution'] );

			printf(
				'<tr><td>%1$s<br /><code>%2$s</code></td><td>%3$s</td><td>%4$s</td><td>',
				esc_html( '' !== $name ? $name : __( '(institution not in the index)', 'wpcredits-program-manager' ) ),
				esc_html( $pair['institution'] ),
				esc_html( WPCPM_Cohort::label( $pair['cohort'] ) ),
				esc_html( number_format_i18n( (int) $pair['in_progress'] ) )
			);

			WPCPM_Semester_Report_Screen::render_draft_form( $pair['institution'], $pair['cohort'] );

			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * The last twenty things that happened to a report.
	 */
	private function render_report_log() {
		$entries = array_slice( WPCPM_Semester_Report::log_entries(), 0, 20 );
		$format  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		printf( '<h3>%s</h3>', esc_html__( 'Report log', 'wpcredits-program-manager' ) );

		if ( empty( $entries ) ) {
			echo '<p class="description">' . esc_html__( 'Nothing yet.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		echo '<ul class="wpcpm-log">';

		foreach ( $entries as $entry ) {
			$actor = ! empty( $entry['actor'] ) ? get_user_by( 'id', (int) $entry['actor'] ) : false;
			$name  = self::institution_name( isset( $entry['institution'] ) ? $entry['institution'] : '' );

			printf(
				'<li>%1$s: <strong>%2$s</strong>, %3$s, %4$s, %5$s%6$s</li>',
				esc_html( wp_date( $format, isset( $entry['at'] ) ? (int) $entry['at'] : 0 ) ),
				esc_html( isset( $entry['event'] ) ? str_replace( '_', ' ', (string) $entry['event'] ) : '' ),
				esc_html( '' !== $name ? $name : ( isset( $entry['institution'] ) ? $entry['institution'] : '' ) ),
				esc_html( isset( $entry['cohort'] ) ? WPCPM_Cohort::label( $entry['cohort'] ) : '' ),
				esc_html( $actor instanceof WP_User ? $actor->display_name : __( 'the site', 'wpcredits-program-manager' ) ),
				! empty( $entry['why'] ) ? esc_html( ': ' . $entry['why'] ) : ''
			);
		}

		echo '</ul>';
	}
```

- [ ] **Step 5: Run the three suites and the reference check**

Run: `php bin/test-institutions-screen.php 2>&1 | grep -E "FAIL|PASS" | head -5; php bin/test-semester-report.php 2>&1 | grep -E "FAIL|PASS|checks"; php bin/test-handlers.php 2>&1 | tail -1; php bin/check-references.php | tail -2`
Expected: all `ALL PASS`; every reference resolves.

---

### Task 7: Settings and the settings screen

**Files:**
- Modify: `includes/class-wpcpm-settings.php` (defaults line 147; `save()` lines 322-340 and 359-367 and 389-404)
- Modify: `includes/class-wpcpm-admin.php` (after the `agreement_notify` row, line 640)
- Test: `bin/test-settings.php` (append)

**Interfaces:**
- Produces: settings `report_autodraft` (bool, true), `report_autodraft_grace_days` (int 7..365, 45), `report_notify` (string of addresses, '').

- [ ] **Step 1: Write the failing tests**

Append to `bin/test-settings.php` before its final summary line:

```php
echo "\n=== Semester report settings ===\n";

$defaults = WPCPM_Settings::defaults();
ck( 'the three report settings and their defaults', array( $defaults['report_autodraft'], $defaults['report_autodraft_grace_days'], $defaults['report_notify'] ), array( true, 45, '' ) );

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = WPCPM_Settings::defaults();
$saved = WPCPM_Settings::save( array( 'report_autodraft_grace_days' => '2', 'report_notify' => "one@example.org, junk\nmaciej@a8c.com" ) );
ck( 'the grace has a floor of a week', $saved['report_autodraft_grace_days'], 7 );
ck( 'the addresses are cleaned like the agreement ones', $saved['report_notify'], 'one@example.org,maciej@a8c.com' );
ck( 'a save that does not carry the switch leaves it on', $saved['report_autodraft'], true );
$saved = WPCPM_Settings::save( array( 'report_autodraft' => '', 'report_autodraft_grace_days' => '900' ) );
ck( 'the switch carried empty is off', $saved['report_autodraft'], false );
ck( 'and the grace has a ceiling of a year', $saved['report_autodraft_grace_days'], 365 );
```

- [ ] **Step 2: Run the suite to verify it fails**

Run: `php bin/test-settings.php 2>&1 | grep -E "^FAIL|Warning" | head -3`
Expected: `FAIL the three report settings and their defaults` (undefined index).

- [ ] **Step 3: Add the defaults and the save rules**

In `defaults()`, after `'agreement_notify' => '',` add:

```php
			// The semester report approval flow (design of 4 September 2026): the daily
			// drafting job, how long a semester with unfinished rows waits, who hears of a draft.
			'report_autodraft'              => true,
			'report_autodraft_grace_days'   => 45,
			'report_notify'                 => '',
```

In `save()`, turn the `agreement_notify` block into a loop over both keys:

```php
		foreach ( array( 'agreement_notify', 'report_notify' ) as $notify_key ) {
			if ( ! isset( $input[ $notify_key ] ) ) {
				continue;
			}

			$raw       = wp_unslash( $input[ $notify_key ] );
			$raw       = is_array( $raw ) ? $raw : preg_split( '/[\s,]+/', (string) $raw );
			$addresses = array();
			foreach ( $raw as $address ) {
				if ( ! is_string( $address ) ) {
					continue;
				}

				// Lowercased before the de-duplication below: an address is one mailbox however
				// it was typed, and the notice must not reach it twice.
				$address = sanitize_email( strtolower( trim( $address ) ) );
				if ( '' !== $address && is_email( $address ) ) {
					$addresses[] = $address;
				}
			}
			$clean[ $notify_key ] = implode( ',', array_unique( $addresses ) );
		}
```

Add `'report_autodraft'` to the flags array `array( 'institution_provision', 'institution_home', 'applications_enabled', 'import_enabled' )`, and add to `$limits`:

```php
			// A week's floor keeps a typo from drafting every unfinished semester tonight.
			'report_autodraft_grace_days'   => array( 7, 365 ),
```

- [ ] **Step 4: Add the three rows to the settings screen**

In `includes/class-wpcpm-admin.php`, after the `agreement_notify` row's `printf(...)`, add:

```php
		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="report_autodraft" value="1"%2$s> %3$s</label><p class="description">%4$s</p></td></tr>',
			esc_html__( 'Semester reports', 'wpcredits-program-manager' ),
			checked( ! empty( $settings['report_autodraft'] ), true, false ),
			esc_html__( 'Draft each institution\'s semester report when the semester ends', 'wpcredits-program-manager' ),
			esc_html__( 'A daily job drafts a report for every finished semester and tells the program managers. Off, drafts are written only when a manager presses Draft now.', 'wpcredits-program-manager' )
		);

		printf(
			'<tr><th scope="row">%1$s</th><td><input type="number" class="small-text" name="report_autodraft_grace_days" min="7" max="365" value="%2$s"> %3$s<p class="description">%4$s</p></td></tr>',
			esc_html__( 'Drafting grace', 'wpcredits-program-manager' ),
			esc_attr( (string) $settings['report_autodraft_grace_days'] ),
			esc_html__( 'days', 'wpcredits-program-manager' ),
			esc_html__( 'How long after a semester ends the job waits for students still in progress before drafting anyway. The draft says how many were still in progress.', 'wpcredits-program-manager' )
		);

		printf(
			'<tr><th scope="row">%1$s</th><td><input type="text" class="regular-text" name="report_notify" value="%2$s" placeholder="%3$s"><p class="description">%4$s</p></td></tr>',
			esc_html__( 'Who reviews reports', 'wpcredits-program-manager' ),
			esc_attr( (string) $settings['report_notify'] ),
			esc_attr__( 'one@example.org, two@example.org', 'wpcredits-program-manager' ),
			esc_html__( 'Addresses told when the job drafts a report. Leave it empty and every program manager is written to.', 'wpcredits-program-manager' )
		);
```

- [ ] **Step 5: Run the settings suite and the whole suite set**

Run: `php bin/test-settings.php 2>&1 | grep -E "FAIL|PASS" | head -3; for f in bin/test-*.php; do r=$(php "$f" 2>&1 | tail -1); case "$r" in *PASS*) ;; *) echo "$f: $r";; esac; done; echo "suites done"`
Expected: `ALL PASS` for settings and no suite listed as failing.

---

### Task 8: Docs, readme, version, standards, zip

**Files:**
- Modify: `wpcredits-program-manager.php:6,22`, `readme.txt:7` and the changelog, `docs/sections/34-admin-operations.md` (the options table and a paragraph), `docs/specs/2026-09-04-semester-report-approval-design.md` (one line, section 8)

- [ ] **Step 1: Bump the version**

Change `* Version:           1.90.0` and `define( 'WPCPM_VERSION', '1.90.0' );` to `1.91.0`, and `Stable tag: 1.90.0` to `Stable tag: 1.91.0`.

- [ ] **Step 2: Add the changelog entry**

Above `= 1.90.0 =` in `readme.txt`, add:

```
= 1.91.0 =
* **The semester report is drafted by the site and approved by a program manager; the institution reads it.** A daily job drafts a report for every institution semester that has ended (after a grace of 45 days when students are still in progress, and never for a semester that ended before this release was installed), and tells the program managers. A manager can also press Draft now on the Institutions screen for any institution and semester. Managers edit the report on the Institution Dashboard as before and press Approve; only then does the institution see it, as a read-only document with a Download PDF button, and its accounts are mailed. Reopening takes it back to a draft and out of the institution's view. Institutions can no longer generate or edit reports (design of 4 September 2026).
* The two states are draft and approved. Reports marked final by the earlier flow read as approved after the upgrade, approved by nobody in particular. Approval is refused while the students' consent answers cannot be read, because a report that cannot be drawn in full must not be published.
* The Institutions screen's Semester reports card lists drafts first, says whether the site or a manager drafted each report, lists the semesters due for drafting with a Draft now button each, and keeps a log of drafts, approvals and reopenings.
* Three settings: whether the job runs, the grace, and who hears of a draft (empty means every program manager).
```

- [ ] **Step 3: Update the administrators guide**

In `docs/sections/34-admin-operations.md`, add to the options table, in alphabetical position:

```
| `wpcpm_report_autodraft_since` | `WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE` |
| `wpcpm_report_log` | `WPCPM_Semester_Report::OPT_LOG` |
| `wpcpm_report_state_version` | `WPCPM_Semester_Report::OPT_STATE_VERSION` |
```

and a short section, before the data table's section, headed `## Semester reports` with this text:

```
The site drafts a report for each institution semester once the semester has ended and every student in it is finished, or 45 days after the end (the "Drafting grace" setting) if some are not. The job runs nightly and drafts at most ten reports a run; it never drafts a semester that ended before the feature was installed, so older semesters are drafted by hand with the Draft now button on the Institutions screen. Each draft is mailed to the addresses in "Who reviews reports", or to every program manager when that is empty.

Review a draft on the Institution Dashboard, reached through the switcher from the Semester reports card. Edit the narrative, choose the quotes, then press Approve. The institution sees the report from that moment, with a Download PDF button, and its accounts are mailed; an institution with no account is not mailed and the screen says so, so send the PDF by hand. Reopen takes the report back to a draft and out of the institution's view. Approval is refused while the students' consent answers cannot be read.
```

- [ ] **Step 4: Record the one deviation from the spec**

In `docs/specs/2026-09-04-semester-report-approval-design.md`, section 8, replace the last sentence of the paragraph beginning `report_notify` takes the semantics with: "The recipient resolution stays in `WPCPM_Institutions::notify_managers()`, which gains a third argument naming the setting to read; `agreement_notify` is its default and the job passes `report_notify`."

- [ ] **Step 5: Run every check**

Run:

```bash
cd ~/GitHub/wpcredits-program-manager && for f in bin/test-*.php; do r=$(php "$f" 2>&1 | tail -1); case "$r" in *PASS*) ;; *) echo "$f: $r";; esac; done; php bin/check-references.php | tail -1; bash bin/check-standards.sh 2>&1 | tail -3; bash bin/check-standards.sh --dead 2>&1 | tail -2; grep -rn $'\xe2\x80\x93\|\xe2\x80\x94' includes readme.txt docs/sections docs/plans/2026-09-04-semester-report-approval.md | head -3
```

Expected: no failing suite listed, references resolve, phpcs reports 0 errors (warnings may remain at the pre-existing 28), no dead annotations, no dash lines.

- [ ] **Step 6: Build the zip**

Run: `bash bin/build`
Expected: `version inside: 1.91.0`, one top-level folder, no `bin/` or `docs/` entries.

---

### Task 9: Deploy, verify live, mirror, notes

**Files:**
- Read: nothing new. Writes go to the live site, the mirror clone at `~/GitHub/Plugins/WPCredits-Tracker-mirror`, the vault note `~/Obsidian/WordPress Education/WordPress Education Dashboard/Institutions module.md`, and the memory file `wpcredits-program-manager-plugin.md`.

- [ ] **Step 1: Deploy**

```bash
scp ~/GitHub/wpcredits-program-manager.zip wpcredits-dashboard:/home/152004889/wpcredits-program-manager.zip && ssh wpcredits-dashboard "wp plugin install /home/152004889/wpcredits-program-manager.zip --force && wp plugin get wpcredits-program-manager --field=version"
```

Expected: `1.91.0`.

- [ ] **Step 2: Verify live, with WP-CLI as a manager**

```bash
ssh wpcredits-dashboard "wp --user=64274470 eval '
wp_set_current_user( 64274470 );
echo \"cron: \" . ( wp_next_scheduled( \"wpcpm_report_autodraft\" ) ? \"scheduled\" : \"MISSING\" ) . \"\n\";
echo \"state version: \" . get_option( \"wpcpm_report_state_version\" ) . \", since: \" . get_option( \"wpcpm_report_autodraft_since\" ) . \"\n\";
foreach ( get_posts( array( \"post_type\" => \"wpcpm_inst_report\", \"post_status\" => \"private\", \"posts_per_page\" => -1 ) ) as \$p ) { echo \$p->ID . \" \" . get_post_meta( \$p->ID, \"_wpcpm_report_state\", true ) . \" \" . WPCPM_Semester_Report::origin_of( \$p ) . \"\n\"; }
echo \"due: \" . count( WPCPM_Semester_Report::due( wp_date( \"Y-m-d\" ) ) ) . \", queue: \" . count( WPCPM_Semester_Report::queue() ) . \"\n\";
'"
```

Expected: the cron scheduled, state version 2, a since-date of today, every existing report reading `draft` or `approved`, and counts that match the Institutions screen.

- [ ] **Step 3: Verify the two dashboards render**

```bash
ssh wpcredits-dashboard "wp --user=64274470 eval 'wp_set_current_user( 64274470 ); \$_GET[\"wpcpm_institution_view\"] = get_option( \"wpcpm_test_institution_record\", \"\" ); ob_start(); WPCPM_Institutions_Dashboard::render( array() ); \$h = ob_get_clean(); echo strlen( \$h ) . \" bytes, approve form: \" . ( false !== strpos( \$h, \"wpcpm_report_approve\" ) || false !== strpos( \$h, \"Write the first draft\" ) ? \"yes\" : \"no\" ) . \"\n\";'"
```

Then sign in as the TEST institution representative in a browser and confirm the card shows the approved report with View and Download PDF, or the "being prepared" sentence, and no form. Record the result.

- [ ] **Step 4: Mirror**

```bash
rsync -a --delete --exclude '.git' --exclude '.DS_Store' --exclude 'node_modules' ~/GitHub/wpcredits-program-manager/ "$HOME/GitHub/Plugins/WPCredits-Tracker-mirror/Education/WordPress Education Dashboard/wpcredits-program-manager/" && cd ~/GitHub/Plugins/WPCredits-Tracker-mirror && grep -rIn -E "pat[A-Za-z0-9]{14}\.[a-f0-9]{20,}" "Education/WordPress Education Dashboard" | head -1; git add -A -- "Education/WordPress Education Dashboard" && git -c user.useConfigOnly=false commit -q -m "WordPress Education Dashboard: plugin 1.91.0, the semester report approval flow

The site drafts a report when a semester ends or a manager presses Draft
now, managers review and approve, the institution reads approved reports
and downloads the PDF. States draft and approved, final upgraded.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

Then, as a separate command: `cd ~/GitHub/Plugins/WPCredits-Tracker-mirror && git push -q origin trunk`.

- [ ] **Step 5: Notes**

Append a section `## 1.91.0: the semester report is the program's document (date)` to the vault note with: what shipped, the live verification lines, and what is left (the Administrator Dashboard reads `queue()`, `due()`, `approved_since()`). Update the plugin memory's last paragraph with one sentence naming 1.91.0 and the three readers.
