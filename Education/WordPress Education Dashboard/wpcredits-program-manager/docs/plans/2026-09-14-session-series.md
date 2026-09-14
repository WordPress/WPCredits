# Session Series Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A series of group sessions, planned at once from a list of dates and joined at once with one email and one calendar file, as release 1.108.0.

**Architecture:** A series is one meta on ordinary session posts, so every per-session flow stays as it is. The group sessions module gains the tag, two readers, a grouping helper and the planning rules as pure functions (`plan_dates()`, `first_clash()`, `create_sessions()`) that the planning handler calls on the first date and the more dates; the outcome flash learns to carry arguments, so a refusal names its date and a join its counts; both lists group a series under a heading; `WPCPM_ICS::build_many()` writes one calendar file holding one event per session from the same lines `build()` writes one from; `WPCPM_Mentor_Calls::notify_joined_series()` sends the two messages; `handle_join_series()` takes every session a student may still take under the booking lock, through `joinable()`, which the Join all form reads too.

**Tech Stack:** WordPress 6.5 and PHP 7.4 as floors, WordPress coding standards, the plugin's standalone `bin/test-*.php` suites, `bin/build-docs.php` for the guides, no build step, no new script: the plugin's own `data-wpcpm-once`, `data-wpcpm-busy` and `data-wpcpm-confirm` attributes do what the existing forms' do.

**Spec:** `docs/specs/2026-09-14-session-series-design.md` at `a58f763`: the six decisions of 14 September 2026 in section 1; 2 for what the code does; 3 for the tag and its readers; 4 for planning and its messages; 5 for the student's list and Join all; 6 for the file and the messages; 7 for the mentor's panel; 8 for the tests; 9 for privacy; 10 for the guides and the release; 11 for what is out of scope.

## Global Constraints

