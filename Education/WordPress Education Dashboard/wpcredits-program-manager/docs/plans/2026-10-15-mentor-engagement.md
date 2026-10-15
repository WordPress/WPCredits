# Mentor engagement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Administrators see each mentor's sign-ins, visits, students, schedule, notes and calls on a new Engagement tab of the wp-admin Mentors screen and a Mentor engagement card on the Administrator Dashboard, and a read-only WP-CLI command feeds the biweekly #wpcredits-admins update.

**Architecture:** `WPCPM_Sign_Ins` records two user meta keys on every account (completed sign-in, first signed-in page view of the day) and seeds them once from the traces the site already holds. `WPCPM_Mentor_Engagement` reads the accounts, notes and calls with two SQL reads plus the user meta cache and computes every row and figure in one pure function, so the tab (`WPCPM_Mentor_Engagement_Table`), the dashboard card and the command all print the same numbers. Nothing else is stored.

**Tech Stack:** PHP 7.4+ WordPress plugin `wpcredits-program-manager` (prefix `WPCPM_`), WordPress list tables, WP-CLI, the plugin's own no-WordPress suites `bin/test-*.php` (run with `php bin/test-x.php`, print `ALL PASS`, exit 0), `bin/check-standards.sh`, the release script `~/Projects/wpcredits-release/release.py`.

**Spec:** `docs/specs/2026-10-15-mentor-engagement-design.md`. Read it before any task; it holds the owner's decisions D1 to D6 and every definition.

## Global Constraints

- Version **1.122.26** (or the next free 1.122 number when the build starts), bumped in the plugin header, `WPCPM_VERSION`, `readme.txt` Stable tag, a `= 1.122.26 =` changelog entry and `languages/wpcredits-program-manager.pot` (`bin/make-pot.sh`).
- No em dash and no en dash anywhere: code, comments, strings, docs, commit messages. Plain hyphens.
- Full product names: "Mentor Report Card", "Student Report Card", never bare "Report Card".
- "Administrators" for the people who manage the program, "Administrator" for one of them, in every new string.
- US English in every interface string (`bin/check-spelling.php` runs in `check-standards.sh`).
- PHP 7.4 compatible: `array()` syntax, no `match`, no named arguments, no typed properties.
- The reader reads counts and dates only: never a note's text, a call's topic, a student's name.
- The command and the tab never write. The only writes are `WPCPM_Sign_Ins`'s two keys, its two options and the one-off import.
- Commit after each task on branch `feature/mentor-engagement` from `main`; each commit message ends with the attribution line the session's instructions name.
- Nothing is deployed, mirrored or posted without the owner's explicit yes for that step. Read the live version first. Every test email goes to the owner's one test address only (none is expected in this release).
- `docs/` is mirrored to the public `WordPress/WPCredits`: the repo copies of the spec and this plan leave out the 15 Oct reference figures and any name.

## Review Focus

1. **Two tables on one screen.** The Accounts and Engagement tabs share one screen id, so WordPress keeps one hidden-columns list and Screen Options per screen: the Engagement table's column keys and its rows-per-page option must not be the Accounts table's (`students`, `past`, `status`, `wpcpm_mentors_per_page`). Pinned in Task 5, step 1.
2. **A call or note whose mentor is gone.** A call whose `_wpcpm_call_mentor` is empty or names a deleted account, and a note by a deleted author, give no row and no PHP warning, and a held call still counts in the site-wide call figures. Pinned in Task 3, step 1.
3. **Malformed meta.** `session_tokens` stored as a string, a token without `login`, a two-factor timestamp that is not a number, an empty `_wpcpm_call_start`: each reads as 0, and neither the seed nor the reader fails. Pinned in Task 2, step 1 and Task 3, step 1.
4. **The day is the site's day.** Two visits at 23:30 and 00:30 site time are two days even when they fall on one UTC day, and two visits on one site day are one write even when they straddle UTC midnight. Pinned in Task 1, step 1.
5. **An empty list and a hostile search.** A view or a search that matches nothing prints "No mentors match." with a count of 0, and a search string holding quotes or tags is printed escaped. Pinned in Task 5, step 1.

---

## Before Task 1: the branch and the documents

- [ ] **Step 1: Branch from main** (a worktree if another session is working in the checkout; see superpowers:using-git-worktrees)

```bash
cd ~/GitHub/wpcredits-program-manager
git checkout main
git pull --ff-only
git checkout -b feature/mentor-engagement
```

- [ ] **Step 2: Put the spec and this plan into the repo, without the private figures**

Copy the vault spec to `docs/specs/2026-10-15-mentor-engagement-design.md` and this plan to `docs/plans/2026-10-15-mentor-engagement.md`. In both copies delete the section "Reference figures, 15 Oct 2026 (private; not for the repo copy)", the "15 Oct" columns of the summary and views tables, and every figure taken from the 15 Oct probes. Replace Obsidian wikilinks with the plain names they hold.

- [ ] **Step 3: Check for dashes and commit**

```bash
grep -n -P "\x{2014}|\x{2013}" docs/specs/2026-10-15-mentor-engagement-design.md docs/plans/2026-10-15-mentor-engagement.md || echo clean
git add docs/specs/2026-10-15-mentor-engagement-design.md docs/plans/2026-10-15-mentor-engagement.md
git commit -m "Mentor engagement: the design and the plan (1.122.26)"
```

Expected: `clean`.

---

### Task 1: Record sign-ins and visits

**Files:**
- Create: `includes/class-wpcpm-sign-ins.php`
- Modify: `wpcredits-program-manager.php` (require after `class-wpcpm-two-factor.php`, line 65; `WPCPM_Sign_Ins::init();` after `WPCPM_Two_Factor::init();`, line 213)
- Test: `bin/test-sign-ins.php` (create)

**Interfaces:**
- Consumes: `WPCPM_Two_Factor::available()`; `Two_Factor_Core::is_user_using_two_factor( $user_id )` (Two Factor 0.16.0).
- Produces:
  - `WPCPM_Sign_Ins::META_SIGN_IN` = `'wpcpm_last_sign_in'`, `META_VISIT` = `'wpcpm_last_visit'` (both Unix times as int).
  - `WPCPM_Sign_Ins::record_sign_in( int $user_id, int $time = 0 ): void`: writes both keys at most once per request per account.
  - `WPCPM_Sign_Ins::record_visit( int $user_id, int $time ): void`: writes `META_VISIT` only when the stored value's site day differs from `$time`'s.
  - `WPCPM_Sign_Ins::init()`, `on_wp_login( $user_login, $user )`, `on_two_factor( $user )`, `on_visit()`, `for_tests()` (resets the per-request guard; test-only).

- [ ] **Step 1: Write the failing suite**

Create `bin/test-sign-ins.php`:

```php
<?php
/**
 * When an account last signed in, and last used the site (1.122.26).
 *
 * What this pins, and why:
 *
 * - A sign-in is recorded when it completes. The Two Factor plugin (0.16.0) hooks `wp_login` at
 *   PHP_INT_MAX and exits there for an account that owes a code, so every other `wp_login`
 *   callback runs after the password and before the code: an account that owes one is recorded on
 *   `two_factor_user_authenticated`, never on `wp_login`, and an abandoned code step records nothing.
 * - One request records a sign-in once, so a later Two Factor that fired `wp_login` again after the
 *   code would still write one value.
 * - A visit is the first signed-in page view of the site's day: one write a day, the day read in the
 *   site's time zone, none for AJAX (the heartbeat), cron or REST.
 *
 * Run from the plugin root:  php bin/test-sign-ins.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['umeta']  = array();
$GLOBALS['opts']   = array();
$GLOBALS['hooks']  = array();
$GLOBALS['uid']    = 0;
$GLOBALS['ajax']   = false;
$GLOBALS['cron']   = false;
$GLOBALS['offset'] = 0;
$GLOBALS['using']  = array();
$GLOBALS['can']    = false;
$GLOBALS['writes'] = 0;

class WP_User {
	public $ID = 0;
	public function __construct( $id = 0 ) { $this->ID = (int) $id; }
}

function add_action( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][] = array( $hook, is_array( $cb ) ? $cb[1] : (string) $cb, $priority, $args ); }
function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['umeta'][ $id ][ $key ] ) ? $GLOBALS['umeta'][ $id ][ $key ] : ''; }
function update_user_meta( $id, $key, $value ) { ++$GLOBALS['writes']; $GLOBALS['umeta'][ $id ][ $key ] = $value; return true; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_doing_ajax() { return $GLOBALS['ajax']; }
function wp_doing_cron() { return $GLOBALS['cron']; }
// The site's clock: UTC plus the site's offset, as core's wp_date() reads the site's time zone.
function wp_date( $format, $timestamp = null ) { return gmdate( $format, (int) $timestamp + $GLOBALS['offset'] ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function current_user_can( $cap ) { return $GLOBALS['can']; }

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-two-factor.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-sign-ins.php';

$fail = 0;
function ck( $label, $actual, $expected = true ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) {
		++$fail;
	}
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		echo '       expected: ' . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
	}
}
function meta( $id, $key ) { return isset( $GLOBALS['umeta'][ $id ][ $key ] ) ? $GLOBALS['umeta'][ $id ][ $key ] : null; }
function fresh() {
	$GLOBALS['umeta']  = array();
	$GLOBALS['writes'] = 0;
	WPCPM_Sign_Ins::for_tests();
}

$S = WPCPM_Sign_Ins::META_SIGN_IN;
$V = WPCPM_Sign_Ins::META_VISIT;
$T = gmmktime( 10, 0, 0, 10, 15, 2026 );

echo "=== The hooks ===\n";

WPCPM_Sign_Ins::init();
ck( 'a sign-in is heard on wp_login with both arguments and on two_factor_user_authenticated, a visit on template_redirect and admin_init',
	array_values( array_filter( $GLOBALS['hooks'], function ( $h ) { return 'maybe_seed' !== $h[1]; } ) ),
	array(
		array( 'wp_login', 'on_wp_login', 10, 2 ),
		array( 'two_factor_user_authenticated', 'on_two_factor', 10, 1 ),
		array( 'template_redirect', 'on_visit', 10, 1 ),
		array( 'admin_init', 'on_visit', 10, 1 ),
	) );

echo "\n=== Without the Two Factor plugin ===\n";

fresh();
$before = time();
WPCPM_Sign_Ins::on_wp_login( 'mentor', new WP_User( 30 ) );
ck( 'wp_login records the sign-in as an int, now', is_int( meta( 30, $S ) ) && meta( 30, $S ) >= $before && meta( 30, $S ) <= time() );
ck( 'and the visit with it, at the same moment', meta( 30, $V ), meta( 30, $S ) );

fresh();
WPCPM_Sign_Ins::record_sign_in( 30, $T );
WPCPM_Sign_Ins::record_sign_in( 30, $T + 60 );
ck( 'one request records a sign-in once: two writes, the sign-in and the visit', array( $GLOBALS['writes'], meta( 30, $S ) ), array( 2, $T ) );

fresh();
WPCPM_Sign_Ins::record_sign_in( 0, $T );
WPCPM_Sign_Ins::on_wp_login( 'nobody', 'not a user' );
ck( 'no account, nothing written', $GLOBALS['writes'], 0 );

echo "\n=== With the Two Factor plugin ===\n";

// Declared here, not at the top: PHP hoists class declarations, and the section above must run
// without it (the same reason bin/test-two-factor.php declares its stand-in with eval).
eval(
	'class Two_Factor_Core {
		public static function is_user_using_two_factor( $user_id ) {
			return ! empty( $GLOBALS["using"][ (int) $user_id ] );
		}
	}'
);

$GLOBALS['using'][40] = true;
fresh();
WPCPM_Sign_Ins::on_wp_login( 'owes-a-code', new WP_User( 40 ) );
ck( 'an account that owes a code records nothing on wp_login: the code step has not happened', array( meta( 40, $S ), meta( 40, $V ) ), array( null, null ) );
WPCPM_Sign_Ins::on_two_factor( new WP_User( 40 ) );
ck( 'and is recorded once the code is accepted', array( is_int( meta( 40, $S ) ), meta( 40, $V ) === meta( 40, $S ) ), array( true, true ) );
$writes = $GLOBALS['writes'];
WPCPM_Sign_Ins::on_wp_login( 'owes-a-code', new WP_User( 40 ) );
ck( 'a wp_login fired again after the code in the same request writes nothing more', $GLOBALS['writes'], $writes );

fresh();
WPCPM_Sign_Ins::on_wp_login( 'no-code', new WP_User( 41 ) );
ck( 'an account that uses no second factor is recorded on wp_login', is_int( meta( 41, $S ) ) );

fresh();
WPCPM_Sign_Ins::on_two_factor( 'not a user' );
ck( 'two_factor_user_authenticated without an account writes nothing', $GLOBALS['writes'], 0 );

echo "\n=== Visits ===\n";

fresh();
WPCPM_Sign_Ins::record_visit( 30, $T );
WPCPM_Sign_Ins::record_visit( 30, $T + 3600 );
ck( 'the first view of the day is kept: a later one that day writes nothing', array( meta( 30, $V ), $GLOBALS['writes'] ), array( $T, 1 ) );
WPCPM_Sign_Ins::record_visit( 30, $T + DAY_IN_SECONDS );
ck( 'the next day writes the next day', meta( 30, $V ), $T + DAY_IN_SECONDS );

// Review Focus 4: the day is the site's day.
$GLOBALS['offset'] = 2 * 3600;
fresh();
$late  = gmmktime( 21, 30, 0, 10, 15, 2026 ); // 23:30 site time.
$early = gmmktime( 22, 30, 0, 10, 15, 2026 ); // 00:30 site time, the 16th; the same UTC day.
WPCPM_Sign_Ins::record_visit( 30, $late );
WPCPM_Sign_Ins::record_visit( 30, $early );
ck( 'two site days on one UTC day are two writes', array( $GLOBALS['writes'], meta( 30, $V ) ), array( 2, $early ) );

$GLOBALS['offset'] = -5 * 3600;
fresh();
WPCPM_Sign_Ins::record_visit( 30, gmmktime( 23, 0, 0, 10, 15, 2026 ) ); // 18:00 site time, the 15th; UTC the 15th.
WPCPM_Sign_Ins::record_visit( 30, gmmktime( 1, 0, 0, 10, 16, 2026 ) );  // 20:00 site time, the 15th; UTC the 16th.
ck( 'one site day across UTC midnight is one write', $GLOBALS['writes'], 1 );
$GLOBALS['offset'] = 0;

fresh();
WPCPM_Sign_Ins::record_visit( 30, $T );
WPCPM_Sign_Ins::record_sign_in( 30, $T + 600 );
ck( 'a sign-in on a day already visited keeps the first view and records the sign-in', array( meta( 30, $V ), meta( 30, $S ) ), array( $T, $T + 600 ) );

fresh();
$GLOBALS['umeta'][30][ $V ] = 'not a time';
WPCPM_Sign_Ins::record_visit( 30, $T );
ck( 'a stored value that is not a time is replaced, not trusted', meta( 30, $V ), $T );

echo "\n=== Which requests are visits ===\n";

fresh();
$GLOBALS['uid'] = 0;
WPCPM_Sign_Ins::on_visit();
ck( 'nobody signed in, nothing written', $GLOBALS['writes'], 0 );

$GLOBALS['uid']  = 30;
$GLOBALS['ajax'] = true;
WPCPM_Sign_Ins::on_visit();
ck( 'an AJAX request is not a visit (the wp-admin heartbeat)', $GLOBALS['writes'], 0 );
$GLOBALS['ajax'] = false;

$GLOBALS['cron'] = true;
WPCPM_Sign_Ins::on_visit();
ck( 'a cron request is not a visit', $GLOBALS['writes'], 0 );
$GLOBALS['cron'] = false;

WPCPM_Sign_Ins::on_visit();
ck( 'a signed-in page view is', is_int( meta( 30, $V ) ) );
WPCPM_Sign_Ins::on_visit();
ck( 'and a second one that day writes nothing more', $GLOBALS['writes'], 1 );

// Last, because a constant cannot be taken back.
fresh();
define( 'REST_REQUEST', true );
WPCPM_Sign_Ins::on_visit();
ck( 'a REST request is not a visit', $GLOBALS['writes'], 0 );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run it to see it fail**

Run: `php bin/test-sign-ins.php`
Expected: a fatal "Failed opening required '.../includes/class-wpcpm-sign-ins.php'".

- [ ] **Step 3: Write the class**

Create `includes/class-wpcpm-sign-ins.php`:

```php
<?php
/**
 * When each account last signed in, and last used the site.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Two dates on every account: the last completed sign-in and the first signed-in page view of the
 * latest day. The Engagement tab, the Administrator Dashboard's Mentor engagement card and
 * `wp wpcredits mentor_engagement` read them (1.122.26); WordPress itself keeps neither.
 *
 * **A sign-in counts once it completes.** The Two Factor plugin (0.16.0 on the live site) hooks
 * `wp_login` at PHP_INT_MAX and, for an account that owes a code, destroys the password session,
 * prints the code form and exits there. So every other `wp_login` callback, this one included,
 * runs after the password and before the code, whether or not the code is ever entered. An account
 * that owes a code is therefore recorded on `two_factor_user_authenticated`, which the plugin fires
 * once the code is accepted, and never on `wp_login`. Nothing in that plugin fires `wp_login` a
 * second time; one request records one sign-in all the same, should a later release do so.
 *
 * **A visit is a day, not a request.** The first signed-in page view of the site's day is written,
 * and nothing more until the day changes, so the check costs no query (WordPress has loaded the
 * account's meta to read its roles) and at most one write a day. AJAX (the wp-admin heartbeat),
 * cron, REST, XML-RPC and WP-CLI are not visits.
 *
 * Every account is recorded, not only mentors (the owner's decision D4 of 15 October 2026), and
 * any future way of signing in calls `record_sign_in()`.
 */
final class WPCPM_Sign_Ins {
	/** User meta: Unix time the last sign-in completed. */
	const META_SIGN_IN = 'wpcpm_last_sign_in';

	/** User meta: Unix time of the first signed-in page view of the latest day. */
	const META_VISIT = 'wpcpm_last_visit';

	/**
	 * Accounts whose sign-in this request has recorded.
	 *
	 * @var array<int, true>
	 */
	private static $recorded = array();

	/**
	 * Hooks. Called from `wpcpm_bootstrap()`.
	 */
	public static function init() {
		add_action( 'wp_login', array( __CLASS__, 'on_wp_login' ), 10, 2 );
		add_action( 'two_factor_user_authenticated', array( __CLASS__, 'on_two_factor' ), 10, 1 );
		add_action( 'template_redirect', array( __CLASS__, 'on_visit' ), 10, 1 );
		add_action( 'admin_init', array( __CLASS__, 'on_visit' ), 10, 1 );
	}

