# Administrator Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A gated front-end page at `/administrator-dashboard/` where program managers see every queue with counts and act on it: institution applications, Collaboration Agreements, semester reports, mentor requests, the programs running, and the syncs' health.

**Architecture:** The Administrators module gains a front end built exactly like the Institution Dashboard: one `render()` that calls cards by name, each card reading through one static method on the class that owns the data and posting to the handler that already exists. A two-value `WPCPM_Return` allowlist tells the two handlers that still redirect to wp-admin to come back here. The theme gets a template, a page predicate and a body class so the dashboard skin loads.

**Tech Stack:** WordPress plugin PHP (7.4+, WP 6.5+), a block theme (theme.json v3); tests are the plugin's own `bin/test-*.php` suites run with `php`; `bin/check-references.php`, `bash bin/check-standards.sh`, `bash bin/build`; the theme's `php bin/check-selectors.php`.

**Spec:** `docs/specs/2026-09-04-administrator-dashboard-design.md` (its section 3.5 depends on `WPCPM_Semester_Report::queue()`, `due()`, `approved_since()` and `WPCPM_Semester_Report_Screen::render_draft_form()`, all shipped in 1.91.x).

## Global Constraints

- Ships as plugin **1.92.0** (`Version:` header and `WPCPM_VERSION` in `wpcredits-program-manager.php`, `Stable tag` and a `= 1.92.0 =` changelog entry in `readme.txt`, the new block's `block.json` and `editor.asset.php` at `1.92.0`) and theme **1.17.0** (`style.css` `Version:`, `readme.txt` `Stable tag` and a `= 1.17.0 =` entry).
- The page: slug `administrator-dashboard`, title `Administrator Dashboard`, block `wpcpm/administrator-dashboard`, shortcode `wpcpm_administrator_dashboard`, option `wpcpm_administrator_page_id`, gated to the `administrator` access level with `metadata_exists()` (never the meta value), and `render()` re-checks `current_user_can( WPCPM_Roles::CAP_MANAGE )` before drawing anything.
- Every card reads through one static method on the owning class and writes through the existing `admin_post_` handler with its existing nonce; the page adds no second way to decide anything.
- `WPCPM_Return` accepts exactly two values, `admin` and `dashboard`; a posted URL is never followed; the default is the handler's own screen.
- Every card is `<details class="wpcpm-group wpcpm-group__disclosure">` with `<summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Title <span class="wpcpm-group__count">N</span></h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>` and a `wpcpm-group__body`, `open` when its count is above zero; every form carries `data-wpcpm-once` and `data-wpcpm-busy`, and `render()` enqueues `assets/js/forms.js`.
- Every link from this page to an institution's own dashboard carries `WPCPM_Institution_Roster::ARG_VIEW` (`wpcpm_institution_view`).
- No em dashes or en dashes anywhere; comments explain why and name the decision behind a rule; text domain `wpcredits-program-manager`; full product names ("Administrator Dashboard", "Institution Dashboard", "Student Report Card", "Mentor Report Card", "the semester report", "the Collaboration Agreement"); constant prefixes `OPT_` / `META_` / `ACTION_` / `ARG_`; Yoda conditions, tabs, WordPress coding standard.
- Every behaviour has an assertion in a `bin/test-*.php` suite using `ck( $label, $actual, $expected )` with strict equality; suites are plain PHP run from the plugin root.
- The plugin directory is a git repository; work on branch `administrator-dashboard` off `main`; every task commits with a subject starting `Task N:` and the trailer `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`. The theme directory `~/GitHub/wpcredits-theme` is not a git repository; its task ends with the checks, not a commit.
- Every test send address is `maciej@a8c.com`.

## File Structure

| File | Responsibility |
| --- | --- |
| `includes/class-wpcpm-return.php` (new) | `WPCPM_Return`: the two-value allowlist for where a decision returns to, and the hidden fields a form prints |
| `includes/modules/class-wpcpm-administrators-cards.php` (new) | `WPCPM_Administrators_Cards`: reads every dataset once (`collect()`), the counts (`counts()`), the programs calculation (`programs()`), the health readout (`health()`), and one `render_*()` per card |
| `includes/modules/class-wpcpm-administrators-dashboard.php` (new) | `WPCPM_Administrators_Dashboard`: the page (ensure, gate, rename, block, shortcode, `page_url()`), the notice area, and the one `render()` that calls the cards in order |
| `blocks/administrator-dashboard/{block.json, editor.asset.php, editor.js}` (new) | the server-rendered block, a copy of the institution one |
| `assets/css/administrator.css` (new) | theme-agnostic base styles for the strip, the tables and the item rows |
| `includes/modules/class-wpcpm-administrators.php` | `boot()`, `activate()`, `uninstall()` and a link from the wp-admin screen |
| `includes/modules/class-wpcpm-sync-module.php`, `includes/modules/class-wpcpm-institution-request.php`, `includes/modules/class-wpcpm-institutions.php` | the two redirect branches; decision forms carry the return field and the double-submit guard; the application helpers the card reads become public |
| `includes/modules/class-wpcpm-institution-agreement.php`, `includes/modules/class-wpcpm-institution-request.php`, `includes/class-wpcpm-updates.php`, `includes/tools/class-wpcpm-handbook-assistant.php`, `includes/class-wpcpm-dashboards.php` | the readers and audience maps the page needs |
| `wpcredits-program-manager.php`, `uninstall.php` | the require lines |
| `wpcredits-theme/templates/page-administrator-dashboard.html`, `inc/template-tags.php`, `functions.php`, `assets/css/dashboard.css`, `readme.txt`, `style.css` | the template, the predicate, the body class, the skin rules, the version |
| `bin/test-return.php` (new), `bin/test-administrators-dashboard.php` (new), `bin/test-institution-agreement.php`, `bin/test-institution-request.php`, `bin/test-updates.php`, `bin/test-handbook.php` | the assertions |

---

### Task 1: `WPCPM_Return`, the two redirect branches, and decision forms that can come back

**Files:**
- Create: `includes/class-wpcpm-return.php`
- Modify: `includes/modules/class-wpcpm-sync-module.php:131-136` (`redirect_back()`), `includes/modules/class-wpcpm-institution-request.php:1157-1162` (`finish()`) and `:896-964` (`render_decisions()`), `includes/modules/class-wpcpm-institutions.php` (`render_decision_form()` at 2800, `render_application_actions()` at 2700, `render_application_answers()` at 2303, and the helper visibilities listed in Step 4), `wpcredits-program-manager.php:35-48` and `uninstall.php:24-109` (the require lines)
- Test: `bin/test-return.php` (new); run `bin/test-institutions-screen.php`, `bin/test-institution-request.php`, `bin/test-handlers.php`, `bin/test-institutions-sync.php`, `bin/test-roles.php`

**Interfaces:**
- Produces: `WPCPM_Return::FIELD = 'wpcpm_return'`, `WPCPM_Return::ANCHOR_FIELD = 'wpcpm_return_to'`, `WPCPM_Return::ADMIN = 'admin'`, `WPCPM_Return::DASHBOARD = 'dashboard'`, `WPCPM_Return::ANCHORS`, `WPCPM_Return::field( $where, $anchor = '' ): void` (prints hidden inputs only for `dashboard`), `WPCPM_Return::url( $default ): string`; `WPCPM_Institutions::render_application_actions( WP_Post $post, $state, $return = '' )` and `render_application_answers( WP_Post $post )` public instance methods; `WPCPM_Institutions::application_state()`, `application_reference()`, `application_email()`, `application_fields()`, `open_states()`, `reopen_states()`, `purgeable_states()`, `signals()`, `country_name()`, `queue_messages()` public static; `WPCPM_Institution_Request::render_decisions( $post_id, $return = '' )`.
- Consumes: `WPCPM_Request::posted_key( $name, $fallback = '' )` (sanitised key from `$_POST`); `WPCPM_Administrators_Dashboard::page_url()` (Task 4; guarded with `class_exists()` until then).

- [ ] **Step 1: Write the failing test**

Create `bin/test-return.php`:

```php
<?php
/**
 * Where a decision goes back to.
 *
 * What this pins: a posted URL is never followed; only `dashboard` reaches the Administrator
 * Dashboard, and only while that page exists; an anchor outside the known list is dropped;
 * `field()` prints nothing for the wp-admin default, so a form the queue draws is unchanged.
 *
 * Run from the plugin root:  php bin/test-return.php
 */
if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
function is_multisite() { return false; }

class WPCPM_Administrators_Dashboard {
	public static function page_url() { return (string) $GLOBALS['admin_page']; }
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';

$fail  = 0;
$total = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $total;
	$total++;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) { echo '       exp: ' . var_export( $expected, true ) . '  got: ' . var_export( $actual, true ) . "\n"; }
}
function printed( $where, $anchor = '' ) { ob_start(); WPCPM_Return::field( $where, $anchor ); return (string) ob_get_clean(); }

$default = 'https://example.test/wp-admin/admin.php?page=wpcpm-institutions';
$GLOBALS['admin_page'] = 'https://example.test/administrator-dashboard/';

$_POST = array();
ck( 'nothing posted is the default', WPCPM_Return::url( $default ), $default );
$_POST = array( 'wpcpm_return' => 'admin' );
ck( 'admin is the default', WPCPM_Return::url( $default ), $default );
$_POST = array( 'wpcpm_return' => 'https://evil.example/' );
ck( 'a URL is not a place', WPCPM_Return::url( $default ), $default );
$_POST = array( 'wpcpm_return' => 'dashboard' );
ck( 'dashboard is the Administrator Dashboard', WPCPM_Return::url( $default ), 'https://example.test/administrator-dashboard/' );
$_POST = array( 'wpcpm_return' => 'dashboard', 'wpcpm_return_to' => 'agreements' );
ck( 'a known anchor travels', WPCPM_Return::url( $default ), 'https://example.test/administrator-dashboard/#wpcpm-agreements' );
$_POST = array( 'wpcpm_return' => 'dashboard', 'wpcpm_return_to' => 'evil' );
ck( 'an unknown anchor is dropped', WPCPM_Return::url( $default ), 'https://example.test/administrator-dashboard/' );
$GLOBALS['admin_page'] = '';
$_POST = array( 'wpcpm_return' => 'dashboard' );
ck( 'no page is the default', WPCPM_Return::url( $default ), $default );
$_POST = array();

ck( 'the field prints nothing for the wp-admin default', printed( 'admin' ), '' );
ck( 'and nothing for an empty target', printed( '' ), '' );
$html = printed( 'dashboard', 'requests' );
ck( 'and both inputs for the dashboard', false !== strpos( $html, 'name="wpcpm_return" value="dashboard"' ) && false !== strpos( $html, 'name="wpcpm_return_to" value="requests"' ), true );
ck( 'an unknown anchor is not printed', false !== strpos( printed( 'dashboard', 'evil' ), 'wpcpm_return_to' ), false );
ck( 'the anchors are the six cards and the strip', WPCPM_Return::ANCHORS, array( 'attention', 'applications', 'agreements', 'reports', 'requests', 'programs', 'health' ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILED', $fail ) : 'ALL PASS', $total );
exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php bin/test-return.php 2>&1 | tail -3`
Expected: a fatal, `includes/class-wpcpm-return.php` does not exist.

- [ ] **Step 3: Write `WPCPM_Return`**

Create `includes/class-wpcpm-return.php`:

```php
<?php
/**
 * Where a decision goes back to: its wp-admin screen, or the Administrator Dashboard.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A two-value allowlist for the page a handler returns to.
 *
 * The Administrator Dashboard posts the wp-admin queue's own decisions, and each of those
 * handlers used to redirect to its screen. A posted URL is never followed: `admin` and
 * `dashboard` are the two places, each mapped to a URL this class builds, and anything
 * else is the handler's default. That is the rule `WPCPM_Request::is_explicit_redirect()`
 * set for the login redirect, kept here for forms (design of 4 September 2026, decision 3).
 */
final class WPCPM_Return {
	/** The posted field naming the place. */
	const FIELD = 'wpcpm_return';
	/** The posted field naming the card to land on. */
	const ANCHOR_FIELD = 'wpcpm_return_to';
	/** The handler's own wp-admin screen, which is also what a missing value means. */
	const ADMIN = 'admin';
	/** The Administrator Dashboard. */
	const DASHBOARD = 'dashboard';
	/** The ids the dashboard's sections carry, minus the `wpcpm-` prefix. */
	const ANCHORS = array( 'attention', 'applications', 'agreements', 'reports', 'requests', 'programs', 'health' );

	/**
	 * Print the hidden fields that bring a decision back to the dashboard.
	 *
	 * Nothing is printed for the wp-admin default, so a form the queue draws on its own
	 * screen is byte for byte what it was.
	 *
	 * @param string $where  ADMIN or DASHBOARD.
	 * @param string $anchor One of ANCHORS, or '' for the top of the page.
	 */
	public static function field( $where, $anchor = '' ) {
		if ( self::DASHBOARD !== $where ) {
			return;
		}

		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::FIELD ), esc_attr( self::DASHBOARD ) );

		if ( in_array( (string) $anchor, self::ANCHORS, true ) ) {
			printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::ANCHOR_FIELD ), esc_attr( $anchor ) );
		}
	}

	/**
	 * The URL a handler redirects to after its decision.
	 *
	 * @param string $default The handler's own screen.
	 * @return string
	 */
	public static function url( $default ) {
		$default = (string) $default;

		if ( self::DASHBOARD !== WPCPM_Request::posted_key( self::FIELD ) ) {
			return $default;
		}

		// The page may not exist: the module has not been activated, or the page was
		// deleted. The default is the screen the handler belongs to, never the front page.
		if ( ! class_exists( 'WPCPM_Administrators_Dashboard' ) ) {
			return $default;
		}

		$page = (string) WPCPM_Administrators_Dashboard::page_url();

		if ( '' === $page ) {
			return $default;
		}

		$anchor = WPCPM_Request::posted_key( self::ANCHOR_FIELD );

		return in_array( $anchor, self::ANCHORS, true ) ? $page . '#wpcpm-' . $anchor : $page;
	}
}
```

Add the require lines: in `wpcredits-program-manager.php` after the line that requires `includes/class-wpcpm-request.php`, add `require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';`; in `uninstall.php` add `require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-return.php';` at the same position in its list (`bin/test-roles.php` asserts the two lists match).

- [ ] **Step 4: Run the test, then wire the two redirects and the forms**

Run: `php bin/test-return.php 2>&1 | tail -1`
Expected: `ALL PASS (12 checks)`.

In `includes/modules/class-wpcpm-sync-module.php`, replace `redirect_back()`:

```php
	/**
	 * Back to the module's screen, with the outcome flashed for the person who pressed.
	 *
	 * The Administrator Dashboard posts the same decisions with a return field; the
	 * allowlist in `WPCPM_Return` decides, and a missing or foreign value is this screen.
	 *
	 * @param string $status An outcome key the screen's message map knows.
	 */
	protected function redirect_back( $status ) {
		WPCPM_Flash::set( $this->flash_key(), $status );
		wp_safe_redirect( class_exists( 'WPCPM_Return' ) ? WPCPM_Return::url( $this->admin_url() ) : $this->admin_url() );
		exit;
	}
```

In `includes/modules/class-wpcpm-institution-request.php`, replace `finish()`:

```php
	private static function finish( $status ) {
		WPCPM_Flash::set( WPCPM_Institutions::FLASH, (string) $status );

		$queue = admin_url( 'admin.php?page=wpcpm-institutions' ) . '#wpcpm-queue';

		// The Administrator Dashboard posts the same decision with a return field; the
		// allowlist in `WPCPM_Return` decides, and a missing or foreign value is the queue.
		wp_safe_redirect( class_exists( 'WPCPM_Return' ) ? WPCPM_Return::url( $queue ) : $queue );
		exit;
	}
```

In the same file, change `render_decisions( $post_id )` to `render_decisions( $post_id, $return = '' )` (docblock: `@param string $return WPCPM_Return::DASHBOARD when drawn on the Administrator Dashboard, else ''.`), replace the form opener

```php
		echo '<form class="wpcpm-request__decide" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
```

with

```php
		printf(
			'<form class="wpcpm-request__decide" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving', 'wpcredits-program-manager' )
		);
```

and after the hidden `request` input add:

```php
		if ( class_exists( 'WPCPM_Return' ) ) {
			WPCPM_Return::field( (string) $return, 'requests' );
		}
```

In `includes/modules/class-wpcpm-institutions.php`:

1. Change the visibility of these static methods from `private static function` to `public static function`: `application_state`, `application_reference`, `application_email`, `application_fields`, `open_states`, `reopen_states`, `purgeable_states`, `signals`, `country_name`, `queue_messages`. Change `private function render_application_answers` and `private function render_application_actions` to `public function`. Add one sentence to each docblock: "Public since 1.92.0 for the Administrator Dashboard, which draws the same queue."
2. `render_application_actions( WP_Post $post, $state )` becomes `render_application_actions( WP_Post $post, $state, $return = '' )` (docblock `@param string $return WPCPM_Return::DASHBOARD when drawn on the Administrator Dashboard, else ''.`), and each of the six `$this->render_decision_form( $post, array( ... ) )` argument arrays gains the entry `'return' => (string) $return,` as its first line.
3. In `render_decision_form()`, add `'return' => ''` to the defaults array; replace the form opener

```php
		printf(
			'<form class="wpcpm-app-action" method="post" action="%1$s"%2$s>',
			esc_url( admin_url( 'admin-post.php' ) ),
			'' !== $args['confirm'] ? ' onsubmit="return confirm(\'' . esc_js( $args['confirm'] ) . '\');"' : ''
		);
```

with

```php
		// The double-submit guard, inert on wp-admin where forms.js is not loaded and live on
		// the Administrator Dashboard, which enqueues it: two presses of Approve made two
		// accounts once, on another form that lacked it.
		printf(
			'<form class="wpcpm-app-action" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%3$s"%2$s>',
			esc_url( admin_url( 'admin-post.php' ) ),
			'' !== $args['confirm'] ? ' onsubmit="return confirm(\'' . esc_js( $args['confirm'] ) . '\');"' : '',
			esc_attr__( 'Working', 'wpcredits-program-manager' )
		);
```

and after the hidden application field add:

```php
		if ( class_exists( 'WPCPM_Return' ) ) {
			WPCPM_Return::field( (string) $args['return'], 'applications' );
		}
```

- [ ] **Step 5: Run the affected suites**

Run: `for f in test-return test-institutions-screen test-institution-request test-handlers test-institutions-sync test-roles; do echo "$f: $(php bin/$f.php 2>&1 | tail -1)"; done; php bin/check-references.php | tail -1`
Expected: every line ends in `ALL PASS` (or the handlers suite's own completion line), references resolve. If `test-institutions-screen.php` or `test-institution-request.php` scrape the decision forms with an exact `<form ...>` string, update that assertion to the new opener (the attributes are the only change) and say so in the report.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Task 1: WPCPM_Return, the two redirect branches, decision forms that come back

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 2: The readers and the audience maps

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-agreement.php` (after `awaiting_review()`, line 2006), `includes/modules/class-wpcpm-institution-request.php` (after `open_requests()`, line 524), `includes/class-wpcpm-updates.php:82-94` (`levels_for()`), `includes/tools/class-wpcpm-handbook-assistant.php:368-401` (`guides()`), `includes/class-wpcpm-dashboards.php:37-99` (`links()`) and `:164-202` (`nothing_to_show()`)
- Test: `bin/test-institution-agreement.php`, `bin/test-institution-request.php`, `bin/test-updates.php`, `bin/test-handbook.php`

**Interfaces:**
- Produces: `WPCPM_Institution_Agreement::in_state( $state, $limit = 50 ): int[]` (post IDs, newest modified first, `array()` for an unknown state); `WPCPM_Institution_Request::closed_requests( $limit = 20 ): int[]` (done and declined, newest modified first); `WPCPM_Updates::levels_for( 'administrator' )` = `array( 'public', 'administrator' )`; `WPCPM_Handbook_Assistant::guides()['administrator']`; `WPCPM_Dashboards::links()` gains `wpcpm-administrator-dashboard` for a manager when `WPCPM_Administrators_Dashboard::page_url()` is non-empty; `WPCPM_Dashboards::nothing_to_show( 'administrators', $can_manage )`.

- [ ] **Step 1: Write the failing tests**

In `bin/test-institution-agreement.php`, append before its summary line (find the final `printf`/`echo` that prints the pass or fail total):

```php
echo "\n=== Documents by state, newest first ===\n";

// Seed two returned and one revoked document for two institutions, with modified times a
// minute apart, the way the suite's other seeds do (use its existing seeding helper if it
// has one; otherwise wp_insert_post() + update_post_meta() with META_INSTITUTION and
// META_STATE, and set post_modified_gmt on the stored post object).
$r_old = wp_insert_post( array( 'post_type' => WPCPM_Institution_Agreement::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Returned, older' ) );
update_post_meta( $r_old, WPCPM_Institution_Agreement::META_INSTITUTION, 'recINSTA000000001' );
update_post_meta( $r_old, WPCPM_Institution_Agreement::META_STATE, WPCPM_Institution_Agreement::STATE_RETURNED );
$GLOBALS['posts'][ $r_old ]->post_modified_gmt = '2026-09-01 10:00:00';
$r_new = wp_insert_post( array( 'post_type' => WPCPM_Institution_Agreement::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Returned, newer' ) );
update_post_meta( $r_new, WPCPM_Institution_Agreement::META_INSTITUTION, 'recINSTB000000002' );
update_post_meta( $r_new, WPCPM_Institution_Agreement::META_STATE, WPCPM_Institution_Agreement::STATE_RETURNED );
$GLOBALS['posts'][ $r_new ]->post_modified_gmt = '2026-09-02 10:00:00';
$v_one = wp_insert_post( array( 'post_type' => WPCPM_Institution_Agreement::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Revoked' ) );
update_post_meta( $v_one, WPCPM_Institution_Agreement::META_INSTITUTION, 'recINSTA000000001' );
update_post_meta( $v_one, WPCPM_Institution_Agreement::META_STATE, WPCPM_Institution_Agreement::STATE_REVOKED );

ck( 'returned documents, newest first', WPCPM_Institution_Agreement::in_state( WPCPM_Institution_Agreement::STATE_RETURNED ), array( $r_new, $r_old ) );
ck( 'revoked documents', WPCPM_Institution_Agreement::in_state( WPCPM_Institution_Agreement::STATE_REVOKED ), array( $v_one ) );
ck( 'the limit caps the list', WPCPM_Institution_Agreement::in_state( WPCPM_Institution_Agreement::STATE_RETURNED, 1 ), array( $r_new ) );
ck( 'a state the class does not know is nobody', WPCPM_Institution_Agreement::in_state( 'lost' ), array() );
ck( 'awaiting_review() is unchanged by the new reader: it lists submitted only', in_array( $r_new, WPCPM_Institution_Agreement::awaiting_review(), true ), false );
```

If the suite's `get_posts()` stub does not honour `orderby` on `modified`, sort in the stub by `post_modified_gmt` when `$args['orderby']` names it, and say so in the report; the reader's contract is newest first.

In `bin/test-institution-request.php`, append before its summary line:

```php
echo "\n=== Closed requests ===\n";

// One handled and one declined, on top of the open fixture rows; the closed reader lists
// only those two, newest edit first, and the open reader still ignores them.
$done = wp_insert_post( array( 'post_type' => WPCPM_Institution_Request::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Handled' ) );
update_post_meta( $done, WPCPM_Institution_Request::META_STATE, WPCPM_Institution_Request::STATE_DONE );
$GLOBALS['posts'][ $done ]->post_modified_gmt = '2026-09-01 10:00:00';
$declined = wp_insert_post( array( 'post_type' => WPCPM_Institution_Request::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Declined' ) );
update_post_meta( $declined, WPCPM_Institution_Request::META_STATE, WPCPM_Institution_Request::STATE_DECLINED );
$GLOBALS['posts'][ $declined ]->post_modified_gmt = '2026-09-02 10:00:00';

ck( 'closed requests, newest first', WPCPM_Institution_Request::closed_requests(), array( $declined, $done ) );
ck( 'capped', WPCPM_Institution_Request::closed_requests( 1 ), array( $declined ) );
ck( 'and the open reader does not list them', array_intersect( WPCPM_Institution_Request::open_requests( 200 ), array( $done, $declined ) ), array() );
```

In `bin/test-updates.php`, find the block that asserts the audience column (the loop over `array( 'student', 'mentor', 'institution' )` near line 273 and the audience checks near lines 320 to 346) and add, in the same shape the file uses for `institution`, an `administrator` audience: a post gated to the `administrator` level appears in `render_column( 'administrator' )` for a manager, a post gated to the `student` level does not, and a public post does. Read the fixture first; the level's meta value is the literal `administrator` and the viewer must hold `CAP_MANAGE`.

In `bin/test-handbook.php`, change the exact-keys assertion near line 1063 to `array( 'student', 'mentor', 'institution', 'administrator' )` and add after it:

```php
ck( 'the program managers\' guide is the handbook\'s education section', $guides['administrator']['url'], 'https://make.wordpress.org/community/handbook/education/credits/' );
ck( 'and their channel is the program\'s', $guides['administrator']['slack'], $guides['institution']['slack'] );
```

- [ ] **Step 2: Run the four suites to verify they fail**

Run: `for f in test-institution-agreement test-institution-request test-updates test-handbook; do echo "$f: $(php bin/$f.php 2>&1 | grep -cE '^FAIL|Fatal') failing"; done`
Expected: each reports at least one failure or a fatal on the missing method.

- [ ] **Step 3: Add the two readers**

In `includes/modules/class-wpcpm-institution-agreement.php`, after `awaiting_review()`:

```php
	/**
	 * Every document in one state, newest edit first, capped.
	 *
	 * `awaiting_review()` is this for `submitted`, oldest first, because a queue is worked
	 * in order; the Administrator Dashboard's returned and revoked lists want the most
	 * recent on top, which is the only difference. A state this class does not know
	 * answers empty rather than everything.
	 *
	 * @param string $state One of the STATE_* values.
	 * @param int    $limit Most rows to read.
	 * @return int[] Post IDs.
	 */
	public static function in_state( $state, $limit = 50 ) {
		$known = array( self::STATE_GENERATED, self::STATE_SUBMITTED, self::STATE_ACCEPTED, self::STATE_RETURNED, self::STATE_WITHDRAWN, self::STATE_SUPERSEDED, self::STATE_REVOKED );

		if ( ! in_array( (string) $state, $known, true ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => self::POST_STATUS,
				'numberposts'      => (int) $limit > 0 ? (int) $limit : 50,
				'fields'           => 'ids',
				'orderby'          => array(
					'modified' => 'DESC',
					'ID'       => 'DESC',
				),
				'suppress_filters' => false,
				'meta_query'       => array(
					array(
						'key'   => self::META_STATE,
						'value' => (string) $state,
					),
				),
			)
		);

		return array_map( 'intval', (array) $posts );
	}
```

In `includes/modules/class-wpcpm-institution-request.php`, after `open_requests()`, mirroring its `post_status` and argument style:

```php
	/**
	 * The requests somebody closed, newest edit first, capped.
	 *
	 * Handled and declined alike: the Administrator Dashboard shows the last few so a manager
	 * can see what a colleague did this week, not to reopen anything (a closed request has
	 * no transition, `transitions()` says so).
	 *
	 * @param int $limit Most rows to read, capped at QUEUE_MAX.
	 * @return int[] Post IDs.
	 */
	public static function closed_requests( $limit = 20 ) {
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'private',
				'numberposts'      => (int) $limit > 0 ? min( (int) $limit, self::QUEUE_MAX ) : 20,
				'fields'           => 'ids',
				'orderby'          => array(
					'modified' => 'DESC',
					'ID'       => 'DESC',
				),
				'suppress_filters' => false,
				'meta_query'       => array(
					array(
						'key'     => self::META_STATE,
						'value'   => array( self::STATE_DONE, self::STATE_DECLINED ),
						'compare' => 'IN',
					),
				),
			)
		);

		return array_map( 'intval', (array) $posts );
	}
```

- [ ] **Step 4: Add the audience maps**

In `includes/class-wpcpm-updates.php` `levels_for()`, add to `$map`:

```php
		// The Administrator Dashboard's column: a manager's own level and the public posts,
		// never every level, which is what the viewer fallback below would give a manager.
		'administrator' => WPCPM_Roles::ROLE_ADMIN,
```

In `includes/tools/class-wpcpm-handbook-assistant.php` `guides()`, add after the `institution` entry:

```php
		// Program managers: the handbook's education section is theirs as a whole, and
		// the program's own channel is where they talk.
		'administrator' => array(
			'label' => __( 'Program manager guide', 'wpcredits-program-manager' ),
			'url'   => 'https://make.wordpress.org/community/handbook/education/credits/',
			'slack' => 'https://wordpress.slack.com/archives/C0959D2M3T8',
			'chat'  => __( 'Ask in the WordPress Credits Slack channel', 'wpcredits-program-manager' ),
		),
```

In `includes/class-wpcpm-dashboards.php`:

`links()`: after the institution block and before the filter, add:

```php
		// Managers only, and guarded like the institution entry: the class lands with the
		// Administrators module's front end and `links()` runs on every toolbar render.
		if ( $can_manage && class_exists( 'WPCPM_Administrators_Dashboard' ) ) {
			$administrator_page = WPCPM_Administrators_Dashboard::page_url();

			if ( '' !== $administrator_page ) {
				$links[] = array(
					'id'    => 'wpcpm-administrator-dashboard',
					'title' => __( 'Administrator Dashboard', 'wpcredits-program-manager' ),
					'href'  => $administrator_page,
					'own'   => true,
				);
			}
		}
```

`nothing_to_show()`: add to `$theirs` the entry `'administrators' => __( 'This page is for the program managers. Your account cannot manage the program.', 'wpcredits-program-manager' ),`, to `$messages` the entry `'administrators' => __( 'Nothing is waiting for a manager right now.', 'wpcredits-program-manager' ),` and to `$screens` the entry `'administrators' => 'wpcpm-administrators',`. Update the comment above the fallback line to say four audiences are named.

- [ ] **Step 5: Run the four suites and the reference check**

Run: `for f in test-institution-agreement test-institution-request test-updates test-handbook test-institutions-dashboard; do echo "$f: $(php bin/$f.php 2>&1 | tail -1)"; done; php bin/check-references.php | tail -1`
Expected: every line ends in `ALL PASS`.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Task 2: the readers and audience maps the Administrator Dashboard needs

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 3: `WPCPM_Administrators_Cards`: the data, the counts and the six cards

**Files:**
- Create: `includes/modules/class-wpcpm-administrators-cards.php`
- Create: `bin/test-administrators-dashboard.php` (the suite both this task and Task 4 use; this task writes its stubs and the card assertions, Task 4 appends the page assertions)
- Modify: `wpcredits-program-manager.php:93` (require the new file before `includes/modules/class-wpcpm-administrators.php`), `uninstall.php` (the same line in its list)

**Interfaces:**
- Consumes (all existing after Tasks 1 and 2): `WPCPM_Institution_Application::applications( array $states ): WP_Post[]`, `::reference( $post_id )`, `::META_COUNTRY`, `::META_COUNTRY_NAME`, `::STATE_REJECTED`, `::STATE_SPAM`; `WPCPM_Institutions::open_states()`, `application_state( WP_Post )`, `application_reference( WP_Post )`, `country_name( $country_id, $stored )`, `render_application_answers( WP_Post )` and `render_application_actions( WP_Post, $state, $return )` on the instance `WPCPM_Modules::get( 'institutions' )`; `WPCPM_Countries::contact_of( $country_id )`; `WPCPM_Institution_Agreement::awaiting_review( 200 )`, `in_state( $state, $limit )`, `summary( $record )['state']`, `META_INSTITUTION`, `META_NOTE`, `STATE_RETURNED`, `STATE_REVOKED`, `ACTION_REINSTATE`; `WPCPM_Institution_Panel::render_review( $post_id )`; `WPCPM_Semester_Report::queue()`, `due( $today )`, `approved_since( $ts )`, `reports_of( $record )`, `state( WP_Post )`, `STATE_APPROVED`; `WPCPM_Semester_Report_Screen::report_url( $cohort )`, `render_draft_form( $record, $cohort )`; `WPCPM_Institution_Request::open_requests( 200 )`, `closed_requests( 20 )`, `facts( $id )`, `render_decisions( $id, $return )`, `STATE_DONE`; `WPCPM_Institutions_Index::rows()`, `row( $record )`; `WPCPM_Roster_Index::read( $record )`, `NEVER_SHOWN`; `WPCPM_Mentors_Sync::tracked_statuses()['active'|'past']`; `WPCPM_Program::track( $status )`, `label( $status )`, `STATUS_150H`, `STATUS_50H`, `STATUS_DEV`; `WPCPM_Cohort::current()`, `range( $key )`, `key( $date )`, `label( $key )`; `WPCPM_Students_Sync::progress()`, `OPT_LAST`, `CRON_AUTO`; `WPCPM_Mentors_Sync::progress()`, `OPT_LAST`, `CRON_DAILY`; `WPCPM_Institutions_Sync::progress()`, `last_read()`, `CRON_DAILY`; `WPCPM_Institution_Roster::locked_today()`, `ARG_VIEW`; `WPCPM_Private_Files::probe_result()`, `verdict( array )`; `WPCPM_Mail::log()`, `run()`, `queued()`; `WPCPM_Institutions_Dashboard::page_url()`; `WPCPM_Return::DASHBOARD`.
- Produces: `WPCPM_Administrators_Cards::collect(): array` with keys `applications` (`open`, `closed`), `agreements` (`awaiting`, `returned`, `revoked`, `overdue`), `reports` (`queue`, `due`, `approved`), `requests` (`open`, `closed`, `overdue`), `locked`, `programs`, `health`; `counts( array $data ): array` of eight tiles (`label`, `n`, `card`); `programs(): array` (`tracks`, `finished`, `rows`, `quiet`, `read`, `semester`); `health(): array`; `render_strip( array $counts )`, `render_applications( array )`, `render_agreements( array )`, `render_reports( array )`, `render_requests( array )`, `render_programs( array )`, `render_health( array $health, array $locked )`; every card section carries `id="wpcpm-<anchor>"` with the anchors `WPCPM_Return::ANCHORS` names.

- [ ] **Step 1: Create the suite with its stubs and the card assertions**

Create `bin/test-administrators-dashboard.php`. Its first part is a copy: open `bin/test-institutions-dashboard.php` and copy everything from the `if ( 'cli' !== PHP_SAPI )` guard up to (not including) its first `require_once` line: the constants, the global fixture arrays, the `WP_Error`/`WP_User`/`WP_Post` classes and the WordPress function stubs. Keep that block verbatim (it defines `get_option`, `update_option`, `metadata_exists` with the default-is-public trap, `get_posts`, `wp_insert_post`, `add_action`, `register_block_type`, `current_user_can` answering `$GLOBALS['manage']`, and the rest). Then replace the file's docblock with:

```php
/**
 * The Administrator Dashboard: every queue on one page, the counts above them, and the
 * decisions posted to the handlers that already exist.
 *
 * What each block pins, and why:
 *
 * - **Every card reads through the owning class.** The collaborators are stubbed to their
 *   contracts and record what was asked; the cards never query posts themselves.
 * - **The counts are the cards' counts.** The strip's eight numbers come from the same
 *   arrays the cards draw, so a tile and its card cannot disagree.
 * - **A decision posted here comes back here.** Every application and request form
 *   carries `wpcpm_return=dashboard`; the agreement forms return to the referer already.
 * - **Every link to an institution carries the switcher argument**, or a manager lands on
 *   their fallback institution and reads another school's report under this row's name.
 * - **The programs calculation reads only status, start, end, reports and mentor_name**
 *   off roster rows, and a finished student no longer says their track, so "finished this
 *   semester" is one number rather than one per track.
 * - **A viewer without the capability sees the refusal and no form.**
 *
 * Run from the plugin root:  php bin/test-administrators-dashboard.php
 */
```

After the copied stub block, add these requires and collaborator stubs (the real classes are required last):

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-cohort.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';

/* ---- the collaborators, stubbed to their contracts ----------------------- */

if ( ! function_exists( 'get_post_time' ) ) {
	function get_post_time( $f = 'U', $gmt = false, $post = null ) { return isset( $post->post_date_gmt ) ? (int) strtotime( $post->post_date_gmt . ' UTC' ) : 0; }
}
if ( ! function_exists( 'get_post_modified_time' ) ) {
	function get_post_modified_time( $f = 'U', $gmt = false, $post = null ) { return isset( $post->post_modified_gmt ) ? (int) strtotime( $post->post_modified_gmt . ' UTC' ) : 0; }
}
if ( ! function_exists( 'human_time_diff' ) ) {
	function human_time_diff( $from, $to = 0 ) { return '2 days'; }
}
if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook ) { return isset( $GLOBALS['next'][ $hook ] ) ? $GLOBALS['next'][ $hook ] : false; }
}
if ( ! function_exists( 'get_user_by' ) ) {
	function get_user_by( $by, $v ) { return isset( $GLOBALS['users'][ (int) $v ] ) ? $GLOBALS['users'][ (int) $v ] : false; }
}
if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $n ) { return (string) $n; }
}
if ( ! function_exists( '_n' ) ) {
	function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
}

function seed_post( $id, $type, $title, $meta = array(), $date = '2026-09-01 10:00:00' ) {
	$post                    = new WP_Post();
	$post->ID                = (int) $id;
	$post->post_type         = $type;
	$post->post_status       = 'private';
	$post->post_title        = $title;
	$post->post_date_gmt     = $date;
	$post->post_modified_gmt = $date;
	$GLOBALS['posts'][ (int) $id ] = $post;
	$GLOBALS['pmeta'][ (int) $id ] = $meta;
	return $post;
}

class WPCPM_Mentors_Sync {
	public static function is_record_id( $v ) { return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $v ) ); }
	public static function tracked_statuses( $settings = null ) {
		return array(
			'active' => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation' ),
			'past'   => array( 'Graduate', 'Dropped out' ),
		);
	}
}
class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) {
		$known = array( 'agreement_review_days' => 3, 'institution_active_stages' => array( 'Confirmed', 'Student' ), 'past_statuses' => array( 'Graduate', 'Dropped out' ) );
		return isset( $known[ $key ] ) ? $known[ $key ] : $fallback;
	}
	public static function is_connected() { return true; }
}
class WPCPM_Content_Access {
	const META_KEY = '_wpcpm_access_level';
}
class WPCPM_Two_Factor {
	public static function prompt( $user ) { $GLOBALS['prompted'][] = $user instanceof WP_User ? $user->ID : 0; }
}
class WPCPM_Flash {
	public static function set( $c, $v, $u = 0 ) { $GLOBALS['flash'][ $c ] = $v; }
	public static function take( $c, $u = 0 ) { $v = isset( $GLOBALS['flash'][ $c ] ) ? $GLOBALS['flash'][ $c ] : ''; unset( $GLOBALS['flash'][ $c ] ); return $v; }
}
class WPCPM_Students_Dashboard {
	public static function page_url() { return ''; }
	public static function is_student() { return false; }
}
class WPCPM_Mentors_Dashboard {
	public static function page_url() { return ''; }
	public static function is_mentor() { return false; }
}
class WPCPM_Institutions_Dashboard {
	public static function page_url() { return 'https://example.test/institution-dashboard/'; }
	public static function is_member() { return false; }
}
class WPCPM_Institution_Roster {
	const ARG_VIEW = 'wpcpm_institution_view';
	public static function locked_today() { return isset( $GLOBALS['locked'] ) ? $GLOBALS['locked'] : array(); }
}
class WPCPM_Institutions_Index {
	public static function rows() { return isset( $GLOBALS['inst_rows'] ) ? $GLOBALS['inst_rows'] : array(); }
	public static function row( $id ) { return isset( $GLOBALS['inst_rows'][ (string) $id ] ) ? $GLOBALS['inst_rows'][ (string) $id ] : null; }
	public static function has( $id ) { return isset( $GLOBALS['inst_rows'][ (string) $id ] ); }
}
class WPCPM_Roster_Index {
	const NEVER_SHOWN = array( 'SPAM', 'Duplicated' );
	public static function read( $id ) { return isset( $GLOBALS['rosters'][ (string) $id ] ) ? $GLOBALS['rosters'][ (string) $id ] : array( 'v' => 4, 'read' => 0, 'rows' => array() ); }
	public static function rows( $id ) { return self::read( $id )['rows']; }
}
class WPCPM_Countries {
	public static function contact_of( $country ) { return 'recCOUNTRY0000001' === (string) $country ? 'Ana Manager' : ''; }
}
class WPCPM_Institution_Application {
	const STATE_NEW = 'new'; const STATE_HELD = 'held'; const STATE_INFO = 'info'; const STATE_REJECTED = 'rejected'; const STATE_SPAM = 'spam'; const STATE_APPROVED = 'approved';
	const META_STATE = '_wpcpm_app_state'; const META_COUNTRY = '_wpcpm_app_country'; const META_COUNTRY_NAME = '_wpcpm_app_country_name';
	public static function applications( $states ) {
		$GLOBALS['asked'][] = array( 'applications', (array) $states );
		$out = array();
		foreach ( $GLOBALS['posts'] as $post ) {
			if ( 'wpcpm_inst_app' === $post->post_type && in_array( get_post_meta( $post->ID, self::META_STATE, true ), (array) $states, true ) ) { $out[] = $post; }
		}
		return $out;
	}
	public static function reference( $id ) { return sprintf( 'APP-2026-%04d', (int) $id ); }
}
class WPCPM_Institutions {
	const FLASH = 'institutions';
	const ACTION_APPROVE = 'wpcpm_app_approve';
	public static function open_states() { return array( 'new', 'held', 'info' ); }
	public static function application_state( WP_Post $post ) { return (string) get_post_meta( $post->ID, '_wpcpm_app_state', true ); }
	public static function application_reference( WP_Post $post ) { return WPCPM_Institution_Application::reference( $post->ID ); }
	public static function country_name( $id, $stored ) { return '' !== $stored ? $stored : ( 'recCOUNTRY0000001' === (string) $id ? 'Poland' : '' ); }
	public static function queue_messages() { return array( 'app-approved' => array( 'success', 'The application is approved.' ) ); }
	public function render_application_answers( WP_Post $post ) { echo '<table class="wpcpm-app-answers" data-app="' . (int) $post->ID . '"></table>'; }
	public function render_application_actions( WP_Post $post, $state, $return = '' ) {
		echo '<form class="wpcpm-app-action" method="post" action="https://example.test/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Working">';
		echo '<input type="hidden" name="action" value="' . self::ACTION_APPROVE . '" /><input type="hidden" name="wpcpm_application" value="' . (int) $post->ID . '" />';
		WPCPM_Return::field( (string) $return, 'applications' );
		echo '<button type="submit" class="button button-primary">Approve</button></form>';
	}
}
class WPCPM_Modules {
	public static function get( $id ) { return 'institutions' === $id ? new WPCPM_Institutions() : null; }
}
class WPCPM_Institution_Agreement {
	const POST_TYPE = 'wpcpm_agreement'; const META_INSTITUTION = '_wpcpm_agr_institution'; const META_STATE = '_wpcpm_agr_state'; const META_NOTE = '_wpcpm_agr_note';
	const STATE_SUBMITTED = 'submitted'; const STATE_RETURNED = 'returned'; const STATE_REVOKED = 'revoked';
	const ACTION_REINSTATE = 'wpcpm_agreement_reinstate';
	public static function awaiting_review( $limit = 200 ) { return isset( $GLOBALS['agr']['submitted'] ) ? $GLOBALS['agr']['submitted'] : array(); }
	public static function in_state( $state, $limit = 50 ) { return isset( $GLOBALS['agr'][ $state ] ) ? $GLOBALS['agr'][ $state ] : array(); }
	public static function summary( $record ) { return array( 'state' => isset( $GLOBALS['agr_summary'][ $record ] ) ? $GLOBALS['agr_summary'][ $record ] : 'none' ); }
}
class WPCPM_Institution_Panel {
	public static function render_review( $post_id ) { $GLOBALS['reviews'][] = (int) $post_id; echo '<section class="wpcpm-review" id="wpcpm-review-' . (int) $post_id . '"><form class="wpcpm-review__form wpcpm-review__form--accept"></form></section>'; }
	public static function messages() { return array( 'agreement-accepted' => array( 'success', 'Accepted.' ) ); }
}
class WPCPM_Semester_Report {
	const STATE_DRAFT = 'draft'; const STATE_APPROVED = 'approved';
	public static function queue() { return isset( $GLOBALS['reports']['queue'] ) ? $GLOBALS['reports']['queue'] : array(); }
	public static function due( $today ) { $GLOBALS['asked'][] = array( 'due', $today ); return isset( $GLOBALS['reports']['due'] ) ? $GLOBALS['reports']['due'] : array(); }
	public static function approved_since( $ts ) { $GLOBALS['asked'][] = array( 'approved_since', (int) $ts ); return isset( $GLOBALS['reports']['approved'] ) ? $GLOBALS['reports']['approved'] : array(); }
	public static function reports_of( $record ) { return isset( $GLOBALS['reports_of'][ $record ] ) ? $GLOBALS['reports_of'][ $record ] : array(); }
	public static function state( WP_Post $post ) { return (string) get_post_meta( $post->ID, '_wpcpm_report_state', true ); }
}
class WPCPM_Semester_Report_Screen {
	const ACTION_DRAFT = 'wpcpm_report_draft';
	public static function report_url( $cohort ) { return 'https://example.test/institution-dashboard/?wpcpm_report=' . $cohort . '#wpcpm-report'; }
	public static function render_draft_form( $record, $cohort, $label = '' ) { echo '<form class="wpcpm-report-card__generate" data-wpcpm-once><input type="hidden" name="action" value="' . self::ACTION_DRAFT . '" /><input type="hidden" name="institution" value="' . esc_attr( $record ) . '" /><input type="hidden" name="cohort" value="' . esc_attr( $cohort ) . '" /></form>'; }
}
class WPCPM_Institution_Request {
	const STATE_OPEN = 'open'; const STATE_DONE = 'done'; const STATE_DECLINED = 'declined'; const OVERDUE_DAYS = 14;
	const ACTION_RESOLVE = 'wpcpm_resolve_request';
	public static function open_requests( $limit = 20 ) { return isset( $GLOBALS['requests']['open'] ) ? $GLOBALS['requests']['open'] : array(); }
	public static function closed_requests( $limit = 20 ) { return isset( $GLOBALS['requests']['closed'] ) ? $GLOBALS['requests']['closed'] : array(); }
	public static function facts( $id ) { return isset( $GLOBALS['facts'][ (int) $id ] ) ? $GLOBALS['facts'][ (int) $id ] : array(); }
	public static function render_decisions( $id, $return = '' ) {
		echo '<form class="wpcpm-request__decide" method="post" data-wpcpm-once data-wpcpm-busy="Saving"><input type="hidden" name="action" value="' . self::ACTION_RESOLVE . '" /><input type="hidden" name="request" value="' . (int) $id . '" />';
		WPCPM_Return::field( (string) $return, 'requests' );
		echo '<button type="submit" name="state" value="done">Mark as handled</button></form>';
	}
	public static function messages() { return array( 'request-done' => array( 'success', 'Handled.' ) ); }
}
class WPCPM_Students_Sync {
	const OPT_LAST = 'wpcpm_students_last_sync'; const CRON_AUTO = 'wpcpm_students_daily';
	public static function progress() { return $GLOBALS['sync']['students']; }
}
class WPCPM_Mentors_Sync_Progress {}
class WPCPM_Institutions_Sync {
	const CRON_DAILY = 'wpcpm_institutions_sync_daily';
	public static function progress() { return $GLOBALS['sync']['institutions']; }
	public static function last_read() { return (int) get_option( 'wpcpm_institutions_last_sync', 0 ); }
}
class WPCPM_Private_Files {
	public static function probe_result() { return isset( $GLOBALS['probe'] ) ? $GLOBALS['probe'] : null; }
	public static function verdict( array $r ) { return ! empty( $r['blocked'] ) ? 'blocked' : ( $r['status'] >= 200 && $r['status'] < 300 ? 'served' : 'unknown' ); }
}
class WPCPM_Mail {
	public static function log() { return isset( $GLOBALS['mail_log'] ) ? $GLOBALS['mail_log'] : array(); }
	public static function run() { return isset( $GLOBALS['invite_run'] ) ? $GLOBALS['invite_run'] : array(); }
	public static function queued() { return isset( $GLOBALS['invite_queued'] ) ? (int) $GLOBALS['invite_queued'] : 0; }
}
class WPCPM_Handbook_Assistant {
	public static function render_resources( $audience = '', $extra = '' ) { $GLOBALS['resources'][] = $audience; return '<section class="wpcpm-handbook__resources" data-audience="' . esc_attr( $audience ) . '"></section>'; }
}
abstract class WPCPM_Sync_Module {
	public static function sync_messages() { return array( 'started' => array( 'success', 'Sync started.' ) ); }
}
```

`WPCPM_Mentors_Sync` above already carries `is_record_id()` and `tracked_statuses()`; add to it, in the same class body, the sync surface the health card reads:

```php
	const OPT_LAST = 'wpcpm_mentors_last_sync'; const CRON_DAILY = 'wpcpm_mentors_daily';
	public static function progress() { return $GLOBALS['sync']['mentors']; }
```

(Delete the empty `WPCPM_Mentors_Sync_Progress` class; it is not needed.)

Then require the real class under test and add the harness:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-cards.php';

$fail  = 0;
$total = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $total;
	$total++;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) { echo '       exp: ' . var_export( $expected, true ) . "\n       got: " . var_export( $actual, true ) . "\n"; }
}
function has( $h, $n ) { return false !== strpos( (string) $h, (string) $n ); }
function capture( $fn ) { ob_start(); call_user_func( $fn ); return (string) ob_get_clean(); }
```

Then the fixture and the card assertions:

```php
/* ---- the fixture ---------------------------------------------------------- */

$A = 'recINSTA000000001';
$B = 'recINSTB000000002';
$C = 'recINSTC000000003';

$GLOBALS['manage']  = true;
$GLOBALS['uid']     = 3;
$GLOBALS['users']   = array( 3 => new WP_User( 3, 'Manager Three', 'maciej@a8c.com' ), 21 => new WP_User( 21, 'Rep One', 'maciej@a8c.com' ) );
$GLOBALS['posts']   = array();
$GLOBALS['pmeta']   = array();
$GLOBALS['asked']   = array();
$GLOBALS['reviews'] = array();
$GLOBALS['next']    = array( 'wpcpm_students_daily' => 1757100000 );
$GLOBALS['opts']['wpcpm_students_last_sync']     = 1757000000;
$GLOBALS['opts']['wpcpm_mentors_last_sync']      = 1756990000;
$GLOBALS['opts']['wpcpm_institutions_last_sync'] = 1756980000;

$GLOBALS['inst_rows'] = array(
	$A => array( 'record_id' => $A, 'name' => 'Uniwersytet Alpha', 'stage' => 'Confirmed', 'country' => 'recCOUNTRY0000001', 'country_name' => 'Poland' ),
	$B => array( 'record_id' => $B, 'name' => 'Universidad Beta ', 'stage' => 'Confirmed', 'country' => '', 'country_name' => '' ),
	$C => array( 'record_id' => $C, 'name' => 'Instituto Gamma', 'stage' => 'Confirmed', 'country' => '', 'country_name' => '' ),
);
$GLOBALS['rosters'] = array(
	$A => array( 'v' => 4, 'read' => 1756900000, 'rows' => array(
		array( 'record_id' => 'recS0000000000001', 'status' => 'In Sensei', 'start' => '2026-08-01', 'end' => '2026-12-15', 'reports' => array( 'recR1' ), 'mentor_name' => 'Mentor One' ),
		array( 'record_id' => 'recS0000000000002', 'status' => 'Developer Track', 'start' => '2026-08-10', 'end' => '2026-11-30', 'reports' => array(), 'mentor_name' => '' ),
		array( 'record_id' => 'recS0000000000003', 'status' => 'In Sensei 50h', 'start' => '2026-02-01', 'end' => '', 'reports' => array( 'recR2' ), 'mentor_name' => 'Mentor One' ),
		array( 'record_id' => 'recS0000000000004', 'status' => 'Graduate', 'start' => '2026-02-01', 'end' => '2026-08-20', 'reports' => array( 'recR3' ), 'mentor_name' => 'Mentor Two' ),
		array( 'record_id' => 'recS0000000000005', 'status' => 'SPAM', 'start' => '2026-08-01', 'end' => '', 'reports' => array(), 'mentor_name' => '' ),
	) ),
	$B => array( 'v' => 4, 'read' => 1756800000, 'rows' => array(
		array( 'record_id' => 'recS0000000000006', 'status' => 'Graduate', 'start' => '2025-09-01', 'end' => '2026-01-20', 'reports' => array( 'recR4' ), 'mentor_name' => 'Mentor Two' ),
	) ),
);
$GLOBALS['agr_summary'] = array( $A => 'accepted', $B => 'submitted' );
seed_post( 701, 'wpcpm_inst_report', 'Report A 2026-H1', array( '_wpcpm_report_state' => 'draft', '_wpcpm_report_institution' => $A, '_wpcpm_report_cohort' => '2026-H1' ) );
$GLOBALS['reports_of'] = array( $A => array( '2026-H1' => $GLOBALS['posts'][701] ) );

seed_post( 501, 'wpcpm_inst_app', 'Uni Nueva', array( '_wpcpm_app_state' => 'new', '_wpcpm_app_country' => 'recCOUNTRY0000001', '_wpcpm_app_country_name' => 'Poland' ), '2026-09-01 08:00:00' );
seed_post( 502, 'wpcpm_inst_app', 'Uni Held', array( '_wpcpm_app_state' => 'held', '_wpcpm_app_country' => '', '_wpcpm_app_country_name' => '' ), '2026-09-02 08:00:00' );
seed_post( 503, 'wpcpm_inst_app', 'Uni Rejected', array( '_wpcpm_app_state' => 'rejected', '_wpcpm_app_country' => '', '_wpcpm_app_country_name' => '' ), '2026-08-01 08:00:00' );

seed_post( 601, 'wpcpm_agreement', 'Agreement B', array( '_wpcpm_agr_institution' => $B, '_wpcpm_agr_state' => 'submitted' ), '2026-08-20 08:00:00' );
seed_post( 602, 'wpcpm_agreement', 'Agreement C', array( '_wpcpm_agr_institution' => $C, '_wpcpm_agr_state' => 'submitted' ), gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) );
seed_post( 603, 'wpcpm_agreement', 'Agreement A returned', array( '_wpcpm_agr_institution' => $A, '_wpcpm_agr_state' => 'returned', '_wpcpm_agr_note' => 'Page two is missing.' ), '2026-08-25 08:00:00' );
seed_post( 604, 'wpcpm_agreement', 'Agreement C revoked', array( '_wpcpm_agr_institution' => $C, '_wpcpm_agr_state' => 'revoked', '_wpcpm_agr_note' => 'Withdrawn by the rector.' ), '2026-08-26 08:00:00' );
$GLOBALS['agr'] = array( 'submitted' => array( 601, 602 ), 'returned' => array( 603 ), 'revoked' => array( 604 ) );

$GLOBALS['reports'] = array(
	'queue'    => array( array( 'post_id' => 701, 'institution' => $A, 'cohort' => '2026-H1', 'generated' => 1756500000, 'origin' => 'auto', 'in_progress' => 1, 'age_days' => 5, 'approved_at' => 0, 'approved_by' => 0 ) ),
	'due'      => array( array( 'institution' => $B, 'cohort' => '2025-H2', 'in_progress' => 0, 'window_end' => '2025-12-31' ) ),
	'approved' => array( array( 'post_id' => 702, 'institution' => $C, 'cohort' => '2026-H1', 'generated' => 1756000000, 'origin' => 'manager', 'in_progress' => 0, 'age_days' => 9, 'approved_at' => 1756600000, 'approved_by' => 3 ) ),
);

$GLOBALS['requests'] = array( 'open' => array( 801, 802 ), 'closed' => array( 803 ) );
$GLOBALS['facts'] = array(
	801 => array( 'id' => 801, 'kind' => 'mentor', 'kind_label' => 'A mentor is wanted', 'state' => 'open', 'institution' => $A, 'institution_name' => 'Uniwersytet Alpha', 'note' => 'Two students without a mentor.', 'at' => time() - 20 * DAY_IN_SECONDS, 'overdue' => true, 'actor' => 21, 'actor_name' => 'Rep One' ),
	802 => array( 'id' => 802, 'kind' => 'mentor', 'kind_label' => 'A mentor is wanted', 'state' => 'open', 'institution' => $B, 'institution_name' => 'Universidad Beta', 'note' => '', 'at' => time() - DAY_IN_SECONDS, 'overdue' => false, 'actor' => 21, 'actor_name' => 'Rep One' ),
	803 => array( 'id' => 803, 'kind' => 'mentor', 'kind_label' => 'A mentor is wanted', 'state' => 'done', 'institution' => $A, 'institution_name' => 'Uniwersytet Alpha', 'note' => 'Handled.', 'at' => time() - 30 * DAY_IN_SECONDS, 'overdue' => false, 'actor' => 21, 'actor_name' => 'Rep One' ),
);
$GLOBALS['locked'] = array( new WP_User( 21, 'Rep One', 'maciej@a8c.com' ) );
$GLOBALS['sync'] = array(
	'students'     => array( 'running' => false, 'phase' => 'done', 'label' => 'Done', 'error' => '', 'elapsed' => 0 ),
	'mentors'      => array( 'running' => true, 'phase' => 'reports', 'label' => 'Reading reports', 'error' => '', 'elapsed' => 40 ),
	'institutions' => array( 'running' => false, 'phase' => 'done', 'label' => 'Done', 'error' => 'HTTP 429 from Airtable <b>x</b>', 'elapsed' => 0 ),
);
$GLOBALS['probe']         = array( 'status' => 403, 'time' => 1756700000, 'blocked' => true, 'error' => '', 'control_status' => 200, 'encrypted' => true );
$GLOBALS['mail_log']      = array( array( 'time' => 1756990000, 'to' => 'm***@a8c.com', 'context' => 'report-drafted', 'sent' => true ) );
$GLOBALS['invite_run']    = array( 'total' => 5, 'started' => 1756990000, 'finished' => 0 );
$GLOBALS['invite_queued'] = 2;

/* ---- collect() and counts() ---------------------------------------------- */

echo "=== The data is read once, through the owners ===\n";

$data = WPCPM_Administrators_Cards::collect();
ck( 'open applications are the three open states, through applications()', in_array( array( 'applications', array( 'new', 'held', 'info' ) ), $GLOBALS['asked'], true ), true );
ck( 'and the closed list is rejected and spam', in_array( array( 'applications', array( 'rejected', 'spam' ) ), $GLOBALS['asked'], true ), true );
ck( 'two open applications, one closed', array( count( $data['applications']['open'] ), count( $data['applications']['closed'] ) ), array( 2, 1 ) );
ck( 'two agreements awaiting review, one of them overdue', array( count( $data['agreements']['awaiting'] ), $data['agreements']['overdue'] ), array( 2, 1 ) );
ck( 'the overdue one is the older', $data['agreements']['awaiting'][0]['overdue'], true );
ck( 'returned and revoked come with their note', array( $data['agreements']['returned'][0]['note'], $data['agreements']['revoked'][0]['note'] ), array( 'Page two is missing.', 'Withdrawn by the rector.' ) );
ck( 'the queue, the due list and the approved list are the report class\'s', array( count( $data['reports']['queue'] ), count( $data['reports']['due'] ), count( $data['reports']['approved'] ) ), array( 1, 1, 1 ) );
ck( 'due is asked for today', in_array( array( 'due', gmdate( 'Y-m-d' ) ), $GLOBALS['asked'], true ), true );
ck( 'and approved since the start of this half-year', in_array( array( 'approved_since', (int) strtotime( WPCPM_Cohort::range( WPCPM_Cohort::current() )['from'] . ' 00:00:00 UTC' ) ), $GLOBALS['asked'], true ), true );
ck( 'two open requests, one overdue, one closed', array( count( $data['requests']['open'] ), $data['requests']['overdue'], count( $data['requests']['closed'] ) ), array( 2, 1, 1 ) );
ck( 'one locked account', count( $data['locked'] ), 1 );

$counts = WPCPM_Administrators_Cards::counts( $data );
ck( 'eight tiles in the spec\'s order', array_keys( $counts ), array( 'applications', 'agreements', 'overdue_agreements', 'drafts', 'due', 'requests', 'overdue_requests', 'locked' ) );
ck( 'each tile is a number and a card', array_map( static function ( $t ) { return $t['n'] . ':' . $t['card']; }, $counts ), array( '2:applications', '2:agreements', '1:agreements', '1:reports', '1:reports', '2:requests', '1:requests', '1:health' ) );

/* ---- programs() ---------------------------------------------------------- */

echo "\n=== Programs running ===\n";

$programs = $data['programs'];
ck( 'the tracks strip counts students in progress per track', array( $programs['tracks']['150h']['in_progress'], $programs['tracks']['50h']['in_progress'], $programs['tracks']['dev']['in_progress'] ), array( 1, 1, 1 ) );
ck( 'signed up this semester per track, from the start date', array( $programs['tracks']['150h']['signed_up'], $programs['tracks']['50h']['signed_up'], $programs['tracks']['dev']['signed_up'] ), array( 1, 0, 1 ) );
ck( 'finished this semester is one number: a graduate no longer says their track', $programs['finished'], 1 );
ck( 'one row per institution with somebody in progress', array_column( $programs['rows'], 'record' ), array( $A ) );
ck( 'with the count, the breakdown by label, the waiting and the distinct mentors', array( $programs['rows'][0]['in_progress'], $programs['rows'][0]['by_status'], $programs['rows'][0]['waiting'], $programs['rows'][0]['mentors'] ), array( 3, array( 'WordPress Credits Program 150h' => 1, 'Developer Track' => 1, 'WordPress Credits Program 50h' => 1 ), 1, 1 ) );
ck( 'the earliest and latest end among those in progress', array( $programs['rows'][0]['earliest'], $programs['rows'][0]['latest'] ), array( '2026-11-30', '2026-12-15' ) );
ck( 'the agreement state and the latest report state ride along', array( $programs['rows'][0]['agreement'], $programs['rows'][0]['report'] ), array( 'accepted', 'draft' ) );
ck( 'an institution with nobody in progress is counted, not listed', $programs['quiet'], 1 );
ck( 'the read time is the newest roster read', $programs['read'], 1756900000 );
ck( 'a SPAM row counts for nothing', $programs['rows'][0]['in_progress'] + $programs['quiet'], 4 );

/* ---- the cards ----------------------------------------------------------- */

echo "\n=== The cards ===\n";

$strip = capture( static function () use ( $counts ) { WPCPM_Administrators_Cards::render_strip( $counts ); } );
ck( 'the strip is one section with eight tiles linking to the cards', array( substr_count( $strip, 'wpcpm-attention__tile' ), has( $strip, 'id="wpcpm-attention"' ), has( $strip, 'href="#wpcpm-agreements"' ) ), array( 8, true, true ) );
ck( 'a zero is drawn muted, not hidden', substr_count( $strip, 'wpcpm-attention__tile--zero' ), 0 );

$apps = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_applications( $data['applications'] ); } );
ck( 'the applications card is open with its count', has( $apps, 'id="wpcpm-applications"' ) && has( $apps, 'wpcpm-group__disclosure" open' ) && has( $apps, '<span class="wpcpm-group__count">2</span>' ), true );
ck( 'each open application names itself, its reference, country and routed manager', has( $apps, 'Uni Nueva' ) && has( $apps, 'APP-2026-0501' ) && has( $apps, 'Poland' ) && has( $apps, 'Ana Manager' ), true );
ck( 'the answers are the module\'s, behind a disclosure', has( $apps, 'data-app="501"' ) && has( $apps, 'wpcpm-administrator__answers' ), true );
ck( 'the decisions are the module\'s, and come back here', substr_count( $apps, 'name="wpcpm_return" value="dashboard"' ), 3 );
ck( 'the closed ones sit in a second, closed disclosure', has( $apps, 'Uni Rejected' ) && has( $apps, 'wpcpm-administrator__closed' ), true );

$agr = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_agreements( $data['agreements'] ); } );
ck( 'each awaiting agreement is the panel\'s review block', $GLOBALS['reviews'], array( 601, 602 ) );
ck( 'the overdue one is marked', has( $agr, 'wpcpm-administrator__item--overdue' ), true );
ck( 'the institution is linked through the switcher', has( $agr, 'wpcpm_institution_view=' . $B ), true );
ck( 'the returned list shows the note', has( $agr, 'Page two is missing.' ), true );
ck( 'and the revoked one offers reinstate with its own nonce', has( $agr, 'value="wpcpm_agreement_reinstate"' ) && has( $agr, 'name="wpcpm_agreement_post" value="604"' ), true );

$rep = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_reports( $data['reports'] ); } );
ck( 'a draft to review links to the editor as that institution', has( $rep, 'wpcpm_report=2026-H1' ) && has( $rep, 'wpcpm_institution_view=' . $A ), true );
ck( 'and says the site drafted it with one still in progress', has( $rep, 'by the site' ) && has( $rep, '>1<' ), true );
ck( 'a due cohort has a Draft now form', has( $rep, 'value="wpcpm_report_draft"' ) && has( $rep, 'name="cohort" value="2025-H2"' ), true );
ck( 'an approved report names who approved it', has( $rep, 'Manager Three' ), true );

$req = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_requests( $data['requests'] ); } );
ck( 'open requests draw the decisions, coming back here', substr_count( $req, 'value="wpcpm_resolve_request"' ) === 2 && substr_count( $req, 'name="wpcpm_return" value="dashboard"' ) === 2, true );
ck( 'the overdue one is marked and the note is printed', has( $req, 'wpcpm-administrator__item--overdue' ) && has( $req, 'Two students without a mentor.' ), true );
ck( 'the closed list says handled', has( $req, 'Handled' ), true );

