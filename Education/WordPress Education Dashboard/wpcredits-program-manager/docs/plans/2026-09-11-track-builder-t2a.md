# Track Builder, phase T2a: implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship plugin 1.101.0 with nothing changed on any page and everything the Track Builder screen will stand on in place: the rules tightened, a live site that runs only what was published and checks it again at every compile, the four built-in tracks seeded as definitions held byte for byte to their PHP, and publishing, unpublishing and the switch as the store logic phase T2b's screen will call.

**Architecture:** No class is added. `WPCPM_Track_Store` gains the published copy (`META_PUBLISHED`), `publish()`, `unpublish()`, a log, the seeding and the switch, and its `compile()` reads published copies only, each checked by `WPCPM_Track_Definition::validate()` against the site as its PHP describes it and the tracks compiled before it. `WPCPM_Tracks` can read the program map with its own filters suspended. The report form's hand-written branches move into `builtin_fields()`, and the seeds are JSON files built offline by `bin/build-seeds.php`, which `bin/test-track-definitions.php` holds to the PHP they were taken from.

**Tech Stack:** PHP 7.4-compatible WordPress plugin (WordPress 6.5 floor); standalone suites under `bin/test-*.php` that stub WordPress at their top; `bash bin/check-standards.sh` (phpcs with the WordPress Coding Standards), `php bin/check-references.php`, `php bin/check-spelling.php`, `php bin/check-dead-annotations.php`; WP-CLI for the translation template (`sh bin/make-pot.sh`); WordPress Studio's CLI, `studio`, for the one check on a real WordPress (Task 6).

**Spec:** `docs/specs/2026-09-10-track-builder-design.md`, approved by the product owner on 10 September 2026. This plan is the first half of phase T2, which the commit that adds it splits into T2a and T2b in section 12, and it settles open items 5 and 6 as that commit records. Every section or decision number below is that document's. The handoff from T1 is the last section of `docs/plans/2026-09-10-track-builder-t1.md`.

**Proven before it was written:** every line of code in this plan was run on 11 September 2026 in a scratch clone of `main` at `4e215d7` (1.100.0), one commit per task: all 88 suites silent, the three static checks clean, and phpcs at 85 warnings and no error after every task. The failing output each Step 2 quotes is what those checks printed against the commit before theirs. The two fixtures Task 4 adds were read the same day, read-only: every Students Reports column's type from the base's metadata API, and the four Learn courses' outlines from Learn's public course-structure endpoint. A check that fails while following this plan is a difference from that run, not a flaw to work around.

## Global Constraints

- **Start from `main` at 1.100.0.** `grep "^Stable tag" readme.txt` prints `Stable tag: 1.100.0` and `git status --short` prints nothing. Then `git switch -c track-builder-t2a`.
- WordPress coding standards throughout: tabs, Yoda conditions, spaces inside parentheses, `array()` and never `[]`, strict `in_array()`. Everything that ships is PHP 7.4 compatible: no `match`, no named arguments, no union types, and `mb_strtolower()` only behind `function_exists()`, since WordPress does not provide it. Every string a person reads is US English. No em dash or en dash anywhere, in code, comments, docs or commit messages: a plain hyphen. Full product names ("Student Report Card", "Mentor Report Card", "Administrator Dashboard").
- **The battery stays silent after every task:** `for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done` prints nothing. `php bin/check-references.php`, `php bin/check-spelling.php` and `php bin/check-dead-annotations.php` report clean. `bash bin/check-standards.sh` prints no line containing ` ERROR `, and its last line reads `85 warnings, no errors.` or a lower count. Four things it counts are easy to trip: the `=` of consecutive assignments align, the arrows of a multi-line array align, an associative array of more than one item takes one line an item, and a comment line between two assignments ends their alignment block.
- **Test first, every task:** apply the checks, run them, see them fail as the step says, then write the code.
- **Nothing a person sees changes in T2a.** The first request after the update seeds the four built-in tracks as drafts, and nothing outside the suites and Task 6's throwaway site publishes one, so every answer of `WPCPM_Program`, `WPCPM_Student_Report_Form::fields()`, the Programs running card and the Institutions Link control stays 1.100.0's. `bin/test-tracks.php` holds this in its section "With nothing compiled".
- `bin/` and `docs/` never ship in the zip but are public on the mirror, and `includes/` ships, the seed files with it: nothing personal in any of them, and no Airtable record ID (`rec` and fourteen characters) that is not an obvious placeholder.
- Nothing reads or writes Airtable or Learn. Task 4's two fixtures were read before this plan was written and are copied from it. Task 6 runs on a local WordPress Studio site only, and only after the product owner has said yes to creating one. The live site is touched in Task 7 only, and only on the product owner's yes.
- Version numbers move in Task 7 only: plugin 1.101.0, which `version_compare()` orders after 1.100.0. No theme release (decision 3.10).
- Comments explain why and name the decision or the bug behind a rule. Every commit message starts with "Track Builder T2a:" and ends with the `Co-Authored-By:` trailer of whoever made it.

## What this plan decides

The design left these to T2. Each is settled here, and the spec says so from the commit that adds this plan.

1. **T2 ships in two releases.** T2a, this plan, is everything under the screen and ships as 1.101.0. T2b, planned on its own, is the Track Builder screen: the track list, track properties, duplication, the read-only preflight, verify, the checklist, and publishing and unpublishing from the screen, as 1.102.0.
2. **The published copy is the post meta `_wpcpm_track_published`** (open item 5): the definition as it was published, stored the way the definition is. Not the ID of a revision, because a site may cap how many revisions it keeps. A published track whose saved definition differs from it has unpublished changes, and `state()` answers `changed`.
3. **`compile()` checks every published copy** (open item 6) against the site as its PHP describes it, from `WPCPM_Tracks::validation_context( '', false )`, and the copies already compiled, in post ID order. Of two that claim one status or key, the first made keeps it. What it leaves out is recorded in the option `wpcpm_tracks_skipped`, post ID => codes, for the track list.
4. **A track's own status comes off the context only when the track is locked to it.** T1's `validation_context( $own_status )` left the track's own status out of the others unconditionally, so a new track could take one of the four built-in statuses under a key of its own; found while writing this plan. The store now leaves it out only for a track published with that status before, or a seeded built-in track.
5. **A built-in track is locked to its PHP's key**: the seeded definition, marked `builtin`, when it is published, and at every compile any copy holding one of the four built-in statuses. **Added by the final review of T2a:** a track marked `builtin` is also read-only in the store, the design's section 6 rule, so `save()` refuses it with `wpcpm_track_builtin` until it switches. `locked()` reads the status a built-in draft names now, so without the rule a seed saved as another built-in track would lock the real one out for good, and a seed renamed away from its status would publish a track no page runs.
6. **The seeds are built offline, and a site only reads them.** `bin/build-seeds.php` writes `includes/tracks/seeds/<key>.json` from `builtin_fields()`, the program map, two fixtures and a hand-written `why` for the columns whose names look like slips. `wp wpcredits seed-tracks` (WP-CLI names the method `seed_tracks` so) creates the four built-in drafts from the committed files, and a site does the same once on its own (`maybe_seed()`). Section 8 names one command for both because it did not ask where the files are written: a site must never write into its own plugin folder.
7. **`learn_lesson_id` is recorded on each question whose `subgroup` or `lead` is a lesson title of its own course**, once case and apostrophes are folded, its own module searched first: on 1 question of the 150-hour course, 0 of the 50-hour course, 1 of the Developer Track and 11 of the Designer Track, which are section 2.6's counts.
8. **The switch keeps the saved definition's md5** from the moment a built-in track switches to its definition, and the way back is open only while that still holds (decision 3.5) and `equivalence()` holds nothing but `not_published`: a published track's copy must still be the PHP's. **Corrected by the final review of T2a:** the md5 alone let a track go back after an edit was published and its old text saved back without being published, which put the PHP in front of students and dropped the edit they saw. The md5 test stays, as the design's literal "not edited since the flip", and an unpublished track may still go back, because its PHP already runs it.
9. **What needs the base or the students is T2b's**: the preflight, the lock, the checklist, and refusing to unpublish while synced students hold the status. The store's `publish()` and `unpublish()` are the part every path shares.

## File map

| File | Task | Responsibility |
| --- | --- | --- |
| `includes/tracks/class-wpcpm-track-palette.php` | 1 | `KEY_PATTERN`, the one shape of a track key |
| `includes/tracks/class-wpcpm-track-definition.php` | 1 | Whole-number columns, lengths in characters, near-matches of the sync columns, names two tracks share |
| `includes/tracks/class-wpcpm-track-store.php` | 1, 2, 5 | A store that refuses what JSON cannot hold; the published copy, `publish()`, `unpublish()`, `state()`, the log and a `compile()` that checks; the seeding and the switch |
| `includes/tracks/class-wpcpm-tracks.php` | 1, 2, 5 | The other tracks' names in the rules' context; the program map read with the compiled tracks suspended (`builtin_key()`, `builtin_row()`) |
| `includes/class-wpcpm-settings.php` | 2 | `add_student_status()`: "Currently mentoring" only grows |
| `uninstall.php` | 2 | The sweep of any form option the index lost track of |
| `includes/modules/class-wpcpm-student-report-form.php` | 3 | `builtin_fields()`; `hide_from_institution` on the three alumni questions; `for_institution()` reads the flag |
| `includes/modules/class-wpcpm-institutions.php` | 1 | A pointer from `AUTOMATION_STATUSES` to `automation_statuses()` |
| `bin/seed-definitions.php`, `bin/build-seeds.php` | 4 | How a seed is built, and the command that writes the four |
| `includes/tracks/seeds/150h.json`, `50h.json`, `dev.json`, `design.json` | 4 | The four seeds, as `bin/build-seeds.php` writes them |
| `bin/fixtures/learn-course-297853.json`, `-322343.json`, `-402893.json`, `-403425.json` | 4 | The four Learn courses' modules and lessons |
| `bin/fixtures/reports-table-fields.json` | 4 | `all_types`: every Students Reports column's Airtable type |
| `includes/class-wpcpm-cli.php` | 5 | `wp wpcredits seed-tracks` |
| `bin/test-track-definitions.php` | 4 | The new suite: each seed is its hand-written track |
| `bin/test-track-definition.php`, `bin/test-track-store.php`, `bin/test-tracks.php`, `bin/test-settings.php`, `bin/test-report-form.php`, `bin/test-fixtures.php` | 1 to 5 | The suites the change reaches |
| `readme.txt`, `wpcredits-program-manager.php`, `languages/wpcredits-program-manager.pot` | 7 | Version, changelog, translation template |

## How to apply a change

Every change to an existing file below is a unified diff against that file as the task before left it, and every new file is given whole. Apply a diff by saving it to a file outside the plugin folder and running `git apply <file>` from the plugin root; `git apply --check <file>` first says whether it fits. Do not retype a diff by hand: several of its lines differ from their neighbors only by alignment spaces, which phpcs counts.

---

### Task 1: The rules, tightened

The final review of T1 found four rules letting through what they meant to refuse (its minors 1, 2 and 4, and its recommendation 4) and a store that could report success while storing nothing (its minor 3).

**Files:**
- Modify: `includes/tracks/class-wpcpm-track-palette.php`, `includes/tracks/class-wpcpm-track-definition.php`, `includes/tracks/class-wpcpm-track-store.php`, `includes/tracks/class-wpcpm-tracks.php`, `includes/modules/class-wpcpm-institutions.php` (a docblock)
- Test: `bin/test-track-definition.php`, `bin/test-track-store.php`, `bin/test-tracks.php`

**Interfaces:**
- Consumes: the four classes as 1.100.0 shipped them.
- Produces: `WPCPM_Track_Palette::KEY_PATTERN` (`'/^[a-z0-9-]{2,20}$/'`). `WPCPM_Track_Definition::validate()` reads a `labels` entry in its context (every other track, status => name) and has one new code, `label_taken`. `WPCPM_Tracks::validation_context()` returns `labels`. `WPCPM_Track_Store::create()` and `save()` return a `WP_Error` coded `wpcpm_track_unencodable` for a definition JSON cannot hold, before writing anything.

- [ ] **Step 1: Write the failing checks.** Apply these three changes:

```diff
--- a/bin/test-track-definition.php
+++ b/bin/test-track-definition.php
@@ -96,6 +96,7 @@ ck( 'seven hues, the four built-in tracks\' among them', array_keys( WPCPM_Track
 ck( 'slate and amber are no hue: they paint the two states on no track', array( WPCPM_Track_Palette::is_hue( 'slate' ), WPCPM_Track_Palette::is_hue( 'amber' ), WPCPM_Track_Palette::is_hue( 5 ) ), array( false, false, false ) );
 ck( 'the chip rule, in the shape dashboard.css gives its own chips', WPCPM_Track_Palette::badge_rule( 'marketing', 'cyan' ), '.wpcpm-badge--marketing{background:rgba(8,145,178,0.12);border-color:rgba(8,145,178,0.35);}' );
 ck( 'no rule for a hue outside the palette', WPCPM_Track_Palette::badge_rule( 'marketing', '#ff0000' ), '' );
+ck( 'one key pattern, which the definition\'s rule reads as well', array( WPCPM_Track_Palette::KEY_PATTERN, preg_match( WPCPM_Track_Palette::KEY_PATTERN, 'marketing' ), preg_match( WPCPM_Track_Palette::KEY_PATTERN, 'Marketing' ) ), array( '/^[a-z0-9-]{2,20}$/', 1, 0 ) );
 ck( 'and none for a key that could carry anything into the stylesheet', array( WPCPM_Track_Palette::badge_rule( 'Marketing', 'cyan' ), WPCPM_Track_Palette::badge_rule( 'a}b{', 'cyan' ), WPCPM_Track_Palette::badge_rule( 'm', 'cyan' ) ), array( '', '', '' ) );
 
 echo "\n=== A definition every rule accepts ===\n";
@@ -109,11 +110,16 @@ ck( 'a property this version does not know', refused( function ( &$d ) { $d['col
 ck( 'a schema version the site does not read', refused( function ( &$d ) { $d['schema_version'] = 2; }, $context ), array( 'schema_version' ) );
 ck( 'no status', refused( function ( &$d ) { $d['status'] = ''; }, $context ), array( 'status_empty' ) );
 ck( 'a status over 100 characters', refused( function ( &$d ) { $d['status'] = str_repeat( 'a', 101 ); }, $context ), array( 'status_shape' ) );
+ck( 'lengths are counted in characters: sixty accented letters are a status', refused( function ( &$d ) { $d['status'] = str_repeat( "\u{00E9}", 60 ); }, $context ), array() );
+ck( 'and a hundred and one of them are too many', refused( function ( &$d ) { $d['status'] = str_repeat( "\u{00E9}", 101 ); }, $context ), array( 'status_shape' ) );
 ck( 'a status on two lines', refused( function ( &$d ) { $d['status'] = "Marketing\nTrack"; }, $context ), array( 'status_shape' ) );
 ck( 'another track\'s status, whatever its case', refused( function ( &$d ) { $d['status'] = 'designer track'; }, $context ), array( 'status_taken' ) );
 $curly                                    = $context;
 $curly['tracks']["Writer\u{2019}s Track"] = 'writers';
 ck( 'and whichever apostrophe it is typed with', refused( function ( &$d ) { $d['status'] = "writer's track"; }, $curly ), array( 'status_taken' ) );
+$accented                                = $context;
+$accented['tracks']["\u{00C9}cole Track"] = 'ecole';
+ck( 'and in any alphabet: an accented capital folds like a plain one', refused( function ( &$d ) { $d['status'] = "\u{00E9}cole track"; }, $accented ), array( 'status_taken' ) );
 ck( 'a status that already means a finished student', refused( function ( &$d ) { $d['status'] = 'Graduate'; }, $context ), array( 'status_refused' ) );
 ck( 'or a paused one', refused( function ( &$d ) { $d['status'] = 'paused'; }, $context ), array( 'status_refused' ) );
 $locked           = $context;
@@ -133,6 +139,11 @@ $builtin = array(
 );
 ck( 'a built-in track\'s own definition keeps its reserved key', refused( function ( &$d ) { $d['status'] = 'Designer Track'; $d['key'] = 'design'; }, $builtin ), array() );
 ck( 'no name', refused( function ( &$d ) { $d['label'] = '  '; }, $context ), array( 'label_empty' ) );
+$named           = $context;
+$named['labels'] = array( 'In Sensei' => 'WordPress Credits Program 150h', 'Developer Track' => 'Developer Track' );
+ck( 'a name another track already goes by, whatever its case', refused( function ( &$d ) { $d['label'] = 'wordpress credits program 150h'; }, $named ), array( 'label_taken' ) );
+ck( 'or another track\'s status, which the institution import matches as well', refused( function ( &$d ) { $d['label'] = 'In Sensei 50h'; }, $named ), array( 'label_taken' ) );
+ck( 'a name equal to the track\'s own status passes: the Developer Track is named so', refused( function ( &$d ) { $d['label'] = 'Marketing Track'; }, $named ), array() );
 ck( 'a course link that is not a Learn course', array( refused( function ( &$d ) { $d['course_url'] = 'http://learn.wordpress.org/course/marketing/'; }, $context ), refused( function ( &$d ) { $d['course_url'] = 'https://example.org/course/marketing/'; }, $context ) ), array( array( 'course_url' ), array( 'course_url' ) ) );
 ck( 'no course link at all is fine', refused( function ( &$d ) { $d['course_url'] = ''; unset( $d['learn_course_id'] ); }, $context ), array() );
 ck( 'a course ID that is not a whole number', array( refused( function ( &$d ) { $d['learn_course_id'] = -1; }, $context ), refused( function ( &$d ) { $d['learn_course_id'] = '500001'; }, $context ) ), array( array( 'course_id' ), array( 'course_id' ) ) );
@@ -149,7 +160,10 @@ $note = array( 'label' => 'Your notes', 'type' => 'textarea', 'group' => 'projec
 ck( 'a column name of spaces', refused( only( array( '   ' => $note ) ), $context ), array( 'column_shape' ) );
 ck( 'a column name over 255 characters', refused( only( array( str_repeat( 'c', 256 ) => $note ) ), $context ), array( 'column_shape' ) );
 ck( 'a column name that is a number alone', refused( only( array( '2024' => $note ) ), $context ), array( 'column_numeric' ) );
+ck( 'or a negative whole number, which PHP turns into an integer key as well', refused( only( array( '-1' => $note ) ), $context ), array( 'column_numeric' ) );
+ck( 'while a number with a leading zero stays a string, and passes', refused( only( array( '007' => $note ) ), $context ), array() );
 ck( 'a column the syncs own, which a student could then change', refused( only( array( 'Status' => $note ) ), $context ), array( 'column_reserved' ) );
+ck( 'or one a capital or a space away from it', array( refused( only( array( 'status' => $note ) ), $context ), refused( only( array( 'STATUS' => $note ) ), $context ), refused( only( array( 'Status ' => $note ) ), $context ) ), array( array( 'column_reserved' ), array( 'column_reserved' ), array( 'column_reserved' ) ) );
 ck( 'a column name ending in a space is taken as it is, like the base\'s own "Company "', refused( only( array( 'Company ' => $note ) ), $context ), array() );
 ck( 'a question that is not a question', refused( only( array( 'Notes' => 'textarea' ) ), $context ), array( 'question_shape' ) );
 ck( 'a property this version does not know', refused( only( array( 'Notes' => $note + array( 'colour' => 'red' ) ) ), $context ), array( 'unknown_property' ) );
@@ -167,6 +181,7 @@ ck( 'a number with every bound passes', refused( only( array( 'Grade' => $grade
 ck( 'a number with no step', refused( only( array( 'Grade' => array_diff_key( $grade, array( 'step' => 1 ) ) ) ), $context ), array( 'number_bounds' ) );
 ck( 'a number whose lowest value is above its highest', refused( only( array( 'Grade' => array( 'min' => 101 ) + $grade ) ), $context ), array( 'number_bounds' ) );
 ck( 'a number with a step of zero', refused( only( array( 'Grade' => array( 'step' => '0' ) + $grade ) ), $context ), array( 'number_bounds' ) );
+ck( 'a bound no JSON can hold', refused( only( array( 'Grade' => array( 'max' => INF ) + $grade ) ), $context ), array( 'number_bounds' ) );
 ck( 'bounds on something that is not a number', refused( only( array( 'Notes' => $note + array( 'min' => 0 ) ) ), $context ), array( 'number_only' ) );
 
 $pick = array( 'label' => 'Tool', 'type' => 'select', 'group' => 'project', 'options' => array( 'WordPress Studio', 'MAAMP', 'DevKinsta' ) );
```

```diff
--- a/bin/test-track-store.php
+++ b/bin/test-track-store.php
@@ -204,6 +204,15 @@ ck( 'and refuses a post of another type', WPCPM_Track_Store::save( $plain, track
 ck( 'get() answers null for another type of post', WPCPM_Track_Store::get( $plain ), null );
 ck( 'and for a post that is not there', WPCPM_Track_Store::get( 424242 ), null );
 
+$infinite                              = track( 'Infinite Track', 'infinite' );
+$infinite['questions']['Hours']['max'] = INF;
+$before                                = count( $GLOBALS['posts'] );
+$refused                               = WPCPM_Track_Store::create( $infinite );
+ck( 'create() refuses a definition JSON cannot hold, before any post is made', array( $refused instanceof WP_Error ? $refused->get_error_code() : $refused, count( $GLOBALS['posts'] ) ), array( 'wpcpm_track_unencodable', $before ) );
+$kept    = WPCPM_Track_Store::get( $id );
+$refused = WPCPM_Track_Store::save( $id, $infinite );
+ck( 'and save() refuses it without touching what was stored', array( $refused instanceof WP_Error ? $refused->get_error_code() : $refused, WPCPM_Track_Store::get( $id ) ), array( 'wpcpm_track_unencodable', $kept ) );
+
 echo "\n=== compile() ===\n";
 
 $GLOBALS['posts']     = array();
```