	/**
	 * A password accepted: a sign-in, unless the account still owes a code.
	 *
	 * @param string  $user_login The username.
	 * @param WP_User $user       The account.
	 */
	public static function on_wp_login( $user_login, $user ) {
		unset( $user_login );

		if ( ! $user instanceof WP_User || self::code_owed( (int) $user->ID ) ) {
			return;
		}

		self::record_sign_in( (int) $user->ID );
	}

	/**
	 * A code accepted: the sign-in completes here.
	 *
	 * @param WP_User $user The account.
	 */
	public static function on_two_factor( $user ) {
		if ( $user instanceof WP_User ) {
			self::record_sign_in( (int) $user->ID );
		}
	}

	/**
	 * Record a completed sign-in, and the visit it is.
	 *
	 * @param int $user_id The account.
	 * @param int $time    When; 0 for now.
	 */
	public static function record_sign_in( $user_id, $time = 0 ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 || isset( self::$recorded[ $user_id ] ) ) {
			return;
		}

		self::$recorded[ $user_id ] = true;

		$time = (int) $time > 0 ? (int) $time : time();

		update_user_meta( $user_id, self::META_SIGN_IN, $time );
		self::record_visit( $user_id, $time );
	}

	/**
	 * A signed-in page view, front end (`template_redirect`) or wp-admin (`admin_init`).
	 */
	public static function on_visit() {
		$user_id = (int) get_current_user_id();

		if ( $user_id <= 0 || ! self::counts_as_visit() ) {
			return;
		}

		self::record_visit( $user_id, time() );
	}

	/**
	 * Write the visit when the stored one is from another site day, or is not a time at all.
	 *
	 * @param int $user_id The account.
	 * @param int $time    When.
	 */
	public static function record_visit( $user_id, $time ) {
		$stored = get_user_meta( (int) $user_id, self::META_VISIT, true );
		$last   = is_numeric( $stored ) ? (int) $stored : 0;

		if ( $last > 0 && wp_date( 'Y-m-d', $last ) === wp_date( 'Y-m-d', (int) $time ) ) {
			return;
		}

		update_user_meta( (int) $user_id, self::META_VISIT, (int) $time );
	}

	/**
	 * Forget which sign-ins this request recorded. Test-only: nothing in the plugin calls it.
	 */
	public static function for_tests() {
		self::$recorded = array();
	}

	/**
	 * Whether the account still owes Two Factor's code, as Two Factor itself decides it: through
	 * the providers the account has, which `WPCPM_Two_Factor::enabled_providers()` fills by role.
	 *
	 * @param int $user_id The account.
	 * @return bool
	 */
	private static function code_owed( $user_id ) {
		return WPCPM_Two_Factor::available() && Two_Factor_Core::is_user_using_two_factor( $user_id );
	}

	/**
	 * Whether this request is a person looking at a page.
	 *
	 * @return bool
	 */
	private static function counts_as_visit() {
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		foreach ( array( 'REST_REQUEST', 'XMLRPC_REQUEST', 'WP_CLI' ) as $constant ) {
			if ( defined( $constant ) && constant( $constant ) ) {
				return false;
			}
		}

		return true;
	}
}
```

- [ ] **Step 4: Load and boot it**

In `wpcredits-program-manager.php`, after `require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-two-factor.php';` add:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-sign-ins.php';
```

and in `wpcpm_bootstrap()`, after `WPCPM_Two_Factor::init();`:

```php
	// When each account last signed in and last used the site (1.122.26), on every request.
	WPCPM_Sign_Ins::init();
```

- [ ] **Step 5: Run the suite and the two neighbors**

Run: `php bin/test-sign-ins.php && php bin/test-two-factor.php && php -l includes/class-wpcpm-sign-ins.php`
Expected: `ALL PASS` twice, `No syntax errors detected`.

- [ ] **Step 6: Commit**

```bash
git add includes/class-wpcpm-sign-ins.php wpcredits-program-manager.php bin/test-sign-ins.php
git commit -m "1.122.26: the site records when each account last signed in and last visited, a two-factor sign-in once its code is accepted"
```

---

### Task 2: Seed the history once, and clean up on uninstall

**Files:**
- Modify: `includes/class-wpcpm-sign-ins.php` (constants, `maybe_seed()`, `seed()`, `latest_trace()`, one hook in `init()`)
- Modify: `uninstall.php` (require the class; delete two keys and two options)
- Test: `bin/test-sign-ins.php` (add a section before the REST check), `bin/test-uninstall.php`

**Interfaces:**
- Consumes: Task 1's class.
- Produces:
  - `WPCPM_Sign_Ins::OPT_VERSION` = `'wpcpm_sign_ins_version'`, `OPT_SINCE` = `'wpcpm_sign_ins_since'`, `SCHEMA_VERSION` = 1, `SEED_BATCH` = 200.
  - `WPCPM_Sign_Ins::maybe_seed(): void` (on `admin_init`), `seed(): int` (accounts given a sign-in), `latest_trace( int $user_id ): int`.
  - `WPCPM_Sign_Ins::since(): int`: the seed's time, 0 before it ran. Task 5 prints it.

- [ ] **Step 1: Add the failing checks**

In `bin/test-sign-ins.php`, add to the stand-ins after `current_user_can()`:

```php
$GLOBALS['accounts']  = array();
$GLOBALS['batches']   = array();
$GLOBALS['primed']    = array();
function get_users( $args = array() ) {
	$GLOBALS['batches'][] = $args;
	$ids = $GLOBALS['accounts'];
	sort( $ids );
	return array_slice( $ids, ( $args['paged'] - 1 ) * $args['number'], $args['number'] );
}
function update_meta_cache( $type, $ids ) { $GLOBALS['primed'][] = array( $type, count( $ids ) ); return array(); }
```

(the hooks check of Task 1 already leaves `maybe_seed` out), then add this section before `// Last, because a constant cannot be taken back.`:

```php
echo "\n=== The seed: what the site already held ===\n";

$tok = 'session_tokens';
$app = '_two_factor_totp_last_successful_login';
$code = '_two_factor_email_token_timestamp';

fresh();
$GLOBALS['umeta'][50] = array( $tok => array( 'h1' => array( 'login' => 100, 'expiration' => 999 ), 'h2' => array( 'login' => 300, 'expiration' => 999 ) ), $app => 200, $code => '250' );
ck( 'the latest of the session sign-ins, the last app code and the last emailed code', WPCPM_Sign_Ins::latest_trace( 50 ), 300 );
$GLOBALS['umeta'][51] = array( $app => '400', $code => 350 );
ck( 'with no session, the codes', WPCPM_Sign_Ins::latest_trace( 51 ), 400 );
// Review Focus 3.
$GLOBALS['umeta'][52] = array( $tok => 'garbage', $app => 'not a time', $code => 120 );
ck( 'a session list that is not a list and a stamp that is not a number read as 0', WPCPM_Sign_Ins::latest_trace( 52 ), 120 );
$GLOBALS['umeta'][53] = array( $tok => array( 'h' => array( 'expiration' => 999 ), 'x' => 'bad' ) );
ck( 'a session without a sign-in time, or not a session at all, reads as 0', WPCPM_Sign_Ins::latest_trace( 53 ), 0 );
ck( 'an account with nothing reads 0', WPCPM_Sign_Ins::latest_trace( 54 ), 0 );

fresh();
$GLOBALS['batches'] = array();
$GLOBALS['primed']  = array();
$GLOBALS['accounts'] = range( 1, 450 );
$GLOBALS['umeta'][10] = array( $S => 500, $app => 900 );
$GLOBALS['umeta'][11] = array( $app => 900 );
$GLOBALS['umeta'][12] = array( $code => 700 );
ck( 'the seed gives a sign-in to the two accounts that had a trace and none', WPCPM_Sign_Ins::seed(), 2 );
ck( 'an account that already had a sign-in keeps it, even with a later trace', meta( 10, $S ), 500 );
ck( 'the others take their latest trace, as an int', array( meta( 11, $S ), meta( 12, $S ) ), array( 900, 700 ) );
ck( 'an account with no trace stays empty, and nobody is given a visit', array( meta( 13, $S ), meta( 11, $V ), meta( 12, $V ) ), array( null, null, null ) );
ck( 'the accounts are read 200 at a time, by ID, three pages for 450', array_map( function ( $a ) { return array( $a['number'], $a['paged'], $a['orderby'], $a['fields'] ); }, $GLOBALS['batches'] ), array( array( 200, 1, 'ID', 'ID' ), array( 200, 2, 'ID', 'ID' ), array( 200, 3, 'ID', 'ID' ) ) );
ck( 'each page\'s meta is read in one go', $GLOBALS['primed'], array( array( 'user', 200 ), array( 'user', 200 ), array( 'user', 50 ) ) );

echo "\n=== The seed runs once, for an Administrator ===\n";

fresh();
$GLOBALS['opts']     = array();
$GLOBALS['batches']  = array();
$GLOBALS['accounts'] = array( 11 );
$GLOBALS['umeta'][11] = array( $app => 900 );
$GLOBALS['can'] = false;
WPCPM_Sign_Ins::maybe_seed();
ck( 'not for someone who does not manage the program', array( $GLOBALS['batches'], get_option( WPCPM_Sign_Ins::OPT_VERSION ) ), array( array(), false ) );
$GLOBALS['can'] = true;
WPCPM_Sign_Ins::maybe_seed();
ck( 'for an Administrator: the version and the time are written, and the seed ran', array( get_option( WPCPM_Sign_Ins::OPT_VERSION ), is_int( get_option( WPCPM_Sign_Ins::OPT_SINCE ) ), meta( 11, $S ) ), array( 1, true, 900 ) );
ck( 'since() reads the time', WPCPM_Sign_Ins::since(), get_option( WPCPM_Sign_Ins::OPT_SINCE ) );
$GLOBALS['batches'] = array();
WPCPM_Sign_Ins::maybe_seed();
ck( 'and never again', $GLOBALS['batches'], array() );
ck( 'init() hooks it on admin_init', in_array( array( 'admin_init', 'maybe_seed', 10, 1 ), $GLOBALS['hooks'], true ) );
```

In `bin/test-uninstall.php`, add to `$named_options` (line 620):

```php
	'wpcpm_sign_ins_version',              // WPCPM_Sign_Ins::OPT_VERSION.
	'wpcpm_sign_ins_since',                // WPCPM_Sign_Ins::OPT_SINCE.
```

and to the student's meta fixture (line 748), before `'nickname'`:

```php
		'wpcpm_last_sign_in'        => 1792000000,          // WPCPM_Sign_Ins::META_SIGN_IN.
		'wpcpm_last_visit'          => 1792000000,          // WPCPM_Sign_Ins::META_VISIT.
```

- [ ] **Step 2: Run both to see them fail**

Run: `php bin/test-sign-ins.php; php bin/test-uninstall.php`
Expected: test-sign-ins fails with "Call to undefined method WPCPM_Sign_Ins::latest_trace()"; test-uninstall FAILs "every option the file names goes" and "the user meta the file names goes".

- [ ] **Step 3: Write the seed**

In `includes/class-wpcpm-sign-ins.php` add the constants after `META_VISIT`:

```php
	/** Option: the seed's schema version; the seed runs while it is below SCHEMA_VERSION. */
	const OPT_VERSION = 'wpcpm_sign_ins_version';

	/** Option: when the seed ran. A sign-in before it is approximate. */
	const OPT_SINCE = 'wpcpm_sign_ins_since';

	/** Bumped when the seed has to run again. */
	const SCHEMA_VERSION = 1;

	/** Accounts the seed reads at a time. */
	const SEED_BATCH = 200;

	/** The user meta the seed reads: Two Factor's last accepted app code and last emailed code. */
	const TRACE_APP  = '_two_factor_totp_last_successful_login';
	const TRACE_CODE = '_two_factor_email_token_timestamp';
```

add to `init()`:

```php
		add_action( 'admin_init', array( __CLASS__, 'maybe_seed' ), 10, 1 );
```

and these methods after `record_visit()`:

```php
	/**
	 * Give every account the sign-in the site can already tell, once, on an Administrator's first
	 * wp-admin page after the update (the owner's decision D3 of 15 October 2026).
	 *
	 * The version is written before the seed runs, as `WPCPM_Administrators_Dashboard::
	 * maybe_rename_page()` writes its own: a seed that failed halfway must not run again on every
	 * request of every Administrator. The accounts it missed keep no sign-in until their next one.
	 */
	public static function maybe_seed() {
		if ( (int) get_option( self::OPT_VERSION ) >= self::SCHEMA_VERSION || ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		update_option( self::OPT_VERSION, self::SCHEMA_VERSION );
		update_option( self::OPT_SINCE, time(), false );

		self::seed();
	}

	/**
	 * Every account without a sign-in takes its latest trace; an account with one keeps it. No visit
	 * is written: visits start at the release.
	 *
	 * @return int How many accounts were given a sign-in.
	 */
	public static function seed() {
		$given = 0;
		$paged = 1;

		do {
			$ids = array_map(
				'intval',
				(array) get_users(
					array(
						'fields'  => 'ID',
						'number'  => self::SEED_BATCH,
						'paged'   => $paged,
						'orderby' => 'ID',
						'order'   => 'ASC',
					)
				)
			);

			if ( $ids ) {
				update_meta_cache( 'user', $ids );
			}

			foreach ( $ids as $user_id ) {
				$stored = get_user_meta( $user_id, self::META_SIGN_IN, true );

				if ( is_numeric( $stored ) && (int) $stored > 0 ) {
					continue;
				}

				$latest = self::latest_trace( $user_id );

				if ( $latest > 0 ) {
					update_user_meta( $user_id, self::META_SIGN_IN, $latest );
					++$given;
				}
			}

			++$paged;
		} while ( count( $ids ) === self::SEED_BATCH );

		return $given;
	}

	/**
	 * The latest sign-in the site can tell for an account: the `login` time of each session
	 * WordPress still holds, Two Factor's last accepted app code, and its last emailed code (sent at
	 * the password step, so an approximation). Anything that is not a number reads as 0.
	 *
	 * @param int $user_id The account.
	 * @return int Unix time, or 0.
	 */
	public static function latest_trace( $user_id ) {
		$times  = array( 0 );
		$tokens = get_user_meta( (int) $user_id, 'session_tokens', true );

		foreach ( is_array( $tokens ) ? $tokens : array() as $token ) {
			if ( is_array( $token ) && isset( $token['login'] ) && is_numeric( $token['login'] ) ) {
				$times[] = (int) $token['login'];
			}
		}

		foreach ( array( self::TRACE_APP, self::TRACE_CODE ) as $key ) {
			$value = get_user_meta( (int) $user_id, $key, true );

			if ( is_numeric( $value ) ) {
				$times[] = (int) $value;
			}
		}

		return max( $times );
	}

	/**
	 * When the seed ran: sign-ins before it are approximate. 0 before it has run.
	 *
	 * @return int
	 */
	public static function since() {
		return (int) get_option( self::OPT_SINCE, 0 );
	}
```

- [ ] **Step 4: Clean up on uninstall**

In `uninstall.php`, after `require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-privacy-guard.php';` add:

```php
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-sign-ins.php';
```

and after `delete_option( WPCPM_Privacy_Guard::OPT_VERSION );`:

```php
// When each account last signed in and visited (1.122.26), and the seed's two options.
delete_metadata( 'user', 0, WPCPM_Sign_Ins::META_SIGN_IN, '', true );
delete_metadata( 'user', 0, WPCPM_Sign_Ins::META_VISIT, '', true );
delete_option( WPCPM_Sign_Ins::OPT_VERSION );
delete_option( WPCPM_Sign_Ins::OPT_SINCE );
```

- [ ] **Step 5: Run the suites**

Run: `php bin/test-sign-ins.php && php bin/test-uninstall.php`
Expected: `ALL PASS` twice. If test-uninstall's last section (names compared with the classes' own) asks for the new options' class constants, add them there in the shape its other rows use.

- [ ] **Step 6: Commit**

```bash
git add includes/class-wpcpm-sign-ins.php uninstall.php bin/test-sign-ins.php bin/test-uninstall.php
git commit -m "1.122.26: every account takes the sign-in the site can already tell, once; uninstall removes both dates"
```

---

### Task 3: The engagement rules

**Files:**
- Create: `includes/modules/class-wpcpm-mentor-engagement.php`
- Modify: `wpcredits-program-manager.php` (require after `class-wpcpm-group-sessions.php`, line 91)
- Test: `bin/test-mentor-engagement.php` (create)

**Interfaces:**
- Consumes: `WPCPM_Roles::ROLE_ADMIN`.
- Produces (all static on `WPCPM_Mentor_Engagement`):
  - `compute( array $accounts, array $notes, array $calls, int $now ): array{rows: array[], summary: array}`.
  - Account shape (input): `id` int, `name`, `login`, `email` string, `roles` string[], `active` bool, `students_now`, `students_past`, `last_sign_in`, `last_visit` int, `schedule_saved`, `weekly_hours`, `meeting_link` bool.
  - Note shape: `id`, `author`, `created` int, `records` string[], `session` int.
  - Call shape: `id`, `author`, `mentor`, `start`, `cancelled_by` int, `status` string, `group` bool, `records` string[].
  - Row keys: the input's identity and meta keys plus `staff`, `last_seen`, `seen_7`, `seen_30`, `notes`, `notes_30`, `last_note`, `calls_booked`, `calls_held`, `calls_upcoming`, `calls_noted`, `cancelled_by_mentor`, `sessions_created`, `sessions_held`, `sessions_upcoming`, `sessions_noted`, `acted`.
  - Summary keys: `active`, `signed_in`, `seen_30`, `seen_7`, `never`, `acted`, `idle`, `with_students`, `with_students_hours`, `with_students_link`, `with_students_notes`, `with_students_seen_30`, `calls_held`, `calls_upcoming`, `calls_noted`, `sessions_held`, `sessions_upcoming`, `sessions_noted`, `staff` (all int).
  - View constants `VIEW_ALL` `'all'`, `VIEW_ACTIVE` `'active'`, `VIEW_STUDENTS` `'students'`, `VIEW_NEVER` `'never'`, `VIEW_QUIET` `'quiet'`, `VIEW_IDLE` `'idle'`, `VIEW_NO_HOURS` `'no-hours'`, `VIEW_STAFF` `'staff'`; `views(): array<string,string>`; `in_view( array $row, string $view ): bool`.
  - `sorted( array $rows, string $orderby = '', string $order = '' ): array`; `matches( array $row, string $search ): bool`.
  - `tiles( array $summary ): array[]` each `name`, `n`, `line`; `apart( array $summary ): string`; `schedule_label( array $row ): string`; `export_row( array $row ): array`; `EXPORT_FIELDS`.

