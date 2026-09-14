# Track Builder T3c Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The Learn course entry point of the Track Builder - the course resolved from its link on save, its lessons listed under the groups of the track's page with "Add a question under this lesson", a question's Learn lesson row, New track from a link, and every lessoned question matched again when the course changes - plus T3b's leftovers, the seed command's exit code and the Track Builder section of the program manager guide, as release 1.107.0.

**Architecture:** One new client and no new screen. `WPCPM_Learn` sits beside `WPCPM_Airtable`: it reads Learn's two public endpoints through `wp_remote_get()`, keeps each answer in a transient for a day, forgets on demand, and parses with no WordPress in the parsing, so its suite drives the real class with the HTTP stood in. `WPCPM_Track_Questions` gains the two pure rules of decisions 32 and 33: a question placed under a lesson, and every lessoned question matched again by heading. The builder resolves the link on save and hands its screens the course and the lessons already mapped to groups, so the screens ask Learn nothing; the editor's Add form, the question screen's lesson row and the New track form take a lesson or a link where they took nothing. The leftovers make one date helper of two, teach the preview about a stale built-in draft, remove a method with no caller and fix a dozen small findings in place; the CLI command gets its first suite; the guide section is prose and a rebuild.

**Tech Stack:** WordPress 6.5 and PHP 7.4 as floors, WordPress coding standards, the plugin's standalone `bin/test-*.php` suites, `bin/build-docs.php` for the guides, no build step, no new script: `assets/js/track-editor.js` is untouched.

**Spec:** `docs/specs/2026-09-10-track-builder-design.md` at `21ee73c`: decisions 30 to 33 in section 1, settled on 14 September 2026; 2.6 for what Learn says; 4.2 for `course_url` and `learn_lesson_id`; section 5 for New track from a link and the re-match; section 6 for the track's page and the question; 7.1 for the preflight's warning; 11's T3c row for the tests; 12's T3c row for the phase.

## Global Constraints

- **Start from `main` at 1.106.1 with the T3c amendment.** `grep "^Stable tag" readme.txt` prints `Stable tag: 1.106.1`, `git log --oneline -1 -- docs/specs/2026-09-10-track-builder-design.md` names `21ee73c`, which amended decision 33's leftovers sentence on top of `23434e1`, the commit that added decisions 30 to 33, and `git status --short` prints nothing. Then `git switch -c track-builder-t3c`. Every block below was proven one commit at a time on `23434e1`, whose tree differs from `21ee73c` by that one sentence of the spec, and replayed onto a fresh checkout.
- WordPress coding standards throughout: tabs, Yoda conditions, spaces inside parentheses, `array()` and never `[]`, strict `in_array()`. Everything that ships is PHP 7.4 compatible: no `match`, no named arguments, no union types, no arrow functions in a constant; `Closure::bind()` with a static closure is fine, `ReflectionProperty::setAccessible()` is not, since PHP 8.1 deprecates it and 8.5 prints a line about it on every run. Every string a person reads is US English. No em dash or en dash anywhere, in code, comments, docs or commit messages: a plain hyphen. Full product names ("Student Report Card", "Mentor Report Card", "Administrator Dashboard").
- Everything a person can see is escaped on output, and every handler checks capability first, then nonce, in that order (the design's decision 3.9). `bin/test-track-builder.php` fails if the two are swapped, on the new handler as on every older one.
- **The battery stays silent after every task:** `for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done` prints nothing. `php bin/check-references.php`, `php bin/check-spelling.php` and `php bin/check-dead-annotations.php` report clean. `bash bin/check-standards.sh` prints no line containing ` ERROR `, and its last line reads `85 warnings, no errors.` or a lower count. What tripped it while this plan was proven: a parameter added to a method needs its own `@param` line, aligned with the others (`$lessons`, `$lesson`, `$stale`); a comparison of a variable with a call is Yoda's the other way round (`$tail === $login`, never `$login === substr( ... )`); an argument of `printf()` is escaped or cast inline (`(int)`), never bare; every method has a docblock, so a replacement that swallows one is an error.
- **Test first, every task but one.** Add the checks, run them, see them fail as the step says, then write the code. Task 9 is the exception it names: its checks pin behavior the code already has and pass before the code moves, which is what proves the refactors that follow change nothing.
- **A suite drives the real class wherever the rule lives in one.** `bin/test-learn.php` loads the real `WPCPM_Learn` and stands in `wp_remote_get()`, the transients and `WP_Error`. `bin/test-track-builder.php` loads the real `WPCPM_Learn` from Task 3 on, the same way, beside the real `WPCPM_Track_Questions`, `WPCPM_Track_Definition`, `WPCPM_Track_Diff` and the screens; it stands in the store, publishing, the students sync, the calendar module and the report form, as T3b left it. `bin/test-track-publish.php` stands `WPCPM_Learn` in, because the preflight's rule is which severity a failed link gets, not what Learn says. `bin/test-cli.php` stands in `WP_CLI` and the store.
- **Learn is read by the builder, never by a screen, and never written.** `course_of()`, `lessons_of()`, `resolve_course()` and `question_form()` read it through the client, whose answers live a day; a failed read is never cached; the client sends nothing but GET. A screen draws what `form()` and `question_form()` hand it.
- **The definition format does not change.** `course_url`, `learn_course_id` and `learn_lesson_id` are T1's properties; `schema_version` stays 1; the seeds are untouched. Nothing in this plan touches Airtable's shape either: publishing is unchanged but for where the link warning comes from.
- **A column name is exact.** It is the question's key, verbatim, and an Airtable name can end in a space (4.2). Nothing here trims one.
- **The live Student Report Card's markup does not change.** The report form is touched once, in Task 9, by a guard on the hours label that draws the same label for every stored definition; `bin/test-report-form.php`'s three md5 pins from T3b hold.
- **Nothing is deleted in Airtable, ever** (section 14, decision 9). Nothing in this plan deletes anything at all.
- Every new class file is required in `wpcredits-program-manager.php` and in `uninstall.php`, in that order, or `bin/test-roles.php` fails.
- Version numbers move in Task 12 only: plugin 1.107.0, which `version_compare()` orders after 1.106.1. No theme release.
- Comments explain why and name the decision or the review that made the rule. Every commit message starts with "Track Builder T3c:" and ends with the `Co-Authored-By:` trailer of whoever made it, after a blank line.

## What this plan decides

1. **Learn's answers are kept per link and per course, a day each** (decision 30). The resolver's answer lives under the transient `wpcpm_learn_course_<slug>` and the structure under `wpcpm_learn_structure_<id>`, both for `DAY_IN_SECONDS`; `forget( $course_id, $url )` drops both; a `WP_Error` is never stored, so the next open tries again.
2. **The resolver's errors are four codes.** `wpcpm_learn_not_a_course` for a link of another shape, with no request made; `wpcpm_learn_no_course` when Learn answered an empty list; `wpcpm_learn_unreachable` when it did not answer or answered a status other than 200; `wpcpm_learn_bad_answer` when it answered something of another shape. The save notice and the publish warning print the message; the codes are for the suites.
3. **Nothing Learn lists is lost, and nothing a student cannot see is kept.** A lesson in draft is left out of the structure; a lesson outside every module is kept under a module with id 0 and no title, at the end; `by_group()` drops a module that is no group and a group with no module is simply absent; `group_of()` matches Onboarding, Project and Wrap-up to the form's groups once case and punctuation are folded (2.6).
4. **A question under a lesson is an ordinary Add with one more thing known about where it goes** (decision 32). `WPCPM_Track_Questions::add()` takes an `$after` column and lands the question behind it, falling back to the group rule when the map does not hold that column; `last_of_lesson()` says which column that is and whether the lesson has a question at all; the lesson's title becomes the new question's `lead` only when it has none, since a heading belongs to the first question after it.
5. **The re-match folds and compares headings, and runs only when it can be right** (decision 33). `rematch()` lowers case, drops apostrophes and collapses spaces on both sides, compares a question's `lead`, or its `subgroup` when it has no lead, with each lesson's title, and answers the map with the columns `matched` and `cleared`. `handle_save()` calls it only when the save resolved the link to a different course and that course's lessons could be read; when they cannot, the course change is held back: the stored link and course stay, the questions keep their lessons, and the notice says to try again once Learn answers (corrected in execution: the plan first said the questions keep what they had and the notice says to save again, which no later save could honor once the new course was stored, the final review's one Important finding; the fix is on the branch after Task 12).
6. **A stored course ID survives a failed read of an unchanged link** (decision 31), so a passing outage never blanks a course; a new link that does not resolve has no ID, since the last one was another course's; an emptied link clears it. A save with a caveat is neither a success nor an error, so the notice gets a third status, `warning`.
7. **New track's name defaults to the course's title** when the label was left empty and the link resolved, and the hue rule stays `first_free()` (decision 31). A link that does not resolve is kept with the warning rather than refused, so a long link is not retyped over an outage.
8. **The screens are handed the lessons already mapped** (decision 32). `form()` carries `course` (`id`, `title`, `error`), `lessons` (group => the module's lessons) and `learn` (one sentence when Learn could not be read, else ''); `question_form()` carries `lessons` (the modules, for the row's optgroups), `learn` and `course` (whether the track has one); the track route passes `lesson` from `wpcpm_lesson`, so "Add a question under this lesson" opens the group's Add form with that lesson chosen.
9. **When Learn cannot be read, one sentence per place, and the boxes stay** (decision 24's rule). The track's page says so under its Questions heading and lists no lessons; the question screen shows the stored lesson as a number box with a sentence. A track with no course has no lesson row at all: the value rides hidden, so a lesson kept from an earlier course is not lost by a save.
10. **One date helper, on the builder screen, public.** `WPCPM_Track_Builder_Screen::when_and_who( $at, $by )` says "%1$s by %2$s" for the list's Last published column, History's saves and the publish log; a deleted account is "somebody since removed", and user 0, which a WP-CLI save and the seeding leave, is "the site itself". `stale()` is one rule shared by `rows()` and `preview()`, so the preview of a built-in draft that fell behind the seed says so.
11. **`forget_counts()` goes** (decision 33). Its one caller was the students-sync suite resetting a cache between checks, which is a test's business: `let_counts_go()` does it through a closure bound to the class, without a method on the class and without `setAccessible()`.
12. **The seed command tries every seed, then exits through `WP_CLI::error()` naming the ones that failed.** The compile still runs for the ones that landed, and a second run creates only what is missing, so nothing is lost by going on.
13. **The guide section is prose without an image token**, so the repo copy and the published copy are built from the same section, and screenshots come in a docs-only release when the product owner takes them (decision 33).

## File structure

**Created**

| File | What it holds |
| --- | --- |
| `includes/class-wpcpm-learn.php` | The Learn client: the course behind a link, a course's modules and lessons, a day's cache, `forget()`, and the pure parsing and grouping. |
| `bin/test-learn.php` | The client's suite, with the HTTP answered from a table by URL. |
| `bin/fixtures/learn-courses-wordpress-credits.json` | Learn's answer to the 150-hour course's slug, trimmed to the fields the client reads. |
| `bin/fixtures/learn-structure-403425.json` | The Designer course's structure as Learn answered it, under `response`. |
| `bin/test-cli.php` | The first suite of `WPCPM_CLI`: `wp wpcredits seed-tracks`. |

**Modified**

| File | Why |
| --- | --- |
| `includes/tracks/class-wpcpm-track-questions.php` | `add()` after a named column; `last_of_lesson()`; `rematch()`. |
| `includes/tools/class-wpcpm-track-builder.php` | The course resolved on save and read again on demand; `course_of()` and `lessons_of()`; `form()` and `question_form()` hand the screens the course and the lessons; New track from a link; the re-match; `stale` in `preview()`; the links loop's data unchanged. |
| `includes/tools/class-wpcpm-track-builder-screen.php` | The course row and "Read the course again"; the New track form's link; a `warning` notice; `when_and_who()`; the stale preview sentence; the four links from one loop; docblock and comment minors. |
| `includes/tools/class-wpcpm-track-editor.php` | `handle_add()` under a lesson; `posted_question()` reads the lesson. |
| `includes/tools/class-wpcpm-track-editor-screen.php` | The lessons under each group; the Add form's "Under lesson"; the question's lesson row; the stored control read once. |
| `includes/tools/class-wpcpm-track-history-screen.php` | Its date helper goes; a dead half of a guard goes. |
| `includes/tracks/class-wpcpm-track-publish.php` | The preflight's link warning through `WPCPM_Learn`; the HEAD request and its cache go. |
| `includes/tracks/class-wpcpm-track-diff.php` | A comment on the two orders and a decision number. |
| `includes/modules/class-wpcpm-students-sync.php` | `forget_counts()` goes. |
| `includes/modules/class-wpcpm-student-report-form.php` | The hours label guarded. |
| `includes/class-wpcpm-cli.php` | The exit code. |
| `docs/sections/32-admin-tools.md`, `docs/administrators.md`, `docs/build/administrators.html` | The Track Builder section, and the two guides it rebuilds into. |
| `wpcredits-program-manager.php`, `uninstall.php` | The new class file. |
| `bin/test-track-questions.php`, `bin/test-track-builder.php`, `bin/test-track-publish.php`, `bin/test-students-sync.php`, `bin/test-report-form.php`, `bin/test-track-diff.php`, `bin/test-track-store.php` | Each holds its class to the new rules. |
| `readme.txt`, `languages/wpcredits-program-manager.pot` | The release. |

---

### Task 1: The Learn client

**Files:**
- Create: `includes/class-wpcpm-learn.php`, `bin/fixtures/learn-courses-wordpress-credits.json` (Learn's answer to the 150-hour course's slug, trimmed to the fields the client reads), `bin/fixtures/learn-structure-403425.json` (the Designer course's structure as Learn answered it, under `response`)
- Modify: `wpcredits-program-manager.php`, `uninstall.php` (the new class is required in both, after `class-wpcpm-airtable.php`)
- Test: `bin/test-learn.php`

**Interfaces:**
- Consumes: `wp_remote_get()`, `wp_remote_retrieve_response_code()`, `wp_remote_retrieve_body()`, `get_transient()`, `set_transient()`, `delete_transient()`, `DAY_IN_SECONDS`, `WP_Error` and `WPCPM_Track_Definition::GROUPS`. The suite stands every one of them in and answers the client's requests from a `$GLOBALS['http']` table keyed by URL, counting each ask in `$GLOBALS['asked']`; its `answer( $name, $code = 200 )` helper builds one answer from a fixture, and `COURSES` and `STRUCTURE` name the two URLs the client asks.
- Produces: `final class WPCPM_Learn` with `API` (`https://learn.wordpress.org/wp-json/`), `TTL` (`DAY_IN_SECONDS`) and `COURSE_LINK` (the course link shape `validate()` requires, its slug captured); `slug( $url )`, the slug or ''; `resolve( $url )`, `array( 'id', 'slug', 'title' )` or a `WP_Error` of `wpcpm_learn_not_a_course`, `wpcpm_learn_no_course`, `wpcpm_learn_unreachable` or `wpcpm_learn_bad_answer`, the answer kept a day under `wpcpm_learn_course_<slug>`; `structure( $course_id )`, a list of modules each `id`, `title` and `lessons` (each `id` and `title`) in Learn's order, a draft lesson left out, a lesson outside every module under a module with id 0 and no title at the end, kept a day under `wpcpm_learn_structure_<id>`, or `wpcpm_learn_unreachable` or `wpcpm_learn_bad_answer`; `forget( $course_id, $url = '' )`, which drops both; and the pure `parse_course( $data )`, `parse_structure( $data )`, `group_of( $title )` (one of `GROUPS`, or '') and `by_group( array $modules )` (group => that module's lessons; a module that is no group is dropped, a group with no module is absent). A failed read is never stored.

Decision 30's client, shaped like `WPCPM_Airtable` with no screen in it, and the only file of this plan that talks to anything outside the site. Two public endpoints answer everything the Track Builder needs (the design's 2.6): the course by its slug from the REST API, and the modules and lessons that Sensei publishes without authentication. Each answer is kept in a transient for a day, because a course's lessons change rarely and a track's page is opened often; a failed read is never kept, so the next open tries again; and `forget()` drops both for the day a lesson is added on Learn (what this plan decides, 1 and 2).

The parsing is pure and is proven on answers Learn actually gave: the Designer course's 39 published lessons in its three modules, with their titles' entities decoded so a heading on the form gets the characters, and the 150-hour course's slug answered as one course. A lesson in draft is left out because no student sees it, and a lesson outside every module is kept rather than lost (what this plan decides, 3). The suite drives the real class and stands in nothing but WordPress: the HTTP is answered from a table by URL and counted, which is how the day's cache and the uncached failure are proven, since a second `resolve()` asks nothing and a `WP_Error` is asked again.

`bin/test-roles.php` walks `includes/` and fails for any class file the loader does not require, which is why the loader and `uninstall.php` are in this task.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/fixtures/learn-courses-wordpress-credits.json b/bin/fixtures/learn-courses-wordpress-credits.json
new file mode 100644
index 0000000..180bd57
--- /dev/null
+++ b/bin/fixtures/learn-courses-wordpress-credits.json
@@ -0,0 +1,15 @@
+{
+ "_comment": "Learn's answer to GET https://learn.wordpress.org/wp-json/wp/v2/courses?slug=wordpress-credits, captured 13 September 2026 and trimmed to the keys WPCPM_Learn reads (id, slug, status, link, title, type); the full answer carries the course's content as well. bin/test-learn.php parses it.",
+ "response": [
+  {
+   "id": 297853,
+   "slug": "wordpress-credits",
+   "status": "publish",
+   "type": "course",
+   "link": "https://learn.wordpress.org/course/wordpress-credits/",
+   "title": {
+    "rendered": "WordPress Credits"
+   }
+  }
+ ]
+}
diff --git a/bin/fixtures/learn-structure-403425.json b/bin/fixtures/learn-structure-403425.json
new file mode 100644
index 0000000..858dfc3
--- /dev/null
+++ b/bin/fixtures/learn-structure-403425.json
@@ -0,0 +1,353 @@
+{
+ "_comment": "Learn's answer to GET https://learn.wordpress.org/wp-json/sensei-internal/v1/course-structure/403425, the Designer Track course, captured 13 September 2026 and kept whole: three modules, each with its lessons as Learn lists them (id, title, draft, preview, type, initialContent). bin/test-learn.php parses it.",
+ "response": [
+  {
+   "type": "module",
+   "id": 115363,
+   "title": "Onboarding",
+   "description": "Before you can contribute to WordPress, you need to know how the community works, where to find your people, and how to show up well. This module gets you set up: a WordPress.org profile, access to Slack, a shared understanding of how decisions get made in this open source project, and a first look at the design tools you'll be using throughout the program.You'll also build your own WordPress site. This becomes your portfolio, your testing ground for design experiments, and the place where you'll document your progress for the rest of the course.By the end of this module, you'll be oriented in the WordPress community and have a personal site ready to build on. From here, you'll move into the Project module, where you apply what you've learned toward a real contribution to the WordPress Design team.",
+   "teacher": "",
+   "teacherId": 18873666,
+   "lastTitle": "Onboarding",
+   "slug": "",
+   "lessons": [
+    {
+     "type": "lesson",
+     "id": 403435,
+     "title": "Welcome and Essential Communication Guidelines",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403437,
+     "title": "Share your WordPress profile",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403439,
+     "title": "Join global Slack",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403441,
+     "title": "Differences between WordPress.com and WordPress.org",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403443,
+     "title": "Complete: Open source basics and WordPress",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403445,
+     "title": "Complete: How decisions are made in the WordPress project",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403447,
+     "title": "Complete: Community meeting etiquette",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403449,
+     "title": "Complete: Writing in the WordPress voice",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403451,
+     "title": "Complete: Basic principles of conflict resolution",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403453,
+     "title": "Complete: Beginner WordPress Designer Course",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403455,
+     "title": "From design skills to contribution",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403457,
+     "title": "Create your portfolio",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403459,
+     "title": "Reflection: Building Your Portfolio",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    }
+   ]
+  },
+  {
+   "type": "module",
+   "id": 115364,
+   "title": "Project",
+   "description": "The Project module is where WP Credits Designer track students move from getting set up to doing real design contribution work. Building on the WordPress fundamentals from Onboarding, this module introduces how the WordPress Design team works, the tools they use, and the different paths students can take to contribute, then gives students hands-on practice through a progression of Practical lessons before choosing and beginning their own contribution project.This module connects the design skills students bring (or are building) to the specific ways WordPress needs design contributions today: Figma and the Design Library, block patterns, the Photo Directory, theme reviews, workshop and meetup materials, and Gutenberg design feedback.",
+   "teacher": "",
+   "teacherId": 18873666,
+   "lastTitle": "Project",
+   "slug": "",
+   "lessons": [
+    {
+     "type": "lesson",
+     "id": 403461,
+     "title": "How to contribute to WordPress",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403463,
+     "title": "The WordPress Design Team Deep Dive",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403465,
+     "title": "Introduction to Figma for WordPress Design",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403467,
+     "title": "Design Contribution Pathways for Students",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403469,
+     "title": "Understand WordPress Design Principles",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403471,
+     "title": "Practical: Duplicate and Explore the WordPress Design Library",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403473,
+     "title": "Practical: Set Up a Local WordPress Environment for Design Testing",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403475,
+     "title": "Explore and practice WordPress design",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403477,
+     "title": "Practical: Change Your Site's Global Styles",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403479,
+     "title": "Practical: Customize with the Style Book",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403481,
+     "title": "Practical: Compose a Landing Page with Layout Blocks",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403483,
+     "title": "Practical: Apply Custom CSS in the Site Editor",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403485,
+     "title": "Contribute to a real project",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403487,
+     "title": "Practical: Create and Submit a Custom Block Pattern",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403489,
+     "title": "Practical: Test Your Site for Accessibility",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403563,
+     "title": "Define and begin developing your contribution project",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403565,
+     "title": "Reflection: Choosing Your Team and Project",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403491,
+     "title": "Alumni Program: Connect with the community and plan your contribution beyond WP Credits",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403493,
+     "title": "Complete the first feedback form",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403495,
+     "title": "Reflection: Your First Contribution",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403497,
+     "title": "Leave your mid-course feedback",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403499,
+     "title": "Reflection: Halfway Check-In",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403501,
+     "title": "Participate at a WordPress Event (online or in person)",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    }
+   ]
+  },
+  {
+   "type": "module",
+   "id": 115365,
+   "title": "Wrap-up",
+   "description": "Wrap-up is where you close out your WP Credits journey. You'll write and deliver a report on the contribution work you've done, receive your certificate, and give feedback that helps shape the program for future cohorts.This module is short by design. The real work happened in Onboarding, where you set up your portfolio and got oriented in the WordPress community, and in Project, where you built design skills and made real contributions to the WordPress Design and Test teams. Wrap-up is where you step back, reflect on that work, and formally close the loop.",
+   "teacher": "",
+   "teacherId": 18873666,
+   "lastTitle": "Wrap-up",
+   "slug": "",
+   "lessons": [
+    {
+     "type": "lesson",
+     "id": 403503,
+     "title": "Prepare and deliver a wrap-up report",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403505,
+     "title": "Get your certificate",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    },
+    {
+     "type": "lesson",
+     "id": 403507,
+     "title": "Complete the feedback form",
+     "draft": false,
+     "preview": false,
+     "initialContent": ""
+    }
+   ]
+  }
+ ]
+}
diff --git a/bin/test-learn.php b/bin/test-learn.php
new file mode 100644
index 0000000..d03b178
--- /dev/null
+++ b/bin/test-learn.php
@@ -0,0 +1,215 @@
+<?php
+/**
+ * WPCPM_Learn: the course resolved from its link and the course's modules and lessons, parsed from
+ * answers Learn actually gave, cached for a day, refreshed on demand (the design's decision 30).
+ *
+ * WordPress is stood in; Learn's HTTP is a table of answers keyed by the address asked, so a check
+ * can see what was asked, what was cached, and that a failure was not.
+ *
+ * Run: php bin/test-learn.php
+ *
+ * @package WPCredits_Program_Manager
+ */
+
+define( 'ABSPATH', '/' );
+define( 'DAY_IN_SECONDS', 86400 );
+
+function __( $s, $d = null ) { return $s; }
+
+class WP_Error {
+	public $code;
+	public $message;
+	public $data;
+	public function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
+	public function get_error_code() { return $this->code; }
+	public function get_error_message() { return $this->message; }
+}
+
+function is_wp_error( $t ) { return $t instanceof WP_Error; }
+
+$GLOBALS['transients'] = array();
+$GLOBALS['ttl']        = array();
+$GLOBALS['http']       = array();
+$GLOBALS['asked']      = array();
+
+function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $k ] : false; }
+function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['transients'][ $k ] = $v; $GLOBALS['ttl'][ $k ] = $ttl; return true; }
+function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ], $GLOBALS['ttl'][ $k ] ); return true; }
+
+// Learn, as a table of answers by address. An address with no answer is a network failure, the
+// shape `wp_remote_get()` gives when nothing answers at all.
+function wp_remote_get( $url, $args = array() ) {
+	$GLOBALS['asked'][] = $url;
+
+	return array_key_exists( $url, $GLOBALS['http'] ) ? $GLOBALS['http'][ $url ] : new WP_Error( 'http_request_failed', 'cURL error 28: Connection timed out' );
+}
+
+function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? ( $r['response']['code'] ?? 200 ) : 0; }
+function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? ( $r['body'] ?? '' ) : ''; }
+
+require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';
+require_once __DIR__ . '/../includes/class-wpcpm-learn.php';
+
+$total = 0;
+$fail  = 0;
+
+function ck( $label, $actual, $expected ) {
+	global $total, $fail;
+	++$total;
+
+	if ( $actual === $expected ) {
+		echo "ok   $label\n";
+
+		return;
+	}
+
+	++$fail;
+	echo "FAIL $label\n       expected: " . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
+}
+
+/**
+ * A captured answer from bin/fixtures, as `wp_remote_get()` would hand it over.
+ *
+ * @param string $name The fixture file.
+ * @param int    $code The HTTP status to answer with.
+ * @return array
+ */
+function answer( $name, $code = 200 ) {
+	$captured = json_decode( file_get_contents( __DIR__ . '/fixtures/' . $name ), true );
+
+	return array( 'response' => array( 'code' => $code ), 'body' => json_encode( $captured['response'] ) );
+}
+
+const COURSES   = 'https://learn.wordpress.org/wp-json/wp/v2/courses?slug=wordpress-credits&_fields=id,slug,status,link,title';
+const STRUCTURE = 'https://learn.wordpress.org/wp-json/sensei-internal/v1/course-structure/403425';
+
+echo "=== The link ===\n";
+
+ck( 'a course link gives its slug', WPCPM_Learn::slug( 'https://learn.wordpress.org/course/wordpress-credits/' ), 'wordpress-credits' );
+ck( 'with or without the trailing slash', WPCPM_Learn::slug( 'https://learn.wordpress.org/course/wordpress-credits' ), 'wordpress-credits' );
+ck( 'a lesson link is not a course', WPCPM_Learn::slug( 'https://learn.wordpress.org/lesson/join-global-slack/' ), '' );
+ck( 'nor another host, nor http', array( WPCPM_Learn::slug( 'https://example.test/course/x/' ), WPCPM_Learn::slug( 'http://learn.wordpress.org/course/x/' ) ), array( '', '' ) );
+
+echo "\n=== Resolving a course ===\n";
+
+$refused = WPCPM_Learn::resolve( 'https://learn.wordpress.org/lesson/join-global-slack/' );
+
+ck( 'a link that is not a course link is refused without asking Learn',
+    array( is_wp_error( $refused ) ? $refused->get_error_code() : null, $GLOBALS['asked'] ), array( 'wpcpm_learn_not_a_course', array() ) );
+
+$GLOBALS['http'][ COURSES ] = answer( 'learn-courses-wordpress-credits.json' );
+$course = WPCPM_Learn::resolve( 'https://learn.wordpress.org/course/wordpress-credits/' );
+
+ck( 'a course link resolves to the course, asked by slug with only the fields read',
+    array( $course, $GLOBALS['asked'] ),
+    array( array( 'id' => 297853, 'slug' => 'wordpress-credits', 'title' => 'WordPress Credits' ), array( COURSES ) ) );
+ck( 'and the answer is kept for a day, under the slug',
+    array( $GLOBALS['transients']['wpcpm_learn_course_wordpress-credits'], $GLOBALS['ttl']['wpcpm_learn_course_wordpress-credits'] ),
+    array( $course, 86400 ) );
+
+$GLOBALS['asked'] = array();
+$again            = WPCPM_Learn::resolve( 'https://learn.wordpress.org/course/wordpress-credits' );
+
+ck( 'a second resolve within the day asks Learn nothing', array( $again, $GLOBALS['asked'] ), array( $course, array() ) );
+
+$GLOBALS['transients'] = array();
+$GLOBALS['http'][ COURSES ] = array( 'response' => array( 'code' => 200 ), 'body' => '[]' );
+$none = WPCPM_Learn::resolve( 'https://learn.wordpress.org/course/wordpress-credits/' );
+
+ck( 'no course at that address is said so, and nothing is cached',
+    array( is_wp_error( $none ) ? $none->get_error_code() : null, $GLOBALS['transients'] ), array( 'wpcpm_learn_no_course', array() ) );
+
+unset( $GLOBALS['http'][ COURSES ] );
+$down = WPCPM_Learn::resolve( 'https://learn.wordpress.org/course/wordpress-credits/' );
+
+ck( 'Learn not answering is said so, with what went wrong, and nothing is cached',
+    array( is_wp_error( $down ) ? $down->get_error_code() : null, false !== strpos( $down->get_error_message(), 'timed out' ), $GLOBALS['transients'] ),
+    array( 'wpcpm_learn_unreachable', true, array() ) );
+
+$GLOBALS['http'][ COURSES ] = array( 'response' => array( 'code' => 500 ), 'body' => 'Internal Server Error' );
+$broken = WPCPM_Learn::resolve( 'https://learn.wordpress.org/course/wordpress-credits/' );
+
+ck( 'a broken answer is unreachable too', is_wp_error( $broken ) ? $broken->get_error_code() : null, 'wpcpm_learn_unreachable' );
+
+$GLOBALS['http'][ COURSES ] = array( 'response' => array( 'code' => 200 ), 'body' => '[{"id":"x"}]' );
+$odd = WPCPM_Learn::resolve( 'https://learn.wordpress.org/course/wordpress-credits/' );
+
+ck( 'and an answer without an id and a title is a bad answer', is_wp_error( $odd ) ? $odd->get_error_code() : null, 'wpcpm_learn_bad_answer' );
+
+$GLOBALS['http'][ COURSES ] = array( 'response' => array( 'code' => 200 ), 'body' => '[{"id":7,"slug":"wordpress-credits","title":{"rendered":"Design &amp; Build &#8217;26"}}]' );
+
+ck( 'a title comes back as words, its entities decoded', WPCPM_Learn::resolve( 'https://learn.wordpress.org/course/wordpress-credits/' )['title'], "Design & Build \u{2019}26" );
+
+echo "\n=== The course's modules and lessons ===\n";
+
+$GLOBALS['transients'] = array();
+$GLOBALS['asked']      = array();
+$GLOBALS['http'][ STRUCTURE ] = answer( 'learn-structure-403425.json' );
+$modules = WPCPM_Learn::structure( 403425 );
+
+ck( 'the Designer course has its three modules, in order, with their lessons counted',
+    array( $GLOBALS['asked'], array_map( function ( $m ) { return array( $m['id'], $m['title'], count( $m['lessons'] ) ); }, $modules ) ),
+    array( array( STRUCTURE ), array( array( 115363, 'Onboarding', 13 ), array( 115364, 'Project', 23 ), array( 115365, 'Wrap-up', 3 ) ) ) );
+ck( 'each lesson is its id and its title, nothing else',
+    array( $modules[0]['lessons'][0], array_keys( $modules[2]['lessons'][2] ) ),
+    array( array( 'id' => 403435, 'title' => 'Welcome and Essential Communication Guidelines' ), array( 'id', 'title' ) ) );
+ck( 'and the answer is kept for a day, under the course id',
+    array( $GLOBALS['transients']['wpcpm_learn_structure_403425'] === $modules, $GLOBALS['ttl']['wpcpm_learn_structure_403425'] ), array( true, 86400 ) );
+
+$GLOBALS['asked'] = array();
+WPCPM_Learn::structure( 403425 );
+
+ck( 'a second read within the day asks Learn nothing', $GLOBALS['asked'], array() );
+
+// A lesson still in draft on Learn is invisible to a student, so it is not offered; and a lesson
+// Learn lists outside any module keeps its place under a module with no name.
+$captured = json_decode( file_get_contents( __DIR__ . '/fixtures/learn-structure-403425.json' ), true )['response'];
+$captured[0]['lessons'][1]['draft'] = true;
+$captured[] = array( 'type' => 'lesson', 'id' => 900001, 'title' => 'A lesson on its own', 'draft' => false );
+$GLOBALS['transients'] = array();
+$GLOBALS['http'][ STRUCTURE ] = array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $captured ) );
+$modules = WPCPM_Learn::structure( 403425 );
+
+ck( 'a draft lesson is left out, and a lesson outside every module is kept under a module with no name',
+    array( count( $modules[0]['lessons'] ), $modules[0]['lessons'][1]['id'], end( $modules ) ),
+    array( 12, 403439, array( 'id' => 0, 'title' => '', 'lessons' => array( array( 'id' => 900001, 'title' => 'A lesson on its own' ) ) ) ) );
+
+$GLOBALS['transients'] = array();
+unset( $GLOBALS['http'][ STRUCTURE ] );
+$down = WPCPM_Learn::structure( 403425 );
+
+ck( 'Learn not answering for the structure is said so, and nothing is cached',
+    array( is_wp_error( $down ) ? $down->get_error_code() : null, $GLOBALS['transients'] ), array( 'wpcpm_learn_unreachable', array() ) );
+
+$GLOBALS['http'][ STRUCTURE ] = array( 'response' => array( 'code' => 200 ), 'body' => '{"code":"rest_no_route"}' );
+
+ck( 'an answer that is not a list is a bad answer', WPCPM_Learn::structure( 403425 )->get_error_code(), 'wpcpm_learn_bad_answer' );
+
+$GLOBALS['http'][ STRUCTURE ] = array( 'response' => array( 'code' => 200 ), 'body' => '[]' );
+
+ck( 'a course with no modules is an empty list, not an error', WPCPM_Learn::structure( 403425 ), array() );
+
+echo "\n=== Forgetting, and the groups ===\n";
+
+$GLOBALS['transients'] = array( 'wpcpm_learn_course_wordpress-credits' => array( 'id' => 297853 ), 'wpcpm_learn_structure_297853' => array(), 'wpcpm_learn_structure_403425' => array(), 'other' => 1 );
+WPCPM_Learn::forget( 297853, 'https://learn.wordpress.org/course/wordpress-credits/' );
+
+ck( 'forget drops the course by its link and the structure by its id, and nothing else',
+    array_keys( $GLOBALS['transients'] ), array( 'wpcpm_learn_structure_403425', 'other' ) );
+
+WPCPM_Learn::forget( 403425 );
+
+ck( 'and drops the structure alone when no link is given', array_keys( $GLOBALS['transients'] ), array( 'other' ) );
+
+ck( "a module's title names the form's group it is, once case and punctuation are folded",
+    array( WPCPM_Learn::group_of( 'Onboarding' ), WPCPM_Learn::group_of( 'Project' ), WPCPM_Learn::group_of( 'Wrap-up' ), WPCPM_Learn::group_of( 'wrap up' ), WPCPM_Learn::group_of( 'Extras' ), WPCPM_Learn::group_of( '' ) ),
+    array( 'onboarding', 'project', 'wrapup', 'wrapup', '', '' ) );
+
+$by_group = WPCPM_Learn::by_group( $modules );
+
+ck( 'the lessons by group leave out a module that is no group, and the hours group has none',
+    array( array_keys( $by_group ), count( $by_group['onboarding'] ), count( $by_group['project'] ), count( $by_group['wrapup'] ) ),
+    array( array( 'onboarding', 'project', 'wrapup' ), 12, 23, 3 ) );
+
+printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
+exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-learn.php`

Expected: `bin/test-learn.php` stops with `Fatal error: Uncaught Error: Failed opening required 'includes/class-wpcpm-learn.php'`. The class file does not exist yet, so the suite's `require_once` fails. `bin/test-roles.php` still passes at this point, since it fails only for a class file on disk that the loader does not require, and the file is not on disk until Step 3 (corrected in execution: the plan said it failed here too).

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/class-wpcpm-learn.php b/includes/class-wpcpm-learn.php
new file mode 100644
index 0000000..6a0a508
--- /dev/null
+++ b/includes/class-wpcpm-learn.php
@@ -0,0 +1,325 @@
+<?php
+/**
+ * What Learn WordPress says about a course: the course behind a link, and its modules and lessons.
+ *
+ * @package WPCredits_Program_Manager
+ */
+
+if ( ! defined( 'ABSPATH' ) ) {
+	exit;
+}
+
+/**
+ * The Learn client, with no screen in it (the design's decision 30).
+ *
+ * Two public, unauthenticated endpoints answer everything the Track Builder needs: the course by
+ * its slug (`wp-json/wp/v2/courses?slug=`) and the course's modules and lessons
+ * (`sensei-internal/v1/course-structure/<id>`). Each answer is kept for a day, since a course's
+ * lessons change rarely and a track's page is opened often; a failed read is not kept, so the next
+ * open tries again; and `forget()` drops both for the day a lesson is added on Learn. The parsing
+ * is pure and proven on answers Learn actually gave (bin/fixtures/learn-*.json).
+ */
+final class WPCPM_Learn {
+
+	/** Where Learn's REST API answers. */
+	const API = 'https://learn.wordpress.org/wp-json/';
+
+	/** How long an answer is kept. */
+	const TTL = DAY_IN_SECONDS;
+
+	/** A course link, as `WPCPM_Track_Definition::validate()` requires it, with its slug captured. */
+	const COURSE_LINK = '#^https://learn\.wordpress\.org/course/([a-z0-9-]+)/?$#';
+
+	/**
+	 * The slug of a course link, or nothing for any other address.
+	 *
+	 * @param string $url The link.
+	 * @return string
+	 */
+	public static function slug( $url ) {
+		return 1 === preg_match( self::COURSE_LINK, (string) $url, $m ) ? $m[1] : '';
+	}
+
+	/**
+	 * The course behind a link.
+	 *
+	 * @param string $url A `learn.wordpress.org/course/<slug>/` link.
+	 * @return array|WP_Error `id`, `slug` and `title`; or `wpcpm_learn_not_a_course` for a link of
+	 *                        another shape, `wpcpm_learn_no_course` when Learn has no course at
+	 *                        that address, `wpcpm_learn_unreachable` when Learn did not answer, and
+	 *                        `wpcpm_learn_bad_answer` when it answered something else.
+	 */
+	public static function resolve( $url ) {
+		$slug = self::slug( $url );
+
+		if ( '' === $slug ) {
+			return new WP_Error( 'wpcpm_learn_not_a_course', __( 'That is not the address of a Learn WordPress course: it should look like https://learn.wordpress.org/course/its-name/.', 'wpcredits-program-manager' ) );
+		}
+
+		$key  = 'wpcpm_learn_course_' . $slug;
+		$held = get_transient( $key );
+
+		if ( is_array( $held ) ) {
+			return $held;
+		}
+
+		$data = self::get( 'wp/v2/courses?slug=' . rawurlencode( $slug ) . '&_fields=id,slug,status,link,title' );
+
+		if ( is_wp_error( $data ) ) {
+			return $data;
+		}
+
+		$course = self::parse_course( $data );
+
+		if ( is_array( $course ) ) {
+			set_transient( $key, $course, self::TTL );
+		}
+
+		return $course;
+	}
+
+	/**
+	 * A course's modules, each with its published lessons.
+	 *
+	 * @param int $course_id The course's post ID on Learn.
+	 * @return array|WP_Error A list of modules, each `id`, `title` and `lessons` (each `id` and
+	 *                        `title`), in Learn's order; a lesson outside every module sits in a
+	 *                        module with id 0 and no title, at the end. Or `wpcpm_learn_unreachable`
+	 *                        or `wpcpm_learn_bad_answer`.
+	 */
+	public static function structure( $course_id ) {
+		$course_id = (int) $course_id;
+		$key       = 'wpcpm_learn_structure_' . $course_id;
+		$held      = get_transient( $key );
+
+		if ( is_array( $held ) ) {
+			return $held;
+		}
+
+		$data = self::get( 'sensei-internal/v1/course-structure/' . $course_id );
+
+		if ( is_wp_error( $data ) ) {
+			return $data;
+		}
+
+		$modules = self::parse_structure( $data );
+
+		if ( is_array( $modules ) ) {
+			set_transient( $key, $modules, self::TTL );
+		}
+
+		return $modules;
+	}
+
+	/**
+	 * Drop what is kept about a course, so the next read asks Learn again.
+	 *
+	 * @param int    $course_id The course's post ID on Learn, for its structure.
+	 * @param string $url       Its link, when known, for the course itself.
+	 */
+	public static function forget( $course_id, $url = '' ) {
+		delete_transient( 'wpcpm_learn_structure_' . (int) $course_id );
+
+		$slug = self::slug( $url );
+
+		if ( '' !== $slug ) {
+			delete_transient( 'wpcpm_learn_course_' . $slug );
+		}
+	}
+
+	/**
+	 * The course out of Learn's answer to a search by slug.
+	 *
+	 * @param mixed $data The decoded answer: a list of courses, one at most.
+	 * @return array|WP_Error `id`, `slug` and `title`, the title's entities decoded.
+	 */
+	public static function parse_course( $data ) {
+		if ( ! is_array( $data ) ) {
+			return new WP_Error( 'wpcpm_learn_bad_answer', __( 'Learn answered something that is not a list of courses.', 'wpcredits-program-manager' ) );
+		}
+
+		if ( array() === $data ) {
+			return new WP_Error( 'wpcpm_learn_no_course', __( 'Learn has no course at that address.', 'wpcredits-program-manager' ) );
+		}
+
+		$course = reset( $data );
+		$title  = is_array( $course ) && isset( $course['title']['rendered'] ) ? $course['title']['rendered'] : null;
+
+		if ( ! is_array( $course ) || ! isset( $course['id'] ) || ! is_int( $course['id'] ) || ! is_string( $title ) ) {
+			return new WP_Error( 'wpcpm_learn_bad_answer', __( 'Learn answered a course without an id or a title.', 'wpcredits-program-manager' ) );
+		}
+
+		return array(
+			'id'    => $course['id'],
+			'slug'  => isset( $course['slug'] ) ? (string) $course['slug'] : '',
+			'title' => self::words( $title ),
+		);
+	}
+
+	/**
+	 * The modules and lessons out of Learn's course structure answer.
+	 *
+	 * A lesson in draft is left out: no student sees it. A lesson listed outside every module is
+	 * kept, under a module with id 0 and no title at the end, so nothing Learn lists is lost.
+	 *
+	 * @param mixed $data The decoded answer: a list of modules and lessons.
+	 * @return array|WP_Error
+	 */
+	public static function parse_structure( $data ) {
+		if ( ! is_array( $data ) || array_values( $data ) !== $data ) {
+			return new WP_Error( 'wpcpm_learn_bad_answer', __( 'Learn answered something that is not a course structure.', 'wpcredits-program-manager' ) );
+		}
+
+		$modules = array();
+		$loose   = array();
+
+		foreach ( $data as $entry ) {
+			if ( ! is_array( $entry ) || ! isset( $entry['type'] ) ) {
+				continue;
+			}
+
+			if ( 'module' === $entry['type'] ) {
+				$modules[] = array(
+					'id'      => isset( $entry['id'] ) ? (int) $entry['id'] : 0,
+					'title'   => isset( $entry['title'] ) ? self::words( $entry['title'] ) : '',
+					'lessons' => self::lessons( isset( $entry['lessons'] ) && is_array( $entry['lessons'] ) ? $entry['lessons'] : array() ),
+				);
+			} elseif ( 'lesson' === $entry['type'] ) {
+				$loose[] = $entry;
+			}
+		}
+
+		if ( array() !== $loose ) {
+			$lessons = self::lessons( $loose );
+
+			if ( array() !== $lessons ) {
+				$modules[] = array(
+					'id'      => 0,
+					'title'   => '',
+					'lessons' => $lessons,
+				);
+			}
+		}
+
+		return $modules;
+	}
+
+	/**
+	 * The form's group a module is, or nothing: Learn's three modules are the form's own groups
+	 * (the design's 2.6), matched once case and punctuation are folded.
+	 *
+	 * @param string $title The module's title.
+	 * @return string One of `WPCPM_Track_Definition::GROUPS`, or ''.
+	 */
+	public static function group_of( $title ) {
+		$folded = strtolower( preg_replace( '/[^a-z]/i', '', (string) $title ) );
+
+		return in_array( $folded, WPCPM_Track_Definition::GROUPS, true ) ? $folded : '';
+	}
+
+	/**
+	 * A course's lessons by the form's group, for the track's page: a module that is no group is
+	 * left out, and a group with no module is absent.
+	 *
+	 * @param array $modules What `structure()` answered.
+	 * @return array Group => a list of lessons, each `id` and `title`.
+	 */
+	public static function by_group( array $modules ) {
+		$by_group = array();
+
+		foreach ( $modules as $module ) {
+			$group = isset( $module['title'] ) ? self::group_of( $module['title'] ) : '';
+
+			if ( '' === $group ) {
+				continue;
+			}
+
+			if ( ! isset( $by_group[ $group ] ) ) {
+				$by_group[ $group ] = array();
+			}
+
+			foreach ( isset( $module['lessons'] ) && is_array( $module['lessons'] ) ? $module['lessons'] : array() as $lesson ) {
+				$by_group[ $group ][] = $lesson;
+			}
+		}
+
+		return $by_group;
+	}
+
+	/**
+	 * The published lessons of a list, each as its id and its title.
+	 *
+	 * @param array $entries Lesson entries as Learn lists them.
+	 * @return array[]
+	 */
+	private static function lessons( array $entries ) {
+		$lessons = array();
+
+		foreach ( $entries as $entry ) {
+			if ( ! is_array( $entry ) || ! isset( $entry['id'] ) || ! empty( $entry['draft'] ) ) {
+				continue;
+			}
+
+			$lessons[] = array(
+				'id'    => (int) $entry['id'],
+				'title' => isset( $entry['title'] ) ? self::words( $entry['title'] ) : '',
+			);
+		}
+
+		return $lessons;
+	}
+
+	/**
+	 * A title as words: Learn renders titles with entities, and a heading on the form wants the
+	 * characters.
+	 *
+	 * @param mixed $title The rendered title.
+	 * @return string
+	 */
+	private static function words( $title ) {
+		return trim( html_entity_decode( (string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
+	}
+
+	/**
+	 * One read of Learn's API, decoded.
+	 *
+	 * @param string $path The path under the API root, query included.
+	 * @return mixed|WP_Error The decoded JSON, or `wpcpm_learn_unreachable`.
+	 */
+	private static function get( $path ) {
+		$response = wp_remote_get(
+			self::API . $path,
+			array(
+				'timeout'     => 10,
+				'redirection' => 3,
+			)
+		);
+
+		if ( is_wp_error( $response ) ) {
+			return new WP_Error(
+				'wpcpm_learn_unreachable',
+				sprintf(
+					/* translators: %s: what the HTTP client reported. */
+					__( 'Learn WordPress did not answer: %s', 'wpcredits-program-manager' ),
+					$response->get_error_message()
+				)
+			);
+		}
+
+		$code = (int) wp_remote_retrieve_response_code( $response );
+		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
+
+		if ( $code < 200 || $code >= 300 || null === $data ) {
+			return new WP_Error(
+				'wpcpm_learn_unreachable',
+				sprintf(
+					/* translators: %d: an HTTP status code. */
+					__( 'Learn WordPress answered with status %d instead of the course.', 'wpcredits-program-manager' ),
+					$code
+				)
+			);
+		}
+
+		return $data;
+	}
+}
diff --git a/uninstall.php b/uninstall.php
index d28f2bb..d5ff0f5 100644
--- a/uninstall.php
+++ b/uninstall.php
@@ -24,6 +24,7 @@ if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
 require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-roles.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-settings.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-airtable.php';
+require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-learn.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-content-access.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-privacy-guard.php';
 require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-wporg-profile.php';
diff --git a/wpcredits-program-manager.php b/wpcredits-program-manager.php
index 807fb31..cb90a4d 100644
--- a/wpcredits-program-manager.php
+++ b/wpcredits-program-manager.php
@@ -27,6 +27,7 @@ define( 'WPCPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
+require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-learn.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-content-access.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-privacy-guard.php';
 require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-wporg-profile.php';
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-learn.php`