```diff
--- a/bin/test-tracks.php
+++ b/bin/test-tracks.php
@@ -219,6 +219,7 @@ echo "\n=== What the rules are told about the site ===\n";
 ck( 'the columns the syncs own: every report column the sync maps that is not a question', WPCPM_Tracks::reserved_columns(), array( 'Name', 'Email', 'Status', 'Mentor', 'Educational institution', 'Internship Start Date', 'Internship End Date', 'Personal link', '50h personal link', 'Dev Track ONLY personal link' ) );
 $context = WPCPM_Tracks::validation_context( 'Marketing Track' );
 ck( 'the other tracks by status and key, the track being checked left out', $context['tracks'], array( 'In Sensei' => '150h', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev', 'Designer Track' => 'design' ) );
+ck( 'and their names, which a new track may not take', $context['labels'], $builtin_labels );
 ck( 'the statuses that mean something else: past students, and the two states', $context['refused_statuses'], array( 'Graduate', 'Dropped out', 'Paused', 'Pending graduation' ) );
 ck( 'the sync columns, and nothing locked', array( $context['reserved_columns'], $context['locked'] ), array( WPCPM_Tracks::reserved_columns(), null ) );
 ck( 'and the track it describes passes with it', WPCPM_Track_Definition::validate( array( 'schema_version' => 1, 'status' => 'Marketing Track', 'key' => 'marketing', 'label' => 'Marketing Track', 'hue' => 'cyan', 'questions' => $form ), $context ), array() );
```

- [ ] **Step 2: Run them, and see them fail.**

```bash
php bin/test-track-definition.php 2>&1 | grep -m1 'Fatal error'
php bin/test-track-store.php | grep -E '^FAIL|checks\)'
php bin/test-tracks.php | grep -E '^FAIL|checks\)'
```

Expected: the first stops at once with `Uncaught Error: Undefined constant WPCPM_Track_Palette::KEY_PATTERN`, because the checks read the constant this task adds. The second fails "create() refuses a definition JSON cannot hold, before any post is made" and "and save() refuses it without touching what was stored", and ends `2 FAILED (42 checks)`. The third fails "and their names, which a new track may not take", and ends `1 FAILED (43 checks)`.

- [ ] **Step 3: Write the code.** Apply these five changes:

```diff
--- a/includes/tracks/class-wpcpm-track-palette.php
+++ b/includes/tracks/class-wpcpm-track-palette.php
@@ -41,6 +41,14 @@ final class WPCPM_Track_Palette {
 		'purple' => array( 124, 58, 237 ),
 	);
 
+	/**
+	 * The shape of a track key, which is also a class name: `.wpcpm-badge--<key>`.
+	 *
+	 * One pattern for the two places that check a key - the definition's rule and this palette's
+	 * guard - so they can never disagree about what reaches a stylesheet.
+	 */
+	const KEY_PATTERN = '/^[a-z0-9-]{2,20}$/';
+
 	/**
 	 * Whether a value is one of the hues.
 	 *
@@ -63,7 +71,7 @@ final class WPCPM_Track_Palette {
 	 * @return string One CSS rule, or an empty string for a key or a hue this cannot vouch for.
 	 */
 	public static function badge_rule( $key, $hue ) {
-		if ( ! self::is_hue( $hue ) || 1 !== preg_match( '/^[a-z0-9-]{2,20}$/', (string) $key ) ) {
+		if ( ! self::is_hue( $hue ) || 1 !== preg_match( self::KEY_PATTERN, (string) $key ) ) {
 			return '';
 		}
 
```

```diff
--- a/includes/tracks/class-wpcpm-track-definition.php
+++ b/includes/tracks/class-wpcpm-track-definition.php
@@ -143,10 +143,11 @@ final class WPCPM_Track_Definition {
 	 * Everything wrong with a definition, as a list a screen can print.
 	 *
 	 * @param array $definition Track definition, after `normalize()`.
-	 * @param array $context    `tracks` (every other track, status => key), `refused_statuses`
-	 *                          (statuses that mean something else), `reserved_columns` (the
-	 *                          columns the syncs own) and `locked` (the status and key a
-	 *                          published track keeps, or null).
+	 * @param array $context    `tracks` (every other track, status => key), `labels` (every
+	 *                          other track, status => name), `refused_statuses` (statuses that
+	 *                          mean something else), `reserved_columns` (the columns the syncs
+	 *                          own) and `locked` (the status and key a published track keeps, or
+	 *                          null).
 	 * @return array[] Each with a `code`, a `where` (a column, or empty for the track) and a
 	 *                 `message`. Empty when the definition may be stored.
 	 */
@@ -154,6 +155,7 @@ final class WPCPM_Track_Definition {
 		$context = array_merge(
 			array(
 				'tracks'           => array(),
+				'labels'           => array(),
 				'refused_statuses' => array(),
 				'reserved_columns' => array(),
 				'locked'           => null,
@@ -177,6 +179,8 @@ final class WPCPM_Track_Definition {
 
 		if ( ! isset( $definition['label'] ) || ! is_string( $definition['label'] ) || '' === trim( $definition['label'] ) ) {
 			$errors[] = self::error( 'label_empty', '', __( 'The track needs a name.', 'wpcredits-program-manager' ) );
+		} elseif ( self::name_taken( $definition['label'], $context ) ) {
+			$errors[] = self::error( 'label_taken', '', __( 'Another track already goes by this name, or has it as its status. The institution import finds a track by either, so two tracks cannot share one.', 'wpcredits-program-manager' ) );
 		}
 
 		if ( isset( $definition['course_url'] ) && '' !== $definition['course_url'] && ( ! is_string( $definition['course_url'] ) || 1 !== preg_match( '#^https://learn\.wordpress\.org/course/[a-z0-9-]+/?$#', $definition['course_url'] ) ) ) {
@@ -204,7 +208,7 @@ final class WPCPM_Track_Definition {
 		$teams = 0;
 
 		foreach ( $definition['questions'] as $column => $spec ) {
-			$errors = array_merge( $errors, self::validate_question( (string) $column, $spec, $context ) );
+			$errors = array_merge( $errors, self::validate_question( $column, $spec, $context ) );
 
 			if ( is_array( $spec ) && isset( $spec['type'] ) && 'team' === $spec['type'] ) {
 				++$teams;
@@ -235,7 +239,7 @@ final class WPCPM_Track_Definition {
 
 		$errors = array();
 
-		if ( strlen( $status ) > self::MAX_STATUS || 1 === preg_match( '/[\r\n]/', $status ) ) {
+		if ( self::length( $status ) > self::MAX_STATUS || 1 === preg_match( '/[\r\n]/', $status ) ) {
 			$errors[] = self::error( 'status_shape', '', sprintf( /* translators: %d: a number of characters. */ __( 'The status must be one line of at most %d characters.', 'wpcredits-program-manager' ), self::MAX_STATUS ) );
 		}
 
@@ -276,7 +280,7 @@ final class WPCPM_Track_Definition {
 	private static function validate_key( array $definition, array $context ) {
 		$key = isset( $definition['key'] ) && is_string( $definition['key'] ) ? $definition['key'] : '';
 
-		if ( 1 !== preg_match( '/^[a-z0-9-]{2,20}$/', $key ) ) {
+		if ( 1 !== preg_match( WPCPM_Track_Palette::KEY_PATTERN, $key ) ) {
 			return array( self::error( 'key_shape', '', __( 'The key is 2 to 20 lowercase letters, digits or hyphens.', 'wpcredits-program-manager' ) ) );
 		}
 
@@ -299,22 +303,33 @@ final class WPCPM_Track_Definition {
 	/**
 	 * The rules of one question.
 	 *
-	 * @param string $column  Airtable column name.
-	 * @param mixed  $spec    The question.
-	 * @param array  $context See `validate()`.
+	 * The column arrives as the array keyed it, before any cast: PHP turns a key written as a
+	 * whole number, `-1` as well as `2024`, into an integer, and that change is what the numeric
+	 * rule exists for. `007` stays a string and keeps its meaning, so it passes.
+	 *
+	 * @param int|string $column  Airtable column name, as the questions array keyed it.
+	 * @param mixed      $spec    The question.
+	 * @param array      $context See `validate()`.
 	 * @return array[]
 	 */
 	private static function validate_question( $column, $spec, array $context ) {
-		$errors = array();
+		$errors  = array();
+		$numeric = is_int( $column );
+		$column  = (string) $column;
 
-		if ( '' === trim( $column ) || strlen( $column ) > self::MAX_COLUMN ) {
+		if ( '' === trim( $column ) || self::length( $column ) > self::MAX_COLUMN ) {
 			$errors[] = self::error( 'column_shape', $column, sprintf( /* translators: %d: a number of characters. */ __( 'A column name must hold more than spaces, and at most %d characters.', 'wpcredits-program-manager' ), self::MAX_COLUMN ) );
-		} elseif ( ctype_digit( $column ) ) {
+		} elseif ( $numeric ) {
 			$errors[] = self::error( 'column_numeric', $column, __( 'A column name cannot be a number alone: the form keys each question by its column name, and a number there changes meaning.', 'wpcredits-program-manager' ) );
 		}
 
-		if ( in_array( $column, (array) $context['reserved_columns'], true ) ) {
-			$errors[] = self::error( 'column_reserved', $column, __( 'This column belongs to the syncs. A question writing it would let a student change it.', 'wpcredits-program-manager' ) );
+		$folded_column = self::fold_column( $column );
+
+		foreach ( (array) $context['reserved_columns'] as $reserved ) {
+			if ( self::fold_column( (string) $reserved ) === $folded_column ) {
+				$errors[] = self::error( 'column_reserved', $column, __( 'This column belongs to the syncs. A question writing it would let a student change it.', 'wpcredits-program-manager' ) );
+				break;
+			}
 		}
 
 		if ( ! is_array( $spec ) ) {
@@ -393,7 +408,10 @@ final class WPCPM_Track_Definition {
 		$errors = array();
 
 		if ( 'number' === $type ) {
-			$bounded = isset( $spec['min'], $spec['max'], $spec['step'] ) && is_numeric( $spec['min'] ) && is_numeric( $spec['max'] ) && is_numeric( $spec['step'] );
+			// `is_finite()` as well: `is_numeric()` accepts INF, which no JSON can hold, so a track
+			// bounded by it could never be stored.
+			$bounded = isset( $spec['min'], $spec['max'], $spec['step'] ) && is_numeric( $spec['min'] ) && is_numeric( $spec['max'] ) && is_numeric( $spec['step'] )
+				&& is_finite( (float) $spec['min'] ) && is_finite( (float) $spec['max'] ) && is_finite( (float) $spec['step'] );
 
 			if ( ! $bounded || (float) $spec['min'] > (float) $spec['max'] || (float) $spec['step'] <= 0 ) {
 				$errors[] = self::error( 'number_bounds', $column, __( 'A number needs a lowest value, a highest value at least as high, and a step above zero.', 'wpcredits-program-manager' ) );
@@ -523,9 +541,33 @@ final class WPCPM_Track_Definition {
 		);
 	}
 
+	/**
+	 * Whether a track's name is another track's name or status, compared as statuses are.
+	 *
+	 * The institution import matches a spreadsheet's program cell against a track's status and
+	 * its name alike, and the Administrator Dashboard's Programs running card draws its tiles by
+	 * name, so a name two tracks share would put students on the wrong one. A track's own status
+	 * is not in the context, so a name equal to it, the Developer Track's way, passes.
+	 *
+	 * @param string $label   The track's name.
+	 * @param array  $context See `validate()`.
+	 * @return bool
+	 */
+	private static function name_taken( $label, array $context ) {
+		$folded = self::fold( $label );
+
+		foreach ( array_merge( array_keys( (array) $context['tracks'] ), array_values( (array) $context['labels'] ) ) as $other ) {
+			if ( self::fold( (string) $other ) === $folded ) {
+				return true;
+			}
+		}
+
+		return false;
+	}
+
 	/**
 	 * A status as the comparisons see it: trimmed, one space for any run of them, the
-	 * typographic apostrophe read as the plain one, and lower case.
+	 * typographic apostrophe read as the plain one, and lower case in any alphabet.
 	 *
 	 * @param string $status Status.
 	 * @return string
@@ -533,7 +575,48 @@ final class WPCPM_Track_Definition {
 	private static function fold( $status ) {
 		$status = str_replace( array( "\u{2019}", "\u{2018}" ), "'", (string) $status );
 
-		return strtolower( trim( (string) preg_replace( '/\s+/', ' ', $status ) ) );
+		return self::lower( trim( (string) preg_replace( '/\s+/', ' ', $status ) ) );
+	}
+
+	/**
+	 * A column name as the reserved-column rule compares it: trimmed and in lower case.
+	 *
+	 * For that rule only. A question keeps its column verbatim, but a name one space or one
+	 * capital away from `Status` is refused all the same: nothing is lost by it, and whether
+	 * Airtable would match such a name to the sync's column on a write is not a thing to find out
+	 * on the live base.
+	 *
+	 * @param string $column Column name.
+	 * @return string
+	 */
+	private static function fold_column( $column ) {
+		return self::lower( trim( (string) $column ) );
+	}
+
+	/**
+	 * Lower case in any alphabet where PHP can, ASCII where it cannot.
+	 *
+	 * WordPress provides `mb_strlen()` where PHP lacks it, but not `mb_strtolower()`, so the plain
+	 * lower-casing stays as the fallback rather than a fatal error on a host without the extension.
+	 *
+	 * @param string $text Text.
+	 * @return string
+	 */
+	private static function lower( $text ) {
+		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text );
+	}
+
+	/**
+	 * A length in characters, as the rules and their messages count it.
+	 *
+	 * `strlen()` counts bytes, and would refuse a status of sixty accented letters as longer than
+	 * a hundred.
+	 *
+	 * @param string $text Text.
+	 * @return int
+	 */
+	private static function length( $text ) {
+		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text, 'UTF-8' ) : strlen( (string) $text );
 	}
 
 	/**
```

```diff
--- a/includes/tracks/class-wpcpm-track-store.php
+++ b/includes/tracks/class-wpcpm-track-store.php
@@ -115,6 +115,10 @@ final class WPCPM_Track_Store {
 	 * @return int|WP_Error The post ID, or whatever WordPress refused with.
 	 */
 	public static function create( array $definition ) {
+		if ( '' === WPCPM_Track_Definition::encode( WPCPM_Track_Definition::normalize( $definition ) ) ) {
+			return self::unencodable();
+		}
+
 		$post_id = wp_insert_post(
 			wp_slash(
 				array(
@@ -148,15 +152,19 @@ final class WPCPM_Track_Store {
 	 */
 	public static function save( $post_id, array $definition ) {
 		$post_id = (int) $post_id;
-		$post    = get_post( $post_id );
 
-		if ( ! ( $post instanceof WP_Post ) || self::POST_TYPE !== $post->post_type ) {
+		if ( null === self::track_post( $post_id ) ) {
 			return new WP_Error( 'wpcpm_track_missing', __( 'That track does not exist.', 'wpcredits-program-manager' ) );
 		}
 
 		$definition = WPCPM_Track_Definition::normalize( $definition );
+		$json       = WPCPM_Track_Definition::encode( $definition );
 
-		update_post_meta( $post_id, self::META_DEFINITION, wp_slash( WPCPM_Track_Definition::encode( $definition ) ) );
+		if ( '' === $json ) {
+			return self::unencodable();
+		}
+
+		update_post_meta( $post_id, self::META_DEFINITION, wp_slash( $json ) );
 
 		$updated = wp_update_post(
 			wp_slash(
@@ -178,15 +186,38 @@ final class WPCPM_Track_Store {
 	 * @return array|null The definition, or null when the post is not a readable track.
 	 */
 	public static function get( $post_id ) {
-		$post = get_post( (int) $post_id );
-
-		if ( ! ( $post instanceof WP_Post ) || self::POST_TYPE !== $post->post_type ) {
+		if ( null === self::track_post( $post_id ) ) {
 			return null;
 		}
 
 		return WPCPM_Track_Definition::decode( get_post_meta( (int) $post_id, self::META_DEFINITION, true ) );
 	}
 
+	/**
+	 * The post, when it is a track.
+	 *
+	 * @param int $post_id Post ID.
+	 * @return WP_Post|null
+	 */
+	private static function track_post( $post_id ) {
+		$post = get_post( (int) $post_id );
+
+		return $post instanceof WP_Post && self::POST_TYPE === $post->post_type ? $post : null;
+	}
+
+	/**
+	 * Why a definition was not stored: JSON cannot hold one of its values.
+	 *
+	 * `validate()` refuses the one such value a form could carry, an infinite bound, and this
+	 * catches whatever else would reach the store: storing what `wp_json_encode()` gave up with
+	 * would leave the track with no definition while the save reported success.
+	 *
+	 * @return WP_Error
+	 */
+	private static function unencodable() {
+		return new WP_Error( 'wpcpm_track_unencodable', __( 'The track was not saved: one of its values cannot be stored.', 'wpcredits-program-manager' ) );
+	}
+
 	/**
 	 * Compile every published track into the options the live site runs on.
 	 *
```

```diff
--- a/includes/tracks/class-wpcpm-tracks.php
+++ b/includes/tracks/class-wpcpm-tracks.php
@@ -319,10 +319,12 @@ final class WPCPM_Tracks {
 	 */
 	public static function validation_context( $own_status = '' ) {
 		$tracks = array();
+		$labels = array();
 
-		foreach ( array_keys( WPCPM_Program::labels() ) as $status ) {
+		foreach ( WPCPM_Program::labels() as $status => $label ) {
 			if ( (string) $status !== (string) $own_status ) {
 				$tracks[ $status ] = WPCPM_Program::track( $status );
+				$labels[ $status ] = (string) $label;
 			}
 		}
 
@@ -334,6 +336,7 @@ final class WPCPM_Tracks {
 
 		return array(
 			'tracks'           => $tracks,
+			'labels'           => $labels,
 			'refused_statuses' => array_values( array_unique( $refused ) ),
 			'reserved_columns' => self::reserved_columns(),
 			'locked'           => null,
```

```diff
--- a/includes/modules/class-wpcpm-institutions.php
+++ b/includes/modules/class-wpcpm-institutions.php
@@ -87,6 +87,9 @@ class WPCPM_Institutions extends WPCPM_Sync_Module {
 	 *
 	 * Written out rather than built from the `WPCPM_Program` constants: PHP resolves those on
 	 * the first `new` of this class, and the suites that load this class do not load that one.
+	 *
+	 * Ask `automation_statuses()` rather than this list: it adds every Track Builder track whose
+	 * reports automation item somebody has ticked (1.100.0), and the Link control's guard reads it.
 	 */
 	const AUTOMATION_STATUSES = array( 'In Sensei', 'In Sensei Self-onboarding', 'In Sensei 50h', 'Developer Track', 'Designer Track' );
 
```

- [ ] **Step 4: Run the three suites, and the form's.** Expected: `ALL PASS (103 checks)`, `ALL PASS (42 checks)` and `ALL PASS (43 checks)`, and `php bin/test-report-form.php` still ends `ALL PASS (163 checks)`: the tighter rules still accept all 115 questions of the four hand-written forms.

- [ ] **Step 5: Run everything** as the Global Constraints say. Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 6: Commit.**

```bash
git add includes/tracks includes/modules/class-wpcpm-institutions.php bin/test-track-definition.php bin/test-track-store.php bin/test-tracks.php
git commit -m "Track Builder T2a: the rules tightened - whole-number columns, lengths in characters, near-matches of the sync columns, names two tracks share, and a store that refuses what it cannot write"
```

---

### Task 2: What compile() reads, and what it checks

**Files:**
- Modify: `includes/tracks/class-wpcpm-track-store.php`, `includes/tracks/class-wpcpm-tracks.php`, `includes/class-wpcpm-settings.php`, `uninstall.php`
- Test: `bin/test-track-store.php`, `bin/test-tracks.php`, `bin/test-settings.php`

**Interfaces:**
- Consumes: Task 1's `labels` context and `label_taken`.
- Produces, on `WPCPM_Track_Store`: `META_PUBLISHED` (`_wpcpm_track_published`), `META_LOG` (`_wpcpm_track_log`) and `OPT_SKIPPED` (`wpcpm_tracks_skipped`); `publish( $post_id, $user_id = 0 )`, the post ID or a `WP_Error` coded `wpcpm_track_missing` or `wpcpm_track_invalid`, whose data `errors` holds `validate()`'s list; `unpublish( $post_id, $user_id = 0 )`, the post ID or `wpcpm_track_not_published`; `published( $post_id )`, the definition as last published, or null; `state( $post_id )`: `draft`, `published`, `changed`, or an empty string for a post that is not a track; `log( $post_id, $did, $user_id = 0 )` and `log_entries( $post_id )`, each entry `at`, `by` and `did`. `compile()` reads published copies only and records what it leaves out. On `WPCPM_Tracks`: `validation_context( $own_status = '', $compiled = true )`, where false reads the program map without the compiled tracks, and `builtin_key( $status )`, the key the PHP gives a status or an empty string. On `WPCPM_Settings`: `add_student_status( $status )`, true when the list changed.

- [ ] **Step 1: Write the failing checks.** Apply these three changes. The store suite now loads the real `WPCPM_Program` and two small stand-ins, because `compile()` asks the program map about the other tracks:

