# Track Builder T2c Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Publishing a track from the Track Builder screen: the read-only preflight, the schema token and the columns it creates, the checklist of what no token can do, verify, unpublishing with its student guard, and the institution gate that keeps a track out of the import until its reports automation names it.

**Architecture:** Three units, so nothing grows a second job. `WPCPM_Track_Columns` turns a question into an Airtable field and says what a column already in the base means for it, with no request in it at all. `WPCPM_Track_Publish` owns the preflight, the ordered run with its lock and its resume, the checklist and verify. `WPCPM_Airtable` gains one call, `create_field()`, the only one that reads the schema token. The screen and its five handlers sit on top, and the definition's own rules stay where they were: the preflight asks `WPCPM_Track_Store::check()`, the same call the editor makes, so the two can never give different answers.

**Tech Stack:** WordPress 6.5 and PHP 7.4 as floors, WordPress coding standards, the plugin's standalone `bin/test-*.php` suites, no build step and no JavaScript.

**Spec:** `docs/specs/2026-09-10-track-builder-design.md`, section 7 for publishing, section 12's T2c row, and decisions 10 and 13 to 20 in section 1. Open items 1, 3 and 7 are settled there.

## Global Constraints

- **Start from `main` at 1.103.0.** `grep "^Stable tag" readme.txt` prints `Stable tag: 1.103.0` and `git status --short` prints nothing. Then `git switch -c track-builder-t2c`. Every block below was proven one commit at a time on `f6b69c2` and replayed onto a fresh checkout.
- WordPress coding standards throughout: tabs, Yoda conditions, spaces inside parentheses, `array()` and never `[]`, strict `in_array()`. Everything that ships is PHP 7.4 compatible: no `match`, no named arguments, no union types. Every string a person reads is US English. No em dash or en dash anywhere, in code, comments, docs or commit messages: a plain hyphen. Full product names ("Student Report Card", "Mentor Report Card", "Administrator Dashboard").
- Everything a person can see is escaped on output, and every handler checks capability first, then nonce, in that order (the design's decision 3.9). `bin/test-track-builder.php` fails if the two are swapped.
- **The battery stays silent after every task:** `for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done` prints nothing. `php bin/check-references.php`, `php bin/check-spelling.php` and `php bin/check-dead-annotations.php` report clean. `bash bin/check-standards.sh` prints no line containing ` ERROR `, and its last line reads `85 warnings, no errors.` or a lower count. Four things it counts are easy to trip: the `=` of consecutive assignments align, the arrows of a multi-line array align, an associative array of more than one item takes one line an item, and a comment line between two assignments ends their alignment block.
- **Test first, every task:** add the checks, run them, see them fail as the step says, then write the code.
- **The token is the product owner's.** They mint it with `schema.bases:write` and paste it into Settings themselves. It is never written into a document, a commit, a message or a test fixture, and no task here asks anybody to hand it over (decision 16).
- **Nothing is deleted in Airtable, ever** (section 14). A failed run leaves what it created and says so; there is no rollback.
- Every new class file is required in `wpcredits-program-manager.php` and in `uninstall.php`, in that order, or `bin/test-roles.php` fails.
- Version numbers move in Task 12 only: plugin 1.104.0, which `version_compare()` orders after 1.103.0. No theme release.
- Comments explain why and name the decision or the review that made the rule. Every commit message starts with "Track Builder T2c:" and ends with the `Co-Authored-By:` trailer of whoever made it, after a blank line.

## What this plan decides

1. **The preflight asks the store the question the editor asks, and adds only what Airtable can answer.** The definition's own rules (a status or key another track holds, a column the syncs own, a built-in track that has drifted from its PHP) are `WPCPM_Track_Store::check()`. The preflight adds the schema questions: does this column exist, what type is it, how many columns would the table hold, is the status a `Status` choice, does the Learn course answer.
2. **Six things refuse and three warn** (decision 17). Refuse: past 500 columns, a sync-owned column, a computed column, a link column other than `Main Contribution Team`, a duplicate status or key, and a built-in track that no longer matches its PHP. Warn: past 450 columns, a nearly-right `Status` choice, an unreachable Learn course.
3. **A failed run resumes and nothing rolls back** (decision 18). Each column that lands is recorded on the post; the next press starts at the first one not recorded. A name already taken is read again: the right type means somebody made it by hand and it counts as landed, the wrong type stops the run.
4. **The lock claims one unchanging value**, because T2a's final review found that a claim whose value changes is not atomic.
5. **Without a schema token, publishing lists the columns instead of failing halfway** (7.2). Every column already there still publishes with no token at all.
6. **Ticking checklist item 1 recompiles**, because the compiled row carries the flag `WPCPM_Tracks::confirmed_automation_statuses()` reads, and that is the list the institution gate reads.
7. **Publishing a built-in track adds no status to the settings** (decision 13). T2a's `publish()` added one unconditionally, which would have put back a status a manager took out.
8. **The student counts are one walk, not one per row** (decision 19, from T2b's whole-branch review), and the settings recompile moves off `WPCPM_Settings::save()`, which the handbook migration also calls before the track post type is registered.

## File structure

**Created**

| File | What it holds |
| --- | --- |
| `includes/tracks/class-wpcpm-track-columns.php` | A question as an Airtable field, and what a column already there means for it. No HTTP. |
| `includes/tracks/class-wpcpm-track-publish.php` | The preflight, the run with its lock and resume, the checklist, verify, and the unpublish guard. |
| `bin/test-track-columns.php` | The column mapper's suite, against the base's own recorded column types. |
| `bin/test-track-publish.php` | Publishing's suite, on stood-in collaborators. |

**Modified**

| File | Why |
| --- | --- |
| `includes/class-wpcpm-airtable.php` | `create_field()`, the schema token on that one call, and each column's type on the schema read. |
| `includes/class-wpcpm-settings.php` | The `schema_token` setting, its mask, and the recompile leaving `save()`. |
| `includes/class-wpcpm-admin.php` | The schema token's row, and the recompile arriving in the settings handler. |
| `includes/tracks/class-wpcpm-track-store.php` | Decision 13: a built-in track adds no status. |
| `includes/tracks/class-wpcpm-track-definition.php` | `maxlength` narrows to text (open item 7). |
| `includes/modules/class-wpcpm-students-sync.php` | `counts_by_status()`, one walk for every count. |
| `includes/modules/class-wpcpm-institutions.php` | `offered_programs()`, the gated map. |
| `includes/modules/class-wpcpm-institution-import-form.php`, `class-wpcpm-institution-create.php` | Both read the gated map (decision 20). |
| `includes/tools/class-wpcpm-track-builder.php`, `class-wpcpm-track-builder-screen.php` | The publish screen and its five handlers. |
| `assets/css/track-builder.css` | The publish screen's own rules. |
| `wpcredits-program-manager.php`, `uninstall.php` | The two new class files. |
| `readme.txt`, `languages/wpcredits-program-manager.pot` | The release. |

---

### Task 1: A question as an Airtable column

**Files:**
- Create: `includes/tracks/class-wpcpm-track-columns.php`
- Create: `bin/test-track-columns.php`
- Modify: `wpcredits-program-manager.php`
- Modify: `uninstall.php`

**Interfaces:**
- Consumes: Nothing. This is the first task and it talks to no other class.
- Produces: `WPCPM_Track_Columns::field( string $column, array $question ): array|null`, the create-field body or null when the control never creates a column; `WPCPM_Track_Columns::judge( string $column, array $question, array $columns ): string`, one of `create`, `ok`, `type_mismatch`, `computed`, `foreign_link`; and the constants `TYPES`, `COMPUTED`, `LINK` and `TEAM_COLUMN`.

Everything publishing does to Airtable starts here, and none of it needs a request. Keeping the map and the verdict in a class of its own is what lets the rules below be held by a suite with no client in it.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-columns.php b/bin/test-track-columns.php
new file mode 100644
index 0000000..32f819d
--- /dev/null
+++ b/bin/test-track-columns.php
@@ -0,0 +1,172 @@
+<?php
+/**
+ * A question as an Airtable column (Track Builder, phase T2c).
+ *
+ * `WPCPM_Track_Columns` is the one place that knows what control becomes what Airtable field, and
+ * what an existing column in the base means for a question that names it. It makes no request, so
+ * this suite needs no client: the schema arrives as the array `fetch_schema()` returns.
+ *
+ * Run from the plugin root:  php bin/test-track-columns.php
+ */
+
+if ( 'cli' !== PHP_SAPI ) {
+	exit( 1 );
+}
+
+define( 'ABSPATH', __DIR__ . '/' );
+
+function __( $s, $d = null ) { return $s; }
+
+require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-columns.php';
+
+$fails = 0;
+$total = 0;
+
+function ck( $label, $got, $want ) {
+	global $fails, $total;
+
+	++$total;
+
+	if ( $got === $want ) {
+		printf( "ok   %s\n", $label );
+		return;
+	}
+
+	++$fails;
+	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
+}
+
+echo "=== A question becomes a column ===\n";
+
+ck( 'text is a single line',
+    WPCPM_Track_Columns::field( 'Slack Name', array( 'type' => 'text' ) ),
+    array( 'name' => 'Slack Name', 'type' => 'singleLineText' ) );
+
+ck( 'textarea is many lines',
+    WPCPM_Track_Columns::field( 'What you did', array( 'type' => 'textarea' ) ),
+    array( 'name' => 'What you did', 'type' => 'multilineText' ) );
+
+ck( 'richtext is rich text, which Airtable stores as Markdown',
+    WPCPM_Track_Columns::field( 'Notes', array( 'type' => 'richtext' ) ),
+    array( 'name' => 'Notes', 'type' => 'richText' ) );
+
+ck( 'url and email are their own types',
+    array(
+        WPCPM_Track_Columns::field( 'Portfolio', array( 'type' => 'url' ) ),
+        WPCPM_Track_Columns::field( 'Personal email', array( 'type' => 'email' ) ),
+    ),
+    array(
+        array( 'name' => 'Portfolio', 'type' => 'url' ),
+        array( 'name' => 'Personal email', 'type' => 'email' ),
+    ) );
+
+ck( 'a whole-number step asks for no decimal places',
+    WPCPM_Track_Columns::field( 'Hours', array( 'type' => 'number', 'step' => '1' ) ),
+    array( 'name' => 'Hours', 'type' => 'number', 'options' => array( 'precision' => 0 ) ) );
+
+ck( 'and a hundredths step asks for two, which is what the forty grades use',
+    WPCPM_Track_Columns::field( 'Final grade', array( 'type' => 'number', 'step' => '0.01' ) ),
+    array( 'name' => 'Final grade', 'type' => 'number', 'options' => array( 'precision' => 2 ) ) );
+
+ck( 'a number with no step at all is whole, rather than refused',
+    WPCPM_Track_Columns::field( 'Count', array( 'type' => 'number' ) ),
+    array( 'name' => 'Count', 'type' => 'number', 'options' => array( 'precision' => 0 ) ) );
+
+ck( 'a checkbox carries the icon and color the base already uses',
+    WPCPM_Track_Columns::field( 'Mentoring opt-in', array( 'type' => 'checkbox' ) ),
+    array( 'name' => 'Mentoring opt-in', 'type' => 'checkbox', 'options' => array( 'icon' => 'check', 'color' => 'greenBright' ) ) );
+
+ck( 'a select carries its options as the choices, in the order they were written',
+    WPCPM_Track_Columns::field( 'Tool used', array( 'type' => 'select', 'options' => array( 'WordPress Studio', 'MAAMP', 'DevKinsta' ) ) ),
+    array(
+        'name'    => 'Tool used',
+        'type'    => 'singleSelect',
+        'options' => array( 'choices' => array( array( 'name' => 'WordPress Studio' ), array( 'name' => 'MAAMP' ), array( 'name' => 'DevKinsta' ) ) ),
+    ) );
+
+ck( 'an image column takes attachments',
+    WPCPM_Track_Columns::field( 'Screenshot', array( 'type' => 'image' ) ),
+    array( 'name' => 'Screenshot', 'type' => 'multipleAttachments' ) );
+
+// The control is written for `Main Contribution Team`, the one column all four tracks share: a
+// second link column would carry a reverse field into a second table (the design's 4.2).
+ck( 'team never asks for a column', WPCPM_Track_Columns::field( 'Main Contribution Team', array( 'type' => 'team' ) ), null );
+
+ck( 'a control nobody has heard of asks for nothing rather than guessing',
+    WPCPM_Track_Columns::field( 'Something', array( 'type' => 'rating' ) ), null );
+
+echo "\n=== The name is the column, verbatim ===\n";
+
+// `key()` hashes the column name and an Airtable name may end in a space, so nothing is trimmed.
+ck( 'a name that ends in a space keeps it',
+    WPCPM_Track_Columns::field( 'Company ', array( 'type' => 'text' ) ),
+    array( 'name' => 'Company ', 'type' => 'singleLineText' ) );
+
+echo "\n=== What an existing column means ===\n";
+
+$columns = array(
+    'What you did'            => array( 'type' => 'multilineText' ),
+    'Hours'                   => array( 'type' => 'number' ),
+    '50h personal link'       => array( 'type' => 'formula' ),
+    'Lessons'                 => array( 'type' => 'multipleRecordLinks' ),
+    'Main Contribution Team'  => array( 'type' => 'multipleRecordLinks' ),
+    "Mentor's email"          => array( 'type' => 'multipleLookupValues' ),
+);
+
+ck( 'a column that is not there yet is one to create',
+    WPCPM_Track_Columns::judge( 'Brand new', array( 'type' => 'text' ), $columns ), 'create' );
+
+ck( 'a column of the right type is ready',
+    WPCPM_Track_Columns::judge( 'What you did', array( 'type' => 'textarea' ), $columns ), 'ok' );
+
+ck( 'a column of another type is a mismatch, because the site would write the wrong shape into it',
+    WPCPM_Track_Columns::judge( 'Hours', array( 'type' => 'text' ), $columns ), 'type_mismatch' );
+
+ck( 'a formula column is computed, and nothing may be written to it',
+    WPCPM_Track_Columns::judge( '50h personal link', array( 'type' => 'text' ), $columns ), 'computed' );
+
+ck( 'so is a lookup',
+    WPCPM_Track_Columns::judge( "Mentor's email", array( 'type' => 'email' ), $columns ), 'computed' );
+
+ck( 'a link column that is not Main Contribution Team reaches into another table',
+    WPCPM_Track_Columns::judge( 'Lessons', array( 'type' => 'team' ), $columns ), 'foreign_link' );
+
+ck( 'and Main Contribution Team itself is the one link a team question may use',
+    WPCPM_Track_Columns::judge( 'Main Contribution Team', array( 'type' => 'team' ), $columns ), 'ok' );
+
+echo "\n=== The base's own columns, as they really are ===\n";
+
+$fixture = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/reports-table-fields.json' ), true );
+$real    = array();
+
+foreach ( (array) $fixture['all_types'] as $name => $type ) {
+    $real[ $name ] = array( 'type' => (string) $type );
+}
+
+$computed = array();
+$foreign  = array();
+
+foreach ( $real as $name => $column ) {
+    $verdict = WPCPM_Track_Columns::judge( $name, array( 'type' => 'text' ), $real );
+
+    if ( 'computed' === $verdict ) {
+        $computed[] = $name;
+    }
+
+    if ( 'foreign_link' === $verdict ) {
+        $foreign[] = $name;
+    }
+}
+
+sort( $computed );
+sort( $foreign );
+
+ck( 'the four computed columns of the real table are named as computed',
+    $computed, array( '50h personal link', 'Dev Track ONLY personal link', "Mentor's email", 'Personal link' ) );
+
+ck( 'and its five other link columns are refused while Main Contribution Team is not among them',
+    $foreign, array( 'Company ', 'Educational institution', 'Lessons', 'Mentor', 'Students' ) );
+
+printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );
+
+exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-columns.php`
Expected: `PHP Fatal error: Uncaught Error: Failed opening required '.../class-wpcpm-track-columns.php'`, because the class does not exist yet.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/tracks/class-wpcpm-track-columns.php b/includes/tracks/class-wpcpm-track-columns.php
new file mode 100644
index 0000000..23dee30
--- /dev/null
+++ b/includes/tracks/class-wpcpm-track-columns.php
@@ -0,0 +1,164 @@
+<?php
+/**
+ * A track's questions as Airtable columns.
+ *
+ * @package WPCredits_Program_Manager
+ */
+
+defined( 'ABSPATH' ) || exit;
+
+/**
+ * What a question becomes in the base, and what a column already there means for it.
+ *
+ * One job, and no request: publishing (`WPCPM_Track_Publish`) asks this class what to create and
+ * what to make of what it finds, then does the talking. Keeping the two apart is what lets every
+ * rule below be held by a suite that needs no Airtable at all (the design's section 3).
+ */
+final class WPCPM_Track_Columns {
+
+	/**
+	 * The control a question uses, and the Airtable type a new column for it gets.
+	 *
+	 * The design's 4.2 fixes this map. `team` is absent on purpose: it is written for
+	 * `Main Contribution Team`, the one column all four tracks share, and a second link column
+	 * would carry a reverse field into a second table, so a team question never creates one.
+	 *
+	 * @var array<string,string>
+	 */
+	const TYPES = array(
+		'text'     => 'singleLineText',
+		'textarea' => 'multilineText',
+		'richtext' => 'richText',
+		'url'      => 'url',
+		'email'    => 'email',
+		'number'   => 'number',
+		'checkbox' => 'checkbox',
+		'select'   => 'singleSelect',
+		'image'    => 'multipleAttachments',
+	);
+
+	/**
+	 * The Airtable types nothing may be written to.
+	 *
+	 * A student's answer sent to one of these is refused by Airtable, and a question bound to one
+	 * would look saved on the site and be missing in the base (the design's 4.2).
+	 *
+	 * @var string[]
+	 */
+	const COMPUTED = array( 'formula', 'rollup', 'count', 'multipleLookupValues' );
+
+	/**
+	 * Airtable's own type for a link to another table.
+	 *
+	 * @var string
+	 */
+	const LINK = 'multipleRecordLinks';
+
+	/**
+	 * The one link column a question may use, because all four tracks already share it.
+	 *
+	 * @var string
+	 */
+	const TEAM_COLUMN = 'Main Contribution Team';
+
+	/**
+	 * The column a question asks Airtable to create.
+	 *
+	 * @param string $column   The column name, verbatim: `key()` hashes it and an Airtable name may
+	 *                         end in a space, so nothing here trims it (the design's 4.2).
+	 * @param array  $question The question, as the definition holds it.
+	 * @return array|null The create-field body, or null when this control never creates a column.
+	 */
+	public static function field( $column, array $question ) {
+		$type = isset( $question['type'] ) ? (string) $question['type'] : '';
+
+		if ( ! isset( self::TYPES[ $type ] ) ) {
+			return null;
+		}
+
+		$field = array(
+			'name' => (string) $column,
+			'type' => self::TYPES[ $type ],
+		);
+
+		if ( 'number' === $type ) {
+			// Airtable asks how many decimal places to keep, and the step the form already uses
+			// says it: the thirteen whole counts step by 1, the forty grades by 0.01.
+			$field['options'] = array( 'precision' => self::precision( $question ) );
+		}
+
+		if ( 'checkbox' === $type ) {
+			// The base's own checkbox, so a new one does not stand out beside it (4.2).
+			$field['options'] = array(
+				'icon'  => 'check',
+				'color' => 'greenBright',
+			);
+		}
+
+		if ( 'select' === $type ) {
+			$choices = array();
+
+			foreach ( isset( $question['options'] ) ? (array) $question['options'] : array() as $choice ) {
+				$choices[] = array( 'name' => (string) $choice );
+			}
+
+			$field['options'] = array( 'choices' => $choices );
+		}
+
+		return $field;
+	}
+
+	/**
+	 * What a column already in the base means for the question that names it.
+	 *
+	 * @param string $column   The column name.
+	 * @param array  $question The question.
+	 * @param array  $columns  The table's columns, keyed by name, each with a `type`, as
+	 *                         `WPCPM_Airtable::fetch_schema()` reports them.
+	 * @return string `create` when it is not there, `ok` when it is ready to be written to,
+	 *                `type_mismatch`, `computed`, or `foreign_link`.
+	 */
+	public static function judge( $column, array $question, array $columns ) {
+		$column = (string) $column;
+
+		if ( ! isset( $columns[ $column ]['type'] ) ) {
+			return 'create';
+		}
+
+		$type = (string) $columns[ $column ]['type'];
+
+		if ( in_array( $type, self::COMPUTED, true ) ) {
+			return 'computed';
+		}
+
+		if ( self::LINK === $type ) {
+			// The reverse field a link carries is why only the shared one passes (4.2).
+			return self::TEAM_COLUMN === $column ? 'ok' : 'foreign_link';
+		}
+
+		$wanted = isset( $question['type'] ) ? (string) $question['type'] : '';
+
+		if ( ! isset( self::TYPES[ $wanted ] ) ) {
+			return 'ok';
+		}
+
+		return self::TYPES[ $wanted ] === $type ? 'ok' : 'type_mismatch';
+	}
+
+	/**
+	 * How many decimal places a number question keeps.
+	 *
+	 * @param array $question The question.
+	 * @return int
+	 */
+	private static function precision( array $question ) {
+		$step = isset( $question['step'] ) ? (string) $question['step'] : '';
+		$dot  = strpos( $step, '.' );
+
+		if ( false === $dot ) {
+			return 0;
+		}
+
+		return strlen( rtrim( substr( $step, $dot + 1 ), '0' ) );
+	}
+}
diff --git a/uninstall.php b/uninstall.php
index 285251c..c7a90d1 100644
--- a/uninstall.php
+++ b/uninstall.php
@@ -52,6 +52,7 @@ require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-pdf-check.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-form-guard.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-form-stash.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-track-palette.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-track-columns.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-track-definition.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-tracks.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-track-store.php';
diff --git a/wpcredits-program-manager.php b/wpcredits-program-manager.php
index 249c7a9..d8fc1fe 100644
--- a/wpcredits-program-manager.php
+++ b/wpcredits-program-manager.php
@@ -44,6 +44,7 @@ require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-contribution-teams.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-palette.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-columns.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-definition.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-tracks.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-store.php';
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-columns.php`
Expected: `ALL PASS (22 checks)`

- [ ] **Step 5: Run everything**

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
bash bin/check-standards.sh 2>&1 | tail -1
```

Expected: silent, clean, and `85 warnings, no errors.`

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Track Builder T2c: a question as an Airtable column"
```

---

### Task 2: The schema token, create_field(), and types on the schema read

**Files:**
- Modify: `includes/class-wpcpm-airtable.php`
- Modify: `includes/class-wpcpm-settings.php`
- Modify: `includes/class-wpcpm-admin.php`
- Test: `bin/test-airtable.php`
- Test: `bin/test-settings.php`

**Interfaces:**
- Consumes: Nothing from Task 1.
- Produces: `WPCPM_Airtable::create_field( string $table, array $field ): array|WP_Error`, refusing with `wpcpm_no_schema_token` when none is set and with `wpcpm_airtable_field_exists` when the name is taken; `WPCPM_Settings::masked_schema_token(): string` and `WPCPM_Settings::has_schema_token(): bool`; and a `columns` map per table on `fetch_schema()`, each entry `type` and `options`.

The everyday token must never gain the right to change the base's structure, because the syncs run every three hours on it. `fields` keeps its old shape, name to description, because the mentors sync stores exactly that and reads it back for the mentor page: the types arrive in a new key beside it.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-airtable.php b/bin/test-airtable.php
index 530570d..8ff8ad5 100644
--- a/bin/test-airtable.php
+++ b/bin/test-airtable.php
@@ -558,6 +558,87 @@ ck( 'nothing to delete sends nothing', array( $airtable->delete_records( 'tblX',
 $no_token = new WPCPM_Airtable( array( 'api_token' => '', 'base_id' => 'appTEST' ) );
 ck( 'and without a token it refuses before sending', array( $no_token->delete_records( 'tblX', array( $del[0] ) )->get_error_code(), sent() ), array( 'wpcpm_no_token', 0 ) );
 
+echo "\n=== Creating a column, the one call that uses the schema token ===\n";
+
+// The everyday token stays at records plus schema read, so the syncs that run every three hours
+// never hold the right to change the base's structure (the design's 7.2).
+$schema_client = new WPCPM_Airtable( array( 'api_token' => 'pat-everyday', 'schema_token' => 'pat-schema', 'base_id' => 'appTEST' ) );
+
+fresh( 'web' );
+queue( response( 200, array( 'id' => 'fldNEW', 'name' => 'What you did', 'type' => 'multilineText' ) ) );
+$made = $schema_client->create_field( 'tblX', array( 'name' => 'What you did', 'type' => 'multilineText' ) );
+
+ck( 'the created column comes back as Airtable describes it',
+	$made, array( 'id' => 'fldNEW', 'name' => 'What you did', 'type' => 'multilineText' ) );
+
+$call = $GLOBALS['sent'][0];
+
+ck( 'it is a POST to the base metadata endpoint for that table',
+	array( $call['args']['method'], $call['url'] ),
+	array( 'POST', 'https://api.airtable.com/v0/meta/bases/appTEST/tables/tblX/fields' ) );
+
+ck( 'carrying the schema token and not the everyday one',
+	$call['args']['headers']['Authorization'], 'Bearer pat-schema' );
+
+ck( 'and the field as the body', json_decode( $call['args']['body'], true ), array( 'name' => 'What you did', 'type' => 'multilineText' ) );
+
+fresh( 'web' );
+$no_schema = new WPCPM_Airtable( array( 'api_token' => 'pat-everyday', 'base_id' => 'appTEST' ) );
+$refused   = $no_schema->create_field( 'tblX', array( 'name' => 'X', 'type' => 'singleLineText' ) );
+
+ck( 'with no schema token it refuses before sending, in its own words',
+	array( $refused->get_error_code(), sent() ), array( 'wpcpm_no_schema_token', 0 ) );
+
+fresh( 'web' );
+queue( response( 403, array( 'error' => array( 'type' => 'INVALID_PERMISSIONS_OR_MODEL_NOT_FOUND' ) ) ) );
+$forbidden = $schema_client->create_field( 'tblX', array( 'name' => 'X', 'type' => 'singleLineText' ) );
+
+ck( 'a 403 names both things it could be, since neither is visible from here',
+	array( $forbidden->get_error_code(), false !== strpos( $forbidden->get_error_message(), 'schema.bases:write' ), false !== strpos( $forbidden->get_error_message(), 'base creator' ) ),
+	array( 'wpcpm_airtable_error', true, true ) );
+
+fresh( 'web' );
+queue( response( 422, array( 'error' => array( 'type' => 'DUPLICATE_OR_EMPTY_FIELD_NAME' ) ) ) );
+$taken = $schema_client->create_field( 'tblX', array( 'name' => 'Hours', 'type' => 'number' ) );
+
+ck( 'a name already taken is its own code, because publishing reads that column again rather than stopping',
+	$taken->get_error_code(), 'wpcpm_airtable_field_exists' );
+
+echo "\n=== The schema read carries each column's type, beside the descriptions ===\n";
+
+fresh( 'web' );
+queue(
+	response(
+		200,
+		array(
+			'tables' => array(
+				array(
+					'id'              => 'tblX',
+					'name'            => 'Students Reports',
+					'primaryFieldId'  => 'fld1',
+					'fields'          => array(
+						array( 'id' => 'fld1', 'name' => 'Name', 'type' => 'singleLineText', 'description' => 'Their name' ),
+						array( 'id' => 'fld2', 'name' => 'Tool used', 'type' => 'singleSelect', 'options' => array( 'choices' => array( array( 'name' => 'MAAMP' ) ) ) ),
+					),
+				),
+			),
+		)
+	)
+);
+$schema = $airtable->fetch_schema();
+
+// `fields` keeps its old shape, name to description: the mentors sync stores it that way and
+// reads it back as the descriptions shown on the mentor page.
+ck( 'the descriptions are where they always were',
+	$schema['tblX']['fields'], array( 'Name' => 'Their name', 'Tool used' => '' ) );
+
+ck( 'and the types arrive beside them, with a select carrying its choices',
+	$schema['tblX']['columns'],
+	array(
+		'Name'      => array( 'type' => 'singleLineText', 'options' => array() ),
+		'Tool used' => array( 'type' => 'singleSelect', 'options' => array( 'choices' => array( array( 'name' => 'MAAMP' ) ) ) ),
+	) );
+
 echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
 
 exit( $fail ? 1 : 0 );
diff --git a/bin/test-settings.php b/bin/test-settings.php
index e9159ea..80207e1 100644
--- a/bin/test-settings.php
+++ b/bin/test-settings.php
@@ -172,6 +172,7 @@ ck( 'the handler derives its keys from the defaults, not a hand-written list',
 // round trip - the failure this file exists for is a field that saves nothing.
 $probe = array(
 	'api_token'                     => 'patTESTTOKEN1234567890',
+	'schema_token'                  => 'patSCHEMATOKEN9876543210',
 	'base_id'                       => 'appPROBE0000000001',
 	'mentors_table'                 => 'tblPROBE0000000002',
 	'mentor_status'                 => 'Probe active',
@@ -260,6 +261,34 @@ foreach ( $bool_probe as $key => $value ) {
 	ck( sprintf( '%s can be flipped to %s', $key, var_export( $value, true ) ), array( $saved[ $key ] ), array( $value ) );
 }
 
+echo "\n=== The schema token, the second secret ===\n";
+
+$GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'schema_token' => 'patKEEPME0000000000' ) );
+
+ck( 'a blank schema token leaves the stored one alone, as the everyday token does',
+    WPCPM_Settings::save( array( 'schema_token' => '' ) )['schema_token'], 'patKEEPME0000000000' );
+
+$GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'schema_token' => 'patKEEPME0000000000' ) );
+
+ck( 'the mask coming back on submit never overwrites it either',
+    WPCPM_Settings::save( array( 'schema_token' => WPCPM_Settings::masked_schema_token() ) )['schema_token'], 'patKEEPME0000000000' );
+
+$GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'schema_token' => 'patKEEPME0000000000' ) );
+
+// A field that only ever shows a mask has no other way to say "take it away", and a site that
+// has finished creating columns should be able to put the right back.
+ck( 'and the word remove clears it, which is the only way to take the right away again',
+    WPCPM_Settings::save( array( 'schema_token' => 'Remove' ) )['schema_token'], '' );
+
+$GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'schema_token' => 'patKEEPME0000000000' ) );
+
+ck( 'the mask shows the last four characters and nothing else',
+    WPCPM_Settings::masked_schema_token(), str_repeat( "\u{2022}", 12 ) . '0000' );
+
+ck( 'and the site knows whether it may create columns at all',
+    array( WPCPM_Settings::has_schema_token(), ( function () { $GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'schema_token' => '' ) ); return WPCPM_Settings::has_schema_token(); } )() ),
+    array( true, false ) );
+
 ck( 'every setting has a round-trip probe',
     array_values( array_diff( array_keys( $defaults ), array_keys( $probe ), array_keys( $bool_probe ) ) ),
     array() );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-airtable.php`
Expected: `PHP Fatal error: Uncaught Error: Call to undefined method WPCPM_Airtable::create_field()`.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/class-wpcpm-admin.php b/includes/class-wpcpm-admin.php
index 8284704..1f784a1 100644
--- a/includes/class-wpcpm-admin.php
+++ b/includes/class-wpcpm-admin.php
@@ -400,6 +400,14 @@ class WPCPM_Admin {
 			'password'
 		);
 
+		$this->text_row(
+			'schema_token',
+			__( 'Schema token', 'wpcredits-program-manager' ),
+			WPCPM_Settings::masked_schema_token(),
+			__( 'Optional, and used by the Track Builder alone: the token that creates a track\'s columns when it is published. It needs the "schema.bases:write" scope and must belong to somebody with the base creator role on the base. Leave blank to keep the current one, or type remove to take it away. Without it, publishing lists the columns for somebody to create by hand.', 'wpcredits-program-manager' ),
+			'password'
+		);
+
 		$this->render_scopes_row();
 
 		$this->text_row( 'base_id', __( 'Base ID', 'wpcredits-program-manager' ), $settings['base_id'] );
diff --git a/includes/class-wpcpm-airtable.php b/includes/class-wpcpm-airtable.php
index b4b6c1c..b463eb0 100644
--- a/includes/class-wpcpm-airtable.php
+++ b/includes/class-wpcpm-airtable.php
@@ -420,6 +420,7 @@ class WPCPM_Airtable {
 			}
 
 			$fields     = array();
+			$columns    = array();
 			$primary_id = isset( $table['primaryFieldId'] ) ? (string) $table['primaryFieldId'] : '';
 			$primary    = '';
 
@@ -435,6 +436,15 @@ class WPCPM_Airtable {
 					// "never read".
 					$fields[ (string) $field['name'] ] = isset( $field['description'] ) ? trim( (string) $field['description'] ) : '';
 
+					// The type and its options, in a map of their own. `fields` keeps its old
+					// shape, name to description, because the mentors sync stores exactly that
+					// and reads it back for the mentor page; publishing needs the type, so it
+					// reads `columns` (the design's 7.1).
+					$columns[ (string) $field['name'] ] = array(
+						'type'    => isset( $field['type'] ) ? (string) $field['type'] : '',
+						'options' => ( isset( $field['options'] ) && is_array( $field['options'] ) ) ? $field['options'] : array(),
+					);
+
 					// The primary field's *name*, resolved from the ID the schema
 					// reports. This is the only reliable way to know which column
 					// carries a record's display value: a records response gives no
@@ -449,12 +459,68 @@ class WPCPM_Airtable {
 				'name'    => isset( $table['name'] ) ? (string) $table['name'] : '',
 				'primary' => $primary,
 				'fields'  => $fields,
+				'columns' => $columns,
 			);
 		}
 
 		return $schema;
 	}
 
+	/**
+	 * Create one column on a table.
+	 *
+	 * The only call in this client that uses the schema token. Everything else keeps using the
+	 * everyday one, which stays at records plus schema read, so the syncs that run every three
+	 * hours never hold the right to change the base's structure (the design's 7.2).
+	 *
+	 * @param string $table Table ID or name.
+	 * @param array  $field The field body: `name`, `type` and, for some types, `options`, as
+	 *                      `WPCPM_Track_Columns::field()` builds it.
+	 * @return array|WP_Error The created field as Airtable describes it, or an error.
+	 *                        `wpcpm_airtable_field_exists` means the name is taken, which
+	 *                        publishing answers by reading that column rather than stopping.
+	 */
+	public function create_field( $table, array $field ) {
+		if ( empty( $this->settings['schema_token'] ) ) {
+			return new WP_Error(
+				'wpcpm_no_schema_token',
+				__( 'No Airtable schema token is configured, so the site cannot create columns. Add one on the WPCredits Program -> Settings screen, or create the columns by hand from the list on the publish screen.', 'wpcredits-program-manager' )
+			);
+		}
+
+		if ( empty( $this->settings['base_id'] ) ) {
+			return new WP_Error( 'wpcpm_no_base', __( 'The Airtable Base ID is missing from the plugin settings.', 'wpcredits-program-manager' ) );
+		}
+
+		$url = trailingslashit( self::API_BASE ) . 'meta/bases/' . rawurlencode( $this->settings['base_id'] ) . '/tables/' . rawurlencode( $table ) . '/fields';
+
+		$response = $this->request( $url, 'POST', $field, (string) $this->settings['schema_token'] );
+
+		if ( is_wp_error( $response ) ) {
+			$data   = $response->get_error_data();
+			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
+
+			// Airtable refuses a taken name with a 422. Publishing reads the column again and
+			// counts it as landed when its type is right, because somebody making it by hand is
+			// the documented way to work without a schema token (the design's 7.2 step 1).
+			if ( 422 === $status ) {
+				return new WP_Error(
+					'wpcpm_airtable_field_exists',
+					sprintf(
+						/* translators: %s: column name. */
+						__( 'Airtable already has a column named "%s" on this table.', 'wpcredits-program-manager' ),
+						isset( $field['name'] ) ? (string) $field['name'] : ''
+					),
+					$data
+				);
+			}
+
+			return $response;
+		}
+
+		return $response;
+	}
+
 	/**
 	 * Verify the token and base by asking one table for one record.
 	 *
@@ -684,9 +750,11 @@ class WPCPM_Airtable {
 	 * @param string     $url    Absolute request URL.
 	 * @param string     $method HTTP method.
 	 * @param array|null $body   Optional payload, JSON-encoded.
+	 * @param string     $token  Token to authenticate with. The everyday one when empty; the
+	 *                           schema token is passed here by `create_field()` alone.
 	 * @return array|WP_Error Decoded response body.
 	 */
-	private function request( $url, $method = 'GET', $body = null ) {
+	private function request( $url, $method = 'GET', $body = null, $token = '' ) {
 		// A 429 seen earlier - by this process, or through the option by any other - is
 		// honoured before anything is sent. Airtable counts the requests it refuses, so
 		// every one sent inside the window pushes the base's thirty seconds out again,
@@ -730,7 +798,7 @@ class WPCPM_Airtable {
 			'method'  => $method,
 			'timeout' => 20,
 			'headers' => array(
-				'Authorization' => 'Bearer ' . $this->settings['api_token'],
+				'Authorization' => 'Bearer ' . ( '' !== (string) $token ? (string) $token : $this->settings['api_token'] ),
 				'Accept'        => 'application/json',
 			),
 		);
@@ -782,7 +850,12 @@ class WPCPM_Airtable {
 			// request, and Airtable's own message does not say which one. Naming the
 			// scope turns a dead end into something fixable.
 			if ( 403 === $code || 401 === $code ) {
-				if ( 'GET' !== strtoupper( $method ) ) {
+				if ( 'GET' !== strtoupper( $method ) && false !== strpos( $url, '/meta/bases/' ) ) {
+					// Two different things wear this status on a schema write, and neither is
+					// visible from here: the scope, and who owns the token. Naming both is the
+					// difference between a fixable message and a dead end (the design's 7.2).
+					$message .= ' ' . __( 'This was a change to the base structure, so the token needs the "schema.bases:write" scope and must belong to somebody with the base creator role on this base.', 'wpcredits-program-manager' );
+				} elseif ( 'GET' !== strtoupper( $method ) ) {
 					$message .= ' ' . __( 'This was a write, so the token most likely lacks the "data.records:write" scope. Add it at airtable.com/create/tokens, or use report-only mode.', 'wpcredits-program-manager' );
 				} elseif ( false !== strpos( $url, '/meta/bases/' ) ) {
 					$message .= ' ' . __( 'This was a schema read, so the token most likely lacks the "schema.bases:read" scope.', 'wpcredits-program-manager' );
diff --git a/includes/class-wpcpm-settings.php b/includes/class-wpcpm-settings.php
index fc6cf5a..17d979f 100644
--- a/includes/class-wpcpm-settings.php
+++ b/includes/class-wpcpm-settings.php
@@ -39,6 +39,7 @@ class WPCPM_Settings {
 	public static function defaults() {
 		return array(
 			'api_token'                     => '',
+			'schema_token'                  => '',
 			'base_id'                       => 'appIzQKfwTn5dyPVp',
 			'mentors_table'                 => 'tblJmEYgBWYxVuzUw',
 			'mentor_status'                 => 'Active',
@@ -259,6 +260,20 @@ class WPCPM_Settings {
 			}
 		}
 
+		// The schema token is the same secret in a second field, kept apart so the everyday
+		// token never carries the right to change the base's structure (the design's 7.2).
+		// Blank leaves it alone, as above, and the word "remove" clears it, because a field
+		// that only ever shows a mask has no other way to say "take it away".
+		if ( isset( $input['schema_token'] ) ) {
+			$schema = trim( wp_unslash( $input['schema_token'] ) );
+
+			if ( 'remove' === strtolower( $schema ) ) {
+				$clean['schema_token'] = '';
+			} elseif ( '' !== $schema && ! self::is_mask( $schema ) ) {
+				$clean['schema_token'] = sanitize_text_field( $schema );
+			}
+		}
+
 		foreach ( array( 'base_id', 'mentors_table', 'reports_table', 'students_table', 'tutors_table', 'feedback_table', 'institutions_table', 'teams_table', 'team_members_table', 'sponsors_table', 'countries_table', 'institutions_name_field', 'teams_name_field', 'sponsors_name_field', 'countries_name_field', 'institution_new_stage' ) as $key ) {
 			if ( isset( $input[ $key ] ) ) {
 				$clean[ $key ] = sanitize_text_field( wp_unslash( $input[ $key ] ) );
@@ -716,6 +731,36 @@ class WPCPM_Settings {
 		return str_repeat( '•', 12 ) . substr( $token, -4 );
 	}
 
+	/**
+	 * Detect the masked placeholder coming back on submit.
+	 *
+	 * @param string $value Submitted value.
+	 * @return bool
+	 */
+	/**
+	 * The schema token as the screen may show it: a mask, never the secret.
+	 *
+	 * @return string
+	 */
+	public static function masked_schema_token() {
+		$token = (string) self::get_value( 'schema_token', '' );
+
+		if ( '' === $token ) {
+			return '';
+		}
+
+		return str_repeat( '•', 12 ) . substr( $token, -4 );
+	}
+
+	/**
+	 * Whether the site can create columns at all.
+	 *
+	 * @return bool
+	 */
+	public static function has_schema_token() {
+		return '' !== (string) self::get_value( 'schema_token', '' );
+	}
+
 	/**
 	 * Detect the masked placeholder coming back on submit.
 	 *
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-airtable.php`
Expected: `ALL PASS` from `bin/test-airtable.php` and `ALL PASS` from `bin/test-settings.php`

