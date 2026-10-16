# Release 1.122.30: institutions resend a student's welcome invitation, Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A member of an institution, or an Administrator who opened the institution's dashboard through the switcher, sends a student with no sign-in on record their welcome invitation again from the student's card on the Institution Dashboard: the site's own invitation with a new link, at most one every fifteen minutes for each student and twenty a day for each institution, each send recorded in the institution's history (issue WordPress/WPCredits#243).

**Architecture:** One new class, `WPCPM_Institution_Resend` (`includes/modules/class-wpcpm-institution-resend.php`), owns the press (`handle_resend()` on the admin-post action `wpcpm_institution_resend_invite`), what each outcome says (`messages()`) and the line on the card (`render()`), which `WPCPM_Institution_Student_View::render()` calls under the student's identity, guarded as it calls its other two controls. The policy gains one action, `ACT_RESEND_INVITE`, on the manager and member grounds. Three shared classes each gain one small answer the press asks for: `WPCPM_Sign_Ins::has_signed_in()`, `WPCPM_Mail::too_soon_message()` and `WPCPM_Ceiling::release()`. The send is `WPCPM_Students_Sync::send_invite()`, the one the Resend invite in wp-admin runs; the record is `WPCPM_Institution_Audit::record()`; the notice travels in `WPCPM_Flash`.

**Tech Stack:** WordPress 7.1 plugin PHP (7.4 and 8.x), the plugin's `bin/test-*.php` suites (plain PHP with their own stand-ins, one process each), `bin/check-references.php`, `bin/check-spelling.php`, `bin/check-standards.sh` (phpcs with the WordPress standard), `bin/build-docs.php`, `bin/make-pot.sh` (WP-CLI's `wp i18n make-pot` when it is installed), the built-in browser and WP-CLI through Studio for the trial.

**Spec:** `docs/specs/2026-10-16-institution-resend-invitation-design.md`, the owner's four decisions of 16 October 2026, amended the same day with the owner's answers to this plan's review (the card's wording, the mail refused, the privacy clause). Read it before any task: every requirement below argues from it.

**Base:** main at 7b790c0 (the design of 74bb1d2 and the first version of this plan), with the amended design and this amended plan committed on top by the controller. Live runs 1.122.29. Every anchor below is that tree's text, and the branch starts from it.

**Order:** the policy's action (Task 1); the three answers the press asks for (Task 2); the press, with its boot, its loader lines and its suite (Task 3); the line on the card, the card's call and the stylesheet (Task 4); the guide, the version, the changelog and the template, and the final battery (Task 5); the trial on the main-line Studio copy, by the controller (Task 6).

**Where the spec left a choice, this plan chooses:**

