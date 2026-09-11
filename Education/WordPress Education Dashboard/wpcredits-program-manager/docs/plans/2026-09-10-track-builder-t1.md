# Track Builder, phase T1: implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Lay the Track Builder's groundwork and ship it as plugin 1.100.0 with nothing changed on any page: the program map filtered for new tracks and carrying the Learn course IDs, the private `wpcpm_track` post type with a revisioned definition, the rules a definition must pass, the compiled options the live site reads, and the two places that kept track lists of their own asking the map instead.

**Architecture:** Four new classes in `includes/tracks/`. `WPCPM_Track_Palette` names the chip hues; `WPCPM_Track_Definition` is the shape, the rules and the compile; `WPCPM_Tracks` is the live site's reader (filters on the program map and the form, the chip CSS, the automation guard's list); `WPCPM_Track_Store` is the post type, the revisioned save, the compile and the uninstall sweep. The live site reads only compiled options, never a post, and with nothing compiled every filter returns what it was given. No screen ships in T1: the Track Builder screen is phase T2.

**Tech Stack:** PHP 7.4-compatible WordPress plugin (WordPress 6.5 floor); standalone suites under `bin/test-*.php` that stub WordPress at their top; `bash bin/check-standards.sh` (phpcs with the WordPress Coding Standards), `php bin/check-references.php`, `php bin/check-spelling.php`, `php bin/check-dead-annotations.php`; WordPress Studio's CLI, `studio`, for the one check on a real WordPress (Task 6).

**Spec:** `docs/specs/2026-09-10-track-builder-design.md`, approved by the product owner on 10 September 2026. T1 is the first row of its section 12, and every section or decision number below is that document's.

**Proven before it was written:** every line of code in this plan was run in a scratch copy of the plugin on 10 September 2026, with 1.99.3's two changed files in place: all 87 suites silent, the three static checks clean, phpcs with no error and no new warning. A check that fails while following this plan is a difference from that run, not a flaw to work around.

## Global Constraints

- **Start from `main` after 1.99.3 is committed.** 1.99.3 (the separate fix that put `Designer Track` into `WPCPM_Institutions::AUTOMATION_STATUSES`) was being made in another session while this plan was written, and Task 5's two diffs of `includes/modules/class-wpcpm-institutions.php` and `bin/test-unlinked-link.php` are against its versions. Before Task 1: `grep -c "'Developer Track', 'Designer Track' );" includes/modules/class-wpcpm-institutions.php` prints `1`, `grep "^Stable tag" readme.txt` prints `Stable tag: 1.99.3`, and `git status --short` prints nothing. Then `git switch -c track-builder-t1`.
- WordPress coding standards throughout: tabs, Yoda conditions, spaces inside parentheses, `array()` and never `[]`, strict `in_array()`; PHP 7.4 compatible (no `match`, no named arguments, no union types). Every string a person reads is US English. No em dash or en dash anywhere, in code, comments, docs or commit messages: a plain hyphen. Full product names ("Student Report Card", "Mentor Report Card", "Administrator Dashboard").
- **The battery stays silent after every task:** `for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done` prints nothing. `php bin/check-references.php`, `php bin/check-spelling.php` and `php bin/check-dead-annotations.php` report clean. `bash bin/check-standards.sh` prints no line containing ` ERROR `, and the count of warnings in its last line is no higher than the count it printed before Task 1 (85 on the scratch copy; measure it again on the branch and write the number in the Task 1 report).
- **Test first, every task:** write the check, run it, see it fail for the reason the step names, then write the code.
- **Nothing a person sees changes in T1.** With no `wpcpm_track` post published, every answer of `WPCPM_Program`, `WPCPM_Student_Report_Form::fields()`, the Programs running card and the Institutions Link control is the one 1.99.3 gave. `bin/test-tracks.php` holds this in its section "With nothing compiled, every answer is 1.99.3's".
- `bin/` and `docs/` never ship in the zip but are public on the mirror, and `includes/` ships: nothing personal in any of them, and no Airtable record ID (`rec` and fourteen characters) that is not an obvious placeholder.
- Nothing touches Airtable. Task 6 runs on a local WordPress Studio site only, and only after the product owner has said yes to creating one. The live site is touched in Task 7 only, and only on the product owner's yes.
- Version numbers move in Task 7 only: plugin 1.100.0, which `version_compare()` orders after 1.99.3 (a string sort would not). No theme release (decision 3.10).
- Comments explain why and name the decision or the bug behind a rule. Every commit message starts with "Track Builder T1:" and ends with the `Co-Authored-By:` trailer of whoever made it.

## File map

| File | Task | Responsibility |
| --- | --- | --- |
| `includes/class-wpcpm-program.php` | 1 | The program map: `track()` filtered, `states()`, `course_ids()` and `course_id()` |
| `includes/tracks/class-wpcpm-track-palette.php` | 2 | The seven chip hues and the one CSS rule each prints |
| `includes/tracks/class-wpcpm-track-definition.php` | 2 | A track as data: `normalize()`, `validate()`, `encode()`, `decode()`, `compile_fields()`, `row()` |
| `includes/tracks/class-wpcpm-tracks.php` | 3 | What the live site reads: the compiled options, the six filter callbacks, the chips, the automation list, the rules' context |
| `includes/tracks/class-wpcpm-track-store.php` | 4 | The `wpcpm_track` post type, the revisioned `create()` and `save()`, `compile()`, `delete_all()` |
| `wpcredits-program-manager.php`, `uninstall.php` | 4 | Load the four classes; boot the store and the reader; sweep on uninstall |
| `includes/modules/class-wpcpm-administrators-cards.php` | 5 | `tracks()` from the map, in place of the `TRACKS` constant |
| `includes/modules/class-wpcpm-institutions.php` | 5 | `automation_statuses()`: the pinned list plus the ticked tracks |
| `bin/test-track-definition.php`, `bin/test-tracks.php`, `bin/test-track-store.php` | 2, 3, 4 | The three new suites |
| `bin/test-student-program.php`, `bin/test-report-form.php`, `bin/test-roles.php`, `bin/test-administrators-dashboard.php`, `bin/test-unlinked-link.php` | 1, 3, 4, 5 | The existing suites the change reaches |
| `readme.txt`, `languages/wpcredits-program-manager.pot` | 7 | Version and changelog; the translation template |

---

### Task 1: The program map: a filtered track key, the two states, the Learn course IDs

**Files:**
- Modify: `includes/class-wpcpm-program.php` (`track()` and `badge()`, and three new methods)
- Test: `bin/test-student-program.php` (one block, above the closing summary)

**Interfaces:**
- Consumes: nothing new.
- Produces: `WPCPM_Program::track( $status )`, now passed through the filter `wpcpm_program_tracks` (status => key); `WPCPM_Program::states()`, returning `array( 'Paused' => 'paused', 'Pending graduation' => 'pending' )`; `WPCPM_Program::course_ids()`, status => Learn course post ID (int), filtered as `wpcpm_program_course_ids`; `WPCPM_Program::course_id( $status )`, an int, 0 for none.

- [ ] **Step 1: Write the failing check.** Apply this change to `bin/test-student-program.php`:

```diff
--- a/bin/test-student-program.php
+++ b/bin/test-student-program.php
@@ -697,6 +697,25 @@
 ck( 'and the added status has a target', WPCPM_Program::has_hours_target( 'In Sensei 25h' ), true );
 $GLOBALS['filters']['wpcpm_program_hours_targets'] = array();
 
+echo "\n=== The track key filter, the two states and the course IDs (1.100.0) ===\n";
+
+ck( 'the four built-in statuses keep their keys', array( WPCPM_Program::track( 'In Sensei' ), WPCPM_Program::track( 'In Sensei 50h' ), WPCPM_Program::track( 'Developer Track' ), WPCPM_Program::track( 'Designer Track' ) ), array( '150h', '50h', 'dev', 'design' ) );
+
+add_filter( 'wpcpm_program_tracks', function ( $tracks ) { $tracks['Research Track'] = 'research'; return $tracks; } );
+ck( 'a status given a key through wpcpm_program_tracks is a track under it, padding trimmed', WPCPM_Program::track( '  Research Track ' ), 'research' );
+ck( 'and badge() paints it with its own key', WPCPM_Program::badge( 'Research Track' ), 'research' );
+$GLOBALS['filters']['wpcpm_program_tracks'] = array();
+
+ck( 'the two states on no track, public for the Track Builder\'s status rule', WPCPM_Program::states(), array( 'Paused' => 'paused', 'Pending graduation' => 'pending' ) );
+ck( 'and badge() still paints them from there', array( WPCPM_Program::badge( 'Paused' ), WPCPM_Program::badge( 'Pending graduation' ) ), array( 'paused', 'pending' ) );
+
+ck( 'the Learn course post ID of each built-in track', array( WPCPM_Program::course_id( 'In Sensei' ), WPCPM_Program::course_id( 'In Sensei 50h' ), WPCPM_Program::course_id( 'Developer Track' ), WPCPM_Program::course_id( 'Designer Track' ) ), array( 297853, 322343, 402893, 403425 ) );
+ck( 'a status with no course has 0, not a notice', WPCPM_Program::course_id( 'Graduate' ), 0 );
+ck( 'every status with a course link has a course ID, and no other', array_keys( WPCPM_Program::course_ids() ), array_keys( WPCPM_Program::courses() ) );
+add_filter( 'wpcpm_program_course_ids', function ( $ids ) { $ids['Research Track'] = '500001'; return $ids; } );
+ck( 'the filter can add a course, and course_id() hands back an integer', WPCPM_Program::course_id( 'Research Track' ), 500001 );
+$GLOBALS['filters']['wpcpm_program_course_ids'] = array();
+
 printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
 
 exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run it and see it fail.** `php bin/test-student-program.php`. Expected: `FAIL a status given a key through wpcpm_program_tracks is a track under it, padding trimmed` (got `''`: nothing filters `track()` yet), then a fatal, `Call to undefined method WPCPM_Program::states()`.

- [ ] **Step 3: Write the code.** Apply this change to `includes/class-wpcpm-program.php`:

```diff
--- a/includes/class-wpcpm-program.php
+++ b/includes/class-wpcpm-program.php
@@ -118,6 +118,49 @@
 	}
 
 	/**
+	 * The Learn WordPress course post ID for a status.
+	 *
+	 * @param string $status Airtable status.
+	 * @return int Post ID, or 0 when the status has no course.
+	 */
+	public static function course_id( $status ) {
+		$status = trim( (string) $status );
+		$ids    = self::course_ids();
+
+		return isset( $ids[ $status ] ) ? (int) $ids[ $status ] : 0;
+	}
+
+	/**
+	 * The status → Learn course post ID map.
+	 *
+	 * The same four courses as `courses()`, by the ID Learn gives each: `wp/v2/courses?slug=`
+	 * on learn.wordpress.org answers it for the slug in the course's address (read 10 September
+	 * 2026). Kept beside the links rather than derived from them, because the join with Learn is
+	 * by ID - the public course structure at `sensei-internal/v1/course-structure/<id>` and the
+	 * Learn Link design both key on it - and a request to Learn on every page to turn a link
+	 * into an ID is a request nothing should wait for.
+	 *
+	 * @return array<string, int>
+	 */
+	public static function course_ids() {
+		$ids = array(
+			self::STATUS_150H   => 297853,
+			self::STATUS_50H    => 322343,
+			self::STATUS_DEV    => 402893,
+			self::STATUS_DESIGN => 403425,
+		);
+
+		/**
+		 * Filter the Learn WordPress course post ID for each Airtable status.
+		 *
+		 * The Track Builder adds its tracks' courses here (1.100.0).
+		 *
+		 * @param array<string, int> $ids Status to course post ID.
+		 */
+		return (array) apply_filters( 'wpcpm_program_course_ids', $ids );
+	}
+
+	/**
 	 * How many hours a status is worked towards, or 0 for no target.
 	 *
 	 * @param string $status Airtable status.
@@ -207,7 +250,8 @@
 	 * needed and nothing has to be kept in step. A fourth track is one entry in this map.
 	 *
 	 * @param string $status Airtable status.
-	 * @return string `150h`, `50h`, `dev`, `design`, or an empty string for a finished state.
+	 * @return string `150h`, `50h`, `dev`, `design`, a Track Builder track's key, or an empty string
+	 *                for a finished state.
 	 */
 	public static function track( $status ) {
 		$tracks = array(
@@ -219,9 +263,22 @@
 			self::STATUS_DESIGN => 'design',
 		);
 
+		/**
+		 * Filter the short key each Airtable status is a track under.
+		 *
+		 * The Track Builder adds its tracks here (1.100.0), beside `wpcpm_program_labels`. The
+		 * key is what `WPCPM_Student_Report_Form::fields()`, `badge()` and the Administrator
+		 * Dashboard's tiles are keyed on, so a status that reaches `labels()` without reaching
+		 * this map is a track with no form of its own: `fields()` answers the 150-hour set for a
+		 * key it does not know.
+		 *
+		 * @param array<string, string> $tracks Status to track key.
+		 */
+		$tracks = (array) apply_filters( 'wpcpm_program_tracks', $tracks );
+
 		$status = trim( (string) $status );
 
-		return isset( $tracks[ $status ] ) ? $tracks[ $status ] : '';
+		return isset( $tracks[ $status ] ) ? (string) $tracks[ $status ] : '';
 	}
 
 	/**
@@ -243,13 +300,27 @@
 			return '150h' === $track ? 'sensei' : $track;
 		}
 
-		$others = array(
-			'Paused'              => 'paused',
-			'Pending graduation'  => 'pending',
-		);
+		$others = self::states();
 
 		$status = trim( (string) $status );
 
 		return isset( $others[ $status ] ) ? $others[ $status ] : '';
 	}
+
+	/**
+	 * The two states on no track, with the chip each is painted.
+	 *
+	 * Paused and Pending graduation are still their mentor's students, so they are painted apart
+	 * from both a working track and the plain finished badge. Public since 1.100.0 because the
+	 * Track Builder refuses both as a track's status: a track called Paused would put a course
+	 * on somebody who has stopped.
+	 *
+	 * @return array<string, string> Status => badge modifier.
+	 */
+	public static function states() {
+		return array(
+			'Paused'             => 'paused',
+			'Pending graduation' => 'pending',
+		);
+	}
 }
```

- [ ] **Step 4: Run it and see it pass.** `php bin/test-student-program.php`. Expected: `ALL PASS (119 checks)`.

- [ ] **Step 5: Run the battery and the three static checks** (Global Constraints). Expected: silent and clean.

- [ ] **Step 6: Commit.**

```bash
git add includes/class-wpcpm-program.php bin/test-student-program.php
git commit -m "Track Builder T1: the program map gains a filtered track key, the two states and the Learn course IDs"
```

---

### Task 2: The palette and the definition

**Files:**
- Create: `includes/tracks/class-wpcpm-track-palette.php`
- Create: `includes/tracks/class-wpcpm-track-definition.php`
- Test: `bin/test-track-definition.php`

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces: `WPCPM_Track_Palette::HUES` (name => array of red, green, blue), `::is_hue( $hue ): bool`, `::badge_rule( $key, $hue ): string`. `WPCPM_Track_Definition` constants `SCHEMA_VERSION`, `TYPES`, `GROUPS`, `RESERVED_KEYS`, `TRACK_PROPERTIES`, `QUESTION_PROPERTIES`, `AUTHORING`, `FLAGS`, `TEXTS`, `TEAM_COLUMN`, `WRITABLE_TYPES`, `MAX_STATUS`, `MAX_COLUMN`, and methods `normalize( array $definition ): array`, `validate( array $definition, array $context = array() ): array` (a list of `code`, `where`, `message`; empty when the definition may be stored; `$context` keys `tracks`, `refused_statuses`, `reserved_columns`, `locked`), `encode( array $definition ): string`, `decode( $json ): array|null`, `compile_fields( array $definition ): array` and `row( array $definition, $post_id, $source = 'definition', $automation = false ): array` (keys `key`, `label`, `course_url`, `course_id`, `hours`, `hue`, `source`, `automation`, `post`).

The rules come from what the four hand-written forms actually use: a probe of their 115 questions found `maxlength` on `text` only, `stack` only inside a `row`, every flag a literal `true`, and no column name that is a number or ends in a space. Task 3 adds the check that keeps it so.

- [ ] **Step 1: Write the failing suite.** Create `bin/test-track-definition.php`:

```php
<?php
/**
 * A program track as data: the Track Builder's palette and definition (1.100.0).
 *
 * Every rule is a function of its arguments, so each is asked here directly: a definition every
 * rule accepts, then one thing broken at a time, each check expecting exactly the one code its
 * rule gives. What the rules must never refuse - the four hand-written forms - is held in
 * bin/test-report-form.php, which loads the form.
 *
 * Run from the plugin root:  php bin/test-track-definition.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

function __( $s, $d = null ) { return $s; }
function wp_json_encode( $v, $o = 0 ) { return json_encode( $v, $o ); }
function wp_slash( $v ) { return is_array( $v ) ? array_map( 'wp_slash', $v ) : addslashes( (string) $v ); }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v ); }

require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-palette.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';

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

/** The codes of what `validate()` refused, in the order it found them. */
function codes( array $errors ) {
	return array_column( $errors, 'code' );
}

/** A definition every rule accepts. Each check below breaks one thing about a copy of it. */
function valid() {
	return array(
		'schema_version'  => 1,
		'status'          => 'Marketing Track',
		'key'             => 'marketing',
		'label'           => 'Marketing Track',
		'course_url'      => 'https://learn.wordpress.org/course/wordpress-credits-marketing-track/',
		'learn_course_id' => 500001,
		'hours_target'    => 120,
		'hue'             => 'cyan',
		'questions'       => array(
			'Hours'                               => array( 'label' => 'Hours contributed', 'type' => 'number', 'step' => '1', 'min' => 0, 'max' => 10000, 'group' => 'hours', 'airtable_type' => 'number' ),
			'Practical: Campaign Brief - Notes'   => array( 'lead' => 'Practical: Write a Campaign Brief', 'label' => 'Your notes', 'type' => 'textarea', 'group' => 'project', 'airtable_type' => 'multilineText', 'learn_lesson_id' => 500101, 'why' => 'The base shortens the lesson name.' ),
			'Practical: Campaign Brief - Link'    => array( 'label' => 'A link to your brief', 'type' => 'url', 'group' => 'project', 'row' => 'brief', 'airtable_type' => 'url' ),
			'Practical: Campaign Brief - Channel' => array( 'label' => 'The channel you chose', 'type' => 'select', 'group' => 'project', 'options' => array( 'Newsletter', 'Social', 'Blog' ), 'row' => 'brief', 'stack' => true, 'airtable_type' => 'singleSelect' ),
			'Main Contribution Team'              => array( 'label' => 'Your contribution team', 'type' => 'team', 'group' => 'project', 'airtable_type' => 'multipleRecordLinks' ),
			'Alumni program: personal email'      => array( 'label' => 'A personal email', 'type' => 'email', 'group' => 'project', 'hide_from_institution' => true, 'airtable_type' => 'email' ),
			'Company '                            => array( 'label' => 'Your company', 'type' => 'text', 'group' => 'wrapup', 'maxlength' => 100, 'required' => true, 'airtable_type' => 'singleLineText' ),
		),
	);
}