- [ ] **Step 1: Write the failing suite**

Create `bin/test-mentor-engagement.php`:

```php
<?php
/**
 * Mentor engagement (1.122.26): every row and figure the Engagement tab, the Administrator
 * Dashboard's card and `wp wpcredits mentor_engagement` print, from plain arrays.
 *
 * The rules are the ones `analyze.py` used for the owner's question of 15 October 2026, so the
 * first numbers the site prints match that answer: who counts as an active mentor (Airtable says
 * Active, not an Administrator), when a held call counts as noted (a note by its mentor about one
 * of its students, dated no earlier than a day before the call), what a program action is (a note,
 * a saved schedule, a group session created, a call cancelled by the mentor; never a student's
 * booking), and that the call figures count every call on the site.
 *
 * Run from the plugin root:  php bin/test-mentor-engagement.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );

function __( $s, $d = null ) { return $s; }
function _n( $one, $many, $n, $d = null ) { return 1 === (int) $n ? $one : $many; }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function gmdate_utc( $t ) { return gmdate( 'Y-m-d H:i', $t ); }

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-engagement.php';

$fail = 0;
function ck( $label, $actual, $expected = true ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) {
		++$fail;
	}
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		echo '       expected: ' . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
	}
}

$NOW = gmmktime( 12, 0, 0, 10, 15, 2026 );
$DAY = DAY_IN_SECONDS;

function account( $id, array $more = array() ) {
	return array_merge(
		array(
			'id'             => $id,
			'name'           => 'Mentor ' . $id,
			'login'          => 'mentor' . $id,
			'email'          => 'mentor' . $id . '@example.test',
			'roles'          => array( 'wpcpm_mentor' ),
			'active'         => true,
			'students_now'   => 0,
			'students_past'  => 0,
			'last_sign_in'   => 0,
			'last_visit'     => 0,
			'schedule_saved' => false,
			'weekly_hours'   => false,
			'meeting_link'   => false,
		),
		$more
	);
}
function note( $id, $author, $created, array $records = array( 'recSTUDENTA' ), $session = 0 ) {
	return array( 'id' => $id, 'author' => $author, 'created' => $created, 'records' => $records, 'session' => $session );
}
function call( $id, $mentor, $start, array $more = array() ) {
	return array_merge(
		array( 'id' => $id, 'author' => 900, 'mentor' => $mentor, 'start' => $start, 'cancelled_by' => 0, 'status' => 'private', 'group' => false, 'records' => array( 'recSTUDENTA' ) ),
		$more
	);
}
function row_of( array $out, $id ) {
	foreach ( $out['rows'] as $row ) {
		if ( $id === $row['id'] ) {
			return $row;
		}
	}
	return null;
}

$accounts = array(
	// 1: active, students, signed in 3 days ago, hours and a link, notes, calls.
	account( 1, array( 'students_now' => 4, 'students_past' => 2, 'last_sign_in' => $NOW - 3 * $DAY, 'last_visit' => $NOW - 3 * $DAY, 'schedule_saved' => true, 'weekly_hours' => true, 'meeting_link' => true ) ),
	// 2: active, students, signed in 40 days ago by sign-in, visited 10 days ago, saved schedule with no hours.
	account( 2, array( 'students_now' => 1, 'last_sign_in' => $NOW - 40 * $DAY, 'last_visit' => $NOW - 10 * $DAY, 'schedule_saved' => true ) ),
	// 3: active, never signed in, no students.
	account( 3 ),
	// 4: active, signed in 50 days ago, no action.
	account( 4, array( 'last_sign_in' => $NOW - 50 * $DAY ) ),
	// 5: an Administrator who mentors.
	account( 5, array( 'roles' => array( 'administrator', 'wpcpm_mentor' ), 'students_now' => 2, 'last_sign_in' => $NOW - $DAY ) ),
	// 6: not active in Airtable.
	account( 6, array( 'active' => false, 'last_sign_in' => $NOW - $DAY ) ),
);

$notes = array(
	note( 101, 1, $NOW - 20 * $DAY ),                          // Before both calls of mentor 1.
	note( 102, 1, $NOW - 9 * $DAY - 3600 ),                    // 23 hours before call 202 starts: noted.
	note( 103, 1, $NOW - 40 * $DAY, array( 'recSTUDENTB' ) ),  // Old, another student.
	note( 104, 900, $NOW - $DAY, array( 'recSTUDENTA' ), 0 ),  // An Administrator's note: not mentor 1's.
	note( 105, 777, $NOW - $DAY ),                             // A deleted author: no row, no warning.
	note( 106, 2, $NOW - 2 * $DAY, array( 'recSTUDENTZ' ), 303 ), // Mentor 2's note on group session 303.
	note( 107, 1, $NOW - 8 * $DAY, array( 'recSTUDENTD' ) ),   // Two days before call 209: too early to count.
);

$calls = array(
	call( 201, 1, $NOW - 25 * $DAY, array( 'records' => array( 'recSTUDENTC' ) ) ), // Held; no note on that student: not noted.
	call( 202, 1, $NOW - 8 * $DAY - 2 * 3600 ),                         // Held; note 102 a day before: noted.
	call( 203, 1, $NOW + 2 * $DAY ),                                    // Upcoming.
	call( 204, 1, $NOW - 5 * $DAY, array( 'status' => 'trash', 'cancelled_by' => 1 ) ),  // Cancelled by the mentor.
	call( 205, 2, $NOW - 4 * $DAY, array( 'status' => 'trash', 'cancelled_by' => 55 ) ), // Cancelled by the student.
	call( 206, 1, 0 ),                                                  // No start: booked, neither held nor upcoming.
	call( 207, 0, $NOW - 3 * $DAY ),                                    // No mentor: no row, still a held call.
	call( 208, 888, $NOW - 3 * $DAY ),                                  // A deleted mentor: no row, still a held call.
	call( 209, 1, $NOW - 6 * $DAY, array( 'records' => array( 'recSTUDENTD' ) ) ), // Held; note 107 is two days before: not noted.
	call( 301, 2, $NOW - 6 * $DAY, array( 'group' => true, 'author' => 2 ) ),           // Created and held.
	call( 302, 2, $NOW + 6 * $DAY, array( 'group' => true, 'author' => 900 ) ),         // Upcoming; a manager made it.
	call( 303, 2, $NOW - 2 * $DAY - 3600, array( 'group' => true, 'author' => 2 ) ),    // Held, noted by note 106.
	call( 304, 2, $NOW + 9 * $DAY, array( 'group' => true, 'author' => 2, 'status' => 'trash', 'cancelled_by' => 2 ) ), // Created, cancelled by the mentor.
);

$out = WPCPM_Mentor_Engagement::compute( $accounts, $notes, $calls, $NOW );
$r1  = row_of( $out, 1 );
$r2  = row_of( $out, 2 );

echo "=== Rows ===\n";

ck( 'one row per account, in the accounts\' order, and none for a deleted author or mentor', array_map( function ( $r ) { return $r['id']; }, $out['rows'] ), array( 1, 2, 3, 4, 5, 6 ) );
ck( 'staff is an account that also holds administrator', array( $r1['staff'], row_of( $out, 5 )['staff'] ), array( false, true ) );
ck( 'last seen is the later of the sign-in and the visit', array( $r1['last_seen'], $r2['last_seen'], row_of( $out, 3 )['last_seen'] ), array( $NOW - 3 * $DAY, $NOW - 10 * $DAY, 0 ) );
ck( 'seen in 7 and 30 days read last seen', array( $r1['seen_7'], $r1['seen_30'], $r2['seen_7'], $r2['seen_30'], row_of( $out, 4 )['seen_30'] ), array( true, true, false, true, false ) );
ck( 'notes are the mentor\'s own: count, in 30 days, the last one', array( $r1['notes'], $r1['notes_30'], $r1['last_note'] ), array( 4, 3, $NOW - 8 * $DAY ) );
ck( 'one-to-one calls: booked (not trashed), held, upcoming; a call with no start is booked only', array( $r1['calls_booked'], $r1['calls_held'], $r1['calls_upcoming'] ), array( 5, 3, 1 ) );
ck( 'a held call is noted by a note about its student dated from a day before it on: 202 is, 201 (no note) and 209 (two days early) are not', $r1['calls_noted'], 1 );
ck( 'a cancellation counts as the mentor\'s only when the mentor cancelled', array( $r1['cancelled_by_mentor'], $r2['cancelled_by_mentor'] ), array( 1, 1 ) );
ck( 'group sessions: created by the mentor (a cancelled one too), held, upcoming, noted', array( $r2['sessions_created'], $r2['sessions_held'], $r2['sessions_upcoming'], $r2['sessions_noted'] ), array( 3, 2, 1, 1 ) );
ck( 'a session an Administrator created on the mentor\'s calendar counts as upcoming, never as created', array( $r2['sessions_created'], $r2['sessions_upcoming'] ), array( 3, 1 ) );
ck( 'acted: a note, a saved schedule, a session created or a cancellation; never a student\'s booking', array( $r1['acted'], $r2['acted'], row_of( $out, 3 )['acted'], row_of( $out, 4 )['acted'] ), array( true, true, false, false ) );

echo "\n=== The summary ===\n";

$s = $out['summary'];
ck( 'active is Airtable Active and not an Administrator', $s['active'], 4 );
ck( 'signed in, never, seen in 30 and 7 days', array( $s['signed_in'], $s['never'], $s['seen_30'], $s['seen_7'] ), array( 3, 1, 2, 1 ) );
ck( 'acted, and signed in without acting', array( $s['acted'], $s['idle'] ), array( 2, 1 ) );
ck( 'with students now: hours, hours and a link, notes, seen in 30 days', array( $s['with_students'], $s['with_students_hours'], $s['with_students_link'], $s['with_students_notes'], $s['with_students_seen_30'] ), array( 2, 1, 1, 2, 2 ) );
ck( 'calls count every call on the site, a deleted mentor\'s and a mentorless one included', array( $s['calls_held'], $s['calls_upcoming'], $s['calls_noted'] ), array( 5, 1, 1 ) );
ck( 'sessions: held, upcoming, noted', array( $s['sessions_held'], $s['sessions_upcoming'], $s['sessions_noted'] ), array( 2, 1, 1 ) );
ck( 'Administrators who mentor are counted apart, and only active ones', $s['staff'], 1 );
ck( 'every figure is an int', array_unique( array_map( 'gettype', array_values( $s ) ) ), array( 'integer' ) );

echo "\n=== Malformed input (Review Focus 2 and 3) ===\n";

$odd = WPCPM_Mentor_Engagement::compute(
	array( account( 9, array( 'last_sign_in' => 'x', 'last_visit' => null, 'students_now' => '3' ) ) ),
	array( note( 1, 9, 'not a time', array() ) ),
	array( call( 2, 9, '', array( 'records' => array() ) ) ),
	$NOW
);
ck( 'a value that is not a number reads as 0 and nothing warns', array( row_of( $odd, 9 )['last_seen'], row_of( $odd, 9 )['students_now'], row_of( $odd, 9 )['calls_held'], row_of( $odd, 9 )['calls_booked'] ), array( 0, 3, 0, 1 ) );
ck( 'no accounts, no notes, no calls: zeros', array_sum( WPCPM_Mentor_Engagement::compute( array(), array(), array(), $NOW )['summary'] ), 0 );

echo "\n=== Views ===\n";

$count = function ( $view ) use ( $out ) {
	return count( array_filter( $out['rows'], function ( $row ) use ( $view ) { return WPCPM_Mentor_Engagement::in_view( $row, $view ); } ) );
};
ck( 'the eight views, in the tab\'s order', array_keys( WPCPM_Mentor_Engagement::views() ), array( 'all', 'active', 'students', 'never', 'quiet', 'idle', 'no-hours', 'staff' ) );
ck( 'each follow-up view counts what its figure counts', array( $count( 'all' ), $count( 'active' ), $count( 'students' ), $count( 'never' ), $count( 'quiet' ), $count( 'idle' ), $count( 'no-hours' ), $count( 'staff' ) ), array( 6, $s['active'], $s['with_students'], $s['never'], $s['signed_in'] - $s['seen_30'], $s['active'] - $s['acted'], $s['with_students'] - $s['with_students_hours'], 1 ) );
ck( 'a view the tab does not have is All', $count( 'nope' ), 6 );

echo "\n=== Order and search ===\n";

$ids = function ( array $rows ) { return array_map( function ( $r ) { return $r['id']; }, $rows ); };
ck( 'the default order is students now, most first, then by name', $ids( WPCPM_Mentor_Engagement::sorted( $out['rows'] ) ), array( 1, 5, 2, 3, 4, 6 ) );
ck( 'by name, A to Z', $ids( WPCPM_Mentor_Engagement::sorted( $out['rows'], 'mentor', 'asc' ) ), array( 1, 2, 3, 4, 5, 6 ) );
ck( 'by last visit, oldest first, the never-visited by name', $ids( WPCPM_Mentor_Engagement::sorted( $out['rows'], 'last_visit', 'asc' ) ), array( 3, 4, 5, 6, 2, 1 ) );
ck( 'an unknown column is the default order', $ids( WPCPM_Mentor_Engagement::sorted( $out['rows'], 'email', 'asc' ) ), array( 1, 5, 2, 3, 4, 6 ) );
ck( 'a search reads the name, the username and the email, in any case', array( WPCPM_Mentor_Engagement::matches( $r1, 'MENTOR 1' ), WPCPM_Mentor_Engagement::matches( $r1, 'mentor1@' ), WPCPM_Mentor_Engagement::matches( $r1, 'zzz' ) ), array( true, true, false ) );

echo "\n=== Words ===\n";

$tiles = WPCPM_Mentor_Engagement::tiles( $s );
ck( 'six tiles in the card\'s order', array_map( function ( $t ) { return $t['name']; }, $tiles ), array( 'Active mentors', 'Seen in the last 30 days', 'Took a program action', 'Never signed in', 'With students now', 'Calls held' ) );
ck( 'each with its number', array_map( function ( $t ) { return $t['n']; }, $tiles ), array( 4, 2, 2, 1, 2, 5 ) );
ck( 'and its line', array_map( function ( $t ) { return $t['line']; }, $tiles ), array( 'not counting Administrators', '1 in the last 7 days', '1 signed in without one', '', '1 with weekly hours, 2 wrote notes', '1 with a note, 1 upcoming; 2 group sessions held' ) );
ck( 'the sentence for the Administrators who mentor', WPCPM_Mentor_Engagement::apart( $s ), 'Administrators who also mentor are counted apart: 1.' );
ck( 'the four schedule states', array( WPCPM_Mentor_Engagement::schedule_label( $r1 ), WPCPM_Mentor_Engagement::schedule_label( array_merge( $r1, array( 'meeting_link' => false ) ) ), WPCPM_Mentor_Engagement::schedule_label( $r2 ), WPCPM_Mentor_Engagement::schedule_label( row_of( $out, 3 ) ) ), array( 'Weekly hours, meeting link', 'Weekly hours, no link', 'Saved, no hours', 'Not set' ) );
ck( 'an exported row has the export fields, dates in UTC, no email', array( array_keys( WPCPM_Mentor_Engagement::export_row( $r1 ) ), WPCPM_Mentor_Engagement::export_row( $r1 )['last_sign_in'] ), array( WPCPM_Mentor_Engagement::EXPORT_FIELDS, gmdate_utc( $NOW - 3 * $DAY ) ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
```

Note on the fixture arithmetic, so a reader can check it by hand. Mentor 1's notes are 101 (20 days ago), 102 (9 days 1 hour ago), 103 (40 days ago) and 107 (8 days ago): 4 notes, 3 in 30 days, the last 8 days ago. The rule has no upper bound (a note written any time after counts), so each held call is given a student no later note names: call 202 starts 8 days 2 hours ago and note 102, about the same student, is 23 hours before it, so 202 is noted; call 201 is about a student nobody wrote about; call 209 starts 6 days ago and note 107, about its student, is 2 days before it, earlier than the day allowed. Mentor 1's one-to-one calls not in the trash are 201, 202, 203, 206 and 209 (5 booked), of which 201, 202 and 209 are held, 203 is upcoming and 206 has no start. The held one-to-one calls site-wide are 201, 202, 207, 208 and 209 (5), with 202 the only noted one; the upcoming one is 203.

- [ ] **Step 2: Run it to see it fail**

Run: `php bin/test-mentor-engagement.php`
Expected: fatal "Failed opening required '.../class-wpcpm-mentor-engagement.php'".

- [ ] **Step 3: Write the rules**

Create `includes/modules/class-wpcpm-mentor-engagement.php`:

