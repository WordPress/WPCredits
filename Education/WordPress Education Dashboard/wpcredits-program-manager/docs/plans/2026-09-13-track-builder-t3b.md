# Track Builder T3b Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Three screens the Track Builder still lacks - Preview, a track started from nothing, and History - plus decision 29's four correctness leftovers and the wording of a built-in track's row and publish screen, as release 1.106.0.

**Architecture:** One new pure unit and one new screen, in the shape T3a used. `WPCPM_Track_Diff` compares two copies of a track with no WordPress in it: the track properties that changed, the questions added, removed and moved, and which properties of each remaining question changed. `WPCPM_Track_History_Screen` draws what `WPCPM_Track_Builder::history()` read and asks the store nothing. Preview is the Student Report Card's own renderer: `render_body()`'s group loop and `render_hours()`'s field become `render_groups()` and `render_hours_field()`, called with exactly what they took before so the live markup does not change, and `render_preview( array $fields )` is the one public entry on top of them; the builder compiles the draft with `compile_fields()`, the call `compile()` makes, so the report form learns nothing about definitions. New track is a third form beside Duplicate, checked through the store as a copy is. The store gains `revisions()`, the palette `first_free()`, the builder three routes and the data each hands its view, and the two screens' property labels move into one map each, which History prints its diffs in.

**Tech Stack:** WordPress 6.5 and PHP 7.4 as floors, WordPress coding standards, the plugin's standalone `bin/test-*.php` suites, no build step, no new script: `assets/js/track-editor.js` is untouched.

**Spec:** `docs/specs/2026-09-10-track-builder-design.md` at `9c86d6e`: section 5 for Preview, History and New track, section 6 for the screens, 11's T3b row for the tests, 12's T3b row for the phase, and decisions 27 to 29 in section 1, settled on 13 September 2026.

## Global Constraints

- **Start from `main` at 1.105.0 with the T3b amendment.** `grep "^Stable tag" readme.txt` prints `Stable tag: 1.105.0`, `git log --oneline -1 -- docs/specs/2026-09-10-track-builder-design.md` names the commit that added decisions 27 to 29, and `git status --short` prints nothing. Then `git switch -c track-builder-t3b`. Every block below was proven one commit at a time on `9c86d6e` and replayed onto a fresh checkout.
- WordPress coding standards throughout: tabs, Yoda conditions, spaces inside parentheses, `array()` and never `[]`, strict `in_array()`. Everything that ships is PHP 7.4 compatible: no `match`, no named arguments, no union types, no arrow functions in a constant. Every string a person reads is US English. No em dash or en dash anywhere, in code, comments, docs or commit messages: a plain hyphen. Full product names ("Student Report Card", "Mentor Report Card", "Administrator Dashboard").
- Everything a person can see is escaped on output, and every handler checks capability first, then nonce, in that order (the design's decision 3.9). `bin/test-track-builder.php` fails if the two are swapped, on the new handler as on every older one.
- **The battery stays silent after every task:** `for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done` prints nothing. `php bin/check-references.php`, `php bin/check-spelling.php` and `php bin/check-dead-annotations.php` report clean. `bash bin/check-standards.sh` prints no line containing ` ERROR `, and its last line reads `85 warnings, no errors.` or a lower count. What tripped it while this plan was proven: `$new` is a reserved word, refused as a parameter name; a stand-alone `$i++` is refused, `++$i` is not; a parameter added to a method needs its own `@param` line, aligned with the others; the `=` of consecutive assignments align; a docblock's `@param` lines align on the type and the name; a blank line before a closing brace is an error; and `esc_html( sprintf( ... ) )` spread over lines is an error, so the `sprintf()` goes into a variable first.
- **Test first, every task:** add the checks, run them, see them fail as the step says, then write the code.
- **A suite drives the real class wherever the rule lives in one.** `bin/test-track-builder.php` loads the real `WPCPM_Track_Palette`, `WPCPM_Track_Definition` and `WPCPM_Track_Diff` from this plan on, beside T3a's `WPCPM_Track_Questions`, `WPCPM_Track_Columns`, `WPCPM_Track_Editor`, `WPCPM_Track_Editor_Screen` and the builder's own two classes, and stands in only WordPress and the collaborators with side effects: the store, publishing, the students sync, the calendar module and the report form. A stand-in is faithful: the store's `revisions()` stand-in reads one more than asked, as the real one does, and the palette's earlier stand-in, with three hues of its own, goes.
- **A column name is exact.** It is the question's key, verbatim, and an Airtable name can end in a space (4.2). The diff compares keys verbatim and never trims one.
- **The live Student Report Card's markup does not change.** Task 3 pins `render_body()`'s output for three cases by md5 taken before the extraction, and the pins hold after it.
- **Nothing is deleted in Airtable, ever** (section 14, decision 9). Nothing in this plan deletes anything at all: a new track is a post, a preview draws, History reads.
- Every new class file is required in `wpcredits-program-manager.php` and in `uninstall.php`, in that order, or `bin/test-roles.php` fails.
- Version numbers move in Task 10 only: plugin 1.106.0, which `version_compare()` orders after 1.105.0. No theme release.
- Comments explain why and name the decision or the review that made the rule. Every commit message starts with "Track Builder T3b:" and ends with the `Co-Authored-By:` trailer of whoever made it, after a blank line.

## What this plan decides

1. **Preview takes a field set, and the builder compiles it** (decision 27). `WPCPM_Track_Builder::preview()` calls `WPCPM_Track_Definition::compile_fields()`, the call `compile()` makes, so the preview and the published form come from the same reading of the definition with the same authoring properties left out, and `render_preview()` never sees a definition.
2. **The preview page enqueues the calendar module's sheet and wraps the form in `wpcpm-dashboard`.** The rules that lay out the report form live in `assets/css/calendar.css` (handle `wpcpm-call-calendar`, which depends on `dashboard.css`), the institution's student view enqueues the same handle when it draws a Student Report Card, and `dashboard.css` sets its `--wpcpm-*` tokens on `.wpcpm-dashboard`, so the wrapper carries that class or the form draws untokened. Neither sheet has an unscoped selector, so nothing in wp-admin restyles. The handle is registered first, as the calendar module registers it on `init`, so it resolves whichever order the modules booted in.
3. **A blank track is checked as a copy is.** `handle_new()` builds a definition from the three things typed, an empty question list and a hue, and runs `WPCPM_Track_Store::check( 0, ... )` before any post exists, so a status or key another track holds is refused before a draft nobody asked for is created. `validate()` accepts an empty question list.
4. **A new track and a duplicate take the first hue no track holds.** Section 5 gives a duplicate this rule and T2b's copy kept its original's instead, so two tracks could share one chip color; `WPCPM_Track_Palette::first_free()` applies to both, and with every hue taken answers the palette's first, since a chip must have some color (decision 10).
5. **History's data is read in the builder; the screen asks nothing** (decision 28). `history()` hands over the published copy against the draft (null when nothing was published), the revisions capped at `HISTORY_LIMIT` (20, the semester report screen's own cap) with one more read so the oldest shown has a predecessor, "created" with a question count only where there is none, a save that kept no definition handed over with neither, the log newest first, and the checklist's labels for the log's ticks.
6. **The diff prints in the screens' own words.** `WPCPM_Track_Editor_Screen::property_labels()` and `WPCPM_Track_Builder_Screen::track_labels()` are the single maps their own rows read, so a label changed there changes in History; a property no screen names prints as itself.
7. **Moved is the complement of the longest common subsequence** of the two orders (decision 28), so a question dragged past ten others is one move; values are compared flattened, so a stored 100 and a posted "100" are the same length limit and a flag stored `true` is a flag posted `1`.
8. **The built-in wording follows source and state, not state alone** (decision 29). "Live, from its hand-written form" replaces "Draft" only on a built-in track whose definition is a draft; a built-in track whose definition is published keeps "Published" and the switch. The publish screen and its buttons read `source`, which the route now passes.
9. **A locked question's rows follow the stored control**, and its forked-from notice drops the clause that promised a rename the lock refuses (decision 29).
10. **A capped list says so, and an empty one says why.** When older saves exist than are shown, a line under the list says the last 20 are shown; a track with no revisions at all says WordPress keeps a copy of each save only while revisions are on, which is what `WP_POST_REVISIONS` turns off.

## File structure

**Created**

| File | What it holds |
| --- | --- |
| `includes/tracks/class-wpcpm-track-diff.php` | Two definitions in, the track properties that changed, the questions added, removed and moved, and which properties of which question changed. No WordPress, no HTTP. |
| `includes/tools/class-wpcpm-track-history-screen.php` | The three parts of History: what publishing would change, every save against the one before, the publish log. |
| `bin/test-track-diff.php` | The pure class's suite. |

**Modified**

| File | Why |
| --- | --- |
| `includes/tracks/class-wpcpm-track-store.php` | `revisions()`, newest first, one more than asked. |
| `includes/modules/class-wpcpm-student-report-form.php` | `render_groups()` and `render_hours_field()` extracted from `render_body()` and `render_hours()`; `render_preview()` on top of them. |
| `includes/tracks/class-wpcpm-track-publish.php` | The preflight reads a store refusal's `where`. |
| `includes/tracks/class-wpcpm-track-palette.php` | `first_free()`. |
| `includes/tools/class-wpcpm-track-builder.php` | New track's action, hook, route and handler; the duplicate's hue; `preview()` and `history()` with their routes; the sheet the preview route enqueues; the publish route passes `source`. |
| `includes/tools/class-wpcpm-track-builder-screen.php` | `render_new()`, `render_preview()`, the New track button, the Preview and History links, `track_labels()`, and the wording of a built-in row and its publish screen. |
| `includes/tools/class-wpcpm-track-editor-screen.php` | `property_labels()`, which its rows read; a locked question's rows follow the stored control; the forked-from notice on a locked question. |
| `assets/css/track-builder.css` | The preview wrapper and the History list. |
| `wpcredits-program-manager.php`, `uninstall.php` | The two new class files. |
| `bin/test-track-store.php`, `bin/test-report-form.php`, `bin/test-track-publish.php`, `bin/test-track-builder.php` | Each holds its class to the new rules. |
| `readme.txt`, `languages/wpcredits-program-manager.pot` | The release. |

---

### Task 1: What changed between two copies of a track

**Files:**
- Create: `includes/tracks/class-wpcpm-track-diff.php`
- Modify: `wpcredits-program-manager.php`, `uninstall.php` (the new class is required in both, after `class-wpcpm-track-questions.php`)
- Test: `bin/test-track-diff.php`

**Interfaces:**
- Consumes: nothing. The class is pure: two definitions in, an account of the difference out. No WordPress function, no HTTP.
- Produces: `WPCPM_Track_Diff::TRACK` (`status`, `key`, `label`, `course_url`, `learn_course_id`, `hours_target`, `hue`: the track's own properties, compared one by one; `questions` is compared question by question and `schema_version` is the format's) and `WPCPM_Track_Diff::between( array $before, array $after ): array` answering `track` (the properties of `TRACK` that differ, in that order), `added` and `removed` (column names, verbatim), `moved` (columns present in both whose order changed, the fewest that account for it, in the newer copy's order), `changed` (column => the properties that differ, in the newer copy's order and then the removed ones) and `same` (true when every one of those is empty). Values are compared through a flattening: `true` as `1`, `false` and `null` as nothing, a number or a string as the string it prints as, a list item by item, so a property present on one side and absent on the other counts, and a stored 100 equals a posted "100".

Decision 28's comparison, in the shape `WPCPM_Track_Questions` and `WPCPM_Track_Columns` set in T3a: every rule History needs and nothing that touches WordPress, so its suite needs neither. A question is keyed by its column name, verbatim (the design's 4.2), so the keys of the two `questions` maps are what is compared and nothing here trims one.

Moved is the complement of the longest common subsequence of the two orders (what this plan decides, 7): a question dragged from first to last past ten others is one move and not eleven, two neighbors swapped is one move whichever of the two is named, and a question added or removed in the middle moves nothing. Reordered choices of a select are a change, because Airtable keeps their order.

