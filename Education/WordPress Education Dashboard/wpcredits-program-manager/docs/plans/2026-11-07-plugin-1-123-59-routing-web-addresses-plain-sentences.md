# Routing, web addresses and plain sentences, plugin 1.123.59 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Three small fixes the owner approved on 6 November 2026, shipped as plugin **1.123.59**: (1) a student or an institution member who can write posts lands on their own page after signing in, as mentors and sponsors already do (WordPress/WPCredits#295); (2) a website that is not a web address, one with a space in it or whose host has no dot, is refused at its field with the typing kept, on every form that takes one, instead of being stored as `https://not%20a%20site` (the 1.123.58 trial's finding T3); (3) no sentence of the plugin's own copy starts with "Please", beginning with the thank-you pages of the three application forms (finding T4).

**Architecture:** (1) The two `should_route()` clauses ask `edit_others_posts`, the capability that means "this account works in wp-admin", as `WPCPM_Mentors_Dashboard` and `WPCPM_Sponsors_Dashboard` already do. (2) One rule in one place: `WPCPM_Field_Value::clean_url()` answers nothing for a value with whitespace in it or a host with no dot, and `WPCPM_Field_Value::clean()` turns "something typed that cleans to nothing" into the refusal `bad_url` instead of an accepted empty value, so no form empties a box on the quiet; `bad_url_text()` is the one sentence every form says for it, "Enter a web address such as example.com.", and the Sponsors screen and the Sponsor Dashboard's profile say the same words. (3) Eighteen sentences reworded in the plain voice, each the same meaning with the word dropped or the verb moved first; the emails edited in Tools > Emails and the Mentor Status Checker's Slack line are untouched.

**Tech Stack:** PHP 7.4+ WordPress plugin `wpcredits-program-manager` (prefix `WPCPM_`); the no-WordPress suites `bin/test-*.php` (run from the plugin root with `php bin/test-x.php`; each ends `ALL PASS` and exits 0), with the stand-ins in `bin/stubs/`; `bin/check-standards.sh`; the Docker copy in `~/Projects/wpcredits-docker`; the release script `~/Projects/wpcredits-release/release.py`.

**Spec:** there is no design document; the three decisions are the owner's yes of 6 November 2026 to the recommendations of that day, read against WordPress/WPCredits#295 (the report, confirmed: the two clauses still ask `edit_posts` while the Mentor and Sponsor Dashboards moved to `edit_others_posts`, as `class-wpcpm-sponsors-dashboard.php` explains above its own clause) and the 1.123.58 trial's notes, findings T3 and T4, kept outside the public tree at `.superpowers/session-backups/2026-11-06/scan-release-1/trial-notes.md` in the main checkout. The house copy rule, "no sentence starting with Please", is the one the Global Constraints of every plan since 1.123.58 state. Nothing here names a person.

## Global Constraints

- Release **1.123.59**: the plugin header, `WPCPM_VERSION`, `readme.txt` Stable tag, a `= 1.123.59 =` changelog entry named "Routing, web addresses and plain sentences" and `languages/wpcredits-program-manager.pot` (`bash bin/make-pot.sh`). Branch `release/1.123.59-small-fixes` from `main` at a8a95123 or later (1.123.58 live since 6 November 2026, 14:42 UTC). If main's version has moved past 1.123.58, take the next free number wherever this plan says 1.123.59.
- PHP 7.4 compatible: `array()` syntax, no `match`, no named arguments, no typed properties, no `?->`, no trailing comma in a parameter list or a closure's `use` list.
- WordPress coding standards: tabs, Yoda conditions, spaces inside parentheses, strict `in_array()`, no short ternary. `bash bin/check-standards.sh` ends `106 warnings, no errors.` with no ` ERROR ` line: 106 is main's own count at a8a95123, and no task adds a warning. phpcs runs out of memory at PHP's default 128M on this tree, so the standards, the dead-annotations and the temp-litter checks run through the release kit's wrapper, which changes nothing in the tree: `PATH="$HOME/Projects/wpcredits-release/local-bin:$PATH"` in front of each.
- Every handler checks the login and the capability first, then the nonce, then the state; every posted value goes through `wp_unslash()` and a sanitizer; every printed value is escaped at output; every string with a placeholder has a translators' comment. This plan adds no handler and no placeholder.
- No em dash and no en dash anywhere: code, comments, strings, docs, commit messages. Plain hyphens.
- US English in every string a person reads (`php bin/check-spelling.php`); full product names ("Student Report Card", "Mentor Report Card", "Administrator Dashboard", "Institution Dashboard", "Sponsor Dashboard", "Mentor Status Checker", "Student Duplicate Finder"); "Administrators" for the people who run the program, in every string.
- **Copy rules for every new sentence:** plain, US English, sentence case, no sentence starting with "Please", no exclamation mark, the plugin's existing voice. Every sentence a person reads is written in this plan, in the diff blocks; an implementer who needs one the plan does not give stops and asks.
- **The one-field-one-reason rule** holds: a refused website is one sentence at its field, keyed `website` (or the question's column on a public form), and every other typed value comes back.
- `bin/` and `docs/` are public (mirrored to WordPress/WPCredits): example.test addresses, invented names, spelled-out record ids, no real person anywhere; `php bin/test-fixtures.php` walks both, `docs/plans` included. No key shape in any added line.
- The Administrator guide convention: a change to what a person sees is documented in the same release, in the section for that screen, after the paragraph that describes the same control, each new paragraph opening "Since 1.123.59 ..."; the sponsor and institution guides take a line each where they describe the field; then `php bin/build-docs.php` twice (the second run changes nothing), `php bin/test-handbook.php`, `php bin/test-admin-menu.php`, `php bin/check-references.php` and `php bin/check-spelling.php`. The guide quotes two sentences this release rewords; the quotes change with them (Task 3).
- One commit per task, its message starting `1.123.59:` and ending, after a blank line, with a `Co-Authored-By:` trailer naming the implementer's own model.
- Nothing is deployed, mirrored or posted without the owner's explicit yes for that step; the live version is read first; every test email on the live site goes to the owner's one test address only.

## Review Focus

Five inputs the three fixes imply, each the kind most likely to bite a person, each pinned with a check in the task that owns the code.

1. **A stored website the new rule refuses, saved untouched.** A student whose Airtable website is "mysite", or a sponsor whose old website has a space in it, opens the form, changes something else and saves: the website box is refused at the field with the sentence, everything else typed comes back, and nothing is emptied on the quiet. Pinned in Task 2, step 1: `bin/test-report-form.php` ("words with a space, or a host with no dot, are not an address: refused, the typing kept for the student to fix"), `bin/test-sponsor-cards.php` ("a website of words, or with no dot in its host, is refused rather than kept") and `bin/test-sponsor-application.php` ("a website of words is sent back with its own reason, the typing kept and drawn back in its box under the sentence, nothing stored").
2. **A port, a path or a query after a dotted host.** `https://example.org:8443/x` and `sub.example.org/path?q=1` are addresses; a `user@` part is still refused by the profile and the offers, as before. Pinned in Task 2, step 1: `bin/test-field-value.php` ("a port after the host does not hide the dot", "a host with a dot, a path and a query is an address") and the existing "a URL with a user is refused" of `bin/test-sponsor-cards.php`.
3. **A stored link read for display.** The semester report's website read from Airtable, a sponsor post's and a tool's website link, a mentor's profile link from the sync: a stored value the rule would refuse links nothing, raises nothing and the page draws. Pinned in Task 2, step 1: `bin/test-field-value.php` ("clean_url() on its own answers nothing for them, as the readers of stored links expect").
4. **An editor who is also a student or an institution member.** The fix moves only the people who can write posts; an account that can edit others' posts still works in wp-admin after signing in. Pinned by the existing cases of `bin/test-login-redirect.php` (the `$editor` account and the `$GLOBALS['edit_others']` stand-in) and `bin/test-institutions-dashboard.php` (the `manage` fallthrough of its capability stub), which Task 1 keeps green.
5. **An emptied website box.** Clearing a website is still accepted as empty on every form; only something typed that is not an address is refused. Pinned by the existing "an emptied box clears the column" of `bin/test-field-value.php` and "an empty box clears it" of `bin/test-report-form.php`, which Task 2 keeps green.

## Files at a glance

| File | What it is responsible for | Task |
| --- | --- | --- |
| `includes/modules/class-wpcpm-students-dashboard.php`, `class-wpcpm-institutions-dashboard.php` | `should_route()`: the clause that keeps wp-admin accounts in wp-admin asks `edit_others_posts` | 1 |
| `docs/sections/31-admin-settings.md` | The landing pages: who goes where since 1.123.59 | 1 |
| `includes/class-wpcpm-field-value.php` | `clean_url()`: no whitespace, a dotted host; `clean()`: `bad_url` for a typed non-address; `bad_url_text()`: the one sentence | 2 |
| `includes/modules/class-wpcpm-sponsors.php`, `class-wpcpm-sponsor-profile.php` | The Sponsors screen's and the profile's website sentence, the same words | 2 |
| `includes/modules/class-wpcpm-sponsor-application.php`, `class-wpcpm-student-report-form.php`, `class-wpcpm-institution-application.php` | `problem_text()` says `bad_url` with the one sentence (the Student Report Card's is shared with the feedback forms) | 2 |
| `docs/sections/30-admin-wpadmin.md`, `34-admin-operations.md`, `45-sponsor-company.md`, `52-institution-details.md` | The website rule in the Administrator, sponsor and institution guides | 2 |
| Ten modules named in Task 3 | Fifteen sentences in the plain voice | 3 |
| `docs/sections/33-admin-access.md`, `52-institution-details.md` | The two quoted sentences follow | 3 |
| `wpcredits-program-manager.php`, `readme.txt`, `languages/wpcredits-program-manager.pot` | The version, the changelog, the template | 4 |

No new file, no new suite, no loader change.

## The checks every task ends with

Run from the plugin root, after the task's own suites pass:

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done; echo battery done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
PATH="$HOME/Projects/wpcredits-release/local-bin:$PATH" php bin/check-dead-annotations.php | tail -1
PATH="$HOME/Projects/wpcredits-release/local-bin:$PATH" php bin/check-temp-litter.php | tail -2
PATH="$HOME/Projects/wpcredits-release/local-bin:$PATH" bash bin/check-standards.sh 2>&1 | tail -1
php bin/test-fixtures.php | tail -1
```

and `php bin/build-docs.php` when a task changes `docs/`. Expected: `battery done` and no `FAIL` line; the references check ending `- all resolve`; the spelling check ending `US English throughout.`; `0 dead`; the temp-litter check saying every script exited 0 and the temp directory is as it was found; `106 warnings, no errors.`; and `ALL PASS`. The temp directory is the machine's, so the temp-litter check also names what another program made while it ran; as its own header says, run it again: the battery's leftovers come back under the same names, a stranger's do not.

The proved runs quoted below were made on the scratch branch `plan-1-123-59` from main at a8a95123, holding exactly the code of Tasks 1 to 3 as written here (its last commit 8cfd895a), on PHP 8.5 with node 26. Only the lines that matter are kept. The fail runs were made with main's code under the branch's checks.

---

## Before Task 1: the branch and the baseline

- [ ] **Step 1: Branch from main** (a worktree if another session is working in the checkout; see superpowers:using-git-worktrees; never enter another session's worktree under `.worktrees/`)

```bash
cd ~/GitHub/wpcredits-program-manager
git worktree add .worktrees/1-123-59 -b release/1.123.59-small-fixes main
cd .worktrees/1-123-59
```

- [ ] **Step 2: Start from a main that passes**

The checks every task ends with, on main at a8a95123 as proved:

```text
battery done
199 classes, 22237 references checked, 109 form handlers scanned - all resolve
202 PHP files, 10 blocks, 4 track seeds, 32 fixture and tooling files, the guides and the readme: US English throughout.
482 annotations, 0 dead.
Ran 175 scripts from bin/, each in a PHP process of its own, and every one exited 0.
The battery leaves the temp directory as it found it.
106 warnings, no errors.
ALL PASS
```

Without the wrapper on `PATH`, the standards check reads `phpcs could not be run: it exited 255.`, the dead-annotations check `phpcs produced no report.` and the temp-litter check names `bin/check-dead-annotations.php exited 1`: that is phpcs at PHP's default memory, not the tree. Two suites print `Undefined global variable $uid` warnings on PHP 8.5 (`bin/test-sponsor-cards.php` line 184, `bin/test-sponsors-screen.php` line 597) and pass; they did so on main before this plan and are not its business.

- [ ] **Step 3: Check that this plan names nobody**

```bash
grep -n -e $'\xe2\x80\x94' -e $'\xe2\x80\x93' docs/plans/2026-11-07-plugin-1-123-59-routing-web-addresses-plain-sentences.md || echo clean
php -r 'require "bin/refused-strings.php"; var_dump( wpcpm_refused_found( file_get_contents( "docs/plans/2026-11-07-plugin-1-123-59-routing-web-addresses-plain-sentences.md" ) ) );'
php bin/test-fixtures.php | tail -1
```

Expected: `clean`, `array(0) {}` and `ALL PASS`.

---

### Task 1: A student or an institution member who can write posts lands on their page (WordPress/WPCredits#295)

The report is right: `WPCPM_Students_Dashboard::should_route()` and `WPCPM_Institutions_Dashboard::should_route()` ask `edit_posts` where the Mentor and Sponsor Dashboards ask `edit_others_posts`, so a student or an institution contact with a contributor or author role, or attached to a sponsor whose posting flag is on, is left in wp-admin by `login_redirect()` and `replace_admin_dashboard()`. The clause means "this account works in wp-admin", and `edit_others_posts` is what an editor and an administrator hold and a participant who writes posts does not. Two one-line changes, one check each.

**Files:**
- Modify: `includes/modules/class-wpcpm-students-dashboard.php` (`should_route()`, the capability clause)
- Modify: `includes/modules/class-wpcpm-institutions-dashboard.php` (`should_route()`, the capability clause)
- Test: `bin/test-login-redirect.php` (its stand-in answers `edit_posts` from `$GLOBALS['edit']` and `edit_others_posts` from `$GLOBALS['edit_others']`), `bin/test-institutions-dashboard.php` (its stub answers `edit_posts` from `$GLOBALS['editors']`)
- Guide: `docs/sections/31-admin-settings.md`, the landing pages

**Interfaces:**
- Consumes: `WPCPM_Roles::CAP_MANAGE`, `user_can()`.
- Produces: nothing new; the two methods keep their signatures.

- [ ] **Step 1: Write the failing checks**

```diff
diff --git a/bin/test-institutions-dashboard.php b/bin/test-institutions-dashboard.php
index 7f776ce8..e29d312a 100644
--- a/bin/test-institutions-dashboard.php
+++ b/bin/test-institutions-dashboard.php
@@ -1143,6 +1143,13 @@ ck(
 	WPCPM_Institutions_Dashboard::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][2] ),
 	'https://example.test/institution-dashboard/'
 );
