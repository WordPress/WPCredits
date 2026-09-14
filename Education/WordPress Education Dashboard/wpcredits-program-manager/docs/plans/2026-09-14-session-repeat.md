# Session Repeat Rule Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A repeat rule on the planning form (every week, every two weeks, every four weeks, every month, with a count of sessions) that fills the dates of a series after the first, up to sixteen in one go, as release 1.109.0.

**Architecture:** The rule is a date generator in front of the series machinery of 1.108.0, nothing behind it changes: a pure `repeat_dates()` answers the dates after the first, the planning handler puts them between the first date and the "More dates" boxes and hands the whole list to `plan_dates()`, whose cap moves from nine to sixteen while the boxes keep their own count of eight. Creation under the lock, the series tag, the lists, Join all, the calendar file and the messages are 1.108.0's.

**Tech Stack:** WordPress 6.5 and PHP 7.4 as floors, WordPress coding standards, the plugin's standalone `bin/test-*.php` suites, `bin/build-docs.php` for the guides, no build step, no new script; PHP's relative date formats ("second tuesday of this month") for the monthly rule.

**Spec:** `docs/specs/2026-09-14-session-series-design.md` at `f502128`: decisions 7 to 9 of 14 September 2026 in section 1 and the amendment in section 12 (the control, the count, the dates the rule makes, the list, the tests, the guides and the release); section 11 for what stays out.

## Global Constraints

- **Start from `main` at 1.108.1 with the amended spec.** `grep "^Stable tag" readme.txt` prints `Stable tag: 1.108.1`, `git log --oneline -1 -- docs/specs/2026-09-14-session-series-design.md` names `f502128`, and `git status --short` prints nothing. Then `git switch -c session-repeat`. Every block below was proven one commit at a time on `f502128` and replayed onto a fresh checkout.
- WordPress coding standards throughout: tabs, Yoda conditions, spaces inside parentheses, `array()` and never `[]`, strict `in_array()`. Everything that ships is PHP 7.4 compatible. Every string a person reads is US English. No em dash or en dash anywhere, in code, comments, docs or commit messages: a plain hyphen. Full product names ("Student Report Card", "Mentor Report Card", "Administrator Dashboard").
- Everything a person can see is escaped on output; the handler keeps its capability check and its nonce first, and reads every posted value through `wp_unslash()` and a sanitizer.
- **The battery stays silent after every task:** `for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done` prints nothing. `php bin/check-references.php`, `php bin/check-spelling.php` and `php bin/check-dead-annotations.php` report clean. `bash bin/check-standards.sh` prints no line containing ` ERROR `, and its last line reads `85 warnings, no errors.` or a lower count. Never run it with `--fix`. What tripped it while the series was proven still applies: a multi-item keyed array on one line is refused; a stand-alone `$i++` is refused (`++$i`); a missing `@param` is refused.
- **Test first, every task.** Add the checks, run them, see them fail as the step says, then write the code. A handler is exercised in `bin/test-handlers.php`, which runs it with `run()` and reads the flash it left from the raw `wpcpm_flash` user meta (`flashed()`); a rule that can be pure is tested pure in `bin/test-group-sessions.php`.
- **Nothing after the list changes.** A series planned by rule is a series as 1.108.0 plans one from typed dates: the same posts, the same tag, the same notices, lists, Join all, file and messages. A session planned alone still carries no series meta.
- **Nothing is deleted in Airtable, ever.** Nothing in this plan touches Airtable at all.
- Version numbers move in Task 4 only: plugin 1.109.0. The theme's rule for the new `<select>` is a theme release of its own, made by the controller after the merge (see the closing notes); nothing in this plan touches `wpcredits-theme`.
- Comments explain why and name the decision or the review that made the rule. Every commit message starts with "Group sessions:" and ends with the `Co-Authored-By:` trailer of whoever made it, after a blank line.

## What this plan decides

1. **The rule's dates come before the boxes** in the list `plan_dates()` reads, so a box that repeats a date the rule made is refused as a date given twice, named, like any repeat.
2. **One cap, sixteen, and the boxes keep their own eight.** `MAX_SERIES` becomes 16 and a new `MORE_BOXES` (8) draws the boxes, so the form does not grow fifteen boxes when the cap grows; the cap's notice says "sixteen".
3. **"Every month" is PHP's relative format.** The first date's weekday and its place in the month (days 1 to 7 first, 8 to 14 second, and so on) become "second tuesday of this month" on the first day of each following month; a fifth week reads as "last", which is the fifth when a month has one (the design's decision 9).
4. **A tampered rule is an error, a bad count is named.** A `repeat` value the form does not offer bounces `error`; a rule with a count that is empty, not a whole number, below 2 or above 16 bounces the new `series-count`; "Does not repeat" ignores the count box.
5. **The Repeat row is one flex line spanning the form's grid**, its two labels inline beside their controls and the hint under them, with the plugin's own field rules narrowed by a doubled class so the order of rules cannot undo it.

## File structure

**Created**

None.

**Modified**

| File | Why |
| --- | --- |
| `includes/modules/class-wpcpm-group-sessions.php` | `MAX_SERIES` 16, `MORE_BOXES`; `repeat_rules()`, `repeat_dates()`; `handle_create()` reads the rule and its count; the form's Repeat row. |
| `includes/modules/class-wpcpm-mentor-calls.php` | `series-many` says sixteen; `series-count`. |
| `assets/css/calendar.css` | The Repeat row. |
| `docs/sections/21-mentor-availability.md`, `docs/mentors.md`, `docs/administrators.md`, `docs/build/mentors.html`, `docs/build/administrators.html` | The Repeat bullet. |
| `bin/test-group-sessions.php`, `bin/test-handlers.php` | The rule's dates, the cap, the handler and the form. |
| `wpcredits-program-manager.php`, `readme.txt`, `languages/wpcredits-program-manager.pot` | The release. |

---

### Task 1: The dates a repeat rule makes, and a series of sixteen

**Files:**
- Modify: `includes/modules/class-wpcpm-group-sessions.php` (`MAX_SERIES` 16, `MORE_BOXES`; `repeat_dates()`; the boxes drawn from `MORE_BOXES`)
- Modify: `includes/modules/class-wpcpm-mentor-calls.php` (`series-many` says sixteen)
- Test: `bin/test-group-sessions.php`

**Interfaces:**
- Consumes: `plan_dates( array $dates, $time, DateTimeZone $zone, $now )` as it stands, `WPCPM_Mentor_Calls::message()`.
- Produces: `WPCPM_Group_Sessions::MAX_SERIES` (16), the cap `plan_dates()` refuses above; `WPCPM_Group_Sessions::MORE_BOXES` (8), the boxes the form draws. `repeat_dates( $first, $rule, $count, DateTimeZone $zone )`: the `Y-m-d` dates after the first, `$count - 1` of them, for `week`, `2weeks`, `4weeks` (7, 14, 28 days on) and `month` (the same weekday and place in the month, a fifth week falling back to the last such weekday); `array()` for an unknown rule, a count below two or a first date that is not a date. The `series-many` sentence reads "A series holds sixteen sessions at most; plan the rest in a second go."

The design's decisions 8 and 9 in code. The cap is one constant, and the form's boxes get a constant of their own so the form does not grow fifteen boxes when the cap grows (what this plan decides, 2); the checks that pinned "ten are refused" now pin seventeen refused and sixteen planned, and read the cap's own sentence. `repeat_dates()` makes dates only, on the mentor's calendar: the time goes on each of them in `plan_dates()`, which is what keeps a rule at its clock time across a change to or from summer time. The monthly rule is PHP's relative format on the first day of each following month (what this plan decides, 3), and the checks pin it against dates worked out by hand: the second Tuesday from 13 October 2026 across the year end, a fifth Friday (30 October 2026) falling back to the last Friday of November, December and January.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-group-sessions.php b/bin/test-group-sessions.php
index 55cc24d..280870e 100644
--- a/bin/test-group-sessions.php
+++ b/bin/test-group-sessions.php
@@ -557,14 +557,59 @@ ck( 'a date that is not a date, a date that has passed and a date given twice ea
         array( 'starts' => array(), 'refused' => 'twice', 'date' => '2026-10-06' ),
     ) );
 
