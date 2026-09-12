# Track Builder T2b Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The Track Builder screen: every track with its state, the properties a Program Administrator may edit, a new track as a copy of an existing one, and the switch between a built-in track's hand-written form and its definition. Nothing on it reaches students, because publishing arrives in T2c.

**Architecture:** A tool rather than a module (`WPCPM_Track_Builder extends WPCPM_Tool`), so the Modules menu lists it beside the Student Duplicate Finder and its handlers do their own capability and nonce check in that order, which is decision 3.9. The tool answers presses and assembles rows; `WPCPM_Track_Builder_Screen` prints them, as the Duplicate Finder's screen does. Everything a row shows comes from the store, the students sync and the shipped seeds, and nothing here reads Airtable, which is what lets this release ship before the schema token exists.

**Tech Stack:** WordPress 6.5 and PHP 7.4 as floors, WordPress coding standards, the plugin's standalone `bin/test-*.php` suites, no build step and no JavaScript.

**Spec:** `docs/specs/2026-09-10-track-builder-design.md`, section 6 for the screen, section 12's T2b row, and decisions 11 to 15 in section 1.

## Global Constraints

- **Start from `main` at 1.102.0.** `grep "^Stable tag" readme.txt` prints `Stable tag: 1.102.0` and `git status --short` prints nothing. Then `git switch -c track-builder-t2b`.
- WordPress coding standards throughout: tabs, Yoda conditions, spaces inside parentheses, `array()` and never `[]`, strict `in_array()`. Everything that ships is PHP 7.4 compatible: no `match`, no named arguments, no union types. Every string a person reads is US English. No em dash or en dash anywhere, in code, comments, docs or commit messages: a plain hyphen. Full product names ("Student Report Card", "Mentor Report Card", "Administrator Dashboard").
- **The battery stays silent after every task:** `for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done` prints nothing. `php bin/check-references.php`, `php bin/check-spelling.php` and `php bin/check-dead-annotations.php` report clean. `bash bin/check-standards.sh` prints no line containing ` ERROR `, and its last line reads `85 warnings, no errors.` or a lower count. Four things it counts are easy to trip: the `=` of consecutive assignments align, the arrows of a multi-line array align, an associative array of more than one item takes one line an item, and a comment line between two assignments ends their alignment block.
- **Test first, every task:** add the checks, run them, see them fail as the step says, then write the code.
- **Nothing a person sees changes for students in T2b.** The screen is wp-admin only, gated on `wpcpm_manage_program`. No track is published by anything in this release, so `WPCPM_Program`, `WPCPM_Student_Report_Form::fields()`, the Programs running card and the Institutions Link control keep 1.102.0's answers.
- Every new class file is required in `wpcredits-program-manager.php` and in `uninstall.php`, in that order, or `bin/test-roles.php` fails.
- Nothing reads or writes Airtable in this release. The schema token, the preflight, publishing, the checklist and the institution gate are T2c's (spec section 12).
- Version numbers move in Task 9 only: plugin 1.103.0, which `version_compare()` orders after 1.102.0. No theme release.
- Comments explain why and name the decision or the review that made the rule. Every commit message starts with "Track Builder T2b:" and ends with the `Co-Authored-By:` trailer of whoever made it.

## What this plan decides