+$GLOBALS['editors'][] = 2;
+ck(
+	'a member who can write posts still goes to their dashboard (WordPress/WPCredits#295)',
+	WPCPM_Institutions_Dashboard::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/wp-admin/', $GLOBALS['users'][2] ),
+	'https://example.test/institution-dashboard/'
+);
+$GLOBALS['editors'] = array_values( array_diff( $GLOBALS['editors'], array( 2 ) ) );
 ck(
 	'a member who asked for somewhere in particular is taken there',
 	WPCPM_Institutions_Dashboard::login_redirect( 'https://example.test/wp-admin/', 'https://example.test/guides/getting-started/', $GLOBALS['users'][2] ),
diff --git a/bin/test-login-redirect.php b/bin/test-login-redirect.php
index cdd88147..deefb49f 100644
--- a/bin/test-login-redirect.php
+++ b/bin/test-login-redirect.php
@@ -243,6 +243,9 @@ echo "=== A plain login, as core presents it ===\n";
 
 ck( 'a mentor lands on the Mentor Report Card', array( plain_login( 'mentor', $mentor ) ), array( MENTOR_PAGE ) );
 ck( 'a student lands on the Student Report Card', array( plain_login( 'student', $student ) ), array( STUDENT_PAGE ) );
+$GLOBALS['edit'][] = $student->ID;
+ck( 'a student who can write posts still lands on the Student Report Card (WordPress/WPCredits#295)', array( plain_login( 'student', $student ) ), array( STUDENT_PAGE ) );
+$GLOBALS['edit'] = array_values( array_diff( $GLOBALS['edit'], array( $student->ID ) ) );
 
 // The admin root with no trailing slash is the same place.
 ck( 'the admin root counts as "nowhere in particular", slash or no slash',
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php bin/test-login-redirect.php`, `php bin/test-institutions-dashboard.php`

```text
FAIL a student who can write posts still lands on the Student Report Card (WordPress/WPCredits#295)
FAIL a member who can write posts still goes to their dashboard (WordPress/WPCredits#295)
```

- [ ] **Step 3: The two clauses**

```diff
diff --git a/includes/modules/class-wpcpm-institutions-dashboard.php b/includes/modules/class-wpcpm-institutions-dashboard.php
index 2b148227..2b7e2b70 100644
--- a/includes/modules/class-wpcpm-institutions-dashboard.php
+++ b/includes/modules/class-wpcpm-institutions-dashboard.php
@@ -225,7 +225,10 @@ class WPCPM_Institutions_Dashboard {
 			return false;
 		}
 
-		if ( user_can( $user->ID, WPCPM_Roles::CAP_MANAGE ) || user_can( $user->ID, 'edit_posts' ) ) {
+		// `edit_others_posts`, not `edit_posts`: the clause means "this account works in wp-admin",
+		// and a participant who can write posts (a contributor, or a sponsor's representative)
+		// still belongs on their dashboard (WordPress/WPCredits#295, 1.123.59).
+		if ( user_can( $user->ID, WPCPM_Roles::CAP_MANAGE ) || user_can( $user->ID, 'edit_others_posts' ) ) {
 			return false;
 		}
 
diff --git a/includes/modules/class-wpcpm-students-dashboard.php b/includes/modules/class-wpcpm-students-dashboard.php
index c4852a39..40c848b4 100644
--- a/includes/modules/class-wpcpm-students-dashboard.php
+++ b/includes/modules/class-wpcpm-students-dashboard.php
@@ -1203,7 +1203,10 @@ class WPCPM_Students_Dashboard {
 			return false;
 		}
 
-		if ( user_can( $user->ID, WPCPM_Roles::CAP_MANAGE ) || user_can( $user->ID, 'edit_posts' ) ) {
+		// `edit_others_posts`, not `edit_posts`: the clause means "this account works in wp-admin",
+		// and a participant who can write posts (a contributor, or a sponsor's representative)
+		// still belongs on their dashboard (WordPress/WPCredits#295, 1.123.59).
+		if ( user_can( $user->ID, WPCPM_Roles::CAP_MANAGE ) || user_can( $user->ID, 'edit_others_posts' ) ) {
 			return false;
 		}
```

- [ ] **Step 4: Run them to verify they pass**

Run: `php bin/test-login-redirect.php`, `php bin/test-institutions-dashboard.php`. Expected: `ALL PASS` from each.

- [ ] **Step 5: The guide**

Two paragraphs in the landing pages' part of the Settings section, after the bullet each describes:

```diff
diff --git a/docs/sections/31-admin-settings.md b/docs/sections/31-admin-settings.md
index ad53dc00..21702096 100644
--- a/docs/sections/31-admin-settings.md
+++ b/docs/sections/31-admin-settings.md
@@ -286,6 +286,9 @@ institution leaves the pipeline**, **Collaboration Agreements** and **Semester r
   and in place of the wp-admin Dashboard, and get an "Institution Dashboard" link in the toolbar, with
   the same exceptions as for mentors and students. The page's address is under the switch.
 
+Since 1.123.59 the same holds here: an institution account that can write posts still goes to the
+Institution Dashboard, and only an editor or an administrator stays in wp-admin.
+
 #### When an institution leaves the pipeline
 
 - **Its people** - **Remove its people, so they lose access to its students**, the default, or
@@ -344,6 +347,11 @@ Approved**, **Interest mail**, **Offers** and **Tiers**.
   applications are closed. As with institutions, its consent box links to the WordPress.org privacy
   policy, and the row's fold says so.
 
+Since 1.123.59 a student who can write posts, a contributor or an author, still goes to the Student
+Report Card: only an account that can edit others' posts, an editor or an administrator, is left
+where WordPress sends it, which is what the Mentor and Sponsor landing pages already did. Before,
+such a student landed in wp-admin.
+
 #### Sponsor landing page
 
 - **Sponsor landing page** - **Send sponsor accounts to the Sponsor Dashboard when they log in**,
```

Then `php bin/build-docs.php && php bin/build-docs.php && git status --short docs/` (the second build changes nothing), `php bin/test-handbook.php` and `php bin/test-admin-menu.php`: `ALL PASS` from both.

- [ ] **Step 6: The checks every task ends with**

Expected as the block above says; `22237 references` grows by the guide's lines.

- [ ] **Step 7: Commit**

```bash
git add includes/modules/class-wpcpm-students-dashboard.php includes/modules/class-wpcpm-institutions-dashboard.php bin/test-login-redirect.php bin/test-institutions-dashboard.php docs/sections/31-admin-settings.md docs/
git commit -m "1.123.59: a student or an institution member who can write posts lands on their page" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 2: A website that is not a web address is refused at its field, everywhere, with one sentence

**What this task decides.** `WPCPM_Field_Value::clean_url()` is the one rule every website goes through: the public sponsor and institution forms (the `url` kind of `WPCPM_Field_Value::clean()`), the Sponsor Dashboard's profile (`WPCPM_Sponsor_Profile::clean()`), the Sponsors screen's Add and Edit forms (through the profile's `clean()`), the Student Report Card's and the feedback forms' `url` boxes, and the readers of stored links (the semester report, the sponsor posts and tools, the sponsor mentors card, the offers). The rule gains two refusals: a value with whitespace anywhere in it (`esc_url_raw()` would keep it as `%20`) and a host with no dot with something on both sides ("example", "https://example"); a port, a path, a query or a hash after a dotted host changes nothing. `clean_url()` keeps answering an empty string for what it refuses, which is what the readers of stored links expect: nothing is linked. The forms are the ones that must not empty a box on the quiet, so `clean()` answers the refusal `bad_url` whenever something was typed and cleans to nothing; the profile's `ok` rule already did (`'' === $raw || '' !== $value`), and the Sponsors screen reads the profile. `bad_url` joins the class docblock's list of keys, which `bin/test-field-value.php` reads. One sentence for all of them, `WPCPM_Field_Value::bad_url_text()`: "Enter a web address such as example.com." (it was "Give a web address starting with https://, or just the domain.", which no longer says the rule); the profile's own string keeps its second sentence, "Nothing on the profile was saved.", and the Sponsors screen's `problem_sentence()` calls the helper. `WPCPM_Sponsor_Application::problem_text()` and `WPCPM_Student_Report_Form::problem_text()` gain the `bad_url` branch; `WPCPM_Institution_Application::problem_text()` had it since 1.123.58. The offers' link and the sponsor event's call link keep their own checks and sentences.

**Files:**
- Modify: `includes/class-wpcpm-field-value.php` (`clean_url()`, `clean()`'s `url` branch, `bad_url_text()`, the docblock's key list)
- Modify: `includes/modules/class-wpcpm-sponsors.php` (`problem_sentence()`, case `website`), `includes/modules/class-wpcpm-sponsor-profile.php` (`refusal()`, the website string)
- Modify: `includes/modules/class-wpcpm-sponsor-application.php` (`problem_text()`), `includes/modules/class-wpcpm-student-report-form.php` (`problem_text()`), `includes/modules/class-wpcpm-institution-application.php` (a comment)
- Test: `bin/test-field-value.php` (gains a `__()` stand-in, which the sentence helper needs), `bin/test-report-form.php`, `bin/test-sponsor-application.php`, `bin/test-institution-application.php`, `bin/test-institution-details.php`, `bin/test-sponsors-screen.php`, `bin/test-sponsor-cards.php`
- Guide: `docs/sections/30-admin-wpadmin.md` (the Sponsors screen), `34-admin-operations.md` (the application forms), `45-sponsor-company.md` (the sponsor guide), `52-institution-details.md` (the institution guide)

**Interfaces:**
- Consumes: `esc_url_raw( $url, array( 'http', 'https' ) )`.
- Produces: `WPCPM_Field_Value::clean_url( $raw ): string` (unchanged signature; `''` for a refused value); `WPCPM_Field_Value::clean( $raw, $spec )` answering `array( 'ok' => false, 'value' => null, 'problem' => 'bad_url' )` for a typed non-address of the `url` kind; `WPCPM_Field_Value::bad_url_text(): string`, the sentence Task 3's forms and the guides quote.

- [ ] **Step 1: Write the failing checks**

The pins of the old sentence move to the new one in the same step, so that the suites fail for the rule and for the words together.

```diff
diff --git a/bin/test-field-value.php b/bin/test-field-value.php
index f75358e9..4368974e 100644
--- a/bin/test-field-value.php
+++ b/bin/test-field-value.php
@@ -28,6 +28,7 @@ function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
 function sanitize_textarea_field( $s ) { return trim( (string) $s ); }
 function sanitize_email( $s ) { return trim( preg_replace( '/[^a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~@\-]/', '', (string) $s ) ); }
 function is_email( $s ) { return (bool) filter_var( (string) $s, FILTER_VALIDATE_EMAIL ); }
+function __( $text, $domain = null ) { return $text; }
 
 /**
  * Honors the protocol allow-list, so the URL assertions are not vacuous.
@@ -212,10 +213,17 @@ ck( 'whatever its case', fv( 'HTTPS://Example.org', $url ), accepted( 'HTTPS://E
 ck( 'leading slashes do not double up', fv( '//example.org', $url ), accepted( 'https://example.org' ) );
 ck( 'whitespace around it is not part of it', fv( '  example.org  ', $url ), accepted( 'https://example.org' ) );
 ck( 'an emptied box clears the column', fv( '', $url ), accepted( '' ) );
-ck( 'a javascript: URL does not survive', fv( 'javascript:alert(1)', $url ), accepted( '' ) );
+ck( 'a javascript: URL does not survive: refused, not emptied on the quiet (1.123.59)', fv( 'javascript:alert(1)', $url ), refused( 'bad_url' ) );
 ck( 'clean_url() is the same rule on its own', WPCPM_Field_Value::clean_url( 'example.org' ), 'https://example.org' );
 ck( 'clean_url() refuses javascript: on its own too', WPCPM_Field_Value::clean_url( 'javascript:alert(1)' ), '' );
 ck( 'clean_url() passes an empty string through', WPCPM_Field_Value::clean_url( '' ), '' );
+ck( 'a value with a space in it is not an address (1.123.59): refused, never stored as https://not%20a%20site', fv( 'not a site', $url ), refused( 'bad_url' ) );
+ck( 'a host with no dot is not an address either', array( fv( 'example', $url ), fv( 'https://example', $url ), fv( 'https://example/path', $url ) ), array( refused( 'bad_url' ), refused( 'bad_url' ), refused( 'bad_url' ) ) );
+ck( 'a space anywhere, in the path too, refuses the whole value', fv( 'example.com/a b', $url ), refused( 'bad_url' ) );
+ck( 'a host with a dot, a path and a query is an address', fv( 'sub.example.org/path?q=1', $url ), accepted( 'https://sub.example.org/path?q=1' ) );
+ck( 'a port after the host does not hide the dot', fv( 'https://example.org:8443/x', $url ), accepted( 'https://example.org:8443/x' ) );
+ck( 'clean_url() on its own answers nothing for them, as the readers of stored links expect', array( WPCPM_Field_Value::clean_url( 'not a site' ), WPCPM_Field_Value::clean_url( 'example' ), WPCPM_Field_Value::clean_url( 'example.org' ) ), array( '', '', 'https://example.org' ) );
+ck( 'the one sentence every form says for a refused address', WPCPM_Field_Value::bad_url_text(), 'Enter a web address such as example.com.' );
 ck( 'an array is refused', fv( array( 'example.org' ), $url ), refused( 'not_scalar' ) );
 
 echo "\n=== text: one line, capped by maxlength, then by the caller, then by the class ===\n";
diff --git a/bin/test-institution-application.php b/bin/test-institution-application.php
index 46660042..c5706c30 100644
--- a/bin/test-institution-application.php
+++ b/bin/test-institution-application.php
@@ -1939,12 +1939,13 @@ $script  = submit( answers( array( 'credits_page' => 'javascript:alert(1)' ) ) )
 $page_id = 'wpcpm-application-' . WPCPM_Institution_Application::form_key( 'credits_page' );
 
 ck( 'a page address with a javascript: scheme is refused, and nothing is stored', array( $script['outcome'], $script['stash']['problems'] ?? null, count( stored() ) ), array( 'again', array( 'credits_page' => 'bad_url' ), 0 ) );
+ck( 'a website of words, or with no dot in its host, is refused the same way, never emptied on the quiet (1.123.59)', array( WPCPM_Institution_Application::clean( 'Website', 'not a site' )['problem'], WPCPM_Institution_Application::clean( 'Website', 'example' )['problem'], WPCPM_Institution_Application::clean( 'credits_page', 'our page' )['problem'] ), array( 'bad_url', 'bad_url', 'bad_url' ) );
 
 follow( $script['url'] );
 $again = WPCPM_Institution_Application::render();
 
 ck( 'and the form handed back says what to give, the address back in its box as typed and escaped (1.123.58)', array(
-	false !== strpos( $again, '<span class="wpcpm-field__problem" id="' . $page_id . '-problem">Give a web address starting with https://, or just the domain.</span>' ),
+	false !== strpos( $again, '<span class="wpcpm-field__problem" id="' . $page_id . '-problem">Enter a web address such as example.com.</span>' ),
 	false !== strpos( $again, 'id="' . $page_id . '" name="wpcpm_application[' . WPCPM_Institution_Application::form_key( 'credits_page' ) . ']" value="javascript:alert(1)"' ),
 	substr_count( $again, 'javascript:' ),
 ), array( true, true, 1 ) );
@@ -2861,7 +2863,7 @@ $typo = submit( answers( array( 'Contact Email' => 'name@campus', 'credits_page'
 $page = page_after( $typo );
 ck(
 	'a refused address and a refused web address come back as typed, and bad_url has its own sentence',
-	array( $typo['stash']['problems'] ?? null, 1 === preg_match( '#id="' . control_id( 'Contact Email' ) . '" name="[^"]+" value="name@campus"#', $page ), 1 === preg_match( '#id="' . control_id( 'credits_page' ) . '" name="[^"]+" value="ftp://example.test"#', $page ), false !== strpos( $page, 'Give a web address starting with https://, or just the domain.' ) ),
+	array( $typo['stash']['problems'] ?? null, 1 === preg_match( '#id="' . control_id( 'Contact Email' ) . '" name="[^"]+" value="name@campus"#', $page ), 1 === preg_match( '#id="' . control_id( 'credits_page' ) . '" name="[^"]+" value="ftp://example.test"#', $page ), false !== strpos( $page, 'Enter a web address such as example.com.' ) ),
 	array( array( 'Contact Email' => 'bad_email', 'credits_page' => 'bad_url' ), true, true, true )
 );
 
diff --git a/bin/test-institution-details.php b/bin/test-institution-details.php
index 5670e80e..6df2c2be 100644
--- a/bin/test-institution-details.php
+++ b/bin/test-institution-details.php
@@ -569,7 +569,9 @@ ck(
 $refused = WPCPM_Institution_Details::clean( 'checked', array( 'name' => 'Universidad', 'website' => 'javascript:alert(1)', 'city' => 'Heredia', 'country' => COSTA, 'contact_email' => 'ana@example.test' ) );
 
 ck( 'a web address naming a scheme of its own is refused', array_keys( $refused['problems'] ), array( 'website' ) );
-ck( 'and says what to give, in the form\'s own sentence for it (1.123.58)', $refused['problems']['website'], 'Give a web address starting with https://, or just the domain.' );
+ck( 'and says what to give, in the form\'s own sentence for it (1.123.58)', $refused['problems']['website'], 'Enter a web address such as example.com.' );
+$words = WPCPM_Institution_Details::clean( 'checked', array( 'name' => 'Universidad', 'website' => 'not a site', 'city' => 'Heredia', 'country' => COSTA, 'contact_email' => 'ana@example.test' ) );
+ck( 'a website of words is refused with the same sentence, never kept as https://not%20a%20site (1.123.59)', $words['problems'], array( 'website' => 'Enter a web address such as example.com.' ) );
 
 $long = 'https://ejemplo.edu/' . str_repeat( 'a', 200 );
 
@@ -946,7 +948,7 @@ echo "\n-- write(): a stored change is cleaned again, as a typed one is --------
 
 foreach (
 	array(
-		'a web address naming a scheme of its own' => array( array( 'website' => array( 'from' => 'https://ejemplo.edu', 'to' => 'javascript:alert(1)' ) ), 'Website: Give a web address starting with https://, or just the domain. Nothing was changed.' ),
+		'a web address naming a scheme of its own' => array( array( 'website' => array( 'from' => 'https://ejemplo.edu', 'to' => 'javascript:alert(1)' ) ), 'Website: Enter a web address such as example.com. Nothing was changed.' ),
 		'a web address over 200 characters'        => array( array( 'website' => array( 'from' => 'https://ejemplo.edu', 'to' => 'https://ejemplo.edu/' . str_repeat( 'a', 200 ) ) ), 'Website: Keep this to 200 characters or fewer. Nothing was changed.' ),
 		'a page address over 200 characters'       => array( array( 'credits_page' => array( 'from' => '', 'to' => 'https://ejemplo.edu/' . str_repeat( 'a', 200 ) ) ), 'Page about WordPress Credits: Keep this to 200 characters or fewer. Nothing was changed.' ),
 		'an email that is not one'                 => array( array( 'contact_email' => array( 'from' => 'ana@example.test', 'to' => 'ana at example dot test' ) ), 'Contact\'s email: That does not look like an email address. Nothing was changed.' ),
diff --git a/bin/test-report-form.php b/bin/test-report-form.php
index 6766d28d..8e1f6971 100644
--- a/bin/test-report-form.php
+++ b/bin/test-report-form.php
@@ -432,7 +432,9 @@ ck( 'an empty box clears it', clean( '', $url ), array( true, '' ) );
 
 // The reason `WPCPM_Field_Value::clean_url()` exists rather than trusting the browser: a javascript: URL must not
 // survive, and prefixing it with https would be worse than dropping it.
-ck( 'a javascript: URL does not survive', clean( 'javascript:alert(1)', $url ), array( true, '' ) );
+ck( 'a javascript: URL does not survive: refused, not emptied on the quiet (1.123.59)', clean( 'javascript:alert(1)', $url ), array( false, null ) );
+ck( 'words with a space, or a host with no dot, are not an address: refused, the typing kept for the student to fix (1.123.59)', array( clean( 'my site', $url ), clean( 'mysite', $url ) ), array( array( false, null ), array( false, null ) ) );
+ck( 'and the field says what to give', WPCPM_Student_Report_Form::problem_text( 'bad_url', $url ), 'Enter a web address such as example.com.' );
 
 echo "\n=== Form keys ===\n";
 
diff --git a/bin/test-sponsor-application.php b/bin/test-sponsor-application.php
index b139f634..09d04442 100644
--- a/bin/test-sponsor-application.php
+++ b/bin/test-sponsor-application.php
@@ -694,6 +694,7 @@ ck( 'a name is trimmed and kept', array( $clean['ok'], $clean['value'] ), array(
 ck( 'and cut to the column\'s length rather than refused', mb_strlen( WPCPM_Sponsor_Application::clean( 'Company Name', str_repeat( 'a', 300 ) )['value'] ), 200 );
 ck( 'an address that is not one is refused by name', WPCPM_Sponsor_Application::clean( 'Contact Email', 'sam at gadgetry' )['problem'], 'bad_email' );
 ck( 'a scheme-less website gets the scheme rather than a telling-off', WPCPM_Sponsor_Application::clean( 'Website', 'gadgetry.example' )['value'], 'https://gadgetry.example' );
+ck( 'a website of words, or with no dot in its host, is refused by name rather than emptied (1.123.59)', array( WPCPM_Sponsor_Application::clean( 'Website', 'not a site' )['problem'], WPCPM_Sponsor_Application::clean( 'Website', 'example' )['problem'] ), array( 'bad_url', 'bad_url' ) );
 ck( 'a support answer is no answer clean() takes now, whatever it says: the form does not ask the question', array( WPCPM_Sponsor_Application::clean( 'Sponsorship options', 'Sponsor one or multiple mentors' )['problem'], WPCPM_Sponsor_Application::clean( 'Sponsorship options', '' )['problem'] ), array( 'unknown_field', 'unknown_field' ) );
 ck( 'consent: "1" and "true" are ticks, "yes" and "0" are not', array( WPCPM_Sponsor_Application::clean( 'Privacy Policy Compliance', '1' )['value'], WPCPM_Sponsor_Application::clean( 'Privacy Policy Compliance', 'true' )['value'], WPCPM_Sponsor_Application::clean( 'Privacy Policy Compliance', 'yes' )['value'], WPCPM_Sponsor_Application::clean( 'Privacy Policy Compliance', '0' )['value'] ), array( true, true, false, false ) );
 ck( 'the logo is not an answer clean() takes', WPCPM_Sponsor_Application::clean( 'Logo', 'anything' )['problem'], 'unknown_field' );
@@ -1358,6 +1360,10 @@ $missing = submit( answers( array( 'Company Name' => '', 'Website' => '   ' ) )
 ck( 'a missing answer is a message naming each field, storing nothing', array( $missing['outcome'], $missing['stash']['problems']['Company Name'], $missing['stash']['problems']['Website'], count( stored() ) ), array( 'again', 'required', 'required', 0 ) );
 reset_world();
 ck( 'a refused answer keeps its own reason rather than being called missing', submit( answers( array( 'Contact Email' => 'sam at gadgetry' ) ) )['stash']['problems']['Contact Email'], 'bad_email' );
+$words = submit( answers( array( 'Website' => 'not a site' ) ) );
+follow( $words['url'] );
+$words_back = WPCPM_Sponsor_Application::render();
+ck( 'a website of words is sent back with its own reason, the typing kept and drawn back in its box under the sentence, nothing stored (1.123.59)', array( $words['outcome'], $words['stash']['problems']['Website'] ?? null, $words['stash']['typed']['Website'] ?? null, count( stored() ), false !== strpos( $words_back, 'Enter a web address such as example.com.' ), 1 === preg_match( '#name="wpcpm_sponsor_application\[[^"]+\]" value="not a site"#', $words_back ) ), array( 'again', 'bad_url', 'not a site', 0, true, true ) );
 // The support question is no longer asked (1.123.12), so nothing can require it: the good
 // application above carries no answer to it and is taken. A form drawn before the update and sent
 // after it still posts the old radio under its key: that is taken too, and the answer is not kept,
diff --git a/bin/test-sponsor-cards.php b/bin/test-sponsor-cards.php
index be89931e..52fa229f 100644
--- a/bin/test-sponsor-cards.php
+++ b/bin/test-sponsor-cards.php
@@ -673,6 +673,7 @@ ck( 'and the support choices are the six it offered, in its order', WPCPM_Sponso
 ck( 'a URL without a scheme is completed', WPCPM_Sponsor_Profile::clean( 'website', 'plugins.mango-example.test' ), array( 'ok' => true, 'value' => 'https://plugins.mango-example.test' ) );
 ck( 'a URL with a user is refused', WPCPM_Sponsor_Profile::clean( 'website', 'https://name@host.test' )['ok'], false );
 ck( 'and a URL with a password too', WPCPM_Sponsor_Profile::clean( 'website', 'https://user:pw@host.test/x' )['ok'], false );
+ck( 'a website of words, or with no dot in its host, is refused rather than kept as https://not%20a%20site (1.123.59)', array( WPCPM_Sponsor_Profile::clean( 'website', 'not a site' )['ok'], WPCPM_Sponsor_Profile::clean( 'website', 'example' )['ok'] ), array( false, false ) );
 ck( 'a choice spelled another way is refused', WPCPM_Sponsor_Profile::clean( 'product_type', 'hosting' )['ok'], false );
 ck( 'an empty choice clears the cell', WPCPM_Sponsor_Profile::clean( 'product_type', '' ), array( 'ok' => true, 'value' => null ) );
 ck( 'text is kept to MAX_TEXT as typed: 4,000 characters are kept whole, and one more is refused, never cut', array( WPCPM_Sponsor_Profile::clean( 'anything', str_repeat( 'x', 4000 ) ), WPCPM_Sponsor_Profile::clean( 'anything', str_repeat( 'x', 4001 ) )['ok'] ), array( array( 'ok' => true, 'value' => str_repeat( 'x', 4000 ) ), false ) );
@@ -708,7 +709,7 @@ ck( 'one rejected field rejects the whole save', array( $r[0], WPCPM_Sponsors_In
 redrawn( 'WPCPM_Sponsor_Profile', $A, $context );
 // The sentence is the field's own, which says what to enter and that nothing was saved (1.123.58, SDB-04):
 // the card's message is that sentence, since the map's own is empty.
-ck( 'and the message is the sentence of the one field at fault, in the card and at the field', array( WPCPM_Sponsor_Profile::messages()['profile-rejected'], $GLOBALS['left_detail'] ), array( array( 'error', '' ), 'Enter a web address that starts with https://. Nothing on the profile was saved.' ) );
+ck( 'and the message is the sentence of the one field at fault, in the card and at the field', array( WPCPM_Sponsor_Profile::messages()['profile-rejected'], $GLOBALS['left_detail'] ), array( array( 'error', '' ), 'Enter a web address such as example.com. Nothing on the profile was saved.' ) );
 
 echo "\n=== Profile: a refused save keeps the typing and marks the field at fault (1.123.58) ===\n";
 // SDB-04, SDB-06: the website is refused, and every other box is kept as typed, the long ones included.
@@ -741,7 +742,7 @@ ck( 'a refused profile keeps every typed box, the long ones included, and marks
 ), array(
 	'profile-rejected',
 	array( 'website' => 'https://x@y', 'contact_person' => 'Rep One', 'contact_email' => 'administrator@example.test', 'anything' => $fault_extra, 'description' => 'A new description for the partner page.' ),
-	array( 'website' => 'Enter a web address that starts with https://. Nothing on the profile was saved.' ),
+	array( 'website' => 'Enter a web address such as example.com. Nothing on the profile was saved.' ),
 	true,
 	true,
 	1,
diff --git a/bin/test-sponsors-screen.php b/bin/test-sponsors-screen.php
index 850c5035..5e151e42 100644
--- a/bin/test-sponsors-screen.php
+++ b/bin/test-sponsors-screen.php
@@ -6911,7 +6911,8 @@ function refused_edit_page( array $r, $key ) {
 
 // A website that is not a web address is named alone, at its field.
 $clean = WPCPM_Sponsors::clean_fields( array( 'name' => 'Stale Example', 'website' => 'https://ana%20b@loss.example/' ), (array) WPCPM_Sponsors_Index::row( $GS ) );
-ck( 'a refused website names the website alone, at its field: clean_fields() answers its sentence', array( $clean['ok'], $clean['problems'] ), array( false, array( 'website' => 'Enter a web address that starts with https://.' ) ) );
+ck( 'a refused website names the website alone, at its field: clean_fields() answers its sentence', array( $clean['ok'], $clean['problems'] ), array( false, array( 'website' => 'Enter a web address such as example.com.' ) ) );
+ck( 'a website of words is refused the same way, never kept as https://not%20a%20site (1.123.59)', WPCPM_Sponsors::clean_fields( array( 'name' => 'Stale Example', 'website' => 'not a site' ), (array) WPCPM_Sponsors_Index::row( $GS ) )['problems'], array( 'website' => 'Enter a web address such as example.com.' ) );
 $typed = array_merge( edit_posts( $GS ), array( 'wpcpm_website' => 'https://ana%20b@loss.example/', 'wpcpm_notes' => 'Kept with it.' ) );
 $r     = post( $typed, array( $module, 'handle_edit' ) );
 $page  = refused_edit_page( $r, $GS );
@@ -6919,10 +6920,10 @@ $site_id = 'wpcpm-edit-website';
 ck( 'and the screen holds the summary linking the field, the notice that sends the manager to the marked fields, and the field marked', array(
 	isset( $r[2] ) ? $r[2] : $r,
 	false !== strpos( $page, '<div class="wpcpm-errors notice notice-error inline" role="alert" tabindex="-1">' ),
-	false !== strpos( $page, '<a href="#' . $site_id . '">Website</a>: Enter a web address that starts with https://.' ),
+	false !== strpos( $page, '<a href="#' . $site_id . '">Website</a>: Enter a web address such as example.com.' ),
 	false !== strpos( $page, 'Nothing was saved. The fields marked below need a change.' ),
 	false !== strpos( $page, 'id="' . $site_id . '" name="wpcpm_website" value="https://ana%20b@loss.example/" aria-invalid="true" aria-describedby="' . $site_id . '-problem" />' ),
-	false !== strpos( $page, '<p class="wpcpm-field__problem" id="' . $site_id . '-problem">Enter a web address that starts with https://.</p>' ),
+	false !== strpos( $page, '<p class="wpcpm-field__problem" id="' . $site_id . '-problem">Enter a web address such as example.com.</p>' ),
 	substr_count( $page, 'aria-invalid="true"' ),
 	false !== strpos( $page, 'One answer needs a change' ),
 	false !== strpos( $page, 'Kept with it.' ),
@@ -6934,12 +6935,12 @@ $r     = post( $typed, array( $module, 'handle_edit' ) );
 $page  = refused_edit_page( $r, $GS );
 ck( 'two refused fields are two sentences, each at its field', array(
 	false !== strpos( $page, 'Two answers need a change' ),
-	false !== strpos( $page, '<a href="#wpcpm-edit-website">Website</a>: Enter a web address that starts with https://.' ),
+	false !== strpos( $page, '<a href="#wpcpm-edit-website">Website</a>: Enter a web address such as example.com.' ),
 	false !== strpos( $page, '<a href="#wpcpm-edit-contact-email">Contact email</a>: Enter one email address, such as name@example.org.' ),
 	substr_count( $page, 'class="wpcpm-field__problem"' ),
 ), array( true, true, true, 2 ) );
 $clean = WPCPM_Sponsors::clean_fields( array( 'website' => 'https://ana%20b@loss.example/', 'contact_email' => 'two@@example' ), (array) WPCPM_Sponsors_Index::row( $GS ) );
-ck( 'clean_fields() answers both, in the forms\' order', $clean['problems'], array( 'website' => 'Enter a web address that starts with https://.', 'contact_email' => 'Enter one email address, such as name@example.org.' ) );
+ck( 'clean_fields() answers both, in the forms\' order', $clean['problems'], array( 'website' => 'Enter a web address such as example.com.', 'contact_email' => 'Enter one email address, such as name@example.org.' ) );
 
 // Add: a missing name, and a number over the limit is the Edit form's alone.
 $r    = post( array_merge( add_posts(), array( 'wpcpm_name' => '', 'wpcpm_website' => 'https://ana%20b@loss.example/' ) ), array( $module, 'handle_add' ) );
@@ -6948,7 +6949,7 @@ $page = render_tab( 'sponsors' );
 ck( 'the Add form names its missing name and its website the same way, under its own form', array(
 	isset( $r[2] ) ? $r[2] : $r,
 	false !== strpos( $page, '<a href="#wpcpm-add-name">Name</a>: Give the sponsor a name.' ),
-	false !== strpos( $page, '<a href="#wpcpm-add-website">Website</a>: Enter a web address that starts with https://.' ),
+	false !== strpos( $page, '<a href="#wpcpm-add-website">Website</a>: Enter a web address such as example.com.' ),
 	false !== strpos( $page, 'value="https://ana%20b@loss.example/" aria-invalid="true"' ),
 ), array( 'sponsor-rejected', true, true, true ) );
```

- [ ] **Step 2: Run them to verify they fail**

Run the seven suites. Expected, with main's code:

```text
bin/test-field-value.php
FAIL a javascript: URL does not survive: refused, not emptied on the quiet (1.123.59)
FAIL a value with a space in it is not an address (1.123.59): refused, never stored as https://not%20a%20site
FAIL a host with no dot is not an address either
FAIL a space anywhere, in the path too, refuses the whole value
FAIL clean_url() on its own answers nothing for them, as the readers of stored links expect
FAIL the one sentence every form says for a refused address
6 FAILED (112 checks)
bin/test-report-form.php
FAIL a javascript: URL does not survive: refused, not emptied on the quiet (1.123.59)
FAIL words with a space, or a host with no dot, are not an address: refused, the typing kept for the student to fix (1.123.59)
FAIL and the field says what to give
3 FAILED (233 checks)
bin/test-sponsor-application.php
FAIL a website of words, or with no dot in its host, is refused by name rather than emptied (1.123.59)
FAIL a website of words is sent back with its own reason, the typing kept and drawn back in its box under the sentence, nothing stored (1.123.59)
2 FAILED (384 checks)
bin/test-institution-application.php
FAIL a website of words, or with no dot in its host, is refused the same way, never emptied on the quiet (1.123.59)
FAIL and the form handed back says what to give, the address back in its box as typed and escaped (1.123.58)
FAIL a refused address and a refused web address come back as typed, and bad_url has its own sentence
bin/test-institution-details.php
FAIL and says what to give, in the form's own sentence for it (1.123.58)
FAIL a website of words is refused with the same sentence, never kept as https://not%20a%20site (1.123.59)
FAIL a stored change holding a web address naming a scheme of its own is refused with the form's sentence
bin/test-sponsors-screen.php
FAIL a refused website names the website alone, at its field: clean_fields() answers its sentence
FAIL a website of words is refused the same way, never kept as https://not%20a%20site (1.123.59)
FAIL and the screen holds the summary linking the field, the notice that sends the manager to the marked fields, and the field marked
FAIL two refused fields are two sentences, each at its field
FAIL clean_fields() answers both, in the forms' order
FAIL the Add form names its missing name and its website the same way, under its own form
6 FAILED (526 checks)
bin/test-sponsor-cards.php
FAIL a website of words, or with no dot in its host, is refused rather than kept as https://not%20a%20site (1.123.59)
FAIL and the message is the sentence of the one field at fault, in the card and at the field
FAIL a refused profile keeps every typed box, the long ones included, and marks the one field at fault
3 FAILED (263 checks)
```

- [ ] **Step 3: The rule, the refusal and the sentence**

```diff
diff --git a/includes/class-wpcpm-field-value.php b/includes/class-wpcpm-field-value.php
index 5443cf4a..dc659f59 100644
--- a/includes/class-wpcpm-field-value.php
+++ b/includes/class-wpcpm-field-value.php
@@ -24,7 +24,8 @@ if ( ! defined( 'ABSPATH' ) ) {
  * A refusal names its reason with a short key rather than a sentence, because the caller decides
  * how to say it: a form lists the rejected fields on its flash, an import lists them per row.
  * The keys are `not_scalar`, `not_a_number`, `below_min`, `above_max`, `bad_email`,
- * `bad_choice`, `bad_date` and `out_of_range`.
+ * `bad_choice`, `bad_date`, `bad_url` and `out_of_range`. `bad_url` is a typed web address that
+ * is not one (since 1.123.59, for a space in it or a host with no dot too).
  */
 final class WPCPM_Field_Value {
 
@@ -138,7 +139,15 @@ final class WPCPM_Field_Value {
 		}
 
 		if ( 'url' === $type ) {
-			return self::accept( self::clean_url( $raw ) );
+			$url = self::clean_url( $raw );
+
+			// A box with something in it that is not an address is refused, never emptied on the
+			// quiet: "not a site" used to be stored as https://not%20a%20site (1.123.59).
+			if ( '' === $url && '' !== trim( (string) $raw ) ) {
+				return self::refuse( 'bad_url' );
+			}
+
+			return self::accept( $url );
 		}
 
 		if ( 'rating' === $type ) {
@@ -208,14 +217,16 @@ final class WPCPM_Field_Value {
 	}
 
 	/**
-	 * What a form says about a web address it refuses for naming a scheme other than http or
-	 * https, its `bad_url` (1.123.58, FORM-11): `clean_url()` gives a scheme-less address one, so
-	 * the one thing to ask for is an address that starts with https://, or the domain alone.
+	 * What a form says about a web address it refuses, its `bad_url`: one naming a scheme other
+	 * than http or https (1.123.58, FORM-11) and, since 1.123.59, one with a space in it or a host
+	 * with no dot. `clean_url()` gives a scheme-less address one, so the one thing to ask for is
+	 * an address with a domain in it; the Sponsors screen and the Sponsor Dashboard's profile say
+	 * the same sentence.
 	 *
 	 * @return string
 	 */
 	public static function bad_url_text() {
-		return __( 'Give a web address starting with https://, or just the domain.', 'wpcredits-program-manager' );
+		return __( 'Enter a web address such as example.com.', 'wpcredits-program-manager' );
 	}
 
 	/**
@@ -228,17 +239,36 @@ final class WPCPM_Field_Value {
 	 * @return string
 	 */
 	public static function clean_url( $raw ) {
+		$raw = trim( (string) $raw );
+
 		if ( '' === $raw ) {
 			return '';
 		}
 
+		// A space anywhere is a typo, not an address: `esc_url_raw()` would keep it as `%20`
+		// and the site would store https://not%20a%20site (1.123.59).
+		if ( preg_match( '/\s/', $raw ) ) {
+			return '';
+		}
+
 		if ( ! preg_match( '#^https?://#i', $raw ) ) {
 			$raw = 'https://' . ltrim( $raw, '/' );
 		}
 
 		$url = esc_url_raw( $raw, array( 'http', 'https' ) );
 
-		return $url ? $url : '';
+		if ( ! $url ) {
+			return '';
+		}
+
+		// The host needs a dot with something on both sides: "example" and "https://example" are
+		// not web addresses the program can follow (1.123.59). The host is what sits between the
+		// scheme and the first slash, question mark, hash or port.
+		if ( ! preg_match( '#^https?://[^/?\#:.]+\.[^/?\#:.][^/?\#:]*#i', $url ) ) {
+			return '';
+		}
+
+		return $url;
 	}
 
 	/**
diff --git a/includes/modules/class-wpcpm-institution-application.php b/includes/modules/class-wpcpm-institution-application.php
index d6fde042..aeedf879 100644
--- a/includes/modules/class-wpcpm-institution-application.php
+++ b/includes/modules/class-wpcpm-institution-application.php
@@ -1898,7 +1898,8 @@ class WPCPM_Institution_Application {
 			return __( 'Choose one of the options offered.', 'wpcredits-program-manager' );
 		}
 
-		// A web address naming another scheme, `ftp://` or `mailto:` (1.123.58, FORM-11).
+		// A web address naming another scheme, `ftp://` or `mailto:` (1.123.58, FORM-11), or with a
+		// space in it or a host with no dot (1.123.59).
 		if ( 'bad_url' === $problem ) {
 			return WPCPM_Field_Value::bad_url_text();
 		}
diff --git a/includes/modules/class-wpcpm-sponsor-application.php b/includes/modules/class-wpcpm-sponsor-application.php
index 1432c7c2..98fa7f3f 100644
--- a/includes/modules/class-wpcpm-sponsor-application.php
+++ b/includes/modules/class-wpcpm-sponsor-application.php
@@ -1808,6 +1808,11 @@ class WPCPM_Sponsor_Application {
 			return __( 'Choose one of the options offered.', 'wpcredits-program-manager' );
 		}
 
+		// A web address that is not one: another scheme, a space, a host with no dot (1.123.59).
+		if ( 'bad_url' === $problem ) {
+			return WPCPM_Field_Value::bad_url_text();
+		}
+
 		if ( 'consent' === $problem ) {
 			return __( 'Tick this box to send the form.', 'wpcredits-program-manager' );
 		}
diff --git a/includes/modules/class-wpcpm-sponsor-profile.php b/includes/modules/class-wpcpm-sponsor-profile.php
index 05711ba7..fb395f38 100644
--- a/includes/modules/class-wpcpm-sponsor-profile.php
+++ b/includes/modules/class-wpcpm-sponsor-profile.php
@@ -375,7 +375,7 @@ final class WPCPM_Sponsor_Profile {
 	 */
 	private static function refusal( $key ) {
 		if ( 'website' === $key ) {
-			return __( 'Enter a web address that starts with https://. Nothing on the profile was saved.', 'wpcredits-program-manager' );
+			return __( 'Enter a web address such as example.com. Nothing on the profile was saved.', 'wpcredits-program-manager' );
 		}
 
 		if ( 'contact_email' === $key ) {
diff --git a/includes/modules/class-wpcpm-sponsors.php b/includes/modules/class-wpcpm-sponsors.php
index 772cda7c..2975beb4 100644
--- a/includes/modules/class-wpcpm-sponsors.php
+++ b/includes/modules/class-wpcpm-sponsors.php
@@ -1958,7 +1958,7 @@ class WPCPM_Sponsors extends WPCPM_Sync_Module {
 			case 'name':
 				return __( 'Give the sponsor a name.', 'wpcredits-program-manager' );
 			case 'website':
-				return __( 'Enter a web address that starts with https://.', 'wpcredits-program-manager' );
+				return WPCPM_Field_Value::bad_url_text();
 			case 'contact_email':
 				return __( 'Enter one email address, such as name@example.org.', 'wpcredits-program-manager' );
 		}
diff --git a/includes/modules/class-wpcpm-student-report-form.php b/includes/modules/class-wpcpm-student-report-form.php
index b3c2a765..da4c26cd 100644
--- a/includes/modules/class-wpcpm-student-report-form.php
+++ b/includes/modules/class-wpcpm-student-report-form.php
@@ -1930,6 +1930,11 @@ class WPCPM_Student_Report_Form {
 			return __( 'Write the date as YYYY-MM-DD.', 'wpcredits-program-manager' );
 		}
 
+		// A web address that is not one: another scheme, a space, a host with no dot (1.123.59).
+		if ( 'bad_url' === $problem ) {
+			return WPCPM_Field_Value::bad_url_text();
+		}
+
 		return __( 'That answer could not be read. Write it again.', 'wpcredits-program-manager' );
 	}
```

- [ ] **Step 4: Run them to verify they pass**

Run the seven suites. Expected: `ALL PASS (112 checks)`, `ALL PASS (233 checks)`, `ALL PASS (384 checks)`, `ALL PASS`, `ALL PASS`, `ALL PASS (526 checks)`, `ALL PASS (263 checks)`.

- [ ] **Step 5: The guides**

One paragraph in the Sponsors screen's part of the Administrator guide, one after the application form's paragraph of the operations section, one line in the sponsor guide after the details list, and one sentence appended to the institution guide's paragraph that starts "If a value is refused, the page says" (that paragraph's quoted sentences change in Task 3; here only the sentence is appended):

```diff
diff --git a/docs/sections/30-admin-wpadmin.md b/docs/sections/30-admin-wpadmin.md
index cb6a7eab..1aa650ba 100644
--- a/docs/sections/30-admin-wpadmin.md
+++ b/docs/sections/30-admin-wpadmin.md
@@ -460,6 +460,11 @@ everything typed. A sponsor somebody saved after you opened the form is not over
 says who changed it and when, and keeps what you typed. Only a change to a field the form carries
 counts as a change made since.
 
+Since 1.123.59 a website that is not a web address is refused at its field, with the typing kept:
+one with a space in it, or whose host has no dot, such as "not a site" or "example". The field says
+"Enter a web address such as example.com.", the sentence the Sponsor Dashboard's profile and the two
+application forms say too. Before, such a value was saved as https://not%20a%20site.
+
 **Accounts** holds the **Invitations** card, whose button invites every sponsor account never invited, except one that also mentors and whose invitation was held when an application made it a mentor (the card says how many it left out, and only that account's own row sends it), and the **Sponsor accounts** list, under a warning naming any sponsor account locked out of changes for the rest of the day, a lock that lifts by itself the next day. The card and the list's **Send invite** and **Resend invite** are new for sponsor accounts in 1.122.0: before, a sponsor account was sent only the invitation queued when it was created or attached. The list works as the **Student accounts** list does: every sponsor account, a page at a time, with no email address printed, though the search finds one. **Search sponsor accounts** finds an account by name, username, email address or the name of the sponsor it acts for. The **All**, **Invited** and **Never invited** views split the list by whether an account has been sent an invitation, each with its count. **Sponsor** names the sponsor each account acts for, with the sponsor's status after it in parentheses when it is not Approved, since such a sponsor keeps its accounts until they are removed. The list opens sorted by name, A to Z, so a first press on the **Name** heading turns it Z to A; **Username** and **Sponsor** sort it A to Z, **Member since** by the day the membership began, the earliest first, and **Status** puts **Active** before **Membership ended**, either followed by *locked today* for an account locked for the day; pressing a heading again reverses the order. Every sort works on the whole list, never only on the page in view. **Screen Options** sets how many rows a page holds, 20 by default, and which columns are shown; the **Name** column cannot be hidden, because each row's actions sit under it.
 
 Under each name are **Edit**, which opens the account in wp-admin, **View page**, which opens the Sponsor Dashboard as the sponsor the account acts for, while its membership lasts and that page exists, **Send invite** or **Resend invite**, which emails that one account at once, and **Manage accounts**, for an account whose **Sponsor** names one. To invite several, tick them, choose **Send invite** or **Resend invite** under **Bulk actions** and press **Apply**; both work as they do on the Students screen, through the same queue, and come back to the list as it was. Here too nobody is sent a second invitation within fifteen minutes of the last one, and **Resend invite** on ticked accounts says how many it left out.
diff --git a/docs/sections/34-admin-operations.md b/docs/sections/34-admin-operations.md
index d56c5386..50345fd2 100644
--- a/docs/sections/34-admin-operations.md
+++ b/docs/sections/34-admin-operations.md
@@ -310,6 +310,8 @@ The Tools section is drawn on a person's own Student Report Card (setting *Tools
 
 **The application form (1.97.0).** A company applies to sponsor the program on this site, at /sponsor-application/, instead of in the Airtable form: the same eight questions in the same order (company name, website, contact person and address, how it would like to support the program, a logo in color and in white, anything else, and the confirmation that the company has read the WordPress.org privacy policy, which the form links since 1.123.24), guarded exactly as the institution form is (a hidden field, a signed token, five submissions an hour per address, forty a day for the whole site before the rest are held, consent as a precondition with what was agreed to recorded, links counted, and a ceiling on acknowledgements). The form is off until "Applications from sponsors" is switched on in the settings. Every submission is a private row: the applicant gets an acknowledgement with a reference (SAPP-2026-0007), the Administrators named by the "Interest mail" setting (or every Administrator) get the facts and a link, and the **Sponsor applications** card on the Sponsors screen's **Waiting for review** tab lists what is waiting, oldest first, with a mark for a row the checks held, for another open application naming the same company or address, and for a company the sponsors index already holds under the same name or website. Under that list, *Recently decided* lists the applications somebody has already decided (marked as spam, rejected or approved), newest decision first, so a genuine application the checks filed as spam, or a rejection somebody pressed by mistake, can be opened and put back in the queue rather than waiting for the retention run to delete it. The logo files an applicant sends are kept in the Media Library as private files under a generated name until the application is approved, so nothing a stranger uploaded is listed or reachable before somebody has looked at it; approval publishes both halves and hands them to the sponsor's account. Open one to read the answers, the logo files, what was agreed to and what the sponsors index already holds. The decisions are the six the institution queue has: Approve, Send this question (mailed with your address to reply to), Reject (a short acknowledgement with no reason; your note stays on this site), Reject as spam (nothing is sent), Put back in the queue, Delete for good. An open application is decided on the Administrator Dashboard's own **Sponsor applications** card, and the dashboard's strip counts the queue; since 1.122.0 the Sponsors screen keeps the record-keeping on a decided application, **Put back in the queue** and **Delete for good**, and every decision on an open one past the fifty oldest that card lists. Approve creates the sponsor's record on the site with status Approved, the website, the contact, the sponsorship option, the free text and the two logo files, then in the same press the account (through the same path Create account uses), the company's category, the logo record and the first offer in draft, and queues the welcome unless that account was invited before. If a press got as far as the sponsor's record and then stopped, the row is marked *approval half done* wherever it is drawn, and Reject, Reject as spam and Put back in the queue are refused on it: pressing Approve again finishes whatever is left. A company the sponsors index already holds is never merged: reject the application and use **Create account** on the Sponsors screen's **Accounts** tab instead. The retention settings for institution applications govern this form too, the same three settings by the same names on both (spam after 30 days, rejected after a year, approved never, by default), and a held application is now purged on the rejected window on both forms alike. A spam, held or rejected sponsor application's logo files are deleted with it - the institution form keeps no logo files to delete - and an approved one's are the sponsor's logo and stay. After go-live, one manual step: change the form link on the handbook page to /sponsor-application/.
 
+Since 1.123.59 the Website question of both application forms, and the page about the program on the institution form, refuse an answer with a space in it or whose host has no dot, at the field with the typing kept, under "Enter a web address such as example.com."; before, such an answer was stored as https://not%20a%20site.
+
 **The guide and the queues (1.98.0).** Sponsors have a guide of their own, composed by `bin/build-docs.php` as the `sponsors` audience from four sections (the dashboard, offers and codes, what a sponsor can and cannot see, posts) and published on the handbook beside the other three, where the Sponsor Dashboard's guide button pointed at it (since 1.123.44 the button follows this site's own page, as the Pages the plugin owns part above says). The Administrator Dashboard gained three sponsor cards after the Sponsor agreements card: *Offers running low* (every live pool under the threshold its sponsor set, the emptiest first, linked to the sponsor's Offers and codes card through the switcher), *New interests* (what sponsors sent in the last thirty days, as they wrote it: from *Offer more* until 1.123.22, from *Talk to your program contact* since) and *Sponsors* (Approved sponsors, how many hold an account, the offers live today, the claims since the semester began). The *Sponsor applications* card folds the whole application under *Read the application*, and the needs-attention strip has a twelfth tile for the pools running low. Nothing here is a new option or a new post type: the phase reads what the earlier phases wrote.
 
 **The clean-up (1.98.1).** The two application forms share one stash-and-redirect class, `WPCPM_Form_Stash`. The Sponsors menu bubble is counted once a minute and forgets its count the moment an application, an agreement or a sponsor post changes. A dwell token has one shape. Recently decided reads a decision-time index instead of every decided row. The docs build escapes a section's text, so a literal angle bracket is a character on the page. The institution agreement retries a failed Airtable write on every sync run. A refused image store deletes the copy it made.
diff --git a/docs/sections/45-sponsor-company.md b/docs/sections/45-sponsor-company.md
index 2f554c08..de66005c 100644
--- a/docs/sections/45-sponsor-company.md
+++ b/docs/sections/45-sponsor-company.md
@@ -14,6 +14,8 @@ The details are:
 - **Anything else you would like to share**, which is optional.
 - **Description for the partner page**, from Supporter up, as the next part describes.
 
+Since 1.123.59 a website needs a dot in its address, such as example.com: words with a space in them, or a name with no dot, are refused at the field with what you typed kept, under "Enter a web address such as example.com.".
+
 Press **Save profile**. Once your company has an offer, the card adds that the offer's text, instructions and link are edited on the **Offers and codes** card.
 
 Under **Your logo**, a logo in color and one in white for a dark background are shown wherever the program names you, beside your offer among them. Logos look alike side by side when they share a shape, so upload a horizontal one, about twice as wide as it is tall, at least 300 pixels wide (600 or more looks sharper), as a PNG, JPEG or WebP image, transparent where possible. SVG is not accepted, so export the logo as a PNG. Choose a **Color logo**, a **White logo, optional**, or both, and press **Save the logo**; **Remove it** takes the logo you uploaded off again.
```

In `docs/sections/52-institution-details.md`, append to the paragraph that starts `If a value is refused, the page says`, after its last sentence: `Since 1.123.59 a website or a page address with a space in it, or whose host has no dot, is refused the same way, under "Enter a web address such as example.com.".`

Then `php bin/build-docs.php && php bin/build-docs.php && git status --short docs/`, `php bin/test-handbook.php`, `php bin/test-admin-menu.php`: `ALL PASS` from both.

- [ ] **Step 6: The checks every task ends with**

Expected as the block above says.

- [ ] **Step 7: Commit**

```bash
git add includes/class-wpcpm-field-value.php includes/modules/class-wpcpm-sponsors.php includes/modules/class-wpcpm-sponsor-profile.php includes/modules/class-wpcpm-sponsor-application.php includes/modules/class-wpcpm-student-report-form.php includes/modules/class-wpcpm-institution-application.php bin/test-field-value.php bin/test-report-form.php bin/test-sponsor-application.php bin/test-institution-application.php bin/test-institution-details.php bin/test-sponsors-screen.php bin/test-sponsor-cards.php docs/
git commit -m "1.123.59: a website that is not a web address is refused at its field, with one sentence" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 3: No sentence of the plugin's own starts with "Please"

**What this task decides.** The owner's yes named the mentor application's thank-you page, whose two sentences start with "Please". The house rule is the plugin's whole copy, so this task takes every sentence of the plugin's own pages and messages that starts with the word: eighteen strings in ten files (the plan first said fifteen; the diff below is the count), each kept to its meaning with the word dropped or the verb put first ("Please try it again." becomes "Write it again.", the Student Report Card's existing fallback; "Please try again in a while." becomes "Try again in a while."; "Please correct what is marked below and save again." becomes "Correct what is marked below and save again."; "Please log in." becomes "Log in to use it."). Left as they are, on purpose: the emails under Tools > Emails (`includes/mail/templates/`), whose words were agreed with the mentor coordinator and which she may have edited on the site; the Mentor Status Checker's Slack line ("Please add them to #mentors:"), a message to the team, pinned nine times in its suite; the mentor guide's own prose ("Please do not chase them." in `docs/sections/26-mentor-student-view.md`) and the Administrator guide's quote of the acknowledgement email in `30-admin-wpadmin.md`, which is a template's sentence; and a `wp_die()` text in `bin/test-report-images.php`, a stand-in. Two guide quotes of plugin sentences change with the sentences.

**Files:**
- Modify: `includes/class-wpcpm-content-access.php`, `includes/tools/class-wpcpm-handbook-assistant.php`, `includes/modules/class-wpcpm-institution-application.php`, `class-wpcpm-sponsor-application.php`, `class-wpcpm-mentor-application-form.php`, `class-wpcpm-student-report-form.php`, `class-wpcpm-student-registration-form.php`, `class-wpcpm-institution-details.php`, `class-wpcpm-institution-details-card.php`, `class-wpcpm-mentor-calls.php`
- Test: `bin/test-mentor-application-form.php`, `bin/test-sponsor-application.php`, `bin/test-institution-application.php`, `bin/test-institution-details.php`, `bin/test-institution-details-card.php`, `bin/test-handbook.php`, `bin/test-admin-menu.php`
- Guide: `docs/sections/52-institution-details.md`, `docs/sections/33-admin-access.md`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new; strings only.

- [ ] **Step 1: Write the failing checks**

The three thank-you pages are pinned on the sentence each asks with, and on holding no "Please" at all; the other pins follow their sentences.

```diff
diff --git a/bin/test-admin-menu.php b/bin/test-admin-menu.php
index 4690fb50..44a54990 100644
--- a/bin/test-admin-menu.php
+++ b/bin/test-admin-menu.php
@@ -874,9 +874,9 @@ ck( 'the first group\'s details are kept in two places, and the guide says which
     array(
 		1 === preg_match( "/'contact_person'\\s*=> array\\(\\s*'group'\\s*=> self::GROUP_NOW,\\s*'label'\\s*=> __\\( 'Contact person'.*?'source'\\s*=> 'airtable'/s", $details_src ),
 		1 === preg_match( "/'contact_role'\\s*=> array\\(\\s*'group'\\s*=> self::GROUP_NOW,\\s*'label'\\s*=> __\\( 'Their role'.*?'source'\\s*=> 'record'/s", $details_src ),
-		false !== strpos( $details_src, "The details could not be kept on the site, so nothing was saved. Please try again in a while." ),
+		false !== strpos( $details_src, "The details could not be kept on the site, so nothing was saved. Try again in a while." ),
 		false !== strpos( $institution_said['sections/52-institution-details.md'], 'The contact person is written to the program\'s records and the other details are kept on this site, straight away' ),
-		false !== strpos( $institution_said['sections/52-institution-details.md'], 'The details could not be kept on the site, so nothing was saved. Please try again in a while.' ),
+		false !== strpos( $institution_said['sections/52-institution-details.md'], 'The details could not be kept on the site, so nothing was saved. Try again in a while.' ),
 	),
     array( true, true, true, true, true ) );
 
diff --git a/bin/test-handbook.php b/bin/test-handbook.php
index 73df0748..2ad8d76d 100644
--- a/bin/test-handbook.php
+++ b/bin/test-handbook.php
@@ -675,7 +675,7 @@ $GLOBALS['uid'] = 0;
 $rendered = WPCPM_Handbook_Assistant::render();
 ck( 'and the shortcode refuses to draw for a visitor',
     array(
-        false !== strpos( $rendered, 'Please log in' ),
+        false !== strpos( $rendered, 'Log in to use it' ),
         false !== strpos( $rendered, 'wpcpm-handbook__form' ),
     ),
     array( true, false ) );
diff --git a/bin/test-institution-application.php b/bin/test-institution-application.php
index 46660042..c5706c30 100644
--- a/bin/test-institution-application.php
+++ b/bin/test-institution-application.php
@@ -2758,8 +2759,9 @@ follow( $applied['url'] );
 $done = WPCPM_Institution_Application::render();
 
 ck( 'and the page after sending says the same need where it speaks of the email', array(
-	false !== strpos( $done, 'with a link that confirms the address. Please follow it: we cannot take your application further until you do.' ),
+	false !== strpos( $done, 'with a link that confirms the address. Follow it: we cannot take your application further until you do.' ),
 ), array( true ) );
+ck( 'and no sentence on it starts with Please (1.123.59)', substr_count( $done, 'Please' ), 0 );
 
 // A cached copy of any page the form is drawn on would hand one visitor another's token, answers or
 // confirmation, so every draw sends the headers, the block's and the shortcode's alike (1.123.58).
diff --git a/bin/test-institution-details-card.php b/bin/test-institution-details-card.php
index d7aa7a18..eaa43ade 100644
--- a/bin/test-institution-details-card.php
+++ b/bin/test-institution-details-card.php
@@ -1129,7 +1129,7 @@ $page                = draw( 7 );
 $form                = disclosure( $page, 'now' );
 ck( 'the member is sent back to the card', $out, member_back() );
 ck( 'what the base answered is not said to the school', has( $page, 'Airtable said 422' ), false );
-ck( 'the sentence says so', has( $page, '<p class="wpcpm-details__message is-error" role="status">The program records could not be updated, so nothing was saved. Please try again in a while.</p>' ), true );
+ck( 'the sentence says so', has( $page, '<p class="wpcpm-details__message is-error" role="status">The program records could not be updated, so nothing was saved. Try again in a while.</p>' ), true );
 ck( 'nothing was written on the site: not the index, not the record', array( $GLOBALS['index'][ A ]['contact_person'], $GLOBALS['records'][ A ]['contact_role'], $GLOBALS['records'][ A ]['student_languages'], in_array( 'index', $GLOBALS['events'], true ), in_array( 'record', $GLOBALS['events'], true ) ), array( 'Contact One', 'Head of the international office', array( 'Spanish', 'English' ), false, false ) );
 ck( 'no history row and no email', array( audit( A, 'details_saved' ), $GLOBALS['mail'] ), array( array(), array() ) );
 ck( 'every typed value comes back, the ones that were not Airtable\'s too', array( has( $form, ' open' ), box( $form, 'wpcpm_details_contact_person' ), box( $form, 'wpcpm_details_contact_role' ), ticked( $form ) ), array( true, 'Contact Two', 'Dean of Studies', array( 'French', 'Spanish' ) ) );
@@ -1138,7 +1138,7 @@ ck( 'and the lists above still read what the program has', array( rows( $page )[
 fresh();
 $GLOBALS['airtable'] = array();
 post_as( 7, 'now', array( 'wpcpm_details_contact_person' => 'Contact Two' ) );
-ck( 'an answer of nothing written is a refusal too', array( has( draw( 7 ), 'The program records could not be updated, so nothing was saved. Please try again in a while.' ), $GLOBALS['index'][ A ]['contact_person'], $GLOBALS['mail'] ), array( true, 'Contact One', array() ) );
+ck( 'an answer of nothing written is a refusal too', array( has( draw( 7 ), 'The program records could not be updated, so nothing was saved. Try again in a while.' ), $GLOBALS['index'][ A ]['contact_person'], $GLOBALS['mail'] ), array( true, 'Contact One', array() ) );
 
 fresh();
 $GLOBALS['airtable'] = new WP_Error( 'wpcpm_airtable_http', 'Airtable said 422' );
@@ -1329,7 +1329,7 @@ ck( 'the outcome sentences are the plan\'s', array( $words['saved'], $words['ask
 	array( 'success', 'Your institution\'s details are saved.' ),
 	array( 'success', 'Sent: an Administrator checks the change, and you will see it here once it is decided.' ),
 	array( 'info', 'Nothing was changed.' ),
-	array( 'error', 'The program records could not be updated, so nothing was saved. Please try again in a while.' ),
+	array( 'error', 'The program records could not be updated, so nothing was saved. Try again in a while.' ),
 ) );
 ck( 'and none of them holds a dash', preg_match( '/[\x{2013}\x{2014}]/u', json_encode( $words, JSON_UNESCAPED_UNICODE ) ), 0 );
 
@@ -1352,7 +1352,7 @@ foreach ( array_merge( WPCPM_Institution_Details_Card::messages(), WPCPM_Institu
 }
 words_in( $decide );
 ck( 'the card holds none of them, in any state this suite draws it in or any sentence it can say', array_values( $GLOBALS['base_words'] ), array() );
-ck( 'and the refusal a school reads when the base turns a save down names the program\'s records instead', WPCPM_Institution_Details_Card::messages()['airtable'], array( 'error', 'The program records could not be updated, so nothing was saved. Please try again in a while.' ) );
+ck( 'and the refusal a school reads when the base turns a save down names the program\'s records instead', WPCPM_Institution_Details_Card::messages()['airtable'], array( 'error', 'The program records could not be updated, so nothing was saved. Try again in a while.' ) );
 
 // The Institution Dashboard's suite holds this card to its rule by running this suite once more and reading the
 // last line it prints (`bin/test-institutions-dashboard.php`).
diff --git a/bin/test-institution-details.php b/bin/test-institution-details.php
index 5670e80e..6df2c2be 100644
--- a/bin/test-institution-details.php
+++ b/bin/test-institution-details.php
@@ -989,7 +991,7 @@ foreach ( array( 'post' => 'WordPress would not make the record\'s post', 'meta'
 
 	$result = WPCPM_Institution_Details::write( OTHER, array( 'contact_role' => array( 'from' => '', 'to' => 'Registrar' ) ), 7, 'member' );
 
-	ck( sprintf( 'when %s, a correction of the record\'s answers alone is an error with the plugin\'s sentence', $why ), array( is_wp_error( $result ) ? $result->get_error_code() : $result, is_wp_error( $result ) ? $result->get_error_message() : '' ), array( 'wpcpm_details_record', 'The details could not be kept on the site, so nothing was saved. Please try again in a while.' ) );
+	ck( sprintf( 'when %s, a correction of the record\'s answers alone is an error with the plugin\'s sentence', $why ), array( is_wp_error( $result ) ? $result->get_error_code() : $result, is_wp_error( $result ) ? $result->get_error_message() : '' ), array( 'wpcpm_details_record', 'The details could not be kept on the site, so nothing was saved. Try again in a while.' ) );
 	ck( sprintf( 'and when %s, it wrote no row of history and called Airtable and the index not at all', $why ), array( audit_rows( OTHER ), $GLOBALS['journal'], $GLOBALS['patched'] ), array( array(), array(), array() ) );
 }
 
diff --git a/bin/test-mentor-application-form.php b/bin/test-mentor-application-form.php
index f07cc30f..bb895d42 100644
--- a/bin/test-mentor-application-form.php
+++ b/bin/test-mentor-application-form.php
@@ -686,6 +686,10 @@ ck(
 reset_world();
 $thanked = submit( answers() );
 $good    = only_row();
+$_GET         = array( WPCPM_Mentor_Application_Form::QUERY_STASH => substr( $thanked['url'], strpos( $thanked['url'], '=' ) + 1 ) );
+$thanked_page = WPCPM_Mentor_Application_Form::render();
+ck( 'the thank-you page speaks plainly: no sentence starts with Please (1.123.59)', array( false !== strpos( $thanked_page, 'Quote it if you write to us about this application.' ), false !== strpos( $thanked_page, 'with a link that confirms the address. Follow it: the program writes to you about your application only once the address is confirmed.' ), substr_count( $thanked_page, 'Please' ) ), array( true, true, 0 ) );
+$_GET         = array();
 $state   = function ( $row ) {
 	return array( WPCPM_Mentor_Application::state_of( $row ), WPCPM_Mentor_Application::fields_of( $row ), array_column( WPCPM_Mentor_Application::events( (int) $row->ID ), 'event' ), WPCPM_Mentor_Application::reference_of( $row ), get_post_meta( $row->ID, WPCPM_Mentor_Application::META_EMAIL, true ), get_post_meta( $row->ID, WPCPM_Mentor_Application::META_CONSENT, true )['sentence'] ?? null );
 };
diff --git a/bin/test-sponsor-application.php b/bin/test-sponsor-application.php
index b139f634..09d04442 100644
--- a/bin/test-sponsor-application.php
+++ b/bin/test-sponsor-application.php
@@ -1153,6 +1154,7 @@ ck( 'the redirect carries a random id and nothing else', preg_match( '#^https://
 $_GET[ WPCPM_Sponsor_Application::QUERY_STASH ] = $sent['id'];
 $page = WPCPM_Sponsor_Application::render();
 ck( 'the confirmation names the reference and draws no form', array( false !== strpos( $page, $expected_reference ), strpos( $page, '<form' ) ), array( true, false ) );
+ck( 'and it speaks plainly: the reference sentence asks without Please, and no sentence on the page starts with it (1.123.59)', array( false !== strpos( $page, 'Quote it if you write to us about this application.' ), substr_count( $page, 'Please' ) ), array( true, 0 ) );
 // Read, not taken since 1.123.58 (FORM-06): within its thirty minutes a reload draws it again.
 ck( 'a reload of the thank-you within its 30 minutes draws the confirmation again, with the reference', array( WPCPM_Sponsor_Application::render() === $page, false !== strpos( $page, $expected_reference ) ), array( true, true ) );
 $GLOBALS['transients'] = array();
```

- [ ] **Step 2: Run them to verify they fail**

Run the seven suites. Expected, with the sentences as main has them:

```text
bin/test-mentor-application-form.php
FAIL the thank-you page speaks plainly: no sentence starts with Please (1.123.59)
bin/test-sponsor-application.php
FAIL and it speaks plainly: the reference sentence asks without Please, and no sentence on the page starts with it (1.123.59)
1 FAILED (385 checks)
bin/test-institution-application.php
FAIL and the page after sending says the same need where it speaks of the email
FAIL and no sentence on it starts with Please (1.123.59)
bin/test-institution-details.php
FAIL when WordPress would not make the record's post, a correction of the record's answers alone is an error with the plugin's sentence
FAIL when WordPress refused the answers, a correction of the record's answers alone is an error with the plugin's sentence
bin/test-institution-details-card.php
FAIL the sentence says so
FAIL an answer of nothing written is a refusal too
FAIL the outcome sentences are the plan's
FAIL and the refusal a school reads when the base turns a save down names the program's records instead
bin/test-handbook.php
FAIL and the shortcode refuses to draw for a visitor
bin/test-admin-menu.php
FAIL the first group's details are kept in two places, and the guide says which: the contact person in the program's records and the rest on this site, with the sentence for each refusal
```

(`bin/test-handbook.php` and `bin/test-admin-menu.php` read the guide, so they fail until step 5 as well as until step 3.)

- [ ] **Step 3: The eighteen sentences**

```diff
diff --git a/includes/class-wpcpm-content-access.php b/includes/class-wpcpm-content-access.php
index 1f7bf695..073f3e30 100644
--- a/includes/class-wpcpm-content-access.php
+++ b/includes/class-wpcpm-content-access.php
@@ -468,7 +468,7 @@ class WPCPM_Content_Access {
 	private static function notice( $level ) {
 		$message = is_user_logged_in()
 			? self::denial_message( $level )
-			: __( 'This content is for program members. Please log in to continue.', 'wpcredits-program-manager' );
+			: __( 'This content is for program members. Log in to continue.', 'wpcredits-program-manager' );
 
 		$html = '<p class="wpcpm-restricted">' . esc_html( $message ) . '</p>';
 
diff --git a/includes/modules/class-wpcpm-institution-application.php b/includes/modules/class-wpcpm-institution-application.php
index d6fde042..aeedf879 100644
--- a/includes/modules/class-wpcpm-institution-application.php
+++ b/includes/modules/class-wpcpm-institution-application.php
@@ -1452,7 +1452,7 @@ class WPCPM_Institution_Application {
 				esc_html(
 					sprintf(
 						/* translators: %s: the application reference, e.g. APP-2026-0007. */
-						__( 'Your reference is %s. Please quote it if you write to us about this application.', 'wpcredits-program-manager' ),
+						__( 'Your reference is %s. Quote it if you write to us about this application.', 'wpcredits-program-manager' ),
 						$reference
 					)
 				)
@@ -1466,7 +1466,7 @@ class WPCPM_Institution_Application {
 					$acknowledged
 						? sprintf(
 							/* translators: %s: the contact email address the applicant gave. */
-							__( 'We have sent an acknowledgement to %s with a link that confirms the address. Please follow it: we cannot take your application further until you do. An Administrator reads every application by hand, so a reply takes a few working days.', 'wpcredits-program-manager' ),
+							__( 'We have sent an acknowledgement to %s with a link that confirms the address. Follow it: we cannot take your application further until you do. An Administrator reads every application by hand, so a reply takes a few working days.', 'wpcredits-program-manager' ),
 							$email
 						)
 						: sprintf(
@@ -1907,7 +1908,7 @@ class WPCPM_Institution_Application {
 			return __( 'Tick this box to send the form.', 'wpcredits-program-manager' );
 		}
 
-		return __( 'That answer could not be read. Please try it again.', 'wpcredits-program-manager' );
+		return __( 'That answer could not be read. Write it again.', 'wpcredits-program-manager' );
 	}
 
 	/*
diff --git a/includes/modules/class-wpcpm-institution-details-card.php b/includes/modules/class-wpcpm-institution-details-card.php
index 4470a8e2..0b27595a 100644
--- a/includes/modules/class-wpcpm-institution-details-card.php
+++ b/includes/modules/class-wpcpm-institution-details-card.php
@@ -107,9 +107,9 @@ class WPCPM_Institution_Details_Card {
 			'asked'           => array( 'success', __( 'Sent: an Administrator checks the change, and you will see it here once it is decided.', 'wpcredits-program-manager' ) ),
 			'nothing'         => array( 'info', __( 'Nothing was changed.', 'wpcredits-program-manager' ) ),
 			// The page never names the base to a school: the records the program keeps are what could not be updated.
-			'airtable'        => array( 'error', __( 'The program records could not be updated, so nothing was saved. Please try again in a while.', 'wpcredits-program-manager' ) ),
-			'refused-now'     => array( 'error', __( 'Nothing was saved. Please correct what is marked below and save again.', 'wpcredits-program-manager' ) ),
-			'refused-checked' => array( 'error', __( 'Nothing was sent. Please correct what is marked below and send it again.', 'wpcredits-program-manager' ) ),
+			'airtable'        => array( 'error', __( 'The program records could not be updated, so nothing was saved. Try again in a while.', 'wpcredits-program-manager' ) ),
+			'refused-now'     => array( 'error', __( 'Nothing was saved. Correct what is marked below and save again.', 'wpcredits-program-manager' ) ),
+			'refused-checked' => array( 'error', __( 'Nothing was sent. Correct what is marked below and send it again.', 'wpcredits-program-manager' ) ),
 			// A save at once that found the school's lock held: the store's words for a raise that found it so.
 			'saving-busy'     => array( 'error', WPCPM_Institution_Request::saving_message() ),
 		);
diff --git a/includes/modules/class-wpcpm-institution-details.php b/includes/modules/class-wpcpm-institution-details.php
index 358805e6..53084989 100644
--- a/includes/modules/class-wpcpm-institution-details.php
+++ b/includes/modules/class-wpcpm-institution-details.php
@@ -455,7 +455,7 @@ class WPCPM_Institution_Details {
 			// saved nothing. After an Airtable change they cannot be undone with it, so the change
 			// stands and the record is the next correction's to fill.
 			if ( ! $kept && empty( $prepared['cells'] ) ) {
-				return new WP_Error( 'wpcpm_details_record', __( 'The details could not be kept on the site, so nothing was saved. Please try again in a while.', 'wpcredits-program-manager' ) );
+				return new WP_Error( 'wpcpm_details_record', __( 'The details could not be kept on the site, so nothing was saved. Try again in a while.', 'wpcredits-program-manager' ) );
 			}
 		}
 
diff --git a/includes/modules/class-wpcpm-mentor-application-form.php b/includes/modules/class-wpcpm-mentor-application-form.php
index 03c9fb25..9cb793a6 100644
--- a/includes/modules/class-wpcpm-mentor-application-form.php
+++ b/includes/modules/class-wpcpm-mentor-application-form.php
@@ -1084,7 +1084,7 @@ final class WPCPM_Mentor_Application_Form {
 				esc_html(
 					sprintf(
 						/* translators: %s: the application reference, e.g. MAPP-2026-0007. */
-						__( 'Your reference is %s. Please quote it if you write to us about this application.', 'wpcredits-program-manager' ),
+						__( 'Your reference is %s. Quote it if you write to us about this application.', 'wpcredits-program-manager' ),
 						$reference
 					)
 				)
@@ -1098,7 +1098,7 @@ final class WPCPM_Mentor_Application_Form {
 					$acknowledged
 						? sprintf(
 							/* translators: %s: the address the applicant gave. */
-							__( 'We have sent an acknowledgement to %s with a link that confirms the address. Please follow it: the program writes to you about your application only once the address is confirmed. Every application is read by hand, and a reply can take several weeks.', 'wpcredits-program-manager' ),
+							__( 'We have sent an acknowledgement to %s with a link that confirms the address. Follow it: the program writes to you about your application only once the address is confirmed. Every application is read by hand, and a reply can take several weeks.', 'wpcredits-program-manager' ),
 							$email
 						)
 						: sprintf(
@@ -1449,7 +1449,7 @@ final class WPCPM_Mentor_Application_Form {
 			'consent'        => __( 'Tick this box to send the form.', 'wpcredits-program-manager' ),
 		);
 
-		return isset( $said[ $problem ] ) ? $said[ $problem ] : __( 'That answer could not be read. Please try it again.', 'wpcredits-program-manager' );
+		return isset( $said[ $problem ] ) ? $said[ $problem ] : __( 'That answer could not be read. Write it again.', 'wpcredits-program-manager' );
 	}
 
 	/*
diff --git a/includes/modules/class-wpcpm-mentor-calls.php b/includes/modules/class-wpcpm-mentor-calls.php
index 973142c8..43f12b33 100644
--- a/includes/modules/class-wpcpm-mentor-calls.php
+++ b/includes/modules/class-wpcpm-mentor-calls.php
@@ -1471,8 +1471,8 @@ class WPCPM_Mentor_Calls {
 			'booked'              => array( 'success', __( 'Your call is booked. It is in the list above, and your mentor can see it too.', 'wpcredits-program-manager' ) ),
 			'cancelled'           => array( 'success', __( 'That call is canceled and the slot is free again.', 'wpcredits-program-manager' ) ),
 			'zone'                => array( 'success', __( 'Times are now shown in your timezone.', 'wpcredits-program-manager' ) ),
-			'taken'               => array( 'error', __( 'Somebody booked that slot first. Please pick another one.', 'wpcredits-program-manager' ) ),
-			'busy'                => array( 'error', __( 'Another booking was going through at the same moment. Please try again.', 'wpcredits-program-manager' ) ),
+			'taken'               => array( 'error', __( 'Somebody booked that slot first. Pick another one.', 'wpcredits-program-manager' ) ),
+			'busy'                => array( 'error', __( 'Another booking was going through at the same moment. Try again.', 'wpcredits-program-manager' ) ),
 			'blocked'             => array( 'error', __( 'That call could not be booked. The calendar below says why.', 'wpcredits-program-manager' ) ),
 			'no-mentor'           => array( 'error', __( 'No mentor is linked to your account yet, so there is nobody to book with.', 'wpcredits-program-manager' ) ),
 			'error'               => array( 'error', __( 'That call could not be saved.', 'wpcredits-program-manager' ) ),
diff --git a/includes/modules/class-wpcpm-sponsor-application.php b/includes/modules/class-wpcpm-sponsor-application.php
index 1432c7c2..98fa7f3f 100644
--- a/includes/modules/class-wpcpm-sponsor-application.php
+++ b/includes/modules/class-wpcpm-sponsor-application.php
@@ -1117,7 +1117,7 @@ class WPCPM_Sponsor_Application {
 				esc_html(
 					sprintf(
 						/* translators: %s: the application reference, e.g. SAPP-2026-0007. */
-						__( 'Your reference is %s. Please quote it if you write to us about this application.', 'wpcredits-program-manager' ),
+						__( 'Your reference is %s. Quote it if you write to us about this application.', 'wpcredits-program-manager' ),
 						$reference
 					)
 				)
@@ -1839,7 +1844,7 @@ class WPCPM_Sponsor_Application {
 			return isset( $images[ $problem ] ) ? $images[ $problem ] : $images['image_missing'];
 		}
 
-		return __( 'That answer could not be read. Please try it again.', 'wpcredits-program-manager' );
+		return __( 'That answer could not be read. Write it again.', 'wpcredits-program-manager' );
 	}
 
 	/*
diff --git a/includes/modules/class-wpcpm-student-registration-form.php b/includes/modules/class-wpcpm-student-registration-form.php
index a1a0b87e..c7481664 100644
--- a/includes/modules/class-wpcpm-student-registration-form.php
+++ b/includes/modules/class-wpcpm-student-registration-form.php
@@ -1426,7 +1426,7 @@ final class WPCPM_Student_Registration_Form {
 			'consent'              => __( 'Tick this box to send the form.', 'wpcredits-program-manager' ),
 		);
 
-		return isset( $said[ $problem ] ) ? $said[ $problem ] : __( 'That answer could not be read. Please try it again.', 'wpcredits-program-manager' );
+		return isset( $said[ $problem ] ) ? $said[ $problem ] : __( 'That answer could not be read. Write it again.', 'wpcredits-program-manager' );
 	}
 
 	/*
diff --git a/includes/modules/class-wpcpm-student-report-form.php b/includes/modules/class-wpcpm-student-report-form.php
index b3c2a765..da4c26cd 100644
--- a/includes/modules/class-wpcpm-student-report-form.php
+++ b/includes/modules/class-wpcpm-student-report-form.php
@@ -2370,7 +2375,7 @@ class WPCPM_Student_Report_Form {
 			),
 			'report-image-removed' => array( 'success', __( 'That screenshot is removed, here and from the program records.', 'wpcredits-program-manager' ) ),
 			'report-image-partly'  => array( 'error', __( 'That screenshot is removed from the program records, but this site could not delete its own copy, so it is still shown here. Press Remove again.', 'wpcredits-program-manager' ) ),
-			'report-image-kept'    => array( 'error', __( 'The screenshot was left in place: the program records could not be told to drop it. Please try again.', 'wpcredits-program-manager' ) ),
+			'report-image-kept'    => array( 'error', __( 'The screenshot was left in place: the program records could not be told to drop it. Try again.', 'wpcredits-program-manager' ) ),
 			'report-image-none'    => array( 'error', __( 'There is no screenshot of yours on that question, so there was nothing to remove.', 'wpcredits-program-manager' ) ),
 		);
 
diff --git a/includes/tools/class-wpcpm-handbook-assistant.php b/includes/tools/class-wpcpm-handbook-assistant.php
index 3ce89644..4c151380 100644
--- a/includes/tools/class-wpcpm-handbook-assistant.php
+++ b/includes/tools/class-wpcpm-handbook-assistant.php
@@ -359,7 +359,7 @@ class WPCPM_Handbook_Assistant {
 		}
 
 		if ( ! is_user_logged_in() ) {
-			return self::notice( __( 'This is for people on the program. Please log in.', 'wpcredits-program-manager' ) );
+			return self::notice( __( 'This is for people on the program. Log in to use it.', 'wpcredits-program-manager' ) );
 		}
 
 		if ( ! self::user_can_ask() ) {
```

- [ ] **Step 4: Run them to verify they pass**

Run the seven suites after step 5. Expected: `ALL PASS (120 checks)`, `ALL PASS (385 checks)`, `ALL PASS`, `ALL PASS`, `ALL PASS`, `ALL PASS`, `ALL PASS`.

- [ ] **Step 5: The two guide quotes**

In `docs/sections/52-institution-details.md` the paragraph already ends with Task 2's sentence; only its two quoted sentences change here, as the diff's added line shows them.

```diff
diff --git a/docs/sections/33-admin-access.md b/docs/sections/33-admin-access.md
index 48c01b72..1962755b 100644
--- a/docs/sections/33-admin-access.md
+++ b/docs/sections/33-admin-access.md
@@ -111,7 +111,7 @@ it was drawn is not a change, even one the program holds in a form the applicati
 refused post gives back what was typed, with the problem under the field that has one, and a text that
 WordPress would take words from is refused whole, as on the application. If Airtable refuses the contact
 person's save, nothing is saved on this site either, the typed values come back, and the card says
-"The program records could not be updated, so nothing was saved. Please try again in a while." The card
+"The program records could not be updated, so nothing was saved. Try again in a while." The card
 never names Airtable to the institution.
 
 A save at once is made under a lock of the institution's own, the option
diff --git a/docs/sections/52-institution-details.md b/docs/sections/52-institution-details.md
index 0cdf7ddf..4ff7ab53 100644
--- a/docs/sections/52-institution-details.md
+++ b/docs/sections/52-institution-details.md
@@ -17,11 +17,11 @@ Under each list is a closed section that opens the form for that list alone. The
 
 Open **Correct these** (saved at once) under the first list, change what is wrong and press **Save the details**. The contact person is written to the program's records and the other details are kept on this site, straight away, the Administrators are emailed what changed and from what to what, and a line is added to your institution's history. The page says "Your institution's details are saved."
 
-If a value is refused, the page says "Nothing was saved. Please correct what is marked below and save again." and puts the reason under the box that has one; what you typed stays in the form. If the program's records cannot be written just now, it says "The program records could not be updated, so nothing was saved. Please try again in a while." and nothing is saved here either, so the form and the records cannot disagree. If this site cannot keep the details that live on it, it says "The details could not be kept on the site, so nothing was saved. Please try again in a while." A form sent with nothing changed says "Nothing was changed." and sends no email, and so does the same change sent again from another tab, or by a colleague, a moment after the first was saved. If a save of your institution's details is being made at that very moment, the page says "Someone else is saving your institution's details at this moment, so nothing was done. Try again in a moment." and what you typed stays in the form.
+If a value is refused, the page says "Nothing was saved. Correct what is marked below and save again." and puts the reason under the box that has one; what you typed stays in the form. If the program's records cannot be written just now, it says "The program records could not be updated, so nothing was saved. Try again in a while." and nothing is saved here either, so the form and the records cannot disagree. If this site cannot keep the details that live on it, it says "The details could not be kept on the site, so nothing was saved. Try again in a while." A form sent with nothing changed says "Nothing was changed." and sends no email, and so does the same change sent again from another tab, or by a colleague, a moment after the first was saved. If a save of your institution's details is being made at that very moment, the page says "Someone else is saving your institution's details at this moment, so nothing was done. Try again in a moment." and what you typed stays in the form. Since 1.123.59 a website or a page address with a space in it, or whose host has no dot, is refused the same way, under "Enter a web address such as example.com.".
 
 ### Asking to correct the name and address
 
-Open **Ask to correct these** (an Administrator checks first) under the second list, change what is wrong and press **Send to an Administrator**. Nothing is written yet. The page says "Sent: an Administrator checks the change, and you will see it here once it is decided." and the Administrators are emailed that a request is waiting. A refused value stays in the form with its reason, and the page says "Nothing was sent. Please correct what is marked below and send it again." A form with nothing changed says "Nothing was changed." If your institution's details are being saved, or a colleague's request sent, at that very moment, the page says "Someone else is saving your institution's details at this moment, so nothing was done. Try again in a moment.", nothing is sent, and what you typed stays in the form.
+Open **Ask to correct these** (an Administrator checks first) under the second list, change what is wrong and press **Send to an Administrator**. Nothing is written yet. The page says "Sent: an Administrator checks the change, and you will see it here once it is decided." and the Administrators are emailed that a request is waiting. A refused value stays in the form with its reason, and the page says "Nothing was sent. Correct what is marked below and send it again." A form with nothing changed says "Nothing was changed." If your institution's details are being saved, or a colleague's request sent, at that very moment, the page says "Someone else is saving your institution's details at this moment, so nothing was done. Try again in a moment.", nothing is sent, and what you typed stays in the form.
 
 An Administrator approves the request or declines it, as a whole and never a detail at a time. On approval the program's records are written with what you asked for, and the account that asked is emailed what changed. On a decline the Administrator writes a note for you, and the account that asked is emailed the note.
```

Then `php bin/build-docs.php && php bin/build-docs.php && git status --short docs/` and step 4.

- [ ] **Step 6: The checks every task ends with**

Expected as the block above says; `grep -rn "Please" includes/ --include='*.php' | grep -v includes/mail/templates/ | grep -v "class-wpcpm-mentor-checker-slack.php"` finds no string.

- [ ] **Step 7: Commit**

```bash
git add includes/ bin/test-mentor-application-form.php bin/test-sponsor-application.php bin/test-institution-application.php bin/test-institution-details.php bin/test-institution-details-card.php bin/test-handbook.php bin/test-admin-menu.php docs/
git commit -m "1.123.59: no sentence of the plugin's own starts with Please" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 4: The version, the changelog and the translation template

- [ ] **Step 1: The version and the changelog**

The plugin header's `Version:` and `define( 'WPCPM_VERSION', ... )` in `wpcredits-program-manager.php`, and `Stable tag:` in `readme.txt`, from `1.123.58` to `1.123.59`; and, above `= 1.123.58 =` in `readme.txt`'s changelog:

```text
= 1.123.59 =

Routing, web addresses and plain sentences.

* A student or an institution member who can write posts lands on their own page after signing in, the Student Report Card or the Institution Dashboard, as mentors and sponsors already did; only an editor or an administrator stays in wp-admin.
* A website that is not a web address is refused at its field with the typing kept, on the sponsor and institution application forms, the Sponsor Dashboard's profile, the Sponsors screen's Add and Edit forms and the Student Report Card: one with a space in it, or whose host has no dot. The field says "Enter a web address such as example.com." Before, "not a site" was saved as https://not%20a%20site.
* No sentence of the plugin's own starts with "Please": the thank-you pages of the three application forms, the Institution Dashboard's details card, the mentor calls' refusals and the forms' one-line refusals say the same things in the plain voice. The emails edited in Tools > Emails are untouched.
```

- [ ] **Step 2: The template and the greps**

`bash bin/make-pot.sh`, then:

```bash
grep -n "Version:\|define( 'WPCPM_VERSION'" wpcredits-program-manager.php
grep -n "^Stable tag:\|^= 1.123.5[89] =" readme.txt
grep -n "Project-Id-Version" languages/wpcredits-program-manager.pot
grep -c "Enter a web address such as example.com." languages/wpcredits-program-manager.pot
```

Expected: `1.123.59` in each of the first three, `= 1.123.59 =` above `= 1.123.58 =`, and the count `2` (the sentence on its own, and the profile's two-sentence string that starts with the same words). Run the checks every task ends with.

- [ ] **Step 3: Commit**

```bash
git add wpcredits-program-manager.php readme.txt languages/wpcredits-program-manager.pot
git commit -m "1.123.59: version 1.123.59 and its changelog" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 5: Try the three fixes on the Docker copy

The copy at localhost:8900 runs over the live site's database, its mail kept in its own log and never sent, its cron off. Snapshot the copy first (`~/Projects/wpcredits-docker/bin/snapshot`) and install the branch's build as the release script's `--site local` deploy does (`python3 release.py build plugin`, then `python3 release.py deploy plugin --site local --version 1.123.59`, from `~/Projects/wpcredits-release`). Sign in through the kit's cookies (`a11y/make-cookies.sh`; the copy's clock runs about eight hours behind the host). Every address typed is example.test's or the owner's one test address; every account is the copy's own test account (the test student, the test mentor, the owner's), never a real participant's. Captures at 1280px, and at 375px where it says so, go in the session's scratchpad and are backed up under `.superpowers/session-backups/<the day's date>/plugin-1-123-59/` with a `trial-notes.md`; a real name read from the copy's data never goes into the notes. Restore the snapshot at the end (`bin/restore`), and read `bin/check` after.

- [ ] Give the test student the contributor role on the copy (`bin/wp user add-role <its login> contributor`), sign out and in as it at /wp-login.php: it lands on the Student Report Card, not in wp-admin (capture); /wp-admin/index.php as it goes to the Student Report Card too. Remove the role after (`bin/wp user remove-role <its login> contributor`).
- [ ] The same with an account that is a member of a test institution (`bin/wp wpcredits ...` or the Institution Dashboard's People card to add the test mentor account to a test institution, if none is): with the contributor role it lands on the Institution Dashboard (capture).
- [ ] The owner's account, an administrator, still lands in wp-admin.
- [ ] Sponsor Dashboard, the profile card, as the copy's test sponsor persona: website `not a site`, a changed contact person: refused inside the card, the website box marked with "Enter a web address such as example.com. Nothing on the profile was saved.", the contact person kept as typed (capture at 1280px and 375px); then `example`: the same; then `example.org:8443/x`: saved.
- [ ] Sponsors screen, Add: a name and the website `example`: refused at the field with the sentence, the name kept (capture); Edit of a test sponsor with the website `not a site`: the same.
- [ ] /sponsor-application/ with the website `not a site` and every other answer filled in: sent back with the summary linking the website, the sentence at the field, every answer back in its box (capture); then a dotted website: the thank-you page, whose sentences hold no "Please" (capture).
- [ ] /institution-application/ the same way, the page about the program `our page` too: both refused at their fields; then both dotted: the thank-you page without "Please" (capture).
- [ ] /become-a-mentor/ with the switch on for the trial: a submission's thank-you page reads "Quote it if you write to us about this application." and "Follow it: the program writes to you ..." (capture). Switch it back as it was.
- [ ] Student Report Card as the test student: the personal website box with `my site`: refused at the field with the sentence, every other answer kept (capture); with `mysite.example`: saved.
- [ ] Institution Dashboard, the details card: a refused correction says "Nothing was saved. Correct what is marked below and save again." (capture).
- [ ] The copy's mail log: only the trial's own acknowledgements, none to a real address.
- [ ] Nothing in the copy's PHP log from the trial.

---

### Task 6: Release, only on the owner's go

The release script's own order: check, build, scan, the mirror's dry run and, on the owner's yes, the mirror; the deploy's dry run and, on the owner's yes, the deploy; then the vault and the memory notes. Every command runs from `~/Projects/wpcredits-release` unless it says otherwise. The mirror, the deploy and anything outward are the controller's, after the owner's yes, through the release script; nothing in this plan pushes or deploys.

- [ ] **Step 1: Read the live version** (`ssh wpcredits-dashboard 'wp plugin get wpcredits-program-manager --field=version'`). Expected `1.123.58`; anything else, say what it reads and wait for the owner.
- [ ] **Step 2: Main holds the branch, on the owner's yes** (`git merge --ff-only`, or a rebase on main and the checks again if main moved).
- [ ] **Step 3: Check, build and scan** (`python3 release.py check plugin`, `build plugin`, `scan plugin`), each ending `PASSED`; read check's watch report of others' changes to the public repository before the mirror.
- [ ] **Step 4: The mirror's message and its dry run**: one plain paragraph in `messages/1.123.59.txt`, no names and no attribution, then `python3 release.py mirror plugin --message messages/1.123.59.txt` (exit 3, `NOT RUN`). Give the owner the version, the zip's md5, the checks' results, Task 5's captures and what the copy's mail log held, the watch report and the message; wait for an explicit yes.
- [ ] **Step 5: Mirror, then deploy, each on the owner's yes** (`mirror plugin --yes --message ...`; `deploy plugin`, its dry run, then `deploy plugin --yes`, which republishes the guides and purges the caches itself).
- [ ] **Step 6: The records**: a note on WordPress/WPCredits#295 that the fix is live (the reply of 6 November promised one), on the owner's yes; the vault's Now page and the plugin's memory note gain the release's paragraph; `~/GitHub/wpcredits-program-manager.zip` is the release's build.

---

## What this plan leaves out

- **The mail templates' sentences starting with "Please"** (`includes/mail/templates/`: the three applications' acknowledgements and references, the invitations' codes line, the sponsors' interest line): the mentor coordinator's words, hers to change; the owner asks her once, and a yes is a release of its own.
- **The Mentor Status Checker's Slack line** ("Please add them to #mentors:"): a message to the team in a channel, not a page; it changes, if at all, with the team.
- **The sponsor event's call link sentence** ("The call link has to be a web address that starts with https:// or http://.") and the offers' link refusal: each has its own rule and its own words, and neither stores a value with a space; untouched.
- **`type="text"` with `inputmode="url"` on the website boxes**: unchanged on purpose where it already was, as each form's comment explains: `type="url"` would make the browser refuse a scheme-less address before the site can add the scheme. The Sponsor Dashboard's profile box was the one `type="url"` left, found by the whole-branch review and changed in the fix wave (After the plan).

## After the plan

- **The planner's calls, for the owner to read with the morning report:** (1) `clean()` refuses a typed non-address instead of emptying it, so the Student Report Card's and the feedback forms' `url` boxes refuse too, which the owner's yes did not name; the alternative, an emptied box with no word, is what 1.123.58 set out to end. (2) One sentence for every website refusal, which rewords the 1.123.58 sentence of the institution forms as well. (3) Task 3 takes every page sentence starting with "Please", not only the mentor application's two; each is a string, reversible by a word.
- **Known and not pinned here:** a student whose stored website the rule refuses saves everything else on the Student Report Card's form ("The rest was saved. The answers marked below need a change."), with the website box marked and the sentence saying what to do; nothing is lost. A sponsor whose stored website holds a space keeps it until somebody opens the profile; the readers of stored links print no link for it meanwhile.
- **Found while planning, not changed here:** two suites print `Undefined global variable $uid` warnings on PHP 8.5 and pass (`bin/test-sponsor-cards.php` line 184, `bin/test-sponsors-screen.php` line 597): a stub's `global $uid` before the first assignment, for a release of its own; `bin/check-temp-litter.php` names `bin/check-dead-annotations.php exited 1` when run without the kit's wrapper on `PATH`, which is phpcs's memory, as the Global Constraints say.
- **As built, 6 and 7 November 2026 (branch `release/1.123.59-small-fixes`, 69e3425f..72558aef, every task reviewed at its gate and the whole branch reviewed):**
  - Task 2's review added to the shared rule what the plan's diff did not have (3ce7ef9d): a userinfo part (`user@host`) is refused by `clean_url()` itself, as the profile already refused it; any Unicode separator counts as a space (`/[\s\p{Z}]/u`); the docblock says what the rule refuses.
  - The whole-branch review found three things the plan got wrong, fixed in one wave (72558aef): the Sponsor Dashboard's profile website box was `type="url"`, so the browser refused "example.com" before the site could say the new sentence (it is `type="text"` with `inputmode="url"` now, like the other four); the Student Report Card's read-only row linked a refused stored website to the page itself (it draws the text with no link now); Task 1's guide paragraph for the student landing page was anchored under the Sponsor applications heading by this plan's diff (it sits after the Student landing page bullet now, and that bullet's "anyone who can write posts is left alone" reads "anyone who can edit others' posts, an editor or an administrator"). The wave also reworded two routing docblocks that still described the old rule, named the userinfo refusal in the changelog and two guides, made `preg_match()`'s `false` on invalid UTF-8 a refusal, strips a leading or trailing Unicode space before the rule, and gave the institutions suite's stub an `edit_others` list with one check.
  - Left for a release of its own: `WPCPM_Semester_Report` has a private `clean_url()` of its own that still keeps a stored "not a site" as an address; the mentor application form's `CALL_URL` constant still holds the handbook address that answers 404 (WordPress/WPCredits#296; the live page's text was corrected by hand); a url box holding only a no-break space is refused rather than read as empty.
  - The trial (Task 5) ran on the Docker copy with every line but one: the Student Report Card's website box could not be tried because the copy holds no cached record for its test students; the suite pins it. Notes and captures: `.superpowers/session-backups/2026-11-07/plugin-1-123-59/` in the main checkout.
