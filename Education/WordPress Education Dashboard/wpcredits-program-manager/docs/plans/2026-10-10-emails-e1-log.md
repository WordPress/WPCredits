# Emails E1: the 30-day log, Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Record every email the site sends, for 30 days, in a table an Administrator can search and filter under WPCredits Program > Tools > Emails, and point Settings > Mail's "Recent mail" and the Administrator Dashboard's health card at it.

**Architecture:** A catalog names every email the site can send (the plugin's 42 contexts, WordPress's own and the Two Factor sign-in code). A store keeps one row per recipient in a new table, and boots from the plugin's bootstrap: the table's upgrade, the daily cleanup, the privacy tools and the capture. The capture hooks `wp_mail` (first and last), `pre_wp_mail`, `wp_mail_succeeded`, `wp_mail_failed` and `shutdown`, and keeps a stack, so a nested or unfinished email is written under its own name. A new tool, Emails, draws the rows as a WordPress list table with search and filters; the Email filter is the Viewing as combobox, split out of the switcher as `WPCPM_Dashboards::render_combo()`.

**Tech Stack:** WordPress 7.1 plugin PHP (7.4 and 8.x), `$wpdb` and `dbDelta()`, WordPress list tables, the plugin's `bin/test-*.php` suites (plain PHP with stubs), PDO SQLite for the new store's tests, vanilla JavaScript (`assets/js/switcher.js`, reused unchanged).

**Spec:** `docs/specs/2026-10-10-emails-tool-design.md` (approved by the owner on 10 October 2026). This plan covers its first release only, "1.122.18: the log".

**Order:** the catalog (Task 1); the store (Task 2); the readers, Settings > Mail and the health card, which need only the store and the catalog (Task 3); the capture, and `WPCPM_Mail` without its own log, once nothing reads that log (Task 4); `render_combo()` (Task 5); the Emails tool, its list table, its stylesheet and its registration (Task 6); the guide, the version, the changelog and the template (Task 7).

## Global Constraints

