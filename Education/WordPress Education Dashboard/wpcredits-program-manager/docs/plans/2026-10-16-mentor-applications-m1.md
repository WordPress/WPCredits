# Mentor applications on the site, M1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A person applies to mentor on wordpresseducation.org at /become-a-mentor/, and the Administrators read and decide each application on a new **Waiting for review** tab of the wp-admin Mentors screen, the applicant and the Administrators being written to from the site's own editable templates; in this phase nothing activates and nothing is written to Airtable.

**Architecture:** One private post type, `wpcpm_ment_app`, holds a row per application with its answers, its guard's evidence, its state (nine, `new` to `lapsed`) and a dated history (`WPCPM_Mentor_Application`). The public form (`WPCPM_Mentor_Application_Form`) is the sponsor form's shape through the shared `WPCPM_Form_Guard` and `WPCPM_Form_Stash`, with the mentor's questions as site fields; the queue (`WPCPM_Mentor_Application_Queue`) is the Mentors screen's first tab, five views, a list of the applications recently decided and seven decisions; a daily run lapses selected rows the course never reached and keeps the retention days; a public REST route (`WPCPM_Mentor_Application_Feed`) answers counts by month, never people. The five emails are templates in a new **Mentor applications** area of the catalog, the four to the applicant in the words of the program's Airtable automations, recorded word for word through the real senders.

**Tech Stack:** PHP 7.4+ WordPress plugin `wpcredits-program-manager` (prefix `WPCPM_`); the plugin's no-WordPress suites `bin/test-*.php` (run from the plugin root with `php bin/test-x.php`; each ends `ALL PASS` and exits 0); `bin/check-standards.sh` (WordPress coding standards, the dash rule and the spelling check); the template registry under `includes/mail/templates/`; the Docker copy in `~/Projects/wpcredits-docker`; the release script `~/Projects/wpcredits-release/release.py`.

**Spec:** `docs/specs/2026-10-16-mentor-applications-on-the-site-design.md`, phase M1 (section 12, first row), with sections 3 to 5, 8, 9 and 13. Read it before any task. "The course is completed", the activation step, the invitation's welcome text and the write to Airtable are M2 and are not in this plan.

## Global Constraints

- Release **1.122.31**: the plugin header, `WPCPM_VERSION`, `readme.txt` Stable tag, a `= 1.122.31 =` changelog entry and `languages/wpcredits-program-manager.pot` (`bash bin/make-pot.sh`). The institutions' resend-invitation release (its plan on main at 7b790c0) ships as 1.122.30 first, the owner's choice of 16 October 2026. Main stays on the 1.122 line. Branch `feature/mentor-applications-m1` from `main` at 7b790c0 or later. If main's version has moved past 1.122.30 by then, take the next free 1.122 number wherever this plan says 1.122.31: the recording of Task 9 then gets that number in its name and its comment, and so a hash of its own, as Task 9 says, and nothing else changes. If this plan is executed before 1.122.30 has merged, say so and take the owner's word on which ships first before Task 10. 1.122.30 also changes `wpcredits-program-manager.php`, `uninstall.php`, `docs/sections/34-admin-operations.md`, `readme.txt`, the translation template and `includes/class-wpcpm-mail.php`: once it has merged, a diff below whose context it moved is applied around its lines, and Task 9's recording, made on main as it then is, must still give Task 9's hash, or the task stops to find out why.
- PHP 7.4 compatible: `array()` syntax, no `match`, no named arguments, no typed properties, no `?->`, no trailing comma in a parameter list or a closure's `use` list, and no reliance on `usort()` keeping equal rows in place (that holds from PHP 8.0 only).
- WordPress coding standards: tabs, Yoda conditions, spaces inside parentheses, strict `in_array()`, no short ternary. `bash bin/check-standards.sh` ends `78 warnings, no errors.` with no ` ERROR ` line: 78 is main's own count at 7b790c0, and no task adds a warning.
- Every handler checks the login and the capability first, then the nonce keyed to the action and the row; every posted value goes through `wp_unslash()` and a sanitizer; every printed value is escaped; every string with a placeholder has a translator comment.
- No em dash and no en dash anywhere: code, comments, strings, docs, commit messages. Plain hyphens.
- US English in every string a person reads (`php bin/check-spelling.php`); full product names ("Student Report Card", "Mentor Report Card", "Administrator Dashboard", "Mentor Status Checker"); "Administrators" for the people who run the program, in every string.
- New data is a site field, never an Airtable column, and nothing in M1 writes to Airtable or reads it on a public request (spec, section 1, item 5; section 4).
- No course code, invitation code or folder link in code, a template, a fixture or a document: the course link is a setting an Administrator fills in (`mentor_course_link`), and every example of it is example.test's.
- `bin/` and `docs/` are public (mirrored to WordPress/WPCredits): example.test addresses, spelled-out record ids (`recCOUNTRY0000001`), invented names, synthetic WordPress.org usernames (`ada-example`), no real person anywhere. `php bin/test-fixtures.php` walks every file of both, `docs/specs` and `docs/plans` included, and `includes/`.
- Name the role, never the person: the program manager who reviews mentor applications is "the mentor coordinator" (spec, section 1, item 3), in this plan and in every code comment, fixture, test name, guide sentence and commit message, and no other real person is named either, the plugin header's Author line, which the privacy walk allows, excepted. In a guide sentence the people who run the program are "Administrators": "Program Administrator" is a retired name `bin/test-admin-menu.php` refuses there.
- The feed answers counts, never a name, an address, a username, a reference or a note.
- One commit per task, its message starting `Mentor applications:` and ending, after a blank line, with a `Co-Authored-By:` trailer naming the implementer's own model.
- Nothing is deployed, mirrored or posted without the owner's explicit yes for that step; the live version is read first; every test email on the live site goes to the owner's one test address only, and the notice's list is pointed there before any test submission.

## Review Focus

1. **Somebody who applies twice, or who mentors already.** The same address in another letter case, the same WordPress.org username, an application still in the queue or waiting for the course, a mentor account of any standing, a mentor the last sync read: each holds the new row with a mark, and nothing is ever merged; a declined application is no duplicate, since the decline email invites a new one. Pinned in Task 4, step 1: "another open application with the same address, in any case, holds the second and marks it", "one waiting for the course with the same username holds it too", "but a declined one is no duplicate: the same person may apply again", and the four "a mentor the site knows holds it" cases.
2. **The WordPress.org profile typed in any of its forms, and nothing else taken for one.** A username with no space, with or without one leading `@`, or an address on profiles.wordpress.org, wordpress.org or www.wordpress.org, with or without the scheme, in capitals, with `/profile/` after it, padded with spaces: each is kept as one username. Anything else is refused with "Give your WordPress.org username or your profile's address." and never stored: a full name typed in the box, an address on another site (GitHub, LinkedIn, the applicant's own), wordpress.org's own address with no profile in it. Pinned in Task 4, step 1: "a profile is a username, with or without its @, or an address on WordPress.org, and each is kept as the username", "anything else is refused, a name, a link to another site, and an address on WordPress.org that names nobody", "an empty one is left for requiredness, an array refused, and the refusal says what to give" and "a profile that is no username and a free text with a tag come back with the sentence that says what to give".
3. **An address the applicant never confirms.** Select, Ask for more information and Decline each refuse with a sentence, send nothing and leave the row where it was; Mark as spam closes such a row without writing to anybody. Pinned in Task 5, step 1: "an address nobody confirmed is refused by Select, Ask for more information and Decline alike, nothing sent and nothing moved" and "Mark as spam closes the row, sends nothing, and needs no confirmed address". A row whose acknowledgement never went, past the day's limit on acknowledgements or filed as spam by the checks, carries the mark **acknowledgement not sent** to say why, until Put back in the queue sends it to such a row filed as spam: "a row past the day's limit on acknowledgements is marked so, as one the checks filed as spam is".
4. **Words typed with quotes, an ampersand, a "<", a backslash or letters past ASCII.** Kept as typed, printed escaped on the tab, and read as typed in every email. The free text (**Anything else you'd like us to know?**) refuses what the site's cleaning would take out of it, a tag such as "the <Navigation> block", with "Please write without angle brackets or tags.", rather than store it cut, and keeps "a < b" and "Tom & Jerry's "quote"" as typed. Pinned in Task 1, step 1 ("the answers are kept as typed, a backslash and a quote among them"), Task 4, step 1 ("the free text refuses what the cleaner would take out of it, and keeps a lone "<", quotes, an ampersand and an apostrophe"), Task 5, step 1 ("a row says who and when, its answers escaped, ...") and Task 9, step 1 (the case `a-name-as-typed`, recorded through the real mail layer).
5. **The site's day, not UTC's.** A reference takes the year of the site's clock, and the feed counts an event in the site's month: an application selected at 23:30 UTC on December 31 on a site two hours ahead is January's. Pinned in Task 1, step 1 ("the reference takes the year of the site's clock, already 2027 at 23:30 UTC on December 31") and Task 7, step 1 ("every month from the first application to this one, each with its five counts, ...").
6. **A decision taken by mistake, a genuine application the checks filed as spam, a declined applicant who appeals.** Recently decided lists the spam and the declined rows, newest first, until retention deletes them; Put back in the queue returns a spam row to `new` with a line in its history, sending the acknowledgement, once, with the link that confirms the address, to a row that never had one, and nothing to one acknowledged when it came; Select takes a declined row as it takes a lapsed one; and no decision takes a row from a state it does not list. Pinned in Task 5, step 1: "Select is offered again on a lapsed or a declined row, and refused on a spam, selected or active one", "every decision from each of the nine states: where it takes the row, its sentence and its email; anywhere else the row as it was, mapp-state and nothing sent", "under the views, Recently decided lists the row the honeypot filed as spam and the declined one, newest first, ...", "sent, the row is new again, the acknowledgement queued once with the link that confirms the address, the mark gone, and the history says so, with who did it", "filed as spam again and put back again, it is sent nothing the second time" and "and a row acknowledged when it came, marked as spam by mistake, is put back with nothing sent"; in Task 9, the case `put-back-after-the-honeypot` records the acknowledgement that path sends.

## Files at a glance

| File | What it is responsible for | Task |
| --- | --- | --- |
| `includes/modules/class-wpcpm-mentor-application.php` | The row: its answers, states, history, reference and confirming link; the lists and counts; the daily lapse and retention run | 1, 6 |
| `includes/mail/templates/mentor-applications.php` | The five emails' shipped words and placeholders | 3 |
| `includes/modules/class-wpcpm-mentor-application-form.php` | The page, the questions, the guard, the row, the acknowledgement and the notice, the confirming link's handler | 4 |
| `blocks/mentor-application/` | The block the page holds, with the owner's heading and thank-you sentence (the shortcode is the fallback) | 4 |
| `includes/modules/class-wpcpm-mentor-application-queue.php` | The Waiting for review tab, its five views and its list of the applications recently decided, and the seven decisions' handlers | 5 |
| `includes/modules/class-wpcpm-mentor-application-feed.php` | The public counts feed | 7 |
| `bin/stubs/mentor-applications.php` | The WordPress stand-ins the new suites share | 1 |
| `bin/test-mentor-application.php`, `-form.php`, `-queue.php`, `-feed.php`, `bin/test-mentor-applications-text.php` | The record, the form, the queue, the feed, and the emails word for word | 1, 4, 5, 6, 7, 9 |

Shared files change where the module plugs in: the loader and the uninstaller (`wpcredits-program-manager.php`, `uninstall.php`), the Mentors module (`class-wpcpm-mentors.php`), the settings and their screen, the catalog and the registry, the Administrator Dashboard's cards and the Overview, one argument pair of `WPCPM_Dashboards::render_combo()`, and the guide.

## The checks every task ends with

Run from the plugin root, after the task's own suites pass:

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done; echo battery done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
php bin/check-temp-litter.php | tail -2
bash bin/check-standards.sh 2>&1 | tail -1
```

Expected: `battery done` and no `FAIL` line; the references check ending `- all resolve`; the spelling check ending `US English throughout.`; `0 dead`; the temp-litter check saying every script exited 0 and the temp directory is as it was found; and `78 warnings, no errors.`. Each task quotes the lines it gave when this plan was proved, since the counts of classes, references and annotations grow task by task. The temp directory is the machine's, so the temp-litter check also names what another program made while it ran (on the machine this plan was proved on, a `docker_cli_*` socket the Docker MCP gateway opens every few minutes); as the check's own header says, run it again: the battery's leftovers come back under the same names, a stranger's do not.

The fail and pass runs quoted below were made on a scratch branch holding exactly this plan's code, on PHP 8.5: a failing run with the task's tests in place and its other files as the task found them, a passing run with the task done. A path is written `<plugin>`, each suite's exit status follows its output in brackets, and only the lines that matter are kept: the failures by name (a suite with many is cut after twenty-five), the first fatal error or warning, and the closing count.

---

## Before Task 1: the branch and the documents

- [ ] **Step 1: Branch from main** (a worktree if another session is working in the checkout; see superpowers:using-git-worktrees)

```bash
cd ~/GitHub/wpcredits-program-manager
git checkout main
git pull --ff-only
git checkout -b feature/mentor-applications-m1
```

- [ ] **Step 2: Start from a main that passes**

Since 892e2df the design names the mentor coordinator by role (section 1, item 3), and the fixtures walk reads `docs/specs` and `docs/plans` as well as `bin/` and `includes/`, so main passes it before any task (proved at 7b790c0, which adds the institutions' resend-invitation design and plan to 892e2df):

Run: `php bin/test-fixtures.php`

```text
$ php bin/test-fixtures.php
ALL PASS
[exit 0]
```

And the checks every task ends with, on main as found:

```text
battery done
153 classes, 14469 references checked, 87 form handlers scanned - all resolve
156 PHP files, 7 blocks, 4 track seeds, 23 fixture and tooling files, the guides and the readme: US English throughout.
260 annotations, 0 dead.
Ran 132 scripts from bin/, each in a PHP process of its own, and every one exited 0.
The battery leaves the temp directory as it found it.
78 warnings, no errors.
```

- [ ] **Step 3: Check that this plan, beside the design, names nobody**

This plan is `docs/plans/2026-10-16-mentor-applications-m1.md`, committed on main beside the design. If the branch does not hold it, copy it there and commit it alone first (`git add docs/plans/2026-10-16-mentor-applications-m1.md` and `git commit -m "Mentor applications: the M1 plan" -m "<the Co-Authored-By trailer your session names for your model>"`). Then:

```bash
grep -n -e $'\xe2\x80\x94' -e $'\xe2\x80\x93' docs/plans/2026-10-16-mentor-applications-m1.md || echo clean
php -r 'require "bin/refused-strings.php"; var_dump( wpcpm_refused_found( file_get_contents( "docs/plans/2026-10-16-mentor-applications-m1.md" ) ) );'
php bin/test-fixtures.php | tail -1
```

Expected, as the plan gave it when it was proved:

```text
clean
array(0) {
}
ALL PASS
```

---

### Task 1: The application record, its states, its history and its confirming link

The row every later task reads and writes: a private post of type `wpcpm_ment_app`, the answers as one meta value, the state, the reference, the consent evidence, the guard's signals, the hash of the address, the moment it was confirmed, and a history of dated rows (spec, section 3, decisions 1 and 2). Nothing here sends or draws anything.

**Files:**
- Create: `includes/modules/class-wpcpm-mentor-application.php`
- Create: `bin/stubs/mentor-applications.php` (the small WordPress the four new suites share: posts and meta, options and transients, accounts, hooks, cron, nonces, redirects as exceptions, the site's clock)
- Create: `bin/test-mentor-application.php`
- Modify: `includes/modules/class-wpcpm-mentors.php` (`boot()`, after `WPCPM_Group_Sessions::init();`)
- Modify: `wpcredits-program-manager.php` and `uninstall.php` (a `require_once` after `class-wpcpm-mentor-engagement.php`)
- Modify: `bin/test-mentors-screen.php` (a stand-in for the record, which the module's boot now calls)

**Interfaces:**
- Consumes: WordPress posts and post meta, `wp_hash()`, `add_query_arg()`, `admin_url()`; nothing of the plugin's.
- Produces: `final class WPCPM_Mentor_Application`, all static:
  - `POST_TYPE = 'wpcpm_ment_app'`; `STATE_NEW`, `STATE_HELD`, `STATE_SPAM`, `STATE_INFO`, `STATE_SECOND`, `STATE_SELECTED`, `STATE_ACTIVE`, `STATE_DECLINED`, `STATE_LAPSED` (`'new'` ... `'lapsed'`); `META_FIELDS`, `META_STATE`, `META_REFERENCE`, `META_CONSENT`, `META_SIGNALS`, `META_EMAIL`, `META_VERIFIED`, `META_EVENT` (keys `_wpcpm_mapp_*`); `ACTION_VERIFY = 'wpcpm_mentor_apply_verify'`; `EVENT_SUBMITTED = 'submitted'`, `EVENT_CONFIRMED = 'address confirmed'`, `EVENT_SELECTED = 'selected'`, `EVENT_INFO = 'information requested'`, `EVENT_SECOND = 'second opinion'`, `EVENT_DECLINED = 'declined'`, `EVENT_SPAM = 'marked as spam'`, `EVENT_WITHDRAWN = 'withdrawn'`, `EVENT_REOPENED = 'put back in the queue'`, `EVENT_LAPSED = 'lapsed'`, `EVENT_ACTIVATED = 'activated'`, and, apart, `EVENT_REOPENED_ACKNOWLEDGED = 'put back in the queue, acknowledgement sent'` (Task 5's Put back in the queue, when it sends the acknowledgement a row never had).
  - `init(): void`; `register_post_type(): void`; `states(): string[]` (the nine, in order); `open_states(): string[]` (`new`, `held`, `info`, `second`).
  - `store( array $fields, string $state, array $signals, array $consent ): int`, the new ID or 0; a state other than `new`, `held` or `spam` is stored as `held`.
  - `email_hash( string $email ): string`; `add_event( int $post_id, string $event, int $actor = 0, string $note = '', int $at = 0 ): void`; `decide( int $post_id, string $state, string $event, int $actor, string $note = '', int $at = 0 ): bool`; `events( int $post_id ): array[]` (each `event`, `at`, `actor`, `note`, oldest first, rows of one second in the order written); `last_at( int $post_id, string $event = '' ): int`.
  - `application( int $post_id ): WP_Post|null`; `state_of( WP_Post $post ): string`; `fields_of( WP_Post $post ): array`; `name_of`, `email_of`, `handle_of( WP_Post $post ): string`; `profile_url( string $handle ): string`; `signals_of( WP_Post $post ): string[]`; `reference( int $post_id ): string` (`MAPP-2026-0007`, the year of the site's clock); `reference_of( WP_Post $post ): string`.
  - `is_confirmed( int $post_id ): bool`; `confirm( int $post_id ): bool` (false when confirmed already); `verify_url( int $post_id ): string`; `verify_signature( int $post_id ): string`.
  - `applications( array $states, int $limit = 0 ): WP_Post[]` (oldest first, by ID within one second); `count_in( array $states ): int`; `waiting_count(): int` (the four open states).

- [ ] **Step 1: Write the shared stand-ins and the failing suite**

Create `bin/stubs/mentor-applications.php`:

```php
<?php
/**
 * The small WordPress the mentor application suites run in (1.122.31).
 *
 * bin/test-mentor-application.php, bin/test-mentor-application-form.php,
 * bin/test-mentor-application-queue.php and bin/test-mentor-application-feed.php share it: posts and
 * their meta, options and transients, accounts and their meta, the hooks a class adds, the cron a
 * class books, nonces, redirects and `wp_die()` as exceptions, and the site's clock. What each
 * suite needs beyond it (the mail layer, the settings, a screen) the suite stands in itself, so a
 * check can read what was sent and to whom.
 *
 * **The clock.** `$GLOBALS['now']` is the moment in UTC, and `$GLOBALS['site_offset']` how far the
 * site's clock is ahead of UTC: `wp_date()` formats on the site's clock, as core's does, and a post
 * is dated on it (`post_date`) and in UTC (`post_date_gmt`). The plugin's own code reads `time()`,
 * PHP's real clock, where it stamps an event; a check that needs an event in the past writes it
 * there itself.
 *
 * Every address is example.test's, and every name an invented one.
 *
 * Loaded with `require_once __DIR__ . '/stubs/mentor-applications.php';` from a suite's header.
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['opts']        = array();
$GLOBALS['transients']  = array();
$GLOBALS['posts']       = array();
$GLOBALS['pmeta']       = array();
$GLOBALS['users']       = array();
$GLOBALS['umeta']       = array();
$GLOBALS['hooks']       = array();
$GLOBALS['cron']        = array();
$GLOBALS['post_types']  = array();
$GLOBALS['slugs']       = array();
$GLOBALS['uid']         = 0;
$GLOBALS['manage']      = array();
$GLOBALS['next_id']     = 500;
$GLOBALS['now']         = gmmktime( 10, 0, 0, 10, 16, 2026 );
$GLOBALS['site_offset'] = 2 * HOUR_IN_SECONDS;
$GLOBALS['insert_fails'] = false;

class WP_Error {
	public $code    = '';
	public $message = '';
	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->message;
	}
}

class WP_Post {
	public $ID = 0, $post_type = 'post', $post_status = 'publish', $post_author = 0, $post_title = '', $post_name = '', $post_date = '', $post_date_gmt = '', $post_content = '', $post_modified_gmt = '';
}

class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', array $roles = array() ) {
		$this->ID           = (int) $id;
		$this->display_name = $name;
		$this->user_email   = $email;
		$this->user_login   = strtolower( preg_replace( '/[^A-Za-z]/', '', $name ) );
		$this->roles        = $roles;
	}
	public function exists() {
		return $this->ID > 0;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/** What a redirect, `wp_die()` or a refused nonce ends a request with, caught by `run()`. */
class WPCPM_Test_Stop extends Exception {}

require_once __DIR__ . '/caps.php';
require_once __DIR__ . '/cleaners.php';
require_once __DIR__ . '/specialchars.php';

/* ---- words and escaping -------------------------------------------------------------------- */

function __( $text, $domain = null ) {
	return $text;
}
function _n( $single, $plural, $number, $domain = null ) {
	return 1 === (int) $number ? $single : $plural;
}
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}
function esc_html__( $text, $domain = null ) {
	return esc_html( $text );
}
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}
function esc_attr__( $text, $domain = null ) {
	return esc_attr( $text );
}
function esc_textarea( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}
function esc_url( $url ) {
	return str_replace( '&', '&#038;', (string) $url );
}
function esc_url_raw( $url, $protocols = null ) {
	return preg_match( '#^https?://[^\s<>"]+$#i', (string) $url ) ? (string) $url : '';
}
function wp_kses( $html, $allowed = array() ) {
	return strip_tags( (string) $html, '<a>' );
}
function wp_strip_all_tags( $text ) {
	return trim( strip_tags( (string) $text ) );
}
function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, (int) $decimals );
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function sanitize_email( $email ) {
	return trim( (string) $email );
}
function is_email( $email ) {
	return (bool) preg_match( '/^[^@\s]+@[^@\s.]+\.[^@\s]+$/', (string) $email );
}
function sanitize_html_class( $class ) {
	return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
}
function wp_unslash( $value ) {
	return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
}
function wp_slash( $value ) {
	return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value );
}
function stripslashes_deep( $value ) {
	return is_array( $value ) ? array_map( 'stripslashes_deep', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
}
function absint( $value ) {
	return abs( (int) $value );
}
function checked( $one, $two = true, $echo = true ) {
	return (string) $one === (string) $two ? ' checked="checked"' : '';
}
function selected( $one, $two = true, $echo = true ) {
	return (string) $one === (string) $two ? " selected='selected'" : '';
}
function remove_accents( $text, $locale = '' ) {
	return (string) $text;
}
function wp_json_encode( $data, $flags = 0 ) {
	return json_encode( $data, $flags );
}

/* ---- the clock ------------------------------------------------------------------------------ */

function wp_date( $format, $timestamp = null, $timezone = null ) {
	return gmdate( $format, ( null === $timestamp ? (int) $GLOBALS['now'] : (int) $timestamp ) + (int) $GLOBALS['site_offset'] );
}
function current_time( $type, $gmt = false ) {
	return 'timestamp' === $type || 'U' === $type ? (int) $GLOBALS['now'] : gmdate( 'Y-m-d H:i:s', (int) $GLOBALS['now'] + ( $gmt ? 0 : (int) $GLOBALS['site_offset'] ) );
}
function human_time_diff( $from, $to = 0 ) {
	return '2 hours';
}

/* ---- addresses ------------------------------------------------------------------------------ */

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
}
function home_url( $path = '/' ) {
	return 'https://example.test' . $path;
}
function rest_url( $path = '' ) {
	return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' );
}
function wp_login_url( $redirect = '' ) {
	return 'https://example.test/wp-login.php';
}
function get_privacy_policy_url() {
	return isset( $GLOBALS['policy'] ) ? (string) $GLOBALS['policy'] : 'https://example.test/privacy/';
}
function wp_parse_url( $url, $component = -1 ) {
	return -1 === $component ? parse_url( (string) $url ) : parse_url( (string) $url, $component );
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( (array) $defaults, (array) $args );
}
function trailingslashit( $text ) {
	return rtrim( (string) $text, '/' ) . '/';
}
function add_query_arg( ...$args ) {
	$pairs = is_array( $args[0] ) ? $args[0] : array( $args[0] => $args[1] );
	$url   = is_array( $args[0] ) ? ( isset( $args[1] ) ? $args[1] : '' ) : ( isset( $args[2] ) ? $args[2] : '' );
	foreach ( $pairs as $key => $value ) {
		$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . rawurlencode( (string) $value );
	}
	return $url;
}

/* ---- hooks, blocks, cron -------------------------------------------------------------------- */

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ][] = $callback;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ][] = $callback;
}
function apply_filters( $hook, $value, ...$args ) {
	return $value;
}
function do_action( $hook, ...$args ) {}
function add_shortcode( $tag, $callback ) {
	$GLOBALS['hooks'][ 'shortcode:' . $tag ][] = $callback;
}
function register_post_type( $type, $args = array() ) {
	$GLOBALS['post_types'][ $type ] = $args;
	return (object) $args;
}
function register_block_type( $dir, $args = array() ) {
	$GLOBALS['hooks'][ 'block:' . basename( (string) $dir ) ][] = $args;
	return true;
}
function wp_next_scheduled( $hook ) {
	return isset( $GLOBALS['cron'][ $hook ] ) ? $GLOBALS['cron'][ $hook ]['at'] : false;
}
function wp_schedule_event( $time, $recurrence, $hook, $args = array() ) {
	$GLOBALS['cron'][ $hook ] = array(
		'at'    => (int) $time,
		'every' => $recurrence,
	);
	return true;
}
function wp_clear_scheduled_hook( $hook ) {
	unset( $GLOBALS['cron'][ $hook ] );
	return 1;
}

/* ---- nonces, redirects, the end of a request ------------------------------------------------ */

function wp_create_nonce( $action = -1 ) {
	return 'nonce-' . $action;
}
function wp_verify_nonce( $nonce, $action = -1 ) {
	return 'nonce-' . $action === (string) $nonce ? 1 : false;
}
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
	$field = '<input type="hidden" name="' . $name . '" value="' . wp_create_nonce( $action ) . '" />';
	if ( $echo ) {
		echo $field; // phpcs:ignore -- a stand-in.
	}
	return $field;
}
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	$nonce = isset( $_REQUEST[ $query_arg ] ) ? (string) $_REQUEST[ $query_arg ] : '';
	if ( ! wp_verify_nonce( $nonce, $action ) ) {
		throw new WPCPM_Test_Stop( 'nonce refused: ' . $action );
	}
	return 1;
}
function wp_safe_redirect( $location, $status = 302 ) {
	throw new WPCPM_Test_Stop( 'redirect: ' . $location );
}
function wp_die( $message = '', $title = '', $args = array() ) {
	throw new WPCPM_Test_Stop( 'wp_die: ' . ( is_scalar( $message ) ? $message : '' ) );
}
function nocache_headers() {
	$GLOBALS['nocache'] = isset( $GLOBALS['nocache'] ) ? $GLOBALS['nocache'] + 1 : 1;
}
function wp_hash( $data, $scheme = 'auth' ) {
	return md5( 'test-salt|' . (string) $data );
}
function wp_generate_password( $length = 12, $special = true, $extra = false ) {
	$GLOBALS['password'] = isset( $GLOBALS['password'] ) ? $GLOBALS['password'] + 1 : 1;
	return substr( str_repeat( 'AbCdEf0123456789', 4 ), 0, (int) $length - 4 ) . sprintf( '%04d', $GLOBALS['password'] );
}

/**
 * Run one request step and say how it ended: its output, and the redirect or the stop it ended with.
 *
 * @param callable $step The step.
 * @return array{html: string, stop: string}
 */
function run( callable $step ) {
	ob_start();
	$stop = '';
	try {
		call_user_func( $step );
	} catch ( WPCPM_Test_Stop $e ) {
		$stop = $e->getMessage();
	}
	return array(
		'html' => (string) ob_get_clean(),
		'stop' => $stop,
	);
}

/* ---- options and transients ----------------------------------------------------------------- */

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default;
}
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['opts'][ $key ] = $value;
	return true;
}
function add_option( $key, $value, $deprecated = '', $autoload = null ) {
	if ( array_key_exists( $key, $GLOBALS['opts'] ) ) {
		return false;
	}
	$GLOBALS['opts'][ $key ] = $value;
	return true;
}
function delete_option( $key ) {
	unset( $GLOBALS['opts'][ $key ] );
	return true;
}
function get_transient( $key ) {
	return array_key_exists( $key, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $key ] : false;
}
function set_transient( $key, $value, $expiration = 0 ) {
	$GLOBALS['transients'][ $key ] = $value;
	return true;
}
function delete_transient( $key ) {
	unset( $GLOBALS['transients'][ $key ] );
	return true;
}

/* ---- posts and their meta ------------------------------------------------------------------- */

function wp_insert_post( $args, $wp_error = false ) {
	if ( $GLOBALS['insert_fails'] ) {
		return $wp_error ? new WP_Error( 'wpcpm_test_insert', 'refused' ) : 0;
	}
	$post                = new WP_Post();
	$post->ID            = $GLOBALS['next_id']++;
	$post->post_type     = isset( $args['post_type'] ) ? $args['post_type'] : 'post';
	$post->post_status   = isset( $args['post_status'] ) ? $args['post_status'] : 'publish';
	$post->post_author   = isset( $args['post_author'] ) ? (int) $args['post_author'] : 0;
	// Unslashed, as core's insert unslashes what it is handed, and a bare "&" written as kses writes it.
	$post->post_title    = str_replace( '&', '&amp;', stripslashes( isset( $args['post_title'] ) ? (string) $args['post_title'] : '' ) );
	$post->post_name     = isset( $args['post_name'] ) ? (string) $args['post_name'] : '';
	$post->post_content  = isset( $args['post_content'] ) ? (string) $args['post_content'] : '';
	$post->post_date     = gmdate( 'Y-m-d H:i:s', (int) $GLOBALS['now'] + (int) $GLOBALS['site_offset'] );
	$post->post_date_gmt = gmdate( 'Y-m-d H:i:s', (int) $GLOBALS['now'] );
	$GLOBALS['posts'][ $post->ID ] = $post;
	if ( '' !== $post->post_name ) {
		$GLOBALS['slugs'][ $post->ID ] = $post->post_name;
	}
	return $post->ID;
}
function get_post( $id ) {
	return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null;
}
function get_post_status( $id ) {
	$post = get_post( $id );
	return $post ? $post->post_status : false;
}
function get_post_time( $format = 'U', $gmt = false, $post = null ) {
	if ( ! $post instanceof WP_Post ) {
		return false;
	}
	$time = (int) strtotime( $post->post_date_gmt . ' UTC' );
	return 'U' === $format ? ( $gmt ? $time : $time + (int) $GLOBALS['site_offset'] ) : gmdate( $format, $gmt ? $time : $time + (int) $GLOBALS['site_offset'] );
}
function get_permalink( $id ) {
	return 'https://example.test/' . ( isset( $GLOBALS['slugs'][ (int) $id ] ) ? $GLOBALS['slugs'][ (int) $id ] . '/' : '?p=' . (int) $id );
}
function get_page_by_path( $path ) {
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( 'page' === $post->post_type && 'trash' !== $post->post_status && isset( $GLOBALS['slugs'][ $post->ID ] ) && $path === $GLOBALS['slugs'][ $post->ID ] ) {
			return $post;
		}
	}
	return null;
}
function is_page( $id = 0 ) {
	return isset( $GLOBALS['on_page'] ) && (int) $id === (int) $GLOBALS['on_page'];
}
function wp_delete_post( $id, $force = false ) {
	if ( ! isset( $GLOBALS['posts'][ (int) $id ] ) ) {
		return false;
	}
	$post = $GLOBALS['posts'][ (int) $id ];
	unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] );
	return $post;
}
function get_post_meta( $id, $key = '', $single = false ) {
	$rows = isset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $key ] : array();
	if ( $single ) {
		return $rows ? $rows[0] : '';
	}
	return $rows;
}
// As core's: what is written is unslashed first, at any depth.
function add_post_meta( $id, $key, $value, $unique = false ) {
	$GLOBALS['pmeta'][ (int) $id ][ $key ][] = stripslashes_deep( $value );
	return true;
}
function update_post_meta( $id, $key, $value ) {
	$GLOBALS['pmeta'][ (int) $id ][ $key ] = array( stripslashes_deep( $value ) );
	return true;
}
function delete_post_meta( $id, $key, $value = '' ) {
	unset( $GLOBALS['pmeta'][ (int) $id ][ $key ] );
	return true;
}

/**
 * `get_posts()` as the mentor application classes ask it: one type, a status, a state's IN clause,
 * oldest first by date then ID, a ceiling, IDs or posts. Anything else it is asked is recorded in
 * `$GLOBALS['unmodeled']`, which a suite checks is empty at its end.
 *
 * @param array $args The query.
 * @return array
 */
function get_posts( $args = array() ) {
	$known = array( 'post_type', 'post_status', 'numberposts', 'posts_per_page', 'orderby', 'order', 'fields', 'meta_query', 'suppress_filters', 'no_found_rows' );
	foreach ( array_keys( (array) $args ) as $key ) {
		if ( ! in_array( $key, $known, true ) ) {
			$GLOBALS['unmodeled'][] = 'get_posts: ' . $key;
		}
	}
	$out = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
			continue;
		}
		if ( isset( $args['post_status'] ) && 'any' !== $args['post_status'] && $post->post_status !== $args['post_status'] ) {
			continue;
		}
		foreach ( isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array() as $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) {
				continue;
			}
			$have = get_post_meta( $post->ID, $clause['key'], true );
			if ( isset( $clause['compare'] ) && 'IN' === $clause['compare'] ? ! in_array( $have, (array) $clause['value'], true ) : $have !== $clause['value'] ) {
				continue 2;
			}
		}
		$out[] = $post;
	}
	usort(
		$out,
		function ( $a, $b ) {
			$by_date = strcmp( $a->post_date_gmt, $b->post_date_gmt );
			return 0 !== $by_date ? $by_date : $a->ID - $b->ID;
		}
	);
	$limit = isset( $args['numberposts'] ) ? (int) $args['numberposts'] : ( isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 5 );
	if ( $limit > 0 ) {
		$out = array_slice( $out, 0, $limit );
	}
	if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
		return array_map(
			function ( $post ) {
				return $post->ID;
			},
			$out
		);
	}
	return $out;
}

/* ---- accounts ------------------------------------------------------------------------------- */

/**
 * Add an account to the site.
 *
 * @param int    $id    Its ID.
 * @param string $name  Its name.
 * @param string $email Its address.
 * @param array  $roles Its roles.
 * @param array  $meta  Its meta.
 * @return WP_User
 */
function an_account( $id, $name, $email, array $roles = array(), array $meta = array() ) {
	$GLOBALS['users'][ (int) $id ] = new WP_User( $id, $name, $email, $roles );
	foreach ( $meta as $key => $value ) {
		$GLOBALS['umeta'][ (int) $id ][ $key ] = $value;
	}
	return $GLOBALS['users'][ (int) $id ];
}
function get_userdata( $id ) {
	return isset( $GLOBALS['users'][ (int) $id ] ) ? $GLOBALS['users'][ (int) $id ] : false;
}
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( ( 'email' === $field && strtolower( $user->user_email ) === strtolower( (string) $value ) ) || ( 'id' === $field && $user->ID === (int) $value ) || ( 'login' === $field && $user->user_login === (string) $value ) ) {
			return $user;
		}
	}
	return false;
}
function get_current_user_id() {
	return (int) $GLOBALS['uid'];
}
function wp_get_current_user() {
	return isset( $GLOBALS['users'][ (int) $GLOBALS['uid'] ] ) ? $GLOBALS['users'][ (int) $GLOBALS['uid'] ] : new WP_User();
}
function get_user_meta( $id, $key = '', $single = false ) {
	$value = isset( $GLOBALS['umeta'][ (int) $id ][ $key ] ) ? $GLOBALS['umeta'][ (int) $id ][ $key ] : '';
	return $single ? $value : ( '' === $value ? array() : array( $value ) );
}
function update_user_meta( $id, $key, $value ) {
	$GLOBALS['umeta'][ (int) $id ][ $key ] = $value;
	return true;
}
function delete_user_meta( $id, $key, $value = '' ) {
	unset( $GLOBALS['umeta'][ (int) $id ][ $key ] );
	return true;
}

/**
 * `get_users()` as the duplicate check asks it: the accounts holding a role, or a meta key, or
 * either (`relation` OR across a role and a meta key is asked as two calls by the plugin), with
 * `fields` all or ID.
 *
 * @param array $args The query.
 * @return array
 */
function get_users( $args = array() ) {
	$out = array();
	foreach ( $GLOBALS['users'] as $user ) {
		if ( isset( $args['role__in'] ) && ! array_intersect( (array) $args['role__in'], $user->roles ) ) {
			continue;
		}
		if ( isset( $args['meta_key'] ) && '' === (string) get_user_meta( $user->ID, $args['meta_key'], true ) ) {
			continue;
		}
		if ( isset( $args['capability'] ) && ! user_can( $user, $args['capability'] ) ) {
			continue;
		}
		$out[] = $user;
	}
	if ( isset( $args['fields'] ) && 'ID' === $args['fields'] ) {
		return array_map(
			function ( $user ) {
				return (string) $user->ID;
			},
			$out
		);
	}
	return $out;
}
```

Create `bin/test-mentor-application.php`:

```php
<?php
/**
 * The mentor application as the site keeps it (1.122.31): `WPCPM_Mentor_Application`.
 *
 * What this pins, and why:
 *
 * - **A private row nobody reaches but the Mentors screen.** The post type is fourteen characters,
 *   inside WordPress's twenty, and is public nowhere: not queried, not searched, not in the REST
 *   API, not in a menu, with a capability type no role holds.
 * - **The nine states of the design, and the four the queue lists** (section 3, decision 2).
 * - **A submission is stored whole.** The answers as typed, a backslash included; the reference
 *   `MAPP-<year>-<id>`, the year on the site's clock; the consent evidence; the checks' signals once
 *   each; the address only as a hash, lowercased; a row in the history; nobody as the author.
 * - **Every decision is a state and a dated row with who took it**, in one call, and the history
 *   reads oldest first, leaving out a row stored in another shape.
 * - **The confirming link is signed and bound to the address**: it carries the application and a
 *   signature, and the signature changes with the address's hash. Confirming stamps once.
 * - **The lists are oldest first, by ID within one second**, and a state that is not one reads no
 *   rows rather than every row.
 *
 * Run from the plugin root:  php bin/test-mentor-application.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/mentor-applications.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application.php';

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

/** A clean site. */
function fresh() {
	$GLOBALS['posts']        = array();
	$GLOBALS['pmeta']        = array();
	$GLOBALS['hooks']        = array();
	$GLOBALS['post_types']   = array();
	$GLOBALS['insert_fails'] = false;
	$GLOBALS['next_id']      = 500;
	$GLOBALS['now']          = gmmktime( 10, 0, 0, 10, 16, 2026 );
}

/** The answers a good application carries. */
function answers( array $more = array() ) {
	return array_merge(
		array(
			'name'    => 'Ada Example',
			'profile' => 'ada-example',
			'email'   => 'Ada@Example.test',
		),
		$more
	);
}

echo "=== The post type ===\n";

fresh();
WPCPM_Mentor_Application::init();
ck( 'init hooks the post type on init', isset( $GLOBALS['hooks']['init'] ) ? $GLOBALS['hooks']['init'] : null, array( array( 'WPCPM_Mentor_Application', 'register_post_type' ) ) );

WPCPM_Mentor_Application::register_post_type();
$type = isset( $GLOBALS['post_types']['wpcpm_ment_app'] ) ? $GLOBALS['post_types']['wpcpm_ment_app'] : array();
ck( 'the post type is wpcpm_ment_app, inside the twenty characters register_post_type() allows', array( WPCPM_Mentor_Application::POST_TYPE, strlen( WPCPM_Mentor_Application::POST_TYPE ) <= 20 ), array( 'wpcpm_ment_app', true ) );
ck(
	'and public nowhere: not queried, not searched, no screen, no menu, no REST, no archive, no address, with a capability type no role holds',
	array( $type['public'], $type['publicly_queryable'], $type['exclude_from_search'], $type['show_ui'], $type['show_in_menu'], $type['show_in_rest'], $type['has_archive'], $type['rewrite'], $type['query_var'], $type['capability_type'] ),
	array( false, false, true, false, false, false, false, false, false, array( 'wpcpm_ment_app', 'wpcpm_ment_apps' ) )
);

echo "\n=== The states ===\n";

ck( 'nine states, in the design\'s order', WPCPM_Mentor_Application::states(), array( 'new', 'held', 'spam', 'info', 'second', 'selected', 'active', 'declined', 'lapsed' ) );
ck( 'and the queue lists four of them', WPCPM_Mentor_Application::open_states(), array( 'new', 'held', 'info', 'second' ) );

echo "\n=== Storing a submission ===\n";

fresh();
$evidence = array(
	'sentence' => 'I confirm.',
	'at'       => 1,
);
$id       = WPCPM_Mentor_Application::store( answers( array( 'anything' => 'C:\\path & "quotes" <b>' ) ), 'new', array( 'links', 'links', 'duplicate' ), $evidence );
$post     = get_post( $id );

ck( 'a submission is a private row of the type, authored by nobody, titled with the name', array( $id, $post->post_type, $post->post_status, $post->post_author, $post->post_title ), array( 500, 'wpcpm_ment_app', 'private', 0, 'Ada Example' ) );
ck( 'the answers are kept as typed, a backslash and a quote among them', WPCPM_Mentor_Application::fields_of( $post ), answers( array( 'anything' => 'C:\\path & "quotes" <b>' ) ) );
ck( 'its state, its reference on the site\'s clock, its consent and its signals once each', array( WPCPM_Mentor_Application::state_of( $post ), WPCPM_Mentor_Application::reference_of( $post ), get_post_meta( $id, WPCPM_Mentor_Application::META_CONSENT, true ), WPCPM_Mentor_Application::signals_of( $post ) ), array( 'new', 'MAPP-2026-0500', $evidence, array( 'links', 'duplicate' ) ) );
ck( 'the address only as a hash of it lowercased', get_post_meta( $id, WPCPM_Mentor_Application::META_EMAIL, true ), wp_hash( 'ada@example.test' ) );
ck( 'and one row in its history: submitted, by nobody', array_map( function ( $row ) { return array( $row['event'], $row['actor'], $row['note'] ); }, WPCPM_Mentor_Application::events( $id ) ), array( array( 'submitted', 0, '' ) ) );

$GLOBALS['now'] = gmmktime( 23, 30, 0, 12, 31, 2026 );
$late           = WPCPM_Mentor_Application::store( answers(), 'held', array(), array() );
ck( 'the reference takes the year of the site\'s clock, already 2027 at 23:30 UTC on December 31', WPCPM_Mentor_Application::reference_of( get_post( $late ) ), 'MAPP-2027-0501' );

$odd = WPCPM_Mentor_Application::store( answers( array( 'name' => '' ) ), 'selected', array(), array() );
ck( 'a state the form does not file in is held, and a row with no name is titled so', array( WPCPM_Mentor_Application::state_of( get_post( $odd ) ), get_post( $odd )->post_title ), array( 'held', 'Application with no name' ) );

$GLOBALS['insert_fails'] = true;
ck( 'when WordPress refuses the row, nothing is stored and the answer is 0', array( WPCPM_Mentor_Application::store( answers(), 'new', array(), array() ), count( $GLOBALS['posts'] ) ), array( 0, 3 ) );

echo "\n=== Decisions and their history ===\n";

fresh();
$id = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
ck( 'a decision moves the state and writes its row, with who and what they wrote', array( WPCPM_Mentor_Application::decide( $id, 'second', 'second opinion', 7, "Ask Ben.\nHe knows Polyglots.", 1000 ), WPCPM_Mentor_Application::state_of( get_post( $id ) ), array_slice( WPCPM_Mentor_Application::events( $id ), 0, 1 ) ), array( true, 'second', array( array( 'event' => 'second opinion', 'at' => 1000, 'actor' => 7, 'note' => "Ask Ben.\nHe knows Polyglots." ) ) ) );
ck( 'a state that is not one is refused and changes nothing', array( WPCPM_Mentor_Application::decide( $id, 'approved', 'approved', 7 ), WPCPM_Mentor_Application::state_of( get_post( $id ) ) ), array( false, 'second' ) );

add_post_meta( $id, WPCPM_Mentor_Application::META_EVENT, 'not a row' );
add_post_meta( $id, WPCPM_Mentor_Application::META_EVENT, array( 'event' => 'no time' ) );
ck( 'the history reads oldest first and leaves out a row stored in another shape', array_column( WPCPM_Mentor_Application::events( $id ), 'event' ), array( 'second opinion', 'submitted' ) );
WPCPM_Mentor_Application::add_event( $id, 'withdrawn', 7, '', 1000 );
ck( 'and rows of one second in the order they were written', array_column( WPCPM_Mentor_Application::events( $id ), 'event' ), array( 'second opinion', 'withdrawn', 'submitted' ) );
WPCPM_Mentor_Application::add_event( $id, 'selected', 7, '', 2000 );
WPCPM_Mentor_Application::add_event( $id, 'selected', 7, '', 3000 );
ck( 'the last time of an event, of any event, and of one that never happened', array( WPCPM_Mentor_Application::last_at( $id, 'selected' ), WPCPM_Mentor_Application::last_at( $id ) >= 3000, WPCPM_Mentor_Application::last_at( $id, 'declined' ) ), array( 3000, true, 0 ) );

echo "\n=== Reading one application ===\n";

$page              = new WP_Post();
$page->ID          = 42;
$page->post_type   = 'page';
$GLOBALS['posts'][42] = $page;
ck( 'an ID that is a page is no application', array( WPCPM_Mentor_Application::application( 42 ), WPCPM_Mentor_Application::application( 999 ), WPCPM_Mentor_Application::application( $id ) instanceof WP_Post ), array( null, null, true ) );

$post = get_post( $id );
ck( 'the name, the address and the handle as stored, and the profile built from the handle', array( WPCPM_Mentor_Application::name_of( $post ), WPCPM_Mentor_Application::email_of( $post ), WPCPM_Mentor_Application::handle_of( $post ), WPCPM_Mentor_Application::profile_url( 'ada-example' ), WPCPM_Mentor_Application::profile_url( '' ) ), array( 'Ada Example', 'Ada@Example.test', 'ada-example', 'https://profiles.wordpress.org/ada-example/', '' ) );

$bad = WPCPM_Mentor_Application::store( answers( array( 'email' => 'not an address' ) ), 'new', array(), array() );
ck( 'an address that is not one reads as none', WPCPM_Mentor_Application::email_of( get_post( $bad ) ), '' );

echo "\n=== The address, confirmed by its link ===\n";

$link = WPCPM_Mentor_Application::verify_url( $id );
ck( 'the link goes to the confirming handler with the application and a signature', $link, 'https://example.test/wp-admin/admin-post.php?action=wpcpm_mentor_apply_verify&app=' . $id . '&t=' . WPCPM_Mentor_Application::verify_signature( $id ) );
$signed = WPCPM_Mentor_Application::verify_signature( $id );
update_post_meta( $id, WPCPM_Mentor_Application::META_EMAIL, WPCPM_Mentor_Application::email_hash( 'other@example.test' ) );
ck( 'and the signature is bound to the address: another address, another signature', $signed !== WPCPM_Mentor_Application::verify_signature( $id ), true );

ck( 'an address is not confirmed until the link is followed', WPCPM_Mentor_Application::is_confirmed( $id ), false );
ck( 'confirming stamps it once, with a row in the history', array( WPCPM_Mentor_Application::confirm( $id ), WPCPM_Mentor_Application::is_confirmed( $id ), WPCPM_Mentor_Application::confirm( $id ), count( array_keys( array_column( WPCPM_Mentor_Application::events( $id ), 'event' ), 'address confirmed', true ) ) ), array( true, true, false, 1 ) );

echo "\n=== The lists ===\n";

fresh();
$first  = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
$second = WPCPM_Mentor_Application::store( answers(), 'held', array(), array() );
$GLOBALS['now'] -= DAY_IN_SECONDS;
$older  = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
$GLOBALS['now'] += DAY_IN_SECONDS;
$picked = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
WPCPM_Mentor_Application::decide( $picked, 'selected', 'selected', 7 );
$spam = WPCPM_Mentor_Application::store( answers(), 'spam', array(), array() );

ck( 'the open ones, oldest first and by ID within one second', array_map( function ( $p ) { return $p->ID; }, WPCPM_Mentor_Application::applications( WPCPM_Mentor_Application::open_states() ) ), array( $older, $first, $second ) );
ck( 'with a ceiling', array_map( function ( $p ) { return $p->ID; }, WPCPM_Mentor_Application::applications( WPCPM_Mentor_Application::open_states(), 2 ) ), array( $older, $first ) );
ck( 'counted by state, and waiting means the four open states', array( WPCPM_Mentor_Application::count_in( array( 'selected' ) ), WPCPM_Mentor_Application::count_in( array( 'spam', 'selected' ) ), WPCPM_Mentor_Application::waiting_count() ), array( 1, 2, 3 ) );
ck( 'and a state that is not one reads no rows, never every row', array( WPCPM_Mentor_Application::applications( array( 'approved' ) ), WPCPM_Mentor_Application::count_in( array() ) ), array( array(), 0 ) );
ck( 'nothing was asked of WordPress that the stand-ins do not model', isset( $GLOBALS['unmodeled'] ) ? $GLOBALS['unmodeled'] : array(), array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

The Mentors screen's suite boots the module, which calls the record's `init()` from this task on, so it gains a stand-in:

```diff
diff --git a/bin/test-mentors-screen.php b/bin/test-mentors-screen.php
--- a/bin/test-mentors-screen.php
+++ b/bin/test-mentors-screen.php
@@ -112,6 +112,10 @@ class WPCPM_Call_Calendar {
 class WPCPM_Group_Sessions {
 	public static function init() {}
 }
+/** And the applications to mentor (1.122.31), whose post type it boots too. */
+class WPCPM_Mentor_Application {
+	public static function init() {}
+}
 
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php bin/test-mentor-application.php` and `php bin/test-mentors-screen.php`

```text
$ php bin/test-mentor-application.php
PHP Warning:  require_once(<plugin>/includes/modules/class-wpcpm-mentor-application.php): Failed to open stream: No such file or directory in <plugin>/bin/test-mentor-application.php on line 29
PHP Fatal error:  Uncaught Error: Failed opening required '<plugin>/includes/modules/class-wpcpm-mentor-application.php' (include_path='.:/opt/homebrew/Cellar/php/8.5.7/share/php/pear') in <plugin>/bin/test-mentor-application.php:29
[exit 255]
$ php bin/test-mentors-screen.php
ALL PASS (170 checks)
[exit 0]
```

The record suite stops at the class that is not there yet; the screen suite passes, its stand-in waiting for the boot that calls it.

- [ ] **Step 3: Write the record**

Create `includes/modules/class-wpcpm-mentor-application.php`:

```php
<?php
/**
 * Mentors module - the mentor application, as the site keeps it (1.122.31).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One row per application to mentor, from the public form to the decision (design of 16 October
 * 2026, section 3, decisions 1 and 2).
 *
 * The sponsor application's shape with the mentor's questions: a private post with the answers as
 * meta, what the form's checks raised, the consent evidence, the hash of the address that the
 * confirming link is bound to, and a history in which every decision is a dated row with who took
 * it and the note they wrote. The answers are site fields, keyed by the form's question keys
 * (`WPCPM_Mentor_Application_Form::fields()`), never by an Airtable column: the one write to
 * Airtable is M2's, on activation, and maps them there.
 *
 * **The states.** `new`, `held` and `spam` are the form's (`held` and `spam` the checks'); `info`
 * (more information asked for), `second` (a second opinion wanted, internal), `selected` (the course
 * is pending), `declined` and `active` (the account exists, which M2 sets) are a person's; `lapsed`
 * is the clock's, or an Administrator's who withdrew a selected applicant. A row filed as spam that
 * an Administrator puts back in the queue is `new` again, and one never acknowledged is sent the
 * acknowledgement then. The queue is the four open states; `selected` waits for the course; the
 * rest are closed.
 *
 * Nothing here sends anything or draws anything: the form, the queue and the feed read and write
 * through this class.
 */
final class WPCPM_Mentor_Application {

	/**
	 * The post type. Fourteen characters: `register_post_type()` refuses a name longer than twenty,
	 * and bin/test-roles.php measures every post type the plugin declares.
	 */
	const POST_TYPE = 'wpcpm_ment_app';

	const STATE_NEW      = 'new';
	const STATE_HELD     = 'held';
	const STATE_SPAM     = 'spam';
	const STATE_INFO     = 'info';
	const STATE_SECOND   = 'second';
	const STATE_SELECTED = 'selected';
	const STATE_ACTIVE   = 'active';
	const STATE_DECLINED = 'declined';
	const STATE_LAPSED   = 'lapsed';

	/** Post meta: the cleaned answers, keyed by the form's question keys. Written once, at submission. */
	const META_FIELDS = '_wpcpm_mapp_fields';

	/** Post meta: one of the `STATE_*` values. */
	const META_STATE = '_wpcpm_mapp_state';

	/** Post meta: the reference quoted in mail and on the queue, `MAPP-2026-0007`. */
	const META_REFERENCE = '_wpcpm_mapp_reference';

	/** Post meta: the consent evidence, as `WPCPM_Form_Guard::consent_evidence()` records it. */
	const META_CONSENT = '_wpcpm_mapp_consent';

	/** Post meta: what the form's checks raised, a list of slugs. */
	const META_SIGNALS = '_wpcpm_mapp_signals';

	/** Post meta: `wp_hash()` of the lowercased address, for the duplicate check and the confirming link. */
	const META_EMAIL = '_wpcpm_mapp_email';

	/** Post meta: when the applicant followed the confirming link, a Unix time; absent until then. */
	const META_VERIFIED = '_wpcpm_mapp_verified';

	/** Post meta, repeating: one row per event, `event`, `at`, `actor`, `note`. */
	const META_EVENT = '_wpcpm_mapp_event';

	/** The `admin_post_` action the confirming link lands on (`WPCPM_Mentor_Application_Form::handle_verify()`). */
	const ACTION_VERIFY = 'wpcpm_mentor_apply_verify';

	/** What the history calls each thing that happened. A decision writes one of these, and the feed counts four. */
	const EVENT_SUBMITTED = 'submitted';
	const EVENT_CONFIRMED = 'address confirmed';
	const EVENT_SELECTED  = 'selected';
	const EVENT_INFO      = 'information requested';
	const EVENT_SECOND    = 'second opinion';
	const EVENT_DECLINED  = 'declined';
	const EVENT_SPAM      = 'marked as spam';
	const EVENT_WITHDRAWN = 'withdrawn';
	const EVENT_REOPENED  = 'put back in the queue';
	const EVENT_LAPSED    = 'lapsed';
	const EVENT_ACTIVATED = 'activated';

	/** Put back in the queue and sent the acknowledgement it never had, with the confirming link. */
	const EVENT_REOPENED_ACKNOWLEDGED = 'put back in the queue, acknowledgement sent';

	/**
	 * Hooks: the post type.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	/**
	 * Register the post type: invisible everywhere, since a row holds a person's name, address and
	 * free text, and the only way to one is the Mentors screen, which asks the capability first.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Mentor applications', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Mentor application', 'wpcredits-program-manager' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title' ),
				// A capability type no role is granted, so no generic screen reaches a row.
				'capability_type'     => array( 'wpcpm_ment_app', 'wpcpm_ment_apps' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * The nine states, in the order an application can move through them.
	 *
	 * @return string[]
	 */
	public static function states() {
		return array(
			self::STATE_NEW,
			self::STATE_HELD,
			self::STATE_SPAM,
			self::STATE_INFO,
			self::STATE_SECOND,
			self::STATE_SELECTED,
			self::STATE_ACTIVE,
			self::STATE_DECLINED,
			self::STATE_LAPSED,
		);
	}

	/**
	 * The four states the Waiting for review tab lists: in front of a person, not decided yet.
	 *
	 * @return string[]
	 */
	public static function open_states() {
		return array( self::STATE_NEW, self::STATE_HELD, self::STATE_INFO, self::STATE_SECOND );
	}

	/**
	 * Store one submission and answer with its ID, or 0 when WordPress refused the row.
	 *
	 * The answers are written as a slashed copy, as core unslashes what it is handed, so a backslash
	 * an applicant typed is kept. The title is the applicant's name, for the rare screen that prints
	 * one; everything a person reads takes the name from the answers (`name_of()`).
	 *
	 * @param array  $fields  The cleaned answers, keyed by question key.
	 * @param string $state   `new`, `held` or `spam`.
	 * @param array  $signals What the checks raised.
	 * @param array  $consent The consent evidence.
	 * @return int
	 */
	public static function store( array $fields, $state, array $signals, array $consent ) {
		$name  = isset( $fields['name'] ) ? trim( (string) $fields['name'] ) : '';
		$email = isset( $fields['email'] ) ? trim( (string) $fields['email'] ) : '';

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'private',
					// Nobody: the applicant has no account, and a row authored by the Administrator who
					// happened to post the form would read on every screen as though they applied.
					'post_author' => 0,
					'post_title'  => '' !== $name ? $name : __( 'Application with no name', 'wpcredits-program-manager' ),
				)
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		$post_id = (int) $post_id;

		update_post_meta( $post_id, self::META_FIELDS, wp_slash( $fields ) );
		update_post_meta( $post_id, self::META_STATE, in_array( $state, array( self::STATE_NEW, self::STATE_HELD, self::STATE_SPAM ), true ) ? $state : self::STATE_HELD );
		update_post_meta( $post_id, self::META_REFERENCE, self::reference( $post_id ) );
		update_post_meta( $post_id, self::META_CONSENT, wp_slash( $consent ) );
		update_post_meta( $post_id, self::META_SIGNALS, array_values( array_unique( array_map( 'strval', $signals ) ) ) );
		update_post_meta( $post_id, self::META_EMAIL, self::email_hash( $email ) );

		self::add_event( $post_id, self::EVENT_SUBMITTED );

		return $post_id;
	}

	/**
	 * The hash an address is compared and signed by: lowercased, so `Ada@Example.test` and
	 * `ada@example.test` are one applicant, and '' for no address.
	 *
	 * @param string $email An address.
	 * @return string
	 */
	public static function email_hash( $email ) {
		$email = trim( (string) $email );

		return '' === $email ? '' : wp_hash( mb_strtolower( $email ) );
	}

	/**
	 * Add one row to an application's history.
	 *
	 * Repeating meta rather than one array, so two writes in the same second cannot lose each other's
	 * row; written as a slashed copy, so a note keeps a backslash.
	 *
	 * @param int    $post_id The application.
	 * @param string $event   One of the `EVENT_*` values.
	 * @param int    $actor   Who did it; 0 for the applicant or the clock.
	 * @param string $note    What they wrote, or ''.
	 * @param int    $at      When, a Unix time; 0 for now.
	 */
	public static function add_event( $post_id, $event, $actor = 0, $note = '', $at = 0 ) {
		add_post_meta(
			(int) $post_id,
			self::META_EVENT,
			wp_slash(
				array(
					'event' => sanitize_text_field( (string) $event ),
					'at'    => (int) $at > 0 ? (int) $at : time(),
					'actor' => absint( $actor ),
					'note'  => sanitize_textarea_field( (string) $note ),
				)
			)
		);
	}

	/**
	 * Move an application to a state and write the decision to its history, in one place, so no
	 * state ever changes without its row.
	 *
	 * @param int    $post_id The application.
	 * @param string $state   One of the `STATE_*` values.
	 * @param string $event   One of the `EVENT_*` values.
	 * @param int    $actor   Who decided; 0 for the clock.
	 * @param string $note    What they wrote, or ''.
	 * @param int    $at      When, a Unix time; 0 for now.
	 * @return bool False for a state that is not one.
	 */
	public static function decide( $post_id, $state, $event, $actor, $note = '', $at = 0 ) {
		if ( ! in_array( $state, self::states(), true ) ) {
			return false;
		}

		update_post_meta( (int) $post_id, self::META_STATE, $state );
		self::add_event( (int) $post_id, $event, $actor, $note, $at );

		return true;
	}

	/**
	 * An application's history, oldest first, each row whole: a row stored in another shape is left
	 * out rather than read as half a decision.
	 *
	 * @param int $post_id The application.
	 * @return array[] Each `event`, `at`, `actor`, `note`.
	 */
	public static function events( $post_id ) {
		$out = array();

		foreach ( (array) get_post_meta( (int) $post_id, self::META_EVENT, false ) as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['event'], $row['at'] ) ) {
				continue;
			}

			$out[] = array(
				'event' => (string) $row['event'],
				'at'    => (int) $row['at'],
				'actor' => isset( $row['actor'] ) ? (int) $row['actor'] : 0,
				'note'  => isset( $row['note'] ) ? (string) $row['note'] : '',
			);
		}

		// By time, and rows of one second in the order they were written, on every PHP the plugin runs
		// on: `usort()` keeps equal rows in place only from PHP 8.0.
		$at    = array_column( $out, 'at' );
		$order = array_keys( $out );

		array_multisort( $at, SORT_ASC, SORT_NUMERIC, $order, SORT_ASC, SORT_NUMERIC, $out );

		return $out;
	}

	/**
	 * When an event last happened to an application: the newest row of that event, or, with no
	 * event named, the newest row of any.
	 *
	 * @param int    $post_id The application.
	 * @param string $event   One of the `EVENT_*` values, or '' for any.
	 * @return int A Unix time, or 0 when none happened.
	 */
	public static function last_at( $post_id, $event = '' ) {
		$latest = 0;

		foreach ( self::events( $post_id ) as $row ) {
			if ( ( '' === $event || $event === $row['event'] ) && $row['at'] > $latest ) {
				$latest = $row['at'];
			}
		}

		return $latest;
	}

	/**
	 * The application a handler was posted for, or null when the ID is not one: the type is checked,
	 * not only the number, so a handler never sets a state on a page.
	 *
	 * @param int $post_id A post ID.
	 * @return WP_Post|null
	 */
	public static function application( $post_id ) {
		$post = get_post( (int) $post_id );

		return $post instanceof WP_Post && self::POST_TYPE === $post->post_type ? $post : null;
	}

	/**
	 * An application's state.
	 *
	 * @param WP_Post $post The application.
	 * @return string
	 */
	public static function state_of( WP_Post $post ) {
		return (string) get_post_meta( (int) $post->ID, self::META_STATE, true );
	}

	/**
	 * The answers as stored, keyed by question key.
	 *
	 * @param WP_Post $post The application.
	 * @return array
	 */
	public static function fields_of( WP_Post $post ) {
		$fields = get_post_meta( (int) $post->ID, self::META_FIELDS, true );

		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * The applicant's name as they typed it, or '' when the answers hold none.
	 *
	 * @param WP_Post $post The application.
	 * @return string
	 */
	public static function name_of( WP_Post $post ) {
		$fields = self::fields_of( $post );

		return isset( $fields['name'] ) ? trim( (string) $fields['name'] ) : '';
	}

	/**
	 * The applicant's address, or '' when there is not a usable one.
	 *
	 * @param WP_Post $post The application.
	 * @return string
	 */
	public static function email_of( WP_Post $post ) {
		$fields = self::fields_of( $post );
		$email  = isset( $fields['email'] ) ? sanitize_email( (string) $fields['email'] ) : '';

		return is_email( $email ) ? $email : '';
	}

	/**
	 * The applicant's WordPress.org username, as the form normalized it.
	 *
	 * @param WP_Post $post The application.
	 * @return string
	 */
	public static function handle_of( WP_Post $post ) {
		$fields = self::fields_of( $post );

		return isset( $fields['profile'] ) ? (string) $fields['profile'] : '';
	}

	/**
	 * The profile's address, built from the handle, or '' with none.
	 *
	 * @param string $handle A WordPress.org username.
	 * @return string
	 */
	public static function profile_url( $handle ) {
		$handle = trim( (string) $handle );

		return '' === $handle ? '' : 'https://profiles.wordpress.org/' . rawurlencode( $handle ) . '/';
	}

	/**
	 * What the form's checks raised against an application.
	 *
	 * @param WP_Post $post The application.
	 * @return string[]
	 */
	public static function signals_of( WP_Post $post ) {
		$stored = get_post_meta( (int) $post->ID, self::META_SIGNALS, true );

		return is_array( $stored ) ? array_values( array_unique( array_filter( array_map( 'strval', $stored ), 'strlen' ) ) ) : array();
	}

	/**
	 * One application's reference: derived from the post ID and the year it arrived, so it needs no
	 * counter and no lock. Not a secret and not a key to anything.
	 *
	 * @param int $post_id The application.
	 * @return string
	 */
	public static function reference( $post_id ) {
		$post = get_post( (int) $post_id );
		$year = $post instanceof WP_Post && '' !== (string) $post->post_date ? substr( (string) $post->post_date, 0, 4 ) : wp_date( 'Y' );

		return sprintf( 'MAPP-%1$s-%2$04d', $year, (int) $post_id );
	}

	/**
	 * One application's reference, the stored one first.
	 *
	 * @param WP_Post $post The application.
	 * @return string
	 */
	public static function reference_of( WP_Post $post ) {
		$stored = (string) get_post_meta( (int) $post->ID, self::META_REFERENCE, true );

		return '' !== $stored ? $stored : self::reference( (int) $post->ID );
	}

	/**
	 * Whether the applicant has confirmed the address by following the acknowledgement's link. A
	 * decision that sends an email asks this first (design, section 3, decision 4).
	 *
	 * @param int $post_id The application.
	 * @return bool
	 */
	public static function is_confirmed( $post_id ) {
		return '' !== trim( (string) get_post_meta( (int) $post_id, self::META_VERIFIED, true ) );
	}

	/**
	 * Stamp the address as confirmed, once, with a row in the history.
	 *
	 * @param int $post_id The application.
	 * @return bool False when it was confirmed already.
	 */
	public static function confirm( $post_id ) {
		if ( self::is_confirmed( $post_id ) ) {
			return false;
		}

		update_post_meta( (int) $post_id, self::META_VERIFIED, time() );
		self::add_event( (int) $post_id, self::EVENT_CONFIRMED );

		return true;
	}

	/**
	 * The link in the acknowledgement that confirms the address: signed with the site's salts and
	 * bound to the stored hash of the address, as the institution application's is, so it cannot be
	 * made without the salts and stops working if the address on the row ever changes.
	 *
	 * @param int $post_id The application.
	 * @return string
	 */
	public static function verify_url( $post_id ) {
		return add_query_arg(
			array(
				'action' => self::ACTION_VERIFY,
				'app'    => (int) $post_id,
				't'      => self::verify_signature( $post_id ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * The signature half of a confirming link.
	 *
	 * @param int $post_id The application.
	 * @return string
	 */
	public static function verify_signature( $post_id ) {
		$hash = (string) get_post_meta( (int) $post_id, self::META_EMAIL, true );

		return substr( wp_hash( 'wpcpm-mentor-application-verify|' . (int) $post_id . '|' . $hash ), 0, 32 );
	}

	/**
	 * The applications in the given states, oldest first and by ID within one second, so every read
	 * of a list comes back in one order.
	 *
	 * @param array $states States to include; anything that is not a state is dropped, and none means no rows.
	 * @param int   $limit  Most rows to read, or 0 for every one.
	 * @return WP_Post[]
	 */
	public static function applications( array $states, $limit = 0 ) {
		$found = array();

		foreach ( self::query( $states, 'all', $limit ) as $post ) {
			if ( $post instanceof WP_Post ) {
				$found[] = $post;
			}
		}

		return $found;
	}

	/**
	 * How many applications are in the given states, read as IDs.
	 *
	 * @param array $states States to count.
	 * @return int
	 */
	public static function count_in( array $states ) {
		return count( self::query( $states, 'ids', 0 ) );
	}

	/**
	 * How many applications wait in the queue: the tab's count, the dashboard tile's and the
	 * screen's choice of tab.
	 *
	 * @return int
	 */
	public static function waiting_count() {
		return self::count_in( self::open_states() );
	}

	/**
	 * The query behind `applications()` and `count_in()`.
	 *
	 * @param array  $states States to include.
	 * @param string $fields `all` for posts, `ids` for IDs.
	 * @param int    $limit  Most rows, or 0 for every one.
	 * @return array
	 */
	private static function query( array $states, $fields, $limit ) {
		$wanted = array_values( array_intersect( self::states(), array_map( 'strval', $states ) ) );

		if ( empty( $wanted ) ) {
			return array();
		}

		return (array) get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'numberposts' => $limit > 0 ? (int) $limit : -1,
				'orderby'     => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
				),
				'fields'      => 'ids' === $fields ? 'ids' : '',
				'meta_query'  => array(
					array(
						'key'     => self::META_STATE,
						'value'   => $wanted,
						'compare' => 'IN',
					),
				),
			)
		);
	}
}
```

Load it in the plugin and in the uninstaller, and boot it with the Mentors module:

```diff
diff --git a/includes/modules/class-wpcpm-mentors.php b/includes/modules/class-wpcpm-mentors.php
--- a/includes/modules/class-wpcpm-mentors.php
+++ b/includes/modules/class-wpcpm-mentors.php
@@ -122,6 +122,8 @@ class WPCPM_Mentors extends WPCPM_Sync_Module {
 		WPCPM_Mentor_Calls::init();
 		WPCPM_Call_Calendar::init();
 		WPCPM_Group_Sessions::init();
+		// The applications to mentor (1.122.31): the post type they are kept in.
+		WPCPM_Mentor_Application::init();
 
 		// The hourly call reminders, put back on the clock whenever they are missing, as the
 		// sync above does for its own job: the activation hook is the only other caller and
diff --git a/uninstall.php b/uninstall.php
--- a/uninstall.php
+++ b/uninstall.php
@@ -101,6 +101,7 @@ require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentor-
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-call-calendar.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-group-sessions.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentor-engagement.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentor-application.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-institutions.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-countries.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-institutions-index.php';
diff --git a/wpcredits-program-manager.php b/wpcredits-program-manager.php
--- a/wpcredits-program-manager.php
+++ b/wpcredits-program-manager.php
@@ -91,6 +91,7 @@ require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-calls.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-call-calendar.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-group-sessions.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-engagement.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-countries.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php bin/test-mentor-application.php` and `php bin/test-mentors-screen.php`

```text
$ php bin/test-mentor-application.php
ALL PASS (30 checks)
[exit 0]
$ php bin/test-mentors-screen.php
ALL PASS (170 checks)
[exit 0]
```

- [ ] **Step 5: The checks every task ends with**

```text
battery done
154 classes, 14529 references checked, 87 form handlers scanned - all resolve
157 PHP files, 7 blocks, 4 track seeds, 23 fixture and tooling files, the guides and the readme: US English throughout.
260 annotations, 0 dead.
Ran 133 scripts from bin/, each in a PHP process of its own, and every one exited 0.
The battery leaves the temp directory as it found it.
78 warnings, no errors.
```

- [ ] **Step 6: Commit**

```bash
git add includes/modules/class-wpcpm-mentor-application.php bin/stubs/mentor-applications.php bin/test-mentor-application.php bin/test-mentors-screen.php includes/modules/class-wpcpm-mentors.php wpcredits-program-manager.php uninstall.php
git commit -m "Mentor applications: the application record, its nine states, its history and the link that confirms the address" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 2: Four settings on the Students and mentors tab

The form's switch, who is told of a new application, the link the selection email gives, and the days a selected applicant has before the application lapses (spec, sections 3 decision 9, 6 and 8). A new last section of the Students and mentors tab, drawn with the rows the other forms' sections use.

**Files:**
- Modify: `includes/class-wpcpm-settings.php` (`defaults()`, the `people` scope of `scopes()`, the scope the Students and mentors tab saves, the reviewer lists' cleaning, the course link's https rule, the switch among the flags, the lapse days' range)
- Modify: `includes/class-wpcpm-settings-screen.php` (the Students and mentors tab's new last section, `render_mentor_application_settings()`)
- Modify: `docs/sections/31-admin-settings.md` (`#### Mentor applications` after `#### Landing pages`)
- Generated: `docs/administrators.md`, `docs/build/administrators.html` (`php bin/build-docs.php`)
- Test: `bin/test-settings.php`, `bin/test-settings-choices.php`

**Interfaces:**
- Consumes: `WPCPM_Settings_Rows::reviewers_row( $key, $label, $value, $description, $details )`, `::text_row( ... )`, `::number_row( $key, $label, $value, $min, $max, $description )`, `::close_row( $label, $details )`; the screen's `open_section( $title, $sentence, $anchor )` and `close_section()`.
- Produces: four settings in the `people` scope: `mentor_applications_enabled` (bool, default `false`), `mentor_notify` (a comma list of addresses, cleaned as `sponsor_notify` is; `''`, the default, means every Administrator), `mentor_course_link` (an https link or `''`, the default), `mentor_lapse_days` (int, 14 to 365, default 90). The section's anchor in guide 31 is `mentor-applications`.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-settings-choices.php b/bin/test-settings-choices.php
--- a/bin/test-settings-choices.php
+++ b/bin/test-settings-choices.php
@@ -1691,5 +1691,6 @@ $sentences = array(
 	'Type the stage. The list could not be read from Airtable just now.'                                      => 1,
 	'Type one stage per line. The list could not be read from Airtable just now.'                             => 1,
-	'Type the addresses, separated by commas. No Administrator account with an email address was found.'  => 3,
+	// The agreements', the reports', the sponsors' and, since 1.122.31, the mentor applications' reviewers.
+	'Type the addresses, separated by commas. No Administrator account with an email address was found.'  => 4,
 );
 $said = array();
diff --git a/bin/test-settings.php b/bin/test-settings.php
--- a/bin/test-settings.php
+++ b/bin/test-settings.php
@@ -494,4 +494,8 @@ $probe = array(
 	'logo_max_kb'                   => '2048',
 	'offer_low_stock'               => '250',
+	// Mentor applications (1.122.31).
+	'mentor_notify'                 => 'probe-one@example.test,probe-two@example.test',
+	'mentor_course_link'            => 'https://learn.example.test/course/probe/?group=PROBE',
+	'mentor_lapse_days'             => '120',
 	// The Emails tool's contact address (1.122.19).
 	'mail_contact'                  => 'programs@example.test',
@@ -1038,4 +1042,20 @@ ck( 'and cleaned like its siblings: split, lowered, the non-addresses dropped, d
 ck( 'the table ID is saved as text', $saved['team_members_table'], 'tblX' );
 
+echo "\n=== Mentor applications settings (1.122.31) ===\n";
+
+$defaults = WPCPM_Settings::defaults();
+ck( 'the mentor form is off until an Administrator switches it on, a new application is told to every Administrator, no course link is set, and a selected application lapses after 90 days',
+	array( $defaults['mentor_applications_enabled'], $defaults['mentor_notify'], $defaults['mentor_course_link'], $defaults['mentor_lapse_days'] ),
+	array( false, '', '', 90 ) );
+
+$saved = WPCPM_Settings::save( array( 'mentor_notify' => 'Ida@Example.test, not-an-address ida@example.test', 'mentor_course_link' => ' https://learn.example.test/course/mentors/?group=EXAMPLE ', 'mentor_lapse_days' => '1000' ) );
+ck( 'the notice list is cleaned as its siblings are: split, lowered, the non-addresses dropped, duplicates gone', $saved['mentor_notify'], 'ida@example.test' );
+ck( 'the course link is kept as an https link, trimmed', $saved['mentor_course_link'], 'https://learn.example.test/course/mentors/?group=EXAMPLE' );
+ck( 'the days are clamped: 365 at most, 14 at least', array( $saved['mentor_lapse_days'], WPCPM_Settings::save( array( 'mentor_lapse_days' => '3' ) )['mentor_lapse_days'] ), array( 365, 14 ) );
+
+foreach ( array( 'http://learn.example.test/course/mentors/', 'javascript:alert(1)', 'not a link at all' ) as $typed ) {
+	ck( sprintf( 'and a course link that is not https is saved as none: %s', $typed ), WPCPM_Settings::save( array( 'mentor_course_link' => $typed ) )['mentor_course_link'], '' );
+}
+
 echo "\n=== S2: the tools switches and the low-stock default ===\n";
 $defaults = WPCPM_Settings::defaults();
@@ -1564,5 +1584,5 @@ ck( 'each tool\'s scope is named from the tool\'s own ID, as its screen posts it
 $by_design = array(
 	'connection'                 => array( 'api_token', 'schema_token', 'base_id', 'mentors_table', 'reports_table', 'students_table', 'institutions_table', 'teams_table', 'tutors_table', 'feedback_table', 'countries_table', 'team_members_table', 'sponsors_table', 'institutions_name_field', 'teams_name_field', 'countries_name_field', 'sponsors_name_field' ),
-	'people'                     => array( 'student_statuses', 'past_statuses', 'mentor_status', 'on_inactive', 'student_on_inactive', 'send_welcome_email', 'auto_sync', 'mentor_home', 'student_home' ),
+	'people'                     => array( 'student_statuses', 'past_statuses', 'mentor_status', 'on_inactive', 'student_on_inactive', 'send_welcome_email', 'auto_sync', 'mentor_home', 'student_home', 'mentor_applications_enabled', 'mentor_notify', 'mentor_course_link', 'mentor_lapse_days' ),
 	'institutions'               => array( 'applications_enabled', 'import_enabled', 'institution_provision', 'institution_home', 'institution_on_inactive', 'agreement_review_days', 'agreement_notify', 'agreement_doc_url', 'report_autodraft', 'report_autodraft_grace_days', 'report_notify' ),
 	'sponsors'                   => array( 'sponsor_applications_enabled', 'sponsor_home', 'sponsor_on_inactive', 'sponsor_notify', 'logo_max_kb', 'tools_students', 'tools_mentors', 'offer_low_stock' ),
@@ -2578,4 +2598,5 @@ $design_sections = array(
 		'Accounts'              => 'accounts',
 		'Landing pages'         => 'landing-pages',
+		'Mentor applications'   => 'mentor-applications',
 	),
 	'institutions' => array(
@@ -2672,6 +2693,6 @@ foreach ( array( 'no page' => null, 'a draft' => 'draft', 'a private page' => 'p
 }
 
-ck( 'with no published page at that path, whether there is none, a draft, a private or a trashed one, each of the 23 intros says its sentence and links nothing',
-    $unlinked, array_fill_keys( array( 'no page', 'a draft', 'a private page', 'a trashed page' ), array( 23, 0, 23 ) ) );
+ck( 'with no published page at that path, whether there is none, a draft, a private or a trashed one, each of the 24 intros says its sentence and links nothing',
+    $unlinked, array_fill_keys( array( 'no page', 'a draft', 'a private page', 'a trashed page' ), array( 24, 0, 24 ) ) );
 
 // A site that publishes the guide elsewhere says where, and a filter that answers no address leaves
@@ -2983,6 +3004,6 @@ ck( 'the model is typed while the provider lists no models, the field holding th
 ck( 'the two status lists and the pipeline stages are checkbox lists',
     $drawn_by['checklist_row'], array( 'institution_active_stages', 'past_statuses', 'student_statuses' ) );
-ck( 'the three reviewer lists are the program managers to tick, with a line for other addresses',
-    $drawn_by['reviewers_row'], array( 'agreement_notify', 'report_notify', 'sponsor_notify' ) );
+ck( 'the four reviewer lists are the program managers to tick, with a line for other addresses',
+    $drawn_by['reviewers_row'], array( 'agreement_notify', 'mentor_notify', 'report_notify', 'sponsor_notify' ) );
 // The reviewer lists are drawn by the two helpers every other field is, not by markup of their own:
 // the program managers through the checkbox list, and with nobody to list, the text input.
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php bin/test-settings.php` and `php bin/test-settings-choices.php`

```text
$ php bin/test-settings.php
PHP Warning:  Undefined array key "mentor_notify" in <plugin>/bin/test-settings.php on line 510
PHP Warning:  Undefined array key "mentor_notify" in <plugin>/bin/test-settings.php on line 512
FAIL mentor_notify survives the save
FAIL mentor_course_link survives the save
FAIL mentor_lapse_days survives the save
FAIL and every probe names a real setting
FAIL the mentor form is off until an Administrator switches it on, a new application is told to every Administrator, no course link is set, and a selected application lapses after 90 days
FAIL the notice list is cleaned as its siblings are: split, lowered, the non-addresses dropped, duplicates gone
FAIL the course link is kept as an https link, trimmed
FAIL the days are clamped: 365 at most, 14 at least
FAIL and a course link that is not https is saved as none: http://learn.example.test/course/mentors/
FAIL and a course link that is not https is saved as none: javascript:alert(1)
FAIL and a course link that is not https is saved as none: not a link at all
FAIL each scope holds the settings the design gives its tab or tool, fourteen for the Mentor Status Checker (its Slack webhook and channel since 1.122.4) and six for Need help?
FAIL the Students and mentors tab's sections are the design's, in its order, each its heading, one sentence ending in a link to its own section of the program manager guide on this site, named for a screen reader, then its rows in a form table
FAIL and every section's anchor is an id of guide 31's own part of the built guide
FAIL with no published page at that path, whether there is none, a draft, a private or a trashed one, each of the 24 intros says its sentence and links nothing
FAIL the four reviewer lists are the program managers to tick, with a line for other addresses
16 FAILURE(S)
[exit 1]
$ php bin/test-settings-choices.php
FAIL and each says what to type, and in one sentence why its list is not there: here, a read Airtable refused
1 FAILED
[exit 1]
```

- [ ] **Step 3: The settings, their section and the guide**

```diff
diff --git a/includes/class-wpcpm-settings-screen.php b/includes/class-wpcpm-settings-screen.php
--- a/includes/class-wpcpm-settings-screen.php
+++ b/includes/class-wpcpm-settings-screen.php
@@ -1080,6 +1080,71 @@ class WPCPM_Settings_Screen {
 		);
 
 		$this->close_section();
+
+		$this->render_mentor_application_settings( $settings );
+	}
+
+	/**
+	 * The Students and mentors tab's last section, the mentor application form (1.122.31): whether
+	 * it takes applications, who is told of each, the link the selection email gives, and when a
+	 * selected application lapses.
+	 *
+	 * Drawn as the Sponsors tab draws its form's switch: off by default, and the privacy policy named on
+	 * the same row because the form shows nothing without one.
+	 *
+	 * @param array $settings Current settings.
+	 */
+	private function render_mentor_application_settings( array $settings ) {
+		$this->open_section(
+			__( 'Mentor applications', 'wpcredits-program-manager' ),
+			__( 'Whether people can apply to mentor through the form on this site, who is told when they do, and what a selected applicant is sent.', 'wpcredits-program-manager' ),
+			'mentor-applications'
+		);
+
+		$policy_url   = function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '';
+		$applications = __( 'Applications from mentors', 'wpcredits-program-manager' );
+
+		printf(
+			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="mentor_applications_enabled" value="1"%2$s> %3$s</label><p class="description">%4$s</p>%5$s',
+			esc_html( $applications ),
+			checked( ! empty( $settings['mentor_applications_enabled'] ), true, false ),
+			esc_html__( 'Take mentor applications through the form on this site', 'wpcredits-program-manager' ),
+			esc_html__( 'A public page anybody can post to.', 'wpcredits-program-manager' ),
+			'' === $policy_url
+				? '<p class="description wpcpm-warning">' . esc_html__( 'No privacy policy page is set, so the form shows nothing to the public however this is switched. Publish one and choose it under Settings > Privacy.', 'wpcredits-program-manager' ) . '</p>'
+				: ''
+		);
+		WPCPM_Settings_Rows::close_row( $applications, __( 'Every application is stored for an Administrator to decide on the Mentors screen\'s Waiting for review tab, and nothing is written to Airtable. While this is off, the page shows the public nothing, and an Administrator one sentence saying why.', 'wpcredits-program-manager' ) );
+
+		WPCPM_Settings_Rows::reviewers_row(
+			'mentor_notify',
+			__( 'Who is mailed', 'wpcredits-program-manager' ),
+			isset( $settings['mentor_notify'] ) ? (string) $settings['mentor_notify'] : '',
+			__( 'Addresses told of each new mentor application; with none here, every Administrator is written to.', 'wpcredits-program-manager' ),
+			__( 'The email names the applicant and their WordPress.org profile and links the Waiting for review tab. It carries none of their other answers, which are read on the tab.', 'wpcredits-program-manager' )
+		);
+
+		WPCPM_Settings_Rows::text_row(
+			'mentor_course_link',
+			__( 'Mentors\' course invitation link', 'wpcredits-program-manager' ),
+			isset( $settings['mentor_course_link'] ) ? (string) $settings['mentor_course_link'] : '',
+			__( 'The link the selection email gives a selected applicant to join the mentors\' course.', 'wpcredits-program-manager' ),
+			'url',
+			'',
+			'',
+			__( 'Select is refused while this is empty, since the selection email would have no link to give. Only an https link is kept.', 'wpcredits-program-manager' )
+		);
+
+		WPCPM_Settings_Rows::number_row(
+			'mentor_lapse_days',
+			__( 'Selected applications lapse after (days)', 'wpcredits-program-manager' ),
+			isset( $settings['mentor_lapse_days'] ) ? (int) $settings['mentor_lapse_days'] : 90,
+			14,
+			365,
+			__( 'A selected applicant whose course has not shown after this many days is moved to Lapsed with a dated note, and Select is offered on the row again.', 'wpcredits-program-manager' )
+		);
+
+		$this->close_section();
 	}
 
 	/**
diff --git a/includes/class-wpcpm-settings.php b/includes/class-wpcpm-settings.php
--- a/includes/class-wpcpm-settings.php
+++ b/includes/class-wpcpm-settings.php
@@ -111,6 +111,15 @@ class WPCPM_Settings {
 			// Students module, mirroring the mentors settings above.
 			'student_home'                  => true,
 			'student_on_inactive'           => 'revoke',
+			// Mentor applications (1.122.31): the public form, off until an Administrator switches it
+			// on, for the reason the other two forms' switches give; who is told of a new application,
+			// every Administrator while it is empty; the link the selection email gives a selected
+			// applicant to join the mentors' course, which an Administrator fills in and no code
+			// holds; and how many days a selected applicant has before the application lapses.
+			'mentor_applications_enabled'   => false,
+			'mentor_notify'                 => '',
+			'mentor_course_link'            => '',
+			'mentor_lapse_days'             => 90,
 
 			// Institutions module. The countries table is read for the same reason as
 			// the three lookup tables above: an institution's country arrives as a bare
@@ -273,6 +282,10 @@ class WPCPM_Settings {
 				'auto_sync',
 				'mentor_home',
 				'student_home',
+				'mentor_applications_enabled',
+				'mentor_notify',
+				'mentor_course_link',
+				'mentor_lapse_days',
 			),
 			'institutions'               => array(
 				'applications_enabled',
@@ -655,7 +668,7 @@ class WPCPM_Settings {
 		// as the boxes ticked beside its Other addresses line (`agreement_notify[]` and so on), every
 		// entry split the way the text is, so both write the same list for the same addresses, and the
 		// boxes keep the order the list is stored in, as the status lists do.
-		foreach ( array( 'agreement_notify', 'report_notify', 'sponsor_notify' ) as $notify_key ) {
+		foreach ( array( 'agreement_notify', 'report_notify', 'sponsor_notify', 'mentor_notify' ) as $notify_key ) {
 			if ( ! isset( $input[ $notify_key ] ) ) {
 				continue;
 			}
@@ -670,6 +683,15 @@ class WPCPM_Settings {
 			$clean[ $notify_key ] = implode( ',', $addresses );
 		}
 
+		// The mentors' course invitation link, which the selection email gives a selected applicant: an
+		// https link or none. It goes to a stranger in an email the site sends, so a link that is not
+		// https, or not a link, is saved as none rather than mailed.
+		if ( isset( $input['mentor_course_link'] ) ) {
+			$link = is_string( $input['mentor_course_link'] ) ? esc_url_raw( trim( wp_unslash( $input['mentor_course_link'] ) ), array( 'https' ) ) : '';
+
+			$clean['mentor_course_link'] = is_string( $link ) && 0 === strpos( $link, 'https://' ) ? $link : '';
+		}
+
 		// The one connecting address whose forwarded header the application form believes.
 		// The form's per-address ceiling keys on the client address, and a header anybody can
 		// send is not an address: only the edge itself may say who is behind it. On this host
@@ -690,7 +712,7 @@ class WPCPM_Settings {
 		// not, so unticking one still switches it off through this same guarded read. Absent
 		// means "leave alone" for a patch and for a save that names no scope: a save given a
 		// scope has already read each of the scope's absent switches as off.
-		foreach ( array( 'institution_provision', 'institution_home', 'applications_enabled', 'import_enabled', 'report_autodraft', 'sponsor_home', 'tools_students', 'tools_mentors', 'sponsor_applications_enabled', 'duplicate_delete_enabled' ) as $flag ) {
+		foreach ( array( 'institution_provision', 'institution_home', 'applications_enabled', 'import_enabled', 'report_autodraft', 'sponsor_home', 'tools_students', 'tools_mentors', 'sponsor_applications_enabled', 'duplicate_delete_enabled', 'mentor_applications_enabled' ) as $flag ) {
 			if ( array_key_exists( $flag, $input ) ) {
 				$clean[ $flag ] = ! empty( $input[ $flag ] );
 			}
@@ -761,6 +783,8 @@ class WPCPM_Settings {
 			'invite_retention_days'         => array( 7, 365 ),
 			// A week's floor keeps a typo from drafting every unfinished semester tonight.
 			'report_autodraft_grace_days'   => array( 7, 365 ),
+			// Two weeks' floor keeps a typo from lapsing tonight everybody selected this month.
+			'mentor_lapse_days'             => array( 14, 365 ),
 		);
 
 		foreach ( $limits as $key => $range ) {
```

In `docs/sections/31-admin-settings.md`:

```diff
diff --git a/docs/sections/31-admin-settings.md b/docs/sections/31-admin-settings.md
--- a/docs/sections/31-admin-settings.md
+++ b/docs/sections/31-admin-settings.md
@@ -81,8 +81,8 @@ column typed: the mentors sync would ask for that column itself, and a blank one
 
 ### The Students and mentors tab
 
-Four sections: **Who is on the program**, **When someone leaves**, **Accounts** and **Landing
-pages**. The status lists are shared: a current student is anyone a mentor is currently mentoring.
+Five sections: **Who is on the program**, **When someone leaves**, **Accounts**, **Landing
+pages** and **Mentor applications**. The status lists are shared: a current student is anyone a mentor is currently mentoring.
 
 #### Who is on the program
 
@@ -156,6 +156,28 @@ when the page is missing, a sentence saying that re-activating the plugin recrea
   the same exceptions: a requested destination wins, their own profile screen stays reachable, and
   anyone who can write posts is left alone.
 
+#### Mentor applications
+
+The form people apply to mentor through, at /become-a-mentor/, since 1.122.31.
+
+- **Applications from mentors** - **Take mentor applications through the form on this site**. Off
+  by default, since it is a public page anybody can post to; its address is under the switch. Every
+  application is stored for an Administrator to decide on the Mentors screen's **Waiting for
+  review** tab, and nothing is written to Airtable; while this is off, the page shows the public
+  nothing, and an Administrator who opens it one sentence saying why. As with the other two forms,
+  the form shows nothing to the public without a published privacy policy, and the row says so.
+- **Who is mailed** - the addresses told of each new mentor application, chosen as the
+  Institutions tab's reviewers are; with none here, every Administrator is written to. The email
+  names the applicant and their WordPress.org profile and links the Waiting for review tab; it
+  carries none of their other answers. Point it at your own address before you try the form, or
+  every Administrator hears of the test.
+- **Mentors' course invitation link** - the link the selection email gives a selected applicant to
+  join the mentors' course. Its fold: Select is refused while it is empty, since the selection
+  email would have no link to give, and only an https link is kept.
+- **Selected applications lapse after (days)** - from 14 to 365, 90 by default: a selected
+  applicant whose course has not shown after this many days is moved to **Lapsed** with a dated
+  note, and Select is offered on the row again.
+
 ### The Institutions tab
 
 Five sections, headed on the screen **Applications and enrollment**, **Landing page**, **When an
```

Then build the guide: `php bin/build-docs.php`.

- [ ] **Step 4: Run them to verify they pass**

Run: `php bin/test-settings.php` and `php bin/test-settings-choices.php`

```text
$ php bin/test-settings.php
ALL PASS
[exit 0]
$ php bin/test-settings-choices.php
All passed.
[exit 0]
```

- [ ] **Step 5: The checks every task ends with**

```text
battery done
154 classes, 14533 references checked, 87 form handlers scanned - all resolve
157 PHP files, 7 blocks, 4 track seeds, 23 fixture and tooling files, the guides and the readme: US English throughout.
260 annotations, 0 dead.
Ran 133 scripts from bin/, each in a PHP process of its own, and every one exited 0.
The battery leaves the temp directory as it found it.
78 warnings, no errors.
```

- [ ] **Step 6: Commit**

```bash
git add includes/class-wpcpm-settings.php includes/class-wpcpm-settings-screen.php docs/sections/31-admin-settings.md docs/administrators.md docs/build/administrators.html bin/test-settings.php bin/test-settings-choices.php
git commit -m "Mentor applications: four settings on the Students and mentors tab, the form's switch, who is mailed, the course link and the lapse days" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 3: The five emails, an area of their own in Tools > Emails

The acknowledgement with the confirming link, the selection with the course link, the request for more information, the decline, and the Administrators' notice, as the template registry ships them (spec, section 3, decision 8, and section 8). The four to the applicant are the words the program's Airtable automations sent as they stood on 16 October 2026 (Mentors 1, 2, 5 and 4), kept word for word but for what the site does its own way: the site's signature and footer stand where each automation signed off; the acknowledgement gains the institution acknowledgement's two lines about the link that confirms the address; the course's name and invitation link are the settings' (`checker_course_title`, `mentor_course_link`) and the program's address the Contact address in emails setting's; the selection's "please reach out to {contact} or reply to this email to let us know" is two lines, "When you finish the course, please reply to this email to let us know." and "You can also write to {program_contact}.", so the paragraph reads whole while no contact address is set, the registry leaving the second line out then (the owner's ruling of 16 October 2026); a dash in an automation's text is a comma or a full stop; and none of the four carries the reference, as no automation did (the thank-you page shows it). The mentor coordinator reads the four before the form is switched on (see "After the plan"). The notice has no automation behind it: it is the sponsor application's notice, naming the applicant and their profile. Nothing sends them yet.

**Files:**
- Create: `includes/mail/templates/mentor-applications.php`
- Modify: `includes/mail/class-wpcpm-mail-catalog.php` (the area `mentor-applications`, named "Mentor applications", and its five ids)
- Modify: `includes/mail/class-wpcpm-mail-templates.php` (`AREAS` gains the area's file)
- Modify: `docs/sections/32-admin-tools.md` (six areas, and the new one's bullet)
- Generated: `docs/administrators.md`, `docs/build/administrators.html`
- Test: `bin/test-mail-catalog.php`, `bin/test-mail-templates.php`, `bin/test-emails-templates.php`

**Interfaces:**
- Consumes: `WPCPM_Mail_Templates::mail( string $id, array $values, array $extra = array() ): array`, `example_values( $id )`, `program_contact()`; `WPCPM_Mail::site_name()`; `WPCPM_Mentor_Application::ACTION_VERIFY`; the settings `checker_course_title` and, from Task 2, `mentor_course_link`.
- Produces: `WPCPM_Mail_Catalog::MODULE_MENTOR_APPS = 'mentor-applications'`; the ids `mentor-application-received`, `mentor-selected`, `mentor-more-information`, `mentor-declined` (audience `applicant`: the person has no account) and `mentor-application-notice` (audience `administrator`). Their placeholders: the acknowledgement `{site_name}`, `{verify_link}` (required) and `{applicant_email}`; the selection `{applicant_name}`, `{course_name}`, `{course_link}` (required) and `{program_contact}`; the request for more information none; the decline `{applicant_name}`; the notice `{site_name}`, `{reference}`, `{applicant_name}`, `{profile_link}` and `{queue_link}`. `{applicant_name}`, `{verify_link}`, `{applicant_email}`, `{course_link}` and `{profile_link}` go in a body alone, so the applicant's name is never in a subject, which the mail log keeps. The examples of `{course_link}` and `{program_contact}` are the settings' when they are set, so a test send from Tools > Emails shows what an applicant would follow, and an example.test address or link while they are empty. As the registry does for every email, a sentence whose placeholders are all optional and all empty is left out: with no contact address set, the selection email loses its line naming it, and keeps the request for a reply above it.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-emails-templates.php b/bin/test-emails-templates.php
--- a/bin/test-emails-templates.php
+++ b/bin/test-emails-templates.php
@@ -178,6 +178,8 @@ require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-applic
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-invite.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-interests.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-roster.php';
+// And the Mentor applications' (1.122.31): the confirming link's handler.
+require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application.php';
 
 /** The dashboards, each published at an address of its own. */
 class WPCPM_Students_Dashboard {
@@ -303,7 +305,7 @@ echo "\n=== The Templates tab ===\n";
 
 preg_match_all( '#<h2>([^<]*)</h2>#', $home, $areas );
 preg_match_all( '#<tr><td>([^<]*)</td><td>([^<]*)</td><td>([^<]*)</td><td><span class="wpcpm-emails__state wpcpm-emails__state--([a-z-]+)">([^<]*)</span></td><td>([^<]*)</td><td><a href="([^"]*)">Edit<span class="screen-reader-text"> ([^<]*)</span></a></td></tr>#', $home, $rows, PREG_SET_ORDER );
-ck( 'the templates are listed by area, in the catalog\'s order: the Invitations, the Mentor calls, the Institutions, the Semester reports and the Sponsors', $areas[1], array( 'Invitations', 'Mentor calls', 'Institutions', 'Semester reports', 'Sponsors' ) );
+ck( 'the templates are listed by area, in the catalog\'s order: the Invitations, the Mentor calls, the Institutions, the Semester reports, the Sponsors and the Mentor applications', $areas[1], array( 'Invitations', 'Mentor calls', 'Institutions', 'Semester reports', 'Sponsors', 'Mentor applications' ) );
 ck(
 	'each row names the email, who receives it, what sends it, its state and who edited it last, and links its editor by its name',
 	array_map( function ( $row ) { return array( html_entity_decode( $row[1], ENT_QUOTES ), $row[2], $row[5], $row[6], $row[7], $row[8] ); }, array_slice( $rows, 0, 5 ) ),
@@ -316,7 +318,7 @@ ck(
 	)
 );
 ck(
-	'and after the invitations, every email of the Mentor calls, the Institutions, the Semester reports and the Sponsors, each with who receives it, as shipped text, its editor linked by its id',
+	'and after the invitations, every email of the Mentor calls, the Institutions, the Semester reports, the Sponsors and the Mentor applications, each with who receives it, as shipped text, its editor linked by its id',
 	array_map( function ( $row ) { return html_entity_decode( $row[1], ENT_QUOTES ) . ' / ' . $row[2] . ' / ' . $row[5] . ' / ' . substr( $row[7], strlen( 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=' ) ); }, array_slice( $rows, 5 ) ),
 	array(
 		'Call booked, to the mentor / Mentor / Shipped text / call-booked-mentor',
@@ -374,12 +376,17 @@ ck(
 		'Codes running low / The people it concerns / Shipped text / offer-low-stock',
 		'Problem with a claimed code / Administrator / Shipped text / claim-problem',
 		'Sponsor post returned with a note / Sponsor member / Shipped text / sponsor-post-returned',
+		'Mentor application received, to the applicant / Applicant (no account) / Shipped text / mentor-application-received',
+		'Selected to mentor: the mentors\' course / Applicant (no account) / Shipped text / mentor-selected',
+		'More information asked of a mentor applicant / Applicant (no account) / Shipped text / mentor-more-information',
+		'Mentor application declined / Applicant (no account) / Shipped text / mentor-declined',
+		'New mentor application, to Administrators / Administrator / Shipped text / mentor-application-notice',
 	)
 );
 ck( 'and what sends each is the registry\'s sentence', array( html_entity_decode( $rows[0][3] ?? '', ENT_QUOTES ), html_entity_decode( $rows[5][3] ?? '', ENT_QUOTES ), html_entity_decode( $rows[24][3] ?? '', ENT_QUOTES ), html_entity_decode( $rows[44][3] ?? '', ENT_QUOTES ), html_entity_decode( $rows[59][3] ?? '', ENT_QUOTES ) ), array( WPCPM_Mail_Templates::get( 'invite-student' )['trigger'], WPCPM_Mail_Templates::get( 'call-booked-mentor' )['trigger'], WPCPM_Mail_Templates::get( 'institution-applied' )['trigger'], WPCPM_Mail_Templates::get( 'report-consent' )['trigger'], WPCPM_Mail_Templates::get( 'sponsor-post-returned' )['trigger'] ) );
 $templates_css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/emails.css' );
 preg_match( '#\.wpcpm-emails__scroll\s*\{([^{}]*)\}#', $templates_css, $scroll_rule );
-ck( 'each of the five areas has a table of its own, printed inside a box that scrolls sideways on a narrow screen, held by a position, so the page does not', array( substr_count( $home, '<table' ), preg_match_all( '#<h2>[^<]*</h2><div class="wpcpm-emails__scroll"><table class="widefat striped wpcpm-emails__templates">.*?</table></div>#s', $home ), 1 === preg_match( '/(^|;)\s*overflow-x\s*:\s*auto\s*;/', $scroll_rule[1] ?? '' ), 1 === preg_match( '/(^|;)\s*position\s*:\s*relative\s*;/', $scroll_rule[1] ?? '' ) ), array( 5, 5, true, true ) );
+ck( 'each of the six areas has a table of its own, printed inside a box that scrolls sideways on a narrow screen, held by a position, so the page does not', array( substr_count( $home, '<table' ), preg_match_all( '#<h2>[^<]*</h2><div class="wpcpm-emails__scroll"><table class="widefat striped wpcpm-emails__templates">.*?</table></div>#s', $home ), 1 === preg_match( '/(^|;)\s*overflow-x\s*:\s*auto\s*;/', $scroll_rule[1] ?? '' ), 1 === preg_match( '/(^|;)\s*position\s*:\s*relative\s*;/', $scroll_rule[1] ?? '' ) ), array( 6, 6, true, true ) );
 ck( 'no word on the tab says module, and no em or en dash', array( preg_match_all( '/\bmodules?\b/i', words_of( $home ) ), preg_match( '/\x{2013}|\x{2014}/u', $home ) ), array( 0, 0 ) );
 ck( 'an Edit link naming an email the registry does not hold opens the list', false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'call-booked' ) ), '<h2>Invitations</h2>' ), true );
 
diff --git a/bin/test-mail-catalog.php b/bin/test-mail-catalog.php
--- a/bin/test-mail-catalog.php
+++ b/bin/test-mail-catalog.php
@@ -39,7 +39,7 @@ function ck( $label, $got, $want ) {
 
 $emails = WPCPM_Mail_Catalog::emails();
 
-ck( 'the catalog names the plugin\'s 70 emails: the 42 contexts it sent before 1.122.19, the institution invitation for an agreement on file, the test of the signature and footer, the 19 Mentor calls emails of their own since 1.122.21, the 4 Institutions and 2 Sponsors emails of their own since 1.122.22, and the institution\'s invitation with no sender named, its own since 1.122.23', count( array_filter( $emails, function ( $e ) { return ! in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) ), 70 );
+ck( 'the catalog names the plugin\'s 75 emails: the 42 contexts it sent before 1.122.19, the institution invitation for an agreement on file, the test of the signature and footer, the 19 Mentor calls emails of their own since 1.122.21, the 4 Institutions and 2 Sponsors emails of their own since 1.122.22, the institution\'s invitation with no sender named, its own since 1.122.23, and the 5 Mentor applications emails since 1.122.31', count( array_filter( $emails, function ( $e ) { return ! in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) ), 75 );
 ck( 'and reads the same list twice in a request', WPCPM_Mail_Catalog::emails() === $emails, true );
 
 $bad = array();
@@ -170,7 +170,33 @@ ck(
 
 ck( 'the last member leaving an institution is named, as the Administrators\' email it is', WPCPM_Mail_Catalog::get( 'member-last' ), array( 'label' => 'Institution has no members left, to Administrators', 'module' => 'institutions', 'audience' => 'administrator', 'test' => false ) );
 
-ck( 'the areas are the ones the Log lists, in its order', array_values( WPCPM_Mail_Catalog::modules() ), array( 'Invitations', 'Mentor calls', 'Institutions', 'Semester reports', 'Sponsors', 'WordPress', 'Two Factor', 'Other' ) );
+// The Mentor applications area (1.122.31): the four emails to the person who applied to mentor and the
+// Administrators' notice. The four go to somebody with no account, so they are the applicant's, as
+// the other two forms' are: a preview names no dashboard for them, and the Log lists them as an
+// applicant's.
+ck(
+	'the Mentor applications area names its five emails, each with who receives it',
+	array_map(
+		function ( $e ) {
+			return $e['label'] . ' / ' . $e['audience'];
+		},
+		array_filter(
+			$emails,
+			function ( $e ) {
+				return 'mentor-applications' === $e['module'];
+			}
+		)
+	),
+	array(
+		'mentor-application-received' => 'Mentor application received, to the applicant / applicant',
+		'mentor-selected'             => 'Selected to mentor: the mentors\' course / applicant',
+		'mentor-more-information'     => 'More information asked of a mentor applicant / applicant',
+		'mentor-declined'             => 'Mentor application declined / applicant',
+		'mentor-application-notice'   => 'New mentor application, to Administrators / administrator',
+	)
+);
+
+ck( 'the areas are the ones the Log lists, in its order', array_values( WPCPM_Mail_Catalog::modules() ), array( 'Invitations', 'Mentor calls', 'Institutions', 'Semester reports', 'Sponsors', 'Mentor applications', 'WordPress', 'Two Factor', 'Other' ) );
 
 // Every context the plugin hands the mail layer, read from the source: a literal, or a constant the
 // same file declares, as the second argument of send(), send_to() and mail_members(), the first of
diff --git a/bin/test-mail-templates.php b/bin/test-mail-templates.php
--- a/bin/test-mail-templates.php
+++ b/bin/test-mail-templates.php
@@ -146,11 +146,20 @@ require_once __DIR__ . '/../includes/modules/class-wpcpm-institution-application
 require_once __DIR__ . '/../includes/modules/class-wpcpm-institution-invite.php';
 require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-interests.php';
 require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
+// And the Mentor applications' (1.122.31): the confirming link's handler.
+require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-application.php';
 
 /** The one setting the footer reads, Contact address in emails, as a check sets it. */
 class WPCPM_Settings {
 	public static $contact = '';
-	public static function get_value( $key, $fallback = null ) { return 'mail_contact' === $key ? self::$contact : $fallback; }
+	public static $course  = '';
+	public static function get_value( $key, $fallback = null ) {
+		$set = array(
+			'mail_contact'       => self::$contact,
+			'mentor_course_link' => self::$course,
+		);
+		return isset( $set[ $key ] ) ? $set[ $key ] : $fallback;
+	}
 }
 
 /** The dashboards a template's example links, each published at an address of its own. */
@@ -197,7 +206,7 @@ echo "=== The registry ===\n";
 $all = WPCPM_Mail_Templates::all();
 
 ck(
-	'the registry holds the Invitations, the four invitations and the institution\'s second one, then the Mentor calls, the Institutions, the Semester reports and the Sponsors, in that order',
+	'the registry holds the Invitations, the four invitations and the institution\'s second one, then the Mentor calls, the Institutions, the Semester reports, the Sponsors and the Mentor applications, in that order',
 	array_keys( $all ),
 	array(
 		'invite-student',
@@ -260,6 +269,11 @@ ck(
 		'offer-low-stock',
 		'claim-problem',
 		'sponsor-post-returned',
+		'mentor-application-received',
+		'mentor-selected',
+		'mentor-more-information',
+		'mentor-declined',
+		'mentor-application-notice',
 	)
 );
 
@@ -453,6 +467,14 @@ $shape = array(
 	'offer-low-stock'               => array( "\n", array(), array() ),
 	'claim-problem'                 => array( "\n", array(), array( 'code_ending' ) ),
 	'sponsor-post-returned'         => array( "\n", array( 'post_title', 'author_name', 'note' ), array( 'note' ) ),
+	// The Mentor applications (1.122.31): the confirming link and the course link each needed, in the
+	// body alone, and the applicant's name, address and profile in the body alone. The request for
+	// more information names nobody and has no placeholder, as its automation had none.
+	'mentor-application-received'   => array( "\n", array( 'verify_link' ), array( 'verify_link', 'applicant_email' ) ),
+	'mentor-selected'               => array( "\n", array( 'course_link' ), array( 'applicant_name', 'course_link' ) ),
+	'mentor-more-information'       => array( "\n", array(), array() ),
+	'mentor-declined'               => array( "\n", array(), array( 'applicant_name' ) ),
+	'mentor-application-notice'     => array( "\n", array(), array( 'applicant_name', 'profile_link' ) ),
 );
 $want_whole = array();
 foreach ( $shape as $id => $want ) {
@@ -562,7 +584,7 @@ foreach ( array_keys( $all ) as $id ) {
 		if ( preg_match_all( '#https?://[^\s/]+#', $value, $hosts ) ) {
 			$links = array_merge( $links, $hosts[0] );
 		}
-		if ( in_array( $name, array( 'student_name', 'mentor_name', 'with_whom', 'canceled_by', 'institution_name', 'company_name', 'contact_name', 'inviter_name', 'uploaded_by', 'administrator_name', 'member_name', 'returned_by', 'claimant_name', 'author_name', 'name_asked_for' ), true ) ) {
+		if ( in_array( $name, array( 'student_name', 'mentor_name', 'with_whom', 'canceled_by', 'institution_name', 'company_name', 'contact_name', 'inviter_name', 'uploaded_by', 'administrator_name', 'member_name', 'returned_by', 'claimant_name', 'author_name', 'name_asked_for', 'applicant_name' ), true ) ) {
 			$names[] = $value;
 		}
 	}
@@ -620,7 +642,7 @@ ck(
 	array( array(), $wanted )
 );
 $secrets = array();
-foreach ( array( 'institution-applied' => 'verify_link', 'institution-invite' => 'accept_link' ) as $id => $name ) {
+foreach ( array( 'institution-applied' => 'verify_link', 'institution-invite' => 'accept_link', 'mentor-application-received' => 'verify_link' ) as $id => $name ) {
 	parse_str( (string) parse_url( WPCPM_Mail_Templates::example_values( $id )[ $name ], PHP_URL_QUERY ), $query );
 	$secrets[ $id ] = array( $query['action'] ?? '', $query['t'] ?? '' );
 }
@@ -628,10 +650,35 @@ ck(
 	'and an application\'s confirmation link and an invitation\'s link are the site\'s own, to the handlers they open, each with a fake secret',
 	$secrets,
 	array(
-		'institution-applied' => array( WPCPM_Institution_Application::ACTION_VERIFY, 'EXAMPLE-NOT-A-REAL-SIGNATURE' ),
-		'institution-invite'  => array( WPCPM_Institution_Invite::ACTION_ACCEPT, 'EXAMPLE-NOT-A-REAL-TOKEN' ),
+		'institution-applied'         => array( WPCPM_Institution_Application::ACTION_VERIFY, 'EXAMPLE-NOT-A-REAL-SIGNATURE' ),
+		'institution-invite'          => array( WPCPM_Institution_Invite::ACTION_ACCEPT, 'EXAMPLE-NOT-A-REAL-TOKEN' ),
+		'mentor-application-received' => array( WPCPM_Mentor_Application::ACTION_VERIFY, 'EXAMPLE-NOT-A-REAL-SIGNATURE' ),
 	)
 );
+ck(
+	'the mentors\' course link of the example is the site\'s own, with a fake invitation in it, and the queue the Mentors screen\'s Waiting for review tab',
+	array( WPCPM_Mail_Templates::example_values( 'mentor-selected' )['course_link'], WPCPM_Mail_Templates::example_values( 'mentor-application-notice' )['queue_link'] ),
+	array( 'https://example.test/mentors-course/?invite=EXAMPLE-NOT-A-REAL-CODE', 'https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue' )
+);
+// Once an Administrator sets the course link, a preview or a test of the selection shows that link,
+// and the program's address as set, so the email tried is the email an applicant gets.
+WPCPM_Settings::$course  = 'https://example.test/learn/mentors/?invite=SET-IN-SETTINGS';
+WPCPM_Settings::$contact = 'programs@example.test';
+$set_selection           = WPCPM_Mail_Templates::example_values( 'mentor-selected' );
+WPCPM_Settings::$course  = '';
+WPCPM_Settings::$contact = '';
+ck(
+	'and with the course link and the contact address set, the selection\'s examples are the two as set; with neither, the contact is an example address',
+	array( $set_selection['course_link'], $set_selection['program_contact'], WPCPM_Mail_Templates::example_values( 'mentor-selected' )['program_contact'] ),
+	array( 'https://example.test/learn/mentors/?invite=SET-IN-SETTINGS', 'programs@example.test', 'programs@example.test' )
+);
+$with_contact = WPCPM_Mail_Templates::render( 'mentor-selected', WPCPM_Mail_Templates::example_values( 'mentor-selected' ) );
+$no_contact   = WPCPM_Mail_Templates::render( 'mentor-selected', array_merge( WPCPM_Mail_Templates::example_values( 'mentor-selected' ), array( 'program_contact' => '' ) ) );
+ck(
+	'the selection asks for a reply when the course is done and names the program\'s address on a line of its own, which no contact address leaves out, so the paragraph reads whole either way',
+	array( false !== strpos( $with_contact['body'], "When you finish the course, please reply to this email to let us know.\nYou can also write to programs@example.test.\n\nOnce we receive your message" ), false !== strpos( $no_contact['body'], "When you finish the course, please reply to this email to let us know.\n\nOnce we receive your message" ), false !== strpos( $no_contact['body'], 'You can also write to' ) ),
+	array( true, true, false )
+);
 ck(
 	'a sponsor\'s uploaded agreement links the agreements waiting on the Sponsors screen, as its email does, and a sponsor application the screen\'s queue',
 	array( WPCPM_Mail_Templates::example_values( 'sponsor-agreement-received' )['queue_link'], WPCPM_Mail_Templates::example_values( 'sponsor-application' )['queue_link'] ),
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php bin/test-mail-catalog.php`, `php bin/test-mail-templates.php` and `php bin/test-emails-templates.php`

```text
$ php bin/test-mail-catalog.php
FAIL the catalog names the plugin's 75 emails: the 42 contexts it sent before 1.122.19, the institution invitation for an agreement on file, the test of the signature and footer, the 19 Mentor calls emails of their own since 1.122.21, the 4 Institutions and 2 Sponsors emails of their own since 1.122.22, the institution's invitation with no sender named, its own since 1.122.23, and the 5 Mentor applications emails since 1.122.31
FAIL the Mentor applications area names its five emails, each with who receives it
FAIL the areas are the ones the Log lists, in its order
3 FAILURE(S) (26 checks)
[exit 1]
$ php bin/test-mail-templates.php
FAIL the registry holds the Invitations, the four invitations and the institution's second one, then the Mentor calls, the Institutions, the Semester reports, the Sponsors and the Mentor applications, in that order
FAIL every template says what sends it, uses only the placeholders it declares and every one it declares, holds its required ones in its body and none that goes in the body in its subject, says what each means with an example, and leaves none unfilled; every invitation needs its set-password link, in its body, every email about a call its time, every link with a secret is needed, in the body alone, and a meeting link and what somebody typed go in the body alone
PHP Warning:  Undefined array key "verify_link" in <plugin>/bin/test-mail-templates.php on line 646
FAIL and an application's confirmation link and an invitation's link are the site's own, to the handlers they open, each with a fake secret
PHP Warning:  Undefined array key "course_link" in <plugin>/bin/test-mail-templates.php on line 660
FAIL the mentors' course link of the example is the site's own, with a fake invitation in it, and the queue the Mentors screen's Waiting for review tab
FAIL and with the course link and the contact address set, the selection's examples are the two as set; with neither, the contact is an example address
FAIL the selection asks for a reply when the course is done and names the program's address on a line of its own, which no contact address leaves out, so the paragraph reads whole either way
6 FAILURE(S) (103 checks)
[exit 1]
$ php bin/test-emails-templates.php
FAIL the templates are listed by area, in the catalog's order: the Invitations, the Mentor calls, the Institutions, the Semester reports, the Sponsors and the Mentor applications
FAIL and after the invitations, every email of the Mentor calls, the Institutions, the Semester reports, the Sponsors and the Mentor applications, each with who receives it, as shipped text, its editor linked by its id
FAIL each of the six areas has a table of its own, printed inside a box that scrolls sideways on a narrow screen, held by a position, so the page does not
3 FAILURE(S) (71 checks)
[exit 1]
```

- [ ] **Step 3: The templates, the catalog and the guide**

Create `includes/mail/templates/mentor-applications.php`:

```php
<?php
/**
 * The Mentor applications area's emails, as the template registry ships them (1.122.31).
 *
 * Read by `WPCPM_Mail_Templates::all()`, which adds each email's name, area and audience from the
 * catalog. Four go to the person who applied to mentor through the form on the site, and one to the
 * Administrators.
 *
 * The four are the program's Airtable automations' words as they stood on 16 October 2026 (Mentors
 * 1, 2, 5 and 4; design of 16 October 2026, sections 2.1 and 8), kept word for word but for what
 * the site does its own way: the site's signature and footer stand where each automation signed
 * off; the acknowledgement carries the lines the institution acknowledgement has for the link that
 * confirms the address; the course's name and its invitation link are the settings'
 * (`{course_name}`, `{course_link}`); the program's address is the Contact address in emails
 * setting's (`{program_contact}`), on a line of its own in the selection, so that email reads whole
 * while none is set; and a dash in an automation's text is a comma or a full stop here. The mentor coordinator reads the four before the form is switched on. The Administrators'
 * notice is the sponsor application's, naming the applicant and their profile.
 *
 * No course code, invitation code or folder link is written here: the course invitation link is a
 * setting an Administrator fills in (`mentor_course_link`), and the examples carry that setting,
 * or a fake one while it is empty.
 *
 * The applicant's name goes in a body alone, never in a subject, which the mail log keeps.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Every placeholder an email of the area may have: what each means and its example, a value or a
// function that works one out when a preview or a test asks for it. An email takes the ones it uses
// (`$wpcpm_take`).
$wpcpm_placeholders = static function () {
	return array(
		'site_name'       => array(
			'means'   => __( 'The site\'s name.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return WPCPM_Mail::site_name();
			},
		),
		'applicant_name'  => array(
			'means'     => __( 'The applicant\'s name, as they typed it on the form. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'Ada Okonjo',
			'body_only' => true,
		),
		'reference'       => array(
			'means'   => __( 'The application\'s reference, such as MAPP-2026-0007.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return sprintf( 'MAPP-%s-0007', wp_date( 'Y' ) );
			},
		),
		'verify_link'     => array(
			'means'     => __( 'The link that confirms the applicant\'s address. It carries a secret, so the email needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => static function () {
				return add_query_arg(
					array(
						'action' => WPCPM_Mentor_Application::ACTION_VERIFY,
						'app'    => 7,
						't'      => 'EXAMPLE-NOT-A-REAL-SIGNATURE',
					),
					admin_url( 'admin-post.php' )
				);
			},
			'body_only' => true,
		),
		'applicant_email' => array(
			'means'     => __( 'The address the application gave, which this email is sent to, as the applicant typed it. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'ada@example.test',
			'body_only' => true,
		),
		'course_name'     => array(
			'means'   => __( 'The mentors\' course, as the Mentor Status Checker\'s settings name it.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return (string) ( class_exists( 'WPCPM_Settings' ) ? WPCPM_Settings::get_value( 'checker_course_title', 'WordPress Credits Mentor\'s Course' ) : 'WordPress Credits Mentor\'s Course' );
			},
		),
		'course_link'     => array(
			'means'     => __( 'The mentors\' course invitation link, from the setting on the Students and mentors tab of Settings. It is the email\'s point, so the email needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => static function () {
				$set = class_exists( 'WPCPM_Settings' ) ? trim( (string) WPCPM_Settings::get_value( 'mentor_course_link', '' ) ) : '';

				// The link as set, so a test of the email shows what an applicant would follow; a
				// fake one until an Administrator sets it.
				return '' !== $set ? $set : 'https://example.test/mentors-course/?invite=EXAMPLE-NOT-A-REAL-CODE';
			},
			'body_only' => true,
		),
		'program_contact' => array(
			'means'   => __( 'The address in Contact address in emails, which a selected applicant writes to when the course is done. Empty leaves its sentence out.', 'wpcredits-program-manager' ),
			'example' => static function () {
				$set = WPCPM_Mail_Templates::program_contact();

				// The address as set, or an example one while none is.
				return '' !== $set ? $set : 'programs@example.test';
			},
		),
		'profile_link'    => array(
			'means'     => __( 'The applicant\'s WordPress.org profile. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'https://profiles.wordpress.org/mentor-two/',
			'body_only' => true,
		),
		'queue_link'      => array(
			'means'   => __( 'The Waiting for review tab of the Mentors screen, where the application waits for an Administrator.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return admin_url( 'admin.php?page=wpcpm-mentors&tab=queue' );
			},
		),
	);
};

// The placeholders one email has, in the order given, those named in `$required` required.
$wpcpm_take = static function ( array $names, array $required ) use ( $wpcpm_placeholders ) {
	$all = $wpcpm_placeholders();
	$out = array();

	foreach ( $names as $name ) {
		$out[ $name ] = array(
			'means'     => $all[ $name ]['means'],
			'example'   => $all[ $name ]['example'],
			'required'  => in_array( $name, $required, true ),
			'body_only' => ! empty( $all[ $name ]['body_only'] ),
		);
	}

	return $out;
};

// A body: its paragraphs, a blank line between each two.
$wpcpm_body = static function ( array $paragraphs ) {
	return implode( "\n\n", $paragraphs );
};

return array(
	'mentor-application-received' => array(
		'trigger'      => __( 'When somebody applies to mentor through the form on the site: to the address the application gives.', 'wpcredits-program-manager' ),
		'subject'      => __( 'Mentor application received - WordPress Credits Program', 'wpcredits-program-manager' ),
		'body'         => $wpcpm_body(
			array(
				__( 'Hi!', 'wpcredits-program-manager' ),
				__( 'Thank you for submitting your application to become a mentor in the WordPress Credits Program. We truly appreciate your willingness to contribute your time and expertise to support our students.', 'wpcredits-program-manager' ),
				__( 'Please note that it may take several weeks before we are able to review all applications. Once the selection process is complete, we will contact you to let you know whether you have been chosen as a mentor.', 'wpcredits-program-manager' ),
				__( 'We are grateful for your patience and interest in being part of this program.', 'wpcredits-program-manager' ),
				__( 'Please confirm this address by following this link. It tells us we are writing to a real address, and it does nothing else:', 'wpcredits-program-manager' ),
				'{verify_link}',
				sprintf(
					/* translators: 1: the address the message went to, 2: site name. */
					__( 'This message went to %1$s because that address was given on the mentor application form at %2$s. If it was not you, you can ignore it: nothing happens until the address is confirmed.', 'wpcredits-program-manager' ),
					'{applicant_email}',
					'{site_name}'
				),
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'verify_link', 'applicant_email' ), array( 'verify_link' ) ),
	),
	'mentor-selected'             => array(
		'trigger'      => __( 'When an Administrator presses Select on a mentor application: to the address the application gives.', 'wpcredits-program-manager' ),
		'subject'      => __( 'WordPress Credits Program - Mentor welcome and mandatory course', 'wpcredits-program-manager' ),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: %s: the applicant's name, as they typed it. */
					__( 'Hi %s,', 'wpcredits-program-manager' ),
					'{applicant_name}'
				),
				__( 'Congratulations! You have been selected as a mentor for the WordPress Credits Program.', 'wpcredits-program-manager' ),
				__( 'Please remember that we cannot ensure sponsorship for mentors. Please move forward with the next steps only if you are sure you can commit at least two hours each week to the program\'s students.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: the name of the mentors' course, 2: its invitation link. */
					__( 'As the next step, please access the %1$s through this invitation link: %2$s', 'wpcredits-program-manager' ),
					'{course_name}',
					'{course_link}'
				),
				__( 'Inside, you will find all the guidance, materials, and examples you need to support your students effectively throughout the WordPress Credits program.', 'wpcredits-program-manager' ),
				// The automation's one sentence, "reach out to {contact} or reply to this email", is two
				// lines, so the paragraph reads whole while no contact address is set: the registry
				// leaves the second out then.
				implode(
					"\n",
					array(
						__( 'When you finish the course, please reply to this email to let us know.', 'wpcredits-program-manager' ),
						sprintf(
							/* translators: %s: the program's contact address. */
							__( 'You can also write to %s.', 'wpcredits-program-manager' ),
							'{program_contact}'
						),
					)
				),
				__( 'Once we receive your message, we will review your course report and confirm when you are officially ready to start mentoring in the WordPress Credits program.', 'wpcredits-program-manager' ),
				__( 'If you experience any issues accessing the course, just reply to this email and we will assist you right away.', 'wpcredits-program-manager' ),
				__( 'Thank you for joining the WordPress Credits mentor community. Your support makes a real difference!', 'wpcredits-program-manager' ),
			)
		),
		'placeholders' => $wpcpm_take( array( 'applicant_name', 'course_name', 'course_link', 'program_contact' ), array( 'course_link' ) ),
	),
	'mentor-more-information'     => array(
		'trigger'      => __( 'When an Administrator presses Ask for more information on a mentor application: to the address the application gives.', 'wpcredits-program-manager' ),
		'subject'      => __( 'WordPress mentorship application - request for further details', 'wpcredits-program-manager' ),
		'body'         => $wpcpm_body(
			array(
				__( 'Hello,', 'wpcredits-program-manager' ),
				__( 'Thank you for your interest in contributing to the WordPress project as a mentor, and for taking the time to submit your application.', 'wpcredits-program-manager' ),
				__( 'As part of the review process carried out by the WordPress Credits team, we aim to better understand each applicant\'s involvement with the WordPress project and its community. After reviewing your profile and the information you shared with us, we would appreciate a bit more detail to help us clearly identify your contributions and connection to WordPress.', 'wpcredits-program-manager' ),
				__( 'Could you please provide additional information about your participation in the WordPress ecosystem? For example, this could include (but is not limited to):', 'wpcredits-program-manager' ),
				implode(
					"\n",
					array(
						__( '- Contributions to WordPress, like core, themes, plugins, documentation, translations, or any of the WordPress contribution teams', 'wpcredits-program-manager' ),
						__( '- Involvement in WordPress community activities (meetups, WordCamps, organizing, speaking, volunteering, etc.)', 'wpcredits-program-manager' ),
						__( '- Mentorship, teaching, or support roles related to WordPress', 'wpcredits-program-manager' ),
						__( '- Any other relevant experience that demonstrates your engagement with the WordPress project', 'wpcredits-program-manager' ),
					)
				),
				__( 'This information will help us better understand your background and evaluate your application more accurately.', 'wpcredits-program-manager' ),
				__( 'Thank you very much for your time and your interest in supporting the WordPress community. We look forward to hearing from you.', 'wpcredits-program-manager' ),
			)
		),
		'placeholders' => $wpcpm_take( array(), array() ),
	),
	'mentor-declined'             => array(
		'trigger'      => __( 'When an Administrator presses Decline on a mentor application: to the address the application gives.', 'wpcredits-program-manager' ),
		'subject'      => __( 'Thank you for your interest in the WordPress Credits Program', 'wpcredits-program-manager' ),
		'body'         => $wpcpm_body(
			array(
				sprintf(
					/* translators: %s: the applicant's name, as they typed it. */
					__( 'Dear %s,', 'wpcredits-program-manager' ),
					'{applicant_name}'
				),
				__( 'Thank you sincerely for expressing your interest in supporting and educating students through the WordPress Credits program. We truly appreciate your enthusiasm and your commitment to fostering contributions within the WordPress community.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: the WordPress Credits Slack channel's address, 2: the GitHub repository's address. */
					__( 'While we would have been delighted to welcome you to the team, after reviewing your application, we found that the information available in your WordPress.org profile was not sufficient to confirm your eligibility to serve as a mentor at this time. We genuinely value the time and effort you invested, and we encourage you to stay involved with the WordPress Credits initiative by contributing in other ways, such as joining discussions in the WordPress Credits Slack channel (%1$s) or helping out with specific tasks in our GitHub repository (%2$s).', 'wpcredits-program-manager' ),
					'https://wordpress.slack.com/archives/C0959D2M3T8',
					'https://github.com/WordPress/WPCredits-Tracker'
				),
				__( 'If you would like to appeal this decision or share additional details about your background, experience, and reasons why you\'d be a great fit as a mentor, please reply to this email and provide more context about your involvement with WordPress and any mentoring or teaching experience you may have.', 'wpcredits-program-manager' ),
				__( 'We truly appreciate your willingness to contribute to this initiative and would be happy to review your application again once more information is available.', 'wpcredits-program-manager' ),
				__( 'Thank you once again for your time, interest, and support.', 'wpcredits-program-manager' ),
			)
		),
		'placeholders' => $wpcpm_take( array( 'applicant_name' ), array() ),
	),
	'mentor-application-notice'   => array(
		'trigger'      => __( 'When a mentor application passes every check: to the addresses in Who is mailed under Mentor applications, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: site name, 2: the application reference. */
			__( '[%1$s] New mentor application: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{reference}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'Somebody has applied to mentor through the form on the site, and the application passed every check.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: the applicant's name, 2: their WordPress.org profile. */
					__( '%1$s, %2$s', 'wpcredits-program-manager' ),
					'{applicant_name}',
					'{profile_link}'
				),
				__( 'It is on the queue here:', 'wpcredits-program-manager' ),
				'{queue_link}',
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'reference', 'applicant_name', 'profile_link', 'queue_link' ), array() ),
	),
);
```

```diff
diff --git a/docs/sections/32-admin-tools.md b/docs/sections/32-admin-tools.md
--- a/docs/sections/32-admin-tools.md
+++ b/docs/sections/32-admin-tools.md
@@ -36,7 +36,7 @@ footer. The tool also keeps a record of every email the site sends, for 30 days.
 
 #### Templates
 
-The plugin's emails, grouped by area, each area in a table of its own. There are five:
+The plugin's emails, grouped by area, each area in a table of its own. There are six:
 
 - **Invitations**: the student's, the mentor's and the sponsor's invitation, and the institution's
   two, one for an institution whose Collaboration Agreement is not on file yet and one for an
@@ -68,6 +68,12 @@ The plugin's emails, grouped by area, each area in a table of its own. There are
   of force and back in force; what a sponsor says on the Sponsor Dashboard it would like to support,
   and its request to sponsor a mentor; the warning that an offer's codes are running low and a
   claimant's report of a code that did not work; and a sponsor's post sent back to its author.
+- **Mentor applications** (since 1.122.31): what a person who applied to mentor through the form on
+  the site is told, the acknowledgement with the link that confirms the address, the selection with
+  the mentors' course invitation link, the request for more information and the decline, and the
+  notice of a new application to the Administrators. The four to the applicant ship with the words
+  the program's Airtable automations sent, and carry the program's contact address as their
+  Reply-To when **Contact address in emails** is set, so an answer reaches the program.
 
 Where an email is two, as the mentor's confirmations with and without a meeting link and the five
 above are, each of the two is edited on its own: an edit of one leaves the other as shipped. So an
diff --git a/includes/mail/class-wpcpm-mail-catalog.php b/includes/mail/class-wpcpm-mail-catalog.php
--- a/includes/mail/class-wpcpm-mail-catalog.php
+++ b/includes/mail/class-wpcpm-mail-catalog.php
@@ -34,6 +34,7 @@ final class WPCPM_Mail_Catalog {
 	const MODULE_INSTITUTIONS = 'institutions';
 	const MODULE_REPORTS      = 'reports';
 	const MODULE_SPONSORS     = 'sponsors';
+	const MODULE_MENTOR_APPS  = 'mentor-applications';
 	const MODULE_WORDPRESS    = 'wordpress';
 	const MODULE_TWO_FACTOR   = 'two-factor';
 	const MODULE_OTHER        = 'other';
@@ -61,6 +62,7 @@ final class WPCPM_Mail_Catalog {
 			self::MODULE_INSTITUTIONS => __( 'Institutions', 'wpcredits-program-manager' ),
 			self::MODULE_REPORTS      => __( 'Semester reports', 'wpcredits-program-manager' ),
 			self::MODULE_SPONSORS     => __( 'Sponsors', 'wpcredits-program-manager' ),
+			self::MODULE_MENTOR_APPS  => __( 'Mentor applications', 'wpcredits-program-manager' ),
 			self::MODULE_WORDPRESS    => __( 'WordPress', 'wpcredits-program-manager' ),
 			self::MODULE_TWO_FACTOR   => __( 'Two Factor', 'wpcredits-program-manager' ),
 			self::MODULE_OTHER        => __( 'Other', 'wpcredits-program-manager' ),
@@ -102,6 +104,7 @@ final class WPCPM_Mail_Catalog {
 		$n = self::MODULE_INSTITUTIONS;
 		$r = self::MODULE_REPORTS;
 		$s = self::MODULE_SPONSORS;
+		$m = self::MODULE_MENTOR_APPS;
 		$w = self::MODULE_WORDPRESS;
 
 		$list = array(
@@ -188,6 +191,13 @@ final class WPCPM_Mail_Catalog {
 			'offer-low-stock'                    => array( __( 'Codes running low', 'wpcredits-program-manager' ), $s, '' ),
 			'claim-problem'                      => array( __( 'Problem with a claimed code', 'wpcredits-program-manager' ), $s, self::TYPE_ADMIN ),
 			'sponsor-post-returned'              => array( __( 'Sponsor post returned with a note', 'wpcredits-program-manager' ), $s, self::TYPE_SPONSOR ),
+			// Mentor applications (1.122.31): the four emails to the person who applied to mentor, who
+			// has no account, and the Administrators' notice of a new application.
+			'mentor-application-received'        => array( __( 'Mentor application received, to the applicant', 'wpcredits-program-manager' ), $m, self::TYPE_APPLICANT ),
+			'mentor-selected'                    => array( __( 'Selected to mentor: the mentors\' course', 'wpcredits-program-manager' ), $m, self::TYPE_APPLICANT ),
+			'mentor-more-information'            => array( __( 'More information asked of a mentor applicant', 'wpcredits-program-manager' ), $m, self::TYPE_APPLICANT ),
+			'mentor-declined'                    => array( __( 'Mentor application declined', 'wpcredits-program-manager' ), $m, self::TYPE_APPLICANT ),
+			'mentor-application-notice'          => array( __( 'New mentor application, to Administrators', 'wpcredits-program-manager' ), $m, self::TYPE_ADMIN ),
 			// WordPress's own.
 			'wp-password-reset'                  => array( __( 'Password reset', 'wpcredits-program-manager' ), $w, '' ),
 			'wp-password-changed'                => array( __( 'Password changed', 'wpcredits-program-manager' ), $w, '' ),
diff --git a/includes/mail/class-wpcpm-mail-templates.php b/includes/mail/class-wpcpm-mail-templates.php
--- a/includes/mail/class-wpcpm-mail-templates.php
+++ b/includes/mail/class-wpcpm-mail-templates.php
@@ -62,6 +62,7 @@ final class WPCPM_Mail_Templates {
 		WPCPM_Mail_Catalog::MODULE_INSTITUTIONS => 'institutions',
 		WPCPM_Mail_Catalog::MODULE_REPORTS      => 'reports',
 		WPCPM_Mail_Catalog::MODULE_SPONSORS     => 'sponsors',
+		WPCPM_Mail_Catalog::MODULE_MENTOR_APPS  => 'mentor-applications',
 	);
 
 	/** What a placeholder looks like: a word in braces. */
```

Then build the guide: `php bin/build-docs.php`.

- [ ] **Step 4: Run them to verify they pass**

Run: `php bin/test-mail-catalog.php`, `php bin/test-mail-templates.php` and `php bin/test-emails-templates.php`

```text
$ php bin/test-mail-catalog.php
ALL PASS (26 checks)
[exit 0]
$ php bin/test-mail-templates.php
ALL PASS (103 checks)
[exit 0]
$ php bin/test-emails-templates.php
ALL PASS (71 checks)
[exit 0]
```

- [ ] **Step 5: The checks every task ends with**

```text
battery done
154 classes, 14546 references checked, 87 form handlers scanned - all resolve
157 PHP files, 7 blocks, 4 track seeds, 23 fixture and tooling files, the guides and the readme: US English throughout.
260 annotations, 0 dead.
Ran 133 scripts from bin/, each in a PHP process of its own, and every one exited 0.
The battery leaves the temp directory as it found it.
78 warnings, no errors.
```

- [ ] **Step 6: Commit**

```bash
git add includes/mail/templates/mentor-applications.php includes/mail/class-wpcpm-mail-catalog.php includes/mail/class-wpcpm-mail-templates.php docs/sections/32-admin-tools.md docs/administrators.md docs/build/administrators.html bin/test-mail-catalog.php bin/test-mail-templates.php bin/test-emails-templates.php
git commit -m "Mentor applications: the five emails in Tools > Emails, under an area of their own" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 4: The form at /become-a-mentor/, its guard, its acknowledgement and the Administrators' notice

The public page and everything behind its button (spec, section 4): the Airtable form's eleven questions in its order, each cleaned by its type; the sponsor form's order of checks through `WPCPM_Form_Guard`; the duplicates that hold; the row; the acknowledgement with the confirming link and the program's Reply-To; the notice of a new row to the list in `mentor_notify`, or to every Administrator; the confirming link's handler; the page, ensured on activation, made with the form's introduction above the block, and never gated.

The questions are the Airtable form's, as it was read on 16 October 2026 through the public link the handbook's Call for Mentors page gives (spec, section 2.3, and section 13, item 1): eleven, in its order, with its labels in US English and the program's full name, and its help texts lightly edited. The mentor coordinator confirms the eleven before the form is switched on (see "After the plan"). The Mentors table's three sponsorship columns are not asked, since the form does not ask them. The countries and the contribution teams are the site's own lists, the ones the institution form and a student's team choice read (`WPCPM_Countries::options()`, `WPCPM_Contribution_Teams::options()`); the site holds no list of languages, so the form carries a fixed one in code, the names the program's Airtable Languages table held on 16 October 2026, each once. The country is one field to type and pick from (`WPCPM_Dashboards::render_combo()`); the languages and the contribution areas are boxes to tick, the areas the one choice that may be left empty; the hours a number from 1 to 40 drawn with `step="0.5"`, kept to one decimal place as the string it then reads (2.25 is kept as 2.3); the teacher question two radio buttons; and the compliance declaration and the privacy policy each a heading and a box to tick, the declaration's words the form's statement with its two links, the GPL and the trademark policy, opening in the same tab.

The profile is kept as a WordPress.org username only when it is one (letters, digits, `.`, `_` and `-`, with or without one leading `@`) or an address on profiles.wordpress.org, wordpress.org or www.wordpress.org; anything else, a full name or a link to another site, is refused with "Give your WordPress.org username or your profile's address." The free text refuses what `sanitize_textarea_field()` would take out of it, through `WPCPM_Typed_Text::cleaner_loses()` as the queue's note box does, with "Please write without angle brackets or tags." The suite reads the order of the guard's fourteen steps off `handle_submit()`'s source, as the sponsor suite does, besides trying each at its boundary. A page this task makes starts with the form's introduction, two paragraph blocks the owner edits on the page; a page already at the slug is adopted as it is. The block's `thanks` attribute, a text control in the inspector's Thank you panel beside the heading's, and the shortcode's `thanks="..."`, are the sentence the thank-you page shows in place of the plugin's.

**Files:**
- Create: `includes/modules/class-wpcpm-mentor-application-form.php`
- Create: `blocks/mentor-application/block.json`, `blocks/mentor-application/editor.js`, `blocks/mentor-application/editor.asset.php` (the sponsor block's, renamed, with a thank-you sentence beside the heading)
- Modify: `assets/css/application.css` (the country field drawn as the dashboards' switcher draws it, under the form's class, and the two long lists of boxes in columns)
- Modify: `includes/class-wpcpm-dashboards.php` (`render_combo()` takes `mark` and `describedby`)
- Modify: `includes/class-wpcpm-settings-screen.php` (the page's address under the switch)
- Modify: `includes/modules/class-wpcpm-mentors.php` (`boot()` and `activate()`), `wpcredits-program-manager.php`, `uninstall.php`
- Create: `bin/test-mentor-application-form.php`
- Modify: `bin/test-combo-field.php`, `bin/test-mail-catalog.php`, `bin/test-mentors-screen.php`, `bin/test-settings.php`

**Interfaces:**
- Consumes: Task 1's record (`store()`, `confirm()`, `verify_url()`, `verify_signature()`, `applications()`, `email_hash()`, `handle_of()`, `profile_url()`, `reference()`, `application()`, the constants); Task 2's settings; Task 3's ids; `WPCPM_Form_Guard::claim_actor()`, `actor_key()`, `honeypot_filled()`, `check_token()`, `token()`, `consented()`, `claim_site()`, `claim_mail()`, `consent_evidence()`, `policy_url()`, `too_many_links()`, `client_ip()`, `user_agent()`, `render_honeypot()`; `WPCPM_Form_Stash::bounce()`, `read()`; `WPCPM_Field_Value::clean()`; `WPCPM_Typed_Text::cleaner_loses()`; `WPCPM_Mentors_Sync::wporg_username()`, `META_PROFILE`, `META_RECORD_ID`, `sponsorship()`; `WPCPM_Mail::send_to()`; `WPCPM_Institutions::notify_managers( $context, $build, 'mentor_notify' )`; `WPCPM_Mail_Templates::mail()`, `program_contact()`.
- Produces: `final class WPCPM_Mentor_Application_Form`, all static: `SHORTCODE = 'wpcpm_mentor_application'`, `BLOCK = 'wpcpm/mentor-application'`, `OPT_PAGE = 'wpcpm_mentor_application_page_id'`, `SLUG = 'become-a-mentor'`, `GPL_URL`, `TRADEMARK_URL`, `CALL_URL`, `ACTION_SUBMIT = 'wpcpm_mentor_apply'`, `FIELD_ANSWERS = 'wpcpm_mentor_application'`, `HONEYPOT = 'wpcpm_confirm_handle'`, `TOKEN_FIELD`, `DWELL_SCOPE`, `MAIL_RECEIVED = 'mentor-application-received'`, `MAIL_NOTICE = 'mentor-application-notice'`, `SIGNAL_DUPLICATE = 'duplicate'`, `SIGNAL_MENTOR = 'mentor'`; `init()`, `register()`, `render_block( $attributes )`, `ensure_page(): int`, `starting_content(): string`, `page_url(): string`, `no_cache()`, `languages(): string[]`, `countries(): string[]`, `teams(): string[]`, `fields(): array` (keys `name`, `email`, `profile`, `country`, `teacher`, `languages`, `expertise`, `hours`, `gpl`, `anything`, `consent`), `groups(): array`, `clean( string $key, mixed $raw ): array{ok, value, problem}`, `is_open(): bool`, `render( $atts = array() ): string` (`title` and `thanks`), `outcomes(): array`, `handle_submit()`, `handle_verify()`, `reply_headers(): string[]`, and `mail_applicant( int $post_id ): bool`, the acknowledgement with the confirming link, public as the one way it is sent: the submit path sends it, and Task 5's Put back in the queue sends it later to a row that never had it, the day's ceiling on acknowledgements being the form's alone. `WPCPM_Dashboards::render_combo()` gains the arguments `mark` (words after the label) and `describedby` (ids the select and the field are described by).

- [ ] **Step 1: Write the failing suite and the checks the shared suites gain**

Create `bin/test-mentor-application-form.php`:

```php
<?php
/**
 * The mentor application form (1.122.31): `WPCPM_Mentor_Application_Form`.
 *
 * Design of 16 October 2026, section 4. What each block pins, and why:
 *
 * - **The questions are the Airtable form's eleven, in its order and with its labels** (section 2.3,
 *   the form as read on 16 October 2026), kept under site keys, never Airtable columns; the
 *   compliance declaration is the form's statement word for word, with its two links; the
 *   contribution areas are the one optional choice; the countries and the contribution teams are
 *   the site's own lists, the languages a fixed list in code.
 * - **Each answer is cleaned by its type.** The WordPress.org profile is a username or an address on
 *   WordPress.org, reduced by the mentors sync's own rule, and anything else is refused; the hours
 *   take a decimal, held to one place; the free text refuses what the cleaner would take out of it;
 *   a country, a language or a team the lists do not offer is never stored.
 * - **The sponsor form's order of checks**, read off the source in that order, and each exercised at
 *   its boundary with real submissions: the per-actor ceiling (counting posts whose nonce failed),
 *   the nonce, the switch, the honeypot (which files a row as spam before requiredness is asked), the
 *   dwell token, consent, requiredness, the site-wide degrade, content scoring.
 * - **Duplicates hold, read from the site alone**: another application in the queue or waiting for
 *   the course, by address or username; a mentor account of any standing by address, username or
 *   the profile the sync keeps; a mentor the last sync read. A closed application is no duplicate.
 * - **Two emails, gated as the sponsor form gates them**: the acknowledgement, with the confirming
 *   link and the program's Reply-To, for any row that is not spam; the notice for a new row only,
 *   through the `mentor_notify` setting.
 * - **The confirming link**: a bad signature and a page are one answer; the first visit confirms,
 *   the second says it was confirmed already.
 * - **The page**: closed for the public, with one sentence for an Administrator, while the switch is
 *   off, the policy missing or either list empty; the form when open, the country as the one field
 *   for a long list; the thank-you with the reference, in the owner's words when the block or the
 *   shortcode gives them; the page made with the Airtable form's introduction, or adopted as it is,
 *   never gated.
 *
 * Run from the plugin root:  php bin/test-mentor-application-form.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/mentor-applications.php';

$GLOBALS['mail']       = array();
$GLOBALS['notices']    = array();
$GLOBALS['settings']   = array();
$GLOBALS['disallowed'] = false;
$GLOBALS['enqueued']   = array();
$GLOBALS['registered'] = array();

function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$out = array();
	foreach ( $pairs as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, (array) $atts ) ? $atts[ $name ] : $default;
	}
	return $out;
}
function wp_style_is( $handle, $list = 'enqueued' ) {
	return isset( $GLOBALS['registered'][ $handle ] );
}
function wp_script_is( $handle, $list = 'enqueued' ) {
	return isset( $GLOBALS['registered'][ $handle ] );
}
function wp_register_style( $handle, $src, $deps = array(), $ver = false ) {
	$GLOBALS['registered'][ $handle ] = $src;
}
function wp_register_script( $handle, $src, $deps = array(), $ver = false, $footer = false ) {
	$GLOBALS['registered'][ $handle ] = $src;
}
function wp_enqueue_style( $handle ) {
	$GLOBALS['enqueued'][] = $handle;
}
function wp_enqueue_script( $handle ) {
	$GLOBALS['enqueued'][] = $handle;
}
function wp_check_comment_disallowed_list( $author, $email, $url, $comment, $ip, $agent ) {
	return (bool) $GLOBALS['disallowed'];
}

/** The settings the form reads, as a check sets them. */
class WPCPM_Settings {
	public static function get() {
		return $GLOBALS['settings'];
	}
	public static function get_value( $key, $fallback = null ) {
		return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
	}
}

/** The mail layer at its contract: what a builder returns, to whom and under which id. */
class WPCPM_Mail {
	public static function send_to( $email, $context, $build, $locale = '' ) {
		if ( ! is_email( $email ) || ! is_callable( $build ) ) {
			return false;
		}
		$mail              = (array) call_user_func( $build, $email );
		$mail['to']        = $email;
		$mail['context']   = $context;
		$GLOBALS['mail'][] = $mail;
		return true;
	}
	public static function site_name() {
		return 'WordPress Credits';
	}
}

/** Who the Administrators' notice goes to is the institutions module's; here, which setting it read. */
class WPCPM_Institutions {
	public static function notify_managers( $context, $build, $setting_key = 'agreement_notify' ) {
		$mail                 = (array) call_user_func( $build, null );
		$mail['context']      = $context;
		$mail['setting']      = $setting_key;
		$GLOBALS['notices'][] = $mail;
		return 1;
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-guard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-contribution-teams.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-countries.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-form.php';

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
 * A site where the form is drawn: applications on, a privacy policy, the page, the two lists, and
 * nobody's mail sent yet.
 */
function reset_world() {
	$GLOBALS['opts']       = array();
	$GLOBALS['transients'] = array();
	$GLOBALS['posts']      = array();
	$GLOBALS['pmeta']      = array();
	$GLOBALS['users']      = array();
	$GLOBALS['umeta']      = array();
	$GLOBALS['slugs']      = array();
	$GLOBALS['mail']       = array();
	$GLOBALS['notices']    = array();
	$GLOBALS['uid']        = 0;
	$GLOBALS['manage']     = array();
	$GLOBALS['policy']     = 'https://example.test/privacy/';
	$GLOBALS['disallowed'] = false;
	$GLOBALS['next_id']    = 500;
	$GLOBALS['settings']   = array(
		'mentor_applications_enabled' => true,
		'application_trusted_proxy'   => '',
		'mail_contact'                => 'programs@example.test',
	);

	$_POST                      = array();
	$_GET                       = array();
	$_REQUEST                   = array();
	$_SERVER['REMOTE_ADDR']     = '203.0.113.7';
	$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (test)';

	$page              = new WP_Post();
	$page->ID          = 42;
	$page->post_type   = 'page';
	$page->post_status = 'publish';
	$GLOBALS['posts'][42] = $page;
	$GLOBALS['slugs'][42] = 'become-a-mentor';
	$GLOBALS['opts'][ WPCPM_Mentor_Application_Form::OPT_PAGE ] = 42;

	$policy                    = new WP_Post();
	$policy->ID                = 43;
	$policy->post_type         = 'page';
	$policy->post_status       = 'publish';
	$policy->post_modified_gmt = '2026-08-20 11:30:00';
	$GLOBALS['posts'][43]                          = $policy;
	$GLOBALS['opts']['wp_page_for_privacy_policy'] = 43;

	// The site's own lists: the countries the institutions sync read, the teams the mentors sync read.
	$GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ]       = array(
		'v'    => WPCPM_Countries::VERSION,
		'read' => 1,
		'rows' => array(
			'recCOUNTRY0000001' => array( 'name' => 'Poland', 'manager' => '', 'email' => '', 'calendly' => '' ),
			'recCOUNTRY0000002' => array( 'name' => 'Costa Rica', 'manager' => '', 'email' => '', 'calendly' => '' ),
		),
	);
	$GLOBALS['opts'][ WPCPM_Mentors_Sync::OPT_LOOKUPS ] = array(
		'v'     => WPCPM_Mentors_Sync::LOOKUPS_VERSION,
		'teams' => array(
			'recTEAM0000000001' => 'Polyglots',
			'recTEAM0000000002' => 'Core',
			'recTEAM0000000003' => 'Training',
		),
	);
}

/** A dwell token of a given age, signed the way the guard signs one for this form. */
function dwell_token( $age = 30 ) {
	$issued = time() - (int) $age;
	$random = wp_generate_password( 12, false, false );

	return $issued . '.' . $random . '.' . substr( wp_hash( WPCPM_Mentor_Application_Form::DWELL_SCOPE . '|' . $issued . '|' . $random . '|' . wp_create_nonce( WPCPM_Mentor_Application_Form::ACTION_SUBMIT ) ), 0, 32 );
}

/** The answers a good application carries, as the form posts them; null takes one out. */
function answers( array $more = array() ) {
	$values = array_merge(
		array(
			'name'      => 'Ada Okonjo',
			'email'     => 'ada@example.test',
			'profile'   => 'https://profiles.wordpress.org/ada-example/',
			'country'   => 'Poland',
			'teacher'   => 'No',
			'languages' => array( 'Polish', 'English' ),
			'expertise' => array( 'Training', 'Polyglots' ),
			'hours'     => '2,5',
			'gpl'       => '1',
			'anything'  => 'I lead a meetup.',
			'consent'   => '1',
		),
		$more
	);

	return array_filter(
		$values,
		function ( $value ) {
			return null !== $value;
		}
	);
}

/**
 * Post the form once and answer with where it landed and what the stash holds.
 *
 * @param array $answers What to post.
 * @param array $extra   `token`, `honeypot`, `nonce`, `ip`.
 * @return array `outcome`, `stash`, `url`.
 */
function submit( array $answers, array $extra = array() ) {
	$_POST = array(
		'action'                                       => WPCPM_Mentor_Application_Form::ACTION_SUBMIT,
		'_wpnonce'                                     => array_key_exists( 'nonce', $extra ) ? $extra['nonce'] : wp_create_nonce( WPCPM_Mentor_Application_Form::ACTION_SUBMIT ),
		WPCPM_Mentor_Application_Form::FIELD_ANSWERS   => wp_slash( $answers ),
		WPCPM_Mentor_Application_Form::TOKEN_FIELD     => array_key_exists( 'token', $extra ) ? $extra['token'] : dwell_token(),
		WPCPM_Mentor_Application_Form::HONEYPOT        => isset( $extra['honeypot'] ) ? $extra['honeypot'] : '',
	);

	if ( isset( $extra['ip'] ) ) {
		$_SERVER['REMOTE_ADDR'] = $extra['ip'];
	}

	$out = run( array( 'WPCPM_Mentor_Application_Form', 'handle_submit' ) );

	return landed( str_replace( 'redirect: ', '', $out['stop'] ) );
}

/** Read a redirect the way the page behind it would. */
function landed( $url ) {
	$stash = array();

	if ( preg_match( '/[?&]' . WPCPM_Mentor_Application_Form::QUERY_STASH . '=([^&]+)/', $url, $id ) ) {
		$stash = (array) get_transient( WPCPM_Mentor_Application_Form::TRANSIENT_PREFIX . $id[1] );
	} elseif ( preg_match( '/[?&]' . WPCPM_Mentor_Application_Form::QUERY_OUTCOME . '=([^&]+)/', $url, $said ) ) {
		$stash = array( 'outcome' => $said[1] );
	}

	return array(
		'outcome' => isset( $stash['outcome'] ) ? $stash['outcome'] : '',
		'stash'   => $stash,
		'url'     => $url,
	);
}

/** Every application row, oldest first. */
function rows() {
	return WPCPM_Mentor_Application::applications( WPCPM_Mentor_Application::states() );
}

/** The one row this check made, or null. */
function only_row() {
	$rows = rows();

	return 1 === count( $rows ) ? $rows[0] : null;
}

/** One method of a class's source, up to the end of its body. */
function method_body( $src, $name ) {
	$body = substr( $src, (int) strpos( $src, 'function ' . $name . '(' ) );

	return substr( $body, 0, (int) strpos( $body, "\n\t}\n" ) );
}

echo "=== The hooks ===\n";

reset_world();
$GLOBALS['hooks'] = array();
WPCPM_Mentor_Application_Form::init();
ck(
	'the form posts, and the confirming link lands, for visitors signed in and signed out alike',
	array( isset( $GLOBALS['hooks']['admin_post_nopriv_wpcpm_mentor_apply'] ), isset( $GLOBALS['hooks']['admin_post_wpcpm_mentor_apply'] ), isset( $GLOBALS['hooks']['admin_post_nopriv_wpcpm_mentor_apply_verify'] ), isset( $GLOBALS['hooks']['admin_post_wpcpm_mentor_apply_verify'] ), isset( $GLOBALS['hooks']['template_redirect'] ) ),
	array( true, true, true, true, true )
);

echo "\n=== The questions ===\n";

ck(
	'the questions are the Airtable form\'s eleven, in its order, with its labels, under the site\'s own keys, and no sponsorship question',
	array_map(
		function ( $spec ) {
			return $spec['label'];
		},
		WPCPM_Mentor_Application_Form::fields()
	),
	array(
		'name'      => 'Full name',
		'email'     => 'Email',
		'profile'   => 'WordPress.org profile URL',
		'country'   => 'Country',
		'teacher'   => 'Are you a teacher from a WordPress Credits participating institution?',
		'languages' => 'Languages in which you could mentor students',
		'expertise' => 'In which WordPress contribution areas do you have expertise?',
		'hours'     => 'Available hours per week to mentor students',
		'gpl'       => 'Compliance declaration',
		'anything'  => 'Anything else you\'d like us to know?',
		'consent'   => 'Privacy policy',
	)
);
ck(
	'the compliance declaration\'s box carries the form\'s statement word for word, its two links to the license and the trademark policy, and nothing else',
	array( wp_strip_all_tags( WPCPM_Mentor_Application_Form::fields()['gpl']['box'] ), preg_match_all( '#<a href="([^"]+)">([^<]+)</a>#', WPCPM_Mentor_Application_Form::fields()['gpl']['box'], $declared, PREG_SET_ORDER ), array_map( function ( $link ) { return $link[2] . ' ' . $link[1]; }, $declared ) ),
	array( 'I confirm that I comply with the GPL and do not violate, endorse, or work for companies that violate the WordPress trademark, misuse the WordPress name or logo, or distribute non-GPL-compliant derivatives of WordPress.', 2, array( 'GPL https://wordpress.org/about/license/', 'WordPress trademark https://wordpressfoundation.org/trademark-policy/' ) )
);
ck(
	'the required ones: everything but the contribution areas and the free text',
	array_keys( array_filter( WPCPM_Mentor_Application_Form::fields(), function ( $spec ) { return ! empty( $spec['required'] ); } ) ),
	array( 'name', 'email', 'profile', 'country', 'teacher', 'languages', 'hours', 'gpl', 'consent' )
);
ck(
	'the help is the form\'s: where a profile is, as many languages and areas as apply, none for somebody new to contributing',
	array( WPCPM_Mentor_Application_Form::fields()['profile']['help'], WPCPM_Mentor_Application_Form::fields()['languages']['help'], WPCPM_Mentor_Application_Form::fields()['expertise']['help'] ),
	array( 'Your profile\'s address, which is https://profiles.wordpress.org/ followed by your username, or just your username. If you do not have a profile yet, create one at https://login.wordpress.org/register.', 'Add as many as apply.', 'This refers to your experience as a WordPress.org contributor. Add as many as apply. If you are new to contributing, select nothing.' )
);
ck( 'the countries are the site\'s own list, by name, A to Z', WPCPM_Mentor_Application_Form::countries(), array( 'Costa Rica', 'Poland' ) );
ck( 'and so are the contribution teams', WPCPM_Mentor_Application_Form::teams(), array( 'Core', 'Polyglots', 'Training' ) );
ck( 'the languages are a fixed list, A to Z, each once', array( count( WPCPM_Mentor_Application_Form::languages() ), WPCPM_Mentor_Application_Form::languages() === array_values( array_unique( WPCPM_Mentor_Application_Form::languages() ) ), array_slice( WPCPM_Mentor_Application_Form::languages(), 0, 3 ) ), array( 38, true, array( 'Bengali', 'Catalan', 'Croatian' ) ) );

echo "\n=== Each answer, cleaned ===\n";

$profile = array();
foreach ( array( '@ada-example', 'ada-example', 'https://profiles.wordpress.org/ada-example/', 'HTTPS://PROFILES.WORDPRESS.ORG/Ada-Example/profile/', ' ada-example ', 'profiles.wordpress.org/ada-example', 'https://wordpress.org/support/users/ada-example/' ) as $typed ) {
	$profile[ $typed ] = WPCPM_Mentor_Application_Form::clean( 'profile', $typed )['value'];
}
ck( 'a profile is a username, with or without its @, or an address on WordPress.org, and each is kept as the username', $profile, array_fill_keys( array_keys( $profile ), 'ada-example' ) );
$refused = array();
foreach ( array( 'Ada Example', 'https://github.com/ada-example', 'https://www.linkedin.com/in/ada-example/', 'https://example.test/about', 'my site: ada.example.test', 'https://wordpress.org/' ) as $typed ) {
	$refused[ $typed ] = WPCPM_Mentor_Application_Form::clean( 'profile', $typed )['problem'];
}
ck( 'anything else is refused, a name, a link to another site, and an address on WordPress.org that names nobody', $refused, array_fill_keys( array_keys( $refused ), 'bad_profile' ) );
ck(
	'an empty one is left for requiredness, an array refused, and the refusal says what to give',
	array( WPCPM_Mentor_Application_Form::clean( 'profile', '' ), WPCPM_Mentor_Application_Form::clean( 'profile', array( 'x' ) )['problem'], submit( answers( array( 'profile' => 'Ada Example' ) ) )['stash']['problems']['profile'] ?? null ),
	array( array( 'ok' => true, 'value' => '', 'problem' => '' ), 'not_scalar', 'bad_profile' )
);
ck(
	'a country on the list is kept by name, one off it refused',
	array( WPCPM_Mentor_Application_Form::clean( 'country', 'Poland' )['value'], WPCPM_Mentor_Application_Form::clean( 'country', 'Atlantis' )['problem'], WPCPM_Mentor_Application_Form::clean( 'country', 'recCOUNTRY0000001' )['problem'] ),
	array( 'Poland', 'bad_choice', 'bad_choice' )
);
ck(
	'languages and teams keep the listed ones, each once, in the list\'s order, and drop the rest',
	array( WPCPM_Mentor_Application_Form::clean( 'languages', array( 'Polish', 'Klingon', 'English', 'Polish' ) )['value'], WPCPM_Mentor_Application_Form::clean( 'expertise', 'Core' )['value'], WPCPM_Mentor_Application_Form::clean( 'expertise', array( array( 'Core' ) ) )['value'] ),
	array( array( 'English', 'Polish' ), array( 'Core' ), array() )
);
$hours = array();
foreach ( array( '2.5', '2.25', '3', '3.0', '2,5', '40', '1' ) as $typed ) {
	$hours[ $typed ] = WPCPM_Mentor_Application_Form::clean( 'hours', $typed )['value'];
}
ck( 'hours take a decimal, held to one place, a comma read as the point, and kept as the number they then read', $hours, array( '2.5' => '2.5', '2.25' => '2.3', '3' => '3', '3.0' => '3', '2,5' => '2.5', '40' => '40', '1' => '1' ) );
ck(
	'and are held between 1 and 40, a word refused, an empty answer left for requiredness',
	array( WPCPM_Mentor_Application_Form::clean( 'hours', '0.5' )['problem'], WPCPM_Mentor_Application_Form::clean( 'hours', '40.1' )['problem'], WPCPM_Mentor_Application_Form::clean( 'hours', 'two' )['problem'], WPCPM_Mentor_Application_Form::clean( 'hours', '' )['value'], WPCPM_Mentor_Application_Form::clean( 'hours', array( '2' ) )['problem'] ),
	array( 'below_min', 'above_max', 'not_a_number', '', 'not_scalar' )
);
ck(
	'the teacher question takes Yes or No and nothing else; the declaration is ticked by the value its box carries alone',
	array( WPCPM_Mentor_Application_Form::clean( 'teacher', 'Yes' )['value'], WPCPM_Mentor_Application_Form::clean( 'teacher', 'maybe' )['problem'], WPCPM_Mentor_Application_Form::clean( 'gpl', '1' )['value'], WPCPM_Mentor_Application_Form::clean( 'gpl', 'yes' )['value'] ),
	array( 'Yes', 'bad_choice', true, false )
);
ck(
	'the free text refuses what the cleaner would take out of it, and keeps a lone "<", quotes, an ampersand and an apostrophe',
	array( WPCPM_Mentor_Application_Form::clean( 'anything', 'I wrote the <Navigation> block docs.' )['problem'], WPCPM_Mentor_Application_Form::clean( 'anything', 'a < b' )['ok'], WPCPM_Mentor_Application_Form::clean( 'anything', 'Tom & Jerry\'s "quote"' )['value'] ),
	array( 'markup', true, 'Tom & Jerry\'s "quote"' )
);
ck(
	'and an empty choice of contribution areas is an answer, not a blank',
	array( WPCPM_Mentor_Application_Form::clean( 'expertise', array() )['value'], submit( answers( array( 'expertise' => null ) ) )['outcome'] ),
	array( array(), 'sent' )
);
ck( 'a key nobody asks about is refused', WPCPM_Mentor_Application_Form::clean( 'role', 'administrator' )['problem'], 'unknown_field' );

echo "\n=== The checks, in the sponsor form's order ===\n";

// The order the design fixes, read off the source: each check must come before the next, so a
// reordering that every boundary below would survive fails here.
$submit_path = method_body( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-form.php' ), 'handle_submit' );
$offsets     = array(
	'actor'      => strpos( $submit_path, 'WPCPM_Form_Guard::claim_actor(' ),
	'nonce'      => strpos( $submit_path, 'wp_verify_nonce(' ),
	'closed'     => strpos( $submit_path, 'self::closed_reason()' ),
	'honeypot'   => strpos( $submit_path, 'WPCPM_Form_Guard::honeypot_filled(' ),
	'dwell'      => strpos( $submit_path, 'WPCPM_Form_Guard::check_token(' ),
	'consent'    => strpos( $submit_path, "self::clean( 'consent'" ),
	'answers'    => strpos( $submit_path, '$cleaned  = self::clean_all(' ),
	'required'   => strpos( $submit_path, 'self::add_required(' ),
	'site'       => strpos( $submit_path, 'WPCPM_Form_Guard::claim_site(' ),
	'scoring'    => strpos( $submit_path, 'self::score(' ),
	'duplicates' => strpos( $submit_path, 'self::duplicate_signals(' ),
	'store'      => strpos( $submit_path, 'WPCPM_Mentor_Application::store(' ),
	'mail'       => strpos( $submit_path, 'self::mail_applicant(' ),
	'notice'     => strpos( $submit_path, 'self::mail_administrators(' ),
);
$in_order    = $offsets;
asort( $in_order );
ck( 'the submit path reads in the order the design fixes, every one of its fourteen steps there', array( array_keys( $in_order ), in_array( false, $offsets, true ) ), array( array_keys( $offsets ), false ) );

reset_world();
for ( $i = 0; $i < WPCPM_Form_Guard::PER_HOUR; $i++ ) {
	submit( answers(), array( 'nonce' => 'nonce-stale' ) );
}
$busy = submit( answers( array( 'email' => 'six@example.test' ) ) );
ck( '1. the per-actor ceiling comes first: after five posts whose nonce failed, the sixth from one address in an hour is refused and stores nothing', array( $busy['outcome'], count( rows() ) ), array( 'busy', 0 ) );

reset_world();
$expired = submit( answers(), array( 'nonce' => 'nonce-stale' ) );
ck( '2. a nonce that fails stores nothing and hands back every answer but the tick', array( $expired['outcome'], count( rows() ), $expired['stash']['values']['name'] ?? null, isset( $expired['stash']['values']['consent'] ) ), array( 'expired', 0, 'Ada Okonjo', false ) );

reset_world();
$GLOBALS['settings']['mentor_applications_enabled'] = false;
ck( '3. with the switch off, a post is closed and stores nothing', array( submit( answers() )['outcome'], count( rows() ) ), array( 'closed', 0 ) );

reset_world();
$honey = submit( answers(), array( 'honeypot' => 'https://spam.example.test' ) );
$row   = only_row();
ck( '4. the honeypot files the row as spam, says sent, and mails nobody', array( $honey['outcome'], $row ? WPCPM_Mentor_Application::state_of( $row ) : null, $row ? WPCPM_Mentor_Application::signals_of( $row ) : null, count( $GLOBALS['mail'] ), count( $GLOBALS['notices'] ) ), array( 'sent', 'spam', array( 'honeypot' ), 0, 0 ) );
reset_world();
$honey_blank = submit( answers( array( 'name' => '', 'languages' => array() ) ), array( 'honeypot' => 'https://spam.example.test' ) );
ck( '   and before requiredness: a honeypot post with blank answers is stored as spam, not sent back to be finished', array( $honey_blank['outcome'], only_row() ? WPCPM_Mentor_Application::state_of( only_row() ) : null ), array( 'sent', 'spam' ) );

reset_world();
$stale = submit( answers(), array( 'token' => dwell_token( 13 * HOUR_IN_SECONDS ) ) );
ck( '5. a stale token bounces with the answers', array( $stale['outcome'], count( rows() ), $stale['stash']['values']['name'] ?? null ), array( 'stale', 0, 'Ada Okonjo' ) );
$fast = submit( answers(), array( 'token' => dwell_token( 1 ) ) );
ck( '   a token seconds old holds the row, and its sender is acknowledged', array( $fast['outcome'], WPCPM_Mentor_Application::state_of( only_row() ), WPCPM_Mentor_Application::signals_of( only_row() ), count( $GLOBALS['mail'] ) ), array( 'sent', 'held', array( 'dwell-fast' ), 1 ) );
reset_world();
$forged = submit( answers(), array( 'token' => '1.abc.forged' ) );
ck( '   a token this site never signed is spam', array( $forged['outcome'], WPCPM_Mentor_Application::state_of( only_row() ) ), array( 'sent', 'spam' ) );

reset_world();
foreach ( array( null, '0', 'yes', '' ) as $tick ) {
	$refused = submit( answers( array( 'consent' => $tick ) ) );
	ck( sprintf( '6. consent %s refuses the whole submission and stores nothing, not even as spam', var_export( $tick, true ) ), array( $refused['outcome'], count( rows() ) ), array( 'consent', 0 ) );
}

reset_world();
$again = submit( answers( array( 'name' => '', 'gpl' => null, 'languages' => array(), 'profile' => 'https://example.test/' ) ) );
ck(
	'8. requiredness: each blank answer, an unticked statement and a profile that is no username come back marked, with the rest of the answers, and nothing is stored',
	array( $again['outcome'], $again['stash']['problems'] ?? null, $again['stash']['values']['email'] ?? null, count( rows() ) ),
	array( 'again', array( 'profile' => 'bad_profile', 'name' => 'required', 'languages' => 'required', 'gpl' => 'unticked' ), 'ada@example.test', 0 )
);

reset_world();
for ( $i = 0; $i < WPCPM_Form_Guard::PER_DAY; $i++ ) {
	WPCPM_Ceiling::claim( WPCPM_Mentor_Application_Form::SITE_KEY, WPCPM_Form_Guard::PER_DAY, DAY_IN_SECONDS );
}
$degraded = submit( answers() );
ck( '9. past the day\'s forty the row is kept and held, and the Administrators are not mailed', array( $degraded['outcome'], WPCPM_Mentor_Application::state_of( only_row() ), WPCPM_Mentor_Application::signals_of( only_row() ), count( $GLOBALS['notices'] ) ), array( 'sent', 'held', array( 'site-ceiling' ), 0 ) );

reset_world();
submit( answers( array( 'anything' => 'https://a.example.test https://b.example.test https://c.example.test' ) ) );
ck( '10. three links in the free text hold the row', WPCPM_Mentor_Application::signals_of( only_row() ), array( 'links' ) );
reset_world();
$GLOBALS['disallowed'] = true;
submit( answers() );
ck( '   and so does a match on the site\'s disallowed list', WPCPM_Mentor_Application::signals_of( only_row() ), array( 'disallowed' ) );

echo "\n=== Duplicates hold, read from the site alone ===\n";

reset_world();
submit( answers() );
$second = submit( answers( array( 'email' => 'ADA@example.test', 'profile' => 'someone-example' ) ) );
$rows   = rows();
ck( 'another open application with the same address, in any case, holds the second and marks it', array( WPCPM_Mentor_Application::state_of( $rows[0] ), WPCPM_Mentor_Application::state_of( $rows[1] ), WPCPM_Mentor_Application::signals_of( $rows[1] ) ), array( 'new', 'held', array( 'duplicate' ) ) );

reset_world();
submit( answers() );
WPCPM_Mentor_Application::decide( rows()[0]->ID, 'selected', 'selected', 7 );
submit( answers( array( 'email' => 'other@example.test', 'profile' => '@Ada-Example' ) ) );
ck( 'one waiting for the course with the same username holds it too', WPCPM_Mentor_Application::signals_of( rows()[1] ), array( 'duplicate' ) );

reset_world();
submit( answers() );
WPCPM_Mentor_Application::decide( rows()[0]->ID, 'declined', 'declined', 7 );
submit( answers() );
ck( 'but a declined one is no duplicate: the same person may apply again', array( WPCPM_Mentor_Application::state_of( rows()[1] ), WPCPM_Mentor_Application::signals_of( rows()[1] ) ), array( 'new', array() ) );

$mentor_cases = array(
	'an account with the address and the Mentor role'         => function () {
		an_account( 30, 'Ada Okonjo', 'ada@example.test', array( 'wpcpm_mentor' ) );
	},
	'a former mentor\'s account, named for the username, with a record and no role' => function () {
		an_account( 31, 'Ada Okonjo', 'old@example.test', array( 'subscriber' ), array( WPCPM_Mentors_Sync::META_RECORD_ID => 'recMENTOR00000001' ) );
		$GLOBALS['users'][31]->user_login = 'ada-example';
	},
	'an account whose kept profile is the same username'      => function () {
		an_account( 32, 'A. Okonjo', 'elsewhere@example.test', array( 'wpcpm_mentor' ), array( WPCPM_Mentors_Sync::META_PROFILE => 'https://profiles.wordpress.org/ada-example/' ) );
	},
	'a mentor the last mentors sync read from Airtable'       => function () {
		$GLOBALS['opts'][ WPCPM_Mentors_Sync::OPT_SPONSORSHIP ] = array( 'rows' => array( 'recMENTOR00000002' => array( 'name' => 'Ada Okonjo', 'profile' => 'ada-example' ) ) );
	},
);
foreach ( $mentor_cases as $case => $plant ) {
	reset_world();
	$plant();
	submit( answers() );
	ck( sprintf( 'a mentor the site knows holds it: %s', $case ), array( WPCPM_Mentor_Application::state_of( only_row() ), WPCPM_Mentor_Application::signals_of( only_row() ) ), array( 'held', array( 'mentor' ) ) );
}
reset_world();
an_account( 33, 'Ada Okonjo', 'ada@example.test', array( 'wpcpm_student' ) );
submit( answers() );
ck( 'and an account with the address that is no mentor\'s holds nothing', WPCPM_Mentor_Application::state_of( only_row() ), 'new' );

echo "\n=== The row and the two emails ===\n";

reset_world();
$sent = submit( answers( array( 'anything' => 'Line one.' . "\r\n" . 'C:\\path & "quotes"' ) ) );
$row  = only_row();
ck( 'a good application lands as a new row, and the page says sent, with the reference and the address', array( $sent['outcome'], WPCPM_Mentor_Application::state_of( $row ), $sent['stash']['reference'] ?? null, $sent['stash']['email'] ?? null ), array( 'sent', 'new', 'MAPP-2026-0500', 'ada@example.test' ) );
ck(
	'the answers are kept cleaned, the username for the profile and the country by name, the statement as ticked, and no consent among them',
	WPCPM_Mentor_Application::fields_of( $row ),
	array(
		'name'      => 'Ada Okonjo',
		'email'     => 'ada@example.test',
		'profile'   => 'ada-example',
		'country'   => 'Poland',
		'teacher'   => 'No',
		'languages' => array( 'English', 'Polish' ),
		'expertise' => array( 'Polyglots', 'Training' ),
		'hours'     => '2.5',
		'gpl'       => true,
		// As a browser posts a text box, each line break CRLF, and kept so.
		'anything'  => "Line one.\r\nC:\\path & \"quotes\"",
	)
);
ck( 'the consent evidence names the sentence beside the box and the policy', array( get_post_meta( $row->ID, WPCPM_Mentor_Application::META_CONSENT, true )['sentence'] ?? null, get_post_meta( $row->ID, WPCPM_Mentor_Application::META_CONSENT, true )['url'] ?? null ), array( 'I confirm I have read the privacy policy and that my answers can be kept under it while the program reviews my application.', 'https://example.test/privacy/' ) );

$ack = $GLOBALS['mail'][0] ?? array();
ck( 'the acknowledgement goes to the address, under its own id, with the program\'s Reply-To', array( $ack['to'] ?? null, $ack['context'] ?? null, $ack['headers'] ?? null ), array( 'ada@example.test', 'mentor-application-received', array( 'Reply-To: programs@example.test' ) ) );
ck( 'and carries the automation\'s subject and greeting, and the link that confirms the address', array( $ack['subject'] ?? null, false !== strpos( $ack['body'] ?? '', WPCPM_Mentor_Application::verify_url( $row->ID ) ), 0 === strpos( $ack['body'] ?? '', "Hi!\n\n" ) ), array( 'Mentor application received - WordPress Credits Program', true, true ) );
$notice = $GLOBALS['notices'][0] ?? array();
ck( 'the Administrators are told through the mentor_notify setting, with the name, the profile and the queue, and none of the other answers', array( $notice['context'] ?? null, $notice['setting'] ?? null, false !== strpos( $notice['body'] ?? '', "Ada Okonjo, https://profiles.wordpress.org/ada-example/" ), false !== strpos( $notice['body'] ?? '', 'admin.php?page=wpcpm-mentors&tab=queue' ), false !== strpos( $notice['body'] ?? '', 'meetup' ), false !== strpos( $notice['subject'] ?? '', 'Ada' ) ), array( 'mentor-application-notice', 'mentor_notify', true, true, false, false ) );

reset_world();
$GLOBALS['settings']['mail_contact'] = '';
submit( answers( array( 'anything' => 'https://a.example.test https://b.example.test https://c.example.test' ) ) );
ck( 'a held row is acknowledged and the Administrators are not mailed; with no contact address, no Reply-To', array( count( $GLOBALS['mail'] ), count( $GLOBALS['notices'] ), $GLOBALS['mail'][0]['headers'] ?? null ), array( 1, 0, array() ) );

reset_world();
for ( $i = 0; $i < WPCPM_Form_Guard::MAIL_PER_DAY; $i++ ) {
	WPCPM_Form_Guard::claim_mail( WPCPM_Mentor_Application_Form::MAIL_KEY, WPCPM_Form_Guard::MAIL_PER_DAY );
}
$quiet = submit( answers() );
ck( 'past the day\'s mail ceiling the row is stored, marked, and the page says the acknowledgement could not go', array( $quiet['outcome'], WPCPM_Mentor_Application::signals_of( only_row() ), count( $GLOBALS['mail'] ) ), array( 'sent-quiet', array( 'mail-ceiling' ), 0 ) );
$late = WPCPM_Mentor_Application_Form::mail_applicant( (int) only_row()->ID );
ck(
	'and the acknowledgement\'s sender is public, the one way it goes, for Put back in the queue to send it later to a row that never had it: asked, it goes, the day\'s ceiling being the form\'s alone, with the link that confirms the address',
	array( $late, count( $GLOBALS['mail'] ), $GLOBALS['mail'][0]['context'] ?? null, false !== strpos( $GLOBALS['mail'][0]['body'] ?? '', WPCPM_Mentor_Application::verify_url( (int) only_row()->ID ) ) ),
	array( true, 1, 'mentor-application-received', true )
);

reset_world();
$GLOBALS['insert_fails'] = true;
ck( 'when WordPress refuses the row, the page says so and keeps the answers', array( submit( answers() )['outcome'], count( $GLOBALS['mail'] ) ), array( 'lost', 0 ) );
$GLOBALS['insert_fails'] = false;

echo "\n=== The confirming link ===\n";

reset_world();
submit( answers() );
$row  = only_row();
$link = function ( $app, $sig ) {
	$_GET     = array(
		'action' => WPCPM_Mentor_Application::ACTION_VERIFY,
		'app'    => (string) $app,
		't'      => $sig,
	);
	$_REQUEST = $_GET;

	return landed( str_replace( 'redirect: ', '', run( array( 'WPCPM_Mentor_Application_Form', 'handle_verify' ) )['stop'] ) )['outcome'];
};
ck(
	'a wrong signature and a page are one answer, and confirm nothing',
	array( $link( $row->ID, 'nope' ), $link( 42, WPCPM_Mentor_Application::verify_signature( 42 ) ), WPCPM_Mentor_Application::is_confirmed( $row->ID ) ),
	array( 'verify-failed', 'verify-failed', false )
);
ck(
	'the link confirms the address once, and says so again on a second visit',
	array( $link( $row->ID, WPCPM_Mentor_Application::verify_signature( $row->ID ) ), WPCPM_Mentor_Application::is_confirmed( $row->ID ), $link( $row->ID, WPCPM_Mentor_Application::verify_signature( $row->ID ) ) ),
	array( 'verified', true, 'verified-already' )
);

echo "\n=== The page ===\n";

$closed = array(
	'the switch off'         => function () {
		$GLOBALS['settings']['mentor_applications_enabled'] = false;
	},
	'no privacy policy'      => function () {
		$GLOBALS['policy'] = '';
	},
	'no countries to offer'  => function () {
		unset( $GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ] );
	},
	'no teams to offer'      => function () {
		unset( $GLOBALS['opts'][ WPCPM_Mentors_Sync::OPT_LOOKUPS ] );
	},
);
foreach ( $closed as $case => $close ) {
	reset_world();
	$close();
	$public = WPCPM_Mentor_Application_Form::render();
	$GLOBALS['uid']    = 9;
	$GLOBALS['manage'] = array( 9 );
	$admin  = WPCPM_Mentor_Application_Form::render();
	ck( sprintf( 'closed with %s: the public sees nothing, an Administrator one sentence and no form', $case ), array( $public, false !== strpos( $admin, 'wpcpm-application__notice' ), false !== strpos( $admin, '<form' ) ), array( '', true, false ) );
}

reset_world();
$page = WPCPM_Mentor_Application_Form::render();
preg_match_all( '#name="wpcpm_mentor_application\[([a-z_]+)\]#', $page, $named );
ck( 'open, the form asks every question in order, under the site\'s keys', array_values( array_unique( $named[1] ) ), array_keys( WPCPM_Mentor_Application_Form::fields() ) );
ck(
	'it posts to its handler with a nonce, a token and the honeypot',
	array( false !== strpos( $page, 'name="action" value="wpcpm_mentor_apply"' ), false !== strpos( $page, 'name="_wpnonce" value="nonce-wpcpm_mentor_apply"' ), false !== strpos( $page, 'name="wpcpm_mentor_application_token"' ), false !== strpos( $page, 'name="wpcpm_confirm_handle"' ) ),
	array( true, true, true, true )
);
ck(
	'the country is the one field for a long list: the label marked Required, the list with its empty first entry chosen, the field that takes typing beside it',
	array( false !== strpos( $page, '<label for="wpcpm-mentor-application-country" id="wpcpm-mentor-application-country-label">Country <span class="wpcpm-field__required">Required</span></label>' ), false !== strpos( $page, '<option value="" selected=\'selected\'>Choose a country</option><option value="Costa Rica">Costa Rica</option><option value="Poland">Poland</option>' ), false !== strpos( $page, 'role="combobox"' ), in_array( WPCPM_Dashboards::SWITCHER_SCRIPT, $GLOBALS['enqueued'], true ) ),
	array( true, true, true, true )
);
ck(
	'the languages and the teams are boxes in a named group, the contribution areas not marked Required, the teacher question two radios',
	array( substr_count( $page, 'name="wpcpm_mentor_application[languages][]"' ), substr_count( $page, 'name="wpcpm_mentor_application[expertise][]"' ), false !== strpos( $page, 'id="wpcpm-mentor-application-expertise-label">In which WordPress contribution areas do you have expertise?</span>' ), substr_count( $page, 'type="radio" name="wpcpm_mentor_application[teacher]"' ), false !== strpos( $page, 'role="group" aria-labelledby="wpcpm-mentor-application-languages-label"' ) ),
	array( 38, 3, true, 2, true )
);
ck(
	'the hours take a decimal in half-hour steps between 1 and 40',
	1 === preg_match( '#<input type="number" id="wpcpm-mentor-application-hours" name="wpcpm_mentor_application\[hours\]" value="" min="1" max="40" step="0\.5" inputmode="decimal" required="required"#', $page ),
	true
);
ck(
	'the compliance declaration is its heading, marked Required, and the box with the statement and its two links, opening where the form is',
	array( false !== strpos( $page, '<span class="wpcpm-field__label" id="wpcpm-mentor-application-gpl-label">Compliance declaration <span class="wpcpm-field__required">Required</span></span>' ), false !== strpos( $page, '<label for="wpcpm-mentor-application-gpl">I confirm that I comply with the <a href="https://wordpress.org/about/license/">GPL</a> and do not violate, endorse, or work for companies that violate the <a href="https://wordpressfoundation.org/trademark-policy/">WordPress trademark</a>, misuse the WordPress name or logo, or distribute non-GPL-compliant derivatives of WordPress.</label>' ), substr_count( $page, 'target=' ) ),
	array( true, true, 0 )
);
ck(
	'and the privacy policy is its heading and the consent sentence beside its box',
	false !== strpos( $page, '<span class="wpcpm-field__label" id="wpcpm-mentor-application-consent-label">Privacy policy <span class="wpcpm-field__required">Required</span></span><span class="wpcpm-field__consent"><input type="checkbox" id="wpcpm-mentor-application-consent" name="wpcpm_mentor_application[consent]" value="1" required="required" /><label for="wpcpm-mentor-application-consent">I confirm I have read the privacy policy and that my answers can be kept under it while the program reviews my application.</label></span>' ),
	true
);

reset_world();
$bounced = submit( answers( array( 'profile' => 'Ada Example', 'anything' => 'I wrote the <Navigation> block docs.' ) ) );
$_GET    = array( WPCPM_Mentor_Application_Form::QUERY_STASH => substr( $bounced['url'], strpos( $bounced['url'], '=' ) + 1 ) );
$again   = WPCPM_Mentor_Application_Form::render();
ck(
	'a profile that is no username and a free text with a tag come back with the sentence that says what to give',
	array( $bounced['outcome'], false !== strpos( $again, '<span class="wpcpm-field__problem" id="wpcpm-mentor-application-profile-problem">Give your WordPress.org username or your profile&#039;s address.</span>' ), false !== strpos( $again, '<span class="wpcpm-field__problem" id="wpcpm-mentor-application-anything-problem">Please write without angle brackets or tags.</span>' ) ),
	array( 'again', true, true )
);

reset_world();
$sent = submit( answers() );
$_GET = array( WPCPM_Mentor_Application_Form::QUERY_STASH => substr( $sent['url'], strpos( $sent['url'], '=' ) + 1 ) );
$thanks = WPCPM_Mentor_Application_Form::render();
ck( 'the thank-you names the reference and the address, says to confirm it, and draws no form', array( false !== strpos( $thanks, 'MAPP-2026-0500' ), false !== strpos( $thanks, 'ada@example.test with a link that confirms the address' ), false !== strpos( $thanks, '<form' ) ), array( true, true, false ) );
ck( 'and it is one-shot: the stash is gone once read', WPCPM_Mentor_Application_Form::render() === $thanks, false );
$owner = array();
foreach ( array( 'block' => array( 'WPCPM_Mentor_Application_Form', 'render_block' ), 'shortcode' => array( 'WPCPM_Mentor_Application_Form', 'render' ) ) as $how => $draw ) {
	reset_world();
	$sent           = submit( answers() );
	$_GET           = array( WPCPM_Mentor_Application_Form::QUERY_STASH => substr( $sent['url'], strpos( $sent['url'], '=' ) + 1 ) );
	$drawn          = call_user_func( $draw, array( 'thanks' => 'Thanks, <b>mentor</b>: we read every one.' ) );
	$owner[ $how ] = array( false !== strpos( $drawn, '<h3>Thanks, &lt;b&gt;mentor&lt;/b&gt;: we read every one.</h3>' ), false !== strpos( $drawn, 'Thank you for applying to mentor' ), false !== strpos( $drawn, 'MAPP-2026-0500' ) );
}
ck( 'the owner\'s thank-you, given to the block or the shortcode, takes the plugin\'s place, escaped, above the reference', $owner, array_fill_keys( array( 'block', 'shortcode' ), array( true, false, true ) ) );

reset_world();
unset( $GLOBALS['opts'][ WPCPM_Mentor_Application_Form::OPT_PAGE ] );
$GLOBALS['posts'][42]->post_content = '<!-- wp:paragraph --><p>Apply in Airtable.</p><!-- /wp:paragraph -->';
$adopted                            = WPCPM_Mentor_Application_Form::ensure_page();
$adopted_content                    = get_post( 42 )->post_content;
unset( $GLOBALS['posts'][42], $GLOBALS['slugs'][42], $GLOBALS['opts'][ WPCPM_Mentor_Application_Form::OPT_PAGE ] );
$made = WPCPM_Mentor_Application_Form::ensure_page();
ck( 'the page at become-a-mentor is adopted as it is when it is there, made when it is not', array( $adopted, $adopted_content, get_post( $made )->post_name, get_option( WPCPM_Mentor_Application_Form::OPT_PAGE ) ), array( 42, '<!-- wp:paragraph --><p>Apply in Airtable.</p><!-- /wp:paragraph -->', 'become-a-mentor', $made ) );
ck(
	'and a page it makes starts with the Airtable form\'s introduction, the call for mentors linked, then the form',
	get_post( $made )->post_content,
	"<!-- wp:paragraph -->\n<p>We are looking for dedicated mentors to guide the next generation of contributors in WordPress Credits, a flagship educational program of the WordPress Foundation.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>We cannot ensure sponsorship for mentors. By applying you confirm that you have at least two hours each week for your students. Before applying, read the call for mentors page: <a href=\"https://make.wordpress.org/handbook/wordpress-credits-contribution-internship-program/call-for-mentors/\">https://make.wordpress.org/handbook/wordpress-credits-contribution-internship-program/call-for-mentors/</a></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:wpcpm/mentor-application /-->"
);
ck( 'and it is never gated: no access level is written on it', isset( $GLOBALS['pmeta'][ $made ] ), false );
ck( 'nothing was asked of WordPress that the stand-ins do not model', isset( $GLOBALS['unmodeled'] ) ? $GLOBALS['unmodeled'] : array(), array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

```diff
diff --git a/bin/test-combo-field.php b/bin/test-combo-field.php
--- a/bin/test-combo-field.php
+++ b/bin/test-combo-field.php
@@ -122,6 +122,22 @@ ck( 'and with no count given, the switcher\'s', false !== strpos( combo( array(
 ck( 'no form, no Show and no note: those are the switcher\'s', array( strpos( $html, '<form' ), strpos( $html, '<button' ), strpos( $html, 'switcher-note' ) ), array( false, false, false ) );
 ck( 'the field loads the script that works it, registered once in the footer', array( $GLOBALS['enqueued'][0] ?? null, $GLOBALS['registered'][ WPCPM_Dashboards::SWITCHER_SCRIPT ]['src'] ?? null, $GLOBALS['registered'][ WPCPM_Dashboards::SWITCHER_SCRIPT ]['footer'] ?? null ), array( WPCPM_Dashboards::SWITCHER_SCRIPT, WPCPM_PLUGIN_URL . 'assets/js/switcher.js', true ) );
 
+// A form's field (the mentor application's country, 1.122.31): the label carries the form's Required
+// mark, and the select and the field are both described by the question's hint and problem, so what
+// a screen reader hears does not change when the script swaps one for the other.
+$marked = combo(
+	array(
+		'mark'        => 'Required',
+		'describedby' => 'wpcpm-test-email-help wpcpm-test-email-problem',
+	)
+);
+ck(
+	'a form can mark its label and describe its select and its field',
+	array( false !== strpos( $marked, '<label for="wpcpm-test-email" id="wpcpm-test-email-label">Email <span class="wpcpm-field__required">Required</span></label>' ), false !== strpos( $marked, '<select name="email" id="wpcpm-test-email" autocomplete="off" aria-describedby="wpcpm-test-email-help wpcpm-test-email-problem">' ), 1 === preg_match( '#<input type="text" id="wpcpm-test-email-input"[^>]* aria-describedby="wpcpm-test-email-help wpcpm-test-email-problem" />#', $marked ) ),
+	array( true, true, true )
+);
+ck( 'and a field asked for neither draws neither', array( strpos( $html, 'wpcpm-field__required' ), strpos( $html, 'aria-describedby' ) ), array( false, false ) );
+
 // The switcher draws its fields through this one, so the two cannot drift apart.
 $source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php' );
 $body   = preg_match( '/function render_switcher\(.*?\n\t}\n/s', $source, $found ) ? $found[0] : '';
diff --git a/bin/test-mail-catalog.php b/bin/test-mail-catalog.php
--- a/bin/test-mail-catalog.php
+++ b/bin/test-mail-catalog.php
@@ -229,7 +229,7 @@ foreach ( array_merge( glob( __DIR__ . '/../includes/*.php' ), glob( __DIR__ . '
 $missing = array_values( array_diff( array_keys( $contexts ), array_keys( $emails ) ) );
 sort( $missing );
 ck( 'every context the source passes to the mail layer is in the catalog', $missing, array() );
-ck( 'and the scan found all 34 of them (it is not reading nothing); the institution\'s invitation is set from a choice between two ids since 1.122.23, and read from the registry\'s files below', count( $contexts ), 34 );
+ck( 'and the scan found all 36 of them (it is not reading nothing), the mentor application form\'s two among them since 1.122.31; the institution\'s invitation is set from a choice between two ids since 1.122.23, and read from the registry\'s files below', count( $contexts ), 36 );
 
 // The template registry's ids, read from its definition files as each file writes them: one at the
 // start of each line of the array the file returns. An invitation is named by its template's id,
diff --git a/bin/test-mentors-screen.php b/bin/test-mentors-screen.php
--- a/bin/test-mentors-screen.php
+++ b/bin/test-mentors-screen.php
@@ -112,10 +112,13 @@ class WPCPM_Call_Calendar {
 class WPCPM_Group_Sessions {
 	public static function init() {}
 }
-/** And the applications to mentor (1.122.31), whose post type it boots too. */
+/** And the applications to mentor (1.122.31), whose post type and public form it boots too. */
 class WPCPM_Mentor_Application {
 	public static function init() {}
 }
+class WPCPM_Mentor_Application_Form {
+	public static function init() {}
+}
 
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
diff --git a/bin/test-settings.php b/bin/test-settings.php
--- a/bin/test-settings.php
+++ b/bin/test-settings.php
@@ -3592,13 +3592,13 @@ ck( 'and with its page missing, each says so in the same words',
     ) );
 
 // The same words because the same code: the four landing pages are drawn by one row, and each row that
-// prints a page, the four and the two application forms, prints its address, or the sentence saying
+// prints a page, the four and the three application forms, prints its address, or the sentence saying
 // it is missing, through one line of the rows class.
 preg_match_all( "/WPCPM_Settings_Rows::landing_row\(\s*'([a-z_]+)'/", $screen_source, $landing_drawn );
 
 ck( 'the four landing pages are drawn by the one landing row, and the sentence saying a page is missing is written once, in the rows\' page line',
     array( $landing_drawn[1], substr_count( $screen_source . $rows_file_source, 'The page is missing: re-activate the plugin to recreate it.' ), substr_count( $screen_source, 'WPCPM_Settings_Rows::page_line( ' ) ),
-    array( array( 'mentor_home', 'student_home', 'institution_home', 'sponsor_home' ), 1, 2 ) );
+    array( array( 'mentor_home', 'student_home', 'institution_home', 'sponsor_home' ), 1, 3 ) );
 
 // The student's page is the Student Report Card, as the toolbar names it for the student; the name
 // it had before, My Program, is one the page no longer carries.
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php bin/test-mentor-application-form.php`, `php bin/test-combo-field.php`, `php bin/test-mail-catalog.php`, `php bin/test-mentors-screen.php` and `php bin/test-settings.php`

```text
$ php bin/test-mentor-application-form.php
PHP Warning:  require_once(<plugin>/includes/modules/class-wpcpm-mentor-application-form.php): Failed to open stream: No such file or directory in <plugin>/bin/test-mentor-application-form.php on line 131
PHP Fatal error:  Uncaught Error: Failed opening required '<plugin>/includes/modules/class-wpcpm-mentor-application-form.php' (include_path='.:/opt/homebrew/Cellar/php/8.5.7/share/php/pear') in <plugin>/bin/test-mentor-application-form.php:131
[exit 255]
$ php bin/test-combo-field.php
FAIL a form can mark its label and describe its select and its field
1 FAILURE(S) (13 checks)
[exit 1]
$ php bin/test-mail-catalog.php
FAIL and the scan found all 36 of them (it is not reading nothing), the mentor application form's two among them since 1.122.31; the institution's invitation is set from a choice between two ids since 1.122.23, and read from the registry's files below
1 FAILURE(S) (26 checks)
[exit 1]
$ php bin/test-mentors-screen.php
ALL PASS (170 checks)
[exit 0]
$ php bin/test-settings.php
FAIL the four landing pages are drawn by the one landing row, and the sentence saying a page is missing is written once, in the rows' page line
1 FAILURE(S)
[exit 1]
```

The Mentors screen's suite passes: its stand-in for the form waits for the boot that calls it.

- [ ] **Step 3: The form**

Create `includes/modules/class-wpcpm-mentor-application-form.php`:

```php
<?php
/**
 * Mentors module - the public form people apply to mentor through (1.122.31).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Loaded here as well as by the plugin's loader, as the sponsor form loads it: the suites require
// this class with a handful of others, and `require_once` is idempotent by real path.
require_once dirname( __DIR__ ) . '/class-wpcpm-form-stash.php';

/**
 * The form at /become-a-mentor/, and everything that guards it (design of 16 October 2026, section 4).
 *
 * The sponsor application's shape with the mentor's questions, which are the program's Airtable
 * form's eleven, in its order and with its labels, as the form read on 16 October 2026 (section
 * 2.3), kept as site fields, never as Airtable columns. The order the checks run in is the sponsor
 * form's, through `WPCPM_Form_Guard`, under this form's own keys: the per-actor ceiling, the nonce,
 * the switch and what the form needs to be drawn, the honeypot, the dwell token, consent, the
 * answers, requiredness, the site-wide degrade, content scoring, the duplicate flags, then the row
 * and the two emails.
 *
 * **Duplicates hold.** Unlike the sponsor form's flags, which only mark a row, a mentor application
 * that shares its address or its WordPress.org username with another application still in play, or
 * with a mentor the site knows (an account with the Mentor role or a mentor record, or a mentor the
 * last mentors sync read from Airtable), is held for an Administrator: the design's rule (section 4).
 * Nothing is ever merged, and nothing on a public request asks Airtable anything.
 *
 * **The address is confirmed by the applicant.** The acknowledgement carries a link bound to the
 * address (`WPCPM_Mentor_Application::verify_url()`); a decision that emails the applicant refuses
 * a row whose address nobody has confirmed (`WPCPM_Mentor_Application_Queue`).
 *
 * The page is deliberately **not gated**, as the other two forms' are not: see `ensure_page()`. Its
 * introduction and its thank-you are the owner's to word on the page (section 13, item 2): the page
 * starts with the Airtable form's introduction, and the block's `thanks` takes the thank-you's place.
 */
final class WPCPM_Mentor_Application_Form {

	const SHORTCODE = 'wpcpm_mentor_application';
	const BLOCK     = 'wpcpm/mentor-application';
	const OPT_PAGE  = 'wpcpm_mentor_application_page_id';
	const STYLE     = 'wpcpm-mentor-application';

	/** The page's slug. Chosen once and never renamed: a rename breaks every link anybody was sent. */
	const SLUG = 'become-a-mentor';

	/** The `admin_post_` action the form posts to, registered for logged-out visitors too. */
	const ACTION_SUBMIT = 'wpcpm_mentor_apply';

	/** The dwell token's scope: a token minted for another form is a forgery here. */
	const DWELL_SCOPE = 'wpcpm-mentor-application-dwell';

	/** The per-actor ceiling's key prefix: this form's own five an hour per source. */
	const ACTOR_PREFIX = 'mentor-apply';

	/** The site-wide ceiling's key: this form's own forty a day. */
	const SITE_KEY = 'mentor-apply-site';

	/** The mail ceiling's key. */
	const MAIL_KEY = 'mentor-apply-mail';

	/** Longest free-text answer kept, in characters. */
	const MAX_TEXT = 4000;

	/** How long a stashed failure or confirmation survives the redirect, in minutes. */
	const TRANSIENT_MINUTES = 30;

	/** Prefix for those stashes. */
	const TRANSIENT_PREFIX = 'wpcpm_mapp_';

	/** The redirect's argument when there is a stash: its id. Nothing typed is ever in the address. */
	const QUERY_STASH = 'wpcpm_mapp';

	/** The redirect's argument when there is no stash: the outcome, as itself. */
	const QUERY_OUTCOME = 'wpcpm_mapp_said';

	/** The posted array holding the answers, keyed by question key. */
	const FIELD_ANSWERS = 'wpcpm_mentor_application';

	/**
	 * The honeypot's field name. Not `website`, `url` or `profile`: a browser autofilling one of
	 * those for a person would file a genuine application as spam, and this form asks for a profile.
	 */
	const HONEYPOT = 'wpcpm_confirm_handle';

	/** The dwell token's field name. */
	const TOKEN_FIELD = 'wpcpm_mentor_application_token';

	/** The two emails the form sends, named for the mail log. */
	const MAIL_RECEIVED = 'mentor-application-received';
	const MAIL_NOTICE   = 'mentor-application-notice';

	/** The two pages the compliance declaration links, as the Airtable form's does: the license and the trademark policy. */
	const GPL_URL       = 'https://wordpress.org/about/license/';
	const TRADEMARK_URL = 'https://wordpressfoundation.org/trademark-policy/';

	/** The handbook's page about mentoring, which the page's introduction sends an applicant to first. */
	const CALL_URL = 'https://make.wordpress.org/handbook/wordpress-credits-contribution-internship-program/call-for-mentors/';

	/** Another application still in play holds this address or this WordPress.org username. */
	const SIGNAL_DUPLICATE = 'duplicate';

	/** A mentor the site knows holds this address or this WordPress.org username. */
	const SIGNAL_MENTOR = 'mentor';

	/**
	 * Hooks. The submit and the confirming link are registered for logged-out visitors as well,
	 * which is the whole point: an applicant has no account.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_' . self::ACTION_SUBMIT, array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_SUBMIT, array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_' . WPCPM_Mentor_Application::ACTION_VERIFY, array( __CLASS__, 'handle_verify' ) );
		add_action( 'admin_post_nopriv_' . WPCPM_Mentor_Application::ACTION_VERIFY, array( __CLASS__, 'handle_verify' ) );
		add_action( 'template_redirect', array( __CLASS__, 'no_cache' ) );
	}

	/**
	 * Register the shortcode, the block and the stylesheet.
	 */
	public static function register() {
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style( self::STYLE, WPCPM_PLUGIN_URL . 'assets/css/application.css', array(), WPCPM_VERSION );
		}

		if ( ! wp_script_is( 'wpcpm-forms', 'registered' ) ) {
			wp_register_script( 'wpcpm-forms', WPCPM_PLUGIN_URL . 'assets/js/forms.js', array(), WPCPM_VERSION, true );
		}

		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );

		$block_dir = WPCPM_PLUGIN_DIR . 'blocks/mentor-application';

		if ( function_exists( 'register_block_type' ) && file_exists( $block_dir . '/block.json' ) ) {
			register_block_type( $block_dir, array( 'render_callback' => array( __CLASS__, 'render_block' ) ) );
		}
	}

	/**
	 * Block render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		return self::render( is_array( $attributes ) ? $attributes : array() );
	}

	/**
	 * Create the form's page if it is missing, or adopt one at the slug, as it stands.
	 *
	 * A page this makes starts with the Airtable form's introduction, two paragraphs the owner edits
	 * on the page, then the form's block.
	 *
	 * **Left ungated on purpose**, as the other two forms' pages are: an absent access level means
	 * public, and the people this page is for have no account.
	 *
	 * @return int Page ID, or 0 on failure.
	 */
	public static function ensure_page() {
		$page_id = (int) get_option( self::OPT_PAGE );

		if ( $page_id ) {
			$existing = get_post( $page_id );

			if ( $existing instanceof WP_Post && 'trash' !== $existing->post_status ) {
				return $page_id;
			}
		}

		$existing = get_page_by_path( self::SLUG );

		if ( $existing instanceof WP_Post ) {
			update_option( self::OPT_PAGE, (int) $existing->ID, false );

			return (int) $existing->ID;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Become a mentor', 'wpcredits-program-manager' ),
				'post_name'    => self::SLUG,
				'post_content' => self::starting_content(),
			),
			true
		);

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return 0;
		}

		update_option( self::OPT_PAGE, (int) $page_id, false );

		return (int) $page_id;
	}

	/**
	 * What a page this plugin makes starts with: the Airtable form's introduction, in two paragraph
	 * blocks, then the form's block.
	 *
	 * @return string Block markup.
	 */
	public static function starting_content() {
		$paragraphs = array(
			esc_html__( 'We are looking for dedicated mentors to guide the next generation of contributors in WordPress Credits, a flagship educational program of the WordPress Foundation.', 'wpcredits-program-manager' ),
			sprintf(
				/* translators: %s: a link to the handbook's call for mentors page, its address as its text. */
				esc_html__( 'We cannot ensure sponsorship for mentors. By applying you confirm that you have at least two hours each week for your students. Before applying, read the call for mentors page: %s', 'wpcredits-program-manager' ),
				'<a href="' . esc_url( self::CALL_URL ) . '">' . esc_html( self::CALL_URL ) . '</a>'
			),
		);
		$blocks     = array();

		foreach ( $paragraphs as $paragraph ) {
			$blocks[] = "<!-- wp:paragraph -->\n<p>" . $paragraph . "</p>\n<!-- /wp:paragraph -->";
		}

		$blocks[] = '<!-- wp:' . self::BLOCK . ' /-->';

		return implode( "\n\n", $blocks );
	}

	/**
	 * The form's page address, while it is published.
	 *
	 * @return string
	 */
	public static function page_url() {
		$page_id = (int) get_option( self::OPT_PAGE );

		if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}

		return (string) get_permalink( $page_id );
	}

	/**
	 * Keep this one page out of every cache: it carries a nonce and a single-use dwell token, and a
	 * cached copy would hand one token to everybody.
	 */
	public static function no_cache() {
		$page_id = (int) get_option( self::OPT_PAGE );

		if ( ! $page_id || ! is_page( $page_id ) ) {
			return;
		}

		nocache_headers();

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- The name every page cache reads.
			define( 'DONOTCACHEPAGE', true );
		}
	}

	/*
	 * The questions
	 * --------------------------------------------------------------------
	 */

	/**
	 * The languages a mentor can mentor in: a fixed list, since the site holds none of its own. The
	 * names the program's Airtable Languages table held on 16 October 2026, each once, so the Airtable
	 * write of a later release finds its link by name.
	 *
	 * @return string[]
	 */
	public static function languages() {
		return array(
			'Bengali',
			'Catalan',
			'Croatian',
			'Egyptian Arabic',
			'English',
			'Finnish',
			'French',
			'German',
			'Gujarati',
			'Hausa',
			'Hindi',
			'Indonesian',
			'Iranian Persian',
			'Italian',
			'Japanese',
			'Javanese',
			'Korean',
			'Levantine Arabic',
			'Mandarin Chinese',
			'Marathi',
			'Modern Standard Arabic',
			'Nepali',
			'Nigerian Pidgin',
			'Polish',
			'Portuguese',
			'Russian',
			'Spanish',
			'Swahili',
			'Tagalog (Filipino)',
			'Tamil',
			'Telugu',
			'Thai',
			'Turkish',
			'Urdu',
			'Vietnamese',
			'Western Punjabi',
			'Wu Chinese (Shanghainese)',
			'Yue Chinese (Cantonese)',
		);
	}

	/**
	 * The countries the form offers: the site's own list, the one the institution form offers
	 * (`WPCPM_Countries::options()`), by name, A to Z. The name is what is stored.
	 *
	 * @return string[]
	 */
	public static function countries() {
		return array_values( array_unique( array_filter( array_map( 'trim', array_map( 'strval', WPCPM_Countries::options() ) ), 'strlen' ) ) );
	}

	/**
	 * The contribution teams the form offers: the site's own list, the one a student picks a team
	 * from (`WPCPM_Contribution_Teams::options()`), by name, A to Z. The name is what is stored.
	 *
	 * @return string[]
	 */
	public static function teams() {
		return array_values( array_unique( array_filter( array_map( 'trim', array_map( 'strval', WPCPM_Contribution_Teams::options() ) ), 'strlen' ) ) );
	}

	/**
	 * The questions, keyed by the site field each answer is kept under: the Airtable form's eleven, in
	 * its order and with its labels and help, as it read on 16 October 2026 (design, section 2.3).
	 * It does not ask the Mentors table's three sponsorship columns, and neither does this.
	 *
	 * The two boxes to tick carry a heading (`label`) and the words beside the box (`box`): the
	 * compliance declaration's are the form's statement word for word, with its two links, and the
	 * privacy policy's are the sentence the consent evidence records.
	 *
	 * @return array<string, array> Key => spec: `group`, `label`, `type`, `required`, and per type
	 *                              `maxlength`, `auto`, `help`, `min`, `max`, `box`.
	 */
	public static function fields() {
		return array(
			'name'      => array(
				'group'     => 'about',
				'label'     => __( 'Full name', 'wpcredits-program-manager' ),
				'type'      => 'text',
				'required'  => true,
				'maxlength' => 120,
				'auto'      => 'name',
			),
			'email'     => array(
				'group'    => 'about',
				'label'    => __( 'Email', 'wpcredits-program-manager' ),
				'type'     => 'email',
				'required' => true,
				'auto'     => 'email',
				'help'     => __( 'We send the acknowledgement here, with a link to confirm the address.', 'wpcredits-program-manager' ),
			),
			'profile'   => array(
				'group'     => 'about',
				'label'     => __( 'WordPress.org profile URL', 'wpcredits-program-manager' ),
				'type'      => 'profile',
				'required'  => true,
				'maxlength' => 200,
				'help'      => __( 'Your profile\'s address, which is https://profiles.wordpress.org/ followed by your username, or just your username. If you do not have a profile yet, create one at https://login.wordpress.org/register.', 'wpcredits-program-manager' ),
			),
			'country'   => array(
				'group'    => 'about',
				'label'    => __( 'Country', 'wpcredits-program-manager' ),
				'type'     => 'country',
				'required' => true,
			),
			'teacher'   => array(
				'group'    => 'about',
				'label'    => __( 'Are you a teacher from a WordPress Credits participating institution?', 'wpcredits-program-manager' ),
				'type'     => 'yesno',
				'required' => true,
			),
			'languages' => array(
				'group'    => 'mentoring',
				'label'    => __( 'Languages in which you could mentor students', 'wpcredits-program-manager' ),
				'type'     => 'languages',
				'required' => true,
				'help'     => __( 'Add as many as apply.', 'wpcredits-program-manager' ),
			),
			'expertise' => array(
				'group'    => 'mentoring',
				'label'    => __( 'In which WordPress contribution areas do you have expertise?', 'wpcredits-program-manager' ),
				'type'     => 'teams',
				'required' => false,
				'help'     => __( 'This refers to your experience as a WordPress.org contributor. Add as many as apply. If you are new to contributing, select nothing.', 'wpcredits-program-manager' ),
			),
			'hours'     => array(
				'group'    => 'mentoring',
				'label'    => __( 'Available hours per week to mentor students', 'wpcredits-program-manager' ),
				'type'     => 'number',
				'required' => true,
				'min'      => 1,
				'max'      => 40,
				'help'     => __( 'Mentoring asks for at least two hours a week.', 'wpcredits-program-manager' ),
			),
			'gpl'       => array(
				'group'    => 'program',
				'label'    => __( 'Compliance declaration', 'wpcredits-program-manager' ),
				'type'     => 'statement',
				'required' => true,
				'box'      => sprintf(
					/* translators: 1: an opening link tag to the GPL, 2: its closing tag, 3: an opening link tag to the WordPress trademark policy, 4: its closing tag. */
					__( 'I confirm that I comply with the %1$sGPL%2$s and do not violate, endorse, or work for companies that violate the %3$sWordPress trademark%4$s, misuse the WordPress name or logo, or distribute non-GPL-compliant derivatives of WordPress.', 'wpcredits-program-manager' ),
					'<a href="' . esc_url( self::GPL_URL ) . '">',
					'</a>',
					'<a href="' . esc_url( self::TRADEMARK_URL ) . '">',
					'</a>'
				),
			),
			'anything'  => array(
				'group'    => 'more',
				'label'    => __( 'Anything else you\'d like us to know?', 'wpcredits-program-manager' ),
				'type'     => 'textarea',
				'required' => false,
			),
			'consent'   => array(
				'group'    => 'consent',
				'label'    => __( 'Privacy policy', 'wpcredits-program-manager' ),
				'type'     => 'consent',
				'required' => true,
				'box'      => __( 'I confirm I have read the privacy policy and that my answers can be kept under it while the program reviews my application.', 'wpcredits-program-manager' ),
			),
		);
	}

	/**
	 * The groups the questions are asked in, in order.
	 *
	 * @return array<string, string> Group key => heading.
	 */
	public static function groups() {
		return array(
			'about'     => __( 'About you', 'wpcredits-program-manager' ),
			'mentoring' => __( 'What you would mentor', 'wpcredits-program-manager' ),
			'program'   => __( 'You and the program', 'wpcredits-program-manager' ),
			'more'      => __( 'Tell us more', 'wpcredits-program-manager' ),
			'consent'   => __( 'Before you send this', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * Clean one posted answer, or refuse it with a reason.
	 *
	 * @param string $key The question's key.
	 * @param mixed  $raw The posted value.
	 * @return array{ok:bool,value:mixed,problem:string}
	 */
	public static function clean( $key, $raw ) {
		$fields = self::fields();

		if ( ! isset( $fields[ $key ] ) ) {
			return self::refuse( 'unknown_field' );
		}

		$spec = $fields[ $key ];
		$type = $spec['type'];

		if ( in_array( $type, array( 'consent', 'statement' ), true ) ) {
			// A tick the strict way: "yes" is not one, and neither is an array.
			return self::accept( WPCPM_Form_Guard::consented( $raw ) );
		}

		if ( in_array( $type, array( 'languages', 'teams' ), true ) ) {
			return self::accept( self::picked( $raw, 'languages' === $type ? self::languages() : self::teams() ) );
		}

		if ( 'profile' === $type ) {
			if ( ! is_scalar( $raw ) ) {
				return self::refuse( 'not_scalar' );
			}

			$typed = trim( mb_substr( trim( (string) $raw ), 0, (int) $spec['maxlength'] ) );

			if ( '' === $typed ) {
				return self::accept( '' );
			}

			// A username, with or without one "@", or an address on WordPress.org, and nothing else:
			// the sync's rule below keeps the last part of any address, so a link to another site, or
			// a name typed in the box, would otherwise pass for a username.
			if ( 1 !== preg_match( '/^@?[A-Za-z0-9._-]+$/', $typed ) && ! self::is_profile_address( $typed ) ) {
				return self::refuse( 'bad_profile' );
			}

			// The mentors sync's own rule, so the username kept here is the one it would read from
			// the Airtable column.
			$handle = WPCPM_Mentors_Sync::wporg_username( $typed );

			return '' !== $handle ? self::accept( $handle ) : self::refuse( 'bad_profile' );
		}

		if ( 'country' === $type ) {
			$value = is_scalar( $raw ) ? trim( (string) $raw ) : '';

			if ( '' === $value ) {
				return self::accept( '' );
			}

			return in_array( $value, self::countries(), true ) ? self::accept( $value ) : self::refuse( 'bad_choice' );
		}

		if ( 'yesno' === $type ) {
			$cleaned = WPCPM_Field_Value::clean(
				$raw,
				array(
					'type'    => 'select',
					'choices' => array( 'Yes', 'No' ),
				)
			);

			return $cleaned['ok'] && null === $cleaned['value'] ? self::accept( '' ) : $cleaned;
		}

		if ( 'number' === $type ) {
			if ( ! is_scalar( $raw ) ) {
				return self::refuse( 'not_scalar' );
			}

			// A decimal, as the Airtable form's hours take one: a comma read as the point, held to
			// one place, and kept as the number it then reads, `2.5` or `3`.
			$typed = str_replace( ',', '.', trim( (string) $raw ) );

			if ( '' === $typed ) {
				return self::accept( '' );
			}

			if ( ! is_numeric( $typed ) ) {
				return self::refuse( 'not_a_number' );
			}

			$hours = round( (float) $typed, 1 );

			if ( $hours < $spec['min'] ) {
				return self::refuse( 'below_min' );
			}

			if ( $hours > $spec['max'] ) {
				return self::refuse( 'above_max' );
			}

			return self::accept( rtrim( rtrim( number_format( $hours, 1, '.', '' ), '0' ), '.' ) );
		}

		// The free text keeps what was typed or says why it cannot: the cleaner would take a run from
		// a "<" to a ">" out of it, as a tag, and an applicant writing about code would lose words.
		if ( 'textarea' === $type && WPCPM_Typed_Text::cleaner_loses( $raw, 'lines' ) ) {
			return self::refuse( 'markup' );
		}

		$rules = array( 'type' => 'textarea' === $type ? 'textarea' : $type );

		if ( isset( $spec['maxlength'] ) ) {
			$rules['maxlength'] = $spec['maxlength'];
		}

		if ( 'textarea' === $type ) {
			$rules['max_text'] = self::MAX_TEXT;
		}

		return WPCPM_Field_Value::clean( $raw, $rules );
	}

	/**
	 * The choices ticked, each once, in the list's own order; one the list does not offer is dropped
	 * rather than refusing the answer, since only a hand-made request can post one.
	 *
	 * @param mixed    $raw     The posted value: an array of choices, or one.
	 * @param string[] $choices The list.
	 * @return string[]
	 */
	private static function picked( $raw, array $choices ) {
		$posted = array();

		foreach ( (array) $raw as $one ) {
			if ( is_scalar( $one ) ) {
				$posted[] = trim( (string) $one );
			}
		}

		return array_values( array_intersect( $choices, $posted ) );
	}

	/**
	 * A cleaned value.
	 *
	 * @param mixed $value The value.
	 * @return array{ok:bool,value:mixed,problem:string}
	 */
	private static function accept( $value ) {
		return array(
			'ok'      => true,
			'value'   => $value,
			'problem' => '',
		);
	}

	/**
	 * A refused value, and why.
	 *
	 * @param string $problem Short key, in `WPCPM_Field_Value`'s vocabulary or this form's own.
	 * @return array{ok:bool,value:mixed,problem:string}
	 */
	private static function refuse( $problem ) {
		return array(
			'ok'      => false,
			'value'   => null,
			'problem' => (string) $problem,
		);
	}

	/**
	 * Whether a typed text is an address on WordPress.org: its host `profiles.wordpress.org`,
	 * `wordpress.org` or `www.wordpress.org`, in any case, the scheme left off or not.
	 *
	 * @param string $typed What was typed.
	 * @return bool
	 */
	private static function is_profile_address( $typed ) {
		return 1 === preg_match( '#^(?:https?://)?(?:profiles\.|www\.)?WordPress\.org(?:[/?\#]|$)#i', (string) $typed );
	}

	/**
	 * Clean every answer the form asks, walked from `fields()` and never from the request, so a key
	 * nobody asked about cannot become a stored answer. Consent never comes back as a value: no
	 * failure path can tick it again for the applicant.
	 *
	 * @param array $posted The posted answers, keyed by question key.
	 * @return array{values:array,problems:array}
	 */
	private static function clean_all( array $posted ) {
		$values   = array();
		$problems = array();

		foreach ( array_keys( self::fields() ) as $key ) {
			$clean = self::clean( $key, isset( $posted[ $key ] ) ? $posted[ $key ] : '' );

			if ( empty( $clean['ok'] ) ) {
				$problems[ $key ] = $clean['problem'];

				continue;
			}

			$values[ $key ] = $clean['value'];
		}

		unset( $values['consent'] );

		return array(
			'values'   => $values,
			'problems' => $problems,
		);
	}

	/**
	 * Add the requiredness problems, once, after everything is cleaned: a required question left
	 * blank, and a required statement left unticked.
	 *
	 * @param array $values   Cleaned values.
	 * @param array $problems Problems so far.
	 * @return array
	 */
	private static function add_required( array $values, array $problems ) {
		foreach ( self::fields() as $key => $spec ) {
			if ( 'consent' === $spec['type'] || isset( $problems[ $key ] ) || empty( $spec['required'] ) ) {
				continue;
			}

			$value = isset( $values[ $key ] ) ? $values[ $key ] : null;

			if ( 'statement' === $spec['type'] ) {
				if ( true !== $value ) {
					$problems[ $key ] = 'unticked';
				}

				continue;
			}

			if ( self::is_blank( $value ) ) {
				$problems[ $key ] = 'required';
			}
		}

		return $problems;
	}

	/**
	 * Whether a cleaned value counts as no answer.
	 *
	 * @param mixed $value The value.
	 * @return bool
	 */
	private static function is_blank( $value ) {
		if ( null === $value || false === $value ) {
			return true;
		}

		if ( is_array( $value ) ) {
			return empty( $value );
		}

		return '' === trim( (string) $value );
	}

	/*
	 * Drawing the page
	 * --------------------------------------------------------------------
	 */

	/**
	 * Whether the program is taking mentor applications on this site right now: a setting, because
	 * "on" means accepting writes from anybody on the internet.
	 *
	 * @return bool
	 */
	public static function is_open() {
		return (bool) WPCPM_Settings::get_value( 'mentor_applications_enabled', false );
	}

	/**
	 * Why the form cannot be drawn, or '' when it can.
	 *
	 * @return string
	 */
	private static function closed_reason() {
		if ( ! self::is_open() ) {
			return __( 'The mentor application form is switched off. Turn on "Applications from mentors" on the Students and mentors tab of WPCredits Program > Settings when the program is ready to take them.', 'wpcredits-program-manager' );
		}

		if ( '' === WPCPM_Form_Guard::policy_url() ) {
			return __( 'This form is not shown yet: applicants are asked to agree to the privacy policy, and this site has no published privacy policy page. Set one under Settings > Privacy.', 'wpcredits-program-manager' );
		}

		if ( empty( self::countries() ) ) {
			return __( 'This form is not shown yet: the countries list is empty, and every application names a country. Read the lists again on the Connection tab of WPCredits Program > Settings.', 'wpcredits-program-manager' );
		}

		if ( empty( self::teams() ) ) {
			return __( 'This form is not shown yet: the contribution teams list is empty, and every application names the teams its mentor knows. Run the mentors sync on the Mentors screen\'s Sync tab.', 'wpcredits-program-manager' );
		}

		return '';
	}

	/**
	 * Render the answer this request carries, the form, the confirmation, or nothing.
	 *
	 * The form is drawn for nobody while it cannot be (`closed_reason()`); an Administrator is told
	 * why in one sentence. The editor's preview is a static summary, because a live one would mint a
	 * nonce and a single-use token on every keystroke.
	 *
	 * @param array $atts Shortcode or block attributes.
	 * @return string
	 */
	public static function render( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'title'  => '',
				'thanks' => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE
		);

		wp_enqueue_style( self::STYLE );
		wp_enqueue_script( 'wpcpm-forms' );

		$closed = self::closed_reason();

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return '' !== $closed ? self::sentence( $closed ) : self::preview();
		}

		$stash   = self::read_stash();
		$outcome = isset( $stash['outcome'] ) ? (string) $stash['outcome'] : '';

		ob_start();

		if ( 'sent' === $outcome || 'sent-quiet' === $outcome ) {
			self::render_confirmation( $stash, 'sent' === $outcome, (string) $atts['thanks'] );
		} else {
			self::render_message( $outcome );
		}

		if ( '' !== $closed ) {
			if ( current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
				printf( '<p class="wpcpm-application__notice">%s</p>', esc_html( $closed ) );
			}
		} elseif ( ! in_array( $outcome, array( 'sent', 'sent-quiet', 'busy', 'verified', 'verified-already' ), true ) ) {
			self::render_form(
				isset( $stash['values'] ) && is_array( $stash['values'] ) ? $stash['values'] : array(),
				isset( $stash['problems'] ) && is_array( $stash['problems'] ) ? $stash['problems'] : array()
			);
		}

		$body = (string) ob_get_clean();

		if ( '' === $body ) {
			return '';
		}

		$title = '' !== $atts['title'] ? '<h2 class="wpcpm-application__title">' . esc_html( $atts['title'] ) . '</h2>' : '';

		return '<div class="wpcpm-application">' . $title . $body . '</div>';
	}

	/**
	 * One sentence in the page's wrapper.
	 *
	 * @param string $text What to say.
	 * @return string
	 */
	private static function sentence( $text ) {
		return '<div class="wpcpm-application"><p class="wpcpm-application__notice">' . esc_html( $text ) . '</p></div>';
	}

	/**
	 * The editor's static preview: what the form asks, without asking it.
	 *
	 * @return string
	 */
	private static function preview() {
		$out  = '<div class="wpcpm-application wpcpm-application--preview">';
		$out .= '<p class="wpcpm-application__notice">' . esc_html__( 'The public mentor application form is drawn here on the front end. This is a summary, not the form: the live form carries a single-use token that the editor would spend.', 'wpcredits-program-manager' ) . '</p>';
		$out .= '<ul class="wpcpm-application__groups">';

		foreach ( self::groups() as $group => $heading ) {
			$count = count(
				array_filter(
					self::fields(),
					static function ( $spec ) use ( $group ) {
						return $group === $spec['group'];
					}
				)
			);

			$out .= sprintf(
				'<li><strong>%1$s</strong> %2$s</li>',
				esc_html( $heading ),
				esc_html(
					sprintf(
						/* translators: %s: how many questions this group asks. */
						_n( '%s question', '%s questions', $count, 'wpcredits-program-manager' ),
						number_format_i18n( $count )
					)
				)
			);
		}

		return $out . '</ul></div>';
	}

	/**
	 * The thank-you, drawn from a stash already consumed: the owner's sentence when the block or the
	 * shortcode gives one (`thanks`), the plugin's otherwise, then the reference and the address.
	 *
	 * @param array  $stash        What the handler left behind.
	 * @param bool   $acknowledged Whether the acknowledgement went: false past the day's mail ceiling.
	 * @param string $thanks       The owner's thank-you sentence, or '' for the plugin's.
	 */
	private static function render_confirmation( array $stash, $acknowledged, $thanks = '' ) {
		$reference = isset( $stash['reference'] ) ? (string) $stash['reference'] : '';
		$email     = isset( $stash['email'] ) ? (string) $stash['email'] : '';
		$thanks    = trim( (string) $thanks );

		echo '<div class="wpcpm-application__done">';
		echo '<h3>' . esc_html( '' !== $thanks ? $thanks : __( 'Thank you for applying to mentor in the WordPress Credits Program. Your application is with us.', 'wpcredits-program-manager' ) ) . '</h3>';

		if ( '' !== $reference ) {
			printf(
				'<p class="wpcpm-application__reference">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the application reference, e.g. MAPP-2026-0007. */
						__( 'Your reference is %s. Please quote it if you write to us about this application.', 'wpcredits-program-manager' ),
						$reference
					)
				)
			);
		}

		if ( '' !== $email ) {
			printf(
				'<p>%s</p>',
				esc_html(
					$acknowledged
						? sprintf(
							/* translators: %s: the address the applicant gave. */
							__( 'We have sent an acknowledgement to %s with a link that confirms the address. Please follow it: the program writes to you about your application only once the address is confirmed. Every application is read by hand, and a reply can take several weeks.', 'wpcredits-program-manager' ),
							$email
						)
						: sprintf(
							/* translators: %s: the address the applicant gave. */
							__( 'We could not send the acknowledgement to %s today, so please keep the reference above. Your application is stored and will be read by hand; a reply can take several weeks.', 'wpcredits-program-manager' ),
							$email
						)
				)
			);
		}

		echo '</div>';
	}

	/**
	 * The outcomes a redirect can carry, and what each says. One map, so every slug the handlers
	 * stash has a sentence.
	 *
	 * @return array<string, array> Slug => `array( level, sentence )`.
	 */
	public static function outcomes() {
		return array(
			'again'            => array( 'error', __( 'Nothing has been sent yet. Please look at the answers marked below and send the form again.', 'wpcredits-program-manager' ) ),
			'consent'          => array( 'error', __( 'Nothing has been sent yet, and nothing has been stored. The program can only accept an application from somebody who confirms the privacy policy, so please tick the last box.', 'wpcredits-program-manager' ) ),
			'stale'            => array( 'error', __( 'This form had been open for a long time, so it was not sent. Your answers are still here: please send it again.', 'wpcredits-program-manager' ) ),
			'expired'          => array( 'error', __( 'This form had been open for a while and could not be sent. Your answers are still here: please send it again.', 'wpcredits-program-manager' ) ),
			'busy'             => array( 'error', __( 'Several applications have already been sent from here in the last hour, so this one was not. Please try again later.', 'wpcredits-program-manager' ) ),
			'lost'             => array( 'error', __( 'Something went wrong at our end and the application was not saved. Nothing has been sent. Please try once more.', 'wpcredits-program-manager' ) ),
			'closed'           => array( 'info', __( 'The program is not taking mentor applications through this form at the moment.', 'wpcredits-program-manager' ) ),
			'verified'         => array( 'success', __( 'Thank you. Your address is confirmed, and your application is with the program\'s team.', 'wpcredits-program-manager' ) ),
			'verified-already' => array( 'info', __( 'This address was already confirmed, so there is nothing more to do. Your application is with the program\'s team.', 'wpcredits-program-manager' ) ),
			'verify-failed'    => array( 'error', __( 'That confirmation link could not be read. It may have been broken across two lines by a mail program: please try the whole link again.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * Draw the sentence for an outcome, if it has one.
	 *
	 * @param string $outcome Outcome slug.
	 */
	private static function render_message( $outcome ) {
		$outcomes = self::outcomes();

		if ( '' === $outcome || ! isset( $outcomes[ $outcome ] ) ) {
			return;
		}

		printf(
			'<p class="wpcpm-application__message is-%1$s" role="status">%2$s</p>',
			esc_attr( $outcomes[ $outcome ][0] ),
			esc_html( $outcomes[ $outcome ][1] )
		);
	}

	/**
	 * Draw the form.
	 *
	 * @param array $values   Cleaned values from a failed attempt, keyed by question key.
	 * @param array $problems Problem slugs from that attempt, keyed by question key.
	 */
	private static function render_form( array $values, array $problems ) {
		printf(
			'<form class="wpcpm-application__form" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s" data-wpcpm-status="%3$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Sending...', 'wpcredits-program-manager' ),
			esc_attr__( 'Sending your application.', 'wpcredits-program-manager' )
		);
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SUBMIT ) );
		// Written out rather than printed by `wp_nonce_field()`: the dwell token is signed with it.
		printf( '<input type="hidden" name="_wpnonce" value="%s" />', esc_attr( wp_create_nonce( self::ACTION_SUBMIT ) ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::TOKEN_FIELD ), esc_attr( WPCPM_Form_Guard::token( self::DWELL_SCOPE, self::ACTION_SUBMIT ) ) );

		WPCPM_Form_Guard::render_honeypot( self::HONEYPOT );

		$fields = self::fields();

		foreach ( self::groups() as $group => $heading ) {
			printf( '<fieldset class="wpcpm-application__group"><legend>%s</legend>', esc_html( $heading ) );

			foreach ( $fields as $key => $spec ) {
				if ( $spec['group'] === $group ) {
					self::render_field( $key, $spec, isset( $values[ $key ] ) ? $values[ $key ] : null, isset( $problems[ $key ] ) ? (string) $problems[ $key ] : '' );
				}
			}

			echo '</fieldset>';
		}

		printf(
			'<p class="wpcpm-application__send"><button type="submit" class="wpcpm-button">%s</button><span class="wpcpm-application__busy" data-wpcpm-busy-status role="status"></span></p>',
			esc_html__( 'Send this application', 'wpcredits-program-manager' )
		);

		echo '</form>';
	}

	/**
	 * One question.
	 *
	 * @param string $key     The question's key.
	 * @param array  $spec    Its spec.
	 * @param mixed  $value   Its value from a failed attempt, or null.
	 * @param string $problem The problem slug from that attempt, or ''.
	 */
	private static function render_field( $key, array $spec, $value, $problem ) {
		$id       = 'wpcpm-mentor-application-' . $key;
		$name     = self::FIELD_ANSWERS . '[' . $key . ']';
		$type     = $spec['type'];
		$mark     = ! empty( $spec['required'] ) ? esc_html__( 'Required', 'wpcredits-program-manager' ) : '';
		$says     = array();
		$multiple = in_array( $type, array( 'languages', 'teams', 'yesno' ), true );

		if ( ! empty( $spec['help'] ) ) {
			$says[] = $id . '-help';
		}

		if ( '' !== $problem ) {
			$says[] = $id . '-problem';
		}

		// A required attribute only where one control is the whole answer: a list of boxes has none
		// that means "at least one", and the country's list is hidden behind its field once the
		// script runs, where a browser would refuse the form for a control nobody can see.
		$required    = ! empty( $spec['required'] ) && ! $multiple && 'country' !== $type ? ' required="required"' : '';
		$required_at = '' !== $mark ? ' <span class="wpcpm-field__required">' . $mark . '</span>' : '';
		$invalid     = '' !== $problem ? ' aria-invalid="true"' : '';
		$describedby = empty( $says ) ? '' : ' aria-describedby="' . esc_attr( implode( ' ', $says ) ) . '"';
		$wrapper     = $multiple || 'country' === $type ? 'div' : 'p';

		printf( '<%1$s class="wpcpm-field wpcpm-field--%2$s%3$s">', esc_attr( $wrapper ), esc_attr( $type ), '' !== $problem ? ' has-problem' : '' );

		if ( in_array( $type, array( 'consent', 'statement' ), true ) ) {
			// The heading, then the box with its words: the declaration's two links are the only
			// markup those words may carry.
			printf( '<span class="wpcpm-field__label" id="%1$s-label">%2$s%3$s</span>', esc_attr( $id ), esc_html( $spec['label'] ), $required_at ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from a literal and esc_html__() above.
			printf(
				'<span class="wpcpm-field__consent"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s%4$s%5$s /><label for="%1$s">%6$s</label></span>',
				esc_attr( $id ),
				esc_attr( $name ),
				checked( true === $value, true, false ),
				$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
				$describedby, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from the escaped id above.
				wp_kses(
					(string) $spec['box'],
					array(
						'a' => array(
							'href' => array(),
						),
					)
				)
			);

			if ( 'consent' === $type ) {
				self::render_policy_line();
			}
		} elseif ( $multiple ) {
			printf( '<span class="wpcpm-field__label" id="%1$s-label">%2$s%3$s</span>', esc_attr( $id ), esc_html( $spec['label'] ), $required_at ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from a literal and esc_html__() above.

			$choices = 'yesno' === $type
				? array(
					'Yes' => __( 'Yes', 'wpcredits-program-manager' ),
					'No'  => __( 'No', 'wpcredits-program-manager' ),
				)
				: array_combine( 'languages' === $type ? self::languages() : self::teams(), 'languages' === $type ? self::languages() : self::teams() );
			$picked  = (array) $value;

			printf( '<span class="wpcpm-field__choices" role="group" aria-labelledby="%1$s-label"%2$s>', esc_attr( $id ), $describedby ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from the escaped id above.

			foreach ( $choices as $choice => $label ) {
				printf(
					'<label class="wpcpm-field__check"><input type="%1$s" name="%2$s" value="%3$s"%4$s /> <span>%5$s</span></label>',
					'yesno' === $type ? 'radio' : 'checkbox',
					esc_attr( 'yesno' === $type ? $name : $name . '[]' ),
					esc_attr( (string) $choice ),
					checked( in_array( (string) $choice, array_map( 'strval', $picked ), true ), true, false ),
					esc_html( $label )
				);
			}

			echo '</span>';
		} elseif ( 'country' === $type ) {
			WPCPM_Dashboards::render_combo(
				array(
					'id'          => $id,
					'name'        => $name,
					'options'     => array_merge( array( '' => __( 'Choose a country', 'wpcredits-program-manager' ) ), array_combine( self::countries(), self::countries() ) ),
					'current'     => is_scalar( $value ) ? (string) $value : '',
					'label'       => $spec['label'],
					'mark'        => $mark,
					'describedby' => implode( ' ', $says ),
					/* translators: Shown in the empty country field, which takes typing. */
					'find'        => __( 'Type to find your country', 'wpcredits-program-manager' ),
					'none'        => __( 'No country has that name.', 'wpcredits-program-manager' ),
					/* translators: %s: how many countries the list shows, as a number. */
					'count'       => __( 'Countries in the list: %s', 'wpcredits-program-manager' ),
				)
			);
		} else {
			printf( '<label for="%1$s">%2$s%3$s</label>', esc_attr( $id ), esc_html( $spec['label'] ), $required_at ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from a literal and esc_html__() above.

			if ( 'textarea' === $type ) {
				printf(
					'<textarea id="%1$s" name="%2$s" rows="4" maxlength="%3$d"%4$s%5$s>%6$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					(int) self::MAX_TEXT,
					$invalid, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
					$describedby, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from the escaped id above.
					esc_textarea( is_scalar( $value ) ? (string) $value : '' )
				);
			} elseif ( 'number' === $type ) {
				printf(
					'<input type="number" id="%1$s" name="%2$s" value="%3$s" min="%4$d" max="%5$d" step="0.5" inputmode="decimal"%6$s%7$s%8$s />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( is_scalar( $value ) ? (string) $value : '' ),
					(int) $spec['min'],
					(int) $spec['max'],
					$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
					$invalid, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
					$describedby // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from the escaped id above.
				);
			} else {
				// A profile is a text box: a handle is not an address, and `type="url"` would refuse it.
				printf(
					'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" maxlength="%5$d"%6$s%7$s%8$s%9$s />',
					'email' === $type ? 'email' : 'text',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( is_scalar( $value ) ? (string) $value : '' ),
					(int) ( isset( $spec['maxlength'] ) ? $spec['maxlength'] : 200 ),
					isset( $spec['auto'] ) ? ' autocomplete="' . esc_attr( $spec['auto'] ) . '"' : '',
					'profile' === $type ? ' autocapitalize="none" spellcheck="false"' : '',
					$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
					$invalid . $describedby // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literals and the escaped id above.
				);
			}
		}

		if ( ! empty( $spec['help'] ) ) {
			printf( '<span class="wpcpm-field__hint" id="%1$s-help">%2$s</span>', esc_attr( $id ), esc_html( $spec['help'] ) );
		}

		if ( '' !== $problem ) {
			printf( '<span class="wpcpm-field__problem" id="%1$s-problem">%2$s</span>', esc_attr( $id ), esc_html( self::problem_text( $problem ) ) );
		}

		printf( '</%s>', esc_attr( $wrapper ) );
	}

	/**
	 * The line under the consent box, linking the policy the applicant agrees to.
	 */
	private static function render_policy_line() {
		$policy = WPCPM_Form_Guard::policy_url();

		if ( '' === $policy ) {
			return;
		}

		printf(
			'<span class="wpcpm-field__hint">%s</span>',
			wp_kses(
				sprintf(
					/* translators: %1$s: opening link tag to the privacy policy, %2$s: closing link tag. */
					__( 'You can read the privacy policy %1$shere%2$s.', 'wpcredits-program-manager' ),
					'<a href="' . esc_url( $policy ) . '" rel="noopener">',
					'</a>'
				),
				array(
					'a' => array(
						'href' => array(),
						'rel'  => array(),
					),
				)
			)
		);
	}

	/**
	 * What to say about one refused answer.
	 *
	 * @param string $problem Problem slug.
	 * @return string
	 */
	private static function problem_text( $problem ) {
		$said = array(
			'required'     => __( 'Please fill this in.', 'wpcredits-program-manager' ),
			'unticked'     => __( 'The program can only take mentors who can say this, so please tick it.', 'wpcredits-program-manager' ),
			'bad_email'    => __( 'That does not look like an email address.', 'wpcredits-program-manager' ),
			'bad_choice'   => __( 'Please choose one of the options offered.', 'wpcredits-program-manager' ),
			'bad_profile'  => __( 'Give your WordPress.org username or your profile\'s address.', 'wpcredits-program-manager' ),
			'markup'       => __( 'Please write without angle brackets or tags.', 'wpcredits-program-manager' ),
			'not_a_number' => __( 'Please give a number of hours.', 'wpcredits-program-manager' ),
			'below_min'    => __( 'Please give at least one hour.', 'wpcredits-program-manager' ),
			'above_max'    => __( 'Please give at most forty hours.', 'wpcredits-program-manager' ),
		);

		return isset( $said[ $problem ] ) ? $said[ $problem ] : __( 'That answer could not be read. Please try it again.', 'wpcredits-program-manager' );
	}

	/*
	 * The submit path
	 * --------------------------------------------------------------------
	 */

	/**
	 * Take one submission, in the sponsor form's order of checks, each numbered below.
	 *
	 * `wp_verify_nonce()` and never `check_admin_referer()`: an expired nonce is one more failure the
	 * form comes back from with the answers in it, not a 403 screen.
	 */
	public static function handle_submit() {
		$nonce  = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		$posted = array();

		if ( isset( $_POST[ self::FIELD_ANSWERS ] ) && is_array( $_POST[ self::FIELD_ANSWERS ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The nonce is verified two steps down, after the ceiling that must count a wrong one; every value is cleaned by its type in `clean()`.
			$posted = wp_unslash( $_POST[ self::FIELD_ANSWERS ] );
		}

		// 1. The per-actor ceiling, before anything writes: the one layer that counts a sender with
		// no account.
		if ( ! WPCPM_Form_Guard::claim_actor( WPCPM_Form_Guard::actor_key( self::ACTOR_PREFIX ), WPCPM_Form_Guard::PER_HOUR ) ) {
			self::bounce( 'busy' );
		}

		// 2. The nonce.
		if ( ! wp_verify_nonce( $nonce, self::ACTION_SUBMIT ) ) {
			self::bounce( 'expired', array( 'values' => self::clean_all( $posted )['values'] ) );
		}

		// 3. The switch, the policy and the two lists, none of which a form this site drew can post past.
		if ( '' !== self::closed_reason() ) {
			self::bounce( 'closed' );
		}

		$signals = array();
		$spam    = false;

		// 4. The honeypot: stored as spam, and the sender sees the ordinary confirmation.
		if ( WPCPM_Form_Guard::honeypot_filled( self::HONEYPOT ) ) {
			$spam      = true;
			$signals[] = 'honeypot';
		}

		// 5. The dwell token: stale bounces, fast holds, and anything else that is not ok is spam.
		$dwell = WPCPM_Form_Guard::check_token( self::DWELL_SCOPE, WPCPM_Request::posted_text( self::TOKEN_FIELD ) );

		if ( WPCPM_Form_Guard::TOKEN_STALE === $dwell ) {
			self::bounce( 'stale', array( 'values' => self::clean_all( $posted )['values'] ) );
		}

		if ( WPCPM_Form_Guard::TOKEN_FAST === $dwell ) {
			$signals[] = 'dwell-fast';
		} elseif ( WPCPM_Form_Guard::TOKEN_OK !== $dwell ) {
			$spam      = true;
			$signals[] = 'dwell';
		}

		// 6. Consent, a precondition: nothing is stored without it, not even as spam.
		$agreed = self::clean( 'consent', isset( $posted['consent'] ) ? $posted['consent'] : '' );

		if ( true !== $agreed['value'] ) {
			self::bounce( 'consent', array( 'values' => self::clean_all( $posted )['values'] ) );
		}

		// 7. The answers, walked from the questions and never from the request.
		$cleaned  = self::clean_all( $posted );
		$values   = $cleaned['values'];
		$problems = $cleaned['problems'];

		// 8. Requiredness, once, after cleaning, skipped for a row headed for the spam pile: telling a
		// bot which answer it missed is free tuition.
		if ( ! $spam ) {
			$problems = self::add_required( $values, $problems );

			if ( ! empty( $problems ) ) {
				self::bounce(
					'again',
					array(
						'values'   => $values,
						'problems' => $problems,
					)
				);
			}
		}

		// 9. The site-wide ceiling degrades rather than refusing: the row is kept and held.
		if ( ! WPCPM_Form_Guard::claim_site( self::SITE_KEY, WPCPM_Form_Guard::PER_DAY ) ) {
			$signals[] = 'site-ceiling';
		}

		$email  = isset( $values['email'] ) ? (string) $values['email'] : '';
		$handle = isset( $values['profile'] ) ? (string) $values['profile'] : '';

		// 10. Content scoring, which holds and never refuses.
		$signals = array_merge( $signals, self::score( $values ) );

		// 11. Duplicates, which hold here and are never merged.
		$signals = array_merge( $signals, self::duplicate_signals( $email, $handle ) );

		// 12. Storage.
		$state   = $spam ? WPCPM_Mentor_Application::STATE_SPAM : ( empty( $signals ) ? WPCPM_Mentor_Application::STATE_NEW : WPCPM_Mentor_Application::STATE_HELD );
		$fields  = self::fields();
		$post_id = WPCPM_Mentor_Application::store( $values, $state, $signals, WPCPM_Form_Guard::consent_evidence( (string) $fields['consent']['box'] ) );

		if ( ! $post_id ) {
			self::bounce( 'lost', array( 'values' => $values ) );
		}

		// 13. The two emails: the applicant hears for any row that is not spam, since a held row is
		// one a person reads first and never one that is over; the Administrators hear of a new
		// row, which is what holding spares them. A spam row's sender sees the same page as anybody.
		$suppressed = false;

		if ( WPCPM_Mentor_Application::STATE_SPAM !== $state ) {
			if ( WPCPM_Form_Guard::claim_mail( self::MAIL_KEY, WPCPM_Form_Guard::MAIL_PER_DAY ) ) {
				self::mail_applicant( $post_id );
			} else {
				update_post_meta( $post_id, WPCPM_Mentor_Application::META_SIGNALS, array_values( array_unique( array_merge( $signals, array( 'mail-ceiling' ) ) ) ) );

				$suppressed = true;
			}
		}

		if ( WPCPM_Mentor_Application::STATE_NEW === $state ) {
			self::mail_administrators( $post_id );
		}

		self::bounce(
			$suppressed ? 'sent-quiet' : 'sent',
			array(
				'reference' => WPCPM_Mentor_Application::reference( $post_id ),
				'email'     => $email,
			)
		);
	}

	/**
	 * What the content of a submission says about it: matched against the site's comment
	 * disallowed list, or carrying too many links. Each holds a row for a person; none refuses it.
	 *
	 * @param array $values Cleaned values.
	 * @return string[]
	 */
	private static function score( array $values ) {
		$signals = array();
		$prose   = isset( $values['anything'] ) ? (string) $values['anything'] : '';
		$name    = isset( $values['name'] ) ? (string) $values['name'] : '';
		$email   = isset( $values['email'] ) ? (string) $values['email'] : '';

		if ( function_exists( 'wp_check_comment_disallowed_list' ) && wp_check_comment_disallowed_list( $name, $email, '', $prose, WPCPM_Form_Guard::client_ip(), WPCPM_Form_Guard::user_agent() ) ) {
			$signals[] = 'disallowed';
		}

		if ( WPCPM_Form_Guard::too_many_links( $prose, WPCPM_Form_Guard::MAX_LINKS ) ) {
			$signals[] = 'links';
		}

		return $signals;
	}

	/**
	 * Whether this address or this username is already in play, read from the site's own data alone
	 * (design, section 4): another application in the queue or waiting for the course, and a mentor
	 * the site knows. Each is a flag that holds the row.
	 *
	 * @param string $email  The address given.
	 * @param string $handle The WordPress.org username given, normalized.
	 * @return string[]
	 */
	private static function duplicate_signals( $email, $handle ) {
		$signals = array();
		$hash    = WPCPM_Mentor_Application::email_hash( $email );
		$handle  = strtolower( trim( (string) $handle ) );
		$live    = array_merge( WPCPM_Mentor_Application::open_states(), array( WPCPM_Mentor_Application::STATE_SELECTED ) );

		foreach ( WPCPM_Mentor_Application::applications( $live ) as $post ) {
			$same_email  = '' !== $hash && (string) get_post_meta( (int) $post->ID, WPCPM_Mentor_Application::META_EMAIL, true ) === $hash;
			$same_handle = '' !== $handle && strtolower( WPCPM_Mentor_Application::handle_of( $post ) ) === $handle;

			if ( $same_email || $same_handle ) {
				$signals[] = self::SIGNAL_DUPLICATE;

				break;
			}
		}

		if ( self::known_mentor( $email, $handle ) ) {
			$signals[] = self::SIGNAL_MENTOR;
		}

		return $signals;
	}

	/**
	 * Whether a mentor the site knows holds this address or this username: an account of any
	 * standing, current or former, with the Mentor role or a mentor record ID, found by its address,
	 * its username (the mentors sync names a mentor's account after their WordPress.org username) or
	 * the profile the sync keeps on it; or a mentor the last mentors sync read from Airtable, found by
	 * the username alone, since the sync's list keeps no address (`sponsorship()`): a mentor row with
	 * no account on the site is not matched by its address.
	 *
	 * @param string $email  The address given.
	 * @param string $handle The WordPress.org username given, lowercased.
	 * @return bool
	 */
	private static function known_mentor( $email, $handle ) {
		$candidates = array();

		if ( '' !== $email ) {
			$candidates[] = get_user_by( 'email', $email );
		}

		if ( '' !== $handle ) {
			$candidates[] = get_user_by( 'login', $handle );
		}

		foreach ( $candidates as $user ) {
			if ( $user instanceof WP_User && self::is_mentor_account( $user ) ) {
				return true;
			}
		}

		if ( '' === $handle ) {
			return false;
		}

		foreach ( (array) get_users(
			array(
				'meta_key' => WPCPM_Mentors_Sync::META_PROFILE,
				'fields'   => 'all',
			)
		) as $user ) {
			if ( $user instanceof WP_User && WPCPM_Mentors_Sync::wporg_username( (string) get_user_meta( $user->ID, WPCPM_Mentors_Sync::META_PROFILE, true ) ) === $handle ) {
				return true;
			}
		}

		foreach ( WPCPM_Mentors_Sync::sponsorship() as $row ) {
			if ( is_array( $row ) && isset( $row['profile'] ) && WPCPM_Mentors_Sync::wporg_username( (string) $row['profile'] ) === $handle ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether an account is a mentor's, of any standing.
	 *
	 * @param WP_User $user The account.
	 * @return bool
	 */
	private static function is_mentor_account( WP_User $user ) {
		return in_array( WPCPM_Roles::ROLE_MENTOR, (array) $user->roles, true ) || '' !== trim( (string) get_user_meta( $user->ID, WPCPM_Mentors_Sync::META_RECORD_ID, true ) );
	}

	/**
	 * The confirming link from the acknowledgement: a GET, for logged-out visitors too, answered on
	 * the form's page. One answer for an application that is not one and for a signature that does
	 * not match, so a stranger cannot walk the IDs.
	 */
	public static function handle_verify() {
		$post_id = WPCPM_Request::id( 'app' );
		$post    = $post_id > 0 ? WPCPM_Mentor_Application::application( $post_id ) : null;

		if ( null === $post || ! hash_equals( WPCPM_Mentor_Application::verify_signature( $post_id ), WPCPM_Request::text( 't' ) ) ) {
			self::bounce( 'verify-failed' );
		}

		if ( ! WPCPM_Mentor_Application::confirm( $post_id ) ) {
			self::bounce( 'verified-already' );
		}

		self::bounce( 'verified' );
	}

	/*
	 * The stash and the emails
	 * --------------------------------------------------------------------
	 */

	/**
	 * The form as `WPCPM_Form_Stash` needs it described.
	 *
	 * @return array
	 */
	private static function stash_form() {
		return array(
			'url'           => self::page_url(),
			'outcomes'      => array_keys( self::outcomes() ),
			'query_outcome' => self::QUERY_OUTCOME,
			'query_stash'   => self::QUERY_STASH,
			'prefix'        => self::TRANSIENT_PREFIX,
			'minutes'       => self::TRANSIENT_MINUTES,
			'one_shot'      => array( 'sent', 'sent-quiet', 'verified', 'verified-already', 'verify-failed' ),
		);
	}

	/**
	 * Redirect back with the outcome, anything typed behind a transient.
	 *
	 * @param string $outcome One of `outcomes()`, or `sent` / `sent-quiet`.
	 * @param array  $stash   What the page needs to draw.
	 */
	private static function bounce( $outcome, array $stash = array() ) {
		WPCPM_Form_Stash::bounce(
			self::stash_form(),
			$outcome,
			$stash,
			static function ( $value ) {
				return self::is_blank( $value );
			}
		);
	}

	/**
	 * Read the stash this request carries.
	 *
	 * @return array
	 */
	private static function read_stash() {
		return WPCPM_Form_Stash::read( self::stash_form() );
	}

	/**
	 * The Reply-To an email to an applicant carries: the program's contact address, while Contact
	 * address in emails holds one, so an applicant who replies, as the emails ask, reaches the
	 * program and not the site's own sender.
	 *
	 * @return string[]
	 */
	public static function reply_headers() {
		$contact = WPCPM_Mail_Templates::program_contact();

		return '' !== $contact ? array( 'Reply-To: ' . $contact ) : array();
	}

	/**
	 * Acknowledge the application, with the link that confirms the address. Public, as the one way
	 * it is sent: the submit path sends it (step 13), and so does Put back in the queue on the
	 * Mentors screen, once, to a row the form's checks filed as spam or one past the day's limit,
	 * which never had it.
	 *
	 * @param int $post_id The application.
	 * @return bool Whether the email was handed off.
	 */
	public static function mail_applicant( $post_id ) {
		$post = WPCPM_Mentor_Application::application( $post_id );

		if ( null === $post ) {
			return false;
		}

		$email  = WPCPM_Mentor_Application::email_of( $post );
		$values = array(
			'site_name'       => WPCPM_Mail::site_name(),
			'applicant_name'  => WPCPM_Mentor_Application::name_of( $post ),
			'reference'       => WPCPM_Mentor_Application::reference_of( $post ),
			'verify_link'     => WPCPM_Mentor_Application::verify_url( $post_id ),
			'applicant_email' => $email,
		);

		$build = static function () use ( $values ) {
			return WPCPM_Mail_Templates::mail( self::MAIL_RECEIVED, $values, array( 'headers' => self::reply_headers() ) );
		};

		// No locale is named: the form asks no language, so the page's own is the applicant's.
		return WPCPM_Mail::send_to( $email, self::MAIL_RECEIVED, $build );
	}

	/**
	 * Tell the Administrators there is an application to read: who applied, their profile and the
	 * way to the queue, and none of their other answers. To the addresses the `mentor_notify`
	 * setting names, or to every Administrator when it names none.
	 *
	 * @param int $post_id The application.
	 * @return int How many were told.
	 */
	private static function mail_administrators( $post_id ) {
		$post = WPCPM_Mentor_Application::application( $post_id );

		if ( null === $post ) {
			return 0;
		}

		$values = array(
			'site_name'      => WPCPM_Mail::site_name(),
			'reference'      => WPCPM_Mentor_Application::reference_of( $post ),
			'applicant_name' => WPCPM_Mentor_Application::name_of( $post ),
			'profile_link'   => WPCPM_Mentor_Application::profile_url( WPCPM_Mentor_Application::handle_of( $post ) ),
			'queue_link'     => admin_url( 'admin.php?page=wpcpm-mentors&tab=queue' ),
		);

		$build = static function () use ( $values ) {
			return WPCPM_Mail_Templates::mail( self::MAIL_NOTICE, $values );
		};

		return WPCPM_Institutions::notify_managers( self::MAIL_NOTICE, $build, 'mentor_notify' );
	}
}
```

Create the block, the sponsor block's three files with the names changed and a second text control, the thank-you sentence:

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "wpcpm/mentor-application",
	"version": "1.122.31",
	"title": "Mentor Application Form",
	"category": "widgets",
	"icon": "welcome-learn-more",
	"description": "The public form a person fills in to apply to mentor in the WordPress Credits Program.",
	"keywords": [ "mentor", "application", "apply", "wpcredits" ],
	"textdomain": "wpcredits-program-manager",
	"attributes": {
		"title": {
			"type": "string",
			"default": ""
		},
		"thanks": {
			"type": "string",
			"default": ""
		}
	},
	"supports": {
		"html": false,
		"multiple": false,
		"align": [ "wide", "full" ]
	},
	"editorScript": "file:./editor.js"
}
```

```js
/**
 * Editor script for the "Mentor Application Form" block.
 *
 * Written in plain ES5 against the wp.* globals so the plugin ships without a
 * build step. The block is server-rendered, and what the server renders for the
 * editor is deliberately not the form: a live preview would mint a nonce and a
 * single-use dwell token on every keystroke that reloads the block, tokens
 * nobody will ever post and each one claiming a row. So the preview here is the
 * static summary the render callback returns under REST_REQUEST: the groups of
 * questions and how many each asks. Beside the optional heading, the inspector
 * takes the owner's thank-you sentence, shown once an application is sent.
 */
( function ( blocks, blockEditor, components, element, i18n, serverSideRender ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var ServerSideRender = serverSideRender;

	blocks.registerBlockType( 'wpcpm/mentor-application', {
		edit: function ( props ) {
			var blockProps = blockEditor.useBlockProps();

			return el(
				'div',
				blockProps,
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __( 'Heading', 'wpcredits-program-manager' ) },
						el( components.TextControl, {
							label: __( 'Optional heading', 'wpcredits-program-manager' ),
							help: __( 'Shown above the form. Leave blank to hide it.', 'wpcredits-program-manager' ),
							value: props.attributes.title || '',
							onChange: function ( value ) {
								props.setAttributes( { title: value } );
							},
							__nextHasNoMarginBottom: true,
						} )
					),
					el(
						components.PanelBody,
						{ title: __( 'Thank you', 'wpcredits-program-manager' ) },
						el( components.TextControl, {
							label: __( 'Thank-you sentence', 'wpcredits-program-manager' ),
							help: __( 'Shown to an applicant once the form is sent, above their reference. Leave blank for the plugin\'s own sentence.', 'wpcredits-program-manager' ),
							value: props.attributes.thanks || '',
							onChange: function ( value ) {
								props.setAttributes( { thanks: value } );
							},
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el(
					components.Disabled,
					null,
					el( ServerSideRender, {
						block: 'wpcpm/mentor-application',
						attributes: props.attributes,
						EmptyResponsePlaceholder: function () {
							return el(
								components.Placeholder,
								{ label: __( 'Mentor Application Form', 'wpcredits-program-manager' ) },
								__(
									'Nothing to preview. The form is not shown until the site has a published privacy policy and the program is taking mentor applications.',
									'wpcredits-program-manager'
								)
							);
						},
					} )
				)
			);
		},

		// Server-rendered: nothing is stored in post content.
		save: function () {
			return null;
		},
	} );
}(
	window.wp.blocks,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.element,
	window.wp.i18n,
	window.wp.serverSideRender
) );
```

```php
<?php
/**
 * Script dependencies for the mentor application block editor script.
 *
 * Hand-written rather than generated: editor.js is plain ES5 against the `wp.*`
 * globals, so there is no build step to produce this file. WordPress reads it
 * because block.json points `editorScript` at `file:./editor.js`.
 *
 * @package WPCreditsProgramManager
 */

return array(
	'dependencies' => array(
		'wp-blocks',
		'wp-block-editor',
		'wp-components',
		'wp-element',
		'wp-i18n',
		'wp-server-side-render',
	),
	'version'      => '1.122.31',
);
```

The country field's mark and description, the page's address under the switch, the styles, and the wiring:

```diff
diff --git a/assets/css/application.css b/assets/css/application.css
--- a/assets/css/application.css
+++ b/assets/css/application.css
@@ -265,3 +265,115 @@
 .wpcpm-field input[type="file"] {
 	max-width: 100%;
 }
+
+/*
+ * The mentor application form's country (1.122.31): the one field for a long list, the combobox
+ * assets/js/switcher.js shows in the select's place, as the dashboards' Viewing as switcher draws it
+ * (dashboard.css), value for value, under this form's class: this page loads none of the dashboard
+ * stylesheets. An author `display` beats the browser's `[hidden]`, so the hidden state is said again.
+ */
+.wpcpm-application .wpcpm-field select[hidden],
+.wpcpm-application .wpcpm-dashboard__switcher-combo[hidden],
+.wpcpm-application .wpcpm-dashboard__switcher-list[hidden] {
+	display: none;
+}
+
+.wpcpm-application .wpcpm-dashboard__switcher-combo {
+	box-sizing: border-box;
+	max-width: 100%;
+	position: relative;
+	width: 100%;
+}
+
+/* The field: the form's own text box, with the WordPress chevron 8px in from its edge and room kept
+   for it. */
+.wpcpm-application .wpcpm-dashboard__switcher-input {
+	background-image: url( "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z'/%3E%3C/svg%3E" );
+	background-position: right 8px center;
+	background-repeat: no-repeat;
+	background-size: 20px 20px;
+	padding-right: 34px;
+}
+
+/* Its list: under the field, at its width, over what follows, in a pair of colors that read on each
+   other whatever the page's are. */
+.wpcpm-application .wpcpm-dashboard__switcher-list {
+	background: Canvas;
+	border: 1px solid var( --wpcpm-control-border, rgba( 128, 128, 128, 0.9 ) );
+	border-radius: 4px;
+	box-shadow: 0 4px 12px rgba( 0, 0, 0, 0.12 );
+	box-sizing: border-box;
+	color: CanvasText;
+	left: 0;
+	list-style: none;
+	margin: 0;
+	max-height: 24em;
+	overflow-y: auto;
+	padding: 0;
+	position: absolute;
+	top: calc( 100% + 2px );
+	width: 100%;
+	z-index: 10;
+}
+
+.wpcpm-application .wpcpm-dashboard__switcher-option,
+.wpcpm-application .wpcpm-dashboard__switcher-empty {
+	margin: 0;
+	overflow-wrap: anywhere;
+	padding: 0.375em 0.5em;
+}
+
+.wpcpm-application .wpcpm-dashboard__switcher-option {
+	cursor: pointer;
+}
+
+.wpcpm-application .wpcpm-dashboard__switcher-option:hover {
+	background: rgba( 128, 128, 128, 0.15 );
+}
+
+/* The highlighted row, the one Enter picks. */
+.wpcpm-application .wpcpm-dashboard__switcher-option.is-active {
+	background: Highlight;
+	color: HighlightText;
+}
+
+/* The country picked, which the select holds: by weight, so it is not told by a color alone. */
+.wpcpm-application .wpcpm-dashboard__switcher-option.is-current {
+	font-weight: 600;
+}
+
+.wpcpm-application .wpcpm-dashboard__switcher-empty {
+	opacity: 0.72;
+}
+
+@media ( forced-colors: active ) {
+
+	.wpcpm-application .wpcpm-dashboard__switcher-option.is-active {
+		background: Highlight;
+		color: HighlightText;
+		forced-color-adjust: none;
+	}
+}
+
+/* For a screen reader: how many countries the list shows, or that none match. */
+.wpcpm-application .wpcpm-dashboard__switcher-status {
+	border: 0;
+	clip: rect( 0 0 0 0 );
+	clip-path: inset( 50% );
+	height: 1px;
+	margin: -1px;
+	overflow: hidden;
+	padding: 0;
+	position: absolute;
+	white-space: nowrap;
+	width: 1px;
+}
+
+/* The long lists of boxes, the languages and the contribution teams: in columns as wide as the
+   longest name needs, so a phone shows one and a wide screen several. */
+.wpcpm-field--languages .wpcpm-field__choices,
+.wpcpm-field--teams .wpcpm-field__choices {
+	display: grid;
+	gap: 0 1.25em;
+	grid-template-columns: repeat( auto-fill, minmax( 13em, 1fr ) );
+}
diff --git a/includes/class-wpcpm-dashboards.php b/includes/class-wpcpm-dashboards.php
--- a/includes/class-wpcpm-dashboards.php
+++ b/includes/class-wpcpm-dashboards.php
@@ -364,19 +364,25 @@ class WPCPM_Dashboards {
 	 *     @type string     $none    What the field's list says when nothing matches what was typed.
 	 *     @type string     $count   The status line's sentence for how many entries the list shows,
 	 *                              with %s for the number; the Viewing as sentence when empty.
+	 *     @type string     $mark    Words after the label, in the forms' "Required" mark, or '' for
+	 *                              none (the mentor application form's country, 1.122.31).
+	 *     @type string     $describedby The ids of a hint and a problem the select and the field are
+	 *                              described by, space-separated, or '' for none.
 	 * }
 	 */
 	public static function render_combo( array $args ) {
 		$args = array_merge(
 			array(
-				'id'      => '',
-				'name'    => '',
-				'options' => array(),
-				'current' => '',
-				'label'   => '',
-				'find'    => '',
-				'none'    => '',
-				'count'   => '',
+				'id'          => '',
+				'name'        => '',
+				'options'     => array(),
+				'current'     => '',
+				'label'       => '',
+				'find'        => '',
+				'none'        => '',
+				'count'       => '',
+				'mark'        => '',
+				'describedby' => '',
 			),
 			$args
 		);
@@ -401,11 +407,14 @@ class WPCPM_Dashboards {
 			$count = __( 'Names in the list: %s', 'wpcredits-program-manager' );
 		}
 
-		printf( '<label for="%1$s" id="%2$s">%3$s</label> ', esc_attr( $id ), esc_attr( $named ), esc_html( $args['label'] ) );
+		$mark        = '' !== (string) $args['mark'] ? ' <span class="wpcpm-field__required">' . esc_html( $args['mark'] ) . '</span>' : '';
+		$describedby = '' !== (string) $args['describedby'] ? ' aria-describedby="' . esc_attr( $args['describedby'] ) . '"' : '';
+
+		printf( '<label for="%1$s" id="%2$s">%3$s%4$s</label> ', esc_attr( $id ), esc_attr( $named ), esc_html( $args['label'] ), $mark ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with esc_html() above.
 		// Not put back as it was left: a browser that restores a form after Back would set the hidden
 		// list on the name picked last while the field reads the page's own, and Show would open
 		// somebody else's page.
-		printf( '<select name="%1$s" id="%2$s" autocomplete="off">', esc_attr( $args['name'] ), esc_attr( $id ) );
+		printf( '<select name="%1$s" id="%2$s" autocomplete="off"%3$s>', esc_attr( $args['name'] ), esc_attr( $id ), $describedby ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with esc_attr() above.
 
 		foreach ( (array) $args['options'] as $value => $label ) {
 			printf(
@@ -423,10 +432,11 @@ class WPCPM_Dashboards {
 		// the field brings its list.
 		echo '<div class="wpcpm-dashboard__switcher-combo" hidden>';
 		printf(
-			'<input type="text" id="%1$s" class="wpcpm-dashboard__switcher-input" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="%2$s" autocomplete="off" spellcheck="false" placeholder="%3$s" />',
+			'<input type="text" id="%1$s" class="wpcpm-dashboard__switcher-input" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="%2$s" autocomplete="off" spellcheck="false" placeholder="%3$s"%4$s />',
 			esc_attr( $field ),
 			esc_attr( $list ),
-			esc_attr( $args['find'] )
+			esc_attr( $args['find'] ),
+			$describedby // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with esc_attr() above.
 		);
 		printf( '<ul id="%1$s" class="wpcpm-dashboard__switcher-list" role="listbox" aria-labelledby="%2$s" hidden></ul>', esc_attr( $list ), esc_attr( $named ) );
 		// Empty until the list opens: a status region is read out when its text changes. Both
diff --git a/includes/class-wpcpm-settings-screen.php b/includes/class-wpcpm-settings-screen.php
--- a/includes/class-wpcpm-settings-screen.php
+++ b/includes/class-wpcpm-settings-screen.php
@@ -1089,8 +1089,8 @@ class WPCPM_Settings_Screen {
 	 * it takes applications, who is told of each, the link the selection email gives, and when a
 	 * selected application lapses.
 	 *
-	 * Drawn as the Sponsors tab draws its form's switch: off by default, and the privacy policy named on
-	 * the same row because the form shows nothing without one.
+	 * Drawn as the Sponsors tab draws its form's switch: off by default, the privacy policy named on
+	 * the same row because the form shows nothing without one, and the page's address under it.
 	 *
 	 * @param array $settings Current settings.
 	 */
@@ -1102,6 +1102,7 @@ class WPCPM_Settings_Screen {
 		);
 
 		$policy_url   = function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '';
+		$apply_url    = class_exists( 'WPCPM_Mentor_Application_Form' ) ? (string) WPCPM_Mentor_Application_Form::page_url() : '';
 		$applications = __( 'Applications from mentors', 'wpcredits-program-manager' );
 
 		printf(
@@ -1114,6 +1115,7 @@ class WPCPM_Settings_Screen {
 				? '<p class="description wpcpm-warning">' . esc_html__( 'No privacy policy page is set, so the form shows nothing to the public however this is switched. Publish one and choose it under Settings > Privacy.', 'wpcredits-program-manager' ) . '</p>'
 				: ''
 		);
+		WPCPM_Settings_Rows::page_line( $apply_url );
 		WPCPM_Settings_Rows::close_row( $applications, __( 'Every application is stored for an Administrator to decide on the Mentors screen\'s Waiting for review tab, and nothing is written to Airtable. While this is off, the page shows the public nothing, and an Administrator one sentence saying why.', 'wpcredits-program-manager' ) );
 
 		WPCPM_Settings_Rows::reviewers_row(
diff --git a/includes/modules/class-wpcpm-mentors.php b/includes/modules/class-wpcpm-mentors.php
--- a/includes/modules/class-wpcpm-mentors.php
+++ b/includes/modules/class-wpcpm-mentors.php
@@ -122,8 +122,9 @@ class WPCPM_Mentors extends WPCPM_Sync_Module {
 		WPCPM_Mentor_Calls::init();
 		WPCPM_Call_Calendar::init();
 		WPCPM_Group_Sessions::init();
-		// The applications to mentor (1.122.31): the post type they are kept in.
+		// The applications to mentor (1.122.31): the post type they are kept in, and the public form.
 		WPCPM_Mentor_Application::init();
+		WPCPM_Mentor_Application_Form::init();
 
 		// The hourly call reminders, put back on the clock whenever they are missing, as the
 		// sync above does for its own job: the activation hook is the only other caller and
@@ -151,6 +152,8 @@ class WPCPM_Mentors extends WPCPM_Sync_Module {
 		WPCPM_Mentors_Sync::schedule();
 		WPCPM_Mentor_Calls::schedule();
 		WPCPM_Mentors_Dashboard::ensure_page();
+		// The application form's page (1.122.31), and deliberately not gated: see its `ensure_page()`.
+		WPCPM_Mentor_Application_Form::ensure_page();
 	}
 
 	/**
diff --git a/uninstall.php b/uninstall.php
--- a/uninstall.php
+++ b/uninstall.php
@@ -102,6 +102,7 @@ require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-call-ca
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-group-sessions.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentor-engagement.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentor-application.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentor-application-form.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-institutions.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-countries.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-institutions-index.php';
diff --git a/wpcredits-program-manager.php b/wpcredits-program-manager.php
--- a/wpcredits-program-manager.php
+++ b/wpcredits-program-manager.php
@@ -92,6 +92,7 @@ require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-call-calendar.php'
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-group-sessions.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-engagement.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-form.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-countries.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';
```

- [ ] **Step 4: Run them to verify they pass**

Run: the five suites of step 2.

```text
$ php bin/test-mentor-application-form.php
ALL PASS (75 checks)
[exit 0]
$ php bin/test-combo-field.php
ALL PASS (13 checks)
[exit 0]
$ php bin/test-mail-catalog.php
ALL PASS (26 checks)
[exit 0]
$ php bin/test-mentors-screen.php
ALL PASS (170 checks)
[exit 0]
$ php bin/test-settings.php
ALL PASS
[exit 0]
```

- [ ] **Step 5: The checks every task ends with**

```text
battery done
155 classes, 14763 references checked, 87 form handlers scanned - all resolve
158 PHP files, 8 blocks, 4 track seeds, 23 fixture and tooling files, the guides and the readme: US English throughout.
278 annotations, 0 dead.
Ran 134 scripts from bin/, each in a PHP process of its own, and every one exited 0.
The battery leaves the temp directory as it found it.
78 warnings, no errors.
```

- [ ] **Step 6: Commit**

```bash
git add includes/modules/class-wpcpm-mentor-application-form.php blocks/mentor-application assets/css/application.css includes/class-wpcpm-dashboards.php includes/class-wpcpm-settings-screen.php includes/modules/class-wpcpm-mentors.php wpcredits-program-manager.php uninstall.php bin/test-mentor-application-form.php bin/test-combo-field.php bin/test-mail-catalog.php bin/test-mentors-screen.php bin/test-settings.php
git commit -m "Mentor applications: the form at /become-a-mentor/, guarded as the sponsor form is, its acknowledgement with the confirming link, and the Administrators' notice" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 5: The Waiting for review tab, its five views, its seven decisions and the applications recently decided

The queue on the Mentors screen (spec, sections 3 decisions 3 and 4, and 5): the tab first in the bar, and the one the screen opens on while applications wait; the views Waiting, Asked for more information, Second opinion, Selected (course pending) and Lapsed; each row whole, with the teacher answer and no sponsorship, since the form asks none; and Select, Ask for more information, Second opinion, Decline, Mark as spam, and Withdraw on a selected row. A decision that emails the applicant refuses an address nobody confirmed; Select needs the course link; a note stays on the site.

Below the rows, in every view, **Recently decided** lists the rows filed as spam and the declined ones, the fifty newest decisions first, until retention deletes them, in the sponsor screen's shape (`render_decided()` and `reopen_states()` in `class-wpcpm-sponsor-application.php`). A spam row there offers **Put back in the queue**, a seventh decision, `ACTION_REOPEN`, guarded as the six are, which returns it to `new` with a line in its history (the author's call, see "After the plan"); a row that was never acknowledged, filed by the form's checks or past the day's limit on acknowledgements, is sent the acknowledgement then, once, with the confirming link, through the form's own sender (`WPCPM_Mentor_Application_Form::mail_applicant()`), its history reading "put back in the queue, acknowledgement sent", and one acknowledged when it came, marked as spam by mistake, is sent nothing (the owner's ruling of 16 October 2026); a send that fails leaves the row as it was; a declined row offers Select with its note, the second of the two ways spec section 5 gives the mentor coordinator for an appeal, as a lapsed row does in the Lapsed view. A row whose acknowledgement never went, past the day's limit on acknowledgements or filed as spam by the form's checks, carries the mark **acknowledgement not sent**, since its address cannot be confirmed, until Put back in the queue sends it and the mark goes. The suite tries every decision from each of the nine states in one table: where it takes the row, its sentence and its email, and anywhere else the row as it was, `mapp-state` and nothing sent.

**Files:**
- Create: `includes/modules/class-wpcpm-mentor-application-queue.php`
- Modify: `includes/modules/class-wpcpm-mentors.php` (`TAB_QUEUE`, `TABS`, `tab()`, the labels, `render_admin_page()`, `render_tab_queue()`, `boot()`)
- Modify: `wpcredits-program-manager.php`, `uninstall.php`
- Create: `bin/test-mentor-application-queue.php`
- Modify: `bin/test-mentors-screen.php`, `bin/test-accounts-table.php`, `bin/test-submit-guard.php`

**Interfaces:**
- Consumes: Tasks 1 to 4 (`WPCPM_Mentor_Application::*`, the settings `mentor_course_link` and `checker_course_title`, the three ids, `WPCPM_Mentor_Application_Form::reply_headers()`, `fields()`, `SIGNAL_*`, `mail_applicant()`); `WPCPM_Mail_Templates::program_contact()`; `WPCPM_Request::posted_id()`, `posted_raw()`, `posted_key()`, `key()`; `WPCPM_Flash::set()`; `WPCPM_Typed_Text::box_form()`, `cleaner_loses()`, `box_length()`, `keep_box()`, `kept_box()`, `forget()`, `drawn_limit()`, `loss_message()`; `WPCPM_Mail::send_to()`.
- Produces: `final class WPCPM_Mentor_Application_Queue`, all static: `ACTION_SELECT = 'wpcpm_mapp_select'`, `ACTION_INFO = 'wpcpm_mapp_info'`, `ACTION_SECOND = 'wpcpm_mapp_second'`, `ACTION_DECLINE = 'wpcpm_mapp_decline'`, `ACTION_SPAM = 'wpcpm_mapp_spam'`, `ACTION_WITHDRAW = 'wpcpm_mapp_withdraw'`, `ACTION_REOPEN = 'wpcpm_mapp_reopen'`, `FIELD_APPLICATION = 'wpcpm_mapp_app'`, `FIELD_NOTE = 'wpcpm_mapp_note'`, `MIN_NOTE = 10`, `MAX_NOTE = 2000`, `MAIL_SELECTED = 'mentor-selected'`, `MAIL_INFO = 'mentor-more-information'`, `MAIL_DECLINED = 'mentor-declined'`, `VIEW_ARG = 'mapp_view'`, `QUEUE_MAX = 50`, `FLASH = 'mentors_admin'`; `init()`, `decisions(): array` (action => handler, the seven), `decided_states(): string[]` (`spam`, `declined`), `views(): array` (`waiting`, `info`, `second`, `selected`, `lapsed`, each `states` and `label`), `current_view(): string`, `view_url( string $view = 'waiting' ): string`, the seven `handle_*()` (Select takes the open states, `lapsed` and `declined`; Put back in the queue takes `spam` alone, and sends the acknowledgement once to a row that never had it, flashing `mapp-reopened-sent`, or nothing, flashing `mapp-reopened`), `messages(): array`, `render(): void`. `WPCPM_Mentors::TAB_QUEUE = 'queue'`; `WPCPM_Mentors::tab()` answers the tab the address names, or `queue` while `waiting_count()` is above 0 and `accounts` otherwise.

- [ ] **Step 1: Write the failing suite and the checks the screen's suites gain**

Create `bin/test-mentor-application-queue.php`:

```php
<?php
/**
 * The Waiting for review tab and its seven decisions (1.122.31): `WPCPM_Mentor_Application_Queue`.
 *
 * Design of 16 October 2026, section 5. What each block pins, and why:
 *
 * - **Who may press**: an Administrator, the capability asked before anything else, the nonce keyed
 *   to the decision and the row next, then the row, which must be an application in a state the
 *   decision takes. Every decision is registered for signed-in requests alone.
 * - **Each decision from each state**, every pair of the seven and the nine in one table: the state it
 *   moves to and its sentence, the email it sends or does not, or, from a state it does not take,
 *   the row left as it was and nothing sent; the row's history with who, when and the note; Select
 *   offered again on a lapsed or a declined row; Withdraw on a selected row alone; Put back in the
 *   queue on a spam row alone, sending the acknowledgement once to a row that never had it, and
 *   nothing to one acknowledged before.
 * - **The address is confirmed first** for every decision that emails the applicant, and Select
 *   needs the course invitation link; a send that fails leaves the row as it was.
 * - **A note** that is needed and too short, too long, or that the cleaner would take words from is
 *   refused, nothing done, and the typing comes back in that row's box.
 * - **The tab**: five views with their counts; each row whole, its answers escaped, its marks (the
 *   acknowledgement not sent among them), its history, "asked for more information on" where it
 *   applies, a selected row's lapse day, and the decisions its state allows, each a form with its
 *   own nonce that comes back to its view; under the views, Recently decided, the rows filed as spam
 *   and the declined ones, newest first, with Put back in the queue and Select.
 *
 * Run from the plugin root:  php bin/test-mentor-application-queue.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/mentor-applications.php';

$GLOBALS['mail']       = array();
$GLOBALS['mail_fails'] = false;
$GLOBALS['settings']   = array();

/** The settings the decisions read, as a check sets them. */
class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) {
		return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
	}
}

/** The mail layer at its contract, which a check can make refuse. */
class WPCPM_Mail {
	public static function send_to( $email, $context, $build, $locale = '' ) {
		if ( ! is_email( $email ) || ! is_callable( $build ) || $GLOBALS['mail_fails'] ) {
			return false;
		}
		$mail              = (array) call_user_func( $build, $email );
		$mail['to']        = $email;
		$mail['context']   = $context;
		$GLOBALS['mail'][] = $mail;
		return true;
	}
	public static function site_name() {
		return 'WordPress Credits';
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-form.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-queue.php';

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

/** A site with one Administrator signed in and the course link set. */
function reset_world() {
	$GLOBALS['posts']      = array();
	$GLOBALS['pmeta']      = array();
	$GLOBALS['umeta']      = array();
	$GLOBALS['users']      = array();
	$GLOBALS['opts']       = array( 'date_format' => 'F j, Y' );
	$GLOBALS['mail']       = array();
	$GLOBALS['mail_fails'] = false;
	$GLOBALS['next_id']    = 500;
	$GLOBALS['settings']   = array(
		'mentor_course_link'   => 'https://example.test/mentors-course/?invite=EXAMPLE-NOT-A-REAL-CODE',
		'checker_course_title' => 'WordPress Credits Mentor\'s Course',
		'mentor_lapse_days'    => 90,
		'mail_contact'         => 'programs@example.test',
	);
	an_account( 9, 'Ida Admin', 'ida@example.test', array( 'administrator' ) );
	$GLOBALS['uid']    = 9;
	$GLOBALS['manage'] = array( 9 );
	$_POST             = array();
	$_GET              = array();
	$_REQUEST          = array();
}

/** One application, stored as the form stores it, in a state, its address confirmed or not. */
function an_application( $state = 'new', $confirmed = true, array $more = array(), array $signals = array() ) {
	$id = WPCPM_Mentor_Application::store(
		array_merge(
			array(
				'name'      => 'Ada Okonjo',
				'email'     => 'ada@example.test',
				'profile'   => 'ada-example',
				'country'   => 'Poland',
				'teacher'   => 'No',
				'languages' => array( 'English', 'Polish' ),
				'expertise' => array( 'Polyglots', 'Training' ),
				'hours'     => '2.5',
				'gpl'       => true,
				'anything'  => 'I lead a meetup.',
			),
			$more
		),
		in_array( $state, array( 'new', 'held', 'spam' ), true ) ? $state : 'new',
		$signals,
		array()
	);

	$events = array(
		'info'     => WPCPM_Mentor_Application::EVENT_INFO,
		'second'   => WPCPM_Mentor_Application::EVENT_SECOND,
		'selected' => WPCPM_Mentor_Application::EVENT_SELECTED,
		'declined' => WPCPM_Mentor_Application::EVENT_DECLINED,
		'lapsed'   => WPCPM_Mentor_Application::EVENT_LAPSED,
		'active'   => WPCPM_Mentor_Application::EVENT_ACTIVATED,
	);

	if ( isset( $events[ $state ] ) ) {
		WPCPM_Mentor_Application::decide( $id, $state, $events[ $state ], 9, '', time() - DAY_IN_SECONDS );
	}

	if ( $confirmed ) {
		WPCPM_Mentor_Application::confirm( $id );
	}

	return $id;
}

/**
 * Press one decision on one row, as its form posts it, and say how it ended.
 *
 * @param string $action The decision's action.
 * @param int    $id     The row.
 * @param array  $more   `note`, `nonce`, `view`.
 * @return array `stop`, `flash`, `state`, `mail`.
 */
function press( $action, $id, array $more = array() ) {
	$GLOBALS['mail'] = array();
	$_POST           = array(
		'action'                                             => $action,
		'_wpnonce'                                           => isset( $more['nonce'] ) ? $more['nonce'] : wp_create_nonce( $action . '_' . $id ),
		WPCPM_Mentor_Application_Queue::FIELD_APPLICATION   => (string) $id,
		WPCPM_Mentor_Application_Queue::VIEW_ARG             => isset( $more['view'] ) ? $more['view'] : 'waiting',
	);
	if ( isset( $more['note'] ) ) {
		$_POST[ WPCPM_Mentor_Application_Queue::FIELD_NOTE ] = wp_slash( $more['note'] );
	}
	$_REQUEST = $_POST;
	unset( $GLOBALS['umeta'][9]['wpcpm_flash'] );

	$methods = WPCPM_Mentor_Application_Queue::decisions();
	$out     = run( array( 'WPCPM_Mentor_Application_Queue', $methods[ $action ] ) );
	$flash   = get_user_meta( 9, 'wpcpm_flash', true );
	$post    = get_post( $id );

	return array(
		'stop'  => $out['stop'],
		'flash' => is_array( $flash ) && isset( $flash['mentors_admin'] ) ? $flash['mentors_admin'] : '',
		'state' => $post instanceof WP_Post ? WPCPM_Mentor_Application::state_of( $post ) : null,
		'mail'  => $GLOBALS['mail'],
	);
}

/**
 * What a refused press kept of its typing for a row's box, read where the next page reads it. Read
 * from the store rather than through `WPCPM_Typed_Text::kept_box()`, which takes it through the
 * flash, whose reads are kept for the rest of a request: in one process the press's own forgetting
 * would be read back.
 *
 * @param int    $id  The row.
 * @param string $key The decision's box.
 * @return string|null
 */
function kept( $id, $key ) {
	$flash = get_user_meta( 9, 'wpcpm_flash', true );
	$entry = is_array( $flash ) ? ( $flash[ WPCPM_Typed_Text::KEPT_CHANNEL . WPCPM_Typed_Text::box_form( WPCPM_Mentor_Application_Queue::TYPED, $id ) ] ?? null ) : null;

	return is_array( $entry ) && isset( $entry['typed'][ $key ] ) ? $entry['typed'][ $key ] : null;
}

/** The newest row of a row's history: what happened, who, and the note. */
function last_event( $id ) {
	$events = WPCPM_Mentor_Application::events( $id );
	$last   = end( $events );

	return array( $last['event'], $last['actor'], $last['note'] );
}

/** The tab, drawn at a view. */
function tab( $view = 'waiting' ) {
	$_GET = 'waiting' === $view ? array() : array( WPCPM_Mentor_Application_Queue::VIEW_ARG => $view );

	return run( array( 'WPCPM_Mentor_Application_Queue', 'render' ) )['html'];
}

/** The part of the tab above Recently decided: the view's own rows. */
function view_part( $html ) {
	$at = strpos( $html, '<section class="wpcpm-mapp-decided">' );

	return false === $at ? $html : substr( $html, 0, $at );
}

/** Recently decided, the part of the tab under the views. */
function decided_part( $html ) {
	$at = strpos( $html, '<section class="wpcpm-mapp-decided">' );

	return false === $at ? '' : substr( $html, $at );
}

echo "=== The hooks ===\n";

$GLOBALS['hooks'] = array();
WPCPM_Mentor_Application_Queue::init();
ck(
	'seven decisions, each for a signed-in request alone',
	array( array_values( array_filter( array_keys( $GLOBALS['hooks'] ), function ( $hook ) { return 0 === strpos( $hook, 'admin_post_' ); } ) ) ),
	array( array( 'admin_post_wpcpm_mapp_select', 'admin_post_wpcpm_mapp_info', 'admin_post_wpcpm_mapp_second', 'admin_post_wpcpm_mapp_decline', 'admin_post_wpcpm_mapp_spam', 'admin_post_wpcpm_mapp_withdraw', 'admin_post_wpcpm_mapp_reopen' ) )
);

echo "\n=== Who may press ===\n";

reset_world();
$id               = an_application();
$GLOBALS['manage'] = array();
$_POST            = array( WPCPM_Mentor_Application_Queue::FIELD_APPLICATION => (string) $id );
$refused          = press( WPCPM_Mentor_Application_Queue::ACTION_SELECT, $id, array( 'nonce' => 'nonce-wrong' ) );
ck( 'without the capability, a press is refused before its nonce is read, and nothing moves', array( 0 === strpos( $refused['stop'], 'wp_die: You do not have permission' ), $refused['state'], $refused['mail'] ), array( true, 'new', array() ) );
$GLOBALS['manage'] = array( 9 );
ck( 'a nonce that is not this decision\'s for this row is refused', press( WPCPM_Mentor_Application_Queue::ACTION_SELECT, $id, array( 'nonce' => wp_create_nonce( WPCPM_Mentor_Application_Queue::ACTION_DECLINE . '_' . $id ) ) )['stop'], 'nonce refused: wpcpm_mapp_select_' . $id );
$page              = new WP_Post();
$page->ID          = 42;
$page->post_type   = 'page';
$GLOBALS['posts'][42] = $page;
ck( 'a page is no application', press( WPCPM_Mentor_Application_Queue::ACTION_SPAM, 42 )['flash'], 'mapp-unknown' );

echo "\n=== Select ===\n";

reset_world();
$id     = an_application();
$select = press( WPCPM_Mentor_Application_Queue::ACTION_SELECT, $id, array( 'note' => 'Strong Polyglots record.' ) );
ck( 'Select moves the row to selected and lands back on the tab with its sentence', array( $select['state'], $select['flash'], $select['stop'] ), array( 'selected', 'mapp-selected', 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue' ) );
ck(
	'the selection email goes to the applicant, with the course, its invitation link and the program\'s address to write to when it is done, and the program\'s Reply-To',
	array( $select['mail'][0]['to'] ?? null, $select['mail'][0]['context'] ?? null, false !== strpos( $select['mail'][0]['body'] ?? '', "please access the WordPress Credits Mentor's Course through this invitation link: https://example.test/mentors-course/?invite=EXAMPLE-NOT-A-REAL-CODE" ), false !== strpos( $select['mail'][0]['body'] ?? '', "When you finish the course, please reply to this email to let us know.\nYou can also write to programs@example.test." ), $select['mail'][0]['headers'] ?? null ),
	array( 'ada@example.test', 'mentor-selected', true, true, array( 'Reply-To: programs@example.test' ) )
);
ck( 'and the history says selected, by whom, with the note', last_event( $id ), array( 'selected', 9, 'Strong Polyglots record.' ) );

// Each of the three decisions that email the applicant waits for the address to be confirmed.
$refused = array();
foreach ( array( WPCPM_Mentor_Application_Queue::ACTION_SELECT, WPCPM_Mentor_Application_Queue::ACTION_INFO, WPCPM_Mentor_Application_Queue::ACTION_DECLINE ) as $action ) {
	reset_world();
	$id                 = an_application( 'new', false );
	$refused[ $action ] = array_intersect_key( press( $action, $id ), array_flip( array( 'flash', 'state', 'mail' ) ) );
}
ck( 'an address nobody confirmed is refused by Select, Ask for more information and Decline alike, nothing sent and nothing moved', $refused, array_fill_keys( array( 'wpcpm_mapp_select', 'wpcpm_mapp_info', 'wpcpm_mapp_decline' ), array( 'flash' => 'mapp-unconfirmed', 'state' => 'new', 'mail' => array() ) ) );

reset_world();
$id                                     = an_application();
$GLOBALS['settings']['mentor_course_link'] = '';
ck( 'with no course invitation link set, Select is refused and sends nothing', array_intersect_key( press( WPCPM_Mentor_Application_Queue::ACTION_SELECT, $id ), array_flip( array( 'flash', 'state', 'mail' ) ) ), array( 'flash' => 'mapp-no-course-link', 'state' => 'new', 'mail' => array() ) );

reset_world();
$id                    = an_application();
$GLOBALS['mail_fails'] = true;
ck( 'a send that fails leaves the row as it was', array_intersect_key( press( WPCPM_Mentor_Application_Queue::ACTION_SELECT, $id ), array_flip( array( 'flash', 'state' ) ) ), array( 'flash' => 'mapp-not-sent', 'state' => 'new' ) );

$again = array();
foreach ( array( 'lapsed', 'declined', 'spam', 'selected', 'active' ) as $state ) {
	reset_world();
	$id               = an_application( $state );
	$again[ $state ] = press( WPCPM_Mentor_Application_Queue::ACTION_SELECT, $id, array( 'note' => 'They wrote again with their meetup work.' ) )['flash'];
}
ck( 'Select is offered again on a lapsed or a declined row, and refused on a spam, selected or active one', $again, array( 'lapsed' => 'mapp-selected', 'declined' => 'mapp-selected', 'spam' => 'mapp-state', 'selected' => 'mapp-state', 'active' => 'mapp-state' ) );

echo "\n=== Every decision from every state ===\n";

// The states each decision takes, what it moves a row to, its sentence and how many emails it sends;
// from any other state it is refused, the row left as it was and nothing sent.
$takes = array(
	'wpcpm_mapp_select'   => array( array( 'new', 'held', 'info', 'second', 'lapsed', 'declined' ), 'selected', 'mapp-selected', 1 ),
	'wpcpm_mapp_info'     => array( array( 'new', 'held', 'info', 'second' ), 'info', 'mapp-info', 1 ),
	'wpcpm_mapp_second'   => array( array( 'new', 'held', 'info', 'second' ), 'second', 'mapp-second', 0 ),
	'wpcpm_mapp_decline'  => array( array( 'new', 'held', 'info', 'second' ), 'declined', 'mapp-declined', 1 ),
	'wpcpm_mapp_spam'     => array( array( 'new', 'held', 'info', 'second' ), 'spam', 'mapp-spam', 0 ),
	'wpcpm_mapp_withdraw' => array( array( 'selected' ), 'lapsed', 'mapp-withdrawn', 0 ),
	'wpcpm_mapp_reopen'   => array( array( 'spam' ), 'new', 'mapp-reopened', 0 ),
);
$pressed = array();
$wanted  = array();
foreach ( $takes as $action => $rule ) {
	foreach ( WPCPM_Mentor_Application::states() as $state ) {
		reset_world();
		$id                         = an_application( $state );
		$out                        = press( $action, $id, array( 'note' => 'A note long enough for every decision.' ) );
		$pressed[ $action ][ $state ] = $out['state'] . ' ' . $out['flash'] . ' ' . count( $out['mail'] );
		$wanted[ $action ][ $state ]  = in_array( $state, $rule[0], true ) ? $rule[1] . ' ' . $rule[2] . ' ' . $rule[3] : $state . ' mapp-state 0';
	}
}
ck( 'every decision from each of the nine states: where it takes the row, its sentence and its email; anywhere else the row as it was, mapp-state and nothing sent', array( array_keys( $takes ), $pressed ), array( array_keys( WPCPM_Mentor_Application_Queue::decisions() ), $wanted ) );

echo "\n=== Ask for more information, Second opinion, Decline, Mark as spam ===\n";

reset_world();
$id   = an_application( 'held' );
$info = press( WPCPM_Mentor_Application_Queue::ACTION_INFO, $id, array( 'note' => 'Asked about Training.' ) );
ck( 'Ask for more information sends its email and keeps the row in the queue, as info', array( $info['state'], $info['mail'][0]['context'] ?? null, in_array( $id, array_map( function ( $p ) { return $p->ID; }, WPCPM_Mentor_Application::applications( WPCPM_Mentor_Application::open_states() ) ), true ), last_event( $id ) ), array( 'info', 'mentor-more-information', true, array( 'information requested', 9, 'Asked about Training.' ) ) );
ck( 'and the note never reaches the applicant', false !== strpos( $info['mail'][0]['body'] ?? '', 'Training.' ), false );

reset_world();
$id    = an_application( 'new', false );
$short = press( WPCPM_Mentor_Application_Queue::ACTION_SECOND, $id, array( 'note' => 'Ask Ben.' ) );
ck( 'Second opinion needs a note: one too short is refused, nothing done, and the typing kept for that row\'s box', array( $short['flash'], $short['state'], kept( $id, 'second' ) ), array( 'mapp-note-short', 'new', 'Ask Ben.' ) );
$second = press( WPCPM_Mentor_Application_Queue::ACTION_SECOND, $id, array( 'note' => 'Ben, does this profile show Polyglots work?' ) );
ck( 'with a note it marks the row, sends nothing, and needs no confirmed address', array( $second['state'], $second['mail'], last_event( $id ) ), array( 'second', array(), array( 'second opinion', 9, 'Ben, does this profile show Polyglots work?' ) ) );

reset_world();
$id      = an_application( 'second' );
$decline = press( WPCPM_Mentor_Application_Queue::ACTION_DECLINE, $id, array( 'note' => 'No contribution yet.' ) );
ck( 'Decline sends the decline email and closes the row; the note stays on the site', array( $decline['state'], $decline['mail'][0]['context'] ?? null, false !== strpos( $decline['mail'][0]['body'] ?? '', 'No contribution' ), last_event( $id ) ), array( 'declined', 'mentor-declined', false, array( 'declined', 9, 'No contribution yet.' ) ) );

reset_world();
$id   = an_application( 'new', false );
$spam = press( WPCPM_Mentor_Application_Queue::ACTION_SPAM, $id );
ck( 'Mark as spam closes the row, sends nothing, and needs no confirmed address', array( $spam['state'], $spam['mail'], $spam['flash'] ), array( 'spam', array(), 'mapp-spam' ) );

echo "\n=== Withdraw ===\n";

reset_world();
$open     = an_application();
$selected = an_application( 'selected' );
ck( 'Withdraw takes a selected row alone', press( WPCPM_Mentor_Application_Queue::ACTION_WITHDRAW, $open, array( 'note' => 'They wrote to stop.' ) )['flash'], 'mapp-state' );
$withdraw = press( WPCPM_Mentor_Application_Queue::ACTION_WITHDRAW, $selected, array( 'note' => 'They wrote that they cannot continue.', 'view' => 'selected' ) );
ck( 'it lapses the row with the note, sends nothing, and comes back to the view it was pressed on', array( $withdraw['state'], $withdraw['mail'], last_event( $selected ), $withdraw['stop'] ), array( 'lapsed', array(), array( 'withdrawn', 9, 'They wrote that they cannot continue.' ), 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue&mapp_view=selected' ) );

echo "\n=== A note refused for its words ===\n";

reset_world();
$id   = an_application();
$long = press( WPCPM_Mentor_Application_Queue::ACTION_DECLINE, $id, array( 'note' => str_repeat( 'a', WPCPM_Mentor_Application_Queue::MAX_NOTE + 1 ) ) );
ck( 'a note longer than the box holds is refused, nothing sent', array( $long['flash'], $long['state'], $long['mail'] ), array( 'mapp-note-long', 'new', array() ) );
$loss = press( WPCPM_Mentor_Application_Queue::ACTION_DECLINE, $id, array( 'note' => 'Kids <12 free, adults >18 pay' ) );
ck( 'and so is one the cleaner would take words from, its typing kept', array( $loss['flash'], $loss['state'], kept( $id, 'decline' ) ), array( 'mapp-note-loss', 'new', 'Kids <12 free, adults >18 pay' ) );
ck( 'every outcome a press leaves has a sentence', array_values( array_diff( array( 'mapp-selected', 'mapp-info', 'mapp-second', 'mapp-declined', 'mapp-spam', 'mapp-withdrawn', 'mapp-reopened', 'mapp-reopened-sent', 'mapp-unknown', 'mapp-state', 'mapp-unconfirmed', 'mapp-no-email', 'mapp-no-course-link', 'mapp-not-sent', 'mapp-note-short', 'mapp-note-long', 'mapp-note-loss' ), array_keys( WPCPM_Mentor_Application_Queue::messages() ) ) ), array() );

echo "\n=== Recently decided, and Put back in the queue ===\n";

reset_world();
$GLOBALS['now'] = time();
$filed          = an_application( 'spam', false, array( 'name' => 'Honey Example' ), array( 'honeypot' ) );
$declined       = an_application( 'declined' );
WPCPM_Mentor_Application::add_event( $filed, WPCPM_Mentor_Application::EVENT_SUBMITTED, 0, '', time() );
$decided        = decided_part( tab() );
preg_match_all( '#<li class="wpcpm-queue-item" id="wpcpm-mentor-application-(\d+)">#', $decided, $decided_rows );
preg_match_all( '#<input type="hidden" name="action" value="(wpcpm_mapp_[a-z]+)" /><input type="hidden" name="wpcpm_mapp_app" value="(\d+)" />#', $decided, $decided_forms, PREG_SET_ORDER );
ck(
	'under the views, Recently decided lists the row the honeypot filed as spam and the declined one, newest first, the first with Put back in the queue and marked as never acknowledged, the second with Select',
	array( array_map( 'intval', $decided_rows[1] ), array_map( function ( $f ) { return $f[1] . ' ' . $f[2]; }, $decided_forms ), substr_count( $decided, 'wpcpm-inst-mark--unacknowledged' ), false !== strpos( $decided, 'Filed as spam on ' ), false !== strpos( $decided, 'Declined on ' ) ),
	array( array( $filed, $declined ), array( 'wpcpm_mapp_reopen ' . $filed, 'wpcpm_mapp_select ' . $declined ), 1, true, true )
);
$GLOBALS['mail_fails'] = true;
$failed                = press( WPCPM_Mentor_Application_Queue::ACTION_REOPEN, $filed );
$GLOBALS['mail_fails'] = false;
ck( 'Put back in the queue sends that row the acknowledgement it never had, and a send that fails leaves the row as it was', array( $failed['state'], $failed['flash'], $failed['mail'] ), array( 'spam', 'mapp-not-sent', array() ) );
$reopened = press( WPCPM_Mentor_Application_Queue::ACTION_REOPEN, $filed );
ck(
	'sent, the row is new again, the acknowledgement queued once with the link that confirms the address, the mark gone, and the history says so, with who did it',
	array( $reopened['state'], $reopened['flash'], count( $reopened['mail'] ), $reopened['mail'][0]['context'] ?? null, $reopened['mail'][0]['to'] ?? null, false !== strpos( $reopened['mail'][0]['body'] ?? '', WPCPM_Mentor_Application::verify_url( $filed ) ), last_event( $filed ), substr_count( tab(), 'wpcpm-inst-mark--unacknowledged' ) ),
	array( 'new', 'mapp-reopened-sent', 1, 'mentor-application-received', 'ada@example.test', true, array( 'put back in the queue, acknowledgement sent', 9, '' ), 0 )
);
press( WPCPM_Mentor_Application_Queue::ACTION_SPAM, $filed );
$again = press( WPCPM_Mentor_Application_Queue::ACTION_REOPEN, $filed );
ck( 'filed as spam again and put back again, it is sent nothing the second time', array( $again['state'], $again['flash'], $again['mail'], last_event( $filed ) ), array( 'new', 'mapp-reopened', array(), array( 'put back in the queue', 9, '' ) ) );
$acknowledged = an_application( 'new' );
press( WPCPM_Mentor_Application_Queue::ACTION_SPAM, $acknowledged );
$mistake = press( WPCPM_Mentor_Application_Queue::ACTION_REOPEN, $acknowledged );
ck( 'and a row acknowledged when it came, marked as spam by mistake, is put back with nothing sent', array( $mistake['state'], $mistake['flash'], $mistake['mail'], last_event( $acknowledged ) ), array( 'new', 'mapp-reopened', array(), array( 'put back in the queue', 9, '' ) ) );
reset_world();
an_application( 'held', false, array(), array( 'mail-ceiling' ) );
ck( 'a row past the day\'s limit on acknowledgements is marked so, as one the checks filed as spam is', substr_count( tab(), 'wpcpm-inst-mark--unacknowledged' ), 1 );
reset_world();
ck( 'and with nothing decided, Recently decided says so', false !== strpos( decided_part( tab() ), 'No application has been filed as spam or declined.' ), true );

echo "\n=== The tab ===\n";

reset_world();
$GLOBALS['now'] = time();
$first   = an_application( 'held', false, array( 'name' => 'Cy <b>Tag</b>', 'anything' => "Line <script>x</script>\nTwo" ), array( 'links', 'duplicate', 'mentor' ) );
$asked   = an_application( 'info' );
$second  = an_application( 'second' );
$picked  = an_application( 'selected' );
$gone    = an_application( 'lapsed' );
$closed  = an_application( 'declined' );
$waiting = tab();

preg_match_all( '#<li class="([a-z]+)"><a href="([^"]*)"( class="current" aria-current="page")?>([^<]*) <span class="count">\((\d+)\)</span>#', $waiting, $views, PREG_SET_ORDER );
ck(
	'five views, each at its own address with its count; Waiting is the queue, its four open states',
	array_map( function ( $v ) { return array( $v[1], $v[4], $v[5], '' !== $v[3] ); }, $views ),
	array( array( 'waiting', 'Waiting', '3', true ), array( 'info', 'Asked for more information', '1', false ), array( 'second', 'Second opinion', '1', false ), array( 'selected', 'Selected (course pending)', '1', false ), array( 'lapsed', 'Lapsed', '1', false ) )
);
preg_match_all( '#<li class="wpcpm-queue-item" id="wpcpm-mentor-application-(\d+)">#', view_part( $waiting ), $listed );
ck( 'Waiting lists the queue oldest first, and nothing decided', array_map( 'intval', $listed[1] ), array( $first, $asked, $second ) );
ck(
	'a row says who and when, its answers escaped, the profile a link with the username, the address and whether it is confirmed',
	array( false !== strpos( $waiting, 'Cy &lt;b&gt;Tag&lt;/b&gt;' ), false === strpos( $waiting, '<b>Tag</b>' ), false !== strpos( $waiting, '<a href="https://profiles.wordpress.org/ada-example/" rel="noopener">ada-example</a>' ), false !== strpos( $waiting, 'ada@example.test (not confirmed yet)' ), false !== strpos( $waiting, 'ada@example.test (confirmed)' ), false !== strpos( $waiting, 'Line &lt;script&gt;x&lt;/script&gt;<br />' ), false !== strpos( $waiting, 'English, Polish' ), false !== strpos( $waiting, 'Agreed' ) ),
	array( true, true, true, true, true, true, true, true )
);
ck(
	'its marks: held, a possible duplicate, already a mentor, the address not confirmed; and an asked row when it was asked',
	array( substr_count( $waiting, 'wpcpm-inst-mark--held' ), substr_count( $waiting, 'wpcpm-inst-mark--duplicate' ), substr_count( $waiting, 'wpcpm-inst-mark--account' ), substr_count( $waiting, 'wpcpm-inst-mark--unconfirmed' ), substr_count( $waiting, 'Asked for more information on ' ) ),
	array( 1, 1, 1, 1, 1 )
);
ck(
	'the history names each decision, its day, who took it and the note',
	array( false !== strpos( $waiting, ': applied, by the site.' ), false !== strpos( $waiting, ': asked for more information, Ida Admin.' ), false !== strpos( $waiting, ': confirmed the address, by the site.' ) ),
	array( true, true, true )
);
preg_match_all( '#<input type="hidden" name="action" value="(wpcpm_mapp_[a-z]+)" /><input type="hidden" name="wpcpm_mapp_app" value="' . $first . '" />#', $waiting, $first_forms );
ck( 'an open row offers Select, Ask for more information, Second opinion, Decline and Mark as spam, in that order', $first_forms[1], array( 'wpcpm_mapp_select', 'wpcpm_mapp_info', 'wpcpm_mapp_second', 'wpcpm_mapp_decline', 'wpcpm_mapp_spam' ) );
ck( 'each form carries the nonce of its decision for its row, and the view to come back to', array( false !== strpos( $waiting, 'value="nonce-wpcpm_mapp_decline_' . $first . '"' ), substr_count( view_part( $waiting ), 'name="mapp_view" value="waiting"' ) ), array( true, 15 ) );

$selected_view = view_part( tab( 'selected' ) );
preg_match_all( '#<input type="hidden" name="action" value="(wpcpm_mapp_[a-z]+)" />#', $selected_view, $selected_forms );
ck( 'Selected lists the row waiting for the course, the day it lapses, and Withdraw alone', array( substr_count( $selected_view, 'class="wpcpm-queue-item"' ), false !== strpos( $selected_view, 'It lapses on ' ), $selected_forms[1] ), array( 1, true, array( 'wpcpm_mapp_withdraw' ) ) );
$lapsed_view = view_part( tab( 'lapsed' ) );
preg_match_all( '#<input type="hidden" name="action" value="(wpcpm_mapp_[a-z]+)" />#', $lapsed_view, $lapsed_forms );
ck( 'Lapsed lists the lapsed row with Select offered again', array( substr_count( $lapsed_view, 'class="wpcpm-queue-item"' ), $lapsed_forms[1] ), array( 1, array( 'wpcpm_mapp_select' ) ) );

reset_world();
ck( 'a view with nothing in it says so', false !== strpos( tab(), 'Nothing here. New applications appear in Waiting.' ), true );
$GLOBALS['manage'] = array();
an_application();
ck( 'and somebody without the capability is shown nothing', tab(), '' );
ck( 'nothing was asked of WordPress that the stand-ins do not model', isset( $GLOBALS['unmodeled'] ) ? $GLOBALS['unmodeled'] : array(), array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

```diff
diff --git a/bin/test-accounts-table.php b/bin/test-accounts-table.php
--- a/bin/test-accounts-table.php
+++ b/bin/test-accounts-table.php
@@ -713,6 +713,15 @@ require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators.php';
 
+// How many applications to mentor wait (1.122.31), which the Mentors screen's choice of tab reads:
+// none, so the screen opens on its Accounts tab as the other two do. Declared inside a condition, which
+// PHP does not hoist: the run that loads the plugin as a site does, above, declares the real class.
+if ( ! class_exists( 'WPCPM_Mentor_Application', false ) ) {
+	class WPCPM_Mentor_Application {
+		public static function waiting_count() { return 0; }
+	}
+}
+
 // The plugin's lazy loader, which the plumbing calls before it words an outcome: the tables are
 // loaded above, so it has nothing to do here. Declared as the run reaches this line, never as the
 // file is read, because the run that loads the plugin as a site does declares the plugin's own.
@@ -2393,12 +2402,13 @@ echo "\n=== The screen plumbing the audiences share: one trait, which neither mo
 // does a method moved back out of the trait. Two of them are defaults a module replaces where its
 // audience differs, by design: where its list stands (`list_state()`), which the Students module
 // keeps for its institution filter, and how one account is sent its invitation (`invite_one()`).
-// Three more are replaced by the Mentors module alone, for its third tab, the Engagement tab: the
-// screen's load hook (`load_screen()`, whose trait copy it keeps for the Accounts tab under another
-// name), the tab bar's labels (`tab_labels()`) and the page (`render_admin_page()`).
+// Four more are replaced by the Mentors module alone, for its Engagement and Waiting for review tabs:
+// the screen's load hook (`load_screen()`, whose trait copy it keeps for the Accounts tab under another
+// name), the tab the screen opens on (`tab()`, the queue while applications wait there, 1.122.31), the
+// tab bar's labels (`tab_labels()`) and the page (`render_admin_page()`).
 preg_match_all( '/^\t(?:(?:public|protected|private|static)\s+)*function\s+(\w+)\s*\(/m', $screen_code, $screen_methods );
 
-$replaceable    = array( 'list_state', 'invite_one', 'load_screen', 'tab_labels', 'render_admin_page' );
+$replaceable    = array( 'list_state', 'invite_one', 'load_screen', 'tab', 'tab_labels', 'render_admin_page' );
 $declared_again = function ( $code ) use ( $screen_methods, $replaceable ) {
 	preg_match_all( '/function\s+(\w+)\s*\(/', $code, $own );
 
@@ -2413,7 +2423,7 @@ $uses_screen    = function ( $code ) {
 	return 1 === preg_match( '/^\tuse WPCPM_Accounts_Screen(?:;| \{)$/m', $code );
 };
 
-ck( 'the screen plumbing the Students and Mentors modules held twice is one trait\'s, which both use without declaring one of its methods again, but for a default the trait leaves to a module: the Students module\'s own list state, for its institution filter, and the Mentors module\'s load hook, tab labels and page, which add its Engagement tab',
+ck( 'the screen plumbing the Students and Mentors modules held twice is one trait\'s, which both use without declaring one of its methods again, but for a default the trait leaves to a module: the Students module\'s own list state, for its institution filter, and the Mentors module\'s load hook, choice of tab, tab labels and page, which add its Engagement and Waiting for review tabs',
 	array(
 		'trait'    => array_values( array_diff( array( 'list_state', 'hook_screen', 'load_screen', 'handle_list_form', 'apply_pressed', 'save_per_page', 'handle_invite', 'invite_one', 'invite_selected', 'leave', 'accounts_url', 'list_url', 'table', 'accounts_messages', 'notice_sentence', 'tab', 'tab_labels', 'tab_field', 'render_admin_page', 'render_tab_accounts', 'render_not_connected', 'never_invited' ), $screen_methods[1] ) ),
 		'students' => array( $uses_screen( $students_code ), $declared_again( $students_code ), $replaced( $students_code ) ),
@@ -2422,7 +2432,7 @@ ck( 'the screen plumbing the Students and Mentors modules held twice is one trai
 	array(
 		'trait'    => array(),
 		'students' => array( true, array(), array( 'list_state' ) ),
-		'mentors'  => array( true, array(), array( 'load_screen', 'tab_labels', 'render_admin_page' ) ),
+		'mentors'  => array( true, array(), array( 'load_screen', 'tab', 'tab_labels', 'render_admin_page' ) ),
 	) );
 
 echo "\n=== The seams answer none on the base, so a list that keeps no records draws as it did ===\n";
@@ -3066,7 +3076,7 @@ foreach ( array( 'WPCPM_Students', 'WPCPM_Mentors', 'WPCPM_Administrators' ) as
 	}
 }
 
-ck( 'and the Students, Mentors and Administrators screens, whose first tab is Accounts, still open on Accounts',
+ck( 'and the Students and Administrators screens, whose first tab is Accounts, still open on Accounts, and so does the Mentors screen while no application to mentor waits',
 	$still,
 	array_fill_keys(
 		array( 'WPCPM_Students', 'WPCPM_Mentors', 'WPCPM_Administrators' ),
diff --git a/bin/test-mentors-screen.php b/bin/test-mentors-screen.php
--- a/bin/test-mentors-screen.php
+++ b/bin/test-mentors-screen.php
@@ -112,13 +112,24 @@ class WPCPM_Call_Calendar {
 class WPCPM_Group_Sessions {
 	public static function init() {}
 }
-/** And the applications to mentor (1.122.31), whose post type and public form it boots too. */
+/**
+ * And the applications to mentor (1.122.31): the post type, the public form and the decisions it
+ * boots, how many wait, which the screen's choice of tab reads, and the Waiting for review tab, drawn
+ * here as a mark with the outcome its notices know: bin/test-mentor-application-queue.php draws it whole.
+ */
 class WPCPM_Mentor_Application {
+	public static $waiting = 0;
 	public static function init() {}
+	public static function waiting_count() { return self::$waiting; }
 }
 class WPCPM_Mentor_Application_Form {
 	public static function init() {}
 }
+class WPCPM_Mentor_Application_Queue {
+	public static function init() {}
+	public static function messages() { return array( 'mapp-selected' => array( 'success', 'The applicant is selected.' ) ); }
+	public static function render() { echo '<div class="wpcpm-card" id="wpcpm-mentor-applications">The applications</div>'; }
+}
 
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
@@ -1799,7 +1810,7 @@ ck( 'a count a press on the ticked accounts left unprinted is not shown under th
 	array( WPCPM_Mail::queue(), html_of( notice_now() ) ),
 	array( array( 12, 13 ), '<div class="notice notice-success is-dismissible"><p>Invitations queued. They go out in the background - the progress is shown below.</p></div>' ) );
 
-echo "\n=== The screen in three tabs, Accounts, Engagement and Sync, in the bar every audience screen prints ===\n";
+echo "\n=== The screen in four tabs, Waiting for review, Accounts, Engagement and Sync, in the bar every audience screen prints ===\n";
 
 three_mentors();
 manager( 200 );
@@ -1809,6 +1820,7 @@ $three_tabs = function ( $shown ) use ( $home ) {
 	return array(
 		'Secondary menu',
 		array(
+			array( $home . '&tab=queue', 'Waiting for review', 'queue' === $shown, 'queue' === $shown ),
 			array( $home . '&tab=accounts', 'Accounts', 'accounts' === $shown, 'accounts' === $shown ),
 			array( $home . '&tab=engagement', 'Engagement', 'engagement' === $shown, 'engagement' === $shown ),
 			array( $home . '&tab=sync', 'Sync', 'sync' === $shown, 'sync' === $shown ),
@@ -1823,6 +1835,7 @@ WPCPM_Mentor_Engagement::for_tests( array( 'accounts' => array(), 'notes' => arr
 
 foreach ( array(
 	'from the menu'                    => array( 'page' => 'wpcpm-mentors' ),
+	'asked for Waiting for review'     => screen( array( 'tab' => 'queue' ) ),
 	'asked for Accounts'               => screen(),
 	'asked for Engagement'             => screen( array( 'tab' => 'engagement' ) ),
 	'asked for Sync'                   => screen( array( 'tab' => 'sync' ) ),
@@ -1839,10 +1852,11 @@ foreach ( array(
 
 WPCPM_Mentor_Engagement::for_tests( null );
 
-ck( 'the bar holds three tabs, Accounts, Engagement then Sync, each at the screen\'s address with its tab; Accounts is the tab shown from the menu, when asked for and for a tab the screen does not have, and Engagement and Sync when asked for, marked for the eye and for a screen reader',
+ck( 'the bar holds four tabs, Waiting for review, Accounts, Engagement then Sync, each at the screen\'s address with its tab; with no application waiting, Accounts is the tab shown from the menu, when asked for and for a tab the screen does not have, and each other tab when asked for, marked for the eye and for a screen reader',
 	$bars,
 	array(
 		'from the menu'                    => $three_tabs( 'accounts' ),
+		'asked for Waiting for review'     => $three_tabs( 'queue' ),
 		'asked for Accounts'               => $three_tabs( 'accounts' ),
 		'asked for Engagement'             => $three_tabs( 'engagement' ),
 		'asked for Sync'                   => $three_tabs( 'sync' ),
@@ -1860,6 +1874,7 @@ $printed_bar = run(
 			WPCPM_Screen_Tabs::render(
 				'wpcpm-mentors',
 				array(
+					'queue'      => 'Waiting for review',
 					'accounts'   => 'Accounts',
 					'engagement' => 'Engagement',
 					'sync'       => 'Sync',
@@ -1880,10 +1895,11 @@ ck( 'the bar is the one every audience screen prints, `WPCPM_Screen_Tabs`\'s, ta
 	array( true, 1, 0, true ) );
 
 $GLOBALS['l10n'] = array(
-	'Accounts'       => 'Cuentas',
-	'Engagement'     => 'Participación',
-	'Sync'           => 'Sincronización',
-	'Secondary menu' => 'Menú secundario',
+	'Waiting for review' => 'En espera de revisión',
+	'Accounts'           => 'Cuentas',
+	'Engagement'         => 'Participación',
+	'Sync'               => 'Sincronización',
+	'Secondary menu'     => 'Menú secundario',
 );
 $spanish         = page_for( screen( array( 'tab' => 'sync' ) ) );
 $GLOBALS['l10n'] = array();
@@ -1897,7 +1913,7 @@ ck( 'the labels are translated where the bar prints them, and so is the name the
 			return null === $bar ? null : array( $bar[0], array_column( $bar[1], 1 ) );
 		}
 	),
-	array( 'Menú secundario', array( 'Cuentas', 'Participación', 'Sincronización' ) ) );
+	array( 'Menú secundario', array( 'En espera de revisión', 'Cuentas', 'Participación', 'Sincronización' ) ) );
 
 $names = run(
 	function () {
@@ -1905,7 +1921,7 @@ $names = run(
 	}
 );
 
-ck( 'the tabs are the module\'s own list, Accounts first, and a form names its tab in the field the list\'s row links carry theirs in',
+ck( 'the tabs are the module\'s own list, Waiting for review first, and a form names its tab in the field the list\'s row links carry theirs in',
 	got(
 		$names,
 		function ( $out ) {
@@ -1914,6 +1930,7 @@ ck( 'the tabs are the module\'s own list, Accounts first, and a form names its t
 	),
 	array(
 		array(
+			'queue'      => 'Waiting for review',
 			'accounts'   => 'Accounts',
 			'engagement' => 'Engagement',
 			'sync'       => 'Sync',
@@ -1946,7 +1963,7 @@ foreach ( array(
 	);
 }
 
-ck( 'the tab shown is the one the address names, and Accounts when it names none or one the screen does not have',
+ck( 'the tab shown is the one the address names, and, with no application waiting, Accounts when it names none or one the screen does not have',
 	$read,
 	array(
 		'none'       => 'accounts',
@@ -1957,6 +1974,42 @@ ck( 'the tab shown is the one the address names, and Accounts when it names none
 		'empty'      => 'accounts',
 	) );
 
+// The queue first when it holds rows (design of 16 October 2026, section 3, decision 3): from the
+// menu, and for a tab the screen does not have, the screen opens on Waiting for review while
+// applications wait there, and a tab the address names is still the one shown.
+WPCPM_Mentor_Application::$waiting = 2;
+$queue_read                        = array();
+foreach ( array(
+	'none'     => null,
+	'unknown'  => 'nope',
+	'accounts' => 'accounts',
+) as $case => $value ) {
+	request( null === $value ? array( 'page' => 'wpcpm-mentors' ) : array( 'page' => 'wpcpm-mentors', 'tab' => $value ) );
+	$queue_read[ $case ] = got(
+		run(
+			function () {
+				return WPCPM_Mentors::tab();
+			}
+		),
+		function ( $out ) {
+			return $out['value'];
+		}
+	);
+}
+$queue_page = html_of( page_for( array( 'page' => 'wpcpm-mentors' ) ) );
+WPCPM_Mentor_Application::$waiting = 0;
+
+ck( 'with applications waiting, the screen opens on Waiting for review, from the menu and for a tab it does not have, and a tab the address names is still the one shown',
+	$queue_read,
+	array(
+		'none'     => 'queue',
+		'unknown'  => 'queue',
+		'accounts' => 'accounts',
+	) );
+ck( 'and the tab draws the applications, and no account list',
+	array( has( $queue_page, 'id="wpcpm-mentor-applications"' ), has( $queue_page, '<h2>Mentor accounts' ) ),
+	array( true, false ) );
+
 echo "\n=== Each tab holds its own cards ===\n";
 
 // Ada's students carry an institution the sync has not turned into a name yet, which the Sync tab
diff --git a/bin/test-submit-guard.php b/bin/test-submit-guard.php
--- a/bin/test-submit-guard.php
+++ b/bin/test-submit-guard.php
@@ -595,6 +595,7 @@ $surfaces = array(
 	'includes/modules/class-wpcpm-institution-people.php'                => array( 'admin', 'institution-dashboard' ),
 	'includes/modules/class-wpcpm-institution-students.php'              => array( 'institution-dashboard' ),
 	'includes/modules/class-wpcpm-institutions.php'                      => array( 'admin', 'administrator-dashboard' ),
+	'includes/modules/class-wpcpm-mentor-application-queue.php'          => array( 'admin' ),
 	'includes/modules/class-wpcpm-mentor-notes.php'                      => array( 'mentor-report-card' ),
 	'includes/modules/class-wpcpm-sponsor-agreement-card.php'            => array( 'sponsor-dashboard' ),
 	'includes/modules/class-wpcpm-sponsor-agreement.php'                 => array( 'administrator-dashboard' ),
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php bin/test-mentor-application-queue.php`, `php bin/test-mentors-screen.php`, `php bin/test-accounts-table.php` and `php bin/test-submit-guard.php`

```text
$ php bin/test-mentor-application-queue.php
PHP Warning:  require_once(<plugin>/includes/modules/class-wpcpm-mentor-application-queue.php): Failed to open stream: No such file or directory in <plugin>/bin/test-mentor-application-queue.php on line 71
PHP Fatal error:  Uncaught Error: Failed opening required '<plugin>/includes/modules/class-wpcpm-mentor-application-queue.php' (include_path='.:/opt/homebrew/Cellar/php/8.5.7/share/php/pear') in <plugin>/bin/test-mentor-application-queue.php:71
[exit 255]
$ php bin/test-mentors-screen.php
FAIL the bar holds four tabs, Waiting for review, Accounts, Engagement then Sync, each at the screen's address with its tab; with no application waiting, Accounts is the tab shown from the menu, when asked for and for a tab the screen does not have, and each other tab when asked for, marked for the eye and for a screen reader
FAIL the bar is the one every audience screen prints, `WPCPM_Screen_Tabs`'s, tag for tag: the screen's one bar, right under its title and lede, and no heading holds it
FAIL the labels are translated where the bar prints them, and so is the name the bar gives itself
FAIL the tabs are the module's own list, Waiting for review first, and a form names its tab in the field the list's row links carry theirs in
FAIL with applications waiting, the screen opens on Waiting for review, from the menu and for a tab it does not have, and a tab the address names is still the one shown
FAIL and the tab draws the applications, and no account list
6 FAILED (172 checks)
[exit 1]
$ php bin/test-accounts-table.php
FAIL the screen plumbing the Students and Mentors modules held twice is one trait's, which both use without declaring one of its methods again, but for a default the trait leaves to a module: the Students module's own list state, for its institution filter, and the Mentors module's load hook, choice of tab, tab labels and page, which add its Engagement and Waiting for review tabs
1 FAILED (169 checks)
[exit 1]
$ php bin/test-submit-guard.php
FAIL and every row names a file that still prints one, so a row does not outlive its file
1 FAILURE(S)
[exit 1]
```

- [ ] **Step 3: The queue and the tab**

Create `includes/modules/class-wpcpm-mentor-application-queue.php`:

```php
<?php
/**
 * Mentors module - the Waiting for review tab and its decisions (1.122.31).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where an Administrator reads a mentor application and decides it (design of 16 October 2026,
 * section 5): the Mentors screen's Waiting for review tab, in five views, each row whole, and the
 * decisions under it; then, under the views, the rows recently decided as spam or declined, which
 * no view lists, so a genuine application the checks filed as spam, or a declined applicant who
 * appeals, can still be reached (the sponsor queue's "Recently decided", its S5 review's lesson).
 *
 * **The decisions** are the Status choices the program used in Airtable, under their own names:
 *
 * - Select: to `selected`, the selection email goes, with the mentors' course invitation link;
 *   offered on a lapsed row and on a declined one too, for the applicant who appeals (section 5).
 * - Ask for more information: to `info`, the request goes; the row stays in the queue, dated.
 * - Second opinion: to `second`, no email; the note is for a colleague, and needed.
 * - Decline: to `declined`, the decline email goes; the note stays on the site.
 * - Mark as spam: to `spam`, nothing is sent.
 * - Withdraw, on a selected row whose applicant wrote that they are not continuing: to `lapsed`,
 *   nothing is sent; the note says why, and is needed.
 * - Put back in the queue, on a spam row: to `new`; a row that was never acknowledged (one the
 *   form's checks filed as spam, or one past the day's limit on acknowledgements) is sent the
 *   acknowledgement then, once, with the link that confirms the address, and one acknowledged
 *   before, marked as spam by mistake, is sent nothing.
 *
 * A decision that emails the applicant refuses an address nobody has confirmed through the
 * acknowledgement's link, as the institution approval does, and Select refuses while no course
 * invitation link is set. The email goes before the state moves: a send that fails leaves the row as
 * it was. Every decision is a press by an Administrator, the capability asked first and then the
 * nonce keyed to the decision and the row, and is written to the row's history with who and when.
 */
final class WPCPM_Mentor_Application_Queue {

	/** The seven decisions, one action apiece, so a nonce from one form cannot press another. */
	const ACTION_SELECT   = 'wpcpm_mapp_select';
	const ACTION_INFO     = 'wpcpm_mapp_info';
	const ACTION_SECOND   = 'wpcpm_mapp_second';
	const ACTION_DECLINE  = 'wpcpm_mapp_decline';
	const ACTION_SPAM     = 'wpcpm_mapp_spam';
	const ACTION_WITHDRAW = 'wpcpm_mapp_withdraw';
	const ACTION_REOPEN   = 'wpcpm_mapp_reopen';

	/** The field naming the application a decision is posted for. */
	const FIELD_APPLICATION = 'wpcpm_mapp_app';

	/** The field a decision's note is posted in. */
	const FIELD_NOTE = 'wpcpm_mapp_note';

	/** The form name a refused note's typing is kept under, with the row's ID (`WPCPM_Typed_Text::box_form()`). */
	const TYPED = 'mapp-note';

	/** A needed note: long enough to be a sentence, short enough to read. */
	const MIN_NOTE = 10;
	const MAX_NOTE = 2000;

	/** The three emails the decisions send, named for the mail log. */
	const MAIL_SELECTED = 'mentor-selected';
	const MAIL_INFO     = 'mentor-more-information';
	const MAIL_DECLINED = 'mentor-declined';

	/** The query argument the tab's views travel in. */
	const VIEW_ARG = 'mapp_view';

	/** Most rows a view draws: the oldest ones, whose turn it is. */
	const QUEUE_MAX = 50;

	/** The flash channel the Mentors screen reads its outcomes from. */
	const FLASH = 'mentors_admin';

	/**
	 * Hooks: the seven decisions, for a signed-in Administrator alone (`admin_post_`, never `nopriv`).
	 */
	public static function init() {
		foreach ( self::decisions() as $action => $method ) {
			add_action( 'admin_post_' . $action, array( __CLASS__, $method ) );
		}
	}

	/**
	 * Each decision's action and its handler.
	 *
	 * @return array<string, string>
	 */
	public static function decisions() {
		return array(
			self::ACTION_SELECT   => 'handle_select',
			self::ACTION_INFO     => 'handle_info',
			self::ACTION_SECOND   => 'handle_second',
			self::ACTION_DECLINE  => 'handle_decline',
			self::ACTION_SPAM     => 'handle_spam',
			self::ACTION_WITHDRAW => 'handle_withdraw',
			self::ACTION_REOPEN   => 'handle_reopen',
		);
	}

	/**
	 * The states a row is decided in and listed under Recently decided for, until retention deletes
	 * it: filed as spam, and declined.
	 *
	 * @return string[]
	 */
	public static function decided_states() {
		return array( WPCPM_Mentor_Application::STATE_SPAM, WPCPM_Mentor_Application::STATE_DECLINED );
	}

	/**
	 * The tab's five views, slug => the states each lists and its label, in the bar's order: the
	 * queue itself, the two kinds of row in it a person may look for, then the two the queue has let
	 * go of, the selected ones waiting for the course and the lapsed ones.
	 *
	 * @return array<string, array{states: string[], label: string}>
	 */
	public static function views() {
		return array(
			'waiting'  => array(
				'states' => WPCPM_Mentor_Application::open_states(),
				'label'  => __( 'Waiting', 'wpcredits-program-manager' ),
			),
			'info'     => array(
				'states' => array( WPCPM_Mentor_Application::STATE_INFO ),
				'label'  => __( 'Asked for more information', 'wpcredits-program-manager' ),
			),
			'second'   => array(
				'states' => array( WPCPM_Mentor_Application::STATE_SECOND ),
				'label'  => __( 'Second opinion', 'wpcredits-program-manager' ),
			),
			'selected' => array(
				'states' => array( WPCPM_Mentor_Application::STATE_SELECTED ),
				'label'  => __( 'Selected (course pending)', 'wpcredits-program-manager' ),
			),
			'lapsed'   => array(
				'states' => array( WPCPM_Mentor_Application::STATE_LAPSED ),
				'label'  => __( 'Lapsed', 'wpcredits-program-manager' ),
			),
		);
	}

	/**
	 * The view the request names, or the queue.
	 *
	 * @return string A key of `views()`.
	 */
	public static function current_view() {
		$view = WPCPM_Request::key( self::VIEW_ARG );

		return isset( self::views()[ $view ] ) ? $view : 'waiting';
	}

	/**
	 * The tab's address at one view.
	 *
	 * @param string $view A key of `views()`.
	 * @return string
	 */
	public static function view_url( $view = 'waiting' ) {
		$url = admin_url( 'admin.php?page=wpcpm-mentors&tab=queue' );

		return 'waiting' === $view ? $url : add_query_arg( self::VIEW_ARG, $view, $url );
	}

	/*
	 * The decisions
	 * --------------------------------------------------------------------
	 */

	/**
	 * What every decision does first: the capability, then the nonce keyed to the decision and the
	 * row, then the row, which must be an application in a state the decision takes.
	 *
	 * @param string   $action The decision.
	 * @param string[] $states The states it takes a row from.
	 * @return WP_Post
	 */
	private static function begin( $action, array $states ) {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( self::FIELD_APPLICATION );

		check_admin_referer( $action . '_' . $post_id );

		$post = WPCPM_Mentor_Application::application( $post_id );

		if ( null === $post ) {
			self::leave( 'mapp-unknown' );
		}

		if ( ! in_array( WPCPM_Mentor_Application::state_of( $post ), $states, true ) ) {
			self::leave( 'mapp-state' );
		}

		return $post;
	}

	/**
	 * The note posted with a decision, cleaned, or a refusal that gives the typing back: one the
	 * cleaner would take words from, one longer than the box holds, and, where the note is needed,
	 * one shorter than a sentence.
	 *
	 * @param WP_Post $post   The row.
	 * @param string  $key    The decision's own box (`select`, `info`, `second`, `decline`, `withdraw`).
	 * @param bool    $needed Whether the decision needs a note.
	 * @return string
	 */
	private static function note( WP_Post $post, $key, $needed ) {
		$form  = WPCPM_Typed_Text::box_form( self::TYPED, (int) $post->ID );
		$typed = WPCPM_Request::posted_raw( self::FIELD_NOTE );

		// What an earlier refusal of a box kept is stale once a form of this row is posted again.
		WPCPM_Typed_Text::forget( $form );

		$refused = '';

		if ( WPCPM_Typed_Text::cleaner_loses( $typed, 'lines' ) ) {
			$refused = 'mapp-note-loss';
		} elseif ( WPCPM_Typed_Text::box_length( $typed ) > self::MAX_NOTE ) {
			$refused = 'mapp-note-long';
		} elseif ( $needed && WPCPM_Typed_Text::box_length( $typed ) < self::MIN_NOTE ) {
			$refused = 'mapp-note-short';
		}

		if ( '' !== $refused ) {
			WPCPM_Typed_Text::keep_box( $form, $key, $typed, self::MAX_NOTE );
			self::leave( $refused );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- begin() checked the nonce before any handler reads this.
		return isset( $_POST[ self::FIELD_NOTE ] ) && is_scalar( $_POST[ self::FIELD_NOTE ] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST[ self::FIELD_NOTE ] ) ) ) : '';
	}

	/**
	 * The applicant's address for a decision that emails them: refused while nobody has confirmed it
	 * through the acknowledgement's link, or when the row holds none.
	 *
	 * @param WP_Post $post The row.
	 * @return string
	 */
	private static function confirmed_address( WP_Post $post ) {
		$email = WPCPM_Mentor_Application::email_of( $post );

		if ( '' === $email ) {
			self::leave( 'mapp-no-email' );
		}

		if ( ! WPCPM_Mentor_Application::is_confirmed( (int) $post->ID ) ) {
			self::leave( 'mapp-unconfirmed' );
		}

		return $email;
	}

	/**
	 * Email the applicant under one id, with their name, the reference and what the email adds, and
	 * the program's Reply-To; or leave, refused, when the email could not be handed off.
	 *
	 * @param WP_Post $post  The row.
	 * @param string  $email Where it goes.
	 * @param string  $id    The email's id.
	 * @param array   $more  What this email adds to the name and the reference.
	 */
	private static function mail( WP_Post $post, $email, $id, array $more = array() ) {
		$values = array_merge(
			array(
				'site_name'      => WPCPM_Mail::site_name(),
				'applicant_name' => WPCPM_Mentor_Application::name_of( $post ),
				'reference'      => WPCPM_Mentor_Application::reference_of( $post ),
			),
			$more
		);

		$build = static function () use ( $id, $values ) {
			return WPCPM_Mail_Templates::mail( $id, $values, array( 'headers' => WPCPM_Mentor_Application_Form::reply_headers() ) );
		};

		if ( ! WPCPM_Mail::send_to( $email, $id, $build ) ) {
			self::leave( 'mapp-not-sent' );
		}
	}

	/**
	 * Select: the selection email with the mentors' course invitation link, and the row waits for the
	 * course. From the queue, from a lapsed row whose applicant turned up again, and from a declined
	 * row whose applicant appealed (design, section 5: "a note and Select on the same row").
	 */
	public static function handle_select() {
		$post = self::begin( self::ACTION_SELECT, self::select_states() );
		$link = trim( (string) WPCPM_Settings::get_value( 'mentor_course_link', '' ) );

		if ( '' === $link ) {
			self::leave( 'mapp-no-course-link' );
		}

		$note  = self::note( $post, 'select', false );
		$email = self::confirmed_address( $post );

		self::mail(
			$post,
			$email,
			self::MAIL_SELECTED,
			array(
				'course_name'     => (string) WPCPM_Settings::get_value( 'checker_course_title', '' ),
				'course_link'     => $link,
				'program_contact' => WPCPM_Mail_Templates::program_contact(),
			)
		);

		WPCPM_Mentor_Application::decide( (int) $post->ID, WPCPM_Mentor_Application::STATE_SELECTED, WPCPM_Mentor_Application::EVENT_SELECTED, get_current_user_id(), $note );

		self::leave( 'mapp-selected' );
	}

	/**
	 * Ask for more information: the request goes, and the row stays in the queue, dated.
	 */
	public static function handle_info() {
		$post  = self::begin( self::ACTION_INFO, WPCPM_Mentor_Application::open_states() );
		$note  = self::note( $post, 'info', false );
		$email = self::confirmed_address( $post );

		self::mail( $post, $email, self::MAIL_INFO );

		WPCPM_Mentor_Application::decide( (int) $post->ID, WPCPM_Mentor_Application::STATE_INFO, WPCPM_Mentor_Application::EVENT_INFO, get_current_user_id(), $note );

		self::leave( 'mapp-info' );
	}

	/**
	 * Second opinion: the row is marked for a colleague and stays in the queue. Nothing is sent, so the
	 * address needs no confirming; the note is what the colleague reads, so it is needed.
	 */
	public static function handle_second() {
		$post = self::begin( self::ACTION_SECOND, WPCPM_Mentor_Application::open_states() );
		$note = self::note( $post, 'second', true );

		WPCPM_Mentor_Application::decide( (int) $post->ID, WPCPM_Mentor_Application::STATE_SECOND, WPCPM_Mentor_Application::EVENT_SECOND, get_current_user_id(), $note );

		self::leave( 'mapp-second' );
	}

	/**
	 * Decline: the decline email goes, and the row closes. The note stays on the site and is never sent.
	 */
	public static function handle_decline() {
		$post  = self::begin( self::ACTION_DECLINE, WPCPM_Mentor_Application::open_states() );
		$note  = self::note( $post, 'decline', false );
		$email = self::confirmed_address( $post );

		self::mail( $post, $email, self::MAIL_DECLINED );

		WPCPM_Mentor_Application::decide( (int) $post->ID, WPCPM_Mentor_Application::STATE_DECLINED, WPCPM_Mentor_Application::EVENT_DECLINED, get_current_user_id(), $note );

		self::leave( 'mapp-declined' );
	}

	/**
	 * Mark as spam: the row closes and nothing is sent, since the address on it is forged or somebody
	 * else's.
	 */
	public static function handle_spam() {
		$post = self::begin( self::ACTION_SPAM, WPCPM_Mentor_Application::open_states() );

		WPCPM_Mentor_Application::decide( (int) $post->ID, WPCPM_Mentor_Application::STATE_SPAM, WPCPM_Mentor_Application::EVENT_SPAM, get_current_user_id() );

		self::leave( 'mapp-spam' );
	}

	/**
	 * Withdraw: a selected applicant wrote that they are not continuing, so the row lapses now, with
	 * the note saying so, and nothing is sent, as Airtable's Dropped out sent nothing.
	 */
	public static function handle_withdraw() {
		$post = self::begin( self::ACTION_WITHDRAW, array( WPCPM_Mentor_Application::STATE_SELECTED ) );
		$note = self::note( $post, 'withdraw', true );

		WPCPM_Mentor_Application::decide( (int) $post->ID, WPCPM_Mentor_Application::STATE_LAPSED, WPCPM_Mentor_Application::EVENT_WITHDRAWN, get_current_user_id(), $note );

		self::leave( 'mapp-withdrawn' );
	}

	/**
	 * Put back in the queue: a row filed as spam is `new` again, for a person to decide. One that was
	 * never acknowledged, which the form's checks filed or which came past the day's limit on
	 * acknowledgements, is sent the acknowledgement now, through the form's own sender, so its
	 * applicant has the link that confirms the address; it goes once, since the history then says it
	 * went. One acknowledged before, marked as spam by mistake, is sent nothing, and so is one with no
	 * address. A send that fails leaves the row as it was.
	 */
	public static function handle_reopen() {
		$post = self::begin( self::ACTION_REOPEN, array( WPCPM_Mentor_Application::STATE_SPAM ) );
		$send = ! self::acknowledged( $post ) && '' !== WPCPM_Mentor_Application::email_of( $post );

		if ( $send && ! WPCPM_Mentor_Application_Form::mail_applicant( (int) $post->ID ) ) {
			self::leave( 'mapp-not-sent' );
		}

		WPCPM_Mentor_Application::decide( (int) $post->ID, WPCPM_Mentor_Application::STATE_NEW, $send ? WPCPM_Mentor_Application::EVENT_REOPENED_ACKNOWLEDGED : WPCPM_Mentor_Application::EVENT_REOPENED, get_current_user_id() );

		self::leave( $send ? 'mapp-reopened-sent' : 'mapp-reopened' );
	}

	/**
	 * Whether the applicant was sent the acknowledgement, with the link that confirms the address:
	 * every row but one the form's checks filed as spam (the honeypot, the dwell token) or one past
	 * the day's limit on acknowledgements, until Put back in the queue sends it.
	 *
	 * @param WP_Post $post The row.
	 * @return bool
	 */
	private static function acknowledged( WP_Post $post ) {
		$withheld = array_intersect( array( 'mail-ceiling', 'honeypot', 'dwell' ), WPCPM_Mentor_Application::signals_of( $post ) );

		return array() === $withheld || 0 < WPCPM_Mentor_Application::last_at( (int) $post->ID, WPCPM_Mentor_Application::EVENT_REOPENED_ACKNOWLEDGED );
	}

	/**
	 * The states Select takes a row from: the four open ones, lapsed and declined.
	 *
	 * @return string[]
	 */
	private static function select_states() {
		return array_merge( WPCPM_Mentor_Application::open_states(), array( WPCPM_Mentor_Application::STATE_LAPSED, WPCPM_Mentor_Application::STATE_DECLINED ) );
	}

	/**
	 * Back to the tab with a one-shot outcome, at the view the press was made on.
	 *
	 * @param string $status A key of `messages()`.
	 */
	private static function leave( $status ) {
		$view = WPCPM_Request::posted_key( self::VIEW_ARG, 'waiting' );

		WPCPM_Flash::set( self::FLASH, $status );
		wp_safe_redirect( self::view_url( isset( self::views()[ $view ] ) ? $view : 'waiting' ) );
		exit;
	}

	/**
	 * What each press can leave, in the Administrator's words.
	 *
	 * @return array<string, array{0: string, 1: string}> Status => notice class and sentence.
	 */
	public static function messages() {
		return array(
			'mapp-selected'       => array( 'success', __( 'The applicant is selected, and the selection email with the mentors\' course invitation link is sent. The application waits for the course under Selected (course pending).', 'wpcredits-program-manager' ) ),
			'mapp-info'           => array( 'success', __( 'The request for more information is sent. The application stays in the queue, under Asked for more information; record what comes back in the note of the decision you take next.', 'wpcredits-program-manager' ) ),
			'mapp-second'         => array( 'success', __( 'The application is marked for a second opinion, with your note for a colleague. Nothing was sent to the applicant.', 'wpcredits-program-manager' ) ),
			'mapp-declined'       => array( 'success', __( 'The application is declined, and the decline email is sent. Your note stays on this site.', 'wpcredits-program-manager' ) ),
			'mapp-spam'           => array( 'success', __( 'The application is marked as spam. Nothing was sent to the address on it.', 'wpcredits-program-manager' ) ),
			'mapp-withdrawn'      => array( 'success', __( 'The application is withdrawn and listed under Lapsed, with your note. Nothing was sent to the applicant, and Select is offered there if they come back.', 'wpcredits-program-manager' ) ),
			'mapp-reopened'       => array( 'success', __( 'The application is back in the queue, under Waiting. Nothing was sent to the address on it.', 'wpcredits-program-manager' ) ),
			'mapp-reopened-sent'  => array( 'success', __( 'The application is back in the queue, under Waiting, and the acknowledgement it never had is sent, with the link that confirms the address.', 'wpcredits-program-manager' ) ),
			'mapp-unknown'        => array( 'error', __( 'There is no mentor application with that ID.', 'wpcredits-program-manager' ) ),
			'mapp-state'          => array( 'error', __( 'That application is not in a state this decision applies to. Reload the page to see where it got to.', 'wpcredits-program-manager' ) ),
			'mapp-unconfirmed'    => array( 'error', __( 'Nothing was sent. The applicant has not confirmed the address with the link in the acknowledgement, and a decision that emails them waits until they have. If they never do, Mark as spam closes the application without writing to anyone.', 'wpcredits-program-manager' ) ),
			'mapp-no-email'       => array( 'error', __( 'Nothing was sent. That application holds no address an email can go to.', 'wpcredits-program-manager' ) ),
			'mapp-no-course-link' => array( 'error', __( 'Nothing was sent. The selection email gives the mentors\' course invitation link, and none is set: fill in Mentors\' course invitation link on the Students and mentors tab of WPCredits Program > Settings.', 'wpcredits-program-manager' ) ),
			'mapp-not-sent'       => array( 'error', __( 'The email could not be handed off, so the application was left as it was. The Log tab of Tools > Emails says what happened to it.', 'wpcredits-program-manager' ) ),
			/* translators: %s: the least length of a note, in characters. */
			'mapp-note-short'     => array( 'error', sprintf( __( 'Nothing was done. This decision needs a note of at least %s characters, for whoever reads the application next.', 'wpcredits-program-manager' ), number_format_i18n( self::MIN_NOTE ) ) ),
			/* translators: %s: the longest note allowed, in characters. */
			'mapp-note-long'      => array( 'error', sprintf( __( 'Nothing was done. The note is longer than %s characters: shorten it and press again.', 'wpcredits-program-manager' ), number_format_i18n( self::MAX_NOTE ) ) ),
			'mapp-note-loss'      => array( 'error', WPCPM_Typed_Text::loss_message( __( 'Note', 'wpcredits-program-manager' ), 'save' ) ),
		);
	}

	/*
	 * The tab
	 * --------------------------------------------------------------------
	 */

	/**
	 * The Waiting for review tab: the views with their counts, then the view's rows, oldest first,
	 * each whole with the decisions its state allows. The capability is asked here as well as by the
	 * screen: every answer a stranger typed is printed below.
	 */
	public static function render() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		$view  = self::current_view();
		$views = self::views();
		$total = WPCPM_Mentor_Application::count_in( $views[ $view ]['states'] );
		$rows  = WPCPM_Mentor_Application::applications( $views[ $view ]['states'], self::QUEUE_MAX );

		echo '<div class="wpcpm-card" id="wpcpm-mentor-applications">';
		printf( '<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>', esc_html__( 'Mentor applications', 'wpcredits-program-manager' ), esc_html( number_format_i18n( WPCPM_Mentor_Application::waiting_count() ) ) );
		echo '<p class="description">' . esc_html__( 'People who applied to mentor through the form on the site, oldest first. Select sends the mentors\' course invitation link; Ask for more information and Decline send their emails; Second opinion, Mark as spam and Withdraw send nothing. A decision that emails the applicant waits until they have confirmed the address.', 'wpcredits-program-manager' ) . '</p>';

		echo '<ul class="subsubsub">';

		$links = array();

		foreach ( $views as $slug => $about ) {
			$links[] = sprintf(
				'<li class="%1$s"><a href="%2$s"%3$s>%4$s <span class="count">(%5$s)</span></a>',
				esc_attr( $slug ),
				esc_url( self::view_url( $slug ) ),
				$slug === $view ? ' class="current" aria-current="page"' : '',
				esc_html( $about['label'] ),
				esc_html( number_format_i18n( WPCPM_Mentor_Application::count_in( $about['states'] ) ) )
			);
		}

		echo implode( ' |</li>', $links ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each link is built escaped above.
		echo '</ul><div class="clear"></div>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'Nothing here. New applications appear in Waiting.', 'wpcredits-program-manager' ) . '</p>';
			self::render_decided( $view );
			echo '</div>';

			return;
		}

		if ( count( $rows ) < $total ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: how many rows are drawn, 2: how many there are. */
						__( 'Showing the oldest %1$s of %2$s. As these are decided, the next take their place.', 'wpcredits-program-manager' ),
						number_format_i18n( count( $rows ) ),
						number_format_i18n( $total )
					)
				)
			);
		}

		echo '<ol class="wpcpm-queue">';

		foreach ( $rows as $post ) {
			self::render_row( $post, $view );
		}

		echo '</ol>';

		self::render_decided( $view );

		echo '</div>';
	}

	/**
	 * Recently decided: the rows filed as spam or declined, newest decision first, the fifty newest,
	 * each whole with the decision its state allows. Retention takes them away in time.
	 *
	 * @param string $view The view the tab is drawn in, which a press here comes back to.
	 */
	private static function render_decided( $view ) {
		$rows = WPCPM_Mentor_Application::applications( self::decided_states() );

		usort(
			$rows,
			static function ( $a, $b ) {
				return WPCPM_Mentor_Application::last_at( (int) $b->ID ) - WPCPM_Mentor_Application::last_at( (int) $a->ID );
			}
		);

		echo '<section class="wpcpm-mapp-decided">';
		printf( '<h3>%s</h3>', esc_html__( 'Recently decided', 'wpcredits-program-manager' ) );

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No application has been filed as spam or declined.', 'wpcredits-program-manager' ) . '</p>';
			echo '</section>';

			return;
		}

		echo '<p class="description">' . esc_html__( 'The applications filed as spam and the declined ones, newest first, until they are deleted. Put back in the queue returns one filed as spam to Waiting, and sends the acknowledgement to one that never had it; Select is offered on a declined one, for an applicant who appeals.', 'wpcredits-program-manager' ) . '</p>';
		echo '<ol class="wpcpm-queue">';

		foreach ( array_slice( $rows, 0, self::QUEUE_MAX ) as $post ) {
			self::render_row( $post, $view );
		}

		echo '</ol></section>';
	}

	/**
	 * One application, whole: who and when, its marks, its answers, its history and its decisions.
	 *
	 * @param WP_Post $post The row.
	 * @param string  $view The view it is drawn in.
	 */
	private static function render_row( WP_Post $post, $view ) {
		$state  = WPCPM_Mentor_Application::state_of( $post );
		$name   = WPCPM_Mentor_Application::name_of( $post );
		$anchor = 'wpcpm-mentor-application-' . (int) $post->ID;

		printf( '<li class="wpcpm-queue-item" id="%s">', esc_attr( $anchor ) );
		printf(
			'<h3 class="wpcpm-queue-title"><span class="wpcpm-inst-name__text">%1$s</span> <span class="wpcpm-inst-muted">%2$s</span></h3>',
			esc_html( '' !== $name ? $name : __( '(no name)', 'wpcredits-program-manager' ) ),
			esc_html( WPCPM_Mentor_Application::reference_of( $post ) )
		);
		printf( '<p class="wpcpm-queue-age">%s</p>', esc_html( self::age_line( $post, $state ) ) );

		self::render_marks( $post, $state );
		self::render_answers( $post );
		self::render_history( $post );
		self::render_decisions( $post, $state, $view );

		echo '</li>';
	}

	/**
	 * When the row arrived, or for a selected or lapsed row, when that happened.
	 *
	 * @param WP_Post $post  The row.
	 * @param string  $state Its state.
	 * @return string
	 */
	private static function age_line( WP_Post $post, $state ) {
		$format = (string) get_option( 'date_format', 'F j, Y' );

		if ( WPCPM_Mentor_Application::STATE_SELECTED === $state ) {
			$selected = WPCPM_Mentor_Application::last_at( (int) $post->ID, WPCPM_Mentor_Application::EVENT_SELECTED );
			$days     = max( 14, (int) WPCPM_Settings::get_value( 'mentor_lapse_days', 90 ) );

			return sprintf(
				/* translators: 1: the day the applicant was selected, 2: the day the application lapses. */
				__( 'Selected on %1$s. It lapses on %2$s if the course has not shown by then.', 'wpcredits-program-manager' ),
				wp_date( $format, $selected ),
				wp_date( $format, $selected + $days * DAY_IN_SECONDS )
			);
		}

		if ( WPCPM_Mentor_Application::STATE_LAPSED === $state ) {
			return sprintf(
				/* translators: %s: the day the application lapsed. */
				__( 'Lapsed on %s.', 'wpcredits-program-manager' ),
				wp_date( $format, WPCPM_Mentor_Application::last_at( (int) $post->ID ) )
			);
		}

		if ( WPCPM_Mentor_Application::STATE_DECLINED === $state ) {
			return sprintf(
				/* translators: %s: the day the application was declined. */
				__( 'Declined on %s.', 'wpcredits-program-manager' ),
				wp_date( $format, WPCPM_Mentor_Application::last_at( (int) $post->ID ) )
			);
		}

		if ( WPCPM_Mentor_Application::STATE_SPAM === $state ) {
			return sprintf(
				/* translators: %s: the day the application was filed as spam. */
				__( 'Filed as spam on %s.', 'wpcredits-program-manager' ),
				wp_date( $format, WPCPM_Mentor_Application::last_at( (int) $post->ID ) )
			);
		}

		$arrived = (int) get_post_time( 'U', true, $post );

		return sprintf(
			/* translators: 1: how long the application has waited, 2: the day it arrived. */
			__( 'Waiting %1$s, since %2$s.', 'wpcredits-program-manager' ),
			human_time_diff( $arrived, time() ),
			wp_date( $format, $arrived )
		);
	}

	/**
	 * The marks a row carries: held and why, a possible duplicate, a mentor already, the address
	 * unconfirmed, and when more information was asked for.
	 *
	 * @param WP_Post $post  The row.
	 * @param string  $state Its state.
	 */
	private static function render_marks( WP_Post $post, $state ) {
		$signals = WPCPM_Mentor_Application::signals_of( $post );
		$marks   = array();

		if ( WPCPM_Mentor_Application::STATE_HELD === $state ) {
			$marks[] = array( 'held', __( 'held', 'wpcredits-program-manager' ), __( 'The form\'s checks held it for a person to read first. Nobody was refused, and the applicant was acknowledged.', 'wpcredits-program-manager' ) );
		}

		if ( in_array( WPCPM_Mentor_Application_Form::SIGNAL_DUPLICATE, $signals, true ) ) {
			$marks[] = array( 'duplicate', __( 'possible duplicate', 'wpcredits-program-manager' ), __( 'Another application in the queue or waiting for the course has the same address or WordPress.org username. Nothing is ever merged.', 'wpcredits-program-manager' ) );
		}

		if ( in_array( WPCPM_Mentor_Application_Form::SIGNAL_MENTOR, $signals, true ) ) {
			$marks[] = array( 'account', __( 'already a mentor', 'wpcredits-program-manager' ), __( 'A mentor the site knows, current or former, has this address or WordPress.org username.', 'wpcredits-program-manager' ) );
		}

		if ( ! WPCPM_Mentor_Application::is_confirmed( (int) $post->ID ) ) {
			$marks[] = array( 'unconfirmed', __( 'address not confirmed', 'wpcredits-program-manager' ), __( 'The applicant has not followed the link in the acknowledgement yet, so Select, Ask for more information and Decline wait.', 'wpcredits-program-manager' ) );
		}

		// The form sends no acknowledgement for a row its checks filed as spam, nor past the day's
		// limit on acknowledgements, so that applicant has no link to confirm the address with until
		// Put back in the queue sends it.
		if ( ! self::acknowledged( $post ) ) {
			$marks[] = array( 'unacknowledged', __( 'acknowledgement not sent', 'wpcredits-program-manager' ), __( 'The acknowledgement was not sent, so the applicant has no link to confirm the address with: Select, Ask for more information and Decline wait. Put back in the queue sends it to an application filed as spam, once.', 'wpcredits-program-manager' ) );
		}

		foreach ( $marks as $mark ) {
			printf(
				'<p><span class="wpcpm-inst-mark wpcpm-inst-mark--%1$s">%2$s</span> %3$s</p>',
				esc_attr( $mark[0] ),
				esc_html( $mark[1] ),
				esc_html( $mark[2] )
			);
		}

		if ( WPCPM_Mentor_Application::STATE_INFO === $state ) {
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the day more information was asked for. */
						__( 'Asked for more information on %s.', 'wpcredits-program-manager' ),
						wp_date( (string) get_option( 'date_format', 'F j, Y' ), WPCPM_Mentor_Application::last_at( (int) $post->ID, WPCPM_Mentor_Application::EVENT_INFO ) )
					)
				)
			);
		}
	}

	/**
	 * Every answer, in the order the form asked, each printed escaped: a stranger's words on an
	 * Administrator's screen. The profile is a link, with the username beside it.
	 *
	 * @param WP_Post $post The row.
	 */
	private static function render_answers( WP_Post $post ) {
		$fields = WPCPM_Mentor_Application::fields_of( $post );

		echo '<table class="widefat striped wpcpm-list wpcpm-app-answers"><tbody>';

		foreach ( WPCPM_Mentor_Application_Form::fields() as $key => $spec ) {
			if ( 'consent' === $spec['type'] ) {
				continue;
			}

			$answer = isset( $fields[ $key ] ) ? $fields[ $key ] : '';
			$label  = $spec['label'];

			if ( 'profile' === $spec['type'] && '' !== (string) $answer ) {
				$cell = sprintf( '<a href="%1$s" rel="noopener">%2$s</a>', esc_url( WPCPM_Mentor_Application::profile_url( (string) $answer ) ), esc_html( (string) $answer ) );
			} elseif ( 'email' === $spec['type'] && '' !== (string) $answer ) {
				$cell = esc_html(
					sprintf(
						WPCPM_Mentor_Application::is_confirmed( (int) $post->ID )
							/* translators: %s: an address. */
							? __( '%s (confirmed)', 'wpcredits-program-manager' )
							/* translators: %s: an address. */
							: __( '%s (not confirmed yet)', 'wpcredits-program-manager' ),
						(string) $answer
					)
				);
			} elseif ( 'statement' === $spec['type'] ) {
				$cell = esc_html( true === $answer ? __( 'Agreed', 'wpcredits-program-manager' ) : __( 'Not agreed', 'wpcredits-program-manager' ) );
			} else {
				$text = is_array( $answer ) ? implode( ', ', array_map( 'strval', $answer ) ) : (string) $answer;
				$cell = '' !== trim( $text ) ? nl2br( esc_html( $text ) ) : '<span class="wpcpm-inst-muted">' . esc_html__( 'no answer', 'wpcredits-program-manager' ) . '</span>';
			}

			printf( '<tr><th scope="row">%1$s</th><td>%2$s</td></tr>', esc_html( $label ), $cell ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each cell is built escaped above.
		}

		echo '</tbody></table>';
	}

	/**
	 * The row's history: each decision with its day, who took it and the note they wrote.
	 *
	 * @param WP_Post $post The row.
	 */
	private static function render_history( WP_Post $post ) {
		$events = WPCPM_Mentor_Application::events( (int) $post->ID );
		$words  = self::event_words();
		$format = (string) get_option( 'date_format', 'F j, Y' );

		printf( '<h4>%s</h4>', esc_html__( 'History', 'wpcredits-program-manager' ) );
		echo '<ul class="wpcpm-app-history">';

		foreach ( $events as $row ) {
			$who = $row['actor'] > 0 ? get_userdata( $row['actor'] ) : null;

			printf(
				'<li>%1$s%2$s</li>',
				esc_html(
					sprintf(
						/* translators: 1: a day, 2: what happened, 3: who did it. */
						__( '%1$s: %2$s, %3$s.', 'wpcredits-program-manager' ),
						wp_date( $format, $row['at'] ),
						isset( $words[ $row['event'] ] ) ? $words[ $row['event'] ] : $row['event'],
						$who instanceof WP_User ? $who->display_name : ( $row['actor'] > 0 ? __( 'an Administrator', 'wpcredits-program-manager' ) : __( 'by the site', 'wpcredits-program-manager' ) )
					)
				),
				'' !== $row['note'] ? '<br />' . nl2br( esc_html( $row['note'] ) ) : ''
			);
		}

		echo '</ul>';
	}

	/**
	 * What the history calls each event, in the Administrator's words.
	 *
	 * @return array<string, string>
	 */
	private static function event_words() {
		return array(
			WPCPM_Mentor_Application::EVENT_SUBMITTED => __( 'applied', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_CONFIRMED => __( 'confirmed the address', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_SELECTED  => __( 'selected', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_INFO      => __( 'asked for more information', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_SECOND    => __( 'asked for a second opinion', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_DECLINED  => __( 'declined', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_SPAM      => __( 'marked as spam', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_WITHDRAWN => __( 'withdrawn', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_REOPENED  => __( 'put back in the queue', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_REOPENED_ACKNOWLEDGED => __( 'put back in the queue, acknowledgement sent', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_LAPSED    => __( 'lapsed', 'wpcredits-program-manager' ),
			WPCPM_Mentor_Application::EVENT_ACTIVATED => __( 'made a mentor', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The decisions the row's state allows, each its own form.
	 *
	 * @param WP_Post $post  The row.
	 * @param string  $state Its state.
	 * @param string  $view  The view it is drawn in, which a press comes back to.
	 */
	private static function render_decisions( WP_Post $post, $state, $view ) {
		$name = WPCPM_Mentor_Application::name_of( $post );
		$name = '' !== $name ? $name : WPCPM_Mentor_Application::reference_of( $post );
		$open = in_array( $state, WPCPM_Mentor_Application::open_states(), true );
		$kept = WPCPM_Typed_Text::box_form( self::TYPED, (int) $post->ID );

		if ( in_array( $state, self::select_states(), true ) ) {
			self::render_form(
				$post,
				$view,
				array(
					'action'  => self::ACTION_SELECT,
					'label'   => __( 'Select', 'wpcredits-program-manager' ),
					'class'   => 'button button-primary',
					'note'    => __( 'A note for the next Administrator, optional. It is never sent.', 'wpcredits-program-manager' ),
					'kept'    => WPCPM_Typed_Text::kept_box( $kept, 'select' ),
					'confirm' => sprintf(
						/* translators: %s: the applicant's name. */
						__( 'Select %s and send the selection email with the mentors\' course invitation link?', 'wpcredits-program-manager' ),
						$name
					),
				)
			);
		}

		if ( $open ) {
			self::render_form(
				$post,
				$view,
				array(
					'action' => self::ACTION_INFO,
					'label'  => __( 'Ask for more information', 'wpcredits-program-manager' ),
					'note'   => __( 'What you are asking about, for the next Administrator, optional. The email is the template\'s; this is never sent.', 'wpcredits-program-manager' ),
					'kept'   => WPCPM_Typed_Text::kept_box( $kept, 'info' ),
				)
			);
			self::render_form(
				$post,
				$view,
				array(
					'action' => self::ACTION_SECOND,
					'label'  => __( 'Second opinion', 'wpcredits-program-manager' ),
					'note'   => __( 'What a colleague should look at. Needed; nothing is sent to the applicant.', 'wpcredits-program-manager' ),
					'needed' => true,
					'kept'   => WPCPM_Typed_Text::kept_box( $kept, 'second' ),
				)
			);
			self::render_form(
				$post,
				$view,
				array(
					'action'  => self::ACTION_DECLINE,
					'label'   => __( 'Decline', 'wpcredits-program-manager' ),
					'note'    => __( 'Why, for the next Administrator, optional. It is never sent.', 'wpcredits-program-manager' ),
					'kept'    => WPCPM_Typed_Text::kept_box( $kept, 'decline' ),
					'confirm' => sprintf(
						/* translators: %s: the applicant's name. */
						__( 'Decline the application from %s and send the decline email?', 'wpcredits-program-manager' ),
						$name
					),
				)
			);
			self::render_form(
				$post,
				$view,
				array(
					'action'  => self::ACTION_SPAM,
					'label'   => __( 'Mark as spam', 'wpcredits-program-manager' ),
					'confirm' => sprintf(
						/* translators: %s: the applicant's name. */
						__( 'Mark the application from %s as spam? Nothing is sent to the address on it.', 'wpcredits-program-manager' ),
						$name
					),
				)
			);
		}

		if ( WPCPM_Mentor_Application::STATE_SPAM === $state ) {
			self::render_form(
				$post,
				$view,
				array(
					'action' => self::ACTION_REOPEN,
					'label'  => __( 'Put back in the queue', 'wpcredits-program-manager' ),
				)
			);
		}

		if ( WPCPM_Mentor_Application::STATE_SELECTED === $state ) {
			self::render_form(
				$post,
				$view,
				array(
					'action'  => self::ACTION_WITHDRAW,
					'label'   => __( 'Withdraw', 'wpcredits-program-manager' ),
					'note'    => __( 'Why: what the applicant wrote. Needed; nothing is sent to them.', 'wpcredits-program-manager' ),
					'needed'  => true,
					'kept'    => WPCPM_Typed_Text::kept_box( $kept, 'withdraw' ),
					'confirm' => sprintf(
						/* translators: %s: the applicant's name. */
						__( 'Withdraw %s? The application moves to Lapsed and nothing is sent.', 'wpcredits-program-manager' ),
						$name
					),
				)
			);
		}
	}

	/**
	 * One decision's form: the nonce keyed to the decision and the row, the view to come back to, a
	 * note box where the decision takes one, and the button, which asks first where `confirm` says.
	 *
	 * @param WP_Post $post The row.
	 * @param string  $view The view to come back to.
	 * @param array   $args `action`, `label`, `class`, `note` (the box's label, or '' for no box),
	 *                      `needed`, `kept` (a refused press's typing, or null) and `confirm`.
	 */
	private static function render_form( WP_Post $post, $view, array $args ) {
		$args = array_merge(
			array(
				'action'  => '',
				'label'   => '',
				'class'   => 'button',
				'note'    => '',
				'needed'  => false,
				'kept'    => null,
				'confirm' => '',
			),
			$args
		);
		$box  = $args['action'] . '-' . (int) $post->ID;

		printf(
			'<form class="wpcpm-app-action" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s"%3$s>',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Working', 'wpcredits-program-manager' ),
			'' !== $args['confirm'] ? ' data-wpcpm-confirm="' . esc_attr( $args['confirm'] ) . '"' : ''
		);
		wp_nonce_field( $args['action'] . '_' . (int) $post->ID );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $args['action'] ) );
		printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( self::FIELD_APPLICATION ), (int) $post->ID );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::VIEW_ARG ), esc_attr( $view ) );

		if ( '' !== $args['note'] ) {
			printf(
				'<p class="wpcpm-app-action__note"><label for="%1$s">%2$s</label><br /><textarea id="%1$s" name="%3$s" rows="2" maxlength="%4$d"%5$s>%6$s</textarea></p>',
				esc_attr( $box ),
				esc_html( $args['note'] ),
				esc_attr( self::FIELD_NOTE ),
				(int) WPCPM_Typed_Text::drawn_limit( '', self::MAX_NOTE ),
				$args['needed'] ? ' minlength="' . (int) self::MIN_NOTE . '" required="required"' : '',
				// The parser drops one line feed right after the opening tag, so kept typing that
				// begins with a line break keeps it.
				is_string( $args['kept'] ) ? "\n" . esc_textarea( $args['kept'] ) : ''
			);
		}

		printf( '<button type="submit" class="%1$s">%2$s</button>', esc_attr( $args['class'] ), esc_html( $args['label'] ) );
		echo '</form>';
	}
}
```

```diff
diff --git a/includes/modules/class-wpcpm-mentors.php b/includes/modules/class-wpcpm-mentors.php
--- a/includes/modules/class-wpcpm-mentors.php
+++ b/includes/modules/class-wpcpm-mentors.php
@@ -16,9 +16,10 @@ if ( ! defined( 'ABSPATH' ) ) {
  * listing their assigned students, and reports on the last sync.
  *
  * Its screen is the accounts screen every audience's module shares (`WPCPM_Accounts_Screen`), in
- * three tabs: Accounts, Engagement (how each mentor uses the site, 1.122.26) and Sync. What is the
- * mentors' own is here: the words, the table, the invitations card, the Engagement tab with its
- * table, and the Sync tab with its warning while names are still unread.
+ * four tabs: Waiting for review (the applications to mentor, 1.122.31), Accounts, Engagement (how
+ * each mentor uses the site, 1.122.26) and Sync. What is the mentors' own is here: the words, the
+ * table, the invitations card, the Engagement tab with its table, the Sync tab with its warning while
+ * names are still unread, and the screen's choice of the tab it opens on.
  */
 class WPCPM_Mentors extends WPCPM_Sync_Module {
 
@@ -45,12 +46,16 @@ class WPCPM_Mentors extends WPCPM_Sync_Module {
 	/** The screen's tab that shows how each mentor uses the site (1.122.26). */
 	const TAB_ENGAGEMENT = 'engagement';
 
+	/** The screen's tab the applications to mentor are decided on (1.122.31), the slug the other two queues use. */
+	const TAB_QUEUE = 'queue';
+
 	/**
-	 * The screen's tabs, slug => label, in the bar's order: the accounts, the tab the screen opens
-	 * on, how each mentor uses the site, then the sync. English, as a constant has to hold them;
-	 * `tab_labels()` translates them.
+	 * The screen's tabs, slug => label, in the bar's order: the applications waiting for review, the
+	 * accounts, how each mentor uses the site, then the sync. English, as a constant has to hold them;
+	 * `tab_labels()` translates them. The tab the screen opens on is `tab()`'s to choose.
 	 */
 	const TABS = array(
+		self::TAB_QUEUE      => 'Waiting for review',
 		self::TAB_ACCOUNTS   => 'Accounts',
 		self::TAB_ENGAGEMENT => 'Engagement',
 		self::TAB_SYNC       => 'Sync',
@@ -122,9 +127,11 @@ class WPCPM_Mentors extends WPCPM_Sync_Module {
 		WPCPM_Mentor_Calls::init();
 		WPCPM_Call_Calendar::init();
 		WPCPM_Group_Sessions::init();
-		// The applications to mentor (1.122.31): the post type they are kept in, and the public form.
+		// The applications to mentor (1.122.31): the post type they are kept in, the public form, and
+		// the decisions taken on the Waiting for review tab.
 		WPCPM_Mentor_Application::init();
 		WPCPM_Mentor_Application_Form::init();
+		WPCPM_Mentor_Application_Queue::init();
 
 		// The hourly call reminders, put back on the clock whenever they are missing, as the
 		// sync above does for its own job: the activation hook is the only other caller and
@@ -329,6 +336,23 @@ class WPCPM_Mentors extends WPCPM_Sync_Module {
 		return ( $rows >= 1 && $rows <= 999 ) ? $rows : false;
 	}
 
+	/**
+	 * The tab the screen shows: the one its address names, or, from the menu and for a tab the screen
+	 * does not have, Waiting for review while applications wait there and Accounts while none do
+	 * (design of 16 October 2026, section 3, decision 3: the queue first when it holds rows).
+	 *
+	 * @return string A key of TABS.
+	 */
+	public static function tab() {
+		$tab = WPCPM_Request::key( 'tab' );
+
+		if ( isset( self::TABS[ $tab ] ) ) {
+			return $tab;
+		}
+
+		return WPCPM_Mentor_Application::waiting_count() > 0 ? self::TAB_QUEUE : self::TAB_ACCOUNTS;
+	}
+
 	/**
 	 * The tabs as the bar prints them: TABS, each label translated.
 	 *
@@ -336,6 +360,7 @@ class WPCPM_Mentors extends WPCPM_Sync_Module {
 	 */
 	private static function tab_labels() {
 		$translated = array(
+			self::TAB_QUEUE      => __( 'Waiting for review', 'wpcredits-program-manager' ),
 			self::TAB_ACCOUNTS   => __( 'Accounts', 'wpcredits-program-manager' ),
 			self::TAB_ENGAGEMENT => __( 'Engagement', 'wpcredits-program-manager' ),
 			self::TAB_SYNC       => __( 'Sync', 'wpcredits-program-manager' ),
@@ -360,6 +385,8 @@ class WPCPM_Mentors extends WPCPM_Sync_Module {
 			$this->render_tab_sync();
 		} elseif ( self::TAB_ENGAGEMENT === $tab ) {
 			$this->render_tab_engagement();
+		} elseif ( self::TAB_QUEUE === $tab ) {
+			$this->render_tab_queue();
 		} else {
 			$this->render_tab_accounts();
 		}
@@ -367,6 +394,16 @@ class WPCPM_Mentors extends WPCPM_Sync_Module {
 		echo '</div>';
 	}
 
+	/**
+	 * The Waiting for review tab: what the last decision pressed on it did, then the applications to
+	 * mentor, read and decided here (`WPCPM_Mentor_Application_Queue`).
+	 */
+	private function render_tab_queue() {
+		$this->render_notice_from( WPCPM_Mentor_Application_Queue::messages() );
+
+		WPCPM_Mentor_Application_Queue::render();
+	}
+
 	/**
 	 * The Engagement tab: the headline figures, then every mentor in the list. Read-only.
 	 */
diff --git a/uninstall.php b/uninstall.php
--- a/uninstall.php
+++ b/uninstall.php
@@ -103,6 +103,7 @@ require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-group-s
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentor-engagement.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentor-application.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentor-application-form.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentor-application-queue.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-institutions.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-countries.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-institutions-index.php';
diff --git a/wpcredits-program-manager.php b/wpcredits-program-manager.php
--- a/wpcredits-program-manager.php
+++ b/wpcredits-program-manager.php
@@ -93,6 +93,7 @@ require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-group-sessions.php
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-engagement.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-form.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-queue.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-countries.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';
```

- [ ] **Step 4: Run them to verify they pass**

Run: the four suites of step 2.

```text
$ php bin/test-mentor-application-queue.php
ALL PASS (42 checks)
[exit 0]
$ php bin/test-mentors-screen.php
ALL PASS (172 checks)
[exit 0]
$ php bin/test-accounts-table.php
ALL PASS (169 checks)
[exit 0]
$ php bin/test-submit-guard.php
ALL PASS
[exit 0]
```

- [ ] **Step 5: The checks every task ends with**

```text
battery done
156 classes, 15008 references checked, 88 form handlers scanned - all resolve
159 PHP files, 8 blocks, 4 track seeds, 23 fixture and tooling files, the guides and the readme: US English throughout.
281 annotations, 0 dead.
Ran 135 scripts from bin/, each in a PHP process of its own, and every one exited 0.
The battery leaves the temp directory as it found it.
78 warnings, no errors.
```

- [ ] **Step 6: Commit**

```bash
git add includes/modules/class-wpcpm-mentor-application-queue.php includes/modules/class-wpcpm-mentors.php wpcredits-program-manager.php uninstall.php bin/test-mentor-application-queue.php bin/test-mentors-screen.php bin/test-accounts-table.php bin/test-submit-guard.php
git commit -m "Mentor applications: the Mentors screen's Waiting for review tab, its five views, its seven decisions and the applications recently decided" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 6: The lapse clock and retention

One daily run, put back on the clock on every load since a deploy never fires the activation hook: it lapses each selected application whose course has not shown after `mentor_lapse_days` (never fewer than fourteen), with a dated note by the site, then deletes the rows the retention settings say have been kept long enough, the sponsor application's pattern per state (spec, sections 6 and 9). What a deleted row counted for the feed is kept by month, so Task 7's months never change as rows go, and a line of each deletion is logged. A withdrawal (Task 5) leaves the row `lapsed`, since the nine states have no `withdrawn`, so the feed's lapsed figure counts withdrawals with the clock's lapses (the author's call, see "After the plan"); a spam row counts for nothing, so an application closed with Mark as spam because its address was never confirmed leaves the received figure too.

| State | Deleted after | Setting |
| --- | --- | --- |
| `spam` | 30 days by default | `application_spam_days` |
| `declined`, `lapsed`, `held` | a year by default | `application_rejected_days` |
| `active` (M2's) | never by default | `application_approved_days` (0 keeps) |
| `new`, `info`, `second`, `selected` | never: they are open, or waiting for the course | |

**Files:**
- Modify: `includes/modules/class-wpcpm-mentor-application.php` (the daily run, the lapse, the purge, the kept counts, the log, `delete_all()`)
- Modify: `includes/modules/class-wpcpm-mentors.php` (`deactivate()`, `uninstall()`)
- Modify: `includes/class-wpcpm-settings-screen.php` (the Advanced tab's Applications section names the third form)
- Modify: `docs/sections/31-admin-settings.md` (`#### Keeping applications`)
- Generated: `docs/administrators.md`, `docs/build/administrators.html`
- Test: `bin/test-mentor-application.php`, `bin/test-settings.php`, `bin/test-uninstall.php`

**Interfaces:**
- Consumes: Task 1's record; Task 2's `mentor_lapse_days`; the settings `application_spam_days`, `application_rejected_days`, `application_approved_days`.
- Produces, on `WPCPM_Mentor_Application`: `CRON_DAILY = 'wpcpm_mentor_applications_daily'`, `OPT_LOG = 'wpcpm_mentor_application_log'`, `LOG_MAX = 200`, `OPT_KEPT = 'wpcpm_mentor_application_counts'`, `LAPSE_FLOOR = 14`; `init()` now also hooks `schedule()` on `init` and `daily()` on the run; `schedule(): void`, `unschedule(): void`, `daily(): void` (lapse, then purge); `lapse( int $now = 0 ): int`; `purge( int $now = 0 ): int`; `forget( WP_Post $post, int $days, int $actor ): bool`; `month_counts_of( WP_Post $post ): array` (`Y-m` => `received`, `selected`, `activated`, `declined`, `lapsed`; a spam row counts nothing, a withdrawal as lapsed); `kept_counts(): array`; `deletion_log(): array[]`; `delete_all(): void`.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-mentor-application.php b/bin/test-mentor-application.php
--- a/bin/test-mentor-application.php
+++ b/bin/test-mentor-application.php
@@ -17,6 +17,12 @@
  *   signature, and the signature changes with the address's hash. Confirming stamps once.
  * - **The lists are oldest first, by ID within one second**, and a state that is not one reads no
  *   rows rather than every row.
+ * - **The daily run** lapses a row selected longer ago than the days set (two weeks at least), with
+ *   a dated note by the site, then deletes what retention says to: spam after its days, declined,
+ *   lapsed and held rows after the rejected days, never an open, selected or active row while
+ *   approved ones are kept for ever (the sponsor application's three settings, reused).
+ * - **History survives retention**: what a row counted for the feed, by month on the site's clock,
+ *   is kept when the row is deleted, and a spam row counts for nothing.
  *
  * Run from the plugin root:  php bin/test-mentor-application.php
  */
@@ -47,6 +53,7 @@ function ck( $label, $got, $want ) {
 
 /** A clean site. */
 function fresh() {
+	$GLOBALS['opts']         = array();
 	$GLOBALS['posts']        = array();
 	$GLOBALS['pmeta']        = array();
 	$GLOBALS['hooks']        = array();
@@ -72,7 +79,7 @@ echo "=== The post type ===\n";
 
 fresh();
 WPCPM_Mentor_Application::init();
-ck( 'init hooks the post type on init', isset( $GLOBALS['hooks']['init'] ) ? $GLOBALS['hooks']['init'] : null, array( array( 'WPCPM_Mentor_Application', 'register_post_type' ) ) );
+ck( 'init hooks the post type, and the daily run\'s place on the clock, on init', isset( $GLOBALS['hooks']['init'] ) ? $GLOBALS['hooks']['init'] : null, array( array( 'WPCPM_Mentor_Application', 'register_post_type' ), array( 'WPCPM_Mentor_Application', 'schedule' ) ) );
 
 WPCPM_Mentor_Application::register_post_type();
 $type = isset( $GLOBALS['post_types']['wpcpm_ment_app'] ) ? $GLOBALS['post_types']['wpcpm_ment_app'] : array();
@@ -171,6 +178,121 @@ ck( 'the open ones, oldest first and by ID within one second', array_map( functi
 ck( 'with a ceiling', array_map( function ( $p ) { return $p->ID; }, WPCPM_Mentor_Application::applications( WPCPM_Mentor_Application::open_states(), 2 ) ), array( $older, $first ) );
 ck( 'counted by state, and waiting means the four open states', array( WPCPM_Mentor_Application::count_in( array( 'selected' ) ), WPCPM_Mentor_Application::count_in( array( 'spam', 'selected' ) ), WPCPM_Mentor_Application::waiting_count() ), array( 1, 2, 3 ) );
 ck( 'and a state that is not one reads no rows, never every row', array( WPCPM_Mentor_Application::applications( array( 'approved' ) ), WPCPM_Mentor_Application::count_in( array() ) ), array( array(), 0 ) );
+
+echo "\n=== The daily run: the lapse clock, then retention ===\n";
+
+$GLOBALS['settings'] = array();
+
+/** The settings the daily run reads, as a check sets them. */
+class WPCPM_Settings {
+	public static function get_value( $key, $fallback = null ) {
+		return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
+	}
+}
+
+/** One application in a state, its last event that many days before the clock. */
+function aged( $state, $days_ago, $event = '' ) {
+	$id = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
+	$GLOBALS['pmeta'][ $id ][ WPCPM_Mentor_Application::META_EVENT ][0]['at'] = $GLOBALS['now'] - ( $days_ago + 1 ) * DAY_IN_SECONDS;
+
+	if ( 'new' !== $state ) {
+		WPCPM_Mentor_Application::decide( $id, $state, '' !== $event ? $event : $state, 7, '', $GLOBALS['now'] - $days_ago * DAY_IN_SECONDS );
+	}
+
+	return $id;
+}
+
+fresh();
+$GLOBALS['hooks'] = array();
+$GLOBALS['cron']  = array();
+WPCPM_Mentor_Application::init();
+WPCPM_Mentor_Application::schedule();
+ck( 'the daily run is hooked and put on the clock once a day, on every load where it is missing', array( isset( $GLOBALS['hooks']['wpcpm_mentor_applications_daily'] ), $GLOBALS['cron']['wpcpm_mentor_applications_daily']['every'] ?? null, in_array( array( 'WPCPM_Mentor_Application', 'schedule' ), $GLOBALS['hooks']['init'], true ) ), array( true, 'daily', true ) );
+
+fresh();
+$GLOBALS['settings'] = array( 'mentor_lapse_days' => 90 );
+$young               = aged( 'selected', 89, 'selected' );
+$old                 = aged( 'selected', 91, 'selected' );
+$open                = aged( 'info', 400, 'information requested' );
+ck( 'the clock lapses a row selected longer ago than the days set, and only that one', array( WPCPM_Mentor_Application::lapse( $GLOBALS['now'] ), WPCPM_Mentor_Application::state_of( get_post( $young ) ), WPCPM_Mentor_Application::state_of( get_post( $old ) ), WPCPM_Mentor_Application::state_of( get_post( $open ) ) ), array( 1, 'selected', 'lapsed', 'info' ) );
+$lapsed_row = WPCPM_Mentor_Application::events( $old );
+$lapsed_row = end( $lapsed_row );
+ck( 'with a dated note in the history, written by the site', array( $lapsed_row['event'], $lapsed_row['actor'], $lapsed_row['note'] ), array( 'lapsed', 0, 'Selected on ' . wp_date( 'Y-m-d', $GLOBALS['now'] - 91 * DAY_IN_SECONDS ) . ', and the mentors\' course had not shown 90 days later.' ) );
+WPCPM_Mentor_Application::decide( $old, 'selected', 'selected', 7, '', $GLOBALS['now'] );
+ck( 'a row selected again starts its days again', array( WPCPM_Mentor_Application::lapse( $GLOBALS['now'] ), WPCPM_Mentor_Application::state_of( get_post( $old ) ) ), array( 0, 'selected' ) );
+fresh();
+$GLOBALS['settings'] = array( 'mentor_lapse_days' => 3 );
+$recent              = aged( 'selected', 10, 'selected' );
+ck( 'and a setting below two weeks is read as two weeks: a row selected ten days ago stays', array( WPCPM_Mentor_Application::lapse( $GLOBALS['now'] ), WPCPM_Mentor_Application::state_of( get_post( $recent ) ) ), array( 0, 'selected' ) );
+
+fresh();
+$GLOBALS['settings'] = array(
+	'application_spam_days'     => 30,
+	'application_rejected_days' => 365,
+	'application_approved_days' => 0,
+);
+$kept    = array(
+	'new'      => aged( 'new', 4000 ),
+	'info'     => aged( 'info', 4000, 'information requested' ),
+	'second'   => aged( 'second', 4000, 'second opinion' ),
+	'selected' => aged( 'selected', 80, 'selected' ),
+	'active'   => aged( 'active', 4000, 'activated' ),
+	'spam'     => aged( 'spam', 29, 'marked as spam' ),
+	'declined' => aged( 'declined', 364, 'declined' ),
+);
+$gone    = array(
+	'spam'     => aged( 'spam', 31, 'marked as spam' ),
+	'declined' => aged( 'declined', 366, 'declined' ),
+	'lapsed'   => aged( 'lapsed', 366, 'lapsed' ),
+	'held'     => aged( 'held', 366, 'submitted' ),
+);
+ck( 'retention deletes spam after its days, and declined, lapsed and held rows after the rejected days', WPCPM_Mentor_Application::purge( $GLOBALS['now'] ), 4 );
+ck( 'and keeps every open row, a selected one, an active one while approved ones are kept for ever, and the rest inside their days', array( array_map( function ( $id ) { return null !== get_post( $id ); }, $kept ), array_map( function ( $id ) { return null !== get_post( $id ); }, $gone ) ), array( array_fill_keys( array_keys( $kept ), true ), array_fill_keys( array_keys( $gone ), false ) ) );
+$log = WPCPM_Mentor_Application::deletion_log();
+ck( 'each deletion leaves a reference, a state, the days and a time, and nothing a person wrote', array( count( $log ), array_keys( $log[0] ), $log[0]['state'], $log[0]['days'] ), array( 4, array( 'at', 'id', 'reference', 'state', 'days', 'actor' ), 'spam', 30 ) );
+
+echo "\n=== History survives retention ===\n";
+
+fresh();
+$GLOBALS['site_offset'] = 2 * HOUR_IN_SECONDS;
+$GLOBALS['now']         = gmmktime( 9, 0, 0, 1, 10, 2027 );
+$row                    = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
+WPCPM_Mentor_Application::add_event( $row, 'selected', 7, '', gmmktime( 23, 30, 0, 1, 31, 2027 ) );
+WPCPM_Mentor_Application::add_event( $row, 'withdrawn', 7, '', gmmktime( 10, 0, 0, 3, 2, 2027 ) );
+WPCPM_Mentor_Application::decide( $row, 'lapsed', 'lapsed', 0, '', gmmktime( 10, 0, 0, 4, 2, 2027 ) );
+$spam = WPCPM_Mentor_Application::store( answers(), 'spam', array(), array() );
+$counts = WPCPM_Mentor_Application::month_counts_of( get_post( $row ) );
+ck(
+	'a row counts once where it arrived, and each selection, decline, withdrawal and lapse in its month on the site\'s clock: 23:30 UTC on January 31 is February there',
+	$counts,
+	array(
+		'2027-01' => array( 'received' => 1, 'selected' => 0, 'activated' => 0, 'declined' => 0, 'lapsed' => 0 ),
+		'2027-02' => array( 'received' => 0, 'selected' => 1, 'activated' => 0, 'declined' => 0, 'lapsed' => 0 ),
+		'2027-03' => array( 'received' => 0, 'selected' => 0, 'activated' => 0, 'declined' => 0, 'lapsed' => 1 ),
+		'2027-04' => array( 'received' => 0, 'selected' => 0, 'activated' => 0, 'declined' => 0, 'lapsed' => 1 ),
+	)
+);
+ck( 'and a spam row counts for nothing', WPCPM_Mentor_Application::month_counts_of( get_post( $spam ) ), array() );
+WPCPM_Mentor_Application::forget( get_post( $row ), 365, 0 );
+WPCPM_Mentor_Application::forget( get_post( $spam ), 30, 0 );
+ck( 'deleting a row keeps what it counted, by month, and a spam row adds nothing', WPCPM_Mentor_Application::kept_counts(), $counts );
+$again = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
+$GLOBALS['now'] = gmmktime( 9, 0, 0, 2, 10, 2027 );
+$later = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
+WPCPM_Mentor_Application::forget( get_post( $again ), 365, 0 );
+WPCPM_Mentor_Application::forget( get_post( $later ), 365, 0 );
+ck( 'and a second deletion adds to the same months', array( WPCPM_Mentor_Application::kept_counts()['2027-01']['received'], WPCPM_Mentor_Application::kept_counts()['2027-02']['received'], WPCPM_Mentor_Application::kept_counts()['2027-02']['selected'] ), array( 2, 1, 1 ) );
+
+echo "\n=== Uninstall ===\n";
+
+fresh();
+$GLOBALS['cron'] = array( 'wpcpm_mentor_applications_daily' => array( 'at' => 1, 'every' => 'daily' ) );
+WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
+update_option( WPCPM_Mentor_Application::OPT_LOG, array( array( 'id' => 1 ) ) );
+update_option( WPCPM_Mentor_Application::OPT_KEPT, array( '2027-01' => array() ) );
+WPCPM_Mentor_Application::delete_all();
+ck( 'deleting everything removes every row, the log, the kept counts and the daily run', array( count( $GLOBALS['posts'] ), get_option( WPCPM_Mentor_Application::OPT_LOG ), get_option( WPCPM_Mentor_Application::OPT_KEPT ), isset( $GLOBALS['cron']['wpcpm_mentor_applications_daily'] ) ), array( 0, false, false, false ) );
+
 ck( 'nothing was asked of WordPress that the stand-ins do not model', isset( $GLOBALS['unmodeled'] ) ? $GLOBALS['unmodeled'] : array(), array() );
 
 printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );
diff --git a/bin/test-settings.php b/bin/test-settings.php
--- a/bin/test-settings.php
+++ b/bin/test-settings.php
@@ -3404,7 +3404,7 @@ ck( 'the starting stage\'s help says the stage cannot be left blank, and the rej
     array( help_of( $states['advanced'], 'Institution starting stage' )[0], help_of( $states['advanced'], 'Rejected kept for (days)' )[0] ),
     array(
         array( 'The Current Stage an institution is created at in Airtable when its application is approved; the stage cannot be left blank.' ),
-        array( 'A rejected application, or one the checks held, is deleted this many days after the decision: long enough to recognize the same applicant applying again.' ),
+        array( 'A rejected, declined or lapsed application, or one the checks held, is deleted this many days after the decision: long enough to recognize the applicant again.' ),
     ) );
 
 // The Token scopes help sends the manager to the fold by the word the fold's summary shows, one string
diff --git a/bin/test-uninstall.php b/bin/test-uninstall.php
--- a/bin/test-uninstall.php
+++ b/bin/test-uninstall.php
@@ -640,6 +640,9 @@ $named_options = array(
 	'wpcpm_tracks_upgrade_lock',           // WPCPM_Track_Store::OPT_UPGRADE_LOCK, through delete_all().
 	'wpcpm_track_fields_growth',           // The form of the track post below.
 	'wpcpm_track_fields_listed',           // The form of a track the index lists and no post holds.
+	'wpcpm_mentor_application_log',        // WPCPM_Mentor_Application::OPT_LOG, through delete_all().
+	'wpcpm_mentor_application_counts',     // WPCPM_Mentor_Application::OPT_KEPT, through delete_all().
+	'wpcpm_mentor_application_page_id',    // WPCPM_Mentor_Application_Form::OPT_PAGE.
 );
 
 foreach ( $named_options as $name ) {
@@ -693,7 +696,7 @@ set_transient( 'feed_example', 'kept', HOUR_IN_SECONDS );
 
 // One post of every type the clean-up removes, private as the modules keep them; an offer is found
 // through its sponsor, and every offer has one.
-$plugin_types = array( 'wpcpm_notice', 'wpcpm_dup_copy', 'wpcpm_track', 'wpcpm_inst_report', 'wpcpm_inst_request', 'wpcpm_inst_app', 'wpcpm_agreement', 'wpcpm_sponsor_app', 'wpcpm_audit_entry', 'wpcpm_import_batch', 'wpcpm_offer', 'wpcpm_mentor_call', 'wpcpm_mentor_note', 'wpcpm_inst_invite', 'wpcpm_sponsor_agr', 'wpcpm_handbook' );
+$plugin_types = array( 'wpcpm_notice', 'wpcpm_dup_copy', 'wpcpm_track', 'wpcpm_inst_report', 'wpcpm_inst_request', 'wpcpm_inst_app', 'wpcpm_agreement', 'wpcpm_sponsor_app', 'wpcpm_audit_entry', 'wpcpm_import_batch', 'wpcpm_offer', 'wpcpm_mentor_call', 'wpcpm_mentor_note', 'wpcpm_inst_invite', 'wpcpm_sponsor_agr', 'wpcpm_handbook', 'wpcpm_ment_app' );
 
 foreach ( $plugin_types as $type ) {
 	a_post( $type, 'private', 'wpcpm_offer' === $type ? array( '_wpcpm_offer_sponsor' => 'recSEED0000000003' ) : array() );
@@ -765,7 +768,7 @@ $sponsor = a_user( array( 'wpcpm_sponsor' ), array( 'edit_posts', 'delete_posts'
 // The cron schedule: the twelve hooks the file clears itself, every hook the modules and the tools
 // clear, and one of core's.
 $file_hooks  = array( 'wpcpm_mentors_daily', 'wpcpm_mentors_sync_tick', 'wpcpm_students_daily', 'wpcpm_students_sync_tick', 'wpcpm_send_call_reminders', 'wpcpm_drain_invite_queue', 'wpcpm_institutions_sync_daily', 'wpcpm_institutions_sync_tick', 'wpcpm_handbook_sync_daily', 'wpcpm_handbook_sync_tick', 'wpcpm_outcome_figures_weekly', 'wpcpm_outcome_figures_retry' );
-$other_hooks = array( 'wpcpm_ceiling_sweep', 'wpcpm_purge_applications', 'wpcpm_agreement_discard', 'wpcpm_agreement_reminders', 'wpcpm_invite_expire', 'wpcpm_report_ask_queue', 'wpcpm_report_autodraft', 'wpcpm_sponsor_agreement_discard', 'wpcpm_purge_sponsor_applications', 'wpcpm_sponsors_daily', 'wpcpm_sponsors_sync_tick', 'wpcpm_checker_weekly_check', 'wpcpm_checker_slack_retry', 'wpcpm_duplicates_scan', 'wpcpm_duplicates_tick', 'wpcpm_duplicates_purge', 'wpcpm_mail_log_purge' );
+$other_hooks = array( 'wpcpm_ceiling_sweep', 'wpcpm_purge_applications', 'wpcpm_agreement_discard', 'wpcpm_agreement_reminders', 'wpcpm_invite_expire', 'wpcpm_report_ask_queue', 'wpcpm_report_autodraft', 'wpcpm_sponsor_agreement_discard', 'wpcpm_purge_sponsor_applications', 'wpcpm_sponsors_daily', 'wpcpm_sponsors_sync_tick', 'wpcpm_checker_weekly_check', 'wpcpm_checker_slack_retry', 'wpcpm_duplicates_scan', 'wpcpm_duplicates_tick', 'wpcpm_duplicates_purge', 'wpcpm_mail_log_purge', 'wpcpm_mentor_applications_daily' );
 
 foreach ( array_merge( $file_hooks, $other_hooks, array( 'wp_version_check' ) ) as $hook ) {
 	$GLOBALS['cron'][] = array(
@@ -991,6 +994,11 @@ $names = array(
 	'WPCPM_Duplicates_Scan::CRON_SCAN'              => 'wpcpm_duplicates_scan',
 	'WPCPM_Duplicates_Scan::CRON_TICK'              => 'wpcpm_duplicates_tick',
 	'WPCPM_Duplicate_Vault::CRON_PURGE'             => 'wpcpm_duplicates_purge',
+	'WPCPM_Mentor_Application::POST_TYPE'           => 'wpcpm_ment_app',
+	'WPCPM_Mentor_Application::OPT_LOG'             => 'wpcpm_mentor_application_log',
+	'WPCPM_Mentor_Application::OPT_KEPT'            => 'wpcpm_mentor_application_counts',
+	'WPCPM_Mentor_Application::CRON_DAILY'          => 'wpcpm_mentor_applications_daily',
+	'WPCPM_Mentor_Application_Form::OPT_PAGE'       => 'wpcpm_mentor_application_page_id',
 );
 
 ck( 'the names seeded above are the ones the classes declare', array_map( 'declared', array_keys( $names ) ), array_values( $names ) );
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php bin/test-mentor-application.php`, `php bin/test-settings.php` and `php bin/test-uninstall.php`

```text
$ php bin/test-mentor-application.php
FAIL init hooks the post type, and the daily run's place on the clock, on init
PHP Fatal error:  Uncaught Error: Call to undefined method WPCPM_Mentor_Application::schedule() in <plugin>/bin/test-mentor-application.php:209
[exit 255]
$ php bin/test-settings.php
FAIL the starting stage's help says the stage cannot be left blank, and the rejected applications' help from when the days are counted
1 FAILURE(S)
[exit 1]
$ php bin/test-uninstall.php
FAIL every option the file names goes, the Track Builder's included
FAIL no option of the plugin's is left but that key
FAIL every post of the plugin's goes, a trashed track, a trashed report, a canceled call and a legacy Handbook page included
FAIL and so is every hook the modules and the tools clear
FAIL a hook of core's is left alone
FAIL the names seeded above are the ones the classes declare
6 FAILED (36 checks)
[exit 1]
```

- [ ] **Step 3: The run, the clean-up, the words and the guide**

```diff
diff --git a/docs/sections/31-admin-settings.md b/docs/sections/31-admin-settings.md
--- a/docs/sections/31-admin-settings.md
+++ b/docs/sections/31-admin-settings.md
@@ -361,21 +361,27 @@ so the limits given below are the field's own.
 
 #### Keeping applications
 
-How long a decided application is kept, institution and sponsor applications alike, and the one
-proxy the two application forms believe.
+How long a decided application is kept, institution, sponsor and mentor applications alike, and the
+one proxy the three application forms believe.
 
 - **Spam kept for (days)** - from 1 to 365, 30 by default: an application marked as spam is deleted
   this many days after it was marked.
-- **Rejected kept for (days)** - from 30 to 3650, a year by default: a rejected application, or one
-  the checks held, is deleted this many days after the decision, which is long enough to recognize
-  the same applicant applying again.
-- **Approved kept for (days)** - from 0 to 3650, 0 by default, which keeps approved applications
-  forever: they are the record of who was let in.
+- **Rejected kept for (days)** - from 30 to 3650, a year by default: a rejected or declined
+  application, a mentor application that lapsed, or one the checks held, is deleted this many days
+  after the decision, which is long enough to recognize the same applicant applying again.
+- **Approved kept for (days)** - from 0 to 3650, 0 by default, which keeps approved applications,
+  and the mentor applications of those who became mentors, forever: they are the record of who was
+  let in.
 - **Trusted proxy** - the one IP address, not a range, of the proxy whose forwarded header the
   application forms believe for a sender's address, which their limits count by. Its fold: leave it
   empty unless the site sits behind a proxy, since anybody can send that header; anything but one
   address, a range included, is saved as empty.
 
+The daily run that keeps these days for the mentor applications also lapses a selected application
+whose course has not shown after the days set under **Mentor applications** on the Students and
+mentors tab, and what every deleted mentor application counted for the public feed of application
+figures is kept, so the feed's months do not change as rows go.
+
 #### Agreement files
 
 - **Largest upload (MB)** - from 1 to 50, 10 by default: the largest signed agreement an institution
diff --git a/includes/class-wpcpm-settings-screen.php b/includes/class-wpcpm-settings-screen.php
--- a/includes/class-wpcpm-settings-screen.php
+++ b/includes/class-wpcpm-settings-screen.php
@@ -1783,13 +1783,13 @@ class WPCPM_Settings_Screen {
 
 		$this->open_section(
 			__( 'Applications', 'wpcredits-program-manager' ),
-			__( 'How long a decided institution or sponsor application is kept, and the one proxy the two application forms believe about a sender\'s address.', 'wpcredits-program-manager' ),
+			__( 'How long a decided application from any of the three forms is kept, and the one proxy the forms believe about a sender\'s address.', 'wpcredits-program-manager' ),
 			'keeping-applications'
 		);
 
 		WPCPM_Settings_Rows::number_row( 'application_spam_days', __( 'Spam kept for (days)', 'wpcredits-program-manager' ), $settings['application_spam_days'], 1, 365, __( 'An application marked as spam is deleted this many days after it was marked.', 'wpcredits-program-manager' ) );
-		WPCPM_Settings_Rows::number_row( 'application_rejected_days', __( 'Rejected kept for (days)', 'wpcredits-program-manager' ), $settings['application_rejected_days'], 30, 3650, __( 'A rejected application, or one the checks held, is deleted this many days after the decision: long enough to recognize the same applicant applying again.', 'wpcredits-program-manager' ) );
-		WPCPM_Settings_Rows::number_row( 'application_approved_days', __( 'Approved kept for (days)', 'wpcredits-program-manager' ), $settings['application_approved_days'], 0, 3650, __( '0 keeps approved applications forever: they are the record of who was let in.', 'wpcredits-program-manager' ) );
+		WPCPM_Settings_Rows::number_row( 'application_rejected_days', __( 'Rejected kept for (days)', 'wpcredits-program-manager' ), $settings['application_rejected_days'], 30, 3650, __( 'A rejected, declined or lapsed application, or one the checks held, is deleted this many days after the decision: long enough to recognize the applicant again.', 'wpcredits-program-manager' ) );
+		WPCPM_Settings_Rows::number_row( 'application_approved_days', __( 'Approved kept for (days)', 'wpcredits-program-manager' ), $settings['application_approved_days'], 0, 3650, __( '0 keeps approved applications, and the mentor applications of those who became mentors, forever: they are the record of who was let in.', 'wpcredits-program-manager' ) );
 		// One address, not a range: the save keeps one valid address or none, and the forms compare it
 		// with the connecting address as one string (`WPCPM_Form_Guard::client_ip()`).
 		WPCPM_Settings_Rows::text_row(
diff --git a/includes/modules/class-wpcpm-mentor-application.php b/includes/modules/class-wpcpm-mentor-application.php
--- a/includes/modules/class-wpcpm-mentor-application.php
+++ b/includes/modules/class-wpcpm-mentor-application.php
@@ -28,6 +28,12 @@ if ( ! defined( 'ABSPATH' ) ) {
  * acknowledgement then. The queue is the four open states; `selected` waits for the course; the
  * rest are closed.
  *
+ * **The daily run** (`daily()`) moves a selected row the course never reached to `lapsed`, then
+ * deletes what the retention settings say to, the three the other two application forms keep
+ * (design, section 9): spam after its days, declined, lapsed and held rows after the rejected days,
+ * an active row only if approved ones are ever set to go. What a row counted for the feed is kept
+ * when it goes (`forget()`), so the months behind the feed do not shrink as rows are deleted.
+ *
  * Nothing here sends anything or draws anything: the form, the queue and the feed read and write
  * through this class.
  */
@@ -92,11 +98,57 @@ final class WPCPM_Mentor_Application {
 	/** Put back in the queue and sent the acknowledgement it never had, with the confirming link. */
 	const EVENT_REOPENED_ACKNOWLEDGED = 'put back in the queue, acknowledgement sent';
 
+	/** The daily run: the lapse clock, then retention. */
+	const CRON_DAILY = 'wpcpm_mentor_applications_daily';
+
+	/**
+	 * Option: what each deletion left behind, a reference, a state, the days and a time, and never a
+	 * word anybody wrote: a log that outlives the row must not become the copy retention removed.
+	 */
+	const OPT_LOG = 'wpcpm_mentor_application_log';
+
+	/** How many rows that log keeps. */
+	const LOG_MAX = 200;
+
+	/** Option: what deleted rows counted for the feed, by month (`month_counts_of()`). */
+	const OPT_KEPT = 'wpcpm_mentor_application_counts';
+
+	/** The fewest days a selected row has before it lapses, whatever the setting says. */
+	const LAPSE_FLOOR = 14;
+
 	/**
-	 * Hooks: the post type.
+	 * Hooks: the post type, the daily run, and its place on the clock, put back on every load where
+	 * it is missing, since a deploy replaces the files and never fires the activation hook.
 	 */
 	public static function init() {
 		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
+		add_action( 'init', array( __CLASS__, 'schedule' ), 20 );
+		add_action( self::CRON_DAILY, array( __CLASS__, 'daily' ) );
+	}
+
+	/**
+	 * Put the daily run on the clock if it is not there: ten hours past the first load, an hour clear
+	 * of the sponsor applications' retention run.
+	 */
+	public static function schedule() {
+		if ( ! wp_next_scheduled( self::CRON_DAILY ) ) {
+			wp_schedule_event( time() + 10 * HOUR_IN_SECONDS, 'daily', self::CRON_DAILY );
+		}
+	}
+
+	/**
+	 * Take the daily run off the clock.
+	 */
+	public static function unschedule() {
+		wp_clear_scheduled_hook( self::CRON_DAILY );
+	}
+
+	/**
+	 * The daily run: lapse first, so a row that lapses tonight starts its retention days tonight.
+	 */
+	public static function daily() {
+		self::lapse();
+		self::purge();
 	}
 
 	/**
@@ -577,4 +629,211 @@ final class WPCPM_Mentor_Application {
 			)
 		);
 	}
+
+	/*
+	 * The lapse clock and retention
+	 * --------------------------------------------------------------------
+	 */
+
+	/**
+	 * Move every selected row whose last selection is older than the days set to `lapsed`, with a
+	 * dated note by the site (design, section 6). Two weeks at least, whatever the setting holds.
+	 *
+	 * @param int $now A Unix time, or 0 for now.
+	 * @return int How many lapsed.
+	 */
+	public static function lapse( $now = 0 ) {
+		$now    = $now > 0 ? (int) $now : time();
+		$days   = max( self::LAPSE_FLOOR, (int) WPCPM_Settings::get_value( 'mentor_lapse_days', 90 ) );
+		$lapsed = 0;
+
+		foreach ( self::applications( array( self::STATE_SELECTED ) ) as $post ) {
+			$selected = self::last_at( (int) $post->ID, self::EVENT_SELECTED );
+			$selected = $selected > 0 ? $selected : (int) get_post_time( 'U', true, $post );
+
+			if ( $selected > $now - $days * DAY_IN_SECONDS ) {
+				continue;
+			}
+
+			$note = sprintf(
+				/* translators: 1: the day the applicant was selected, 2: how many days the course had. */
+				__( 'Selected on %1$s, and the mentors\' course had not shown %2$s days later.', 'wpcredits-program-manager' ),
+				wp_date( 'Y-m-d', $selected ),
+				number_format_i18n( $days )
+			);
+
+			if ( self::decide( (int) $post->ID, self::STATE_LAPSED, self::EVENT_LAPSED, 0, $note, $now ) ) {
+				++$lapsed;
+			}
+		}
+
+		return $lapsed;
+	}
+
+	/**
+	 * Delete the rows the retention settings say have been kept long enough: the three settings the
+	 * institution and sponsor applications keep, each in days, 0 meaning never. The clock runs from
+	 * the last thing that happened to a row, so a decision restarts it.
+	 *
+	 * @param int $now A Unix time, or 0 for now.
+	 * @return int How many were deleted.
+	 */
+	public static function purge( $now = 0 ) {
+		$now       = $now > 0 ? (int) $now : time();
+		$retention = array(
+			self::STATE_SPAM     => 'application_spam_days',
+			self::STATE_DECLINED => 'application_rejected_days',
+			self::STATE_LAPSED   => 'application_rejected_days',
+			self::STATE_HELD     => 'application_rejected_days',
+			self::STATE_ACTIVE   => 'application_approved_days',
+		);
+		$purged    = 0;
+
+		foreach ( $retention as $state => $setting ) {
+			$days = (int) WPCPM_Settings::get_value( $setting, 0 );
+
+			if ( $days < 1 ) {
+				continue;
+			}
+
+			foreach ( self::applications( array( $state ) ) as $post ) {
+				$last = self::last_at( (int) $post->ID );
+				$last = $last > 0 ? $last : (int) get_post_time( 'U', true, $post );
+
+				if ( $last <= $now - $days * DAY_IN_SECONDS && self::forget( $post, $days, 0 ) ) {
+					++$purged;
+				}
+			}
+		}
+
+		return $purged;
+	}
+
+	/**
+	 * Delete one row for good, keeping what it counted for the feed and a line in the log.
+	 *
+	 * @param WP_Post $post  The row.
+	 * @param int     $days  The retention setting that removed it, or 0 for a person's hand.
+	 * @param int     $actor Who did it; 0 for the daily run.
+	 * @return bool Whether the row is gone.
+	 */
+	public static function forget( WP_Post $post, $days, $actor ) {
+		$counts    = self::month_counts_of( $post );
+		$state     = self::state_of( $post );
+		$reference = self::reference_of( $post );
+		$post_id   = (int) $post->ID;
+
+		if ( ! wp_delete_post( $post_id, true ) ) {
+			return false;
+		}
+
+		$kept = self::kept_counts();
+
+		foreach ( $counts as $month => $row ) {
+			foreach ( $row as $key => $count ) {
+				$kept[ $month ][ $key ] = ( isset( $kept[ $month ][ $key ] ) ? (int) $kept[ $month ][ $key ] : 0 ) + (int) $count;
+			}
+		}
+
+		ksort( $kept );
+		update_option( self::OPT_KEPT, $kept, false );
+
+		$log   = self::deletion_log();
+		$log[] = array(
+			'at'        => time(),
+			'id'        => $post_id,
+			'reference' => sanitize_text_field( $reference ),
+			'state'     => sanitize_key( $state ),
+			'days'      => (int) $days,
+			'actor'     => (int) $actor,
+		);
+
+		update_option( self::OPT_LOG, array_slice( $log, -self::LOG_MAX ), false );
+
+		return true;
+	}
+
+	/**
+	 * What one row counts for the feed, by month on the site's clock: received where it arrived,
+	 * unless it is spam; then each selection, activation and decline in its month, and a lapse or a
+	 * withdrawal as lapsed. A spam row counts for nothing, as the sponsors feed counts none.
+	 *
+	 * @param WP_Post $post The row.
+	 * @return array<string, array{received: int, selected: int, activated: int, declined: int, lapsed: int}>
+	 */
+	public static function month_counts_of( WP_Post $post ) {
+		if ( self::STATE_SPAM === self::state_of( $post ) ) {
+			return array();
+		}
+
+		$counted = array(
+			self::EVENT_SELECTED  => 'selected',
+			self::EVENT_ACTIVATED => 'activated',
+			self::EVENT_DECLINED  => 'declined',
+			self::EVENT_LAPSED    => 'lapsed',
+			self::EVENT_WITHDRAWN => 'lapsed',
+		);
+		$out     = array();
+		$add     = static function ( $month, $key ) use ( &$out ) {
+			if ( ! isset( $out[ $month ] ) ) {
+				$out[ $month ] = array_fill_keys( array( 'received', 'selected', 'activated', 'declined', 'lapsed' ), 0 );
+			}
+
+			++$out[ $month ][ $key ];
+		};
+
+		$add( substr( (string) $post->post_date, 0, 7 ), 'received' );
+
+		foreach ( self::events( (int) $post->ID ) as $row ) {
+			if ( isset( $counted[ $row['event'] ] ) ) {
+				$add( wp_date( 'Y-m', $row['at'] ), $counted[ $row['event'] ] );
+			}
+		}
+
+		ksort( $out );
+
+		return $out;
+	}
+
+	/**
+	 * What deleted rows counted for the feed, by month.
+	 *
+	 * @return array
+	 */
+	public static function kept_counts() {
+		$kept = get_option( self::OPT_KEPT, array() );
+
+		return is_array( $kept ) ? $kept : array();
+	}
+
+	/**
+	 * The deletions retention and people have made, oldest first.
+	 *
+	 * @return array[]
+	 */
+	public static function deletion_log() {
+		$log = get_option( self::OPT_LOG, array() );
+
+		return is_array( $log ) ? array_values( $log ) : array();
+	}
+
+	/**
+	 * Delete every row, the log, the kept counts and the daily run. Called on uninstall.
+	 */
+	public static function delete_all() {
+		foreach ( (array) get_posts(
+			array(
+				'post_type'   => self::POST_TYPE,
+				'post_status' => 'any',
+				'numberposts' => -1,
+				'fields'      => 'ids',
+			)
+		) as $post_id ) {
+			wp_delete_post( (int) $post_id, true );
+		}
+
+		delete_option( self::OPT_LOG );
+		delete_option( self::OPT_KEPT );
+		self::unschedule();
+	}
 }
diff --git a/includes/modules/class-wpcpm-mentors.php b/includes/modules/class-wpcpm-mentors.php
--- a/includes/modules/class-wpcpm-mentors.php
+++ b/includes/modules/class-wpcpm-mentors.php
@@ -169,6 +169,7 @@ class WPCPM_Mentors extends WPCPM_Sync_Module {
 	public function deactivate() {
 		WPCPM_Mentors_Sync::unschedule();
 		WPCPM_Mentor_Calls::unschedule();
+		WPCPM_Mentor_Application::unschedule();
 	}
 
 	/**
@@ -179,6 +180,11 @@ class WPCPM_Mentors extends WPCPM_Sync_Module {
 	public function uninstall() {
 		WPCPM_Mentor_Notes::delete_all();
 		WPCPM_Mentor_Calls::delete_all();
+		// The applications to mentor (1.122.31): every row, the deletion log, what deleted rows counted
+		// for the feed and the daily run, and the form's page ID. The page itself stays, as every page
+		// the plugin creates does: a page is site content.
+		WPCPM_Mentor_Application::delete_all();
+		delete_option( WPCPM_Mentor_Application_Form::OPT_PAGE );
 		// Prefix-named, so it cannot be swept by listing keys: booking locks left by
 		// requests that died holding one, and the per-student resolved-mentor cache.
 		WPCPM_Mentor_Calls::flush_cache();
```

Then build the guide: `php bin/build-docs.php`.

- [ ] **Step 4: Run them to verify they pass**

Run: the three suites of step 2.

```text
$ php bin/test-mentor-application.php
ALL PASS (43 checks)
[exit 0]
$ php bin/test-settings.php
ALL PASS
[exit 0]
$ php bin/test-uninstall.php
ALL PASS (36 checks)
[exit 0]
```

- [ ] **Step 5: The checks every task ends with**

```text
battery done
156 classes, 15057 references checked, 88 form handlers scanned - all resolve
159 PHP files, 8 blocks, 4 track seeds, 23 fixture and tooling files, the guides and the readme: US English throughout.
281 annotations, 0 dead.
Ran 135 scripts from bin/, each in a PHP process of its own, and every one exited 0.
The battery leaves the temp directory as it found it.
78 warnings, no errors.
```

- [ ] **Step 6: Commit**

```bash
git add includes/modules/class-wpcpm-mentor-application.php includes/modules/class-wpcpm-mentors.php includes/class-wpcpm-settings-screen.php docs/sections/31-admin-settings.md docs/administrators.md docs/build/administrators.html bin/test-mentor-application.php bin/test-settings.php bin/test-uninstall.php
git commit -m "Mentor applications: the daily run lapses a selected application the course never reached, then keeps the retention days, and what a deleted row counted is kept" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 7: The public feed of the counts

`GET /wp-json/wpcpm/v1/mentor-applications`, in the namespace and the shape of the sponsors feed (spec, section 3, decision 10): for each calendar month on the site's clock from the first application to this one, how many applications were received, selected, activated, declined and lapsed, and how many are open and how many selected and waiting for the course today. Counts only, never a field of a person; public, as both readers are anonymous scripts; metered per address and cached for fifteen minutes. The Credits Monthly Report and the WPCredits Tracker read it in releases of their own.

**Files:**
- Create: `includes/modules/class-wpcpm-mentor-application-feed.php`
- Modify: `includes/modules/class-wpcpm-mentors.php` (`boot()`, `uninstall()`), `wpcredits-program-manager.php`, `uninstall.php`
- Create: `bin/test-mentor-application-feed.php`
- Modify: `bin/test-mentors-screen.php`, `bin/test-uninstall.php`

**Interfaces:**
- Consumes: `WPCPM_Mentor_Application::applications()`, `states()`, `month_counts_of()`, `kept_counts()`, `waiting_count()`, `count_in()`, `STATE_SELECTED`; `WPCPM_Ceiling::claim( $key, $limit, $window )`; `WPCPM_Form_Guard::actor_key( $prefix )`.
- Produces: `final class WPCPM_Mentor_Application_Feed`: `ROUTE = '/mentor-applications'`, `TRANSIENT = 'wpcpm_mentor_applications_feed'`, `CEILING = 'mentor-applications-feed'`, `PER_HOUR = 60`, `CACHE_SECONDS = 900`; `init()`, `register_route()`, `rest_counts( WP_REST_Request $request ): WP_REST_Response` (200 with the answer and `Cache-Control: public, max-age=900`, or 429 with the code `wpcpm_feed_busy`), `build( int $now = 0 ): array` (`months`, an empty object while there are none; `today` with `open` and `selected`; `generated`, ISO 8601 in UTC).

- [ ] **Step 1: Write the failing suite**

Create `bin/test-mentor-application-feed.php`:

```php
<?php
/**
 * The public feed of mentor application counts (1.122.31): `WPCPM_Mentor_Application_Feed`.
 *
 * Design of 16 October 2026, section 3, decision 10. What is pinned here and why:
 *
 * - **The route** (`GET /wp-json/wpcpm/v1/mentor-applications`) is public and reads no argument.
 * - **The months**: every calendar month from the first application to this one, on the site's
 *   clock, each with received, selected, activated, declined and lapsed, a month with nothing a
 *   month of zeros; a spam row counts for nothing; a withdrawal counts as lapsed; what deleted rows
 *   counted is in its months still. With no application, the months are an empty object.
 * - **Today**: how many applications are open, and how many are selected and waiting for the course.
 * - **Counts, never people**: rows whose every answer, reference and note holds a sentinel, and none
 *   of it reaches the answer, whose keys are the three above and nothing else.
 * - **Cached and metered as the sponsors feed is**: fifteen minutes, read back without a rebuild; the
 *   sixty-first request in an hour from one address is refused with 429 before anything is read,
 *   another address is not; and the answer says how long a cache may keep it.
 *
 * Run from the plugin root:  php bin/test-mentor-application-feed.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/mentor-applications.php';

$GLOBALS['routes']   = array();
$GLOBALS['settings'] = array();

class WP_REST_Request {}

class WP_REST_Response {
	public $data, $status, $headers = array();
	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}
	public function header( $key, $value ) {
		$this->headers[ $key ] = $value;
	}
}

function register_rest_route( $namespace, $route, $args = array() ) {
	$GLOBALS['routes'][ $namespace ][ $route ] = $args;
	return true;
}

/** The one setting the address reading consults. */
class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) {
		return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-guard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-feed.php';

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

/** A clean site, its clock on 16 March 2027, two hours ahead of UTC. */
function fresh() {
	$GLOBALS['opts']        = array();
	$GLOBALS['transients']  = array();
	$GLOBALS['posts']       = array();
	$GLOBALS['pmeta']       = array();
	$GLOBALS['next_id']     = 500;
	$GLOBALS['site_offset'] = 2 * HOUR_IN_SECONDS;
	$GLOBALS['now']         = gmmktime( 12, 0, 0, 3, 16, 2027 );
	$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
}

/** A row that arrived at a moment, with what then happened to it, each `array( event, state, at )`. */
function a_row( $arrived, $state, array $events = array(), array $answers = array() ) {
	$GLOBALS['now'] = $arrived;
	$id             = WPCPM_Mentor_Application::store(
		array_merge(
			array(
				'name'    => 'Ada Okonjo',
				'profile' => 'ada-example',
				'email'   => 'ada@example.test',
			),
			$answers
		),
		in_array( $state, array( 'new', 'held', 'spam' ), true ) ? $state : 'new',
		array(),
		array()
	);

	foreach ( $events as $event ) {
		WPCPM_Mentor_Application::decide( $id, $event[1], $event[0], 7, isset( $event[3] ) ? $event[3] : '', $event[2] );
	}

	$GLOBALS['now'] = gmmktime( 12, 0, 0, 3, 16, 2027 );

	return $id;
}

/** One month's five counts. */
function month( $received, $selected = 0, $activated = 0, $declined = 0, $lapsed = 0 ) {
	return array(
		'received'  => $received,
		'selected'  => $selected,
		'activated' => $activated,
		'declined'  => $declined,
		'lapsed'    => $lapsed,
	);
}

echo "=== The route ===\n";

fresh();
WPCPM_Mentor_Application_Feed::register_route();
$route = isset( $GLOBALS['routes']['wpcpm/v1']['/mentor-applications'] ) ? $GLOBALS['routes']['wpcpm/v1']['/mentor-applications'] : array();
ck( 'the feed is GET /wp-json/wpcpm/v1/mentor-applications, public, reading no argument', array( $route['methods'] ?? null, $route['permission_callback'] ?? null, $route['callback'] ?? null, isset( $route['args'] ) ), array( 'GET', '__return_true', array( 'WPCPM_Mentor_Application_Feed', 'rest_counts' ), false ) );

echo "\n=== The months and today ===\n";

fresh();
$empty = WPCPM_Mentor_Application_Feed::build( $GLOBALS['now'] );
ck( 'with no application the months are an empty object, and today is two zeros', array( wp_json_encode( $empty['months'] ), $empty['today'] ), array( '{}', array( 'open' => 0, 'selected' => 0 ) ) );

fresh();
// November: one received and declined in December. December: one received, selected in January
// (on the site's clock: 23:30 UTC on December 31 is January there), withdrawn in February. February:
// one spam, and one received and still waiting. March: one selected and waiting for the course.
a_row( gmmktime( 9, 0, 0, 11, 20, 2026 ), 'declined', array( array( 'declined', 'declined', gmmktime( 9, 0, 0, 12, 3, 2026 ) ) ) );
a_row( gmmktime( 9, 0, 0, 12, 10, 2026 ), 'lapsed', array( array( 'selected', 'selected', gmmktime( 23, 30, 0, 12, 31, 2026 ) ), array( 'withdrawn', 'lapsed', gmmktime( 9, 0, 0, 2, 4, 2027 ) ) ) );
a_row( gmmktime( 9, 0, 0, 2, 11, 2027 ), 'spam' );
a_row( gmmktime( 9, 0, 0, 2, 12, 2027 ), 'held' );
a_row( gmmktime( 9, 0, 0, 3, 1, 2027 ), 'selected', array( array( 'selected', 'selected', gmmktime( 9, 0, 0, 3, 8, 2027 ) ) ) );
$built = WPCPM_Mentor_Application_Feed::build( $GLOBALS['now'] );
ck(
	'every month from the first application to this one, each with its five counts, an empty month zeros, a spam row counted nowhere, a withdrawal as lapsed',
	$built['months'],
	array(
		'2026-11' => month( 1 ),
		'2026-12' => month( 1, 0, 0, 1 ),
		'2027-01' => month( 0, 1 ),
		'2027-02' => month( 1, 0, 0, 0, 1 ),
		'2027-03' => month( 1, 1 ),
	)
);
ck( 'today: one open, the held one, and one selected and waiting for the course', $built['today'], array( 'open' => 1, 'selected' => 1 ) );
ck( 'and when it was built', $built['generated'], '2027-03-16T12:00:00+00:00' );

$declined = WPCPM_Mentor_Application::applications( array( 'declined' ) );
WPCPM_Mentor_Application::forget( $declined[0], 365, 0 );
ck( 'a row retention deleted still counts in its months', WPCPM_Mentor_Application_Feed::build( $GLOBALS['now'] )['months'], $built['months'] );

echo "\n=== Counts, never people ===\n";

fresh();
$sentinel = 'SENTINEL-<b>"&\'';
$answers  = array();
foreach ( array( 'name', 'profile', 'email', 'country', 'hours', 'anything' ) as $key ) {
	$answers[ $key ] = $sentinel . $key;
}
$answers['languages'] = array( $sentinel . 'language' );
$planted              = a_row( gmmktime( 9, 0, 0, 3, 2, 2027 ), 'second', array( array( 'second opinion', 'second', gmmktime( 9, 0, 0, 3, 3, 2027 ), $sentinel . 'note' ) ), $answers );
update_post_meta( $planted, WPCPM_Mentor_Application::META_REFERENCE, $sentinel . 'reference' );
$stored = wp_json_encode( WPCPM_Mentor_Application::fields_of( get_post( $planted ) ) ) . wp_json_encode( WPCPM_Mentor_Application::events( $planted ) );
$answer = wp_json_encode( WPCPM_Mentor_Application_Feed::build( $GLOBALS['now'] ) );
ck( 'every sentinel is planted where a person\'s words are kept, so the walk cannot pass by planting nothing', substr_count( $stored, 'SENTINEL' ), 8 );
ck( 'and none of them, nor anything of the reference, reaches the answer', array( substr_count( $answer, 'SENTINEL' ), substr_count( $answer, 'MAPP-' ), substr_count( $answer, '@' ) ), array( 0, 0, 0 ) );
ck( 'whose keys are the months, today and when it was built, and nothing else', array_keys( WPCPM_Mentor_Application_Feed::build( $GLOBALS['now'] ) ), array( 'months', 'today', 'generated' ) );

echo "\n=== Cached and metered ===\n";

fresh();
a_row( gmmktime( 9, 0, 0, 3, 1, 2027 ), 'new' );
$first = WPCPM_Mentor_Application_Feed::rest_counts( new WP_REST_Request() );
ck( 'an answer is built, kept fifteen minutes, and says a cache may keep it as long', array( $first->status, $first->data['today']['open'], get_transient( WPCPM_Mentor_Application_Feed::TRANSIENT ) === $first->data, $first->headers ), array( 200, 1, true, array( 'Cache-Control' => 'public, max-age=900' ) ) );
set_transient( WPCPM_Mentor_Application_Feed::TRANSIENT, array( 'kept' => 'sentinel' ) );
ck( 'and read back without a rebuild while it is kept', WPCPM_Mentor_Application_Feed::rest_counts( new WP_REST_Request() )->data, array( 'kept' => 'sentinel' ) );

fresh();
for ( $i = 0; $i < WPCPM_Mentor_Application_Feed::PER_HOUR; $i++ ) {
	WPCPM_Mentor_Application_Feed::rest_counts( new WP_REST_Request() );
}
delete_transient( WPCPM_Mentor_Application_Feed::TRANSIENT );
$refused = WPCPM_Mentor_Application_Feed::rest_counts( new WP_REST_Request() );
ck( 'the sixty-first request from one address in an hour is refused with 429, before anything is read', array( $refused->status, $refused->data['code'] ?? null, get_transient( WPCPM_Mentor_Application_Feed::TRANSIENT ) ), array( 429, 'wpcpm_feed_busy', false ) );
$_SERVER['REMOTE_ADDR'] = '198.51.100.20';
ck( 'and another address is answered', WPCPM_Mentor_Application_Feed::rest_counts( new WP_REST_Request() )->status, 200 );
ck( 'nothing was asked of WordPress that the stand-ins do not model', isset( $GLOBALS['unmodeled'] ) ? $GLOBALS['unmodeled'] : array(), array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

```diff
diff --git a/bin/test-mentors-screen.php b/bin/test-mentors-screen.php
--- a/bin/test-mentors-screen.php
+++ b/bin/test-mentors-screen.php
@@ -125,6 +125,9 @@ class WPCPM_Mentor_Application {
 class WPCPM_Mentor_Application_Form {
 	public static function init() {}
 }
+class WPCPM_Mentor_Application_Feed {
+	public static function init() {}
+}
 class WPCPM_Mentor_Application_Queue {
 	public static function init() {}
 	public static function messages() { return array( 'mapp-selected' => array( 'success', 'The applicant is selected.' ) ); }
diff --git a/bin/test-uninstall.php b/bin/test-uninstall.php
--- a/bin/test-uninstall.php
+++ b/bin/test-uninstall.php
@@ -675,6 +675,8 @@ $GLOBALS['opts']['wpcpm_tracks_upgrade_lock'] = time() - HOUR_IN_SECONDS;
 set_transient( 'wpcpm_airtable_schema', array( 'read' => time() ), 15 * MINUTE_IN_SECONDS );
 set_transient( 'wpcpm_learn_course_example-course', array( 'id' => 1 ), DAY_IN_SECONDS );
 set_transient( 'wpcpm_learn_structure_1', array(), DAY_IN_SECONDS );
+// The mentor application feed's answer, kept fifteen minutes (1.122.31).
+set_transient( 'wpcpm_mentor_applications_feed', array( 'months' => array() ), 15 * MINUTE_IN_SECONDS );
 
 // The mail log holds the addresses mail went to; the queue, the people still waiting for one; the
 // run, the counts and times of the last bulk invite (the final fix wave, item 6).
@@ -999,6 +1001,7 @@ $names = array(
 	'WPCPM_Mentor_Application::OPT_KEPT'            => 'wpcpm_mentor_application_counts',
 	'WPCPM_Mentor_Application::CRON_DAILY'          => 'wpcpm_mentor_applications_daily',
 	'WPCPM_Mentor_Application_Form::OPT_PAGE'       => 'wpcpm_mentor_application_page_id',
+	'WPCPM_Mentor_Application_Feed::TRANSIENT'      => 'wpcpm_mentor_applications_feed',
 );
 
 ck( 'the names seeded above are the ones the classes declare', array_map( 'declared', array_keys( $names ) ), array_values( $names ) );
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php bin/test-mentor-application-feed.php`, `php bin/test-mentors-screen.php` and `php bin/test-uninstall.php`

```text
$ php bin/test-mentor-application-feed.php
PHP Warning:  require_once(<plugin>/includes/modules/class-wpcpm-mentor-application-feed.php): Failed to open stream: No such file or directory in <plugin>/bin/test-mentor-application-feed.php on line 59
PHP Fatal error:  Uncaught Error: Failed opening required '<plugin>/includes/modules/class-wpcpm-mentor-application-feed.php' (include_path='.:/opt/homebrew/Cellar/php/8.5.7/share/php/pear') in <plugin>/bin/test-mentor-application-feed.php:59
[exit 255]
$ php bin/test-mentors-screen.php
ALL PASS (172 checks)
[exit 0]
$ php bin/test-uninstall.php
FAIL no option of the plugin's is left but that key
FAIL the names seeded above are the ones the classes declare
2 FAILED (36 checks)
[exit 1]
```

The Mentors screen's suite passes: its stand-in for the feed waits for the boot that calls it.

- [ ] **Step 3: The feed**

Create `includes/modules/class-wpcpm-mentor-application-feed.php`:

```php
<?php
/**
 * Mentors module - the public feed of mentor application counts (1.122.31).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `GET /wp-json/wpcpm/v1/mentor-applications`: how many people applied to mentor, were selected,
 * became mentors, were declined and lapsed, per calendar month since the first application, and how
 * many applications are open and how many selected and waiting for the course today (design of
 * 16 October 2026, section 3, decision 10). The Credits Monthly Report and the WPCredits Tracker read
 * it, in releases of their own, where they read the Airtable Mentors table's Status until now.
 *
 * **Counts, never people.** The answer holds month keys, numbers and the time it was built: no name,
 * address, username, reference or note, ever. The months come from `WPCPM_Mentor_Application::
 * month_counts_of()` over the rows the site holds and the counts deleted rows left behind
 * (`kept_counts()`), so retention never rewrites a month the feed has already shown.
 *
 * Public, as both readers are anonymous scripts, metered per address through `WPCPM_Ceiling` and
 * cached for fifteen minutes, as the sponsors feed is: a burst of requests costs the site one read
 * of the rows, not one per request. A new application reaches the counts within the fifteen minutes,
 * which a figure read once a month does not notice.
 */
final class WPCPM_Mentor_Application_Feed {

	/** Registered under `wpcpm/v1`. */
	const ROUTE = '/mentor-applications';

	/** The built answer, held for fifteen minutes. */
	const TRANSIENT = 'wpcpm_mentor_applications_feed';

	/** This route's own name for the per-address ceiling. */
	const CEILING = 'mentor-applications-feed';

	/** Requests one address may make in an hour. */
	const PER_HOUR = 60;

	/** How long an answer is kept and may be kept by a cache on the way, in seconds. */
	const CACHE_SECONDS = 900;

	/**
	 * Hook the route.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
	}

	/**
	 * Register the feed: public, and taking no argument, since the answer is the same for everybody
	 * who asks in the same fifteen minutes.
	 */
	public static function register_route() {
		register_rest_route(
			'wpcpm/v1',
			self::ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_counts' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * The feed: metered first, then the cached answer or a fresh one.
	 *
	 * @param WP_REST_Request $request The request. Unused: the route reads no argument.
	 * @return WP_REST_Response
	 */
	public static function rest_counts( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found,VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- the REST dispatcher always hands the callback a request; see the docblock.
		if ( ! WPCPM_Ceiling::claim( WPCPM_Form_Guard::actor_key( self::CEILING ), self::PER_HOUR, HOUR_IN_SECONDS ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'wpcpm_feed_busy',
					'message' => __( 'Too many requests. Try again in an hour.', 'wpcredits-program-manager' ),
				),
				429
			);
		}

		$answer = get_transient( self::TRANSIENT );

		if ( ! is_array( $answer ) ) {
			$answer = self::build();

			set_transient( self::TRANSIENT, $answer, self::CACHE_SECONDS );
		}

		$response = new WP_REST_Response( $answer, 200 );
		$response->header( 'Cache-Control', 'public, max-age=' . self::CACHE_SECONDS );

		return $response;
	}

	/**
	 * The answer, built fresh.
	 *
	 * @param int $now A Unix time, or 0 for now.
	 * @return array `months` (`Y-m` => `received`, `selected`, `activated`, `declined`, `lapsed`, an
	 *               object while there are none), `today` (`open`, `selected`), `generated` (`c`).
	 */
	public static function build( $now = 0 ) {
		$now    = $now > 0 ? (int) $now : time();
		$months = self::months( $now );

		return array(
			'months'    => empty( $months ) ? new stdClass() : $months,
			'today'     => array(
				'open'     => WPCPM_Mentor_Application::waiting_count(),
				'selected' => WPCPM_Mentor_Application::count_in( array( WPCPM_Mentor_Application::STATE_SELECTED ) ),
			),
			'generated' => gmdate( 'c', $now ),
		);
	}

	/**
	 * Every calendar month from the first one anything was counted in to this one, on the site's
	 * clock, each with its five counts, a month with nothing a month of zeros; none before the first
	 * application.
	 *
	 * @param int $now A Unix time.
	 * @return array<string, array{received: int, selected: int, activated: int, declined: int, lapsed: int}>
	 */
	private static function months( $now ) {
		$counted = WPCPM_Mentor_Application::kept_counts();

		foreach ( WPCPM_Mentor_Application::applications( WPCPM_Mentor_Application::states() ) as $post ) {
			foreach ( WPCPM_Mentor_Application::month_counts_of( $post ) as $month => $row ) {
				foreach ( $row as $key => $count ) {
					$counted[ $month ][ $key ] = ( isset( $counted[ $month ][ $key ] ) ? (int) $counted[ $month ][ $key ] : 0 ) + (int) $count;
				}
			}
		}

		$keys = array_filter(
			array_keys( $counted ),
			static function ( $month ) {
				return 1 === preg_match( '/^\d{4}-\d{2}$/', (string) $month );
			}
		);

		if ( empty( $keys ) ) {
			return array();
		}

		sort( $keys );

		$out   = array();
		$month = (string) reset( $keys );
		$last  = wp_date( 'Y-m', $now );
		$last  = strcmp( $last, (string) end( $keys ) ) >= 0 ? $last : (string) end( $keys );
		$zero  = array_fill_keys( array( 'received', 'selected', 'activated', 'declined', 'lapsed' ), 0 );

		while ( strcmp( $month, $last ) <= 0 ) {
			$out[ $month ] = array_merge( $zero, array_intersect_key( isset( $counted[ $month ] ) ? array_map( 'intval', (array) $counted[ $month ] ) : array(), $zero ) );

			list( $year, $number ) = array_map( 'intval', explode( '-', $month ) );

			$month = 12 === $number ? sprintf( '%04d-01', $year + 1 ) : sprintf( '%04d-%02d', $year, $number + 1 );
		}

		return $out;
	}
}
```

```diff
diff --git a/includes/modules/class-wpcpm-mentors.php b/includes/modules/class-wpcpm-mentors.php
--- a/includes/modules/class-wpcpm-mentors.php
+++ b/includes/modules/class-wpcpm-mentors.php
@@ -127,11 +127,12 @@ class WPCPM_Mentors extends WPCPM_Sync_Module {
 		WPCPM_Mentor_Calls::init();
 		WPCPM_Call_Calendar::init();
 		WPCPM_Group_Sessions::init();
-		// The applications to mentor (1.122.31): the post type they are kept in, the public form, and
-		// the decisions taken on the Waiting for review tab.
+		// The applications to mentor (1.122.31): the post type they are kept in, the public form, the
+		// decisions taken on the Waiting for review tab, and the public feed of their counts.
 		WPCPM_Mentor_Application::init();
 		WPCPM_Mentor_Application_Form::init();
 		WPCPM_Mentor_Application_Queue::init();
+		WPCPM_Mentor_Application_Feed::init();
 
 		// The hourly call reminders, put back on the clock whenever they are missing, as the
 		// sync above does for its own job: the activation hook is the only other caller and
@@ -185,6 +186,7 @@ class WPCPM_Mentors extends WPCPM_Sync_Module {
 		// the plugin creates does: a page is site content.
 		WPCPM_Mentor_Application::delete_all();
 		delete_option( WPCPM_Mentor_Application_Form::OPT_PAGE );
+		delete_transient( WPCPM_Mentor_Application_Feed::TRANSIENT );
 		// Prefix-named, so it cannot be swept by listing keys: booking locks left by
 		// requests that died holding one, and the per-student resolved-mentor cache.
 		WPCPM_Mentor_Calls::flush_cache();
diff --git a/uninstall.php b/uninstall.php
--- a/uninstall.php
+++ b/uninstall.php
@@ -104,6 +104,7 @@ require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentor-
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentor-application.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentor-application-form.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentor-application-queue.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-mentor-application-feed.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-institutions.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-countries.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-institutions-index.php';
diff --git a/wpcredits-program-manager.php b/wpcredits-program-manager.php
--- a/wpcredits-program-manager.php
+++ b/wpcredits-program-manager.php
@@ -94,6 +94,7 @@ require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-engagement.
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-form.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-queue.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-feed.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-countries.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';
```

- [ ] **Step 4: Run them to verify they pass**

Run: the three suites of step 2.

```text
$ php bin/test-mentor-application-feed.php
ALL PASS (14 checks)
[exit 0]
$ php bin/test-mentors-screen.php
ALL PASS (172 checks)
[exit 0]
$ php bin/test-uninstall.php
ALL PASS (36 checks)
[exit 0]
```

- [ ] **Step 5: The checks every task ends with**

```text
battery done
157 classes, 15077 references checked, 88 form handlers scanned - all resolve
160 PHP files, 8 blocks, 4 track seeds, 23 fixture and tooling files, the guides and the readme: US English throughout.
282 annotations, 0 dead.
Ran 136 scripts from bin/, each in a PHP process of its own, and every one exited 0.
The battery leaves the temp directory as it found it.
78 warnings, no errors.
```

- [ ] **Step 6: Commit**

```bash
git add includes/modules/class-wpcpm-mentor-application-feed.php includes/modules/class-wpcpm-mentors.php wpcredits-program-manager.php uninstall.php bin/test-mentor-application-feed.php bin/test-mentors-screen.php bin/test-uninstall.php
git commit -m "Mentor applications: the public feed of their counts by month, and how many are open and waiting for the course today, with no field of a person" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 8: The Administrator Dashboard's Mentor applications waiting tile

The strip gains a tile beside the two kinds of application it counts (spec, section 3, decision 9), placed right after **Sponsor applications waiting**. It counts the open applications through the record class (`waiting_count()`), and, having no card on the dashboard, opens the Mentors screen's Waiting for review tab, where they are decided: the tile carries `url` in place of `card`, and the strip prints that address as its link. The Overview's **Waiting for a decision** card lists the same count with the same destination, since its map must place every tile.

**Files:**
- Modify: `includes/modules/class-wpcpm-administrators-cards.php` (`collect()`, `counts()`, `render_strip()`)
- Modify: `includes/class-wpcpm-overview.php` (`targets()`)
- Modify: `bin/test-administrators-dashboard.php`, `bin/test-overview.php`, `bin/stubs/overview-reads.php`

**Interfaces:**
- Consumes: `WPCPM_Mentor_Application::waiting_count(): int`.
- Produces: `WPCPM_Administrators_Cards::collect()['mentor_applications']` (int); `counts()['mentor_applications']` = `array( 'label' => 'Mentor applications waiting', 'n' => int, 'url' => admin_url( 'admin.php?page=wpcpm-mentors&tab=queue' ) )`, between `sponsor_applications` and `offers_low`; fourteen tiles; a tile carrying `url` is linked to it, any other to `#wpcpm-<card>`. `WPCPM_Overview::targets()['mentor_applications']`, the same address.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/stubs/overview-reads.php b/bin/stubs/overview-reads.php
--- a/bin/stubs/overview-reads.php
+++ b/bin/stubs/overview-reads.php
@@ -22,6 +22,7 @@
  * - `sponsor_posts` and `sponsor_applications`: the rows waiting, and `sponsor_agreements`, ID =>
  *   the facts of a document waiting for review;
  * - `offers`, ID => offer, and `codes`, ID => the offer's code counts;
+ * - `mentor_applications`: how many mentor applications wait in the Mentors screen's queue;
  * - `duplicates`: the Student Duplicate Finder's last report, or none before its first scan;
  * - `progress`: audience => where its sync is (`running`, `label`, `error`).
  *
@@ -261,6 +262,15 @@ class WPCPM_Sponsor_Codes {
 	}
 }
 
+/* ---- the mentors' queue ------------------------------------------------------ */
+
+/** Mentor applications waiting for a decision (1.122.31): the number, all the cards read of them. */
+class WPCPM_Mentor_Application {
+	public static function waiting_count() {
+		return (int) wpcpm_overview_holds( 'mentor_applications', 0 );
+	}
+}
+
 /* ---- the syncs, the scan, the storage and the mail -------------------------- */
 
 class WPCPM_Students_Sync {
diff --git a/bin/test-administrators-dashboard.php b/bin/test-administrators-dashboard.php
--- a/bin/test-administrators-dashboard.php
+++ b/bin/test-administrators-dashboard.php
@@ -7,8 +7,10 @@
  *
  * - **Every card reads through the owning class.** The collaborators are stubbed to their
  *   contracts and record what was asked; the cards never query posts themselves.
- * - **The counts are the cards' counts.** The strip's eight numbers come from the same
- *   arrays the cards draw, so a tile and its card cannot disagree.
+ * - **The counts are the cards' counts.** The strip's numbers come from the same
+ *   arrays the cards draw, so a tile and its card cannot disagree. The one tile with no card
+ *   here, the mentor applications waiting (1.122.31), counts through the record class and
+ *   opens the Mentors screen at Waiting for review, where they are decided.
  * - **A decision posted here comes back here.** Every application and request form
  *   carries `wpcpm_return=dashboard`; the agreement forms return to the referer already.
  * - **Every link to an institution carries the switcher argument**, or a manager lands on
@@ -599,6 +601,10 @@ class WPCPM_Sponsor_Application {
 	public static function render_details( $post ) { echo '<div class="wpcpm-sapp-details-stub" data-post="' . (int) $post->ID . '"></div>'; }
 	public static function manager_messages() { return array( 'sapp-approved' => array( 'success', 'The sponsor application is approved.' ) ); }
 }
+/** The mentor applications waiting for a decision (1.122.31): their number, all the cards read of them. */
+class WPCPM_Mentor_Application {
+	public static function waiting_count() { return isset( $GLOBALS['mentor_waiting'] ) ? (int) $GLOBALS['mentor_waiting'] : 0; }
+}
 class WPCPM_Sponsor_Offers {
 	const KIND_CODES  = 'codes';
 	const KIND_SHARED = 'shared';
@@ -646,7 +652,7 @@ class WPCPM_Sponsors_Sync {
 	public static function progress() { return isset( $GLOBALS['sync']['sponsors'] ) ? $GLOBALS['sync']['sponsors'] : array( 'running' => false, 'phase' => '', 'label' => '', 'error' => '', 'elapsed' => 0 ); }
 	public static function last_read() { return 1756880000; }
 }
-// The Student Duplicate Finder's scan, as the thirteenth tile and its card read it (1.102.0): the
+// The Student Duplicate Finder's scan, as the last tile and its card read it (1.102.0): the
 // stored report, of which the dashboard reads the counts and the read time and nothing else.
 class WPCPM_Duplicates_Scan {
 	public static function report() { return isset( $GLOBALS['dup_report'] ) ? $GLOBALS['dup_report'] : array(); }
@@ -845,6 +851,9 @@ $GLOBALS['audit'] = array(
 // groups the finder's screen lists left out, because the dashboard never reads them.
 $GLOBALS['dup_report'] = array( 'v' => 1, 'read' => 1757000000, 'counts' => array( 'addresses' => 3, 'ready' => 1, 'decide' => 2 ) );
 
+// Four mentor applications waiting in the Mentors screen's queue.
+$GLOBALS['mentor_waiting'] = 4;
+
 /* ---- collect() and counts() ---------------------------------------------- */
 
 echo "=== The data is read once, through the owners ===\n";
@@ -864,14 +873,16 @@ ck( 'two open requests, one overdue, one closed', array( count( $data['requests'
 ck( 'one locked account', count( $data['locked'] ), 1 );
 
 $counts = WPCPM_Administrators_Cards::counts( $data );
-ck( 'thirteen tiles in the spec\'s order, the sponsor tiles and then the duplicated students last', array_keys( $counts ), array( 'applications', 'agreements', 'overdue_agreements', 'drafts', 'due', 'requests', 'overdue_requests', 'locked', 'sponsor_posts', 'sponsor_agreements', 'sponsor_applications', 'offers_low', 'duplicates' ) );
+ck( 'fourteen tiles in the spec\'s order, the sponsor tiles, the mentor applications beside the sponsor applications, and then the duplicated students last', array_keys( $counts ), array( 'applications', 'agreements', 'overdue_agreements', 'drafts', 'due', 'requests', 'overdue_requests', 'locked', 'sponsor_posts', 'sponsor_agreements', 'sponsor_applications', 'mentor_applications', 'offers_low', 'duplicates' ) );
 // array_map() keeps the input array's keys, and counts() is keyed by tile name (the
 // previous check pins that order), so the expectation is keyed the same way rather than
 // the plain list the brief first wrote, which could never === an array with string keys.
-ck( 'each tile is a number and a card', array_map( static function ( $t ) { return $t['n'] . ':' . $t['card']; }, $counts ), array( 'applications' => '2:applications', 'agreements' => '2:agreements', 'overdue_agreements' => '1:agreements', 'drafts' => '1:reports', 'due' => '1:reports', 'requests' => '2:requests', 'overdue_requests' => '1:requests', 'locked' => '1:health', 'sponsor_posts' => '1:sponsor-posts', 'sponsor_agreements' => '1:sponsor-agreements', 'sponsor_applications' => '1:sponsor-applications', 'offers_low' => '1:offers-low', 'duplicates' => '3:duplicates' ) );
+// The mentor applications' tile has no card on this page, so it names where it opens instead.
+ck( 'each tile is a number and a card, but the mentor applications\', which is a number and the address of their queue', array_map( static function ( $t ) { return $t['n'] . ':' . ( isset( $t['card'] ) ? $t['card'] : $t['url'] ); }, $counts ), array( 'applications' => '2:applications', 'agreements' => '2:agreements', 'overdue_agreements' => '1:agreements', 'drafts' => '1:reports', 'due' => '1:reports', 'requests' => '2:requests', 'overdue_requests' => '1:requests', 'locked' => '1:health', 'sponsor_posts' => '1:sponsor-posts', 'sponsor_agreements' => '1:sponsor-agreements', 'sponsor_applications' => '1:sponsor-applications', 'mentor_applications' => '4:https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue', 'offers_low' => '1:offers-low', 'duplicates' => '3:duplicates' ) );
+ck( 'the mentor applications tile reads what waits through the record class, in the words of the design, and carries no card', array( $data['mentor_applications'], $counts['mentor_applications'] ), array( 4, array( 'label' => 'Mentor applications waiting', 'n' => 4, 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue' ) ) );
 
 echo "\n=== The sponsors' figures, the pools running low and the new interests (S6) ===\n";
-ck( 'thirteen tiles now, offers running low just before the duplicated students', array( count( $counts ), array_slice( array_keys( $counts ), -3 ), $counts['offers_low'] ), array( 13, array( 'sponsor_applications', 'offers_low', 'duplicates' ), array( 'label' => 'Offers running low', 'n' => 1, 'card' => 'offers-low' ) ) );
+ck( 'fourteen tiles now, offers running low just before the duplicated students', array( count( $counts ), array_slice( array_keys( $counts ), -3 ), $counts['offers_low'] ), array( 14, array( 'mentor_applications', 'offers_low', 'duplicates' ), array( 'label' => 'Offers running low', 'n' => 1, 'card' => 'offers-low' ) ) );
 ck( 'one pool runs low: live, of kind codes, under its own threshold; the expired, ended, shared and well-stocked ones are not it', array_map( static function ( $r ) { return $r['id'] . ':' . $r['available'] . '/' . $r['low'] . ':' . $r['sponsor_name']; }, $data['offers_low'] ), array( '941:3/10:TEST Sponsor' ) );
 ck( 'and it links to the sponsor\'s Offers and codes card through the switcher', $data['offers_low'][0]['url'], 'https://site.example/sponsor-dashboard/?wpcpm_sponsor_view=recSPN00000000001#wpcpm-sponsor-offers' );
 ck( 'one interest in the last thirty days, of the audit kind sponsor_interest only, with the sponsor\'s name and the line as written', array( count( $data['interests'] ), $data['interests'][0]['sponsor_name'], $data['interests'][0]['message'], $data['interests'][0]['url'] ), array( 1, 'TEST Sponsor', '2026-09-01 by Member One: Sponsor a mentor or multiple mentors; events: WordCamp Europe; note: "Two mentors from Q1."', 'https://site.example/sponsor-dashboard/?wpcpm_sponsor_view=recSPN00000000001#wpcpm-sponsor-interests' ) );
@@ -900,7 +911,7 @@ $strip2 = capture( static function () use ( $data ) { WPCPM_Administrators_Cards
 // hardcoded digit here would silently pin the wrong one (brief review).
 ck( 'the Sponsors card is four tiles on the programs card\'s markup: a name, the number and a qualifier each, never the name twice', array( has( $strip2, 'id="wpcpm-sponsors"' ), substr_count( $strip2, '<li class="wpcpm-programs__tile">' ), preg_match( '#<span class="wpcpm-programs__name">Approved sponsors</span><span class="wpcpm-programs__n">2</span><span class="wpcpm-programs__l">in the program records</span>#', $strip2 ), preg_match( '#<span class="wpcpm-programs__name">With an account</span><span class="wpcpm-programs__n">1</span><span class="wpcpm-programs__l">of the Approved sponsors</span>#', $strip2 ), preg_match( '#<span class="wpcpm-programs__name">Live offers</span><span class="wpcpm-programs__n">3</span><span class="wpcpm-programs__l">shown on the site today</span>#', $strip2 ), preg_match( '#<span class="wpcpm-programs__name">Claims this semester</span><span class="wpcpm-programs__n">' . (int) $data['sponsors']['claims_semester'] . '</span><span class="wpcpm-programs__l">since ' . preg_quote( $data['sponsors']['since_display'], '#' ) . ', on every offer</span>#', $strip2 ) ), array( true, 4, 1, 1, 1, 1 ) );
 
-echo "\n=== Duplicated students: the thirteenth tile and its card (1.102.0) ===\n";
+echo "\n=== Duplicated students: the last tile and its card (1.102.0) ===\n";
 
 ck( 'the tile reads the Student Duplicate Finder\'s last scan through the scan\'s own report', array( $data['duplicates'], $counts['duplicates'] ), array( array( 'addresses' => 3, 'ready' => 1, 'read' => 1757000000, 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-duplicate-finder' ), array( 'label' => 'Duplicated students', 'n' => 3, 'card' => 'duplicates' ) ) );
 $dup = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_duplicates( $data['duplicates'] ); } );
@@ -966,14 +977,19 @@ $strip = capture( static function () use ( $counts ) { WPCPM_Administrators_Card
 // Eight, counting the opening tag rather than the bare class: the wrapping
 // <ul class="wpcpm-attention__tiles"> also matches the bare needle, since "tiles" starts with
 // "tile", so the bare count would read nine and call the wrapper a ninth tile.
-ck( 'the strip is one section with thirteen tiles linking to the cards', array( substr_count( $strip, '<li class="wpcpm-attention__tile' ), has( $strip, 'id="wpcpm-attention"' ), has( $strip, 'href="#wpcpm-agreements"' ), has( $strip, 'href="#wpcpm-sponsor-posts"' ), has( $strip, 'href="#wpcpm-sponsor-agreements"' ), has( $strip, 'href="#wpcpm-sponsor-applications"' ), has( $strip, 'href="#wpcpm-duplicates"' ) ), array( 13, true, true, true, true, true, true ) );
+ck( 'the strip is one section with fourteen tiles linking to the cards', array( substr_count( $strip, '<li class="wpcpm-attention__tile' ), has( $strip, 'id="wpcpm-attention"' ), has( $strip, 'href="#wpcpm-agreements"' ), has( $strip, 'href="#wpcpm-sponsor-posts"' ), has( $strip, 'href="#wpcpm-sponsor-agreements"' ), has( $strip, 'href="#wpcpm-sponsor-applications"' ), has( $strip, 'href="#wpcpm-duplicates"' ) ), array( 14, true, true, true, true, true, true ) );
+// A card this page does not draw is no place to anchor to: the tile opens the Mentors screen.
+ck( 'and the mentor applications tile opens the Mentors screen at Waiting for review, never an anchor to a card this page does not draw', array( preg_match( '#<li class="wpcpm-attention__tile"><a class="wpcpm-attention__link" href="https://example\.test/wp-admin/admin\.php\?page=wpcpm-mentors&tab=queue"><span class="wpcpm-attention__n">4</span><span class="wpcpm-attention__l">Mentor applications waiting</span></a></li>#', $strip ), has( $strip, 'href="#wpcpm-mentor' ), has( $strip, 'href="#wpcpm-"' ) ), array( 1, false, false ) );
 // None of the fixture's eight counts is 0, so this used to hold no matter what render_strip()
 // did with a zero; a tile is zeroed here, from counts()'s own output, so the check can
 // actually fail if --zero ever stops being drawn (final review, Important 6).
 $zero_counts = $counts;
 $zero_counts['locked']['n'] = 0;
 $zero_strip = capture( static function () use ( $zero_counts ) { WPCPM_Administrators_Cards::render_strip( $zero_counts ); } );
-ck( 'a zero is drawn muted, not hidden', array( substr_count( $zero_strip, 'wpcpm-attention__tile--zero' ), substr_count( $zero_strip, '<li' ) ), array( 1, 13 ) );
+ck( 'a zero is drawn muted, not hidden', array( substr_count( $zero_strip, 'wpcpm-attention__tile--zero' ), substr_count( $zero_strip, '<li' ) ), array( 1, 14 ) );
+$zero_counts['mentor_applications']['n'] = 0;
+$zero_mentors = capture( static function () use ( $zero_counts ) { WPCPM_Administrators_Cards::render_strip( $zero_counts ); } );
+ck( 'and so is an empty mentor queue, still a link to it', array( substr_count( $zero_mentors, 'wpcpm-attention__tile--zero' ), has( $zero_mentors, '<li class="wpcpm-attention__tile wpcpm-attention__tile--zero"><a class="wpcpm-attention__link" href="https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue"><span class="wpcpm-attention__n">0</span>' ) ), array( 2, true ) );
 
 $apps = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_applications( $data['applications'] ); } );
 ck( 'the applications card is open with its count', has( $apps, 'id="wpcpm-applications"' ) && has( $apps, 'wpcpm-group__disclosure" open' ) && has( $apps, '<span class="wpcpm-group__count">2</span>' ), true );
diff --git a/bin/test-overview.php b/bin/test-overview.php
--- a/bin/test-overview.php
+++ b/bin/test-overview.php
@@ -529,6 +529,7 @@ $busy = array(
 		),
 	),
 	'sponsor_applications' => array( array( 'id' => 950 ) ),
+	'mentor_applications'  => 2,
 	'offers'               => array(
 		941 => array(
 			'id'      => 941,
@@ -618,9 +619,9 @@ foreach ( $counts as $tile ) {
 	}
 }
 
-ck( 'and a queue with nothing in it has no link: ten of the thirteen are zero, and none of them is on the list of three',
+ck( 'and a queue with nothing in it has no link: eleven of the fourteen are zero, and none of them is on the list of three',
 	array( count( array_filter( $counts, static function ( $tile ) { return 0 === (int) $tile['n']; } ) ), count( waiting_of( $waiting ) ), $zero_labels ),
-	array( 10, 3, array() ) );
+	array( 11, 3, array() ) );
 
 $busy_counts = counts_for( $busy );
 $busy_html   = draw( $busy );
@@ -628,7 +629,7 @@ $busy_links  = waiting_of( card( $busy_html, 'Waiting for a decision' ) );
 
 ck( 'with every queue waiting, the list is the Administrator Dashboard\'s strip, tile for tile: the same numbers, the same labels, the same order',
 	array( count( $busy_links ), array_map( static function ( $link ) { return array( $link[2], $link[1] ); }, $busy_links ) ),
-	array( 13, strip_tiles( $busy_counts ) ) );
+	array( 14, strip_tiles( $busy_counts ) ) );
 
 $busy_targets = array();
 
@@ -703,7 +704,7 @@ ck( 'every tile the strip counts has a place to link to, and every place on the
 	is_array( $targets ) ? array( array_values( array_diff( $keys, array_keys( $targets ) ) ), array_values( array_diff( array_keys( $targets ), $keys ) ) ) : $targets,
 	array( array(), array() ) );
 
-ck( 'today the institutions\' queues open the Institutions screen at the tab for each, the decisions on Waiting for review, the semester reports on Semester reports and the locked accounts on Accounts; the sponsors\' the Sponsors screen at the tab for each, the applications, the posts and the signed agreements on Waiting for review, each at its own card there, and the offers running low on Offers and codes; and the duplicated students the Student Duplicate Finder',
+ck( 'today the institutions\' queues open the Institutions screen at the tab for each, the decisions on Waiting for review, the semester reports on Semester reports and the locked accounts on Accounts; the sponsors\' the Sponsors screen at the tab for each, the applications, the posts and the signed agreements on Waiting for review, each at its own card there, and the offers running low on Offers and codes; the mentor applications the Mentors screen at Waiting for review; and the duplicated students the Student Duplicate Finder',
 	$targets,
 	array(
 		'applications'         => 'https://site.example/wp-admin/admin.php?page=wpcpm-institutions&tab=queue',
@@ -717,6 +718,7 @@ ck( 'today the institutions\' queues open the Institutions screen at the tab for
 		'sponsor_posts'        => 'https://site.example/wp-admin/admin.php?page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-posts',
 		'sponsor_agreements'   => 'https://site.example/wp-admin/admin.php?page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-agreements',
 		'sponsor_applications' => 'https://site.example/wp-admin/admin.php?page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-applications',
+		'mentor_applications'  => 'https://site.example/wp-admin/admin.php?page=wpcpm-mentors&tab=queue',
 		'offers_low'           => 'https://site.example/wp-admin/admin.php?page=wpcpm-sponsors&tab=offers',
 		'duplicates'           => 'https://site.example/wp-admin/admin.php?page=wpcpm-tool-duplicate-finder',
 	) );
@@ -954,7 +956,7 @@ $everything = draw( array_merge( $busy, $syncs_world ) );
 
 ck( 'nothing on the screen says module, with every queue waiting, every sync and every tool on it',
 	array( count( waiting_of( card( $everything, 'Waiting for a decision' ) ) ), count( rows_of( card( $everything, 'Syncs' ) ) ), modules_in( words_of( $everything ) ) ),
-	array( 13, 5, array() ) );
+	array( 14, 5, array() ) );
 
 // Decisions have one home, the Administrator Dashboard: this screen links to it and to the queues,
 // and posts nothing itself.
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php bin/test-administrators-dashboard.php`, `php bin/test-overview.php` and `php bin/test-admin-menu.php` (the last draws the Overview through the same stand-ins)

```text
$ php bin/test-administrators-dashboard.php
FAIL fourteen tiles in the spec's order, the sponsor tiles, the mentor applications beside the sponsor applications, and then the duplicated students last
FAIL each tile is a number and a card, but the mentor applications', which is a number and the address of their queue
PHP Warning:  Undefined array key "mentor_applications" in <plugin>/bin/test-administrators-dashboard.php on line 882
FAIL the mentor applications tile reads what waits through the record class, in the words of the design, and carries no card
FAIL fourteen tiles now, offers running low just before the duplicated students
FAIL the strip is one section with fourteen tiles linking to the cards
FAIL and the mentor applications tile opens the Mentors screen at Waiting for review, never an anchor to a card this page does not draw
FAIL a zero is drawn muted, not hidden
PHP Warning:  Undefined array key "card" in <plugin>/includes/modules/class-wpcpm-administrators-cards.php on line 1278
FAIL and so is an empty mentor queue, still a link to it
8 FAILED (196 checks)
[exit 1]
$ php bin/test-overview.php
FAIL and a queue with nothing in it has no link: eleven of the fourteen are zero, and none of them is on the list of three
FAIL with every queue waiting, the list is the Administrator Dashboard's strip, tile for tile: the same numbers, the same labels, the same order
FAIL today the institutions' queues open the Institutions screen at the tab for each, the decisions on Waiting for review, the semester reports on Semester reports and the locked accounts on Accounts; the sponsors' the Sponsors screen at the tab for each, the applications, the posts and the signed agreements on Waiting for review, each at its own card there, and the offers running low on Offers and codes; the mentor applications the Mentors screen at Waiting for review; and the duplicated students the Student Duplicate Finder
FAIL nothing on the screen says module, with every queue waiting, every sync and every tool on it
4 FAILED (32 checks)
[exit 1]
$ php bin/test-admin-menu.php
ALL PASS
[exit 0]
```

The admin menu's suite passes before the change and after it: it is run to show the stand-ins it shares with the Overview's suite still serve it.

- [ ] **Step 3: The tile and the Overview's line**

```diff
diff --git a/includes/class-wpcpm-overview.php b/includes/class-wpcpm-overview.php
--- a/includes/class-wpcpm-overview.php
+++ b/includes/class-wpcpm-overview.php
@@ -67,8 +67,9 @@ final class WPCPM_Overview {
 	 * and to review at Semester reports, and the locked accounts at Accounts. The sponsors' counts
 	 * open the Sponsors screen the same way: the applications, the sponsor posts and the signed
 	 * agreements at Waiting for review, each at the card there that lists it, and the offers running
-	 * low at Offers and codes. Every count opens a wp-admin screen, so none of them depends on the
-	 * Administrator Dashboard's page being there.
+	 * low at Offers and codes. The mentor applications open the Mentors screen at Waiting for review
+	 * (1.122.31). Every count opens a wp-admin screen, so none of them depends on the Administrator
+	 * Dashboard's page being there.
 	 *
 	 * One map, so pointing a count at another screen, or at a tab of one, is an edit here and
 	 * nowhere else. Every tile `counts()` returns has a place in it, which bin/test-overview.php
@@ -97,6 +98,7 @@ final class WPCPM_Overview {
 			'sponsor_posts'        => $sponsor_queue . '#wpcpm-sponsor-posts',
 			'sponsor_agreements'   => $sponsor_queue . '#wpcpm-sponsor-agreements',
 			'sponsor_applications' => $sponsor_queue . '#wpcpm-sponsor-applications',
+			'mentor_applications'  => admin_url( 'admin.php?page=wpcpm-mentors&tab=queue' ),
 			'offers_low'           => $sponsor_offers,
 			'duplicates'           => admin_url( 'admin.php?page=wpcpm-tool-duplicate-finder' ),
 		);
diff --git a/includes/modules/class-wpcpm-administrators-cards.php b/includes/modules/class-wpcpm-administrators-cards.php
--- a/includes/modules/class-wpcpm-administrators-cards.php
+++ b/includes/modules/class-wpcpm-administrators-cards.php
@@ -160,6 +160,11 @@ final class WPCPM_Administrators_Cards {
 			'sponsor_applications' => $sponsor_applications,
 			// How many wait in each of the three, which its tile and its card print.
 			'sponsor_totals'       => self::sponsor_totals( $sponsor_posts, $sponsor_agreements['review'], $sponsor_applications ),
+			// How many mentor applications wait for a decision (1.122.31): the number only, read as
+			// IDs through the record class. They are decided on the Mentors screen's Waiting for
+			// review tab, which their tile opens, and on no card here (design of 16 October 2026,
+			// decision 9).
+			'mentor_applications'  => class_exists( 'WPCPM_Mentor_Application' ) ? WPCPM_Mentor_Application::waiting_count() : 0,
 			// Pools under their own threshold, what sponsors said lately, and the sponsors'
 			// figures (Sponsors module, S6): read through the owning classes, never their rows.
 			'offers_low'           => self::offers_low(),
@@ -176,7 +181,7 @@ final class WPCPM_Administrators_Cards {
 	}
 
 	/**
-	 * The thirteen tiles of the attention strip, from the arrays the cards draw.
+	 * The fourteen tiles of the attention strip, from the arrays the cards draw.
 	 *
 	 * `n` is a number on every tile, which a reader may compare with zero or with another. The three
 	 * sponsor queues' tiles say how many wait, as `collect()` read them apart from their cards' rows.
@@ -188,9 +193,13 @@ final class WPCPM_Administrators_Cards {
 	 * than the ceiling: a lower bound, not the count. A sum of tiles that holds one is a lower bound
 	 * too, and is not a number to print as a total.
 	 *
+	 * The mentor applications' tile is the one with no card on this page: they are decided on the
+	 * Mentors screen, so the tile carries `url`, the address of its Waiting for review tab, in place
+	 * of `card`.
+	 *
 	 * @param array $data What `collect()` returned.
-	 * @return array[] `label`, `n`, `card`, and `shown` on the sponsor posts' and agreements' tiles,
-	 *                 keyed in the strip's order.
+	 * @return array[] `label`, `n`, `card` (`url` on the mentor applications' tile), and `shown` on
+	 *                 the sponsor posts' and agreements' tiles, keyed in the strip's order.
 	 */
 	public static function counts( array $data ) {
 		$totals = array_merge(
@@ -263,6 +272,13 @@ final class WPCPM_Administrators_Cards {
 				'n'     => (int) $totals['applications'],
 				'card'  => 'sponsor-applications',
 			),
+			// Beside the other two kinds of application (1.122.31), opening the queue they are
+			// decided in, as no card here lists them.
+			'mentor_applications'  => array(
+				'label' => __( 'Mentor applications waiting', 'wpcredits-program-manager' ),
+				'n'     => isset( $data['mentor_applications'] ) ? (int) $data['mentor_applications'] : 0,
+				'url'   => admin_url( 'admin.php?page=wpcpm-mentors&tab=queue' ),
+			),
 			'offers_low'           => array(
 				'label' => __( 'Offers running low', 'wpcredits-program-manager' ),
 				'n'     => isset( $data['offers_low'] ) ? count( (array) $data['offers_low'] ) : 0,
@@ -1260,10 +1276,11 @@ final class WPCPM_Administrators_Cards {
 	 */
 
 	/**
-	 * The attention strip: thirteen counts, each an anchor to its card. A zero is muted, not
-	 * hidden, so the strip always has the same shape and a manager learns where to look. A tile
-	 * prints the words it carries for its number (`shown`, a sponsor posts or agreements count as
-	 * `WPCPM_Sponsors::queue_count()` prints it), or its number as it stands.
+	 * The attention strip: fourteen counts, each an anchor to its card, or, for the one tile with no
+	 * card here (`url`, the mentor applications'), a link to the screen it is decided on. A zero is
+	 * muted, not hidden, so the strip always has the same shape and a manager learns where to look.
+	 * A tile prints the words it carries for its number (`shown`, a sponsor posts or agreements count
+	 * as `WPCPM_Sponsors::queue_count()` prints it), or its number as it stands.
 	 *
 	 * @param array $counts What `counts()` returned.
 	 */
@@ -1273,9 +1290,9 @@ final class WPCPM_Administrators_Cards {
 
 		foreach ( $counts as $tile ) {
 			printf(
-				'<li class="wpcpm-attention__tile%1$s"><a class="wpcpm-attention__link" href="#wpcpm-%2$s"><span class="wpcpm-attention__n">%3$s</span><span class="wpcpm-attention__l">%4$s</span></a></li>',
+				'<li class="wpcpm-attention__tile%1$s"><a class="wpcpm-attention__link" href="%2$s"><span class="wpcpm-attention__n">%3$s</span><span class="wpcpm-attention__l">%4$s</span></a></li>',
 				0 === (int) $tile['n'] ? ' wpcpm-attention__tile--zero' : '',
-				esc_attr( $tile['card'] ),
+				isset( $tile['url'] ) ? esc_url( (string) $tile['url'] ) : '#wpcpm-' . esc_attr( $tile['card'] ),
 				esc_html( isset( $tile['shown'] ) ? (string) $tile['shown'] : number_format_i18n( (int) $tile['n'] ) ),
 				esc_html( $tile['label'] )
 			);
```

- [ ] **Step 4: Run them to verify they pass**

Run: the three suites of step 2.

```text
$ php bin/test-administrators-dashboard.php
ALL PASS (196 checks)
[exit 0]
$ php bin/test-overview.php
ALL PASS (32 checks)
[exit 0]
$ php bin/test-admin-menu.php
ALL PASS
[exit 0]
```

- [ ] **Step 5: The checks every task ends with**

```text
battery done
157 classes, 15078 references checked, 88 form handlers scanned - all resolve
160 PHP files, 8 blocks, 4 track seeds, 23 fixture and tooling files, the guides and the readme: US English throughout.
282 annotations, 0 dead.
Ran 136 scripts from bin/, each in a PHP process of its own, and every one exited 0.
The battery leaves the temp directory as it found it.
78 warnings, no errors.
```

- [ ] **Step 6: Commit**

```bash
git add includes/modules/class-wpcpm-administrators-cards.php includes/class-wpcpm-overview.php bin/test-administrators-dashboard.php bin/test-overview.php bin/stubs/overview-reads.php
git commit -m "Mentor applications: the Administrator Dashboard's Mentor applications waiting tile, beside the other two kinds of application, opening the Mentors screen's Waiting for review tab, and the Overview's line for it" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 9: The five emails, word for word, through the real senders

The emails recording suite of spec section 10, for M1's five: every email of seven cases, sent through the real senders (the form's submission, its confirming link followed as an applicant follows it, the queue's three decisions that email and its Put back in the queue, the notice through `WPCPM_Institutions::notify_managers()`) and the real mail layer (`WPCPM_Mail`, the template registry, the signature and footer), recorded once into `bin/fixtures/mentor-applications-1-122-31.json` and held to byte for byte from then on: who it went to, the id it was logged under, its subject, its body with the footer, its headers. M2, which adds the course's completion and the invitation's welcome beside these five, must change nothing their recipients read but what its suite lists, as `bin/test-invitation-text.php` lists the one change since its recording.

The seven cases: a good application with nobody named under **Who is mailed**; one with the setting naming an address with no account and an Administrator's; one the comment disallowed list holds, with no contact address set, then confirmed and selected (no notice, no Reply-To, no contact line in the footer, and the selection email without its line naming the program's address, its request for a reply kept); one asked for more information and then selected; one declined; one whose name was typed with quotes, an ampersand, a "<" and a letter past ASCII; and one the honeypot filed as spam, then put back in the queue, which is when its acknowledgement goes, through the form's own sender. Every decision is pressed with a note, which no email may carry. The recording writes every address "at example.test" and the signature of every confirming link as `EXAMPLE-NOT-A-REAL-SIGNATURE`, so `bin/test-fixtures.php` finds no address and no secret in it; what is sent now is written the same way before it is compared.

**Files:**
- Create: `bin/test-mentor-applications-text.php`
- Create, by the suite's `--record` run: `bin/fixtures/mentor-applications-1-122-31.json`

**Interfaces:**
- Consumes: everything of Tasks 1 to 5, through their handlers: `WPCPM_Mentor_Application_Form::handle_submit()` and `handle_verify()`, `WPCPM_Mentor_Application_Queue::handle_info()`, `handle_select()`, `handle_decline()` and `handle_reopen()`; `WPCPM_Mail::take_context()` and `take_test()`.
- Produces: the recording, whose sha256 is `44d03b315434a0d1ce1624fd159aa89fbdc63db5923e3584ba1539eec4fcbf3d`. The recording depends on nothing but the code and the suite (the clock is the stand-ins' fixed 16 October 2026), so at 1.122.31 a different hash means the code or the suite differs from what this plan was proved against. With another number (Global Constraints), the fixture and its comment are renamed to that number, the recording made, its seven cases read against Task 3's words, and the new hash pinned in the suite's check and in this line; the task stops only when the number is 1.122.31 and the hash differs.

- [ ] **Step 1: Write the suite**

Create `bin/test-mentor-applications-text.php`:

```php
<?php
/**
 * The mentor application emails, word for word: what each of the five says in 1.122.31, as
 * `wp_mail()` is handed it.
 *
 * The five are new in 1.122.31, written from the program's Airtable automations (design of 16
 * October 2026, section 2.1). M2 adds the course's completion and the activation beside them, and
 * must change nothing the recipients of these five read but what its suite lists, as
 * bin/test-invitation-text.php lists the one change since its recording. So this suite recorded,
 * through the real senders and the real mail layer, every email of every case below into
 * bin/fixtures/mentor-applications-1-122-31.json: who it went to, the id it was logged under, its
 * subject, its body with the signature and footer, and its headers. From then on it sends the same
 * cases and compares them with the recording, byte for byte.
 *
 * The real senders: the form's submission (`WPCPM_Mentor_Application_Form::handle_submit()`), the
 * confirming link it sends, followed as an applicant follows it (`handle_verify()`), the queue's
 * three decisions that email and its Put back in the queue, which sends the acknowledgement a row
 * never had (`WPCPM_Mentor_Application_Queue`), and the Administrators' notice through
 * `WPCPM_Institutions::notify_managers()`. The real mail layer: `WPCPM_Mail`, the template
 * registry and the signature and footer. Only WordPress, the settings and the Administrator
 * Dashboard's address are stood in.
 *
 * The cases: a good application with nobody named to hear of it, and one with the setting naming an
 * address with no account and an Administrator's; one the comment disallowed list holds, with no
 * contact address set, then selected, so the selection's line naming the program's address is left
 * out and its request for a reply stays; one asked for more information and then selected; one
 * declined; one whose name was typed with quotes, an ampersand, a "<" and a letter past ASCII; and
 * one the honeypot filed as spam, put back in the queue, which is when it is acknowledged. Every
 * decision is pressed with a note, which no email carries. The names are invented and every address is example.test's.
 * The recording writes every address "at example.test" and the signature of every confirming link
 * as a fake, so it holds no address and no secret; what is sent now is written the same way before
 * it is compared.
 *
 * The recording is made once: `--record` refuses when the file is there, and the suite pins the
 * file's sha256, so a recording made again from later code can never stand in for 1.122.31's.
 *
 * Run from the plugin root:  php bin/test-mentor-applications-text.php
 * Record (once, against 1.122.31):  php bin/test-mentor-applications-text.php --record
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/mentor-applications.php';

$GLOBALS['sent']       = array();
$GLOBALS['settings']   = array();
$GLOBALS['disallowed'] = false;

function get_bloginfo( $show = 'name' ) {
	return 'WordPress Credits';
}
function wp_check_comment_disallowed_list( $author, $email, $url, $comment, $ip, $agent ) {
	return (bool) $GLOBALS['disallowed'];
}
/** What the mail layer hands WordPress, kept in the order it was handed. */
function wp_mail( $to, $subject, $body, $headers = array(), $attachments = array() ) {
	$GLOBALS['sent'][] = array(
		'to'      => $to,
		'context' => WPCPM_Mail::take_context(),
		'subject' => $subject,
		'body'    => $body,
		'headers' => array_values( (array) $headers ),
		'files'   => count( (array) $attachments ),
	);

	WPCPM_Mail::take_test();

	return true;
}

/** The settings the senders read, as a case sets them. */
class WPCPM_Settings {
	public static function get() {
		return $GLOBALS['settings'];
	}
	public static function get_value( $key, $fallback = null ) {
		return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
	}
}

/** The Administrator Dashboard, published: the page an Administrator's footer names. */
class WPCPM_Administrators_Dashboard {
	public static function page_url() {
		return 'https://example.test/administrator-dashboard/';
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-guard.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-contribution-teams.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-capture.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/trait-wpcpm-accounts-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-countries.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-form.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-application-queue.php';

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

/** The note every decision is pressed with: the site's alone, which no email may carry. */
const NOTE = 'A note for the Administrators, NEVER-SENT, about this application.';

/**
 * The cases, each a world and what happens in it, in order: `apply` posts the form, its honeypot
 * filled where the case says so, `confirm` follows the confirming link of the acknowledgement just
 * sent, and `info`, `select`, `decline` and `reopen` press that decision on the one row, as the
 * first Administrator.
 *
 * @return array[]
 */
function cases() {
	return array(
		'applied'                        => array(
			'steps' => array( 'apply' ),
		),
		'applied-notice-to-the-list'     => array(
			'settings' => array( 'mentor_notify' => 'mentors-team@example.test, ida@example.test' ),
			'steps'    => array( 'apply' ),
		),
		'held-with-no-contact-address'   => array(
			'settings'   => array( 'mail_contact' => '' ),
			'disallowed' => true,
			'steps'      => array( 'apply', 'confirm', 'select' ),
		),
		'more-information-then-selected' => array(
			'steps' => array( 'apply', 'confirm', 'info', 'select' ),
		),
		'declined'                       => array(
			'steps' => array( 'apply', 'confirm', 'decline' ),
		),
		'a-name-as-typed'                => array(
			'answers' => array(
				'name'    => 'Zoë "Z" Example & Co <3',
				'profile' => '@zoe-example',
				'email'   => 'zoe@example.test',
			),
			'steps'   => array( 'apply', 'confirm', 'select' ),
		),
		'put-back-after-the-honeypot'    => array(
			'honeypot' => true,
			'steps'    => array( 'apply', 'reopen' ),
		),
	);
}

/** The answers a good application carries, as the form posts them. */
function answers( array $more = array() ) {
	return array_merge(
		array(
			'name'      => 'Ada Okonjo',
			'email'     => 'ada@example.test',
			'profile'   => 'https://profiles.wordpress.org/ada-example/',
			'country'   => 'Poland',
			'teacher'   => 'No',
			'languages' => array( 'Polish', 'English' ),
			'expertise' => array( 'Training', 'Polyglots' ),
			'hours'     => '3',
			'gpl'       => '1',
			'anything'  => 'I lead a meetup.',
			'consent'   => '1',
		),
		$more
	);
}

/** A dwell token half a minute old, signed the way the guard signs one for this form. */
function dwell_token() {
	$issued = time() - 30;
	$random = wp_generate_password( 12, false, false );

	return $issued . '.' . $random . '.' . substr( wp_hash( WPCPM_Mentor_Application_Form::DWELL_SCOPE . '|' . $issued . '|' . $random . '|' . wp_create_nonce( WPCPM_Mentor_Application_Form::ACTION_SUBMIT ) ), 0, 32 );
}

/**
 * A site where the form is open, with two Administrators, the course link set, and nothing sent.
 *
 * @param array $case The case.
 */
function fresh( array $case ) {
	$GLOBALS['opts']       = array( 'date_format' => 'F j, Y' );
	$GLOBALS['transients'] = array();
	$GLOBALS['posts']      = array();
	$GLOBALS['pmeta']      = array();
	$GLOBALS['users']      = array();
	$GLOBALS['umeta']      = array();
	$GLOBALS['slugs']      = array();
	$GLOBALS['sent']       = array();
	$GLOBALS['uid']        = 0;
	$GLOBALS['next_id']    = 500;
	$GLOBALS['policy']     = 'https://example.test/privacy/';
	$GLOBALS['disallowed'] = ! empty( $case['disallowed'] );
	$GLOBALS['settings']   = array_merge(
		array(
			'mentor_applications_enabled' => true,
			'application_trusted_proxy'   => '',
			'mail_contact'                => 'programs@example.test',
			'mentor_notify'               => '',
			'mentor_course_link'          => 'https://example.test/mentors-course/?invite=EXAMPLE-NOT-A-REAL-CODE',
			'checker_course_title'        => 'WordPress Credits Mentor\'s Course',
		),
		isset( $case['settings'] ) ? $case['settings'] : array()
	);

	$_POST                      = array();
	$_GET                       = array();
	$_REQUEST                   = array();
	$_SERVER['REMOTE_ADDR']     = '203.0.113.7';
	$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (test)';

	$page                 = new WP_Post();
	$page->ID             = 42;
	$page->post_type      = 'page';
	$page->post_status    = 'publish';
	$GLOBALS['posts'][42] = $page;
	$GLOBALS['slugs'][42] = 'become-a-mentor';
	$GLOBALS['opts'][ WPCPM_Mentor_Application_Form::OPT_PAGE ] = 42;

	$policy                    = new WP_Post();
	$policy->ID                = 43;
	$policy->post_type         = 'page';
	$policy->post_status       = 'publish';
	$policy->post_modified_gmt = '2026-08-20 11:30:00';
	$GLOBALS['posts'][43]                          = $policy;
	$GLOBALS['opts']['wp_page_for_privacy_policy'] = 43;

	$GLOBALS['opts'][ WPCPM_Countries::OPT_NAME ]       = array(
		'v'    => WPCPM_Countries::VERSION,
		'read' => 1,
		'rows' => array(
			'recCOUNTRY0000001' => array( 'name' => 'Poland', 'manager' => '', 'email' => '', 'calendly' => '' ),
		),
	);
	$GLOBALS['opts'][ WPCPM_Mentors_Sync::OPT_LOOKUPS ] = array(
		'v'     => WPCPM_Mentors_Sync::LOOKUPS_VERSION,
		'teams' => array(
			'recTEAM0000000001' => 'Polyglots',
			'recTEAM0000000003' => 'Training',
		),
	);

	an_account( 40, 'Ida Admin', 'ida@example.test', array( 'administrator' ) );
	an_account( 41, 'Ben Admin', 'ben@example.test', array( 'administrator' ) );
	$GLOBALS['manage'] = array( 40, 41 );
}

/**
 * One step of a case, as the request that takes it.
 *
 * @param string $step The step.
 * @param array  $case The case.
 */
function take_step( $step, array $case ) {
	if ( 'apply' === $step ) {
		$GLOBALS['uid'] = 0;
		$_POST          = array(
			'action'                                     => WPCPM_Mentor_Application_Form::ACTION_SUBMIT,
			'_wpnonce'                                   => wp_create_nonce( WPCPM_Mentor_Application_Form::ACTION_SUBMIT ),
			WPCPM_Mentor_Application_Form::FIELD_ANSWERS => wp_slash( answers( isset( $case['answers'] ) ? $case['answers'] : array() ) ),
			WPCPM_Mentor_Application_Form::TOKEN_FIELD   => dwell_token(),
			WPCPM_Mentor_Application_Form::HONEYPOT      => empty( $case['honeypot'] ) ? '' : 'https://spam.example.test/',
		);
		$_REQUEST       = $_POST;

		run( array( 'WPCPM_Mentor_Application_Form', 'handle_submit' ) );
		return;
	}

	if ( 'confirm' === $step ) {
		$link = '';

		foreach ( $GLOBALS['sent'] as $mail ) {
			if ( 'mentor-application-received' === $mail['context'] && preg_match( '#https://example\.test/wp-admin/admin-post\.php\?\S+#', $mail['body'], $found ) ) {
				$link = $found[0];
			}
		}

		parse_str( (string) wp_parse_url( $link, PHP_URL_QUERY ), $query );

		$GLOBALS['uid'] = 0;
		$_POST          = array();
		$_GET           = $query;
		$_REQUEST       = $query;

		run( array( 'WPCPM_Mentor_Application_Form', 'handle_verify' ) );
		return;
	}

	$decisions = array(
		'info'    => array( WPCPM_Mentor_Application_Queue::ACTION_INFO, 'handle_info' ),
		'select'  => array( WPCPM_Mentor_Application_Queue::ACTION_SELECT, 'handle_select' ),
		'decline' => array( WPCPM_Mentor_Application_Queue::ACTION_DECLINE, 'handle_decline' ),
		'reopen'  => array( WPCPM_Mentor_Application_Queue::ACTION_REOPEN, 'handle_reopen' ),
	);
	$rows      = WPCPM_Mentor_Application::applications( WPCPM_Mentor_Application::states() );
	$id        = isset( $rows[0] ) ? (int) $rows[0]->ID : 0;

	$GLOBALS['uid'] = 40;
	$_GET           = array();
	$_POST          = array(
		'action'                                          => $decisions[ $step ][0],
		'_wpnonce'                                        => wp_create_nonce( $decisions[ $step ][0] . '_' . $id ),
		WPCPM_Mentor_Application_Queue::FIELD_APPLICATION => (string) $id,
		WPCPM_Mentor_Application_Queue::FIELD_NOTE        => NOTE,
	);
	$_REQUEST       = $_POST;

	run( array( 'WPCPM_Mentor_Application_Queue', $decisions[ $step ][1] ) );
}

/**
 * Every email a case sends, in order, as the recording holds them.
 *
 * @param array $case The case.
 * @return array[]
 */
function emails_of( array $case ) {
	fresh( $case );

	foreach ( $case['steps'] as $step ) {
		take_step( $step, $case );
	}

	return array_map( 'recorded_form', $GLOBALS['sent'] );
}

/**
 * An email as the recording holds it: every address written "at example.test", and the signature
 * of a confirming link written as a fake.
 *
 * @param array $mail What `wp_mail()` was handed.
 * @return array
 */
function recorded_form( array $mail ) {
	$plain = static function ( $text ) {
		$text = preg_replace( '/([A-Za-z0-9._%+-]+)@example\.test/', '$1 at example.test', (string) $text );

		return preg_replace( '/([?&]t=)[A-Za-z0-9]+/', '$1EXAMPLE-NOT-A-REAL-SIGNATURE', $text );
	};

	$mail['to']      = $plain( $mail['to'] );
	$mail['subject'] = $plain( $mail['subject'] );
	$mail['body']    = $plain( $mail['body'] );
	$mail['headers'] = array_map( $plain, $mail['headers'] );

	return $mail;
}

$fixture = __DIR__ . '/fixtures/mentor-applications-1-122-31.json';

if ( in_array( '--record', $argv, true ) ) {
	// Made once, from 1.122.31: a recording made again would be made from whatever the code says
	// then, and would prove nothing.
	if ( file_exists( $fixture ) ) {
		fwrite( STDERR, "The recording exists and is never made twice.\n" );
		exit( 1 );
	}

	$recorded = array(
		'_comment' => 'What each Mentor applications email said in 1.122.31, recorded through the real senders and WPCPM_Mail by bin/test-mentor-applications-text.php --record. Invented names, every address written "at example.test", and the signature of every confirming link a fake. Never edit by hand: a later release is held to these bytes, but for the changes its suite lists.',
		'cases'    => array(),
	);

	foreach ( cases() as $name => $case ) {
		$recorded['cases'][ $name ] = emails_of( $case );
	}

	// A text JSON cannot hold stops the recording here, before a file is written.
	file_put_contents( $fixture, json_encode( $recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR ) . "\n" );
	printf( "Recorded %d cases, %d emails, in %s\n", count( $recorded['cases'] ), array_sum( array_map( 'count', $recorded['cases'] ) ), basename( $fixture ) );

	exit( 0 );
}

echo "=== The recording ===\n";

$recorded = is_file( $fixture ) ? json_decode( (string) file_get_contents( $fixture ), true ) : null;

ck( 'the recording is there, and holds every case', is_array( $recorded ) && isset( $recorded['cases'] ) ? array_keys( $recorded['cases'] ) : null, array_keys( cases() ) );
ck( 'the recording is the one made from 1.122.31', is_file( $fixture ) ? hash_file( 'sha256', $fixture ) : null, '44d03b315434a0d1ce1624fd159aa89fbdc63db5923e3584ba1539eec4fcbf3d' );

echo "\n=== Every email of every case, as recorded ===\n";

$as_sent = array();

foreach ( cases() as $name => $case ) {
	$want             = isset( $recorded['cases'][ $name ] ) ? $recorded['cases'][ $name ] : null;
	$got              = emails_of( $case );
	$as_sent[ $name ] = $got;

	ck( $name . ': the same emails, to the same people, in the same order', array_column( $got, 'to' ), null === $want ? null : array_column( $want, 'to' ) );

	foreach ( (array) $want as $n => $mail ) {
		$sent = isset( $got[ $n ] ) ? $got[ $n ] : array_fill_keys( array_keys( $mail ), null );

		ck( $name . ' #' . $n . ': logged under its own id', $sent['context'], $mail['context'] );
		ck( $name . ' #' . $n . ': the subject, byte for byte', $sent['subject'], $mail['subject'] );
		ck( $name . ' #' . $n . ': the body, byte for byte, the signature and footer included', $sent['body'], $mail['body'] );
		ck( $name . ' #' . $n . ': the headers, byte for byte, and no file attached', array( $sent['headers'], $sent['files'] ), array( $mail['headers'], 0 ) );
	}
}

echo "\n=== What the cases say, apart from the recording ===\n";

ck(
	'each case sends what its steps say: the acknowledgement for any row that is not spam, and for one put back in the queue that never had it, the notice for a new row alone, to each Administrator or to each address the setting names, and each decision\'s email',
	array_map(
		static function ( $mails ) {
			return array_map(
				static function ( $mail ) {
					return $mail['context'] . ' > ' . $mail['to'];
				},
				$mails
			);
		},
		$as_sent
	),
	array(
		'applied'                        => array( 'mentor-application-received > ada at example.test', 'mentor-application-notice > ida at example.test', 'mentor-application-notice > ben at example.test' ),
		'applied-notice-to-the-list'     => array( 'mentor-application-received > ada at example.test', 'mentor-application-notice > mentors-team at example.test', 'mentor-application-notice > ida at example.test' ),
		'held-with-no-contact-address'   => array( 'mentor-application-received > ada at example.test', 'mentor-selected > ada at example.test' ),
		'more-information-then-selected' => array( 'mentor-application-received > ada at example.test', 'mentor-application-notice > ida at example.test', 'mentor-application-notice > ben at example.test', 'mentor-more-information > ada at example.test', 'mentor-selected > ada at example.test' ),
		'declined'                       => array( 'mentor-application-received > ada at example.test', 'mentor-application-notice > ida at example.test', 'mentor-application-notice > ben at example.test', 'mentor-declined > ada at example.test' ),
		'a-name-as-typed'                => array( 'mentor-application-received > zoe at example.test', 'mentor-application-notice > ida at example.test', 'mentor-application-notice > ben at example.test', 'mentor-selected > zoe at example.test' ),
		'put-back-after-the-honeypot'    => array( 'mentor-application-received > ada at example.test' ),
	)
);

$noted = array();

foreach ( $as_sent as $name => $mails ) {
	foreach ( $mails as $mail ) {
		if ( false !== strpos( wp_json_encode( $mail ), 'NEVER-SENT' ) ) {
			$noted[] = $name . ': ' . $mail['context'];
		}
	}
}

ck( 'a decision\'s note is the site\'s alone: no email carries it', $noted, array() );

$text = is_file( $fixture ) ? (string) file_get_contents( $fixture ) : '';
preg_match_all( '/[?&]t=([A-Za-z0-9-]+)/', $text, $tokens );
ck(
	'and the recording holds no address, and no secret but a fake one',
	is_file( $fixture ) ? array( preg_match( '/[A-Za-z0-9._%+-]*@[A-Za-z0-9-]+\.[A-Za-z]{2,}/', $text ), array_values( array_unique( $tokens[1] ) ) ) : null,
	array( 0, array( 'EXAMPLE-NOT-A-REAL-SIGNATURE' ) )
);
ck( 'nothing was asked of WordPress that the stand-ins do not model', isset( $GLOBALS['unmodeled'] ) ? $GLOBALS['unmodeled'] : array(), array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run it to verify it fails, for want of a recording**

Run: `php bin/test-mentor-applications-text.php` and `php bin/test-fixtures.php`

```text
$ php bin/test-mentor-applications-text.php
FAIL the recording is there, and holds every case
FAIL the recording is the one made from 1.122.31
FAIL applied: the same emails, to the same people, in the same order
FAIL applied-notice-to-the-list: the same emails, to the same people, in the same order
FAIL held-with-no-contact-address: the same emails, to the same people, in the same order
FAIL more-information-then-selected: the same emails, to the same people, in the same order
FAIL declined: the same emails, to the same people, in the same order
FAIL a-name-as-typed: the same emails, to the same people, in the same order
FAIL put-back-after-the-honeypot: the same emails, to the same people, in the same order
FAIL and the recording holds no address, and no secret but a fake one
10 FAILURE(S) (13 checks)
[exit 1]
$ php bin/test-fixtures.php
ALL PASS
[exit 0]
```

The three checks that stand apart from the recording pass already: each case sends what its steps say, no email carries a note, and the stand-ins were asked nothing they do not model.

- [ ] **Step 3: Record, once**

Run: `php bin/test-mentor-applications-text.php --record`
Expected: `Recorded 7 cases, 22 emails, in mentor-applications-1-122-31.json`, and `shasum -a 256 bin/fixtures/mentor-applications-1-122-31.json` printing the hash above.

Run it again: `php bin/test-mentor-applications-text.php --record`
Expected: `The recording exists and is never made twice.`, exit 1, and the file's hash unchanged.

Read the recording before going on, each email against Task 3's words: it is the first time anybody sees the five emails whole, the signature and footer included, and what the owner and the mentor coordinator read in Tools > Emails before the form is switched on.

- [ ] **Step 4: Run it to verify it passes, and that the recording is a fixture the privacy walk accepts**

Run: `php bin/test-mentor-applications-text.php` and `php bin/test-fixtures.php`

```text
$ php bin/test-mentor-applications-text.php
ALL PASS (101 checks)
[exit 0]
$ php bin/test-fixtures.php
ALL PASS
[exit 0]
```

- [ ] **Step 5: The checks every task ends with**

```text
battery done
157 classes, 15078 references checked, 88 form handlers scanned - all resolve
160 PHP files, 8 blocks, 4 track seeds, 24 fixture and tooling files, the guides and the readme: US English throughout.
282 annotations, 0 dead.
Ran 137 scripts from bin/, each in a PHP process of its own, and every one exited 0.
The battery leaves the temp directory as it found it.
78 warnings, no errors.
```

- [ ] **Step 6: Commit**

```bash
git add bin/test-mentor-applications-text.php bin/fixtures/mentor-applications-1-122-31.json
git commit -m "Mentor applications: record what each of the five emails says in 1.122.31, through the real senders and the real mail layer, so a later release is held to it word for word" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 10: The guide, the version and the changelog

The Administrator guide says where applications to mentor are read and decided, the way it says it for the sponsor applications on their screen: a new subsection of the part on wp-admin, `### Applications to mentor` (a heading no other part has, so guide 31's `#mentor-applications`, which the Settings screen links to, keeps its id), the Mentors row of the screens table, the Overview's list of where each count opens, and, in the operations part, the strip's fourteen counts, what uninstalling removes and the three new options. The subsection names the form's eleven questions in their order, the page's introduction and thank-you sentence as the page's to edit, the five views and Recently decided, the seven decisions (Select on a declined row for an appeal, Put back in the queue on a spam one, which sends the acknowledgement to a row that never had it), the marks with **acknowledgement not sent**, which Put back in the queue clears, and that an application closed as spam for want of a confirmed address counts for nothing in the public figures; the changelog says the same in short. The student and mentor guides do not change. If 1.122.30, the institutions' resend-invitation release, has not merged into main when this task starts, say so and take the owner's word on which ships first before writing a version (Global Constraints).

**Files:**
- Modify: `docs/sections/30-admin-wpadmin.md`, `docs/sections/34-admin-operations.md`
- Modify: `wpcredits-program-manager.php` (`Version:`, `WPCPM_VERSION`), `readme.txt` (Stable tag, the Mentors line of the description, the changelog)
- Generated: `docs/administrators.md`, `docs/build/administrators.html` (`php bin/build-docs.php`), `languages/wpcredits-program-manager.pot` (`bash bin/make-pot.sh`)

**Interfaces:**
- Consumes: the words of Tasks 2 to 8 as the screens print them: the tab and view names, the seven decision buttons, the marks, the block's Thank you panel, the setting labels.
- Produces: version 1.122.31 in every place the release script checks.

- [ ] **Step 1: The guide**

```diff
diff --git a/docs/sections/30-admin-wpadmin.md b/docs/sections/30-admin-wpadmin.md
--- a/docs/sections/30-admin-wpadmin.md
+++ b/docs/sections/30-admin-wpadmin.md
@@ -46,7 +46,8 @@ and mentor requests open the **Waiting for review** tab of the **Institutions**
 reports to review and the semesters due for drafting its **Semester reports** tab, and the locked
 accounts its **Accounts** tab; the sponsor posts, agreements and applications open the
 **Waiting for review** tab of the **Sponsors** screen, each at the card that lists it, and the
-offers running low its **Offers and codes** tab; and the duplicated students open the
+offers running low its **Offers and codes** tab; the mentor applications open the
+**Waiting for review** tab of the **Mentors** screen; and the duplicated students open the
 **Student Duplicate Finder**. The dashboard's strip shows every count, a zero muted; this card
 leaves out a queue with nothing in it, and when nothing is waiting it says "Nothing is waiting for an
 Administrator right now." At the foot of the card, **Decide on the Administrator Dashboard** opens that
@@ -87,7 +88,7 @@ warning that says why, in the tool's own words.
 | Screen | What it does |
 | --- | --- |
 | **Students** | Two tabs: **Accounts**, the invitations and every Student account in one list, and **Sync**, the students sync and its last report. |
-| **Mentors** | Three tabs: **Accounts**, the invitations and every Mentor account in one list; **Engagement**, how each mentor uses the site; and **Sync**, the mentors sync and its last report. |
+| **Mentors** | Four tabs: **Waiting for review**, the applications to mentor that came through the form on the site, read and decided here, the tab the screen opens on while any wait; **Accounts**, the invitations and every Mentor account in one list; **Engagement**, how each mentor uses the site; and **Sync**, the mentors sync and its last report. |
 | **Institutions** | Six tabs: **Waiting for review**, the applications, signed agreements and requests from institutions waiting, read here and decided on the Administrator Dashboard; **Pipeline**, every institution record by stage and the consent report; **Accounts**, the invitations, every institution account in one list, the Confirmed institutions with no account yet, and each institution's members; **Semester reports**, every semester report and the semesters due for drafting; **Agreements**, the agreements to record as on file, the ones Airtable disagrees with, and the plugin's copy of the Collaboration Agreement; and **Sync and storage**, the institutions sync, the reconciliation of Students with Students Reports, and the check of how the host serves the private files. |
 | **Sponsors** | Six tabs: **Waiting for review**, the sponsor applications, sponsor posts and signed agreements waiting, read here and decided on the Administrator Dashboard; **Sponsors**, the sponsors sync and every sponsor with its status, program contact and accounts; **Accounts**, the invitations, every sponsor account in one list, the Approved sponsors with no account yet, and each sponsor's accounts; **Offers and codes**, every offer with its codes and claims; **Interests**, what sponsors said they would like to support; and **Agreements**, the state of every Approved sponsor's agreement. |
 | **Administrators** | Two tabs, under the button to the Administrator Dashboard while that page exists: **Accounts**, every administrator account in one list, and **Capabilities**, the program capabilities granted to Administrator. |
@@ -104,7 +105,7 @@ The **Student accounts** list works like WordPress's own **Users** screen: every
 
 Under each name are **Edit**, which opens the account in wp-admin, **View page**, which opens that student's Student Report Card, and **Send invite** or **Resend invite**, which emails that one student at once. To invite several, tick them, choose **Send invite** or **Resend invite** under **Bulk actions** and press **Apply**. On WordPress 6.7 or later, on this list and on every other accounts list, the action chosen under **Bulk actions** runs only when **Apply** is pressed: a search sent with rows ticked and an action chosen comes back to the list with nothing done to them, and Enter pressed in the menu does not apply it. Below 6.7, WordPress's own behavior stands. **Send invite** queues a first invitation for each ticked account that has never had one, as the card's button does, and the card shows the progress. **Resend invite** queues a fresh invitation for each ticked account invited before, which goes out with the next batch: the card's progress bar and its stop button follow a run of first invitations, and a re-invitation starts no run, so on its own it shows on neither. A row's invitation and the bulk actions both come back to the list as it was: the same view, search, institution, sort and page. However it is asked for, nobody is sent a second invitation within fifteen minutes of the last one: **Resend invite** on ticked accounts leaves out anybody invited in the last fifteen minutes, and its notice says how many it left out.
 
-In 1.119.0 the Mentors screen became two tabs in the same way, and a press on either tab comes back to it. **Accounts**, the tab **WPCredits Program > Mentors** opens on, holds the **Invitations** card and the **Mentor accounts** list. **Sync** holds the last sync's error when it ended in one, the warning while institution and team names have not been read yet, the **Airtable sync** card with **Sync mentors now**, or the run's progress and **Cancel sync** while one is going, and the **Last sync report**.
+In 1.119.0 the Mentors screen became two tabs in the same way, and a press on either tab comes back to it. **Accounts**, the tab **WPCredits Program > Mentors** opens on while no application to mentor waits (see *Applications to mentor*), holds the **Invitations** card and the **Mentor accounts** list. **Sync** holds the last sync's error when it ended in one, the warning while institution and team names have not been read yet, the **Airtable sync** card with **Sync mentors now**, or the run's progress and **Cancel sync** while one is going, and the **Last sync report**.
 
 Since 1.122.26 the Mentors screen has a third tab between them, **Engagement**. It opens with the figures the Administrator Dashboard's **Mentor engagement** card shows, then lists every mentor: **Status**, whether Airtable holds them as Active (and **Administrator** for an Administrator who also mentors), their **Students** now and before, their **Last visit** (the latest day they opened a page of the site while signed in, which the site notes once a day) and **Last sign-in**, their **Schedule** (weekly hours and a meeting link, or what is missing), the **Notes** they wrote, their one-to-one **Calls** held and to come with how many held calls have a note, and their **Group sessions** held and to come. The 7 days and the 30 days count whichever is later, a mentor's last sign-in or last visit, so a mentor on a remembered session is not shown as absent; a mentor for whom the site holds neither is counted as never signed in. Sort by **Mentor**, **Students**, **Last visit**, **Last sign-in**, **Notes** or **Calls**; the list opens with the most students first. **Search mentors** finds a mentor by name, username or email address. **Screen Options** sets how many rows a page holds, 20 by default, and which columns are shown; the **Mentor** column cannot be hidden. The views, each with its count, find who to follow up with: **All**, **Active**, **With students now**, **Never signed in**, **Not seen in 30 days**, **No program action** (no note, no saved schedule, no group session created and no call they canceled, including those who never signed in, so it can list more mentors than the card's count of those who signed in without one), **Students, no weekly hours** and **Administrators**. Every view but **All** and **Administrators** lists active mentors only, those Airtable holds as Active, and so do the figures above, except the call and group session figures, which count every call and group session on the site; an Administrator who also mentors is counted apart, and **Administrators** lists the active ones. **All** lists every mentor, active or not. The tab reads only. Sign-ins from before the release were read once from what the site already held and are approximate, and visits start with the release, so a date can read **Not recorded yet**; the note under the list gives the date of the reading. The site records the last sign-in and last visit of every account, not only mentors', and uninstalling the plugin removes them. `wp wpcredits mentor_engagement --summary` prints the same figures on the command line, and without `--summary` it prints one row per mentor, as a table, JSON or CSV; it writes nothing.
 
@@ -116,6 +117,102 @@ Since 1.119.0 the Administrators screen is two tabs too, **Accounts** and **Capa
 
 The list has one view, **All**, and no checkboxes, bulk actions or invitations: Administrators use WordPress's own Administrator role, the plugin never invites them, and their accounts are added and managed on the **Users** screen. **Capabilities** lists the program capabilities the Administrator role is granted on activation, as the screen did before.
 
+### Applications to mentor
+
+Since 1.122.31 a person can apply to mentor on this site, at /become-a-mentor/, and an Administrator
+decides on each application on the **Waiting for review** tab of the **Mentors** screen. The form is
+off until **Take mentor applications through the form on this site** is switched on, under
+**Mentor applications** on the Students and mentors tab of Settings. While it is off, or while the
+site has no published privacy policy or the syncs have read no countries or no contribution teams
+yet, the page shows the public nothing, and an Administrator who opens it one sentence saying why.
+The page is not gated: the people it is for have no account. Nothing is written to
+Airtable in this release: an application stops at the mentors' course, and somebody who completes
+it still becomes a mentor in Airtable, as before.
+
+The page the plugin makes starts with the Airtable form's introduction, two paragraphs above the
+form, and they are the page's to edit. So is the sentence an applicant reads once the form is sent:
+**Thank-you sentence**, in the **Thank you** panel of the **Mentor Application Form** block (or
+`thanks="..."` on the `[wpcpm_mentor_application]` shortcode), and while it is empty the plugin's
+own sentence is shown. A page already at /become-a-mentor/ when the plugin first looks is used as
+it is, so the form shows on it only once the block is added.
+
+The form asks the eleven questions the Airtable form asks, in its order: the applicant's full name;
+their email; their WordPress.org profile (the username, an @username or the address of their
+profile on wordpress.org, kept as the username, and anything else refused with a sentence asking for
+one of those); their country (one field: type a few letters and pick one); whether they are a
+teacher at an institution taking part in the program; the languages they can mentor in; the
+contribution areas they have expertise in, which may be left empty; the hours a week they can give,
+from 1 to 40, kept to one decimal place; the compliance declaration, the GPL and trademark statement
+with its two links, which must be ticked; anything else they would like us to know; and the privacy
+policy confirmation. An answer to **Anything else you'd like us to know?** holding something the
+site would strip, such as a tag, is returned to the applicant with the sentence "Please write
+without angle brackets or tags." The countries are the ones the institution form offers and the contribution areas the teams a student picks from, both
+read by the syncs; the languages are a fixed list in the plugin, since the site holds none. It is
+guarded as the sponsor form is: a hidden field, a signed token, five submissions an hour per address
+and forty a day for the whole site before the rest are held, consent as a precondition with what was
+agreed to recorded, the comment disallowed list and the links counted, and a ceiling on
+acknowledgements. An application giving the address or the WordPress.org username of another
+application in the queue or waiting for the course, or of a mentor the site knows, is held with a
+mark and never merged. The thank-you page shows the application's reference (MAPP-2026-0007), and
+every application but one filed as spam is acknowledged by email with a link that confirms the
+address; the addresses under **Who is mailed**, or every Administrator, are told of each one the
+checks did not hold, with the applicant's name and profile and the way to the tab.
+
+**WPCredits Program > Mentors** opens on **Waiting for review** while any application waits there,
+and on **Accounts** while none does; the tab is first in the bar either way. It lists the
+applications oldest first, fifty at a time, in five views, each with its count: **Waiting**, every
+application not decided yet; **Asked for more information** and **Second opinion**, those two kinds
+among them; **Selected (course pending)**, the applicants sent to the mentors' course; and
+**Lapsed**. Under the list, in every view, **Recently decided** lists the fifty applications most
+recently declined or filed as spam, newest first, until they are deleted. Each application shows
+the name and reference, how long it has waited (for a selected one, the day it was selected and the
+day it lapses; for a decided one, the day it was declined or filed as spam), its marks (**held**,
+**possible duplicate**, **already a mentor**, **address not confirmed** and **acknowledgement not
+sent**), every answer, its history (each decision with its day, who took it and their note) and the
+decisions its state allows. The Administrator Dashboard's strip counts the applications waiting, and
+its tile opens this tab.
+
+The decisions, each with a box for a note where it takes one; a note stays on this site and is
+never sent:
+
+- **Select** sends the selection email, with the link set under **Mentors' course invitation link**,
+  and moves the application to **Selected (course pending)**. It is refused while that setting is
+  empty, and it is offered on a lapsed application too, for an applicant who comes back, and on a
+  declined one, for an applicant who appeals: an appeal is either a new application, if you ask
+  them to apply again, or a note and Select on the same row.
+- **Ask for more information** sends the request for more information; the application stays in the
+  queue, under **Asked for more information**, with the day it was asked.
+- **Second opinion** marks the application for a colleague and sends nothing. Its note, which says
+  what to look at, is needed.
+- **Decline** sends the decline email and moves the application to **Recently decided**.
+- **Mark as spam** moves the application to **Recently decided** and sends nothing.
+- **Put back in the queue**, on an application filed as spam, returns it to **Waiting**, with a line
+  in its history. One that was never acknowledged, which the form's checks filed as spam, is sent
+  the acknowledgement then, once, with the link that confirms the address; one acknowledged when it
+  came, marked as spam by mistake, is sent nothing.
+- **Withdraw**, on a selected application alone, moves it to **Lapsed** now and sends nothing. Its
+  note, what the applicant wrote, is needed, and the public figures count it as lapsed.
+
+Select, Ask for more information and Decline wait until the applicant has confirmed the address with
+the link in the acknowledgement: until then each is refused and says so. An application whose
+address is never confirmed is closed with Mark as spam, and a spam application counts for nothing in
+the public figures, so that applicant is not counted as having applied. An application marked
+**acknowledgement not sent**, one the form's checks filed as spam or one past the day's ceiling on
+acknowledgements, was never sent the link, so its address cannot be confirmed and the three
+decisions that email the applicant stay refused until it is: Put back in the queue sends it to an
+application filed as spam, and the mark goes. Select, Decline, Mark as spam and Withdraw ask before
+they act. The five emails are the **Mentor applications** area of **Tools > Emails**, where their
+words can be edited.
+
+A selected application whose course has not shown after the days under **Selected applications
+lapse after (days)**, 90 by default, moves to **Lapsed** by itself, with a line in its history, in
+the daily run that also keeps the days under **Keeping applications** on the Advanced tab: spam is
+deleted after 30 days, and declined, lapsed and held applications after a year, by default. The
+figures for the Credits Monthly Report and the WPCredits Tracker are public at
+`/wp-json/wpcpm/v1/mentor-applications`: for each month since the first application, how many people
+applied, were selected, became mentors, were declined and lapsed, and how many applications are open
+and how many selected today, with nothing about any person.
+
 ### Institutions
 
 The Institutions screen is six tabs, and a press on any of them comes back to it, with what came of it said above the tabs. **Waiting for review**, the tab **WPCredits Program > Institutions** opens on, holds the queue; the others are **Pipeline**, **Accounts**, **Semester reports**, **Agreements** and **Sync and storage**. While Airtable is not connected, every tab says so above the tabs, with the way to the setting. The menu entry carries a bubble counting the applications, the signed agreements and the requests from institutions waiting.
diff --git a/docs/sections/34-admin-operations.md b/docs/sections/34-admin-operations.md
--- a/docs/sections/34-admin-operations.md
+++ b/docs/sections/34-admin-operations.md
@@ -62,8 +62,9 @@ is not there.
 
 Uninstall removes the settings, the sync state, the access-level meta, the last sign-in and last visit the site records for every account, and the custom roles, and moves
 affected accounts back to Subscriber; it also deletes the sponsors' offers, pools and locks, and the
-claims meta, and the sponsor applications with the logo files nobody approved. **Accounts are never
-deleted**, and their program details in Airtable are untouched.
+claims meta, and the sponsor applications with the logo files nobody approved, and the mentor
+applications with what deleted ones counted for the public feed of their figures. **Accounts are
+never deleted**, and their program details in Airtable are untouched.
 
 ### When something is wrong
 
@@ -85,7 +86,7 @@ Review a draft on the Institution Dashboard, reached through the switcher from t
 
 ## The Administrator Dashboard
 
-The Administrator Dashboard at /administrator-dashboard/ is the page to start the day on. It is gated to Administrators and shows, in this order: a strip of thirteen counts (institution applications waiting, agreements to review and overdue, reports to review, semesters due for drafting, mentor requests open and overdue, locked accounts, sponsor posts to review, sponsor agreements to review, sponsor applications waiting, offers running low, duplicated students), then one card each for institution applications, Collaboration Agreements, semester reports, mentor requests, sponsor applications, sponsor posts, sponsor Collaboration Agreements, offers running low, new interests, the sponsors' figures, the programs running, the mentor engagement and the syncs' health. A decision on the page that a wp-admin screen offers too is the same decision there, posted to the same handler with the same safeguards, and every decision lands back on the page (Draft now opens the new draft in the editor, and a refusal comes back to the page). The decisions on an open institution application, a signed agreement and a mentor request are made on this page: the Institutions screen's Waiting for review tab lists them to be read, each with a link to its card here, and keeps the record-keeping on a closed application and the four decisions on an open application past the fifty oldest this page's card lists, which no card here reaches. The sponsor queues are decided here the same way: an open sponsor application; a sponsor post, published with **Publish** or returned with **Return it with this note**, under **Return with a note**; and a sponsor's signed agreement, accepted with **Accept it** or returned with **Return it with this note**, while an agreement out of force is put back with **Reinstate**, under **Out of force** in the same card. **Accept it** and **Reinstate** ask before they act, since each writes Airtable and emails everybody at the company. The Sponsors screen lists them to be read on its **Waiting for review** tab, each application, post and document with a link to its own item here, and keeps the record-keeping on a decided application and every decision on an open one past the fifty oldest this page's card lists; its **Agreements** tab reinstates an agreement past the fifty oldest out of force that this page lists. The sponsor applications, sponsor posts and sponsor Collaboration Agreements cards each list the fifty oldest in their queue and, while more are waiting, say how many more under the list; each one's count, and its tile in the strip, are everything waiting in its queue, the number the Sponsors screen shows for it: the applications in full, at any size, and the posts and the agreements up to 200, past which they read 200+, as their cards on the Sponsors screen do. Each sponsor card ends with **Open on the Sponsors screen**, the way to the tab that holds the card's items, as each institution card ends with **Open on the Institutions screen**. What the page does not do: run a sync, change a setting, approve a semester report (that happens in the editor, where you have read it) or provision accounts; those stay on the wp-admin screens the Syncs card links to.
+The Administrator Dashboard at /administrator-dashboard/ is the page to start the day on. It is gated to Administrators and shows, in this order: a strip of fourteen counts (institution applications waiting, agreements to review and overdue, reports to review, semesters due for drafting, mentor requests open and overdue, locked accounts, sponsor posts to review, sponsor agreements to review, sponsor applications waiting, mentor applications waiting, offers running low, duplicated students), then one card each for institution applications, Collaboration Agreements, semester reports, mentor requests, sponsor applications, sponsor posts, sponsor Collaboration Agreements, offers running low, new interests, the sponsors' figures, the programs running, the mentor engagement and the syncs' health. The mentor applications waiting (since 1.122.31) have no card here: they are decided on the Mentors screen, and their tile opens its **Waiting for review** tab. A decision on the page that a wp-admin screen offers too is the same decision there, posted to the same handler with the same safeguards, and every decision lands back on the page (Draft now opens the new draft in the editor, and a refusal comes back to the page). The decisions on an open institution application, a signed agreement and a mentor request are made on this page: the Institutions screen's Waiting for review tab lists them to be read, each with a link to its card here, and keeps the record-keeping on a closed application and the four decisions on an open application past the fifty oldest this page's card lists, which no card here reaches. The sponsor queues are decided here the same way: an open sponsor application; a sponsor post, published with **Publish** or returned with **Return it with this note**, under **Return with a note**; and a sponsor's signed agreement, accepted with **Accept it** or returned with **Return it with this note**, while an agreement out of force is put back with **Reinstate**, under **Out of force** in the same card. **Accept it** and **Reinstate** ask before they act, since each writes Airtable and emails everybody at the company. The Sponsors screen lists them to be read on its **Waiting for review** tab, each application, post and document with a link to its own item here, and keeps the record-keeping on a decided application and every decision on an open one past the fifty oldest this page's card lists; its **Agreements** tab reinstates an agreement past the fifty oldest out of force that this page lists. The sponsor applications, sponsor posts and sponsor Collaboration Agreements cards each list the fifty oldest in their queue and, while more are waiting, say how many more under the list; each one's count, and its tile in the strip, are everything waiting in its queue, the number the Sponsors screen shows for it: the applications in full, at any size, and the posts and the agreements up to 200, past which they read 200+, as their cards on the Sponsors screen do. Each sponsor card ends with **Open on the Sponsors screen**, the way to the tab that holds the card's items, as each institution card ends with **Open on the Institutions screen**. What the page does not do: run a sync, change a setting, approve a semester report (that happens in the editor, where you have read it) or provision accounts; those stay on the wp-admin screens the Syncs card links to.
 
 The programs card counts students in progress per track and per institution from the roster index, so its numbers are as old as the last students sync; the read time is printed under the table. "Finished this semester" counts graduates only, not everyone who left the program; a graduate's row no longer says which track they were on, so it is one number rather than one per track. "Signed up this semester" per track counts the students who started in the semester and are still on that track; a student who started and has since paused, graduated or left is in the semester's finished count or in no count. Mentors are counted by distinct name, not by their Airtable record, because a roster row carries no mentor record ID: two mentors who share a name count once.
 
@@ -151,6 +152,9 @@ renaming a stored key is a migration, and nothing here warranted one.
 | `wpcpm_institutions_lock` | `WPCPM_Institutions_Sync::OPT_LOCK` |
 | `wpcpm_institutions_report` | `WPCPM_Institutions_Sync::OPT_REPORT` |
 | `wpcpm_institutions_state` | `WPCPM_Institutions_Sync::OPT_STATE` |
+| `wpcpm_mentor_application_counts` | `WPCPM_Mentor_Application::OPT_KEPT` |
+| `wpcpm_mentor_application_log` | `WPCPM_Mentor_Application::OPT_LOG` |
+| `wpcpm_mentor_application_page_id` | `WPCPM_Mentor_Application_Form::OPT_PAGE` |
 | `wpcpm_mentor_page_id` | `WPCPM_Mentors_Dashboard::OPT_PAGE` |
 | `wpcpm_mentor_page_title_fixed` | `WPCPM_Mentors_Dashboard::OPT_TITLE_FIXED` |
 | `wpcpm_mentors_field_meta` | `WPCPM_Mentors_Sync::OPT_FIELDS` |
```

Then build it and check the spelling: `php bin/build-docs.php && php bin/check-spelling.php | tail -1`.

- [ ] **Step 2: The version and the changelog**

The places the version is written, read with:

```bash
grep -n "Version:\|define( 'WPCPM_VERSION'" wpcredits-program-manager.php
grep -n "^Stable tag:\|^= 1.122.3[01] =\|^= 1.122.29 =" readme.txt
grep -n "Project-Id-Version" languages/wpcredits-program-manager.pot
```

Before, as proved on 7b790c0, where 1.122.30 had not merged yet (once it has, these read 1.122.30 and its changelog entry is listed above 1.122.29's; this release's entry then goes above it):

```text
6: * Version:           1.122.29
22:define( 'WPCPM_VERSION', '1.122.29' );
7:Stable tag: 1.122.29
295:= 1.122.29 =
5:"Project-Id-Version: WPCredits Program Manager 1.122.29\n"
```

```diff
diff --git a/readme.txt b/readme.txt
--- a/readme.txt
+++ b/readme.txt
@@ -4,7 +4,7 @@ Tags: airtable, members, roles, education, wordpress-credits
 Requires at least: 6.5
 Tested up to: 7.1
 Requires PHP: 7.4
-Stable tag: 1.122.29
+Stable tag: 1.122.31
 License: GPL-2.0-or-later
 License URI: https://www.gnu.org/licenses/gpl-2.0.html
 
@@ -15,7 +15,7 @@ Runs the WPCredits program on WordPress for five audiences - Students, Mentors,
 The plugin is organized around five audiences:
 
 1. **Students** - the Student role, Airtable account provisioning, and the Student Report Card, a private page with each student's program details and their assigned mentor. **Built.**
-2. **Mentors** - the Mentor role, Airtable account provisioning, and the Mentor Report Card, a private page listing each mentor's assigned students. **Built.**
+2. **Mentors** - the Mentor role, Airtable account provisioning, the Mentor Report Card, a private page listing each mentor's assigned students, and the public mentor application form with its review queue on the Mentors screen. **Built.**
 3. **Institutions** - the Institution role, the Airtable sync of the institution records, account creation, the Institution Dashboard with its roster, student imports and semester reports, the public institution application form, the Collaboration Agreement, and the institution queues on the Administrator Dashboard. **Built.**
 4. **Sponsors** - the Sponsor role, the Airtable sync of the Sponsors table, account creation, the Sponsor Dashboard, the public sponsor application form, the sponsors' guide, and the sponsor queues on the Administrator Dashboard. **Built.**
 5. **Administrators** - the people who manage the program: the built-in WordPress Administrator role, granted the program capabilities.
@@ -292,6 +292,17 @@ No. Uninstall removes settings, sync state, access-level meta, the last sign-in
 
 == Changelog ==
 
+= 1.122.31 =
+
+* People can apply to mentor on this site, at /become-a-mentor/, with the eleven questions the Airtable form asks, in its order: full name, email, WordPress.org profile, country, whether they are a teacher at a participating institution, the languages they can mentor in, their contribution areas, the hours a week they can give, the compliance declaration, anything else, and the privacy policy. The form is off until Take mentor applications through the form on this site is switched on, under Mentor applications on the Students and mentors tab of Settings, and it is guarded as the sponsor form is. Each applicant sees a reference on the thank-you page and is emailed an acknowledgement with a link that confirms their address, and the addresses under Who is mailed, or every Administrator, are told of each new application.
+* The page the plugin makes at /become-a-mentor/ starts with the Airtable form's introduction, which is the page's to edit, and the Mentor Application Form block takes the thank-you sentence an applicant reads once the form is sent, in its Thank you panel; the shortcode takes it as thanks. Without one, the plugin's sentence is shown.
+* The Mentors screen has a new first tab, Waiting for review, where each application is read whole and decided: Select, which sends the link set under Mentors' course invitation link, Ask for more information, Second opinion, Decline, Mark as spam, and Withdraw for a selected applicant. Under its five views, Recently decided lists the declined applications and those filed as spam, newest first: Select is offered on a declined one, for an applicant who appeals, and Put back in the queue returns one filed as spam to Waiting, sending the acknowledgement, with the link that confirms the address, to one that never had it. A decision that emails the applicant waits until they have confirmed their address. The screen opens on this tab while applications wait.
+* Nothing is written to Airtable yet. A selected applicant waits under Selected (course pending), and lapses when the course has not shown after the days under Selected applications lapse after (days), 90 by default. Somebody who completes the course still becomes a mentor in Airtable, as before.
+* The five emails are a new Mentor applications area of Tools > Emails, where their words can be edited. The four to the applicant start with the words the program's Airtable automations send.
+* The Administrator Dashboard's strip has a Mentor applications waiting tile, which opens the tab, and the Overview lists the same count.
+* Decided mentor applications are deleted after the days under Keeping applications, as the other two forms' are: spam after 30 days, and declined, lapsed and held ones after a year, by default.
+* A public feed at /wp-json/wpcpm/v1/mentor-applications gives, for each month since the first application, how many people applied, were selected, became mentors, were declined and lapsed, and how many applications are open and how many selected today, with nothing about any person. The Credits Monthly Report and the WPCredits Tracker read it in releases of their own.
+
 = 1.122.29 =
 
 * A single-line answer box on the Student Report Card, such as Your Slack name, asks a phone for the plain keyboard. It asked for the web-address keyboard, which on iPhone has no space bar, because every single-line box shared its markup with the web-address boxes; those keep the web-address keyboard, since that is what their answer needs.
diff --git a/wpcredits-program-manager.php b/wpcredits-program-manager.php
--- a/wpcredits-program-manager.php
+++ b/wpcredits-program-manager.php
@@ -3,7 +3,7 @@
  * Plugin Name:       WPCredits Program Manager
  * Plugin URI:        https://github.com/gomp/wpcredits-program-manager
  * Description:       Runs the WPCredits program on WordPress for five audiences - Students, Mentors, Institutions, Sponsors and Administrators - plus a Tools section. Provisions role-based accounts from Airtable, gives each mentor a Mentor Report Card listing the students assigned to them, and includes the Mentor Status Checker.
- * Version:           1.122.29
+ * Version:           1.122.31
  * Requires at least: 6.5
  * Requires PHP:      7.4
  * Author:            Maciej Pilarski
@@ -19,7 +19,7 @@ if ( ! defined( 'ABSPATH' ) ) {
 	exit; // No direct access.
 }
 
-define( 'WPCPM_VERSION', '1.122.29' );
+define( 'WPCPM_VERSION', '1.122.31' );
 define( 'WPCPM_PLUGIN_FILE', __FILE__ );
 define( 'WPCPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
 define( 'WPCPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
```

Then regenerate the translation template: `bash bin/make-pot.sh`. After:

```text
6: * Version:           1.122.31
22:define( 'WPCPM_VERSION', '1.122.31' );
7:Stable tag: 1.122.31
295:= 1.122.31 =
306:= 1.122.29 =
5:"Project-Id-Version: WPCredits Program Manager 1.122.31\n"
```

- [ ] **Step 3: The suites that read the guide, and the build left as committed**

Run: `php bin/test-admin-menu.php`, `php bin/test-settings.php`, `php bin/test-handbook.php` and `php bin/test-cli.php`

```text
$ php bin/test-admin-menu.php
ALL PASS
[exit 0]
$ php bin/test-settings.php
ALL PASS
[exit 0]
$ php bin/test-handbook.php
ALL PASS
[exit 0]
$ php bin/test-cli.php
ALL PASS (51 checks)
[exit 0]
```

Run `php bin/build-docs.php` once more and `git status --short`: nothing changes.

- [ ] **Step 4: The checks every task ends with**

```text
battery done
157 classes, 15078 references checked, 88 form handlers scanned - all resolve
160 PHP files, 8 blocks, 4 track seeds, 24 fixture and tooling files, the guides and the readme: US English throughout.
282 annotations, 0 dead.
Ran 137 scripts from bin/, each in a PHP process of its own, and every one exited 0.
The battery leaves the temp directory as it found it.
78 warnings, no errors.
```

- [ ] **Step 5: Commit**

```bash
git add docs/sections/30-admin-wpadmin.md docs/sections/34-admin-operations.md docs/administrators.md docs/build/administrators.html wpcredits-program-manager.php readme.txt languages/wpcredits-program-manager.pot
git commit -m "Mentor applications: the guide, the version and the changelog of 1.122.31" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 11: Try the form, the queue and the emails on the Docker copy

No repository files. Scratch notes and captures go in the session's scratchpad and are backed up under `.superpowers/session-backups/<the day's date, YYYY-MM-DD>/mentor-applications-m1/` (the scratchpad is swept after three days). The copy is closed to the outside: its mail is kept in `site/wp-content/local-safety-mail.log` and never leaves, and its cron is off, so the daily run is started by hand here. This is the implementer's trial; the mentor coordinator's, which spec section 12 puts on the copy and then live, is on the live site alone (see "After the plan"). The commands of this task and the next act on the Docker copy and the live site, which nothing in writing this plan touched; they are the release tooling's own, were not run while the plan was proved, and each expected line is the one its tool prints when it passes, read from the tool.

- [ ] **Step 1: Snapshot the copy, give it its rollback zip, and install the branch's build**

```bash
cd ~/Projects/wpcredits-docker
bin/snapshot before-rehearsal-1.122.31
bin/check | tail -1
bin/wp plugin get wpcredits-program-manager --field=version
```

Expected: `snapshots/before-rehearsal-1.122.31.tgz (<its size>)`, `All checks passed.`, and the version the copy runs. The copy's rollback zip is that version's zip in `release/`, taken from that version's release folder; for 1.122.29, the live version when this plan was written, and the same way for 1.122.30 from its own folder once it has one:

```bash
cp ~/GitHub/wpcredits-program-manager/.superpowers/session-backups/2026-10-16/release-plugin-1.122.29/wpcredits-program-manager-1.122.29.zip release/
```

(another version's zip is in its own `release-plugin-<version>` folder under `.superpowers/session-backups/`). Then the rehearsal the release script's README gives ("Rehearsing on the local copy"), each step that checks or builds naming the branch's commit, so the check and the build are of the same one:

```bash
cd ~/Projects/wpcredits-release
BRANCH="$(git -C ~/GitHub/wpcredits-program-manager rev-parse feature/mentor-applications-m1)"
python3 release.py check plugin --site local --commit "$BRANCH"
python3 release.py build plugin --commit "$BRANCH"
python3 release.py deploy plugin --site local
python3 release.py deploy plugin --site local --yes
~/Projects/wpcredits-docker/bin/wp plugin get wpcredits-program-manager --field=version
```

Expected, in order: `PASSED: check plugin 1.122.31 (<the branch's first twelve characters>) against live <the copy's version>.`, the copy's plugin folder said to equal its rollback zip; `PASSED: build plugin 1.122.31: <the release folder>/wpcredits-program-manager-1.122.31.zip and <your home>/GitHub/wpcredits-program-manager.zip`, its md5 on the line before; the deploy's plan, ending `NOT RUN: deploy installs on the site only with --yes, after the owner's yes for this release.` (exit 3); `PASSED: deploy plugin 1.122.31 on http://localhost:8900. Rollback, if a person judges it needed: ...`, naming `rollback-local.txt` in the release folder; and `1.122.31`.

- [ ] **Step 2: The page, the clock and what the form needs**

An update never fires the activation hook, so the page is made by hand, as the sponsor form's was in 1.97.0:

```bash
cd ~/Projects/wpcredits-docker
bin/wp eval 'echo WPCPM_Mentor_Application_Form::ensure_page(), "\n", WPCPM_Mentor_Application_Form::page_url(), "\n";'
bin/wp eval '$p = get_post( (int) get_option( WPCPM_Mentor_Application_Form::OPT_PAGE ) ); echo substr_count( $p->post_content, "<!-- wp:paragraph -->" ), " ", has_block( WPCPM_Mentor_Application_Form::BLOCK, $p ) ? "block" : "no block", "\n";'
bin/wp eval 'echo get_privacy_policy_url(), "\n", count( WPCPM_Mentor_Application_Form::countries() ), " ", count( WPCPM_Mentor_Application_Form::teams() ), "\n";'
```

Expected: a page ID and `http://localhost:8900/become-a-mentor/`; `2 block`, the form's introduction in two paragraphs above the block (a page the copy already held at the slug is adopted as it is and prints what it holds: note it for the owner, since the live site may hold one too); then the privacy policy's address and two counts above 0. An empty first line means the site has no published privacy policy: the form then shows the public nothing, on the copy and on the live site alike. On the copy only, publish a short test page and choose it under Settings > Privacy to go on; on the live site it is the owner's to decide (see "After the plan"). After the first page load, `bin/wp cron event list --hook=wpcpm_mentor_applications_daily --fields=hook,recurrence` lists the daily run.

- [ ] **Step 3: The settings for the trial**

```bash
bin/wp eval 'WPCPM_Settings::patch( array( "mentor_applications_enabled" => 1, "mentor_notify" => "notify@example.test", "mentor_course_link" => "https://example.test/mentors-course/?invite=EXAMPLE-NOT-A-REAL-CODE", "mail_contact" => "programs@example.test" ) ); echo "set\n";'
```

Expected: `set`.

- [ ] **Step 4: The thank-you sentence, then apply, as a stranger, and read what was sent**

Signed in as the copy's own Administrator, open **Become a mentor** in the editor, select the **Mentor Application Form** block, and in its **Thank you** panel type a sentence of your own in **Thank-you sentence**; update the page. Then, in the built-in browser, signed out, open `http://localhost:8900/become-a-mentor/`: the two paragraphs of the introduction, then the eleven questions in the Airtable form's order. Apply with invented answers (an example.test address, the username `ada-example`, 2.5 hours). Expected: the thank-you with your sentence in place of the plugin's, then the reference and the address. Then:

```bash
tail -n 120 site/wp-content/local-safety-mail.log
```

Expected: the acknowledgement to the address given, with `Reply-To: programs@example.test` and the confirming link, and the notice to `notify@example.test` naming the applicant and the profile, in the words Task 9 recorded. Follow the confirming link in the browser: "Thank you. Your address is confirmed, and your application is with the program's team."

- [ ] **Step 5: Decide, as an Administrator**

Open `http://localhost:8900/wp-admin/admin.php?page=wpcpm-mentors`. Expected: the screen opens on **Waiting for review**, the row whole with its eleven answers and its history (applied, confirmed the address), and **Recently decided** under the list saying no application has been filed as spam or declined. Press **Ask for more information** (the request in the mail log; the row under **Asked for more information** with the day), then **Select** (the selection email with the course link and the program's address; the row under **Selected (course pending)** with the day it lapses). Apply a second time from the same address, and follow the second acknowledgement's link from the mail log: the new row is **held**, marked **possible duplicate**; **Second opinion** on it refuses a note shorter than ten characters and keeps the typing, and takes a longer one; then **Decline** it (the decline email; the note never in it): it is listed under **Recently decided**, with **Select** offered on it. A third application, its link left unfollowed: **Select** is refused with the sentence about the confirming link; **Mark as spam** moves it to **Recently decided**, nothing sent; **Put back in the queue** returns it to **Waiting**, with a line in its history, and sends nothing, since it was acknowledged when it came (a row the form's checks filed as spam would be sent the acknowledgement it never had, as Task 9's case `put-back-after-the-honeypot` records); **Mark as spam** again. **Withdraw** the selected one: it moves to **Lapsed**, nothing sent, and **Select** is offered on it there.

- [ ] **Step 6: The tile, the Overview and the feed**

Open `http://localhost:8900/administrator-dashboard/`: the strip's **Mentor applications waiting** tile, after **Sponsor applications waiting**, counts the open applications and opens the tab. Compare its computed styles with the **Sponsor applications waiting** tile's (font, margin, padding, gap): every pair the same. The Overview's **Waiting for a decision** lists the same count. Then:

```bash
curl -s http://localhost:8900/wp-json/wpcpm/v1/mentor-applications
curl -s http://localhost:8900/wp-json/wpcpm/v1/mentor-applications | grep -c '@'
```

Expected: this month's received, selected, declined and lapsed as pressed (the application filed as spam counted nowhere, the withdrawal counted as lapsed), today's open and selected, and `0`.

- [ ] **Step 7: The clock, by hand**

Select the lapsed application again, then:

```bash
bin/wp eval 'echo WPCPM_Mentor_Application::lapse( time() + 91 * DAY_IN_SECONDS ), "\n";'
```

Expected: `1`, and the row under **Lapsed** with the site's dated line in its history.

- [ ] **Step 8: Screenshots, and put the copy back**

Screenshot, at 1280 and at 375 pixels wide: the form, which at 375 shows every label above its field, the country as one field, the languages and the contribution areas in one column of boxes, the declaration's two links, and nothing wider than the screen; the tab with a row open and **Recently decided** under the list; and the strip with the new tile, which at 375 wraps with the others, none cut. Then put the copy back the way the release script's README says, the copy's own plugin first, then the snapshot:

```bash
cat "$(ls -d ~/GitHub/wpcredits-program-manager/.superpowers/session-backups/*/release-plugin-1.122.31 | tail -1)/rollback-local.txt"
```

Run each command it lists, in its order (the install of the copy's own zip first, then the guide pages the rehearsal replaced), then:

```bash
cd ~/Projects/wpcredits-docker
bin/restore before-rehearsal-1.122.31
bin/wp plugin get wpcredits-program-manager --field=version
bin/check | tail -1
```

Expected: `Restored snapshots/before-rehearsal-1.122.31.tgz`, the version Step 1 read, and `All checks passed.`. Back up the notes and the screenshots in the folder above, and give the owner the results. `~/GitHub/wpcredits-program-manager.zip` is now the branch's build; Task 12 builds it again from main.

---

### Task 12: Release, only on the owner's go

The release script's own order, which every release of October 2026 ran: check, build, scan, the mirror's dry run and, on the owner's yes, the mirror; the deploy's dry run and, on the owner's yes, the deploy, which republishes the Administrator guide and purges the caches itself; then the page, the settings and the caches again, each on the owner's yes; then the vault and the memory note. The mirror goes first because `deploy --yes` refuses a release the public mirror does not hold yet. Every command runs from `~/Projects/wpcredits-release` unless it says otherwise.

- [ ] **Step 1: Read the live version**

```bash
ssh wpcredits-dashboard 'wp plugin get wpcredits-program-manager --field=version'
```

Expected: `1.122.30`. That number is the institutions' resend-invitation release's, which ships before this one, the owner's choice of 16 October 2026 (its plan is on main at 7b790c0). If the live version reads anything else, say what it reads and wait for the owner; if it has moved past 1.122.30, this release takes the next free number, as the Global Constraints and Task 9 say.

- [ ] **Step 2: Main holds the branch, on the owner's yes**

```bash
cd ~/GitHub/wpcredits-program-manager
git checkout main
git merge --ff-only feature/mentor-applications-m1
git log -1 --format='%h %s'
```

Expected: a fast-forward, and main's head Task 10's commit, `Mentor applications: the guide, the version and the changelog of 1.122.31`. If main moved since the branch was made, rebase the branch on it, run the checks every task ends with again and Task 9's suite (its pinned hash must still hold), and merge then.

- [ ] **Step 3: Check, build and scan**

```bash
cd ~/Projects/wpcredits-release
python3 release.py check plugin
python3 release.py build plugin
python3 release.py scan plugin
```

Expected, in order: the watch report of the public repository's changes since our last push, read before the owner's yes for the mirror, then `PASSED: check plugin 1.122.31 (<main's first twelve characters>) against live 1.122.30.`; `PASSED: build plugin 1.122.31: <the release folder>/wpcredits-program-manager-1.122.31.zip and <your home>/GitHub/wpcredits-program-manager.zip`, with the zip's md5 on the line before; `PASSED: scan plugin 1.122.31: no finding. mirror stages the same release again and checks it against this scan.` A change somebody else made in our folder on the mirror stops the scan: port it first (the release README, "Changes made on the mirror").

- [ ] **Step 4: The mirror's message, its dry run, and the owner's go**

Write the mirror commit's paragraph to `~/Projects/wpcredits-release/messages/1.122.31.txt`: one plain paragraph, no attribution and no names, since `mirror` scans it like an added line and refuses a Co-Authored-By, "Generated with", Claude or Anthropic in it. This one, unless the owner words it otherwise:

```text
People can apply to mentor on the site, at /become-a-mentor/, with the eleven questions the Airtable form asks, in its order; the form stays off until an Administrator switches it on. Administrators read and decide each application on a new first tab of the Mentors screen, Waiting for review: Select, Ask for more information, Second opinion, Decline, Mark as spam and Withdraw, with the applications recently declined or filed as spam listed under the views, where one filed as spam can be put back in the queue and a declined one selected after an appeal. The four emails to the applicant start with the words of the program's Airtable automations and are edited in Tools > Emails; a selected applicant lapses after 90 days by default, and nothing is written to Airtable yet. A public feed at /wp-json/wpcpm/v1/mentor-applications gives the counts by month, never a person.
```

Then:

```bash
python3 release.py mirror plugin --message messages/1.122.31.txt
```

Expected: what `--yes` would do, `the message passes its checks`, and `NOT RUN: mirror pushes to the public repository only with --yes, after the owner's yes for this release.` (exit 3). Give the owner: the version, the zip's md5, the battery and standards results, Task 11's screenshots and what its mail log held, check's watch report, the message, and the steps below. Wait for an explicit yes to mirror.

- [ ] **Step 5: Mirror, on the owner's yes**

```bash
python3 release.py mirror plugin --yes --message messages/1.122.31.txt
```

Expected: the watch report as of now, then `PASSED: mirror plugin 1.122.31: origin/trunk is now <the new commit>`.

- [ ] **Step 6: Deploy, on the owner's yes**

```bash
python3 release.py deploy plugin
```

Expected: the plan, ending `NOT RUN: deploy installs on the site only with --yes, after the owner's yes for this release.` (exit 3). Wait for an explicit yes to deploy, then:

```bash
python3 release.py deploy plugin --yes
```

Expected: the probes before and after, every difference printed for a person to judge; `== guides: building with --base=https://wordpresseducation.org/wp-content/uploads/2026/08, then comparing each page with its build`, page 560 `republished from administrators.html (...), title 'Administrator guide'` and pages 558 and 559 left as they are; `== caches: object cache flushed, edge cache purged`; and `PASSED: deploy plugin 1.122.31 on https://wordpresseducation.org. Rollback, if a person judges it needed: ...`, naming `rollback-live.txt` in the release folder. The guide page is republished and the caches purged by the deploy itself: nothing is published by hand.

- [ ] **Step 7: The page: a look first, then made on the owner's yes**

Read-only:

```bash
ssh wpcredits-dashboard 'wp post list --post_type=page --name=become-a-mentor --post_status=any --fields=ID,post_status,post_title'
```

Expected: no page listed. For a page that is listed, read whether it holds the form:

```bash
ssh wpcredits-dashboard 'wp post get <its ID> --field=post_content' | grep -c 'wp:wpcpm/mentor-application\|\[wpcpm_mentor_application'
```

A page without the block (`0`) would be adopted as it is, the form never added: report it to the owner, who decides, and stop here until they have. With no page, or the owner's decision made, and on the owner's yes:

```bash
ssh wpcredits-dashboard 'wp eval "echo WPCPM_Mentor_Application_Form::ensure_page(), PHP_EOL, WPCPM_Mentor_Application_Form::page_url(), PHP_EOL;"'
ssh wpcredits-dashboard 'wp post get <the ID it printed> --field=post_content' | grep -c 'wp:wpcpm/mentor-application'
ssh wpcredits-dashboard 'wp cron event list --hook=wpcpm_mentor_applications_daily --fields=hook,next_run_relative,recurrence'
```

Expected: a page ID and `https://wordpresseducation.org/become-a-mentor/`; `1`, a page whose content holds the block; and the daily run on the clock once the site has been loaded after the deploy. The page is public and shows the public nothing while the form is off.

- [ ] **Step 8: The settings, before anybody tries the form**

On the owner's yes for each: **Who is mailed** under **Mentor applications** set to the owner's one test address, so no test application writes to every Administrator; **Contact address in emails** (Tools > Emails, the Signature and footer tab) holding the program's address, the Reply-To of the four applicant emails and the address the selection email names; **Mentors' course invitation link** filled with the link the owner gives, never one taken from a document; **Take mentor applications through the form on this site** left off until the owner says the mentor coordinator's trial starts. The form shows nothing to the public without a published privacy policy page: say so if Task 11's step 2 found none.

- [ ] **Step 9: The caches again, on the owner's yes**

For the new page and the settings to show at once:

```bash
ssh wpcredits-dashboard 'wp cache flush'
echo y | ssh wpcredits-dashboard 'wp edge-cache purge --domain --yes'
curl -s -o /dev/null -w '%{http_code}\n' https://wordpresseducation.org/become-a-mentor/
```

Expected: `Success: The cache was flushed.`, the purge's own success line, and `200`.

- [ ] **Step 10: The vault and the memory note**

The spec's status line says "M1 shipped as 1.122.31 on <date>", and the plugin note and the memory note `wpcredits-program-manager-plugin` gain the release paragraph and the deploy runbook line (`WPCPM_Mentor_Application_Form::ensure_page()` after the update, the look at the slug first).

---

## After the plan

- **Before the form is switched on, for the mentor coordinator and the owner:** the eleven questions, their order, their labels and which are required. Task 4 asks what the Airtable form asks, as it was read on 16 October 2026 through the public link the handbook's Call for Mentors page gives (spec, section 2.3); the mentor coordinator confirms the eleven (spec, section 13, item 1). Also: the list of languages, fixed in code because the site holds none (the Airtable Languages table held both "Bangla" and "Bengali"; the form offers "Bengali" once); the four applicant emails' words, which are the program's Airtable automations' as they stood on 16 October 2026 (Mentors 1, 2, 5 and 4), with the site's signature and footer in place of the sign-offs and the confirming link's two lines added to the acknowledgement, read by the mentor coordinator in Tools > Emails and each sent as a test to the owner's address; and the page's title, its introduction (the Airtable form's, made by `ensure_page()` as two paragraphs above the block) and its thank-you sentence, all the owner's to edit on the page, the last in the block's **Thank you** panel (the block's `thanks` attribute, or `thanks="..."` on the shortcode), the plugin's own sentence shown while it is empty (spec, section 13, item 2).
- **The author's calls the review's rulings named:**
  - **Recently decided, and Put back in the queue.** Spec section 3, decision 3 lists only the open rows; the tab also lists the rows filed as spam and the declined ones, newest first, until retention deletes them, so that a genuine application the checks filed as spam, or a Mark as spam pressed by mistake, can be found: a seventh decision, `wpcpm_mapp_reopen`, returns a spam row to `new` with a line in its history. A declined row there offers Select with its note, spec section 5's second way of answering an appeal.
  - **Withdraw files the row as `lapsed`**, since the nine states have no `withdrawn`, so the feed's lapsed figure counts withdrawals with the clock's lapses.
  - **A mentor the last sync read from Airtable is matched by WordPress.org username alone**, since that list holds no address; spec section 4 says "address or handle". Accounts are matched by both, so only a mentor with no account on the site is affected (`known_mentor()`'s docblock says so).
  - **The trial on the copy is the implementer's** (Task 11), since the Docker copy is on the owner's machine and closed to anybody else; the mentor coordinator's trial, which spec section 12 puts on the copy and then live, is on the live site alone, with the notice pointed at the owner's test address.
- **The owner's rulings of 16 October 2026 on two calls the plan had left open:**
  - **The selection email reads whole with or without a contact address.** The automation's "please reach out to {contact} or reply to this email to let us know" is two lines: "When you finish the course, please reply to this email to let us know.", always printed, and "You can also write to {program_contact}.", which the registry leaves out while Contact address in emails is empty, so "Once we receive your message" always follows a complete request (Task 3; recorded in Task 9's case `held-with-no-contact-address`).
  - **Put back in the queue sends the acknowledgement a row never had.** A row the form's checks filed as spam, or one past the day's limit on acknowledgements, was never acknowledged, so its address could never be confirmed. Put back in the queue sends it the acknowledgement, once, with the link that confirms the address, through the form's own sender, and its history says "put back in the queue, acknowledgement sent"; a row acknowledged when it came, marked as spam by mistake, is sent nothing and its history says "put back in the queue". The mark **acknowledgement not sent** goes once it is sent (Task 5; recorded in Task 9's case `put-back-after-the-honeypot`). This replaces the earlier ruling that Put back in the queue sends nothing. The acknowledgement it sends does not count against the form's daily ceiling on acknowledgements, which guards the public form, not an Administrator's press.
- **The four applicant emails are typed as the applicant's**, not the mentor's: the person has no account until M2 activates them, so a test or a preview names no dashboard in their footer, and the Emails log lists them with the other two forms' applicants. The spec records it as the author's call (section 3, decision 8); M2 decides whether the invitation, the first email to the account, is the mentor's.
- **Calls made in planning that no ruling settles, for the owner to confirm or change:**
  - The profile is taken from a username or from an address on profiles.wordpress.org, wordpress.org or www.wordpress.org, the scheme optional, so `profiles.wordpress.org/ada-example` and a `wordpress.org/support/users/` address are kept too; the help text names the profile's address as https://profiles.wordpress.org/ followed by the username, since the privacy walk refuses a WordPress.org handle that is not synthetic, and "username" in the form's own example is one.
  - The free text alone refuses what the cleaning would lose (a tag, or a `%` code such as `%3C`); a name keeps the sponsor form's cleaning.
  - None of the four applicant emails carries the reference, as no automation did; the thank-you page shows it.
- **While M1 runs with the Airtable form still open**, an application selected on the site becomes a mentor only in Airtable, by hand, as before; nothing here marks the row active, so it lapses after the lapse days and the feed counts it as lapsed. Keep the form on the live site to the mentor coordinator's trial until M2.
- **M2** is planned on its own (spec, section 12): the Mentor Status Checker reading the `selected` rows, "The course is completed", the shared activation step, the invitation with the welcome, the write of the Mentors row, its retry and the Slack summary. The Credits Monthly Report's release that reads the feed is a release of that plugin.
- **Found while planning, not changed here:** the guide and the Settings screen say of the institution and sponsor forms that, while switched off, "the page shows one sentence saying applications are closed"; both forms show the public nothing and an Administrator one sentence, as this form does and as its own words now say. A one-line fix in each, for a release of its own.