$context = array(
	'tracks'           => array( 'In Sensei' => '150h', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev', 'Designer Track' => 'design' ),
	'refused_statuses' => array( 'Graduate', 'Dropped out', 'Paused', 'Pending graduation' ),
	'reserved_columns' => array( 'Name', 'Email', 'Status', 'Mentor', 'Educational institution', 'Internship Start Date', 'Internship End Date' ),
);

/** What `validate()` says about a copy of the valid definition with one change made to it. */
function refused( callable $change, array $context ) {
	$definition = valid();
	$change( $definition );

	return codes( WPCPM_Track_Definition::validate( $definition, $context ) );
}

/** A change that swaps the valid definition's questions for exactly these. */
function only( array $questions ) {
	return function ( &$d ) use ( $questions ) {
		$d['questions'] = $questions;
	};
}

echo "=== The palette ===\n";

ck( 'seven hues, the four built-in tracks\' among them', array_keys( WPCPM_Track_Palette::HUES ), array( 'blue', 'cyan', 'teal', 'green', 'red', 'pink', 'purple' ) );
ck( 'slate and amber are no hue: they paint the two states on no track', array( WPCPM_Track_Palette::is_hue( 'slate' ), WPCPM_Track_Palette::is_hue( 'amber' ), WPCPM_Track_Palette::is_hue( 5 ) ), array( false, false, false ) );
ck( 'the chip rule, in the shape dashboard.css gives its own chips', WPCPM_Track_Palette::badge_rule( 'marketing', 'cyan' ), '.wpcpm-badge--marketing{background:rgba(8,145,178,0.12);border-color:rgba(8,145,178,0.35);}' );
ck( 'no rule for a hue outside the palette', WPCPM_Track_Palette::badge_rule( 'marketing', '#ff0000' ), '' );
ck( 'and none for a key that could carry anything into the stylesheet', array( WPCPM_Track_Palette::badge_rule( 'Marketing', 'cyan' ), WPCPM_Track_Palette::badge_rule( 'a}b{', 'cyan' ), WPCPM_Track_Palette::badge_rule( 'm', 'cyan' ) ), array( '', '', '' ) );

echo "\n=== A definition every rule accepts ===\n";

ck( 'the valid definition passes', WPCPM_Track_Definition::validate( valid(), $context ), array() );
ck( 'and passes with no context at all', WPCPM_Track_Definition::validate( valid() ), array() );

echo "\n=== The track's rules, one at a time ===\n";

ck( 'a property this version does not know', refused( function ( &$d ) { $d['colour'] = 'red'; }, $context ), array( 'unknown_property' ) );
ck( 'a schema version the site does not read', refused( function ( &$d ) { $d['schema_version'] = 2; }, $context ), array( 'schema_version' ) );
ck( 'no status', refused( function ( &$d ) { $d['status'] = ''; }, $context ), array( 'status_empty' ) );
ck( 'a status over 100 characters', refused( function ( &$d ) { $d['status'] = str_repeat( 'a', 101 ); }, $context ), array( 'status_shape' ) );
ck( 'a status on two lines', refused( function ( &$d ) { $d['status'] = "Marketing\nTrack"; }, $context ), array( 'status_shape' ) );
ck( 'another track\'s status, whatever its case', refused( function ( &$d ) { $d['status'] = 'designer track'; }, $context ), array( 'status_taken' ) );
$curly                                    = $context;
$curly['tracks']["Writer\u{2019}s Track"] = 'writers';
ck( 'and whichever apostrophe it is typed with', refused( function ( &$d ) { $d['status'] = "writer's track"; }, $curly ), array( 'status_taken' ) );
ck( 'a status that already means a finished student', refused( function ( &$d ) { $d['status'] = 'Graduate'; }, $context ), array( 'status_refused' ) );
ck( 'or a paused one', refused( function ( &$d ) { $d['status'] = 'paused'; }, $context ), array( 'status_refused' ) );
$locked           = $context;
$locked['locked'] = array( 'status' => 'Marketing Track', 'key' => 'marketing' );
ck( 'a published track keeps its status', refused( function ( &$d ) { $d['status'] = 'Marketing Program'; }, $locked ), array( 'status_locked' ) );
ck( 'and its key', refused( function ( &$d ) { $d['key'] = 'marketing-2'; }, $locked ), array( 'key_locked' ) );
ck( 'a key with capitals', refused( function ( &$d ) { $d['key'] = 'Marketing'; }, $context ), array( 'key_shape' ) );
ck( 'a key of one character, or of twenty-one', array( refused( function ( &$d ) { $d['key'] = 'm'; }, $context ), refused( function ( &$d ) { $d['key'] = str_repeat( 'm', 21 ); }, $context ) ), array( array( 'key_shape' ), array( 'key_shape' ) ) );
ck( 'a key a chip already paints', refused( function ( &$d ) { $d['key'] = 'sensei'; }, $context ), array( 'key_reserved' ) );
ck( 'a built-in track\'s key', refused( function ( &$d ) { $d['key'] = 'design'; }, $context ), array( 'key_reserved' ) );
$research                             = $context;
$research['tracks']['Research Track'] = 'research';
ck( 'another Track Builder track\'s key', refused( function ( &$d ) { $d['key'] = 'research'; }, $research ), array( 'key_taken' ) );
$builtin = array(
	'tracks' => array( 'In Sensei' => '150h', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev' ),
	'locked' => array( 'status' => 'Designer Track', 'key' => 'design' ),
);
ck( 'a built-in track\'s own definition keeps its reserved key', refused( function ( &$d ) { $d['status'] = 'Designer Track'; $d['key'] = 'design'; }, $builtin ), array() );
ck( 'no name', refused( function ( &$d ) { $d['label'] = '  '; }, $context ), array( 'label_empty' ) );
ck( 'a course link that is not a Learn course', array( refused( function ( &$d ) { $d['course_url'] = 'http://learn.wordpress.org/course/marketing/'; }, $context ), refused( function ( &$d ) { $d['course_url'] = 'https://example.org/course/marketing/'; }, $context ) ), array( array( 'course_url' ), array( 'course_url' ) ) );
ck( 'no course link at all is fine', refused( function ( &$d ) { $d['course_url'] = ''; unset( $d['learn_course_id'] ); }, $context ), array() );
ck( 'a course ID that is not a whole number', array( refused( function ( &$d ) { $d['learn_course_id'] = -1; }, $context ), refused( function ( &$d ) { $d['learn_course_id'] = '500001'; }, $context ) ), array( array( 'course_id' ), array( 'course_id' ) ) );
ck( 'an hours target that is not a whole number of hours', array( refused( function ( &$d ) { $d['hours_target'] = -5; }, $context ), refused( function ( &$d ) { $d['hours_target'] = '150'; }, $context ) ), array( array( 'hours_target' ), array( 'hours_target' ) ) );
ck( 'no hours target is a track that counts none, and 0 says the same', array( refused( function ( &$d ) { unset( $d['hours_target'] ); }, $context ), refused( function ( &$d ) { $d['hours_target'] = 0; }, $context ) ), array( array(), array() ) );
ck( 'a hue outside the palette, a reserved one included, or none', array( refused( function ( &$d ) { $d['hue'] = 'slate'; }, $context ), refused( function ( &$d ) { unset( $d['hue'] ); }, $context ) ), array( array( 'hue' ), array( 'hue' ) ) );
ck( 'questions that are not a list of questions', refused( function ( &$d ) { $d['questions'] = 'none'; }, $context ), array( 'questions_shape' ) );
ck( 'an empty form is a blank track, not an error', refused( function ( &$d ) { $d['questions'] = array(); }, $context ), array() );

echo "\n=== A question's rules, one at a time ===\n";

$note = array( 'label' => 'Your notes', 'type' => 'textarea', 'group' => 'project' );

ck( 'a column name of spaces', refused( only( array( '   ' => $note ) ), $context ), array( 'column_shape' ) );
ck( 'a column name over 255 characters', refused( only( array( str_repeat( 'c', 256 ) => $note ) ), $context ), array( 'column_shape' ) );
ck( 'a column name that is a number alone', refused( only( array( '2024' => $note ) ), $context ), array( 'column_numeric' ) );
ck( 'a column the syncs own, which a student could then change', refused( only( array( 'Status' => $note ) ), $context ), array( 'column_reserved' ) );
ck( 'a column name ending in a space is taken as it is, like the base\'s own "Company "', refused( only( array( 'Company ' => $note ) ), $context ), array() );
ck( 'a question that is not a question', refused( only( array( 'Notes' => 'textarea' ) ), $context ), array( 'question_shape' ) );
ck( 'a property this version does not know', refused( only( array( 'Notes' => $note + array( 'colour' => 'red' ) ) ), $context ), array( 'unknown_property' ) );
ck( 'a control the form does not draw', refused( only( array( 'Notes' => array( 'type' => 'date' ) + $note ) ), $context ), array( 'type_unknown' ) );
ck( 'no words for the student', refused( only( array( 'Notes' => array( 'label' => '' ) + $note ) ), $context ), array( 'label_empty' ) );
ck( 'a group the form does not have', refused( only( array( 'Notes' => array( 'group' => 'extra' ) + $note ) ), $context ), array( 'group_unknown' ) );
ck( 'a hint that is not words', refused( only( array( 'Notes' => $note + array( 'help' => array( 'x' ) ) ) ), $context ), array( 'text_shape' ) );
ck( 'a flag that is not true', refused( only( array( 'Notes' => $note + array( 'required' => 1 ) ) ), $context ), array( 'flag_shape' ) );
ck( 'a row named like a sentence', refused( only( array( 'Notes' => $note + array( 'row' => 'Brief A' ) ) ), $context ), array( 'row_shape' ) );
ck( 'a stack outside a row', refused( only( array( 'Notes' => $note + array( 'stack' => true ) ) ), $context ), array( 'stack_without_row' ) );

$grade = array( 'label' => 'Final grade', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'max' => 100, 'group' => 'onboarding' );
ck( 'a number with every bound passes', refused( only( array( 'Grade' => $grade ) ), $context ), array() );
ck( 'a number with no step', refused( only( array( 'Grade' => array_diff_key( $grade, array( 'step' => 1 ) ) ) ), $context ), array( 'number_bounds' ) );
ck( 'a number whose lowest value is above its highest', refused( only( array( 'Grade' => array( 'min' => 101 ) + $grade ) ), $context ), array( 'number_bounds' ) );
ck( 'a number with a step of zero', refused( only( array( 'Grade' => array( 'step' => '0' ) + $grade ) ), $context ), array( 'number_bounds' ) );
ck( 'bounds on something that is not a number', refused( only( array( 'Notes' => $note + array( 'min' => 0 ) ) ), $context ), array( 'number_only' ) );

$pick = array( 'label' => 'Tool', 'type' => 'select', 'group' => 'project', 'options' => array( 'WordPress Studio', 'MAAMP', 'DevKinsta' ) );
ck( 'a select with its choices passes', refused( only( array( 'Tool' => $pick ) ), $context ), array() );
ck( 'a select with no choices', refused( only( array( 'Tool' => array_diff_key( $pick, array( 'options' => 1 ) ) ) ), $context ), array( 'options_shape' ) );
ck( 'a choice written twice', refused( only( array( 'Tool' => array( 'options' => array( 'A', 'A' ) ) + $pick ) ), $context ), array( 'options_shape' ) );
ck( 'an empty choice', refused( only( array( 'Tool' => array( 'options' => array( 'A', ' ' ) ) + $pick ) ), $context ), array( 'options_shape' ) );
ck( 'choices on something that is not a select', refused( only( array( 'Notes' => $note + array( 'options' => array( 'A' ) ) ) ), $context ), array( 'options_only' ) );
ck( 'a length limit on a link', refused( only( array( 'Link' => array( 'label' => 'Link', 'type' => 'url', 'group' => 'project', 'maxlength' => 100 ) ) ), $context ), array( 'maxlength_shape' ) );
ck( 'a length limit of nothing, or written as words', array( refused( only( array( 'Notes' => $note + array( 'maxlength' => 0 ) ) ), $context ), refused( only( array( 'Notes' => $note + array( 'maxlength' => '100' ) ) ), $context ) ), array( array( 'maxlength_shape' ), array( 'maxlength_shape' ) ) );
ck( 'monospace on a one-line box', refused( only( array( 'Name on the brief' => array( 'label' => 'Name', 'type' => 'text', 'group' => 'project', 'mono' => true ) ) ), $context ), array( 'mono_only' ) );

$team = array( 'label' => 'Your contribution team', 'type' => 'team', 'group' => 'project' );
ck( 'the team question writing any column but its own', refused( only( array( 'Second Team' => $team ) ), $context ), array( 'team_column' ) );
ck( 'the team asked twice', refused( only( array( 'Main Contribution Team' => $team, 'Second Team' => $team ) ), $context ), array( 'team_column', 'team_twice' ) );
ck( 'a computed Airtable column, which refuses every write', refused( only( array( 'Notes' => $note + array( 'airtable_type' => 'formula' ) ) ), $context ), array( 'airtable_type' ) );
ck( 'a linked-record column under anything but the team question', refused( only( array( 'Link' => array( 'label' => 'Link', 'type' => 'url', 'group' => 'project', 'airtable_type' => 'multipleRecordLinks' ) ) ), $context ), array( 'airtable_link' ) );
ck( 'and the team question under anything but a linked-record column', refused( only( array( 'Main Contribution Team' => $team + array( 'airtable_type' => 'singleLineText' ) ) ), $context ), array( 'airtable_link' ) );
ck( 'a Learn lesson ID that is not a whole number', refused( only( array( 'Notes' => $note + array( 'learn_lesson_id' => -3 ) ) ), $context ), array( 'lesson_shape' ) );

echo "\n=== normalize() ===\n";

$messy                    = valid();
$messy['status']          = '  Marketing Track ';
$messy['label']           = ' Marketing Track  ';
$messy['hours_target']    = '';
$messy['questions']['Company ']['required'] = false;
unset( $messy['schema_version'], $messy['questions']['Alumni program: personal email']['hide_from_institution'] );
$clean = WPCPM_Track_Definition::normalize( $messy );

ck( 'the status and the name are trimmed', array( $clean['status'], $clean['label'] ), array( 'Marketing Track', 'Marketing Track' ) );
ck( 'a column name is not: it is the key, verbatim', array_key_exists( 'Company ', $clean['questions'] ), true );
ck( 'an empty hours target is no target, and is stored as nothing', array_key_exists( 'hours_target', $clean ), false );
$null                 = valid();
$null['hours_target'] = null;
ck( 'so is a null one', array_key_exists( 'hours_target', WPCPM_Track_Definition::normalize( $null ) ), false );
ck( 'the schema version is filled in', $clean['schema_version'], 1 );
ck( 'an email question is kept off the institution\'s view whatever it said', $clean['questions']['Alumni program: personal email']['hide_from_institution'], true );
ck( 'a flag that is false is dropped, so two definitions that mean the same are the same bytes', array_key_exists( 'required', $clean['questions']['Company '] ), false );
ck( 'and the questions keep their order', array_keys( $clean['questions'] ), array_keys( valid()['questions'] ) );
ck( 'normalizing twice changes nothing', WPCPM_Track_Definition::normalize( $clean ), $clean );

echo "\n=== Storage ===\n";

$tricky = valid();
$tricky['questions']['Practical: Campaign Brief - Notes']['label'] = 'Back\\slash, "quoted", the Site' . "\u{2019}" . 's own';
$json   = WPCPM_Track_Definition::encode( $tricky );

ck( 'a definition survives encoding byte for byte', WPCPM_Track_Definition::decode( $json ), $tricky );
ck( 'the typographic apostrophe is stored as the character it is', false !== strpos( $json, "Site\u{2019}s" ), true );
ck( 'and a course address reads as one', false !== strpos( $json, 'https://learn.wordpress.org/course/' ), true );
ck( 'slashed on its way into the meta store, it comes back whole', WPCPM_Track_Definition::decode( wp_unslash( wp_slash( $json ) ) ), $tricky );
ck( 'unslashed without the slashing, it would come back as nothing at all', WPCPM_Track_Definition::decode( wp_unslash( $json ) ), null );
ck( 'nothing that is not a stored definition decodes', array( WPCPM_Track_Definition::decode( '' ), WPCPM_Track_Definition::decode( 'not json' ), WPCPM_Track_Definition::decode( 5 ), WPCPM_Track_Definition::decode( '"text"' ) ), array( null, null, null, null ) );

echo "\n=== What the live site runs ===\n";

$fields = WPCPM_Track_Definition::compile_fields( valid() );
$left   = array();
foreach ( $fields as $spec ) {
	$left = array_merge( $left, array_values( array_intersect( array_keys( $spec ), WPCPM_Track_Definition::AUTHORING ) ) );
}

ck( 'the form has every question, in order', array_keys( $fields ), array_keys( valid()['questions'] ) );
ck( 'and none of the three properties the form never sees', $left, array() );
ck( 'the institution flag stays: the form reads it', $fields['Alumni program: personal email']['hide_from_institution'], true );
ck( 'everything else of a question comes through untouched', $fields['Practical: Campaign Brief - Channel'], array( 'label' => 'The channel you chose', 'type' => 'select', 'group' => 'project', 'options' => array( 'Newsletter', 'Social', 'Blog' ), 'row' => 'brief', 'stack' => true ) );

ck( 'the compiled row the program map reads', WPCPM_Track_Definition::row( valid(), 42 ), array( 'key' => 'marketing', 'label' => 'Marketing Track', 'course_url' => 'https://learn.wordpress.org/course/wordpress-credits-marketing-track/', 'course_id' => 500001, 'hours' => 120, 'hue' => 'cyan', 'source' => 'definition', 'automation' => false, 'post' => 42 ) );
$bare = valid();
unset( $bare['hours_target'], $bare['course_url'], $bare['learn_course_id'] );
$row = WPCPM_Track_Definition::row( $bare, '7', 'builtin', 1 );
ck( 'no hours target is null, not 0, so the map drops the row rather than printing a denominator', $row['hours'], null );
ck( 'no course is an empty link and ID 0', array( $row['course_url'], $row['course_id'] ), array( '', 0 ) );
ck( 'the source and the automation tick are carried, typed', array( $row['source'], $row['automation'], $row['post'] ), array( 'builtin', true, 7 ) );
ck( 'any source but builtin is a definition', WPCPM_Track_Definition::row( valid(), 1, 'whatever' )['source'], 'definition' );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run it and see it fail.** `php bin/test-track-definition.php`. Expected: a fatal, `Failed opening required '.../includes/tracks/class-wpcpm-track-palette.php'`.

- [ ] **Step 3: Write the palette.** Create `includes/tracks/class-wpcpm-track-palette.php`:

```php
<?php
/**
 * The hues a Track Builder track's chip can be painted in.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A fixed set of named hues, and the one chip rule each paints.
 *
 * **A hue is a name chosen from this list, never a color typed on a screen.** The rule it
 * prints goes into the stylesheet of every dashboard page, so a free color would be a way to
 * put arbitrary text into CSS; and the chips a mentor scans down a list are only useful while
 * no two read alike. The values follow `dashboard.css`, and the four built-in tracks' hues are
 * in the list under their own names, so their definitions can name them and a new track's
 * default can pass them over.
 *
 * Slate and amber are missing on purpose. They paint Paused and Pending graduation, the two
 * states on no track, and a track painted in either would read as a student who has stopped.
 */
final class WPCPM_Track_Palette {

	/**
	 * Hue name => red, green, blue.
	 *
	 * Blue, purple, teal and pink are the chips of the 150-hour track, the 50-hour track, the
	 * Developer Track and the Designer Track; cyan, green and red are the hues no chip uses
	 * yet, none of which reads as either state.
	 */
	const HUES = array(
		'blue'   => array( 56, 88, 233 ),
		'cyan'   => array( 8, 145, 178 ),
		'teal'   => array( 13, 148, 136 ),
		'green'  => array( 22, 163, 74 ),
		'red'    => array( 220, 38, 38 ),
		'pink'   => array( 219, 39, 119 ),
		'purple' => array( 124, 58, 237 ),
	);

	/**
	 * Whether a value is one of the hues.
	 *
	 * @param mixed $hue Anything.
	 * @return bool
	 */
	public static function is_hue( $hue ) {
		return is_string( $hue ) && array_key_exists( $hue, self::HUES );
	}

	/**
	 * The chip rule for one track, in the shape `dashboard.css` gives its own chips.
	 *
	 * The hue at 0.12 behind the row's own ink, and at 0.35 for the border the theme then
	 * removes: the Developer Track and Designer Track chips are drawn exactly so, which is why
	 * an authored chip needs no theme release to sit beside them.
	 *
	 * @param string $key Track key.
	 * @param string $hue Hue name.
	 * @return string One CSS rule, or an empty string for a key or a hue this cannot vouch for.
	 */
	public static function badge_rule( $key, $hue ) {
		if ( ! self::is_hue( $hue ) || 1 !== preg_match( '/^[a-z0-9-]{2,20}$/', (string) $key ) ) {
			return '';
		}

		list( $red, $green, $blue ) = self::HUES[ $hue ];

		return sprintf(
			'.wpcpm-badge--%1$s{background:rgba(%2$d,%3$d,%4$d,0.12);border-color:rgba(%2$d,%3$d,%4$d,0.35);}',
			$key,
			$red,
			$green,
			$blue
		);
	}
}
```

- [ ] **Step 4: Write the definition.** Create `includes/tracks/class-wpcpm-track-definition.php`:

```php
<?php
/**
 * A program track as data: what the Track Builder stores and what the live site runs.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One track's definition: its shape, its rules, and the two things compiled from it.
 *
 * **A question is exactly a `WPCPM_Student_Report_Form::fields()` entry, keyed by its
 * Airtable column,** plus three properties the form never sees (`AUTHORING`). That is the
 * whole design. The four hand-written forms were passed through `json_encode()` and back on
 * 10 September 2026 and came back identical, so a definition can hold what the PHP holds,
 * and everything downstream of `fields()` - the renderer, `WPCPM_Field_Value`,
 * `handle_save()`, the screenshot store - runs an authored track unchanged.
 *
 * The rules were set from what the four forms actually use, a probe of all 115 questions,
 * and `bin/test-report-form.php` holds them to it: every rule here accepts the four forms,
 * so no rule can make parity impossible.
 *
 * Nothing here reads WordPress state. What a rule needs from the site - the other tracks,
 * the statuses that mean something else, the columns the syncs own - arrives as `$context`,
 * which `WPCPM_Tracks::validation_context()` builds, so each rule is a function of its
 * arguments and the suite can ask every one of them.
 */
final class WPCPM_Track_Definition {

	/** The version of this shape; a stored definition says which one it was written in. */
	const SCHEMA_VERSION = 1;

	/** The ten controls `WPCPM_Student_Report_Form::render_field()` draws. */
	const TYPES = array( 'text', 'textarea', 'richtext', 'url', 'email', 'number', 'checkbox', 'select', 'image', 'team' );

	/**
	 * The form's groups, as `WPCPM_Student_Report_Form::groups()` keys them.
	 *
	 * Written out rather than read from that class, which would make these rules load the whole
	 * form; `bin/test-report-form.php` asserts that the two lists are the same.
	 */
	const GROUPS = array( 'hours', 'onboarding', 'project', 'wrapup' );

	/**
	 * Keys no new track may take: the four built-in tracks' and the other modifiers `badge()`
	 * already emits. A key is also a class name the stylesheets paint.
	 */
	const RESERVED_KEYS = array( '150h', '50h', 'dev', 'design', 'sensei', 'paused', 'pending' );

	/** Every property a track may have. */
	const TRACK_PROPERTIES = array( 'schema_version', 'status', 'key', 'label', 'course_url', 'learn_course_id', 'hours_target', 'hue', 'questions' );

	/** Every property a question may have. */
	const QUESTION_PROPERTIES = array( 'type', 'label', 'group', 'help', 'lead', 'subgroup', 'note', 'row', 'stack', 'required', 'min', 'max', 'step', 'maxlength', 'mono', 'options', 'hide_from_institution', 'airtable_type', 'learn_lesson_id', 'why' );

	/** The properties the form never sees; compiling strips them. */
	const AUTHORING = array( 'airtable_type', 'learn_lesson_id', 'why' );

	/** Properties that are `true` or absent. */
	const FLAGS = array( 'stack', 'required', 'mono', 'hide_from_institution' );

	/** Properties holding words, other than the label. */
	const TEXTS = array( 'help', 'lead', 'subgroup', 'note', 'why' );

	/**
	 * The one column a `team` question may write.
	 *
	 * The team control is written for it: its choices are the rows of `Contribution areas`, and
	 * every link column in the base carries a reverse field in the table it points at, so a
	 * second link column would reach into a second table.
	 */
	const TEAM_COLUMN = 'Main Contribution Team';

	/**
	 * The Airtable types a question may be bound to.
	 *
	 * Computed types (formula, lookup, rollup, count and the rest) are missing because Airtable
	 * refuses every write to them; a link is allowed for the team question alone.
	 */
	const WRITABLE_TYPES = array( 'singleLineText', 'multilineText', 'richText', 'url', 'email', 'number', 'checkbox', 'singleSelect', 'multipleAttachments', 'multipleRecordLinks' );

	/** The longest status: one line of the "Currently mentoring" box, and a chip that still reads. */
	const MAX_STATUS = 100;

	/** The longest column name Airtable accepts. */
	const MAX_COLUMN = 255;

	/**
	 * The definition in its one canonical shape, ready to validate and store.
	 *
	 * Trims the status and the name (the plugin trims a status everywhere it reads one), never
	 * a column name: `key()` hashes the name, and an Airtable name can end in a space. An empty
	 * hours target is removed rather than stored as 0, because no target and an explicit 0
	 * already mean the same thing (decision 1.9 of the design). Every `email` question is kept
	 * off the institution's view, and a flag that is `false` is dropped, so two definitions
	 * that mean the same thing are the same bytes.
	 *
	 * @param array $definition Track definition.
	 * @return array
	 */
	public static function normalize( array $definition ) {
		if ( ! isset( $definition['schema_version'] ) ) {
			$definition['schema_version'] = self::SCHEMA_VERSION;
		}

		foreach ( array( 'status', 'label' ) as $property ) {
			if ( isset( $definition[ $property ] ) && is_string( $definition[ $property ] ) ) {
				$definition[ $property ] = trim( $definition[ $property ] );
			}
		}

		if ( array_key_exists( 'hours_target', $definition ) && ( null === $definition['hours_target'] || '' === $definition['hours_target'] ) ) {
			unset( $definition['hours_target'] );
		}

		if ( isset( $definition['questions'] ) && is_array( $definition['questions'] ) ) {
			foreach ( $definition['questions'] as $column => $spec ) {
				if ( ! is_array( $spec ) ) {
					continue;
				}

				if ( isset( $spec['type'] ) && 'email' === $spec['type'] ) {
					$spec['hide_from_institution'] = true;
				}

				foreach ( self::FLAGS as $flag ) {
					if ( array_key_exists( $flag, $spec ) && false === $spec[ $flag ] ) {
						unset( $spec[ $flag ] );
					}
				}

				$definition['questions'][ $column ] = $spec;
			}
		}

		return $definition;
	}

	/**
	 * Everything wrong with a definition, as a list a screen can print.
	 *
	 * @param array $definition Track definition, after `normalize()`.
	 * @param array $context    `tracks` (every other track, status => key), `refused_statuses`
	 *                          (statuses that mean something else), `reserved_columns` (the
	 *                          columns the syncs own) and `locked` (the status and key a
	 *                          published track keeps, or null).
	 * @return array[] Each with a `code`, a `where` (a column, or empty for the track) and a
	 *                 `message`. Empty when the definition may be stored.
	 */
	public static function validate( array $definition, array $context = array() ) {
		$context = array_merge(
			array(
				'tracks'           => array(),
				'refused_statuses' => array(),
				'reserved_columns' => array(),
				'locked'           => null,
			),
			$context
		);

		$errors = array();

		foreach ( array_keys( $definition ) as $property ) {
			if ( ! in_array( $property, self::TRACK_PROPERTIES, true ) ) {
				$errors[] = self::error( 'unknown_property', '', sprintf( /* translators: %s: a property name. */ __( 'The track has a property this version does not know: %s.', 'wpcredits-program-manager' ), $property ) );
			}
		}

		if ( ! isset( $definition['schema_version'] ) || self::SCHEMA_VERSION !== $definition['schema_version'] ) {
			$errors[] = self::error( 'schema_version', '', __( 'The track was written in a version of this format the site does not read.', 'wpcredits-program-manager' ) );
		}

		$errors = array_merge( $errors, self::validate_status( $definition, $context ), self::validate_key( $definition, $context ) );

		if ( ! isset( $definition['label'] ) || ! is_string( $definition['label'] ) || '' === trim( $definition['label'] ) ) {
			$errors[] = self::error( 'label_empty', '', __( 'The track needs a name.', 'wpcredits-program-manager' ) );
		}

		if ( isset( $definition['course_url'] ) && '' !== $definition['course_url'] && ( ! is_string( $definition['course_url'] ) || 1 !== preg_match( '#^https://learn\.wordpress\.org/course/[a-z0-9-]+/?$#', $definition['course_url'] ) ) ) {
			$errors[] = self::error( 'course_url', '', __( 'The course link must be the address of a Learn WordPress course: https://learn.wordpress.org/course/ and its name.', 'wpcredits-program-manager' ) );
		}

		if ( isset( $definition['learn_course_id'] ) && ( ! is_int( $definition['learn_course_id'] ) || $definition['learn_course_id'] < 0 ) ) {
			$errors[] = self::error( 'course_id', '', __( 'The Learn course ID must be a whole number.', 'wpcredits-program-manager' ) );
		}

		if ( isset( $definition['hours_target'] ) && ( ! is_int( $definition['hours_target'] ) || $definition['hours_target'] < 0 ) ) {
			$errors[] = self::error( 'hours_target', '', __( 'The hours target must be a whole number of hours, or empty for none.', 'wpcredits-program-manager' ) );
		}

		if ( ! isset( $definition['hue'] ) || ! WPCPM_Track_Palette::is_hue( $definition['hue'] ) ) {
			$errors[] = self::error( 'hue', '', __( 'Choose one of the chip colors.', 'wpcredits-program-manager' ) );
		}

		if ( ! isset( $definition['questions'] ) || ! is_array( $definition['questions'] ) ) {
			$errors[] = self::error( 'questions_shape', '', __( 'The track\'s questions could not be read.', 'wpcredits-program-manager' ) );

			return $errors;
		}

		$teams = 0;

		foreach ( $definition['questions'] as $column => $spec ) {
			$errors = array_merge( $errors, self::validate_question( (string) $column, $spec, $context ) );

			if ( is_array( $spec ) && isset( $spec['type'] ) && 'team' === $spec['type'] ) {
				++$teams;
			}
		}

		if ( $teams > 1 ) {
			$errors[] = self::error( 'team_twice', '', __( 'A track asks for the contribution team once.', 'wpcredits-program-manager' ) );
		}

		return $errors;
	}

	/**
	 * The status rules: one line, not taken, not a status that means something else, and kept
	 * once the track is published.
	 *
	 * @param array $definition Track definition.
	 * @param array $context    See `validate()`.
	 * @return array[]
	 */
	private static function validate_status( array $definition, array $context ) {
		$status = isset( $definition['status'] ) && is_string( $definition['status'] ) ? $definition['status'] : '';

		if ( '' === $status ) {
			return array( self::error( 'status_empty', '', __( 'The track needs its Airtable status.', 'wpcredits-program-manager' ) ) );
		}

		$errors = array();

		if ( strlen( $status ) > self::MAX_STATUS || 1 === preg_match( '/[\r\n]/', $status ) ) {
			$errors[] = self::error( 'status_shape', '', sprintf( /* translators: %d: a number of characters. */ __( 'The status must be one line of at most %d characters.', 'wpcredits-program-manager' ), self::MAX_STATUS ) );
		}

		$folded = self::fold( $status );

		foreach ( array_keys( (array) $context['tracks'] ) as $other ) {
			if ( self::fold( $other ) === $folded ) {
				$errors[] = self::error( 'status_taken', '', __( 'Another track already has this status.', 'wpcredits-program-manager' ) );
				break;
			}
		}

		foreach ( (array) $context['refused_statuses'] as $refused ) {
			if ( self::fold( $refused ) === $folded ) {
				$errors[] = self::error( 'status_refused', '', __( 'This status already means something else to the program, such as a student who has finished or paused.', 'wpcredits-program-manager' ) );
				break;
			}
		}

		if ( is_array( $context['locked'] ) && isset( $context['locked']['status'] ) && $status !== $context['locked']['status'] ) {
			$errors[] = self::error( 'status_locked', '', __( 'A published track keeps its status: every student on it is found by that exact value.', 'wpcredits-program-manager' ) );
		}

		return $errors;
	}

	/**
	 * The key rules: its shape, not a key the stylesheets already paint, not taken, and kept
	 * once the track is published.
	 *
	 * A reserved key passes for the track that already holds it, which is how a built-in track's
	 * own definition keeps `design` (the migration of phase T2 creates it locked to its key).
	 *
	 * @param array $definition Track definition.
	 * @param array $context    See `validate()`.
	 * @return array[]
	 */
	private static function validate_key( array $definition, array $context ) {
		$key = isset( $definition['key'] ) && is_string( $definition['key'] ) ? $definition['key'] : '';

		if ( 1 !== preg_match( '/^[a-z0-9-]{2,20}$/', $key ) ) {
			return array( self::error( 'key_shape', '', __( 'The key is 2 to 20 lowercase letters, digits or hyphens.', 'wpcredits-program-manager' ) ) );
		}

		$errors = array();
		$own    = is_array( $context['locked'] ) && isset( $context['locked']['key'] ) ? (string) $context['locked']['key'] : null;

		if ( $key !== $own && in_array( $key, self::RESERVED_KEYS, true ) ) {
			$errors[] = self::error( 'key_reserved', '', __( 'This key belongs to a built-in track or to a chip the site already paints.', 'wpcredits-program-manager' ) );
		} elseif ( in_array( $key, array_map( 'strval', array_values( (array) $context['tracks'] ) ), true ) ) {
			$errors[] = self::error( 'key_taken', '', __( 'Another track already has this key.', 'wpcredits-program-manager' ) );
		}

		if ( null !== $own && $key !== $own ) {
			$errors[] = self::error( 'key_locked', '', __( 'A published track keeps its key.', 'wpcredits-program-manager' ) );
		}

		return $errors;
	}

	/**
	 * The rules of one question.
	 *
	 * @param string $column  Airtable column name.
	 * @param mixed  $spec    The question.
	 * @param array  $context See `validate()`.
	 * @return array[]
	 */
	private static function validate_question( $column, $spec, array $context ) {
		$errors = array();

		if ( '' === trim( $column ) || strlen( $column ) > self::MAX_COLUMN ) {
			$errors[] = self::error( 'column_shape', $column, sprintf( /* translators: %d: a number of characters. */ __( 'A column name must hold more than spaces, and at most %d characters.', 'wpcredits-program-manager' ), self::MAX_COLUMN ) );
		} elseif ( ctype_digit( $column ) ) {
			$errors[] = self::error( 'column_numeric', $column, __( 'A column name cannot be a number alone: the form keys each question by its column name, and a number there changes meaning.', 'wpcredits-program-manager' ) );
		}

		if ( in_array( $column, (array) $context['reserved_columns'], true ) ) {
			$errors[] = self::error( 'column_reserved', $column, __( 'This column belongs to the syncs. A question writing it would let a student change it.', 'wpcredits-program-manager' ) );
		}

		if ( ! is_array( $spec ) ) {
			$errors[] = self::error( 'question_shape', $column, __( 'This question could not be read.', 'wpcredits-program-manager' ) );

			return $errors;
		}

		foreach ( array_keys( $spec ) as $property ) {
			if ( ! in_array( $property, self::QUESTION_PROPERTIES, true ) ) {
				$errors[] = self::error( 'unknown_property', $column, sprintf( /* translators: %s: a property name. */ __( 'The question has a property this version does not know: %s.', 'wpcredits-program-manager' ), $property ) );
			}
		}

		$type = isset( $spec['type'] ) ? $spec['type'] : '';

		if ( ! in_array( $type, self::TYPES, true ) ) {
			$errors[] = self::error( 'type_unknown', $column, __( 'The question needs one of the ten controls.', 'wpcredits-program-manager' ) );
		}

		if ( ! isset( $spec['label'] ) || ! is_string( $spec['label'] ) || '' === trim( $spec['label'] ) ) {
			$errors[] = self::error( 'label_empty', $column, __( 'The question needs the words a student reads.', 'wpcredits-program-manager' ) );
		}

		if ( ! isset( $spec['group'] ) || ! in_array( $spec['group'], self::GROUPS, true ) ) {
			$errors[] = self::error( 'group_unknown', $column, __( 'The question needs a group: Total hours, Onboarding, Project or Wrap-up.', 'wpcredits-program-manager' ) );
		}

		foreach ( self::TEXTS as $property ) {
			if ( isset( $spec[ $property ] ) && ! is_string( $spec[ $property ] ) ) {
				$errors[] = self::error( 'text_shape', $column, sprintf( /* translators: %s: a property name. */ __( '%s must be text.', 'wpcredits-program-manager' ), $property ) );
			}
		}

		foreach ( self::FLAGS as $flag ) {
			if ( isset( $spec[ $flag ] ) && true !== $spec[ $flag ] ) {
				$errors[] = self::error( 'flag_shape', $column, sprintf( /* translators: %s: a property name. */ __( '%s is either on or absent.', 'wpcredits-program-manager' ), $flag ) );
			}
		}

		if ( isset( $spec['row'] ) && ( ! is_string( $spec['row'] ) || 1 !== preg_match( '/^[a-z0-9-]+$/', $spec['row'] ) ) ) {
			$errors[] = self::error( 'row_shape', $column, __( 'A row is named with lowercase letters, digits or hyphens.', 'wpcredits-program-manager' ) );
		}

		if ( ! empty( $spec['stack'] ) && empty( $spec['row'] ) ) {
			$errors[] = self::error( 'stack_without_row', $column, __( 'Only a question in a row can share a column of it.', 'wpcredits-program-manager' ) );
		}

		$errors = array_merge( $errors, self::validate_control( $column, $type, $spec ) );

		if ( isset( $spec['airtable_type'] ) ) {
			if ( ! in_array( $spec['airtable_type'], self::WRITABLE_TYPES, true ) ) {
				$errors[] = self::error( 'airtable_type', $column, __( 'A form cannot write this kind of Airtable column.', 'wpcredits-program-manager' ) );
			} elseif ( ( 'multipleRecordLinks' === $spec['airtable_type'] ) !== ( 'team' === $type ) ) {
				$errors[] = self::error( 'airtable_link', $column, __( 'The contribution team question writes a linked-record column, and no other question does.', 'wpcredits-program-manager' ) );
			}
		}

		if ( isset( $spec['learn_lesson_id'] ) && ( ! is_int( $spec['learn_lesson_id'] ) || $spec['learn_lesson_id'] < 0 ) ) {
			$errors[] = self::error( 'lesson_shape', $column, __( 'The Learn lesson ID must be a whole number.', 'wpcredits-program-manager' ) );
		}

		return $errors;
	}

	/**
	 * The rules that belong to one control: numbers have bounds, selects have choices, and a
	 * property that means something to one control appears on that control only.
	 *
	 * @param string $column Airtable column name.
	 * @param mixed  $type   The question's control.
	 * @param array  $spec   The question.
	 * @return array[]
	 */
	private static function validate_control( $column, $type, array $spec ) {
		$errors = array();

		if ( 'number' === $type ) {
			$bounded = isset( $spec['min'], $spec['max'], $spec['step'] ) && is_numeric( $spec['min'] ) && is_numeric( $spec['max'] ) && is_numeric( $spec['step'] );

			if ( ! $bounded || (float) $spec['min'] > (float) $spec['max'] || (float) $spec['step'] <= 0 ) {
				$errors[] = self::error( 'number_bounds', $column, __( 'A number needs a lowest value, a highest value at least as high, and a step above zero.', 'wpcredits-program-manager' ) );
			}
		} elseif ( isset( $spec['min'] ) || isset( $spec['max'] ) || isset( $spec['step'] ) ) {
			$errors[] = self::error( 'number_only', $column, __( 'Only a number has a lowest value, a highest value and a step.', 'wpcredits-program-manager' ) );
		}

		if ( 'select' === $type ) {
			if ( ! self::is_choice_list( isset( $spec['options'] ) ? $spec['options'] : null ) ) {
				$errors[] = self::error( 'options_shape', $column, __( 'A select needs its choices, each written once and none of them empty.', 'wpcredits-program-manager' ) );
			}
		} elseif ( isset( $spec['options'] ) ) {
			$errors[] = self::error( 'options_only', $column, __( 'Only a select has choices.', 'wpcredits-program-manager' ) );
		}

		if ( isset( $spec['maxlength'] ) && ( ! in_array( $type, array( 'text', 'textarea' ), true ) || ! is_int( $spec['maxlength'] ) || $spec['maxlength'] < 1 ) ) {
			$errors[] = self::error( 'maxlength_shape', $column, __( 'A length limit is a whole number above zero, on a text box.', 'wpcredits-program-manager' ) );
		}

		if ( ! empty( $spec['mono'] ) && 'textarea' !== $type ) {
			$errors[] = self::error( 'mono_only', $column, __( 'Only a text area can be set in monospace.', 'wpcredits-program-manager' ) );
		}

		if ( 'team' === $type && self::TEAM_COLUMN !== $column ) {
			$errors[] = self::error( 'team_column', $column, sprintf( /* translators: %s: the contribution team column's name. */ __( 'The contribution team question writes %s and no other column.', 'wpcredits-program-manager' ), self::TEAM_COLUMN ) );
		}

		return $errors;
	}

	/**
	 * Whether a select's choices are a list of words, each written once.
	 *
	 * @param mixed $options The choices.
	 * @return bool
	 */
	private static function is_choice_list( $options ) {
		if ( ! is_array( $options ) || array() === $options || array_values( $options ) !== $options ) {
			return false;
		}

		foreach ( $options as $option ) {
			if ( ! is_string( $option ) || '' === trim( $option ) ) {
				return false;
			}
		}

		return count( array_unique( $options ) ) === count( $options );
	}

	/**
	 * The stored form of a definition.
	 *
	 * Unicode and slashes unescaped, so the typographic apostrophe of `Site’s` is stored as the
	 * character it is and a course address reads as one. The caller slashes the result before
	 * `update_post_meta()`, which unslashes what it is given.
	 *
	 * @param array $definition Track definition.
	 * @return string JSON.
	 */
	public static function encode( array $definition ) {
		return (string) wp_json_encode( $definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * A stored definition, read back.
	 *
	 * @param mixed $json What was stored.
	 * @return array|null The definition, or null for anything that is not one.
	 */
	public static function decode( $json ) {
		if ( ! is_string( $json ) || '' === $json ) {
			return null;
		}

		$definition = json_decode( $json, true );

		return is_array( $definition ) ? $definition : null;
	}

	/**
	 * The form the live site draws: the questions, with the properties it never sees removed.
	 *
	 * @param array $definition Track definition.
	 * @return array Column => the spec `WPCPM_Student_Report_Form::fields()` returns for it.
	 */
	public static function compile_fields( array $definition ) {
		$questions = isset( $definition['questions'] ) && is_array( $definition['questions'] ) ? $definition['questions'] : array();
		$fields    = array();

		foreach ( $questions as $column => $spec ) {
			if ( ! is_array( $spec ) ) {
				continue;
			}

			foreach ( self::AUTHORING as $property ) {
				unset( $spec[ $property ] );
			}

			$fields[ $column ] = $spec;
		}

		return $fields;
	}

	/**
	 * The track's row in the compiled index the program map reads.
	 *
	 * @param array  $definition Track definition.
	 * @param int    $post_id    The definition's post.
	 * @param string $source     `definition`, or `builtin` for a migrated track its PHP still runs.
	 * @param bool   $automation Whether somebody has ticked the reports automation item.
	 * @return array
	 */
	public static function row( array $definition, $post_id, $source = 'definition', $automation = false ) {
		return array(
			'key'        => isset( $definition['key'] ) ? (string) $definition['key'] : '',
			'label'      => isset( $definition['label'] ) ? (string) $definition['label'] : '',
			'course_url' => isset( $definition['course_url'] ) ? (string) $definition['course_url'] : '',
			'course_id'  => isset( $definition['learn_course_id'] ) ? (int) $definition['learn_course_id'] : 0,
			'hours'      => isset( $definition['hours_target'] ) ? (int) $definition['hours_target'] : null,
			'hue'        => isset( $definition['hue'] ) ? (string) $definition['hue'] : '',
			'source'     => 'builtin' === $source ? 'builtin' : 'definition',
			'automation' => (bool) $automation,
			'post'       => (int) $post_id,
		);
	}

	/**
	 * A status as the comparisons see it: trimmed, one space for any run of them, the
	 * typographic apostrophe read as the plain one, and lower case.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function fold( $status ) {
		$status = str_replace( array( "\u{2019}", "\u{2018}" ), "'", (string) $status );

		return strtolower( trim( (string) preg_replace( '/\s+/', ' ', $status ) ) );
	}

	/**
	 * One entry of `validate()`'s list.
	 *
	 * @param string $code    What was wrong, for code to act on.
	 * @param string $where   The column, or empty for the track.
	 * @param string $message What was wrong, for a person.
	 * @return array
	 */
	private static function error( $code, $where, $message ) {
		return array(
			'code'    => $code,
			'where'   => (string) $where,
			'message' => $message,
		);
	}
}
```

- [ ] **Step 5: Run it and see it pass.** `php bin/test-track-definition.php`. Expected: `ALL PASS (90 checks)`.

- [ ] **Step 6: Run the battery and the three static checks, and phpcs.** Expected: silent and clean; no ` ERROR ` line; no new warning. (Neither class is loaded by the plugin until Task 4; `bin/test-roles.php` starts holding `includes/tracks/` to the loader in the same task.)

- [ ] **Step 7: Commit.**

```bash
git add includes/tracks/class-wpcpm-track-palette.php includes/tracks/class-wpcpm-track-definition.php bin/test-track-definition.php
git commit -m "Track Builder T1: a track as data - the palette, the definition, its rules and its compile"
```

---

### Task 3: What the live site reads, and every rule held to the four forms

**Files:**
- Create: `includes/tracks/class-wpcpm-tracks.php`
- Test: `bin/test-tracks.php`
- Modify: `bin/test-report-form.php` (the parity block, above the closing summary)

**Interfaces:**
- Consumes: Task 1's `WPCPM_Program::states()`, `course_id()` and the filters `wpcpm_program_tracks` and `wpcpm_program_course_ids`; Task 2's palette and definition. Also the existing `WPCPM_Mentors_Dashboard::register_assets()` and `::STYLE`, `WPCPM_Mentors_Sync::fields()` and `WPCPM_Settings::get()`.
- Produces: `WPCPM_Tracks::OPT_TRACKS` (`wpcpm_tracks`), `::OPT_FIELDS_PREFIX` (`wpcpm_track_fields_`), `::SYNC_COLUMNS`; `init()`, `rows()`, `live()`, `flush()`; the callbacks `filter_labels()`, `filter_courses()`, `filter_hours()`, `filter_tracks()`, `filter_course_ids()` and `filter_fields( $fields, $track )`; `form( $key ): array`; `confirmed_automation_statuses(): string[]`; `badge_css(): string`; `add_badge_styles()`; `reserved_columns(): string[]`; `validation_context( $own_status = '' ): array`.

- [ ] **Step 1: Write the failing suite.** Create `bin/test-tracks.php`:

```php
<?php
/**
 * The Track Builder's tracks as the live site reads them (1.100.0): the program map, the form,
 * the chips and the automation guard, from the compiled options alone.
 *
 * The filters here run for real, so a check can ask `WPCPM_Program` the questions every page asks
 * it and see the answer a compiled track makes. With nothing compiled, every answer is the one
 * 1.99.3 gave, and the second section holds that.
 *
 * Run from the plugin root:  php bin/test-tracks.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['filters'] = array();
$GLOBALS['hooked']  = array();
$GLOBALS['actions'] = array();
$GLOBALS['opts']    = array();
$GLOBALS['reads']   = 0;
$GLOBALS['inline']  = array();
$GLOBALS['assets']  = 0;

function __( $s, $d = null ) { return $s; }
function apply_filters( $tag, $value, ...$args ) {
	foreach ( $GLOBALS['filters'][ $tag ] ?? array() as $callback ) {
		$value = call_user_func_array( $callback, array_merge( array( $value ), $args ) );
	}

	return $value;
}
function add_filter( $tag, $callback, $priority = 10, $accepted = 1 ) {
	$GLOBALS['filters'][ $tag ][] = $callback;
	$GLOBALS['hooked'][]          = array( $tag, is_array( $callback ) ? $callback[1] : 'closure', $priority, $accepted );
}
function add_action( $tag, $callback, $priority = 10, $accepted = 1 ) { $GLOBALS['actions'][] = array( $tag, $callback[1], $priority ); }
function get_option( $k, $d = false ) {
	if ( 'wpcpm_tracks' === $k ) {
		++$GLOBALS['reads'];
	}
	return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d;
}
function wp_add_inline_style( $handle, $css ) { $GLOBALS['inline'][] = array( $handle, $css ); return true; }

/** The mentor dashboard, for the one constant and the one call the chips use. */
class WPCPM_Mentors_Dashboard {
	const STYLE = 'wpcpm-mentor-dashboard';
	public static function register_assets() { ++$GLOBALS['assets']; }
}

/** The sync's column map, its report half: all the Track Builder reads of the sync. */
class WPCPM_Mentors_Sync {
	public static function fields() {
		return array(
			'report_name'       => 'Name',
			'report_email'      => 'Email',
			'report_status'     => 'Status',
			'report_mentor'     => 'Mentor',
			'report_instituton' => 'Educational institution',
			'report_profile'    => 'WordPress Profile',
			'report_slack'      => 'Slack Name',
			'report_team'       => 'Main Contribution Team',
			'report_website'    => 'Personal Website URL',
			'report_start'      => 'Internship Start Date',
			'report_end'        => 'Internship End Date',
			'report_hours'      => 'Hours',
			'report_link'       => 'Personal link',
			'report_link_50h'   => '50h personal link',
			'report_link_dev'   => 'Dev Track ONLY personal link',
		);
	}
}

/** The settings, for the one list the status rule reads. */
class WPCPM_Settings {
	public static function get() {
		return array( 'past_statuses' => array( 'Graduate', 'Dropped out' ) );
	}
}

require_once __DIR__ . '/../includes/class-wpcpm-program.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-palette.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-tracks.php';

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

/** Put a compiled store in place, as `WPCPM_Track_Store::compile()` leaves it. */
function compiled( array $rows, array $forms = array() ) {
	$GLOBALS['opts'] = array( WPCPM_Tracks::OPT_TRACKS => $rows );
	foreach ( $forms as $key => $form ) {
		$GLOBALS['opts'][ WPCPM_Tracks::OPT_FIELDS_PREFIX . $key ] = $form;
	}
	WPCPM_Tracks::flush();
}

/** One compiled row. */
function row( $key, $label, array $overrides = array() ) {
	return array_merge(
		array( 'key' => $key, 'label' => $label, 'course_url' => '', 'course_id' => 0, 'hours' => null, 'hue' => 'cyan', 'source' => 'definition', 'automation' => false, 'post' => 1 ),
		$overrides
	);
}

$form           = array( 'Practical: Campaign Brief - Notes' => array( 'label' => 'Your notes', 'type' => 'textarea', 'group' => 'project' ) );
$builtin_labels = array( 'In Sensei' => 'WordPress Credits Program 150h', 'In Sensei 50h' => 'WordPress Credits Program 50h', 'Developer Track' => 'Developer Track', 'Designer Track' => 'Designer Track' );

echo "=== init() ===\n";

WPCPM_Tracks::init();
ck(
	'the five program filters and the form filter, the form one taking its track',
	$GLOBALS['hooked'],
	array(
		array( 'wpcpm_program_labels', 'filter_labels', 10, 1 ),
		array( 'wpcpm_program_courses', 'filter_courses', 10, 1 ),
		array( 'wpcpm_program_hours_targets', 'filter_hours', 10, 1 ),
		array( 'wpcpm_program_tracks', 'filter_tracks', 10, 1 ),
		array( 'wpcpm_program_course_ids', 'filter_course_ids', 10, 1 ),
		array( 'wpcpm_report_form_fields', 'filter_fields', 10, 2 ),
	)
);
ck( 'and the chips after init 10, where the dashboards register the stylesheet they live in', $GLOBALS['actions'], array( array( 'init', 'add_badge_styles', 20 ) ) );

echo "\n=== With nothing compiled, every answer is 1.99.3's ===\n";

compiled( array() );
ck( 'the four tracks and nothing else', WPCPM_Program::labels(), $builtin_labels );
ck( 'the hours targets', WPCPM_Program::hours_targets(), array( 'In Sensei' => 150, 'In Sensei 50h' => 50, 'Developer Track' => 0, 'Designer Track' => 150 ) );
ck( 'the form filter hands back what it was given', apply_filters( 'wpcpm_report_form_fields', array( 'x' => 1 ), 'marketing' ), array( 'x' => 1 ) );
ck( 'no chip rule', WPCPM_Tracks::badge_css(), '' );
WPCPM_Tracks::add_badge_styles();
ck( 'and not even the stylesheet is touched', array( $GLOBALS['assets'], $GLOBALS['inline'] ), array( 0, array() ) );
ck( 'no track is known to the automation', WPCPM_Tracks::confirmed_automation_statuses(), array() );

echo "\n=== An authored track, published ===\n";

compiled(
	array(
		'Marketing Track' => row( 'marketing', 'Marketing Track', array( 'course_url' => 'https://learn.wordpress.org/course/wordpress-credits-marketing-track/', 'course_id' => 500001, 'hours' => 120, 'automation' => true ) ),
		'In Sensei 50h'   => row( '50h', 'Should not appear', array( 'source' => 'builtin', 'hours' => 1 ) ),
	),
	array( 'marketing' => $form, '50h' => array( 'Should not appear' => array() ) )
);
ck( 'it is a track', WPCPM_Program::is_track( 'Marketing Track' ), true );
ck( 'with its name, after the four', WPCPM_Program::labels(), $builtin_labels + array( 'Marketing Track' => 'Marketing Track' ) );
ck( 'its key, and the chip painted from it', array( WPCPM_Program::track( 'Marketing Track' ), WPCPM_Program::badge( 'Marketing Track' ) ), array( 'marketing', 'marketing' ) );
ck( 'its Learn course, by link and by ID', array( WPCPM_Program::course_url( 'Marketing Track' ), WPCPM_Program::course_id( 'Marketing Track' ) ), array( 'https://learn.wordpress.org/course/wordpress-credits-marketing-track/', 500001 ) );
ck( 'its hours target', array( WPCPM_Program::hours_target( 'Marketing Track' ), WPCPM_Program::has_hours_target( 'Marketing Track' ) ), array( 120, true ) );
ck( 'its own form, from its option', apply_filters( 'wpcpm_report_form_fields', array( 'the 150-hour set' => array() ), 'marketing' ), $form );
ck( 'a built-in track\'s form is left to its PHP', apply_filters( 'wpcpm_report_form_fields', array( 'the 150-hour set' => array() ), '150h' ), array( 'the 150-hour set' => array() ) );
ck( 'a track its PHP still runs is skipped by every callback', array( WPCPM_Program::label( 'In Sensei 50h' ), WPCPM_Program::hours_target( 'In Sensei 50h' ), apply_filters( 'wpcpm_report_form_fields', array( 'php' => array() ), '50h' ) ), array( 'WordPress Credits Program 50h', 50, array( 'php' => array() ) ) );
ck( 'its chip rule, from the palette', WPCPM_Tracks::badge_css(), '.wpcpm-badge--marketing{background:rgba(8,145,178,0.12);border-color:rgba(8,145,178,0.35);}' );
WPCPM_Tracks::add_badge_styles();
ck( 'printed with the dashboard stylesheet, registered first so the rule has somewhere to go', array( $GLOBALS['assets'], $GLOBALS['inline'] ), array( 1, array( array( 'wpcpm-mentor-dashboard', WPCPM_Tracks::badge_css() ) ) ) );
ck( 'and its automation item, ticked, puts it on the guard\'s list', WPCPM_Tracks::confirmed_automation_statuses(), array( 'Marketing Track' ) );

echo "\n=== A track with no hours and no course ===\n";

compiled( array( 'Marketing Track' => row( 'marketing', 'Marketing Track' ) ), array( 'marketing' => $form ) );
ck( 'no target: absent from the map, so nothing prints a denominator', array( array_key_exists( 'Marketing Track', WPCPM_Program::hours_targets() ), WPCPM_Program::has_hours_target( 'Marketing Track' ) ), array( false, false ) );
ck( 'no course: no link and no ID', array( WPCPM_Program::course_url( 'Marketing Track' ), WPCPM_Program::course_id( 'Marketing Track' ) ), array( '', 0 ) );
ck( 'and with its automation item unticked, the guard does not know it', WPCPM_Tracks::confirmed_automation_statuses(), array() );

echo "\n=== A built-in track, switched to its definition ===\n";

compiled( array( 'Designer Track' => row( 'design', 'Designer Track', array( 'hue' => 'pink', 'course_id' => 403425, 'course_url' => 'https://learn.wordpress.org/course/wordpress-credits-designer-track/' ) ) ), array( 'design' => $form ) );
ck( 'its definition names no hours, and the PHP\'s 150 gives way', WPCPM_Program::hours_target( 'Designer Track' ), 0 );
ck( 'its form is the definition\'s', apply_filters( 'wpcpm_report_form_fields', array( 'php' => array() ), 'design' ), $form );
ck( 'but its chip keeps the hand-written rule: none is generated', WPCPM_Tracks::badge_css(), '' );

echo "\n=== When a compiled option is not what it should be ===\n";

compiled( array( 'Marketing Track' => row( 'marketing', 'Marketing Track' ) ) );
ck( 'a live track with no form option draws an empty form, never the 150-hour set it was handed', apply_filters( 'wpcpm_report_form_fields', array( 'the 150-hour set' => array() ), 'marketing' ), array() );
compiled( array( 'Marketing Track' => row( 'marketing', 'Marketing Track', array( 'hue' => 'mauve' ) ) ), array( 'marketing' => $form ) );
ck( 'a hue outside the palette prints no rule', WPCPM_Tracks::badge_css(), '' );

echo "\n=== Read once a request ===\n";

compiled( array( 'Marketing Track' => row( 'marketing', 'Marketing Track' ) ), array( 'marketing' => $form ) );
$GLOBALS['reads'] = 0;
WPCPM_Program::labels();
WPCPM_Program::labels();
WPCPM_Program::track( 'Marketing Track' );
ck( 'the index is read once, however often the map is asked', $GLOBALS['reads'], 1 );
WPCPM_Tracks::flush();
WPCPM_Program::labels();
ck( 'and again after a flush', $GLOBALS['reads'], 2 );

echo "\n=== What the rules are told about the site ===\n";

ck( 'the columns the syncs own: every report column the sync maps that is not a question', WPCPM_Tracks::reserved_columns(), array( 'Name', 'Email', 'Status', 'Mentor', 'Educational institution', 'Internship Start Date', 'Internship End Date', 'Personal link', '50h personal link', 'Dev Track ONLY personal link' ) );
$context = WPCPM_Tracks::validation_context( 'Marketing Track' );
ck( 'the other tracks by status and key, the track being checked left out', $context['tracks'], array( 'In Sensei' => '150h', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev', 'Designer Track' => 'design' ) );
ck( 'the statuses that mean something else: past students, and the two states', $context['refused_statuses'], array( 'Graduate', 'Dropped out', 'Paused', 'Pending graduation' ) );
ck( 'the sync columns, and nothing locked', array( $context['reserved_columns'], $context['locked'] ), array( WPCPM_Tracks::reserved_columns(), null ) );
ck( 'and the track it describes passes with it', WPCPM_Track_Definition::validate( array( 'schema_version' => 1, 'status' => 'Marketing Track', 'key' => 'marketing', 'label' => 'Marketing Track', 'hue' => 'cyan', 'questions' => $form ), $context ), array() );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run it and see it fail.** `php bin/test-tracks.php`. Expected: a fatal, `Failed opening required '.../includes/tracks/class-wpcpm-tracks.php'`.

- [ ] **Step 3: Write the reader.** Create `includes/tracks/class-wpcpm-tracks.php`:

```php
<?php
/**
 * The Track Builder's tracks, as the live site reads them.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hands the compiled tracks to the program map, the form, the chips and the automation guard.
 *
 * **The live site never reads a `wpcpm_track` post.** `WPCPM_Program::labels()` runs for every
 * row of every roster, which rules out a query, and editing a published track must not change
 * the form students see until somebody publishes the change. So publishing compiles every live
 * track into options (`WPCPM_Track_Store::compile()`), and everything here reads only those:
 * `OPT_TRACKS`, autoloaded and small, and one `OPT_FIELDS_PREFIX` option per track holding its
 * form, read on the pages that draw one.
 *
 * A row whose source is `builtin` is a migrated track its PHP still runs (the design's decision
 * 3.5): every callback skips it, so the hand-written code stays authoritative until a Program
 * Administrator switches the track to its definition.
 */
final class WPCPM_Tracks {

	/** The compiled index: status => `WPCPM_Track_Definition::row()`. Autoloaded. */
	const OPT_TRACKS = 'wpcpm_tracks';

	/** One compiled form per track key, not autoloaded. */
	const OPT_FIELDS_PREFIX = 'wpcpm_track_fields_';

	/**
	 * The report columns the syncs own, by their key in `WPCPM_Mentors_Sync::fields()`.
	 *
	 * `handle_save()` writes the columns a form names, so a question bound to one of these would
	 * let a student change their own status, mentor, institution or dates. The five report keys
	 * missing here - profile, Slack, team, website and hours - are questions on every form, which
	 * is why the sync maps them as well. `report_instituton` is spelled as the sync spells it.
	 */
	const SYNC_COLUMNS = array( 'report_name', 'report_email', 'report_status', 'report_mentor', 'report_instituton', 'report_start', 'report_end', 'report_link', 'report_link_50h', 'report_link_dev' );

	/**
	 * The compiled index, read once a request.
	 *
	 * @var array|null
	 */
	private static $rows = null;

	/**
	 * Compiled forms read this request, by track key.
	 *
	 * @var array<string, array>
	 */
	private static $forms = array();

	/**
	 * Hook the tracks into the program map, the form and the stylesheet.
	 */
	public static function init() {
		add_filter( 'wpcpm_program_labels', array( __CLASS__, 'filter_labels' ) );
		add_filter( 'wpcpm_program_courses', array( __CLASS__, 'filter_courses' ) );
		add_filter( 'wpcpm_program_hours_targets', array( __CLASS__, 'filter_hours' ) );
		add_filter( 'wpcpm_program_tracks', array( __CLASS__, 'filter_tracks' ) );
		add_filter( 'wpcpm_program_course_ids', array( __CLASS__, 'filter_course_ids' ) );
		add_filter( 'wpcpm_report_form_fields', array( __CLASS__, 'filter_fields' ), 10, 2 );

		// After `init` 10, where the dashboards register the stylesheet the chips live in.
		add_action( 'init', array( __CLASS__, 'add_badge_styles' ), 20 );
	}

	/**
	 * Every compiled track, the ones their PHP still runs included.
	 *
	 * @return array<string, array> Status => row.
	 */
	public static function rows() {
		if ( null === self::$rows ) {
			$stored     = get_option( self::OPT_TRACKS, array() );
			self::$rows = is_array( $stored ) ? $stored : array();
		}

		return self::$rows;
	}

	/**
	 * The tracks the site runs from their definitions.
	 *
	 * @return array<string, array> Status => row.
	 */
	public static function live() {
		return array_filter(
			self::rows(),
			static function ( $row ) {
				return is_array( $row ) && isset( $row['source'], $row['key'] ) && 'definition' === $row['source'];
			}
		);
	}

	/**
	 * Forget what was read this request, after a compile has written new options.
	 */
	public static function flush() {
		self::$rows  = null;
		self::$forms = array();
	}

	/**
	 * Add each live track's name to `WPCPM_Program::labels()`.
	 *
	 * @param array $labels Status => name.
	 * @return array
	 */
	public static function filter_labels( $labels ) {
		foreach ( self::live() as $status => $row ) {
			$labels[ $status ] = (string) $row['label'];
		}

		return $labels;
	}

	/**
	 * Add each live track's Learn course link, or take the map's away when the definition has none.
	 *
	 * Taking away matters for a built-in track switched to its definition: the definition is then
	 * what the track is, and a course it no longer names must not survive from the PHP.
	 *
	 * @param array $courses Status => course URL.
	 * @return array
	 */
	public static function filter_courses( $courses ) {
		foreach ( self::live() as $status => $row ) {
			if ( isset( $row['course_url'] ) && '' !== $row['course_url'] ) {
				$courses[ $status ] = (string) $row['course_url'];
			} else {
				unset( $courses[ $status ] );
			}
		}

		return $courses;
	}

	/**
	 * Add each live track's hours target, or take the row away for a track that counts none.
	 *
	 * A missing row is how `WPCPM_Program::hours_targets()` says there is no target (decision 1.9
	 * of the design), so a definition without one removes the row rather than writing 0.
	 *
	 * @param array $targets Status => hours.
	 * @return array
	 */
	public static function filter_hours( $targets ) {
		foreach ( self::live() as $status => $row ) {
			if ( isset( $row['hours'] ) ) {
				$targets[ $status ] = (int) $row['hours'];
			} else {
				unset( $targets[ $status ] );
			}
		}

		return $targets;
	}

	/**
	 * Add each live track's key to `WPCPM_Program::track()`.
	 *
	 * @param array $tracks Status => track key.
	 * @return array
	 */
	public static function filter_tracks( $tracks ) {
		foreach ( self::live() as $status => $row ) {
			$tracks[ $status ] = (string) $row['key'];
		}

		return $tracks;
	}

	/**
	 * Add each live track's Learn course post ID, or take the map's away when there is none.
	 *
	 * @param array $ids Status => Learn course post ID.
	 * @return array
	 */
	public static function filter_course_ids( $ids ) {
		foreach ( self::live() as $status => $row ) {
			if ( ! empty( $row['course_id'] ) ) {
				$ids[ $status ] = (int) $row['course_id'];
			} else {
				unset( $ids[ $status ] );
			}
		}

		return $ids;
	}

	/**
	 * A live track's form, in place of what `WPCPM_Student_Report_Form::fields()` built.
	 *
	 * **A live track whose form option is missing gets an empty form, never the one it was
	 * handed.** For a key it does not know, `fields()` builds the 150-hour set, and drawing that
	 * for an authored track would have its students writing another track's columns. An empty
	 * form writes nothing.
	 *
	 * @param array  $fields What `fields()` built.
	 * @param string $track  Track key.
	 * @return array
	 */
	public static function filter_fields( $fields, $track ) {
		foreach ( self::live() as $row ) {
			if ( (string) $row['key'] === (string) $track ) {
				return self::form( (string) $row['key'] );
			}
		}

		return $fields;
	}

	/**
	 * One track's compiled form, read once a request.
	 *
	 * @param string $key Track key.
	 * @return array
	 */
	public static function form( $key ) {
		if ( ! isset( self::$forms[ $key ] ) ) {
			$stored              = get_option( self::OPT_FIELDS_PREFIX . $key, array() );
			self::$forms[ $key ] = is_array( $stored ) ? $stored : array();
		}

		return self::$forms[ $key ];
	}

	/**
	 * The live tracks whose reports automation item somebody has ticked.
	 *
	 * @return string[] Statuses.
	 */
	public static function confirmed_automation_statuses() {
		$statuses = array();

		foreach ( self::live() as $status => $row ) {
			if ( ! empty( $row['automation'] ) ) {
				$statuses[] = (string) $status;
			}
		}

		return $statuses;
	}

	/**
	 * One chip rule per authored track.
	 *
	 * None for the built-in keys, a built-in track switched to its definition included: their
	 * rules are hand-written, in the plugin's stylesheet and in the theme's.
	 *
	 * @return string CSS, or an empty string when there is nothing to paint.
	 */
	public static function badge_css() {
		$rules = array();

		foreach ( self::live() as $row ) {
			if ( in_array( $row['key'], WPCPM_Track_Definition::RESERVED_KEYS, true ) ) {
				continue;
			}

			$rule = WPCPM_Track_Palette::badge_rule( (string) $row['key'], isset( $row['hue'] ) ? (string) $row['hue'] : '' );

			if ( '' !== $rule ) {
				$rules[] = $rule;
			}
		}

		return implode( "\n", $rules );
	}

	/**
	 * Attach the chip rules to the stylesheet every dashboard page loads.
	 *
	 * The stylesheet is registered first, because an inline rule for a handle that is not
	 * registered is dropped without a word; `register_assets()` registers it at most once.
	 */
	public static function add_badge_styles() {
		$css = self::badge_css();

		if ( '' === $css ) {
			return;
		}

		WPCPM_Mentors_Dashboard::register_assets();
		wp_add_inline_style( WPCPM_Mentors_Dashboard::STYLE, $css );
	}

	/**
	 * The column names the syncs own, which no question may write.
	 *
	 * @return string[]
	 */
	public static function reserved_columns() {
		$map     = WPCPM_Mentors_Sync::fields();
		$columns = array();

		foreach ( self::SYNC_COLUMNS as $key ) {
			if ( isset( $map[ $key ] ) ) {
				$columns[] = (string) $map[ $key ];
			}
		}

		return $columns;
	}

	/**
	 * What `WPCPM_Track_Definition::validate()` needs to know about the site.
	 *
	 * @param string $own_status The status of the track being checked, left out of the others.
	 * @return array
	 */
	public static function validation_context( $own_status = '' ) {
		$tracks = array();

		foreach ( array_keys( WPCPM_Program::labels() ) as $status ) {
			if ( (string) $status !== (string) $own_status ) {
				$tracks[ $status ] = WPCPM_Program::track( $status );
			}
		}

		$settings = WPCPM_Settings::get();
		$refused  = array_merge(
			isset( $settings['past_statuses'] ) ? (array) $settings['past_statuses'] : array(),
			array_keys( WPCPM_Program::states() )
		);

		return array(
			'tracks'           => $tracks,
			'refused_statuses' => array_values( array_unique( $refused ) ),
			'reserved_columns' => self::reserved_columns(),
			'locked'           => null,
		);
	}
}
```

- [ ] **Step 4: Run it and see it pass.** `php bin/test-tracks.php`. Expected: `ALL PASS (34 checks)`.

- [ ] **Step 5: Hold every rule to the four hand-written forms.** Apply this change to `bin/test-report-form.php`, then run `php bin/test-report-form.php`. Expected: `ALL PASS (162 checks)`. If a rule refuses a form, change the rule and never the form: the four forms are the parity baseline (decision 1.4), and a rule they break would make the migration of phase T2 impossible.

```diff
--- a/bin/test-report-form.php
+++ b/bin/test-report-form.php
@@ -1308,6 +1308,30 @@
 // card's link branch runs it through `WPCPM_Field_Value::clean_url()` first.
 ck( 'a schemeless address stored by Airtable links out with a scheme, and shows as it was typed', preg_match( '#<span class="wpcpm-field__value"><a href="https://example\.org/me" target="_blank" rel="noopener noreferrer">example\.org/me</a></span>#', $read ) === 1, true );
 
+echo "\n=== The Track Builder's rules accept the four hand-written forms (1.100.0) ===\n";
+
+require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-palette.php';
+require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';
+require_once __DIR__ . '/../includes/tracks/class-wpcpm-tracks.php';
+
+ck( 'the definition\'s groups are the form\'s, in order', WPCPM_Track_Definition::GROUPS, array_keys( WPCPM_Student_Report_Form::groups() ) );
+
+foreach ( array( '150h', '50h', 'dev', 'design' ) as $parity_key ) {
+	$parity_questions  = WPCPM_Student_Report_Form::fields( $parity_key );
+	$parity_definition = array(
+		'schema_version' => WPCPM_Track_Definition::SCHEMA_VERSION,
+		'status'         => 'Parity ' . $parity_key,
+		'key'            => 'parity-' . $parity_key,
+		'label'          => 'Parity ' . $parity_key,
+		'hue'            => 'blue',
+		'questions'      => $parity_questions,
+	);
+
+	ck( sprintf( 'every rule accepts the %s form, none of its columns a sync column', $parity_key ), WPCPM_Track_Definition::validate( $parity_definition, array( 'reserved_columns' => WPCPM_Tracks::reserved_columns() ) ), array() );
+	ck( sprintf( 'the %s form compiles back to itself', $parity_key ), WPCPM_Track_Definition::compile_fields( $parity_definition ), $parity_questions );
+	ck( sprintf( 'and the %s form survives storage byte for byte', $parity_key ), WPCPM_Track_Definition::decode( WPCPM_Track_Definition::encode( $parity_definition ) ), $parity_definition );
+}
+
 printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
 
 
```

- [ ] **Step 6: Run the battery and the three static checks, and phpcs.** Expected: silent and clean; no ` ERROR `; no new warning.

- [ ] **Step 7: Commit.**

```bash
git add includes/tracks/class-wpcpm-tracks.php bin/test-tracks.php bin/test-report-form.php
git commit -m "Track Builder T1: the reader the live site runs on, and every rule held to the four hand-written forms"
```

---

### Task 4: The store, and the plugin loads the four classes

**Files:**
- Create: `includes/tracks/class-wpcpm-track-store.php`
- Test: `bin/test-track-store.php`
- Modify: `wpcredits-program-manager.php` (four requires; two lines in `wpcpm_bootstrap()`)
- Modify: `uninstall.php` (four requires; the sweep)
- Modify: `bin/test-roles.php` (the class-file rule reaches `includes/tracks`)

**Interfaces:**
- Consumes: Task 2's definition; Task 3's `WPCPM_Tracks::OPT_TRACKS`, `::OPT_FIELDS_PREFIX` and `::flush()`.
- Produces: `WPCPM_Track_Store::POST_TYPE` (`wpcpm_track`), `::META_DEFINITION` (`_wpcpm_track_definition`), `::META_SOURCE` (`_wpcpm_track_source`, `builtin` or absent), `::META_AUTOMATION` (`_wpcpm_track_automation`, `1` or absent); `init()`, `register_post_type()`, `register_meta()`, `create( array $definition ): int|WP_Error`, `save( $post_id, array $definition ): int|WP_Error`, `get( $post_id ): array|null`, `compile(): array`, `delete_all()`.

The two WordPress behaviors this class is built around are modeled by the suite's stand-ins, and Task 6 proves them on a real WordPress: a revision is saved from inside `wp_update_post()` with the revisioned meta the post holds at that moment, so the meta is written first (the semester report's rule, `WPCPM_Semester_Report::save()`); and `update_post_meta()` and `wp_insert_post()` unslash what they are given, so both are slashed.

- [ ] **Step 1: Write the failing suite.** Create `bin/test-track-store.php`:

```php
<?php
/**
 * Where the Track Builder keeps its tracks (1.100.0): the post type, the revisioned definition,
 * the compile step and the uninstall sweep.
 *
 * The stand-ins below model the three WordPress behaviors the store is built around: the post
 * array and every meta value are unslashed on the way in; a revision is saved from inside
 * `wp_update_post()`, with the revisioned meta the post holds at that moment; and
 * `revisions_enabled` is refused for a type that does not support revisions yet. A store that got
 * any of the three wrong passes a pass-through stub and fails here.
 *
 * Run from the plugin root:  php bin/test-track-store.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	private $code;
	public function __construct( $c = '' ) { $this->code = $c; }
	public function get_error_code() { return $this->code; }
}
class WP_Post {
	public $ID = 0, $post_type = 'post', $post_status = 'draft', $post_title = '';
}

$GLOBALS['posts']      = array();
$GLOBALS['pmeta']      = array();
$GLOBALS['revisions']  = array();
$GLOBALS['opts']       = array();
$GLOBALS['autoload']   = array();
$GLOBALS['actions']    = array();
$GLOBALS['types']      = array();
$GLOBALS['meta_args']  = array();
$GLOBALS['revisioned'] = array();
$GLOBALS['wrong']      = array();
$GLOBALS['next_id']    = 100;

function __( $s, $d = null ) { return $s; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function wp_json_encode( $v, $o = 0 ) { return json_encode( $v, $o ); }
function wp_slash( $v ) { return is_array( $v ) ? array_map( 'wp_slash', $v ) : ( is_string( $v ) ? addslashes( $v ) : $v ); }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['actions'][] = array( $hook, $callback, $priority ); }
function add_filter() {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $autoload; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ], $GLOBALS['autoload'][ $k ] ); return true; }
function get_post_stati() { return array( 'publish' => 'publish', 'draft' => 'draft', 'pending' => 'pending', 'private' => 'private', 'trash' => 'trash', 'auto-draft' => 'auto-draft' ); }
function register_post_type( $type, $args ) { $GLOBALS['types'][ $type ] = $args; }
function post_type_supports( $type, $feature ) { return in_array( $feature, $GLOBALS['types'][ $type ]['supports'] ?? array(), true ); }
function register_post_meta( $type, $key, $args ) {
	// WordPress refuses revisions for a type that does not support them yet, with a notice.
	if ( ! empty( $args['revisions_enabled'] ) && ! post_type_supports( $type, 'revisions' ) ) {
		$GLOBALS['wrong'][]        = $key;
		$args['revisions_enabled'] = false;
	}
	$GLOBALS['meta_args'][ $type ][ $key ] = $args;
	if ( ! empty( $args['revisions_enabled'] ) ) {
		$GLOBALS['revisioned'][ $type ][ $key ] = true;
	}
	return true;
}
function wp_insert_post( $postarr, $wp_error = false ) {
	$postarr           = wp_unslash( $postarr ); // WordPress unslashes the post array.
	$post              = new WP_Post();
	$post->ID          = ++$GLOBALS['next_id'];
	$post->post_type   = $postarr['post_type'] ?? 'post';
	$post->post_status = $postarr['post_status'] ?? 'draft';
	$post->post_title  = $postarr['post_title'] ?? '';
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function wp_update_post( $postarr, $wp_error = false ) {
	$postarr = wp_unslash( $postarr );
	$id      = (int) ( $postarr['ID'] ?? 0 );
	if ( ! isset( $GLOBALS['posts'][ $id ] ) ) {
		return $wp_error ? new WP_Error( 'invalid_post' ) : 0;
	}
	foreach ( array( 'post_title', 'post_status' ) as $field ) {
		if ( isset( $postarr[ $field ] ) ) {
			$GLOBALS['posts'][ $id ]->$field = $postarr[ $field ];
		}
	}
	// The revision is taken from inside the update, from the meta the post holds right now.
	$type = $GLOBALS['posts'][ $id ]->post_type;
	if ( post_type_supports( $type, 'revisions' ) ) {
		$copy = array();
		foreach ( array_keys( $GLOBALS['revisioned'][ $type ] ?? array() ) as $key ) {
			$copy[ $key ] = $GLOBALS['pmeta'][ $id ][ $key ] ?? null;
		}
		$GLOBALS['revisions'][ $id ][] = $copy;
	}
	return $id;
}
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_posts( $args = array() ) {
	$statuses = (array) ( $args['post_status'] ?? 'publish' );
	$out      = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
			continue;
		}
		if ( ! in_array( $post->post_status, $statuses, true ) ) {
			continue;
		}
		$out[] = 'ids' === ( $args['fields'] ?? '' ) ? $post->ID : $post;
	}
	return $out; // Ascending IDs: the store is keyed by an ID that only grows.
}
function get_post_meta( $id, $key = '', $single = false ) { return $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = wp_unslash( $value ); return true; } // Unslashed, as WordPress does.
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ], $GLOBALS['revisions'][ (int) $id ] ); return true; }

require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-palette.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-tracks.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-store.php';

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

/** A small definition, told apart by its status and key. */
function track( $status, $key, $label = null ) {
	return array(
		'schema_version' => 1,
		'status'         => $status,
		'key'            => $key,
		'label'          => null === $label ? $status : $label,
		'hue'            => 'green',
		'questions'      => array(
			'Hours'         => array( 'label' => 'Hours contributed', 'type' => 'number', 'step' => '1', 'min' => 0, 'max' => 10000, 'group' => 'hours', 'airtable_type' => 'number' ),
			$key . ' notes' => array( 'label' => 'Your notes', 'type' => 'textarea', 'group' => 'project', 'why' => 'A developer note.' ),
		),
	);
}

/** The definition a revision of a post holds, decoded: 0 is the first, -1 the newest. */
function revision( $post_id, $which ) {
	$revisions = $GLOBALS['revisions'][ $post_id ] ?? array();
	$revision  = -1 === $which ? end( $revisions ) : ( $revisions[ $which ] ?? array() );

	return WPCPM_Track_Definition::decode( $revision[ WPCPM_Track_Store::META_DEFINITION ] ?? null );
}

echo "=== Registration ===\n";

WPCPM_Track_Store::init();
ck( 'init() registers the type, then the meta, both on init', array_map( function ( $a ) { return array( $a[0], $a[1][1] ); }, $GLOBALS['actions'] ), array( array( 'init', 'register_post_type' ), array( 'init', 'register_meta' ) ) );

WPCPM_Track_Store::register_meta();
ck( 'registered before its type, the meta would lose its revisions: WordPress refuses them', array( $GLOBALS['wrong'], $GLOBALS['meta_args']['wpcpm_track']['_wpcpm_track_definition']['revisions_enabled'] ), array( array( '_wpcpm_track_definition' ), false ) );
$GLOBALS['wrong']      = array();
$GLOBALS['revisioned'] = array();

WPCPM_Track_Store::register_post_type();
WPCPM_Track_Store::register_meta();
$type = $GLOBALS['types']['wpcpm_track'];
ck( 'the post type is private everywhere', array( $type['public'], $type['publicly_queryable'], $type['show_ui'], $type['show_in_menu'], $type['show_in_rest'], $type['rewrite'], $type['query_var'] ), array( false, false, false, false, false, false, false ) );
ck( 'with a title and revisions', $type['supports'], array( 'title', 'revisions' ) );
ck( 'and a capability type nobody is granted', array( $type['capability_type'], $type['map_meta_cap'] ), array( array( 'wpcpm_track', 'wpcpm_tracks' ), true ) );
ck( 'registered in that order, the definition is revisioned, and nothing else about it is open', $GLOBALS['meta_args']['wpcpm_track']['_wpcpm_track_definition'], array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'revisions_enabled' => true, 'auth_callback' => '__return_false' ) );
ck( 'with no notice', $GLOBALS['wrong'], array() );

echo "\n=== create() and save() ===\n";

$id = WPCPM_Track_Store::create( track( 'Marketing Track', 'marketing' ) );
ck( 'create() hands back the new post', is_int( $id ) && $id > 0, true );
ck( 'a draft of the private type, titled by the track\'s name', array( get_post( $id )->post_type, get_post( $id )->post_status, get_post( $id )->post_title ), array( 'wpcpm_track', 'draft', 'Marketing Track' ) );
ck( 'holding the normalized definition', WPCPM_Track_Store::get( $id ), WPCPM_Track_Definition::normalize( track( 'Marketing Track', 'marketing' ) ) );
ck( 'and the history begins at creation: one revision, holding it', array( count( $GLOBALS['revisions'][ $id ] ), revision( $id, 0 ) ), array( 1, WPCPM_Track_Store::get( $id ) ) );

ck( 'save() hands back the post', WPCPM_Track_Store::save( $id, track( 'Marketing Track', 'marketing', 'Marketing Track, renamed' ) ), $id );
ck( 'the newest revision holds the new definition, not the one before it', array( count( $GLOBALS['revisions'][ $id ] ), revision( $id, -1 )['label'] ), array( 2, 'Marketing Track, renamed' ) );
ck( 'and the title follows the name', get_post( $id )->post_title, 'Marketing Track, renamed' );

$awkward = 'Back\\slash, "quoted", Site' . "\u{2019}" . 's';
WPCPM_Track_Store::save( $id, track( 'Marketing Track', 'marketing', $awkward ) );
ck( 'a name with a backslash, quotes and a typographic apostrophe comes back byte for byte', WPCPM_Track_Store::get( $id )['label'], $awkward );
ck( 'in the revision as well', revision( $id, -1 )['label'], $awkward );
ck( 'and in the title', get_post( $id )->post_title, $awkward );

ck( 'save() refuses a post that is not there', WPCPM_Track_Store::save( 999999, track( 'X Track', 'x-track' ) ) instanceof WP_Error, true );
ck( 'and writes nothing for it', isset( $GLOBALS['pmeta'][999999] ), false );
$plain = wp_insert_post( array( 'post_type' => 'post' ) );
ck( 'and refuses a post of another type', WPCPM_Track_Store::save( $plain, track( 'X Track', 'x-track' ) ) instanceof WP_Error, true );
ck( 'get() answers null for another type of post', WPCPM_Track_Store::get( $plain ), null );
ck( 'and for a post that is not there', WPCPM_Track_Store::get( 424242 ), null );

echo "\n=== compile() ===\n";

$GLOBALS['posts']     = array();
$GLOBALS['pmeta']     = array();
$GLOBALS['revisions'] = array();
$a = WPCPM_Track_Store::create( track( 'Alpha Track', 'alpha' ) );
$b = WPCPM_Track_Store::create( track( 'Beta Track', 'beta' ) );
$c = WPCPM_Track_Store::create( track( 'Gamma Track', 'gamma' ) );
$d = WPCPM_Track_Store::create( track( 'Delta Track', 'delta' ) );
foreach ( array( $a, $b, $d ) as $published ) {
	wp_update_post( array( 'ID' => $published, 'post_status' => 'publish' ) );
}
update_post_meta( $b, WPCPM_Track_Store::META_SOURCE, 'builtin' );
update_post_meta( $a, WPCPM_Track_Store::META_AUTOMATION, '1' );
update_post_meta( $d, WPCPM_Track_Store::META_DEFINITION, '{not json' );

$GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ] = array();
WPCPM_Tracks::rows(); // Read, so this request has the empty index in hand.
$rows = WPCPM_Track_Store::compile();