```php
<?php
/**
 * Mentor engagement: how each mentor uses the site, read for the Engagement tab, the
 * Administrator Dashboard's card and `wp wpcredits mentor_engagement` (1.122.26).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One reader and one set of rules, so the tab, the card and the command cannot disagree.
 *
 * The rules are the ones `analyze.py` used for the owner's question of 15 October 2026 (spec, section
 * 2): an active mentor is one Airtable holds as Active who is not an Administrator; a held call is
 * noted when its mentor wrote a note about one of its students, dated no earlier than a day before the
 * call; a program action is a note, a saved schedule, a group session created or a call the mentor
 * cancelled, never a student's booking; the call figures count every call on the site.
 *
 * `compute()` holds every rule and reads nothing, so a suite pins each one on plain arrays; `read()`
 * reads the site once (Task 4). Counts and dates only: never a note's text, a call's topic or a
 * student's name.
 */
final class WPCPM_Mentor_Engagement {
	/** The views of the Engagement tab, also `--view` of the command. */
	const VIEW_ALL      = 'all';
	const VIEW_ACTIVE   = 'active';
	const VIEW_STUDENTS = 'students';
	const VIEW_NEVER    = 'never';
	const VIEW_QUIET    = 'quiet';
	const VIEW_IDLE     = 'idle';
	const VIEW_NO_HOURS = 'no-hours';
	const VIEW_STAFF    = 'staff';

	/** What a sort may name, column key => row key. */
	const SORTS = array(
		'mentor'       => 'name',
		'students_now' => 'students_now',
		'last_visit'   => 'last_visit',
		'last_sign_in' => 'last_sign_in',
		'notes'        => 'notes',
		'calls'        => 'calls_held',
	);

	/** The columns of the command's per-mentor output, in order. */
	const EXPORT_FIELDS = array( 'id', 'name', 'active', 'administrator', 'students_now', 'students_past', 'last_visit', 'last_sign_in', 'schedule', 'notes', 'last_note', 'calls_held', 'calls_upcoming', 'calls_noted', 'sessions_held', 'sessions_upcoming' );

	/**
	 * Every row and figure, from the site's facts.
	 *
	 * @param array[] $accounts The mentor accounts (see the class's suite for the shape).
	 * @param array[] $notes    The notes.
	 * @param array[] $calls    The calls and group sessions.
	 * @param int     $now      Unix time the figures are as of.
	 * @return array{rows: array[], summary: array<string, int>}
	 */
	public static function compute( array $accounts, array $notes, array $calls, $now ) {
		$now            = (int) $now;
		$notes_by       = array();
		$noted_sessions = array();
		$calls_by       = array();

		foreach ( $notes as $note ) {
			$notes_by[ (int) $note['author'] ][] = $note;

			if ( (int) $note['session'] > 0 ) {
				$noted_sessions[ (int) $note['session'] ] = true;
			}
		}

		foreach ( $calls as $call ) {
			$calls_by[ (int) $call['mentor'] ][] = $call;
		}

		$rows = array();

		foreach ( $accounts as $account ) {
			$id     = (int) $account['id'];
			$rows[] = self::row(
				$account,
				isset( $notes_by[ $id ] ) ? $notes_by[ $id ] : array(),
				isset( $calls_by[ $id ] ) ? $calls_by[ $id ] : array(),
				$noted_sessions,
				$now
			);
		}

		return array(
			'rows'    => $rows,
			'summary' => self::summary( $rows, $calls, $notes_by, $noted_sessions, $now ),
		);
	}

	/**
	 * The tab's views, slug => label, in the order the tab prints them.
	 *
	 * @return array<string, string>
	 */
	public static function views() {
		return array(
			self::VIEW_ALL      => __( 'All', 'wpcredits-program-manager' ),
			self::VIEW_ACTIVE   => __( 'Active', 'wpcredits-program-manager' ),
			self::VIEW_STUDENTS => __( 'With students now', 'wpcredits-program-manager' ),
			self::VIEW_NEVER    => __( 'Never signed in', 'wpcredits-program-manager' ),
			self::VIEW_QUIET    => __( 'Not seen in 30 days', 'wpcredits-program-manager' ),
			self::VIEW_IDLE     => __( 'No program action', 'wpcredits-program-manager' ),
			self::VIEW_NO_HOURS => __( 'Students, no weekly hours', 'wpcredits-program-manager' ),
			self::VIEW_STAFF    => __( 'Administrators', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * Whether a row is in a view. Every follow-up view counts the active mentors only, so its count
	 * is the figure of the same name; a view the tab does not have is All.
	 *
	 * @param array  $row  A row.
	 * @param string $view A view.
	 * @return bool
	 */
	public static function in_view( array $row, $view ) {
		$active = $row['active'] && ! $row['staff'];

		switch ( (string) $view ) {
			case self::VIEW_ACTIVE:
				return $active;
			case self::VIEW_STUDENTS:
				return $active && $row['students_now'] > 0;
			case self::VIEW_NEVER:
				return $active && 0 === $row['last_seen'];
			case self::VIEW_QUIET:
				return $active && $row['last_seen'] > 0 && ! $row['seen_30'];
			case self::VIEW_IDLE:
				return $active && ! $row['acted'];
			case self::VIEW_NO_HOURS:
				return $active && $row['students_now'] > 0 && ! $row['weekly_hours'];
			case self::VIEW_STAFF:
				return $row['staff'];
			default:
				return true;
		}
	}

	/**
	 * The rows in a column's order. With no column, or one that cannot sort, students now, most
	 * first. A number column opens most first, the name A to Z; ties go by name, then by ID.
	 *
	 * @param array[] $rows    Rows.
	 * @param string  $orderby A key of SORTS.
	 * @param string  $order   `asc` or `desc`.
	 * @return array[]
	 */
	public static function sorted( array $rows, $orderby = '', $order = '' ) {
		if ( ! isset( self::SORTS[ $orderby ] ) ) {
			$orderby = 'students_now';
			$order   = 'desc';
		}

		$key   = self::SORTS[ $orderby ];
		$order = strtolower( (string) $order );
		$dir   = 'asc' === $order ? 1 : ( 'desc' === $order ? -1 : ( 'name' === $key ? 1 : -1 ) );

		usort(
			$rows,
			static function ( $a, $b ) use ( $key, $dir ) {
				$cmp = 'name' === $key ? strcasecmp( $a['name'], $b['name'] ) : ( $a[ $key ] <=> $b[ $key ] );

				if ( 0 !== $cmp ) {
					return $dir * $cmp;
				}

				$cmp = strcasecmp( $a['name'], $b['name'] );

				return 0 !== $cmp ? $cmp : ( $a['id'] <=> $b['id'] );
			}
		);

		return $rows;
	}

	/**
	 * Whether a row matches a search: its name, username or email, in any case.
	 *
	 * @param array  $row    A row.
	 * @param string $search The words searched.
	 * @return bool
	 */
	public static function matches( array $row, $search ) {
		$search = trim( (string) $search );

		if ( '' === $search ) {
			return true;
		}

		foreach ( array( 'name', 'login', 'email' ) as $field ) {
			if ( false !== stripos( (string) $row[ $field ], $search ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The headline figures as the card's tiles and the tab's list say them.
	 *
	 * @param array $summary The summary.
	 * @return array[] `name`, `n`, `line`.
	 */
	public static function tiles( array $summary ) {
		return array(
			array(
				'name' => __( 'Active mentors', 'wpcredits-program-manager' ),
				'n'    => (int) $summary['active'],
				'line' => __( 'not counting Administrators', 'wpcredits-program-manager' ),
			),
			array(
				'name' => __( 'Seen in the last 30 days', 'wpcredits-program-manager' ),
				'n'    => (int) $summary['seen_30'],
				/* translators: %s: a number of mentors. */
				'line' => sprintf( __( '%s in the last 7 days', 'wpcredits-program-manager' ), number_format_i18n( (int) $summary['seen_7'] ) ),
			),
			array(
				'name' => __( 'Took a program action', 'wpcredits-program-manager' ),
				'n'    => (int) $summary['acted'],
				/* translators: %s: a number of mentors. */
				'line' => sprintf( __( '%s signed in without one', 'wpcredits-program-manager' ), number_format_i18n( (int) $summary['idle'] ) ),
			),
			array(
				'name' => __( 'Never signed in', 'wpcredits-program-manager' ),
				'n'    => (int) $summary['never'],
				'line' => '',
			),
			array(
				'name' => __( 'With students now', 'wpcredits-program-manager' ),
				'n'    => (int) $summary['with_students'],
				/* translators: 1: a number of mentors with weekly hours, 2: a number of mentors who wrote notes. */
				'line' => sprintf( __( '%1$s with weekly hours, %2$s wrote notes', 'wpcredits-program-manager' ), number_format_i18n( (int) $summary['with_students_hours'] ), number_format_i18n( (int) $summary['with_students_notes'] ) ),
			),
			array(
				'name' => __( 'Calls held', 'wpcredits-program-manager' ),
				'n'    => (int) $summary['calls_held'],
				/* translators: 1: calls with a note, 2: calls to come, 3: group sessions held. */
				'line' => sprintf( __( '%1$s with a note, %2$s upcoming; %3$s group sessions held', 'wpcredits-program-manager' ), number_format_i18n( (int) $summary['calls_noted'] ), number_format_i18n( (int) $summary['calls_upcoming'] ), number_format_i18n( (int) $summary['sessions_held'] ) ),
			),
		);
	}

	/**
	 * The sentence under the tiles: the Administrators who mentor, counted apart.
	 *
	 * @param array $summary The summary.
	 * @return string
	 */
	public static function apart( array $summary ) {
		/* translators: %s: a number of Administrators. */
		return sprintf( __( 'Administrators who also mentor are counted apart: %s.', 'wpcredits-program-manager' ), number_format_i18n( (int) $summary['staff'] ) );
	}

	/**
	 * A row's schedule in words.
	 *
	 * @param array $row A row.
	 * @return string
	 */
	public static function schedule_label( array $row ) {
		if ( $row['weekly_hours'] ) {
			return $row['meeting_link'] ? __( 'Weekly hours, meeting link', 'wpcredits-program-manager' ) : __( 'Weekly hours, no link', 'wpcredits-program-manager' );
		}

		return $row['schedule_saved'] ? __( 'Saved, no hours', 'wpcredits-program-manager' ) : __( 'Not set', 'wpcredits-program-manager' );
	}

	/**
	 * A row as the command prints it: EXPORT_FIELDS, dates in UTC, no email.
	 *
	 * @param array $row A row.
	 * @return array<string, int|string>
	 */
	public static function export_row( array $row ) {
		$day = static function ( $t ) {
			return $t > 0 ? gmdate( 'Y-m-d H:i', (int) $t ) : '';
		};

		return array(
			'id'                => $row['id'],
			'name'              => $row['name'],
			'active'            => $row['active'] ? 'yes' : 'no',
			'administrator'     => $row['staff'] ? 'yes' : 'no',
			'students_now'      => $row['students_now'],
			'students_past'     => $row['students_past'],
			'last_visit'        => $day( $row['last_visit'] ),
			'last_sign_in'      => $day( $row['last_sign_in'] ),
			'schedule'          => self::schedule_label( $row ),
			'notes'             => $row['notes'],
			'last_note'         => $day( $row['last_note'] ),
			'calls_held'        => $row['calls_held'],
			'calls_upcoming'    => $row['calls_upcoming'],
			'calls_noted'       => $row['calls_noted'],
			'sessions_held'     => $row['sessions_held'],
			'sessions_upcoming' => $row['sessions_upcoming'],
		);
	}

	/**
	 * One mentor's row.
	 *
	 * @param array   $account        The account's facts.
	 * @param array[] $notes          The notes the mentor wrote.
	 * @param array[] $calls          The calls on the mentor's calendar.
	 * @param array   $noted_sessions Session IDs a note names.
	 * @param int     $now            Unix time.
	 * @return array
	 */
	private static function row( array $account, array $notes, array $calls, array $noted_sessions, $now ) {
		$id        = (int) $account['id'];
		$sign_in   = self::int( $account['last_sign_in'] );
		$visit     = self::int( $account['last_visit'] );
		$last_seen = max( $sign_in, $visit );

		$row = array(
			'id'                  => $id,
			'name'                => (string) $account['name'],
			'login'               => (string) $account['login'],
			'email'               => (string) $account['email'],
			'active'              => ! empty( $account['active'] ),
			'staff'               => in_array( WPCPM_Roles::ROLE_ADMIN, (array) $account['roles'], true ),
			'students_now'        => self::int( $account['students_now'] ),
			'students_past'       => self::int( $account['students_past'] ),
			'last_sign_in'        => $sign_in,
			'last_visit'          => $visit,
			'last_seen'           => $last_seen,
			'seen_7'              => $last_seen > 0 && $last_seen >= $now - 7 * DAY_IN_SECONDS,
			'seen_30'             => $last_seen > 0 && $last_seen >= $now - 30 * DAY_IN_SECONDS,
			'schedule_saved'      => ! empty( $account['schedule_saved'] ),
			'weekly_hours'        => ! empty( $account['weekly_hours'] ),
			'meeting_link'        => ! empty( $account['meeting_link'] ),
			'notes'               => count( $notes ),
			'notes_30'            => 0,
			'last_note'           => 0,
			'calls_booked'        => 0,
			'calls_held'          => 0,
			'calls_upcoming'      => 0,
			'calls_noted'         => 0,
			'cancelled_by_mentor' => 0,
			'sessions_created'    => 0,
			'sessions_held'       => 0,
			'sessions_upcoming'   => 0,
			'sessions_noted'      => 0,
		);

		foreach ( $notes as $note ) {
			$created = self::int( $note['created'] );

			if ( $created >= $now - 30 * DAY_IN_SECONDS ) {
				++$row['notes_30'];
			}

			$row['last_note'] = max( $row['last_note'], $created );
		}

		foreach ( $calls as $call ) {
			$trashed = 'trash' === (string) $call['status'];
			$when    = self::when( $call, $now );

			if ( $trashed && $id === (int) $call['cancelled_by'] ) {
				++$row['cancelled_by_mentor'];
			}

			if ( ! empty( $call['group'] ) ) {
				if ( $id === (int) $call['author'] ) {
					++$row['sessions_created'];
				}

				if ( ! $trashed && 'held' === $when ) {
					++$row['sessions_held'];

					if ( isset( $noted_sessions[ (int) $call['id'] ] ) ) {
						++$row['sessions_noted'];
					}
				} elseif ( ! $trashed && 'upcoming' === $when ) {
					++$row['sessions_upcoming'];
				}

				continue;
			}

			if ( $trashed ) {
				continue;
			}

			++$row['calls_booked'];

			if ( 'held' === $when ) {
				++$row['calls_held'];

				if ( self::noted( $call, $notes ) ) {
					++$row['calls_noted'];
				}
			} elseif ( 'upcoming' === $when ) {
				++$row['calls_upcoming'];
			}
		}

		$row['acted'] = $row['notes'] > 0 || $row['schedule_saved'] || $row['sessions_created'] > 0 || $row['cancelled_by_mentor'] > 0;

		return $row;
	}

	/**
	 * The headline figures (spec, section 2).
	 *
	 * @param array[] $rows           Every row.
	 * @param array[] $calls          Every call on the site.
	 * @param array   $notes_by       Notes by author.
	 * @param array   $noted_sessions Session IDs a note names.
	 * @param int     $now            Unix time.
	 * @return array<string, int>
	 */
	private static function summary( array $rows, array $calls, array $notes_by, array $noted_sessions, $now ) {
		$s = array_fill_keys(
			array( 'active', 'signed_in', 'seen_30', 'seen_7', 'never', 'acted', 'idle', 'with_students', 'with_students_hours', 'with_students_link', 'with_students_notes', 'with_students_seen_30', 'calls_held', 'calls_upcoming', 'calls_noted', 'sessions_held', 'sessions_upcoming', 'sessions_noted', 'staff' ),
			0
		);

		foreach ( $rows as $row ) {
			if ( ! $row['active'] ) {
				continue;
			}

			if ( $row['staff'] ) {
				++$s['staff'];
				continue;
			}

			++$s['active'];
			$s['signed_in'] += $row['last_seen'] > 0 ? 1 : 0;
			$s['never']     += 0 === $row['last_seen'] ? 1 : 0;
			$s['seen_30']   += $row['seen_30'] ? 1 : 0;
			$s['seen_7']    += $row['seen_7'] ? 1 : 0;
			$s['acted']     += $row['acted'] ? 1 : 0;
			$s['idle']      += ! $row['acted'] && $row['last_seen'] > 0 ? 1 : 0;

			if ( $row['students_now'] > 0 ) {
				++$s['with_students'];
				$s['with_students_hours']   += $row['weekly_hours'] ? 1 : 0;
				$s['with_students_link']    += $row['weekly_hours'] && $row['meeting_link'] ? 1 : 0;
				$s['with_students_notes']   += $row['notes'] > 0 ? 1 : 0;
				$s['with_students_seen_30'] += $row['seen_30'] ? 1 : 0;
			}
		}

		foreach ( $calls as $call ) {
			if ( 'trash' === (string) $call['status'] ) {
				continue;
			}

			$when = self::when( $call, $now );

			if ( ! empty( $call['group'] ) ) {
				if ( 'held' === $when ) {
					++$s['sessions_held'];
					$s['sessions_noted'] += isset( $noted_sessions[ (int) $call['id'] ] ) ? 1 : 0;
				} elseif ( 'upcoming' === $when ) {
					++$s['sessions_upcoming'];
				}

				continue;
			}

			if ( 'held' === $when ) {
				++$s['calls_held'];
				$mine              = isset( $notes_by[ (int) $call['mentor'] ] ) ? $notes_by[ (int) $call['mentor'] ] : array();
				$s['calls_noted'] += self::noted( $call, $mine ) ? 1 : 0;
			} elseif ( 'upcoming' === $when ) {
				++$s['calls_upcoming'];
			}
		}

		return $s;
	}

	/**
	 * Whether a call has started (`held`), has not (`upcoming`), or has no start at all (`''`).
	 *
	 * @param array $call A call.
	 * @param int   $now  Unix time.
	 * @return string
	 */
	private static function when( array $call, $now ) {
		$start = self::int( $call['start'] );

		if ( $start <= 0 ) {
			return '';
		}

		return $start < $now ? 'held' : 'upcoming';
	}

	/**
	 * Whether one of the mentor's notes is about one of the call's students, dated no earlier than a
	 * day before the call started.
	 *
	 * @param array   $call  A held call.
	 * @param array[] $notes The notes its mentor wrote.
	 * @return bool
	 */
	private static function noted( array $call, array $notes ) {
		$from = self::int( $call['start'] ) - DAY_IN_SECONDS;

		foreach ( $notes as $note ) {
			if ( self::int( $note['created'] ) >= $from && array_intersect( (array) $note['records'], (array) $call['records'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A stored number as an int; anything that is not a number is 0.
	 *
	 * @param mixed $value The value.
	 * @return int
	 */
	private static function int( $value ) {
		return is_numeric( $value ) ? (int) $value : 0;
	}
}
```

- [ ] **Step 4: Load it**

In `wpcredits-program-manager.php`, after `require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-group-sessions.php';`:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-engagement.php';
```

- [ ] **Step 5: Run the suite**

Run: `php bin/test-mentor-engagement.php && php -l includes/modules/class-wpcpm-mentor-engagement.php`
Expected: `ALL PASS`, no syntax errors. A failing number check is a rule disagreeing with the fixture arithmetic noted under Step 1: recompute by hand before changing either side.

- [ ] **Step 6: Commit**

```bash
git add includes/modules/class-wpcpm-mentor-engagement.php wpcredits-program-manager.php bin/test-mentor-engagement.php
git commit -m "1.122.26: the mentor engagement rules, as the owner's question of 15 October counted them"
```

---

### Task 4: Read the site

**Files:**
- Modify: `includes/modules/class-wpcpm-mentor-engagement.php` (`report()`, `read()`, `read_accounts()`, `read_posts()`, `for_tests()`)
- Modify: `bin/stubs/sqlite-wpdb.php` (two table names: `public $posts = 'wp_posts';` and `public $postmeta = 'wp_postmeta';` beside `$prefix`, since PHP 8.2 and later warn on a property the class does not declare)
- Test: `bin/test-mentor-engagement.php` (a section before the final line, on the SQLite wpdb stand-in)

**Interfaces:**
- Consumes: `WPCPM_Mentor_Calls::POST_TYPE`, `META_MENTOR`, `META_START`, `META_CAPACITY`, `META_CANCELLED_BY`, `META_RECORD`; `WPCPM_Mentor_Notes::POST_TYPE`, `META_STUDENT`, `META_SESSION`; `WPCPM_Mentors_Sync::META_RECORD_ID`, `META_ACTIVE`, `META_COUNT`, `META_PAST_COUNT`; `WPCPM_Mentor_Availability::META`, `get()`, `is_published()`; `WPCPM_Sign_Ins::META_SIGN_IN`, `META_VISIT`; `WPCPM_Roles::ROLE_MENTOR`.
- Produces: `WPCPM_Mentor_Engagement::report( int $now = 0 ): array{rows, summary}`; `read(): array{accounts, notes, calls}`; `for_tests( ?array $input ): void` (test-only: `read()` returns `$input` while set).

- [ ] **Step 1: Add the failing checks**

At the top of `bin/test-mentor-engagement.php`, after the `define()` lines:

```php
define( 'ARRAY_A', 'ARRAY_A' );
require __DIR__ . '/stubs/sqlite-wpdb.php';
$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();
$GLOBALS['umeta'] = array();
$GLOBALS['users'] = array();