```diff
--- a/bin/test-track-store.php
+++ b/bin/test-track-store.php
@@ -19,9 +19,10 @@ if ( 'cli' !== PHP_SAPI ) {
 define( 'ABSPATH', __DIR__ . '/' );
 
 class WP_Error {
-	private $code;
-	public function __construct( $c = '' ) { $this->code = $c; }
+	private $code, $data;
+	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->data = $d; }
 	public function get_error_code() { return $this->code; }
+	public function get_error_data() { return $this->data; }
 }
 class WP_Post {
 	public $ID = 0, $post_type = 'post', $post_status = 'draft', $post_title = '';
@@ -113,8 +114,27 @@ function get_posts( $args = array() ) {
 }
 function get_post_meta( $id, $key = '', $single = false ) { return $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? ''; }
 function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = wp_unslash( $value ); return true; } // Unslashed, as WordPress does.
+function apply_filters( $tag, $value ) { return $value; } // Nothing hooked: the program map as its PHP describes it.
+function get_current_user_id() { return 7; }
+function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
+
+/** The settings: the list the status rule reads, and the one write publishing makes. */
+class WPCPM_Settings {
+	public static $added = array();
+	public static function get() { return array( 'past_statuses' => array( 'Graduate', 'Dropped out' ) ); }
+	public static function add_student_status( $status ) { self::$added[] = $status; return true; }
+}
+
+/** The sync's column map, its report half: what the reserved-column rule reads. */
+class WPCPM_Mentors_Sync {
+	public static function fields() {
+		return array( 'report_name' => 'Name', 'report_email' => 'Email', 'report_status' => 'Status', 'report_mentor' => 'Mentor', 'report_instituton' => 'Educational institution', 'report_start' => 'Internship Start Date', 'report_end' => 'Internship End Date', 'report_link' => 'Personal link', 'report_link_50h' => '50h personal link', 'report_link_dev' => 'Dev Track ONLY personal link' );
+	}
+}
+
 function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ], $GLOBALS['revisions'][ (int) $id ] ); return true; }
 
+require_once __DIR__ . '/../includes/class-wpcpm-program.php';
 require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-palette.php';
 require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';
 require_once __DIR__ . '/../includes/tracks/class-wpcpm-tracks.php';
@@ -222,19 +242,20 @@ $a = WPCPM_Track_Store::create( track( 'Alpha Track', 'alpha' ) );
 $b = WPCPM_Track_Store::create( track( 'Beta Track', 'beta' ) );
 $c = WPCPM_Track_Store::create( track( 'Gamma Track', 'gamma' ) );
 $d = WPCPM_Track_Store::create( track( 'Delta Track', 'delta' ) );
+$GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ] = array();
+WPCPM_Tracks::rows(); // Read, so this request has the empty index in hand.
 foreach ( array( $a, $b, $d ) as $published ) {
-	wp_update_post( array( 'ID' => $published, 'post_status' => 'publish' ) );
+	WPCPM_Track_Store::publish( $published );
 }
 update_post_meta( $b, WPCPM_Track_Store::META_SOURCE, 'builtin' );
 update_post_meta( $a, WPCPM_Track_Store::META_AUTOMATION, '1' );
-update_post_meta( $d, WPCPM_Track_Store::META_DEFINITION, '{not json' );
-
-$GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ] = array();
-WPCPM_Tracks::rows(); // Read, so this request has the empty index in hand.
+update_post_meta( $d, WPCPM_Track_Store::META_PUBLISHED, '{not json' );
 $rows = WPCPM_Track_Store::compile();
 
 ck( 'only published tracks are compiled, in the order they were made', array_keys( $rows ), array( 'Alpha Track', 'Beta Track' ) );
-ck( 'a definition that cannot be read is left out rather than breaking the rest', isset( $rows['Delta Track'] ), false );
+ck( 'a published copy that cannot be read is left out rather than breaking the rest', isset( $rows['Delta Track'] ), false );
+ck( 'and the track list is told why', get_option( WPCPM_Track_Store::OPT_SKIPPED ), array( $d => array( 'unreadable' ) ) );
+ck( 'the list is not autoloaded: only the Track Builder reads it', $GLOBALS['autoload'][ WPCPM_Track_Store::OPT_SKIPPED ], false );
 ck( 'the index is autoloaded: labels() reads it for every row of every roster', array( $GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ] === $rows, $GLOBALS['autoload'][ WPCPM_Tracks::OPT_TRACKS ] ), array( true, true ) );
 ck( 'each form is an option of its own, not autoloaded', array( $GLOBALS['autoload']['wpcpm_track_fields_alpha'], $GLOBALS['autoload']['wpcpm_track_fields_beta'] ), array( false, false ) );
 ck( 'holding the form the live site draws, without the authoring properties', $GLOBALS['opts']['wpcpm_track_fields_alpha'], WPCPM_Track_Definition::compile_fields( WPCPM_Track_Store::get( $a ) ) );
@@ -247,6 +268,103 @@ $rows = WPCPM_Track_Store::compile();
 ck( 'a track taken back to draft leaves the index', array_keys( $rows ), array( 'Beta Track' ) );
 ck( 'and its form option goes with it', array_key_exists( 'wpcpm_track_fields_alpha', $GLOBALS['opts'] ), false );
 
+echo "\n=== What compile() reads: the copy each track was published with ===\n";
+
+$GLOBALS['posts']     = array();
+$GLOBALS['pmeta']     = array();
+$GLOBALS['revisions'] = array();
+$GLOBALS['opts']      = array();
+WPCPM_Tracks::flush();
+WPCPM_Settings::$added = array();
+
+$alpha = WPCPM_Track_Store::create( track( 'Alpha Track', 'alpha' ) );
+ck( 'a new track is a draft', WPCPM_Track_Store::state( $alpha ), 'draft' );
+ck( 'publish() hands back the track', WPCPM_Track_Store::publish( $alpha ), $alpha );
+ck( 'now published, its copy the definition as it stood', array( WPCPM_Track_Store::state( $alpha ), WPCPM_Track_Store::published( $alpha ) ), array( 'published', WPCPM_Track_Store::get( $alpha ) ) );
+ck( 'and compiled', WPCPM_Tracks::rows()['Alpha Track']['label'], 'Alpha Track' );
+ck( 'its status joins "Currently mentoring", so the students sync reads its students', WPCPM_Settings::$added, array( 'Alpha Track' ) );
+
+WPCPM_Track_Store::save( $alpha, track( 'Alpha Track', 'alpha', 'Alpha Track, renamed' ) );
+ck( 'an edit saved to a published track is an unpublished change', WPCPM_Track_Store::state( $alpha ), 'changed' );
+ck( 'which students do not see', WPCPM_Tracks::rows()['Alpha Track']['label'], 'Alpha Track' );
+
+$beta = WPCPM_Track_Store::create( track( 'Beta Track', 'beta' ) );
+WPCPM_Track_Store::publish( $beta );
+ck( 'nor once another track is published and everything compiles again (the design\'s decision 3.2)', array( WPCPM_Tracks::rows()['Alpha Track']['label'], isset( WPCPM_Tracks::rows()['Beta Track'] ) ), array( 'Alpha Track', true ) );
+
+WPCPM_Track_Store::publish( $alpha );
+ck( 'until it is published itself', array( WPCPM_Tracks::rows()['Alpha Track']['label'], WPCPM_Track_Store::state( $alpha ) ), array( 'Alpha Track, renamed', 'published' ) );
+
+echo "\n=== What publish() refuses ===\n";
+
+/** Create a track, publish it, and say what came back: the code, the rules' codes, and its state. */
+function publish_new( array $definition, $source = '' ) {
+	$id = WPCPM_Track_Store::create( $definition );
+
+	if ( '' !== $source ) {
+		update_post_meta( $id, WPCPM_Track_Store::META_SOURCE, $source );
+	}
+
+	$result = WPCPM_Track_Store::publish( $id );
+	$errors = $result instanceof WP_Error ? array_column( $result->get_error_data()['errors'], 'code' ) : array();
+
+	return array( $result instanceof WP_Error ? $result->get_error_code() : 'published', $errors, WPCPM_Track_Store::state( $id ) );
+}
+
+$status_question               = track( 'Gamma Track', 'gamma' );
+$status_question['questions']['Status'] = array( 'label' => 'Your status', 'type' => 'text', 'group' => 'project' );
+ck( 'a question on a column the syncs own, which would let a student move tracks', publish_new( $status_question ), array( 'wpcpm_track_invalid', array( 'column_reserved' ), 'draft' ) );
+ck( 'one of the four built-in statuses, which only the built-in track may hold', publish_new( track( 'Designer Track', 'designer-two', 'Designer Two' ) ), array( 'wpcpm_track_invalid', array( 'status_taken' ), 'draft' ) );
+ck( 'a key the stylesheets already paint', publish_new( track( 'Design Two Track', 'design' ) ), array( 'wpcpm_track_invalid', array( 'key_reserved' ), 'draft' ) );
+ck( 'a built-in track\'s name', publish_new( track( 'Design Two Track', 'design-two', 'Designer Track' ) ), array( 'wpcpm_track_invalid', array( 'label_taken' ), 'draft' ) );
+ck( 'another published track\'s status', publish_new( track( 'Beta Track', 'beta-two', 'Beta Two' ) ), array( 'wpcpm_track_invalid', array( 'status_taken' ), 'draft' ) );
+WPCPM_Track_Store::save( $alpha, track( 'Alpha Program', 'alpha' ) );
+$moved = WPCPM_Track_Store::publish( $alpha );
+ck( 'a new status for a track already published', array( $moved->get_error_code(), array_column( $moved->get_error_data()['errors'], 'code' ) ), array( 'wpcpm_track_invalid', array( 'status_locked' ) ) );
+ck( 'and nothing live changes for it', array( WPCPM_Tracks::rows()['Alpha Track']['label'], WPCPM_Track_Store::state( $alpha ) ), array( 'Alpha Track, renamed', 'changed' ) );
+WPCPM_Track_Store::save( $alpha, WPCPM_Track_Store::published( $alpha ) );
+
+$seed          = track( 'Designer Track', 'design' );
+$seed['label'] = 'Designer Track';
+ck( 'a built-in track\'s own definition keeps its status, its key and its name', publish_new( $seed, 'builtin' ), array( 'published', array(), 'published' ) );
+ck( 'and is compiled as built-in, so its PHP keeps running it', WPCPM_Tracks::rows()['Designer Track']['source'], 'builtin' );
+
+echo "\n=== What compile() leaves out ===\n";
+
+/** Write a published copy by hand, as nothing but a bug or a hand edit would. */
+function tamper( $post_id, callable $change ) {
+	$copy = WPCPM_Track_Store::published( $post_id );
+	$change( $copy );
+	update_post_meta( $post_id, WPCPM_Track_Store::META_PUBLISHED, wp_slash( WPCPM_Track_Definition::encode( $copy ) ) );
+}
+
+tamper( $beta, function ( &$copy ) { $copy['questions']['Status'] = array( 'label' => 'Your status', 'type' => 'text', 'group' => 'project' ); } );
+$rows = WPCPM_Track_Store::compile();
+ck( 'a copy the rules refuse is not compiled, though it was published', isset( $rows['Beta Track'] ), false );
+ck( 'and the track list is told why', get_option( WPCPM_Track_Store::OPT_SKIPPED )[ $beta ], array( 'column_reserved' ) );
+ck( 'while every other track compiles regardless', array_keys( $rows ), array( 'Alpha Track', 'Designer Track' ) );
+
+$late = WPCPM_Track_Store::create( track( 'Late Track', 'late' ) );
+WPCPM_Track_Store::publish( $late );
+tamper( $late, function ( &$copy ) { $copy['status'] = 'Alpha Track'; } );
+$rows = WPCPM_Track_Store::compile();
+ck( 'of two copies claiming one status, the first made keeps it', array( $rows['Alpha Track']['post'], get_option( WPCPM_Track_Store::OPT_SKIPPED )[ $late ] ), array( $alpha, array( 'status_taken' ) ) );
+tamper( $late, function ( &$copy ) { $copy['status'] = 'Developer Track'; } );
+$rows = WPCPM_Track_Store::compile();
+ck( 'and a copy holding a built-in status must hold its key as well', array( isset( $rows['Developer Track'] ), get_option( WPCPM_Track_Store::OPT_SKIPPED )[ $late ] ), array( false, array( 'key_locked' ) ) );
+
+echo "\n=== unpublish(), and the log ===\n";
+
+ck( 'unpublish() hands back the track', WPCPM_Track_Store::unpublish( $alpha ), $alpha );
+ck( 'which is a draft again, off the live site', array( WPCPM_Track_Store::state( $alpha ), isset( WPCPM_Tracks::rows()['Alpha Track'] ) ), array( 'draft', false ) );
+ck( 'its published copy kept, as the record of what was live', WPCPM_Track_Store::published( $alpha )['label'], 'Alpha Track, renamed' );
+ck( 'a track that is not published cannot be unpublished', WPCPM_Track_Store::unpublish( $alpha )->get_error_code(), 'wpcpm_track_not_published' );
+ck( 'the log says what happened, in order, and who did it', array( array_column( WPCPM_Track_Store::log_entries( $alpha ), 'did' ), array_values( array_unique( array_column( WPCPM_Track_Store::log_entries( $alpha ), 'by' ) ) ) ), array( array( 'publish', 'publish', 'unpublish' ), array( 7 ) ) );
+WPCPM_Track_Store::publish( $beta, 42 );
+$entries = WPCPM_Track_Store::log_entries( $beta );
+ck( 'naming the person the screen says published it', end( $entries )['by'], 42 );
+ck( 'and a timestamp', is_int( end( $entries )['at'] ) && end( $entries )['at'] > 0, true );
+
 echo "\n=== delete_all(), for uninstall ===\n";
 
 $trashed = WPCPM_Track_Store::create( track( 'Trashed Track', 'trashed' ) );
@@ -268,6 +386,7 @@ echo "\n=== Wired into the plugin ===\n";
 $uninstall = (string) file_get_contents( __DIR__ . '/../uninstall.php' );
 ck( 'uninstall.php loads the store and the two classes it reads', array( false !== strpos( $uninstall, "includes/tracks/class-wpcpm-track-store.php'" ), false !== strpos( $uninstall, "includes/tracks/class-wpcpm-tracks.php'" ), false !== strpos( $uninstall, "includes/tracks/class-wpcpm-track-definition.php'" ) ), array( true, true, true ) );
 ck( 'and calls delete_all()', false !== strpos( $uninstall, 'WPCPM_Track_Store::delete_all();' ), true );
+ck( 'and sweeps any form option the index lost track of', false !== strpos( $uninstall, "array( 'wpcpm_institution_modules_', WPCPM_Tracks::OPT_FIELDS_PREFIX ) as \$wpcpm_prefix" ), true );
 $main = (string) file_get_contents( __DIR__ . '/../wpcredits-program-manager.php' );
 ck( 'the plugin boots the store and the runtime', array( false !== strpos( $main, 'WPCPM_Track_Store::init();' ), false !== strpos( $main, 'WPCPM_Tracks::init();' ) ), array( true, true ) );
 
```

```diff
--- a/bin/test-tracks.php
+++ b/bin/test-tracks.php
@@ -224,6 +224,15 @@ ck( 'the statuses that mean something else: past students, and the two states',
 ck( 'the sync columns, and nothing locked', array( $context['reserved_columns'], $context['locked'] ), array( WPCPM_Tracks::reserved_columns(), null ) );
 ck( 'and the track it describes passes with it', WPCPM_Track_Definition::validate( array( 'schema_version' => 1, 'status' => 'Marketing Track', 'key' => 'marketing', 'label' => 'Marketing Track', 'hue' => 'cyan', 'questions' => $form ), $context ), array() );
 
+echo "\n=== The program map as its PHP alone describes it ===\n";
+
+compiled( array( 'Marketing Track' => row( 'marketing', 'Marketing Track' ) ), array( 'marketing' => $form ) );
+ck( 'with a track compiled, the context counts it', array_key_exists( 'Marketing Track', WPCPM_Tracks::validation_context()['tracks'] ), true );
+ck( 'read without the compiled tracks, it holds the four the PHP knows', WPCPM_Tracks::validation_context( '', false )['tracks'], array( 'In Sensei' => '150h', 'In Sensei 50h' => '50h', 'Developer Track' => 'dev', 'Designer Track' => 'design' ) );
+ck( 'and the map answers with the compiled track again afterwards', WPCPM_Program::is_track( 'Marketing Track' ), true );
+ck( 'the key the PHP gives a built-in status', array( WPCPM_Tracks::builtin_key( 'Designer Track' ), WPCPM_Tracks::builtin_key( 'In Sensei' ) ), array( 'design', '150h' ) );
+ck( 'and none for a compiled track, or for a status no track holds', array( WPCPM_Tracks::builtin_key( 'Marketing Track' ), WPCPM_Tracks::builtin_key( 'Graduate' ) ), array( '', '' ) );
+
 printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
 
 exit( $fails ? 1 : 0 );
```