ck( 'only published tracks are compiled, in the order they were made', array_keys( $rows ), array( 'Alpha Track', 'Beta Track' ) );
ck( 'a definition that cannot be read is left out rather than breaking the rest', isset( $rows['Delta Track'] ), false );
ck( 'the index is autoloaded: labels() reads it for every row of every roster', array( $GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ] === $rows, $GLOBALS['autoload'][ WPCPM_Tracks::OPT_TRACKS ] ), array( true, true ) );
ck( 'each form is an option of its own, not autoloaded', array( $GLOBALS['autoload']['wpcpm_track_fields_alpha'], $GLOBALS['autoload']['wpcpm_track_fields_beta'] ), array( false, false ) );
ck( 'holding the form the live site draws, without the authoring properties', $GLOBALS['opts']['wpcpm_track_fields_alpha'], WPCPM_Track_Definition::compile_fields( WPCPM_Track_Store::get( $a ) ) );
ck( 'a built-in track keeps its source, so the runtime leaves it to its PHP', array( $rows['Alpha Track']['source'], $rows['Beta Track']['source'] ), array( 'definition', 'builtin' ) );
ck( 'the automation tick is carried into the row', array( $rows['Alpha Track']['automation'], $rows['Beta Track']['automation'] ), array( true, false ) );
ck( 'and compiling refreshes what the runtime read this request', WPCPM_Tracks::rows(), $rows );