class WP_User {
	public $ID; public $display_name; public $user_login; public $user_email; public $roles;
	public function __construct( $id, $name, array $roles ) {
		$this->ID = $id; $this->display_name = $name; $this->user_login = 'u' . $id; $this->user_email = 'u' . $id . '@example.test'; $this->roles = $roles;
	}
}
function get_users( $args ) {
	$out = array();
	foreach ( $GLOBALS['users'] as $u ) {
		if ( isset( $args['role'] ) && in_array( $args['role'], $u->roles, true ) ) {
			$out[] = $u->ID;
		}
		if ( isset( $args['meta_key'] ) && '' !== (string) get_user_meta( $u->ID, $args['meta_key'], true ) ) {
			$out[] = $u->ID;
		}
	}
	return $out;
}
function get_userdata( $id ) { return isset( $GLOBALS['users'][ $id ] ) ? $GLOBALS['users'][ $id ] : false; }
function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['umeta'][ $id ][ $key ] ) ? $GLOBALS['umeta'][ $id ][ $key ] : ''; }
function cache_users( $ids ) { $GLOBALS['cached'] = $ids; }

// The schedule's reader, stood in: its own rules are pinned by the mentors dashboard's suite, and
// this reader's contract is only that it asks them.
class WPCPM_Mentor_Availability {
	const META = 'wpcpm_mentor_availability';
	public static function get( $id ) {
		$s = get_user_meta( $id, self::META, true );
		return array( 'weekly' => is_array( $s ) && isset( $s['weekly'] ) ? $s['weekly'] : array(), 'meeting_url' => is_array( $s ) && isset( $s['meeting_url'] ) ? (string) $s['meeting_url'] : '' );
	}
	public static function is_published( $id ) {
		foreach ( self::get( $id )['weekly'] as $w ) {
			if ( ! empty( $w ) ) {
				return true;
			}
		}
		return false;
	}
}
```

and after the existing `require_once` lines:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-sign-ins.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-calls.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-notes.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
```

Then, before the final `echo "\n" . ( $fail ...`:

```php
echo "\n=== Reading the site ===\n";

$db = $GLOBALS['wpdb'];
$db->query( 'CREATE TABLE wp_posts ( ID INTEGER PRIMARY KEY, post_type TEXT, post_status TEXT, post_author INTEGER, post_date_gmt TEXT )' );
$db->query( 'CREATE TABLE wp_postmeta ( meta_id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER, meta_key TEXT, meta_value TEXT )' );
$post = function ( $id, $type, $status, $author, $date, array $meta ) use ( $db ) {
	$db->insert( 'wp_posts', array( 'ID' => $id, 'post_type' => $type, 'post_status' => $status, 'post_author' => $author, 'post_date_gmt' => $date ) );
	foreach ( $meta as $pair ) {
		$db->insert( 'wp_postmeta', array( 'post_id' => $id, 'meta_key' => $pair[0], 'meta_value' => $pair[1] ) );
	}
};
$post( 11, 'wpcpm_mentor_note', 'private', 1, '2026-10-10 08:00:00', array( array( '_wpcpm_student_record', 'recSTUDENTA' ), array( '_wpcpm_note_session', '22' ), array( '_wpcpm_note_text_never_read', 'x' ) ) );
$post( 12, 'wpcpm_mentor_note', 'auto-draft', 1, '2026-10-10 08:00:00', array() );
$post( 21, 'wpcpm_mentor_call', 'private', 900, '2026-10-01 08:00:00', array( array( '_wpcpm_call_mentor', '1' ), array( '_wpcpm_call_start', '1791000000' ), array( '_wpcpm_call_student_record', 'recSTUDENTA' ) ) );
$post( 22, 'wpcpm_mentor_call', 'trash', 1, '2026-10-01 08:00:00', array( array( '_wpcpm_call_mentor', '1' ), array( '_wpcpm_call_start', '1791100000' ), array( '_wpcpm_call_capacity', '10' ), array( '_wpcpm_call_cancelled_by', '1' ), array( '_wpcpm_call_student_record', 'recSTUDENTA' ), array( '_wpcpm_call_student_record', 'recSTUDENTB' ) ) );
$post( 31, 'post', 'publish', 1, '2026-10-01 08:00:00', array( array( '_wpcpm_call_mentor', '1' ) ) );

$GLOBALS['users'] = array( 1 => new WP_User( 1, 'Ada', array( 'wpcpm_mentor' ) ), 2 => new WP_User( 2, 'Bo', array( 'editor' ) ), 3 => new WP_User( 3, 'Cy', array( 'subscriber' ) ) );
$GLOBALS['umeta'] = array(
	1 => array( 'wpcpm_mentor_active' => '1', 'wpcpm_mentee_count' => '4', 'wpcpm_mentee_past_count' => '1', 'wpcpm_last_sign_in' => '1792000000', 'wpcpm_last_visit' => '1792050000', 'wpcpm_mentor_availability' => array( 'weekly' => array( 1 => array( array( '09:00', '10:00' ) ) ), 'meeting_url' => 'https://meet.example.test/a' ) ),
	2 => array( 'wpcpm_mentor_record_id' => 'recMENTORB', 'wpcpm_mentor_active' => '0' ),
);

$before = count( $db->queries );
$read   = WPCPM_Mentor_Engagement::read();
$asked  = array_slice( $db->queries, $before );
ck( 'the accounts are the Mentor role and any account holding a mentor record, once each', array_map( function ( $a ) { return $a['id']; }, $read['accounts'] ), array( 1, 2 ) );
ck( 'their users and meta are read in one go', $GLOBALS['cached'], array( 1, 2 ) );
ck( 'an account\'s facts', $read['accounts'][0], array( 'id' => 1, 'name' => 'Ada', 'login' => 'u1', 'email' => 'u1@example.test', 'roles' => array( 'wpcpm_mentor' ), 'active' => true, 'students_now' => 4, 'students_past' => 1, 'last_sign_in' => 1792000000, 'last_visit' => 1792050000, 'schedule_saved' => true, 'weekly_hours' => true, 'meeting_link' => true ) );
ck( 'an account with nothing stored reads as not active, nothing saved', array( $read['accounts'][1]['active'], $read['accounts'][1]['schedule_saved'], $read['accounts'][1]['last_sign_in'] ), array( false, false, 0 ) );
ck( 'the notes: no auto-draft, the student and the session, the time in UTC, nothing else', $read['notes'], array( array( 'id' => 11, 'author' => 1, 'created' => gmmktime( 8, 0, 0, 10, 10, 2026 ), 'records' => array( 'recSTUDENTA' ), 'session' => 22 ) ) );
ck( 'the calls: a one-to-one call and a cancelled group session with both its students; no other post type', $read['calls'], array(
	array( 'id' => 21, 'author' => 900, 'mentor' => 1, 'start' => 1791000000, 'cancelled_by' => 0, 'status' => 'private', 'group' => false, 'records' => array( 'recSTUDENTA' ) ),
	array( 'id' => 22, 'author' => 1, 'mentor' => 1, 'start' => 1791100000, 'cancelled_by' => 1, 'status' => 'trash', 'group' => true, 'records' => array( 'recSTUDENTA', 'recSTUDENTB' ) ),
) );
ck( 'two statements through the site\'s database object, neither a GROUP_CONCAT nor a read of a post\'s content', array( count( $asked ), count( array_filter( $asked, function ( $q ) { return false !== stripos( $q, 'GROUP_CONCAT' ) || false !== stripos( $q, 'post_content' ); } ) ) ), array( 2, 0 ) );

WPCPM_Mentor_Engagement::for_tests( array( 'accounts' => array( account( 1 ) ), 'notes' => array(), 'calls' => array() ) );
ck( 'report() runs compute() on what read() returns', WPCPM_Mentor_Engagement::report( $NOW )['summary']['active'], 1 );
WPCPM_Mentor_Engagement::for_tests( null );
```

- [ ] **Step 2: Run it to see it fail**

Run: `php bin/test-mentor-engagement.php`
Expected: the rule checks pass, then "Call to undefined method WPCPM_Mentor_Engagement::read()".

- [ ] **Step 3: Write the reader**

Add to `WPCPM_Mentor_Engagement`, after `compute()`:

```php
	/**
	 * What `read()` returns in place of the site, while a suite has set it.
	 *
	 * @var array|null
	 */
	private static $input = null;

	/**
	 * Every row and figure as of now (or `$now`), read from the site once.
	 *
	 * @param int $now Unix time, or 0 for now.
	 * @return array{rows: array[], summary: array<string, int>}
	 */
	public static function report( $now = 0 ) {
		$input = self::read();

		return self::compute( $input['accounts'], $input['notes'], $input['calls'], (int) $now > 0 ? (int) $now : time() );
	}

	/**
	 * The site's facts, as `compute()` takes them.
	 *
	 * @return array{accounts: array[], notes: array[], calls: array[]}
	 */
	public static function read() {
		if ( null !== self::$input ) {
			return self::$input;
		}

		$posts = self::read_posts();

		return array(
			'accounts' => self::read_accounts(),
			'notes'    => $posts['notes'],
			'calls'    => $posts['calls'],
		);
	}

	/**
	 * Read this instead of the site. Test-only: nothing in the plugin calls it.
	 *
	 * @param array|null $input `accounts`, `notes`, `calls`; null to read the site again.
	 */
	public static function for_tests( $input ) {
		self::$input = is_array( $input ) ? $input : null;
	}

	/**
	 * The mentor accounts: the Mentor role, and any account holding a mentor record, once each,
	 * their users and meta read in one go.
	 *
	 * @return array[]
	 */
	private static function read_accounts() {
		$ids = array_merge(
			(array) get_users(
				array(
					'role'   => WPCPM_Roles::ROLE_MENTOR,
					'fields' => 'ID',
					'number' => -1,
				)
			),
			(array) get_users(
				array(
					'meta_key'     => WPCPM_Mentors_Sync::META_RECORD_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- About a hundred accounts, read on three screens an Administrator opens.
					'meta_value'   => '', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- As above.
					'meta_compare' => '!=',
					'fields'       => 'ID',
					'number'       => -1,
				)
			)
		);
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		sort( $ids );

		if ( empty( $ids ) ) {
			return array();
		}

		cache_users( $ids );

		$accounts = array();

		foreach ( $ids as $id ) {
			$user = get_userdata( $id );

			if ( ! $user instanceof WP_User ) {
				continue;
			}

			$stored   = get_user_meta( $id, WPCPM_Mentor_Availability::META, true );
			$schedule = WPCPM_Mentor_Availability::get( $id );

			$accounts[] = array(
				'id'             => $id,
				'name'           => (string) $user->display_name,
				'login'          => (string) $user->user_login,
				'email'          => (string) $user->user_email,
				'roles'          => array_values( (array) $user->roles ),
				'active'         => 1 === self::int( get_user_meta( $id, WPCPM_Mentors_Sync::META_ACTIVE, true ) ),
				'students_now'   => self::int( get_user_meta( $id, WPCPM_Mentors_Sync::META_COUNT, true ) ),
				'students_past'  => self::int( get_user_meta( $id, WPCPM_Mentors_Sync::META_PAST_COUNT, true ) ),
				'last_sign_in'   => self::int( get_user_meta( $id, WPCPM_Sign_Ins::META_SIGN_IN, true ) ),
				'last_visit'     => self::int( get_user_meta( $id, WPCPM_Sign_Ins::META_VISIT, true ) ),
				'schedule_saved' => is_array( $stored ),
				'weekly_hours'   => WPCPM_Mentor_Availability::is_published( $id ),
				'meeting_link'   => '' !== (string) $schedule['meeting_url'],
			);
		}

		return $accounts;
	}

	/**
	 * The notes and the calls: the posts, then their meta, in two statements and joined here. No
	 * GROUP_CONCAT, which MySQL cuts at 1,024 characters and a full group session's students reach.
	 * Never the posts' content or title.
	 *
	 * @return array{notes: array[], calls: array[]}
	 */
	private static function read_posts() {
		global $wpdb;

		$types = array( WPCPM_Mentor_Notes::POST_TYPE, WPCPM_Mentor_Calls::POST_TYPE );
		$keys  = array( WPCPM_Mentor_Calls::META_MENTOR, WPCPM_Mentor_Calls::META_START, WPCPM_Mentor_Calls::META_CAPACITY, WPCPM_Mentor_Calls::META_CANCELLED_BY, WPCPM_Mentor_Calls::META_RECORD, WPCPM_Mentor_Notes::META_STUDENT, WPCPM_Mentor_Notes::META_SESSION );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Two reads of a few hundred rows, on three screens an Administrator opens; nothing to cache across requests.
		$posts = $wpdb->get_results(
			$wpdb->prepare( "SELECT ID, post_type, post_status, post_author, post_date_gmt FROM {$wpdb->posts} WHERE post_type IN (%s, %s) AND post_status <> %s ORDER BY ID", $types[0], $types[1], 'auto-draft' ),
			ARRAY_A
		);
		$meta  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_key, pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type IN (%s, %s) AND p.post_status <> %s AND pm.meta_key IN (%s, %s, %s, %s, %s, %s, %s) ORDER BY pm.meta_id",
				array_merge( $types, array( 'auto-draft' ), $keys )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$by = array();

		foreach ( (array) $meta as $m ) {
			$by[ (int) $m['post_id'] ][ (string) $m['meta_key'] ][] = (string) $m['meta_value'];
		}

		$notes = array();
		$calls = array();

		foreach ( (array) $posts as $p ) {
			$id    = (int) $p['ID'];
			$m     = isset( $by[ $id ] ) ? $by[ $id ] : array();
			$first = static function ( $key ) use ( $m ) {
				return isset( $m[ $key ][0] ) ? $m[ $key ][0] : '';
			};

			if ( WPCPM_Mentor_Notes::POST_TYPE === $p['post_type'] ) {
				$notes[] = array(
					'id'      => $id,
					'author'  => (int) $p['post_author'],
					'created' => (int) strtotime( $p['post_date_gmt'] . ' UTC' ),
					'records' => isset( $m[ WPCPM_Mentor_Notes::META_STUDENT ] ) ? $m[ WPCPM_Mentor_Notes::META_STUDENT ] : array(),
					'session' => self::int( $first( WPCPM_Mentor_Notes::META_SESSION ) ),
				);
				continue;
			}

			$calls[] = array(
				'id'           => $id,
				'author'       => (int) $p['post_author'],
				'mentor'       => self::int( $first( WPCPM_Mentor_Calls::META_MENTOR ) ),
				'start'        => self::int( $first( WPCPM_Mentor_Calls::META_START ) ),
				'cancelled_by' => self::int( $first( WPCPM_Mentor_Calls::META_CANCELLED_BY ) ),
				'status'       => (string) $p['post_status'],
				'group'        => '' !== $first( WPCPM_Mentor_Calls::META_CAPACITY ),
				'records'      => isset( $m[ WPCPM_Mentor_Calls::META_RECORD ] ) ? $m[ WPCPM_Mentor_Calls::META_RECORD ] : array(),
			);
		}

		return array(
			'notes' => $notes,
			'calls' => $calls,
		);
	}
```

- [ ] **Step 4: Run the suite**

Run: `php bin/test-mentor-engagement.php`
Expected: `ALL PASS`.

- [ ] **Step 5: Run the standards on the two new files**

Run: `bash bin/check-standards.sh 2>&1 | tail -20`
Expected: no new error in `class-wpcpm-sign-ins.php` or `class-wpcpm-mentor-engagement.php`; the warning count no higher than the baseline the release script's `config.toml` holds. Fix any new error before the commit.

- [ ] **Step 6: Commit**

```bash
git add includes/modules/class-wpcpm-mentor-engagement.php bin/test-mentor-engagement.php
git commit -m "1.122.26: the engagement reader reads the mentors, their notes and their calls in two statements, never a note's text"
```

---

### Task 5: The Engagement tab

**Files:**
- Create: `includes/modules/class-wpcpm-mentor-engagement-table.php`
- Modify: `includes/modules/class-wpcpm-mentors.php` (the `use` line, `TAB_ENGAGEMENT`, `TABS`, `ENGAGEMENT_PER_PAGE_OPTION`, a property, `boot()`, `load_screen()`, `tab_labels()`, `render_admin_page()`, `render_tab_engagement()`, `engagement_table()`, `save_engagement_per_page()`)
- Test: `bin/test-mentors-screen.php`

**Interfaces:**
- Consumes: `WPCPM_Mentor_Engagement::report()`, `views()`, `in_view()`, `sorted()`, `matches()`, `tiles()`, `apart()`, `schedule_label()`; `WPCPM_Sign_Ins::since()`; `WPCPM_Mentors_Dashboard::page_url()`; `WPCPM_Request::key()`, `text()`; `WPCPM_Screen_Tabs::render()`.
- Produces: `WPCPM_Mentor_Engagement_Table` (constructor `( array $report )`; `PER_PAGE_DEFAULT` 20; `VIEW_ARG` `'engagement_view'`; `ANCHOR` `'wpcpm-mentor-engagement-list'`; `summary(): array`; `current_view(): string`; `columns_offered(): array`); `WPCPM_Mentors::TAB_ENGAGEMENT` `'engagement'`; `WPCPM_Mentors::ENGAGEMENT_PER_PAGE_OPTION` `'wpcpm_mentor_engagement_per_page'`. The tab's address is `admin.php?page=wpcpm-mentors&tab=engagement` (Task 6 links to it).

- [ ] **Step 1: Write the failing checks**

Read `bin/test-mentors-screen.php` around line 1735 (the tab bar check) and the helpers it uses (`screen()`, `page_for()`, `bar_of()`, `html_of()`, `between()`, `view_counts()`, `row_ids()`, `cell()`), then:

