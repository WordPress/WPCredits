# Role-aware sign-in cards on the homepage, theme 1.32.13 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The homepage's sign-in section ("Open your page") shows a signed-in reader "Open" only for the pages they can open, and leads them to the public way in for the other roles instead of to a gated dashboard's access notice (WordPress/WPCredits#294). Theme **1.32.13**.

**Architecture:** One block, `blocks/sign-in/render.php`, drawn per request. Signed out: unchanged, every published dashboard page is a card whose link signs in and returns to that page. Signed in: a card reads "Open" when the reader opens that page by the plugin's own tests (a student by `WPCPM_Students_Dashboard::is_student()`, a mentor by `wpcredits_viewer_is_mentor()`, an institution's or a sponsor's member by `wpcredits_partners_acts_for()`, an Administrator by the plugin's manage capability, who opens every page); otherwise the card leads to that role's way in (`wpcredits_student_registration_url()` while the form is open, `wpcredits_mentor_application_url()`, `wpcredits_hub_url( 'institution_application' )`, and the sponsor application only while `wpcredits_partners_live()`), reading "Register" (students) or "Apply"; a role with neither has no card. The head stays "Already in a program?" over "Open your page" while at least one card opens, and reads "Not in a program yet?" over "Get involved" when none does. Every helper is one the theme already has (inc/template-tags.php, inc/partners.php, inc/get-involved.php, inc/hub-content.php); only the names a drawn card prints are looked up, so a translator and the suite's string count see nothing spare.