wp_update_post( array( 'ID' => $a, 'post_status' => 'draft' ) );
$rows = WPCPM_Track_Store::compile();
ck( 'a track taken back to draft leaves the index', array_keys( $rows ), array( 'Beta Track' ) );
ck( 'and its form option goes with it', array_key_exists( 'wpcpm_track_fields_alpha', $GLOBALS['opts'] ), false );

echo "\n=== delete_all(), for uninstall ===\n";

$trashed = WPCPM_Track_Store::create( track( 'Trashed Track', 'trashed' ) );
wp_update_post( array( 'ID' => $trashed, 'post_status' => 'trash' ) );
$orphan = WPCPM_Track_Store::create( track( 'Orphan Track', 'orphan' ) );
update_option( 'wpcpm_track_fields_orphan', array( 'x' => array() ), false ); // Written by a compile that never finished.
$other = wp_insert_post( array( 'post_type' => 'post' ) );

WPCPM_Track_Store::delete_all();

ck( 'every track goes, a draft and a trashed one included', get_posts( array( 'post_type' => 'wpcpm_track', 'post_status' => array_keys( get_post_stati() ), 'fields' => 'ids' ) ), array() );
ck( 'with its revisions', array_values( array_intersect( array_keys( $GLOBALS['revisions'] ), array( $a, $b, $c, $d, $trashed, $orphan ) ) ), array() );
ck( 'the index and every form option go, one that only a post still knew included', array_values( preg_grep( '/^wpcpm_track/', array_keys( $GLOBALS['opts'] ) ) ), array() );
ck( 'and nothing that is not a track is touched', null !== get_post( $other ), true );
ck( 'the runtime forgets what it read', WPCPM_Tracks::rows(), array() );