$prog = capture( static function () use ( $programs ) { WPCPM_Administrators_Cards::render_programs( $programs ); } );
ck( 'the programs card has three track tiles and a finished tile', substr_count( $prog, 'wpcpm-programs__tile' ), 4 );
ck( 'the institution row links through the switcher and carries its numbers', has( $prog, 'wpcpm_institution_view=' . $A ) && has( $prog, '2026-12-15' ) && has( $prog, 'Uniwersytet Alpha' ), true );
ck( 'the quiet institutions are one closing line', has( $prog, '1 more institution' ), true );
ck( 'and the read time is printed', has( $prog, 'Read from the program records' ), true );

$health = capture( static function () use ( $data ) { WPCPM_Administrators_Cards::render_health( $data['health'], $data['locked'] ); } );
ck( 'three syncs with their state', substr_count( $health, 'wpcpm-health__sync' ), 3 );
ck( 'the error is printed verbatim and escaped', has( $health, 'HTTP 429 from Airtable &lt;b&gt;x&lt;/b&gt;' ), true );
ck( 'the locked account is named', has( $health, 'Rep One' ), true );
ck( 'the probe verdict, the last mail and the invitation run are there', has( $health, 'blocked' ) && has( $health, 'report-drafted' ) && has( $health, '3 of 5' ), true );
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php bin/test-administrators-dashboard.php 2>&1 | tail -3`
Expected: a fatal, `includes/modules/class-wpcpm-administrators-cards.php` does not exist.

- [ ] **Step 3: Write `WPCPM_Administrators_Cards`**

Create `includes/modules/class-wpcpm-administrators-cards.php`:

```php
<?php
/**
 * The Administrator Dashboard's cards: what each reads, what each draws.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every queue a program manager works, read once and drawn as cards.
 *
 * Each card reads through one static method on the class that owns the data and posts to
 * the handler that already exists (design of 4 September 2026, decision 2): nothing here
 * queries posts or options itself, so the page can never show a count the owner would
 * compute differently. `collect()` reads everything once, because the attention strip and
 * the cards draw the same arrays.
 *
 * Sponsors' cards join in the Sponsor module's last phase; the ids here are the anchors
 * `WPCPM_Return::ANCHORS` names, so a decision posted from a card lands back on it.
 */