```diff
--- a/bin/test-settings.php
+++ b/bin/test-settings.php
@@ -723,6 +723,16 @@ $saved = WPCPM_Settings::save( array( 'tools_mentors' => '1' ) );
 ck( 'tools_students stays off when a save omits it, like sponsor_home', $saved['tools_students'], false );
 ck( 'tools_mentors carried as 1 reads as on', $saved['tools_mentors'], true );
 
+echo "\n=== A published track's status joins the list, and nothing leaves it (1.101.0) ===\n";
+
+$GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'student_statuses' => array( 'In Sensei', 'Paused' ) ) );
+ck( 'a status the list lacks is appended', array( WPCPM_Settings::add_student_status( 'Marketing Track' ), $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'] ), array( true, array( 'In Sensei', 'Paused', 'Marketing Track' ) ) );
+ck( 'a status it holds is not added twice, and nothing is taken away', array( WPCPM_Settings::add_student_status( 'Marketing Track' ), $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'] ), array( false, array( 'In Sensei', 'Paused', 'Marketing Track' ) ) );
+ck( 'trimmed like every status, and nothing for an empty one', array( WPCPM_Settings::add_student_status( ' Writing Track ' ), WPCPM_Settings::add_student_status( '  ' ), end( $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'] ) ), array( true, false, 'Writing Track' ) );
+$GLOBALS['opts'] = array( WPCPM_Settings::OPT_NAME => array( 'api_token' => 'kept' ) );
+WPCPM_Settings::add_student_status( 'Marketing Track' );
+ck( 'a saved option with no list starts from the default one, and keeps its other settings', array( $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['student_statuses'], $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ]['api_token'] ), array( array_merge( WPCPM_Settings::defaults()['student_statuses'], array( 'Marketing Track' ) ), 'kept' ) );
+
 echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
 
 exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them, and see them fail.**

```bash
php bin/test-track-store.php 2>&1 | grep -m1 'Fatal error'
php bin/test-tracks.php 2>&1 | grep -E '^FAIL|Fatal error' | head -n 2
php bin/test-settings.php 2>&1 | grep -m1 'Fatal error'
```

Expected: the store suite stops with `Call to undefined method WPCPM_Track_Store::publish()`; the reader's fails "read without the compiled tracks, it holds the four the PHP knows" and then stops with `Call to undefined method WPCPM_Tracks::builtin_key()`; the settings suite stops with `Call to undefined method WPCPM_Settings::add_student_status()`.

- [ ] **Step 3: Write the code.** Apply these four changes. The uninstall sweep is a loop over two prefixes rather than a second query or a second `LIKE`, because every extra mention of `$wpdb` in `uninstall.php` is one more phpcs warning:

```diff
--- a/includes/tracks/class-wpcpm-track-store.php
+++ b/includes/tracks/class-wpcpm-track-store.php
@@ -14,15 +14,14 @@ if ( ! defined( 'ABSPATH' ) ) {
  *
  * **A draft touches nothing; publishing is the one act that changes the live site** (the
  * design's decision 1.2). The post is where people edit: private, reachable through no generic
- * screen, with the definition in revisioned meta so every saved change is kept. `compile()` is
- * the only writer of what the site runs on (`WPCPM_Tracks`), and nothing calls it yet.
+ * screen, with the definition in revisioned meta so every saved change is kept. Publishing
+ * copies the definition as it stands into `META_PUBLISHED`, and `compile()`, the only writer of
+ * what the site runs on (`WPCPM_Tracks`), reads that copy and never the saved definition: an
+ * edit saved to a published track reaches no student until it is published, whatever else is
+ * compiled in the meantime (the design's decision 3.2 and its open item 5).
  *
- * `compile()` builds every published track from its latest saved definition, so a change saved
- * to a published track would reach students at the next compile of any track, not only its own.
- * Keeping an edit from students until it is published needs the definition that was published
- * recorded at publish time and read here instead (the design's open item 5). T2 adds that before
- * anything calls `compile()`, since publishing, unpublishing, the switch and the automation tick
- * all recompile.
+ * `compile()` checks every copy it compiles as well (open item 6), because a compile rebuilds
+ * every published track, including one whose surroundings changed after it was published.
  */
 final class WPCPM_Track_Store {
 
@@ -43,6 +42,26 @@ final class WPCPM_Track_Store {
 	/** `1` once somebody has ticked the reports automation item of the publish checklist (T2). */
 	const META_AUTOMATION = '_wpcpm_track_automation';
 
+	/**
+	 * The definition as it was last published: what `compile()` reads.
+	 *
+	 * Kept apart from the revisioned definition, which is what people edit, so saving a change to
+	 * a published track changes nothing students see until the change is published. Not the ID of
+	 * a revision either: a site may limit how many revisions it keeps, and this copy must outlive
+	 * them all. It stays when a track is unpublished, as the record of what was live.
+	 */
+	const META_PUBLISHED = '_wpcpm_track_published';
+
+	/** What happened to the track and who did it, oldest first: `log()` writes it. */
+	const META_LOG = '_wpcpm_track_log';
+
+	/**
+	 * The published tracks the last compile left out: post ID => the codes of what was wrong.
+	 *
+	 * For the track list to show. Not autoloaded, because only the Track Builder reads it.
+	 */
+	const OPT_SKIPPED = 'wpcpm_tracks_skipped';
+
 	/**
 	 * Register the type and its meta.
 	 *
@@ -221,38 +240,42 @@ final class WPCPM_Track_Store {
 	/**
 	 * Compile every published track into the options the live site runs on.
 	 *
-	 * Each form is written before the index that names it, so a request never finds a track in
-	 * the index without its form. A published track whose definition cannot be read is left out
-	 * rather than stopping the others, and the form of a track that has left the index is
-	 * deleted with it.
+	 * From each track's published copy, never its saved definition (the design's decision 3.2),
+	 * and only a copy every rule still accepts: a compile rebuilds every published track,
+	 * including one whose surroundings changed since it was published (a sync column renamed, a
+	 * status added to "Past students"), and the report form writes every column a compiled form
+	 * names. Tracks are checked in the order they were made, each against the ones compiled before
+	 * it, so of two that claim one status or key the first keeps it. What is left out is recorded
+	 * in `OPT_SKIPPED` for the track list, and the rest compile regardless (open item 6).
 	 *
-	 * It trusts every definition it reads: nothing here runs `WPCPM_Track_Definition::validate()`.
-	 * Checking each one here, and leaving out any that fails or that claims a status or key already
-	 * compiled (the first by post ID keeps it), is T2's first task (the design's open item 6).
+	 * Each form is written before the index that names it, so a request never finds a track in
+	 * the index without its form, and the form of a track that has left the index is deleted
+	 * with it.
 	 *
 	 * @return array The index written: status => row.
 	 */
 	public static function compile() {
 		$previous = get_option( WPCPM_Tracks::OPT_TRACKS, array() );
 		$rows     = array();
+		$skipped  = array();
+		$accepted = array();
 
-		$posts = get_posts(
-			array(
-				'post_type'   => self::POST_TYPE,
-				'post_status' => 'publish',
-				'numberposts' => -1,
-				'orderby'     => 'ID',
-				'order'       => 'ASC',
-			)
-		);
+		foreach ( self::published_posts() as $post ) {
+			$definition = self::published( $post->ID );
+
+			if ( ! is_array( $definition ) ) {
+				$skipped[ $post->ID ] = array( 'unreadable' );
+				continue;
+			}
 
-		foreach ( $posts as $post ) {
-			$definition = self::get( $post->ID );
+			$errors = WPCPM_Track_Definition::validate( $definition, self::context( $post->ID, $definition, $accepted, false ) );
 
-			if ( ! is_array( $definition ) || empty( $definition['status'] ) || empty( $definition['key'] ) ) {
+			if ( array() !== $errors ) {
+				$skipped[ $post->ID ] = array_values( array_unique( array_column( $errors, 'code' ) ) );
 				continue;
 			}
 
+			$accepted[] = $definition;
 			$source     = 'builtin' === get_post_meta( $post->ID, self::META_SOURCE, true ) ? 'builtin' : 'definition';
 			$automation = '1' === (string) get_post_meta( $post->ID, self::META_AUTOMATION, true );
 
@@ -270,11 +293,251 @@ final class WPCPM_Track_Store {
 		}
 
 		update_option( WPCPM_Tracks::OPT_TRACKS, $rows, true );
+		update_option( self::OPT_SKIPPED, $skipped, false );
 		WPCPM_Tracks::flush();
 
 		return $rows;
 	}
 
+	/**
+	 * Publish a track: check it, copy it as it stands, and compile.
+	 *
+	 * The copy is what `compile()` reads from now on (the design's decision 3.2). The track is
+	 * checked against every other published track, so two can never be published claiming one
+	 * status, key or name, and its status joins "Currently mentoring", because the students sync
+	 * reads only the statuses listed there (7.2). What the Track Builder screen does around this -
+	 * the preflight, the checklist, the lock - is the screen's; this is the part every path shares.
+	 *
+	 * @param int $post_id The track.
+	 * @param int $user_id Who published it, for the log; 0 for the current user.
+	 * @return int|WP_Error The post ID, or why the track was not published.
+	 */
+	public static function publish( $post_id, $user_id = 0 ) {
+		$post_id    = (int) $post_id;
+		$definition = self::get( $post_id );
+
+		if ( ! is_array( $definition ) ) {
+			return new WP_Error( 'wpcpm_track_missing', __( 'That track does not exist.', 'wpcredits-program-manager' ) );
+		}
+
+		$others = array();
+
+		foreach ( self::published_posts() as $post ) {
+			$copy = (int) $post->ID !== $post_id ? self::published( $post->ID ) : null;
+
+			if ( is_array( $copy ) ) {
+				$others[] = $copy;
+			}
+		}
+
+		$errors = WPCPM_Track_Definition::validate( $definition, self::context( $post_id, $definition, $others, true ) );
+
+		if ( array() !== $errors ) {
+			return new WP_Error( 'wpcpm_track_invalid', $errors[0]['message'], array( 'errors' => $errors ) );
+		}
+
+		update_post_meta( $post_id, self::META_PUBLISHED, wp_slash( WPCPM_Track_Definition::encode( $definition ) ) );
+		wp_update_post(
+			array(
+				'ID'          => $post_id,
+				'post_status' => 'publish',
+			)
+		);
+		self::compile();
+		WPCPM_Settings::add_student_status( (string) $definition['status'] );
+		self::log( $post_id, 'publish', $user_id );
+
+		return $post_id;
+	}
+
+	/**
+	 * Take a track off the live site: back to draft, compiled out, kept.
+	 *
+	 * Nothing is deleted (the design's decision 3.9): the published copy stays as the record of
+	 * what was live, and the status stays in "Currently mentoring", because removing it would
+	 * take the Student role from everybody still on the track at the next sync (7.5). Refusing
+	 * while students hold the status is the screen's, which can count them.
+	 *
+	 * @param int $post_id The track.
+	 * @param int $user_id Who unpublished it, for the log; 0 for the current user.
+	 * @return int|WP_Error The post ID, or why nothing was done.
+	 */
+	public static function unpublish( $post_id, $user_id = 0 ) {
+		$post = self::track_post( $post_id );
+
+		if ( null === $post || 'publish' !== $post->post_status ) {
+			return new WP_Error( 'wpcpm_track_not_published', __( 'That track is not published.', 'wpcredits-program-manager' ) );
+		}
+
+		wp_update_post(
+			array(
+				'ID'          => (int) $post_id,
+				'post_status' => 'draft',
+			)
+		);
+		self::compile();
+		self::log( $post_id, 'unpublish', $user_id );
+
+		return (int) $post_id;
+	}
+
+	/**
+	 * A track's definition as it was last published.
+	 *
+	 * @param int $post_id The track.
+	 * @return array|null Null for a track never published, or a post that is not a track.
+	 */
+	public static function published( $post_id ) {
+		if ( null === self::track_post( $post_id ) ) {
+			return null;
+		}
+
+		return WPCPM_Track_Definition::decode( get_post_meta( (int) $post_id, self::META_PUBLISHED, true ) );
+	}
+
+	/**
+	 * Where a track stands.
+	 *
+	 * @param int $post_id The track.
+	 * @return string `draft`; `published`; `changed` for a published track with a saved edit not
+	 *                yet published; or an empty string for a post that is not a track.
+	 */
+	public static function state( $post_id ) {
+		$post = self::track_post( $post_id );
+
+		if ( null === $post ) {
+			return '';
+		}
+
+		if ( 'publish' !== $post->post_status ) {
+			return 'draft';
+		}
+
+		return self::get( $post_id ) === self::published( $post_id ) ? 'published' : 'changed';
+	}
+
+	/**
+	 * Add a line to a track's log.
+	 *
+	 * @param int    $post_id The track.
+	 * @param string $did     What happened, as a code: `publish`, `unpublish` and the like.
+	 * @param int    $user_id Who did it; 0 for the current user.
+	 */
+	public static function log( $post_id, $did, $user_id = 0 ) {
+		$entries   = self::log_entries( $post_id );
+		$entries[] = array(
+			'at'  => time(),
+			'by'  => $user_id ? (int) $user_id : get_current_user_id(),
+			'did' => sanitize_key( $did ),
+		);
+
+		update_post_meta( (int) $post_id, self::META_LOG, $entries );
+	}
+
+	/**
+	 * A track's log, oldest first.
+	 *
+	 * @param int $post_id The track.
+	 * @return array[] Each with `at` (a timestamp), `by` (a user ID) and `did`.
+	 */
+	public static function log_entries( $post_id ) {
+		$entries = get_post_meta( (int) $post_id, self::META_LOG, true );
+
+		return is_array( $entries ) ? $entries : array();
+	}
+
+	/**
+	 * Every published track, oldest first.
+	 *
+	 * @return WP_Post[]
+	 */
+	private static function published_posts() {
+		return get_posts(
+			array(
+				'post_type'   => self::POST_TYPE,
+				'post_status' => 'publish',
+				'numberposts' => -1,
+				'orderby'     => 'ID',
+				'order'       => 'ASC',
+			)
+		);
+	}
+
+	/**
+	 * What the rules are told when a track is published or compiled.
+	 *
+	 * The site as its PHP describes it, from `WPCPM_Tracks::validation_context()` with the
+	 * compiled tracks left out, and then the published tracks this one is checked against: every
+	 * other one when it is published, the ones already compiled when it is compiled. Its own
+	 * status comes off the list only when it is locked to it, so a new track can never take a
+	 * status that already names a track, one of the four built-in ones included.
+	 *
+	 * @param int   $post_id    The track.
+	 * @param array $definition Its definition.
+	 * @param array $others     The published definitions it is checked against.
+	 * @param bool  $publishing Whether it is being published, rather than compiled.
+	 * @return array
+	 */
+	private static function context( $post_id, array $definition, array $others, $publishing ) {
+		$status  = isset( $definition['status'] ) ? (string) $definition['status'] : '';
+		$context = WPCPM_Tracks::validation_context( '', false );
+		$locked  = self::locked( $post_id, $status, $publishing );
+
+		if ( null !== $locked ) {
+			unset( $context['tracks'][ $locked['status'] ], $context['labels'][ $locked['status'] ] );
+			$context['locked'] = $locked;
+		}
+
+		foreach ( $others as $other ) {
+			if ( isset( $other['status'], $other['key'] ) ) {
+				$context['tracks'][ (string) $other['status'] ] = (string) $other['key'];
+				$context['labels'][ (string) $other['status'] ] = isset( $other['label'] ) ? (string) $other['label'] : '';
+			}
+		}
+
+		return $context;
+	}
+
+	/**
+	 * The status and key a track may not change, and which therefore pass as its own.
+	 *
+	 * A track published before keeps what it was published with (the design's 4.1). A built-in
+	 * track keeps its PHP's: the seeded definition when it is published, and at every compile the
+	 * copy of any track holding one of the four built-in statuses, which must then hold that
+	 * track's key as well. Every other track has nothing locked, so the four statuses and the
+	 * reserved keys are refused to it.
+	 *
+	 * @param int    $post_id    The track.
+	 * @param string $status     Its status.
+	 * @param bool   $publishing Whether it is being published, rather than compiled.
+	 * @return array|null `status` and `key`, or null.
+	 */
+	private static function locked( $post_id, $status, $publishing ) {
+		$builtin = WPCPM_Tracks::builtin_key( $status );
+
+		if ( $publishing ) {
+			$published = self::published( $post_id );
+
+			if ( is_array( $published ) && isset( $published['status'], $published['key'] ) ) {
+				return array(
+					'status' => (string) $published['status'],
+					'key'    => (string) $published['key'],
+				);
+			}
+
+			if ( '' === $builtin || 'builtin' !== get_post_meta( (int) $post_id, self::META_SOURCE, true ) ) {
+				return null;
+			}
+		} elseif ( '' === $builtin ) {
+			return null;
+		}
+
+		return array(
+			'status' => (string) $status,
+			'key'    => $builtin,
+		);
+	}
+
 	/**
 	 * Delete every track, its revisions and the options compiled from it. Called on uninstall.
 	 *
@@ -309,6 +572,7 @@ final class WPCPM_Track_Store {
 		}
 
 		delete_option( WPCPM_Tracks::OPT_TRACKS );
+		delete_option( self::OPT_SKIPPED );
 		WPCPM_Tracks::flush();
 	}
 }
```

```diff
--- a/includes/tracks/class-wpcpm-tracks.php
+++ b/includes/tracks/class-wpcpm-tracks.php
@@ -18,8 +18,8 @@ if ( ! defined( 'ABSPATH' ) ) {
  * autoloaded and small, and one `OPT_FIELDS_PREFIX` option per track holding its form, read on
  * the pages that draw one. The design gives a second reason for the options, that editing a
  * published track must not change the form students see until somebody publishes the change,
- * and that holds only once the compile reads what was published rather than what was last
- * saved (the design's open item 5).
+ * and it holds because the compile reads what was published, never what was last saved
+ * (`WPCPM_Track_Store::META_PUBLISHED`).
  *
  * A row whose source is `builtin` is a migrated track its PHP still runs (the design's decision
  * 3.5): every callback skips it, so the hand-written code stays authoritative until a Program
@@ -57,6 +57,13 @@ final class WPCPM_Tracks {
 	 */
 	private static $forms = array();
 
+	/**
+	 * Above zero while the PHP maps are read without the compiled tracks.
+	 *
+	 * @var int
+	 */
+	private static $suspended = 0;
+
 	/**
 	 * Hook the tracks into the program map, the form and the stylesheet.
 	 */
@@ -92,6 +99,10 @@ final class WPCPM_Tracks {
 	 * @return array<string, array> Status => row.
 	 */
 	public static function live() {
+		if ( self::$suspended > 0 ) {
+			return array();
+		}
+
 		return array_filter(
 			self::rows(),
 			static function ( $row ) {
@@ -315,9 +326,19 @@ final class WPCPM_Tracks {
 	 * What `WPCPM_Track_Definition::validate()` needs to know about the site.
 	 *
 	 * @param string $own_status The status of the track being checked, left out of the others.
+	 * @param bool   $compiled   False to read the program map as its PHP alone describes it, which
+	 *                           is how the store checks a track against the others it compiles.
 	 * @return array
 	 */
-	public static function validation_context( $own_status = '' ) {
+	public static function validation_context( $own_status = '', $compiled = true ) {
+		if ( ! $compiled ) {
+			return self::unfiltered(
+				static function () use ( $own_status ) {
+					return self::validation_context( $own_status );
+				}
+			);
+		}
+
 		$tracks = array();
 		$labels = array();
 
@@ -342,4 +363,35 @@ final class WPCPM_Tracks {
 			'locked'           => null,
 		);
 	}
+
+	/**
+	 * The key the PHP gives a status, compiled tracks aside: one of the four built-in keys, or an
+	 * empty string for a status no built-in track holds.
+	 *
+	 * @param string $status Status.
+	 * @return string
+	 */
+	public static function builtin_key( $status ) {
+		return self::unfiltered(
+			static function () use ( $status ) {
+				return WPCPM_Program::is_track( $status ) ? (string) WPCPM_Program::track( $status ) : '';
+			}
+		);
+	}
+
+	/**
+	 * Run a read of the program map with no compiled track in it.
+	 *
+	 * @param callable $read The read.
+	 * @return mixed What it returned.
+	 */
+	private static function unfiltered( callable $read ) {
+		++self::$suspended;
+
+		try {
+			return $read();
+		} finally {
+			--self::$suspended;
+		}
+	}
 }
```

```diff
--- a/includes/class-wpcpm-settings.php
+++ b/includes/class-wpcpm-settings.php
@@ -540,6 +540,40 @@ class WPCPM_Settings {
 		update_option( self::OPT_VERSION, self::SETTINGS_VERSION );
 	}
 
+	/**
+	 * Add a status to "Currently mentoring", and never take one away.
+	 *
+	 * Publishing a Track Builder track calls it (7.2 of the design): the students sync reads only
+	 * the statuses listed here, and `WPCPM_Students_Sync::revoke_departed()` treats a student it
+	 * did not read as departed, so a published track's students would lose their role at the next
+	 * run without it. Matched exactly, as the sync matches: a status the list holds in another
+	 * case would not fetch this one's students, so it does not count as present.
+	 *
+	 * @param string $status Status.
+	 * @return bool Whether the list changed.
+	 */
+	public static function add_student_status( $status ) {
+		$status = trim( (string) $status );
+
+		if ( '' === $status ) {
+			return false;
+		}
+
+		$stored   = get_option( self::OPT_NAME, array() );
+		$stored   = is_array( $stored ) ? $stored : array();
+		$statuses = isset( $stored['student_statuses'] ) && is_array( $stored['student_statuses'] ) ? $stored['student_statuses'] : self::defaults()['student_statuses'];
+
+		if ( in_array( $status, $statuses, true ) ) {
+			return false;
+		}
+
+		$statuses[]                 = $status;
+		$stored['student_statuses'] = $statuses;
+		update_option( self::OPT_NAME, $stored );
+
+		return true;
+	}
+
 	/**
 	 * Which schema version added which `student_statuses` entry.
 	 *
```

```diff
--- a/uninstall.php
+++ b/uninstall.php
@@ -186,9 +186,13 @@ delete_metadata( 'user', 0, 'wpcpm_student_modules', '', true );
 // wp-admin, where what else uses them is visible.
 delete_metadata( 'user', 0, WPCPM_Student_Report_Form::META_IMAGES, '', true );
 
-// Every institution's module order (1.96.4).
-foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'wpcpm_institution_modules_' ) . '%' ) ) as $wpcpm_module_option ) {
-	delete_option( $wpcpm_module_option );
+// Every institution's module order (1.96.4), and every Track Builder form (1.101.0), a form the
+// index lost track of included: `WPCPM_Track_Store::delete_all()` below finds forms through the
+// posts and the index only.
+foreach ( array( 'wpcpm_institution_modules_', WPCPM_Tracks::OPT_FIELDS_PREFIX ) as $wpcpm_prefix ) {
+	foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $wpcpm_prefix ) . '%' ) ) as $wpcpm_swept_option ) {
+		delete_option( $wpcpm_swept_option );
+	}
 }
 
 // The Track Builder's tracks (1.100.0): every definition post with its revisions, and the
```

- [ ] **Step 4: Run the three suites.** Expected: `ALL PASS (75 checks)`, `ALL PASS (48 checks)` and `ALL PASS`. Among the store's: an edit saved to a published track stays away from students even after another track is published and everything compiles again (the design's decision 3.2); a question on `Status`, a built-in status, a reserved key, a built-in track's name and another published track's status are each refused at publish; and a copy the rules refuse, a later copy claiming a status another holds, and a copy holding a built-in status under another key are each left out of the compile and named in `wpcpm_tracks_skipped`.

- [ ] **Step 5: Run everything.** Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 6: Commit.**

```bash
git add includes/tracks includes/class-wpcpm-settings.php uninstall.php bin/test-track-store.php bin/test-tracks.php bin/test-settings.php
git commit -m "Track Builder T2a: the live site runs the copy each track was published with, and every compile checks it again"
```

---

### Task 3: builtin_fields(), and the institution flag in the PHP

T1's handoff note 1, as a failing check first. `normalize()` turns `hide_from_institution` on for every `email` question, so until the PHP carries the flag, the Developer and Designer Track forms normalize to something other than themselves, and no seed of either could equal it byte for byte.

**Files:**
- Modify: `includes/modules/class-wpcpm-student-report-form.php`
- Test: `bin/test-report-form.php`

**Interfaces:**
- Consumes: `WPCPM_Track_Definition::normalize()`.
- Produces: `WPCPM_Student_Report_Form::builtin_fields( $track )`, the four hand-written forms, what `fields()` returned in 1.100.0 but for the flag; `fields( $track )` hands `builtin_fields()` to the filter `wpcpm_report_form_fields`. The three `ALUMNI_FIELDS` questions carry `hide_from_institution`, and `for_institution()` drops any flagged question as well.

- [ ] **Step 1: Write the failing checks.** Apply this change:

```diff
--- a/bin/test-report-form.php
+++ b/bin/test-report-form.php
@@ -1334,6 +1334,37 @@ foreach ( array( '150h', '50h', 'dev', 'design' ) as $parity_key ) {
 	ck( sprintf( 'and the %s form survives storage byte for byte', $parity_key ), WPCPM_Track_Definition::decode( WPCPM_Track_Definition::encode( $parity_definition ) ), $parity_definition );
 }
 
+foreach ( array( '150h', '50h', 'dev', 'design' ) as $move_key ) {
+	ck( sprintf( 'fields() is builtin_fields() while nothing hooks the filter: the %s form, moved as it was', $move_key ), WPCPM_Student_Report_Form::fields( $move_key ), WPCPM_Student_Report_Form::builtin_fields( $move_key ) );
+
+	$move_definition = array(
+		'schema_version' => WPCPM_Track_Definition::SCHEMA_VERSION,
+		'status'         => 'Parity ' . $move_key,
+		'key'            => 'parity-' . $move_key,
+		'label'          => 'Parity ' . $move_key,
+		'hue'            => 'blue',
+		'questions'      => WPCPM_Student_Report_Form::builtin_fields( $move_key ),
+	);
+
+	ck( sprintf( 'normalizing the %s form changes nothing, so its definition can be held to it byte for byte', $move_key ), WPCPM_Track_Definition::normalize( $move_definition )['questions'], $move_definition['questions'] );
+}
+
+foreach ( array( 'dev', 'design' ) as $alumni_key ) {
+	$alumni_flags = array();
+
+	foreach ( WPCPM_Student_Report_Form::ALUMNI_FIELDS as $alumni_name ) {
+		$alumni_flags[ $alumni_name ] = WPCPM_Student_Report_Form::builtin_fields( $alumni_key )[ $alumni_name ]['hide_from_institution'] ?? null;
+	}
+
+	ck( sprintf( 'the three alumni answers of the %s form carry the institution flag in the PHP too (the design\'s section 10)', $alumni_key ), $alumni_flags, array_fill_keys( WPCPM_Student_Report_Form::ALUMNI_FIELDS, true ) );
+}
+
+ck(
+	'for_institution() drops a question the flag keeps off the institution\'s view, and keeps the rest',
+	array_keys( WPCPM_Student_Report_Form::for_institution( array( 'Flagged' => array( 'type' => 'text', 'hide_from_institution' => true ), 'Shown' => array( 'type' => 'text' ) ) ) ),
+	array( 'Shown' )
+);
+
 printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
 
 
```

- [ ] **Step 2: Run it, and see it fail.** `php bin/test-report-form.php 2>&1 | grep -m1 'Fatal error'`. Expected: `Call to undefined method WPCPM_Student_Report_Form::builtin_fields()`.

- [ ] **Step 3: Move the forms into builtin_fields().** A pure move: `fields()` keeps its docblock and hands the moved body to the filter. Apply this change:

```diff
--- a/includes/modules/class-wpcpm-student-report-form.php
+++ b/includes/modules/class-wpcpm-student-report-form.php
@@ -128,12 +128,36 @@ class WPCPM_Student_Report_Form {
 	 * `step` mirrors the column's own precision: the grades allow two decimals, hours and the three
 	 * course marks are whole numbers.
 	 *
+	 * The hand-written forms are `builtin_fields()`. This is what everything reads, because a track
+	 * the Track Builder runs from its definition is handed its compiled form here, by the filter.
+	 *
 	 * @param string $track Track key from `WPCPM_Program::track()`. Any key the sets below do not
 	 *                      name, including the empty string a finished student has, gets the
 	 *                      150-hour form - the one most of them filled in.
 	 * @return array<string, array> Airtable field name => spec.
 	 */
 	public static function fields( $track ) {
+		/**
+		 * Filter the report form's fields for one track.
+		 *
+		 * @param array  $fields Airtable field name => spec.
+		 * @param string $track  Track key: `150h`, `50h`, `dev`, `design`, or a Track Builder track's.
+		 */
+		return (array) apply_filters( 'wpcpm_report_form_fields', self::builtin_fields( $track ), $track );
+	}
+
+	/**
+	 * The four hand-written forms, as `fields()` returned them before the Track Builder existed.
+	 *
+	 * A pure move out of `fields()` (the design's section 8): the seed definitions are held to what
+	 * this returns, byte for byte, and a built-in track may switch to its definition only while the
+	 * two are identical. It goes, with the rest of the hand-written tracks, on the product owner's
+	 * word (phase T5).
+	 *
+	 * @param string $track Track key; see `fields()`.
+	 * @return array<string, array> Airtable field name => spec.
+	 */
+	public static function builtin_fields( $track ) {
 		$grade = array(
 			'type'  => 'number',
 			'step'  => '0.01',
@@ -710,13 +734,7 @@ class WPCPM_Student_Report_Form {
 			}
 		}
 
-		/**
-		 * Filter the report form's fields for one track.
-		 *
-		 * @param array  $fields Airtable field name => spec.
-		 * @param string $track  Track key: `150h`, `50h`, `dev` or `design`.
-		 */
-		return (array) apply_filters( 'wpcpm_report_form_fields', $fields, $track );
+		return $fields;
 	}
 
 
```

- [ ] **Step 4: Run it, and see the flag checks fail.** `php bin/test-report-form.php | grep -E '^FAIL|checks\)'`. Expected, and nothing more:

```text
FAIL normalizing the dev form changes nothing, so its definition can be held to it byte for byte
FAIL normalizing the design form changes nothing, so its definition can be held to it byte for byte
FAIL the three alumni answers of the dev form carry the institution flag in the PHP too (the design's section 10)
FAIL the three alumni answers of the design form carry the institution flag in the PHP too (the design's section 10)
FAIL for_institution() drops a question the flag keeps off the institution's view, and keeps the rest
5 FAILED (174 checks)
```

- [ ] **Step 5: Add the flag, and read it.** Apply this change:

```diff
--- a/includes/modules/class-wpcpm-student-report-form.php
+++ b/includes/modules/class-wpcpm-student-report-form.php
@@ -303,24 +303,31 @@ class WPCPM_Student_Report_Form {
 		// base's own dev-track view puts them. They read as end-of-programme questions and were in
 		// Wrap-up until 1.63.0 - but where a question is asked is the program's decision, not an
 		// inference from what it sounds like, and the view is where that decision is recorded.
+		//
+		// All three are kept off the institution's view by `hide_from_institution` (the Track Builder
+		// design's section 10), the flag an authored question uses, so a seed definition can hold
+		// these byte for byte. `ALUMNI_FIELDS` keeps doing the same until phase T5.
 		$dev_alumni = array(
 			'Contributing beyond WP Credits'   => array(
-				'label' => __( 'How you plan to keep contributing after the program', 'wpcredits-program-manager' ),
-				'type'  => 'textarea',
-				'group' => 'project',
+				'label'                 => __( 'How you plan to keep contributing after the program', 'wpcredits-program-manager' ),
+				'type'                  => 'textarea',
+				'group'                 => 'project',
+				'hide_from_institution' => true,
 			),
 			'Alumni program: personal email'   => array(
-				'label' => __( 'A personal email address for the alumni program', 'wpcredits-program-manager' ),
-				'type'  => 'email',
-				'group' => 'project',
-				'help'  => __( 'Somewhere that still reaches you once your student address stops working.', 'wpcredits-program-manager' ),
+				'label'                 => __( 'A personal email address for the alumni program', 'wpcredits-program-manager' ),
+				'type'                  => 'email',
+				'group'                 => 'project',
+				'help'                  => __( 'Somewhere that still reaches you once your student address stops working.', 'wpcredits-program-manager' ),
+				'hide_from_institution' => true,
 			),
 			// The label says what is being agreed to. Repeating the column name here would ask for
 			// consent without stating what for.
 			'Alumni program: mentoring opt-in' => array(
-				'label' => __( 'Yes, I am happy to be contacted about mentoring future WordPress Credits students.', 'wpcredits-program-manager' ),
-				'type'  => 'checkbox',
-				'group' => 'project',
+				'label'                 => __( 'Yes, I am happy to be contacted about mentoring future WordPress Credits students.', 'wpcredits-program-manager' ),
+				'type'                  => 'checkbox',
+				'group'                 => 'project',
+				'hide_from_institution' => true,
 			),
 		);
 
@@ -1316,6 +1323,11 @@ class WPCPM_Student_Report_Form {
 	 * the student's arrangement with the program for after the course, not part of what a
 	 * school sent them to do. Public so the suite can hold the list to the promise.
 	 *
+	 * A question flagged `hide_from_institution` is dropped as well (1.101.0): the flag is how a
+	 * Track Builder question stays off this view, and the three alumni answers carry it in the PHP
+	 * too. `ALUMNI_FIELDS` stays until phase T5, with its suite holding the list to the promise
+	 * (the Track Builder design's section 10).
+	 *
 	 * @param array $fields Field specs, keyed by Airtable column name.
 	 * @return array The same array with those fields removed.
 	 */
@@ -1327,6 +1339,10 @@ class WPCPM_Student_Report_Form {
 				continue;
 			}
 
+			if ( ! empty( $spec['hide_from_institution'] ) ) {
+				continue;
+			}
+
 			if ( in_array( $name, self::ALUMNI_FIELDS, true ) ) {
 				continue;
 			}
```

- [ ] **Step 6: Run it.** Expected: `ALL PASS (174 checks)`.

- [ ] **Step 7: Run everything.** Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 8: Commit.**

```bash
git add includes/modules/class-wpcpm-student-report-form.php bin/test-report-form.php
git commit -m "Track Builder T2a: builtin_fields(), and the three alumni answers carry the institution flag in the PHP"
```

---

### Task 4: The four seeds, and their proof

**Files:**
- Create: `bin/test-track-definitions.php`, `bin/seed-definitions.php`, `bin/build-seeds.php`, `bin/fixtures/learn-course-297853.json`, `bin/fixtures/learn-course-322343.json`, `bin/fixtures/learn-course-402893.json`, `bin/fixtures/learn-course-403425.json`, and, written by `bin/build-seeds.php`, `includes/tracks/seeds/150h.json`, `50h.json`, `dev.json` and `design.json`
- Modify: `bin/fixtures/reports-table-fields.json`
- Test: `bin/test-track-definitions.php`, `bin/test-fixtures.php`

**Interfaces:**
- Consumes: `builtin_fields()` (Task 3); `WPCPM_Track_Definition::normalize()`, `validate()`, `compile_fields()` and `decode()`; the program map's `labels()`, `courses()`, `course_ids()` and `hours_targets()`.
- Produces: the four seed files, each a definition in the shape of the design's section 4 with the program's own status, key, name, course and hours, the hues `blue`, `purple`, `teal` and `pink`, and on each question its `airtable_type`, with `learn_lesson_id` and `why` where they apply. `bin/seed-definitions.php` defines `wpcpm_seed_tracks()`, `wpcpm_seed_path( $key )`, `wpcpm_seed_json( $definition )`, `wpcpm_seed_build( $key )`, `wpcpm_seed_definition( $key, $types, $course )`, `wpcpm_seed_lesson( $spec, $course )`, `wpcpm_seed_fold( $text )` and `wpcpm_seed_why()`.

- [ ] **Step 1: Write the failing checks.** Create `bin/test-track-definitions.php`:

```php
<?php
/**
 * The four seed definitions (Track Builder, phase T2a): each is its hand-written track, byte for byte.
 *
 * `includes/tracks/seeds/<key>.json` is what a site with no tracks starts from, and a built-in
 * track may switch to its definition only while the two are identical (the design's decision 3.5
 * and section 8). So each seed is held to the PHP it was taken from: its form to
 * `builtin_fields()`, its map to `WPCPM_Program`, its column types to the base's, its lessons to its
 * Learn course. And each is what bin/build-seeds.php builds today, so a form changed without the
 * seeds rebuilt fails here.
 *
 * Run from the plugin root:  php bin/test-track-definitions.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

function __( $s, $d = null ) { return $s; }
function apply_filters( $tag, $value ) { return $value; }

require_once __DIR__ . '/../includes/class-wpcpm-program.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-student-report-form.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-palette.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';
require_once __DIR__ . '/seed-definitions.php';

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

$reports = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/reports-table-fields.json' ), true );
$types   = isset( $reports['all_types'] ) ? (array) $reports['all_types'] : array();

// The columns the syncs own. bin/test-report-form.php holds this list to the real sync's map.
$reserved = array( 'Name', 'Email', 'Status', 'Mentor', 'Educational institution', 'Internship Start Date', 'Internship End Date', 'Personal link', '50h personal link', 'Dev Track ONLY personal link' );

echo "=== Four seeds, one for each hand-written track ===\n";

ck(
	'the four built-in tracks, by key, with their statuses and the hues their chips are painted in',
	wpcpm_seed_tracks(),
	array(
		'150h'   => array( 'status' => 'In Sensei', 'hue' => 'blue' ),
		'50h'    => array( 'status' => 'In Sensei 50h', 'hue' => 'purple' ),
		'dev'    => array( 'status' => 'Developer Track', 'hue' => 'teal' ),
		'design' => array( 'status' => 'Designer Track', 'hue' => 'pink' ),
	)
);

$lessons_named = array();
$why_named     = array();

foreach ( wpcpm_seed_tracks() as $key => $track ) {
	echo "\n=== The {$key} seed ===\n";

	$status = $track['status'];
	$file   = wpcpm_seed_path( $key );
	$stored = is_file( $file ) ? (string) file_get_contents( $file ) : '';
	$seed   = WPCPM_Track_Definition::decode( $stored );

	ck( 'is a stored definition', is_array( $seed ), true );

	if ( ! is_array( $seed ) ) {
		continue;
	}

	ck( 'and is what bin/build-seeds.php builds from the PHP today (run it, and commit what it writes)', $stored, wpcpm_seed_json( wpcpm_seed_build( $key ) ) );
	ck( 'its form is the hand-written one, byte for byte', WPCPM_Track_Definition::compile_fields( $seed ), WPCPM_Student_Report_Form::builtin_fields( $key ) );
	ck(
		'its status, key, name, course and hours are the program map\'s',
		array( $seed['status'], $seed['key'], $seed['label'], $seed['course_url'], $seed['learn_course_id'], $seed['hours_target'] ),
		array( $status, WPCPM_Program::track( $status ), WPCPM_Program::labels()[ $status ], WPCPM_Program::courses()[ $status ], WPCPM_Program::course_ids()[ $status ], WPCPM_Program::hours_targets()[ $status ] )
	);
	ck( 'it is stored normalized: normalizing it changes nothing', WPCPM_Track_Definition::normalize( $seed ), $seed );

	$context = array(
		'tracks'           => array(),
		'labels'           => array(),
		'refused_statuses' => array( 'Graduate', 'Dropped out', 'Paused', 'Pending graduation' ),
		'reserved_columns' => $reserved,
		'locked'           => array(
			'status' => $status,
			'key'    => $key,
		),
	);

	foreach ( wpcpm_seed_tracks() as $other_key => $other ) {
		if ( $other_key !== $key ) {
			$context['tracks'][ $other['status'] ] = $other_key;
			$context['labels'][ $other['status'] ] = WPCPM_Program::labels()[ $other['status'] ];
		}
	}

	ck( 'every rule accepts it, its reserved key kept by the lock a built-in track has', WPCPM_Track_Definition::validate( $seed, $context ), array() );

	$wrong_types = array();
	$lesson_ids  = array();

	foreach ( $seed['questions'] as $column => $spec ) {
		if ( ! isset( $spec['airtable_type'], $types[ $column ] ) || $spec['airtable_type'] !== $types[ $column ] ) {
			$wrong_types[] = $column;
		}

		if ( isset( $spec['learn_lesson_id'] ) ) {
			$lesson_ids[] = $spec['learn_lesson_id'];
		}

		if ( isset( $spec['why'] ) ) {
			$why_named[ $key ][] = $column;
		}
	}

	ck( 'every question records its column\'s Airtable type, as the base has it', $wrong_types, array() );

	$course = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/learn-course-' . WPCPM_Program::course_ids()[ $status ] . '.json' ), true );
	$known  = array();

	foreach ( (array) $course['modules'] as $module ) {
		foreach ( (array) $module['lessons'] as $lesson ) {
			$known[] = $lesson['id'];
		}
	}

	ck( 'and every Learn lesson it names is a lesson of its own course', array_values( array_diff( $lesson_ids, $known ) ), array() );

	$lessons_named[ $key ] = count( $lesson_ids );
}

echo "\n=== What the seeds say about Learn, and about the base ===\n";

ck( 'the Designer Track is organized by lesson, the other three by the program\'s own headings: the questions that report on a Learn lesson (the design\'s 2.6)', $lessons_named, array( '150h' => 1, '50h' => 0, 'dev' => 1, 'design' => 11 ) );
ck(
	'every column whose name looks like a slip says why, on each track that asks it (the design\'s section 8)',
	$why_named,
	array(
		'150h' => array(
			'Advance WordPress User - final grade',
		),
		'dev' => array(
			'Advance WordPress User - final grade',
			'Slack/GitHub/Blog WordPress Community meetings/discussions',
		),
		'design' => array(
			'Advance WordPress User - final grade',
			'Personal Website URL',
			'Post Reflection: Building Your Personal Website',
			'Practical: Duplicate & Explore WP Design Library - Reflection',
			'Practical: Duplicate & Explore WP Design Library - link',
			'Practical: Duplicate & Explore WP Design Library - image',
			'Practical: Local WordPress Environment for Design Testing - Tool used',
			'Practical: Change Your Site’s Global Styles - Notes',
			'Practical: Change Your Site’s Global Styles - Before Screenshot',
			'Practical: Change Your Site’s Global Styles - After Screenshot',
			'Slack/GitHub/Blog WordPress Community meetings/discussions',
		),
	)
);

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

Then apply this change to `bin/test-fixtures.php`:

```diff
--- a/bin/test-fixtures.php
+++ b/bin/test-fixtures.php
@@ -332,6 +332,18 @@ ck( 'the local-environment select offers the three tools, in the base\'s order a
     array( 'WordPress Studio', 'MAAMP', 'DevKinsta' ) );
 ck( 'and every field with choices is a typed field', not_offered( array_keys( $rep_choices ), array_keys( $rep_types ) ), array() );
 
+// The type of every field, which the Track Builder's seed definitions record (1.101.0).
+$rep_all = isset( $reports['all_types'] ) ? (array) $reports['all_types'] : array();
+ck( 'every listed field has its Airtable type in all_types, in the same order', array_keys( $rep_all ), $rep_fields );
+ck( 'and all_types agrees with every typed Designer Track column', array_diff_assoc( $rep_types, $rep_all ), array() );
+ck( 'read from the metadata API on a stated day', isset( $reports['all_types_read'] ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $reports['all_types_read'] ), true );
+
+// The four Learn courses the seeds match their headings against.
+foreach ( array( 297853, 322343, 402893, 403425 ) as $learn_id ) {
+	$learn = fixture( 'learn-course-' . $learn_id . '.json' );
+	ck( sprintf( 'the Learn course %d fixture names its course and holds the three modules the form has groups for', $learn_id ), array( isset( $learn['course_id'] ) ? $learn['course_id'] : null, array_column( isset( $learn['modules'] ) ? (array) $learn['modules'] : array(), 'title' ) ), array( $learn_id, array( 'Onboarding', 'Project', 'Wrap-up' ) ) );
+}
+
 /* ---- the settings defaults agree with the base -------------------------- */
 
 echo "\n=== The settings defaults name what the base offers ===\n";
```

- [ ] **Step 2: Run them, and see them fail.**

```bash
php bin/test-track-definitions.php 2>&1 | grep -m1 'Fatal error'
php bin/test-fixtures.php | grep -E '^FAIL|FAILURE'
```

Expected: the first stops with `Failed opening required` for `bin/seed-definitions.php`. The second fails the three `all_types` checks and the four Learn course checks, and ends `7 FAILURE(S)`.

- [ ] **Step 3: Add the two fixtures.** They were read on 11 September 2026 and must be copied as they are, never read again: the seeds' counts and checksums below come from them. The four Learn outlines come first.

Create `bin/fixtures/learn-course-297853.json`:

```json
{
	"_comment": "The modules and lessons of one Learn WordPress course, read from the public, unauthenticated sensei-internal/v1/course-structure endpoint. bin/build-seeds.php matches the report form's headings against these lesson titles to record learn_lesson_id in the seed definitions, and bin/test-track-definitions.php checks the seeds against them. Refresh by reading the endpoint again.",
	"course_id": 297853,
	"course_url": "https://learn.wordpress.org/course/wordpress-credits/",
	"read": "2026-09-11",
	"modules": [
		{
			"title": "Onboarding",
			"lessons": [
				{
					"id": 299685,
					"title": "Welcome and Essential Communication Guidelines"
				},
				{
					"id": 297856,
					"title": "Share your WordPress profile"
				},
				{
					"id": 297874,
					"title": "Join global Slack"
				},
				{
					"id": 315361,
					"title": "Differences between WordPress.com and WordPress.org"
				},
				{
					"id": 297858,
					"title": "Complete: Open source basics and WordPress"
				},
				{
					"id": 297885,
					"title": "Complete: How decisions are made in the WordPress project"
				},
				{
					"id": 297891,
					"title": "Complete: Community meeting etiquette"
				},
				{
					"id": 297893,
					"title": "Complete: Writing in the WordPress voice"
				},
				{
					"id": 297895,
					"title": "Complete: Basic principles of conflict resolution"
				},
				{
					"id": 297882,
					"title": "Complete: Beginner, Intermediate, or Advanced WordPress User"
				},
				{
					"id": 297899,
					"title": "Create your personal website"
				},
				{
					"id": 297903,
					"title": "Reflection: Building your personal website"
				}
			]
		},
		{
			"title": "Project",
			"lessons": [
				{
					"id": 300214,
					"title": "How to contribute to WordPress"
				},
				{
					"id": 304804,
					"title": "Contribution Team Deep Dive"
				},
				{
					"id": 304809,
					"title": "Immediate Contribution Opportunities"
				},
				{
					"id": 297901,
					"title": "Define and begin developing your contribution project"
				},
				{
					"id": 297925,
					"title": "Reflection: Choosing Your Team and Project"
				},
				{
					"id": 297905,
					"title": "Connect with the WordPress Global Community"
				},
				{
					"id": 299660,
					"title": "Complete the first feedback form"
				},
				{
					"id": 297927,
					"title": "Reflection: Your first contribution"
				},
				{
					"id": 299664,
					"title": "Leave your mid-course feedback"
				},
				{
					"id": 297929,
					"title": "Reflection: Halfway Check-In"
				},
				{
					"id": 297907,
					"title": "Participate at a WordPress Event (online or in person)"
				}
			]
		},
		{
			"title": "Wrap-up",
			"lessons": [
				{
					"id": 297916,
					"title": "Prepare and deliver a wrap-up report"
				},
				{
					"id": 321361,
					"title": "Get your certificate"
				},
				{
					"id": 297918,
					"title": "Complete the feedback form"
				}
			]
		}
	]
}
```

Create `bin/fixtures/learn-course-322343.json`:

```json
{
	"_comment": "The modules and lessons of one Learn WordPress course, read from the public, unauthenticated sensei-internal/v1/course-structure endpoint. bin/build-seeds.php matches the report form's headings against these lesson titles to record learn_lesson_id in the seed definitions, and bin/test-track-definitions.php checks the seeds against them. Refresh by reading the endpoint again.",
	"course_id": 322343,
	"course_url": "https://learn.wordpress.org/course/50-hours-wordpress-credits/",
	"read": "2026-09-11",
	"modules": [
		{
			"title": "Onboarding",
			"lessons": [
				{
					"id": 322392,
					"title": "Welcome and Essential Communication Guidelines"
				},
				{
					"id": 322344,
					"title": "Share your WordPress profile"
				},
				{
					"id": 322358,
					"title": "Join global Slack"
				},
				{
					"id": 322399,
					"title": "Differences between WordPress.Com and WordPress.Org"
				},
				{
					"id": 322346,
					"title": "Complete: Open source basics and WordPress"
				},
				{
					"id": 322348,
					"title": "Complete: How decisions are made in the WordPress project"
				},
				{
					"id": 322354,
					"title": "Course: Basic principles of conflict resolution"
				}
			]
		},
		{
			"title": "Project",
			"lessons": [
				{
					"id": 322398,
					"title": "How to contribute to WordPress"
				},
				{
					"id": 322394,
					"title": "Contribution Team Deep Dive"
				},
				{
					"id": 322396,
					"title": "Select your Contribution Project"
				},
				{
					"id": 322362,
					"title": "Define and begin developing your contribution project"
				}
			]
		},
		{
			"title": "Wrap-up",
			"lessons": [
				{
					"id": 322384,
					"title": "Final Contribution Project Report"
				},
				{
					"id": 401007,
					"title": "Optional: Create your Website"
				},
				{
					"id": 322401,
					"title": "Get your certificate"
				},
				{
					"id": 322386,
					"title": "Complete the feedback form"
				}
			]
		}
	]
}
```

Create `bin/fixtures/learn-course-402893.json`:

```json
{
	"_comment": "The modules and lessons of one Learn WordPress course, read from the public, unauthenticated sensei-internal/v1/course-structure endpoint. bin/build-seeds.php matches the report form's headings against these lesson titles to record learn_lesson_id in the seed definitions, and bin/test-track-definitions.php checks the seeds against them. Refresh by reading the endpoint again.",
	"course_id": 402893,
	"course_url": "https://learn.wordpress.org/course/wordpress-credits-developer-track/",
	"read": "2026-09-11",
	"modules": [
		{
			"title": "Onboarding",
			"lessons": [
				{
					"id": 402934,
					"title": "Welcome and Essential Communication Guidelines"
				},
				{
					"id": 402894,
					"title": "Share your WordPress profile"
				},
				{
					"id": 402908,
					"title": "Join global Slack"
				},
				{
					"id": 402941,
					"title": "Differences between WordPress.com and WordPress.org"
				},
				{
					"id": 402896,
					"title": "Complete: Open source basics and WordPress"
				},
				{
					"id": 402898,
					"title": "Complete: How decisions are made in the WordPress project"
				},
				{
					"id": 402900,
					"title": "Complete: Community meeting etiquette"
				},
				{
					"id": 402902,
					"title": "Complete: Writing in the WordPress voice"
				},
				{
					"id": 402904,
					"title": "Complete: Basic principles of conflict resolution"
				},
				{
					"id": 402906,
					"title": "Complete your WordPress training: Developer track"
				},
				{
					"id": 402910,
					"title": "Create your personal website"
				},
				{
					"id": 402914,
					"title": "Reflection: Building Your Personal Website"
				}
			]
		},
		{
			"title": "Project",
			"lessons": [
				{
					"id": 402940,
					"title": "How to contribute to WordPress"
				},
				{
					"id": 402936,
					"title": "Contribution Team Deep Dive"
				},
				{
					"id": 403021,
					"title": "Practical: Patch Testing"
				},
				{
					"id": 402938,
					"title": "Immediate Contribution Opportunities"
				},
				{
					"id": 402912,
					"title": "Define and begin developing your contribution project"
				},
				{
					"id": 402916,
					"title": "Reflection: Choosing Your Team and Project"
				},
				{
					"id": 403098,
					"title": "Alumni Program: Connect with the community and plan your contribution beyond WP Credits"
				},
				{
					"id": 402930,
					"title": "Complete the first feedback form"
				},
				{
					"id": 402918,
					"title": "Reflection: Your First Contribution"
				},
				{
					"id": 402932,
					"title": "Leave Your Mid-Course Feedback"
				},
				{
					"id": 402920,
					"title": "Reflection: Halfway Check-In"
				},
				{
					"id": 402924,
					"title": "Participate at a WordPress Event (online or in person)"
				}
			]
		},
		{
			"title": "Wrap-up",
			"lessons": [
				{
					"id": 402926,
					"title": "Prepare and deliver a wrap-up report"
				},
				{
					"id": 402943,
					"title": "Get your certificate"
				},
				{
					"id": 402928,
					"title": "Complete the feedback form"
				}
			]
		}
	]
}
```

Create `bin/fixtures/learn-course-403425.json`:

```json
{
	"_comment": "The modules and lessons of one Learn WordPress course, read from the public, unauthenticated sensei-internal/v1/course-structure endpoint. bin/build-seeds.php matches the report form's headings against these lesson titles to record learn_lesson_id in the seed definitions, and bin/test-track-definitions.php checks the seeds against them. Refresh by reading the endpoint again.",
	"course_id": 403425,
	"course_url": "https://learn.wordpress.org/course/wordpress-credits-designer-track/",
	"read": "2026-09-11",
	"modules": [
		{
			"title": "Onboarding",
			"lessons": [
				{
					"id": 403435,
					"title": "Welcome and Essential Communication Guidelines"
				},
				{
					"id": 403437,
					"title": "Share your WordPress profile"
				},
				{
					"id": 403439,
					"title": "Join global Slack"
				},
				{
					"id": 403441,
					"title": "Differences between WordPress.com and WordPress.org"
				},
				{
					"id": 403443,
					"title": "Complete: Open source basics and WordPress"
				},
				{
					"id": 403445,
					"title": "Complete: How decisions are made in the WordPress project"
				},
				{
					"id": 403447,
					"title": "Complete: Community meeting etiquette"
				},
				{
					"id": 403449,
					"title": "Complete: Writing in the WordPress voice"
				},
				{
					"id": 403451,
					"title": "Complete: Basic principles of conflict resolution"
				},
				{
					"id": 403453,
					"title": "Complete: Beginner WordPress Designer Course"
				},
				{
					"id": 403455,
					"title": "From design skills to contribution"
				},
				{
					"id": 403457,
					"title": "Create your portfolio"
				},
				{
					"id": 403459,
					"title": "Reflection: Building Your Portfolio"
				}
			]
		},
		{
			"title": "Project",
			"lessons": [
				{
					"id": 403461,
					"title": "How to contribute to WordPress"
				},
				{
					"id": 403463,
					"title": "The WordPress Design Team Deep Dive"
				},
				{
					"id": 403465,
					"title": "Introduction to Figma for WordPress Design"
				},
				{
					"id": 403467,
					"title": "Design Contribution Pathways for Students"
				},
				{
					"id": 403469,
					"title": "Understand WordPress Design Principles"
				},
				{
					"id": 403471,
					"title": "Practical: Duplicate and Explore the WordPress Design Library"
				},
				{
					"id": 403473,
					"title": "Practical: Set Up a Local WordPress Environment for Design Testing"
				},
				{
					"id": 403475,
					"title": "Explore and practice WordPress design"
				},
				{
					"id": 403477,
					"title": "Practical: Change Your Site's Global Styles"
				},
				{
					"id": 403479,
					"title": "Practical: Customize with the Style Book"
				},
				{
					"id": 403481,
					"title": "Practical: Compose a Landing Page with Layout Blocks"
				},
				{
					"id": 403483,
					"title": "Practical: Apply Custom CSS in the Site Editor"
				},
				{
					"id": 403485,
					"title": "Contribute to a real project"
				},
				{
					"id": 403487,
					"title": "Practical: Create and Submit a Custom Block Pattern"
				},
				{
					"id": 403489,
					"title": "Practical: Test Your Site for Accessibility"
				},
				{
					"id": 403563,
					"title": "Define and begin developing your contribution project"
				},
				{
					"id": 403565,
					"title": "Reflection: Choosing Your Team and Project"
				},
				{
					"id": 403491,
					"title": "Alumni Program: Connect with the community and plan your contribution beyond WP Credits"
				},
				{
					"id": 403493,
					"title": "Complete the first feedback form"
				},
				{
					"id": 403495,
					"title": "Reflection: Your First Contribution"
				},
				{
					"id": 403497,
					"title": "Leave your mid-course feedback"
				},
				{
					"id": 403499,
					"title": "Reflection: Halfway Check-In"
				},
				{
					"id": 403501,
					"title": "Participate at a WordPress Event (online or in person)"
				}
			]
		},
		{
			"title": "Wrap-up",
			"lessons": [
				{
					"id": 403503,
					"title": "Prepare and deliver a wrap-up report"
				},
				{
					"id": 403505,
					"title": "Get your certificate"
				},
				{
					"id": 403507,
					"title": "Complete the feedback form"
				}
			]
		}
	]
}
```

and apply this change to `bin/fixtures/reports-table-fields.json`, which gives every column its type:

```diff
--- a/bin/fixtures/reports-table-fields.json
+++ b/bin/fixtures/reports-table-fields.json
@@ -1,5 +1,5 @@
 {
-	"_comment": "Field names of the Airtable 'Students Reports' table (tbljYkkVGbeoaWEtY), read from the base's metadata API. bin/test-report-form.php checks every field-name key the report form uses against this list, and bin/test-fixtures.php checks the counts, the ordering and the Designer Track's columns. Trailing spaces are real -- 'Company ' has one in the base. The twenty-one 'Practical: ...' columns carry the Designer Track's practical lessons and were read on 2026-09-07 (design spec of that date, section 1), which is how 52 fields became 73; three of their names are load-bearing and look like slips: 'Change Your Site’s Global Styles' uses a right single quotation mark (U+2019) and not an apostrophe, 'Duplicate & Explore WP Design Library' shortens the lesson title Learn spells out and ends '- link' and '- image' in lower case, and the base spells the local-environment choice 'MAAMP'. Types and choices are recorded for those columns only: the other 52 predate the read and their types were never needed. status_choices_confirmed is not the whole Status list either, only the two choices a read has named on this table; the fuller record of the shared vocabulary is the Students table fixture's 'Status'. `create_records()` and `update_records()` send no `typecast`, so a field or a choice spelled any other way is a 422 for the whole record. Refresh with a metadata read when the table changes.",
+	"_comment": "Field names of the Airtable 'Students Reports' table (tbljYkkVGbeoaWEtY), read from the base's metadata API. bin/test-report-form.php checks every field-name key the report form uses against this list, and bin/test-fixtures.php checks the counts, the ordering and the Designer Track's columns. Trailing spaces are real -- 'Company ' has one in the base. The twenty-one 'Practical: ...' columns carry the Designer Track's practical lessons and were read on 2026-09-07 (design spec of that date, section 1), which is how 52 fields became 73; three of their names are load-bearing and look like slips: 'Change Your Site’s Global Styles' uses a right single quotation mark (U+2019) and not an apostrophe, 'Duplicate & Explore WP Design Library' shortens the lesson title Learn spells out and ends '- link' and '- image' in lower case, and the base spells the local-environment choice 'MAAMP'. Types and choices are recorded for those columns only: the other 52 predate the read and their types were never needed. status_choices_confirmed is not the whole Status list either, only the two choices a read has named on this table; the fuller record of the shared vocabulary is the Students table fixture's 'Status'. `create_records()` and `update_records()` send no `typecast`, so a field or a choice spelled any other way is a 422 for the whole record. Refresh with a metadata read when the table changes. all_types (1.101.0) gives every field its Airtable type, read from the metadata API on 2026-09-11; bin/build-seeds.php records it on each question of the seed definitions, and bin/test-track-definitions.php checks it.",
 	"table": "tbljYkkVGbeoaWEtY",
 	"read": "2026-09-07",
 	"fields": [
@@ -110,5 +110,81 @@
 	"status_choices_confirmed": [
 		"Designer Track",
 		"Developer Track"
-	]
+	],
+	"all_types_read": "2026-09-11",
+	"all_types": {
+		"50h personal link": "formula",
+		"Advance WordPress User - final grade": "number",
+		"Alumni program: mentoring opt-in": "checkbox",
+		"Alumni program: personal email": "email",
+		"Basic principles of conflict resolution - final grade": "number",
+		"Beginner WordPress Designer": "number",
+		"Beginner WordPress Developer": "number",
+		"Beginner WordPress User - final grade": "number",
+		"Closing post URL": "url",
+		"Community meeting etiquette - final grade": "number",
+		"Company ": "multipleRecordLinks",
+		"Contributing beyond WP Credits": "multilineText",
+		"Contribution Project Summary": "multilineText",
+		"Dev Track ONLY personal link": "formula",
+		"Developer Basics: modules completed": "multilineText",
+		"Developer basics: Optional modules taken": "multilineText",
+		"Educational institution": "multipleRecordLinks",
+		"Email": "email",
+		"Final Contribution Project Report": "richText",
+		"Hours": "number",
+		"How decisions are made in the WordPress project - final grade": "number",
+		"Intermediate Theme Developer": "number",
+		"Intermediate WordPress User - final grade": "number",
+		"Internship End Date": "date",
+		"Internship Start Date": "date",
+		"Lessons": "multipleRecordLinks",
+		"Main Contribution Team": "multipleRecordLinks",
+		"Mentor": "multipleRecordLinks",
+		"Mentor's email": "multipleLookupValues",
+		"Name": "singleLineText",
+		"Open source basics and WordPress - final grade": "number",
+		"Optional: Additional Contribution Project Summary": "multilineText",
+		"Patch Testing: Trac ticket comments": "multilineText",
+		"Permanent contact email": "email",
+		"Personal Website URL": "url",
+		"Personal link": "formula",
+		"Post 5 (DELETED)": "url",
+		"Post 6 (DELETED)": "url",
+		"Post 7 (DELETED)": "url",
+		"Post 8 (DELETED)": "url",
+		"Post Reflection: Building Your Personal Website": "url",
+		"Post Reflection: Choosing Your Team and Project": "url",
+		"Post Reflection: Halfway Check-In": "url",
+		"Post Reflection: Your First Contribution": "url",
+		"Practical: Apply Custom CSS in the Site Editor - CSS": "multilineText",
+		"Practical: Apply Custom CSS in the Site Editor - Notes": "multilineText",
+		"Practical: Apply Custom CSS in the Site Editor - Screenshot": "multipleAttachments",
+		"Practical: Change Your Site’s Global Styles - After Screenshot": "multipleAttachments",
+		"Practical: Change Your Site’s Global Styles - Before Screenshot": "multipleAttachments",
+		"Practical: Change Your Site’s Global Styles - Notes": "multilineText",
+		"Practical: Duplicate & Explore WP Design Library - Reflection": "multilineText",
+		"Practical: Duplicate & Explore WP Design Library - image": "multipleAttachments",
+		"Practical: Duplicate & Explore WP Design Library - link": "url",
+		"Practical: Landing Page with Layout Blocks - Note": "multilineText",
+		"Practical: Landing Page with Layout Blocks - Screenshot": "multipleAttachments",
+		"Practical: Local WordPress Environment for Design Testing - Screenshot": "multipleAttachments",
+		"Practical: Local WordPress Environment for Design Testing - Tool used": "singleSelect",
+		"Practical: Style Book - Notes": "multilineText",
+		"Practical: Style Book - Screenshot": "multipleAttachments",
+		"Practical: Submit a Custom Block Pattern - Link": "url",
+		"Practical: Submit a Custom Block Pattern - Screenshot": "multipleAttachments",
+		"Practical: Test Your Site for Accessibility - Part 1 - Note": "multilineText",
+		"Practical: Test Your Site for Accessibility - Part 1 - Screenshot": "multipleAttachments",
+		"Practical: Test Your Site for Accessibility - Part 2 - Note": "multilineText",
+		"Practical: Test Your Site for Accessibility - Part 2 - Screenshot": "multipleAttachments",
+		"Slack Name": "singleLineText",
+		"Slack/GitHub/Blog WordPress Community meetings/discussions": "multilineText",
+		"Status": "singleSelect",
+		"Students": "multipleRecordLinks",
+		"WP event participation URL": "url",
+		"WordPress Profile": "url",
+		"Wrap-up WP TV (DELETED)": "url",
+		"Writing in the WordPress voice - final grade": "number"
+	}
 }
```

- [ ] **Step 4: Write how a seed is built, and the command that writes the four.** Create `bin/seed-definitions.php`:

```php
<?php
/**
 * Build the four seed definitions from the hand-written tracks (Track Builder, phase T2a).
 *
 * Shared by bin/build-seeds.php, which writes them to includes/tracks/seeds/, and
 * bin/test-track-definitions.php, which holds the files to them. The caller loads `WPCPM_Program`,
 * `WPCPM_Student_Report_Form` and `WPCPM_Track_Definition` first.
 *
 * Everything is read from the repository: the forms from `builtin_fields()`, each column's
 * Airtable type from bin/fixtures/reports-table-fields.json (`all_types`, read from the base's
 * metadata API) and the lessons from bin/fixtures/learn-course-<id>.json. Nothing reaches the
 * network, so every build gives the same bytes.
 */

/**
 * The four built-in tracks: key => the status that holds it, and the palette hue its chip is
 * painted in by the stylesheets.
 *
 * @return array
 */
function wpcpm_seed_tracks() {
	return array(
		'150h'   => array(
			'status' => WPCPM_Program::STATUS_150H,
			'hue'    => 'blue',
		),
		'50h'    => array(
			'status' => WPCPM_Program::STATUS_50H,
			'hue'    => 'purple',
		),
		'dev'    => array(
			'status' => WPCPM_Program::STATUS_DEV,
			'hue'    => 'teal',
		),
		'design' => array(
			'status' => WPCPM_Program::STATUS_DESIGN,
			'hue'    => 'pink',
		),
	);
}

/**
 * Where a track's seed is kept.
 *
 * @param string $key Track key.
 * @return string
 */
function wpcpm_seed_path( $key ) {
	return dirname( __DIR__ ) . '/includes/tracks/seeds/' . $key . '.json';
}

/**
 * A seed as it is stored: pretty-printed, so a change reads as a diff, and ending in a newline.
 *
 * @param array $definition Track definition.
 * @return string
 */
function wpcpm_seed_json( array $definition ) {
	return json_encode( $definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
}

/**
 * One track's seed, built from the repository alone.
 *
 * @param string $key Track key.
 * @return array
 */
function wpcpm_seed_build( $key ) {
	$status  = wpcpm_seed_tracks()[ $key ]['status'];
	$reports = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/reports-table-fields.json' ), true );
	$course  = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/learn-course-' . WPCPM_Program::course_ids()[ $status ] . '.json' ), true );

	return wpcpm_seed_definition( $key, (array) $reports['all_types'], (array) $course );
}

/**
 * One track's definition: its PHP form, with what the form never shows recorded beside each
 * question - the column's Airtable type, the Learn lesson it reports on, and why its name looks
 * the way it does.
 *
 * @param string $key    Track key.
 * @param array  $types  Column => Airtable type.
 * @param array  $course The track's Learn course: `modules`, each with `title` and `lessons`.
 * @return array
 */
function wpcpm_seed_definition( $key, array $types, array $course ) {
	$track     = wpcpm_seed_tracks()[ $key ];
	$status    = $track['status'];
	$notes     = wpcpm_seed_why();
	$notes     = array_merge( $notes['*'], isset( $notes[ $key ] ) ? $notes[ $key ] : array() );
	$hours     = WPCPM_Program::hours_targets();
	$questions = array();

	foreach ( WPCPM_Student_Report_Form::builtin_fields( $key ) as $column => $spec ) {
		if ( isset( $types[ $column ] ) ) {
			$spec['airtable_type'] = $types[ $column ];
		}

		$lesson = wpcpm_seed_lesson( $spec, $course );

		if ( $lesson > 0 ) {
			$spec['learn_lesson_id'] = $lesson;
		}

		if ( isset( $notes[ $column ] ) ) {
			$spec['why'] = $notes[ $column ];
		}

		$questions[ $column ] = $spec;
	}

	return WPCPM_Track_Definition::normalize(
		array(
			'schema_version'  => WPCPM_Track_Definition::SCHEMA_VERSION,
			'status'          => $status,
			'key'             => $key,
			'label'           => WPCPM_Program::labels()[ $status ],
			'course_url'      => WPCPM_Program::courses()[ $status ],
			'learn_course_id' => WPCPM_Program::course_ids()[ $status ],
			'hours_target'    => $hours[ $status ],
			'hue'             => $track['hue'],
			'questions'       => $questions,
		)
	);
}

/**
 * The Learn lesson a question reports on: the lesson its heading names, once case and
 * apostrophes are folded (the design's 2.6).
 *
 * The subgroup is tried before the lead, being the nearer heading, and the lessons of the
 * question's own module before the others.
 *
 * @param array $spec   The question.
 * @param array $course The track's Learn course.
 * @return int The lesson's post ID, or 0 when no heading names a lesson.
 */
function wpcpm_seed_lesson( array $spec, array $course ) {
	$module_of = array(
		'onboarding' => 'Onboarding',
		'project'    => 'Project',
		'wrapup'     => 'Wrap-up',
	);
	$own       = isset( $spec['group'], $module_of[ $spec['group'] ] ) ? $module_of[ $spec['group'] ] : '';
	$modules   = isset( $course['modules'] ) ? (array) $course['modules'] : array();
	$ordered   = array_merge(
		array_filter(
			$modules,
			static function ( $module ) use ( $own ) {
				return $module['title'] === $own;
			}
		),
		array_filter(
			$modules,
			static function ( $module ) use ( $own ) {
				return $module['title'] !== $own;
			}
		)
	);

	foreach ( array( 'subgroup', 'lead' ) as $heading ) {
		if ( empty( $spec[ $heading ] ) ) {
			continue;
		}

		foreach ( $ordered as $module ) {
			foreach ( (array) $module['lessons'] as $lesson ) {
				if ( wpcpm_seed_fold( $lesson['title'] ) === wpcpm_seed_fold( $spec[ $heading ] ) ) {
					return (int) $lesson['id'];
				}
			}
		}
	}

	return 0;
}

/**
 * A heading or a lesson title as the match sees it: the typographic apostrophe read as the
 * plain one, one space for any run of them, trimmed, lower case.
 *
 * @param string $text Text.
 * @return string
 */
function wpcpm_seed_fold( $text ) {
	$text = str_replace( array( "\u{2019}", "\u{2018}" ), "'", (string) $text );

	return mb_strtolower( trim( (string) preg_replace( '/\s+/', ' ', $text ) ), 'UTF-8' );
}

/**
 * Why a column's name looks like a slip, by track, and under `*` for every track that asks it.
 *
 * Written by hand for the developer who reads a seed and would otherwise correct the name (the
 * design's section 8). No student sees these: compiling strips `why`.
 *
 * @return array<string, array<string, string>>
 */
function wpcpm_seed_why() {
	$site      = "The base spells Site\u{2019}s with the typographic apostrophe (U+2019) where Learn writes a plain one. The name is the base's column: a plain apostrophe here names a column that does not exist.";
	$library   = 'The base shortens the lesson Duplicate and Explore the WordPress Design Library to this name and ends its link and image columns in lower case. The names are the base\'s columns, not slips to correct.';
	$portfolio = 'On this track the lesson is Create your portfolio, so the column the other tracks ask as a personal website is asked as a portfolio: one site, one column, whatever a course calls it.';
	$meetings  = 'On this track the question is asked inside the Alumni Program lesson and heads that run, rather than sitting beside the team list as on the 150-hour course. It keeps its column, so its answers stay where every track keeps them.';

	return array(
		'*'      => array(
			'Advance WordPress User - final grade' => 'The column says Advance and the label says Advanced: the base\'s spelling is the column\'s name, and the label is what a student reads.',
		),
		'dev'    => array(
			'Slack/GitHub/Blog WordPress Community meetings/discussions' => $meetings,
		),
		'design' => array(
			'Personal Website URL'                            => $portfolio,
			'Post Reflection: Building Your Personal Website' => $portfolio,
			'Practical: Duplicate & Explore WP Design Library - Reflection' => $library,
			'Practical: Duplicate & Explore WP Design Library - link' => $library,
			'Practical: Duplicate & Explore WP Design Library - image' => $library,
			'Practical: Local WordPress Environment for Design Testing - Tool used' => 'The choices are spelled as the base spells them, MAAMP included: a choice spelled any other way is one the column does not have, and Airtable refuses the whole save.',
			"Practical: Change Your Site\u{2019}s Global Styles - Notes" => $site,
			"Practical: Change Your Site\u{2019}s Global Styles - Before Screenshot" => $site,
			"Practical: Change Your Site\u{2019}s Global Styles - After Screenshot" => $site,
			'Slack/GitHub/Blog WordPress Community meetings/discussions' => $meetings,
		),
	);
}
```

Create `bin/build-seeds.php`:

```php
<?php
/**
 * Write the four seed definitions to includes/tracks/seeds/ (Track Builder, phase T2a).
 *
 * Offline, and the same bytes every run: bin/seed-definitions.php says what each seed is built
 * from. Run it after a hand-written form changes or a fixture is read again, and commit what it
 * writes; bin/test-track-definitions.php fails until the seeds and the PHP agree.
 *
 * Run from the plugin root:  php bin/build-seeds.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

function __( $s, $d = null ) { return $s; }
function apply_filters( $tag, $value ) { return $value; }

require_once __DIR__ . '/../includes/class-wpcpm-program.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-student-report-form.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-palette.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';
require_once __DIR__ . '/seed-definitions.php';

$folder = dirname( wpcpm_seed_path( '150h' ) );

if ( ! is_dir( $folder ) ) {
	mkdir( $folder, 0755, true );
}

foreach ( array_keys( wpcpm_seed_tracks() ) as $key ) {
	$definition = wpcpm_seed_build( $key );
	$lessons    = count( array_filter( array_column( $definition['questions'], 'learn_lesson_id' ) ) );

	file_put_contents( wpcpm_seed_path( $key ), wpcpm_seed_json( $definition ) );
	printf( "%-6s %2d questions, %2d reporting on a Learn lesson: includes/tracks/seeds/%s.json\n", $key, count( $definition['questions'] ), $lessons, $key );
}
```

- [ ] **Step 5: Run the checks again, and see the missing seeds fail.**

```bash
php bin/test-fixtures.php | tail -n 1
php bin/test-track-definitions.php | grep -E '^FAIL|checks\)'
```

Expected: `ALL PASS`; then `FAIL is a stored definition` four times, the two counts at the end failing with them, and `6 FAILED (7 checks)`.

- [ ] **Step 6: Write the seeds.** `php bin/build-seeds.php`. Expected:

```text
150h   24 questions,  1 reporting on a Learn lesson: includes/tracks/seeds/150h.json
50h    11 questions,  0 reporting on a Learn lesson: includes/tracks/seeds/50h.json
dev    31 questions,  1 reporting on a Learn lesson: includes/tracks/seeds/dev.json
design 49 questions, 11 reporting on a Learn lesson: includes/tracks/seeds/design.json
```

and `shasum -a 1 includes/tracks/seeds/*.json` prints these, the scratch run's bytes:

```text
22d569c1baf3bdb0473cc983c90c3a055d41b230  includes/tracks/seeds/150h.json
d28858bdb5e39ad27d0fc75d6d5e2365b5d0c162  includes/tracks/seeds/50h.json
2f0257d2bcc1c232da7c2cc71abd7d950950627a  includes/tracks/seeds/design.json
f9f5ae0cf5e0d9384b23bf4e82e67b91804c689c  includes/tracks/seeds/dev.json
```

- [ ] **Step 7: Run the checks.** `php bin/test-track-definitions.php | tail -n 1`. Expected: `ALL PASS (35 checks)`. Each seed is what the command builds, its form is its hand-written one byte for byte, its map is the program map's, it is stored normalized, every rule accepts it with the lock a built-in track has, every question records its column's type as the base has it, and every lesson it names belongs to its own course.

- [ ] **Step 8: Run everything.** Expected: silent, clean, `85 warnings, no errors.` `php bin/check-spelling.php` now counts 16 fixture and tooling files.

- [ ] **Step 9: Commit.**

```bash
git add bin/test-track-definitions.php bin/seed-definitions.php bin/build-seeds.php bin/fixtures bin/test-fixtures.php includes/tracks/seeds
git commit -m "Track Builder T2a: the four seed definitions, built from the hand-written forms and held to them byte for byte"
```

---

### Task 5: Seeding the site, and the switch

**Files:**
- Modify: `includes/tracks/class-wpcpm-track-store.php`, `includes/tracks/class-wpcpm-tracks.php`, `includes/class-wpcpm-cli.php`
- Test: `bin/test-track-store.php`

**Interfaces:**
- Consumes: the seed files (Task 4), `builtin_fields()` (Task 3), `publish()`, `compile()` and `builtin_key()` (Task 2).
- Produces, on `WPCPM_Track_Store`: `META_SWITCHED`, `OPT_SEEDED` (`wpcpm_tracks_seeded`), `SEED_VERSION` (1) and `BUILTIN_KEYS`; `maybe_seed()`, hooked on `init` at 20; `seed()`, track key => the new post ID, 0 for a status already held, or a `WP_Error`; `seeds()`, the committed seed definitions by key; `equivalence( $post_id )`, what differs from the PHP, empty when nothing does: `not_published` or `not_builtin` alone, or any of `form`, `label`, `course`, `course_id` and `hours`; `switch_to_definition( $post_id, $user_id = 0 )` and `switch_to_builtin( $post_id, $user_id = 0 )`, the post ID or a `WP_Error` coded `wpcpm_track_not_builtin`, `wpcpm_track_not_equivalent` (data `differences`), `wpcpm_track_not_switched`, `wpcpm_track_no_php` or `wpcpm_track_edited`. On `WPCPM_Tracks`: `builtin_row( $status )`, the PHP's `label`, `course_url`, `course_id` and `hours` for a status. On `WPCPM_CLI`: `seed_tracks()`, which WP-CLI runs as `wp wpcredits seed-tracks`.

- [ ] **Step 1: Write the failing checks.** Apply this change:

```diff
--- a/bin/test-track-store.php
+++ b/bin/test-track-store.php
@@ -116,6 +116,21 @@ function get_post_meta( $id, $key = '', $single = false ) { return $GLOBALS['pme
 function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = wp_unslash( $value ); return true; } // Unslashed, as WordPress does.
 function apply_filters( $tag, $value ) { return $value; } // Nothing hooked: the program map as its PHP describes it.
 function get_current_user_id() { return 7; }
+function add_option( $k, $v = '', $deprecated = '', $autoload = null ) {
+	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) {
+		return false;
+	}
+	$GLOBALS['opts'][ $k ]     = $v;
+	$GLOBALS['autoload'][ $k ] = $autoload;
+	return true;
+}
+function delete_post_meta( $id, $key ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ); return true; }
+
+/** The hand-written forms the switch compares a definition with: whatever a check sets. */
+class WPCPM_Student_Report_Form {
+	public static $forms = array();
+	public static function builtin_fields( $track ) { return self::$forms[ $track ] ?? array(); }
+}
 function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
 
 /** The settings: the list the status rule reads, and the one write publishing makes. */
@@ -183,7 +198,7 @@ function revision( $post_id, $which ) {
 echo "=== Registration ===\n";
 
 WPCPM_Track_Store::init();
-ck( 'init() registers the type, then the meta, both on init', array_map( function ( $a ) { return array( $a[0], $a[1][1] ); }, $GLOBALS['actions'] ), array( array( 'init', 'register_post_type' ), array( 'init', 'register_meta' ) ) );
+ck( 'init() registers the type, then the meta, both on init, and the seeds after them', array_map( function ( $a ) { return array( $a[0], $a[1][1], $a[2] ); }, $GLOBALS['actions'] ), array( array( 'init', 'register_post_type', 10 ), array( 'init', 'register_meta', 10 ), array( 'init', 'maybe_seed', 20 ) ) );
 
 WPCPM_Track_Store::register_meta();
 ck( 'registered before its type, the meta would lose its revisions: WordPress refuses them', array( $GLOBALS['wrong'], $GLOBALS['meta_args']['wpcpm_track']['_wpcpm_track_definition']['revisions_enabled'] ), array( array( '_wpcpm_track_definition' ), false ) );
@@ -365,6 +380,87 @@ $entries = WPCPM_Track_Store::log_entries( $beta );
 ck( 'naming the person the screen says published it', end( $entries )['by'], 42 );
 ck( 'and a timestamp', is_int( end( $entries )['at'] ) && end( $entries )['at'] > 0, true );
 
+echo "\n=== The four built-in tracks, seeded ===\n";
+
+$GLOBALS['posts'] = array();
+$GLOBALS['pmeta'] = array();
+$GLOBALS['opts']  = array();
+WPCPM_Tracks::flush();
+
+$seeded = WPCPM_Track_Store::seed();
+ck( 'seed() creates one track for each seed the plugin ships', array_keys( $seeded ), array( '150h', '50h', 'dev', 'design' ) );
+$states = array();
+foreach ( $seeded as $key => $post_id ) {
+	$states[ $key ] = array( WPCPM_Track_Store::state( $post_id ), get_post_meta( $post_id, WPCPM_Track_Store::META_SOURCE, true ), WPCPM_Track_Store::get( $post_id )['status'] );
+}
+ck( 'each a draft marked built-in, holding its seed', $states, array( '150h' => array( 'draft', 'builtin', 'In Sensei' ), '50h' => array( 'draft', 'builtin', 'In Sensei 50h' ), 'dev' => array( 'draft', 'builtin', 'Developer Track' ), 'design' => array( 'draft', 'builtin', 'Designer Track' ) ) );
+ck( 'the seed as shipped, byte for byte', WPCPM_Track_Store::get( $seeded['design'] ), WPCPM_Track_Store::seeds()['design'] );
+ck( 'seeding again creates nothing: each status is already held', array( WPCPM_Track_Store::seed(), count( $GLOBALS['posts'] ) ), array( array( '150h' => 0, '50h' => 0, 'dev' => 0, 'design' => 0 ), 4 ) );
+
+$GLOBALS['posts'] = array();
+$GLOBALS['pmeta'] = array();
+$GLOBALS['opts']  = array();
+WPCPM_Tracks::flush();
+WPCPM_Track_Store::maybe_seed();
+ck( 'a site seeds itself once, the first time it runs this version', array( count( $GLOBALS['posts'] ), get_option( WPCPM_Track_Store::OPT_SEEDED ) ), array( 4, 1 ) );
+ck( 'and writes the index, empty and autoloaded, so no request asks for an option that is not there', array( get_option( WPCPM_Tracks::OPT_TRACKS, 'missing' ), $GLOBALS['autoload'][ WPCPM_Tracks::OPT_TRACKS ] ), array( array(), true ) );
+WPCPM_Track_Store::maybe_seed();
+ck( 'and never again', count( $GLOBALS['posts'] ), 4 );
+
+echo "\n=== The switch between a built-in track's PHP and its definition ===\n";
+
+$GLOBALS['posts'] = array();
+$GLOBALS['pmeta'] = array();
+$GLOBALS['opts']  = array();
+WPCPM_Tracks::flush();
+
+$designer = array(
+	'schema_version'  => 1,
+	'status'          => 'Designer Track',
+	'key'             => 'design',
+	'label'           => 'Designer Track',
+	'course_url'      => WPCPM_Program::course_url( 'Designer Track' ),
+	'learn_course_id' => WPCPM_Program::course_id( 'Designer Track' ),
+	'hours_target'    => 150,
+	'hue'             => 'pink',
+	'questions'       => track( 'Designer Track', 'design' )['questions'],
+);
+$form = WPCPM_Track_Definition::compile_fields( WPCPM_Track_Definition::normalize( $designer ) );
+$d    = WPCPM_Track_Store::create( $designer );
+update_post_meta( $d, WPCPM_Track_Store::META_SOURCE, 'builtin' );
+WPCPM_Track_Store::publish( $d );
+
+WPCPM_Student_Report_Form::$forms['design'] = array( 'Something else' => array( 'label' => 'x', 'type' => 'text', 'group' => 'project' ) );
+$refused = WPCPM_Track_Store::switch_to_definition( $d );
+ck( 'a built-in track cannot switch while its definition differs from its PHP', array( $refused->get_error_code(), $refused->get_error_data()['differences'] ), array( 'wpcpm_track_not_equivalent', array( 'form' ) ) );
+ck( 'and keeps running from its PHP', WPCPM_Tracks::rows()['Designer Track']['source'], 'builtin' );
+
+WPCPM_Student_Report_Form::$forms['design'] = $form;
+ck( 'identical, the two are equivalent', WPCPM_Track_Store::equivalence( $d ), array() );
+ck( 'and the switch hands back the track', WPCPM_Track_Store::switch_to_definition( $d ), $d );
+ck( 'which now runs from its definition', array( WPCPM_Tracks::rows()['Designer Track']['source'], get_post_meta( $d, WPCPM_Track_Store::META_SOURCE, true ) ), array( 'definition', '' ) );
+ck( 'switching back is open while nothing has been edited', WPCPM_Track_Store::switch_to_builtin( $d ), $d );
+ck( 'and puts its PHP back in charge', WPCPM_Tracks::rows()['Designer Track']['source'], 'builtin' );
+
+WPCPM_Track_Store::switch_to_definition( $d );
+$edited          = WPCPM_Track_Store::get( $d );
+$edited['label'] = 'Designer Track, edited';
+WPCPM_Track_Store::save( $d, $edited );
+ck( 'once the definition is edited, the way back is closed: it would drop the edit', WPCPM_Track_Store::switch_to_builtin( $d )->get_error_code(), 'wpcpm_track_edited' );
+ck( 'the log records each switch', array_column( WPCPM_Track_Store::log_entries( $d ), 'did' ), array( 'publish', 'switch_definition', 'switch_builtin', 'switch_definition' ) );
+
+$other = WPCPM_Track_Store::create( track( 'Marketing Track', 'marketing' ) );
+WPCPM_Track_Store::publish( $other );
+ck( 'a track that was never built in cannot switch', WPCPM_Track_Store::switch_to_definition( $other )->get_error_code(), 'wpcpm_track_not_builtin' );
+ck( 'nor switch back', WPCPM_Track_Store::switch_to_builtin( $other )->get_error_code(), 'wpcpm_track_not_switched' );
+ck( 'and is equivalent to no PHP', WPCPM_Track_Store::equivalence( $other ), array( 'not_builtin' ) );
+
+$drift = WPCPM_Track_Store::create( array( 'hours_target' => 120, 'label' => 'Designer Track, renamed' ) + $designer );
+update_post_meta( $drift, WPCPM_Track_Store::META_SOURCE, 'builtin' );
+update_post_meta( $drift, WPCPM_Track_Store::META_PUBLISHED, wp_slash( WPCPM_Track_Definition::encode( WPCPM_Track_Store::get( $drift ) ) ) );
+wp_update_post( array( 'ID' => $drift, 'post_status' => 'publish' ) );
+ck( 'equivalence names each way a definition differs from its PHP', WPCPM_Track_Store::equivalence( $drift ), array( 'label', 'hours' ) );
+
 echo "\n=== delete_all(), for uninstall ===\n";
 
 $trashed = WPCPM_Track_Store::create( track( 'Trashed Track', 'trashed' ) );
```

- [ ] **Step 2: Run them, and see them fail.** `php bin/test-track-store.php 2>&1 | grep -E '^FAIL|Fatal error' | head -n 2`. Expected: `FAIL init() registers the type, then the meta, both on init, and the seeds after them`, then `Call to undefined method WPCPM_Track_Store::seed()`.

- [ ] **Step 3: Write the code.** Apply these three changes:

```diff
--- a/includes/tracks/class-wpcpm-track-store.php
+++ b/includes/tracks/class-wpcpm-track-store.php
@@ -62,16 +62,36 @@ final class WPCPM_Track_Store {
 	 */
 	const OPT_SKIPPED = 'wpcpm_tracks_skipped';
 
+	/**
+	 * The saved definition's fingerprint when a built-in track switched to its definition.
+	 *
+	 * The way back is open only while the definition is unchanged since the switch (the design's
+	 * decision 3.5): going back after an edit would put the PHP in front of students and quietly
+	 * drop what the edit published.
+	 */
+	const META_SWITCHED = '_wpcpm_track_switched';
+
+	/** The seed version this site's Track Builder started from, set once. Autoloaded: read every request. */
+	const OPT_SEEDED = 'wpcpm_tracks_seeded';
+
+	/** The version of the seeds in `includes/tracks/seeds/`, which `OPT_SEEDED` records. */
+	const SEED_VERSION = 1;
+
+	/** The four built-in tracks' keys, in the program's order: one seed file each. */
+	const BUILTIN_KEYS = array( '150h', '50h', 'dev', 'design' );
+
 	/**
 	 * Register the type and its meta.
 	 *
 	 * The type before the meta, and that order is load-bearing: WordPress refuses
 	 * `revisions_enabled`, with a notice, for a type that does not support revisions yet, and
-	 * the definition would then be the one thing a revision does not keep.
+	 * the definition would then be the one thing a revision does not keep. The seeds come after
+	 * both, at 20, once (`maybe_seed()`).
 	 */
 	public static function init() {
 		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
 		add_action( 'init', array( __CLASS__, 'register_meta' ) );
+		add_action( 'init', array( __CLASS__, 'maybe_seed' ), 20 );
 	}
 
 	/**
@@ -539,14 +559,211 @@ final class WPCPM_Track_Store {
 	}
 
 	/**
-	 * Delete every track, its revisions and the options compiled from it. Called on uninstall.
+	 * Seed the four built-in tracks once, the first time a site runs this version.
 	 *
-	 * Every status, trash included, which `any` would leave behind. A form option is deleted for
-	 * the key of every track post as well as for every key the index names, so a form written by
-	 * a compile that never finished goes too.
+	 * The flag is claimed with `add_option()`, which only one request can win, so two requests
+	 * arriving together cannot seed twice. The compile that follows writes the index even when
+	 * nothing is published, so every later request finds `wpcpm_tracks` among the autoloaded
+	 * options rather than asking the database for an option that does not exist yet.
 	 */
-	public static function delete_all() {
-		$ids = get_posts(
+	public static function maybe_seed() {
+		if ( get_option( self::OPT_SEEDED ) || ! add_option( self::OPT_SEEDED, self::SEED_VERSION, '', true ) ) {
+			return;
+		}
+
+		self::seed();
+		self::compile();
+	}
+
+	/**
+	 * Create the four built-in tracks' definitions from the seeds the plugin ships.
+	 *
+	 * Each is a draft, marked built-in, so its PHP keeps running it until a Program Administrator
+	 * publishes it and switches it (the design's decision 3.5). A seed whose status a track already
+	 * holds is passed over, so seeding twice creates nothing the second time.
+	 *
+	 * @return array Track key => the new post's ID, 0 when a track already holds its status, or a
+	 *               WP_Error when WordPress refused to create it.
+	 */
+	public static function seed() {
+		$held = array();
+
+		foreach ( self::all_ids() as $post_id ) {
+			$definition = self::get( $post_id );
+
+			if ( is_array( $definition ) && isset( $definition['status'] ) ) {
+				$held[] = (string) $definition['status'];
+			}
+		}
+
+		$created = array();
+
+		foreach ( self::seeds() as $key => $definition ) {
+			if ( in_array( (string) $definition['status'], $held, true ) ) {
+				$created[ $key ] = 0;
+				continue;
+			}
+
+			$post_id = self::create( $definition );
+
+			if ( ! is_wp_error( $post_id ) ) {
+				update_post_meta( $post_id, self::META_SOURCE, 'builtin' );
+			}
+
+			$created[ $key ] = $post_id;
+		}
+
+		return $created;
+	}
+
+	/**
+	 * The seed definitions the plugin ships, by track key.
+	 *
+	 * Written by bin/build-seeds.php from the hand-written forms, and held to them byte for byte by
+	 * bin/test-track-definitions.php.
+	 *
+	 * @return array<string, array>
+	 */
+	public static function seeds() {
+		$seeds = array();
+
+		foreach ( self::BUILTIN_KEYS as $key ) {
+			$file = __DIR__ . '/seeds/' . $key . '.json';
+			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A file the plugin ships, read from its own folder.
+			$definition = is_readable( $file ) ? WPCPM_Track_Definition::decode( (string) file_get_contents( $file ) ) : null;
+
+			if ( is_array( $definition ) ) {
+				$seeds[ $key ] = $definition;
+			}
+		}
+
+		return $seeds;
+	}
+
+	/**
+	 * How a built-in track's published definition differs from its PHP: empty when it does not.
+	 *
+	 * The switch waits on this (the design's decision 3.5). The form must be `builtin_fields()`
+	 * byte for byte, and the name, course and hours the program map's, read with no compiled track
+	 * in it, so the switch changes nothing a student sees.
+	 *
+	 * @param int $post_id The track.
+	 * @return string[] `not_published` or `not_builtin` alone, or any of `form`, `label`, `course`,
+	 *                  `course_id` and `hours`.
+	 */
+	public static function equivalence( $post_id ) {
+		$post = self::track_post( $post_id );
+		$copy = self::published( $post_id );
+
+		if ( null === $post || 'publish' !== $post->post_status || ! is_array( $copy ) ) {
+			return array( 'not_published' );
+		}
+
+		$status = isset( $copy['status'] ) ? (string) $copy['status'] : '';
+		$key    = WPCPM_Tracks::builtin_key( $status );
+
+		if ( '' === $key || ! isset( $copy['key'] ) || $key !== $copy['key'] ) {
+			return array( 'not_builtin' );
+		}
+
+		$php       = WPCPM_Tracks::builtin_row( $status );
+		$mine      = array(
+			'label'     => isset( $copy['label'] ) ? (string) $copy['label'] : '',
+			'course'    => isset( $copy['course_url'] ) ? (string) $copy['course_url'] : '',
+			'course_id' => isset( $copy['learn_course_id'] ) ? (int) $copy['learn_course_id'] : 0,
+			'hours'     => isset( $copy['hours_target'] ) ? (int) $copy['hours_target'] : null,
+		);
+		$php_field = array(
+			'label'     => 'label',
+			'course'    => 'course_url',
+			'course_id' => 'course_id',
+			'hours'     => 'hours',
+		);
+
+		$differences = WPCPM_Track_Definition::compile_fields( $copy ) === WPCPM_Student_Report_Form::builtin_fields( $key ) ? array() : array( 'form' );
+
+		foreach ( $php_field as $what => $field ) {
+			if ( $mine[ $what ] !== $php[ $field ] ) {
+				$differences[] = $what;
+			}
+		}
+
+		return $differences;
+	}
+
+	/**
+	 * Run a built-in track from its definition instead of its PHP (the design's decision 3.5).
+	 *
+	 * Only while the two are identical, which is what makes the switch invisible to students; the
+	 * definition's fingerprint is kept, so the way back stays open until the definition is edited.
+	 *
+	 * @param int $post_id The track.
+	 * @param int $user_id Who switched it, for the log; 0 for the current user.
+	 * @return int|WP_Error The post ID, or why it was not switched.
+	 */
+	public static function switch_to_definition( $post_id, $user_id = 0 ) {
+		$post_id = (int) $post_id;
+
+		if ( 'builtin' !== get_post_meta( $post_id, self::META_SOURCE, true ) ) {
+			return new WP_Error( 'wpcpm_track_not_builtin', __( 'Only a built-in track its PHP still runs can switch to its definition.', 'wpcredits-program-manager' ) );
+		}
+
+		$differences = self::equivalence( $post_id );
+
+		if ( array() !== $differences ) {
+			return new WP_Error( 'wpcpm_track_not_equivalent', __( 'The definition is not identical to the track as its PHP runs it, so switching would change what students see.', 'wpcredits-program-manager' ), array( 'differences' => $differences ) );
+		}
+
+		update_post_meta( $post_id, self::META_SWITCHED, md5( (string) get_post_meta( $post_id, self::META_DEFINITION, true ) ) );
+		delete_post_meta( $post_id, self::META_SOURCE );
+		self::compile();
+		self::log( $post_id, 'switch_definition', $user_id );
+
+		return $post_id;
+	}
+
+	/**
+	 * Run a switched track from its PHP again (the design's decision 3.5).
+	 *
+	 * Only while the PHP exists and the definition is as it was when the track switched: after an
+	 * edit, going back would put the PHP in front of students and quietly drop what was published.
+	 *
+	 * @param int $post_id The track.
+	 * @param int $user_id Who switched it back, for the log; 0 for the current user.
+	 * @return int|WP_Error The post ID, or why it was not switched back.
+	 */
+	public static function switch_to_builtin( $post_id, $user_id = 0 ) {
+		$post_id     = (int) $post_id;
+		$fingerprint = (string) get_post_meta( $post_id, self::META_SWITCHED, true );
+		$copy        = self::published( $post_id );
+
+		if ( '' === $fingerprint || ! is_array( $copy ) ) {
+			return new WP_Error( 'wpcpm_track_not_switched', __( 'That track does not run from its definition.', 'wpcredits-program-manager' ) );
+		}
+
+		if ( ! isset( $copy['status'], $copy['key'] ) || WPCPM_Tracks::builtin_key( (string) $copy['status'] ) !== $copy['key'] ) {
+			return new WP_Error( 'wpcpm_track_no_php', __( 'The hand-written track this one came from has been removed, so there is nothing to go back to.', 'wpcredits-program-manager' ) );
+		}
+
+		if ( md5( (string) get_post_meta( $post_id, self::META_DEFINITION, true ) ) !== $fingerprint ) {
+			return new WP_Error( 'wpcpm_track_edited', __( 'The definition has been edited since the switch, so going back would drop the edit.', 'wpcredits-program-manager' ) );
+		}
+
+		update_post_meta( $post_id, self::META_SOURCE, 'builtin' );
+		delete_post_meta( $post_id, self::META_SWITCHED );
+		self::compile();
+		self::log( $post_id, 'switch_builtin', $user_id );
+
+		return $post_id;
+	}
+
+	/**
+	 * Every track's post ID, whatever its status, trash included.
+	 *
+	 * @return int[]
+	 */
+	private static function all_ids() {
+		return get_posts(
 			array(
 				'post_type'   => self::POST_TYPE,
 				'post_status' => array_keys( get_post_stati() ),
@@ -554,8 +771,17 @@ final class WPCPM_Track_Store {
 				'fields'      => 'ids',
 			)
 		);
+	}
 
-		foreach ( $ids as $post_id ) {
+	/**
+	 * Delete every track, its revisions and the options compiled from it. Called on uninstall.
+	 *
+	 * Every status, trash included, which `any` would leave behind. A form option is deleted for
+	 * the key of every track post as well as for every key the index names, so a form written by
+	 * a compile that never finished goes too.
+	 */
+	public static function delete_all() {
+		foreach ( self::all_ids() as $post_id ) {
 			$definition = self::get( $post_id );
 
 			if ( is_array( $definition ) && ! empty( $definition['key'] ) ) {
@@ -573,6 +799,7 @@ final class WPCPM_Track_Store {
 
 		delete_option( WPCPM_Tracks::OPT_TRACKS );
 		delete_option( self::OPT_SKIPPED );
+		delete_option( self::OPT_SEEDED );
 		WPCPM_Tracks::flush();
 	}
 }
```

```diff
--- a/includes/tracks/class-wpcpm-tracks.php
+++ b/includes/tracks/class-wpcpm-tracks.php
@@ -364,6 +364,28 @@ final class WPCPM_Tracks {
 		);
 	}
 
+	/**
+	 * What the PHP says of a status, compiled tracks aside: the name, course and hours a built-in
+	 * track's definition must match before it may switch (the design's decision 3.5).
+	 *
+	 * @param string $status Status.
+	 * @return array `label`, `course_url`, `course_id` (0 for none) and `hours` (null for none).
+	 */
+	public static function builtin_row( $status ) {
+		return self::unfiltered(
+			static function () use ( $status ) {
+				$hours = WPCPM_Program::hours_targets();
+
+				return array(
+					'label'      => (string) WPCPM_Program::label( $status ),
+					'course_url' => (string) WPCPM_Program::course_url( $status ),
+					'course_id'  => (int) WPCPM_Program::course_id( $status ),
+					'hours'      => isset( $hours[ $status ] ) ? (int) $hours[ $status ] : null,
+				);
+			}
+		);
+	}
+
 	/**
 	 * The key the PHP gives a status, compiled tracks aside: one of the four built-in keys, or an
 	 * empty string for a status no built-in track holds.
```

```diff
--- a/includes/class-wpcpm-cli.php
+++ b/includes/class-wpcpm-cli.php
@@ -247,6 +247,33 @@ class WPCPM_CLI {
 		WP_CLI::success( __( 'Roles registered.', 'wpcredits-program-manager' ) );
 	}
 
+	/**
+	 * Put the four built-in tracks into the Track Builder, from the seeds the plugin ships.
+	 *
+	 * Each is created as a draft marked built-in, so its PHP keeps running it. A track whose status
+	 * the Track Builder already holds is passed over, so running this twice creates nothing the
+	 * second time. A site does this once on its own, the first time it runs 1.101.0; the command
+	 * is for a site that needs it again.
+	 *
+	 * ## EXAMPLES
+	 *
+	 *     wp wpcredits seed-tracks
+	 */
+	public function seed_tracks() {
+		foreach ( WPCPM_Track_Store::seed() as $key => $result ) {
+			if ( is_wp_error( $result ) ) {
+				WP_CLI::warning( sprintf( '%s: %s', $key, $result->get_error_message() ) );
+			} elseif ( $result ) {
+				WP_CLI::log( sprintf( '%-6s created as track %d, a draft marked built-in', $key, $result ) );
+			} else {
+				WP_CLI::log( sprintf( '%-6s already in the Track Builder', $key ) );
+			}
+		}
+
+		WPCPM_Track_Store::compile();
+		WP_CLI::success( __( 'The built-in tracks are in the Track Builder.', 'wpcredits-program-manager' ) );
+	}
+
 	/**
 	 * Read Airtable and report the totals without writing anything.
 	 */
```

- [ ] **Step 4: Run it.** Expected: `ALL PASS (95 checks)`. Seeding makes four drafts marked built-in, each its shipped seed byte for byte, and seeding again makes none; a site seeds itself once and writes the index, empty and autoloaded; a built-in track cannot switch while its form differs from its PHP, switches when it does not, goes back while unedited, and cannot go back after an edit; a track never built in can do neither; and `equivalence()` names a changed name and changed hours.

- [ ] **Step 5: Run everything.** Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 6: Commit.**

```bash
git add includes/tracks includes/class-wpcpm-cli.php bin/test-track-store.php
git commit -m "Track Builder T2a: a site seeds its four built-in tracks once, and a built-in track switches to its definition only while the two are identical"
```

---

### Task 6: Proof on a real WordPress

The suites stand in for WordPress, and four things only WordPress can answer. Whether `init` at 20 seeds a fresh site once and leaves an index every request loads. Whether a published seed, and then a switched one, leave the program map and the form exactly as they were. Whether an edit saved to a published track still reaches no one after another track is published, with the edit kept as a revision. And whether the uninstall's `delete_all()` finds every track with the post type unregistered, as it is when `uninstall.php` runs (T1's final review, minor 7).

- [ ] **Step 1: Ask the product owner for a yes to creating one throwaway local site, and wait for it.** It downloads WordPress core into `$HOME/Studio/wpcpm-t2a-check` and is deleted in Step 5. If `studio` refuses to run, ask the product owner to open the WordPress Studio app once, as on 10 September 2026. Never name or touch any other Studio site.

- [ ] **Step 2: Create the site and install the branch's build.** `SCRATCH` is the implementer's own scratch folder; nothing of this lands in the plugin.

```bash
SITE="$HOME/Studio/wpcpm-t2a-check"
studio create --path "$SITE" --name "WPCPM T2a check" --skip-browser --skip-log-details
bash bin/build "$SCRATCH/wpcpm-t2a.zip"
(cd "$SITE" && studio wp plugin install "$SCRATCH/wpcpm-t2a.zip" --activate)
```

Expected: the plugin activates. The site has no Airtable settings, so no sync runs.

- [ ] **Step 3: Write the check.** Create `$SCRATCH/t2a-real-wordpress.php`:

```php
<?php
/**
 * Track Builder, phase T2a, on a real WordPress: seeding once, the published copy, the switch,
 * and the uninstall sweep with the post type unregistered.
 *
 * Run on a throwaway site with the 1.101.0 zip active:  studio wp eval-file t2a-real-wordpress.php
 * The request that runs this booted WordPress, so `init` has run and the site has seeded itself.
 */

$fails = 0;
$total = 0;

function t2a_ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		echo "ok   $label\n";
		return;
	}

	++$fails;
	echo "FAIL $label\n     got:  " . var_export( $got, true ) . "\n     want: " . var_export( $want, true ) . "\n";
}

$tracks = get_posts( array( 'post_type' => 'wpcpm_track', 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
$ids    = wp_list_pluck( $tracks, 'ID' );

echo "=== Seeded once ===\n";
t2a_ck(
	'the four built-in tracks are in the Track Builder, drafts marked built-in, in the program\'s order',
	array_map(
		function ( $post ) {
			return array( $post->post_status, WPCPM_Track_Store::get( $post->ID )['key'], get_post_meta( $post->ID, WPCPM_Track_Store::META_SOURCE, true ) );
		},
		$tracks
	),
	array( array( 'draft', '150h', 'builtin' ), array( 'draft', '50h', 'builtin' ), array( 'draft', 'dev', 'builtin' ), array( 'draft', 'design', 'builtin' ) )
);
t2a_ck( 'and the site says it has seeded', (int) get_option( WPCPM_Track_Store::OPT_SEEDED ), 1 );
t2a_ck( 'the index exists, empty, among the options every request loads', array( get_option( WPCPM_Tracks::OPT_TRACKS, 'missing' ), array_key_exists( WPCPM_Tracks::OPT_TRACKS, wp_load_alloptions() ) ), array( array(), true ) );

echo "\n=== A published seed changes nothing a person sees ===\n";
$labels = WPCPM_Program::labels();
$form   = WPCPM_Student_Report_Form::fields( 'design' );
$design = $ids[3];
t2a_ck( 'the Designer Track seed publishes', WPCPM_Track_Store::publish( $design ), $design );
t2a_ck( 'compiled as built-in, so its PHP keeps running it', WPCPM_Tracks::rows()['Designer Track']['source'], 'builtin' );
t2a_ck( 'the program map and the form are what they were', array( WPCPM_Program::labels(), WPCPM_Student_Report_Form::fields( 'design' ) ), array( $labels, $form ) );
t2a_ck( 'and its published definition is its PHP, byte for byte', WPCPM_Track_Store::equivalence( $design ), array() );

echo "\n=== The switch ===\n";
t2a_ck( 'it switches to its definition', WPCPM_Track_Store::switch_to_definition( $design ), $design );
t2a_ck( 'which now runs it', WPCPM_Tracks::rows()['Designer Track']['source'], 'definition' );
t2a_ck( 'and the map and the form are still what they were, now drawn from the definition', array( WPCPM_Program::labels(), WPCPM_Student_Report_Form::fields( 'design' ) ), array( $labels, $form ) );

echo "\n=== An edit saved is not an edit published (the design's decision 3.2) ===\n";
$edited          = WPCPM_Track_Store::get( $design );
$edited['label'] = 'Designer Track, renamed in a draft';
WPCPM_Track_Store::save( $design, $edited );
WPCPM_Track_Store::publish( $ids[1] );
t2a_ck( 'another track published, the edit still reaches no one', WPCPM_Program::labels()['Designer Track'], 'Designer Track' );
$revisions = wp_get_post_revisions( $design );
$newest    = reset( $revisions );
t2a_ck( 'the edit is a revision holding the new definition', WPCPM_Track_Definition::decode( get_post_meta( $newest->ID, WPCPM_Track_Store::META_DEFINITION, true ) )['label'], 'Designer Track, renamed in a draft' );
t2a_ck( 'and the way back to the PHP is closed: it would drop the edit', WPCPM_Track_Store::switch_to_builtin( $design )->get_error_code(), 'wpcpm_track_edited' );

echo "\n=== Uninstall, with the post type unregistered as it is then ===\n";
unregister_post_type( WPCPM_Track_Store::POST_TYPE );
WPCPM_Track_Store::delete_all();
global $wpdb;
$left = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'wpcpm_track' OR ( post_type = 'revision' AND post_parent IN (" . implode( ',', array_map( 'intval', $ids ) ) . ') )' );
t2a_ck( 'every track and every revision of one is gone', $left, 0 );
t2a_ck( 'and the index, the skipped list and the seed flag', array( get_option( WPCPM_Tracks::OPT_TRACKS, 'gone' ), get_option( WPCPM_Track_Store::OPT_SKIPPED, 'gone' ), get_option( WPCPM_Track_Store::OPT_SEEDED, 'gone' ) ), array( 'gone', 'gone', 'gone' ) );
t2a_ck( 'and every form option', (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( WPCPM_Tracks::OPT_FIELDS_PREFIX ) . '%' ) ), 0 );

printf( "\n%s (%d checks)\n", $fails ? $fails . ' FAILED' : 'ALL PASS', $total );
```

- [ ] **Step 4: Run it.** `(cd "$SITE" && studio wp eval-file "$SCRATCH/t2a-real-wordpress.php")`. Expected: sixteen `ok` lines, then `ALL PASS (16 checks)`. A `FAIL` stops the task: report it rather than work around it.

- [ ] **Step 5: Delete the site.** `studio delete --dry-run "$SITE"` first, which must name that one site and nothing else; then `studio delete "$SITE"`, which moves its files to the Trash. Expected: `studio list` no longer shows it.

No commit: this task changes no file of the plugin.

---

### Task 7: Release 1.101.0

**Files:**
- Modify: `wpcredits-program-manager.php` (the `Version:` header and `WPCPM_VERSION`), `readme.txt` (`Stable tag:` and a changelog entry), `languages/wpcredits-program-manager.pot` (regenerated)

- [ ] **Step 1: Move the version.** `1.100.0` becomes `1.101.0` in the plugin header's `Version:` line, in `define( 'WPCPM_VERSION', ... )` and in `readme.txt`'s `Stable tag:`.

- [ ] **Step 2: Write the changelog entry**, first under `== Changelog ==` in `readme.txt`, with one empty line after it:

```text
= 1.101.0 =

* Track Builder, phase T2a (docs/plans/2026-09-11-track-builder-t2a.md): the definitions the Track Builder screen of phase T2b will work on. Nothing changes on any page. The first request after the update puts the four built-in tracks into the Track Builder as drafts, from the seed definitions the plugin now ships in `includes/tracks/seeds/`, each held byte for byte to its hand-written form by `bin/test-track-definitions.php`; their PHP keeps running them.
* Publishing a track records its definition as published, and the live site compiles that copy, never the latest saved one, so an edit reaches students only once it is published. Every compile checks each published track again and leaves out one the rules now refuse, or one claiming a status or key another track holds; a new track can no longer take one of the four built-in statuses.
* The rules are stricter: a track's name may not be another track's name or status, a column one capital or space away from a column the syncs own is refused, a whole number is refused as a column name however it is written, and lengths are counted in characters. `WPCPM_Student_Report_Form::fields()` now hands the Track Builder `builtin_fields()`, and the three alumni answers carry `hide_from_institution` in the PHP as well.
* New underneath, with no screen yet: publishing and unpublishing, the switch between a built-in track's PHP and its definition (allowed only while the two are identical), a log of both, and `wp wpcredits seed-tracks`. The uninstall also removes any Track Builder form the index lost track of.
```

- [ ] **Step 3: Regenerate the translation template.** `sh bin/make-pot.sh`. Expected: `Success: POT file successfully generated.`, a header reading `Project-Id-Version: WPCredits Program Manager 1.101.0`, and 2998 `msgid` lines.

- [ ] **Step 4: Run everything.** Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 5: Build the zip and read it back.**

```bash
bash bin/build
unzip -p ../wpcredits-program-manager.zip wpcredits-program-manager/wpcredits-program-manager.php | grep "Version:"
unzip -Z1 ../wpcredits-program-manager.zip | grep -c 'includes/tracks/seeds/.*\.json$'
```

Expected: `Version:           1.101.0` and `4`, and the archive lists no `bin/` or `docs/`.

- [ ] **Step 6: Commit.**

```bash
git add wpcredits-program-manager.php readme.txt languages/wpcredits-program-manager.pot
git commit -m "Track Builder T2a: 1.101.0"
```

- [ ] **Step 7: Merge and mirror, on the product owner's choice.** Merge `track-builder-t2a` into `main` (fast-forward) and run the battery on the result. Then push the source to the public mirror, which the product owner asked for after every version bump: pull the `WordPress/WPCredits` clone at `~/GitHub/Plugins/WPCredits-Tracker-mirror` (branch `trunk`), rsync the plugin into its `Education/WordPress Education Dashboard/wpcredits-program-manager/` with `rsync -a --delete --exclude '.git/' --exclude '.superpowers/' --exclude '.DS_Store' --exclude 'node_modules/' --exclude '*.zip' --exclude '.*.swp'`, update the version in that folder's `README.md` table, scan the added lines for keys, record IDs, email addresses and names, read `git diff --stat`, then commit and push as two separate steps.

- [ ] **Step 8: Deploy only on the product owner's yes.** Ask first. On a yes, follow the deploy recorded for `wordpresseducation.org`: stream the zip over `ssh wpcredits-dashboard`, check its md5 on arrival, install it over the old one as a step of its own, read the version back, and purge the edge cache. Before the install and after it, run one read-only check of what a person sees: the program map, the Administrator Dashboard's Programs running card drawn as a Program Administrator, and the Student Report Card drawn as the TEST students, with version strings and nonces normalized. The two runs must be identical. Then, with an administrator set as the current user, confirm that the four built-in tracks are drafts in the Track Builder and that `wpcpm_tracks_seeded` reads 1.

---

## What T2a leaves for T2b

- **The screen.** `WPCPM_Track_Builder extends WPCPM_Tool`, added to the array literal in `WPCPM_Tools::all()` (`includes/class-wpcpm-tools.php`), so the admin menu lists it under Modules; gated on `wpcpm_manage_program`; every handler runs `WPCPM_Sync_Module::verify()`'s order, the capability and then the nonce, before anything else, and redirects through `WPCPM_Flash` and `WPCPM_Return`.
- **The track list reads the store.** `state()` for draft, published or unpublished changes; `log_entries()` for who published a track last and when; `wpcpm_tracks_skipped` for any published track the last compile left out, with the rule that left it out; `equivalence()` beside each built-in track's switch.
- **Duplication records `duplicated_from` as post meta**, not in the definition: it is not in `TRACK_PROPERTIES`, and `validate()` refuses a property it does not know.
- **Publishing from the screen wraps the store's `publish()`**: the read-only preflight (spec 7.1, which needs `WPCPM_Airtable::fetch_schema()` to return each field's type and a select's choices, not only its description), the lock (the syncs' `add_option()` claim with a timeout), and the checklist, whose first item writes `META_AUTOMATION` and a `log()` line.
- **Unpublishing from the screen is refused while any synced student holds the status**, with the count, before it calls the store's `unpublish()`.
- **The checklist tick must recompile.** `META_AUTOMATION` reaches `confirmed_automation_statuses()` only through `compile()`. Spec open item 5 says so, and Task 2's review raised it.
- **The switch's rules are in the store.** A built-in track is read-only there until it switches (the final review's I2: `save()` refuses it with `wpcpm_track_builtin`), and the way back also needs the published copy to be the PHP's (its I1). T2b's screen shows `equivalence()` beside both directions of the switch.
- **A seeded draft goes stale when a hand-written form changes.**
  - `seed()` passes over held statuses.
  - `maybe_seed()` runs once.
  - `SEED_VERSION` is recorded but never compared.
  - Because `save()` refuses a built-in track, it cannot refresh a stale draft, so the refresh is a store method of its own. T2b adds it, or the first release that changes a hand-written form does.
- **`publish()` adds the status to "Currently mentoring" even for a built-in seed.** Publishing the 50h seed would put back `In Sensei 50h` after a manager took it out. T2b skips the addition for a builtin-source track, or shows it in the preflight.
- **A compile skip takes a published track off the map with no student check.** The track list presents `wpcpm_tracks_skipped` as a failure that needs action.
- **The index and the skipped list are only as fresh as the last compile.** T2b recompiles when the settings are saved, or runs the check live in the list.
- **`WPCPM_Tracks::validation_context( $own_status )` keeps decision 4's hole.** T2b's editor relies on `publish()`'s errors, or on a store method that uses `context()`.
- **`publish()` brings a trashed track back as published** (the final review's M3). `bin/test-track-store.php` has no check of `state()` for a post that is not a track, or for a trashed one (Task 2's review). T2b refuses trash in `publish()` and adds both checks.
- **A request that dies mid-seed** leaves `wpcpm_tracks_seeded` set with fewer than four drafts (the final review's M4).
  - `wp wpcredits seed-tracks` is the way to recover.
  - It should exit non-zero when a seed failed (Task 5's review).
  - The four seeded posts carry whoever made the first request as their author, so the list does not show "created by".
- **The institution import form and institution create** read `labels()`, so they would offer a new track from its first compile, before its automation item is ticked, and a student put on it then gets no report row (spec 2.4). The product owner decided on 11 September 2026 that they wait for the tick (the design's decision 1.10), which T2b builds.
- **Open item 7**, `maxlength` on a textarea, is still the product owner's to settle before T3's editor offers it.
- Blank tracks, the question editor, preview, history and a track started from its Learn course are T3's.