echo "\n=== Wired into the plugin ===\n";

$uninstall = (string) file_get_contents( __DIR__ . '/../uninstall.php' );
ck( 'uninstall.php loads the store and the two classes it reads', array( false !== strpos( $uninstall, "includes/tracks/class-wpcpm-track-store.php'" ), false !== strpos( $uninstall, "includes/tracks/class-wpcpm-tracks.php'" ), false !== strpos( $uninstall, "includes/tracks/class-wpcpm-track-definition.php'" ) ), array( true, true, true ) );
ck( 'and calls delete_all()', false !== strpos( $uninstall, 'WPCPM_Track_Store::delete_all();' ), true );
$main = (string) file_get_contents( __DIR__ . '/../wpcredits-program-manager.php' );
ck( 'the plugin boots the store and the runtime', array( false !== strpos( $main, 'WPCPM_Track_Store::init();' ), false !== strpos( $main, 'WPCPM_Tracks::init();' ) ), array( true, true ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run it and see it fail.** `php bin/test-track-store.php`. Expected: a fatal, `Failed opening required '.../includes/tracks/class-wpcpm-track-store.php'`.

- [ ] **Step 3: Write the store.** Create `includes/tracks/class-wpcpm-track-store.php`:

```php
<?php
/**
 * Where the Track Builder keeps its tracks: one private post each.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Track definitions as `wpcpm_track` posts, and the compile step the live site runs on.
 *
 * **A draft touches nothing; publishing is the one act that changes the live site** (the
 * design's decision 1.2). The post is where people edit: private, reachable through no generic
 * screen, with the definition in revisioned meta so every saved change is kept. `compile()` is
 * the only writer of what the site runs on (`WPCPM_Tracks`), and publishing (phase T2) will be
 * its only caller, so editing a published track changes nothing students see until the change
 * is published.
 */
final class WPCPM_Track_Store {

	/** Eleven characters; `register_post_type()` refuses a name over twenty. */
	const POST_TYPE = 'wpcpm_track';

	/** The definition, as `WPCPM_Track_Definition::encode()` writes it. Revisioned. */
	const META_DEFINITION = '_wpcpm_track_definition';

	/**
	 * `builtin` for a migrated track its PHP still runs; absent for every other track.
	 *
	 * Written by the migration of phase T2 and cleared by the switch; read here only to carry it
	 * into the compiled row, where `WPCPM_Tracks` leaves such a track to its PHP.
	 */
	const META_SOURCE = '_wpcpm_track_source';

	/** `1` once somebody has ticked the reports automation item of the publish checklist (T2). */
	const META_AUTOMATION = '_wpcpm_track_automation';

	/**
	 * Register the type and its meta.
	 *
	 * The type before the meta, and that order is load-bearing: WordPress refuses
	 * `revisions_enabled`, with a notice, for a type that does not support revisions yet, and
	 * the definition would then be the one thing a revision does not keep.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
	}

	/**
	 * The private post type.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Tracks', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Track', 'wpcredits-program-manager' ),
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
				'supports'            => array( 'title', 'revisions' ),
				// A capability type nobody is granted, so no role reaches a track through any
				// generic post screen; the Track Builder's own handlers are the one way in.
				'capability_type'     => array( 'wpcpm_track', 'wpcpm_tracks' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * The definition's meta, revisioned.
	 *
	 * `revisions_enabled` landed in WordPress 6.4, inside this plugin's 6.5 floor. Not in REST
	 * and not editable through the meta API: `save()` is the one way in, like the semester
	 * report's narratives.
	 */
	public static function register_meta() {
		register_post_meta(
			self::POST_TYPE,
			self::META_DEFINITION,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'revisions_enabled' => true,
				'auth_callback'     => '__return_false',
			)
		);
	}

	/**
	 * Create a track, as a draft.
	 *
	 * The definition goes in through `save()`, so the first revision is taken at once and the
	 * history begins with the track as it was created.
	 *
	 * @param array $definition Track definition.
	 * @return int|WP_Error The post ID, or whatever WordPress refused with.
	 */
	public static function create( array $definition ) {
		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'draft',
					'post_title'  => isset( $definition['label'] ) ? (string) $definition['label'] : '',
				)
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return self::save( (int) $post_id, $definition );
	}

	/**
	 * Store a definition on its track.
	 *
	 * **The meta is written before the post, and that order is load-bearing** (the semester
	 * report's rule): WordPress saves a revision from inside `wp_update_post()`, copying the
	 * revisioned meta the post holds at that moment, so writing the post first would file every
	 * revision one save behind. The meta and the post array are both slashed, because WordPress
	 * unslashes each on the way in, and a backslash in a label would otherwise be lost.
	 *
	 * @param int   $post_id    The track.
	 * @param array $definition Track definition.
	 * @return int|WP_Error The post ID, or why it could not be stored.
	 */
	public static function save( $post_id, array $definition ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );

		if ( ! ( $post instanceof WP_Post ) || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'wpcpm_track_missing', __( 'That track does not exist.', 'wpcredits-program-manager' ) );
		}

		$definition = WPCPM_Track_Definition::normalize( $definition );

		update_post_meta( $post_id, self::META_DEFINITION, wp_slash( WPCPM_Track_Definition::encode( $definition ) ) );

		$updated = wp_update_post(
			wp_slash(
				array(
					'ID'         => $post_id,
					'post_title' => isset( $definition['label'] ) ? (string) $definition['label'] : '',
				)
			),
			true
		);

		return is_wp_error( $updated ) ? $updated : $post_id;
	}

	/**
	 * A track's definition.
	 *
	 * @param int $post_id The track.
	 * @return array|null The definition, or null when the post is not a readable track.
	 */
	public static function get( $post_id ) {
		$post = get_post( (int) $post_id );

		if ( ! ( $post instanceof WP_Post ) || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return WPCPM_Track_Definition::decode( get_post_meta( (int) $post_id, self::META_DEFINITION, true ) );
	}

	/**
	 * Compile every published track into the options the live site runs on.
	 *
	 * Each form is written before the index that names it, so a request never finds a track in
	 * the index without its form. A published track whose definition cannot be read is left out
	 * rather than stopping the others, and the form of a track that has left the index is
	 * deleted with it.
	 *
	 * @return array The index written: status => row.
	 */
	public static function compile() {
		$previous = get_option( WPCPM_Tracks::OPT_TRACKS, array() );
		$rows     = array();

		$posts = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'ID',
				'order'       => 'ASC',
			)
		);

		foreach ( $posts as $post ) {
			$definition = self::get( $post->ID );

			if ( ! is_array( $definition ) || empty( $definition['status'] ) || empty( $definition['key'] ) ) {
				continue;
			}

			$source     = 'builtin' === get_post_meta( $post->ID, self::META_SOURCE, true ) ? 'builtin' : 'definition';
			$automation = '1' === (string) get_post_meta( $post->ID, self::META_AUTOMATION, true );

			update_option( WPCPM_Tracks::OPT_FIELDS_PREFIX . $definition['key'], WPCPM_Track_Definition::compile_fields( $definition ), false );

			$rows[ (string) $definition['status'] ] = WPCPM_Track_Definition::row( $definition, $post->ID, $source, $automation );
		}

		$keys = array_column( $rows, 'key' );

		foreach ( is_array( $previous ) ? $previous : array() as $row ) {
			if ( is_array( $row ) && isset( $row['key'] ) && ! in_array( $row['key'], $keys, true ) ) {
				delete_option( WPCPM_Tracks::OPT_FIELDS_PREFIX . $row['key'] );
			}
		}

		update_option( WPCPM_Tracks::OPT_TRACKS, $rows, true );
		WPCPM_Tracks::flush();

		return $rows;
	}

	/**
	 * Delete every track, its revisions and the options compiled from it. Called on uninstall.
	 *
	 * Every status, trash included, which `any` would leave behind. A form option is deleted for
	 * the key of every track post as well as for every key the index names, so a form written by
	 * a compile that never finished goes too.
	 */
	public static function delete_all() {
		$ids = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => array_keys( get_post_stati() ),
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		foreach ( $ids as $post_id ) {
			$definition = self::get( $post_id );

			if ( is_array( $definition ) && ! empty( $definition['key'] ) ) {
				delete_option( WPCPM_Tracks::OPT_FIELDS_PREFIX . $definition['key'] );
			}

			wp_delete_post( (int) $post_id, true );
		}

		foreach ( (array) get_option( WPCPM_Tracks::OPT_TRACKS, array() ) as $row ) {
			if ( is_array( $row ) && isset( $row['key'] ) ) {
				delete_option( WPCPM_Tracks::OPT_FIELDS_PREFIX . $row['key'] );
			}
		}

		delete_option( WPCPM_Tracks::OPT_TRACKS );
		WPCPM_Tracks::flush();
	}
}
```

- [ ] **Step 4: Run it and see the wiring checks fail.** `php bin/test-track-store.php`. Expected: every check passes but the three under "Wired into the plugin": `3 FAILED (40 checks)`.

- [ ] **Step 5: Load the four classes and boot two of them.** Apply this change to `wpcredits-program-manager.php`:

```diff
--- a/wpcredits-program-manager.php
+++ b/wpcredits-program-manager.php
@@ -43,6 +43,10 @@
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-contribution-teams.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-palette.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-definition.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-tracks.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-store.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-updates.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-agreement-template.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-two-factor.php';
@@ -158,6 +162,10 @@
 	WPCPM_Privacy_Guard::init();
 	WPCPM_Notices::init();
 	WPCPM_Mail::init();
+	// The Track Builder's tracks reach the program map through its filters (1.100.0), hooked
+	// before any module asks the map anything.
+	WPCPM_Track_Store::init();
+	WPCPM_Tracks::init();
 	WPCPM_Modules::boot();
 	WPCPM_Tools::boot();
 	WPCPM_Dashboards::init();
```

- [ ] **Step 6: Load them on uninstall too, and sweep.** Apply this change to `uninstall.php`. All four requires, not only the three the sweep uses: `bin/test-roles.php` holds `uninstall.php` to every class the loader requires, because a class missing there was once a silent fatal in the middle of cleanup.

```diff
--- a/uninstall.php
+++ b/uninstall.php
@@ -51,6 +51,10 @@
 require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-pdf-check.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-form-guard.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-form-stash.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-track-palette.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-track-definition.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-tracks.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-track-store.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-module.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-sync-module.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-students.php';
@@ -186,6 +190,11 @@
 foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'wpcpm_institution_modules_' ) . '%' ) ) as $wpcpm_module_option ) {
 	delete_option( $wpcpm_module_option );
 }
+
+// The Track Builder's tracks (1.100.0): every definition post with its revisions, and the
+// options the live site runs on.
+WPCPM_Track_Store::delete_all();
+
 delete_option( WPCPM_Notices::OPT_PLAIN );
 delete_metadata( 'post', 0, WPCPM_Notices::META_AUDIENCE, '', true );
 
```

- [ ] **Step 7: Run it and see it pass.** `php bin/test-track-store.php`. Expected: `ALL PASS (40 checks)`.

- [ ] **Step 8: Hold `includes/tracks/` to the loader.** The rule "every class file under includes/ is required by the loader" globs three folders, so a class that lands in `includes/tracks/` without a `require_once` would never load and no check would say so. Apply this change to `bin/test-roles.php`:

```diff
--- a/bin/test-roles.php
+++ b/bin/test-roles.php
@@ -352,7 +352,8 @@
 // listed here, so the next file is covered the day it lands.
 preg_match_all( "/require_once WPCPM_PLUGIN_DIR \. '([^']+)';/", $loader_src, $anywhere );
 $on_disk = array();
-foreach ( array( 'includes', 'includes/modules', 'includes/tools' ) as $dir ) {
+// `includes/tracks` since the Track Builder's classes landed there (1.100.0).
+foreach ( array( 'includes', 'includes/modules', 'includes/tools', 'includes/tracks' ) as $dir ) {
 	foreach ( glob( dirname( __DIR__ ) . '/' . $dir . '/class-wpcpm-*.php' ) as $path ) {
 		$on_disk[] = $dir . '/' . basename( $path );
 	}
```

- [ ] **Step 9: Prove the rule bites, then that it passes.**

```bash
printf '<?php\nclass WPCPM_Dummy {}\n' > includes/tracks/class-wpcpm-dummy.php
php bin/test-roles.php | grep -A2 "every class file under includes"
rm includes/tracks/class-wpcpm-dummy.php
php bin/test-roles.php | tail -1
```

Expected: first `FAIL every class file under includes/ is required by the loader` with `includes/tracks/class-wpcpm-dummy.php` as the actual value; then `ALL PASS`. The dummy file is removed before anything is committed.

- [ ] **Step 10: Run the battery and the three static checks, and phpcs.** Expected: silent and clean; no ` ERROR `; no new warning (phpcs's hits on `uninstall.php` are the institution-modules query it already had, three lines lower).

- [ ] **Step 11: Commit.**

```bash
git add includes/tracks/class-wpcpm-track-store.php bin/test-track-store.php wpcredits-program-manager.php uninstall.php bin/test-roles.php
git commit -m "Track Builder T1: the wpcpm_track store with a revisioned definition, the compile and the uninstall sweep, loaded and booted"
```

---

### Task 5: The two places that kept track lists of their own

**Files:**
- Modify: `includes/modules/class-wpcpm-administrators-cards.php` (the `TRACKS` constant becomes `tracks()`)
- Modify: `includes/modules/class-wpcpm-institutions.php` (`automation_statuses()`)
- Test: `bin/test-administrators-dashboard.php`, `bin/test-unlinked-link.php`

**Interfaces:**
- Consumes: Task 1's filtered `WPCPM_Program::track()`; Task 3's `WPCPM_Tracks::confirmed_automation_statuses()`, `::OPT_TRACKS` and `::flush()`.
- Produces: `WPCPM_Administrators_Cards::tracks(): array` (track key => name, in `WPCPM_Program::labels()` order), replacing the removed `TRACKS` constant; `WPCPM_Institutions::automation_statuses(): string[]` (the pinned `AUTOMATION_STATUSES`, then every live track whose automation item is ticked), which `link_block()` now reads.

- [ ] **Step 1: Write the failing checks.** Apply these changes to the two suites. The dashboard suite's `apply_filters()` stand-in becomes a pass-through that also runs filters a check places in `$GLOBALS['live_filters']`; `add_filter()` still only records, so nothing the plugin hooks at load time starts running in the suite.

```diff
--- a/bin/test-administrators-dashboard.php
+++ b/bin/test-administrators-dashboard.php
@@ -83,7 +83,20 @@
 function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
 function wp_unslash( $v ) { return $v; }
 function absint( $v ) { return abs( (int) $v ); }
-function apply_filters( $t, $v ) { return $v; }
+/*
+ * A pass-through, except for the filters a check puts in `$GLOBALS['live_filters']` (1.100.0): the
+ * Programs running card's tiles are the program map's tracks, and the only way to show a track
+ * added to the map is to add one. `add_filter()` below still records calls without running them,
+ * so nothing the plugin hooks at load time starts running here.
+ */
+$GLOBALS['live_filters'] = array();
+function apply_filters( $t, $v ) {
+	foreach ( $GLOBALS['live_filters'][ $t ] ?? array() as $callback ) {
+		$v = $callback( $v );
+	}
+
+	return $v;
+}
 function add_action( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['calls'][] = array( 'add_action', $h, $p ); }
 function add_filter( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['calls'][] = array( 'add_filter', $h, $p ); }
 function add_shortcode( $tag, $c ) { $GLOBALS['calls'][] = array( 'add_shortcode', $tag ); }
@@ -797,9 +810,9 @@
 	$answerable[] = WPCPM_Program::track( $status );
 }
 ck( 'every track the program map can answer has a tile on the strip',
-    array_values( array_diff( array_filter( $answerable, 'strlen' ), WPCPM_Administrators_Cards::TRACKS ) ), array() );
+    array_values( array_diff( array_filter( $answerable, 'strlen' ), array_keys( WPCPM_Administrators_Cards::tracks() ) ) ), array() );
 ck( 'and the Designer Track is one of them, counted like the rest',
-    array( in_array( 'design', WPCPM_Administrators_Cards::TRACKS, true ), isset( $programs['tracks']['design']['in_progress'] ) ), array( true, true ) );
+    array( in_array( 'design', array_keys( WPCPM_Administrators_Cards::tracks() ), true ), isset( $programs['tracks']['design']['in_progress'] ) ), array( true, true ) );
 ck( 'signed up this semester per track, from the start date', array( $programs['tracks']['150h']['signed_up'], $programs['tracks']['50h']['signed_up'], $programs['tracks']['dev']['signed_up'] ), array( 1, 0, 1 ) );
 ck( 'finished this semester is one number: a graduate no longer says their track', $programs['finished'], 1 );
 // A Dropped out row, ending inside the same cohort as the one Graduate row above: finished
@@ -920,7 +933,7 @@
 // <ul class="wpcpm-programs__tiles"> - which matches the needle as well as the tiles it
 // contains, the same reason the strip count above is nine. Counted from the class rather than
 // written down, so adding a track moves this number by itself.
-ck( 'the programs card draws a tile per track and a finished tile', substr_count( $prog, 'wpcpm-programs__tile' ), count( WPCPM_Administrators_Cards::TRACKS ) + 2 );
+ck( 'the programs card draws a tile per track and a finished tile', substr_count( $prog, 'wpcpm-programs__tile' ), count( array_keys( WPCPM_Administrators_Cards::tracks() ) ) + 2 );
 ck( 'the institution row links through the switcher and carries its numbers', has( $prog, 'wpcpm_institution_view=' . $A ) && has( $prog, '2026-12-15' ) && has( $prog, 'Uniwersytet Alpha' ), true );
 ck( 'the quiet institutions are one closing line', has( $prog, '1 more institution' ), true );
 ck( 'and the read time is printed', has( $prog, 'Read from the program records' ), true );
@@ -1201,5 +1214,26 @@
 
 $GLOBALS['tz'] = 'UTC';
 
+echo "\n=== A track the program map is given has its tile (1.100.0) ===\n";
+
+$GLOBALS['live_filters']['wpcpm_program_labels'][] = static function ( $labels ) {
+	$labels['Research Track'] = 'Research Track';
+	return $labels;
+};
+$GLOBALS['live_filters']['wpcpm_program_tracks'][] = static function ( $tracks ) {
+	$tracks['Research Track'] = 'research';
+	return $tracks;
+};
+
+ck( 'the tiles are the program map\'s tracks, the built-in four first', WPCPM_Administrators_Cards::tracks(), array( '150h' => 'WordPress Credits Program 150h', '50h' => 'WordPress Credits Program 50h', 'dev' => 'Developer Track', 'design' => 'Designer Track', 'research' => 'Research Track' ) );
+
+ob_start();
+WPCPM_Administrators_Cards::render_programs( array( 'tracks' => array(), 'finished' => 0, 'rows' => array(), 'quiet' => 0, 'read' => 0, 'semester' => '' ) );
+$research_card = ob_get_clean();
+
+ck( 'and the Programs running card draws a tile for the new one, counted from nothing', has( $research_card, '<span class="wpcpm-programs__name">Research Track</span><span class="wpcpm-programs__n">0</span>' ), true );
+
+$GLOBALS['live_filters'] = array();
+
 printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILED', $fail ) : 'ALL PASS', $total );
 exit( $fail ? 1 : 0 );
```

```diff
--- a/bin/test-unlinked-link.php
+++ b/bin/test-unlinked-link.php
@@ -914,6 +914,24 @@
 
 ck( 'no dash but the plain hyphen in either file', $dashes, array() );
 
+echo "\n=== A Track Builder track, once its automation item is ticked (1.100.0) ===\n";
+
+require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-tracks.php';
+
+$GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ] = array(
+	'Marketing Track' => array( 'key' => 'marketing', 'label' => 'Marketing Track', 'course_url' => '', 'course_id' => 0, 'hours' => null, 'hue' => 'cyan', 'source' => 'definition', 'automation' => false, 'post' => 7 ),
+);
+WPCPM_Tracks::flush();
+ck( 'until somebody ticks the item, the site has no reason to think the automation watches the track', blocked( roster_row( $CLEAN, array( 'has_mentor' => true, 'status' => 'Marketing Track' ) ) ) !== WPCPM_Institutions::LINK_AUTOMATION, true );
+
+$GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ]['Marketing Track']['automation'] = true;
+WPCPM_Tracks::flush();
+ck( 'once it is ticked, a mentored row at the track is refused like the five', blocked( roster_row( $CLEAN, array( 'has_mentor' => true, 'status' => 'Marketing Track' ) ) ), WPCPM_Institutions::LINK_AUTOMATION );
+ck( 'and the list the guard reads is the pinned five, then the ticked track', WPCPM_Institutions::automation_statuses(), array( 'In Sensei', 'In Sensei Self-onboarding', 'In Sensei 50h', 'Developer Track', 'Designer Track', 'Marketing Track' ) );
+
+unset( $GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ] );
+WPCPM_Tracks::flush();
+
 printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
 
 exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run them and see them fail.** `php bin/test-administrators-dashboard.php`: a fatal, `Call to undefined method WPCPM_Administrators_Cards::tracks()`. `php bin/test-unlinked-link.php`: `FAIL once it is ticked, a mentored row at the track is refused like the five`, then a fatal, `Call to undefined method WPCPM_Institutions::automation_statuses()`.

- [ ] **Step 3: Write the code.** Apply these changes:

```diff
--- a/includes/modules/class-wpcpm-administrators-cards.php
+++ b/includes/modules/class-wpcpm-administrators-cards.php
@@ -26,17 +26,6 @@
 	const LIMIT = 50;
 	/** Closed requests shown under the open ones. */
 	const CLOSED_SHOWN = 20;
-	/**
-	 * The track keys `WPCPM_Program::track()` answers, in the order the strip draws them.
-	 *
-	 * Every key it can answer has to be here. The counting loop increments `$tracks[ $track ]`
-	 * for any track a student is on, and the strip draws only what this list names, so a track
-	 * left out is one whose students are added to a tile that is never shown - and, on PHP 8, a
-	 * warning on every load of the page. It is not derived from `WPCPM_Program` because the
-	 * order is a display decision: newest track last, so the tiles do not move under a reader
-	 * who has learned where to look.
-	 */
-	const TRACKS = array( '150h', '50h', 'dev', 'design' );
 
 	/*
 	 * --------------------------------------------------------------------
@@ -809,6 +798,33 @@
 	}
 
 	/**
+	 * The tracks the Programs running card draws a tile for: track key => name, in the order
+	 * `WPCPM_Program::labels()` lists them.
+	 *
+	 * Derived from the program map since 1.100.0, when the Track Builder made a track something
+	 * the site can gain without a release. Until then this was a constant beside the map, and a
+	 * track missing from it was one whose students were counted into a tile never drawn - and, on
+	 * PHP 8, a warning on every load of the page. The order is still newest last: the map lists
+	 * the built-in tracks first and the Track Builder's after them in the order they were made,
+	 * so the tiles do not move under a reader who has learned where to look.
+	 *
+	 * @return array<string, string>
+	 */
+	public static function tracks() {
+		$tracks = array();
+
+		foreach ( WPCPM_Program::labels() as $status => $label ) {
+			$key = WPCPM_Program::track( $status );
+
+			if ( '' !== $key && ! isset( $tracks[ $key ] ) ) {
+				$tracks[ $key ] = (string) $label;
+			}
+		}
+
+		return $tracks;
+	}
+
+	/**
 	 * The programs running: totals per track, and one row per institution with somebody in progress.
 	 *
 	 * Reads only `status`, `start`, `end`, `reports` and `mentor_name` off roster rows, and
@@ -837,7 +853,7 @@
 		$quiet    = 0;
 		$read     = 0;
 
-		foreach ( self::TRACKS as $track ) {
+		foreach ( array_keys( self::tracks() ) as $track ) {
 			$tracks[ $track ] = array(
 				'in_progress' => 0,
 				'signed_up'   => 0,
@@ -884,7 +900,7 @@
 				$track = WPCPM_Program::track( $status );
 				$end   = self::day( isset( $row['end'] ) ? $row['end'] : '' );
 
-				if ( '' !== $track && WPCPM_Cohort::key( isset( $row['start'] ) ? $row['start'] : '' ) === $semester ) {
+				if ( isset( $tracks[ $track ] ) && WPCPM_Cohort::key( isset( $row['start'] ) ? $row['start'] : '' ) === $semester ) {
 					++$tracks[ $track ]['signed_up'];
 				}
 
@@ -894,7 +910,7 @@
 					$label               = WPCPM_Program::label( $status );
 					$by_status[ $label ] = ( isset( $by_status[ $label ] ) ? $by_status[ $label ] : 0 ) + 1;
 
-					if ( '' !== $track ) {
+					if ( isset( $tracks[ $track ] ) ) {
 						++$tracks[ $track ]['in_progress'];
 					}
 
@@ -1480,16 +1496,9 @@
 
 		self::card_open( 'programs', __( 'Programs running', 'wpcredits-program-manager' ), count( $rows ) );
 
-		$names = array(
-			'150h'   => WPCPM_Program::label( WPCPM_Program::STATUS_150H ),
-			'50h'    => WPCPM_Program::label( WPCPM_Program::STATUS_50H ),
-			'dev'    => WPCPM_Program::label( WPCPM_Program::STATUS_DEV ),
-			'design' => WPCPM_Program::label( WPCPM_Program::STATUS_DESIGN ),
-		);
-
 		echo '<ul class="wpcpm-programs__tiles">';
 
-		foreach ( self::TRACKS as $track ) {
+		foreach ( self::tracks() as $track => $name ) {
 			$tile = isset( $programs['tracks'][ $track ] ) ? $programs['tracks'][ $track ] : array(
 				'in_progress' => 0,
 				'signed_up'   => 0,
@@ -1497,7 +1506,7 @@
 
 			printf(
 				'<li class="wpcpm-programs__tile"><span class="wpcpm-programs__name">%1$s</span><span class="wpcpm-programs__n">%2$s</span><span class="wpcpm-programs__l">%3$s</span></li>',
-				esc_html( $names[ $track ] ),
+				esc_html( $name ),
 				esc_html( number_format_i18n( (int) $tile['in_progress'] ) ),
 				esc_html( sprintf( /* translators: %s: a number of students. */ __( 'in progress, %s signed up this semester', 'wpcredits-program-manager' ), number_format_i18n( (int) $tile['signed_up'] ) ) )
 			);