- [ ] **Step 5: Run everything**

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
bash bin/check-standards.sh 2>&1 | tail -1
```

Expected: silent, clean, and `85 warnings, no errors.`

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Track Builder T2c: the schema token, and the one call that uses it"
```

---

### Task 3: The preflight

**Files:**
- Create: `includes/tracks/class-wpcpm-track-publish.php`
- Create: `bin/test-track-publish.php`
- Modify: `wpcredits-program-manager.php`
- Modify: `uninstall.php`

**Interfaces:**
- Consumes: `WPCPM_Track_Columns::judge()` and `::field()` from Task 1, and `fetch_schema()`'s `columns` map from Task 2.
- Produces: `WPCPM_Track_Publish::preflight( int $post_id ): array` with `refusals`, `warnings`, `columns` (`create` and `ready`), `choices` (`reports` and `students`, each `ok`, `near` or `missing`), `fields` (`now` and `after`), `adds_status` and `ready`; plus the constants `FIELD_CEILING`, `FIELD_WARNING` and `STATUS_COLUMN`.

This is the screen's whole answer to "what would happen". The definition's rules come from `WPCPM_Track_Store::check()` so the editor and Publish cannot disagree; everything else is a question only the base can answer.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-publish.php b/bin/test-track-publish.php
new file mode 100644
index 0000000..1727bab
--- /dev/null
+++ b/bin/test-track-publish.php
@@ -0,0 +1,267 @@
+<?php
+/**
+ * The preflight (Track Builder, phase T2c): what publishing would refuse, and what it would warn about.
+ *
+ * The preflight answers two different kinds of question. The definition's own rules are the store's
+ * `check()`, the same call the editor makes, so the two can never disagree. Everything else is a
+ * question about the base: does this column exist, what type is it, how many columns would the
+ * table have afterwards, and is the track's status one of the `Status` choices. Airtable is stood
+ * in for here, so every rule below is held without a request.
+ *
+ * Run from the plugin root:  php bin/test-track-publish.php
+ */
+
+if ( 'cli' !== PHP_SAPI ) {
+	exit( 1 );
+}
+
+define( 'ABSPATH', __DIR__ . '/' );
+
+function __( $s, $d = null ) { return $s; }
+function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
+function wp_remote_head( $url, $args = array() ) { return $GLOBALS['head'][ $url ] ?? array( 'response' => array( 'code' => 200 ) ); }
+function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? (int) ( $r['response']['code'] ?? 0 ) : 0; }
+
+class WP_Error {
+	private $code;
+	private $message;
+	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
+	public function get_error_code() { return $this->code; }
+	public function get_error_message() { return $this->message; }
+}
+
+/** The store, stood in: the definition, what `check()` says of it, and where a built-in track stands. */
+class WPCPM_Track_Store {
+	public static $definitions = array();
+	public static $errors      = array();
+	public static $sources     = array();
+
+	public static function get( $post_id ) { return self::$definitions[ $post_id ] ?? null; }
+	public static function check( $post_id, array $definition ) { return self::$errors[ $post_id ] ?? array(); }
+	public static function source( $post_id ) { return self::$sources[ $post_id ] ?? 'definition'; }
+}
+
+/** The settings, stood in: the two tables the `Status` choice has to exist on. */
+class WPCPM_Settings {
+	public static $values = array( 'reports_table' => 'tblReports', 'students_table' => 'tblStudents' );
+	public static function get() { return self::$values; }
+	public static function get_value( $key, $default = '' ) { return self::$values[ $key ] ?? $default; }
+	public static function has_schema_token() { return ! empty( self::$values['schema_token'] ); }
+}
+
+/** The client, stood in: one canned schema, and a note of whether it was asked. */
+class WPCPM_Airtable {
+	public static $schema = array();
+	public static $reads  = 0;
+
+	public function fetch_schema() {
+		++self::$reads;
+
+		return self::$schema instanceof WP_Error ? self::$schema : self::$schema;
+	}
+}
+
+require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-columns.php';
+require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-publish.php';
+
+$fails = 0;
+$total = 0;
+
+function ck( $label, $got, $want ) {
+	global $fails, $total;
+
+	++$total;
+
+	if ( $got === $want ) {
+		printf( "ok   %s\n", $label );
+		return;
+	}
+
+	++$fails;
+	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
+}
+
+/** The codes a preflight answered, in the order it answered them. */
+function codes( array $findings ) {
+	return array_map(
+		function ( $finding ) {
+			return $finding['code'];
+		},
+		$findings
+	);
+}
+
+/** A base with the columns named, each of the type given, plus a `Status` single select. */
+function base( array $columns, array $status_choices = array( 'In Sensei', 'Developer Track' ), $extra = 0 ) {
+	$reports = array();
+
+	foreach ( $columns as $name => $type ) {
+		$reports[ $name ] = array( 'type' => $type, 'options' => array() );
+	}
+
+	$choices = array();
+
+	foreach ( $status_choices as $choice ) {
+		$choices[] = array( 'name' => $choice );
+	}
+
+	$reports['Status'] = array( 'type' => 'singleSelect', 'options' => array( 'choices' => $choices ) );
+
+	// Padding, so a table can be brought near the 500-column ceiling without naming 500 columns.
+	for ( $i = 0; $i < $extra; $i++ ) {
+		$reports[ 'Filler ' . $i ] = array( 'type' => 'singleLineText', 'options' => array() );
+	}
+
+	return array(
+		'tblReports'  => array( 'name' => 'Students Reports', 'columns' => $reports ),
+		'tblStudents' => array( 'name' => 'Students', 'columns' => array( 'Status' => $reports['Status'] ) ),
+	);
+}
+
+/** A track of two questions, one already in the base and one not. */
+function track( $questions = null ) {
+	return array(
+		'schema_version'  => 1,
+		'status'          => 'Marketing Track',
+		'key'             => 'marketing',
+		'label'           => 'Marketing Track',
+		'course_url'      => 'https://learn.wordpress.org/course/marketing/',
+		'learn_course_id' => 500001,
+		'hours_target'    => 0,
+		'hue'             => 'rose',
+		'questions'       => null === $questions ? array(
+			'What you did' => array( 'label' => 'What you did', 'type' => 'textarea', 'group' => 'project' ),
+			'Brand new'    => array( 'label' => 'Brand new', 'type' => 'text', 'group' => 'project' ),
+		) : $questions,
+	);
+}
+
+echo "=== A track that is ready to publish ===\n";
+
+WPCPM_Track_Store::$definitions = array( 7 => track() );
+WPCPM_Track_Store::$errors      = array();
+WPCPM_Airtable::$schema         = base( array( 'What you did' => 'multilineText' ), array( 'In Sensei', 'Marketing Track' ) );
+
+$flight = WPCPM_Track_Publish::preflight( 7 );
+
+ck( 'nothing is refused', codes( $flight['refusals'] ), array() );
+ck( 'and nothing is warned about', codes( $flight['warnings'] ), array() );
+ck( 'so it is ready', $flight['ready'], true );
+ck( 'the column the base already has is ready, and the other is one to create',
+    array( $flight['columns']['ready'], $flight['columns']['create'] ),
+    array( array( 'What you did' ), array( 'Brand new' ) ) );
+ck( 'the status is a choice on both tables', $flight['choices'], array( 'reports' => 'ok', 'students' => 'ok' ) );
+
+echo "\n=== What it refuses ===\n";
+
+// The definition's own rules are the store's, asked once. A screen that asked its own questions
+// would call a clash fine and Publish would refuse it on the next screen (T2b's decision 2).
+WPCPM_Track_Store::$errors = array( 7 => array( array( 'code' => 'status_taken', 'column' => '', 'message' => 'Another track already claims that status.' ) ) );
+
+$flight = WPCPM_Track_Publish::preflight( 7 );
+
+ck( 'a definition the store refuses is refused here, in the store\'s own words',
+    array( codes( $flight['refusals'] ), $flight['refusals'][0]['message'], $flight['ready'] ),
+    array( array( 'status_taken' ), 'Another track already claims that status.', false ) );
+
+WPCPM_Track_Store::$errors = array();
+WPCPM_Airtable::$schema    = base( array( 'What you did' => 'formula' ), array( 'Marketing Track' ) );
+
+ck( 'a computed column is refused: a student\'s answer sent to one is thrown away',
+    codes( WPCPM_Track_Publish::preflight( 7 )['refusals'] ), array( 'column_computed' ) );
+
+WPCPM_Airtable::$schema = base( array( 'What you did' => 'multipleRecordLinks' ), array( 'Marketing Track' ) );
+
+ck( 'so is a link column that is not Main Contribution Team, which would reach into another table',
+    codes( WPCPM_Track_Publish::preflight( 7 )['refusals'] ), array( 'column_foreign_link' ) );
+
+WPCPM_Airtable::$schema = base( array( 'What you did' => 'singleLineText' ), array( 'Marketing Track' ) );
+
+ck( 'and a column of the wrong type, which would take the wrong shape of answer',
+    codes( WPCPM_Track_Publish::preflight( 7 )['refusals'] ), array( 'column_type_mismatch' ) );
+
+echo "\n=== The ceiling, which Airtable enforces part-way through ===\n";
+
+// Airtable refuses the request that passes 500 and leaves everything before it created, so the
+// preflight refuses the whole publish rather than half a track (the design's 7.1).
+WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText' ), array( 'Marketing Track' ), 498 );
+
+$flight = WPCPM_Track_Publish::preflight( 7 );
+
+ck( 'a table that would pass 500 is refused, and says both numbers',
+    array( codes( $flight['refusals'] ), $flight['fields'] ),
+    array( array( 'fields_ceiling' ), array( 'now' => 500, 'after' => 501 ) ) );
+
+WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText' ), array( 'Marketing Track' ), 448 );
+
+$flight = WPCPM_Track_Publish::preflight( 7 );
+
+ck( 'past 450 it is a warning, and publishing goes ahead',
+    array( codes( $flight['refusals'] ), codes( $flight['warnings'] ), $flight['ready'] ),
+    array( array(), array( 'fields_near_ceiling' ), true ) );
+
+echo "\n=== The Status choice, which no token can add ===\n";
+
+WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText' ), array( 'In Sensei' ) );
+
+$flight = WPCPM_Track_Publish::preflight( 7 );
+
+ck( 'a status that is not a choice yet is checklist item 3, not a refusal',
+    array( codes( $flight['refusals'] ), $flight['choices'] ),
+    array( array(), array( 'reports' => 'missing', 'students' => 'missing' ) ) );
+
+// A choice differing by case, spacing or an apostrophe is the shape that makes every sync miss
+// silently, and the site cannot tell a typo from a deliberate rename (decision 17).
+WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText' ), array( 'marketing  track' ) );
+
+$flight = WPCPM_Track_Publish::preflight( 7 );
+
+ck( 'one that differs only by case and spacing is a warning that names it',
+    array( codes( $flight['warnings'] ), $flight['choices']['reports'] ),
+    array( array( 'status_choice_near', 'status_choice_near' ), 'near' ) );
+
+echo "\n=== A built-in track ===\n";
+
+WPCPM_Track_Store::$sources = array( 7 => 'builtin' );
+WPCPM_Track_Store::$errors  = array( 7 => array( array( 'code' => 'builtin_changed', 'column' => '', 'message' => 'This track no longer matches its hand-written form.' ) ) );
+WPCPM_Airtable::$schema     = base( array( 'What you did' => 'multilineText' ), array( 'Marketing Track' ) );
+
+ck( 'a built-in track whose definition has drifted from its PHP is refused',
+    codes( WPCPM_Track_Publish::preflight( 7 )['refusals'] ), array( 'builtin_changed' ) );
+
+WPCPM_Track_Store::$errors = array();
+
+// Those four statuses were the program's before the Track Builder existed and are edited in
+// Settings, so publishing a seed never puts back one a manager took out (decision 13).
+ck( 'and publishing one never adds its status to the settings, which is said in a line',
+    WPCPM_Track_Publish::preflight( 7 )['adds_status'], false );
+
+WPCPM_Track_Store::$sources = array();
+
+ck( 'while a track of somebody\'s own does add its status, which is what makes its students sync',
+    WPCPM_Track_Publish::preflight( 7 )['adds_status'], true );
+
+echo "\n=== The Learn course, which is only ever a warning ===\n";
+
+$GLOBALS['head'] = array( 'https://learn.wordpress.org/course/marketing/' => array( 'response' => array( 'code' => 404 ) ) );
+
+$flight = WPCPM_Track_Publish::preflight( 7 );
+
+ck( 'a course that does not answer is a warning and nothing more',
+    array( codes( $flight['warnings'] ), $flight['ready'] ), array( array( 'course_unreachable' ), true ) );
+
+$GLOBALS['head'] = array();
+
+echo "\n=== When Airtable cannot be read at all ===\n";
+
+WPCPM_Airtable::$schema = new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 401)' );
+
+$flight = WPCPM_Track_Publish::preflight( 7 );
+
+ck( 'the preflight refuses rather than guessing, and passes Airtable\'s own words on',
+    array( codes( $flight['refusals'] ), $flight['refusals'][0]['message'], $flight['ready'] ),
+    array( array( 'schema_unreadable' ), 'Airtable request failed (HTTP 401)', false ) );
+
+printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );
+
+exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-publish.php`
Expected: `PHP Fatal error: Uncaught Error: Failed opening required '.../class-wpcpm-track-publish.php'`.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/tracks/class-wpcpm-track-publish.php b/includes/tracks/class-wpcpm-track-publish.php
new file mode 100644
index 0000000..79fb27b
--- /dev/null
+++ b/includes/tracks/class-wpcpm-track-publish.php
@@ -0,0 +1,333 @@
+<?php
+/**
+ * Publishing a track, and everything that has to be true first.
+ *
+ * @package WPCredits_Program_Manager
+ */
+
+defined( 'ABSPATH' ) || exit;
+
+/**
+ * What publishing would do, and what it would refuse.
+ *
+ * The definition's own rules belong to `WPCPM_Track_Store::check()`, the same call the editor
+ * makes, so the two can never give different answers (T2b's decision 2). What is added here is
+ * everything only Airtable can answer: whether a column exists and what type it is, how many
+ * columns the table would hold afterwards, and whether the track's status is a `Status` choice.
+ */
+final class WPCPM_Track_Publish {
+
+	/**
+	 * The most columns an Airtable table holds.
+	 *
+	 * Airtable refuses the request that passes this and keeps everything created before it, so a
+	 * run that would cross the line is refused whole rather than leaving half a track (7.1).
+	 *
+	 * @var int
+	 */
+	const FIELD_CEILING = 500;
+
+	/**
+	 * Where a table stops being comfortable and the screen says so.
+	 *
+	 * @var int
+	 */
+	const FIELD_WARNING = 450;
+
+	/**
+	 * The column both tables carry the track's status in.
+	 *
+	 * @var string
+	 */
+	const STATUS_COLUMN = 'Status';
+
+	/**
+	 * What publishing this track would do, without doing any of it.
+	 *
+	 * @param int $post_id The track.
+	 * @return array {
+	 *     @type array  $refusals    Findings that stop publishing, each `code`, `column`, `message`.
+	 *     @type array  $warnings    Findings that do not, in the same shape.
+	 *     @type array  $columns     `create` and `ready`, each a list of column names.
+	 *     @type array  $choices     `reports` and `students`, each `ok`, `near` or `missing`.
+	 *     @type array  $fields      `now` and `after`, how many columns the reports table holds.
+	 *     @type bool   $adds_status Whether publishing appends the status to `student_statuses`.
+	 *     @type bool   $ready       Whether nothing refuses it.
+	 * }
+	 */
+	public static function preflight( $post_id ) {
+		$post_id    = (int) $post_id;
+		$definition = WPCPM_Track_Store::get( $post_id );
+		$refusals   = array();
+		$warnings   = array();
+
+		if ( ! is_array( $definition ) ) {
+			return self::answer( array( self::finding( 'no_definition', '', __( 'This track has no definition to publish.', 'wpcredits-program-manager' ) ) ), array() );
+		}
+
+		// The store's own rules, asked once: a status or key another track holds, a column the
+		// syncs own, a built-in track that no longer matches its PHP. Its messages travel as they
+		// are, so the editor and this screen read the same.
+		foreach ( (array) WPCPM_Track_Store::check( $post_id, $definition ) as $error ) {
+			$refusals[] = self::finding(
+				isset( $error['code'] ) ? (string) $error['code'] : 'invalid',
+				isset( $error['column'] ) ? (string) $error['column'] : '',
+				isset( $error['message'] ) ? (string) $error['message'] : ''
+			);
+		}
+
+		$settings = WPCPM_Settings::get();
+		$client   = new WPCPM_Airtable();
+		$schema   = $client->fetch_schema();
+
+		if ( is_wp_error( $schema ) ) {
+			// Nothing below can be answered without the base, and guessing would be worse than
+			// saying so: every column would look like one to create.
+			$refusals[] = self::finding( 'schema_unreadable', '', $schema->get_error_message() );
+
+			return self::answer( $refusals, $warnings );
+		}
+
+		$reports  = isset( $settings['reports_table'] ) ? (string) $settings['reports_table'] : '';
+		$students = isset( $settings['students_table'] ) ? (string) $settings['students_table'] : '';
+		$columns  = isset( $schema[ $reports ]['columns'] ) ? (array) $schema[ $reports ]['columns'] : array();
+		$create   = array();
+		$ready    = array();
+
+		foreach ( (array) ( isset( $definition['questions'] ) ? $definition['questions'] : array() ) as $column => $question ) {
+			$column  = (string) $column;
+			$verdict = WPCPM_Track_Columns::judge( $column, (array) $question, $columns );
+
+			if ( 'create' === $verdict ) {
+				// A control that never creates a column and is not in the base is a question
+				// nothing can answer, so it is refused rather than queued for creation.
+				if ( null === WPCPM_Track_Columns::field( $column, (array) $question ) ) {
+					$refusals[] = self::finding( 'column_uncreatable', $column, __( 'This control cannot have a column created for it, and the base does not have one by this name.', 'wpcredits-program-manager' ) );
+					continue;
+				}
+
+				$create[] = $column;
+				continue;
+			}
+
+			if ( 'ok' === $verdict ) {
+				$ready[] = $column;
+				continue;
+			}
+
+			$refusals[] = self::finding( 'column_' . $verdict, $column, self::column_message( $verdict ) );
+		}
+
+		$now   = count( $columns );
+		$after = $now + count( $create );
+
+		if ( $after > self::FIELD_CEILING ) {
+			$refusals[] = self::finding(
+				'fields_ceiling',
+				'',
+				sprintf(
+					/* translators: 1: how many columns the table would hold, 2: the limit. */
+					__( 'Publishing would take this table to %1$d columns, past Airtable\'s limit of %2$d. Airtable refuses the request that crosses it and keeps every column made before, so nothing is created until the track has fewer questions or the table has fewer columns.', 'wpcredits-program-manager' ),
+					$after,
+					self::FIELD_CEILING
+				)
+			);
+		} elseif ( $after > self::FIELD_WARNING ) {
+			$warnings[] = self::finding(
+				'fields_near_ceiling',
+				'',
+				sprintf(
+					/* translators: 1: how many columns the table would hold, 2: the limit. */
+					__( 'Publishing would take this table to %1$d columns, close to Airtable\'s limit of %2$d.', 'wpcredits-program-manager' ),
+					$after,
+					self::FIELD_CEILING
+				)
+			);
+		}
+
+		$status  = isset( $definition['status'] ) ? (string) $definition['status'] : '';
+		$choices = array(
+			'reports'  => self::choice_state( $status, $schema, $reports ),
+			'students' => self::choice_state( $status, $schema, $students ),
+		);
+
+		foreach ( $choices as $where => $state ) {
+			if ( 'near' === $state ) {
+				$warnings[] = self::finding(
+					'status_choice_near',
+					'',
+					sprintf(
+						/* translators: 1: the status, 2: the table's name. */
+						__( 'The "%1$s" choice on %2$s is nearly this track\'s status but not exactly: the syncs match it letter for letter, so a student on this track would be missed. Check the choice in Airtable.', 'wpcredits-program-manager' ),
+						$status,
+						'reports' === $where ? __( 'Students Reports', 'wpcredits-program-manager' ) : __( 'Students', 'wpcredits-program-manager' )
+					)
+				);
+			}
+		}
+
+		if ( '' !== (string) ( isset( $definition['course_url'] ) ? $definition['course_url'] : '' ) && ! self::course_answers( (string) $definition['course_url'] ) ) {
+			$warnings[] = self::finding(
+				'course_unreachable',
+				'',
+				__( 'The Learn course did not answer. The link still publishes: it is shown to students, and a course that is private or moved is worth checking.', 'wpcredits-program-manager' )
+			);
+		}
+
+		return self::answer(
+			$refusals,
+			$warnings,
+			array(
+				'columns'     => array(
+					'create' => $create,
+					'ready'  => $ready,
+				),
+				'choices'     => $choices,
+				'fields'      => array(
+					'now'   => $now,
+					'after' => $after,
+				),
+				'adds_status' => 'builtin' !== WPCPM_Track_Store::source( $post_id ),
+			)
+		);
+	}
+
+	/**
+	 * Whether a status is one of a table's `Status` choices, nearly one, or absent.
+	 *
+	 * @param string $status The track's status.
+	 * @param array  $schema The base schema.
+	 * @param string $table  Table ID.
+	 * @return string `ok`, `near` or `missing`.
+	 */
+	private static function choice_state( $status, array $schema, $table ) {
+		$options = isset( $schema[ $table ]['columns'][ self::STATUS_COLUMN ]['options']['choices'] )
+			? (array) $schema[ $table ]['columns'][ self::STATUS_COLUMN ]['options']['choices']
+			: array();
+
+		$near = false;
+
+		foreach ( $options as $choice ) {
+			$name = isset( $choice['name'] ) ? (string) $choice['name'] : '';
+
+			if ( $name === $status ) {
+				return 'ok';
+			}
+
+			if ( self::loosen( $name ) === self::loosen( $status ) ) {
+				$near = true;
+			}
+		}
+
+		return $near ? 'near' : 'missing';
+	}
+
+	/**
+	 * A status with the differences that make a sync miss silently taken out of it.
+	 *
+	 * Case, runs of space, and the two apostrophes a keyboard produces: the syncs match a status
+	 * letter for letter, so a choice differing by any of these matches nobody (7.1).
+	 *
+	 * @param string $value The status or choice.
+	 * @return string
+	 */
+	private static function loosen( $value ) {
+		$value = str_replace( array( "\xe2\x80\x99", '`' ), "'", (string) $value );
+		$value = preg_replace( '/\s+/u', ' ', $value );
+
+		return trim( strtolower( (string) $value ) );
+	}
+
+	/**
+	 * Whether the Learn course answers.
+	 *
+	 * A warning at worst: the link is shown to students and publishing never depends on it, so a
+	 * slow or private course must not stop a track going live (7.1).
+	 *
+	 * @param string $url The course URL.
+	 * @return bool
+	 */
+	private static function course_answers( $url ) {
+		$response = wp_remote_head(
+			$url,
+			array(
+				'timeout'     => 5,
+				'redirection' => 3,
+			)
+		);
+
+		if ( is_wp_error( $response ) ) {
+			return false;
+		}
+
+		$code = (int) wp_remote_retrieve_response_code( $response );
+
+		return $code >= 200 && $code < 400;
+	}
+
+	/**
+	 * Why a column already in the base cannot be used.
+	 *
+	 * @param string $verdict What `WPCPM_Track_Columns::judge()` said.
+	 * @return string
+	 */
+	private static function column_message( $verdict ) {
+		if ( 'computed' === $verdict ) {
+			return __( 'Airtable works this column out for itself, so nothing can be written to it: a student\'s answer would be thrown away.', 'wpcredits-program-manager' );
+		}
+
+		if ( 'foreign_link' === $verdict ) {
+			return __( 'This column links to another table, and a link carries a reverse column into it. Main Contribution Team is the one link a question may use.', 'wpcredits-program-manager' );
+		}
+
+		return __( 'The column in the base is a different type from the one this question needs, so the answer would arrive in the wrong shape.', 'wpcredits-program-manager' );
+	}
+
+	/**
+	 * One finding.
+	 *
+	 * @param string $code    Its code.
+	 * @param string $column  The column it is about, or an empty string.
+	 * @param string $message What a person reads.
+	 * @return array
+	 */
+	private static function finding( $code, $column, $message ) {
+		return array(
+			'code'    => (string) $code,
+			'column'  => (string) $column,
+			'message' => (string) $message,
+		);
+	}
+
+	/**
+	 * The preflight's answer, with the parts a caller always gets.
+	 *
+	 * @param array $refusals Findings that stop publishing.
+	 * @param array $warnings Findings that do not.
+	 * @param array $rest     What the run would do, when it got far enough to work it out.
+	 * @return array
+	 */
+	private static function answer( array $refusals, array $warnings, array $rest = array() ) {
+		return array_merge(
+			array(
+				'refusals'    => $refusals,
+				'warnings'    => $warnings,
+				'columns'     => array(
+					'create' => array(),
+					'ready'  => array(),
+				),
+				'choices'     => array(
+					'reports'  => 'missing',
+					'students' => 'missing',
+				),
+				'fields'      => array(
+					'now'   => 0,
+					'after' => 0,
+				),
+				'adds_status' => true,
+				'ready'       => array() === $refusals,
+			),
+			$rest
+		);
+	}
+}
diff --git a/uninstall.php b/uninstall.php
index c7a90d1..030b463 100644
--- a/uninstall.php
+++ b/uninstall.php
@@ -53,6 +53,7 @@ require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-form-guard.php'
 require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-form-stash.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-track-palette.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-track-columns.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-track-publish.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-track-definition.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-tracks.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-track-store.php';