final class WPCPM_Administrators_Cards {
	/** Most rows a list draws before it says how many more there are. */
	const LIMIT = 50;
	/** Closed requests shown under the open ones. */
	const CLOSED_SHOWN = 20;
	/** The track keys `WPCPM_Program::track()` answers, in the order the strip draws them. */
	const TRACKS = array( '150h', '50h', 'dev' );

	/*
	 * --------------------------------------------------------------------
	 * Reading
	 * --------------------------------------------------------------------
	 */

	/**
	 * Every dataset the page draws, read once.
	 *
	 * @return array
	 */
	public static function collect() {
		$now    = time();
		$review = max( 1, (int) WPCPM_Settings::get_value( 'agreement_review_days', 3 ) ) * DAY_IN_SECONDS;

		$awaiting = array();
		$overdue  = 0;

		foreach ( WPCPM_Institution_Agreement::awaiting_review( 200 ) as $post_id ) {
			$post = get_post( (int) $post_id );

			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$at   = (int) get_post_time( 'U', true, $post );
			$late = ( $now - $at ) > $review;

			if ( $late ) {
				++$overdue;
			}

			$awaiting[] = array(
				'id'      => (int) $post->ID,
				'record'  => (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Agreement::META_INSTITUTION, true ),
				'at'      => $at,
				'overdue' => $late,
			);
		}

		$open_requests   = array();
		$overdue_request = 0;

		foreach ( WPCPM_Institution_Request::open_requests( 200 ) as $request_id ) {
			$facts = WPCPM_Institution_Request::facts( (int) $request_id );

			if ( empty( $facts ) ) {
				continue;
			}

			if ( ! empty( $facts['overdue'] ) ) {
				++$overdue_request;
			}

			$open_requests[] = $facts;
		}

		$closed_requests = array();

		foreach ( WPCPM_Institution_Request::closed_requests( self::CLOSED_SHOWN ) as $request_id ) {
			$facts = WPCPM_Institution_Request::facts( (int) $request_id );

			if ( ! empty( $facts ) ) {
				$closed_requests[] = $facts;
			}
		}

		// "This semester" is the current half-year, the same window the semester report and
		// the roster strip use, so the three cannot disagree about what the phrase means.
		$from = WPCPM_Cohort::range( WPCPM_Cohort::current() );
		$from = '' !== $from['from'] ? (int) strtotime( $from['from'] . ' 00:00:00 UTC' ) : 0;

		return array(
			'applications' => array(
				'open'   => WPCPM_Institution_Application::applications( WPCPM_Institutions::open_states() ),
				'closed' => WPCPM_Institution_Application::applications( array( WPCPM_Institution_Application::STATE_REJECTED, WPCPM_Institution_Application::STATE_SPAM ) ),
			),
			'agreements'   => array(
				'awaiting' => $awaiting,
				'returned' => self::agreement_rows( WPCPM_Institution_Agreement::in_state( WPCPM_Institution_Agreement::STATE_RETURNED, self::LIMIT ) ),
				'revoked'  => self::agreement_rows( WPCPM_Institution_Agreement::in_state( WPCPM_Institution_Agreement::STATE_REVOKED, self::LIMIT ) ),
				'overdue'  => $overdue,
			),
			'reports'      => array(
				'queue'    => WPCPM_Semester_Report::queue(),
				'due'      => WPCPM_Semester_Report::due( wp_date( 'Y-m-d' ) ),
				'approved' => WPCPM_Semester_Report::approved_since( $from ),
			),
			'requests'     => array(
				'open'    => $open_requests,
				'closed'  => $closed_requests,
				'overdue' => $overdue_request,
			),
			'locked'       => WPCPM_Institution_Roster::locked_today(),
			'programs'     => self::programs(),
			'health'       => self::health(),
		);
	}