(a) Replace `$two_tabs` and its check (lines 1741 to the end of that `ck()`) so the bar holds three tabs, Accounts, Engagement, Sync, each at `$home . '&tab=<slug>'`, Accounts shown from the menu and for an unknown tab. Rename the section heading to "The screen in three tabs, Accounts, Engagement and Sync".

(b) Near the top, after the suite requires the Mentors module, add:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-sign-ins.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-engagement.php';
// The Engagement table prints no checkbox, so `row_ids()` (which reads the Accounts list's) cannot read it.
function engagement_ids( $html ) {
	preg_match_all( '/<tr id="mentor-(\d+)">/', (string) $html, $found );
	return array_map( 'intval', $found[1] );
}
if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $timestamp = null ) { return gmdate( $format, (int) $timestamp ); }
}
```

(c) Add a section before the suite's last check:

```php
echo "\n=== The Engagement tab ===\n";

$NOW = time();
$e_account = function ( $id, $name, array $more = array() ) {
	return array_merge( array( 'id' => $id, 'name' => $name, 'login' => 'login' . $id, 'email' => 'e' . $id . '@example.test', 'roles' => array( 'wpcpm_mentor' ), 'active' => true, 'students_now' => 0, 'students_past' => 0, 'last_sign_in' => 0, 'last_visit' => 0, 'schedule_saved' => false, 'weekly_hours' => false, 'meeting_link' => false ), $more );
};
WPCPM_Mentor_Engagement::for_tests(
	array(
		'accounts' => array(
			$e_account( 301, 'Ada Engaged', array( 'students_now' => 3, 'students_past' => 1, 'last_sign_in' => $NOW - 2 * 86400, 'last_visit' => $NOW - 86400, 'schedule_saved' => true, 'weekly_hours' => true, 'meeting_link' => true ) ),
			$e_account( 302, 'Bo Quiet', array( 'students_now' => 1 ) ),
			$e_account( 303, 'Cy <b>Tag</b>', array( 'roles' => array( 'administrator', 'wpcpm_mentor' ) ) ),
		),
		'notes'    => array(),
		'calls'    => array(),
	)
);

$e_page = html_of( page_for( screen( array( 'tab' => 'engagement' ) ) ) );
ck( 'the figures come first, in the card\'s words', array( false !== strpos( $e_page, 'Active mentors' ), false !== strpos( $e_page, 'Administrators who also mentor are counted apart: 1.' ), strpos( $e_page, 'Active mentors' ) < strpos( $e_page, 'wpcpm-mentor-engagement-list' ) ), array( true, true, true ) );
ck( 'the rows open on students now, most first', engagement_ids( $e_page ), array( 301, 302, 303 ) );
ck( 'the views with their counts', view_counts( $e_page ), array( 'all' => '3', 'active' => '2', 'students' => '2', 'never' => '1', 'quiet' => '0', 'idle' => '1', 'no-hours' => '1', 'staff' => '1' ) );
ck( 'a row says its schedule, its last visit and its status', array( false !== strpos( $e_page, 'Weekly hours, meeting link' ), false !== strpos( $e_page, 'Not recorded yet' ), false !== strpos( $e_page, 'Active<br />Administrator' ) ), array( true, true, true ) );
ck( 'a name is printed escaped', array( false !== strpos( $e_page, 'Cy &lt;b&gt;Tag&lt;/b&gt;' ), false === strpos( $e_page, 'Cy <b>Tag</b>' ) ), array( true, true ) );
ck( 'View page opens the mentor\'s Mentor Report Card', false !== strpos( $e_page, 'wpcpm_mentor=301' ) );
ck( 'the note under the list says how far back sign-ins go', false !== strpos( $e_page, 'Sign-ins ' ) );

// Review Focus 1: two tables, one screen.
wpcpm_load_accounts_tables();
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-engagement-table.php';
$accounts_keys = array_keys( ( new WPCPM_Mentors_Table() )->get_columns() );
$e_keys        = array_keys( ( new WPCPM_Mentor_Engagement_Table( WPCPM_Mentor_Engagement::report() ) )->get_columns() );
ck( 'no column key is the Accounts list\'s, so hiding one never hides the other', array_values( array_intersect( $accounts_keys, $e_keys ) ), array() );
ck( 'nor is the rows-per-page option', array( WPCPM_Mentors::ENGAGEMENT_PER_PAGE_OPTION !== WPCPM_Mentors::PER_PAGE_OPTION, WPCPM_Mentors::ENGAGEMENT_PER_PAGE_OPTION ), array( true, 'wpcpm_mentor_engagement_per_page' ) );
ck( 'and its save is hooked at boot', has_filter_for( 'set_screen_option_wpcpm_mentor_engagement_per_page' ) );

// Review Focus 5: nothing to show, and a hostile search.
$e_none = html_of( page_for( screen( array( 'tab' => 'engagement', 's' => '"><script>x</script>' ) ) ) );
ck( 'a search that matches nothing says so', false !== strpos( $e_none, 'No mentors match.' ) );
ck( 'and nothing of the search is printed as markup', false === strpos( $e_none, '<script>x</script>' ) );

$e_sorted = html_of( page_for( screen( array( 'tab' => 'engagement', 'orderby' => 'mentor', 'order' => 'desc' ) ) ) );
ck( 'a sort by name, Z to A', engagement_ids( $e_sorted ), array( 303, 302, 301 ) );
$e_view = html_of( page_for( screen( array( 'tab' => 'engagement', 'engagement_view' => 'no-hours' ) ) ) );
ck( 'a view keeps its rows', engagement_ids( $e_view ), array( 302 ) );

WPCPM_Mentor_Engagement::for_tests( null );
```

If the suite has no `has_filter_for()` helper, add it beside its other helpers, reading the filters its `add_filter()` stand-in records (check the stand-in's storage in `bin/stubs/accounts-screen.php` first):

```php
function has_filter_for( $hook ) {
	foreach ( (array) $GLOBALS['filters'] as $entry ) {
		if ( ( is_array( $entry ) && isset( $entry[0] ) && $hook === $entry[0] ) || ( is_string( $entry ) && $hook === $entry ) ) {
			return true;
		}
	}
	return isset( $GLOBALS['filters'][ $hook ] );
}
```

- [ ] **Step 2: Run it to see it fail**

Run: `php bin/test-mentors-screen.php 2>&1 | grep -E "FAIL|ALL PASS" | head`
Expected: FAIL on the three-tab bar check and on every Engagement check.

- [ ] **Step 3: Write the table**

Create `includes/modules/class-wpcpm-mentor-engagement-table.php`:

```php
<?php
/**
 * The Mentors screen's Engagement tab: one row per mentor.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Every mentor's sign-ins, students, schedule, notes and calls, read once by
 * `WPCPM_Mentor_Engagement::report()` and sorted, searched, filtered and paged here in PHP, as the
 * Accounts tab orders its Students column: about a hundred rows.
 *
 * Read-only: no checkbox, no bulk action, no form but the GET form of the view, the search and the
 * page. **Its column keys and its rows-per-page option are its own**, because the two tabs are one
 * screen to WordPress, which keeps one hidden-columns list per screen: a key shared with the
 * Accounts list (`students`, `past`, `status`) would hide in both.
 */
class WPCPM_Mentor_Engagement_Table extends WP_List_Table {
	/** Rows a page until the person chooses. */
	const PER_PAGE_DEFAULT = 20;

	/** The query argument the views use. */
	const VIEW_ARG = 'engagement_view';

	/** The list's anchor, which the form and the views return to. */
	const ANCHOR = 'wpcpm-mentor-engagement-list';

	/**
	 * What `WPCPM_Mentor_Engagement::report()` returned.
	 *
	 * @var array
	 */
	private $report;

	/**
	 * The Mentor Report Card's address, read once.
	 *
	 * @var string
	 */
	private $mentor_page;

	/**
	 * The table, over one report.
	 *
	 * @param array $report `rows` and `summary`.
	 */
	public function __construct( array $report ) {
		parent::__construct(
			array(
				'singular' => 'mentor',
				'plural'   => 'mentor-engagement',
				'ajax'     => false,
			)
		);

		$this->report      = $report;
		$this->mentor_page = WPCPM_Mentors_Dashboard::page_url();

		// Screen Options offers every column but the Mentor column, as the Accounts list does.
		if ( isset( $this->screen->id ) ) {
			$filter = 'manage_' . $this->screen->id . '_columns';

			remove_filter( $filter, array( $this, 'get_columns' ), 0 );
			add_filter( $filter, array( $this, 'columns_offered' ), 0 );
		}
	}

	/**
	 * The headline figures the tab prints above the list.
	 *
	 * @return array<string, int>
	 */
	public function summary() {
		return (array) $this->report['summary'];
	}

	/**
	 * The columns, their labels escaped: core prints them as given.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'mentor'       => esc_html__( 'Mentor', 'wpcredits-program-manager' ),
			'standing'     => esc_html__( 'Status', 'wpcredits-program-manager' ),
			'students_now' => esc_html__( 'Students', 'wpcredits-program-manager' ),
			'last_visit'   => esc_html__( 'Last visit', 'wpcredits-program-manager' ),
			'last_sign_in' => esc_html__( 'Last sign-in', 'wpcredits-program-manager' ),
			'schedule'     => esc_html__( 'Schedule', 'wpcredits-program-manager' ),
			'notes'        => esc_html__( 'Notes', 'wpcredits-program-manager' ),
			'calls'        => esc_html__( 'Calls', 'wpcredits-program-manager' ),
			'sessions'     => esc_html__( 'Group sessions', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * What Screen Options may hide: every column but Mentor.
	 *
	 * @return array<string, string>
	 */
	public function columns_offered() {
		return array_diff_key( $this->get_columns(), array( 'mentor' => true ) );
	}

	/**
	 * The sortable columns; a number column opens most first.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'mentor'       => array( 'mentor', false ),
			'students_now' => array( 'students_now', true ),
			'last_visit'   => array( 'last_visit', true ),
			'last_sign_in' => array( 'last_sign_in', true ),
			'notes'        => array( 'notes', true ),
			'calls'        => array( 'calls', true ),
		);
	}

	/**
	 * The column the row actions sit under.
	 *
	 * @return string
	 */
	protected function get_primary_column_name() {
		return 'mentor';
	}

	/**
	 * The view asked for, or All.
	 *
	 * @return string
	 */
	public function current_view() {
		$view = WPCPM_Request::key( self::VIEW_ARG );

		return array_key_exists( $view, WPCPM_Mentor_Engagement::views() ) ? $view : WPCPM_Mentor_Engagement::VIEW_ALL;
	}

	/**
	 * The page of rows: the view, the search, the order, then the page.
	 */
	public function prepare_items() {
		$view   = $this->current_view();
		$search = WPCPM_Request::text( 's' );
		$rows   = array();

		foreach ( (array) $this->report['rows'] as $row ) {
			if ( WPCPM_Mentor_Engagement::in_view( $row, $view ) && WPCPM_Mentor_Engagement::matches( $row, $search ) ) {
				$rows[] = $row;
			}
		}

		$rows     = WPCPM_Mentor_Engagement::sorted( $rows, WPCPM_Request::key( 'orderby' ), WPCPM_Request::key( 'order' ) );
		$per_page = $this->get_items_per_page( WPCPM_Mentors::ENGAGEMENT_PER_PAGE_OPTION, self::PER_PAGE_DEFAULT );

		$this->set_pagination_args(
			array(
				'total_items' => count( $rows ),
				'per_page'    => $per_page,
			)
		);

		$this->items           = array_slice( $rows, ( $this->get_pagenum() - 1 ) * $per_page, $per_page );
		$this->_column_headers = array( $this->get_columns(), get_hidden_columns( $this->screen ), $this->get_sortable_columns(), 'mentor' );
	}

	/**
	 * The views, each with its count over every row.
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$current = $this->current_view();
		$base    = admin_url( 'admin.php?page=wpcpm-mentors&tab=engagement' );
		$links   = array();

		foreach ( WPCPM_Mentor_Engagement::views() as $view => $label ) {
			$count = 0;

			foreach ( (array) $this->report['rows'] as $row ) {
				$count += WPCPM_Mentor_Engagement::in_view( $row, $view ) ? 1 : 0;
			}

			$links[ $view ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				esc_url( WPCPM_Mentor_Engagement::VIEW_ALL === $view ? $base . '#' . self::ANCHOR : add_query_arg( self::VIEW_ARG, $view, $base ) . '#' . self::ANCHOR ),
				$view === $current ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				esc_html( number_format_i18n( $count ) )
			);
		}

		return $links;
	}

	/**
	 * What an empty list says.
	 */
	public function no_items() {
		echo esc_html__( 'No mentors match.', 'wpcredits-program-manager' );
	}

	/**
	 * One row, carrying the mentor's ID for the suite to read (there is no checkbox to carry it).
	 *
	 * @param array $item A row.
	 */
	public function single_row( $item ) {
		printf( '<tr id="mentor-%d">', (int) $item['id'] );
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * The name, with View page and Edit under it.
	 *
	 * @param array $item A row.
	 * @return string
	 */
	protected function column_mentor( $item ) {
		$actions = array();
		$edit    = (string) get_edit_user_link( (int) $item['id'] );

		if ( '' !== $edit ) {
			$actions['edit'] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $edit ), esc_html__( 'Edit', 'wpcredits-program-manager' ) );
		}

		if ( '' !== $this->mentor_page ) {
			$actions['view'] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( add_query_arg( 'wpcpm_mentor', (int) $item['id'], $this->mentor_page ) ), esc_html__( 'View page', 'wpcredits-program-manager' ) );
		}

		return '<strong>' . esc_html( $item['name'] ) . '</strong>' . $this->row_actions( $actions );
	}

	/**
	 * Active or not in Airtable, and Administrator.
	 *
	 * @param array $item A row.
	 * @return string
	 */
	protected function column_standing( $item ) {
		$text = $item['active'] ? esc_html__( 'Active', 'wpcredits-program-manager' ) : esc_html__( 'Not active', 'wpcredits-program-manager' );

		return $item['staff'] ? $text . '<br />' . esc_html__( 'Administrator', 'wpcredits-program-manager' ) : $text;
	}

	/**
	 * Students now, and before.
	 *
	 * @param array $item A row.
	 * @return string
	 */
	protected function column_students_now( $item ) {
		/* translators: %s: a number of past students. */
		return esc_html( number_format_i18n( $item['students_now'] ) ) . '<br /><span class="description">' . esc_html( sprintf( __( '%s before', 'wpcredits-program-manager' ), number_format_i18n( $item['students_past'] ) ) ) . '</span>';
	}

	/**
	 * The last visit, and how long ago.
	 *
	 * @param array $item A row.
	 * @return string
	 */
	protected function column_last_visit( $item ) {
		if ( $item['last_visit'] <= 0 ) {
			return esc_html__( 'Not recorded yet', 'wpcredits-program-manager' );
		}

		/* translators: %s: a time span, such as "3 days". */
		return esc_html( $this->day( $item['last_visit'] ) ) . '<br /><span class="description">' . esc_html( sprintf( __( '%s ago', 'wpcredits-program-manager' ), human_time_diff( $item['last_visit'], time() ) ) ) . '</span>';
	}

	/**
	 * The last sign-in.
	 *
	 * @param array $item A row.
	 * @return string
	 */
	protected function column_last_sign_in( $item ) {
		return $item['last_sign_in'] > 0 ? esc_html( $this->day( $item['last_sign_in'] ) ) : esc_html__( 'Not recorded yet', 'wpcredits-program-manager' );
	}

	/**
	 * The schedule in words.
	 *
	 * @param array $item A row.
	 * @return string
	 */
	protected function column_schedule( $item ) {
		return esc_html( WPCPM_Mentor_Engagement::schedule_label( $item ) );
	}

	/**
	 * Notes written, and the last one's day.
	 *
	 * @param array $item A row.
	 * @return string
	 */
	protected function column_notes( $item ) {
		if ( $item['notes'] <= 0 ) {
			return esc_html( number_format_i18n( 0 ) );
		}

		/* translators: %s: a date. */
		return esc_html( number_format_i18n( $item['notes'] ) ) . '<br /><span class="description">' . esc_html( sprintf( __( 'last on %s', 'wpcredits-program-manager' ), $this->day( $item['last_note'] ) ) ) . '</span>';
	}

	/**
	 * One-to-one calls: held, upcoming, and how many held have a note.
	 *
	 * @param array $item A row.
	 * @return string
	 */
	protected function column_calls( $item ) {
		/* translators: 1: calls held, 2: calls to come. */
		$html = esc_html( sprintf( __( '%1$s held, %2$s upcoming', 'wpcredits-program-manager' ), number_format_i18n( $item['calls_held'] ), number_format_i18n( $item['calls_upcoming'] ) ) );

		if ( $item['calls_held'] > 0 ) {
			/* translators: 1: calls with a note, 2: calls held. */
			$html .= '<br /><span class="description">' . esc_html( sprintf( __( '%1$s of %2$s noted', 'wpcredits-program-manager' ), number_format_i18n( $item['calls_noted'] ), number_format_i18n( $item['calls_held'] ) ) ) . '</span>';
		}

		return $html;
	}

	/**
	 * Group sessions: held and upcoming.
	 *
	 * @param array $item A row.
	 * @return string
	 */
	protected function column_sessions( $item ) {
		/* translators: 1: sessions held, 2: sessions to come. */
		return esc_html( sprintf( __( '%1$s held, %2$s upcoming', 'wpcredits-program-manager' ), number_format_i18n( $item['sessions_held'] ), number_format_i18n( $item['sessions_upcoming'] ) ) );
	}

	/**
	 * A day in the site's format and time zone.
	 *
	 * @param int $time Unix time.
	 * @return string
	 */
	private function day( $time ) {
		return (string) wp_date( (string) get_option( 'date_format' ), (int) $time );
	}
}
```

- [ ] **Step 4: Give the Mentors screen its third tab**

In `includes/modules/class-wpcpm-mentors.php`:

Replace `use WPCPM_Accounts_Screen;` with:

```php
	// The Engagement tab builds its own table on the screen's load hook (`load_screen()` below); the
	// shared one is kept under another name for the Accounts tab, as the Institutions module keeps it.
	use WPCPM_Accounts_Screen {
		load_screen as private load_accounts_list;
	}
```

After `const TAB_SYNC = 'sync';` add, and replace `TABS`:

```php
	/** The screen's tab that shows how each mentor uses the site (1.122.26). */
	const TAB_ENGAGEMENT = 'engagement';

	/**
	 * The screen's tabs, slug => label, in the bar's order: the accounts, the tab the screen opens
	 * on, how each mentor uses the site, then the sync. English, as a constant has to hold them;
	 * `tab_labels()` translates them.
	 */
	const TABS = array(
		self::TAB_ACCOUNTS   => 'Accounts',
		self::TAB_ENGAGEMENT => 'Engagement',
		self::TAB_SYNC       => 'Sync',
	);
