# Scan release 1: the six changes, plugin 1.123.58 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Nobody loses typed work to a refusal, no action runs twice, a page opened earlier can never undo a change made since, a collapsed Airtable read can no longer empty the program, no page reloads under the person, and every outcome is printed and heard where the person is. Release **1.123.58**, named "Scan release 1" in the changelog; it answers the six changes the UI and logic scan of 6 November 2026 put first ("The ten changes that pay back most", rows 1 to 6).

**Architecture:** Five shared mechanisms, built once and applied everywhere. (1) **Kept values**: a refused signed-in form keeps what was posted, and why each field was refused, through `WPCPM_Flash::keep()` and `kept()` (taken once, ten minutes at most); a public form keeps through `WPCPM_Form_Stash` as today; a form of one box keeps through `WPCPM_Typed_Text::keep_box()` as today. A refused form is drawn again from the kept values, never from the stored record. (2) **One error renderer**: `WPCPM_Form_Errors` prints the summary (`role="alert"`, `tabindex="-1"`, "N answers need a change", one link per problem) and the field marking (`aria-invalid`, `aria-describedby`, the problem paragraph), and `assets/js/forms.js` moves the focus to the summary, or else to a card's outcome, on load. Handlers return `array( field => sentence )`. (3) **The drawn-value save**: every settings form carries a fingerprint of each value it was drawn with; a save writes only what the person changed, keeps what changed elsewhere since and names it, and refuses a value its rule would have blanked. (4) **The card-targeted outcome**: a dashboard handler names the card it goes back to, and the card prints the outcome at the top of its body, open, toned, with the role its tone is heard with; a page that does not draw that card prints it at the top. (5) **The invitations poller**: the card's 30-second reload is replaced by a poll of `WPCPM_Mail::invite_progress()` every ten seconds through `wp_ajax_wpcpm_invite_progress`. A sixth, `WPCPM_Sync_Guard`, is the count check the two syncs make before they take a role.

**Tech Stack:** PHP 7.4+ WordPress plugin `wpcredits-program-manager` (prefix `WPCPM_`); the no-WordPress suites `bin/test-*.php` (run from the plugin root with `php bin/test-x.php`; each ends `ALL PASS` and exits 0), with the stand-ins in `bin/stubs/`; the node harnesses under `bin/js/` (run by their PHP suites; a machine without node gets one `skip` line); `bin/check-standards.sh`; the Docker copy in `~/Projects/wpcredits-docker`; the release script `~/Projects/wpcredits-release/release.py`.

**Spec:** the scan, kept outside the public tree at `.superpowers/session-backups/2026-11-06/ui-logic-scan/` in the main checkout: `summary.md` (the themes and the ten changes), `rubric.md` (the standards and the decisions never to re-propose) and `findings/` (each finding by its id, with the file and line it cites; a line number there may be off by a handful, since main moved while the scan ran). Read the findings a task names before the task. The scan names no person, and neither does anything this plan adds.

## Global Constraints

- Release **1.123.58**: the plugin header, `WPCPM_VERSION`, `readme.txt` Stable tag, a `= 1.123.58 =` changelog entry named "Scan release 1" and `languages/wpcredits-program-manager.pot` (`bash bin/make-pot.sh`). Branch `release/1.123.58-scan-release-1` from `main` at 020794b8 or later (1.123.57 merged and not yet deployed; the live site runs 1.123.56). If 1.123.57 has not been deployed when this release is ready, say so and take the owner's word on whether the two ship together, before Task 16. If main's version has moved past 1.123.57, take the next free number wherever this plan says 1.123.58.
- PHP 7.4 compatible: `array()` syntax, no `match`, no named arguments, no typed properties, no `?->`, no trailing comma in a parameter list or a closure's `use` list, no reliance on `usort()` keeping equal rows in place.
- WordPress coding standards: tabs, Yoda conditions, spaces inside parentheses, strict `in_array()`, no short ternary. `bash bin/check-standards.sh` ends `106 warnings, no errors.` with no ` ERROR ` line: 106 is main's own count at 020794b8, and no task adds a warning. phpcs runs out of memory at PHP's default 128M on this tree, so run the standards and the dead-annotations check through the release kit's wrapper, which every session already uses and which changes nothing in the tree: `PATH="$HOME/Projects/wpcredits-release/local-bin:$PATH" bash bin/check-standards.sh` and `PATH="$HOME/Projects/wpcredits-release/local-bin:$PATH" php bin/check-dead-annotations.php`.
- Every handler checks the login and the capability first, then the nonce keyed to the action and the row, then the state; every posted value goes through `wp_unslash()` and a sanitizer; every printed value is escaped at output; every string with a placeholder has a translators' comment.
- No em dash and no en dash anywhere: code, comments, strings, docs, commit messages. Plain hyphens.
- US English in every string a person reads (`php bin/check-spelling.php`); full product names ("Student Report Card", "Mentor Report Card", "Administrator Dashboard", "Institution Dashboard", "Sponsor Dashboard", "Mentor Status Checker", "Student Duplicate Finder"); "Administrators" for the people who run the program, in every string, and "the mentor coordinator" where a role is needed.
- **Copy rules for every new sentence:** plain, US English, sentence case, no sentence starting with "Please", no exclamation mark, numbers under ten spelled out in prose (a count filled in at run time prints as figures, as every count sentence of the plugin does, except the error summary's heading, which `WPCPM_Form_Errors::count_words()` spells out), the plugin's existing voice (read the neighboring messages before writing one), `_n()` for every count, a translators' comment for every placeholder. Every sentence a person reads is written in this plan; an implementer who needs one the plan does not give stops and asks.
- **The store each form family keeps in:** a public form (the four of Task 4) keeps through `WPCPM_Form_Stash`, its sender having no account; every dashboard and wp-admin form keeps its values and its problems through `WPCPM_Flash::keep()` and draws from `WPCPM_Flash::kept()`, the Sponsor Dashboard's profile, offer and planner forms included, which move off `WPCPM_Typed_Text::keep()` in Task 7 (each box's room is the box's `maxlength`, which the form's own validation already refuses past, so the second store's room rule protects nothing `KEPT_MAX` does not); `WPCPM_Typed_Text::keep_box()` stays only where a single box already uses it (the question to an applicant, the reasons and return notes).
- **The kept-values rule:** a refused form is drawn again from the kept values, never from the stored record; a file input says `WPCPM_Form_Errors::file_again()`; a handler that keeps calls `WPCPM_Flash::forget( $form )` first; a value over `WPCPM_Flash::KEPT_MAX` characters is not kept (a paste box past it says `The pasted list was too long to keep here: paste it again.` at the field), never kept cut.
- **The one-field-one-reason rule:** a handler returns `array( field => sentence )`, one sentence per refused field, never one sentence listing every rule. A form-wide refusal is the problem keyed `''`.
- `bin/` and `docs/` are public (mirrored to WordPress/WPCredits): example.test addresses, invented names, spelled-out record ids (`recSPONSOR0000001`), synthetic WordPress.org usernames (`ada-example`), no real person anywhere; `php bin/test-fixtures.php` walks both, `docs/plans` included. No key shape in any added line: a Slack webhook's host and path never on one line, no token shapes (a personal access token's prefix or a Slack token's prefix followed by a real-looking value).
- The Administrator guide convention: a change to what an Administrator sees is documented in the same release in `docs/sections/30-admin-wpadmin.md`, inside the part for that screen; a new option, cron or CLI command in `docs/sections/34-admin-operations.md`, with a row in its option table, A to Z; each new paragraph opens "Since 1.123.58 ..."; then `php bin/build-docs.php` twice (the second run changes nothing), `php bin/test-handbook.php`, `php bin/check-references.php`, `php bin/check-spelling.php` and the dash grep (Task 14).
- One commit per task, its message starting `Scan release 1:` and ending, after a blank line, with a `Co-Authored-By:` trailer naming the implementer's own model.
- Nothing is deployed, mirrored or posted without the owner's explicit yes for that step; the live version is read first; every test email on the live site goes to the owner's one test address only.

## Review Focus

Five inputs the six changes imply that no single finding names, each the kind most likely to bite a person, each pinned with a test in the task that owns the code.

1. **A refusal while the stash has expired.** A public form bounced with a stash id, and the person comes back to the tab more than 30 minutes later, or reloads it then: the transient is gone, and the page used to draw a blank form with no word, which reads as the site having eaten the answers. `WPCPM_Form_Stash::read()` now answers `stash-gone` for an id whose transient is gone, and each of the four forms draws itself empty under "The answers kept for this form have expired, so the form is empty again. Nothing was sent: fill it in and send it again." On a signed-in page the same case is a kept entry older than `WPCPM_Flash::KEPT_FOR`, which hands back nothing and is cleared. Pinned in Task 1, step 1 ("an entry older than KEPT_FOR hands back nothing", "and is cleared all the same") and Task 4, step 1 ("a stash id whose answers have expired draws the form empty, with the sentence that says so, and marks no field").
2. **Two Administrators and one stale tab.** One saves a Settings tab, a sponsor, a track, an email or the header notices; the other's tab was drawn before and is saved after. The second save writes only what its person changed, keeps the first person's change and names it; where both changed the same thing, the stored value wins and the second person is told, with their typing kept. Pinned in Task 2, step 1 ("a switch changed elsewhere since the page was drawn is not switched back by a Save of that page", "a setting the person changed and that changed elsewhere since too keeps the stored value, and is named", "a token rotated elsewhere since is kept by a Save that posts its mask, with no warning"), Task 8, step 1 ("a stale Edit form is refused with the typing kept and the sentence naming who changed the sponsor and when"), Task 10, step 1 (the track and the email) and Task 11, step 1 (the notices).
3. **A sync whose read is exactly half.** 70 mentors read against 140 accounts linked goes on; 69 stops; 50 of 101 stops and 51 goes on; a read of nothing stops; nothing linked stops nothing. Pinned in Task 12, step 1 (`bin/test-sync-guard.php`, "a read of exactly half goes on", "one under half stops", "and half of an odd count is held to the same rule") and in the mentors sync's own suite ("the revoke step of a run that read exactly half of the linked mentors takes the role of the ones it did not read").
4. **A flash with a card id the page does not hold.** A handler names a card that a sponsor's page does not draw (a retired offer's card, a module the page hides, a tile's card on a page without the module); the outcome must still be said, at the top, with its role and its focus. Pinned in Task 3, step 1 (`bin/test-card-message.php`, "a flash naming a card the page does not draw is said at the top, where the slot was, and no card is opened for it").
5. **A poller on a tab left open overnight.** The invitations card's poller on a tab nobody looks at: it must ask nothing while the tab is hidden, and when the nonce has expired by morning it must stop with a sentence rather than loop, reload or speak. Pinned in Task 3, step 1 (`bin/js/admin-invites.js`, "a refused answer, as an expired nonce on a tab left open overnight gives, stops the panel with its sentence and asks nothing more", "while the tab is hidden nothing is asked; the poll that came due is asked once the tab is shown", "a request that fails is asked again after twice the wait each time, up to a minute, and five in a row stop the panel").

## Files at a glance

| File | What it is responsible for | Task |
| --- | --- | --- |
| `includes/class-wpcpm-flash.php` | `keep()`, `kept()`, `forget()`: a refused signed-in form's values and problems, taken once | 1 |
| `includes/class-wpcpm-form-errors.php` (new) | The error summary, the field marking, the problem paragraph, `file_again()` | 1 |
| `assets/js/forms.js` | `focusOutcome()`: the focus to the summary, or else to a card's outcome, on load | 1 |
| `assets/css/application.css`, `dashboard.css`, `institution.css`, `sponsor.css`, `admin.css` | The summary's and the problem's styles | 1 |
| `includes/class-wpcpm-settings.php`, `class-wpcpm-settings-screen.php`, `class-wpcpm-settings-rows.php` | The drawn-value save, the refusals, the marked rows and the warning | 2 |
| `includes/class-wpcpm-cli.php` | `wp wpcredits setting get|set`, `sync_mentors --accept|--restart` | 2, 12, 13 |
| `includes/class-wpcpm-dashboard-card.php` | `target()`, `hold()`, `message()`, `settle()`: the outcome inside its card | 3 |
| `includes/class-wpcpm-mail.php`, `assets/js/admin.js` | `invite_progress()`, the AJAX answer, the poller; the queue lock | 3, 13 |
| `includes/modules/class-wpcpm-sync-guard.php` (new) | The count check before a revoke | 12 |
| The four public forms, `class-wpcpm-form-guard.php`, `class-wpcpm-form-stash.php`, `class-wpcpm-ceiling.php` | Summary, `busy` with the stash, consent in the same pass, the used token | 4 |
| The Student and Mentor Report Cards' modules, `assets/js/modules.js` | Kept answers, marked fields, the note guard, the hours box, the move refusal | 5 |
| The Institution Dashboard's import, correction, people and panel modules | Kept enrollment, the file size, marked fields, the busy forms, toned outcomes | 6 |
| The Sponsor Dashboard's profile, offers, planner, mentors and tools cards | Kept typing, marked fields, one sentence per move, the interest once | 7 |
| `class-wpcpm-institutions.php`, `class-wpcpm-sponsors.php` (wp-admin) | The question kept, the reject's acknowledgement, the link form, the sponsor form | 8 |
| `class-wpcpm-mentor-application-queue.php`, `trait-wpcpm-accounts-screen.php` | The decisions under the lock, the applicant named, the refused note at its box | 9 |
| The Track Builder, `class-wpcpm-emails.php`, `class-wpcpm-mail-templates.php`, `assets/js/track-editor.js` | Every error at its field, the version stamp, the busy publish, the move refusal | 10 |
| `class-wpcpm-header-notices.php`, `class-wpcpm-notices.php`, the Mentor Status Checker and its script | A Save per audience, the drawn check; Promote's focus and announcements | 11 |
| The three syncs, `class-wpcpm-mentor-provision.php`, `class-wpcpm-airtable.php`, `class-wpcpm-sync-module.php` | The guard, Needs confirming, the retry, the created ids, the page cap | 12 |
| The three syncs, `class-wpcpm-sync-module.php`, `assets/js/admin.js` | One run at a time, the atomic tick lock, the queue lock, the sync speaks | 13 |
| `docs/sections/30-admin-wpadmin.md`, `34-admin-operations.md` | The Administrator guide | 14 |

New suites: `bin/test-form-errors.php`, `bin/test-card-message.php`, `bin/test-admin-js.php` (with `bin/js/admin-invites.js`), `bin/test-sync-guard.php`. Loader and uninstaller (`wpcredits-program-manager.php`, `uninstall.php`) gain two `require_once` lines: `class-wpcpm-form-errors.php` after `class-wpcpm-typed-text.php`, and `modules/class-wpcpm-sync-guard.php` after `modules/class-wpcpm-sync-module.php`.

## The checks every task ends with

Run from the plugin root, after the task's own suites pass:

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done; echo battery done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
PATH="$HOME/Projects/wpcredits-release/local-bin:$PATH" php bin/check-dead-annotations.php | tail -1
php bin/check-temp-litter.php | tail -2
PATH="$HOME/Projects/wpcredits-release/local-bin:$PATH" bash bin/check-standards.sh 2>&1 | tail -1
php bin/test-fixtures.php | tail -1
```

and `php bin/build-docs.php` when a task changes `docs/`. Expected: `battery done` and no `FAIL` line; the references check ending `- all resolve`; the spelling check ending `US English throughout.`; `0 dead`; the temp-litter check saying every script exited 0 and the temp directory is as it was found; `106 warnings, no errors.`; and `ALL PASS`. The counts of classes, references, files, annotations and scripts grow task by task, so a task's expected lines name only what must hold. The temp directory is the machine's, so the temp-litter check also names what another program made while it ran; as its own header says, run it again: the battery's leftovers come back under the same names, a stranger's do not.

The proved runs quoted below were made on the scratch branch `plan-1-123-58` from main at 020794b8, holding exactly the code of Tasks 1, 2, 3 and the guard of Task 12 as written here, on PHP 8.5 with node 26. Only the lines that matter are kept.

---

## Before Task 1: the branch and the baseline

- [ ] **Step 1: Branch from main** (a worktree if another session is working in the checkout; see superpowers:using-git-worktrees; never enter another session's worktree under `.worktrees/`)

```bash
cd ~/GitHub/wpcredits-program-manager
git worktree add .worktrees/1-123-58 -b release/1.123.58-scan-release-1 main
cd .worktrees/1-123-58
```

- [ ] **Step 2: Start from a main that passes**

The checks every task ends with, on main at 020794b8 as proved:

```text
battery done
197 classes, 21167 references checked, 108 form handlers scanned - all resolve
200 PHP files, 10 blocks, 4 track seeds, 30 fixture and tooling files, the guides and the readme: US English throughout.
362 annotations, 0 dead.
Ran 170 scripts from bin/, each in a PHP process of its own, and every one exited 0.
The battery leaves the temp directory as it found it.
106 warnings, no errors.
ALL PASS
```

Without the wrapper on `PATH`, the standards check reads `phpcs could not be run: it exited 255.` and the dead-annotations check `phpcs produced no report.`: that is phpcs at PHP's default memory, not the tree.

- [ ] **Step 3: Check that this plan names nobody**

```bash
grep -n -e $'\xe2\x80\x94' -e $'\xe2\x80\x93' docs/plans/2026-11-06-plugin-1-123-58-scan-release-1.md || echo clean
php -r 'require "bin/refused-strings.php"; var_dump( wpcpm_refused_found( file_get_contents( "docs/plans/2026-11-06-plugin-1-123-58-scan-release-1.md" ) ) );'
php bin/test-fixtures.php | tail -1
```

Expected: `clean`, `array(0) {}` and `ALL PASS`.

---

### Task 1: Kept values, and the error summary with its focus (shared mechanism)

The two pieces every refused form after this one uses: the per-user store a signed-in form keeps its typing and its problems in, and the one renderer of the summary and the field marking, with the few lines of `forms.js` that move the focus. Scope **S**. Nothing here changes a form yet; Tasks 2 and 4 to 11 apply it.

**Files:**
- Modify: `includes/class-wpcpm-flash.php` (three constants and three methods after `sweep()`)
- Create: `includes/class-wpcpm-form-errors.php`
- Modify: `wpcredits-program-manager.php`, `uninstall.php` (a `require_once` after `class-wpcpm-typed-text.php`)
- Modify: `assets/js/forms.js` (`focusOutcome()`, called first in `ready()`; one line of the header's list)
- Modify: `assets/css/application.css`, `assets/css/dashboard.css`, `assets/css/institution.css`, `assets/css/sponsor.css`, `assets/css/admin.css` (appended rules)
- Test: `bin/test-flash.php` (a section before the URL checks), `bin/test-form-errors.php` (new), `bin/js/forms-harness/run.js` (`focus()`, `.class` selectors, `activeElement` reset), `bin/js/forms-harness/scenarios.js` (three scenarios), `bin/test-submit-guard.php` (the `ready()` order)

**Interfaces:**
- Consumes: `WPCPM_Flash::set()`, `take()`, `sweep()`; core's `__()`, `_n()`, `esc_html()`, `esc_attr()`, `number_format_i18n()`.
- Produces:
  - `WPCPM_Flash::KEPT_CHANNEL = 'kept:'`, `KEPT_FOR = 600`, `KEPT_MAX = 60000`;
  - `WPCPM_Flash::keep( string $form, array $values, array $problems = array() ): void`;
  - `WPCPM_Flash::kept( string $form ): array{values: array, problems: array<string,string>}` (both keys always present; taken once);
  - `WPCPM_Flash::forget( string $form ): void`;
  - `final class WPCPM_Form_Errors`: `SUFFIX = '-problem'`; `problem_id( $field_id ): string`; `count_words( int $count ): string`; `file_again(): string`; `heading( int $count ): string`; `summary( array $problems, array $fields, array $args = array() ): string` (`$fields` is field => `array( 'id' => control id, 'label' => text )`; `$args` `level` 2 to 4, `class`, `heading`); `attrs( $field_id, $sentence, $hint_id = '' ): string`; `problem( $field_id, $sentence ): string`;
  - `forms.js` focuses the first `.wpcpm-errors`, or else the first `[data-wpcpm-card-message]`, when the page loads.
- Form names for `keep()`, used by later tasks: what the form does, a colon, the record it edits, such as `sponsor-edit:42`, `import-batch:recINSTITUTION001`, `report-form:recSTUDENT0000001`.

- [ ] **Step 1: Write the failing checks**

The section `bin/test-flash.php` gains, before "No status is left in the redirect URLs any more.":

```diff
diff --git a/bin/test-flash.php b/bin/test-flash.php
index dda28ab3..e7e6dfd0 100644
--- a/bin/test-flash.php
+++ b/bin/test-flash.php
@@ -189,6 +189,59 @@ WPCPM_Flash::sweep(
 );
 ck( 'a guest has nothing to sweep, and nothing is written', isset( $GLOBALS['umeta'][0] ), false );
 
+// A refused form's values and problems wait once for its redraw (1.123.58). Each scenario keeps
+// under a form name of its own, since `take()` memoizes per request.
+$GLOBALS['uid'] = 7;
+WPCPM_Flash::keep(
+	'sponsor-edit:42',
+	array(
+		'name'    => 'Ada\'s "Studio" \\ C:\drafts',
+		'website' => 'htp://example.test',
+		'notes'   => "Line one\r\nLine two",
+		'levels'  => array( 'gold', 7, 'silver' ),
+	),
+	array( 'website' => 'Enter a web address that starts with https://.' )
+);
+$kept = WPCPM_Flash::kept( 'sponsor-edit:42' );
+ck( 'keep() and kept(): the typed values come back as typed, a CRLF read as one LF, a list kept as its strings', $kept['values'], array( 'name' => 'Ada\'s "Studio" \\ C:\drafts', 'website' => 'htp://example.test', 'notes' => "Line one\nLine two", 'levels' => array( 'gold', 'silver' ) ) );
+ck( 'and the problem comes back beside them, keyed by its field', $kept['problems'], array( 'website' => 'Enter a web address that starts with https://.' ) );
+ck( 'kept() asked twice in one request gives the same answer', WPCPM_Flash::kept( 'sponsor-edit:42' ), $kept );
+ck( 'and the entry is gone from storage', isset( $GLOBALS['umeta'][7]['wpcpm_flash'] ), false );
+ck( 'a form never kept hands back both keys, empty', WPCPM_Flash::kept( 'sponsor-edit:never' ), array( 'values' => array(), 'problems' => array() ) );
+
+WPCPM_Flash::keep( 'kept-empty', array( 'bad' => "\xC3\x28", 'flag' => true, 'obj' => new stdClass() ), array( 'x' => '' ) );
+ck( 'nothing worth keeping (invalid UTF-8, a boolean, an object, a blank sentence) stores nothing', isset( $GLOBALS['umeta'][7]['wpcpm_flash'] ), false );
+
+WPCPM_Flash::keep( 'kept-problems-only', array(), array( 'consent' => 'Tick the box to go on.' ) );
+ck( 'problems alone are kept, for a form whose typing waits in another store', WPCPM_Flash::kept( 'kept-problems-only' ), array( 'values' => array(), 'problems' => array( 'consent' => 'Tick the box to go on.' ) ) );
+
+WPCPM_Flash::keep( 'kept-long', array( 'list' => str_repeat( 'a', WPCPM_Flash::KEPT_MAX + 50 ) ) );
+$long = WPCPM_Flash::kept( 'kept-long' );
+ck( 'a value is cut at KEPT_MAX characters', mb_strlen( $long['values']['list'] ), WPCPM_Flash::KEPT_MAX );
+
+$GLOBALS['umeta'][7]['wpcpm_flash'] = array( 'kept:kept-stale' => array( 'at' => time() - WPCPM_Flash::KEPT_FOR - 1, 'values' => array( 'a' => 'old' ), 'problems' => array() ) );
+ck( 'an entry older than KEPT_FOR hands back nothing', WPCPM_Flash::kept( 'kept-stale' ), array( 'values' => array(), 'problems' => array() ) );
+ck( 'and is cleared all the same', isset( $GLOBALS['umeta'][7]['wpcpm_flash'] ), false );
+
+$GLOBALS['umeta'][7]['wpcpm_flash'] = array(
+	'kept:kept-left-behind' => array( 'at' => time() - WPCPM_Flash::KEPT_FOR - 1, 'values' => array( 'email' => 'ada@example.test' ), 'problems' => array() ),
+	'kept:kept-not-an-entry' => 'a string',
+	'typed:offer:1'          => array( 'at' => time() - 9999, 'typed' => array( 'title' => 'T' ) ),
+	'note'                   => 'deleted',
+);
+WPCPM_Flash::keep( 'kept-fresh', array( 'a' => 'b' ) );
+ck( 'every keep sweeps the kept entries left behind, and leaves the other stores and channels alone', array_keys( $GLOBALS['umeta'][7]['wpcpm_flash'] ), array( 'typed:offer:1', 'note', 'kept:kept-fresh' ) );
+
+WPCPM_Flash::keep( 'kept-forget', array( 'a' => 'b' ) );
+WPCPM_Flash::forget( 'kept-forget' );
+ck( 'forget() drops the entry unread', array_key_exists( 'kept:kept-forget', $GLOBALS['umeta'][7]['wpcpm_flash'] ), false );
+
+$GLOBALS['uid'] = 8;
+ck( 'another user is handed nothing of it', WPCPM_Flash::kept( 'kept-fresh' ), array( 'values' => array(), 'problems' => array() ) );
+$GLOBALS['uid'] = 7;
+ck( 'and its owner still is', WPCPM_Flash::kept( 'kept-fresh' )['values'], array( 'a' => 'b' ) );
+unset( $GLOBALS['umeta'][7]['wpcpm_flash'] );
+
 // No status is left in the redirect URLs any more. Each file is asserted to be there first:
 // a check that reads a module which no longer exists passes on an empty string and warns on
 // every run, which is what the student profile row here did until the deep check of
```

The new suite:

```php
<?php
/**
 * The error summary and the field marking every refused form prints (1.123.58).
 *
 * `WPCPM_Form_Errors` is what a refused form says: a summary at the top, focused when the page loads,
 * with one link per problem to its field, and the field marked `aria-invalid` with its sentence tied
 * by `aria-describedby`. These checks read the markup it returns, escaped, in the form's order, and
 * that a form nothing was refused on prints nothing.
 *
 * Run from the plugin root:  php bin/test-form-errors.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

function __( $text, $domain = null ) {
	return $text;
}
function _n( $single, $plural, $number, $domain = null ) {
	return 1 === (int) $number ? $single : $plural;
}
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}
function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, (int) $decimals );
}

require_once dirname( __DIR__ ) . '/includes/class-wpcpm-form-errors.php';

$fail = 0;
function ck( $l, $a, $e ) {
	global $fail; $ok = $a === $e; if ( ! $ok ) { $fail++; }
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $l . "\n";
	if ( ! $ok ) { echo '       exp: ' . var_export( $e, true ) . '  got: ' . var_export( $a, true ) . "\n"; }
}

$fields = array(
	'name'    => array( 'id' => 'wpcpm-sponsor-name', 'label' => 'Name' ),
	'website' => array( 'id' => 'wpcpm-sponsor-website', 'label' => 'Website' ),
	'email'   => array( 'id' => 'wpcpm-sponsor-email', 'label' => 'Contact email' ),
);

ck( 'nothing refused prints no summary', WPCPM_Form_Errors::summary( array(), $fields ), '' );
ck( 'and a blank sentence is no problem', WPCPM_Form_Errors::summary( array( 'name' => '  ' ), $fields ), '' );

ck(
	'one problem: the heading spelled out, one link to the field with its label, the sentence after it',
	WPCPM_Form_Errors::summary( array( 'website' => 'Enter a web address that starts with https://.' ), $fields ),
	'<div class="wpcpm-errors" role="alert" tabindex="-1"><h2 class="wpcpm-errors__title">One answer needs a change</h2><ul class="wpcpm-errors__list"><li><a href="#wpcpm-sponsor-website">Website</a>: Enter a web address that starts with https://.</li></ul></div>'
);

$two = WPCPM_Form_Errors::summary(
	array(
		'email'   => 'Enter one email address, such as name@example.org.',
		'website' => 'Enter a web address that starts with https://.',
	),
	$fields,
	array( 'level' => 3, 'class' => 'notice notice-error' )
);
ck( 'two problems are listed in the form\'s order, not the order the handler found them', strpos( $two, 'Website' ) < strpos( $two, 'Contact email' ), true );
ck( 'the heading counts them, at the level asked, and the classes follow wpcpm-errors', 0 === strpos( $two, '<div class="wpcpm-errors notice notice-error" role="alert" tabindex="-1"><h3 class="wpcpm-errors__title">Two answers need a change</h3>' ), true );

ck( 'a level outside two to four is held to the nearest', false !== strpos( WPCPM_Form_Errors::summary( array( 'name' => 'Give a name.' ), $fields, array( 'level' => 1 ) ), '<h2 ' ), true );
ck( 'the heading can be the caller\'s words', false !== strpos( WPCPM_Form_Errors::summary( array( 'name' => 'Kept.' ), $fields, array( 'heading' => 'One setting changed since you opened this page' ) ), '>One setting changed since you opened this page</h2>' ), true );

$whole = WPCPM_Form_Errors::summary( array( '' => 'Too many attempts from this connection.', 'name' => 'Give a name.' ), $fields );
ck( 'a problem with no field in the map is listed after the others, without a link', false !== strpos( $whole, '<li><a href="#wpcpm-sponsor-name">Name</a>: Give a name.</li><li>Too many attempts from this connection.</li>' ), true );

$hostile = WPCPM_Form_Errors::summary( array( 'name' => 'Not "<b>this</b>" & that' ), array( 'name' => array( 'id' => 'a"b', 'label' => '<i>Name</i>' ) ) );
ck( 'the sentence, the label and the id are escaped', false !== strpos( $hostile, '<a href="#a&quot;b">&lt;i&gt;Name&lt;/i&gt;</a>: Not &quot;&lt;b&gt;this&lt;/b&gt;&quot; &amp; that' ), true );

$many = array();
for ( $i = 1; $i <= 12; $i++ ) {
	$many[ 'f' . $i ] = 'Fix ' . $i . '.';
}
ck( 'from ten the count is in figures', false !== strpos( WPCPM_Form_Errors::summary( $many, array() ), '>12 answers need a change<' ), true );
ck( 'the count is spelled out from one to nine', array( WPCPM_Form_Errors::count_words( 1 ), WPCPM_Form_Errors::count_words( 9 ), WPCPM_Form_Errors::count_words( 10 ) ), array( 'One', 'Nine', '10' ) );

ck( 'a refused control: aria-invalid, and its problem named before its hint', WPCPM_Form_Errors::attrs( 'wpcpm-sponsor-website', 'Enter a web address.', 'wpcpm-sponsor-website-hint' ), ' aria-invalid="true" aria-describedby="wpcpm-sponsor-website-problem wpcpm-sponsor-website-hint"' );
ck( 'a control nothing was refused on: its hint alone', WPCPM_Form_Errors::attrs( 'wpcpm-sponsor-website', '', 'wpcpm-sponsor-website-hint' ), ' aria-describedby="wpcpm-sponsor-website-hint"' );
ck( 'and with no hint, nothing', WPCPM_Form_Errors::attrs( 'wpcpm-sponsor-website', '' ), '' );
ck( 'the problem paragraph carries the id attrs() names', WPCPM_Form_Errors::problem( 'wpcpm-sponsor-website', 'Enter a web address & try again.' ), '<p class="wpcpm-field__problem" id="wpcpm-sponsor-website-problem">Enter a web address &amp; try again.</p>' );
ck( 'and prints nothing for a field nothing was refused on', WPCPM_Form_Errors::problem( 'wpcpm-sponsor-website', '' ), '' );

ck( 'a file input that comes back says the file has to be chosen again, in one sentence for every form', WPCPM_Form_Errors::file_again(), 'A chosen file cannot be kept when the form comes back. Choose the file again.' );

echo $fail ? "\n$fail FAILED\n" : "\nALL PASS\n";
exit( $fail ? 1 : 0 );
```

The harness learns `focus()`, class selectors and a fresh `activeElement`:

```diff
diff --git a/bin/js/forms-harness/run.js b/bin/js/forms-harness/run.js
index faa16f06..37f788ad 100644
--- a/bin/js/forms-harness/run.js
+++ b/bin/js/forms-harness/run.js
@@ -94,6 +94,11 @@ class El {
 		delete this.attrs[ name ];
 	}
 
+	// Where the focus is, as the page's `document.activeElement` says (1.123.58).
+	focus() {
+		doc.activeElement = this;
+	}
+
 	addEventListener( type, fn, options ) {
 		const capture = true === options || !! ( options && options.capture );
 
@@ -113,10 +118,10 @@ class El {
 		return out;
 	}
 
-	// Comma lists of `tag[attr][attr="value"]`: all the script's selectors are of that shape.
+	// Comma lists of `tag.class[attr][attr="value"]`: all the script's selectors are of that shape.
 	matches( selector ) {
 		return selector.split( ',' ).map( ( s ) => s.trim() ).some( ( part ) => {
-			const m = part.match( /^([a-z]*)((?:\[[^\]]+\])*)$/i );
+			const m = part.match( /^([a-z]*)((?:\.[a-z0-9_-]+)*)((?:\[[^\]]+\])*)$/i );
 
 			if ( ! m ) {
 				return false;
@@ -126,7 +131,13 @@ class El {
 				return false;
 			}
 
-			return ( m[ 2 ].match( /\[[^\]]+\]/g ) || [] ).every( ( test ) => {
+			const classes = ( this.attrs.class || '' ).split( /\s+/ );
+
+			if ( ! ( m[ 2 ].match( /\.[a-z0-9_-]+/gi ) || [] ).every( ( dot ) => classes.includes( dot.slice( 1 ) ) ) ) {
+				return false;
+			}
+
+			return ( m[ 3 ].match( /\[[^\]]+\]/g ) || [] ).every( ( test ) => {
 				const mm = test.match( /^\[([^=\]]+)(?:="([^"]*)")?\]$/ );
 				const value = this.getAttribute( mm[ 1 ] );
 
@@ -166,6 +177,7 @@ function reset() {
 	registry.length = 0;
 	doc.listeners = [];
 	doc.readyState = 'complete';
+	doc.activeElement = null;
 }
 
 // Run forms.js in a fresh context, as a page that has just loaded it. `state.answer` is what
```

```diff
diff --git a/bin/js/forms-harness/scenarios.js b/bin/js/forms-harness/scenarios.js
index a8503c2e..e904ef09 100644
--- a/bin/js/forms-harness/scenarios.js
+++ b/bin/js/forms-harness/scenarios.js
@@ -1020,5 +1020,46 @@ scenario(
 	}
 );
 
+/* ---- A refused form's summary, and a card's outcome, take the focus on load (1.123.58). ---- */
+
+scenario(
+	'a page holding a refused form\'s error summary moves the focus to it when the script loads',
+	{ focused: 'summary' },
+	() => {
+		mk( 'p', { 'data-wpcpm-card-message': '', tabindex: '-1', id: 'message' } );
+		mk( 'div', { class: 'wpcpm-errors notice', role: 'alert', tabindex: '-1', id: 'summary' } );
+
+		load( FORMS );
+
+		return { focused: doc.activeElement ? doc.activeElement.id : null };
+	}
+);
+
+scenario(
+	'with no summary, the message a card\'s handler left takes the focus',
+	{ focused: 'message' },
+	() => {
+		mk( 'div', { class: 'wpcpm-card', id: 'card' } );
+		mk( 'p', { 'data-wpcpm-card-message': '', tabindex: '-1', id: 'message' } );
+
+		load( FORMS );
+
+		return { focused: doc.activeElement ? doc.activeElement.id : null };
+	}
+);
+
+scenario(
+	'a page with neither leaves the focus where it was',
+	{ focused: null },
+	() => {
+		mk( 'div', { class: 'wpcpm-errors-not', id: 'other' } );
+		mk( 'form', { 'data-wpcpm-once': '' } );
+
+		load( FORMS );
+
+		return { focused: doc.activeElement ? doc.activeElement.id : null };
+	}
+);
+
 console.log( '\n' + ran + ' scenarios, ' + failed + ' differ' );
 process.exit( failed ? 1 : 0 );
```

And the order check of `bin/test-submit-guard.php`:

```diff
diff --git a/bin/test-submit-guard.php b/bin/test-submit-guard.php
index 310538f5..608d2ab7 100644
--- a/bin/test-submit-guard.php
+++ b/bin/test-submit-guard.php
@@ -54,8 +54,8 @@ ck( 'forms.js exists and defines the guard, as a handler of the submit event and
         false !== strpos( $js, "querySelectorAll( 'form[data-wpcpm-once]' )" ),
     ),
     array( true, false, false, false ) );
-ck( 'and wires it, with the confirm reader in the one submit listener, after the press tracker and with the bfcache release, the code-selector, the kind switch and the required-with switch, once the DOM is ready',
-    (bool) preg_match( '/ready\( function \(\) \{\s*watchPressed\(\);\s*watchSubmits\(\);\s*releaseOnRestore\(\);\s*selectOnClick\(\);\s*showForKind\(\);\s*requireWith\(\);\s*\} \);/', $js ) );
+ck( 'and wires it, with the confirm reader in the one submit listener, after the focus on a refused form\'s summary or a card\'s outcome (1.123.58) and the press tracker, and with the bfcache release, the code-selector, the kind switch and the required-with switch, once the DOM is ready',
+    (bool) preg_match( '/ready\( function \(\) \{\s*focusOutcome\(\);\s*watchPressed\(\);\s*watchSubmits\(\);\s*releaseOnRestore\(\);\s*selectOnClick\(\);\s*showForKind\(\);\s*requireWith\(\);\s*\} \);/', $js ) );
 
 // The deep check of 1.109.1, SESSIONS-6: "Cancel the session" and "Leave the session" carried a
 // `data-wpcpm-confirm` sentence that no script read, so one press canceled a session for everybody.
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php bin/test-flash.php`, `php bin/test-form-errors.php`, `php bin/test-forms-js.php`, `php bin/test-submit-guard.php`

```text
$ php bin/test-flash.php
PHP Fatal error:  Uncaught Error: Call to undefined method WPCPM_Flash::keep()
$ php bin/test-form-errors.php
PHP Warning:  require_once(<plugin>/includes/class-wpcpm-form-errors.php): Failed to open stream: No such file or directory
$ php bin/test-forms-js.php
FAIL a page holding a refused form's error summary moves the focus to it when the script loads
FAIL with no summary, the message a card's handler left takes the focus
     52 scenarios, 2 differ
2 FAILURE(S)
$ php bin/test-submit-guard.php
FAIL and wires it, with the confirm reader in the one submit listener, after the focus on a refused form's summary or a card's outcome (1.123.58) and the press tracker, ...
1 FAILURE(S)
```

- [ ] **Step 3: The store, the renderer and the focus**

`includes/class-wpcpm-flash.php`:

```diff
diff --git a/includes/class-wpcpm-flash.php b/includes/class-wpcpm-flash.php
index 49800963..ac021718 100644
--- a/includes/class-wpcpm-flash.php
+++ b/includes/class-wpcpm-flash.php
@@ -41,6 +41,25 @@ class WPCPM_Flash {
 	/** User meta holding the pending messages, keyed by channel. */
 	const META = 'wpcpm_flash';
 
+	/**
+	 * The channel prefix a refused form's kept values wait under, one channel per form (1.123.58).
+	 * Apart from `WPCPM_Typed_Text::KEPT_CHANNEL`, so the two stores never read each other's entries.
+	 */
+	const KEPT_CHANNEL = 'kept:';
+
+	/**
+	 * How long a refused form's kept values wait for the form to be drawn again, in seconds: a refusal
+	 * lands on its page at once, so an entry older than this is from a page that never drew the form.
+	 * The same ten minutes `WPCPM_Typed_Text::KEPT_FOR` gives its boxes.
+	 */
+	const KEPT_FOR = 600;
+
+	/**
+	 * The most characters one kept value holds: above the longest box any signed-in form draws (the
+	 * enrollment list's paste box, 300 rows), so a crafted post keeps no more than a box could hold.
+	 */
+	const KEPT_MAX = 60000;
+
 	/**
 	 * Queue a message for the next page this user loads.
 	 *
@@ -152,6 +171,122 @@ class WPCPM_Flash {
 		}
 	}
 
+	/**
+	 * Keep what a person posted to a form that was refused, and why each field was refused, for the
+	 * one time the form is drawn again (1.123.58).
+	 *
+	 * For the forms of a signed-in person: the dashboards' and wp-admin's. A public form keeps its
+	 * values behind `WPCPM_Form_Stash`, since its sender has no account, and a form of one box keeps
+	 * through `WPCPM_Typed_Text::keep_box()`, which cuts to the box's own room. A refused form is drawn
+	 * again from what `kept()` hands back, never from the stored record.
+	 *
+	 * A value is a string, cut at `KEPT_MAX` characters with a line break read as one LF, or a list
+	 * of strings (the boxes of a checklist). A string that is not valid UTF-8, and anything that is
+	 * neither, is not kept. A problem is a field and one sentence. When nothing is left to keep,
+	 * nothing is stored. Every keep first clears the person's kept entries older than `KEPT_FOR`, for
+	 * whatever form, and any value on such a channel that is not a kept entry (`sweep()`), so a form
+	 * that is never drawn again does not leave what was typed in user meta for good.
+	 *
+	 * @param string $form     The form, by what it does and the record it edits, such as
+	 *                         `sponsor-edit:42`.
+	 * @param array  $values   Each field and what was posted for it, unslashed and not cleaned.
+	 * @param array  $problems Each refused field and the one sentence that says why.
+	 */
+	public static function keep( $form, array $values, array $problems = array() ) {
+		self::sweep(
+			static function ( $channel, $entry ) {
+				if ( 0 !== strpos( $channel, self::KEPT_CHANNEL ) ) {
+					return false;
+				}
+
+				return ! is_array( $entry ) || ! isset( $entry['at'] ) || time() - (int) $entry['at'] > self::KEPT_FOR;
+			}
+		);
+
+		$kept = array();
+
+		foreach ( $values as $field => $value ) {
+			if ( is_array( $value ) ) {
+				$list = array();
+
+				foreach ( $value as $item ) {
+					if ( is_string( $item ) && mb_check_encoding( $item, 'UTF-8' ) ) {
+						$list[] = mb_substr( $item, 0, self::KEPT_MAX, 'UTF-8' );
+					}
+				}
+
+				$kept[ (string) $field ] = $list;
+
+				continue;
+			}
+
+			if ( ! is_scalar( $value ) || is_bool( $value ) || ! mb_check_encoding( (string) $value, 'UTF-8' ) ) {
+				continue;
+			}
+
+			$kept[ (string) $field ] = mb_substr( str_replace( "\r\n", "\n", (string) $value ), 0, self::KEPT_MAX, 'UTF-8' );
+		}
+
+		$why = array();
+
+		foreach ( $problems as $field => $sentence ) {
+			if ( is_string( $sentence ) && '' !== trim( $sentence ) ) {
+				$why[ (string) $field ] = $sentence;
+			}
+		}
+
+		if ( array() === $kept && array() === $why ) {
+			return;
+		}
+
+		self::set(
+			self::KEPT_CHANNEL . $form,
+			array(
+				'at'       => time(),
+				'values'   => $kept,
+				'problems' => $why,
+			)
+		);
+	}
+
+	/**
+	 * What a refused form kept for its redraw, handed back once and cleared (1.123.58).
+	 *
+	 * Both keys are always there, so a form reads `$kept['values']` and `$kept['problems']` without a
+	 * test. An entry older than `KEPT_FOR` hands back nothing and is cleared all the same: one still
+	 * waiting is from a page that never drew the form, and it must not turn up on a later visit.
+	 *
+	 * @param string $form The form, named as `keep()` was given it.
+	 * @return array{values: array, problems: array<string, string>}
+	 */
+	public static function kept( $form ) {
+		$none  = array(
+			'values'   => array(),
+			'problems' => array(),
+		);
+		$entry = self::take( self::KEPT_CHANNEL . $form );
+
+		if ( ! is_array( $entry ) || ! isset( $entry['at'] ) || time() - (int) $entry['at'] > self::KEPT_FOR ) {
+			return $none;
+		}
+
+		return array(
+			'values'   => isset( $entry['values'] ) && is_array( $entry['values'] ) ? $entry['values'] : array(),
+			'problems' => isset( $entry['problems'] ) && is_array( $entry['problems'] ) ? $entry['problems'] : array(),
+		);
+	}
+
+	/**
+	 * Drop what an earlier refusal of a form kept, because the form has been posted again: every
+	 * handler that keeps calls this first, so a refusal whose page never drew the form cannot fill
+	 * the boxes after a save that went through (1.123.58).
+	 *
+	 * @param string $form The form, named as `keep()` was given it.
+	 */
+	public static function forget( $form ) {
+		self::take( self::KEPT_CHANNEL . $form );
+	}
+
 	/**
 	 * Everything currently queued for a user.
 	 *
```

`includes/class-wpcpm-form-errors.php`:

```php
<?php
/**
 * One way to say what is wrong with a refused form: a summary at the top, and a mark at each field.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The error summary and the field marking every refused form prints (1.123.58).
 *
 * A handler that refuses a form names each refused field with one sentence, `array( field =>
 * sentence )`, never one sentence listing every rule, and keeps what was typed (`WPCPM_Flash::keep()`
 * on a signed-in page, `WPCPM_Form_Stash` on a public form). The form is drawn again from the kept
 * values, and prints through here:
 *
 * - `summary()` at the top of the form: a heading saying how many answers need a change, and one
 *   link per problem to the field, the field's label as the link's words and the sentence after it.
 *   `role="alert"` for a screen reader with scripts off, and `tabindex="-1"` so assets/js/forms.js
 *   can move the focus to it when the page loads (WCAG 3.3.1, 3.3.3 and 4.1.3);
 * - `attrs()` on the control: `aria-invalid="true"` and `aria-describedby` naming the problem's
 *   paragraph before the hint's, so a screen reader reads why first;
 * - `problem()` beside the control: the sentence, in a paragraph whose id `attrs()` names.
 *
 * A field is known by the id of its control, which the summary links to. A problem with no field in
 * the map, a refusal of the whole form, is listed without a link.
 *
 * Every printed value is escaped here, so a caller hands sentences and labels as text.
 */
final class WPCPM_Form_Errors {

	/** What a field's id is followed by in its problem paragraph's id. */
	const SUFFIX = '-problem';

	/**
	 * The id of a field's problem paragraph.
	 *
	 * @param string $field_id The control's id.
	 * @return string
	 */
	public static function problem_id( $field_id ) {
		return (string) $field_id . self::SUFFIX;
	}

	/**
	 * A count as the summary's heading says it: spelled out from one to nine, as the house style
	 * writes a number under ten in prose, and in figures from ten. Capitalized, since it opens the
	 * heading.
	 *
	 * @param int $count How many.
	 * @return string
	 */
	public static function count_words( $count ) {
		$count = (int) $count;
		$words = array(
			1 => __( 'One', 'wpcredits-program-manager' ),
			2 => __( 'Two', 'wpcredits-program-manager' ),
			3 => __( 'Three', 'wpcredits-program-manager' ),
			4 => __( 'Four', 'wpcredits-program-manager' ),
			5 => __( 'Five', 'wpcredits-program-manager' ),
			6 => __( 'Six', 'wpcredits-program-manager' ),
			7 => __( 'Seven', 'wpcredits-program-manager' ),
			8 => __( 'Eight', 'wpcredits-program-manager' ),
			9 => __( 'Nine', 'wpcredits-program-manager' ),
		);

		return isset( $words[ $count ] ) ? $words[ $count ] : number_format_i18n( $count );
	}

	/**
	 * The one sentence a file input says when its form comes back refused: a browser never hands a
	 * chosen file back to a page, so the file is the one answer nothing can keep.
	 *
	 * @return string
	 */
	public static function file_again() {
		return __( 'A chosen file cannot be kept when the form comes back. Choose the file again.', 'wpcredits-program-manager' );
	}

	/**
	 * The summary's heading: "One answer needs a change", "Two answers need a change".
	 *
	 * @param int $count How many problems.
	 * @return string
	 */
	public static function heading( $count ) {
		return sprintf(
			/* translators: %s: how many answers, spelled out under ten, such as Two. */
			_n( '%s answer needs a change', '%s answers need a change', (int) $count, 'wpcredits-program-manager' ),
			self::count_words( $count )
		);
	}

	/**
	 * The summary a refused form opens with, or '' when nothing was refused.
	 *
	 * Listed in the order of `$fields`, the form's own order, then any problem the map does not
	 * name, in the order given.
	 *
	 * @param array<string, string> $problems Field => the sentence saying why it was refused.
	 * @param array                 $fields   Field => `array( 'id' => the control's id, 'label' =>
	 *                                        its label as text )`, in the form's order.
	 * @param array                 $args     Optional: `level`, the heading's level, 2 to 4 (2);
	 *                                        `class`, classes after `wpcpm-errors` (''); `heading`,
	 *                                        words in place of `heading()` ('').
	 * @return string
	 */
	public static function summary( array $problems, array $fields, array $args = array() ) {
		$problems = array_filter(
			$problems,
			static function ( $sentence ) {
				return is_string( $sentence ) && '' !== trim( $sentence );
			}
		);

		if ( array() === $problems ) {
			return '';
		}

		$args  = array_merge(
			array(
				'level'   => 2,
				'class'   => '',
				'heading' => '',
			),
			$args
		);
		$level = max( 2, min( 4, (int) $args['level'] ) );
		$items = array();

		foreach ( $fields as $field => $about ) {
			if ( ! isset( $problems[ $field ] ) ) {
				continue;
			}

			$id    = is_array( $about ) && isset( $about['id'] ) ? (string) $about['id'] : '';
			$label = is_array( $about ) && isset( $about['label'] ) ? (string) $about['label'] : '';

			$items[] = '' !== $id && '' !== $label
				? sprintf(
					/* translators: 1: a link to the field, its label as the words, 2: the sentence saying why it was refused. */
					__( '%1$s: %2$s', 'wpcredits-program-manager' ),
					sprintf( '<a href="#%1$s">%2$s</a>', esc_attr( $id ), esc_html( $label ) ),
					esc_html( $problems[ $field ] )
				)
				: esc_html( $problems[ $field ] );

			unset( $problems[ $field ] );
		}

		foreach ( $problems as $sentence ) {
			$items[] = esc_html( $sentence );
		}

		$class = trim( 'wpcpm-errors ' . (string) $args['class'] );
		$title = '' !== (string) $args['heading'] ? (string) $args['heading'] : self::heading( count( $items ) );

		return sprintf(
			'<div class="%1$s" role="alert" tabindex="-1"><h%2$d class="wpcpm-errors__title">%3$s</h%2$d><ul class="wpcpm-errors__list"><li>%4$s</li></ul></div>',
			esc_attr( $class ),
			$level,
			esc_html( $title ),
			implode( '</li><li>', $items )
		);
	}

	/**
	 * The attributes a control carries: a refused one `aria-invalid="true"` and `aria-describedby`
	 * naming its problem, then its hint; any other its hint alone; '' for neither. With a space in
	 * front, to be printed straight after the control's other attributes.
	 *
	 * @param string $field_id The control's id.
	 * @param string $sentence The sentence it was refused with, or '' when it was not.
	 * @param string $hint_id  The id of the control's hint, or '' for none.
	 * @return string
	 */
	public static function attrs( $field_id, $sentence, $hint_id = '' ) {
		$described = array();

		if ( '' !== trim( (string) $sentence ) ) {
			$described[] = self::problem_id( $field_id );
		}

		if ( '' !== (string) $hint_id ) {
			$described[] = (string) $hint_id;
		}

		$attrs = '' !== trim( (string) $sentence ) ? ' aria-invalid="true"' : '';

		if ( array() !== $described ) {
			$attrs .= ' aria-describedby="' . esc_attr( implode( ' ', $described ) ) . '"';
		}

		return $attrs;
	}

	/**
	 * The sentence a control was refused with, in the paragraph `attrs()` names, or '' when it was
	 * not refused.
	 *
	 * @param string $field_id The control's id.
	 * @param string $sentence The sentence, or ''.
	 * @return string
	 */
	public static function problem( $field_id, $sentence ) {
		if ( '' === trim( (string) $sentence ) ) {
			return '';
		}

		return sprintf(
			'<p class="wpcpm-field__problem" id="%1$s">%2$s</p>',
			esc_attr( self::problem_id( $field_id ) ),
			esc_html( (string) $sentence )
		);
	}
}
```

The loader and the uninstaller each gain one line after `class-wpcpm-typed-text.php`:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-errors.php';
```

```php
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-form-errors.php';
```

`assets/js/forms.js`:

```diff
diff --git a/assets/js/forms.js b/assets/js/forms.js
index 62fe5d6d..45c33536 100644
--- a/assets/js/forms.js
+++ b/assets/js/forms.js
@@ -31,7 +31,9 @@
  *   a name shared by radios is not a control and is left alone;
  * - `data-wpcpm-confirm` on a form or on a submit control is a question asked with
  *   `window.confirm()` before the form posts, and a No posts nothing. It is read for any form on
- *   the page when that form is submitted, including one inserted after the page loaded.
+ *   the page when that form is submitted, including one inserted after the page loaded;
+ * - `.wpcpm-errors`, a refused form's error summary, or else `data-wpcpm-card-message`, the outcome
+ *   a card's handler left in its card, takes the focus when the page loads (1.123.58).
  */
 ( function () {
 	'use strict';
@@ -529,7 +531,23 @@
 		}
 	}
 
+	/**
+	 * Move the focus to what a refused or answered form says, once the page has loaded (1.123.58):
+	 * the error summary a refused form opens with (`WPCPM_Form_Errors::summary()`), else the message a
+	 * card's handler left inside the card it came back to (`WPCPM_Dashboard_Card::message()`). The
+	 * first of either on the page; a page with neither is left as it is. With scripts off, the
+	 * summary's `role="alert"` and the message's own role are what a screen reader hears.
+	 */
+	function focusOutcome() {
+		var target = document.querySelector( '.wpcpm-errors' ) || document.querySelector( '[data-wpcpm-card-message]' );
+
+		if ( target && 'function' === typeof target.focus ) {
+			target.focus();
+		}
+	}
+
 	ready( function () {
+		focusOutcome();
 		watchPressed();
 		watchSubmits();
 		releaseOnRestore();
```

The styles. `assets/css/application.css`, `dashboard.css`, `institution.css` and `sponsor.css` each end with the summary's rules, which read the sheet's own `--wpcpm-border` and `--wpcpm-radius`:

```css
/*
 * A refused form's summary (`WPCPM_Form_Errors::summary()`, 1.123.58): the count of answers to change
 * and a link to each, bordered on the leading edge as the outcome of an attempt is, and outlined when
 * assets/js/forms.js moves the focus to it on load. A card's outcome takes the same outline.
 */
.wpcpm-errors {
	border: 1px solid var( --wpcpm-border );
	border-inline-start-width: 4px;
	border-radius: var( --wpcpm-radius );
	margin: 0 0 1.5em;
	padding: 0.75em 1em;
}

.wpcpm-errors__title {
	font-size: 16px;
	margin: 0 0 0.5em;
}

.wpcpm-errors__list {
	margin: 0;
	padding-inline-start: 1.25em;
}

.wpcpm-errors:focus,
[data-wpcpm-card-message]:focus {
	outline: 2px solid currentColor;
	outline-offset: 2px;
}
```

and `dashboard.css`, `institution.css` and `sponsor.css` (application.css has its own already) the problem's:

```css

/* What is wrong with one answer, beside its control (1.123.58). Never color alone: the field is
   marked `aria-invalid` and the sentence says what to do. */
.wpcpm-field__problem {
	font-weight: 600;
	margin: 0.3em 0 0;
}
```

`assets/css/admin.css` ends with the wp-admin form of both, since there the summary is drawn inside core's `notice notice-error`:

```css
/*
 * A refused settings form's summary (`WPCPM_Form_Errors::summary()`, 1.123.58), drawn as core's error
 * notice: the list keeps its bullets, and the notice is outlined when assets/js/forms.js moves the
 * focus to it on load, in the admin color scheme's own color.
 */
.wpcpm-errors__title {
	font-size: 14px;
	margin: 0.75em 0 0.25em;
}

.wpcpm-errors__list {
	list-style: disc;
	margin: 0 0 0.75em;
	padding-inline-start: 1.25em;
}

.wpcpm-errors:focus {
	outline: 2px solid var( --wp-admin-theme-color, #2271b1 );
	outline-offset: 2px;
}

.wpcpm-field__problem {
	color: #d63638;
	font-weight: 600;
	margin: 0.3em 0 0;
}
```

- [ ] **Step 4: Run them to verify they pass**

```text
$ php bin/test-flash.php
ALL PASS
$ php bin/test-form-errors.php
ALL PASS
$ php bin/test-forms-js.php
     52 scenarios, 0 differ
ALL PASS
$ php bin/test-submit-guard.php
ALL PASS
```

- [ ] **Step 5: The checks every task ends with**

Expected as the block above says, with `198 classes` in the references line and one more script in the temp-litter line than at the start.

- [ ] **Step 6: Commit**

```bash
git add includes/class-wpcpm-flash.php includes/class-wpcpm-form-errors.php wpcredits-program-manager.php uninstall.php assets/js/forms.js assets/css/application.css assets/css/dashboard.css assets/css/institution.css assets/css/sponsor.css assets/css/admin.css bin/test-flash.php bin/test-form-errors.php bin/js/forms-harness/run.js bin/js/forms-harness/scenarios.js bin/test-submit-guard.php
git commit -m "Scan release 1: kept values for a refused signed-in form, and one error summary with its focus" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 2: Settings write only what changed, refuse what would be blanked, and say so at the field (shared mechanism and the Settings screen)

Findings: SET-01, LOG-03 (the drawn-value save), SET-02, SET-03, SET-13 (a value the rule would blank is a refusal of that field), LOG-11 (the CLI). Scope **M**. The drawn-value save, the refusals and the box above each Who is mailed list are proved below with their suites; the marking of the rows, the warning and the CLI are specified.

**The way back to every Administrator account** (the owner's ruling of 6 November 2026). Each of the four Who is mailed rows (`agreement_notify`, `report_notify`, `sponsor_notify`, `mentor_notify`, `WPCPM_Settings::NOTIFY_LISTS`) gets a checkbox above its list, `Mail every Administrator account instead of the people below`, posting `<key>_everyone` (`EVERYONE_SUFFIX`), drawn ticked when the stored list is empty. Ticked, the list below is drawn as it is but not read, and the value written is `''`. Unticked with an empty list, the list is refused at the field. `wp wpcredits setting set <key> everyone` writes the same, for those four keys only. The refusal of an empty list belongs to the Settings form's own save: `patch()` and `setting set <key> everyone` write the empty value without it, and a Settings page drawn before 1.123.58 went live posts no box, so its first save of an empty list meets the refusal once and the reloaded page carries the box.

**The trade-off chosen.** One hidden fingerprint per setting (`wpcpm_drawn[<key>]`, an md5 of the value as drawn), not one fingerprint of the whole tab. A tab fingerprint says only that something changed since the page was drawn; the save would then have to refuse the whole tab, losing the person's other changes, or write it whole, which is the 17 October incident. A fingerprint per setting says which setting the person changed and which changed elsewhere, so the save writes the first and keeps the second. The cost is about forty bytes a setting, a kilobyte on the largest tab; a fingerprint, not the value, so a masked secret never reaches the page. The older `FIELD_DRAWN_STATUSES` stays: it carries the list itself, which `student_statuses_from()` still merges.

**The rule**, in `WPCPM_Settings::write()`, for every setting of the scope the form posted a fingerprint for: posted back as drawn, the stored value stays, whatever it is now; changed by the person and unchanged elsewhere, the person's value is written; changed by both, to different values, the stored value stays and the setting is named (`last_kept()`). A refused setting (`last_refused()`) keeps its stored value and is never named as changed elsewhere. A form that posts no fingerprints (a page drawn before 1.123.58, or `save()` called from code) is saved as before.

**Files:**
- Modify: `includes/class-wpcpm-settings.php` (`FIELD_DRAWN`, `$refused`, `$kept`; `save()`; `fingerprint()`, `as_text()`, `drawn_fields()`, `drawn_from()`, `last_refused()`, `last_kept()`, `refuse()`; `write()`; `not_addresses()`; the reviewer lists, `agreement_doc_url`, `mentor_course_link`, `application_trusted_proxy`, `mail_contact` and `checker_slack_webhook` branches; `patch()` gains `$dry`)
- Modify: `includes/class-wpcpm-settings-screen.php` (`open_settings_form()` prints the fingerprints; `handle_settings_save()` reads them, keeps the refusals, flashes `settings-kept`; `kept_form()`; `settings_messages()` gains `saved-except`; `render_tab_form()` buffers the tab and prints the summary and the warning above it)
- Modify: `includes/class-wpcpm-settings-rows.php` (`mark()`, `drawn_labels()`, and every row function reads the marks)
- Modify: `includes/tools/class-wpcpm-tool.php` (`render_settings()` buffers its section as the tab does), `includes/tools/class-wpcpm-emails.php` (drops the `emails-contact-refused` notice, about `:396`), `includes/tools/class-wpcpm-mentor-checker.php` (drops the `checker-webhook-refused` notice, about `:807`)
- Modify: `includes/class-wpcpm-cli.php` (`setting()`)
- Test: `bin/test-settings.php`, `bin/test-settings-choices.php`, `bin/test-duplicate-finder.php` (its stand-in gains `drawn_fields()`), `bin/test-emails-templates.php` (about `:1078`), `bin/test-cli.php`

**Interfaces:**
- Consumes: Task 1's `WPCPM_Flash::keep()`, `kept()`, `WPCPM_Form_Errors::summary()`, `attrs()`, `problem()`, `count_words()`.
- Produces:
  - `WPCPM_Settings::FIELD_DRAWN = 'wpcpm_drawn'`; `NOTIFY_LISTS`; `EVERYONE_SUFFIX = '_everyone'`; `fingerprint( mixed ): string`; `drawn_fields( string $scope ): string`; `drawn_from( mixed ): array<string,string>`; `last_refused(): array<string,string>`; `last_kept(): string[]`; `patch( array $changes, bool $dry = false ): array`.
  - `WPCPM_Settings_Screen::kept_form( string $scope ): string` (`settings:<scope>`); the outcome `saved-except` (warning); the flash `settings-kept` (a list of settings).
  - `WPCPM_Settings_Rows::mark( array $problems, array $values ): void`, `drawn_labels(): array<string, array{id: string, label: string}>`, `reset_marks(): void`.
  - `wp wpcredits setting get <key>` and `wp wpcredits setting set <key> <value> [--dry-run]`.

**The words** (every one below is the plugin's; the tests pin them):

| Where | Words |
| --- | --- |
| A reviewer list with an entry that is no address (one) | `"%s" is not an email address, so the list was not saved. Correct it or remove it, then save again.` |
| (more) | `%s are not email addresses, so the list was not saved. Correct them or remove them, then save again.` (the entries in quotes, joined by `", "`) |
| A reviewer list left empty with its box unticked | `Name at least one address, or tick the box above to mail every Administrator account.` |
| The box above each Who is mailed list | `Mail every Administrator account instead of the people below` |
| Collaboration Agreement link | `Give a link that starts with https://docs.google.com/ or https://drive.google.com/. The link saved before was kept.` |
| Mentors' course invitation link | `Give a link that starts with https://. The link saved before was kept.` |
| Trusted proxy | `Give one IP address, such as 192.0.2.10, or leave the box empty. The address saved before was kept.` |
| Contact address in emails | `Enter one email address, such as program@example.org, or leave the box empty to name none. The address saved before was kept.` |
| Slack webhook | `Give the address Slack showed when the incoming webhook was made, or type remove to clear it. The webhook saved before was kept.` |
| The tab's notice when anything was refused (`saved-except`, warning) | `The settings marked below were not saved. Everything else was saved.` |
| The warning's heading (`count_words()`) | `_n( '%s setting changed since you opened this page', '%s settings changed since you opened this page' )` |
| Each kept setting, at its field and in the warning | `Changed elsewhere after this page was opened, so the value saved then is shown and yours was not saved. Check it, and save again if yours should win.` |
| CLI, `get` of an unknown key | `There is no setting called %s.` |
| CLI, `set` that changed it | `Changed %1$s from %2$s to %3$s.` |
| CLI, `set` to the stored value | `%1$s is already %2$s. Nothing was written.` |
| CLI, `set --dry-run` | `Dry run: %1$s would change from %2$s to %3$s. Nothing was written.` |
| CLI, a refused value | `%1$s was not changed: %2$s` (the refusal's sentence) |
| CLI, `everyone` for a key that is no Who is mailed list | `everyone is a value only for the four Who is mailed lists: %s.` (the four keys, joined by `, `) |
| CLI, a status list a live track holds | `%s was left as it was: a track on the live site runs on a status this change would take out, or would make past. Take the track off the live site in the Track Builder first.` |

A secret (`api_token`, `schema_token`, `handbook_key`, `checker_slack_webhook`) is printed by the CLI as the screen masks it (`masked_token()`, `masked_schema_token()`, `masked_handbook_key()`, `masked_slack_webhook()`), never as stored. A boolean is printed `on` or `off` and taken as `1`, `0`, `on`, `off`, `true` or `false`; a list as its items joined by `, ` and taken comma-separated.

- [ ] **Step 1: Write the failing checks**

`bin/test-settings.php`. The checks that pinned the old silent blanking are rewritten to the refusal (the reviewer list, the report list, the mentors' list, the course link, the patch), the outcome map gains `saved-except`, `forms_of()` reads the fingerprints as plumbing, and a new section proves the drawn-value save:

```diff
diff --git a/bin/test-settings.php b/bin/test-settings.php
index 80be882c..066d938e 100644
--- a/bin/test-settings.php
+++ b/bin/test-settings.php
@@ -737,17 +737,46 @@ foreach ( $ranges as $key => $range ) {
 	ck( sprintf( '%s accepts its floor', $key ), array( $saved[ $key ] ), array( $range[0] ) );
 }
 
-// The notify list: commas or newlines, valid addresses kept once, everything else dropped.
+// The notify list: commas or newlines, valid addresses kept once; an entry that is no address refuses
+// the list, which keeps what was stored and says which entries (SET-02, 1.123.58).
+$saved = WPCPM_Settings::save( array( 'agreement_notify' => "one@example.org,\ntwo@example.org, one@example.org\n\n  three@example.org  " ) );
+ck( 'agreement_notify keeps the addresses, each once, in the order typed',
+    array( $saved['agreement_notify'], WPCPM_Settings::last_refused() ), array( 'one@example.org,two@example.org,three@example.org', array() ) );
+
 $saved = WPCPM_Settings::save(
 	array(
 		'agreement_notify' => "one@example.org, not-an-address\ntwo@example.org,name@\n@example.org, one@example.org\n\n  three@example.org  ",
 	)
 );
-ck( 'agreement_notify keeps the addresses and drops the junk',
-    array( $saved['agreement_notify'] ), array( 'one@example.org,two@example.org,three@example.org' ) );
+ck( 'and refuses a list with an entry that is no address: the stored list stays, and the sentence names each wrong entry as typed',
+    array( $saved['agreement_notify'], WPCPM_Settings::last_refused() ),
+    array( 'one@example.org,two@example.org,three@example.org', array( 'agreement_notify' => '"not-an-address", "name@", "@example.org" are not email addresses, so the list was not saved. Correct them or remove them, then save again.' ) ) );
+
+$saved = WPCPM_Settings::save( array( 'agreement_notify' => "nothing\njavascript:alert(1) one@example.org" ) );
+ck( 'two wrong entries are each named, in quotes', WPCPM_Settings::last_refused(), array( 'agreement_notify' => '"nothing", "javascript:alert(1)" are not email addresses, so the list was not saved. Correct them or remove them, then save again.' ) );
+
+$saved = WPCPM_Settings::save( array( 'agreement_notify' => 'one@example.org, typo@' ) );
+ck( 'and a single wrong entry in the singular', WPCPM_Settings::last_refused(), array( 'agreement_notify' => '"typo@" is not an email address, so the list was not saved. Correct it or remove it, then save again.' ) );
+
+$saved = WPCPM_Settings::save( array( 'agreement_notify' => '' ) );
+ck( 'a list the save would empty is refused too, unless its box says every Administrator account',
+    array( $saved['agreement_notify'], WPCPM_Settings::last_refused() ),
+    array( 'one@example.org,two@example.org,three@example.org', array( 'agreement_notify' => 'Name at least one address, or tick the box above to mail every Administrator account.' ) ) );
+
+$saved = WPCPM_Settings::save( array( 'agreement_notify' => 'kept-below@example.org', 'agreement_notify' . WPCPM_Settings::EVERYONE_SUFFIX => true ) );
+ck( 'the box "Mail every Administrator account instead of the people below" writes the list empty, whatever the list below it holds, and refuses nothing',
+    array( $saved['agreement_notify'], WPCPM_Settings::last_refused() ), array( '', array() ) );
 
-$saved = WPCPM_Settings::save( array( 'agreement_notify' => "nothing here\njavascript:alert(1)" ) );
-ck( 'and is empty when nothing valid was typed', array( $saved['agreement_notify'] ), array( '' ) );
+$saved = WPCPM_Settings::save( array( 'agreement_notify' => 'not-an-address', 'agreement_notify' . WPCPM_Settings::EVERYONE_SUFFIX => true ) );
+ck( 'and with the box ticked a list that is no address is not read at all', array( $saved['agreement_notify'], WPCPM_Settings::last_refused() ), array( '', array() ) );
+
+$saved = WPCPM_Settings::save( array( 'agreement_notify' => '' ) );
+ck( 'an empty list with the box unticked is refused even when the stored list is empty, the stored list kept', array( $saved['agreement_notify'], array_keys( WPCPM_Settings::last_refused() ) ), array( '', array( 'agreement_notify' ) ) );
+
+$saved = WPCPM_Settings::patch( array( 'report_notify' => '', 'report_notify' . WPCPM_Settings::EVERYONE_SUFFIX => true ) );
+ck( 'a patch carrying the box writes the list empty, as wp wpcredits setting set <key> everyone does', array( $saved['report_notify'], WPCPM_Settings::last_refused() ), array( '', array() ) );
+
+WPCPM_Settings::save( array( 'agreement_notify' => "one@example.org,\ntwo@example.org,three@example.org" ) );
 
 $saved = WPCPM_Settings::save( array( 'agreement_notify' => 'kept@example.org' ) );
 $saved = WPCPM_Settings::save( array( 'base_id' => 'appPROBE0000000003' ) );
@@ -1061,7 +1090,9 @@ ck( 'the three report settings and their defaults', array( $defaults['report_aut
 $GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = WPCPM_Settings::defaults();
 $saved = WPCPM_Settings::save( array( 'report_autodraft_grace_days' => '2', 'report_notify' => "one@example.org, junk\nadministrator@example.test" ) );
 ck( 'the grace has a floor of a week', $saved['report_autodraft_grace_days'], 7 );
-ck( 'the addresses are cleaned like the agreement ones', $saved['report_notify'], 'one@example.org,administrator@example.test' );
+ck( 'the addresses are held to the agreement ones\' rule: an entry that is no address refuses the list', array( $saved['report_notify'], WPCPM_Settings::last_refused() ), array( '', array( 'report_notify' => '"junk" is not an email address, so the list was not saved. Correct it or remove it, then save again.' ) ) );
+$saved = WPCPM_Settings::save( array( 'report_notify' => "one@example.org\nadministrator@example.test" ) );
+ck( 'and a list of addresses is saved as the comma list', $saved['report_notify'], 'one@example.org,administrator@example.test' );
 ck( 'a save that does not carry the switch leaves it on', $saved['report_autodraft'], true );
 $saved = WPCPM_Settings::save( array( 'report_autodraft' => '', 'report_autodraft_grace_days' => '900' ) );
 ck( 'the switch carried empty is off', $saved['report_autodraft'], false );
@@ -1117,8 +1148,9 @@ ck( 'the mentor form is off until an Administrator switches it on, a new applica
 	array( $defaults['mentor_applications_enabled'], $defaults['mentor_notify'], $defaults['mentor_course_link'], $defaults['mentor_lapse_days'] ),
 	array( false, '', '', 90 ) );
 
-$saved = WPCPM_Settings::save( array( 'mentor_notify' => 'Ida@Example.test, not-an-address ida@example.test', 'mentor_course_link' => ' https://learn.example.test/course/mentors/?group=EXAMPLE ', 'mentor_lapse_days' => '1000' ) );
-ck( 'the notice list is cleaned as its siblings are: split, lowered, the non-addresses dropped, duplicates gone', $saved['mentor_notify'], 'ida@example.test' );
+$saved = WPCPM_Settings::save( array( 'mentor_notify' => 'Ida@Example.test, ida@example.test', 'mentor_course_link' => ' https://learn.example.test/course/mentors/?group=EXAMPLE ', 'mentor_lapse_days' => '1000' ) );
+ck( 'the notice list is cleaned as its siblings are: split, lowered, duplicates gone', $saved['mentor_notify'], 'ida@example.test' );
+ck( 'and held to their rule: an entry that is no address refuses it', array( WPCPM_Settings::save( array( 'mentor_notify' => 'ida@example.test, not-an-address' ) )['mentor_notify'], array_keys( WPCPM_Settings::last_refused() ) ), array( 'ida@example.test', array( 'mentor_notify' ) ) );
 ck( 'the course link is kept as an https link, trimmed', $saved['mentor_course_link'], 'https://learn.example.test/course/mentors/?group=EXAMPLE' );
 ck( 'the days are clamped: 365 at most, 14 at least', array( $saved['mentor_lapse_days'], WPCPM_Settings::save( array( 'mentor_lapse_days' => '3' ) )['mentor_lapse_days'] ), array( 365, 14 ) );
 
@@ -1127,9 +1159,14 @@ ck( 'the days are clamped: 365 at most, 14 at least', array( $saved['mentor_laps
 // protocol allowed, so "not a link at all" is kept as https://not%20a%20link%20at%20all, where earlier
 // versions gave it http:// and refused it. What the site does on 6.9 and later is pinned after the loop.
 foreach ( array( 'http://learn.example.test/course/mentors/', 'javascript:alert(1)', 'http://example.test/course' ) as $typed ) {
-	ck( sprintf( 'and a course link that is not https is saved as none: %s', $typed ), WPCPM_Settings::save( array( 'mentor_course_link' => $typed ) )['mentor_course_link'], '' );
+	ck( sprintf( 'and a course link that is not https is refused, the link saved before kept, with the sentence that says why: %s', $typed ),
+		array( WPCPM_Settings::save( array( 'mentor_course_link' => $typed ) )['mentor_course_link'], WPCPM_Settings::last_refused() ),
+		array( 'https://learn.example.test/course/mentors/?group=EXAMPLE', array( 'mentor_course_link' => 'Give a link that starts with https://. The link saved before was kept.' ) ) );
 }
 
+ck( 'a course link emptied on purpose is saved as none, refusing nothing', array( WPCPM_Settings::save( array( 'mentor_course_link' => '  ' ) )['mentor_course_link'], WPCPM_Settings::last_refused() ), array( '', array() ) );
+WPCPM_Settings::save( array( 'mentor_course_link' => 'https://learn.example.test/course/mentors/?group=EXAMPLE' ) );
+
 ck( 'and a course link typed with no scheme is given https and kept, as WordPress 6.9 and later gives it',
 	WPCPM_Settings::save( array( 'mentor_course_link' => 'learn.example.test/course' ) )['mentor_course_link'], 'https://learn.example.test/course' );
 
@@ -2159,8 +2196,8 @@ ck( 'the notice names the tab, or the tool, that was saved',
 $messages = WPCPM_Settings_Screen::settings_messages();
 
 ck( 'every outcome the settings flash can carry has words, the refusal among them as an error that says what the form asked for',
-    array( array_keys( $messages ), $messages['refused'] ),
-    array( array( 'saved', 'refused', 'log-cleared', 'lists-read' ), array( 'error', 'Nothing was saved: the form asked to save settings this screen does not have. Reload the page and save again.' ) ) );
+    array( array_keys( $messages ), $messages['refused'], $messages['saved-except'] ),
+    array( array( 'saved', 'saved-except', 'refused', 'log-cleared', 'lists-read' ), array( 'error', 'Nothing was saved: the form asked to save settings this screen does not have. Reload the page and save again.' ), array( 'warning', 'The settings marked below were not saved. Everything else was saved.' ) ) );
 
 WPCPM_Tracks::$live = array();
 $GLOBALS['umeta']   = array();
@@ -2182,6 +2219,112 @@ ck( 'and the save handler and save() both ask it, while neither resolves a scope
     ),
     array( 1, 1, 0 ) );
 
+echo "\n=== A page drawn earlier writes only what the person changed (1.123.58) ===\n";
+
+/**
+ * The fingerprints a tab's form carries, as a browser posts them: setting => fingerprint.
+ *
+ * @param string $scope The tab.
+ * @return array<string, string>
+ */
+function drawn_post( $scope ) {
+	preg_match_all( '#name="' . preg_quote( WPCPM_Settings::FIELD_DRAWN, '#' ) . '\[([a-z0-9_]+)\]" value="([a-f0-9]{32})"#', WPCPM_Settings::drawn_fields( $scope ), $found, PREG_SET_ORDER );
+
+	$drawn = array();
+
+	foreach ( $found as $field ) {
+		$drawn[ $field[1] ] = $field[2];
+	}
+
+	return $drawn;
+}
+
+$people_page = array_merge(
+	$defaults,
+	array(
+		'mentor_applications_enabled' => false,
+		'mentor_course_link'          => 'https://learn.example.test/course/one/',
+		'mentor_lapse_days'           => 90,
+		'mentor_notify'               => 'ida@example.test',
+	)
+);
+
+store_settings( $people_page );
+$drawn = drawn_post( 'people' );
+ck( 'the form carries a fingerprint for every setting of its scope, and no other', array_keys( $drawn ), $scopes['people'] );
+store_settings( array_merge( $people_page, array( 'api_token' => 'patEXAMPLE0000000000001' ) ) );
+ck( 'and a fingerprint is no setting\'s value: a secret the page masks never reaches it', array( substr_count( WPCPM_Settings::drawn_fields( 'connection' ), WPCPM_Settings::FIELD_DRAWN . '[api_token]' ), strpos( WPCPM_Settings::drawn_fields( 'connection' ), 'patEXAMPLE' ) ), array( 1, false ) );
+store_settings( $people_page );
+ck( 'a scope this version does not have draws none', array( WPCPM_Settings::drawn_fields( 'no-such-tab' ), WPCPM_Settings::drawn_fields( '' ) ), array( '', '' ) );
+
+// The page as drawn, posted back with one change: the lapse days.
+$as_drawn = array(
+	'student_statuses'      => implode( "\n", (array) $people_page['student_statuses'] ),
+	'past_statuses'         => implode( "\n", (array) $people_page['past_statuses'] ),
+	'mentor_status'         => $people_page['mentor_status'],
+	'on_inactive'           => $people_page['on_inactive'],
+	'student_on_inactive'   => $people_page['student_on_inactive'],
+	'mentor_notify'         => $people_page['mentor_notify'],
+	'mentor_course_link'    => $people_page['mentor_course_link'],
+	'mentor_lapse_days'     => '120',
+	WPCPM_Settings::FIELD_DRAWN => $drawn,
+);
+
+foreach ( array( 'send_welcome_email', 'auto_sync', 'mentor_home', 'student_home', 'mentor_activation_enabled', 'student_registrations_enabled' ) as $flag ) {
+	if ( ! empty( $people_page[ $flag ] ) ) {
+		$as_drawn[ $flag ] = '1';
+	}
+}
+
+// Meanwhile the command line switches the mentor form on: the 17 October 2026 case.
+store_settings( array_merge( $people_page, array( 'mentor_applications_enabled' => true ) ) );
+press_tab( 'people', $as_drawn );
+ck( 'a switch changed elsewhere since the page was drawn is not switched back by a Save of that page',
+    array( WPCPM_Settings::get()['mentor_applications_enabled'], WPCPM_Settings::get()['mentor_lapse_days'], WPCPM_Settings::last_kept() ),
+    array( true, 120, array() ) );
+
+store_settings( array_merge( $people_page, array( 'mentor_course_link' => 'https://learn.example.test/course/two/' ) ) );
+press_tab( 'people', array_merge( $as_drawn, array( 'mentor_course_link' => 'https://learn.example.test/course/three/' ) ) );
+ck( 'a setting the person changed and that changed elsewhere since too keeps the stored value, and is named',
+    array( WPCPM_Settings::get()['mentor_course_link'], WPCPM_Settings::last_kept(), $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-kept'] ?? null, WPCPM_Settings::get()['mentor_lapse_days'] ),
+    array( 'https://learn.example.test/course/two/', array( 'mentor_course_link' ), array( 'mentor_course_link' ), 120 ) );
+
+store_settings( array_merge( $people_page, array( 'mentor_course_link' => 'https://learn.example.test/course/two/' ) ) );
+press_tab( 'people', array_merge( $as_drawn, array( 'mentor_course_link' => 'https://learn.example.test/course/two/' ) ) );
+ck( 'one changed to the same value in both places is no conflict', array( WPCPM_Settings::get()['mentor_course_link'], WPCPM_Settings::last_kept() ), array( 'https://learn.example.test/course/two/', array() ) );
+
+store_settings( $people_page );
+press_tab( 'people', array_merge( $as_drawn, array( 'mentor_course_link' => 'https://learn.example.test/course/three/' ) ) );
+ck( 'with nothing changed elsewhere, the person\'s change is written', array( WPCPM_Settings::get()['mentor_course_link'], WPCPM_Settings::last_kept() ), array( 'https://learn.example.test/course/three/', array() ) );
+
+store_settings( array_merge( $people_page, array( 'mentor_applications_enabled' => true ) ) );
+$no_prints = $as_drawn;
+unset( $no_prints[ WPCPM_Settings::FIELD_DRAWN ] );
+press_tab( 'people', $no_prints );
+ck( 'a form that carries no fingerprints is saved as before, every setting as posted', WPCPM_Settings::get()['mentor_applications_enabled'], false );
+
+store_settings( array_merge( $people_page, array( 'mentor_course_link' => 'https://learn.example.test/course/two/' ) ) );
+press_tab( 'people', array_merge( $as_drawn, array( 'mentor_course_link' => 'http://learn.example.test/plain/' ) ) );
+ck( 'a refused setting is refused, not named as changed elsewhere, and its typing and sentence wait for the tab',
+    array( WPCPM_Settings::get()['mentor_course_link'], WPCPM_Settings::last_kept(), WPCPM_Flash::kept( WPCPM_Settings_Screen::kept_form( 'people' ) ), $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings'] ?? null ),
+    array( 'https://learn.example.test/course/two/', array(), array( 'values' => array( 'mentor_course_link' => 'http://learn.example.test/plain/' ), 'problems' => array( 'mentor_course_link' => 'Give a link that starts with https://. The link saved before was kept.' ) ), 'saved-except' ) );
+
+ck( 'the fingerprint reads a value as text: an integer and its numeral, true and "1", false, null and "" are alike',
+    array( WPCPM_Settings::fingerprint( 90 ) === WPCPM_Settings::fingerprint( '90' ), WPCPM_Settings::fingerprint( true ) === WPCPM_Settings::fingerprint( '1' ), WPCPM_Settings::fingerprint( false ) === WPCPM_Settings::fingerprint( null ), WPCPM_Settings::fingerprint( array( 'a' ) ) === WPCPM_Settings::fingerprint( array( 'b' ) ) ),
+    array( true, true, true, false ) );
+
+ck( 'a posted fingerprint is taken only for a setting there is, and only in a fingerprint\'s shape',
+    WPCPM_Settings::drawn_from( array( 'mentor_lapse_days' => str_repeat( 'a', 32 ), 'no_such_setting' => str_repeat( 'b', 32 ), 'mentor_notify' => 'not-a-print', 'auto_sync' => array( 'x' ) ) ),
+    array( 'mentor_lapse_days' => str_repeat( 'a', 32 ) ) );
+
+store_settings( array_merge( $defaults, array( 'api_token' => 'patEXAMPLE0000000000002' ) ) );
+$connection_drawn = drawn_post( 'connection' );
+$token_mask       = WPCPM_Settings::masked_token();
+store_settings( array_merge( $defaults, array( 'api_token' => 'patEXAMPLE0000000000003' ) ) );
+press_tab( 'connection', array( 'api_token' => $token_mask, 'base_id' => (string) $defaults['base_id'], WPCPM_Settings::FIELD_DRAWN => $connection_drawn ) );
+ck( 'a token rotated elsewhere since is kept by a Save that posts its mask, with no warning', array( WPCPM_Settings::get()['api_token'], WPCPM_Settings::last_kept() ), array( 'patEXAMPLE0000000000003', array() ) );
+store_settings( $defaults );
+
 echo "\n=== Writing some settings and leaving the rest: patch() ===\n";
 
 // The handbook's migration moves a site off a retired model or provider on `init`, one setting at a
@@ -2215,8 +2358,8 @@ $patched = WPCPM_Settings::patch(
 );
 
 ck( 'each setting patched is held to save()\'s rule for it: clamped, refused, cleaned, a blank token left alone, an unknown answer read as the fallback',
-    array( $patched['logo_max_kb'], $patched['handbook_provider'], $patched['agreement_notify'], $patched['api_token'], $patched['on_inactive'] ),
-    array( 8192, '', 'a@example.org', 'patKEEPME0000000000', 'revoke' ) );
+    array( $patched['logo_max_kb'], $patched['handbook_provider'], $patched['agreement_notify'], $patched['api_token'], $patched['on_inactive'], array_keys( WPCPM_Settings::last_refused() ) ),
+    array( 8192, '', '', 'patKEEPME0000000000', 'revoke', array( 'agreement_notify' ) ) );
 
 store_settings( $defaults );
 WPCPM_Settings::patch( array( 'mentor_status' => '' ) );
@@ -2402,6 +2545,11 @@ function forms_of( $html ) {
 		$fields = array();
 
 		foreach ( $names[1] as $name ) {
+			// The fingerprints of what the form was drawn with (1.123.58) are plumbing, as the nonce is.
+			if ( 0 === strpos( $name, WPCPM_Settings::FIELD_DRAWN . '[' ) ) {
+				continue;
+			}
+
 			$name = preg_replace( '/\[\]$/', '', $name );
 
 			if ( ! in_array( $name, $plumbing, true ) ) {
```

`bin/test-settings-choices.php`: the field-name reader treats the fingerprints as plumbing, the emptied list is refused, the sponsor list with a non-address is refused, and a drawn form with a list's box ticked writes the list empty:

```diff
diff --git a/bin/test-settings-choices.php b/bin/test-settings-choices.php
index 22c47111..bb759601 100644
--- a/bin/test-settings-choices.php
+++ b/bin/test-settings-choices.php
@@ -636,6 +636,11 @@ function names_of( $form ) {
 	$names = array();
 
 	foreach ( $found[1] as $name ) {
+		// The fingerprints of what the form was drawn with (1.123.58) are plumbing, as the nonce is.
+		if ( 0 === strpos( $name, WPCPM_Settings::FIELD_DRAWN . '[' ) ) {
+			continue;
+		}
+
 		$names[ preg_replace( '/\[\]$/', '', $name ) ] = true;
 	}
 
@@ -2091,8 +2096,8 @@ press( $institutions_post );
 
 ck( 'the ticked program managers and the Other addresses line save the comma list the text input saved for the same addresses, lowercased and each once',
     $reviewers_from_boxes, array( WPCPM_Settings::get()['agreement_notify'], WPCPM_Settings::get()['report_notify'] ) );
-ck( 'which is today\'s stored shape, and nobody ticked with an empty line is the empty setting, which writes to every program manager',
-    $reviewers_from_boxes, array( 'board@partner.example,ana@program.example,pat@program.example,second@partner.example', '' ) );
+ck( 'which is today\'s stored shape; and nobody ticked with an empty line would empty a list that names somebody, so that list is refused and stays as stored (SET-02, 1.123.58)',
+    $reviewers_from_boxes, array( 'board@partner.example,ana@program.example,pat@program.example,second@partner.example', $stored_now['report_notify'] ) );
 
 store( $stored_now, $live );
 airtable_answers( true );
@@ -2108,8 +2113,26 @@ $sponsors_post                   = browser_post( form_of( draw_settings( 'sponso
 $sponsors_post['sponsor_notify'] = array( 'zoe@program.example', 'zoe@program.example, not an address' );
 press( $sponsors_post );
 
-ck( 'the interest mail\'s list is held to the same rule: an address twice is kept once, and what is not an address is dropped',
-    WPCPM_Settings::get()['sponsor_notify'], 'zoe@program.example' );
+ck( 'the interest mail\'s list is held to the same rule: what is not an address refuses the list, which stays as stored',
+    array( WPCPM_Settings::get()['sponsor_notify'], array_keys( WPCPM_Settings::last_refused() ) ), array( $stored_now['sponsor_notify'], array( 'sponsor_notify' ) ) );
+
+store( $stored_now, $live );
+airtable_answers( true );
+$institutions_post = browser_post( form_of( draw_settings( 'institutions' ) ) );
+$institutions_post['report_notify'] = array( 'pat@program.example' );
+$institutions_post['report_notify' . WPCPM_Settings::EVERYONE_SUFFIX] = '1';
+press( $institutions_post );
+
+ck( 'a Save with the box above a list ticked writes the list empty, the people ticked below not read (ruling of 6 November 2026)',
+    array( WPCPM_Settings::get()['report_notify'], WPCPM_Settings::last_refused() ), array( '', array() ) );
+
+store( $stored_now, $live );
+airtable_answers( true );
+$sponsors_post                   = browser_post( form_of( draw_settings( 'sponsors' ) ) );
+$sponsors_post['sponsor_notify'] = array( 'zoe@program.example', 'zoe@program.example' );
+press( $sponsors_post );
+
+ck( 'and an address ticked and typed is kept once', WPCPM_Settings::get()['sponsor_notify'], 'zoe@program.example' );
 
 /* ---- a Save that changes nothing writes nothing ------------------------------------ */
 
```

`bin/test-duplicate-finder.php`'s stand-in for the settings:

```diff
diff --git a/bin/test-duplicate-finder.php b/bin/test-duplicate-finder.php
index 82731208..7ac7fb4a 100644
--- a/bin/test-duplicate-finder.php
+++ b/bin/test-duplicate-finder.php
@@ -216,6 +216,8 @@ class WPCPM_Airtable {
 class WPCPM_Settings {
 	public static function get() { return array( 'base_id' => 'appTEST', 'students_table' => 'tblSTUDENTS', 'reports_table' => 'tblREPORTS', 'feedback_table' => 'tblFEEDBACK', 'duplicate_delete_enabled' => $GLOBALS['switch'] ); }
 	public static function is_connected() { return $GLOBALS['connected']; }
+	// The fingerprints a settings form carries (1.123.58): none here, the screen's own suite reads them.
+	public static function drawn_fields( $scope ) { return ''; }
 }
 class WPCPM_Students_Sync {
 	const EVERY_THREE_HOURS = 'wpcpm_three_hours';
```

Then, specified rather than proved, in `bin/test-settings.php` after the new section (write them in its `ck()` style):
- "a refused setting's row is drawn again from the typing, marked aria-invalid, with its sentence beside it": store `mentor_course_link` as an https link, press the people tab with `http://learn.example.test/plain/`, draw the tab; the `wpcpm-mentor_course_link` input's value is the typed `http://learn.example.test/plain/`, it carries `aria-invalid="true" aria-describedby="wpcpm-mentor_course_link-problem"`, and `<p class="wpcpm-field__problem" id="wpcpm-mentor_course_link-problem">Give a link that starts with https://. The link saved before was kept.</p>` follows it.
- "the tab opens with the summary, linking the refused setting by its label": the drawn tab holds, before the first section, `WPCPM_Form_Errors::summary()` of that one problem with the class `notice notice-error`, its link `<a href="#wpcpm-mentor_course_link">Mentors' course invitation link</a>`, and the outcome notice reads `The settings marked below were not saved. Everything else was saved.` as `notice-warning`.
- "each Who is mailed row draws the box above its list, ticked while the stored list is empty and not otherwise": draw the institutions tab with `agreement_notify` `''` and `report_notify` `pat@program.example`; the page holds `<input type="checkbox" name="agreement_notify_everyone" value="1" checked="checked"` and the same for `report_notify_everyone` without `checked`, each labeled `Mail every Administrator account instead of the people below`, before its row's first box.
- "a reviewer list refused draws its typing back on the Other addresses line, the bad entries named": press the institutions tab with `agreement_notify` as `array( 'ana@program.example', 'typo@, also-wrong' )`; the drawn row ticks Ana, its Other addresses input (id `wpcpm-agreement_notify-other`) holds `typo@, also-wrong`, marked, and the sentence names `"typo@", "also-wrong"`.
- "a kept setting is named in a warning above the tab and marked at its field": after the conflict case of the new section, draw the people tab; a summary with the class `notice notice-warning` and the heading `One setting changed since you opened this page` links `#wpcpm-mentor_course_link`, and the row carries the kept sentence as its problem, its value the stored `https://learn.example.test/course/two/`.
- "the Emails tool's contact address and the checker's webhook are refused through the same map": press `tool:emails` with `mail_contact` `name@` and `tool:mentor-status-checker` with a webhook that is not Slack's (built from two strings, so no key shape is on one line); each keeps the stored value, `last_refused()` names it with its sentence above, the outcome is `saved-except`, and neither `emails-contact-refused` nor `checker-webhook-refused` is flashed any more (replace the three old checks at about `:649`, `:653`, `:668`, and the one in `bin/test-emails-templates.php` at about `:1078`, with these).

`bin/test-cli.php` (its stand-in `WPCPM_Settings` gains `get()`, `get_value()`, `defaults()`, `patch( $changes, $dry )`, `last_refused()` and the four `masked_*()` readers, over `$GLOBALS['settings']`):
- "setting get prints a setting, a boolean as on or off, a list joined, a secret masked"; "and refuses a key there is none of with the sentence";
- "setting set <key> everyone writes the list empty through patch() with the box, and is refused for any other key with the sentence that lists the four";
- "setting set writes through patch(), and says from what to what"; "set to the value stored writes nothing and says so"; "set --dry-run hands patch() the dry flag and says what would change";
- "a refused value is not written, and the refusal's sentence is the CLI's error"; "a status list a live track holds is left, and the CLI says why".

- [ ] **Step 2: Run them to verify they fail**

```text
$ php bin/test-settings.php
PHP Fatal error:  Uncaught Error: Call to undefined method WPCPM_Settings::last_refused()
$ php bin/test-settings-choices.php
PHP Fatal error:  Uncaught Error: Undefined constant WPCPM_Settings::FIELD_DRAWN
$ php bin/test-cli.php
FAIL setting get prints a setting, a boolean as on or off, a list joined, a secret masked
```

- [ ] **Step 3: The drawn-value save and the refusals**

`includes/class-wpcpm-settings.php`:

```diff
diff --git a/includes/class-wpcpm-settings.php b/includes/class-wpcpm-settings.php
index 55e2f6be..cc563989 100644
--- a/includes/class-wpcpm-settings.php
+++ b/includes/class-wpcpm-settings.php
@@ -30,6 +30,44 @@ class WPCPM_Settings {
 	 */
 	const FIELD_DRAWN_STATUSES = 'student_statuses_drawn';
 
+	/**
+	 * The field a settings form posts what it drew under: each setting of its scope and the
+	 * fingerprint of the value the page was drawn with (`drawn_fields()`), so a save writes only what
+	 * the person changed and keeps what changed elsewhere since (1.123.58). A form field, never a
+	 * stored setting.
+	 */
+	const FIELD_DRAWN = 'wpcpm_drawn';
+
+	/**
+	 * The four Who is mailed lists, each of which, left empty, mails every Administrator account
+	 * (`addresses()`'s callers fall back to them).
+	 */
+	const NOTIFY_LISTS = array( 'agreement_notify', 'report_notify', 'sponsor_notify', 'mentor_notify' );
+
+	/**
+	 * What a Who is mailed list's box posts under, after the list's own name: ticked, the list is
+	 * written empty, which mails every Administrator account, whatever the list below it holds
+	 * (1.123.58). A form field, never a stored setting.
+	 */
+	const EVERYONE_SUFFIX = '_everyone';
+
+	/**
+	 * Why the last write refused each setting it did not write, by setting: a value its rule would
+	 * have saved as blank, or a list it would have emptied (`write()`, 1.123.58). Read with
+	 * `last_refused()`.
+	 *
+	 * @var array<string, string>
+	 */
+	private static $refused = array();
+
+	/**
+	 * The settings the last write kept as stored because they changed since the page was drawn and
+	 * the person changed them too (`write()`, 1.123.58). Read with `last_kept()`.
+	 *
+	 * @var string[]
+	 */
+	private static $kept = array();
+
 	/**
 	 * Bump this when a *saved* option has to be migrated rather than merely defaulted.
 	 *
@@ -496,6 +534,9 @@ class WPCPM_Settings {
 	public static function save( array $input, $scope = '' ) {
 		$scope = (string) $scope;
 		$keys  = self::scope_keys( $scope );
+		$drawn = isset( $input[ self::FIELD_DRAWN ] ) ? self::drawn_from( $input[ self::FIELD_DRAWN ] ) : null;
+
+		unset( $input[ self::FIELD_DRAWN ] );
 
 		if ( null === $keys ) {
 			return self::get();
@@ -513,7 +554,140 @@ class WPCPM_Settings {
 			}
 		}
 
-		return self::write( $input, $keys );
+		return self::write( $input, $keys, $drawn );
+	}
+
+	/**
+	 * A setting's value as a fingerprint: what a form posts for the value it was drawn with
+	 * (`drawn_fields()`), and what a save compares the value it would write, and the value stored
+	 * now, with (1.123.58).
+	 *
+	 * The value is read as text before it is hashed, a boolean as '1' or '', null as '', and a list
+	 * item by item with its keys, so a value read back from the database, where an integer can come
+	 * back as a numeral, gives the fingerprint the value written gave. A fingerprint, not the value,
+	 * so a secret the page shows masked never reaches the page.
+	 *
+	 * @param mixed $value A setting's value.
+	 * @return string 32 hexadecimal characters.
+	 */
+	public static function fingerprint( $value ) {
+		return md5( serialize( self::as_text( $value ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Hashed, never stored or read back.
+	}
+
+	/**
+	 * A value with every scalar in it as text, for `fingerprint()`.
+	 *
+	 * @param mixed $value A setting's value.
+	 * @return string|array
+	 */
+	private static function as_text( $value ) {
+		if ( is_array( $value ) ) {
+			$text = array();
+
+			foreach ( $value as $key => $item ) {
+				$text[ (string) $key ] = self::as_text( $item );
+			}
+
+			return $text;
+		}
+
+		if ( is_bool( $value ) ) {
+			return $value ? '1' : '';
+		}
+
+		return is_scalar( $value ) ? (string) $value : '';
+	}
+
+	/**
+	 * The hidden fields a settings form carries: for every setting of its scope, the fingerprint of
+	 * the value the page is drawn with (1.123.58). `WPCPM_Settings_Screen::open_settings_form()`
+	 * prints them, so a tab's form and a tool's Settings section carry them alike.
+	 *
+	 * One field a setting rather than one fingerprint of the whole tab: a fingerprint of the tab can
+	 * say only that something changed since the page was drawn, and a save would then have to refuse
+	 * the whole tab or write it whole; a fingerprint a setting says which setting the person changed
+	 * and which changed elsewhere, so the save writes the first and keeps the second. The price is
+	 * forty bytes a setting, about a kilobyte on the largest tab.
+	 *
+	 * @param string $scope A key of `scopes()`.
+	 * @return string The fields' markup, or '' for a scope this version does not have.
+	 */
+	public static function drawn_fields( $scope ) {
+		$keys = self::scope_keys( (string) $scope );
+
+		if ( null === $keys || '' === (string) $scope ) {
+			return '';
+		}
+
+		$settings = self::get();
+		$fields   = '';
+
+		foreach ( $keys as $key ) {
+			$fields .= sprintf(
+				'<input type="hidden" name="%1$s[%2$s]" value="%3$s" />',
+				esc_attr( self::FIELD_DRAWN ),
+				esc_attr( $key ),
+				esc_attr( self::fingerprint( array_key_exists( $key, $settings ) ? $settings[ $key ] : null ) )
+			);
+		}
+
+		return $fields;
+	}
+
+	/**
+	 * The fingerprints a form posted, by setting: only a known setting's, and only a fingerprint's
+	 * shape, so a hand-made post can do no more than a form posting no fingerprint at all.
+	 *
+	 * @param mixed $posted What the form posted under `FIELD_DRAWN`.
+	 * @return array<string, string>
+	 */
+	public static function drawn_from( $posted ) {
+		$drawn    = array();
+		$defaults = self::defaults();
+
+		if ( ! is_array( $posted ) ) {
+			return $drawn;
+		}
+
+		foreach ( $posted as $key => $print ) {
+			if ( is_string( $key ) && array_key_exists( $key, $defaults ) && is_string( $print ) && 1 === preg_match( '/^[a-f0-9]{32}$/', $print ) ) {
+				$drawn[ $key ] = $print;
+			}
+		}
+
+		return $drawn;
+	}
+
+	/**
+	 * Why the last save or patch did not write each setting it refused, by setting (1.123.58).
+	 *
+	 * @return array<string, string>
+	 */
+	public static function last_refused() {
+		return self::$refused;
+	}
+
+	/**
+	 * The settings the last save kept as stored because they changed since the page was drawn
+	 * (1.123.58).
+	 *
+	 * @return string[]
+	 */
+	public static function last_kept() {
+		return self::$kept;
+	}
+
+	/**
+	 * Refuse one setting in the write under way: its stored value stays, and the sentence says why.
+	 *
+	 * @param array  $clean    The settings the write builds, by reference.
+	 * @param array  $current  The settings as stored.
+	 * @param string $key      The setting.
+	 * @param string $sentence Why.
+	 */
+	private static function refuse( array &$clean, array $current, $key, $sentence ) {
+		$clean[ $key ]         = array_key_exists( $key, $current ) ? $current[ $key ] : self::defaults()[ $key ];
+		self::$refused[ $key ] = (string) $sentence;
 	}
 
 	/**
@@ -562,14 +736,18 @@ class WPCPM_Settings {
 	 * Sanitize and store the settings a save or a patch reads, and leave every other setting as
 	 * stored.
 	 *
-	 * @param array    $input Raw input, typically from $_POST.
-	 * @param string[] $keys  The settings this write reads.
+	 * @param array      $input Raw input, typically from $_POST.
+	 * @param string[]   $keys  The settings this write reads.
+	 * @param array|null $drawn The fingerprints the form was drawn with, by setting (`drawn_from()`),
+	 *                          or null for a write that carries none: a patch, or a form from before.
 	 * @return array The settings as written.
 	 */
-	private static function write( array $input, array $keys ) {
-		$current = self::get();
-		$reads   = array_fill_keys( $keys, true );
-		$clean   = $current;
+	private static function write( array $input, array $keys, $drawn = null ) {
+		$current       = self::get();
+		$reads         = array_fill_keys( $keys, true );
+		$clean         = $current;
+		self::$refused = array();
+		self::$kept    = array();
 
 		// An empty token field means "leave the stored token alone" - the UI only
 		// ever renders a masked placeholder, so blank must not wipe the secret.
@@ -718,8 +896,17 @@ class WPCPM_Settings {
 			$host = $url ? strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) : '';
 
 			// Google only, and https only: this value is rendered as a link on an admin screen,
-			// and a free-text URL field there is a redirect waiting to happen.
-			$clean['agreement_doc_url'] = in_array( $host, array( 'docs.google.com', 'drive.google.com' ), true ) ? $url : '';
+			// and a free-text URL field there is a redirect waiting to happen. A link typed that the
+			// rule would save as none is refused, the stored link kept (SET-03, 1.123.58).
+			$typed = is_string( $input['agreement_doc_url'] ) ? trim( wp_unslash( $input['agreement_doc_url'] ) ) : '';
+
+			if ( in_array( $host, array( 'docs.google.com', 'drive.google.com' ), true ) ) {
+				$clean['agreement_doc_url'] = $url;
+			} elseif ( '' === $typed ) {
+				$clean['agreement_doc_url'] = '';
+			} else {
+				self::refuse( $clean, $current, 'agreement_doc_url', __( 'Give a link that starts with https://docs.google.com/ or https://drive.google.com/. The link saved before was kept.', 'wpcredits-program-manager' ) );
+			}
 		}
 
 		// Who hears about an agreement upload, a drafted report or a sponsor's interest (`addresses()`).
@@ -727,13 +914,47 @@ class WPCPM_Settings {
 		// as the boxes ticked beside its Other addresses line (`agreement_notify[]` and so on), every
 		// entry split the way the text is, so both write the same list for the same addresses, and the
 		// boxes keep the order the list is stored in, as the status lists do.
-		foreach ( array( 'agreement_notify', 'report_notify', 'sponsor_notify', 'mentor_notify' ) as $notify_key ) {
+		foreach ( self::NOTIFY_LISTS as $notify_key ) {
+			// The box above the list, "Mail every Administrator account instead of the people below":
+			// ticked, the list is written empty and what it holds is not read (1.123.58).
+			if ( ! empty( $input[ $notify_key . self::EVERYONE_SUFFIX ] ) ) {
+				$clean[ $notify_key ] = '';
+
+				continue;
+			}
+
 			if ( ! isset( $input[ $notify_key ] ) ) {
 				continue;
 			}
 
 			$raw       = wp_unslash( $input[ $notify_key ] );
 			$addresses = self::addresses( $raw );
+			$wrong     = self::not_addresses( $raw );
+
+			// A typed entry that is no address refuses the list, rather than the list saved without it:
+			// with one address typed wrong, the list would be empty, which mails every Administrator
+			// account (SET-02, 1.123.58). And a list the save would empty is refused too, for the same
+			// reason: an empty list is never what a person meant by unticking the last box.
+			if ( array() !== $wrong ) {
+				self::refuse(
+					$clean,
+					$current,
+					$notify_key,
+					sprintf(
+						/* translators: %s: the entries that are not email addresses, in quotes, separated by commas. */
+						_n( '%s is not an email address, so the list was not saved. Correct it or remove it, then save again.', '%s are not email addresses, so the list was not saved. Correct them or remove them, then save again.', count( $wrong ), 'wpcredits-program-manager' ),
+						'"' . implode( '", "', $wrong ) . '"'
+					)
+				);
+
+				continue;
+			}
+
+			if ( array() === $addresses ) {
+				self::refuse( $clean, $current, $notify_key, __( 'Name at least one address, or tick the box above to mail every Administrator account.', 'wpcredits-program-manager' ) );
+
+				continue;
+			}
 
 			if ( is_array( $raw ) ) {
 				$addresses = self::kept_in_stored_order( $addresses, self::addresses( isset( $current[ $notify_key ] ) ? (string) $current[ $notify_key ] : '' ) );
@@ -746,9 +967,16 @@ class WPCPM_Settings {
 		// https link or none. It goes to a stranger in an email the site sends, so a link that is not
 		// https, or not a link, is saved as none rather than mailed.
 		if ( isset( $input['mentor_course_link'] ) ) {
-			$link = is_string( $input['mentor_course_link'] ) ? esc_url_raw( trim( wp_unslash( $input['mentor_course_link'] ) ), array( 'https' ) ) : '';
+			$typed = is_string( $input['mentor_course_link'] ) ? trim( wp_unslash( $input['mentor_course_link'] ) ) : '';
+			$link  = '' !== $typed ? esc_url_raw( $typed, array( 'https' ) ) : '';
 
-			$clean['mentor_course_link'] = is_string( $link ) && 0 === strpos( $link, 'https://' ) ? $link : '';
+			if ( is_string( $link ) && 0 === strpos( $link, 'https://' ) ) {
+				$clean['mentor_course_link'] = $link;
+			} elseif ( '' === $typed ) {
+				$clean['mentor_course_link'] = '';
+			} else {
+				self::refuse( $clean, $current, 'mentor_course_link', __( 'Give a link that starts with https://. The link saved before was kept.', 'wpcredits-program-manager' ) );
+			}
 		}
 
 		// The one connecting address whose forwarded header the application form believes.
@@ -761,7 +989,11 @@ class WPCPM_Settings {
 		if ( isset( $input['application_trusted_proxy'] ) ) {
 			$proxy = is_string( $input['application_trusted_proxy'] ) ? trim( wp_unslash( $input['application_trusted_proxy'] ) ) : '';
 
-			$clean['application_trusted_proxy'] = ( '' !== $proxy && false !== filter_var( $proxy, FILTER_VALIDATE_IP ) ) ? $proxy : '';
+			if ( '' === $proxy || false !== filter_var( $proxy, FILTER_VALIDATE_IP ) ) {
+				$clean['application_trusted_proxy'] = $proxy;
+			} else {
+				self::refuse( $clean, $current, 'application_trusted_proxy', __( 'Give one IP address, such as 192.0.2.10, or leave the box empty. The address saved before was kept.', 'wpcredits-program-manager' ) );
+			}
 		}
 
 		// Guarded like `handbook_enabled`, unlike the checkboxes above: a write can be handed
@@ -881,6 +1113,37 @@ class WPCPM_Settings {
 		// stored in a setting this save or patch does not read is its own tab's to put right, and
 		// a notice about it after another tab's Save would name a setting the manager never
 		// touched.
+		// A page drawn earlier writes only what the person changed on it (SET-01, LOG-03, 1.123.58).
+		// The form posted, for every setting of its scope, the fingerprint of the value it was drawn
+		// with. A setting posted back as drawn is not the person's change, so the stored value stays,
+		// whatever another tab, another Administrator or the command line made it since. A setting the
+		// person changed is written, unless the stored value changed since the page was drawn too, to
+		// something else: then the stored value stays and the setting is named (`last_kept()`). A
+		// setting the form drew no fingerprint for is written as before; a refused one is already back
+		// to its stored value.
+		if ( is_array( $drawn ) ) {
+			foreach ( $keys as $key ) {
+				if ( ! isset( $drawn[ $key ] ) || isset( self::$refused[ $key ] ) || ! array_key_exists( $key, $clean ) ) {
+					continue;
+				}
+
+				$mine = self::fingerprint( $clean[ $key ] );
+
+				if ( $mine === $drawn[ $key ] ) {
+					$clean[ $key ] = $current[ $key ];
+
+					continue;
+				}
+
+				$stored = self::fingerprint( $current[ $key ] );
+
+				if ( $stored !== $drawn[ $key ] && $stored !== $mine ) {
+					$clean[ $key ] = $current[ $key ];
+					self::$kept[]  = $key;
+				}
+			}
+		}
+
 		$restored = array();
 
 		foreach ( array_keys( self::never_blank() ) as $key ) {
@@ -1118,6 +1381,33 @@ class WPCPM_Settings {
 		return $list;
 	}
 
+	/**
+	 * The entries of a typed list of addresses, split as `addresses()` splits them, that are not
+	 * addresses, as typed (SET-02, 1.123.58).
+	 *
+	 * @param string|string[] $raw The text input's text, or the boxes and the Other addresses line.
+	 * @return string[]
+	 */
+	private static function not_addresses( $raw ) {
+		$wrong = array();
+
+		foreach ( is_array( $raw ) ? $raw : array( (string) $raw ) as $entry ) {
+			if ( ! is_string( $entry ) ) {
+				continue;
+			}
+
+			foreach ( preg_split( '/[\s,]+/', $entry, -1, PREG_SPLIT_NO_EMPTY ) as $address ) {
+				$clean = sanitize_email( strtolower( trim( (string) $address ) ) );
+
+				if ( '' === $clean || ! is_email( $clean ) ) {
+					$wrong[] = sanitize_text_field( (string) $address );
+				}
+			}
+		}
+
+		return array_values( array_unique( $wrong ) );
+	}
+
 	/**
 	 * The addresses a reviewer list holds: one per line or comma-separated, whichever the manager
 	 * typed, or an array of such entries, as the boxes and the Other addresses line post them.
```

`includes/class-wpcpm-settings-screen.php` (the fingerprints, the keeping and the `saved-except` outcome):

```diff
diff --git a/includes/class-wpcpm-settings-screen.php b/includes/class-wpcpm-settings-screen.php
index 58240d1a..812847b8 100644
--- a/includes/class-wpcpm-settings-screen.php
+++ b/includes/class-wpcpm-settings-screen.php
@@ -387,6 +387,19 @@ class WPCPM_Settings_Screen {
 			$input[ WPCPM_Settings::FIELD_DRAWN_STATUSES ] = wp_unslash( $_POST[ WPCPM_Settings::FIELD_DRAWN_STATUSES ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in WPCPM_Settings::student_statuses_from().
 		}
 
+		// The box above each Who is mailed list of the scope, which is no setting either (1.123.58).
+		foreach ( array_intersect( WPCPM_Settings::NOTIFY_LISTS, $keys ) as $list ) {
+			if ( ! empty( $_POST[ $list . WPCPM_Settings::EVERYONE_SUFFIX ] ) ) {
+				$input[ $list . WPCPM_Settings::EVERYONE_SUFFIX ] = true;
+			}
+		}
+
+		// What the page was drawn with, a fingerprint a setting (1.123.58): the save writes only the
+		// settings the person changed, and keeps one that changed elsewhere since.
+		if ( isset( $_POST[ WPCPM_Settings::FIELD_DRAWN ] ) ) {
+			$input[ WPCPM_Settings::FIELD_DRAWN ] = WPCPM_Settings::drawn_from( wp_unslash( $_POST[ WPCPM_Settings::FIELD_DRAWN ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each key checked against the defaults and each value against a fingerprint's shape in drawn_from().
+		}
+
 		// A change to the list that would take the status of a track the site runs from its
 		// definition out of it is not made: the next students sync would take the Student role from
 		// everybody on the track, and the status leaves once the track is off the live site (the
@@ -414,6 +427,20 @@ class WPCPM_Settings_Screen {
 
 		$saved = WPCPM_Settings::save( $input, $scope );
 
+		// What the save did not write, for the page it goes back to (1.123.58): each refused setting's
+		// typing and its sentence, drawn again in its row and summarized at the top of the tab
+		// (`WPCPM_Form_Errors`), and the settings kept as stored because they changed elsewhere since
+		// the page was drawn, named in a warning.
+		$refused = WPCPM_Settings::last_refused();
+
+		if ( array() !== $refused ) {
+			WPCPM_Flash::keep( self::kept_form( $scope ), array_intersect_key( $input, $refused ), $refused );
+		}
+
+		if ( array() !== WPCPM_Settings::last_kept() ) {
+			WPCPM_Flash::set( 'settings-kept', WPCPM_Settings::last_kept() );
+		}
+
 		// "Currently mentoring" and the past statuses are rules every published track was compiled
 		// against, so a save that changes them would otherwise leave the live tracks, and the list
 		// of what the last compile left out, answering for the settings as they were (decision 14).
@@ -437,13 +464,24 @@ class WPCPM_Settings_Screen {
 		}
 
 		// Back to where the form was drawn, a tab or a tool's own screen, with a notice naming what
-		// was saved.
-		self::flash_outcome( 'saved', $scope );
+		// was saved, or, when a setting was refused, a warning that says the rest was.
+		self::flash_outcome( array() === $refused ? 'saved' : 'saved-except', $scope );
 
 		wp_safe_redirect( self::return_url( $scope ) );
 		exit;
 	}
 
+	/**
+	 * The name a settings form's refused typing is kept under (`WPCPM_Flash::keep()`): one per scope,
+	 * so a tab's refusal comes back on that tab and a tool's on that tool's screen (1.123.58).
+	 *
+	 * @param string $scope A key of `WPCPM_Settings::scopes()`.
+	 * @return string
+	 */
+	public static function kept_form( $scope ) {
+		return 'settings:' . (string) $scope;
+	}
+
 	/**
 	 * The button on the Connection tab that reads the Airtable lists again: the bases, the statuses and
 	 * the stages are kept a day and the tables and the columns fifteen minutes, so a status or a stage
@@ -619,10 +657,11 @@ class WPCPM_Settings_Screen {
 		}
 
 		return array(
-			'saved'       => array( 'success', $saved ),
-			'refused'     => array( 'error', __( 'Nothing was saved: the form asked to save settings this screen does not have. Reload the page and save again.', 'wpcredits-program-manager' ) ),
-			'log-cleared' => array( 'success', __( 'The mail log is empty.', 'wpcredits-program-manager' ) ),
-			'lists-read'  => array( 'success', __( 'The lists were read again.', 'wpcredits-program-manager' ) ),
+			'saved'        => array( 'success', $saved ),
+			'saved-except' => array( 'warning', __( 'The settings marked below were not saved. Everything else was saved.', 'wpcredits-program-manager' ) ),
+			'refused'      => array( 'error', __( 'Nothing was saved: the form asked to save settings this screen does not have. Reload the page and save again.', 'wpcredits-program-manager' ) ),
+			'log-cleared'  => array( 'success', __( 'The mail log is empty.', 'wpcredits-program-manager' ) ),
+			'lists-read'   => array( 'success', __( 'The lists were read again.', 'wpcredits-program-manager' ) ),
 		);
 	}
 
@@ -811,6 +850,10 @@ class WPCPM_Settings_Screen {
 			esc_attr( self::SETTINGS_TAB_FIELD ),
 			esc_attr( $scope )
 		);
+
+		// The fingerprint of every value the form is drawn with, so its Save writes only what the
+		// person changed (`WPCPM_Settings::save()`, 1.123.58).
+		echo WPCPM_Settings::drawn_fields( $scope ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hidden inputs, every attribute escaped in drawn_fields().
 	}
 
 	/**
```

Then, specified:
- **`mail_contact` and `checker_slack_webhook`** in `write()`: in place of `WPCPM_Flash::set( 'emails-contact-refused', true )` and `WPCPM_Flash::set( 'checker-webhook-refused', true )`, `self::refuse( $clean, $current, '<key>', <the sentence from the table> )`; delete the two notices that read those flashes in `class-wpcpm-emails.php` and `class-wpcpm-mentor-checker.php`.
- **The box** in `reviewers_row()`: a new parameter `$everyone_box = false` (true from the four Who is mailed calls in `class-wpcpm-settings-screen.php`, about `:1413`, `:1582`, `:1631`, `:1732`); when true, the row opens its cell with `<p><label><input type="checkbox" name="<name>_everyone" value="1"<checked when the value is empty>> Mail every Administrator account instead of the people below</label></p>`, before the managers' boxes. The row's existing `$nobody` warning stays for an empty list.
- **`patch( array $changes, $dry = false )`**: `write()` gains a fourth parameter `$dry`; when true, it returns `$clean` before `update_option()` and does nothing after (no flash, no version stamp, no cron sync), and `patch()` skips the compile. `last_refused()` is filled either way.
- **The rows** (`class-wpcpm-settings-rows.php`). `mark( array $problems, array $values )` stores, for the draw that follows, each refused or kept setting's sentence and its kept typing; `reset_marks()` clears both and the labels. Every row function (`number_row`, `text_row`, `select_row`, `checklist_row`, `reviewers_row`, `landing_row`, and any other that prints a control under a setting's name) records `self::$labels[ $name ] = array( 'id' => <the control's id>, 'label' => $label )`, and, when the setting is marked: draws its value from the kept typing when there is one (a reviewer row puts the typed text on its Other addresses line and ticks only the managers the typing names), prints `WPCPM_Form_Errors::attrs( <id>, <sentence>, <the hint's id or ''> )` on the control, and `WPCPM_Form_Errors::problem( <id>, <sentence> )` straight after it, before the row's help. The control's id is `wpcpm-<name>`; the reviewer row's Other addresses input is `wpcpm-<name>-other` (give it that id if it has none) and is the control a refused list is marked on.
- **`render_tab_form()`** and **`WPCPM_Tool::render_settings()`**: take `$kept = WPCPM_Flash::kept( WPCPM_Settings_Screen::kept_form( $scope ) )` and `$changed = WPCPM_Flash::take( 'settings-kept' )`; call `WPCPM_Settings_Rows::mark()` with the refused problems and values, and the kept settings with the kept sentence (no value: they draw what is stored); draw the form's sections into a buffer; print, inside the form and before the buffer, `WPCPM_Form_Errors::summary( $kept['problems'], WPCPM_Settings_Rows::drawn_labels(), array( 'class' => 'notice notice-error' ) )`, then, for the kept settings, `WPCPM_Form_Errors::summary( <each kept setting => the kept sentence>, WPCPM_Settings_Rows::drawn_labels(), array( 'class' => 'notice notice-warning', 'heading' => <the warning's heading> ) )`; then the buffer; then `reset_marks()`. A problem for a setting the form did not draw is listed without a link, as `summary()` does.
- **`render_outcome_notice()`** prints `saved-except` as `notice-warning`, as `settings_messages()` gives it.
- **The CLI** (`class-wpcpm-cli.php`): `public function setting( $args, $assoc_args )`, with the docblock's `## OPTIONS` naming `<action>` (`get` or `set`), `<key>`, `[<value>]`, `[--dry-run]`, and `## EXAMPLES` `wp wpcredits setting get mentor_applications_enabled`, `wp wpcredits setting set mentor_lapse_days 120 --dry-run`. `get` reads `WPCPM_Settings::get_value()`; `set` takes the literal `everyone` for a key of `NOTIFY_LISTS` as `array( $key => '', $key . WPCPM_Settings::EVERYONE_SUFFIX => true )` and refuses it for any other key; it parses any other value by the default's type, compares with the stored value, then calls `WPCPM_Settings::patch( array( $key => $value ), $dry )`, reads `last_refused()` and compares the list it gets back for the two status lists. Every sentence is from the table above, through `WP_CLI::success()`, `WP_CLI::log()` or `WP_CLI::error()`.

- [ ] **Step 4: Run them to verify they pass**

```text
$ php bin/test-settings.php
ok   agreement_notify keeps the addresses, each once, in the order typed
ok   and refuses a list with an entry that is no address: the stored list stays, and the sentence names each wrong entry as typed
ok   two wrong entries are each named, in quotes
ok   and a single wrong entry in the singular
ok   a list the save would empty is refused too, unless its box says every Administrator account
ok   the box "Mail every Administrator account instead of the people below" writes the list empty, whatever the list below it holds, and refuses nothing
ok   and with the box ticked a list that is no address is not read at all
ok   an empty list with the box unticked is refused even when the stored list is empty, the stored list kept
ok   a patch carrying the box writes the list empty, as wp wpcredits setting set <key> everyone does
=== A page drawn earlier writes only what the person changed (1.123.58) ===
ok   the form carries a fingerprint for every setting of its scope, and no other
ok   and a fingerprint is no setting's value: a secret the page masks never reaches it
ok   a scope this version does not have draws none
ok   a switch changed elsewhere since the page was drawn is not switched back by a Save of that page
ok   a setting the person changed and that changed elsewhere since too keeps the stored value, and is named
ok   one changed to the same value in both places is no conflict
ok   with nothing changed elsewhere, the person's change is written
ok   a form that carries no fingerprints is saved as before, every setting as posted
ok   a refused setting is refused, not named as changed elsewhere, and its typing and sentence wait for the tab
ok   the fingerprint reads a value as text: an integer and its numeral, true and "1", false, null and "" are alike
ok   a posted fingerprint is taken only for a setting there is, and only in a fingerprint's shape
ok   a token rotated elsewhere since is kept by a Save that posts its mask, with no warning
ALL PASS
$ php bin/test-settings-choices.php
ok   a Save with the box above a list ticked writes the list empty, the people ticked below not read (ruling of 6 November 2026)
All passed.
$ php bin/test-duplicate-finder.php
ALL PASS (85 checks)
$ php bin/test-cli.php
ALL PASS
```

(The quoted run is the proved part; the specified checks join it.)

- [ ] **Step 5: The checks every task ends with**

As the block says. `php bin/test-emails-templates.php`, `php bin/test-emails-screen.php` and `php bin/test-mentor-checker.php` are among the battery and read the two notices this task removes: each must pass with its checks moved to the marked field.

- [ ] **Step 6: Commit**

```bash
git add includes/class-wpcpm-settings.php includes/class-wpcpm-settings-screen.php includes/class-wpcpm-settings-rows.php includes/tools/class-wpcpm-tool.php includes/tools/class-wpcpm-emails.php includes/tools/class-wpcpm-mentor-checker.php includes/class-wpcpm-cli.php bin/test-settings.php bin/test-settings-choices.php bin/test-duplicate-finder.php bin/test-emails-templates.php bin/test-cli.php
git commit -m "Scan release 1: Settings write only what changed, refuse a value that would be blanked, and mark it at the field; wp wpcredits setting" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 3: The outcome inside its card, and the invitations card that polls instead of reloading (shared mechanisms and their first two pages)

Findings: SDB-01, SDB-02 (the card-targeted outcome, on the Sponsor Dashboard and the Administrator Dashboard), ADM-01, LOG-04 (the 30-second reload). Scope **M**. The card printer, the progress answer and the poller are proved with their suites; the wiring into the two dashboards is specified.

**How the outcome finds its card.** A handler that redirects to `#<anchor>` names the card with `WPCPM_Dashboard_Card::target( <anchor> )` beside the outcome it flashes on its own channel. The page takes both, holds the sentence for the card (`hold()`), prints `WPCPM_Dashboard_Card::SLOT` where its outcome used to go, and draws its cards; `open()` prints the held sentence at the top of the named card's body and draws that card open; `settle()`, run on the page's buffer, puts the sentence in the slot when no card printed it (an outcome with no card, or one whose card the page does not draw), and otherwise empties the slot. A success is `role="status"`, an error `role="alert"`, info and warning `role="status"`; each is `tabindex="-1"` with `data-wpcpm-card-message`, which `forms.js` (Task 1) focuses on load. Every outcome carries its tone as `wpcpm-dashboard__message--<tone>` and `is-<tone>`: the plugin's `sponsor.css` and `administrator.css` already color the first, and the theme the second on the Institution Dashboard.

**How the invitations card polls.** While a bulk run sends, the card draws a panel marked `data-wpcpm-invite-poll` with the action, its nonce, the interval and the sentence it stops with; `assets/js/admin.js` asks `wp_ajax_wpcpm_invite_progress` every ten seconds and moves the count, the bar and the line under it in place. The panel is not a live region, so the count is not read out every ten seconds: a screen reader is told `Invitations sent: N of M.` through `wp.a11y.speak()` as the run passes each quarter and once at the finish, and at the finish the card prints, where the line under the bar was, `This run has finished: every invitation in it has been sent or passed over.` (the panel's `data-finished`), for the eye. The poll's request spawns WordPress's cron as the reload's did, which is what moves the queue. A refused answer (an expired nonce, a signed-out session) and five failed requests in a row stop the panel with `The count stopped updating.`; a hidden tab asks nothing until it is shown. The card prints `To see the latest count, refresh the page.` (the last three words a link to the page), for a page with scripts off.

**Files:**
- Modify: `includes/class-wpcpm-dashboard-card.php` (`TARGET`, `SLOT`, `TONES`, `$held`; `target()`, `targeted()`, `hold()`, `forget()`, `message()`, `settle()`; `open()` prints the held outcome)
- Modify: `includes/modules/class-wpcpm-sponsors-dashboard.php` (`leave()` at about `:391`, `render()` at about `:459`, `render_message()` at about `:923` becomes `message_of()`)
- Modify: `includes/modules/class-wpcpm-administrators-dashboard.php` (`render()` at about `:248`, `render_messages()` at about `:413`, `render_report_message()` at about `:491`), `includes/class-wpcpm-return.php` (`url()` at about `:66`)
- Modify: `includes/class-wpcpm-mail.php` (`ACTION_PROGRESS`, `PROGRESS_POLL`; `init()`; `invite_progress()`, `handle_progress()`; the running branch of `render_invite_card()`), `includes/class-wpcpm-admin.php` (`wpcpm-admin` depends on `wp-a11y`), `assets/js/admin.js` (`speak()`, `initInvites()`, `pollInvites()`)
- Test: `bin/test-card-message.php` (new), `bin/test-mail.php` (a section before "The queue across a deactivation"), `bin/test-admin-js.php` (new) with `bin/js/admin-invites.js` (new), `bin/test-sponsors-dashboard.php`, `bin/test-administrators-dashboard.php`, `bin/test-return.php`

**Interfaces:**
- Consumes: Task 1's `forms.js` focus; `WPCPM_Flash`; `WPCPM_Mail::run()`, `queued()`.
- Produces: `WPCPM_Dashboard_Card::TARGET = 'card-target'`, `SLOT`, `TONES`; `target( $anchor )`, `targeted(): string`, `hold( $anchor, $tone, $text )`, `forget()`, `message( $tone, $text, $base = 'wpcpm-dashboard__message' ): string`, `settle( $html ): string`. `WPCPM_Mail::ACTION_PROGRESS = 'wpcpm_invite_progress'`, `PROGRESS_POLL = 10`, `invite_progress(): array{running, sent, total, waiting, percent, count, detail, said}`, `handle_progress()`. `WPCPM_Sponsors_Dashboard::message_of( array $flash ): array{0: string tone, 1: string text}` (private).

- [ ] **Step 1: Write the failing checks**

The card printer's suite:

```php
<?php
/**
 * The outcome a dashboard handler leaves is printed inside the card it goes back to (SDB-01, SDB-02,
 * 1.123.58).
 *
 * A handler names the card (`WPCPM_Dashboard_Card::target()`), the page holds the outcome for it
 * (`hold()`), the card prints it at the top of its body and is drawn open (`open()`), and the slot
 * where the page's outcome used to go is filled only when no card printed it (`settle()`): a flash
 * naming a card the page does not draw is still said, at the top. Every outcome carries its tone, the
 * role its tone is heard with, and the mark assets/js/forms.js focuses.
 *
 * Run from the plugin root:  php bin/test-card-message.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['umeta'] = array();
$GLOBALS['uid']   = 7;

function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_url( $url ) { return (string) $url; }
function sanitize_html_class( $class ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class ); }
function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, (int) $decimals ); }
function wp_slash( $value ) { return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value ); }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value ); }
function get_current_user_id() { return $GLOBALS['uid']; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = wp_unslash( $v ); return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }

require_once dirname( __DIR__ ) . '/includes/class-wpcpm-flash.php';
require_once dirname( __DIR__ ) . '/includes/class-wpcpm-dashboard-card.php';

$fail = 0;
function ck( $l, $a, $e ) {
	global $fail; $ok = $a === $e; if ( ! $ok ) { $fail++; }
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $l . "\n";
	if ( ! $ok ) { echo '       exp: ' . var_export( $e, true ) . '  got: ' . var_export( $a, true ) . "\n"; }
}

/**
 * A page as the two dashboards draw one: the slot, then each card, then settle().
 *
 * @param string[] $keys The cards drawn, by key.
 * @param string   $prefix The anchors' prefix.
 * @return string
 */
function page_of( array $keys, $prefix = 'wpcpm-sponsor-' ) {
	ob_start();
	echo '<div class="page">' . WPCPM_Dashboard_Card::SLOT;

	foreach ( $keys as $key ) {
		WPCPM_Dashboard_Card::open( array( 'key' => $key, 'title' => ucfirst( $key ), 'prefix' => $prefix ) );
		echo '<p>body of ' . $key . '</p>';
		WPCPM_Dashboard_Card::close();
	}

	echo '</div>';

	return WPCPM_Dashboard_Card::settle( (string) ob_get_clean() );
}

ck( 'a success is heard as a status, focusable, marked for the script, in the message classes with its tone',
	WPCPM_Dashboard_Card::message( 'success', 'Your profile was saved.' ),
	'<p class="wpcpm-dashboard__message wpcpm-dashboard__message--success is-success" role="status" tabindex="-1" data-wpcpm-card-message>Your profile was saved.</p>' );
ck( 'an error is heard as an alert', false !== strpos( WPCPM_Dashboard_Card::message( 'error', 'The offer could not be saved.' ), 'role="alert"' ), true );
ck( 'an unknown tone is info, heard as a status', false !== strpos( WPCPM_Dashboard_Card::message( 'loud', 'x' ), 'wpcpm-dashboard__message--info is-info" role="status"' ), true );
ck( 'the sentence is escaped', false !== strpos( WPCPM_Dashboard_Card::message( 'info', 'A <b>"bold"</b> & claim' ), '>A &lt;b&gt;&quot;bold&quot;&lt;/b&gt; &amp; claim</p>' ), true );
ck( 'another part\'s own message class takes the same markup', WPCPM_Dashboard_Card::message( 'error', 'That file is not a PDF.', 'wpcpm-agreement-panel__message' ), '<p class="wpcpm-agreement-panel__message wpcpm-agreement-panel__message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message>That file is not a PDF.</p>' );

WPCPM_Dashboard_Card::target( 'wpcpm-sponsor-offers' );
ck( 'a handler names the card it goes back to, taken once', array( WPCPM_Dashboard_Card::targeted(), WPCPM_Flash::take( WPCPM_Dashboard_Card::TARGET ) ), array( 'wpcpm-sponsor-offers', 'wpcpm-sponsor-offers' ) );

WPCPM_Dashboard_Card::hold( 'wpcpm-sponsor-offers', 'success', 'Paused: Cloud credits is hidden until you resume it.' );
$page = page_of( array( 'profile', 'offers', 'tools' ) );
ck( 'the outcome is printed at the top of the card it names, and only there',
	array( substr_count( $page, 'data-wpcpm-card-message' ), strpos( $page, 'data-wpcpm-card-message' ) > strpos( $page, 'id="wpcpm-sponsor-offers"' ), strpos( $page, 'data-wpcpm-card-message' ) < strpos( $page, 'body of offers' ), strpos( $page, 'data-wpcpm-card-message' ) > strpos( $page, 'body of profile' ) ),
	array( 1, true, true, true ) );
ck( 'that card is drawn open, the others as they asked', array( 1 === preg_match( '#<details id="wpcpm-sponsor-offers" class="[^"]*" open>#', $page ), 1 === preg_match( '#<details id="wpcpm-sponsor-profile" class="[^"]*">#', $page ) ), array( true, true ) );
ck( 'and the slot at the top is cleared', strpos( $page, WPCPM_Dashboard_Card::SLOT ), false );

WPCPM_Dashboard_Card::hold( 'wpcpm-reports', 'error', 'That report was published since you opened the page.' );
$admin = page_of( array( 'reports' ), 'wpcpm-' );
ck( 'the Administrator Dashboard\'s anchors are held to the same rule', array( substr_count( $admin, 'role="alert"' ), strpos( $admin, 'role="alert"' ) > strpos( $admin, 'id="wpcpm-reports"' ) ), array( 1, true ) );

WPCPM_Dashboard_Card::hold( 'wpcpm-sponsor-retired', 'error', 'The offer could not be saved.' );
$missing = page_of( array( 'profile', 'offers' ) );
ck( 'a flash naming a card the page does not draw is said at the top, where the slot was, and no card is opened for it',
	array( strpos( $missing, '<div class="page"><p class="wpcpm-dashboard__message wpcpm-dashboard__message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message>The offer could not be saved.</p>' ), substr_count( $missing, ' open>' ) ),
	array( 0, 0 ) );

WPCPM_Dashboard_Card::hold( '', 'info', 'Sync started.' );
ck( 'an outcome with no card is said at the top', 0 === strpos( page_of( array( 'profile' ) ), '<div class="page"><p class="wpcpm-dashboard__message wpcpm-dashboard__message--info' ), true );

ck( 'with nothing held, the slot is cleared and nothing is said', array( substr_count( page_of( array( 'profile' ) ), 'data-wpcpm-card-message' ), strpos( page_of( array( 'profile' ) ), '<!--' ) ), array( 0, false ) );

WPCPM_Dashboard_Card::hold( 'wpcpm-sponsor-profile', 'success', '   ' );
ck( 'a blank sentence holds nothing', substr_count( page_of( array( 'profile' ) ), 'data-wpcpm-card-message' ), 0 );

WPCPM_Dashboard_Card::hold( 'wpcpm-sponsor-profile', 'success', 'Saved.' );
page_of( array( 'profile' ) );
ck( 'an outcome is said once: a second render in the same request says nothing', substr_count( page_of( array( 'profile' ) ), 'data-wpcpm-card-message' ), 0 );

ck( 'a page with no slot is handed back as it was', WPCPM_Dashboard_Card::settle( '<p>no slot</p>' ), '<p>no slot</p>' );

echo $fail ? "\n$fail FAILED\n" : "\nALL PASS\n";
exit( $fail ? 1 : 0 );
```

`bin/test-mail.php`, the progress answer and the card that no longer reloads:

```diff
diff --git a/bin/test-mail.php b/bin/test-mail.php
index eb4792b1..84b34ba8 100644
--- a/bin/test-mail.php
+++ b/bin/test-mail.php
@@ -950,6 +950,87 @@ ck( 'and it drains to empty', array( count( $GLOBALS['invited'] ), WPCPM_Mail::q
 ck( 'everybody drained is stamped as invited',
     array( (int) get_user_meta( 105, 'wpcpm_student_invited', true ) > 0 ), array( true ) );
 
+/* ---- the invitations card's poller (1.123.58) ------------------------------ */
+
+echo "\n=== The invitations card's poller ===\n";
+
+if ( ! function_exists( 'wp_create_nonce' ) ) {
+	function wp_create_nonce( $action = -1 ) { return 'nonce-' . $action; }
+}
+if ( ! function_exists( 'check_ajax_referer' ) ) {
+	function check_ajax_referer( $action = -1, $query = false, $stop = true ) { $GLOBALS['ajax_checked'] = $action; return true; }
+}
+if ( ! function_exists( 'wp_send_json_success' ) ) {
+	function wp_send_json_success( $data = null, $status = null ) { throw new Exception( 'json:' . json_encode( array( 'success' => true, 'data' => $data ) ) ); }
+}
+if ( ! function_exists( 'wp_send_json_error' ) ) {
+	function wp_send_json_error( $data = null, $status = null ) { throw new Exception( 'json:' . (int) $status . ':' . json_encode( array( 'success' => false, 'data' => $data ) ) ); }
+}
+
+/**
+ * Run the poller's AJAX handler and answer what it sent.
+ *
+ * @return string
+ */
+function asked_progress() {
+	try {
+		WPCPM_Mail::handle_progress();
+	} catch ( Exception $e ) {
+		return $e->getMessage();
+	}
+
+	return 'nothing sent';
+}
+
+WPCPM_Mail::clear_queue();
+WPCPM_Mail::dismiss_run();
+
+ck( 'with no run, the figures say nothing is sending', WPCPM_Mail::invite_progress(), array( 'running' => false, 'sent' => 0, 'total' => 0, 'waiting' => 0, 'percent' => 0, 'count' => 'Sending: 0 of 0 sent.', 'detail' => '', 'said' => 'Invitations sent: 0 of 0.' ) );
+
+foreach ( range( 1, 25 ) as $i ) {
+	$GLOBALS['users'][ 300 + $i ] = new WP_User( 300 + $i, 'Poll ' . $i, "p$i@example.test", array( WPCPM_Roles::ROLE_STUDENT ) );
+}
+
+WPCPM_Mail::queue_invites( range( 301, 325 ) );
+WPCPM_Mail::drain_queue();
+$halfway = WPCPM_Mail::invite_progress();
+
+ck( 'one batch in, the figures are the card\'s: ten of 25 sent, fifteen waiting, the bar at 40',
+	array( $halfway['running'], $halfway['sent'], $halfway['total'], $halfway['waiting'], $halfway['percent'], $halfway['count'], $halfway['said'] ),
+	array( true, 10, 25, 15, 40, 'Sending: 10 of 25 sent.', 'Invitations sent: 10 of 25.' ) );
+ck( 'and the line under the bar says how many are left and when the next batch goes', 0 === strpos( $halfway['detail'], '15 still to go. They go out 10 at a time' ), true );
+
+ob_start();
+WPCPM_Mail::render_invite_card( array( 'action' => 'wpcpm_students_bulk_invite', 'pending' => array(), 'noun' => 'students' ) );
+$polled = (string) ob_get_clean();
+
+ck( 'the card no longer reloads the page while a run sends', array( strpos( $polled, 'location.reload' ), strpos( $polled, '<script' ) ), array( false, false ) );
+ck( 'it draws the poller\'s panel instead: the action, its nonce, every ten seconds, the run\'s total, the sentences it stops and finishes with, busy while it sends',
+	1 === preg_match( '#<div class="wpcpm-invites__progress" data-wpcpm-invite-poll data-action="wpcpm_invite_progress" data-nonce="nonce-wpcpm_invite_progress" data-poll="10" data-total="25" data-stopped="The count stopped updating." data-finished="This run has finished: every invitation in it has been sent or passed over." aria-busy="true">#', $polled ),
+	true );
+ck( 'with the count, the bar and the line under it marked for the poller, from the same figures',
+	array( false !== strpos( $polled, '<strong data-wpcpm-invite-count>Sending: 10 of 25 sent.</strong>' ), 1 === preg_match( '#role="progressbar" aria-valuenow="40"[^>]*data-wpcpm-invite-bar><span style="width:40%" data-wpcpm-invite-fill></span>#', $polled ), false !== strpos( $polled, 'data-wpcpm-invite-detail>15 still to go.' ) ),
+	array( true, true, true ) );
+ck( 'and a link that loads the page again, for a page with scripts off or a poller that stopped', false !== strpos( $polled, '<p class="wpcpm-invites__refresh" data-wpcpm-invite-refresh>To see the latest count, <a href="">refresh the page</a>.</p>' ), true );
+
+$poll_manage       = $GLOBALS['manage'];
+$GLOBALS['manage'] = false;
+ck( 'the poller is refused without the right to manage the program, with a 403', 0 === strpos( asked_progress(), 'json:403:' ), true );
+$GLOBALS['manage'] = true;
+$answer            = asked_progress();
+ck( 'and answered with the figures under its own nonce', array( $GLOBALS['ajax_checked'] ?? null, $answer ), array( 'wpcpm_invite_progress', 'json:' . json_encode( array( 'success' => true, 'data' => WPCPM_Mail::invite_progress() ) ) ) );
+
+WPCPM_Mail::drain_queue();
+WPCPM_Mail::drain_queue();
+$done = WPCPM_Mail::invite_progress();
+ck( 'once the last batch has gone, the figures say it finished: all 25 sent, nothing waiting, the bar full',
+	array( $done['running'], $done['sent'], $done['waiting'], $done['percent'], $done['said'], $done['detail'] ),
+	array( false, 25, 0, 100, 'Invitations sent: 25 of 25.', '' ) );
+
+WPCPM_Mail::clear_queue();
+WPCPM_Mail::dismiss_run();
+$GLOBALS['manage'] = $poll_manage;
+
 /* ---- the queue across a deactivation (1.122.25) ------------------------- */
 
 echo "\n=== The queue across a deactivation ===\n";
```

The poller, run in node:

```js
'use strict';
/*
 * assets/js/admin.js, run in node: the invitations card's poller (1.123.58), what a person on an
 * audience screen sees while a bulk invitation run sends, and what a screen reader is told.
 *
 *   php bin/test-admin-js.php
 *
 * which runs this file as bin/test-forms-js.php runs the forms harness:
 *
 *   node bin/js/admin-invites.js <path to admin.js>
 *
 * The script runs on a stand-in for the page: the card's panel as WPCPM_Mail::render_invite_card()
 * prints it, a fetch that answers what each scenario queues, a clock whose timers a scenario runs by
 * hand, a tab that can be hidden and shown, and wp.a11y.speak() writing down what it was asked to say.
 *
 * Prints `ok   <scenario>` or `FAIL <scenario>` with what was expected and what happened, then a summary
 * line, and exits 1 when any scenario differs.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const SCRIPT = path.resolve( process.argv[ 2 ] || path.join( __dirname, '..', '..', 'assets', 'js', 'admin.js' ) );

let ran = 0;
let failed = 0;

async function scenario( name, want, build ) {
	let got;

	try {
		got = await build();
	} catch ( error ) {
		got = 'threw: ' + error.message;
	}

	const ok = JSON.stringify( got ) === JSON.stringify( want );

	ran++;
	failed += ok ? 0 : 1;
	console.log( ( ok ? 'ok   ' : 'FAIL ' ) + name );

	if ( ! ok ) {
		console.log( '       exp: ' + JSON.stringify( want ) + '  got: ' + JSON.stringify( got ) );
	}
}

class Element {
	constructor( attrs ) {
		this.attrs = Object.assign( {}, attrs || {} );
		this.children = [];
		this.textContent = '';
		this.hidden = false;
		this.style = {};
	}

	add( ...kids ) {
		this.children.push( ...kids );

		return this;
	}

	getAttribute( name ) {
		return Object.prototype.hasOwnProperty.call( this.attrs, name ) ? this.attrs[ name ] : null;
	}

	setAttribute( name, value ) {
		this.attrs[ name ] = String( value );
	}

	all() {
		const out = [];

		( function walk( node ) {
			node.children.forEach( ( child ) => {
				out.push( child );
				walk( child );
			} );
		}( this ) );

		return out;
	}

	// The selectors the poller asks for: `[attr]` and `form`.
	querySelector( selector ) {
		return this.all().find( ( el ) => ( 'form' === selector ? 'form' === el.attrs.tag : null !== el.getAttribute( selector.slice( 1, -1 ) ) ) ) || null;
	}
}

// The panel as the card prints it while a run sends: 10 of 40 sent.
function page() {
	const panel = new Element( { 'data-wpcpm-invite-poll': '', 'data-action': 'wpcpm_invite_progress', 'data-nonce': 'n1', 'data-poll': '10', 'data-total': '40', 'data-stopped': 'The count stopped updating.', 'data-finished': 'This run has finished: every invitation in it has been sent or passed over.', 'aria-busy': 'true' } );
	const count = new Element( { 'data-wpcpm-invite-count': '' } );
	const bar = new Element( { 'data-wpcpm-invite-bar': '', 'aria-valuenow': '25' } );
	const fill = new Element( { 'data-wpcpm-invite-fill': '' } );
	const detail = new Element( { 'data-wpcpm-invite-detail': '' } );
	const stop = new Element( { tag: 'form' } );

	count.textContent = 'Sending: 10 of 40 sent.';
	detail.textContent = '30 still to go.';
	bar.add( fill );
	panel.add( count, bar, detail, stop );

	return { panel, count, bar, fill, detail, stop };
}

// Figures as WPCPM_Mail::invite_progress() answers them.
function figures( sent, total, running ) {
	return {
		running,
		sent,
		total,
		waiting: running ? total - sent : 0,
		percent: Math.round( ( sent / total ) * 100 ),
		count: 'Sending: ' + sent + ' of ' + total + ' sent.',
		detail: running ? ( total - sent ) + ' still to go.' : '',
		said: 'Invitations sent: ' + sent + ' of ' + total + '.',
	};
}

const ok = ( data ) => ( { json: () => Promise.resolve( { success: true, data } ) } );
const refused = () => ( { json: () => Promise.resolve( -1 ) } );

// Load admin.js on the page, with `answers` queued for fetch: a response, or 'drop' for a request that
// fails. Returns what a scenario drives and reads.
function load( answers, options ) {
	const els = page();
	const timers = [];
	const said = [];
	const asked = [];
	const listeners = {};
	const doc = {
		hidden: !! ( options && options.hidden ),
		querySelectorAll: ( selector ) => ( '[data-wpcpm-invite-poll]' === selector ? [ els.panel ] : [] ),
		querySelector: () => null,
		addEventListener: ( type, fn ) => {
			( listeners[ type ] = listeners[ type ] || [] ).push( fn );
		},
	};
	const win = {
		ajaxurl: '/wp-admin/admin-ajax.php',
		setTimeout: ( fn, ms ) => timers.push( { fn, ms } ),
		setInterval: () => 0,
		fetch: ( url, init ) => {
			asked.push( init.body );
			const answer = answers.shift();

			return 'drop' === answer || undefined === answer ? Promise.reject( new Error( 'offline' ) ) : Promise.resolve( answer );
		},
		location: { reload: () => said.push( 'RELOADED' ) },
		wp: { a11y: { speak: ( text ) => said.push( text ) } },
	};

	win.window = win;
	win.document = doc;
	vm.runInNewContext( fs.readFileSync( SCRIPT, 'utf8' ), { window: win, document: doc, encodeURIComponent, Math, parseInt, String } );
	( listeners.DOMContentLoaded || [] ).forEach( ( fn ) => fn() );

	return {
		els,
		timers,
		said,
		asked,
		doc,
		settle: () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) ),
		next: () => timers.shift().fn(),
		show: () => {
			doc.hidden = false;
			( listeners.visibilitychange || [] ).forEach( ( fn ) => fn() );
		},
	};
}

( async function () {
	await scenario( 'the first answer moves the count, the bar and the line in place, says nothing, and asks again in ten seconds', { count: 'Sending: 12 of 40 sent.', bar: '30', fill: '30%', detail: '28 still to go.', said: [], next: [ 10000 ], body: 'action=wpcpm_invite_progress&nonce=n1' }, async () => {
		const run = load( [ ok( figures( 12, 40, true ) ) ] );

		await run.settle();

		return { count: run.els.count.textContent, bar: run.els.bar.getAttribute( 'aria-valuenow' ), fill: run.els.fill.style.width, detail: run.els.detail.textContent, said: run.said, next: run.timers.map( ( t ) => t.ms ), body: run.asked[ 0 ] };
	} );

	await scenario( 'a screen reader is told as the run passes each quarter, once each, and not between', { said: [ 'Invitations sent: 20 of 40.', 'Invitations sent: 30 of 40.' ] }, async () => {
		const run = load( [ ok( figures( 12, 40, true ) ), ok( figures( 16, 40, true ) ), ok( figures( 20, 40, true ) ), ok( figures( 24, 40, true ) ), ok( figures( 30, 40, true ) ) ] );

		for ( let i = 0; i < 4; i++ ) {
			await run.settle();
			run.next();
		}

		await run.settle();

		return { said: run.said };
	} );

	await scenario( 'at the finish it says what was sent, once, prints that the run has finished, hides the Stop form, is no longer busy, and asks nothing more', { said: [ 'Invitations sent: 40 of 40.' ], count: 'Invitations sent: 40 of 40.', detail: 'This run has finished: every invitation in it has been sent or passed over.', stopHidden: true, busy: 'false', timers: 0, reloaded: false }, async () => {
		const run = load( [ ok( figures( 36, 40, true ) ), ok( figures( 40, 40, false ) ) ] );

		await run.settle();
		run.next();
		await run.settle();

		return { said: run.said, count: run.els.count.textContent, detail: run.els.detail.textContent, stopHidden: run.els.stop.hidden, busy: run.els.panel.getAttribute( 'aria-busy' ), timers: run.timers.length, reloaded: run.said.includes( 'RELOADED' ) };
	} );

	await scenario( 'a refused answer, as an expired nonce on a tab left open overnight gives, stops the panel with its sentence and asks nothing more', { detail: 'The count stopped updating.', busy: 'false', timers: 0, asked: 2 }, async () => {
		const run = load( [ ok( figures( 12, 40, true ) ), refused() ] );

		await run.settle();
		run.next();
		await run.settle();

		return { detail: run.els.detail.textContent, busy: run.els.panel.getAttribute( 'aria-busy' ), timers: run.timers.length, asked: run.asked.length };
	} );

	await scenario( 'a request that fails is asked again after twice the wait each time, up to a minute, and five in a row stop the panel', { waits: [ 20000, 40000, 60000, 60000 ], detail: 'The count stopped updating.', timers: 0 }, async () => {
		const run = load( [ 'drop', 'drop', 'drop', 'drop', 'drop' ] );
		const waits = [];

		for ( let i = 0; i < 4; i++ ) {
			await run.settle();
			waits.push( run.timers[ 0 ].ms );
			run.next();
		}

		await run.settle();

		return { waits, detail: run.els.detail.textContent, timers: run.timers.length };
	} );

	await scenario( 'one answer after a failure starts the count of failures again', { waits: [ 20000, 10000, 20000 ] }, async () => {
		const run = load( [ 'drop', ok( figures( 14, 40, true ) ), 'drop' ] );
		const waits = [];

		for ( let i = 0; i < 3; i++ ) {
			await run.settle();
			waits.push( run.timers[ 0 ].ms );
			run.next();
		}

		return { waits };
	} );

	await scenario( 'while the tab is hidden nothing is asked; the poll that came due is asked once the tab is shown', { whileHidden: 1, afterShown: 2, count: 'Sending: 20 of 40 sent.' }, async () => {
		const run = load( [ ok( figures( 12, 40, true ) ), ok( figures( 20, 40, true ) ) ] );

		await run.settle();
		run.doc.hidden = true;
		run.next();
		await run.settle();

		const whileHidden = run.asked.length;

		run.show();
		await run.settle();

		return { whileHidden, afterShown: run.asked.length, count: run.els.count.textContent };
	} );

	console.log( '\n' + ran + ' scenarios, ' + failed + ' differ' );
	process.exit( failed ? 1 : 0 );
}() );
```

```php
<?php
/**
 * Runs assets/js/admin.js: the invitations card's poller (1.123.58), on a stand-in for the page.
 *
 * Run from the plugin root:  php bin/test-admin-js.php
 *
 * node loads assets/js/admin.js into the stand-in in bin/js/admin-invites.js and drives it through
 * the scenarios there (the count moved in place, a screen reader told at each quarter and at the
 * finish, a refused answer and a dropped network stopping the panel, a hidden tab asking nothing).
 * Each scenario the harness prints is shown here as one line.
 *
 * Every other suite is PHP and needs nothing installed, so a machine without node on the path
 * gets one `skip` line and an exit of 0 rather than a failure.
 */
if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root = dirname( __DIR__ );
$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

if ( '' === $node ) {
	echo "skip admin.js was not run: node is not on the path\n";
	exit( 0 );
}

$fail = 0;
function ck( $l, $a, $e = true ) {
	global $fail; $ok = $a === $e; if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $l . "\n";
	if ( ! $ok ) { echo "       exp: " . var_export( $e, true ) . "  got: " . var_export( $a, true ) . "\n"; }
}

$out    = array();
$status = 0;

exec(
	escapeshellarg( $node ) . ' ' . escapeshellarg( $root . '/bin/js/admin-invites.js' ) . ' ' . escapeshellarg( $root . '/assets/js/admin.js' ) . ' 2>&1',
	$out,
	$status
);

// The harness prints "ok   <scenario>" or "FAIL <scenario>" and, under a FAIL, a line saying what
// was expected and what happened. Those lines are this suite's own; anything else it prints is
// a summary or a crash, and is shown as it came. The summary, "N scenarios, M differ", is read
// as well: a harness that stopped early with a status of 0 shows fewer lines than it counts.
$ran     = 0;
$differ  = 0;
$summary = null;

foreach ( $out as $line ) {
	if ( 0 === strpos( $line, 'ok   ' ) ) {
		++$ran;
		echo $line . "\n";
	} elseif ( 0 === strpos( $line, 'FAIL ' ) ) {
		++$ran;
		++$differ;
		++$fail;
		echo $line . "\n";
	} elseif ( 0 === strpos( $line, '       ' ) ) {
		echo $line . "\n";
	} elseif ( '' !== trim( $line ) ) {
		if ( preg_match( '/^(\d+) scenarios, (\d+) differ$/', trim( $line ), $counts ) ) {
			$summary = array( (int) $counts[1], (int) $counts[2] );
		}

		echo '     ' . $line . "\n";
	}
}

ck( 'the harness ran scenarios', $ran > 0, true );
ck( 'and its own summary counts the scenarios and the failures shown above', $summary, array( $ran, $differ ) );
ck( 'and node ended with a status of 0, or of 1 when a scenario differed', $status, $differ ? 1 : 0 );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
```

Specified, in the suites that draw the two pages (their `ck()` style, their stand-ins):
- `bin/test-sponsors-dashboard.php`: "a card's outcome is printed at the top of the card it names, drawn open, as a status, and not at the top of the page" (leave `profile-saved` on `profile`, render; one `data-wpcpm-card-message`, after `id="wpcpm-sponsor-profile"`, `role="status"`, and nothing between the switcher and the identity header); "a refusal is an alert in its card" (an `offer-rejected` on `offers`: `role="alert"`); "a status a page's card does not draw is said at the top" (leave on a card key the page does not draw: one message, before the identity header); "the detail follows the status's sentence in the same message".
- `bin/test-administrators-dashboard.php`: "a decision posted from a card comes back with its sentence inside that card, as a status" (post with `WPCPM_Return::FIELD` and `ANCHOR_FIELD` `reports`, `WPCPM_Flash::set( WPCPM_Institutions::FLASH, <a success status> )`, render: the message is inside `id="wpcpm-reports"`); "a refused decision is an alert in its card"; "a refused Draft now from the report screen is said in the card it came from"; "an outcome with no card is said under the intro line, where it was".
- `bin/test-return.php`: "url() names the card for the outcome when it goes back to the dashboard with an anchor, and names none otherwise" (`WPCPM_Flash::take( WPCPM_Dashboard_Card::TARGET )` is `wpcpm-<anchor>` after a dashboard return, and '' after a return to the handler's own screen).

- [ ] **Step 2: Run them to verify they fail**

```text
$ php bin/test-card-message.php
PHP Fatal error:  Uncaught Error: Call to undefined method WPCPM_Dashboard_Card::message()
$ php bin/test-mail.php
PHP Fatal error:  Uncaught Error: Call to undefined method WPCPM_Mail::invite_progress()
$ php bin/test-admin-js.php
FAIL the first answer moves the count, the bar and the line in place, says nothing, and asks again in ten seconds
FAIL a screen reader is told as the run passes each quarter, once each, and not between
FAIL at the finish it says what was sent, once, prints that the run has finished, hides the Stop form, is no longer busy, and asks nothing more
FAIL a refused answer, as an expired nonce on a tab left open overnight gives, stops the panel with its sentence and asks nothing more
FAIL a request that fails is asked again after twice the wait each time, up to a minute, and five in a row stop the panel
FAIL one answer after a failure starts the count of failures again
FAIL while the tab is hidden nothing is asked; the poll that came due is asked once the tab is shown
8 FAILURE(S)
```

- [ ] **Step 3: The card printer, the progress answer and the poller**

`includes/class-wpcpm-dashboard-card.php`:

```diff
diff --git a/includes/class-wpcpm-dashboard-card.php b/includes/class-wpcpm-dashboard-card.php
index 3099dee9..da478d15 100644
--- a/includes/class-wpcpm-dashboard-card.php
+++ b/includes/class-wpcpm-dashboard-card.php
@@ -52,6 +52,31 @@ final class WPCPM_Dashboard_Card {
 	/** The handle the dashboards' opener, `assets/js/dashboard-open.js`, is registered under. */
 	const OPENER = 'wpcpm-dashboard-open';
 
+	/**
+	 * The flash channel a handler names the card it goes back to on, by the card's anchor, beside the
+	 * outcome it flashes on its own channel (`target()`, 1.123.58).
+	 */
+	const TARGET = 'card-target';
+
+	/** What a page prints where its outcome used to go, until `settle()` fills or clears it. */
+	const SLOT = '<!-- wpcpm-card-message -->';
+
+	/** The tones a message carries, each with the role a screen reader hears it with. */
+	const TONES = array(
+		'success' => 'status',
+		'info'    => 'status',
+		'warning' => 'status',
+		'error'   => 'alert',
+	);
+
+	/**
+	 * The one outcome the page holds, for the card it names (`hold()`): `anchor`, `tone`, `text`, and
+	 * whether a card printed it yet. Null while the page holds none.
+	 *
+	 * @var array|null
+	 */
+	private static $held = null;
+
 	/**
 	 * What each card opened and not yet closed asked for, the innermost last, so `close()` prints
 	 * the action of the card it closes.
@@ -77,6 +102,106 @@ final class WPCPM_Dashboard_Card {
 		wp_enqueue_script( self::OPENER );
 	}
 
+	/**
+	 * Name the card a handler goes back to, for the outcome it flashes (1.123.58): a handler that
+	 * redirects to `#<anchor>` calls this with the anchor, so the page prints the outcome inside that
+	 * card. The Administrator Dashboard's way back calls it for every decision (`WPCPM_Return::url()`),
+	 * and the Sponsor Dashboard's `leave()` for every card.
+	 *
+	 * @param string $anchor The card's anchor, such as `wpcpm-sponsor-offers` or `wpcpm-reports`.
+	 */
+	public static function target( $anchor ) {
+		WPCPM_Flash::set( self::TARGET, sanitize_html_class( (string) $anchor ) );
+	}
+
+	/**
+	 * The card the last handler named, taken once, or ''.
+	 *
+	 * @return string
+	 */
+	public static function targeted() {
+		return sanitize_html_class( (string) WPCPM_Flash::take( self::TARGET ) );
+	}
+
+	/**
+	 * Hold the page's outcome for the card it names: `open()` prints it at the top of that card's body
+	 * and draws the card open; `settle()` prints it where the page's outcome used to go when no card of
+	 * that anchor was drawn, or when it names none.
+	 *
+	 * @param string $anchor The card's anchor, or '' for an outcome with no card.
+	 * @param string $tone   A key of `TONES`; anything else is `info`.
+	 * @param string $text   The sentence, as text.
+	 */
+	public static function hold( $anchor, $tone, $text ) {
+		if ( '' === trim( (string) $text ) ) {
+			self::$held = null;
+
+			return;
+		}
+
+		self::$held = array(
+			'anchor'  => (string) $anchor,
+			'tone'    => isset( self::TONES[ $tone ] ) ? (string) $tone : 'info',
+			'text'    => (string) $text,
+			'printed' => false,
+		);
+	}
+
+	/**
+	 * Forget the outcome held, printed or not: for a page that renders twice in one request.
+	 */
+	public static function forget() {
+		self::$held = null;
+	}
+
+	/**
+	 * One outcome's markup: the dashboards' message classes with the tone, the role its tone is heard
+	 * with, and the mark and `tabindex` assets/js/forms.js moves the focus to on load.
+	 *
+	 * The Institution Dashboard's parts, which are not drawn in this shell, print their outcomes in
+	 * their own message classes through the same markup (`$base`), so every dashboard's outcome is
+	 * toned, heard and focused alike (IDB-09).
+	 *
+	 * @param string $tone A key of `TONES`; anything else is `info`.
+	 * @param string $text The sentence, as text.
+	 * @param string $base The message's own class, its tone after `--`: `wpcpm-dashboard__message`
+	 *                     by default.
+	 * @return string
+	 */
+	public static function message( $tone, $text, $base = 'wpcpm-dashboard__message' ) {
+		$tone = isset( self::TONES[ $tone ] ) ? (string) $tone : 'info';
+		$base = sanitize_html_class( (string) $base );
+
+		return sprintf(
+			'<p class="%1$s %1$s--%2$s is-%2$s" role="%3$s" tabindex="-1" data-wpcpm-card-message>%4$s</p>',
+			esc_attr( $base ),
+			esc_attr( $tone ),
+			esc_attr( self::TONES[ $tone ] ),
+			esc_html( (string) $text )
+		);
+	}
+
+	/**
+	 * Fill the page's slot once every card is drawn: with the outcome held when no card printed it,
+	 * else with nothing.
+	 *
+	 * @param string $html The page as rendered, holding `SLOT` where its outcome used to go.
+	 * @return string
+	 */
+	public static function settle( $html ) {
+		$fill = '';
+
+		if ( is_array( self::$held ) && ! self::$held['printed'] ) {
+			$fill = self::message( self::$held['tone'], self::$held['text'] );
+		}
+
+		self::$held = null;
+
+		$at = strpos( (string) $html, self::SLOT );
+
+		return false === $at ? (string) $html : substr_replace( (string) $html, $fill, $at, strlen( self::SLOT ) );
+	}
+
 	/**
 	 * One group of cards: a heading and one sentence saying what the group's cards hold, over the
 	 * cards, in a wrapper of its own, the one shape both dashboards draw their groups in. The cards
@@ -216,6 +341,12 @@ final class WPCPM_Dashboard_Card {
 		);
 
 		$anchor = (string) $args['prefix'] . (string) $args['key'];
+		$holds  = is_array( self::$held ) && ! self::$held['printed'] && '' !== self::$held['anchor'] && self::$held['anchor'] === $anchor;
+
+		// A card that holds the page's outcome is drawn open, so the outcome is on screen (1.123.58).
+		if ( $holds ) {
+			$args['open'] = true;
+		}
 
 		self::$opened[] = array(
 			'anchor' => $anchor,
@@ -255,6 +386,13 @@ final class WPCPM_Dashboard_Card {
 
 		echo '<span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>';
 		printf( '<div class="%s">', esc_attr( trim( 'wpcpm-group__body ' . (string) $args['body_class'] ) ) );
+
+		// The outcome a handler left for this card opens its body, where the person is (SDB-01, SDB-02).
+		if ( $holds ) {
+			echo self::message( self::$held['tone'], self::$held['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in message().
+
+			self::$held['printed'] = true;
+		}
 	}
 
 	/**
```

`includes/class-wpcpm-mail.php`:

```diff
diff --git a/includes/class-wpcpm-mail.php b/includes/class-wpcpm-mail.php
index 7d374bfd..f4dda4fa 100644
--- a/includes/class-wpcpm-mail.php
+++ b/includes/class-wpcpm-mail.php
@@ -98,6 +98,15 @@ class WPCPM_Mail {
 	/** Admin-post action that clears a finished run from the screen. */
 	const ACTION_DISMISS = 'wpcpm_dismiss_invite_run';
 
+	/**
+	 * The AJAX action the invitations card's poller reads a run's progress through every ten seconds,
+	 * in place of the page reload it replaced (ADM-01, LOG-04, 1.123.58), and its nonce's action.
+	 */
+	const ACTION_PROGRESS = 'wpcpm_invite_progress';
+
+	/** How often the poller asks, in seconds. */
+	const PROGRESS_POLL = 10;
+
 	/**
 	 * What is currently being sent, if it is ours: the email's id in `WPCPM_Mail_Catalog`.
 	 *
@@ -126,6 +135,7 @@ class WPCPM_Mail {
 		add_action( 'admin_init', array( __CLASS__, 'maybe_schedule_drain' ) );
 		add_action( 'admin_post_' . self::ACTION_STOP, array( __CLASS__, 'handle_stop' ) );
 		add_action( 'admin_post_' . self::ACTION_DISMISS, array( __CLASS__, 'handle_dismiss' ) );
+		add_action( 'wp_ajax_' . self::ACTION_PROGRESS, array( __CLASS__, 'handle_progress' ) );
 
 		// Before wpcomsh's own `login_init` callback, which runs at -1. See the method.
 		add_action( 'login_init', array( __CLASS__, 'keep_password_links_working' ), -2 );
@@ -1185,6 +1195,66 @@ class WPCPM_Mail {
 		self::back();
 	}
 
+	/**
+	 * Where the bulk invitation run stands, as the card prints it and its poller reads it (1.123.58).
+	 *
+	 * `running` is whether a run is sending; `sent`, `total` and `waiting` are the card's own figures;
+	 * `percent` is the bar's; `count` is the card's count line, `detail` the line under the bar, and
+	 * `said` the sentence a screen reader is told at each quarter and at the finish. Read-only: the request that asks spawns WordPress's
+	 * cron as any page load does, which is what moves the queue, as the reload did.
+	 *
+	 * @return array{running: bool, sent: int, total: int, waiting: int, percent: int, count: string, detail: string, said: string}
+	 */
+	public static function invite_progress() {
+		$run     = self::run();
+		$waiting = self::queued();
+		$total   = empty( $run ) ? 0 : (int) $run['total'];
+		$done    = max( 0, $total - $waiting );
+		$sent    = empty( $run ) ? 0 : max( 0, $done - (int) $run['unsent'] );
+		$running = $waiting > 0 && ! empty( $run );
+
+		if ( ! empty( $run ) && ! $running ) {
+			$sent = max( 0, $total - (int) $run['unsent'] );
+		}
+
+		$next   = $running ? wp_next_scheduled( self::CRON_QUEUE ) : false;
+		$detail = $running ? sprintf(
+			/* translators: 1: how many are left, 2: batch size, 3: human time until the next batch. */
+			__( '%1$d still to go. They go out %2$d at a time, so this finishes on its own - you can leave this page. Next batch %3$s.', 'wpcredits-program-manager' ),
+			$waiting,
+			self::QUEUE_BATCH,
+			$next ? sprintf( /* translators: %s: human time difference. */ __( 'in about %s', 'wpcredits-program-manager' ), human_time_diff( time(), $next ) ) : __( 'shortly', 'wpcredits-program-manager' )
+		) : '';
+
+		return array(
+			'running' => $running,
+			'sent'    => $sent,
+			'total'   => $total,
+			'waiting' => $running ? $waiting : 0,
+			'percent' => $total > 0 ? (int) min( 100, round( ( ( $running ? $done : $total ) / $total ) * 100 ) ) : 0,
+			/* translators: 1: sent so far, 2: total in this run. */
+			'count'   => sprintf( __( 'Sending: %1$d of %2$d sent.', 'wpcredits-program-manager' ), $sent, $total ),
+			'detail'  => $detail,
+			/* translators: 1: invitations sent so far, 2: how many the run sends. */
+			'said'    => sprintf( __( 'Invitations sent: %1$d of %2$d.', 'wpcredits-program-manager' ), $sent, $total ),
+		);
+	}
+
+	/**
+	 * The AJAX answer the invitations card's poller reads (`invite_progress()`): the capability, then
+	 * the nonce, then the figures. A refused request answers 403, which stops the poller with its
+	 * sentence asking for a reload (assets/js/admin.js).
+	 */
+	public static function handle_progress() {
+		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
+			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ) ), 403 );
+		}
+
+		check_ajax_referer( self::ACTION_PROGRESS, 'nonce' );
+
+		wp_send_json_success( self::invite_progress() );
+	}
+
 	/**
 	 * Clear a finished run from the screen.
 	 */
@@ -1341,35 +1411,30 @@ class WPCPM_Mail {
 		// A run in progress owns the card. Offering "send" while a send is under way is how the
 		// same people get two emails.
 		if ( $waiting > 0 && ! empty( $run ) ) {
-			$done    = max( 0, $run['total'] - $waiting );
-			$sent    = max( 0, $done - $run['unsent'] );
-			$percent = $run['total'] > 0 ? (int) round( ( $done / $run['total'] ) * 100 ) : 0;
-			$next    = wp_next_scheduled( self::CRON_QUEUE );
+			$progress = self::invite_progress();
 
+			// The poller's panel: assets/js/admin.js reads the figures every PROGRESS_POLL seconds through
+			// ACTION_PROGRESS and moves the count and the bar in place, and tells a screen reader at each
+			// quarter and at the finish; the page is never reloaded under the person (1.123.58).
 			printf(
-				'<p class="wpcpm-invites__count"><strong>%s</strong></p>',
-				esc_html(
-					sprintf(
-						/* translators: 1: sent so far, 2: total in this run. */
-						__( 'Sending: %1$d of %2$d sent.', 'wpcredits-program-manager' ),
-						$sent,
-						$run['total']
-					)
-				)
+				'<div class="wpcpm-invites__progress" data-wpcpm-invite-poll data-action="%1$s" data-nonce="%2$s" data-poll="%3$d" data-total="%4$d" data-stopped="%5$s" data-finished="%6$s" aria-busy="true">',
+				esc_attr( self::ACTION_PROGRESS ),
+				esc_attr( wp_create_nonce( self::ACTION_PROGRESS ) ),
+				(int) self::PROGRESS_POLL,
+				(int) $progress['total'],
+				esc_attr__( 'The count stopped updating.', 'wpcredits-program-manager' ),
+				esc_attr__( 'This run has finished: every invitation in it has been sent or passed over.', 'wpcredits-program-manager' )
 			);
 
 			printf(
-				'<div class="wpcpm-invites__bar" role="progressbar" aria-valuenow="%1$d" aria-valuemin="0" aria-valuemax="100" aria-label="%3$s"><span style="width:%1$d%%"></span></div><p class="description">%2$s</p>',
-				(int) $percent,
-				esc_html(
-					sprintf(
-						/* translators: 1: how many are left, 2: batch size, 3: human time until the next batch. */
-						__( '%1$d still to go. They go out %2$d at a time, so this finishes on its own - you can leave this page. Next batch %3$s.', 'wpcredits-program-manager' ),
-						$waiting,
-						self::QUEUE_BATCH,
-						$next ? sprintf( /* translators: %s: human time difference. */ __( 'in about %s', 'wpcredits-program-manager' ), human_time_diff( time(), $next ) ) : __( 'shortly', 'wpcredits-program-manager' )
-					)
-				),
+				'<p class="wpcpm-invites__count"><strong data-wpcpm-invite-count>%s</strong></p>',
+				esc_html( $progress['count'] )
+			);
+
+			printf(
+				'<div class="wpcpm-invites__bar" role="progressbar" aria-valuenow="%1$d" aria-valuemin="0" aria-valuemax="100" aria-label="%3$s" data-wpcpm-invite-bar><span style="width:%1$d%%" data-wpcpm-invite-fill></span></div><p class="description" data-wpcpm-invite-detail>%2$s</p>',
+				(int) $progress['percent'],
+				esc_html( $progress['detail'] ),
 				esc_attr__( 'Invitations sent', 'wpcredits-program-manager' )
 			);
 
@@ -1400,9 +1465,17 @@ class WPCPM_Mail {
 			);
 			echo '</form>';
 
-			// Cron runs on requests, so the page has to come back for the count to move. Only
-			// while something is actually in flight - a page that reloads for ever is its own bug.
-			echo '<script>window.setTimeout(function(){window.location.reload();},30000);</script>';
+			// Closes the poller's panel. No reload any more (1.123.58): the poller's request is a request,
+			// which spawns cron as the reload did, and the link is the way to the full card for a page
+			// with scripts off, or one whose poller stopped.
+			printf(
+				'<p class="wpcpm-invites__refresh" data-wpcpm-invite-refresh>%s</p></div>',
+				sprintf(
+					/* translators: %s: a link that loads this page again, its words "refresh the page". */
+					esc_html__( 'To see the latest count, %s.', 'wpcredits-program-manager' ),
+					'<a href="">' . esc_html__( 'refresh the page', 'wpcredits-program-manager' ) . '</a>'
+				)
+			);
 			echo '</div>';
 
 			return;
```

`includes/class-wpcpm-admin.php`:

```diff
diff --git a/includes/class-wpcpm-admin.php b/includes/class-wpcpm-admin.php
index 0dab6910..c60cdff7 100644
--- a/includes/class-wpcpm-admin.php
+++ b/includes/class-wpcpm-admin.php
@@ -213,7 +213,8 @@ class WPCPM_Admin {
 		wp_enqueue_script(
 			'wpcpm-admin',
 			WPCPM_PLUGIN_URL . 'assets/js/admin.js',
-			array(),
+			// WordPress's live region, which the invitations and sync pollers speak through (1.123.58).
+			array( 'wp-a11y' ),
 			WPCPM_VERSION,
 			true
 		);
```

`assets/js/admin.js`:

```diff
diff --git a/assets/js/admin.js b/assets/js/admin.js
index f84cc89c..6431f62d 100644
--- a/assets/js/admin.js
+++ b/assets/js/admin.js
@@ -223,7 +223,193 @@
 		}, seconds * 1000 );
 	}
 
+	/**
+	 * Tell a screen reader something, politely, through WordPress's own live region (`wp-a11y`, a
+	 * dependency of this script). Nothing happens on a page without it.
+	 *
+	 * @param {string} text What to say.
+	 */
+	function speak( text ) {
+		if ( text && window.wp && window.wp.a11y && 'function' === typeof window.wp.a11y.speak ) {
+			window.wp.a11y.speak( text, 'polite' );
+		}
+	}
+
+	/**
+	 * Wire every invitations card that is sending (1.123.58).
+	 *
+	 * The card reloaded the whole screen every 30 seconds while a bulk run sent, which threw away a
+	 * typed search, ticked rows and a screen reader's place (ADM-01, LOG-04, WCAG 2.2.1). It now
+	 * draws a panel marked `data-wpcpm-invite-poll`, which this reads every `data-poll` seconds
+	 * through `WPCPM_Mail::ACTION_PROGRESS` and moves in place.
+	 *
+	 * @return {number} How many panels were wired.
+	 */
+	function initInvites() {
+		var panels = document.querySelectorAll( '[data-wpcpm-invite-poll]' );
+		var i;
+
+		if ( ! panels.length || typeof window.ajaxurl === 'undefined' ) {
+			return 0;
+		}
+
+		for ( i = 0; i < panels.length; i++ ) {
+			pollInvites( panels[ i ] );
+		}
+
+		return panels.length;
+	}
+
+	/**
+	 * Keep one invitations panel current until its run finishes, its request is refused, or the
+	 * network stays away.
+	 *
+	 * - The count, the bar and the line under it move on every answer; the panel is no live region,
+	 *   so they are not read out every ten seconds. A screen reader is told "Invitations sent: N of M"
+	 *   as the run passes each quarter, and once at the finish (`speak()`); the first answer sets the
+	 *   quarter the page opened at, unsaid.
+	 * - At the finish the count says what was sent, the line under the bar says the run has finished
+	 *   (the panel's `data-finished`, a notice a sighted person sees), the Stop form is hidden,
+	 *   `aria-busy` goes false, and nothing more is asked.
+	 * - A refused answer (an expired nonce on a tab left open overnight, a signed-out session) stops the
+	 *   panel with the sentence in its `data-stopped`; the card's link to refresh the page stays.
+	 * - A request that fails is asked again after twice the wait, up to a minute, and after five in a
+	 *   row the panel stops as a refused one does.
+	 * - While the tab is hidden nothing is asked; the poll that came due is asked when the tab is
+	 *   shown again.
+	 *
+	 * @param {Element} panel The panel element.
+	 */
+	function pollInvites( panel ) {
+		var action = panel.getAttribute( 'data-action' );
+		var nonce = panel.getAttribute( 'data-nonce' );
+		var wait = ( parseInt( panel.getAttribute( 'data-poll' ), 10 ) || 10 ) * 1000;
+		var el = {
+			count: panel.querySelector( '[data-wpcpm-invite-count]' ),
+			bar: panel.querySelector( '[data-wpcpm-invite-bar]' ),
+			fill: panel.querySelector( '[data-wpcpm-invite-fill]' ),
+			detail: panel.querySelector( '[data-wpcpm-invite-detail]' ),
+			stop: panel.querySelector( 'form' ),
+		};
+		var told = -1;
+		var failures = 0;
+		var stopped = false;
+		var due = false;
+
+		function end( sentence ) {
+			stopped = true;
+			panel.setAttribute( 'aria-busy', 'false' );
+
+			if ( sentence && el.detail ) {
+				el.detail.textContent = sentence;
+			}
+		}
+
+		function render( data ) {
+			var quarter = data.total > 0 ? Math.floor( ( data.sent / data.total ) * 4 ) : 0;
+
+			if ( el.count && data.count ) {
+				el.count.textContent = data.running ? data.count : data.said;
+			}
+
+			if ( typeof data.percent === 'number' ) {
+				if ( el.fill ) {
+					el.fill.style.width = data.percent + '%';
+				}
+				if ( el.bar ) {
+					el.bar.setAttribute( 'aria-valuenow', String( data.percent ) );
+				}
+			}
+
+			if ( el.detail ) {
+				el.detail.textContent = data.detail || '';
+			}
+
+			if ( ! data.running ) {
+				speak( data.said );
+
+				if ( el.stop ) {
+					el.stop.hidden = true;
+				}
+
+				end( panel.getAttribute( 'data-finished' ) );
+
+				return;
+			}
+
+			if ( told >= 0 && quarter > told && quarter < 4 ) {
+				speak( data.said );
+			}
+
+			told = Math.max( told, quarter );
+		}
+
+		function later( ms ) {
+			window.setTimeout( function () {
+				if ( document.hidden ) {
+					due = true;
+					return;
+				}
+
+				ask();
+			}, ms );
+		}
+
+		function ask() {
+			if ( stopped ) {
+				return;
+			}
+
+			due = false;
+
+			window
+				.fetch( window.ajaxurl, {
+					method: 'POST',
+					credentials: 'same-origin',
+					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
+					body: 'action=' + encodeURIComponent( action ) + '&nonce=' + encodeURIComponent( nonce ),
+				} )
+				.then( function ( response ) {
+					return response.json();
+				} )
+				.then( function ( result ) {
+					failures = 0;
+
+					if ( ! result || ! result.success || ! result.data ) {
+						end( panel.getAttribute( 'data-stopped' ) );
+						return;
+					}
+
+					render( result.data );
+
+					if ( ! stopped ) {
+						later( wait );
+					}
+				} )
+				.catch( function () {
+					failures += 1;
+
+					if ( failures >= 5 ) {
+						end( panel.getAttribute( 'data-stopped' ) );
+						return;
+					}
+
+					later( Math.min( 60000, wait * Math.pow( 2, failures ) ) );
+				} );
+		}
+
+		document.addEventListener( 'visibilitychange', function () {
+			if ( ! document.hidden && due ) {
+				ask();
+			}
+		} );
+
+		ask();
+	}
+
 	document.addEventListener( 'DOMContentLoaded', function () {
+		initInvites();
+
 		// The live poll supersedes a blunt reload, so only fall back when absent.
 		if ( 0 === initProgress() ) {
 			initReload();
```

Then the two pages, specified:
- **Sponsor Dashboard.** `leave()`: after the flash is set, `if ( '' !== $flash['card'] ) { WPCPM_Dashboard_Card::target( 'wpcpm-sponsor-' . $flash['card'] ); }`. `render_message()` becomes `message_of( array $flash ): array`, answering `array( <the messages() tone>, <the sentence, a space and the detail, or the detail alone> )` and `array( '', '' )` in every case it now prints nothing. `render()`: `WPCPM_Dashboard_Card::forget()` first; then, in place of `self::render_message( $flash )`, `list( $tone, $text ) = self::message_of( $flash ); WPCPM_Dashboard_Card::hold( WPCPM_Dashboard_Card::targeted(), $tone, $text ); echo WPCPM_Dashboard_Card::SLOT;` (the target is read for the card the handler named; a flash with no target holds `''`); the page's last line `return WPCPM_Dashboard_Card::settle( (string) ob_get_clean() );`.
- **Administrator Dashboard.** `render()`: `WPCPM_Dashboard_Card::forget()` before `ob_start()`, `echo WPCPM_Dashboard_Card::SLOT;` where `self::render_messages()` was called, `self::render_messages()` itself called before the groups, and `return WPCPM_Dashboard_Card::settle( (string) ob_get_clean() );`. `render_messages()` holds instead of printing: `WPCPM_Dashboard_Card::hold( WPCPM_Dashboard_Card::targeted(), (string) $messages[ $status ][0], $sentence )`; `render_report_message()` holds its refusal with the tone `error`, only when no status was held. `WPCPM_Return::url()`: when it answers the dashboard with an anchor of `ANCHORS`, `WPCPM_Dashboard_Card::target( 'wpcpm-' . $anchor )` before returning, so every decision that comes back to a card says its outcome there.
- The plugin's `administrator.css` and `sponsor.css` already color `wpcpm-dashboard__message--success` and `--error`; nothing else changes in them.

- [ ] **Step 4: Run them to verify they pass**

```text
$ php bin/test-card-message.php
ok   a success is heard as a status, focusable, marked for the script, in the message classes with its tone
ok   an error is heard as an alert
ok   an unknown tone is info, heard as a status
ok   the sentence is escaped
ok   another part's own message class takes the same markup
ok   a handler names the card it goes back to, taken once
ok   the outcome is printed at the top of the card it names, and only there
ok   that card is drawn open, the others as they asked
ok   and the slot at the top is cleared
ok   the Administrator Dashboard's anchors are held to the same rule
ok   a flash naming a card the page does not draw is said at the top, where the slot was, and no card is opened for it
ok   an outcome with no card is said at the top
ok   with nothing held, the slot is cleared and nothing is said
ok   a blank sentence holds nothing
ok   an outcome is said once: a second render in the same request says nothing
ok   a page with no slot is handed back as it was
ALL PASS
$ php bin/test-mail.php
=== The invitations card's poller ===
ok   with no run, the figures say nothing is sending
ok   one batch in, the figures are the card's: ten of 25 sent, fifteen waiting, the bar at 40
ok   and the line under the bar says how many are left and when the next batch goes
ok   the card no longer reloads the page while a run sends
ok   it draws the poller's panel instead: the action, its nonce, every ten seconds, the run's total, the sentences it stops and finishes with, busy while it sends
ok   with the count, the bar and the line under it marked for the poller, from the same figures
ok   and a link that loads the page again, for a page with scripts off or a poller that stopped
ok   the poller is refused without the right to manage the program, with a 403
ok   and answered with the figures under its own nonce
ok   once the last batch has gone, the figures say it finished: all 25 sent, nothing waiting, the bar full
ALL PASS
$ php bin/test-admin-js.php
ok   the first answer moves the count, the bar and the line in place, says nothing, and asks again in ten seconds
ok   a screen reader is told as the run passes each quarter, once each, and not between
ok   at the finish it says what was sent, once, prints that the run has finished, hides the Stop form, is no longer busy, and asks nothing more
ok   a refused answer, as an expired nonce on a tab left open overnight gives, stops the panel with its sentence and asks nothing more
ok   a request that fails is asked again after twice the wait each time, up to a minute, and five in a row stop the panel
ok   one answer after a failure starts the count of failures again
ok   while the tab is hidden nothing is asked; the poll that came due is asked once the tab is shown
     7 scenarios, 0 differ
ALL PASS
```

and the three suites of the specified checks: `ALL PASS`.

- [ ] **Step 5: The checks every task ends with**

As the block says; the screens that draw the invitations card (`bin/test-students-screen.php`, `bin/test-institutions-screen.php`, `bin/test-sponsors-screen.php`) are in the battery and pass as they were.

- [ ] **Step 6: Commit**

```bash
git add includes/class-wpcpm-dashboard-card.php includes/modules/class-wpcpm-sponsors-dashboard.php includes/modules/class-wpcpm-administrators-dashboard.php includes/class-wpcpm-return.php includes/class-wpcpm-mail.php includes/class-wpcpm-admin.php assets/js/admin.js bin/test-card-message.php bin/test-mail.php bin/test-admin-js.php bin/js/admin-invites.js bin/test-sponsors-dashboard.php bin/test-administrators-dashboard.php bin/test-return.php
git commit -m "Scan release 1: every dashboard outcome inside the card it came from, and the invitations card polls in place of its reload" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 4: The four public forms: a summary with its focus, every answer kept, one pass, one row per send

Findings: FORM-01, FORM-02, FORM-11, FORM-12, FORM-13 (change A), FORM-04 (change B); Review Focus 1. Scope **L**. The four forms are the mentor application (`class-wpcpm-mentor-application-form.php`), the institution application (`class-wpcpm-institution-application.php`), the sponsor application (`class-wpcpm-sponsor-application.php`) and the student registration (`class-wpcpm-student-registration-form.php`); each already keeps through `WPCPM_Form_Stash`, marks fields with `aria-invalid` and ties each problem with `aria-describedby`. Read FORM-01, 02, 04, 11, 12 and 13 in full, with the lines they cite, first.

**Files:**
- Modify: the four forms (each: the render's message for a refused send at about mentor `:1067`, institution `:1405`, sponsor `:1067`, student `:1041`; the problem texts at mentor `:1327`, institution `:1764`; the handler's order at mentor `:1374-1416`, institution `:1881-1940`, sponsor `:1725-1778`, student `:1349-1392`; the "does not draw" lists at mentor `:911`, institution `:1216`, sponsor `:896`, student `:942`; the store step)
- Modify: `includes/class-wpcpm-form-guard.php` (`TOKEN_USED`, `token_key()`, the in-flight record, `check_token()` at about `:215`), `includes/class-wpcpm-form-stash.php` (`read()`, `fits()`), `includes/class-wpcpm-ceiling.php` (`resets_at()`), `includes/class-wpcpm-field-value.php` (`bad_url` at about `:107`)
- Test: `bin/test-mentor-application-form.php`, `bin/test-institution-application.php`, `bin/test-sponsor-application.php`, `bin/test-student-registration.php`, `bin/test-form-guard.php`, `bin/test-form-stash.php`, `bin/test-ceiling.php`

**Interfaces:**
- Consumes: Task 1's `WPCPM_Form_Errors::summary()`, `file_again()`, `WPCPM_Flash::KEPT_MAX`, `forms.js`'s focus.
- Produces:
  - `WPCPM_Form_Guard::TOKEN_USED = 'used'`; `token_key( string $scope, string $token ): string` ('' for a token that does not parse); `PENDING = 'pending'`; `in_flight( string $key ): void` (writes the transient `wpcpm_dwell_<key>` = `pending` for `TOKEN_LIFETIME`); `landed( string $key, int $row_id ): void`; `bounced( string $key ): void` (deletes it); `row_of( string $key ): int|string|null` (the row id, `'pending'`, or null when gone); `wait_for_row( string $key, int $tries = 6, int $pause_us = 500000 ): int` (the row id, or 0 when the record is gone or still pending after the tries).
  - `WPCPM_Form_Stash::read()` answers `array( 'outcome' => 'stash-gone' )` for an id whose transient is gone; `WPCPM_Form_Stash::fits( array $stash ): bool` (the sum of the string lengths of its values and typing is at most `WPCPM_Flash::KEPT_MAX`).
  - `WPCPM_Ceiling::resets_at( int $window ): int`.
  - Each form's outcomes `busy` (bare: the sentence only), `busy-kept` (the form drawn with its answers), `busy-unkept`, `stash-gone`, and the problem keys `consent`, and each file field's key.

**The rules:**
1. **The summary.** A refused send draws `WPCPM_Form_Errors::summary( $problems, $fields )` as the form's first child, above the first `<fieldset`, in place of the generic "Please look at the answers marked below" paragraph, which goes. `$fields` is the form's questions in their order, each its control's id (the id its `<span class="wpcpm-field__problem" id="<id>-problem">` already uses, so read the field printer) and its label as the page prints it. The existing per-field problem span stays.
2. **One pass** (FORM-12, FORM-13). Every answer, the consent box and, on the sponsor form, the two logo files are checked in the same pass, and one bounce carries every problem. The consent box gets the problem `consent`, with its `aria-invalid`, border and sentence like every other field. Nothing is stored without consent, as today.
3. **Every refused answer comes back as typed** (FORM-11): the stash's `typed` carries the raw typing of every refused text, email and web-address field, and the field is drawn from it.
4. **A file** (FORM-13): when a form is bounced for any reason and a file had been chosen for a file field (`! empty( $_FILES[ <field> ]['name'] )`), that field gets the problem `WPCPM_Form_Errors::file_again()` (replacing "Files have to be chosen again").
5. **The ceiling** (FORM-01). The ceiling stays the handler's first step. When it refuses, the handler checks the form's own nonce next (the check its later step makes today; read it in the handler) and only then decides what to keep: a nonce that fails keeps nothing and answers the bare `busy` (no transient is written: the flood the ceiling makes cheap stays cheap); a nonce that holds keeps the cleaned values and the typing when `WPCPM_Form_Stash::fits()` says they fit, as `busy-kept`, and otherwise answers `busy-unkept`, keeping nothing. `busy` and `busy-unkept` stay in the "does not draw" list; `busy-kept` draws the form under its sentence with its Send button.
6. **A used token** (FORM-04). `check_token()` answers `TOKEN_USED` when the signature is good and the claim fails (today that is `TOKEN_SPAM`); a forged or unreadable token is still `TOKEN_SPAM`. The press that claims a token calls `in_flight( $key )` at the claim, `landed( $key, $row_id )` once its row is stored, and `bounced( $key )` before any bounce. A press that gets `TOKEN_USED` calls `wait_for_row( $key )`: a row id bounces to that row's own confirmation, the outcome a send that stored it was answered with, its reference included; 0 bounces `stale` with the values. Nothing is stored for the second press.
7. **An expired stash** (Review Focus 1): `read()`, given an id in the address whose transient is gone, answers `array( 'outcome' => 'stash-gone' )`; each form knows the outcome, draws itself empty under its sentence and marks no field.

**The words:**

| Where | Words |
| --- | --- |
| `busy-kept`, all four forms (replaces each form's own) | `Too many attempts were made from this connection in this hour, so this one was not taken. Your answers are still here: send the form again after %s.` |
| `busy`, all four forms | `Too many attempts were made from this connection in this hour, so this one was not taken. Send the form again after %s.` |
| `busy-unkept`, all four forms | `Too many attempts were made from this connection in this hour, so this one was not taken, and the answers were too long to keep here. Send the form again after %s.` |
| (the `%s` of the three) | `wp_date( get_option( 'time_format' ), WPCPM_Ceiling::resets_at( HOUR_IN_SECONDS ) )`; translators' comment: "the time the hour turns, in the site's time format" |
| `consent`, at the box | `Tick this box to send the form.` |
| `bad_url` (institution form, and `WPCPM_Field_Value`'s text for it) | `Give a web address starting with https://, or just the domain.` |
| `stash-gone`, all four forms (info) | `The answers kept for this form have expired, so the form is empty again. Nothing was sent: fill it in and send it again.` |
| A chosen file on any bounce | `WPCPM_Form_Errors::file_again()` |

- [ ] **Step 1: Write the failing checks**

In each form's suite, in its `ck()` style and with its own stand-ins (each check below is written once per form unless it names one; `<form>` is the form's own name):
- "a refused send opens the form with the summary: its heading counts the problems, and each links its field by the field's label": post with two required answers empty; the drawn page holds `<div class="wpcpm-errors" role="alert" tabindex="-1"><h2 class="wpcpm-errors__title">Two answers need a change</h2>`, then two `<a href="#<id>">` in the form's order, all before the first `<fieldset`; expected `true` for each of the three tests.
- "and the old generic sentence is gone": `strpos( $page, 'Please look at the answers marked below' )` is `false`.
- "consent is checked in the same pass as the other answers: one bounce marks the box and the missing answer together": post with consent unticked and one required answer empty; the stash's `problems` has the keys `array( <that answer>, 'consent' )`, the page holds `Tick this box to send the form.`, and no row is stored (`0` rows).
- "a busy connection with a good nonce keeps the answers and draws the form, with the sentence that says when to try again": claim the ceiling to its limit (`PER_HOUR`, the student form its own), post with a good nonce; the redirect carries the stash id, the stash's `outcome` is `busy-kept` with the values posted, and the page holds the `busy-kept` sentence ending `after 14:00.` (the suite's clock at 13:20 and `time_format` `H:i`) and the form's Send button.
- "a busy post with no valid nonce stores no stash": the same with the nonce refused; no transient with the form's prefix is written (`array()` of the suite's transients under it), the redirect carries `busy` bare, and the page holds the `busy` sentence and no `<form`.
- "a busy post too long to keep keeps nothing and says so": the same with a good nonce and a free text of `KEPT_MAX + 1` characters; no transient, `busy-unkept`, its sentence.
- "a refused address and a refused web address come back as typed, and bad_url has its own sentence": post `name@campus` and (institution form) `ftp://example.test`; the drawn inputs' values are `name@campus` and `ftp://example.test`, and the page holds `Give a web address starting with https://, or just the domain.`.
- sponsor form: "a bounce for any reason with chosen logos says at each logo that the file must be chosen again": post with a name missing and both logo `name`s set; each logo field's problem is `A chosen file cannot be kept when the form comes back. Choose the file again.`; "and a too-small logo is found in the same pass as a missing name": both problems in one stash.
- "a second press with the same token goes to the first press's confirmation and stores nothing": two POSTs with one token; one row; the second redirect carries the first row's reference.
- "a second press while the first is still in flight waits for its row": the first press's stand-in claims the token and calls `in_flight()`, the second press's `wait_for_row()` stand-in for `usleep()` writes the first row and `landed()` on its third read; one row, and the second redirect carries that row's reference.
- "a used token whose first press bounced bounces stale with the answers": the first press bounced (`bounced()` called); the second answers `stale` with its values and stores nothing.
- "a stash id whose answers have expired draws the form empty, with the sentence that says so, and marks no field" (Review Focus 1): a request with a stash id and no transient; the page holds the `stash-gone` sentence, the form, no `aria-invalid` and no `wpcpm-errors`.

`bin/test-form-guard.php`: "a token used once answers TOKEN_USED, not spam, and a forged one is still spam" (`array( 'ok', 'used', 'spam' )`); "token_key() is the claim's own key, and '' for a token that does not parse"; "in_flight(), landed() and bounced() record pending, the row id and nothing" (`array( 'pending', 42, null )`); "wait_for_row() answers the row once it lands, and 0 after six reads still pending, or at once when the record is gone".
`bin/test-form-stash.php`: "an id whose transient is gone reads as stash-gone, and no id reads as nothing" (`array( array( 'outcome' => 'stash-gone' ), array() )`); "fits() says yes at KEPT_MAX characters and no at one more".
`bin/test-ceiling.php`: "resets_at() is the start of the next window: 14:00:00 for any moment from 13:00:00 to 13:59:59 in an hour's window" (for `gmmktime( 13, 0, 0, ... )` and `gmmktime( 13, 59, 59, ... )` both `gmmktime( 14, 0, 0, ... )`).

- [ ] **Step 2: Run them to verify they fail**

One line per suite, as each first fails:

```text
$ php bin/test-mentor-application-form.php
FAIL a refused send opens the form with the summary: its heading counts the problems, and each links its field by the field's label
$ php bin/test-institution-application.php
FAIL a refused send opens the form with the summary: its heading counts the problems, and each links its field by the field's label
$ php bin/test-sponsor-application.php
FAIL a refused send opens the form with the summary: its heading counts the problems, and each links its field by the field's label
$ php bin/test-student-registration.php
FAIL a refused send opens the form with the summary: its heading counts the problems, and each links its field by the field's label
$ php bin/test-form-guard.php
PHP Fatal error:  Uncaught Error: Undefined constant WPCPM_Form_Guard::TOKEN_USED
$ php bin/test-form-stash.php
FAIL an id whose transient is gone reads as stash-gone, and no id reads as nothing
$ php bin/test-ceiling.php
PHP Fatal error:  Uncaught Error: Call to undefined method WPCPM_Ceiling::resets_at()
```

- [ ] **Step 3: The forms, the guard, the stash and the ceiling**

- `WPCPM_Ceiling::resets_at( $window )`: `( (int) floor( time() / max( 1, (int) $window ) ) + 1 ) * max( 1, (int) $window )`, the buckets `claim()` counts in.
- `WPCPM_Form_Guard::token_key( $scope, $token )`: the hash `check_token()` already claims with, `wp_hash( $scope . '|' . $issued . '|' . $random . '|' . $signature )`, from the same parse; `check_token()` calls it, so the two cannot disagree. `in_flight()`, `landed()`, `bounced()`, `row_of()` and `wait_for_row()` as the Interfaces give them (rule 6); `wait_for_row()` reads `row_of()` up to `$tries` times with `usleep( $pause_us )` between, the sleep behind a filterable callable so a suite stands it in.
- `WPCPM_Form_Stash::read( array $form )`: rule 7; `WPCPM_Form_Stash::fits( array $stash ): bool`: rule 5.
- In each form: the render (rule 1), the validation pass (rules 2 to 4), the handler's ceiling branch (rule 5), the dwell branch and the store step (rule 6), the messages map (the words above) and the "does not draw" list (rule 5). `WPCPM_Field_Value`'s text for `bad_url` (the words above).

- [ ] **Step 4: Run them to verify they pass**

The seven suites end `ALL PASS`.

- [ ] **Step 5: The checks every task ends with**

- [ ] **Step 6: Commit**

```bash
git add includes/modules/class-wpcpm-mentor-application-form.php includes/modules/class-wpcpm-institution-application.php includes/modules/class-wpcpm-sponsor-application.php includes/modules/class-wpcpm-student-registration-form.php includes/class-wpcpm-form-guard.php includes/class-wpcpm-form-stash.php includes/class-wpcpm-ceiling.php includes/class-wpcpm-field-value.php bin/test-mentor-application-form.php bin/test-institution-application.php bin/test-sponsor-application.php bin/test-student-registration.php bin/test-form-guard.php bin/test-form-stash.php bin/test-ceiling.php
git commit -m "Scan release 1: the public forms summarize their problems, keep every answer on every refusal, check once, and file one row per send" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 5: The Student Report Card and the Mentor Report Card

Findings: CARD-01, CARD-02, CARD-04 (kept work and marked fields), CARD-05 (the note twice), CARD-08, CARD-09 (outcomes where the student is), CARD-28, ADM-29 (the module move's network failure). Scope **L**. Read the six findings in full first.

**Files:**
- Modify: `includes/modules/class-wpcpm-student-report-form.php` (the handler at about `:1563-1622`, the bounce at `:1984-2006`, the hours box at `:897-915`, the render at `:1038-1220`), `includes/modules/class-wpcpm-student-feedback.php` (`:830`, `:936-941`, `:1489-1491`, `:1615-1644`)
- Modify: `includes/modules/class-wpcpm-group-sessions.php` (`:422-523`, the planner at `:1448-1553`), `includes/modules/class-wpcpm-mentor-calls.php` (`:905-908`, `:1430-1467`)
- Modify: `includes/modules/class-wpcpm-mentor-notes.php` (`handle_add()` at about `:362-422`, `render_form()` at about `:759-787`)
- Modify: `assets/js/modules.js` (the `catch` at about `:278-280`), `assets/css/student.css` (the hours box's message)
- Test: `bin/test-report-form.php`, `bin/test-feedback.php`, `bin/test-group-sessions.php`, `bin/test-student-modules.php` (runs `bin/js/modules-arrange.js`), `bin/js/modules-arrange.js`

**Interfaces:**
- Consumes: Task 1's `WPCPM_Flash::keep()`, `kept()`, `forget()`, `WPCPM_Form_Errors`; Task 3's `WPCPM_Dashboard_Card::message()` (markup only: these pages are not drawn in the card shell).
- Produces: the forms `report-form:<student record>`, `feedback:<form key>:<student record>`, `planner:<mentor id>`, `booking:<slot>:<student record>`; the posted field `wpcpm_from` on the report handler (`hours` or `form`); `WPCPM_Mentor_Notes::DUPLICATE_WINDOW = 60`; the hours box's message class `wpcpm-hours__message`.

**The rules:**
1. **The report form and the feedback forms** keep every posted answer on every refusal (an Airtable refusal and a validation refusal alike) and are drawn from the kept values. The rejected answers (`$rejected`, which the handler collects and drops today) become `array( field => sentence )`: a grade or an hours count that is not a number is `Write the number only, such as 85.` and any other rejected answer its validator's own sentence. The form opens with `WPCPM_Form_Errors::summary()` (level 3) and each refused field carries `attrs()` and `problem()`. The report form's disclosure is drawn open on any refusal, and the saved feedback form's disclosure on any outcome (CARD-09), with its status inside it.
2. **The hours box** (CARD-08) posts `wpcpm_from=hours`; the handler then returns to `#wpcpm-module-course`, the report form's disclosure is not opened, and the hours box prints its own status inside it: `WPCPM_Dashboard_Card::message( 'success', __( 'Hours saved.', 'wpcredits-program-manager' ), 'wpcpm-hours__message' )`, or `error` with the refusal. `student.css` draws `.wpcpm-hours__message` with the rules it gives `.wpcpm-notes__message` (copy them under the new name).
3. **The planner** (CARD-04) is drawn open with every posted field on any refusal: date, repeat rule, count, the extra dates, start time, length, places, topic; the refused date (a past date, a clash, a malformed extra date) is the problem of its own field, and the sentence names the date, `wp_date()` in the site's format. A one-to-one booking refused as `taken` or `busy` keeps the typed topic.
4. **The note form** (CARD-05) carries `data-wpcpm-once` and `data-wpcpm-busy="Saving the note"`, and its delete form `data-wpcpm-busy="Deleting the note"`; `handle_add()` refuses a note whose text, author and student are those of a note saved in the last `DUPLICATE_WINDOW` seconds, files nothing, and answers the outcome the first save answered.
5. **The module move** (CARD-28, ADM-29): `modules.js`'s `catch` does what its refusal branch does: `arrange( kept )`, and announces the refused sentence the page already gives that branch.

**The words:**

| Where | Words |
| --- | --- |
| The feedback form's Airtable refusal (replaces "Nothing has been lost - try again in a moment.") | `Airtable did not take your answers. They are still in the form below: send it again in a moment.` |
| The report form's Airtable refusal | `Airtable did not take your report. Your answers are still in the form below: save it again in a moment.` |
| A number field with words in it | `Write the number only, such as 85.` |
| Hours saved, inside the hours box | `Hours saved.` |
| Hours refused | `The hours were not saved. Write the number of hours only, such as 12.` |
| A planned date in the past | `%s is in the past. Choose a date from today on.` (`%s`: the date) |
| A planned date that clashes | `%s clashes with a session you already have. Choose another date or time.` |
| An extra date that cannot be read | `%s is not a date. Write it as YYYY-MM-DD.` (`%s`: as typed) |
| A note saved twice in a minute | (the first save's own outcome, unchanged) |

- [ ] **Step 1: Write the failing checks**

- `bin/test-report-form.php`:
  - "an Airtable refusal keeps every answer typed and draws the form from them, open": the stand-in for Airtable refuses the write; the redirect lands on `#wpcpm-report-form`, the drawn form's boxes hold the posted answers, the disclosure is `<details ... open`, and the page holds the report form's refusal sentence above;
  - "a rejected grade is named at its field, aria-invalid, its sentence beside it, and the summary links it": post `85%` as the grade; `WPCPM_Flash::kept( 'report-form:recSTUDENT0000001' )['problems']` is `array( <grade field> => 'Write the number only, such as 85.' )`, the grade input carries `aria-invalid="true"`, and the summary holds `One answer needs a change`;
  - "the hours box saves back to My course with Hours saved inside it, and does not open the report form": post with `wpcpm_from=hours`; the redirect ends `#wpcpm-module-course`, the hours box holds `<p class="wpcpm-hours__message wpcpm-hours__message--success is-success" role="status" tabindex="-1" data-wpcpm-card-message>Hours saved.</p>`, and the report form's `<details` has no `open`;
  - "a refused hours count keeps the typing and says so in the hours box": post `twelve`; the hours input's value is `twelve` and the box holds `The hours were not saved. Write the number of hours only, such as 12.` with `role="alert"`.
- `bin/test-feedback.php`:
  - "a refused feedback form keeps its fifteen answers and comes back open with its status inside": the drawn form's fifteen boxes hold the posted answers, its `<details` is `open`, the status is inside it;
  - "the Airtable refusal says the answers are still in the form": the page holds `Airtable did not take your answers. They are still in the form below: send it again in a moment.` and not `Nothing has been lost`;
  - "a saved form's disclosure is drawn open with its status inside it, not above the heading": after a save of `f2`, `#wpcpm-feedback-f2` is `open` and holds the success status; no status above the `Feedback forms` heading.
- `bin/test-group-sessions.php`:
  - "a clash on one of sixteen dates keeps the planner open with every field and every extra date, and marks the date that clashed": post sixteen dates with one clashing; the planner's `<details` is `open`, every field holds its posted value, and the clashing date's input carries `aria-invalid="true"` with `<date> clashes with a session you already have. Choose another date or time.`;
  - "a booking refused as taken keeps the topic": after `taken`, the booking textarea holds the typed topic;
  - "the note form and its delete form are guarded with their busy words": `data-wpcpm-once` and `data-wpcpm-busy="Saving the note"`, `data-wpcpm-busy="Deleting the note"`;
  - "the same note by the same mentor for the same student within a minute is filed once": two `handle_add()` posts one second apart; one note post (`1`), both answering the same outcome;
  - "and a minute and a second later it is filed again": `2`.
- `bin/js/modules-arrange.js`: "a move whose request fails on the network is put back and the refusal is said, as a refused move is": the stand-in's fetch rejects; the order is the kept one and the live region holds the refusal branch's sentence.

- [ ] **Step 2: Run them to verify they fail**

```text
$ php bin/test-report-form.php
FAIL an Airtable refusal keeps every answer typed and draws the form from them, open
$ php bin/test-feedback.php
FAIL a refused feedback form keeps its fifteen answers and comes back open with its status inside
$ php bin/test-group-sessions.php
FAIL a clash on one of sixteen dates keeps the planner open with every field and every extra date, and marks the date that clashed
$ php bin/test-student-modules.php
FAIL a move whose request fails on the network is put back and the refusal is said, as a refused move is
```

- [ ] **Step 3: The forms, the planner, the note guard and the move**

Each handler calls `WPCPM_Flash::forget( <its form> )` first and `WPCPM_Flash::keep( <its form>, <the posted fields, unslashed and not cleaned>, <the problems> )` before every refusing redirect; each render calls `WPCPM_Flash::kept( <its form> )` once and draws from `values` when it is not empty. The functions:
- `WPCPM_Student_Report_Form::handle_save()` (rules 1, 2), its bounce (the redirect's anchor from `wpcpm_from`), its render of the form and of the hours box;
- `WPCPM_Student_Feedback::handle_save()` and its render (rule 1);
- `WPCPM_Group_Sessions`' planning handler and `render_planner()` (rule 3), `WPCPM_Mentor_Calls`' booking handler and its topic box (rule 3);
- `WPCPM_Mentor_Notes::handle_add()`, a private `duplicate_of( string $record, int $author, string $text ): int` answering the note's id saved in the window or 0, and `render_form()` (rule 4);
- `modules.js`'s request `catch` (rule 5).

- [ ] **Step 4: Run them to verify they pass**

The four suites end `ALL PASS`.

- [ ] **Step 5: The checks every task ends with**

- [ ] **Step 6: Commit**

```bash
git add includes/modules/class-wpcpm-student-report-form.php includes/modules/class-wpcpm-student-feedback.php includes/modules/class-wpcpm-group-sessions.php includes/modules/class-wpcpm-mentor-calls.php includes/modules/class-wpcpm-mentor-notes.php assets/js/modules.js assets/css/student.css bin/test-report-form.php bin/test-feedback.php bin/test-group-sessions.php bin/test-student-modules.php bin/js/modules-arrange.js
git commit -m "Scan release 1: the Report Cards keep every answer and mark the field, the note is filed once, and a failed move is put back" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 6: The Institution Dashboard: enrollment, corrections, people and the panel

Findings: INS-02, IDB-02 (the import keeps everything), INS-05, IDB-16 (the file too big), INS-10 (the import's errors at the field), IDB-13, IDB-12 (the correction form), IDB-15 (the busy import forms), INS-14 (Add account and Invite a colleague), IDB-09 (toned and heard outcomes), INS-09 (the creating stage's own outcome). Scope **L**.

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-import-form.php` (`render_form()` at about `:204-330`, `handle_check()` at `:828-900`, `batch_values()` at `:1078-1150`, the file read at `:1193`, `render_message()` at `:1341-1357`, the messages at `:1443-1444`, the forms at `:211`, `:517`, `:569` and the cancel form after `:470`)
- Modify: `includes/modules/class-wpcpm-institution-create.php` (`:383`, `:400`)
- Modify: `includes/modules/class-wpcpm-institution-student-form.php` (the slots at `:167-172`, `render_field()` at `:412`, `:508-534`, `apply_rules()` at `:1070`)
- Modify: `includes/modules/class-wpcpm-institution-people.php` (`render_add_form()` at `:723-760`, the messages at `:1241-1290`), `includes/modules/class-wpcpm-institution-invite.php` (`render_form()` at `:268-310`)
- Modify: `includes/modules/class-wpcpm-institution-panel.php` (`:217-230`), `includes/modules/class-wpcpm-semester-report-screen.php` (its `render_message()`, about `:3673`)
- Modify: `assets/css/institution.css` (the tones for the three message families)
- Test: `bin/test-institution-import-form.php`, `bin/test-institution-create.php`, `bin/test-institution-student-form.php`, `bin/test-institution-people.php`, `bin/test-institution-invite.php`, `bin/test-institution-panel.php`, `bin/test-semester-report.php`

**Interfaces:**
- Consumes: Tasks 1 and 3 (`WPCPM_Dashboard_Card::message( $tone, $text, $base )`).
- Produces: the forms `import-batch:<institution record>`, `student-fix:<student record>`, `people-add:<institution record>`, `invite:<institution record>`; the import outcome `create-unreadable`; the problem `paste-too-long`.

**The rules:**
1. **The import** keeps the program, both dates, the tick, the pasted list and the one-student fields on every refusal and draws from them; a chosen file is `WPCPM_Form_Errors::file_again()` at the file input; each refusal is the problem of its field (start date, end date, the tick, the program, the paste box or the file), with the summary over the form and `attrs()`/`problem()` at the field. A pasted list over `WPCPM_Flash::KEPT_MAX` characters is not kept: the paste box gets the problem `The pasted list was too long to keep here: paste it again.`.
2. **A file over the limit or refused by PHP** (`UPLOAD_ERR_INI_SIZE`, `UPLOAD_ERR_FORM_SIZE`, or a size over `MAX_BYTES`) bounces `parse-too_large` at the file input, and never falls through to the paste box; the file input's hint says the limit.
3. **The four import forms** carry `data-wpcpm-once` and their busy words.
4. **The correction form** validates the start and end dates as a pair: when either fails the rule, both are refused and kept, nothing of the pair is written, and each refused slot has its own sentence at its field; the four-rule box goes, and the summary takes its place.
5. **Add account and Invite a colleague** show their labels (no `screen-reader-text` on the two labels; the placeholders stay as examples), keep the typed name and address on every refusal, and the conflict names the account, linked to its profile for an Administrator (`get_edit_user_link()`), its name alone otherwise.
6. **The panel, the import and the report messages** print through `WPCPM_Dashboard_Card::message( <tone>, <text>, <their own class> )` (`wpcpm-agreement-panel__message`, `wpcpm-import__message`, and the class the report screen prints, read there): success, error or info as their meaning is, heard and focused. `institution.css` colors `is-success` and `is-error` on the three classes with `#1a7f37` and `#b32d2e` on the leading border, as its `wpcpm-people__message` rules do.
7. **The creating stage** answers `create-unreadable`, not the check's `unreadable`.

**The words:**

| Where | Words |
| --- | --- |
| The file input's hint | `A CSV file of %s at most.` (`size_format( MAX_BYTES )`) |
| `parse-too_large`, at the file input (today's sentence) | `That file is too big. Send it in two halves.` |
| `paste-too-long`, at the paste box | `The pasted list was too long to keep here: paste it again.` |
| The paired dates refused, at the start date | `The start date has to come before the end date, and a placement cannot run longer than a year. Neither date was saved.` |
| at the end date | `Check this date against the start date. Neither date was saved.` |
| `create-unreadable` | `_n( 'The program records stopped answering partway. %s student record was created, and the rest are created by themselves as soon as the records answer again. Nothing needs to be sent again.', 'The program records stopped answering partway. %s student records were created, and the rest are created by themselves as soon as the records answer again. Nothing needs to be sent again.', $created )` |
| The conflict on Add account | `An account already uses that address: %s. Nothing was created.` (`%s`: the account's name, linked for an Administrator) |
| The four import forms' busy words | `Checking the list` (Check the list), `Creating the records` (Create these student records), `Sending` (the one-student form's Send; read its label), `Canceling` (Cancel the list) |

- [ ] **Step 1: Write the failing checks**

- `bin/test-institution-import-form.php`:
  - "a start date a year away keeps the program, the dates, the tick, a pasted list of 300 rows and the one-student fields, and marks the start date": the drawn form's controls hold the posted values (the paste box its 300 lines), the start date carries `aria-invalid="true"`, the summary holds `One answer needs a change`;
  - "a chosen file is asked for again at the file input": the file input's problem is `A chosen file cannot be kept when the form comes back. Choose the file again.`;
  - "a file over the limit is too large, never nothing was sent": a `tmp_name` of 300 KB; the outcome is `parse-too_large` and the page does not hold `Nothing was sent`;
  - "and a file PHP refused for its size is too large too": `error` `UPLOAD_ERR_INI_SIZE`, empty `tmp_name`; `parse-too_large`;
  - "a pasted list too long to keep is asked for again at the paste box": a paste of `KEPT_MAX + 1` characters refused for its rows; nothing is kept for the box and its problem is `The pasted list was too long to keep here: paste it again.`;
  - "the four import forms are guarded with their busy words": four `data-wpcpm-once`, and the four `data-wpcpm-busy` values above.
- `bin/test-institution-create.php`: "an Airtable hiccup while creating says how many were created and that the rest carry on, and the check's unreadable is not said": after 7 of 12 created and an unreadable answer, the outcome is `create-unreadable` and its sentence names `7 student records were created`.
- `bin/test-institution-student-form.php`: "a start date moved past the stored end date writes neither date, keeps both typed and marks both": the stored record's two dates are unchanged, both inputs hold the typing and carry `aria-invalid="true"` with the two sentences above; "each refused slot says its own reason at its field, and the summary counts them": two refused slots, `Two answers need a change`, and the four-rule box's text is not on the page.
- `bin/test-institution-people.php` and `bin/test-institution-invite.php`: "the name and address labels are visible": no `screen-reader-text` on the two labels; "a refused address keeps the typed name and address": the inputs hold them; "the conflict names the account": `An account already uses that address: <a href=` (as an Administrator) and the name alone otherwise.
- `bin/test-institution-panel.php` and `bin/test-semester-report.php`: "an upload refused is an error in its tone, as an alert, focusable; one accepted is a success, as a status": `class="wpcpm-agreement-panel__message wpcpm-agreement-panel__message--error is-error" role="alert" tabindex="-1" data-wpcpm-card-message` and the success's `role="status"`; the report screen's message likewise in its class.

- [ ] **Step 2: Run them to verify they fail**

```text
$ php bin/test-institution-import-form.php
FAIL a start date a year away keeps the program, the dates, the tick, a pasted list of 300 rows and the one-student fields, and marks the start date
$ php bin/test-institution-create.php
FAIL an Airtable hiccup while creating says how many were created and that the rest carry on, and the check's unreadable is not said
$ php bin/test-institution-student-form.php
FAIL a start date moved past the stored end date writes neither date, keeps both typed and marks both
$ php bin/test-institution-people.php
FAIL the name and address labels are visible
$ php bin/test-institution-invite.php
FAIL the name and address labels are visible
$ php bin/test-institution-panel.php
FAIL an upload refused is an error in its tone, as an alert, focusable; one accepted is a success, as a status
$ php bin/test-semester-report.php
FAIL an upload refused is an error in its tone, as an alert, focusable; one accepted is a success, as a status
```

- [ ] **Step 3: The import, the correction, the people, the panel**

- `WPCPM_Institution_Import_Form`: `render_form()` (draws from `WPCPM_Flash::kept( 'import-batch:<record>' )`, rules 1 and 3), `handle_check()` and every `bounce()` before staging (rule 1), the file read at about `:1193` as a private `uploaded_file( array $file ): string|WP_Error` that answers `WP_Error( 'parse-too_large' )` for rule 2's three cases, `render_message()` (rule 6), the messages (`create-unreadable`, `paste-too-long`).
- `WPCPM_Institution_Create::create_slice()`: answers `create-unreadable` in the creating state (rule 7).
- `WPCPM_Institution_Student_Form`: the date slots' rules as a pair in `apply_rules()`, `render_field()` with `attrs()` and `problem()` (rule 4).
- `WPCPM_Institution_People::render_add_form()` and `WPCPM_Institution_Invite::render_form()`, and their handlers' refusals (rule 5).
- `WPCPM_Institution_Panel`'s message printer and `WPCPM_Semester_Report_Screen::render_message()` (rule 6).

- [ ] **Step 4: Run them to verify they pass**

The seven suites end `ALL PASS`.

- [ ] **Step 5: The checks every task ends with**

- [ ] **Step 6: Commit**

```bash
git add includes/modules/class-wpcpm-institution-import-form.php includes/modules/class-wpcpm-institution-create.php includes/modules/class-wpcpm-institution-student-form.php includes/modules/class-wpcpm-institution-people.php includes/modules/class-wpcpm-institution-invite.php includes/modules/class-wpcpm-institution-panel.php includes/modules/class-wpcpm-semester-report-screen.php assets/css/institution.css bin/test-institution-import-form.php bin/test-institution-create.php bin/test-institution-student-form.php bin/test-institution-people.php bin/test-institution-invite.php bin/test-institution-panel.php bin/test-semester-report.php
git commit -m "Scan release 1: the Institution Dashboard keeps an enrollment on every refusal, says a file is too big, marks each field, and tones every outcome" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 7: The Sponsor Dashboard's cards

Findings: SDB-04, SDB-05, SDB-06, SDB-23 (kept typing and marked fields), SDB-08 (the interest once), SDB-21 (one sentence per offer move), SPA-16 (the tools' result). Scope **M**. Every outcome here already returns to its card and, after Task 3, is printed inside it.

**Files:**
- Modify: `includes/modules/class-wpcpm-sponsor-profile.php` (`keep_typing()` at `:359`, `:572`, the fields at `:764`), `includes/modules/class-wpcpm-sponsor-offers.php` (`keep_typing()` at `:1815`, the messages at `:1727`, `:2013-2059`, `:2262`, the `$field` helper at `:2679`), `includes/modules/class-wpcpm-sponsor-event-planner.php` (`:207`, `:224`, `:605`, `:1058`, its keep at `:1338`, `leave_typed()` at `:1782`), `includes/modules/class-wpcpm-sponsor-mentors.php` (the messages at `:47-51`, the handler at `:144-211`), `includes/modules/class-wpcpm-sponsor-tier-card.php` (the messages at `:84`), `includes/modules/class-wpcpm-sponsor-tools.php` (`render_message()` at `:489-502`, `leave()` at `:775-784`, `may_claim_reason()` at `:153-200`)
- Test: `bin/test-sponsor-cards.php`, `bin/test-sponsor-offers.php`, `bin/test-sponsor-event-planner.php`, `bin/test-sponsor-tools.php`, `bin/test-sponsors-dashboard.php`

**Interfaces:**
- Consumes: Tasks 1 and 3.
- Produces: the three cards keep their typing and their problems through `WPCPM_Flash::keep( <form>, $typed, $problems )` and draw from `WPCPM_Flash::kept()`, in place of `WPCPM_Typed_Text::keep()` and `kept()` (the store-per-family rule of the Global Constraints); the form names stay those `typed_form()` gives today. `WPCPM_Sponsor_Mentors::REPEAT_WINDOW = 30 * DAY_IN_SECONDS`; the outcomes `mentor-interest-unsent` and `mentor-interest-repeat`, and `offer-paused`, `offer-resumed`, `offer-taken-back`, `offer-ended` in place of `offer-state-saved`; the tools' reasons `tool-closed`, `tool-off`, `tool-test`, `tool-partners`, `tool-claimed`, `tool-empty`, `tool-unshared`, one per answer of `may_claim_reason()`.

**The rules:**
1. **The profile** keeps the typing before its value refusal (`:572`) and refuses the one field at fault with its own sentence; **the offer form** keeps the typing on every `offer-rejected` leave, the codes and the shared code excepted as the code says, and opens the form it came from; **the planner's ceiling** leaves through `leave_typed()`. A kept text is drawn back through `WPCPM_Typed_Text::kept_attr()` in an input, as today, so a typed `&` comes back as typed.
2. **Every refused field** on the three forms carries `attrs()` and `problem()`, and the form opens with the summary (level 4, inside the card); every hint is tied to its control by `aria-describedby` (the contact email note, the description note and the logo note among them).
3. **The interest** (SDB-08): an interest that could not be mailed answers `mentor-interest-unsent` (a warning, the row written as today), in place of `mentor-interest-failed` for that case; a second interest by the same sponsor in the same mentor within `REPEAT_WINDOW` answers `mentor-interest-repeat`, writes no row and spends none of the day's `PER_DAY`.
4. **The offer moves** (SDB-21) each say their own sentence, naming the offer.
5. **The tools' result** (SPA-16) prints through `WPCPM_Dashboard_Card::message()` with its tone and is focused on load; a refusal is worded by its reason.
6. **The ceilings** (SDB-23) name the action and read the number from the constant each claim passes: `WPCPM_Sponsor_Event_Planner::PER_HOUR` (the planner, `sevent-ceiling`), `WPCPM_Sponsor_Mentors::PER_DAY` (the interest, `mentor-interest-ceiling`) and `WPCPM_Sponsor_Interests::PER_DAY` (the Sponsorship card, `tier-ceiling`; read the claim at about `:300-309` to confirm it is this constant).

**The words:**

| Where | Words |
| --- | --- |
| Profile, the website (at the field, after its label in the summary) | `Enter a web address that starts with https://. Nothing on the profile was saved.` |
| Profile, the contact email | `Enter one email address, such as name@example.org. Nothing on the profile was saved.` |
| Offer, the title missing | `Give the offer a title.` |
| Offer, a link with a name or password in it | `Give the link without a user name or password in it.` |
| Offer, the last day unreadable | `Write the last day as YYYY-MM-DD.` |
| `mentor-interest-unsent` (warning) | `Your interest is on record. The program will see it, though nobody could be emailed about it right now.` |
| `mentor-interest-repeat` (info) | `You already told the program you would like to sponsor %1$s, on %2$s. It is on record, so there is nothing to send again.` (`%1$s` the mentor's name, `%2$s` the date in the site's format) |
| `offer-paused` | `Paused: %s is hidden until you resume it.` |
| `offer-resumed` | `Resumed: %s is offered again.` |
| `offer-taken-back` | `Taken back: %s is a draft again, and nobody can claim it.` |
| `offer-ended` | `Ended: %s is closed for good, and its codes stay in the usage report.` |
| `tool-closed` | `That offer is closed, so its code cannot be shown.` |
| `tool-off` | `That offer is switched off for now.` |
| `tool-test` | `That offer is a test, so only the sponsor and Administrators see it.` |
| `tool-partners` | `That offer is for partners of the program only.` |
| `tool-claimed` | `You have already claimed this offer: your code is below.` |
| `tool-empty` | `That offer has run out of codes.` |
| `tool-unshared` | `That offer is not shared with your account.` |
| `sevent-ceiling` | `_n( 'You can plan or change an event %1$s time an hour. Your plan is still here: send it again after %2$s.', 'You can plan or change events %1$s times an hour. Your plan is still here: send it again after %2$s.', PER_HOUR )` (`%2$s`: `resets_at( HOUR_IN_SECONDS )` in the site's time format) |
| `mentor-interest-ceiling` | `_n( 'You can tell the program about %s mentor a day. Try again tomorrow.', 'You can tell the program about %s mentors a day. Try again tomorrow.', PER_DAY )` |
| `tier-ceiling` | `_n( 'You can send %s message a day from the Sponsorship card. Try again tomorrow.', 'You can send %s messages a day from the Sponsorship card. Try again tomorrow.', PER_DAY )` |

- [ ] **Step 1: Write the failing checks**

- `bin/test-sponsor-cards.php`:
  - "a refused profile keeps every typed box, the long ones included, and marks the one field at fault": post a bad website with a changed "Anything else" and description; `WPCPM_Flash::kept( <the profile's form> )` holds every typed box and `problems` is `array( 'website' => 'Enter a web address that starts with https://. Nothing on the profile was saved.' )`; the drawn website input carries `aria-invalid="true"`, the contact email input does not, and the summary's link reads `<a href="#<website id>">Website</a>: Enter a web address`, once;
  - "every hint on the profile is tied to its control": the three hints' ids are in their controls' `aria-describedby`;
  - "an interest no contact could be mailed about is on record, and says so": no contact; the outcome is `mentor-interest-unsent`, one audit row is written;
  - "a second interest in the same mentor within 30 days is already sent, with no row and nothing spent": `mentor-interest-repeat`, the audit rows stay at one, and the ceiling's count is unchanged;
  - "and after 30 days it is a new interest": with the first row dated 31 days ago, `mentor-interest-sent`, two rows;
  - "the three ceilings name their action and read their constant": the three sentences above with `10`, `5` and `5` filled in.
- `bin/test-sponsor-offers.php`: "every offer-rejected leave keeps the typing and opens the form it came from" (for each of the six refusals SDB-05 lists: the kept title and text equal the posted ones, and the card's `open` is the form's key); "the missing title is marked at the title" (`aria-invalid="true"` on the title, `Give the offer a title.`); "each of the four moves says its own sentence, naming the offer" (`Paused: Cloud credits is hidden until you resume it.` and the three others for an offer titled `Cloud credits`).
- `bin/test-sponsor-event-planner.php`: "the planner's ceiling keeps the plan and says when to try again" (the kept title equals the posted one and the sentence ends with the time); "a refused field is marked at the field".
- `bin/test-sponsor-tools.php`: "each of the seven reasons is its own sentence" (the seven sentences above, keyed by reason); "the result is a status, a refusal an alert, focusable" (`role="status"` for the code shown, `role="alert"` for `tool-empty`, each with `data-wpcpm-card-message`).

- [ ] **Step 2: Run them to verify they fail**

```text
$ php bin/test-sponsor-cards.php
FAIL a refused profile keeps every typed box, the long ones included, and marks the one field at fault
$ php bin/test-sponsor-offers.php
FAIL every offer-rejected leave keeps the typing and opens the form it came from
$ php bin/test-sponsor-event-planner.php
FAIL the planner's ceiling keeps the plan and says when to try again
$ php bin/test-sponsor-tools.php
FAIL each of the seven reasons is its own sentence
```

- [ ] **Step 3: The cards**

- `WPCPM_Sponsor_Profile::keep_typing( $record, array $typed, array $row, array $problems = array() )` and `WPCPM_Sponsor_Offers::keep_typing( $form, array $typed, array $raw, $existing, array $problems = array() )` keep through `WPCPM_Flash::keep()`; the event planner's keep at `:1338` likewise; each card's form render reads `WPCPM_Flash::kept()` (rules 1, 2).
- `WPCPM_Sponsor_Profile`'s save handler at `:572` (rule 1), its field printer at `:764`, `WPCPM_Sponsor_Offers`' `$field` helper at `:2679` and the planner's at `:605` (rule 2).
- `WPCPM_Sponsor_Mentors::handle_interest()` and a private `repeat_of( string $record, string $mentor ): int` answering the last interest's time within `REPEAT_WINDOW`, or 0 (rule 3); `messages()`.
- `WPCPM_Sponsor_Offers`' state handler at about `:2262` and `messages()` (rule 4).
- `WPCPM_Sponsor_Tools::render_message()`, `leave()` and the claim handler's refusal from `may_claim_reason()` (rule 5).
- The three `messages()` maps (rule 6).

- [ ] **Step 4: Run them to verify they pass**

The five suites end `ALL PASS`.

- [ ] **Step 5: The checks every task ends with**

- [ ] **Step 6: Commit**

```bash
git add includes/modules/class-wpcpm-sponsor-profile.php includes/modules/class-wpcpm-sponsor-offers.php includes/modules/class-wpcpm-sponsor-event-planner.php includes/modules/class-wpcpm-sponsor-mentors.php includes/modules/class-wpcpm-sponsor-tier-card.php includes/modules/class-wpcpm-sponsor-tools.php bin/test-sponsor-cards.php bin/test-sponsor-offers.php bin/test-sponsor-event-planner.php bin/test-sponsor-tools.php bin/test-sponsors-dashboard.php
git commit -m "Scan release 1: the Sponsor Dashboard keeps typing on every refusal, marks the field, says each move, and files an interest once" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 8: The Institutions and Sponsors screens in wp-admin

Findings: INS-01, INS-10 (the decision form), SPA-02, SPA-31 (kept typing and marked fields), INS-23 (the link form), SPA-03 (the stale Edit form), INS-06 (the reject's acknowledgement). Scope **M**.

**Files:**
- Modify: `includes/modules/class-wpcpm-institutions.php` (`handle_info()` at about `:1253-1327`, `handle_reject()` at `:1353-1410`, the messages at `:2568-2570`, `render_decision_form()` at `:4182-4250`, `handle_link()` at `:899-910`, `render_link_form()` at `:5700-5735`, the link words at `:5800-5815`)
- Modify: `includes/modules/class-wpcpm-sponsors.php` (`refusal()` at `:2008`, `clean_fields()` at `:1793`, `handle_add()` at `:1510`, `handle_edit()` at `:1616`, the messages at `:793-817`, the fields at `:3858-4040`, the attach branch at `:1452-1468`)
- Modify: `includes/modules/class-wpcpm-sponsors-index.php` (the row keeps `modified` and `modified_by`; read where the row is written)
- Test: `bin/test-institutions-screen.php`, `bin/test-sponsors-screen.php`, `bin/test-unlinked-link.php`

**Interfaces:**
- Consumes: Tasks 1 and 2 (`WPCPM_Settings::fingerprint()` for the sponsor's version when the row holds no stamp).
- Produces: `WPCPM_Sponsors::FIELD_VERSION = 'wpcpm_version'`; the outcomes `app-rejected-unsent`, `sponsor-stale`, `link-no-institution`; `clean_fields( array $posted, array $current ): array{values: array, problems: array<string,string>}`; the forms `sponsor-add:new`, `sponsor-edit:<sponsor key>` for `WPCPM_Flash::keep()`.

**The rules:**
1. **The question to an applicant** (INS-01) is kept (`WPCPM_Typed_Text::keep_box()`) before the `app-not-sent` and `app-no-email` redirects too, and every refusal goes back to the opened application, where the box is drawn with `attrs()` and `problem()` (INS-10).
2. **The reject** (INS-06) keeps the send's result: sent is `app-rejected`, as today; not sent (no usable address, or the mail refused) is `app-rejected-unsent`.
3. **The link form** (INS-23): the institution field is `required`, an empty one is refused with its own sentence before any write, and the form carries `data-wpcpm-once` with `data-wpcpm-busy="Linking"`.
4. **The sponsor Add and Edit forms** (SPA-02) refuse field by field: `clean_fields()` names each field it refused with its sentence; the screen opens with the summary in a `notice notice-error` and marks each field; the typing is kept as today.
5. **The stale Edit** (SPA-03): the form posts `FIELD_VERSION`, the row's `modified` stamp (or, for a row written before this release, the fingerprint of the row as drawn); a save whose stamp differs from the stored one writes nothing, keeps the typing, and says who changed the sponsor and when (`modified_by`'s display name, `wp_date()` of `modified` in the site's date and time formats; with no `modified_by`, the sentence without the name). Every write of the row through the Add and Edit forms and the sync stamps `modified` and `modified_by`.
6. **Attach account** (SPA-31) keeps the typed address and says why a refused attach was refused when the policy gives a reason.

**The words:**

| Where | Words |
| --- | --- |
| `app-rejected-unsent` (warning) | `The application is rejected. No acknowledgement could be sent: the application has no usable address, or the mail host refused the message.` |
| The question box, refused | (the refusal's existing sentence, now at the box as well as in the notice) |
| `link-no-institution`, at the field | `Choose an institution first. Nothing was written.` |
| Sponsor, the name missing | `Give the sponsor a name.` |
| Sponsor, the website | `Enter a web address that starts with https://.` |
| Sponsor, the contact email | `Enter one email address, such as name@example.org.` |
| Sponsor, extra events over the limit | `Give a whole number from 0 to %s.` (the limit) |
| `sponsor-rejected` (replaces the sentence listing every rule) | `Nothing was saved. The fields marked below need a change.` |
| `sponsor-stale` (error) | `Nothing was saved: %1$s changed this sponsor at %2$s, after you opened the form. Your changes are still in the form: check them against the record and save again.` |
| `sponsor-stale`, no name known | `Nothing was saved: this sponsor was changed at %s, after you opened the form. Your changes are still in the form: check them against the record and save again.` |
| `attach-refused` with a reason | `That account cannot act for this sponsor: %s` (the reason's sentence) |

- [ ] **Step 1: Write the failing checks**

- `bin/test-institutions-screen.php`:
  - "a question whose mail fails is kept in its box, and the press goes back to the opened application": `WPCPM_Mail::send_to()` stood in as refusing; `WPCPM_Typed_Text::kept_box( WPCPM_Typed_Text::box_form( 'iapp-info', 42 ), 'question' )` is the typed question, and the redirect names the application (`application=42`, as the queue's link to an opened application does; read it there);
  - "and so is one to an applicant with no address": the same with no address, outcome `app-no-email`;
  - "a refused question is marked at its box": the drawn box carries `aria-invalid="true" aria-describedby="<box id>-problem"`;
  - "a reject whose acknowledgement could not be sent says so": outcome `app-rejected-unsent` and its sentence; "and one that was sent says the applicant has the acknowledgement": `app-rejected`.
- `bin/test-unlinked-link.php`: "Link with no institution chosen is refused at the field, and nothing is written": outcome `link-no-institution`, the Students row unchanged; "the link form is guarded against a second press with its busy word": `data-wpcpm-once` and `data-wpcpm-busy="Linking"`; the institution control carries `required`.
- `bin/test-sponsors-screen.php`:
  - "a refused website names the website alone, at its field, and the summary links it": `clean_fields()`'s `problems` is `array( 'website' => 'Enter a web address that starts with https://.' )`; the screen holds `<div class="wpcpm-errors notice notice-error"` with `<a href="#<website id>">Website</a>: Enter a web address that starts with https://.` and the notice `Nothing was saved. The fields marked below need a change.`;
  - "two refused fields are two sentences, each at its field": `Two answers need a change`;
  - "a stale Edit form is refused with the typing kept and the sentence naming who changed the sponsor and when" (Review Focus 2): store the row, draw the Edit form (its `wpcpm_version`), save the row as Administrator 9 at 14:05, post the first form with a changed website; the stored website is the second Administrator's, the outcome is `sponsor-stale`, its sentence names that Administrator and `14:05`, and the kept typing holds the posted website;
  - "a fresh Edit form saves as before": the website is written;
  - "a row from before this release is stamped by its fingerprint and held to the same rule": a row with no `modified`; the form's `wpcpm_version` is `WPCPM_Settings::fingerprint( $row )`, and a change made since refuses it;
  - "Attach account keeps the typed address when no account has it": the drawn box holds the typed address with `attach-no-account`.

- [ ] **Step 2: Run them to verify they fail**

```text
$ php bin/test-institutions-screen.php
FAIL a question whose mail fails is kept in its box, and the press goes back to the opened application
$ php bin/test-unlinked-link.php
FAIL Link with no institution chosen is refused at the field, and nothing is written
$ php bin/test-sponsors-screen.php
FAIL a refused website names the website alone, at its field, and the summary links it
```

- [ ] **Step 3: The screens**

- `WPCPM_Institutions::handle_info()` (rule 1), `render_decision_form()` (rule 1's marking), `handle_reject()` and `queue_messages()` (rule 2), `handle_link()` and `render_link_form()` (rule 3).
- `WPCPM_Sponsors::clean_fields()` (rule 4, its new answer), `refusal()` (replaced by the problems), `handle_add()` and `handle_edit()` (rules 4, 5; `WPCPM_Flash::keep( 'sponsor-edit:<key>', ... )`), the Add and Edit fields' printer (`attrs()`, `problem()`, the hidden `FIELD_VERSION`), the attach branch (rule 6).
- `WPCPM_Sponsors_Index`'s row writer (rule 5's stamps).

- [ ] **Step 4: Run them to verify they pass**

The three suites end `ALL PASS`.

- [ ] **Step 5: The checks every task ends with**

- [ ] **Step 6: Commit**

```bash
git add includes/modules/class-wpcpm-institutions.php includes/modules/class-wpcpm-sponsors.php includes/modules/class-wpcpm-sponsors-index.php bin/test-institutions-screen.php bin/test-sponsors-screen.php bin/test-unlinked-link.php
git commit -m "Scan release 1: the Institutions and Sponsors screens keep the typing, mark each field, refuse a stale sponsor edit, and say whether a rejection was acknowledged" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 9: The mentor application queue and the accounts' invitations

Findings: ADM-02 (a decision twice), ADM-05 (outcomes that name nobody), ADM-06 (the refused note far from its box). Scope **M**.

**Files:**
- Modify: `includes/modules/class-wpcpm-mentor-application-queue.php` (`handle_select()` at `:387`, `handle_info()` at `:421`, `handle_decline()` at `:449`, `mail()` at `:363`, `note()` at `:307-331`, `locked()` at `:511`, `leave()` at `:758-771`, the messages at `:853-871`, the decision form at about `:1876-1887`, `render_row()` at `:1100`)
- Modify: `includes/modules/trait-wpcpm-accounts-screen.php` (`:381-387`, `:491-501`), `includes/class-wpcpm-mail.php` (`invite_outcome()` and the sentence at about `:1114`)
- Test: `bin/test-mentor-application-queue.php`, `bin/test-students-screen.php`

**Interfaces:**
- Consumes: `locked()`, `WPCPM_Mentor_Application::events()`, `last_at()`; Task 1.
- Produces: `private static function send_mail( WP_Post $post, $email, $id, array $more = array() ): bool` (`mail()` stays for the presses outside the lock); the outcome `mapp-decided`; the flash detail carries `who` (the applicant's name) and `reference`; `leave( $status, $message = '', $land = 0 )`, `$land` the row to land on.

**The rules:**
1. **Select, Ask for more information and Decline** run their mail and their write inside `locked()`, as Withdraw does: under the lock the row is read again, and a row no longer in a state the press is offered from answers `mapp-decided`, sends nothing and writes nothing. The mail inside the lock goes through `send_mail()`, which answers whether it went: inside the `try` nothing may redirect (`exit` runs no `finally`, so the lock would be held for its whole lifetime). A mail that did not go answers `mapp-not-sent` and writes nothing, as today.
2. **Every queue outcome** names the applicant and the reference, and lands on the row's anchor, `#wpcpm-mentor-application-<ID>` (`render_row()`'s own), when the row is still listed in the view, else on the next row's, else on the view's top.
3. **A refused note** goes back to its row's anchor, its box drawn with `attrs()` and `problem()` and its typing (kept as today); the notice at the top names the applicant.
4. **A row's invitation** (ADM-05) names the person it went to.

**The words:**

| Where | Words |
| --- | --- |
| `mapp-decided` (warning) | `%1$s already decided this application: %2$s, on %3$s. Nothing was sent.` (`%1$s` the Administrator's display name, `%2$s` the decision's event as the history prints it, such as "selected", `%3$s` the date and time in the site's formats) |
| Every queue outcome | `%1$s (%2$s): %3$s` with the translators' comment `/* translators: 1: the applicant's name, 2: the application's reference, such as MAPP-2026-0007, 3: what was done, the outcome's own sentence. */` |
| A refused note, at the box | (the note's existing sentence) |
| A row's invitation sent | `Invitation email sent to %s.` followed by the second sentence the mail layer gives today, as it is (`%s`: the display name) |

- [ ] **Step 1: Write the failing checks**

- `bin/test-mentor-application-queue.php`:
  - "two Selects on one row send one email: the second, under the lock, finds the row selected and answers who decided it and when": the first Select runs whole; the second's `begin()` is stood in as having read the row as `new` before the first's write (the suite's `forget_cached()` stand-in hands the second press the row as written under the lock); one mail sent (`1`), the second's outcome `mapp-decided`, its sentence naming Administrator 7, `selected` and the time;
  - "a Decline after a Select sends nothing": outcome `mapp-decided`, mails `1`, the state `selected`;
  - "a Select whose mail fails writes nothing and lets the lock go": `mapp-not-sent`, the state `new`, and `WPCPM_Lock::until( <the row's lock> )` is `0`;
  - "every outcome names the applicant and the reference": the flash's sentence begins `Ada Example (MAPP-2026-0007): `;
  - "the press lands on the next row's anchor when the decided row left the view": the redirect ends `#wpcpm-mentor-application-<next ID>`;
  - "a refused note lands on its row, its box marked and its typing kept": the redirect ends `#wpcpm-mentor-application-<ID>` and the drawn box carries `aria-invalid="true"` and holds the typing.
- `bin/test-students-screen.php`: "a row's invitation names the person it went to": the notice begins `Invitation email sent to Ada Example.`.

- [ ] **Step 2: Run them to verify they fail**

```text
$ php bin/test-mentor-application-queue.php
FAIL two Selects on one row send one email: the second, under the lock, finds the row selected and answers who decided it and when
$ php bin/test-students-screen.php
FAIL a row's invitation names the person it went to
```

- [ ] **Step 3: The queue and the invitation's words**

- `handle_select()`, `handle_info()`, `handle_decline()` (rule 1: each calls `begin()`, `note()` and `confirmed_address()` outside the lock, then `self::locked( $post, static function ( WP_Post $row ) use ( ... ) { ... } )`, whose work re-reads the state, calls `send_mail()`, then `decide()`, and answers the outcome); `send_mail()`; `leave()` and `messages()` (rules 2, 3); the note box in the decision form (rule 3).
- `WPCPM_Mail::invite_outcome()` and the accounts trait's row invitation (rule 4).

- [ ] **Step 4: Run them to verify they pass**

The two suites end `ALL PASS`.

- [ ] **Step 5: The checks every task ends with**

- [ ] **Step 6: Commit**

```bash
git add includes/modules/class-wpcpm-mentor-application-queue.php includes/modules/trait-wpcpm-accounts-screen.php includes/class-wpcpm-mail.php bin/test-mentor-application-queue.php bin/test-students-screen.php
git commit -m "Scan release 1: Select, Ask for more information and Decline run under the application's lock, and every outcome names the applicant" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 10: The Track Builder and the Emails tool

Findings: SET-19 (every error at its field), SET-20 (a stale track or email edit), LOG-16 (the email edit's `at`), SET-22 (Publish twice), SET-23 (a question move that fails on the network). Scope **M**.

**Files:**
- Modify: `includes/tools/class-wpcpm-track-builder.php` (`handle_save()` at `:695-749`, `handle_duplicate()` and `handle_new()` at `:794`, `:877`, the properties at `:1253-1317`, `refuse()` at `:1408`), `includes/tools/class-wpcpm-track-builder-screen.php` (`render_publish_confirm()` at `:654-695`, the property form at about `:747`, `render_button()` at `:1084-1091`, the notice at `:1266-1275`), `includes/tools/class-wpcpm-track-editor.php` (`handle_move()` at `:247`, its success answer at `:285`)
- Modify: `includes/tools/class-wpcpm-emails.php` (`handle_save()` at `:835-868`, the editor's form), `includes/mail/class-wpcpm-mail-templates.php` (`write_edit()` at `:676`, `save()` at `:788`)
- Modify: `assets/js/track-editor.js` (the move's success at about `:295` and the `catch` at `:337-339`)
- Test: `bin/test-track-builder.php`, `bin/test-emails-screen.php`, `bin/test-mail-templates.php`, and a new `bin/js/track-editor-move.js` run by `bin/test-track-builder.php` the way `bin/test-student-modules.php` runs `bin/js/modules-arrange.js`

**Interfaces:**
- Consumes: Task 1.
- Produces: `WPCPM_Track_Builder::FIELD_REVISION = 'wpcpm_track_revision'` (the track post's `post_modified_gmt`, drawn as a hidden field of the property form with the id `wpcpm-track-revision`); `handle_move()`'s success answer gains `revision` (the post's `post_modified_gmt` after the move); `WPCPM_Emails::FIELD_EDIT_AT = 'wpcpm_edit_at'` (the stored edit's `at`, 0 for none); `WPCPM_Mail_Templates::save( $id, $subject, $body, $user_id, $base_at = null )` answers `WP_Error( 'wpcpm_edit_stale' )` when `$base_at` is given and differs from the stored edit's `at`; the outcomes `track-stale`, `email-stale`.

**The rules:**
1. **A refused track** carries every error of `WPCPM_Track_Store::check()`, not the first: the screen opens with the summary in a `notice notice-error`, each linking its field, and each field carries `attrs()` and `problem()`. The typing is kept as `refuse()` keeps it today.
2. **A stale track** (its revision differs) and **a stale email** (its `at` differs) are refused with the typing kept and the sentence naming who and when. **A person's own question move is not a change made elsewhere:** the move's answer carries the new `revision`, and `track-editor.js` writes it into `#wpcpm-track-revision`, so the next Save of the same page is not stale.
3. **Publish, Check and Take off** carry `data-wpcpm-once` with their busy words.
4. **A question move that fails on the network** puts the kept order back and says so.

**The words:**

| Where | Words |
| --- | --- |
| `track-stale` | `Nothing was saved: %1$s saved this track at %2$s, after you opened it. Your changes are still in the form: check them against the track's History and save again.` |
| `email-stale` | `Nothing was saved: %1$s changed this email at %2$s, after you opened it. Your wording is still in the boxes: check it against the email as it is now, and save again.` |
| Publish's busy word | `Publishing` |
| Check's busy word | `Checking` |
| Take off's busy word | `Taking it off` |
| A question move that failed on the network | `The move could not be sent. The questions are back in the order last saved.` |

- [ ] **Step 1: Write the failing checks**

- `bin/test-track-builder.php`:
  - "a track with three problems names all three in the summary, each linked, and each field is marked": the redirect's flash holds three problems keyed by field, and the drawn screen holds `Three answers need a change` and three `aria-invalid="true"`;
  - "a stale track save is refused with the typing kept and who saved it named": draw the property form (its `wpcpm_track_revision`), save the track as Administrator 9, post the first form; nothing written, outcome `track-stale`, the kept values equal the posted ones;
  - "a move then a Save on the same page is not stale": draw the form, call `handle_move()` (its answer's `revision`), post the property form with that revision; the property is written and the outcome is the save's success;
  - "a fresh save writes as before";
  - "Publish, Check and Take off are guarded with their busy words": `data-wpcpm-once` with `data-wpcpm-busy="Publishing"`, `"Checking"` and `"Taking it off"`.
- `bin/js/track-editor-move.js`: "a move's answer writes the new revision into the property form": the hidden field's value is the answer's `revision`; "a question move whose request fails puts the kept order back and says the move could not be sent": the order is the kept one and the notice holds the sentence above.
- `bin/test-mail-templates.php`: "save() with a base that is not the stored edit's at refuses, and writes nothing" (`wpcpm_edit_stale`, the stored edit unchanged); "save() with no base writes as before".
- `bin/test-emails-screen.php`: "the editor posts the stored edit's at": `<input type="hidden" name="wpcpm_edit_at" value="<at>"`; "a stale save keeps the wording in the boxes and says who changed the email and when": outcome `email-stale`, the drawn boxes hold the posted subject and body.

- [ ] **Step 2: Run them to verify they fail**

```text
$ php bin/test-track-builder.php
FAIL a track with three problems names all three in the summary, each linked, and each field is marked
$ php bin/test-mail-templates.php
FAIL save() with a base that is not the stored edit's at refuses, and writes nothing
$ php bin/test-emails-screen.php
FAIL the editor posts the stored edit's at
```

- [ ] **Step 3: The builder, the editor and the move**

- `WPCPM_Track_Builder::handle_save()`, `handle_duplicate()`, `handle_new()` and `refuse( $post_id, array $problems, array $typed )` (rule 1: the problems map in place of one message; rule 2: the revision check before `WPCPM_Track_Store::save()`).
- The builder screen's property form (the hidden `FIELD_REVISION`, the fields' marking, the summary in place of the first-error notice) and `render_button()` (rule 3).
- `WPCPM_Track_Editor::handle_move()`'s answer (rule 2), `track-editor.js`'s success branch and its `catch` (rules 2, 4).
- `WPCPM_Mail_Templates::save()` (rule 2), `WPCPM_Emails::handle_save()` and the editor's form (rule 2).

- [ ] **Step 4: Run them to verify they pass**

The three suites end `ALL PASS`.

- [ ] **Step 5: The checks every task ends with**

- [ ] **Step 6: Commit**

```bash
git add includes/tools/class-wpcpm-track-builder.php includes/tools/class-wpcpm-track-builder-screen.php includes/tools/class-wpcpm-track-editor.php includes/tools/class-wpcpm-emails.php includes/mail/class-wpcpm-mail-templates.php assets/js/track-editor.js bin/test-track-builder.php bin/test-emails-screen.php bin/test-mail-templates.php bin/js/track-editor-move.js
git commit -m "Scan release 1: the Track Builder names every problem at its field, a stale track or email save is refused, Publish runs once" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 11: The header notices and the Mentor Status Checker

Findings: SET-04, ADM-19 (the notices saved from a stale page), SET-08 (Promote's focus), SET-17 (the progress bar's name and its announcements). Scope **M**.

**Files:**
- Modify: `includes/tools/class-wpcpm-header-notices.php` (`handle_save()` at `:105-123`, `render_admin_page()` at `:129-197`), `includes/class-wpcpm-notices.php` (`save()` at `:240-250` gains `save_one()`)
- Modify: `includes/tools/class-wpcpm-mentor-checker.php` (the progress bar at `:936`), `assets/js/mentor-checker.js` (`upsertRow()` at `:132-151`, Promote all at `:430-450`, Promote at `:462-490`, the progress announcements at `:350`)
- Test: `bin/test-notices.php`, `bin/test-mentor-checker.php`, and a new harness `bin/js/mentor-checker-promote.js` run by `bin/test-mentor-checker.php` (skip without node, as `bin/test-forms-js.php` does)

**Interfaces:**
- Consumes: `WPCPM_Settings::fingerprint()` (Task 2); Task 1.
- Produces: `WPCPM_Notices::save_one( string $audience, string $body ): void`; the screen's posted fields `wpcpm_audience` and `wpcpm_drawn_body` (the fingerprint of the body as drawn); the outcomes `notice-saved`, `notice-stale`; the form `notice:<audience>`.

**The rules:**
1. **One form and one Save per audience.** Each audience's editor is its own form, posting its audience, its body and the fingerprint of the body as drawn. A body posted back as drawn writes nothing; a body changed by the person is written when the stored body is still the one drawn; when the stored body changed since too, nothing is written, the typing is kept (`WPCPM_Flash::keep( 'notice:<audience>', ... )`) and the sentence says so. The other audiences are never written by this form.
2. **Promote** moves the focus, once the row is replaced, to the new row's name cell (`tabindex="-1"`), and says the outcome in the existing status region; Promote all says how many were promoted.
3. **The progress bar** is named by its visible label (`aria-labelledby="wpcpm-checker-progress-label"`; give the label that id), and the run's status region speaks at each quarter of the mentors checked and at the end, not after every batch.

**The words:**

| Where | Words |
| --- | --- |
| A Save button | `Save this notice` |
| `notice-saved` | `The notice for %s is saved.` (`%s`: the audience as the screen names its editor, such as students) |
| `notice-stale` | `The notice for %s was not saved: it was changed elsewhere after this page was opened. Your text is still in the box: check it against the notice as it is now, and save again.` |
| One Promote | `Promoted %s.` (the mentor's name) |
| Promote all | `_n( 'Promoted %s mentor.', 'Promoted %s mentors.', $count )` |
| Progress at a quarter and at the end | `Checked %1$s of %2$s mentors.` (as today, now at quarters and the end only) |

- [ ] **Step 1: Write the failing checks**

- `bin/test-notices.php`:
  - "each audience is a form of its own with its own Save": five `<form`, each with one `Save this notice` and its `wpcpm_audience`;
  - "a Save of the students' notice leaves the mentors' notice as stored, whatever the page drew": the mentors' stored body unchanged after a students' Save;
  - "a body changed elsewhere since the page was drawn is not overwritten: the typing is kept and the sentence says so" (Review Focus 2): draw, change the stored students' body, post the drawn form with a new body; the stored body is the changed one, the outcome `notice-stale`, `WPCPM_Flash::kept( 'notice:students' )['values']` holds the typing;
  - "a body posted back as drawn writes nothing": no `update_option` of the notices.
- `bin/test-mentor-checker.php`: "the progress bar is named by its visible label": `role="progressbar"` with `aria-labelledby="wpcpm-checker-progress-label"` and the label's `id="wpcpm-checker-progress-label"`; and, from `bin/js/mentor-checker-promote.js`: "after a row's Promote the focus is on the new row's name cell, and the outcome is said" (`activeElement` is the name cell, `tabindex` `-1`, the region holds `Promoted Ada Example.`), "Promote all says how many were promoted" (`Promoted 3 mentors.`), "progress is said at each quarter and at the end, and not after every batch" (for 60 mentors in batches of 5, the region is written 4 times).

- [ ] **Step 2: Run them to verify they fail**

```text
$ php bin/test-notices.php
FAIL each audience is a form of its own with its own Save
$ php bin/test-mentor-checker.php
FAIL the progress bar is named by its visible label
```

- [ ] **Step 3: The notices and the checker**

- `WPCPM_Notices::save_one()` (rule 1), `WPCPM_Header_Notices::handle_save()` (rule 1: capability, nonce keyed to the audience, then the drawn check) and `render_admin_page()` (one form per audience, the kept typing drawn back).
- The checker's progress markup (rule 3); `mentor-checker.js`'s `upsertRow()` (it answers the new row), the Promote and Promote all handlers (rule 2) and the progress announcer (rule 3).

- [ ] **Step 4: Run them to verify they pass**

The two suites end `ALL PASS`.

- [ ] **Step 5: The checks every task ends with**

- [ ] **Step 6: Commit**

```bash
git add includes/tools/class-wpcpm-header-notices.php includes/class-wpcpm-notices.php includes/tools/class-wpcpm-mentor-checker.php assets/js/mentor-checker.js bin/test-notices.php bin/test-mentor-checker.php bin/js/mentor-checker-promote.js
git commit -m "Scan release 1: each header notice saves on its own and refuses a stale page; Promote keeps the focus and says what it did" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 12: Sync safeguards: the count check, a login is not a mentor, a passing refusal waits, nothing created is lost

Findings: LOG-01 (the count check; the guard is proved), LOG-02 (Needs confirming), LOG-06 (the retry), LOG-13 (the created ids), LOG-23 (the page cap). Scope **L**. Review Focus 3.

**Files:**
- Create: `includes/modules/class-wpcpm-sync-guard.php`
- Modify: `wpcredits-program-manager.php`, `uninstall.php` (a `require_once` after `modules/class-wpcpm-sync-module.php`)
- Modify: `includes/modules/class-wpcpm-mentors-sync.php` (`phase_revoke()` at `:1446`, `run_tick()`'s error branch at `:703`, `cron_daily()` at `:364`, `start()` at `:386`; `accept()`, `linked_active()`)
- Modify: `includes/modules/class-wpcpm-students-sync.php` (`phase_provision()` at `:1174-1186`, `retry_delay()` at `:675` made public and taught the guard's codes; `accept()`, `linked_active()`), `includes/modules/class-wpcpm-institutions-sync.php` (`tick()`'s error branch)
- Modify: `includes/modules/class-wpcpm-sync-module.php` (`ACTION_ACCEPT`, `handle_accept()`, the outcomes `accepted`, `not-stopped`), `includes/modules/class-wpcpm-mentors.php` and `includes/modules/class-wpcpm-students.php` (the Sync tab: the stop and Run anyway; the Mentors Sync tab's Needs confirming, `ACTION_CONFIRM_MATCH`, `handle_confirm_match()`)
- Modify: `includes/modules/class-wpcpm-mentor-provision.php` (`find_account()` at `:177-187`, `link_existing_user()` at `:254-281`; `OPT_UNCONFIRMED`)
- Modify: `includes/class-wpcpm-airtable.php` (`create_records()` at `:315`, `fetch_all()` at `:255-271`), `includes/modules/class-wpcpm-institution-create.php` (records the created ids before reporting)
- Modify: `includes/class-wpcpm-cli.php` (`sync_mentors --accept`)
- Test: `bin/test-sync-guard.php` (new), a new `bin/test-mentors-sync-guard.php` on the stand-ins of `bin/test-mentors-sync-welcome.php` (copy its header and its `phase_mentors()` driver; `phase_revoke()` reached the same way, through `ReflectionMethod`), `bin/test-students-sync.php`, `bin/test-institutions-sync.php`, `bin/test-mentor-provision.php`, `bin/test-mentors-screen.php`, `bin/test-airtable.php`, `bin/test-institution-create.php`, `bin/test-cli.php`, `bin/test-uninstall.php` (the new option)

**Interfaces:**
- Consumes: `WPCPM_Lock` (Task 13 moves the ticks onto it; this task does not need it).
- Produces: `WPCPM_Mentors_Sync::start( $run_first_tick = false, $accept = false )` and the same on `WPCPM_Students_Sync` (Task 13 adds `$restart`; the institutions sync gains no `$accept`, since its own empty-read guard is not this one, and only `$restart` in Task 13); `is_stopped(): bool` on both (the state holds `guard`), and `is_running()` false for a stopped run; `progress()` gains `stopped` (bool) and `guard` (`array( 'read', 'linked', 'sentence' )` or null); `final class WPCPM_Sync_Guard` (`CODE_EMPTY`, `CODE_FELL`, `check( $who, $read, $linked, $accepted = false )`, `is_stop( $error )`, `sentence( $who, $read, $linked )`); `WPCPM_Mentors_Sync::accept(): bool`, `linked_active(): int`; the same two on `WPCPM_Students_Sync`; `WPCPM_Students_Sync::retry_delay()` public; `WPCPM_Sync_Module::ACTION_ACCEPT` (each module's own value: `wpcpm_mentors_sync_accept`, `wpcpm_students_sync_accept`); `WPCPM_Mentor_Provision::OPT_UNCONFIRMED = 'wpcpm_mentors_unconfirmed'` (record id => `array( 'name', 'profile', 'user_id', 'at' )`), `WPCPM_Mentors::ACTION_CONFIRM_MATCH = 'wpcpm_mentors_confirm_match'`; `WPCPM_Airtable::fetch_all()` answers `WP_Error( 'wpcpm_airtable_capped' )`; `create_records()`'s error data carries `created`.

**The rules:**
1. **The count check** (LOG-01). Before `phase_revoke()` takes any role, and before `phase_provision()` calls `revoke_departed()`, the sync asks `WPCPM_Sync_Guard::check( <who>, <read>, self::linked_active(), ! empty( $state['accepted'] ) )`. `read` is `count( $state['mentors'] )` for the mentors sync and the number of `$state['students']` that are not past for the students sync. `linked_active()` is one `WP_User_Query` (`fields` `ID`, `number` 1, `count_total` true) for the accounts holding the record id meta and the active meta `1`, answering `get_total()`. A stop writes `$state['guard'] = array( 'read' => ..., 'linked' => ... )`, leaves the phase where it is, and returns the guard's error, which `run_tick()` stores as the last error with the state kept, as it does for any error; the guard's two codes are never retried (`retry_delay()` answers 0 for `WPCPM_Sync_Guard::is_stop()`). The institutions sync keeps its own empty-read guard as it is.
1a. **A stopped run is a state of its own.** `is_stopped()` is true while the state holds `guard`; `is_running()` is false for it (`phase` set, not `done`, and no `guard`), so `run_tick()` returns at once, `handle_tick()` runs no slice, the cron entries start nothing while `is_stopped()` (`cron_daily()` and `cron_auto()`), and `start()` (Sync now, and the CLI without `--accept`) begins a fresh run that reads again and stops again while the base is still short (Task 13 pins it), and `progress()` answers `running` false, `stopped` true and `guard` with both counts and the stop's sentence. Run anyway and `--accept` clear `guard` and go on from the stopped phase; Cancel clears the state, as it does today.
2. **Run anyway**: the Sync tab of the Mentors and the Students screens, while the state holds `guard`, prints the stop's sentence in a `notice notice-error` (it is the last error, which the tab prints already) and, after it, a form posting `ACTION_ACCEPT` with its nonce and `data-wpcpm-confirm`; `handle_accept()` checks the capability, then the nonce, then the state (a state with `guard`), and calls `accept()`, which sets `$state['accepted']`, removes `guard`, deletes the last error and books the tick; the press comes back with `accepted`, or `not-stopped` when there was nothing to accept. The acceptance is the run's, not the site's: the next run starts without it. `wp wpcredits sync_mentors --accept` starts a run with `accepted` set; a CLI run the guard stops ends with its own sentence (below), which names the two counts and `--accept`, not the tab's.
3. **Needs confirming** (LOG-02). `find_account()`'s login branch no longer links: a mentor whose address and record id match no account, but whose WordPress.org username is an account's login, is written to `OPT_UNCONFIRMED` and given nothing (no role, no record id, no students), and the run's notice says so. The Mentors screen's Sync tab lists the waiting matches under **Needs confirming** (the mentor's name and profile link, the account's display name, login and address, when it was found) with a **Confirm** button per row (capability, nonce keyed to the record, state: the entry is still waiting and the account still exists), which links the record and adds the Mentor role through the step a linked account takes today (`link_existing_user()`), removes the entry, and comes back with `match-confirmed`; the next sync gives the account its students. An entry whose mentor the next sync finds by address or record id is removed by that sync. Uninstall deletes the option.
4. **The retry** (LOG-06): the mentors and institutions syncs' error branches use `WPCPM_Students_Sync::retry_delay()` and the students sync's notice and `retried` count; `WPCPM_Mentors_Sync::cron_daily()` leaves a running run alone unless it has stalled, as the students sync's `cron_auto()` does.
5. **The created ids** (LOG-13): `create_records()`, when a request after the first fails, answers the error with `array( 'status' => <as today>, 'created' => <every id the earlier requests created> )` as its data; the import's create step records those ids on the batch before it reports, so a retry does not create them again.
6. **The page cap** (LOG-23): `fetch_all()` answers `WP_Error( 'wpcpm_airtable_capped', <the sentence> )` when an offset is still pending after 100 pages.

**The words:**

| Where | Words |
| --- | --- |
| The stop (the guard's own, proved) | `Airtable returned 12 mentors. That is under half of the 140 accounts linked to a mentor's record, so the mentors sync stopped before taking anyone's Mentor role, and every account was kept. If the base really holds that few now, choose Run anyway on the Sync tab.` (and its students form) |
| The button | `Run anyway` |
| Its confirm (mentors) | `Run the sync anyway? Every linked account it did not read loses the Mentor role.` |
| Its confirm (students) | `Run the sync anyway? Every linked account it did not read loses the Student role.` |
| `accepted` (success) | `The sync goes on from where it stopped, with the counts it read.` |
| `not-stopped` (info) | `That sync was not stopped by its count check, so there was nothing to run anyway.` |
| CLI, the stop (in place of the tab's sentence) | `The mentors sync stopped before taking anyone's Mentor role: Airtable returned %1$s mentors, under half of the %2$s accounts linked to a mentor's record, and every account was kept. If the base really holds that few now, run the command again with --accept.` (two sentences, as the guard's own: `Airtable returned %s mentor.` with `_n()` on the read count and `Airtable returned no mentors.` at zero, then `That is under half of the %s account linked to a mentor's record, so the mentors sync stopped before taking anyone's Mentor role, and every account was kept. If the base really holds that few now, run the command again with --accept.` with `_n()` on the account count; the two counts from the stop's data) |
| The heading | `Needs confirming` |
| Its sentence | `The sync found an account whose login is a mentor's WordPress.org username, and nothing else ties the two: no address and no record. It gave the account nothing. Confirm a match only when the account is the mentor's.` |
| The button | `Confirm` |
| Its confirm | `Make %s a mentor and give the account this mentor's students?` (the account's display name) |
| `match-confirmed` (success) | `Confirmed: %s is linked to the mentor's record and holds the Mentor role. The next sync gives the account its students.` |
| `match-gone` (info) | `That match is no longer waiting: a sync found the mentor another way, or the account is gone. Nothing was changed.` |
| The run's notice, per match | `%1$s has the login of a mentor's WordPress.org username (%2$s) and nothing else. Nothing was linked: confirm it under Needs confirming on the Sync tab if it is the mentor's account.` |
| The page cap | `Airtable has more than %s rows to give for this read, and one read stops at that many, so nothing was counted.` (`%s`: `number_format_i18n( 10000 )`) |

- [ ] **Step 1: Write the failing checks**

The guard's suite, proved:

```php
<?php
/**
 * The check the mentors and students syncs make before they take anybody's role (LOG-01, 1.123.58).
 *
 * A read of no records, or of under half as many as there are linked accounts, stops the run before
 * its revoke step; exactly half goes on; nothing linked is nothing to take; a run an Administrator
 * accepted goes on. The stop's sentence names both counts, each with its own plural.
 *
 * Run from the plugin root:  php bin/test-sync-guard.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	private $code, $message, $data;
	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function __( $text, $domain = null ) { return $text; }
function _n( $single, $plural, $number, $domain = null ) { return 1 === (int) $number ? $single : $plural; }
function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, (int) $decimals ); }

require_once dirname( __DIR__ ) . '/includes/modules/class-wpcpm-sync-guard.php';

$fail = 0;
function ck( $l, $a, $e ) {
	global $fail; $ok = $a === $e; if ( ! $ok ) { $fail++; }
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $l . "\n";
	if ( ! $ok ) { echo '       exp: ' . var_export( $e, true ) . '  got: ' . var_export( $a, true ) . "\n"; }
}

function outcome( $result ) {
	return true === $result ? 'goes on' : ( is_wp_error( $result ) ? $result->get_error_code() : 'something else' );
}

ck( 'a read of as many as are linked goes on', outcome( WPCPM_Sync_Guard::check( 'mentors', 140, 140 ) ), 'goes on' );
ck( 'a read of more than are linked goes on', outcome( WPCPM_Sync_Guard::check( 'mentors', 150, 140 ) ), 'goes on' );
ck( 'a read of exactly half goes on', outcome( WPCPM_Sync_Guard::check( 'mentors', 70, 140 ) ), 'goes on' );
ck( 'one under half stops', outcome( WPCPM_Sync_Guard::check( 'mentors', 69, 140 ) ), WPCPM_Sync_Guard::CODE_FELL );
ck( 'and half of an odd count is held to the same rule: 50 of 101 stops, 51 goes on', array( outcome( WPCPM_Sync_Guard::check( 'students', 50, 101 ) ), outcome( WPCPM_Sync_Guard::check( 'students', 51, 101 ) ) ), array( WPCPM_Sync_Guard::CODE_FELL, 'goes on' ) );
ck( 'a read of nothing stops, with its own code', outcome( WPCPM_Sync_Guard::check( 'students', 0, 1 ) ), WPCPM_Sync_Guard::CODE_EMPTY );
ck( 'nothing linked is nothing to take: a first run, or a site with no accounts yet, goes on whatever it read', array( outcome( WPCPM_Sync_Guard::check( 'mentors', 0, 0 ) ), outcome( WPCPM_Sync_Guard::check( 'mentors', 5, 0 ) ) ), array( 'goes on', 'goes on' ) );
ck( 'a run an Administrator accepted goes on, even on a read of nothing', array( outcome( WPCPM_Sync_Guard::check( 'mentors', 0, 140, true ) ), outcome( WPCPM_Sync_Guard::check( 'students', 3, 300, true ) ) ), array( 'goes on', 'goes on' ) );
ck( 'a negative count, which no query answers, reads as none', outcome( WPCPM_Sync_Guard::check( 'mentors', -4, 10 ) ), WPCPM_Sync_Guard::CODE_EMPTY );

$fell = WPCPM_Sync_Guard::check( 'mentors', 12, 140 );
ck( 'a stop carries whose sync it is and both counts, for the Sync tab', $fell->get_error_data(), array( 'who' => 'mentors', 'read' => 12, 'linked' => 140 ) );
ck( 'and its sentence names both counts and says every account was kept',
	$fell->get_error_message(),
	'Airtable returned 12 mentors. That is under half of the 140 accounts linked to a mentor\'s record, so the mentors sync stopped before taking anyone\'s Mentor role, and every account was kept. If the base really holds that few now, choose Run anyway on the Sync tab.' );

ck( 'a read of nothing says so, and the singular reads as one',
	WPCPM_Sync_Guard::check( 'students', 0, 1 )->get_error_message(),
	'Airtable returned no current students. That is under half of the 1 account linked to a student\'s record, so the students sync stopped before taking anyone\'s Student role, and every account was kept. If the base really holds that few now, choose Run anyway on the Sync tab.' );

ck( 'one record read is said in the singular', 0 === strpos( WPCPM_Sync_Guard::check( 'students', 1, 300 )->get_error_message(), 'Airtable returned 1 current student. That is under half of the 300 accounts' ), true );

ck( 'the two stops are known as stops, and nothing else is', array( WPCPM_Sync_Guard::is_stop( $fell ), WPCPM_Sync_Guard::is_stop( WPCPM_Sync_Guard::check( 'students', 0, 9 ) ), WPCPM_Sync_Guard::is_stop( new WP_Error( 'wpcpm_airtable_error', 'x' ) ), WPCPM_Sync_Guard::is_stop( true ) ), array( true, true, false, false ) );

echo $fail ? "\n$fail FAILED\n" : "\nALL PASS\n";
exit( $fail ? 1 : 0 );
```

Specified, in their suites' style, each with its expected value:
- `bin/test-mentors-sync-guard.php` (new):
  - "a run that read nothing while 140 mentors are linked takes nobody's role, keeps its state and stops with both counts": 140 linked active accounts holding the Mentor role, a mentors phase that read none; after `phase_revoke()`, all 140 still hold the role, `get_option( OPT_ERROR )` is the guard's sentence for 0 and 140, `$state['guard']` is `array( 'read' => 0, 'linked' => 140 )`, and the phase is still `revoke`;
  - "the revoke step of a run that read exactly half of the linked mentors takes the role of the ones it did not read" (Review Focus 3): 70 read of 140; 70 roles taken (`stats['revoked']` `70`), no `guard`;
  - "one under half stops": 69 of 140; no role taken, `guard` set;
  - "a stopped run does not run: is_running() is false, is_stopped() true, and run_tick() does nothing": `array( false, true )` and the state unchanged after a `run_tick()`;
  - "progress() reports the stop with both counts and its sentence": `stopped` `true`, `running` `false`, `guard` `array( 'read' => 0, 'linked' => 140, 'sentence' => <the guard's sentence> )`;
  - "accept() lets the stopped run go on, for that run, and the next start is without it": after `accept()` the state has `accepted` true and no `guard`, `is_running()` true; a later `start()` writes a state with no `accepted`;
  - "the stop is never retried: no tick is booked for it": no `CRON_TICK` on the stand-in's clock after the stop;
  - "the daily start leaves a stopped run alone": `cron_daily()` with `auto_sync` on leaves the state as it was.
- `bin/test-students-sync.php`: the same eight for the students sync (`phase_provision()`'s end, `cron_auto()`), and "retry_delay() answers 0 for the guard's two codes".
- `bin/test-mentors-screen.php`: "the Sync tab prints Run anyway with its confirm while the state holds the stop, and not otherwise" (`<button type="submit" class="button">Run anyway</button>` inside a form with `data-wpcpm-confirm="Run the sync anyway? Every linked account it did not read loses the Mentor role."`); "Run anyway checks the right, then the nonce, then the state" (a press without the capability ends in `wp_die`, a bad nonce in the nonce stand-in's stop, a run with no stop comes back `not-stopped`); "Needs confirming lists a waiting match with its Confirm" (the heading `Needs confirming`, its sentence, one row with the mentor's name and the account's login, one `Confirm`); "Confirm links the record and adds the role, and a match no longer waiting is match-gone" (`match-confirmed`, the account holds the Mentor role and the record id meta; a second press `match-gone`).
- `bin/test-mentor-provision.php`: "a mentor found by login alone is given nothing: no role, no record, no students, and is written to Needs confirming" (the account's roles unchanged, no record id meta, `get_option( 'wpcpm_mentors_unconfirmed' )` keyed by the record id with `user_id`); "a mentor found by address is linked as before"; "a waiting match whose mentor is found by address is removed" (the option no longer holds the record).
- `bin/test-institutions-sync.php`: "a passing refusal books the tick after the wait and says so in the run's notices" (a 503 from the stand-in; a `CRON_TICK` at `time() + 30` and the notice `A read was refused during this run (...). The run waited and carried on from where it stopped.`).
- `bin/test-airtable.php`: "a create that fails on its second request answers the ids the first created" (`$error->get_error_data()['created']` is the ten ids of the first request); "fetch_all() past 100 pages answers capped, not a part of the table" (`wpcpm_airtable_capped`, its sentence naming `10,000`).
- `bin/test-institution-create.php`: "the ids created before a failure are recorded on the batch before the failure is reported" (the batch's created ids are the first request's ten).
- `bin/test-cli.php`: "sync_mentors --accept starts an accepted run" (`start()` called with `accept` true); "a CLI run the guard stops ends with its own sentence, naming --accept" (`WP_CLI::error()` with the CLI sentence above for 12 and 140).
- `bin/test-uninstall.php`: "uninstall deletes wpcpm_mentors_unconfirmed".

- [ ] **Step 2: Run them to verify they fail**

```text
$ php bin/test-sync-guard.php
PHP Warning:  require_once(<plugin>/includes/modules/class-wpcpm-sync-guard.php): Failed to open stream: No such file or directory
$ php bin/test-mentors-sync-guard.php
FAIL a run that read nothing while 140 mentors are linked takes nobody's role, keeps its state and stops with both counts
$ php bin/test-students-sync.php
FAIL a run that read nothing while 140 students are linked takes nobody's role, keeps its state and stops with both counts
$ php bin/test-mentors-screen.php
FAIL the Sync tab prints Run anyway with its confirm while the state holds the stop, and not otherwise
$ php bin/test-mentor-provision.php
FAIL a mentor found by login alone is given nothing: no role, no record, no students, and is written to Needs confirming
$ php bin/test-institutions-sync.php
FAIL a passing refusal books the tick after the wait and says so in the run's notices
$ php bin/test-airtable.php
FAIL a create that fails on its second request answers the ids the first created
$ php bin/test-institution-create.php
FAIL the ids created before a failure are recorded on the batch before the failure is reported
$ php bin/test-cli.php
FAIL sync_mentors --accept starts an accepted run
$ php bin/test-uninstall.php
FAIL uninstall deletes wpcpm_mentors_unconfirmed
```

- [ ] **Step 3: The guard and the syncs**

`includes/modules/class-wpcpm-sync-guard.php`:

```php
<?php
/**
 * The check the mentors and students syncs make before they take anybody's role.
 *
 * @package WPCredits_Program_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a sync may go on to its revoke step, from two counts (LOG-01, 1.123.58).
 *
 * The mentors sync and the students sync take the role from every linked account the run did not
 * read. A read that came back with nothing, or with far fewer than the site holds (a status renamed in
 * the shared base, a status option deleted, a formula column renamed), would take it from everybody
 * within three hours. So before the revoke step each sync compares how many records the run read with
 * how many accounts are linked to a record and marked active, which is how many the last completed
 * runs left linked plus any this run linked:
 *
 * - nothing linked is nothing to take, and the run goes on whatever it read;
 * - a read of no records stops the run;
 * - a read under half of the linked accounts stops it; exactly half goes on;
 * - a run an Administrator accepted (Run anyway on the Sync tab, `--accept` on the command line)
 *   goes on, for that run only.
 *
 * A stop keeps every account: the sync returns the error, its tick keeps the state for Run anyway,
 * and the Sync tab and the command line print the sentence, which names both counts. The two codes
 * are never retried on their own (`is_stop()`): the counts will not change until somebody looks.
 */
final class WPCPM_Sync_Guard {

	/** The run read no records while accounts are linked. */
	const CODE_EMPTY = 'wpcpm_sync_read_empty';

	/** The run read under half as many records as there are linked accounts. */
	const CODE_FELL = 'wpcpm_sync_read_fell';

	/**
	 * Whether the run may take roles.
	 *
	 * @param string $who      'mentors' or 'students': whose sync asks, for the sentence.
	 * @param int    $read     How many records in scope the run read.
	 * @param int    $linked   How many accounts are linked to a record and marked active.
	 * @param bool   $accepted Whether an Administrator accepted this run's counts.
	 * @return true|WP_Error
	 */
	public static function check( $who, $read, $linked, $accepted = false ) {
		$read   = max( 0, (int) $read );
		$linked = max( 0, (int) $linked );

		if ( $accepted || 0 === $linked ) {
			return true;
		}

		if ( 0 === $read ) {
			return new WP_Error( self::CODE_EMPTY, self::sentence( $who, $read, $linked ), self::data( $who, $read, $linked ) );
		}

		if ( $read * 2 < $linked ) {
			return new WP_Error( self::CODE_FELL, self::sentence( $who, $read, $linked ), self::data( $who, $read, $linked ) );
		}

		return true;
	}

	/**
	 * Whether an error is one of this guard's stops, which nothing retries on its own.
	 *
	 * @param mixed $error What a phase returned.
	 * @return bool
	 */
	public static function is_stop( $error ) {
		return is_wp_error( $error ) && in_array( $error->get_error_code(), array( self::CODE_EMPTY, self::CODE_FELL ), true );
	}

	/**
	 * What a stop carries for the Sync tab: whose sync, and the two counts.
	 *
	 * @param string $who    'mentors' or 'students'.
	 * @param int    $read   Records read.
	 * @param int    $linked Accounts linked and active.
	 * @return array{who: string, read: int, linked: int}
	 */
	private static function data( $who, $read, $linked ) {
		return array(
			'who'    => 'students' === $who ? 'students' : 'mentors',
			'read'   => (int) $read,
			'linked' => (int) $linked,
		);
	}

	/**
	 * The sentence a stop says, naming both counts: two sentences, each with its own plural.
	 *
	 * @param string $who    'mentors' or 'students'.
	 * @param int    $read   Records read.
	 * @param int    $linked Accounts linked and active.
	 * @return string
	 */
	public static function sentence( $who, $read, $linked ) {
		$read   = (int) $read;
		$linked = (int) $linked;

		if ( 'students' === $who ) {
			$found = 0 === $read
				? __( 'Airtable returned no current students.', 'wpcredits-program-manager' )
				/* translators: %s: how many current students Airtable returned. */
				: sprintf( _n( 'Airtable returned %s current student.', 'Airtable returned %s current students.', $read, 'wpcredits-program-manager' ), number_format_i18n( $read ) );
			$kept = sprintf(
				/* translators: %s: how many accounts are linked to a student's record. */
				_n( 'That is under half of the %s account linked to a student\'s record, so the students sync stopped before taking anyone\'s Student role, and every account was kept. If the base really holds that few now, choose Run anyway on the Sync tab.', 'That is under half of the %s accounts linked to a student\'s record, so the students sync stopped before taking anyone\'s Student role, and every account was kept. If the base really holds that few now, choose Run anyway on the Sync tab.', $linked, 'wpcredits-program-manager' ),
				number_format_i18n( $linked )
			);

			return $found . ' ' . $kept;
		}

		$found = 0 === $read
			? __( 'Airtable returned no mentors.', 'wpcredits-program-manager' )
			/* translators: %s: how many mentors Airtable returned. */
			: sprintf( _n( 'Airtable returned %s mentor.', 'Airtable returned %s mentors.', $read, 'wpcredits-program-manager' ), number_format_i18n( $read ) );
		$kept = sprintf(
			/* translators: %s: how many accounts are linked to a mentor's record. */
			_n( 'That is under half of the %s account linked to a mentor\'s record, so the mentors sync stopped before taking anyone\'s Mentor role, and every account was kept. If the base really holds that few now, choose Run anyway on the Sync tab.', 'That is under half of the %s accounts linked to a mentor\'s record, so the mentors sync stopped before taking anyone\'s Mentor role, and every account was kept. If the base really holds that few now, choose Run anyway on the Sync tab.', $linked, 'wpcredits-program-manager' ),
			number_format_i18n( $linked )
		);

		return $found . ' ' . $kept;
	}
}
```

The loader and the uninstaller each gain, after `modules/class-wpcpm-sync-module.php`:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-guard.php';
```

```php
require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-sync-guard.php';
```

Then, the functions to change:
- `WPCPM_Mentors_Sync`: `start( $run_first_tick = false, $accept = false )`, `is_running()`, `is_stopped()`, `progress()`, `run_tick()`'s error branch (rule 4's retry), `phase_revoke( array &$state, array $settings )` (rule 1), `cron_daily()` (rules 1a, 4), `accept()`, `linked_active()`.
- `WPCPM_Students_Sync`: the same, with `phase_provision()`'s end (rule 1), `cron_auto()` (rule 1a), `retry_delay()` public.
- `WPCPM_Institutions_Sync::tick()`'s error branch (rule 4).
- `WPCPM_Sync_Module`: `ACTION_ACCEPT`, `handle_accept()`, `sync_messages()` (`accepted`, `not-stopped`); `handle_tick()` runs no slice for a stopped run (rule 1a).
- `WPCPM_Mentors` and `WPCPM_Students`: the Sync tab's stop and Run anyway (rule 2); `WPCPM_Mentors::ACTION_CONFIRM_MATCH`, `handle_confirm_match()`, the Needs confirming list (rule 3).
- `WPCPM_Mentor_Provision`: `find_account()`'s login branch, `OPT_UNCONFIRMED`, a public `forget_unconfirmed( string $record_id ): void` (rule 3).
- `WPCPM_Airtable::create_records()` and `fetch_all()` (rules 5, 6); `WPCPM_Institution_Create`'s create step (rule 5).
- `WPCPM_CLI::sync_mentors()` (`--accept`, the CLI's stop sentence); `uninstall.php` (the option).

- [ ] **Step 4: Run them to verify they pass**

```text
$ php bin/test-sync-guard.php
ok   a read of as many as are linked goes on
ok   a read of more than are linked goes on
ok   a read of exactly half goes on
ok   one under half stops
ok   and half of an odd count is held to the same rule: 50 of 101 stops, 51 goes on
ok   a read of nothing stops, with its own code
ok   nothing linked is nothing to take: a first run, or a site with no accounts yet, goes on whatever it read
ok   a run an Administrator accepted goes on, even on a read of nothing
ok   a negative count, which no query answers, reads as none
ok   a stop carries whose sync it is and both counts, for the Sync tab
ok   and its sentence names both counts and says every account was kept
ok   a read of nothing says so, and the singular reads as one
ok   one record read is said in the singular
ok   the two stops are known as stops, and nothing else is
ALL PASS
```

and the specified suites: `ALL PASS`.

- [ ] **Step 5: The checks every task ends with**

- [ ] **Step 6: Commit**

```bash
git add includes/modules/class-wpcpm-sync-guard.php wpcredits-program-manager.php uninstall.php includes/modules/class-wpcpm-mentors-sync.php includes/modules/class-wpcpm-students-sync.php includes/modules/class-wpcpm-institutions-sync.php includes/modules/class-wpcpm-sync-module.php includes/modules/class-wpcpm-mentors.php includes/modules/class-wpcpm-students.php includes/modules/class-wpcpm-mentor-provision.php includes/class-wpcpm-airtable.php includes/modules/class-wpcpm-institution-create.php includes/class-wpcpm-cli.php bin/test-sync-guard.php bin/test-mentors-sync-guard.php bin/test-students-sync.php bin/test-institutions-sync.php bin/test-mentor-provision.php bin/test-mentors-screen.php bin/test-airtable.php bin/test-institution-create.php bin/test-cli.php bin/test-uninstall.php
git commit -m "Scan release 1: a collapsed Airtable read stops the sync before any role is taken, a login match waits to be confirmed, and a passing refusal waits" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 13: One run at a time, and the sync speaks

Findings: LOG-05 (Sync now twice), LOG-07 (the tick lock), LOG-24 (the invitation queue's write), ADM-03, INS-26 (the sync's progress, stall and finish, heard). Scope **M**.

**Files:**
- Modify: `includes/modules/class-wpcpm-sync-module.php` (`handle_sync()` at `:94`, `handle_tick()`; the outcomes `running`, `finished`), the three syncs (`start()` at mentors `:405`, students `:353`, institutions `:341`; `acquire_lock()` and `release_lock()` at mentors `:614`, students `:537`, institutions `:523`; the page loops of their long phases), `includes/class-wpcpm-cli.php` (`sync_mentors --restart`)
- Modify: `includes/class-wpcpm-mail.php` (`QUEUE_LOCK`, `queue_invite()` at `:571`, `queue_invites()` at `:616`, `drain_queue()` at `:729`)
- Modify: `assets/js/admin.js` (`pollProgress()`), the three sync panels (`class-wpcpm-students.php` at `:515-552`, `class-wpcpm-mentors.php` at `:718-768`, `class-wpcpm-institutions.php` `render_sync_panel()` at `:3040-3075`: `data-finished`)
- Modify: `includes/class-wpcpm-lock.php` (`extend()`)
- Test: `bin/test-lock.php`, `bin/test-students-sync.php`, `bin/test-institutions-sync.php`, `bin/test-mentors-screen.php`, `bin/test-mail.php`, `bin/test-cli.php`, `bin/test-admin-js.php` with a new `bin/js/admin-sync.js` (the suite runs both harnesses)

**Interfaces:**
- Consumes: `WPCPM_Lock::take()`, `release()`; Task 3's `speak()` in `admin.js`; Task 12's `is_stopped()` and `progress()['stopped']`; `WPCPM_Flash`.
- Produces: the mentors and students syncs' `start( $run_first_tick = false, $accept = false, $restart = false )`, and the institutions sync's `start( $run_first_tick = false, $restart = false )` (no `$accept`: its own empty-read guard is not Task 12's), each refusing with `WP_Error( 'wpcpm_sync_running' )` while `is_running()` and not stalled, unless `$restart`; a private `hold_lock(): bool` on each sync that refreshes the tick lock; `WPCPM_Lock::extend( string $name, string $token, int $ttl ): string`; `WPCPM_Mail::QUEUE_LOCK = 'wpcpm_invite_queue_lock'`, `QUEUE_LOCK_TTL = 30`; the flash `finished` on a sync screen's channel, `array( 'status' => 'finished', 'created' => int, 'refreshed' => int )`.

**The rules:**
1. **One run at a time** (LOG-05): `start()` refuses a run while one is going and has not stalled; `handle_sync()` comes back with `running`; `wp wpcredits sync_mentors` refuses with the line below unless `--restart`, the deliberate restart, which starts again from the beginning. A run Task 12's guard stopped is not running, so Sync now starts a new run (pinned in Step 1: "Sync now after a guard stop starts a fresh run, and the daily cron starts nothing while the stop stands"; the run reads again, and stops again on the same counts).
2. **The tick lock** (LOG-07): `acquire_lock()` is `WPCPM_Lock::take( self::OPT_LOCK, self::LOCK_TIMEOUT )`, its token kept in a static for `release_lock()`, which is `WPCPM_Lock::release( self::OPT_LOCK, $token )`. `LOCK_TIMEOUT` (120) is above every tick's budget (`BUDGET`, 18), but the budget is checked only between slices, and one slice of a long read can outlast it, so every phase that pages through Airtable calls `hold_lock()` after each page, which calls a new `WPCPM_Lock::extend( $name, $token, $ttl ): string`: one `UPDATE` setting the row to now and the lifetime where it still holds this token, answering the new token (or '' when the row no longer holds it, and the tick then stops at once, as one that lost its lock), so the lock never runs out under a working tick and no other tick can slip in between. `cancel()` still deletes the row, the deliberate end of a run, and `start()` no longer does (a restart may: it is deliberate too).
3. **The queue's write** (LOG-24): every read-modify-write of `QUEUE_OPTION` (`queue_invite()`, `queue_invites()`, the splice in `drain_queue()`) runs under `WPCPM_Lock::take( self::QUEUE_LOCK, self::QUEUE_LOCK_TTL )`, taken up to ten times 50 milliseconds apart; a drain that cannot take it does nothing this run (the next run is on the clock), and a queueing that cannot take it returns without writing, as an account the queue passed over is left today (never invited, so the next bulk press reaches it).
4. **The sync speaks** (ADM-03, INS-26): `pollProgress()` sets `aria-busy="true"` on the panel while a run goes and `false` at the end; speaks the step line once per step (when `data.step` changes, `data.step_label` and `data.label` as the panel prints them, joined by `: `), the stall line once when it is first shown, and the panel's `data-finished` sentence before the final reload. **A stopped run** (`data.stopped`): the poller stops polling, sets `aria-busy="false"`, speaks `data.guard.sentence` once, and reloads nothing; the Sync tab, drawn again by the person, shows the stop and Run anyway. `handle_tick()`, when the tick it ran finished the run, flashes `finished` with the report's `created` and its `linked` plus `updated` on the screen's channel, and the Sync tab prints it.

**The words:**

| Where | Words |
| --- | --- |
| `running` (info) | `A sync is already running. Its progress is shown below.` |
| CLI, a run going | `A sync is already running. Run the command with --restart to start it again from the beginning.` |
| The panel's `data-finished` | `The sync has finished. The page will show its report.` |
| `finished` (success, on the Sync tab) | `Sync finished. Accounts created: %1$s. Accounts refreshed: %2$s.` |

- [ ] **Step 1: Write the failing checks**

- `bin/test-students-sync.php` and `bin/test-institutions-sync.php`:
  - "start() refuses while a run is going and has not stalled, and leaves the state and the lock as they were": a state touched 10 seconds ago; `start()` answers `wpcpm_sync_running` and the stored state and lock row are unchanged;
  - "a stalled run may be started again": touched 200 seconds ago; `start()` answers `true` and a fresh state;
  - "start() with restart starts again from the beginning": touched 10 seconds ago and `$restart` true; `true` and a fresh state;
  - "two ticks taking the lock at once: one gets it": with `bin/stubs/options-lock-wpdb.php`, the first `acquire_lock()` answers true and the second false;
  - "a tick whose lock was taken over does not let go of its successor's": after the lock ran out and a second tick took it, the first tick's `release_lock()` leaves the row (`WPCPM_Lock::until()` non-zero);
  - "a long read refreshes its lock after every page": a phase of three pages; `WPCPM_Lock::until( OPT_LOCK )` grows after each page.
- `bin/test-mentors-screen.php`: "Sync now pressed during a run comes back with running" (the flash `running`); "the tick that finishes a run flashes finished with its counts, and the Sync tab says it" (the flash `array( 'status' => 'finished', 'created' => 2, 'refreshed' => 5 )` and the tab's `Sync finished. Accounts created: 2. Accounts refreshed: 5.`); "a stopped run is not ticked by the poll" (`handle_tick()` answers `stopped` true and the state is unchanged).
- `bin/test-lock.php`: "extend() moves a held lock's time on and answers the new token, and answers '' for a token the row no longer holds" (the row's value is the new token; a stale token leaves the row as it was).
- `bin/test-mail.php`: "a queueing during a drain is not lost: the drain's write-back keeps the account queued meanwhile" (with the lock stand-in, a `queue_invite( 77 )` attempted while the drain holds the lock is retried and lands after the drain's write: the queue holds 77); "a drain that cannot take the lock sends nothing this run" (the lock held by another token: no mail, the queue unchanged).
- `bin/test-cli.php`: "sync_mentors refuses during a run and says how to restart" (`WP_CLI::error()` with the line above); "--restart starts again" (`start()` called with `restart` true).
- `bin/js/admin-sync.js`, run by `bin/test-admin-js.php`: "the panel is busy while a run goes and not after" (`aria-busy` `true` then `false`); "each step is said once" (three answers at step 2 and one at step 3: `speak()` called twice, with `Step 2 of 7: <label>` and `Step 3 of 7: <label>`); "the stall line is said once, when it first shows"; "the finish is said before the reload" (the speech log ends with `data-finished`, then `RELOADED`); "a stopped run stops the poll and says its sentence once, with no reload" (one speech, the guard's sentence; no timer booked; no `RELOADED`).

- [ ] **Step 2: Run them to verify they fail**

```text
$ php bin/test-students-sync.php
FAIL start() refuses while a run is going and has not stalled, and leaves the state and the lock as they were
$ php bin/test-institutions-sync.php
FAIL start() refuses while a run is going and has not stalled, and leaves the state and the lock as they were
$ php bin/test-mentors-screen.php
FAIL Sync now pressed during a run comes back with running
$ php bin/test-lock.php
PHP Fatal error:  Uncaught Error: Call to undefined method WPCPM_Lock::extend()
$ php bin/test-mail.php
FAIL a queueing during a drain is not lost: the drain's write-back keeps the account queued meanwhile
$ php bin/test-cli.php
FAIL sync_mentors refuses during a run and says how to restart
$ php bin/test-admin-js.php
FAIL the panel is busy while a run goes and not after
```

- [ ] **Step 3: The runs, the locks and the speech**

- `WPCPM_Lock::extend()` (rule 2), beside `take()` and `release()`, with its `phpcs:ignore` reason as theirs.
- Each sync's `start()` (rule 1), `acquire_lock()`, `release_lock()`, `hold_lock()` and the page loops of `phase_mentors()`, `phase_reports()`, `phase_students()` (mentors), `phase_reports()`, `phase_mentors()`, `phase_tutors()` (students) and the institutions sync's paging phase (rule 2).
- `WPCPM_Sync_Module::handle_sync()` (rule 1), `handle_tick()` and `sync_messages()` (rules 1, 4).
- `WPCPM_Mail::queue_invite()`, `queue_invites()`, `drain_queue()`, and a private `with_queue_lock( callable $work ): bool` (rule 3).
- `admin.js`'s `pollProgress()` and the three panels' `data-finished` (rule 4).
- `WPCPM_CLI::sync_mentors()` (`--restart`).

- [ ] **Step 4: Run them to verify they pass**

The seven suites end `ALL PASS`.

- [ ] **Step 5: The checks every task ends with**

- [ ] **Step 6: Commit**

```bash
git add includes/class-wpcpm-lock.php bin/test-lock.php includes/modules/class-wpcpm-sync-module.php includes/modules/class-wpcpm-mentors-sync.php includes/modules/class-wpcpm-students-sync.php includes/modules/class-wpcpm-institutions-sync.php includes/modules/class-wpcpm-students.php includes/modules/class-wpcpm-mentors.php includes/modules/class-wpcpm-institutions.php includes/class-wpcpm-cli.php includes/class-wpcpm-mail.php assets/js/admin.js bin/test-students-sync.php bin/test-institutions-sync.php bin/test-mentors-screen.php bin/test-mail.php bin/test-cli.php bin/test-admin-js.php bin/js/admin-sync.js
git commit -m "Scan release 1: a sync runs once at a time on the atomic lock, the invitation queue is written under a lock, and the sync's progress is heard" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 14: The Administrator guide

Scope **S**. The guide says what each screen now does, in its own part, each paragraph opening "Since 1.123.58". Read the part before adding to it, and add after the paragraph that describes the same control. The Administrator guide is built from the student, mentor and Administrator sections alike (`bin/build-docs.php:109-133`), so the Report Card changes are documented in the student and mentor sections too.

**Files:**
- Modify: `docs/sections/31-admin-settings.md` (the Settings screen's opening part, before `### The Connection tab`), `docs/sections/30-admin-wpadmin.md` (`### The audience screens`, `### Applications to mentor`, `### Institutions`, `### Sponsors`), `docs/sections/32-admin-tools.md` (`### Header notices`, `### Emails`, `### Mentor Status Checker`, `### Track Builder`), `docs/sections/34-admin-operations.md` (`### The sync`, the matching sentence at about `:62-63` and its follow-up at about `:73`, the option table)
- Modify: `docs/sections/10-student-card.md` (the report form and the hours box), `docs/sections/13-student-feedback.md` (the feedback forms), `docs/sections/21-mentor-availability.md` (the session planner), `docs/sections/20-mentor-card.md` (the notes; read where they are described)
- Generated: `docs/administrators.md`, `docs/build/administrators.html`, and the student and mentor guides `php bin/build-docs.php` builds from those sections

**The paragraphs** (as written here; a paragraph may be re-wrapped, not reworded):

31, the Settings screen's opening part:

> Since 1.123.58 a Save writes only what you changed on the page. Each tab remembers the value every setting had when the page was opened, so a setting somebody else changed since, on another tab, as another Administrator or on the command line, stays as they left it. If you changed it too, their value is kept, the setting is marked, and a warning above the tab names it: check it, and save again if yours should win. A value a setting cannot take, such as an address with a typo in a Who is mailed list or a course link that is not https, is not saved: the value saved before stays, what you typed comes back in the box, and the box says why. Each Who is mailed list has a box above it, **Mail every Administrator account instead of the people below**: tick it to mail every Administrator account, since a list left empty is refused. On the command line, `wp wpcredits setting set <list> everyone` does the same.

30, `### The audience screens`, after the invitations card's paragraph:

> Since 1.123.58 the invitations card no longer reloads the page while invitations go out. The count and the bar move on their own every ten seconds, a screen reader hears how many were sent at each quarter and at the end, and the card says when the run has finished; a search, a filter or a ticked row stays as it was. The card also links to the page itself, to see the whole card again.

30, `### Applications to mentor`, after the decisions:

> Since 1.123.58 Select, Ask for more information and Decline each check the application again at the moment they send, so two Administrators deciding the same application at once send one email: the second is told who decided it, how, and when, and nothing is sent. Every outcome names the applicant and the reference, and the page comes back to the application, or to the next one in the view. A note the screen refuses is marked at its box.

30, `### Institutions`, after the applications:

> Since 1.123.58 a question to an applicant that could not be sent stays in its box. A rejection says whether the applicant was sent the acknowledgement. Link with no institution chosen says to choose one first.

30, `### Sponsors`, after the Add and Edit forms:

> Since 1.123.58 a refused sponsor names the field that needs a change, at the field, and keeps everything typed. A sponsor somebody saved after you opened the form is not overwritten: the form says who changed it and when, and keeps what you typed.

32, `### Track Builder`:

> Since 1.123.58 a track that cannot be saved lists every problem at the top, each linked to its field, and a track another Administrator saved after you opened it is not overwritten; your own question moves on the same page do not count as a change. Publish, Check and Take off can be pressed once. A question moved while the connection failed is put back, and the page says so.

32, `### Emails`, the editor's part under `#### Templates`:

> Since 1.123.58 an email somebody changed after you opened it is not overwritten: the editor says who changed it and when, and keeps your wording in the boxes.

32, `### Header notices`:

> Since 1.123.58 each audience's notice has its own Save, so saving one notice never touches another, and a notice changed after you opened the page is not overwritten.

32, `### Mentor Status Checker`:

> Since 1.123.58 Promote keeps the keyboard's place on the promoted row and says who was promoted; a run's progress is said at each quarter and at the end.

34, the matching sentence at about `:62-63`, reworded:

> Accounts are matched by the Airtable record the sync stored, then by email address. Since 1.123.58 a mentor whose WordPress.org username is an account's login, with nothing else to tie the two, is not linked: the match waits under **Needs confirming** on the Mentors screen's Sync tab. **No account is ever deleted by a sync** - the most it will do is remove a role.

and in the 1.122.33 paragraph after it, "reports an account matched by username alone for an Administrator to confirm is the same person" becomes "lists an account matched by username alone under **Needs confirming**, for an Administrator to confirm".

34, `### The sync`:

> Since 1.123.58 the mentors and students syncs count before they take anyone's role. A run that read no records, or fewer than half as many as there are accounts linked to a record, stops before its revoke step and keeps every account; the Sync tab says what it read and offers **Run anyway**, and `wp wpcredits sync_mentors --accept` does the same on the command line, for that run only. A stopped run is not running: the Sync tab stops polling and says why. A passing refusal from Airtable makes every sync wait and carry on. Sync now while a run is going says so instead of starting again; `wp wpcredits sync_mentors --restart` starts again on purpose. While a run goes, a screen reader hears each step, a stall and the finish, and the finished run's counts are said on the Sync tab.

34, after `### The sync`'s commands:

> Since 1.123.58 `wp wpcredits setting get <key>` prints a setting as the screen shows it, a secret masked, and `wp wpcredits setting set <key> <value> [--dry-run]` changes one by the Save's own rules: it prints what changed, what it would change with `--dry-run`, or why the value was refused; `everyone` is the value that sets a Who is mailed list to every Administrator account. Change a live setting one way at a time: a settings tab open in a browser keeps its own view of the page it drew.

10, the report form's part:

> Since 1.123.58 a report that could not be saved comes back with every answer you typed, and the answer that needs a change is marked, with a list of them at the top. Saving your hours keeps you in My course and says "Hours saved." there.

13, the feedback forms' part:

> Since 1.123.58 a feedback form that could not be sent comes back with your answers, and the form you saved opens again with its message inside it.

21, the session planner's part:

> Since 1.123.58 a session or series that cannot be planned comes back with the planner open and every field as you left it, and the date that clashed, or is in the past, is marked.

20, the notes' part:

> Since 1.123.58 a note pressed twice is saved once.

34, the option table, A to Z:

| Option | Constant |
| --- | --- |
| `wpcpm_invite_queue_lock` | `WPCPM_Mail::QUEUE_LOCK` (a lock row while the invitation queue is written, 30 seconds at most) |
| `wpcpm_mentors_unconfirmed` | `WPCPM_Mentor_Provision::OPT_UNCONFIRMED` (the mentors the sync found by an account's login alone, waiting under Needs confirming; uninstall deletes it) |

- [ ] **Step 1: The paragraphs and the rows**, as above.
- [ ] **Step 2: Build twice and check**

```bash
php bin/build-docs.php && php bin/build-docs.php && git status --short docs/
php bin/test-handbook.php | tail -1
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
grep -rn -e $'\xe2\x80\x94' -e $'\xe2\x80\x93' docs/sections docs/administrators.md || echo clean
```

Expected: the second build changes nothing more than the first; `ALL PASS`; `- all resolve`; `US English throughout.`; `clean`.

- [ ] **Step 3: The checks every task ends with**

- [ ] **Step 4: Commit**

```bash
git add docs/sections/10-student-card.md docs/sections/13-student-feedback.md docs/sections/20-mentor-card.md docs/sections/21-mentor-availability.md docs/sections/30-admin-wpadmin.md docs/sections/31-admin-settings.md docs/sections/32-admin-tools.md docs/sections/34-admin-operations.md docs/administrators.md docs/build/
git commit -m "Scan release 1: the Administrator, student and mentor guides" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 15: Try every screen on the Docker copy

The copy at localhost:8900 runs over the live site's database, its mail kept in its own log and never sent, its cron off; it refuses airtable.com (`WP_HTTP_BLOCK_EXTERNAL`) and holds blank Airtable tokens, so the Sync-tab lines below run on a trial mu-plugin that answers Airtable's host from invented answers. Captures and notes go in the session's scratchpad and are backed up under `.superpowers/session-backups/<the day's date>/scan-release-1/`. Snapshot the copy first and install the branch's build, as the release script's `--site local` deploy does (`python3 release.py deploy plugin --site local --version 1.123.58`). Every test address is example.test's or the owner's one test address. Each line below is a box to tick, with a capture at 1280px and, where it says so, at 375px.

**The trial's repository files** (public, so invented: example.test addresses, synthetic usernames, spelled-out record ids; `php bin/test-fixtures.php` must pass with them):

`bin/fixtures/trial-mentors-empty.json`:

```json
{ "records": [] }
```

`bin/fixtures/trial-mentors-login-only.json` (one mentor whose address and record id match nothing on the copy, and whose username is the login of the account the trial makes): Its `Status` must equal the copy's `mentor_status` setting (`bin/wp eval 'echo WPCPM_Settings::get_value( "mentor_status" );'` on the copy): edit the file before the trial if that is not `Active`.

```json
{
  "records": [
    {
      "id": "recMENTORTRIAL001",
      "fields": {
        "Full Name": "Ada Example",
        "WordPress profile": "https://profiles.wordpress.org/ada-example/",
        "Email": "ada.trial@example.test",
        "Status": "Active"
      }
    }
  ]
}
```

The "half" answers cannot be a file, since a file cannot know how many mentors the copy has linked: the mu-plugin builds them from the copy's own linked active mentors, `floor( N / 2 )` of them for **half** (goes on, and takes the role of the other half on the copy, which the snapshot puts back) and `floor( N / 2 ) - 1` for **under-half** (stops).

**The trial mu-plugin**, written to the copy as `site/wp-content/mu-plugins/wpcpm-trial-airtable.php` and removed after the trial, never committed:

```php
<?php
/**
 * Trial only: answers Airtable's host on the Docker copy from an invented answer, chosen with
 * `wp option update wpcpm_trial_answer <empty|login|half|under-half>`. Removed after the trial.
 */
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		if ( false === strpos( (string) $url, 'api.airtable.com' ) ) {
			return $pre;
		}

		$answer   = (string) get_option( 'wpcpm_trial_answer', 'empty' );
		$settings = WPCPM_Settings::get();
		$records  = array();

		if ( false !== strpos( (string) $url, '/meta/' ) ) {
			return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'tables' => array() ) ) );
		}

		if ( false !== strpos( (string) $url, '/' . rawurlencode( (string) $settings['mentors_table'] ) ) ) {
			$fixtures = WPCPM_PLUGIN_DIR . 'bin/fixtures/';

			if ( 'login' === $answer ) {
				$records = json_decode( (string) file_get_contents( $fixtures . 'trial-mentors-login-only.json' ), true )['records'];
			} elseif ( 'half' === $answer || 'under-half' === $answer ) {
				$ids  = get_users( array( 'fields' => 'ID', 'number' => -1, 'meta_query' => array( array( 'key' => WPCPM_Mentors_Sync::META_RECORD_ID, 'compare' => 'EXISTS' ), array( 'key' => WPCPM_Mentors_Sync::META_ACTIVE, 'value' => '1' ) ) ) );
				$take = max( 0, (int) floor( count( $ids ) / 2 ) - ( 'under-half' === $answer ? 1 : 0 ) );

				foreach ( array_slice( $ids, 0, $take ) as $id ) {
					$user      = get_userdata( (int) $id );
					$records[] = array(
						'id'     => (string) get_user_meta( (int) $id, WPCPM_Mentors_Sync::META_RECORD_ID, true ),
						'fields' => array( 'Full Name' => $user->display_name, 'Email' => $user->user_email, 'Status' => WPCPM_Settings::get_value( 'mentor_status' ) ),
					);
				}
			}
		}

		return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'records' => $records ) ) );
	},
	10,
	3
);
```

With it in place, on the copy: give the copy a placeholder token and base so the sync starts (`wp wpcredits setting set api_token patEXAMPLE0000000000001` and `wp wpcredits setting set base_id appEXAMPLE0000001`; the snapshot puts the copy's blanks back), make the login-only account (`wp user create ada-example ada-account@example.test --role=subscriber`), and allow the host for the trial (`WP_ACCESSIBLE_HOSTS` is not needed: `pre_http_request` answers before the block).

- [ ] **Public forms** (mentor, institution, sponsor, student registration): send each with two problems: the summary takes the focus, its links reach the fields, the answers are all there; tick nothing: the consent box is marked with the other problems in one pass; press Send seven times in an hour from one connection: the form is drawn with its answers and the time the hour turns; send twice quickly with scripts off: one row, one reference; reload a bounced page after 31 minutes: the expired sentence. 375px for the summary.
- [ ] **Student Report Card**: save the report with a grade "85%": the field is marked and every other answer is there; save hours: "Hours saved." in My course; save the second feedback form: its disclosure is open with its status inside.
- [ ] **Mentor Report Card**: plan a series with one clashing date: the planner is open with every field and the date marked; double-click Add a note: one note; move a module with the network off (the browser's offline switch): it is put back and the refusal is said.
- [ ] **Institution Dashboard**: import with a start date a year away and a pasted list: everything is there and the date is marked; choose a CSV over 256 KB: "That file is too big."; correct a start date past the end date: neither is written and both are marked; upload a signed agreement that is not a PDF: the message is red and takes the focus.
- [ ] **Sponsor Dashboard**: save the profile with a bad website: only the website is marked and the long texts are kept; pause, resume, take back and end an offer: four sentences, each in the Offers card; press the interest twice for one mentor: "already"; claim a tool's code: the result takes the focus.
- [ ] **Administrator Dashboard**: publish, return and approve from the queue cards: each outcome is inside its card, open; a refused decision is an alert.
- [ ] **Settings**: open the Students and mentors tab in two windows; switch a form off in one and save; save a lapse-days change in the other: the switch stays off; change the course link in both: the second is warned and the first's link kept; type an http course link: refused at the field, the old link kept; empty a Who is mailed list with its box unticked: refused; tick its box: saved as every Administrator account. `wp wpcredits setting get mentor_applications_enabled`, a `set ... --dry-run`, and `wp wpcredits setting set report_notify everyone`.
- [ ] **Students screen, Accounts tab**: start a bulk invitation run of the copy's test accounts only (the copy sends nothing), type in the search box and tick rows: nothing reloads; the count moves; VoiceOver hears the quarters; the finish sentence shows; leave the tab hidden for a while and come back.
- [ ] **Mentors screen, Sync tab** (the mu-plugin on): `wpcpm_trial_answer` `empty`, Sync now: the run stops with both counts and Run anyway, every mentor keeps the role, the panel stops polling and says why; Sync now again: it reads again and stops again. `under-half`: stops; `half`: goes on. `login`: Needs confirming lists Ada Example against the `ada-example` account, which holds no Mentor role; Confirm: it does. Press Sync now twice during a run: "already running". Hear the steps and the finish with VoiceOver. `wp wpcredits sync_mentors` with `empty`: the CLI's own stop sentence; with `--accept`: it goes on.
- [ ] **Mentor applications queue**: press Select in two tabs on one application: one email in the copy's mail log, the second press told who decided it.
- [ ] **Institutions and Sponsors screens**: a question whose mail fails is kept (stand the send in with the mail log's refusal if the copy cannot fail its mail host); edit one sponsor in two tabs: the second save is refused with the typing kept.
- [ ] **Track Builder, Emails, Notices, Mentor Status Checker**: three problems on one track; move a question, then save the track's name on the same page: saved, not stale; a stale track and a stale email from a second tab; Publish double-clicked; each notice's own Save in two tabs; Promote with the keyboard.
- [ ] **Put the copy back** from the snapshot, remove the mu-plugin, and say what the mail log held.
- [ ] **Commit the two fixtures**, after `php bin/test-fixtures.php` passes with them:

```bash
git add bin/fixtures/trial-mentors-empty.json bin/fixtures/trial-mentors-login-only.json
git commit -m "Scan release 1: the invented Airtable answers of the Sync trial" -m "<the Co-Authored-By trailer your session names for your model>"
```

---

### Task 16: Release, only on the owner's go

The release script's own order: check, build, scan, the mirror's dry run and, on the owner's yes, the mirror; the deploy's dry run and, on the owner's yes, the deploy; then the vault and the memory notes. Every command runs from `~/Projects/wpcredits-release` unless it says otherwise. The mirror, the deploy and anything outward are the controller's, after the owner's yes, through the release script; nothing in this plan pushes or deploys.

- [ ] **Step 1: The version, the changelog and the translation template** (on the branch)

The plugin header's `Version:` and `define( 'WPCPM_VERSION', ... )` in `wpcredits-program-manager.php`, and `Stable tag:` in `readme.txt`, from `1.123.57` to `1.123.58`; and, above `= 1.123.57 =` in `readme.txt`'s changelog:

```text
= 1.123.58 =

Scan release 1.

* Nothing typed is lost to a refusal. Every form that is refused comes back with what was typed, opens with a summary of what needs a change, linked to each field, and marks each field with its reason: the public forms, the Student Report Card's report, hours and feedback forms, the Mentor Report Card's session planner and notes, the Institution Dashboard's enrollment, correction, Add account and Invite a colleague forms, the Sponsor Dashboard's profile, offers and event planner, the Institutions and Sponsors screens, the Track Builder and Settings. A chosen file is the one thing a form cannot keep, and the form says so.
* A public form refused for too many attempts from one connection keeps its answers and says when to try again; the privacy box is checked with every other answer; a second press of Send files one application, not a second one as spam; a page whose kept answers expired says so.
* No action runs twice: Select, Ask for more information and Decline check the application again as they send, the note form, the Track Builder's Publish, Check and Take off, the link form and the four enrollment forms can be pressed once, a second interest in the same mentor within 30 days is on record already, a sync runs once at a time, and the invitation queue is written under a lock.
* A page opened earlier never undoes a change made since. A Settings tab writes only what changed on it and names a setting changed elsewhere meanwhile; a value a setting cannot take is refused, not saved as blank; a Who is mailed list is never emptied by accident, and its new box, Mail every Administrator account instead of the people below, is the way to every Administrator account. The header notices save one audience at a time; a sponsor, a track and an email edited after the page was opened are refused with the typing kept. `wp wpcredits setting get` and `set` change a setting by the Save's own rules, `everyone` setting a Who is mailed list to every Administrator account.
* The mentors and students syncs count before they take anyone's role, and stop on a read of nothing or of under half the linked accounts, with Run anyway on the Sync tab and `--accept` on the command line; a stopped run is not running, so the Sync tab stops polling and says why. A mentor found by an account's login alone waits under Needs confirming. Every sync waits out a passing Airtable refusal; a create that fails partway keeps the ids it made.
* No page reloads under you: the invitations card counts in place, and a screen reader hears it at each quarter and at the end; the sync's steps, a stall and the finish are heard, and its counts are said on the Sync tab.
* Every outcome is printed where you are: inside the card a dashboard press came from, with its tone, read out and focused; queue outcomes name the applicant; the offer moves say what each did; a rejection says whether its acknowledgement went; a failed module or question move is put back.
```

Then `bash bin/make-pot.sh`, and:

```bash
grep -n "Version:\|define( 'WPCPM_VERSION'" wpcredits-program-manager.php
grep -n "^Stable tag:\|^= 1.123.5[78] =" readme.txt
grep -n "Project-Id-Version" languages/wpcredits-program-manager.pot
```

Expected: `1.123.58` in each, and `= 1.123.58 =` above `= 1.123.57 =`. Run the checks every task ends with, then:

```bash
git add wpcredits-program-manager.php readme.txt languages/wpcredits-program-manager.pot
git commit -m "Scan release 1: version 1.123.58 and its changelog" -m "<the Co-Authored-By trailer your session names for your model>"
```

- [ ] **Step 2: Read the live version** (`ssh wpcredits-dashboard 'wp plugin get wpcredits-program-manager --field=version'`). Expected `1.123.56` or `1.123.57`; anything else, say what it reads and wait for the owner. If 1.123.57 is not live, take the owner's word on shipping both.
- [ ] **Step 3: Main holds the branch, on the owner's yes** (`git merge --ff-only`, or a rebase on main and the checks again if main moved).
- [ ] **Step 4: Check, build and scan** (`python3 release.py check plugin`, `build plugin`, `scan plugin`), each ending `PASSED`; read check's watch report of others' changes to the public repository before the mirror.
- [ ] **Step 5: The mirror's message and its dry run**: one plain paragraph in `messages/1.123.58.txt`, no names and no attribution, then `python3 release.py mirror plugin --message messages/1.123.58.txt` (exit 3, `NOT RUN`). Give the owner the version, the zip's md5, the checks' results, Task 15's captures and what the copy's mail log held, the watch report and the message; wait for an explicit yes.
- [ ] **Step 6: Mirror, then deploy, each on the owner's yes** (`mirror plugin --yes --message ...`; `deploy plugin`, its dry run, then `deploy plugin --yes`, which republishes the Administrator guide and purges the caches itself).
- [ ] **Step 7: The vault and the memory notes**: the plugin note gains the release's paragraph; `~/GitHub/wpcredits-program-manager.zip` is the release's build.

---

## What this plan leaves out

Every finding the brief names is placed in a task above. Of the parts of those findings the six changes do not cover, these are left for a release of their own, each with the reason:

- **SPA-31, two of its three frictions**: the seed buttons above the Offers table want a heading, and shared offers show code counts that do not apply to them. Neither is typed work, a second press, a stale page, a sync or an outcome; this plan takes SPA-31's Attach account half (the typed address and the reason) only.
- **The students sync has no command of its own**, so `--accept` exists for `wp wpcredits sync_mentors` alone; the students sync's Run anyway is on its Sync tab. A `sync_students` command is a release of its own.
- **The theme's own rules** for the new message tones: the plugin's sheets carry them (Tasks 1, 3, 6); the companion theme's `dashboard.css`, which colors the Institution Dashboard's messages too, matches them in a theme release.

## After the plan

- **The owner's rulings of 6 November 2026 on the calls this plan first left open, now in the tasks:**
  - **The count check compares with the accounts linked and marked active at the start of the run** (accepted as built): a run with nobody to revoke is never stopped by an empty read; a read of none, or under half the linked count, stops a run that has someone to revoke (Task 12).
  - **The invitations panel is not a live region** (accepted): the count moves silently every ten seconds, `wp.a11y.speak()` says the quarters and the finish, the finish also prints its sentence in the card, and the Refresh link stays (Task 3).
  - **The way back to every Administrator account**: each Who is mailed row has the box "Mail every Administrator account instead of the people below", and `wp wpcredits setting set <key> everyone` (Task 2).
  - **Queue outcomes name the applicant by one prefix pattern** (accepted), with its translators' comment naming the three parts (Task 9).
  - **phpcs's memory**: the checks run through the release kit's wrapper on `PATH` (Global Constraints).
- **Known and not pinned here:** a page holding two outcomes at once (a refused form's summary and a card's message from another press) moves the focus to the first in the page, which `focusOutcome()` chooses by order; on a host with no system cron, a bulk invitation run moves only while some request spawns WordPress's cron, and a hidden tab's poller makes none, where the old reload did. Neither has a test; both are for the trial to watch (Task 15).
- **Found while planning, not changed here:** phpcs runs out of memory at PHP's default 128M for the whole tree, which the release kit's wrapper works around; raising the limit in `phpcs.xml.dist` (`<ini name="memory_limit" value="512M"/>`) would make the bare `bash bin/check-standards.sh` work too, in a release of its own.