	/**
	 * The eight tiles of the attention strip, from the arrays the cards draw.
	 *
	 * @param array $data What `collect()` returned.
	 * @return array[] `label`, `n`, `card`, keyed in the strip's order.
	 */
	public static function counts( array $data ) {
		return array(
			'applications'       => array( 'label' => __( 'Applications waiting', 'wpcredits-program-manager' ), 'n' => count( $data['applications']['open'] ), 'card' => 'applications' ),
			'agreements'         => array( 'label' => __( 'Agreements to review', 'wpcredits-program-manager' ), 'n' => count( $data['agreements']['awaiting'] ), 'card' => 'agreements' ),
			'overdue_agreements' => array( 'label' => __( 'Agreements overdue', 'wpcredits-program-manager' ), 'n' => (int) $data['agreements']['overdue'], 'card' => 'agreements' ),
			'drafts'             => array( 'label' => __( 'Reports to review', 'wpcredits-program-manager' ), 'n' => count( $data['reports']['queue'] ), 'card' => 'reports' ),
			'due'                => array( 'label' => __( 'Semesters due for drafting', 'wpcredits-program-manager' ), 'n' => count( $data['reports']['due'] ), 'card' => 'reports' ),
			'requests'           => array( 'label' => __( 'Mentor requests open', 'wpcredits-program-manager' ), 'n' => count( $data['requests']['open'] ), 'card' => 'requests' ),
			'overdue_requests'   => array( 'label' => __( 'Mentor requests overdue', 'wpcredits-program-manager' ), 'n' => (int) $data['requests']['overdue'], 'card' => 'requests' ),
			'locked'             => array( 'label' => __( 'Locked accounts', 'wpcredits-program-manager' ), 'n' => count( $data['locked'] ), 'card' => 'health' ),
		);
	}