Expected: `bin/test-learn.php` ends `ALL PASS (25 checks)`. `php bin/test-roles.php` ends `ALL PASS` as well.

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
git add bin/fixtures/learn-courses-wordpress-credits.json bin/fixtures/learn-structure-403425.json bin/test-learn.php includes/class-wpcpm-learn.php uninstall.php wpcredits-program-manager.php
git commit -m "Track Builder T3c: the Learn client"
```

---

### Task 2: A question under a lesson, and lessons matched again

**Files:**
- Modify: `includes/tracks/class-wpcpm-track-questions.php`
- Test: `bin/test-track-questions.php` (it reads the four captured courses in `bin/fixtures/learn-course-<id>.json`, which T2a's seeds were built from)

**Interfaces:**
- Consumes: the map shape T3a set, column => spec in order, and `WPCPM_Track_Definition::GROUPS` for the group rule `add()` already applies.
- Produces: `WPCPM_Track_Questions::add( array $questions, $column, array $question, $after = '' )`, the map with the question after the column `$after` names when the map holds it, else after the last question of its group, or null when the column is already used; `last_of_lesson( array $questions, $lesson_id )`, the column of the last question whose `learn_lesson_id` is the lesson, or '' when none reports on it; `rematch( array $questions, array $lessons )`, given the new course's lessons (each `id` and `title`, every module together), answering `questions` (the map in the same order, a matched question carrying the new lesson's id, an unmatched one carrying none), `matched` and `cleared` (column lists); and the private `fold()` they compare through: lower case, apostrophes gone, one space between words.

The two pure rules of decisions 32 and 33, in the class T3a made for the question list's rules, so the builder's handlers stay thin and the suite needs no WordPress. `add()` grows one optional argument rather than a second method: a question under a lesson is an ordinary Add with one more thing known about where it goes, and the fallback to the group rule means a stale `$after` can never lose a question (what this plan decides, 4). `last_of_lesson()` answers both questions the editor will ask, where the new question goes and whether the lesson has any question yet, since the lesson's title becomes a `lead` only on its first.

`rematch()` compares a question's `lead`, or its `subgroup` when it has no lead, since the Designer Track's form holds one lesson's title in a subgroup, and folds both sides the way 2.6 counted its matches, exact once case and apostrophes are folded (what this plan decides, 5). It is proven on the captured Designer course: the seeded form's lesson headings match exactly once folded and keep or take the lesson's id, a heading of the program's own is cleared and named, and a question with no lesson is left alone whatever its heading says.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-questions.php b/bin/test-track-questions.php
index 70880b6..f42b914 100644
--- a/bin/test-track-questions.php
+++ b/bin/test-track-questions.php
@@ -241,6 +241,67 @@ ck( 'and a track never published locks nothing',
     WPCPM_Track_Questions::locked( 'Slack name', array() ),
     false );
 
+
+echo "\n=== Under a lesson (T3c) ===\n";
+
+// Two questions of one lesson, then one of another, all in the project group, and one in wrap-up.
+$lessoned = array(
+	'Hours'    => array( 'type' => 'number', 'group' => 'hours' ),
+	'Figma'    => array( 'type' => 'url', 'group' => 'project', 'lead' => 'Introduction to Figma', 'learn_lesson_id' => 403465 ),
+	'Figma 2'  => array( 'type' => 'text', 'group' => 'project', 'learn_lesson_id' => 403465 ),
+	'Library'  => array( 'type' => 'url', 'group' => 'project', 'lead' => 'Explore the library', 'learn_lesson_id' => 403471 ),
+	'Feedback' => array( 'type' => 'textarea', 'group' => 'wrapup', 'learn_lesson_id' => 403507 ),
+);
+
+ck( "the last question of a lesson is the one a new question of that lesson goes after",
+    array( WPCPM_Track_Questions::last_of_lesson( $lessoned, 403465 ), WPCPM_Track_Questions::last_of_lesson( $lessoned, 403471 ), WPCPM_Track_Questions::last_of_lesson( $lessoned, 403507 ) ),
+    array( 'Figma 2', 'Library', 'Feedback' ) );
+ck( 'a lesson with no question yet has no last question, and neither has no lesson at all',
+    array( WPCPM_Track_Questions::last_of_lesson( $lessoned, 403499 ), WPCPM_Track_Questions::last_of_lesson( $lessoned, 0 ) ), array( '', '' ) );
+ck( 'add() after a named column places the question right after it, inside the group',
+    array_keys( WPCPM_Track_Questions::add( $lessoned, 'Figma 3', array( 'type' => 'text', 'group' => 'project', 'learn_lesson_id' => 403465 ), 'Figma 2' ) ),
+    array( 'Hours', 'Figma', 'Figma 2', 'Figma 3', 'Library', 'Feedback' ) );
+ck( 'and after a column the map does not hold, at the end of its group as before',
+    array_keys( WPCPM_Track_Questions::add( $lessoned, 'Deploy', array( 'type' => 'url', 'group' => 'project' ), 'Gone' ) ),
+    array( 'Hours', 'Figma', 'Figma 2', 'Library', 'Deploy', 'Feedback' ) );
+ck( 'a column already used is still refused, wherever it was to go',
+    WPCPM_Track_Questions::add( $lessoned, 'Library', array( 'type' => 'url', 'group' => 'project' ), 'Figma' ), null );
+
+echo "\n=== Matching lessons again on a course change (T3c) ===\n";
+
+$captured = json_decode( file_get_contents( __DIR__ . '/fixtures/learn-course-403425.json' ), true );
+$lessons  = array();
+
+foreach ( $captured['modules'] as $module ) {
+	foreach ( $module['lessons'] as $lesson ) {
+		$lessons[] = $lesson;
+	}
+}
+
+$before = array(
+	'Portfolio' => array( 'type' => 'url', 'group' => 'project', 'subgroup' => 'Create your portfolio', 'learn_lesson_id' => 111 ),
+	'Styles'    => array( 'type' => 'url', 'group' => 'project', 'lead' => "practical: change your site's global styles", 'learn_lesson_id' => 222 ),
+	'Event'     => array( 'type' => 'text', 'group' => 'wrapup', 'lead' => 'Participate at a WordPress Event (online or in person)', 'learn_lesson_id' => 333 ),
+	'Reflect'   => array( 'type' => 'textarea', 'group' => 'wrapup', 'lead' => 'Your reflection posts', 'learn_lesson_id' => 444 ),
+	'Nameless'  => array( 'type' => 'text', 'group' => 'project', 'learn_lesson_id' => 555 ),
+	'Hours'     => array( 'type' => 'number', 'group' => 'hours', 'lead' => 'Get your certificate' ),
+);
+
+$result = WPCPM_Track_Questions::rematch( $before, $lessons );
+
+ck( 'a question whose heading is a lesson of the new course, in lead or in subgroup, exact once case and apostrophes are folded, takes that lesson',
+    array( $result['questions']['Portfolio']['learn_lesson_id'], $result['questions']['Styles']['learn_lesson_id'], $result['questions']['Event']['learn_lesson_id'], $result['matched'] ),
+    array( 403457, 403477, 403501, array( 'Portfolio', 'Styles', 'Event' ) ) );
+ck( 'one whose heading is no lesson of the new course, or that has no heading, loses its lesson and is listed',
+    array( array_key_exists( 'learn_lesson_id', $result['questions']['Reflect'] ), array_key_exists( 'learn_lesson_id', $result['questions']['Nameless'] ), $result['cleared'] ),
+    array( false, false, array( 'Reflect', 'Nameless' ) ) );
+ck( 'a question that had no lesson is left alone, heading or not',
+    array( array_key_exists( 'learn_lesson_id', $result['questions']['Hours'] ), $result['questions']['Hours'] ), array( false, $before['Hours'] ) );
+ck( 'the order and every other property survive',
+    array( array_keys( $result['questions'] ), $result['questions']['Styles']['lead'] ), array( array_keys( $before ), $before['Styles']['lead'] ) );
+ck( 'against no lessons at all, every lesson is cleared',
+    WPCPM_Track_Questions::rematch( $before, array() )['cleared'], array( 'Portfolio', 'Styles', 'Event', 'Reflect', 'Nameless' ) );
+
 printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );
 
 exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-questions.php`