**Tech Stack:** The WPCredits block theme `wpcredits-theme` (prefix `wpcredits_`), its no-WordPress suites `bin/test-*.php` with the stubs in `bin/stubs/` (the hub suite stands the plugin in with small classes), `bin/check-selectors.php` (takes the plugin checkout's path), the release script `~/Projects/wpcredits-release/release.py` (`check theme`, `build theme`, `scan theme`, `mirror theme`, `deploy theme`).

**Spec:** WordPress/WPCredits#294 (the report of 6 November 2026, with its three options) and the owner's yes of 6 November 2026 to the recommendation "show only the cards the account can open and point the others at the public application pages", which the reply posted on the issue promises for the next theme release. The reader who opens nothing, the head's words for them and the Students card's "Register" are the planner's calls, listed under After the plan.

## Global Constraints

- Release **1.32.13**: `Version:` in `style.css`'s header (the theme reads `WPCREDITS_VERSION` from it), `Stable tag:` in `readme.txt`, a `= 1.32.13 =` changelog entry above `= 1.32.12 =`, and the translation template (`bash bin/make-pot.sh`). Branch `release/1.32.13-sign-in-cards` from the theme's `main` at ebb5138 or later (1.32.12 live since 5 November 2026). The plan lives in the plugin's `docs/plans/`, as every theme plan does; the code lives in the theme.
- PHP 7.4 compatible; WordPress coding standards (tabs, Yoda conditions, spaces inside parentheses, strict `in_array()`); every printed value escaped at output; a translators' comment on every string a translator could misread, as the block already has for each link name.
- No em dash and no en dash anywhere: code, comments, strings, docs, commit messages. Plain hyphens. (The arrow after each link is the existing `→` character, which is neither.)
- US English; full product names ("Student Report Card", "Mentor Report Card", "Institution Dashboard", "Sponsor Dashboard"); "Administrator" for the people who run the program.
- Every theme `bin/test-*.php` exits 0 (`for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done; echo battery done`), and `php bin/check-selectors.php ~/GitHub/wpcredits-program-manager` ends `No em or en dashes in the theme.` with `0 placements off`. The theme has no phpcs script of its own; the release script's `check theme` runs the markers, lint, battery and selectors checks.
- Nothing is deployed, mirrored or posted without the owner's explicit yes for that step; the live version is read first.

## Review Focus

1. **A signed-in account in no program** (a subscriber, a lapsed member): no gated link anywhere, the head reads "Not in a program yet?" over "Get involved", and only the roles with a way in right now have a card. Pinned in Task 1, step 1 (the reader who is nothing, with registration closed and the Partners page not live, and again with both open).
2. **An Administrator**: four "Open" cards, as every signed-in reader had until now. Pinned in Task 1, step 1 (the signed-in check that every later signed-in check reads as).
3. **The Partners page not live, or registration closed**: the sponsor and student ways in are not offered, so nothing points at what visitors cannot see (the sponsor tiers are not public, the owner's hold of 5 November 2026). Pinned in Task 1, step 1.
4. **A link's name starts with its words** for every state, "Register as a student", "Apply to mentor students", so a reader who speaks what they see is understood; no link opens a new tab. Pinned in Task 1, step 1 ("every way-in link is a plain link ...").
5. **A translator sees whole strings and the suite counts them**: the Administrator's and the visitor's pages still look up exactly 18 strings and print each, so no name is looked up for a card that does not print it. Pinned by the existing "every text is one translated string" check, which Task 1 keeps green by building each state's names only when a drawn card uses them.

## Files at a glance

| File | What it is responsible for | Task |
| --- | --- | --- |
| `blocks/sign-in/render.php` | The cards, who opens what, the ways in, the head's words | 1 |
| `bin/test-hub.php` | The sign-in section's checks; the plugin stand-ins gain the membership answers | 1 |
| `bin/stubs/hub-wp.php`, `bin/stubs/migrate-wp.php` | `current_user_can()`, shared by the two stubs the migration suites load together | 1 |
| `style.css`, `readme.txt`, `languages/wpcredits-theme.pot` | The version, the changelog, the template | 2 |
| `bin/check-selectors.php` | The selectors check reads the plugin's one message builder; the Sponsor Dashboard's detail rule kept for a rollback | 3 |

No CSS change: a way-in link is the same `.wpc-hub-door__go` line the "Open" link is.

---

## Before Task 1: the branch and the baseline

- [ ] **Step 1: Branch from the theme's main** (a worktree; never enter another session's worktree under `.worktrees/`)

```bash
cd ~/GitHub/wpcredits-theme
git worktree add .worktrees/1-32-13 -b release/1.32.13-sign-in-cards main
cd .worktrees/1-32-13
```

- [ ] **Step 2: Start from a main that passes**: the battery line above prints `battery done` and no `FAIL`; `php bin/check-selectors.php ~/GitHub/wpcredits-program-manager` ends `0 placements off the values their neighbors take.` and `No em or en dashes in the theme.`

---

### Task 1: The cards a signed-in reader can open, and the ways in for the rest

**Files:**
- Modify: `blocks/sign-in/render.php` (the whole body after the pages are read)
- Modify: `bin/test-hub.php` (the stand-ins for the four dashboards, the registration form and the sponsor tiers; `sign_in_as()` and `sign_in_said()`; the checks), `bin/stubs/hub-wp.php` (`current_user_can()` and `$GLOBALS['caps']`), `bin/stubs/migrate-wp.php` (its `current_user_can()` guarded)

**Interfaces:**
- Consumes: `wpcredits_plugin_active()`, `wpcredits_viewer_is_mentor()`, `wpcredits_partners_acts_for( $kind )`, `wpcredits_partners_live()`, `wpcredits_student_registration_url()`, `wpcredits_mentor_application_url()`, `wpcredits_hub_url( $target )`, `WPCPM_Students_Dashboard::is_student()` (guarded with `method_exists()`), `WPCPM_Roles::CAP_MANAGE`.
- Produces: nothing new outside the block.

- [ ] **Step 1: Write the failing checks**

The hub suite loads `inc/get-involved.php` beside `inc/partners.php` (the block asks the registration form's address through it); the stand-ins answer who the reader is; every signed-in check after the new ones reads as an Administrator, who opens every page as every reader did before.

```diff
diff --git a/bin/stubs/hub-wp.php b/bin/stubs/hub-wp.php
index d0be99a..0303b80 100644
--- a/bin/stubs/hub-wp.php
+++ b/bin/stubs/hub-wp.php
@@ -79,6 +79,7 @@ $GLOBALS['request']       = array(
 $GLOBALS['marked']        = false;
 $GLOBALS['translated']    = array();
 $GLOBALS['logged_in']     = false;
+$GLOBALS['caps']          = array();
 $GLOBALS['said']          = 0;
 $GLOBALS['is_admin']      = false;
 $GLOBALS['hooks']         = array();
@@ -201,6 +202,17 @@ function get_page_template_slug( $post = null ) {
 	return isset( $GLOBALS['request']['templates'][ $id ] ) ? (string) $GLOBALS['request']['templates'][ $id ] : '';
 }
 function is_user_logged_in() { return ! empty( $GLOBALS['logged_in'] ); }
+// The signed-in reader's capabilities, `$GLOBALS['caps']`, none by default: the sign-in block asks for the
+// plugin's manage capability, which opens every page (theme 1.32.13). The migration stub declares the same
+// function from `$GLOBALS['user']['caps']`, and the two stubs are loaded together by the migration suites, so
+// each declares it only when the other has not, and this one reads both globals.
+if ( ! function_exists( 'current_user_can' ) ) {
+	function current_user_can( $capability ) {
+		$caps = array_merge( isset( $GLOBALS['caps'] ) ? (array) $GLOBALS['caps'] : array(), isset( $GLOBALS['user']['caps'] ) ? (array) $GLOBALS['user']['caps'] : array() );
+
+		return in_array( $capability, $caps, true );
+	}
+}
 
 /**
  * Core's wp_login_url(): the login page, with the address to return to, percent-encoded, only when
diff --git a/bin/stubs/migrate-wp.php b/bin/stubs/migrate-wp.php
index 68eb615..f2ba47e 100644
--- a/bin/stubs/migrate-wp.php
+++ b/bin/stubs/migrate-wp.php
@@ -531,7 +531,9 @@ function update_option( $key, $value, $autoload = null ) {
 }
 
 function get_current_user_id() { return (int) $GLOBALS['user']['id']; }
-function current_user_can( $capability ) { return in_array( $capability, $GLOBALS['user']['caps'], true ); }
+if ( ! function_exists( 'current_user_can' ) ) {
+	function current_user_can( $capability ) { return in_array( $capability, $GLOBALS['user']['caps'], true ); }
+}
 function get_user_meta( $user, $key, $single = false ) { return isset( $GLOBALS['user_meta'][ $user ][ $key ] ) ? $GLOBALS['user_meta'][ $user ][ $key ] : ''; }
 function update_user_meta( $user, $key, $value ) { $GLOBALS['user_meta'][ $user ][ $key ] = $value; return true; }
 function get_current_screen() { return (object) array( 'id' => $GLOBALS['screen'] ); }
diff --git a/bin/test-hub.php b/bin/test-hub.php
index e3a907b..ba36019 100644
--- a/bin/test-hub.php
+++ b/bin/test-hub.php
@@ -28,6 +28,8 @@ if ( ! function_exists( 'get_post_field' ) ) {
 }
 
 require_once WPCREDITS_THEME_DIR . 'inc/partners.php';
+// The Get involved rows' helpers: the sign-in block asks the student registration form's address through them (theme 1.32.13).
+require_once WPCREDITS_THEME_DIR . 'inc/get-involved.php';
 
 $fail  = 0;
 $total = 0;
@@ -2434,8 +2436,17 @@ $sign_in_without_plugin = is_file( WPCREDITS_THEME_DIR . 'blocks/sign-in/render.
 if ( ! class_exists( 'WPCPM_Mentors_Dashboard' ) ) {
 	/** Present, so the plugin reads as active; its page as the plugin reports it: '' while it is not published. */
 	class WPCPM_Mentors_Dashboard {
-		public static $url = '';
+		public static $url    = '';
+		public static $mentor = false;
 		public static function page_url() { return self::$url; }
+		public static function is_mentor( $user = null ) { return self::$mentor; }
+	}
+
+	/** The plugin's roles class, there so the theme's capability test runs; the reader's capabilities are the stub's. */
+	class WPCPM_Roles {
+		const CAP_MANAGE  = 'wpcpm_manage_program';
+		const ROLE_MENTOR = 'wpcpm_mentor';
+		public static function user_has_role( $user, $role ) { return false; }
 	}
 }
 
@@ -2768,23 +2779,84 @@ $sign_in_only_mentors         = sign_in_block();
 if ( ! class_exists( 'WPCPM_Students_Dashboard' ) ) {
 	/** The Student Report Card's page, as the plugin reports it. */
 	class WPCPM_Students_Dashboard {
-		public static $url = '';
+		public static $url     = '';
+		public static $student = false;
 		public static function page_url() { return self::$url; }
+		public static function is_student( $user = null ) { return self::$student; }
 	}
 
-	/** The Institution Dashboard's page. */
+	/** The Institution Dashboard's page, and whether the reader acts for an institution. */
 	class WPCPM_Institutions_Dashboard {
-		public static $url = '';
+		public static $url    = '';
+		public static $member = false;
 		public static function page_url() { return self::$url; }
+		public static function is_member( $user = null ) { return self::$member; }
 	}
 
-	/** The Sponsor Dashboard's page. */
+	/** The Sponsor Dashboard's page, and whether the reader acts for a sponsor. */
 	class WPCPM_Sponsors_Dashboard {
-		public static $url = '';
+		public static $url    = '';
+		public static $member = false;
+		public static function page_url() { return self::$url; }
+		public static function is_member( $user = null ) { return self::$member; }
+	}
+}
+
+// The student registration form and the Partners page's launch switch, as the signed-in cards ask them
+// (theme 1.32.13): the form closed and the switch off unless a check sets them.
+if ( ! class_exists( 'WPCPM_Student_Registration_Form' ) ) {
+	/** The plugin's student registration form: open or not, and its page. */
+	class WPCPM_Student_Registration_Form {
+		public static $open = false;
+		public static $url  = 'https://wordpresseducation.org/student-registration/';
+		public static function is_open() { return self::$open; }
 		public static function page_url() { return self::$url; }
 	}
 }
 
+if ( ! class_exists( 'WPCPM_Sponsor_Tiers' ) ) {
+	/** The plugin's launch switch for the partnership levels and the Partners page. */
+	class WPCPM_Sponsor_Tiers {
+		public static $live = false;
+		public static function live() { return self::$live; }
+	}
+}
+
+/**
+ * Who the signed-in reader is, for the sign-in block: a student, a mentor, an institution's member, a
+ * sponsor's member, an Administrator (the plugin's manage capability), any of them or none.
+ *
+ * @param string[] $who Any of `student`, `mentor`, `institution`, `sponsor`, `manage`.
+ */
+function sign_in_as( array $who ) {
+	WPCPM_Students_Dashboard::$student    = in_array( 'student', $who, true );
+	WPCPM_Mentors_Dashboard::$mentor      = in_array( 'mentor', $who, true );
+	WPCPM_Institutions_Dashboard::$member = in_array( 'institution', $who, true );
+	WPCPM_Sponsors_Dashboard::$member     = in_array( 'sponsor', $who, true );
+	$GLOBALS['caps']                      = in_array( 'manage', $who, true ) ? array( WPCPM_Roles::CAP_MANAGE ) : array();
+}
+
+/**
+ * What the signed-in block says, card by card: the eyebrow and the heading, then each card's eyebrow, its
+ * link's words, its address and its whole name, and how many cards the grid says it holds.
+ *
+ * @param string $html The block's markup.
+ * @return array
+ */
+function sign_in_said( $html ) {
+	$xpath = dom_of( $html );
+
+	return array(
+		found( $xpath, '/html/body/section/div[1]/p' ),
+		found( $xpath, '//h2' ),
+		found( $xpath, '//div[@class="wpc-hub-sign-in__grid"]/div/p[1]' ),
+		found( $xpath, '//p[@class="wpc-hub-door__go"]/a/text()' ),
+		found( $xpath, '//p[@class="wpc-hub-door__go"]/a', 'href' ),
+		found( $xpath, '//p[@class="wpc-hub-door__go"]/a', 'aria-label' ),
+		found( $xpath, '//div[@class="wpc-hub-sign-in__grid"]', 'style' ),
+	);
+}
+
 $sign_pages = array(
 	'students'     => 'https://wordpresseducation.org/student/',
 	'mentors'      => 'https://wordpresseducation.org/mentor/',
@@ -2879,11 +2951,12 @@ ck( 'the sign-in addresses written out: the login page, and after it each card\'
 	) );
 
 $GLOBALS['logged_in'] = true;
+sign_in_as( array( 'manage' ) );
 
 $signed_in = sign_in_block();
 $sign_in   = dom_of( $signed_in );
 
-ck( 'signed in, the same four cards in the same order, the heading "Open your page", each link the page itself and reading "Open", named by one whole sentence that starts with that word and names its page, and described by nothing',
+ck( 'signed in as an Administrator, who opens every page: the same four cards in the same order, the heading "Open your page", each link the page itself and reading "Open", named by one whole sentence that starts with that word and names its page, and described by nothing',
 	array(
 		found( $sign_in, '//h2' ),
 		sign_in_cards( $signed_in ),
@@ -2907,7 +2980,71 @@ ck( 'signed in, the same four cards in the same order, the heading "Open your pa
 		array_fill( 0, 4, true ),
 	) );
 
+// Theme 1.32.13 (WordPress/WPCredits#294): signed in, a card says "Open" only for a page the reader can open,
+// and the other cards lead to the public way in for that role, or are left out when there is none.
+$sign_in_who = array();
+
+foreach ( array( array(), array( 'mentor' ), array( 'student', 'institution' ), array( 'sponsor' ) ) as $sign_who ) {
+	sign_in_as( $sign_who );
+	$sign_in_who[] = sign_in_said( sign_in_block() );
+}
+
+WPCPM_Student_Registration_Form::$open = true;
+WPCPM_Sponsor_Tiers::$live             = true;
+sign_in_as( array() );
+$sign_in_who[] = sign_in_said( sign_in_block() );
+sign_in_as( array( 'mentor' ) );
+$sign_in_who[] = sign_in_said( sign_in_block() );
+WPCPM_Student_Registration_Form::$open = false;
+WPCPM_Sponsor_Tiers::$live             = false;
+sign_in_as( array() );
+
+$sign_apply = array(
+	'students'     => array( 'Register', 'https://wordpresseducation.org/student-registration/', 'Register as a student' ),
+	'mentors'      => array( 'Apply', 'https://site.example/become-a-mentor/', 'Apply to mentor students' ),
+	'institutions' => array( 'Apply', 'https://wordpresseducation.org/apply/', 'Apply to partner as an institution' ),
+	'sponsors'     => array( 'Apply', 'https://wordpresseducation.org/sponsor-application/', 'Apply to become a sponsor' ),
+);
+$sign_open  = array(
+	'students'     => array( 'Open', $sign_pages['students'], 'Open your Student Report Card' ),
+	'mentors'      => array( 'Open', $sign_pages['mentors'], 'Open your Mentor Report Card' ),
+	'institutions' => array( 'Open', $sign_pages['institutions'], 'Open your Institution Dashboard' ),
+	'sponsors'     => array( 'Open', $sign_pages['sponsors'], 'Open your Sponsor Dashboard' ),
+);
+$sign_cards = static function ( $eyebrow, $heading, array $cards ) use ( $sign_apply, $sign_open ) {
+	$links = array_map( static function ( $card ) use ( $sign_apply, $sign_open ) { return 'open' === $card[1] ? $sign_open[ $card[0] ] : $sign_apply[ $card[0] ]; }, $cards );
+	$eyes  = array( 'students' => 'Students', 'mentors' => 'Mentors', 'institutions' => 'Institutions', 'sponsors' => 'Sponsors' );
+
+	return array( array( $eyebrow ), array( $heading ), array_map( static function ( $card ) use ( $eyes ) { return $eyes[ $card[0] ]; }, $cards ), array_column( $links, 0 ), array_column( $links, 1 ), array_column( $links, 2 ), array( '--wpc-cards:' . count( $cards ) ) );
+};
+
+ck( 'signed in, a card reads "Open" only for a page the reader can open, and the other cards lead to that role\'s public way in, or are left out when there is none (theme 1.32.13): a reader who is nothing, with registration closed and the Partners page not live, gets Apply for mentors and institutions and no student or sponsor card, under "Not in a program yet?" and "Get involved"; a mentor opens the Mentor Report Card under "Open your page" and is offered the other two; a student who also acts for an institution opens both; a sponsor\'s member opens the Sponsor Dashboard; with registration open and the Partners page live the nothing reader gets all four ways in, and the mentor three',
+	$sign_in_who,
+	array(
+		$sign_cards( 'Not in a program yet?', 'Get involved', array( array( 'mentors', 'apply' ), array( 'institutions', 'apply' ) ) ),
+		$sign_cards( 'Already in a program?', 'Open your page', array( array( 'mentors', 'open' ), array( 'institutions', 'apply' ) ) ),
+		$sign_cards( 'Already in a program?', 'Open your page', array( array( 'students', 'open' ), array( 'mentors', 'apply' ), array( 'institutions', 'open' ) ) ),
+		$sign_cards( 'Already in a program?', 'Open your page', array( array( 'mentors', 'apply' ), array( 'institutions', 'apply' ), array( 'sponsors', 'open' ) ) ),
+		$sign_cards( 'Not in a program yet?', 'Get involved', array( array( 'students', 'apply' ), array( 'mentors', 'apply' ), array( 'institutions', 'apply' ), array( 'sponsors', 'apply' ) ) ),
+		$sign_cards( 'Already in a program?', 'Open your page', array( array( 'students', 'apply' ), array( 'mentors', 'open' ), array( 'institutions', 'apply' ), array( 'sponsors', 'apply' ) ) ),
+	) );
+
+ck( 'and every way-in link is a plain link to a page of this site or the plugin\'s, in a new tab for nobody, its name starting with the words it shows',
+	(static function () {
+		WPCPM_Student_Registration_Form::$open = true;
+		WPCPM_Sponsor_Tiers::$live             = true;
+		sign_in_as( array() );
+		$html = sign_in_block();
+		WPCPM_Student_Registration_Form::$open = false;
+		WPCPM_Sponsor_Tiers::$live             = false;
+		$xp = dom_of( $html );
+
+		return array( count( $xp->query( '//a[@target]' ) ), count( $xp->query( '//a' ) ), array_map( static function ( $label, $words ) { return 0 === strpos( $label, $words ); }, found( $xp, '//p[@class="wpc-hub-door__go"]/a', 'aria-label' ), found( $xp, '//p[@class="wpc-hub-door__go"]/a/text()' ) ) );
+	})(),
+	array( 0, 4, array_fill( 0, 4, true ) ) );
+
 $GLOBALS['logged_in'] = false;
+sign_in_as( array( 'manage' ) );
 
 $sign_some = array();
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php bin/test-hub.php`. Expected, with the block as 1.32.12 has it, one failing check:

```text
FAIL signed in, a card reads "Open" only for a page the reader can open, and the other cards lead to that role's public way in, or are left out when there is none (theme 1.32.13): ...
```

(The other new check passes against the old block too: four "Open" links are plain links whose names start with their words.) Then `php bin/test-migrate-1-32-12.php` must still pass: a `Cannot redeclare function current_user_can()` fatal means the stub guard of `bin/stubs/migrate-wp.php` is missing.

- [ ] **Step 3: The block**

```diff
diff --git a/blocks/sign-in/render.php b/blocks/sign-in/render.php
index 55b4232..b843913 100644
--- a/blocks/sign-in/render.php
+++ b/blocks/sign-in/render.php
@@ -7,8 +7,14 @@
  * placed in a template is written into the template as static blocks the moment someone saves the
  * template in the Site Editor, which would freeze one visitor's links for everyone; a block is drawn
  * again for every request. Signed out, a card's link is the login screen with the card's page to
- * return to (the plugin's login redirect honors it, so the visitor lands on that page); signed in, it
- * is the page itself.
+ * return to (the plugin's login redirect honors it, so the visitor lands on that page). Signed in, a
+ * card reads "Open" only for a page this reader can open, as the plugin's own tests answer it (a
+ * student, a mentor, an institution's or a sponsor's member, or an Administrator, who opens every
+ * page); the other cards lead to that role's public way in, the student registration form while it is
+ * open, the mentor application, the institution application and, while the Partners page is live, the
+ * sponsor application, and a role with no way in right now has no card (theme 1.32.13,
+ * WordPress/WPCredits#294: until then every card said "Open" to everyone signed in, and three of them
+ * led a mentor to the access notice). A reader who opens nothing reads "Get involved" over the ways in.
  *
  * **A page that is not published has no card, and with none the block prints nothing at all**, not an
  * empty padded box: the section is the block itself, placed in the template, so there is no group
@@ -73,20 +79,50 @@ $wpcredits_cards = array(
 	),
 );
 
+// Signed in: which pages this reader opens, by the plugin's own tests, each guarded so an older plugin
+// opens nothing; an Administrator opens every page. And each role's public way in for the others,
+// '' where there is none right now. A card with neither is left out.
+$wpcredits_opens = array();
+$wpcredits_ways  = array();
+
+if ( $wpcredits_signed_in ) {
+	$wpcredits_manages = wpcredits_plugin_active() && class_exists( 'WPCPM_Roles' ) && current_user_can( WPCPM_Roles::CAP_MANAGE );
+	$wpcredits_student = class_exists( 'WPCPM_Students_Dashboard' ) && method_exists( 'WPCPM_Students_Dashboard', 'is_student' ) && (bool) WPCPM_Students_Dashboard::is_student();
+
+	$wpcredits_opens = array(
+		'students'     => $wpcredits_manages || $wpcredits_student,
+		'mentors'      => $wpcredits_manages || wpcredits_viewer_is_mentor(),
+		'institutions' => $wpcredits_manages || wpcredits_partners_acts_for( 'institution' ),
+		'sponsors'     => $wpcredits_manages || wpcredits_partners_acts_for( 'sponsor' ),
+	);
+	$wpcredits_ways  = array(
+		'students'     => wpcredits_student_registration_url(),
+		'mentors'      => wpcredits_mentor_application_url(),
+		'institutions' => wpcredits_hub_url( 'institution_application' ),
+		'sponsors'     => wpcredits_partners_live() ? wpcredits_hub_url( 'sponsor_application' ) : '',
+	);
+
+	foreach ( $wpcredits_pages as $wpcredits_id => $wpcredits_page ) {
+		if ( ! $wpcredits_opens[ $wpcredits_id ] && '' === $wpcredits_ways[ $wpcredits_id ] ) {
+			unset( $wpcredits_pages[ $wpcredits_id ] );
+		}
+	}
+
+	if ( ! $wpcredits_pages ) {
+		return;
+	}
+}
+
+// Of the cards drawn, whether any opens, and whether any is a way in.
+$wpcredits_opens_any = (bool) array_filter( array_intersect_key( $wpcredits_opens, $wpcredits_pages ) );
+$wpcredits_ways_any  = $wpcredits_signed_in && count( array_filter( array_intersect_key( $wpcredits_opens, $wpcredits_pages ) ) ) < count( $wpcredits_pages );
+
 // Each link's whole name, for the state the reader is in: the words the link shows, then the page it
-// is for, as one sentence a translator sees whole.
-$wpcredits_names = $wpcredits_signed_in
-	? array(
-		/* translators: A link's name for screen readers. Start it with the words the link shows ("Open"). */
-		'students'     => __( 'Open your Student Report Card', 'wpcredits-theme' ),
-		/* translators: A link's name for screen readers. Start it with the words the link shows ("Open"). */
-		'mentors'      => __( 'Open your Mentor Report Card', 'wpcredits-theme' ),
-		/* translators: A link's name for screen readers. Start it with the words the link shows ("Open"). */
-		'institutions' => __( 'Open your Institution Dashboard', 'wpcredits-theme' ),
-		/* translators: A link's name for screen readers. Start it with the words the link shows ("Open"). */
-		'sponsors'     => __( 'Open your Sponsor Dashboard', 'wpcredits-theme' ),
-	)
-	: array(
+// is for, as one sentence a translator sees whole. Only the names a card below prints are looked up.
+$wpcredits_names = array();
+
+if ( ! $wpcredits_signed_in ) {
+	$wpcredits_names['sign-in'] = array(
 		/* translators: A link's name for screen readers. Start it with the words the link shows ("Sign in"). */
 		'students'     => __( 'Sign in to your Student Report Card', 'wpcredits-theme' ),
 		/* translators: A link's name for screen readers. Start it with the words the link shows ("Sign in"). */
@@ -96,6 +132,46 @@ $wpcredits_names = $wpcredits_signed_in
 		/* translators: A link's name for screen readers. Start it with the words the link shows ("Sign in"). */
 		'sponsors'     => __( 'Sign in to your Sponsor Dashboard', 'wpcredits-theme' ),
 	);
+}
+
+if ( $wpcredits_opens_any ) {
+	$wpcredits_names['open'] = array(
+		/* translators: A link's name for screen readers. Start it with the words the link shows ("Open"). */
+		'students'     => __( 'Open your Student Report Card', 'wpcredits-theme' ),
+		/* translators: A link's name for screen readers. Start it with the words the link shows ("Open"). */
+		'mentors'      => __( 'Open your Mentor Report Card', 'wpcredits-theme' ),
+		/* translators: A link's name for screen readers. Start it with the words the link shows ("Open"). */
+		'institutions' => __( 'Open your Institution Dashboard', 'wpcredits-theme' ),
+		/* translators: A link's name for screen readers. Start it with the words the link shows ("Open"). */
+		'sponsors'     => __( 'Open your Sponsor Dashboard', 'wpcredits-theme' ),
+	);
+}
+
+if ( $wpcredits_ways_any ) {
+	$wpcredits_names['way'] = array(
+		/* translators: A link's name for screen readers. Start it with the words the link shows ("Register"). */
+		'students'     => __( 'Register as a student', 'wpcredits-theme' ),
+		/* translators: A link's name for screen readers. Start it with the words the link shows ("Apply"). */
+		'mentors'      => __( 'Apply to mentor students', 'wpcredits-theme' ),
+		/* translators: A link's name for screen readers. Start it with the words the link shows ("Apply"). */
+		'institutions' => __( 'Apply to partner as an institution', 'wpcredits-theme' ),
+		/* translators: A link's name for screen readers. Start it with the words the link shows ("Apply"). */
+		'sponsors'     => __( 'Apply to become a sponsor', 'wpcredits-theme' ),
+	);
+}
+
+// The head: for a visitor, and for a reader who opens at least one page, "Already in a program?" over
+// "Sign in to your page" or "Open your page"; for a signed-in reader who opens none, the ways in alone.
+if ( ! $wpcredits_signed_in ) {
+	$wpcredits_eyebrow = __( 'Already in a program?', 'wpcredits-theme' );
+	$wpcredits_heading = __( 'Sign in to your page', 'wpcredits-theme' );
+} elseif ( $wpcredits_opens_any ) {
+	$wpcredits_eyebrow = __( 'Already in a program?', 'wpcredits-theme' );
+	$wpcredits_heading = __( 'Open your page', 'wpcredits-theme' );
+} else {
+	$wpcredits_eyebrow = __( 'Not in a program yet?', 'wpcredits-theme' );
+	$wpcredits_heading = __( 'Get involved', 'wpcredits-theme' );
+}
 
 // The wrapper: wide, and named by the heading, whose ID is fixed because the block is printed once.
 $wpcredits_wrapper = array(
@@ -106,28 +182,38 @@ $wpcredits_wrapper = array(
 ?>
 <section <?php echo get_block_wrapper_attributes( $wpcredits_wrapper ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already-escaped attribute string from core. ?>>
 	<div class="wp-block-group wpc-hub-head">
-		<p class="is-style-eyebrow"><?php esc_html_e( 'Already in a program?', 'wpcredits-theme' ); ?></p>
-		<h2 class="wp-block-heading" id="wpc-hub-sign-in-title"><?php echo esc_html( $wpcredits_signed_in ? __( 'Open your page', 'wpcredits-theme' ) : __( 'Sign in to your page', 'wpcredits-theme' ) ); ?></h2>
+		<p class="is-style-eyebrow"><?php echo esc_html( $wpcredits_eyebrow ); ?></p>
+		<h2 class="wp-block-heading" id="wpc-hub-sign-in-title"><?php echo esc_html( $wpcredits_heading ); ?></h2>
 	</div>
 	<div class="wpc-hub-sign-in__grid" style="--wpc-cards:<?php echo (int) count( $wpcredits_pages ); ?>">
 		<?php
 		foreach ( $wpcredits_pages as $wpcredits_id => $wpcredits_page ) :
 			$wpcredits_title_id = 'wpc-hub-sign-in-' . $wpcredits_id;
 
-			if ( $wpcredits_signed_in ) {
+			if ( ! $wpcredits_signed_in ) {
+				$wpcredits_href = wp_login_url( $wpcredits_page );
+				/* translators: A card's link when the reader is signed out: a verb, it goes to the login screen and then to the page the card names. */
+				$wpcredits_link = _x( 'Sign in', 'a card\'s link to the page it names', 'wpcredits-theme' );
+				$wpcredits_name = $wpcredits_names['sign-in'][ $wpcredits_id ];
+			} elseif ( $wpcredits_opens[ $wpcredits_id ] ) {
 				$wpcredits_href = $wpcredits_page;
 				/* translators: A card's link when the reader is signed in: a verb, it opens the page the card names. */
 				$wpcredits_link = _x( 'Open', 'a card\'s link to the page it names', 'wpcredits-theme' );
+				$wpcredits_name = $wpcredits_names['open'][ $wpcredits_id ];
 			} else {
-				$wpcredits_href = wp_login_url( $wpcredits_page );
-				/* translators: A card's link when the reader is signed out: a verb, it goes to the login screen and then to the page the card names. */
-				$wpcredits_link = _x( 'Sign in', 'a card\'s link to the page it names', 'wpcredits-theme' );
+				$wpcredits_href = $wpcredits_ways[ $wpcredits_id ];
+				$wpcredits_link = 'students' === $wpcredits_id
+					/* translators: The Students card's link for a signed-in reader who is not a student: a verb, it goes to the student registration form. */
+					? _x( 'Register', 'a card\'s link to the way into the program it names', 'wpcredits-theme' )
+					/* translators: A card's link for a signed-in reader who is not in that role: a verb, it goes to that role's application form. */
+					: _x( 'Apply', 'a card\'s link to the way into the program it names', 'wpcredits-theme' );
+				$wpcredits_name = $wpcredits_names['way'][ $wpcredits_id ];
 			}
 			?>
 			<div class="wp-block-group is-style-card wpc-hub-door wpc-hub-sign-in__card">
 				<p class="is-style-eyebrow"><?php echo esc_html( $wpcredits_cards[ $wpcredits_id ]['eyebrow'] ); ?></p>
 				<h3 class="wp-block-heading" id="<?php echo esc_attr( $wpcredits_title_id ); ?>"><?php echo esc_html( $wpcredits_cards[ $wpcredits_id ]['title'] ); ?></h3>
-				<p class="wpc-hub-door__go"><a href="<?php echo esc_url( $wpcredits_href ); ?>" aria-label="<?php echo esc_attr( $wpcredits_names[ $wpcredits_id ] ); ?>"><?php echo esc_html( $wpcredits_link ); ?><span aria-hidden="true"> →</span></a></p>
+				<p class="wpc-hub-door__go"><a href="<?php echo esc_url( $wpcredits_href ); ?>" aria-label="<?php echo esc_attr( $wpcredits_name ); ?>"><?php echo esc_html( $wpcredits_link ); ?><span aria-hidden="true"> →</span></a></p>
 			</div>
 		<?php endforeach; ?>
 	</div>
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php bin/test-hub.php`. Expected: `ALL PASS (276 checks)`. Then the battery line: `battery done` and no `FAIL`; `php bin/check-selectors.php ~/GitHub/wpcredits-program-manager`: `0 placements off the values their neighbors take.` and `No em or en dashes in the theme.`

- [ ] **Step 5: Commit**

```bash
git add blocks/sign-in/render.php bin/test-hub.php bin/stubs/hub-wp.php bin/stubs/migrate-wp.php
git commit -m "1.32.13: the homepage's sign-in cards open only the pages the reader can open, and lead the rest to the way in" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 2: The version, the changelog and the translation template

- [ ] **Step 1:** `Version: 1.32.12` to `Version: 1.32.13` in `style.css` (line 7), `Stable tag: 1.32.12` to `1.32.13` in `readme.txt`, and above `= 1.32.12 =` in its changelog:

```text
= 1.32.13 =

* The homepage's "Open your page" section shows a signed-in reader "Open" only for the pages they can open, as the plugin answers it: a student their Student Report Card, a mentor their Mentor Report Card, an institution's or a sponsor's member their dashboard, an Administrator every page. The other cards lead to that role's way in, the student registration form while it is open, the mentor application, the institution application and, while the Partners page is live, the sponsor application, reading "Register" or "Apply"; a role with no way in right now has no card. A reader in no program reads "Not in a program yet?" over "Get involved". Until now every card said "Open" to everyone signed in, and three of them led a mentor to the access notice (WordPress/WPCredits#294).
* For developers: the hub suite's plugin stand-ins answer membership (`is_student()`, `is_member()`, `is_mentor()`) and the two stubs share one `current_user_can()`.

```

- [ ] **Step 2:** `bash bin/make-pot.sh`, then `grep -n "^Version:" style.css; grep -n "^Stable tag:\|^= 1.32.1[23] =" readme.txt; grep -c "Apply to mentor students" languages/wpcredits-theme.pot`: `1.32.13` in the first two, `= 1.32.13 =` above `= 1.32.12 =`, and the count `1`. The battery line and the selectors check again.
- [ ] **Step 3:** Commit: `git add style.css readme.txt languages/` with the message `1.32.13: version 1.32.13 and its changelog` and the trailer.

---

### Task 3: The selectors check reads the plugin's message builder, and the detail rule is kept for a rollback

Found by the release script's `check theme` on 7 November 2026, on the theme's main as much as on this branch: `bin/check-selectors.php` exits 1 with four classes "printed by nothing". Three are the dashboards' message tones, `.wpcpm-dashboard__message--success`, `--error` and `--warning`: since plugin 1.123.58 every dashboard outcome is printed by one builder, `WPCPM_Dashboard_Card::message()`, as `<base> <base>--<tone> is-<tone>`, so the stem with its `--` is no longer written anywhere and the check's rule for a modifier assembled at run time cannot see them. The fourth, `.wpcpm-dashboard__detail` on the Sponsor Dashboard, went into the card's own message in the same release; plugin 1.123.57 was the last to print it, so its rule is kept for a rollback, as the check's `$kept` list is for. The check learns the builder: its bases are the builder's default and every literal third argument a call passes it, its tones the keys of `TONES`, both read from the plugin's source, so nothing is listed by hand.

**Files:**
- Modify: `bin/check-selectors.php` (`$kept`, the builder's bases and tones, one rule before the run-time modifier rule), `readme.txt` (one clause in the "For developers" changelog line)

- [ ] **Step 1: See it fail**

Run: `php bin/check-selectors.php ~/GitHub/wpcredits-program-manager; echo "exit $?"`. Expected, before the change: four `MISS` lines naming `.wpcpm-dashboard__detail`, `.wpcpm-dashboard__message--error`, `--success` and `--warning`, the summary `4 printed by nothing.` and `exit 1`.

- [ ] **Step 2: The check**

```diff
diff --git a/bin/check-selectors.php b/bin/check-selectors.php
index ef7de74..af62ef9 100644
--- a/bin/check-selectors.php
+++ b/bin/check-selectors.php
@@ -261,8 +261,31 @@ $kept = array(
 	'wpcpm-sponsor__group'       => '1.123.37',
 	'wpcpm-sponsor__group-title' => '1.123.37',
 	'wpcpm-sponsor__group-lead'  => '1.123.37',
+	// The Sponsor Dashboard's outcome detail line went into the card's own message in plugin 1.123.58
+	// (every dashboard outcome inside the card it came from), so 1.123.57 was the last to print it.
+	'wpcpm-dashboard__detail'    => '1.123.57',
 );
 
+// Since plugin 1.123.58 every dashboard outcome is printed by one builder, `WPCPM_Dashboard_Card::message()`,
+// as `<base> <base>--<tone> is-<tone>`: the stem with its `--` is no longer written anywhere, so the rule below
+// for a modifier assembled at run time cannot see these. The bases are the builder's default and every literal
+// third argument a call passes it; the tones are the keys of its `TONES`. Both are read from the plugin's
+// source, so a tone or a base added to the plugin is seen here without a list to keep (theme 1.32.13).
+$message_bases = array();
+$message_tones = array();
+
+if ( preg_match( '/function message\(\s*\$tone,\s*\$text,\s*\$base = \'([a-z0-9_-]+)\'/', $corpus, $found ) ) {
+	$message_bases[] = $found[1];
+}
+
+if ( preg_match_all( '/Dashboard_Card::message\(\s*[^;]*?,\s*\'([a-z0-9_-]+__message[a-z0-9_-]*)\'\s*\)/', $corpus, $found ) ) {
+	$message_bases = array_merge( $message_bases, $found[1] );
+}
+
+if ( preg_match( '/const TONES = array\((.*?)\);/s', $corpus, $found ) && preg_match_all( '/\'([a-z]+)\'\s*=>/', $found[1], $keys ) ) {
+	$message_tones = $keys[1];
+}
+
 $misses = 0;
 $built  = 0;
 $stayed = 0;
@@ -286,6 +309,13 @@ foreach ( $dressed as $class => $where ) {
 		$stem     = substr( $class, 0, $at + 2 );
 		$modifier = substr( $class, $at + 2 );
 
+		// A dashboard outcome's tone, built by the plugin's one message builder (above).
+		if ( in_array( substr( $class, 0, $at ), $message_bases, true ) && in_array( $modifier, $message_tones, true ) ) {
+			++$built;
+			printf( "  built  .%-44s WPCPM_Dashboard_Card::message()'s tone '%s'  (%s)\n", $class, $modifier, implode( ', ', $where ) );
+			continue;
+		}
+
 		if ( wpcredits_printed( $corpus, $stem ) && preg_match( '/[\'"]' . preg_quote( $modifier, '/' ) . '[\'"]/', $corpus ) ) {
 			++$built;
 			printf( "  built  .%-44s '%s' . '%s'  (%s)\n", $class, $stem, $modifier, implode( ', ', $where ) );
```

- [ ] **Step 3: See it pass**

The same run. Expected: `kept   .wpcpm-dashboard__detail ... last printed by plugin 1.123.57`, three `built ... WPCPM_Dashboard_Card::message()'s tone` lines, the summary `19 built at run time, 2 kept for an older plugin, 0 printed by nothing.`, its last line `No em or en dashes in the theme.` and `exit 0`. Then the battery line (no `FAIL`).

- [ ] **Step 4: The changelog**

In `readme.txt`, the 1.32.13 entry's "For developers" line gains, before its final period: `; the selectors check reads the plugin's message builder for the dashboards' message tones, and keeps the Sponsor Dashboard's detail rule for a plugin rolled back to 1.123.57`.

- [ ] **Step 5: Commit**

```bash
git add bin/check-selectors.php readme.txt
git commit -m "1.32.13: the selectors check reads the plugin's message builder, and the detail rule is kept for a rollback" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 4: Try the cards on the Docker copy

The copy at localhost:8900 runs over the live site's database (student registration open, the Partners page not live, as live is on 7 November 2026). Snapshot the copy, install the branch's build as the release script's local deploy does (`python3 release.py build theme`, then `deploy theme --site local --version 1.32.13`, from `~/Projects/wpcredits-release`), sign in through the kit's cookies (`a11y/make-cookies.sh`). Captures at 1280px and 375px go in the session's scratchpad and are backed up under `.superpowers/session-backups/<the day's date>/theme-1-32-13/` in the plugin checkout, with a `trial-notes.md` naming no real person. Restore the snapshot at the end.

- [ ] Signed out, the homepage's sign-in section is as before: four "Sign in" cards (capture).
- [ ] As the copy's test mentor: Mentors "Open" to the Mentor Report Card, Students "Register" to the registration form, Institutions "Apply" to the institution application, no Sponsors card; the head "Already in a program?" over "Open your page" (capture at 1280px and 375px). Each link followed once lands where it says.
- [ ] As the copy's test student: Students "Open", Mentors "Apply" to /become-a-mentor/, Institutions "Apply", no Sponsors card (capture).
- [ ] As the owner's account (an Administrator): four "Open" (capture).
- [ ] As a signed-in account in no program (make a subscriber on the copy, `bin/wp user create`, an example.test address): "Not in a program yet?" over "Get involved", three cards, Register, Apply, Apply (capture); delete the account after.
- [ ] Nothing in the copy's PHP log from the trial.

---

### Task 5: Release, only on the owner's go

- [ ] **Step 1: Read the live version** (`ssh wpcredits-dashboard 'wp theme get wpcredits-theme --field=version'`). Expected `1.32.12`; anything else, say what it reads and wait for the owner.
- [ ] **Step 2: Main holds the branch, on the owner's yes** (`git merge --ff-only` in the theme checkout, or a rebase if main moved).
- [ ] **Step 3: Check, build and scan** (`python3 release.py check theme`, `build theme`, `scan theme`), each ending `PASSED`; read check's watch report of others' changes to the public repository before the mirror.
- [ ] **Step 4: The mirror's message and its dry run**: one plain paragraph in `messages/theme-1.32.13.txt`, no names and no attribution, then `python3 release.py mirror theme --message messages/theme-1.32.13.txt` (exit 3, `NOT RUN`). Give the owner the version, the zip's md5, the checks' results, Task 4's captures, the watch report and the message; wait for an explicit yes.
- [ ] **Step 5: Mirror, then deploy, each on the owner's yes** (`mirror theme --yes --message ...`; `deploy theme`, its dry run, then `deploy theme --yes`).
- [ ] **Step 6: The records**: a note on WordPress/WPCredits#294 that the fix is live, on the owner's yes (the reply of 6 November promised one); the vault's Now page and the theme's memory note gain the release's paragraph; `~/GitHub/wpcredits-theme.zip` is the release's build.

---

## After the plan

- **The planner's calls, for the owner to read with the morning report:** (1) a signed-in reader who opens nothing sees the ways in under "Not in a program yet?" and "Get involved" (the issue's option 2, with the heading changed so "Open your page" never names a page they cannot open); (2) the Students card's way in is the registration form, reading "Register", while the form is open, and the card is left out while it is closed, as the header's Get involved menu already does; (3) the sponsor way in follows the Partners page's launch switch, as every sponsor link on the site does since 1.32.12; (4) a dashboard page that is not published has no card even when its way in exists, as before: the cards are the dashboards' pages.
- **Found at the release gate, 7 November 2026:** the selectors check failed on the theme's main against plugin 1.123.58 (Task 3); the fix is the check's own, not the stylesheet's, since the rules are right and the plugin's markup is right.
- **Known and not pinned here:** an older plugin without `is_student()` or `is_member()` opens no student, institution or sponsor page for anybody but an Administrator (the guards answer false), so those cards become ways in; the plugin live has had all three for months (1.123.58 is live). A dashboard page unpublished while its way in exists leaves the card out for a signed-in reader too, which the trial does not exercise.
- **As built, 7 November 2026 (branch `release/1.32.13-sign-in-cards`, ebb5138..71a63af, every task reviewed at its gate and the whole branch reviewed):** the whole-branch review's two notes (the inserter description still said "each opens it"; a comment claimed the names were looked up per card) were fixed in b5c1c56; Task 3 was added at the release gate as the plan says above. The trial (Task 4) ran on the Docker copy with plugin 1.123.59's build, the copy's front page put on the hub template for it (live's front page uses it; the copy's did not): the visitor, the mentor, the student, an institution's member, the Administrator, the sponsor persona and a subscriber in no program each drew the cards the plan names. Notes and captures: `.superpowers/session-backups/2026-11-07/theme-1-32-13/` in the plugin checkout.