	/**
	 * The programs running: totals per track, and one row per institution with somebody in progress.
	 *
	 * Reads only `status`, `start`, `end`, `reports` and `mentor_name` off roster rows, and
	 * never Airtable. "Signed up this semester" is a start date in the current half-year;
	 * "finished this semester" is an end date inside it on a past status, and it is one
	 * number rather than one per track because a graduate's status no longer says which
	 * track they were on (the spec's per-track finished count has no source in a row).
	 * Mentors are counted by distinct name: a roster row carries no mentor record ID.
	 *
	 * @return array `tracks`, `finished`, `rows`, `quiet`, `read`, `semester`.
	 */
	public static function programs() {
		$tracked  = WPCPM_Mentors_Sync::tracked_statuses();
		$active   = isset( $tracked['active'] ) ? (array) $tracked['active'] : array();
		$past     = isset( $tracked['past'] ) ? (array) $tracked['past'] : array();
		$semester = WPCPM_Cohort::current();
		$window   = WPCPM_Cohort::range( $semester );
		$tracks   = array();
		$finished = 0;
		$rows     = array();
		$quiet    = 0;
		$read     = 0;

		foreach ( self::TRACKS as $track ) {
			$tracks[ $track ] = array(
				'in_progress' => 0,
				'signed_up'   => 0,
			);
		}

		foreach ( WPCPM_Institutions_Index::rows() as $record => $institution ) {
			$record   = (string) $record;
			$envelope = WPCPM_Roster_Index::read( $record );
			$roster   = isset( $envelope['rows'] ) && is_array( $envelope['rows'] ) ? $envelope['rows'] : array();

			if ( empty( $roster ) ) {
				continue;
			}

			$read        = max( $read, isset( $envelope['read'] ) ? (int) $envelope['read'] : 0 );
			$in_progress = 0;
			$by_status   = array();
			$mentors     = array();
			$waiting     = 0;
			$earliest    = '';
			$latest      = '';

			foreach ( $roster as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$status = isset( $row['status'] ) ? trim( (string) $row['status'] ) : '';

				if ( in_array( $status, WPCPM_Roster_Index::NEVER_SHOWN, true ) ) {
					continue;
				}

				$track = WPCPM_Program::track( $status );
				$end   = self::day( isset( $row['end'] ) ? $row['end'] : '' );

				if ( '' !== $track && WPCPM_Cohort::key( isset( $row['start'] ) ? $row['start'] : '' ) === $semester ) {
					++$tracks[ $track ]['signed_up'];
				}

				if ( in_array( $status, $active, true ) ) {
					++$in_progress;

					$label               = WPCPM_Program::label( $status );
					$by_status[ $label ] = ( isset( $by_status[ $label ] ) ? $by_status[ $label ] : 0 ) + 1;

					if ( '' !== $track ) {
						++$tracks[ $track ]['in_progress'];
					}

					if ( empty( $row['reports'] ) ) {
						++$waiting;
					}

					$mentor = isset( $row['mentor_name'] ) ? trim( (string) $row['mentor_name'] ) : '';

					if ( '' !== $mentor ) {
						$mentors[ $mentor ] = true;
					}

					if ( '' !== $end ) {
						if ( '' === $earliest || $end < $earliest ) {
							$earliest = $end;
						}

						if ( $end > $latest ) {
							$latest = $end;
						}
					}
				} elseif ( in_array( $status, $past, true ) && '' !== $end && $end >= $window['from'] && $end <= $window['to'] ) {
					++$finished;
				}
			}

			if ( 0 === $in_progress ) {
				++$quiet;
				continue;
			}

			$reports = WPCPM_Semester_Report::reports_of( $record );
			$report  = 'none';

			if ( ! empty( $reports ) ) {
				$first  = reset( $reports );
				$report = $first instanceof WP_Post ? WPCPM_Semester_Report::state( $first ) : 'none';
			}

			$summary = WPCPM_Institution_Agreement::summary( $record );

			$rows[] = array(
				'record'      => $record,
				'name'        => self::institution_name( $record ),
				'in_progress' => $in_progress,
				'by_status'   => $by_status,
				'waiting'     => $waiting,
				'mentors'     => count( $mentors ),
				'earliest'    => $earliest,
				'latest'      => $latest,
				'agreement'   => isset( $summary['state'] ) ? (string) $summary['state'] : 'none',
				'report'      => $report,
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return array(
			'tracks'   => $tracks,
			'finished' => $finished,
			'rows'     => $rows,
			'quiet'    => $quiet,
			'read'     => $read,
			'semester' => $semester,
		);
	}

	/**
	 * The syncs, the private storage probe, the last mail and the invitation run.
	 *
	 * @return array
	 */
	public static function health() {
		$syncs = array(
			'students'     => array(
				'label'    => __( 'Students', 'wpcredits-program-manager' ),
				'progress' => WPCPM_Students_Sync::progress(),
				'last'     => (int) get_option( WPCPM_Students_Sync::OPT_LAST, 0 ),
				'next'     => (int) wp_next_scheduled( WPCPM_Students_Sync::CRON_AUTO ),
				'screen'   => admin_url( 'admin.php?page=wpcpm-students' ),
			),
			'mentors'      => array(
				'label'    => __( 'Mentors', 'wpcredits-program-manager' ),
				'progress' => WPCPM_Mentors_Sync::progress(),
				'last'     => (int) get_option( WPCPM_Mentors_Sync::OPT_LAST, 0 ),
				'next'     => (int) wp_next_scheduled( WPCPM_Mentors_Sync::CRON_DAILY ),
				'screen'   => admin_url( 'admin.php?page=wpcpm-mentors' ),
			),
			'institutions' => array(
				'label'    => __( 'Institutions', 'wpcredits-program-manager' ),
				'progress' => WPCPM_Institutions_Sync::progress(),
				'last'     => (int) WPCPM_Institutions_Sync::last_read(),
				'next'     => (int) wp_next_scheduled( WPCPM_Institutions_Sync::CRON_DAILY ),
				'screen'   => admin_url( 'admin.php?page=wpcpm-institutions' ),
			),
		);

		$probe = WPCPM_Private_Files::probe_result();
		$log   = WPCPM_Mail::log();

		return array(
			'syncs'   => $syncs,
			'probe'   => array(
				'verdict' => is_array( $probe ) ? WPCPM_Private_Files::verdict( $probe ) : 'unknown',
				'time'    => is_array( $probe ) && isset( $probe['time'] ) ? (int) $probe['time'] : 0,
			),
			'mail'    => isset( $log[0] ) && is_array( $log[0] ) ? $log[0] : array(),
			'invites' => array(
				'run'    => WPCPM_Mail::run(),
				'queued' => (int) WPCPM_Mail::queued(),
			),
		);
	}

	/*
	 * --------------------------------------------------------------------
	 * Drawing
	 * --------------------------------------------------------------------
	 */

	/**
	 * The attention strip: eight counts, each an anchor to its card. A zero is muted, not
	 * hidden, so the strip always has the same shape and a manager learns where to look.
	 *
	 * @param array $counts What `counts()` returned.
	 */
	public static function render_strip( array $counts ) {
		printf( '<section class="wpcpm-administrator__card wpcpm-attention" id="wpcpm-attention" aria-label="%s">', esc_attr__( 'Needs attention', 'wpcredits-program-manager' ) );
		echo '<ul class="wpcpm-attention__tiles">';

		foreach ( $counts as $tile ) {
			printf(
				'<li class="wpcpm-attention__tile%1$s"><a class="wpcpm-attention__link" href="#wpcpm-%2$s"><span class="wpcpm-attention__n">%3$s</span><span class="wpcpm-attention__l">%4$s</span></a></li>',
				0 === (int) $tile['n'] ? ' wpcpm-attention__tile--zero' : '',
				esc_attr( $tile['card'] ),
				esc_html( number_format_i18n( (int) $tile['n'] ) ),
				esc_html( $tile['label'] )
			);
		}

		echo '</ul></section>';
	}

	/**
	 * Institution applications: the open ones with the six decisions, the closed ones folded.
	 *
	 * @param array $data `open` and `closed`, each `WP_Post[]`.
	 */
	public static function render_applications( array $data ) {
		$open   = isset( $data['open'] ) ? (array) $data['open'] : array();
		$closed = isset( $data['closed'] ) ? (array) $data['closed'] : array();
		$module = class_exists( 'WPCPM_Modules' ) ? WPCPM_Modules::get( 'institutions' ) : null;

		self::card_open( 'applications', __( 'Institution applications', 'wpcredits-program-manager' ), count( $open ) );

		if ( empty( $open ) ) {
			self::empty_line( __( 'No application is waiting.', 'wpcredits-program-manager' ) );
		}

		foreach ( $open as $post ) {
			if ( $post instanceof WP_Post ) {
				self::render_application( $post, $module );
			}
		}

		if ( ! empty( $closed ) ) {
			printf(
				'<details class="wpcpm-administrator__closed"><summary>%s</summary>',
				esc_html(
					sprintf(
						/* translators: %s: a number of applications. */
						_n( '%s rejected or spam application', '%s rejected or spam applications', count( $closed ), 'wpcredits-program-manager' ),
						number_format_i18n( count( $closed ) )
					)
				)
			);

			foreach ( $closed as $post ) {
				if ( $post instanceof WP_Post ) {
					self::render_application( $post, $module );
				}
			}

			echo '</details>';
		}

		self::card_close();
	}

	/**
	 * One application: its facts, its answers behind a disclosure, and the module's decisions.
	 *
	 * @param WP_Post     $post   The application.
	 * @param object|null $module The Institutions module, which owns the answers and the forms.
	 */
	private static function render_application( WP_Post $post, $module ) {
		$state   = WPCPM_Institutions::application_state( $post );
		$country = (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_COUNTRY, true );
		$name    = trim( (string) $post->post_title );
		$at      = (int) get_post_time( 'U', true, $post );
		$manager = WPCPM_Countries::contact_of( $country );

		echo '<article class="wpcpm-administrator__item wpcpm-application">';
		printf(
			'<h4 class="wpcpm-administrator__item-title">%1$s <code class="wpcpm-administrator__ref">%2$s</code></h4>',
			esc_html( '' !== $name ? $name : __( '(no name)', 'wpcredits-program-manager' ) ),
			esc_html( WPCPM_Institutions::application_reference( $post ) )
		);
		echo '<p class="wpcpm-administrator__facts">';
		printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( ucfirst( $state ) ) );
		printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( WPCPM_Institutions::country_name( $country, (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_COUNTRY_NAME, true ) ) ) );

		if ( '' !== $manager ) {
			printf(
				'<span class="wpcpm-administrator__fact">%s</span>',
				esc_html(
					sprintf(
						/* translators: %s: a program manager's name or address. */
						__( 'Routed to %s', 'wpcredits-program-manager' ),
						$manager
					)
				)
			);
		}

		printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( self::received( $at ) ) );
		echo '</p>';

		printf( '<details class="wpcpm-administrator__answers"><summary>%s</summary>', esc_html__( 'Read the application', 'wpcredits-program-manager' ) );

		if ( is_object( $module ) && method_exists( $module, 'render_application_answers' ) ) {
			$module->render_application_answers( $post );
		}

		echo '</details>';

		if ( is_object( $module ) && method_exists( $module, 'render_application_actions' ) ) {
			echo '<div class="wpcpm-administrator__actions">';
			$module->render_application_actions( $post, $state, WPCPM_Return::DASHBOARD );
			echo '</div>';
		}