Expected: `bin/test-track-questions.php` stops with `Fatal error: Uncaught Error: Call to undefined method WPCPM_Track_Questions::last_of_lesson()`. `last_of_lesson()` does not exist yet, and neither do `$after` on `add()` nor `rematch()`.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/tracks/class-wpcpm-track-questions.php b/includes/tracks/class-wpcpm-track-questions.php
index 021bdba..960a789 100644
--- a/includes/tracks/class-wpcpm-track-questions.php
+++ b/includes/tracks/class-wpcpm-track-questions.php
@@ -38,30 +38,37 @@ final class WPCPM_Track_Questions {
 	const FORK_JOIN = ' - ';
 
 	/**
-	 * Add a question at the end of its own group.
+	 * Add a question, after the last question of its group or after a named column.
 	 *
-	 * The form draws group by group, so a question added to Onboarding belongs after the last
-	 * onboarding question rather than at the end of the track, which is where a plain append would
-	 * put it and is not where the person who pressed Add is looking.
+	 * The form draws group by group, so a question added to a group lands after the last one of
+	 * that group rather than at the end of the map (the design's section 5). A question added under
+	 * a Learn lesson names the lesson's last question as `$after` and lands right behind it, so a
+	 * lesson's questions stay together (decision 32); a column the map does not hold falls back to
+	 * the group rule.
 	 *
-	 * @param array  $questions The questions, column => spec, in order.
-	 * @param string $column    The new column name, verbatim.
+	 * @param array  $questions Column => spec, in order.
+	 * @param string $column    The new question's column, verbatim.
 	 * @param array  $question  The new question.
-	 * @return array|null The questions with it in place, or null when that column is already used.
+	 * @param string $after     The column to place it after, when there is one.
+	 * @return array|null The map with the question in place, or null when the column is used.
 	 */
-	public static function add( array $questions, $column, array $question ) {
+	public static function add( array $questions, $column, array $question, $after = '' ) {
 		$column = (string) $column;
 
 		if ( array_key_exists( $column, $questions ) ) {
 			return null;
 		}
 
-		$group = isset( $question['group'] ) ? (string) $question['group'] : '';
-		$after = '';
+		$after = (string) $after;
 
-		foreach ( $questions as $name => $spec ) {
-			if ( is_array( $spec ) && isset( $spec['group'] ) && (string) $spec['group'] === $group ) {
-				$after = (string) $name;
+		if ( '' === $after || ! array_key_exists( $after, $questions ) ) {
+			$group = isset( $question['group'] ) ? (string) $question['group'] : '';
+			$after = '';
+
+			foreach ( $questions as $name => $spec ) {
+				if ( is_array( $spec ) && isset( $spec['group'] ) && (string) $spec['group'] === $group ) {
+					$after = (string) $name;
+				}
 			}
 		}
 
@@ -84,6 +91,98 @@ final class WPCPM_Track_Questions {
 		return $placed;
 	}
 
+	/**
+	 * The last question reporting on a Learn lesson: where a new question of that lesson goes, and
+	 * whether the lesson has a question at all (its first question is the one that carries the
+	 * lesson's title as `lead`; decision 32).
+	 *
+	 * @param array $questions Column => spec, in order.
+	 * @param int   $lesson_id The lesson.
+	 * @return string The column, or '' when no question reports on the lesson.
+	 */
+	public static function last_of_lesson( array $questions, $lesson_id ) {
+		$lesson_id = (int) $lesson_id;
+		$last      = '';
+
+		if ( $lesson_id <= 0 ) {
+			return '';
+		}
+
+		foreach ( $questions as $name => $spec ) {
+			if ( is_array( $spec ) && isset( $spec['learn_lesson_id'] ) && (int) $spec['learn_lesson_id'] === $lesson_id ) {
+				$last = (string) $name;
+			}
+		}
+
+		return $last;
+	}
+
+	/**
+	 * Match every question that reports on a lesson against another course's lessons, by heading.
+	 *
+	 * A question's heading is its `lead`, or its `subgroup` when it has no lead (the Designer
+	 * Track's form holds one lesson's title in a subgroup); it matches a lesson's title exactly once
+	 * case, apostrophes and runs of spaces are folded, the way the design's 2.6 counted the matches.
+	 * A match takes the new lesson's ID; the rest lose theirs and are listed; a question with no
+	 * lesson is left alone, heading or not (decision 33).
+	 *
+	 * @param array   $questions Column => spec, in order.
+	 * @param array[] $lessons   The new course's lessons, each `id` and `title`, every module together.
+	 * @return array `questions` (the map, in the same order), `matched` and `cleared` (columns).
+	 */
+	public static function rematch( array $questions, array $lessons ) {
+		$by_title = array();
+
+		foreach ( $lessons as $lesson ) {
+			if ( is_array( $lesson ) && isset( $lesson['id'], $lesson['title'] ) ) {
+				$folded = self::fold( $lesson['title'] );
+
+				if ( '' !== $folded && ! isset( $by_title[ $folded ] ) ) {
+					$by_title[ $folded ] = (int) $lesson['id'];
+				}
+			}
+		}
+
+		$matched = array();
+		$cleared = array();
+
+		foreach ( $questions as $name => $spec ) {
+			if ( ! is_array( $spec ) || ! isset( $spec['learn_lesson_id'] ) ) {
+				continue;
+			}
+
+			$heading = isset( $spec['lead'] ) && '' !== (string) $spec['lead'] ? $spec['lead'] : ( isset( $spec['subgroup'] ) ? $spec['subgroup'] : '' );
+			$folded  = self::fold( $heading );
+
+			if ( '' !== $folded && isset( $by_title[ $folded ] ) ) {
+				$questions[ $name ]['learn_lesson_id'] = $by_title[ $folded ];
+				$matched[]                             = (string) $name;
+			} else {
+				unset( $questions[ $name ]['learn_lesson_id'] );
+				$cleared[] = (string) $name;
+			}
+		}
+
+		return array(
+			'questions' => $questions,
+			'matched'   => $matched,
+			'cleared'   => $cleared,
+		);
+	}
+
+	/**
+	 * A heading or a title as the re-match compares it: lower case, apostrophes gone, one space
+	 * between words.
+	 *
+	 * @param mixed $text The heading or title.
+	 * @return string
+	 */
+	private static function fold( $text ) {
+		$text = str_replace( array( "'", "\u{2019}", '`' ), '', (string) $text );
+
+		return strtolower( trim( preg_replace( '/\s+/u', ' ', $text ) ) );
+	}
+
 	/**
 	 * Move a question one place up or down among the questions of its own group.
 	 *
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-questions.php`

Expected: `bin/test-track-questions.php` ends `ALL PASS (49 checks)`. 

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
git add bin/test-track-questions.php includes/tracks/class-wpcpm-track-questions.php
git commit -m "Track Builder T3c: a question under a lesson, and lessons matched again"
```

---

### Task 3: The course resolved on save, and read again on demand

**Files:**
- Modify: `includes/tools/class-wpcpm-track-builder.php` (`ACTION_COURSE` and its hook, `handle_course()`, `course_of()`, `resolve_course()`; `posted_definition()` no longer reads a course ID; `handle_save()` carries a warning; `form()` carries `course`)
- Modify: `includes/tools/class-wpcpm-track-builder-screen.php` (the properties form skips the course ID row; `render_course_row()`, `render_course_press()`; `render_notice()` draws a `warning`)
- Modify: `includes/tracks/class-wpcpm-track-publish.php` (the link warning through `WPCPM_Learn`; `course_answers()`, `COURSE_CACHE_TTL` and the HEAD request go)
- Test: `bin/test-track-builder.php`, `bin/test-track-publish.php`

**Interfaces:**
- Consumes: Task 1's `WPCPM_Learn::resolve()` and `forget()`; `WPCPM_Request::posted_text()`; the screen's `render_button()`.
- Produces: `WPCPM_Track_Builder::ACTION_COURSE` (`wpcpm_track_course`) on `admin_post_`, and `handle_course()`, which checks capability then nonce, forgets the course and its structure and returns to the track. `course_of( array $definition )`, `id` and `title` of the course the link resolves to with `error` empty, or `error` saying why not with the `id` the track last resolved to, or all empty for a track with no link. `resolve_course( array &$definition, array $stored )`, private, sets `learn_course_id` from the answer and returns the warning "The Learn course link did not resolve: %s" or ''; the stored ID is kept while the link is the one it came from, a new link that fails has none, an emptied link clears it. `posted_definition()` reads no `wpcpm_learn_course_id`. `handle_save()` redirects with status `warning` and a message joined from "The track was saved.", the warning and "Nothing reaches students until it is published." when the link did not resolve. `form()` carries `course` as `course_of()` answers it. The screen skips the `learn_course_id` row, draws a "Learn course" row after the link reading "%1$s (course %2$d)" or the error followed by "The link last resolved to course %2$d, which the track keeps.", draws "Read the course again" as a form of its own after the properties form, and `render_notice()` draws a `warning` status as a warning notice. The preflight's `course_unreachable` warning reads "The Learn course link did not resolve to a course: %s The link still publishes: it is shown to students, and a course that is private or moved is worth checking." and comes from `resolve()`. The builder suite loads the real `WPCPM_Learn` with the transients and `wp_remote_get()` stood in, and its `course_answer( $slug, $id, $title )` helper seeds Learn's answer for a slug; the publish suite stands `WPCPM_Learn` in with an `$answers` table that resolves by default.

Decision 31's first half. The "Learn course ID" box goes: the link is what a person gives, the save resolves it and stores the ID from the answer, and the row after the link says what it resolved to. A link that does not resolve still saves, as 4.2 says, with a warning in the notice, and keeps the ID it last resolved to while the link is unchanged, so a passing outage on Learn never blanks a course; a new link that does not resolve has no ID, since the last one was another course's, and clearing the link clears the ID (what this plan decides, 6). A save that landed with a caveat is neither a success nor an error, so the notice learns a third status.

"Read the course again" is a form of its own after the properties form, since a form cannot sit inside another; it forgets what the client kept and comes back to the track, whose page reads Learn afresh. The preflight's own HEAD request to the link and its five-minute cache go, because the resolver answers the same question with the same severity (decision 30): the publish suite drops its `wp_remote_head()` stub, which is why the old preflight fails before the code moves.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-builder.php b/bin/test-track-builder.php
index c703a64..b74de99 100644
--- a/bin/test-track-builder.php
+++ b/bin/test-track-builder.php
@@ -26,6 +26,28 @@ function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
 function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
 function esc_url( $s ) { return (string) $s; }
 function esc_url_raw( $s ) { return (string) $s; }
+
+// Learn, as the real client reads it (T3c): a table of HTTP answers by address, and transients.
+if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); }
+$GLOBALS['transients'] = array();
+$GLOBALS['http']       = array();
+function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $k ] : false; }
+function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['transients'][ $k ] = $v; return true; }
+function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); return true; }
+function wp_remote_get( $url, $args = array() ) { return array_key_exists( $url, $GLOBALS['http'] ) ? $GLOBALS['http'][ $url ] : new WP_Error( 'http_request_failed', 'cURL error 28: Connection timed out' ); }
+function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? ( $r['response']['code'] ?? 200 ) : 0; }
+function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? ( $r['body'] ?? '' ) : ''; }
+
+/**
+ * Let Learn answer a course by its slug.
+ *
+ * @param string $slug  The course's slug.
+ * @param int    $id    Its post ID on Learn.
+ * @param string $title Its title, as Learn renders it.
+ */
+function course_answer( $slug, $id, $title ) {
+	$GLOBALS['http'][ 'https://learn.wordpress.org/wp-json/wp/v2/courses?slug=' . $slug . '&_fields=id,slug,status,link,title' ] = array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( array( 'id' => $id, 'slug' => $slug, 'title' => array( 'rendered' => $title ) ) ) ) );
+}
 function esc_html__( $s, $d = null ) { return esc_html( $s ); }
 function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
 function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
@@ -361,6 +383,7 @@ class WPCPM_Flash {
 
 // The real rules, not a stand-in: a stand-in for WPCPM_Track_Questions would let a handler pass
 // against a rule the real class does not hold (T2c's stub-drift findings).
+require_once __DIR__ . '/../includes/class-wpcpm-learn.php';
 require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-palette.php';
 require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';
 require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-diff.php';
@@ -564,7 +587,7 @@ echo "\n=== The properties form ===\n";
 // PHP is what the switch rests on (spec section 6), and the store refuses the save in any case.
 ck( 'the form offers the track properties, then its questions and every other track\'s columns',
     array_keys( WPCPM_Track_Builder::form( 13 ) ),
-    array( 'id', 'label', 'status', 'key', 'course_url', 'learn_course_id', 'hours_target', 'hue', 'read_only', 'questions', 'others', 'schema', 'locked' ) );
+    array( 'id', 'label', 'status', 'key', 'course_url', 'learn_course_id', 'hours_target', 'hue', 'read_only', 'questions', 'others', 'schema', 'locked', 'course' ) );
 ck( 'filled from the definition', array( WPCPM_Track_Builder::form( 13 )['label'], WPCPM_Track_Builder::form( 13 )['status'], WPCPM_Track_Builder::form( 13 )['read_only'] ), array( 'Marketing Track', 'Marketing Track', false ) );
 ck( 'and a built-in track its PHP runs is read-only', WPCPM_Track_Builder::form( 11 )['read_only'], true );
 
@@ -615,10 +638,9 @@ $_POST                     = array(
 	'wpcpm_label'           => $form_before['label'],
 	'wpcpm_status'          => $form_before['status'],
 	'wpcpm_key'             => $form_before['key'],
-	'wpcpm_course_url'      => $form_before['course_url'],
-	'wpcpm_learn_course_id' => $form_before['learn_course_id'],
-	'wpcpm_hours_target'    => $form_before['hours_target'],
-	'wpcpm_hue'             => $form_before['hue'],
+	'wpcpm_course_url'   => $form_before['course_url'],
+	'wpcpm_hours_target' => $form_before['hours_target'],
+	'wpcpm_hue'          => $form_before['hue'],
 );
 
 ck( 'an untouched save does not turn no target at all into a target of zero, or no course into an empty one',
@@ -2609,5 +2631,129 @@ $_POST = array();
 ck( 'what posted_question() builds for each of the ten controls is a question the real validator accepts, each carrying its Airtable type',
     $verdicts, array_fill_keys( array_keys( $posts ), array( array(), true ) ) );
 
+
+echo "\n=== The Learn course, resolved on save (T3c) ===\n";
+
+WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
+$GLOBALS['transients']     = array();
+$GLOBALS['http']           = array();
+
+ck( 'a track with no course link has no course to show', WPCPM_Track_Builder::form( 13 )['course'], array( 'id' => 0, 'title' => '', 'error' => '' ) );
+
+WPCPM_Track_Store::$tracks[13]['definition']['course_url']      = 'https://learn.wordpress.org/course/marketing/';
+WPCPM_Track_Store::$tracks[13]['definition']['learn_course_id'] = 500001;
+course_answer( 'marketing', 500001, 'Marketing &amp; Sales' );
+
+ck( 'the form carries what Learn says about the course, its title as words',
+    array( WPCPM_Track_Builder::form( 13 )['course'], array_key_exists( 'wpcpm_learn_course_marketing', $GLOBALS['transients'] ) ),
+    array( array( 'id' => 500001, 'title' => 'Marketing & Sales', 'error' => '' ), true ) );
+
+$GLOBALS['transients'] = array();
+$GLOBALS['http']       = array();
+
+ck( 'and when Learn does not answer, the reason and the last resolved id',
+    array( WPCPM_Track_Builder::form( 13 )['course']['id'], WPCPM_Track_Builder::form( 13 )['course']['title'], false !== strpos( WPCPM_Track_Builder::form( 13 )['course']['error'], 'did not answer' ) ),
+    array( 500001, '', true ) );
+
+course_answer( 'marketing', 500001, 'Marketing &amp; Sales' );
+ob_start();
+WPCPM_Track_Builder_Screen::render_form( array( 'form' => WPCPM_Track_Builder::form( 13 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
+$with_course = ob_get_clean();
+
+ck( 'the properties form has no box for the course id: the link is what a person gives, and the course it resolves to is shown beside it, with a press to read it again',
+    array(
+        substr_count( $with_course, 'name="wpcpm_learn_course_id"' ),
+        substr_count( $with_course, 'name="wpcpm_course_url"' ),
+        false !== strpos( $with_course, '<th scope="row">Learn course</th><td>Marketing &amp; Sales (course 500001)</td>' ),
+        substr_count( $with_course, 'name="action" value="wpcpm_track_course"' ),
+        substr_count( $with_course, 'Read the course again' ),
+    ),
+    array( 0, 1, true, 1, 1 ) );
+
+$GLOBALS['transients'] = array();
+$GLOBALS['http']       = array();
+ob_start();
+WPCPM_Track_Builder_Screen::render_form( array( 'form' => WPCPM_Track_Builder::form( 13 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
+$unresolved = ob_get_clean();
+unset( WPCPM_Track_Store::$tracks[13]['definition']['course_url'], WPCPM_Track_Store::$tracks[13]['definition']['learn_course_id'] );
+ob_start();
+WPCPM_Track_Builder_Screen::render_form( array( 'form' => WPCPM_Track_Builder::form( 13 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
+$no_course = ob_get_clean();
+
+ck( 'an unresolved link says why, keeping the course it last resolved to; no link, no course row and no press',
+    array(
+        false !== strpos( $unresolved, 'Learn WordPress did not answer' ),
+        false !== strpos( $unresolved, 'course 500001' ),
+        substr_count( $no_course, '<th scope="row">Learn course</th>' ),
+        substr_count( $no_course, 'Read the course again' ),
+    ),
+    array( true, true, 0, 0 ) );
+
+$GLOBALS['nonce']          = WPCPM_Track_Builder::ACTION_SAVE;
+WPCPM_Track_Store::$saved  = array();
+WPCPM_Track_Store::$errors = array();
+WPCPM_Flash::$set          = array();
+course_answer( 'marketing', 500001, 'Marketing &amp; Sales' );
+$_POST = array( 'track' => 13, 'wpcpm_label' => 'Marketing Track', 'wpcpm_status' => 'Marketing Track', 'wpcpm_key' => 'marketing', 'wpcpm_course_url' => 'https://learn.wordpress.org/course/marketing/', 'wpcpm_hours_target' => '', 'wpcpm_hue' => 'blue' );
+
+ck( 'a save resolves the link and stores the course id Learn answers, not one a person typed',
+    array( outcome( array( $tool, 'handle_save' ) ), WPCPM_Track_Store::$saved[13]['learn_course_id'], WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['status'] ),
+    array( 'redirect', 500001, 'success' ) );
+
+WPCPM_Track_Store::$tracks[13]['definition']['course_url']      = 'https://learn.wordpress.org/course/marketing/';
+WPCPM_Track_Store::$tracks[13]['definition']['learn_course_id'] = 500001;
+WPCPM_Track_Store::$saved = array();
+$GLOBALS['transients']    = array();
+$GLOBALS['http']          = array();
+
+ck( 'the same link while Learn is down keeps the last resolved id, and the notice carries the warning',
+    array( outcome( array( $tool, 'handle_save' ) ), WPCPM_Track_Store::$saved[13]['learn_course_id'], WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['status'], false !== strpos( WPCPM_Track_Builder_Screen::class ? WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['message'] : '', 'did not resolve' ) ),
+    array( 'redirect', 500001, 'warning', true ) );
+
+$_POST['wpcpm_course_url'] = 'https://learn.wordpress.org/course/another/';
+WPCPM_Track_Store::$saved  = array();
+
+ck( 'a new link that does not resolve saves with no course id, since the last one was another course\'s',
+    array( outcome( array( $tool, 'handle_save' ) ), array_key_exists( 'learn_course_id', WPCPM_Track_Store::$saved[13] ), WPCPM_Track_Store::$saved[13]['course_url'] ),
+    array( 'redirect', false, 'https://learn.wordpress.org/course/another/' ) );
+
+$_POST['wpcpm_course_url'] = '';
+WPCPM_Track_Store::$saved  = array();
+
+ck( 'clearing the link clears the course id with it',
+    array( outcome( array( $tool, 'handle_save' ) ), array_key_exists( 'learn_course_id', WPCPM_Track_Store::$saved[13] ), array_key_exists( 'course_url', WPCPM_Track_Store::$saved[13] ) ),
+    array( 'redirect', false, false ) );
+
+// The saves above went through the stand-in, which keeps what was saved; the press below needs a
+// track that holds a course, so the fixture is set again.
+WPCPM_Track_Store::$tracks[13]['definition']['course_url']      = 'https://learn.wordpress.org/course/marketing/';
+WPCPM_Track_Store::$tracks[13]['definition']['learn_course_id'] = 500001;
+$GLOBALS['hooks'] = array();
+$tool->boot();
+$GLOBALS['transients'] = array( 'wpcpm_learn_course_marketing' => array( 'id' => 500001 ), 'wpcpm_learn_structure_500001' => array(), 'wpcpm_airtable_schema' => array( 'kept' => 1 ) );
+$GLOBALS['can_manage'] = false;
+$GLOBALS['nonce']      = '';
+$_POST                 = array( 'track' => 13 );
+$refused               = outcome( array( $tool, 'handle_course' ) );
+$GLOBALS['can_manage'] = true;
+$nonce_refused         = outcome( array( $tool, 'handle_course' ) );
+$GLOBALS['nonce']      = WPCPM_Track_Builder::ACTION_COURSE;
+$read_again            = outcome( array( $tool, 'handle_course' ) );
+
+ck( 'Read the course again is on admin-post, checks the capability and then the nonce, forgets what Learn said about the course and nothing else, and returns to the track',
+    array(
+        in_array( 'admin_post_' . WPCPM_Track_Builder::ACTION_COURSE, $GLOBALS['hooks'], true ),
+        $refused, $nonce_refused, $read_again,
+        array_keys( $GLOBALS['transients'] ),
+        $GLOBALS['last_redirect'],
+        WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['status'],
+    ),
+    array( true, 'die: You do not have permission to manage the program.', 'die: the nonce was refused', 'redirect', array( 'wpcpm_airtable_schema' ), 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_track=13', 'success' ) );
+
+$_POST = array();
+$GLOBALS['transients'] = array();
+$GLOBALS['http']       = array();
+WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
+
 printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
 exit( $fail ? 1 : 0 );
diff --git a/bin/test-track-publish.php b/bin/test-track-publish.php
index 5d09a09..00b4619 100644
--- a/bin/test-track-publish.php
+++ b/bin/test-track-publish.php
@@ -19,7 +19,14 @@ define( 'ABSPATH', __DIR__ . '/' );
 
 function __( $s, $d = null ) { return $s; }
 function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
-function wp_remote_head( $url, $args = array() ) { return $GLOBALS['head'][ $url ] ?? array( 'response' => array( 'code' => 200 ) ); }
+/** Learn, stood in (T3c): what the resolver answers per link; a link with no answer resolves. */
+class WPCPM_Learn {
+	public static $answers = array();
+
+	public static function resolve( $url ) {
+		return self::$answers[ $url ] ?? array( 'id' => 500001, 'slug' => 'marketing', 'title' => 'Marketing' );
+	}
+}
 function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? (int) ( $r['response']['code'] ?? 0 ) : 0; }
 
 class WP_Error {
@@ -497,30 +504,18 @@ ck( 'while a track of somebody\'s own does add its status, which is what makes i
 
 echo "\n=== The Learn course, which is only ever a warning ===\n";
 
-// Every earlier preflight() in this file asked and cached "reachable" for this same URL, so this
-// scenario needs a clear transient before it, or the cached answer would win over the 404 below;
-// and the one after, or the "unreachable" answer this scenario writes would leak into every
-// preflight() for the rest of the file (the answer is cached across calls - Task 9 review, M4).
-$GLOBALS['transients'] = array();
-$GLOBALS['head']       = array( 'https://learn.wordpress.org/course/marketing/' => array( 'response' => array( 'code' => 404 ) ) );
+// Since T3c the question is whether the link resolves to a course, asked of WPCPM_Learn, which
+// keeps its own day's cache (decision 30); the preflight caches nothing of its own.
+WPCPM_Learn::$answers = array( 'https://learn.wordpress.org/course/marketing/' => new WP_Error( 'wpcpm_learn_no_course', 'Learn has no course at that address.' ) );
 
 $flight = WPCPM_Track_Publish::preflight( 7 );
 
-ck( 'a course that does not answer is a warning and nothing more',
-    array( codes( $flight['warnings'] ), $flight['ready'] ), array( array( 'course_unreachable' ), true ) );
-
-// M4 (Task 9 review): the answer is cached behind a transient, not asked fresh on every
-// preflight. The course would now answer, but a second preflight before the cache expires still
-// sees the warning, which is what proves the check is reading the cache and not the HEAD stub.
-$GLOBALS['head'] = array( 'https://learn.wordpress.org/course/marketing/' => array( 'response' => array( 'code' => 200 ) ) );
+ck( 'a link that does not resolve to a course is a warning and nothing more, and the warning says why',
+    array( codes( $flight['warnings'] ), $flight['ready'], false !== strpos( $flight['warnings'][0]['message'], 'Learn has no course at that address.' ) ), array( array( 'course_unreachable' ), true, true ) );
 
-$flight_again = WPCPM_Track_Publish::preflight( 7 );
+WPCPM_Learn::$answers = array();
 
-ck( 'a second preflight reuses the cached answer rather than asking again',
-    codes( $flight_again['warnings'] ), array( 'course_unreachable' ) );
-
-$GLOBALS['head']       = array();
-$GLOBALS['transients'] = array();
+ck( 'and a link that resolves is no warning at all', codes( WPCPM_Track_Publish::preflight( 7 )['warnings'] ), array() );
 
 echo "\n=== When Airtable cannot be read at all ===\n";
 
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-builder.php` and `php bin/test-track-publish.php`

Expected: `bin/test-track-builder.php` stops with `FAIL the form offers the track properties, then its questions and every other track's columns`; `bin/test-track-publish.php` stops with `Fatal error: Uncaught Error: Call to undefined function wp_remote_head()`. The builder suite's form check wants `course` in what `form()` hands over, and the publish suite no longer stands in `wp_remote_head()`, which the old preflight still calls.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/tools/class-wpcpm-track-builder-screen.php b/includes/tools/class-wpcpm-track-builder-screen.php
index a79216a..cd83101 100644
--- a/includes/tools/class-wpcpm-track-builder-screen.php
+++ b/includes/tools/class-wpcpm-track-builder-screen.php
@@ -718,7 +718,9 @@ final class WPCPM_Track_Builder_Screen {
 		if ( ! empty( $form['read_only'] ) ) {
 			echo '<p class="wpcpm-tracks__readonly">' . esc_html__( 'This track runs from its hand-written form, so it cannot be edited here. Duplicate it to start a track of your own, or switch it to its definition first.', 'wpcredits-program-manager' ) . '</p>';
 
-			// The questions are still shown, with nothing to press: what a duplicate would copy.
+			// The questions are still shown, with nothing to press: what a duplicate would copy. The
+			// course can still be read again, since its lessons are shown here too (T3c).
+			self::render_course_press( $form );
 			self::render_questions( $form, $url, $question_values );
 
 			return;
@@ -732,6 +734,12 @@ final class WPCPM_Track_Builder_Screen {
 		echo '<table class="form-table" role="presentation"><tbody>';
 
 		foreach ( self::track_labels() as $field => $heading ) {
+			// The course's ID is not typed since T3c: it is what the link resolves to, shown on the
+			// row after the link (decision 31).
+			if ( 'learn_course_id' === $field ) {
+				continue;
+			}
+
 			$value = array_key_exists( $field, $typed ) ? $typed[ $field ] : ( isset( $form[ $field ] ) ? $form[ $field ] : '' );
 
 			printf(
@@ -740,6 +748,10 @@ final class WPCPM_Track_Builder_Screen {
 				esc_html( $heading ),
 				esc_attr( (string) $value )
 			);
+
+			if ( 'course_url' === $field ) {
+				self::render_course_row( $form );
+			}
 		}
 
 		echo '</tbody></table>';
@@ -747,9 +759,62 @@ final class WPCPM_Track_Builder_Screen {
 		printf( '<p class="submit"><button type="submit" class="button button-primary">%s</button></p>', esc_html__( 'Save the track', 'wpcredits-program-manager' ) );
 		echo '</form>';
 
+		self::render_course_press( $form );
 		self::render_questions( $form, $url, $question_values );
 	}
 
+	/**
+	 * The course the link resolves to, or why it did not, on the row after the link (decision 31).
+	 *
+	 * @param array $form The track as `WPCPM_Track_Builder::form()` gives it.
+	 */
+	private static function render_course_row( array $form ) {
+		$course = isset( $form['course'] ) && is_array( $form['course'] ) ? $form['course'] : array();
+		$id     = isset( $course['id'] ) ? (int) $course['id'] : 0;
+		$title  = isset( $course['title'] ) ? (string) $course['title'] : '';
+		$error  = isset( $course['error'] ) ? (string) $course['error'] : '';
+
+		if ( '' === ( isset( $form['course_url'] ) ? (string) $form['course_url'] : '' ) ) {
+			return;
+		}
+
+		if ( '' === $error ) {
+			$text = sprintf(
+				/* translators: 1: the course's title, 2: its post ID on Learn. */
+				__( '%1$s (course %2$d)', 'wpcredits-program-manager' ),
+				$title,
+				$id
+			);
+		} elseif ( $id > 0 ) {
+			$text = sprintf(
+				/* translators: 1: why the link did not resolve, 2: the course's post ID on Learn. */
+				__( '%1$s The link last resolved to course %2$d, which the track keeps.', 'wpcredits-program-manager' ),
+				$error,
+				$id
+			);
+		} else {
+			$text = $error;
+		}
+
+		printf( '<tr><th scope="row">%1$s</th><td>%2$s</td></tr>', esc_html__( 'Learn course', 'wpcredits-program-manager' ), esc_html( $text ) );
+	}
+
+	/**
+	 * "Read the course again", for the day a lesson is added on Learn (decision 31). Its own form,
+	 * outside the properties form, since a form cannot sit inside another.
+	 *
+	 * @param array $form The track as `WPCPM_Track_Builder::form()` gives it.
+	 */
+	private static function render_course_press( array $form ) {
+		if ( '' === ( isset( $form['course_url'] ) ? (string) $form['course_url'] : '' ) ) {
+			return;
+		}
+
+		echo '<p class="wpcpm-tracks__course-press">';
+		self::render_button( WPCPM_Track_Builder::ACTION_COURSE, isset( $form['id'] ) ? (int) $form['id'] : 0, __( 'Read the course again', 'wpcredits-program-manager' ) );
+		echo '</p>';
+	}
+
 	/**
 	 * The question list under the properties, drawn by the editor's own screen class.
 	 *
@@ -1024,7 +1089,7 @@ final class WPCPM_Track_Builder_Screen {
 
 		printf(
 			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
-			esc_attr( isset( $flash['status'] ) && 'error' === $flash['status'] ? 'error' : 'success' ),
+			esc_attr( isset( $flash['status'] ) && in_array( $flash['status'], array( 'error', 'warning' ), true ) ? $flash['status'] : 'success' ),
 			esc_html( (string) $flash['message'] )
 		);
 	}
diff --git a/includes/tools/class-wpcpm-track-builder.php b/includes/tools/class-wpcpm-track-builder.php
index 6189a0a..70485d1 100644
--- a/includes/tools/class-wpcpm-track-builder.php
+++ b/includes/tools/class-wpcpm-track-builder.php
@@ -29,6 +29,9 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 	/** Start a track from nothing. */
 	const ACTION_NEW = 'wpcpm_track_new';
 
+	/** Read the track's Learn course again. */
+	const ACTION_COURSE = 'wpcpm_track_course';
+
 	/** How many saves History shows, the cap the semester report screen gives its own. */
 	const HISTORY_LIMIT = 20;
 
@@ -133,6 +136,7 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		add_action( 'admin_post_' . self::ACTION_SAVE, array( $this, 'handle_save' ) );
 		add_action( 'admin_post_' . self::ACTION_DUPLICATE, array( $this, 'handle_duplicate' ) );
 		add_action( 'admin_post_' . self::ACTION_NEW, array( $this, 'handle_new' ) );
+		add_action( 'admin_post_' . self::ACTION_COURSE, array( $this, 'handle_course' ) );
 		add_action( 'admin_post_' . self::ACTION_SWITCH_DEFINITION, array( $this, 'handle_switch_definition' ) );
 		add_action( 'admin_post_' . self::ACTION_SWITCH_BUILTIN, array( $this, 'handle_switch_builtin' ) );
 		add_action( 'admin_post_' . self::ACTION_REFRESH, array( $this, 'handle_refresh' ) );
@@ -270,6 +274,8 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 			// The columns of the published copy: a row for one of these promises no fork, since
 			// that is the change `handle_save()` refuses (decision 23, the whole-branch review).
 			'locked'          => is_array( $published ) && isset( $published['questions'] ) && is_array( $published['questions'] ) ? array_map( 'strval', array_keys( $published['questions'] ) ) : array(),
+			// What Learn says about the course, for the line beside the link (T3c, decision 31).
+			'course'          => self::course_of( $definition ),
 		);
 	}
 
@@ -635,6 +641,7 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		}
 
 		$definition = self::posted_definition( $stored );
+		$warning    = self::resolve_course( $definition, $stored );
 		$errors     = WPCPM_Track_Store::check( $post_id, $definition );
 
 		if ( array() !== $errors ) {
@@ -647,10 +654,11 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 			$this->refuse( $post_id, $saved->get_error_message(), $definition );
 		}
 
+		// A link that did not resolve saves all the same (4.2), and the notice says so.
 		$this->redirect_back(
 			array(
-				'status'  => 'success',
-				'message' => __( 'The track was saved. Nothing reaches students until it is published.', 'wpcredits-program-manager' ),
+				'status'  => '' === $warning ? 'success' : 'warning',
+				'message' => implode( ' ', array_filter( array( __( 'The track was saved.', 'wpcredits-program-manager' ), $warning, __( 'Nothing reaches students until it is published.', 'wpcredits-program-manager' ) ) ) ),
 			),
 			array( 'wpcpm_track' => $post_id )
 		);
@@ -793,6 +801,121 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		return $hues;
 	}
 
+	/**
+	 * What Learn says about the track's course, for the line beside the link (decision 31).
+	 *
+	 * Read through `WPCPM_Learn`, which keeps the answer for a day, so a track's page costs Learn
+	 * nothing after its first open.
+	 *
+	 * @param array $definition The track's definition.
+	 * @return array `id` and `title` of the course the link resolves to, with `error` empty; or
+	 *               `error` saying why it did not, with the `id` the track last resolved to and no
+	 *               title; all empty for a track with no link.
+	 */
+	public static function course_of( array $definition ) {
+		$url  = isset( $definition['course_url'] ) ? (string) $definition['course_url'] : '';
+		$last = isset( $definition['learn_course_id'] ) ? (int) $definition['learn_course_id'] : 0;
+
+		if ( '' === $url ) {
+			return array(
+				'id'    => 0,
+				'title' => '',
+				'error' => '',
+			);
+		}
+
+		$course = WPCPM_Learn::resolve( $url );
+
+		if ( is_wp_error( $course ) ) {
+			return array(
+				'id'    => $last,
+				'title' => '',
+				'error' => $course->get_error_message(),
+			);
+		}
+
+		return array(
+			'id'    => (int) $course['id'],
+			'title' => (string) $course['title'],
+			'error' => '',
+		);
+	}
+
+	/**
+	 * Resolve the posted link to its course and set `learn_course_id` from the answer.
+	 *
+	 * A link that does not resolve still saves (4.2), with a warning for the notice. While the link
+	 * is the one the stored ID came from, that ID is kept, so a passing outage on Learn never blanks
+	 * a course; a new link that does not resolve has no ID, since the last one was another course's
+	 * (decision 31).
+	 *
+	 * @param array $definition The posted definition, by reference.
+	 * @param array $stored     The definition as it was stored.
+	 * @return string The warning, or '' when the link resolved or there is none.
+	 */
+	private static function resolve_course( array &$definition, array $stored ) {
+		$url = isset( $definition['course_url'] ) ? (string) $definition['course_url'] : '';
+
+		if ( '' === $url ) {
+			return '';
+		}
+
+		$course = WPCPM_Learn::resolve( $url );
+
+		if ( ! is_wp_error( $course ) ) {
+			$definition['learn_course_id'] = (int) $course['id'];
+
+			return '';
+		}
+
+		$unchanged = isset( $stored['course_url'] ) && (string) $stored['course_url'] === $url;
+
+		if ( $unchanged && isset( $stored['learn_course_id'] ) ) {
+			$definition['learn_course_id'] = (int) $stored['learn_course_id'];
+		} else {
+			unset( $definition['learn_course_id'] );
+		}
+
+		return sprintf(
+			/* translators: %s: why the link did not resolve. */
+			__( 'The Learn course link did not resolve: %s', 'wpcredits-program-manager' ),
+			$course->get_error_message()
+		);
+	}
+
+	/**
+	 * Read the track's Learn course again: forget what was kept and come back to the track, whose
+	 * page reads it afresh (decision 31). For the day a lesson is added on Learn.
+	 */
+	public function handle_course() {
+		$this->verify( self::ACTION_COURSE );
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
+		WPCPM_Learn::forget(
+			isset( $stored['learn_course_id'] ) ? (int) $stored['learn_course_id'] : 0,
+			isset( $stored['course_url'] ) ? (string) $stored['course_url'] : ''
+		);
+
+		$this->redirect_back(
+			array(
+				'status'  => 'success',
+				'message' => __( 'The course was read again from Learn.', 'wpcredits-program-manager' ),
+			),
+			array( 'wpcpm_track' => $post_id )
+		);
+	}
+
 	/**
 	 * The posted properties, on top of the definition the track holds.
 	 *
@@ -809,7 +932,6 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		$definition['status'] = WPCPM_Request::posted_text( 'wpcpm_status' );
 		$definition['key']    = WPCPM_Request::posted_text( 'wpcpm_key' );
 		$course               = WPCPM_Request::posted_text( 'wpcpm_course_url' );
-		$learn                = (int) WPCPM_Request::posted_text( 'wpcpm_learn_course_id' );
 		$hours                = WPCPM_Request::posted_text( 'wpcpm_hours_target' );
 		$hue                  = WPCPM_Request::posted_text( 'wpcpm_hue' );
 
@@ -822,9 +944,9 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 			$definition['course_url'] = esc_url_raw( $course );
 		}
 
-		if ( $learn > 0 ) {
-			$definition['learn_course_id'] = $learn;
-		} else {
+		// The course's ID is not typed since T3c: `resolve_course()` sets it from the link on save
+		// (decision 31). With no link there is no course, whatever ID the stored definition carried.
+		if ( '' === $course ) {
 			unset( $definition['learn_course_id'] );
 		}
 
diff --git a/includes/tracks/class-wpcpm-track-publish.php b/includes/tracks/class-wpcpm-track-publish.php
index 9a71aca..510a00b 100644
--- a/includes/tracks/class-wpcpm-track-publish.php
+++ b/includes/tracks/class-wpcpm-track-publish.php
@@ -98,19 +98,6 @@ final class WPCPM_Track_Publish {
 	 */
 	const CHECKLIST = array( 'automation', 'welcome', 'choices' );
 
-	/**
-	 * How long a Learn course's reachability is trusted before being asked again.
-	 *
-	 * A course's reachability does not change minute to minute, and every tick, verify and
-	 * publish on the publish screen redirects straight back to a fresh preflight, so without this
-	 * a person working down the checklist would pay a five-second-timeout HEAD request on every
-	 * single page load (Task 9 review, M4). The schema read the rest of the preflight does is
-	 * never cached this way: that one has to say what the base looks like now.
-	 *
-	 * @var int
-	 */
-	const COURSE_CACHE_TTL = 300;
-
 	/**
 	 * What publishing this track would do, without doing any of it.
 	 *
@@ -272,12 +259,25 @@ final class WPCPM_Track_Publish {
 			}
 		}
 
-		if ( '' !== (string) ( isset( $definition['course_url'] ) ? $definition['course_url'] : '' ) && ! self::course_answers( (string) $definition['course_url'] ) ) {
-			$warnings[] = self::finding(
-				'course_unreachable',
-				'',
-				__( 'The Learn course did not answer. The link still publishes: it is shown to students, and a course that is private or moved is worth checking.', 'wpcredits-program-manager' )
-			);
+		// Whether the link resolves to a course, asked of the Learn client, which keeps its own
+		// day's cache (decision 30). A warning at worst: the link is shown to students and publishing
+		// never depends on it, so a private or moved course must not stop a track going live (7.1).
+		$course_url = isset( $definition['course_url'] ) ? (string) $definition['course_url'] : '';
+
+		if ( '' !== $course_url ) {
+			$course = WPCPM_Learn::resolve( $course_url );
+
+			if ( is_wp_error( $course ) ) {
+				$warnings[] = self::finding(
+					'course_unreachable',
+					'',
+					sprintf(
+						/* translators: %s: why the link did not resolve. */
+						__( 'The Learn course link did not resolve to a course: %s The link still publishes: it is shown to students, and a course that is private or moved is worth checking.', 'wpcredits-program-manager' ),
+						$course->get_error_message()
+					)
+				);
+			}
 		}
 
 		return self::answer(
@@ -798,46 +798,6 @@ final class WPCPM_Track_Publish {
 		return trim( strtolower( (string) $value ) );
 	}
 
-	/**
-	 * Whether the Learn course answers, asked at most once per `COURSE_CACHE_TTL`.
-	 *
-	 * A warning at worst: the link is shown to students and publishing never depends on it, so a
-	 * slow or private course must not stop a track going live (7.1). The answer is cached behind
-	 * a transient keyed by the URL rather than asked fresh on every preflight, because a course's
-	 * reachability does not change minute to minute (Task 9 review, M4) - unlike the schema read
-	 * above, which is never cached this way.
-	 *
-	 * @param string $url The course URL.
-	 * @return bool
-	 */
-	private static function course_answers( $url ) {
-		$key    = 'wpcpm_track_course_' . md5( $url );
-		$cached = get_transient( $key );
-
-		if ( false !== $cached ) {
-			return '1' === $cached;
-		}
-
-		$response = wp_remote_head(
-			$url,
-			array(
-				'timeout'     => 5,
-				'redirection' => 3,
-			)
-		);
-
-		$answers = false;
-
-		if ( ! is_wp_error( $response ) ) {
-			$code    = (int) wp_remote_retrieve_response_code( $response );
-			$answers = $code >= 200 && $code < 400;
-		}
-
-		set_transient( $key, $answers ? '1' : '0', self::COURSE_CACHE_TTL );
-
-		return $answers;
-	}
-
 	/**
 	 * Why a column already in the base cannot be used.
 	 *
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-builder.php` and `php bin/test-track-publish.php`

Expected: `bin/test-track-builder.php` ends `ALL PASS (223 checks)` and `bin/test-track-publish.php` ends `ALL PASS (86 checks)`. 

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
git add bin/test-track-builder.php bin/test-track-publish.php includes/tools/class-wpcpm-track-builder-screen.php includes/tools/class-wpcpm-track-builder.php includes/tracks/class-wpcpm-track-publish.php
git commit -m "Track Builder T3c: the course resolved on save, and read again on demand"
```

---

### Task 4: The course's lessons under each group, and Add under a lesson

**Files:**
- Modify: `includes/class-wpcpm-learn.php` (`lesson_title()`)
- Modify: `includes/tools/class-wpcpm-track-builder.php` (`lessons_of()`; `form()` carries `lessons` and `learn`; the track route passes `lesson`)
- Modify: `includes/tools/class-wpcpm-track-builder-screen.php` (`render_form()` reads `lesson` and hands the editor screen the lessons)
- Modify: `includes/tools/class-wpcpm-track-editor-screen.php` (the sentence under Questions; `render_lessons()`; the Add form's "Under lesson")
- Modify: `includes/tools/class-wpcpm-track-editor.php` (`handle_add()` under a lesson; `lesson_title()`)
- Test: `bin/test-learn.php`, `bin/test-track-builder.php`

**Interfaces:**
- Consumes: Task 1's `structure()` and `by_group()`; Task 2's `add()` with `$after` and `last_of_lesson()`; `WPCPM_Request::id()` and `posted_id()`.
- Produces: `WPCPM_Learn::lesson_title( array $modules, $lesson_id )`, the title or ''. `WPCPM_Track_Builder::lessons_of( array $definition )`, `by_group` (group => the module's lessons, each `id` and `title`) and `error` (the sentence when Learn could not be read, else ''), both empty for a track with no course; `form()` carries them as `lessons` and `learn`; the track route passes `'lesson' => WPCPM_Request::id( 'wpcpm_lesson' )`. The editor screen prints `<p class="wpcpm-questions__learn">The lessons of the course could not be read from Learn: %s</p>` under the Questions heading when it must; under each group's questions and above its Add form, `<p class="wpcpm-lessons__count">Lessons on Learn: %1$d of %2$d have questions.</p>` and a `<ul class="wpcpm-lessons">` whose items carry `<span class="wpcpm-lesson__title">`, then `<span class="wpcpm-lesson__asked">Asked by: %s</span>` naming the questions that report on the lesson or `<span class="wpcpm-lesson__none">No question yet</span>` with `<a class="wpcpm-lesson__add" href="...&wpcpm_track=<id>&wpcpm_lesson=<lesson>#wpcpm-questions-add-<group>">Add a question under this lesson</a>`, the link left off on a built-in track still on its PHP. The Add form carries `id="wpcpm-questions-add-<group>"` and, when its group has lessons, an "Under lesson" select `name="wpcpm_lesson"`, None first, the named lesson chosen. `WPCPM_Track_Editor::handle_add()` reads `wpcpm_lesson`, sets `learn_lesson_id` on the new question, places it after `last_of_lesson()` and gives it the lesson's title as `lead` when the lesson had no question yet. The builder suite's `structure_answer( $course_id, array $modules )` helper seeds a structure.

Decision 32's list. Each group's table is one of the course's modules (2.6), so a module's lessons go under the group's questions and above its Add form, in Learn's order, each with the questions that report on it or, for a lesson with none, the link that opens the group's Add form with the lesson chosen. Add under a lesson goes through that form, so the column, the words and the control are still the person's (T3a's rule); what the lesson adds is where the question goes and, when it is the lesson's first, its heading (what this plan decides, 4).

The builder maps the lessons to groups once, through the client's day-long answer, and hands the screens the map: the screens ask Learn nothing (what this plan decides, 8). When Learn cannot be read, one sentence under the Questions heading says so and no list is drawn, the rule decision 24 set for the schema line (what this plan decides, 9). A built-in track still on its PHP shows its lessons and their marks with nothing to press, since it cannot be edited here.

**Added in execution (the Task 4 review):** the plan gave the new markup no rule in `assets/css/track-builder.css`, whose siblings are all styled, so a fix round appended these rules after the History rules; apply them with Step 3:

```css
/* The lessons of the course under each group (1.107.0, decision 32): the count line reads as a
   notice in the same muted color as the schema line's age; the list keeps the group's left edge
   with no bullet, one lesson a line; a lesson's mark and its Add link sit after the title in the
   size a question's notice uses. */
.wpcpm-lessons__count {
	margin: 12px 0 4px;
	color: #50575e;
}

.wpcpm-lessons {
	margin: 0 0 8px;
	padding: 0;
	list-style: none;
}

.wpcpm-lessons li {
	margin: 0 0 4px;
}

.wpcpm-lesson__asked,
.wpcpm-lesson__none {
	margin-left: 8px;
	color: #50575e;
	font-size: 12px;
}

.wpcpm-lesson__add {
	margin-left: 8px;
	font-size: 12px;
}

/* When the course's lessons could not be read, the one sentence under the Questions heading is a
   notice, not an error: every question is still there to edit (decision 24's rule). */
.wpcpm-questions__learn {
	margin: 4px 0 12px;
	color: #50575e;
}
```

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-learn.php b/bin/test-learn.php
index d03b178..33b4f3c 100644
--- a/bin/test-learn.php
+++ b/bin/test-learn.php
@@ -211,5 +211,9 @@ ck( 'the lessons by group leave out a module that is no group, and the hours gro
     array( array_keys( $by_group ), count( $by_group['onboarding'] ), count( $by_group['project'] ), count( $by_group['wrapup'] ) ),
     array( array( 'onboarding', 'project', 'wrapup' ), 12, 23, 3 ) );
 
+ck( "a lesson's title is found by its id across the modules, and an unknown id has none",
+    array( WPCPM_Learn::lesson_title( $modules, 403439 ), WPCPM_Learn::lesson_title( $modules, 900001 ), WPCPM_Learn::lesson_title( $modules, 1 ) ),
+    array( 'Join global Slack', 'A lesson on its own', '' ) );
+
 printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
 exit( $fail ? 1 : 0 );
diff --git a/bin/test-track-builder.php b/bin/test-track-builder.php
index b74de99..1b675ec 100644
--- a/bin/test-track-builder.php
+++ b/bin/test-track-builder.php
@@ -48,6 +48,28 @@ function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? ( $r['body'] ??
 function course_answer( $slug, $id, $title ) {
 	$GLOBALS['http'][ 'https://learn.wordpress.org/wp-json/wp/v2/courses?slug=' . $slug . '&_fields=id,slug,status,link,title' ] = array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( array( 'id' => $id, 'slug' => $slug, 'title' => array( 'rendered' => $title ) ) ) ) );
 }
+
+/**
+ * Let Learn answer a course's structure: modules with lessons, as Learn lists them.
+ *
+ * @param int   $course_id The course's post ID on Learn.
+ * @param array $modules   Module title => list of [ id, title ] lessons.
+ */
+function structure_answer( $course_id, array $modules ) {
+	$entries = array();
+	$n       = 0;
+
+	foreach ( $modules as $title => $lessons ) {
+		$entries[] = array(
+			'type'    => 'module',
+			'id'      => 100 + ++$n,
+			'title'   => $title,
+			'lessons' => array_map( function ( $l ) { return array( 'type' => 'lesson', 'id' => $l[0], 'title' => $l[1], 'draft' => false ); }, $lessons ),
+		);
+	}
+
+	$GLOBALS['http'][ 'https://learn.wordpress.org/wp-json/sensei-internal/v1/course-structure/' . (int) $course_id ] = array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $entries ) );
+}
 function esc_html__( $s, $d = null ) { return esc_html( $s ); }
 function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
 function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
@@ -587,7 +609,7 @@ echo "\n=== The properties form ===\n";
 // PHP is what the switch rests on (spec section 6), and the store refuses the save in any case.
 ck( 'the form offers the track properties, then its questions and every other track\'s columns',
     array_keys( WPCPM_Track_Builder::form( 13 ) ),
-    array( 'id', 'label', 'status', 'key', 'course_url', 'learn_course_id', 'hours_target', 'hue', 'read_only', 'questions', 'others', 'schema', 'locked', 'course' ) );
+    array( 'id', 'label', 'status', 'key', 'course_url', 'learn_course_id', 'hours_target', 'hue', 'read_only', 'questions', 'others', 'schema', 'locked', 'course', 'lessons', 'learn' ) );
 ck( 'filled from the definition', array( WPCPM_Track_Builder::form( 13 )['label'], WPCPM_Track_Builder::form( 13 )['status'], WPCPM_Track_Builder::form( 13 )['read_only'] ), array( 'Marketing Track', 'Marketing Track', false ) );
 ck( 'and a built-in track its PHP runs is read-only', WPCPM_Track_Builder::form( 11 )['read_only'], true );
 
@@ -2755,5 +2777,113 @@ $GLOBALS['transients'] = array();
 $GLOBALS['http']       = array();
 WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
 
+
+echo "\n=== The course's lessons under each group, and Add under a lesson (T3c) ===\n";
+
+WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
+WPCPM_Track_Store::$tracks[13]['definition']['course_url']      = 'https://learn.wordpress.org/course/marketing/';
+WPCPM_Track_Store::$tracks[13]['definition']['learn_course_id'] = 500001;
+WPCPM_Track_Store::$tracks[13]['definition']['questions']['Slack name']['learn_lesson_id'] = 4001;
+$GLOBALS['transients'] = array();
+$GLOBALS['http']       = array();
+course_answer( 'marketing', 500001, 'Marketing' );
+structure_answer( 500001, array( 'Onboarding' => array( array( 4001, 'Join global Slack' ), array( 4002, 'Share your WordPress profile' ) ), 'Project' => array( array( 4011, 'Write your first post' ) ), 'Wrap-up' => array(), 'Extras' => array( array( 4099, 'Not a group' ) ) ) );
+
+$form = WPCPM_Track_Builder::form( 13 );
+
+ck( 'the form carries the course\'s lessons by the form\'s group, a module that is no group left out, and no complaint',
+    array( array_keys( $form['lessons'] ), $form['lessons']['onboarding'], $form['lessons']['project'], $form['learn'] ),
+    array( array( 'onboarding', 'project', 'wrapup' ), array( array( 'id' => 4001, 'title' => 'Join global Slack' ), array( 'id' => 4002, 'title' => 'Share your WordPress profile' ) ), array( array( 'id' => 4011, 'title' => 'Write your first post' ) ), '' ) );
+
+ob_start();
+WPCPM_Track_Builder_Screen::render_form( array( 'form' => $form, 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
+$page = ob_get_clean();
+
+ck( 'under a group, each lesson is listed with the questions that report on it, or a way to add one under it, and the count says how many have questions',
+    array(
+        false !== strpos( $page, '<span class="wpcpm-lesson__title">Join global Slack</span> <span class="wpcpm-lesson__asked">Asked by: Your Slack name</span>' ),
+        false !== strpos( $page, '<span class="wpcpm-lesson__title">Share your WordPress profile</span> <a class="wpcpm-lesson__add" href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_track=13&wpcpm_lesson=4002#wpcpm-questions-add-onboarding">Add a question under this lesson</a>' ),
+        false !== strpos( $page, 'Lessons on Learn: 1 of 2 have questions.' ),
+        false !== strpos( $page, 'Lessons on Learn: 0 of 1 have questions.' ),
+        substr_count( $page, 'class="wpcpm-lessons"' ),
+        false !== strpos( $page, 'Not a group' ),
+    ),
+    array( true, true, true, true, 2, false ) );
+
+ck( 'the add form of a group with lessons offers them under "Under lesson", None first, and carries its anchor',
+    array(
+        substr_count( $page, '<form method="post" action="https://example.test/wp-admin/admin-post.php" class="wpcpm-questions__add" id="wpcpm-questions-add-onboarding">' ),
+        false !== strpos( $page, '<label for="wpcpm_add_lesson_onboarding">Under lesson</label> <select id="wpcpm_add_lesson_onboarding" name="wpcpm_lesson"><option value="0">None</option><option value="4001">Join global Slack</option><option value="4002">Share your WordPress profile</option></select>' ),
+        substr_count( $page, 'name="wpcpm_lesson"' ),
+    ),
+    array( 1, true, 2 ) );
+
+ob_start();
+WPCPM_Track_Builder_Screen::render_form( array( 'form' => $form, 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array(), 'lesson' => 4002 ) );
+$linked = ob_get_clean();
+ob_start();
+WPCPM_Track_Builder_Screen::render_form( array( 'form' => $form, 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array( 'status' => 'error', 'message' => 'One column, one question.', 'question_values' => array( 'group' => 'onboarding', 'column' => 'Hours', 'label' => 'Again', 'type' => 'text', 'lesson' => 4001 ) ) ) );
+$refused_lesson = ob_get_clean();
+
+ck( 'the lesson the link names is chosen in its group\'s add form, and so is the one a refused add carried',
+    array( substr_count( $linked, '<option value="4002" selected="selected">' ), substr_count( $linked, 'selected="selected">Join' ), substr_count( $refused_lesson, '<option value="4001" selected="selected">' ) ),
+    array( 1, 0, 1 ) );
+
+WPCPM_Track_Store::$tracks[13]['source'] = 'builtin';
+ob_start();
+WPCPM_Track_Builder_Screen::render_form( array( 'form' => WPCPM_Track_Builder::form( 13 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
+$read_only_lessons = ob_get_clean();
+WPCPM_Track_Store::$tracks[13]['source'] = 'definition';
+
+ck( 'a built-in track still on its PHP shows the lessons and their marks with nothing to press',
+    array( substr_count( $read_only_lessons, 'Asked by: Your Slack name' ), substr_count( $read_only_lessons, 'wpcpm-lesson__add' ), substr_count( $read_only_lessons, '<span class="wpcpm-lesson__none">No question yet</span>' ) ),
+    array( 1, 0, 2 ) );
+
+$GLOBALS['transients'] = array();
+unset( $GLOBALS['http'][ 'https://learn.wordpress.org/wp-json/sensei-internal/v1/course-structure/500001' ] );
+$unread = WPCPM_Track_Builder::form( 13 );
+ob_start();
+WPCPM_Track_Builder_Screen::render_form( array( 'form' => $unread, 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
+$unread_page = ob_get_clean();
+
+ck( 'when Learn cannot be read the lessons are left off and one sentence says so; with no course at all nothing is said',
+    array(
+        $unread['lessons'], false !== strpos( $unread['learn'], 'did not answer' ),
+        substr_count( $unread_page, 'class="wpcpm-lessons"' ), false !== strpos( $unread_page, 'The lessons of the course could not be read from Learn' ),
+        WPCPM_Track_Builder::form( 11 )['lessons'], WPCPM_Track_Builder::form( 11 )['learn'],
+    ),
+    array( array(), true, 0, true, array(), '' ) );
+
+structure_answer( 500001, array( 'Onboarding' => array( array( 4001, 'Join global Slack' ), array( 4002, 'Share your WordPress profile' ) ), 'Project' => array( array( 4011, 'Write your first post' ) ) ) );
+$GLOBALS['nonce']         = WPCPM_Track_Editor::ACTION_ADD;
+WPCPM_Track_Store::$saved = array();
+$under = press_editor( 'handle_add', array( 'track' => 13, 'wpcpm_column' => 'Profile link', 'wpcpm_label' => 'Your profile', 'wpcpm_type' => 'url', 'wpcpm_group' => 'onboarding', 'wpcpm_lesson' => 4002 ) );
+
+ck( 'a question added under a lesson with no question yet carries the lesson and takes its title as the heading, after the last of its group',
+    array( $under[0], array_keys( WPCPM_Track_Store::$saved[13]['questions'] ), WPCPM_Track_Store::$saved[13]['questions']['Profile link'] ),
+    array( 'redirect', array( 'Hours', 'Slack name', 'Your blog', 'Profile link' ), array( 'type' => 'url', 'label' => 'Your profile', 'group' => 'onboarding', 'airtable_type' => 'url', 'learn_lesson_id' => 4002, 'lead' => 'Share your WordPress profile' ) ) );
+
+WPCPM_Track_Store::$saved = array();
+$second = press_editor( 'handle_add', array( 'track' => 13, 'wpcpm_column' => 'Slack handle', 'wpcpm_label' => 'Your handle', 'wpcpm_type' => 'text', 'wpcpm_group' => 'onboarding', 'wpcpm_lesson' => 4001 ) );
+
+ck( 'a second question under a lesson that has one takes no heading and lands right after that lesson\'s last question',
+    array( $second[0], array_keys( WPCPM_Track_Store::$saved[13]['questions'] ), array_key_exists( 'lead', WPCPM_Track_Store::$saved[13]['questions']['Slack handle'] ), WPCPM_Track_Store::$saved[13]['questions']['Slack handle']['learn_lesson_id'] ),
+    array( 'redirect', array( 'Hours', 'Slack name', 'Slack handle', 'Your blog', 'Profile link' ), false, 4001 ) );
+
+WPCPM_Track_Store::$saved = array();
+$plain = press_editor( 'handle_add', array( 'track' => 13, 'wpcpm_column' => 'Plain', 'wpcpm_label' => 'Plain', 'wpcpm_type' => 'text', 'wpcpm_group' => 'onboarding', 'wpcpm_lesson' => 0 ) );
+
+ck( 'None is no lesson: the question is added as before', array( $plain[0], array_key_exists( 'learn_lesson_id', WPCPM_Track_Store::$saved[13]['questions']['Plain'] ) ), array( 'redirect', false ) );
+
+WPCPM_Track_Store::$saved = array();
+$dup = press_editor( 'handle_add', array( 'track' => 13, 'wpcpm_column' => 'Hours', 'wpcpm_label' => 'Again', 'wpcpm_type' => 'text', 'wpcpm_group' => 'onboarding', 'wpcpm_lesson' => 4001 ) );
+
+ck( 'a refused add carries the lesson back with what was typed', array( $dup[0], $dup[2]['question_values']['lesson'] ), array( 'redirect', 4001 ) );
+
+$_POST = array();
+$GLOBALS['transients'] = array();
+$GLOBALS['http']       = array();
+WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
+
 printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
 exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-learn.php` and `php bin/test-track-builder.php`

Expected: `bin/test-learn.php` stops with `Fatal error: Uncaught Error: Call to undefined method WPCPM_Learn::lesson_title()`; `bin/test-track-builder.php` stops with `FAIL the form offers the track properties, then its questions and every other track's columns`. `WPCPM_Learn::lesson_title()` does not exist yet, and the builder suite's form check wants `lessons` and `learn` in what `form()` hands over.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/class-wpcpm-learn.php b/includes/class-wpcpm-learn.php
index 6a0a508..51e142c 100644
--- a/includes/class-wpcpm-learn.php
+++ b/includes/class-wpcpm-learn.php
@@ -246,6 +246,27 @@ final class WPCPM_Learn {
 		return $by_group;
 	}
 
+	/**
+	 * A lesson's title, found by its id across the modules; '' when the course has no such lesson.
+	 *
+	 * @param array $modules What `structure()` answered.
+	 * @param int   $lesson_id The lesson.
+	 * @return string
+	 */
+	public static function lesson_title( array $modules, $lesson_id ) {
+		$lesson_id = (int) $lesson_id;
+
+		foreach ( $modules as $module ) {
+			foreach ( isset( $module['lessons'] ) && is_array( $module['lessons'] ) ? $module['lessons'] : array() as $lesson ) {
+				if ( isset( $lesson['id'] ) && (int) $lesson['id'] === $lesson_id ) {
+					return isset( $lesson['title'] ) ? (string) $lesson['title'] : '';
+				}
+			}
+		}
+
+		return '';
+	}
+
 	/**
 	 * The published lessons of a list, each as its id and its title.
 	 *
diff --git a/includes/tools/class-wpcpm-track-builder-screen.php b/includes/tools/class-wpcpm-track-builder-screen.php
index cd83101..f9b9592 100644
--- a/includes/tools/class-wpcpm-track-builder-screen.php
+++ b/includes/tools/class-wpcpm-track-builder-screen.php
@@ -702,6 +702,7 @@ final class WPCPM_Track_Builder_Screen {
 		$flash           = isset( $args['flash'] ) && is_array( $args['flash'] ) ? $args['flash'] : array();
 		$typed           = isset( $flash['values'] ) && is_array( $flash['values'] ) ? $flash['values'] : array();
 		$question_values = isset( $flash['question_values'] ) && is_array( $flash['question_values'] ) ? $flash['question_values'] : array();
+		$lesson          = isset( $args['lesson'] ) ? (int) $args['lesson'] : 0;
 
 		self::render_notice( $flash );
 
@@ -721,7 +722,7 @@ final class WPCPM_Track_Builder_Screen {
 			// The questions are still shown, with nothing to press: what a duplicate would copy. The
 			// course can still be read again, since its lessons are shown here too (T3c).
 			self::render_course_press( $form );
-			self::render_questions( $form, $url, $question_values );
+			self::render_questions( $form, $url, $question_values, $lesson );
 
 			return;
 		}
@@ -760,7 +761,7 @@ final class WPCPM_Track_Builder_Screen {
 		echo '</form>';
 
 		self::render_course_press( $form );
-		self::render_questions( $form, $url, $question_values );
+		self::render_questions( $form, $url, $question_values, $lesson );
 	}
 
 	/**
@@ -818,11 +819,12 @@ final class WPCPM_Track_Builder_Screen {
 	/**
 	 * The question list under the properties, drawn by the editor's own screen class.
 	 *
-	 * @param array  $form  The track as `WPCPM_Track_Builder::form()` gives it.
-	 * @param string $url   The screen's URL.
-	 * @param array  $typed What a refused Add carried, for the add form to draw again.
+	 * @param array  $form   The track as `WPCPM_Track_Builder::form()` gives it.
+	 * @param string $url    The screen's URL.
+	 * @param array  $typed  What a refused Add carried, for the add form to draw again.
+	 * @param int    $lesson The lesson "Add a question under this lesson" named, for its group's add form.
 	 */
-	private static function render_questions( array $form, $url, array $typed = array() ) {
+	private static function render_questions( array $form, $url, array $typed = array(), $lesson = 0 ) {
 		WPCPM_Track_Editor_Screen::render_questions(
 			array(
 				'track'     => isset( $form['id'] ) ? (int) $form['id'] : 0,
@@ -831,6 +833,9 @@ final class WPCPM_Track_Builder_Screen {
 				'others'    => isset( $form['others'] ) && is_array( $form['others'] ) ? $form['others'] : array(),
 				'schema'    => isset( $form['schema'] ) && is_array( $form['schema'] ) ? $form['schema'] : array(),
 				'locked'    => isset( $form['locked'] ) && is_array( $form['locked'] ) ? $form['locked'] : array(),
+				'lessons'   => isset( $form['lessons'] ) && is_array( $form['lessons'] ) ? $form['lessons'] : array(),
+				'learn'     => isset( $form['learn'] ) ? (string) $form['learn'] : '',
+				'lesson'    => (int) $lesson,
 				'typed'     => $typed,
 				'url'       => $url,
 				'read_only' => ! empty( $form['read_only'] ),
diff --git a/includes/tools/class-wpcpm-track-builder.php b/includes/tools/class-wpcpm-track-builder.php
index 70485d1..48ed84f 100644
--- a/includes/tools/class-wpcpm-track-builder.php
+++ b/includes/tools/class-wpcpm-track-builder.php
@@ -254,6 +254,7 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		$definition = is_array( $definition ) ? $definition : array();
 		$read_only  = 'builtin' === WPCPM_Track_Store::source( $post_id );
 		$published  = WPCPM_Track_Store::published( $post_id );
+		$lessons    = self::lessons_of( $definition );
 
 		return array(
 			'id'              => $post_id,
@@ -276,6 +277,10 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 			'locked'          => is_array( $published ) && isset( $published['questions'] ) && is_array( $published['questions'] ) ? array_map( 'strval', array_keys( $published['questions'] ) ) : array(),
 			// What Learn says about the course, for the line beside the link (T3c, decision 31).
 			'course'          => self::course_of( $definition ),
+			// The course's lessons by the form's group, and one sentence when they could not be
+			// read (decision 32).
+			'lessons'         => $lessons['by_group'],
+			'learn'           => $lessons['error'],
 		);
 	}
 
@@ -598,9 +603,12 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		if ( $track > 0 && is_array( WPCPM_Track_Store::get( $track ) ) ) {
 			WPCPM_Track_Builder_Screen::render_form(
 				array(
-					'form'  => self::form( $track ),
-					'url'   => $this->admin_url(),
-					'flash' => $flash,
+					'form'   => self::form( $track ),
+					'url'    => $this->admin_url(),
+					'flash'  => $flash,
+					// "Add a question under this lesson" names the lesson to choose in the
+					// group's add form (decision 32).
+					'lesson' => WPCPM_Request::id( 'wpcpm_lesson' ),
 				)
 			);
 
@@ -841,6 +849,41 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		);
 	}
 
+	/**
+	 * The course's lessons by the form's group, for the track's page (decision 32).
+	 *
+	 * Read through `WPCPM_Learn`, which keeps the structure for a day. When Learn cannot be read
+	 * the lessons are left off and one sentence says why, the rule decision 24 set for the schema
+	 * line; a track with no course has neither.
+	 *
+	 * @param array $definition The track's definition.
+	 * @return array `by_group` (group => a list of lessons, each `id` and `title`) and `error`.
+	 */
+	public static function lessons_of( array $definition ) {
+		$course_id = isset( $definition['learn_course_id'] ) ? (int) $definition['learn_course_id'] : 0;
+
+		if ( $course_id <= 0 ) {
+			return array(
+				'by_group' => array(),
+				'error'    => '',
+			);
+		}
+
+		$modules = WPCPM_Learn::structure( $course_id );
+
+		if ( is_wp_error( $modules ) ) {
+			return array(
+				'by_group' => array(),
+				'error'    => $modules->get_error_message(),
+			);
+		}
+
+		return array(
+			'by_group' => WPCPM_Learn::by_group( $modules ),
+			'error'    => '',
+		);
+	}
+
 	/**
 	 * Resolve the posted link to its course and set `learn_course_id` from the answer.
 	 *
diff --git a/includes/tools/class-wpcpm-track-editor-screen.php b/includes/tools/class-wpcpm-track-editor-screen.php
index 7f1beb9..2b635a5 100644
--- a/includes/tools/class-wpcpm-track-editor-screen.php
+++ b/includes/tools/class-wpcpm-track-editor-screen.php
@@ -410,12 +410,27 @@ class WPCPM_Track_Editor_Screen {
 		$typed     = isset( $args['typed'] ) && is_array( $args['typed'] ) ? $args['typed'] : array();
 		$url       = isset( $args['url'] ) ? (string) $args['url'] : '';
 		$read_only = ! empty( $args['read_only'] );
+		$lessons   = isset( $args['lessons'] ) && is_array( $args['lessons'] ) ? $args['lessons'] : array();
+		$learn     = isset( $args['learn'] ) ? (string) $args['learn'] : '';
+		$lesson    = isset( $args['lesson'] ) ? (int) $args['lesson'] : 0;
 
 		echo '<div class="wpcpm-questions">';
 		echo '<h2 class="wpcpm-questions__heading">' . esc_html__( 'Questions', 'wpcredits-program-manager' ) . '</h2>';
 
 		self::render_schema_line( $schema );
 
+		// The lessons are left off and one sentence says why when Learn could not be read; with no
+		// course there is nothing to say (decision 32).
+		if ( '' !== $learn ) {
+			$unread = sprintf(
+				/* translators: %s: why Learn could not be read. */
+				__( 'The lessons of the course could not be read from Learn: %s', 'wpcredits-program-manager' ),
+				$learn
+			);
+
+			echo '<p class="wpcpm-questions__learn">' . esc_html( $unread ) . '</p>';
+		}
+
 		foreach ( self::groups() as $group => $heading ) {
 			printf( '<section class="wpcpm-questions__group" id="wpcpm-questions-%s">', esc_attr( $group ) );
 			printf( '<h3>%s</h3>', esc_html( $heading ) );
@@ -450,8 +465,12 @@ class WPCPM_Track_Editor_Screen {
 				echo '</tbody></table>';
 			}
 
+			$of_group = isset( $lessons[ $group ] ) && is_array( $lessons[ $group ] ) ? $lessons[ $group ] : array();
+
+			self::render_lessons( $group, $of_group, $questions, $track, $url, $read_only );
+
 			if ( ! $read_only ) {
-				self::render_add( $track, $group, $typed );
+				self::render_add( $track, $group, $typed, $of_group, $lesson );
 			}
 
 			echo '</section>';
@@ -666,17 +685,22 @@ class WPCPM_Track_Editor_Screen {
 	 * rather than cleared; only in the group the press came from, since the other groups' forms
 	 * were never filled in (the whole-branch review).
 	 *
-	 * @param int    $track The post ID.
-	 * @param string $group The group.
-	 * @param array  $typed What a refused Add carried, or empty.
+	 * @param int    $track   The post ID.
+	 * @param string $group   The group.
+	 * @param array  $typed   What a refused Add carried, or empty.
+	 * @param array  $lessons The module's lessons, each `id` and `title`, for "Under lesson".
+	 * @param int    $lesson  The lesson to choose when no refusal carried one.
 	 */
-	private static function render_add( $track, $group, array $typed = array() ) {
+	private static function render_add( $track, $group, array $typed = array(), array $lessons = array(), $lesson = 0 ) {
 		$mine    = isset( $typed['group'] ) && (string) $typed['group'] === (string) $group;
 		$column  = $mine && isset( $typed['column'] ) ? (string) $typed['column'] : '';
 		$words   = $mine && isset( $typed['label'] ) ? (string) $typed['label'] : '';
 		$control = $mine && isset( $typed['type'] ) ? (string) $typed['type'] : '';
+		// The lesson to choose: the one a refused add carried, else the one the link named.
+		$chosen = $mine && isset( $typed['lesson'] ) ? (int) $typed['lesson'] : (int) $lesson;
 
-		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wpcpm-questions__add">';
+		// The id is where "Add a question under this lesson" lands (decision 32).
+		printf( '<form method="post" action="%1$s" class="wpcpm-questions__add" id="wpcpm-questions-add-%2$s">', esc_url( admin_url( 'admin-post.php' ) ), esc_attr( $group ) );
 		wp_nonce_field( WPCPM_Track_Editor::ACTION_ADD );
 		echo '<input type="hidden" name="action" value="' . esc_attr( WPCPM_Track_Editor::ACTION_ADD ) . '" />';
 		printf( '<input type="hidden" name="track" value="%d" />', (int) $track );
@@ -701,7 +725,96 @@ class WPCPM_Track_Editor_Screen {
 		}
 
 		echo '</select> ';
+
+		// Under lesson, for a group that is one of the course's modules: None first, so a question
+		// of the program's own is the plain case.
+		if ( array() !== $lessons ) {
+			printf( '<label for="wpcpm_add_lesson_%1$s">%2$s</label> <select id="wpcpm_add_lesson_%1$s" name="wpcpm_lesson">', esc_attr( $group ), esc_html__( 'Under lesson', 'wpcredits-program-manager' ) );
+			printf( '<option value="0">%s</option>', esc_html__( 'None', 'wpcredits-program-manager' ) );
+
+			foreach ( $lessons as $entry ) {
+				$id = isset( $entry['id'] ) ? (int) $entry['id'] : 0;
+
+				printf( '<option value="%1$d"%3$s>%2$s</option>', (int) $id, esc_html( isset( $entry['title'] ) ? (string) $entry['title'] : '' ), $id === $chosen ? ' selected="selected"' : '' );
+			}
+
+			echo '</select> ';
+		}
+
 		printf( '<button type="submit" class="button">%s</button>', esc_html__( 'Add a question', 'wpcredits-program-manager' ) );
 		echo '</form>';
 	}
+
+	/**
+	 * The lessons of the module this group is, under its questions (decision 32): each with the
+	 * questions that report on it, or "Add a question under this lesson", which opens the group's
+	 * add form with the lesson chosen; a built-in track still on its PHP has nothing to press.
+	 *
+	 * @param string  $group     The group.
+	 * @param array[] $lessons   Its module's lessons, each `id` and `title`.
+	 * @param array   $questions Every question of the track, column => spec.
+	 * @param int     $track     The track.
+	 * @param string  $url       The screen's URL.
+	 * @param bool    $read_only Whether the track is a built-in one still on its PHP.
+	 */
+	private static function render_lessons( $group, array $lessons, array $questions, $track, $url, $read_only ) {
+		if ( array() === $lessons ) {
+			return;
+		}
+
+		$asked = array();
+
+		foreach ( $questions as $spec ) {
+			if ( is_array( $spec ) && isset( $spec['learn_lesson_id'] ) ) {
+				$asked[ (int) $spec['learn_lesson_id'] ][] = isset( $spec['label'] ) ? (string) $spec['label'] : '';
+			}
+		}
+
+		$with = 0;
+
+		foreach ( $lessons as $entry ) {
+			if ( isset( $entry['id'], $asked[ (int) $entry['id'] ] ) ) {
+				++$with;
+			}
+		}
+
+		$count = sprintf(
+			/* translators: 1: how many of the module's lessons have questions, 2: how many lessons it has. */
+			__( 'Lessons on Learn: %1$d of %2$d have questions.', 'wpcredits-program-manager' ),
+			$with,
+			count( $lessons )
+		);
+
+		echo '<p class="wpcpm-lessons__count">' . esc_html( $count ) . '</p>';
+		echo '<ul class="wpcpm-lessons">';
+
+		foreach ( $lessons as $entry ) {
+			$id = isset( $entry['id'] ) ? (int) $entry['id'] : 0;
+
+			echo '<li class="wpcpm-lesson">';
+			printf( '<span class="wpcpm-lesson__title">%s</span> ', esc_html( isset( $entry['title'] ) ? (string) $entry['title'] : '' ) );
+
+			if ( isset( $asked[ $id ] ) ) {
+				$by = sprintf(
+					/* translators: %s: the questions that report on the lesson, comma-separated. */
+					__( 'Asked by: %s', 'wpcredits-program-manager' ),
+					implode( ', ', $asked[ $id ] )
+				);
+
+				printf( '<span class="wpcpm-lesson__asked">%s</span>', esc_html( $by ) );
+			} elseif ( $read_only || '' === $url ) {
+				echo '<span class="wpcpm-lesson__none">' . esc_html__( 'No question yet', 'wpcredits-program-manager' ) . '</span>';
+			} else {
+				printf(
+					'<a class="wpcpm-lesson__add" href="%1$s">%2$s</a>',
+					esc_url( add_query_arg( 'wpcpm_lesson', $id, add_query_arg( 'wpcpm_track', (int) $track, $url ) ) . '#wpcpm-questions-add-' . $group ),
+					esc_html__( 'Add a question under this lesson', 'wpcredits-program-manager' )
+				);
+			}
+
+			echo '</li>';
+		}
+
+		echo '</ul>';
+	}
 }
diff --git a/includes/tools/class-wpcpm-track-editor.php b/includes/tools/class-wpcpm-track-editor.php
index 0cefb28..70d219f 100644
--- a/includes/tools/class-wpcpm-track-editor.php
+++ b/includes/tools/class-wpcpm-track-editor.php
@@ -84,11 +84,13 @@ final class WPCPM_Track_Editor {
 		$column  = WPCPM_Request::posted_exact( 'wpcpm_column' );
 		$type    = WPCPM_Request::posted_key( 'wpcpm_type' );
 		$group   = WPCPM_Request::posted_key( 'wpcpm_group' );
+		$lesson  = WPCPM_Request::posted_id( 'wpcpm_lesson' );
 		$typed   = array(
 			'column' => $column,
 			'label'  => WPCPM_Request::posted_text( 'wpcpm_label' ),
 			'type'   => $type,
 			'group'  => $group,
+			'lesson' => $lesson,
 		);
 
 		$question = array(
@@ -103,7 +105,25 @@ final class WPCPM_Track_Editor {
 			$question['airtable_type'] = $airtable_type;
 		}
 
-		$questions = WPCPM_Track_Questions::add( self::questions( $stored ), $column, $question );
+		$after = '';
+
+		// Under a lesson (decision 32): the question carries it, takes the lesson's title as its
+		// heading when it is the lesson's first question, since the heading belongs to the first
+		// question after it, and lands after the lesson's last.
+		if ( $lesson > 0 ) {
+			$question['learn_lesson_id'] = $lesson;
+			$after                       = WPCPM_Track_Questions::last_of_lesson( self::questions( $stored ), $lesson );
+
+			if ( '' === $after ) {
+				$title = self::lesson_title( $stored, $lesson );
+
+				if ( '' !== $title ) {
+					$question['lead'] = $title;
+				}
+			}
+		}
+
+		$questions = WPCPM_Track_Questions::add( self::questions( $stored ), $column, $question, $after );
 
 		if ( null === $questions ) {
 			$this->refuse( $post_id, __( 'This track already has a question on that column. One column, one question.', 'wpcredits-program-manager' ), $typed );
@@ -449,6 +469,25 @@ final class WPCPM_Track_Editor {
 		return isset( $definition['questions'] ) && is_array( $definition['questions'] ) ? $definition['questions'] : array();
 	}
 
+	/**
+	 * A lesson's title on the track's course, from what Learn keeps; '' when it cannot be read.
+	 *
+	 * @param array $definition The track's definition.
+	 * @param int   $lesson_id  The lesson.
+	 * @return string
+	 */
+	private static function lesson_title( array $definition, $lesson_id ) {
+		$course_id = isset( $definition['learn_course_id'] ) ? (int) $definition['learn_course_id'] : 0;
+
+		if ( $course_id <= 0 ) {
+			return '';
+		}
+
+		$modules = WPCPM_Learn::structure( $course_id );
+
+		return is_wp_error( $modules ) ? '' : WPCPM_Learn::lesson_title( $modules, $lesson_id );
+	}
+
 	/**
 	 * The definition a handler works on, or the list with a refusal.
 	 *
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-learn.php` and `php bin/test-track-builder.php`

Expected: `bin/test-learn.php` ends `ALL PASS (26 checks)` and `bin/test-track-builder.php` ends `ALL PASS (233 checks)`. 

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
git add bin/test-learn.php bin/test-track-builder.php includes/class-wpcpm-learn.php includes/tools/class-wpcpm-track-builder-screen.php includes/tools/class-wpcpm-track-builder.php includes/tools/class-wpcpm-track-editor-screen.php includes/tools/class-wpcpm-track-editor.php
git commit -m "Track Builder T3c: the course's lessons under each group, and Add under a lesson"
```

---

### Task 5: A question's Learn lesson, on its screen

**Files:**
- Modify: `includes/tools/class-wpcpm-track-builder.php` (`question_form()` carries `lessons`, `learn` and `course`)
- Modify: `includes/tools/class-wpcpm-track-editor-screen.php` (`render_lesson_row()` after the heading row)
- Modify: `includes/tools/class-wpcpm-track-editor.php` (`posted_question()` reads the lesson; the carry-through goes)
- Test: `bin/test-track-builder.php`

**Interfaces:**
- Consumes: Task 1's `structure()`; `WPCPM_Request::posted_id()`. (Corrected in execution: the row draws its own select, number box and hidden input rather than calling the screen's `render_text_row()`, whose box is a text box.)
- Produces: `question_form()` carries `lessons` (the modules as `structure()` answers them, for the row's groups), `learn` (the sentence when Learn could not be read, else '') and `course` (whether the track has one). The screen's private `render_lesson_row( array $form, $lesson_id, $heading )` draws, after "Heading before it", a select `name="wpcpm_learn_lesson_id"` with one optgroup per module, "None" first, a stored lesson the course does not have as "Lesson %d, not in this course"; a number box with the sentence when Learn could not be read; and, for a track with no course, no row at all, the value carried in a hidden input. `posted_question()` reads `wpcpm_learn_lesson_id` through `posted_id()` like every other property, and the stored value is no longer carried through a save unread.

Decision 32's row. Until now `learn_lesson_id` rode through a save untouched, the one property the question screen did not draw, because there was nothing to choose it from; the course's lessons are that, in their modules, "None" first. A stored lesson the course no longer has is shown as such so it can be seen and cleared. When Learn cannot be read the row is the stored ID as a number box with one sentence, the rule decision 24 set (what this plan decides, 9). With no course there is no row: the value rides hidden, so a lesson kept from an earlier course is not lost by a save. The T3a save check now posts the lesson as a number too, since the handler reads it like any other property.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-builder.php b/bin/test-track-builder.php
index 1b675ec..6517332 100644
--- a/bin/test-track-builder.php
+++ b/bin/test-track-builder.php
@@ -1309,9 +1309,11 @@ $saved = press_editor( 'handle_save', array(
 	'wpcpm_type' => 'url', 'wpcpm_label' => 'Your blog, if you have one', 'wpcpm_group' => 'onboarding',
 	'wpcpm_help' => 'The address', 'wpcpm_lead' => 'About you', 'wpcpm_required' => '1', 'wpcpm_hide_from_institution' => '1',
 	'wpcpm_row' => 'links', 'wpcpm_stack' => '1', 'wpcpm_why' => 'Kept short',
+	// The lesson is a box of the screen's own since T3c, posted like any other property (decision 32).
+	'wpcpm_learn_lesson_id' => '4242',
 ) );
 
-ck( 'every property the control owns is read, the flags only when ticked, and the lesson id is carried through untouched',
+ck( 'every property the control owns is read, the flags only when ticked, and the lesson id from its box',
     WPCPM_Track_Store::$saved[13]['questions']['Your blog'],
     array( 'type' => 'url', 'label' => 'Your blog, if you have one', 'group' => 'onboarding', 'help' => 'The address', 'lead' => 'About you', 'why' => 'Kept short', 'row' => 'links', 'stack' => true, 'required' => true, 'hide_from_institution' => true, 'airtable_type' => 'url', 'learn_lesson_id' => 4242 ) );
 
@@ -2885,5 +2887,68 @@ $GLOBALS['transients'] = array();
 $GLOBALS['http']       = array();
 WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
 
+
+echo "\n=== A question's Learn lesson (T3c) ===\n";
+
+WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
+WPCPM_Track_Store::$tracks[13]['definition']['course_url']      = 'https://learn.wordpress.org/course/marketing/';
+WPCPM_Track_Store::$tracks[13]['definition']['learn_course_id'] = 500001;
+WPCPM_Track_Store::$tracks[13]['definition']['questions']['Slack name']['learn_lesson_id'] = 4001;
+$GLOBALS['transients'] = array();
+$GLOBALS['http']       = array();
+course_answer( 'marketing', 500001, 'Marketing' );
+structure_answer( 500001, array( 'Onboarding' => array( array( 4001, 'Join global Slack' ), array( 4002, 'Share your WordPress profile' ) ), 'Project' => array( array( 4011, 'Write your first post' ) ) ) );
+
+$lessoned = WPCPM_Track_Builder::question_form( 13, 'Slack name' );
+
+ck( 'the question\'s form carries the course\'s modules with their lessons, and whether the track has a course at all',
+    array( array_column( $lessoned['lessons'], 'title' ), count( $lessoned['lessons'][0]['lessons'] ), $lessoned['learn'], $lessoned['course'] ),
+    array( array( 'Onboarding', 'Project' ), 2, '', true ) );
+
+$screen = question_screen( $lessoned );
+
+ck( 'the screen offers the course\'s lessons in their modules after the heading row, None first, the stored one chosen',
+    array(
+        false !== strpos( $screen, '<tr><th scope="row"><label for="wpcpm_learn_lesson_id">Learn lesson</label></th><td><select id="wpcpm_learn_lesson_id" name="wpcpm_learn_lesson_id"><option value="0">None</option><optgroup label="Onboarding"><option value="4001" selected="selected">Join global Slack</option><option value="4002">Share your WordPress profile</option></optgroup><optgroup label="Project"><option value="4011">Write your first post</option></optgroup></select></td></tr>' ),
+        strpos( $screen, 'name="wpcpm_lead"' ) < strpos( $screen, 'name="wpcpm_learn_lesson_id"' ),
+        strpos( $screen, 'name="wpcpm_learn_lesson_id"' ) < strpos( $screen, 'name="wpcpm_subgroup"' ),
+    ),
+    array( true, true, true ) );
+
+WPCPM_Track_Store::$tracks[13]['definition']['questions']['Slack name']['learn_lesson_id'] = 4999;
+$gone = question_screen( WPCPM_Track_Builder::question_form( 13, 'Slack name' ) );
+
+ck( 'a stored lesson the course no longer has is shown as such, chosen, so it can be seen and cleared',
+    array( substr_count( $gone, '<option value="0">None</option><option value="4999" selected="selected">Lesson 4999, not in this course</option>' ), preg_match( '#<select id="wpcpm_learn_lesson_id".*?</select>#s', $gone, $lesson_select ) ? substr_count( $lesson_select[0], 'selected="selected"' ) : -1 ),
+    array( 1, 1 ) );
+
+$GLOBALS['transients'] = array();
+unset( $GLOBALS['http']['https://learn.wordpress.org/wp-json/sensei-internal/v1/course-structure/500001'] );
+$unread = question_screen( WPCPM_Track_Builder::question_form( 13, 'Slack name' ) );
+
+ck( 'when Learn cannot be read the row is the stored id as a number box, and says why',
+    array( false !== strpos( $unread, '<input type="number" class="small-text" id="wpcpm_learn_lesson_id" name="wpcpm_learn_lesson_id" value="4999" />' ), false !== strpos( $unread, 'The lessons of the course could not be read from Learn' ), substr_count( $unread, '<select id="wpcpm_learn_lesson_id"' ) ),
+    array( true, true, 0 ) );
+
+unset( WPCPM_Track_Store::$tracks[13]['definition']['course_url'], WPCPM_Track_Store::$tracks[13]['definition']['learn_course_id'] );
+$no_course = question_screen( WPCPM_Track_Builder::question_form( 13, 'Slack name' ) );
+
+ck( 'with no course there is no row, and the stored lesson rides hidden so a save keeps it',
+    array( substr_count( $no_course, 'Learn lesson' ), substr_count( $no_course, '<input type="hidden" name="wpcpm_learn_lesson_id" value="4999" />' ) ),
+    array( 0, 1 ) );
+
+$_POST  = array( 'wpcpm_type' => 'text', 'wpcpm_label' => 'Words', 'wpcpm_group' => 'onboarding', 'wpcpm_learn_lesson_id' => '4002' );
+$chosen = WPCPM_Track_Editor::posted_question( array( 'type' => 'text', 'learn_lesson_id' => 4001 ) );
+$_POST['wpcpm_learn_lesson_id'] = '0';
+$none = WPCPM_Track_Editor::posted_question( array( 'type' => 'text', 'learn_lesson_id' => 4001 ) );
+$_POST = array();
+
+ck( 'posted_question() takes the lesson from its box, and None clears the one that was stored',
+    array( $chosen['learn_lesson_id'], array_key_exists( 'learn_lesson_id', $none ) ), array( 4002, false ) );
+
+$GLOBALS['transients'] = array();
+$GLOBALS['http']       = array();
+WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
+
 printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
 exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-builder.php`

Expected: `bin/test-track-builder.php` stops with `Fatal error: Uncaught TypeError: array_column(): Argument #1 ($array) must be of type array, null given`. The question form carries no `lessons` yet, so the suite's `array_column()` over them is handed null.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/tools/class-wpcpm-track-builder.php b/includes/tools/class-wpcpm-track-builder.php
index 48ed84f..6b47d2d 100644
--- a/includes/tools/class-wpcpm-track-builder.php
+++ b/includes/tools/class-wpcpm-track-builder.php
@@ -477,6 +477,8 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		$key       = isset( $definition['key'] ) ? (string) $definition['key'] : '';
 		$published = WPCPM_Track_Store::published( $post_id );
 		$others    = WPCPM_Track_Store::others( $post_id );
+		$course_id = isset( $definition['learn_course_id'] ) ? (int) $definition['learn_course_id'] : 0;
+		$modules   = $course_id > 0 ? WPCPM_Learn::structure( $course_id ) : array();
 
 		return array(
 			'track'       => $post_id,
@@ -488,6 +490,11 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 			'forked_from' => WPCPM_Track_Questions::forked_from( $column, $key, $others ),
 			'locked'      => WPCPM_Track_Questions::locked( $column, is_array( $published ) && isset( $published['questions'] ) && is_array( $published['questions'] ) ? $published['questions'] : array() ),
 			'read_only'   => 'builtin' === WPCPM_Track_Store::source( $post_id ),
+			// The course's modules with their lessons for the lesson row, why they could not be
+			// read when they could not, and whether there is a course at all (decision 32).
+			'lessons'     => is_wp_error( $modules ) ? array() : $modules,
+			'learn'       => is_wp_error( $modules ) ? $modules->get_error_message() : '',
+			'course'      => $course_id > 0,
 		);
 	}
 
diff --git a/includes/tools/class-wpcpm-track-editor-screen.php b/includes/tools/class-wpcpm-track-editor-screen.php
index 2b635a5..5c69a06 100644
--- a/includes/tools/class-wpcpm-track-editor-screen.php
+++ b/includes/tools/class-wpcpm-track-editor-screen.php
@@ -228,6 +228,7 @@ class WPCPM_Track_Editor_Screen {
 
 		self::render_text_row( 'help', $labels['help'], (string) $value( 'help' ) );
 		self::render_text_row( 'lead', $labels['lead'], (string) $value( 'lead' ) );
+		self::render_lesson_row( $form, (int) $value( 'learn_lesson_id', 0 ), $labels['learn_lesson_id'] );
 		self::render_text_row( 'subgroup', $labels['subgroup'], (string) $value( 'subgroup' ) );
 		self::render_text_row( 'note', $labels['note'], (string) $value( 'note' ) );
 		self::render_text_row( 'row', $labels['row'], (string) $value( 'row' ), __( 'Questions with the same row name sit side by side: lowercase letters, digits and hyphens.', 'wpcredits-program-manager' ) );
@@ -338,12 +339,86 @@ class WPCPM_Track_Editor_Screen {
 	}
 
 	/**
-	 * One text box in the properties table.
+	 * The question's Learn lesson, after the heading row (decision 32): the course's lessons in their
+	 * modules, None first; a stored lesson the course no longer has shown as such; the stored id as a
+	 * number box when Learn could not be read; and no row at all, the lesson carried hidden, when
+	 * the track has no course.
 	 *
-	 * @param string $property    The property, which names the field.
+	 * @param array  $form      The form as `WPCPM_Track_Builder::question_form()` gives it.
+	 * @param int    $lesson_id The lesson the question holds, or 0.
+	 * @param string $heading   The row's label.
+	 */
+	private static function render_lesson_row( array $form, $lesson_id, $heading ) {
+		$modules = isset( $form['lessons'] ) && is_array( $form['lessons'] ) ? $form['lessons'] : array();
+		$learn   = isset( $form['learn'] ) ? (string) $form['learn'] : '';
+
+		// No course, no row: a lesson the question holds rides hidden, so a save keeps it.
+		if ( empty( $form['course'] ) ) {
+			if ( $lesson_id > 0 ) {
+				printf( '<input type="hidden" name="wpcpm_learn_lesson_id" value="%d" />', (int) $lesson_id );
+			}
+
+			return;
+		}
+
+		// Learn could not be read: the stored id as a number box, and why (decision 32).
+		if ( '' !== $learn ) {
+			$unread = sprintf(
+				/* translators: %s: why Learn could not be read. */
+				__( 'The lessons of the course could not be read from Learn: %s', 'wpcredits-program-manager' ),
+				$learn
+			);
+
+			printf(
+				'<tr><th scope="row"><label for="wpcpm_learn_lesson_id">%1$s</label></th><td><input type="number" class="small-text" id="wpcpm_learn_lesson_id" name="wpcpm_learn_lesson_id" value="%2$s" /><p class="description">%3$s</p></td></tr>',
+				esc_html( $heading ),
+				esc_attr( $lesson_id > 0 ? (string) $lesson_id : '' ),
+				esc_html( $unread )
+			);
+
+			return;
+		}
+
+		$known = '' !== WPCPM_Learn::lesson_title( $modules, $lesson_id );
+
+		printf( '<tr><th scope="row"><label for="wpcpm_learn_lesson_id">%s</label></th><td><select id="wpcpm_learn_lesson_id" name="wpcpm_learn_lesson_id">', esc_html( $heading ) );
+		printf( '<option value="0"%2$s>%1$s</option>', esc_html__( 'None', 'wpcredits-program-manager' ), $lesson_id <= 0 ? ' selected="selected"' : '' );
+
+		// A lesson the course no longer has stays visible, chosen, so it can be seen and cleared.
+		if ( $lesson_id > 0 && ! $known ) {
+			$missing = sprintf(
+				/* translators: %d: a Learn lesson's post ID. */
+				__( 'Lesson %d, not in this course', 'wpcredits-program-manager' ),
+				$lesson_id
+			);
+
+			printf( '<option value="%1$d" selected="selected">%2$s</option>', (int) $lesson_id, esc_html( $missing ) );
+		}
+
+		foreach ( $modules as $module ) {
+			$title = isset( $module['title'] ) && '' !== (string) $module['title'] ? (string) $module['title'] : __( 'Other lessons', 'wpcredits-program-manager' );
+
+			printf( '<optgroup label="%s">', esc_attr( $title ) );
+
+			foreach ( isset( $module['lessons'] ) && is_array( $module['lessons'] ) ? $module['lessons'] : array() as $entry ) {
+				$id = isset( $entry['id'] ) ? (int) $entry['id'] : 0;
+
+				printf( '<option value="%1$d"%3$s>%2$s</option>', (int) $id, esc_html( isset( $entry['title'] ) ? (string) $entry['title'] : '' ), $id === $lesson_id ? ' selected="selected"' : '' );
+			}
+
+			echo '</optgroup>';
+		}
+
+		echo '</select></td></tr>';
+	}
+
+	/**
+	 * One property as a text box in its row.
+	 *
+	 * @param string $property    The property, which names the box.
 	 * @param string $heading     The row's label.
-	 * @param string $value       What the box holds.
-	 * @param string $description A line under the box, or empty.
+	 * @param string $value       The value to draw.
+	 * @param string $description A description under the box, when there is one.
 	 */
 	private static function render_text_row( $property, $heading, $value, $description = '' ) {
 		printf(
diff --git a/includes/tools/class-wpcpm-track-editor.php b/includes/tools/class-wpcpm-track-editor.php
index 70d219f..58bf2ec 100644
--- a/includes/tools/class-wpcpm-track-editor.php
+++ b/includes/tools/class-wpcpm-track-editor.php
@@ -358,9 +358,9 @@ final class WPCPM_Track_Editor {
 	 *
 	 * Every property 4.2 lists, read for the control that owns it and left out otherwise, so
 	 * `validate()` sees exactly what a person set and nothing a previous control left behind.
-	 * `learn_lesson_id` is carried through from what is stored, until T3c makes it editable;
-	 * `why` is not, because the screen draws it, so it is read from the post like any other text
-	 * and dropped when it comes back empty (the whole-branch review).
+	 * `learn_lesson_id` is a box since T3c (decision 32) and read like any other property; `why`
+	 * too, since the screen draws it, so it is read from the post like any other text and dropped
+	 * when it comes back empty (the whole-branch review).
 	 *
 	 * @param array $was The question as it is stored.
 	 * @return array
@@ -436,10 +436,12 @@ final class WPCPM_Track_Editor {
 			}
 		}
 
-		foreach ( array( 'learn_lesson_id' ) as $carried ) {
-			if ( isset( $was[ $carried ] ) ) {
-				$question[ $carried ] = $was[ $carried ];
-			}
+		// The lesson the screen's box holds: chosen from the course's lessons, the stored id when
+		// Learn could not be read, or carried hidden when the track has no course; None is none.
+		$lesson = WPCPM_Request::posted_id( 'wpcpm_learn_lesson_id' );
+
+		if ( $lesson > 0 ) {
+			$question['learn_lesson_id'] = $lesson;
 		}
 
 		return $question;
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-builder.php`

Expected: `bin/test-track-builder.php` ends `ALL PASS (239 checks)`. 

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
git add bin/test-track-builder.php includes/tools/class-wpcpm-track-builder.php includes/tools/class-wpcpm-track-editor-screen.php includes/tools/class-wpcpm-track-editor.php
git commit -m "Track Builder T3c: a question's Learn lesson, on its screen"
```

---

### Task 6: New track from a Learn course link

**Files:**
- Modify: `includes/tools/class-wpcpm-track-builder.php` (`handle_new()` reads and resolves a link)
- Modify: `includes/tools/class-wpcpm-track-builder-screen.php` (`render_new()` asks for the link)
- Test: `bin/test-track-builder.php`

**Interfaces:**
- Consumes: Task 1's `WPCPM_Learn::resolve()`; `WPCPM_Track_Store::check()` and `create()` as T3b left them. (Corrected in execution: `handle_new()` resolves the link inline rather than through Task 3's `resolve_course()`, which compares with a stored definition a new track does not have.)
- Produces: `handle_new()` reads `wpcpm_course_url` through `posted_text()`; a link that resolves sets `course_url` and `learn_course_id` and, when `wpcpm_label` was left empty, the label from the course's title; one that does not resolve is kept, with the warning in the notice and the flash status `warning`; the check through the store runs as before. `render_new()` draws a "Learn course" row after the three, and its sentence says the name is taken from the course when it is left empty.

Decision 31's second half: a track can start from a link, a status and a key. The link is resolved through the same client a save uses and a failure is read the same way, so the two entry points cannot disagree about what a link means, and the name defaults to the course's title only when the box was left empty, since a name typed on purpose is the person's (what this plan decides, 7). A link that does not resolve is kept with the warning rather than refused: a person is not made to retype a long link over a passing outage, and the track's page will say what is wrong with it.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-builder.php b/bin/test-track-builder.php
index 6517332..57156a7 100644
--- a/bin/test-track-builder.php
+++ b/bin/test-track-builder.php
@@ -2950,5 +2950,63 @@ $GLOBALS['transients'] = array();
 $GLOBALS['http']       = array();
 WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
 
+
+echo "\n=== New track from a Learn course link (T3c) ===\n";
+
+WPCPM_Track_Store::$tracks  = array( 13 => editable_track() );
+WPCPM_Track_Store::$created = array();
+WPCPM_Track_Store::$errors  = array();
+$GLOBALS['transients']      = array();
+$GLOBALS['http']            = array();
+$GLOBALS['nonce']           = WPCPM_Track_Builder::ACTION_NEW;
+course_answer( 'wordpress-credits-designer', 403425, 'WordPress Credits &#8211; Designer Track' );
+
+$from_link = press_new( array( 'wpcpm_label' => '', 'wpcpm_status' => 'Designer Track 2', 'wpcpm_key' => 'design-2', 'wpcpm_course_url' => 'https://learn.wordpress.org/course/wordpress-credits-designer/' ) );
+
+ck( 'a link that resolves fills the course and, with the name left empty, the name from the course\'s title',
+    array( $from_link[0], $from_link[2]['status'], WPCPM_Track_Store::$created[0] ),
+    array( 'redirect', 'success', array( 'schema_version' => 1, 'status' => 'Designer Track 2', 'key' => 'design-2', 'label' => "WordPress Credits \u{2013} Designer Track", 'hue' => 'blue', 'questions' => array(), 'course_url' => 'https://learn.wordpress.org/course/wordpress-credits-designer/', 'learn_course_id' => 403425 ) ) );
+
+WPCPM_Track_Store::$created = array();
+$named = press_new( array( 'wpcpm_label' => 'My own name', 'wpcpm_status' => 'Designer Track 2', 'wpcpm_key' => 'design-2', 'wpcpm_course_url' => 'https://learn.wordpress.org/course/wordpress-credits-designer/' ) );
+
+ck( 'a name that was typed is kept over the course\'s title', WPCPM_Track_Store::$created[0]['label'], 'My own name' );
+
+WPCPM_Track_Store::$created = array();
+$GLOBALS['transients']      = array();
+$GLOBALS['http']            = array();
+$unresolved = press_new( array( 'wpcpm_label' => 'Blank Track', 'wpcpm_status' => 'Blank Track', 'wpcpm_key' => 'blank', 'wpcpm_course_url' => 'https://learn.wordpress.org/course/nowhere/' ) );
+
+ck( 'a link that does not resolve is kept, with no course id, and the notice says so',
+    array( $unresolved[0], $unresolved[2]['status'], false !== strpos( $unresolved[2]['message'], 'did not resolve' ), WPCPM_Track_Store::$created[0]['course_url'], array_key_exists( 'learn_course_id', WPCPM_Track_Store::$created[0] ) ),
+    array( 'redirect', 'warning', true, 'https://learn.wordpress.org/course/nowhere/', false ) );
+
+WPCPM_Track_Store::$created = array();
+// The real check() runs validate(), which refuses an empty name; the stand-in refuses what it is told.
+WPCPM_Track_Store::$errors  = array( array( 'code' => 'label_empty', 'message' => 'The track needs a name.' ) );
+$nameless = press_new( array( 'wpcpm_label' => '', 'wpcpm_status' => 'Blank Track', 'wpcpm_key' => 'blank', 'wpcpm_course_url' => 'https://learn.wordpress.org/course/nowhere/' ) );
+WPCPM_Track_Store::$errors  = array();
+
+ck( 'with no name and a link that does not resolve, the store\'s own refusal comes back with what was typed, the link included',
+    array( $nameless[0], $nameless[2]['status'], $nameless[2]['values']['course_url'], WPCPM_Track_Store::$created ),
+    array( 'redirect', 'error', 'https://learn.wordpress.org/course/nowhere/', array() ) );
+
+ob_start();
+WPCPM_Track_Builder_Screen::render_new( array( 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array( 'status' => 'error', 'message' => 'The track needs a name.', 'values' => array( 'label' => '', 'status' => 'Blank Track', 'key' => 'blank', 'course_url' => 'https://learn.wordpress.org/course/nowhere/' ) ) ) );
+$new_form = ob_get_clean();
+
+ck( 'the New track form offers the course link after the key and redraws what was typed',
+    array(
+        false !== strpos( $new_form, '<label for="wpcpm_course_url">Learn course</label>' ),
+        false !== strpos( $new_form, 'id="wpcpm_course_url" name="wpcpm_course_url" value="https://learn.wordpress.org/course/nowhere/"' ),
+        strpos( $new_form, 'name="wpcpm_key"' ) < strpos( $new_form, 'name="wpcpm_course_url"' ),
+        false !== strpos( $new_form, 'the name is taken from the course' ),
+    ),
+    array( true, true, true, true ) );
+
+$_POST = array();
+$GLOBALS['transients'] = array();
+$GLOBALS['http']       = array();
+
 printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
 exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-builder.php`

Expected: `bin/test-track-builder.php` stops with `FAIL a link that resolves fills the course and, with the name left empty, the name from the course's title`. `handle_new()` reads no link yet, so the course stays empty and the name is not taken from the course's title.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/tools/class-wpcpm-track-builder-screen.php b/includes/tools/class-wpcpm-track-builder-screen.php
index f9b9592..14de9dc 100644
--- a/includes/tools/class-wpcpm-track-builder-screen.php
+++ b/includes/tools/class-wpcpm-track-builder-screen.php
@@ -877,7 +877,7 @@ final class WPCPM_Track_Builder_Screen {
 
 		printf( '<p><a href="%1$s">%2$s</a></p>', esc_url( $url ), esc_html__( 'Back to every track', 'wpcredits-program-manager' ) );
 
-		echo '<p>' . esc_html__( 'A track from nothing: its name, its Airtable status and its key. Everything else about it, and every question, is edited on the page that opens next.', 'wpcredits-program-manager' ) . '</p>';
+		echo '<p>' . esc_html__( 'A track from nothing: its name, its Airtable status and its key, and the link of the Learn course it follows, when it follows one. With a link, the course\'s lessons are listed beside the questions, and the name is taken from the course when it is left empty. Everything else about the track, and every question, is edited on the page that opens next.', 'wpcredits-program-manager' ) . '</p>';
 
 		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
 		wp_nonce_field( WPCPM_Track_Builder::ACTION_NEW );
@@ -885,7 +885,7 @@ final class WPCPM_Track_Builder_Screen {
 
 		echo '<table class="form-table" role="presentation"><tbody>';
 
-		foreach ( array_intersect_key( self::track_labels(), array_flip( array( 'label', 'status', 'key' ) ) ) as $field => $heading ) {
+		foreach ( array_intersect_key( self::track_labels(), array_flip( array( 'label', 'status', 'key', 'course_url' ) ) ) as $field => $heading ) {
 			printf(
 				'<tr><th scope="row"><label for="wpcpm_%1$s">%2$s</label></th><td><input type="text" class="regular-text" id="wpcpm_%1$s" name="wpcpm_%1$s" value="%3$s" /></td></tr>',
 				esc_attr( $field ),
diff --git a/includes/tools/class-wpcpm-track-builder.php b/includes/tools/class-wpcpm-track-builder.php
index 6b47d2d..6907e2c 100644
--- a/includes/tools/class-wpcpm-track-builder.php
+++ b/includes/tools/class-wpcpm-track-builder.php
@@ -744,7 +744,9 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 	}
 
 	/**
-	 * Start a track from nothing: a status, a key and a label, and an empty form (the design's 5).
+	 * Start a track from nothing: a status, a key and a label, and an empty form (the design's 5);
+	 * or from a Learn course link, which fills the course and, when the name was left empty, the
+	 * name from the course's title (decision 31).
 	 *
 	 * Checked as a track with nothing locked to it, as a copy is, so a status or key another track
 	 * holds is refused before a draft nobody asked for exists. Everything else about the track, and
@@ -762,6 +764,30 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 			'questions'      => array(),
 		);
 
+		$link    = WPCPM_Request::posted_text( 'wpcpm_course_url' );
+		$warning = '';
+
+		if ( '' !== $link ) {
+			$definition['course_url'] = esc_url_raw( $link );
+			$course                   = WPCPM_Learn::resolve( $definition['course_url'] );
+
+			if ( is_wp_error( $course ) ) {
+				// Kept as typed, with the warning (4.2): the link is shown to students and can be
+				// put right on the track's page.
+				$warning = sprintf(
+					/* translators: %s: why the link did not resolve. */
+					__( 'The Learn course link did not resolve: %s', 'wpcredits-program-manager' ),
+					$course->get_error_message()
+				);
+			} else {
+				$definition['learn_course_id'] = (int) $course['id'];
+
+				if ( '' === $definition['label'] ) {
+					$definition['label'] = (string) $course['title'];
+				}
+			}
+		}
+
 		$errors = WPCPM_Track_Store::check( 0, $definition );
 
 		if ( array() !== $errors ) {
@@ -790,8 +816,8 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 
 		$this->redirect_back(
 			array(
-				'status'  => 'success',
-				'message' => __( 'The track was created, as a draft with no questions yet. Add them below. Nothing reaches students until it is published.', 'wpcredits-program-manager' ),
+				'status'  => '' === $warning ? 'success' : 'warning',
+				'message' => implode( ' ', array_filter( array( __( 'The track was created, as a draft with no questions yet.', 'wpcredits-program-manager' ), $warning, __( 'Add them below. Nothing reaches students until it is published.', 'wpcredits-program-manager' ) ) ) ),
 			),
 			array( 'wpcpm_track' => (int) $created )
 		);
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-builder.php`

Expected: `bin/test-track-builder.php` ends `ALL PASS (244 checks)`. 

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
git commit -m "Track Builder T3c: New track from a Learn course link"
```

---

### Task 7: Lessons matched again when the course changes

**Files:**
- Modify: `includes/tools/class-wpcpm-track-builder.php` (`rematch_lessons()`, `question_names()`; `handle_save()` runs the re-match)
- Test: `bin/test-track-builder.php`

**Interfaces:**
- Consumes: Task 2's `rematch()`; Task 1's `structure()`.
- Produces: `rematch_lessons( array &$definition, array $stored )`, private, answering `message` for the notice, or '', and `cleared`, how many questions lost their lesson: "The course changed. Matched to the new course's lessons by heading: %s. No longer pointing at a lesson: %s." with the questions named by what the student reads (`question_names()`, falling back to the column), or "The course changed, but its lessons could not be read from Learn, so the questions keep the lessons they had; save the track again once Learn answers." `handle_save()` joins the message into its notice and uses status `warning` when any question lost its lesson.

Decision 33's rule on the save. The re-match runs only when the save resolved the link to a different course, so an ordinary save touches nothing, and only when the new course's lessons could be read; when they cannot, the questions keep what they had and the notice says to save again, because clearing lessons on a Learn outage would lose work nobody asked to lose (what this plan decides, 5). In execution the final review found that promise unkeepable, since the new course was already stored; the fix wave after Task 12 holds the course change back instead (what this plan decides, 5, as corrected). A match takes the new lesson's id; the rest lose theirs and are named, since a person who changed the course wants to know which questions still report on one; questions with no lesson are left alone.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-track-builder.php b/bin/test-track-builder.php
index 57156a7..0b4e541 100644
--- a/bin/test-track-builder.php
+++ b/bin/test-track-builder.php
@@ -3008,5 +3008,81 @@ $_POST = array();
 $GLOBALS['transients'] = array();
 $GLOBALS['http']       = array();
 
+
+echo "\n=== Lessons matched again when the course changes (T3c) ===\n";
+
+WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
+WPCPM_Track_Store::$tracks[13]['definition']['course_url']      = 'https://learn.wordpress.org/course/marketing/';
+WPCPM_Track_Store::$tracks[13]['definition']['learn_course_id'] = 500001;
+WPCPM_Track_Store::$tracks[13]['definition']['questions']['Slack name']['lead']            = 'Join global Slack';
+WPCPM_Track_Store::$tracks[13]['definition']['questions']['Slack name']['learn_lesson_id'] = 4001;
+$GLOBALS['transients'] = array();
+$GLOBALS['http']       = array();
+course_answer( 'marketing', 500001, 'Marketing' );
+course_answer( 'marketing-2', 500002, 'Marketing, second edition' );
+structure_answer( 500002, array( 'Onboarding' => array( array( 5001, "Join Global Slack" ), array( 5002, 'Something else' ) ) ) );
+$GLOBALS['nonce']          = WPCPM_Track_Builder::ACTION_SAVE;
+WPCPM_Track_Store::$saved  = array();
+WPCPM_Track_Store::$errors = array();
+$_POST = array( 'track' => 13, 'wpcpm_label' => 'Marketing Track', 'wpcpm_status' => 'Marketing Track', 'wpcpm_key' => 'marketing', 'wpcpm_course_url' => 'https://learn.wordpress.org/course/marketing-2/', 'wpcpm_hours_target' => '', 'wpcpm_hue' => 'blue' );
+
+$changed = outcome( array( $tool, 'handle_save' ) );
+
+ck( 'a save that resolves the link to another course matches each lessoned question by heading, clears the rest, and says which',
+    array(
+        $changed,
+        WPCPM_Track_Store::$saved[13]['learn_course_id'],
+        WPCPM_Track_Store::$saved[13]['questions']['Slack name']['learn_lesson_id'],
+        array_key_exists( 'learn_lesson_id', WPCPM_Track_Store::$saved[13]['questions']['Your blog'] ),
+        WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['status'],
+        false !== strpos( WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['message'], "The course changed. Matched to the new course's lessons by heading: Your Slack name. No longer pointing at a lesson: Your blog." ),
+    ),
+    array( 'redirect', 500002, 5001, false, 'warning', true ) );
+
+// The stand-in keeps what a save stored, as the real store does, so each scenario starts from the
+// same track again.
+WPCPM_Track_Store::$tracks[13]['definition'] = editable_track()['definition'];
+WPCPM_Track_Store::$tracks[13]['definition']['course_url']      = 'https://learn.wordpress.org/course/marketing/';
+WPCPM_Track_Store::$tracks[13]['definition']['learn_course_id'] = 500001;
+WPCPM_Track_Store::$tracks[13]['definition']['questions']['Slack name']['lead']            = 'Join global Slack';
+WPCPM_Track_Store::$tracks[13]['definition']['questions']['Slack name']['learn_lesson_id'] = 4001;
+WPCPM_Track_Store::$saved  = array();
+$_POST['wpcpm_course_url'] = 'https://learn.wordpress.org/course/marketing/';
+$same = outcome( array( $tool, 'handle_save' ) );
+
+ck( 'the same course again leaves every lesson as it was',
+    array( $same, WPCPM_Track_Store::$saved[13]['questions']['Slack name']['learn_lesson_id'], WPCPM_Track_Store::$saved[13]['questions']['Your blog']['learn_lesson_id'], WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['status'] ),
+    array( 'redirect', 4001, 4242, 'success' ) );
+
+course_answer( 'marketing-3', 500003, 'Marketing, third edition' );
+WPCPM_Track_Store::$tracks[13]['definition'] = editable_track()['definition'];
+WPCPM_Track_Store::$tracks[13]['definition']['course_url']      = 'https://learn.wordpress.org/course/marketing/';
+WPCPM_Track_Store::$tracks[13]['definition']['learn_course_id'] = 500001;
+WPCPM_Track_Store::$tracks[13]['definition']['questions']['Slack name']['lead']            = 'Join global Slack';
+WPCPM_Track_Store::$tracks[13]['definition']['questions']['Slack name']['learn_lesson_id'] = 4001;
+WPCPM_Track_Store::$saved  = array();
+$_POST['wpcpm_course_url'] = 'https://learn.wordpress.org/course/marketing-3/';
+$unread = outcome( array( $tool, 'handle_save' ) );
+
+ck( 'a new course whose lessons cannot be read keeps every lesson, and the notice says to save again once Learn answers',
+    array( $unread, WPCPM_Track_Store::$saved[13]['learn_course_id'], WPCPM_Track_Store::$saved[13]['questions']['Slack name']['learn_lesson_id'], WPCPM_Track_Store::$saved[13]['questions']['Your blog']['learn_lesson_id'], false !== strpos( WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ]['message'], 'could not be read from Learn' ) ),
+    array( 'redirect', 500003, 4001, 4242, true ) );
+
+WPCPM_Track_Store::$tracks[13]['definition'] = editable_track()['definition'];
+WPCPM_Track_Store::$tracks[13]['definition']['course_url']      = 'https://learn.wordpress.org/course/marketing/';
+WPCPM_Track_Store::$tracks[13]['definition']['learn_course_id'] = 500001;
+WPCPM_Track_Store::$tracks[13]['definition']['questions']['Slack name']['lead']            = 'Join global Slack';
+WPCPM_Track_Store::$tracks[13]['definition']['questions']['Slack name']['learn_lesson_id'] = 4001;
+WPCPM_Track_Store::$saved  = array();
+$_POST['wpcpm_course_url'] = '';
+$cleared = outcome( array( $tool, 'handle_save' ) );
+
+ck( 'clearing the course leaves the lessons alone', array( $cleared, WPCPM_Track_Store::$saved[13]['questions']['Slack name']['learn_lesson_id'], array_key_exists( 'learn_course_id', WPCPM_Track_Store::$saved[13] ) ), array( 'redirect', 4001, false ) );
+
+$_POST = array();
+$GLOBALS['transients'] = array();
+$GLOBALS['http']       = array();
+WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
+
 printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
 exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-track-builder.php`

Expected: `bin/test-track-builder.php` stops with `FAIL a save that resolves the link to another course matches each lessoned question by heading, clears the rest, and says which`. `handle_save()` does not compare the courses yet, so the questions keep their old lessons and the notice says nothing of a change.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/tools/class-wpcpm-track-builder.php b/includes/tools/class-wpcpm-track-builder.php
index 6907e2c..1b7eaea 100644
--- a/includes/tools/class-wpcpm-track-builder.php
+++ b/includes/tools/class-wpcpm-track-builder.php
@@ -657,6 +657,7 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 
 		$definition = self::posted_definition( $stored );
 		$warning    = self::resolve_course( $definition, $stored );
+		$rematched  = self::rematch_lessons( $definition, $stored );
 		$errors     = WPCPM_Track_Store::check( $post_id, $definition );
 
 		if ( array() !== $errors ) {
@@ -669,11 +670,12 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 			$this->refuse( $post_id, $saved->get_error_message(), $definition );
 		}
 
-		// A link that did not resolve saves all the same (4.2), and the notice says so.
+		// A link that did not resolve saves all the same (4.2), and the notice says so; so does a
+		// course change that left questions with no lesson.
 		$this->redirect_back(
 			array(
-				'status'  => '' === $warning ? 'success' : 'warning',
-				'message' => implode( ' ', array_filter( array( __( 'The track was saved.', 'wpcredits-program-manager' ), $warning, __( 'Nothing reaches students until it is published.', 'wpcredits-program-manager' ) ) ) ),
+				'status'  => '' === $warning && 0 === $rematched['cleared'] ? 'success' : 'warning',
+				'message' => implode( ' ', array_filter( array( __( 'The track was saved.', 'wpcredits-program-manager' ), $warning, $rematched['message'], __( 'Nothing reaches students until it is published.', 'wpcredits-program-manager' ) ) ) ),
 			),
 			array( 'wpcpm_track' => $post_id )
 		);
@@ -959,6 +961,105 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		);
 	}
 
+	/**
+	 * When the save resolved the link to another course, match every question that carried a
+	 * lesson against the new course by heading (decision 33).
+	 *
+	 * A match takes the new lesson; the rest lose theirs and are named, since a person who changed
+	 * the course wants to know which questions still report on one. Questions with no lesson are
+	 * left alone, and so is everything when the course did not change, was cleared, or its lessons
+	 * cannot be read from Learn just now, in which case the notice says to save again.
+	 *
+	 * @param array $definition The posted definition, by reference.
+	 * @param array $stored     The definition as it was stored.
+	 * @return array `message` for the notice, or '', and `cleared`, how many questions lost a lesson.
+	 */
+	private static function rematch_lessons( array &$definition, array $stored ) {
+		$none = array(
+			'message' => '',
+			'cleared' => 0,
+		);
+		$now  = isset( $definition['learn_course_id'] ) ? (int) $definition['learn_course_id'] : 0;
+		$was  = isset( $stored['learn_course_id'] ) ? (int) $stored['learn_course_id'] : 0;
+
+		if ( $now <= 0 || $now === $was ) {
+			return $none;
+		}
+
+		$questions = isset( $definition['questions'] ) && is_array( $definition['questions'] ) ? $definition['questions'] : array();
+		$lessoned  = false;
+
+		foreach ( $questions as $spec ) {
+			if ( is_array( $spec ) && isset( $spec['learn_lesson_id'] ) ) {
+				$lessoned = true;
+				break;
+			}
+		}
+
+		if ( ! $lessoned ) {
+			return $none;
+		}
+
+		$modules = WPCPM_Learn::structure( $now );
+
+		if ( is_wp_error( $modules ) ) {
+			return array(
+				'message' => __( 'The course changed, but its lessons could not be read from Learn, so the questions keep the lessons they had; save the track again once Learn answers.', 'wpcredits-program-manager' ),
+				'cleared' => 0,
+			);
+		}
+
+		$lessons = array();
+
+		foreach ( $modules as $module ) {
+			foreach ( isset( $module['lessons'] ) && is_array( $module['lessons'] ) ? $module['lessons'] : array() as $lesson ) {
+				$lessons[] = $lesson;
+			}
+		}
+
+		$result                  = WPCPM_Track_Questions::rematch( $questions, $lessons );
+		$definition['questions'] = $result['questions'];
+		$parts                   = array( __( 'The course changed.', 'wpcredits-program-manager' ) );
+
+		if ( array() !== $result['matched'] ) {
+			$parts[] = sprintf(
+				/* translators: %s: the questions, comma-separated. */
+				__( 'Matched to the new course\'s lessons by heading: %s.', 'wpcredits-program-manager' ),
+				implode( ', ', self::question_names( $questions, $result['matched'] ) )
+			);
+		}
+
+		if ( array() !== $result['cleared'] ) {
+			$parts[] = sprintf(
+				/* translators: %s: the questions, comma-separated. */
+				__( 'No longer pointing at a lesson: %s.', 'wpcredits-program-manager' ),
+				implode( ', ', self::question_names( $questions, $result['cleared'] ) )
+			);
+		}
+
+		return array(
+			'message' => implode( ' ', $parts ),
+			'cleared' => count( $result['cleared'] ),
+		);
+	}
+
+	/**
+	 * Questions by the words a student reads, falling back to the column, for a notice.
+	 *
+	 * @param array    $questions Column => spec.
+	 * @param string[] $columns   The columns to name.
+	 * @return string[]
+	 */
+	private static function question_names( array $questions, array $columns ) {
+		$names = array();
+
+		foreach ( $columns as $column ) {
+			$names[] = isset( $questions[ $column ]['label'] ) && '' !== (string) $questions[ $column ]['label'] ? (string) $questions[ $column ]['label'] : (string) $column;
+		}
+
+		return $names;
+	}
+
 	/**
 	 * Read the track's Learn course again: forget what was kept and come back to the track, whose
 	 * page reads it afresh (decision 31). For the day a lesson is added on Learn.
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-track-builder.php`

Expected: `bin/test-track-builder.php` ends `ALL PASS (248 checks)`. 

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
git add bin/test-track-builder.php includes/tools/class-wpcpm-track-builder.php
git commit -m "Track Builder T3c: lessons matched again when the course changes"
```

---

### Task 8: T3b's leftovers: one date helper, a stale preview, and forget_counts()

**Files:**
- Modify: `includes/tools/class-wpcpm-track-builder-screen.php` (`when_and_who()` public; `published_line()` through it; `preview_line()` takes `stale`)
- Modify: `includes/tools/class-wpcpm-track-history-screen.php` (its own `when_and_who()` goes)
- Modify: `includes/tools/class-wpcpm-track-builder.php` (`preview()` carries `stale`; `stale()` shared with `rows()`)
- Modify: `includes/modules/class-wpcpm-students-sync.php` (`forget_counts()` goes)
- Test: `bin/test-track-builder.php`, `bin/test-students-sync.php`

**Interfaces:**
- Consumes: `get_userdata()`, `wp_date()`; `WPCPM_Track_Store::seeds()`, `published()`, `source()`.
- Produces: `WPCPM_Track_Builder_Screen::when_and_who( $at, $by )`, public: "%1$s by %2$s" with `wp_date( 'Y-m-d H:i', $at )` and the account's display name, "somebody since removed" for an account that is gone, "the site itself" for user 0; the History screen calls it for its saves and its log. `WPCPM_Track_Builder::preview()` carries `stale`, true for a built-in draft whose definition is not the seed the plugin ships, through a private `stale( array $definition, $source, $published, array $seeds )` that `rows()` shares; `preview_line( $state, $source, $stale = false )` answers "This built-in draft has fallen behind the plugin's own form: refresh it from the plugin on the track list first, since until then this preview is not what students see." when it is. `WPCPM_Students_Sync::forget_counts()` is gone; the students-sync suite's `let_counts_go()` lets the held counts go through a closure bound to the class.

The three leftovers decision 33 names. History's `when_and_who()` was a copy of the list's `published_line()`, same format, same date, same fallback, and both said "somebody since removed" of a save nobody signed in made, which WP-CLI and the seeding leave and which was untrue: one public helper on the builder screen says it for all three places, and user 0 reads "the site itself" (what this plan decides, 10). The preview of a built-in draft that fell behind the seed claimed to be what the form draws, when the list beside it offered "Refresh from the plugin" for exactly that reason; `stale()` is one rule now, shared by the list's rows and the preview, and the preview says to refresh first.

`forget_counts()` had no caller in production since T2c; its one user was a suite resetting a cache between checks, which is a test's business, so the suite does it through a closure bound to the class and the method goes (what this plan decides, 11). Not `ReflectionProperty::setAccessible()`, which would do the same: PHP 8.1 deprecates it and 8.5 prints a line about it on every run, which the battery would read as noise.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-students-sync.php b/bin/test-students-sync.php
index 6b37105..ba6821b 100644
--- a/bin/test-students-sync.php
+++ b/bin/test-students-sync.php
@@ -1385,9 +1385,24 @@ ck( 'every status is counted in one answer',
 ck( 'and the statuses the rest of this suite set up are in the same answer, so it is one walk for all of them',
     array( isset( $all_counts['In Sensei'] ), count( $all_counts ) > 2 ), array( true, true ) );
 
+/**
+ * Let the held counts go, as a new request would: the answer lives for one request, and nothing in
+ * production lets it go before then (T3c dropped the unused forget_counts()).
+ */
+function let_counts_go() {
+	$let_go = Closure::bind(
+		static function () {
+			self::$counts = null;
+		},
+		null,
+		'WPCPM_Students_Sync'
+	);
+	$let_go();
+}
+
 // The track list asks once per row, and a query per row grew with the roster (the T2b
 // whole-branch review). Four rows now cost one walk, not four.
-WPCPM_Students_Sync::forget_counts();
+let_counts_go();
 $GLOBALS['user_queries'] = 0;
 
 foreach ( array( 'Counting Track', 'Other Track', 'Writing Track', 'Another Track' ) as $one ) {
@@ -1401,7 +1416,9 @@ update_user_meta( 903, WPCPM_Students_Sync::META_PROGRAM, array( 'program' => 'C
 
 ck( 'the held answer stands until it is let go', WPCPM_Students_Sync::count_on_status( 'Counting Track' ), 2 );
 
-WPCPM_Students_Sync::forget_counts();
+let_counts_go();
+
+ck( 'and nothing in the class lets it go by hand any more', method_exists( 'WPCPM_Students_Sync', 'forget_counts' ), false );
 
 ck( 'and then the next read walks again', WPCPM_Students_Sync::count_on_status( 'Counting Track' ), 3 );
 
diff --git a/bin/test-track-builder.php b/bin/test-track-builder.php
index 0b4e541..b84e2d8 100644
--- a/bin/test-track-builder.php
+++ b/bin/test-track-builder.php
@@ -2204,6 +2204,7 @@ ck( 'the builder hands the view the draft compiled as the live site compiles it:
         ),
         'state'  => 'draft',
         'source' => 'definition',
+        'stale'  => false,
     ) );
 
 $GLOBALS['enqueued'] = array();
@@ -3084,5 +3085,57 @@ $GLOBALS['transients'] = array();
 $GLOBALS['http']       = array();
 WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
 
+
+echo "\n=== T3b's leftovers: one date helper, and a stale draft's preview (T3c) ===\n";
+
+$GLOBALS['users'] = array( 7 => 'A Manager' );
+
+ck( 'one helper says when and who for the list, History and the log: a person, somebody since removed, and the site itself for a save with nobody signed in',
+    array( WPCPM_Track_Builder_Screen::when_and_who( 1788000000, 7 ), WPCPM_Track_Builder_Screen::when_and_who( 1788000000, 999 ), WPCPM_Track_Builder_Screen::when_and_who( 1788000000, 0 ) ),
+    array( gmdate( 'Y-m-d H:i', 1788000000 ) . ' by A Manager', gmdate( 'Y-m-d H:i', 1788000000 ) . ' by somebody since removed', gmdate( 'Y-m-d H:i', 1788000000 ) . ' by the site itself' ) );
+
+WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
+WPCPM_Track_Store::$tracks[13]['revisions'] = array( array( 'id' => 101, 'at' => 1788000000, 'by' => 0, 'definition' => WPCPM_Track_Store::$tracks[13]['definition'] ) );
+WPCPM_Track_Store::$tracks[13]['log']       = array( array( 'at' => 1788050000, 'by' => 0, 'did' => 'publish' ) );
+$_GET = array( 'wpcpm_history' => 13 );
+ob_start();
+$tool->render_admin_page();
+$by_site = ob_get_clean();
+$_GET = array();
+
+ck( 'History says so for a save and a publish by the site itself',
+    array( substr_count( $by_site, ' by the site itself</p>' ), substr_count( $by_site, '<li>Published, ' . gmdate( 'Y-m-d H:i', 1788050000 ) . ' by the site itself</li>' ) ), array( 1, 1 ) );
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
+);
+$stale_preview = WPCPM_Track_Builder::preview( 12 );
+$_GET = array( 'wpcpm_preview' => 12 );
+ob_start();
+$tool->render_admin_page();
+$stale_page = ob_get_clean();
+WPCPM_Track_Store::$tracks[12]['definition'] = WPCPM_Track_Store::seeds()['design'];
+$fresh_preview = WPCPM_Track_Builder::preview( 12 );
+ob_start();
+$tool->render_admin_page();
+$fresh_page = ob_get_clean();
+$_GET = array();
+
+ck( 'a built-in draft that fell behind the plugin\'s form says so on its preview, and one that matches the seed says what the form draws',
+    array(
+        $stale_preview['stale'], false !== strpos( $stale_page, 'has fallen behind the plugin' ), false !== strpos( $stale_page, 'this definition is what that form draws' ),
+        $fresh_preview['stale'], false !== strpos( $fresh_page, 'this definition is what that form draws' ),
+    ),
+    array( true, true, false, false, true ) );
+
+WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
+
 printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
 exit( $fail ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-students-sync.php` and `php bin/test-track-builder.php`

Expected: `bin/test-students-sync.php` stops with `FAIL and nothing in the class lets it go by hand any more`; `bin/test-track-builder.php` stops with `FAIL the builder hands the view the draft compiled as the live site compiles it: the authoring properties gone, the rest as stored`. The compiled-preview check wants `stale` in what `preview()` hands over, the students-sync suite finds `forget_counts()` still on the class, and the leftovers section then stops on `when_and_who()`, which the builder screen does not have yet.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/modules/class-wpcpm-students-sync.php b/includes/modules/class-wpcpm-students-sync.php
index dcd58d1..b517e63 100644
--- a/includes/modules/class-wpcpm-students-sync.php
+++ b/includes/modules/class-wpcpm-students-sync.php
@@ -2549,15 +2549,6 @@ class WPCPM_Students_Sync {
 		return $counts;
 	}
 
-	/**
-	 * Forget the counted statuses, for a process that changes them and reads them again.
-	 *
-	 * @return void
-	 */
-	public static function forget_counts() {
-		self::$counts = null;
-	}
-
 	/**
 	 * The contact card for a student's mentor.
 	 *
diff --git a/includes/tools/class-wpcpm-track-builder-screen.php b/includes/tools/class-wpcpm-track-builder-screen.php
index 14de9dc..1925482 100644
--- a/includes/tools/class-wpcpm-track-builder-screen.php
+++ b/includes/tools/class-wpcpm-track-builder-screen.php
@@ -323,7 +323,7 @@ final class WPCPM_Track_Builder_Screen {
 		echo '<p class="wpcpm-tracks__preview-note">';
 		echo esc_html__( 'The form as a student sees it, with empty answers and no student\'s record. Nothing typed here is kept: there is no Save button and no form behind the controls.', 'wpcredits-program-manager' );
 		echo ' ';
-		echo esc_html( self::preview_line( $state, $source ) );
+		echo esc_html( self::preview_line( $state, $source, ! empty( $args['stale'] ) ) );
 		echo '</p>';
 
 		if ( array() === $fields ) {
@@ -342,9 +342,16 @@ final class WPCPM_Track_Builder_Screen {
 	 *
 	 * @param string $state  The track's state.
 	 * @param string $source `builtin` while a built-in track runs from its PHP, else `definition`.
+	 * @param bool   $stale  Whether a built-in draft has fallen behind the plugin's seed.
 	 * @return string
 	 */
-	private static function preview_line( $state, $source ) {
+	private static function preview_line( $state, $source, $stale = false ) {
+		// A built-in draft that fell behind the seed the plugin ships is not what the PHP draws
+		// until it is refreshed (the T3b final review).
+		if ( 'builtin' === $source && $stale ) {
+			return __( 'This built-in draft has fallen behind the plugin\'s own form: refresh it from the plugin on the track list first, since until then this preview is not what students see.', 'wpcredits-program-manager' );
+		}
+
 		if ( 'builtin' === $source ) {
 			return __( 'This track runs from its hand-written form, and this definition is what that form draws.', 'wpcredits-program-manager' );
 		}
@@ -1055,13 +1062,35 @@ final class WPCPM_Track_Builder_Screen {
 			return __( 'Never', 'wpcredits-program-manager' );
 		}
 
-		$user = get_userdata( isset( $row['published_by'] ) ? (int) $row['published_by'] : 0 );
+		return self::when_and_who( $at, isset( $row['published_by'] ) ? (int) $row['published_by'] : 0 );
+	}
+
+	/**
+	 * A date and time, and who: the words the list, History's saves and the publish log share
+	 * (the T3b final review, which found the two screens each keeping a copy).
+	 *
+	 * A save with nobody signed in, which WP-CLI and the seeding make, is the site's own; an
+	 * account since deleted is said to be gone.
+	 *
+	 * @param int $at A Unix timestamp.
+	 * @param int $by A user ID, or 0 for nobody.
+	 * @return string
+	 */
+	public static function when_and_who( $at, $by ) {
+		$by = (int) $by;
+
+		if ( $by <= 0 ) {
+			$who = __( 'the site itself', 'wpcredits-program-manager' );
+		} else {
+			$user = get_userdata( $by );
+			$who  = $user ? $user->display_name : __( 'somebody since removed', 'wpcredits-program-manager' );
+		}
 
 		return sprintf(
-			/* translators: 1: a date and time, 2: who published the track. */
+			/* translators: 1: a date and time, 2: who saved or published the track. */
 			__( '%1$s by %2$s', 'wpcredits-program-manager' ),
-			wp_date( 'Y-m-d H:i', $at ),
-			$user ? $user->display_name : __( 'somebody since removed', 'wpcredits-program-manager' )
+			wp_date( 'Y-m-d H:i', (int) $at ),
+			$who
 		);
 	}
 
diff --git a/includes/tools/class-wpcpm-track-builder.php b/includes/tools/class-wpcpm-track-builder.php
index 1b7eaea..aabae48 100644
--- a/includes/tools/class-wpcpm-track-builder.php
+++ b/includes/tools/class-wpcpm-track-builder.php
@@ -222,7 +222,7 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 				'skipped'        => isset( $skipped[ $post_id ] ) ? (array) $skipped[ $post_id ] : array(),
 				'equivalence'    => WPCPM_Track_Store::equivalence( $post_id ),
 				'switched'       => WPCPM_Track_Store::switched( $post_id ),
-				'stale'          => 'builtin' === $source && ! is_array( $published ) && isset( $seeds[ $key ] ) && $seeds[ $key ] !== $definition,
+				'stale'          => self::stale( $definition, $source, $published, $seeds ),
 				// From the log, not the state: an unpublished track is a draft again and is
 				// still the record of what was created in the base (decision 25).
 				'ever_published' => WPCPM_Track_Store::ever_published( $post_id ),
@@ -299,15 +299,35 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		$definition = WPCPM_Track_Store::get( $post_id );
 		$definition = is_array( $definition ) ? $definition : array();
 
+		$source = WPCPM_Track_Store::source( $post_id );
+		$seeds  = WPCPM_Track_Store::seeds();
+
 		return array(
 			'track'  => $post_id,
 			'label'  => isset( $definition['label'] ) ? (string) $definition['label'] : '',
 			'fields' => WPCPM_Track_Definition::compile_fields( $definition ),
 			'state'  => WPCPM_Track_Store::state( $post_id ),
-			'source' => WPCPM_Track_Store::source( $post_id ),
+			'source' => $source,
+			'stale'  => self::stale( $definition, $source, WPCPM_Track_Store::published( $post_id ), $seeds ),
 		);
 	}
 
+	/**
+	 * Whether a built-in draft has fallen behind the seed the plugin now ships (decision 12): what
+	 * the list's Refresh from the plugin puts right, and what a preview of it warns about.
+	 *
+	 * @param array      $definition The track's definition.
+	 * @param string     $source     `builtin` while its PHP runs it.
+	 * @param array|null $published  Its published copy, or null.
+	 * @param array      $seeds      The seeds, by key.
+	 * @return bool
+	 */
+	private static function stale( array $definition, $source, $published, array $seeds ) {
+		$key = isset( $definition['key'] ) ? (string) $definition['key'] : '';
+
+		return 'builtin' === $source && ! is_array( $published ) && isset( $seeds[ $key ] ) && $seeds[ $key ] !== $definition;
+	}
+
 	/**
 	 * What History shows (the design's decision 28), read here so the screen asks the store nothing.
 	 *
diff --git a/includes/tools/class-wpcpm-track-history-screen.php b/includes/tools/class-wpcpm-track-history-screen.php
index e7aa71e..a7b71ec 100644
--- a/includes/tools/class-wpcpm-track-history-screen.php
+++ b/includes/tools/class-wpcpm-track-history-screen.php
@@ -113,7 +113,7 @@ final class WPCPM_Track_History_Screen {
 
 		foreach ( $revisions as $revision ) {
 			echo '<li class="wpcpm-history__revision">';
-			echo '<p class="wpcpm-history__meta">' . esc_html( self::when_and_who( isset( $revision['at'] ) ? (int) $revision['at'] : 0, isset( $revision['by'] ) ? (int) $revision['by'] : 0 ) ) . '</p>';
+			echo '<p class="wpcpm-history__meta">' . esc_html( WPCPM_Track_Builder_Screen::when_and_who( isset( $revision['at'] ) ? (int) $revision['at'] : 0, isset( $revision['by'] ) ? (int) $revision['by'] : 0 ) ) . '</p>';
 
 			if ( isset( $revision['created'] ) && null !== $revision['created'] ) {
 				$count = (int) $revision['created'];
@@ -201,7 +201,7 @@ final class WPCPM_Track_History_Screen {
 					/* translators: 1: what happened, 2: a date and time, then who did it. */
 					__( '%1$s, %2$s', 'wpcredits-program-manager' ),
 					self::what( $entry, $items ),
-					self::when_and_who( isset( $entry['at'] ) ? (int) $entry['at'] : 0, isset( $entry['by'] ) ? (int) $entry['by'] : 0 )
+					WPCPM_Track_Builder_Screen::when_and_who( isset( $entry['at'] ) ? (int) $entry['at'] : 0, isset( $entry['by'] ) ? (int) $entry['by'] : 0 )
 				)
 			) . '</li>';
 		}
@@ -330,22 +330,4 @@ final class WPCPM_Track_History_Screen {
 
 		return $did;
 	}
-
-	/**
-	 * A date and time, and who: the words the track list uses for its "published" column.
-	 *
-	 * @param int $at A Unix timestamp.
-	 * @param int $by A user ID.
-	 * @return string
-	 */
-	private static function when_and_who( $at, $by ) {
-		$user = get_userdata( (int) $by );
-
-		return sprintf(
-			/* translators: 1: a date and time, 2: who saved or published the track. */
-			__( '%1$s by %2$s', 'wpcredits-program-manager' ),
-			wp_date( 'Y-m-d H:i', (int) $at ),
-			$user ? $user->display_name : __( 'somebody since removed', 'wpcredits-program-manager' )
-		);
-	}
 }
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-students-sync.php` and `php bin/test-track-builder.php`

Expected: `bin/test-students-sync.php` ends `ALL PASS (178 checks)` and `bin/test-track-builder.php` ends `ALL PASS (251 checks)`. 

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
git add bin/test-students-sync.php bin/test-track-builder.php includes/modules/class-wpcpm-students-sync.php includes/tools/class-wpcpm-track-builder-screen.php includes/tools/class-wpcpm-track-builder.php includes/tools/class-wpcpm-track-history-screen.php
git commit -m "Track Builder T3c: T3b's leftovers, the date helper, a stale preview and forget_counts()"
```

---

### Task 9: The small minors of the T3b reviews, and the checks they lacked

**Files:**
- Modify: `includes/tools/class-wpcpm-track-builder-screen.php` (the four links from one loop; `render_publish()`'s docblock names `source`)
- Modify: `includes/tools/class-wpcpm-track-editor-screen.php` (the stored control read once)
- Modify: `includes/tools/class-wpcpm-track-history-screen.php` (a dead half of the created guard)
- Modify: `includes/tools/class-wpcpm-track-builder.php` (`history()` reads the revisions zero-based; a note on the two unscoped selectors)
- Modify: `includes/tracks/class-wpcpm-track-diff.php` (a comment on the two orders; the decision number on `moved()`)
- Modify: `includes/modules/class-wpcpm-student-report-form.php` (the hours label guarded)
- Test: `bin/test-track-builder.php`, `bin/test-track-store.php`, `bin/test-track-diff.php`, `bin/test-report-form.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: no interface changes. `render_actions()` draws Edit, Duplicate, Preview and History from one loop, in that order and with the same markup; `render_question()` reads the stored control into `$stored_type` once and uses it where two more reads and `$fixed` were; `history()` runs `array_values()` over the revisions before comparing each with the next, so the store's list shape cannot break the pairing; `render_hours_field()` draws '' for a label that is not there rather than a notice; the checks: History's other log words (unpublish, both switches, and a word it has no words for printed as it is), a save that changed nothing, a removed and a moved column, a store that refuses to create the post on New track and on Duplicate (the store stand-in's `$refuse`), the built-in track's publish screen once published (its heading and paragraph, not the buttons alone), History on every row counted as Preview's check counts, a negative revisions limit, a revision whose meta is absent, a copy under a newer schema version, and the Designer Track check naming the class the remove button actually has.

The dozen small findings of the T3b ledger, taken as listed (decision 33). Every check here is written first and passes first: they pin behavior the code already has, which is what lets the refactors that follow be proven to change nothing, and the two suites that only gain checks (`bin/test-track-store.php`, `bin/test-track-diff.php`) gain coverage the reviews found missing.

What is left as it is, with the reason: the placeholder-only `%1$s, %2$s` in `render_log()` stays translatable, since it is there so a translator can put the date before what happened; `enqueue_assets()` still enqueues the report's sheet for any positive preview id, which is harmless since the page falls through to the list; `hues_in_use()` reads one definition per track on a New track or Duplicate press, a handful of tracks on one press; a site whose revisions cap is exactly the list's length prints both the cap line and "Older saves exist", both true; and a predecessor with no definition reads as everything added, which is the only honest reading of it. The `selected()` stub in `bin/test-report-form.php` moves next to `checked()`, where a person looks for it.

- [ ] **Step 1: Write the checks that pin what the code does today**

```diff
diff --git a/bin/test-report-form.php b/bin/test-report-form.php
index 6e0c5d4..b81e61a 100644
--- a/bin/test-report-form.php
+++ b/bin/test-report-form.php
@@ -75,9 +75,6 @@ function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['o
 function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
 function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
 function get_transient( $k ) { return $GLOBALS['opts'][ 'T_' . $k ] ?? false; }
-// The Designer Track's select control, which the preview is the first render here to reach: core's
-// selected() prints ` selected='selected'` when the two are equal, compared as strings.
-function selected( $selected, $current = true, $echo = true ) { $out = (string) $selected === (string) $current ? " selected='selected'" : ''; if ( $echo ) { echo $out; } return $out; }
 function set_transient( $k, $v, $e = 0 ) { $GLOBALS['opts'][ 'T_' . $k ] = $v; return true; }
 function delete_transient( $k ) { unset( $GLOBALS['opts'][ 'T_' . $k ] ); return true; }
 function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
@@ -125,6 +122,9 @@ function esc_url_raw( $url, $protocols = null ) {
 // Needed once the Developer Track's checkbox field renders live, in `$edit` below (phase two of
 // the type review, 1.94.6): the consent box still goes through the real `checked()` call.
 function checked( $a, $b = true, $echo = true ) { $r = ( (string) $a === (string) $b ) ? ' checked="checked"' : ''; if ( $echo ) { echo $r; } return $r; }
+// The Designer Track's select control, which the preview is the first render here to reach: core's
+// selected() prints ` selected='selected'` when the two are equal, compared as strings.
+function selected( $selected, $current = true, $echo = true ) { $out = (string) $selected === (string) $current ? " selected='selected'" : ''; if ( $echo ) { echo $out; } return $out; }
 function wp_kses_post( $s ) { return $s; }
 
 require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
@@ -1426,7 +1426,7 @@ WPCPM_Student_Report_Form::render_preview( WPCPM_Student_Report_Form::fields( WP
 $design = ob_get_clean();
 
 ck( 'the Designer Track\'s ten screenshot questions show their upload boxes, no picture and no remove form',
-	array( substr_count( $design, 'type="file"' ), substr_count( $design, '<img' ), substr_count( $design, 'wpcpm-report__remove' ) ),
+	array( substr_count( $design, 'type="file"' ), substr_count( $design, '<img' ), substr_count( $design, 'wpcpm-report__image-remove' ) ),
 	array( 10, 0, 0 ) );
 
 ck( 'a field set with no hours question draws no hours box',
diff --git a/bin/test-track-builder.php b/bin/test-track-builder.php
index b84e2d8..75e0919 100644
--- a/bin/test-track-builder.php
+++ b/bin/test-track-builder.php
@@ -311,7 +311,14 @@ class WPCPM_Track_Store {
 
 	public static $created = array();
 
+	/** A WP_Error that create() and duplicate() answer instead of a post, when a check sets one. */
+	public static $refuse = null;
+
 	public static function create( array $definition ) {
+		if ( self::$refuse instanceof WP_Error ) {
+			return self::$refuse;
+		}
+
 		self::$created[]      = $definition;
 		$new                  = 98;
 		self::$tracks[ $new ] = array( 'definition' => $definition, 'state' => 'draft', 'source' => 'definition', 'log' => array(), 'equivalence' => array( 'not_builtin' ), 'published' => null );
@@ -320,6 +327,10 @@ class WPCPM_Track_Store {
 	}
 
 	public static function duplicate( $from_id, array $definition ) {
+		if ( self::$refuse instanceof WP_Error ) {
+			return self::$refuse;
+		}
+
 		self::$duplicated[] = array( (int) $from_id, $definition );
 		$new                = 99;
 		self::$tracks[ $new ] = array( 'definition' => $definition, 'state' => 'draft', 'source' => 'definition', 'log' => array(), 'equivalence' => array( 'not_builtin' ), 'published' => null );
@@ -2489,7 +2500,18 @@ ck( 'a save that kept no definition is handed over with neither a diff nor a cou
     array( $gap['revisions'][1]['diff'], $gap['revisions'][1]['created'], $gap['revisions'][0]['diff']['added'] ),
     array( null, null, array( 'Hours', 'Slack name', 'Your blog' ) ) );
 
-WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
+// Two rows, one of them built in, as the Preview check has: History is a link on each.
+WPCPM_Track_Store::$tracks = array(
+	12 => array(
+		'definition'  => array( 'key' => 'design', 'status' => 'Designer Track', 'label' => 'Designer Track', 'course_url' => '', 'questions' => array() ),
+		'state'       => 'draft',
+		'source'      => 'builtin',
+		'log'         => array(),
+		'equivalence' => array( 'not_published' ),
+		'published'   => null,
+	),
+	13 => editable_track(),
+);
 
 ob_start();
 WPCPM_Track_Builder_Screen::render_list( array( 'rows' => WPCPM_Track_Builder::rows(), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
@@ -2498,9 +2520,9 @@ ob_start();
 WPCPM_Track_Builder_Screen::render_form( array( 'form' => WPCPM_Track_Builder::form( 13 ), 'url' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder', 'flash' => array() ) );
 $track_page = ob_get_clean();
 
-ck( 'History is offered on every row and beside Preview on the track\'s page',
-    array( substr_count( $rows_html, 'wpcpm_history=13">History</a>' ), substr_count( $track_page, 'wpcpm_preview=13">Preview</a> <a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_history=13">History</a>' ) ),
-    array( 1, 1 ) );
+ck( 'History is offered on every row, the built-in one included, and beside Preview on the track\'s page',
+    array( substr_count( $rows_html, '>History</a>' ), substr_count( $rows_html, 'wpcpm_history=12">History</a>' ), substr_count( $track_page, 'wpcpm_preview=13">Preview</a> <a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_history=13">History</a>' ) ),
+    array( 2, 1, 1 ) );
 
 
 echo "\n=== The words on a built-in row and on its publish screen (decision 29) ===\n";
@@ -2551,7 +2573,7 @@ $tool->render_admin_page();
 $own_publish = ob_get_clean();
 $_GET = array();
 
-ck( 'its publish screen is headed as the definition\'s, says the track keeps running from its form, and its buttons name the definition; a track of somebody\'s own keeps its words',
+ck( 'its publish screen is headed as the definition\'s, says the track keeps running from its form, and its buttons name the definition, published or not; a track of somebody\'s own keeps its words',
     array(
         false !== strpos( $builtin_publish, '<h2>Publishing the definition of Designer Track</h2>' ),
         false !== strpos( $builtin_publish, 'Publishing records its definition and changes nothing for students' ),
@@ -2560,11 +2582,13 @@ ck( 'its publish screen is headed as the definition\'s, says the track keeps run
         substr_count( $builtin_live, 'Unpublish the definition' ),
         substr_count( $builtin_live, 'Take it off the live site' ),
         substr_count( $builtin_live, 'Check it against Airtable' ),
+        false !== strpos( $builtin_live, '<h2>Publishing the definition of Designer Track</h2>' ),
+        false !== strpos( $builtin_live, 'keeps doing so. Publishing records its definition' ),
         false !== strpos( $own_publish, '<h2>Publishing Marketing Track</h2>' ),
         substr_count( $own_publish, 'Publish this track' ),
         false !== strpos( $own_publish, 'keeps doing so' ),
     ),
-    array( true, true, 1, 0, 1, 0, 1, true, 1, false ) );
+    array( true, true, 1, 0, 1, 0, 1, true, true, true, 1, false ) );
 
 
 echo "\n=== The editor's fold-ins: a locked question's rows and its notice, and every control through the real validator (decision 29) ===\n";
@@ -3137,5 +3161,76 @@ ck( 'a built-in draft that fell behind the plugin\'s form says so on its preview
 
 WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
 
+
+echo "\n=== T3b's leftovers: History's other words, and a store that refuses to create a post (T3c) ===\n";
+
+$GLOBALS['users'] = array( 7 => 'A Manager' );
+
+$c = array( 'schema_version' => 1, 'key' => 'marketing', 'status' => 'Marketing Track', 'label' => 'Marketing Track', 'hue' => 'pink', 'questions' => array(
+	'Hours'      => array( 'type' => 'number', 'label' => 'Hours', 'group' => 'hours', 'min' => 0, 'max' => 1000, 'step' => 1 ),
+	'Slack name' => array( 'type' => 'text', 'label' => 'Your Slack name', 'group' => 'onboarding' ),
+	'Your blog'  => array( 'type' => 'url', 'label' => 'Your blog', 'group' => 'onboarding' ),
+) );
+$b = $c;
+unset( $b['questions']['Slack name'] );
+$b['questions'] = array_reverse( $b['questions'], true );
+
+WPCPM_Track_Store::$tracks = array( 13 => editable_track() );
+WPCPM_Track_Store::$tracks[13]['definition'] = $b;
+WPCPM_Track_Store::$tracks[13]['revisions']  = array(
+	array( 'id' => 103, 'at' => 1788200000, 'by' => 7, 'definition' => $b ),
+	array( 'id' => 102, 'at' => 1788100000, 'by' => 7, 'definition' => $b ),
+	array( 'id' => 101, 'at' => 1788000000, 'by' => 7, 'definition' => $c ),
+);
+WPCPM_Track_Store::$tracks[13]['log'] = array(
+	array( 'at' => 1788050000, 'by' => 7, 'did' => 'publish' ),
+	array( 'at' => 1788060000, 'by' => 7, 'did' => 'unpublish' ),
+	array( 'at' => 1788070000, 'by' => 7, 'did' => 'switch_definition' ),
+	array( 'at' => 1788080000, 'by' => 7, 'did' => 'switch_builtin' ),
+	array( 'at' => 1788090000, 'by' => 7, 'did' => 'archive' ),
+);
+$_GET = array( 'wpcpm_history' => 13 );
+ob_start();
+$tool->render_admin_page();
+$words_page = ob_get_clean();
+$_GET = array();
+
+ck( 'History names an unpublish and both switches, prints a word it has no words for as it is, and says when a save changed nothing, removed a column or moved one',
+    array(
+        substr_count( $words_page, '<li>Unpublished, ' ),
+        substr_count( $words_page, '<li>Switched to run from its definition, ' ),
+        substr_count( $words_page, '<li>Switched back to its hand-written form, ' ),
+        substr_count( $words_page, '<li>archive, ' ),
+        substr_count( $words_page, 'Nothing in the definition changed.' ),
+        substr_count( $words_page, '<li>Removed: <code>Slack name</code></li>' ),
+        substr_count( $words_page, '<li>Moved: <code>' ),
+    ),
+    array( 1, 1, 1, 1, 1, 1, 1 ) );
+
+$GLOBALS['nonce']           = WPCPM_Track_Builder::ACTION_NEW;
+$GLOBALS['can_manage']      = true;
+WPCPM_Track_Store::$tracks  = array( 13 => editable_track() );
+WPCPM_Track_Store::$errors  = array();
+WPCPM_Track_Store::$created = array();
+WPCPM_Track_Store::$refuse  = new WP_Error( 'wpcpm_track_insert', 'The post could not be created.' );
+$refused_new = press_new( array( 'wpcpm_label' => 'Blank Track', 'wpcpm_status' => 'Blank Track', 'wpcpm_key' => 'blank' ) );
+
+$GLOBALS['nonce']              = WPCPM_Track_Builder::ACTION_DUPLICATE;
+WPCPM_Track_Store::$duplicated = array();
+$_POST                         = array( 'track' => 13, 'wpcpm_label' => 'A Copy', 'wpcpm_status' => 'Copied Track', 'wpcpm_key' => 'copy' );
+$refused_copy                  = array( outcome( array( $tool, 'handle_duplicate' ) ), $GLOBALS['last_redirect'], WPCPM_Flash::$set[ WPCPM_Track_Builder::FLASH ] );
+$_POST                         = array();
+WPCPM_Track_Store::$refuse     = null;
+
+ck( 'a store that refuses to create the post, on New track and on Duplicate, sends the form back with its reason and what was typed',
+    array(
+        $refused_new[0], $refused_new[1], $refused_new[2]['status'], $refused_new[2]['message'], $refused_new[2]['values']['key'], WPCPM_Track_Store::$created,
+        $refused_copy[0], $refused_copy[1], $refused_copy[2]['status'], $refused_copy[2]['message'], $refused_copy[2]['values']['label'], WPCPM_Track_Store::$duplicated,
+    ),
+    array(
+        'redirect', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_new=1', 'error', 'The post could not be created.', 'blank', array(),
+        'redirect', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_duplicate=13', 'error', 'The post could not be created.', 'A Copy', array(),
+    ) );
+
 printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
 exit( $fail ? 1 : 0 );
diff --git a/bin/test-track-diff.php b/bin/test-track-diff.php
index 05a36e7..3069ff1 100644
--- a/bin/test-track-diff.php
+++ b/bin/test-track-diff.php
@@ -73,6 +73,10 @@ ck( 'a stored 100 and a posted "100" are the same length limit, and a flag store
     )['same'],
     true );
 
+ck( 'a copy kept under a newer schema version is the same track: the version is the format\'s, not the track\'s',
+    WPCPM_Track_Diff::between( track(), changed( function ( &$t ) { $t['schema_version'] = 2; } ) )['same'],
+    true );
+
 echo "\n=== The track's own properties ===\n";
 
 ck( 'a renamed track and a changed hours target are named, in the order the properties are kept',
diff --git a/bin/test-track-store.php b/bin/test-track-store.php
index b05f725..163e23e 100644
--- a/bin/test-track-store.php
+++ b/bin/test-track-store.php
@@ -921,7 +921,18 @@ ck( 'a limit reads one more than it says, so the oldest shown still has its pred
     array( 3, 4, 4 ) );
 
 ck( 'a limit below one reads as one',
-    count( WPCPM_Track_Store::revisions( $hist, 0 ) ), 2 );
+    array( count( WPCPM_Track_Store::revisions( $hist, 0 ) ), count( WPCPM_Track_Store::revisions( $hist, -5 ) ) ), array( 2, 2 ) );
+
+// A revision whose own copy of the definition is gone reads as one that kept none, not as a
+// broken one: History says so of it (the T3b Task 2 review).
+$raw = $GLOBALS['pmeta'][ $all[2]['id'] ][ WPCPM_Track_Store::META_DEFINITION ];
+unset( $GLOBALS['pmeta'][ $all[2]['id'] ][ WPCPM_Track_Store::META_DEFINITION ] );
+
+ck( 'a revision whose meta is absent carries a null definition, its neighbors theirs',
+    array_map( function ( $r ) { return null === $r['definition'] ? null : $r['definition']['label']; }, WPCPM_Track_Store::revisions( $hist ) ),
+    array( 'History Track, renamed', 'History Track, renamed', null, 'History Track' ) );
+
+$GLOBALS['pmeta'][ $all[2]['id'] ][ WPCPM_Track_Store::META_DEFINITION ] = $raw;
 
 ck( 'a post that is not a track has no revisions to give',
     array( WPCPM_Track_Store::revisions( 987654 ), WPCPM_Track_Store::revisions( $all[0]['id'] ) ),
```

- [ ] **Step 2: Run them and watch them pass already**

Run: `php bin/test-report-form.php` and `php bin/test-track-builder.php` and `php bin/test-track-diff.php` and `php bin/test-track-store.php`

Expected: `bin/test-report-form.php` ends `ALL PASS (181 checks)` and `bin/test-track-builder.php` ends `ALL PASS (253 checks)` and `bin/test-track-diff.php` ends `ALL PASS (25 checks)` and `bin/test-track-store.php` ends `ALL PASS (164 checks)`. Every check passes before the code moves: they pin behavior the code already has, so that Step 3 can be proven to change nothing.

- [ ] **Step 3: Make the small changes**

```diff
diff --git a/includes/modules/class-wpcpm-student-report-form.php b/includes/modules/class-wpcpm-student-report-form.php
index 9360b43..ae0158c 100644
--- a/includes/modules/class-wpcpm-student-report-form.php
+++ b/includes/modules/class-wpcpm-student-report-form.php
@@ -1539,7 +1539,7 @@ class WPCPM_Student_Report_Form {
 		printf(
 			'<label class="wpcpm-hours__label" for="%1$s">%2$s</label>',
 			esc_attr( $id ),
-			esc_html( $spec['label'] )
+			esc_html( isset( $spec['label'] ) ? (string) $spec['label'] : '' )
 		);
 
 		echo '<span class="wpcpm-hours__entry">';
diff --git a/includes/tools/class-wpcpm-track-builder-screen.php b/includes/tools/class-wpcpm-track-builder-screen.php
index 1925482..b908e97 100644
--- a/includes/tools/class-wpcpm-track-builder-screen.php
+++ b/includes/tools/class-wpcpm-track-builder-screen.php
@@ -127,39 +127,22 @@ final class WPCPM_Track_Builder_Screen {
 	 * @param string $url The screen's URL.
 	 */
 	private static function render_actions( array $row, $url ) {
+		// Four links into this screen, on every row: Preview draws any track, a built-in one
+		// included, since its definition is what its PHP draws; History is the published copy
+		// against the draft, every save against the one before, and the publish log (decision 28).
 		if ( '' !== $url ) {
-			printf(
-				'<a href="%1$s">%2$s</a> ',
-				esc_url( add_query_arg( 'wpcpm_track', (int) $row['id'], $url ) ),
-				esc_html__( 'Edit', 'wpcredits-program-manager' )
-			);
-		}
-
-		if ( '' !== $url ) {
-			printf(
-				'<a href="%1$s">%2$s</a> ',
-				esc_url( add_query_arg( 'wpcpm_duplicate', (int) $row['id'], $url ) ),
-				esc_html__( 'Duplicate', 'wpcredits-program-manager' )
-			);
-		}
-
-		// Preview draws any track, a built-in one included: its definition is what its PHP draws.
-		if ( '' !== $url ) {
-			printf(
-				'<a href="%1$s">%2$s</a> ',
-				esc_url( add_query_arg( 'wpcpm_preview', (int) $row['id'], $url ) ),
-				esc_html__( 'Preview', 'wpcredits-program-manager' )
-			);
-		}
-
-		// History: the published copy against the draft, every save against the one before, and
-		// the publish log (decision 28).
-		if ( '' !== $url ) {
-			printf(
-				'<a href="%1$s">%2$s</a> ',
-				esc_url( add_query_arg( 'wpcpm_history', (int) $row['id'], $url ) ),
-				esc_html__( 'History', 'wpcredits-program-manager' )
-			);
+			foreach ( array(
+				'wpcpm_track'     => __( 'Edit', 'wpcredits-program-manager' ),
+				'wpcpm_duplicate' => __( 'Duplicate', 'wpcredits-program-manager' ),
+				'wpcpm_preview'   => __( 'Preview', 'wpcredits-program-manager' ),
+				'wpcpm_history'   => __( 'History', 'wpcredits-program-manager' ),
+			) as $arg => $words ) {
+				printf(
+					'<a href="%1$s">%2$s</a> ',
+					esc_url( add_query_arg( $arg, (int) $row['id'], $url ) ),
+					esc_html( $words )
+				);
+			}
 		}
 
 		// Publishing is a screen of its own: it has a preflight to read, a checklist to work
@@ -238,8 +221,9 @@ final class WPCPM_Track_Builder_Screen {
 	/**
 	 * The publish screen: what would happen, what a person has to do, and the button.
 	 *
-	 * @param array $args `track`, `label`, `state`, `preflight`, `checklist`, `can_make` (whether
-	 *                    a schema token is configured), the screen's `url` and the `flash`.
+	 * @param array $args `track`, `label`, `state`, `source` (`builtin` while the track runs from
+	 *                    its PHP), `preflight`, `checklist`, `can_make` (whether a schema token is
+	 *                    configured), the screen's `url` and the `flash`.
 	 */
 	public static function render_publish( array $args ) {
 		$track     = isset( $args['track'] ) ? (int) $args['track'] : 0;
diff --git a/includes/tools/class-wpcpm-track-builder.php b/includes/tools/class-wpcpm-track-builder.php
index aabae48..f9693dc 100644
--- a/includes/tools/class-wpcpm-track-builder.php
+++ b/includes/tools/class-wpcpm-track-builder.php
@@ -171,7 +171,9 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		// The preview draws the draft through the report form's own renderer, so it needs the sheet
 		// that dresses the form on the student's page, which nothing in wp-admin enqueues otherwise
 		// (decision 27). Registered first, as the calendar module does on `init`, so the handle
-		// resolves whichever order the modules booted in.
+		// resolves whichever order the modules booted in. Two selectors in those sheets are not
+		// scoped to the form, dashboard.css's `.screen-reader-text` and calendar.css's `.is-sending`
+		// family; nothing on this page carries either, so they are inert here (the T3b final review).
 		if ( WPCPM_Request::id( 'wpcpm_preview' ) > 0 ) {
 			WPCPM_Call_Calendar::register_assets();
 			wp_enqueue_style( WPCPM_Call_Calendar::STYLE );
@@ -362,6 +364,10 @@ class WPCPM_Track_Builder extends WPCPM_Tool {
 		// kept" sentence holds instead.
 		$pruned = $cap >= 0 && array() !== $kept && count( $kept ) >= $cap;
 
+		// Each entry is compared with the one at the next index, so the list is read zero-based
+		// whatever the store answered.
+		$kept = array_values( $kept );
+
 		foreach ( array_slice( $kept, 0, self::HISTORY_LIMIT ) as $i => $revision ) {
 			$entry = array(
 				'at'      => isset( $revision['at'] ) ? (int) $revision['at'] : 0,
diff --git a/includes/tools/class-wpcpm-track-editor-screen.php b/includes/tools/class-wpcpm-track-editor-screen.php
index 5c69a06..94d764c 100644
--- a/includes/tools/class-wpcpm-track-editor-screen.php
+++ b/includes/tools/class-wpcpm-track-editor-screen.php
@@ -153,7 +153,7 @@ class WPCPM_Track_Editor_Screen {
 		// so rows drawn for it would be the wrong rows (the design's decision 29). The identity
 		// block below posts the stored control back for the same reason.
 		if ( $locked ) {
-			$type = isset( $question['type'] ) ? (string) $question['type'] : '';
+			$type = $stored_type;
 		}
 
 		self::render_flash( $flash );
@@ -181,19 +181,17 @@ class WPCPM_Track_Editor_Screen {
 		echo '<div class="wpcpm-question__identity">';
 
 		if ( $locked ) {
-			// The stored control, not the typed one: a lock can be taken while this screen is
-			// open (publish the track in another tab), and the typed control shown and posted back
-			// would be the very change the handler refuses (the Task 7 review).
-			$fixed = isset( $question['type'] ) ? (string) $question['type'] : '';
-
+			// The stored control, not the typed one, is what is shown and posted back: a lock can be
+			// taken while this screen is open (publish the track in another tab), and the typed
+			// control would be the very change the handler refuses (the Task 7 review).
 			printf( '<input type="hidden" name="wpcpm_column" value="%s" />', esc_attr( $column ) );
-			printf( '<input type="hidden" name="wpcpm_type" value="%s" />', esc_attr( $fixed ) );
+			printf( '<input type="hidden" name="wpcpm_type" value="%s" />', esc_attr( $type ) );
 			printf(
 				'<p><strong>%1$s</strong> <code>%2$s</code><br /><strong>%3$s</strong> %4$s</p>',
 				esc_html__( 'Airtable column', 'wpcredits-program-manager' ),
 				esc_html( $column ),
 				esc_html( $labels['type'] ),
-				esc_html( isset( $controls[ $fixed ] ) ? $controls[ $fixed ] : $fixed )
+				esc_html( isset( $controls[ $type ] ) ? $controls[ $type ] : $type )
 			);
 			echo '<p class="wpcpm-question__locked">' . esc_html__( 'This question has been published, so its column, its control and its choices are fixed: the column in Airtable holds what students have written, in that shape. To ask it differently, remove it and add a new question with a column of its own.', 'wpcredits-program-manager' ) . '</p>';
 		} else {
diff --git a/includes/tools/class-wpcpm-track-history-screen.php b/includes/tools/class-wpcpm-track-history-screen.php
index a7b71ec..83253f1 100644
--- a/includes/tools/class-wpcpm-track-history-screen.php
+++ b/includes/tools/class-wpcpm-track-history-screen.php
@@ -115,7 +115,7 @@ final class WPCPM_Track_History_Screen {
 			echo '<li class="wpcpm-history__revision">';
 			echo '<p class="wpcpm-history__meta">' . esc_html( WPCPM_Track_Builder_Screen::when_and_who( isset( $revision['at'] ) ? (int) $revision['at'] : 0, isset( $revision['by'] ) ? (int) $revision['by'] : 0 ) ) . '</p>';
 
-			if ( isset( $revision['created'] ) && null !== $revision['created'] ) {
+			if ( isset( $revision['created'] ) ) {
 				$count = (int) $revision['created'];
 
 				if ( ! empty( $revision['pruned'] ) ) {
diff --git a/includes/tracks/class-wpcpm-track-diff.php b/includes/tracks/class-wpcpm-track-diff.php
index 15462bc..acd8fbe 100644
--- a/includes/tracks/class-wpcpm-track-diff.php
+++ b/includes/tracks/class-wpcpm-track-diff.php
@@ -64,6 +64,8 @@ final class WPCPM_Track_Diff {
 			}
 		}
 
+		// The same columns in each copy's own order: `$common` runs in the newer copy's, and the
+		// older copy's keys intersected with it run in the older copy's.
 		$moved = self::moved( array_values( array_intersect( array_keys( $old ), $common ) ), $common );
 
 		return array(
@@ -121,7 +123,8 @@ final class WPCPM_Track_Diff {
 
 	/**
 	 * The columns whose order changed: the common columns not in the longest common subsequence
-	 * of the two orders, so a question dragged past ten others is reported once, not eleven times.
+	 * of the two orders, so a question dragged past ten others is reported once, not eleven times
+	 * (the design's decision 28).
 	 *
 	 * @param string[] $older The common columns in the older copy's order.
 	 * @param string[] $newer The same columns in the newer copy's order.
```

- [ ] **Step 4: Run them again and watch them still pass**

Run: `php bin/test-report-form.php` and `php bin/test-track-builder.php` and `php bin/test-track-diff.php` and `php bin/test-track-store.php`

Expected: `bin/test-report-form.php` ends `ALL PASS (181 checks)` and `bin/test-track-builder.php` ends `ALL PASS (253 checks)` and `bin/test-track-diff.php` ends `ALL PASS (25 checks)` and `bin/test-track-store.php` ends `ALL PASS (164 checks)`.

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
git add bin/test-report-form.php bin/test-track-builder.php bin/test-track-diff.php bin/test-track-store.php includes/modules/class-wpcpm-student-report-form.php includes/tools/class-wpcpm-track-builder-screen.php includes/tools/class-wpcpm-track-builder.php includes/tools/class-wpcpm-track-editor-screen.php includes/tools/class-wpcpm-track-history-screen.php includes/tracks/class-wpcpm-track-diff.php
git commit -m "Track Builder T3c: the small minors of the T3b reviews, and the checks they lacked"
```

---

### Task 10: seed-tracks tries every seed, then exits non-zero naming the ones that failed

**Files:**
- Create: `bin/test-cli.php`
- Modify: `includes/class-wpcpm-cli.php` (`seed_tracks()`)

**Interfaces:**
- Consumes: `WPCPM_Track_Store::seed()`, key => the post created, false for a track already held, or a `WP_Error`; `WPCPM_Track_Store::compile()`; `WP_CLI::log()`, `warning()`, `success()` and `error()`.
- Produces: `seed_tracks()` warns about each seed that failed as before, logs the rest, compiles, and then, when any failed, ends through `WP_CLI::error( 'Not created: %s. The others are in the Track Builder; run the command again once the reason is put right.' )`, which prints and exits 1; with none failed the success line as before. The suite stands `WP_CLI` in with a line log and an `error()` that throws `ExitSignal`, stands the store in with `$seeded` and `$compiled`, and its `run_seed()` answers the exit code.

T2c's park, the last item of decision 33. A warning and a success line leave a shell or a cron line reading exit 0 of a run that created nothing, so the command now says so with its exit code, after trying every seed rather than stopping at the first, because a second run creates only what is missing and nothing is lost by going on (what this plan decides, 12). The compile still runs for what landed.

This is the first suite of the CLI class. `bin/test-handlers.php` skips that file because it expects `WP_CLI` to exist; this suite gives it one, and stands in nothing else the command touches.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-cli.php b/bin/test-cli.php
new file mode 100644
index 0000000..0fae3dd
--- /dev/null
+++ b/bin/test-cli.php
@@ -0,0 +1,124 @@
+<?php
+/**
+ * The WP-CLI command that needs no Airtable: `wp wpcredits seed-tracks` (Track Builder, phase T3c).
+ *
+ * WP_CLI is stood in for so that what the command prints and how it exits can be read, and the
+ * track store so that a seed can be made to fail. `WP_CLI::error()` exits the process; here it
+ * throws, and the runner reads the throw as exit code 1.
+ *
+ * Run from the plugin root:  php bin/test-cli.php
+ */
+
+if ( 'cli' !== PHP_SAPI ) {
+	exit( 1 );
+}
+
+define( 'ABSPATH', __DIR__ . '/' );
+
+function __( $text, $domain = '' ) { return $text; }
+function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
+
+class WP_Error {
+	private $code;
+	private $message;
+	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
+	public function get_error_code() { return $this->code; }
+	public function get_error_message() { return $this->message; }
+}
+
+/** What WP_CLI::error() does, as something a check can catch. */
+class ExitSignal extends Exception {}
+
+class WP_CLI {
+	public static $lines = array();
+	public static function log( $message ) { self::$lines[] = 'log: ' . $message; }
+	public static function warning( $message ) { self::$lines[] = 'warning: ' . $message; }
+	public static function success( $message ) { self::$lines[] = 'success: ' . $message; }
+	public static function error( $message ) { self::$lines[] = 'error: ' . $message; throw new ExitSignal( $message ); }
+}
+
+/** The store, answering whatever a check seeds: a post id, false for a track already held, or a WP_Error. */
+class WPCPM_Track_Store {
+	public static $seeded   = array();
+	public static $compiled = 0;
+	public static function seed() { return self::$seeded; }
+	public static function compile() { ++self::$compiled; }
+}
+
+require_once __DIR__ . '/../includes/class-wpcpm-cli.php';
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
+/** Run the command; its exit code. */
+function run_seed() {
+	WP_CLI::$lines               = array();
+	WPCPM_Track_Store::$compiled = 0;
+
+	try {
+		( new WPCPM_CLI() )->seed_tracks();
+	} catch ( ExitSignal $e ) {
+		return 1;
+	}
+
+	return 0;
+}
+
+echo "=== wp wpcredits seed-tracks ===\n";
+
+WPCPM_Track_Store::$seeded = array( '150h' => 41, 'sensei' => false, 'design' => 43, 'dev' => 44 );
+$exit                      = run_seed();
+
+ck( 'every seed is reported, created or already there, the definitions are compiled, and the command exits 0',
+    array( $exit, WP_CLI::$lines, WPCPM_Track_Store::$compiled ),
+    array(
+        0,
+        array(
+            'log: 150h   created as track 41, a draft marked built-in',
+            'log: sensei already in the Track Builder',
+            'log: design created as track 43, a draft marked built-in',
+            'log: dev    created as track 44, a draft marked built-in',
+            'success: The built-in tracks are in the Track Builder.',
+        ),
+        1,
+    ) );
+
+WPCPM_Track_Store::$seeded = array(
+	'150h'   => 41,
+	'sensei' => new WP_Error( 'wpcpm_track_insert', 'The post could not be created.' ),
+	'design' => new WP_Error( 'wpcpm_track_insert', 'The post could not be created.' ),
+	'dev'    => 44,
+);
+$exit                      = run_seed();
+
+ck( 'a seed that fails is warned about, the rest are still tried and compiled, and the command exits non-zero naming the ones that failed (decision 33)',
+    array( $exit, WP_CLI::$lines, WPCPM_Track_Store::$compiled ),
+    array(
+        1,
+        array(
+            'log: 150h   created as track 41, a draft marked built-in',
+            'warning: sensei: The post could not be created.',
+            'warning: design: The post could not be created.',
+            'log: dev    created as track 44, a draft marked built-in',
+            'error: Not created: sensei, design. The others are in the Track Builder; run the command again once the reason is put right.',
+        ),
+        1,
+    ) );
+
+printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );
+
+exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php bin/test-cli.php`

Expected: `bin/test-cli.php` stops with `FAIL a seed that fails is warned about, the rest are still tried and compiled, and the command exits non-zero naming the ones that failed (decision 33)`. The command still ends in success with exit 0 after a failed seed.

- [ ] **Step 3: Write the code**

```diff
diff --git a/includes/class-wpcpm-cli.php b/includes/class-wpcpm-cli.php
index e0a44a2..97ee042 100644
--- a/includes/class-wpcpm-cli.php
+++ b/includes/class-wpcpm-cli.php
@@ -253,15 +253,19 @@ class WPCPM_CLI {
 	 * Each is created as a draft marked built-in, so its PHP keeps running it. A track whose status
 	 * the Track Builder already holds is passed over, so running this twice creates nothing the
 	 * second time. A site does this once on its own, the first time it runs 1.101.0; the command
-	 * is for a site that needs it again.
+	 * is for a site that needs it again. Every seed is tried before the exit code says that one
+	 * failed, since a second run creates only what is missing (the design's decision 33).
 	 *
 	 * ## EXAMPLES
 	 *
 	 *     wp wpcredits seed-tracks
 	 */
 	public function seed_tracks() {
+		$failed = array();
+
 		foreach ( WPCPM_Track_Store::seed() as $key => $result ) {
 			if ( is_wp_error( $result ) ) {
+				$failed[] = (string) $key;
 				WP_CLI::warning( sprintf( '%s: %s', $key, $result->get_error_message() ) );
 			} elseif ( $result ) {
 				WP_CLI::log( sprintf( '%-6s created as track %d, a draft marked built-in', $key, $result ) );
@@ -271,6 +275,11 @@ class WPCPM_CLI {
 		}
 
 		WPCPM_Track_Store::compile();
+
+		if ( array() !== $failed ) {
+			WP_CLI::error( sprintf( 'Not created: %s. The others are in the Track Builder; run the command again once the reason is put right.', implode( ', ', $failed ) ) );
+		}
+
 		WP_CLI::success( __( 'The built-in tracks are in the Track Builder.', 'wpcredits-program-manager' ) );
 	}
 
```

- [ ] **Step 4: Run them and watch them pass**

Run: `php bin/test-cli.php`

Expected: `bin/test-cli.php` ends `ALL PASS (2 checks)`. 

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
git add bin/test-cli.php includes/class-wpcpm-cli.php
git commit -m "Track Builder T3c: seed-tracks tries every seed, then exits non-zero naming the ones that failed"
```

---

### Task 11: The Track Builder section of the program manager guide

**Files:**
- Modify: `docs/sections/32-admin-tools.md` (the section, before "### Need help?")
- Modify: `docs/administrators.md`, `docs/build/administrators.html` (rebuilt by `bin/build-docs.php`)

**Interfaces:**
- Consumes: the screens' words as Tasks 3 to 9 leave them; every word quoted from a screen is the screen's, so a check against the source is the review.
- Produces: the section "### Track Builder" with eight subsections: the track list, starting a track, the track's page, controls and columns, one question, Preview and History, publishing, and the three presses that move a built-in track onto its definition. No image token, so the repository build needs no `--base` (what this plan decides, 13).

Decision 33's prose section: what a program manager sees, in the screens' own words, as `docs/sections/32-admin-tools.md` already describes the Student Duplicate Finder. The two things worth reading twice come first, that nothing reaches students until a track is published and that the four built-in tracks are here too; the walkthrough of those four ends the section because it is the product owner's next job (section 12's T5 gate), and Preview and History are what a person checks before each press.

`bin/build-docs.php` rewrites the two generated files from the sections. The diff below holds them, so the replay is exact and a reviewer reads what will be published; running the build again after applying it changes nothing, which is the check that the generated copies are the section's.

- [ ] **Step 1: Write the section, and the two guides it rebuilds into**

```diff
diff --git a/docs/administrators.md b/docs/administrators.md
index ab6e2b8..7555bce 100644
--- a/docs/administrators.md
+++ b/docs/administrators.md
@@ -292,6 +292,198 @@ Leaving it off between clean-ups is a reasonable habit, not a sign that somethin
 The **Duplicated students** tile counts the students the last scan listed, and its card links here.
 A tile at zero means the last scan found nothing, not that no scan has run: the card says which.
 
+### Track Builder
+
+**WPCredits Program → Modules → Track Builder.** A program track is one Airtable status, one form
+on the Student Report Card, a key chip in one color and, when the track follows one, a Learn course
+and an hours target. Until 1.100.0 a new track was a plugin release. The Track Builder makes it a
+draft you write here, preview, publish to Airtable and switch on, with no developer in the loop.
+
+Two things are worth knowing before you touch it. **Nothing reaches students until a track is
+published**, and every screen says so. And **the four tracks the program runs today are here too**,
+as built-in tracks that still run from the plugin's own code; the last part of this section is how
+to move one of them onto its definition.
+
+#### The track list
+
+One row per track, in the order they were made:
+
+| Column | What it shows |
+| --- | --- |
+| Track | The track's name |
+| Status | The Airtable status a student holds to be on this track |
+| Runs from | *Its definition*, or *Its hand-written form, so it cannot be edited here* for a built-in track that has not switched yet |
+| State | *Draft*, *Published*, *Unpublished changes*, or *Live, from its hand-written form* for a built-in track; under it, whether the definition matches the plugin's form, and a line when the last compile left the track out |
+| Students | How many synced students hold its status now |
+| Last published | When, and by whom |
+| Actions | **Edit**, **Duplicate**, **Preview**, **History** and **Publish** (**Publishing** once it is; **Publish definition** on a built-in track), then the buttons only some tracks get |
+
+**New track** sits above the list. The buttons a row gets only sometimes: **Refresh from the plugin**
+on a built-in draft that fell behind a plugin update, **Run from its definition** and **Run from its
+hand-written form** on a built-in track, and **Delete** on a track that was never published. Delete
+asks first and cannot be undone. A track that was ever published keeps its row, because its columns
+and its status live on in Airtable.
+
+#### Starting a track
+
+Three ways, each ending on the new track's page, as a draft.
+
+- **New track** asks for the three things two tracks can never share: the **Name**, the **Airtable
+  status** and the **Key**, the short word on the chip, plus the **Learn course** link when the
+  track follows one. With a link, the course's lessons are listed beside the questions, and the name
+  is taken from the course when it is left empty. The chip color is chosen for you, the first one no
+  other track holds, drafts included. The form starts empty; the questions are added on the track's
+  page.
+- **Duplicate** copies every question of an existing track, the four built-in ones included, and asks
+  for a name, a status and a key of its own. This is the usual way to start a track that resembles
+  one you run: duplicate the 150-hour track and change what differs.
+- **From a Learn course link** is New track with the link filled in. The lessons appear under the
+  groups that are their modules, and each lesson offers **Add a question under this lesson**, which
+  is how a form gets built lesson by lesson.
+
+#### The track's page
+
+The properties come first: **Name**, **Airtable status**, **Key**, **Learn course**, **Hours target**
+and **Key chip color**, which is one of blue, cyan, teal, green, red, pink or purple. The hours target
+may stay empty, as the Developer Track's does. **Save the track** saves the properties; the questions
+save themselves as they are added, edited and moved.
+
+The **Learn course** row shows what the link resolved to, the course's title and number. **Read the
+course again** asks Learn afresh; otherwise the site keeps a day's reading, so a lesson renamed on
+Learn shows up here within a day. When Learn cannot be reached the link is kept with a warning, the
+lessons cannot be listed, and the track still saves and publishes. Changing the link to another
+course matches every question that carried a lesson against the new course's lessons by its heading,
+and the notice names the questions matched and the ones that no longer point at a lesson.
+
+Then **Questions**, by group: Hours, Onboarding, Project and Wrap-up, the four parts of the Student
+Report Card's form. Each question is a row with what the student reads, its Airtable column and its
+control, and beside it **Edit**, **Move up**, **Move down** and **Remove**. Remove takes the question
+off the form; the column, and whatever students wrote in it, stay in Airtable, and the confirmation
+says so.
+
+Each group ends with **Add a question**: the Airtable column, what the student reads, the control and,
+when the track follows a course, **Under lesson**. Adding opens the new question's page.
+
+When the track follows a course, the lessons of each group's module are listed under the group's
+questions, with a count such as *Lessons on Learn: 3 of 13 have questions.* Each lesson names the
+questions that report on it, or offers **Add a question under this lesson**. A question added that way
+is placed after the lesson's last question, carries the lesson, and takes the lesson's title as its
+heading when it is the lesson's first question.
+
+A line above the questions says what publishing would create in Airtable, *This track needs no new
+Airtable columns.* or *Publishing will create 3 columns in Airtable.*, and how long ago the base was
+read.
+
+#### Controls and columns
+
+Ten controls: Text, one line; Text, many lines; Rich text; Web address; Email address; Number;
+Checkbox; One choice of several; Screenshot; Contribution team. The control decides the Airtable
+column type: a new column is created with the type the control needs, and a column that already
+exists has to be of that type, or the publish screen refuses.
+
+A duplicated track shares its columns with the track it came from, and the question's page says so,
+naming every other track that writes the column. Rewording a shared question keeps the column.
+Changing its control, or the choices of a select, gives it a column of its own, named after the track,
+which can still be renamed until the track is published. A different control is a different column.
+
+#### One question
+
+Each question has a page of its own: the column and the control at the top, then the rows that apply
+to that control.
+
+| Row | What it does |
+| --- | --- |
+| What the student reads | The label above the box |
+| Group | Hours, Onboarding, Project or Wrap-up |
+| Help under the box | A sentence under the control |
+| Heading before it | A heading printed before this question; the first question under a lesson carries the lesson's title here |
+| Subheading before it | A smaller heading before this question |
+| Note after the run | A sentence after the run of questions this one ends |
+| Row, and Shares one column of its row | Questions with the same row name sit side by side |
+| Marked required | Draws the word *Required* beside the label; nothing is enforced |
+| Kept off everything an institution reads | The answer never reaches the Institution Dashboard or the institution's downloads |
+| Lowest value, Highest value, Step | A number's limits; the step also sets how many decimal places a new column keeps |
+| Length limit | A single-line box's limit; a text area has its own |
+| Monospace, for code | A text area drawn in a monospace face |
+| Choices, one a line | The choices of a select; a column that already exists must offer every one of them |
+| Learn lesson | The lesson this question reports on: the course's lessons by module, *None* first, or a number box when Learn cannot be read |
+| Developer note | Why a column name looks like a slip; no student sees it |
+
+**Save the question** returns to the track. A refusal redraws the question with what was typed, and
+the reason.
+
+**Once a track is published, its questions are locked**: the column, the control and the choices are
+fixed, because the column in Airtable holds what students wrote, in that shape. The words can still
+change. To ask something differently, remove the question and add a new one with a column of its own.
+
+#### Preview and History
+
+**Preview** draws the draft's form as a student sees it: empty answers, no student's record, the same
+renderer and the same stylesheet as the Student Report Card. Nothing typed there is kept. A built-in
+track can be previewed too; its definition is what its form draws.
+
+**History** has three parts: what publishing would change, the published copy against the draft;
+every save, newest first and at most twenty, with who saved it, when, and what changed since the one
+before; and the publish log, every publish, unpublish, switch, column created and checklist item
+ticked, with who and when.
+
+#### Publishing
+
+**Publish** opens the publish screen. Nothing happens until the button at the bottom is pressed; the
+screen first reads the base and says what it found.
+
+- **What would stop it**: a column in Airtable with a question's name but another type, a control
+  that cannot have a column created for it, a table that would pass Airtable's limit of 500 columns,
+  a status or key another track holds.
+- **Worth knowing before you publish**: the track's status missing as a choice of the Status column
+  on Students Reports or Students, or nearly matching, which the syncs would never match; a Learn
+  course link that does not resolve; a table past 450 columns.
+- **Columns**: every column the track writes to, and whether it exists or will be created. With a
+  schema token, the optional second token under **WPCredits Program → Settings**, the site creates
+  the missing columns when you publish. Without one, the screen lists the exact columns to create by
+  hand, name and type, and Publish waits until the next reading finds them.
+- **What the site cannot do**: the three Airtable steps no token can take, with the exact values to
+  use. Add the status to the condition of the automation *Add students to Students Reports and
+  Feedback*; create the track's welcome email automation, as each of the four tracks has one; add the
+  status as a choice of the Status column on both tables. Tick each with **I have done this** once it
+  is done, and the tick records who and when. An unticked item never blocks publishing, but until
+  the automation item is ticked, students cannot be put on the track from the institution import,
+  because they would never get a report row.
+- Publishing adds the status to **Currently mentoring** in Settings.
+
+Publishing runs its steps one at a time and records each. If Airtable refuses part-way, the notice
+carries Airtable's own message, and pressing Publish again picks up at the first step not done.
+
+After publishing, the same screen offers **Check it against Airtable**, which reads the base again and
+says whether every column is still there with its type and the status is a choice on both tables, and
+**Take it off the live site**. Unpublishing is refused while any student holds the status, with the
+count. Otherwise the track becomes a draft again, nothing in Airtable changes, and the status stays in
+Currently mentoring: removing it there takes the Student role from everybody on the track, which is a
+decision of its own.
+
+Editing a published track's words makes it *Unpublished changes*; students keep the published copy
+until **Publish the changes** is pressed.
+
+#### The four built-in tracks
+
+The 150-hour, 50-hour, Developer and Designer tracks run from forms written in the plugin's code. Each
+has a definition in the Track Builder, shown as *Live, from its hand-written form*, and the line under
+the state says whether that definition is identical to the form. Moving one onto its definition takes
+three presses, and students see no change at any of them:
+
+1. Read the line under the state. *Identical to its hand-written form.* is what you want. *Differs
+   from its hand-written form: ...* means a plugin update changed the form since the definition was
+   made: press **Refresh from the plugin**, and the line changes.
+2. **Publish definition**. Its preflight should find nothing to create, because every column and both
+   choices exist already; that empty preflight is the proof that the definition matches the base. The
+   three checklist items were done for these four tracks long ago, so tick them as done.
+3. **Run from its definition**. From then on the Student Report Card draws the form from the
+   definition, and the notice says that what students see has not changed, which is what let it
+   switch. **Run from its hand-written form** puts it back the same way, at any time.
+
+Do this for all four before asking for the hand-written forms to be removed from the plugin. Until a
+built-in track has switched, it cannot be edited here, only duplicated and previewed.
+
 ### Need help?
 
 The tool screen for the question box configured under Settings. Its own screen is where the handbook
diff --git a/docs/build/administrators.html b/docs/build/administrators.html
index c049490..d382b95 100644
--- a/docs/build/administrators.html
+++ b/docs/build/administrators.html
@@ -405,6 +405,194 @@
 <p>The <strong>Duplicated students</strong> tile counts the students the last scan listed, and its card links here. A tile at zero means the last scan found nothing, not that no scan has run: the card says which.</p>
 <!-- /wp:paragraph -->
 
+<!-- wp:heading {"level":3,"anchor":"track-builder"} -->
+<h3 class="wp-block-heading" id="track-builder">Track Builder</h3>
+<!-- /wp:heading -->
+
+<!-- wp:paragraph -->
+<p><strong>WPCredits Program → Modules → Track Builder.</strong> A program track is one Airtable status, one form on the Student Report Card, a key chip in one color and, when the track follows one, a Learn course and an hours target. Until 1.100.0 a new track was a plugin release. The Track Builder makes it a draft you write here, preview, publish to Airtable and switch on, with no developer in the loop.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:paragraph -->
+<p>Two things are worth knowing before you touch it. <strong>Nothing reaches students until a track is published</strong>, and every screen says so. And <strong>the four tracks the program runs today are here too</strong>, as built-in tracks that still run from the plugin's own code; the last part of this section is how to move one of them onto its definition.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:heading {"level":4,"anchor":"the-track-list"} -->
+<h4 class="wp-block-heading" id="the-track-list">The track list</h4>
+<!-- /wp:heading -->
+
+<!-- wp:paragraph -->
+<p>One row per track, in the order they were made:</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:table -->
+<figure class="wp-block-table"><table><thead><tr><th>Column</th><th>What it shows</th></tr></thead><tbody><tr><td>Track</td><td>The track's name</td></tr><tr><td>Status</td><td>The Airtable status a student holds to be on this track</td></tr><tr><td>Runs from</td><td><em>Its definition</em>, or <em>Its hand-written form, so it cannot be edited here</em> for a built-in track that has not switched yet</td></tr><tr><td>State</td><td><em>Draft</em>, <em>Published</em>, <em>Unpublished changes</em>, or <em>Live, from its hand-written form</em> for a built-in track; under it, whether the definition matches the plugin's form, and a line when the last compile left the track out</td></tr><tr><td>Students</td><td>How many synced students hold its status now</td></tr><tr><td>Last published</td><td>When, and by whom</td></tr><tr><td>Actions</td><td><strong>Edit</strong>, <strong>Duplicate</strong>, <strong>Preview</strong>, <strong>History</strong> and <strong>Publish</strong> (<strong>Publishing</strong> once it is; <strong>Publish definition</strong> on a built-in track), then the buttons only some tracks get</td></tr></tbody></table></figure>
+<!-- /wp:table -->
+
+<!-- wp:paragraph -->
+<p><strong>New track</strong> sits above the list. The buttons a row gets only sometimes: <strong>Refresh from the plugin</strong> on a built-in draft that fell behind a plugin update, <strong>Run from its definition</strong> and <strong>Run from its hand-written form</strong> on a built-in track, and <strong>Delete</strong> on a track that was never published. Delete asks first and cannot be undone. A track that was ever published keeps its row, because its columns and its status live on in Airtable.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:heading {"level":4,"anchor":"starting-a-track"} -->
+<h4 class="wp-block-heading" id="starting-a-track">Starting a track</h4>
+<!-- /wp:heading -->
+
+<!-- wp:paragraph -->
+<p>Three ways, each ending on the new track's page, as a draft.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:list -->
+<ul class="wp-block-list">
+<!-- wp:list-item -->
+<li><strong>New track</strong> asks for the three things two tracks can never share: the <strong>Name</strong>, the <strong>Airtable status</strong> and the <strong>Key</strong>, the short word on the chip, plus the <strong>Learn course</strong> link when the track follows one. With a link, the course's lessons are listed beside the questions, and the name is taken from the course when it is left empty. The chip color is chosen for you, the first one no other track holds, drafts included. The form starts empty; the questions are added on the track's page.</li>
+<!-- /wp:list-item -->
+<!-- wp:list-item -->
+<li><strong>Duplicate</strong> copies every question of an existing track, the four built-in ones included, and asks for a name, a status and a key of its own. This is the usual way to start a track that resembles one you run: duplicate the 150-hour track and change what differs.</li>
+<!-- /wp:list-item -->
+<!-- wp:list-item -->
+<li><strong>From a Learn course link</strong> is New track with the link filled in. The lessons appear under the groups that are their modules, and each lesson offers <strong>Add a question under this lesson</strong>, which is how a form gets built lesson by lesson.</li>
+<!-- /wp:list-item -->
+</ul>
+<!-- /wp:list -->
+
+<!-- wp:heading {"level":4,"anchor":"the-track-s-page"} -->
+<h4 class="wp-block-heading" id="the-track-s-page">The track's page</h4>
+<!-- /wp:heading -->
+
+<!-- wp:paragraph -->
+<p>The properties come first: <strong>Name</strong>, <strong>Airtable status</strong>, <strong>Key</strong>, <strong>Learn course</strong>, <strong>Hours target</strong> and <strong>Key chip color</strong>, which is one of blue, cyan, teal, green, red, pink or purple. The hours target may stay empty, as the Developer Track's does. <strong>Save the track</strong> saves the properties; the questions save themselves as they are added, edited and moved.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:paragraph -->
+<p>The <strong>Learn course</strong> row shows what the link resolved to, the course's title and number. <strong>Read the course again</strong> asks Learn afresh; otherwise the site keeps a day's reading, so a lesson renamed on Learn shows up here within a day. When Learn cannot be reached the link is kept with a warning, the lessons cannot be listed, and the track still saves and publishes. Changing the link to another course matches every question that carried a lesson against the new course's lessons by its heading, and the notice names the questions matched and the ones that no longer point at a lesson.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:paragraph -->
+<p>Then <strong>Questions</strong>, by group: Hours, Onboarding, Project and Wrap-up, the four parts of the Student Report Card's form. Each question is a row with what the student reads, its Airtable column and its control, and beside it <strong>Edit</strong>, <strong>Move up</strong>, <strong>Move down</strong> and <strong>Remove</strong>. Remove takes the question off the form; the column, and whatever students wrote in it, stay in Airtable, and the confirmation says so.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:paragraph -->
+<p>Each group ends with <strong>Add a question</strong>: the Airtable column, what the student reads, the control and, when the track follows a course, <strong>Under lesson</strong>. Adding opens the new question's page.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:paragraph -->
+<p>When the track follows a course, the lessons of each group's module are listed under the group's questions, with a count such as <em>Lessons on Learn: 3 of 13 have questions.</em> Each lesson names the questions that report on it, or offers <strong>Add a question under this lesson</strong>. A question added that way is placed after the lesson's last question, carries the lesson, and takes the lesson's title as its heading when it is the lesson's first question.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:paragraph -->
+<p>A line above the questions says what publishing would create in Airtable, <em>This track needs no new Airtable columns.</em> or <em>Publishing will create 3 columns in Airtable.</em>, and how long ago the base was read.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:heading {"level":4,"anchor":"controls-and-columns"} -->
+<h4 class="wp-block-heading" id="controls-and-columns">Controls and columns</h4>
+<!-- /wp:heading -->
+
+<!-- wp:paragraph -->
+<p>Ten controls: Text, one line; Text, many lines; Rich text; Web address; Email address; Number; Checkbox; One choice of several; Screenshot; Contribution team. The control decides the Airtable column type: a new column is created with the type the control needs, and a column that already exists has to be of that type, or the publish screen refuses.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:paragraph -->
+<p>A duplicated track shares its columns with the track it came from, and the question's page says so, naming every other track that writes the column. Rewording a shared question keeps the column. Changing its control, or the choices of a select, gives it a column of its own, named after the track, which can still be renamed until the track is published. A different control is a different column.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:heading {"level":4,"anchor":"one-question"} -->
+<h4 class="wp-block-heading" id="one-question">One question</h4>
+<!-- /wp:heading -->
+
+<!-- wp:paragraph -->
+<p>Each question has a page of its own: the column and the control at the top, then the rows that apply to that control.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:table -->
+<figure class="wp-block-table"><table><thead><tr><th>Row</th><th>What it does</th></tr></thead><tbody><tr><td>What the student reads</td><td>The label above the box</td></tr><tr><td>Group</td><td>Hours, Onboarding, Project or Wrap-up</td></tr><tr><td>Help under the box</td><td>A sentence under the control</td></tr><tr><td>Heading before it</td><td>A heading printed before this question; the first question under a lesson carries the lesson's title here</td></tr><tr><td>Subheading before it</td><td>A smaller heading before this question</td></tr><tr><td>Note after the run</td><td>A sentence after the run of questions this one ends</td></tr><tr><td>Row, and Shares one column of its row</td><td>Questions with the same row name sit side by side</td></tr><tr><td>Marked required</td><td>Draws the word <em>Required</em> beside the label; nothing is enforced</td></tr><tr><td>Kept off everything an institution reads</td><td>The answer never reaches the Institution Dashboard or the institution's downloads</td></tr><tr><td>Lowest value, Highest value, Step</td><td>A number's limits; the step also sets how many decimal places a new column keeps</td></tr><tr><td>Length limit</td><td>A single-line box's limit; a text area has its own</td></tr><tr><td>Monospace, for code</td><td>A text area drawn in a monospace face</td></tr><tr><td>Choices, one a line</td><td>The choices of a select; a column that already exists must offer every one of them</td></tr><tr><td>Learn lesson</td><td>The lesson this question reports on: the course's lessons by module, <em>None</em> first, or a number box when Learn cannot be read</td></tr><tr><td>Developer note</td><td>Why a column name looks like a slip; no student sees it</td></tr></tbody></table></figure>
+<!-- /wp:table -->
+
+<!-- wp:paragraph -->
+<p><strong>Save the question</strong> returns to the track. A refusal redraws the question with what was typed, and the reason.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:paragraph -->
+<p><strong>Once a track is published, its questions are locked</strong>: the column, the control and the choices are fixed, because the column in Airtable holds what students wrote, in that shape. The words can still change. To ask something differently, remove the question and add a new one with a column of its own.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:heading {"level":4,"anchor":"preview-and-history"} -->
+<h4 class="wp-block-heading" id="preview-and-history">Preview and History</h4>
+<!-- /wp:heading -->
+
+<!-- wp:paragraph -->
+<p><strong>Preview</strong> draws the draft's form as a student sees it: empty answers, no student's record, the same renderer and the same stylesheet as the Student Report Card. Nothing typed there is kept. A built-in track can be previewed too; its definition is what its form draws.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:paragraph -->
+<p><strong>History</strong> has three parts: what publishing would change, the published copy against the draft; every save, newest first and at most twenty, with who saved it, when, and what changed since the one before; and the publish log, every publish, unpublish, switch, column created and checklist item ticked, with who and when.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:heading {"level":4,"anchor":"publishing"} -->
+<h4 class="wp-block-heading" id="publishing">Publishing</h4>
+<!-- /wp:heading -->
+
+<!-- wp:paragraph -->
+<p><strong>Publish</strong> opens the publish screen. Nothing happens until the button at the bottom is pressed; the screen first reads the base and says what it found.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:list -->
+<ul class="wp-block-list">
+<!-- wp:list-item -->
+<li><strong>What would stop it</strong>: a column in Airtable with a question's name but another type, a control that cannot have a column created for it, a table that would pass Airtable's limit of 500 columns, a status or key another track holds.</li>
+<!-- /wp:list-item -->
+<!-- wp:list-item -->
+<li><strong>Worth knowing before you publish</strong>: the track's status missing as a choice of the Status column on Students Reports or Students, or nearly matching, which the syncs would never match; a Learn course link that does not resolve; a table past 450 columns.</li>
+<!-- /wp:list-item -->
+<!-- wp:list-item -->
+<li><strong>Columns</strong>: every column the track writes to, and whether it exists or will be created. With a schema token, the optional second token under <strong>WPCredits Program → Settings</strong>, the site creates the missing columns when you publish. Without one, the screen lists the exact columns to create by hand, name and type, and Publish waits until the next reading finds them.</li>
+<!-- /wp:list-item -->
+<!-- wp:list-item -->
+<li><strong>What the site cannot do</strong>: the three Airtable steps no token can take, with the exact values to use. Add the status to the condition of the automation <em>Add students to Students Reports and Feedback</em>; create the track's welcome email automation, as each of the four tracks has one; add the status as a choice of the Status column on both tables. Tick each with <strong>I have done this</strong> once it is done, and the tick records who and when. An unticked item never blocks publishing, but until the automation item is ticked, students cannot be put on the track from the institution import, because they would never get a report row.</li>
+<!-- /wp:list-item -->
+<!-- wp:list-item -->
+<li>Publishing adds the status to <strong>Currently mentoring</strong> in Settings.</li>
+<!-- /wp:list-item -->
+</ul>
+<!-- /wp:list -->
+
+<!-- wp:paragraph -->
+<p>Publishing runs its steps one at a time and records each. If Airtable refuses part-way, the notice carries Airtable's own message, and pressing Publish again picks up at the first step not done.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:paragraph -->
+<p>After publishing, the same screen offers <strong>Check it against Airtable</strong>, which reads the base again and says whether every column is still there with its type and the status is a choice on both tables, and <strong>Take it off the live site</strong>. Unpublishing is refused while any student holds the status, with the count. Otherwise the track becomes a draft again, nothing in Airtable changes, and the status stays in Currently mentoring: removing it there takes the Student role from everybody on the track, which is a decision of its own.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:paragraph -->
+<p>Editing a published track's words makes it <em>Unpublished changes</em>; students keep the published copy until <strong>Publish the changes</strong> is pressed.</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:heading {"level":4,"anchor":"the-four-built-in-tracks"} -->
+<h4 class="wp-block-heading" id="the-four-built-in-tracks">The four built-in tracks</h4>
+<!-- /wp:heading -->
+
+<!-- wp:paragraph -->
+<p>The 150-hour, 50-hour, Developer and Designer tracks run from forms written in the plugin's code. Each has a definition in the Track Builder, shown as <em>Live, from its hand-written form</em>, and the line under the state says whether that definition is identical to the form. Moving one onto its definition takes three presses, and students see no change at any of them:</p>
+<!-- /wp:paragraph -->
+
+<!-- wp:list {"ordered":true} -->
+<ol class="wp-block-list">
+<!-- wp:list-item -->
+<li>Read the line under the state. <em>Identical to its hand-written form.</em> is what you want. <em>Differs from its hand-written form: ...</em> means a plugin update changed the form since the definition was made: press <strong>Refresh from the plugin</strong>, and the line changes.</li>
+<!-- /wp:list-item -->
+<!-- wp:list-item -->
+<li><strong>Publish definition</strong>. Its preflight should find nothing to create, because every column and both choices exist already; that empty preflight is the proof that the definition matches the base. The three checklist items were done for these four tracks long ago, so tick them as done.</li>
+<!-- /wp:list-item -->
+<!-- wp:list-item -->
+<li><strong>Run from its definition</strong>. From then on the Student Report Card draws the form from the definition, and the notice says that what students see has not changed, which is what let it switch. <strong>Run from its hand-written form</strong> puts it back the same way, at any time.</li>
+<!-- /wp:list-item -->
+</ol>
+<!-- /wp:list -->
+
+<!-- wp:paragraph -->
+<p>Do this for all four before asking for the hand-written forms to be removed from the plugin. Until a built-in track has switched, it cannot be edited here, only duplicated and previewed.</p>
+<!-- /wp:paragraph -->
+
 <!-- wp:heading {"level":3,"anchor":"need-help-2"} -->
 <h3 class="wp-block-heading" id="need-help-2">Need help?</h3>
 <!-- /wp:heading -->
diff --git a/docs/sections/32-admin-tools.md b/docs/sections/32-admin-tools.md
index 426afd9..7f55b82 100644
--- a/docs/sections/32-admin-tools.md
+++ b/docs/sections/32-admin-tools.md
@@ -148,6 +148,198 @@ Leaving it off between clean-ups is a reasonable habit, not a sign that somethin
 The **Duplicated students** tile counts the students the last scan listed, and its card links here.
 A tile at zero means the last scan found nothing, not that no scan has run: the card says which.
 
+### Track Builder
+
+**WPCredits Program → Modules → Track Builder.** A program track is one Airtable status, one form
+on the Student Report Card, a key chip in one color and, when the track follows one, a Learn course
+and an hours target. Until 1.100.0 a new track was a plugin release. The Track Builder makes it a
+draft you write here, preview, publish to Airtable and switch on, with no developer in the loop.
+
+Two things are worth knowing before you touch it. **Nothing reaches students until a track is
+published**, and every screen says so. And **the four tracks the program runs today are here too**,
+as built-in tracks that still run from the plugin's own code; the last part of this section is how
+to move one of them onto its definition.
+
+#### The track list
+
+One row per track, in the order they were made:
+
+| Column | What it shows |
+| --- | --- |
+| Track | The track's name |
+| Status | The Airtable status a student holds to be on this track |
+| Runs from | *Its definition*, or *Its hand-written form, so it cannot be edited here* for a built-in track that has not switched yet |
+| State | *Draft*, *Published*, *Unpublished changes*, or *Live, from its hand-written form* for a built-in track; under it, whether the definition matches the plugin's form, and a line when the last compile left the track out |
+| Students | How many synced students hold its status now |
+| Last published | When, and by whom |
+| Actions | **Edit**, **Duplicate**, **Preview**, **History** and **Publish** (**Publishing** once it is; **Publish definition** on a built-in track), then the buttons only some tracks get |
+
+**New track** sits above the list. The buttons a row gets only sometimes: **Refresh from the plugin**
+on a built-in draft that fell behind a plugin update, **Run from its definition** and **Run from its
+hand-written form** on a built-in track, and **Delete** on a track that was never published. Delete
+asks first and cannot be undone. A track that was ever published keeps its row, because its columns
+and its status live on in Airtable.
+
+#### Starting a track
+
+Three ways, each ending on the new track's page, as a draft.
+
+- **New track** asks for the three things two tracks can never share: the **Name**, the **Airtable
+  status** and the **Key**, the short word on the chip, plus the **Learn course** link when the
+  track follows one. With a link, the course's lessons are listed beside the questions, and the name
+  is taken from the course when it is left empty. The chip color is chosen for you, the first one no
+  other track holds, drafts included. The form starts empty; the questions are added on the track's
+  page.
+- **Duplicate** copies every question of an existing track, the four built-in ones included, and asks
+  for a name, a status and a key of its own. This is the usual way to start a track that resembles
+  one you run: duplicate the 150-hour track and change what differs.
+- **From a Learn course link** is New track with the link filled in. The lessons appear under the
+  groups that are their modules, and each lesson offers **Add a question under this lesson**, which
+  is how a form gets built lesson by lesson.
+
+#### The track's page
+
+The properties come first: **Name**, **Airtable status**, **Key**, **Learn course**, **Hours target**
+and **Key chip color**, which is one of blue, cyan, teal, green, red, pink or purple. The hours target
+may stay empty, as the Developer Track's does. **Save the track** saves the properties; the questions
+save themselves as they are added, edited and moved.
+
+The **Learn course** row shows what the link resolved to, the course's title and number. **Read the
+course again** asks Learn afresh; otherwise the site keeps a day's reading, so a lesson renamed on
+Learn shows up here within a day. When Learn cannot be reached the link is kept with a warning, the
+lessons cannot be listed, and the track still saves and publishes. Changing the link to another
+course matches every question that carried a lesson against the new course's lessons by its heading,
+and the notice names the questions matched and the ones that no longer point at a lesson.
+
+Then **Questions**, by group: Hours, Onboarding, Project and Wrap-up, the four parts of the Student
+Report Card's form. Each question is a row with what the student reads, its Airtable column and its
+control, and beside it **Edit**, **Move up**, **Move down** and **Remove**. Remove takes the question
+off the form; the column, and whatever students wrote in it, stay in Airtable, and the confirmation
+says so.
+
+Each group ends with **Add a question**: the Airtable column, what the student reads, the control and,
+when the track follows a course, **Under lesson**. Adding opens the new question's page.
+
+When the track follows a course, the lessons of each group's module are listed under the group's
+questions, with a count such as *Lessons on Learn: 3 of 13 have questions.* Each lesson names the
+questions that report on it, or offers **Add a question under this lesson**. A question added that way
+is placed after the lesson's last question, carries the lesson, and takes the lesson's title as its
+heading when it is the lesson's first question.
+
+A line above the questions says what publishing would create in Airtable, *This track needs no new
+Airtable columns.* or *Publishing will create 3 columns in Airtable.*, and how long ago the base was
+read.
+
+#### Controls and columns
+
+Ten controls: Text, one line; Text, many lines; Rich text; Web address; Email address; Number;
+Checkbox; One choice of several; Screenshot; Contribution team. The control decides the Airtable
+column type: a new column is created with the type the control needs, and a column that already
+exists has to be of that type, or the publish screen refuses.
+
+A duplicated track shares its columns with the track it came from, and the question's page says so,
+naming every other track that writes the column. Rewording a shared question keeps the column.
+Changing its control, or the choices of a select, gives it a column of its own, named after the track,
+which can still be renamed until the track is published. A different control is a different column.
+
+#### One question
+
+Each question has a page of its own: the column and the control at the top, then the rows that apply
+to that control.
+
+| Row | What it does |
+| --- | --- |
+| What the student reads | The label above the box |
+| Group | Hours, Onboarding, Project or Wrap-up |
+| Help under the box | A sentence under the control |
+| Heading before it | A heading printed before this question; the first question under a lesson carries the lesson's title here |
+| Subheading before it | A smaller heading before this question |
+| Note after the run | A sentence after the run of questions this one ends |
+| Row, and Shares one column of its row | Questions with the same row name sit side by side |
+| Marked required | Draws the word *Required* beside the label; nothing is enforced |
+| Kept off everything an institution reads | The answer never reaches the Institution Dashboard or the institution's downloads |
+| Lowest value, Highest value, Step | A number's limits; the step also sets how many decimal places a new column keeps |
+| Length limit | A single-line box's limit; a text area has its own |
+| Monospace, for code | A text area drawn in a monospace face |
+| Choices, one a line | The choices of a select; a column that already exists must offer every one of them |
+| Learn lesson | The lesson this question reports on: the course's lessons by module, *None* first, or a number box when Learn cannot be read |
+| Developer note | Why a column name looks like a slip; no student sees it |
+
+**Save the question** returns to the track. A refusal redraws the question with what was typed, and
+the reason.
+
+**Once a track is published, its questions are locked**: the column, the control and the choices are
+fixed, because the column in Airtable holds what students wrote, in that shape. The words can still
+change. To ask something differently, remove the question and add a new one with a column of its own.
+
+#### Preview and History
+
+**Preview** draws the draft's form as a student sees it: empty answers, no student's record, the same
+renderer and the same stylesheet as the Student Report Card. Nothing typed there is kept. A built-in
+track can be previewed too; its definition is what its form draws.
+
+**History** has three parts: what publishing would change, the published copy against the draft;
+every save, newest first and at most twenty, with who saved it, when, and what changed since the one
+before; and the publish log, every publish, unpublish, switch, column created and checklist item
+ticked, with who and when.
+
+#### Publishing
+
+**Publish** opens the publish screen. Nothing happens until the button at the bottom is pressed; the
+screen first reads the base and says what it found.
+
+- **What would stop it**: a column in Airtable with a question's name but another type, a control
+  that cannot have a column created for it, a table that would pass Airtable's limit of 500 columns,
+  a status or key another track holds.
+- **Worth knowing before you publish**: the track's status missing as a choice of the Status column
+  on Students Reports or Students, or nearly matching, which the syncs would never match; a Learn
+  course link that does not resolve; a table past 450 columns.
+- **Columns**: every column the track writes to, and whether it exists or will be created. With a
+  schema token, the optional second token under **WPCredits Program → Settings**, the site creates
+  the missing columns when you publish. Without one, the screen lists the exact columns to create by
+  hand, name and type, and Publish waits until the next reading finds them.
+- **What the site cannot do**: the three Airtable steps no token can take, with the exact values to
+  use. Add the status to the condition of the automation *Add students to Students Reports and
+  Feedback*; create the track's welcome email automation, as each of the four tracks has one; add the
+  status as a choice of the Status column on both tables. Tick each with **I have done this** once it
+  is done, and the tick records who and when. An unticked item never blocks publishing, but until
+  the automation item is ticked, students cannot be put on the track from the institution import,
+  because they would never get a report row.
+- Publishing adds the status to **Currently mentoring** in Settings.
+
+Publishing runs its steps one at a time and records each. If Airtable refuses part-way, the notice
+carries Airtable's own message, and pressing Publish again picks up at the first step not done.
+
+After publishing, the same screen offers **Check it against Airtable**, which reads the base again and
+says whether every column is still there with its type and the status is a choice on both tables, and
+**Take it off the live site**. Unpublishing is refused while any student holds the status, with the
+count. Otherwise the track becomes a draft again, nothing in Airtable changes, and the status stays in
+Currently mentoring: removing it there takes the Student role from everybody on the track, which is a
+decision of its own.
+
+Editing a published track's words makes it *Unpublished changes*; students keep the published copy
+until **Publish the changes** is pressed.
+
+#### The four built-in tracks
+
+The 150-hour, 50-hour, Developer and Designer tracks run from forms written in the plugin's code. Each
+has a definition in the Track Builder, shown as *Live, from its hand-written form*, and the line under
+the state says whether that definition is identical to the form. Moving one onto its definition takes
+three presses, and students see no change at any of them:
+
+1. Read the line under the state. *Identical to its hand-written form.* is what you want. *Differs
+   from its hand-written form: ...* means a plugin update changed the form since the definition was
+   made: press **Refresh from the plugin**, and the line changes.
+2. **Publish definition**. Its preflight should find nothing to create, because every column and both
+   choices exist already; that empty preflight is the proof that the definition matches the base. The
+   three checklist items were done for these four tracks long ago, so tick them as done.
+3. **Run from its definition**. From then on the Student Report Card draws the form from the
+   definition, and the notice says that what students see has not changed, which is what let it
+   switch. **Run from its hand-written form** puts it back the same way, at any time.
+
+Do this for all four before asking for the hand-written forms to be removed from the plugin. Until a
+built-in track has switched, it cannot be edited here, only duplicated and previewed.
+
 ### Need help?
 
 The tool screen for the question box configured under Settings. Its own screen is where the handbook
```

- [ ] **Step 2: Rebuild, and confirm the build changes nothing**

Run: `php bin/build-docs.php && git status --short`

Expected: the build reports the four guides, and the status names the same three files the diff changed and nothing else, since the generated copies in the diff are what the build writes.

- [ ] **Step 3: Run the checks that read the guides**

Run: `php bin/check-spelling.php | tail -1 && php bin/test-handbook.php | tail -1`

Expected: a line ending `US English throughout.`, then `ALL PASS`.

- [ ] **Step 4: Read the built page once**

Run: `grep -c "Track Builder" docs/build/administrators.html && grep -n '<h4 class="wp-block-heading" id="the-four-built-in-tracks">' docs/build/administrators.html`

Expected: `3`, and one line for the last subsection's heading.

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
git add docs/administrators.md docs/build/administrators.html docs/sections/32-admin-tools.md
git commit -m "Track Builder T3c: the Track Builder section of the program manager guide"
```

---

### Task 12: Release 1.107.0

**Files:**
- Modify: `wpcredits-program-manager.php` (the `Version:` header and `WPCPM_VERSION`), `readme.txt` (`Stable tag:` and a changelog entry), `languages/wpcredits-program-manager.pot` (regenerated)

- [ ] **Step 1: Move the version.** `1.106.1` becomes `1.107.0` in the plugin header's `Version:` line, in `define( 'WPCPM_VERSION', ... )` and in `readme.txt`'s `Stable tag:`. Every other mention of 1.106.1 stays, including the changelog's own heading.

- [ ] **Step 2: Write the changelog entry**, first under `== Changelog ==` in `readme.txt`, above the previous entry and with one empty line after it:

```text
= 1.107.0 =

* The Track Builder follows a Learn course. A track's Learn course link resolves to the course when the track is saved, and the track's page lists the course's lessons under the group that is their module, each naming the questions that report on it or offering "Add a question under this lesson", which places the new question after the lesson's last and gives it the lesson's title as its heading. A question's screen has a "Learn lesson" row: the course's lessons by module, or a number box when Learn cannot be read.
* New track takes a Learn course link, and the track's name is taken from the course when it is left empty. Choosing another course matches every question that carried a lesson against the new course's lessons by heading, and the notice names the questions matched and the ones that no longer point at a lesson.
* Learn is read through one client and the reading kept for a day; "Read the course again" on the track's page reads it afresh. The publish screen's warning about a course link that does not resolve comes from the same client.
* The track list, History and the publish log say when and who through one helper, with "by the site itself" for a save nobody signed in made, and the preview of a built-in draft that fell behind the plugin's own form says so instead of claiming to be what students see.
* wp wpcredits seed-tracks tries every seed, then exits non-zero naming the ones that failed.
* The program manager guide gains its Track Builder section.
```

- [ ] **Step 3: Regenerate the translation template.** `sh bin/make-pot.sh`. Expected: `Success: POT file successfully generated.` and a header reading `Project-Id-Version: WPCredits Program Manager 1.107.0`. (WP-CLI prints a deprecation notice from its own colors library first; it is not the plugin's.)

- [ ] **Step 4: Run everything.** Expected: silent, clean, `85 warnings, no errors.`

- [ ] **Step 5: Build the zip and read it back.**

```bash
bash bin/build
unzip -p ../wpcredits-program-manager.zip wpcredits-program-manager/wpcredits-program-manager.php | grep "Version:"
unzip -Z1 ../wpcredits-program-manager.zip | grep -cE '^wpcredits-program-manager/(bin|docs)/'
unzip -Z1 ../wpcredits-program-manager.zip | grep -c 'class-wpcpm-learn.php'
```

Expected: `Version:           1.107.0`, `0`, and `1` (the client ships; the fixtures and the suites do not).

- [ ] **Step 6: Commit.**

```bash
git add wpcredits-program-manager.php readme.txt languages/wpcredits-program-manager.pot
git commit -m "Track Builder T3c: 1.107.0"
```

- [ ] **Step 7: Merge and mirror, on the product owner's choice.** Merge `track-builder-t3c` into `main` and run the battery on the result. Then push the source to the public mirror: pull the `WordPress/WPCredits` clone at `~/GitHub/Plugins/WPCredits-Tracker-mirror` (branch `trunk`), rsync the plugin into its `Education/WordPress Education Dashboard/wpcredits-program-manager/` with `rsync -a --delete --exclude '.git/' --exclude '.superpowers/' --exclude '.DS_Store' --exclude 'node_modules/' --exclude '*.zip' --exclude '.*.swp'`, update the version in that folder's `README.md` table, scan the added lines and the untracked files (`git ls-files --others --exclude-standard`, since `git status --porcelain` quotes a path with a space in it) for keys, record IDs, email addresses and names, read `git diff --stat`, then commit and push as two separate steps. This plan and its spec amendment travel with the source; neither holds a Liquid tag (a brace followed by a percent sign), which a Jekyll build of the mirror would fail on. The two captured Learn answers are public course data with no person in them.

- [ ] **Step 8: Deploy only on the product owner's yes.** Ask first. On a yes, follow the deploy recorded for `wordpresseducation.org`: stream the zip over `ssh wpcredits-dashboard`, check its md5 on arrival, install it as a step of its own, read the version back, and purge the edge cache with `echo y | ssh wpcredits-dashboard 'wp edge-cache purge --domain --yes'`, which needs both the domain flag and an answer piped to its prompt. Before the install and after it, run one read-only check of what a person sees: the program map, the Programs running card drawn as a Program Administrator, the Student Report Card drawn as the TEST students, and the Track Builder's list drawn as a Program Administrator, with version strings and relative times normalized. The two runs must be identical: nothing on those pages changes in this release.

- [ ] **Step 9: Republish the program manager guide, in the same deploy.** The guide page's markup lives in `post_content`, so a plugin update does not refresh it. Build the block markup with the uploads base, `php bin/build-docs.php --base=https://wordpresseducation.org/wp-content/uploads/2026/08`, copy `docs/build/administrators.html` aside, and run `php bin/build-docs.php` again so the repository keeps its relative image paths and `git status` stays clean. Then, on the site: back up page 560's `post_content` to a file, stream the built copy over ssh stdin, update the page through `wp_update_post()` with `wp_slash()` as the owner's own administrator account, check that the stored byte count equals the file's (a smaller count means kses stripped something), that `_wpcpm_access_level` survived, and that no `<img src="` lacks a scheme, then purge the page's URL from the edge cache. The heading list old against new should differ by the eight new Track Builder headings and nothing else.

**The live site needs nothing new to take this release.** The Learn client reads only when a track's page or its question screen is opened in wp-admin, keeps the answer a day, and sends nothing but GET; nothing reaches Airtable or a student until somebody publishes a track, and publishing is unchanged from 1.106.1 but for where the link warning comes from. Every track stays exactly as it is.

---

## What T3c leaves for the product owner and T5

- **The migration of the four built-in tracks** (section 12's T5 gate): reading the line under each row's state, refreshing from the plugin where it differs, publishing each definition, ticking its checklist and switching it, which is the product owner's to do on the live site with the screens this release completes. The guide's last subsection is the walkthrough.
- **Screenshots for the guide**, in a docs-only release when the product owner takes them (decision 33); the section carries no image token until then.
- **Spec 7.4's "N students, 0 report rows" count** stays where T2c left it: it needs a records read the verify does not make yet.
- **The Learn Link project** (section 9), which reads grades rather than lessons and runs on Learn, not here.

## What this plan parks, with its reasons

- **A snapshot of the course in the definition**, which decision 30 set aside: a new property would touch the format, the seeds, the diff and History, and go stale between saves; the day's transient answers the same question.
- **A form for each lesson**, which decision 32 set aside: on the Designer course that would put three boxes for each of 39 lessons on one page. Add under a lesson goes through the group's form.
- **A live read of Learn on every open**, the cost decision 24 refused for the schema and decision 30 for the course.
- **The placeholder-only string in `render_log()`** stays translatable: it is there so a translator can put the date before what happened, which a concatenation would not allow.
- **Four of the T3b ledger's smallest findings are left as they are**, with the reason beside each in Task 9: the report's sheet enqueued for any positive preview id, `hues_in_use()` reading one definition per track on a press, both the cap line and "Older saves exist" printing on a site whose cap is exactly the list's length, and a predecessor with no definition reading as everything added.
- **The two-step control change** from T3a stands: a question's screen draws the boxes of the control last typed, and the refusal names what is missing.