```

```diff
--- a/includes/modules/class-wpcpm-institutions.php
+++ b/includes/modules/class-wpcpm-institutions.php
@@ -645,7 +645,7 @@
 	 *
 	 * **The write this handler makes is what fires an automation, so the row is read again
 	 * before it is made.** Setting `Educational Institutions` on a Students row that already
-	 * carries a mentor at one of `AUTOMATION_STATUSES` completes the condition
+	 * carries a mentor at one of `automation_statuses()` completes the condition
 	 * `Add students to Students Reports and Feedback` watches, and it creates a second
 	 * Students Reports row for a student who has one. The same is true one step removed when
 	 * a reports row already exists for the address. Both are refused, and refused against a
@@ -832,6 +832,26 @@
 	}
 
 	/**
+	 * The statuses the reports automation is known to watch: the pinned list, and every Track
+	 * Builder track whose publish checklist says somebody added it to the automation's condition.
+	 *
+	 * The site cannot read an automation (no token scope reaches one), so for a track made on the
+	 * site the only source is the person who changed the automation and ticked the item.
+	 * `class_exists()` because the suites that load this class do not all load the tracks.
+	 *
+	 * @return string[]
+	 */
+	public static function automation_statuses() {
+		$statuses = self::AUTOMATION_STATUSES;
+
+		if ( class_exists( 'WPCPM_Tracks' ) ) {
+			$statuses = array_merge( $statuses, WPCPM_Tracks::confirmed_automation_statuses() );
+		}
+
+		return array_values( array_unique( $statuses ) );
+	}
+
+	/**
 	 * Why this row may not be linked, or '' when it may.
 	 *
 	 * **One rule, asked twice.** The card asks it of the index row so it does not offer a
@@ -853,7 +873,7 @@
 
 		$status = trim( (string) ( isset( $row['status'] ) ? $row['status'] : '' ) );
 
-		if ( ! empty( $row['has_mentor'] ) && in_array( $status, self::AUTOMATION_STATUSES, true ) ) {
+		if ( ! empty( $row['has_mentor'] ) && in_array( $status, self::automation_statuses(), true ) ) {
 			return self::LINK_AUTOMATION;
 		}
 
```

- [ ] **Step 4: Run them and see them pass.** Expected: `php bin/test-administrators-dashboard.php` prints `ALL PASS (132 checks)`; `php bin/test-unlinked-link.php` prints `ALL PASS (72 checks)`.

- [ ] **Step 5: Run the battery and the three static checks, and phpcs.** Expected: silent and clean; no ` ERROR `; no new warning. `php bin/check-references.php` would name any `WPCPM_Administrators_Cards::TRACKS` left anywhere.

- [ ] **Step 6: Commit.**

```bash
git add includes/modules/class-wpcpm-administrators-cards.php includes/modules/class-wpcpm-institutions.php bin/test-administrators-dashboard.php bin/test-unlinked-link.php
git commit -m "Track Builder T1: the Programs running card and the Link control's automation guard ask the program map for the tracks"
```

---

### Task 6: Proof on a real WordPress

The suites stand in for WordPress; three things only WordPress can answer. Whether a save that changes nothing but the definition makes a revision (WordPress 6.4 compares revisioned meta when it decides whether anything changed). Whether that revision holds the new definition rather than the old one. And whether a slashed definition with a backslash, quotes and U+2019 comes back byte for byte through the real meta API. This task answers them on a throwaway local site, never the live one.

- [ ] **Step 1: Ask the product owner two things, and wait for both.** First, to open the WordPress Studio app once and let it update: on 10 September 2026 the CLI refused to run with "Studio is installed, but your config must be migrated by Studio before this CLI can run". Second, for a yes to creating a local site, which downloads WordPress core.

- [ ] **Step 2: Create the site and install the branch's build.** `SCRATCH` is the implementer's own scratch directory; nothing of this lands in the plugin.

```bash
SITE="$HOME/Studio/wpcpm-t1-check"
studio create --path "$SITE" --name "WPCPM T1 check" --skip-browser --skip-log-details
bash bin/build "$SCRATCH/wpcpm-t1.zip"
(cd "$SITE" && studio wp plugin install "$SCRATCH/wpcpm-t1.zip" --activate)
```

Expected: the plugin activates. It has no Airtable settings on this site, so no sync runs.

- [ ] **Step 3: Write the check.** Create `$SCRATCH/t1-real-wordpress.php`:

```php
<?php
/**
 * Track Builder T1 on a real WordPress: the checks the stubbed suites cannot make.
 *
 * Run inside the Studio site's folder:  studio wp eval-file <this file>
 */