```

After `const PER_PAGE_OPTION = 'wpcpm_mentors_per_page';` add:

```php
	/** The user option the Engagement tab's rows-per-page choice is kept in: its own, never the Accounts list's. */
	const ENGAGEMENT_PER_PAGE_OPTION = 'wpcpm_mentor_engagement_per_page';

	/**
	 * The Engagement tab's table, built on the screen's load hook.
	 *
	 * @var WPCPM_Mentor_Engagement_Table|null
	 */
	private $engagement = null;
```

In `boot()`, after `$this->boot_screen();`:

```php
		// The Engagement tab's rows-per-page save, hooked here for the reason the Accounts list's is:
		// WordPress saves a screen option before it builds the menu.
		add_filter( 'set_screen_option_' . self::ENGAGEMENT_PER_PAGE_OPTION, array( __CLASS__, 'save_engagement_per_page' ), 10, 3 );
```

Then add these methods after `dashboard_url()`:

```php
	/**
	 * Before the screen draws: the Engagement tab's rows-per-page option and its table, or the
	 * Accounts tab's list as every accounts screen loads it.
	 */
	public function load_screen() {
		if ( self::TAB_ENGAGEMENT !== self::tab() ) {
			$this->load_accounts_list();

			return;
		}

		add_screen_option(
			'per_page',
			array(
				'default' => WPCPM_Mentor_Engagement_Table::PER_PAGE_DEFAULT,
				'option'  => self::ENGAGEMENT_PER_PAGE_OPTION,
			)
		);

		$this->engagement_table();
	}

	/**
	 * The value WordPress stores for the Engagement tab's rows-per-page choice: 1 to 999, or false
	 * for none, the range `WPCPM_Accounts_Table::per_page_to_save()` allows.
	 *
	 * @param mixed  $keep   What core would store: false.
	 * @param string $option The option's name.
	 * @param mixed  $value  The number as submitted.
	 * @return int|false
	 */
	public static function save_engagement_per_page( $keep, $option, $value ) {
		$rows = (int) $value;

		return ( $rows >= 1 && $rows <= 999 ) ? $rows : false;
	}

	/**
	 * The tabs as the bar prints them: TABS, each label translated.
	 *
	 * @return array<string, string> Slug => label.
	 */
	private static function tab_labels() {
		$translated = array(
			self::TAB_ACCOUNTS   => __( 'Accounts', 'wpcredits-program-manager' ),
			self::TAB_ENGAGEMENT => __( 'Engagement', 'wpcredits-program-manager' ),
			self::TAB_SYNC       => __( 'Sync', 'wpcredits-program-manager' ),
		);

		return array_merge( self::TABS, array_intersect_key( $translated, self::TABS ) );
	}

	/**
	 * Render the screen: the tab bar, then the tab shown.
	 */
	public function render_admin_page() {
		$tab = self::tab();

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html( $this->label() ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html( $this->description() ) . '</p>';

		WPCPM_Screen_Tabs::render( $this->page_slug(), self::tab_labels(), $tab );

		if ( self::TAB_SYNC === $tab ) {
			$this->render_tab_sync();
		} elseif ( self::TAB_ENGAGEMENT === $tab ) {
			$this->render_tab_engagement();
		} else {
			$this->render_tab_accounts();
		}

		echo '</div>';
	}

	/**
	 * The Engagement tab: the headline figures, then every mentor in the list. Read-only.
	 */
	private function render_tab_engagement() {
		$table = $this->engagement_table();

		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Mentor engagement', 'wpcredits-program-manager' ) . '</h2>';
		echo '<ul class="ul-disc">';

		foreach ( WPCPM_Mentor_Engagement::tiles( $table->summary() ) as $tile ) {
			printf(
				'<li><strong>%1$s:</strong> %2$s%3$s</li>',
				esc_html( $tile['name'] ),
				esc_html( number_format_i18n( $tile['n'] ) ),
				'' !== $tile['line'] ? esc_html( ', ' . $tile['line'] ) : ''
			);
		}

		echo '</ul>';
		echo '<p>' . esc_html( WPCPM_Mentor_Engagement::apart( $table->summary() ) ) . '</p>';
		echo '</div>';

		echo '<div class="wpcpm-card" id="' . esc_attr( WPCPM_Mentor_Engagement_Table::ANCHOR ) . '">';
		$table->views();
		printf( '<form method="get" action="#%s">', esc_attr( WPCPM_Mentor_Engagement_Table::ANCHOR ) );
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $this->page_slug() ) );
		printf( '<input type="hidden" name="tab" value="%s" />', esc_attr( self::TAB_ENGAGEMENT ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( WPCPM_Mentor_Engagement_Table::VIEW_ARG ), esc_attr( $table->current_view() ) );
		$table->search_box( esc_html__( 'Search mentors', 'wpcredits-program-manager' ), 'wpcpm-mentor-engagement' );
		$table->display();
		echo '</form>';

		$since = WPCPM_Sign_Ins::since();

		printf(
			'<p class="description">%s</p>',
			esc_html(
				$since > 0
					/* translators: %s: a date. */
					? sprintf( __( 'Sign-ins before %s are approximate: they were read from the site\'s sessions, Two Factor and the activity log on the day of the release.', 'wpcredits-program-manager' ), wp_date( (string) get_option( 'date_format' ), $since ) )
					: __( 'Sign-ins are recorded from this release on; earlier ones are read once, the first time an Administrator opens wp-admin after the update.', 'wpcredits-program-manager' )
			)
		);
		echo '</div>';
	}

	/**
	 * The Engagement tab's table over one report, built once a request.
	 *
	 * @return WPCPM_Mentor_Engagement_Table
	 */
	private function engagement_table() {
		if ( null === $this->engagement ) {
			require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-engagement-table.php';

			$this->engagement = new WPCPM_Mentor_Engagement_Table( WPCPM_Mentor_Engagement::report() );
			$this->engagement->prepare_items();
		}

		return $this->engagement;
	}
```

Update the class docblock's first paragraph (lines 1 to 21) to say the screen is three tabs: Accounts, Engagement (how each mentor uses the site, 1.122.26) and Sync.

- [ ] **Step 5: Run the screen suite, its neighbors and the reference check**

Run: `php bin/test-mentors-screen.php && php bin/test-accounts-table.php && php bin/test-students-screen.php && php bin/test-administrators-screen.php && php bin/check-references.php`
Expected: `ALL PASS` each and a clean reference check. If `check-references.php` asks for a reference in `render_admin_page()` or `tab_labels()`, it is reading the trait's copy: the class's own methods replace the trait's, as the trait's docblock says, and the `as` keeps `load_screen()`'s references the class's to answer for.

- [ ] **Step 6: Commit**

```bash
git add includes/modules/class-wpcpm-mentor-engagement-table.php includes/modules/class-wpcpm-mentors.php bin/test-mentors-screen.php
git commit -m "1.122.26: the Mentors screen's Engagement tab: every mentor's sign-ins, students, schedule, notes and calls, sorted, searched and filtered"
```

---

### Task 6: The Mentor engagement card

**Files:**
- Modify: `includes/modules/class-wpcpm-administrators-cards.php` (`collect()`, `render_mentor_engagement()`)
- Modify: `includes/modules/class-wpcpm-administrators-dashboard.php:283` (one call after `render_programs`)
- Modify: `includes/class-wpcpm-return.php:40` (`ANCHORS`)
- Modify: `blocks/administrator-dashboard/block.json` (the description names the card)
- Test: `bin/test-administrators-dashboard.php`, `bin/test-return.php`

**Interfaces:**
- Consumes: `WPCPM_Mentor_Engagement::report()`, `tiles()`, `apart()`; the cards class's private `card_open()`, `card_close()`.
- Produces: `WPCPM_Administrators_Cards::render_mentor_engagement( array $summary ): void`; `collect()['mentor_engagement']` (the summary only); anchor `mentor-engagement`.

- [ ] **Step 1: Write the failing checks**

In `bin/test-return.php`, line 88, replace the anchors check with:

```php
ck( 'the anchors are the fourteen cards and the strip', WPCPM_Return::ANCHORS, array( 'attention', 'applications', 'agreements', 'reports', 'requests', 'sponsor-applications', 'sponsor-posts', 'sponsor-agreements', 'offers-low', 'duplicates', 'interests', 'sponsors', 'programs', 'mentor-engagement', 'health' ) );
```

In `bin/test-administrators-dashboard.php`, require `includes/modules/class-wpcpm-mentor-engagement.php` beside the cards class (and `class-wpcpm-roles.php` if the suite does not already), call `WPCPM_Mentor_Engagement::for_tests( array( 'accounts' => array(), 'notes' => array(), 'calls' => array() ) );` before the suite's first `collect()`, and add after the programs card checks (line 1078):

```php
$me_summary = array_merge( array_fill_keys( array( 'active', 'signed_in', 'seen_30', 'seen_7', 'never', 'acted', 'idle', 'with_students', 'with_students_hours', 'with_students_link', 'with_students_notes', 'with_students_seen_30', 'calls_held', 'calls_upcoming', 'calls_noted', 'sessions_held', 'sessions_upcoming', 'sessions_noted', 'staff' ), 0 ), array( 'active' => 9, 'seen_30' => 5, 'seen_7' => 2, 'acted' => 4, 'idle' => 3, 'never' => 2, 'with_students' => 6, 'with_students_hours' => 3, 'with_students_notes' => 2, 'calls_held' => 7, 'calls_noted' => 4, 'calls_upcoming' => 1, 'sessions_held' => 2, 'staff' => 1 ) );
$me = capture( static function () use ( $me_summary ) { WPCPM_Administrators_Cards::render_mentor_engagement( $me_summary ); } );
ck( 'the card is a section at its anchor, the canonical disclosure inside', array( has( $me, '<section class="wpcpm-administrator__card" id="wpcpm-mentor-engagement">' ), has( $me, 'wpcpm-group__disclosure' ) ), array( true, true ) );
ck( 'it draws six tiles in the programs card\'s markup', substr_count( $me, 'wpcpm-programs__tile' ), 6 + 1 );
ck( 'each tile its words and number', array( has( $me, 'Active mentors' ), has( $me, '>9<' ), has( $me, '2 in the last 7 days' ), has( $me, '4 with a note, 1 upcoming; 2 group sessions held' ) ), array( true, true, true, true ) );
ck( 'a tile with no line prints no empty line', substr_count( $me, 'wpcpm-programs__l' ), 5 );
ck( 'the Administrators who mentor, and the way to the tab', array( has( $me, 'counted apart: 1.' ), has( $me, 'admin.php?page=wpcpm-mentors&amp;tab=engagement' ) || has( $me, 'admin.php?page=wpcpm-mentors&tab=engagement' ) ), array( true, true ) );
ck( 'an empty summary draws nothing', capture( static function () { WPCPM_Administrators_Cards::render_mentor_engagement( array() ); } ), '' );
ck( 'collect() reads the summary, never the rows', array_key_exists( 'mentor_engagement', WPCPM_Administrators_Cards::collect() ) && ! array_key_exists( 'rows', WPCPM_Administrators_Cards::collect()['mentor_engagement'] ) );
$order_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-dashboard.php' );
ck( 'the card comes after Programs running and before Syncs and health', strpos( $order_src, 'render_programs(' ) < strpos( $order_src, 'render_mentor_engagement(' ) && strpos( $order_src, 'render_mentor_engagement(' ) < strpos( $order_src, 'render_health(' ) );
```

If the suite already counts every card the page draws (search it for `card_open` and a number of cards), add one to that count with a comment naming the Mentor engagement card.

- [ ] **Step 2: Run them to see them fail**

Run: `php bin/test-return.php; php bin/test-administrators-dashboard.php 2>&1 | grep -E "FAIL|Fatal|ALL PASS" | head`
Expected: test-return FAILs the anchors list; the dashboard suite fails with "Call to undefined method WPCPM_Administrators_Cards::render_mentor_engagement()".

- [ ] **Step 3: Write the card**

In `includes/class-wpcpm-return.php`, line 40, put `'mentor-engagement'` between `'programs'` and `'health'`.

In `WPCPM_Administrators_Cards::collect()`, after `'programs' => self::programs(),`:

```php
			// How the mentors use the site (1.122.26): the figures only, never the rows, read through
			// the class the Engagement tab and the command read too.
			'mentor_engagement'    => class_exists( 'WPCPM_Mentor_Engagement' ) ? WPCPM_Mentor_Engagement::report()['summary'] : array(),
```

After `render_programs()` add:

```php
	/**
	 * How the mentors use the site: six tiles in the Programs running card's markup, so the two look
	 * alike with no rule of their own, the Administrators who mentor counted apart, and the way to
	 * the Mentors screen's Engagement tab, where every mentor is listed. Read-only; nothing when the
	 * summary is empty.
	 *
	 * @param array $summary What `WPCPM_Mentor_Engagement::report()` returned as `summary`.
	 */
	public static function render_mentor_engagement( array $summary ) {
		if ( empty( $summary ) ) {
			return;
		}

		self::card_open( 'mentor-engagement', __( 'Mentor engagement', 'wpcredits-program-manager' ), (int) $summary['active'] );

		echo '<ul class="wpcpm-programs__tiles">';

		foreach ( WPCPM_Mentor_Engagement::tiles( $summary ) as $tile ) {
			printf(
				'<li class="wpcpm-programs__tile"><span class="wpcpm-programs__name">%1$s</span><span class="wpcpm-programs__n">%2$s</span>%3$s</li>',
				esc_html( $tile['name'] ),
				esc_html( number_format_i18n( (int) $tile['n'] ) ),
				'' !== $tile['line'] ? '<span class="wpcpm-programs__l">' . esc_html( $tile['line'] ) . '</span>' : ''
			);
		}

		echo '</ul>';

		printf( '<p class="wpcpm-administrator__quiet">%s</p>', esc_html( WPCPM_Mentor_Engagement::apart( $summary ) ) );
		printf(
			'<p class="wpcpm-administrator__read"><a href="%1$s">%2$s</a></p>',
			esc_url( admin_url( 'admin.php?page=wpcpm-mentors&tab=engagement' ) ),
			esc_html__( 'Open the Mentors screen\'s Engagement tab', 'wpcredits-program-manager' )
		);

		self::card_close();
	}
```

In `WPCPM_Administrators_Dashboard::render()`, after `WPCPM_Administrators_Cards::render_programs( $data['programs'] );`:

```php
		WPCPM_Administrators_Cards::render_mentor_engagement( isset( $data['mentor_engagement'] ) ? (array) $data['mentor_engagement'] : array() );
```

In `blocks/administrator-dashboard/block.json`, add "mentor engagement" to the description's list of what the page shows, in the list's own wording.

- [ ] **Step 4: Run the suites**

Run: `php bin/test-return.php && php bin/test-administrators-dashboard.php && php bin/test-mentor-engagement.php`
Expected: `ALL PASS` three times.

- [ ] **Step 5: Commit**

```bash
git add includes/modules/class-wpcpm-administrators-cards.php includes/modules/class-wpcpm-administrators-dashboard.php includes/class-wpcpm-return.php blocks/administrator-dashboard/block.json bin/test-administrators-dashboard.php bin/test-return.php
git commit -m "1.122.26: the Administrator Dashboard's Mentor engagement card, after Programs running, in its tiles"
```

---

### Task 7: The command

**Files:**
- Modify: `includes/class-wpcpm-cli.php` (method `mentor_engagement()`)
- Create: `bin/stubs/wp-cli-utils.php`
- Test: `bin/test-cli.php`

**Interfaces:**
- Consumes: `WPCPM_Mentor_Engagement::report()`, `views()`, `in_view()`, `sorted()`, `export_row()`, `EXPORT_FIELDS`, `VIEW_ALL`.
- Produces: `wp wpcredits mentor_engagement [--summary] [--view=<view>] [--format=<format>] [--now=<time>]`. With `--summary --format=json` it prints one line, the summary object, which the biweekly task reads (Task 11).

- [ ] **Step 1: Write the failing checks**

Create `bin/stubs/wp-cli-utils.php`:

```php
<?php
/**
 * WP-CLI's `format_items()`, stood in: it keeps what it was asked to print, so a suite can read it.
 *
 * Loaded with `require __DIR__ . '/stubs/wp-cli-utils.php';` from a suite whose WP_CLI stand-in has
 * a public static `$lines`.
 */

namespace WP_CLI\Utils;

function format_items( $format, $items, $fields ) {
	\WP_CLI::$lines[] = 'items: ' . $format . ' ' . json_encode( array( 'fields' => array_values( (array) $fields ), 'items' => array_values( (array) $items ) ) );
}
```

In `bin/test-cli.php`: add `public static function line( $message ) { self::$lines[] = 'line: ' . $message; }` to the `WP_CLI` stand-in; after it, `require __DIR__ . '/stubs/wp-cli-utils.php';`; define `DAY_IN_SECONDS` and `WPCPM_PLUGIN_DIR` if the suite does not; stub `function wp_json_encode( $d ) { return json_encode( $d ); }` and `function number_format_i18n( $n ) { return number_format( (float) $n ); }` if absent; require `includes/class-wpcpm-roles.php` and `includes/modules/class-wpcpm-mentor-engagement.php` before `includes/class-wpcpm-cli.php`. Then add a section before the suite's last line:

```php
echo "\n=== wp wpcredits mentor_engagement ===\n";

$me_rows = array(
	array( 'id' => 1, 'name' => 'Ada', 'login' => 'ada', 'email' => 'ada@example.test', 'roles' => array( 'wpcpm_mentor' ), 'active' => true, 'students_now' => 2, 'students_past' => 0, 'last_sign_in' => 1792000000, 'last_visit' => 1792000000, 'schedule_saved' => true, 'weekly_hours' => true, 'meeting_link' => false ),
	array( 'id' => 2, 'name' => 'Bo', 'login' => 'bo', 'email' => 'bo@example.test', 'roles' => array( 'wpcpm_mentor' ), 'active' => true, 'students_now' => 0, 'students_past' => 0, 'last_sign_in' => 0, 'last_visit' => 0, 'schedule_saved' => false, 'weekly_hours' => false, 'meeting_link' => false ),
);
WPCPM_Mentor_Engagement::for_tests( array( 'accounts' => $me_rows, 'notes' => array(), 'calls' => array() ) );
$cli = new WPCPM_CLI();