- **Start from `main` at 1.107.1 with the series spec.** `grep "^Stable tag" readme.txt` prints `Stable tag: 1.107.1`, `git log --oneline -1 -- docs/specs/2026-09-14-session-series-design.md` names `a58f763`, and `git status --short` prints nothing. Then `git switch -c session-series`. Every block below was proven one commit at a time on `a58f763` and replayed onto a fresh checkout.
- WordPress coding standards throughout: tabs, Yoda conditions, spaces inside parentheses, `array()` and never `[]`, strict `in_array()`. Everything that ships is PHP 7.4 compatible. Every string a person reads is US English. No em dash or en dash anywhere, in code, comments, docs or commit messages: a plain hyphen. Full product names ("Student Report Card", "Mentor Report Card", "Administrator Dashboard").
- Everything a person can see is escaped on output, and every handler checks capability first, then nonce (the join handlers check the nonce and then that the person is logged in and the sessions are their own mentor's, as `handle_join()` does).
- **The battery stays silent after every task:** `for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done` prints nothing. `php bin/check-references.php`, `php bin/check-spelling.php` and `php bin/check-dead-annotations.php` report clean. `bash bin/check-standards.sh` prints no line containing ` ERROR `, and its last line reads `85 warnings, no errors.` or a lower count. What tripped it while this plan was proven: a multi-item array with keys on one line is refused, so a returned array goes on several lines with its arrows aligned and its closer under the `return`; an `_n()` whose singular lacks a placeholder the plural has is refused, so a counted phrase and a date span are two strings; a stand-alone `$i++` is refused, `++$i` is not.
- **Test first, every task.** Add the checks, run them, see them fail as the step says, then write the code. A handler is exercised in `bin/test-handlers.php`, which runs it and reads the flash it left from the raw `wpcpm_flash` user meta (`flashed()`), since `WPCPM_Flash::take()` memoizes per request; a rule that can be pure is tested pure in `bin/test-group-sessions.php`.
- **A suite drives the real class wherever the rule lives in one.** `bin/test-group-sessions.php` loads the real `WPCPM_Mentor_Calls` and `WPCPM_Group_Sessions` and stands in WordPress with a faithful post model: posts queried by type, status, exclusion and meta clauses, a status on insert, repeated meta rows. `bin/test-handlers.php` loads the plugin's classes as the loader does and answers queries from `$GLOBALS['query_result']`, honoring `fields => ids`. `bin/test-mail.php` loads the real `WPCPM_ICS`, `WPCPM_Mail` and `WPCPM_Mentor_Calls` and records what `wp_mail()` is handed, including whether each attachment exists at send time.
- **Nothing about a single session changes.** A session planned alone carries no series meta and reads as it did; joining, leaving, moving, cancelling, the reminder sweep, the diary and the blocking of one-to-one booking are untouched. The per-student limit counts one-to-one calls alone (1.107.1), which a series needs.
- **Nothing is deleted in Airtable, ever.** Nothing in this plan touches Airtable at all; a session is a WordPress post.
- Version numbers move in Task 7 only: plugin 1.108.0, which `version_compare()` orders after 1.107.1. No theme release.
- Comments explain why and name the decision or the review that made the rule. Every commit message starts with "Group sessions:" and ends with the `Co-Authored-By:` trailer of whoever made it, after a blank line.

## What this plan decides

1. **The flash carries arguments.** A bounce that has something to name sets `array( $status, ...$args )` on the `call` channel; `WPCPM_Mentor_Calls::status()` answers the flag from a string or an array, `args()` the rest sanitized, and `message( $status, $args )` formats a sentence that holds a placeholder with `vsprintf()`. A flag with nothing to name is stored as the string it always was.
2. **A series is read as calls carrying the tag** (the design's section 3), through a public `WPCPM_Mentor_Calls::having( $key, $value, $upcoming )` on the calls query, so a canceled first session does not lose the rest their series and a canceled member drops out by itself.
3. **The planning rules are pure.** `plan_dates()` refuses more than nine before reading any, then walks the list in order refusing the first date that is not a date, has passed or is given twice, and answers the starts sorted; `first_clash()` reads one diary query for the whole list; `create_sessions()` unmakes what it made when an insert fails, so all or nothing holds for the insert too. The handler maps a refusal to the old single-date flag when the form carried one date and to the series flag naming the date when it carried more.
4. **The series heading is a counted phrase plus a date span**, two strings rather than one `_n()`, since the gate refuses a singular that lacks a placeholder the plural has, and "1 session, on <date>" is what a series down to one reads.
5. **`grouped()` places a series where its first session sits** in a date-ordered list, and the row under a series heading leaves its topic to the heading.
6. **`build_many()` shares `event()` with `build()`.** The single invitation is unchanged byte for byte; the series file opens once, holds one event per session with `SEQUENCE:0`, and passes through its own filter, `wpcpm_series_ics`.
7. **The series file is `mentor-sessions.ics`**, its events carry the single invitation's summary ("Mentor call: <mentor> and <student>") so a session's entry reads the same however it arrived, and its description names the series.
8. **`joinable()` is read twice**: once to decide whether Join all is drawn, and again under the booking lock before anything is taken, since a place can go between the page and the press.
9. **The suites' stand-ins grow where the rules needed them**: the group sessions suite's `get_posts()` filters and orders posts as WordPress does for the shapes the calls module asks, its inserted posts keep their status, and `wp_delete_post()` and `delete_user_meta()` exist; the handlers suite's `get_posts()` honors `fields => ids`; the mail suite gains posts and their meta.

## File structure

**Created**

None.

**Modified**

| File | Why |
| --- | --- |
| `includes/modules/class-wpcpm-group-sessions.php` | `META_SERIES`, `series_of()`, `series_members()`, `grouped()`; `plan_dates()`, `first_clash()`, `create_sessions()`, `refuse_dates()` and the planning handler on a list; the form's more dates; `series_heading()`, `render_series()`, the rows' topic; `ACTION_JOIN_SERIES`, `joinable()`, `handle_join_series()`, the Join all form. |
| `includes/modules/class-wpcpm-mentor-calls.php` | `having()`; `status()`, `args()`, `message()` with arguments and the series flags; `bounce()` and `bounce_to()` taking arguments; `notify_joined_series()`, `calendar_many()`, `series_mail_body()`. |
| `includes/modules/class-wpcpm-call-calendar.php` | The notice passes the flash's arguments. |
| `includes/class-wpcpm-ics.php` | `opening()`, `event()`, `text_of()` extracted from `build()`; `build_many()`. |
| `assets/css/calendar.css` | The more dates, the series heading and list, the Join all form. |
| `docs/sections/11-student-booking.md`, `docs/sections/21-mentor-availability.md`, the three `docs/*.md` and `docs/build/*.html` guides | The paragraphs on a series. |
| `bin/test-group-sessions.php`, `bin/test-handlers.php`, `bin/test-mail.php` | Each holds its class to the new rules. |
| `wpcredits-program-manager.php`, `readme.txt`, `languages/wpcredits-program-manager.pot` | The release. |

---

### Task 1: The series tag, its readers, and outcomes that carry their detail

**Files:**
- Modify: `includes/modules/class-wpcpm-group-sessions.php` (`META_SERIES`, `series_of()`, `series_members()`, `grouped()`; `bounce()` takes arguments)
- Modify: `includes/modules/class-wpcpm-mentor-calls.php` (`having()`; `status()`, `args()`, `message()` with arguments and the series flags; `bounce()` and `bounce_to()` take arguments)
- Modify: `includes/modules/class-wpcpm-call-calendar.php` (the notice passes the arguments)
- Test: `bin/test-group-sessions.php`

**Interfaces:**
- Consumes: the calls query `WPCPM_Mentor_Calls::query()` as it stands (private; `having()` is its new public entry), `capacity()`, `WPCPM_Flash::set()` and `take()`.
- Produces: `WPCPM_Group_Sessions::META_SERIES` (`_wpcpm_session_series`, the first session's post ID on every session of a series); `series_of( $call_id )`, the series ID or 0; `series_members( $series_id, $upcoming = true )`, the series' sessions soonest first, empty for 0 or an unknown ID; `grouped( array $sessions )`, a list of `array( 'series' => int, 'sessions' => WP_Post[] )` in the order the sessions appear, a series together under its ID at its first session's place, a lone session a group of one with series 0. `WPCPM_Mentor_Calls::having( $key, $value, $upcoming = true )`, the calls whose meta `$key` equals the integer `$value`, soonest first. `WPCPM_Mentor_Calls::status()` reads the flag from a string flash or the first element of an array flash; `args()` the rest of an array flash as sanitized strings, `array()` otherwise; `message( $status, array $args = array() )` formats a sentence holding a placeholder with `vsprintf()`, and knows the new flags `series-planned` (%d), `series-past` (%s), `series-twice` (%s), `series-clash` (%s), `series-many`, `series-joined` (%d), `series-joined-some` (%1$d of %2$d, %3$d full) and `series-nothing`. `bounce( $status, array $args = array() )` in both classes and `bounce_to( $status, array $args = array() )` store `array( $status, ...$args )` when there are arguments, the bare string otherwise. The calendar's notice calls `message( status(), args() )`.

The design's decision 4 in data: a series is one meta on the session posts that exist today, so nothing about a single session changes, and a session planned alone carries no tag. The members are read as calls carrying the tag, through a public entry on the calls query, so a canceled session drops out by itself and a canceled first session does not lose the rest their series (what this plan decides, 2). `grouped()` is the one rule both lists will draw from in Task 3.

The second half is the flash. A refused list has to name its date and a series join its counts (the design's sections 4 and 5), and the `call` channel carried a bare flag. It now carries the flag and its arguments when there are any, and `message()` formats the sentence; every flag without arguments is stored and read exactly as before (what this plan decides, 1). The new flags are defined here with their sentences, so Tasks 2 and 5 only have to bounce them.

The group sessions suite stands WordPress in with a post model that was too thin for readers that are queries: its `get_posts()` answered nothing. It now filters and orders posts by type, status, exclusion and the meta clauses the calls module writes, an inserted post keeps its status (a session is inserted private and looked up by that), and `wp_delete_post()` and `delete_user_meta()` exist, since a canceled member and a taken flash are what the checks exercise.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-group-sessions.php b/bin/test-group-sessions.php
index efccb1a..b6595d5 100644
--- a/bin/test-group-sessions.php
+++ b/bin/test-group-sessions.php
@@ -79,9 +79,52 @@ require_once __DIR__ . '/stubs/caps.php';
 function get_user_by( $f, $v ) { return new WP_User( (int) $v, 'User ' . (int) $v ); }
 function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
 function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
+function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
 function get_post( $id = null ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
 function get_post_time( $f, $gmt = false, $post = null ) { return time() - DAY_IN_SECONDS; }
-function get_posts( $a = array() ) { return array(); }
+function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }
+/**
+ * Posts as WordPress would query them, for the shapes the calls module asks: a post type, a status,
+ * an exclusion, meta clauses that are equal, at least or between, and an order by a numeric meta.
+ * Faithful because the series readers are queries, and a stub that answered nothing would prove
+ * nothing about them.
+ */
+function get_posts( $a = array() ) {
+	$out = array();
+
+	foreach ( $GLOBALS['posts'] as $post ) {
+		if ( isset( $a['post_type'] ) && $post->post_type !== $a['post_type'] ) { continue; }
+		if ( isset( $a['post_status'] ) && $post->post_status !== $a['post_status'] ) { continue; }
+		if ( isset( $a['exclude'] ) && in_array( $post->ID, array_map( 'intval', (array) $a['exclude'] ), true ) ) { continue; }
+
+		$ok = true;
+
+		foreach ( isset( $a['meta_query'] ) ? $a['meta_query'] : array() as $k => $clause ) {
+			if ( 'relation' === $k ) { continue; }
+			$value   = get_post_meta( $post->ID, $clause['key'], true );
+			$compare = isset( $clause['compare'] ) ? $clause['compare'] : '=';
+			if ( 'BETWEEN' === $compare ) { $ok = (int) $value >= (int) $clause['value'][0] && (int) $value <= (int) $clause['value'][1]; }
+			elseif ( '>=' === $compare ) { $ok = (int) $value >= (int) $clause['value']; }
+			else { $ok = (string) $value === (string) $clause['value']; }
+			if ( ! $ok ) { break; }
+		}
+
+		if ( $ok ) { $out[] = $post; }
+	}
+
+	if ( isset( $a['meta_key'] ) ) {
+		$key  = $a['meta_key'];
+		$desc = isset( $a['order'] ) && 'DESC' === $a['order'];
+		usort( $out, function ( $x, $y ) use ( $key, $desc ) {
+			$d = (int) get_post_meta( $x->ID, $key, true ) - (int) get_post_meta( $y->ID, $key, true );
+			return $desc ? -$d : $d;
+		} );
+	}
+
+	if ( isset( $a['fields'] ) && 'ids' === $a['fields'] ) { return array_map( function ( $p ) { return $p->ID; }, $out ); }
+
+	return $out;
+}
 function wp_insert_post( $a, $error = false ) {
 	static $next = 500;
 	$post               = new WP_Post();
@@ -89,6 +132,7 @@ function wp_insert_post( $a, $error = false ) {
 	$post->post_title   = $a['post_title'] ?? '';
 	$post->post_content = $a['post_content'] ?? '';
 	$post->post_type    = $a['post_type'] ?? 'post';
+	$post->post_status  = $a['post_status'] ?? 'publish';
 	$GLOBALS['posts'][ $post->ID ] = $post;
 	return $post->ID;
 }
@@ -184,7 +228,7 @@ function ck( $label, $got, $want ) {
  * @return int Post ID.
  */
 function make_call( $capacity = 1 ) {
-	$id = wp_insert_post( array( 'post_type' => WPCPM_Mentor_Calls::POST_TYPE, 'post_title' => 'Call' ) );
+	$id = wp_insert_post( array( 'post_type' => WPCPM_Mentor_Calls::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Call' ) );
 
 	update_post_meta( $id, WPCPM_Mentor_Calls::META_START, time() + DAY_IN_SECONDS );
 	update_post_meta( $id, WPCPM_Mentor_Calls::META_END, time() + DAY_IN_SECONDS + 1800 );
@@ -398,6 +442,58 @@ ck( 'and skips whoever moved it, who already knows',
     false !== strpos( $calls, '(int) $student->ID === (int) $actor' ), true );
 
 
+echo "\n=== A series is a tag on ordinary sessions (1.108.0) ===\n";
+
+// Three sessions planned together, a week apart, and a lone one between them.
+$first  = make_call( 4 );
+$second = make_call( 4 );
+$third  = make_call( 4 );
+$alone  = make_call( 4 );
+update_post_meta( $second, WPCPM_Mentor_Calls::META_START, time() + 8 * DAY_IN_SECONDS );
+update_post_meta( $third, WPCPM_Mentor_Calls::META_START, time() + 15 * DAY_IN_SECONDS );
+update_post_meta( $alone, WPCPM_Mentor_Calls::META_START, time() + 3 * DAY_IN_SECONDS );
+foreach ( array( $first, $second, $third ) as $member ) {
+	update_post_meta( $member, WPCPM_Group_Sessions::META_SERIES, $first );
+}
+
+ck( 'a member names its series by the first session, and a lone session names none',
+    array( WPCPM_Group_Sessions::series_of( $first ), WPCPM_Group_Sessions::series_of( $third ), WPCPM_Group_Sessions::series_of( $alone ) ),
+    array( $first, $first, 0 ) );
+
+ck( 'the members of a series come soonest first, the lone session left out',
+    array_map( function ( $p ) { return $p->ID; }, WPCPM_Group_Sessions::series_members( $first ) ),
+    array( $first, $second, $third ) );
+
+ck( 'a series that is not one answers nothing',
+    array( WPCPM_Group_Sessions::series_members( 0 ), WPCPM_Group_Sessions::series_members( 424242 ) ),
+    array( array(), array() ) );
+
+wp_delete_post( $second, true );
+
+ck( 'a canceled member drops out by itself, since it is no longer a call post',
+    array_map( function ( $p ) { return $p->ID; }, WPCPM_Group_Sessions::series_members( $first ) ),
+    array( $first, $third ) );
+
+$mentor_sessions = WPCPM_Group_Sessions::for_mentor( 20 );
+$groups          = WPCPM_Group_Sessions::grouped( $mentor_sessions );
+
+ck( 'the lists group a series under its first upcoming session, in date order, a lone session on its own',
+    array_map( function ( $g ) { return array( $g['series'], array_map( function ( $p ) { return $p->ID; }, $g['sessions'] ) ); }, $groups ),
+    array( array( 0, array( $group ) ), array( $first, array( $first, $third ) ), array( 0, array( $alone ) ) ) );
+
+echo "\n=== An outcome that carries its detail (1.108.0) ===\n";
+
+$GLOBALS['uid'] = 31;
+WPCPM_Flash::set( 'call', array( 'series-past', '2026-10-06' ) );
+
+ck( 'the flag and its detail are read apart, and the sentence names the detail',
+    array( WPCPM_Mentor_Calls::status(), WPCPM_Mentor_Calls::args(), WPCPM_Mentor_Calls::message( 'series-past', array( '2026-10-06' ) ) ),
+    array( 'series-past', array( '2026-10-06' ), array( 'error', 'One of the dates has passed: 2026-10-06.' ) ) );
+
+ck( 'a flag with no detail reads as it always did',
+    array( WPCPM_Mentor_Calls::message( 'session-full' ), WPCPM_Mentor_Calls::message( 'series-joined-some', array( 5, 6, 1 ) ), WPCPM_Mentor_Calls::message( 'nonsense' ) ),
+    array( array( 'error', 'That session filled up while you were reading it.' ), array( 'success', 'You are on 5 of the 6 sessions; 1 had no place left.' ), array() ) );
+
 printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
 
 exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-group-sessions.php`

Expected: `bin/test-group-sessions.php` stops with `Fatal error: Uncaught Error: Undefined constant WPCPM_Group_Sessions::META_SERIES`. `META_SERIES` does not exist yet.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/modules/class-wpcpm-call-calendar.php b/includes/modules/class-wpcpm-call-calendar.php
index 9106e10..177f16d 100644
--- a/includes/modules/class-wpcpm-call-calendar.php
+++ b/includes/modules/class-wpcpm-call-calendar.php
@@ -882,7 +882,7 @@ class WPCPM_Call_Calendar {
 	 * Render the outcome of a booking or cancellation, if there was one.
 	 */
 	private static function render_message() {
-		$message = WPCPM_Mentor_Calls::message( WPCPM_Mentor_Calls::status() );
+		$message = WPCPM_Mentor_Calls::message( WPCPM_Mentor_Calls::status(), WPCPM_Mentor_Calls::args() );
 
 		if ( empty( $message ) ) {
 			return;
diff --git a/includes/modules/class-wpcpm-group-sessions.php b/includes/modules/class-wpcpm-group-sessions.php
index e2c85fc..14964c4 100644
--- a/includes/modules/class-wpcpm-group-sessions.php
+++ b/includes/modules/class-wpcpm-group-sessions.php
@@ -41,6 +41,16 @@ class WPCPM_Group_Sessions {
 	const ACTION_NOTE   = 'wpcpm_session_note';
 	const ACTION_EDIT   = 'wpcpm_edit_session';
 
+	/**
+	 * The series a session was planned in: the post ID of the series' first session, on every one
+	 * of them, the first included.
+	 *
+	 * A series is a tag on ordinary sessions (the design's decision 4 of 14 September 2026): nothing
+	 * else about a session changes, so the diary, the reminders, the blocking and the calendar
+	 * files keep working per session, and a session planned alone carries no tag at all.
+	 */
+	const META_SERIES = '_wpcpm_session_series';
+
 	/**
 	 * How many times a session has been changed since it was announced.
 	 *
@@ -141,6 +151,80 @@ class WPCPM_Group_Sessions {
 		return in_array( (int) $student_id, WPCPM_Mentor_Calls::attendees( $call_id ), true );
 	}
 
+	/**
+	 * The series a session belongs to, or 0 for one planned alone.
+	 *
+	 * @param int $call_id Session post ID.
+	 * @return int The series' first session's post ID.
+	 */
+	public static function series_of( $call_id ) {
+		return (int) get_post_meta( (int) $call_id, self::META_SERIES, true );
+	}
+
+	/**
+	 * The sessions of a series, soonest first.
+	 *
+	 * Read as calls carrying the series tag rather than from a list kept anywhere, so a canceled
+	 * session, which is no longer a call post, drops out by itself, and the first session being
+	 * canceled does not lose the rest their series.
+	 *
+	 * @param int  $series_id The series: its first session's post ID.
+	 * @param bool $upcoming  Only sessions that have not started.
+	 * @return WP_Post[]
+	 */
+	public static function series_members( $series_id, $upcoming = true ) {
+		$series_id = (int) $series_id;
+
+		if ( $series_id <= 0 ) {
+			return array();
+		}
+
+		$out = array();
+
+		foreach ( WPCPM_Mentor_Calls::having( self::META_SERIES, $series_id, $upcoming ) as $call ) {
+			if ( WPCPM_Mentor_Calls::capacity( $call->ID ) > 1 ) {
+				$out[] = $call;
+			}
+		}
+
+		return $out;
+	}
+
+	/**
+	 * Sessions in the order given, the members of one series together under its ID.
+	 *
+	 * The lists draw a series under one heading (the design's sections 5 and 7); a lone session is
+	 * a group of one with no series. A series sits where its first session in the list sits, which
+	 * in a date-ordered list is its next upcoming session.
+	 *
+	 * @param WP_Post[] $sessions Sessions, soonest first.
+	 * @return array[] Each `series` (an int, 0 for none) and `sessions` (WP_Post[]).
+	 */
+	public static function grouped( array $sessions ) {
+		$groups = array();
+		$where  = array();
+
+		foreach ( $sessions as $session ) {
+			$series = self::series_of( $session->ID );
+
+			if ( $series > 0 && isset( $where[ $series ] ) ) {
+				$groups[ $where[ $series ] ]['sessions'][] = $session;
+				continue;
+			}
+
+			$groups[] = array(
+				'series'   => $series,
+				'sessions' => array( $session ),
+			);
+
+			if ( $series > 0 ) {
+				$where[ $series ] = count( $groups ) - 1;
+			}
+		}
+
+		return $groups;
+	}
+
 	/*
 	 * Creating
 	 * --------------------------------------------------------------------
@@ -1103,8 +1187,9 @@ class WPCPM_Group_Sessions {
 	 * so this needs no mentor argument, and an earlier draft's was doing nothing.
 	 *
 	 * @param string $status Message key.
+	 * @param array  $args   The arguments its sentence takes, if any (1.108.0).
 	 */
-	private static function bounce( $status ) {
-		WPCPM_Mentor_Calls::bounce_to( $status );
+	private static function bounce( $status, array $args = array() ) {
+		WPCPM_Mentor_Calls::bounce_to( $status, $args );
 	}
 }
diff --git a/includes/modules/class-wpcpm-mentor-calls.php b/includes/modules/class-wpcpm-mentor-calls.php
index 38880a6..fbca0f4 100644
--- a/includes/modules/class-wpcpm-mentor-calls.php
+++ b/includes/modules/class-wpcpm-mentor-calls.php
@@ -470,6 +470,27 @@ class WPCPM_Mentor_Calls {
 		);
 	}
 
+	/**
+	 * The calls carrying one meta value, soonest first: how a session series is read (1.108.0).
+	 *
+	 * @param string $key      Meta key.
+	 * @param int    $value    The value, a post ID.
+	 * @param bool   $upcoming Only calls that have not started.
+	 * @return WP_Post[]
+	 */
+	public static function having( $key, $value, $upcoming = true ) {
+		return self::query(
+			array(
+				array(
+					'key'   => (string) $key,
+					'value' => (int) $value,
+					'type'  => 'NUMERIC',
+				),
+			),
+			$upcoming
+		);
+	}
+
 	/**
 	 * Calls about one Airtable student record, whatever account booked them.
 	 *
@@ -928,8 +949,9 @@ class WPCPM_Mentor_Calls {
 	 * bounce anyone somewhere else.
 	 *
 	 * @param string $status Outcome flag.
+	 * @param array  $args   The arguments its sentence takes, if any (1.108.0).
 	 */
-	private static function bounce( $status ) {
+	private static function bounce( $status, array $args = array() ) {
 		$student_page = WPCPM_Students_Dashboard::page_url();
 		$mentor_page  = WPCPM_Mentors_Dashboard::page_url();
 
@@ -967,7 +989,7 @@ class WPCPM_Mentor_Calls {
 
 		// The outcome goes in a flash, not the URL. In the URL it survived every reload -
 		// "That call is canceled and the slot is free again" stayed on the page for good.
-		WPCPM_Flash::set( 'call', $status );
+		WPCPM_Flash::set( 'call', array() === $args ? $status : array_merge( array( $status ), array_values( $args ) ) );
 
 		$args = array();
 
@@ -1024,9 +1046,10 @@ class WPCPM_Mentor_Calls {
 	 * Redirect with an outcome message, for another module.
 	 *
 	 * @param string $status Outcome flag.
+	 * @param array  $args   The arguments its sentence takes, if any (1.108.0).
 	 */
-	public static function bounce_to( $status ) {
-		self::bounce( $status );
+	public static function bounce_to( $status, array $args = array() ) {
+		self::bounce( $status, $args );
 	}
 
 	/**
@@ -1196,10 +1219,14 @@ class WPCPM_Mentor_Calls {
 	/**
 	 * The message for an outcome flag, or an empty array.
 	 *
+	 * A flag whose sentence has a placeholder takes its arguments from the flash, so a refusal
+	 * can name the date it refused and a series join can say how many it took (1.108.0).
+	 *
 	 * @param string $status Outcome flag.
+	 * @param array  $args   The arguments the sentence's placeholders take, if any.
 	 * @return array{0:string,1:string}|array
 	 */
-	public static function message( $status ) {
+	public static function message( $status, array $args = array() ) {
 		$messages = array(
 			'booked'              => array( 'success', __( 'Your call is booked. It is in the list above, and your mentor can see it too.', 'wpcredits-program-manager' ) ),
 			'cancelled'           => array( 'success', __( 'That call is canceled and the slot is free again.', 'wpcredits-program-manager' ) ),
@@ -1227,9 +1254,53 @@ class WPCPM_Mentor_Calls {
 			'session-shrink'      => array( 'error', __( 'That is fewer places than there are students already on the session. Remove somebody first, or keep the places.', 'wpcredits-program-manager' ) ),
 			'session-noted'       => array( 'success', __( 'Your note is saved, and it is on every card of everybody who was there.', 'wpcredits-program-manager' ) ),
 			'session-note-failed' => array( 'error', __( 'That note could not be saved.', 'wpcredits-program-manager' ) ),
+
+			// A series of sessions (1.108.0).
+			/* translators: %d: how many sessions were planned. */
+			'series-planned'      => array( 'success', __( 'Your %d sessions are planned. Your students can see the series and join it.', 'wpcredits-program-manager' ) ),
+			/* translators: %s: a date. */
+			'series-past'         => array( 'error', __( 'One of the dates has passed: %s.', 'wpcredits-program-manager' ) ),
+			/* translators: %s: a date. */
+			'series-twice'        => array( 'error', __( 'One of the dates is given twice: %s.', 'wpcredits-program-manager' ) ),
+			/* translators: %s: a date. */
+			'series-clash'        => array( 'error', __( 'Something else of yours already starts on %s at that time.', 'wpcredits-program-manager' ) ),
+			'series-many'         => array( 'error', __( 'A series holds nine sessions at most; plan the rest in a second go.', 'wpcredits-program-manager' ) ),
+			/* translators: %d: how many sessions the student is on. */
+			'series-joined'       => array( 'success', __( 'You are on all %d sessions. They are in your list above, and one email holds them all for your calendar.', 'wpcredits-program-manager' ) ),
+			/* translators: 1: sessions the student is on, 2: sessions in the series, 3: sessions that were full. */
+			'series-joined-some'  => array( 'success', __( 'You are on %1$d of the %2$d sessions; %3$d had no place left.', 'wpcredits-program-manager' ) ),
+			'series-nothing'      => array( 'error', __( 'There was nothing to join: you are on every session of the series that has a place.', 'wpcredits-program-manager' ) ),
 		);
 
-		return isset( $messages[ $status ] ) ? $messages[ $status ] : array();
+		if ( ! isset( $messages[ $status ] ) ) {
+			return array();
+		}
+
+		$message = $messages[ $status ];
+
+		if ( array() !== $args && false !== strpos( $message[1], '%' ) ) {
+			$message[1] = vsprintf( $message[1], array_values( $args ) );
+		}
+
+		return $message;
+	}
+
+	/**
+	 * The arguments the outcome flag on the current request carries, if any (1.108.0).
+	 *
+	 * The flash holds either the flag alone or the flag followed by its arguments; `take()`
+	 * memoizes per request, so reading it here after `status()` read it is the same read.
+	 *
+	 * @return string[]
+	 */
+	public static function args() {
+		$taken = WPCPM_Flash::take( 'call' );
+
+		if ( ! is_array( $taken ) ) {
+			return array();
+		}
+
+		return array_map( 'sanitize_text_field', array_map( 'strval', array_slice( array_values( $taken ), 1 ) ) );
 	}
 
 	/**
@@ -1238,7 +1309,13 @@ class WPCPM_Mentor_Calls {
 	 * @return string
 	 */
 	public static function status() {
-		return sanitize_key( (string) WPCPM_Flash::take( 'call' ) );
+		$taken = WPCPM_Flash::take( 'call' );
+
+		if ( is_array( $taken ) ) {
+			$taken = reset( $taken );
+		}
+
+		return sanitize_key( (string) $taken );
 	}
 
 	/*
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-group-sessions.php`

Expected: `bin/test-group-sessions.php` ends `ALL PASS (61 checks)`. 

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
git add bin/test-group-sessions.php includes/modules/class-wpcpm-call-calendar.php includes/modules/class-wpcpm-group-sessions.php includes/modules/class-wpcpm-mentor-calls.php
git commit -m "Group sessions: the series tag, its readers, and outcomes that carry their detail"
```

---

### Task 2: A series planned from a list of dates, all or nothing

**Files:**
- Modify: `includes/modules/class-wpcpm-group-sessions.php` (`MAX_SERIES`; `handle_create()` on a list; `plan_dates()`, `first_clash()`, `create_sessions()`, `refuse_dates()`; the form's more dates)
- Modify: `assets/css/calendar.css` (the more dates)
- Test: `bin/test-group-sessions.php`, `bin/test-handlers.php`

**Interfaces:**
- Consumes: Task 1's `META_SERIES` and `bounce( $status, $args )`; `WPCPM_Mentor_Availability::date_string()`, `time_string()`, `timezone()`; `WPCPM_Mentor_Calls::taken_starts( $mentor_id, $from, $to )`.
- Produces: `WPCPM_Group_Sessions::MAX_SERIES` (9). `plan_dates( array $dates, $time, DateTimeZone $zone, $now )`: `starts` (timestamps soonest first), `refused` (`many` before any date is read, then in list order `twice`, `when`, `past`, or '') and `date` (the date refused, or ''). `first_clash( array $starts, array $taken )`: the first start present as a key of `$taken`, or 0. `create_sessions( $mentor_id, array $starts, $minutes, $capacity, $topic, DateTimeZone $zone )`: the post IDs in date order, each created as a lone session is, the series meta on every one when there is more than one, `array()` after unmaking what it made when an insert fails. `handle_create()` reads `more_dates[]` beside `date` (an empty box passed over, a box holding no date bouncing `session-when`), plans the list, refuses through `refuse_dates()` (the old flags `session-past` and `session-clash` when the form carried one date, `series-past`, `series-twice`, `series-clash` naming the date and `series-many` when more), creates, and bounces `session-created` for one session or `series-planned` with the count. The form gains a fieldset `wpcpm-sessions__more` with eight `<input type="date" name="more_dates[]">` and its hint, and the button reads "Create the sessions".

The design's section 4. The rules are pure and tested pure: `plan_dates()` walks the list once, refusing the first offending date in list order and naming it, after refusing more than nine before reading any, and sorts the starts; the clash is one diary query over the whole span and `first_clash()` names the first start the mentor holds; `create_sessions()` creates each as a lone session is created and tags them all with the first ID, unmaking what it made if an insert fails, so all or nothing holds for the insert too (what this plan decides, 3). The handler is the same handler with the list in place of the one date, and a form that carried one date keeps the words it always had (what this plan decides, 3).

Every date takes the one time on the mentor's clock, so a series keeps its clock time across a change to or from summer time: the suite pins that a week apart across the October change is 169 hours, not 168.

`bin/test-handlers.php` plans a series of three through the handler and reads the flash from the raw queue, then a date given twice, a date that has passed and a clash, each naming its date with nothing created; its `get_posts()` learns to honor `fields => ids`, which `taken_starts()` asks for.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-group-sessions.php b/bin/test-group-sessions.php
index b6595d5..225c7f5 100644
--- a/bin/test-group-sessions.php
+++ b/bin/test-group-sessions.php
@@ -494,6 +494,78 @@ ck( 'a flag with no detail reads as it always did',
     array( WPCPM_Mentor_Calls::message( 'session-full' ), WPCPM_Mentor_Calls::message( 'series-joined-some', array( 5, 6, 1 ) ), WPCPM_Mentor_Calls::message( 'nonsense' ) ),
     array( array( 'error', 'That session filled up while you were reading it.' ), array( 'success', 'You are on 5 of the 6 sessions; 1 had no place left.' ), array() ) );
 
+echo "\n=== Planning a series: the dates, all or nothing (1.108.0) ===\n";
+
+$riga = new DateTimeZone( 'Europe/Riga' );
+$now  = strtotime( '2026-10-01 12:00:00 UTC' );
+
+$three = WPCPM_Group_Sessions::plan_dates( array( '2026-10-13', '2026-10-06', '2026-10-20' ), '18:00', $riga, $now );
+
+ck( 'three dates at one time become three starts, soonest first, on the mentor\'s clock',
+    array( $three['refused'], array_map( function ( $ts ) { return gmdate( 'Y-m-d H:i', $ts ); }, $three['starts'] ) ),
+    array( '', array( '2026-10-06 15:00', '2026-10-13 15:00', '2026-10-20 15:00' ) ) );
+
+$across = WPCPM_Group_Sessions::plan_dates( array( '2026-10-24', '2026-10-31' ), '10:00', $riga, $now );
+
+ck( 'a series keeps its clock time across the change from summer time, so a week apart is 169 hours, not 168',
+    array( $across['refused'], ( $across['starts'][1] - $across['starts'][0] ) / HOUR_IN_SECONDS ),
+    array( '', 169 ) );
+
+ck( 'one date is one start, as a lone session',
+    array( WPCPM_Group_Sessions::plan_dates( array( '2026-10-06' ), '18:00', $riga, $now )['refused'], count( WPCPM_Group_Sessions::plan_dates( array( '2026-10-06' ), '18:00', $riga, $now )['starts'] ) ),
+    array( '', 1 ) );
+
+ck( 'a date that is not a date, a date that has passed and a date given twice each refuse the whole list, naming the date',
+    array(
+        WPCPM_Group_Sessions::plan_dates( array( '2026-10-06', 'not-a-date' ), '18:00', $riga, $now ),
+        WPCPM_Group_Sessions::plan_dates( array( '2026-10-06', '2026-09-29' ), '18:00', $riga, $now ),
+        WPCPM_Group_Sessions::plan_dates( array( '2026-10-06', '2026-10-13', '2026-10-06' ), '18:00', $riga, $now ),
+    ),
+    array(
+        array( 'starts' => array(), 'refused' => 'when', 'date' => 'not-a-date' ),
+        array( 'starts' => array(), 'refused' => 'past', 'date' => '2026-09-29' ),
+        array( 'starts' => array(), 'refused' => 'twice', 'date' => '2026-10-06' ),
+    ) );
+
+$ten = array();
+for ( $i = 1; $i <= 10; ++$i ) {
+	$ten[] = sprintf( '2026-11-%02d', $i );
+}
+
+ck( 'ten dates are refused before any is read: a series holds nine',
+    WPCPM_Group_Sessions::plan_dates( $ten, '18:00', $riga, $now ),
+    array( 'starts' => array(), 'refused' => 'many', 'date' => '' ) );
+
+ck( 'the first start the mentor already holds is the clash, and none is no clash',
+    array(
+        WPCPM_Group_Sessions::first_clash( $three['starts'], array( $three['starts'][1] => true, 12345 => true ) ),
+        WPCPM_Group_Sessions::first_clash( $three['starts'], array( 12345 => true ) ),
+    ),
+    array( $three['starts'][1], 0 ) );
+
+$before  = count( $GLOBALS['posts'] );
+$planned = WPCPM_Group_Sessions::create_sessions( 20, $three['starts'], 60, 5, 'Release cycle', $riga );
+
+ck( 'three sessions are created in date order, each as a session is created alone',
+    array(
+        count( $planned ),
+        count( $GLOBALS['posts'] ) - $before,
+        array_map( function ( $id ) use ( $three ) { return (int) get_post_meta( $id, WPCPM_Mentor_Calls::META_START, true ) === $three['starts'][ array_search( $id, $GLOBALS['planned_ids'] ?? array(), true ) ?: 0 ] ? 'unused' : 'unused'; }, array() ),
+        array_map( function ( $id ) { return (int) get_post_meta( $id, WPCPM_Mentor_Calls::META_START, true ); }, $planned ),
+        array_map( function ( $id ) { return (int) get_post_meta( $id, WPCPM_Mentor_Calls::META_END, true ) - (int) get_post_meta( $id, WPCPM_Mentor_Calls::META_START, true ); }, $planned ),
+        array_unique( array_map( function ( $id ) { return array( (int) get_post_meta( $id, WPCPM_Mentor_Calls::META_MENTOR, true ), WPCPM_Mentor_Calls::capacity( $id ), get_post_meta( $id, WPCPM_Mentor_Calls::META_ZONE, true ), get_post( $id )->post_content, get_post( $id )->post_status ); }, $planned ), SORT_REGULAR ),
+    ),
+    array( 3, 3, array(), $three['starts'], array( 3600, 3600, 3600 ), array( array( 20, 5, 'Europe/Riga', 'Release cycle', 'private' ) ) ) );
+
+ck( 'and every one of them carries the first as its series',
+    array_map( function ( $id ) { return WPCPM_Group_Sessions::series_of( $id ); }, $planned ),
+    array( $planned[0], $planned[0], $planned[0] ) );
+
+$lone = WPCPM_Group_Sessions::create_sessions( 20, array( $three['starts'][0] + 100 ), 60, 5, '', $riga );
+
+ck( 'a session created alone carries no series',
+    array( count( $lone ), WPCPM_Group_Sessions::series_of( $lone[0] ) ), array( 1, 0 ) );
+
 printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
 
 exit( $fails ? 1 : 0 );
diff --git a/bin/test-handlers.php b/bin/test-handlers.php
index 2d7e3ed..e7c9ab7 100644
--- a/bin/test-handlers.php
+++ b/bin/test-handlers.php
@@ -151,7 +151,7 @@ function set_transient( $k, $v, $t = 0 ) { $GLOBALS['trans'][ $k ] = $v; return
 function delete_transient( $k ) { unset( $GLOBALS['trans'][ $k ] ); return true; }
 function register_post_type( $t, $a = array() ) { return true; }
 function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
-function get_posts( $a = array() ) { return $GLOBALS['query_result'] ?? array(); }
+function get_posts( $a = array() ) { $found = $GLOBALS['query_result'] ?? array(); return ( isset( $a['fields'] ) && 'ids' === $a['fields'] ) ? array_map( function ( $p ) { return $p->ID; }, $found ) : $found; }
 function wp_insert_post( $a, $err = false ) {
 	$id = count( $GLOBALS['posts'] ) + 100;
 	$p = new WP_Post();
@@ -510,6 +510,39 @@ $GLOBALS['query_result'] = array( $GLOBALS['posts'][501] );
 check( 'while a one-to-one call still counts', '' !== WPCPM_Mentor_Calls::why_not_bookable( 30 ), true );
 $GLOBALS['query_result'] = array();
 
+// 1.108.0: a series is planned from the first date and the more dates, all or nothing.
+$GLOBALS['uid'] = 20;
+$posts_before   = count( $GLOBALS['posts'] );
+$_POST          = array( 'mentor' => 20, 'date' => '2027-02-02', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'topic' => 'Release cycle', 'more_dates' => array( '2027-02-09', '', '2027-02-16' ) );
+run( 'handle_create (a series of three)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
+check( 'the series is planned and the notice says how many', flashed( 20, 'call' ), array( 'series-planned', 3 ) );
+
+$series = array();
+foreach ( $GLOBALS['posts'] as $post ) {
+	if ( WPCPM_Group_Sessions::series_of( $post->ID ) > 0 ) {
+		$series[] = $post->ID;
+	}
+}
+check( 'three sessions carry the first as their series, a week apart, the empty box ignored',
+    array( count( $series ), count( $GLOBALS['posts'] ) - $posts_before, array_unique( array_map( 'WPCPM_Group_Sessions::series_of', $series ) ), array_map( function ( $id ) { return gmdate( 'Y-m-d H:i', (int) get_post_meta( $id, WPCPM_Mentor_Calls::META_START, true ) ); }, $series ) ),
+    array( 3, 3, array( $series[0] ), array( '2027-02-02 10:00', '2027-02-09 10:00', '2027-02-16 10:00' ) ) );
+
+$posts_before = count( $GLOBALS['posts'] );
+$_POST        = array( 'mentor' => 20, 'date' => '2027-03-02', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'more_dates' => array( '2027-03-09', '2027-03-09' ) );
+run( 'handle_create (a date given twice)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
+$twice = flashed( 20, 'call' );
+$_POST = array( 'mentor' => 20, 'date' => '2027-03-02', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'more_dates' => array( '2020-03-09' ) );
+run( 'handle_create (a date that has passed)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
+$past = flashed( 20, 'call' );
+$GLOBALS['query_result'] = array( $GLOBALS['posts'][ $series[2] ] );
+$_POST                   = array( 'mentor' => 20, 'date' => '2027-03-02', 'time' => '10:00', 'minutes' => 60, 'capacity' => 6, 'more_dates' => array( '2027-02-16' ) );
+run( 'handle_create (a date the mentor already holds)', array( 'WPCPM_Group_Sessions', 'handle_create' ) );
+$clash                   = flashed( 20, 'call' );
+$GLOBALS['query_result'] = array();
+check( 'a date given twice, a date that has passed and a clash each refuse the whole list naming the date, and nothing is created',
+    array( $twice, $past, $clash, count( $GLOBALS['posts'] ) - $posts_before ),
+    array( array( 'series-twice', '2027-03-09' ), array( 'series-past', '2020-03-09' ), array( 'series-clash', '2027-02-16' ), 0 ) );
+
 $GLOBALS['uid'] = 20;
 $_POST          = array( 'session' => 0, 'note' => 'Covered the release cycle.' );
 run( 'handle_note (no such session)', array( 'WPCPM_Group_Sessions', 'handle_note' ) );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-group-sessions.php` and `php bin/test-handlers.php`

Expected: `bin/test-group-sessions.php` stops with `Fatal error: Uncaught Error: Call to undefined method WPCPM_Group_Sessions::plan_dates()`; `bin/test-handlers.php` stops with `FAIL the series is planned and the notice says how many`. `plan_dates()` does not exist yet, and the handlers suite's series plan bounces `session-created` with one session.

- [ ] **Step 3: Write the code**

```diff
diff --git a/assets/css/calendar.css b/assets/css/calendar.css
index 94fd824..408c8e1 100644
--- a/assets/css/calendar.css
+++ b/assets/css/calendar.css
@@ -771,6 +771,27 @@
 	grid-column: 1 / -1;
 }
 
+/* The more dates of a series (1.108.0): eight date boxes across the form's whole width, wrapping
+   on a narrow screen, under one legend, the hint under them all. */
+.wpcpm-sessions__more {
+	border: 0;
+	display: grid;
+	gap: 0.5em;
+	grid-column: 1 / -1;
+	grid-template-columns: repeat( auto-fit, minmax( 9em, 1fr ) );
+	padding: 0;
+}
+
+.wpcpm-sessions__more legend {
+	font-size: 0.9em;
+	font-weight: 600;
+	margin-bottom: 0.2em;
+}
+
+.wpcpm-sessions__more .wpcpm-field__hint {
+	grid-column: 1 / -1;
+}
+
 .wpcpm-field label {
 	display: block;
 	font-size: 0.9em;
diff --git a/includes/modules/class-wpcpm-group-sessions.php b/includes/modules/class-wpcpm-group-sessions.php
index 14964c4..0337079 100644
--- a/includes/modules/class-wpcpm-group-sessions.php
+++ b/includes/modules/class-wpcpm-group-sessions.php
@@ -72,6 +72,14 @@ class WPCPM_Group_Sessions {
 	/** Fewest, because a session for one is a one-to-one call and there is already one of those. */
 	const MIN_CAPACITY = 2;
 
+	/**
+	 * Most sessions one form plans: the first date and eight more (the design's decision 1).
+	 *
+	 * A ceiling like `MAX_CAPACITY`: the dates are boxes on a form, and a longer series is planned
+	 * in a second go rather than becoming a term of sessions nobody meant.
+	 */
+	const MAX_SERIES = 9;
+
 	/** Longest a session may run, in minutes. */
 	const MAX_MINUTES = 480;
 
@@ -267,58 +275,234 @@ class WPCPM_Group_Sessions {
 			self::bounce( 'session-capacity' );
 		}
 
-		// Entered in the mentor's own clock - the one they mean when they say "Tuesday at two" -
-		// and stored as UTC, exactly as the weekly hours are.
-		$zone  = WPCPM_Mentor_Availability::timezone( WPCPM_Mentor_Availability::get( $mentor_id )['timezone'] );
-		$start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time, $zone );
+		// The rest of a series, when the form carries more dates (the design's decision 1): each
+		// read as the first is, an empty box passed over, a box holding no date refusing the form.
+		$dates = array( $date );
 
-		if ( false === $start ) {
-			self::bounce( 'session-when' );
+		if ( isset( $_POST['more_dates'] ) && is_array( $_POST['more_dates'] ) ) {
+			foreach ( wp_unslash( $_POST['more_dates'] ) as $more ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each entry is sanitized and validated below.
+				$more = sanitize_text_field( (string) $more );
+
+				if ( '' === trim( $more ) ) {
+					continue;
+				}
+
+				$more = WPCPM_Mentor_Availability::date_string( $more );
+
+				if ( '' === $more ) {
+					self::bounce( 'session-when' );
+				}
+
+				$dates[] = $more;
+			}
 		}
 
-		$start_ts = $start->getTimestamp();
+		// Entered in the mentor's own clock - the one they mean when they say "Tuesday at two" -
+		// and stored as UTC, exactly as the weekly hours are.
+		$zone    = WPCPM_Mentor_Availability::timezone( WPCPM_Mentor_Availability::get( $mentor_id )['timezone'] );
+		$planned = self::plan_dates( $dates, $time, $zone, time() );
 
-		if ( $start_ts <= time() ) {
-			self::bounce( 'session-past' );
+		if ( '' !== $planned['refused'] ) {
+			self::refuse_dates( $planned['refused'], $planned['date'], count( $dates ) );
 		}
 
 		// Nothing else of this mentor's may start at the same moment, in either direction: a
 		// session over a booked call would double-book the mentor, and two sessions at once is a
-		// mistake rather than a plan.
-		if ( ! empty( WPCPM_Mentor_Calls::taken_starts( $mentor_id, $start_ts, $start_ts ) ) ) {
-			self::bounce( 'session-clash' );
+		// mistake rather than a plan. One read of the diary covers the whole list.
+		$starts = $planned['starts'];
+		$clash  = self::first_clash( $starts, WPCPM_Mentor_Calls::taken_starts( $mentor_id, min( $starts ), max( $starts ) ) );
+
+		if ( $clash > 0 ) {
+			self::refuse_dates( 'clash', wp_date( 'Y-m-d', $clash, $zone ), count( $dates ) );
 		}
 
-		// `private`, like every call: see `WPCPM_Mentor_Calls::register_post_type()`. A
-		// `publish` row here handed out the mentor's login through `?author=N`.
-		$post_id = wp_insert_post(
-			array(
-				'post_type'    => WPCPM_Mentor_Calls::POST_TYPE,
-				'post_status'  => 'private',
-				'post_author'  => get_current_user_id(),
-				'post_content' => $topic,
-				'post_title'   => sprintf(
-					/* translators: %s: session date and time. */
-					__( 'Group session - %s', 'wpcredits-program-manager' ),
-					wp_date( 'Y-m-d H:i', $start_ts )
-				),
-			),
-			true
-		);
+		$created = self::create_sessions( $mentor_id, $starts, $minutes, $capacity, $topic, $zone );
 
-		if ( is_wp_error( $post_id ) ) {
+		if ( array() === $created ) {
 			self::bounce( 'error' );
 		}
 
-		update_post_meta( $post_id, WPCPM_Mentor_Calls::META_START, $start_ts );
-		update_post_meta( $post_id, WPCPM_Mentor_Calls::META_END, $start_ts + ( $minutes * MINUTE_IN_SECONDS ) );
-		update_post_meta( $post_id, WPCPM_Mentor_Calls::META_MENTOR, (int) $mentor_id );
-		update_post_meta( $post_id, WPCPM_Mentor_Calls::META_CAPACITY, $capacity );
-		update_post_meta( $post_id, WPCPM_Mentor_Calls::META_ZONE, $zone->getName() );
+		if ( count( $created ) > 1 ) {
+			self::bounce( 'series-planned', array( count( $created ) ) );
+		}
 
 		self::bounce( 'session-created' );
 	}
 
+	/**
+	 * The starts of a list of dates at one time, or why the list is refused.
+	 *
+	 * All or nothing (the design's section 4): the first date that is not a date, has passed or is
+	 * given twice refuses the whole list, named, so the mentor fixes the list rather than ending up
+	 * with half a series. More than `MAX_SERIES` dates are refused before any is read. Every date
+	 * takes the one time on the mentor's clock, so a series keeps its clock time across a change to
+	 * or from summer time rather than drifting by an hour.
+	 *
+	 * @param string[]     $dates The dates, `Y-m-d`, as `WPCPM_Mentor_Availability::date_string()` gives them.
+	 * @param string       $time  The start time, `H:i`.
+	 * @param DateTimeZone $zone  The mentor's clock.
+	 * @param int          $now   The moment a date has to be after.
+	 * @return array `starts` (timestamps, soonest first, empty when refused), `refused` (`when`,
+	 *               `past`, `twice`, `many`, or '') and `date` (the date refused, or '').
+	 */
+	public static function plan_dates( array $dates, $time, DateTimeZone $zone, $now ) {
+		if ( count( $dates ) > self::MAX_SERIES ) {
+			return array(
+				'starts'  => array(),
+				'refused' => 'many',
+				'date'    => '',
+			);
+		}
+
+		$starts = array();
+		$seen   = array();
+
+		foreach ( $dates as $date ) {
+			$date = (string) $date;
+
+			if ( isset( $seen[ $date ] ) ) {
+				return array(
+					'starts'  => array(),
+					'refused' => 'twice',
+					'date'    => $date,
+				);
+			}
+
+			$seen[ $date ] = true;
+			$start         = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time, $zone );
+
+			if ( false === $start ) {
+				return array(
+					'starts'  => array(),
+					'refused' => 'when',
+					'date'    => $date,
+				);
+			}
+
+			$start_ts = $start->getTimestamp();
+
+			if ( $start_ts <= (int) $now ) {
+				return array(
+					'starts'  => array(),
+					'refused' => 'past',
+					'date'    => $date,
+				);
+			}
+
+			$starts[] = $start_ts;
+		}
+
+		sort( $starts );
+
+		return array(
+			'starts'  => $starts,
+			'refused' => '',
+			'date'    => '',
+		);
+	}
+
+	/**
+	 * The first of the starts the mentor already holds, or 0.
+	 *
+	 * @param int[]           $starts Timestamps.
+	 * @param array<int,bool> $taken  The starts already held, as `WPCPM_Mentor_Calls::taken_starts()` keys them.
+	 * @return int
+	 */
+	public static function first_clash( array $starts, array $taken ) {
+		foreach ( $starts as $start_ts ) {
+			if ( isset( $taken[ (int) $start_ts ] ) ) {
+				return (int) $start_ts;
+			}
+		}
+
+		return 0;
+	}
+
+	/**
+	 * Create the sessions of a list of starts, and tag them as one series when there is more than one.
+	 *
+	 * Each is created exactly as a session planned alone is; the series tag is the first created
+	 * ID, written to every one of them, the first included (the design's section 3). An insert
+	 * that fails unmakes what was made before it, so all or nothing holds for the insert too.
+	 *
+	 * @param int          $mentor_id The mentor.
+	 * @param int[]        $starts    Timestamps, soonest first.
+	 * @param int          $minutes   The length of each.
+	 * @param int          $capacity  The places of each.
+	 * @param string       $topic     What it is about.
+	 * @param DateTimeZone $zone      The mentor's clock, kept on each session.
+	 * @return int[] The post IDs, in date order; empty when one could not be created.
+	 */
+	public static function create_sessions( $mentor_id, array $starts, $minutes, $capacity, $topic, DateTimeZone $zone ) {
+		$created = array();
+
+		foreach ( $starts as $start_ts ) {
+			$start_ts = (int) $start_ts;
+
+			// `private`, like every call: see `WPCPM_Mentor_Calls::register_post_type()`. A
+			// `publish` row here handed out the mentor's login through `?author=N`.
+			$post_id = wp_insert_post(
+				array(
+					'post_type'    => WPCPM_Mentor_Calls::POST_TYPE,
+					'post_status'  => 'private',
+					'post_author'  => get_current_user_id(),
+					'post_content' => $topic,
+					'post_title'   => sprintf(
+						/* translators: %s: session date and time. */
+						__( 'Group session - %s', 'wpcredits-program-manager' ),
+						wp_date( 'Y-m-d H:i', $start_ts )
+					),
+				),
+				true
+			);
+
+			if ( is_wp_error( $post_id ) ) {
+				foreach ( $created as $made ) {
+					wp_delete_post( $made, true );
+				}
+
+				return array();
+			}
+
+			update_post_meta( $post_id, WPCPM_Mentor_Calls::META_START, $start_ts );
+			update_post_meta( $post_id, WPCPM_Mentor_Calls::META_END, $start_ts + ( (int) $minutes * MINUTE_IN_SECONDS ) );
+			update_post_meta( $post_id, WPCPM_Mentor_Calls::META_MENTOR, (int) $mentor_id );
+			update_post_meta( $post_id, WPCPM_Mentor_Calls::META_CAPACITY, (int) $capacity );
+			update_post_meta( $post_id, WPCPM_Mentor_Calls::META_ZONE, $zone->getName() );
+
+			$created[] = (int) $post_id;
+		}
+
+		if ( count( $created ) > 1 ) {
+			foreach ( $created as $post_id ) {
+				update_post_meta( $post_id, self::META_SERIES, $created[0] );
+			}
+		}
+
+		return $created;
+	}
+
+	/**
+	 * Refuse a list of dates: with the words a lone session has always had for one date, and with
+	 * the date named for a series, where the mentor has to find which of nine it was.
+	 *
+	 * @param string $why   `when`, `past`, `twice`, `clash` or `many`.
+	 * @param string $date  The date refused, `Y-m-d`, or ''.
+	 * @param int    $count How many dates the form carried.
+	 */
+	private static function refuse_dates( $why, $date, $count ) {
+		if ( 'when' === $why || $count < 2 ) {
+			$alone = array(
+				'past'  => 'session-past',
+				'clash' => 'session-clash',
+			);
+
+			self::bounce( isset( $alone[ $why ] ) ? $alone[ $why ] : 'session-when' );
+		}
+
+		self::bounce( 'series-' . $why, '' === $date ? array() : array( $date ) );
+	}
+
 	/**
 	 * Change a session that has already been announced.
 	 *
@@ -696,6 +880,26 @@ class WPCPM_Group_Sessions {
 			esc_html__( 'Date', 'wpcredits-program-manager' )
 		);
 
+		// More dates, for a series (the design's decision 1): the same time, length, places and
+		// topic for every one, an empty box passed over. Eight, so the first date and these make
+		// the nine a series holds at most.
+		echo '<fieldset class="wpcpm-field wpcpm-sessions__more">';
+		printf( '<legend>%s</legend>', esc_html__( 'More dates', 'wpcredits-program-manager' ) );
+
+		for ( $more = 2; $more <= self::MAX_SERIES; ++$more ) {
+			printf(
+				'<input type="date" name="more_dates[]" aria-label="%s" />',
+				/* translators: %d: the date's place in the series, from 2. */
+				esc_attr( sprintf( __( 'Date %d', 'wpcredits-program-manager' ), $more ) )
+			);
+		}
+
+		printf(
+			'<span class="wpcpm-field__hint">%s</span>',
+			esc_html__( 'Leave the ones you do not need empty. The same time, length, places and topic apply to every date.', 'wpcredits-program-manager' )
+		);
+		echo '</fieldset>';
+
 		printf(
 			'<p class="wpcpm-field"><label for="wpcpm-session-time">%1$s</label>'
 				. '<input type="time" id="wpcpm-session-time" name="time" required />'
@@ -739,7 +943,7 @@ class WPCPM_Group_Sessions {
 
 		printf(
 			'<p class="wpcpm-sessions__submit"><button type="submit" class="wpcpm-button">%s</button></p>',
-			esc_html__( 'Create the session', 'wpcredits-program-manager' )
+			esc_html__( 'Create the sessions', 'wpcredits-program-manager' )
 		);
 
 		echo '</form>';
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-group-sessions.php` and `php bin/test-handlers.php`

Expected: `bin/test-group-sessions.php` ends `ALL PASS (70 checks)` and `bin/test-handlers.php` ends `ALL HANDLERS REACHED A NORMAL OUTCOME`. 

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
git add assets/css/calendar.css bin/test-group-sessions.php bin/test-handlers.php includes/modules/class-wpcpm-group-sessions.php
git commit -m "Group sessions: a series planned from a list of dates, all or nothing"
```

---

### Task 3: The lists group a series under one heading

**Files:**
- Modify: `includes/modules/class-wpcpm-group-sessions.php` (`series_heading()`, `render_series()`; both lists draw `grouped()`; `render_session_row()` takes `$in_series`)
- Modify: `assets/css/calendar.css` (the series heading and list)
- Test: `bin/test-group-sessions.php`, `bin/test-handlers.php`

**Interfaces:**
- Consumes: Task 1's `grouped()` and `series_of()`; `get_option( 'date_format' )`, `wp_date()`.
- Produces: `series_heading( array $sessions, DateTimeZone $zone )`: "%d sessions, <first date> to <last date>" on the viewer's clock, or "1 session, on <date>" for a series down to one. The private `render_series( array $sessions, DateTimeZone $zone, $for_mentor, $student = null, $viewer_is_student = false )` prints `<li class="wpcpm-sessions__series">`, `<p class="wpcpm-sessions__series-heading"><strong>topic</strong> <span class="wpcpm-sessions__series-span">heading</span></p>` and `<ul class="wpcpm-sessions__list wpcpm-sessions__list--series">` of the rows; `render_session_row()` takes a sixth argument `$in_series` and leaves the topic off a row under a heading. `render_student_list()` and `render_mentor_panel()` iterate `grouped()`: a series through `render_series()`, a lone session through `render_session_row()` as before.

The design's sections 5 and 7 as markup, the same for both lists: the rows a series holds are the rows a lone session gets, with the topic printed once in the heading rather than on each (what this plan decides, 5). The heading is a counted phrase and a date span composed from two strings, since the gate refuses an `_n()` whose singular lacks a placeholder the plural has (what this plan decides, 4).

`bin/test-handlers.php` renders the student's list and the mentor's panel with a series and a lone session in the query's answer and reads the markup a person sees: one series block, the heading's words, three rows inside it, the topic once, the lone session as its own row, Join on each row not yet joined and Leave on the one joined, Change and Cancel on every row of the mentor's.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-group-sessions.php b/bin/test-group-sessions.php
index 225c7f5..7c9a34c 100644
--- a/bin/test-group-sessions.php
+++ b/bin/test-group-sessions.php
@@ -566,6 +566,17 @@ $lone = WPCPM_Group_Sessions::create_sessions( 20, array( $three['starts'][0] +
 ck( 'a session created alone carries no series',
     array( count( $lone ), WPCPM_Group_Sessions::series_of( $lone[0] ) ), array( 1, 0 ) );
 
+echo "\n=== A series' heading (1.108.0) ===\n";
+
+$utc = new DateTimeZone( 'UTC' );
+
+ck( 'the heading counts the sessions and spans the first and last date on the viewer\'s clock',
+    array(
+        WPCPM_Group_Sessions::series_heading( array_map( 'get_post', $planned ), $utc ),
+        WPCPM_Group_Sessions::series_heading( array( get_post( $planned[2] ) ), $utc ),
+    ),
+    array( '3 sessions, October 6, 2026 to October 20, 2026', '1 session, on October 20, 2026' ) );
+
 printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
 
 exit( $fails ? 1 : 0 );
diff --git a/bin/test-handlers.php b/bin/test-handlers.php
index e7c9ab7..dc2e198 100644
--- a/bin/test-handlers.php
+++ b/bin/test-handlers.php
@@ -543,6 +543,47 @@ check( 'a date given twice, a date that has passed and a clash each refuse the w
     array( $twice, $past, $clash, count( $GLOBALS['posts'] ) - $posts_before ),
     array( array( 'series-twice', '2027-03-09' ), array( 'series-past', '2020-03-09' ), array( 'series-clash', '2027-02-16' ), 0 ) );
 
+// 1.108.0: the lists group a series under one heading, the lone sessions on their own.
+$lone_sessions = array();
+foreach ( $GLOBALS['posts'] as $post ) {
+	if ( WPCPM_Mentor_Calls::POST_TYPE === $post->post_type && WPCPM_Mentor_Calls::capacity( $post->ID ) > 1 && 0 === WPCPM_Group_Sessions::series_of( $post->ID ) ) {
+		$lone_sessions[] = $post;
+	}
+}
+$GLOBALS['query_result'] = array_merge( array( $lone_sessions[0] ), array_map( 'get_post', $series ) );
+
+$GLOBALS['uid'] = 30;
+ob_start();
+WPCPM_Group_Sessions::render_student_list( $GLOBALS['users'][30], true );
+$student_list = ob_get_clean();
+
+check( 'the student\'s list draws the series under its heading with its three rows, the topic once, and the lone session as a row of its own, with Join on each row not yet joined and Leave on the one joined',
+    array(
+        substr_count( $student_list, 'class="wpcpm-sessions__series"' ),
+        false !== strpos( $student_list, '<p class="wpcpm-sessions__series-heading"><strong>Release cycle</strong> <span class="wpcpm-sessions__series-span">3 sessions, February 2, 2027 to February 16, 2027</span></p>' ),
+        substr_count( substr( $student_list, strpos( $student_list, 'wpcpm-sessions__list--series' ) ), '<li class="wpcpm-sessions__item">' ),
+        substr_count( $student_list, 'wpcpm-call__topic">Release cycle' ),
+        substr_count( $student_list, '<li class="wpcpm-sessions__item">' ),
+        substr_count( $student_list, 'name="action" value="wpcpm_join_session"' ),
+        substr_count( $student_list, 'name="action" value="wpcpm_leave_session"' ),
+    ),
+    array( 1, true, 3, 0, 4, 3, 1 ) );
+
+$GLOBALS['uid'] = 20;
+ob_start();
+WPCPM_Group_Sessions::render_mentor_panel( $GLOBALS['users'][20] );
+$mentor_panel = ob_get_clean();
+
+check( 'the mentor\'s panel groups the same series and keeps Change and Cancel on every row',
+    array(
+        substr_count( $mentor_panel, 'class="wpcpm-sessions__series"' ),
+        false !== strpos( $mentor_panel, '3 sessions, February 2, 2027 to February 16, 2027' ),
+        substr_count( $mentor_panel, 'name="action" value="' . WPCPM_Mentor_Calls::ACTION_CANCEL . '"' ),
+    ),
+    array( 1, true, 4 ) );
+
+$GLOBALS['query_result'] = array();
+
 $GLOBALS['uid'] = 20;
 $_POST          = array( 'session' => 0, 'note' => 'Covered the release cycle.' );
 run( 'handle_note (no such session)', array( 'WPCPM_Group_Sessions', 'handle_note' ) );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-group-sessions.php` and `php bin/test-handlers.php`

Expected: `bin/test-group-sessions.php` stops with `Fatal error: Uncaught Error: Call to undefined method WPCPM_Group_Sessions::series_heading()`; `bin/test-handlers.php` stops with `FAIL the student's list draws the series under its heading with its three rows, the topic once, and the lone session as a row of its own, with Join on each row not yet joined and Leave on the one joined`. `series_heading()` does not exist yet, and the lists draw every session as its own row.

- [ ] **Step 3: Write the code**

```diff
diff --git a/assets/css/calendar.css b/assets/css/calendar.css
index 408c8e1..f9a27d6 100644
--- a/assets/css/calendar.css
+++ b/assets/css/calendar.css
@@ -740,6 +740,29 @@
 	margin-top: 0.6em;
 }
 
+/* A series (1.108.0): one heading carrying the topic and the span, its sessions as the same rows
+   a lone session gets, a step in; a series and a lone session keep the list's rhythm. */
+.wpcpm-sessions__series {
+	margin-bottom: 0.75em;
+}
+
+.wpcpm-sessions__series:last-child {
+	margin-bottom: 0;
+}
+
+.wpcpm-sessions__series-heading {
+	margin: 0 0 0.5em;
+}
+
+.wpcpm-sessions__series-span {
+	font-size: 0.9em;
+	opacity: 0.72;
+}
+
+.wpcpm-sessions__list--series {
+	margin-left: 1em;
+}
+
 /* The create form and the note form are both disclosures, so a mentor's diary is not
    two forms deep before they have decided to write anything. */
 .wpcpm-sessions__new,
diff --git a/includes/modules/class-wpcpm-group-sessions.php b/includes/modules/class-wpcpm-group-sessions.php
index 0337079..4a96de2 100644
--- a/includes/modules/class-wpcpm-group-sessions.php
+++ b/includes/modules/class-wpcpm-group-sessions.php
@@ -814,8 +814,12 @@ class WPCPM_Group_Sessions {
 		} else {
 			echo '<ul class="wpcpm-sessions__list">';
 
-			foreach ( $sessions as $session ) {
-				self::render_session_row( $session, $zone, true );
+			foreach ( self::grouped( $sessions ) as $group ) {
+				if ( $group['series'] > 0 ) {
+					self::render_series( $group['sessions'], $zone, true );
+				} else {
+					self::render_session_row( $group['sessions'][0], $zone, true );
+				}
 			}
 
 			echo '</ul>';
@@ -974,14 +978,88 @@ class WPCPM_Group_Sessions {
 
 		echo '<ul class="wpcpm-sessions__list">';
 
-		foreach ( $sessions as $session ) {
-			self::render_session_row( $session, $zone, false, $student, $viewer_is_student );
+		foreach ( self::grouped( $sessions ) as $group ) {
+			if ( $group['series'] > 0 ) {
+				self::render_series( $group['sessions'], $zone, false, $student, $viewer_is_student );
+			} else {
+				self::render_session_row( $group['sessions'][0], $zone, false, $student, $viewer_is_student );
+			}
 		}
 
 		echo '</ul>';
 		echo '</div>';
 	}
 
+	/**
+	 * A series' heading: how many sessions, and the first and last date on the viewer's clock.
+	 *
+	 * The topic is not in it: the list prints the topic once above the sessions, and a series
+	 * down to its last session still reads as a series, since its tag says what it is (the
+	 * design's section 3).
+	 *
+	 * @param WP_Post[]    $sessions The series' upcoming sessions, soonest first.
+	 * @param DateTimeZone $zone     The clock to show the dates in.
+	 * @return string
+	 */
+	public static function series_heading( array $sessions, DateTimeZone $zone ) {
+		$first  = reset( $sessions );
+		$last   = end( $sessions );
+		$format = get_option( 'date_format', 'F j, Y' );
+		$count  = count( $sessions );
+		/* translators: %d: how many sessions. */
+		$how_many = sprintf( _n( '%d session', '%d sessions', $count, 'wpcredits-program-manager' ), $count );
+		$from     = wp_date( $format, (int) get_post_meta( $first->ID, WPCPM_Mentor_Calls::META_START, true ), $zone );
+
+		if ( $count < 2 ) {
+			/* translators: 1: "1 session", 2: its date. */
+			return sprintf( __( '%1$s, on %2$s', 'wpcredits-program-manager' ), $how_many, $from );
+		}
+
+		return sprintf(
+			/* translators: 1: "3 sessions", 2: the first date, 3: the last date. */
+			__( '%1$s, %2$s to %3$s', 'wpcredits-program-manager' ),
+			$how_many,
+			$from,
+			wp_date( $format, (int) get_post_meta( $last->ID, WPCPM_Mentor_Calls::META_START, true ), $zone )
+		);
+	}
+
+	/**
+	 * A series in a list: its heading, then its sessions as rows (the design's sections 5 and 7).
+	 *
+	 * The rows are the same rows a lone session gets, with the topic left off each, since the
+	 * heading carries it once for all of them.
+	 *
+	 * @param WP_Post[]    $sessions          The series' upcoming sessions, soonest first.
+	 * @param DateTimeZone $zone              The clock to show them in.
+	 * @param bool         $for_mentor        Whether this is the mentor's own list.
+	 * @param WP_User|null $student           The student, on their list.
+	 * @param bool         $viewer_is_student Whether the viewer may join or leave.
+	 */
+	private static function render_series( array $sessions, DateTimeZone $zone, $for_mentor, $student = null, $viewer_is_student = false ) {
+		$first = reset( $sessions );
+		$topic = trim( (string) $first->post_content );
+
+		echo '<li class="wpcpm-sessions__series">';
+		echo '<p class="wpcpm-sessions__series-heading">';
+
+		if ( '' !== $topic ) {
+			printf( '<strong>%s</strong> ', esc_html( $topic ) );
+		}
+
+		printf( '<span class="wpcpm-sessions__series-span">%s</span>', esc_html( self::series_heading( $sessions, $zone ) ) );
+		echo '</p>';
+
+		echo '<ul class="wpcpm-sessions__list wpcpm-sessions__list--series">';
+
+		foreach ( $sessions as $session ) {
+			self::render_session_row( $session, $zone, $for_mentor, $student, $viewer_is_student, true );
+		}
+
+		echo '</ul>';
+		echo '</li>';
+	}
+
 	/**
 	 * One session in a list.
 	 *
@@ -990,8 +1068,9 @@ class WPCPM_Group_Sessions {
 	 * @param bool         $for_mentor        Whether this is the mentor's own list.
 	 * @param WP_User|null $student           The student, on their list.
 	 * @param bool         $viewer_is_student Whether the viewer may join or leave.
+	 * @param bool         $in_series         Whether the row sits under a series heading, which carries the topic.
 	 */
-	private static function render_session_row( WP_Post $session, DateTimeZone $zone, $for_mentor, $student = null, $viewer_is_student = false ) {
+	private static function render_session_row( WP_Post $session, DateTimeZone $zone, $for_mentor, $student = null, $viewer_is_student = false, $in_series = false ) {
 		$facts  = WPCPM_Mentor_Calls::details( $session );
 		$joined = ( $student instanceof WP_User ) && self::has_joined( $session->ID, $student->ID );
 
@@ -1023,7 +1102,7 @@ class WPCPM_Group_Sessions {
 			)
 		);
 
-		if ( '' !== trim( (string) $facts['topic'] ) ) {
+		if ( ! $in_series && '' !== trim( (string) $facts['topic'] ) ) {
 			printf( '<p class="wpcpm-call__topic">%s</p>', esc_html( $facts['topic'] ) );
 		}
 
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-group-sessions.php` and `php bin/test-handlers.php`

Expected: `bin/test-group-sessions.php` ends `ALL PASS (71 checks)` and `bin/test-handlers.php` ends `ALL HANDLERS REACHED A NORMAL OUTCOME`. 

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
git add assets/css/calendar.css bin/test-group-sessions.php bin/test-handlers.php includes/modules/class-wpcpm-group-sessions.php
git commit -m "Group sessions: the lists group a series under one heading"
```

---

### Task 4: One calendar file for a series, and the two messages a series join sends

**Files:**
- Modify: `includes/class-wpcpm-ics.php` (`opening()`, `event()` and `text_of()` extracted from `build()`; `build_many()`)
- Modify: `includes/modules/class-wpcpm-mentor-calls.php` (`notify_joined_series()`, `calendar_many()`, `series_mail_body()`)
- Test: `bin/test-mail.php`

**Interfaces:**
- Consumes: `WPCPM_ICS::build()`'s lines, unchanged in what they print; `WPCPM_Mail::send()`, `site_name()`, `reply_to()`; `WPCPM_Mentor_Calls::details()`, `format_range()`, `mail_enabled()`; `WPCPM_Mentor_Availability::viewer_timezone()`, `zone_label()`, `meeting_place()`; the two dashboards' `page_url()`.
- Produces: `WPCPM_ICS::build_many( array $facts_list, $method, $mentor, $student, $summary, $body, $where = '' )`: one `VCALENDAR` with one `VEVENT` per call, each under its own UID with its own start and end, `SEQUENCE:0`, the shared summary, description and place, the organizer and the two attendees, folded and CRLF-delimited, through the filter `wpcpm_series_ics`. `WPCPM_Mentor_Calls::notify_joined_series( array $call_ids, WP_User $mentor, $student )`: one message to the mentor ("[site] <student> joined your series: %d sessions") and one to the student ("[site] Group sessions with <mentor>: %d dates"), each with the one file `mentor-sessions.ics` attached and cleaned up, the body listing every session's range on the reader's clock, the timezone, the meeting place when set, the topic, the sentence that the file adds them all at once, and the reader's dashboard link.

The design's section 6. `build()` is split into the lines that open a calendar, one event, and the folding, so `build_many()` is the opening, one event per call and the closing, and the single invitation is unchanged byte for byte (what this plan decides, 6). Each event in the series file is the event that session's single invitation carries, under the same UID, so a later move or cancellation of that session, which goes out alone, lands on the right entry.

The two messages follow `notify_booked()`'s shape: one `WPCPM_Mail::send()` per person with the file built once per recipient and memoized, since the sender asks for the attachment and the cleanup separately. The mail suite gains posts and their meta, builds two sessions, and reads what `wp_mail()` was handed: the two recipients and subjects, the ranges and the sentences in the student's body, and the one attachment present at send time and gone after.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-mail.php b/bin/test-mail.php
index bdf7fd4..78d48ad 100644
--- a/bin/test-mail.php
+++ b/bin/test-mail.php
@@ -82,6 +82,13 @@ function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
 function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
 function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
 function get_user_by( $f, $v ) { return $GLOBALS['users'][ (int) $v ] ?? false; }
+// Session posts, for the series message, which reads every session it names: a single meta read
+// answers the first row, a rows read answers them all, as WordPress does.
+$GLOBALS['posts'] = array();
+$GLOBALS['pmeta'] = array();
+function get_post( $id = null ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
+function get_post_meta( $id, $k = '', $single = false ) { $rows = $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? array(); $rows = is_array( $rows ) ? $rows : array( $rows ); if ( $single ) { return $rows ? $rows[0] : ''; } return $rows; }
+function get_post_time( $f, $gmt = false, $post = null ) { return 1785000000; }
 function get_current_user_id() { return $GLOBALS['uid']; }
 function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
 require_once __DIR__ . '/stubs/caps.php';
@@ -991,6 +998,93 @@ ck( 'the attachment exists at the moment it is sent',
 ck( 'and is gone once the send is over',
     array( file_exists( reset( $attached['attachments'] ) ) ), array( false ) );
 
+/* ---- a series: one file, every session in it (1.108.0) ------------------ */
+
+echo "\n=== A series in one calendar file ===\n";
+
+$second = $facts;
+$second['id']    = 78;
+$second['start'] = 1786604800;
+$second['end']   = 1786606600;
+
+$many = WPCPM_ICS::build_many( array( $facts, $second ), WPCPM_ICS::METHOD_REQUEST, $GLOBALS['users'][20], $GLOBALS['users'][30], 'Group session', 'Two of them', 'https://meet.example.test/room' );
+
+ck( 'one calendar holds one event per session, each under its own ID with its own start and a first sequence',
+    array(
+        substr_count( $many, 'BEGIN:VCALENDAR' ),
+        substr_count( $many, 'METHOD:REQUEST' ),
+        substr_count( $many, 'BEGIN:VEVENT' ),
+        substr_count( $many, 'UID:' . WPCPM_ICS::uid( 77 ) ),
+        substr_count( $many, 'UID:' . WPCPM_ICS::uid( 78 ) ),
+        substr_count( $many, 'DTSTART:' . gmdate( 'Ymd\THis\Z', 1786000000 ) ),
+        substr_count( $many, 'DTSTART:' . gmdate( 'Ymd\THis\Z', 1786604800 ) ),
+        substr_count( $many, 'SEQUENCE:0' ),
+        substr_count( $many, 'LOCATION:https://meet.example.test/room' ),
+        substr_count( $many, 'END:VCALENDAR' ),
+    ),
+    array( 1, 1, 2, 1, 1, 1, 1, 2, 2, 1 ) );
+
+$too_long = array();
+foreach ( explode( "\r\n", trim( $many ) ) as $line ) {
+	if ( strlen( $line ) > 75 ) { $too_long[] = $line; }
+}
+ck( 'and every line of it is folded to 75 octets', count( $too_long ), 0 );
+
+
+echo "\n=== The series message ===\n";
+
+foreach ( array( 401 => array( 1786000000, 1786001800 ), 402 => array( 1786604800, 1786606600 ) ) as $id => $when ) {
+	$session                    = new WP_Post();
+	$session->ID                = $id;
+	$session->post_type         = WPCPM_Mentor_Calls::POST_TYPE;
+	$session->post_content      = 'Release cycle';
+	$GLOBALS['posts'][ $id ]    = $session;
+	$GLOBALS['pmeta'][ $id ]    = array(
+		WPCPM_Mentor_Calls::META_START    => $when[0],
+		WPCPM_Mentor_Calls::META_END      => $when[1],
+		WPCPM_Mentor_Calls::META_MENTOR   => 20,
+		WPCPM_Mentor_Calls::META_CAPACITY => 6,
+		WPCPM_Mentor_Calls::META_ZONE     => 'UTC',
+		WPCPM_Mentor_Calls::META_STUDENT  => array( 30 ),
+	);
+}
+
+$GLOBALS['mail']                = array();
+$GLOBALS['opts']['date_format'] = 'F j, Y';
+$GLOBALS['opts']['time_format'] = 'g:i a';
+WPCPM_Mentor_Calls::notify_joined_series( array( 401, 402 ), $GLOBALS['users'][20], $GLOBALS['users'][30] );
+
+ck( 'the mentor and the student each get one message, the mentor\'s naming the student and the count, the student\'s the mentor and the count',
+    array(
+        count( $GLOBALS['mail'] ),
+        $GLOBALS['mail'][0]['to'],
+        false !== strpos( $GLOBALS['mail'][0]['subject'], 'Lu Example joined your series: 2 sessions' ),
+        $GLOBALS['mail'][1]['to'],
+        false !== strpos( $GLOBALS['mail'][1]['subject'], 'Group sessions with Ada Example: 2 dates' ),
+    ),
+    array( 2, 'ada@example.test', true, 'lu@example.test', true ) );
+
+ck( 'the student\'s message lists every date on their clock, the topic, and what the file does',
+    array(
+        false !== strpos( $GLOBALS['mail'][1]['body'], WPCPM_Mentor_Calls::format_range( 1786000000, 1786001800, new DateTimeZone( 'UTC' ) ) ),
+        false !== strpos( $GLOBALS['mail'][1]['body'], WPCPM_Mentor_Calls::format_range( 1786604800, 1786606600, new DateTimeZone( 'UTC' ) ) ),
+        false !== strpos( $GLOBALS['mail'][1]['body'], 'Release cycle' ),
+        false !== strpos( $GLOBALS['mail'][1]['body'], 'Times are shown in UTC.' ),
+        false !== strpos( $GLOBALS['mail'][1]['body'], 'adds all of them to your calendar at once' ),
+    ),
+    array( true, true, true, true, true ) );
+
+ck( 'each message carries the one file, present when sent and gone after, named for the series',
+    array(
+        count( $GLOBALS['mail'][0]['attachments'] ),
+        reset( $GLOBALS['mail'][0]['present'] ),
+        basename( reset( $GLOBALS['mail'][0]['attachments'] ) ),
+        file_exists( reset( $GLOBALS['mail'][0]['attachments'] ) ),
+        count( $GLOBALS['mail'][1]['attachments'] ),
+        reset( $GLOBALS['mail'][1]['present'] ),
+    ),
+    array( 1, true, 'mentor-sessions.ics', false, 1, true ) );
+
 /* ---- format_range ------------------------------------------------------ */
 
 echo "\n=== format_range ===\n";
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-mail.php`

Expected: `bin/test-mail.php` stops with `Fatal error: Uncaught Error: Call to undefined method WPCPM_ICS::build_many()`. `build_many()` does not exist yet.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/class-wpcpm-ics.php b/includes/class-wpcpm-ics.php
index 3be93b1..98fc219 100644
--- a/includes/class-wpcpm-ics.php
+++ b/includes/class-wpcpm-ics.php
@@ -53,12 +53,103 @@ class WPCPM_ICS {
 	public static function build( array $facts, $method, $mentor, $student, $summary, $body, $where = '', $sequence = null ) {
 		$method = self::METHOD_CANCEL === $method ? self::METHOD_CANCEL : self::METHOD_REQUEST;
 
-		$lines = array(
+		$lines   = self::opening( $method );
+		$lines   = array_merge( $lines, self::event( $facts, $method, $mentor, $student, $summary, $body, $where, $sequence ) );
+		$lines[] = 'END:VCALENDAR';
+
+		/**
+		 * Filter the calendar invitation for a call.
+		 *
+		 * @param string $ics    The `.ics` contents.
+		 * @param array  $facts  Call facts.
+		 * @param string $method `REQUEST` or `CANCEL`.
+		 */
+		return (string) apply_filters( 'wpcpm_call_ics', self::text_of( $lines ), $facts, $method );
+	}
+
+	/**
+	 * Build one calendar object holding several calls: a series a student joined at once (1.108.0).
+	 *
+	 * Each call is its own event under its own UID, exactly the event its single invitation would
+	 * carry, so a later move or cancellation of one session, which goes out alone, lands on the
+	 * right entry. Importing the file once plans them all (the design's section 6).
+	 *
+	 * @param array[]      $facts_list Call facts, one per call, from `WPCPM_Mentor_Calls::details()`.
+	 * @param string       $method     `REQUEST` or `CANCEL`.
+	 * @param WP_User|null $mentor     Mentor, the organizer.
+	 * @param WP_User|null $student    Student, the attendee.
+	 * @param string       $summary    Event title, the same on each.
+	 * @param string       $body       Event description, as plain text, the same on each.
+	 * @param string       $where      Meeting URL or place, may be empty.
+	 * @return string The `.ics` contents, CRLF-delimited.
+	 */
+	public static function build_many( array $facts_list, $method, $mentor, $student, $summary, $body, $where = '' ) {
+		$method = self::METHOD_CANCEL === $method ? self::METHOD_CANCEL : self::METHOD_REQUEST;
+		$lines  = self::opening( $method );
+
+		foreach ( $facts_list as $facts ) {
+			$lines = array_merge( $lines, self::event( $facts, $method, $mentor, $student, $summary, $body, $where, null ) );
+		}
+
+		$lines[] = 'END:VCALENDAR';
+
+		/**
+		 * Filter the calendar file for a series of calls.
+		 *
+		 * @param string  $ics        The `.ics` contents.
+		 * @param array[] $facts_list The calls' facts.
+		 * @param string  $method     `REQUEST` or `CANCEL`.
+		 */
+		return (string) apply_filters( 'wpcpm_series_ics', self::text_of( $lines ), $facts_list, $method );
+	}
+
+	/**
+	 * The lines that open a calendar object.
+	 *
+	 * @param string $method `REQUEST` or `CANCEL`.
+	 * @return string[]
+	 */
+	private static function opening( $method ) {
+		return array(
 			'BEGIN:VCALENDAR',
 			'VERSION:2.0',
 			'PRODID:-//WordPress Credits Program//WPCredits Program Manager//EN',
 			'CALSCALE:GREGORIAN',
 			'METHOD:' . $method,
+		);
+	}
+
+	/**
+	 * The lines, folded, as one CRLF-delimited text.
+	 *
+	 * @param string[] $lines Content lines.
+	 * @return string
+	 */
+	private static function text_of( array $lines ) {
+		$folded = array();
+
+		foreach ( $lines as $line ) {
+			$folded[] = self::fold( $line );
+		}
+
+		return implode( "\r\n", $folded ) . "\r\n";
+	}
+
+	/**
+	 * One event, from its `BEGIN:VEVENT` to its `END:VEVENT`.
+	 *
+	 * @param array        $facts    Call facts.
+	 * @param string       $method   `REQUEST` or `CANCEL`.
+	 * @param WP_User|null $mentor   Mentor, the organizer.
+	 * @param WP_User|null $student  Student, the attendee.
+	 * @param string       $summary  Event title.
+	 * @param string       $body     Event description, as plain text.
+	 * @param string       $where    Meeting URL or place, may be empty.
+	 * @param int|null     $sequence Revision number; null keeps the default.
+	 * @return string[]
+	 */
+	private static function event( array $facts, $method, $mentor, $student, $summary, $body, $where, $sequence ) {
+		$lines = array(
 			'BEGIN:VEVENT',
 			'UID:' . self::uid( $facts['id'] ),
 			// A calendar that already holds this event is entitled to ignore anything that does
@@ -95,27 +186,8 @@ class WPCPM_ICS {
 		}
 
 		$lines[] = 'END:VEVENT';
-		$lines[] = 'END:VCALENDAR';
-
-		$folded = array();
-
-		foreach ( $lines as $line ) {
-			$folded[] = self::fold( $line );
-		}
 
-		/**
-		 * Filter the calendar invitation for a call.
-		 *
-		 * @param string $ics    The `.ics` contents.
-		 * @param array  $facts  Call facts.
-		 * @param string $method `REQUEST` or `CANCEL`.
-		 */
-		return (string) apply_filters(
-			'wpcpm_call_ics',
-			implode( "\r\n", $folded ) . "\r\n",
-			$facts,
-			$method
-		);
+		return $lines;
 	}
 
 	/**
diff --git a/includes/modules/class-wpcpm-mentor-calls.php b/includes/modules/class-wpcpm-mentor-calls.php
index fbca0f4..a7919d0 100644
--- a/includes/modules/class-wpcpm-mentor-calls.php
+++ b/includes/modules/class-wpcpm-mentor-calls.php
@@ -1408,6 +1408,76 @@ class WPCPM_Mentor_Calls {
 		);
 	}
 
+	/**
+	 * Tell both people a student joined a series: one message each, with one calendar file holding
+	 * every session the student was put on (the design's section 6, 1.108.0).
+	 *
+	 * @param int[]        $call_ids The sessions the student was put on, soonest first.
+	 * @param WP_User      $mentor   Mentor.
+	 * @param WP_User|null $student  Student.
+	 */
+	public static function notify_joined_series( array $call_ids, WP_User $mentor, $student ) {
+		$facts_list = array();
+
+		foreach ( $call_ids as $call_id ) {
+			$call = get_post( (int) $call_id );
+
+			if ( $call instanceof WP_Post && self::mail_enabled( $call ) ) {
+				$facts_list[] = self::details( $call );
+			}
+		}
+
+		if ( array() === $facts_list || ! $student instanceof WP_User ) {
+			return;
+		}
+
+		$count = count( $facts_list );
+
+		WPCPM_Mail::send(
+			$mentor,
+			'series-joined',
+			function ( $recipient ) use ( $facts_list, $count, $mentor, $student ) {
+				$invite = self::calendar_many( $facts_list, $mentor, $student, $recipient );
+
+				return array(
+					'subject'     => sprintf(
+						/* translators: 1: site name, 2: student name, 3: how many sessions. */
+						__( '[%1$s] %2$s joined your series: %3$d sessions', 'wpcredits-program-manager' ),
+						WPCPM_Mail::site_name(),
+						$student->display_name,
+						$count
+					),
+					'body'        => self::series_mail_body( $facts_list, $recipient, $student->display_name, true ),
+					'headers'     => WPCPM_Mail::reply_to( $student ),
+					'attachments' => $invite,
+					'cleanup'     => $invite,
+				);
+			}
+		);
+
+		WPCPM_Mail::send(
+			$student,
+			'series-joined',
+			function ( $recipient ) use ( $facts_list, $count, $mentor, $student ) {
+				$invite = self::calendar_many( $facts_list, $mentor, $student, $recipient );
+
+				return array(
+					'subject'     => sprintf(
+						/* translators: 1: site name, 2: mentor name, 3: how many sessions. */
+						__( '[%1$s] Group sessions with %2$s: %3$d dates', 'wpcredits-program-manager' ),
+						WPCPM_Mail::site_name(),
+						$mentor->display_name,
+						$count
+					),
+					'body'        => self::series_mail_body( $facts_list, $recipient, $mentor->display_name, false ),
+					'headers'     => WPCPM_Mail::reply_to( $mentor ),
+					'attachments' => $invite,
+					'cleanup'     => $invite,
+				);
+			}
+		);
+	}
+
 	/**
 	 * Everybody a message about this call should go to: the mentor, then every attendee.
 	 *
@@ -1689,6 +1759,118 @@ class WPCPM_Mentor_Calls {
 		return $built[ $memo ];
 	}
 
+	/**
+	 * The calendar file for a series, written for `wp_mail()` to attach (1.108.0).
+	 *
+	 * Memoized like `calendar()`, since `WPCPM_Mail::send()` asks for it twice per message, once
+	 * for the attachment and once for the cleanup.
+	 *
+	 * @param array[] $facts_list Call facts, one per session.
+	 * @param WP_User $mentor     Mentor.
+	 * @param WP_User $student    Student.
+	 * @param WP_User $recipient  Who the message is for.
+	 * @return string[] The file's path in a list, or an empty list when it could not be written.
+	 */
+	private static function calendar_many( array $facts_list, WP_User $mentor, WP_User $student, WP_User $recipient ) {
+		static $built = array();
+
+		$ids  = array_map( 'intval', array_column( $facts_list, 'id' ) );
+		$memo = implode( ',', $ids ) . '|' . $recipient->ID;
+
+		if ( isset( $built[ $memo ] ) ) {
+			return $built[ $memo ];
+		}
+
+		$summary = sprintf(
+			/* translators: 1: mentor name, 2: student name. */
+			__( 'Mentor call: %1$s and %2$s', 'wpcredits-program-manager' ),
+			$mentor->display_name,
+			$student->display_name
+		);
+
+		$description = sprintf(
+			/* translators: 1: mentor name, 2: how many sessions the series holds. */
+			__( 'A group session on the WordPress Credits Program with %1$s, one of %2$d in a series.', 'wpcredits-program-manager' ),
+			$mentor->display_name,
+			count( $facts_list )
+		);
+
+		$ics  = WPCPM_ICS::build_many( $facts_list, WPCPM_ICS::METHOD_REQUEST, $mentor, $student, $summary, $description, WPCPM_Mentor_Availability::meeting_place( $mentor->ID ) );
+		$path = WPCPM_ICS::tempfile( $ics, 'mentor-sessions.ics' );
+
+		$built[ $memo ] = '' === $path ? array() : array( $path );
+
+		return $built[ $memo ];
+	}
+
+	/**
+	 * The body of a series message: every session on the reader's clock, the topic, the place,
+	 * and what the attached file does (the design's section 6, 1.108.0).
+	 *
+	 * @param array[] $facts_list Call facts, one per session, soonest first.
+	 * @param WP_User $recipient  Who is reading it.
+	 * @param string  $other      The other person's name.
+	 * @param bool    $to_mentor  Whether the reader is the mentor.
+	 * @return string
+	 */
+	private static function series_mail_body( array $facts_list, WP_User $recipient, $other, $to_mentor ) {
+		$zone  = WPCPM_Mentor_Availability::viewer_timezone( $recipient->ID );
+		$first = reset( $facts_list );
+		$count = count( $facts_list );
+		$lines = array();
+
+		$lines[] = $to_mentor
+			? sprintf(
+				/* translators: 1: student name, 2: how many sessions. */
+				_n( '%1$s joined %2$d group session of yours:', '%1$s joined %2$d group sessions of yours:', $count, 'wpcredits-program-manager' ),
+				$other,
+				$count
+			)
+			: sprintf(
+				/* translators: 1: mentor name, 2: how many sessions. */
+				_n( 'You are on %2$d group session with %1$s:', 'You are on %2$d group sessions with %1$s:', $count, 'wpcredits-program-manager' ),
+				$other,
+				$count
+			);
+
+		foreach ( $facts_list as $facts ) {
+			$lines[] = self::format_range( $facts['start'], $facts['end'], $zone );
+		}
+
+		$lines[] = '';
+		$lines[] = sprintf(
+			/* translators: %s: timezone name. */
+			__( 'Times are shown in %s.', 'wpcredits-program-manager' ),
+			WPCPM_Mentor_Availability::zone_label( $zone->getName() )
+		);
+
+		$where = WPCPM_Mentor_Availability::meeting_place( (int) $first['mentor_id'] );
+
+		if ( '' !== $where ) {
+			$lines[] = '';
+			$lines[] = __( 'Where you will meet:', 'wpcredits-program-manager' );
+			$lines[] = $where;
+		}
+
+		if ( '' !== trim( (string) $first['topic'] ) ) {
+			$lines[] = '';
+			$lines[] = __( 'What the sessions are about:', 'wpcredits-program-manager' );
+			$lines[] = $first['topic'];
+		}
+
+		$lines[] = '';
+		$lines[] = __( 'The attached calendar file adds all of them to your calendar at once.', 'wpcredits-program-manager' );
+
+		$page = $to_mentor ? WPCPM_Mentors_Dashboard::page_url() : WPCPM_Students_Dashboard::page_url();
+
+		if ( '' !== $page ) {
+			$lines[] = '';
+			$lines[] = $page;
+		}
+
+		return implode( "\n", $lines );
+	}
+
 	/**
 	 * The body of a booking, reminder or calendar description.
 	 *
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-mail.php`

Expected: `bin/test-mail.php` ends `ALL PASS`. 

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
git add bin/test-mail.php includes/class-wpcpm-ics.php includes/modules/class-wpcpm-mentor-calls.php
git commit -m "Group sessions: one calendar file for a series, and the two messages a series join sends"
```

---

### Task 5: Join all takes every session of a series that has a place

**Files:**
- Modify: `includes/modules/class-wpcpm-group-sessions.php` (`ACTION_JOIN_SERIES` and its hook; `joinable()`; `handle_join_series()`; the Join all form in `render_series()`)
- Modify: `assets/css/calendar.css` (the Join all form)
- Test: `bin/test-group-sessions.php`, `bin/test-handlers.php`

**Interfaces:**
- Consumes: Task 1's `series_members()` and `series_of()`, Task 4's `notify_joined_series()`; `has_joined()`, `acting_student()`; `WPCPM_Mentor_Calls::mentor_for_student()`, `why_not_bookable( $student_id, $mentor, false )`, `lock_for()`, `unlock_for()`, `has_room()`, `add_attendee()`, `student_record()`.
- Produces: `WPCPM_Group_Sessions::ACTION_JOIN_SERIES` (`wpcpm_join_series`) on `admin_post_`. `joinable( array $sessions, $student_id )`: `take` (the sessions the student is not on that have a place), `full` and `on` (counts). `handle_join_series()`: nonce, logged in, `series` posted; `session-gone` when the series has no upcoming session; `session-not-yours` unless the sessions are the student's own mentor's; `blocked` for the reasons other than the limit; `busy` when the mentor's lock is held; under the lock `joinable()` again, `series-nothing` when nothing is left to take, else every session taken through `add_attendee()`, the lock released, `notify_joined_series()` for the sessions taken, and `series-joined` with the series' count when none was full or `series-joined-some` with the count the student is on, the series' count and the count that was full. The Join all form (`wpcpm-sessions__join--series`, hidden `series` and `student`, the button "Join all") is drawn under a series heading on the student's own list while `joinable()` has something to take.

The design's section 5 and decision 2. The handler is `handle_join()`'s guards on a series, then the booking lock, and under it the plan is read again, since a place can go between the page and the press (what this plan decides, 8): a full session is skipped and counted, a session the student is on is left alone, the rest are taken in date order. One message each holds every session taken.

`bin/test-handlers.php` presses through the whole flow: no such series, another mentor's series, a series of three with the middle session filled to its places by six other students (two taken, one full, the flash naming the counts, the student on the first and the third, one message each with the file, the lock released), a second press finding nothing, and the list no longer offering Join all.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-group-sessions.php b/bin/test-group-sessions.php
index 7c9a34c..886cf76 100644
--- a/bin/test-group-sessions.php
+++ b/bin/test-group-sessions.php
@@ -577,6 +577,25 @@ ck( 'the heading counts the sessions and spans the first and last date on the vi
     ),
     array( '3 sessions, October 6, 2026 to October 20, 2026', '1 session, on October 20, 2026' ) );
 
+echo "\n=== Join all: what a student may still take (1.108.0) ===\n";
+
+$open = make_call( 2 );
+$on   = make_call( 2 );
+$fill = make_call( 2 );
+WPCPM_Mentor_Calls::add_attendee( $on, 31, 'recSTUDENT0000001' );
+WPCPM_Mentor_Calls::add_attendee( $fill, 32, 'recSTUDENT0000002' );
+WPCPM_Mentor_Calls::add_attendee( $fill, 33, 'recSTUDENT0000003' );
+
+$plan = WPCPM_Group_Sessions::joinable( array_map( 'get_post', array( $open, $on, $fill ) ), 31 );
+
+ck( 'a session the student is on and a full one are passed over, counted apart; the open one is taken',
+    array( array_map( function ( $p ) { return $p->ID; }, $plan['take'] ), $plan['on'], $plan['full'] ),
+    array( array( $open ), 1, 1 ) );
+
+ck( 'with nothing left to take, the plan says so',
+    WPCPM_Group_Sessions::joinable( array_map( 'get_post', array( $on, $fill ) ), 31 ),
+    array( 'take' => array(), 'full' => 1, 'on' => 1 ) );
+
 printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
 
 exit( $fails ? 1 : 0 );
diff --git a/bin/test-handlers.php b/bin/test-handlers.php
index dc2e198..fa8f8b9 100644
--- a/bin/test-handlers.php
+++ b/bin/test-handlers.php
@@ -582,6 +582,63 @@ check( 'the mentor\'s panel groups the same series and keeps Change and Cancel o
     ),
     array( 1, true, 4 ) );
 
+// 1.108.0: Join all takes every session of the series with a place, under the booking lock.
+$GLOBALS['query_result'] = array_map( 'get_post', $series );
+$GLOBALS['uid']          = 30;
+
+check( 'the series offers Join all while there is something to take',
+    array( substr_count( $student_list, 'name="action" value="wpcpm_join_series"' ), substr_count( $student_list, '>Join all</button>' ) ),
+    array( 1, 1 ) );
+
+$_POST = array( 'series' => 0 );
+run( 'handle_join_series (no such series)', array( 'WPCPM_Group_Sessions', 'handle_join_series' ) );
+check( 'no such series is gone', flashed( 30, 'call' ), 'session-gone' );
+
+$GLOBALS['posts'][900]            = new WP_Post();
+$GLOBALS['posts'][900]->ID        = 900;
+$GLOBALS['posts'][900]->post_type = WPCPM_Mentor_Calls::POST_TYPE;
+$GLOBALS['pmeta'][900]            = array(
+	WPCPM_Mentor_Calls::META_MENTOR => 21, WPCPM_Mentor_Calls::META_CAPACITY => 6,
+	WPCPM_Mentor_Calls::META_START => time() + 864000, WPCPM_Mentor_Calls::META_END => time() + 867600,
+	WPCPM_Group_Sessions::META_SERIES => 900,
+);
+$GLOBALS['query_result'] = array( $GLOBALS['posts'][900] );
+$_POST                   = array( 'series' => 900 );
+run( 'handle_join_series (another mentor\'s series)', array( 'WPCPM_Group_Sessions', 'handle_join_series' ) );
+check( 'another mentor\'s series is not theirs', flashed( 30, 'call' ), 'session-not-yours' );
+
+// The middle session fills up before the student presses.
+foreach ( array( 41, 42, 43, 44, 45, 46 ) as $other ) {
+	WPCPM_Mentor_Calls::add_attendee( $series[1], $other, 'recSTUDENT' . $other . '000000' );
+}
+$GLOBALS['query_result'] = array_map( 'get_post', $series );
+$GLOBALS['mail']         = array();
+$_POST                   = array( 'series' => $series[0] );
+run( 'handle_join_series (two of three, one full)', array( 'WPCPM_Group_Sessions', 'handle_join_series' ) );
+$outcome = flashed( 30, 'call' );
+
+check( 'the student is put on the two sessions with a place, the full one is skipped and counted, and one message each goes to the student and the mentor with the file',
+    array(
+        $outcome,
+        WPCPM_Group_Sessions::has_joined( $series[0], 30 ),
+        WPCPM_Group_Sessions::has_joined( $series[1], 30 ),
+        WPCPM_Group_Sessions::has_joined( $series[2], 30 ),
+        count( $GLOBALS['mail'] ),
+        false !== strpos( $GLOBALS['mail'][1]['subj'], 'Group sessions with Mia Mentor: 2 dates' ),
+        basename( reset( $GLOBALS['mail'][1]['attachments'] ) ),
+        get_option( 'wpcpm_booking_lock_20', false ),
+    ),
+    array( array( 'series-joined-some', 2, 3, 1 ), true, false, true, 2, true, 'mentor-sessions.ics', false ) );
+
+$_POST = array( 'series' => $series[0] );
+run( 'handle_join_series (nothing left to take)', array( 'WPCPM_Group_Sessions', 'handle_join_series' ) );
+check( 'a second press finds nothing to join', flashed( 30, 'call' ), 'series-nothing' );
+
+ob_start();
+WPCPM_Group_Sessions::render_student_list( $GLOBALS['users'][30], true );
+$after_list = ob_get_clean();
+check( 'and the series no longer offers Join all', substr_count( $after_list, 'name="action" value="wpcpm_join_series"' ), 0 );
+
 $GLOBALS['query_result'] = array();
 
 $GLOBALS['uid'] = 20;
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-group-sessions.php` and `php bin/test-handlers.php`

Expected: `bin/test-group-sessions.php` stops with `Fatal error: Uncaught Error: Call to undefined method WPCPM_Group_Sessions::joinable()`; `bin/test-handlers.php` stops with `FAIL the series offers Join all while there is something to take`. `joinable()` does not exist yet, and the student's list offers no Join all.

- [ ] **Step 3: Write the code**

```diff
diff --git a/assets/css/calendar.css b/assets/css/calendar.css
index f9a27d6..3ae0fa2 100644
--- a/assets/css/calendar.css
+++ b/assets/css/calendar.css
@@ -763,6 +763,10 @@
 	margin-left: 1em;
 }
 
+.wpcpm-sessions__join--series {
+	margin: 0 0 0.75em;
+}
+
 /* The create form and the note form are both disclosures, so a mentor's diary is not
    two forms deep before they have decided to write anything. */
 .wpcpm-sessions__new,
diff --git a/includes/modules/class-wpcpm-group-sessions.php b/includes/modules/class-wpcpm-group-sessions.php
index 4a96de2..83da34d 100644
--- a/includes/modules/class-wpcpm-group-sessions.php
+++ b/includes/modules/class-wpcpm-group-sessions.php
@@ -41,6 +41,9 @@ class WPCPM_Group_Sessions {
 	const ACTION_NOTE   = 'wpcpm_session_note';
 	const ACTION_EDIT   = 'wpcpm_edit_session';
 
+	/** Join every session of a series that has a place (the design's decision 2, 1.108.0). */
+	const ACTION_JOIN_SERIES = 'wpcpm_join_series';
+
 	/**
 	 * The series a session was planned in: the post ID of the series' first session, on every one
 	 * of them, the first included.
@@ -103,6 +106,7 @@ class WPCPM_Group_Sessions {
 		add_action( 'admin_post_' . self::ACTION_LEAVE, array( __CLASS__, 'handle_leave' ) );
 		add_action( 'admin_post_' . self::ACTION_NOTE, array( __CLASS__, 'handle_note' ) );
 		add_action( 'admin_post_' . self::ACTION_EDIT, array( __CLASS__, 'handle_edit' ) );
+		add_action( 'admin_post_' . self::ACTION_JOIN_SERIES, array( __CLASS__, 'handle_join_series' ) );
 	}
 
 	/*
@@ -724,6 +728,108 @@ class WPCPM_Group_Sessions {
 		self::bounce( 'session-joined' );
 	}
 
+	/**
+	 * The sessions of a series a student may still take: the ones they are not on that have a place.
+	 *
+	 * Read again under the booking lock before anything is taken, since a place can go between
+	 * the page and the press (the design's section 5).
+	 *
+	 * @param WP_Post[] $sessions   The series' upcoming sessions, soonest first.
+	 * @param int       $student_id The student.
+	 * @return array `take` (WP_Post[]), `full` (how many had no place) and `on` (how many they are on).
+	 */
+	public static function joinable( array $sessions, $student_id ) {
+		$take = array();
+		$full = 0;
+		$on   = 0;
+
+		foreach ( $sessions as $session ) {
+			if ( self::has_joined( $session->ID, $student_id ) ) {
+				++$on;
+				continue;
+			}
+
+			if ( ! WPCPM_Mentor_Calls::has_room( $session->ID ) ) {
+				++$full;
+				continue;
+			}
+
+			$take[] = $session;
+		}
+
+		return array(
+			'take' => $take,
+			'full' => $full,
+			'on'   => $on,
+		);
+	}
+
+	/**
+	 * Join every session of a series that has a place (the design's decision 2, 1.108.0).
+	 *
+	 * The guards a single join has, then the lock the one-to-one booking takes, and under it the
+	 * sessions are read again: a full one is skipped and counted, one the student is on is left
+	 * alone, the rest are taken. One message each to the student and the mentor holds every
+	 * session taken, in one calendar file.
+	 */
+	public static function handle_join_series() {
+		check_admin_referer( self::ACTION_JOIN_SERIES );
+
+		if ( ! is_user_logged_in() ) {
+			wp_die( esc_html__( 'Please log in to join a session.', 'wpcredits-program-manager' ), 403 );
+		}
+
+		$student_id = self::acting_student();
+		$series_id  = isset( $_POST['series'] ) ? absint( wp_unslash( $_POST['series'] ) ) : 0;
+		$sessions   = self::series_members( $series_id );
+
+		if ( array() === $sessions ) {
+			self::bounce( 'session-gone' );
+		}
+
+		$mentor = get_user_by( 'id', (int) get_post_meta( $sessions[0]->ID, WPCPM_Mentor_Calls::META_MENTOR, true ) );
+		$theirs = WPCPM_Mentor_Calls::mentor_for_student( $student_id );
+
+		if ( ! $mentor instanceof WP_User || ! $theirs instanceof WP_User || (int) $theirs->ID !== (int) $mentor->ID ) {
+			self::bounce( 'session-not-yours' );
+		}
+
+		// The reasons a student cannot book at all, the per-student limit left out: a session's
+		// places are its own limit (1.107.1).
+		if ( '' !== WPCPM_Mentor_Calls::why_not_bookable( $student_id, $mentor, false ) ) {
+			self::bounce( 'blocked' );
+		}
+
+		if ( ! WPCPM_Mentor_Calls::lock_for( $mentor->ID ) ) {
+			self::bounce( 'busy' );
+		}
+
+		$plan = self::joinable( $sessions, $student_id );
+
+		if ( array() === $plan['take'] ) {
+			WPCPM_Mentor_Calls::unlock_for( $mentor->ID );
+			self::bounce( 'series-nothing' );
+		}
+
+		$record = WPCPM_Mentor_Calls::student_record( $student_id );
+		$taken  = array();
+
+		foreach ( $plan['take'] as $session ) {
+			WPCPM_Mentor_Calls::add_attendee( $session->ID, $student_id, $record );
+			$taken[] = (int) $session->ID;
+		}
+
+		WPCPM_Mentor_Calls::unlock_for( $mentor->ID );
+
+		WPCPM_Mentor_Calls::notify_joined_series( $taken, $mentor, get_user_by( 'id', $student_id ) );
+
+		if ( $plan['full'] > 0 ) {
+			self::bounce( 'series-joined-some', array( count( $taken ) + $plan['on'], count( $sessions ), $plan['full'] ) );
+		}
+
+		self::bounce( 'series-joined', array( count( $sessions ) ) );
+	}
+
 	/**
 	 * Leave a session.
 	 *
@@ -1050,6 +1156,26 @@ class WPCPM_Group_Sessions {
 		printf( '<span class="wpcpm-sessions__series-span">%s</span>', esc_html( self::series_heading( $sessions, $zone ) ) );
 		echo '</p>';
 
+		// Join all, while there is a session of the series the student is not on that has a place
+		// (the design's section 5). Not drawn for the mentor, nor for somebody looking over a
+		// student's shoulder.
+		if ( ! $for_mentor && $viewer_is_student && $student instanceof WP_User && array() !== self::joinable( $sessions, $student->ID )['take'] ) {
+			printf(
+				'<form class="wpcpm-sessions__join wpcpm-sessions__join--series" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
+				esc_url( admin_url( 'admin-post.php' ) ),
+				esc_attr__( 'Joining…', 'wpcredits-program-manager' )
+			);
+			wp_nonce_field( self::ACTION_JOIN_SERIES );
+			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_JOIN_SERIES ) );
+			printf( '<input type="hidden" name="series" value="%d" />', (int) self::series_of( $first->ID ) );
+			printf( '<input type="hidden" name="student" value="%d" />', (int) $student->ID );
+			printf(
+				'<button type="submit" class="wpcpm-button">%s</button>',
+				esc_html__( 'Join all', 'wpcredits-program-manager' )
+			);
+			echo '</form>';
+		}
+
 		echo '<ul class="wpcpm-sessions__list wpcpm-sessions__list--series">';
 
 		foreach ( $sessions as $session ) {
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-group-sessions.php` and `php bin/test-handlers.php`

Expected: `bin/test-group-sessions.php` ends `ALL PASS (73 checks)` and `bin/test-handlers.php` ends `ALL HANDLERS REACHED A NORMAL OUTCOME`. 

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
git add assets/css/calendar.css bin/test-group-sessions.php bin/test-handlers.php includes/modules/class-wpcpm-group-sessions.php
git commit -m "Group sessions: Join all takes every session of a series that has a place"
```

---

### Task 6: The guides on a series

**Files:**
- Modify: `docs/sections/11-student-booking.md` (a paragraph under *Group sessions*), `docs/sections/21-mentor-availability.md` (**More dates** in the planning list, a paragraph on a series)
- Modify: `docs/students.md`, `docs/mentors.md`, `docs/administrators.md`, `docs/build/students.html`, `docs/build/mentors.html`, `docs/build/administrators.html` (rebuilt by `bin/build-docs.php`)

**Interfaces:**
- Consumes: the screens' words as Tasks 2 to 5 leave them.
- Produces: the two guide paragraphs and the six generated files.

The design's section 10, in the guides' own voice: what a student sees and presses, what a mentor fills in and what refuses it. The diff holds the generated guides too, so the replay is exact and a reviewer reads what will be published; running the build again after applying it changes nothing.

- [ ] **Step 1: Write the paragraphs, and the guides they rebuild into**

```diff
diff --git a/docs/administrators.md b/docs/administrators.md
index 6560321..f725965 100644
--- a/docs/administrators.md
+++ b/docs/administrators.md
@@ -914,6 +914,8 @@ walkthrough, a question hour, a session for everybody starting the same week.
 - **Places** - how many students may join, between 2 and 50.
 - **What it is about**. Your students read this beside the session, so it is how they decide
   whether it is for them.
+- **More dates**, for a series: up to eight further dates with the same time, length, places and
+  topic. Leave the ones you do not need empty.
 
 A session is not carved out of your weekly hours; you pick any time, including one you would never
 offer for private calls. It does **block that time from one-to-one booking**, so nobody books you
@@ -927,6 +929,13 @@ one-to-one calls, and a session's places are its own.
 Everybody who joins gets an email with a calendar invitation, and the reminder 24 hours before goes
 to all of them. If you cancel the session, every student on it is told.
 
+A series is planned all or nothing: a date that has passed, a date given twice, or a date on which
+you already hold a session or a call refuses the whole list and names the date, so nothing is
+created until the list is right. Your students see the series under one heading and can **Join
+all** in one press, which sends them one email with one calendar file for every date; each date
+keeps its own row, so a student can still join or leave a single one. Changing or canceling a
+session touches that session alone, and the rest of the series stands.
+
 ### One note for the whole group
 
 Under a session you have run, **Add a note for everybody on this session**. You write it once and it
@@ -1115,6 +1124,12 @@ somebody else.
 A session does not count towards the number of upcoming calls you may hold at once: join every
 session that has a place, and your one-to-one booking is unaffected.
 
+Your mentor may plan a **series**: several sessions on several dates, with the same topic, length
+and places. The list shows a series under one heading, each date as its own row. **Join all** puts
+you on every date that still has a place and sends you one email with one calendar file that plans
+them all; a date that is already full is skipped, and the message says so. You can still join or
+leave a single date from its row.
+
 ## Telling us how it is going
 
 Under your report form are three short forms - **Getting started**, **Half way** and **Finishing
diff --git a/docs/build/administrators.html b/docs/build/administrators.html
index 4ec36b2..57b4bbd 100644
--- a/docs/build/administrators.html
+++ b/docs/build/administrators.html
@@ -1053,6 +1053,9 @@
 <!-- wp:list-item -->
 <li><strong>What it is about</strong>. Your students read this beside the session, so it is how they decide whether it is for them.</li>
 <!-- /wp:list-item -->
+<!-- wp:list-item -->
+<li><strong>More dates</strong>, for a series: up to eight further dates with the same time, length, places and topic. Leave the ones you do not need empty.</li>
+<!-- /wp:list-item -->
 </ul>
 <!-- /wp:list -->
 
@@ -1068,6 +1071,10 @@
 <p>Everybody who joins gets an email with a calendar invitation, and the reminder 24 hours before goes to all of them. If you cancel the session, every student on it is told.</p>
 <!-- /wp:paragraph -->
 
+<!-- wp:paragraph -->
+<p>A series is planned all or nothing: a date that has passed, a date given twice, or a date on which you already hold a session or a call refuses the whole list and names the date, so nothing is created until the list is right. Your students see the series under one heading and can <strong>Join all</strong> in one press, which sends them one email with one calendar file for every date; each date keeps its own row, so a student can still join or leave a single one. Changing or canceling a session touches that session alone, and the rest of the series stands.</p>
+<!-- /wp:paragraph -->
+
 <!-- wp:heading {"level":3,"anchor":"one-note-for-the-whole-group"} -->
 <h3 class="wp-block-heading" id="one-note-for-the-whole-group">One note for the whole group</h3>
 <!-- /wp:heading -->
@@ -1311,6 +1318,10 @@
 <p>A session does not count towards the number of upcoming calls you may hold at once: join every session that has a place, and your one-to-one booking is unaffected.</p>
 <!-- /wp:paragraph -->
 
+<!-- wp:paragraph -->
+<p>Your mentor may plan a <strong>series</strong>: several sessions on several dates, with the same topic, length and places. The list shows a series under one heading, each date as its own row. <strong>Join all</strong> puts you on every date that still has a place and sends you one email with one calendar file that plans them all; a date that is already full is skipped, and the message says so. You can still join or leave a single date from its row.</p>
+<!-- /wp:paragraph -->
+
 <!-- wp:heading {"level":2,"anchor":"telling-us-how-it-is-going"} -->
 <h2 class="wp-block-heading" id="telling-us-how-it-is-going">Telling us how it is going</h2>
 <!-- /wp:heading -->
diff --git a/docs/build/mentors.html b/docs/build/mentors.html
index c2622e2..b0455ae 100644
--- a/docs/build/mentors.html
+++ b/docs/build/mentors.html
@@ -206,6 +206,9 @@
 <!-- wp:list-item -->
 <li><strong>What it is about</strong>. Your students read this beside the session, so it is how they decide whether it is for them.</li>
 <!-- /wp:list-item -->
+<!-- wp:list-item -->
+<li><strong>More dates</strong>, for a series: up to eight further dates with the same time, length, places and topic. Leave the ones you do not need empty.</li>
+<!-- /wp:list-item -->
 </ul>
 <!-- /wp:list -->
 
@@ -221,6 +224,10 @@
 <p>Everybody who joins gets an email with a calendar invitation, and the reminder 24 hours before goes to all of them. If you cancel the session, every student on it is told.</p>
 <!-- /wp:paragraph -->
 
+<!-- wp:paragraph -->
+<p>A series is planned all or nothing: a date that has passed, a date given twice, or a date on which you already hold a session or a call refuses the whole list and names the date, so nothing is created until the list is right. Your students see the series under one heading and can <strong>Join all</strong> in one press, which sends them one email with one calendar file for every date; each date keeps its own row, so a student can still join or leave a single one. Changing or canceling a session touches that session alone, and the rest of the series stands.</p>
+<!-- /wp:paragraph -->
+
 <!-- wp:heading {"level":3,"anchor":"one-note-for-the-whole-group"} -->
 <h3 class="wp-block-heading" id="one-note-for-the-whole-group">One note for the whole group</h3>
 <!-- /wp:heading -->
@@ -464,6 +471,10 @@
 <p>A session does not count towards the number of upcoming calls you may hold at once: join every session that has a place, and your one-to-one booking is unaffected.</p>
 <!-- /wp:paragraph -->
 
+<!-- wp:paragraph -->
+<p>Your mentor may plan a <strong>series</strong>: several sessions on several dates, with the same topic, length and places. The list shows a series under one heading, each date as its own row. <strong>Join all</strong> puts you on every date that still has a place and sends you one email with one calendar file that plans them all; a date that is already full is skipped, and the message says so. You can still join or leave a single date from its row.</p>
+<!-- /wp:paragraph -->
+
 <!-- wp:heading {"level":2,"anchor":"telling-us-how-it-is-going"} -->
 <h2 class="wp-block-heading" id="telling-us-how-it-is-going">Telling us how it is going</h2>
 <!-- /wp:heading -->
diff --git a/docs/build/students.html b/docs/build/students.html
index af9b03b..8b2ebb7 100644
--- a/docs/build/students.html
+++ b/docs/build/students.html
@@ -212,6 +212,10 @@
 <p>A session does not count towards the number of upcoming calls you may hold at once: join every session that has a place, and your one-to-one booking is unaffected.</p>
 <!-- /wp:paragraph -->
 
+<!-- wp:paragraph -->
+<p>Your mentor may plan a <strong>series</strong>: several sessions on several dates, with the same topic, length and places. The list shows a series under one heading, each date as its own row. <strong>Join all</strong> puts you on every date that still has a place and sends you one email with one calendar file that plans them all; a date that is already full is skipped, and the message says so. You can still join or leave a single date from its row.</p>
+<!-- /wp:paragraph -->
+
 <!-- wp:heading {"level":2,"anchor":"telling-us-how-it-is-going"} -->
 <h2 class="wp-block-heading" id="telling-us-how-it-is-going">Telling us how it is going</h2>
 <!-- /wp:heading -->
diff --git a/docs/mentors.md b/docs/mentors.md
index 3e2387f..161022a 100644
--- a/docs/mentors.md
+++ b/docs/mentors.md
@@ -132,6 +132,8 @@ walkthrough, a question hour, a session for everybody starting the same week.
 - **Places** - how many students may join, between 2 and 50.
 - **What it is about**. Your students read this beside the session, so it is how they decide
   whether it is for them.
+- **More dates**, for a series: up to eight further dates with the same time, length, places and
+  topic. Leave the ones you do not need empty.
 
 A session is not carved out of your weekly hours; you pick any time, including one you would never
 offer for private calls. It does **block that time from one-to-one booking**, so nobody books you
@@ -145,6 +147,13 @@ one-to-one calls, and a session's places are its own.
 Everybody who joins gets an email with a calendar invitation, and the reminder 24 hours before goes
 to all of them. If you cancel the session, every student on it is told.
 
+A series is planned all or nothing: a date that has passed, a date given twice, or a date on which
+you already hold a session or a call refuses the whole list and names the date, so nothing is
+created until the list is right. Your students see the series under one heading and can **Join
+all** in one press, which sends them one email with one calendar file for every date; each date
+keeps its own row, so a student can still join or leave a single one. Changing or canceling a
+session touches that session alone, and the rest of the series stands.
+
 ### One note for the whole group
 
 Under a session you have run, **Add a note for everybody on this session**. You write it once and it
@@ -333,6 +342,12 @@ somebody else.
 A session does not count towards the number of upcoming calls you may hold at once: join every
 session that has a place, and your one-to-one booking is unaffected.
 
+Your mentor may plan a **series**: several sessions on several dates, with the same topic, length
+and places. The list shows a series under one heading, each date as its own row. **Join all** puts
+you on every date that still has a place and sends you one email with one calendar file that plans
+them all; a date that is already full is skipped, and the message says so. You can still join or
+leave a single date from its row.
+
 ## Telling us how it is going
 
 Under your report form are three short forms - **Getting started**, **Half way** and **Finishing
diff --git a/docs/sections/11-student-booking.md b/docs/sections/11-student-booking.md
index 2d21fbd..5954191 100644
--- a/docs/sections/11-student-booking.md
+++ b/docs/sections/11-student-booking.md
@@ -39,3 +39,9 @@ somebody else.
 
 A session does not count towards the number of upcoming calls you may hold at once: join every
 session that has a place, and your one-to-one booking is unaffected.
+
+Your mentor may plan a **series**: several sessions on several dates, with the same topic, length
+and places. The list shows a series under one heading, each date as its own row. **Join all** puts
+you on every date that still has a place and sends you one email with one calendar file that plans
+them all; a date that is already full is skipped, and the message says so. You can still join or
+leave a single date from its row.
diff --git a/docs/sections/21-mentor-availability.md b/docs/sections/21-mentor-availability.md
index 31b7afb..d1b6f02 100644
--- a/docs/sections/21-mentor-availability.md
+++ b/docs/sections/21-mentor-availability.md
@@ -47,6 +47,8 @@ walkthrough, a question hour, a session for everybody starting the same week.
 - **Places** - how many students may join, between 2 and 50.
 - **What it is about**. Your students read this beside the session, so it is how they decide
   whether it is for them.
+- **More dates**, for a series: up to eight further dates with the same time, length, places and
+  topic. Leave the ones you do not need empty.
 
 A session is not carved out of your weekly hours; you pick any time, including one you would never
 offer for private calls. It does **block that time from one-to-one booking**, so nobody books you
@@ -60,6 +62,13 @@ one-to-one calls, and a session's places are its own.
 Everybody who joins gets an email with a calendar invitation, and the reminder 24 hours before goes
 to all of them. If you cancel the session, every student on it is told.
 
+A series is planned all or nothing: a date that has passed, a date given twice, or a date on which
+you already hold a session or a call refuses the whole list and names the date, so nothing is
+created until the list is right. Your students see the series under one heading and can **Join
+all** in one press, which sends them one email with one calendar file for every date; each date
+keeps its own row, so a student can still join or leave a single one. Changing or canceling a
+session touches that session alone, and the rest of the series stands.
+
 ### One note for the whole group
 
 Under a session you have run, **Add a note for everybody on this session**. You write it once and it
diff --git a/docs/students.md b/docs/students.md
index 4afe22d..4575a0f 100644
--- a/docs/students.md
+++ b/docs/students.md
@@ -158,6 +158,12 @@ somebody else.
 A session does not count towards the number of upcoming calls you may hold at once: join every
 session that has a place, and your one-to-one booking is unaffected.
 
+Your mentor may plan a **series**: several sessions on several dates, with the same topic, length
+and places. The list shows a series under one heading, each date as its own row. **Join all** puts
+you on every date that still has a place and sends you one email with one calendar file that plans
+them all; a date that is already full is skipped, and the message says so. You can still join or
+leave a single date from its row.
+
 ## Telling us how it is going
 
 Under your report form are three short forms - **Getting started**, **Half way** and **Finishing
```

- [ ] **Step 2: Rebuild, and confirm the build changes nothing**

Run: `php bin/build-docs.php && git status --short`

Expected: the build reports the four guides, and the status names the same eight files the diff changed and nothing else.

- [ ] **Step 3: Run the checks that read the guides**

Run: `php bin/check-spelling.php | tail -1 && php bin/test-handbook.php | tail -1`

Expected: a line ending `US English throughout.`, then `ALL PASS`.

- [ ] **Step 4: Read the built pages once**

Run: `grep -c "Join all" docs/build/students.html docs/build/mentors.html docs/build/administrators.html`

Expected: `1`, `2` and `2`: the mentor guide holds the student booking section too, so both paragraphs are in it.

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
git add docs/administrators.md docs/build/administrators.html docs/build/mentors.html docs/build/students.html docs/mentors.md docs/sections/11-student-booking.md docs/sections/21-mentor-availability.md docs/students.md
git commit -m "Group sessions: the guides on a series"
```

---

### Task 7: Release 1.108.0

**Files:**
- Modify: `wpcredits-program-manager.php` (the `Version:` header and `WPCPM_VERSION`), `readme.txt` (`Stable tag:` and a changelog entry), `languages/wpcredits-program-manager.pot` (regenerated)

- [ ] **Step 1: Move the version.** `1.107.1` becomes `1.108.0` in the plugin header's `Version:` line, in `define( 'WPCPM_VERSION', ... )` and in `readme.txt`'s `Stable tag:`. Every other mention of 1.107.1 stays, including the changelog's own heading.

- [ ] **Step 2: Write the changelog entry**, first under `== Changelog ==` in `readme.txt`, above the previous entry and with one empty line after it:

```text
= 1.108.0 =

* A mentor can plan a series of group sessions at once. The planning form takes up to eight more dates with the same time, length, places and topic, all or nothing: a date that has passed, a date given twice or a date the mentor already holds refuses the whole list and names the date. The lists show a series under one heading, each date as its own row.
* A student joins a whole series in one press. Join all takes every session of the series that still has a place, skips a full one and says so, and sends one email with one calendar file that holds every session as its own event, so importing it once plans them all. Joining or leaving a single session works as before, and a change or cancellation of one session reaches its own calendar entry.
* The notice after a press can name a date or a count: which date a series was refused on, how many sessions were planned, how many a student is on.
```

- [ ] **Step 3: Regenerate the translation template.** `sh bin/make-pot.sh`. Expected: `Success: POT file successfully generated.` and a header reading `Project-Id-Version: WPCredits Program Manager 1.108.0`. (WP-CLI prints a deprecation notice from its own colors library first; it is not the plugin's.)

- [ ] **Step 4: Run everything.** Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 5: Build the zip and read it back.**

```bash
bash bin/build
unzip -p ../wpcredits-program-manager.zip wpcredits-program-manager/wpcredits-program-manager.php | grep "Version:"
unzip -Z1 ../wpcredits-program-manager.zip | grep -cE '^wpcredits-program-manager/(bin|docs)/'
unzip -Z1 ../wpcredits-program-manager.zip | grep -c 'class-wpcpm-ics.php'
```

Expected: `Version:           1.108.0`, `0`, and `1`.

- [ ] **Step 6: Commit.**

```bash
git add wpcredits-program-manager.php readme.txt languages/wpcredits-program-manager.pot
git commit -m "Group sessions: 1.108.0"
```

- [ ] **Step 7: Merge and mirror, on the product owner's choice.** Merge `session-series` into `main` and run the battery on the result. Then push the source to the public mirror: pull the `WordPress/WPCredits` clone at `~/GitHub/Plugins/WPCredits-Tracker-mirror` (branch `trunk`), rsync the plugin into its `Education/WordPress Education Dashboard/wpcredits-program-manager/` with `rsync -a --delete --exclude '.git/' --exclude '.superpowers/' --exclude '.DS_Store' --exclude 'node_modules/' --exclude '*.zip' --exclude '.*.swp'`, update the version in that folder's `README.md` table, scan the added lines and the untracked files (`git ls-files --others --exclude-standard`) for keys, record IDs, email addresses and names, read `git diff --stat`, then commit and push as two separate steps. This plan and its spec travel with the source; neither holds a Liquid tag (a brace followed by a percent sign), which a Jekyll build of the mirror would fail on.

- [ ] **Step 8: Deploy only on the product owner's yes.** Ask first. On a yes, follow the deploy recorded for `wordpresseducation.org`: stream the zip over `ssh wpcredits-dashboard`, check its md5 on arrival, install it as a step of its own, read the version back, and purge the edge cache with `echo y | ssh wpcredits-dashboard 'wp edge-cache purge --domain --yes'`. Before the install and after it, run the read-only probe of what a person sees (the program map, the Programs running card, the Student Report Card as the TEST students, the Track Builder's list) with version strings and relative times normalized; the two runs must be identical, since nothing on those pages changes in this release.

- [ ] **Step 9: Republish the three guide pages, in the same deploy.** The student and mentor guides changed, and the program manager guide holds both. Build the block markup with the uploads base, `php bin/build-docs.php --base=https://wordpresseducation.org/wp-content/uploads/2026/08`, copy `docs/build/students.html`, `mentors.html` and `administrators.html` aside, and run `php bin/build-docs.php` again so the repository keeps its relative image paths and `git status` stays clean. On the site, for each of pages 558, 559 and 560: back up `post_content` to a file, stream the built copy over ssh stdin, update the page through `wp_update_post()` with `wp_slash()` as the owner's own administrator account (`wp eval-file - <id> <file>`), check that the stored byte count equals the file's, that `_wpcpm_access_level` survived, and that no `<img src="` lacks a scheme; then purge once.

**The live site needs nothing new to take this release.** A series is a tag on posts the site already keeps; nothing is created in Airtable; a mentor sees the more dates the next time they open the planning form, and students see a series only once a mentor plans one.

---

## What this plan leaves out, by the spec's decisions

- **Leave the series** and **Cancel the remaining sessions** (decisions 2 and 5): a student leaves a date from its row, a mentor cancels one from its row.
- **More than nine dates in one form** (decision 1): a longer series is planned in two goes.
- **A recurring rule or a true recurring event** (decision 4).
- **RSVP from the calendar**, which the single invitation does not have either.

## What this plan parks, with its reasons

- **The event summary of a group session reads "Mentor call: <mentor> and <student>"**, in the series file as in the single invitation it copies; renaming it is a change to the single invitation too, and belongs to a release about the invitations.
- **A series' rows repeat the time range each**, which for a weekly series at one time is the same hour nine times; the date is what differs, and the row is the same row a lone session gets, so nothing new had to be learned.
- **A refused date names the date only**, not which box held it; the form has nine boxes and the date is enough to find it.
- **The mentor's message on a series join lists the dates the student was put on**, not the ones that were full; the student's notice says how many were full, and the mentor's panel shows every session's places.