diff --git a/wpcredits-program-manager.php b/wpcredits-program-manager.php
index d8fc1fe..ac89275 100644
--- a/wpcredits-program-manager.php
+++ b/wpcredits-program-manager.php
@@ -45,6 +45,7 @@ require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-contribution-teams.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-palette.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-columns.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-publish.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-definition.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-tracks.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-store.php';
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-publish.php`
Expected: `ALL PASS (18 checks)`

- [ ] **Step 5: Run everything**

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
bash bin/check-standards.sh 2>&1 | tail -1
```

Expected: silent, clean, and `85 warnings, no errors.`

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Track Builder T2c: the preflight"
```

---

### Task 4: The publish run, its lock and its resume

**Files:**
- Modify: `includes/tracks/class-wpcpm-track-publish.php`
- Modify: `includes/tracks/class-wpcpm-track-store.php`
- Test: `bin/test-track-publish.php`
- Test: `bin/test-track-store.php`

**Interfaces:**
- Consumes: `preflight()` from Task 3, `create_field()` from Task 2, `WPCPM_Track_Columns::field()` and `::judge()` from Task 1.
- Produces: `WPCPM_Track_Publish::run( int $post_id, int $user_id = 0 ): array|WP_Error`, answering `array( 'created' => string[], 'published' => true )`; the constants `OPT_LOCK`, `LOCK_VALUE` and `META_RUN`; and `WPCPM_Track_Store::publish()` no longer adding a status for a built-in track.

Airtable creates one column per request, so a run can stop halfway. Recording each landing on the post is what makes the next press safe. The store change is decision 13: publishing a seed must not put back a status a manager took out, and T2a's `publish()` added one unconditionally.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-publish.php b/bin/test-track-publish.php
index 1727bab..60261a2 100644
--- a/bin/test-track-publish.php
+++ b/bin/test-track-publish.php
@@ -25,9 +25,11 @@ function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? (int)
 class WP_Error {
 	private $code;
 	private $message;
-	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
+	private $data;
+	public function __construct( $code = '', $message = '', $data = array() ) { $this->code = $code; $this->message = $message; $this->data = $data; }
 	public function get_error_code() { return $this->code; }
 	public function get_error_message() { return $this->message; }
+	public function get_error_data() { return $this->data; }
 }
 
 /** The store, stood in: the definition, what `check()` says of it, and where a built-in track stands. */
@@ -36,9 +38,24 @@ class WPCPM_Track_Store {
 	public static $errors      = array();
 	public static $sources     = array();
 
+	public static $published = array();
+	public static $logged    = array();
+	public static $refuse    = null;
+
 	public static function get( $post_id ) { return self::$definitions[ $post_id ] ?? null; }
 	public static function check( $post_id, array $definition ) { return self::$errors[ $post_id ] ?? array(); }
 	public static function source( $post_id ) { return self::$sources[ $post_id ] ?? 'definition'; }
+
+	public static function publish( $post_id, $user_id = 0 ) {
+		if ( self::$refuse instanceof WP_Error ) { return self::$refuse; }
+		self::$published[] = array( (int) $post_id, (int) $user_id );
+		return (int) $post_id;
+	}
+
+	public static function log( $post_id, $did, $user_id = 0, array $detail = array() ) {
+		self::$logged[] = array( (int) $post_id, (string) $did, (int) $user_id, $detail );
+		return true;
+	}
 }
 
 /** The settings, stood in: the two tables the `Status` choice has to exist on. */
@@ -49,18 +66,52 @@ class WPCPM_Settings {
 	public static function has_schema_token() { return ! empty( self::$values['schema_token'] ); }
 }
 
-/** The client, stood in: one canned schema, and a note of whether it was asked. */
+/** The client, stood in: one canned schema, a queue of create answers, and what it was asked. */
 class WPCPM_Airtable {
-	public static $schema = array();
-	public static $reads  = 0;
+	public static $schema  = array();
+	public static $reads   = 0;
+	public static $answers = array();
+	public static $created = array();
+
+	/**
+	 * The canned schema. `$later` is what a second read sees, which is how the race the resume
+	 * exists for is modelled: the column was not there when the preflight looked, and was by the
+	 * time creation was refused.
+	 */
+	public static $later = null;
 
 	public function fetch_schema() {
 		++self::$reads;
 
-		return self::$schema instanceof WP_Error ? self::$schema : self::$schema;
+		if ( self::$reads > 1 && null !== self::$later ) {
+			return self::$later;
+		}
+
+		return self::$schema;
+	}
+
+	public function create_field( $table, array $field ) {
+		self::$created[] = array( $table, $field['name'] );
+		$answer          = array_shift( self::$answers );
+
+		return null === $answer ? $field : $answer;
 	}
 }
 
+$GLOBALS['opts'] = array();
+$GLOBALS['meta'] = array();
+
+function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
+function add_option( $k, $v, $deprecated = '', $autoload = 'yes' ) { if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; } $GLOBALS['opts'][ $k ] = $v; return true; }
+function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
+function get_post_meta( $post_id, $key, $single = false ) { return $GLOBALS['meta'][ $post_id ][ $key ] ?? ( $single ? '' : array() ); }
+function update_post_meta( $post_id, $key, $value ) { $GLOBALS['meta'][ $post_id ][ $key ] = $value; return true; }
+function delete_post_meta( $post_id, $key ) { unset( $GLOBALS['meta'][ $post_id ][ $key ] ); return true; }
+function wp_slash( $v ) { return $v; }
+function wp_unslash( $v ) { return $v; }
+function wp_json_encode( $v ) { return json_encode( $v ); }
+function get_current_user_id() { return 5; }
+
 require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-columns.php';
 require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-publish.php';
 
@@ -262,6 +313,143 @@ ck( 'the preflight refuses rather than guessing, and passes Airtable\'s own word
     array( codes( $flight['refusals'] ), $flight['refusals'][0]['message'], $flight['ready'] ),
     array( array( 'schema_unreadable' ), 'Airtable request failed (HTTP 401)', false ) );
 
+echo "\n=== The run: columns first, then the track goes live ===\n";
+
+/** A scenario with nothing recorded, nothing locked and nothing sent. */
+function fresh_run() {
+	$GLOBALS['opts']              = array();
+	$GLOBALS['meta']              = array();
+	WPCPM_Airtable::$answers      = array();
+	WPCPM_Airtable::$later        = null;
+	WPCPM_Airtable::$reads        = 0;
+	WPCPM_Airtable::$created      = array();
+	WPCPM_Track_Store::$published = array();
+	WPCPM_Track_Store::$logged    = array();
+	WPCPM_Track_Store::$refuse    = null;
+	WPCPM_Track_Store::$errors    = array();
+	WPCPM_Track_Store::$sources   = array();
+	WPCPM_Track_Store::$definitions = array( 7 => track() );
+	WPCPM_Airtable::$schema       = base( array( 'What you did' => 'multilineText' ), array( 'Marketing Track' ) );
+	WPCPM_Settings::$values       = array( 'reports_table' => 'tblReports', 'students_table' => 'tblStudents', 'schema_token' => 'pat-schema' );
+}
+
+fresh_run();
+$ran = WPCPM_Track_Publish::run( 7, 5 );
+
+ck( 'the one missing column is created, on the reports table',
+    WPCPM_Airtable::$created, array( array( 'tblReports', 'Brand new' ) ) );
+
+ck( 'the track is then published, by the person who pressed the button',
+    WPCPM_Track_Store::$published, array( array( 7, 5 ) ) );
+
+ck( 'the run says what it created', $ran, array( 'created' => array( 'Brand new' ), 'published' => true ) );
+
+ck( 'the log carries the columns, so the record survives the screen',
+    WPCPM_Track_Store::$logged, array( array( 7, 'columns', 5, array( 'columns' => array( 'Brand new' ) ) ) ) );
+
+ck( 'and the lock is let go', array_key_exists( WPCPM_Track_Publish::OPT_LOCK, $GLOBALS['opts'] ), false );
+
+echo "\n=== One run at a time ===\n";
+
+fresh_run();
+$GLOBALS['opts'][ WPCPM_Track_Publish::OPT_LOCK ] = WPCPM_Track_Publish::LOCK_VALUE;
+$busy = WPCPM_Track_Publish::run( 7, 5 );
+
+ck( 'a second run while one is going refuses, and creates nothing',
+    array( $busy->get_error_code(), WPCPM_Airtable::$created ), array( 'wpcpm_track_publish_running', array() ) );
+
+// The claim is `add_option()` with a value that never changes: T2a's final review found that a
+// claim whose value changes is not atomic.
+ck( 'the lock claims one unchanging value', WPCPM_Track_Publish::LOCK_VALUE, 'running' );
+
+echo "\n=== A refused preflight stops before anything is created ===\n";
+
+fresh_run();
+WPCPM_Track_Store::$errors = array( 7 => array( array( 'code' => 'status_taken', 'column' => '', 'message' => 'Another track already claims that status.' ) ) );
+$stopped = WPCPM_Track_Publish::run( 7, 5 );
+
+ck( 'the refusal comes back with the findings, and Airtable is never asked to create anything',
+    array( $stopped->get_error_code(), $stopped->get_error_message(), WPCPM_Airtable::$created, WPCPM_Track_Store::$published ),
+    array( 'wpcpm_track_preflight', 'Another track already claims that status.', array(), array() ) );
+
+echo "\n=== A failed step stops, and the next press resumes ===\n";
+
+fresh_run();
+WPCPM_Track_Store::$definitions = array(
+	7 => track(
+		array(
+			'One'   => array( 'label' => 'One', 'type' => 'text', 'group' => 'project' ),
+			'Two'   => array( 'label' => 'Two', 'type' => 'text', 'group' => 'project' ),
+			'Three' => array( 'label' => 'Three', 'type' => 'text', 'group' => 'project' ),
+		)
+	),
+);
+WPCPM_Airtable::$answers = array( null, new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 500)' ) );
+$failed = WPCPM_Track_Publish::run( 7, 5 );
+
+ck( 'it stops at the step that failed, in Airtable\'s own words, with the track still a draft',
+    array( $failed->get_error_code(), $failed->get_error_message(), WPCPM_Track_Store::$published ),
+    array( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 500)', array() ) );
+
+ck( 'the column that did land is recorded on the post',
+    get_post_meta( 7, WPCPM_Track_Publish::META_RUN, true ), array( 'columns' => array( 'One' ) ) );
+
+ck( 'and the lock is let go, so the next press is not refused',
+    array_key_exists( WPCPM_Track_Publish::OPT_LOCK, $GLOBALS['opts'] ), false );
+
+WPCPM_Airtable::$created = array();
+WPCPM_Airtable::$answers = array();
+$resumed = WPCPM_Track_Publish::run( 7, 5 );
+
+ck( 'the next press starts at the first step not recorded',
+    WPCPM_Airtable::$created, array( array( 'tblReports', 'Two' ), array( 'tblReports', 'Three' ) ) );
+
+ck( 'it says everything the two runs created together',
+    $resumed, array( 'created' => array( 'One', 'Two', 'Three' ), 'published' => true ) );
+
+ck( 'and the record is cleared once the track is live',
+    get_post_meta( 7, WPCPM_Track_Publish::META_RUN, true ), '' );
+
+echo "\n=== A name already taken is read again, not treated as a failure ===\n";
+
+fresh_run();
+WPCPM_Airtable::$answers = array( new WP_Error( 'wpcpm_airtable_field_exists', 'Airtable already has a column named "Brand new" on this table.' ) );
+// Somebody made it by hand between the preflight and the run, which is the documented way to work
+// without a schema token, so the second read finds it with the right type (7.2 step 1).
+WPCPM_Airtable::$later   = base( array( 'What you did' => 'multilineText', 'Brand new' => 'singleLineText' ), array( 'Marketing Track' ) );
+$by_hand = WPCPM_Track_Publish::run( 7, 5 );
+
+ck( 'a column somebody made by hand counts as landed and the run carries on',
+    array( $by_hand, WPCPM_Track_Store::$published ), array( array( 'created' => array( 'Brand new' ), 'published' => true ), array( array( 7, 5 ) ) ) );
+
+fresh_run();
+WPCPM_Airtable::$answers = array( new WP_Error( 'wpcpm_airtable_field_exists', 'Airtable already has a column named "Brand new" on this table.' ) );
+// The same name on a column of the wrong type is a different thing: writing to it would put the
+// answer in the wrong shape, so the run stops and says so.
+WPCPM_Airtable::$later   = base( array( 'What you did' => 'multilineText', 'Brand new' => 'checkbox' ), array( 'Marketing Track' ) );
+$wrong_type = WPCPM_Track_Publish::run( 7, 5 );
+
+ck( 'but one of the wrong type stops the run',
+    array( $wrong_type->get_error_code(), WPCPM_Track_Store::$published ), array( 'wpcpm_track_column_conflict', array() ) );
+
+echo "\n=== With no schema token the columns are a list, not a failure ===\n";
+
+fresh_run();
+WPCPM_Settings::$values = array( 'reports_table' => 'tblReports', 'students_table' => 'tblStudents' );
+$no_token = WPCPM_Track_Publish::run( 7, 5 );
+
+ck( 'the run refuses before creating anything and names the columns to make by hand',
+    array( $no_token->get_error_code(), $no_token->get_error_data(), WPCPM_Airtable::$created ),
+    array( 'wpcpm_track_columns_by_hand', array( 'columns' => array( 'Brand new' ) ), array() ) );
+
+fresh_run();
+WPCPM_Settings::$values         = array( 'reports_table' => 'tblReports', 'students_table' => 'tblStudents' );
+WPCPM_Airtable::$schema         = base( array( 'What you did' => 'multilineText', 'Brand new' => 'singleLineText' ), array( 'Marketing Track' ) );
+$nothing_to_make = WPCPM_Track_Publish::run( 7, 5 );
+
+ck( 'and with every column already there it publishes without a schema token at all',
+    array( $nothing_to_make, WPCPM_Track_Store::$published ), array( array( 'created' => array(), 'published' => true ), array( array( 7, 5 ) ) ) );
+
 printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );
 
 exit( $fails ? 1 : 0 );
diff --git a/bin/test-track-store.php b/bin/test-track-store.php
index 277888f..40f94cd 100644
--- a/bin/test-track-store.php
+++ b/bin/test-track-store.php
@@ -694,6 +694,30 @@ ck( 'and sweeps any form option the index lost track of', false !== strpos( $uni
 $main = (string) file_get_contents( __DIR__ . '/../wpcredits-program-manager.php' );
 ck( 'the plugin boots the store and the runtime', array( false !== strpos( $main, 'WPCPM_Track_Store::init();' ), false !== strpos( $main, 'WPCPM_Tracks::init();' ) ), array( true, true ) );
 
+echo "\n=== Publishing and the settings (the design's decision 13) ===\n";
+
+// Those four statuses were the program's before the Track Builder existed and are edited in
+// Settings, so a seed published again must not ask for one to be put back.
+WPCPM_Settings::$added = array();
+
+$builtin_post = WPCPM_Track_Store::create( WPCPM_Track_Store::seeds()['dev'] );
+update_post_meta( $builtin_post, WPCPM_Track_Store::META_SOURCE, 'builtin' );
+$builtin_done = WPCPM_Track_Store::publish( $builtin_post );
+
+ck( 'a built-in track publishes', is_wp_error( $builtin_done ) ? $builtin_done->get_error_message() : true, true );
+
+ck( 'and asks for no status to be added, so one a manager took out stays out',
+    WPCPM_Settings::$added, array() );
+
+$mine_post = WPCPM_Track_Store::create( track( 'Growth Track', 'growth' ) );
+$mine_done = WPCPM_Track_Store::publish( $mine_post );
+
+ck( 'a track of somebody\'s own publishes too', is_wp_error( $mine_done ) ? $mine_done->get_error_message() : true, true );
+
+ck( 'and its status is the one added, which is what makes its students sync',
+    WPCPM_Settings::$added, array( 'Growth Track' ) );
+
+
 printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
 
 exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-publish.php`