$t1_fails = 0;

function t1_ck( $label, $ok ) {
	global $t1_fails;

	if ( ! $ok ) {
		++$t1_fails;
	}

	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
}

$registered = get_registered_meta_keys( 'post', WPCPM_Track_Store::POST_TYPE );
t1_ck( 'the definition meta is registered with revisions on', ! empty( $registered[ WPCPM_Track_Store::META_DEFINITION ]['revisions_enabled'] ) );
t1_ck( 'and WordPress counts it among the type\'s revisioned keys', in_array( WPCPM_Track_Store::META_DEFINITION, wp_post_revision_meta_keys( WPCPM_Track_Store::POST_TYPE ), true ) );

$awkward    = 'Back\\slash, "quoted", Site' . "\u{2019}" . 's notes';
$definition = array(
	'schema_version' => 1,
	'status'         => 'T1 Check Track',
	'key'            => 't1-check',
	'label'          => 'T1 Check Track',
	'hue'            => 'green',
	'questions'      => array(
		'T1 check notes' => array( 'label' => $awkward, 'type' => 'textarea', 'group' => 'project' ),
		'T1 check link'  => array( 'label' => 'A link', 'type' => 'url', 'group' => 'project' ),
	),
);

$id = WPCPM_Track_Store::create( $definition );
t1_ck( 'create() makes a post', is_int( $id ) && $id > 0 );