-$ten = array();
-for ( $i = 1; $i <= 10; ++$i ) {
-	$ten[] = sprintf( '2026-11-%02d', $i );
+$seventeen = array();
+for ( $i = 1; $i <= 17; ++$i ) {
+	$seventeen[] = sprintf( '2026-11-%02d', $i );
 }
 
-ck( 'ten dates are refused before any is read: a series holds nine',
-    WPCPM_Group_Sessions::plan_dates( $ten, '18:00', $riga, $now ),
-    array( 'starts' => array(), 'refused' => 'many', 'date' => '' ) );
+ck( 'seventeen dates are refused before any is read, and sixteen are planned: a series holds sixteen (the design\'s decision 8)',
+    array(
+        WPCPM_Group_Sessions::plan_dates( $seventeen, '18:00', $riga, $now ),
+        count( WPCPM_Group_Sessions::plan_dates( array_slice( $seventeen, 0, 16 ), '18:00', $riga, $now )['starts'] ),
+        false !== strpos( WPCPM_Mentor_Calls::message( 'series-many' )[1], 'sixteen' ),
+    ),
+    array( array( 'starts' => array(), 'refused' => 'many', 'date' => '' ), 16, true ) );
+
+echo "\n=== A repeat rule fills the dates after the first (1.109.0) ===\n";
+
+ck( 'every week, every two weeks and every four weeks step by days from the first date, which is not repeated',
+    array(
+        WPCPM_Group_Sessions::repeat_dates( '2026-10-06', 'week', 4, $riga ),
+        WPCPM_Group_Sessions::repeat_dates( '2026-10-06', '2weeks', 3, $riga ),
+        WPCPM_Group_Sessions::repeat_dates( '2026-10-06', '4weeks', 2, $riga ),
+    ),
+    array(
+        array( '2026-10-13', '2026-10-20', '2026-10-27' ),
+        array( '2026-10-20', '2026-11-03' ),
+        array( '2026-11-03' ),
+    ) );
+
+ck( 'every month keeps the weekday and its place in the month across a year end: the second Tuesday stays the second Tuesday',
+    WPCPM_Group_Sessions::repeat_dates( '2026-10-13', 'month', 5, $riga ),
+    array( '2026-11-10', '2026-12-08', '2027-01-12', '2027-02-09' ) );
+
+ck( 'a first date in a fifth week takes the last such weekday of a month that has no fifth',
+    WPCPM_Group_Sessions::repeat_dates( '2026-10-30', 'month', 4, $riga ),
+    array( '2026-11-27', '2026-12-25', '2027-01-29' ) );
+
+$sixteen_weeks = WPCPM_Group_Sessions::repeat_dates( '2026-10-06', 'week', 16, $riga );
+
+ck( 'sixteen in all is fifteen more, two is one more; an unknown rule, a count of one and a first date that is not a date make nothing',
+    array(
+        count( $sixteen_weeks ),
+        end( $sixteen_weeks ),
+        WPCPM_Group_Sessions::repeat_dates( '2026-10-06', 'week', 2, $riga ),
+        WPCPM_Group_Sessions::repeat_dates( '2026-10-06', 'daily', 3, $riga ),
+        WPCPM_Group_Sessions::repeat_dates( '2026-10-06', 'week', 1, $riga ),
+        WPCPM_Group_Sessions::repeat_dates( 'not-a-date', 'week', 3, $riga ),
+    ),
+    array( 15, '2027-01-19', array( '2026-10-13' ), array(), array(), array() ) );
+
+$weekly = WPCPM_Group_Sessions::plan_dates( array_merge( array( '2026-10-06' ), $sixteen_weeks ), '18:00', $riga, $now );
+
+ck( 'the dates a rule makes go through plan_dates() like any list: sixteen starts, none refused',
+    array( $weekly['refused'], count( $weekly['starts'] ) ),
+    array( '', 16 ) );
 
 ck( 'the first start the mentor already holds is the clash, and none is no clash',
     array(
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-group-sessions.php`

Expected: `php bin/test-group-sessions.php` prints `FAIL seventeen dates are refused before any is read, and sixteen are planned: a series holds sixteen (the design's decision 8)` and then stops with `Fatal error: Uncaught Error: Call to undefined method WPCPM_Group_Sessions::repeat_dates()`. The cap is still nine, and the rule does not exist yet.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/modules/class-wpcpm-group-sessions.php b/includes/modules/class-wpcpm-group-sessions.php
index c82fa72..87d31fe 100644
--- a/includes/modules/class-wpcpm-group-sessions.php
+++ b/includes/modules/class-wpcpm-group-sessions.php
@@ -76,12 +76,16 @@ class WPCPM_Group_Sessions {
 	const MIN_CAPACITY = 2;
 
 	/**
-	 * Most sessions one form plans: the first date and eight more (the design's decision 1).
+	 * Most sessions one form plans (the design's decision 8): a semester of weekly sessions from
+	 * one repeat rule, the boxes counted with it.
 	 *
-	 * A ceiling like `MAX_CAPACITY`: the dates are boxes on a form, and a longer series is planned
-	 * in a second go rather than becoming a term of sessions nobody meant.
+	 * A ceiling like `MAX_CAPACITY`: a longer series is planned in a second go rather than
+	 * becoming a year of sessions nobody meant.
 	 */
-	const MAX_SERIES = 9;
+	const MAX_SERIES = 16;
+
+	/** The "More dates" boxes on the planning form, for odd dates (the design's decision 1). */
+	const MORE_BOXES = 8;
 
 	/** Longest a session may run, in minutes. */
 	const MAX_MINUTES = 480;
@@ -312,9 +316,9 @@ class WPCPM_Group_Sessions {
 		}
 
 		// The same lock a booking takes, and for the same reason: the diary is read here and up to
-		// nine sessions are written from what it said, so a student booking a call on this mentor
+		// sixteen sessions are written from what it said, so a student booking a call on this mentor
 		// could win one of those starts in between and the sessions would go in over it. One date
-		// was a narrow window; nine is nine times the window (the final review of 1.108.0).
+		// was a narrow window; sixteen is sixteen times the window (the final review of 1.108.0).
 		if ( ! WPCPM_Mentor_Calls::lock_for( $mentor_id ) ) {
 			self::bounce( 'busy' );
 		}
@@ -436,6 +440,65 @@ class WPCPM_Group_Sessions {
 		);
 	}
 
+	/**
+	 * The dates a repeat rule makes after the first (the design's section 12, decisions 7 and 9).
+	 *
+	 * Dates only, on the mentor's calendar: the time goes on each of them in `plan_dates()`, which
+	 * is what keeps a rule at its clock time across a change to or from summer time. `week`,
+	 * `2weeks` and `4weeks` step by days; `month` keeps the weekday and its place in the month, so
+	 * the second Tuesday stays the second Tuesday, and a first date in a fifth week takes the last
+	 * such weekday of a month that has no fifth (decision 9).
+	 *
+	 * @param string       $first The first date, `Y-m-d`.
+	 * @param string       $rule  `week`, `2weeks`, `4weeks` or `month`.
+	 * @param int          $count Sessions in all, the first counted.
+	 * @param DateTimeZone $zone  The mentor's calendar.
+	 * @return string[] The `Y-m-d` dates after the first, `$count - 1` of them; empty for an unknown
+	 *                  rule, a count below two or a first date that is not a date.
+	 */
+	public static function repeat_dates( $first, $rule, $count, DateTimeZone $zone ) {
+		$days  = array(
+			'week'   => 7,
+			'2weeks' => 14,
+			'4weeks' => 28,
+			'month'  => 0,
+		);
+		$count = (int) $count;
+		$start = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $first, $zone );
+
+		if ( ! isset( $days[ $rule ] ) || $count < 2 || false === $start || $start->format( 'Y-m-d' ) !== (string) $first ) {
+			return array();
+		}
+
+		$dates = array();
+
+		if ( $days[ $rule ] > 0 ) {
+			for ( $i = 1; $i < $count; ++$i ) {
+				$dates[] = $start->modify( '+' . ( $i * $days[ $rule ] ) . ' days' )->format( 'Y-m-d' );
+			}
+
+			return $dates;
+		}
+
+		// Days 1 to 7 are a month's first such weekday, 8 to 14 its second, and so on; the fifth
+		// exists in some months only, so it reads as the last, which is the fifth when there is one.
+		$ordinals = array(
+			1 => 'first',
+			2 => 'second',
+			3 => 'third',
+			4 => 'fourth',
+		);
+		$place    = (int) ceil( (int) $start->format( 'j' ) / 7 );
+		$which    = isset( $ordinals[ $place ] ) ? $ordinals[ $place ] : 'last';
+		$weekday  = strtolower( $start->format( 'l' ) );
+
+		for ( $i = 1; $i < $count; ++$i ) {
+			$dates[] = $start->modify( 'first day of +' . $i . ' month' )->modify( $which . ' ' . $weekday . ' of this month' )->format( 'Y-m-d' );
+		}
+
+		return $dates;
+	}
+
 	/**
 	 * The first of the starts the mentor already holds, or 0.
 	 *
@@ -519,7 +582,7 @@ class WPCPM_Group_Sessions {
 
 	/**
 	 * Refuse a list of dates: with the words a lone session has always had for one date, and with
-	 * the date named for a series, where the mentor has to find which of nine it was.
+	 * the date named for a series, where the mentor has to find which of sixteen it was.
 	 *
 	 * @param string $why   `when`, `past`, `twice`, `clash` or `many`.
 	 * @param string $date  The date refused, `Y-m-d`, or ''.
@@ -1038,15 +1101,15 @@ class WPCPM_Group_Sessions {
 		);
 
 		// More dates, for a series (the design's decision 1): the same time, length, places and
-		// topic for every one, an empty box passed over. Eight, so the first date and these make
-		// the nine a series holds at most.
+		// topic for every one, an empty box passed over. Eight boxes; a longer series comes from
+		// the repeat rule (the design's section 12), and the whole list stops at `MAX_SERIES`.
 		echo '<fieldset class="wpcpm-field wpcpm-sessions__more">';
 		printf( '<legend>%s</legend>', esc_html__( 'More dates', 'wpcredits-program-manager' ) );
 
 		// Each box says which date it is and nothing else, so the sentence below is named as their
 		// description: a screen reader that reads "Date 5" alone would never reach the one place
 		// that says an empty box is fine (the final review of 1.108.0).
-		for ( $more = 2; $more <= self::MAX_SERIES; ++$more ) {
+		for ( $more = 2; $more <= self::MORE_BOXES + 1; ++$more ) {
 			printf(
 				'<input type="date" name="more_dates[]" aria-label="%s" aria-describedby="wpcpm-sessions-more-hint" />',
 				/* translators: %d: the date's place in the series, from 2. */
diff --git a/includes/modules/class-wpcpm-mentor-calls.php b/includes/modules/class-wpcpm-mentor-calls.php
index 596ea8e..42c5abc 100644
--- a/includes/modules/class-wpcpm-mentor-calls.php
+++ b/includes/modules/class-wpcpm-mentor-calls.php
@@ -1267,7 +1267,7 @@ class WPCPM_Mentor_Calls {
 			'series-twice'        => array( 'error', __( 'One of the dates is given twice: %s.', 'wpcredits-program-manager' ) ),
 			/* translators: %s: a date. */
 			'series-clash'        => array( 'error', __( 'Something else of yours already starts on %s at that time.', 'wpcredits-program-manager' ) ),
-			'series-many'         => array( 'error', __( 'A series holds nine sessions at most; plan the rest in a second go.', 'wpcredits-program-manager' ) ),
+			'series-many'         => array( 'error', __( 'A series holds sixteen sessions at most; plan the rest in a second go.', 'wpcredits-program-manager' ) ),
 			/* translators: %d: how many sessions the student is on. */
 			'series-joined'       => array( 'success', __( 'You are on all %d sessions. They are in your list above, and one email holds the ones you joined just now for your calendar.', 'wpcredits-program-manager' ) ),
 			/* translators: 1: sessions the student is on, 2: sessions in the series, 3: sessions that were full. */
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-group-sessions.php`

Expected: `php bin/test-group-sessions.php` ends `ALL PASS (83 checks)`.

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
git add bin/test-group-sessions.php includes/modules/class-wpcpm-group-sessions.php includes/modules/class-wpcpm-mentor-calls.php
git commit -m "Group sessions: the dates a repeat rule makes, and a series of sixteen"
```

---

### Task 2: The planning form's repeat rule and its count

**Files:**
- Modify: `includes/modules/class-wpcpm-group-sessions.php` (`repeat_rules()`; `handle_create()` reads `repeat` and `repeat_count`; the form's Repeat row)
- Modify: `includes/modules/class-wpcpm-mentor-calls.php` (`series-count`)
- Modify: `assets/css/calendar.css` (the Repeat row)
- Test: `bin/test-handlers.php`

**Interfaces:**
- Consumes: Task 1's `repeat_dates()` and `MAX_SERIES`; `plan_dates()`, `refuse_dates()`, `create_sessions()` and the lock as they stand.
- Produces: `WPCPM_Group_Sessions::repeat_rules()`: the rules the form offers, key to label, `''` for "Does not repeat", then `week`, `2weeks`, `4weeks`, `month`. `handle_create()` reads `repeat` (`sanitize_key()`; a value the form does not offer bounces `error`) and, for a rule, `repeat_count` (a whole number from 2 to `MAX_SERIES`, else `series-count`), puts the rule's dates between the first date and the boxes, and goes on as before. The new flag `series-count`: "Say how many sessions the repeat should make, from 2 to 16." The form's Repeat row: `<p class="wpcpm-field wpcpm-sessions__repeat">` with `<select id="wpcpm-session-repeat" name="repeat">` of the five rules, `<input type="number" id="wpcpm-session-repeat-count" name="repeat_count" min="2" max="16" step="1" aria-describedby="wpcpm-sessions-repeat-hint">` and the hint "Counting the first date. Up to sixteen."

The design's section 12 on the form and in the handler. The rule's dates go into the list before the boxes, so a box that repeats one of them is refused as a date given twice, named (what this plan decides, 1); after that the handler is 1.108.0's: `plan_dates()`, the lock, the clash, `create_sessions()`, the notice with its count. The mentor's zone is read before the rule rather than after the boxes, since the rule needs it. A rule with a bad count is named (`series-count`), a rule the form does not offer is a tampered form and bounces `error`, and "Does not repeat" ignores whatever the count box holds (what this plan decides, 4).

The Repeat row is one flex line spanning the form's grid, the labels inline beside their controls and the hint under them; the plugin's own field rules stack labels and stretch inputs, so they are narrowed by a doubled class (what this plan decides, 5). The theme's rule for the select is a theme release of its own (the closing notes).

`bin/test-handlers.php` plans eight weekly sessions through the handler and reads the flash, the count of posts, the one series they share and their eight dates a week apart; then presses "Does not repeat" with a stray count, a rule with a missing, low, high and non-numeric count, sixteen by rule plus one box, and a rule the form does not offer, each refused with nothing created; then renders the planner and reads the select's five options and the count box's bounds and hint.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-handlers.php b/bin/test-handlers.php
index cfddd14..e069707 100644
--- a/bin/test-handlers.php
+++ b/bin/test-handlers.php
@@ -776,6 +776,73 @@ check( 'a session that has started offers neither Join nor Leave and says so, it
     ),
     array( 1, 0, 2, 2, 1 ) );
 
+// 1.109.0: a repeat rule fills the dates after the first (the design's section 12).
+$GLOBALS['uid']          = 20;
+$GLOBALS['query_result'] = array();
+$posts_before            = count( $GLOBALS['posts'] );
+$_POST                   = array( 'mentor' => 20, 'date' => '2027-06-01', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'topic' => 'Office hours', 'repeat' => 'week', 'repeat_count' => '8' );
+run( 'handle_create (every week, eight sessions)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
+$weekly_ids = array();
+foreach ( $GLOBALS['posts'] as $post ) {
+	if ( 'Office hours' === $post->post_content ) {
+		$weekly_ids[] = $post->ID;
+	}
+}
+check( 'a rule of eight weeks plans eight sessions in one series, a week apart, the notice counting them',
+    array(
+        flashed( 20, 'call' ),
+        count( $GLOBALS['posts'] ) - $posts_before,
+        count( array_unique( array_map( 'WPCPM_Group_Sessions::series_of', $weekly_ids ) ) ),
+        array_map( function ( $id ) { return gmdate( 'Y-m-d', (int) get_post_meta( $id, WPCPM_Mentor_Calls::META_START, true ) ); }, $weekly_ids ),
+    ),
+    array( array( 'series-planned', 8 ), 8, 1, array( '2027-06-01', '2027-06-08', '2027-06-15', '2027-06-22', '2027-06-29', '2027-07-06', '2027-07-13', '2027-07-20' ) ) );
+
+$posts_before = count( $GLOBALS['posts'] );
+$_POST        = array( 'mentor' => 20, 'date' => '2027-08-03', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'repeat' => '', 'repeat_count' => '5' );
+run( 'handle_create (does not repeat, a stray count)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
+$stray = array( flashed( 20, 'call' ), count( $GLOBALS['posts'] ) - $posts_before );
+
+$posts_before = count( $GLOBALS['posts'] );
+$outcomes     = array();
+foreach ( array( 'missing' => null, 'low' => '1', 'high' => '17', 'text' => 'ten' ) as $label => $bad ) {
+	$_POST = array( 'mentor' => 20, 'date' => '2027-09-07', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'repeat' => '2weeks' );
+	if ( null !== $bad ) {
+		$_POST['repeat_count'] = $bad;
+	}
+	run( 'handle_create (a rule with a ' . $label . ' count)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
+	$outcomes[ $label ] = flashed( 20, 'call' );
+}
+$_POST = array( 'mentor' => 20, 'date' => '2027-09-07', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'repeat' => 'week', 'repeat_count' => '16', 'more_dates' => array( '2028-01-04' ) );
+run( 'handle_create (sixteen by rule and one box)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
+$outcomes['many'] = flashed( 20, 'call' );
+$_POST            = array( 'mentor' => 20, 'date' => '2027-09-07', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'repeat' => 'daily', 'repeat_count' => '3' );
+run( 'handle_create (a rule the form does not offer)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
+$outcomes['unknown'] = flashed( 20, 'call' );
+
+check( '"Does not repeat" ignores a stray count and plans one; a rule with a missing, low, high or non-numeric count, sixteen by rule plus a box, and a rule the form does not offer each refuse the form and create nothing',
+    array( $stray, $outcomes, count( $GLOBALS['posts'] ) - $posts_before ),
+    array(
+        array( 'session-created', 1 ),
+        array( 'missing' => 'series-count', 'low' => 'series-count', 'high' => 'series-count', 'text' => 'series-count', 'many' => 'series-many', 'unknown' => 'error' ),
+        0,
+    ) );
+
+ob_start();
+WPCPM_Group_Sessions::render_mentor_planner( $GLOBALS['users'][20] );
+$planner = ob_get_clean();
+check( 'the form offers the five repeat rules and a count box from 2 to 16, described by its hint',
+    array(
+        substr_count( $planner, '<select id="wpcpm-session-repeat" name="repeat">' ),
+        false !== strpos( $planner, '<option value="">Does not repeat</option>' ),
+        false !== strpos( $planner, '<option value="week">Every week</option>' ),
+        false !== strpos( $planner, '<option value="2weeks">Every two weeks</option>' ),
+        false !== strpos( $planner, '<option value="4weeks">Every four weeks</option>' ),
+        false !== strpos( $planner, '<option value="month">Every month</option>' ),
+        substr_count( $planner, 'name="repeat_count" min="2" max="16" step="1" aria-describedby="wpcpm-sessions-repeat-hint"' ),
+        substr_count( $planner, 'id="wpcpm-sessions-repeat-hint"' ),
+    ),
+    array( 1, true, true, true, true, true, 1, 1 ) );
+
 $GLOBALS['query_result'] = array();
 
 $GLOBALS['uid'] = 20;
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-handlers.php`

Expected: `php bin/test-handlers.php` prints `FAIL a rule of eight weeks plans eight sessions in one series, a week apart, the notice counting them`, `FAIL "Does not repeat" ignores a stray count and plans one; a rule with a missing, low, high or non-numeric count, sixteen by rule plus a box, and a rule the form does not offer each refuse the form and create nothing` and `FAIL the form offers the five repeat rules and a count box from 2 to 16, described by its hint`, and ends `3 FAILURE(S)`. The handler ignores the rule and plans one session, and the form has no Repeat row.

- [ ] **Step 3: Write the code**

```diff
diff --git a/assets/css/calendar.css b/assets/css/calendar.css
index c8952f0..3722499 100644
--- a/assets/css/calendar.css
+++ b/assets/css/calendar.css
@@ -824,6 +824,30 @@
 	grid-column: 1 / -1;
 }
 
+/* The repeat rule (1.109.0): the two controls beside their labels on one line, the hint under
+   them; the row spans the form like the boxes it fills. The field's own label and input rules
+   would stack the labels and stretch the count box, so both are narrowed here. */
+.wpcpm-field.wpcpm-sessions__repeat {
+	align-items: center;
+	display: flex;
+	flex-wrap: wrap;
+	gap: 0.35em 0.6em;
+	grid-column: 1 / -1;
+}
+
+.wpcpm-field.wpcpm-sessions__repeat label {
+	display: inline;
+	margin: 0;
+}
+
+.wpcpm-field.wpcpm-sessions__repeat input {
+	width: 5em;
+}
+
+.wpcpm-field.wpcpm-sessions__repeat .wpcpm-field__hint {
+	flex-basis: 100%;
+}
+
 .wpcpm-field label {
 	display: block;
 	font-size: 0.9em;
diff --git a/includes/modules/class-wpcpm-group-sessions.php b/includes/modules/class-wpcpm-group-sessions.php
index 87d31fe..abf320c 100644
--- a/includes/modules/class-wpcpm-group-sessions.php
+++ b/includes/modules/class-wpcpm-group-sessions.php
@@ -284,10 +284,33 @@ class WPCPM_Group_Sessions {
 			self::bounce( 'session-capacity' );
 		}
 
-		// The rest of a series, when the form carries more dates (the design's decision 1): each
-		// read as the first is, an empty box passed over, a box holding no date refusing the form.
+		// Entered in the mentor's own clock - the one they mean when they say "Tuesday at two" -
+		// and stored as UTC, exactly as the weekly hours are.
+		$zone  = WPCPM_Mentor_Availability::timezone( WPCPM_Mentor_Availability::get( $mentor_id )['timezone'] );
 		$dates = array( $date );
 
+		// A repeat rule fills the dates after the first (the design's section 12): a rule needs a
+		// count from 2 to `MAX_SERIES`, "Does not repeat" ignores whatever the count box holds,
+		// and a rule the form does not offer can only be a tampered form.
+		$rule = isset( $_POST['repeat'] ) ? sanitize_key( wp_unslash( $_POST['repeat'] ) ) : '';
+
+		if ( '' !== $rule ) {
+			if ( ! isset( self::repeat_rules()[ $rule ] ) ) {
+				self::bounce( 'error' );
+			}
+
+			$count = isset( $_POST['repeat_count'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['repeat_count'] ) ) ) : '';
+
+			if ( ! ctype_digit( $count ) || (int) $count < 2 || (int) $count > self::MAX_SERIES ) {
+				self::bounce( 'series-count' );
+			}
+
+			$dates = array_merge( $dates, self::repeat_dates( $date, $rule, (int) $count, $zone ) );
+		}
+
+		// The rest of a series, when the form carries more dates (the design's decision 1): each
+		// read as the first is, an empty box passed over, a box holding no date refusing the form.
+		// After the rule's dates, so a box repeating one of them reads as a date given twice.
 		if ( isset( $_POST['more_dates'] ) && is_array( $_POST['more_dates'] ) ) {
 			foreach ( wp_unslash( $_POST['more_dates'] ) as $more ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each entry is sanitized and validated below.
 				$more = sanitize_text_field( (string) $more );
@@ -306,9 +329,6 @@ class WPCPM_Group_Sessions {
 			}
 		}
 
-		// Entered in the mentor's own clock - the one they mean when they say "Tuesday at two" -
-		// and stored as UTC, exactly as the weekly hours are.
-		$zone    = WPCPM_Mentor_Availability::timezone( WPCPM_Mentor_Availability::get( $mentor_id )['timezone'] );
 		$planned = self::plan_dates( $dates, $time, $zone, time() );
 
 		if ( '' !== $planned['refused'] ) {
@@ -440,6 +460,21 @@ class WPCPM_Group_Sessions {
 		);
 	}
 
+	/**
+	 * The repeat rules the planning form offers, the empty key for none (the design's section 12).
+	 *
+	 * @return array<string,string> Rule key to its label.
+	 */
+	public static function repeat_rules() {
+		return array(
+			''       => __( 'Does not repeat', 'wpcredits-program-manager' ),
+			'week'   => __( 'Every week', 'wpcredits-program-manager' ),
+			'2weeks' => __( 'Every two weeks', 'wpcredits-program-manager' ),
+			'4weeks' => __( 'Every four weeks', 'wpcredits-program-manager' ),
+			'month'  => __( 'Every month', 'wpcredits-program-manager' ),
+		);
+	}
+
 	/**
 	 * The dates a repeat rule makes after the first (the design's section 12, decisions 7 and 9).
 	 *
@@ -1100,6 +1135,29 @@ class WPCPM_Group_Sessions {
 			esc_html__( 'Date', 'wpcredits-program-manager' )
 		);
 
+		// A repeat rule (the design's section 12): the rule makes the dates after the first, the
+		// boxes below stay for odd ones. The count box says what its bounds are, and the hint says
+		// that the first date counts.
+		echo '<p class="wpcpm-field wpcpm-sessions__repeat">';
+		printf( '<label for="wpcpm-session-repeat">%s</label>', esc_html__( 'Repeat', 'wpcredits-program-manager' ) );
+		echo '<select id="wpcpm-session-repeat" name="repeat">';
+
+		foreach ( self::repeat_rules() as $rule => $label ) {
+			printf( '<option value="%1$s">%2$s</option>', esc_attr( $rule ), esc_html( $label ) );
+		}
+
+		echo '</select>';
+		printf( '<label for="wpcpm-session-repeat-count">%s</label>', esc_html__( 'Sessions', 'wpcredits-program-manager' ) );
+		printf(
+			'<input type="number" id="wpcpm-session-repeat-count" name="repeat_count" min="2" max="%d" step="1" aria-describedby="wpcpm-sessions-repeat-hint" />',
+			(int) self::MAX_SERIES
+		);
+		printf(
+			'<span class="wpcpm-field__hint" id="wpcpm-sessions-repeat-hint">%s</span>',
+			esc_html__( 'Counting the first date. Up to sixteen.', 'wpcredits-program-manager' )
+		);
+		echo '</p>';
+
 		// More dates, for a series (the design's decision 1): the same time, length, places and
 		// topic for every one, an empty box passed over. Eight boxes; a longer series comes from
 		// the repeat rule (the design's section 12), and the whole list stops at `MAX_SERIES`.
diff --git a/includes/modules/class-wpcpm-mentor-calls.php b/includes/modules/class-wpcpm-mentor-calls.php
index 42c5abc..31f41d5 100644
--- a/includes/modules/class-wpcpm-mentor-calls.php
+++ b/includes/modules/class-wpcpm-mentor-calls.php
@@ -1268,6 +1268,7 @@ class WPCPM_Mentor_Calls {
 			/* translators: %s: a date. */
 			'series-clash'        => array( 'error', __( 'Something else of yours already starts on %s at that time.', 'wpcredits-program-manager' ) ),
 			'series-many'         => array( 'error', __( 'A series holds sixteen sessions at most; plan the rest in a second go.', 'wpcredits-program-manager' ) ),
+			'series-count'        => array( 'error', __( 'Say how many sessions the repeat should make, from 2 to 16.', 'wpcredits-program-manager' ) ),
 			/* translators: %d: how many sessions the student is on. */
 			'series-joined'       => array( 'success', __( 'You are on all %d sessions. They are in your list above, and one email holds the ones you joined just now for your calendar.', 'wpcredits-program-manager' ) ),
 			/* translators: 1: sessions the student is on, 2: sessions in the series, 3: sessions that were full. */
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-handlers.php`

Expected: `php bin/test-handlers.php` ends `ALL HANDLERS REACHED A NORMAL OUTCOME`.

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
git add assets/css/calendar.css bin/test-handlers.php includes/modules/class-wpcpm-group-sessions.php includes/modules/class-wpcpm-mentor-calls.php
git commit -m "Group sessions: the planning form's repeat rule and its count"
```

---

### Task 3: The mentor guide on the repeat rule

**Files:**
- Modify: `docs/sections/21-mentor-availability.md` (**Repeat** in the planning list, **More dates** reworded for the odd dates)
- Modify: `docs/mentors.md`, `docs/administrators.md`, `docs/build/mentors.html`, `docs/build/administrators.html` (rebuilt by `bin/build-docs.php`)

**Interfaces:**
- Consumes: the form's words as Task 2 leaves them.
- Produces: the Repeat bullet and the four generated files. The student guide is unchanged: a series is a series.

The design's section 12 in the guide's own voice: what a mentor picks and what it makes. The diff holds the generated guides too, so the replay is exact and a reviewer reads what will be published; running the build again after applying it changes nothing. The student guide has no section 21 in it, so only the mentor and program manager guides rebuild.

- [ ] **Step 1: Write the bullet, and the guides it rebuilds into**

```diff
diff --git a/docs/administrators.md b/docs/administrators.md
index 5fe3914..7b838d6 100644
--- a/docs/administrators.md
+++ b/docs/administrators.md
@@ -914,8 +914,12 @@ walkthrough, a question hour, a session for everybody starting the same week.
 - **Places** - how many students may join, between 2 and 50.
 - **What it is about**. Your students read this beside the session, so it is how they decide
   whether it is for them.
-- **More dates**, for a series: up to eight further dates with the same time, length, places and
-  topic. Leave the ones you do not need empty.
+- **Repeat**, for a regular series: every week, every two weeks, every four weeks or every month,
+  and how many sessions in all, counting the first date, up to sixteen. Every month keeps the
+  weekday and its place in the month, so a second Tuesday stays a second Tuesday. The rule fills
+  the dates after the first one.
+- **More dates**, for the odd dates of a series: up to eight further dates with the same time,
+  length, places and topic. Leave the ones you do not need empty.
 
 A session is not carved out of your weekly hours; you pick any time, including one you would never
 offer for private calls. It does **block that time from one-to-one booking**, so nobody books you
diff --git a/docs/build/administrators.html b/docs/build/administrators.html
index 4cff00e..ccadcb7 100644
--- a/docs/build/administrators.html
+++ b/docs/build/administrators.html
@@ -1054,7 +1054,10 @@
 <li><strong>What it is about</strong>. Your students read this beside the session, so it is how they decide whether it is for them.</li>
 <!-- /wp:list-item -->
 <!-- wp:list-item -->
-<li><strong>More dates</strong>, for a series: up to eight further dates with the same time, length, places and topic. Leave the ones you do not need empty.</li>
+<li><strong>Repeat</strong>, for a regular series: every week, every two weeks, every four weeks or every month, and how many sessions in all, counting the first date, up to sixteen. Every month keeps the weekday and its place in the month, so a second Tuesday stays a second Tuesday. The rule fills the dates after the first one.</li>
+<!-- /wp:list-item -->
+<!-- wp:list-item -->
+<li><strong>More dates</strong>, for the odd dates of a series: up to eight further dates with the same time, length, places and topic. Leave the ones you do not need empty.</li>
 <!-- /wp:list-item -->
 </ul>
 <!-- /wp:list -->
diff --git a/docs/build/mentors.html b/docs/build/mentors.html
index f72d03a..5a63749 100644
--- a/docs/build/mentors.html
+++ b/docs/build/mentors.html
@@ -207,7 +207,10 @@
 <li><strong>What it is about</strong>. Your students read this beside the session, so it is how they decide whether it is for them.</li>
 <!-- /wp:list-item -->
 <!-- wp:list-item -->
-<li><strong>More dates</strong>, for a series: up to eight further dates with the same time, length, places and topic. Leave the ones you do not need empty.</li>
+<li><strong>Repeat</strong>, for a regular series: every week, every two weeks, every four weeks or every month, and how many sessions in all, counting the first date, up to sixteen. Every month keeps the weekday and its place in the month, so a second Tuesday stays a second Tuesday. The rule fills the dates after the first one.</li>
+<!-- /wp:list-item -->
+<!-- wp:list-item -->
+<li><strong>More dates</strong>, for the odd dates of a series: up to eight further dates with the same time, length, places and topic. Leave the ones you do not need empty.</li>
 <!-- /wp:list-item -->
 </ul>
 <!-- /wp:list -->
diff --git a/docs/mentors.md b/docs/mentors.md
index d5a0cb9..b41a8bd 100644
--- a/docs/mentors.md
+++ b/docs/mentors.md
@@ -132,8 +132,12 @@ walkthrough, a question hour, a session for everybody starting the same week.
 - **Places** - how many students may join, between 2 and 50.
 - **What it is about**. Your students read this beside the session, so it is how they decide
   whether it is for them.
-- **More dates**, for a series: up to eight further dates with the same time, length, places and
-  topic. Leave the ones you do not need empty.
+- **Repeat**, for a regular series: every week, every two weeks, every four weeks or every month,
+  and how many sessions in all, counting the first date, up to sixteen. Every month keeps the
+  weekday and its place in the month, so a second Tuesday stays a second Tuesday. The rule fills
+  the dates after the first one.
+- **More dates**, for the odd dates of a series: up to eight further dates with the same time,
+  length, places and topic. Leave the ones you do not need empty.
 
 A session is not carved out of your weekly hours; you pick any time, including one you would never
 offer for private calls. It does **block that time from one-to-one booking**, so nobody books you
diff --git a/docs/sections/21-mentor-availability.md b/docs/sections/21-mentor-availability.md
index 67ed8f8..ca9abc6 100644
--- a/docs/sections/21-mentor-availability.md
+++ b/docs/sections/21-mentor-availability.md
@@ -47,8 +47,12 @@ walkthrough, a question hour, a session for everybody starting the same week.
 - **Places** - how many students may join, between 2 and 50.
 - **What it is about**. Your students read this beside the session, so it is how they decide
   whether it is for them.
-- **More dates**, for a series: up to eight further dates with the same time, length, places and
-  topic. Leave the ones you do not need empty.
+- **Repeat**, for a regular series: every week, every two weeks, every four weeks or every month,
+  and how many sessions in all, counting the first date, up to sixteen. Every month keeps the
+  weekday and its place in the month, so a second Tuesday stays a second Tuesday. The rule fills
+  the dates after the first one.
+- **More dates**, for the odd dates of a series: up to eight further dates with the same time,
+  length, places and topic. Leave the ones you do not need empty.
 
 A session is not carved out of your weekly hours; you pick any time, including one you would never
 offer for private calls. It does **block that time from one-to-one booking**, so nobody books you
```

- [ ] **Step 2: Rebuild, and confirm the build changes nothing**

Run: `php bin/build-docs.php && git status --short`

Expected: the build reports the four guides, and the status names the same five files the diff changed and nothing else.

- [ ] **Step 3: Run the checks that read the guides**

Run: `php bin/check-spelling.php | tail -1 && php bin/test-handbook.php | tail -1`

Expected: a line ending `US English throughout.`, then `ALL PASS`.

- [ ] **Step 4: Read the built pages once**

Run: `grep -c "every four weeks" docs/build/students.html docs/build/mentors.html docs/build/administrators.html`

Expected: `0`, `1` and `1`.

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
git add docs/administrators.md docs/build/administrators.html docs/build/mentors.html docs/mentors.md docs/sections/21-mentor-availability.md
git commit -m "Group sessions: the mentor guide on the repeat rule"
```

---

### Task 4: Release 1.109.0

**Files:**
- Modify: `wpcredits-program-manager.php` (the `Version:` header and `WPCPM_VERSION`), `readme.txt` (`Stable tag:` and a changelog entry), `languages/wpcredits-program-manager.pot` (regenerated)

- [ ] **Step 1: Move the version.** `1.108.1` becomes `1.109.0` in the plugin header's `Version:` line, in `define( 'WPCPM_VERSION', ... )` and in `readme.txt`'s `Stable tag:`. Every other mention of 1.108.1 stays, including the changelog's own heading.

- [ ] **Step 2: Write the changelog entry**, first under `== Changelog ==` in `readme.txt`, above the previous entry and with one empty line after it:

```text
= 1.109.0 =

* A repeat rule on the planning form: every week, every two weeks, every four weeks or every month, with how many sessions in all, fills the dates of a series after the first one, up to sixteen in one go. Every month keeps the weekday and its place in the month, so a second Tuesday stays a second Tuesday. The dates go through the same rules as a list typed in by hand, all or nothing, and the boxes for odd dates stay.
```

- [ ] **Step 3: Regenerate the translation template.** `sh bin/make-pot.sh`. Expected: `Success: POT file successfully generated.` and a header reading `Project-Id-Version: WPCredits Program Manager 1.109.0`. (WP-CLI prints deprecation notices from its own libraries first; they are not the plugin's.)

- [ ] **Step 4: Run everything.** Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 5: Build the zip and read it back.**

```bash
bash bin/build
unzip -p ../wpcredits-program-manager.zip wpcredits-program-manager/wpcredits-program-manager.php | grep "Version:"
unzip -Z1 ../wpcredits-program-manager.zip | grep -cE '^wpcredits-program-manager/(bin|docs)/'
```

Expected: `Version:           1.109.0` and `0`.

- [ ] **Step 6: Commit.**

```bash
git add wpcredits-program-manager.php readme.txt languages/wpcredits-program-manager.pot
git commit -m "Group sessions: 1.109.0"
```

- [ ] **Step 7: Merge and mirror, on the product owner's choice.** Merge `session-repeat` into `main` and run the battery on the result. Then push the source to the public mirror: pull the `WordPress/WPCredits` clone at `~/GitHub/Plugins/WPCredits-Tracker-mirror` (branch `trunk`), rsync the plugin into its `Education/WordPress Education Dashboard/wpcredits-program-manager/` with `rsync -a --delete --exclude '.git/' --exclude '.superpowers/' --exclude '.DS_Store' --exclude 'node_modules/' --exclude '*.zip' --exclude '.*.swp'`, update the version in that folder's `README.md` table, scan the added lines for keys, record IDs, email addresses and names, read `git diff --stat`, then commit and push as two separate steps; if the push is rejected because trunk moved, fetch, rebase the one commit and push again. This plan travels with the source; it holds no Liquid tag (a brace followed by a percent sign), which a Jekyll build of the mirror would fail on.

- [ ] **Step 8: Deploy only on the product owner's yes.** Ask first. On a yes, follow the deploy recorded for `wordpresseducation.org`: stream the zip over `ssh wpcredits-dashboard`, check its md5 on arrival, install it as a step of its own, read the version back, and purge the edge cache with `echo y | ssh wpcredits-dashboard 'wp edge-cache purge --domain --yes'`. Before the install and after it, run the read-only probe of what a person sees, with version strings and relative times normalized; the two runs must be identical, since nothing on those pages changes in this release.

- [ ] **Step 9: Republish the two guide pages that changed, in the same deploy.** The mentor guide changed, and the program manager guide holds it; the student guide did not. Build the block markup with the uploads base, `php bin/build-docs.php --base=https://wordpresseducation.org/wp-content/uploads/2026/08`, copy `docs/build/mentors.html` and `administrators.html` aside, and run `php bin/build-docs.php` again so the repository keeps its relative image paths and `git status` stays clean. On the site, for pages 559 and 560: back up `post_content` to a file, stream the built copy over ssh stdin, update the page through `wp_update_post()` with `wp_slash()` as the owner's own administrator account (`wp eval-file - <id> <file>`), check that the stored byte count equals the file's, that `_wpcpm_access_level` survived, and that no `<img src="` lacks a scheme; then purge once.

**The live site needs nothing new to take this release.** A rule makes dates; the sessions it makes are the posts the site already keeps; nothing is created in Airtable; a mentor sees the Repeat row the next time they open the planning form.

---

## What this plan leaves out, by the spec's decisions

- **An end date for a rule** (decision 7): a count, not "until".
- **More than sixteen sessions in one go** (decision 8): a longer series is planned in two goes.
- **A true recurring event in the calendar file** (decision 4): each date stays a session of its own, with its own entry.
- **Leave the series, Cancel the remaining sessions, RSVP** (the series design's section 11).

## What this plan parks, with its reasons

- **The theme's rule for the `<select>`** the Repeat row adds: the theme dresses the session form's inputs and textareas (`.wpc-dashboard-page .wpcpm-sessions__form input, ... textarea`) but not a select, so the Repeat select renders in the browser's own style until the theme's next release adds `.wpc-dashboard-page .wpcpm-sessions__form select` to that rule. The controller makes that release (1.24.5) after this merge, as 1.24.4 dressed the "More dates" legend; nothing in this plan touches the theme.
- **The dates a rule makes are not shown before they are created.** The notice says how many were planned and the panel lists every one, and a wrong one is canceled from its row; a preview step would be a form of its own.
- **"Every month" from a 29th, 30th or 31st** reads as the last such weekday of every following month (decision 9), which in a month with five of them is the fifth: the mentor picked the last one of the month, and the last one is what they get.