- Version **1.122.18** in the plugin header (`Version:`), `WPCPM_VERSION` and readme.txt's `Stable tag`, with a changelog entry and the translation template regenerated (`sh bin/make-pot.sh`).
- US English; plain hyphens only, never an em or en dash, in code, comments, strings and docs.
- Full product names: Administrator Dashboard, Student Report Card, Mentor Report Card, Student Duplicate Finder, Collaboration Agreement; the program's managers are "Administrators".
- No translated string anywhere says "module" (bin/test-admin-menu.php refuses one: the owner's one-vocabulary rule of 28 September 2026). On screen the Log's column, its filter, the guide and the changelog say **Area** ("Area", with "Every area" or "All" as a filter's first option). The spec's "Module" (the Log's column and filter) is this plan's "Area"; the code keeps `module` as the table's column, the `WPCPM_Mail_Catalog::MODULE_*` constants and `modules()`, whose values are Invitations, Mentor calls, Institutions, Semester reports, Sponsors, WordPress, Two Factor and Other.
- No `!important` in any plugin stylesheet (bin/test-institution-panel.php refuses it).
- Every box a stylesheet lets scroll carries a position (bin/test-institution-panel.php refuses one that does not).
- Examples and test data use invented names and `@example.test` addresses, never real people or institutions (bin/test-fixtures.php).
- The log never stores message text, attachments or anything secret; it keeps who, what, when and status (owner's decision 4).
- The log records every email the site sends, not only the plugin's (owner's decision 3), and an email whose outcome never comes is still written, as Not confirmed.
- Entries are kept 30 days.
- The log boots from the plugin's bootstrap (`WPCPM_Mail_Log::init()`, called in `wpcpm_bootstrap()` beside `WPCPM_Mail::init()`), never from the Emails tool, so filtering the tool out through `wpcpm_tools` cannot stop the recording. The tool draws the screen and its status line, and schedules nothing it does not own.
- Administrators only: every screen and handler checks `WPCPM_Roles::CAP_MANAGE` (`wpcpm_manage_program`).
- A choice from a long list is one field that drops down, takes typing and narrows (the owner's rule): the Email filter is that combobox (`WPCPM_Dashboards::render_combo()`); short lists stay plain selects.
- SQL must run on MySQL (the live site) and on WordPress's SQLite integration (the local copies, and the suites through `bin/stubs/sqlite-wpdb.php`): no `DELETE ... LIMIT`, no `SHOW` statements except `SHOW TABLES LIKE`. LIKE searches escape with `$wpdb->esc_like()` and write no ESCAPE clause: MySQL and WordPress's SQLite integration both read a backslash, and the integration adds `ESCAPE '\'` itself.
- Every link to the Log carries `&tab=log`, so the tabs of the next release keep it.
- Every method in a new file under `includes/` has a docblock; `sh bin/check-standards.sh` gains no error.
- Every task ends with the whole battery: every `bin/test-*.php` in its own process, all passing, and `php bin/check-references.php`, as its last step before the commit shows.
- Never `git stash`. Commit after each task's battery passes.
- Public text (this repository is mirrored publicly) names no unreleased or private work.

## Review Focus

1. **Several recipients, and what WordPress hands the outcome hooks.** `wp_mail( 'Ada Lin <ada@example.test>, ben@example.test, , not-an-address', ..., "Cc: cy@example.test\r\nBcc: Dee <dee@example.test>" )` writes one row per address (four), none for a blank or a non-address, the name form reduced to its address; so does an array of two header lines. The rows are read from the email as the last `wp_mail` filter left it, never from the outcome hook's data, whose headers WordPress has already parsed without Cc, Bcc, From or Reply-To. Pinned in Task 4.
2. **Nested and unfinished sends.** The capture keeps a stack: an email another plugin sends from its `wp_mail_succeeded` listener is written, and so is the one it followed, each under its own name; an email sent from another plugin's `wp_mail` filter does not take the plugin's context; an email whose outcome never fires is written as Not confirmed at shutdown, under its own name, and lends nothing to the next. Pinned in Task 4.
3. **Literal search on MySQL and the SQLite integration.** "100%", "o'brien", "a_b" and "a\b" are found literally and match nothing else, and "a%b" finds nothing; the search sends no ESCAPE clause of its own. Pinned in Task 2.
4. **The Email combobox wiring up in wp-admin.** `render_combo()` prints the label too, so its id is the listbox's `aria-labelledby`, its `for` is the select's id, and the field's `aria-controls` is the listbox's id: the three links assets/js/switcher.js follows to wire the field. Pinned in Tasks 5 and 6.
5. **No table.** When the table is missing (an upgrade that has not run yet, or failed), sending email still works, nothing fatal is raised, the Log says the log is not ready, the tool's status line says so, and the upgrade is tried again on the next load. Pinned in Tasks 2, 4 and 6.

The time-zone checks (Today from local midnight in Europe/Warsaw) stay in Task 6's tests.

---

## File Structure

| File | Responsibility |
| --- | --- |
| `includes/mail/class-wpcpm-mail-catalog.php` (new, Task 1) | `WPCPM_Mail_Catalog`: the areas, the recipient types, and every email the site can send, by id, with its label, area and audience; the WordPress and Two Factor filters that name an email. Pure data and lookups. The template registry of the next release grows out of it. |
| `includes/mail/class-wpcpm-mail-log.php` (new, Task 2) | `WPCPM_Mail_Log`: its boot (`init()`), the table (schema, creation, upgrade, the move of the old option), rows in and out (`add()`, `find()`, `latest()`, `count_since()`, `failures_since()`), the cleanup, the privacy export and erase, deactivation and uninstall. |
| `bin/stubs/sqlite-wpdb.php` (new, Task 2) | A `wpdb` over in-memory SQLite that rewrites what WordPress's SQLite integration rewrites (`SHOW TABLES LIKE`, `LIKE ... ESCAPE '\'`). |
| `includes/class-wpcpm-settings-screen.php`, `includes/modules/class-wpcpm-administrators-cards.php` (Task 3) | Settings > Mail's Recent mail points to the Log; the health card reads the latest email and the last day's failures from the log. |
| `includes/mail/class-wpcpm-mail-capture.php` (new, Task 4) | `WPCPM_Mail_Capture`: the mail hooks, the stack and the naming filters; turns one email into rows (recipients, recipient type, area, template, status). |
| `includes/class-wpcpm-mail.php` (Task 4) | Gives up its own log (`LOG_OPTION`, `LOG_MAX`, the two outcome hooks, `mail_succeeded()`, `mail_failed()`, `record()`, `mask_recipients()`, `log()`, `clear_log()`, `failures()`); gains `take_context()`; keeps `mask_address()`. |
| `includes/class-wpcpm-dashboards.php` (Task 5) | `render_combo()` split out of `render_switcher()`: the label, the select and the hidden combobox, so a form other than the switcher can draw the field. |
| `includes/tools/class-wpcpm-emails.php` (new, Task 6) | `WPCPM_Emails extends WPCPM_Tool`: the Emails screen and its status line. |
| `includes/mail/class-wpcpm-mail-log-table.php` (new, Task 6) | `WPCPM_Mail_Log_Table extends WP_List_Table`: the Log's columns and paging. Required only inside `WPCPM_Emails::render_admin_page()`. |
| `assets/css/emails.css` (new, Task 6) | The Emails screen: the filter row and the combobox's rules for wp-admin. |
| `includes/class-wpcpm-tools.php`, `wpcredits-program-manager.php`, `uninstall.php` | Register and load the new files (Tasks 1, 2, 4 and 6); boot and deactivate the log (Task 2). |
| `docs/sections/30-admin-wpadmin.md` (Tasks 6 and 7), `docs/sections/31-admin-settings.md`, `docs/sections/32-admin-tools.md`, then `docs/*.md`, `docs/build/*.html` (Task 7) | The administrators' guide. |
| `bin/test-mail-catalog.php`, `bin/test-mail-log.php`, `bin/test-mail-capture.php`, `bin/test-combo-field.php`, `bin/test-emails-screen.php` (new); `bin/test-roles.php`, `bin/test-uninstall.php`, `bin/test-settings.php`, `bin/test-administrators-dashboard.php`, `bin/stubs/overview-reads.php`, `bin/test-mail.php`, `bin/test-admin-menu.php` | The suites, each in the task that changes what it reads. |

Every suite is plain PHP run as `php bin/<suite>.php`, printing `ok` / `FAIL` lines and a last line `ALL PASS (N checks)` or `N FAILURE(S)`, and exiting non-zero on a failure. A new suite carries the `ck()` helper below, as every suite does (each new suite in this plan is written out whole, helper included):

```php
$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}
```

and ends with:

```php
printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

"The whole battery" is every suite in its own process, then the references check, which every task's last step runs:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the references check ends `- all resolve`.

---

### Task 1: The catalog

**Files:**
- Create: `includes/mail/class-wpcpm-mail-catalog.php`
- Create: `bin/test-mail-catalog.php`
- Modify: `wpcredits-program-manager.php` (require the file after `includes/class-wpcpm-mail.php`, line 46), `uninstall.php` (the same require, after its own `includes/class-wpcpm-mail.php`, line 51)
- Modify: `bin/test-roles.php` (its on-disk scan of class files, lines 376 to 389, reads `includes/mail/` too)

**Interfaces:**
- Consumes: nothing of this plan.
- Produces:
  - `WPCPM_Mail_Catalog::MODULE_INVITATIONS` `'invitations'`, `MODULE_CALLS` `'calls'`, `MODULE_INSTITUTIONS` `'institutions'`, `MODULE_REPORTS` `'reports'`, `MODULE_SPONSORS` `'sponsors'`, `MODULE_WORDPRESS` `'wordpress'`, `MODULE_TWO_FACTOR` `'two-factor'`, `MODULE_OTHER` `'other'` (the areas).
  - `TYPE_STUDENT` `'student'`, `TYPE_MENTOR` `'mentor'`, `TYPE_INSTITUTION` `'institution'`, `TYPE_SPONSOR` `'sponsor'`, `TYPE_ADMIN` `'administrator'`, `TYPE_APPLICANT` `'applicant'`, `TYPE_OTHER` `'other'`.
  - `OTHER` `'other'`: the id of an email nothing names.
  - `modules(): array<string,string>` area slug to label, in display order.
  - `types(): array<string,string>` type slug to label, in display order.
  - `emails(): array<string,array{label:string,module:string,audience:string,test:bool}>` id to entry, worked out once a request (a static). `audience` is a type slug, or `''` when the email goes to people of several kinds and the account decides. 42 plugin contexts, 19 WordPress emails and the Two Factor code.
  - `get( string $id ): ?array` the entry, or null.
  - `label( string $id ): string` the entry's label, or "Other email".
  - `module_of( string $id ): string` the entry's area, or `MODULE_OTHER`.
  - `wordpress_filters(): array<string,string>` filter name to email id, for the capture to hook.

- [ ] **Step 1: Write the failing test**

The source scan reads every context the plugin hands the mail layer: a literal or a constant the same file declares, as the second argument of `send()`, `send_to()` and `mail_members()`, the first of `notify_managers()` (called directly or through `call_user_func()`), the third of `mail_manager()` or its default, and the two families built from a kind (`'invite-' . $kind`, `'test-' . $kind`). On 10 October 2026 it finds 42. A context the scan finds and the catalog lacks is reported by the failing check, and is added to the catalog only if a call site truly sends it; a pattern that misses a way the code passes a context is a defect of the scan, to fix there.

Create `bin/test-mail-catalog.php` (the `ck()` helper and the two closing lines are the File Structure section's, written out):

```php
<?php
/**
 * The mail catalog: every email the site can send has a name, an area and an audience.
 *
 * The catalog is what the Log tab shows in its Email and Area columns, so an email the code sends
 * under a context the catalog does not know would show as "Other email" for no reason. The source
 * scan below reads every context the plugin passes to the mail layer and fails on one the catalog
 * lacks.
 *
 * Run from the plugin root:  php bin/test-mail-catalog.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

function __( $s, $d = null ) { return $s; }

require __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';

$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

$emails = WPCPM_Mail_Catalog::emails();

ck( 'the catalog names the 42 contexts the plugin sends today', count( array_filter( $emails, function ( $e ) { return ! in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) ), 42 );
ck( 'and reads the same list twice in a request', WPCPM_Mail_Catalog::emails() === $emails, true );

$bad = array();
foreach ( $emails as $id => $e ) {
	if ( ! preg_match( '/^[a-z0-9-]+$/', $id ) || '' === $e['label'] || ! isset( WPCPM_Mail_Catalog::modules()[ $e['module'] ] ) || ( '' !== $e['audience'] && ! isset( WPCPM_Mail_Catalog::types()[ $e['audience'] ] ) ) || ! is_bool( $e['test'] ) ) {
		$bad[] = $id;
	}
}
ck( 'every entry has a key of letters, digits and hyphens, a label, a known area and a known audience', $bad, array() );

ck( 'the four samples from Settings > Mail are tests, nothing else is', array_keys( array_filter( $emails, function ( $e ) { return $e['test']; } ) ), array( 'test-student', 'test-mentor', 'test-institution', 'test-sponsor' ) );

ck( 'an unknown id is "Other email" in the Other area', array( WPCPM_Mail_Catalog::label( 'no-such' ), WPCPM_Mail_Catalog::module_of( 'no-such' ), WPCPM_Mail_Catalog::label( '' ) ), array( 'Other email', 'other', 'Other email' ) );

ck( 'a known id reads back', array( WPCPM_Mail_Catalog::label( 'call-booked' ), WPCPM_Mail_Catalog::module_of( 'call-booked' ) ), array( 'Call booked', 'calls' ) );

ck( 'the last member leaving an institution is named, as the Administrators\' email it is', WPCPM_Mail_Catalog::get( 'member-last' ), array( 'label' => 'Institution has no members left, to Administrators', 'module' => 'institutions', 'audience' => 'administrator', 'test' => false ) );

ck( 'the areas are the ones the Log lists, in its order', array_values( WPCPM_Mail_Catalog::modules() ), array( 'Invitations', 'Mentor calls', 'Institutions', 'Semester reports', 'Sponsors', 'WordPress', 'Two Factor', 'Other' ) );

// Every context the plugin hands the mail layer, read from the source: a literal, or a constant the
// same file declares, as the second argument of send(), send_to() and mail_members(), the first of
// notify_managers() (called directly or through call_user_func()), the third of mail_manager() or
// its default, and the two built from a kind.
$contexts = array();
foreach ( array_merge( glob( __DIR__ . '/../includes/*.php' ), glob( __DIR__ . '/../includes/*/*.php' ) ) as $file ) {
	$src = (string) file_get_contents( $file );
	preg_match_all( "/const\s+(\w+)\s*=\s*'([a-z0-9-]+)'\s*;/", $src, $c, PREG_SET_ORDER );
	$consts = array_column( $c, 2, 1 );
	$arg    = "(?:'([a-z0-9-]+)'|self::(\w+))\s*[,)]";
	foreach ( array( "/(?:WPCPM_Mail|self)::send(?:_to)?\(\s*[^,]+,\s*$arg/", "/mail_members\(\s*[^,]+,\s*$arg/", "/notify_managers(?:\(|'\s*\),)\s*$arg/", "/mail_manager\(\s*[^,]+,\s*[^,]+,\s*$arg/" ) as $pattern ) {
		preg_match_all( $pattern, $src, $m, PREG_SET_ORDER );
		foreach ( $m as $hit ) {
			$contexts[ ! empty( $hit[1] ) ? $hit[1] : ( isset( $consts[ $hit[2] ] ) ? $consts[ $hit[2] ] : 'unresolved:' . $hit[2] ) ] = true;
		}
	}
	if ( preg_match( '/mail_manager\(\s*[^,()]+,\s*\$\w+\s*\)/', $src ) ) {
		$contexts['sponsor-interest'] = true;
	}
	foreach ( array( 'invite-', 'test-' ) as $built ) {
		if ( false !== strpos( $src, "'" . $built . "' . " ) ) {
			foreach ( array( 'student', 'mentor', 'institution', 'sponsor' ) as $k ) {
				$contexts[ $built . $k ] = true;
			}
		}
	}
}
$missing = array_values( array_diff( array_keys( $contexts ), array_keys( $emails ) ) );
sort( $missing );
ck( 'every context the source passes to the mail layer is in the catalog', $missing, array() );
ck( 'and the scan found all 42 of them (it is not reading nothing)', count( $contexts ), 42 );

$filters = WPCPM_Mail_Catalog::wordpress_filters();
$unnamed = array_values( array_diff( array_values( $filters ), array_keys( $emails ) ) );
ck( 'every WordPress and Two Factor filter names an email the catalog has', $unnamed, array() );
ck( 'and every WordPress and Two Factor email the catalog has is named by one', array_values( array_diff( array_keys( array_filter( $emails, function ( $e ) { return in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) ), array_values( $filters ) ) ), array() );
ck( 'the Two Factor sign-in code is named by its subject filter', isset( $filters['two_factor_token_email_subject'] ) ? $filters['two_factor_token_email_subject'] : '', 'two-factor-code' );
ck( 'WordPress\'s password reset is named', isset( $filters['retrieve_password_notification_email'] ) ? $filters['retrieve_password_notification_email'] : '', 'wp-password-reset' );
ck(
	'and so are the erasure, the confirmed request and the background update details',
	array( $filters['user_erasure_fulfillment_email_content'] ?? '', $filters['user_request_confirmed_email_content'] ?? '', $filters['automatic_updates_debug_email'] ?? '' ),
	array( 'wp-personal-data-erased', 'wp-personal-data-request-confirmed', 'wp-updates-debug' )
);

$text = (string) file_get_contents( __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php' );
ck( 'no em or en dash in the catalog', preg_match( '/\x{2013}|\x{2014}/u', $text ), 0 );
ck( 'and no label says module', array_values( array_filter( array_merge( array_column( $emails, 'label' ), array_values( WPCPM_Mail_Catalog::modules() ), array_values( WPCPM_Mail_Catalog::types() ) ), function ( $l ) { return 1 === preg_match( '/\bmodules?\b/i', $l ); } ) ), array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php bin/test-mail-catalog.php`
Expected: a PHP warning that `includes/mail/class-wpcpm-mail-catalog.php` is missing, and a fatal error.

- [ ] **Step 3: Write the catalog**

Create `includes/mail/class-wpcpm-mail-catalog.php`:

```php
<?php
/**
 * Every email the site can send, by name.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The names the Emails tool shows for what the site sends.
 *
 * An email's id is the context the plugin hands `WPCPM_Mail` when it sends it, so the log can name
 * it without the sending code knowing the log exists; WordPress's own emails and the Two Factor
 * plugin's sign-in code are named by the filters they pass through on the way out
 * (`wordpress_filters()`), which the capture hooks. An email nothing names is "Other email".
 *
 * Each email belongs to an area of the program, which the Log's Area column and filter show. The
 * code calls the areas modules (`MODULE_*`, `modules()`, the table's `module` column) and the
 * screens call them areas: no string a person reads says module (the owner's one-vocabulary rule of
 * 28 September 2026, which bin/test-admin-menu.php holds every translated string to).
 *
 * `audience` is who an email is for when the account cannot say: an applicant or an invitee has no
 * account, and a person with two roles is shown as the one this email is for. Empty means the email
 * goes to people of several kinds (a call booked mails the mentor and the student) and the account
 * decides.
 */
final class WPCPM_Mail_Catalog {

	const MODULE_INVITATIONS  = 'invitations';
	const MODULE_CALLS        = 'calls';
	const MODULE_INSTITUTIONS = 'institutions';
	const MODULE_REPORTS      = 'reports';
	const MODULE_SPONSORS     = 'sponsors';
	const MODULE_WORDPRESS    = 'wordpress';
	const MODULE_TWO_FACTOR   = 'two-factor';
	const MODULE_OTHER        = 'other';

	const TYPE_STUDENT     = 'student';
	const TYPE_MENTOR      = 'mentor';
	const TYPE_INSTITUTION = 'institution';
	const TYPE_SPONSOR     = 'sponsor';
	const TYPE_ADMIN       = 'administrator';
	const TYPE_APPLICANT   = 'applicant';
	const TYPE_OTHER       = 'other';

	/** The id of an email nothing names. */
	const OTHER = 'other';

	/**
	 * The areas, in the order the Log's Area filter lists them.
	 *
	 * @return array<string,string> Slug to label.
	 */
	public static function modules() {
		return array(
			self::MODULE_INVITATIONS  => __( 'Invitations', 'wpcredits-program-manager' ),
			self::MODULE_CALLS        => __( 'Mentor calls', 'wpcredits-program-manager' ),
			self::MODULE_INSTITUTIONS => __( 'Institutions', 'wpcredits-program-manager' ),
			self::MODULE_REPORTS      => __( 'Semester reports', 'wpcredits-program-manager' ),
			self::MODULE_SPONSORS     => __( 'Sponsors', 'wpcredits-program-manager' ),
			self::MODULE_WORDPRESS    => __( 'WordPress', 'wpcredits-program-manager' ),
			self::MODULE_TWO_FACTOR   => __( 'Two Factor', 'wpcredits-program-manager' ),
			self::MODULE_OTHER        => __( 'Other', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The recipient types, in the order the Log's filter lists them.
	 *
	 * @return array<string,string> Slug to label.
	 */
	public static function types() {
		return array(
			self::TYPE_STUDENT     => __( 'Student', 'wpcredits-program-manager' ),
			self::TYPE_MENTOR      => __( 'Mentor', 'wpcredits-program-manager' ),
			self::TYPE_INSTITUTION => __( 'Institution member', 'wpcredits-program-manager' ),
			self::TYPE_SPONSOR     => __( 'Sponsor member', 'wpcredits-program-manager' ),
			self::TYPE_ADMIN       => __( 'Administrator', 'wpcredits-program-manager' ),
			self::TYPE_APPLICANT   => __( 'Applicant (no account)', 'wpcredits-program-manager' ),
			self::TYPE_OTHER       => __( 'Other', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * Every email, by id, worked out once a request: the labels are shown in wp-admin only, and the
	 * capture stores ids.
	 *
	 * @return array<string,array{label:string,module:string,audience:string,test:bool}>
	 */
	public static function emails() {
		static $out = null;

		if ( null !== $out ) {
			return $out;
		}

		$i = self::MODULE_INVITATIONS;
		$c = self::MODULE_CALLS;
		$n = self::MODULE_INSTITUTIONS;
		$r = self::MODULE_REPORTS;
		$s = self::MODULE_SPONSORS;
		$w = self::MODULE_WORDPRESS;

		$list = array(
			// Invitations.
			'invite-student'                     => array( __( 'Invitation: student', 'wpcredits-program-manager' ), $i, self::TYPE_STUDENT ),
			'invite-mentor'                      => array( __( 'Invitation: mentor', 'wpcredits-program-manager' ), $i, self::TYPE_MENTOR ),
			'invite-institution'                 => array( __( 'Invitation: institution', 'wpcredits-program-manager' ), $i, self::TYPE_INSTITUTION ),
			'invite-sponsor'                     => array( __( 'Invitation: sponsor', 'wpcredits-program-manager' ), $i, self::TYPE_SPONSOR ),
			'test-student'                       => array( __( 'Sample invitation: student', 'wpcredits-program-manager' ), $i, self::TYPE_ADMIN, true ),
			'test-mentor'                        => array( __( 'Sample invitation: mentor', 'wpcredits-program-manager' ), $i, self::TYPE_ADMIN, true ),
			'test-institution'                   => array( __( 'Sample invitation: institution', 'wpcredits-program-manager' ), $i, self::TYPE_ADMIN, true ),
			'test-sponsor'                       => array( __( 'Sample invitation: sponsor', 'wpcredits-program-manager' ), $i, self::TYPE_ADMIN, true ),
			// Mentor calls: to the mentor and the students, so the account decides.
			'call-booked'                        => array( __( 'Call booked', 'wpcredits-program-manager' ), $c, '' ),
			'series-joined'                      => array( __( 'Joined a session series', 'wpcredits-program-manager' ), $c, '' ),
			'session-moved'                      => array( __( 'Group session moved', 'wpcredits-program-manager' ), $c, '' ),
			'call-cancelled'                     => array( __( 'Call canceled', 'wpcredits-program-manager' ), $c, '' ),
			'call-reminder'                      => array( __( 'Call reminder', 'wpcredits-program-manager' ), $c, '' ),
			// Institutions.
			'institution-applied'                => array( __( 'Application received, to the applicant', 'wpcredits-program-manager' ), $n, self::TYPE_APPLICANT ),
			'institution-application'            => array( __( 'New institution application, to Administrators', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			'institution-information'            => array( __( 'Question about an application', 'wpcredits-program-manager' ), $n, self::TYPE_APPLICANT ),
			'institution-declined'               => array( __( 'Application declined', 'wpcredits-program-manager' ), $n, self::TYPE_APPLICANT ),
			'institution-invite'                 => array( __( 'Invitation to join an institution', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-received'                 => array( __( 'Agreement received, to the institution', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-landed'                   => array( __( 'Agreement uploaded, to Administrators', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			'agreement-accepted'                 => array( __( 'Agreement accepted', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-returned'                 => array( __( 'Agreement returned with a note', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-revoked'                  => array( __( 'Agreement taken out of force', 'wpcredits-program-manager' ), $n, self::TYPE_INSTITUTION ),
			'agreement-reminder'                 => array( __( 'Agreements waiting for review', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			'agreement-template'                 => array( __( 'Agreement could not be generated', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			'member-last'                        => array( __( 'Institution has no members left, to Administrators', 'wpcredits-program-manager' ), $n, self::TYPE_ADMIN ),
			// Semester reports.
			'report-drafted'                     => array( __( 'Semester report drafted', 'wpcredits-program-manager' ), $r, self::TYPE_ADMIN ),
			'report-approved'                    => array( __( 'Semester report approved', 'wpcredits-program-manager' ), $r, self::TYPE_INSTITUTION ),
			'report-consent'                     => array( __( 'Consent for the semester report', 'wpcredits-program-manager' ), $r, self::TYPE_STUDENT ),
			// Sponsors.
			'sponsor-applied'                    => array( __( 'Sponsor application received, to the applicant', 'wpcredits-program-manager' ), $s, self::TYPE_APPLICANT ),
			'sponsor-application'                => array( __( 'New sponsor application, to Administrators', 'wpcredits-program-manager' ), $s, self::TYPE_ADMIN ),
			'sponsor-information'                => array( __( 'Question about a sponsor application', 'wpcredits-program-manager' ), $s, self::TYPE_APPLICANT ),
			'sponsor-declined'                   => array( __( 'Sponsor application declined', 'wpcredits-program-manager' ), $s, self::TYPE_APPLICANT ),
			'sponsor-agreement-received'         => array( __( 'Collaboration Agreement received', 'wpcredits-program-manager' ), $s, self::TYPE_ADMIN ),
			'sponsor-agreement-accepted'         => array( __( 'Collaboration Agreement accepted', 'wpcredits-program-manager' ), $s, self::TYPE_SPONSOR ),
			'sponsor-agreement-returned'         => array( __( 'Collaboration Agreement returned with a note', 'wpcredits-program-manager' ), $s, self::TYPE_SPONSOR ),
			'sponsor-agreement-revoked'          => array( __( 'Collaboration Agreement taken out of force', 'wpcredits-program-manager' ), $s, self::TYPE_SPONSOR ),
			'sponsor-agreement-reinstated'       => array( __( 'Collaboration Agreement back in force', 'wpcredits-program-manager' ), $s, self::TYPE_SPONSOR ),
			'sponsor-interest'                   => array( __( 'Sponsor interest', 'wpcredits-program-manager' ), $s, self::TYPE_ADMIN ),
			'offer-low-stock'                    => array( __( 'Codes running low', 'wpcredits-program-manager' ), $s, '' ),
			'claim-problem'                      => array( __( 'Problem with a claimed code', 'wpcredits-program-manager' ), $s, self::TYPE_ADMIN ),
			'sponsor-post-returned'              => array( __( 'Sponsor post returned with a note', 'wpcredits-program-manager' ), $s, self::TYPE_SPONSOR ),
			// WordPress's own.
			'wp-password-reset'                  => array( __( 'Password reset', 'wpcredits-program-manager' ), $w, '' ),
			'wp-password-changed'                => array( __( 'Password changed', 'wpcredits-program-manager' ), $w, '' ),
			'wp-password-changed-admin'          => array( __( 'Password changed, to the site\'s address', 'wpcredits-program-manager' ), $w, self::TYPE_ADMIN ),
			'wp-email-changed'                   => array( __( 'Email address changed', 'wpcredits-program-manager' ), $w, '' ),
			'wp-email-change-confirm'            => array( __( 'Confirm a new email address', 'wpcredits-program-manager' ), $w, '' ),
			'wp-new-user'                        => array( __( 'New account', 'wpcredits-program-manager' ), $w, '' ),
			'wp-new-user-admin'                  => array( __( 'New account, to the site\'s address', 'wpcredits-program-manager' ), $w, self::TYPE_ADMIN ),
			'wp-admin-email-change'              => array( __( 'Confirm the site\'s new address', 'wpcredits-program-manager' ), $w, self::TYPE_ADMIN ),
			'wp-admin-email-changed'             => array( __( 'The site\'s address changed', 'wpcredits-program-manager' ), $w, self::TYPE_ADMIN ),
			'wp-personal-data-request'           => array( __( 'Personal data request', 'wpcredits-program-manager' ), $w, '' ),
			'wp-personal-data-request-confirmed' => array( __( 'Personal data request confirmed, to the site\'s address', 'wpcredits-program-manager' ), $w, self::TYPE_ADMIN ),
			'wp-personal-data-export'            => array( __( 'Personal data export ready', 'wpcredits-program-manager' ), $w, '' ),
			'wp-personal-data-erased'            => array( __( 'Personal data erased', 'wpcredits-program-manager' ), $w, '' ),
			'wp-core-update'                     => array( __( 'WordPress updated', 'wpcredits-program-manager' ), $w, self::TYPE_ADMIN ),
			'wp-plugin-theme-update'             => array( __( 'Plugins or themes updated', 'wpcredits-program-manager' ), $w, self::TYPE_ADMIN ),
			'wp-updates-debug'                   => array( __( 'Background update details', 'wpcredits-program-manager' ), $w, self::TYPE_ADMIN ),
			'wp-recovery-mode'                   => array( __( 'Recovery mode', 'wpcredits-program-manager' ), $w, self::TYPE_ADMIN ),
			'wp-comment-notification'            => array( __( 'New comment', 'wpcredits-program-manager' ), $w, '' ),
			'wp-comment-moderation'              => array( __( 'Comment waiting for moderation', 'wpcredits-program-manager' ), $w, '' ),
			// The Two Factor plugin's.
			'two-factor-code'                    => array( __( 'Sign-in code', 'wpcredits-program-manager' ), self::MODULE_TWO_FACTOR, '' ),
		);

		$out = array();

		foreach ( $list as $id => $row ) {
			$out[ $id ] = array(
				'label'    => $row[0],
				'module'   => $row[1],
				'audience' => $row[2],
				'test'     => ! empty( $row[3] ),
			);
		}

		return $out;
	}

	/**
	 * One email's entry.
	 *
	 * @param string $id The email's id.
	 * @return array|null The entry, or null for an id the catalog does not hold.
	 */
	public static function get( $id ) {
		$emails = self::emails();

		return isset( $emails[ (string) $id ] ) ? $emails[ (string) $id ] : null;
	}

	/**
	 * An email's name as the Log shows it.
	 *
	 * @param string $id The email's id.
	 * @return string The label, or "Other email".
	 */
	public static function label( $id ) {
		$entry = self::get( $id );

		return null === $entry ? __( 'Other email', 'wpcredits-program-manager' ) : $entry['label'];
	}

	/**
	 * An email's area.
	 *
	 * @param string $id The email's id.
	 * @return string One of the `MODULE_*` slugs; `MODULE_OTHER` for an id the catalog does not hold.
	 */
	public static function module_of( $id ) {
		$entry = self::get( $id );

		return null === $entry ? self::MODULE_OTHER : $entry['module'];
	}

	/**
	 * The filters WordPress and the Two Factor plugin pass an email through on its way out, and the
	 * email each one names. WordPress's were read in WordPress 7.1.2 on 10 October 2026, each a
	 * current filter and none deprecated. `two_factor_token_email_subject` was read in the Two Factor
	 * plugin 0.17.0, the version on the live site, and exists since 0.5.2; the local copies run
	 * 0.16.0, which has it too.
	 *
	 * `wp_new_user_notification_email` is WordPress's new-account email, which the plugin rewrites
	 * into its invitations; an invitation is named by the plugin's own context, which wins over this
	 * name (`WPCPM_Mail_Capture::open()`).
	 *
	 * The two comment emails' subject filters run inside WordPress 7.1.2's loop over the recipients,
	 * once before each send, so every recipient's email is named. A WordPress that ran a filter once
	 * before such a loop would have its first recipient's email named and the rest shown as Other.
	 * The Two Factor plugin's two emails about a compromised password pass no filter of their own,
	 * so they show as Other.
	 *
	 * @return array<string,string> Filter name to email id.
	 */
	public static function wordpress_filters() {
		return array(
			'retrieve_password_notification_email'   => 'wp-password-reset',
			'password_change_email'                  => 'wp-password-changed',
			'wp_password_change_notification_email'  => 'wp-password-changed-admin',
			'email_change_email'                     => 'wp-email-changed',
			'new_user_email_content'                 => 'wp-email-change-confirm',
			'wp_new_user_notification_email'         => 'wp-new-user',
			'wp_new_user_notification_email_admin'   => 'wp-new-user-admin',
			'new_admin_email_content'                => 'wp-admin-email-change',
			'site_admin_email_change_email'          => 'wp-admin-email-changed',
			'user_request_action_email_content'      => 'wp-personal-data-request',
			'user_request_confirmed_email_content'   => 'wp-personal-data-request-confirmed',
			'wp_privacy_personal_data_email_content' => 'wp-personal-data-export',
			'user_erasure_fulfillment_email_content' => 'wp-personal-data-erased',
			'auto_core_update_email'                 => 'wp-core-update',
			'auto_plugin_theme_update_email'         => 'wp-plugin-theme-update',
			'automatic_updates_debug_email'          => 'wp-updates-debug',
			'recovery_mode_email'                    => 'wp-recovery-mode',
			'comment_notification_subject'           => 'wp-comment-notification',
			'comment_moderation_subject'             => 'wp-comment-moderation',
			'two_factor_token_email_subject'         => 'two-factor-code',
		);
	}
}
```

Every WordPress filter above was read in WordPress 7.1.2's own source (on a 7.1.2 install, `grep -rn "apply_filters( 'retrieve_password_notification_email'" wp-includes wp-admin`, and so on for each): none is a deprecated one, and the two comment filters run inside core's loop over the recipients, once before each send. The labels say no "module".

- [ ] **Step 4: Load the file**

In `wpcredits-program-manager.php`, replace line 46:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
```

with:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
```

In `uninstall.php`, replace line 51 (bin/test-roles.php holds the loader and uninstall.php to the same list of requires):

```php
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-mail.php';
```

with:

```php
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-mail.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/mail/class-wpcpm-mail-catalog.php';
```

In `bin/test-roles.php`, so that a class file landing in `includes/mail/` without a require fails there, replace (lines 381 and 382):

```php
// `includes/tracks` since the Track Builder's classes landed there (1.100.0).
foreach ( array( 'includes', 'includes/modules', 'includes/tools', 'includes/tracks' ) as $dir ) {
```

with:

```php
// `includes/tracks` since the Track Builder's classes landed there (1.100.0), and `includes/mail`
// since the mail log's (1.122.18).
foreach ( array( 'includes', 'includes/modules', 'includes/tools', 'includes/tracks', 'includes/mail' ) as $dir ) {
```

- [ ] **Step 5: Run the touched suites to verify they pass**

Run, one per command: `php bin/test-mail-catalog.php`, `php bin/test-roles.php`.
Expected: `ALL PASS (17 checks)`, then `ALL PASS`.

- [ ] **Step 6: Run the whole battery**

Run, from the plugin root, every suite in its own process, then the references check:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 7: Commit**

```bash
git add includes/mail/class-wpcpm-mail-catalog.php bin/test-mail-catalog.php wpcredits-program-manager.php uninstall.php bin/test-roles.php
git commit -m "Mail catalog: every email the site can send, by name, area and audience"
```

---

### Task 2: The store

**Files:**
- Create: `bin/stubs/sqlite-wpdb.php`
- Create: `includes/mail/class-wpcpm-mail-log.php`
- Create: `bin/test-mail-log.php`
- Modify: `wpcredits-program-manager.php` (require the store after the catalog; call `WPCPM_Mail_Log::init()` in `wpcpm_bootstrap()` after `WPCPM_Mail::init()`; call `WPCPM_Mail_Log::deactivate()` in `wpcpm_deactivate()`)
- Modify: `uninstall.php` (the same require after the catalog's; `WPCPM_Mail_Log::uninstall()` after `WPCPM_Mail::clear_log()`, line 281 once Task 1's require is in)
- Modify: `bin/test-uninstall.php` (`wpcpm_mail_log_purge` in `$other_hooks`, line 754; the log's two options seeded beside the old option, line 673; a check that the table is dropped; the store's four names in `$names`)
- Suites to run: `bin/test-mail-log.php`, `bin/test-uninstall.php`, `bin/test-roles.php`, then the whole battery

**Interfaces:**
- Consumes: `WPCPM_Mail_Catalog::get()`, `::module_of()`, `::label()`, `::OTHER` (Task 1).
- Produces:
  - Constants: `SCHEMA_VERSION` `1`, `OPT_VERSION` `'wpcpm_mail_log_version'`, `OPT_PURGED` `'wpcpm_mail_log_purged'`, `OLD_OPTION` `'wpcpm_mail_log'`, `KEEP_DAYS` `30`, `PURGE_BATCH` `1000`, `PURGE_HOOK` `'wpcpm_mail_log_purge'`, `PER_PAGE` `50`, `STATUS_SENT` `'sent'`, `STATUS_FAILED` `'failed'`, `STATUS_UNCONFIRMED` `'unconfirmed'`, `FILTER_TEST` `'test'`, `PRIVACY_PAGE` `100`.
  - `init(): void` hooks `maybe_upgrade` on `init` at 5, `schedule` on `init`, `purge` on `PURGE_HOOK`, `register_exporter` on `wp_privacy_personal_data_exporters`, `register_eraser` on `wp_privacy_personal_data_erasers`. Task 4 adds the capture to it.
  - `schedule(): void`, `deactivate(): void`, `register_exporter( array $exporters ): array`, `register_eraser( array $erasers ): array`.
  - `table(): string`, `schema(): string`, `exists(): bool` (a found table is kept for the request), `reset(): void` (test-only: forget the table found), `maybe_upgrade(): void`, `migrate_old_option(): int`.
  - `add( array $row ): int` row keys `sent_at` (UTC `Y-m-d H:i:s`), `to_email`, `user_id` (int or null), `to_name`, `recipient_type`, `module`, `template`, `subject`, `status`, `error`, `is_test` (bool); returns the new id, or 0 when nothing was written.
  - `find( array $filters, int $page = 1, int $per_page = self::PER_PAGE ): array{rows:array<int,array>,total:int}` with filter keys `search`, `exact_email`, `module`, `recipient_type`, `status` (a status or `FILTER_TEST`), `template`, `from` and `to` (UTC `Y-m-d H:i:s`, from inclusive, to exclusive).
  - `latest(): ?array`, `count_since( string $since_utc ): int`, `failures_since( string $since_utc ): int`.
  - `purge( int $now = 0 ): int`, `maybe_purge(): void`.
  - `export( string $email, int $page = 1 ): array{data:array,done:bool}`, `erase( string $email, int $page = 1 ): array{items_removed:bool,items_retained:bool,messages:array,done:bool}` (WordPress's exporter and eraser shapes).
  - `status_label( string $status ): string` a status in words: "Handed to the mail server", "Failed", "Not confirmed", or "Not recorded" for anything else.
  - `uninstall(): void`.
  - `WPCPM_Test_Wpdb` (bin/stubs/sqlite-wpdb.php): core's `prefix`, `insert_id`, `last_error`, `queries`, `prepare()`, `esc_like()`, `insert()`, `query()`, `get_results()`, `get_row()`, `get_var()`, `get_col()`, `get_charset_collate()`, `suppress_errors()`; and for the suites `create_mail_log()`, `drop_mail_log()`, `mail_log_rows()`.

- [ ] **Step 1: Write the SQLite stub**

It rewrites the two statements WordPress's SQLite integration (3.x, the version the local copies run) rewrites: `SHOW TABLES LIKE` is answered from `sqlite_master`, and every `LIKE '<literal>'` gains ` ESCAPE '\'`, so a pattern escaped with `esc_like()` means here what it means on MySQL and on the local copies.

Create `bin/stubs/sqlite-wpdb.php`:

```php
<?php
/**
 * A wpdb for the suites, backed by an in-memory SQLite database (PDO).
 *
 * Models the part of core's wpdb the mail log uses: `prefix`, `prepare()` with %s, %d, %f and %%,
 * `esc_like()`, `insert()`, `query()`, `get_results()`, `get_var()`, `get_row()`, `get_col()`,
 * `get_charset_collate()`, `suppress_errors()`, `insert_id` and `last_error`. A suite creates the
 * table in SQLite's dialect (`WPCPM_Test_Wpdb::create_mail_log()`); the plugin's MySQL schema is
 * pinned as text by the suite instead.
 *
 * Two statements are rewritten on the way in, as WordPress's SQLite integration (3.x, the one the
 * local copies run) rewrites them: `SHOW TABLES LIKE 'name'` is answered from sqlite_master,
 * because SQLite has no SHOW, and every `LIKE '<literal>'` gains ` ESCAPE '\'`, because MySQL reads
 * a backslash in a LIKE pattern as its escape and SQLite reads none unless told. A pattern escaped
 * with `esc_like()` therefore means here what it means on the live site.
 *
 * Loaded with `require __DIR__ . '/stubs/sqlite-wpdb.php';` from a suite's header.
 */
class WPCPM_Test_Wpdb {

	public $prefix     = 'wp_';
	public $insert_id  = 0;
	public $last_error = '';
	public $queries    = array();

	private $pdo;

	public function __construct() {
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT );
	}

	/** The mail log's table, in SQLite's dialect. */
	public function create_mail_log() {
		$this->pdo->exec(
			"CREATE TABLE {$this->prefix}wpcpm_mail_log ( id INTEGER PRIMARY KEY AUTOINCREMENT, sent_at TEXT NOT NULL, to_email TEXT NOT NULL DEFAULT '', user_id INTEGER DEFAULT NULL, to_name TEXT NOT NULL DEFAULT '', recipient_type TEXT NOT NULL DEFAULT '', module TEXT NOT NULL DEFAULT '', template TEXT NOT NULL DEFAULT '', subject TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT '', error TEXT NOT NULL DEFAULT '', is_test INTEGER NOT NULL DEFAULT 0, delivery TEXT DEFAULT NULL, delivery_at TEXT DEFAULT NULL )"
		);
	}

	/** Take the mail log's table away, as a site whose upgrade has not run has none. */
	public function drop_mail_log() {
		$this->pdo->exec( "DROP TABLE IF EXISTS {$this->prefix}wpcpm_mail_log" );
	}

	/** Every row of the mail log's table, every column, as stored. */
	public function mail_log_rows() {
		$st = $this->pdo->query( "SELECT * FROM {$this->prefix}wpcpm_mail_log ORDER BY id" );

		return false === $st ? array() : $st->fetchAll( PDO::FETCH_ASSOC );
	}

	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}

	public function suppress_errors( $suppress = true ) {
		return true;
	}

	/** Core's: LIKE's own characters, and the backslash, each escaped with a backslash. */
	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$i   = 0;
		$pdo = $this->pdo;

		return preg_replace_callback(
			'/%%|%[sdf]/',
			function ( $m ) use ( &$i, $args, $pdo ) {
				if ( '%%' === $m[0] ) {
					return '%';
				}

				$v = array_key_exists( $i, $args ) ? $args[ $i ] : '';
				++$i;

				if ( '%d' === $m[0] ) {
					return (string) (int) $v;
				}

				if ( '%f' === $m[0] ) {
					return (string) (float) $v;
				}

				return $pdo->quote( (string) $v );
			},
			(string) $query
		);
	}

	private function translate( $sql ) {
		if ( preg_match( "/^\s*SHOW TABLES LIKE '([^']+)'\s*$/i", $sql, $m ) ) {
			// The name as LIKE reads it once its escapes are read: `wp\_x` is `wp_x`.
			return "SELECT name FROM sqlite_master WHERE type = 'table' AND name = '" . preg_replace( '/\\\\(.)/', '$1', $m[1] ) . "'";
		}

		// As the SQLite integration 3.x does: every LIKE escapes with a backslash.
		return (string) preg_replace( "/(\\bLIKE\\s+'(?:[^']|'')*')/i", "$1 ESCAPE '\\\\'", (string) $sql );
	}

	private function run( $sql ) {
		$sql              = $this->translate( (string) $sql );
		$this->queries[]  = $sql;
		$this->last_error = '';
		$st               = $this->pdo->query( $sql );

		if ( false === $st ) {
			$info             = $this->pdo->errorInfo();
			$this->last_error = (string) ( $info[2] ?? 'error' );
		}

		return $st;
	}

	public function query( $sql ) {
		$st = $this->run( $sql );

		return false === $st ? false : $st->rowCount();
	}

	public function insert( $table, array $data, $format = null ) {
		$cols  = array_keys( $data );
		$marks = implode( ', ', array_fill( 0, count( $cols ), '?' ) );
		$sql   = 'INSERT INTO ' . $table . ' (' . implode( ', ', $cols ) . ') VALUES (' . $marks . ')';
		$st    = $this->pdo->prepare( $sql );

		$this->queries[]  = $sql;
		$this->last_error = '';

		if ( false === $st || false === $st->execute( array_values( $data ) ) ) {
			$info             = false === $st ? $this->pdo->errorInfo() : $st->errorInfo();
			$this->last_error = (string) ( $info[2] ?? 'error' );
			return false;
		}

		$this->insert_id = (int) $this->pdo->lastInsertId();

		return 1;
	}

	public function get_results( $sql, $output = 'OBJECT' ) {
		$st = $this->run( $sql );

		if ( false === $st ) {
			return array();
		}

		$rows = $st->fetchAll( PDO::FETCH_ASSOC );

		return 'ARRAY_A' === $output ? $rows : array_map( function ( $r ) { return (object) $r; }, $rows );
	}

	public function get_row( $sql, $output = 'OBJECT' ) {
		$rows = $this->get_results( $sql, $output );

		return isset( $rows[0] ) ? $rows[0] : null;
	}

	public function get_var( $sql ) {
		$st = $this->run( $sql );

		if ( false === $st ) {
			return null;
		}

		$v = $st->fetchColumn();

		return false === $v ? null : $v;
	}

	public function get_col( $sql ) {
		$st = $this->run( $sql );

		return false === $st ? array() : $st->fetchAll( PDO::FETCH_COLUMN );
	}
}
```

- [ ] **Step 2: Write the failing test**

Create `bin/test-mail-log.php` (the `ck()` helper and the two closing lines are the File Structure section's, written out):

```php
<?php
/**
 * The mail log's store: one table of who, what, when and status, kept 30 days.
 *
 * The store runs on an in-memory SQLite database (bin/stubs/sqlite-wpdb.php), which rewrites what
 * WordPress's SQLite integration rewrites, so a search that is literal here is literal on the local
 * copies; MySQL reads the same backslash escapes.
 *
 * Run from the plugin root:  php bin/test-mail-log.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'OBJECT', 'OBJECT' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['opts']      = array();
$GLOBALS['stale']     = array(); // An option a request read before another request deleted it.
$GLOBALS['dbdelta']   = array();
$GLOBALS['cleared']   = array();
$GLOBALS['hooked']    = array();
$GLOBALS['scheduled'] = array();

function __( $s, $d = null ) { return $s; }
function get_option( $k, $d = false ) {
	if ( array_key_exists( $k, $GLOBALS['stale'] ) ) {
		return $GLOBALS['stale'][ $k ];
	}
	return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d;
}
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
// Core's: true only when there was an option to delete.
function delete_option( $k ) { $had = array_key_exists( $k, $GLOBALS['opts'] ); unset( $GLOBALS['opts'][ $k ] ); return $had; }
function wp_clear_scheduled_hook( $h ) { $GLOBALS['cleared'][] = $h; unset( $GLOBALS['scheduled'][ $h ] ); return 1; }
function wp_next_scheduled( $h ) { return isset( $GLOBALS['scheduled'][ $h ] ) ? $GLOBALS['scheduled'][ $h ][0] : false; }
function wp_schedule_event( $t, $r, $h ) { $GLOBALS['scheduled'][ $h ] = array( $t, $r ); return true; }
function add_action( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['hooked'][] = array( $h, $cb, $p ); return true; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { return add_action( $h, $cb, $p, $n ); }
function sanitize_email( $e ) { $e = trim( (string) $e ); return filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : ''; }
// Core's, as 7.1 has it: valid text passes, and invalid bytes are replaced when asked to strip.
function wp_check_invalid_utf8( $s, $strip = false ) {
	$s = (string) $s;
	if ( '' === $s || mb_check_encoding( $s, 'UTF-8' ) ) {
		return $s;
	}
	return $strip ? mb_scrub( $s, 'UTF-8' ) : '';
}
function dbDelta( $sql ) {
	$GLOBALS['dbdelta'][] = $sql;
	if ( empty( $GLOBALS['dbdelta_fails'] ) ) {
		$GLOBALS['wpdb']->create_mail_log();
	}
	return array();
}

require __DIR__ . '/stubs/sqlite-wpdb.php';
$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();

require __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require __DIR__ . '/../includes/mail/class-wpcpm-mail-log.php';

$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/** A row as the capture writes one, with some columns put in place. */
function row( array $over = array() ) {
	return array_merge(
		array(
			'sent_at'        => '2026-10-10 09:00:00',
			'to_email'       => 'ada@example.test',
			'user_id'        => 7,
			'to_name'        => 'Ada Lin',
			'recipient_type' => 'student',
			'module'         => 'calls',
			'template'       => 'call-booked',
			'subject'        => 'Call booked with your mentor',
			'status'         => 'sent',
			'error'          => '',
			'is_test'        => false,
		),
		$over
	);
}

/** An empty table, and the store told to look for it again. */
function fresh_table() {
	$GLOBALS['wpdb']->drop_mail_log();
	$GLOBALS['wpdb']->create_mail_log();
	WPCPM_Mail_Log::reset();
}

// The hooks, from the plugin's bootstrap.
WPCPM_Mail_Log::init();
$own = array();
foreach ( $GLOBALS['hooked'] as $h ) {
	if ( is_array( $h[1] ) && 'WPCPM_Mail_Log' === $h[1][0] ) {
		$own[] = array( $h[0], $h[1][1], $h[2] );
	}
}
ck( 'init() hooks the upgrade early on init, the schedule, the cleanup job and both privacy tools', $own, array( array( 'init', 'maybe_upgrade', 5 ), array( 'init', 'schedule', 10 ), array( 'wpcpm_mail_log_purge', 'purge', 10 ), array( 'wp_privacy_personal_data_exporters', 'register_exporter', 10 ), array( 'wp_privacy_personal_data_erasers', 'register_eraser', 10 ) ) );
WPCPM_Mail_Log::schedule();
WPCPM_Mail_Log::schedule();
ck( 'the cleanup is scheduled once, daily', array( count( $GLOBALS['scheduled'] ), $GLOBALS['scheduled']['wpcpm_mail_log_purge'][1] ?? '' ), array( 1, 'daily' ) );
WPCPM_Mail_Log::deactivate();
ck( 'and deactivation clears it', array( isset( $GLOBALS['scheduled']['wpcpm_mail_log_purge'] ), in_array( 'wpcpm_mail_log_purge', $GLOBALS['cleared'], true ) ), array( false, true ) );
$exporters = WPCPM_Mail_Log::register_exporter( array( 'core' => array() ) );
$erasers   = WPCPM_Mail_Log::register_eraser( array() );
ck( 'the exporter and the eraser join WordPress\'s, each calling the store', array( array_keys( $exporters ), $exporters['wpcpm-mail-log']['callback'], $erasers['wpcpm-mail-log']['callback'], $erasers['wpcpm-mail-log']['eraser_friendly_name'] ), array( array( 'core', 'wpcpm-mail-log' ), array( 'WPCPM_Mail_Log', 'export' ), array( 'WPCPM_Mail_Log', 'erase' ), 'Emails the site sent' ) );

// The schema, as MySQL will read it.
$schema = WPCPM_Mail_Log::schema();
foreach ( array( 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT', 'sent_at datetime NOT NULL', 'to_email varchar(191)', 'user_id bigint(20) unsigned DEFAULT NULL', 'to_name varchar(191)', 'recipient_type varchar(20)', 'module varchar(20)', 'template varchar(64)', 'subject varchar(255)', 'status varchar(12)', 'error varchar(255)', 'is_test tinyint(1)', 'delivery varchar(20) DEFAULT NULL', 'delivery_at datetime DEFAULT NULL', 'PRIMARY KEY  (id)', 'KEY sent_at (sent_at)', 'KEY to_email (to_email)', 'KEY module_sent (module,sent_at)', 'KEY template (template)', 'KEY user_id (user_id)', 'utf8mb4' ) as $part ) {
	ck( 'the schema holds ' . $part, false !== strpos( $schema, $part ), true );
}

// No table yet: nothing is written and nothing breaks (Review Focus 5).
ck( 'without the table, exists() says so', WPCPM_Mail_Log::exists(), false );
ck( 'and add() writes nothing and returns 0', WPCPM_Mail_Log::add( row() ), 0 );
ck( 'and find() returns nothing', WPCPM_Mail_Log::find( array() ), array( 'rows' => array(), 'total' => 0 ) );
ck( 'and the counts are 0', array( WPCPM_Mail_Log::count_since( '2026-01-01 00:00:00' ), WPCPM_Mail_Log::failures_since( '2026-01-01 00:00:00' ), WPCPM_Mail_Log::latest() ), array( 0, 0, null ) );

// An upgrade that fails leaves the version unset, so the next load tries again.
$GLOBALS['dbdelta_fails'] = true;
WPCPM_Mail_Log::maybe_upgrade();
ck( 'a failed creation leaves the version unset', get_option( WPCPM_Mail_Log::OPT_VERSION, 0 ), 0 );
$GLOBALS['dbdelta_fails'] = false;

// The old option's hundred entries move in once.
update_option( WPCPM_Mail_Log::OLD_OPTION, array(
	array( 'time' => 1791500000, 'to' => 'a***@example.test', 'context' => 'call-booked', 'sent' => true ),
	array( 'time' => 1791400000, 'to' => 'b***@example.test', 'context' => 'nobody-knows', 'sent' => false ),
) );
WPCPM_Mail_Log::maybe_upgrade();
ck( 'the upgrade creates the table and stamps the version', array( WPCPM_Mail_Log::exists(), (int) get_option( WPCPM_Mail_Log::OPT_VERSION ) ), array( true, 1 ) );
ck( 'and moves the old entries in, then deletes the option', array( WPCPM_Mail_Log::find( array() )['total'], get_option( WPCPM_Mail_Log::OLD_OPTION, 'gone' ) ), array( 2, 'gone' ) );
$moved = WPCPM_Mail_Log::find( array() )['rows'];
ck( 'a moved entry keeps its masked address, context, area and status, with no subject; an unknown context is Other', array( $moved[0]['to_email'], $moved[0]['template'], $moved[0]['module'], $moved[0]['status'], $moved[0]['subject'], $moved[1]['template'], $moved[1]['module'], $moved[1]['status'] ), array( 'a***@example.test', 'call-booked', 'calls', 'sent', '', 'other', 'other', 'failed' ) );
WPCPM_Mail_Log::maybe_upgrade();
ck( 'a second load does nothing more', WPCPM_Mail_Log::find( array() )['total'], 2 );

// Two first requests: both read the hundred, one deletes the option, and only that one writes.
$GLOBALS['stale'][ WPCPM_Mail_Log::OLD_OPTION ] = array( array( 'time' => 1791500000, 'to' => 'a***@example.test', 'context' => 'call-booked', 'sent' => true ) );
ck( 'a request that read the option after another deleted it moves nothing', array( WPCPM_Mail_Log::migrate_old_option(), WPCPM_Mail_Log::find( array() )['total'] ), array( 0, 2 ) );
$GLOBALS['stale'] = array();

// Once found, the table is not asked for again this request.
$asked = count( $GLOBALS['wpdb']->queries );
WPCPM_Mail_Log::exists();
WPCPM_Mail_Log::exists();
ck( 'exists() asks the database once a request once the table is found', count( $GLOBALS['wpdb']->queries ), $asked );
ck( 'and the question escapes the name for LIKE', count( preg_grep( '/^SELECT name FROM sqlite_master/', $GLOBALS['wpdb']->queries ) ) > 0, true );

fresh_table();

// Rows in and out.
$id = WPCPM_Mail_Log::add( row() );
ck( 'add() returns the new id', $id > 0, true );
$got = WPCPM_Mail_Log::find( array() );
ck( 'find() returns it with every column', array( $got['total'], $got['rows'][0]['to_email'], (int) $got['rows'][0]['user_id'], $got['rows'][0]['template'], (int) $got['rows'][0]['is_test'] ), array( 1, 'ada@example.test', 7, 'call-booked', 0 ) );

$long = str_repeat( 'é', 300 );
WPCPM_Mail_Log::add( row( array( 'subject' => $long, 'error' => $long, 'to_name' => $long, 'user_id' => null ) ) );
$r = WPCPM_Mail_Log::find( array( 'search' => 'éé' ) )['rows'][0];
ck( 'a long subject, error and name are cut at their columns without breaking a character', array( mb_strlen( $r['subject'] ), mb_strlen( $r['error'] ), mb_strlen( $r['to_name'] ), mb_check_encoding( $r['subject'], 'UTF-8' ), $r['user_id'] ), array( 255, 255, 191, true, null ) );

WPCPM_Mail_Log::add( row( array( 'subject' => "Broken \xC3\x28 byte", 'to_email' => 'bytes@example.test' ) ) );
$r = WPCPM_Mail_Log::find( array( 'search' => 'bytes@' ) )['rows'];
ck( 'text that is not valid UTF-8 is made valid before the insert, and the row is written', array( count( $r ), isset( $r[0] ) && mb_check_encoding( $r[0]['subject'], 'UTF-8' ), isset( $r[0] ) && 0 === strpos( $r[0]['subject'], 'Broken ' ) ), array( 1, true, true ) );

// Filters.
fresh_table();
WPCPM_Mail_Log::add( row( array( 'sent_at' => '2026-10-01 08:00:00', 'to_email' => 'ben@example.test', 'to_name' => 'Ben Ode', 'recipient_type' => 'mentor' ) ) );
WPCPM_Mail_Log::add( row( array( 'sent_at' => '2026-10-05 08:00:00', 'module' => 'wordpress', 'template' => 'wp-password-reset', 'subject' => 'Password Reset, sent on request', 'recipient_type' => 'other' ) ) );
WPCPM_Mail_Log::add( row( array( 'sent_at' => '2026-10-09 08:00:00', 'status' => 'failed', 'error' => 'Could not instantiate mail function.', 'subject' => 'The draft was not sent' ) ) );
WPCPM_Mail_Log::add( row( array( 'sent_at' => '2026-10-09 09:00:00', 'template' => 'test-student', 'module' => 'invitations', 'is_test' => true, 'subject' => '100% ready, o\'brien a_b a\\b' ) ) );

ck( 'newest first', array_column( WPCPM_Mail_Log::find( array() )['rows'], 'sent_at' ), array( '2026-10-09 09:00:00', '2026-10-09 08:00:00', '2026-10-05 08:00:00', '2026-10-01 08:00:00' ) );
ck( 'by area', WPCPM_Mail_Log::find( array( 'module' => 'wordpress' ) )['total'], 1 );
ck( 'by recipient type', WPCPM_Mail_Log::find( array( 'recipient_type' => 'mentor' ) )['total'], 1 );
ck( 'by status', WPCPM_Mail_Log::find( array( 'status' => 'failed' ) )['total'], 1 );
ck( 'tests, by the test filter', WPCPM_Mail_Log::find( array( 'status' => WPCPM_Mail_Log::FILTER_TEST ) )['total'], 1 );
ck( 'by template', WPCPM_Mail_Log::find( array( 'template' => 'wp-password-reset' ) )['total'], 1 );
ck( 'by time, from inclusive and to exclusive', WPCPM_Mail_Log::find( array( 'from' => '2026-10-05 08:00:00', 'to' => '2026-10-09 08:00:00' ) )['total'], 1 );
ck( 'search finds the address, the name and the subject', array( WPCPM_Mail_Log::find( array( 'search' => 'ben@' ) )['total'], WPCPM_Mail_Log::find( array( 'search' => 'ode' ) )['total'], WPCPM_Mail_Log::find( array( 'search' => 'reset' ) )['total'] ), array( 1, 1, 1 ) );

// The page of rows is not prepared a second time: "sent" holds "%s" once it is a LIKE pattern.
$sent = WPCPM_Mail_Log::find( array( 'search' => 'sent' ) );
ck( 'a search of "sent" returns as many rows as it counts, and raises no error', array( $sent['total'], count( $sent['rows'] ), $GLOBALS['wpdb']->last_error ), array( 2, 2, '' ) );

// Review Focus 3: SQL's own characters are read literally.
ck( 'search "100%" finds only the subject that holds it', WPCPM_Mail_Log::find( array( 'search' => '100%' ) )['total'], 1 );
ck( 'search "o\'brien" finds it and raises no error', array( WPCPM_Mail_Log::find( array( 'search' => "o'brien" ) )['total'], $GLOBALS['wpdb']->last_error ), array( 1, '' ) );
ck( 'search "a_b" does not read "_" as any character', WPCPM_Mail_Log::find( array( 'search' => 'a_b' ) )['total'], 1 );
ck( 'search "a%b" finds nothing', WPCPM_Mail_Log::find( array( 'search' => 'a%b' ) )['total'], 0 );
ck( 'search "a\\b" finds only the subject that holds the backslash', WPCPM_Mail_Log::find( array( 'search' => 'a\\b' ) )['total'], 1 );
ck( 'and the search sends no ESCAPE of its own: the database adds the one it reads', count( preg_grep( "/ESCAPE '!'/", $GLOBALS['wpdb']->queries ) ), 0 );

ck( 'paging: 2 per page, page 2', array_column( WPCPM_Mail_Log::find( array(), 2, 2 )['rows'], 'sent_at' ), array( '2026-10-05 08:00:00', '2026-10-01 08:00:00' ) );
ck( 'latest()', WPCPM_Mail_Log::latest()['sent_at'], '2026-10-09 09:00:00' );
ck( 'count_since()', array( WPCPM_Mail_Log::count_since( '2026-10-05 08:00:00' ), WPCPM_Mail_Log::count_since( '2026-10-10 00:00:00' ) ), array( 3, 0 ) );
ck( 'failures_since()', array( WPCPM_Mail_Log::failures_since( '2026-10-09 00:00:00' ), WPCPM_Mail_Log::failures_since( '2026-10-09 08:00:01' ) ), array( 1, 0 ) );

// The cleanup keeps 30 days.
$now = strtotime( '2026-10-31 09:00:00 UTC' ); // The cutoff is 1 October 09:00 UTC.
ck( 'purge() deletes what is older than 30 days and says how many', WPCPM_Mail_Log::purge( $now ), 1 );
ck( 'the rest stay', WPCPM_Mail_Log::find( array() )['total'], 3 );
ck( 'and the run is stamped', (int) get_option( WPCPM_Mail_Log::OPT_PURGED ), $now );
ck( 'purge() uses no DELETE ... LIMIT', count( preg_grep( '/^DELETE.*LIMIT/i', $GLOBALS['wpdb']->queries ) ), 0 );

// Export and erase, by address.
$export = WPCPM_Mail_Log::export( 'ada@example.test' );
ck( 'export lists the address\'s entries with no other person\'s', array( count( $export['data'] ), $export['done'], $export['data'][0]['group_id'] ), array( 3, true, 'wpcpm-mail-log' ) );
$names = array_column( $export['data'][0]['data'], 'name' );
ck( 'each exported entry names when, the email, the subject and the status', $names, array( 'When', 'Email', 'Subject', 'Status' ) );
$erase = WPCPM_Mail_Log::erase( 'ada@example.test' );
ck( 'erase removes them and says so', array( $erase['items_removed'], $erase['done'], WPCPM_Mail_Log::find( array() )['total'] ), array( true, true, 0 ) );
ck( 'and an address with nothing logged removes nothing', WPCPM_Mail_Log::erase( 'nobody@example.test' )['items_removed'], false );

// The statuses in words.
ck( 'status_label()', array_map( array( 'WPCPM_Mail_Log', 'status_label' ), array( 'sent', 'failed', 'unconfirmed', '' ) ), array( 'Handed to the mail server', 'Failed', 'Not confirmed', 'Not recorded' ) );

// Uninstall.
update_option( WPCPM_Mail_Log::OPT_VERSION, 1 );
update_option( WPCPM_Mail_Log::OPT_PURGED, 1 );
WPCPM_Mail_Log::uninstall();
ck( 'uninstall drops the table, deletes both options and clears the job', array( WPCPM_Mail_Log::exists(), get_option( WPCPM_Mail_Log::OPT_VERSION, 'gone' ), get_option( WPCPM_Mail_Log::OPT_PURGED, 'gone' ), in_array( WPCPM_Mail_Log::PURGE_HOOK, $GLOBALS['cleared'], true ) ), array( false, 'gone', 'gone', true ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php bin/test-mail-log.php`
Expected: a fatal error, `includes/mail/class-wpcpm-mail-log.php` is missing.

- [ ] **Step 4: Write the store**

Create `includes/mail/class-wpcpm-mail-log.php`:

```php
<?php
/**
 * The record of every email the site sends.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One row per recipient of every email, kept 30 days: when, to whom, which email, its subject and
 * whether WordPress handed it to the mail server. Never the message text, the attachments, or
 * anything secret (the owner's decision of 10 October 2026): an email's body can hold a sign-in
 * link or a code, and the log exists to answer "was it sent", which the subject, the address and
 * the status answer.
 *
 * Booted from the plugin's bootstrap (`init()`), not from the Emails tool, so a site that filters
 * the tool out of `wpcpm_tools` still keeps its log: the table's creation and upgrade, the daily
 * cleanup and the privacy tools' hooks start here.
 *
 * The SQL runs on MySQL, where the site lives, and on WordPress's SQLite integration, where its
 * local copies and the suites run: no `DELETE ... LIMIT`, no `SHOW` but `SHOW TABLES LIKE`, and a
 * search escapes LIKE's characters with `$wpdb->esc_like()` and writes no ESCAPE clause, since both
 * read a backslash and the integration adds `ESCAPE '\'` itself.
 */
final class WPCPM_Mail_Log {

	const SCHEMA_VERSION     = 1;
	const OPT_VERSION        = 'wpcpm_mail_log_version';
	const OPT_PURGED         = 'wpcpm_mail_log_purged';
	const OLD_OPTION         = 'wpcpm_mail_log';
	const KEEP_DAYS          = 30;
	const PURGE_BATCH        = 1000;
	const PURGE_HOOK         = 'wpcpm_mail_log_purge';
	const PER_PAGE           = 50;
	const STATUS_SENT        = 'sent';
	const STATUS_FAILED      = 'failed';
	const STATUS_UNCONFIRMED = 'unconfirmed';
	const FILTER_TEST        = 'test';

	/** How many rows one page of the privacy tools handles. */
	const PRIVACY_PAGE = 100;

	/**
	 * Whether the table was found this request. Only a table found is kept: a missing one is looked
	 * for again, so the upgrade that creates it is seen at once.
	 *
	 * @var bool
	 */
	private static $found = false;

	/**
	 * Hooks: the table's creation and upgrade, the daily cleanup, and WordPress's privacy tools.
	 * Called from `wpcpm_bootstrap()`, beside `WPCPM_Mail::init()`.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( self::PURGE_HOOK, array( __CLASS__, 'purge' ) );

		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	/**
	 * The daily cleanup, scheduled once.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::PURGE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PURGE_HOOK );
		}
	}

	/**
	 * Stop the daily cleanup, on deactivation (`wpcpm_deactivate()`).
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::PURGE_HOOK );
	}

	/**
	 * The log's entry in WordPress's Export Personal Data tool.
	 *
	 * @param array $exporters WordPress's exporters.
	 * @return array The same, with the log's.
	 */
	public static function register_exporter( $exporters ) {
		$exporters = is_array( $exporters ) ? $exporters : array();

		$exporters['wpcpm-mail-log'] = array(
			'exporter_friendly_name' => __( 'Emails the site sent', 'wpcredits-program-manager' ),
			'callback'               => array( __CLASS__, 'export' ),
		);

		return $exporters;
	}

	/**
	 * The log's entry in WordPress's Erase Personal Data tool.
	 *
	 * @param array $erasers WordPress's erasers.
	 * @return array The same, with the log's.
	 */
	public static function register_eraser( $erasers ) {
		$erasers = is_array( $erasers ) ? $erasers : array();

		$erasers['wpcpm-mail-log'] = array(
			'eraser_friendly_name' => __( 'Emails the site sent', 'wpcredits-program-manager' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * The table's name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'wpcpm_mail_log';
	}

	/**
	 * The table as `dbDelta()` reads it: one column per line, two spaces after PRIMARY KEY.
	 *
	 * @return string
	 */
	public static function schema() {
		global $wpdb;

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		return "CREATE TABLE {$table} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
sent_at datetime NOT NULL,
to_email varchar(191) NOT NULL DEFAULT '',
user_id bigint(20) unsigned DEFAULT NULL,
to_name varchar(191) NOT NULL DEFAULT '',
recipient_type varchar(20) NOT NULL DEFAULT '',
module varchar(20) NOT NULL DEFAULT '',
template varchar(64) NOT NULL DEFAULT '',
subject varchar(255) NOT NULL DEFAULT '',
status varchar(12) NOT NULL DEFAULT '',
error varchar(255) NOT NULL DEFAULT '',
is_test tinyint(1) NOT NULL DEFAULT 0,
delivery varchar(20) DEFAULT NULL,
delivery_at datetime DEFAULT NULL,
PRIMARY KEY  (id),
KEY sent_at (sent_at),
KEY to_email (to_email),
KEY module_sent (module,sent_at),
KEY template (template),
KEY user_id (user_id)
) {$collate};";
	}

	/**
	 * Whether the table is there, asked once a request once it is found.
	 *
	 * @return bool
	 */
	public static function exists() {
		global $wpdb;

		if ( self::$found ) {
			return true;
		}

		$table       = self::table();
		self::$found = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A table of the plugin's own, asked for once a request.

		return self::$found;
	}

	/**
	 * Forget that the table was found. Test-only: a suite that takes the table away calls this.
	 */
	public static function reset() {
		self::$found = false;
	}

	/**
	 * Create or upgrade the table, on `init`, as the plugin's other stores do: updates arrive by
	 * replacing files, so the activation hook cannot be relied on. The version is stamped only once
	 * the table is there, so a creation that failed is tried again on the next load. The old option's
	 * entries move in on the first creation.
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( self::OPT_VERSION, 0 ) >= self::SCHEMA_VERSION ) {
			return;
		}

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		dbDelta( self::schema() );

		if ( ! self::exists() ) {
			return;
		}

		self::migrate_old_option();
		update_option( self::OPT_VERSION, self::SCHEMA_VERSION );
	}

	/**
	 * Move the hundred entries the plugin kept in an option before 1.122.18 into the table, then
	 * forget them. Their addresses stay masked as they were stored, and they have no subject. The
	 * option is deleted before anything is written, and only the request whose delete removed it
	 * writes, so two first requests cannot both move the hundred. A context the catalog does not
	 * know is stored as Other.
	 *
	 * @return int How many entries moved.
	 */
	public static function migrate_old_option() {
		$old = get_option( self::OLD_OPTION, array() );

		if ( ! delete_option( self::OLD_OPTION ) ) {
			return 0;
		}

		$moved = 0;

		foreach ( is_array( $old ) ? $old : array() as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['time'] ) ) {
				continue;
			}

			$context = isset( $entry['context'] ) ? (string) $entry['context'] : '';
			$context = null === WPCPM_Mail_Catalog::get( $context ) ? WPCPM_Mail_Catalog::OTHER : $context;

			$moved += self::add(
				array(
					'sent_at'        => gmdate( 'Y-m-d H:i:s', (int) $entry['time'] ),
					'to_email'       => isset( $entry['to'] ) ? (string) $entry['to'] : '',
					'user_id'        => null,
					'to_name'        => '',
					'recipient_type' => '',
					'module'         => WPCPM_Mail_Catalog::module_of( $context ),
					'template'       => $context,
					'subject'        => '',
					'status'         => empty( $entry['sent'] ) ? self::STATUS_FAILED : self::STATUS_SENT,
					'error'          => '',
					'is_test'        => 0 === strpos( $context, 'test-' ),
				)
			) ? 1 : 0;
		}

		return $moved;
	}

	/**
	 * Write one row.
	 *
	 * @param array $row sent_at (UTC `Y-m-d H:i:s`), to_email, user_id (int or null), to_name,
	 *                   recipient_type, module, template, subject, status, error, is_test (bool).
	 * @return int The new row's id, or 0 when nothing was written (no table, or the insert failed).
	 */
	public static function add( array $row ) {
		global $wpdb;

		if ( ! self::exists() ) {
			return 0;
		}

		$user_id = isset( $row['user_id'] ) && (int) $row['user_id'] > 0 ? (int) $row['user_id'] : null;

		$data = array(
			'sent_at'        => isset( $row['sent_at'] ) ? (string) $row['sent_at'] : gmdate( 'Y-m-d H:i:s' ),
			'to_email'       => self::cut( isset( $row['to_email'] ) ? $row['to_email'] : '', 191 ),
			'user_id'        => $user_id,
			'to_name'        => self::cut( isset( $row['to_name'] ) ? $row['to_name'] : '', 191 ),
			'recipient_type' => self::cut( isset( $row['recipient_type'] ) ? $row['recipient_type'] : '', 20 ),
			'module'         => self::cut( isset( $row['module'] ) ? $row['module'] : '', 20 ),
			'template'       => self::cut( isset( $row['template'] ) ? $row['template'] : '', 64 ),
			'subject'        => self::cut( isset( $row['subject'] ) ? $row['subject'] : '', 255 ),
			'status'         => self::cut( isset( $row['status'] ) ? $row['status'] : '', 12 ),
			'error'          => self::cut( isset( $row['error'] ) ? $row['error'] : '', 255 ),
			'is_test'        => empty( $row['is_test'] ) ? 0 : 1,
		);

		// WordPress writes NULL for a null value whatever its format.
		$format = array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

		if ( false === $wpdb->insert( self::table(), $data, $format ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The table is ours.
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * A text made valid UTF-8 and cut to a column's length in characters, never inside a character.
	 * Invalid bytes are replaced rather than refused: WordPress's insert drops a whole row whose text
	 * the column's character set cannot hold.
	 *
	 * @param mixed $text  The text.
	 * @param int   $chars The column's length.
	 * @return string
	 */
	private static function cut( $text, $chars ) {
		$text = wp_check_invalid_utf8( (string) $text, true );

		return mb_strlen( $text ) > $chars ? mb_substr( $text, 0, $chars ) : $text;
	}

	/**
	 * A LIKE pattern that matches the text anywhere, with LIKE's own characters read literally.
	 *
	 * @param string $text What was typed.
	 * @return string
	 */
	private static function like( $text ) {
		global $wpdb;

		return '%' . $wpdb->esc_like( (string) $text ) . '%';
	}

	/**
	 * The WHERE clause for a set of filters, prepared.
	 *
	 * @param array $filters search, exact_email, module, recipient_type, status, template, from, to.
	 * @return string " WHERE ..." or ''.
	 */
	private static function where( array $filters ) {
		global $wpdb;

		$parts = array();

		if ( isset( $filters['search'] ) && '' !== trim( (string) $filters['search'] ) ) {
			$like    = self::like( trim( (string) $filters['search'] ) );
			$parts[] = $wpdb->prepare( '( to_email LIKE %s OR to_name LIKE %s OR subject LIKE %s )', $like, $like, $like );
		}

		// One address exactly, for the privacy tools.
		if ( isset( $filters['exact_email'] ) && '' !== (string) $filters['exact_email'] ) {
			$parts[] = $wpdb->prepare( 'to_email = %s', (string) $filters['exact_email'] );
		}

		foreach ( array( 'module', 'recipient_type', 'template' ) as $key ) {
			if ( isset( $filters[ $key ] ) && '' !== (string) $filters[ $key ] ) {
				$parts[] = $wpdb->prepare( "{$key} = %s", (string) $filters[ $key ] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The column is one of three names written here.
			}
		}

		if ( isset( $filters['status'] ) && '' !== (string) $filters['status'] ) {
			$parts[] = self::FILTER_TEST === $filters['status'] ? 'is_test = 1' : $wpdb->prepare( 'status = %s', (string) $filters['status'] );
		}

		if ( ! empty( $filters['from'] ) ) {
			$parts[] = $wpdb->prepare( 'sent_at >= %s', (string) $filters['from'] );
		}

		if ( ! empty( $filters['to'] ) ) {
			$parts[] = $wpdb->prepare( 'sent_at < %s', (string) $filters['to'] );
		}

		return $parts ? ' WHERE ' . implode( ' AND ', $parts ) : '';
	}

	/**
	 * One page of rows, newest first, and how many rows the filters match.
	 *
	 * The clause is prepared once, in `where()`, and the page's two numbers are written in as
	 * integers: preparing the clause a second time would read a search's `%` as a placeholder.
	 *
	 * @param array $filters  See `where()`.
	 * @param int   $page     The page, from 1.
	 * @param int   $per_page Rows per page.
	 * @return array{rows:array<int,array>,total:int}
	 */
	public static function find( array $filters, $page = 1, $per_page = self::PER_PAGE ) {
		global $wpdb;

		if ( ! self::exists() ) {
			return array(
				'rows'  => array(),
				'total' => 0,
			);
		}

		$table    = self::table();
		$where    = self::where( $filters );
		$per_page = max( 1, (int) $per_page );
		$offset   = ( max( 1, (int) $page ) - 1 ) * $per_page;

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}{$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours; the clause is prepared in where().
		$rows  = $wpdb->get_results( "SELECT * FROM {$table}{$where} ORDER BY sent_at DESC, id DESC LIMIT " . (int) $per_page . ' OFFSET ' . (int) $offset, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- As above; the two numbers are integers.

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * The newest row.
	 *
	 * @return array|null
	 */
	public static function latest() {
		$found = self::find( array(), 1, 1 );

		return isset( $found['rows'][0] ) ? $found['rows'][0] : null;
	}

	/**
	 * How many emails were written since a time, whatever their status.
	 *
	 * @param string $since_utc UTC `Y-m-d H:i:s`.
	 * @return int
	 */
	public static function count_since( $since_utc ) {
		return self::find( array( 'from' => (string) $since_utc ), 1, 1 )['total'];
	}

	/**
	 * How many emails failed since a time.
	 *
	 * @param string $since_utc UTC `Y-m-d H:i:s`.
	 * @return int
	 */
	public static function failures_since( $since_utc ) {
		return self::find(
			array(
				'status' => self::STATUS_FAILED,
				'from'   => (string) $since_utc,
			),
			1,
			1
		)['total'];
	}

	/**
	 * Delete what is older than 30 days, a thousand rows at a time, and stamp the run.
	 *
	 * @param int $now The time to count back from; 0 for now.
	 * @return int How many rows went.
	 */
	public static function purge( $now = 0 ) {
		global $wpdb;

		$now = $now ? (int) $now : time();

		if ( ! self::exists() ) {
			return 0;
		}

		$table   = self::table();
		$cutoff  = gmdate( 'Y-m-d H:i:s', $now - self::KEEP_DAYS * DAY_IN_SECONDS );
		$removed = 0;

		// At most twenty batches a run: a backlog larger than that finishes on the next.
		for ( $batch = 0; $batch < 20; $batch++ ) {
			$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE sent_at < %s ORDER BY id LIMIT %d", $cutoff, self::PURGE_BATCH ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours.

			if ( ! $ids ) {
				break;
			}

			$removed += (int) $wpdb->query( "DELETE FROM {$table} WHERE id IN (" . implode( ',', $ids ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- Integers only.
		}

		update_option( self::OPT_PURGED, $now, false );

		return $removed;
	}

	/**
	 * Run the cleanup when it has not run for a day, for a site whose scheduled jobs are late.
	 */
	public static function maybe_purge() {
		if ( time() - (int) get_option( self::OPT_PURGED, 0 ) >= DAY_IN_SECONDS ) {
			self::purge();
		}
	}

	/**
	 * WordPress's Export Personal Data tool: a person's entries, by address.
	 *
	 * @param string $email The address.
	 * @param int    $page  The page, from 1.
	 * @return array{data:array,done:bool}
	 */
	public static function export( $email, $page = 1 ) {
		$found = self::find( self::exact( $email ), $page, self::PRIVACY_PAGE );
		$data  = array();

		foreach ( $found['rows'] as $row ) {
			$data[] = array(
				'group_id'    => 'wpcpm-mail-log',
				'group_label' => __( 'Emails the site sent', 'wpcredits-program-manager' ),
				'item_id'     => 'wpcpm-mail-log-' . (int) $row['id'],
				'data'        => array(
					array(
						'name'  => __( 'When', 'wpcredits-program-manager' ),
						'value' => $row['sent_at'] . ' UTC',
					),
					array(
						'name'  => __( 'Email', 'wpcredits-program-manager' ),
						'value' => WPCPM_Mail_Catalog::label( $row['template'] ),
					),
					array(
						'name'  => __( 'Subject', 'wpcredits-program-manager' ),
						'value' => $row['subject'],
					),
					array(
						'name'  => __( 'Status', 'wpcredits-program-manager' ),
						'value' => self::status_label( $row['status'] ),
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => (int) $page * self::PRIVACY_PAGE >= $found['total'],
		);
	}

	/**
	 * WordPress's Erase Personal Data tool: delete a person's entries, by address.
	 *
	 * @param string $email The address.
	 * @param int    $page  The page, from 1; one call deletes them all.
	 * @return array{items_removed:bool,items_retained:bool,messages:array,done:bool}
	 */
	public static function erase( $email, $page = 1 ) {
		global $wpdb;

		$removed = 0;

		if ( self::exists() && '' !== trim( (string) $email ) ) {
			$removed = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE to_email = %s', trim( (string) $email ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours.
		}

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * The filter that matches one address exactly, which `where()` reads as `to_email = %s`.
	 *
	 * @param string $email The address.
	 * @return array
	 */
	private static function exact( $email ) {
		return array( 'exact_email' => trim( (string) $email ) );
	}

	/**
	 * A status in words.
	 *
	 * @param string $status The stored status.
	 * @return string
	 */
	public static function status_label( $status ) {
		switch ( (string) $status ) {
			case self::STATUS_SENT:
				return __( 'Handed to the mail server', 'wpcredits-program-manager' );
			case self::STATUS_FAILED:
				return __( 'Failed', 'wpcredits-program-manager' );
			case self::STATUS_UNCONFIRMED:
				return __( 'Not confirmed', 'wpcredits-program-manager' );
		}

		return __( 'Not recorded', 'wpcredits-program-manager' );
	}

	/**
	 * Remove everything the log keeps: the table, its two options, the old option and the cleanup
	 * job.
	 */
	public static function uninstall() {
		global $wpdb;

		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- The table is ours.
		self::$found = false;

		delete_option( self::OPT_VERSION );
		delete_option( self::OPT_PURGED );
		delete_option( self::OLD_OPTION );
		wp_clear_scheduled_hook( self::PURGE_HOOK );
	}
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php bin/test-mail-log.php`
Expected: `ALL PASS (70 checks)`.

- [ ] **Step 6: Load, boot and deactivate the store**

In `wpcredits-program-manager.php`, replace:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
```

with:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-log.php';
```

In `wpcpm_bootstrap()` in the same file, replace:

```php
	WPCPM_Mail::init();
```

with:

```php
	WPCPM_Mail::init();
	// The email log, booted here rather than by the Emails tool, so a site that filters the tool out
	// of `wpcpm_tools` still keeps it (1.122.18).
	WPCPM_Mail_Log::init();
```

In `wpcpm_deactivate()` in the same file, replace:

```php
	WPCPM_Tools::deactivate();
	flush_rewrite_rules();
```

with:

```php
	WPCPM_Tools::deactivate();
	WPCPM_Mail_Log::deactivate();
	flush_rewrite_rules();
```

In `uninstall.php`, replace:

```php
require_once plugin_dir_path( __FILE__ ) . 'includes/mail/class-wpcpm-mail-catalog.php';
```

with:

```php
require_once plugin_dir_path( __FILE__ ) . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/mail/class-wpcpm-mail-log.php';
```

In the same file, replace `WPCPM_Mail::clear_log();` (Task 4 takes that line away):

```php
WPCPM_Mail::clear_log();
```

with:

```php
WPCPM_Mail::clear_log();
// The email log's table, its two options and its daily cleanup (1.122.18).
WPCPM_Mail_Log::uninstall();
```

- [ ] **Step 7: Hold the uninstall to the log's table, options and job**

bin/test-uninstall.php runs uninstall.php whole, as WordPress does. Its `$wpdb` stand-in already answers `DROP TABLE IF EXISTS <name>` and keeps every statement in `$wpdb->queries`.

In `bin/test-uninstall.php`, replace line 673:

```php
$GLOBALS['opts']['wpcpm_mail_log']     = array( array( 'to' => 'student-one@example.test' ) );
```

with:

```php
$GLOBALS['opts']['wpcpm_mail_log']     = array( array( 'to' => 'student-one@example.test' ) );
// The email log's schema version and the time of its last cleanup (1.122.18); its table is dropped.
$GLOBALS['opts']['wpcpm_mail_log_version'] = 1;
$GLOBALS['opts']['wpcpm_mail_log_purged']  = time();
```

Replace the `$other_hooks` line (754 before the edit above, 757 after it):

```php
$other_hooks = array( 'wpcpm_ceiling_sweep', 'wpcpm_purge_applications', 'wpcpm_agreement_discard', 'wpcpm_agreement_reminders', 'wpcpm_invite_expire', 'wpcpm_report_ask_queue', 'wpcpm_report_autodraft', 'wpcpm_sponsor_agreement_discard', 'wpcpm_purge_sponsor_applications', 'wpcpm_sponsors_daily', 'wpcpm_sponsors_sync_tick', 'wpcpm_checker_weekly_check', 'wpcpm_checker_slack_retry', 'wpcpm_duplicates_scan', 'wpcpm_duplicates_tick', 'wpcpm_duplicates_purge' );
```

with:

```php
$other_hooks = array( 'wpcpm_ceiling_sweep', 'wpcpm_purge_applications', 'wpcpm_agreement_discard', 'wpcpm_agreement_reminders', 'wpcpm_invite_expire', 'wpcpm_report_ask_queue', 'wpcpm_report_autodraft', 'wpcpm_sponsor_agreement_discard', 'wpcpm_purge_sponsor_applications', 'wpcpm_sponsors_daily', 'wpcpm_sponsors_sync_tick', 'wpcpm_checker_weekly_check', 'wpcpm_checker_slack_retry', 'wpcpm_duplicates_scan', 'wpcpm_duplicates_tick', 'wpcpm_duplicates_purge', 'wpcpm_mail_log_purge' );
```

Replace the mail log's check:

```php
ck( 'the mail log goes, with the addresses in it', get_option( 'wpcpm_mail_log', 'gone' ), 'gone' );
```

with:

```php
ck( 'the mail log goes, with the addresses in it', get_option( 'wpcpm_mail_log', 'gone' ), 'gone' );
ck( 'and so do the email log\'s table, its schema version and the time of its last cleanup', array( in_array( 'DROP TABLE IF EXISTS wp_wpcpm_mail_log', $wpdb->queries, true ), get_option( 'wpcpm_mail_log_version', 'gone' ), get_option( 'wpcpm_mail_log_purged', 'gone' ) ), array( true, 'gone', 'gone' ) );
```

And in `$names`, the list compared with the classes' own constants, replace:

```php
	'WPCPM_Mail::RUN_OPTION'                        => 'wpcpm_invite_run',
```

with:

```php
	'WPCPM_Mail::RUN_OPTION'                        => 'wpcpm_invite_run',
	'WPCPM_Mail_Log::OLD_OPTION'                    => 'wpcpm_mail_log',
	'WPCPM_Mail_Log::OPT_VERSION'                   => 'wpcpm_mail_log_version',
	'WPCPM_Mail_Log::OPT_PURGED'                    => 'wpcpm_mail_log_purged',
	'WPCPM_Mail_Log::PURGE_HOOK'                    => 'wpcpm_mail_log_purge',
```

- [ ] **Step 8: Run the touched suites to verify they pass**

Run, one per command: `php bin/test-mail-log.php`, `php bin/test-uninstall.php`, `php bin/test-roles.php`.
Expected: `ALL PASS (70 checks)`, `ALL PASS (35 checks)`, `ALL PASS`.

- [ ] **Step 9: Run the whole battery**

Run, from the plugin root, every suite in its own process, then the references check:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 10: Commit**

```bash
git add bin/stubs/sqlite-wpdb.php includes/mail/class-wpcpm-mail-log.php bin/test-mail-log.php wpcredits-program-manager.php uninstall.php bin/test-uninstall.php
git commit -m "Mail log store: a table of who, what, when and status, kept 30 days, booted from the bootstrap"
```

---

### Task 3: The readers: Settings > Mail and the health card read the new log

**Files:**
- Modify: `includes/class-wpcpm-settings-screen.php` (`render_mail_log()`, lines 1602 to 1693)
- Modify: `includes/modules/class-wpcpm-administrators-cards.php` (the `health()` docblock at line 1177; the health data at lines 1222 to 1231; the mail line at lines 1929 to 1936)
- Modify: `bin/test-settings.php` (its `WPCPM_Mail` stand-in, lines 304 to 323; the Mail tab's check at line 2694; its `WPCPM_Tools` stand-in at lines 1421 to 1433 stays as it is and answers null for `emails`)
- Modify: `bin/test-administrators-dashboard.php` (its `WPCPM_Mail` stand-in at lines 514 to 518 gains `mask_address()`; stand-ins for `WPCPM_Mail_Log` and `WPCPM_Tools`; the catalog loaded; the seeded mail at line 779; the mail line's checks at line 1065)
- Modify: `bin/stubs/overview-reads.php` (lines 347 to 360, the stand-ins bin/test-overview.php and bin/test-admin-menu.php share: `WPCPM_Mail` loses `log()`, and `WPCPM_Mail_Log` is stood in)
- Suites to run: `bin/test-settings.php`, `bin/test-administrators-dashboard.php`, `bin/test-overview.php`, `bin/test-admin-menu.php`, then the whole battery

`WPCPM_Mail::log()` and `failures()` stay until Task 4, and nothing calls them after this task, so Task 4 can take them away with the references check green.

**Interfaces:**
- Consumes: `WPCPM_Mail_Log::latest()`, `::failures_since()`, `::status_label()`, `::KEEP_DAYS`, `::STATUS_FAILED` (Task 2); `WPCPM_Mail_Catalog::label()` (Task 1); `WPCPM_Mail::mask_address( string $address ): string` (existing); `WPCPM_Tools::get( 'emails' )` (existing; null until Task 6 registers the tool, and the readers then fall back to `admin_url( 'admin.php?page=wpcpm-tool-emails' )`, the address the tool will have).
- Produces:
  - `WPCPM_Administrators_Cards::health()['mail']`: `array{latest:array,failed:int}`, the newest row of the log (or `array()`) and how many failed in the last day.
  - The Log's address in both readers: `add_query_arg( 'tab', 'log', <the tool's admin_url()> )`, and the failed emails' `add_query_arg( 'status', 'failed', <that> )`.

- [ ] **Step 1: Write the failing checks**

bin/test-settings.php stands in for the mail layer the Mail tab reads, and its stand-in's made-up log names a real address. In `bin/test-settings.php`, replace the `WPCPM_Mail` stand-in, lines 304 to 324 (from its docblock, `/** The mail layer, for the Mail tab: what is queued, the sample buttons' action, the log. */`, through its closing brace and the blank line after it, up to the docblock that opens ` * One method's source`), with a stand-in for the mail layer without its log, and one for the email log:

```php
/** The mail layer, for the Mail tab: what is queued, and the sample buttons' action. */
class WPCPM_Mail {
	const ACTION_TEST = 'wpcpm_send_test_mail';

	public static $queued = 0;

	public static function queued() {
		return self::$queued;
	}
}

/** The email log, for the Mail tab's Recent mail: how many failed, and since when it was asked. */
class WPCPM_Mail_Log {
	const KEEP_DAYS     = 30;
	const STATUS_FAILED = 'failed';

	public static $failed = 0;
	public static $asked  = array();

	public static function failures_since( $since_utc ) {
		self::$asked[] = $since_utc;

		return self::$failed;
	}
}

```

Replace the Mail tab's check, `ck( 'and it draws what is waiting to be sent and the recent mail, with no Save anywhere on it', ... )` (its three lines from line 2694 before the edit above, and the blank line after them, up to the comment `// Each sample form carries its nonce under the name its handler reads it by`), with checks of the section that points to the Log. That suite's `esc_url()` hands an address back unchanged, so `&` stays `&`:

```php
ck( 'and it draws what is waiting to be sent and the way to the email log, with no Save anywhere on it',
    array( false !== strpos( $mail_html, '3 invitations are waiting to be sent.' ), false !== strpos( $mail_html, '<a class="button" href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=log">Open the email log</a>' ), substr_count( $mail_html, 'value="Save settings"' ) ),
    array( true, true, 0 ) );

// Recent mail points to the Emails tool's Log, which keeps every email for 30 days, and says how many
// failed in that time, linked to them; with none failed it says nothing of failures.
WPCPM_Mail_Log::$failed = 2;
WPCPM_Mail_Log::$asked  = array();
$mail_failed_html       = draw_settings( 'mail' );
WPCPM_Mail_Log::$failed = 0;
$asked_since            = isset( WPCPM_Mail_Log::$asked[0] ) ? (int) strtotime( WPCPM_Mail_Log::$asked[0] . ' UTC' ) : 0;

ck( 'Recent mail says the log keeps every email for 30 days, and counts the failures of those 30 days, linked to the failed emails',
    array( false !== strpos( $mail_failed_html, 'Every email the site sends is kept for 30 days in WPCredits Program &gt; Tools &gt; Emails, where it can be searched and filtered.' ), false !== strpos( $mail_failed_html, '2 emails failed in the last 30 days. <a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=log&status=failed">Show the failed emails</a>' ), abs( $asked_since - ( time() - 30 * DAY_IN_SECONDS ) ) <= 5, false !== strpos( $mail_html, 'failed in the last 30 days' ) ),
    array( true, true, true, false ) );

```

In `bin/test-administrators-dashboard.php`, the cards read through stand-ins for their owning classes. Replace the `WPCPM_Mail` stand-in (lines 514 to 518):

```php
class WPCPM_Mail {
	public static function log() { return isset( $GLOBALS['mail_log'] ) ? $GLOBALS['mail_log'] : array(); }
	public static function run() { return isset( $GLOBALS['invite_run'] ) ? $GLOBALS['invite_run'] : array(); }
	public static function queued() { return isset( $GLOBALS['invite_queued'] ) ? (int) $GLOBALS['invite_queued'] : 0; }
}
```

with one that masks an address as the real one does, and stand-ins for the email log and for the tool registry:

```php
class WPCPM_Mail {
	public static function run() { return isset( $GLOBALS['invite_run'] ) ? $GLOBALS['invite_run'] : array(); }
	public static function queued() { return isset( $GLOBALS['invite_queued'] ) ? (int) $GLOBALS['invite_queued'] : 0; }
	// The real one's rule: the mailbox's first character and the whole domain.
	public static function mask_address( $a ) { $at = strrpos( (string) $a, '@' ); return false === $at || 0 === $at ? ( '' === (string) $a ? '' : '***' ) : substr( (string) $a, 0, 1 ) . '***' . substr( (string) $a, $at ); }
}
// The email log the health card reads: its latest row, and how many failed since the time asked.
class WPCPM_Mail_Log {
	const STATUS_FAILED = 'failed';
	public static function latest() { return isset( $GLOBALS['mail_latest'] ) ? $GLOBALS['mail_latest'] : null; }
	public static function failures_since( $since ) { $GLOBALS['mail_failed_since'][] = $since; return isset( $GLOBALS['mail_failed'] ) ? (int) $GLOBALS['mail_failed'] : 0; }
	public static function status_label( $status ) { return 'sent' === $status ? 'Handed to the mail server' : ( 'failed' === $status ? 'Failed' : 'Not recorded' ); }
}
// The tool registry, with the Emails tool filtered out of it: the card links the Log at its address.
class WPCPM_Tools {
	public static function get( $id ) { return null; }
}
```

Load the real catalog, which is data and needs only `__()`; replace line 243:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';
```

with:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';
// The names the health card gives the emails it reads, which are the catalog's own data.
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
```

Seed the log's latest row and one failure, with an invented address, in place of the old log entry: replace line 779, the one that begins `$GLOBALS['mail_log']      = array( array( 'time' => 1756990000,`, with:

```php
$GLOBALS['mail_latest']   = array( 'sent_at' => '2026-09-04 12:46:40', 'to_email' => 'mia@example.test', 'template' => 'report-drafted', 'status' => 'sent' );
$GLOBALS['mail_failed']   = 1;
```

Replace the mail line's check (line 1065 before these edits). That suite's `esc_url()` hands an address back unchanged, so `&` stays `&`:

```php
ck( 'the probe verdict, the last mail and the invitation run are there', has( $health, 'blocked' ) && has( $health, 'report-drafted' ) && has( $health, '3 of 5' ), true );
```

with:

```php
ck( 'the probe verdict and the invitation run are there', has( $health, 'blocked' ) && has( $health, '3 of 5' ), true );
// The mail line reads the email log: the latest email by its name, its address masked, when, and its
// status in a sentence of its own; then the last day's failures; each linked to the Emails tool's Log.
$failed_since = isset( $GLOBALS['mail_failed_since'][0] ) ? (int) strtotime( $GLOBALS['mail_failed_since'][0] . ' UTC' ) : 0;
ck( 'the mail line names the latest email, masks its address, and gives its status in a sentence of its own',
	array( has( $health, 'Last email: Semester report drafted to m***@example.test on ' ), has( $health, '. Status: Handed to the mail server. <a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=log">Open the email log</a>' ), has( $health, 'mia@example.test' ) ),
	array( true, true, false ) );
ck( 'and counts the failures of the last day, linked to the Log\'s failed emails',
	array( has( $health, '<li>1 email failed in the last day. <a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=log&status=failed">Show the failed emails</a></li>' ), abs( $failed_since - ( time() - DAY_IN_SECONDS ) ) <= 5 ),
	array( true, true ) );
$health_quiet         = $data['health'];
$health_quiet['mail'] = array( 'latest' => array(), 'failed' => 0 );
$health_quiet         = capture( static function () use ( $health_quiet, $data ) { WPCPM_Administrators_Cards::render_health( $health_quiet, $data['locked'] ); } );
ck( 'with nothing sent yet the line says so, still linked to the Log, and no failures are counted',
	array( has( $health_quiet, '<li>No email has been sent yet. <a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=log">Open the email log</a></li>' ), has( $health_quiet, 'failed in the last day' ) ),
	array( true, false ) );
```

In `bin/stubs/overview-reads.php`, which bin/test-overview.php and bin/test-admin-menu.php share, the Overview reads the health data through the cards. Replace the `WPCPM_Mail` stand-in's first lines (347 to 353):

```php
/** The mail log and the invitation run: nothing sent, nothing queued. */
class WPCPM_Mail {
	public static function log() {
		return array();
	}

	public static function run() {
```

with:

```php
/** The invitation run: nothing queued. */
class WPCPM_Mail {
	public static function run() {
```

and add at the end of the file, after the `WPCPM_Mail` stand-in's closing brace:

```php

/** The email log, which a new site has, empty: nothing sent, nothing failed. */
class WPCPM_Mail_Log {
	const STATUS_FAILED = 'failed';

	public static function latest() {
		return null;
	}

	public static function failures_since( $since_utc ) {
		return 0;
	}

	public static function status_label( $status ) {
		return 'Not recorded';
	}
}
```

- [ ] **Step 2: Run them to verify they fail**

Run, one per command: `php bin/test-settings.php`, `php bin/test-administrators-dashboard.php`, `php bin/test-overview.php`.
Expected: each ends in a fatal error, `Call to undefined method WPCPM_Mail::log()` (test-overview FAILs its first checks on the way): the screens still read the old log, which the stand-ins no longer offer.

- [ ] **Step 3: The Settings > Mail section**

In `includes/class-wpcpm-settings-screen.php`, replace `render_mail_log()` with its docblock, from the line `	/**` above ` * The recent-mail log, the Mail tab's second section.` (line 1602) to the method's closing brace (line 1693) and the blank line after it, with:

```php
	/**
	 * The Mail tab's second section: where the record of sent email is.
	 *
	 * Until 1.122.18 this drew the plugin's last hundred emails from an option. The Emails tool's Log
	 * keeps every email the site sends for 30 days, so the section points there, with how many of
	 * them failed, when any did.
	 */
	private function render_mail_log() {
		$this->section_heading(
			__( 'Recent mail', 'wpcredits-program-manager' ),
			__( 'Every email the site sends is kept for 30 days in WPCredits Program > Tools > Emails, where it can be searched and filtered.', 'wpcredits-program-manager' ),
			'recent-mail'
		);

		// The Log tab, whichever way the tool is reached: through the registry, or, with the tool
		// filtered out of it, at the address it would have.
		$tool = WPCPM_Tools::get( 'emails' );
		$log  = add_query_arg( 'tab', 'log', $tool ? $tool->admin_url() : admin_url( 'admin.php?page=wpcpm-tool-emails' ) );

		$failed = WPCPM_Mail_Log::failures_since( gmdate( 'Y-m-d H:i:s', time() - WPCPM_Mail_Log::KEEP_DAYS * DAY_IN_SECONDS ) );

		if ( $failed ) {
			printf(
				'<p class="wpcpm-warning">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html(
					sprintf(
						/* translators: %s: number of failures. */
						_n( '%s email failed in the last 30 days.', '%s emails failed in the last 30 days.', $failed, 'wpcredits-program-manager' ),
						number_format_i18n( $failed )
					)
				),
				esc_url( add_query_arg( 'status', WPCPM_Mail_Log::STATUS_FAILED, $log ) ),
				esc_html__( 'Show the failed emails', 'wpcredits-program-manager' )
			);
		}

		printf( '<p><a class="button" href="%1$s">%2$s</a></p>', esc_url( $log ), esc_html__( 'Open the email log', 'wpcredits-program-manager' ) );
	}

```

The section keeps its heading, its anchor (`recent-mail`, which bin/test-settings.php holds against guide 31's built ids) and one intro sentence; the menu path is written the house way, "WPCredits Program > Tools > Emails".

- [ ] **Step 4: The health card**

In `includes/modules/class-wpcpm-administrators-cards.php`:

Replace the docblock of `health()` (lines 1176 to 1180):

```php
	/**
	 * The syncs, the private storage probe, the last mail and the invitation run.
	 *
	 * @return array
	 */
```

with:

```php
	/**
	 * The syncs, the private storage probe, the latest email and the last day's failures from the
	 * email log, and the invitation run.
	 *
	 * @return array
	 */
```

Replace the two reads at lines 1222 and 1223:

```php
		$probe = WPCPM_Private_Files::probe_result();
		$log   = WPCPM_Mail::log();
```

with:

```php
		$probe  = WPCPM_Private_Files::probe_result();
		$latest = WPCPM_Mail_Log::latest();
```

Replace the `'mail'` entry (line 1231 before the edit above):

```php
			'mail'    => isset( $log[0] ) && is_array( $log[0] ) ? $log[0] : array(),
```

with:

```php
			'mail'    => array(
				'latest' => is_array( $latest ) ? $latest : array(),
				'failed' => (int) WPCPM_Mail_Log::failures_since( gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ),
			),
```

Replace the mail line, where `render_health()` draws it (lines 1929 to 1936 before these edits):

```php
		printf(
			'<li>%s</li>',
			esc_html(
				empty( $mail )
					? __( 'No mail has been sent yet.', 'wpcredits-program-manager' )
					: sprintf( /* translators: 1: a mail context, 2: a masked address, 3: a date, 4: sent or failed. */ __( 'Last mail: %1$s to %2$s on %3$s, %4$s.', 'wpcredits-program-manager' ), isset( $mail['context'] ) ? $mail['context'] : '', isset( $mail['to'] ) ? $mail['to'] : '', self::when( isset( $mail['time'] ) ? (int) $mail['time'] : 0 ), ! empty( $mail['sent'] ) ? __( 'sent', 'wpcredits-program-manager' ) : __( 'failed', 'wpcredits-program-manager' ) )
			)
		);
```

with:

```php
		// The latest email by name, and the last day's failures, each linked to the Emails tool's Log.
		// The address is masked: this is a page of the site, and the full one is the Log's, in wp-admin.
		$latest = isset( $mail['latest'] ) ? (array) $mail['latest'] : array();
		$failed = isset( $mail['failed'] ) ? (int) $mail['failed'] : 0;
		$tool   = WPCPM_Tools::get( 'emails' );
		$log    = add_query_arg( 'tab', 'log', $tool ? $tool->admin_url() : admin_url( 'admin.php?page=wpcpm-tool-emails' ) );

		printf(
			'<li>%1$s <a href="%2$s">%3$s</a></li>',
			esc_html(
				empty( $latest )
					? __( 'No email has been sent yet.', 'wpcredits-program-manager' )
					: sprintf(
						/* translators: 1: an email's name, 2: a masked address, 3: a date, 4: the email's status. */
						__( 'Last email: %1$s to %2$s on %3$s. Status: %4$s.', 'wpcredits-program-manager' ),
						WPCPM_Mail_Catalog::label( isset( $latest['template'] ) ? $latest['template'] : '' ),
						WPCPM_Mail::mask_address( isset( $latest['to_email'] ) ? $latest['to_email'] : '' ),
						self::when( isset( $latest['sent_at'] ) ? (int) strtotime( $latest['sent_at'] . ' UTC' ) : 0 ),
						WPCPM_Mail_Log::status_label( isset( $latest['status'] ) ? $latest['status'] : '' )
					)
			),
			esc_url( $log ),
			esc_html__( 'Open the email log', 'wpcredits-program-manager' )
		);

		if ( $failed ) {
			printf(
				'<li>%1$s <a href="%2$s">%3$s</a></li>',
				esc_html(
					sprintf(
						/* translators: %s: number of failures. */
						_n( '%s email failed in the last day.', '%s emails failed in the last day.', $failed, 'wpcredits-program-manager' ),
						number_format_i18n( $failed )
					)
				),
				esc_url( add_query_arg( 'status', WPCPM_Mail_Log::STATUS_FAILED, $log ) ),
				esc_html__( 'Show the failed emails', 'wpcredits-program-manager' )
			);
		}
```

The status is a sentence of its own ("Status: Handed to the mail server."), not a word in the middle of one, so a translator can word the line whatever a status says. The Administrator Dashboard is a page of the site, so the address stays masked there.

- [ ] **Step 5: Run the touched suites to verify they pass**

Run, one per command: `php bin/test-settings.php`, `php bin/test-administrators-dashboard.php`, `php bin/test-overview.php`, `php bin/test-admin-menu.php`.
Expected: `ALL PASS`, `ALL PASS (176 checks)`, `ALL PASS (30 checks)`, `ALL PASS`. Then `grep -rn "WPCPM_Mail::\(log\|failures\)" includes` prints nothing.

- [ ] **Step 6: Run the whole battery**

Run, from the plugin root, every suite in its own process, then the references check:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 7: Commit**

```bash
git add includes/class-wpcpm-settings-screen.php includes/modules/class-wpcpm-administrators-cards.php bin/test-settings.php bin/test-administrators-dashboard.php bin/stubs/overview-reads.php
git commit -m "Settings > Mail and the health card read the email log"
```

---

### Task 4: The capture, and WPCPM_Mail without its own log

**Files:**
- Create: `includes/mail/class-wpcpm-mail-capture.php`
- Create: `bin/test-mail-capture.php`
- Modify: `includes/mail/class-wpcpm-mail-log.php` (`init()` starts the capture; the class docblock says so)
- Modify: `includes/class-wpcpm-mail.php` (remove `LOG_OPTION`, `LOG_MAX`, the two `wp_mail_*` hooks in `init()`, `mail_succeeded()`, `mail_failed()`, `record()`, `mask_recipients()`, `log()`, `clear_log()`, `failures()`; add `take_context()`; keep `mask_address()`; rewrite the class docblock's "A record" bullet, the `$context` docblock at lines 95 to 104 and the three `$context` parameter lines)
- Modify: `wpcredits-program-manager.php` and `uninstall.php` (require the capture after the store)
- Modify: `uninstall.php` (`WPCPM_Mail::clear_log()` goes; `WPCPM_Mail_Log::uninstall()`, which Task 2 added, deletes the old option too)
- Modify: `bin/test-mail.php` (every check of `WPCPM_Mail::log()`, `clear_log()` and `failures()` becomes a check of the context `wp_mail()` is handed), `bin/test-mail-log.php` (it loads the capture, which `init()` now starts), `bin/test-uninstall.php` (`WPCPM_Mail::LOG_OPTION` leaves `$names`)
- Suites to run: `bin/test-mail-capture.php`, `bin/test-mail-log.php`, `bin/test-mail.php`, `bin/test-uninstall.php`, `bin/test-roles.php`, then the whole battery

**Interfaces:**
- Consumes: `WPCPM_Mail_Catalog::get()`, `::module_of()`, `::wordpress_filters()`, `::TYPE_*`, `::OTHER` (Task 1); `WPCPM_Mail_Log::add()`, `::init()`, `::reset()`, `::STATUS_SENT`, `::STATUS_FAILED`, `::STATUS_UNCONFIRMED` (Task 2); `WPCPM_Roles::ROLE_STUDENT`, `ROLE_MENTOR`, `ROLE_INSTITUTION`, `ROLE_SPONSOR`, `ROLE_ADMIN`, `CAP_MANAGE` (existing).
- Produces:
  - `WPCPM_Mail::take_context(): string` the context the plugin set for the email being sent, cleared as it is read.
  - `WPCPM_Mail_Capture::init(): void` hooks `open()` on `wp_mail` at `PHP_INT_MIN`, `note()` on `wp_mail` at `PHP_INT_MAX`, `taken_over()` on `pre_wp_mail` at `PHP_INT_MAX` with 2 arguments, `succeeded()` on `wp_mail_succeeded` and `failed()` on `wp_mail_failed` at `PHP_INT_MIN`, `flush()` on `shutdown`, and each `wordpress_filters()` filter at `PHP_INT_MAX`. Called from `WPCPM_Mail_Log::init()`.
  - `open( array $atts ): array` and `note( array $atts ): array` (the `wp_mail` filter, first and last; both hand `$atts` back unchanged), `taken_over( mixed $answer, array $atts = array() ): mixed`, `succeeded( array $mail_data ): void`, `failed( WP_Error $error ): void`, `flush(): void`.
  - `recipients( string|string[] $to, string|string[] $headers ): string[]` and `type_of( ?WP_User $user, string $audience ): string`, public for the suites.
  - `reset(): void`, test-only: forgets the stack and the hint.

The capture keeps a stack, not one slot:

- at `wp_mail`, `PHP_INT_MIN` (`open()`): take the plugin's context (`WPCPM_Mail::take_context()`) and the hint a WordPress or Two Factor filter left, and push an entry named by them, so an email another plugin sends from its own `wp_mail` filter, after this, finds both gone;
- at `wp_mail`, `PHP_INT_MAX` (`note()`): the top entry takes `to`, `headers` and `subject` from the final atts;
- at `pre_wp_mail` (`PHP_INT_MAX`, an answer other than null), `wp_mail_succeeded` and `wp_mail_failed` (both `PHP_INT_MIN`): pop the top entry and write it, before another plugin's listener can send one of its own;
- on `shutdown` (`flush()`): write every entry still on the stack as Not confirmed, under its own name.

The rows are read from the entry only: the outcome hook's `$mail_data` carries headers WordPress has already parsed, without Cc, Bcc, From or Reply-To.

- [ ] **Step 1: Write the failing test**

The suite stands in for WordPress's hooks with their priorities kept, and its `send()` runs them the way `wp_mail()` 7.1 does, handing each outcome what `wp_mail()` hands it: `to` split on commas, and only the headers it did not read out itself. A `WPCPM_Mail` stands in for the real one, so that only the context passes between the two.

Create `bin/test-mail-capture.php` (the `ck()` helper and the two closing lines are the File Structure section's, written out):

```php
<?php
/**
 * The capture: every email the site sends becomes one row per recipient.
 *
 * WordPress's hooks are stood in for with their priorities kept, since the capture's place among
 * other plugins' callbacks is what it relies on, and `send()` runs them the way `wp_mail()` 7.1
 * does: the `wp_mail` filter, `pre_wp_mail`, then one outcome, handed what `wp_mail()` hands it
 * (`to` split on commas, and only the headers it did not read out itself). A `WPCPM_Mail` stands in
 * for the real one, so that only the context passes between the two.
 *
 * Run from the plugin root:  php bin/test-mail-capture.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['opts']  = array();
$GLOBALS['hooks'] = array();
$GLOBALS['users'] = array();

function __( $s, $d = null ) { return $s; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { $had = array_key_exists( $k, $GLOBALS['opts'] ); unset( $GLOBALS['opts'][ $k ] ); return $had; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['hooks'][ $h ][ $p ][] = array( $cb, $n ); return true; }
function add_action( $h, $cb, $p = 10, $n = 1 ) { return add_filter( $h, $cb, $p, $n ); }
/** A hook's callbacks in the order WordPress runs them: by priority, then as added. */
function hooked( $h ) {
	$by = isset( $GLOBALS['hooks'][ $h ] ) ? $GLOBALS['hooks'][ $h ] : array();
	ksort( $by );
	$out = array();
	foreach ( $by as $list ) {
		foreach ( $list as $f ) {
			$out[] = $f;
		}
	}
	return $out;
}
function apply_filters( $h, $v, ...$rest ) {
	foreach ( hooked( $h ) as $f ) {
		$v = call_user_func_array( $f[0], array_slice( array_merge( array( $v ), $rest ), 0, max( 1, $f[1] ) ) );
	}
	return $v;
}
function do_action( $h, ...$args ) {
	foreach ( hooked( $h ) as $f ) {
		call_user_func_array( $f[0], array_slice( $args, 0, max( 1, $f[1] ) ) );
	}
}
function sanitize_email( $e ) { $e = trim( (string) $e ); return filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : ''; }
function get_user_by( $field, $value ) { return $GLOBALS['users'][ strtolower( (string) $value ) ] ?? false; }
function user_can( $user, $cap ) { return in_array( $cap, $user->caps ?? array(), true ); }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function wp_check_invalid_utf8( $s, $strip = false ) { return (string) $s; }
function wp_next_scheduled( $h ) { return false; }
function wp_schedule_event( $t, $r, $h ) { return true; }

class WP_Error {
	private $m, $d;
	public function __construct( $c = '', $m = '', $d = null ) { $this->m = $m; $this->d = $d; }
	public function get_error_message() { return $this->m; }
	public function get_error_data() { return $this->d; }
}
class WP_User {
	public $ID, $display_name, $user_email, $roles, $caps;
	public function __construct( $id, $name, $email, $roles, $caps = array() ) { $this->ID = $id; $this->display_name = $name; $this->user_email = $email; $this->roles = $roles; $this->caps = $caps; }
	public function exists() { return $this->ID > 0; }
}
class WPCPM_Roles {
	const ROLE_STUDENT = 'wpcpm_student';
	const ROLE_MENTOR = 'wpcpm_mentor';
	const ROLE_INSTITUTION = 'wpcpm_institution';
	const ROLE_SPONSOR = 'wpcpm_sponsor';
	const ROLE_ADMIN = 'administrator';
	const CAP_MANAGE = 'wpcpm_manage_program';
}
// Stands in for the real class: only the context passes between the two.
class WPCPM_Mail {
	public static $ctx = '';
	public static function take_context() { $c = self::$ctx; self::$ctx = ''; return $c; }
}

require __DIR__ . '/stubs/sqlite-wpdb.php';
$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();
$GLOBALS['wpdb']->create_mail_log();

require __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require __DIR__ . '/../includes/mail/class-wpcpm-mail-log.php';
require __DIR__ . '/../includes/mail/class-wpcpm-mail-capture.php';

$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

$GLOBALS['users']['ada@example.test'] = new WP_User( 7, 'Ada Lin', 'ada@example.test', array( 'wpcpm_student' ) );
$GLOBALS['users']['ben@example.test'] = new WP_User( 8, 'Ben Ode', 'ben@example.test', array( 'wpcpm_mentor' ) );
$GLOBALS['users']['cy@example.test']  = new WP_User( 9, 'Cy Ray', 'cy@example.test', array( 'administrator' ), array( 'wpcpm_manage_program' ) );
$GLOBALS['users']['two@example.test'] = new WP_User( 10, 'Two Roles', 'two@example.test', array( 'wpcpm_student', 'wpcpm_mentor' ) );

// Booted as the plugin boots it: the store's init() starts the capture.
WPCPM_Mail_Log::init();

/**
 * Send through the hooks the way `wp_mail()` 7.1 runs them, and hand each outcome what it hands it.
 *
 * @param string|string[] $to      To.
 * @param string          $subject Subject.
 * @param string|string[] $headers Headers, as a string or an array of lines.
 * @param string          $outcome 'sent', 'failed' or 'taken' (another plugin's `pre_wp_mail`).
 * @return mixed What wp_mail() would return.
 */
function send( $to, $subject, $headers = '', $outcome = 'sent' ) {
	$atts = apply_filters( 'wp_mail', array( 'to' => $to, 'subject' => $subject, 'message' => 'BODY-NEVER-STORED', 'headers' => $headers, 'attachments' => array(), 'embeds' => array() ) );
	$pre  = apply_filters( 'pre_wp_mail', 'taken' === $outcome ? true : null, $atts );

	if ( null !== $pre ) {
		return $pre;
	}

	// What wp_mail() has made of the email by its outcome: `to` split on commas, and the headers it
	// kept once it read Cc, Bcc, From, Reply-To and Content-Type out of them, by name.
	$kept  = array();
	$lines = is_array( $atts['headers'] ) ? $atts['headers'] : explode( "\n", str_replace( "\r\n", "\n", (string) $atts['headers'] ) );

	foreach ( $lines as $line ) {
		if ( false === strpos( (string) $line, ':' ) ) {
			continue;
		}

		list( $name, $content ) = explode( ':', trim( (string) $line ), 2 );

		if ( ! in_array( strtolower( trim( $name ) ), array( 'cc', 'bcc', 'from', 'reply-to', 'content-type' ), true ) ) {
			$kept[ trim( $name ) ] = trim( $content );
		}
	}

	$mail_data = array( 'to' => is_array( $atts['to'] ) ? $atts['to'] : explode( ',', (string) $atts['to'] ), 'subject' => $atts['subject'], 'message' => $atts['message'], 'headers' => $kept, 'attachments' => array(), 'embeds' => array() );

	if ( 'failed' === $outcome ) {
		$mail_data['phpmailer_exception_code'] = 2;
		do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', 'Could not instantiate mail function.', $mail_data ) );

		return false;
	}

	do_action( 'wp_mail_succeeded', $mail_data );

	return true;
}
function rows() { return WPCPM_Mail_Log::find( array() )['rows']; }
function wipe() { $GLOBALS['wpdb']->drop_mail_log(); $GLOBALS['wpdb']->create_mail_log(); WPCPM_Mail_Log::reset(); WPCPM_Mail_Capture::reset(); }
/** Where the capture sits on a hook: its priorities there, in order. */
function priorities( $h ) {
	$found = array();
	foreach ( isset( $GLOBALS['hooks'][ $h ] ) ? $GLOBALS['hooks'][ $h ] : array() as $p => $list ) {
		foreach ( $list as $f ) {
			if ( is_array( $f[0] ) && 'WPCPM_Mail_Capture' === $f[0][0] ) {
				$found[] = array( $f[0][1], $p, $f[1] );
			}
		}
	}
	return $found;
}

ck( 'the capture opens an email first on wp_mail and notes it last there', priorities( 'wp_mail' ), array( array( 'open', PHP_INT_MIN, 1 ), array( 'note', PHP_INT_MAX, 1 ) ) );
ck( 'reads another plugin\'s take-over last, and each outcome first', array( priorities( 'pre_wp_mail' ), priorities( 'wp_mail_succeeded' ), priorities( 'wp_mail_failed' ) ), array( array( array( 'taken_over', PHP_INT_MAX, 2 ) ), array( array( 'succeeded', PHP_INT_MIN, 1 ) ), array( array( 'failed', PHP_INT_MIN, 1 ) ) ) );
ck( 'and writes what is left at shutdown', priorities( 'shutdown' ), array( array( 'flush', 10, 1 ) ) );
ck( 'and every naming filter is hooked', count( array_intersect( array_keys( WPCPM_Mail_Catalog::wordpress_filters() ), array_keys( $GLOBALS['hooks'] ) ) ), count( WPCPM_Mail_Catalog::wordpress_filters() ) );

// A plugin email.
WPCPM_Mail::$ctx = 'call-booked';
send( 'Ada Lin <ada@example.test>', 'Call booked with your mentor' );
$r = rows()[0];
ck( 'a plugin email is named by its context', array( $r['template'], $r['module'], $r['status'], $r['to_email'], (int) $r['user_id'], $r['to_name'], $r['recipient_type'], $r['subject'] ), array( 'call-booked', 'calls', 'sent', 'ada@example.test', 7, 'Ada Lin', 'student', 'Call booked with your mentor' ) );
ck( 'the context is taken, so it cannot name the next email', WPCPM_Mail::$ctx, '' );

// Review Focus 1: several recipients, Cc and Bcc in the one header string wp_mail() is often given.
wipe();
WPCPM_Mail::$ctx = 'offer-low-stock';
send( 'Ada Lin <ada@example.test>, ben@example.test, , not-an-address', 'Codes running low', "Cc: cy@example.test\r\nBcc: Dee <dee@example.test>" );
ck( 'one row per recipient of To, Cc and Bcc from a header string, none for a blank or a non-address', array_column( rows(), 'to_email' ), array( 'dee@example.test', 'cy@example.test', 'ben@example.test', 'ada@example.test' ) );
ck( 'an address without an account is Other on an email for several kinds', rows()[0]['recipient_type'], 'other' );

wipe();
WPCPM_Mail::$ctx = 'agreement-reminder';
send( array( 'cy@example.test' ), 'Agreements waiting', array( 'Cc: ben@example.test', 'Bcc: Dee <dee@example.test>, ada@example.test' ) );
ck( 'and from an array of two header lines', array_column( rows(), 'to_email' ), array( 'ada@example.test', 'dee@example.test', 'ben@example.test', 'cy@example.test' ) );
ck( 'recipients() reads a To array, once each without regard to case, and leaves Reply-To out', WPCPM_Mail_Capture::recipients( array( 'ada@example.test', 'ADA@example.test' ), array( 'Cc: ben@example.test', 'Reply-To: x@example.test' ) ), array( 'ada@example.test', 'ben@example.test' ) );

// The email as the last wp_mail filter left it: another plugin's filter changes the subject and adds a
// Bcc, which WordPress then reads out of the headers before the outcome.
wipe();
add_filter(
	'wp_mail',
	function ( $atts ) {
		if ( ! empty( $GLOBALS['rewrite'] ) ) {
			$atts['subject'] = '[Program] ' . $atts['subject'];
			$atts['headers'] = (array) $atts['headers'];
			$atts['headers'][] = 'Bcc: archive@example.test';
		}
		return $atts;
	}
);
$GLOBALS['rewrite'] = true;
WPCPM_Mail::$ctx    = 'report-drafted';
send( 'cy@example.test', 'A report is drafted' );
$GLOBALS['rewrite'] = false;
ck( 'the rows hold the subject and the recipients as the other filters left them, a Bcc WordPress has read out included', array( array_column( rows(), 'to_email' ), array_values( array_unique( array_column( rows(), 'subject' ) ) ) ), array( array( 'archive@example.test', 'cy@example.test' ), array( '[Program] A report is drafted' ) ) );

// Recipient types.
ck( 'type_of: the account decides when the email is for several kinds', array( WPCPM_Mail_Capture::type_of( $GLOBALS['users']['ben@example.test'], '' ), WPCPM_Mail_Capture::type_of( $GLOBALS['users']['cy@example.test'], '' ) ), array( 'mentor', 'administrator' ) );
ck( 'type_of: a person with two roles is shown as the one the email is for', array( WPCPM_Mail_Capture::type_of( $GLOBALS['users']['two@example.test'], 'mentor' ), WPCPM_Mail_Capture::type_of( $GLOBALS['users']['two@example.test'], '' ) ), array( 'mentor', 'student' ) );
ck( 'type_of: no account takes the audience, or Other', array( WPCPM_Mail_Capture::type_of( null, 'applicant' ), WPCPM_Mail_Capture::type_of( null, '' ) ), array( 'applicant', 'other' ) );

// WordPress's own and the Two Factor code are named by their filters.
wipe();
apply_filters( 'retrieve_password_notification_email', array( 'to' => 'ada@example.test' ) );
send( 'ada@example.test', '[Site] Password Reset' );
apply_filters( 'two_factor_token_email_subject', 'Your login confirmation code', 7 );
send( 'ada@example.test', 'Your login confirmation code' );
send( 'ada@example.test', 'Something else' );
ck( 'WordPress\'s reset, the Two Factor code, then an unnamed email', array_column( rows(), 'template' ), array( 'other', 'two-factor-code', 'wp-password-reset' ) );
ck( 'with their areas', array_column( rows(), 'module' ), array( 'other', 'two-factor', 'wordpress' ) );

// The plugin's context wins over WordPress's name: an invitation is WordPress's new-account email.
wipe();
apply_filters( 'wp_new_user_notification_email', array( 'to' => 'ben@example.test' ), null, 'Site' );
WPCPM_Mail::$ctx = 'invite-mentor';
send( 'ben@example.test', 'Your mentor account' );
ck( 'an invitation is the plugin\'s, not WordPress\'s new account', rows()[0]['template'], 'invite-mentor' );

// Outcomes.
wipe();
WPCPM_Mail::$ctx = 'report-drafted';
send( 'cy@example.test', 'A report is drafted', '', 'failed' );
ck( 'a failure is written with WordPress\'s message', array( rows()[0]['status'], rows()[0]['error'] ), array( 'failed', 'Could not instantiate mail function.' ) );

// Review Focus 2: nested and unfinished sends.
wipe();
WPCPM_Mail::$ctx = 'call-reminder';
send( 'ada@example.test', 'Reminder', '', 'taken' );
ck( 'an email another plugin took over is Not confirmed, under its own name', array( rows()[0]['status'], rows()[0]['template'] ), array( 'unconfirmed', 'call-reminder' ) );
send( 'ada@example.test', 'Next one' );
ck( 'and the next email is not named after it', rows()[0]['template'], 'other' );

// Another plugin sends an email of its own from its wp_mail_succeeded listener.
wipe();
add_action(
	'wp_mail_succeeded',
	function () {
		static $busy = false;
		if ( empty( $GLOBALS['notify_on_send'] ) || $busy ) {
			return;
		}
		$busy = true;
		send( 'alerts@example.test', 'An email went out' );
		$busy = false;
	}
);
$GLOBALS['notify_on_send'] = true;
WPCPM_Mail::$ctx           = 'call-booked';
send( 'ada@example.test', 'Call booked with your mentor' );
$GLOBALS['notify_on_send'] = false;
ck( 'an email sent from another plugin\'s outcome listener is written, and so is the one it followed, each under its own name', array_map( function ( $r ) { return $r['to_email'] . ' ' . $r['template'] . ' ' . $r['status']; }, rows() ), array( 'alerts@example.test other sent', 'ada@example.test call-booked sent' ) );

// Another plugin sends an email from its own wp_mail filter, while the plugin's is on its way.
wipe();
add_filter(
	'wp_mail',
	function ( $atts ) {
		static $busy = false;
		if ( ! empty( $GLOBALS['copy_on_filter'] ) && ! $busy ) {
			$busy = true;
			send( 'audit@example.test', 'A copy for the audit' );
			$busy = false;
		}
		return $atts;
	}
);
$GLOBALS['copy_on_filter'] = true;
WPCPM_Mail::$ctx           = 'session-moved';
send( 'ben@example.test', 'Group session moved' );
$GLOBALS['copy_on_filter'] = false;
ck( 'an email sent from another plugin\'s wp_mail filter does not take the plugin\'s context, which stays with the plugin\'s email', array_map( function ( $r ) { return $r['to_email'] . ' ' . $r['template'] . ' ' . $r['subject']; }, rows() ), array( 'ben@example.test session-moved Group session moved', 'audit@example.test other A copy for the audit' ) );

// An email whose wp_mail filter ran and whose outcome never came.
wipe();
apply_filters( 'password_change_email', array() );
apply_filters( 'wp_mail', array( 'to' => 'ada@example.test', 'subject' => 'Half sent', 'message' => '', 'headers' => array(), 'attachments' => array(), 'embeds' => array() ) );
send( 'ben@example.test', 'A later one' );
ck( 'an email whose outcome never came lends nothing to the next: its name is not carried', array( count( rows() ), rows()[0]['template'], rows()[0]['to_email'] ), array( 1, 'other', 'ben@example.test' ) );
do_action( 'shutdown' );
ck( 'and at shutdown it is written as Not confirmed, under its own name', array_map( function ( $r ) { return $r['to_email'] . ' ' . $r['template'] . ' ' . $r['status']; }, rows() ), array( 'ada@example.test wp-password-changed unconfirmed', 'ben@example.test other sent' ) );
do_action( 'shutdown' );
ck( 'once', count( rows() ), 2 );

// Tests from Settings > Mail.
wipe();
WPCPM_Mail::$ctx = 'test-mentor';
send( 'cy@example.test', 'Sample' );
ck( 'a sample invitation is marked Test', (int) rows()[0]['is_test'], 1 );

// No body is stored: every column of every row written in this run, read back from the table.
wipe();
WPCPM_Mail::$ctx = 'call-booked';
send( 'Ada Lin <ada@example.test>', 'Call booked', "Cc: ben@example.test" );
send( 'cy@example.test', 'Unnamed', '', 'failed' );
WPCPM_Mail::$ctx = 'call-reminder';
send( 'ada@example.test', 'Reminder', '', 'taken' );
$stored = $GLOBALS['wpdb']->mail_log_rows();
$held   = array();
foreach ( $stored as $row ) {
	foreach ( $row as $value ) {
		if ( false !== strpos( (string) $value, 'BODY-NEVER-STORED' ) ) {
			$held[] = $value;
		}
	}
}
ck( 'no body is stored: no column of any of the four rows holds it', array( count( $stored ), $held ), array( 4, array() ) );

// Review Focus 5: no table, and sending goes on.
$GLOBALS['wpdb']->drop_mail_log();
WPCPM_Mail_Log::reset();
WPCPM_Mail::$ctx = 'call-booked';
$threw = false;
try {
	$sent = send( 'ada@example.test', 'Still sent' );
	do_action( 'shutdown' );
} catch ( Throwable $e ) {
	$threw = true;
}
ck( 'with no table, an email still goes through every hook without an error', array( $threw, $sent ), array( false, true ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php bin/test-mail-capture.php`
Expected: a fatal error, `includes/mail/class-wpcpm-mail-capture.php` is missing.

- [ ] **Step 3: Write the capture**

Create `includes/mail/class-wpcpm-mail-capture.php`:

```php
<?php
/**
 * Every email the site sends, written to the mail log as it goes.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns one email into one log row per recipient.
 *
 * `wp_mail()` runs these hooks, in order: the `wp_mail` filter; `pre_wp_mail`, which another plugin
 * may answer to take the email over (the local copies do, to swallow their mail), and then neither
 * outcome follows; and one of `wp_mail_succeeded` and `wp_mail_failed`. This listens to all four:
 *
 * - **First on `wp_mail`** (`open()`), the email is named and put on a stack: by the plugin's
 *   context (`WPCPM_Mail::take_context()`), set just before the plugin calls `wp_mail()`; else by
 *   the WordPress or Two Factor filter it passed through just before
 *   (`WPCPM_Mail_Catalog::wordpress_filters()`); else as Other. Both are taken there, so neither
 *   can name a later email, and an email another plugin sends from its own `wp_mail` filter, after
 *   this, finds them gone.
 * - **Last on `wp_mail`** (`note()`), the email on top of the stack takes its recipients, headers
 *   and subject as the filters left them, which are what is sent.
 * - **Last on `pre_wp_mail`** (`taken_over()`), an answer other than null writes the email on top
 *   as Not confirmed.
 * - **First on each outcome** (`succeeded()`, `failed()`), the email on top is written with its
 *   status, before another plugin's listener can send one of its own. The rows are read from the
 *   email as noted, never from what the outcome hook carries: WordPress has parsed its headers by
 *   then, and Cc, Bcc, From and Reply-To are no longer among them.
 * - **On `shutdown`** (`flush()`), an email still on the stack, whose outcome never came (a
 *   replacement `wp_mail()` that fires no outcome, or a fatal error mid-send), is written as Not
 *   confirmed under its own name.
 *
 * A stack rather than one slot, because one email can be sent while another is on its way: from
 * another plugin's `wp_mail` filter, or from its outcome listener. Each finishes on top of the one
 * it interrupted, and each is written under its own name.
 */
final class WPCPM_Mail_Capture {

	/**
	 * The email a WordPress or Two Factor filter named last, not yet taken.
	 *
	 * @var string
	 */
	private static $hint = '';

	/**
	 * The emails noted at `wp_mail` and waiting for their outcome, the one sent last on top.
	 *
	 * @var array[]
	 */
	private static $stack = array();

	/**
	 * Hooks. Booted from the plugin's bootstrap, through `WPCPM_Mail_Log::init()`.
	 */
	public static function init() {
		add_filter( 'wp_mail', array( __CLASS__, 'open' ), PHP_INT_MIN );
		add_filter( 'wp_mail', array( __CLASS__, 'note' ), PHP_INT_MAX );
		add_filter( 'pre_wp_mail', array( __CLASS__, 'taken_over' ), PHP_INT_MAX, 2 );
		add_action( 'wp_mail_succeeded', array( __CLASS__, 'succeeded' ), PHP_INT_MIN );
		add_action( 'wp_mail_failed', array( __CLASS__, 'failed' ), PHP_INT_MIN );
		add_action( 'shutdown', array( __CLASS__, 'flush' ) );

		foreach ( WPCPM_Mail_Catalog::wordpress_filters() as $filter => $id ) {
			add_filter(
				$filter,
				static function ( $value ) use ( $id ) {
					self::$hint = $id;

					return $value;
				},
				PHP_INT_MAX
			);
		}
	}

	/**
	 * The `wp_mail` filter, first: name the email and put it on the stack. Changes nothing.
	 *
	 * @param array $atts to, subject, message, headers, attachments, embeds.
	 * @return array The same.
	 */
	public static function open( $atts ) {
		$context = class_exists( 'WPCPM_Mail' ) ? (string) WPCPM_Mail::take_context() : '';
		$id      = '' !== $context ? $context : ( '' !== self::$hint ? self::$hint : WPCPM_Mail_Catalog::OTHER );

		self::$hint    = '';
		self::$stack[] = array_merge( array( 'template' => $id ), self::email( $atts ) );

		return $atts;
	}

	/**
	 * The `wp_mail` filter, last: the email on top takes its recipients, headers and subject as the
	 * other filters left them. Changes nothing.
	 *
	 * @param array $atts to, subject, message, headers, attachments, embeds.
	 * @return array The same.
	 */
	public static function note( $atts ) {
		$top = count( self::$stack ) - 1;

		if ( $top >= 0 ) {
			self::$stack[ $top ] = array_merge( self::$stack[ $top ], self::email( $atts ) );
		}

		return $atts;
	}

	/**
	 * The parts of an email the log keeps, as the `wp_mail` filter holds them.
	 *
	 * @param mixed $atts The filter's value.
	 * @return array to, headers, subject.
	 */
	private static function email( $atts ) {
		$atts = is_array( $atts ) ? $atts : array();

		return array(
			'to'      => isset( $atts['to'] ) ? $atts['to'] : '',
			'headers' => isset( $atts['headers'] ) ? $atts['headers'] : array(),
			'subject' => isset( $atts['subject'] ) ? (string) $atts['subject'] : '',
		);
	}

	/**
	 * The `pre_wp_mail` filter, last: an answer other than null means another plugin took the email
	 * over, and WordPress will say nothing more about it.
	 *
	 * @param mixed $answer Null, or another plugin's answer.
	 * @param array $atts   The email.
	 * @return mixed The same answer.
	 */
	public static function taken_over( $answer, $atts = array() ) {
		if ( null !== $answer ) {
			self::write( WPCPM_Mail_Log::STATUS_UNCONFIRMED, '' );
		}

		return $answer;
	}

	/**
	 * The email went to the mail server.
	 *
	 * @param array $mail_data WordPress's account of the email, its headers already parsed; not read.
	 */
	public static function succeeded( $mail_data ) {
		self::write( WPCPM_Mail_Log::STATUS_SENT, '' );
	}

	/**
	 * The email failed.
	 *
	 * @param WP_Error $error WordPress's error; its message is kept.
	 */
	public static function failed( $error ) {
		self::write( WPCPM_Mail_Log::STATUS_FAILED, is_wp_error( $error ) ? (string) $error->get_error_message() : '' );
	}

	/**
	 * At the end of the request, every email whose outcome never came, as Not confirmed.
	 */
	public static function flush() {
		while ( self::$stack ) {
			self::write( WPCPM_Mail_Log::STATUS_UNCONFIRMED, '' );
		}
	}

	/**
	 * Write the email on top of the stack, one row per recipient, and take it off.
	 *
	 * @param string $status One of the log's statuses.
	 * @param string $error  WordPress's message for a failure.
	 */
	private static function write( $status, $error ) {
		$email = array_pop( self::$stack );

		if ( null === $email ) {
			return;
		}

		$entry = WPCPM_Mail_Catalog::get( $email['template'] );
		$id    = null === $entry ? WPCPM_Mail_Catalog::OTHER : $email['template'];

		foreach ( self::recipients( $email['to'], $email['headers'] ) as $address ) {
			$user = get_user_by( 'email', $address );
			$user = $user instanceof WP_User && $user->exists() ? $user : null;

			WPCPM_Mail_Log::add(
				array(
					'sent_at'        => gmdate( 'Y-m-d H:i:s' ),
					'to_email'       => $address,
					'user_id'        => $user ? (int) $user->ID : null,
					'to_name'        => $user ? (string) $user->display_name : '',
					'recipient_type' => self::type_of( $user, null === $entry ? '' : $entry['audience'] ),
					'module'         => WPCPM_Mail_Catalog::module_of( $id ),
					'template'       => $id,
					'subject'        => $email['subject'],
					'status'         => $status,
					'error'          => $error,
					'is_test'        => null !== $entry && $entry['test'],
				)
			);
		}
	}

	/**
	 * Every address an email goes to: To, then Cc, then Bcc; each once, without regard to case; a
	 * `Name <address>` reduced to its address; blanks and non-addresses dropped. Read the way
	 * `wp_mail()` reads them: `to` split on commas, and each header a `Name: value` line, from one
	 * string or an array of lines.
	 *
	 * @param string|string[] $to      What `wp_mail()` was given as `to`.
	 * @param string|string[] $headers Its headers, as one string or an array of lines.
	 * @return string[]
	 */
	public static function recipients( $to, $headers ) {
		$list  = is_array( $to ) ? $to : explode( ',', (string) $to );
		$lines = is_array( $headers ) ? $headers : explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );

		foreach ( $lines as $line ) {
			if ( is_string( $line ) && preg_match( '/^\s*(cc|bcc)\s*:(.*)$/i', $line, $m ) ) {
				$list = array_merge( $list, explode( ',', $m[2] ) );
			}
		}

		$out  = array();
		$seen = array();

		foreach ( $list as $item ) {
			$item = trim( (string) $item );

			if ( preg_match( '/<([^>]*)>/', $item, $m ) ) {
				$item = trim( $m[1] );
			}

			$address = sanitize_email( $item );
			$key     = strtolower( $address );

			if ( '' === $address || isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$out[]        = $address;
		}

		return $out;
	}

	/**
	 * Who a recipient is, as the Log shows it.
	 *
	 * @param WP_User|null $user     The account, or null.
	 * @param string       $audience The email's audience, or '' when the account decides.
	 * @return string One of the catalog's types.
	 */
	public static function type_of( $user, $audience ) {
		if ( ! $user instanceof WP_User ) {
			return '' !== (string) $audience ? (string) $audience : WPCPM_Mail_Catalog::TYPE_OTHER;
		}

		$roles = array(
			WPCPM_Mail_Catalog::TYPE_STUDENT     => in_array( WPCPM_Roles::ROLE_STUDENT, (array) $user->roles, true ),
			WPCPM_Mail_Catalog::TYPE_MENTOR      => in_array( WPCPM_Roles::ROLE_MENTOR, (array) $user->roles, true ),
			WPCPM_Mail_Catalog::TYPE_INSTITUTION => in_array( WPCPM_Roles::ROLE_INSTITUTION, (array) $user->roles, true ),
			WPCPM_Mail_Catalog::TYPE_SPONSOR     => in_array( WPCPM_Roles::ROLE_SPONSOR, (array) $user->roles, true ),
			WPCPM_Mail_Catalog::TYPE_ADMIN       => in_array( WPCPM_Roles::ROLE_ADMIN, (array) $user->roles, true ) || user_can( $user, WPCPM_Roles::CAP_MANAGE ),
		);

		if ( '' !== (string) $audience && ! empty( $roles[ $audience ] ) ) {
			return (string) $audience;
		}

		foreach ( $roles as $type => $has ) {
			if ( $has ) {
				return $type;
			}
		}

		return WPCPM_Mail_Catalog::TYPE_OTHER;
	}

	/**
	 * Forget the emails on the stack and the hint. Test-only.
	 */
	public static function reset() {
		self::$hint  = '';
		self::$stack = array();
	}
}
```

- [ ] **Step 4: Start the capture from the store's boot**

The capture boots with the rest of the log, from the plugin's bootstrap, so filtering the Emails tool out of `wpcpm_tools` cannot stop the recording.

In `includes/mail/class-wpcpm-mail-log.php`, replace the class docblock's second paragraph (lines 19 to 21):

```php
 * Booted from the plugin's bootstrap (`init()`), not from the Emails tool, so a site that filters
 * the tool out of `wpcpm_tools` still keeps its log: the table's creation and upgrade, the daily
 * cleanup and the privacy tools' hooks start here.
```

with:

```php
 * Booted from the plugin's bootstrap (`init()`), not from the Emails tool, so a site that filters
 * the tool out of `wpcpm_tools` still records its email: the capture (`WPCPM_Mail_Capture`), the
 * table's creation and upgrade, the daily cleanup and the privacy tools' hooks start here.
```

and the start of `init()` (lines 54 to 59):

```php
	/**
	 * Hooks: the table's creation and upgrade, the daily cleanup, and WordPress's privacy tools.
	 * Called from `wpcpm_bootstrap()`, beside `WPCPM_Mail::init()`.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 );
```

with:

```php
	/**
	 * Hooks: the capture, the table's creation and upgrade, the daily cleanup, and WordPress's
	 * privacy tools. Called from `wpcpm_bootstrap()`, beside `WPCPM_Mail::init()`.
	 */
	public static function init() {
		WPCPM_Mail_Capture::init();

		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 );
```

- [ ] **Step 5: Run the capture test to verify it passes**

Run: `php bin/test-mail-capture.php`
Expected: `ALL PASS (28 checks)`.

- [ ] **Step 6: The store's suite loads the capture**

`WPCPM_Mail_Log::init()` now starts the capture, so bin/test-mail-log.php loads it, and checks that it is started.

In `bin/test-mail-log.php`, replace:

```php
require __DIR__ . '/../includes/mail/class-wpcpm-mail-log.php';
```

with:

```php
require __DIR__ . '/../includes/mail/class-wpcpm-mail-log.php';
require __DIR__ . '/../includes/mail/class-wpcpm-mail-capture.php';
```

and replace the check of `init()`'s own hooks with the same check after one of the capture's:

```php
ck( 'init() hooks the upgrade early on init, the schedule, the cleanup job and both privacy tools', $own, array( array( 'init', 'maybe_upgrade', 5 ), array( 'init', 'schedule', 10 ), array( 'wpcpm_mail_log_purge', 'purge', 10 ), array( 'wp_privacy_personal_data_exporters', 'register_exporter', 10 ), array( 'wp_privacy_personal_data_erasers', 'register_eraser', 10 ) ) );
```

with:

```php
ck( 'init() starts the capture, which listens to wp_mail and both outcomes', array_values( array_intersect( array( 'wp_mail', 'pre_wp_mail', 'wp_mail_succeeded', 'wp_mail_failed', 'shutdown' ), array_column( $GLOBALS['hooked'], 0 ) ) ), array( 'wp_mail', 'pre_wp_mail', 'wp_mail_succeeded', 'wp_mail_failed', 'shutdown' ) );
ck( 'init() hooks the upgrade early on init, the schedule, the cleanup job and both privacy tools', $own, array( array( 'init', 'maybe_upgrade', 5 ), array( 'init', 'schedule', 10 ), array( 'wpcpm_mail_log_purge', 'purge', 10 ), array( 'wp_privacy_personal_data_exporters', 'register_exporter', 10 ), array( 'wp_privacy_personal_data_erasers', 'register_eraser', 10 ) ) );
```

Run: `php bin/test-mail-log.php`
Expected: `ALL PASS (71 checks)`.

- [ ] **Step 7: Bring bin/test-mail.php to the context, and see it fail**

The mail layer no longer keeps a record: it hands the capture the email's id. The suite's `wp_mail()` stand-in takes the context where the capture takes it, and each check that read the old log reads that context. Make each of these replacements in `bin/test-mail.php`; the line numbers are the file's before any of them.

The stand-in's docblock (lines 213 to 215):

```php
/**
 * `wp_mail()`, recording what it was handed and firing the outcome hook the log listens to.
 */
function wp_mail( $to, $subject, $body, $headers = array(), $attachments = array() ) {
```

with:

```php
/**
 * `wp_mail()`, recording what it was handed and the context the mail log's capture takes at the
 * `wp_mail` filter (`WPCPM_Mail::take_context()`), and firing the outcome hooks as core does.
 */
function wp_mail( $to, $subject, $body, $headers = array(), $attachments = array() ) {
```

What it records (line 231):

```php
	$GLOBALS['mail'][] = compact( 'to', 'subject', 'body', 'headers', 'attachments', 'present', 'contents' );
```

with:

```php
	// Taken where the capture takes it, so a context left standing would show on the next message.
	$context = WPCPM_Mail::take_context();

	$GLOBALS['mail'][] = compact( 'to', 'subject', 'body', 'headers', 'attachments', 'present', 'contents', 'context' );
```

Before `send_to()`'s checks (lines 333 to 337), nothing to clear any more:

```php
// An applicant is an address and nothing else. Before `send_to()`, mail to one went to
// `wp_mail()` directly and so past the filter, the log and the subject sanitising - which is
// why every claim made about `send()` above is made again here, against a bare address.
WPCPM_Mail::clear_log();
$GLOBALS['mail'] = array();
```

with:

```php
// An applicant is an address and nothing else. Before `send_to()`, mail to one went to
// `wp_mail()` directly and so past the filter, the log and the subject sanitising - which is
// why every claim made about `send()` above is made again here, against a bare address.
$GLOBALS['mail'] = array();
```

The check of what `send_to()` logged (lines 364 to 367):

```php
ck( 'the send is logged under its context',
    array( WPCPM_Mail::log()[0]['context'], WPCPM_Mail::log()[0]['to'] ),
    // The log keeps a masked address: enough to tell whose it was, never a contact list.
    array( 'institution-applied', 'a***@example.test' ) );
```

with:

```php
ck( 'the send carries its context to the mail log', array( $GLOBALS['mail'][0]['context'] ), array( 'institution-applied' ) );
```

The log's block (lines 528 to 551) becomes the context's, with a check that the mail layer listens to neither outcome any more:

```php
/* ---- the log ------------------------------------------------------------ */

echo "\n=== The log ===\n";

WPCPM_Mail::clear_log();
WPCPM_Mail::send( 30, 'call-booked', function () { return array( 'subject' => 'Booked', 'body' => 'x' ); } );

$log = WPCPM_Mail::log();
ck( 'a send is recorded with its outcome and context',
    array( count( $log ), $log[0]['context'], $log[0]['sent'], $log[0]['to'] ),
    array( 1, 'call-booked', true, 'l***@example.test' ) );

$GLOBALS['mail_fails'] = true;
WPCPM_Mail::send( 30, 'call-booked', function () { return array( 'subject' => 'Booked', 'body' => 'x' ); } );
$GLOBALS['mail_fails'] = false;

$log = WPCPM_Mail::log();
ck( 'a refusal is recorded as one', array( $log[0]['sent'] ), array( false ) );
ck( 'and counted', array( WPCPM_Mail::failures() ), array( 1 ) );

// Mail belonging to WordPress or another plugin must not be swept into this log.
WPCPM_Mail::clear_log();
wp_mail( 'someone@example.test', 'Comment awaiting moderation', 'body' );
ck( 'somebody else\'s mail is not recorded', array( count( WPCPM_Mail::log() ) ), array( 0 ) );
```

with:

```php
/* ---- the context the mail log names a send by ---------------------------- */

echo "\n=== The context the mail log names a send by ===\n";

// The record of what was sent is the mail log's (WPCPM_Mail_Log, WPCPM_Mail_Capture): the mail
// layer keeps none of its own, and hands the capture the email's id as `wp_mail()` runs.
ck( 'the mail layer listens to neither outcome of a send: the mail log\'s capture does', array( isset( $GLOBALS['filters']['wp_mail_succeeded'] ), isset( $GLOBALS['filters']['wp_mail_failed'] ), method_exists( 'WPCPM_Mail', 'log' ), defined( 'WPCPM_Mail::LOG_OPTION' ) ), array( false, false, false, false ) );

$GLOBALS['mail'] = array();
WPCPM_Mail::send( 30, 'call-booked', function () { return array( 'subject' => 'Booked', 'body' => 'x' ); } );

ck( 'a send hands wp_mail() its context, for the capture to take', array( count( $GLOBALS['mail'] ), end( $GLOBALS['mail'] )['context'] ), array( 1, 'call-booked' ) );
ck( 'and once taken it is gone, so it cannot name the next email', WPCPM_Mail::take_context(), '' );

$GLOBALS['mail_fails'] = true;
WPCPM_Mail::send( 30, 'call-booked', function () { return array( 'subject' => 'Booked', 'body' => 'x' ); } );
$GLOBALS['mail_fails'] = false;

ck( 'a send that fails carries its context too', end( $GLOBALS['mail'] )['context'], 'call-booked' );

// Mail belonging to WordPress or another plugin carries none: the capture names it otherwise.
wp_mail( 'someone@example.test', 'Comment awaiting moderation', 'body' );
ck( 'a message the plugin did not send carries no context', end( $GLOBALS['mail'] )['context'], '' );
```

The institution invitation's (lines 1123 to 1128):

```php
// The context is observed the way production observes it: WordPress calls `wp_mail()` itself
// straight after the filter, and the outcome hook reads what the filter left behind.
WPCPM_Mail::clear_log();
WPCPM_Mail::welcome_email( $core, $GLOBALS['users'][70], 'Site' );
wp_mail( 'contact@oscar.example', $institution['subject'], $institution['message'] );
ck( 'the invitation is logged as an institution\'s', array( WPCPM_Mail::log()[0]['context'] ), array( 'invite-institution' ) );
```

with:

```php
// The context is observed the way production observes it: WordPress calls `wp_mail()` itself
// straight after the filter, and the capture takes what the filter left behind.
WPCPM_Mail::welcome_email( $core, $GLOBALS['users'][70], 'Site' );
wp_mail( 'contact@oscar.example', $institution['subject'], $institution['message'] );
ck( 'the invitation carries an institution\'s context to the mail log', array( end( $GLOBALS['mail'] )['context'] ), array( 'invite-institution' ) );
```

The sponsor invitation's (lines 1214 to 1217):

```php
WPCPM_Mail::clear_log();
WPCPM_Mail::welcome_email( $sponsor_email, $sponsor, 'Site' );
wp_mail( 'rep@sponsor.example.test', $sponsor_mail['subject'], $sponsor_mail['message'] );
ck( 'the invitation is logged as a sponsor\'s', array( WPCPM_Mail::log()[0]['context'] ), array( 'invite-sponsor' ) );
```

with:

```php
WPCPM_Mail::welcome_email( $sponsor_email, $sponsor, 'Site' );
wp_mail( 'rep@sponsor.example.test', $sponsor_mail['subject'], $sponsor_mail['message'] );
ck( 'the invitation carries a sponsor\'s context to the mail log', array( end( $GLOBALS['mail'] )['context'] ), array( 'invite-sponsor' ) );
```

The two samples' (lines 1745 and 1746):

```php
ck( 'and each is logged under its own audience',
    array( WPCPM_Mail::log()[0]['context'] ), array( 'test-mentor' ) );
```

with:

```php
ck( 'and each carries its own audience\'s context to the mail log',
    array( $student_sample['context'], $mentor_sample['context'] ), array( 'test-student', 'test-mentor' ) );
```

The institution sample's (line 1762):

```php
    array( $institution_sample['subject'], WPCPM_Mail::log()[0]['context'] ),
```

with:

```php
    array( $institution_sample['subject'], $institution_sample['context'] ),
```

The sponsor sample's (line 1768):

```php
    array( $sponsor_sample['subject'], WPCPM_Mail::log()[0]['context'] ),
```

with:

```php
    array( $sponsor_sample['subject'], $sponsor_sample['context'] ),
```

And the masked address's heading (line 1804), since the address is the health card's now:

```php
echo "\n=== The log's masked address ===\n";
```

with:

```php
echo "\n=== The masked address the health card shows ===\n";
```

Run: `php bin/test-mail.php`
Expected: a fatal error, `Call to undefined method WPCPM_Mail::take_context()`.

- [ ] **Step 8: WPCPM_Mail gives up its log, and hands its context over**

In `includes/class-wpcpm-mail.php` (the line numbers are the file's before any of these edits):

The class docblock's "A record" bullet (lines 23 to 25):

```php
 * - **A record.** `wp_mail()` returns a boolean that every caller discarded, so "the student
 *   says they got nothing" was unanswerable. The record holds a masked address and the
 *   template's context, never the subject: see `record()`.
```

with:

```php
 * - **A record.** `wp_mail()` returns a boolean that every caller discarded, so "the student
 *   says they got nothing" was unanswerable. The context each message carries names it in the
 *   mail log (`WPCPM_Mail_Log`, WPCredits Program > Tools > Emails), which keeps who, what, when
 *   and status for 30 days and never the message text.
```

Delete `LOG_OPTION` and `LOG_MAX` with their comments (lines 31 to 36):

```php
	/** Option holding the recent-mail log. */
	const LOG_OPTION = 'wpcpm_mail_log';

	/** How many sends to remember. Enough to answer a question, not enough to bloat an option. */
	const LOG_MAX = 100;

```

The `$context` docblock at lines 95 to 104; the property at line 105 stays as it is:

```php
	/**
	 * What is currently being sent, if it is ours.
	 *
	 * Set immediately before handing a message to `wp_mail()` and read by the outcome hooks.
	 * Empty means the message belongs to WordPress or another plugin, and is none of this
	 * log's business - the log exists to answer "did *our* mail arrive", and a site's entire
	 * mail volume would bury that.
	 *
	 * @var string
	 */
	private static $context = '';
```

with:

```php
	/**
	 * What is currently being sent, if it is ours: the email's id in `WPCPM_Mail_Catalog`.
	 *
	 * Set immediately before handing a message to `wp_mail()` (and, for an invitation, in
	 * `welcome_email()`, which WordPress's own `wp_mail()` call follows), and taken by the mail log's
	 * capture at the `wp_mail` filter (`take_context()`), so it names that email and no later one.
	 *
	 * @var string
	 */
	private static $context = '';
```

In `init()`, the comment and the two outcome hooks (lines 120 to 125) go, `mail_succeeded()` and `mail_failed()` (lines 128 to 146) go, and `take_context()` follows `init()`; replace lines 117 to 146:

```php
		// Before wpcomsh's own `login_init` callback, which runs at -1. See the method.
		add_action( 'login_init', array( __CLASS__, 'keep_password_links_working' ), -2 );

		// The outcome, rather than the attempt. `wp_mail()` returns a boolean that says
		// whether the message was accepted for delivery; these two hooks carry the same
		// answer and also fire for the invitations, which WordPress sends itself and which
		// therefore never pass through `send()` at all.
		add_action( 'wp_mail_succeeded', array( __CLASS__, 'mail_succeeded' ) );
		add_action( 'wp_mail_failed', array( __CLASS__, 'mail_failed' ) );
	}

	/**
	 * Record a message that was accepted for delivery.
	 *
	 * @param array $mail_data `to`, `subject`, `message`, `headers`, `attachments`.
	 */
	public static function mail_succeeded( $mail_data ) {
		self::record( (array) $mail_data, true );
	}

	/**
	 * Record a message that was refused.
	 *
	 * @param WP_Error $error The failure, carrying the message in its error data.
	 */
	public static function mail_failed( $error ) {
		$data = $error instanceof WP_Error ? $error->get_error_data() : array();

		self::record( is_array( $data ) ? $data : array(), false );
	}
```

with:

```php
		// Before wpcomsh's own `login_init` callback, which runs at -1. See the method.
		add_action( 'login_init', array( __CLASS__, 'keep_password_links_working' ), -2 );
	}

	/**
	 * The id of the email being sent, if the plugin set one, cleared as it is read: the mail log's
	 * capture takes it at the `wp_mail` filter (`WPCPM_Mail_Capture::open()`).
	 *
	 * @return string
	 */
	public static function take_context() {
		$context       = self::$context;
		self::$context = '';

		return $context;
	}
```

The `$context` parameter of `send()` (line 157):

```php
	 * @param string      $context   Short label for the log, e.g. `call-booked`.
```

with:

```php
	 * @param string      $context   The email's id in `WPCPM_Mail_Catalog`, e.g. `call-booked`.
```

of `send_to()` (line 202):

```php
	 * @param string   $context Short label for the log, e.g. `institution-applied`.
```

with:

```php
	 * @param string   $context The email's id in `WPCPM_Mail_Catalog`, e.g. `institution-applied`.
```

and of `hand_off()` (line 247):

```php
	 * @param string       $context   Short label for the log.
```

with:

```php
	 * @param string       $context   The email's id in `WPCPM_Mail_Catalog`.
```

Last, the log's section: replace everything from its section comment (`/*`, ` * The log`, lines 376 to 379) down to, not including, the section comment of the invitation queue (`/*`, ` * The invitation queue`, line 511), that is `record()`, `mask_recipients()`, `mask_address()`, `log()`, `clear_log()` and `failures()` with their docblocks, with `mask_address()` alone under a section of its own, its docblock saying who reads it now:

```php
	/*
	 * An address, masked
	 * --------------------------------------------------------------------
	 */

	/**
	 * An address reduced to what identifies it without disclosing it: `a***@example.org`.
	 *
	 * The first character of the mailbox and the whole domain: what the Administrator Dashboard's
	 * health card shows of the latest email's recipient, since that card is a page of the site and
	 * the full address is the mail log's, in wp-admin. A `Name <address>` form is masked by its
	 * address and the name dropped; anything that is not an address at all becomes `***`.
	 *
	 * @param string $address One recipient, as handed to `wp_mail()`.
	 * @return string The masked form, or an empty string for a blank.
	 */
	public static function mask_address( $address ) {
		$address = trim( (string) $address );

		if ( preg_match( '/<([^>]*)>/', $address, $m ) ) {
			$address = trim( $m[1] );
		}

		if ( '' === $address ) {
			return '';
		}

		$at = strrpos( $address, '@' );

		if ( false === $at || 0 === $at ) {
			return '***';
		}

		return sanitize_text_field( mb_substr( $address, 0, 1 ) . '***' . substr( $address, $at ) );
	}

```

`hand_off()` keeps setting `self::$context` before `wp_mail()` and clearing it after; `welcome_email()` keeps setting it for the invitation WordPress sends straight after the filter. Nothing else changes there.

Run: `php bin/test-mail.php`
Expected: `ALL PASS`.

- [ ] **Step 9: Load the capture, and leave the old option to the store's uninstall**

In `wpcredits-program-manager.php`, replace line 48:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-log.php';
```

with:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-log.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-capture.php';
```

In `uninstall.php`, replace line 53:

```php
require_once plugin_dir_path( __FILE__ ) . 'includes/mail/class-wpcpm-mail-log.php';
```

with:

```php
require_once plugin_dir_path( __FILE__ ) . 'includes/mail/class-wpcpm-mail-log.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/mail/class-wpcpm-mail-capture.php';
```

In the same file, `WPCPM_Mail::clear_log()` is gone; `WPCPM_Mail_Log::uninstall()` deletes the old option with the rest. Replace lines 280 to 284:

```php
// The mail log, anyone still waiting for an invitation that is no longer coming, and the counts
// and times of the last bulk invite (the final fix wave of the deep check of 1.109.1).
WPCPM_Mail::clear_log();
// The email log's table, its two options and its daily cleanup (1.122.18).
WPCPM_Mail_Log::uninstall();
```

with:

```php
// The email log's table, its two options, the option the plugin kept its mail in before 1.122.18
// and the daily cleanup; anyone still waiting for an invitation that is no longer coming, and the
// counts and times of the last bulk invite (the final fix wave of the deep check of 1.109.1).
WPCPM_Mail_Log::uninstall();
```

In `bin/test-uninstall.php`, the constant is gone too; delete from `$names` (line 898):

```php
	'WPCPM_Mail::LOG_OPTION'                        => 'wpcpm_mail_log',
```

bin/test-uninstall.php keeps its check that the old option goes (`the mail log goes, with the addresses in it`) and the one Task 2 added for the table.

- [ ] **Step 10: Run the touched suites to verify they pass**

Run, one per command: `php bin/test-mail-capture.php`, `php bin/test-mail-log.php`, `php bin/test-mail.php`, `php bin/test-uninstall.php`, `php bin/test-roles.php`.
Expected: `ALL PASS (28 checks)`, `ALL PASS (71 checks)`, `ALL PASS`, `ALL PASS (35 checks)`, `ALL PASS`. Then `grep -rn "WPCPM_Mail::\(log\|failures\|clear_log\|LOG_OPTION\|LOG_MAX\)" includes uninstall.php wpcredits-program-manager.php` prints nothing.

- [ ] **Step 11: Run the whole battery**

Run, from the plugin root, every suite in its own process, then the references check:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 12: Commit**

```bash
git add includes/mail/class-wpcpm-mail-capture.php bin/test-mail-capture.php includes/mail/class-wpcpm-mail-log.php bin/test-mail-log.php includes/class-wpcpm-mail.php bin/test-mail.php wpcredits-program-manager.php uninstall.php bin/test-uninstall.php
git commit -m "Mail capture: every email the site sends is written to the log, one row per recipient; WPCPM_Mail hands its context over"
```

---

### Task 5: render_combo(): the one field for a long list, outside the switcher

**Files:**
- Modify: `includes/class-wpcpm-dashboards.php` (split `render_combo()` out of `render_switcher()`, lines 260 to 389)
- Create: `bin/test-combo-field.php`
- Suites to run: `bin/test-combo-field.php`, `bin/test-dashboard-switcher.php` (with its node harness, `bin/js/switcher-filter.js`, which it runs; both must pass with no change to either), then the whole battery

**Interfaces:**
- Consumes: nothing of this plan.
- Produces:
  - `WPCPM_Dashboards::render_combo( array $args ): void` with keys `id` (the select's ID; the field takes it with `-input`, its list with `-list`, the label with `-label`), `name` (the query argument the select posts), `options` (value to label, drawn in the order given), `current` (the value selected), `label` (the label, which it prints: `<label for="{id}" id="{id}-label">`), `find` (the field's placeholder), `none` (the sentence for no match) and `count` (the status line's sentence, `%s` for the number; empty means the switcher's "Names in the list: %s"). It registers and enqueues `WPCPM_Dashboards::SWITCHER_SCRIPT` and prints no form, no Show and no note.
  - `render_switcher()` keeps its contract and its bytes: it still sorts A to Z (`sort_switcher_options()`) and draws nothing for fewer than two entries, then draws the form, the block of fields, `render_combo()`, Show and the note.

assets/js/switcher.js (lines 106 to 113) wires a field by its ARIA: the field names its list (`aria-controls`), the list names the label (`aria-labelledby`), the label names the select (`for`). The label moves into `render_combo()` so that no form can draw the field with a label its list does not name.

- [ ] **Step 1: Write the failing test**

Create `bin/test-combo-field.php` (the `ck()` helper and the two closing lines are the File Structure section's, written out):

```php
<?php
/**
 * The one field for a long list, drawn on its own (`WPCPM_Dashboards::render_combo()`).
 *
 * The Viewing as switcher draws it inside its form (bin/test-dashboard-switcher.php holds that
 * markup, byte for byte, and runs assets/js/switcher.js on it); the Emails tool's Log draws it as its
 * Email filter, in a form of its own. What each block pins, and why:
 *
 * - **The label is the field's.** switcher.js finds its parts through their ARIA: the field names
 *   its list, the list names the label, the label names the select. A form that drew its own label
 *   without the `-label` id would leave the list naming nothing, and the script would leave the
 *   select where it is. So the label's id is the list's `aria-labelledby`, its `for` the select's
 *   id, and the field's `aria-controls` the list's id.
 * - **The options are drawn as given.** The caller sorts them: the switcher A to Z, the Log A to Z
 *   after its "Every email". The chosen one is selected, and only it.
 * - **The count's sentence is the caller's**, with the switcher's "Names in the list: %s" when none
 *   is given.
 * - **No form, no Show and no note**: those are the switcher's. The script is registered and
 *   enqueued by the field, so a page that draws one loads it.
 *
 * Run from the plugin root:  php bin/test-combo-field.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['opts']       = array();
$GLOBALS['registered'] = array();
$GLOBALS['enqueued']   = array();

function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
// Core's, as `selected()` writes it: a string comparison, so 7 and '7' are one value.
function selected( $a, $b, $echo = true ) {
	$out = ( (string) $a === (string) $b ) ? " selected='selected'" : '';
	if ( $echo ) { echo $out; }
	return $out;
}
function wp_register_script( $h, $src, $deps = array(), $ver = false, $footer = false ) {
	$GLOBALS['registered'][ $h ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'footer' => $footer );
}
function wp_script_is( $h, $list = 'enqueued' ) {
	return 'registered' === $list ? isset( $GLOBALS['registered'][ $h ] ) : in_array( $h, $GLOBALS['enqueued'], true );
}
function wp_enqueue_script( $h ) { $GLOBALS['enqueued'][] = $h; }
function remove_accents( $text, $locale = '' ) { return (string) $text; }

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';

$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/**
 * Draw one field and hand back its markup.
 *
 * @param array $more Arguments to add, or to put in place of the ones below.
 * @return string
 */
function combo( array $more = array() ) {
	ob_start();

	WPCPM_Dashboards::render_combo(
		array_merge(
			array(
				'id'      => 'wpcpm-test-email',
				'name'    => 'email',
				'options' => array( '' => 'Every email', 'call-booked' => 'Call booked', 'call-cancelled' => 'Call canceled', 'other' => 'Other email' ),
				'current' => 'call-booked',
				'label'   => 'Email',
				'find'    => 'Type to find an email',
				'none'    => 'No email has that name.',
				'count'   => 'Emails in the list: %s',
			),
			$more
		)
	);

	return (string) ob_get_clean();
}

ck( 'WPCPM_Dashboards draws the field on its own', method_exists( 'WPCPM_Dashboards', 'render_combo' ), true );

$html = combo();

preg_match( '#<label for="([^"]*)" id="([^"]*)">([^<]*)</label>#', $html, $label );
preg_match( '#<select name="([^"]*)" id="([^"]*)" autocomplete="off">#', $html, $select );
preg_match( '#<input type="text" id="([^"]*)"[^>]*aria-controls="([^"]*)"#', $html, $field );
preg_match( '#<ul id="([^"]*)" class="wpcpm-dashboard__switcher-list" role="listbox" aria-labelledby="([^"]*)" hidden></ul>#', $html, $list );

ck( 'the field draws its label, with the id its list is labeled by', array( $label[1] ?? null, $label[2] ?? null, $label[3] ?? null ), array( 'wpcpm-test-email', 'wpcpm-test-email-label', 'Email' ) );
ck( 'the three links switcher.js follows hold: the field names its list, the list names the label, the label names the select', array( ( $field[2] ?? 'a' ) === ( $list[1] ?? 'b' ), ( $list[2] ?? 'a' ) === ( $label[2] ?? 'b' ), ( $label[1] ?? 'a' ) === ( $select[2] ?? 'b' ) ), array( true, true, true ) );
ck( 'the select posts the name given', $select[1] ?? null, 'email' );

preg_match_all( '#<option value="([^"]*)"( selected=\'selected\')?>([^<]*)</option>#', $html, $options, PREG_SET_ORDER );
ck( 'the options are drawn in the order given, and the chosen one alone is selected', array_map( function ( $o ) { return $o[1] . ( '' !== $o[2] ? '*' : '' ) . '=' . $o[3]; }, $options ), array( '=Every email', 'call-booked*=Call booked', 'call-cancelled=Call canceled', 'other=Other email' ) );
ck( 'the field and its parts are drawn hidden, after the select', array( false !== strpos( $html, '<div class="wpcpm-dashboard__switcher-combo" hidden>' ), strpos( $html, '</select>' ) < strpos( $html, 'wpcpm-dashboard__switcher-combo' ) ), array( true, true ) );
ck( 'the status line carries the caller\'s count and no-match sentences', false !== strpos( $html, '<span class="wpcpm-dashboard__switcher-status" role="status" data-wpcpm-count="Emails in the list: %s" data-wpcpm-none="No email has that name."></span>' ), true );
ck( 'and with no count given, the switcher\'s', false !== strpos( combo( array( 'count' => '' ) ), 'data-wpcpm-count="Names in the list: %s"' ), true );
ck( 'no form, no Show and no note: those are the switcher\'s', array( strpos( $html, '<form' ), strpos( $html, '<button' ), strpos( $html, 'switcher-note' ) ), array( false, false, false ) );
ck( 'the field loads the script that works it, registered once in the footer', array( $GLOBALS['enqueued'][0] ?? null, $GLOBALS['registered'][ WPCPM_Dashboards::SWITCHER_SCRIPT ]['src'] ?? null, $GLOBALS['registered'][ WPCPM_Dashboards::SWITCHER_SCRIPT ]['footer'] ?? null ), array( WPCPM_Dashboards::SWITCHER_SCRIPT, WPCPM_PLUGIN_URL . 'assets/js/switcher.js', true ) );

// The switcher draws its fields through this one, so the two cannot drift apart.
$source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php' );
$body   = preg_match( '/function render_switcher\(.*?\n\t}\n/s', $source, $found ) ? $found[0] : '';
ck( 'render_switcher() draws its label, list and field through render_combo(), and prints none of them itself', array( false !== strpos( $body, 'self::render_combo(' ), strpos( $body, '<select' ), strpos( $body, '<label' ), strpos( $body, 'role="combobox"' ) ), array( true, false, false, false ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php bin/test-combo-field.php`
Expected: `FAIL WPCPM_Dashboards draws the field on its own`, then a fatal error, `Call to undefined method WPCPM_Dashboards::render_combo()`.

- [ ] **Step 3: Split render_combo() out of the switcher**

In `includes/class-wpcpm-dashboards.php` (the line numbers are the file's before any of these edits):

In `render_switcher()`'s docblock, replace the end of its first paragraph (lines 276 and 277):

```php
	 * the form, because the WordPress Credits theme lifts the form above the dashboard card by its
	 * opening tag. The script is enqueued here, so only a page that draws a switcher loads it.
```

with:

```php
	 * the form, because the WordPress Credits theme lifts the form above the dashboard card by its
	 * opening tag. The label, the list and the field are `render_combo()`'s, which enqueues the
	 * script, so only a page that draws a switcher, or another form's field, loads it.
```

Replace the start of its body (lines 313 to 332): the sort and the fewer-than-two return stay, the script's registration moves into `render_combo()`, and so do the four IDs:

```php
		$options = self::sort_switcher_options( (array) $args['options'] );

		if ( count( $options ) < 2 ) {
			return;
		}

		// Registered here the first time a switcher is drawn, in the footer, which a block's render
		// still reaches: the four dashboards share the handle and whichever draws first wins.
		if ( ! wp_script_is( self::SWITCHER_SCRIPT, 'registered' ) ) {
			wp_register_script( self::SWITCHER_SCRIPT, WPCPM_PLUGIN_URL . 'assets/js/switcher.js', array(), WPCPM_VERSION, true );
		}

		wp_enqueue_script( self::SWITCHER_SCRIPT );

		$id    = (string) $args['id'];
		$field = $id . '-input';
		$list  = $id . '-list';
		$named = $id . '-label';

		echo '<form class="wpcpm-dashboard__switcher" method="get">';
```

with:

```php
		$options = self::sort_switcher_options( (array) $args['options'] );

		if ( count( $options ) < 2 ) {
			return;
		}

		echo '<form class="wpcpm-dashboard__switcher" method="get">';
```

Replace the label, the select and the field (lines 344 to 383, from the block of fields' opening `echo` to Show's `printf()`):

```php
		echo '<div class="wpcpm-dashboard__switcher-fields">';
		printf( '<label for="%1$s" id="%2$s">%3$s</label> ', esc_attr( $id ), esc_attr( $named ), esc_html( $args['label'] ) );
		// Not put back as it was left: a browser that restores a form after Back would set the hidden
		// list on the name picked last while the field reads the page's own, and Show would open
		// somebody else's page.
		printf( '<select name="%1$s" id="%2$s" autocomplete="off">', esc_attr( $args['name'] ), esc_attr( $id ) );

		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $value, $args['current'], false ),
				esc_html( $label )
			);
		}

		echo '</select> ';

		// The field, hidden until the script shows it in the list's place. `spellcheck` is off because
		// a name is not a word a dictionary holds, and the browser's own suggestions are off because
		// the field brings its list.
		echo '<div class="wpcpm-dashboard__switcher-combo" hidden>';
		printf(
			'<input type="text" id="%1$s" class="wpcpm-dashboard__switcher-input" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="%2$s" autocomplete="off" spellcheck="false" placeholder="%3$s" />',
			esc_attr( $field ),
			esc_attr( $list ),
			esc_attr( $args['find'] )
		);
		printf( '<ul id="%1$s" class="wpcpm-dashboard__switcher-list" role="listbox" aria-labelledby="%2$s" hidden></ul>', esc_attr( $list ), esc_attr( $named ) );
		// Empty until the list opens: a status region is read out when its text changes. Both
		// sentences are the markup's, so that they are translated with the rest of the page; the
		// count's needs no plural, since the script fills in the number.
		printf(
			'<span class="wpcpm-dashboard__switcher-status" role="status" data-wpcpm-count="%1$s" data-wpcpm-none="%2$s"></span>',
			/* translators: %s: how many names the Viewing as list shows, as a number. */
			esc_attr( __( 'Names in the list: %s', 'wpcredits-program-manager' ) ),
			esc_attr( $args['none'] )
		);
		echo '</div> ';
		printf( '<button type="submit" class="wpcpm-button">%s</button>', esc_html__( 'Show', 'wpcredits-program-manager' ) );
```

with:

```php
		echo '<div class="wpcpm-dashboard__switcher-fields">';
		self::render_combo( array_merge( $args, array( 'options' => $options ) ) );
		printf( '<button type="submit" class="wpcpm-button">%s</button>', esc_html__( 'Show', 'wpcredits-program-manager' ) );
```

And add `render_combo()` before `sort_switcher_options()`'s docblock (line 389); replace:

```php
	/**
	 * A switcher's entries in A to Z order, as a reader of the list expects names
```

with:

```php
	/**
	 * The one field for a long list (the owner's rule of 9 October 2026): its label, the select a form
	 * sends, and beside it, hidden, the combobox assets/js/switcher.js shows in the select's place, a
	 * text field that drops down, takes typing and narrows its list. The Viewing as switcher draws it
	 * between its block of fields' opening and Show (`render_switcher()`); the Emails tool's Log draws
	 * it as its Email filter. The options are drawn in the order given, so the caller sorts them, and
	 * a caller decides whether one entry is worth a field.
	 *
	 * The script finds the parts through their ARIA: the field names its list (`aria-controls`), the
	 * list names the label (`aria-labelledby`, the label's `{id}-label`), and the label names the
	 * select (`for`). The label is drawn here for that reason, so no form can draw the field with a
	 * label the list does not name. The script is registered and enqueued here, so every page that
	 * draws the field loads it.
	 *
	 * @param array $args {
	 *     The field.
	 *
	 *     @type string     $id      The select's ID; the field takes it with `-input` added, its list of
	 *                              names with `-list` and the label with `-label`.
	 *     @type string     $name    The query argument the select posts.
	 *     @type array      $options Value to label, in the order to draw them.
	 *     @type string|int $current The value chosen, selected in the select.
	 *     @type string     $label   The label.
	 *     @type string     $find    The field's placeholder, which it shows while it is empty.
	 *     @type string     $none    What the field's list says when nothing matches what was typed.
	 *     @type string     $count   The status line's sentence for how many entries the list shows,
	 *                              with %s for the number; the Viewing as sentence when empty.
	 * }
	 */
	public static function render_combo( array $args ) {
		$args = array_merge(
			array(
				'id'      => '',
				'name'    => '',
				'options' => array(),
				'current' => '',
				'label'   => '',
				'find'    => '',
				'none'    => '',
				'count'   => '',
			),
			$args
		);

		// Registered here the first time a field is drawn, in the footer, which a block's render still
		// reaches: the four dashboards and the Emails screen share the handle and whichever draws first
		// wins.
		if ( ! wp_script_is( self::SWITCHER_SCRIPT, 'registered' ) ) {
			wp_register_script( self::SWITCHER_SCRIPT, WPCPM_PLUGIN_URL . 'assets/js/switcher.js', array(), WPCPM_VERSION, true );
		}

		wp_enqueue_script( self::SWITCHER_SCRIPT );

		$id    = (string) $args['id'];
		$field = $id . '-input';
		$list  = $id . '-list';
		$named = $id . '-label';
		$count = (string) $args['count'];

		if ( '' === $count ) {
			/* translators: %s: how many names the Viewing as list shows, as a number. */
			$count = __( 'Names in the list: %s', 'wpcredits-program-manager' );
		}

		printf( '<label for="%1$s" id="%2$s">%3$s</label> ', esc_attr( $id ), esc_attr( $named ), esc_html( $args['label'] ) );
		// Not put back as it was left: a browser that restores a form after Back would set the hidden
		// list on the name picked last while the field reads the page's own, and Show would open
		// somebody else's page.
		printf( '<select name="%1$s" id="%2$s" autocomplete="off">', esc_attr( $args['name'] ), esc_attr( $id ) );

		foreach ( (array) $args['options'] as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $value, $args['current'], false ),
				esc_html( $label )
			);
		}

		echo '</select> ';

		// The field, hidden until the script shows it in the list's place. `spellcheck` is off because
		// a name is not a word a dictionary holds, and the browser's own suggestions are off because
		// the field brings its list.
		echo '<div class="wpcpm-dashboard__switcher-combo" hidden>';
		printf(
			'<input type="text" id="%1$s" class="wpcpm-dashboard__switcher-input" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="%2$s" autocomplete="off" spellcheck="false" placeholder="%3$s" />',
			esc_attr( $field ),
			esc_attr( $list ),
			esc_attr( $args['find'] )
		);
		printf( '<ul id="%1$s" class="wpcpm-dashboard__switcher-list" role="listbox" aria-labelledby="%2$s" hidden></ul>', esc_attr( $list ), esc_attr( $named ) );
		// Empty until the list opens: a status region is read out when its text changes. Both
		// sentences are the markup's, so that they are translated with the rest of the page; the
		// count's needs no plural, since the script fills in the number.
		printf(
			'<span class="wpcpm-dashboard__switcher-status" role="status" data-wpcpm-count="%1$s" data-wpcpm-none="%2$s"></span>',
			esc_attr( $count ),
			esc_attr( $args['none'] )
		);
		echo '</div> ';
	}

	/**
	 * A switcher's entries in A to Z order, as a reader of the list expects names
```

Every line `render_combo()` prints is a line `render_switcher()` printed, in the same order, with the same comments; only the label's and the count's arguments are new, and the switcher passes its own `label` and no `count`. The translators comment stays directly above the one `__()` it describes.

- [ ] **Step 4: Run the tests to verify they pass, the switcher's byte for byte**

Run, one per command: `php bin/test-combo-field.php`, `php bin/test-dashboard-switcher.php`.
Expected: `ALL PASS (11 checks)`, then `ALL PASS` with the node checks run (`=== The field, run by node ===` lists its scenarios, all `ok`), with neither suite changed: bin/test-dashboard-switcher.php pins the switcher's markup as exact strings, so it passing unchanged proves the switcher prints what it printed.

- [ ] **Step 5: Run the whole battery**

Run, from the plugin root, every suite in its own process, then the references check:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 6: Commit**

```bash
git add includes/class-wpcpm-dashboards.php bin/test-combo-field.php
git commit -m "render_combo(): the Viewing as field, label included, for any form to draw"
```

---

### Task 6: The Emails tool, its Log screen and its registration

**Files:**
- Create: `includes/tools/class-wpcpm-emails.php`
- Create: `includes/mail/class-wpcpm-mail-log-table.php`
- Create: `assets/css/emails.css`
- Create: `bin/test-emails-screen.php`
- Modify: `includes/class-wpcpm-tools.php` (`new WPCPM_Emails()` after `new WPCPM_Header_Notices()` in `all()`, line 31)
- Modify: `wpcredits-program-manager.php` and `uninstall.php` (require `includes/tools/class-wpcpm-emails.php` after `includes/tools/class-wpcpm-header-notices.php`; the table file is required only inside `WPCPM_Emails::render_admin_page()`)
- Modify: `bin/test-admin-menu.php` (it requires the tool files by hand, lines 144 to 152; its menu list, lines 239 to 253; its cards, lines 311 to 316; its status lines on a new site and with everything ready, lines 368 to 380 and 404 to 414)
- Modify: `bin/test-roles.php` (its on-disk scan, which reads `includes/mail/` since Task 1, leaves out the Log's list table, which the tool requires on its screen; the tool is registered)
- Modify: `bin/stubs/overview-reads.php` (the email log's stand-in answers `exists()` and `count_since()`, which the tool's status line reads on the Tools screen and the Overview)
- Modify: `docs/sections/30-admin-wpadmin.md` (the "words the screens use" table's Tools row, line 26, names **Emails**: bin/test-admin-menu.php lines 561 to 585 hold that row to the registry's tools)
- Suites to run: `bin/test-emails-screen.php`, `bin/test-admin-menu.php`, `bin/test-roles.php`, `bin/test-overview.php`, `bin/test-uninstall.php`, `bin/test-settings.php`, `bin/test-institution-panel.php` (the stylesheet checks), `bin/test-dashboard-switcher.php`, `bin/test-combo-field.php`, then the whole battery

**Interfaces:**
- Consumes: `WPCPM_Mail_Catalog::modules()`, `::types()`, `::emails()`, `::label()`, `::OTHER`, `::MODULE_OTHER` (Task 1); `WPCPM_Mail_Log::exists()`, `::find()`, `::count_since()`, `::maybe_purge()`, `::status_label()`, `::reset()`, `::add()`, `::PER_PAGE`, `::KEEP_DAYS`, `::OPT_PURGED`, `::STATUS_*`, `::FILTER_TEST` (Task 2); `WPCPM_Dashboards::render_combo()`, `::compare_names()`, `::SWITCHER_SCRIPT` (Task 5); `WPCPM_Request::key( string $name, string $fallback = '' ): string` (a `sanitize_key()` of the query argument, `''` when absent) and `WPCPM_Request::text( string $name, string $fallback = '' ): string` (a `sanitize_text_field()`, `''` when absent), from `includes/class-wpcpm-request.php`; `WPCPM_Tool` (`includes/tools/class-wpcpm-tool.php`: `page_slug()` is `'wpcpm-tool-' . id()`, `admin_url()` is `admin_url( 'admin.php?page=' . page_slug() )`).
- Produces:
  - `WPCPM_Emails extends WPCPM_Tool`: `id()` `'emails'`, `label()` "Emails", page `wpcpm-tool-emails`, `is_ready()` true, `TAB_LOG` `'log'`.
  - `status_line(): string` "N emails in the last 30 days." (`_n()`, "1 email in the last 30 days."), or "The email log is not ready yet." when the table is missing.
  - `boot(): void` hooks `enqueue_assets` on `admin_enqueue_scripts` and nothing else; `enqueue_assets( string $hook ): void` loads `assets/css/emails.css` on the screen alone.
  - `range( string $when, string $from = '', string $to = '', int $now = 0 ): array{from:string,to:string}` the UTC bounds of a When choice in the site's time zone, public for the suites.
  - `filters_from_request(): array` the Log's filters as `WPCPM_Mail_Log::find()` reads them (`s`, `module`, `type`, `status`, `email`, `when`, `from`, `to`).
  - `email_options(): array<string,string>` "Every email" (`''`) first, then every catalog email and "Other email", A to Z by `WPCPM_Dashboards::compare_names()`.
  - `render_admin_page(): void`.
  - `WPCPM_Mail_Log_Table extends WP_List_Table`: `__construct( array $filters )`, `get_columns()` (`when`, `to`, `type`, `module` headed "Area", `email`, `subject`, `status`), `prepare_items()`, `no_items()`.

The screen's filters are a GET form of their own, closed before `$table->display()`: its search box is the form's own `s` field, not `search_box()` (which core draws only while there is a search or a row), so the box never disappears and no nonce or referer of the table's joins the filters' address; the form carries `page` and `tab=log`. The table's page links carry the current query arguments, the filters among them.

- [ ] **Step 1: Write the failing test**

It loads the list table stub (`bin/stubs/class-wp-list-table.php`), the SQLite store, the request readers and the dashboards' helpers, and stubs what the screen calls. Before the screen is drawn it stamps the cleanup (`wpcpm_mail_log_purged`) as just run, so `maybe_purge()` leaves the rows it seeded alone whatever day the suite runs on.

Create `bin/test-emails-screen.php` (the `ck()` helper and the two closing lines are the File Structure section's, written out):

```php
<?php
/**
 * The Emails tool and its Log screen.
 *
 * The screen is drawn through the real tool, the real list table over bin/stubs/class-wp-list-table.php,
 * the real store over an in-memory SQLite database (bin/stubs/sqlite-wpdb.php), and the real Email
 * field (`WPCPM_Dashboards::render_combo()`). What each block pins, and why:
 *
 * - **The tool draws and counts, and boots nothing else**: the recording, the upgrade, the cleanup
 *   and the privacy hooks are the store's, booted from the plugin's bootstrap, so filtering the tool
 *   out of `wpcpm_tools` cannot stop the log.
 * - **When is counted in the site's time zone** (Review Focus 4).
 * - **The filters are a GET form of their own**, closed before the table, with the search as its
 *   own field: no nonce or referer joins a filter's address, and the box is there with no row.
 *   Area, Recipient type, Status and When are plain selects; Email is the one field that drops down,
 *   takes typing and narrows, and its label is the one its list is labeled by, which is how
 *   assets/js/switcher.js finds it (Review Focus 4 of the plan: the field wires up in wp-admin).
 * - **No screen word says module**: the column and the filter say Area.
 * - **No table**: the screen says the log is not ready, and the status line says so (Review Focus 5).
 *
 * Run from the plugin root:  php bin/test-emails-screen.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['opts']       = array();
$GLOBALS['hooked']     = array();
$GLOBALS['styles']     = array();
$GLOBALS['registered'] = array();
$GLOBALS['enqueued']   = array();
$GLOBALS['can']        = true;
$GLOBALS['users']      = array();

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_email( $e ) { $e = trim( (string) $e ); return filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : ''; }
function wp_unslash( $v ) { return $v; }
function wp_check_invalid_utf8( $s, $strip = false ) { return (string) $s; }
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_query_arg( $k, $v, $u ) { return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . $k . '=' . $v; }
// Core's, as `selected()` writes it: a string comparison, so 7 and '7' are one value.
function selected( $a, $b, $echo = true ) {
	$out = ( (string) $a === (string) $b ) ? " selected='selected'" : '';
	if ( $echo ) { echo $out; }
	return $out;
}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { $had = array_key_exists( $k, $GLOBALS['opts'] ); unset( $GLOBALS['opts'][ $k ] ); return $had; }
function add_action( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['hooked'][] = array( $h, $cb, $p ); return true; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { return add_action( $h, $cb, $p, $n ); }
function apply_filters( $h, $v, ...$rest ) { return $v; }
function wp_timezone() { $tz = (string) get_option( 'timezone_string', '' ); return new DateTimeZone( '' !== $tz ? $tz : 'UTC' ); }
function wp_date( $format, $timestamp = null ) { return ( new DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( wp_timezone() )->format( $format ); }
function current_user_can( $cap ) { return (bool) $GLOBALS['can']; }
function wp_die( $m = '', $t = '', $a = array() ) { throw new Exception( 'wp_die: ' . $m ); }
function get_user_by( $field, $value ) { return $GLOBALS['users'][ (int) $value ] ?? false; }
function get_edit_user_link( $id ) { return 'https://example.test/wp-admin/user-edit.php?user_id=' . (int) $id; }
function wp_enqueue_style( $h, $src = '', $deps = array(), $ver = false ) { $GLOBALS['styles'][ $h ] = $src; }
function wp_register_script( $h, $src, $deps = array(), $ver = false, $footer = false ) { $GLOBALS['registered'][ $h ] = $src; }
function wp_script_is( $h, $list = 'enqueued' ) { return 'registered' === $list ? isset( $GLOBALS['registered'][ $h ] ) : in_array( $h, $GLOBALS['enqueued'], true ); }
function wp_enqueue_script( $h ) { $GLOBALS['enqueued'][] = $h; }
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) { echo '<input type="hidden" id="' . $name . '" name="' . $name . '" value="nonce:' . $action . '" />'; }
function remove_accents( $text, $locale = '' ) { return (string) $text; }

class WP_User {
	public $ID, $display_name;
	public function __construct( $id, $name ) { $this->ID = $id; $this->display_name = $name; }
}
class WPCPM_Roles {
	const CAP_MANAGE = 'wpcpm_manage_program';
}

require __DIR__ . '/stubs/class-wp-list-table.php';
require __DIR__ . '/stubs/sqlite-wpdb.php';
$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();
$GLOBALS['wpdb']->create_mail_log();

require WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
require WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-log.php';
require WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-emails.php';

$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/** The screen as an Administrator opening it with these query arguments sees it. */
function screen( array $query ) {
	$_GET                   = $query;
	$_REQUEST               = $query;
	$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?' . http_build_query( $query );

	ob_start();
	( new WPCPM_Emails() )->render_admin_page();

	return (string) ob_get_clean();
}

/** The words some markup says, as a person reads them. */
function words_of( $html ) {
	return trim( preg_replace( '/\s+/', ' ', html_entity_decode( preg_replace( '/<[^>]*>/', ' ', (string) $html ), ENT_QUOTES ) ) );
}

// The tool.
$tool = new WPCPM_Emails();
ck( 'the tool is Emails at wpcpm-tool-emails, always ready', array( $tool->id(), $tool->label(), $tool->page_slug(), $tool->is_ready() ), array( 'emails', 'Emails', 'wpcpm-tool-emails', true ) );

$tool->boot();
ck( 'it boots its stylesheet\'s hook and nothing else: the log boots from the plugin\'s bootstrap', array_map( function ( $h ) { return $h[0]; }, $GLOBALS['hooked'] ), array( 'admin_enqueue_scripts' ) );
$tool->enqueue_assets( 'index.php' );
$tool->enqueue_assets( 'wpcredits-program_page_wpcpm-tool-emails' );
ck( 'its stylesheet loads on its screen alone', $GLOBALS['styles'], array( 'wpcpm-emails' => WPCPM_PLUGIN_URL . 'assets/css/emails.css' ) );

// The status line the Tools screen and the Overview print.
ck( 'the status line counts the last 30 days\' emails', $tool->status_line(), '0 emails in the last 30 days.' );
WPCPM_Mail_Log::add( array( 'sent_at' => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ), 'to_email' => 'ben@example.test', 'module' => 'calls', 'template' => 'call-reminder', 'subject' => 'Reminder', 'status' => 'sent' ) );
WPCPM_Mail_Log::add( array( 'sent_at' => gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ), 'to_email' => 'ben@example.test', 'module' => 'calls', 'template' => 'call-reminder', 'subject' => 'Old reminder', 'status' => 'sent' ) );
ck( 'one, in the singular, and none older than 30 days', $tool->status_line(), '1 email in the last 30 days.' );

$GLOBALS['wpdb']->drop_mail_log();
WPCPM_Mail_Log::reset();
ck( 'and with no table it says the log is not ready yet', $tool->status_line(), 'The email log is not ready yet.' );
$GLOBALS['wpdb']->create_mail_log();
WPCPM_Mail_Log::reset();

// Review Focus 4: the When choices in the site's time zone.
$GLOBALS['opts']['timezone_string'] = 'Europe/Warsaw';
$now = strtotime( '2026-10-10 08:00:00 UTC' ); // 10:00 in Warsaw.
ck( 'Today runs from local midnight, in UTC', WPCPM_Emails::range( 'today', '', '', $now ), array( 'from' => '2026-10-09 22:00:00', 'to' => '' ) );
ck( 'the last 7 days count back from now', WPCPM_Emails::range( '7', '', '', $now ), array( 'from' => '2026-10-03 08:00:00', 'to' => '' ) );
ck( 'the last 30 days count back from now', WPCPM_Emails::range( '30', '', '', $now ), array( 'from' => '2026-09-10 08:00:00', 'to' => '' ) );
ck( 'from and to dates take whole local days', WPCPM_Emails::range( 'range', '2026-10-01', '2026-10-02', $now ), array( 'from' => '2026-09-30 22:00:00', 'to' => '2026-10-02 22:00:00' ) );
ck( 'a date that is not one is ignored', WPCPM_Emails::range( 'range', 'yesterday', '2026-13-40', $now ), array( 'from' => '', 'to' => '' ) );
ck( 'and no choice is the 30 days the log keeps', WPCPM_Emails::range( '', '', '', $now ), array( 'from' => '', 'to' => '' ) );
// A row at 23:30 UTC on 9 October is 01:30 on 10 October in Warsaw: Today holds it.
WPCPM_Mail_Log::add( array( 'sent_at' => '2026-10-09 23:30:00', 'to_email' => 'ada@example.test', 'user_id' => 7, 'to_name' => 'Ada Lin', 'recipient_type' => 'student', 'module' => 'calls', 'template' => 'call-booked', 'subject' => 'Call booked', 'status' => 'sent' ) );
ck( 'and Today holds a row sent at 23:30 UTC the day before', WPCPM_Mail_Log::find( WPCPM_Emails::range( 'today', '', '', $now ) )['total'], 1 );

// The filters, from the request.
$_GET = array( 's' => ' ada ', 'module' => 'calls', 'type' => 'student', 'status' => 'test', 'email' => 'call-booked', 'when' => 'range', 'from' => '2026-10-01', 'to' => '2026-10-02' );
ck( 'filters_from_request() reads every filter as find() reads it', WPCPM_Emails::filters_from_request(), array( 'search' => 'ada', 'module' => 'calls', 'recipient_type' => 'student', 'status' => 'test', 'template' => 'call-booked', 'from' => '2026-09-30 22:00:00', 'to' => '2026-10-02 22:00:00' ) );

// The screen. The cleanup ran a moment ago, so the screen does not run it over the rows above.
$GLOBALS['opts'][ WPCPM_Mail_Log::OPT_PURGED ] = time();
$GLOBALS['users'][7]                           = new WP_User( 7, 'Ada Lin' );

$html  = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'log', 'when' => 'range', 'from' => '2026-10-10', 'to' => '2026-10-10' ) );
$form  = preg_match( '#<form method="get" class="wpcpm-emails__filters">(.*?)</form>#s', $html, $found ) ? $found[1] : '';
$words = words_of( $html );

ck( 'the row shows its time in the site\'s zone, its address, its person linked, its area and its email', array( false !== strpos( $html, '10 Oct 2026, 01:30' ), false !== strpos( $html, 'ada@example.test' ), false !== strpos( $html, '<a href="https://example.test/wp-admin/user-edit.php?user_id=7">Ada Lin</a>' ), false !== strpos( $html, 'Mentor calls' ), false !== strpos( $html, 'Call booked' ) ), array( true, true, true, true, true ) );
ck( 'the count is shown', false !== strpos( $html, '1 email matches.' ), true );
ck( 'the screen says, under the table, what Handed to the mail server means', strpos( $html, 'means the site passed the email on, not that it reached the inbox' ) > strpos( $html, '</table>' ), true );
ck( 'the filters are a GET form of their own, closed before the table, naming the screen and the Log tab', array( '' !== $form, strpos( $html, '</form>' ) < strpos( $html, '<table' ), false !== strpos( $form, '<input type="hidden" name="page" value="wpcpm-tool-emails" />' ), false !== strpos( $form, '<input type="hidden" name="tab" value="log" />' ) ), array( true, true, true, true ) );
ck( 'with its own search box, and no nonce or referer of the table\'s', array( 1 === preg_match( '#<input type="search" id="wpcpm-emails-search" name="s" value="" />#', $form ), false !== strpos( $form, '_wpnonce' ), false !== strpos( $form, '_wp_http_referer' ), false !== strpos( $html, 'search-submit' ) ), array( true, false, false, false ) );
ck( 'Area, Recipient type, Status and When are plain selects', array( 1 === preg_match( '#<label for="wpcpm-emails-module">Area</label> <select id="wpcpm-emails-module" name="module"><option value="">Every area</option>#', $form ), 1 === preg_match( '#<select id="wpcpm-emails-type" name="type"><option value="">All</option>#', $form ), 1 === preg_match( '#<select id="wpcpm-emails-status" name="status"><option value="">All</option>#', $form ), 1 === preg_match( '#<select id="wpcpm-emails-when" name="when"><option value="">Any time</option>#', $form ) ), array( true, true, true, true ) );
ck( 'and the dates chosen are put back', array( false !== strpos( $form, '<option value="range" selected=\'selected\'>From and to dates</option>' ), false !== strpos( $form, 'name="from" value="2026-10-10"' ) ), array( true, true ) );

// Review Focus 4: the Email field wires up, which assets/js/switcher.js does through its ARIA.
preg_match( '#<label for="([^"]*)" id="([^"]*)">Email</label> <select name="email" id="([^"]*)" autocomplete="off">#', $form, $email_label );
preg_match( '#aria-controls="([^"]*)"#', $form, $controls );
preg_match( '#<ul id="([^"]*)" class="wpcpm-dashboard__switcher-list" role="listbox" aria-labelledby="([^"]*)" hidden></ul>#', $form, $listbox );
ck( 'Email is the one field that drops down and takes typing, inside the form', array( isset( $email_label[3] ), false !== strpos( $form, '<div class="wpcpm-dashboard__switcher-combo" hidden>' ), false !== strpos( $form, 'role="combobox"' ) ), array( true, true, true ) );
ck( 'its label\'s id is the listbox\'s aria-labelledby, its label names the select, and the field names the listbox', array( ( $email_label[2] ?? 'a' ) === ( $listbox[2] ?? 'b' ), ( $email_label[1] ?? 'a' ) === ( $email_label[3] ?? 'b' ), ( $controls[1] ?? 'a' ) === ( $listbox[1] ?? 'b' ) ), array( true, true, true ) );
ck( 'and the page loads the script that works it', in_array( WPCPM_Dashboards::SWITCHER_SCRIPT, $GLOBALS['enqueued'], true ), true );

preg_match( '#<select name="email"[^>]*>(.*?)</select>#s', $form, $email_select );
preg_match_all( '#<option value="([^"]*)"[^>]*>([^<]*)</option>#', $email_select[1] ?? '', $email_options );
$labels = array_slice( array_map( 'html_entity_decode', $email_options[2] ), 1 );
$sorted = $labels;
usort( $sorted, array( 'WPCPM_Dashboards', 'compare_names' ) );
ck( 'its list opens on Every email, then every email the catalog names and Other email, A to Z', array( $email_options[2][0] ?? '', $email_options[1][0] ?? 'x', count( $labels ), $labels === $sorted, in_array( 'Other email', $labels, true ) ), array( 'Every email', '', count( WPCPM_Mail_Catalog::emails() ) + 1, true, true ) );
ck( 'and its count sentence is the Log\'s own', false !== strpos( $form, 'data-wpcpm-count="Emails in the list: %s" data-wpcpm-none="No email has that name."' ), true );

ck( 'no word on the screen says module: the column and the filter say Area', array( preg_match_all( '/\bmodules?\b/i', $words ), 1 === preg_match( "#<th scope=\"col\" id='module' class='manage-column column-module'\s*>Area</th>#", $html ) ), array( 0, true ) );
ck( 'the body never reaches the screen, and no em or en dash', array( false === strpos( $html, 'BODY' ), 0 === preg_match( '/\x{2013}|\x{2014}/u', $html ) ), array( true, true ) );

// A filter that matches nothing, and the box still there to search again.
$none = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'log', 'module' => 'wordpress', 's' => 'nobody' ) );
ck( 'a filter that matches nothing says so, and the search box is still there, holding the search', array( false !== strpos( $none, '0 emails match.' ), false !== strpos( $none, 'No email matches.' ), false !== strpos( $none, 'name="s" value="nobody"' ), false !== strpos( $none, '<option value="wordpress" selected=\'selected\'>WordPress</option>' ) ), array( true, true, true, true ) );

// The page links carry the filters, which are the address's.
for ( $i = 0; $i < 51; $i++ ) {
	WPCPM_Mail_Log::add( array( 'sent_at' => gmdate( 'Y-m-d H:i:s', time() - $i * 60 ), 'to_email' => 'many@example.test', 'module' => 'sponsors', 'template' => 'offer-low-stock', 'subject' => 'Codes running low', 'status' => 'sent' ) );
}
$paged = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'log', 'module' => 'sponsors' ) );
ck( '50 a page, and the next page\'s link keeps the Log tab and the filter', array( substr_count( $paged, 'many@example.test' ), 1 === preg_match( "#class='next-page button' href='[^']*\?page=wpcpm-tool-emails&tab=log&module=sponsors&paged=2'#", $paged ) ), array( 50, true ) );

// Review Focus 5: no table.
$GLOBALS['wpdb']->drop_mail_log();
WPCPM_Mail_Log::reset();
$missing = screen( array( 'page' => 'wpcpm-tool-emails' ) );
ck( 'with no table the screen says the log is not ready yet, and draws no table', array( false !== strpos( $missing, 'The email log is not ready yet' ), strpos( $missing, '<table' ) ), array( true, false ) );

// Only Administrators.
$GLOBALS['can'] = false;
$died           = '';
try {
	screen( array( 'page' => 'wpcpm-tool-emails' ) );
} catch ( Exception $e ) {
	$died = $e->getMessage();
}
ck( 'anyone else is refused', false !== strpos( $died, 'permission' ), true );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php bin/test-emails-screen.php`
Expected: a fatal error, `includes/tools/class-wpcpm-emails.php` is missing.

- [ ] **Step 3: Write the list table**

Create `includes/mail/class-wpcpm-mail-log-table.php`:

```php
<?php
/**
 * The Emails screen's Log as a WordPress list table.
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
 * Newest first, 50 a page, no sorting: the question it answers is "did this email go out", which
 * the newest-first order and the filters answer. Required by the Emails tool on its own screen
 * only, since it extends core's list table, which wp-admin loads after the plugins.
 */
class WPCPM_Mail_Log_Table extends WP_List_Table {

	/**
	 * The filters, as `WPCPM_Mail_Log::find()` reads them.
	 *
	 * @var array
	 */
	private $filters;

	/**
	 * The table, for one set of filters.
	 *
	 * @param array $filters The Log's filters (`WPCPM_Emails::filters_from_request()`).
	 */
	public function __construct( array $filters ) {
		$this->filters = $filters;

		parent::__construct(
			array(
				'singular' => 'wpcpm-email',
				'plural'   => 'wpcpm-emails',
				'ajax'     => false,
			)
		);
	}

	/**
	 * The columns. The Area column's key is the table's `module`, the code's name for an area.
	 *
	 * @return array<string,string>
	 */
	public function get_columns() {
		return array(
			'when'    => __( 'When', 'wpcredits-program-manager' ),
			'to'      => __( 'To', 'wpcredits-program-manager' ),
			'type'    => __( 'Recipient type', 'wpcredits-program-manager' ),
			'module'  => __( 'Area', 'wpcredits-program-manager' ),
			'email'   => __( 'Email', 'wpcredits-program-manager' ),
			'subject' => __( 'Subject', 'wpcredits-program-manager' ),
			'status'  => __( 'Status', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * One page of rows for the filters, and the pagination's numbers.
	 */
	public function prepare_items() {
		$found = WPCPM_Mail_Log::find( $this->filters, $this->get_pagenum(), WPCPM_Mail_Log::PER_PAGE );

		$this->items           = $found['rows'];
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		$this->set_pagination_args(
			array(
				'total_items' => $found['total'],
				'per_page'    => WPCPM_Mail_Log::PER_PAGE,
			)
		);
	}

	/**
	 * What the table says when the filters match nothing.
	 */
	public function no_items() {
		esc_html_e( 'No email matches.', 'wpcredits-program-manager' );
	}

	/**
	 * When it was sent, stored in UTC, shown in the site's time zone.
	 *
	 * @param array $row The row.
	 */
	protected function column_when( $row ) {
		echo esc_html( wp_date( 'j M Y, H:i', (int) strtotime( $row['sent_at'] . ' UTC' ) ) );
	}

	/**
	 * The address, and the person's name at the time of sending, linked to the account while it
	 * exists.
	 *
	 * @param array $row The row.
	 */
	protected function column_to( $row ) {
		echo esc_html( $row['to_email'] );

		if ( '' !== (string) $row['to_name'] ) {
			$link = ! empty( $row['user_id'] ) && get_user_by( 'id', (int) $row['user_id'] ) ? get_edit_user_link( (int) $row['user_id'] ) : '';

			echo '<br />';
			echo $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $row['to_name'] ) . '</a>' : esc_html( $row['to_name'] );
		}
	}

	/**
	 * The recipient type, as the account said at the time of sending.
	 *
	 * @param array $row The row.
	 */
	protected function column_type( $row ) {
		$types = WPCPM_Mail_Catalog::types();

		echo esc_html( isset( $types[ $row['recipient_type'] ] ) ? $types[ $row['recipient_type'] ] : __( 'Not recorded', 'wpcredits-program-manager' ) );
	}

	/**
	 * The area that sent it.
	 *
	 * @param array $row The row.
	 */
	protected function column_module( $row ) {
		$areas = WPCPM_Mail_Catalog::modules();

		echo esc_html( isset( $areas[ $row['module'] ] ) ? $areas[ $row['module'] ] : $areas[ WPCPM_Mail_Catalog::MODULE_OTHER ] );
	}

	/**
	 * Which email it was.
	 *
	 * @param array $row The row.
	 */
	protected function column_email( $row ) {
		echo esc_html( WPCPM_Mail_Catalog::label( $row['template'] ) );
	}

	/**
	 * Its subject, as sent; none was kept for the entries moved in from the old option.
	 *
	 * @param array $row The row.
	 */
	protected function column_subject( $row ) {
		echo '' === (string) $row['subject'] ? '<span class="description">' . esc_html__( 'Not recorded', 'wpcredits-program-manager' ) . '</span>' : esc_html( $row['subject'] );
	}

	/**
	 * Its status, the Test mark, and WordPress's message for a failure.
	 *
	 * @param array $row The row.
	 */
	protected function column_status( $row ) {
		echo esc_html( WPCPM_Mail_Log::status_label( $row['status'] ) );

		if ( ! empty( $row['is_test'] ) ) {
			echo ' <span class="wpcpm-emails__test">' . esc_html__( 'Test', 'wpcredits-program-manager' ) . '</span>';
		}

		if ( '' !== (string) $row['error'] ) {
			echo '<br /><span class="description">' . esc_html( $row['error'] ) . '</span>';
		}
	}
}
```

- [ ] **Step 4: Write the tool**

Create `includes/tools/class-wpcpm-emails.php`:

```php
<?php
/**
 * Tool - Emails.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The site's email: in this version the Log, every email the site sent in the last 30 days.
 *
 * The tool draws the screen and its status line and nothing more. The recording, the table's
 * creation and upgrade, the daily cleanup and the privacy tools' hooks boot from the plugin's
 * bootstrap (`WPCPM_Mail_Log::init()`), so a site that filters this tool out of `wpcpm_tools` still
 * records its email.
 *
 * Every link to the Log names its tab (`tab=log`), which the screen's filter form carries too, so
 * the links keep working once the screen has other tabs beside it.
 */
class WPCPM_Emails extends WPCPM_Tool {

	/** The Log's tab. */
	const TAB_LOG = 'log';

	/**
	 * Tool ID.
	 *
	 * @return string
	 */
	public function id() {
		return 'emails';
	}

	/**
	 * Tool name.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Emails', 'wpcredits-program-manager' );
	}

	/**
	 * One-line description for the Tools screen.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Every email the site sent in the last 30 days: who it went to, which email, and whether WordPress handed it to the mail server.', 'wpcredits-program-manager' );
	}

	/**
	 * Always ready: the log needs no connection, and says itself when its table is missing.
	 *
	 * @return bool
	 */
	public function is_ready() {
		return true;
	}

	/**
	 * How many emails the log holds for the last 30 days, or that it is not ready yet.
	 *
	 * @return string
	 */
	public function status_line() {
		if ( ! WPCPM_Mail_Log::exists() ) {
			return __( 'The email log is not ready yet.', 'wpcredits-program-manager' );
		}

		$count = WPCPM_Mail_Log::count_since( gmdate( 'Y-m-d H:i:s', time() - WPCPM_Mail_Log::KEEP_DAYS * DAY_IN_SECONDS ) );

		return sprintf(
			/* translators: %s: number of emails. */
			_n( '%s email in the last 30 days.', '%s emails in the last 30 days.', $count, 'wpcredits-program-manager' ),
			number_format_i18n( $count )
		);
	}

	/**
	 * Hooks: the screen's stylesheet. Nothing else: the log boots on its own.
	 */
	public function boot() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * The screen's stylesheet, on this screen only. The Email field's script is enqueued by the
	 * field itself (`WPCPM_Dashboards::render_combo()`).
	 *
	 * @param string $hook The screen's hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, $this->page_slug() ) ) {
			return;
		}

		wp_enqueue_style( 'wpcpm-emails', WPCPM_PLUGIN_URL . 'assets/css/emails.css', array(), WPCPM_VERSION );
	}

	/**
	 * The UTC bounds of a When choice, counted in the site's time zone.
	 *
	 * @param string $when '' (the 30 days kept), 'today', '7', '30' or 'range'.
	 * @param string $from For 'range': a Y-m-d local date, from its midnight.
	 * @param string $to   For 'range': a Y-m-d local date, to the next midnight.
	 * @param int    $now  The time; 0 for now.
	 * @return array{from:string,to:string}
	 */
	public static function range( $when, $from = '', $to = '', $now = 0 ) {
		$now  = $now ? (int) $now : time();
		$zone = wp_timezone();
		$utc  = new DateTimeZone( 'UTC' );
		$out  = array(
			'from' => '',
			'to'   => '',
		);

		switch ( (string) $when ) {
			case 'today':
				$day         = ( new DateTimeImmutable( '@' . $now ) )->setTimezone( $zone )->setTime( 0, 0 );
				$out['from'] = $day->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
				break;
			case '7':
			case '30':
				$out['from'] = gmdate( 'Y-m-d H:i:s', $now - (int) $when * DAY_IN_SECONDS );
				break;
			case 'range':
				foreach ( array(
					'from' => $from,
					'to'   => $to,
				) as $key => $date ) {
					$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $date, $zone );

					if ( false === $parsed || $parsed->format( 'Y-m-d' ) !== (string) $date ) {
						continue;
					}

					$parsed      = 'to' === $key ? $parsed->modify( '+1 day' ) : $parsed;
					$out[ $key ] = $parsed->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
				}
				break;
		}

		return $out;
	}

	/**
	 * The Log's filters from the request, as `WPCPM_Mail_Log::find()` reads them.
	 *
	 * @return array
	 */
	public static function filters_from_request() {
		$range = self::range( WPCPM_Request::key( 'when' ), WPCPM_Request::text( 'from' ), WPCPM_Request::text( 'to' ) );

		return array(
			'search'         => WPCPM_Request::text( 's' ),
			'module'         => WPCPM_Request::key( 'module' ),
			'recipient_type' => WPCPM_Request::key( 'type' ),
			'status'         => WPCPM_Request::key( 'status' ),
			'template'       => WPCPM_Request::key( 'email' ),
			'from'           => $range['from'],
			'to'             => $range['to'],
		);
	}

	/**
	 * The Email filter's choices: "Every email" first, then every email the catalog names and "Other
	 * email", A to Z as the Viewing as lists are (`WPCPM_Dashboards::compare_names()`).
	 *
	 * @return array<string,string> Value to label.
	 */
	public static function email_options() {
		$labels = array();

		foreach ( WPCPM_Mail_Catalog::emails() as $id => $entry ) {
			$labels[ $id ] = $entry['label'];
		}

		$labels[ WPCPM_Mail_Catalog::OTHER ] = WPCPM_Mail_Catalog::label( WPCPM_Mail_Catalog::OTHER );

		uasort( $labels, array( 'WPCPM_Dashboards', 'compare_names' ) );

		return array( '' => __( 'Every email', 'wpcredits-program-manager' ) ) + $labels;
	}

	/**
	 * The screen: the filters, a form of their own, then the count, the table and what its main
	 * status promises.
	 *
	 * The filter form is a GET form closed before the table, and its search box is its own field
	 * (`s`) rather than the table's `search_box()`, which core draws only while there is a search or
	 * a row: so the box never disappears, and no nonce or referer of the table's joins the filters'
	 * address. The table's page links carry the filters, which are the address's.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		echo '<div class="wrap wpcpm-wrap wpcpm-emails">';
		printf( '<h1>%s</h1>', esc_html( $this->label() ) );

		if ( ! WPCPM_Mail_Log::exists() ) {
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html__( 'The email log is not ready yet. It is created the next time a page of the site loads; if this stays, tell whoever looks after the site.', 'wpcredits-program-manager' ) );
			echo '</div>';

			return;
		}

		WPCPM_Mail_Log::maybe_purge();

		require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-log-table.php';

		$filters = self::filters_from_request();
		$table   = new WPCPM_Mail_Log_Table( $filters );
		$table->prepare_items();

		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: %d: days kept. */
					__( 'Every email the site sent in the last %d days, newest first.', 'wpcredits-program-manager' ),
					WPCPM_Mail_Log::KEEP_DAYS
				)
			)
		);

		echo '<form method="get" class="wpcpm-emails__filters">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $this->page_slug() ) );
		printf( '<input type="hidden" name="tab" value="%s" />', esc_attr( self::TAB_LOG ) );

		printf(
			'<div class="wpcpm-emails__filter wpcpm-emails__search"><label for="wpcpm-emails-search">%1$s</label> <input type="search" id="wpcpm-emails-search" name="s" value="%2$s" /></div>',
			esc_html__( 'Search address, name or subject', 'wpcredits-program-manager' ),
			esc_attr( $filters['search'] )
		);

		self::select( 'module', __( 'Area', 'wpcredits-program-manager' ), WPCPM_Mail_Catalog::modules(), $filters['module'], __( 'Every area', 'wpcredits-program-manager' ) );
		self::select( 'type', __( 'Recipient type', 'wpcredits-program-manager' ), WPCPM_Mail_Catalog::types(), $filters['recipient_type'] );
		self::select(
			'status',
			__( 'Status', 'wpcredits-program-manager' ),
			array(
				WPCPM_Mail_Log::STATUS_SENT        => WPCPM_Mail_Log::status_label( WPCPM_Mail_Log::STATUS_SENT ),
				WPCPM_Mail_Log::STATUS_FAILED      => WPCPM_Mail_Log::status_label( WPCPM_Mail_Log::STATUS_FAILED ),
				WPCPM_Mail_Log::STATUS_UNCONFIRMED => WPCPM_Mail_Log::status_label( WPCPM_Mail_Log::STATUS_UNCONFIRMED ),
				WPCPM_Mail_Log::FILTER_TEST        => __( 'Test', 'wpcredits-program-manager' ),
			),
			$filters['status']
		);

		// The Email filter is the one long list: one field that drops down, takes typing and narrows.
		/* translators: %s: how many emails the Email filter's list shows, as a number. */
		$count = __( 'Emails in the list: %s', 'wpcredits-program-manager' );

		echo '<div class="wpcpm-emails__filter wpcpm-emails__email">';
		WPCPM_Dashboards::render_combo(
			array(
				'id'      => 'wpcpm-emails-email',
				'name'    => 'email',
				'options' => self::email_options(),
				'current' => $filters['template'],
				'label'   => __( 'Email', 'wpcredits-program-manager' ),
				'find'    => __( 'Type to find an email', 'wpcredits-program-manager' ),
				'none'    => __( 'No email has that name.', 'wpcredits-program-manager' ),
				'count'   => $count,
			)
		);
		echo '</div>';

		self::select(
			'when',
			__( 'When', 'wpcredits-program-manager' ),
			array(
				'today' => __( 'Today', 'wpcredits-program-manager' ),
				'7'     => __( 'The last 7 days', 'wpcredits-program-manager' ),
				'30'    => __( 'The last 30 days', 'wpcredits-program-manager' ),
				'range' => __( 'From and to dates', 'wpcredits-program-manager' ),
			),
			WPCPM_Request::key( 'when' ),
			__( 'Any time', 'wpcredits-program-manager' )
		);

		foreach ( array(
			'from' => __( 'From', 'wpcredits-program-manager' ),
			'to'   => __( 'To', 'wpcredits-program-manager' ),
		) as $name => $label ) {
			printf(
				'<div class="wpcpm-emails__filter"><label for="wpcpm-emails-%1$s">%2$s</label> <input type="date" id="wpcpm-emails-%1$s" name="%1$s" value="%3$s" /></div>',
				esc_attr( $name ),
				esc_html( $label ),
				esc_attr( WPCPM_Request::text( $name ) )
			);
		}

		printf( '<div class="wpcpm-emails__filter"><button type="submit" class="button">%s</button></div>', esc_html__( 'Filter', 'wpcredits-program-manager' ) );
		echo '</form>';

		$total = (int) $table->get_pagination_arg( 'total_items' );

		printf(
			'<p class="wpcpm-emails__count">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: number of emails. */
					_n( '%s email matches.', '%s emails match.', $total, 'wpcredits-program-manager' ),
					number_format_i18n( $total )
				)
			)
		);

		$table->display();

		// Under the table: what the status most rows carry promises.
		printf( '<p class="description">%s</p>', esc_html__( '"Handed to the mail server" means the site passed the email on, not that it reached the inbox.', 'wpcredits-program-manager' ) );
		echo '</div>';
	}

	/**
	 * A plain select for a short list, with its label.
	 *
	 * @param string $name    The query argument.
	 * @param string $label   The label.
	 * @param array  $options Value to label.
	 * @param string $current The value chosen.
	 * @param string $any     The first option's label, for no choice; "All" when empty.
	 */
	private static function select( $name, $label, array $options, $current, $any = '' ) {
		$any = '' !== $any ? $any : __( 'All', 'wpcredits-program-manager' );

		printf( '<div class="wpcpm-emails__filter"><label for="wpcpm-emails-%1$s">%2$s</label> ', esc_attr( $name ), esc_html( $label ) );
		printf( '<select id="wpcpm-emails-%1$s" name="%1$s"><option value="">%2$s</option>', esc_attr( $name ), esc_html( $any ) );

		foreach ( $options as $value => $text ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( (string) $current, (string) $value, false ), esc_html( $text ) );
		}

		echo '</select></div>';
	}
}
```

- [ ] **Step 5: Write the stylesheet**

The combobox's rules follow `assets/css/dashboard.css` (the `.wpcpm-dashboard__switcher-combo`, `-input`, `-list`, `-option`, `.is-active`, `.is-current`, `-empty` and `-status` rules), scoped to `.wpcpm-emails` and in wp-admin's colors. The list scrolls and is `position: absolute`, which bin/test-institution-panel.php's scroll-box check asks of every box that scrolls; nothing says `!important`.

Create `assets/css/emails.css`:

```css
/* The Emails screen: the Log's filters, its Email field, and the Test mark. Loaded on that screen
   only (WPCPM_Emails::enqueue_assets()), in wp-admin's own colors. */

/* The filters, as one row that wraps: each its label over its field, so a phone stacks them. */
.wpcpm-emails__filters {
	align-items: flex-end;
	display: flex;
	flex-wrap: wrap;
	gap: 0.75em 1.25em;
	margin: 1em 0;
}

.wpcpm-emails__filter {
	display: flex;
	flex-direction: column;
	gap: 0.25em;
	max-width: 100%;
}

.wpcpm-emails__filter select,
.wpcpm-emails__filter input {
	max-width: 100%;
}

.wpcpm-emails__count {
	margin: 0.75em 0;
}

.wpcpm-emails__test {
	background: #f0f0f1;
	border-radius: 2px;
	padding: 0 0.4em;
}

/* The Email field: the combobox assets/js/switcher.js shows in the select's place, as the Viewing as
   switcher's (assets/css/dashboard.css), without the theme's colors. An author `display` beats the
   browser's `[hidden]`, so the hidden state is said again. */
.wpcpm-emails .wpcpm-emails__email select[hidden],
.wpcpm-emails .wpcpm-dashboard__switcher-combo[hidden],
.wpcpm-emails .wpcpm-dashboard__switcher-list[hidden] {
	display: none;
}

.wpcpm-emails .wpcpm-dashboard__switcher-combo {
	box-sizing: border-box;
	max-width: 100%;
	position: relative;
	width: 18em;
}

.wpcpm-emails .wpcpm-dashboard__switcher-input {
	box-sizing: border-box;
	width: 100%;
}

/* The field's list: under the field, at its width, over the table. About ten rows show before it
   scrolls: switcher.js sets the height from a row it measures, and this one stands until it does. */
.wpcpm-emails .wpcpm-dashboard__switcher-list {
	background: #fff;
	border: 1px solid #8c8f94;
	border-radius: 4px;
	box-shadow: 0 4px 12px rgba( 0, 0, 0, 0.12 );
	box-sizing: border-box;
	color: #1d2327;
	left: 0;
	list-style: none;
	margin: 0;
	max-height: 24em;
	overflow-y: auto;
	padding: 0;
	position: absolute;
	top: calc( 100% + 2px );
	width: 100%;
	z-index: 10;
}

.wpcpm-emails .wpcpm-dashboard__switcher-option,
.wpcpm-emails .wpcpm-dashboard__switcher-empty {
	margin: 0;
	overflow-wrap: anywhere;
	padding: 0.375em 0.5em;
}

.wpcpm-emails .wpcpm-dashboard__switcher-option {
	cursor: pointer;
}

.wpcpm-emails .wpcpm-dashboard__switcher-option:hover {
	background: #f0f0f1;
}

/* The highlighted row, the one Enter picks. */
.wpcpm-emails .wpcpm-dashboard__switcher-option.is-active {
	background: #2271b1;
	color: #fff;
}

/* The email picked, which the select holds: by weight, so it is not told by a color alone. */
.wpcpm-emails .wpcpm-dashboard__switcher-option.is-current {
	font-weight: 600;
}

.wpcpm-emails .wpcpm-dashboard__switcher-empty {
	color: #646970;
}

/* Forced colors replace the backgrounds; the system's own pair keeps the highlighted row apart. */
@media ( forced-colors: active ) {

	.wpcpm-emails .wpcpm-dashboard__switcher-option.is-active {
		background: Highlight;
		color: HighlightText;
		forced-color-adjust: none;
	}
}

/* For a screen reader: how many emails the list shows, or that none match. Out of sight but never
   out of the page, because a status region that is not in the page when its text arrives is not
   read out. */
.wpcpm-emails .wpcpm-dashboard__switcher-status {
	border: 0;
	clip: rect( 0 0 0 0 );
	clip-path: inset( 50% );
	height: 1px;
	margin: -1px;
	overflow: hidden;
	padding: 0;
	position: absolute;
	white-space: nowrap;
	width: 1px;
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php bin/test-emails-screen.php`
Expected: `ALL PASS (32 checks)`.

- [ ] **Step 7: Bring the suites that read the registry to the new tool, and see them fail**

The tool's status line reads the email log on the Tools screen and the Overview, which bin/test-admin-menu.php and bin/test-overview.php draw over `bin/stubs/overview-reads.php`. In that file, replace the start of the email log's stand-in (which Task 3 added):

```php
/** The email log, which a new site has, empty: nothing sent, nothing failed. */
class WPCPM_Mail_Log {
	const STATUS_FAILED = 'failed';

	public static function latest() {
```

with:

```php
/** The email log, which a new site has, empty: nothing sent, nothing failed. */
class WPCPM_Mail_Log {
	const KEEP_DAYS     = 30;
	const STATUS_FAILED = 'failed';

	public static function exists() {
		return true;
	}

	public static function count_since( $since_utc ) {
		return 0;
	}

	public static function latest() {
```

In `bin/test-admin-menu.php`, which requires each tool file by hand, replace lines 144 to 146:

```php
// The five tools and their registry, the same way.
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-header-notices.php';
```

with:

```php
// The six tools and their registry, the same way.
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-header-notices.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-emails.php';
```

In the menu's list (line 249), the tool follows Header notices, as the registry has it:

```php
        array( 'Header notices', "-\u{2009}Header notices", 'wpcpm-tool-header-notices' ),
```

with:

```php
        array( 'Header notices', "-\u{2009}Header notices", 'wpcpm-tool-header-notices' ),
        array( 'Emails', "-\u{2009}Emails", 'wpcpm-tool-emails' ),
```

and in the cards' (line 314):

```php
        array( 'Header notices', 'Need help?', 'Mentor Status Checker', 'Student Duplicate Finder', 'Track Builder' ),
```

with:

```php
        array( 'Header notices', 'Emails', 'Need help?', 'Mentor Status Checker', 'Student Duplicate Finder', 'Track Builder' ),
```

The comment above the status lines (lines 368 to 370):

```php
// A tool that cannot run says why on its card, as a warning, in its own words: the Mentor Status
// Checker and the Student Duplicate Finder need Airtable, and Need help? needs its switch and a
// provider and no Airtable at all. On a new site nothing is connected and no provider is set. The
```

with:

```php
// A tool that cannot run says why on its card, as a warning, in its own words: the Mentor Status
// Checker and the Student Duplicate Finder need Airtable, and Need help? needs its switch and a
// provider and no Airtable at all. Emails needs nothing, and counts what its log holds, which on a
// new site is nothing. On a new site nothing is connected and no provider is set. The
```

A new site's status lines (lines 376 and 377): Emails can always run, and its log is empty:

```php
	'Header notices'           => array( array( 'wpcpm-tool-status', false, 'No notices are showing.' ) ),
	'Need help?'               => array( array( 'wpcpm-tool-status', true, 'No AI provider is configured, so questions cannot be answered.' ) ),
```

with:

```php
	'Header notices'           => array( array( 'wpcpm-tool-status', false, 'No notices are showing.' ) ),
	'Emails'                   => array( array( 'wpcpm-tool-status', false, '0 emails in the last 30 days.' ) ),
	'Need help?'               => array( array( 'wpcpm-tool-status', true, 'No AI provider is configured, so questions cannot be answered.' ) ),
```

and the lines with everything ready (lines 410 and 411):

```php
            'Header notices'           => array( array( 'wpcpm-tool-status', false, 'No notices are showing.' ) ),
            'Need help?'               => array( array( 'wpcpm-tool-status', false, 'Answering through Google AI Studio (Gemini).' ) ),
```

with:

```php
            'Header notices'           => array( array( 'wpcpm-tool-status', false, 'No notices are showing.' ) ),
            'Emails'                   => array( array( 'wpcpm-tool-status', false, '0 emails in the last 30 days.' ) ),
            'Need help?'               => array( array( 'wpcpm-tool-status', false, 'Answering through Google AI Studio (Gemini).' ) ),
```

In `bin/test-roles.php`, the scan of class files (line 388 once Task 1's edit is in) leaves out the Log's list table, which is required on the Emails screen only, and checks that it is:

```php
ck( 'every class file under includes/ is required by the loader',
    array_values( array_diff( $on_disk, $anywhere[1] ) ), array() );
```

with:

```php
// But one: the Log's list table extends core's list table, which wp-admin loads after the plugins,
// so the Emails tool requires it on its own screen, as the audience screens load their account
// lists (`wpcpm_load_accounts_tables()`), and the loader leaves it out.
$on_screen = array( 'includes/mail/class-wpcpm-mail-log-table.php' );
ck( 'every class file under includes/ is required by the loader, but the Log\'s list table',
    array_values( array_diff( $on_disk, $anywhere[1], $on_screen ) ), array() );
ck( 'which the Emails tool requires on its screen, and the loader does not',
    array( false !== strpos( (string) file_get_contents( dirname( __DIR__ ) . '/includes/tools/class-wpcpm-emails.php' ), "require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-log-table.php';" ), in_array( $on_screen[0], $anywhere[1], true ) ), array( true, false ) );
```

and the registry's check (line 395 before the edit above) asks for the new tool too:

```php
ck( 'the Student Duplicate Finder is registered as a tool',
    array( false !== strpos( (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-wpcpm-tools.php' ), 'new WPCPM_Duplicate_Finder()' ) ), array( true ) );
```

with:

```php
ck( 'the Student Duplicate Finder is registered as a tool',
    array( false !== strpos( (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-wpcpm-tools.php' ), 'new WPCPM_Duplicate_Finder()' ) ), array( true ) );
ck( 'and so is Emails',
    array( false !== strpos( (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-wpcpm-tools.php' ), 'new WPCPM_Emails()' ) ), array( true ) );
```

In `docs/sections/30-admin-wpadmin.md`, the table of the words the screens use names every tool the registry holds (bin/test-admin-menu.php reads the names from `WPCPM_Tools::all()`), so its Tools row (line 26) names **Emails**. Replace:

```markdown
| **Tools** | The parts of the program that are run and configured on their own rather than belonging to one audience: **Header notices**, **Need help?**, the **Mentor Status Checker**, the **Student Duplicate Finder** and the **Track Builder**, each called by its name alone. | The **Tools** menu item and screen, and the Overview's **Tools** card, which lists each tool with its status. |
```

with:

```markdown
| **Tools** | The parts of the program that are run and configured on their own rather than belonging to one audience: **Header notices**, **Emails**, **Need help?**, the **Mentor Status Checker**, the **Student Duplicate Finder** and the **Track Builder**, each called by its name alone. | The **Tools** menu item and screen, and the Overview's **Tools** card, which lists each tool with its status. |
```

Task 7 rewords the Tools paragraph further down the same file and rebuilds the guides.

Run, one per command: `php bin/test-admin-menu.php`, `php bin/test-roles.php`.
Expected: test-admin-menu FAILs its menu, cards and status-line checks (no Emails tool is registered yet); test-roles FAILs its scan of class files (the tool's file is not required yet) and `and so is Emails`.

- [ ] **Step 8: Register and load the tool**

In `includes/class-wpcpm-tools.php`, replace line 31:

```php
			$tools = array( new WPCPM_Header_Notices(), new WPCPM_Handbook(), new WPCPM_Mentor_Checker(), new WPCPM_Duplicate_Finder(), new WPCPM_Track_Builder() );
```

with:

```php
			$tools = array( new WPCPM_Header_Notices(), new WPCPM_Emails(), new WPCPM_Handbook(), new WPCPM_Mentor_Checker(), new WPCPM_Duplicate_Finder(), new WPCPM_Track_Builder() );
```

In `wpcredits-program-manager.php`, replace the Header notices tool's require (line 144 once Tasks 1, 2 and 4 are in):

```php
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-header-notices.php';
```

with:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-header-notices.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-emails.php';
```

In `uninstall.php`, replace the same require (line 158 once Tasks 1, 2 and 4 are in); bin/test-roles.php holds the two lists of requires together:

```php
require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-header-notices.php';
```

with:

```php
require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-header-notices.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-emails.php';
```

The list table's file is required only inside `render_admin_page()` (Step 4's code), as the audience screens load their account lists only when a screen needs them: it extends core's list table, which wp-admin loads after the plugins.

- [ ] **Step 9: Run the touched suites to verify they pass**

Run, one per command: `php bin/test-emails-screen.php`, `php bin/test-admin-menu.php`, `php bin/test-roles.php`, `php bin/test-overview.php`, `php bin/test-uninstall.php`, `php bin/test-settings.php`, `php bin/test-institution-panel.php`, `php bin/test-dashboard-switcher.php`, `php bin/test-combo-field.php`.
Expected: each ends `ALL PASS` (`(32 checks)` for the first, `(30 checks)` for test-overview, `(35 checks)` for test-uninstall, `(11 checks)` for test-combo-field).

- [ ] **Step 10: Run the whole battery**

Run, from the plugin root, every suite in its own process, then the references check:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`. Then `sh bin/check-standards.sh` reports no more errors than before this release (the new files hold none).

- [ ] **Step 11: Commit**

```bash
git add includes/tools/class-wpcpm-emails.php includes/mail/class-wpcpm-mail-log-table.php assets/css/emails.css bin/test-emails-screen.php includes/class-wpcpm-tools.php wpcredits-program-manager.php uninstall.php bin/test-admin-menu.php bin/test-roles.php bin/stubs/overview-reads.php docs/sections/30-admin-wpadmin.md
git commit -m "Emails tool: the Log, searchable and filtered by area, recipient type, status, email and time"
```

---

### Task 7: The guide, the version, the changelog and the template

**Files:**
- Modify: `docs/sections/32-admin-tools.md` (a new `### Emails` section, after `### Header notices` and before `### Mentor Status Checker`, line 30, as the menu orders the tools)
- Modify: `docs/sections/31-admin-settings.md` (the `#### Recent mail` part, lines 310 to 316)
- Modify: `docs/sections/30-admin-wpadmin.md` (the Tools paragraph, lines 156 to 161; the table's Tools row at line 26 names Emails since Task 6)
- Regenerate: `docs/*.md` and `docs/build/*.html` with `php bin/build-docs.php`
- Modify: `wpcredits-program-manager.php` (`Version:` at line 6, `WPCPM_VERSION` at line 22), `readme.txt` (`Stable tag` at line 7, the 1.122.18 entry at the top of the changelog, line 295)
- Regenerate: `languages/wpcredits-program-manager.pot` with `sh bin/make-pot.sh`
- Suites to run: `bin/test-cli.php`, `bin/check-spelling.php`, `bin/test-settings.php` (it holds the built guide to its parts), `bin/test-admin-menu.php` (the guides' words), `bin/test-tooling.php`, `bin/test-fixtures.php`, then the whole battery

**Interfaces:**
- Consumes: the strings Tasks 1 to 6 added, which the template collects.
- Produces: nothing new in code; the guide, version 1.122.18, its changelog entry and the template.

- [ ] **Step 1: The guide's Emails section**

The guide calls the menu path "WPCredits Program > Tools > Emails", as it calls every tool's, and says **Area**, never module.

In `docs/sections/32-admin-tools.md`, replace the Mentor Status Checker's heading (line 30):

```markdown
### Mentor Status Checker
```

with:

```markdown
### Emails

**WPCredits Program > Tools > Emails.** A record of every email the site sends, kept for 30 days:
the plugin's own, WordPress's (a password reset, for example) and the Two Factor plugin's sign-in
codes. Use it to answer "did they get the email?".

Each row is one recipient of one email: when it was sent, in the site's time zone; the address,
with the person's name when the address belongs to an account, linked to the account; the
recipient type (Student, Mentor, Institution member, Sponsor member, Administrator, Applicant (no
account) or Other); the area that sent it (Invitations, Mentor calls, Institutions, Semester
reports, Sponsors, WordPress, Two Factor or Other); which email it was; its subject; and its status.

- **Handed to the mail server**: the site passed the email on. This does not prove it reached the
  inbox: a full mailbox or a spam filter can still stop it after it leaves the site.
- **Failed**: WordPress could not hand it over; the reason it gave is shown under the status.
- **Not confirmed**: another plugin took the email over before WordPress could say what happened,
  or the request ended before it did.
- **Test**: a sample you sent yourself from Settings > Mail.

To find an email, type part of an address, a name or a subject into the search box, or narrow the
list by **Area**, **Recipient type**, **Status**, **Email** or **When** (today, the last 7 days, the
last 30 days, or from and to dates), and press **Filter**. The **Email** filter is one field: type
a few letters of the email's name and pick it from the list. Fifty rows show a page, newest first,
with the count above them.

The log never keeps the text of an email, its attachments, or anything secret such as a sign-in
code or a password link. Entries older than 30 days are deleted every day. WordPress's **Export
Personal Data** and **Erase Personal Data** tools include a person's entries, found by their
address. The last hundred emails the plugin listed under Settings > Mail before 1.122.18 are in the
log too, with their addresses masked as they were kept and no subject.

### Mentor Status Checker
```

In `docs/sections/31-admin-settings.md`, replace the Recent mail part (lines 310 to 316); the paragraph at line 299 keeps both section names as they are:

```markdown
#### Recent mail

A log of the last 25 messages the plugin sent: bookings, cancellations, reminders and invitations,
each with when, to whom, its subject and what it was, and whether the site accepted it. "Accepted"
means the site handed the message off without complaint; it cannot tell you the message was
delivered or read. A message the site refused is marked **Refused**, and a line above the log counts
them: that is a delivery problem to fix, not a program one.
```

with:

```markdown
#### Recent mail

Points to **WPCredits Program > Tools > Emails**, which keeps every email the site sends for 30
days, with **Open the email log**. When an email failed in the last 30 days, the section says how
many and links to them.
```

In `docs/sections/30-admin-wpadmin.md`, the Tools paragraph names the new tool; replace lines 157 and 158:

```markdown
rather than belonging to one audience - currently **Header notices**, **Need help?**, the **Mentor
Status Checker**, the **Student Duplicate Finder** and the **Track Builder**. Each has its own screen
```

with:

```markdown
rather than belonging to one audience - currently **Header notices**, **Emails**, **Need help?**, the
**Mentor Status Checker**, the **Student Duplicate Finder** and the **Track Builder**. Each has its own screen
```

Run: `php bin/build-docs.php` (the plain build, so the committed guides keep their relative image paths), then, one per command, `php bin/test-cli.php`, `php bin/check-spelling.php`, `php bin/test-settings.php`.
Expected: the build prints a line per guide and `Done.`; `ALL PASS (14 checks)`; `... US English throughout.`; `ALL PASS` (its check that the built guide is its parts, heading for heading, reads the new `### Emails`).

- [ ] **Step 2: The version and the entry**

In `wpcredits-program-manager.php`, replace line 6:

```php
 * Version:           1.122.17
```

with:

```php
 * Version:           1.122.18
```

and line 22:

```php
define( 'WPCPM_VERSION', '1.122.17' );
```

with:

```php
define( 'WPCPM_VERSION', '1.122.18' );
```

In `readme.txt`, replace line 7:

```text
Stable tag: 1.122.17
```

with:

```text
Stable tag: 1.122.18
```

and add the entry above `= 1.122.17 =` (lines 293 to 295):

```text
== Changelog ==

= 1.122.17 =
```

with:

```text
== Changelog ==

= 1.122.18 =

* WPCredits Program > Tools > Emails: a record of every email the site sends, kept for 30 days. Each row is one recipient of one email: when, to whom (with the person's name when the address belongs to an account), the recipient type, the area that sent it, which email it was, its subject, and whether WordPress handed it to the mail server, failed, or did not say. Search by address, name or subject, and filter by area, recipient type, status, email and time; the Email filter is one field to type into and pick from. It covers the plugin's emails, WordPress's own and the Two Factor plugin's sign-in codes, and never keeps an email's text, attachments or anything secret.
* Settings > Mail: Recent mail, which listed the plugin's last hundred emails, now points to the new log and says how many emails failed in the last 30 days. Its hundred entries move into the log, their addresses masked as they were kept.
* Administrator Dashboard, the site health card: the last email by name, its status, and how many failed in the last day, each linked to the log.
* Privacy: WordPress's Export Personal Data and Erase Personal Data tools include a person's entries in the log. Uninstalling the plugin removes the log's table.
* For developers: `WPCPM_Mail_Log` (the table `{prefix}wpcpm_mail_log`, created on `init` and stamped in `wpcpm_mail_log_version`, booted from the plugin's bootstrap), `WPCPM_Mail_Capture` (the `wp_mail`, `pre_wp_mail`, `wp_mail_succeeded`, `wp_mail_failed` and `shutdown` hooks) and `WPCPM_Mail_Catalog` (every email's name, area and audience). `WPCPM_Dashboards::render_combo()` draws the one field for a long list that the Viewing as switcher and the Email filter share. `WPCPM_Mail::log()`, `failures()` and `clear_log()` are gone, with the `wpcpm_mail_log` option; `WPCPM_Mail::take_context()` hands the email's id to the capture.

= 1.122.17 =
```

- [ ] **Step 3: The template**

Run: `sh bin/make-pot.sh`
Expected: `Success: POT file successfully generated.` The new strings arrive in the template, each with its translators comment, among them `Names in the list: %s`, `Emails in the list: %s` and `Last email: %1$s to %2$s on %3$s. Status: %4$s.`

- [ ] **Step 4: Run the release's suites**

Run, one per command: `php bin/test-tooling.php`, `php bin/check-spelling.php`, `php bin/test-fixtures.php`, `php bin/test-admin-menu.php`.
Expected: each passes. Then grep the release's diff for dashes: `git diff main -- . | grep -nP '[\x{2013}\x{2014}]'` prints nothing.

- [ ] **Step 5: Run the whole battery**

Run, from the plugin root, every suite in its own process, then the references check:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 6: Commit**

```bash
git add docs/ wpcredits-program-manager.php readme.txt languages/wpcredits-program-manager.pot
git commit -m "1.122.18: the email log; the guide, the version, the changelog and the template"
```

---

## After the tasks (the controller's, not an implementer's)

1. The whole-release review, one fix wave, a scoped re-review.
2. The gate on a frozen copy: no conflict markers, `php -l` on every file, `php bin/check-references.php`, the whole battery (`for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done`, printing nothing), `sh bin/check-standards.sh` with no more errors than 1.122.17, and `php bin/check-dead-annotations.php` with none dead, all green.
3. The zip from a clean clone (`bash bin/build ~/GitHub/wpcredits-program-manager.zip`).
4. The local copy (localhost:8883): snapshot the database; install; check that the table exists (`studio wp db query "SHOW TABLES LIKE 'wp_wpcpm_mail_log'"`, which the SQLite integration answers), that `wpcpm_mail_log_version` reads 1 and that the old option moved into the table and is gone. Then send one email of each area and read each back in the Log, each **Not confirmed** (the copy's safety plugin takes every email over through `pre_wp_mail`), with its area and its email's name: a sample invitation from Settings > Mail (Invitations, marked **Test**), a call booking (Mentor calls), an institution email such as a question about an application (Institutions), a semester report drafted (Semester reports), a sponsor email such as a question about a sponsor application (Sponsors), a WordPress password reset from the login screen (WordPress, "Password reset") and a Two Factor sign-in code at a login with the email provider (Two Factor, "Sign-in code"; the local copies run Two Factor 0.16.0, which has the filter). Exercise the search (an address, a name, a subject, "100%", "a_b"), every filter, paging, and the Email field at desktop width and at 375px: the select is hidden, the field shows in its place, drops down, takes typing and narrows, and Filter sends what was picked. Open the Administrator Dashboard and read the health card's line and its links. Run WordPress's Export Personal Data for the local administrator's address and see the entries; erase a test address's entries. Put the database back.
5. Before the deploy, check that the live site's Two Factor plugin still applies `two_factor_token_email_subject` (read its version with `wp plugin get two-factor --field=version` and find the filter in its `providers/class-two-factor-email.php`); if it does not, the codes show as Other until the catalog names its new filter. Then the mirror push and the live deploy on the owner's yes, with the usual recipe; after the install, check on the live site that the table was created and the version stamped (`wp option get wpcpm_mail_log_version` reads 1), that the old option is gone, send one test email to the owner's test address and see it in the Log as **Handed to the mail server**, and republish the administrators' guide (page 560) from a `--base=` build.