$revisions = wp_get_post_revisions( $id );
$newest    = reset( $revisions );
t1_ck( 'the history begins at creation: one revision', 1 === count( $revisions ) );
t1_ck( 'and it holds the created definition', WPCPM_Track_Definition::decode( get_post_meta( $newest->ID, WPCPM_Track_Store::META_DEFINITION, true ) ) === WPCPM_Track_Store::get( $id ) );
t1_ck( 'the awkward label came back byte for byte', $awkward === WPCPM_Track_Store::get( $id )['questions']['T1 check notes']['label'] );

$changed = $definition;
$changed['questions']['T1 check link']['label'] = 'A link to your work';
WPCPM_Track_Store::save( $id, $changed );
$revisions = wp_get_post_revisions( $id );
$newest    = reset( $revisions );
t1_ck( 'a save that changes only the definition still adds a revision', 2 === count( $revisions ) );
t1_ck( 'and the newest revision holds the new definition, not the one before it', 'A link to your work' === WPCPM_Track_Definition::decode( get_post_meta( $newest->ID, WPCPM_Track_Store::META_DEFINITION, true ) )['questions']['T1 check link']['label'] );

$before = array();
foreach ( array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track' ) as $status ) {
	$before[ $status ] = array( WPCPM_Program::label( $status ), WPCPM_Program::track( $status ), WPCPM_Program::hours_target( $status ), WPCPM_Program::course_id( $status ) );
}

wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
WPCPM_Track_Store::compile();

t1_ck( 'published and compiled, the track is in the program map', 'T1 Check Track' === WPCPM_Program::label( 'T1 Check Track' ) && 't1-check' === WPCPM_Program::track( 'T1 Check Track' ) );
t1_ck( 'with no hours target', 0 === WPCPM_Program::hours_target( 'T1 Check Track' ) );
t1_ck( 'and its own form', array( 'T1 check notes', 'T1 check link' ) === array_keys( WPCPM_Student_Report_Form::fields( 't1-check' ) ) );

$after = array();
foreach ( array_keys( $before ) as $status ) {
	$after[ $status ] = array( WPCPM_Program::label( $status ), WPCPM_Program::track( $status ), WPCPM_Program::hours_target( $status ), WPCPM_Program::course_id( $status ) );
}
t1_ck( 'the four built-in tracks answer exactly as they did', $before === $after );

WPCPM_Tracks::add_badge_styles();
$inline = (array) wp_styles()->get_data( WPCPM_Mentors_Dashboard::STYLE, 'after' );
t1_ck( 'the chip rule rides on the dashboard stylesheet', false !== strpos( implode( "\n", $inline ), '.wpcpm-badge--t1-check{' ) );

WPCPM_Track_Store::delete_all();
t1_ck( 'delete_all() leaves no track post', array() === get_posts( array( 'post_type' => WPCPM_Track_Store::POST_TYPE, 'post_status' => array_keys( get_post_stati() ), 'numberposts' => -1, 'fields' => 'ids' ) ) );
t1_ck( 'and no compiled option', false === get_option( WPCPM_Tracks::OPT_TRACKS ) && false === get_option( WPCPM_Tracks::OPT_FIELDS_PREFIX . 't1-check' ) );

echo $t1_fails ? "\n{$t1_fails} FAILED\n" : "\nALL PASS\n";
```

- [ ] **Step 4: Run it.** `(cd "$SITE" && studio wp eval-file "$SCRATCH/t1-real-wordpress.php")`. Expected: `ALL PASS`. If "a save that changes only the definition still adds a revision" fails, stop and report it: T2's history screen depends on it, and the answer is a design question for the product owner rather than a code fix.

- [ ] **Step 5: Delete the site.** `studio delete --help` says whether it asks to confirm; delete `$SITE` with it, and say in the task report that it is gone.

---

### Task 7: Release 1.100.0

**Files:**
- Modify: `wpcredits-program-manager.php` (the `Version:` header and `WPCPM_VERSION`)
- Modify: `readme.txt` (`Stable tag:` and a changelog entry)
- Modify: `languages/wpcredits-program-manager.pot` (regenerated)

- [ ] **Step 1: Move the version.** `1.99.3` becomes `1.100.0` in the plugin header's `Version:` line, in `define( 'WPCPM_VERSION', ... )` and in `readme.txt`'s `Stable tag:`.

- [ ] **Step 2: Write the changelog entry**, first under `== Changelog ==` in `readme.txt`:

```text
= 1.100.0 =

* Groundwork for the Track Builder, the module that will let Program Administrators create and edit program tracks on the site (phase T1 of the design in docs/specs/2026-09-10-track-builder-design.md). Nothing changes on any page: while no track has been made on the site, every program map, form and card answers exactly as before. New underneath: the private `wpcpm_track` post type, whose definition is kept in revisions; the options the live site will read a published track from; and the rules a track's definition has to pass, every one of which accepts all 115 questions of the four existing Student Report Card forms.
* `WPCPM_Program::track()` is filtered (`wpcpm_program_tracks`), the program map now carries each track's Learn WordPress course ID (`course_ids()` and `course_id()`, filtered as `wpcpm_program_course_ids`), and it names its two states on no track (`states()`).
* The Administrator Dashboard's Programs running card and the Institutions Link control's automation guard now ask the program map for the tracks instead of keeping lists of their own, so a track added later reaches both without a change to either.
```

- [ ] **Step 3: Regenerate the translation template.** `sh bin/make-pot.sh`. Expected: the new strings (the validation messages, the post type's two labels, "That track does not exist.") appear in `languages/wpcredits-program-manager.pot`.

- [ ] **Step 4: Run everything.** The battery, the three static checks and phpcs, as in the Global Constraints. Expected: silent and clean.

- [ ] **Step 5: Build the zip and read the version back out of it.**

```bash
bash bin/build
unzip -p ../wpcredits-program-manager.zip wpcredits-program-manager/wpcredits-program-manager.php | grep "Version:"
```

Expected: `Version:           1.100.0`, and the archive lists `includes/tracks/` and no `bin/` or `docs/`.

- [ ] **Step 6: Commit.**

```bash
git add wpcredits-program-manager.php readme.txt languages/wpcredits-program-manager.pot
git commit -m "Track Builder T1: 1.100.0"
```

- [ ] **Step 7: Merge and mirror.** Merge `track-builder-t1` into `main` (fast-forward). Then push the source to the public mirror, which the product owner asked for after every version bump: rsync the plugin into `Education/WordPress Education Dashboard/wpcredits-program-manager/` of the `WordPress/WPCredits` clone (branch `trunk`, clone at `~/GitHub/Plugins/WPCredits-Tracker-mirror`) with `rsync -a --delete --exclude '.git/' --exclude '.DS_Store' --exclude 'node_modules/' --exclude '*.zip' --exclude '.*.swp'`, update the version table in that folder's `README.md`, read `git diff --stat` there before committing, then commit and push as two separate steps.

- [ ] **Step 8: Deploy only on the product owner's yes.** Ask first. On a yes, follow the deploy recorded for `wordpresseducation.org` (stream the zip over `ssh wpcredits-dashboard` rather than `scp`, check its md5 on arrival, install it over the old one, purge the edge cache). Then confirm on the live site that the Administrator Dashboard's Programs running card still shows the same four tiles and that a Student Report Card renders.

---

## What T1 leaves for T2

- `normalize()` turns on `hide_from_institution` for every `email` question. Before T2 compares a built-in track's definition with its PHP, the three alumni specs in `WPCPM_Student_Report_Form::fields()` (`ALUMNI_FIELDS`, the email among them) gain `'hide_from_institution' => true` in the PHP too (spec section 8), and `for_institution()` starts reading the flag.
- A built-in track's definition is created with `locked` set to its own status and key, which is the only way `validate()` lets it keep a reserved key such as `design`.
- `META_SOURCE` and `META_AUTOMATION` are read by `compile()` and written by nothing yet: the migration writes the first, the checklist the second.
- The seed definitions go to `includes/tracks/seeds/<key>.json` (spec section 8), which `bin/test-roles.php` does not glob: it only looks for `class-wpcpm-*.php`.
- `validation_context()` knows the columns the syncs own by name. The rule that refuses computed and link columns from the live schema is T2's preflight (spec 4.2 and 7.1).
- **What `compile()` reads** (the design's open item 5, from the final review of T1). `compile()` builds every published track from its latest saved definition, so an edit saved to a published track reaches students at the next compile of any track. T2 records the published definition at publish time and has `compile()` read it, and a test in `bin/test-track-store.php` pins decision 3.2: publishing Beta leaves Alpha's saved edit unpublished.
- **`compile()` checks what it compiles** (open item 6), as T2's first task and before anything calls `compile()`: `validate()` for each definition, with `validation_context()` for its own status and `locked` for a built-in track only, and nothing compiled for a definition that fails or for a later post claiming a status or key already taken (the first by post ID keeps it). The drift guard on `reserved_columns()` landed with T1, in `bin/test-report-form.php`.
- Publishing, unpublishing, the switch and ticking checklist item 1 all reach the live site through `compile()`, so each of them depends on the two bullets above.
- `validate()` has three rules to tighten. The number-alone column rule tests the string after the cast, so `-1` (an integer array key) passes while `007` is refused: test `is_int()` on the raw key. Status and column lengths are counted in bytes with `strlen()`: count characters with `mb_strlen()`. The reserved columns are matched exactly, so `status`, `STATUS` and `Status ` pass while `Status` is refused: compare ignoring case and surrounding spaces.
- `save()` returns a `WP_Error` when `encode()` yields nothing. A `max` of `INF` passes `validate()` and cannot be written as JSON; today the meta is left empty and `create()` still returns the post ID.
- The uninstall sweeps `wpcpm_track_fields_%` by `LIKE`, the way `uninstall.php` already sweeps another option family, so a form option orphaned by a lost index entry goes too. A real-WordPress check runs `delete_all()` with the post type unregistered, as uninstall does.
- Every request reads `wpcpm_tracks` at `init` 20, and until the first compile the option does not exist, which costs a query on a host with no persistent object cache: create it empty on upgrade, or attach the chip rules at `wp_enqueue_scripts`.
- `maxlength` on a textarea is the design's open item 7, for the product owner before T3.
- A label equal to another track's label or status, ignoring case, is refused: the institution import matches a spreadsheet's program cell against either, and the Programs running card draws its tiles by name. Two drafts that collide are caught only at publish, because the check reads live tracks.
- The institution import form and institution create read `labels()`, so they offer a track from its first compile, before checklist item 1 is ticked, and a student put on it then gets no report row (spec 2.4). Decide whether they wait for the tick.
- Handoff note 1 starts as a failing test: normalizing the Developer and Designer Track forms differs from their PHP by exactly the `hide_from_institution` flag on the alumni email.
- Small things that waited: `fold()` lower-cases ASCII only (if it changes, `mb_strtolower()` behind `function_exists()`, which WordPress does not polyfill); the key pattern `/^[a-z0-9-]{2,20}$/` is written in both the palette and the definition (one constant on the palette would do); the store checks "is this a readable track" twice, in `save()` and `get()`; the docblock of `AUTOMATION_STATUSES` should point to `automation_statuses()`; the definition's class docblock counts three authoring keys, which is true once `hide_from_institution` is in the PHP.