Expected: `PHP Fatal error: Uncaught Error: Call to undefined method WPCPM_Track_Publish::run()`.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/tracks/class-wpcpm-track-publish.php b/includes/tracks/class-wpcpm-track-publish.php
index 79fb27b..e4ca2e4 100644
--- a/includes/tracks/class-wpcpm-track-publish.php
+++ b/includes/tracks/class-wpcpm-track-publish.php
@@ -41,6 +41,30 @@ final class WPCPM_Track_Publish {
 	 */
 	const STATUS_COLUMN = 'Status';
 
+	/**
+	 * The option one run claims while it is going, so two cannot create the same column twice.
+	 *
+	 * @var string
+	 */
+	const OPT_LOCK = 'wpcpm_track_publish_lock';
+
+	/**
+	 * The value the claim writes, which never changes.
+	 *
+	 * `add_option()` is the atomic part: it returns false when the option is already there. A
+	 * claim whose value changes is not atomic, which T2a's final review found the hard way.
+	 *
+	 * @var string
+	 */
+	const LOCK_VALUE = 'running';
+
+	/**
+	 * Where a run records the steps that landed, so the next press resumes rather than repeats.
+	 *
+	 * @var string
+	 */
+	const META_RUN = '_wpcpm_track_run';
+
 	/**
 	 * What publishing this track would do, without doing any of it.
 	 *
@@ -192,6 +216,144 @@ final class WPCPM_Track_Publish {
 		);
 	}
 
+	/**
+	 * Publish a track: create the columns it needs, then put it live.
+	 *
+	 * One run at a time. Each column that lands is recorded on the post, so a run stopped by
+	 * Airtable can be pressed again and starts at the first step not recorded (decision 18). A
+	 * name already taken is read again rather than treated as a failure: somebody making the
+	 * column by hand is the documented way to work without a schema token (7.2 step 1).
+	 *
+	 * @param int $post_id The track.
+	 * @param int $user_id Who pressed Publish, for the log; 0 for the current user.
+	 * @return array|WP_Error `created` and `published`, or why nothing more was done.
+	 */
+	public static function run( $post_id, $user_id = 0 ) {
+		$post_id = (int) $post_id;
+		$flight  = self::preflight( $post_id );
+
+		if ( ! $flight['ready'] ) {
+			$first = isset( $flight['refusals'][0]['message'] ) ? (string) $flight['refusals'][0]['message'] : '';
+
+			return new WP_Error( 'wpcpm_track_preflight', $first, array( 'refusals' => $flight['refusals'] ) );
+		}
+
+		$landed  = self::landed( $post_id );
+		$pending = array_values( array_diff( $flight['columns']['create'], $landed ) );
+
+		// Without the token the site cannot make a column, so it says which ones to make instead
+		// of failing halfway. Publishing waits for the preflight to find them (7.2).
+		if ( array() !== $pending && ! WPCPM_Settings::has_schema_token() ) {
+			return new WP_Error(
+				'wpcpm_track_columns_by_hand',
+				__( 'This track needs columns the base does not have, and no schema token is configured. Create them in Airtable from the list on this screen, then publish again.', 'wpcredits-program-manager' ),
+				array( 'columns' => $pending )
+			);
+		}
+
+		if ( ! add_option( self::OPT_LOCK, self::LOCK_VALUE, '', false ) ) {
+			return new WP_Error( 'wpcpm_track_publish_running', __( 'Another track is being published right now. Wait for that to finish and try again.', 'wpcredits-program-manager' ) );
+		}
+
+		$settings   = WPCPM_Settings::get();
+		$table      = isset( $settings['reports_table'] ) ? (string) $settings['reports_table'] : '';
+		$client     = new WPCPM_Airtable();
+		$definition = WPCPM_Track_Store::get( $post_id );
+		$questions  = is_array( $definition ) && isset( $definition['questions'] ) ? (array) $definition['questions'] : array();
+
+		foreach ( $pending as $column ) {
+			$field = WPCPM_Track_Columns::field( $column, (array) ( $questions[ $column ] ?? array() ) );
+
+			if ( null === $field ) {
+				continue;
+			}
+
+			$made = $client->create_field( $table, $field );
+
+			if ( is_wp_error( $made ) ) {
+				if ( 'wpcpm_airtable_field_exists' !== $made->get_error_code() ) {
+					self::record( $post_id, $landed );
+					delete_option( self::OPT_LOCK );
+
+					return $made;
+				}
+
+				// Taken. Read the base again: the same name on a column of the right type is one
+				// somebody made by hand, and it counts as landed. Of the wrong type it is a
+				// conflict, because writing to it would put the answer in the wrong shape.
+				$again   = $client->fetch_schema();
+				$there   = ( ! is_wp_error( $again ) && isset( $again[ $table ]['columns'] ) ) ? (array) $again[ $table ]['columns'] : array();
+				$verdict = WPCPM_Track_Columns::judge( $column, (array) ( $questions[ $column ] ?? array() ), $there );
+
+				if ( 'ok' !== $verdict ) {
+					self::record( $post_id, $landed );
+					delete_option( self::OPT_LOCK );
+
+					return new WP_Error(
+						'wpcpm_track_column_conflict',
+						sprintf(
+							/* translators: %s: column name. */
+							__( 'Airtable already has a column named "%s", and it is not the type this question needs. Rename one of them in Airtable, or change the question.', 'wpcredits-program-manager' ),
+							$column
+						),
+						array( 'column' => $column )
+					);
+				}
+			}
+
+			$landed[] = $column;
+		}
+
+		self::record( $post_id, $landed );
+
+		$published = WPCPM_Track_Store::publish( $post_id, $user_id );
+
+		if ( is_wp_error( $published ) ) {
+			delete_option( self::OPT_LOCK );
+
+			return $published;
+		}
+
+		if ( array() !== $landed ) {
+			WPCPM_Track_Store::log( $post_id, 'columns', $user_id, array( 'columns' => $landed ) );
+		}
+
+		delete_post_meta( $post_id, self::META_RUN );
+		delete_option( self::OPT_LOCK );
+
+		return array(
+			'created'   => $landed,
+			'published' => true,
+		);
+	}
+
+	/**
+	 * The columns an earlier run of this track already created.
+	 *
+	 * @param int $post_id The track.
+	 * @return string[]
+	 */
+	private static function landed( $post_id ) {
+		$run = get_post_meta( (int) $post_id, self::META_RUN, true );
+
+		return ( is_array( $run ) && isset( $run['columns'] ) ) ? array_values( (array) $run['columns'] ) : array();
+	}
+
+	/**
+	 * Record what has landed, so the next press resumes here.
+	 *
+	 * @param int   $post_id The track.
+	 * @param array $columns The columns created so far.
+	 * @return void
+	 */
+	private static function record( $post_id, array $columns ) {
+		if ( array() === $columns ) {
+			return;
+		}
+
+		update_post_meta( (int) $post_id, self::META_RUN, array( 'columns' => array_values( $columns ) ) );
+	}
+
 	/**
 	 * Whether a status is one of a table's `Status` choices, nearly one, or absent.
 	 *
diff --git a/includes/tracks/class-wpcpm-track-store.php b/includes/tracks/class-wpcpm-track-store.php
index 417f014..b4b6456 100644
--- a/includes/tracks/class-wpcpm-track-store.php
+++ b/includes/tracks/class-wpcpm-track-store.php
@@ -429,7 +429,15 @@ final class WPCPM_Track_Store {
 		}
 
 		self::compile();
-		WPCPM_Settings::add_student_status( (string) $definition['status'] );
+
+		// Those four statuses were the program's before the Track Builder existed and are edited
+		// in Settings, so publishing a seed never puts back one a manager took out (the design's
+		// decision 13). A track of somebody's own still gets its status added, which is what
+		// makes its students sync.
+		if ( 'builtin' !== self::source( $post_id ) ) {
+			WPCPM_Settings::add_student_status( (string) $definition['status'] );
+		}
+
 		self::log( $post_id, 'publish', $user_id );
 
 		return $post_id;
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-publish.php`
Expected: `ALL PASS (36 checks)` from `bin/test-track-publish.php` and `ALL PASS (133 checks)` from `bin/test-track-store.php`

- [ ] **Step 5: Run everything**

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
bash bin/check-standards.sh 2>&1 | tail -1
```

Expected: silent, clean, and `85 warnings, no errors.`

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Track Builder T2c: the publish run, and a built-in track that adds no status"
```

---

### Task 5: The checklist, its ticks, and verify

**Files:**
- Modify: `includes/tracks/class-wpcpm-track-publish.php`
- Test: `bin/test-track-publish.php`

**Interfaces:**
- Consumes: `WPCPM_Track_Store::compile()` and `::log()`, and `WPCPM_Track_Store::META_AUTOMATION`, which T2a left read by `compile()` and written by nothing.
- Produces: `WPCPM_Track_Publish::checklist( int $post_id ): array` keyed `automation`, `welcome`, `choices`, each `label`, `detail`, `ticked`, `by`, `at`; `::tick()` and `::untick()` returning `true|WP_Error`; `::verify( int $post_id ): array|WP_Error` with `columns` (`missing`, `wrong`) and `choices`; and the constants `META_CHECKLIST` and `CHECKLIST`.

The site cannot see an Airtable automation either way, so these three are a person's word, recorded with their name. Ticking item 1 is the one that changes what the site does: it writes the flag the compiled row carries, which is the list the institution gate reads, so it has to recompile.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-publish.php b/bin/test-track-publish.php
index 60261a2..48d22ed 100644
--- a/bin/test-track-publish.php
+++ b/bin/test-track-publish.php
@@ -41,6 +41,11 @@ class WPCPM_Track_Store {
 	public static $published = array();
 	public static $logged    = array();
 	public static $refuse    = null;
+	public static $compiles  = 0;
+
+	const META_AUTOMATION = '_wpcpm_track_automation';
+
+	public static function compile() { ++self::$compiles; return array(); }
 
 	public static function get( $post_id ) { return self::$definitions[ $post_id ] ?? null; }
 	public static function check( $post_id, array $definition ) { return self::$errors[ $post_id ] ?? array(); }
@@ -111,6 +116,7 @@ function wp_slash( $v ) { return $v; }
 function wp_unslash( $v ) { return $v; }
 function wp_json_encode( $v ) { return json_encode( $v ); }
 function get_current_user_id() { return 5; }
+function time_now() { return $GLOBALS['now'] ?? 1788000000; }
 
 require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-columns.php';
 require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-publish.php';
@@ -450,6 +456,91 @@ $nothing_to_make = WPCPM_Track_Publish::run( 7, 5 );
 ck( 'and with every column already there it publishes without a schema token at all',
     array( $nothing_to_make, WPCPM_Track_Store::$published ), array( array( 'created' => array(), 'published' => true ), array( array( 7, 5 ) ) ) );
 
+echo "\n=== The checklist: what no token can do ===\n";
+
+fresh_run();
+
+$list = WPCPM_Track_Publish::checklist( 7 );
+
+ck( 'it has the three items, in the order they have to happen',
+    array_keys( $list ), array( 'automation', 'welcome', 'choices' ) );
+
+ck( 'none of them is ticked on a track nobody has touched',
+    array_column( $list, 'ticked' ), array( false, false, false ) );
+
+ck( 'and each carries the exact value somebody needs to type into Airtable',
+    array( false !== strpos( $list['automation']['detail'], 'Marketing Track' ), false !== strpos( $list['choices']['detail'], 'Marketing Track' ) ),
+    array( true, true ) );
+
+WPCPM_Track_Publish::tick( 7, 'welcome', 5 );
+$list = WPCPM_Track_Publish::checklist( 7 );
+
+ck( 'ticking one records who did it, and stamps it with the time it happened',
+    array( $list['welcome']['ticked'], $list['welcome']['by'], abs( $list['welcome']['at'] - time() ) < 5 ), array( true, 5, true ) );
+
+ck( 'and it is logged, because the base cannot be asked whether it happened',
+    WPCPM_Track_Store::$logged, array( array( 7, 'tick-welcome', 5, array() ) ) );
+
+ck( 'ticking the welcome email does not recompile: it changes nothing the site reads',
+    WPCPM_Track_Store::$compiles, 0 );
+
+// Ticking item 1 is what puts the status into `confirmed_automation_statuses()`, which is the list
+// the institution import and institution create read (the design's decision 10). The compiled row
+// carries the flag, so the tick has to recompile or the gate stays shut.
+WPCPM_Track_Publish::tick( 7, 'automation', 5 );
+
+ck( 'ticking the reports automation writes the flag the compiled row carries',
+    get_post_meta( 7, WPCPM_Track_Store::META_AUTOMATION, true ), '1' );
+
+ck( 'and recompiles, or the institution gate would stay shut until something unrelated published',
+    WPCPM_Track_Store::$compiles, 1 );
+
+WPCPM_Track_Publish::untick( 7, 'automation', 5 );
+
+ck( 'unticking it takes the flag away again and recompiles',
+    array( get_post_meta( 7, WPCPM_Track_Store::META_AUTOMATION, true ), WPCPM_Track_Store::$compiles ), array( '', 2 ) );
+
+ck( 'an item nobody has heard of is refused rather than recorded',
+    WPCPM_Track_Publish::tick( 7, 'something', 5 )->get_error_code(), 'wpcpm_track_checklist_item' );
+
+echo "\n=== Verify, which reads and changes nothing ===\n";
+
+fresh_run();
+WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText', 'Brand new' => 'singleLineText' ), array( 'Marketing Track' ) );
+$seen = WPCPM_Track_Publish::verify( 7 );
+
+ck( 'every column the definition names is still there, of the type it needs',
+    array( $seen['columns']['missing'], $seen['columns']['wrong'] ), array( array(), array() ) );
+
+ck( 'the status is a choice on both tables',
+    $seen['choices'], array( 'reports' => 'ok', 'students' => 'ok' ) );
+
+ck( 'and it changed nothing: no column created, nothing published',
+    array( WPCPM_Airtable::$created, WPCPM_Track_Store::$published ), array( array(), array() ) );
+
+// A column renamed in the base otherwise surfaces as a silently empty answer: the form keeps
+// writing to a name nothing reads (7.4).
+fresh_run();
+WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText' ), array( 'Marketing Track' ) );
+$seen = WPCPM_Track_Publish::verify( 7 );
+
+ck( 'a column that has gone is named, which is what a rename in the base looks like from here',
+    $seen['columns']['missing'], array( 'Brand new' ) );
+
+fresh_run();
+WPCPM_Airtable::$schema = base( array( 'What you did' => 'multilineText', 'Brand new' => 'checkbox' ), array( 'Marketing Track' ) );
+$seen = WPCPM_Track_Publish::verify( 7 );
+
+ck( 'and one whose type has changed under the track is named too',
+    $seen['columns']['wrong'], array( 'Brand new' ) );
+
+fresh_run();
+WPCPM_Airtable::$schema = new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 401)' );
+$seen = WPCPM_Track_Publish::verify( 7 );
+
+ck( 'and when the base cannot be read it says so rather than reporting everything missing',
+    array( is_wp_error( $seen ), $seen->get_error_message() ), array( true, 'Airtable request failed (HTTP 401)' ) );
+
 printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );
 
 exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-publish.php`
Expected: `PHP Fatal error: Uncaught Error: Call to undefined method WPCPM_Track_Publish::checklist()`.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/tracks/class-wpcpm-track-publish.php b/includes/tracks/class-wpcpm-track-publish.php
index e4ca2e4..4703f48 100644
--- a/includes/tracks/class-wpcpm-track-publish.php
+++ b/includes/tracks/class-wpcpm-track-publish.php
@@ -65,6 +65,23 @@ final class WPCPM_Track_Publish {
 	 */
 	const META_RUN = '_wpcpm_track_run';
 
+	/**
+	 * Where the checklist's ticks are kept: who ticked each item, and when.
+	 *
+	 * @var string
+	 */
+	const META_CHECKLIST = '_wpcpm_track_checklist';
+
+	/**
+	 * The three things the site cannot do, in the order they have to happen.
+	 *
+	 * The site cannot see an Airtable automation either way, so an unticked item never blocks
+	 * publishing; the track list counts it until somebody ticks it (7.3).
+	 *
+	 * @var string[]
+	 */
+	const CHECKLIST = array( 'automation', 'welcome', 'choices' );
+
 	/**
 	 * What publishing this track would do, without doing any of it.
 	 *
@@ -327,6 +344,206 @@ final class WPCPM_Track_Publish {
 		);
 	}
 
+	/**
+	 * What the site cannot do, with the exact values to use.
+	 *
+	 * @param int $post_id The track.
+	 * @return array One entry per item of `CHECKLIST`, each `label`, `detail`, `ticked`, `by`, `at`.
+	 */
+	public static function checklist( $post_id ) {
+		$post_id    = (int) $post_id;
+		$definition = WPCPM_Track_Store::get( $post_id );
+		$status     = ( is_array( $definition ) && isset( $definition['status'] ) ) ? (string) $definition['status'] : '';
+		$ticks      = self::ticks( $post_id );
+		$list       = array();
+
+		$labels = array(
+			'automation' => array(
+				__( 'Add the status to the reports automation', 'wpcredits-program-manager' ),
+				sprintf(
+					/* translators: %s: the track's status. */
+					__( 'In Airtable, add "%s" to the condition of the automation named "Add students to Students Reports and Feedback". Without it, no student on this track gets a report row, and their Student Report Card stays empty.', 'wpcredits-program-manager' ),
+					$status
+				),
+			),
+			'welcome'    => array(
+				__( 'Create the welcome email automation', 'wpcredits-program-manager' ),
+				sprintf(
+					/* translators: %s: the track's status. */
+					__( 'Each of the four tracks has one. Copy an existing welcome email automation and set its condition to "%s".', 'wpcredits-program-manager' ),
+					$status
+				),
+			),
+			'choices'    => array(
+				__( 'Add the two Status choices', 'wpcredits-program-manager' ),
+				sprintf(
+					/* translators: %s: the track's status. */
+					__( 'Add "%s" as a choice of the Status column on both Students Reports and Students. No token can add a choice to a single select, so this one is always by hand.', 'wpcredits-program-manager' ),
+					$status
+				),
+			),
+		);
+
+		foreach ( self::CHECKLIST as $item ) {
+			$list[ $item ] = array(
+				'label'  => $labels[ $item ][0],
+				'detail' => $labels[ $item ][1],
+				'ticked' => isset( $ticks[ $item ] ),
+				'by'     => isset( $ticks[ $item ]['by'] ) ? (int) $ticks[ $item ]['by'] : 0,
+				'at'     => isset( $ticks[ $item ]['at'] ) ? (int) $ticks[ $item ]['at'] : 0,
+			);
+		}
+
+		return $list;
+	}
+
+	/**
+	 * Tick one item, recording who and when.
+	 *
+	 * Ticking the reports automation is what puts this track's status into
+	 * `WPCPM_Tracks::confirmed_automation_statuses()`, which the institution import and institution
+	 * create read (the design's decision 10). The compiled row carries that flag, so the tick
+	 * recompiles: without it the gate would stay shut until something unrelated published.
+	 *
+	 * @param int    $post_id The track.
+	 * @param string $item    One of `CHECKLIST`.
+	 * @param int    $user_id Who ticked it; 0 for the current user.
+	 * @return true|WP_Error
+	 */
+	public static function tick( $post_id, $item, $user_id = 0 ) {
+		return self::set_tick( $post_id, $item, $user_id, true );
+	}
+
+	/**
+	 * Take a tick back, for an item somebody ticked by mistake.
+	 *
+	 * @param int    $post_id The track.
+	 * @param string $item    One of `CHECKLIST`.
+	 * @param int    $user_id Who unticked it; 0 for the current user.
+	 * @return true|WP_Error
+	 */
+	public static function untick( $post_id, $item, $user_id = 0 ) {
+		return self::set_tick( $post_id, $item, $user_id, false );
+	}
+
+	/**
+	 * Check a published track against the base, changing nothing.
+	 *
+	 * A column renamed in the base otherwise surfaces as a silently empty answer: the form keeps
+	 * writing to a name nothing reads any more (7.4).
+	 *
+	 * @param int $post_id The track.
+	 * @return array|WP_Error `columns` with `missing` and `wrong`, and `choices`.
+	 */
+	public static function verify( $post_id ) {
+		$post_id    = (int) $post_id;
+		$definition = WPCPM_Track_Store::get( $post_id );
+
+		if ( ! is_array( $definition ) ) {
+			return new WP_Error( 'wpcpm_track_missing', __( 'That track does not exist.', 'wpcredits-program-manager' ) );
+		}
+
+		$settings = WPCPM_Settings::get();
+		$client   = new WPCPM_Airtable();
+		$schema   = $client->fetch_schema();
+
+		if ( is_wp_error( $schema ) ) {
+			return $schema;
+		}
+
+		$reports  = isset( $settings['reports_table'] ) ? (string) $settings['reports_table'] : '';
+		$students = isset( $settings['students_table'] ) ? (string) $settings['students_table'] : '';
+		$columns  = isset( $schema[ $reports ]['columns'] ) ? (array) $schema[ $reports ]['columns'] : array();
+		$missing  = array();
+		$wrong    = array();
+
+		foreach ( (array) ( isset( $definition['questions'] ) ? $definition['questions'] : array() ) as $column => $question ) {
+			$verdict = WPCPM_Track_Columns::judge( (string) $column, (array) $question, $columns );
+
+			if ( 'create' === $verdict ) {
+				$missing[] = (string) $column;
+				continue;
+			}
+
+			if ( 'ok' !== $verdict ) {
+				$wrong[] = (string) $column;
+			}
+		}
+
+		$status = isset( $definition['status'] ) ? (string) $definition['status'] : '';
+
+		return array(
+			'columns' => array(
+				'missing' => $missing,
+				'wrong'   => $wrong,
+			),
+			'choices' => array(
+				'reports'  => self::choice_state( $status, $schema, $reports ),
+				'students' => self::choice_state( $status, $schema, $students ),
+			),
+		);
+	}
+
+	/**
+	 * Record or clear one tick.
+	 *
+	 * @param int    $post_id The track.
+	 * @param string $item    One of `CHECKLIST`.
+	 * @param int    $user_id Who did it; 0 for the current user.
+	 * @param bool   $on      Whether it is now ticked.
+	 * @return true|WP_Error
+	 */
+	private static function set_tick( $post_id, $item, $user_id, $on ) {
+		$post_id = (int) $post_id;
+		$item    = (string) $item;
+
+		if ( ! in_array( $item, self::CHECKLIST, true ) ) {
+			return new WP_Error( 'wpcpm_track_checklist_item', __( 'That is not one of the checklist items.', 'wpcredits-program-manager' ) );
+		}
+
+		$user_id = $user_id ? (int) $user_id : get_current_user_id();
+		$ticks   = self::ticks( $post_id );
+
+		if ( $on ) {
+			$ticks[ $item ] = array(
+				'by' => $user_id,
+				'at' => time(),
+			);
+		} else {
+			unset( $ticks[ $item ] );
+		}
+
+		update_post_meta( $post_id, self::META_CHECKLIST, $ticks );
+
+		// Item 1 is the one the site reads: the compiled row carries the flag that opens the
+		// institution gate, so the tick has to recompile for it to mean anything today.
+		if ( 'automation' === $item ) {
+			if ( $on ) {
+				update_post_meta( $post_id, WPCPM_Track_Store::META_AUTOMATION, '1' );
+			} else {
+				delete_post_meta( $post_id, WPCPM_Track_Store::META_AUTOMATION );
+			}
+
+			WPCPM_Track_Store::compile();
+		}
+
+		WPCPM_Track_Store::log( $post_id, ( $on ? 'tick-' : 'untick-' ) . $item, $user_id );
+
+		return true;
+	}
+
+	/**
+	 * The ticks recorded on a track.
+	 *
+	 * @param int $post_id The track.
+	 * @return array
+	 */
+	private static function ticks( $post_id ) {
+		$ticks = get_post_meta( (int) $post_id, self::META_CHECKLIST, true );
+
+		return is_array( $ticks ) ? $ticks : array();
+	}
+
 	/**
 	 * The columns an earlier run of this track already created.
 	 *
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-publish.php`
Expected: `ALL PASS (52 checks)`

- [ ] **Step 5: Run everything**

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
bash bin/check-standards.sh 2>&1 | tail -1
```

Expected: silent, clean, and `85 warnings, no errors.`

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Track Builder T2c: the checklist, its ticks, and verify"
```

---

### Task 6: The unpublish guard, and one walk for every student count

**Files:**
- Modify: `includes/tracks/class-wpcpm-track-publish.php`
- Modify: `includes/modules/class-wpcpm-students-sync.php`
- Test: `bin/test-track-publish.php`
- Test: `bin/test-students-sync.php`

**Interfaces:**
- Consumes: `WPCPM_Track_Store::unpublish()` as T2a left it.
- Produces: `WPCPM_Track_Publish::take_down( int $post_id, int $user_id = 0 ): int|WP_Error`, refusing with `wpcpm_track_in_use` and the count in its data; `WPCPM_Students_Sync::counts_by_status(): array<string,int>` and `::forget_counts(): void`, with `count_on_status()` now a lookup into the first.

Unpublishing compiles a track out, and a student on it would open a page with no form. The counter changes shape here because the track list asks once per row: one walk answers the whole column, which is what T2b's whole-branch review parked for this phase (decision 19).

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-students-sync.php b/bin/test-students-sync.php
index 736770f..6b37105 100644
--- a/bin/test-students-sync.php
+++ b/bin/test-students-sync.php
@@ -186,7 +186,8 @@ function wp_insert_user( array $data ) {
  * Users by exact meta match, or by a meta key existing, honouring `fields`.
  */
 function get_users( $args = array() ) {
-	$out = array();
+	$GLOBALS['user_queries'] = ( $GLOBALS['user_queries'] ?? 0 ) + 1;
+	$out                     = array();
 
 	foreach ( $GLOBALS['umeta'] as $id => $meta ) {
 		if ( isset( $args['meta_key'] ) ) {
@@ -1375,5 +1376,34 @@ ck( 'a track nobody holds counts none, and so does no status at all',
     array( WPCPM_Students_Sync::count_on_status( 'Writing Track' ), WPCPM_Students_Sync::count_on_status( '' ) ),
     array( 0, 0 ) );
 
+$all_counts = WPCPM_Students_Sync::counts_by_status();
+
+ck( 'every status is counted in one answer',
+    array_intersect_key( $all_counts, array( 'Counting Track' => 0, 'Other Track' => 0 ) ),
+    array( 'Counting Track' => 2, 'Other Track' => 1 ) );
+
+ck( 'and the statuses the rest of this suite set up are in the same answer, so it is one walk for all of them',
+    array( isset( $all_counts['In Sensei'] ), count( $all_counts ) > 2 ), array( true, true ) );
+
+// The track list asks once per row, and a query per row grew with the roster (the T2b
+// whole-branch review). Four rows now cost one walk, not four.
+WPCPM_Students_Sync::forget_counts();
+$GLOBALS['user_queries'] = 0;
+
+foreach ( array( 'Counting Track', 'Other Track', 'Writing Track', 'Another Track' ) as $one ) {
+	WPCPM_Students_Sync::count_on_status( $one );
+}
+
+ck( 'and four rows cost one walk over the students, not four', $GLOBALS['user_queries'], 1 );
+
+// A process that changes a status and reads it again asks for the walk to happen afresh.
+update_user_meta( 903, WPCPM_Students_Sync::META_PROGRAM, array( 'program' => 'Counting Track' ) );
+
+ck( 'the held answer stands until it is let go', WPCPM_Students_Sync::count_on_status( 'Counting Track' ), 2 );
+
+WPCPM_Students_Sync::forget_counts();
+
+ck( 'and then the next read walks again', WPCPM_Students_Sync::count_on_status( 'Counting Track' ), 3 );
+
 printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
 exit( $fail ? 1 : 0 );
diff --git a/bin/test-track-publish.php b/bin/test-track-publish.php
index 48d22ed..cf62d8a 100644
--- a/bin/test-track-publish.php
+++ b/bin/test-track-publish.php
@@ -51,12 +51,19 @@ class WPCPM_Track_Store {
 	public static function check( $post_id, array $definition ) { return self::$errors[ $post_id ] ?? array(); }
 	public static function source( $post_id ) { return self::$sources[ $post_id ] ?? 'definition'; }
 
+	public static $unpublished = array();
+
 	public static function publish( $post_id, $user_id = 0 ) {
 		if ( self::$refuse instanceof WP_Error ) { return self::$refuse; }
 		self::$published[] = array( (int) $post_id, (int) $user_id );
 		return (int) $post_id;
 	}
 
+	public static function unpublish( $post_id, $user_id = 0 ) {
+		self::$unpublished[] = array( (int) $post_id, (int) $user_id );
+		return (int) $post_id;
+	}
+
 	public static function log( $post_id, $did, $user_id = 0, array $detail = array() ) {
 		self::$logged[] = array( (int) $post_id, (string) $did, (int) $user_id, $detail );
 		return true;
@@ -116,6 +123,13 @@ function wp_slash( $v ) { return $v; }
 function wp_unslash( $v ) { return $v; }
 function wp_json_encode( $v ) { return json_encode( $v ); }
 function get_current_user_id() { return 5; }
+function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
+
+/** The students sync, stood in: how many people hold a status. */
+class WPCPM_Students_Sync {
+	public static $counts = array();
+	public static function count_on_status( $status ) { return (int) ( self::$counts[ $status ] ?? 0 ); }
+}
 function time_now() { return $GLOBALS['now'] ?? 1788000000; }
 
 require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-columns.php';
@@ -329,7 +343,9 @@ function fresh_run() {
 	WPCPM_Airtable::$later        = null;
 	WPCPM_Airtable::$reads        = 0;
 	WPCPM_Airtable::$created      = array();
-	WPCPM_Track_Store::$published = array();
+	WPCPM_Track_Store::$published   = array();
+	WPCPM_Track_Store::$unpublished = array();
+	WPCPM_Students_Sync::$counts    = array();
 	WPCPM_Track_Store::$logged    = array();
 	WPCPM_Track_Store::$refuse    = null;
 	WPCPM_Track_Store::$errors    = array();
@@ -541,6 +557,30 @@ $seen = WPCPM_Track_Publish::verify( 7 );
 ck( 'and when the base cannot be read it says so rather than reporting everything missing',
     array( is_wp_error( $seen ), $seen->get_error_message() ), array( true, 'Airtable request failed (HTTP 401)' ) );
 
+echo "\n=== Unpublishing, and the students it would strand ===\n";
+
+fresh_run();
+WPCPM_Students_Sync::$counts = array( 'Marketing Track' => 3 );
+$refused_down = WPCPM_Track_Publish::take_down( 7, 5 );
+
+ck( 'a track students are on is not taken down, and the message counts them',
+    array( $refused_down->get_error_code(), $refused_down->get_error_data(), WPCPM_Track_Store::$unpublished ),
+    array( 'wpcpm_track_in_use', array( 'students' => 3 ), array() ) );
+
+ck( 'and the count is in the sentence a person reads',
+    false !== strpos( $refused_down->get_error_message(), '3 students are on this track' ), true );
+
+fresh_run();
+WPCPM_Students_Sync::$counts = array( 'Marketing Track' => 1 );
+
+ck( 'one student reads as one student, not "1 students"',
+    false !== strpos( WPCPM_Track_Publish::take_down( 7, 5 )->get_error_message(), '1 student is on this track' ), true );
+
+fresh_run();
+
+ck( 'with nobody on it the store takes it down',
+    array( WPCPM_Track_Publish::take_down( 7, 5 ), WPCPM_Track_Store::$unpublished ), array( 7, array( array( 7, 5 ) ) ) );
+
 printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );
 
 exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-publish.php`
Expected: `PHP Fatal error: Uncaught Error: Call to undefined method WPCPM_Track_Publish::take_down()`.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/modules/class-wpcpm-students-sync.php b/includes/modules/class-wpcpm-students-sync.php
index 00e98ba..dcd58d1 100644
--- a/includes/modules/class-wpcpm-students-sync.php
+++ b/includes/modules/class-wpcpm-students-sync.php
@@ -2466,6 +2466,13 @@ class WPCPM_Students_Sync {
 		return true;
 	}
 
+	/**
+	 * The statuses counted once for this request, or null when nothing has asked yet.
+	 *
+	 * @var array<string,int>|null
+	 */
+	private static $counts = null;
+
 	/**
 	 * How many synced students hold a track's status now.
 	 *
@@ -2483,8 +2490,32 @@ class WPCPM_Students_Sync {
 			return 0;
 		}
 
-		$count = 0;
-		$users = get_users(
+		$counts = self::counts_by_status();
+
+		return isset( $counts[ $status ] ) ? (int) $counts[ $status ] : 0;
+	}
+
+	/**
+	 * How many provisioned students hold each status, in one pass.
+	 *
+	 * The track list asks for a count once per row, and asking per status meant walking every
+	 * provisioned student again each time, so the screen's cost grew with the roster (the T2b
+	 * whole-branch review). One walk answers the whole column. The answer is held for this request
+	 * only, because a sync running while the page is drawn would otherwise be read as stale.
+	 *
+	 * It counts by the status the site recorded, with no filter for whether the account is still
+	 * active: that is the number the Track Builder's questions are about, who would lose their
+	 * page if the track left the site.
+	 *
+	 * @return array<string,int> Status to how many hold it.
+	 */
+	public static function counts_by_status() {
+		if ( null !== self::$counts ) {
+			return self::$counts;
+		}
+
+		$counts = array();
+		$users  = get_users(
 			array(
 				'number'     => -1,
 				'fields'     => 'ID',
@@ -2500,12 +2531,31 @@ class WPCPM_Students_Sync {
 		foreach ( $users as $user_id ) {
 			$program = get_user_meta( (int) $user_id, self::META_PROGRAM, true );
 
-			if ( is_array( $program ) && isset( $program['program'] ) && (string) $program['program'] === $status ) {
-				++$count;
+			if ( ! is_array( $program ) || ! isset( $program['program'] ) ) {
+				continue;
 			}
+
+			$status = (string) $program['program'];
+
+			if ( '' === $status ) {
+				continue;
+			}
+
+			$counts[ $status ] = isset( $counts[ $status ] ) ? $counts[ $status ] + 1 : 1;
 		}
 
-		return $count;
+		self::$counts = $counts;
+
+		return $counts;
+	}
+
+	/**
+	 * Forget the counted statuses, for a process that changes them and reads them again.
+	 *
+	 * @return void
+	 */
+	public static function forget_counts() {
+		self::$counts = null;
 	}
 
 	/**
diff --git a/includes/tracks/class-wpcpm-track-publish.php b/includes/tracks/class-wpcpm-track-publish.php
index 4703f48..b69e11c 100644
--- a/includes/tracks/class-wpcpm-track-publish.php
+++ b/includes/tracks/class-wpcpm-track-publish.php
@@ -426,6 +426,51 @@ final class WPCPM_Track_Publish {
 		return self::set_tick( $post_id, $item, $user_id, false );
 	}
 
+	/**
+	 * Take a track off the live site, once nobody is on it.
+	 *
+	 * Refused while any synced student holds the status, with the count, because unpublishing
+	 * compiles the track out and those students would open a page with no form on it. Airtable is
+	 * not touched and the status stays in "Currently mentoring": taking it out is a manager's
+	 * decision in Settings, since `revoke_departed()` would take the Student role from everybody
+	 * on the track (7.5).
+	 *
+	 * @param int $post_id The track.
+	 * @param int $user_id Who pressed it, for the log; 0 for the current user.
+	 * @return int|WP_Error The post ID, or why nothing was done.
+	 */
+	public static function take_down( $post_id, $user_id = 0 ) {
+		$post_id    = (int) $post_id;
+		$definition = WPCPM_Track_Store::get( $post_id );
+
+		if ( ! is_array( $definition ) ) {
+			return new WP_Error( 'wpcpm_track_missing', __( 'That track does not exist.', 'wpcredits-program-manager' ) );
+		}
+
+		$status = isset( $definition['status'] ) ? (string) $definition['status'] : '';
+		$held   = WPCPM_Students_Sync::count_on_status( $status );
+
+		if ( $held > 0 ) {
+			return new WP_Error(
+				'wpcpm_track_in_use',
+				sprintf(
+					/* translators: 1: how many students, 2: the track's status. */
+					_n(
+						'%1$d student is on this track in Airtable, and unpublishing it would leave their Student Report Card with no form on it. Move them off "%2$s" first.',
+						'%1$d students are on this track in Airtable, and unpublishing it would leave their Student Report Cards with no form on them. Move them off "%2$s" first.',
+						$held,
+						'wpcredits-program-manager'
+					),
+					$held,
+					$status
+				),
+				array( 'students' => $held )
+			);
+		}
+
+		return WPCPM_Track_Store::unpublish( $post_id, $user_id );
+	}
+
 	/**
 	 * Check a published track against the base, changing nothing.
 	 *
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-publish.php`
Expected: `ALL PASS (56 checks)` from `bin/test-track-publish.php` and `ALL PASS (177 checks)` from `bin/test-students-sync.php`

- [ ] **Step 5: Run everything**

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
bash bin/check-standards.sh 2>&1 | tail -1
```

Expected: silent, clean, and `85 warnings, no errors.`

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Track Builder T2c: the unpublish guard, and one walk for every count"
```

---

### Task 7: The recompile moves off save(), and maxlength narrows to text

**Files:**
- Modify: `includes/class-wpcpm-settings.php`
- Modify: `includes/class-wpcpm-admin.php`
- Modify: `includes/tracks/class-wpcpm-track-definition.php`
- Test: `bin/test-settings.php`
- Test: `bin/test-track-definition.php`

**Interfaces:**
- Consumes: Nothing.
- Produces: Nothing new: two rules change place and shape.

`WPCPM_Settings::save()` has callers of its own, and one is the handbook's model migration on `init` priority 5, before the track post type is registered at 10; a compile from there is wasted work on an unrelated path (decision 19). And `maxlength` on a textarea was accepted by `validate()` and ignored by the form, so T3's editor would have offered a box that did nothing (open item 7, settled: text only).

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-settings.php b/bin/test-settings.php
index 80207e1..b9a660e 100644
--- a/bin/test-settings.php
+++ b/bin/test-settings.php
@@ -787,7 +787,24 @@ class WPCPM_Track_Store {
 
 $compiled_before = WPCPM_Track_Store::$compiled;
 WPCPM_Settings::save( array( 'student_statuses' => array( 'In Sensei' ) ) );
-ck( 'saving the settings compiles the tracks again', WPCPM_Track_Store::$compiled - $compiled_before, 1 );
+
+// The recompile hangs on the settings screen's own handler, not on `save()`: `save()` has callers
+// of its own, and one of them is the handbook's model migration on `init` priority 5, before the
+// track post type is registered at 10 (the T2b whole-branch review). Asserted by reading both
+// sources, because what is being pinned is which of the two carries the call.
+ck( 'saving through the sanitiser alone compiles nothing', WPCPM_Track_Store::$compiled - $compiled_before, 0 );
+
+$settings_source = (string) file_get_contents( __DIR__ . '/../includes/class-wpcpm-settings.php' );
+
+ck( 'and the sanitiser carries no compile at all', false !== strpos( $settings_source, 'WPCPM_Track_Store::compile()' ), false );
+
+ck( 'the settings screen handler compiles after it saves, which is what decision 14 asks for',
+    array(
+        false !== strpos( $admin, 'WPCPM_Settings::save( $input );' ),
+        false !== strpos( $admin, 'WPCPM_Track_Store::compile();' ),
+        strpos( $admin, 'WPCPM_Settings::save( $input );' ) < strpos( $admin, 'WPCPM_Track_Store::compile();' ),
+    ),
+    array( true, true, true ) );
 
 echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
 
diff --git a/bin/test-track-definition.php b/bin/test-track-definition.php
index c05a839..1c327b8 100644
--- a/bin/test-track-definition.php
+++ b/bin/test-track-definition.php
@@ -195,6 +195,12 @@ ck( 'an empty choice', refused( only( array( 'Tool' => array( 'options' => array
 ck( 'choices on something that is not a select', refused( only( array( 'Notes' => $note + array( 'options' => array( 'A' ) ) ) ), $context ), array( 'options_only' ) );
 ck( 'a length limit on a link', refused( only( array( 'Link' => array( 'label' => 'Link', 'type' => 'url', 'group' => 'project', 'maxlength' => 100 ) ) ), $context ), array( 'maxlength_shape' ) );
 ck( 'a length limit of nothing, or written as words', array( refused( only( array( 'Notes' => $note + array( 'maxlength' => 0 ) ) ), $context ), refused( only( array( 'Notes' => $note + array( 'maxlength' => '100' ) ) ), $context ) ), array( array( 'maxlength_shape' ), array( 'maxlength_shape' ) ) );
+// Open item 7, settled 12 September 2026: text only. A textarea already draws maxlength="5000"
+// and saves capped at the same figure, so a spec value would lower a limit rather than add one,
+// and a browser maxlength on a prose box stops typing with no message.
+ck( 'a length limit on a text box of many lines', refused( only( array( 'Notes' => $note + array( 'maxlength' => 200 ) ) ), $context ), array( 'maxlength_shape' ) );
+ck( 'while one on a single-line box is accepted, which is where all four tracks use it', refused( only( array( 'Slack Name' => array( 'label' => 'Slack', 'type' => 'text', 'group' => 'project', 'maxlength' => 100 ) ) ), $context ), array() );
+
 ck( 'monospace on a one-line box', refused( only( array( 'Name on the brief' => array( 'label' => 'Name', 'type' => 'text', 'group' => 'project', 'mono' => true ) ) ), $context ), array( 'mono_only' ) );
 
 $team = array( 'label' => 'Your contribution team', 'type' => 'team', 'group' => 'project' );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-settings.php`
Expected: `FAIL saving the settings compiles the tracks again` - the check still expects the compile on `save()`, which is exactly what moves.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/class-wpcpm-admin.php b/includes/class-wpcpm-admin.php
index 1f784a1..01a8e5b 100644
--- a/includes/class-wpcpm-admin.php
+++ b/includes/class-wpcpm-admin.php
@@ -350,6 +350,18 @@ class WPCPM_Admin {
 
 		WPCPM_Settings::save( $input );
 
+		// "Currently mentoring" and the past statuses are rules every published track was compiled
+		// against, so a save that changes them would otherwise leave the live tracks, and the list
+		// of what the last compile left out, answering for the settings as they were (decision 14).
+		//
+		// It hangs on this handler rather than on `WPCPM_Settings::save()`, which has callers of
+		// its own: one is the handbook's model migration on `init` priority 5, before the track
+		// post type is registered at 10, and a compile from there is wasted work on an unrelated
+		// path (the T2b whole-branch review).
+		if ( class_exists( 'WPCPM_Track_Store' ) ) {
+			WPCPM_Track_Store::compile();
+		}
+
 		WPCPM_Flash::set( 'settings', 'saved' );
 
 		wp_safe_redirect( self::settings_url() );
diff --git a/includes/class-wpcpm-settings.php b/includes/class-wpcpm-settings.php
index 17d979f..c1e8538 100644
--- a/includes/class-wpcpm-settings.php
+++ b/includes/class-wpcpm-settings.php
@@ -501,13 +501,6 @@ class WPCPM_Settings {
 			WPCPM_Mentor_Checker_Runner::sync_cron( $clean['checker_cron_enabled'] );
 		}
 
-		// "Currently mentoring" and the past statuses are rules every published track was compiled
-		// against, so a save that changes them would otherwise leave the live tracks, and the list
-		// of what the last compile left out, answering for the settings as they were (decision 14).
-		if ( class_exists( 'WPCPM_Track_Store' ) ) {
-			WPCPM_Track_Store::compile();
-		}
-
 		return $clean;
 	}
 
diff --git a/includes/tracks/class-wpcpm-track-definition.php b/includes/tracks/class-wpcpm-track-definition.php
index 97478c3..cc3dd53 100644
--- a/includes/tracks/class-wpcpm-track-definition.php
+++ b/includes/tracks/class-wpcpm-track-definition.php
@@ -438,8 +438,12 @@ final class WPCPM_Track_Definition {
 			$errors[] = self::error( 'options_only', $column, __( 'Only a select has choices.', 'wpcredits-program-manager' ) );
 		}
 
-		if ( isset( $spec['maxlength'] ) && ( ! in_array( $type, array( 'text', 'textarea' ), true ) || ! is_int( $spec['maxlength'] ) || $spec['maxlength'] < 1 ) ) {
-			$errors[] = self::error( 'maxlength_shape', $column, __( 'A length limit is a whole number above zero, on a text box.', 'wpcredits-program-manager' ) );
+		// Text only, settled 12 September 2026 (open item 7). A textarea already draws
+		// `maxlength="5000"` and saves capped at the same figure, so honoring a spec value would
+		// lower a limit rather than add one, and a browser `maxlength` on a prose box stops
+		// typing with no message on the questions students write most in.
+		if ( isset( $spec['maxlength'] ) && ( 'text' !== $type || ! is_int( $spec['maxlength'] ) || $spec['maxlength'] < 1 ) ) {
+			$errors[] = self::error( 'maxlength_shape', $column, __( 'A length limit is a whole number above zero, on a single-line text box.', 'wpcredits-program-manager' ) );
 		}
 
 		if ( ! empty( $spec['mono'] ) && 'textarea' !== $type ) {
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-settings.php`
Expected: `ALL PASS` from `bin/test-settings.php` and `ALL PASS (106 checks)` from `bin/test-track-definition.php`

- [ ] **Step 5: Run everything**

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
bash bin/check-standards.sh 2>&1 | tail -1
```

Expected: silent, clean, and `85 warnings, no errors.`

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Track Builder T2c: the recompile moves to the settings handler, and maxlength narrows to text"
```

---

### Task 8: The institution gate reaches both flows

**Files:**
- Modify: `includes/modules/class-wpcpm-institutions.php`
- Modify: `includes/modules/class-wpcpm-institution-import-form.php`
- Modify: `includes/modules/class-wpcpm-institution-create.php`
- Test: `bin/test-institution-import-form.php`
- Test: `bin/test-institution-create.php`

**Interfaces:**
- Consumes: `WPCPM_Tracks::confirmed_automation_statuses()`, which Task 5's tick now feeds through the compiled row.
- Produces: `WPCPM_Institutions::offered_programs(): array<string,string>`, status to label, gated.

A student put on a track before its status is in the reports automation never gets a report row, and their Student Report Card stays empty. The import form draws the gated map and both handlers check it, so a track unticked after the form was drawn is still refused at the write (decision 20).

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-institution-create.php b/bin/test-institution-create.php
index b7c09b7..bf0d98c 100644
--- a/bin/test-institution-create.php
+++ b/bin/test-institution-create.php
@@ -150,6 +150,29 @@ function is_wp_error( $t ) { return $t instanceof WP_Error; }
 class Lost extends Exception {}
 
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
+
+/**
+ * The institutions module, stood in for its one question here: which programs may be offered.
+ *
+ * The four built-in statuses are always offered; a Track Builder track joins them only once
+ * somebody ticks its reports automation item (the design's decision 10).
+ */
+class WPCPM_Institutions {
+	public static $gated = array();
+
+	public static function offered_programs() {
+		$out = array();
+
+		foreach ( WPCPM_Program::labels() as $status => $label ) {
+			if ( ! in_array( (string) $status, self::$gated, true ) ) {
+				$out[ (string) $status ] = (string) $label;
+			}
+		}
+
+		return $out;
+	}
+}
+
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
 
 class WPCPM_Mentors_Sync {
@@ -1049,6 +1072,24 @@ foreach ( array( 'module' => $source, 'suite' => file_get_contents( __FILE__ ) )
 	ck( sprintf( 'no dash but the plain hyphen in the %s', $what ), preg_match( '/[\x{2013}\x{2014}]/u', $text ), 0 );
 }
 
+echo "\n=== The institution gate reaches institution create too (decision 10) ===\n";
+
+// The batch is read back out of storage here, so a track whose automation item was unticked
+// between staging and creating is refused at the write rather than putting students on a track
+// that generates no report rows.
+WPCPM_Institutions::$gated = array( WPCPM_Program::STATUS_150H );
+
+ck( 'a batch whose program is gated creates nothing',
+    WPCPM_Institution_Create::fields_for( $batch, $batch['rows'][0], 0 ),
+    array() );
+
+WPCPM_Institutions::$gated = array();
+
+ck( 'and the same batch creates once the item is ticked again',
+    array() !== WPCPM_Institution_Create::fields_for( $batch, $batch['rows'][0], 0 ),
+    true );
+
+
 printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
 
 exit( $fails ? 1 : 0 );
diff --git a/bin/test-institution-import-form.php b/bin/test-institution-import-form.php
index 77423f3..e2762ea 100644
--- a/bin/test-institution-import-form.php
+++ b/bin/test-institution-import-form.php
@@ -114,6 +114,29 @@ function wpcpm_test_exit() { throw new Left( $GLOBALS['redirect'] ); }
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';
 
+/**
+ * The institutions module, stood in for its one question here: which programs may be offered.
+ *
+ * The four built-in statuses are always offered; a Track Builder track joins them only once
+ * somebody ticks its reports automation item (the design's decision 10).
+ */
+class WPCPM_Institutions {
+	public static $gated = array();
+
+	public static function offered_programs() {
+		$out = array();
+
+		foreach ( WPCPM_Program::labels() as $status => $label ) {
+			if ( ! in_array( (string) $status, self::$gated, true ) ) {
+				$out[ (string) $status ] = (string) $label;
+			}
+		}
+
+		return $out;
+	}
+}
+
+
 /** Only what the form calls, so the suite stays about the form. */
 class WPCPM_Mentors_Sync {
 	public static function is_record_id( $id ) { return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $id ); }
@@ -716,6 +739,30 @@ foreach ( array( 'module' => $source, 'suite' => file_get_contents( __FILE__ ) )
 	ck( sprintf( 'no dash but the plain hyphen in the %s', $what ), preg_match( '/[\x{2013}\x{2014}]/u', $text ), 0 );
 }
 
+echo "\n=== The institution gate (the design's decision 10) ===\n";
+
+// A student put on a Track Builder track before its status is in the reports automation never
+// gets a report row, so the track is not offered until somebody ticks checklist item 1. The four
+// built-in statuses are in the pinned list and are never gated.
+fresh_world();
+WPCPM_Institutions::$gated = array( WPCPM_Program::STATUS_DEV );
+$gated_form                = draw_section( $HERE );
+
+ck( 'a track whose automation item is not ticked is not in the picker',
+    false !== strpos( $gated_form, 'value="' . WPCPM_Program::STATUS_DEV . '"' ), false );
+
+ck( 'while the programs that are stay in it',
+    false !== strpos( $gated_form, 'value="' . WPCPM_Program::STATUS_150H . '"' ), true );
+
+fresh_world();
+WPCPM_Institutions::$gated = array( WPCPM_Program::STATUS_DEV );
+
+ck( 'and posting the gated one is refused by the handler, whatever the form said',
+    post_check( batch_fields( array( 'program' => WPCPM_Program::STATUS_DEV ) ) ), 'bad-program' );
+
+WPCPM_Institutions::$gated = array();
+
+
 printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
 
 exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-institution-import-form.php`
Expected: `PHP Fatal error: Uncaught Error: Class "WPCPM_Institutions" not found`, because neither suite loads it yet: the stub is part of this task's first step.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/modules/class-wpcpm-institution-create.php b/includes/modules/class-wpcpm-institution-create.php
index 2023c42..ed449dc 100644
--- a/includes/modules/class-wpcpm-institution-create.php
+++ b/includes/modules/class-wpcpm-institution-create.php
@@ -741,7 +741,7 @@ final class WPCPM_Institution_Create {
 		// The program is the server's own map and the date is the form's checked one, but both
 		// are read back out of storage here, and a batch stored before either was validated
 		// would otherwise be created with a blank Status that Airtable refuses per record.
-		if ( '' === $name || '' === $email || '' === $start || ! isset( WPCPM_Program::labels()[ $status ] ) ) {
+		if ( '' === $name || '' === $email || '' === $start || ! isset( WPCPM_Institutions::offered_programs()[ $status ] ) ) {
 			return array();
 		}
 
diff --git a/includes/modules/class-wpcpm-institution-import-form.php b/includes/modules/class-wpcpm-institution-import-form.php
index 004102d..098d21d 100644
--- a/includes/modules/class-wpcpm-institution-import-form.php
+++ b/includes/modules/class-wpcpm-institution-import-form.php
@@ -189,8 +189,10 @@ final class WPCPM_Institution_Import_Form {
 		echo '<select id="wpcpm-import-program" name="program">';
 
 		// The value is the status the base holds and the label is what a person calls it. The
-		// map is the server's, so a posted value that is not one of these is not a program.
-		foreach ( WPCPM_Program::labels() as $status => $label ) {
+		// map is the server's, so a posted value that is not one of these is not a program. A
+		// Track Builder track appears only once its reports automation item is ticked, because a
+		// student on it before that never gets a report row (the design's decision 10).
+		foreach ( WPCPM_Institutions::offered_programs() as $status => $label ) {
 			printf( '<option value="%1$s">%2$s</option>', esc_attr( $status ), esc_html( $label ) );
 		}
 
@@ -1011,7 +1013,9 @@ final class WPCPM_Institution_Import_Form {
 
 		// The map is the server's. A posted value outside it is not a program this site offers,
 		// whatever the form said, and typecast is off in the base so it would be a 422 anyway.
-		if ( ! isset( WPCPM_Program::labels()[ $status ] ) ) {
+		// It is the gated map, so a track whose automation item is not ticked is refused here
+		// even if the select was drawn before somebody unticked it (decision 10).
+		if ( ! isset( WPCPM_Institutions::offered_programs()[ $status ] ) ) {
 			return array(
 				'values'  => array(),
 				'problem' => 'bad-program',
diff --git a/includes/modules/class-wpcpm-institutions.php b/includes/modules/class-wpcpm-institutions.php
index 95a5d4f..64d3687 100644
--- a/includes/modules/class-wpcpm-institutions.php
+++ b/includes/modules/class-wpcpm-institutions.php
@@ -854,6 +854,31 @@ class WPCPM_Institutions extends WPCPM_Sync_Module {
 		return array_values( array_unique( $statuses ) );
 	}
 
+	/**
+	 * The programs an institution may be given, as status to label.
+	 *
+	 * `WPCPM_Program::labels()` names every live track; this names the ones a student can safely
+	 * be put on. A Track Builder track reaches these two flows only once somebody has ticked its
+	 * reports automation item, because a student put on it before the automation names its status
+	 * never gets a report row and their Student Report Card stays empty (the design's decision 10,
+	 * and 2.4 for the automation itself). The four built-in statuses are in the pinned list, so
+	 * they are unaffected.
+	 *
+	 * @return array<string,string> Status to label, in the order `labels()` gives them.
+	 */
+	public static function offered_programs() {
+		$offered = self::automation_statuses();
+		$out     = array();
+
+		foreach ( WPCPM_Program::labels() as $status => $label ) {
+			if ( in_array( (string) $status, $offered, true ) ) {
+				$out[ (string) $status ] = (string) $label;
+			}
+		}
+
+		return $out;
+	}
+
 	/**
 	 * Why this row may not be linked, or '' when it may.
 	 *
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-institution-import-form.php`
Expected: `ALL PASS (104 checks)` from `bin/test-institution-import-form.php` and `ALL PASS (151 checks)` from `bin/test-institution-create.php`

- [ ] **Step 5: Run everything**

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
bash bin/check-standards.sh 2>&1 | tail -1
```

Expected: silent, clean, and `85 warnings, no errors.`

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Track Builder T2c: the institution gate reaches both flows"
```

---

### Task 9: The publish screen and its five handlers

**Files:**
- Modify: `includes/tools/class-wpcpm-track-builder.php`
- Modify: `includes/tools/class-wpcpm-track-builder-screen.php`
- Modify: `includes/tracks/class-wpcpm-track-publish.php`
- Test: `bin/test-track-builder.php`

**Interfaces:**
- Consumes: Everything above: `preflight()`, `run()`, `checklist()`, `tick()`, `untick()`, `verify()`, `take_down()` and `WPCPM_Settings::has_schema_token()`.
- Produces: `WPCPM_Track_Builder::ACTION_PUBLISH`, `ACTION_UNPUBLISH`, `ACTION_VERIFY`, `ACTION_TICK` and `ACTION_UNTICK` with their handlers; and `WPCPM_Track_Builder_Screen::render_publish( array $args )`.

The publish screen is its own view because it has a preflight to read, a checklist to work through and, when a column is missing, a list to take to Airtable. The row's Publish link reaches it, and a trashed track is not offered it at all, because the store refuses to publish out of the trash.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-builder.php b/bin/test-track-builder.php
index f03278a..74f35f3 100644
--- a/bin/test-track-builder.php
+++ b/bin/test-track-builder.php
@@ -40,6 +40,7 @@ function wp_safe_redirect( $url ) { throw new RedirectSignal( (string) $url ); }
 function wp_die( $message = '', $title = '', $args = array() ) { throw new DieSignal( is_string( $message ) ? $message : '' ); }
 function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
 function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][] = $hook; return true; }
+function get_current_user_id() { return 5; }
 function get_userdata( $id ) { return isset( $GLOBALS['users'][ (int) $id ] ) ? (object) array( 'display_name' => $GLOBALS['users'][ (int) $id ] ) : false; }
 function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
 function wp_enqueue_style( $handle, $src = '', $deps = array() ) { $GLOBALS['enqueued'][] = array( 'style', $handle, $deps ); }
@@ -75,6 +76,51 @@ $GLOBALS['users']    = array( 7 => 'A Manager' );
 $GLOBALS['enqueued'] = array();
 
 /** The store, as the screen uses it: definitions, states, logs, equivalence and the seeds. */
+/** Publishing, stood in: what the preflight says, what the checklist holds, and what was pressed. */
+class WPCPM_Track_Publish {
+	public static $flight    = array();
+	public static $checklist = array();
+	public static $ran       = array();
+	public static $ticked    = array();
+	public static $down      = array();
+	public static $verified  = array();
+	public static $answer    = null;
+
+	public static function preflight( $post_id ) { return self::$flight; }
+	public static function checklist( $post_id ) { return self::$checklist; }
+
+	public static function run( $post_id, $user_id = 0 ) {
+		self::$ran[] = array( (int) $post_id, (int) $user_id );
+		return null === self::$answer ? array( 'created' => array( 'Brand new' ), 'published' => true ) : self::$answer;
+	}
+
+	public static function take_down( $post_id, $user_id = 0 ) {
+		self::$down[] = array( (int) $post_id, (int) $user_id );
+		return null === self::$answer ? (int) $post_id : self::$answer;
+	}
+
+	public static function verify( $post_id ) {
+		self::$verified[] = (int) $post_id;
+		return null === self::$answer ? array( 'columns' => array( 'missing' => array(), 'wrong' => array() ), 'choices' => array( 'reports' => 'ok', 'students' => 'ok' ) ) : self::$answer;
+	}
+
+	public static function tick( $post_id, $item, $user_id = 0 ) {
+		self::$ticked[] = array( 'tick', (int) $post_id, (string) $item );
+		return null === self::$answer ? true : self::$answer;
+	}
+
+	public static function untick( $post_id, $item, $user_id = 0 ) {
+		self::$ticked[] = array( 'untick', (int) $post_id, (string) $item );
+		return null === self::$answer ? true : self::$answer;
+	}
+}
+
+/** The settings, stood in for the one question the screen asks them. */
+class WPCPM_Settings {
+	public static $schema = true;
+	public static function has_schema_token() { return self::$schema; }
+}
+
 class WPCPM_Track_Store {
 	const META_SOURCE = '_wpcpm_track_source';
 	const OPT_SKIPPED = 'wpcpm_tracks_skipped';
@@ -591,5 +637,205 @@ ck( 'the way back runs through the store too',
 
 $_POST = array();
 
+echo "\n=== The publish screen ===\n";
+
+WPCPM_Track_Publish::$flight = array(
+	'refusals'    => array(),
+	'warnings'    => array( array( 'code' => 'course_unreachable', 'column' => '', 'message' => 'The Learn course did not answer.' ) ),
+	'columns'     => array( 'create' => array( 'Brand new' ), 'ready' => array( 'What you did' ) ),
+	'choices'     => array( 'reports' => 'ok', 'students' => 'missing' ),
+	'fields'      => array( 'now' => 120, 'after' => 121 ),
+	'adds_status' => true,
+	'ready'       => true,
+);
+WPCPM_Track_Publish::$checklist = array(
+	'automation' => array( 'label' => 'Add the status to the reports automation', 'detail' => 'Add "Marketing Track" to the condition.', 'ticked' => false, 'by' => 0, 'at' => 0 ),
+	'welcome'    => array( 'label' => 'Create the welcome email automation', 'detail' => 'Copy an existing one.', 'ticked' => true, 'by' => 7, 'at' => 1788000000 ),
+	'choices'    => array( 'label' => 'Add the two Status choices', 'detail' => 'On both tables.', 'ticked' => false, 'by' => 0, 'at' => 0 ),
+);
+$GLOBALS['users'] = array( 7 => 'Ada Lovelace' );
+
+ob_start();
+WPCPM_Track_Builder_Screen::render_publish(
+	array(
+		'track'     => 12,
+		'label'     => 'Marketing Track',
+		'state'     => 'draft',
+		'preflight' => WPCPM_Track_Publish::$flight,
+		'checklist' => WPCPM_Track_Publish::$checklist,
+		'can_make'  => true,
+		'url'       => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder',
+		'flash'     => array(),
+	)
+);
+$screen = ob_get_clean();
+
+ck( 'it names the track it is about', false !== strpos( $screen, 'Publishing Marketing Track' ), true );
+
+ck( 'a warning is drawn as a warning, not as a refusal',
+    array( false !== strpos( $screen, 'notice-warning' ), false !== strpos( $screen, 'notice-error' ) ), array( true, false ) );
+
+ck( 'the column to create is named, so somebody could make it by hand',
+    false !== strpos( $screen, '<code>Brand new</code>' ), true );
+
+ck( 'and what the table would come to is said',
+    false !== strpos( $screen, 'The table would hold 121 columns afterward.' ), true );
+
+ck( 'every checklist item is drawn, with the one that is done marked',
+    array( substr_count( $screen, 'wpcpm-tracks__item' ), substr_count( $screen, 'wpcpm-tracks__item--done' ) ), array( 4, 1 ) );
+
+ck( 'a ticked item says who ticked it', false !== strpos( $screen, 'Ticked by Ada Lovelace' ), true );
+
+ck( 'an unticked one offers the tick and a ticked one offers the undo',
+    array( substr_count( $screen, 'I have done this' ), substr_count( $screen, '>Undo</button>' ) ), array( 2, 1 ) );
+
+ck( 'the tick carries the item as well as the track',
+    false !== strpos( $screen, 'name="item" value="automation"' ), true );
+
+ck( 'a draft that passes its preflight offers Publish, and neither of the live-track buttons',
+    array(
+        false !== strpos( $screen, 'Publish this track' ),
+        false !== strpos( $screen, 'Check it against Airtable' ),
+        false !== strpos( $screen, 'Take it off the live site' ),
+    ),
+    array( true, false, false ) );
+
+// A label with markup in it reaches the page encoded: the track's name is typed by a person.
+ob_start();
+WPCPM_Track_Builder_Screen::render_publish(
+	array(
+		'track'     => 12,
+		'label'     => 'Marketing <b>Track</b>',
+		'state'     => 'draft',
+		'preflight' => WPCPM_Track_Publish::$flight,
+		'checklist' => array(),
+		'can_make'  => true,
+		'url'       => '',
+		'flash'     => array(),
+	)
+);
+$escaped_screen = ob_get_clean();
+
+ck( 'and a name with markup in it is encoded on the way out',
+    array( false !== strpos( $escaped_screen, 'Marketing &lt;b&gt;Track&lt;/b&gt;' ), false !== strpos( $escaped_screen, '<b>Track</b>' ) ),
+    array( true, false ) );
+
+$refused_flight            = WPCPM_Track_Publish::$flight;
+$refused_flight['ready']   = false;
+$refused_flight['refusals'] = array( array( 'code' => 'column_computed', 'column' => 'Personal link', 'message' => 'Airtable works this column out for itself.' ) );
+
+ob_start();
+WPCPM_Track_Builder_Screen::render_publish(
+	array(
+		'track'     => 12,
+		'label'     => 'Marketing Track',
+		'state'     => 'draft',
+		'preflight' => $refused_flight,
+		'checklist' => array(),
+		'can_make'  => true,
+		'url'       => '',
+		'flash'     => array(),
+	)
+);
+$refused_screen = ob_get_clean();
+
+ck( 'a refused preflight says so and offers no Publish button at all',
+    array( false !== strpos( $refused_screen, 'notice-error' ), false !== strpos( $refused_screen, '<code>Personal link</code>' ), false !== strpos( $refused_screen, 'Publish this track' ) ),
+    array( true, true, false ) );
+
+ob_start();
+WPCPM_Track_Builder_Screen::render_publish(
+	array(
+		'track'     => 12,
+		'label'     => 'Marketing Track',
+		'state'     => 'published',
+		'preflight' => WPCPM_Track_Publish::$flight,
+		'checklist' => array(),
+		'can_make'  => false,
+		'url'       => '',
+		'flash'     => array(),
+	)
+);
+$live_screen = ob_get_clean();
+
+ck( 'a live track offers the check and the way off the live site, and not Publish',
+    array(
+        false !== strpos( $live_screen, 'Check it against Airtable' ),
+        false !== strpos( $live_screen, 'Take it off the live site' ),
+        false !== strpos( $live_screen, 'Publish this track' ),
+    ),
+    array( true, true, false ) );
+
+ck( 'and with no schema token the columns are a list to make by hand',
+    false !== strpos( $live_screen, 'no schema token is configured' ), true );
+
+echo "\n=== The publish handlers, and their guards ===\n";
+
+$tool = new WPCPM_Track_Builder();
+
+$GLOBALS['can_manage']      = false;
+$GLOBALS['nonce']           = 'another-action';
+WPCPM_Track_Publish::$ran    = array();
+WPCPM_Track_Publish::$down   = array();
+WPCPM_Track_Publish::$ticked = array();
+$_POST                       = array( 'track' => 12, 'item' => 'automation' );
+
+// Decision 3.9: the capability is checked before the nonce. The nonce here is the wrong one, so
+// a handler that read it first would die saying so instead.
+foreach ( array( 'handle_publish', 'handle_unpublish', 'handle_verify', 'handle_tick', 'handle_untick' ) as $handler ) {
+	ck( sprintf( '%s dies on the capability before it reads the nonce', $handler ),
+	    outcome( array( $tool, $handler ) ), 'die: You do not have permission to manage the program.' );
+}
+
+ck( 'and none of them did anything',
+    array( WPCPM_Track_Publish::$ran, WPCPM_Track_Publish::$down, WPCPM_Track_Publish::$ticked ), array( array(), array(), array() ) );
+
+$GLOBALS['can_manage'] = true;
+$GLOBALS['nonce']      = WPCPM_Track_Builder::ACTION_PUBLISH;
+WPCPM_Track_Publish::$answer = null;
+
+ck( 'with both guards passed, Publish runs for the track that was posted',
+    array( outcome( array( $tool, 'handle_publish' ) ), WPCPM_Track_Publish::$ran ), array( 'redirect', array( array( 12, 5 ) ) ) );
+
+ck( 'and the flash says how many columns were created',
+    false !== strpos( WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['message'], '1 column was created' ), true );
+
+$GLOBALS['nonce']            = WPCPM_Track_Builder::ACTION_UNPUBLISH;
+WPCPM_Track_Publish::$answer = new WP_Error( 'wpcpm_track_in_use', '3 students are on this track in Airtable.' );
+
+ck( 'a refused unpublish comes back as the error it is, in the store\'s own words',
+    array( outcome( array( $tool, 'handle_unpublish' ) ), WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ] ),
+    array( 'redirect', array( 'status' => 'error', 'message' => '3 students are on this track in Airtable.' ) ) );
+
+$GLOBALS['nonce']            = WPCPM_Track_Builder::ACTION_TICK;
+WPCPM_Track_Publish::$answer = null;
+WPCPM_Track_Publish::$ticked = array();
+
+ck( 'a tick names the item it was pressed for',
+    array( outcome( array( $tool, 'handle_tick' ) ), WPCPM_Track_Publish::$ticked ), array( 'redirect', array( array( 'tick', 12, 'automation' ) ) ) );
+
+$GLOBALS['nonce']              = WPCPM_Track_Builder::ACTION_VERIFY;
+WPCPM_Track_Publish::$answer   = array( 'columns' => array( 'missing' => array( 'Brand new' ), 'wrong' => array() ), 'choices' => array( 'reports' => 'ok', 'students' => 'ok' ) );
+
+WPCPM_Flash::$set = array();
+$verify_outcome   = outcome( array( $tool, 'handle_verify' ) );
+
+// A column renamed in the base takes its answers with it, and the form keeps writing into a name
+// nothing reads, so the one thing this notice must do is name the column (7.4).
+ck( 'a verify that finds a column gone names it, as an error',
+    array( $verify_outcome, WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['status'], false !== strpos( WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['message'], 'Brand new' ) ),
+    array( 'redirect', 'error', true ) );
+
+WPCPM_Flash::$set            = array();
+WPCPM_Track_Publish::$answer = array( 'columns' => array( 'missing' => array(), 'wrong' => array() ), 'choices' => array( 'reports' => 'ok', 'students' => 'ok' ) );
+outcome( array( $tool, 'handle_verify' ) );
+
+ck( 'and one that finds everything in place says so',
+    WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['status'], 'success' );
+
+WPCPM_Track_Publish::$answer = null;
+$_POST                       = array();
+
+
 printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
 exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-builder.php`
Expected: `PHP Fatal error: Uncaught Error: Call to undefined method WPCPM_Track_Builder_Screen::render_publish()`.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/tools/class-wpcpm-track-builder-screen.php b/includes/tools/class-wpcpm-track-builder-screen.php
index e643faf..1a497b2 100644
--- a/includes/tools/class-wpcpm-track-builder-screen.php
+++ b/includes/tools/class-wpcpm-track-builder-screen.php
@@ -126,6 +126,19 @@ final class WPCPM_Track_Builder_Screen {
 			);
 		}
 
+		// Publishing is a screen of its own: it has a preflight to read, a checklist to work
+		// through and, when a column is missing, a list to take to Airtable. A trashed track is
+		// not offered it, because the store refuses to publish out of the trash.
+		if ( '' !== $url && 'trash' !== $row['state'] ) {
+			printf(
+				'<a href="%1$s">%2$s</a> ',
+				esc_url( add_query_arg( 'wpcpm_publish', (int) $row['id'], $url ) ),
+				'draft' === $row['state']
+					? esc_html__( 'Publish', 'wpcredits-program-manager' )
+					: esc_html__( 'Publishing', 'wpcredits-program-manager' )
+			);
+		}
+
 		if ( ! empty( $row['stale'] ) ) {
 			self::render_button( WPCPM_Track_Builder::ACTION_REFRESH, (int) $row['id'], __( 'Refresh from the plugin', 'wpcredits-program-manager' ) );
 		}
@@ -139,6 +152,226 @@ final class WPCPM_Track_Builder_Screen {
 		}
 	}
 
+	/**
+	 * The publish screen: what would happen, what a person has to do, and the button.
+	 *
+	 * @param array $args `track`, `label`, `state`, `preflight`, `checklist`, `can_make` (whether
+	 *                    a schema token is configured), the screen's `url` and the `flash`.
+	 */
+	public static function render_publish( array $args ) {
+		$track     = isset( $args['track'] ) ? (int) $args['track'] : 0;
+		$label     = isset( $args['label'] ) ? (string) $args['label'] : '';
+		$state     = isset( $args['state'] ) ? (string) $args['state'] : '';
+		$flight    = isset( $args['preflight'] ) && is_array( $args['preflight'] ) ? $args['preflight'] : array();
+		$checklist = isset( $args['checklist'] ) && is_array( $args['checklist'] ) ? $args['checklist'] : array();
+		$can_make  = ! empty( $args['can_make'] );
+		$url       = isset( $args['url'] ) ? (string) $args['url'] : '';
+
+		self::render_notice( isset( $args['flash'] ) && is_array( $args['flash'] ) ? $args['flash'] : array() );
+
+		echo '<h2>';
+		printf(
+			/* translators: %s: the track's name. */
+			esc_html__( 'Publishing %s', 'wpcredits-program-manager' ),
+			esc_html( $label )
+		);
+		echo '</h2>';
+
+		if ( '' !== $url ) {
+			printf( '<p><a href="%1$s">%2$s</a></p>', esc_url( $url ), esc_html__( 'Back to the track list', 'wpcredits-program-manager' ) );
+		}
+
+		self::render_findings( $flight );
+		self::render_columns( $flight, $can_make );
+		self::render_checklist( $checklist, $track );
+		self::render_publish_actions( $flight, $state, $track );
+	}
+
+	/**
+	 * What the preflight refused and what it only warned about.
+	 *
+	 * @param array $flight The preflight's answer.
+	 * @return void
+	 */
+	private static function render_findings( array $flight ) {
+		foreach ( array( 'refusals', 'warnings' ) as $kind ) {
+			$findings = isset( $flight[ $kind ] ) ? (array) $flight[ $kind ] : array();
+
+			if ( array() === $findings ) {
+				continue;
+			}
+
+			$lede = 'refusals' === $kind
+				? esc_html__( 'This track cannot be published yet:', 'wpcredits-program-manager' )
+				: esc_html__( 'Worth knowing before you publish:', 'wpcredits-program-manager' );
+
+			printf(
+				'<div class="notice notice-%1$s inline"><p><strong>%2$s</strong></p><ul class="wpcpm-tracks__findings">',
+				'refusals' === $kind ? 'error' : 'warning',
+				$lede // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two escaped literals above.
+			);
+
+			foreach ( $findings as $finding ) {
+				$column = isset( $finding['column'] ) ? (string) $finding['column'] : '';
+
+				echo '<li>';
+
+				if ( '' !== $column ) {
+					printf( '<code>%s</code> ', esc_html( $column ) );
+				}
+
+				echo esc_html( isset( $finding['message'] ) ? (string) $finding['message'] : '' );
+				echo '</li>';
+			}
+
+			echo '</ul></div>';
+		}
+	}
+
+	/**
+	 * The columns publishing would create, or the list to make by hand.
+	 *
+	 * @param array $flight   The preflight's answer.
+	 * @param bool  $can_make Whether a schema token is configured.
+	 * @return void
+	 */
+	private static function render_columns( array $flight, $can_make ) {
+		$create = isset( $flight['columns']['create'] ) ? (array) $flight['columns']['create'] : array();
+
+		echo '<h3>' . esc_html__( 'Columns', 'wpcredits-program-manager' ) . '</h3>';
+
+		if ( array() === $create ) {
+			echo '<p>' . esc_html__( 'Every column this track writes to is already in the base.', 'wpcredits-program-manager' ) . '</p>';
+
+			return;
+		}
+
+		echo '<p>';
+
+		if ( $can_make ) {
+			esc_html_e( 'Publishing creates these columns on Students Reports, one at a time:', 'wpcredits-program-manager' );
+		} else {
+			esc_html_e( 'These columns are missing, and no schema token is configured, so somebody has to create them in Airtable first. Publish again once they are there.', 'wpcredits-program-manager' );
+		}
+
+		echo '</p><ul class="wpcpm-tracks__columns">';
+
+		foreach ( $create as $column ) {
+			printf( '<li><code>%s</code></li>', esc_html( (string) $column ) );
+		}
+
+		echo '</ul>';
+
+		$fields = isset( $flight['fields']['after'] ) ? (int) $flight['fields']['after'] : 0;
+
+		if ( $fields > 0 ) {
+			echo '<p class="wpcpm-tracks__count">';
+			echo esc_html(
+				sprintf(
+					/* translators: %d: how many columns the table would hold afterward. */
+					__( 'The table would hold %d columns afterward.', 'wpcredits-program-manager' ),
+					$fields
+				)
+			);
+			echo '</p>';
+		}
+	}
+
+	/**
+	 * The three things the site cannot do, each with its tick.
+	 *
+	 * @param array $checklist What `WPCPM_Track_Publish::checklist()` answered.
+	 * @param int   $track     The track.
+	 * @return void
+	 */
+	private static function render_checklist( array $checklist, $track ) {
+		if ( array() === $checklist ) {
+			return;
+		}
+
+		echo '<h3>' . esc_html__( 'What the site cannot do', 'wpcredits-program-manager' ) . '</h3>';
+		echo '<p>' . esc_html__( 'The site cannot see an Airtable automation either way, so none of these stops a track being published. The track list counts them until they are ticked.', 'wpcredits-program-manager' ) . '</p>';
+		echo '<ul class="wpcpm-tracks__checklist">';
+
+		foreach ( $checklist as $item => $entry ) {
+			printf( '<li class="wpcpm-tracks__item%s">', empty( $entry['ticked'] ) ? '' : ' wpcpm-tracks__item--done' );
+			printf( '<strong>%s</strong>', esc_html( (string) $entry['label'] ) );
+			printf( '<span class="wpcpm-tracks__detail">%s</span>', esc_html( (string) $entry['detail'] ) );
+
+			if ( ! empty( $entry['ticked'] ) ) {
+				$who = get_userdata( (int) $entry['by'] );
+
+				echo '<span class="wpcpm-tracks__ticked">';
+				printf(
+					/* translators: 1: a person's name, 2: a date. */
+					esc_html__( 'Ticked by %1$s on %2$s.', 'wpcredits-program-manager' ),
+					esc_html( $who ? $who->display_name : __( 'somebody', 'wpcredits-program-manager' ) ),
+					esc_html( wp_date( 'j F Y', (int) $entry['at'] ) )
+				);
+				echo '</span>';
+			}
+
+			self::render_tick(
+				empty( $entry['ticked'] ) ? WPCPM_Track_Builder::ACTION_TICK : WPCPM_Track_Builder::ACTION_UNTICK,
+				$track,
+				(string) $item,
+				empty( $entry['ticked'] ) ? __( 'I have done this', 'wpcredits-program-manager' ) : __( 'Undo', 'wpcredits-program-manager' )
+			);
+
+			echo '</li>';
+		}
+
+		echo '</ul>';
+	}
+
+	/**
+	 * Publish, unpublish and verify, as the track's state allows.
+	 *
+	 * @param array  $flight The preflight's answer.
+	 * @param string $state  The track's state.
+	 * @param int    $track  The track.
+	 * @return void
+	 */
+	private static function render_publish_actions( array $flight, $state, $track ) {
+		echo '<p class="wpcpm-list__actions">';
+
+		if ( ! empty( $flight['ready'] ) && in_array( $state, array( 'draft', 'changed' ), true ) ) {
+			self::render_button(
+				WPCPM_Track_Builder::ACTION_PUBLISH,
+				$track,
+				'changed' === $state
+					? __( 'Publish the changes', 'wpcredits-program-manager' )
+					: __( 'Publish this track', 'wpcredits-program-manager' )
+			);
+		}
+
+		if ( in_array( $state, array( 'published', 'changed' ), true ) ) {
+			self::render_button( WPCPM_Track_Builder::ACTION_VERIFY, $track, __( 'Check it against Airtable', 'wpcredits-program-manager' ) );
+			self::render_button( WPCPM_Track_Builder::ACTION_UNPUBLISH, $track, __( 'Take it off the live site', 'wpcredits-program-manager' ) );
+		}
+
+		echo '</p>';
+	}
+
+	/**
+	 * One checklist button, which carries the item as well as the track.
+	 *
+	 * @param string $action The action.
+	 * @param int    $track  The track.
+	 * @param string $item   The checklist item.
+	 * @param string $label  What the button reads.
+	 * @return void
+	 */
+	private static function render_tick( $action, $track, $item, $label ) {
+		printf( '<form method="post" action="%s" class="wpcpm-list__form">', esc_url( admin_url( 'admin-post.php' ) ) );
+		wp_nonce_field( $action );
+		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );
+		printf( '<input type="hidden" name="track" value="%d" />', (int) $track );
+		printf( '<input type="hidden" name="item" value="%s" />', esc_attr( $item ) );
+		printf( '<button type="submit" class="button">%s</button>', esc_html( $label ) );
+		echo '</form>';
+	}
+
 	/**
 	 * One track's properties, to read or to edit.
 	 *
diff --git a/includes/tools/class-wpcpm-track-builder.php b/includes/tools/class-wpcpm-track-builder.php
index bb26ddd..7213fc4 100644
--- a/includes/tools/class-wpcpm-track-builder.php
+++ b/includes/tools/class-wpcpm-track-builder.php
@@ -35,6 +35,21 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 	/** Put a built-in draft back to the seed the plugin ships. */
 	const ACTION_REFRESH = 'wpcpm_track_refresh';
 
+	/** Publish a track: create its columns, then put it live. */
+	const ACTION_PUBLISH = 'wpcpm_track_publish';
+
+	/** Take a published track off the live site. */
+	const ACTION_UNPUBLISH = 'wpcpm_track_unpublish';
+
+	/** Read the base and say whether a live track still matches it. */
+	const ACTION_VERIFY = 'wpcpm_track_verify';
+
+	/** Tick one checklist item. */
+	const ACTION_TICK = 'wpcpm_track_tick';
+
+	/** Take a tick back. */
+	const ACTION_UNTICK = 'wpcpm_track_untick';
+
 	/** Flash channel for this screen's outcomes. */
 	const FLASH = 'track-builder';
 
@@ -114,6 +129,11 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		add_action( 'admin_post_' . self::ACTION_SWITCH_DEFINITION, array( $this, 'handle_switch_definition' ) );
 		add_action( 'admin_post_' . self::ACTION_SWITCH_BUILTIN, array( $this, 'handle_switch_builtin' ) );
 		add_action( 'admin_post_' . self::ACTION_REFRESH, array( $this, 'handle_refresh' ) );
+		add_action( 'admin_post_' . self::ACTION_PUBLISH, array( $this, 'handle_publish' ) );
+		add_action( 'admin_post_' . self::ACTION_UNPUBLISH, array( $this, 'handle_unpublish' ) );
+		add_action( 'admin_post_' . self::ACTION_VERIFY, array( $this, 'handle_verify' ) );
+		add_action( 'admin_post_' . self::ACTION_TICK, array( $this, 'handle_tick' ) );
+		add_action( 'admin_post_' . self::ACTION_UNTICK, array( $this, 'handle_untick' ) );
 		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
 	}
 
@@ -265,6 +285,29 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 			return;
 		}
 
+		$publish = WPCPM_Request::id( 'wpcpm_publish' );
+
+		if ( $publish > 0 && is_array( WPCPM_Track_Store::get( $publish ) ) ) {
+			$held = WPCPM_Track_Store::get( $publish );
+
+			WPCPM_Track_Builder_Screen::render_publish(
+				array(
+					'track'     => $publish,
+					'label'     => isset( $held['label'] ) ? (string) $held['label'] : '',
+					'state'     => WPCPM_Track_Store::state( $publish ),
+					'preflight' => WPCPM_Track_Publish::preflight( $publish ),
+					'checklist' => WPCPM_Track_Publish::checklist( $publish ),
+					'can_make'  => WPCPM_Settings::has_schema_token(),
+					'url'       => $this->admin_url(),
+					'flash'     => $flash,
+				)
+			);
+
+			echo '</div>';
+
+			return;
+		}
+
 		if ( $track > 0 && is_array( WPCPM_Track_Store::get( $track ) ) ) {
 			WPCPM_Track_Builder_Screen::render_form(
 				array(
@@ -557,6 +600,163 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		return $last;
 	}
 
+	/**
+	 * Publish a track from the screen.
+	 */
+	public function handle_publish() {
+		$this->verify( self::ACTION_PUBLISH );
+
+		$track = WPCPM_Request::posted_id( 'track' );
+		$done  = WPCPM_Track_Publish::run( $track, get_current_user_id() );
+
+		if ( is_wp_error( $done ) ) {
+			$this->redirect_back(
+				array(
+					'status'  => 'error',
+					'message' => $done->get_error_message(),
+				),
+				array( 'wpcpm_publish' => $track )
+			);
+		}
+
+		$made = count( $done['created'] );
+
+		$this->redirect_back(
+			array(
+				'status'  => 'success',
+				'message' => 0 === $made
+					? __( 'The track is live. Nothing had to be created in Airtable: every column was already there.', 'wpcredits-program-manager' )
+					: sprintf(
+						/* translators: %d: how many columns were created. */
+						_n( 'The track is live, and %d column was created in Airtable.', 'The track is live, and %d columns were created in Airtable.', $made, 'wpcredits-program-manager' ),
+						$made
+					),
+			),
+			array( 'wpcpm_publish' => $track )
+		);
+	}
+
+	/**
+	 * Take a track off the live site.
+	 */
+	public function handle_unpublish() {
+		$this->verify( self::ACTION_UNPUBLISH );
+
+		$track = WPCPM_Request::posted_id( 'track' );
+
+		$this->report(
+			WPCPM_Track_Publish::take_down( $track, get_current_user_id() ),
+			__( 'The track is a draft again. Nothing was changed in Airtable, and its status is still in "Currently mentoring".', 'wpcredits-program-manager' )
+		);
+	}
+
+	/**
+	 * Check a live track against the base.
+	 */
+	public function handle_verify() {
+		$this->verify( self::ACTION_VERIFY );
+
+		$track = WPCPM_Request::posted_id( 'track' );
+		$seen  = WPCPM_Track_Publish::verify( $track );
+
+		if ( is_wp_error( $seen ) ) {
+			$this->redirect_back(
+				array(
+					'status'  => 'error',
+					'message' => $seen->get_error_message(),
+				),
+				array( 'wpcpm_publish' => $track )
+			);
+		}
+
+		$this->redirect_back( self::verified( $seen ), array( 'wpcpm_publish' => $track ) );
+	}
+
+	/**
+	 * What a verify found, as a notice.
+	 *
+	 * @param array $seen What `WPCPM_Track_Publish::verify()` answered.
+	 * @return array
+	 */
+	private static function verified( array $seen ) {
+		$trouble = array_merge( $seen['columns']['missing'], $seen['columns']['wrong'] );
+
+		if ( array() !== $trouble ) {
+			return array(
+				'status'  => 'error',
+				'message' => sprintf(
+					/* translators: %s: a list of column names. */
+					__( 'These columns are missing from the base, or are no longer the type this track needs: %s. A column renamed in Airtable takes its answers with it, so students would be writing into nothing.', 'wpcredits-program-manager' ),
+					implode( ', ', $trouble )
+				),
+			);
+		}
+
+		if ( 'ok' !== $seen['choices']['reports'] || 'ok' !== $seen['choices']['students'] ) {
+			return array(
+				'status'  => 'error',
+				'message' => __( 'The track\'s status is not a choice on both tables, so students on it are not synced. Checklist item 3 is the one to do.', 'wpcredits-program-manager' ),
+			);
+		}
+
+		return array(
+			'status'  => 'success',
+			'message' => __( 'Every column this track writes to is still in the base, with the type it needs, and its status is a choice on both tables.', 'wpcredits-program-manager' ),
+		);
+	}
+
+	/**
+	 * Tick one checklist item.
+	 */
+	public function handle_tick() {
+		$this->verify( self::ACTION_TICK );
+
+		$this->ticked( true );
+	}
+
+	/**
+	 * Take one tick back.
+	 */
+	public function handle_untick() {
+		$this->verify( self::ACTION_UNTICK );
+
+		$this->ticked( false );
+	}
+
+	/**
+	 * Record a tick either way, and say so.
+	 *
+	 * @param bool $on Whether it is now ticked.
+	 * @return void
+	 */
+	private function ticked( $on ) {
+		$track = WPCPM_Request::posted_id( 'track' );
+		$item  = WPCPM_Request::posted_text( 'item' );
+		$done  = $on
+			? WPCPM_Track_Publish::tick( $track, $item, get_current_user_id() )
+			: WPCPM_Track_Publish::untick( $track, $item, get_current_user_id() );
+
+		if ( is_wp_error( $done ) ) {
+			$this->redirect_back(
+				array(
+					'status'  => 'error',
+					'message' => $done->get_error_message(),
+				),
+				array( 'wpcpm_publish' => $track )
+			);
+		}
+
+		$this->redirect_back(
+			array(
+				'status'  => 'success',
+				'message' => $on
+					? __( 'Ticked, and recorded against your name.', 'wpcredits-program-manager' )
+					: __( 'The tick is taken back.', 'wpcredits-program-manager' ),
+			),
+			array( 'wpcpm_publish' => $track )
+		);
+	}
+
 	/**
 	 * The capability, then the nonce, before a handler does anything.
 	 *
diff --git a/includes/tracks/class-wpcpm-track-publish.php b/includes/tracks/class-wpcpm-track-publish.php
index b69e11c..b6960ee 100644
--- a/includes/tracks/class-wpcpm-track-publish.php
+++ b/includes/tracks/class-wpcpm-track-publish.php
@@ -13,7 +13,7 @@ defined( 'ABSPATH' ) || exit;
  * The definition's own rules belong to `WPCPM_Track_Store::check()`, the same call the editor
  * makes, so the two can never give different answers (T2b's decision 2). What is added here is
  * everything only Airtable can answer: whether a column exists and what type it is, how many
- * columns the table would hold afterwards, and whether the track's status is a `Status` choice.
+ * columns the table would hold afterward, and whether the track's status is a `Status` choice.
  */
 final class WPCPM_Track_Publish {
 
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-builder.php`
Expected: `ALL PASS (74 checks)`

- [ ] **Step 5: Run everything**

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
bash bin/check-standards.sh 2>&1 | tail -1
```

Expected: silent, clean, and `85 warnings, no errors.`

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Track Builder T2c: the publish screen and its five handlers"
```

---

### Task 10: The publish screen's stylesheet

**Files:**
- Modify: `assets/css/track-builder.css`

**Interfaces:**
- Consumes: The classes Task 9's markup emits.
- Produces: Rules for `wpcpm-tracks__findings`, `__columns`, `__checklist`, `__item`, `__item--done`, `__detail`, `__ticked` and `__count`.

T2b's whole-branch review blocked on a screen that emitted bespoke classes with no rules anywhere, so the same must not happen twice. The sheet is already enqueued on this screen by T2b; this only adds to it.

- [ ] **Step 1: Write the code**

```diff
diff --git a/assets/css/track-builder.css b/assets/css/track-builder.css
index b11101e..44127fa 100644
--- a/assets/css/track-builder.css
+++ b/assets/css/track-builder.css
@@ -36,3 +36,52 @@
 .wpcpm-tracks__course {
 	font-size: 13px;
 }
+
+/* The publish screen. The findings and the columns are core's own notice and list shapes, so only
+   the parts core does not draw are set here: the run of checklist items, and the quiet lines
+   under each one. */
+/* The refusals and the warnings sit inside core's own notice, which already draws the box and the
+   color; the list inside it only needs its bullets back, since core's notice strips them. */
+.wpcpm-tracks__findings {
+	list-style: disc;
+	margin: 0 0 0 20px;
+}
+
+.wpcpm-tracks__checklist {
+	margin: 0;
+	max-width: 48rem;
+}
+
+.wpcpm-tracks__item {
+	border-left: 3px solid #dcdcde;
+	margin: 0 0 12px;
+	padding: 0 0 0 12px;
+}
+
+/* Done, in the same green the rest of the plugin gives a finished thing. */
+.wpcpm-tracks__item--done {
+	border-left-color: #00a32a;
+}
+
+.wpcpm-tracks__detail,
+.wpcpm-tracks__ticked,
+.wpcpm-tracks__count {
+	color: #646970;
+	display: block;
+	font-size: 13px;
+}
+
+/* 8px under the heading it belongs to and 12px above the button that acts on it, so each item
+   reads as one block rather than three loose lines. */
+.wpcpm-tracks__detail {
+	margin: 4px 0 8px;
+}
+
+.wpcpm-tracks__ticked {
+	margin: 0 0 8px;
+}
+
+.wpcpm-tracks__columns code {
+	display: inline-block;
+	margin: 2px 0;
+}
```

- [ ] **Step 2: Run everything**

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
bash bin/check-standards.sh 2>&1 | tail -1
```

Expected: silent, clean, and `85 warnings, no errors.`

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "Track Builder T2c: the publish screen's stylesheet"
```

---
### Task 11: The first real column, on a base nobody needs

**Files:** none. This task runs the branch against a real Airtable base and a real WordPress, and writes down what it saw.

Every rule above is held against a stood-in Airtable. Nothing so far has proved that `create_field()`'s body is the one Airtable accepts, that its 422 for a taken name is the shape the resume reads, or that a column created from a question is the column a student's answer lands in. This is the task that proves it, and it is the reason the product owner mints the token (decision 16).

**Before anything:** the product owner mints a token with `schema.bases:write` and pastes it in themselves. It is never pasted into a chat message, a commit, a document or a test fixture. Nobody else handles it.

- [ ] **Step 1: Make a base nobody needs**

Create a new Airtable base for this check, with a table named `Students Reports` carrying a `Status` single select and a `Name` text column. **Never `appIzQKfwTn5dyPVp`**, the live WordPress Credits base, where a test column could not be removed once it existed.

- [ ] **Step 2: Build the zip and stand up a throwaway site**

```bash
bash bin/build
studio create --name wpcpm-t2c-check --path ~/Studio/wpcpm-t2c-check
studio start --path ~/Studio/wpcpm-t2c-check
```

Install the zip through that site's own WP-CLI and activate the plugin. The product owner's own Studio site, `My WordPress Website`, is never started, read, installed into or deleted.

- [ ] **Step 3: Point it at the throwaway base**

The product owner sets the two tokens and the base on that site, typing the values themselves:

```bash
wp option patch update wpcpm_settings api_token 'PASTE_THE_EVERYDAY_TOKEN'
wp option patch update wpcpm_settings schema_token 'PASTE_THE_SCHEMA_TOKEN'
wp option patch update wpcpm_settings base_id 'PASTE_THE_THROWAWAY_BASE_ID'
wp option patch update wpcpm_settings reports_table 'PASTE_THE_TABLE_ID'
```

- [ ] **Step 4: Publish a duplicate of a built-in track**

In wp-admin, open the Track Builder, duplicate the Developer Track as "Marketing Track" with the key `marketing`, then open its publish screen. Write down what the preflight says before pressing anything.

- [ ] **Step 5: Press Publish, and read the base**

Expected, and each one written down as seen:

1. The columns the preflight listed appear on `Students Reports` in Airtable, each of the type the question needs: a number's precision matches its step, the one select carries its three choices in order, the checkbox is the check icon in green.
2. `Main Contribution Team` is **not** created: the team control never asks for a column.
3. The track's row in the list reads Published, and the log names the columns created, with who and when.
4. The `Status` choice is **not** added, and the checklist still shows item 3 unticked, because no token can add a choice to a single select (decision 15).

- [ ] **Step 6: Prove the resume**

Rename one of the created columns in Airtable, then press Publish again on the same track. Expected: the run creates the renamed column afresh rather than stopping, because the recorded landing is the run's, and the column it cannot find is simply created again. Write down what happened either way: this is the one step most likely to disagree with the suite.

- [ ] **Step 7: Prove the by-hand path**

```bash
wp option patch update wpcpm_settings schema_token ''
```

Duplicate the track again under another name and open its publish screen. Expected: the columns are listed for somebody to create by hand, with no Publish button, and the notice says no schema token is configured.

- [ ] **Step 8: Verify, then unpublish**

Press "Check it against Airtable" on the live track: expected, every column still there and the status not yet a choice. Then press "Take it off the live site": expected, it goes back to draft, and nothing in Airtable changes.

- [ ] **Step 9: Put everything back**

Delete the throwaway Studio site and the throwaway Airtable base. The product owner revokes the schema token if they do not want it to keep existing. Confirm `studio list` shows only their own site.

- [ ] **Step 10: Write it down**

Record every expectation above as met or not met, in the task's report. A step that disagreed with the suite is a finding about the plugin, not something to work around: report it rather than changing code to suit it.

---

### Task 12: Release 1.104.0

**Files:**
- Modify: `wpcredits-program-manager.php` (the `Version:` header and `WPCPM_VERSION`), `readme.txt` (`Stable tag:` and a changelog entry), `languages/wpcredits-program-manager.pot` (regenerated)

- [ ] **Step 1: Move the version.** `1.103.0` becomes `1.104.0` in the plugin header's `Version:` line, in `define( 'WPCPM_VERSION', ... )` and in `readme.txt`'s `Stable tag:`. Every other mention of 1.103.0 stays, including the changelog's own heading.

- [ ] **Step 2: Write the changelog entry**, first under `== Changelog ==` in `readme.txt`, above the previous entry and with one empty line after it:

```text
= 1.104.0 =

* A track is published from the Track Builder. Before anything happens the screen says what would: which columns Airtable is missing, which are ready, what the table would come to, and anything that would stop the publish, in the same words the editor uses.
* With a schema token configured, publishing creates the columns a track's questions name, one at a time, and a run stopped by Airtable carries on from where it left off when it is pressed again. Without one, the screen lists the columns for somebody to create by hand and waits for them.
* What the site cannot do is a checklist of three, each ticked by whoever did it and recorded with their name: the reports automation, the welcome email, and the two Status choices no token can add. Ticking the first is what lets an institution import offer the track.
* A live track can be checked against Airtable at any time, which catches a column renamed in the base before students write into nothing, and taken off the live site once nobody is on it.
* Publishing a built-in track no longer puts its status back into "Currently mentoring", so one taken out in Settings stays out.
* The track list counts the students on every track in one pass rather than one per row, and saving the settings compiles from the settings screen rather than from anything else that saves them.
```

- [ ] **Step 3: Regenerate the translation template.** `sh bin/make-pot.sh`. Expected: `Success: POT file successfully generated.` and a header reading `Project-Id-Version: WPCredits Program Manager 1.104.0`.

- [ ] **Step 4: Run everything.** Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 5: Build the zip and read it back.**

```bash
bash bin/build
unzip -p ../wpcredits-program-manager.zip wpcredits-program-manager/wpcredits-program-manager.php | grep "Version:"
unzip -Z1 ../wpcredits-program-manager.zip | grep -cE '^wpcredits-program-manager/(bin|docs)/'
```

Expected: `Version:           1.104.0` and `0`.

- [ ] **Step 6: Commit.**

```bash
git add wpcredits-program-manager.php readme.txt languages/wpcredits-program-manager.pot
git commit -m "Track Builder T2c: 1.104.0"
```

- [ ] **Step 7: Merge and mirror, on the product owner's choice.** Merge `track-builder-t2c` into `main` and run the battery on the result. Then push the source to the public mirror: pull the `WordPress/WPCredits` clone at `~/GitHub/Plugins/WPCredits-Tracker-mirror` (branch `trunk`), rsync the plugin into its `Education/WordPress Education Dashboard/wpcredits-program-manager/` with `rsync -a --delete --exclude '.git/' --exclude '.superpowers/' --exclude '.DS_Store' --exclude 'node_modules/' --exclude '*.zip' --exclude '.*.swp'`, update the version in that folder's `README.md` table, scan the added lines for keys, record IDs, email addresses and names, read `git diff --stat`, then commit and push as two separate steps.

- [ ] **Step 8: Deploy only on the product owner's yes.** Ask first. On a yes, follow the deploy recorded for `wordpresseducation.org`: stream the zip over `ssh wpcredits-dashboard`, check its md5 on arrival, install it as a step of its own, read the version back, and purge the edge cache. Before the install and after it, run one read-only check of what a person sees: the program map, the Programs running card drawn as a Program Administrator, and the Student Report Card drawn as the TEST students, with version strings and relative times normalized. The two runs must be identical.

**The live site has no schema token, and does not need one to take this release.** Publishing there is the product owner's to do when they choose, with the token they mint for the real base. Until then every track stays exactly as it is: this release changes nothing about what students see.

---

## What T2c leaves for T3

- **The question editor at full parity**, forks, warnings, Learn lessons, preview and history, and the Track Builder section of `docs/sections/32-admin-tools.md` (followed by `bin/build-docs.php`).
- **A blank track and a track from a Learn course**, which is what makes Duplicate stop being the only way in.
- **`maxlength` is text only now** (open item 7), so T3's editor offers that box for a single-line control and for nothing else.
- **`wp wpcredits seed-tracks` should exit non-zero when a seed failed** (T2a's Task 5 review), which matters once it is the recovery path for a request that died mid-seed.
- **The run's record is per track, not per site.** A site publishing two tracks at once is held apart by the lock, not by the record, which is right today and worth remembering if publishing ever runs from cron.
- **Nothing reads a column's description.** `fetch_schema()` has carried them since before T1, and a track's questions could one day write theirs, so a person in Airtable sees what a column is for.