`bin/test-roles.php` walks `includes/` and fails for any class file the loader does not require, which is why the loader and `uninstall.php` are in this task. Two of the gate's traps are in this file: the parameters are `$older` and `$newer` because `$new` is a reserved word, and the subsequence walk steps with `++$i`, since a stand-alone `$i++` is refused.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-diff.php b/bin/test-track-diff.php
new file mode 100644
index 0000000..05a36e7
--- /dev/null
+++ b/bin/test-track-diff.php
@@ -0,0 +1,221 @@
+<?php
+/**
+ * What changed between two copies of a track (Track Builder, phase T3b).
+ *
+ * `WPCPM_Track_Diff` compares two definitions and touches neither WordPress nor Airtable, so this
+ * suite loads the real class and stands nothing in for it.
+ *
+ * Run from the plugin root:  php bin/test-track-diff.php
+ */
+
+if ( 'cli' !== PHP_SAPI ) {
+	exit( 1 );
+}
+
+define( 'ABSPATH', __DIR__ . '/' );
+
+require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-diff.php';
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
+/** A track of twelve questions, in the shape the store holds. */
+function track() {
+	$questions = array( 'Hours' => array( 'type' => 'number', 'label' => 'Hours', 'group' => 'hours', 'min' => 0, 'max' => 1000, 'step' => 1 ) );
+
+	foreach ( array( 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K' ) as $letter ) {
+		$questions[ 'Question ' . $letter ] = array( 'type' => 'text', 'label' => 'Question ' . $letter, 'group' => 'project', 'help' => 'Help ' . $letter );
+	}
+
+	return array(
+		'schema_version' => 1,
+		'status'         => 'Marketing Track',
+		'key'            => 'marketing',
+		'label'          => 'Marketing Track',
+		'hue'            => 'teal',
+		'hours_target'   => 50,
+		'questions'      => $questions,
+	);
+}
+
+/** The same track with one change applied by a callback. */
+function changed( callable $change ) {
+	$track = track();
+	$change( $track );
+
+	return $track;
+}
+
+echo "=== Nothing changed ===\n";
+
+ck( 'two identical copies differ in nothing, and say so',
+    WPCPM_Track_Diff::between( track(), track() ),
+    array( 'track' => array(), 'added' => array(), 'removed' => array(), 'moved' => array(), 'changed' => array(), 'same' => true ) );
+
+ck( 'a stored 100 and a posted "100" are the same length limit, and a flag stored true is a flag posted 1',
+    WPCPM_Track_Diff::between(
+        changed( function ( &$t ) { $t['questions']['Question A']['maxlength'] = 100; $t['questions']['Question A']['required'] = true; } ),
+        changed( function ( &$t ) { $t['questions']['Question A']['maxlength'] = '100'; $t['questions']['Question A']['required'] = '1'; } )
+    )['same'],
+    true );
+
+echo "\n=== The track's own properties ===\n";
+
+ck( 'a renamed track and a changed hours target are named, in the order the properties are kept',
+    WPCPM_Track_Diff::between( track(), changed( function ( &$t ) { $t['label'] = 'Marketing'; $t['hours_target'] = 60; } ) )['track'],
+    array( 'label', 'hours_target' ) );
+
+ck( 'a course added where there was none is a change',
+    WPCPM_Track_Diff::between( track(), changed( function ( &$t ) { $t['course_url'] = 'https://learn.wordpress.org/course/marketing/'; } ) )['track'],
+    array( 'course_url' ) );
+
+ck( 'and the questions are not the track',
+    WPCPM_Track_Diff::between( track(), changed( function ( &$t ) { $t['questions']['Question A']['label'] = 'Renamed'; } ) )['track'],
+    array() );
+
+echo "\n=== Added and removed ===\n";
+
+$with_new = changed( function ( &$t ) { $t['questions']['Your blog'] = array( 'type' => 'url', 'label' => 'Your blog', 'group' => 'onboarding' ); } );
+
+ck( 'a new question is added, nothing else moves',
+    array_intersect_key( WPCPM_Track_Diff::between( track(), $with_new ), array( 'added' => 1, 'removed' => 1, 'moved' => 1, 'changed' => 1, 'same' => 1 ) ),
+    array( 'added' => array( 'Your blog' ), 'removed' => array(), 'moved' => array(), 'changed' => array(), 'same' => false ) );
+
+ck( 'the other way round it is removed',
+    WPCPM_Track_Diff::between( $with_new, track() )['removed'],
+    array( 'Your blog' ) );
+
+ck( 'an empty track against a full one adds every question and the columns come verbatim',
+    WPCPM_Track_Diff::between( array( 'questions' => array() ), changed( function ( &$t ) { $t['questions'] = array( 'Company ' => array( 'type' => 'text' ), 'Hours' => array( 'type' => 'number' ) ); } ) )['added'],
+    array( 'Company ', 'Hours' ) );
+
+ck( 'a full track against an empty one removes them',
+    count( WPCPM_Track_Diff::between( track(), array( 'questions' => array() ) )['removed'] ),
+    12 );
+
+ck( 'a definition with no questions at all reads as empty rather than failing',
+    WPCPM_Track_Diff::between( array(), array() )['same'],
+    true );
+
+echo "\n=== Moved ===\n";
+
+/** The questions of a track reordered: `$order` names the letters in their new order. */
+function reordered( array $order ) {
+    return changed( function ( &$t ) use ( $order ) {
+        $questions = array( 'Hours' => $t['questions']['Hours'] );
+
+        foreach ( $order as $letter ) {
+            $questions[ 'Question ' . $letter ] = $t['questions'][ 'Question ' . $letter ];
+        }
+
+        $t['questions'] = $questions;
+    } );
+}
+
+ck( 'a question dragged from first to last past ten others is one move, not eleven',
+    WPCPM_Track_Diff::between( track(), reordered( array( 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'A' ) ) )['moved'],
+    array( 'Question A' ) );
+
+ck( 'and dragged from last to first, one move as well',
+    WPCPM_Track_Diff::between( track(), reordered( array( 'K', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J' ) ) )['moved'],
+    array( 'Question K' ) );
+
+$swap = WPCPM_Track_Diff::between( track(), reordered( array( 'B', 'A', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K' ) ) )['moved'];
+
+ck( 'two neighbors swapped is one move, whichever of the two is named',
+    array( count( $swap ), in_array( $swap[0], array( 'Question A', 'Question B' ), true ) ),
+    array( 1, true ) );
+
+ck( 'two questions each dragged elsewhere are two moves',
+    count( WPCPM_Track_Diff::between( track(), reordered( array( 'B', 'C', 'D', 'A', 'E', 'F', 'G', 'H', 'K', 'I', 'J' ) ) )['moved'] ),
+    2 );
+
+ck( 'a move is reported in the newer copy\'s order',
+    WPCPM_Track_Diff::between( track(), reordered( array( 'K', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'A' ) ) )['moved'],
+    array( 'Question K', 'Question A' ) );
+
+ck( 'a question added in the middle moves nothing',
+    WPCPM_Track_Diff::between( track(), changed( function ( &$t ) {
+        $questions = array();
+        foreach ( $t['questions'] as $column => $spec ) {
+            $questions[ $column ] = $spec;
+            if ( 'Question C' === $column ) {
+                $questions['Your blog'] = array( 'type' => 'url', 'label' => 'Your blog', 'group' => 'project' );
+            }
+        }
+        $t['questions'] = $questions;
+    } ) )['moved'],
+    array() );
+
+ck( 'and a question removed from the middle moves nothing',
+    WPCPM_Track_Diff::between( track(), changed( function ( &$t ) { unset( $t['questions']['Question C'] ); } ) )['moved'],
+    array() );
+
+echo "\n=== Changed properties ===\n";
+
+ck( 'a reworded question names the property',
+    WPCPM_Track_Diff::between( track(), changed( function ( &$t ) { $t['questions']['Question B']['label'] = 'Question B, reworded'; } ) )['changed'],
+    array( 'Question B' => array( 'label' ) ) );
+
+ck( 'a property added and one removed are both changes, the added one first',
+    WPCPM_Track_Diff::between(
+        track(),
+        changed( function ( &$t ) { $t['questions']['Question B']['note'] = 'One of these.'; unset( $t['questions']['Question B']['help'] ); } )
+    )['changed'],
+    array( 'Question B' => array( 'note', 'help' ) ) );
+
+ck( 'a control change names type and the Airtable type it brought',
+    WPCPM_Track_Diff::between(
+        track(),
+        changed( function ( &$t ) { $t['questions']['Question B']['type'] = 'textarea'; $t['questions']['Question B']['airtable_type'] = 'multilineText'; } )
+    )['changed'],
+    array( 'Question B' => array( 'type', 'airtable_type' ) ) );
+
+$select = changed( function ( &$t ) { $t['questions']['Question B'] = array( 'type' => 'select', 'label' => 'Question B', 'group' => 'project', 'options' => array( 'Yes', 'No' ) ); } );
+
+ck( 'reordered choices are a change, because Airtable keeps their order',
+    WPCPM_Track_Diff::between( $select, changed( function ( &$t ) { $t['questions']['Question B'] = array( 'type' => 'select', 'label' => 'Question B', 'group' => 'project', 'options' => array( 'No', 'Yes' ) ); } ) )['changed'],
+    array( 'Question B' => array( 'options' ) ) );
+
+ck( 'the same choices are not',
+    WPCPM_Track_Diff::between( $select, $select )['same'],
+    true );
+
+ck( 'several questions changed are each named, in the newer copy\'s order',
+    array_keys( WPCPM_Track_Diff::between( track(), changed( function ( &$t ) { $t['questions']['Question D']['help'] = 'x'; $t['questions']['Question A']['required'] = true; } ) )['changed'] ),
+    array( 'Question A', 'Question D' ) );
+
+ck( 'a change, a move, an addition and a removal all at once are each reported where they belong',
+    array_intersect_key(
+        WPCPM_Track_Diff::between(
+            track(),
+            changed( function ( &$t ) {
+                unset( $t['questions']['Question K'] );
+                $t['questions']['Question A']['label'] = 'First, reworded';
+                $t['questions']['Your blog'] = array( 'type' => 'url', 'label' => 'Your blog', 'group' => 'project' );
+                $moved = $t['questions']['Question B'];
+                unset( $t['questions']['Question B'] );
+                $t['questions']['Question B'] = $moved;
+            } )
+        ),
+        array( 'added' => 1, 'removed' => 1, 'moved' => 1, 'changed' => 1 )
+    ),
+    array( 'added' => array( 'Your blog' ), 'removed' => array( 'Question K' ), 'moved' => array( 'Question B' ), 'changed' => array( 'Question A' => array( 'label' ) ) ) );
+
+printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );
+
+exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-diff.php`

Expected: `bin/test-track-diff.php` stops with `Fatal error: Uncaught Error: Failed opening required 'includes/tracks/class-wpcpm-track-diff.php'`. The class file does not exist yet, so the suite's `require_once` fails. (`bin/test-roles.php` still passes here: it flags a class file under `includes/` that the loader does not require, and the file does not exist until Step 3, which creates it and requires it in the same step.)

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/tracks/class-wpcpm-track-diff.php b/includes/tracks/class-wpcpm-track-diff.php
new file mode 100644
index 0000000..15462bc
--- /dev/null
+++ b/includes/tracks/class-wpcpm-track-diff.php
@@ -0,0 +1,189 @@
+<?php
+/**
+ * What changed between two copies of a track.
+ *
+ * @package WPCredits_Program_Manager
+ */
+
+defined( 'ABSPATH' ) || exit;
+
+/**
+ * Two definitions in, a question-level account of the difference out.
+ *
+ * No WordPress and no Airtable in it: History (`WPCPM_Track_History_Screen`) reads the copies and
+ * prints the answer, this class compares them, and its suite needs neither (the design's decision
+ * 28, and the shape `WPCPM_Track_Questions` and `WPCPM_Track_Columns` set).
+ *
+ * A question is keyed by its column name, verbatim (the design's 4.2), so the keys of the two
+ * `questions` maps are what is compared, never trimmed.
+ */
+final class WPCPM_Track_Diff {
+
+	/**
+	 * The track's own properties, compared one by one.
+	 *
+	 * `questions` is compared question by question below, and `schema_version` is the format's,
+	 * not the track's.
+	 *
+	 * @var string[]
+	 */
+	const TRACK = array( 'status', 'key', 'label', 'course_url', 'learn_course_id', 'hours_target', 'hue' );
+
+	/**
+	 * Compare two definitions.
+	 *
+	 * @param array $before The older copy.
+	 * @param array $after  The newer copy.
+	 * @return array `track` (the track properties that changed), `added` and `removed` (columns),
+	 *               `moved` (columns present in both whose order changed, the fewest that account
+	 *               for it), `changed` (column => the properties that changed), and `same`, true
+	 *               when nothing did.
+	 */
+	public static function between( array $before, array $after ) {
+		$track = array();
+
+		foreach ( self::TRACK as $property ) {
+			if ( self::flat( isset( $before[ $property ] ) ? $before[ $property ] : null ) !== self::flat( isset( $after[ $property ] ) ? $after[ $property ] : null ) ) {
+				$track[] = $property;
+			}
+		}
+
+		$old = self::questions( $before );
+		$new = self::questions( $after );
+
+		$added   = array_values( array_diff( array_keys( $new ), array_keys( $old ) ) );
+		$removed = array_values( array_diff( array_keys( $old ), array_keys( $new ) ) );
+		$common  = array_values( array_intersect( array_keys( $new ), array_keys( $old ) ) );
+		$changed = array();
+
+		foreach ( $common as $column ) {
+			$properties = self::properties( $old[ $column ], $new[ $column ] );
+
+			if ( array() !== $properties ) {
+				$changed[ $column ] = $properties;
+			}
+		}
+
+		$moved = self::moved( array_values( array_intersect( array_keys( $old ), $common ) ), $common );
+
+		return array(
+			'track'   => $track,
+			'added'   => $added,
+			'removed' => $removed,
+			'moved'   => $moved,
+			'changed' => $changed,
+			'same'    => array() === $track && array() === $added && array() === $removed && array() === $moved && array() === $changed,
+		);
+	}
+
+	/**
+	 * The questions of a definition, as a map keyed by column name.
+	 *
+	 * @param array $definition The definition.
+	 * @return array
+	 */
+	private static function questions( array $definition ) {
+		$questions = isset( $definition['questions'] ) && is_array( $definition['questions'] ) ? $definition['questions'] : array();
+		$map       = array();
+
+		foreach ( $questions as $column => $spec ) {
+			$map[ (string) $column ] = is_array( $spec ) ? $spec : array();
+		}
+
+		return $map;
+	}
+
+	/**
+	 * The properties of one question that differ between two copies of it.
+	 *
+	 * A property present on one side and absent on the other counts, so a help line removed is
+	 * reported as `help` changed. Values are compared through `flat()`, so a stored 100 and a
+	 * posted "100" are the same length limit.
+	 *
+	 * @param array $older The question before.
+	 * @param array $newer The question after.
+	 * @return string[] Property names, in the order the newer copy holds them, then the removed ones.
+	 */
+	private static function properties( array $older, array $newer ) {
+		$changed = array();
+
+		foreach ( array_unique( array_merge( array_keys( $newer ), array_keys( $older ) ) ) as $property ) {
+			$was = array_key_exists( $property, $older ) ? self::flat( $older[ $property ] ) : null;
+			$is  = array_key_exists( $property, $newer ) ? self::flat( $newer[ $property ] ) : null;
+
+			if ( $was !== $is ) {
+				$changed[] = (string) $property;
+			}
+		}
+
+		return $changed;
+	}
+
+	/**
+	 * The columns whose order changed: the common columns not in the longest common subsequence
+	 * of the two orders, so a question dragged past ten others is reported once, not eleven times.
+	 *
+	 * @param string[] $older The common columns in the older copy's order.
+	 * @param string[] $newer The same columns in the newer copy's order.
+	 * @return string[] The movers, in the newer copy's order.
+	 */
+	private static function moved( array $older, array $newer ) {
+		$n = count( $older );
+		$m = count( $newer );
+
+		if ( $n < 2 || $older === $newer ) {
+			return array();
+		}
+
+		$length = array_fill( 0, $n + 1, array_fill( 0, $m + 1, 0 ) );
+
+		for ( $i = $n - 1; $i >= 0; --$i ) {
+			for ( $j = $m - 1; $j >= 0; --$j ) {
+				$length[ $i ][ $j ] = $older[ $i ] === $newer[ $j ]
+					? $length[ $i + 1 ][ $j + 1 ] + 1
+					: max( $length[ $i + 1 ][ $j ], $length[ $i ][ $j + 1 ] );
+			}
+		}
+
+		$kept = array();
+		$i    = 0;
+		$j    = 0;
+
+		while ( $i < $n && $j < $m ) {
+			if ( $older[ $i ] === $newer[ $j ] ) {
+				$kept[] = $newer[ $j ];
+				++$i;
+				++$j;
+			} elseif ( $length[ $i + 1 ][ $j ] >= $length[ $i ][ $j + 1 ] ) {
+				++$i;
+			} else {
+				++$j;
+			}
+		}
+
+		return array_values( array_diff( $newer, $kept ) );
+	}
+
+	/**
+	 * A value in one shape for comparison: a flag as `1`, a number or a string as the string it
+	 * prints as, a list as the list of its flattened items, and nothing as null.
+	 *
+	 * @param mixed $value Any stored value.
+	 * @return mixed
+	 */
+	private static function flat( $value ) {
+		if ( null === $value || false === $value ) {
+			return null;
+		}
+
+		if ( true === $value ) {
+			return '1';
+		}
+
+		if ( is_array( $value ) ) {
+			return array_map( array( __CLASS__, 'flat' ), $value );
+		}
+
+		return (string) $value;
+	}
+}
diff --git a/uninstall.php b/uninstall.php
index 7811407..50f14b6 100644
--- a/uninstall.php
+++ b/uninstall.php
@@ -54,6 +54,7 @@ require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-form-stash.php'
 require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-track-palette.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-track-columns.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-track-questions.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-track-diff.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-track-publish.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-track-definition.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tracks/class-wpcpm-tracks.php';
diff --git a/wpcredits-program-manager.php b/wpcredits-program-manager.php
index 97b088b..752c9b7 100644
--- a/wpcredits-program-manager.php
+++ b/wpcredits-program-manager.php
@@ -46,6 +46,7 @@ require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-field-value.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-palette.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-columns.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-questions.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-diff.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-publish.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-track-definition.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tracks/class-wpcpm-tracks.php';
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-diff.php`

Expected: `bin/test-track-diff.php` ends `ALL PASS (24 checks)`. `php bin/test-roles.php` ends `ALL PASS` as well.

- [ ] **Step 5: Run everything**

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
bash bin/check-standards.sh | tail -1
```

Expected: the battery prints nothing, then a line ending `all resolve`, a line ending `US English throughout.`, a line ending `0 dead.`, and `85 warnings, no errors.` (or fewer warnings).

- [ ] **Step 6: Commit**

```bash
git add bin/test-track-diff.php includes/tracks/class-wpcpm-track-diff.php wpcredits-program-manager.php uninstall.php
git commit -m "Track Builder T3b: what changed between two copies of a track"
```

---

### Task 2: A track's revisions, for History

**Files:**
- Modify: `includes/tracks/class-wpcpm-track-store.php`
- Test: `bin/test-track-store.php`

**Interfaces:**
- Consumes: `track_post()`, `META_DEFINITION` and `WPCPM_Track_Definition::decode()` as T3a left them, and WordPress's `wp_get_post_revisions()`, which the suite stands in.
- Produces: `WPCPM_Track_Store::revisions( $post_id, $limit = 20 ): array[]`, newest first, each `id`, `at` (a Unix timestamp from `post_date_gmt`), `by` (the revision's `post_author`) and `definition` (decoded from the revision's own meta, or null when it carries none); one more entry than `$limit` when there is one, a limit below one read as one, and empty for a post that is not a track. The method goes before `others()`.

Every save is a revision (the design's decision 3.2) and the revisioned meta lives on the revision post, so this is one query and one meta read a revision. One more than asked for is read so the oldest revision History shows still has the copy before it to be compared with, and "created" is said only when there is none (decision 28). A site may cap how many revisions it keeps, which is why the published copy lives in meta of its own and not here.

The suite's `wp_update_post()` stub grows a revision model - each save adds a `revision` post from ID 100000 up, with `post_parent`, `post_author` from `get_current_user_id()` and a `post_date_gmt` - and a `wp_get_post_revisions()` stub that answers newest first and honors `posts_per_page`. Three older checks counted `$GLOBALS['posts']` to prove seeding creates nothing twice; now that a save adds a revision post they count tracks through `track_count()`, which counts `all_ids()`.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-store.php b/bin/test-track-store.php
index 70a2b83..5fa9fe5 100644
--- a/bin/test-track-store.php
+++ b/bin/test-track-store.php
@@ -26,7 +26,7 @@ class WP_Error {
 	public function get_error_data() { return $this->data; }
 }
 class WP_Post {
-	public $ID = 0, $post_type = 'post', $post_status = 'draft', $post_title = '';
+	public $ID = 0, $post_type = 'post', $post_status = 'draft', $post_title = '', $post_parent = 0, $post_author = 0, $post_date_gmt = '';
 }
 
 $GLOBALS['posts']      = array();
@@ -94,7 +94,10 @@ function wp_update_post( $postarr, $wp_error = false ) {
 			$GLOBALS['posts'][ $id ]->$field = $postarr[ $field ];
 		}
 	}
-	// The revision is taken from inside the update, from the meta the post holds right now.
+	// The revision is taken from inside the update, from the meta the post holds right now. As in
+	// WordPress, it is a post of its own, of type `revision`, with the revisioned meta copied onto
+	// it, a date of its own and the saving user as its author, so `wp_get_post_revisions()` and
+	// `get_post_meta()` on the revision's ID answer the way they do on a live site (T3b).
 	$type = $GLOBALS['posts'][ $id ]->post_type;
 	if ( post_type_supports( $type, 'revisions' ) ) {
 		$copy = array();
@@ -102,9 +105,29 @@ function wp_update_post( $postarr, $wp_error = false ) {
 			$copy[ $key ] = $GLOBALS['pmeta'][ $id ][ $key ] ?? null;
 		}
 		$GLOBALS['revisions'][ $id ][] = $copy;
+		$rev                = new WP_Post();
+		$rev->ID            = 100000 + count( $GLOBALS['posts'] );
+		$rev->post_type     = 'revision';
+		$rev->post_parent   = $id;
+		$rev->post_author   = get_current_user_id();
+		$rev->post_date_gmt = gmdate( 'Y-m-d H:i:s', 1700000000 + 60 * count( $GLOBALS['revisions'][ $id ] ) );
+		$GLOBALS['posts'][ $rev->ID ] = $rev;
+		$GLOBALS['pmeta'][ $rev->ID ] = $copy;
 	}
 	return $id;
 }
+function wp_get_post_revisions( $post_id, $args = array() ) {
+	$found = array();
+	foreach ( $GLOBALS['posts'] as $post ) {
+		if ( 'revision' === $post->post_type && (int) $post->post_parent === (int) $post_id ) {
+			$found[] = $post;
+		}
+	}
+	// Newest first, as WordPress orders them, and no more than asked for.
+	usort( $found, function ( $a, $b ) { return strcmp( $b->post_date_gmt, $a->post_date_gmt ) ?: $b->ID - $a->ID; } );
+	$limit = (int) ( $args['posts_per_page'] ?? -1 );
+	return $limit > 0 ? array_slice( $found, 0, $limit ) : $found;
+}
 function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
 function get_posts( $args = array() ) {
 	$statuses = (array) ( $args['post_status'] ?? 'publish' );
@@ -166,6 +189,11 @@ require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-store.php';
 $fails = 0;
 $total = 0;
 
+/** How many track posts there are: the posts table holds their revisions too, since T3b models them. */
+function track_count() {
+	return count( WPCPM_Track_Store::all_ids() );
+}
+
 function ck( $label, $got, $want ) {
 	global $fails, $total;
 
@@ -453,7 +481,7 @@ foreach ( $seeded as $key => $post_id ) {
 }
 ck( 'each a draft marked built-in, holding its seed', $states, array( '150h' => array( 'draft', 'builtin', 'In Sensei' ), '50h' => array( 'draft', 'builtin', 'In Sensei 50h' ), 'dev' => array( 'draft', 'builtin', 'Developer Track' ), 'design' => array( 'draft', 'builtin', 'Designer Track' ) ) );
 ck( 'the seed as shipped, byte for byte', WPCPM_Track_Store::get( $seeded['design'] ), WPCPM_Track_Store::seeds()['design'] );
-ck( 'seeding again creates nothing: each status is already held', array( WPCPM_Track_Store::seed(), count( $GLOBALS['posts'] ) ), array( array( '150h' => 0, '50h' => 0, 'dev' => 0, 'design' => 0 ), 4 ) );
+ck( 'seeding again creates nothing: each status is already held', array( WPCPM_Track_Store::seed(), track_count() ), array( array( '150h' => 0, '50h' => 0, 'dev' => 0, 'design' => 0 ), 4 ) );
 
 // The design's section 6: a built-in track its PHP still runs is read-only, so it can be
 // duplicated but not edited. `locked()` reads the status a built-in draft names now, so an edit
@@ -484,10 +512,10 @@ $GLOBALS['pmeta'] = array();
 $GLOBALS['opts']  = array();
 WPCPM_Tracks::flush();
 WPCPM_Track_Store::maybe_seed();
-ck( 'a site seeds itself once, the first time it runs this version', array( count( $GLOBALS['posts'] ), get_option( WPCPM_Track_Store::OPT_SEEDED ) ), array( 4, 1 ) );
+ck( 'a site seeds itself once, the first time it runs this version', array( track_count(), get_option( WPCPM_Track_Store::OPT_SEEDED ) ), array( 4, 1 ) );
 ck( 'and writes the index, empty and autoloaded, so no request asks for an option that is not there', array( get_option( WPCPM_Tracks::OPT_TRACKS, 'missing' ), $GLOBALS['autoload'][ WPCPM_Tracks::OPT_TRACKS ] ), array( array(), true ) );
 WPCPM_Track_Store::maybe_seed();
-ck( 'and never again', count( $GLOBALS['posts'] ), 4 );
+ck( 'and never again', track_count(), 4 );
 
 echo "\n=== Duplicating a track ===\n";
 
@@ -851,6 +879,52 @@ ck( 'and the other tracks are untouched',
     array( null !== get_post( $shared_b ), null !== get_post( $shared_c ) ), array( true, true ) );
 
 
+echo "\n=== A track's revisions, for History ===\n";
+
+$GLOBALS['posts'] = array();
+$GLOBALS['pmeta'] = array();
+$GLOBALS['opts']  = array();
+
+$hist = WPCPM_Track_Store::create( track( 'History Track', 'history' ) );
+$edit = track( 'History Track', 'history' );
+$edit['questions']['Second'] = array( 'label' => 'Second', 'type' => 'text', 'group' => 'project' );
+WPCPM_Track_Store::save( $hist, $edit );
+$edit['label'] = 'History Track, renamed';
+WPCPM_Track_Store::save( $hist, $edit );
+$edit['questions']['Third'] = array( 'label' => 'Third', 'type' => 'text', 'group' => 'wrapup' );
+WPCPM_Track_Store::save( $hist, $edit );
+
+$all = WPCPM_Track_Store::revisions( $hist );
+
+ck( 'every save is a revision, newest first, the creation the oldest',
+    array_map( function ( $r ) { return array_keys( $r['definition']['questions'] ); }, $all ),
+    array(
+        array( 'Hours', 'history notes', 'Second', 'Third' ),
+        array( 'Hours', 'history notes', 'Second' ),
+        array( 'Hours', 'history notes', 'Second' ),
+        array( 'Hours', 'history notes' ),
+    ) );
+
+ck( 'each carries the definition as it was saved, decoded',
+    array( $all[1]['definition']['label'], $all[2]['definition']['label'] ),
+    array( 'History Track, renamed', 'History Track' ) );
+
+ck( 'with who saved it and when, the times running backwards',
+    array( array_unique( array_column( $all, 'by' ) ), $all[0]['at'] > $all[1]['at'] && $all[1]['at'] > $all[2]['at'] && $all[2]['at'] > $all[3]['at'] ),
+    array( array( 7 ), true ) );
+
+ck( 'a limit reads one more than it says, so the oldest shown still has its predecessor',
+    array( count( WPCPM_Track_Store::revisions( $hist, 2 ) ), count( WPCPM_Track_Store::revisions( $hist, 3 ) ), count( WPCPM_Track_Store::revisions( $hist, 20 ) ) ),
+    array( 3, 4, 4 ) );
+
+ck( 'a limit below one reads as one',
+    count( WPCPM_Track_Store::revisions( $hist, 0 ) ), 2 );
+
+ck( 'a post that is not a track has no revisions to give',
+    array( WPCPM_Track_Store::revisions( 987654 ), WPCPM_Track_Store::revisions( $all[0]['id'] ) ),
+    array( array(), array() ) );
+
+
 printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
 
 exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-store.php`

Expected: `bin/test-track-store.php` stops with `Fatal error: Uncaught Error: Call to undefined method WPCPM_Track_Store::revisions()`. `revisions()` does not exist yet.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/tracks/class-wpcpm-track-store.php b/includes/tracks/class-wpcpm-track-store.php
index 123a8e7..31e4dcc 100644
--- a/includes/tracks/class-wpcpm-track-store.php
+++ b/includes/tracks/class-wpcpm-track-store.php
@@ -1070,6 +1070,42 @@ final class WPCPM_Track_Store {
 		return new WP_Error( 'wpcpm_track_not_equivalent', __( 'The definition is not identical to the track as its PHP runs it, so switching would change what students see.', 'wpcredits-program-manager' ), array( 'differences' => $differences ) );
 	}
 
+	/**
+	 * A track's revisions, newest first, each with the definition it carries.
+	 *
+	 * Every save is a revision (the design's decision 3.2), and the revisioned meta lives on the
+	 * revision post, so this is one query and one meta read a revision. One more than asked for
+	 * is read, so the oldest revision History shows still has the copy before it to be compared
+	 * with, and "created" is said only when there is none (decision 28). A site may cap how many
+	 * revisions it keeps, which is why the published copy lives in meta of its own and not here.
+	 *
+	 * @param int $post_id The track.
+	 * @param int $limit   How many History shows; one more comes back when there is one.
+	 * @return array[] Each `id`, `at` (a Unix timestamp), `by` (a user ID) and `definition`
+	 *                 (decoded, or null when that revision carries none); empty for a post that
+	 *                 is not a track.
+	 */
+	public static function revisions( $post_id, $limit = 20 ) {
+		$post_id = (int) $post_id;
+
+		if ( null === self::track_post( $post_id ) ) {
+			return array();
+		}
+
+		$revisions = array();
+
+		foreach ( wp_get_post_revisions( $post_id, array( 'posts_per_page' => max( 1, (int) $limit ) + 1 ) ) as $revision ) {
+			$revisions[] = array(
+				'id'         => (int) $revision->ID,
+				'at'         => (int) strtotime( (string) $revision->post_date_gmt . ' UTC' ),
+				'by'         => (int) $revision->post_author,
+				'definition' => WPCPM_Track_Definition::decode( get_post_meta( (int) $revision->ID, self::META_DEFINITION, true ) ),
+			);
+		}
+
+		return $revisions;
+	}
+
 	/**
 	 * Every other track, as the question editor's sharing index takes them.
 	 *
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-store.php`

Expected: `bin/test-track-store.php` ends `ALL PASS (160 checks)`. 

- [ ] **Step 5: Run everything**

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
bash bin/check-standards.sh | tail -1
```

Expected: the battery prints nothing, then a line ending `all resolve`, a line ending `US English throughout.`, a line ending `0 dead.`, and `85 warnings, no errors.` (or fewer warnings).

- [ ] **Step 6: Commit**

```bash
git add bin/test-track-store.php includes/tracks/class-wpcpm-track-store.php
git commit -m "Track Builder T3b: a track's revisions, for History"
```

---

### Task 3: The report form's loop and hours field, extracted, and a preview drawn through them

**Files:**
- Modify: `includes/modules/class-wpcpm-student-report-form.php`
- Test: `bin/test-report-form.php`

**Interfaces:**
- Consumes: `fields()`, `groups()`, `render_field()` and `render_body()`'s own gathering, all as they are.
- Produces: `private static render_groups( array $fields, array $values, $can, array $context )`, the group loop `render_body()` ran in place, called by `render_body()` with exactly what it gathered before; `private static render_hours_field( array $spec, $value, $can, $with_button )`, the hours box's label, input and hint from `render_hours()`, its Save button drawn only when `$can && $with_button`; and `public static render_preview( array $fields )`, which draws `<div class="wpcpm-report__body wpcpm-report__body--preview">`, then `<div class="wpcpm-hours">` around `render_hours_field( $fields['Hours'], '', true, false )` when the field set has an `Hours` question, then `<div class="wpcpm-report">` around `render_groups( $fields, array(), true, $context )` with a context of no student, no images and no files. It takes a field set as `compile_fields()` gives one, never a definition.

Decision 27's surgery on the file that draws the live Student Report Card, and the reason this task stands alone. `render_body()` gathers five things and then runs one loop over the groups; the loop becomes `render_groups()`, taking what it gathered as arguments, and the hours box's label, input and hint become `render_hours_field()` the same way. Both are byte-for-byte moves: the suite pins `render_body()`'s output for three cases by md5 taken before the extraction - a report read, a report edited, a fifty-hour track - and the pins hold after it.

The preview is those two calls with empty values, the controls enabled as a student sees them, and no form: no action, no nonce, no Save button. The suite proves it draws the same fieldsets as a body drawn from an empty record, that the hours box comes first without its button, that the enabled controls sit inside the report wrappers, and that the Designer Track's ten screenshot questions show their upload boxes with no picture and no remove form, since the image control reads the student and the images out of the context and the preview's holds none. The stub for `selected()` is new because the Designer Track's form is the first the suite draws with a select in it.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-report-form.php b/bin/test-report-form.php
index e0a0c1b..6e0c5d4 100644
--- a/bin/test-report-form.php
+++ b/bin/test-report-form.php
@@ -75,6 +75,9 @@ function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['o
 function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
 function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
 function get_transient( $k ) { return $GLOBALS['opts'][ 'T_' . $k ] ?? false; }
+// The Designer Track's select control, which the preview is the first render here to reach: core's
+// selected() prints ` selected='selected'` when the two are equal, compared as strings.
+function selected( $selected, $current = true, $echo = true ) { $out = (string) $selected === (string) $current ? " selected='selected'" : ''; if ( $echo ) { echo $out; } return $out; }
 function set_transient( $k, $v, $e = 0 ) { $GLOBALS['opts'][ 'T_' . $k ] = $v; return true; }
 function delete_transient( $k ) { unset( $GLOBALS['opts'][ 'T_' . $k ] ); return true; }
 function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
@@ -1365,6 +1368,71 @@ ck(
 	array( 'Shown' )
 );
 
+
+echo "\n=== The preview: the same loop, empty values, no form (1.106.0) ===\n";
+
+// The three renders above, byte for byte. In 1.106.0 the group loop and the hours field moved
+// out of render_body() and render_hours() into render_groups() and render_hours_field(), and
+// this pins that nothing a student sees moved with them. A markup change that is meant updates
+// these three hashes knowingly.
+ck( 'render_body() draws what it drew before the loop moved out',
+	array( md5( $read ), md5( $edit ), md5( body_50h() ) ),
+	array( '9ac719628c91cf7302c39d246d191e31', 'c814575c7d237a3ef9b25588cfa4e86e', '8e7234816827737bb6e374d6737fa154' ) );
+
+// A body drawn from a record with nothing in it, so its fieldsets can be held against the
+// preview's, which has no record at all.
+$GLOBALS['umeta'][ $sid ][ WPCPM_Students_Sync::META_RECORD_ID ] = 'recEmpty000000001';
+set_transient( 'wpcpm_report_' . md5( 'recEmpty000000001' ), array() );
+$GLOBALS['rec'] = 'recEmpty000000001';
+$blank          = body( false, true );
+$GLOBALS['rec'] = $record;
+$GLOBALS['umeta'][ $sid ][ WPCPM_Students_Sync::META_RECORD_ID ] = $record;
+
+ob_start();
+// fields() takes the track's key, the way render_body() reaches it through WPCPM_Program::track().
+WPCPM_Student_Report_Form::render_preview( WPCPM_Student_Report_Form::fields( WPCPM_Program::track( WPCPM_Program::STATUS_DEV ) ) );
+$preview = ob_get_clean();
+
+/** The fieldsets of a render, from the first `<fieldset` to the last `</fieldset>`. */
+function fieldsets( $html ) {
+	$from = strpos( $html, '<fieldset' );
+	$to   = strrpos( $html, '</fieldset>' );
+
+	return false === $from || false === $to ? '' : substr( $html, $from, $to + 11 - $from );
+}
+
+ck( 'the preview draws the same fieldsets as a report with nothing filled in',
+	array( '' !== fieldsets( $preview ), fieldsets( $preview ) === fieldsets( $blank ) ),
+	array( true, true ) );
+
+ck( 'the hours box comes first, in the hours wrapper, without its Save button',
+	array(
+		strpos( $preview, 'class="wpcpm-hours"' ) < strpos( $preview, '<fieldset' ),
+		substr_count( $preview, 'wpcpm-hours__label' ),
+		substr_count( $preview, 'Save hours' ),
+	),
+	array( true, 1, 0 ) );
+
+ck( 'and nothing in it is a form: no form, no nonce, no action, no Save button',
+	array( substr_count( $preview, '<form' ), substr_count( $preview, '_wpnonce' ), substr_count( $preview, 'name="action"' ), substr_count( $preview, 'Save my report' ) ),
+	array( 0, 0, 0, 0 ) );
+
+ck( 'the controls are drawn enabled, as a student sees them, inside the report wrappers',
+	array( substr_count( $preview, 'disabled="disabled"' ), substr_count( $preview, '<div class="wpcpm-report">' ), substr_count( $preview, 'wpcpm-report__body--preview' ) ),
+	array( 0, 1, 1 ) );
+
+ob_start();
+WPCPM_Student_Report_Form::render_preview( WPCPM_Student_Report_Form::fields( WPCPM_Program::track( WPCPM_Program::STATUS_DESIGN ) ) );
+$design = ob_get_clean();
+
+ck( 'the Designer Track\'s ten screenshot questions show their upload boxes, no picture and no remove form',
+	array( substr_count( $design, 'type="file"' ), substr_count( $design, '<img' ), substr_count( $design, 'wpcpm-report__remove' ) ),
+	array( 10, 0, 0 ) );
+
+ck( 'a field set with no hours question draws no hours box',
+	substr_count( ( function () { ob_start(); WPCPM_Student_Report_Form::render_preview( array( 'Notes' => array( 'type' => 'textarea', 'label' => 'Notes', 'group' => 'project' ) ) ); return ob_get_clean(); } )(), 'wpcpm-hours' ),
+	0 );
+
 printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
 
 
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-report-form.php`

Expected: `bin/test-report-form.php` stops with `Fatal error: Uncaught Error: Call to undefined method WPCPM_Student_Report_Form::render_preview()`. `render_preview()` does not exist yet. The three md5 pins pass already, since they hold what `render_body()` draws today, and they are what proves Step 3 moved the code without changing it.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/modules/class-wpcpm-student-report-form.php b/includes/modules/class-wpcpm-student-report-form.php
index ddd5c67..9360b43 100644
--- a/includes/modules/class-wpcpm-student-report-form.php
+++ b/includes/modules/class-wpcpm-student-report-form.php
@@ -1103,6 +1103,58 @@ class WPCPM_Student_Report_Form {
 			echo '<div class="wpcpm-report wpcpm-report--readonly">';
 		}
 
+		self::render_groups( $fields, $values, $can, $context );
+
+		if ( $can ) {
+			printf(
+				'<p class="wpcpm-report__submit"><button type="submit" class="wpcpm-button">%s</button></p>',
+				esc_html__( 'Save my report', 'wpcredits-program-manager' )
+			);
+		}
+
+		echo $can ? '</form>' : '</div>';
+
+		// The form every Remove button on the card posts through, printed here because a
+		// `<form>` inside a `<form>` is markup a browser drops: the buttons name this one by
+		// id, and each carries the field it removes as its own value. One form, one nonce,
+		// however many screenshots the track asks for.
+		//
+		// Guarded once like the report form above it and like the sponsor logo's own Remove.
+		// Remove is a delete, and a second press while the first is in flight sends a second
+		// PATCH of a cell the first one already emptied.
+		if ( $can && $has_files ) {
+			printf(
+				'<form class="wpcpm-report__remove" id="wpcpm-report-remove-%1$d" method="post" action="%2$s" data-wpcpm-once data-wpcpm-busy="%3$s">',
+				(int) $student->ID,
+				esc_url( admin_url( 'admin-post.php' ) ),
+				esc_attr__( 'Removing', 'wpcredits-program-manager' )
+			);
+
+			wp_nonce_field( self::ACTION_REMOVE_IMAGE . '_' . (int) $student->ID );
+			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_REMOVE_IMAGE ) );
+			printf( '<input type="hidden" name="student" value="%d" />', (int) $student->ID );
+
+			echo '</form>';
+		}
+
+		echo '</div>';
+	}
+
+	/**
+	 * The groups of a report form, each a fieldset of its fields, as the student's page draws them.
+	 *
+	 * The loop `render_body()` ran in place until 1.106.0, taking what it gathers - the field set,
+	 * the values, whether the reader may edit, the images and files context - as arguments, so the
+	 * Track Builder's preview can draw a draft through the very same code with empty values and no
+	 * student (the design's decision 27). `render_body()` calls it with exactly what it gathered
+	 * before, so the live Student Report Card's markup is unchanged.
+	 *
+	 * @param array $fields  The fields, column name => spec, as `fields()` gives them.
+	 * @param array $values  The student's answers, column name => value; empty for a preview.
+	 * @param bool  $can     Whether the controls are drawn editable.
+	 * @param array $context `student`, `images` and `files`, as `render_field()` reads them.
+	 */
+	private static function render_groups( array $fields, array $values, $can, array $context ) {
 		// Grouped, so the form reads as four short questions rather than twenty boxes. `hours`
 		// is skipped: it is rendered in *My course*, beside the course button, by
 		// `render_hours()` - one field, posting to this same handler.
@@ -1234,40 +1286,6 @@ class WPCPM_Student_Report_Form {
 
 			echo '</fieldset>';
 		}
-
-		if ( $can ) {
-			printf(
-				'<p class="wpcpm-report__submit"><button type="submit" class="wpcpm-button">%s</button></p>',
-				esc_html__( 'Save my report', 'wpcredits-program-manager' )
-			);
-		}
-
-		echo $can ? '</form>' : '</div>';
-
-		// The form every Remove button on the card posts through, printed here because a
-		// `<form>` inside a `<form>` is markup a browser drops: the buttons name this one by
-		// id, and each carries the field it removes as its own value. One form, one nonce,
-		// however many screenshots the track asks for.
-		//
-		// Guarded once like the report form above it and like the sponsor logo's own Remove.
-		// Remove is a delete, and a second press while the first is in flight sends a second
-		// PATCH of a cell the first one already emptied.
-		if ( $can && $has_files ) {
-			printf(
-				'<form class="wpcpm-report__remove" id="wpcpm-report-remove-%1$d" method="post" action="%2$s" data-wpcpm-once data-wpcpm-busy="%3$s">',
-				(int) $student->ID,
-				esc_url( admin_url( 'admin-post.php' ) ),
-				esc_attr__( 'Removing', 'wpcredits-program-manager' )
-			);
-
-			wp_nonce_field( self::ACTION_REMOVE_IMAGE . '_' . (int) $student->ID );
-			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_REMOVE_IMAGE ) );
-			printf( '<input type="hidden" name="student" value="%d" />', (int) $student->ID );
-
-			echo '</form>';
-		}
-
-		echo '</div>';
 	}
 
 	/**
@@ -1495,7 +1513,24 @@ class WPCPM_Student_Report_Form {
 		// left aligned.
 		$spec  = $fields['Hours'];
 		$value = isset( $values['Hours'] ) ? $values['Hours'] : '';
-		$id    = 'wpcpm-report-' . self::key( 'Hours' );
+		self::render_hours_field( $spec, $value, $can, true );
+		echo '</form>';
+	}
+
+	/**
+	 * The hours box's label, input and hint, as the student's page draws them.
+	 *
+	 * Split out of `render_hours()` in 1.106.0 for the same reason the groups were: the Track
+	 * Builder's preview draws the hours question through the same code, with no value, no form and
+	 * no Save button (the design's decision 27). `render_hours()` passes exactly what it drew before.
+	 *
+	 * @param array $spec        The `Hours` question.
+	 * @param mixed $value       The student's hours; empty for a preview.
+	 * @param bool  $can         Whether the input is drawn editable.
+	 * @param bool  $with_button Whether the Save button follows the input, which needs the form.
+	 */
+	private static function render_hours_field( array $spec, $value, $can, $with_button ) {
+		$id = 'wpcpm-report-' . self::key( 'Hours' );
 
 		// Same id scheme as render_field(), so a screen reader hears this box described the same
 		// way as every other control on the card (phase two of the type review, 1.94.6).
@@ -1521,7 +1556,7 @@ class WPCPM_Student_Report_Form {
 			empty( $spec['help'] ) ? '' : ' aria-describedby="' . esc_attr( $hint_id ) . '"'
 		);
 
-		if ( $can ) {
+		if ( $can && $with_button ) {
 			printf(
 				'<button type="submit" class="wpcpm-button">%s</button>',
 				esc_html__( 'Save hours', 'wpcredits-program-manager' )
@@ -1533,8 +1568,41 @@ class WPCPM_Student_Report_Form {
 		if ( ! empty( $spec['help'] ) ) {
 			printf( '<span class="wpcpm-field__hint" id="%1$s">%2$s</span>', esc_attr( $hint_id ), esc_html( $spec['help'] ) );
 		}
+	}
 
-		echo '</form>';
+	/**
+	 * A form drawn from a field set alone: the Track Builder's preview of a draft.
+	 *
+	 * The same code the student's page runs - `render_hours_field()` for the hours box, then
+	 * `render_groups()` for the four groups - with empty values, the controls enabled as a student
+	 * sees them, and a context holding no student, no pictures and no files, so the image control
+	 * shows its upload box and no picture. Nothing here is a form: no action, no nonce, no button,
+	 * and the wrappers are the form's classes on `div`s, so the report stylesheet applies. It takes
+	 * a field set, never a definition: the Track Builder compiles the draft first, the way
+	 * `compile()` does, and this class learns nothing about definitions (the design's decisions
+	 * 3.4 and 27).
+	 *
+	 * @param array $fields The fields, column name => spec, as `compile_fields()` gives them.
+	 */
+	public static function render_preview( array $fields ) {
+		$context = array(
+			'student' => 0,
+			'images'  => array(),
+			'files'   => array(),
+		);
+
+		echo '<div class="wpcpm-report__body wpcpm-report__body--preview">';
+
+		if ( isset( $fields['Hours'] ) && is_array( $fields['Hours'] ) ) {
+			echo '<div class="wpcpm-hours">';
+			self::render_hours_field( $fields['Hours'], '', true, false );
+			echo '</div>';
+		}
+
+		echo '<div class="wpcpm-report">';
+		self::render_groups( $fields, array(), true, $context );
+		echo '</div>';
+		echo '</div>';
 	}
 
 	/**
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-report-form.php`

Expected: `bin/test-report-form.php` ends `ALL PASS (181 checks)`. 

- [ ] **Step 5: Run everything**

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
bash bin/check-standards.sh | tail -1
```

Expected: the battery prints nothing, then a line ending `all resolve`, a line ending `US English throughout.`, a line ending `0 dead.`, and `85 warnings, no errors.` (or fewer warnings).

- [ ] **Step 6: Commit**

```bash
git add bin/test-report-form.php includes/modules/class-wpcpm-student-report-form.php
git commit -m "Track Builder T3b: the report form's loop and hours field, extracted, and a preview drawn through them"
```

---

### Task 4: A store refusal's finding names its column again

**Files:**
- Modify: `includes/tracks/class-wpcpm-track-publish.php`
- Test: `bin/test-track-publish.php`

**Interfaces:**
- Consumes: the error shape `WPCPM_Track_Definition::error()` writes: `code`, `where` (the column, or empty for the track itself) and `message`.
- Produces: the preflight's finding for a store refusal carries `column` = the error's `where`, so the publish screen names the column on the right row. The publish suite's stand-in for the store writes `where` too.

The first of decision 29's leftovers. The preflight read `$error['column']`, which nothing writes, so a store refusal about one column reached the publish screen with no column on it, and the publish suite's stand-in wrote `column` as well, which is why no check saw the slip: a check that passes against a stand-in the real code does not match is the finding class T2c's review named and T3a met twice. The stand-in is made faithful in the same step.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-publish.php b/bin/test-track-publish.php
index 0ace1ad..5d09a09 100644
--- a/bin/test-track-publish.php
+++ b/bin/test-track-publish.php
@@ -256,10 +256,23 @@ echo "\n=== What it refuses ===\n";
 
 // The definition's own rules are the store's, asked once. A screen that asked its own questions
 // would call a clash fine and Publish would refuse it on the next screen (T2b's decision 2).
-WPCPM_Track_Store::$errors = array( 7 => array( array( 'code' => 'status_taken', 'column' => '', 'message' => 'Another track already claims that status.' ) ) );
+WPCPM_Track_Store::$errors = array( 7 => array( array( 'code' => 'status_taken', 'where' => '', 'message' => 'Another track already claims that status.' ) ) );
 
 $flight = WPCPM_Track_Publish::preflight( 7 );
 
+// The store's errors carry `where`, the column or empty for the track, the key
+// `WPCPM_Track_Definition::error()` writes: a stand-in writing `column` hid a preflight that
+// read the wrong key for three releases (decision 29).
+WPCPM_Track_Store::$errors = array( 7 => array( array( 'code' => 'column_reserved', 'where' => 'Status', 'message' => 'This column belongs to the syncs.' ) ) );
+
+$named = WPCPM_Track_Publish::preflight( 7 );
+
+ck( 'a store refusal about one column names that column on the publish screen',
+    array( codes( $named['refusals'] ), $named['refusals'][0]['column'] ),
+    array( array( 'column_reserved' ), 'Status' ) );
+
+WPCPM_Track_Store::$errors = array( 7 => array( array( 'code' => 'status_taken', 'where' => '', 'message' => 'Another track already claims that status.' ) ) );
+
 ck( 'a definition the store refuses is refused here, in the store\'s own words',
     array( codes( $flight['refusals'] ), $flight['refusals'][0]['message'], $flight['ready'] ),
     array( array( 'status_taken' ), 'Another track already claims that status.', false ) );
@@ -588,7 +601,7 @@ ck( 'a claim over a lock older than the timeout succeeds, so a run a host killed
 echo "\n=== A refused preflight stops before anything is created ===\n";
 
 fresh_run();
-WPCPM_Track_Store::$errors = array( 7 => array( array( 'code' => 'status_taken', 'column' => '', 'message' => 'Another track already claims that status.' ) ) );
+WPCPM_Track_Store::$errors = array( 7 => array( array( 'code' => 'status_taken', 'where' => '', 'message' => 'Another track already claims that status.' ) ) );
 $stopped = WPCPM_Track_Publish::run( 7, 5 );
 
 ck( 'the refusal comes back with the findings, and Airtable is never asked to create anything',
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-publish.php`

Expected: `bin/test-track-publish.php` stops with `FAIL a store refusal about one column names that column on the publish screen`. The finding's `column` is empty where the check wants the column named.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/tracks/class-wpcpm-track-publish.php b/includes/tracks/class-wpcpm-track-publish.php
index 9d8ea88..9a71aca 100644
--- a/includes/tracks/class-wpcpm-track-publish.php
+++ b/includes/tracks/class-wpcpm-track-publish.php
@@ -150,7 +150,11 @@ final class WPCPM_Track_Publish {
 		foreach ( (array) WPCPM_Track_Store::check( $post_id, $definition ) as $error ) {
 			$refusals[] = self::finding(
 				isset( $error['code'] ) ? (string) $error['code'] : 'invalid',
-				isset( $error['column'] ) ? (string) $error['column'] : '',
+				// `where`: the key `WPCPM_Track_Definition::error()` writes. This read `column` until
+				// 1.106.0, which nothing writes, so a store refusal's finding on the publish screen
+				// never named its column; the publish suite's stand-in wrote `column` too, which is
+				// why no check saw it (T3a's final fix wave, deferred to T3b; decision 29).
+				isset( $error['where'] ) ? (string) $error['where'] : '',
 				isset( $error['message'] ) ? (string) $error['message'] : ''
 			);
 		}
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-publish.php`

Expected: `bin/test-track-publish.php` ends `ALL PASS (86 checks)`. 

- [ ] **Step 5: Run everything**

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
bash bin/check-standards.sh | tail -1
```

Expected: the battery prints nothing, then a line ending `all resolve`, a line ending `US English throughout.`, a line ending `0 dead.`, and `85 warnings, no errors.` (or fewer warnings).

- [ ] **Step 6: Commit**

```bash
git add bin/test-track-publish.php includes/tracks/class-wpcpm-track-publish.php
git commit -m "Track Builder T3b: a store refusal's finding names its column again"
```

---

### Task 5: A track from nothing

**Files:**
- Modify: `includes/tracks/class-wpcpm-track-palette.php` (`first_free()`)
- Modify: `includes/tools/class-wpcpm-track-builder.php` (`ACTION_NEW`, its hook in `boot()`, the route in `render_admin_page()`, `handle_new()`, `hues_in_use()`; `handle_duplicate()` takes the first free hue)
- Modify: `includes/tools/class-wpcpm-track-builder-screen.php` (`render_new()`; the New track button above the list)
- Test: `bin/test-track-builder.php`

**Interfaces:**
- Consumes: `WPCPM_Track_Store::check( 0, $definition )`, `create()`, `all_ids()` and `get()`; `WPCPM_Track_Definition::SCHEMA_VERSION`; the builder's `verify()`, `redirect_back()` and `admin_url()`.
- Produces: `WPCPM_Track_Palette::first_free( array $used ): string`, the first key of `HUES` not in `$used`, or the first key when every hue is taken. `WPCPM_Track_Builder::ACTION_NEW` (`wpcpm_track_new`) on `admin_post_`; `handle_new()` reads `wpcpm_label`, `wpcpm_status` and `wpcpm_key` through `posted_text()`, builds `schema_version`, `status`, `key`, `label`, `hue` and `questions => array()`, sends a refusal back to `?wpcpm_new=1` with the typed values under the flash's `values`, and a created track to `?wpcpm_track=<id>` with a success message that says there are no questions yet. The route `?wpcpm_new=1` draws `WPCPM_Track_Builder_Screen::render_new( array( 'url', 'flash' ) )` before any track is looked up: a form-table of Name, Airtable status and Key posting `wpcpm_track_new` with its nonce, submit "Create the track". The list draws `<a class="button" href="...&wpcpm_new=1">New track</a>` after its notice, with no tracks at all included.

Section 5's second entry point beside Duplicate. The three things a track cannot borrow are asked for, the definition is checked as a copy is - through `check( 0, ... )`, so a status or key another track holds is refused before a draft nobody asked for exists (what this plan decides, 3) - and the empty track opens for its questions. The chip takes the first hue no track holds, drafts and built-in tracks included, and `handle_duplicate()` now does the same rather than keeping its original's, which is what section 5 asked of a copy all along (what this plan decides, 4).

The builder suite stops standing in the palette and loads the real one: it has no WordPress in it, `first_free()` is under test, and its earlier stand-in's three hues would have made "every hue taken" a different rule. It loads the real `WPCPM_Track_Definition` too, since `SCHEMA_VERSION` is what the handler stamps and Task 9 sends posted questions through the real `validate()`; the definition class needs `wp_json_encode()`, which the suite now stubs. The "every hue taken" check seeds one track a hue from `WPCPM_Track_Palette::HUES` itself, so it holds if a hue is ever added.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-builder.php b/bin/test-track-builder.php
index b4ef1b6..d09c67a 100644
--- a/bin/test-track-builder.php
+++ b/bin/test-track-builder.php
@@ -57,6 +57,7 @@ function wp_enqueue_style( $handle, $src = '', $deps = array() ) { $GLOBALS['enq
 function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $footer = false ) { $GLOBALS['enqueued'][] = array( 'script', $handle, $deps, $footer ); }
 // Faithful to core's esc_js(): markup and double quotes are encoded before the quotes are
 // escaped, so a track name with a tag in it cannot break out of the attribute it sits in.
+function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
 function esc_js( $s ) { $s = htmlspecialchars( (string) $s, ENT_COMPAT ); $s = preg_replace( '/&#(x)?0*(?(1)27|39);?/i', "'", $s ); return str_replace( "\n", '\\n', addslashes( str_replace( "\r", '', $s ) ) ); }
 
 class RedirectSignal extends Exception {}
@@ -86,12 +87,6 @@ class WPCPM_Request {
 	public static function posted_verbatim_lines( $key ) { return isset( $_POST[ $key ] ) ? implode( "\n", array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $_POST[ $key ] ) ), 'strlen' ) ) : ''; }
 }
 
-class WPCPM_Track_Palette {
-	const HUES = array( 'pink', 'blue', 'green' );
-
-	public static function is_hue( $hue ) { return in_array( $hue, self::HUES, true ); }
-}
-
 $GLOBALS['can_manage'] = true;
 $GLOBALS['nonce']      = '';
 $GLOBALS['hooks']      = array();
@@ -260,6 +255,16 @@ class WPCPM_Track_Store {
 	public static $duplicated = array();
 	public static $saved      = array();
 
+	public static $created = array();
+
+	public static function create( array $definition ) {
+		self::$created[]      = $definition;
+		$new                  = 98;
+		self::$tracks[ $new ] = array( 'definition' => $definition, 'state' => 'draft', 'source' => 'definition', 'log' => array(), 'equivalence' => array( 'not_builtin' ), 'published' => null );
+
+		return $new;
+	}
+
 	public static function duplicate( $from_id, array $definition ) {
 		self::$duplicated[] = array( (int) $from_id, $definition );
 		$new                = 99;
@@ -327,6 +332,8 @@ class WPCPM_Flash {
 
 // The real rules, not a stand-in: a stand-in for WPCPM_Track_Questions would let a handler pass
 // against a rule the real class does not hold (T2c's stub-drift findings).
+require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-palette.php';
+require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';
 require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-columns.php';
 require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-questions.php';
 require_once __DIR__ . '/../includes/tools/class-wpcpm-tool.php';
@@ -1979,5 +1986,120 @@ ck( 'and so does a refusal, with the reason',
     array( $went, WPCPM_Flash::$set['track-builder']['message'] ?? '' ),
     array( 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_publish=13', 'Three students hold this status.' ) );
 
+
+echo "\n=== New track: a status, a key and a label, and an empty form (1.106.0) ===\n";
+
+$GLOBALS['hooks'] = array();
+$tool->boot();
+
+ck( 'the handler is on admin-post', in_array( 'admin_post_' . WPCPM_Track_Builder::ACTION_NEW, $GLOBALS['hooks'], true ), true );
+
+/**
+ * Press the builder's New track handler.
+ *
+ * @param array $post What the form posted.
+ * @return array `redirect` or `die`, the detail, and the flash.
+ */
+function press_new( array $post ) {
+	global $tool;
+	$_POST = $post;
+	WPCPM_Flash::$set = array();
+
+	try {
+		$tool->handle_new();
+	} catch ( RedirectSignal $e ) {
+		return array( 'redirect', $e->getMessage(), WPCPM_Flash::$set['track-builder'] ?? array() );
+	} catch ( DieSignal $e ) {
+		return array( 'die', $e->getMessage() );
+	}
+
+	return array( 'fell through' );
+}
+
+WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
+WPCPM_Track_Store::$tracks[13]['definition']['hue'] = 'pink';
+WPCPM_Track_Store::$errors  = array();
+WPCPM_Track_Store::$created = array();
+
+$GLOBALS['can_manage'] = false;
+$GLOBALS['nonce']      = '';
+$refused = press_new( array( 'wpcpm_label' => 'Blank Track' ) );
+$GLOBALS['can_manage'] = true;
+$nonce_refused = press_new( array( 'wpcpm_label' => 'Blank Track' ) );
+
+ck( 'the capability is checked before the nonce, and then the nonce',
+    array( $refused[0], $refused[1], $nonce_refused[0], $nonce_refused[1] ),
+    array( 'die', 'You do not have permission to manage the program.', 'die', 'the nonce was refused' ) );
+
+$GLOBALS['nonce'] = WPCPM_Track_Builder::ACTION_NEW;
+$made = press_new( array( 'wpcpm_label' => ' Blank Track ', 'wpcpm_status' => 'Blank Track', 'wpcpm_key' => 'blank' ) );
+
+ck( 'a new track is created from the three things it needs, with an empty form and the first hue no track holds',
+    WPCPM_Track_Store::$created,
+    array( array( 'schema_version' => 1, 'status' => 'Blank Track', 'key' => 'blank', 'label' => 'Blank Track', 'hue' => 'blue', 'questions' => array() ) ) );
+
+ck( 'and the person lands on the new track\'s page, told there are no questions yet',
+    array( $made[0], $made[1], $made[2]['status'], false !== strpos( $made[2]['message'], 'no questions yet' ) ),
+    array( 'redirect', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_track=98', 'success', true ) );
+
+WPCPM_Track_Store::$created = array();
+WPCPM_Track_Store::$errors  = array( array( 'code' => 'status_taken', 'message' => 'Another track already claims that status.' ) );
+$taken = press_new( array( 'wpcpm_label' => 'Blank Track', 'wpcpm_status' => 'Marketing Track', 'wpcpm_key' => 'blank' ) );
+WPCPM_Track_Store::$errors = array();
+
+ck( 'a status another track holds is refused through check(), nothing is created, and the form comes back with what was typed',
+    array( $taken[2]['status'], $taken[2]['message'], $taken[1], $taken[2]['values']['status'], WPCPM_Track_Store::$created ),
+    array( 'error', 'Another track already claims that status.', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_new=1', 'Marketing Track', array() ) );
+
+// Every hue the palette has, across seven tracks: the eighth gets the palette's first.
+$holding = array_keys( WPCPM_Track_Palette::HUES );
+foreach ( $holding as $i => $hue ) {
+	WPCPM_Track_Store::$tracks[ 200 + $i ] = array( 'definition' => array( 'hue' => $hue, 'questions' => array() ), 'state' => 'draft', 'source' => 'definition', 'log' => array(), 'equivalence' => array( 'not_builtin' ), 'published' => null );
+}
+WPCPM_Track_Store::$created = array();
+press_new( array( 'wpcpm_label' => 'Eighth', 'wpcpm_status' => 'Eighth', 'wpcpm_key' => 'eighth' ) );
+
+ck( 'with every hue taken, the first one, since a chip must have some color',
+    array( count( $holding ), WPCPM_Track_Store::$created[0]['hue'] ), array( 7, 'blue' ) );
+
+foreach ( $holding as $i => $hue ) {
+	unset( WPCPM_Track_Store::$tracks[ 200 + $i ] );
+}
+
+ob_start();
+WPCPM_Track_Builder_Screen::render_new( array( 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array( 'status' => 'error', 'message' => 'Another track already claims that status.', 'values' => array( 'label' => 'Blank "Track"', 'status' => 'Marketing Track', 'key' => 'blank' ) ) ) );
+$new_form = ob_get_clean();
+
+ck( 'the form asks for the three things, posts the new action with its nonce, and redraws what was typed, encoded',
+    array(
+        substr_count( $new_form, 'name="action" value="wpcpm_track_new"' ),
+        substr_count( $new_form, 'name="_wpnonce" value="wpcpm_track_new"' ),
+        false !== strpos( $new_form, 'id="wpcpm_label" name="wpcpm_label" value="Blank &quot;Track&quot;"' ),
+        false !== strpos( $new_form, 'id="wpcpm_status" name="wpcpm_status" value="Marketing Track"' ),
+        false !== strpos( $new_form, 'id="wpcpm_key" name="wpcpm_key" value="blank"' ),
+        false !== strpos( $new_form, 'Create the track' ),
+        false !== strpos( $new_form, 'Another track already claims that status.' ),
+        substr_count( $new_form, 'name="track"' ),
+    ),
+    array( 1, 1, true, true, true, true, true, 0 ) );
+
+$_GET = array( 'wpcpm_new' => 1 );
+ob_start();
+$tool->render_admin_page();
+$new_page = ob_get_clean();
+$_GET = array();
+
+ck( 'the screen routes to the form, before any track is looked up',
+    array( false !== strpos( $new_page, 'name="action" value="wpcpm_track_new"' ), false === strpos( $new_page, '<table class="widefat striped wpcpm-tracks">' ) ),
+    array( true, true ) );
+
+ob_start();
+WPCPM_Track_Builder_Screen::render_list( array( 'rows' => array(), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
+$empty_list = ob_get_clean();
+
+ck( 'New track is offered above the list, even with no tracks at all',
+    array( substr_count( $empty_list, 'wpcpm_new=1">New track</a>' ), false !== strpos( $empty_list, 'No tracks yet' ) ),
+    array( 1, true ) );
+
 printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
 exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-builder.php`

Expected: `bin/test-track-builder.php` stops with `Fatal error: Uncaught Error: Undefined constant WPCPM_Track_Builder::ACTION_NEW`. `ACTION_NEW` does not exist yet.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/tools/class-wpcpm-track-builder-screen.php b/includes/tools/class-wpcpm-track-builder-screen.php
index 09f6b90..95d7aaf 100644
--- a/includes/tools/class-wpcpm-track-builder-screen.php
+++ b/includes/tools/class-wpcpm-track-builder-screen.php
@@ -32,6 +32,16 @@ final class WPCPM_Track_Builder_Screen {
 
 		self::render_notice( $flash );
 
+		// Above the table, and there with no tracks at all: New track is the way in that needs
+		// nothing to copy (the design's section 5).
+		if ( '' !== $url ) {
+			printf(
+				'<p><a class="button" href="%1$s">%2$s</a></p>',
+				esc_url( add_query_arg( 'wpcpm_new', 1, $url ) ),
+				esc_html__( 'New track', 'wpcredits-program-manager' )
+			);
+		}
+
 		if ( empty( $rows ) ) {
 			echo '<p>' . esc_html__( 'No tracks yet. The four the program runs today appear here the first time this site loads after the update.', 'wpcredits-program-manager' ) . '</p>';
 
@@ -621,6 +631,47 @@ final class WPCPM_Track_Builder_Screen {
 		);
 	}
 
+	/**
+	 * A track being started from nothing: its name, its Airtable status and its key (the design's 5).
+	 *
+	 * @param array $args The screen's `url`, and the `flash` the last press left.
+	 */
+	public static function render_new( array $args ) {
+		$url   = isset( $args['url'] ) ? (string) $args['url'] : '';
+		$flash = isset( $args['flash'] ) && is_array( $args['flash'] ) ? $args['flash'] : array();
+		$typed = isset( $flash['values'] ) && is_array( $flash['values'] ) ? $flash['values'] : array();
+
+		self::render_notice( $flash );
+
+		printf( '<p><a href="%1$s">%2$s</a></p>', esc_url( $url ), esc_html__( 'Back to every track', 'wpcredits-program-manager' ) );
+
+		echo '<p>' . esc_html__( 'A track from nothing: its name, its Airtable status and its key. Everything else about it, and every question, is edited on the page that opens next.', 'wpcredits-program-manager' ) . '</p>';
+
+		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
+		wp_nonce_field( WPCPM_Track_Builder::ACTION_NEW );
+		echo '<input type="hidden" name="action" value="' . esc_attr( WPCPM_Track_Builder::ACTION_NEW ) . '" />';
+
+		echo '<table class="form-table" role="presentation"><tbody>';
+
+		foreach ( array(
+			'label'  => __( 'Name', 'wpcredits-program-manager' ),
+			'status' => __( 'Airtable status', 'wpcredits-program-manager' ),
+			'key'    => __( 'Key', 'wpcredits-program-manager' ),
+		) as $field => $heading ) {
+			printf(
+				'<tr><th scope="row"><label for="wpcpm_%1$s">%2$s</label></th><td><input type="text" class="regular-text" id="wpcpm_%1$s" name="wpcpm_%1$s" value="%3$s" /></td></tr>',
+				esc_attr( $field ),
+				esc_html( $heading ),
+				esc_attr( array_key_exists( $field, $typed ) ? (string) $typed[ $field ] : '' )
+			);
+		}
+
+		echo '</tbody></table>';
+
+		printf( '<p class="submit"><button type="submit" class="button button-primary">%s</button></p>', esc_html__( 'Create the track', 'wpcredits-program-manager' ) );
+		echo '</form>';
+	}
+
 	/**
 	 * A copy being started: the three things it cannot share with the track it comes from.
 	 *
diff --git a/includes/tools/class-wpcpm-track-builder.php b/includes/tools/class-wpcpm-track-builder.php
index 1c58036..286e6b0 100644
--- a/includes/tools/class-wpcpm-track-builder.php
+++ b/includes/tools/class-wpcpm-track-builder.php
@@ -26,6 +26,9 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 	/** Copy a track into a new one. */
 	const ACTION_DUPLICATE = 'wpcpm_track_duplicate';
 
+	/** Start a track from nothing. */
+	const ACTION_NEW = 'wpcpm_track_new';
+
 	/** Run a built-in track from its definition. */
 	const ACTION_SWITCH_DEFINITION = 'wpcpm_track_switch_definition';
 
@@ -126,6 +129,7 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 	public function boot() {
 		add_action( 'admin_post_' . self::ACTION_SAVE, array( $this, 'handle_save' ) );
 		add_action( 'admin_post_' . self::ACTION_DUPLICATE, array( $this, 'handle_duplicate' ) );
+		add_action( 'admin_post_' . self::ACTION_NEW, array( $this, 'handle_new' ) );
 		add_action( 'admin_post_' . self::ACTION_SWITCH_DEFINITION, array( $this, 'handle_switch_definition' ) );
 		add_action( 'admin_post_' . self::ACTION_SWITCH_BUILTIN, array( $this, 'handle_switch_builtin' ) );
 		add_action( 'admin_post_' . self::ACTION_REFRESH, array( $this, 'handle_refresh' ) );
@@ -378,6 +382,19 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		echo '<div class="wrap wpcpm-wrap">';
 		echo '<h1>' . esc_html( $this->label() ) . '</h1>';
 
+		if ( 1 === WPCPM_Request::id( 'wpcpm_new' ) ) {
+			WPCPM_Track_Builder_Screen::render_new(
+				array(
+					'url'   => $this->admin_url(),
+					'flash' => $flash,
+				)
+			);
+
+			echo '</div>';
+
+			return;
+		}
+
 		if ( $duplicate > 0 && is_array( WPCPM_Track_Store::get( $duplicate ) ) ) {
 			WPCPM_Track_Builder_Screen::render_duplicate(
 				array(
@@ -525,6 +542,10 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		$definition['status'] = WPCPM_Request::posted_text( 'wpcpm_status' );
 		$definition['key']    = WPCPM_Request::posted_text( 'wpcpm_key' );
 
+		// The first hue no track holds (the design's section 5): a copy that kept its original's
+		// would give two tracks one chip color, which decision 10 exists to avoid. T2b's copy kept it.
+		$definition['hue'] = WPCPM_Track_Palette::first_free( self::hues_in_use() );
+
 		// Checked as a track with nothing locked to it, which is what a copy is, so a status
 		// another track already holds is refused before a draft nobody asked for exists.
 		$errors = WPCPM_Track_Store::check( 0, $definition );
@@ -562,6 +583,79 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		);
 	}
 
+	/**
+	 * Start a track from nothing: a status, a key and a label, and an empty form (the design's 5).
+	 *
+	 * Checked as a track with nothing locked to it, as a copy is, so a status or key another track
+	 * holds is refused before a draft nobody asked for exists. Everything else about the track, and
+	 * every question, is edited on the track's page this opens.
+	 */
+	public function handle_new() {
+		$this->verify( self::ACTION_NEW );
+
+		$definition = array(
+			'schema_version' => WPCPM_Track_Definition::SCHEMA_VERSION,
+			'status'         => WPCPM_Request::posted_text( 'wpcpm_status' ),
+			'key'            => WPCPM_Request::posted_text( 'wpcpm_key' ),
+			'label'          => WPCPM_Request::posted_text( 'wpcpm_label' ),
+			'hue'            => WPCPM_Track_Palette::first_free( self::hues_in_use() ),
+			'questions'      => array(),
+		);
+
+		$errors = WPCPM_Track_Store::check( 0, $definition );
+
+		if ( array() !== $errors ) {
+			$this->redirect_back(
+				array(
+					'status'  => 'error',
+					'message' => (string) $errors[0]['message'],
+					'values'  => $definition,
+				),
+				array( 'wpcpm_new' => 1 )
+			);
+		}
+
+		$created = WPCPM_Track_Store::create( $definition );
+
+		if ( is_wp_error( $created ) ) {
+			$this->redirect_back(
+				array(
+					'status'  => 'error',
+					'message' => $created->get_error_message(),
+					'values'  => $definition,
+				),
+				array( 'wpcpm_new' => 1 )
+			);
+		}
+
+		$this->redirect_back(
+			array(
+				'status'  => 'success',
+				'message' => __( 'The track was created, as a draft with no questions yet. Add them below. Nothing reaches students until it is published.', 'wpcredits-program-manager' ),
+			),
+			array( 'wpcpm_track' => (int) $created )
+		);
+	}
+
+	/**
+	 * The hue every track holds, drafts included, so a new one can take the first that is free.
+	 *
+	 * @return string[]
+	 */
+	private static function hues_in_use() {
+		$hues = array();
+
+		foreach ( WPCPM_Track_Store::all_ids() as $post_id ) {
+			$definition = WPCPM_Track_Store::get( $post_id );
+
+			if ( is_array( $definition ) && isset( $definition['hue'] ) ) {
+				$hues[] = (string) $definition['hue'];
+			}
+		}
+
+		return $hues;
+	}
+
 	/**
 	 * The posted properties, on top of the definition the track holds.
 	 *
diff --git a/includes/tracks/class-wpcpm-track-palette.php b/includes/tracks/class-wpcpm-track-palette.php
index 0477ef7..f838ccf 100644
--- a/includes/tracks/class-wpcpm-track-palette.php
+++ b/includes/tracks/class-wpcpm-track-palette.php
@@ -59,6 +59,27 @@ final class WPCPM_Track_Palette {
 		return is_string( $hue ) && array_key_exists( $hue, self::HUES );
 	}
 
+	/**
+	 * The first hue none of the given tracks holds, so a new track's chip reads unlike its neighbors.
+	 *
+	 * The design's section 5 gives a duplicate this rule and 1.106.0 gives a blank track the same;
+	 * with every hue taken, the first one, since a chip must have some color (decision 10).
+	 *
+	 * @param string[] $used The hues the existing tracks hold.
+	 * @return string
+	 */
+	public static function first_free( array $used ) {
+		$used = array_map( 'strval', $used );
+
+		foreach ( array_keys( self::HUES ) as $hue ) {
+			if ( ! in_array( $hue, $used, true ) ) {
+				return $hue;
+			}
+		}
+
+		return (string) key( self::HUES );
+	}
+
 	/**
 	 * The chip rule for one track, in the shape `dashboard.css` gives its own chips.
 	 *
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-builder.php`

Expected: `bin/test-track-builder.php` ends `ALL PASS (186 checks)`. 

- [ ] **Step 5: Run everything**

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
bash bin/check-standards.sh | tail -1
```

Expected: the battery prints nothing, then a line ending `all resolve`, a line ending `US English throughout.`, a line ending `0 dead.`, and `85 warnings, no errors.` (or fewer warnings).

- [ ] **Step 6: Commit**

```bash
git add bin/test-track-builder.php includes/tools/class-wpcpm-track-builder-screen.php includes/tools/class-wpcpm-track-builder.php includes/tracks/class-wpcpm-track-palette.php
git commit -m "Track Builder T3b: a track from nothing"
```

---

### Task 6: Preview

**Files:**
- Modify: `includes/tools/class-wpcpm-track-builder.php` (`enqueue_assets()` adds the report's sheet on the preview route; `preview()`; the route)
- Modify: `includes/tools/class-wpcpm-track-builder-screen.php` (`render_preview()` and `preview_line()`; Preview on every row and on the track's page)
- Modify: `assets/css/track-builder.css`
- Test: `bin/test-track-builder.php`

**Interfaces:**
- Consumes: Task 3's `WPCPM_Student_Report_Form::render_preview()`, `WPCPM_Track_Definition::compile_fields()`, `WPCPM_Call_Calendar::register_assets()` and `WPCPM_Call_Calendar::STYLE` (`wpcpm-call-calendar`), `WPCPM_Track_Store::state()` and `source()`.
- Produces: `WPCPM_Track_Builder::preview( $post_id ): array` with `track`, `label`, `fields` (the draft compiled as `compile()` compiles it), `state` and `source`; the route `?wpcpm_preview=<id>`, before the publish route, falling through to the list for a track that does not exist; on that route `enqueue_assets()` calls `WPCPM_Call_Calendar::register_assets()` and enqueues `wpcpm-call-calendar` after its own sheet and script. `WPCPM_Track_Builder_Screen::render_preview( array $args )` draws `<h2>Previewing %s</h2>`, Back to the track and Back to every track, a `<p class="wpcpm-tracks__preview-note">` saying nothing typed is kept followed by the state line, then `<div class="wpcpm-dashboard wpcpm-tracks__preview">` around the renderer's output, or one sentence for a track with no questions. `>Preview</a>` is on every row after Duplicate, and beside Back to every track on the track's page.

Decision 27 on the screen. The builder compiles the draft and hands the screen a field set (what this plan decides, 1); the screen hands it to the report form's own renderer inside the sheet that dresses the form on the student's page, so the preview is faithful to the form's structure rather than to the theme's pixels. That sheet is the calendar module's, `assets/css/calendar.css`, which is where the report form's rules live and what the institution's student view enqueues when it draws a Student Report Card; it depends on `dashboard.css`, which sets its tokens on `.wpcpm-dashboard`, so the wrapper carries that class (what this plan decides, 2). Neither sheet has an unscoped selector, so nothing else in wp-admin changes.

The sentence under the heading says the preview is the form as a student sees it and nothing typed is kept, then what students have against what is drawn: nothing yet for a draft, the same form for a published track, the published copy for one with unpublished changes, the trash for a trashed one, and for a built-in track still running from its PHP, that its definition is what that form draws. A track with no questions says so instead of drawing an empty form.

The suite stands in the calendar module (a counter for `register_assets()`) and the report form (a marker holding the field count, and a record of the field names it was handed), and proves the route, the sheet, the five sentences, the empty case, the fall-through and the two links.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-builder.php b/bin/test-track-builder.php
index d09c67a..aae6dea 100644
--- a/bin/test-track-builder.php
+++ b/bin/test-track-builder.php
@@ -305,6 +305,25 @@ class WPCPM_Tracks {
 	const OPT_TRACKS = 'wpcpm_tracks';
 }
 
+/** The calendar module, which owns the sheet that dresses the Student Report Card. */
+class WPCPM_Call_Calendar {
+	const STYLE = 'wpcpm-call-calendar';
+
+	public static $registered = 0;
+
+	public static function register_assets() { ++self::$registered; }
+}
+
+/** The report form, stood in: what the preview handed it, and a marker where it drew. */
+class WPCPM_Student_Report_Form {
+	public static $previewed = array();
+
+	public static function render_preview( array $fields ) {
+		self::$previewed[] = array_keys( $fields );
+		echo '<div class="wpcpm-report__body wpcpm-report__body--preview">' . count( $fields ) . ' fields</div>';
+	}
+}
+
 /** The students sync, for how many people are on a track now. */
 class WPCPM_Students_Sync {
 	public static $counts = array();
@@ -2101,5 +2120,114 @@ ck( 'New track is offered above the list, even with no tracks at all',
     array( substr_count( $empty_list, 'wpcpm_new=1">New track</a>' ), false !== strpos( $empty_list, 'No tracks yet' ) ),
     array( 1, true ) );
 
+
+echo "\n=== Preview: the draft through the report form's own renderer (1.106.0) ===\n";
+
+WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
+WPCPM_Track_Store::$tracks[11] = array(
+	'definition'  => array( 'key' => '150h', 'status' => 'In Sensei', 'label' => '150-hour Track', 'questions' => array( 'Hours' => array( 'type' => 'number', 'label' => 'Hours', 'group' => 'hours', 'airtable_type' => 'number' ) ) ),
+	'state'       => 'published',
+	'source'      => 'builtin',
+	'log'         => array(),
+	'equivalence' => array(),
+	'published'   => array( 'key' => '150h' ),
+);
+
+ck( 'the builder hands the view the draft compiled as the live site compiles it: the authoring properties gone, the rest as stored',
+    WPCPM_Track_Builder::preview( 13 ),
+    array(
+        'track'  => 13,
+        'label'  => 'Marketing Track',
+        'fields' => array(
+            'Hours'      => array( 'type' => 'number', 'label' => 'Hours', 'group' => 'hours', 'min' => 0, 'max' => 1000, 'step' => 1 ),
+            'Slack name' => array( 'type' => 'text', 'label' => 'Your Slack name', 'group' => 'onboarding' ),
+            'Your blog'  => array( 'type' => 'url', 'label' => 'Your blog', 'group' => 'onboarding' ),
+        ),
+        'state'  => 'draft',
+        'source' => 'definition',
+    ) );
+
+$GLOBALS['enqueued'] = array();
+WPCPM_Call_Calendar::$registered = 0;
+$_GET = array( 'wpcpm_preview' => 13 );
+$tool->enqueue_assets( 'wpcredits-program_page_wpcpm-tool-track-builder' );
+
+ck( 'on the preview route the screen also enqueues the sheet that dresses the Student Report Card, registered first',
+    array( $GLOBALS['enqueued'][2] ?? null, count( $GLOBALS['enqueued'] ), WPCPM_Call_Calendar::$registered ),
+    array( array( 'style', 'wpcpm-call-calendar', array() ), 3, 1 ) );
+
+WPCPM_Student_Report_Form::$previewed = array();
+ob_start();
+$tool->render_admin_page();
+$preview_page = ob_get_clean();
+
+ck( 'the route draws the heading, the two ways back, the sentence that nothing is kept, the state, and the renderer\'s output inside the tokened wrapper',
+    array(
+        false !== strpos( $preview_page, '<h2>Previewing Marketing Track</h2>' ),
+        substr_count( $preview_page, 'wpcpm_track=13">Back to the track</a> <a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder">Back to every track</a>' ),
+        false !== strpos( $preview_page, 'Nothing typed here is kept: there is no Save button and no form behind the controls. Nothing reaches students until the track is published.' ),
+        substr_count( $preview_page, '<div class="wpcpm-dashboard wpcpm-tracks__preview"><div class="wpcpm-report__body wpcpm-report__body--preview">3 fields</div></div>' ),
+        WPCPM_Student_Report_Form::$previewed,
+        substr_count( $preview_page, '<form' ),
+        substr_count( $preview_page, '_wpnonce' ),
+        false !== strpos( $preview_page, '<table class="widefat striped wpcpm-tracks">' ),
+    ),
+    array( true, 1, true, 1, array( array( 'Hours', 'Slack name', 'Your blog' ) ), 0, 0, false ) );
+
+$_GET = array( 'wpcpm_preview' => 11 );
+ob_start();
+$tool->render_admin_page();
+$builtin_preview = ob_get_clean();
+
+ck( 'a built-in track still running from its PHP previews too, and the sentence says its definition is what that form draws',
+    array(
+        false !== strpos( $builtin_preview, '<h2>Previewing 150-hour Track</h2>' ),
+        false !== strpos( $builtin_preview, 'This track runs from its hand-written form, and this definition is what that form draws.' ),
+        substr_count( $builtin_preview, '1 fields</div>' ),
+    ),
+    array( true, true, 1 ) );
+
+foreach ( array( 'published' => 'so this is the form students on the track have', 'changed' => 'this draft has changes they do not see yet', 'trash' => 'This track is in the trash.' ) as $state => $said ) {
+	WPCPM_Track_Store::$tracks[13]['state'] = $state;
+	$_GET = array( 'wpcpm_preview' => 13 );
+	ob_start();
+	$tool->render_admin_page();
+	$states[ $state ] = false !== strpos( ob_get_clean(), $said );
+}
+WPCPM_Track_Store::$tracks[13]['state'] = 'draft';
+
+ck( 'and a published, a changed and a trashed track each say what students have against what is drawn',
+    $states, array( 'published' => true, 'changed' => true, 'trash' => true ) );
+
+WPCPM_Track_Store::$tracks[13]['definition']['questions'] = array();
+WPCPM_Student_Report_Form::$previewed = array();
+ob_start();
+$tool->render_admin_page();
+$empty_preview = ob_get_clean();
+WPCPM_Track_Store::$tracks[13] = editable_track();
+
+ck( 'a track with no questions yet says so instead of drawing an empty form',
+    array( false !== strpos( $empty_preview, 'This track has no questions yet, so there is nothing to draw.' ), WPCPM_Student_Report_Form::$previewed, substr_count( $empty_preview, 'wpcpm-tracks__preview"' ) ),
+    array( true, array(), 0 ) );
+
+$_GET = array( 'wpcpm_preview' => 999 );
+ob_start();
+$tool->render_admin_page();
+$no_such = ob_get_clean();
+$_GET = array();
+
+ck( 'a track that does not exist falls through to the list', false !== strpos( $no_such, '<table class="widefat striped wpcpm-tracks">' ), true );
+
+ob_start();
+WPCPM_Track_Builder_Screen::render_list( array( 'rows' => WPCPM_Track_Builder::rows(), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
+$rows_html = ob_get_clean();
+ob_start();
+WPCPM_Track_Builder_Screen::render_form( array( 'form' => WPCPM_Track_Builder::form( 13 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
+$track_page = ob_get_clean();
+
+ck( 'Preview is offered on every row, the built-in one included, and beside the way back on the track\'s page',
+    array( substr_count( $rows_html, '>Preview</a>' ), substr_count( $rows_html, 'wpcpm_preview=11">Preview</a>' ), substr_count( $track_page, 'Back to every track</a> <a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_preview=13">Preview</a>' ) ),
+    array( 2, 1, 1 ) );
+
 printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
 exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-builder.php`

Expected: `bin/test-track-builder.php` stops with `Fatal error: Uncaught Error: Call to undefined method WPCPM_Track_Builder::preview()`. `preview()` does not exist yet.

- [ ] **Step 3: Write the code**

```diff
diff --git a/assets/css/track-builder.css b/assets/css/track-builder.css
index 8e61b15..caff1b0 100644
--- a/assets/css/track-builder.css
+++ b/assets/css/track-builder.css
@@ -154,3 +154,10 @@
 .wpcpm-question__locked {
 	color: #50575e;
 }
+
+/* Preview (1.106.0): the draft's form through the report form's own renderer and the sheet that
+   lays it out for the student's page, held here to about that page's width rather than wp-admin's. */
+.wpcpm-tracks__preview {
+	max-width: 60rem;
+	margin-top: 1.5rem;
+}
diff --git a/includes/tools/class-wpcpm-track-builder-screen.php b/includes/tools/class-wpcpm-track-builder-screen.php
index 95d7aaf..ffbbbcb 100644
--- a/includes/tools/class-wpcpm-track-builder-screen.php
+++ b/includes/tools/class-wpcpm-track-builder-screen.php
@@ -136,6 +136,15 @@ final class WPCPM_Track_Builder_Screen {
 			);
 		}
 
+		// Preview draws any track, a built-in one included: its definition is what its PHP draws.
+		if ( '' !== $url ) {
+			printf(
+				'<a href="%1$s">%2$s</a> ',
+				esc_url( add_query_arg( 'wpcpm_preview', (int) $row['id'], $url ) ),
+				esc_html__( 'Preview', 'wpcredits-program-manager' )
+			);
+		}
+
 		// Publishing is a screen of its own: it has a preflight to read, a checklist to work
 		// through and, when a column is missing, a list to take to Airtable. A trashed track is
 		// not offered it, because the store refuses to publish out of the trash.
@@ -230,6 +239,87 @@ final class WPCPM_Track_Builder_Screen {
 		self::render_publish_actions( $flight, $state, $track, $can_make );
 	}
 
+	/**
+	 * The draft's form as a student sees it: empty answers, the controls enabled, nothing to press.
+	 *
+	 * Drawn through the report form's own renderer, inside the sheet that dresses it on the
+	 * student's page, so the preview is faithful to the form's structure rather than to the theme's
+	 * pixels (the design's decision 27). The wrapper carries `wpcpm-dashboard` because that is the
+	 * element the plugin's stylesheet sets its tokens on; without it the form would draw untokened.
+	 *
+	 * @param array $args `track`, `label`, `fields` and `state` and `source` as
+	 *                    `WPCPM_Track_Builder::preview()` gives them, the screen's `url`, and the
+	 *                    `flash` the last press left.
+	 */
+	public static function render_preview( array $args ) {
+		$track  = isset( $args['track'] ) ? (int) $args['track'] : 0;
+		$label  = isset( $args['label'] ) ? (string) $args['label'] : '';
+		$fields = isset( $args['fields'] ) && is_array( $args['fields'] ) ? $args['fields'] : array();
+		$state  = isset( $args['state'] ) ? (string) $args['state'] : '';
+		$source = isset( $args['source'] ) ? (string) $args['source'] : '';
+		$url    = isset( $args['url'] ) ? (string) $args['url'] : '';
+
+		self::render_notice( isset( $args['flash'] ) && is_array( $args['flash'] ) ? $args['flash'] : array() );
+
+		echo '<h2>';
+		printf(
+			/* translators: %s: the track's name. */
+			esc_html__( 'Previewing %s', 'wpcredits-program-manager' ),
+			esc_html( $label )
+		);
+		echo '</h2>';
+
+		if ( '' !== $url ) {
+			printf(
+				'<p><a href="%1$s">%2$s</a> <a href="%3$s">%4$s</a></p>',
+				esc_url( add_query_arg( 'wpcpm_track', $track, $url ) ),
+				esc_html__( 'Back to the track', 'wpcredits-program-manager' ),
+				esc_url( $url ),
+				esc_html__( 'Back to every track', 'wpcredits-program-manager' )
+			);
+		}
+
+		echo '<p class="wpcpm-tracks__preview-note">';
+		echo esc_html__( 'The form as a student sees it, with empty answers and no student\'s record. Nothing typed here is kept: there is no Save button and no form behind the controls.', 'wpcredits-program-manager' );
+		echo ' ';
+		echo esc_html( self::preview_line( $state, $source ) );
+		echo '</p>';
+
+		if ( array() === $fields ) {
+			echo '<p>' . esc_html__( 'This track has no questions yet, so there is nothing to draw.', 'wpcredits-program-manager' ) . '</p>';
+
+			return;
+		}
+
+		echo '<div class="wpcpm-dashboard wpcpm-tracks__preview">';
+		WPCPM_Student_Report_Form::render_preview( $fields );
+		echo '</div>';
+	}
+
+	/**
+	 * What the preview is a preview of, against what students have now.
+	 *
+	 * @param string $state  The track's state.
+	 * @param string $source `builtin` while a built-in track runs from its PHP, else `definition`.
+	 * @return string
+	 */
+	private static function preview_line( $state, $source ) {
+		if ( 'builtin' === $source ) {
+			return __( 'This track runs from its hand-written form, and this definition is what that form draws.', 'wpcredits-program-manager' );
+		}
+
+		switch ( $state ) {
+			case 'published':
+				return __( 'The published copy is the same as this draft, so this is the form students on the track have.', 'wpcredits-program-manager' );
+			case 'changed':
+				return __( 'Students on the track still have the published copy; this draft has changes they do not see yet.', 'wpcredits-program-manager' );
+			case 'trash':
+				return __( 'This track is in the trash.', 'wpcredits-program-manager' );
+		}
+
+		return __( 'Nothing reaches students until the track is published.', 'wpcredits-program-manager' );
+	}
+
 	/**
 	 * What the preflight refused and what it only warned about.
 	 *
@@ -563,7 +653,13 @@ final class WPCPM_Track_Builder_Screen {
 
 		self::render_notice( $flash );
 
-		printf( '<p><a href="%1$s">%2$s</a></p>', esc_url( $url ), esc_html__( 'Back to every track', 'wpcredits-program-manager' ) );
+		printf(
+			'<p><a href="%1$s">%2$s</a> <a href="%3$s">%4$s</a></p>',
+			esc_url( $url ),
+			esc_html__( 'Back to every track', 'wpcredits-program-manager' ),
+			esc_url( add_query_arg( 'wpcpm_preview', isset( $form['id'] ) ? (int) $form['id'] : 0, $url ) ),
+			esc_html__( 'Preview', 'wpcredits-program-manager' )
+		);
 
 		if ( ! empty( $form['read_only'] ) ) {
 			echo '<p class="wpcpm-tracks__readonly">' . esc_html__( 'This track runs from its hand-written form, so it cannot be edited here. Duplicate it to start a track of your own, or switch it to its definition first.', 'wpcredits-program-manager' ) . '</p>';
diff --git a/includes/tools/class-wpcpm-track-builder.php b/includes/tools/class-wpcpm-track-builder.php
index 286e6b0..a110146 100644
--- a/includes/tools/class-wpcpm-track-builder.php
+++ b/includes/tools/class-wpcpm-track-builder.php
@@ -160,6 +160,15 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		// The question list's arrows move a row in place and post in the background; without the
 		// script the same forms post the ordinary way (the design's section 6).
 		wp_enqueue_script( 'wpcpm-track-editor', WPCPM_PLUGIN_URL . 'assets/js/track-editor.js', array(), WPCPM_VERSION, true );
+
+		// The preview draws the draft through the report form's own renderer, so it needs the sheet
+		// that dresses the form on the student's page, which nothing in wp-admin enqueues otherwise
+		// (decision 27). Registered first, as the calendar module does on `init`, so the handle
+		// resolves whichever order the modules booted in.
+		if ( WPCPM_Request::id( 'wpcpm_preview' ) > 0 ) {
+			WPCPM_Call_Calendar::register_assets();
+			wp_enqueue_style( WPCPM_Call_Calendar::STYLE );
+		}
 	}
 
 	/**
@@ -261,6 +270,30 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		);
 	}
 
+	/**
+	 * What the preview draws: the draft compiled as the live site would compile it (decision 27).
+	 *
+	 * `compile_fields()` is the call `compile()` makes, so the preview and the published form come
+	 * from the same reading of the definition, with the same authoring properties left out. A
+	 * built-in track still running from its PHP previews too: its definition is what the PHP draws.
+	 *
+	 * @param int $post_id The track.
+	 * @return array `track`, `label`, `fields` (column => spec), `state` and `source`.
+	 */
+	public static function preview( $post_id ) {
+		$post_id    = (int) $post_id;
+		$definition = WPCPM_Track_Store::get( $post_id );
+		$definition = is_array( $definition ) ? $definition : array();
+
+		return array(
+			'track'  => $post_id,
+			'label'  => isset( $definition['label'] ) ? (string) $definition['label'] : '',
+			'fields' => WPCPM_Track_Definition::compile_fields( $definition ),
+			'state'  => WPCPM_Track_Store::state( $post_id ),
+			'source' => WPCPM_Track_Store::source( $post_id ),
+		);
+	}
+
 	/**
 	 * What publishing would create in the base, off the cached reading (decision 24).
 	 *
@@ -409,6 +442,21 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 			return;
 		}
 
+		$preview = WPCPM_Request::id( 'wpcpm_preview' );
+
+		if ( $preview > 0 && is_array( WPCPM_Track_Store::get( $preview ) ) ) {
+			WPCPM_Track_Builder_Screen::render_preview(
+				self::preview( $preview ) + array(
+					'url'   => $this->admin_url(),
+					'flash' => $flash,
+				)
+			);
+
+			echo '</div>';
+
+			return;
+		}
+
 		$publish = WPCPM_Request::id( 'wpcpm_publish' );
 
 		if ( $publish > 0 && is_array( WPCPM_Track_Store::get( $publish ) ) ) {
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-builder.php`

Expected: `bin/test-track-builder.php` ends `ALL PASS (194 checks)`. 

- [ ] **Step 5: Run everything**

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
bash bin/check-standards.sh | tail -1
```

Expected: the battery prints nothing, then a line ending `all resolve`, a line ending `US English throughout.`, a line ending `0 dead.`, and `85 warnings, no errors.` (or fewer warnings).

- [ ] **Step 6: Commit**

```bash
git add assets/css/track-builder.css bin/test-track-builder.php includes/tools/class-wpcpm-track-builder-screen.php includes/tools/class-wpcpm-track-builder.php
git commit -m "Track Builder T3b: Preview"
```

---

### Task 7: History

**Files:**
- Create: `includes/tools/class-wpcpm-track-history-screen.php`
- Modify: `includes/tools/class-wpcpm-track-builder.php` (`HISTORY_LIMIT`, `history()`, the route)
- Modify: `includes/tools/class-wpcpm-track-builder-screen.php` (`track_labels()`, which `render_form()` and `render_new()` read; History on every row and on the track's page)
- Modify: `includes/tools/class-wpcpm-track-editor-screen.php` (`property_labels()`, which `render_question()` and the add form read)
- Modify: `assets/css/track-builder.css`, `wpcredits-program-manager.php`, `uninstall.php` (the new class, after `class-wpcpm-track-editor-screen.php`)
- Test: `bin/test-track-builder.php` (loads the real `WPCPM_Track_Diff` and the new screen)

**Interfaces:**
- Consumes: Task 1's `between()`, Task 2's `revisions()`, `WPCPM_Track_Store::published()` and `log_entries()` (oldest first, each `at`, `by`, `did` and an optional `detail`), `WPCPM_Track_Publish::checklist()` (item => `label`, ...).
- Produces: `WPCPM_Track_Builder::HISTORY_LIMIT` (20) and `history( $post_id ): array` with `track`, `label`, `pending` (`between( published, draft )`, or null when nothing was published), `revisions` (at most 20, newest first, each `at`, `by`, `diff` against the one before or `created` with the question count when there is none, both null for a save that kept no definition), `more` (whether older saves exist than are shown), `log` (newest first) and `items` (checklist item => its label); the route `?wpcpm_history=<id>`. `WPCPM_Track_History_Screen::render( array $args )` draws `<h2>History of %s</h2>`, the two ways back, `<h3>What publishing would change</h3>` with a diff or a sentence, `<h3>Saves</h3>` as `<ol class="wpcpm-history">` of `<li class="wpcpm-history__revision">` each with `<p class="wpcpm-history__meta">` (date and who) and `<ul class="wpcpm-history__diff">` or a sentence, a line when older saves exist, and `<h3>Publish log</h3>` as `<ul class="wpcpm-history__log">` or a sentence. `WPCPM_Track_Editor_Screen::property_labels()` names every property of `WPCPM_Track_Definition::QUESTION_PROPERTIES`; `WPCPM_Track_Builder_Screen::track_labels()` names every property of `WPCPM_Track_Diff::TRACK`. `>History</a>` follows Preview on every row and on the track's page.

Decision 28 and section 5's three parts, in the order a person wants them: the published copy against the draft, which is the question before pressing Publish; the revisions, newest first and twenty at most, each as the question-level diff against the one before, the oldest shown reading "Created, with N questions" only when it is the first; and the publish log, every entry the store keeps with who and when, its ticks named by the checklist's own labels and its column creations naming the columns. The builder reads all of it, so the screen asks the store nothing (what this plan decides, 5).

A diff prints as lines: the track's own properties in the track form's words, then Added, Removed and Moved with the columns as code, then one line per question whose properties changed, in the question screen's words. Those words come from one map each - `property_labels()` on the editor screen, which its rows and the add form now read instead of naming the rows inline, and `track_labels()` on the builder screen, which the track form and New track read - so a label changed there changes here (what this plan decides, 6). A revision whose diff is empty says nothing in the definition changed; a save that kept no definition says so; more saves than shown adds a line; no saves at all names what turns revisions off (what this plan decides, 10). The date and who are the words the track list already uses for its "published" column, "somebody since removed" included.

The suite's store stand-in gains `revisions()`, reading one more than asked as the real one does, and the checks seed three saves that add a question, change a help line and the hours target, and reorder nothing; twenty-one saves for the cap; a save with a null definition for the gap; and the four kinds of log entry.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-builder.php b/bin/test-track-builder.php
index aae6dea..be69ae9 100644
--- a/bin/test-track-builder.php
+++ b/bin/test-track-builder.php
@@ -180,6 +180,11 @@ class WPCPM_Track_Store {
 		return self::$tracks[ $post_id ]['log'] ?? array();
 	}
 
+	// As the real one: newest first, and one more than asked for when there is one (decision 28).
+	public static function revisions( $post_id, $limit = 20 ) {
+		return array_slice( self::$tracks[ $post_id ]['revisions'] ?? array(), 0, max( 1, (int) $limit ) + 1 );
+	}
+
 	public static function equivalence( $post_id ) {
 		return self::$tracks[ $post_id ]['equivalence'] ?? array( 'not_builtin' );
 	}
@@ -353,12 +358,14 @@ class WPCPM_Flash {
 // against a rule the real class does not hold (T2c's stub-drift findings).
 require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-palette.php';
 require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';
+require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-diff.php';
 require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-columns.php';
 require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-questions.php';
 require_once __DIR__ . '/../includes/tools/class-wpcpm-tool.php';
 require_once __DIR__ . '/../includes/tools/class-wpcpm-track-editor.php';
 require_once __DIR__ . '/../includes/tools/class-wpcpm-track-editor-screen.php';
 require_once __DIR__ . '/../includes/tools/class-wpcpm-track-builder-screen.php';
+require_once __DIR__ . '/../includes/tools/class-wpcpm-track-history-screen.php';
 require_once __DIR__ . '/../includes/tools/class-wpcpm-track-builder.php';
 
 $fail  = 0;
@@ -2229,5 +2236,169 @@ ck( 'Preview is offered on every row, the built-in one included, and beside the
     array( substr_count( $rows_html, '>Preview</a>' ), substr_count( $rows_html, 'wpcpm_preview=11">Preview</a>' ), substr_count( $track_page, 'Back to every track</a> <a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_preview=13">Preview</a>' ) ),
     array( 2, 1, 1 ) );
 
+
+echo "\n=== History: what publishing would change, every save against the one before, the log (1.106.0) ===\n";
+
+ck( 'the question screen names every property the definition allows, and the track form every property the diff compares',
+    array(
+        array_values( array_diff( WPCPM_Track_Definition::QUESTION_PROPERTIES, array_keys( WPCPM_Track_Editor_Screen::property_labels() ) ) ),
+        array_values( array_diff( WPCPM_Track_Diff::TRACK, array_keys( WPCPM_Track_Builder_Screen::track_labels() ) ) ),
+        WPCPM_Track_Editor_Screen::property_labels()['help'],
+    ),
+    array( array(), array(), 'Help under the box' ) );
+
+$question_page = question_screen( WPCPM_Track_Builder::question_form( 13, 'Slack name' ) );
+
+ck( 'and the question screen still draws its rows in those words',
+    array( substr_count( $question_page, '>What the student reads</label>' ), substr_count( $question_page, '>Help under the box</label>' ), substr_count( $question_page, '>Control</label>' ) ),
+    array( 1, 1, 1 ) );
+
+$first  = array( 'schema_version' => 1, 'key' => 'marketing', 'status' => 'Marketing Track', 'label' => 'Marketing Track', 'hours_target' => 100, 'hue' => 'pink', 'questions' => array( 'Hours' => array( 'type' => 'number', 'label' => 'Hours', 'group' => 'hours' ) ) );
+$second = $first;
+$second['questions']['Slack name'] = array( 'type' => 'text', 'label' => 'Your Slack name', 'group' => 'onboarding' );
+$third  = $second;
+$third['hours_target'] = 120;
+$third['questions']['Slack name']['help'] = 'Without the @.';
+$third['questions']['Your blog'] = array( 'type' => 'url', 'label' => 'Your blog', 'group' => 'onboarding' );
+
+WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
+WPCPM_Track_Store::$tracks[13]['definition'] = $third;
+WPCPM_Track_Store::$tracks[13]['state']      = 'changed';
+WPCPM_Track_Store::$tracks[13]['published']  = $second;
+WPCPM_Track_Store::$tracks[13]['revisions']  = array(
+	array( 'id' => 103, 'at' => 1788200000, 'by' => 7, 'definition' => $third ),
+	array( 'id' => 102, 'at' => 1788100000, 'by' => 7, 'definition' => $second ),
+	array( 'id' => 101, 'at' => 1788000000, 'by' => 9, 'definition' => $first ),
+);
+WPCPM_Track_Store::$tracks[13]['log'] = array(
+	array( 'at' => 1788050000, 'by' => 7, 'did' => 'publish' ),
+	array( 'at' => 1788060000, 'by' => 7, 'did' => 'columns', 'detail' => array( 'columns' => array( 'Slack name' ) ) ),
+	array( 'at' => 1788070000, 'by' => 7, 'did' => 'tick-welcome' ),
+	array( 'at' => 1788080000, 'by' => 7, 'did' => 'untick-nothing' ),
+);
+$GLOBALS['users'] = array( 7 => 'A Manager' );
+
+$history = WPCPM_Track_Builder::history( 13 );
+
+ck( 'the builder hands the view the published copy against the draft, each save against the one before, "created" on the first, and the log newest first with the checklist labels for its ticks',
+    array(
+        $history['track'],
+        $history['label'],
+        array( $history['pending']['track'], $history['pending']['added'], $history['pending']['changed'], $history['pending']['same'] ),
+        array( $history['revisions'][0]['at'], $history['revisions'][0]['by'], $history['revisions'][0]['diff']['track'], $history['revisions'][0]['diff']['added'], $history['revisions'][0]['diff']['changed'], $history['revisions'][0]['created'] ),
+        array( $history['revisions'][1]['diff']['added'], $history['revisions'][1]['diff']['changed'], $history['revisions'][1]['created'] ),
+        array( $history['revisions'][2]['by'], $history['revisions'][2]['diff'], $history['revisions'][2]['created'] ),
+        $history['more'],
+        array_column( $history['log'], 'did' ),
+        $history['items']['welcome'],
+    ),
+    array(
+        13,
+        'Marketing Track',
+        array( array( 'hours_target' ), array( 'Your blog' ), array( 'Slack name' => array( 'help' ) ), false ),
+        array( 1788200000, 7, array( 'hours_target' ), array( 'Your blog' ), array( 'Slack name' => array( 'help' ) ), null ),
+        array( array( 'Slack name' ), array(), null ),
+        array( 9, null, 1 ),
+        false,
+        array( 'untick-nothing', 'tick-welcome', 'columns', 'publish' ),
+        'Create the welcome email automation',
+    ) );
+
+$_GET = array( 'wpcpm_history' => 13 );
+ob_start();
+$tool->render_admin_page();
+$history_page = ob_get_clean();
+$_GET = array();
+
+ck( 'the route draws the three parts in order, the diffs in the screens\' own words, the columns as code, and who did what when',
+    array(
+        false !== strpos( $history_page, '<h2>History of Marketing Track</h2>' ),
+        substr_count( $history_page, 'wpcpm_track=13">Back to the track</a> <a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder">Back to every track</a>' ),
+        strpos( $history_page, '<h3>What publishing would change</h3>' ) < strpos( $history_page, '<h3>Saves</h3>' ) && strpos( $history_page, '<h3>Saves</h3>' ) < strpos( $history_page, '<h3>Publish log</h3>' ),
+        substr_count( $history_page, '<li>Track: Hours target</li>' ),
+        substr_count( $history_page, '<li>Added: <code>Your blog</code></li>' ),
+        substr_count( $history_page, '<li><code>Slack name</code>: Help under the box</li>' ),
+        substr_count( $history_page, '<li>Added: <code>Slack name</code></li>' ),
+        substr_count( $history_page, '<p class="wpcpm-history__meta">' . gmdate( 'Y-m-d H:i', 1788000000 ) . ' by somebody since removed</p><p>Created, with 1 question.</p>' ),
+        substr_count( $history_page, '<p class="wpcpm-history__meta">' . gmdate( 'Y-m-d H:i', 1788200000 ) . ' by A Manager</p>' ),
+        substr_count( $history_page, '<li>Published, ' . gmdate( 'Y-m-d H:i', 1788050000 ) . ' by A Manager</li>' ),
+        substr_count( $history_page, '<li>Created in Airtable: Slack name, ' ),
+        substr_count( $history_page, '<li>Ticked &quot;Create the welcome email automation&quot;, ' ),
+        substr_count( $history_page, '<li>Unticked &quot;nothing&quot;, ' ),
+        strpos( $history_page, 'Unticked' ) < strpos( $history_page, '<li>Published, ' ),
+        false !== strpos( $history_page, 'Older saves exist' ),
+        substr_count( $history_page, '<form' ),
+    ),
+    array( true, 1, true, 2, 2, 2, 1, 1, 1, 1, 1, 1, 1, true, false, 0 ) );
+
+WPCPM_Track_Store::$tracks[13]['published'] = null;
+WPCPM_Track_Store::$tracks[13]['log']       = array();
+$_GET = array( 'wpcpm_history' => 13 );
+ob_start();
+$tool->render_admin_page();
+$never = ob_get_clean();
+WPCPM_Track_Store::$tracks[13]['published'] = $third;
+ob_start();
+$tool->render_admin_page();
+$same = ob_get_clean();
+$_GET = array();
+
+ck( 'a track never published says so in place of the top diff, and so does one whose published copy is the draft; an empty log says when it starts',
+    array(
+        false !== strpos( $never, 'This track has never been published, so there is no published copy to compare the draft with.' ),
+        false !== strpos( $never, 'Nothing yet: the log starts when the track is first published.' ),
+        false !== strpos( $same, 'The published copy is the same as the draft: publishing would change nothing.' ),
+        substr_count( $same, '<li>Track: Hours target</li>' ),
+    ),
+    array( true, true, true, 1 ) );
+
+$many = array();
+for ( $i = 21; $i >= 1; $i-- ) {
+	$copy = $first;
+	$copy['hours_target'] = $i;
+	$many[] = array( 'id' => 200 + $i, 'at' => 1788000000 + $i, 'by' => 7, 'definition' => $copy );
+}
+WPCPM_Track_Store::$tracks[13]['revisions'] = $many;
+$capped = WPCPM_Track_Builder::history( 13 );
+WPCPM_Track_Store::$tracks[13]['revisions'] = array_slice( $many, 0, 20 );
+$exact = WPCPM_Track_Builder::history( 13 );
+WPCPM_Track_Store::$tracks[13]['revisions'] = array();
+$_GET = array( 'wpcpm_history' => 13 );
+ob_start();
+$tool->render_admin_page();
+$no_saves = ob_get_clean();
+$_GET = array();
+
+ck( 'twenty saves are shown of more, the oldest shown against the one before it rather than as "created"; exactly twenty are shown whole; none at all says why',
+    array(
+        count( $capped['revisions'] ), $capped['more'], $capped['revisions'][19]['diff']['track'], $capped['revisions'][19]['created'],
+        count( $exact['revisions'] ), $exact['more'], $exact['revisions'][19]['diff'], $exact['revisions'][19]['created'],
+        false !== strpos( $no_saves, 'No saves have been kept for this track. WordPress keeps a copy of each save only while revisions are on.' ),
+    ),
+    array( 20, true, array( 'hours_target' ), null, 20, false, null, 1, true ) );
+
+WPCPM_Track_Store::$tracks[13]['revisions'] = array(
+	array( 'id' => 103, 'at' => 1788200000, 'by' => 7, 'definition' => $third ),
+	array( 'id' => 102, 'at' => 1788100000, 'by' => 7, 'definition' => null ),
+);
+$gap = WPCPM_Track_Builder::history( 13 );
+
+ck( 'a save that kept no definition is handed over with neither a diff nor a count, and the one after it is compared with nothing',
+    array( $gap['revisions'][1]['diff'], $gap['revisions'][1]['created'], $gap['revisions'][0]['diff']['added'] ),
+    array( null, null, array( 'Hours', 'Slack name', 'Your blog' ) ) );
+
+WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
+
+ob_start();
+WPCPM_Track_Builder_Screen::render_list( array( 'rows' => WPCPM_Track_Builder::rows(), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
+$rows_html = ob_get_clean();
+ob_start();
+WPCPM_Track_Builder_Screen::render_form( array( 'form' => WPCPM_Track_Builder::form( 13 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
+$track_page = ob_get_clean();
+
+ck( 'History is offered on every row and beside Preview on the track\'s page',
+    array( substr_count( $rows_html, 'wpcpm_history=13">History</a>' ), substr_count( $track_page, 'wpcpm_preview=13">Preview</a> <a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_history=13">History</a>' ) ),
+    array( 1, 1 ) );
+
 printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
 exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-builder.php`

Expected: `bin/test-track-builder.php` stops with `Fatal error: Uncaught Error: Failed opening required 'includes/tools/class-wpcpm-track-history-screen.php'`. The suite's `require_once` of the new screen fails: the file does not exist yet. (`bin/test-roles.php` still passes here, for the reason Task 1 gives: the file it would flag does not exist until Step 3 creates and requires it.)

- [ ] **Step 3: Write the code**

```diff
diff --git a/assets/css/track-builder.css b/assets/css/track-builder.css
index caff1b0..fcfe2da 100644
--- a/assets/css/track-builder.css
+++ b/assets/css/track-builder.css
@@ -161,3 +161,19 @@
 	max-width: 60rem;
 	margin-top: 1.5rem;
 }
+
+/* History (1.106.0): each save with its diff under it, and the log as plain lines. */
+.wpcpm-history {
+	max-width: 60rem;
+}
+
+.wpcpm-history__meta {
+	margin: 0 0 0.25em;
+	font-weight: 600;
+}
+
+.wpcpm-history__diff,
+.wpcpm-history__log {
+	margin: 0 0 1em 1.5em;
+	list-style: disc;
+}
diff --git a/includes/tools/class-wpcpm-track-builder-screen.php b/includes/tools/class-wpcpm-track-builder-screen.php
index ffbbbcb..a762ca5 100644
--- a/includes/tools/class-wpcpm-track-builder-screen.php
+++ b/includes/tools/class-wpcpm-track-builder-screen.php
@@ -145,6 +145,16 @@ final class WPCPM_Track_Builder_Screen {
 			);
 		}
 
+		// History: the published copy against the draft, every save against the one before, and
+		// the publish log (decision 28).
+		if ( '' !== $url ) {
+			printf(
+				'<a href="%1$s">%2$s</a> ',
+				esc_url( add_query_arg( 'wpcpm_history', (int) $row['id'], $url ) ),
+				esc_html__( 'History', 'wpcredits-program-manager' )
+			);
+		}
+
 		// Publishing is a screen of its own: it has a preflight to read, a checklist to work
 		// through and, when a column is missing, a list to take to Airtable. A trashed track is
 		// not offered it, because the store refuses to publish out of the trash.
@@ -654,11 +664,13 @@ final class WPCPM_Track_Builder_Screen {
 		self::render_notice( $flash );
 
 		printf(
-			'<p><a href="%1$s">%2$s</a> <a href="%3$s">%4$s</a></p>',
+			'<p><a href="%1$s">%2$s</a> <a href="%3$s">%4$s</a> <a href="%5$s">%6$s</a></p>',
 			esc_url( $url ),
 			esc_html__( 'Back to every track', 'wpcredits-program-manager' ),
 			esc_url( add_query_arg( 'wpcpm_preview', isset( $form['id'] ) ? (int) $form['id'] : 0, $url ) ),
-			esc_html__( 'Preview', 'wpcredits-program-manager' )
+			esc_html__( 'Preview', 'wpcredits-program-manager' ),
+			esc_url( add_query_arg( 'wpcpm_history', isset( $form['id'] ) ? (int) $form['id'] : 0, $url ) ),
+			esc_html__( 'History', 'wpcredits-program-manager' )
 		);
 
 		if ( ! empty( $form['read_only'] ) ) {
@@ -677,15 +689,7 @@ final class WPCPM_Track_Builder_Screen {
 
 		echo '<table class="form-table" role="presentation"><tbody>';
 
-		foreach ( array(
-			'label'           => __( 'Name', 'wpcredits-program-manager' ),
-			'status'          => __( 'Airtable status', 'wpcredits-program-manager' ),
-			'key'             => __( 'Key', 'wpcredits-program-manager' ),
-			'course_url'      => __( 'Learn course', 'wpcredits-program-manager' ),
-			'learn_course_id' => __( 'Learn course ID', 'wpcredits-program-manager' ),
-			'hours_target'    => __( 'Hours target', 'wpcredits-program-manager' ),
-			'hue'             => __( 'Key chip color', 'wpcredits-program-manager' ),
-		) as $field => $heading ) {
+		foreach ( self::track_labels() as $field => $heading ) {
 			$value = array_key_exists( $field, $typed ) ? $typed[ $field ] : ( isset( $form[ $field ] ) ? $form[ $field ] : '' );
 
 			printf(
@@ -727,6 +731,26 @@ final class WPCPM_Track_Builder_Screen {
 		);
 	}
 
+	/**
+	 * Every track property the form edits, in the words it uses for them.
+	 *
+	 * One map, read by the track form, by New track for its first three, and by History for a
+	 * diff line about the track itself (the design's decision 28).
+	 *
+	 * @return string[] Property => what its row is called.
+	 */
+	public static function track_labels() {
+		return array(
+			'label'           => __( 'Name', 'wpcredits-program-manager' ),
+			'status'          => __( 'Airtable status', 'wpcredits-program-manager' ),
+			'key'             => __( 'Key', 'wpcredits-program-manager' ),
+			'course_url'      => __( 'Learn course', 'wpcredits-program-manager' ),
+			'learn_course_id' => __( 'Learn course ID', 'wpcredits-program-manager' ),
+			'hours_target'    => __( 'Hours target', 'wpcredits-program-manager' ),
+			'hue'             => __( 'Key chip color', 'wpcredits-program-manager' ),
+		);
+	}
+
 	/**
 	 * A track being started from nothing: its name, its Airtable status and its key (the design's 5).
 	 *
@@ -749,11 +773,7 @@ final class WPCPM_Track_Builder_Screen {
 
 		echo '<table class="form-table" role="presentation"><tbody>';
 
-		foreach ( array(
-			'label'  => __( 'Name', 'wpcredits-program-manager' ),
-			'status' => __( 'Airtable status', 'wpcredits-program-manager' ),
-			'key'    => __( 'Key', 'wpcredits-program-manager' ),
-		) as $field => $heading ) {
+		foreach ( array_intersect_key( self::track_labels(), array_flip( array( 'label', 'status', 'key' ) ) ) as $field => $heading ) {
 			printf(
 				'<tr><th scope="row"><label for="wpcpm_%1$s">%2$s</label></th><td><input type="text" class="regular-text" id="wpcpm_%1$s" name="wpcpm_%1$s" value="%3$s" /></td></tr>',
 				esc_attr( $field ),
diff --git a/includes/tools/class-wpcpm-track-builder.php b/includes/tools/class-wpcpm-track-builder.php
index a110146..4e2e405 100644
--- a/includes/tools/class-wpcpm-track-builder.php
+++ b/includes/tools/class-wpcpm-track-builder.php
@@ -29,6 +29,9 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 	/** Start a track from nothing. */
 	const ACTION_NEW = 'wpcpm_track_new';
 
+	/** How many saves History shows, the cap the semester report screen gives its own. */
+	const HISTORY_LIMIT = 20;
+
 	/** Run a built-in track from its definition. */
 	const ACTION_SWITCH_DEFINITION = 'wpcpm_track_switch_definition';
 
@@ -294,6 +297,65 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		);
 	}
 
+	/**
+	 * What History shows (the design's decision 28), read here so the screen asks the store nothing.
+	 *
+	 * The store hands back one revision more than shown, so the oldest shown still has the copy
+	 * before it to be compared with; the one with no predecessor is the creation, and says how
+	 * many questions it started with. A save that kept no definition, which the store never
+	 * makes but a site's own revision handling could, is handed over with neither.
+	 *
+	 * @param int $post_id The track.
+	 * @return array `track`, `label`, `pending` (the published copy against the draft, or null when
+	 *               nothing was published), `revisions` (each `at`, `by`, and a `diff` or a
+	 *               `created` count), `more` (whether older saves exist than are shown), `log`
+	 *               (newest first) and `items` (checklist item => its label, for the log's ticks).
+	 */
+	public static function history( $post_id ) {
+		$post_id    = (int) $post_id;
+		$definition = WPCPM_Track_Store::get( $post_id );
+		$definition = is_array( $definition ) ? $definition : array();
+		$published  = WPCPM_Track_Store::published( $post_id );
+		$kept       = WPCPM_Track_Store::revisions( $post_id, self::HISTORY_LIMIT );
+		$revisions  = array();
+
+		foreach ( array_slice( $kept, 0, self::HISTORY_LIMIT ) as $i => $revision ) {
+			$entry = array(
+				'at'      => isset( $revision['at'] ) ? (int) $revision['at'] : 0,
+				'by'      => isset( $revision['by'] ) ? (int) $revision['by'] : 0,
+				'diff'    => null,
+				'created' => null,
+			);
+
+			if ( isset( $revision['definition'] ) && is_array( $revision['definition'] ) ) {
+				if ( isset( $kept[ $i + 1 ] ) ) {
+					$previous      = isset( $kept[ $i + 1 ]['definition'] ) && is_array( $kept[ $i + 1 ]['definition'] ) ? $kept[ $i + 1 ]['definition'] : array();
+					$entry['diff'] = WPCPM_Track_Diff::between( $previous, $revision['definition'] );
+				} else {
+					$entry['created'] = isset( $revision['definition']['questions'] ) && is_array( $revision['definition']['questions'] ) ? count( $revision['definition']['questions'] ) : 0;
+				}
+			}
+
+			$revisions[] = $entry;
+		}
+
+		$items = array();
+
+		foreach ( WPCPM_Track_Publish::checklist( $post_id ) as $item => $spec ) {
+			$items[ $item ] = isset( $spec['label'] ) ? (string) $spec['label'] : (string) $item;
+		}
+
+		return array(
+			'track'     => $post_id,
+			'label'     => isset( $definition['label'] ) ? (string) $definition['label'] : '',
+			'pending'   => is_array( $published ) ? WPCPM_Track_Diff::between( $published, $definition ) : null,
+			'revisions' => $revisions,
+			'more'      => count( $kept ) > self::HISTORY_LIMIT,
+			'log'       => array_reverse( WPCPM_Track_Store::log_entries( $post_id ) ),
+			'items'     => $items,
+		);
+	}
+
 	/**
 	 * What publishing would create in the base, off the cached reading (decision 24).
 	 *
@@ -457,6 +519,16 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 			return;
 		}
 
+		$history = WPCPM_Request::id( 'wpcpm_history' );
+
+		if ( $history > 0 && is_array( WPCPM_Track_Store::get( $history ) ) ) {
+			WPCPM_Track_History_Screen::render( self::history( $history ) + array( 'url' => $this->admin_url() ) );
+
+			echo '</div>';
+
+			return;
+		}
+
 		$publish = WPCPM_Request::id( 'wpcpm_publish' );
 
 		if ( $publish > 0 && is_array( WPCPM_Track_Store::get( $publish ) ) ) {
diff --git a/includes/tools/class-wpcpm-track-editor-screen.php b/includes/tools/class-wpcpm-track-editor-screen.php
index 6b57d39..496005a 100644
--- a/includes/tools/class-wpcpm-track-editor-screen.php
+++ b/includes/tools/class-wpcpm-track-editor-screen.php
@@ -38,6 +38,40 @@ class WPCPM_Track_Editor_Screen {
 		);
 	}
 
+	/**
+	 * Every question property, in the words the question screen uses for it.
+	 *
+	 * One map, so History's diff lines read as the rows they point at (the design's decision 28),
+	 * and a label changed here changes there. The last two are authoring properties the screen
+	 * carries without a row of their own.
+	 *
+	 * @return string[] Property => what its row is called.
+	 */
+	public static function property_labels() {
+		return array(
+			'type'                  => __( 'Control', 'wpcredits-program-manager' ),
+			'label'                 => __( 'What the student reads', 'wpcredits-program-manager' ),
+			'group'                 => __( 'Group', 'wpcredits-program-manager' ),
+			'help'                  => __( 'Help under the box', 'wpcredits-program-manager' ),
+			'lead'                  => __( 'Heading before it', 'wpcredits-program-manager' ),
+			'subgroup'              => __( 'Subheading before it', 'wpcredits-program-manager' ),
+			'note'                  => __( 'Note after the run', 'wpcredits-program-manager' ),
+			'row'                   => __( 'Row', 'wpcredits-program-manager' ),
+			'stack'                 => __( 'Shares one column of its row', 'wpcredits-program-manager' ),
+			'required'              => __( 'Marked required', 'wpcredits-program-manager' ),
+			'hide_from_institution' => __( 'Kept off everything an institution reads', 'wpcredits-program-manager' ),
+			'min'                   => __( 'Lowest value', 'wpcredits-program-manager' ),
+			'max'                   => __( 'Highest value', 'wpcredits-program-manager' ),
+			'step'                  => __( 'Step', 'wpcredits-program-manager' ),
+			'maxlength'             => __( 'Length limit', 'wpcredits-program-manager' ),
+			'mono'                  => __( 'Monospace, for code', 'wpcredits-program-manager' ),
+			'options'               => __( 'Choices, one a line', 'wpcredits-program-manager' ),
+			'why'                   => __( 'Developer note', 'wpcredits-program-manager' ),
+			'learn_lesson_id'       => __( 'Learn lesson', 'wpcredits-program-manager' ),
+			'airtable_type'         => __( 'Airtable column type', 'wpcredits-program-manager' ),
+		);
+	}
+
 	/**
 	 * The ten controls, named for the person choosing one.
 	 *
@@ -95,7 +129,8 @@ class WPCPM_Track_Editor_Screen {
 			return array_key_exists( $property, $from ) ? $from[ $property ] : $fallback;
 		};
 
-		$type = (string) $value( 'type' );
+		$type   = (string) $value( 'type' );
+		$labels = self::property_labels();
 
 		self::render_flash( $flash );
 
@@ -133,7 +168,7 @@ class WPCPM_Track_Editor_Screen {
 				'<p><strong>%1$s</strong> <code>%2$s</code><br /><strong>%3$s</strong> %4$s</p>',
 				esc_html__( 'Airtable column', 'wpcredits-program-manager' ),
 				esc_html( $column ),
-				esc_html__( 'Control', 'wpcredits-program-manager' ),
+				esc_html( $labels['type'] ),
 				esc_html( isset( $controls[ $fixed ] ) ? $controls[ $fixed ] : $fixed )
 			);
 			echo '<p class="wpcpm-question__locked">' . esc_html__( 'This question has been published, so its column, its control and its choices are fixed: the column in Airtable holds what students have written, in that shape. To ask it differently, remove it and add a new question with a column of its own.', 'wpcredits-program-manager' ) . '</p>';
@@ -143,7 +178,7 @@ class WPCPM_Track_Editor_Screen {
 				esc_html__( 'Airtable column', 'wpcredits-program-manager' ),
 				esc_attr( (string) $value( 'column', $column ) )
 			);
-			printf( '<p><label for="wpcpm_type">%s</label><br /><select id="wpcpm_type" name="wpcpm_type">', esc_html__( 'Control', 'wpcredits-program-manager' ) );
+			printf( '<p><label for="wpcpm_type">%s</label><br /><select id="wpcpm_type" name="wpcpm_type">', esc_html( $labels['type'] ) );
 
 			foreach ( $controls as $control => $name ) {
 				printf( '<option value="%1$s"%3$s>%2$s</option>', esc_attr( $control ), esc_html( $name ), $control === $type ? ' selected="selected"' : '' );
@@ -157,9 +192,9 @@ class WPCPM_Track_Editor_Screen {
 
 		echo '<table class="form-table" role="presentation"><tbody>';
 
-		self::render_text_row( 'label', __( 'What the student reads', 'wpcredits-program-manager' ), (string) $value( 'label' ) );
+		self::render_text_row( 'label', $labels['label'], (string) $value( 'label' ) );
 
-		printf( '<tr><th scope="row"><label for="wpcpm_group">%s</label></th><td><select id="wpcpm_group" name="wpcpm_group">', esc_html__( 'Group', 'wpcredits-program-manager' ) );
+		printf( '<tr><th scope="row"><label for="wpcpm_group">%s</label></th><td><select id="wpcpm_group" name="wpcpm_group">', esc_html( $labels['group'] ) );
 
 		foreach ( self::groups() as $group => $heading ) {
 			printf( '<option value="%1$s"%3$s>%2$s</option>', esc_attr( $group ), esc_html( $heading ), $group === (string) $value( 'group' ) ? ' selected="selected"' : '' );
@@ -167,27 +202,27 @@ class WPCPM_Track_Editor_Screen {
 
 		echo '</select></td></tr>';
 
-		self::render_text_row( 'help', __( 'Help under the box', 'wpcredits-program-manager' ), (string) $value( 'help' ) );
-		self::render_text_row( 'lead', __( 'Heading before it', 'wpcredits-program-manager' ), (string) $value( 'lead' ) );
-		self::render_text_row( 'subgroup', __( 'Subheading before it', 'wpcredits-program-manager' ), (string) $value( 'subgroup' ) );
-		self::render_text_row( 'note', __( 'Note after the run', 'wpcredits-program-manager' ), (string) $value( 'note' ) );
-		self::render_text_row( 'row', __( 'Row', 'wpcredits-program-manager' ), (string) $value( 'row' ), __( 'Questions with the same row name sit side by side: lowercase letters, digits and hyphens.', 'wpcredits-program-manager' ) );
-		self::render_flag_row( 'stack', __( 'Shares one column of its row', 'wpcredits-program-manager' ), ! empty( $value( 'stack' ) ) );
-		self::render_flag_row( 'required', __( 'Marked required', 'wpcredits-program-manager' ), ! empty( $value( 'required' ) ) );
-		self::render_flag_row( 'hide_from_institution', __( 'Kept off everything an institution reads', 'wpcredits-program-manager' ), ! empty( $value( 'hide_from_institution' ) ) || 'email' === $type );
+		self::render_text_row( 'help', $labels['help'], (string) $value( 'help' ) );
+		self::render_text_row( 'lead', $labels['lead'], (string) $value( 'lead' ) );
+		self::render_text_row( 'subgroup', $labels['subgroup'], (string) $value( 'subgroup' ) );
+		self::render_text_row( 'note', $labels['note'], (string) $value( 'note' ) );
+		self::render_text_row( 'row', $labels['row'], (string) $value( 'row' ), __( 'Questions with the same row name sit side by side: lowercase letters, digits and hyphens.', 'wpcredits-program-manager' ) );
+		self::render_flag_row( 'stack', $labels['stack'], ! empty( $value( 'stack' ) ) );
+		self::render_flag_row( 'required', $labels['required'], ! empty( $value( 'required' ) ) );
+		self::render_flag_row( 'hide_from_institution', $labels['hide_from_institution'], ! empty( $value( 'hide_from_institution' ) ) || 'email' === $type );
 
 		if ( 'number' === $type ) {
-			self::render_text_row( 'min', __( 'Lowest value', 'wpcredits-program-manager' ), (string) $value( 'min' ) );
-			self::render_text_row( 'max', __( 'Highest value', 'wpcredits-program-manager' ), (string) $value( 'max' ) );
-			self::render_text_row( 'step', __( 'Step', 'wpcredits-program-manager' ), (string) $value( 'step' ), __( 'The step also sets how many decimal places a new Airtable column keeps: 1 for whole numbers, 0.01 for a grade.', 'wpcredits-program-manager' ) );
+			self::render_text_row( 'min', $labels['min'], (string) $value( 'min' ) );
+			self::render_text_row( 'max', $labels['max'], (string) $value( 'max' ) );
+			self::render_text_row( 'step', $labels['step'], (string) $value( 'step' ), __( 'The step also sets how many decimal places a new Airtable column keeps: 1 for whole numbers, 0.01 for a grade.', 'wpcredits-program-manager' ) );
 		}
 
 		if ( 'text' === $type ) {
-			self::render_text_row( 'maxlength', __( 'Length limit', 'wpcredits-program-manager' ), (string) $value( 'maxlength' ), __( 'Optional. A single-line box alone takes one; a text area already has its own.', 'wpcredits-program-manager' ) );
+			self::render_text_row( 'maxlength', $labels['maxlength'], (string) $value( 'maxlength' ), __( 'Optional. A single-line box alone takes one; a text area already has its own.', 'wpcredits-program-manager' ) );
 		}
 
 		if ( 'textarea' === $type ) {
-			self::render_flag_row( 'mono', __( 'Monospace, for code', 'wpcredits-program-manager' ), ! empty( $value( 'mono' ) ) );
+			self::render_flag_row( 'mono', $labels['mono'], ! empty( $value( 'mono' ) ) );
 		}
 
 		if ( 'select' === $type ) {
@@ -202,14 +237,14 @@ class WPCPM_Track_Editor_Screen {
 
 			printf(
 				'<tr><th scope="row"><label for="wpcpm_options">%1$s</label></th><td><textarea id="wpcpm_options" name="wpcpm_options" rows="6" class="large-text code"%4$s>%2$s</textarea><p class="description">%3$s</p></td></tr>',
-				esc_html__( 'Choices, one a line', 'wpcredits-program-manager' ),
+				esc_html( $labels['options'] ),
 				esc_textarea( is_array( $options ) ? implode( "\n", array_map( 'strval', $options ) ) : (string) $options ),
 				esc_html( $choices ),
 				$locked ? ' readonly="readonly"' : ''
 			);
 		}
 
-		self::render_text_row( 'why', __( 'Developer note', 'wpcredits-program-manager' ), (string) $value( 'why' ), __( 'Why a column name looks like a slip. No student sees it.', 'wpcredits-program-manager' ) );
+		self::render_text_row( 'why', $labels['why'], (string) $value( 'why' ), __( 'Why a column name looks like a slip. No student sees it.', 'wpcredits-program-manager' ) );
 
 		echo '</tbody></table>';
 
@@ -622,10 +657,10 @@ class WPCPM_Track_Editor_Screen {
 		printf(
 			'<label for="wpcpm_add_label_%1$s">%2$s</label> <input type="text" class="regular-text" id="wpcpm_add_label_%1$s" name="wpcpm_label" value="%3$s" /> ',
 			esc_attr( $group ),
-			esc_html__( 'What the student reads', 'wpcredits-program-manager' ),
+			esc_html( self::property_labels()['label'] ),
 			esc_attr( $words )
 		);
-		printf( '<label for="wpcpm_add_type_%1$s">%2$s</label> <select id="wpcpm_add_type_%1$s" name="wpcpm_type">', esc_attr( $group ), esc_html__( 'Control', 'wpcredits-program-manager' ) );
+		printf( '<label for="wpcpm_add_type_%1$s">%2$s</label> <select id="wpcpm_add_type_%1$s" name="wpcpm_type">', esc_attr( $group ), esc_html( self::property_labels()['type'] ) );
 
 		foreach ( self::controls() as $type => $name ) {
 			printf( '<option value="%1$s"%3$s>%2$s</option>', esc_attr( $type ), esc_html( $name ), $type === $control ? ' selected="selected"' : '' );
diff --git a/includes/tools/class-wpcpm-track-history-screen.php b/includes/tools/class-wpcpm-track-history-screen.php
new file mode 100644
index 0000000..3716b14
--- /dev/null
+++ b/includes/tools/class-wpcpm-track-history-screen.php
@@ -0,0 +1,321 @@
+<?php
+/**
+ * The Track Builder's History screen: what publishing would change, every save against the one
+ * before it, and the publish log (the design's decision 28).
+ *
+ * @package WPCredits_Program_Manager
+ */
+
+if ( ! defined( 'ABSPATH' ) ) {
+	exit;
+}
+
+/**
+ * Draws what `WPCPM_Track_Builder::history()` read, and asks nothing of the store itself.
+ *
+ * Three parts, in the order a person wants them (the design's section 5): the published copy
+ * against the draft, which is the question before pressing Publish; the revisions, newest first,
+ * each as the question-level diff against the one before; and the publish log. A diff prints in
+ * the words the question screen and the track form use for the same properties, so a line here
+ * reads as the row it points at.
+ */
+final class WPCPM_Track_History_Screen {
+
+	/**
+	 * The screen.
+	 *
+	 * @param array $args `track`, `label`, `pending` (the published copy against the draft, or
+	 *                    null when nothing was published), `revisions` (each `at`, `by`, and a
+	 *                    `diff` or a `created` count), `more` (whether older saves exist than are
+	 *                    shown), `log` (newest first), `items` (checklist item => its label, for
+	 *                    the log's ticks), and the screen's `url`.
+	 */
+	public static function render( array $args ) {
+		$track     = isset( $args['track'] ) ? (int) $args['track'] : 0;
+		$label     = isset( $args['label'] ) ? (string) $args['label'] : '';
+		$pending   = isset( $args['pending'] ) && is_array( $args['pending'] ) ? $args['pending'] : null;
+		$revisions = isset( $args['revisions'] ) && is_array( $args['revisions'] ) ? $args['revisions'] : array();
+		$log       = isset( $args['log'] ) && is_array( $args['log'] ) ? $args['log'] : array();
+		$items     = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
+		$url       = isset( $args['url'] ) ? (string) $args['url'] : '';
+
+		echo '<h2>';
+		printf(
+			/* translators: %s: the track's name. */
+			esc_html__( 'History of %s', 'wpcredits-program-manager' ),
+			esc_html( $label )
+		);
+		echo '</h2>';
+
+		if ( '' !== $url ) {
+			printf(
+				'<p><a href="%1$s">%2$s</a> <a href="%3$s">%4$s</a></p>',
+				esc_url( add_query_arg( 'wpcpm_track', $track, $url ) ),
+				esc_html__( 'Back to the track', 'wpcredits-program-manager' ),
+				esc_url( $url ),
+				esc_html__( 'Back to every track', 'wpcredits-program-manager' )
+			);
+		}
+
+		self::render_pending( $pending );
+		self::render_revisions( $revisions, ! empty( $args['more'] ) );
+		self::render_log( $log, $items );
+	}
+
+	/**
+	 * The published copy against the draft: the question a person has before pressing Publish.
+	 *
+	 * @param array|null $pending The diff, or null when the track was never published.
+	 */
+	private static function render_pending( $pending ) {
+		echo '<h3>' . esc_html__( 'What publishing would change', 'wpcredits-program-manager' ) . '</h3>';
+
+		if ( null === $pending ) {
+			echo '<p>' . esc_html__( 'This track has never been published, so there is no published copy to compare the draft with.', 'wpcredits-program-manager' ) . '</p>';
+
+			return;
+		}
+
+		if ( ! empty( $pending['same'] ) ) {
+			echo '<p>' . esc_html__( 'The published copy is the same as the draft: publishing would change nothing.', 'wpcredits-program-manager' ) . '</p>';
+
+			return;
+		}
+
+		self::render_diff( $pending );
+	}
+
+	/**
+	 * Every save, newest first, each against the one before it.
+	 *
+	 * @param array[] $revisions Each `at`, `by`, and a `diff` or a `created` count.
+	 * @param bool    $more      Whether older saves exist than are shown.
+	 */
+	private static function render_revisions( array $revisions, $more ) {
+		echo '<h3>' . esc_html__( 'Saves', 'wpcredits-program-manager' ) . '</h3>';
+
+		if ( array() === $revisions ) {
+			// WordPress keeps a copy of each save only while revisions are on (`WP_POST_REVISIONS`),
+			// and the store cannot make one where the site refuses them.
+			echo '<p>' . esc_html__( 'No saves have been kept for this track. WordPress keeps a copy of each save only while revisions are on.', 'wpcredits-program-manager' ) . '</p>';
+
+			return;
+		}
+
+		echo '<ol class="wpcpm-history">';
+
+		foreach ( $revisions as $revision ) {
+			echo '<li class="wpcpm-history__revision">';
+			echo '<p class="wpcpm-history__meta">' . esc_html( self::when_and_who( isset( $revision['at'] ) ? (int) $revision['at'] : 0, isset( $revision['by'] ) ? (int) $revision['by'] : 0 ) ) . '</p>';
+
+			if ( isset( $revision['created'] ) && null !== $revision['created'] ) {
+				$count = (int) $revision['created'];
+
+				echo '<p>' . esc_html(
+					sprintf(
+						/* translators: %d: how many questions the track started with. */
+						_n( 'Created, with %d question.', 'Created, with %d questions.', $count, 'wpcredits-program-manager' ),
+						$count
+					)
+				) . '</p>';
+			} elseif ( isset( $revision['diff'] ) && is_array( $revision['diff'] ) ) {
+				if ( ! empty( $revision['diff']['same'] ) ) {
+					echo '<p>' . esc_html__( 'Nothing in the definition changed.', 'wpcredits-program-manager' ) . '</p>';
+				} else {
+					self::render_diff( $revision['diff'] );
+				}
+			} else {
+				echo '<p>' . esc_html__( 'This save kept no definition.', 'wpcredits-program-manager' ) . '</p>';
+			}
+
+			echo '</li>';
+		}
+
+		echo '</ol>';
+
+		if ( $more ) {
+			echo '<p>' . esc_html(
+				sprintf(
+					/* translators: %d: how many saves are shown. */
+					__( 'Older saves exist; the last %d are shown.', 'wpcredits-program-manager' ),
+					count( $revisions )
+				)
+			) . '</p>';
+		}
+	}
+
+	/**
+	 * The publish log, newest first: every entry the store keeps, with who and when.
+	 *
+	 * @param array[] $log   The entries.
+	 * @param array   $items Checklist item => its label, for the ticks.
+	 */
+	private static function render_log( array $log, array $items ) {
+		echo '<h3>' . esc_html__( 'Publish log', 'wpcredits-program-manager' ) . '</h3>';
+
+		if ( array() === $log ) {
+			echo '<p>' . esc_html__( 'Nothing yet: the log starts when the track is first published.', 'wpcredits-program-manager' ) . '</p>';
+
+			return;
+		}
+
+		echo '<ul class="wpcpm-history__log">';
+
+		foreach ( $log as $entry ) {
+			if ( ! is_array( $entry ) ) {
+				continue;
+			}
+
+			echo '<li>' . esc_html(
+				sprintf(
+					/* translators: 1: what happened, 2: a date and time, then who did it. */
+					__( '%1$s, %2$s', 'wpcredits-program-manager' ),
+					self::what( $entry, $items ),
+					self::when_and_who( isset( $entry['at'] ) ? (int) $entry['at'] : 0, isset( $entry['by'] ) ? (int) $entry['by'] : 0 )
+				)
+			) . '</li>';
+		}
+
+		echo '</ul>';
+	}
+
+	/**
+	 * One diff, as lines: the track's own properties, then the columns added, removed and moved,
+	 * then each question whose properties changed, named as the question screen names them.
+	 *
+	 * @param array $diff What `WPCPM_Track_Diff::between()` answered.
+	 */
+	private static function render_diff( array $diff ) {
+		echo '<ul class="wpcpm-history__diff">';
+
+		if ( ! empty( $diff['track'] ) ) {
+			echo '<li>' . esc_html(
+				sprintf(
+					/* translators: %s: the track properties that changed, in the track form's words. */
+					__( 'Track: %s', 'wpcredits-program-manager' ),
+					implode( ', ', self::named( (array) $diff['track'], WPCPM_Track_Builder_Screen::track_labels() ) )
+				)
+			) . '</li>';
+		}
+
+		foreach ( array(
+			'added'   => __( 'Added:', 'wpcredits-program-manager' ),
+			'removed' => __( 'Removed:', 'wpcredits-program-manager' ),
+			'moved'   => __( 'Moved:', 'wpcredits-program-manager' ),
+		) as $part => $heading ) {
+			if ( empty( $diff[ $part ] ) ) {
+				continue;
+			}
+
+			echo '<li>' . esc_html( $heading ) . ' ';
+			self::render_columns( (array) $diff[ $part ] );
+			echo '</li>';
+		}
+
+		if ( ! empty( $diff['changed'] ) && is_array( $diff['changed'] ) ) {
+			$labels = WPCPM_Track_Editor_Screen::property_labels();
+
+			foreach ( $diff['changed'] as $column => $properties ) {
+				echo '<li><code>' . esc_html( (string) $column ) . '</code>: ' . esc_html( implode( ', ', self::named( (array) $properties, $labels ) ) ) . '</li>';
+			}
+		}
+
+		echo '</ul>';
+	}
+
+	/**
+	 * Column names, each in its own code element.
+	 *
+	 * @param string[] $columns The columns.
+	 */
+	private static function render_columns( array $columns ) {
+		$first = true;
+
+		foreach ( $columns as $column ) {
+			echo ( $first ? '' : ', ' ) . '<code>' . esc_html( (string) $column ) . '</code>';
+			$first = false;
+		}
+	}
+
+	/**
+	 * Properties in the words a screen uses for them; one no screen names prints as itself.
+	 *
+	 * @param string[] $properties The properties.
+	 * @param string[] $labels     Property => its label.
+	 * @return string[]
+	 */
+	private static function named( array $properties, array $labels ) {
+		$named = array();
+
+		foreach ( $properties as $property ) {
+			$property = (string) $property;
+			$named[]  = isset( $labels[ $property ] ) ? (string) $labels[ $property ] : $property;
+		}
+
+		return $named;
+	}
+
+	/**
+	 * What a log entry says happened.
+	 *
+	 * @param array $entry The entry: `did`, and `detail` when the store kept one.
+	 * @param array $items Checklist item => its label.
+	 * @return string
+	 */
+	private static function what( array $entry, array $items ) {
+		$did   = isset( $entry['did'] ) ? (string) $entry['did'] : '';
+		$words = array(
+			'publish'           => __( 'Published', 'wpcredits-program-manager' ),
+			'unpublish'         => __( 'Unpublished', 'wpcredits-program-manager' ),
+			'switch_definition' => __( 'Switched to run from its definition', 'wpcredits-program-manager' ),
+			'switch_builtin'    => __( 'Switched back to its hand-written form', 'wpcredits-program-manager' ),
+		);
+
+		if ( isset( $words[ $did ] ) ) {
+			return $words[ $did ];
+		}
+
+		if ( 'columns' === $did ) {
+			$columns = isset( $entry['detail']['columns'] ) ? array_map( 'strval', (array) $entry['detail']['columns'] ) : array();
+
+			return sprintf(
+				/* translators: %s: the columns created, comma-separated. */
+				__( 'Created in Airtable: %s', 'wpcredits-program-manager' ),
+				implode( ', ', $columns )
+			);
+		}
+
+		foreach ( array(
+			/* translators: %s: a checklist item. */
+			'tick-'   => __( 'Ticked "%s"', 'wpcredits-program-manager' ),
+			/* translators: %s: a checklist item. */
+			'untick-' => __( 'Unticked "%s"', 'wpcredits-program-manager' ),
+		) as $prefix => $pattern ) {
+			if ( 0 === strpos( $did, $prefix ) ) {
+				$item = substr( $did, strlen( $prefix ) );
+
+				return sprintf( $pattern, isset( $items[ $item ] ) ? (string) $items[ $item ] : $item );
+			}
+		}
+
+		return $did;
+	}
+
+	/**
+	 * A date and time, and who: the words the track list uses for its "published" column.
+	 *
+	 * @param int $at A Unix timestamp.
+	 * @param int $by A user ID.
+	 * @return string
+	 */
+	private static function when_and_who( $at, $by ) {
+		$user = get_userdata( (int) $by );
+
+		return sprintf(
+			/* translators: 1: a date and time, 2: who saved or published the track. */
+			__( '%1$s by %2$s', 'wpcredits-program-manager' ),
+			wp_date( 'Y-m-d H:i', (int) $at ),
+			$user ? $user->display_name : __( 'somebody since removed', 'wpcredits-program-manager' )
+		);
+	}
+}
diff --git a/uninstall.php b/uninstall.php
index 50f14b6..d28f2bb 100644
--- a/uninstall.php
+++ b/uninstall.php
@@ -153,6 +153,7 @@ require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicate
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-track-builder-screen.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-track-builder.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-track-editor-screen.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-track-history-screen.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-track-editor.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-tools.php';
 
diff --git a/wpcredits-program-manager.php b/wpcredits-program-manager.php
index 752c9b7..9ac65c8 100644
--- a/wpcredits-program-manager.php
+++ b/wpcredits-program-manager.php
@@ -147,6 +147,7 @@ require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-finder.php
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-track-builder-screen.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-track-builder.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-track-editor-screen.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-track-history-screen.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-track-editor.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-tools.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-builder.php`

Expected: `bin/test-track-builder.php` ends `ALL PASS (202 checks)`. `php bin/test-roles.php` ends `ALL PASS` as well.

- [ ] **Step 5: Run everything**

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
bash bin/check-standards.sh | tail -1
```

Expected: the battery prints nothing, then a line ending `all resolve`, a line ending `US English throughout.`, a line ending `0 dead.`, and `85 warnings, no errors.` (or fewer warnings).

- [ ] **Step 6: Commit**

```bash
git add assets/css/track-builder.css bin/test-track-builder.php includes/tools/class-wpcpm-track-builder-screen.php includes/tools/class-wpcpm-track-builder.php includes/tools/class-wpcpm-track-editor-screen.php includes/tools/class-wpcpm-track-history-screen.php uninstall.php wpcredits-program-manager.php
git commit -m "Track Builder T3b: History"
```

---

### Task 8: The words on a built-in row and its publish screen

**Files:**
- Modify: `includes/tools/class-wpcpm-track-builder-screen.php` (`render_row()`'s state cell, `render_equivalence()`'s not-published line, `publish_link_label()`, `render_publish()` and `render_publish_actions()`)
- Modify: `includes/tools/class-wpcpm-track-builder.php` (the publish route passes `source`)
- Test: `bin/test-track-builder.php`

**Interfaces:**
- Consumes: each row's `source` and `state` as `rows()` gives them; `WPCPM_Track_Store::source()`.
- Produces: on a built-in track whose definition is a draft, the state cell reads "Live, from its hand-written form" and the publish link "Publish definition"; the `not_published` equivalence line reads "Its definition is not published yet. Publishing it changes nothing for students: it records the definition, so that the track can switch to running from it once the two are identical."; `render_publish()` takes `source`, and for a built-in track is headed "Publishing the definition of %s", says the track keeps running from its form, and its buttons read "Publish the definition" and "Unpublish the definition" (Check it against Airtable stays). A track of somebody's own keeps every word it had.

Decision 29's wording. A built-in track still running from its PHP is live for every student on it, whatever the store calls its unpublished definition: the product owner read "Draft" on four rows, one with 458 students, as tracks nobody could see, which is what the store's word meant and not what a person reads. The change follows source and state together (what this plan decides, 8): a built-in track whose definition is published keeps "Published", the switch and the equivalence line, because there the store's word and the person's agree.

The publish screen follows the same source, which the route now passes: what is published there is the definition, and the track itself stays as it is, so the heading, the sentence under it and the two buttons say so. Unpublishing a built-in track's definition takes nothing off the live site, so its button no longer says it does.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-builder.php b/bin/test-track-builder.php
index be69ae9..bbcac17 100644
--- a/bin/test-track-builder.php
+++ b/bin/test-track-builder.php
@@ -2400,5 +2400,69 @@ ck( 'History is offered on every row and beside Preview on the track\'s page',
     array( substr_count( $rows_html, 'wpcpm_history=13">History</a>' ), substr_count( $track_page, 'wpcpm_preview=13">Preview</a> <a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_history=13">History</a>' ) ),
     array( 1, 1 ) );
 
+
+echo "\n=== The words on a built-in row and on its publish screen (decision 29) ===\n";
+
+WPCPM_Track_Store::$tracks = array(
+	12 => array(
+		'definition'  => array( 'key' => 'design', 'status' => 'Designer Track', 'label' => 'Designer Track', 'course_url' => '', 'questions' => array( 'B' => array( 'type' => 'text', 'label' => 'B', 'group' => 'project' ) ) ),
+		'state'       => 'draft',
+		'source'      => 'builtin',
+		'log'         => array(),
+		'equivalence' => array( 'not_published' ),
+		'published'   => null,
+	),
+	13 => editable_track(),
+);
+WPCPM_Students_Sync::$counts             = array( 'Designer Track' => 458, 'Marketing Track' => 0 );
+$GLOBALS['opts']['wpcpm_tracks_skipped'] = array();
+
+ob_start();
+WPCPM_Track_Builder_Screen::render_list( array( 'rows' => WPCPM_Track_Builder::rows(), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
+$words = ob_get_clean();
+
+ck( 'a built-in track still on its PHP reads live rather than Draft, says what publishing its definition does, and offers Publish definition; a draft of somebody\'s own still reads Draft and offers Publish',
+    array(
+        substr_count( $words, '<td>Live, from its hand-written form' ),
+        substr_count( $words, '<td>Draft' ),
+        substr_count( $words, 'Its definition is not published yet. Publishing it changes nothing for students: it records the definition, so that the track can switch to running from it once the two are identical.' ),
+        substr_count( $words, '>Publish definition</a>' ),
+        substr_count( $words, '>Publish</a>' ),
+        substr_count( $words, '<td>458</td>' ),
+    ),
+    array( 1, 1, 1, 1, 1, 1 ) );
+
+WPCPM_Track_Publish::$flight    = array( 'refusals' => array(), 'warnings' => array(), 'columns' => array( 'create' => array(), 'ready' => array( 'B' ) ), 'choices' => array( 'reports' => 'ok', 'students' => 'ok' ), 'fields' => array( 'now' => 120, 'after' => 120 ), 'adds_status' => false, 'ready' => true );
+WPCPM_Track_Publish::$checklist = array();
+
+$_GET = array( 'wpcpm_publish' => 12 );
+ob_start();
+$tool->render_admin_page();
+$builtin_publish = ob_get_clean();
+WPCPM_Track_Store::$tracks[12]['state'] = 'published';
+ob_start();
+$tool->render_admin_page();
+$builtin_live = ob_get_clean();
+$_GET = array( 'wpcpm_publish' => 13 );
+ob_start();
+$tool->render_admin_page();
+$own_publish = ob_get_clean();
+$_GET = array();
+
+ck( 'its publish screen is headed as the definition\'s, says the track keeps running from its form, and its buttons name the definition; a track of somebody\'s own keeps its words',
+    array(
+        false !== strpos( $builtin_publish, '<h2>Publishing the definition of Designer Track</h2>' ),
+        false !== strpos( $builtin_publish, 'Publishing records its definition and changes nothing for students' ),
+        substr_count( $builtin_publish, 'Publish the definition' ),
+        substr_count( $builtin_publish, 'Publish this track' ),
+        substr_count( $builtin_live, 'Unpublish the definition' ),
+        substr_count( $builtin_live, 'Take it off the live site' ),
+        substr_count( $builtin_live, 'Check it against Airtable' ),
+        false !== strpos( $own_publish, '<h2>Publishing Marketing Track</h2>' ),
+        substr_count( $own_publish, 'Publish this track' ),
+        false !== strpos( $own_publish, 'keeps doing so' ),
+    ),
+    array( true, true, 1, 0, 1, 0, 1, true, 1, false ) );
+
 printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
 exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-builder.php`

Expected: `bin/test-track-builder.php` stops with `FAIL a built-in track still on its PHP reads live rather than Draft, says what publishing its definition does, and offers Publish definition; a draft of somebody's own still reads Draft and offers Publish`. The built-in row still reads Draft and offers Publish, and the publish screen is still headed as the track's.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/tools/class-wpcpm-track-builder-screen.php b/includes/tools/class-wpcpm-track-builder-screen.php
index a762ca5..a79216a 100644
--- a/includes/tools/class-wpcpm-track-builder-screen.php
+++ b/includes/tools/class-wpcpm-track-builder-screen.php
@@ -100,7 +100,14 @@ final class WPCPM_Track_Builder_Screen {
 
 		echo '</td>';
 
-		printf( '<td>%s', esc_html( self::state_label( (string) $row['state'] ) ) );
+		// A built-in track still running from its PHP is live for every student on it, whatever
+		// the store calls its unpublished definition: "Draft" read as a track nobody could see yet
+		// (the product owner, 13 September 2026; the design's decision 29).
+		$state = $builtin && 'draft' === (string) $row['state']
+			? __( 'Live, from its hand-written form', 'wpcredits-program-manager' )
+			: self::state_label( (string) $row['state'] );
+
+		printf( '<td>%s', esc_html( $state ) );
 		self::render_skipped( $skipped );
 		self::render_equivalence( $row );
 		echo '</td>';
@@ -162,9 +169,7 @@ final class WPCPM_Track_Builder_Screen {
 			printf(
 				'<a href="%1$s">%2$s</a> ',
 				esc_url( add_query_arg( 'wpcpm_publish', (int) $row['id'], $url ) ),
-				'draft' === $row['state']
-					? esc_html__( 'Publish', 'wpcredits-program-manager' )
-					: esc_html__( 'Publishing', 'wpcredits-program-manager' )
+				esc_html( self::publish_link_label( $row ) )
 			);
 		}
 
@@ -188,6 +193,23 @@ final class WPCPM_Track_Builder_Screen {
 		}
 	}
 
+	/**
+	 * Publish, or Publishing once it is; on a built-in track still on its PHP, "Publish
+	 * definition", since the track itself is live already (the design's decision 29).
+	 *
+	 * @param array $row One row.
+	 * @return string
+	 */
+	private static function publish_link_label( array $row ) {
+		if ( 'draft' !== (string) $row['state'] ) {
+			return __( 'Publishing', 'wpcredits-program-manager' );
+		}
+
+		return 'builtin' === (string) $row['source']
+			? __( 'Publish definition', 'wpcredits-program-manager' )
+			: __( 'Publish', 'wpcredits-program-manager' );
+	}
+
 	/**
 	 * Delete, behind a confirmation that names the track (decision 25).
 	 *
@@ -226,27 +248,36 @@ final class WPCPM_Track_Builder_Screen {
 		$flight    = isset( $args['preflight'] ) && is_array( $args['preflight'] ) ? $args['preflight'] : array();
 		$checklist = isset( $args['checklist'] ) && is_array( $args['checklist'] ) ? $args['checklist'] : array();
 		$can_make  = ! empty( $args['can_make'] );
+		$builtin   = isset( $args['source'] ) && 'builtin' === (string) $args['source'];
 		$url       = isset( $args['url'] ) ? (string) $args['url'] : '';
 
 		self::render_notice( isset( $args['flash'] ) && is_array( $args['flash'] ) ? $args['flash'] : array() );
 
-		echo '<h2>';
-		printf(
+		// On a built-in track still running from its PHP, what is published is the definition,
+		// and the track itself stays as it is (decision 29).
+		if ( $builtin ) {
 			/* translators: %s: the track's name. */
-			esc_html__( 'Publishing %s', 'wpcredits-program-manager' ),
-			esc_html( $label )
-		);
-		echo '</h2>';
+			$heading = __( 'Publishing the definition of %s', 'wpcredits-program-manager' );
+		} else {
+			/* translators: %s: the track's name. */
+			$heading = __( 'Publishing %s', 'wpcredits-program-manager' );
+		}
+
+		echo '<h2>' . esc_html( sprintf( $heading, $label ) ) . '</h2>';
 
 		if ( '' !== $url ) {
 			printf( '<p><a href="%1$s">%2$s</a></p>', esc_url( $url ), esc_html__( 'Back to the track list', 'wpcredits-program-manager' ) );
 		}
 
+		if ( $builtin ) {
+			echo '<p>' . esc_html__( 'This track runs from its hand-written form, and keeps doing so. Publishing records its definition and changes nothing for students; once the definition is identical to the form, the track can switch to running from it.', 'wpcredits-program-manager' ) . '</p>';
+		}
+
 		self::render_findings( $flight );
 		self::render_columns( $flight, $can_make );
 		self::render_adds_status( $flight );
 		self::render_checklist( $checklist, $track, isset( $flight['choices'] ) && is_array( $flight['choices'] ) ? $flight['choices'] : array() );
-		self::render_publish_actions( $flight, $state, $track, $can_make );
+		self::render_publish_actions( $flight, $state, $track, $can_make, $builtin );
 	}
 
 	/**
@@ -599,27 +630,38 @@ final class WPCPM_Track_Builder_Screen {
 	 * @param string $state    The track's state.
 	 * @param int    $track    The track.
 	 * @param bool   $can_make Whether a schema token is configured.
+	 * @param bool   $builtin  Whether the track still runs from its PHP, so the buttons name the definition.
 	 * @return void
 	 */
-	private static function render_publish_actions( array $flight, $state, $track, $can_make ) {
+	private static function render_publish_actions( array $flight, $state, $track, $can_make, $builtin = false ) {
 		echo '<p class="wpcpm-list__actions">';
 
 		$pending     = isset( $flight['columns']['create'] ) ? (array) $flight['columns']['create'] : array();
 		$can_publish = ! empty( $flight['ready'] ) && ( array() === $pending || $can_make );
 
 		if ( $can_publish && in_array( $state, array( 'draft', 'changed' ), true ) ) {
-			self::render_button(
-				WPCPM_Track_Builder::ACTION_PUBLISH,
-				$track,
-				'changed' === $state
-					? __( 'Publish the changes', 'wpcredits-program-manager' )
-					: __( 'Publish this track', 'wpcredits-program-manager' )
-			);
+			if ( 'changed' === $state ) {
+				$label = __( 'Publish the changes', 'wpcredits-program-manager' );
+			} elseif ( $builtin ) {
+				$label = __( 'Publish the definition', 'wpcredits-program-manager' );
+			} else {
+				$label = __( 'Publish this track', 'wpcredits-program-manager' );
+			}
+
+			self::render_button( WPCPM_Track_Builder::ACTION_PUBLISH, $track, $label );
 		}
 
 		if ( in_array( $state, array( 'published', 'changed' ), true ) ) {
 			self::render_button( WPCPM_Track_Builder::ACTION_VERIFY, $track, __( 'Check it against Airtable', 'wpcredits-program-manager' ) );
-			self::render_button( WPCPM_Track_Builder::ACTION_UNPUBLISH, $track, __( 'Take it off the live site', 'wpcredits-program-manager' ) );
+			// Unpublishing a built-in track's definition takes nothing off the live site: the
+			// track never left its PHP (decision 29).
+			self::render_button(
+				WPCPM_Track_Builder::ACTION_UNPUBLISH,
+				$track,
+				$builtin
+					? __( 'Unpublish the definition', 'wpcredits-program-manager' )
+					: __( 'Take it off the live site', 'wpcredits-program-manager' )
+			);
 		}
 
 		echo '</p>';
@@ -866,7 +908,7 @@ final class WPCPM_Track_Builder_Screen {
 		}
 
 		if ( array( 'not_published' ) === $differences ) {
-			echo '<br /><span class="wpcpm-tracks__equivalence">' . esc_html__( 'Not published yet, so there is nothing to compare with its hand-written form.', 'wpcredits-program-manager' ) . '</span>';
+			echo '<br /><span class="wpcpm-tracks__equivalence">' . esc_html__( 'Its definition is not published yet. Publishing it changes nothing for students: it records the definition, so that the track can switch to running from it once the two are identical.', 'wpcredits-program-manager' ) . '</span>';
 
 			return;
 		}
diff --git a/includes/tools/class-wpcpm-track-builder.php b/includes/tools/class-wpcpm-track-builder.php
index 4e2e405..7087a40 100644
--- a/includes/tools/class-wpcpm-track-builder.php
+++ b/includes/tools/class-wpcpm-track-builder.php
@@ -539,6 +539,7 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 					'track'     => $publish,
 					'label'     => isset( $held['label'] ) ? (string) $held['label'] : '',
 					'state'     => WPCPM_Track_Store::state( $publish ),
+					'source'    => WPCPM_Track_Store::source( $publish ),
 					'preflight' => WPCPM_Track_Publish::preflight( $publish ),
 					'checklist' => WPCPM_Track_Publish::checklist( $publish ),
 					'can_make'  => WPCPM_Settings::has_schema_token(),
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-builder.php`

Expected: `bin/test-track-builder.php` ends `ALL PASS (204 checks)`. 

- [ ] **Step 5: Run everything**

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
bash bin/check-standards.sh | tail -1
```

Expected: the battery prints nothing, then a line ending `all resolve`, a line ending `US English throughout.`, a line ending `0 dead.`, and `85 warnings, no errors.` (or fewer warnings).

- [ ] **Step 6: Commit**

```bash
git add bin/test-track-builder.php includes/tools/class-wpcpm-track-builder-screen.php includes/tools/class-wpcpm-track-builder.php
git commit -m "Track Builder T3b: the words on a built-in row and its publish screen"
```

---

### Task 9: The editor's fold-ins

**Files:**
- Modify: `includes/tools/class-wpcpm-track-editor-screen.php` (`render_question()` follows the stored control when locked; `render_identity_notices()`'s forked-from notice on a locked question)
- Test: `bin/test-track-builder.php`

**Interfaces:**
- Consumes: `question_form()`'s `locked` and `question`, `WPCPM_Track_Editor::posted_question( array $was )`, `WPCPM_Track_Definition::validate()` and `TEAM_COLUMN`.
- Produces: on a locked question, `render_question()` draws the rows of the stored control whatever the flash carries, with the stored control posted back as before; the forked-from notice on a locked question reads "A column of this track's own, forked from %s." and on a draft keeps its "Its name can still be changed until the track is published." The suite proves that what `posted_question()` builds for each of the ten controls, from a post as the question form sends it, is a question the real `validate()` accepts, each carrying its Airtable type.

The three remaining leftovers of decision 29. A lock can be taken while the question's screen is open - the track published in another tab, then a control change saved and refused - and the screen drew the typed control's rows on the way back, which are the rows of the very change the handler refuses; now the rows follow the stored control, as the identity block already did (what this plan decides, 9). The forked-from notice promised that a forked column's name "can still be changed until the track is published", on a question whose lock refuses exactly that; the clause goes on a locked question and stays on a draft.

The third is a check, not a change: T3a's suite proved `posted_question()` property by property against a stand-in, and this sends its output for all ten controls through the real validator, which the builder suite has loaded since Task 5. The team question is posted against `TEAM_COLUMN`, the only column it may write.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-builder.php b/bin/test-track-builder.php
index bbcac17..ce8ff8f 100644
--- a/bin/test-track-builder.php
+++ b/bin/test-track-builder.php
@@ -2464,5 +2464,74 @@ ck( 'its publish screen is headed as the definition\'s, says the track keeps run
     ),
     array( true, true, 1, 0, 1, 0, 1, true, 1, false ) );
 
+
+echo "\n=== The editor's fold-ins: a locked question's rows and its notice, and every control through the real validator (decision 29) ===\n";
+
+WPCPM_Track_Store::$tracks = array(
+	13 => editable_track(),
+	11 => array(
+		'definition'  => array( 'key' => '150h', 'status' => 'In Sensei', 'label' => '150-hour Track', 'questions' => array( 'Slack name' => array( 'type' => 'text', 'label' => 'Your Slack name', 'group' => 'onboarding' ) ) ),
+		'state'       => 'published',
+		'source'      => 'builtin',
+		'log'         => array(),
+		'equivalence' => array(),
+		'published'   => array( 'key' => '150h' ),
+	),
+);
+unset( WPCPM_Track_Store::$tracks[13]['definition']['questions']['Slack name'] );
+WPCPM_Track_Store::$tracks[13]['definition']['questions']['Slack name - marketing'] = array( 'type' => 'textarea', 'label' => 'Your Slack name', 'group' => 'onboarding', 'mono' => true );
+WPCPM_Track_Store::$tracks[13]['state']     = 'published';
+WPCPM_Track_Store::$tracks[13]['published'] = WPCPM_Track_Store::$tracks[13]['definition'];
+
+$raced = question_screen(
+	WPCPM_Track_Builder::question_form( 13, 'Hours' ),
+	array( 'status' => 'error', 'message' => 'This question has been published, so its control and its choices are fixed.', 'question_values' => array( 'column' => 'Hours', 'type' => 'text', 'label' => 'Hours', 'group' => 'hours', 'maxlength' => '5' ) )
+);
+
+ck( 'a locked question\'s rows follow its stored control after a lock race: the number\'s bounds, not the typed text box\'s length limit, with the stored control posted back',
+    array( substr_count( $raced, 'name="wpcpm_min"' ), substr_count( $raced, 'name="wpcpm_maxlength"' ), substr_count( $raced, 'name="wpcpm_type" value="number"' ), false !== strpos( $raced, 'its control and its choices are fixed' ) ),
+    array( 1, 0, 1, true ) );
+
+$forked_locked = question_screen( WPCPM_Track_Builder::question_form( 13, 'Slack name - marketing' ) );
+WPCPM_Track_Store::$tracks[13]['state']     = 'draft';
+WPCPM_Track_Store::$tracks[13]['published'] = null;
+$forked_free = question_screen( WPCPM_Track_Builder::question_form( 13, 'Slack name - marketing' ) );
+
+ck( 'the forked-from notice on a locked question no longer promises a rename, and on a draft still does',
+    array(
+        false !== strpos( $forked_locked, 'forked from Slack name.</p>' ),
+        false !== strpos( $forked_locked, 'can still be changed' ),
+        false !== strpos( $forked_free, 'forked from Slack name. Its name can still be changed until the track is published.</p>' ),
+    ),
+    array( true, false, true ) );
+
+$posts = array(
+	'text'     => array( 'wpcpm_maxlength' => '80' ),
+	'textarea' => array( 'wpcpm_mono' => '1' ),
+	'richtext' => array(),
+	'url'      => array(),
+	'email'    => array(),
+	'number'   => array( 'wpcpm_min' => '0', 'wpcpm_max' => '10', 'wpcpm_step' => '0.5' ),
+	'checkbox' => array(),
+	'select'   => array( 'wpcpm_options' => "Yes\nNo" ),
+	'image'    => array(),
+	'team'     => array(),
+);
+$verdicts = array();
+
+foreach ( $posts as $control => $extra ) {
+	$_POST    = array_merge( array( 'wpcpm_type' => $control, 'wpcpm_label' => 'Words', 'wpcpm_group' => 'project', 'wpcpm_help' => 'Help', 'wpcpm_row' => 'pair', 'wpcpm_stack' => '1', 'wpcpm_required' => '1' ), $extra );
+	$column   = 'team' === $control ? WPCPM_Track_Definition::TEAM_COLUMN : 'Column ' . $control;
+	$question = WPCPM_Track_Editor::posted_question( array() );
+	$errors   = WPCPM_Track_Definition::validate( array( 'schema_version' => 1, 'status' => 'Marketing Track', 'key' => 'marketing', 'label' => 'Marketing Track', 'hue' => 'blue', 'questions' => array( $column => $question ) ) );
+
+	$verdicts[ $control ] = array( array_column( $errors, 'code' ), isset( $question['airtable_type'] ) );
+}
+
+$_POST = array();
+
+ck( 'what posted_question() builds for each of the ten controls is a question the real validator accepts, each carrying its Airtable type',
+    $verdicts, array_fill_keys( array_keys( $posts ), array( array(), true ) ) );
+
 printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
 exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-builder.php`

Expected: `bin/test-track-builder.php` stops with `FAIL a locked question's rows follow its stored control after a lock race: the number's bounds, not the typed text box's length limit, with the stored control posted back`. The raced screen draws the typed text box's length limit where the stored number's bounds belong.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/tools/class-wpcpm-track-editor-screen.php b/includes/tools/class-wpcpm-track-editor-screen.php
index 496005a..1ba9c7d 100644
--- a/includes/tools/class-wpcpm-track-editor-screen.php
+++ b/includes/tools/class-wpcpm-track-editor-screen.php
@@ -132,6 +132,14 @@ class WPCPM_Track_Editor_Screen {
 		$type   = (string) $value( 'type' );
 		$labels = self::property_labels();
 
+		// A locked question's rows follow its stored control, not a typed one: a lock can be taken
+		// while this screen is open, and the typed control is the very change the handler refuses,
+		// so rows drawn for it would be the wrong rows (the design's decision 29). The identity
+		// block below posts the stored control back for the same reason.
+		if ( $locked ) {
+			$type = isset( $question['type'] ) ? (string) $question['type'] : '';
+		}
+
 		self::render_flash( $flash );
 
 		$back = sprintf(
@@ -293,11 +301,21 @@ class WPCPM_Track_Editor_Screen {
 		}
 
 		if ( ! empty( $form['forked_from'] ) ) {
-			$notice = sprintf(
-				/* translators: %s: the column this question forked from. */
-				__( 'A column of this track\'s own, forked from %s. Its name can still be changed until the track is published.', 'wpcredits-program-manager' ),
-				(string) $form['forked_from']
-			);
+			// Once the track is published the column is fixed, so the notice stops promising a
+			// rename it cannot keep (decision 29).
+			if ( $locked ) {
+				$notice = sprintf(
+					/* translators: %s: the column this question forked from. */
+					__( 'A column of this track\'s own, forked from %s.', 'wpcredits-program-manager' ),
+					(string) $form['forked_from']
+				);
+			} else {
+				$notice = sprintf(
+					/* translators: %s: the column this question forked from. */
+					__( 'A column of this track\'s own, forked from %s. Its name can still be changed until the track is published.', 'wpcredits-program-manager' ),
+					(string) $form['forked_from']
+				);
+			}
 
 			printf( '<p class="wpcpm-question__notice wpcpm-question__notice--forked">%s</p>', esc_html( $notice ) );
 		}
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-builder.php`

Expected: `bin/test-track-builder.php` ends `ALL PASS (207 checks)`. 

- [ ] **Step 5: Run everything**

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
bash bin/check-standards.sh | tail -1
```

Expected: the battery prints nothing, then a line ending `all resolve`, a line ending `US English throughout.`, a line ending `0 dead.`, and `85 warnings, no errors.` (or fewer warnings).

- [ ] **Step 6: Commit**

```bash
git add bin/test-track-builder.php includes/tools/class-wpcpm-track-editor-screen.php
git commit -m "Track Builder T3b: the editor's fold-ins"
```

---

### Task 10: Release 1.106.0

**Files:**
- Modify: `wpcredits-program-manager.php` (the `Version:` header and `WPCPM_VERSION`), `readme.txt` (`Stable tag:` and a changelog entry), `languages/wpcredits-program-manager.pot` (regenerated)

- [ ] **Step 1: Move the version.** `1.105.0` becomes `1.106.0` in the plugin header's `Version:` line, in `define( 'WPCPM_VERSION', ... )` and in `readme.txt`'s `Stable tag:`. Every other mention of 1.105.0 stays, including the changelog's own heading.

- [ ] **Step 2: Write the changelog entry**, first under `== Changelog ==` in `readme.txt`, above the previous entry and with one empty line after it:

```text
= 1.106.0 =

* The Track Builder previews a track: its form as a student sees it, with empty answers and no student's record, drawn in wp-admin through the Student Report Card's own renderer and stylesheet. Nothing typed on a preview is kept.
* A track can be started from nothing. New track, above the list, asks for its name, its Airtable status and its key, and opens the empty track for its questions. A new track, and a duplicate, take the first chip color no track holds.
* Every track has a History: what publishing would change, each save against the one before it (the questions added, removed and moved, and which properties of which question changed), and the publish log, with who and when.
* A built-in track still running from its hand-written form reads "Live, from its hand-written form" on the track list rather than "Draft", says that its definition is not published yet and what publishing it does, and offers "Publish definition". Its publish screen says the same.
* A question locked by publishing keeps its stored control on its screen even after a refused control change, and its forked-from notice no longer promises a rename. A store refusal about one column names that column on the publish screen.
```

- [ ] **Step 3: Regenerate the translation template.** `sh bin/make-pot.sh`. Expected: `Success: POT file successfully generated.` and a header reading `Project-Id-Version: WPCredits Program Manager 1.106.0`. (WP-CLI prints a deprecation notice from its own colors library first; it is not the plugin's.)

- [ ] **Step 4: Run everything.** Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 5: Build the zip and read it back.**

```bash
bash bin/build
unzip -p ../wpcredits-program-manager.zip wpcredits-program-manager/wpcredits-program-manager.php | grep "Version:"
unzip -Z1 ../wpcredits-program-manager.zip | grep -cE '^wpcredits-program-manager/(bin|docs)/'
unzip -Z1 ../wpcredits-program-manager.zip | grep -c 'class-wpcpm-track-history-screen.php'
```

Expected: `Version:           1.106.0`, `0`, and `1` (the new screen ships).

- [ ] **Step 6: Commit.**

```bash
git add wpcredits-program-manager.php readme.txt languages/wpcredits-program-manager.pot
git commit -m "Track Builder T3b: 1.106.0"
```

- [ ] **Step 7: Merge and mirror, on the product owner's choice.** Merge `track-builder-t3b` into `main` and run the battery on the result. Then push the source to the public mirror: pull the `WordPress/WPCredits` clone at `~/GitHub/Plugins/WPCredits-Tracker-mirror` (branch `trunk`), rsync the plugin into its `Education/WordPress Education Dashboard/wpcredits-program-manager/` with `rsync -a --delete --exclude '.git/' --exclude '.superpowers/' --exclude '.DS_Store' --exclude 'node_modules/' --exclude '*.zip' --exclude '.*.swp'`, update the version in that folder's `README.md` table, scan the added lines and the untracked files (`git ls-files --others --exclude-standard`, since `git status --porcelain` quotes a path with a space in it) for keys, record IDs, email addresses and names, read `git diff --stat`, then commit and push as two separate steps. This plan and its spec amendment travel with the source; neither holds a Liquid tag (a brace followed by a percent sign), which a Jekyll build of the mirror would fail on.

- [ ] **Step 8: Deploy only on the product owner's yes.** Ask first. On a yes, follow the deploy recorded for `wordpresseducation.org`: stream the zip over `ssh wpcredits-dashboard`, check its md5 on arrival, install it as a step of its own, read the version back, and purge the edge cache with `echo y | ssh wpcredits-dashboard 'wp edge-cache purge --domain --yes'`, which needs both the domain flag and an answer piped to its prompt. Before the install and after it, run one read-only check of what a person sees: the program map, the Programs running card drawn as a Program Administrator, the Student Report Card drawn as the TEST students, and the Track Builder's list drawn as a Program Administrator, with version strings and relative times normalized. The two runs must be identical except for the four built-in rows, whose state cell and publish link change by decision 29.

**The live site needs nothing new to take this release.** New track writes a WordPress post; Preview and History read posts and the base's stored schema, never Airtable; nothing reaches Airtable or a student until somebody publishes a track, and publishing is unchanged from 1.105.0 but for a finding's column name. Every track stays exactly as it is, and the four built-in rows change only their words.

---

## What T3b leaves for T3c

- **The Learn course entry point** (decision 21): New track from a Learn course link, and `learn_lesson_id` drawn and editable beside the lessons of the track's course, with "Add a question under this lesson" seeding `lead` and `learn_lesson_id` from the lesson.
- **The migration of the four built-in tracks** (section 12's T5 gate): publishing each definition, ticking its checklist and switching it, which is the product owner's to do on the live site with the screens this release completes. History and Preview are what a person checks before each press.
- **The Track Builder section of `docs/sections/32-admin-tools.md`**, once the screen is complete enough to describe in one pass, which T3c's entry point finishes.
- **Spec 7.4's "N students, 0 report rows" count** and **`wp wpcredits seed-tracks` exiting non-zero on a failed seed** stay where T2c left them.

## What this plan parks, with its reasons

- **A diff of values.** History names which properties of a question changed, not what they changed from and to (decision 28's "which of its properties changed"); a value diff would print choice lists and help text in full, and the question's own screen already shows the current value.
- **The two-step control change** from T3a stands: a question's screen draws the boxes of the control last typed, and the refusal names what is missing.
- **The duplicate form's three headings** are its own rather than `track_labels()`'s, because they say what the copy cannot share rather than what a row is called.
- **`forget_counts()` still has no production caller**, as T2c's final review noted; the counts are read once per page and the page is short-lived.