		echo '</article>';
	}

	/**
	 * Collaboration Agreements: awaiting review with the panel's review block, then the
	 * returned and the revoked, folded.
	 *
	 * @param array $data `awaiting`, `returned`, `revoked`, `overdue`.
	 */
	public static function render_agreements( array $data ) {
		$awaiting = isset( $data['awaiting'] ) ? (array) $data['awaiting'] : array();
		$returned = isset( $data['returned'] ) ? (array) $data['returned'] : array();
		$revoked  = isset( $data['revoked'] ) ? (array) $data['revoked'] : array();

		self::card_open( 'agreements', __( 'Collaboration Agreements', 'wpcredits-program-manager' ), count( $awaiting ) );
		printf( '<h4 class="wpcpm-administrator__subtitle">%s</h4>', esc_html__( 'Awaiting review', 'wpcredits-program-manager' ) );

		if ( empty( $awaiting ) ) {
			self::empty_line( __( 'No signed agreement is waiting.', 'wpcredits-program-manager' ) );
		}

		foreach ( $awaiting as $row ) {
			printf( '<article class="wpcpm-administrator__item wpcpm-agreement%s">', ! empty( $row['overdue'] ) ? ' wpcpm-administrator__item--overdue' : '' );
			printf( '<h4 class="wpcpm-administrator__item-title">%s</h4>', self::institution_link( $row['record'] ) );
			echo '<p class="wpcpm-administrator__facts">';
			printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( self::uploaded( (int) $row['at'] ) ) );

			if ( ! empty( $row['overdue'] ) ) {
				printf( '<span class="wpcpm-administrator__fact wpcpm-administrator__fact--overdue">%s</span>', esc_html__( 'Overdue', 'wpcredits-program-manager' ) );
			}

			echo '</p>';
			WPCPM_Institution_Panel::render_review( (int) $row['id'] );
			echo '</article>';
		}

		self::render_agreement_list( $returned, __( '%s returned, waiting for a new upload', 'wpcredits-program-manager' ), __( '%s returned, waiting for new uploads', 'wpcredits-program-manager' ), false );
		self::render_agreement_list( $revoked, __( '%s revoked agreement', 'wpcredits-program-manager' ), __( '%s revoked agreements', 'wpcredits-program-manager' ), true );

		self::card_close();
	}

	/**
	 * A folded list of returned or revoked agreements; the revoked ones offer Reinstate.
	 *
	 * @param array  $rows      `id`, `record`, `at`, `note`.
	 * @param string $singular  The summary, singular, with %s for the count.
	 * @param string $plural    The summary, plural.
	 * @param bool   $reinstate Whether to draw the Reinstate form.
	 */
	private static function render_agreement_list( array $rows, $singular, $plural, $reinstate ) {
		if ( empty( $rows ) ) {
			return;
		}

		// The sentences are translated by the caller, so _n() would translate them twice;
		// the count picks the form here.
		printf( '<details class="wpcpm-administrator__closed"><summary>%s</summary>', esc_html( sprintf( 1 === count( $rows ) ? $singular : $plural, number_format_i18n( count( $rows ) ) ) ) );

		foreach ( $rows as $row ) {
			echo '<article class="wpcpm-administrator__item wpcpm-agreement">';
			printf( '<h4 class="wpcpm-administrator__item-title">%s</h4>', self::institution_link( $row['record'] ) );
			printf( '<p class="wpcpm-administrator__facts"><span class="wpcpm-administrator__fact">%s</span></p>', esc_html( self::when( (int) $row['at'] ) ) );

			if ( '' !== $row['note'] ) {
				printf( '<p class="wpcpm-administrator__note">%s</p>', esc_html( $row['note'] ) );
			}

			if ( $reinstate ) {
				self::render_reinstate_form( (int) $row['id'] );
			}

			echo '</article>';
		}

		echo '</details>';
	}

	/**
	 * The Reinstate form, drawn here because nothing else draws one: the handler and its nonce
	 * exist in the agreement class, and the queue never offered the button.
	 *
	 * @param int $post_id The revoked document.
	 */
	private static function render_reinstate_form( $post_id ) {
		printf(
			'<form class="wpcpm-administrator__form" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Reinstating', 'wpcredits-program-manager' )
		);
		wp_nonce_field( WPCPM_Institution_Agreement::ACTION_REINSTATE . '_' . (int) $post_id );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( WPCPM_Institution_Agreement::ACTION_REINSTATE ) );
		printf( '<input type="hidden" name="wpcpm_agreement_post" value="%d" />', (int) $post_id );
		printf( '<button type="submit" class="wpcpm-button wpcpm-button--secondary">%s</button>', esc_html__( 'Reinstate', 'wpcredits-program-manager' ) );
		echo '</form>';
	}

	/**
	 * Semester reports: drafts to review, cohorts due for drafting, approved this semester.
	 *
	 * No approve button here: approval belongs in the editor, where the manager has read the
	 * document. Every link opens the Institution Dashboard as that institution.
	 *
	 * @param array $data `queue`, `due`, `approved`.
	 */
	public static function render_reports( array $data ) {
		$queue    = isset( $data['queue'] ) ? (array) $data['queue'] : array();
		$due      = isset( $data['due'] ) ? (array) $data['due'] : array();
		$approved = isset( $data['approved'] ) ? (array) $data['approved'] : array();

		self::card_open( 'reports', __( 'Semester reports', 'wpcredits-program-manager' ), count( $queue ) + count( $due ) );

		printf( '<h4 class="wpcpm-administrator__subtitle">%s</h4>', esc_html__( 'To review', 'wpcredits-program-manager' ) );

		if ( empty( $queue ) ) {
			self::empty_line( __( 'No draft is waiting for review.', 'wpcredits-program-manager' ) );
		} else {
			echo '<table class="wpcpm-admin-table"><thead><tr>';
			printf( '<th scope="col">%s</th>', esc_html__( 'Institution', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Semester', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Drafted', 'wpcredits-program-manager' ) );
			printf( '<th scope="col" class="wpcpm-admin-table__n">%s</th>', esc_html__( 'Still in progress', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Age', 'wpcredits-program-manager' ) );
			echo '<th scope="col"></th></tr></thead><tbody>';

			foreach ( $queue as $row ) {
				printf(
					'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td class="wpcpm-admin-table__n">%4$s</td><td>%5$s</td><td><a class="wpcpm-button wpcpm-button--secondary" href="%6$s">%7$s</a></td></tr>',
					self::institution_link( $row['institution'] ),
					esc_html( WPCPM_Cohort::label( $row['cohort'] ) ),
					esc_html( self::drafted( (int) $row['generated'], (string) $row['origin'] ) ),
					esc_html( number_format_i18n( (int) $row['in_progress'] ) ),
					esc_html( sprintf( /* translators: %s: a number of days. */ _n( '%s day', '%s days', (int) $row['age_days'], 'wpcredits-program-manager' ), number_format_i18n( (int) $row['age_days'] ) ) ),
					esc_url( self::report_link( $row['institution'], $row['cohort'] ) ),
					esc_html__( 'Review', 'wpcredits-program-manager' )
				);
			}

			echo '</tbody></table>';
		}

		printf( '<h4 class="wpcpm-administrator__subtitle">%s</h4>', esc_html__( 'Due for drafting', 'wpcredits-program-manager' ) );

		if ( empty( $due ) ) {
			self::empty_line( __( 'Nothing is waiting: every finished semester has a report, or is not finished yet.', 'wpcredits-program-manager' ) );
		} else {
			echo '<table class="wpcpm-admin-table"><thead><tr>';
			printf( '<th scope="col">%s</th>', esc_html__( 'Institution', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Semester', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Window ended', 'wpcredits-program-manager' ) );
			printf( '<th scope="col" class="wpcpm-admin-table__n">%s</th>', esc_html__( 'Still in progress', 'wpcredits-program-manager' ) );
			echo '<th scope="col"></th></tr></thead><tbody>';

			foreach ( $due as $pair ) {
				printf(
					'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td class="wpcpm-admin-table__n">%4$s</td><td>',
					self::institution_link( $pair['institution'] ),
					esc_html( WPCPM_Cohort::label( $pair['cohort'] ) ),
					esc_html( $pair['window_end'] ),
					esc_html( number_format_i18n( (int) $pair['in_progress'] ) )
				);
				WPCPM_Semester_Report_Screen::render_draft_form( $pair['institution'], $pair['cohort'] );
				echo '</td></tr>';
			}

			echo '</tbody></table>';
		}

		printf( '<h4 class="wpcpm-administrator__subtitle">%s</h4>', esc_html__( 'Approved this semester', 'wpcredits-program-manager' ) );

		if ( empty( $approved ) ) {
			self::empty_line( __( 'No report has been approved this semester yet.', 'wpcredits-program-manager' ) );
		} else {
			echo '<ul class="wpcpm-administrator__list">';

			foreach ( $approved as $row ) {
				$by = ! empty( $row['approved_by'] ) ? get_user_by( 'id', (int) $row['approved_by'] ) : false;

				printf(
					'<li>%1$s, %2$s: %3$s <a href="%4$s">%5$s</a></li>',
					self::institution_link( $row['institution'] ),
					esc_html( WPCPM_Cohort::label( $row['cohort'] ) ),
					esc_html(
						$by instanceof WP_User
							? sprintf( /* translators: 1: a date, 2: a program manager's name. */ __( 'approved on %1$s by %2$s', 'wpcredits-program-manager' ), self::when( (int) $row['approved_at'] ), $by->display_name )
							: sprintf( /* translators: %s: a date. */ __( 'approved on %s', 'wpcredits-program-manager' ), self::when( (int) $row['approved_at'] ) )
					),
					esc_url( self::report_link( $row['institution'], $row['cohort'] ) ),
					esc_html__( 'View', 'wpcredits-program-manager' )
				);
			}

			echo '</ul>';
		}

		self::card_close();
	}

	/**
	 * Mentor requests: the open ones with the request class's two decisions, the closed folded.
	 *
	 * @param array $data `open`, `closed` (facts rows), `overdue`.
	 */
	public static function render_requests( array $data ) {
		$open   = isset( $data['open'] ) ? (array) $data['open'] : array();
		$closed = isset( $data['closed'] ) ? (array) $data['closed'] : array();

		self::card_open( 'requests', __( 'Mentor requests', 'wpcredits-program-manager' ), count( $open ) );

		if ( empty( $open ) ) {
			self::empty_line( __( 'No request is open.', 'wpcredits-program-manager' ) );
		}

		foreach ( $open as $facts ) {
			printf( '<article class="wpcpm-administrator__item wpcpm-request%s">', ! empty( $facts['overdue'] ) ? ' wpcpm-administrator__item--overdue' : '' );
			printf( '<h4 class="wpcpm-administrator__item-title">%1$s <span class="wpcpm-administrator__kind">%2$s</span></h4>', self::institution_link( $facts['institution'] ), esc_html( $facts['kind_label'] ) );
			echo '<p class="wpcpm-administrator__facts">';
			printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( sprintf( /* translators: %s: how long ago. */ __( 'Opened %s ago', 'wpcredits-program-manager' ), human_time_diff( (int) $facts['at'], time() ) ) ) );

			if ( ! empty( $facts['overdue'] ) ) {
				printf( '<span class="wpcpm-administrator__fact wpcpm-administrator__fact--overdue">%s</span>', esc_html__( 'Overdue', 'wpcredits-program-manager' ) );
			}

			echo '</p>';

			if ( '' !== (string) $facts['note'] ) {
				printf( '<p class="wpcpm-administrator__note">%s</p>', esc_html( $facts['note'] ) );
			}

			WPCPM_Institution_Request::render_decisions( (int) $facts['id'], WPCPM_Return::DASHBOARD );
			echo '</article>';
		}

		if ( ! empty( $closed ) ) {
			printf( '<details class="wpcpm-administrator__closed"><summary>%s</summary><ul class="wpcpm-administrator__list">', esc_html__( 'Recently closed', 'wpcredits-program-manager' ) );

			foreach ( $closed as $facts ) {
				printf(
					'<li>%1$s, %2$s: %3$s, %4$s</li>',
					self::institution_link( $facts['institution'] ),
					esc_html( $facts['kind_label'] ),
					esc_html( WPCPM_Institution_Request::STATE_DONE === $facts['state'] ? __( 'Handled', 'wpcredits-program-manager' ) : __( 'Declined', 'wpcredits-program-manager' ) ),
					esc_html( self::when( (int) $facts['at'] ) )
				);
			}

			echo '</ul></details>';
		}

		self::card_close();
	}

	/**
	 * The programs running: a tile per track and one for the finished, then the institutions.
	 *
	 * @param array $programs What `programs()` returned.
	 */
	public static function render_programs( array $programs ) {
		$rows = isset( $programs['rows'] ) ? (array) $programs['rows'] : array();

		self::card_open( 'programs', __( 'Programs running', 'wpcredits-program-manager' ), count( $rows ) );

		$names = array(
			'150h' => WPCPM_Program::label( WPCPM_Program::STATUS_150H ),
			'50h'  => WPCPM_Program::label( WPCPM_Program::STATUS_50H ),
			'dev'  => WPCPM_Program::label( WPCPM_Program::STATUS_DEV ),
		);

		echo '<ul class="wpcpm-programs__tiles">';

		foreach ( self::TRACKS as $track ) {
			$tile = isset( $programs['tracks'][ $track ] ) ? $programs['tracks'][ $track ] : array( 'in_progress' => 0, 'signed_up' => 0 );

			printf(
				'<li class="wpcpm-programs__tile"><span class="wpcpm-programs__name">%1$s</span><span class="wpcpm-programs__n">%2$s</span><span class="wpcpm-programs__l">%3$s</span></li>',
				esc_html( $names[ $track ] ),
				esc_html( number_format_i18n( (int) $tile['in_progress'] ) ),
				esc_html( sprintf( /* translators: %s: a number of students. */ __( 'in progress, %s signed up this semester', 'wpcredits-program-manager' ), number_format_i18n( (int) $tile['signed_up'] ) ) )
			);
		}

		printf(
			'<li class="wpcpm-programs__tile"><span class="wpcpm-programs__name">%1$s</span><span class="wpcpm-programs__n">%2$s</span><span class="wpcpm-programs__l">%3$s</span></li>',
			esc_html__( 'Finished this semester', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( isset( $programs['finished'] ) ? (int) $programs['finished'] : 0 ) ),
			esc_html__( 'across every track', 'wpcredits-program-manager' )
		);
		echo '</ul>';

		if ( empty( $rows ) ) {
			self::empty_line( __( 'No institution has a student in progress.', 'wpcredits-program-manager' ) );
		} else {
			echo '<table class="wpcpm-admin-table wpcpm-programs__table"><thead><tr>';
			printf( '<th scope="col">%s</th>', esc_html__( 'Institution', 'wpcredits-program-manager' ) );
			printf( '<th scope="col" class="wpcpm-admin-table__n">%s</th>', esc_html__( 'In progress', 'wpcredits-program-manager' ) );
			printf( '<th scope="col" class="wpcpm-admin-table__n">%s</th>', esc_html__( 'Mentors', 'wpcredits-program-manager' ) );
			printf( '<th scope="col" class="wpcpm-admin-table__n">%s</th>', esc_html__( 'Waiting for a mentor', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'End dates', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Agreement', 'wpcredits-program-manager' ) );
			printf( '<th scope="col">%s</th>', esc_html__( 'Report', 'wpcredits-program-manager' ) );
			echo '</tr></thead><tbody>';

			foreach ( $rows as $row ) {
				$breakdown = array();

				foreach ( $row['by_status'] as $label => $n ) {
					$breakdown[] = number_format_i18n( (int) $n ) . ' ' . $label;
				}

				printf(
					'<tr><td>%1$s</td><td class="wpcpm-admin-table__n">%2$s<span class="wpcpm-admin-table__breakdown">%3$s</span></td><td class="wpcpm-admin-table__n">%4$s</td><td class="wpcpm-admin-table__n">%5$s</td><td>%6$s</td><td>%7$s</td><td>%8$s</td></tr>',
					self::institution_link( $row['record'] ),
					esc_html( number_format_i18n( (int) $row['in_progress'] ) ),
					esc_html( implode( ', ', $breakdown ) ),
					esc_html( number_format_i18n( (int) $row['mentors'] ) ),
					esc_html( number_format_i18n( (int) $row['waiting'] ) ),
					esc_html( '' === $row['earliest'] ? __( 'no end date', 'wpcredits-program-manager' ) : ( $row['earliest'] === $row['latest'] ? $row['earliest'] : $row['earliest'] . ' to ' . $row['latest'] ) ),
					esc_html( str_replace( '_', ' ', (string) $row['agreement'] ) ),
					esc_html( (string) $row['report'] )
				);
			}

			echo '</tbody></table>';
		}

		if ( ! empty( $programs['quiet'] ) ) {
			printf(
				'<p class="wpcpm-administrator__quiet">%s</p>',
				esc_html( sprintf( /* translators: %s: a number of institutions. */ _n( '%s more institution with no student in progress.', '%s more institutions with no student in progress.', (int) $programs['quiet'], 'wpcredits-program-manager' ), number_format_i18n( (int) $programs['quiet'] ) ) )
			);
		}

		printf(
			'<p class="wpcpm-administrator__read">%s</p>',
			esc_html(
				empty( $programs['read'] )
					? __( 'These students have not been read from the program records yet.', 'wpcredits-program-manager' )
					: sprintf( /* translators: 1: date and time, 2: how long ago. */ __( 'Read from the program records on %1$s (%2$s ago).', 'wpcredits-program-manager' ), self::when( (int) $programs['read'] ), human_time_diff( (int) $programs['read'], time() ) )
			)
		);

		self::card_close();
	}

	/**
	 * Syncs and health: read-only, every row linking out to the screen that owns it.
	 *
	 * @param array     $health What `health()` returned.
	 * @param WP_User[] $locked Accounts the roster's refusal ceiling locked today.
	 */
	public static function render_health( array $health, array $locked ) {
		$syncs = isset( $health['syncs'] ) ? (array) $health['syncs'] : array();

		self::card_open( 'health', __( 'Syncs and health', 'wpcredits-program-manager' ), count( $locked ) );

		echo '<table class="wpcpm-admin-table"><thead><tr>';
		printf( '<th scope="col">%s</th>', esc_html__( 'Sync', 'wpcredits-program-manager' ) );
		printf( '<th scope="col">%s</th>', esc_html__( 'State', 'wpcredits-program-manager' ) );
		printf( '<th scope="col">%s</th>', esc_html__( 'Last run', 'wpcredits-program-manager' ) );
		printf( '<th scope="col">%s</th>', esc_html__( 'Next run', 'wpcredits-program-manager' ) );
		printf( '<th scope="col">%s</th>', esc_html__( 'Last error', 'wpcredits-program-manager' ) );
		echo '<th scope="col"></th></tr></thead><tbody>';

		foreach ( $syncs as $sync ) {
			$progress = isset( $sync['progress'] ) && is_array( $sync['progress'] ) ? $sync['progress'] : array();
			$state    = ! empty( $progress['running'] )
				? sprintf( /* translators: %s: what the sync is doing. */ __( 'Running: %s', 'wpcredits-program-manager' ), isset( $progress['label'] ) ? (string) $progress['label'] : '' )
				: __( 'Idle', 'wpcredits-program-manager' );

			printf(
				'<tr class="wpcpm-health__sync"><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td><td>%5$s</td><td><a href="%6$s">%7$s</a></td></tr>',
				esc_html( $sync['label'] ),
				esc_html( $state ),
				esc_html( empty( $sync['last'] ) ? __( 'never', 'wpcredits-program-manager' ) : self::when( (int) $sync['last'] ) ),
				esc_html( empty( $sync['next'] ) ? __( 'not scheduled', 'wpcredits-program-manager' ) : self::when( (int) $sync['next'] ) ),
				esc_html( isset( $progress['error'] ) ? (string) $progress['error'] : '' ),
				esc_url( $sync['screen'] ),
				esc_html__( 'Open', 'wpcredits-program-manager' )
			);
		}

		echo '</tbody></table>';

		printf( '<h4 class="wpcpm-administrator__subtitle">%s</h4>', esc_html__( 'Locked accounts', 'wpcredits-program-manager' ) );

		if ( empty( $locked ) ) {
			self::empty_line( __( 'No account is locked today.', 'wpcredits-program-manager' ) );
		} else {
			$names = array();

			foreach ( $locked as $user ) {
				if ( $user instanceof WP_User ) {
					$names[] = $user->display_name . ' (' . $user->user_login . ')';
				}
			}

			printf(
				'<p class="wpcpm-administrator__note">%1$s %2$s</p>',
				esc_html( implode( ', ', $names ) ),
				esc_html__( 'Each was refused more claims than the daily ceiling allows; the lock lifts by itself tomorrow.', 'wpcredits-program-manager' )
			);
		}

		$probe   = isset( $health['probe'] ) ? (array) $health['probe'] : array( 'verdict' => 'unknown', 'time' => 0 );
		$mail    = isset( $health['mail'] ) ? (array) $health['mail'] : array();
		$invites = isset( $health['invites'] ) ? (array) $health['invites'] : array( 'run' => array(), 'queued' => 0 );

		echo '<ul class="wpcpm-administrator__list">';
		printf(
			'<li>%s</li>',
			esc_html( sprintf( /* translators: 1: the verdict, 2: a date. */ __( 'Private storage: %1$s (probed %2$s).', 'wpcredits-program-manager' ), $probe['verdict'], empty( $probe['time'] ) ? __( 'never', 'wpcredits-program-manager' ) : self::when( (int) $probe['time'] ) ) )
		);
		printf(
			'<li>%s</li>',
			esc_html(
				empty( $mail )
					? __( 'No mail has been sent yet.', 'wpcredits-program-manager' )
					: sprintf( /* translators: 1: a mail context, 2: a masked address, 3: a date, 4: sent or failed. */ __( 'Last mail: %1$s to %2$s on %3$s, %4$s.', 'wpcredits-program-manager' ), isset( $mail['context'] ) ? $mail['context'] : '', isset( $mail['to'] ) ? $mail['to'] : '', self::when( isset( $mail['time'] ) ? (int) $mail['time'] : 0 ), ! empty( $mail['sent'] ) ? __( 'sent', 'wpcredits-program-manager' ) : __( 'failed', 'wpcredits-program-manager' ) )
			)
		);

		if ( ! empty( $invites['run'] ) && isset( $invites['run']['total'] ) ) {
			$total = (int) $invites['run']['total'];
			$sent  = max( 0, $total - (int) $invites['queued'] );

			printf(
				'<li>%s</li>',
				esc_html( sprintf( /* translators: 1: messages sent, 2: messages in the run. */ __( 'Invitations: %1$s of %2$s sent.', 'wpcredits-program-manager' ), number_format_i18n( $sent ), number_format_i18n( $total ) ) )
			);
		}

		echo '</ul>';

		self::card_close();
	}

	/*
	 * --------------------------------------------------------------------
	 * The small pieces
	 * --------------------------------------------------------------------
	 */

	/**
	 * Open one card: the canonical disclosure, open when there is something in it.
	 *
	 * The extra class comes before the two shared ones so the attribute still ends in
	 * `wpcpm-group__disclosure`, which is what the suites and the theme's rules key on.
	 *
	 * @param string $id    The anchor, one of WPCPM_Return::ANCHORS.
	 * @param string $title The heading.
	 * @param int    $count What the badge says; open when above zero.
	 */
	private static function card_open( $id, $title, $count ) {
		printf( '<section class="wpcpm-administrator__card" id="wpcpm-%s">', esc_attr( $id ) );
		printf( '<details class="wpcpm-administrator__disclosure wpcpm-group wpcpm-group__disclosure"%s>', (int) $count > 0 ? ' open' : '' );
		printf(
			'<summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">%1$s <span class="wpcpm-group__count">%2$s</span></h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>',
			esc_html( $title ),
			esc_html( number_format_i18n( (int) $count ) )
		);
		echo '<div class="wpcpm-group__body">';
	}

	/** Close the card `card_open()` opened. */
	private static function card_close() {
		echo '</div></details></section>';
	}

	/**
	 * An empty half under a heading reads as something that failed to load, so it says so.
	 *
	 * @param string $text The sentence.
	 */
	private static function empty_line( $text ) {
		printf( '<p class="wpcpm-administrator__empty">%s</p>', esc_html( $text ) );
	}

	/**
	 * Rows for a folded agreement list.
	 *
	 * @param int[] $ids Post IDs.
	 * @return array[] `id`, `record`, `at`, `note`.
	 */
	private static function agreement_rows( array $ids ) {
		$rows = array();

		foreach ( $ids as $post_id ) {
			$post = get_post( (int) $post_id );

			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$rows[] = array(
				'id'     => (int) $post->ID,
				'record' => (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Agreement::META_INSTITUTION, true ),
				'at'     => (int) get_post_modified_time( 'U', true, $post ),
				'note'   => trim( (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Agreement::META_NOTE, true ) ),
			);
		}

		return $rows;
	}

	/**
	 * The institution's name from the index, trimmed; the record ID when the index lost it.
	 *
	 * @param string $record Institutions record ID.
	 * @return string
	 */
	private static function institution_name( $record ) {
		$row  = WPCPM_Institutions_Index::row( $record );
		$name = is_array( $row ) && isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';

		return '' !== $name ? $name : (string) $record;
	}

	/**
	 * The institution's name, linked to its dashboard through the switcher argument.
	 *
	 * Escaped here: every caller prints it into markup as is.
	 *
	 * @param string $record Institutions record ID.
	 * @return string
	 */
	private static function institution_link( $record ) {
		$page = class_exists( 'WPCPM_Institutions_Dashboard' ) ? (string) WPCPM_Institutions_Dashboard::page_url() : '';
		$name = esc_html( self::institution_name( $record ) );

		if ( '' === $page || '' === (string) $record ) {
			return $name;
		}

		return sprintf( '<a href="%1$s">%2$s</a>', esc_url( add_query_arg( WPCPM_Institution_Roster::ARG_VIEW, (string) $record, $page ) ), $name );
	}

	/**
	 * The report editor on the Institution Dashboard, as that institution.
	 *
	 * @param string $record Institutions record ID.
	 * @param string $cohort Cohort key.
	 * @return string
	 */
	private static function report_link( $record, $cohort ) {
		$url = (string) WPCPM_Semester_Report_Screen::report_url( $cohort );

		return '' === $url ? '' : add_query_arg( WPCPM_Institution_Roster::ARG_VIEW, (string) $record, $url );
	}

	/**
	 * A date-only string's leading `Y-m-d`, or '' for anything else.
	 *
	 * @param mixed $value A roster row's date.
	 * @return string
	 */
	private static function day( $value ) {
		return preg_match( '/^(\d{4}-\d{2}-\d{2})/', trim( (string) $value ), $found ) ? $found[1] : '';
	}

	/**
	 * A date and time in the site's format.
	 *
	 * @param int $timestamp Unix time.
	 * @return string
	 */
	private static function when( $timestamp ) {
		return (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $timestamp );
	}

	/**
	 * "Received <when> (<ago>)".
	 *
	 * @param int $timestamp Unix time.
	 * @return string
	 */
	private static function received( $timestamp ) {
		/* translators: 1: date and time, 2: how long ago. */
		return sprintf( __( 'Received %1$s (%2$s ago)', 'wpcredits-program-manager' ), self::when( $timestamp ), human_time_diff( (int) $timestamp, time() ) );
	}

	/**
	 * "Uploaded <when> (<ago>)".
	 *
	 * @param int $timestamp Unix time.
	 * @return string
	 */
	private static function uploaded( $timestamp ) {
		/* translators: 1: date and time, 2: how long ago. */
		return sprintf( __( 'Uploaded %1$s (%2$s ago)', 'wpcredits-program-manager' ), self::when( $timestamp ), human_time_diff( (int) $timestamp, time() ) );
	}

	/**
	 * "<when>, by the site" or "<when>, by a manager".
	 *
	 * @param int    $timestamp Unix time.
	 * @param string $origin    `auto` or `manager`.
	 * @return string
	 */
	private static function drafted( $timestamp, $origin ) {
		return self::when( $timestamp ) . ', ' . ( 'auto' === $origin ? __( 'by the site', 'wpcredits-program-manager' ) : __( 'by a manager', 'wpcredits-program-manager' ) );
	}
}
```

Add the require line to `wpcredits-program-manager.php` immediately before the line requiring `includes/modules/class-wpcpm-administrators.php`, and the matching `plugin_dir_path( __FILE__ )` line to `uninstall.php`.

- [ ] **Step 4: Run the suite, the reference check and the roles suite**

Run: `php bin/test-administrators-dashboard.php 2>&1 | grep -E "^FAIL|PASS|FAILED" ; php bin/check-references.php | tail -1; php bin/test-roles.php 2>&1 | tail -1`
Expected: `ALL PASS`, every reference resolves, roles suite `ALL PASS` (the require lists agree).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "Task 3: WPCPM_Administrators_Cards, the data and the six cards

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 4: `WPCPM_Administrators_Dashboard`: the page, the block, the base stylesheet, the module wiring

**Files:**
- Create: `includes/modules/class-wpcpm-administrators-dashboard.php`, `blocks/administrator-dashboard/block.json`, `blocks/administrator-dashboard/editor.asset.php`, `blocks/administrator-dashboard/editor.js`, `assets/css/administrator.css`
- Modify: `includes/modules/class-wpcpm-administrators.php` (add `boot()`, `activate()`, `uninstall()`, a link on the wp-admin screen), `wpcredits-program-manager.php` and `uninstall.php` (require the dashboard file right after the cards file), `bin/test-roles.php:338-383` (the per-dashboard option check, duplicated for this page)
- Test: `bin/test-administrators-dashboard.php` (append the page and render assertions)

**Interfaces:**
- Consumes: Task 3's `WPCPM_Administrators_Cards`; `WPCPM_Dashboards::nothing_to_show( 'administrators', $can_manage )` (Task 2); `WPCPM_Institutions::queue_messages()`, `WPCPM_Institution_Panel::messages()`, `WPCPM_Institution_Request::messages()`, `WPCPM_Sync_Module::sync_messages()`, `WPCPM_Flash::take( WPCPM_Institutions::FLASH )`; `WPCPM_Two_Factor::prompt( $user )`; `WPCPM_Handbook_Assistant::render_resources( 'administrator' )`; `WPCPM_Content_Access::META_KEY`; `WPCPM_Roles::ROLE_ADMIN`, `CAP_MANAGE`; `WPCPM_PLUGIN_DIR`, `WPCPM_PLUGIN_URL`, `WPCPM_VERSION`.
- Produces: `WPCPM_Administrators_Dashboard::SLUG`, `BLOCK`, `SHORTCODE`, `OPT_PAGE`, `OPT_TITLE_FIXED`, `STYLE = 'wpcpm-administrator-dashboard'`, `init()`, `register()`, `render_block( $attributes )`, `render( $attributes = array() ): string`, `ensure_page(): int`, `maybe_rename_page()`, `page_url(): string`; the theme's Task 5 keys on `STYLE`, `BLOCK`, `SHORTCODE` and `OPT_PAGE`.

- [ ] **Step 1: Append the page assertions to the suite**

At the end of `bin/test-administrators-dashboard.php` (before any summary line; the file has none yet), add the requires and the assertions. Find how `bin/test-institutions-dashboard.php` asserts that `forms.js` is enqueued (grep it for `wpcpm-forms`) and use the same global in the last check below.

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators-dashboard.php';

echo "\n=== The page ===\n";

$page_id = WPCPM_Administrators_Dashboard::ensure_page();
ck( 'ensure_page() creates the page and records it', $page_id > 0 && (int) get_option( 'wpcpm_administrator_page_id' ) === $page_id, true );
ck( 'with the slug, the title and the block', array( $GLOBALS['posts'][ $page_id ]->post_name, $GLOBALS['posts'][ $page_id ]->post_title, $GLOBALS['posts'][ $page_id ]->post_content ), array( 'administrator-dashboard', 'Administrator Dashboard', '<!-- wp:wpcpm/administrator-dashboard /-->' ) );
ck( 'gated to administrators, and the meta exists rather than defaults', array( get_post_meta( $page_id, '_wpcpm_access_level', true ), metadata_exists( 'post', $page_id, '_wpcpm_access_level' ) ), array( 'administrator', true ) );
ck( 'a second call inserts nothing', WPCPM_Administrators_Dashboard::ensure_page(), $page_id );
update_post_meta( $page_id, '_wpcpm_access_level', 'public' );
WPCPM_Administrators_Dashboard::ensure_page();
ck( 'a level the site set is never re-gated', get_post_meta( $page_id, '_wpcpm_access_level', true ), 'public' );
update_post_meta( $page_id, '_wpcpm_access_level', 'administrator' );
unset( $GLOBALS['opts']['wpcpm_administrator_page_id'] );
ck( 'a lost option adopts the page by slug', WPCPM_Administrators_Dashboard::ensure_page(), $page_id );
ck( 'page_url() is the permalink', WPCPM_Administrators_Dashboard::page_url(), get_permalink( $page_id ) );

echo "\n=== The render ===\n";

$GLOBALS['uid']    = 0;
$GLOBALS['manage'] = false;
$out = WPCPM_Administrators_Dashboard::render( array() );
ck( 'logged out: a notice and no card', has( $out, 'wpcpm-dashboard--notice' ) && ! has( $out, 'wpcpm-attention' ), true );

$GLOBALS['uid']    = 21;
$GLOBALS['manage'] = false;
$out = WPCPM_Administrators_Dashboard::render( array() );
ck( 'a signed-in non-manager gets the refusal and no form', has( $out, 'cannot manage the program' ) && ! has( $out, '<form' ), true );

$GLOBALS['uid']       = 3;
$GLOBALS['manage']    = true;
$GLOBALS['flash']     = array( 'institutions' => 'app-approved' );
$GLOBALS['prompted']  = array();
$GLOBALS['resources'] = array();
$GLOBALS['reviews']   = array();
$out = WPCPM_Administrators_Dashboard::render( array( 'title' => 'Today' ) );
ck( 'the manager gets the page with the title', has( $out, 'class="wpcpm-dashboard wpcpm-administrator"' ) && has( $out, '<h2 class="wpcpm-dashboard__title">Today</h2>' ), true );
ck( 'the two-factor prompt is for the viewer', $GLOBALS['prompted'], array( 3 ) );
ck( 'the flash on the institutions channel is drawn in the queue\'s words', has( $out, 'The application is approved.' ) && has( $out, 'wpcpm-dashboard__message--success' ), true );
ck( 'and taken, so it shows once', isset( $GLOBALS['flash']['institutions'] ), false );
$positions = array();
foreach ( array( 'id="wpcpm-attention"', 'id="wpcpm-applications"', 'id="wpcpm-agreements"', 'id="wpcpm-reports"', 'id="wpcpm-requests"', 'id="wpcpm-programs"', 'id="wpcpm-health"', 'data-audience="administrator"' ) as $needle ) {
	$positions[] = strpos( $out, $needle );
}
$sorted = $positions;
sort( $sorted );
ck( 'every card is drawn, in the spec\'s order, the resources last', ! in_array( false, $positions, true ) && $positions === $sorted, true );
ck( 'the resources are the administrator audience', $GLOBALS['resources'], array( 'administrator' ) );
ck( 'the agreement reviews were drawn once each', $GLOBALS['reviews'], array( 601, 602 ) );
ck( 'the stylesheet is registered from assets/css/administrator.css', isset( $GLOBALS['styles'][ WPCPM_Administrators_Dashboard::STYLE ] ) && false !== strpos( $GLOBALS['styles'][ WPCPM_Administrators_Dashboard::STYLE ]['src'], 'assets/css/administrator.css' ), true );
ck( 'and render() switched it on', ! empty( $GLOBALS['styles'][ WPCPM_Administrators_Dashboard::STYLE ]['on'] ), true );
ck( 'the dashboard arms the double-submit guard its forms carry', in_array( 'wpcpm-forms', $GLOBALS['scripts'], true ), true );

echo "\n=== The toolbar and the refusal ===\n";

$GLOBALS['manage'] = true;
ck( 'a manager\'s toolbar lists the Administrator Dashboard', in_array( 'wpcpm-administrator-dashboard', array_column( WPCPM_Dashboards::links(), 'id' ), true ), true );
$GLOBALS['manage'] = false;
ck( 'and a member\'s does not', in_array( 'wpcpm-administrator-dashboard', array_column( WPCPM_Dashboards::links(), 'id' ), true ), false );
ck( 'the refusal names the audience, not the Mentor role', WPCPM_Dashboards::nothing_to_show( 'administrators', false ), 'This page is for the program managers. Your account cannot manage the program.' );
ck( 'and a manager with nothing is pointed at the Administrators screen', has( WPCPM_Dashboards::nothing_to_show( 'administrators', true ), 'page=wpcpm-administrators' ), true );

echo "\n=== The wiring ===\n";

$module_src = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-administrators.php' );
ck( 'the module boots the dashboard, ensures the page on activation and deletes its options on uninstall', array( has( $module_src, 'WPCPM_Administrators_Dashboard::init()' ), has( $module_src, 'WPCPM_Administrators_Dashboard::ensure_page()' ), has( $module_src, 'WPCPM_Administrators_Dashboard::OPT_PAGE' ), has( $module_src, 'WPCPM_Administrators_Dashboard::OPT_TITLE_FIXED' ) ), array( true, true, true, true ) );
$block = json_decode( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'blocks/administrator-dashboard/block.json' ), true );
$asset = include WPCPM_PLUGIN_DIR . 'blocks/administrator-dashboard/editor.asset.php';
ck( 'the block is named for the class and versioned with the release', array( $block['name'], $block['version'], $asset['version'] ), array( 'wpcpm/administrator-dashboard', '1.92.0', '1.92.0' ) );
$dashes = array();
foreach ( array( 'includes/modules/class-wpcpm-administrators-cards.php', 'includes/modules/class-wpcpm-administrators-dashboard.php', 'includes/class-wpcpm-return.php', 'assets/css/administrator.css', 'blocks/administrator-dashboard/editor.js', 'bin/test-administrators-dashboard.php' ) as $relative ) {
	if ( preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( WPCPM_PLUGIN_DIR . $relative ) ) ) {
		$dashes[] = $relative;
	}
}
ck( 'no dash but the plain hyphen in any new file', $dashes, array() );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILED', $fail ) : 'ALL PASS', $total );
exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php bin/test-administrators-dashboard.php 2>&1 | tail -3`
Expected: a fatal, `includes/modules/class-wpcpm-administrators-dashboard.php` does not exist.

- [ ] **Step 3: Write the dashboard class**

Create `includes/modules/class-wpcpm-administrators-dashboard.php`:

```php
<?php
/**
 * The Administrator Dashboard: every queue on one page, for program managers.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The page program managers work from.
 *
 * Built exactly like the Institution Dashboard (design of 4 September 2026, decision 1):
 * one page the module creates and adopts by slug, gated to the administrator level with
 * `metadata_exists()`, a block and a shortcode that both reach `render()`, and one
 * `render()` that calls the cards in order. The order of the calls is the spec's, and it
 * is the whole of it.
 */
final class WPCPM_Administrators_Dashboard {
	/** The shortcode, for a page the plugin did not create. */
	const SHORTCODE = 'wpcpm_administrator_dashboard';
	/** The block. */
	const BLOCK = 'wpcpm/administrator-dashboard';
	/** The page the plugin created, by ID. */
	const OPT_PAGE = 'wpcpm_administrator_page_id';
	/** The stylesheet handle; the theme's skin depends on it when it is registered. */
	const STYLE = 'wpcpm-administrator-dashboard';
	/** The slug, which is also the theme template's name: `page-administrator-dashboard.html`. */
	const SLUG = 'administrator-dashboard';
	/** Whether the title rename for TITLE_VERSION has run. */
	const OPT_TITLE_FIXED = 'wpcpm_administrator_page_title_fixed';
	/** Bumped when the product renames the page; `maybe_rename_page()` follows once. */
	const TITLE_VERSION = 1;
	/** Titles a previous version gave the page. None yet. */
	const OLD_TITLES = array();
	/** The module id `WPCPM_Dashboards::nothing_to_show()` knows. */
	const MODULE = 'administrators';

	/**
	 * Hooks. Called from `WPCPM_Administrators::boot()`.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'maybe_rename_page' ), 20 );
	}

	/**
	 * The stylesheet, the shortcode and the block.
	 */
	public static function register() {
		wp_register_style( self::STYLE, WPCPM_PLUGIN_URL . 'assets/css/administrator.css', array(), WPCPM_VERSION );
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );

		$block_dir = WPCPM_PLUGIN_DIR . 'blocks/administrator-dashboard';

		if ( function_exists( 'register_block_type' ) && file_exists( $block_dir . '/block.json' ) ) {
			register_block_type( $block_dir, array( 'render_callback' => array( __CLASS__, 'render_block' ) ) );
		}
	}

	/**
	 * The block's render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		return self::render( is_array( $attributes ) ? $attributes : array() );
	}

	/**
	 * The page title, written once.
	 *
	 * @return string
	 */
	public static function title() {
		return __( 'Administrator Dashboard', 'wpcredits-program-manager' );
	}

	/**
	 * Rename the page once per TITLE_VERSION, only when it still carries an old title.
	 */
	public static function maybe_rename_page() {
		if ( (int) get_option( self::OPT_TITLE_FIXED ) >= self::TITLE_VERSION ) {
			return;
		}

		// Written first, whatever happens next: a rename that fails should not be retried
		// on every request of every visitor.
		update_option( self::OPT_TITLE_FIXED, self::TITLE_VERSION, false );

		$page_id = (int) get_option( self::OPT_PAGE );
		$page    = $page_id ? get_post( $page_id ) : null;

		if ( $page instanceof WP_Post && in_array( (string) $page->post_title, self::OLD_TITLES, true ) ) {
			wp_update_post(
				array(
					'ID'         => $page_id,
					'post_title' => self::title(),
				)
			);
		}
	}

	/**
	 * The page: the stored one, else the one at the slug, else a new one. Gated either way.
	 *
	 * A site that has the page but not the option (restored from a backup, or migrated)
	 * adopts it rather than creating a second one at `administrator-dashboard-2`.
	 *
	 * @return int The page ID, or 0.
	 */
	public static function ensure_page() {
		$page_id = (int) get_option( self::OPT_PAGE );

		if ( $page_id ) {
			$page = get_post( $page_id );

			if ( $page instanceof WP_Post && 'trash' !== $page->post_status ) {
				self::gate_page( $page_id );

				return $page_id;
			}
		}

		$existing = get_page_by_path( self::SLUG );

		if ( $existing instanceof WP_Post && 'trash' !== $existing->post_status ) {
			update_option( self::OPT_PAGE, (int) $existing->ID, false );
			self::gate_page( (int) $existing->ID );

			return (int) $existing->ID;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => self::title(),
				'post_name'    => self::SLUG,
				'post_content' => '<!-- wp:' . self::BLOCK . ' /-->',
			)
		);

		if ( ! $page_id || is_wp_error( $page_id ) ) {
			return 0;
		}

		update_option( self::OPT_PAGE, (int) $page_id, false );
		self::gate_page( (int) $page_id );

		return (int) $page_id;
	}

	/**
	 * Gate the page to the administrator level, once.
	 *
	 * `metadata_exists()` and not the value: the level is registered with a default of
	 * `public`, so `get_post_meta()` reads a brand-new page as deliberately public and never
	 * gates it, which is how the Institution Dashboard first came up on the live site.
	 *
	 * @param int $page_id The page.
	 */
	private static function gate_page( $page_id ) {
		if ( ! metadata_exists( 'post', (int) $page_id, WPCPM_Content_Access::META_KEY ) ) {
			update_post_meta( (int) $page_id, WPCPM_Content_Access::META_KEY, WPCPM_Roles::ROLE_ADMIN );
		}
	}

	/**
	 * The page's address, or '' while there is no published page.
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
	 * The page. The block and the shortcode both land here.
	 *
	 * The capability is checked here as well as by the page's level, because a page can be
	 * reached through the shortcode in some other post.
	 *
	 * @param array $attributes `title`.
	 * @return string
	 */
	public static function render( $attributes = array() ) {
		$atts = shortcode_atts( array( 'title' => '' ), is_array( $attributes ) ? $attributes : array(), self::SHORTCODE );

		wp_enqueue_style( self::STYLE );

		// Every form on this page prints `data-wpcpm-once`; the guard is inert without the
		// script, and the Institution Dashboard shipped once that way.
		if ( ! wp_script_is( 'wpcpm-forms', 'registered' ) ) {
			wp_register_script( 'wpcpm-forms', WPCPM_PLUGIN_URL . 'assets/js/forms.js', array(), WPCPM_VERSION, true );
		}

		wp_enqueue_script( 'wpcpm-forms' );

		if ( ! is_user_logged_in() ) {
			return self::notice( __( 'Sign in to see the Administrator Dashboard.', 'wpcredits-program-manager' ) );
		}

		$viewer = wp_get_current_user();

		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return self::notice( WPCPM_Dashboards::nothing_to_show( self::MODULE, false ) );
		}

		$data   = WPCPM_Administrators_Cards::collect();
		$counts = WPCPM_Administrators_Cards::counts( $data );

		ob_start();

		echo '<div class="wpcpm-dashboard wpcpm-administrator">';

		WPCPM_Two_Factor::prompt( $viewer );

		if ( '' !== trim( (string) $atts['title'] ) ) {
			printf( '<h2 class="wpcpm-dashboard__title">%s</h2>', esc_html( $atts['title'] ) );
		}

		self::render_messages();

		WPCPM_Administrators_Cards::render_strip( $counts );
		WPCPM_Administrators_Cards::render_applications( $data['applications'] );
		WPCPM_Administrators_Cards::render_agreements( $data['agreements'] );
		WPCPM_Administrators_Cards::render_reports( $data['reports'] );
		WPCPM_Administrators_Cards::render_requests( $data['requests'] );
		WPCPM_Administrators_Cards::render_programs( $data['programs'] );
		WPCPM_Administrators_Cards::render_health( $data['health'], $data['locked'] );

		self::render_help();

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * What the last decision left to say, in the words the wp-admin queue would use.
	 *
	 * Every handler this page posts to flashes on the Institutions screen's channel, so the
	 * four message maps are merged here as that screen merges them. Taken once, so it shows
	 * once, here or there.
	 */
	private static function render_messages() {
		$status = sanitize_key( (string) WPCPM_Flash::take( WPCPM_Institutions::FLASH ) );

		if ( '' === $status ) {
			return;
		}

		$messages = array();

		if ( method_exists( 'WPCPM_Institutions', 'queue_messages' ) ) {
			$messages = array_merge( $messages, (array) WPCPM_Institutions::queue_messages() );
		}

		if ( class_exists( 'WPCPM_Institution_Panel' ) && method_exists( 'WPCPM_Institution_Panel', 'messages' ) ) {
			$messages = array_merge( $messages, (array) WPCPM_Institution_Panel::messages() );
		}

		if ( class_exists( 'WPCPM_Institution_Request' ) && method_exists( 'WPCPM_Institution_Request', 'messages' ) ) {
			$messages = array_merge( $messages, (array) WPCPM_Institution_Request::messages() );
		}

		if ( class_exists( 'WPCPM_Sync_Module' ) && method_exists( 'WPCPM_Sync_Module', 'sync_messages' ) ) {
			$messages = array_merge( $messages, (array) WPCPM_Sync_Module::sync_messages() );
		}

		if ( ! isset( $messages[ $status ] ) || ! is_array( $messages[ $status ] ) ) {
			return;
		}

		printf(
			'<p class="wpcpm-dashboard__message wpcpm-dashboard__message--%1$s">%2$s</p>',
			esc_attr( (string) $messages[ $status ][0] ),
			esc_html( (string) $messages[ $status ][1] )
		);
	}

	/**
	 * The Updates column and the program manager guide, last on the page.
	 */
	private static function render_help() {
		if ( ! class_exists( 'WPCPM_Handbook_Assistant' ) || ! method_exists( 'WPCPM_Handbook_Assistant', 'render_resources' ) ) {
			return;
		}

		echo WPCPM_Handbook_Assistant::render_resources( 'administrator' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_resources() escapes every value it interpolates.
	}

	/**
	 * One sentence in the dashboard's shell, for the viewer who gets no page.
	 *
	 * @param string $message Escaped text, or text with the one link `nothing_to_show()` adds.
	 * @return string
	 */
	private static function notice( $message ) {
		return '<div class="wpcpm-dashboard wpcpm-dashboard--notice"><p>' . wp_kses( (string) $message, array( 'a' => array( 'href' => array() ) ) ) . '</p></div>';
	}
}
```

- [ ] **Step 4: The block, the stylesheet, the module, the requires**

Create `blocks/administrator-dashboard/block.json`:

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "wpcpm/administrator-dashboard",
	"version": "1.92.0",
	"title": "Administrator Dashboard",
	"category": "widgets",
	"icon": "dashboard",
	"description": "Shows program managers every queue on one page: institution applications, Collaboration Agreements, semester reports, mentor requests, the programs running and the syncs.",
	"keywords": [ "administrator", "manager", "wpcredits", "program" ],
	"textdomain": "wpcredits-program-manager",
	"attributes": {
		"title": {
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

Create `blocks/administrator-dashboard/editor.asset.php`:

```php
<?php
/**
 * Script dependencies for the Administrator Dashboard block editor script.
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
	'version'      => '1.92.0',
);
```

Create `blocks/administrator-dashboard/editor.js` as a copy of `blocks/institution-dashboard/editor.js` with every `wpcpm/institution-dashboard` replaced by `wpcpm/administrator-dashboard`, the heading comment reading "Administrator Dashboard", the placeholder label `__( 'Administrator Dashboard', 'wpcredits-program-manager' )` and its sentence `__( 'Nothing to preview - this page is built from the program's queues for the manager viewing it.', 'wpcredits-program-manager' )`.

Create `assets/css/administrator.css` (theme-agnostic; the theme's skin refines it):

```css
/*
 * The Administrator Dashboard's base layout: the attention strip, the cards' item rows,
 * the tables. Theme-agnostic, so the page reads on any theme; the WPCredits theme's
 * dashboard skin adds its type, its card and its insets on top.
 */

.wpcpm-administrator .wpcpm-administrator__card {
	margin-bottom: 1.25em;
}

.wpcpm-attention__tiles {
	display: grid;
	gap: 12px;
	grid-template-columns: repeat( auto-fit, minmax( 150px, 1fr ) );
	list-style: none;
	margin: 0;
	padding: 0;
}

.wpcpm-attention__tile {
	border: 1px solid rgba( 0, 0, 0, 0.12 );
	border-radius: 8px;
}

.wpcpm-attention__tile--zero {
	opacity: 0.55;
}

.wpcpm-attention__link {
	color: inherit;
	display: block;
	padding: 14px 16px;
	text-decoration: none;
}

.wpcpm-attention__n {
	display: block;
	font-size: 1.75em;
	font-weight: 700;
	line-height: 1.1;
}

.wpcpm-attention__l {
	display: block;
	font-size: 0.8em;
	letter-spacing: 0.04em;
	opacity: 0.72;
	text-transform: uppercase;
}

.wpcpm-administrator__item {
	border-top: 1px solid rgba( 0, 0, 0, 0.08 );
	padding: 1em 0;
}

.wpcpm-administrator__item:first-of-type {
	border-top: 0;
	padding-top: 0;
}

.wpcpm-administrator__item--overdue {
	border-left: 4px solid #b3261e;
	padding-left: 0.75em;
}

.wpcpm-administrator__item-title {
	margin: 0 0 0.25em;
}

.wpcpm-administrator__facts {
	margin: 0 0 0.5em;
	opacity: 0.8;
}

.wpcpm-administrator__fact + .wpcpm-administrator__fact::before {
	content: " \00b7 ";
}

.wpcpm-administrator__fact--overdue {
	color: #b3261e;
	font-weight: 600;
}

.wpcpm-administrator__note {
	white-space: pre-wrap;
}

.wpcpm-administrator__actions .wpcpm-app-action,
.wpcpm-administrator__actions .wpcpm-request__decide {
	display: inline-block;
	margin: 0 8px 8px 0;
	vertical-align: top;
}

.wpcpm-administrator__closed > summary {
	cursor: pointer;
	font-weight: 600;
	padding: 0.5em 0;
}

.wpcpm-administrator__subtitle {
	margin: 1.25em 0 0.5em;
}

.wpcpm-administrator__empty,
.wpcpm-administrator__quiet,
.wpcpm-administrator__read {
	opacity: 0.72;
}

.wpcpm-admin-table {
	border-collapse: collapse;
	width: 100%;
}

.wpcpm-admin-table th,
.wpcpm-admin-table td {
	border-bottom: 1px solid rgba( 0, 0, 0, 0.08 );
	padding: 0.5em 0.5em 0.5em 0;
	text-align: left;
	vertical-align: top;
}

.wpcpm-admin-table__n {
	font-variant-numeric: tabular-nums;
	text-align: right;
}

.wpcpm-admin-table__breakdown {
	display: block;
	font-size: 0.85em;
	opacity: 0.72;
}

.wpcpm-programs__tiles {
	display: grid;
	gap: 12px;
	grid-template-columns: repeat( auto-fit, minmax( 180px, 1fr ) );
	list-style: none;
	margin: 0 0 1em;
	padding: 0;
}

.wpcpm-programs__tile {
	border: 1px solid rgba( 0, 0, 0, 0.12 );
	border-radius: 8px;
	padding: 12px 14px;
}

.wpcpm-programs__name,
.wpcpm-programs__l {
	display: block;
	font-size: 0.85em;
	opacity: 0.8;
}

.wpcpm-programs__n {
	display: block;
	font-size: 1.5em;
	font-weight: 700;
}

.wpcpm-administrator__list {
	margin: 0;
	padding-left: 1.25em;
}

@media ( max-width: 782px ) {
	.wpcpm-admin-table {
		display: block;
		overflow-x: auto;
	}
}
```

In `includes/modules/class-wpcpm-administrators.php`, add after `is_implemented()`:

```php
	/**
	 * Boot the module's front end. The wp-admin screen needs no hooks of its own.
	 */
	public function boot() {
		WPCPM_Administrators_Dashboard::init();
	}

	/**
	 * Activation: the page exists and is gated before anybody can reach it.
	 */
	public function activate() {
		WPCPM_Administrators_Dashboard::ensure_page();
	}

	/**
	 * Uninstall: the page's two options. The page itself is content and stays, as the
	 * other dashboards' pages do.
	 */
	public function uninstall() {
		delete_option( WPCPM_Administrators_Dashboard::OPT_PAGE );
		delete_option( WPCPM_Administrators_Dashboard::OPT_TITLE_FIXED );
	}
```

and in `render_admin_page()`, right after the `<p class="wpcpm-lede">` line:

```php
		$dashboard = class_exists( 'WPCPM_Administrators_Dashboard' ) ? WPCPM_Administrators_Dashboard::page_url() : '';

		if ( '' !== $dashboard ) {
			printf(
				'<p><a class="button button-primary" href="%1$s">%2$s</a> %3$s</p>',
				esc_url( $dashboard ),
				esc_html__( 'Open the Administrator Dashboard', 'wpcredits-program-manager' ),
				esc_html__( 'Every queue on one page, with its decisions.', 'wpcredits-program-manager' )
			);
		}
```

Add the require line for `includes/modules/class-wpcpm-administrators-dashboard.php` to `wpcredits-program-manager.php` right after the cards file's line, and the matching line to `uninstall.php`. In `bin/test-roles.php`, duplicate the guarded block at lines 338 to 383 for `includes/modules/class-wpcpm-administrators-dashboard.php` with the patterns `WPCPM_Administrators_Dashboard::OPT_PAGE|'wpcpm_administrator_page_id'` and `WPCPM_Administrators_Dashboard::OPT_TITLE_FIXED|'wpcpm_administrator_page_title_fixed'` (the deletes live in `class-wpcpm-administrators.php`, which is on the uninstall require path).

- [ ] **Step 5: Run the suite, the checks and every suite once**

Run: `php bin/test-administrators-dashboard.php 2>&1 | grep -E "^FAIL|PASS|FAILED"; php bin/check-references.php | tail -1; bash bin/check-standards.sh 2>&1 | tail -2; bash bin/check-standards.sh --dead 2>&1 | tail -1; for f in bin/test-*.php; do r=$(php "$f" 2>&1 | tail -1); case "$r" in *PASS*|*"NORMAL OUTCOME"*) ;; *) echo "$f: $r";; esac; done; echo "suites done"`
Expected: `ALL PASS`, references resolve, 0 phpcs errors and no dash, no dead annotation, no suite listed.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Task 4: the Administrator Dashboard page, its block, its stylesheet and the module wiring

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 5: The theme, 1.17.0

**Files (all under `/Users/maciejpilarski/GitHub/wpcredits-theme`):**
- Create: `templates/page-administrator-dashboard.html`
- Modify: `inc/template-tags.php` (add `wpcredits_is_administrator_page()` after `wpcredits_is_institution_page()`, and `wpcredits_is_dashboard_page()`), `functions.php:268-287` (`wpcredits_body_class()`), `inc/dashboard.php:47-60` (the dependency list), `assets/css/dashboard.css` (after the `.wpc-institution-page` group overrides near line 950), `readme.txt`, `style.css:7`
- Check: `php bin/check-selectors.php` from the theme root (the plugin path defaults to `../wpcredits-program-manager`)

**Interfaces:**
- Consumes: `WPCPM_Administrators_Dashboard::OPT_PAGE`, `BLOCK`, `SHORTCODE`, `STYLE` (Task 4); the plugin's classes `wpcpm-administrator`, `wpcpm-administrator__card`, `wpcpm-attention__*`, `wpcpm-programs__*`, `wpcpm-admin-table*`, `wpcpm-administrator__*`, `wpcpm-app-action`, `wpcpm-request__decide`, `wpcpm-report-card__generate`.
- Produces: body class `wpc-administrator-page`; the template bound to the slug.

- [ ] **Step 1: The template**

Create `templates/page-administrator-dashboard.html` as a copy of `templates/page-institution-dashboard.html` with `wpc-main--institution` replaced by `wpc-main--administrator` in both the block comment and the `<main>` tag (two occurrences, nothing else changes).

- [ ] **Step 2: The predicate, the body class, the skin's dependency**

In `inc/template-tags.php`, after `wpcredits_is_institution_page()`:

```php
/**
 * Whether this request is the Administrator Dashboard.
 *
 * Matched three ways, like the other three pages: the stored page ID, the block, or the
 * shortcode, because the dashboard is reachable all three ways.
 *
 * @return bool
 */
function wpcredits_is_administrator_page() {
	if ( ! wpcredits_plugin_active() || ! is_singular() || ! class_exists( 'WPCPM_Administrators_Dashboard' ) ) {
		return false;
	}

	$page_id = (int) get_option( WPCPM_Administrators_Dashboard::OPT_PAGE );

	if ( $page_id && get_queried_object_id() === $page_id ) {
		return true;
	}

	$post = get_post();

	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	return has_block( WPCPM_Administrators_Dashboard::BLOCK, $post )
		|| has_shortcode( (string) $post->post_content, WPCPM_Administrators_Dashboard::SHORTCODE );
}
```

and change `wpcredits_is_dashboard_page()` to return `wpcredits_is_mentor_page() || wpcredits_is_student_page() || wpcredits_is_institution_page() || wpcredits_is_administrator_page();`, adding to its docblock: "The Administrator Dashboard joined in 1.17.0, added here and in `wpcredits_body_class()` in the same commit."

In `functions.php` `wpcredits_body_class()`, after the institution branch:

```php
	if ( wpcredits_is_administrator_page() ) {
		$classes[] = 'wpc-administrator-page';
	}
```

In `inc/dashboard.php` `wpcredits_dashboard_assets()`, after the institution dependency line:

```php
	if ( class_exists( 'WPCPM_Administrators_Dashboard' ) && wp_style_is( WPCPM_Administrators_Dashboard::STYLE, 'registered' ) ) {
		$deps[] = WPCPM_Administrators_Dashboard::STYLE;
	}
```

- [ ] **Step 3: The skin rules**

In `assets/css/dashboard.css`, after the block that ends with `.wpc-institution-page .wpcpm-group > .wpcpm-group__title { padding-left: 0; padding-right: 0; }` (near line 950), add:

```css
/*
 * The Administrator Dashboard nests its groups inside sections already inset by 32px, the
 * way the Institution Dashboard does, so it needs the same two answers: the clickable row
 * loses its horizontal margin and the open heading its horizontal padding, or every row
 * on the page steps 32px right of the strip above it (theme 1.16.5 and 1.16.6 were this
 * bug on the institution page).
 */
.wpc-administrator-page .wpcpm-group__summary,
.wpc-administrator-page .wpcpm-group__disclosure[open] .wpcpm-group__body {
	margin-left: 0;
	margin-right: 0;
}

.wpc-administrator-page .wpcpm-group > .wpcpm-group__title {
	padding-left: 0;
	padding-right: 0;
}

.wpc-administrator-page .wpcpm-administrator__card {
	padding-left: 32px;
	padding-right: 32px;
}

/* The attention strip: a row of tiles that wraps, in the card's own surface and line. */
.wpc-administrator-page .wpcpm-attention__tile {
	background: var(--wpc-surface);
	border: 1px solid var(--wpc-line-card);
	border-radius: var(--wpc-radius-card);
}

.wpc-administrator-page .wpcpm-attention__n {
	font: 700 28px/32px var(--wpc-font);
}

.wpc-administrator-page .wpcpm-attention__l {
	font: 600 12px/16px var(--wpc-font);
	letter-spacing: 0.04em;
}

/* The programs tiles share the strip's surface. */
.wpc-administrator-page .wpcpm-programs__tile {
	background: var(--wpc-surface);
	border: 1px solid var(--wpc-line-card);
	border-radius: var(--wpc-radius-card);
}

.wpc-administrator-page .wpcpm-programs__n {
	font: 700 24px/28px var(--wpc-font);
}

/* Tables: the theme's line, numbers right-aligned in tabular figures, a muted breakdown. */
.wpc-administrator-page .wpcpm-admin-table th,
.wpc-administrator-page .wpcpm-admin-table td {
	border-bottom: 1px solid var(--wpc-line);
}

.wpc-administrator-page .wpcpm-admin-table th {
	font: 700 12px/16px var(--wpc-font);
	letter-spacing: 0.06em;
	text-transform: uppercase;
}

/*
 * The six decisions, the request buttons and Draft now are drawn by the wp-admin queue with
 * core's button classes, which the front end never loads. They take the theme's secondary
 * button here, so a decision reads as a decision and not as unstyled text.
 */
.wpc-administrator-page .wpcpm-app-action .button,
.wpc-administrator-page .wpcpm-request__decide .button,
.wpc-administrator-page .wpcpm-report-card__generate .button {
	background: none;
	border: 1px solid var(--wpc-brand-border);
	border-radius: 8px;
	color: var(--wpc-brand);
	cursor: pointer;
	font: 600 15px/20px var(--wpc-font);
	padding: 9px 18px;
}

.wpc-administrator-page .wpcpm-app-action .button-primary,
.wpc-administrator-page .wpcpm-request__decide .button-primary {
	background: var(--wpc-brand);
	color: #fff;
}

.wpc-administrator-page .wpcpm-app-action textarea,
.wpc-administrator-page .wpcpm-request__decide textarea {
	border: 1px solid var(--wpc-line);
	border-radius: 8px;
	font: inherit;
	padding: 8px 10px;
	width: 100%;
}
```

If any `--wpc-*` token above is not defined in `style.css` or `theme.json` (check with `grep -c "wpc-brand-border\|wpc-line-card\|wpc-radius-card\|wpc-surface" style.css`), replace it with the token the institution rules use for the same purpose; an undefined custom property drops the whole declaration.

- [ ] **Step 4: Version and changelog**

`style.css`: `Version: 1.17.0`. `readme.txt`: `Stable tag: 1.17.0` and above `= 1.16.8 =`:

```
= 1.17.0 =
* The Administrator Dashboard, the page plugin 1.92.0 gives program managers, has its template, its page predicate, its body class and its skin: the attention strip, the programs tiles, the tables, and the theme's secondary button on the decisions the wp-admin queue draws with core's button classes. As with the Institution Dashboard, the predicate and the body class were added together, because a page in one and not the other renders as an article with no skin.
```

- [ ] **Step 5: Check**

Run from the theme root: `php bin/check-selectors.php 2>&1 | tail -3` (every `.wpcpm-*` class the theme dresses must be printed by the plugin's source, which Task 4's classes are; no dash) and `grep -n $'\xe2\x80\x93\|\xe2\x80\x94' templates/page-administrator-dashboard.html inc/template-tags.php functions.php inc/dashboard.php assets/css/dashboard.css readme.txt` (prints nothing).
Expected: `check-selectors` exits 0 and prints no `MISS`.

---

### Task 6: Docs, versions, checks, zips, deploy, verify, mirror, notes

**Files:**
- Modify: `wpcredits-program-manager.php:6,22`, `readme.txt:7` and the changelog, `docs/sections/34-admin-operations.md` (the options table and a new section), `docs/sections/30-admin-wpadmin.md` (one paragraph pointing at the dashboard)

- [ ] **Step 1: Version and changelog**

`Version:` header and `WPCPM_VERSION` to `1.92.0`; `Stable tag: 1.92.0`; above `= 1.91.1 =`:

```
= 1.92.0 =
* **The Administrator Dashboard.** A page for program managers at /administrator-dashboard/, gated to the administrator level, built like the other dashboards. It shows every queue with its count and its decisions: institution applications with the six decisions, Collaboration Agreements awaiting review with the reviewer's checklist, download, accept and return (and the returned and revoked ones, with Reinstate), semester reports to review, semesters due for drafting with Draft now, and approved reports this semester, open mentor requests with handle and decline, the programs running as a totals strip per track and one row per institution with students in progress, and the syncs' health with locked accounts, the private storage probe, the last mail and the invitation run. A strip of eight counts at the top links to the cards. Decisions made on the page come back to it; the wp-admin screens stay for settings, syncs and the rarer work.
* The Updates column and the guide button know the administrator audience; the toolbar's Dashboards menu lists the page for managers.
* Institution applications and mentor requests can be listed by state from code (`in_state()`, `closed_requests()`), and the decision forms carry the double-submit guard everywhere they are drawn.
```

- [ ] **Step 2: The administrators guide**

In `docs/sections/34-admin-operations.md`, add to the options table, in alphabetical position: `| wpcpm_administrator_page_id | WPCPM_Administrators_Dashboard::OPT_PAGE |` and `| wpcpm_administrator_page_title_fixed | WPCPM_Administrators_Dashboard::OPT_TITLE_FIXED |`, and a section `## The Administrator Dashboard` before the section holding the table:

```
The Administrator Dashboard at /administrator-dashboard/ is the page to start the day on. It is gated to program managers and shows, in this order: a strip of eight counts (applications waiting, agreements to review and overdue, reports to review, semesters due for drafting, mentor requests open and overdue, locked accounts), then one card each for institution applications, Collaboration Agreements, semester reports, mentor requests, the programs running and the syncs' health. Every decision on the page is the same decision the wp-admin screen offers, posted to the same handler with the same safeguards, and it lands back on the page. What the page does not do: run a sync, change a setting, approve a semester report (that happens in the editor, where you have read it) or provision accounts; those stay on the wp-admin screens the Syncs card links to.

The programs card counts students in progress per track and per institution from the roster index, so its numbers are as old as the last students sync; the read time is printed under the table. A finished student's row no longer says which track they were on, so "finished this semester" is one number rather than one per track.
```

In `docs/sections/30-admin-wpadmin.md`, add one paragraph at the end: "Since 1.92.0 the Administrator Dashboard on the front end gathers every queue these screens hold; the Administrators screen links to it."

- [ ] **Step 3: Every check, both zips**

Run from the plugin root:

```bash
for f in bin/test-*.php; do r=$(php "$f" 2>&1 | tail -1); case "$r" in *PASS*|*"NORMAL OUTCOME"*) ;; *) echo "$f: $r";; esac; done; echo "suites done"; php bin/check-references.php | tail -1; bash bin/check-standards.sh 2>&1 | tail -2; bash bin/check-standards.sh --dead 2>&1 | tail -1; bash bin/build | tail -4
```

Expected: no suite listed, references resolve, 0 phpcs errors and no dash, no dead annotation, `version inside: 1.92.0`, one top-level folder.

Then the theme zip, from `~/GitHub`:

```bash
cd ~/GitHub && rm -f wpcredits-theme.zip && find wpcredits-theme -name ".DS_Store" -delete && zip -rq wpcredits-theme.zip wpcredits-theme -x "wpcredits-theme/.git/*" "*/.DS_Store" "wpcredits-theme/node_modules/*" "*.map" && unzip -p wpcredits-theme.zip wpcredits-theme/style.css | grep "^Version:"
```

Expected: `Version: 1.17.0`.

- [ ] **Step 4: Commit the plugin**

```bash
git add -A
git commit -m "Task 6: version 1.92.0, changelog and the administrators guide

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

- [ ] **Step 5: Deploy both and verify live** (the controller runs this step)

```bash
scp ~/GitHub/wpcredits-program-manager.zip ~/GitHub/wpcredits-theme.zip wpcredits-dashboard:/home/152004889/ && ssh wpcredits-dashboard "wp plugin install /home/152004889/wpcredits-program-manager.zip --force 2>&1 | tail -1; wp theme install /home/152004889/wpcredits-theme.zip --force 2>&1 | tail -1; echo plugin \$(wp plugin get wpcredits-program-manager --field=version) theme \$(wp theme get wpcredits-theme --field=version)"
```

Then, with WP-CLI as a manager: `WPCPM_Administrators_Dashboard::ensure_page()` returns the page ID and `page_url()` its address (activation does not run on `--force` installs, so ensure the page by hand once); `get_post_meta( id, '_wpcpm_access_level', true )` is `administrator`; `render()` as the manager (user 64274470) is several kilobytes and contains `id="wpcpm-attention"`, the six card ids and `data-audience="administrator"` or the resources section; `render()` as an institution account (64274500) is the refusal with no `<form`; a logged-out `curl -s -o /dev/null -w '%{http_code}'` of the page URL is not 200 with the strip in it (the content-access gate). Then open the page in a browser as a manager and confirm the theme skin loaded (the card, the tiles, the buttons styled) and that one decision, the TEST institution's mentor request or a Draft now on the TEST institution, lands back on the page with its message.

- [ ] **Step 6: Mirror, vault, memory** (the controller runs this step)

Mirror both trees (`rsync -a --delete --exclude '.git' --exclude '.gitignore' --exclude '.superpowers' --exclude '.DS_Store' --exclude 'node_modules'`) into `~/GitHub/Plugins/WPCredits-Tracker-mirror/Education/WordPress Education Dashboard/{wpcredits-program-manager,wpcredits-theme}/`, scan the delta for secrets and addresses, commit `WordPress Education Dashboard: plugin 1.92.0 and theme 1.17.0, the Administrator Dashboard` with the trailer, push as a separate command. Append a `## 1.92.0 with theme 1.17.0: the Administrator Dashboard` section to the vault note *Institutions module* (what shipped, the live checks, what is left: the sponsor cards join with the Sponsor module's last phase) and one sentence to the plugin memory and the designs memory.