- **The day's place is given back when the send answers an error or the host refuses its email.** Section 4 claims the place before the send (step 4, then step 5) and section 7 wants no refusal but the ceiling's own to spend it. The send can still refuse after every check before it has passed, when another route invites the student in the same instant or the account loses the Student role, and the host can refuse the email, so `WPCPM_Ceiling` gains `release()` and the press calls it in all three cases. A press that sends nothing costs the school nothing, whatever stopped it.
- **A refused email is heard, not taken for sent** (the owner's answer of 16 October 2026). `send_invite()` calls `wp_new_user_notification()`, which drops what `wp_mail()` answered, and then stamps the account; so the press adds a listener on `wp_mail_failed` right before `send_invite()` and removes it right after, for that one send and no other. On a refusal it gives the place back, writes no history row and says the shared error sentence; the stamp `send_invite()` wrote stays, since the new link it minted has replaced the old one either way. The Resend invite in wp-admin keeps its own behavior.
- **The line says what the site knows** (the owner's answer of 16 October 2026): "No sign-in on record since October 15, 2026." in the place of "Not signed in yet.", followed by "Invitation last sent <date>." or "No invitation sent yet.", each pair one translatable sentence. The first date is `WPCPM_Sign_Ins::since()`, the day the record began, in the site's date format, never written into the code. The predicate is the design's (no `wpcpm_last_sign_in`), and the line is drawn only while `since()` is above zero: before the record began there is no first day to name, and on a site running 1.122.26 or later the record begins at an Administrator's first wp-admin page (live began it on 15 October 2026). The trial begins it on the copy if no Administrator has.
- **The ceiling's key is `institution-resend:` and the record ID as written,** built by `WPCPM_Institution_Resend::ceiling_key()` rather than through `WPCPM_Ceiling::key()`, which lowercases what it is given: a record ID is case-sensitive, so two institutions whose IDs differ only in case are two allowances. `WPCPM_Institution_Invite::ceiling_key()` builds its key the same way for the same reason.
- **A first invitation's notice leaves out the replaced link.** "The new link replaces the one in the earlier email." is true only when there was an earlier email; a student never invited is told "The invitation is on its way to {masked address}." alone (`invite-sent`), and the history row says `first`. The owner approved this sentence, `invite-unlogged`'s and the card's use of the policy's refusal (`invite-refused`) as written, on 16 October 2026.
- **An account that is not a student, or has no address to send to, gets the shared error sentence, before the day's place is claimed.** The card is visible to the reader in both cases (an account the sync stamped and then took the Student role from still has a card), so the policy's one refusal ("That record is not on your roster.") would be untrue there; "The invitation could not be sent." is the sentence the Resend invite in wp-admin prints. An address that is not an email address is refused rather than told an invitation is on its way to nobody.
- **The policy's one refusal also covers a decision that names no institution.** The manager ground allows an account with no institution stamp; a send for it could be counted against no institution and recorded under none, so the press refuses it with the policy's sentence and the card draws no button for it.
- **The fifteen minutes' sentence is worded once,** in `WPCPM_Mail::too_soon_message()`, which `may_invite()` now builds its error from; the card's notice prints the same call.
- **A history row that will not insert after the email went is said out loud** (`invite-unlogged`), as the other two student writes on this dashboard say theirs (`student-unlogged`, `status-unlogged`); the email has gone, so the place stays spent.
- **An Administrator's presses through the switcher count against the institution's twenty,** under the institution the decision names (the student's own), and their history row carries the `manager` ground.
- **The card takes the flash before it asks the policy,** so a notice meant for that card is spent by the render that could print it; a reader the policy refuses is printed nothing, notice or not, and a student who has signed in since the page was drawn gets the notice without the line.
- **The line sits inside the student card, right under the identity block,** 8px under the name (the identity's margin drops to 0.5em through `:has()`, a progressive touch the calendar stylesheet already uses) and 1.25em above the table, the gap the table had. The notice takes the bordered shape every flash on this page has.
- **The ceiling's sentence goes through `_n()`,** with the twenty as its number, so a language with more than two plural forms can say it.
- **The day is the ceiling's day,** a window of `DAY_IN_SECONDS` counted from midnight UTC as every daily ceiling in the plugin is, so "Try again tomorrow" is approximate for a school far from UTC.
- **The institutions guide is the handbook page on make.wordpress.org** (`WPCPM_Handbook_Assistant::guides()['institution']`), which is not in this repository: Task 5 writes the Administrators' paragraph into `docs/sections/34-admin-operations.md` and gives the owner the institutions paragraph to publish on the handbook page after the deploy. No screen lists an institution's audit rows today, so the Administrators' paragraph names the Emails tool's Log, which lists each send, and the institution's audit log, which records who pressed.

## Global Constraints

- Release (spec, head): "Plugin release 1.122.30 (live runs 1.122.29). Issue: WordPress/WPCredits#243." Version **1.122.30** in the plugin header (`Version:`), `WPCPM_VERSION` and `readme.txt`'s `Stable tag`; a `= 1.122.30 =` entry under `== Changelog ==`, above 1.122.29's; the guides rebuilt (`php bin/build-docs.php`); the translation template regenerated last (`sh bin/make-pot.sh`). The owner confirmed on 16 October 2026 that this release is 1.122.30.
- If main has moved past 1.122.29 when this plan starts, take the next free 1.122 number wherever this plan says 1.122.30, branch from main as it then is, and re-derive each step's expected battery line from the base run of Before Task 1, step 3; the code and the suites' own counts do not change.
- Decision 1 (spec 2): "**Who:** every member of the institution, for any student on its roster, as adding and editing students already work (membership is the permission, decision 13 of the institutions design). An Administrator who opens the institution's dashboard through the switcher may press it too; Administrators keep their own Resend in wp-admin."
- Decision 2 (spec 2): "**Which students:** those with an account and no recorded sign-in (the sign-in record, `wpcpm_last_sign_in`, since 1.122.26). A student who has signed in once gets no new control: a lost password has WordPress's own reset."
- Decision 3 (spec 2): "**Limits:** the existing 15 minutes per student (`WPCPM_Mail::may_invite()`: another send would cancel the link just mailed), and a ceiling of 20 resends a day for each institution."
- Decision 4 (spec 2): "**The email and the record:** the student gets the site's own welcome invitation, exactly the email Administrators send from wp-admin (`WPCPM_Students_Sync::send_invite()`), with a new link that replaces the old one. The institution's history records who resent it and when. The program team is not emailed."
- The line (spec 3, as amended): *"No sign-in on record since October 15, 2026. Invitation last sent October 9, 2026." The first date is the day the site began recording sign-ins (`WPCPM_Sign_Ins::since()`), never written into the code; the second is the last invitation (`WPCPM_Mail::last_invited()`); both in the site's date format. When the site has never sent one: "No sign-in on record since October 15, 2026. No invitation sent yet."* And: *"A student with a sign-in on record shows no line and no button: the card says nothing new about them, so the sign-in record is read for one question only (is a sign-in on record for this student) and no date of a sign-in is shown to the school."*
- The mail refused (spec 3, as amended): *the shared error sentence the Resend in wp-admin shows, "The invitation could not be sent." [...] or the host refuses the email, which WordPress reports on `wp_mail_failed` and the press listens for around its own send alone: the day's place is given back and no history row is written; the stamp the send wrote stays.*
- The press (spec 4): the action `wpcpm_institution_resend_invite`, its nonce keyed to the student's account (`wpcpm_institution_resend_invite_{user_id}`), and the order nonce, policy, student, gap, ceiling, send, record, back to the card's own anchor with the notice.
- Privacy (spec 5, as amended): *"**For the owner, before the deploy:** a clause in that paragraph, for example "and an institution sees whether a sign-in is on record for each of its students since October 15, 2026, and nothing more, so that it can send the welcome invitation again"."* The owner adds it to the Privacy Notice (page 654); the release waits for it.
- Not in this release (spec 8): "Resending for a student with no account yet (the roster's rows without one); a bulk resend from the roster; telling the program team."
- Copy, in every string, comment, guide line, changelog line and commit message: US English; plain hyphens only, never an em or en dash; "Administrators" and "Administrator", never "program manager"; never the on-screen word "module"; full product names (Institution Dashboard, Student Report Card, Mentor Report Card); every new string translatable in `wpcredits-program-manager`, with a translators comment where it has a placeholder. Comments state the code's fact and name no plan, task, review or finding.
- The repository is mirrored publicly: no file, message or comment names unreleased or private work; invented names and `@example.test` addresses only; invented record IDs are uppercase letters and digits only (the suite builds the one lowercase variant it needs at run time); this plan carries no address and no account ID.
- Every method in a file under `includes/` has a docblock; `sh bin/check-standards.sh` stays at main's 0 errors and 78 warnings, with no new warning.
- Tests use stand-ins only: no request leaves a suite and no email is sent. Each task's failing check is written and run first; the task's code makes it pass; the whole battery is silent before the commit.
- Never `git stash`. Commit after each task's battery passes. Never touch the live site, the Studio copies or the Docker copy while building; the trial (Task 6) is the controller's, on the main-line Studio copy, inside a database snapshot put back afterwards. Never build into `~/GitHub/*.zip`; the trial's zip goes to the session's scratchpad.
- Each commit message ends with a blank line and the attribution line the session's instructions name, held in `$COAUTHOR` (Before Task 1).
- The release is not this plan's: it runs later through `~/Projects/wpcredits-release` (check, build, scan, mirror, deploy) on the owner's yes for it, with the live version read first.

## Review Focus

1. **Two presses close together**, a double press or two members pressing for the same student: one email, and the second press is refused by the fifteen minutes without spending a place or writing a row; the form is locked at its first press (`data-wpcpm-once`, busy word "Sending..."). Pinned in Task 3, step 1 ("a second press within fifteen minutes sends nothing") and Task 4, step 1 (the form's attributes).
2. **A card drawn before the world changed and pressed after it:** the student signed in, the account lost the Student role, the agreement is no longer settled, or the presser is no longer a member. Each is refused, nothing sent, stamped, recorded or spent, and the student who signed in gets the notice without the line. Pinned in Task 3, step 1 (the refusals) and Task 4, step 1 (the notice without the line).
3. **A form for one student replayed with another student's account in it, or posted by a member of another institution:** the token is checked against the account the form names, and the policy decides on that account's own stamp, so the press is refused with the one refusal and nothing reveals whether the student exists. Pinned in Task 3, step 1 (the token's action, the member of B) and Task 4, step 1 (the nonce keyed to the student on screen, one link for two readers).
4. **An account with no address to send to**, or one that is no address: refused before the day's place is claimed, never "on its way to ." Pinned in Task 3, step 1.
5. **A send that fails after the claim, at the edge of the day's twenty:** another route invites the student in the same instant, the account loses the Student role between the checks and the send, or the host refuses the email; one place is given back, never more, and the twenty-first press is refused while the twentieth sends. Pinned in Task 3, step 1 (played between the claim and the send on a day three places in, and the stand-in firing `wp_mail_failed`) and Task 2, step 1 (`release()`).

## Execution

Subagent-driven, strictly sequential in one worktree: Tasks 3 and 4 share `includes/modules/class-wpcpm-institution-resend.php` and `bin/test-institution-resend.php`, and every task after the first consumes the names the one before it produced. Every task gets its task review; the most capable reviewer goes to Task 3 (the press: the order of its refusals, the day's place, the policy's grounds) and then Task 1 (the fence). Each commit message is the one its step gives.

---

## File Structure

| File | Responsibility |
| --- | --- |
| `includes/modules/class-wpcpm-institution-policy.php` (Task 1) | `ACT_RESEND_INVITE` and its row in `grounds()`. |
| `includes/class-wpcpm-sign-ins.php`, `includes/class-wpcpm-mail.php`, `includes/class-wpcpm-ceiling.php` (Task 2) | `has_signed_in()`; `too_soon_message()`, which `may_invite()` builds its error from; `release()`. |
| `includes/modules/class-wpcpm-institution-resend.php` (created in Task 3; Task 4) | The press and its notices (Task 3); the line on the card (Task 4). |
| `wpcredits-program-manager.php`, `uninstall.php`, `includes/modules/class-wpcpm-institutions.php` (Task 3; the version in Task 5) | The class required by the loader and by `uninstall.php`, beside the other institution classes, and started by `WPCPM_Institutions::boot()` right after graduate and withdraw. |
| `includes/modules/class-wpcpm-institution-student-view.php`, `assets/css/institution.css` (Task 4) | The card's call under the identity block; the line's rules. |
| `bin/test-institution-resend.php` (created in Task 3; Task 4) | The class's suite: the real policy, flash, ceiling, mail, sign-in record, audit log and students sync, on stand-ins for WordPress, the members, the agreements and the dashboard's address. |
| `bin/test-institution-policy.php` (Task 1), `bin/test-sign-ins.php`, `bin/test-mail.php`, `bin/test-ceiling.php` (Task 2), `bin/test-institutions-screen.php`, `bin/test-institutions-accounts.php` (Task 3), `bin/test-student-view-controls.php` (Task 4) | The existing suites, each gaining what its file gains; the two that boot `WPCPM_Institutions` gain a stand-in for the new class. |
| `docs/sections/34-admin-operations.md`, then `docs/administrators.md` and `docs/build/administrators.html`; `readme.txt`; `languages/wpcredits-program-manager.pot` (Task 5) | The guide's paragraph, the changelog, the version and the template. |

Every suite is plain PHP run as `php bin/<suite>.php`, printing `ok` and `FAIL` lines and a last line with the result (`ALL PASS (N checks)`, or `ALL PASS` for the older suites, or `N FAILURE(S)`), and exiting non-zero on a failure. "The battery" is every suite in its own process, counted, then the references check:

```bash
n=0; bad=0; ok=0
for f in bin/test-*.php; do
	n=$((n+1))
	out=$(php "$f" 2>&1) || { bad=$((bad+1)); echo "FAIL $f"; }
	ok=$((ok + $(printf '%s\n' "$out" | grep -c '^ok ')))
done
echo "battery: $n suites, $bad failing, $ok ok lines"
php bin/check-references.php | tail -n 1
```

"The checks" are the standards (through the wrapper made before Task 1, since phpcs needs more than PHP's default 128 MB on this tree) and the spelling:

```bash
PATH="$SCRATCH/phpcs-512:$PATH" sh bin/check-standards.sh 2>&1 | tail -n 1
php bin/check-spelling.php | tail -n 1
```

How each change is written: "Create `path`:" gives a whole new file; "In `path`, replace:" gives the exact text to find, once in the file as the steps before left it, and "with:" the text to put in its place; the replacements of a step are made in the order given. Each task's failing run is made in the task's tree after its test step and before its code step.

---

## Before Task 1: the branch and the tools

- [ ] **Step 1: The branch, in a worktree of its own**

```bash
cd ~/GitHub/wpcredits-program-manager
git worktree add .worktrees/resend-1-122-30 -b institution-resend-1-122-30 main
cd .worktrees/resend-1-122-30
git log --oneline -1
```

Expected: the commit that adds the amended design and this amended plan, on top of `7b790c0 Institutions resend a student's welcome invitation: the plan for 1.122.30, six tasks proven from their own text`.

- [ ] **Step 2: The scratch folder, the attribution line and the phpcs wrapper**

```bash
export SCRATCH="<the session's scratchpad>/resend-1-122-30"
export COAUTHOR="<the attribution line the session's instructions name>"
mkdir -p "$SCRATCH/phpcs-512"
printf '#!/bin/sh\nexec php -d memory_limit=512M "$HOME/.composer/vendor/bin/phpcs" "$@"\n' > "$SCRATCH/phpcs-512/phpcs"
chmod +x "$SCRATCH/phpcs-512/phpcs"
```

- [ ] **Step 3: The battery and the checks on the base, for the record**

Run the battery and the checks (see "The battery" above).

Expected:

```text
battery: 129 suites, 0 failing, 16700 ok lines
153 classes, 14469 references checked, 87 form handlers scanned - all resolve
78 warnings, no errors.
156 PHP files, 7 blocks, 4 track seeds, 23 fixture and tooling files, the guides and the readme: US English throughout.
```

---

### Task 1: The policy's action

The one fence learns one action. Membership is the permission, as it is for editing a student, so the action takes the manager and member grounds, and the agreement gate applies to it as to every member action but the agreement's own.

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-policy.php` (the constants after `ACT_CHANGE_STATUS`; the row after `ACT_CHANGE_STATUS` in `grounds()`)
- Test: `bin/test-institution-policy.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `WPCPM_Institution_Policy::ACT_RESEND_INVITE` = `'resend_invite'`; `grounds()` maps it to `array( 'manager', 'member' )`, tried in that order. `decide( ACT_RESEND_INVITE, subject_student_account( $user_id ) )` allows a member of the account's stamped institution while its agreement is settled (ground `member`, `institution` the stamp) and an Administrator always (ground `manager`, `institution` the stamp or `''` when there is none); everybody else is refused (`why` `no-ground`, or `no-user` for nobody signed in).

- [ ] **Step 1: Write the failing checks**

In `bin/test-institution-policy.php`, replace:

```php
	'change_status'        => array( 'manager', 'member' ),
	'add_student'          => array( 'manager', 'member' ),
```

with:

```php
	'change_status'        => array( 'manager', 'member' ),
	// A student's welcome invitation, sent again from their card: the member ground for the
	// student's own institution, as editing a student is, and the manager ground.
	'resend_invite'        => array( 'manager', 'member' ),
	'add_student'          => array( 'manager', 'member' ),
```

In `bin/test-institution-policy.php`, replace:

```php
ck( 'a live subject is decided on the link claim() read', $P::decide( $P::ACT_VIEW_REPORT, $P::subject_live( 'report', $S1, array( $B ) ), 2 ), refused_for( 'no-ground' ) );
```

with:

```php
ck( 'a live subject is decided on the link claim() read', $P::decide( $P::ACT_VIEW_REPORT, $P::subject_live( 'report', $S1, array( $B ) ), 2 ), refused_for( 'no-ground' ) );

// Sending a student's invitation again, on the account's own stamp.
ck( "a member of A may send A's student their invitation again", $P::decide( $P::ACT_RESEND_INVITE, $P::subject_student_account( 8 ), 2 ), allowed_as( 'member', $A ) );
ck( "a member of B may not", $P::decide( $P::ACT_RESEND_INVITE, $P::subject_student_account( 8 ), 3 ), refused_for( 'no-ground' ) );
ck( "an Administrator may, the decision naming the student's own institution", $P::decide( $P::ACT_RESEND_INVITE, $P::subject_student_account( 8 ), 1 ), allowed_as( 'manager', $A ) );
ck( 'the student may not send their own', $P::decide( $P::ACT_RESEND_INVITE, $P::subject_student_account( 8 ), 8 ), refused_for( 'no-ground' ) );
ck( 'nor may an account that is nobody\'s member', $P::decide( $P::ACT_RESEND_INVITE, $P::subject_student_account( 8 ), 4 ), refused_for( 'no-ground' ) );
$GLOBALS['uid'] = 0;
ck( 'nor a signed-out visitor', $P::decide( $P::ACT_RESEND_INVITE, $P::subject_student_account( 8 ) ), refused_for( 'no-user' ) );
ck( 'a student with no stamp is an Administrator\'s alone, naming no institution',
    array( $P::decide( $P::ACT_RESEND_INVITE, $P::subject_student_account( 9 ), 2 ), $P::decide( $P::ACT_RESEND_INVITE, $P::subject_student_account( 9 ), 1 ) ),
    array( refused_for( 'no-ground' ), allowed_as( 'manager', '' ) ) );
```

- [ ] **Step 2: Run the suite to see it fail**

Run: `php bin/test-institution-policy.php > "$SCRATCH/red.txt" 2>&1; echo "exit $?"; grep -E '^(FAIL|PHP Fatal error|Fatal error)' "$SCRATCH/red.txt"`

Expected (`<tree>` is the worktree's path; the line numbers are the suite's as this step leaves it):

```text
exit 255
FAIL grounds() is exactly the expected map, in order
FAIL a manager passes resend_invite on A, as a manager
FAIL a manager passes resend_invite on an institution-less subject, naming no institution
FAIL a member of A passes resend_invite on A, as a member
FAIL a member of B is refused resend_invite on A with no ground
FAIL a member of A is refused resend_invite on an institution-less subject
FAIL an account with no membership is refused resend_invite
FAIL a manager who is also a member passes resend_invite as a manager, the first ground
FAIL while A's agreement is not settled, a member gets resend_invite: refused
FAIL while A's agreement is not settled, a manager passes resend_invite
PHP Fatal error:  Uncaught Error: Undefined constant WPCPM_Institution_Policy::ACT_RESEND_INVITE in <tree>/bin/test-institution-policy.php:424
Fatal error: Uncaught Error: Undefined constant WPCPM_Institution_Policy::ACT_RESEND_INVITE in <tree>/bin/test-institution-policy.php:424
```

- [ ] **Step 3: Add the action**

In `includes/modules/class-wpcpm-institution-policy.php`, replace:

```php
	/** Graduate, withdraw. */
	const ACT_CHANGE_STATUS = 'change_status';
```

with:

```php
	/** Graduate, withdraw. */
	const ACT_CHANGE_STATUS = 'change_status';

	/** Send a student's welcome invitation again, from their card. */
	const ACT_RESEND_INVITE = 'resend_invite';
```

In `includes/modules/class-wpcpm-institution-policy.php`, replace:

```php
			self::ACT_CHANGE_STATUS        => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
```

with:

```php
			self::ACT_CHANGE_STATUS        => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
			self::ACT_RESEND_INVITE        => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
```

- [ ] **Step 4: Run the suite to see it pass**

Run: `php bin/test-institution-policy.php | tail -n 1; php bin/test-institution-policy.php | grep -c '^ok '`

Expected:

```text
ALL PASS
254
```

The walk over every action now covers `resend_invite` for a manager, a member of A, a member of B, an institution-less subject, an account with no membership, a manager who is also a member and the locked agreement, without a line of its own: the map is what it walks.

- [ ] **Step 5: The battery and the checks**

Expected:

```text
battery: 129 suites, 0 failing, 16716 ok lines
153 classes, 14472 references checked, 87 form handlers scanned - all resolve
78 warnings, no errors.
156 PHP files, 7 blocks, 4 track seeds, 23 fixture and tooling files, the guides and the readme: US English throughout.
```

- [ ] **Step 6: Commit**

```bash
git add includes/modules/class-wpcpm-institution-policy.php bin/test-institution-policy.php
git commit -m "1.122.30: the policy has an action for sending a student's invitation again, on the manager and member grounds" -m "$COAUTHOR"
```

---

### Task 2: Three answers the press asks for

Each is one method on a class every route already shares, so the card, the press and the Resend invite in wp-admin ask the same question in the same words: whether a sign-in is on record for an account, the sentence the fifteen minutes refuse with, and a place of a ceiling given back.

**Files:**
- Modify: `includes/class-wpcpm-sign-ins.php` (a method before `on_visit()`)
- Modify: `includes/class-wpcpm-mail.php` (`may_invite()`'s error, and a method after it)
- Modify: `includes/class-wpcpm-ceiling.php` (a method before `count()`)
- Test: `bin/test-sign-ins.php`, `bin/test-mail.php`, `bin/test-ceiling.php`

**Interfaces:**
- Consumes: nothing new.
- Produces:
  - `WPCPM_Sign_Ins::has_signed_in( int $user_id ): bool`: true when `wpcpm_last_sign_in` holds a number above zero; never reads or returns the time.
  - `WPCPM_Mail::too_soon_message(): string`: "This person was sent an invitation less than 15 minutes ago. Another one now would cancel the link in it, so nothing was sent." (the minutes from `INVITE_GAP`); `may_invite()`'s `wpcpm_invite_too_soon` error carries it.
  - `WPCPM_Ceiling::release( string $key, int $window, int $amount = 1 ): bool`: takes `$amount` off the current window's count for the key, never below zero, never autoloaded; false, and nothing written, when the window has no place taken.

- [ ] **Step 1: Write the failing checks**

The mail suite's stand-in for `WP_Error` dropped every message; it keeps it now, so the refusal's sentence can be read.

In `bin/test-sign-ins.php`, replace:

```php
// Last, because a constant cannot be taken back.
fresh();
define( 'REST_REQUEST', true );
```

with:

```php
echo "\n=== Whether an account has signed in at all ===\n";

// The Institution Dashboard asks this one question of the record, for a student's card, and never
// reads the time itself.
fresh();
$GLOBALS['umeta'][40] = array( $S => $T );
$GLOBALS['umeta'][41] = array( $S => (string) $T );
$GLOBALS['umeta'][42] = array();
$GLOBALS['umeta'][43] = array( $S => 0 );
$GLOBALS['umeta'][44] = array( $S => 'yesterday' );
$GLOBALS['umeta'][45] = array( $V => $T );
ck( 'a recorded sign-in, as an int or as the string user meta comes back as, is a sign-in',
	array( WPCPM_Sign_Ins::has_signed_in( 40 ), WPCPM_Sign_Ins::has_signed_in( 41 ) ), array( true, true ) );
ck( 'no key, a zero or a word is never signed in',
	array( WPCPM_Sign_Ins::has_signed_in( 42 ), WPCPM_Sign_Ins::has_signed_in( 43 ), WPCPM_Sign_Ins::has_signed_in( 44 ) ), array( false, false, false ) );
ck( 'a visit alone is not a sign-in', WPCPM_Sign_Ins::has_signed_in( 45 ), false );
ck( 'and asking writes nothing', $GLOBALS['writes'], 0 );

// Last, because a constant cannot be taken back.
fresh();
define( 'REST_REQUEST', true );
```

In `bin/test-mail.php`, replace:

```php
class WP_Error {
	private $data;
	private $code;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_data() { return $this->data; }
	public function get_error_message() { return ''; }
}
```

with:

```php
class WP_Error {
	private $data;
	private $code;
	private $message;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_data() { return $this->data; }
	public function get_error_message() { return (string) $this->message; }
}
```

In `bin/test-mail.php`, replace:

```php
ck( 'and answers true for somebody never invited', array( WPCPM_Mail::may_invite( 806 ) ), array( true ) );
```

with:

```php
ck( 'and answers true for somebody never invited', array( WPCPM_Mail::may_invite( 806 ) ), array( true ) );
// Worded once: the Institution Dashboard prints the same sentence after a press it refused.
ck( 'its refusal is the sentence too_soon_message() words, with the fifteen minutes in it',
    array( $refusal->get_error_message(), WPCPM_Mail::too_soon_message() ),
    array_fill( 0, 2, 'This person was sent an invitation less than 15 minutes ago. Another one now would cancel the link in it, so nothing was sent.' ) );
```

In `bin/test-ceiling.php`, replace:

```php
ck( 'every update_option() call passes autoload false',
    array( preg_match_all( '/update_option\( \$/', $source ), preg_match_all( '/update_option\( \$[^;]*?,\s*false\s*\);/s', $source ) ),
    array( 1, 1 ) );
```

with:

```php
// Two writes: a claim's and a release's.
ck( 'every update_option() call passes autoload false',
    array( preg_match_all( '/update_option\( \$/', $source ), preg_match_all( '/update_option\( \$[^;]*?,\s*false\s*\);/s', $source ) ),
    array( 2, 2 ) );
```

In `bin/test-ceiling.php`, replace:

```php
echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
```

with:

```php
echo "\n=== A place given back ===\n";

// For a claim whose action did not happen: the Institution Dashboard claims a place of the day's
// invitations before it sends one, and gives the place back when the send answers an error, so a
// press that sends nothing costs the school nothing.
reset_store();
WPCPM_Ceiling::claim( 'resend', 3, DAY_IN_SECONDS );
WPCPM_Ceiling::claim( 'resend', 3, DAY_IN_SECONDS );
ck( 'a release gives one place back', array( WPCPM_Ceiling::release( 'resend', DAY_IN_SECONDS ), WPCPM_Ceiling::count( 'resend', DAY_IN_SECONDS ) ), array( true, 1 ) );
ck( 'which a later claim takes again, up to the limit and no further',
    array( WPCPM_Ceiling::claim( 'resend', 3, DAY_IN_SECONDS ), WPCPM_Ceiling::claim( 'resend', 3, DAY_IN_SECONDS ), WPCPM_Ceiling::claim( 'resend', 3, DAY_IN_SECONDS ) ),
    array( true, true, false ) );
ck( 'its write is not autoloaded, and keeps the row\'s shape', array( end( $GLOBALS['writes'] )[2], $GLOBALS['opts'][ name_for( 'resend', DAY_IN_SECONDS ) ] ),
    array( false, array( 'v' => 1, 'window' => DAY_IN_SECONDS, 'bucket' => bucket_now( DAY_IN_SECONDS ), 'count' => 3 ) ) );
ck( 'an amount gives back that many, and never below zero, in the count or in the row',
    array( WPCPM_Ceiling::release( 'resend', DAY_IN_SECONDS, 2 ), WPCPM_Ceiling::count( 'resend', DAY_IN_SECONDS ), WPCPM_Ceiling::release( 'resend', DAY_IN_SECONDS, 5 ), WPCPM_Ceiling::count( 'resend', DAY_IN_SECONDS ), $GLOBALS['opts'][ name_for( 'resend', DAY_IN_SECONDS ) ]['count'] ),
    array( true, 1, true, 0, 0 ) );

$written = count( $GLOBALS['writes'] );
ck( 'a window at zero gives nothing back and writes nothing', array( WPCPM_Ceiling::release( 'resend', DAY_IN_SECONDS ), count( $GLOBALS['writes'] ) ), array( false, $written ) );
ck( 'nor does a window with no row, which is left with none', array( WPCPM_Ceiling::release( 'never', DAY_IN_SECONDS ), in_array( name_for( 'never', DAY_IN_SECONDS ), ceiling_names(), true ) ), array( false, false ) );

reset_store();
WPCPM_Ceiling::claim( 'mine', 5, DAY_IN_SECONDS );
WPCPM_Ceiling::claim( 'theirs', 5, DAY_IN_SECONDS );
WPCPM_Ceiling::release( 'mine', DAY_IN_SECONDS );
ck( 'a release touches its own key and no other', array( WPCPM_Ceiling::count( 'mine', DAY_IN_SECONDS ), WPCPM_Ceiling::count( 'theirs', DAY_IN_SECONDS ) ), array( 0, 1 ) );

seed( 'old', DAY_IN_SECONDS, -1, 4 );
ck( 'and the current window\'s only: yesterday\'s row keeps its count', array( WPCPM_Ceiling::release( 'old', DAY_IN_SECONDS ), $GLOBALS['opts'][ name_for( 'old', DAY_IN_SECONDS, -1 ) ]['count'] ), array( false, 4 ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
```

- [ ] **Step 2: Run the three suites to see them fail**

Run: `for s in sign-ins mail ceiling; do php bin/test-$s.php > "$SCRATCH/red.txt" 2>&1; echo "== $s, exit $?"; grep -E '^(FAIL|PHP Fatal error|Fatal error)' "$SCRATCH/red.txt"; done`

Expected:

```text
== sign-ins, exit 255
PHP Fatal error:  Uncaught Error: Call to undefined method WPCPM_Sign_Ins::has_signed_in() in <tree>/bin/test-sign-ins.php:304
Fatal error: Uncaught Error: Call to undefined method WPCPM_Sign_Ins::has_signed_in() in <tree>/bin/test-sign-ins.php:304
== mail, exit 255
PHP Fatal error:  Uncaught Error: Call to undefined method WPCPM_Mail::too_soon_message() in <tree>/bin/test-mail.php:1502
Fatal error: Uncaught Error: Call to undefined method WPCPM_Mail::too_soon_message() in <tree>/bin/test-mail.php:1502
== ceiling, exit 255
FAIL every update_option() call passes autoload false
PHP Fatal error:  Uncaught Error: Call to undefined method WPCPM_Ceiling::release() in <tree>/bin/test-ceiling.php:420
Fatal error: Uncaught Error: Call to undefined method WPCPM_Ceiling::release() in <tree>/bin/test-ceiling.php:420
```

- [ ] **Step 3: Write the three methods**

In `includes/class-wpcpm-sign-ins.php`, replace:

```php
	/**
	 * A signed-in page view, front end (`template_redirect`) or wp-admin (`admin_init`).
	 */
```

with:

```php
	/**
	 * Whether the account has a recorded sign-in: a time above zero, as the seed reads one. An
	 * account the seed could not place, or whose key holds anything but a time, reads as never
	 * signed in. A visit alone is not a sign-in.
	 *
	 * @param int $user_id The account.
	 * @return bool
	 */
	public static function has_signed_in( $user_id ) {
		$stored = get_user_meta( (int) $user_id, self::META_SIGN_IN, true );

		return is_numeric( $stored ) && (int) $stored > 0;
	}

	/**
	 * A signed-in page view, front end (`template_redirect`) or wp-admin (`admin_init`).
	 */
```

In `includes/class-wpcpm-mail.php`, replace:

```php
		return new WP_Error(
			'wpcpm_invite_too_soon',
			sprintf(
				/* translators: %d: a number of minutes. */
				__( 'This person was sent an invitation less than %d minutes ago. Another one now would cancel the link in it, so nothing was sent.', 'wpcredits-program-manager' ),
				(int) ( self::INVITE_GAP / MINUTE_IN_SECONDS )
			),
			array( 'wait' => $wait )
		);
	}
```

with:

```php
		return new WP_Error( 'wpcpm_invite_too_soon', self::too_soon_message(), array( 'wait' => $wait ) );
	}

	/**
	 * The sentence `may_invite()` refuses with: the last invitation went less than `INVITE_GAP`
	 * ago, so nothing was sent.
	 *
	 * Worded once, for that refusal and for the notice the Institution Dashboard prints after a
	 * press it refused, so the two cannot drift apart.
	 *
	 * @return string
	 */
	public static function too_soon_message() {
		return sprintf(
			/* translators: %d: a number of minutes. */
			__( 'This person was sent an invitation less than %d minutes ago. Another one now would cancel the link in it, so nothing was sent.', 'wpcredits-program-manager' ),
			(int) ( self::INVITE_GAP / MINUTE_IN_SECONDS )
		);
	}
```

In `includes/class-wpcpm-ceiling.php`, replace:

```php
	/**
	 * How many claims the current window holds for a key.
```

with:

```php
	/**
	 * Give back places a claim took in the current window, when what it was claimed for did not
	 * happen.
	 *
	 * Read and written back, as a later claim is, with the same race: two releases that read the
	 * same count give back one place between them, and a release and a claim that read the same
	 * count keep only one of their two writes. Every ceiling here is a nuisance control, so that
	 * is the price of taking no lock, as it is for claims. The count never goes below zero, and a
	 * window with no row, or none above zero, is left as it is. A release that lands after the
	 * window of its claim has closed gives back a place of the window it lands in.
	 *
	 * @param string $key    What is being limited, as it was claimed.
	 * @param int    $window Window length in seconds, the same one `claim()` was given.
	 * @param int    $amount How many places to give back. One by default.
	 * @return bool Whether a place was given back.
	 */
	public static function release( $key, $window, $amount = 1 ) {
		$window = self::window( $window );
		$bucket = self::bucket( $window );
		$name   = self::option_name( $key, $bucket );
		$count  = self::count_of( get_option( $name ) );

		if ( $count < 1 ) {
			return false;
		}

		update_option( $name, self::row( $window, $bucket, max( 0, $count - max( 1, (int) $amount ) ) ), false );

		return true;
	}

	/**
	 * How many claims the current window holds for a key.
```

- [ ] **Step 4: Run the three suites to see them pass**

Run: `for s in sign-ins mail ceiling; do printf '%s: %s, %s ok\n' "$s" "$(php bin/test-$s.php | tail -n 1)" "$(php bin/test-$s.php | grep -c '^ok ')"; done`

Expected:

```text
sign-ins: ALL PASS, 47 ok
mail: ALL PASS, 224 ok
ceiling: ALL PASS, 62 ok
```

- [ ] **Step 5: The battery and the checks**

Expected:

```text
battery: 129 suites, 0 failing, 16729 ok lines
153 classes, 14479 references checked, 87 form handlers scanned - all resolve
78 warnings, no errors.
156 PHP files, 7 blocks, 4 track seeds, 23 fixture and tooling files, the guides and the readme: US English throughout.
```

- [ ] **Step 6: Commit**

```bash
git add includes/class-wpcpm-sign-ins.php includes/class-wpcpm-mail.php includes/class-wpcpm-ceiling.php bin/test-sign-ins.php bin/test-mail.php bin/test-ceiling.php
git commit -m "1.122.30: whether an account has signed in, the fifteen minutes' sentence worded once, and a ceiling's place given back" -m "$COAUTHOR"
```

---

### Task 3: The press

The handler, in the spec's order, and every outcome's sentence. The class is created whole here except for the line on the card, which Task 4 adds; the suite runs the real policy, flash, ceiling, mail, sign-in record, audit log and students sync, so "one email, one stamp, one row, one place" is the plugin's own behavior and not a stand-in's. The send listens for `wp_mail_failed` around itself, so an email the host refuses is told as no send. The two suites that boot `WPCPM_Institutions` gain a stand-in for the class, since `boot()` now starts it.

**Files:**
- Create: `includes/modules/class-wpcpm-institution-resend.php`
- Create: `bin/test-institution-resend.php`
- Modify: `wpcredits-program-manager.php` (after the `class-wpcpm-institution-request.php` line), `uninstall.php` (the same), `includes/modules/class-wpcpm-institutions.php` (`boot()`, after `WPCPM_Institution_Students::init();`)
- Modify: `bin/test-institutions-screen.php`, `bin/test-institutions-accounts.php`

**Interfaces:**
- Consumes: `WPCPM_Institution_Policy::ACT_RESEND_INVITE` (Task 1); `WPCPM_Sign_Ins::has_signed_in()`, `WPCPM_Mail::too_soon_message()`, `WPCPM_Ceiling::release()` (Task 2); `WPCPM_Students_Sync::send_invite( int ): true|WP_Error`, `WPCPM_Mail::may_invite()`, `last_invited()`, `mask_address()`, `WPCPM_Ceiling::claim()`, `count()`, `WPCPM_Institution_Audit::record()`, `EVIDENCE_CACHE`, `WPCPM_Flash::set()`, `WPCPM_Request::posted_id()`, `WPCPM_Institutions_Dashboard::page_url()`; WordPress's `add_action()` and `remove_action()` on `wp_mail_failed`.
- Produces, on `WPCPM_Institution_Resend`:
  - `ACTION` = `'wpcpm_institution_resend_invite'`, `FIELD_USER` = `'user'`, `FLASH` = `'institution_resend'`, `ANCHOR` = `'wpcpm-student-invite'`, `KIND` = `'student-invite-resent'`, `PER_DAY` = `20`, `CEILING` = `'institution-resend:'`.
  - Outcomes: `OUT_RESENT` `'invite-resent'`, `OUT_SENT` `'invite-sent'`, `OUT_TOO_SOON` `'invite-too-soon'`, `OUT_CEILING` `'invite-ceiling'`, `OUT_SIGNED_IN` `'invite-signed-in'`, `OUT_ERROR` `'invite-error'`, `OUT_REFUSED` `'invite-refused'`, `OUT_UNLOGGED` `'invite-unlogged'`.
  - `init(): void` hooks `admin_post_wpcpm_institution_resend_invite` (and no `nopriv` hook).
  - `messages( string $address = '' ): array<string, array{0: string, 1: string}>`: outcome to tone (`success`, `warning`, `info`, `error`) and sentence, the masked address filled in.
  - `handle_resend(): void`, never returns: the flash `array( 'outcome' => <OUT_*>, 'user' => <account>, 'detail' => <masked address or ''> )` on channel `FLASH`, then a redirect to the referer, or the dashboard's address, with `#wpcpm-student-invite`. A send that answers an error, or whose email fires `wp_mail_failed`, gives the day's place back and writes no row (`invite-too-soon` for the fifteen minutes reached in the same instant, `invite-error` otherwise); the listener is removed before the press goes on.
  - `ceiling_key( string $institution ): string`: `'institution-resend:'` and the trimmed record ID.
  - Private, for Task 4: `decision( int ): array{allowed: bool, ground: string, institution: string}` (allowed only when the policy allows `ACT_RESEND_INVITE` and names a well-formed institution record).

- [ ] **Step 1: Write the failing suite and the two stand-ins**

Create `bin/test-institution-resend.php`:

```php
<?php
/**
 * A student's welcome invitation, sent again from their card on the Institution Dashboard.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **The press sends the site's own invitation, once.** `WPCPM_Students_Sync::send_invite()`
 *   runs for real here: one email handed to WordPress, the account's invitation stamped, one
 *   history row naming the institution, the student, who pressed and on which ground, and one
 *   place of the institution's day taken. The notice names the address masked and says the new
 *   link replaces the old one, or, for a first invitation, only that it is on its way.
 * - **Every refusal, in order, sends nothing, stamps nothing, records nothing and spends
 *   nothing.** The nonce (keyed to the account the form names), the policy (a member of another
 *   institution, a student, a stranger, nobody signed in, a locked agreement, an account with no
 *   institution stamp even for an Administrator), an account that is not a student, a student
 *   who has signed in, an account with no address, the fifteen minutes, and the day's twenty.
 *   The day's place is claimed only after all of them, so none of them costs the school one.
 * - **A send that answers an error gives the place back.** The two ways it can, another route
 *   inviting the student in the same instant and the account losing the Student role, are
 *   played between the claim and the send, on a day with places already taken.
 * - **An email the host refuses is no send.** The stand-in for WordPress's notification fires
 *   `wp_mail_failed`: the notice is the shared error sentence, no history row is written and
 *   the day's place is given back, while the stamp the send wrote stays.
 * - **The day's twenty are counted per institution**, under a key that keeps the record ID's
 *   case, and an Administrator pressing through the switcher counts against the institution.
 * - **Every outcome's sentence**, one for each outcome and none for any other.
 *
 * Run from the plugin root:  php bin/test-institution-resend.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']        = array( 'date_format' => 'F j, Y' );
$GLOBALS['umeta']       = array();
$GLOBALS['users']       = array();
$GLOBALS['posts']       = array();
$GLOBALS['pmeta']       = array();
$GLOBALS['uid']         = 0;
$GLOBALS['manage']      = array();
$GLOBALS['members']     = array();
$GLOBALS['settled']     = array();
$GLOBALS['hooks']       = array();
$GLOBALS['invited']     = array();
$GLOBALS['referer']     = array();
$GLOBALS['bad_nonce']   = false;
$GLOBALS['no_insert']   = false;
$GLOBALS['after_claim'] = null;
// Whether the host refuses the next email, which WordPress reports on `wp_mail_failed`.
$GLOBALS['mail_fails'] = false;
// Every write to a ceiling row, so a refusal can be shown to have claimed nothing at all rather
// than to have claimed a place and given it back.
$GLOBALS['ceiling_writes'] = 0;

class WP_Error {
	private $code, $message, $data;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array() ) {
		$this->ID = $id; $this->display_name = $name; $this->user_email = $email; $this->roles = $roles;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_title = '', $post_content = '', $post_type = '', $post_status = 'private', $post_author = 0, $post_date_gmt = '';
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( str_replace( array( "\r", "\n" ), '', strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function is_email( $e ) { return (bool) filter_var( (string) $e, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $v ) { return $v; }
function wp_slash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function add_action( $h, $c = null, $p = 10, $n = 1 ) { $GLOBALS['hooks'][] = array( $h, $c ); }
function remove_action( $h, $c = null, $p = 10 ) {
	foreach ( $GLOBALS['hooks'] as $i => $hook ) {
		if ( $h === $hook[0] && $c === $hook[1] ) {
			unset( $GLOBALS['hooks'][ $i ] );
			$GLOBALS['hooks'] = array_values( $GLOBALS['hooks'] );
			return true;
		}
	}
	return false;
}
function do_action( $h, ...$args ) {
	foreach ( $GLOBALS['hooks'] as $hook ) {
		if ( $h === $hook[0] && is_callable( $hook[1] ) ) { call_user_func_array( $hook[1], $args ); }
	}
}
function register_post_type() {}
function wp_date( $f, $t = null ) { return gmdate( (string) $f, null === $t ? time() : (int) $t ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }

/**
 * Something another request does between this press's claim and its send, played once, when the
 * ceiling's row is written. It is how the suite reaches the two errors a send can still answer
 * after every check before it has passed.
 */
function after_claim( $k ) {
	if ( 0 === strpos( (string) $k, 'wpcpm_ceiling_' ) ) {
		++$GLOBALS['ceiling_writes'];
	}
	if ( 0 === strpos( (string) $k, 'wpcpm_ceiling_' ) && is_callable( $GLOBALS['after_claim'] ) ) {
		$run                    = $GLOBALS['after_claim'];
		$GLOBALS['after_claim'] = null;
		$run();
	}
}
function add_option( $k, $v, $dep = '', $a = 'yes' ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; }
	$GLOBALS['opts'][ $k ] = $v;
	after_claim( $k );
	return true;
}
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; after_claim( $k ); return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_user_by( $f, $v ) { return 'id' === $f ? ( $GLOBALS['users'][ (int) $v ] ?? false ) : false; }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
require_once __DIR__ . '/stubs/caps.php';
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function wp_get_referer() { return 'https://example.test/institution-dashboard/?wpcpm_institution_student=10'; }
function wp_safe_redirect( $to ) { throw new Exception( 'redirect:' . $to ); }
/** Records the action the token was checked against, and dies as core does on a bad token. */
function check_admin_referer( $a = -1, $q = '_wpnonce' ) {
	$GLOBALS['referer'][] = $a;
	if ( $GLOBALS['bad_nonce'] ) { throw new Exception( 'wp_die:nonce' ); }
	return 1;
}
/** The field core prints, with the action left readable so the keying can be asserted. */
function wp_nonce_field( $a = -1, $n = '_wpnonce', $r = true, $e = true ) {
	echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $a ) . '" />';
}
/**
 * The email WordPress would send: recorded by account, as core's own function is called. When the
 * suite says the host refuses it, `wp_mail()` reports so on `wp_mail_failed`, as core's does, and
 * the function answers nothing either way, as core's does.
 */
function wp_new_user_notification( $id, $dep = null, $notify = '' ) {
	$GLOBALS['invited'][] = array( (int) $id, (string) $notify );
	if ( $GLOBALS['mail_fails'] ) {
		do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', 'Could not instantiate mail function.' ) );
	}
}
function wp_insert_post( $a, $error = false ) {
	static $next = 500;
	if ( $GLOBALS['no_insert'] ) { return new WP_Error( 'db_insert_error', 'Could not insert post into the database.' ); }
	$post                          = new WP_Post();
	$post->ID                      = ++$next;
	$post->post_type               = $a['post_type'] ?? 'post';
	$post->post_status             = $a['post_status'] ?? 'private';
	$post->post_author             = (int) ( $a['post_author'] ?? 0 );
	$post->post_content            = $a['post_content'] ?? '';
	$post->post_date_gmt           = gmdate( 'Y-m-d H:i:s', 1700000000 + $post->ID );
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = array( $v ); return true; }
function get_post_meta( $id, $k = '', $single = false ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? array();
	return $single ? ( $rows ? $rows[0] : '' ) : $rows;
}
function get_post_time( $f, $gmt = false, $post = null ) { return strtotime( $post->post_date_gmt . ' UTC' ); }
function get_posts( $a = array() ) {
	$out = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( ( $a['post_type'] ?? '' ) !== $post->post_type ) { continue; }
		$clause = $a['meta_query'][0] ?? null;
		if ( $clause ) {
			$value = $GLOBALS['pmeta'][ $post->ID ][ $clause['key'] ][0] ?? null;
			if ( null === $value || 0 !== strcasecmp( (string) $value, (string) $clause['value'] ) ) { continue; }
		}
		$out[] = $post;
	}
	return array_reverse( $out );
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-sign-ins.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-students-sync.php';

/* ---- the pieces around the press, each to its contract -------------------- */

/** The one record-ID shape check the plugin has. */
class WPCPM_Mentors_Sync {
	const RECORD_ID_PATTERN = '/^rec[A-Za-z0-9]{14}$/';
	public static function is_record_id( $v ) { return is_scalar( $v ) && (bool) preg_match( self::RECORD_ID_PATTERN, trim( (string) $v ) ); }
}
/** The institutions an account acts for right now. */
class WPCPM_Institution_Members {
	public static function memberships_of( $user ) {
		$id = is_object( $user ) ? (int) $user->ID : (int) $user;
		return $GLOBALS['members'][ $id ] ?? array();
	}
}
/** Whether an institution's agreement gate is open. */
class WPCPM_Institution_Agreement {
	public static function is_settled( $id ) { return in_array( $id, $GLOBALS['settled'], true ); }
}
/** Where a press goes back to when the browser sent no referer. */
class WPCPM_Institutions_Dashboard {
	public static function page_url() { return 'https://example.test/institution-dashboard/'; }
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-audit.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-policy.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-resend.php';

$fail  = 0;
$total = 0;

/**
 * Assert and report.
 *
 * @param string $label What is being checked.
 * @param mixed  $got   Actual.
 * @param mixed  $want  Expected.
 */
function ck( $label, $got, $want ) {
	global $fail, $total;
	++$total;
	if ( $got === $want ) {
		echo 'ok   ' . $label . "\n";
		return;
	}
	++$fail;
	echo 'FAIL ' . $label . "\n";
	echo '       expected: ' . var_export( $want, true ) . "\n";
	echo '       actual:   ' . var_export( $got, true ) . "\n";
}
function has( $haystack, $needle ) { return false !== strpos( (string) $haystack, (string) $needle ); }
/** A well-formed record ID of uppercase letters and digits. */
function rid( $name ) { return 'rec' . str_pad( $name, 14, '0' ); }

$R     = 'WPCPM_Institution_Resend';
$A     = rid( 'INSTA' );
$B     = rid( 'INSTB' );
$NW    = time();
$SINCE = gmmktime( 8, 0, 0, 10, 15, 2026 );

/**
 * One world: institutions A and B, both settled; a member of each, an Administrator; and the
 * students of A and B, each in the state one check below needs.
 */
function reset_world() {
	global $A, $B, $NW, $SINCE;

	// The day the site began recording sign-ins, which the card's line names.
	$GLOBALS['opts']        = array(
		'date_format'             => 'F j, Y',
		WPCPM_Sign_Ins::OPT_SINCE => $SINCE,
	);
	$GLOBALS['umeta']       = array();
	$GLOBALS['posts']       = array();
	$GLOBALS['pmeta']       = array();
	$GLOBALS['invited']     = array();
	$GLOBALS['referer']     = array();
	$GLOBALS['bad_nonce']   = false;
	$GLOBALS['no_insert']   = false;
	$GLOBALS['after_claim'] = null;
	$GLOBALS['mail_fails']  = false;
	$GLOBALS['manage']      = array( 30 );
	$GLOBALS['settled']     = array( $A, $B );

	$student = array( WPCPM_Roles::ROLE_STUDENT );

	$GLOBALS['users'] = array(
		10 => new WP_User( 10, 'Ana Lopez', 'ana@example.test', $student ),
		11 => new WP_User( 11, 'Ben Okafor', 'ben@example.test', $student ),
		12 => new WP_User( 12, 'Cleo Silva', 'cleo@example.test', $student ),
		13 => new WP_User( 13, 'Dara Novak', 'dara@example.test', $student ),
		14 => new WP_User( 14, 'Eli Moreau', 'eli@example.test', array( WPCPM_Roles::ROLE_MENTOR ) ),
		15 => new WP_User( 15, 'Fay Brandt', '', $student ),
		16 => new WP_User( 16, 'Gus Ferraro', 'gus@example.test', $student ),
		20 => new WP_User( 20, 'Ines Ruiz', 'ines@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) ),
		21 => new WP_User( 21, 'Bruno Bianchi', 'bruno@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) ),
		30 => new WP_User( 30, 'An Administrator', 'admin@example.test', array( 'administrator' ) ),
		40 => new WP_User( 40, 'Nobody Here', 'nobody@example.test', array( 'subscriber' ) ),
	);

	$GLOBALS['members'] = array(
		20 => array( $A ),
		21 => array( $B ),
	);

	$stamp = WPCPM_Students_Sync::META_INSTITUTION;
	$sign  = WPCPM_Sign_Ins::META_SIGN_IN;
	$sent  = WPCPM_Mail::STAMPS[ WPCPM_Roles::ROLE_STUDENT ];

	// 10 invited nine days ago, no sign-in on record; 11 signed in; 12 never invited; 13 at B; 14
	// at A but a mentor now; 15 with no address; 16 a student whose account carries no stamp.
	$GLOBALS['umeta'] = array(
		10 => array( $stamp => $A, $sent => $NW - 9 * DAY_IN_SECONDS ),
		11 => array( $stamp => $A, $sent => $NW - 9 * DAY_IN_SECONDS, $sign => $NW - DAY_IN_SECONDS ),
		12 => array( $stamp => $A ),
		13 => array( $stamp => $B, $sent => $NW - 9 * DAY_IN_SECONDS ),
		14 => array( $stamp => $A ),
		15 => array( $stamp => $A ),
		16 => array( $sent => $NW - 9 * DAY_IN_SECONDS ),
	);
}

/**
 * Press the button as one user, for one student's account, and say how the request ended.
 *
 * @param int $viewer  Who presses.
 * @param int $student The account the form names.
 * @return string The redirect or the death, as the stand-ins report them.
 */
function press( $viewer, $student ) {
	$GLOBALS['uid']     = (int) $viewer;
	$GLOBALS['invited'] = array();
	$GLOBALS['referer'] = array();
	unset( $GLOBALS['umeta'][ (int) $viewer ]['wpcpm_flash'] );
	$_POST = array(
		'action' => WPCPM_Institution_Resend::ACTION,
		'user'   => (string) $student,
	);

	try {
		WPCPM_Institution_Resend::handle_resend();
	} catch ( Exception $e ) {
		return $e->getMessage();
	}

	return '';
}

/** The flash the last press left for this user, or null. */
function flash_of( $viewer ) { return $GLOBALS['umeta'][ (int) $viewer ]['wpcpm_flash'][ WPCPM_Institution_Resend::FLASH ] ?? null; }
function outcome_of( $viewer ) { $f = flash_of( $viewer ); return is_array( $f ) ? $f['outcome'] : ''; }
/** How many of the institution's places today are taken. */
function spent( $institution ) { return WPCPM_Ceiling::count( WPCPM_Institution_Resend::ceiling_key( $institution ), DAY_IN_SECONDS ); }
function rows( $institution ) { return WPCPM_Institution_Audit::entries_for( $institution, 0 ); }

/**
 * A reader no card has been drawn for yet.
 *
 * `WPCPM_Flash::take()` keeps its first answer for the rest of a request, so a reader who has
 * drawn a card once would read their empty channel again; every render that should print a
 * notice is made by a new account of the same standing.
 *
 * @param string $institution The institution they are a member of, or '' for an Administrator.
 * @return int
 */
function fresh_reader( $institution = '' ) {
	static $next = 100;
	$id                      = ++$next;
	$GLOBALS['users'][ $id ] = new WP_User( $id, 'Reader ' . $id, 'reader' . $id . '@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );
	if ( '' === $institution ) {
		$GLOBALS['manage'][] = $id;
	} else {
		$GLOBALS['members'][ $id ] = array( $institution );
	}
	return $id;
}

/* ---- 1. the hook ---------------------------------------------------------- */

echo "=== The press is an admin-post action of its own ===\n";

reset_world();
WPCPM_Institution_Resend::init();

ck( 'init() hooks the handler on its own admin-post action', $GLOBALS['hooks'], array( array( 'admin_post_wpcpm_institution_resend_invite', array( $R, 'handle_resend' ) ) ) );
ck( 'and nothing for a signed-out visitor', in_array( 'admin_post_nopriv_wpcpm_institution_resend_invite', array_column( $GLOBALS['hooks'], 0 ), true ), false );

/* ---- 2. a press that sends ------------------------------------------------ */

echo "\n=== A member resends the invitation of a student with no sign-in on record ===\n";

reset_world();
$before = WPCPM_Mail::last_invited( 10 );
$ended  = press( 20, 10 );

ck( 'the token is checked against the account the form names', $GLOBALS['referer'], array( 'wpcpm_institution_resend_invite_10' ) );
ck( 'one email is handed to WordPress, the site\'s own invitation to that one account', $GLOBALS['invited'], array( array( 10, 'user' ) ) );
ck( 'the invitation is stamped now', WPCPM_Mail::last_invited( 10 ) >= $NW && WPCPM_Mail::last_invited( 10 ) > $before, true );
ck( 'the notice says it is on its way and replaces the earlier link', outcome_of( 20 ), 'invite-resent' );
ck( 'naming the address masked, and the student it is about', array( flash_of( 20 )['detail'], flash_of( 20 )['user'] ), array( 'a***@example.test', 10 ) );
ck( 'and the press goes back to the card, at the line', $ended, 'redirect:https://example.test/institution-dashboard/?wpcpm_institution_student=10#wpcpm-student-invite' );
ck( 'one place of the institution\'s day is taken', spent( $A ), 1 );

$row = rows( $A );

ck( 'one history row, under the institution', count( $row ), 1 );
ck( 'saying what happened, to whom, by whom and on what ground',
	array( $row[0]['kind'], $row[0]['institution'], $row[0]['subject'], $row[0]['actor'], $row[0]['ground'], $row[0]['evidence'] ),
	array( 'student-invite-resent', $A, '10', 20, 'member', 'cache' ) );
ck( 'and that it was not the account\'s first', $row[0]['data'], array( 'student' => 10, 'first' => false ) );
ck( 'no other institution gets a row', rows( $B ), array() );

// A second press inside the fifteen minutes: the new link would cancel the one just mailed.
$ended = press( 20, 10 );

ck( 'a second press within fifteen minutes sends nothing', $GLOBALS['invited'], array() );
ck( 'and says why', outcome_of( 20 ), 'invite-too-soon' );
ck( 'and costs no second place', spent( $A ), 1 );
ck( 'and writes no second row', count( rows( $A ) ), 1 );

echo "\n=== A first invitation ===\n";

reset_world();
press( 20, 12 );

ck( 'a student never invited is sent one', $GLOBALS['invited'], array( array( 12, 'user' ) ) );
ck( 'and the notice does not speak of an earlier email', array( outcome_of( 20 ), flash_of( 20 )['detail'] ), array( 'invite-sent', 'c***@example.test' ) );
ck( 'the row says it was the first', rows( $A )[0]['data'], array( 'student' => 12, 'first' => true ) );

/* ---- 3. every refusal, in order ------------------------------------------- */

echo "\n=== Every refusal sends nothing, stamps nothing, records nothing and spends nothing ===\n";

/**
 * Press as one user and report everything a refusal must leave as it was.
 *
 * @param int $viewer  Who presses.
 * @param int $student The account the form names.
 * @return array{outcome: string, sent: array, stamp: bool, rows: int, spent: int, claimed: int, ended: string}
 */
function refused( $viewer, $student ) {
	global $A, $B;
	$stamp                     = WPCPM_Mail::last_invited( $student );
	$GLOBALS['ceiling_writes'] = 0;
	$ended                     = press( $viewer, $student );
	return array(
		'outcome' => outcome_of( $viewer ),
		'sent'    => $GLOBALS['invited'],
		'stamp'   => WPCPM_Mail::last_invited( $student ) === $stamp,
		'rows'    => count( rows( $A ) ) + count( rows( $B ) ),
		'spent'   => spent( $A ) + spent( $B ),
		'claimed' => $GLOBALS['ceiling_writes'],
		'ended'   => 0 === strpos( $ended, 'redirect:' ) ? 'redirect' : $ended,
	);
}
function nothing( $outcome, $ended = 'redirect' ) {
	return array( 'outcome' => $outcome, 'sent' => array(), 'stamp' => true, 'rows' => 0, 'spent' => 0, 'claimed' => 0, 'ended' => $ended );
}

reset_world();
$GLOBALS['bad_nonce'] = true;
ck( 'a token that does not match dies before anything is read', refused( 20, 10 ), nothing( '', 'wp_die:nonce' ) );
$GLOBALS['bad_nonce'] = false;

reset_world();
ck( 'a member of another institution gets the one refusal', refused( 21, 10 ), nothing( 'invite-refused' ) );
ck( 'an account that is nobody\'s member gets it too', refused( 40, 10 ), nothing( 'invite-refused' ) );
ck( 'and so does the student, for their own invitation', refused( 10, 10 ), nothing( 'invite-refused' ) );
ck( 'and an account that does not exist', refused( 20, 999 ), nothing( 'invite-refused' ) );
ck( 'nobody signed in sends nothing either, and is left no notice', refused( 0, 10 ), nothing( '' ) );
ck( 'an Administrator, for a student whose account names no institution, gets the one refusal', refused( 30, 16 ), nothing( 'invite-refused' ) );

$GLOBALS['settled'] = array( $B );
ck( 'a member whose agreement is not settled gets the one refusal', refused( 20, 10 ), nothing( 'invite-refused' ) );
$GLOBALS['settled'] = array( $A, $B );

ck( 'an account on the roster that is not a student is not sent one', refused( 20, 14 ), nothing( 'invite-error' ) );
ck( 'a student who has signed in is not sent one, and is told so', refused( 20, 11 ), nothing( 'invite-signed-in' ) );
ck( 'a student with no address is not sent one', refused( 20, 15 ), nothing( 'invite-error' ) );

update_user_meta( 10, WPCPM_Mail::STAMPS[ WPCPM_Roles::ROLE_STUDENT ], time() - 5 * MINUTE_IN_SECONDS );
ck( 'a student invited five minutes ago is not sent another', refused( 20, 10 ), nothing( 'invite-too-soon' ) );

// The order, read from the source: the policy before the student, the student before the gap, the
// gap before the day's place, the place before the send, the send before the row.
$source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-resend.php' );
$body   = substr( $source, (int) strpos( $source, 'public static function handle_resend()' ) );
$body   = substr( $body, 0, (int) strpos( $body, 'public static function ceiling_key(' ) );
$at     = array();

foreach ( array( 'check_admin_referer(', 'self::decision(', 'self::student(', 'WPCPM_Sign_Ins::has_signed_in(', 'is_email(', 'WPCPM_Mail::may_invite(', 'WPCPM_Ceiling::claim(', 'WPCPM_Students_Sync::send_invite(', 'self::log(' ) as $call ) {
	$at[ $call ] = strpos( $body, $call );
}

$found = array_filter( $at, 'is_int' );
asort( $found );

ck( 'the handler asks in the order the refusals above are listed', array_keys( $found ), array_keys( $at ) );
ck( 'and reads the account from the posted form, never from the address', array( has( $body, 'WPCPM_Request::posted_id( self::FIELD_USER )' ), has( $body, 'WPCPM_Request::id(' ) ), array( true, false ) );
ck( 'the press and the line ask the policy for ACT_RESEND_INVITE, and for nothing else',
	array( substr_count( $source, 'WPCPM_Institution_Policy::ACT_RESEND_INVITE' ), preg_match_all( '/WPCPM_Institution_Policy::ACT_(?!RESEND_INVITE)/', $source ) ),
	array( 1, 0 ) );

/* ---- 4. the day's twenty -------------------------------------------------- */

echo "\n=== Twenty a day for each institution ===\n";

reset_world();
WPCPM_Ceiling::claim( WPCPM_Institution_Resend::ceiling_key( $A ), WPCPM_Institution_Resend::PER_DAY, DAY_IN_SECONDS, 19 );

press( 20, 10 );
ck( 'the twentieth of the day is sent', array( outcome_of( 20 ), spent( $A ) ), array( 'invite-resent', 20 ) );

$at_ceiling = refused( 20, 12 );
ck( 'the twenty-first is refused, sends nothing and records nothing', array( $at_ceiling['outcome'], $at_ceiling['sent'], $at_ceiling['stamp'] ), array( 'invite-ceiling', array(), true ) );
ck( 'and the day stays at twenty', spent( $A ), 20 );
ck( 'and the one row is the twentieth\'s', count( rows( $A ) ), 1 );

press( 21, 13 );
ck( 'another institution\'s day is its own', array( outcome_of( 21 ), spent( $B ) ), array( 'invite-resent', 1 ) );

$admin = fresh_reader();
press( $admin, 12 );
ck( 'an Administrator through the switcher is held to the institution\'s day too', outcome_of( $admin ), 'invite-ceiling' );

ck( 'the key is the institution\'s record ID as written',
	array( WPCPM_Institution_Resend::ceiling_key( $A ), WPCPM_Institution_Resend::ceiling_key( ' ' . $A . ' ' ) ),
	array( 'institution-resend:' . $A, 'institution-resend:' . $A ) );
ck( 'and keeps its case, so two records that differ only in case are two days',
	WPCPM_Institution_Resend::ceiling_key( $A ) === WPCPM_Institution_Resend::ceiling_key( strtolower( $A ) ), false );
ck( 'the figure is twenty', WPCPM_Institution_Resend::PER_DAY, 20 );

/* ---- 5. a send that answers an error gives the place back ----------------- */

echo "\n=== A send that answers an error gives the day's place back ===\n";

reset_world();

// A day three places in, so the place given back is one place and not the whole day.
WPCPM_Ceiling::claim( WPCPM_Institution_Resend::ceiling_key( $A ), WPCPM_Institution_Resend::PER_DAY, DAY_IN_SECONDS, 3 );

// Another route invites the student in the instant between this press's claim and its send.
$GLOBALS['after_claim'] = function () {
	update_user_meta( 10, WPCPM_Mail::STAMPS[ WPCPM_Roles::ROLE_STUDENT ], time() );
};
press( 20, 10 );

ck( 'a send that finds the student invited a moment ago sends nothing', $GLOBALS['invited'], array() );
ck( 'says the fifteen minutes', outcome_of( 20 ), 'invite-too-soon' );
ck( 'gives the place back', spent( $A ), 3 );
ck( 'and records nothing', rows( $A ), array() );

reset_world();

// The account loses the Student role in the same instant: the send refuses it.
$GLOBALS['after_claim'] = function () {
	$GLOBALS['users'][12]->roles = array( WPCPM_Roles::ROLE_MENTOR );
};
press( 20, 12 );

ck( 'a send the sync refuses sends nothing', $GLOBALS['invited'], array() );
ck( 'says the invitation could not be sent', outcome_of( 20 ), 'invite-error' );
ck( 'gives the place back', spent( $A ), 0 );
ck( 'and records nothing', rows( $A ), array() );

echo "\n=== An email the host refuses is no send ===\n";

reset_world();
WPCPM_Ceiling::claim( WPCPM_Institution_Resend::ceiling_key( $A ), WPCPM_Institution_Resend::PER_DAY, DAY_IN_SECONDS, 3 );
$GLOBALS['mail_fails'] = true;
$before                = WPCPM_Mail::last_invited( 10 );
press( 20, 10 );

ck( 'the email was handed to WordPress, and the host refused it', $GLOBALS['invited'], array( array( 10, 'user' ) ) );
ck( 'the notice is the shared error sentence', outcome_of( 20 ), 'invite-error' );
ck( 'with no address quoted', flash_of( 20 )['detail'], '' );
ck( 'the day\'s place is given back', spent( $A ), 3 );
ck( 'no history row is written', rows( $A ), array() );
ck( 'the stamp the send wrote stays, since its new link replaced the old one', WPCPM_Mail::last_invited( 10 ) > $before, true );
ck( 'and the press listens for a refusal only around its own send', in_array( 'wp_mail_failed', array_column( $GLOBALS['hooks'], 0 ), true ), false );

$GLOBALS['mail_fails'] = false;
press( 20, 10 );
ck( 'so a press straight after is held to the fifteen minutes', array( outcome_of( 20 ), $GLOBALS['invited'], spent( $A ) ), array( 'invite-too-soon', array(), 3 ) );

/* ---- 6. a row that will not insert ---------------------------------------- */

echo "\n=== The invitation went, and its row would not insert ===\n";

reset_world();
$GLOBALS['no_insert'] = true;
press( 20, 10 );

ck( 'the invitation is still sent', $GLOBALS['invited'], array( array( 10, 'user' ) ) );
ck( 'and the notice says the history is missing it, naming the address', array( outcome_of( 20 ), flash_of( 20 )['detail'] ), array( 'invite-unlogged', 'a***@example.test' ) );
ck( 'the place stays taken, since the email went', spent( $A ), 1 );

/* ---- 7. an Administrator through the switcher ----------------------------- */

echo "\n=== An Administrator through the switcher ===\n";

reset_world();
press( 30, 10 );

ck( 'an Administrator may send it', $GLOBALS['invited'], array( array( 10, 'user' ) ) );
ck( 'the row names them, on the manager ground, under the student\'s institution',
	array( rows( $A )[0]['actor'], rows( $A )[0]['ground'], rows( $A )[0]['institution'] ),
	array( 30, 'manager', $A ) );
ck( 'and the send counts against that institution\'s day', spent( $A ), 1 );

$GLOBALS['settled'] = array( $B );
press( 30, 12 );
ck( 'an unsettled agreement does not stop an Administrator', outcome_of( 30 ), 'invite-sent' );
$GLOBALS['settled'] = array( $A, $B );

/* ---- 8. what each outcome says -------------------------------------------- */

echo "\n=== What each outcome says ===\n";

reset_world();

$expected = array(
	'invite-resent'    => array( 'success', 'The invitation is on its way to a***@example.test. The new link replaces the one in the earlier email.' ),
	'invite-sent'      => array( 'success', 'The invitation is on its way to a***@example.test.' ),
	'invite-too-soon'  => array( 'warning', 'This person was sent an invitation less than 15 minutes ago. Another one now would cancel the link in it, so nothing was sent.' ),
	'invite-ceiling'   => array( 'error', 'Your institution has sent 20 invitations today, the most a day allows, so nothing was sent. Try again tomorrow, or write to the program team.' ),
	'invite-signed-in' => array( 'info', 'This student has signed in since the page was drawn, so nothing was sent.' ),
	'invite-error'     => array( 'error', 'The invitation could not be sent.' ),
	'invite-refused'   => array( 'error', 'That record is not on your roster.' ),
	'invite-unlogged'  => array( 'error', 'The invitation is on its way to a***@example.test, but the entry saying who sent it could not be written, so it is not in this institution\'s history. Tell your program contact.' ),
);

ck( 'every outcome has its sentence, and no other outcome has one', WPCPM_Institution_Resend::messages( 'a***@example.test' ), $expected );
ck( 'the fifteen minutes are the sentence every route refuses with', WPCPM_Institution_Resend::messages()['invite-too-soon'][1], WPCPM_Mail::too_soon_message() );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
```

In `bin/test-institutions-screen.php`, replace:

```php
	class WPCPM_Institution_Create {
		public static function init() {
			$GLOBALS['calls'][] = array( 'create_init' );
		}
	}
```

with:

```php
	class WPCPM_Institution_Create {
		public static function init() {
			$GLOBALS['calls'][] = array( 'create_init' );
		}
	}

	/** A student's invitation, sent again from their card: started by `boot()` and nothing more here. */
	class WPCPM_Institution_Resend {
		public static function init() {
			$GLOBALS['calls'][] = array( 'resend_init' );
		}
	}
```

In `bin/test-institutions-screen.php`, replace:

```php
	array( 'notes_init' ),
	array( 'invite_init' ),
) );
```

with:

```php
	array( 'notes_init' ),
	array( 'invite_init' ),
) );
$started = array_map( function ( $c ) { return $c[0]; }, $GLOBALS['calls'] );
ck( 'and starts the resend of a student\'s invitation right after graduate and withdraw, the card\'s other student control',
	array_slice( $started, (int) array_search( 'students_init', $started, true ), 2 ), array( 'students_init', 'resend_init' ) );
```

In `bin/test-institutions-accounts.php`, replace:

```php
class WPCPM_Institution_Students {
	public static function init() {}
}
```

with:

```php
class WPCPM_Institution_Students {
	public static function init() {}
}
class WPCPM_Institution_Resend {
	public static function init() {}
}
```

- [ ] **Step 2: Run the suites to see them fail**

Run: `for s in institution-resend institutions-screen institutions-accounts; do php bin/test-$s.php > "$SCRATCH/red.txt" 2>&1; echo "== $s, exit $?"; grep -E '^(FAIL|PHP Fatal error|Fatal error)' "$SCRATCH/red.txt"; done`

Expected:

```text
== institution-resend, exit 255
PHP Fatal error:  Uncaught Error: Failed opening required '<tree>/includes/modules/class-wpcpm-institution-resend.php' (include_path=...) in <tree>/bin/test-institution-resend.php:237
Fatal error: Uncaught Error: Failed opening required '<tree>/includes/modules/class-wpcpm-institution-resend.php' (include_path=...) in <tree>/bin/test-institution-resend.php:237
== institutions-screen, exit 1
FAIL and starts the resend of a student's invitation right after graduate and withdraw, the card's other student control
== institutions-accounts, exit 0
```

The accounts suite's stand-in only keeps `boot()` from a fatal once the class is started there, so it has nothing to fail yet.

- [ ] **Step 3: Write the class**

Create `includes/modules/class-wpcpm-institution-resend.php`:

```php
<?php
/**
 * The Institution Dashboard - a student's welcome invitation, sent again from their card.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The press that sends a student's welcome invitation again from the Institution Dashboard.
 *
 * **Who.** Every member of the student's own institution, on the member ground, and an
 * Administrator who opened the institution's dashboard through the switcher, on the manager
 * ground: `decide( ACT_RESEND_INVITE )` on the student's account stamp, as reading the card is
 * decided. Everybody else gets the policy's one refusal, which says nothing about whether the
 * student exists. The decision has to name a well-formed institution record as well, because
 * the day's allowance is counted under it and the history row is filed under it: the manager
 * ground carries an account with no stamp, and a send that could be counted and recorded
 * nowhere is a send this class does not make.
 *
 * **For whom.** An account that holds the Student role and has no recorded sign-in
 * (`WPCPM_Sign_Ins::has_signed_in()`). A student who has signed in once has WordPress's own
 * password reset, so the card draws no control for them, and a press from a page drawn before
 * they signed in is refused.
 *
 * **The two limits.** `WPCPM_Mail::may_invite()`'s fifteen minutes per student, because each
 * invitation cancels the link in the one before it, and `PER_DAY` sends a day for each
 * institution, through `WPCPM_Ceiling`. The day's place is claimed only once every refusal
 * before it has passed, and given back when the send answers an error or the host refuses its
 * email, so a press that sends nothing costs the school nothing.
 *
 * **The send.** `WPCPM_Students_Sync::send_invite()`, the one Administrators press in wp-admin:
 * the site's own welcome invitation with a new link, its time stamped and its row in the email
 * log. Nobody else is emailed. An email the host refuses (WordPress's `wp_mail_failed`, listened
 * for around this one send) is no send: the place is given back and no history row is written,
 * while the stamp the send wrote stays, since its new link has replaced the old one either way.
 *
 * **The record.** One history row per send (`WPCPM_Institution_Audit::record()`): the
 * institution, the student's account, who pressed and on which ground.
 *
 * **The line.** On the student's card, under the name, for a student with no sign-in on record:
 * the day the site began recording sign-ins, when the last invitation went, and one button. The
 * sign-in record is read for one question here, whether a sign-in is on record for the student,
 * and no date of a sign-in is shown.
 */
final class WPCPM_Institution_Resend {

	/** The one press. Nonce keyed to the student's account. */
	const ACTION = 'wpcpm_institution_resend_invite';

	/** The posted field naming the student's account. Nothing else in the form is read. */
	const FIELD_USER = 'user';

	/** Flash channel. Carries the account it is about, so one card prints it and no other. */
	const FLASH = 'institution_resend';

	/** The line's anchor, so a press lands back on the line it came from. */
	const ANCHOR = 'wpcpm-student-invite';

	/** Audit kind: a student's welcome invitation, sent from the Institution Dashboard. */
	const KIND = 'student-invite-resent';

	/** How many invitations one institution may send in a day. */
	const PER_DAY = 20;

	/** The day's allowance is counted under this prefix and the institution's record ID. */
	const CEILING = 'institution-resend:';

	/** Outcome: sent, replacing the link of an earlier invitation. */
	const OUT_RESENT = 'invite-resent';

	/** Outcome: sent, the first invitation the account was ever sent. */
	const OUT_SENT = 'invite-sent';

	/** Outcome: the last invitation went less than fifteen minutes ago, so nothing was sent. */
	const OUT_TOO_SOON = 'invite-too-soon';

	/** Outcome: the institution has sent `PER_DAY` today, so nothing was sent. */
	const OUT_CEILING = 'invite-ceiling';

	/** Outcome: the student signed in after the card was drawn, so nothing was sent. */
	const OUT_SIGNED_IN = 'invite-signed-in';

	/** Outcome: the account cannot be sent an invitation, the send answered an error, or the host refused its email. */
	const OUT_ERROR = 'invite-error';

	/** Outcome: the policy refused the press. */
	const OUT_REFUSED = 'invite-refused';

	/** Outcome: sent, and the history row would not insert. */
	const OUT_UNLOGGED = 'invite-unlogged';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_resend' ) );
	}

	/*
	 * What the card says
	 * --------------------------------------------------------------------
	 */

	/**
	 * What each outcome says, with the student's masked address where a sentence names it.
	 *
	 * Every refusal says that nothing was sent, because the reader has just pressed a button and
	 * cannot see the server. The fifteen minutes are `WPCPM_Mail::too_soon_message()`, the
	 * sentence every route's refusal gives; a failed send is the sentence the Resend invite in
	 * wp-admin prints.
	 *
	 * @param string $address The student's address, masked by `WPCPM_Mail::mask_address()`.
	 * @return array<string, array{0: string, 1: string}> Outcome to its tone and sentence.
	 */
	public static function messages( $address = '' ) {
		$address = (string) $address;

		return array(
			self::OUT_RESENT    => array(
				'success',
				sprintf(
					/* translators: %s: the student's email address, masked, such as a***@example.org. */
					__( 'The invitation is on its way to %s. The new link replaces the one in the earlier email.', 'wpcredits-program-manager' ),
					$address
				),
			),
			self::OUT_SENT      => array(
				'success',
				sprintf(
					/* translators: %s: the student's email address, masked, such as a***@example.org. */
					__( 'The invitation is on its way to %s.', 'wpcredits-program-manager' ),
					$address
				),
			),
			self::OUT_TOO_SOON  => array( 'warning', WPCPM_Mail::too_soon_message() ),
			self::OUT_CEILING   => array(
				'error',
				sprintf(
					/* translators: %d: how many invitations an institution may send in a day. */
					_n(
						'Your institution has sent %d invitation today, the most a day allows, so nothing was sent. Try again tomorrow, or write to the program team.',
						'Your institution has sent %d invitations today, the most a day allows, so nothing was sent. Try again tomorrow, or write to the program team.',
						self::PER_DAY,
						'wpcredits-program-manager'
					),
					self::PER_DAY
				),
			),
			self::OUT_SIGNED_IN => array( 'info', __( 'This student has signed in since the page was drawn, so nothing was sent.', 'wpcredits-program-manager' ) ),
			self::OUT_ERROR     => array( 'error', __( 'The invitation could not be sent.', 'wpcredits-program-manager' ) ),
			self::OUT_REFUSED   => array( 'error', WPCPM_Institution_Policy::refusal()->get_error_message() ),
			self::OUT_UNLOGGED  => array(
				'error',
				sprintf(
					/* translators: %s: the student's email address, masked, such as a***@example.org. */
					__( 'The invitation is on its way to %s, but the entry saying who sent it could not be written, so it is not in this institution\'s history. Tell your program contact.', 'wpcredits-program-manager' ),
					$address
				),
			),
		);
	}

	/*
	 * The press
	 * --------------------------------------------------------------------
	 */

	/**
	 * Send one student's welcome invitation again, and say what came of it on their card.
	 *
	 * The order is the rule. The nonce first, keyed to the account the form names. Then the
	 * policy. Then the student, asked again here rather than taken from the page: an account
	 * holding the Student role, with no recorded sign-in and an address to send to. Then the
	 * fifteen minutes, then the day's place, claimed only now, so no refusal before it spends
	 * one. Then the send, which gives the place back when it answers an error or its email is
	 * refused, and the history row. Every step ends in `bounce()`, which does not return.
	 */
	public static function handle_resend() {
		// Read to key the token and for nothing else: whose student this is, and whether the
		// invitation may go, are decided below.
		$user_id = WPCPM_Request::posted_id( self::FIELD_USER );

		check_admin_referer( self::ACTION . '_' . $user_id );

		$decision = self::decision( $user_id );

		if ( empty( $decision['allowed'] ) ) {
			self::bounce( self::OUT_REFUSED, $user_id );
		}

		$student = self::student( $user_id );

		if ( ! $student instanceof WP_User ) {
			self::bounce( self::OUT_ERROR, $user_id );
		}

		if ( WPCPM_Sign_Ins::has_signed_in( $user_id ) ) {
			self::bounce( self::OUT_SIGNED_IN, $user_id );
		}

		// An account with no address it could be sent to is refused before the day's place is
		// claimed, rather than told an invitation is on its way to nobody.
		if ( ! is_email( (string) $student->user_email ) ) {
			self::bounce( self::OUT_ERROR, $user_id );
		}

		if ( is_wp_error( WPCPM_Mail::may_invite( $user_id ) ) ) {
			self::bounce( self::OUT_TOO_SOON, $user_id );
		}

		$key = self::ceiling_key( $decision['institution'] );

		if ( ! WPCPM_Ceiling::claim( $key, self::PER_DAY, DAY_IN_SECONDS ) ) {
			self::bounce( self::OUT_CEILING, $user_id );
		}

		// Read before the send stamps the new one: it decides whether the notice speaks of an
		// earlier email whose link the new one replaces.
		$earlier = WPCPM_Mail::last_invited( $user_id ) > 0;

		// `send_invite()` cannot tell a refused email from a sent one: WordPress drops what
		// `wp_mail()` answered. The refusal is heard here, for this one send and no other.
		$refused = false;
		$listen  = static function () use ( &$refused ) {
			$refused = true;
		};

		add_action( 'wp_mail_failed', $listen );
		$result = WPCPM_Students_Sync::send_invite( $user_id );
		remove_action( 'wp_mail_failed', $listen );

		if ( is_wp_error( $result ) || $refused ) {
			WPCPM_Ceiling::release( $key, DAY_IN_SECONDS );

			self::bounce( is_wp_error( $result ) && 'wpcpm_invite_too_soon' === $result->get_error_code() ? self::OUT_TOO_SOON : self::OUT_ERROR, $user_id );
		}

		$address = WPCPM_Mail::mask_address( (string) $student->user_email );

		// The invitation has gone: a history row that would not insert is said out loud rather
		// than swallowed, since the row is how an Administrator learns who sent it.
		if ( is_wp_error( self::log( $user_id, $decision, $earlier ) ) ) {
			self::bounce( self::OUT_UNLOGGED, $user_id, $address );
		}

		self::bounce( $earlier ? self::OUT_RESENT : self::OUT_SENT, $user_id, $address );
	}

	/**
	 * The key the day's allowance is counted under: one per institution.
	 *
	 * Built here rather than through `WPCPM_Ceiling::key()`, which lowercases what it is given: a
	 * record ID is case-sensitive, and two institutions whose IDs differ only in case are two
	 * allowances. Nothing readable reaches the options table either way, because the ceiling
	 * names its row after an md5 of the key and the window.
	 *
	 * @param string $institution Institutions record ID.
	 * @return string
	 */
	public static function ceiling_key( $institution ) {
		return self::CEILING . trim( (string) $institution );
	}

	/**
	 * The decision for this student's account and this reader, and the institution it names.
	 *
	 * One question for the line and the press, so the card never draws a button the press
	 * refuses at its policy step. Allowed only when the policy allows the action and names a
	 * well-formed institution record.
	 *
	 * @param int $user_id The student's account.
	 * @return array{allowed: bool, ground: string, institution: string}
	 */
	private static function decision( $user_id ) {
		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_RESEND_INVITE,
			WPCPM_Institution_Policy::subject_student_account( (int) $user_id )
		);

		$institution = isset( $decision['institution'] ) ? trim( (string) $decision['institution'] ) : '';

		return array(
			'allowed'     => ! empty( $decision['allowed'] ) && WPCPM_Mentors_Sync::is_record_id( $institution ),
			'ground'      => isset( $decision['ground'] ) ? (string) $decision['ground'] : '',
			'institution' => $institution,
		);
	}

	/**
	 * The account behind the ID when it is a student's: it exists and holds the Student role,
	 * the one `WPCPM_Students_Sync::send_invite()` sends to.
	 *
	 * @param int $user_id The account.
	 * @return WP_User|null
	 */
	private static function student( $user_id ) {
		$user = get_user_by( 'id', (int) $user_id );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return null;
		}

		return WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_STUDENT ) ? $user : null;
	}

	/**
	 * One history row for one send.
	 *
	 * The evidence is `cache`: the decision was made on the account's institution stamp, which
	 * the students sync writes.
	 *
	 * @param int   $user_id  The student's account.
	 * @param array $decision What `decision()` answered.
	 * @param bool  $earlier  Whether the account had been sent an invitation before.
	 * @return int|WP_Error The row's post ID, or why there is none.
	 */
	private static function log( $user_id, array $decision, $earlier ) {
		return WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::KIND,
				'institution' => $decision['institution'],
				'subject'     => (string) (int) $user_id,
				'actor'       => get_current_user_id(),
				'ground'      => $decision['ground'],
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_CACHE,
				'message'     => __( 'Welcome invitation sent from the Institution Dashboard.', 'wpcredits-program-manager' ),
				'data'        => array(
					'student' => (int) $user_id,
					'first'   => ! $earlier,
				),
			)
		);
	}

	/**
	 * Record the outcome and go back to the card the press came from, at the line's anchor.
	 *
	 * **This does not return.** The account travels with the outcome, so the sentence is
	 * printed on that student's card and on no other.
	 *
	 * @param string $outcome One of the OUT_* constants.
	 * @param int    $user_id The student's account the press named.
	 * @param string $detail  The masked address a sentence names, or ''.
	 */
	private static function bounce( $outcome, $user_id, $detail = '' ) {
		WPCPM_Flash::set(
			self::FLASH,
			array(
				'outcome' => (string) $outcome,
				'user'    => (int) $user_id,
				'detail'  => (string) $detail,
			)
		);

		$back = wp_get_referer();

		if ( ! $back ) {
			$page = WPCPM_Institutions_Dashboard::page_url();
			$back = '' !== $page ? $page : home_url( '/' );
		}

		wp_safe_redirect( $back . '#' . self::ANCHOR );
		exit;
	}
}
```

- [ ] **Step 4: Load it, uninstall it and start it**

In `wpcredits-program-manager.php`, replace:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-request.php';
```

with:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-resend.php';
```

In `uninstall.php`, replace:

```php
require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-institution-request.php';
```

with:

```php
require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-institution-request.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-wpcpm-institution-resend.php';
```

In `includes/modules/class-wpcpm-institutions.php`, replace:

```php
		WPCPM_Institution_Students::init();
		WPCPM_Institution_Export::init();
```

with:

```php
		WPCPM_Institution_Students::init();
		WPCPM_Institution_Resend::init();
		WPCPM_Institution_Export::init();
```

- [ ] **Step 5: Run the suites to see them pass**

Run: `for s in institution-resend institutions-screen institutions-accounts institution-policy roles; do printf '%s: %s, %s ok\n' "$s" "$(php bin/test-$s.php | tail -n 1)" "$(php bin/test-$s.php | grep -c '^ok ')"; done; php bin/test-institution-policy.php | grep 'Resend::handle_resend() decides'`

Expected:

```text
institution-resend: ALL PASS (69 checks), 69 ok
institutions-screen: ALL PASS, 379 ok
institutions-accounts: ALL PASS (153 checks), 153 ok
institution-policy: ALL PASS, 255 ok
roles: ALL PASS, 52 ok
ok   WPCPM_Institution_Resend::handle_resend() decides before it touches Airtable (no Airtable use, directly or one call away)
```

`bin/test-roles.php` holds the loader and `uninstall.php` to the same class list, and the policy suite's source walk now scans the new handler among the institution handlers, which reaches no Airtable at all.

- [ ] **Step 6: The battery and the checks**

Expected:

```text
battery: 130 suites, 0 failing, 16800 ok lines
154 classes, 14542 references checked, 88 form handlers scanned - all resolve
78 warnings, no errors.
157 PHP files, 7 blocks, 4 track seeds, 23 fixture and tooling files, the guides and the readme: US English throughout.
```

- [ ] **Step 7: Commit**

```bash
git add includes/modules/class-wpcpm-institution-resend.php bin/test-institution-resend.php wpcredits-program-manager.php uninstall.php includes/modules/class-wpcpm-institutions.php bin/test-institutions-screen.php bin/test-institutions-accounts.php
git commit -m "1.122.30: a member of an institution sends a student's welcome invitation again, twenty a day, each send in the institution's history" -m "$COAUTHOR"
```

---

### Task 4: The line on the card

Under the student's name: whether a sign-in is on record since the site began recording them, when the last invitation went, and the one-press button, or nothing at all. The card draws it the way it draws its other two controls, guarded and decided elsewhere; the stylesheet keeps it close under the name.

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-resend.php` (the line's section, before "What the card says")
- Modify: `includes/modules/class-wpcpm-institution-student-view.php` (the class docblock, `render()`, and a method before `status()`)
- Modify: `assets/css/institution.css` (the shared message rule; rules after `.wpcpm-institution__student-name`)
- Test: `bin/test-institution-resend.php`, `bin/test-student-view-controls.php`

**Interfaces:**
- Consumes: everything Task 3 produced; `WPCPM_Sign_Ins::since()` (the record's first day, 0 before it began) and `has_signed_in()`, `WPCPM_Flash::take()`, `WPCPM_Roles::user_has_role()`, `wp_date()`, `get_option( 'date_format' )`, `wp_nonce_field()`.
- Produces: `WPCPM_Institution_Resend::render( WP_User $student ): void`, printing nothing, or `<div class="wpcpm-institution__invite" id="wpcpm-student-invite">` holding the notice (`<p class="wpcpm-institution__invite-message is-<tone>" role="status">`), the sentence (`<p class="wpcpm-institution__invite-note">`, "No sign-in on record since <the record's first day>. Invitation last sent <date>." or "... No invitation sent yet.") and the form (`<form class="wpcpm-institution__invite-form" method="post" action=".../admin-post.php" data-wpcpm-once data-wpcpm-busy="Sending...">` with the nonce, `action` and `user`, and `<button type="submit" class="wpcpm-button">`). `WPCPM_Institution_Student_View::render()` calls it right after the identity block.

- [ ] **Step 1: Write the failing checks**

In `bin/test-institution-resend.php`, replace:

```php
 * - **Every outcome's sentence**, one for each outcome and none for any other.
```

with:

```php
 * - **Every outcome's sentence**, one for each outcome and none for any other, printed on the
 *   card it is about and on no other, in its tone, escaped.
 * - **The card's line**: nothing for a student with a sign-in on record, for an account that is
 *   not a student, for a reader the policy refuses, or while the site keeps no sign-in record;
 *   the two sentences, naming the record's first day and the last invitation in the site's date
 *   format; the two button labels; a one-press form whose nonce is keyed to the student on
 *   screen; and the stylesheet's rules for all of it.
```

In `bin/test-institution-resend.php`, replace:

```php
printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
```

with:

```php
/** Draw one student's line as one reader. */
function draw( $viewer, $student ) {
	$GLOBALS['uid'] = (int) $viewer;
	ob_start();
	WPCPM_Institution_Resend::render( $GLOBALS['users'][ (int) $student ] );
	return (string) ob_get_clean();
}

/* ---- 9. the line on the card ---------------------------------------------- */

echo "\n=== The line on the card ===\n";

reset_world();

$line  = draw( 20, 10 );
$date  = gmdate( 'F j, Y', $NW - 9 * DAY_IN_SECONDS );
$since = gmdate( 'F j, Y', $SINCE );

ck( 'a student invited before reads the day the record began and when the last invitation went, in the site\'s date format',
	has( $line, '<p class="wpcpm-institution__invite-note">No sign-in on record since ' . $since . '. Invitation last sent ' . $date . '.</p>' ), true );
ck( 'the record\'s day is the sign-in record\'s own start', $since, 'October 15, 2026' );
ck( 'with a button to send it again', has( $line, '<button type="submit" class="wpcpm-button">Resend invitation</button>' ), true );
ck( 'in a one-press form that says it is sending, posting to admin-post.php',
	has( $line, '<form class="wpcpm-institution__invite-form" method="post" action="https://example.test/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Sending...">' ), true );
ck( 'carrying the action and the student\'s account, and nothing else to read',
	array( has( $line, '<input type="hidden" name="action" value="wpcpm_institution_resend_invite" />' ), has( $line, '<input type="hidden" name="user" value="10" />' ), substr_count( $line, '<input' ) ),
	array( true, true, 3 ) );
ck( 'its nonce keyed to the student\'s account', has( $line, 'value="nonce-wpcpm_institution_resend_invite_10"' ), true );
ck( 'under the line\'s anchor', has( $line, '<div class="wpcpm-institution__invite" id="wpcpm-student-invite">' ), true );

$first = draw( 20, 12 );

ck( 'a student never invited reads that none was sent', has( $first, '<p class="wpcpm-institution__invite-note">No sign-in on record since ' . $since . '. No invitation sent yet.</p>' ), true );
ck( 'and the button sends the first', has( $first, '>Send invitation</button>' ), true );
ck( 'keyed to that student', has( $first, 'value="nonce-wpcpm_institution_resend_invite_12"' ), true );

ck( 'a student who has signed in has no line and no button', draw( 20, 11 ), '' );
ck( 'nor does an account on the roster that is not a student', draw( 20, 14 ), '' );
ck( 'a member of another institution is drawn nothing', draw( 21, 10 ), '' );
ck( 'nor is an account that is nobody\'s member', draw( 40, 10 ), '' );
ck( 'nor anybody signed out', draw( 0, 10 ), '' );

$GLOBALS['settled'] = array( $B );
ck( 'nor a member whose agreement is not settled', draw( 20, 10 ), '' );
$GLOBALS['settled'] = array( $A, $B );

$by_admin = draw( 30, 10 );
ck( 'an Administrator through the switcher sees the same line, keyed to the student and not to them', $by_admin, $line );
ck( 'but not for a student whose account names no institution, whom the press would refuse', draw( 30, 16 ), '' );

$GLOBALS['opts']['date_format'] = '<\b>Y';
ck( 'the note is escaped', has( draw( 20, 10 ), 'Invitation last sent &lt;b&gt;' ), true );

$GLOBALS['opts']['date_format'] = 'Y-m-d';
ck( 'both dates follow the site\'s own format',
	has( draw( 20, 10 ), 'No sign-in on record since ' . gmdate( 'Y-m-d', $SINCE ) . '. Invitation last sent ' . gmdate( 'Y-m-d', $NW - 9 * DAY_IN_SECONDS ) . '.' ), true );
$GLOBALS['opts']['date_format'] = 'F j, Y';

$GLOBALS['opts'][ WPCPM_Sign_Ins::OPT_SINCE ] = 0;
ck( 'while the site keeps no sign-in record there is no day to name, and no line', draw( 20, 10 ), '' );
$GLOBALS['opts'][ WPCPM_Sign_Ins::OPT_SINCE ] = $SINCE;

// What the card is asked about the student: only whether they have signed in, never when.
update_user_meta( 10, WPCPM_Sign_Ins::META_SIGN_IN, '' );
ck( 'a sign-in key holding no time reads as no sign-in on record', has( draw( 20, 10 ), 'No sign-in on record since ' ), true );
update_user_meta( 10, WPCPM_Sign_Ins::META_SIGN_IN, gmmktime( 12, 0, 0, 3, 3, 2026 ) );
ck( 'and once a time is on record the card prints nothing, so no time of a sign-in reaches the page', draw( 20, 10 ), '' );

/* ---- 10. the notices on the card ----------------------------------------- */

echo "\n=== What the card says after a press ===\n";

reset_world();

foreach ( $expected as $outcome => $message ) {
	$reader = fresh_reader( $A );
	WPCPM_Flash::set( WPCPM_Institution_Resend::FLASH, array( 'outcome' => $outcome, 'user' => 10, 'detail' => 'a***@example.test' ), $reader );
	ck( sprintf( 'the card prints %s, in its tone, above the line', $outcome ),
		has( draw( $reader, 10 ), '<div class="wpcpm-institution__invite" id="wpcpm-student-invite"><p class="wpcpm-institution__invite-message is-' . $message[0] . '" role="status">' . esc_html( $message[1] ) . '</p><p class="wpcpm-institution__invite-note">' ), true );
}

$reader = fresh_reader( $A );
WPCPM_Flash::set( WPCPM_Institution_Resend::FLASH, array( 'outcome' => 'invite-resent', 'user' => 12, 'detail' => 'c***@example.test' ), $reader );
ck( 'a notice about another student is not printed on this card', has( draw( $reader, 10 ), 'is-success' ), false );

$reader = fresh_reader( $A );
WPCPM_Flash::set( WPCPM_Institution_Resend::FLASH, array( 'outcome' => 'invite-signed-in', 'user' => 11, 'detail' => '' ), $reader );
ck( 'a student who signed in meanwhile gets the notice and no line',
	draw( $reader, 11 ),
	'<div class="wpcpm-institution__invite" id="wpcpm-student-invite"><p class="wpcpm-institution__invite-message is-info" role="status">This student has signed in since the page was drawn, so nothing was sent.</p></div>' );

$reader = fresh_reader( $A );
WPCPM_Flash::set( WPCPM_Institution_Resend::FLASH, array( 'outcome' => 'invite-resent', 'user' => 10, 'detail' => '<b>x</b>@example.test' ), $reader );
ck( 'what a notice quotes is escaped', has( draw( $reader, 10 ), '&lt;b&gt;x&lt;/b&gt;@example.test' ), true );

$reader = fresh_reader( $A );
WPCPM_Flash::set( WPCPM_Institution_Resend::FLASH, array( 'outcome' => 'invite-unknown', 'user' => 10, 'detail' => '' ), $reader );
ck( 'an outcome the card does not know prints nothing', has( draw( $reader, 10 ), 'role="status"' ), false );

$reader = fresh_reader( $B );
WPCPM_Flash::set( WPCPM_Institution_Resend::FLASH, array( 'outcome' => 'invite-resent', 'user' => 10, 'detail' => 'a***@example.test' ), $reader );
ck( 'a reader the policy refuses is printed nothing, notice or not', draw( $reader, 10 ), '' );
ck( 'and the notice is spent, not left waiting', isset( $GLOBALS['umeta'][ $reader ]['wpcpm_flash'][ WPCPM_Institution_Resend::FLASH ] ), false );

/* ---- 11. the stylesheet dresses what the line prints ---------------------- */

echo "\n=== The stylesheet dresses the line ===\n";

$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/institution.css' ) );

foreach ( array( 'wpcpm-institution__invite', 'wpcpm-institution__invite-note', 'wpcpm-institution__invite-form', 'wpcpm-institution__invite-message' ) as $class ) {
	ck( sprintf( 'institution.css has a rule for .%s', $class ), 1 === preg_match( '/\.' . preg_quote( $class, '/' ) . '(?![a-zA-Z0-9_-])/', $css ), true );
}

ck( 'the notice takes the shape every flash on this page has', 1 === preg_match( '/\.wpcpm-agreement-panel__message,\s*\.wpcpm-institution__invite-message,\s*\.wpcpm-people__message\s*\{/', $css ), true );
ck( 'and the line sits close under the name it is about', 1 === preg_match( '/\.wpcpm-institution__student-identity:has\(\s*\+\s*\.wpcpm-institution__invite\s*\)\s*\{\s*margin-bottom:\s*0\.5em;/', $css ), true );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
```

In `bin/test-student-view-controls.php`, replace:

```php
 * - **The status card is last, and outside the card.** It is the only control on this page
 *   whose write cannot be taken back, and it draws a card box of its own.
```

with:

```php
 * - **The status card is last, and outside the card.** It is the only control on this page
 *   whose write cannot be taken back, and it draws a card box of its own.
 * - **The sign-in line sits under the name, inside the card, and is handed the student on
 *   screen.** What it says and who may press its button are `bin/test-institution-resend.php`'s.
```

In `bin/test-student-view-controls.php`, replace:

```php
$GLOBALS['export_calls'] = array();
$GLOBALS['status_calls'] = array();

class WP_User {
```

with:

```php
$GLOBALS['export_calls'] = array();
$GLOBALS['status_calls'] = array();
$GLOBALS['invite_calls'] = array();

class WP_User {
```

In `bin/test-student-view-controls.php`, replace:

```php
/** The three display helpers and the script handle the card uses. */
class WPCPM_Mentors_Dashboard {
```

with:

```php
/**
 * The sign-in line, to the one contract point the card has to satisfy: it is handed the account on
 * screen. Who may press, which students get a button and what the line says belong to
 * `bin/test-institution-resend.php`.
 */
class WPCPM_Institution_Resend {
	public static function render( WP_User $student ) {
		$GLOBALS['invite_calls'][] = (int) $student->ID;

		echo '<div class="wpcpm-institution__invite" id="wpcpm-student-invite"></div>';
	}
}

/** The three display helpers and the script handle the card uses. */
class WPCPM_Mentors_Dashboard {
```

In `bin/test-student-view-controls.php`, replace:

```php
	$GLOBALS['export_calls'] = array();
	$GLOBALS['status_calls'] = array();

	ob_start();
```

with:

```php
	$GLOBALS['export_calls'] = array();
	$GLOBALS['status_calls'] = array();
	$GLOBALS['invite_calls'] = array();

	ob_start();
```

In `bin/test-student-view-controls.php`, replace:

```php
printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
```

with:

```php
/* ---- 5. the sign-in line sits under the name ------------------------------ */

echo "\n=== The sign-in line sits under the name ===\n";

$ana = card( $inst_a, 10, 20 );

ck( 'the line is asked for once, about the student on screen and never the reader', $GLOBALS['invite_calls'], array( 10 ) );
ck( 'it comes right after the identity block, before the table',
	order( $ana, array( '<table', 'wpcpm-institution__invite', 'wpcpm-institution__student-identity', 'wpcpm-report__disclosure' ) ),
	array( 'wpcpm-institution__student-identity', 'wpcpm-institution__invite', '<table', 'wpcpm-report__disclosure' ) );
ck( 'as the next thing the card prints once the identity block closes', false !== strpos( $ana, '</div></div><div class="wpcpm-institution__invite"' ), true );

$opens  = substr( $ana, 0, (int) strpos( $ana, 'wpcpm-institution__invite' ) );
ck( 'inside the student card, not beside it', substr_count( $opens, '<section' ) - substr_count( $opens, '</section>' ), 1 );

card( $inst_b, 11, 30 );
ck( 'an Administrator reading another student asks about that student', $GLOBALS['invite_calls'], array( 11 ) );

card( $inst_a, 10, 20, array( WPCPM_Institution_Policy::ACT_VIEW_STUDENT => false ) );
ck( 'a reader refused the card never reaches the line', $GLOBALS['invite_calls'], array() );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );
```

- [ ] **Step 2: Run the two suites to see them fail**

Run: `for s in institution-resend student-view-controls; do php bin/test-$s.php > "$SCRATCH/red.txt" 2>&1; echo "== $s, exit $?"; grep -E '^(FAIL|PHP Fatal error|Fatal error)' "$SCRATCH/red.txt"; done`

Expected:

```text
== institution-resend, exit 255
PHP Fatal error:  Uncaught Error: Call to undefined method WPCPM_Institution_Resend::render() in <tree>/bin/test-institution-resend.php:656
Fatal error: Uncaught Error: Call to undefined method WPCPM_Institution_Resend::render() in <tree>/bin/test-institution-resend.php:656
== student-view-controls, exit 1
FAIL the line is asked for once, about the student on screen and never the reader
FAIL it comes right after the identity block, before the table
FAIL as the next thing the card prints once the identity block closes
FAIL inside the student card, not beside it
FAIL an Administrator reading another student asks about that student
```

- [ ] **Step 3: Write the line**

In `includes/modules/class-wpcpm-institution-resend.php`, replace:

```php
	/*
	 * What the card says
	 * --------------------------------------------------------------------
	 */
```

with:

```php
	/*
	 * The line on the card
	 * --------------------------------------------------------------------
	 */

	/**
	 * The sign-in line for one student's card, and the outcome of the last press on it.
	 *
	 * Nothing at all for a reader the policy refuses, never a disabled control with a reason
	 * beside it. The flash is taken first, so a sentence meant for this card is spent by the
	 * render that could have printed it rather than waiting in user meta for a later one. A
	 * student with a sign-in on record, or an account that no longer holds the Student role, gets
	 * no line and no button; the outcome of a press on their card is still printed. No student
	 * gets the line while the site keeps no sign-in record (`WPCPM_Sign_Ins::since()` is 0 until
	 * the record begins), because the line names the day it began.
	 *
	 * @param WP_User $student The student whose card this is.
	 */
	public static function render( WP_User $student ) {
		$user_id  = (int) $student->ID;
		$flash    = self::flash_for( $user_id );
		$decision = self::decision( $user_id );

		if ( empty( $decision['allowed'] ) ) {
			return;
		}

		$line = WPCPM_Sign_Ins::since() > 0
			&& WPCPM_Roles::user_has_role( $student, WPCPM_Roles::ROLE_STUDENT )
			&& ! WPCPM_Sign_Ins::has_signed_in( $user_id );

		if ( ! $line && empty( $flash ) ) {
			return;
		}

		printf( '<div class="wpcpm-institution__invite" id="%s">', esc_attr( self::ANCHOR ) );

		if ( ! empty( $flash ) ) {
			printf(
				'<p class="wpcpm-institution__invite-message is-%1$s" role="status">%2$s</p>',
				esc_attr( $flash['tone'] ),
				esc_html( $flash['text'] )
			);
		}

		if ( $line ) {
			self::render_line( $user_id );
		}

		echo '</div>';
	}

	/**
	 * The sentence and the button.
	 *
	 * Two dates, both in the site's date format: the day the site began recording sign-ins
	 * (`WPCPM_Sign_Ins::since()`), since the site can speak of no sign-in before it, and the last
	 * invitation by any route (`WPCPM_Mail::last_invited()`). The form is locked at its first press
	 * and says it is sending, as the plugin's other one-press forms do, and its nonce is keyed to
	 * the student's account, so a form drawn for one student is no use for another.
	 *
	 * @param int $user_id The student's account.
	 */
	private static function render_line( $user_id ) {
		$user_id = (int) $user_id;
		$last    = WPCPM_Mail::last_invited( $user_id );
		$format  = (string) get_option( 'date_format' );
		$since   = wp_date( $format, WPCPM_Sign_Ins::since() );

		if ( $last > 0 ) {
			$note = sprintf(
				/* translators: 1: the day the site began recording sign-ins, 2: the date the last invitation was sent, both in the site's date format. */
				__( 'No sign-in on record since %1$s. Invitation last sent %2$s.', 'wpcredits-program-manager' ),
				$since,
				wp_date( $format, $last )
			);
		} else {
			$note = sprintf(
				/* translators: %s: the day the site began recording sign-ins, in the site's date format. */
				__( 'No sign-in on record since %s. No invitation sent yet.', 'wpcredits-program-manager' ),
				$since
			);
		}

		printf( '<p class="wpcpm-institution__invite-note">%s</p>', esc_html( $note ) );

		printf(
			'<form class="wpcpm-institution__invite-form" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Sending...', 'wpcredits-program-manager' )
		);

		wp_nonce_field( self::ACTION . '_' . $user_id );

		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION ) );
		printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( self::FIELD_USER ), (int) $user_id );

		printf(
			'<button type="submit" class="wpcpm-button">%s</button>',
			esc_html( $last > 0 ? __( 'Resend invitation', 'wpcredits-program-manager' ) : __( 'Send invitation', 'wpcredits-program-manager' ) )
		);

		echo '</form>';
	}

	/**
	 * The last press's outcome when it is about this student, as its tone and its sentence.
	 *
	 * @param int $user_id The student whose card is being drawn.
	 * @return array{tone?: string, text?: string} Empty when there is nothing to print here.
	 */
	private static function flash_for( $user_id ) {
		$flash   = WPCPM_Flash::take( self::FLASH );
		$outcome = is_array( $flash ) && isset( $flash['outcome'] ) ? sanitize_key( (string) $flash['outcome'] ) : '';
		$about   = is_array( $flash ) && isset( $flash['user'] ) ? (int) $flash['user'] : 0;
		$detail  = is_array( $flash ) && isset( $flash['detail'] ) && is_scalar( $flash['detail'] ) ? (string) $flash['detail'] : '';

		// Routing, not authorization: which card prints a sentence, never who may read one.
		if ( '' === $outcome || $about !== (int) $user_id ) {
			return array();
		}

		$messages = self::messages( $detail );

		if ( ! isset( $messages[ $outcome ] ) ) {
			return array();
		}

		return array(
			'tone' => $messages[ $outcome ][0],
			'text' => $messages[ $outcome ][1],
		);
	}

	/*
	 * What the card says
	 * --------------------------------------------------------------------
	 */
```

- [ ] **Step 4: Draw it on the card**

In `includes/modules/class-wpcpm-institution-student-view.php`, replace:

```php
 * **Two controls hang off the card, and neither of them belongs to this file.** The single
 * student export is `WPCPM_Institution_Export`'s, drawn as a link when `decide( ACT_EXPORT )`
 * allows it; graduate and withdraw are `WPCPM_Institution_Students`', drawn by calling its own
 * renderer. Every rule about either - which states are offered, that a `Paused` student is
 * refused by name, what the confirm dialog says, how the nonces are keyed - lives in those two
 * classes, and this file decides nothing about them beyond where they go.
```

with:

```php
 * **Three controls hang off the card, and none of them belongs to this file.** The single
 * student export is `WPCPM_Institution_Export`'s, drawn as a link when `decide( ACT_EXPORT )`
 * allows it; graduate and withdraw are `WPCPM_Institution_Students`', drawn by calling its own
 * renderer; the line saying whether the student has signed in yet, with the button that sends
 * their invitation again, is `WPCPM_Institution_Resend`'s, drawn the same way. Every rule about
 * any of them - which states are offered, that a `Paused` student is refused by name, what the
 * confirm dialog says, who may send an invitation and how often, how the nonces are keyed -
 * lives in those three classes, and this file decides nothing about them beyond where they go.
```

In `includes/modules/class-wpcpm-institution-student-view.php`, replace:

```php
		self::render_back( $context );
		self::render_identity( $student, $program, $row );
```

with:

```php
		self::render_back( $context );
		self::render_identity( $student, $program, $row );
		self::render_invite( $student );
```

In `includes/modules/class-wpcpm-institution-student-view.php`, replace:

```php
	/**
	 * The program this student is on, as the Students Reports table says it.
```

with:

```php
	/**
	 * Whether the student has signed in yet, under their name, drawn by the class that owns it.
	 *
	 * Every rule about the line and its button is `WPCPM_Institution_Resend`'s: who may press, for
	 * which students, the two limits, what the line and the notices say. Not one of them is
	 * repeated here, and that includes whether to draw anything at all. Guarded, as the other two
	 * controls are, so a site that has not loaded the class draws the card without the line.
	 *
	 * @param WP_User $student The student.
	 */
	private static function render_invite( WP_User $student ) {
		if ( ! class_exists( 'WPCPM_Institution_Resend' ) ) {
			return;
		}

		WPCPM_Institution_Resend::render( $student );
	}

	/**
	 * The program this student is on, as the Students Reports table says it.
```

- [ ] **Step 5: Dress it**

In `assets/css/institution.css`, replace:

```css
.wpcpm-agreement-panel__message,
.wpcpm-people__message {
	border: 1px solid var( --wpcpm-border );
```

with:

```css
.wpcpm-agreement-panel__message,
.wpcpm-institution__invite-message,
.wpcpm-people__message {
	border: 1px solid var( --wpcpm-border );
```

In `assets/css/institution.css`, replace:

```css
.wpcpm-institution__student-name {
	font-size: 1.2em;
	font-weight: 700;
	margin: 0;
}
```

with:

```css
.wpcpm-institution__student-name {
	font-size: 1.2em;
	font-weight: 700;
	margin: 0;
}

/* Whether the student has signed in yet, and the button that sends the invitation again. It is
   about the name above it, so it sits close under the name (8px) and leaves the card's own room
   (1.25em) before the table. `:has()` is a progressive touch: without it the line sits 1.25em
   below the name, which is the gap the table had. */
.wpcpm-institution__student-identity:has( + .wpcpm-institution__invite ) {
	margin-bottom: 0.5em;
}

.wpcpm-institution__invite {
	align-items: center;
	display: flex;
	flex-wrap: wrap;
	gap: 0.5em 1em;
	margin: 0 0 1.25em;
}

.wpcpm-institution__invite-note,
.wpcpm-institution__invite-form {
	margin: 0;
}

/* The outcome of a press, on a line of its own above the sentence. The flex gap is the room
   under it, so the shared message rule's own margin is taken off here. */
.wpcpm-institution__invite-message {
	flex: 1 1 100%;
	margin: 0;
}
```

- [ ] **Step 6: Run the suites to see them pass**

Run: `for s in institution-resend student-view-controls institution-student-view; do printf '%s: %s, %s ok\n' "$s" "$(php bin/test-$s.php | tail -n 1)" "$(php bin/test-$s.php | grep -c '^ok ')"; done`

Expected:

```text
institution-resend: ALL PASS (112 checks), 112 ok
student-view-controls: ALL PASS (45 checks), 45 ok
institution-student-view: ALL PASS (104 checks), 104 ok
```

`bin/test-institution-student-view.php` loads the card without the class, so the guard draws its card exactly as before: its 104 checks are unchanged.

- [ ] **Step 7: The battery and the checks**

Expected:

```text
battery: 130 suites, 0 failing, 16849 ok lines
154 classes, 14560 references checked, 88 form handlers scanned - all resolve
78 warnings, no errors.
157 PHP files, 7 blocks, 4 track seeds, 23 fixture and tooling files, the guides and the readme: US English throughout.
```

- [ ] **Step 8: Commit**

```bash
git add includes/modules/class-wpcpm-institution-resend.php includes/modules/class-wpcpm-institution-student-view.php assets/css/institution.css bin/test-institution-resend.php bin/test-student-view-controls.php
git commit -m "1.122.30: a student's card says whether a sign-in is on record for them, with the button that sends their invitation again" -m "$COAUTHOR"
```

---

### Task 5: The guide, the version, the changelog and the template

**Files:**
- Modify: `docs/sections/34-admin-operations.md` (the Invitations section's last paragraph)
- Generated: `docs/administrators.md`, `docs/build/administrators.html` by `php bin/build-docs.php`
- Modify: `wpcredits-program-manager.php` (header `Version:`, `WPCPM_VERSION`), `readme.txt` (`Stable tag:`, the changelog)
- Generated: `languages/wpcredits-program-manager.pot` by `sh bin/make-pot.sh`

**Interfaces:** none; nothing reads these but people, the release script and translators.

- [ ] **Step 1: The Administrators' guide**

In `docs/sections/34-admin-operations.md`, replace:

```markdown
in the last fifteen minutes. Ask them to use their newest email, or send another once the fifteen
minutes are up.
```

with:

```markdown
in the last fifteen minutes. Ask them to use their newest email, or send another once the fifteen
minutes are up.

Since 1.122.30 an institution sends a student's welcome invitation again itself. On a student's
card on the Institution Dashboard, a student with no sign-in on record since the site began
recording sign-ins, on October 15, 2026, shows a line saying so, with when their last invitation
was sent or that none has been, and a **Resend invitation** button (**Send invitation** when none
has). Every member of the student's own institution can press it, and so can an Administrator who
opens that institution's dashboard through the switcher. It sends the same invitation as
**Resend invite** here, with a new link that replaces the one before, and no Administrator is
emailed. The fifteen minutes hold here too, and each institution can send twenty a day, the presses
of an Administrator through the switcher included; a press that sends nothing costs nothing, an
email the host refuses included. A student with a sign-in on record shows no line and no button: a
lost password has WordPress's own reset. Each send is in the Emails tool's **Log**, and the
institution's history records each one in its audit log, with who pressed it and whether as a
member or as an Administrator.
```

- [ ] **Step 2: Build the guides**

Run: `php bin/build-docs.php && git status --short docs`

Expected:

```text
students          20650 bytes of Markdown, 101 blocks
mentors           34128 bytes of Markdown, 175 blocks
administrators   206901 bytes of Markdown, 612 blocks
sponsors          13338 bytes of Markdown,  52 blocks
Done.
 M docs/administrators.md
 M docs/build/administrators.html
 M docs/sections/34-admin-operations.md
```

Only the Administrators' guide holds the Invitations section, so the other three guides do not change.

- [ ] **Step 3: The version**

In `wpcredits-program-manager.php`, replace:

```php
 * Version:           1.122.29
```

with:

```php
 * Version:           1.122.30
```

In `wpcredits-program-manager.php`, replace:

```php
define( 'WPCPM_VERSION', '1.122.29' );
```

with:

```php
define( 'WPCPM_VERSION', '1.122.30' );
```

In `readme.txt`, replace:

```text
Stable tag: 1.122.29
```

with:

```text
Stable tag: 1.122.30
```

- [ ] **Step 4: The changelog**

In `readme.txt`, replace:

```text
== Changelog ==

= 1.122.29 =
```

with:

```text
== Changelog ==

= 1.122.30 =

* An institution can send a student's welcome invitation again from the Institution Dashboard. On a student's card, a student with no sign-in on record since the site began recording sign-ins (October 15, 2026) shows a line saying so, with when their last invitation was sent, or that none has been, and a Resend invitation button (Send invitation when none has). Every member of the student's own institution can press it, and so can an Administrator through the switcher. It sends the same invitation as Resend invite in wp-admin, with a new link that replaces the one before; one every fifteen minutes for each student and twenty a day for each institution. A press that sends nothing costs nothing, an email the host refuses included. The institution's history records each send, with who pressed it.
* An institution now sees whether a sign-in is on record for each of its students since October 15, 2026, and nothing more: never when. A student with a sign-in on record shows no line and no button.

= 1.122.29 =
```

- [ ] **Step 5: The translation template, last**

Run: `sh bin/make-pot.sh 2>&1 | tail -n 1; grep -m1 'Project-Id-Version' languages/wpcredits-program-manager.pot; for m in 'No sign-in on record since %1$s. Invitation last sent %2$s.' 'No sign-in on record since %s. No invitation sent yet.' 'Resend invitation' 'The invitation is on its way to %s.' 'This student has signed in since the page was drawn, so nothing was sent.' 'Welcome invitation sent from the Institution Dashboard.'; do grep -cF "msgid \"$m\"" languages/wpcredits-program-manager.pot; done`

Expected:

```text
Success: POT file successfully generated.
"Project-Id-Version: WPCredits Program Manager 1.122.30\n"
1
1
1
1
1
1
```

- [ ] **Step 6: The final battery, the checks and the dashes**

Run the battery and the checks, then: `grep -rIn --exclude-dir=.git --exclude-dir=fixtures --exclude-dir=.superpowers --exclude-dir=.worktrees --exclude=collaboration-agreement-en.php -e $'\xe2\x80\x94' -e $'\xe2\x80\x93' . || echo "no dashes"`

Expected:

```text
battery: 130 suites, 0 failing, 16849 ok lines
154 classes, 14560 references checked, 88 form handlers scanned - all resolve
78 warnings, no errors.
157 PHP files, 7 blocks, 4 track seeds, 23 fixture and tooling files, the guides and the readme: US English throughout.
no dashes
```

- [ ] **Step 7: Commit**

```bash
git add docs/sections/34-admin-operations.md docs/administrators.md docs/build/administrators.html wpcredits-program-manager.php readme.txt languages/wpcredits-program-manager.pot
git commit -m "1.122.30: the guide, the version, the changelog and the translation template" -m "$COAUTHOR"
```

- [ ] **Step 8: The institutions guide's paragraph, for the owner**

The institutions guide is the handbook page the Institution Dashboard's Resources section links to (make.wordpress.org/community/handbook/education/credits/institutions/), outside this repository. Hand the owner this paragraph to publish there once 1.122.30 is live:

```markdown
**Sending a student's invitation again.** When the site has no sign-in on record for one of your students (it has recorded sign-ins since October 15, 2026), their card on your Institution Dashboard says so, with the date their last invitation was sent, and a **Resend invitation** button (**Send invitation** if none has been sent). Press it to send them the welcome invitation again. The new email has a new link, which replaces the link in the earlier one, so ask the student to use the newest email. A student can be sent one invitation every 15 minutes, so that the next one does not cancel the link just sent, and your institution can send up to 20 a day. Once a student has signed in, the button is gone: if they forget their password, they can reset it from the sign-in page.
```

---

### Task 6: The trial on the main-line Studio copy (the controller's)

On the main-line copy (http://localhost:8883, `~/Studio/wpcredits-t5`), hardened: its safety must-use plugin swallows every email into `wp-content/local-safety-mail.log`, refuses Airtable and every request off the machine, and cron is off. WP-CLI is `~/.local/bin/studio wp --path ~/Studio/wpcredits-t5`. Everything below happens inside a database snapshot that is put back at the end. The trial's accounts are throwaway ones the setup script makes, each at a plus-address of the owner's one test mailbox (the vault names it; it is not written here); no real person is pressed for or as. Scripts and captures live in `.superpowers/sdd/2026-10-16-resend-invitation-1-122-30/trial/` (git-ignored, never mirrored); back them up there before the scratchpad is swept.

```bash
export SITE=~/Studio/wpcredits-t5
export WP="$HOME/.local/bin/studio wp --path $SITE"
export TRIAL=~/GitHub/wpcredits-program-manager/.superpowers/sdd/2026-10-16-resend-invitation-1-122-30/trial
export SNAP=~/GitHub/wpcredits-program-manager/.superpowers/session-backups/2026-10-16/copy-8883-before-trial-1.122.30
mkdir -p "$TRIAL" "$SNAP"
```

- [ ] **Step 1: Read the copy, and stop if it is not as expected**

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8883/
$WP plugin get wpcredits-program-manager --field=version | tee "$SNAP/plugin-version.txt"
ls -la "$SITE/wp-content/database/" "$SITE/wp-content/mu-plugins/"
grep -c "DISABLE_WP_CRON" "$SITE/wp-config.php"
$WP eval '$s = WPCPM_Settings::get(); echo ( "" === (string) ( isset( $s["api_token"] ) ? $s["api_token"] : "" ) && "" === (string) ( isset( $s["schema_token"] ) ? $s["schema_token"] : "" ) ) ? "tokens blank\n" : "A TOKEN IS SET\n";'
$WP eval 'echo has_filter( "pre_wp_mail" ) ? "mail held\n" : "MAIL NOT HELD\n";'
wc -l < "$SITE/wp-content/local-safety-mail.log"
```

Expected: `200`; a 1.122 version (the copy's last install), written to the snapshot folder; the database folder holding `.ht.sqlite`; the safety plugin among the must-use plugins; `1`; `tokens blank`; `mail held`, the safety plugin's filter live; a line count, the log's length before the trial. Never read the settings option itself, and never print a token. If any answer differs, stop and tell the owner. If the site answers a fatal about a missing `prepend.php`, run `~/.local/bin/studio site stop --path "$SITE" > /dev/null 2>&1; ~/.local/bin/studio site start --path "$SITE" > /dev/null 2>&1` (start prints the local Administrator's password: the output is discarded, never repeated).

- [ ] **Step 2: The snapshot, the plugin of its line first**

```bash
(cd "$SITE/wp-content/plugins" && zip -qr "$SNAP/plugin-before.zip" wpcredits-program-manager)
~/.local/bin/studio site stop --path "$SITE" > /dev/null 2>&1
cp "$SITE/wp-content/database/.ht.sqlite" "$SNAP/ht.sqlite"
~/.local/bin/studio site start --path "$SITE" > /dev/null 2>&1
ls -la "$SNAP"
```

Expected: `plugin-before.zip`, `ht.sqlite` and `plugin-version.txt` in the snapshot folder.

- [ ] **Step 3: Build the branch and install it**

```bash
cd ~/GitHub/wpcredits-program-manager/.worktrees/resend-1-122-30
bash bin/build "$SCRATCH/wpcredits-program-manager-1.122.30.zip"
$WP plugin install "$SCRATCH/wpcredits-program-manager-1.122.30.zip" --force
$WP plugin get wpcredits-program-manager --field=version
```

Expected: the build names 1.122.30, the install succeeds, and the copy reads `1.122.30`. The zip is never written to `~/GitHub`.

- [ ] **Step 4: The error logger and the trial's accounts**

Create `$TRIAL/wpcpm-trial-errors.php` and copy it to `$SITE/wp-content/mu-plugins/`:

```php
<?php
/**
 * Throwaway must-use plugin for the 1.122.30 trial on http://localhost:8883: every PHP error raised
 * from the plugin's or the theme's files goes to wp-content/trial-php-errors.log, since the copy
 * runs with WP_DEBUG off. Removed when the trial ends.
 */
set_error_handler(
	function ( $number, $message, $file, $line ) {
		if ( false !== strpos( (string) $file, '/plugins/wpcredits-program-manager/' ) || false !== strpos( (string) $file, '/themes/wpcredits-theme/' ) ) {
			error_log( gmdate( 'c' ) . ' ' . $number . ' ' . $message . ' ' . $file . ':' . $line . "\n", 3, WP_CONTENT_DIR . '/trial-php-errors.log' );
		}

		return false;
	}
);
```

Create `$TRIAL/trial-resend-setup.php`:

```php
<?php
/**
 * The 1.122.30 trial's accounts, on the main-line Studio copy at http://localhost:8883 alone.
 *
 * Makes, inside the snapshot the trial puts back afterwards: a member of each of two institutions
 * whose agreement is settled, and four students of the first, every address a plus-address of the
 * test mailbox named on the command line. Begins the sign-in record first when the copy has never
 * begun it, as an Administrator's first wp-admin page would. Prints the record's first day, the
 * accounts' IDs, the two institutions and the Institution Dashboard's address as one line of JSON. Sends nothing: `wp_insert_user()` mails nobody, and the
 * memberships are written through `WPCPM_Institution_Members::attach()`, which mails nobody either.
 *
 * Usage: studio wp --path ~/Studio/wpcredits-t5 eval-file trial-resend-setup.php <test mailbox>
 *
 * @package WPCreditsProgramManager
 */

if ( 'http://localhost:8883' !== untrailingslashit( (string) get_option( 'siteurl' ) ) ) {
	WP_CLI::error( 'This runs on http://localhost:8883 alone.' );
}

$mailbox = isset( $args[0] ) ? trim( (string) $args[0] ) : '';

if ( ! is_email( $mailbox ) ) {
	WP_CLI::error( 'Name the test mailbox: eval-file trial-resend-setup.php <test mailbox>' );
}

list( $local, $domain ) = explode( '@', $mailbox, 2 );

// The card's line names the day the sign-in record began, and draws nothing before it has. Begun
// here, before any trial account exists, the way `WPCPM_Sign_Ins::maybe_seed()` begins it.
if ( ! WPCPM_Sign_Ins::since() ) {
	update_option( WPCPM_Sign_Ins::OPT_VERSION, WPCPM_Sign_Ins::SCHEMA_VERSION );
	update_option( WPCPM_Sign_Ins::OPT_SINCE, time(), false );
	WPCPM_Sign_Ins::seed();
}

$settled = array();

foreach ( array_keys( (array) WPCPM_Institutions_Index::rows() ) as $record ) {
	if ( WPCPM_Mentors_Sync::is_record_id( $record ) && WPCPM_Institution_Agreement::is_settled( (string) $record ) ) {
		$settled[] = (string) $record;
	}

	if ( 2 === count( $settled ) ) {
		break;
	}
}

if ( 2 !== count( $settled ) ) {
	WP_CLI::error( 'The copy holds fewer than two institutions whose agreement is settled.' );
}

$make = function ( $tag, $role ) use ( $local, $domain ) {
	$id = wp_insert_user(
		array(
			'user_login'   => 'resend-trial-' . $tag,
			'user_email'   => $local . '+resend-' . $tag . '@' . $domain,
			'user_pass'    => wp_generate_password( 32 ),
			'display_name' => 'Resend trial ' . $tag,
			'role'         => $role,
		)
	);

	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $tag . ': ' . $id->get_error_message() );
	}

	return (int) $id;
};

$ids = array(
	'member_a' => $make( 'member-a', 'subscriber' ),
	'member_b' => $make( 'member-b', 'subscriber' ),
	's1'       => $make( 's1', WPCPM_Roles::ROLE_STUDENT ),
	's2'       => $make( 's2', WPCPM_Roles::ROLE_STUDENT ),
	's3'       => $make( 's3', WPCPM_Roles::ROLE_STUDENT ),
	's4'       => $make( 's4', WPCPM_Roles::ROLE_MENTOR ),
);

foreach ( array( 'member_a' => $settled[0], 'member_b' => $settled[1] ) as $who => $record ) {
	$attached = WPCPM_Institution_Members::attach( $ids[ $who ], $record, WPCPM_Institution_Members::HOW_MANAGER, 0 );

	if ( is_wp_error( $attached ) ) {
		WP_CLI::error( $who . ': ' . $attached->get_error_message() );
	}
}

$sent = WPCPM_Mail::STAMPS[ WPCPM_Roles::ROLE_STUDENT ];

// s1 was invited two days ago and has no sign-in on record; s2 was never invited; s3 signed in
// yesterday; s4 sits on the same institution with the Mentor role and no Student role.
foreach ( array( 's1', 's2', 's3', 's4' ) as $who ) {
	update_user_meta( $ids[ $who ], WPCPM_Students_Sync::META_INSTITUTION, $settled[0] );
}

update_user_meta( $ids['s1'], $sent, time() - 2 * DAY_IN_SECONDS );
update_user_meta( $ids['s3'], $sent, time() - 3 * DAY_IN_SECONDS );
update_user_meta( $ids['s3'], WPCPM_Sign_Ins::META_SIGN_IN, time() - DAY_IN_SECONDS );

WP_CLI::line(
	wp_json_encode(
		$ids + array(
			'since' => wp_date( (string) get_option( 'date_format' ), WPCPM_Sign_Ins::since() ),
			'x'     => $settled[0],
			'y'     => $settled[1],
			'page'  => WPCPM_Institutions_Dashboard::page_url(),
		)
	)
);
```

Run it, naming the owner's test mailbox, and keep its answer:

```bash
cp "$TRIAL/wpcpm-trial-errors.php" "$SITE/wp-content/mu-plugins/"
$WP eval-file "$TRIAL/trial-resend-setup.php" "<the owner's test mailbox>" | tee "$TRIAL/ids.json"
wc -l < "$SITE/wp-content/local-safety-mail.log"
```

Expected: the log's length unchanged from Step 1 (nothing was mailed; this count is the baseline below), and one line of JSON naming `since`, the sign-in record's first day in the site's date format (the day the copy began it, or today when the script began it), `member_a` and `member_b` (one member each of the first two institutions the copy holds with a settled agreement, `x` and `y`), `s1` (invited two days ago, no sign-in on record), `s2` (never invited), `s3` (signed in yesterday), `s4` (on `x` with the Mentor role and no Student role) and `page`, the Institution Dashboard's address. Below, `<member_a>`, `<s1>` and the rest are those values.

- [ ] **Step 5: A throwaway sign-in for the built-in browser**

Create `$TRIAL/trial-signin.php.tpl`:

```php
<?php
/**
 * Throwaway: signs the built-in browser in as one of two accounts on http://localhost:8883, for the
 * 1.122.30 trial. Deleted by its literal path when the trial ends.
 */
require __DIR__ . '/wp-load.php';

$wpcpm_allowed = array( MEMBER_ID, ADMIN_ID );
$wpcpm_user    = isset( $_GET['u'] ) ? (int) $_GET['u'] : 0;
$wpcpm_key     = isset( $_GET['k'] ) ? (string) wp_unslash( $_GET['k'] ) : '';

if ( 'http://localhost:8883' !== untrailingslashit( (string) get_option( 'siteurl' ) ) || ! hash_equals( 'SIGNIN_KEY', $wpcpm_key ) || ! in_array( $wpcpm_user, $wpcpm_allowed, true ) ) {
	status_header( 404 );
	exit;
}

wp_set_auth_cookie( $wpcpm_user, false );
wp_safe_redirect( isset( $_GET['to'] ) ? esc_url_raw( wp_unslash( $_GET['to'] ) ) : home_url( '/' ) );
exit;
```

Fill it for the member and the copy's local Administrator (the vault names that account) with a random key, under a random name in the site's root, and note the two values:

```bash
KEY=$(openssl rand -hex 16); NAME="signin-$(openssl rand -hex 6).php"
sed -e "s/MEMBER_ID/<member_a>/" -e "s/ADMIN_ID/<the local Administrator's ID>/" -e "s/SIGNIN_KEY/$KEY/" "$TRIAL/trial-signin.php.tpl" > "$SITE/$NAME"
echo "$NAME $KEY" > "$TRIAL/signin.txt"
```

It refuses (404) on any other site, without the key, or for any other account. It is deleted by its literal path in Step 11.

- [ ] **Step 6: The card and the press, as a member, at 1280 pixels**

In the built-in browser, at 1280 by 900 (`resize_window` 1280 x 900), open `http://localhost:8883/<NAME>?u=<member_a>&k=<KEY>&to=<page>?wpcpm_institution_student=<s1>` (the `to` address URL-encoded). Then:

1. Read the card: under the name, "No sign-in on record since <`since`>. Invitation last sent <the date two days ago, in the site's date format>." and **Resend invitation**. Run in the page:

```js
(() => {
	const r = ( s ) => document.querySelector( s ).getBoundingClientRect();
	const form = document.querySelector( '.wpcpm-institution__invite-form' );
	const notice = document.querySelector( '.wpcpm-institution__invite-message' );
	return {
		nameToLine: Math.round( r( '.wpcpm-institution__invite' ).top - r( '.wpcpm-institution__student-identity' ).bottom ),
		lineToTable: Math.round( r( '.wpcpm-institution__student table' ).top - r( '.wpcpm-institution__invite' ).bottom ),
		noticeToNote: notice ? Math.round( r( '.wpcpm-institution__invite-note' ).top - notice.getBoundingClientRect().bottom ) : null,
		fontSize: getComputedStyle( document.querySelector( '.wpcpm-institution__invite' ) ).fontSize,
		once: form.hasAttribute( 'data-wpcpm-once' ),
		busy: form.getAttribute( 'data-wpcpm-busy' ),
		sideways: document.documentElement.scrollWidth - window.innerWidth
	};
} )()
```

Expected: `nameToLine` half the font size (8 at 16px) and `lineToTable` one and a quarter times it (20 at 16px); `noticeToNote` null, since no notice is on the card yet; `once` true; `busy` "Sending..."; `sideways` 0. Screenshot the card.

2. Press **Resend invitation** twice in quick succession. Expected: the page comes back to the line (the address ends `#wpcpm-student-invite`) with "The invitation is on its way to <the plus-address, masked>. The new link replaces the one in the earlier email." above "No sign-in on record since <`since`>. Invitation last sent <today>.", and `wc -l < "$SITE/wp-content/local-safety-mail.log"` is one more than Step 4's count: the second press was never posted, or was refused by the fifteen minutes. Run the snippet again: `noticeToNote` half the font size (8 at 16px), `nameToLine` and `lineToTable` as before. Screenshot it.

3. Press **Resend invitation** once more. Expected: "This person was sent an invitation less than 15 minutes ago. Another one now would cancel the link in it, so nothing was sent." and the log's count unchanged.

4. Open `<page>?wpcpm_institution_student=<s2>`: "No sign-in on record since <`since`>. No invitation sent yet." and **Send invitation**. Press it: "The invitation is on its way to <masked>." with no sentence about an earlier email, and one more line in the log.

5. Open `<page>?wpcpm_institution_student=<s3>`: the card, with no line and no button under the name, and no date of a sign-in anywhere on it.

- [ ] **Step 7: The same card at 375 pixels**

`resize_window` preset `mobile`, reload `<page>?wpcpm_institution_student=<s1>`, run the snippet of Step 6 again and screenshot. Expected: the button on its own line under the sentence, `sideways` 0, the same two gaps. Then press **Resend invitation** (the fifteen minutes refuse it, which is the notice wanted here), run the snippet once more and screenshot: `noticeToNote` 8 at 16px, the other gaps unchanged, `sideways` 0. Back to `desktop` after.

- [ ] **Step 8: Through the switcher, as an Administrator**

Put the fifteen minutes behind `s1` first: `$WP eval 'update_user_meta( <s1>, WPCPM_Mail::STAMPS[ WPCPM_Roles::ROLE_STUDENT ], time() - 20 * MINUTE_IN_SECONDS );'`. Open `http://localhost:8883/<NAME>?u=<the local Administrator's ID>&k=<KEY>&to=<page>?wpcpm_institution_view=<x>&wpcpm_institution_student=<s1>` (the `to` address URL-encoded, as in Step 6). Expected: the same line; a press sends (one more line in the log) and says so. Then read the history:

```bash
$WP eval 'foreach ( WPCPM_Institution_Audit::entries_for( "<x>", 10 ) as $e ) { if ( "student-invite-resent" === $e["kind"] ) { echo $e["subject"], " ", $e["actor"], " ", $e["ground"], " ", $e["evidence"], " ", wp_json_encode( $e["data"] ), "\n"; } }'
$WP eval 'echo WPCPM_Ceiling::count( WPCPM_Institution_Resend::ceiling_key( "<x>" ), DAY_IN_SECONDS ), "\n";'
```

Expected: three rows, newest first: `<s1> <the Administrator's ID> manager cache {"student":<s1>,"first":false}`, `<s2> <member_a> member cache {"student":<s2>,"first":true}`, `<s1> <member_a> member cache {"student":<s1>,"first":false}`; and `3`, the three sends of the day.

- [ ] **Step 9: Every refusal, pressed through the handler**

Create `$TRIAL/trial-resend-press.php`:

```php
<?php
/**
 * Press the 1.122.30 handler as one account for one student, on http://localhost:8883 alone, and
 * print what came of it as one line of JSON: how the request ended, the outcome and its detail,
 * the emails the copy's safety plugin swallowed and the email log's new rows, the student's
 * invitation stamp and the places the student's institution has spent today, each before and
 * after the press, and the history rows it added.
 *
 * With `bad-nonce` the form's token is forged; with `mail-fails` the email is refused as a host
 * refuses one, after the copy's safety plugin has taken it: WordPress's `wp_mail_failed` fires and
 * `wp_mail()` answers false.
 *
 * Usage: studio wp --path ~/Studio/wpcredits-t5 eval-file trial-resend-press.php <presser ID> <student ID> [bad-nonce|mail-fails]
 *
 * @package WPCreditsProgramManager
 */

if ( 'http://localhost:8883' !== untrailingslashit( (string) get_option( 'siteurl' ) ) ) {
	WP_CLI::error( 'This runs on http://localhost:8883 alone.' );
}

$presser = isset( $args[0] ) ? (int) $args[0] : 0;
$student = isset( $args[1] ) ? (int) $args[1] : 0;
$forged  = isset( $args[2] ) && 'bad-nonce' === $args[2];
$refuse  = isset( $args[2] ) && 'mail-fails' === $args[2];

global $wpdb;

$institution = (string) get_user_meta( $student, WPCPM_Students_Sync::META_INSTITUTION, true );
$safety_log  = WP_CONTENT_DIR . '/local-safety-mail.log';
$mail_table  = $wpdb->prefix . 'wpcpm_mail_log';
$count_lines = function () use ( $safety_log ) {
	return is_readable( $safety_log ) ? count( (array) file( $safety_log ) ) : 0;
};
$count_rows  = function () use ( $wpdb, $mail_table ) {
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$mail_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
};
$history     = function () use ( $institution ) {
	return WPCPM_Mentors_Sync::is_record_id( $institution ) ? count( WPCPM_Institution_Audit::entries_for( $institution, 0 ) ) : 0;
};
$spent       = function () use ( $institution ) {
	return WPCPM_Ceiling::count( WPCPM_Institution_Resend::ceiling_key( $institution ), DAY_IN_SECONDS );
};

$before       = array( $count_lines(), $count_rows(), $history(), $spent() );
$stamp_before = WPCPM_Mail::last_invited( $student );

wp_set_current_user( $presser );
delete_user_meta( $presser, WPCPM_Flash::META );

$_POST    = array(
	'action'   => WPCPM_Institution_Resend::ACTION,
	'user'     => (string) $student,
	'_wpnonce' => $forged ? 'not-a-nonce' : wp_create_nonce( WPCPM_Institution_Resend::ACTION . '_' . $student ),
);
$_REQUEST = $_POST;

// The handler ends in a redirect and `exit`, and a bad token in `wp_die()`; both are caught here so
// the command can say what happened.
add_filter(
	'wp_redirect',
	function ( $to ) {
		throw new Exception( 'redirect:' . $to );
	},
	PHP_INT_MAX
);
if ( $refuse ) {
	add_filter(
		'pre_wp_mail',
		function ( $answer, $atts ) {
			do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', 'Refused for the trial.', $atts ) );

			return false;
		},
		PHP_INT_MAX,
		2
	);
}
add_filter(
	'wp_die_handler',
	function () {
		return function () {
			throw new Exception( 'wp_die' );
		};
	},
	PHP_INT_MAX
);

try {
	WPCPM_Institution_Resend::handle_resend();
	$ended = 'returned';
} catch ( Exception $e ) {
	$ended = $e->getMessage();
}

$flash = get_user_meta( $presser, WPCPM_Flash::META, true );
$flash = is_array( $flash ) && isset( $flash[ WPCPM_Institution_Resend::FLASH ] ) ? $flash[ WPCPM_Institution_Resend::FLASH ] : array();

// Spent here, so a browser opened as the presser afterwards does not print it.
delete_user_meta( $presser, WPCPM_Flash::META );

WP_CLI::line(
	wp_json_encode(
		array(
			'ended'        => 0 === strpos( $ended, 'redirect:' ) ? 'redirect' : $ended,
			'outcome'      => isset( $flash['outcome'] ) ? $flash['outcome'] : '',
			'detail'       => isset( $flash['detail'] ) ? $flash['detail'] : '',
			'emails'       => $count_lines() - $before[0],
			'logged'       => $count_rows() - $before[1],
			'stamp_before' => $stamp_before,
			'stamp'        => WPCPM_Mail::last_invited( $student ),
			'history'      => $history() - $before[2],
			'spent_before' => $before[3],
			'spent'        => $spent(),
		)
	)
);
```

Then, each press printing one line of JSON:

```bash
P="$WP eval-file $TRIAL/trial-resend-press.php"
$P <member_a> <s1> bad-nonce      # the token: ended "wp_die", outcome "", emails 0, history 0
$P <member_b> <s1>                # another institution's member: "invite-refused"
$P <s2> <s2>                      # the student for themself: "invite-refused"
$P <member_a> <s4>                # not a student: "invite-error"
$P <member_a> <s3>                # signed in: "invite-signed-in"
$P <member_a> <s1>                # sent minutes ago: "invite-too-soon"
$WP eval 'update_user_meta( <s2>, WPCPM_Mail::STAMPS[ WPCPM_Roles::ROLE_STUDENT ], time() - 20 * MINUTE_IN_SECONDS );'
$P <member_a> <s2> mail-fails     # the host refuses the email: "invite-error"
$WP eval 'WPCPM_Ceiling::claim( WPCPM_Institution_Resend::ceiling_key( "<x>" ), 20, DAY_IN_SECONDS, 20 - WPCPM_Ceiling::count( WPCPM_Institution_Resend::ceiling_key( "<x>" ), DAY_IN_SECONDS ) );'
$WP eval 'update_user_meta( <s2>, WPCPM_Mail::STAMPS[ WPCPM_Roles::ROLE_STUDENT ], time() - 20 * MINUTE_IN_SECONDS );'
$P <member_a> <s2>                # the day's twenty: "invite-ceiling"
```

Expected: every line carries its own before and after. The seven refusals: `emails` 0, `logged` 0, `history` 0, `stamp` equal to `stamp_before` and `spent` equal to `spent_before` (3 until the claim, then 20). The refused email: `outcome` "invite-error", `emails` 1 and `logged` 1 (the email reached WordPress, and the Log's row reads **Failed**), `history` 0, `spent` equal to `spent_before` (3), and `stamp` above `stamp_before`: the stamp the send wrote stays. The policy's refusal never says whether the student exists.

- [ ] **Step 10: The emails, in the copy's own log and in the plugin's**

```bash
tail -n 4 "$SITE/wp-content/local-safety-mail.log"
$WP eval 'global $wpdb; foreach ( $wpdb->get_results( "SELECT user_id, template, status FROM {$wpdb->prefix}wpcpm_mail_log ORDER BY id DESC LIMIT 4" ) as $r ) { echo $r->user_id, " ", $r->template, " ", $r->status, "\n"; }'
```

(`wp db query` does not run on a Studio copy, which keeps its database in SQLite; the query goes through WordPress instead, and prints no address.)

Expected: the trial's three sends and the refused email (`s1` by the member, `s2`, `s1` by the Administrator, `s2` refused, newest last in the safety log), each to a plus-address of the test mailbox and each the student invitation; in the plugin's log the same four accounts, newest first, each `invite-student`: the refused one `failed`, the three sends `unconfirmed`, which the Log prints as **Not confirmed** (the safety plugin takes every email before it leaves). Read the same four in wp-admin, **WPCredits Program > Tools > Emails > Log**, as the Administrator. Nothing went to anybody else, and no Administrator was emailed. `cat "$SITE/wp-content/trial-php-errors.log"` holds no line (or the file does not exist).

- [ ] **Step 11: Put the copy back**

Type both removals out in full, the sign-in file's name copied from `signin.txt`: a removal built from a shell variable is refused by the session's safety check. Keep the error log first, when there is one.

```bash
cp ~/Studio/wpcredits-t5/wp-content/trial-php-errors.log "$TRIAL/" 2>/dev/null || echo "no error log"
rm -f ~/Studio/wpcredits-t5/wp-content/trial-php-errors.log
rm ~/Studio/wpcredits-t5/signin-<the six-byte hex from signin.txt>.php
rm ~/Studio/wpcredits-t5/wp-content/mu-plugins/wpcpm-trial-errors.php
$WP plugin install "$SNAP/plugin-before.zip" --force
~/.local/bin/studio site stop --path "$SITE" > /dev/null 2>&1
cp "$SNAP/ht.sqlite" "$SITE/wp-content/database/.ht.sqlite"
~/.local/bin/studio site start --path "$SITE" > /dev/null 2>&1
$WP plugin get wpcredits-program-manager --field=version
cat "$SNAP/plugin-version.txt"
$WP user list --search='resend-trial-*' --field=ID | wc -l
```

Expected: the version read back equals the one in `plugin-version.txt`; `0` trial accounts; Step 1's checks read the same again (the cron constant, the tokens blank, the safety plugin in place). The safety log keeps the trial's four lines, which name the test mailbox alone. Copy the captures, `ids.json` and the scripts' output into `$TRIAL` (the scratchpad is swept after three days), and report to the owner: the screenshots at 1280 and 375, the measured gaps, the three emails, the refused one and the seven refusals.

---

## After the plan

- **The release** goes through `~/Projects/wpcredits-release` on the owner's yes for it, never from this plan: read the live version first (1.122.29 expected), then check, build, scan, mirror and deploy. If the live site or the mirror's trunk holds a change the source does not, someone else's release, it is ported first and this one renumbered; the script's scan and mirror steps refuse until it is. Before the deploy, the owner adds the Privacy Notice clause of spec section 5 to page 654; the release waits for it.
- **After the deploy:** the owner publishes the institutions guide's paragraph (Task 5, step 8) on the handbook page, the guide page is republished from `docs/build/administrators.html`, and a reply on issue #243 saying it has shipped is drafted for the owner, posted only on their yes.
- **Not in this release** (spec 8): resending for a student with no account yet; a bulk resend from the roster; telling the program team. No screen lists an institution's audit rows yet; the Emails tool's Log is where an Administrator sees each send today.