WP_CLI::$lines = array();
$cli->mentor_engagement( array(), array( 'summary' => true, 'format' => 'json', 'now' => 1792100000 ) );
$json = json_decode( substr( WP_CLI::$lines[0], strlen( 'line: ' ) ), true );
ck( '--summary --format=json prints the summary as one object', array( count( WP_CLI::$lines ), $json['active'], $json['never'], $json['with_students_hours'] ), array( 1, 2, 1, 1 ) );

WP_CLI::$lines = array();
$cli->mentor_engagement( array(), array( 'view' => 'never', 'format' => 'csv', 'now' => 1792100000 ) );
ck( 'a view prints its rows with the export fields, and no email anywhere', array( false !== strpos( WP_CLI::$lines[0], '"Bo"' ), false === strpos( WP_CLI::$lines[0], '"Ada"' ), false === strpos( WP_CLI::$lines[0], '@example.test' ), 0 === strpos( WP_CLI::$lines[0], 'items: csv ' ) ), array( true, true, true, true ) );

foreach ( array( array( 'format' => 'xml' ), array( 'view' => 'nope' ) ) as $bad ) {
	WP_CLI::$lines = array();
	$threw = '';
	try {
		$cli->mentor_engagement( array(), $bad );
	} catch ( ExitSignal $e ) {
		$threw = $e->getMessage();
	}
	ck( 'refused: ' . json_encode( $bad ), '' !== $threw );
}

WPCPM_Mentor_Engagement::for_tests( null );
```

- [ ] **Step 2: Run it to see it fail**

Run: `php bin/test-cli.php 2>&1 | tail -5`
Expected: "Call to undefined method WPCPM_CLI::mentor_engagement()".

- [ ] **Step 3: Write the command**

Add to `WPCPM_CLI`, after `outcome_figures()` and its helpers:

```php
	/**
	 * Show how each mentor uses the site: sign-ins, visits, students, schedule, notes and calls.
	 *
	 * Reads only. The figures are the Mentors screen's Engagement tab's and the Administrator
	 * Dashboard's Mentor engagement card's, counted the same way.
	 *
	 * ## OPTIONS
	 *
	 * [--summary]
	 * : Print the headline figures instead of one row per mentor.
	 *
	 * [--view=<view>]
	 * : One of the Engagement tab's views: all, active, students, never, quiet, idle, no-hours, staff. Default: all.
	 *
	 * [--format=<format>]
	 * : table, json or csv. Default: table.
	 *
	 * [--now=<time>]
	 * : Count as of this Unix time instead of now.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpcredits mentor_engagement --summary
	 *     wp wpcredits mentor_engagement --summary --format=json
	 *     wp wpcredits mentor_engagement --view=never --format=csv
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function mentor_engagement( $args, $assoc_args ) {
		unset( $args );

		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';
		$view   = isset( $assoc_args['view'] ) ? (string) $assoc_args['view'] : WPCPM_Mentor_Engagement::VIEW_ALL;
		$views  = WPCPM_Mentor_Engagement::views();

		if ( ! in_array( $format, array( 'table', 'json', 'csv' ), true ) ) {
			WP_CLI::error( 'Use --format=table, json or csv.' );
		}

		if ( ! isset( $views[ $view ] ) ) {
			WP_CLI::error( sprintf( 'There is no view "%1$s". Use one of: %2$s.', $view, implode( ', ', array_keys( $views ) ) ) );
		}

		$report = WPCPM_Mentor_Engagement::report( isset( $assoc_args['now'] ) ? (int) $assoc_args['now'] : 0 );

		if ( ! empty( $assoc_args['summary'] ) ) {
			if ( 'json' === $format ) {
				WP_CLI::line( (string) wp_json_encode( $report['summary'] ) );

				return;
			}

			$items = array();

			foreach ( $report['summary'] as $figure => $value ) {
				$items[] = array(
					'figure' => $figure,
					'value'  => $value,
				);
			}

			WP_CLI\Utils\format_items( $format, $items, array( 'figure', 'value' ) );

			return;
		}

		$rows = array();

		foreach ( WPCPM_Mentor_Engagement::sorted( $report['rows'] ) as $row ) {
			if ( WPCPM_Mentor_Engagement::in_view( $row, $view ) ) {
				$rows[] = WPCPM_Mentor_Engagement::export_row( $row );
			}
		}

		WP_CLI\Utils\format_items( $format, $rows, WPCPM_Mentor_Engagement::EXPORT_FIELDS );
	}
```

- [ ] **Step 4: Run the suite**

Run: `php bin/test-cli.php`
Expected: `ALL PASS`.

- [ ] **Step 5: Commit**

```bash
git add includes/class-wpcpm-cli.php bin/stubs/wp-cli-utils.php bin/test-cli.php
git commit -m "1.122.26: wp wpcredits mentor_engagement prints the figures or the rows, and writes nothing"
```

---

### Task 8: The guide, the version and the changelog

**Files:**
- Modify: `docs/sections/30-admin-wpadmin.md` (the Mentors row, line 90; the 1.119.0 paragraph, line 107)
- Modify: `docs/sections/34-admin-operations.md` (the Administrator Dashboard's cards)
- Modify: `wpcredits-program-manager.php` (header `Version:` line 6, `WPCPM_VERSION` line 22), `readme.txt` (Stable tag line 7, changelog), `languages/wpcredits-program-manager.pot`
- Generated: `docs/build/` by `php bin/build-docs.php`

- [ ] **Step 1: The guide**

In `docs/sections/30-admin-wpadmin.md`, line 90, make the Mentors row read:

```markdown
| **Mentors** | Three tabs: **Accounts**, the invitations and every Mentor account in one list; **Engagement**, how each mentor uses the site; and **Sync**, the mentors sync and its last report. |
```

and after the paragraph at line 107 add:

```markdown
Since 1.122.26 the Mentors screen has a third tab between them, **Engagement**. It opens with the figures the Administrator Dashboard's **Mentor engagement** card shows, then lists every mentor: whether Airtable holds them as Active, their students now and before, their **Last visit** (the first day they opened the site that day, signed in) and **Last sign-in**, their **Schedule** (weekly hours and a meeting link, or what is missing), the **Notes** they wrote, their one-to-one **Calls** held and to come with how many held calls have a note, and their **Group sessions**. Sort by any of these, search by name, username or email, and use the views to find who to follow up with: **Never signed in**, **Not seen in 30 days**, **No program action** (no note, no saved schedule, no group session created and no call they cancelled) and **Students, no weekly hours**. Those views and the figures count active mentors only, and an Administrator who also mentors is counted apart. The tab reads only. Sign-ins from before the release were read once from what the site already held and are approximate; the note under the list gives the date. `wp wpcredits mentor_engagement --summary` prints the same figures on the command line.
```

In `docs/sections/34-admin-operations.md`, where the Administrator Dashboard's cards are described in their order, add after Programs running:

```markdown
**Mentor engagement** shows six figures about the active mentors (Administrators who also mentor are counted apart): how many there are, how many were seen on the site in the last 30 days (and 7), how many took a program action, how many never signed in, how many have students now (and of them, how many offer weekly hours and wrote notes), and how many one-to-one calls were held (with a note, to come) and group sessions held. **Open the Mentors screen's Engagement tab** lists every mentor behind the figures.
```

- [ ] **Step 2: Build the guide and check the spelling**

Run: `php bin/build-docs.php && php bin/check-spelling.php`
Expected: the build writes `docs/build/`; the spelling check passes (US English).

- [ ] **Step 3: The version**

Set `Version: 1.122.26` in the plugin header, `define( 'WPCPM_VERSION', '1.122.26' );`, `Stable tag: 1.122.26` in `readme.txt`, and add above `= 1.122.25 =`:

```text
= 1.122.26 =

* The site records when each account last signed in and when it last opened the site, the first signed-in page of the day. A sign-in that asks for a two-factor code counts once the code is accepted. Sign-ins from before this release are read once from what the site already held, the first time an Administrator opens wp-admin after the update.
* The Mentors screen has a new Engagement tab: every mentor's last visit and sign-in, students, schedule, notes, one-to-one calls and group sessions, sorted, searched and filtered, with views for the mentors to follow up with. It reads only.
* The Administrator Dashboard has a Mentor engagement card after Programs running, with the same figures and a link to the tab.
* `wp wpcredits mentor_engagement` prints the figures (`--summary`) or one row per mentor, as a table, JSON or CSV, and writes nothing.
```

Then regenerate the translation template:

```bash
bash bin/make-pot.sh
grep -c "Mentor engagement" languages/wpcredits-program-manager.pot
```

Expected: a count of at least 1, and `Project-Id-Version: WPCredits Program Manager 1.122.26` in the template's header.

- [ ] **Step 4: Run every suite and the standards**

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAILED: $f"; done; echo battery done
bash bin/check-standards.sh 2>&1 | tail -15
php bin/check-references.php
grep -rn -P "\x{2014}|\x{2013}" includes bin docs/sections readme.txt wpcredits-program-manager.php uninstall.php || echo "no dashes"
```

Expected: `battery done` with no FAILED line (run a failing suite on its own to read why); standards with no new error and warnings at or under the release script's baseline; references clean; `no dashes`.

- [ ] **Step 5: Commit**

```bash
git add docs/sections/30-admin-wpadmin.md docs/sections/34-admin-operations.md docs/build wpcredits-program-manager.php readme.txt languages/wpcredits-program-manager.pot
git commit -m "1.122.26: the guide, the version and the changelog"
```

---

### Task 9: Check the numbers and the look on the Docker copy

No new repository files; scratch scripts and captures go in the session's scratchpad and are backed up under `.superpowers/session-backups/<the day's date, YYYY-MM-DD>/mentor-engagement/` (the scratchpad is swept after three days).

- [ ] **Step 1: Snapshot the copy and install the branch's build**

```bash
cd ~/Projects/wpcredits-docker && bin/snapshot && bin/check
cd ~/Projects/wpcredits-release && python3 release.py check plugin --site local
```

Then install the branch's build on the copy the way the release script's `deploy plugin --site local` does (its dry run first, then `--yes`), and confirm `bin/wp plugin get wpcredits-program-manager --field=version` reads `1.122.26`.

- [ ] **Step 2: The reader agrees with the 15 Oct probes on the same database**

Copy `probe3.php` and `probe4.php` from `.superpowers/session-backups/2026-10-15/mentor-activity/` and run them on the copy with `bin/wp eval-file`, saving the JSON; then run:

```bash
bin/wp eval 'echo wp_json_encode( WPCPM_Mentor_Engagement::report( 1792067069 ) );' > engagement-copy.json
```

and compare with a short Python script (`python3 -I`) that rebuilds `analyze.py`'s per-mentor numbers from the two probe files, with `NOW = 1792067069`, and checks, per mentor: `students_now`, `schedule_saved`, `weekly_hours`, `meeting_link` (`windows > 0 and meet_url`), `notes`, `notes_30`, `sessions_created`, `cancelled_by_mentor`, `calls_booked`, `acted`; and the summary's `active`, `with_students`, `with_students_hours`, `with_students_link`, `with_students_notes`, `calls_held`, `calls_upcoming`, `calls_noted`, `sessions_held`, `sessions_upcoming`. Expected: every one equal. The sign-in figures differ by design (the copy has no Jetpack log and the seed has not run); they are checked live in Task 10.

- [ ] **Step 3: The seed runs once on the copy**

Open `http://localhost:8900/wp-admin/` once as an Administrator of the copy (the copy's own test account), then:

```bash
bin/wp option get wpcpm_sign_ins_version
bin/wp eval 'echo WPCPM_Sign_Ins::since() > 0 ? "seeded" : "not seeded";'
bin/wp eval '$n = 0; foreach ( get_users( array( "fields" => "ID", "number" => -1 ) ) as $id ) { $n += (int) get_user_meta( $id, "wpcpm_last_sign_in", true ) > 0 ? 1 : 0; } echo $n;'
```

Expected: `1`, `seeded`, and a count close to the number of accounts holding a session or a Two Factor stamp on the copy.

- [ ] **Step 4: The look and the spacing**

In the built-in browser at `http://localhost:8900/administrator-dashboard/` (signed in as the copy's Administrator): screenshot the Programs running and Mentor engagement cards together, then run the computed-style compare of the dashboards-look-alike rule on the pair (each tile's font, margin, padding, gap; the gap between the two cards and between Mentor engagement and Syncs and health). Expected: every pair "same", and the gaps equal to the Programs running card's. Then open `http://localhost:8900/wp-admin/admin.php?page=wpcpm-mentors&tab=engagement`, check the three tabs, a sort, a view, a search, Screen Options hiding a column on Engagement does not hide one on Accounts, and screenshot it at 1280px and 375px.

- [ ] **Step 5: Put the copy back**

```bash
cd ~/Projects/wpcredits-docker && bin/restore && bin/check
```

Back up the scratch scripts, the JSON and the screenshots in that folder. Report the comparison and the screenshots to the owner.

---

### Task 10: Release, only on the owner's go

- [ ] **Step 1: Read the live version, build and scan**

```bash
cd ~/Projects/wpcredits-release
python3 release.py check plugin
python3 release.py build plugin
python3 release.py scan plugin
```

Expected: check reads live 1.122.25 (stop if it reads anything else and ask the owner), every step PASSED, the zip at `~/GitHub/wpcredits-program-manager.zip` with its md5 printed. Merge `feature/mentor-engagement` into `main` before the build only on the owner's yes.

- [ ] **Step 2: Ask the owner for the go, with the facts**

Give the owner: the version, the zip's md5, the battery and standards results, the Task 9 comparison and screenshots, and the post-deploy steps below. Wait for an explicit yes to deploy.

- [ ] **Step 3: Deploy**

```bash
python3 release.py deploy plugin
python3 release.py deploy plugin --yes
```

- [ ] **Step 4: Let the seed run, then import the 15 Oct sign-ins**

Open `https://wordpresseducation.org/wp-admin/` once as an Administrator (the owner, or the session's browser if the owner is signed in there), then check `ssh wpcredits-dashboard 'wp option get wpcpm_sign_ins_version'` reads `1`.

Build the import file from `rows.json` (IDs and times only):

```bash
cd ~/GitHub/wpcredits-program-manager/.superpowers/session-backups/2026-10-15/mentor-activity
python3 -I -c "import json; R=json.load(open('rows.json')); json.dump({str(r['id']): int(r['login_last']) for r in R if r['login_last']}, open('import-2026-10-15.json','w'))"
python3 -I -c "import json; d=json.load(open('import-2026-10-15.json')); print(len(d))"
```

Expected: the number of mentors with a 15 Oct sign-in.

The import script, `import-2026-10-15.php`, kept beside it:

```php
<?php
// One-off, 1.122.26 deploy: raise each mentor's wpcpm_last_sign_in to the 15 Oct Jetpack-derived
// value when it is absent or earlier. Never lowers a date. IDs and times only. Run with
// `wp eval-file import-2026-10-15.php` from the folder the JSON was piped to.
$data   = json_decode( (string) file_get_contents( __DIR__ . '/import-2026-10-15.json' ), true );
$raised = 0;
$left   = 0;

foreach ( (array) $data as $id => $time ) {
	$id   = (int) $id;
	$time = (int) $time;
	$user = get_userdata( $id );

	if ( ! $user || $time <= 0 || ( ! in_array( 'wpcpm_mentor', (array) $user->roles, true ) && '' === (string) get_user_meta( $id, 'wpcpm_mentor_record_id', true ) ) ) {
		++$left;
		continue;
	}

	$stored = (int) get_user_meta( $id, WPCPM_Sign_Ins::META_SIGN_IN, true );

	if ( $stored >= $time ) {
		++$left;
		continue;
	}

	update_user_meta( $id, WPCPM_Sign_Ins::META_SIGN_IN, $time );
	++$raised;
}

echo "raised {$raised}, left {$left}\n";
```

Send both files to the server over stdin (scp fails silently on this host), then run:

```bash
ssh wpcredits-dashboard 'mkdir -p ~/tmp/me-import && cat > ~/tmp/me-import/import-2026-10-15.json' < import-2026-10-15.json
ssh wpcredits-dashboard 'cat > ~/tmp/me-import/import-2026-10-15.php' < import-2026-10-15.php
ssh wpcredits-dashboard 'cd ~/tmp/me-import && wp eval-file import-2026-10-15.php && rm -rf ~/tmp/me-import'
```

Expected: `raised N, left M` with N + M the count printed above.

- [ ] **Step 5: Compare with the 15 Oct answer**

```bash
ssh wpcredits-dashboard 'wp wpcredits mentor_engagement --summary --format=json'
```

Expected: `active` equal to the 15 Oct answer and `signed_in` at least its figure (more if mentors signed in since the probe); the action, schedule and call figures within what changed since 15 Oct. Any figure that moved the wrong way (for example `signed_in` below the 15 Oct figure) stops here: report it to the owner, nothing else runs.

- [ ] **Step 6: The guide page, the privacy notice, the caches**

Republish guide page 560 from the build the way the 1.122.19 deploy did; add to the draft privacy notice (page 654) the sentence "The site records when you last signed in and when you last visited it; the program's Administrators can see both." and leave the page a draft; purge the caches. Each content change only on the owner's yes in this step.

- [ ] **Step 7: The mirror**

```bash
python3 release.py mirror plugin --message message.txt
python3 release.py mirror plugin --yes --message message.txt
```

On the owner's yes. Then the vault: the spec's status line becomes "shipped as 1.122.26 on <date>", the plugin note in the vault and the memory note `wpcredits-program-manager-plugin` gain the release paragraph.

---

### Task 11: The biweekly line, after the release is live

**Files:**
- Modify: `~/.claude/scheduled-tasks/wpcredits-biweekly-update/` (the task's prompt; its `state.json` gains `mentor_engagement` on the first run)

- [ ] **Step 1: Draft the step**

Add to the task's prompt, after it reads the versions and changelogs and before it drafts:

```text
Mentor engagement line. Run, read-only: ssh wpcredits-dashboard 'wp wpcredits mentor_engagement --summary --format=json'.
If it fails or prints nothing, leave the line out and say so in this run's notes.
Otherwise store the figures with today's date in state.json under "mentor_engagement" (keep the previous entry as "mentor_engagement_previous"),
and add one line to the draft, counts only, never a name:
"Mentors: <seen_30> of <active> active mentors used the site in the last 30 days (<change> since <previous date>); <with_students_hours> of the <with_students> with students offer weekly hours."
On the first run, with no previous entry, leave out the part in brackets.
```

- [ ] **Step 2: Show the owner and save only on yes**

Show the owner the exact text to be added and where it goes in the prompt. Save it only on the owner's explicit yes. The task's approval gate for posting is unchanged.

---

## After the plan

- Parked for later, if staff ask: a CSV download on the tab; engagement views for students or institutions (the sign-in record is already there for them).