1. **A new track starts as a copy** (the design's decision 11). Blank tracks and the question editor are T3's, so Duplicate is how a Program Administrator makes a track, and the copy carries every question of the original.
2. **The editor asks the store the question Publish asks.** `WPCPM_Track_Store::check()` runs `validate()` against the same locked context `publish()` uses, because `WPCPM_Tracks::validation_context()` leaves a track's own status out for everybody (T1's decision 4), so a screen built on it would call a clash fine and Publish would refuse it on the next screen.
3. **A refusal keeps what the person typed.** The flash carries the attempted definition, and the form prefers it over the stored one, so nothing has to be retyped.
4. **A built-in draft refreshes itself** (decision 12) when the shipped `SEED_VERSION` is newer than the one the site recorded, and by hand from its row. Only a draft with no published copy, so nothing a student has seen changes.
5. **Trash is a state of its own.** `state()` names it, and `publish()` refuses it (T2a's final review, its M3), so the list never offers a button that can only fail.
6. **Saving the program settings compiles the tracks again** (decision 14).
7. **The row count of students comes from the sync's own meta**, not from Airtable, so the list works with no connection at all.

## File structure

**Created**

| File | What it holds |
| --- | --- |
| `includes/tools/class-wpcpm-track-builder.php` | The tool: its identity, the rows the list draws, the properties and duplicate forms as data, and the five handlers. |
| `includes/tools/class-wpcpm-track-builder-screen.php` | The markup: the list, the properties form, the duplicate form, and the notice. |
| `bin/test-track-builder.php` | The screen's suite, on stood-in collaborators. |

**Modified**

| File | Why |
| --- | --- |
| `includes/tracks/class-wpcpm-track-store.php` | The refresh, the trash rules, `check()`, `source()`, `switched()`, `duplicate()`, and `all_ids()` made public. |
| `includes/modules/class-wpcpm-students-sync.php` | `count_on_status()`. |
| `includes/class-wpcpm-settings.php` | Saving compiles. |
| `includes/class-wpcpm-tools.php` | The tool joins the registry. |
| `wpcredits-program-manager.php`, `uninstall.php` | The two new class files. |
| `bin/test-track-store.php`, `bin/test-students-sync.php`, `bin/test-settings.php` | Their tasks' checks. |
| `readme.txt`, `languages/wpcredits-program-manager.pot` | The release. |

---

### Task 1: The store's own rules

**Files:**
- Modify: `includes/tracks/class-wpcpm-track-store.php`
- Test: `bin/test-track-store.php`

**Interfaces:**
- Consumes: `WPCPM_Track_Store::seeds()`, `create()`, `state()`, `publish()` as T2a left them.
- Produces: `WPCPM_Track_Store::refresh_builtin( int $post_id ): int|WP_Error`, `refresh_builtins(): array`, `state()` answering `trash`, and `publish()` refusing `wpcpm_track_trashed`.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-store.php b/bin/test-track-store.php
index bb2fa8f..7c84f06 100644
--- a/bin/test-track-store.php
+++ b/bin/test-track-store.php
@@ -473,6 +473,71 @@ ck( 'and writes the index, empty and autoloaded, so no request asks for an optio
 WPCPM_Track_Store::maybe_seed();
 ck( 'and never again', count( $GLOBALS['posts'] ), 4 );
 
+echo "\n=== Trash, and a post that is not a track ===\n";
+
+// `publish()` read the definition and never the post, so a track somebody trashed published again
+// from the trash (the final review of T2a, M3), and `state()` called it a draft.
+$trashed = WPCPM_Track_Store::create( track( 'Trashed Track', 'trashed' ) );
+wp_update_post( array( 'ID' => $trashed, 'post_status' => 'trash' ) );
+$plain = wp_insert_post( array( 'post_type' => 'post' ) );
+
+$refused = WPCPM_Track_Store::publish( $trashed );
+ck( 'publish() refuses a track in the trash', is_wp_error( $refused ) ? $refused->get_error_code() : $refused, 'wpcpm_track_trashed' );
+ck( 'state() says trash, and says nothing at all for a post that is not a track',
+    array( WPCPM_Track_Store::state( $trashed ), WPCPM_Track_Store::state( $plain ) ),
+    array( 'trash', '' ) );
+
+echo "\n=== Refreshing a built-in draft from the seed ===\n";
+
+// A release that edits a hand-written form leaves every site's built-in draft behind it, and since
+// 1.101.1 the store refuses to save a built-in track, so nothing else can bring one back into line
+// (the design's decision 12). Only a draft nobody has published is touched.
+$by_key = array();
+
+foreach ( array_keys( $GLOBALS['posts'] ) as $id ) {
+	$held = WPCPM_Track_Store::get( $id );
+
+	if ( is_array( $held ) && isset( $held['key'] ) ) {
+		$by_key[ (string) $held['key'] ] = $id;
+	}
+}
+
+$design         = $by_key['design'];
+$stale          = WPCPM_Track_Store::get( $design );
+$stale['label'] = 'Designer Track, left behind';
+update_post_meta( $design, WPCPM_Track_Store::META_DEFINITION, wp_slash( WPCPM_Track_Definition::encode( $stale ) ) );
+
+ck( 'a built-in draft is refreshed from the seed the plugin ships',
+    array( WPCPM_Track_Store::refresh_builtin( $design ), WPCPM_Track_Store::get( $design ) === WPCPM_Track_Store::seeds()['design'] ),
+    array( $design, true ) );
+ck( 'and the post title follows the seed', get_post( $design )->post_title, WPCPM_Track_Store::seeds()['design']['label'] );
+
+$mine = WPCPM_Track_Store::create( track( 'Marketing Track', 'marketing' ) );
+$refused = WPCPM_Track_Store::refresh_builtin( $mine );
+ck( 'a track that was never built in is not refreshed', is_wp_error( $refused ) ? $refused->get_error_code() : $refused, 'wpcpm_track_not_builtin' );
+
+$published = $by_key['150h'];
+update_post_meta( $published, WPCPM_Track_Store::META_PUBLISHED, wp_slash( WPCPM_Track_Definition::encode( WPCPM_Track_Store::get( $published ) ) ) );
+$refused = WPCPM_Track_Store::refresh_builtin( $published );
+ck( 'nor is one that has been published: students may be reading it', is_wp_error( $refused ) ? $refused->get_error_code() : $refused, 'wpcpm_track_published' );
+
+// The version the site recorded is how it knows a release moved the seeds under it.
+$stale          = WPCPM_Track_Store::get( $by_key['dev'] );
+$stale['label'] = 'Developer Track, left behind';
+update_post_meta( $by_key['dev'], WPCPM_Track_Store::META_DEFINITION, wp_slash( WPCPM_Track_Definition::encode( $stale ) ) );
+update_option( WPCPM_Track_Store::OPT_SEEDED, WPCPM_Track_Store::SEED_VERSION - 1, true );
+WPCPM_Track_Store::maybe_seed();
+
+ck( 'a newer seed version refreshes the drafts and records itself',
+    array( WPCPM_Track_Store::get( $by_key['dev'] ) === WPCPM_Track_Store::seeds()['dev'], get_option( WPCPM_Track_Store::OPT_SEEDED ) ),
+    array( true, WPCPM_Track_Store::SEED_VERSION ) );
+ck( 'and the published one it passed over keeps what it was published with',
+    WPCPM_Track_Store::get( $published ) === WPCPM_Track_Store::published( $published ), true );
+
+$count = count( $GLOBALS['posts'] );
+WPCPM_Track_Store::maybe_seed();
+ck( 'running again on the same version creates nothing and refreshes nothing', count( $GLOBALS['posts'] ), $count );
+
 echo "\n=== The switch between a built-in track's PHP and its definition ===\n";
 
 $GLOBALS['posts'] = array();
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-store.php`
Expected: `FAIL publish() refuses a track in the trash`, `FAIL state() says trash, and says nothing at all for a post that is not a track`, then `PHP Fatal error: Uncaught Error: Call to undefined method WPCPM_Track_Store::refresh_builtin()`.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/tracks/class-wpcpm-track-store.php b/includes/tracks/class-wpcpm-track-store.php
index 025fd07..f98f30f 100644
--- a/includes/tracks/class-wpcpm-track-store.php
+++ b/includes/tracks/class-wpcpm-track-store.php
@@ -359,6 +359,15 @@ final class WPCPM_Track_Store {
 			return new WP_Error( 'wpcpm_track_missing', __( 'That track does not exist.', 'wpcredits-program-manager' ) );
 		}
 
+		// The definition outlives the trash, so without this a trashed track published straight
+		// out of it, and the track list would show a live track nobody could find (T2a's final
+		// review, its M3).
+		$post = self::track_post( $post_id );
+
+		if ( $post instanceof WP_Post && 'trash' === $post->post_status ) {
+			return new WP_Error( 'wpcpm_track_trashed', __( 'That track is in the trash. Restore it before publishing it.', 'wpcredits-program-manager' ) );
+		}
+
 		$others = array();
 
 		foreach ( self::published_posts() as $post ) {
@@ -465,7 +474,8 @@ final class WPCPM_Track_Store {
 	 *
 	 * @param int $post_id The track.
 	 * @return string `draft`; `published`; `changed` for a published track with a saved edit not
-	 *                yet published; or an empty string for a post that is not a track.
+	 *                yet published; `trash` for a trashed track; or an empty string for a post that
+	 *                is not a track.
 	 */
 	public static function state( $post_id ) {
 		$post = self::track_post( $post_id );
@@ -474,6 +484,13 @@ final class WPCPM_Track_Store {
 			return '';
 		}
 
+		// Named rather than folded into `draft`: the track list offers Publish on a draft, and
+		// `publish()` refuses a trashed one, so a row that called itself a draft offered a button
+		// that could only fail (T2a's final review, its M3).
+		if ( 'trash' === $post->post_status ) {
+			return 'trash';
+		}
+
 		if ( 'publish' !== $post->post_status ) {
 			return 'draft';
 		}
@@ -614,7 +631,21 @@ final class WPCPM_Track_Store {
 	 * options rather than asking the database for an option that does not exist yet.
 	 */
 	public static function maybe_seed() {
-		if ( get_option( self::OPT_SEEDED ) || ! add_option( self::OPT_SEEDED, self::SEED_VERSION, '', true ) ) {
+		$seeded = get_option( self::OPT_SEEDED, null );
+
+		if ( null !== $seeded ) {
+			// A release that edits a hand-written form ships new seeds with a new version, and the
+			// drafts this site made from the old ones are behind it. Nobody can bring them back by
+			// hand, because the store refuses to save a built-in track (the design's decision 12).
+			if ( (int) $seeded < self::SEED_VERSION ) {
+				self::refresh_builtins();
+				update_option( self::OPT_SEEDED, self::SEED_VERSION, true );
+			}
+
+			return;
+		}
+
+		if ( ! add_option( self::OPT_SEEDED, self::SEED_VERSION, '', true ) ) {
 			return;
 		}
 
@@ -622,6 +653,85 @@ final class WPCPM_Track_Store {
 		self::compile();
 	}
 
+	/**
+	 * Put every built-in draft back to the seed the plugin ships now.
+	 *
+	 * Only a draft with no published copy: a track students may have been reading keeps what it
+	 * was published with, and its own refresh is a republish, which is the screen's business.
+	 *
+	 * @return array Track key => the post ID refreshed, or the WP_Error that stopped it.
+	 */
+	public static function refresh_builtins() {
+		$done = array();
+
+		foreach ( self::all_ids() as $post_id ) {
+			if ( 'builtin' !== get_post_meta( $post_id, self::META_SOURCE, true ) || '' !== (string) get_post_meta( $post_id, self::META_PUBLISHED, true ) ) {
+				continue;
+			}
+
+			$held = self::get( $post_id );
+			$key  = is_array( $held ) && isset( $held['key'] ) ? (string) $held['key'] : '';
+
+			$done[ $key ] = self::refresh_builtin( $post_id );
+		}
+
+		return $done;
+	}
+
+	/**
+	 * Put one built-in draft back to the seed the plugin ships now.
+	 *
+	 * The one write that may touch a built-in track's definition: `save()` refuses them, so that a
+	 * seed cannot be pointed at another track (T2a's final review, its I2), and this writes the
+	 * shipped seed and nothing a person typed.
+	 *
+	 * @param int $post_id The track.
+	 * @return int|WP_Error The post ID, or why it was not refreshed.
+	 */
+	public static function refresh_builtin( $post_id ) {
+		$post_id = (int) $post_id;
+
+		if ( null === self::track_post( $post_id ) ) {
+			return new WP_Error( 'wpcpm_track_missing', __( 'That track does not exist.', 'wpcredits-program-manager' ) );
+		}
+
+		if ( 'builtin' !== get_post_meta( $post_id, self::META_SOURCE, true ) ) {
+			return new WP_Error( 'wpcpm_track_not_builtin', __( 'Only a built-in track its PHP still runs is refreshed from the seed.', 'wpcredits-program-manager' ) );
+		}
+
+		if ( '' !== (string) get_post_meta( $post_id, self::META_PUBLISHED, true ) ) {
+			return new WP_Error( 'wpcpm_track_published', __( 'That track has been published, so its definition stays as it is: students may be reading it.', 'wpcredits-program-manager' ) );
+		}
+
+		$held  = self::get( $post_id );
+		$key   = is_array( $held ) && isset( $held['key'] ) ? (string) $held['key'] : '';
+		$seeds = self::seeds();
+
+		if ( ! isset( $seeds[ $key ] ) ) {
+			return new WP_Error( 'wpcpm_track_no_seed', __( 'The plugin ships no seed for that track, so there is nothing to refresh it from.', 'wpcredits-program-manager' ) );
+		}
+
+		$json = WPCPM_Track_Definition::encode( $seeds[ $key ] );
+
+		if ( '' === $json ) {
+			return self::unencodable();
+		}
+
+		update_post_meta( $post_id, self::META_DEFINITION, wp_slash( $json ) );
+
+		$updated = wp_update_post(
+			wp_slash(
+				array(
+					'ID'         => $post_id,
+					'post_title' => isset( $seeds[ $key ]['label'] ) ? (string) $seeds[ $key ]['label'] : '',
+				)
+			),
+			true
+		);
+
+		return is_wp_error( $updated ) ? $updated : $post_id;
+	}
+
 	/**
 	 * Create the four built-in tracks' definitions from the seeds the plugin ships.
 	 *
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-store.php`
Expected: `ALL PASS (121 checks)`.

- [ ] **Step 5: Run everything**

Run the battery, the three checks and `bash bin/check-standards.sh`.
Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 6: Commit**

```bash
git add includes/tracks/class-wpcpm-track-store.php bin/test-track-store.php
git commit -m "Track Builder T2b: the store refreshes a built-in draft, refuses to publish out of the trash, and names trash"
```

---

### Task 2: One question, asked by the editor and by Publish

**Files:**
- Modify: `includes/tracks/class-wpcpm-track-store.php`
- Test: `bin/test-track-store.php`

**Interfaces:**
- Produces: `WPCPM_Track_Store::check( int $post_id, array $definition ): array[]`, the rules a definition fails, empty when it would publish. `publish()` now calls it, so the two can never drift apart.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-store.php b/bin/test-track-store.php
index 7c84f06..e7a0f04 100644
--- a/bin/test-track-store.php
+++ b/bin/test-track-store.php
@@ -352,6 +352,22 @@ $seed['label'] = 'Designer Track';
 ck( 'a built-in track\'s own definition keeps its status, its key and its name', publish_new( $seed, 'builtin' ), array( 'published', array(), 'published' ) );
 ck( 'and is compiled as built-in, so its PHP keeps running it', WPCPM_Tracks::rows()['Designer Track']['source'], 'builtin' );
 
+// The editor asks the same question Publish will ask, because `validation_context()` leaves every
+// track's own status out for everybody (T1's decision 4 hole), so an editor built on it would call
+// a clash fine and Publish would refuse it on the next screen.
+$fine = WPCPM_Track_Store::create( track( 'Delta Track', 'delta' ) );
+ck( 'check() answers nothing for a definition that would publish', WPCPM_Track_Store::check( $fine, WPCPM_Track_Store::get( $fine ) ), array() );
+
+$twin   = WPCPM_Track_Store::create( track( 'Alpha Track', 'twin' ) );
+$looked = array_column( WPCPM_Track_Store::check( $twin, WPCPM_Track_Store::get( $twin ) ), 'code' );
+$tried  = WPCPM_Track_Store::publish( $twin );
+ck( 'it sees another published track\'s status', in_array( 'status_taken', $looked, true ), true );
+ck( 'and names exactly the rules publish() names, so the editor and Publish cannot disagree',
+    $looked === array_column( $tried->get_error_data()['errors'], 'code' ), true );
+
+ck( 'a published track checking the copy it was published with is not refused its own status',
+    WPCPM_Track_Store::check( $alpha, WPCPM_Track_Store::published( $alpha ) ), array() );
+
 echo "\n=== What compile() leaves out ===\n";
 
 /** Write a published copy by hand, as nothing but a bug or a hand edit would. */
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-store.php`
Expected: `PHP Fatal error: Uncaught Error: Call to undefined method WPCPM_Track_Store::check()`.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/tracks/class-wpcpm-track-store.php b/includes/tracks/class-wpcpm-track-store.php
index f98f30f..65f063b 100644
--- a/includes/tracks/class-wpcpm-track-store.php
+++ b/includes/tracks/class-wpcpm-track-store.php
@@ -368,17 +368,7 @@ final class WPCPM_Track_Store {
 			return new WP_Error( 'wpcpm_track_trashed', __( 'That track is in the trash. Restore it before publishing it.', 'wpcredits-program-manager' ) );
 		}
 
-		$others = array();
-
-		foreach ( self::published_posts() as $post ) {
-			$copy = (int) $post->ID !== $post_id ? self::published( $post->ID ) : null;
-
-			if ( is_array( $copy ) ) {
-				$others[] = $copy;
-			}
-		}
-
-		$errors = WPCPM_Track_Definition::validate( $definition, self::context( $post_id, $definition, $others, true ) );
+		$errors = self::check( $post_id, $definition );
 
 		if ( array() !== $errors ) {
 			return new WP_Error( 'wpcpm_track_invalid', $errors[0]['message'], array( 'errors' => $errors ) );
@@ -455,6 +445,33 @@ final class WPCPM_Track_Store {
 		return (int) $post_id;
 	}
 
+	/**
+	 * What publishing this definition would refuse, without publishing it.
+	 *
+	 * The editor's question and Publish's question are the same call, because they have to give the
+	 * same answer: `WPCPM_Tracks::validation_context()` leaves a track's own status out for
+	 * everybody (T1's decision 4), so a screen built on that would call a clash fine and Publish
+	 * would refuse it on the next screen. Read-only: nothing is stored.
+	 *
+	 * @param int   $post_id    The track the definition belongs to.
+	 * @param array $definition The definition to check.
+	 * @return array[] The rules it fails, as `validate()` answers them; empty when it would publish.
+	 */
+	public static function check( $post_id, array $definition ) {
+		$post_id = (int) $post_id;
+		$others  = array();
+
+		foreach ( self::published_posts() as $post ) {
+			$copy = (int) $post->ID !== $post_id ? self::published( $post->ID ) : null;
+
+			if ( is_array( $copy ) ) {
+				$others[] = $copy;
+			}
+		}
+
+		return WPCPM_Track_Definition::validate( $definition, self::context( $post_id, $definition, $others, true ) );
+	}
+
 	/**
 	 * A track's definition as it was last published.
 	 *
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-store.php`
Expected: `ALL PASS (125 checks)`.

- [ ] **Step 5: Run everything**

Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 6: Commit**

```bash
git add includes/tracks/class-wpcpm-track-store.php bin/test-track-store.php
git commit -m "Track Builder T2b: the editor and Publish ask one question"
```

---

### Task 3: How many students are on a track

**Files:**
- Modify: `includes/modules/class-wpcpm-students-sync.php`
- Test: `bin/test-students-sync.php`

**Interfaces:**
- Produces: `WPCPM_Students_Sync::count_on_status( string $status ): int`, counting the students this sync provisioned whose program names that status. T2c's unpublish guard reads the same number.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-students-sync.php b/bin/test-students-sync.php
index 83ecdb3..736770f 100644
--- a/bin/test-students-sync.php
+++ b/bin/test-students-sync.php
@@ -1362,5 +1362,18 @@ foreach ( $lasting as $what => $error ) {
 
 unset( $GLOBALS['fetch_error'] );
 
+echo "\n=== Counting the students on a track ===\n";
+
+// The Track Builder's list shows how many students each track has now, and T2c refuses to
+// unpublish a track while any synced student still holds its status (spec 7.5).
+update_user_meta( 900, WPCPM_Students_Sync::META_PROGRAM, array( 'program' => 'Counting Track' ) );
+update_user_meta( 901, WPCPM_Students_Sync::META_PROGRAM, array( 'program' => 'Counting Track' ) );
+update_user_meta( 902, WPCPM_Students_Sync::META_PROGRAM, array( 'program' => 'Other Track' ) );
+
+ck( 'the students on a track are counted', WPCPM_Students_Sync::count_on_status( 'Counting Track' ), 2 );
+ck( 'a track nobody holds counts none, and so does no status at all',
+    array( WPCPM_Students_Sync::count_on_status( 'Writing Track' ), WPCPM_Students_Sync::count_on_status( '' ) ),
+    array( 0, 0 ) );
+
 printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
 exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-students-sync.php`
Expected: `PHP Fatal error: Uncaught Error: Call to undefined method WPCPM_Students_Sync::count_on_status()`.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/modules/class-wpcpm-students-sync.php b/includes/modules/class-wpcpm-students-sync.php
index 5168484..00e98ba 100644
--- a/includes/modules/class-wpcpm-students-sync.php
+++ b/includes/modules/class-wpcpm-students-sync.php
@@ -2466,6 +2466,48 @@ class WPCPM_Students_Sync {
 		return true;
 	}
 
+	/**
+	 * How many synced students hold a track's status now.
+	 *
+	 * The Track Builder's list shows it on every row, and unpublishing is refused while it is not
+	 * zero (spec 7.5). It counts the students this sync provisioned rather than the rows in the
+	 * base, because those are the people who would lose their page if the track left the site.
+	 *
+	 * @param string $status The track's Airtable status.
+	 * @return int
+	 */
+	public static function count_on_status( $status ) {
+		$status = (string) $status;
+
+		if ( '' === $status ) {
+			return 0;
+		}
+
+		$count = 0;
+		$users = get_users(
+			array(
+				'number'     => -1,
+				'fields'     => 'ID',
+				'meta_query' => array(
+					array(
+						'key'     => self::META_PROGRAM,
+						'compare' => 'EXISTS',
+					),
+				),
+			)
+		);
+
+		foreach ( $users as $user_id ) {
+			$program = get_user_meta( (int) $user_id, self::META_PROGRAM, true );
+
+			if ( is_array( $program ) && isset( $program['program'] ) && (string) $program['program'] === $status ) {
+				++$count;
+			}
+		}
+
+		return $count;
+	}
+
 	/**
 	 * The contact card for a student's mentor.
 	 *
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-students-sync.php`
Expected: `ALL PASS (172 checks)`.

- [ ] **Step 5: Run everything**

Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 6: Commit**

```bash
git add includes/modules/class-wpcpm-students-sync.php bin/test-students-sync.php
git commit -m "Track Builder T2b: how many students hold a track's status"
```

---

### Task 4: Saving the settings compiles the tracks again

**Files:**
- Modify: `includes/class-wpcpm-settings.php`
- Test: `bin/test-settings.php`

**Interfaces:**
- Consumes: `WPCPM_Track_Store::compile()`, guarded by `class_exists()` so the settings screen keeps working in a suite that does not load the store.

- [ ] **Step 1: Write the failing check**

```diff
diff --git a/bin/test-settings.php b/bin/test-settings.php
index 9fcefdf..e9159ea 100644
--- a/bin/test-settings.php
+++ b/bin/test-settings.php
@@ -741,6 +741,25 @@ echo "\n=== The Student Duplicate Finder's switch (1.102.0) ===\n";
 ck( 'deleting duplicates is off until a manager turns it on', isset( WPCPM_Settings::defaults()['duplicate_delete_enabled'] ) ? WPCPM_Settings::defaults()['duplicate_delete_enabled'] : null, false );
 ck( 'and the switch is a checkbox of its own on the settings screen', false !== strpos( $admin, 'name="duplicate_delete_enabled"' ), true );
 
+echo "\n=== Saving the settings compiles the tracks again ===\n";
+
+// "Currently mentoring" and the past statuses are rules every published track was compiled
+// against, so a save that changed them left the live site running the last compile's answer until
+// something unrelated published (the design's decision 14).
+class WPCPM_Track_Store {
+	public static $compiled = 0;
+
+	public static function compile() {
+		++self::$compiled;
+
+		return array();
+	}
+}
+
+$compiled_before = WPCPM_Track_Store::$compiled;
+WPCPM_Settings::save( array( 'student_statuses' => array( 'In Sensei' ) ) );
+ck( 'saving the settings compiles the tracks again', WPCPM_Track_Store::$compiled - $compiled_before, 1 );
+
 echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
 
 exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php bin/test-settings.php`
Expected: `FAIL saving the settings compiles the tracks again`, with `got: 0` and `want: 1`.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/class-wpcpm-settings.php b/includes/class-wpcpm-settings.php
index 9be1dca..fc6cf5a 100644
--- a/includes/class-wpcpm-settings.php
+++ b/includes/class-wpcpm-settings.php
@@ -486,6 +486,13 @@ class WPCPM_Settings {
 			WPCPM_Mentor_Checker_Runner::sync_cron( $clean['checker_cron_enabled'] );
 		}
 
+		// "Currently mentoring" and the past statuses are rules every published track was compiled
+		// against, so a save that changes them would otherwise leave the live tracks, and the list
+		// of what the last compile left out, answering for the settings as they were (decision 14).
+		if ( class_exists( 'WPCPM_Track_Store' ) ) {
+			WPCPM_Track_Store::compile();
+		}
+
 		return $clean;
 	}
 
```

- [ ] **Step 4: Run it and watch it pass**

Run: `php bin/test-settings.php`
Expected: `ALL PASS`.

- [ ] **Step 5: Run everything**

Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 6: Commit**

```bash
git add includes/class-wpcpm-settings.php bin/test-settings.php
git commit -m "Track Builder T2b: saving the settings compiles the tracks again"
```

---

### Task 5: The tool, its rows and its list

**Files:**
- Create: `includes/tools/class-wpcpm-track-builder.php`, `includes/tools/class-wpcpm-track-builder-screen.php`, `bin/test-track-builder.php`
- Modify: `includes/tracks/class-wpcpm-track-store.php`, `includes/class-wpcpm-tools.php`, `wpcredits-program-manager.php`, `uninstall.php`

**Interfaces:**
- Consumes: `WPCPM_Track_Store::all_ids()`, `get()`, `state()`, `source()`, `published()`, `log_entries()`, `equivalence()`, `seeds()`, `refresh_builtin()`; `WPCPM_Students_Sync::count_on_status()`; `WPCPM_Request::posted_id()`; `WPCPM_Flash::set()` and `take()`.
- Produces: `WPCPM_Track_Builder::rows(): array[]`, each row carrying `id`, `label`, `status`, `key`, `course`, `source`, `state`, `students`, `published_by`, `published_at`, `skipped`, `equivalence` and `stale`; `WPCPM_Track_Builder::ACTION_REFRESH` and `FLASH`; `WPCPM_Track_Builder_Screen::render_list( array $args )` taking `rows` and `flash`. Tasks 6 to 8 add to both.

- [ ] **Step 1: Write the failing suite**

Create `bin/test-track-builder.php`:

```php
<?php
/**
 * The Track Builder screen (phase T2b): the rows it shows, and the markup it draws them in.
 *
 * The list is the first thing a Program Administrator sees of the Track Builder, and every answer
 * on it comes from somewhere else: the store says what state a track is in and what the last
 * compile left out, the students sync says how many people are on it, and the seeds say whether a
 * built-in draft has fallen behind its PHP. So the collaborators are stood in for here and the
 * screen is held to what it does with their answers.
 *
 * Run from the plugin root:  php bin/test-track-builder.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', '1.103.0' );

function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function number_format_i18n( $n ) { return (string) $n; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . rawurlencode( $key ) . '=' . rawurlencode( $value ); }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $action ) . '" />'; }
function current_user_can( $cap ) { return ! empty( $GLOBALS['can_manage'] ); }
function check_admin_referer( $action ) { if ( ( $GLOBALS['nonce'] ?? '' ) !== $action ) { throw new DieSignal( 'the nonce was refused' ); } return true; }
function wp_safe_redirect( $url ) { throw new RedirectSignal( (string) $url ); }
function wp_die( $message = '', $title = '', $args = array() ) { throw new DieSignal( is_string( $message ) ? $message : '' ); }
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][] = $hook; return true; }
function get_userdata( $id ) { return isset( $GLOBALS['users'][ (int) $id ] ) ? (object) array( 'display_name' => $GLOBALS['users'][ (int) $id ] ) : false; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }

class RedirectSignal extends Exception {}
class DieSignal extends Exception {}

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

class WPCPM_Request {
	public static function posted_id( $key ) { return (int) ( $_POST[ $key ] ?? 0 ); }
}

$GLOBALS['can_manage'] = true;
$GLOBALS['nonce']      = '';
$GLOBALS['hooks']      = array();
$GLOBALS['opts']  = array();
$GLOBALS['users'] = array( 7 => 'A Manager' );

/** The store, as the screen uses it: definitions, states, logs, equivalence and the seeds. */
class WPCPM_Track_Store {
	const META_SOURCE = '_wpcpm_track_source';
	const OPT_SKIPPED = 'wpcpm_tracks_skipped';

	public static $tracks = array();

	public static function all_ids() {
		return array_keys( self::$tracks );
	}

	public static function get( $post_id ) {
		return self::$tracks[ $post_id ]['definition'] ?? null;
	}

	public static function state( $post_id ) {
		return self::$tracks[ $post_id ]['state'] ?? '';
	}

	public static function source( $post_id ) {
		return self::$tracks[ $post_id ]['source'] ?? '';
	}

	public static function log_entries( $post_id ) {
		return self::$tracks[ $post_id ]['log'] ?? array();
	}

	public static function equivalence( $post_id ) {
		return self::$tracks[ $post_id ]['equivalence'] ?? array( 'not_builtin' );
	}

	public static function published( $post_id ) {
		return self::$tracks[ $post_id ]['published'] ?? null;
	}

	public static $refreshed = array();

	public static function refresh_builtin( $post_id ) {
		self::$refreshed[] = (int) $post_id;

		return isset( self::$tracks[ $post_id ] ) ? (int) $post_id : new WP_Error( 'wpcpm_track_missing', 'That track does not exist.' );
	}

	public static function seeds() {
		return array( 'design' => array( 'key' => 'design', 'status' => 'Designer Track', 'label' => 'Designer Track', 'questions' => array( 'A' => 1 ) ) );
	}
}

/** The runtime stores, for what the last compile left out. */
class WPCPM_Tracks {
	const OPT_TRACKS = 'wpcpm_tracks';
}

/** The students sync, for how many people are on a track now. */
class WPCPM_Students_Sync {
	public static $counts = array();

	public static function count_on_status( $status ) {
		return (int) ( self::$counts[ $status ] ?? 0 );
	}
}

class WPCPM_Roles {
	const CAP_MANAGE = 'wpcpm_manage_program';
}

class WPCPM_Flash {
	public static $set = array();

	public static function set( $key, $value ) {
		self::$set[ $key ] = $value;
	}

	public static function take( $key ) {
		return $GLOBALS['flash'] ?? array();
	}
}

require_once __DIR__ . '/../includes/tools/class-wpcpm-tool.php';
require_once __DIR__ . '/../includes/tools/class-wpcpm-track-builder-screen.php';
require_once __DIR__ . '/../includes/tools/class-wpcpm-track-builder.php';

$fail  = 0;
$total = 0;

/**
 * One check.
 *
 * @param string $label    What is being checked.
 * @param mixed  $actual   What the code answered.
 * @param mixed  $expected What it should answer.
 */
function ck( $label, $actual, $expected ) {
	global $fail, $total;
	++$total;
	$ok = $actual === $expected;
	if ( ! $ok ) {
		++$fail;
		echo "FAIL $label\n  got:  " . str_replace( "\n", ' ', var_export( $actual, true ) ) . "\n  want: " . str_replace( "\n", ' ', var_export( $expected, true ) ) . "\n";
		return;
	}
	echo "ok $label\n";
}

echo "=== The tool itself ===\n";

$tool = new WPCPM_Track_Builder();
ck( 'it is a tool, so the Modules menu lists it', $tool instanceof WPCPM_Tool, true );
ck( 'with its own id and page', array( $tool->id(), $tool->page_slug() ), array( 'track-builder', 'wpcpm-tool-track-builder' ) );
ck( 'and it does not need Airtable to draw its list', $tool->is_ready(), true );

echo "\n=== The rows the list shows ===\n";

WPCPM_Track_Store::$tracks = array(
	11 => array(
		'definition'  => array( 'key' => '150h', 'status' => 'In Sensei', 'label' => 'WordPress Credits Program 150h', 'course_url' => 'https://learn.wordpress.org/course/wordpress-credits/' ),
		'state'       => 'published',
		'source'      => 'builtin',
		'log'         => array( array( 'at' => 1788000000, 'by' => 7, 'did' => 'publish' ) ),
		'equivalence' => array(),
		'published'   => array( 'key' => '150h' ),
	),
	12 => array(
		'definition'  => array( 'key' => 'design', 'status' => 'Designer Track', 'label' => 'Designer Track, left behind', 'course_url' => '', 'questions' => array( 'B' => 2 ) ),
		'state'       => 'draft',
		'source'      => 'builtin',
		'log'         => array(),
		'equivalence' => array( 'not_published' ),
		'published'   => null,
	),
	13 => array(
		'definition'  => array( 'key' => 'marketing', 'status' => 'Marketing Track', 'label' => 'Marketing Track', 'course_url' => '' ),
		'state'       => 'published',
		'source'      => 'definition',
		'log'         => array( array( 'at' => 1788100000, 'by' => 7, 'did' => 'publish' ) ),
		'equivalence' => array( 'not_builtin' ),
		'published'   => array( 'key' => 'marketing' ),
	),
);
WPCPM_Students_Sync::$counts = array( 'In Sensei' => 411, 'Marketing Track' => 0 );
$GLOBALS['opts']['wpcpm_tracks_skipped'] = array( 13 => array( 'column_reserved' ) );

$rows = WPCPM_Track_Builder::rows();

ck( 'one row per track, in post order', array_column( $rows, 'id' ), array( 11, 12, 13 ) );
ck( 'each carrying what the list prints',
    array( $rows[0]['label'], $rows[0]['status'], $rows[0]['key'], $rows[0]['source'], $rows[0]['state'], $rows[0]['students'] ),
    array( 'WordPress Credits Program 150h', 'In Sensei', '150h', 'builtin', 'published', 411 ) );
ck( 'who published it last, and when', array( $rows[0]['published_by'], $rows[0]['published_at'] ), array( 7, 1788000000 ) );
ck( 'a track the last compile left out says why', array( $rows[2]['skipped'], $rows[0]['skipped'] ), array( array( 'column_reserved' ), array() ) );
ck( 'a built-in draft that has fallen behind its PHP can be refreshed', array( $rows[1]['stale'], $rows[0]['stale'], $rows[2]['stale'] ), array( true, false, false ) );
ck( 'and the equivalence line travels with the built-in rows', array( $rows[0]['equivalence'], $rows[2]['equivalence'] ), array( array(), array( 'not_builtin' ) ) );

echo "\n=== The markup ===\n";

ob_start();
WPCPM_Track_Builder_Screen::render_list( array( 'rows' => $rows, 'flash' => array() ) );
$html = ob_get_clean();

ck( 'a row per track, and every name on the page', array( substr_count( $html, '<tr class="wpcpm-tracks__row' ), false !== strpos( $html, 'WordPress Credits Program 150h' ), false !== strpos( $html, 'Marketing Track' ) ), array( 3, true, true ) );
ck( 'the students on each track are shown', false !== strpos( $html, '411' ), true );
ck( 'a skipped track is shown as needing action, not left out quietly', false !== strpos( $html, 'wpcpm-tracks__skipped' ), true );
ck( 'the refresh is offered on the stale built-in draft only', substr_count( $html, 'name="action" value="wpcpm_track_refresh"' ), 1 );
ck( 'a built-in track its PHP runs says so rather than offering an edit that would be refused', false !== strpos( $html, 'wpcpm-tracks__readonly' ), true );

echo "\n=== Refreshing a built-in draft from the screen ===\n";

/** Run a handler and say how it ended: a redirect, or the message it died with. */
function outcome( callable $handler ) {
	try {
		$handler();
	} catch ( RedirectSignal $e ) {
		return 'redirect';
	} catch ( DieSignal $e ) {
		return 'die: ' . $e->getMessage();
	}

	return 'no outcome';
}

$tool                        = new WPCPM_Track_Builder();
$GLOBALS['can_manage']       = false;
$GLOBALS['nonce']            = WPCPM_Track_Builder::ACTION_REFRESH;
WPCPM_Track_Store::$refreshed = array();

ck( 'without the capability it dies before the nonce is read, and refreshes nothing',
    array( outcome( array( $tool, 'handle_refresh' ) ), WPCPM_Track_Store::$refreshed ),
    array( 'die: You do not have permission to manage the program.', array() ) );

$GLOBALS['can_manage'] = true;
$GLOBALS['nonce']      = 'another-action';
ck( 'with the capability but the wrong nonce it dies too',
    array( outcome( array( $tool, 'handle_refresh' ) ), WPCPM_Track_Store::$refreshed ),
    array( 'die: the nonce was refused', array() ) );

$GLOBALS['nonce'] = WPCPM_Track_Builder::ACTION_REFRESH;
$_POST['track']   = 12;
ck( 'with both, it refreshes that draft and says so',
    array( outcome( array( $tool, 'handle_refresh' ) ), WPCPM_Track_Store::$refreshed, WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['status'] ),
    array( 'redirect', array( 12 ), 'success' ) );

$_POST['track'] = 999;
ck( 'and when the store refuses, the screen says what the store said',
    array( outcome( array( $tool, 'handle_refresh' ) ), WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ] ),
    array( 'redirect', array( 'status' => 'error', 'message' => 'That track does not exist.' ) ) );

$tool->boot();
ck( 'and the handler is on admin-post', in_array( 'admin_post_' . WPCPM_Track_Builder::ACTION_REFRESH, $GLOBALS['hooks'], true ), true );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php bin/test-track-builder.php`
Expected: `PHP Fatal error: Uncaught Error: Failed opening required '.../includes/tools/class-wpcpm-track-builder-screen.php'`, because neither class file exists yet.

- [ ] **Step 3: Write the screen**

Create `includes/tools/class-wpcpm-track-builder-screen.php`:

```php
<?php
/**
 * Tools - the Track Builder screen's markup.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Draws the Track Builder's list.
 *
 * Separated from the tool for the reason the Student Duplicate Finder's screen is: the tool
 * answers presses and the screen prints, so a change to the wording never touches a handler.
 * Everything printed here arrives ready in the row (`WPCPM_Track_Builder::rows()`), so this class
 * asks nothing of the store and nothing of Airtable.
 */
final class WPCPM_Track_Builder_Screen {

	/**
	 * The list of tracks.
	 *
	 * @param array $args `rows` from `WPCPM_Track_Builder::rows()`, and the `flash` the last press
	 *                    left. Every row action posts to `admin-post.php`, so the screen needs no
	 *                    URL of its own until T2c gives a row somewhere to link to.
	 */
	public static function render_list( array $args ) {
		$rows  = isset( $args['rows'] ) && is_array( $args['rows'] ) ? $args['rows'] : array();
		$flash = isset( $args['flash'] ) && is_array( $args['flash'] ) ? $args['flash'] : array();

		self::render_notice( $flash );

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No tracks yet. The four the program runs today appear here the first time this site loads after the update.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		echo '<table class="widefat striped wpcpm-tracks"><thead><tr>';

		foreach ( array(
			__( 'Track', 'wpcredits-program-manager' ),
			__( 'Status', 'wpcredits-program-manager' ),
			__( 'Runs from', 'wpcredits-program-manager' ),
			__( 'State', 'wpcredits-program-manager' ),
			__( 'Students', 'wpcredits-program-manager' ),
			__( 'Last published', 'wpcredits-program-manager' ),
			__( 'Actions', 'wpcredits-program-manager' ),
		) as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}

		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			self::render_row( $row );
		}

		echo '</tbody></table>';
	}

	/**
	 * One track.
	 *
	 * @param array $row One row from `WPCPM_Track_Builder::rows()`.
	 */
	private static function render_row( array $row ) {
		$skipped = isset( $row['skipped'] ) ? (array) $row['skipped'] : array();
		$builtin = isset( $row['source'] ) && 'builtin' === $row['source'];
		$classes = 'wpcpm-tracks__row' . ( empty( $skipped ) ? '' : ' wpcpm-tracks__row--skipped' );

		printf( '<tr class="%s">', esc_attr( $classes ) );

		printf( '<td><strong>%s</strong>', esc_html( (string) $row['label'] ) );
		self::render_course( $row );
		echo '</td>';

		printf( '<td>%s<br /><code class="wpcpm-tracks__key">%s</code></td>', esc_html( (string) $row['status'] ), esc_html( (string) $row['key'] ) );

		echo '<td>';

		if ( $builtin ) {
			echo '<span class="wpcpm-tracks__readonly">' . esc_html__( 'Its hand-written form, so it cannot be edited here', 'wpcredits-program-manager' ) . '</span>';
		} else {
			echo esc_html__( 'Its definition', 'wpcredits-program-manager' );
		}

		echo '</td>';

		printf( '<td>%s', esc_html( self::state_label( (string) $row['state'] ) ) );
		self::render_skipped( $skipped );
		echo '</td>';

		printf( '<td>%s</td>', esc_html( number_format_i18n( (int) $row['students'] ) ) );
		printf( '<td>%s</td>', esc_html( self::published_line( $row ) ) );

		echo '<td class="wpcpm-list__actions">';
		self::render_actions( $row );
		echo '</td></tr>';
	}

	/**
	 * The buttons a row offers.
	 *
	 * @param array $row One row.
	 */
	private static function render_actions( array $row ) {
		if ( ! empty( $row['stale'] ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( WPCPM_Track_Builder::ACTION_REFRESH );
			echo '<input type="hidden" name="action" value="' . esc_attr( WPCPM_Track_Builder::ACTION_REFRESH ) . '" />';
			printf( '<input type="hidden" name="track" value="%d" />', (int) $row['id'] );
			printf( '<button type="submit" class="button-link">%s</button>', esc_html__( 'Refresh from the plugin', 'wpcredits-program-manager' ) );
			echo '</form>';
		}
	}

	/**
	 * The track's Learn course, when it has one.
	 *
	 * @param array $row One row.
	 */
	private static function render_course( array $row ) {
		$course = isset( $row['course'] ) ? (string) $row['course'] : '';

		if ( '' === $course ) {
			return;
		}

		echo '<br /><a class="wpcpm-tracks__course" href="' . esc_url( $course ) . '">' . esc_html__( 'Learn course', 'wpcredits-program-manager' ) . '</a>';
	}

	/**
	 * What the last compile left out, and why.
	 *
	 * @param array $skipped The rules it failed.
	 */
	private static function render_skipped( array $skipped ) {
		if ( empty( $skipped ) ) {
			return;
		}

		$sentence = sprintf(
			/* translators: %s: the rules a track failed, separated by commas. */
			__( 'Left out of the live site by the last compile: %s. Students on it see the 150-hour form until it passes.', 'wpcredits-program-manager' ),
			implode( ', ', array_map( 'strval', $skipped ) )
		);

		echo '<br /><span class="wpcpm-tracks__skipped">' . esc_html( $sentence ) . '</span>';
	}

	/**
	 * Who published the track last, and when.
	 *
	 * @param array $row One row.
	 * @return string
	 */
	private static function published_line( array $row ) {
		$at = isset( $row['published_at'] ) ? (int) $row['published_at'] : 0;

		if ( ! $at ) {
			return __( 'Never', 'wpcredits-program-manager' );
		}

		$user = get_userdata( isset( $row['published_by'] ) ? (int) $row['published_by'] : 0 );

		return sprintf(
			/* translators: 1: a date and time, 2: who published the track. */
			__( '%1$s by %2$s', 'wpcredits-program-manager' ),
			wp_date( 'Y-m-d H:i', $at ),
			$user ? $user->display_name : __( 'somebody since removed', 'wpcredits-program-manager' )
		);
	}

	/**
	 * A state in the words the list uses.
	 *
	 * @param string $state What `WPCPM_Track_Store::state()` answered.
	 * @return string
	 */
	private static function state_label( $state ) {
		$states = array(
			'draft'     => __( 'Draft', 'wpcredits-program-manager' ),
			'published' => __( 'Published', 'wpcredits-program-manager' ),
			'changed'   => __( 'Unpublished changes', 'wpcredits-program-manager' ),
			'trash'     => __( 'In the trash', 'wpcredits-program-manager' ),
		);

		return isset( $states[ $state ] ) ? $states[ $state ] : $state;
	}

	/**
	 * The notice the last press left.
	 *
	 * @param array $flash `status` and `message`.
	 */
	private static function render_notice( array $flash ) {
		if ( empty( $flash['message'] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( isset( $flash['status'] ) && 'error' === $flash['status'] ? 'error' : 'success' ),
			esc_html( (string) $flash['message'] )
		);
	}
}
```

- [ ] **Step 4: Write the tool**

Create `includes/tools/class-wpcpm-track-builder.php`:

```php
<?php
/**
 * Tools - the Track Builder.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Track Builder screen: every program track, what state it is in, and what to do about it.
 *
 * A tool rather than a module, so the Modules menu lists it beside the others, and its handlers do
 * their own capability and nonce check in that order rather than borrowing `WPCPM_Sync_Module`'s,
 * which serves modules (the design's decision 3.9). The rows it draws come from the store and the
 * students sync; nothing here reads Airtable, which is what lets this release ship without the
 * schema token that T2c needs.
 */
class WPCPM_Track_Builder extends WPCPM_Tool {

	/** Put a built-in draft back to the seed the plugin ships. */
	const ACTION_REFRESH = 'wpcpm_track_refresh';

	/** Flash channel for this screen's outcomes. */
	const FLASH = 'track-builder';

	/**
	 * Tool identifier.
	 *
	 * @return string
	 */
	public function id() {
		return 'track-builder';
	}

	/**
	 * Human-readable tool name.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Track Builder', 'wpcredits-program-manager' );
	}

	/**
	 * One-line description for the Modules screen.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Every program track: what state it is in, how many students are on it, and what the last compile left out.', 'wpcredits-program-manager' );
	}

	/**
	 * The list reads the site's own tracks, so it works with no Airtable connection at all.
	 *
	 * @return bool
	 */
	public function is_ready() {
		return true;
	}

	/**
	 * How many tracks there are, and how many the live site runs.
	 *
	 * @return string
	 */
	public function status_line() {
		$rows = self::rows();
		$live = 0;

		foreach ( $rows as $row ) {
			if ( 'published' === $row['state'] || 'changed' === $row['state'] ) {
				++$live;
			}
		}

		return sprintf(
			/* translators: 1: how many tracks exist, 2: how many of them are published. */
			__( '%1$s tracks, %2$s of them published.', 'wpcredits-program-manager' ),
			number_format_i18n( count( $rows ) ),
			number_format_i18n( $live )
		);
	}

	/**
	 * Hooks.
	 */
	public function boot() {
		add_action( 'admin_post_' . self::ACTION_REFRESH, array( $this, 'handle_refresh' ) );
	}

	/**
	 * One row per track, in the order the tracks were made.
	 *
	 * Everything the list prints, read once here so the screen asks nothing of the store while it
	 * draws: the state, who published it last, how many students hold its status, what the last
	 * compile left out, how its definition compares with its PHP, and whether a built-in draft has
	 * fallen behind the seed the plugin now ships (the design's decision 12).
	 *
	 * @return array[]
	 */
	public static function rows() {
		$skipped = get_option( WPCPM_Track_Store::OPT_SKIPPED, array() );
		$skipped = is_array( $skipped ) ? $skipped : array();
		$seeds   = WPCPM_Track_Store::seeds();
		$rows    = array();

		foreach ( WPCPM_Track_Store::all_ids() as $post_id ) {
			$post_id    = (int) $post_id;
			$definition = WPCPM_Track_Store::get( $post_id );

			if ( ! is_array( $definition ) ) {
				continue;
			}

			$key       = isset( $definition['key'] ) ? (string) $definition['key'] : '';
			$status    = isset( $definition['status'] ) ? (string) $definition['status'] : '';
			$source    = WPCPM_Track_Store::source( $post_id );
			$published = WPCPM_Track_Store::published( $post_id );
			$last      = self::last_publish( WPCPM_Track_Store::log_entries( $post_id ) );

			$rows[] = array(
				'id'           => $post_id,
				'label'        => isset( $definition['label'] ) ? (string) $definition['label'] : '',
				'status'       => $status,
				'key'          => $key,
				'course'       => isset( $definition['course_url'] ) ? (string) $definition['course_url'] : '',
				'source'       => $source,
				'state'        => WPCPM_Track_Store::state( $post_id ),
				'students'     => WPCPM_Students_Sync::count_on_status( $status ),
				'published_by' => (int) $last['by'],
				'published_at' => (int) $last['at'],
				'skipped'      => isset( $skipped[ $post_id ] ) ? (array) $skipped[ $post_id ] : array(),
				'equivalence'  => WPCPM_Track_Store::equivalence( $post_id ),
				'stale'        => 'builtin' === $source && ! is_array( $published ) && isset( $seeds[ $key ] ) && $seeds[ $key ] !== $definition,
			);
		}

		return $rows;
	}

	/**
	 * Render the screen.
	 */
	public function render_admin_page() {
		$this->require_manager();

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html( $this->label() ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html( $this->description() ) . '</p>';

		WPCPM_Track_Builder_Screen::render_list(
			array(
				'rows'  => self::rows(),
				'flash' => (array) WPCPM_Flash::take( self::FLASH ),
			)
		);

		echo '</div>';
	}

	/**
	 * Put one built-in draft back to the seed the plugin ships.
	 */
	public function handle_refresh() {
		$this->verify( self::ACTION_REFRESH );

		$result = WPCPM_Track_Store::refresh_builtin( WPCPM_Request::posted_id( 'track' ) );

		if ( is_wp_error( $result ) ) {
			$this->redirect_back(
				array(
					'status'  => 'error',
					'message' => $result->get_error_message(),
				)
			);
		}

		$this->redirect_back(
			array(
				'status'  => 'success',
				'message' => __( 'The track was refreshed from the version this plugin ships.', 'wpcredits-program-manager' ),
			)
		);
	}

	/**
	 * When a track was last published, and by whom.
	 *
	 * @param array[] $entries The track's log, oldest first.
	 * @return array `at` and `by`, both 0 when it has never been published.
	 */
	private static function last_publish( array $entries ) {
		$last = array(
			'at' => 0,
			'by' => 0,
		);

		foreach ( $entries as $entry ) {
			if ( isset( $entry['did'] ) && 'publish' === $entry['did'] ) {
				$last = array(
					'at' => isset( $entry['at'] ) ? (int) $entry['at'] : 0,
					'by' => isset( $entry['by'] ) ? (int) $entry['by'] : 0,
				);
			}
		}

		return $last;
	}

	/**
	 * The capability, then the nonce, before a handler does anything.
	 *
	 * @param string $action The action being verified.
	 */
	private function verify( $action ) {
		$this->require_manager();

		check_admin_referer( $action );
	}

	/**
	 * Refuse anybody who does not manage the program.
	 */
	private function require_manager() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}
	}

	/**
	 * Back to the screen, with what happened flashed for the person who pressed.
	 *
	 * @param array $outcome `status` and `message`.
	 */
	private function redirect_back( array $outcome ) {
		WPCPM_Flash::set( self::FLASH, $outcome );
		wp_safe_redirect( class_exists( 'WPCPM_Return' ) ? WPCPM_Return::url( $this->admin_url() ) : $this->admin_url() );
		exit;
	}
}
```

- [ ] **Step 5: Give the store the two answers the list needs, and load the files**

```diff
diff --git a/includes/class-wpcpm-tools.php b/includes/class-wpcpm-tools.php
index 006ed38..03f5c31 100644
--- a/includes/class-wpcpm-tools.php
+++ b/includes/class-wpcpm-tools.php
@@ -28,7 +28,7 @@ class WPCPM_Tools {
 	 */
 	public static function all() {
 		if ( null === self::$tools ) {
-			$tools = array( new WPCPM_Header_Notices(), new WPCPM_Handbook(), new WPCPM_Mentor_Checker(), new WPCPM_Duplicate_Finder() );
+			$tools = array( new WPCPM_Header_Notices(), new WPCPM_Handbook(), new WPCPM_Mentor_Checker(), new WPCPM_Duplicate_Finder(), new WPCPM_Track_Builder() );
 
 			/**
 			 * Filter the registered tools.
diff --git a/includes/tracks/class-wpcpm-track-store.php b/includes/tracks/class-wpcpm-track-store.php
index 65f063b..a4c901f 100644
--- a/includes/tracks/class-wpcpm-track-store.php
+++ b/includes/tracks/class-wpcpm-track-store.php
@@ -515,6 +515,16 @@ final class WPCPM_Track_Store {
 		return self::get( $post_id ) === self::published( $post_id ) ? 'published' : 'changed';
 	}
 
+	/**
+	 * Which side of the switch a track is on: `builtin` while its PHP runs it, `definition` after.
+	 *
+	 * @param int $post_id The track.
+	 * @return string
+	 */
+	public static function source( $post_id ) {
+		return 'builtin' === get_post_meta( (int) $post_id, self::META_SOURCE, true ) ? 'builtin' : 'definition';
+	}
+
 	/**
 	 * Add a line to a track's log.
 	 *
@@ -961,7 +971,7 @@ final class WPCPM_Track_Store {
 	 *
 	 * @return int[]
 	 */
-	private static function all_ids() {
+	public static function all_ids() {
 		return get_posts(
 			array(
 				'post_type'   => self::POST_TYPE,
diff --git a/uninstall.php b/uninstall.php
index a13dd5d..285251c 100644
--- a/uninstall.php
+++ b/uninstall.php
@@ -146,6 +146,8 @@ require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicate
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicate-delete.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicate-finder-screen.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-duplicate-finder.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-track-builder-screen.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/tools/class-wpcpm-track-builder.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-tools.php';
 
 WPCPM_Modules::uninstall();
diff --git a/wpcredits-program-manager.php b/wpcredits-program-manager.php
index 62d3564..5f5c166 100644
--- a/wpcredits-program-manager.php
+++ b/wpcredits-program-manager.php
@@ -140,6 +140,8 @@ require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-vault.php'
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-delete.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-finder-screen.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-duplicate-finder.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-track-builder-screen.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-track-builder.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-tools.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php';
```

- [ ] **Step 6: Run it and watch it pass**

Run: `php bin/test-track-builder.php`
Expected: `ALL PASS (19 checks)`.

- [ ] **Step 7: Run everything**

Expected: silent, clean, `85 warnings, no errors.` The battery now runs 94 suites. `php bin/test-roles.php` is the one that fails if either class file is missing from `uninstall.php`.

- [ ] **Step 8: Commit**

```bash
git add includes/tools/class-wpcpm-track-builder.php includes/tools/class-wpcpm-track-builder-screen.php bin/test-track-builder.php includes/tracks/class-wpcpm-track-store.php includes/class-wpcpm-tools.php wpcredits-program-manager.php uninstall.php
git commit -m "Track Builder T2b: the Track Builder tool, its rows and its list"
```

---

### Task 6: A track's properties

**Files:**
- Modify: `includes/tools/class-wpcpm-track-builder.php`, `includes/tools/class-wpcpm-track-builder-screen.php`
- Test: `bin/test-track-builder.php`

**Interfaces:**
- Consumes: `WPCPM_Track_Store::check()` from Task 2, `WPCPM_Track_Store::save()`, `WPCPM_Track_Palette::is_hue()`, `WPCPM_Request::id()` and `posted_text()`.
- Produces: `WPCPM_Track_Builder::form( int $post_id ): array` with `id`, `label`, `status`, `key`, `course_url`, `learn_course_id`, `hours_target`, `hue` and `read_only`; `ACTION_SAVE`; `WPCPM_Track_Builder_Screen::render_form( array $args )`; and `redirect_back()` taking query arguments, which Task 7 reuses.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-builder.php b/bin/test-track-builder.php
index b57f901..d25a005 100644
--- a/bin/test-track-builder.php
+++ b/bin/test-track-builder.php
@@ -24,6 +24,7 @@ function __( $s, $d = null ) { return $s; }
 function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
 function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
 function esc_url( $s ) { return (string) $s; }
+function esc_url_raw( $s ) { return (string) $s; }
 function esc_html__( $s, $d = null ) { return esc_html( $s ); }
 function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
 function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
@@ -55,6 +56,14 @@ class WP_Error {
 
 class WPCPM_Request {
 	public static function posted_id( $key ) { return (int) ( $_POST[ $key ] ?? 0 ); }
+	public static function id( $key ) { return (int) ( $_GET[ $key ] ?? 0 ); }
+	public static function posted_text( $key ) { return isset( $_POST[ $key ] ) ? trim( (string) $_POST[ $key ] ) : ''; }
+}
+
+class WPCPM_Track_Palette {
+	const HUES = array( 'pink', 'blue', 'green' );
+
+	public static function is_hue( $hue ) { return in_array( $hue, self::HUES, true ); }
 }
 
 $GLOBALS['can_manage'] = true;
@@ -99,6 +108,23 @@ class WPCPM_Track_Store {
 	}
 
 	public static $refreshed = array();
+	public static $saved     = array();
+	public static $errors    = array();
+
+	public static function check( $post_id, array $definition ) {
+		return self::$errors;
+	}
+
+	public static function save( $post_id, array $definition ) {
+		if ( 'builtin' === self::source( $post_id ) ) {
+			return new WP_Error( 'wpcpm_track_builtin', 'A built-in track runs from its hand-written form until it switches to its definition.' );
+		}
+
+		self::$saved[ (int) $post_id ] = $definition;
+		self::$tracks[ $post_id ]['definition'] = $definition;
+
+		return (int) $post_id;
+	}
 
 	public static function refresh_builtin( $post_id ) {
 		self::$refreshed[] = (int) $post_id;
@@ -219,7 +245,7 @@ ck( 'and the equivalence line travels with the built-in rows', array( $rows[0]['
 echo "\n=== The markup ===\n";
 
 ob_start();
-WPCPM_Track_Builder_Screen::render_list( array( 'rows' => $rows, 'flash' => array() ) );
+WPCPM_Track_Builder_Screen::render_list( array( 'rows' => $rows, 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
 $html = ob_get_clean();
 
 ck( 'a row per track, and every name on the page', array( substr_count( $html, '<tr class="wpcpm-tracks__row' ), false !== strpos( $html, 'WordPress Credits Program 150h' ), false !== strpos( $html, 'Marketing Track' ) ), array( 3, true, true ) );
@@ -227,6 +253,7 @@ ck( 'the students on each track are shown', false !== strpos( $html, '411' ), tr
 ck( 'a skipped track is shown as needing action, not left out quietly', false !== strpos( $html, 'wpcpm-tracks__skipped' ), true );
 ck( 'the refresh is offered on the stale built-in draft only', substr_count( $html, 'name="action" value="wpcpm_track_refresh"' ), 1 );
 ck( 'a built-in track its PHP runs says so rather than offering an edit that would be refused', false !== strpos( $html, 'wpcpm-tracks__readonly' ), true );
+ck( 'Edit is offered on the track a person may edit, and on that one only', substr_count( $html, '>Edit</a>' ), 1 );
 
 echo "\n=== Refreshing a built-in draft from the screen ===\n";
 
@@ -272,5 +299,62 @@ ck( 'and when the store refuses, the screen says what the store said',
 $tool->boot();
 ck( 'and the handler is on admin-post', in_array( 'admin_post_' . WPCPM_Track_Builder::ACTION_REFRESH, $GLOBALS['hooks'], true ), true );
 
+echo "\n=== The properties form ===\n";
+
+// The questions are T3's; this edits what a track is, not what it asks. A built-in track its PHP
+// still runs is read-only here, because its equivalence with that PHP is what the switch rests on
+// (spec section 6), and the store refuses the save in any case.
+ck( 'the form offers the track properties, and nothing about its questions',
+    array_keys( WPCPM_Track_Builder::form( 13 ) ),
+    array( 'id', 'label', 'status', 'key', 'course_url', 'learn_course_id', 'hours_target', 'hue', 'read_only' ) );
+ck( 'filled from the definition', array( WPCPM_Track_Builder::form( 13 )['label'], WPCPM_Track_Builder::form( 13 )['status'], WPCPM_Track_Builder::form( 13 )['read_only'] ), array( 'Marketing Track', 'Marketing Track', false ) );
+ck( 'and a built-in track its PHP runs is read-only', WPCPM_Track_Builder::form( 11 )['read_only'], true );
+
+ob_start();
+WPCPM_Track_Builder_Screen::render_form( array( 'form' => WPCPM_Track_Builder::form( 13 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
+$form = ob_get_clean();
+ck( 'the form posts to the save action with a field per property',
+    array( substr_count( $form, 'name="action" value="wpcpm_track_save"' ), substr_count( $form, 'name="wpcpm_label"' ), substr_count( $form, 'name="wpcpm_status"' ), substr_count( $form, 'name="wpcpm_key"' ), substr_count( $form, 'name="wpcpm_hours_target"' ) ),
+    array( 1, 1, 1, 1, 1 ) );
+
+ob_start();
+WPCPM_Track_Builder_Screen::render_form( array( 'form' => WPCPM_Track_Builder::form( 11 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
+$readonly = ob_get_clean();
+ck( 'a built-in track shows why it cannot be edited instead of a form that would be refused',
+    array( substr_count( $readonly, 'name="action" value="wpcpm_track_save"' ), false !== strpos( $readonly, 'wpcpm-tracks__readonly' ) ),
+    array( 0, true ) );
+
+$GLOBALS['nonce']           = WPCPM_Track_Builder::ACTION_SAVE;
+WPCPM_Track_Store::$saved   = array();
+WPCPM_Track_Store::$errors  = array();
+$_POST                      = array(
+	'track'             => 13,
+	'wpcpm_label'       => 'Marketing Track, renamed',
+	'wpcpm_status'      => 'Marketing Track',
+	'wpcpm_key'         => 'marketing',
+	'wpcpm_course_url'  => 'https://learn.wordpress.org/course/marketing/',
+	'wpcpm_hours_target' => '120',
+	'wpcpm_hue'         => 'blue',
+);
+
+ck( 'a save writes the properties and leaves the questions alone',
+    array( outcome( array( $tool, 'handle_save' ) ), WPCPM_Track_Store::$saved[13]['label'], WPCPM_Track_Store::$saved[13]['hours_target'], WPCPM_Track_Store::$saved[13]['key'] ),
+    array( 'redirect', 'Marketing Track, renamed', 120, 'marketing' ) );
+
+WPCPM_Track_Store::$saved  = array();
+WPCPM_Track_Store::$errors = array( array( 'code' => 'status_taken', 'message' => 'Another track already has this status.' ) );
+ck( 'a definition the rules refuse is not stored, and the screen says what publishing would have said',
+    array( outcome( array( $tool, 'handle_save' ) ), WPCPM_Track_Store::$saved, WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['message'] ),
+    array( 'redirect', array(), 'Another track already has this status.' ) );
+ck( 'and what the person typed comes back with the refusal', WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['values']['label'], 'Marketing Track, renamed' );
+
+WPCPM_Track_Store::$errors = array();
+$_POST['track']            = 11;
+ck( 'the store has the last word on a built-in track, whatever the screen offered',
+    array( outcome( array( $tool, 'handle_save' ) ), WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['status'], WPCPM_Track_Store::$saved ),
+    array( 'redirect', 'error', array() ) );
+
+$_POST = array();
+
 printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
 exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-builder.php`
Expected: `PHP Fatal error: Uncaught Error: Call to undefined method WPCPM_Track_Builder::form()`.

- [ ] **Step 3: Write the tool's half**

```diff
diff --git a/includes/tools/class-wpcpm-track-builder.php b/includes/tools/class-wpcpm-track-builder.php
index 6374d4e..383a875 100644
--- a/includes/tools/class-wpcpm-track-builder.php
+++ b/includes/tools/class-wpcpm-track-builder.php
@@ -20,6 +20,9 @@ if ( ! defined( 'ABSPATH' ) ) {
  */
 class WPCPM_Track_Builder extends WPCPM_Tool {
 
+	/** Save a track's properties. */
+	const ACTION_SAVE = 'wpcpm_track_save';
+
 	/** Put a built-in draft back to the seed the plugin ships. */
 	const ACTION_REFRESH = 'wpcpm_track_refresh';
 
@@ -89,6 +92,7 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 	 * Hooks.
 	 */
 	public function boot() {
+		add_action( 'admin_post_' . self::ACTION_SAVE, array( $this, 'handle_save' ) );
 		add_action( 'admin_post_' . self::ACTION_REFRESH, array( $this, 'handle_refresh' ) );
 	}
 
@@ -143,25 +147,173 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 	}
 
 	/**
-	 * Render the screen.
+	 * A track's properties, as the form edits them.
+	 *
+	 * The questions are T3's, so this is what a track *is* rather than what it asks. A built-in
+	 * track its PHP still runs is read-only: its equivalence with that PHP is what the switch
+	 * rests on (spec section 6), and the store refuses the save in any case.
+	 *
+	 * @param int $post_id The track.
+	 * @return array
+	 */
+	public static function form( $post_id ) {
+		$post_id    = (int) $post_id;
+		$definition = WPCPM_Track_Store::get( $post_id );
+		$definition = is_array( $definition ) ? $definition : array();
+
+		return array(
+			'id'              => $post_id,
+			'label'           => isset( $definition['label'] ) ? (string) $definition['label'] : '',
+			'status'          => isset( $definition['status'] ) ? (string) $definition['status'] : '',
+			'key'             => isset( $definition['key'] ) ? (string) $definition['key'] : '',
+			'course_url'      => isset( $definition['course_url'] ) ? (string) $definition['course_url'] : '',
+			'learn_course_id' => isset( $definition['learn_course_id'] ) ? (int) $definition['learn_course_id'] : 0,
+			'hours_target'    => isset( $definition['hours_target'] ) ? (int) $definition['hours_target'] : 0,
+			'hue'             => isset( $definition['hue'] ) ? (string) $definition['hue'] : '',
+			'read_only'       => 'builtin' === WPCPM_Track_Store::source( $post_id ),
+		);
+	}
+
+	/**
+	 * Render the screen: one track's properties, or the list.
 	 */
 	public function render_admin_page() {
 		$this->require_manager();
 
+		$track = WPCPM_Request::id( 'wpcpm_track' );
+		$flash = (array) WPCPM_Flash::take( self::FLASH );
+
 		echo '<div class="wrap wpcpm-wrap">';
 		echo '<h1>' . esc_html( $this->label() ) . '</h1>';
+
+		if ( $track > 0 && is_array( WPCPM_Track_Store::get( $track ) ) ) {
+			WPCPM_Track_Builder_Screen::render_form(
+				array(
+					'form'  => self::form( $track ),
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
 		echo '<p class="wpcpm-lede">' . esc_html( $this->description() ) . '</p>';
 
 		WPCPM_Track_Builder_Screen::render_list(
 			array(
 				'rows'  => self::rows(),
-				'flash' => (array) WPCPM_Flash::take( self::FLASH ),
+				'url'   => $this->admin_url(),
+				'flash' => $flash,
 			)
 		);
 
 		echo '</div>';
 	}
 
+	/**
+	 * Save a track's properties.
+	 */
+	public function handle_save() {
+		$this->verify( self::ACTION_SAVE );
+
+		$post_id = WPCPM_Request::posted_id( 'track' );
+		$stored  = WPCPM_Track_Store::get( $post_id );
+
+		if ( ! is_array( $stored ) ) {
+			$this->redirect_back(
+				array(
+					'status'  => 'error',
+					'message' => __( 'That track does not exist.', 'wpcredits-program-manager' ),
+				)
+			);
+		}
+
+		$definition = self::posted_definition( $stored );
+		$errors     = WPCPM_Track_Store::check( $post_id, $definition );
+
+		if ( array() !== $errors ) {
+			$this->refuse( $post_id, (string) $errors[0]['message'], $definition );
+		}
+
+		$saved = WPCPM_Track_Store::save( $post_id, $definition );
+
+		if ( is_wp_error( $saved ) ) {
+			$this->refuse( $post_id, $saved->get_error_message(), $definition );
+		}
+
+		$this->redirect_back(
+			array(
+				'status'  => 'success',
+				'message' => __( 'The track was saved. Nothing reaches students until it is published.', 'wpcredits-program-manager' ),
+			),
+			$post_id
+		);
+	}
+
+	/**
+	 * The posted properties, on top of the definition the track holds.
+	 *
+	 * The questions travel untouched, because this form does not show them: an editor that rebuilt
+	 * the definition from its fields alone would drop every question the moment somebody renamed a
+	 * track.
+	 *
+	 * @param array $stored The definition as it is stored.
+	 * @return array
+	 */
+	private static function posted_definition( array $stored ) {
+		$definition           = $stored;
+		$definition['label']  = WPCPM_Request::posted_text( 'wpcpm_label' );
+		$definition['status'] = WPCPM_Request::posted_text( 'wpcpm_status' );
+		$definition['key']    = WPCPM_Request::posted_text( 'wpcpm_key' );
+		$course               = WPCPM_Request::posted_text( 'wpcpm_course_url' );
+		$learn                = (int) WPCPM_Request::posted_text( 'wpcpm_learn_course_id' );
+		$hours                = WPCPM_Request::posted_text( 'wpcpm_hours_target' );
+		$hue                  = WPCPM_Request::posted_text( 'wpcpm_hue' );
+
+		$definition['course_url'] = '' === $course ? '' : esc_url_raw( $course );
+
+		if ( $learn > 0 ) {
+			$definition['learn_course_id'] = $learn;
+		} else {
+			unset( $definition['learn_course_id'] );
+		}
+
+		// A track may have no hours target at all, which is the Developer Track's answer, so an
+		// empty field means no target rather than zero.
+		if ( '' === $hours ) {
+			unset( $definition['hours_target'] );
+		} else {
+			$definition['hours_target'] = (int) $hours;
+		}
+
+		if ( WPCPM_Track_Palette::is_hue( $hue ) ) {
+			$definition['hue'] = $hue;
+		}
+
+		return $definition;
+	}
+
+	/**
+	 * Back to the form with the refusal and what the person typed, so nothing has to be retyped.
+	 *
+	 * @param int    $post_id    The track.
+	 * @param string $message    Why it was refused.
+	 * @param array  $definition What was posted.
+	 */
+	private function refuse( $post_id, $message, array $definition ) {
+		$this->redirect_back(
+			array(
+				'status'  => 'error',
+				'message' => $message,
+				'values'  => $definition,
+			),
+			$post_id
+		);
+	}
+
 	/**
 	 * Put one built-in draft back to the seed the plugin ships.
 	 */
@@ -234,11 +386,18 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 	/**
 	 * Back to the screen, with what happened flashed for the person who pressed.
 	 *
-	 * @param array $outcome `status` and `message`.
+	 * @param array $outcome `status`, `message`, and `values` when a form has to come back.
+	 * @param int   $track   A track to return to its form; 0 for the list.
 	 */
-	private function redirect_back( array $outcome ) {
+	private function redirect_back( array $outcome, $track = 0 ) {
+		$url = $this->admin_url();
+
+		if ( (int) $track > 0 ) {
+			$url = add_query_arg( 'wpcpm_track', (int) $track, $url );
+		}
+
 		WPCPM_Flash::set( self::FLASH, $outcome );
-		wp_safe_redirect( class_exists( 'WPCPM_Return' ) ? WPCPM_Return::url( $this->admin_url() ) : $this->admin_url() );
+		wp_safe_redirect( class_exists( 'WPCPM_Return' ) ? WPCPM_Return::url( $url ) : $url );
 		exit;
 	}
 }
```

- [ ] **Step 4: Write the screen's half**

```diff
diff --git a/includes/tools/class-wpcpm-track-builder-screen.php b/includes/tools/class-wpcpm-track-builder-screen.php
index e78db05..cb794e2 100644
--- a/includes/tools/class-wpcpm-track-builder-screen.php
+++ b/includes/tools/class-wpcpm-track-builder-screen.php
@@ -22,12 +22,12 @@ final class WPCPM_Track_Builder_Screen {
 	/**
 	 * The list of tracks.
 	 *
-	 * @param array $args `rows` from `WPCPM_Track_Builder::rows()`, and the `flash` the last press
-	 *                    left. Every row action posts to `admin-post.php`, so the screen needs no
-	 *                    URL of its own until T2c gives a row somewhere to link to.
+	 * @param array $args `rows` from `WPCPM_Track_Builder::rows()`, the screen's `url` for the Edit
+	 *                    links, and the `flash` the last press left.
 	 */
 	public static function render_list( array $args ) {
 		$rows  = isset( $args['rows'] ) && is_array( $args['rows'] ) ? $args['rows'] : array();
+		$url   = isset( $args['url'] ) ? (string) $args['url'] : '';
 		$flash = isset( $args['flash'] ) && is_array( $args['flash'] ) ? $args['flash'] : array();
 
 		self::render_notice( $flash );
@@ -55,7 +55,7 @@ final class WPCPM_Track_Builder_Screen {
 		echo '</tr></thead><tbody>';
 
 		foreach ( $rows as $row ) {
-			self::render_row( $row );
+			self::render_row( $row, $url );
 		}
 
 		echo '</tbody></table>';
@@ -64,9 +64,10 @@ final class WPCPM_Track_Builder_Screen {
 	/**
 	 * One track.
 	 *
-	 * @param array $row One row from `WPCPM_Track_Builder::rows()`.
+	 * @param array  $row One row from `WPCPM_Track_Builder::rows()`.
+	 * @param string $url The screen's URL, for the Edit link.
 	 */
-	private static function render_row( array $row ) {
+	private static function render_row( array $row, $url ) {
 		$skipped = isset( $row['skipped'] ) ? (array) $row['skipped'] : array();
 		$builtin = isset( $row['source'] ) && 'builtin' === $row['source'];
 		$classes = 'wpcpm-tracks__row' . ( empty( $skipped ) ? '' : ' wpcpm-tracks__row--skipped' );
@@ -97,16 +98,25 @@ final class WPCPM_Track_Builder_Screen {
 		printf( '<td>%s</td>', esc_html( self::published_line( $row ) ) );
 
 		echo '<td class="wpcpm-list__actions">';
-		self::render_actions( $row );
+		self::render_actions( $row, $url );
 		echo '</td></tr>';
 	}
 
 	/**
 	 * The buttons a row offers.
 	 *
-	 * @param array $row One row.
+	 * @param array  $row One row.
+	 * @param string $url The screen's URL.
 	 */
-	private static function render_actions( array $row ) {
+	private static function render_actions( array $row, $url ) {
+		if ( 'builtin' !== $row['source'] && '' !== $url ) {
+			printf(
+				'<a href="%1$s">%2$s</a> ',
+				esc_url( add_query_arg( 'wpcpm_track', (int) $row['id'], $url ) ),
+				esc_html__( 'Edit', 'wpcredits-program-manager' )
+			);
+		}
+
 		if ( ! empty( $row['stale'] ) ) {
 			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
 			wp_nonce_field( WPCPM_Track_Builder::ACTION_REFRESH );
@@ -117,6 +127,61 @@ final class WPCPM_Track_Builder_Screen {
 		}
 	}
 
+	/**
+	 * One track's properties, to read or to edit.
+	 *
+	 * @param array $args `form` from `WPCPM_Track_Builder::form()`, the screen's `url`, and the
+	 *                    `flash` the last press left, whose `values` win over the stored ones so a
+	 *                    refusal never makes somebody type their change again.
+	 */
+	public static function render_form( array $args ) {
+		$form  = isset( $args['form'] ) && is_array( $args['form'] ) ? $args['form'] : array();
+		$url   = isset( $args['url'] ) ? (string) $args['url'] : '';
+		$flash = isset( $args['flash'] ) && is_array( $args['flash'] ) ? $args['flash'] : array();
+		$typed = isset( $flash['values'] ) && is_array( $flash['values'] ) ? $flash['values'] : array();
+
+		self::render_notice( $flash );
+
+		printf( '<p><a href="%1$s">%2$s</a></p>', esc_url( $url ), esc_html__( 'Back to every track', 'wpcredits-program-manager' ) );
+
+		if ( ! empty( $form['read_only'] ) ) {
+			echo '<p class="wpcpm-tracks__readonly">' . esc_html__( 'This track runs from its hand-written form, so it cannot be edited here. Duplicate it to start a track of your own, or switch it to its definition first.', 'wpcredits-program-manager' ) . '</p>';
+
+			return;
+		}
+
+		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
+		wp_nonce_field( WPCPM_Track_Builder::ACTION_SAVE );
+		echo '<input type="hidden" name="action" value="' . esc_attr( WPCPM_Track_Builder::ACTION_SAVE ) . '" />';
+		printf( '<input type="hidden" name="track" value="%d" />', (int) $form['id'] );
+
+		echo '<table class="form-table" role="presentation"><tbody>';
+
+		foreach ( array(
+			'label'           => __( 'Name', 'wpcredits-program-manager' ),
+			'status'          => __( 'Airtable status', 'wpcredits-program-manager' ),
+			'key'             => __( 'Key', 'wpcredits-program-manager' ),
+			'course_url'      => __( 'Learn course', 'wpcredits-program-manager' ),
+			'learn_course_id' => __( 'Learn course ID', 'wpcredits-program-manager' ),
+			'hours_target'    => __( 'Hours target', 'wpcredits-program-manager' ),
+			'hue'             => __( 'Key chip color', 'wpcredits-program-manager' ),
+		) as $field => $heading ) {
+			$value = array_key_exists( $field, $typed ) ? $typed[ $field ] : ( isset( $form[ $field ] ) ? $form[ $field ] : '' );
+
+			printf(
+				'<tr><th scope="row"><label for="wpcpm_%1$s">%2$s</label></th><td><input type="text" class="regular-text" id="wpcpm_%1$s" name="wpcpm_%1$s" value="%3$s" /></td></tr>',
+				esc_attr( $field ),
+				esc_html( $heading ),
+				esc_attr( (string) $value )
+			);
+		}
+
+		echo '</tbody></table>';
+
+		printf( '<p class="submit"><button type="submit" class="button button-primary">%s</button></p>', esc_html__( 'Save the track', 'wpcredits-program-manager' ) );
+		echo '</form>';
+	}
+
 	/**
 	 * The track's Learn course, when it has one.
 	 *
```

- [ ] **Step 5: Run them and watch them pass**

Run: `php bin/test-track-builder.php`
Expected: `ALL PASS (29 checks)`.

- [ ] **Step 6: Run everything**

Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 7: Commit**

```bash
git add includes/tools/class-wpcpm-track-builder.php includes/tools/class-wpcpm-track-builder-screen.php bin/test-track-builder.php
git commit -m "Track Builder T2b: a track's properties, edited on the screen and checked as Publish would"
```

---

### Task 7: A new track starts as a copy

**Files:**
- Modify: `includes/tracks/class-wpcpm-track-store.php`, `includes/tools/class-wpcpm-track-builder.php`, `includes/tools/class-wpcpm-track-builder-screen.php`
- Test: `bin/test-track-store.php`, `bin/test-track-builder.php`

**Interfaces:**
- Produces: `WPCPM_Track_Store::META_DUPLICATED_FROM`, `WPCPM_Track_Store::duplicate( int $from_id, array $definition ): int|WP_Error`; `WPCPM_Track_Builder::duplicate_form( int $post_id ): array` with `id`, `from`, `label`, `status` and `key`; `ACTION_DUPLICATE`; `WPCPM_Track_Builder_Screen::render_duplicate( array $args )`.

- [ ] **Step 1: Write the failing checks, in both suites**

```diff
diff --git a/bin/test-track-builder.php b/bin/test-track-builder.php
index d25a005..874db28 100644
--- a/bin/test-track-builder.php
+++ b/bin/test-track-builder.php
@@ -107,8 +107,17 @@ class WPCPM_Track_Store {
 		return self::$tracks[ $post_id ]['published'] ?? null;
 	}
 
-	public static $refreshed = array();
-	public static $saved     = array();
+	public static $refreshed  = array();
+	public static $duplicated = array();
+	public static $saved      = array();
+
+	public static function duplicate( $from_id, array $definition ) {
+		self::$duplicated[] = array( (int) $from_id, $definition );
+		$new                = 99;
+		self::$tracks[ $new ] = array( 'definition' => $definition, 'state' => 'draft', 'source' => 'definition', 'log' => array(), 'equivalence' => array( 'not_builtin' ), 'published' => null );
+
+		return $new;
+	}
 	public static $errors    = array();
 
 	public static function check( $post_id, array $definition ) {
@@ -254,6 +263,7 @@ ck( 'a skipped track is shown as needing action, not left out quietly', false !=
 ck( 'the refresh is offered on the stale built-in draft only', substr_count( $html, 'name="action" value="wpcpm_track_refresh"' ), 1 );
 ck( 'a built-in track its PHP runs says so rather than offering an edit that would be refused', false !== strpos( $html, 'wpcpm-tracks__readonly' ), true );
 ck( 'Edit is offered on the track a person may edit, and on that one only', substr_count( $html, '>Edit</a>' ), 1 );
+ck( 'and Duplicate on every row, since a built-in track may be copied though not edited', substr_count( $html, '>Duplicate</a>' ), 3 );
 
 echo "\n=== Refreshing a built-in draft from the screen ===\n";
 
@@ -356,5 +366,37 @@ ck( 'the store has the last word on a built-in track, whatever the screen offere
 
 $_POST = array();
 
+echo "\n=== Duplicating a track ===\n";
+
+// The three things a copy cannot share are what the form asks for; everything else, the questions
+// above all, travels with it.
+ck( 'the duplicate form names what the copy needs of its own, and what it is copying',
+    WPCPM_Track_Builder::duplicate_form( 11 ),
+    array( 'id' => 11, 'from' => 'WordPress Credits Program 150h', 'label' => 'WordPress Credits Program 150h copy', 'status' => '', 'key' => '' ) );
+
+ob_start();
+WPCPM_Track_Builder_Screen::render_duplicate( array( 'form' => WPCPM_Track_Builder::duplicate_form( 11 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
+$duplicate = ob_get_clean();
+ck( 'and the form posts the three of them',
+    array( substr_count( $duplicate, 'name="action" value="wpcpm_track_duplicate"' ), substr_count( $duplicate, 'name="wpcpm_label"' ), substr_count( $duplicate, 'name="wpcpm_status"' ), substr_count( $duplicate, 'name="wpcpm_key"' ) ),
+    array( 1, 1, 1, 1 ) );
+
+$GLOBALS['nonce']             = WPCPM_Track_Builder::ACTION_DUPLICATE;
+WPCPM_Track_Store::$duplicated = array();
+WPCPM_Track_Store::$errors     = array( array( 'code' => 'status_taken', 'message' => 'Another track already has this status.' ) );
+$_POST                         = array( 'track' => 11, 'wpcpm_label' => 'A Copy', 'wpcpm_status' => 'In Sensei', 'wpcpm_key' => 'copy' );
+
+ck( 'a copy claiming a status another track holds is refused before anything is created',
+    array( outcome( array( $tool, 'handle_duplicate' ) ), WPCPM_Track_Store::$duplicated, WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['message'] ),
+    array( 'redirect', array(), 'Another track already has this status.' ) );
+
+WPCPM_Track_Store::$errors = array();
+$_POST['wpcpm_status']     = 'Copied Track';
+ck( 'and a copy with three of its own is created from the original, questions and all',
+    array( outcome( array( $tool, 'handle_duplicate' ) ), WPCPM_Track_Store::$duplicated[0][0], WPCPM_Track_Store::$duplicated[0][1]['status'], WPCPM_Track_Store::$duplicated[0][1]['label'], WPCPM_Track_Store::$duplicated[0][1]['key'] ),
+    array( 'redirect', 11, 'Copied Track', 'A Copy', 'copy' ) );
+
+$_POST = array();
+
 printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
 exit( $fail ? 1 : 0 );
diff --git a/bin/test-track-store.php b/bin/test-track-store.php
index e7a0f04..b050f57 100644
--- a/bin/test-track-store.php
+++ b/bin/test-track-store.php
@@ -489,6 +489,27 @@ ck( 'and writes the index, empty and autoloaded, so no request asks for an optio
 WPCPM_Track_Store::maybe_seed();
 ck( 'and never again', count( $GLOBALS['posts'] ), 4 );
 
+echo "\n=== Duplicating a track ===\n";
+
+// A new track starts as a copy in T2b (the design's decision 11), and what it was copied from is
+// post meta rather than a property: `TRACK_PROPERTIES` does not know it, and `validate()` refuses
+// a property it does not know.
+$original = WPCPM_Track_Store::create( track( 'Original Track', 'original' ) );
+update_post_meta( $original, WPCPM_Track_Store::META_SOURCE, 'builtin' );
+$copy_definition           = WPCPM_Track_Store::get( $original );
+$copy_definition['status'] = 'Copied Track';
+$copy_definition['key']    = 'copied';
+$copy_definition['label']  = 'Copied Track';
+$copy                      = WPCPM_Track_Store::duplicate( $original, $copy_definition );
+
+ck( 'the copy is a draft holding the definition it was given',
+    array( WPCPM_Track_Store::state( $copy ), WPCPM_Track_Store::get( $copy )['status'], WPCPM_Track_Store::get( $copy )['questions'] === WPCPM_Track_Store::get( $original )['questions'] ),
+    array( 'draft', 'Copied Track', true ) );
+ck( 'it records what it was copied from', (int) get_post_meta( $copy, WPCPM_Track_Store::META_DUPLICATED_FROM, true ), $original );
+ck( 'and a copy of a built-in track is its own track, not another built-in one',
+    array( WPCPM_Track_Store::source( $copy ), WPCPM_Track_Store::source( $original ) ),
+    array( 'definition', 'builtin' ) );
+
 echo "\n=== Trash, and a post that is not a track ===\n";
 
 // `publish()` read the definition and never the post, so a track somebody trashed published again
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-store.php` and `php bin/test-track-builder.php`
Expected: `Call to undefined method WPCPM_Track_Store::duplicate()` from the first, and from the second `FAIL and Duplicate on every row, since a built-in track may be copied though not edited` followed by `Call to undefined method WPCPM_Track_Builder::duplicate_form()`.

- [ ] **Step 3: Write the store's half**

```diff
diff --git a/includes/tracks/class-wpcpm-track-store.php b/includes/tracks/class-wpcpm-track-store.php
index a4c901f..65c76b1 100644
--- a/includes/tracks/class-wpcpm-track-store.php
+++ b/includes/tracks/class-wpcpm-track-store.php
@@ -75,6 +75,14 @@ final class WPCPM_Track_Store {
 	 */
 	const META_SWITCHED = '_wpcpm_track_switched';
 
+	/**
+	 * The track a copy was made from.
+	 *
+	 * Post meta rather than a property, because `TRACK_PROPERTIES` does not know it and
+	 * `validate()` refuses a property it does not know (the T2a handoff).
+	 */
+	const META_DUPLICATED_FROM = '_wpcpm_track_duplicated_from';
+
 	/** The seed version this site's Track Builder started from, set once. Autoloaded: read every request. */
 	const OPT_SEEDED = 'wpcpm_tracks_seeded';
 
@@ -180,6 +188,28 @@ final class WPCPM_Track_Store {
 		return self::save( (int) $post_id, $definition );
 	}
 
+	/**
+	 * Copy a track: a new draft holding the definition it is given, and a note of where it came from.
+	 *
+	 * The copy is nobody's built-in track, whatever the original was: `create()` marks nothing, so
+	 * its PHP runs the original and this one answers for itself (the design's decision 11).
+	 *
+	 * @param int   $from_id    The track being copied.
+	 * @param array $definition The copy's definition, identity and all.
+	 * @return int|WP_Error The new track's ID, or why it was not created.
+	 */
+	public static function duplicate( $from_id, array $definition ) {
+		$created = self::create( $definition );
+
+		if ( is_wp_error( $created ) ) {
+			return $created;
+		}
+
+		update_post_meta( (int) $created, self::META_DUPLICATED_FROM, (int) $from_id );
+
+		return $created;
+	}
+
 	/**
 	 * Store a definition on its track.
 	 *
```

- [ ] **Step 4: Write the screen's half**

```diff
diff --git a/includes/tools/class-wpcpm-track-builder-screen.php b/includes/tools/class-wpcpm-track-builder-screen.php
index cb794e2..4e4db3d 100644
--- a/includes/tools/class-wpcpm-track-builder-screen.php
+++ b/includes/tools/class-wpcpm-track-builder-screen.php
@@ -117,6 +117,14 @@ final class WPCPM_Track_Builder_Screen {
 			);
 		}
 
+		if ( '' !== $url ) {
+			printf(
+				'<a href="%1$s">%2$s</a> ',
+				esc_url( add_query_arg( 'wpcpm_duplicate', (int) $row['id'], $url ) ),
+				esc_html__( 'Duplicate', 'wpcredits-program-manager' )
+			);
+		}
+
 		if ( ! empty( $row['stale'] ) ) {
 			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
 			wp_nonce_field( WPCPM_Track_Builder::ACTION_REFRESH );
@@ -182,6 +190,61 @@ final class WPCPM_Track_Builder_Screen {
 		echo '</form>';
 	}
 
+	/**
+	 * A copy being started: the three things it cannot share with the track it comes from.
+	 *
+	 * @param array $args `form` from `WPCPM_Track_Builder::duplicate_form()`, the screen's `url`,
+	 *                    and the `flash` the last press left.
+	 */
+	public static function render_duplicate( array $args ) {
+		$form  = isset( $args['form'] ) && is_array( $args['form'] ) ? $args['form'] : array();
+		$url   = isset( $args['url'] ) ? (string) $args['url'] : '';
+		$flash = isset( $args['flash'] ) && is_array( $args['flash'] ) ? $args['flash'] : array();
+		$typed = isset( $flash['values'] ) && is_array( $flash['values'] ) ? $flash['values'] : array();
+
+		self::render_notice( $flash );
+
+		printf( '<p><a href="%1$s">%2$s</a></p>', esc_url( $url ), esc_html__( 'Back to every track', 'wpcredits-program-manager' ) );
+
+		printf(
+			'<p>%s</p>',
+			esc_html(
+				sprintf(
+					/* translators: %s: the name of the track being copied. */
+					__( 'Copying %s. Its questions come with the copy; a name, a status and a key of its own do not.', 'wpcredits-program-manager' ),
+					(string) $form['from']
+				)
+			)
+		);
+
+		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
+		wp_nonce_field( WPCPM_Track_Builder::ACTION_DUPLICATE );
+		echo '<input type="hidden" name="action" value="' . esc_attr( WPCPM_Track_Builder::ACTION_DUPLICATE ) . '" />';
+		printf( '<input type="hidden" name="track" value="%d" />', (int) $form['id'] );
+
+		echo '<table class="form-table" role="presentation"><tbody>';
+
+		foreach ( array(
+			'label'  => __( 'Name', 'wpcredits-program-manager' ),
+			'status' => __( 'Airtable status', 'wpcredits-program-manager' ),
+			'key'    => __( 'Key', 'wpcredits-program-manager' ),
+		) as $field => $heading ) {
+			$value = array_key_exists( $field, $typed ) ? $typed[ $field ] : ( isset( $form[ $field ] ) ? $form[ $field ] : '' );
+
+			printf(
+				'<tr><th scope="row"><label for="wpcpm_%1$s">%2$s</label></th><td><input type="text" class="regular-text" id="wpcpm_%1$s" name="wpcpm_%1$s" value="%3$s" /></td></tr>',
+				esc_attr( $field ),
+				esc_html( $heading ),
+				esc_attr( (string) $value )
+			);
+		}
+
+		echo '</tbody></table>';
+
+		printf( '<p class="submit"><button type="submit" class="button button-primary">%s</button></p>', esc_html__( 'Make the copy', 'wpcredits-program-manager' ) );
+		echo '</form>';
+	}
+
 	/**
 	 * The track's Learn course, when it has one.
 	 *
diff --git a/includes/tools/class-wpcpm-track-builder.php b/includes/tools/class-wpcpm-track-builder.php
index 383a875..7ef722a 100644
--- a/includes/tools/class-wpcpm-track-builder.php
+++ b/includes/tools/class-wpcpm-track-builder.php
@@ -23,6 +23,9 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 	/** Save a track's properties. */
 	const ACTION_SAVE = 'wpcpm_track_save';
 
+	/** Copy a track into a new one. */
+	const ACTION_DUPLICATE = 'wpcpm_track_duplicate';
+
 	/** Put a built-in draft back to the seed the plugin ships. */
 	const ACTION_REFRESH = 'wpcpm_track_refresh';
 
@@ -93,6 +96,7 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 	 */
 	public function boot() {
 		add_action( 'admin_post_' . self::ACTION_SAVE, array( $this, 'handle_save' ) );
+		add_action( 'admin_post_' . self::ACTION_DUPLICATE, array( $this, 'handle_duplicate' ) );
 		add_action( 'admin_post_' . self::ACTION_REFRESH, array( $this, 'handle_refresh' ) );
 	}
 
@@ -175,17 +179,55 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 	}
 
 	/**
-	 * Render the screen: one track's properties, or the list.
+	 * What a copy of a track needs of its own: the three things two tracks may never share.
+	 *
+	 * @param int $post_id The track being copied.
+	 * @return array
+	 */
+	public static function duplicate_form( $post_id ) {
+		$definition = WPCPM_Track_Store::get( $post_id );
+		$label      = is_array( $definition ) && isset( $definition['label'] ) ? (string) $definition['label'] : '';
+
+		return array(
+			'id'     => (int) $post_id,
+			'from'   => $label,
+			'label'  => '' === $label ? '' : sprintf(
+				/* translators: %s: the name of the track being copied. */
+				__( '%s copy', 'wpcredits-program-manager' ),
+				$label
+			),
+			'status' => '',
+			'key'    => '',
+		);
+	}
+
+	/**
+	 * Render the screen: a copy being started, one track's properties, or the list.
 	 */
 	public function render_admin_page() {
 		$this->require_manager();
 
-		$track = WPCPM_Request::id( 'wpcpm_track' );
-		$flash = (array) WPCPM_Flash::take( self::FLASH );
+		$track     = WPCPM_Request::id( 'wpcpm_track' );
+		$duplicate = WPCPM_Request::id( 'wpcpm_duplicate' );
+		$flash     = (array) WPCPM_Flash::take( self::FLASH );
 
 		echo '<div class="wrap wpcpm-wrap">';
 		echo '<h1>' . esc_html( $this->label() ) . '</h1>';
 
+		if ( $duplicate > 0 && is_array( WPCPM_Track_Store::get( $duplicate ) ) ) {
+			WPCPM_Track_Builder_Screen::render_duplicate(
+				array(
+					'form'  => self::duplicate_form( $duplicate ),
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
 		if ( $track > 0 && is_array( WPCPM_Track_Store::get( $track ) ) ) {
 			WPCPM_Track_Builder_Screen::render_form(
 				array(
@@ -249,7 +291,67 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 				'status'  => 'success',
 				'message' => __( 'The track was saved. Nothing reaches students until it is published.', 'wpcredits-program-manager' ),
 			),
-			$post_id
+			array( 'wpcpm_track' => $post_id )
+		);
+	}
+
+	/**
+	 * Copy a track.
+	 */
+	public function handle_duplicate() {
+		$this->verify( self::ACTION_DUPLICATE );
+
+		$from   = WPCPM_Request::posted_id( 'track' );
+		$stored = WPCPM_Track_Store::get( $from );
+
+		if ( ! is_array( $stored ) ) {
+			$this->redirect_back(
+				array(
+					'status'  => 'error',
+					'message' => __( 'That track does not exist.', 'wpcredits-program-manager' ),
+				)
+			);
+		}
+
+		$definition           = $stored;
+		$definition['label']  = WPCPM_Request::posted_text( 'wpcpm_label' );
+		$definition['status'] = WPCPM_Request::posted_text( 'wpcpm_status' );
+		$definition['key']    = WPCPM_Request::posted_text( 'wpcpm_key' );
+
+		// Checked as a track with nothing locked to it, which is what a copy is, so a status
+		// another track already holds is refused before a draft nobody asked for exists.
+		$errors = WPCPM_Track_Store::check( 0, $definition );
+
+		if ( array() !== $errors ) {
+			$this->redirect_back(
+				array(
+					'status'  => 'error',
+					'message' => (string) $errors[0]['message'],
+					'values'  => $definition,
+				),
+				array( 'wpcpm_duplicate' => $from )
+			);
+		}
+
+		$copy = WPCPM_Track_Store::duplicate( $from, $definition );
+
+		if ( is_wp_error( $copy ) ) {
+			$this->redirect_back(
+				array(
+					'status'  => 'error',
+					'message' => $copy->get_error_message(),
+					'values'  => $definition,
+				),
+				array( 'wpcpm_duplicate' => $from )
+			);
+		}
+
+		$this->redirect_back(
+			array(
+				'status'  => 'success',
+				'message' => __( 'The copy was made, as a draft. Nothing reaches students until it is published.', 'wpcredits-program-manager' ),
+			),
+			array( 'wpcpm_track' => (int) $copy )
 		);
 	}
 
@@ -310,7 +412,7 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 				'message' => $message,
 				'values'  => $definition,
 			),
-			$post_id
+			array( 'wpcpm_track' => (int) $post_id )
 		);
 	}
 
@@ -387,13 +489,13 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 	 * Back to the screen, with what happened flashed for the person who pressed.
 	 *
 	 * @param array $outcome `status`, `message`, and `values` when a form has to come back.
-	 * @param int   $track   A track to return to its form; 0 for the list.
+	 * @param array $args    Query arguments naming the screen to come back to; none for the list.
 	 */
-	private function redirect_back( array $outcome, $track = 0 ) {
+	private function redirect_back( array $outcome, array $args = array() ) {
 		$url = $this->admin_url();
 
-		if ( (int) $track > 0 ) {
-			$url = add_query_arg( 'wpcpm_track', (int) $track, $url );
+		foreach ( $args as $key => $value ) {
+			$url = add_query_arg( $key, $value, $url );
 		}
 
 		WPCPM_Flash::set( self::FLASH, $outcome );
```

- [ ] **Step 5: Run them and watch them pass**

Run: `php bin/test-track-store.php` and `php bin/test-track-builder.php`
Expected: `ALL PASS (128 checks)` and `ALL PASS (34 checks)`.

- [ ] **Step 6: Run everything**

Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 7: Commit**

```bash
git add includes/tracks/class-wpcpm-track-store.php includes/tools/class-wpcpm-track-builder.php includes/tools/class-wpcpm-track-builder-screen.php bin/test-track-store.php bin/test-track-builder.php
git commit -m "Track Builder T2b: a new track starts as a copy, which records what it came from"
```

---

### Task 8: The switch, both ways

**Files:**
- Modify: `includes/tracks/class-wpcpm-track-store.php`, `includes/tools/class-wpcpm-track-builder.php`, `includes/tools/class-wpcpm-track-builder-screen.php`
- Test: `bin/test-track-builder.php`

**Interfaces:**
- Produces: `WPCPM_Track_Store::switched( int $post_id ): bool`; `WPCPM_Track_Builder::ACTION_SWITCH_DEFINITION` and `ACTION_SWITCH_BUILTIN` with their handlers; a `switched` key on every row.
- Consumes: `WPCPM_Track_Store::switch_to_definition()` and `switch_to_builtin()` exactly as T2a's fix wave left them, refusals and all. The screen adds no rule of its own: the store has the last word.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-builder.php b/bin/test-track-builder.php
index 874db28..2bc0e09 100644
--- a/bin/test-track-builder.php
+++ b/bin/test-track-builder.php
@@ -103,6 +103,24 @@ class WPCPM_Track_Store {
 		return self::$tracks[ $post_id ]['equivalence'] ?? array( 'not_builtin' );
 	}
 
+	public static function switched( $post_id ) {
+		return ! empty( self::$tracks[ $post_id ]['switched'] );
+	}
+
+	public static $switches = array();
+
+	public static function switch_to_definition( $post_id, $user_id = 0 ) {
+		self::$switches[] = array( 'definition', (int) $post_id );
+
+		return empty( self::$tracks[ $post_id ]['equivalence'] ) ? (int) $post_id : new WP_Error( 'wpcpm_track_not_equivalent', 'The definition is not identical to the track as its PHP runs it.' );
+	}
+
+	public static function switch_to_builtin( $post_id, $user_id = 0 ) {
+		self::$switches[] = array( 'builtin', (int) $post_id );
+
+		return self::switched( $post_id ) ? (int) $post_id : new WP_Error( 'wpcpm_track_not_switched', 'That track does not run from its definition.' );
+	}
+
 	public static function published( $post_id ) {
 		return self::$tracks[ $post_id ]['published'] ?? null;
 	}
@@ -250,6 +268,7 @@ ck( 'who published it last, and when', array( $rows[0]['published_by'], $rows[0]
 ck( 'a track the last compile left out says why', array( $rows[2]['skipped'], $rows[0]['skipped'] ), array( array( 'column_reserved' ), array() ) );
 ck( 'a built-in draft that has fallen behind its PHP can be refreshed', array( $rows[1]['stale'], $rows[0]['stale'], $rows[2]['stale'] ), array( true, false, false ) );
 ck( 'and the equivalence line travels with the built-in rows', array( $rows[0]['equivalence'], $rows[2]['equivalence'] ), array( array(), array( 'not_builtin' ) ) );
+ck( 'a track that has switched to its definition says so, so the way back can be offered', array( $rows[0]['switched'], $rows[2]['switched'] ), array( false, false ) );
 
 echo "\n=== The markup ===\n";
 
@@ -398,5 +417,48 @@ ck( 'and a copy with three of its own is created from the original, questions an
 
 $_POST = array();
 
+echo "\n=== The switch, both ways ===\n";
+
+// A built-in track runs from its hand-written form until somebody flips it, and only while the two
+// are identical, which is what makes the flip invisible to students (spec decision 3.5). The way
+// back needs the same: T2a's final review found it could drop a published edit.
+WPCPM_Track_Store::$tracks[11]['equivalence'] = array();
+WPCPM_Track_Store::$tracks[12]['equivalence'] = array( 'form' );
+WPCPM_Track_Store::$tracks[13]['switched']    = true;
+WPCPM_Track_Store::$tracks[13]['equivalence'] = array();
+
+ob_start();
+WPCPM_Track_Builder_Screen::render_list( array( 'rows' => WPCPM_Track_Builder::rows(), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
+$switches = ob_get_clean();
+
+ck( 'the track that matches its PHP is offered the switch, and the one that does not is told what differs',
+    array( substr_count( $switches, 'name="action" value="wpcpm_track_switch_definition"' ), false !== strpos( $switches, 'form' ), substr_count( $switches, 'wpcpm-tracks__equivalence' ) ),
+    array( 1, true, 3 ) );
+ck( 'and a track already running from its definition is offered the way back',
+    substr_count( $switches, 'name="action" value="wpcpm_track_switch_builtin"' ), 1 );
+
+$GLOBALS['nonce']            = WPCPM_Track_Builder::ACTION_SWITCH_DEFINITION;
+WPCPM_Track_Store::$switches = array();
+$_POST                       = array( 'track' => 11 );
+
+ck( 'flipping a track to its definition says so',
+    array( outcome( array( $tool, 'handle_switch_definition' ) ), WPCPM_Track_Store::$switches, WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['status'] ),
+    array( 'redirect', array( array( 'definition', 11 ) ), 'success' ) );
+
+WPCPM_Track_Store::$switches = array();
+$_POST['track']              = 12;
+ck( 'and a track whose definition differs is refused in the store\'s own words',
+    array( outcome( array( $tool, 'handle_switch_definition' ) ), WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['message'] ),
+    array( 'redirect', 'The definition is not identical to the track as its PHP runs it.' ) );
+
+$GLOBALS['nonce']            = WPCPM_Track_Builder::ACTION_SWITCH_BUILTIN;
+WPCPM_Track_Store::$switches = array();
+$_POST['track']              = 13;
+ck( 'the way back runs through the store too',
+    array( outcome( array( $tool, 'handle_switch_builtin' ) ), WPCPM_Track_Store::$switches, WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['status'] ),
+    array( 'redirect', array( array( 'builtin', 13 ) ), 'success' ) );
+
+$_POST = array();
+
 printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
 exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-builder.php`
Expected: three failures about the switch buttons and the equivalence line, then `PHP Fatal error: Uncaught Error: Undefined constant WPCPM_Track_Builder::ACTION_SWITCH_DEFINITION`.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/tools/class-wpcpm-track-builder-screen.php b/includes/tools/class-wpcpm-track-builder-screen.php
index 4e4db3d..2705eff 100644
--- a/includes/tools/class-wpcpm-track-builder-screen.php
+++ b/includes/tools/class-wpcpm-track-builder-screen.php
@@ -92,6 +92,7 @@ final class WPCPM_Track_Builder_Screen {
 
 		printf( '<td>%s', esc_html( self::state_label( (string) $row['state'] ) ) );
 		self::render_skipped( $skipped );
+		self::render_equivalence( $row );
 		echo '</td>';
 
 		printf( '<td>%s</td>', esc_html( number_format_i18n( (int) $row['students'] ) ) );
@@ -126,12 +127,15 @@ final class WPCPM_Track_Builder_Screen {
 		}
 
 		if ( ! empty( $row['stale'] ) ) {
-			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
-			wp_nonce_field( WPCPM_Track_Builder::ACTION_REFRESH );
-			echo '<input type="hidden" name="action" value="' . esc_attr( WPCPM_Track_Builder::ACTION_REFRESH ) . '" />';
-			printf( '<input type="hidden" name="track" value="%d" />', (int) $row['id'] );
-			printf( '<button type="submit" class="button-link">%s</button>', esc_html__( 'Refresh from the plugin', 'wpcredits-program-manager' ) );
-			echo '</form>';
+			self::render_button( WPCPM_Track_Builder::ACTION_REFRESH, (int) $row['id'], __( 'Refresh from the plugin', 'wpcredits-program-manager' ) );
+		}
+
+		if ( 'builtin' === $row['source'] && empty( $row['equivalence'] ) ) {
+			self::render_button( WPCPM_Track_Builder::ACTION_SWITCH_DEFINITION, (int) $row['id'], __( 'Run from its definition', 'wpcredits-program-manager' ) );
+		}
+
+		if ( ! empty( $row['switched'] ) ) {
+			self::render_button( WPCPM_Track_Builder::ACTION_SWITCH_BUILTIN, (int) $row['id'], __( 'Run from its hand-written form', 'wpcredits-program-manager' ) );
 		}
 	}
 
@@ -245,6 +249,59 @@ final class WPCPM_Track_Builder_Screen {
 		echo '</form>';
 	}
 
+	/**
+	 * How a built-in track's definition compares with the hand-written form its PHP runs.
+	 *
+	 * Shown on the rows the switch applies to, because it is the switch's whole condition: the two
+	 * must be identical, in both directions (spec decision 3.5, and T2a's final review).
+	 *
+	 * @param array $row One row.
+	 */
+	private static function render_equivalence( array $row ) {
+		$builtin     = 'builtin' === $row['source'];
+		$differences = isset( $row['equivalence'] ) ? (array) $row['equivalence'] : array();
+
+		if ( ! $builtin && empty( $row['switched'] ) ) {
+			return;
+		}
+
+		if ( empty( $differences ) ) {
+			echo '<br /><span class="wpcpm-tracks__equivalence">' . esc_html__( 'Identical to its hand-written form.', 'wpcredits-program-manager' ) . '</span>';
+
+			return;
+		}
+
+		if ( array( 'not_published' ) === $differences ) {
+			echo '<br /><span class="wpcpm-tracks__equivalence">' . esc_html__( 'Not published yet, so there is nothing to compare with its hand-written form.', 'wpcredits-program-manager' ) . '</span>';
+
+			return;
+		}
+
+		$sentence = sprintf(
+			/* translators: %s: what differs, separated by commas. */
+			__( 'Differs from its hand-written form: %s. It cannot switch until they match.', 'wpcredits-program-manager' ),
+			implode( ', ', array_map( 'strval', $differences ) )
+		);
+
+		echo '<br /><span class="wpcpm-tracks__equivalence">' . esc_html( $sentence ) . '</span>';
+	}
+
+	/**
+	 * One button that posts to `admin-post.php`.
+	 *
+	 * @param string $action The admin-post action.
+	 * @param int    $track  The track it acts on.
+	 * @param string $label  What the button says.
+	 */
+	private static function render_button( $action, $track, $label ) {
+		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
+		wp_nonce_field( $action );
+		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />';
+		printf( '<input type="hidden" name="track" value="%d" />', (int) $track );
+		printf( '<button type="submit" class="button-link">%s</button>', esc_html( $label ) );
+		echo '</form>';
+	}
+
 	/**
 	 * The track's Learn course, when it has one.
 	 *
diff --git a/includes/tools/class-wpcpm-track-builder.php b/includes/tools/class-wpcpm-track-builder.php
index 7ef722a..46fe547 100644
--- a/includes/tools/class-wpcpm-track-builder.php
+++ b/includes/tools/class-wpcpm-track-builder.php
@@ -26,6 +26,12 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 	/** Copy a track into a new one. */
 	const ACTION_DUPLICATE = 'wpcpm_track_duplicate';
 
+	/** Run a built-in track from its definition. */
+	const ACTION_SWITCH_DEFINITION = 'wpcpm_track_switch_definition';
+
+	/** Run a switched track from its hand-written form again. */
+	const ACTION_SWITCH_BUILTIN = 'wpcpm_track_switch_builtin';
+
 	/** Put a built-in draft back to the seed the plugin ships. */
 	const ACTION_REFRESH = 'wpcpm_track_refresh';
 
@@ -97,6 +103,8 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 	public function boot() {
 		add_action( 'admin_post_' . self::ACTION_SAVE, array( $this, 'handle_save' ) );
 		add_action( 'admin_post_' . self::ACTION_DUPLICATE, array( $this, 'handle_duplicate' ) );
+		add_action( 'admin_post_' . self::ACTION_SWITCH_DEFINITION, array( $this, 'handle_switch_definition' ) );
+		add_action( 'admin_post_' . self::ACTION_SWITCH_BUILTIN, array( $this, 'handle_switch_builtin' ) );
 		add_action( 'admin_post_' . self::ACTION_REFRESH, array( $this, 'handle_refresh' ) );
 	}
 
@@ -143,6 +151,7 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 				'published_at' => (int) $last['at'],
 				'skipped'      => isset( $skipped[ $post_id ] ) ? (array) $skipped[ $post_id ] : array(),
 				'equivalence'  => WPCPM_Track_Store::equivalence( $post_id ),
+				'switched'     => WPCPM_Track_Store::switched( $post_id ),
 				'stale'        => 'builtin' === $source && ! is_array( $published ) && isset( $seeds[ $key ] ) && $seeds[ $key ] !== $definition,
 			);
 		}
@@ -416,6 +425,54 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		);
 	}
 
+	/**
+	 * Run a built-in track from its definition.
+	 */
+	public function handle_switch_definition() {
+		$this->verify( self::ACTION_SWITCH_DEFINITION );
+
+		$this->report(
+			WPCPM_Track_Store::switch_to_definition( WPCPM_Request::posted_id( 'track' ) ),
+			__( 'That track now runs from its definition. What students see has not changed, which is what let it switch.', 'wpcredits-program-manager' )
+		);
+	}
+
+	/**
+	 * Run a switched track from its hand-written form again.
+	 */
+	public function handle_switch_builtin() {
+		$this->verify( self::ACTION_SWITCH_BUILTIN );
+
+		$this->report(
+			WPCPM_Track_Store::switch_to_builtin( WPCPM_Request::posted_id( 'track' ) ),
+			__( 'That track runs from its hand-written form again.', 'wpcredits-program-manager' )
+		);
+	}
+
+	/**
+	 * Flash what the store answered and go back to the list.
+	 *
+	 * @param int|WP_Error $result  What the store answered.
+	 * @param string       $message What to say when it worked.
+	 */
+	private function report( $result, $message ) {
+		if ( is_wp_error( $result ) ) {
+			$this->redirect_back(
+				array(
+					'status'  => 'error',
+					'message' => $result->get_error_message(),
+				)
+			);
+		}
+
+		$this->redirect_back(
+			array(
+				'status'  => 'success',
+				'message' => $message,
+			)
+		);
+	}
+
 	/**
 	 * Put one built-in draft back to the seed the plugin ships.
 	 */
diff --git a/includes/tracks/class-wpcpm-track-store.php b/includes/tracks/class-wpcpm-track-store.php
index 65c76b1..5b828e8 100644
--- a/includes/tracks/class-wpcpm-track-store.php
+++ b/includes/tracks/class-wpcpm-track-store.php
@@ -555,6 +555,19 @@ final class WPCPM_Track_Store {
 		return 'builtin' === get_post_meta( (int) $post_id, self::META_SOURCE, true ) ? 'builtin' : 'definition';
 	}
 
+	/**
+	 * Whether a track runs from its definition because somebody flipped it.
+	 *
+	 * The fingerprint is written at the flip and removed when it goes back, so it is also what
+	 * says the way back is on offer at all (the design's decision 3.5).
+	 *
+	 * @param int $post_id The track.
+	 * @return bool
+	 */
+	public static function switched( $post_id ) {
+		return '' !== (string) get_post_meta( (int) $post_id, self::META_SWITCHED, true );
+	}
+
 	/**
 	 * Add a line to a track's log.
 	 *
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-builder.php`
Expected: `ALL PASS (40 checks)`.

- [ ] **Step 5: Run everything**

Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 6: Commit**

```bash
git add includes/tracks/class-wpcpm-track-store.php includes/tools/class-wpcpm-track-builder.php includes/tools/class-wpcpm-track-builder-screen.php bin/test-track-builder.php
git commit -m "Track Builder T2b: the switch, both ways, with the equivalence line beside it"
```

---

### Task 9: The screen on a real WordPress

**Files:** none. This task runs the branch on a throwaway WordPress Studio site and writes down what it saw.

The suites stand WordPress in for itself, so nothing before this task has proved that the tool reaches the Modules menu, that the seeded drafts render, or that the store's refusals hold with real posts and real meta. Ask the product owner before creating the site; it is deleted at the end, and the product owner's own site, `My WordPress Website`, is never touched.

- [ ] **Step 1: Build the zip from the branch**

```bash
bash bin/build
```

- [ ] **Step 2: Create the site and install the zip**

```bash
studio create wpcpm-t2b-check
studio start wpcpm-t2b-check
```

Install the zip through the site's own WP-CLI, then activate the plugin.

- [ ] **Step 3: Run the check**

Write this to `t2b-real-wordpress.php` and run it with `wp eval-file t2b-real-wordpress.php`. Every counter lives in `$GLOBALS`, because `wp eval-file` runs the file inside a function and top-level variables are not the globals a helper increments (T2a's ruling on its Task 6 summary).

```php
<?php
// The Track Builder screen on a real WordPress: the menu, the rows, and the store's refusals.
$GLOBALS['t2b_fails'] = 0;
$GLOBALS['t2b_total'] = 0;

function t2b_ck( $label, $actual, $expected ) {
	++$GLOBALS['t2b_total'];

	if ( $actual === $expected ) {
		echo "ok   $label\n";

		return;
	}

	++$GLOBALS['t2b_fails'];
	echo "FAIL $label\n  got:  " . var_export( $actual, true ) . "\n  want: " . var_export( $expected, true ) . "\n";
}

wp_set_current_user( 1 );

$ids = array();

foreach ( WPCPM_Track_Store::all_ids() as $id ) {
	$definition = WPCPM_Track_Store::get( $id );

	if ( is_array( $definition ) ) {
		$ids[ $definition['key'] ] = $id;
	}
}

$tools = array();

foreach ( WPCPM_Tools::all() as $tool ) {
	$tools[] = $tool->id();
}

t2b_ck( 'the Modules menu lists the Track Builder', in_array( 'track-builder', $tools, true ), true );
t2b_ck( 'the four built-in tracks are there as drafts', count( $ids ), 4 );

$rows = WPCPM_Track_Builder::rows();
t2b_ck( 'one row per track', count( $rows ), 4 );
t2b_ck( 'each built-in, each a draft, none live', array( $rows[0]['source'], $rows[0]['state'], $rows[0]['students'], $rows[0]['skipped'] ), array( 'builtin', 'draft', 0, array() ) );

ob_start();
WPCPM_Track_Builder_Screen::render_list( array( 'rows' => $rows, 'url' => 'https://example.test/x', 'flash' => array() ) );
$html = ob_get_clean();
t2b_ck( 'the list draws a row per track and offers Duplicate on each', array( substr_count( $html, 'wpcpm-tracks__row' ), substr_count( $html, '>Duplicate</a>' ) ), array( 4, 4 ) );
t2b_ck( 'and no Edit, since every track here is built-in', substr_count( $html, '>Edit</a>' ), 0 );

$design = $ids['design'];
$edit   = WPCPM_Track_Store::get( $design );

$edit['label'] = 'Designer Track, edited by hand';
$refused       = WPCPM_Track_Store::save( $design, $edit );
t2b_ck( 'the store refuses to edit a built-in track', is_wp_error( $refused ) ? $refused->get_error_code() : $refused, 'wpcpm_track_builtin' );

update_post_meta( $design, WPCPM_Track_Store::META_DEFINITION, wp_slash( WPCPM_Track_Definition::encode( $edit ) ) );
t2b_ck( 'a draft that has fallen behind is refreshed from the seed', array( WPCPM_Track_Store::refresh_builtin( $design ) === $design, WPCPM_Track_Store::get( $design ) === WPCPM_Track_Store::seeds()['design'] ), array( true, true ) );

$copy           = WPCPM_Track_Store::get( $design );
$copy['status'] = 'Studio Check Track';
$copy['key']    = 'studio-check';
$copy['label']  = 'Studio Check Track';
$made           = WPCPM_Track_Store::duplicate( $design, $copy );
t2b_ck( 'a copy is a draft of its own, recording what it came from', array( WPCPM_Track_Store::state( $made ), (int) get_post_meta( $made, WPCPM_Track_Store::META_DUPLICATED_FROM, true ), WPCPM_Track_Store::source( $made ) ), array( 'draft', $design, 'definition' ) );
t2b_ck( 'and it carries the questions', WPCPM_Track_Store::get( $made )['questions'] === WPCPM_Track_Store::get( $design )['questions'], true );

wp_trash_post( $made );
$trashed = WPCPM_Track_Store::publish( $made );
t2b_ck( 'a trashed track cannot be published', is_wp_error( $trashed ) ? $trashed->get_error_code() : $trashed, 'wpcpm_track_trashed' );
t2b_ck( 'and the list names its state', WPCPM_Track_Store::state( $made ), 'trash' );

WPCPM_Settings::save( array( 'student_statuses' => WPCPM_Settings::get()['student_statuses'] ) );
t2b_ck( 'saving the settings compiles: the index is there and empty', array( is_array( get_option( WPCPM_Tracks::OPT_TRACKS, null ) ), WPCPM_Tracks::live() ), array( true, array() ) );
t2b_ck( 'and nothing a student sees has changed', array_keys( WPCPM_Program::labels() ), array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Designer Track' ) );

printf( "\n%s (%d checks)\n", $GLOBALS['t2b_fails'] ? sprintf( '%d FAILURE(S)', $GLOBALS['t2b_fails'] ) : 'ALL PASS', $GLOBALS['t2b_total'] );
```

Expected: thirteen `ok` lines and no `FAIL` line.

- [ ] **Step 4: Delete the site**

Dry-run the delete first and check it names only `wpcpm-t2b-check`, then delete it and confirm `studio list` no longer shows it.

- [ ] **Step 5: Write the report**

No commit: this task changes no file. Record the thirteen lines, the dry run and the deletion in the task's report.

---

### Task 10: Release 1.103.0

**Files:**
- Modify: `wpcredits-program-manager.php` (the `Version:` header and `WPCPM_VERSION`), `readme.txt` (`Stable tag:` and a changelog entry), `languages/wpcredits-program-manager.pot` (regenerated)

- [ ] **Step 1: Move the version.** `1.102.0` becomes `1.103.0` in the plugin header's `Version:` line, in `define( 'WPCPM_VERSION', ... )` and in `readme.txt`'s `Stable tag:`. Every other mention of 1.102.0 stays, including the changelog's own heading.

- [ ] **Step 2: Write the changelog entry**, first under `== Changelog ==` in `readme.txt`, with one empty line after it:

```text
= 1.103.0 =

* The Track Builder screen, under Modules: every program track with its state, how many students are on it, its Learn course, who published it last, and what the last compile left out and why. Nothing on this screen reaches students; publishing a track arrives in the next release.
* A track's properties are edited here, and a new track starts as a copy of one that exists, questions and all, with a name, a status and a key of its own. What the editor refuses is exactly what publishing would refuse, so the two screens can never disagree.
* A built-in track that its hand-written form still runs is read-only, shows how its definition compares with that form, and offers the switch in both directions while the two are identical. A built-in draft that a release has left behind refreshes itself from the seed the plugin ships, and can be refreshed from its row.
* Saving the program settings compiles the tracks again, so a status moved out of "Currently mentoring" reaches both the live site and the list at once.
```

- [ ] **Step 3: Regenerate the translation template.** `sh bin/make-pot.sh`. Expected: `Success: POT file successfully generated.`, a header reading `Project-Id-Version: WPCredits Program Manager 1.103.0`, and 3180 lines matching `^msgid "`.

- [ ] **Step 4: Run everything.** Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 5: Build the zip and read it back.**

```bash
bash bin/build
unzip -p ../wpcredits-program-manager.zip wpcredits-program-manager/wpcredits-program-manager.php | grep "Version:"
unzip -Z1 ../wpcredits-program-manager.zip | grep -cE '^wpcredits-program-manager/(bin|docs)/'
```

Expected: `Version:           1.103.0` and `0`.

- [ ] **Step 6: Commit.**

```bash
git add wpcredits-program-manager.php readme.txt languages/wpcredits-program-manager.pot
git commit -m "Track Builder T2b: 1.103.0"
```

- [ ] **Step 7: Merge and mirror, on the product owner's choice.** Merge `track-builder-t2b` into `main` (fast-forward) and run the battery on the result. Then push the source to the public mirror: pull the `WordPress/WPCredits` clone at `~/GitHub/Plugins/WPCredits-Tracker-mirror` (branch `trunk`), rsync the plugin into its `Education/WordPress Education Dashboard/wpcredits-program-manager/` with `rsync -a --delete --exclude '.git/' --exclude '.superpowers/' --exclude '.DS_Store' --exclude 'node_modules/' --exclude '*.zip' --exclude '.*.swp'`, update the version in that folder's `README.md` table, scan the added lines for keys, record IDs, email addresses and names, read `git diff --stat`, then commit and push as two separate steps.

- [ ] **Step 8: Deploy only on the product owner's yes.** Ask first. On a yes, follow the deploy recorded for `wordpresseducation.org`: stream the zip over `ssh wpcredits-dashboard`, check its md5 on arrival, install it as a step of its own, read the version back, and purge the edge cache. Before the install and after it, run one read-only check of what a person sees: the program map, the Programs running card drawn as a Program Administrator, and the Student Report Card drawn as the TEST students, with version strings and nonces normalized. The two runs must be identical. Then, as an administrator, open the Track Builder screen and confirm it lists the four built-in tracks as drafts, each read-only, with no track live.

---

## What T2b leaves for T2c

- **Publishing from the screen.** The preflight of spec 7.1, the schema token and the column creation of 7.2 step 1, the checklist of 7.3 with its ticks, verify of 7.4, and unpublishing with the student guard of 7.5. `WPCPM_Track_Publish` is the class the design's three-way split reserves for it.
- **The two `Status` choices stay checklist items** (the design's decision 15, settling open item 1): no token can add a choice to an existing single select.
- **The institution gate** of decision 10: the import form and institution create offer a track only once its first checklist item is ticked. `WPCPM_Tracks::confirmed_automation_statuses()` is already there; the two flows still read `WPCPM_Program::labels()`.
- **The row's students count is ready**: `WPCPM_Students_Sync::count_on_status()` is what 7.5's refusal reads.
- **The publish lock** claims with `add_option()` and a constant value, because T2a's final review found that a claim whose value changes is not atomic.
- **The trash guard is in the store**, so the screen's Publish button may trust `state()`.
- **`wp wpcredits seed-tracks` should exit non-zero when a seed failed** (T2a's Task 5 review), which matters once it is the recovery path for a request that died mid-seed.
- Blank tracks, the question editor, preview, history and a track started from its Learn course remain T3's.
