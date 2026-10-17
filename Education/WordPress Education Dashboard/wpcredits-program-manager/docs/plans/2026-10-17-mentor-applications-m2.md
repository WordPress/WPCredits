# Mentor applications on the site, M2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A selected applicant whose mentors' course shows on their WordPress.org profile, or for whom an Administrator presses **The course is completed**, becomes a mentor on wordpresseducation.org in one shared step: the account made or linked as the mentors sync makes it, one Mentors row in Airtable for the person (a new one, or the one that already holds them set back to Active), retried on the application when Airtable refuses, and one invitation that carries the welcome; the Mentor Status Checker reads the site's selected applications for it, its Slack message names who was made a mentor, and nothing of this runs on the live site until a person switches **Make mentors from applications** on at the launch.

**Architecture:** The account half of the mentors sync moves into `WPCPM_Mentor_Provision`, which the sync and the new `WPCPM_Mentor_Activation` both call, so there is one way to make a person a mentor (design, section 3, decision 6). `WPCPM_Mentor_Activation::activate()` holds the application's lock through 1.122.32's `WPCPM_Lock`, makes or links the account, files the application `active` with `EVENT_ACTIVATED` (which the feed already counts), queues `invite-mentor` (sent by 1.122.32's `WPCPM_Mail::send_invite_now()` under the account's lock), then writes the row through `WPCPM_Mentor_Airtable_Row`, which searches the Mentors table by the address first and shapes each cell by the column's type as the base's schema gives it; a refusal stays on the application with **Write the Airtable row again**, and the mentors sync leaves such an account alone. The checker's runner queues the site's selected applications ahead of Airtable's rows, which it keeps reading until a person ticks **Stop checking mentors in Airtable**, and the Waiting for review tab gains **The course is completed**, an **Activated** view with the retry and the invitation's fate, and **Send the acknowledgement**.

**Tech Stack:** PHP 7.4+ WordPress plugin `wpcredits-program-manager` (prefix `WPCPM_`); the no-WordPress suites `bin/test-*.php` (run from the plugin root with `php bin/test-x.php`; each ends `ALL PASS` and exits 0), the shared stand-ins `bin/stubs/mentor-applications.php` and, for the lock, 1.122.32's `bin/stubs/options-lock-wpdb.php`; `bin/check-standards.sh`; the template registry under `includes/mail/templates/`; the Docker copy in `~/Projects/wpcredits-docker`; the release script `~/Projects/wpcredits-release/release.py`.

**Spec:** `docs/specs/2026-10-16-mentor-applications-on-the-site-design.md`, phase M2 (section 12, second row), with section 3 (decisions 5 to 8 and 11) and sections 6 to 11 and 13. M1's plan, `docs/plans/2026-10-16-mentor-applications-m1.md`, with its closing note and "After the plan", records what M1 built. Where this plan departs from the design, the section "Departures from the design" below says so and quotes it. Read the spec before any task.

## For the owner

One line per decision. Each is the plan's default, the owner may change it, and the tasks are built on it.

1. **Live activation before the launch.** Default: none; **Make mentors from applications** stays off until the launch sitting, and the Docker copy proves the whole step with Airtable faked. Other: a throwaway Airtable base on the Studio copy, for real writes off the live site.
2. **The first real Airtable write.** Default: the check row: in the launch sitting, `wp wpcredits mentor_airtable_row --check` writes one synthetic Mentors row through the activation's client and column map, with a Status neither the sync nor the checker reads, once a search shows no row holds the check address; it reads the row back, deletes it, deletes a row a lost create left, and says which columns the live base took (launch step 5). Other: skip it and accept that the first real write is a real mentor's.
3. **A Mentors row that already holds the applicant's address.** Default: that row is set back to Active and linked, whatever Status it holds (Declined, Interested or Dropped out included), so one person keeps one row; this departs from decision 7. Other: a second row is created.
4. **A current mentor selected again.** Default: filed under Activated as already a mentor, nothing sent or written; this departs from section 6. Other: the invitation with the welcome goes again, cancelling the set-password link they hold.
5. **The welcome's private links.** Default: the mentor coordinator saves the whole edited Invitation: mentor before the deploy; from that save on every mentor invitation, the sync's bulk ones and every Resend to a long-standing mentor included, says "congratulations on completing" the course, and until the launch sitting a mentor Airtable makes Active whose account the sync creates gets two welcomes (Mentors 3's and the site's); after the deploy that edit shows as changed and must never be reset to the shipped text, which would send the bracketed line. Other: paste the links within three hours after a deploy made right after a mentors sync, with the invitation queue empty and no Resend pressed until then.
6. **The Slack line.** Default: a mentor made from an application is named as a promotion is, the line ending " - from an application on the site". Other: other words.
7. **Held applications.** Default: deleted after the rejected days (365), as shipped (M1 Ruling 18). Other: kept until a person decides them.

Settled by the controller, not open:

- **The launch is decoupled.** Once the site's form is live, Mentors 3 - Welcome is switched off and Make mentors from applications is switched on in one sitting, so no applicant on the site waits behind an Airtable row; a person ticks Stop checking mentors in Airtable later, once Airtable's own applicants are finished. The cost is the overlap between the two: the checker reads both sources daily, and a mentor Airtable makes Active on an account that already exists gets no welcome until somebody, told by the Slack message, presses Resend invite (launch step 7).
- **No activation is tried on the live site, not even a test one.** A live test activation would ping the channel through the Slack message, stay in the counts feed for good (a deleted row's counts are kept), and could link an account the test identity's handle matches. The launch proves the Airtable half alone, with the check row of owner item 2, which changes nothing on the site. The cost: the end-to-end activation is never run on the live site before the first real one; the Docker trial (Task 10) proves it with Airtable faked.

## Departures from the design

Each is a default or a ruling above; each is named in the task that builds it.

- **A row found by the address is adopted** (owner item 3). The design's decision 7: "The site never deletes a row and updates one only for a returning mentor". The plan also sets back to Active the one row whose Email is the applicant's, whatever its Status.
- **A current mentor is not invited again** (owner item 4). The design's section 6: "an account already holding a mentor record id is a returning mentor and is activated on that account and that Airtable row". The plan treats an account that already holds the Mentor role, the active flag and a record id as a mentor already, and sends it nothing.
- **Airtable's rows stay a source for a while** (the controller's ruling). The design's decision 5: "The Mentor Status Checker's source becomes the `selected` applications instead of Airtable's Vetted - positive rows". The plan reads both until a person ticks Stop checking mentors in Airtable.
- **The launch order** (the controller's ruling). The design's section 11 closes the Airtable form and switches Mentors 1, 2, 4 and 5 off (its step 4) before Mentors 3 (its step 5), then finishes Airtable's applications by hand (its step 6). The plan switches Mentors 3 off in the sitting that makes mentors from applications, right after the site's form is live, before the Airtable form closes.

## Global Constraints

- Release **1.122.33**: the plugin header, `WPCPM_VERSION`, `readme.txt` Stable tag, a `= 1.122.33 =` changelog entry above 1.122.32's, and `languages/wpcredits-program-manager.pot` (`bash bin/make-pot.sh`). Base: **main after the 1.122.32 merge** (60238424, 1.122.32 live), which Before Task 1, step 1, checks. Main stays on the 1.122 line. Branch `feature/mentor-applications-m2`. If main has moved past 1.122.32 by Task 9, take the next free 1.122 number wherever this plan says 1.122.33, the recording's file name in Task 8 included.
- PHP 7.4 compatible: `array()` syntax, no `match`, no named arguments, no typed properties, no `?->`, no trailing comma in a parameter list or a closure's `use` list, and no reliance on `usort()` keeping equal rows in place: order with `array_multisort()` and an ID tie-break, as `WPCPM_Mentor_Application::events()` does.
- WordPress coding standards: tabs, Yoda conditions, spaces inside parentheses, strict `in_array()`, no short ternary. `bash bin/check-standards.sh` ends with the warning count Before Task 1 records and no ` ERROR ` line; no task adds a warning.
- Every handler checks the login and the capability first, then the nonce keyed to the action and the row (the queue's `begin()`); every posted value goes through `wp_unslash()` and a sanitizer; every printed value is escaped; every string with a placeholder has a translator comment.
- No em dash and no en dash anywhere: code, comments, strings, docs, commit messages. Plain hyphens.
- US English in every string a person reads (`php bin/check-spelling.php`); full product names (Student Report Card, Mentor Report Card, Administrator Dashboard, Mentor Status Checker); "Administrators" for the people who run the program.
- New data is a site field (meta on the application or the account), never an Airtable column. In M2 the site writes to Airtable exactly two kinds of thing: one Mentors row created when an application is activated and no row holds the person, and the Status cell of the one row that holds them (a returning mentor's own row, or the row found by the applicant's address, owner item 3) set to the mentors sync's status, the `mentor_status` setting; and, at the launch only, the check row of owner item 2, one synthetic row with Status Inactive that `--check` creates and deletes. Nothing else is ever deleted there, no other cell is updated, and nothing reads or writes Airtable on a public request (design, section 1, items 4 and 5; section 3, decision 7; section 7).
- Nothing activates while **Make mentors from applications** is off, its default (owner item 1); the live site keeps it off until the launch sitting.
- The welcome's private links, the four student courses' group invitation links and the mentor resource folder's link, are never in code, a template default, a fixture, a document, a test, a commit or a message: the default carries one bracketed line where they go, and the mentor coordinator types them into the template on the site (design, sections 6 and 8). The only links the default welcome holds are the six public ones Task 4 lists. Private Slack channel names stay out as well.
- `bin/` and `docs/` are public (mirrored to WordPress/WPCredits): example.test addresses, invented names, synthetic WordPress.org usernames (`ada-example`), and invented Airtable ids made of the type's prefix and uppercase letters and digits only (`recNEWMENTOR00001`, `tblMENTORS0000001`). `php bin/test-fixtures.php` walks `bin/`, `includes/` and `docs/`, `docs/specs` and `docs/plans` included. What only a trial needs (the copy's own account IDs, its cookie recipe, the faked Airtable) lives in the trial kit under `.superpowers/sdd/2026-10-17-mentor-applications-m2/`, never in a mirrored file.
- Name the role, never the person: the mentor coordinator, the owner, Administrators, a mentor. Nothing under `docs/` or in a mirrored commit message holds a word on the release scan's private list (the scan reads its own list; Before Task 1, step 4, runs it over this plan).
- The feed answers counts, never a name, an address, a username, a reference or a note.
- Commits: each message starts `Mentor applications:` and ends, after a blank line, with the Co-Authored-By trailer the implementer's session names for its model; a review's fix commits stay commits of their own (M1 Ruling 4).
- Nothing is deployed, mirrored, posted or switched on the live site without the owner's explicit yes for that step; the live version is read first; every test email on the live site goes to the owner's one test mailbox; nothing on the live site prints a real applicant's answers into a session. A step that saves a settings scope, a Settings tab or a tool's own Settings section, or patches its keys, reads that whole scope back afterwards: a save from a page loaded before another change re-saves every setting the page drew.
- The trial runs on the Docker copy (http://localhost:8900) inside a snapshot, or on the Studio copy at port 8883 inside one; the copy's mail never leaves it.

## Review Focus

1. **A person the site or the Mentors table already knows applies and is made a mentor.** Their address, WordPress.org username or stored profile belongs to an account already (a former mentor whose row is Inactive or Dropped out, a current mentor, a student, an Administrator), or a Mentors row already holds their address. One account and one row per person: a former mentor on their own account and their own row set back to Active; a row found by the address set back to Active and linked; a current mentor filed as already a mentor with nothing sent; an Administrator's roles never touched and no invitation sent to them; the account an activation found is the one provisioned, never a second. Pinned in Task 3, step 1: "a former mentor is made a mentor again on their own account and their own row", "a current mentor selected again is filed as already a mentor", "a row that already holds the address is set back to Active and linked", "an Administrator who applied keeps their roles and is sent no invitation", "a student's account is linked, not duplicated", "an account found by its stored profile is the one made a mentor"; Task 1, step 1: "find_account() takes the address, then the username, then the stored profile", "an account the caller found is linked as given".
2. **Airtable says no, or answers and the request dies**: a bad token, a missing column, a value a column refuses, a linked table that cannot be read, a row deleted in Airtable, the network, or no connection at all. The mentor is still made and invited, the refusal is on the application in Airtable's words, Write the Airtable row again is offered until it succeeds, and no path makes a second row. The sync never unmakes the mentor meanwhile, and stops waiting once it links the row itself. Pinned in Task 3, step 1 ("Airtable refusing the row keeps nobody waiting", "a retry finds the row a request that died had made, by its address, and creates nothing", "the mentors sync leaves an account alone while its row is not written, and one made a mentor during its run", "once the sync links the account to a row, it is no longer one with no row"), Task 2, step 1 ("a linked table that cannot be read stops the write"), and Task 6, step 1 ("Write the Airtable row again on an activated application").
3. **Two things at once on one application or one account**: the daily check and a press, two Administrators pressing, the lapse clock and an activation, the queue's welcome and a Resend. One account, one row, one invitation; the second is told it is in hand. Pinned in Task 3, step 1 ("an activation already in hand is refused, and nothing is made", "the lapse clock leaves an application whose activation is in hand"); the invitation side is 1.122.32's, pinned in `bin/test-mail.php` ("a queued account whose lock another request holds is passed over, as one inside the fifteen minutes is").
4. **Answers typed with quotes, an ampersand, a "<" or letters past ASCII, and choices the base does not hold.** They reach Airtable as typed, never as `&lt;` or `&amp;`; a language or a contribution area the Mentors table does not hold is left out by name and the rest of the row is written. Pinned in Task 2, step 1: "the row an application writes", "a name typed with quotes, an ampersand and a "<" is sent as typed", "a language the base does not hold is left out by name, and the others are linked".
5. **A welcome that never arrives, and the weeks around the launch.** A refused or skipped invitation leaves no stamp, so the Activated view says what happened since the activation and never shows an older invitation as the welcome; nothing activates on the live site until Make mentors from applications is on; Airtable's Vetted - positive mentors go on being promoted until Stop checking mentors in Airtable is ticked, and a stale save of the checker's screen cannot stop that. Pinned in Task 6, step 1 ("an invitation older than the activation is never read as the welcome", "a refused welcome reads as refused, with what to do"), Task 3, step 1 ("with Make mentors from applications off, nothing is made"), Task 5, step 1 ("an application whose course shows waits while Make mentors from applications is off", "Airtable's mentors are checked after the site's until Stop checking mentors in Airtable is ticked") and `bin/test-settings.php` ("a save of that form without it keeps Airtable's mentors checked").

---

## Files at a glance

| File | What it is responsible for | Task |
| --- | --- | --- |
| `includes/modules/class-wpcpm-mentor-provision.php` (new) | Making or linking a mentor's account: the mentors sync's rule, shared; the two account keys an activation leaves | 1 |
| `includes/modules/class-wpcpm-mentors-sync.php` | Hands each mentor to the shared step; `fields()` names five more Mentors columns; its revoke leaves an account an activation holds | 1, 2, 3 |
| `includes/modules/class-wpcpm-mentor-airtable-row.php` (new) | The row an activation writes: the search by address, its cells by column type, the linked names, the write | 2 |
| `includes/modules/class-wpcpm-mentor-activation.php` (new) | The shared activation step, the retry, the switch, the mentors with no row yet, the Activated list | 3 |
| `includes/modules/class-wpcpm-mentor-application.php` | The record's lock name, new meta, events and readers; the lapse clock's and retention's care for an activation; the feed's count per application | 2, 3, 5, 6, 7 |
| `includes/class-wpcpm-settings.php`, `includes/class-wpcpm-settings-screen.php` | Make mentors from applications; the Invitation emails fold; Stop checking mentors in Airtable | 3, 5 |
| `includes/mail/templates/invitations.php`, `includes/class-wpcpm-mail.php` | The welcome after the mentor's invitation, and its Reply-To | 4 |
| `includes/tools/class-wpcpm-mentor-checker-runner.php`, `class-wpcpm-mentor-checker.php`, `class-wpcpm-mentor-checker-slack.php` | The checker reads the site, the Airtable switch, the Slack line | 5 |
| `includes/modules/class-wpcpm-mentor-application-queue.php`, `includes/modules/class-wpcpm-mentors.php` | The course is completed, the Activated view, its invitation line and retry, Send the acknowledgement; the Sync tab's line | 6 |
| `includes/class-wpcpm-cli.php` | `mentor_airtable_row`; `check_mentors` without the Airtable connection | 2, 5 |
| `bin/test-mentor-provision.php`, `bin/test-mentor-airtable-row.php`, `bin/test-mentor-activation.php`, `bin/test-mentor-activation-text.php`, `bin/fixtures/mentor-activation-1-122-33.json` (new) | The shared step, the row, the activation, and the emails word for word | 1, 2, 3, 8 |
| `bin/stubs/mentor-applications.php` | Gains what accounts need: `wp_insert_user()`, roles written back, `get_users()` by meta value and EXISTS; nothing an M1 suite reads changes | 1 |

The loader and the uninstaller (`wpcredits-program-manager.php`, `uninstall.php`) `require_once` the three new classes after the mentors sync and the application record; the Mentors module's `boot()` initializes nothing new (the activation adds no hook). Every M2 suite that reaches a lock loads the real `includes/class-wpcpm-lock.php` over 1.122.32's `bin/stubs/options-lock-wpdb.php`, which runs the lock's statements on `$GLOBALS['opts']`, so the lock answers exactly as on the site; a held lock is planted as a row of `$GLOBALS['opts']` holding a Unix time as digits.

## The checks every task ends with

Run from the plugin root, after the task's own suites pass:

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done; echo battery done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
php bin/check-temp-litter.php | tail -2
bash bin/check-standards.sh 2>&1 | tail -1
```

Expected: `battery done` with no `FAIL` line; references `- all resolve`; spelling `US English throughout.`; `0 dead`; temp litter: every script exited 0 and the temp directory as found (the check names entries other programs made while it ran, such as `com.google.Chrome.*` or `docker_cli_*`; run it again, and only the battery's own come back under the same names); and the standards line Before Task 1 records. Before Task 1, step 2, records the counts on the base; they grow by what each task adds (a new class, a new suite, three new handlers in Task 6).

Unlike M1's, this plan was not proved on a scratch branch: it gives each task's interfaces, its tests and the values the design pins, and the implementer writes the bodies. Each test step names the suite's own helpers as they stand on main and lists every helper or stand-in it adds. `ck( $label, $got, $want )` compares with `===` and prints the label in every suite; `an_account( $id, $name, $email, $roles, $meta )` is the shared stand-in's, whose account takes a login made of the name's letters, lowercased.

---

## Before Task 1: the base, the branch and the documents

- [ ] **Step 1: Check that 1.122.32 is in main, and read what M2 takes from it**

```bash
cd ~/GitHub/wpcredits-program-manager
git checkout main
grep -n "^ \* Version:\|define( 'WPCPM_VERSION'" wpcredits-program-manager.php
grep -n "^Stable tag:\|^= 1.122.32 =" readme.txt
grep -n "public static function take\|public static function release\|public static function until" includes/class-wpcpm-lock.php
grep -n "const INVITE_LOCK \|public static function send_invite_now\|public static function invite_refused_message\|send_invite_now( \$user )" includes/class-wpcpm-mail.php
ls bin/stubs/options-lock-wpdb.php
```

Expected: `1.122.32` on both version lines and the Stable tag, and its changelog heading; then the names M2 takes, as main holds them at 60238424:

- `WPCPM_Lock::take( $name, $ttl )`: the token, which is the time the lock runs to as digits only, or `''` (never false) while another request holds it; `WPCPM_Lock::release( $name, $token )`: deletes the row only while it holds exactly that token, answering whether it did; `WPCPM_Lock::until( $name )`: when a held lock runs out, or 0. Each is one SQL statement through `$wpdb`, never the options API or the object cache. The caller takes the lock, works inside `try` and lets go in `finally`. M2's activation lock (Task 3) is a use of it under its own name prefix, tested by `'' === $token`.
- `WPCPM_Mail::send_invite_now( WP_User $user )`, which every invitation route already goes through under the account's lock, the queue's `drain_queue()` included. A refused email writes no stamp (the account keeps its earlier one, or none) and answers `wpcpm_invite_refused`, whose outcome is `invite-mail-refused` and whose sentence is `WPCPM_Mail::invite_refused_message( 'administrator' )` (or `'member'`); the queue counts it as not sent and does not queue it again. So M2 adds no lock to the queue, and the Activated view (Task 6) is the only place a lost welcome shows: it reads the mail log and the stamp from the activation's time on.
- `bin/stubs/options-lock-wpdb.php`, the suites' `$wpdb` for the real lock.

If the version is not 1.122.32, stop and tell the owner.

- [ ] **Step 2: Branch, and record the base's checks**

Branch in a worktree (superpowers:using-git-worktrees): `git worktree add .worktrees/mentor-applications-m2 -b feature/mentor-applications-m2 main`. In it, run the checks every task ends with and `php bin/test-fixtures.php`, and write their last lines and the suite count in the ledger: these are the counts every task's checks compare with.

- [ ] **Step 3: Commit this plan with the design's status line**

The design's first paragraph says "M2 is not planned yet"; make it "M2 is planned in docs/plans/2026-10-17-mentor-applications-m2.md". If the branch does not hold this plan, copy it there. Commit both alone: `git add docs/plans/2026-10-17-mentor-applications-m2.md docs/specs/2026-10-16-mentor-applications-on-the-site-design.md`, message `Mentor applications: the M2 plan` and the trailer.

- [ ] **Step 4: Check that the plan names nobody and holds no word of the scan's private list**

```bash
grep -n -e $'\xe2\x80\x94' -e $'\xe2\x80\x93' docs/plans/2026-10-17-mentor-applications-m2.md || echo clean
php -r 'require "bin/refused-strings.php"; var_dump( wpcpm_refused_found( file_get_contents( "docs/plans/2026-10-17-mentor-applications-m2.md" ) ) );'
python3 -c 'import re,sys,tomllib; w=tomllib.load(open(sys.argv[1],"rb"))["scan"]["private_words"]; t=open(sys.argv[2],encoding="utf-8").read(); print(sum(len(re.findall(p,t,re.I)) for p in w))' ~/Projects/wpcredits-release/config.toml docs/plans/2026-10-17-mentor-applications-m2.md
php bin/test-fixtures.php | tail -1
```

Expected: `clean`, `array(0) {}`, `0`, `ALL PASS`. The third command prints a count and never a word of the list.

---

### Task 1: The shared provisioning step

The mentors sync's account half, moved into one class both the sync and the activation call (design, section 3, decision 6), with what the activation needs beside it: a mentor with no record id yet, which is what an application is until its row exists; a lookup by the stored WordPress.org profile, as the form recognizes a mentor (`known_mentor()`); an account a caller already found, linked as given, so two lookups cannot make two accounts; and the account key an activation leaves while its row is not written, cleared once the sync links the account to an Active row.

**Files:**
- Create: `includes/modules/class-wpcpm-mentor-provision.php`
- Modify: `includes/modules/class-wpcpm-mentors-sync.php` (`provision_mentor()`, `link_existing_user()`, `create_mentor_user()` and `find_by_record_id()`, at 1.122.31's lines 1255 to 1442 and 1871 to 1885); `bin/test-students-sync.php` (its three reflection checks of the sync's writers, at 1.122.31's lines 1810 to 1836, now call them on the new class); `bin/stubs/mentor-applications.php` (`get_users()` by `meta_value`, and by a `meta_query` of `EXISTS`; `WP_User::add_role()`, `remove_role()`, `set_role()` writing the roles back; `wp_insert_user()` and `wp_update_user()`); `wpcredits-program-manager.php` and `uninstall.php` (the `require_once`, after the mentors sync)
- Test: `bin/test-mentor-provision.php` (new), `bin/test-students-sync.php`; run `bin/test-mentors-screen.php`, `bin/test-sponsors-sync.php`, `bin/test-provision-conflict.php` and every M1 suite on the shared stand-in

**Interfaces:**
- Consumes: `WPCPM_Mentors_Sync::wporg_username()`, `META_RECORD_ID`, `META_PROFILE`, `META_ACTIVE`, `empty_stats()`; `WPCPM_Roles::insert_user()`, `ROLE_MENTOR`, `ROLE_ADMIN`; `WPCPM_Mail::queue_invite()`; `WPCPM_Settings::get_value( 'send_welcome_email' )`.
- Produces:
  - `WPCPM_Mentor_Provision::USER_ROW_PENDING = 'wpcpm_mentor_row_pending'` (user meta: 1 while an activation's row is pending or refused) and `USER_ACTIVATED_AT = 'wpcpm_mentor_activated_at'` (user meta: when the account was last made a mentor from an application); the activation (Task 3) writes them, the sync's revoke (Task 3) reads them, and the uninstaller deletes both.
  - `WPCPM_Mentor_Provision::provision( array $mentor, array &$report, $found = null ): int`. `$mentor`: `record_id` (string, `''` for none yet), `name`, `profile`, `email`. `$report` gains `notices` (string[]) and `stats` (the keys of `WPCPM_Mentors_Sync::empty_stats()`) where it lacks them, and both are added to as the sync's state was. `$found`, a `WP_User` the caller found, is linked as given (the claimed-record check still applies) and no lookup is made; otherwise the lookup is the sync's: the record id, the address, the username. With `record_id` `''`: no lookup by record id, and `META_RECORD_ID` is not written. Linking with a non-empty record id deletes `USER_ROW_PENDING`. Answers the user ID, or 0.
  - `WPCPM_Mentor_Provision::find_account( $email, $login, $handle = '' ): array` with `user` (`WP_User` or null) and `how` (`email`, `login`, `profile` or `''`): the address, then the username, then, with a handle given, the account whose `META_PROFILE` reduces to it through `wporg_username()`. `provision()` asks it without a handle, so the sync matches as it always has.
  - `WPCPM_Mentor_Provision::find_by_record_id( $record_id ): WP_User|null`, null for `''`.
  - `link_existing_user()` and `create_mentor_user()` stay private on the new class with their signatures, `$state` renamed `$report`.
  - `WPCPM_Mentors_Sync::provision_mentor()` stays private and is one line: `return WPCPM_Mentor_Provision::provision( $mentor, $state );`.

- [ ] **Step 1: Write the failing test**

`bin/test-mentor-provision.php` runs on `bin/stubs/mentor-applications.php`, extended as the Files list says (each addition answers only arguments the M1 suites never pass, so their checks are unchanged), with the real `WPCPM_Roles`, `WPCPM_Mentors_Sync` and the new class, and stands in `WPCPM_Mail::queue_invite()` (appending to `$GLOBALS['queued']`, once per account, as the real one does) and `WPCPM_Settings::get_value()` (from `$GLOBALS['settings']`). Helpers the suite adds: `fresh( array $settings = array() )` empties the users, user meta, queue and settings and sets `$GLOBALS['insert_error']` back to null; `mentor( $record )` is `array( 'record_id' => $record, 'name' => 'Ada Example', 'profile' => 'https://profiles.wordpress.org/ada-example/', 'email' => 'ada@example.test' )`; `account( $id, $login, $email, array $roles, array $meta = array() )` plants an account through `an_account()` and then sets its `user_login` to `$login`, since the stand-in's account takes its login from the name; `roles( $id )`, `meta( $id, $key )`, `login( $id )`, `email( $id )`, `display( $id )`, `url( $id )` read the stand-ins.

```php
echo "\n=== A new mentor ===\n";
fresh( array( 'send_welcome_email' => true ) );
$report = array();
$id     = WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00001' ), $report );
ck( 'a new mentor gets an account named after their WordPress.org username, with the address, the name, the profile as its website and the Mentor role',
	array( login( $id ), email( $id ), display( $id ), url( $id ), roles( $id ) ),
	array( 'ada-example', 'ada@example.test', 'Ada Example', 'https://profiles.wordpress.org/ada-example/', array( 'wpcpm_mentor' ) ) );
ck( 'and the three mentor meta the sync writes',
	array( meta( $id, 'wpcpm_mentor_record_id' ), meta( $id, 'wpcpm_mentor_profile' ), meta( $id, 'wpcpm_mentor_active' ) ),
	array( 'recNEWMENTOR00001', 'https://profiles.wordpress.org/ada-example/', 1 ) );
ck( 'with invitation emails on, the invitation is queued and counted', array( $GLOBALS['queued'], $report['stats']['created'], $report['stats']['invited'] ), array( array( $id ), 1, 1 ) );

fresh( array( 'send_welcome_email' => false ) );
$report = array();
WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00001' ), $report );
ck( 'with them off, nothing is queued', array( $GLOBALS['queued'], $report['stats']['invited'] ), array( array(), 0 ) );

echo "\n=== An account that exists ===\n";
fresh();
account( 41, 'ada-example', 'old@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recNEWMENTOR00001', WPCPM_Mentor_Provision::USER_ROW_PENDING => 1 ) );
$report = array();
ck( 'found by its record id, an account is updated, not linked again, and linking it to its row clears the flag an activation left',
	array( WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00001' ), $report ), $report['stats']['updated'], $report['stats']['linked'], meta( 41, WPCPM_Mentor_Provision::USER_ROW_PENDING ) ),
	array( 41, 1, 0, '' ) );

fresh();
account( 42, 'someone-else', 'ada@example.test', array( 'subscriber' ) );
$report = array();
ck( 'found by its address, an account with no record is linked and gains the Mentor role beside the one it had, with a notice',
	array( WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00001' ), $report ), roles( 42 ), $report['stats']['linked'], $report['notices'] ),
	array( 42, array( 'subscriber', 'wpcpm_mentor' ), 1, array( 'Added the Mentor role to the existing account "someone-else" (Ada Example).' ) ) );

fresh();
account( 43, 'ada-example', 'other@example.test', array( 'wpcpm_mentor' ) );
$report = array();
WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00001' ), $report );
ck( 'found by its username alone, it is linked with the notice that asks a person to confirm',
	$report['notices'], array( 'Linked mentor record to the pre-existing account "ada-example" (Ada Example) by username. Confirm it is the same person.' ) );

fresh();
account( 44, 'ada-example', 'ada@example.test', array( 'administrator' ) );
$report = array();
ck( 'an Administrator\'s account is linked and keeps its roles', array( WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00001' ), $report ), roles( 44 ) ), array( 44, array( 'administrator' ) ) );

fresh();
account( 45, 'ada-example', 'ada@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recRETURNING00001' ) );
$report = array();
ck( 'an account linked to another mentor record is refused, counted as a conflict and named',
	array( WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00001' ), $report ), $report['stats']['conflicts'], $report['notices'] ),
	array( 0, 1, array( 'Skipped Ada Example - the account "ada-example" is already linked to a different Airtable mentor record. Resolve the duplicate in Airtable.' ) ) );

fresh();
account( 50, 'renamed-example', 'renamed@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recRENAMED0000001' ) );
$report = array();
ck( 'an account the caller found is linked as given, and no lookup by address or username makes another',
	array( WPCPM_Mentor_Provision::provision( mentor( 'recRENAMED0000001' ), $report, get_userdata( 50 ) ), count( $GLOBALS['users'] ), $report['stats']['created'] ),
	array( 50, 1, 0 ) );

echo "\n=== What is refused ===\n";
fresh();
$report = array();
WPCPM_Mentor_Provision::provision( array_merge( mentor( 'recNEWMENTOR00001' ), array( 'profile' => 'https://example.test/' ) ), $report );
ck( 'a profile with no WordPress.org username in it is skipped, quoted', array( $report['stats']['skipped'], $report['notices'] ), array( 1, array( 'Skipped Ada Example - no WordPress.org username could be read from "https://example.test/".' ) ) );
$report = array();
WPCPM_Mentor_Provision::provision( array_merge( mentor( 'recNEWMENTOR00001' ), array( 'email' => 'not-an-address' ) ), $report );
ck( 'no valid address is skipped', $report['notices'], array( 'Skipped Ada Example - Airtable has no valid email address, and WordPress cannot create an account without one.' ) );
$GLOBALS['insert_error'] = new WP_Error( 'existing_user_login', 'Sorry, that username already exists!' );
$report                  = array();
ck( 'WordPress refusing the account is skipped with its words', array( WPCPM_Mentor_Provision::provision( mentor( 'recNEWMENTOR00001' ), $report ), $report['notices'] ), array( 0, array( 'Could not create the account "ada-example": Sorry, that username already exists!' ) ) );

echo "\n=== A mentor with no record id yet ===\n";
fresh();
account( 46, 'decoy-example', 'decoy@example.test', array( 'subscriber' ), array( 'wpcpm_mentor_record_id' => '' ) );
account( 47, 'someone-else', 'ada@example.test', array( 'subscriber' ) );
$report = array();
$id     = WPCPM_Mentor_Provision::provision( mentor( '' ), $report );
ck( 'with no record id, the address finds the account, no account is found by an empty record id, and no record id is written',
	array( $id, roles( 46 ), array_key_exists( 'wpcpm_mentor_record_id', $GLOBALS['umeta'][47] ), meta( 47, 'wpcpm_mentor_active' ) ),
	array( 47, array( 'subscriber' ), false, 1 ) );
fresh();
$report = array();
$id     = WPCPM_Mentor_Provision::provision( mentor( '' ), $report );
ck( 'and a new account made with no record id carries none', array( login( $id ), array_key_exists( 'wpcpm_mentor_record_id', $GLOBALS['umeta'][ $id ] ) ), array( 'ada-example', false ) );
fresh();
account( 48, 'ada-example', 'ada@example.test', array(), array( 'wpcpm_mentor_record_id' => 'recRETURNING00001' ) );
$report = array();
ck( 'and an account holding a record id is a conflict for a mentor with none: the activation passes that record id instead', array( WPCPM_Mentor_Provision::provision( mentor( '' ), $report ), $report['stats']['conflicts'] ), array( 0, 1 ) );
account( 49, 'renamed-example', 'renamed@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recRENAMED0000001', 'wpcpm_mentor_profile' => 'https://profiles.wordpress.org/zoe-example/' ) );
ck( 'find_account() takes the address, then the username, then the stored profile, and says which',
	array(
		WPCPM_Mentor_Provision::find_account( 'ada@example.test', 'nobody' )['how'],
		WPCPM_Mentor_Provision::find_account( 'none@example.test', 'ada-example' )['how'],
		WPCPM_Mentor_Provision::find_account( 'none@example.test', 'nobody', 'zoe-example' )['user']->ID,
		WPCPM_Mentor_Provision::find_account( 'none@example.test', 'nobody', 'zoe-example' )['how'],
		WPCPM_Mentor_Provision::find_account( 'none@example.test', 'nobody' ),
	),
	array( 'email', 'login', 49, 'profile', array( 'user' => null, 'how' => '' ) ) );

echo "\n=== One way to make a mentor ===\n";
$sync = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentors-sync.php' );
ck( 'the mentors sync makes no account of its own any more: it hands each mentor to the shared step',
	array( substr_count( $sync, 'WPCPM_Mentor_Provision::provision(' ), substr_count( $sync, 'insert_user(' ), substr_count( $sync, 'function link_existing_user' ), substr_count( $sync, 'function create_mentor_user' ), substr_count( $sync, 'function find_by_record_id' ) ),
	array( 1, 0, 0, 0, 0 ) );
```

In `bin/test-students-sync.php`, the section "What the base holds is what the mentors sync keeps" calls `create_mentor_user` and `link_existing_user` through `new ReflectionMethod( 'WPCPM_Mentor_Provision', $method )` in place of `WPCPM_Mentors_Sync`; its three checks (the profile kept exactly on a created and on a linked account, and the address handed to core slashed once) keep their labels and values, and its comment says the writers moved to the shared step in 1.122.33.

- [ ] **Step 2: Run it to make sure it fails**

Run: `php bin/test-mentor-provision.php; php bin/test-students-sync.php`
Expected: a fatal error, `Class "WPCPM_Mentor_Provision" not found`, from each.

- [ ] **Step 3: Implement `WPCPM_Mentor_Provision` and hand the sync's provisioning to it**

Move the four methods' bodies as they stand on main (their notices word for word, their stats keys, `add_role()` and never `set_role()`, the slashed profile meta, the `wp_update_user()` of `user_url`, the queued invitation under `send_welcome_email`), renaming `$state` to `$report`. `provision()` fills `notices` and `stats` where `$report` lacks them, links `$found` when given, skips `find_by_record_id()` for `''`, and otherwise uses `find_account( $email, $login )` for the rest of its lookup; both writers write `META_RECORD_ID` only for a non-empty record id, and `link_existing_user()` deletes `USER_ROW_PENDING` when it writes one. `find_account()`'s profile step reads the accounts holding `META_PROFILE` (`get_users( array( 'meta_key' => META_PROFILE, 'fields' => 'all' ) )`), as `known_mentor()` does. The class docblock says the account half moved here from the sync in 1.122.33 so that the sync and the activation make a mentor one way. Add the `require_once` to the loader and the uninstaller after `class-wpcpm-mentors-sync.php`, and the uninstaller's deletion of the two user meta keys.

- [ ] **Step 4: Run the tests to make sure they pass**

Run: `php bin/test-mentor-provision.php; php bin/test-students-sync.php; php bin/test-mentors-screen.php; php bin/test-sponsors-sync.php; php bin/test-provision-conflict.php; for f in bin/test-mentor-application*.php; do php "$f" | tail -1; done`
Expected: `ALL PASS` from each, exit 0.

- [ ] **Step 5: The checks every task ends with, then commit**

```bash
git add includes/modules/class-wpcpm-mentor-provision.php includes/modules/class-wpcpm-mentors-sync.php wpcredits-program-manager.php uninstall.php bin/test-mentor-provision.php bin/test-students-sync.php bin/stubs/mentor-applications.php
git commit -m "Mentor applications: the mentors sync makes and links accounts through one shared step, which an application will use too" -m "<the Co-Authored-By trailer>"
```

---

### Task 2: The Mentors row an activation writes

One row per newly active mentor, with Full Name, WordPress profile, Email, Status and the answers whose columns exist, country and languages as links where the names match (design, section 3, decision 7); a row that already holds the applicant's address is set back to Active and linked instead, whatever its Status (owner item 3, a departure from decision 7). The column types are read from the base's schema, because `create_records()` sends no `typecast` and a value of the wrong kind is a refusal of the whole row; and a linked table that cannot be read stops the write rather than leaving a row without its links for good, since the site never updates a row afterwards.

**Files:**
- Create: `includes/modules/class-wpcpm-mentor-airtable-row.php`
- Modify: `includes/modules/class-wpcpm-mentors-sync.php` (`fields()`, five keys), `includes/modules/class-wpcpm-mentor-application.php` (`by_reference()`), `includes/class-wpcpm-cli.php` (`mentor_airtable_row`), `wpcredits-program-manager.php` and `uninstall.php` (the `require_once`; the uninstaller deletes `LINKS_TRANSIENT`)
- Test: `bin/test-mentor-airtable-row.php` (new), `bin/test-cli.php`, `bin/test-uninstall.php`; run `bin/test-students-sync.php`, which holds every `mentor_*` name of `fields()` to `bin/fixtures/mentors-table-fields.json`, and `bin/test-institution-roster.php`, which parses `fields()` from the source

**Interfaces:**
- Consumes: `WPCPM_Mentors_Sync::fields()`, `META_RECORD_ID`; `WPCPM_Airtable::cached_schema()` (which answers `array( 'schema' => ..., 'age' => ... )` or a `WP_Error`), `table_in()`, `fetch_page()`, `fetch_all()`, `formula_in()`, `create_records()`, `update_records()`, `get_record()`, `delete_records()`, `is_record_id()`, `flatten()`; `WPCPM_Mentor_Application_Form::countries()` and `teams()`; `WPCPM_Settings::get()` (`mentors_table`, `mentor_status`); `WPCPM_Mentor_Application::fields_of()`, `name_of()`, `email_of()`, `handle_of()`, `profile_url()`, `reference_of()`; `WPCPM_Typed_Text::mail_text()`.
- Produces:
  - Five keys in `WPCPM_Mentors_Sync::fields()`, after `mentor_expertise`: `'mentor_country' => 'Country'`, `'mentor_languages' => 'Languages'`, `'mentor_hours' => 'Available hours per week'`, `'mentor_teacher' => 'Teacher from a participating institution?'`, `'mentor_anything' => 'Anything else you’d like us to know?'` (a right single quotation mark, U+2019, as the table spells it and the fixture records).
  - `WPCPM_Mentor_Airtable_Row::ANSWERS`: `array( 'country' => 'mentor_country', 'languages' => 'mentor_languages', 'expertise' => 'mentor_expertise', 'hours' => 'mentor_hours', 'teacher' => 'mentor_teacher', 'anything' => 'mentor_anything' )`, answer key to `fields()` key, in the row's order.
  - `WPCPM_Mentor_Airtable_Row::LINKS_TRANSIENT = 'wpcpm_mapp_link_names'`, the linked tables' names by table id, kept a day.
  - `cells( WP_Post $post, $schema, callable $names ): array` with `cells` (column name to value) and `left_out` (column name to `array( 'why' => string, 'values' => string[] )`). `$schema` is the bare schema, table id to table, as `fetch_schema()` answers it and as `cached_schema()` holds it under `schema`, or null when it cannot be read; a schema without the Mentors table is read as none. `$names( $table_id, $primary )` answers lowercased name to record id, or a `WP_Error`.
  - `link_names( WPCPM_Airtable $airtable, $table_id, $primary ): array|WP_Error`.
  - `preview( WP_Post $post, WPCPM_Airtable $airtable ): array`: `cells()` with `cached_schema()['schema']` (null for an error) and the names read through the client; reads, never a write.
  - `find_by_email( $email, WPCPM_Airtable $airtable ): string[]|WP_Error`, the record ids of the Mentors rows whose Email is the address, compared trimmed and case-insensitively (`formula_in( fields()['mentor_email'], array( $email ), true, true )`).
  - `write( WP_Post $post, $record_id, $user_id, WPCPM_Airtable $airtable ): array` with `ok` (bool), `how` (`created`, `reactivated` or `adopted`), `record`, `error` (Airtable's words or the site's, `''` on success) and `left_out`.
  - `left_out_sentence( array $left_out ): string`, `''` for none.
  - `WPCPM_Mentor_Application::by_reference( $reference ): WP_Post|null`.
  - `cells_for( array $answers, $schema, callable $names ): array`, what `cells()` does from an answers array (the keys `name`, `email`, `profile`, `country`, `languages`, `expertise`, `hours`, `teacher`, `anything`, as the form stores them), which `cells( WP_Post )` calls with the application's.
  - `link_names( WPCPM_Airtable $airtable, $table_id, $primary, $keep = true )`: with `$keep` false the names are read and not kept.
  - `check_answers( $email ): array`, the synthetic mentor: the name `Mentor row check`, the address given, the username `mentor-row-check`, the first country `WPCPM_Mentor_Application_Form::countries()` offers, `array( 'English' )`, the first of `WPCPM_Mentor_Application_Form::teams()`, `'2.5'`, `No`, and the free text `A check row written by the site and deleted at once.`.
  - `CHECK_STATUS = 'Inactive'`, the Status the check row is written with, by this rule, checked before anything is written: a choice of the Status column in the schema; neither the mentors sync's `mentor_status` nor the checker's `checker_source_status` or `checker_target_status`, so neither reads the row; and none that a Mentors automation the owner lists in launch step 4 fires on (the owner's read, since the site cannot see automations). Inactive meets it on the base as the fields fixture records it; a Status that breaks it stops the check with nothing written.
  - `check_row( $email, WPCPM_Airtable $airtable ): array` with `written` (the columns sent), `record`, `kept`, `missing`, `stops` (the sentences that stop the launch sitting), `notes` (the sentences that only inform), `found_before` (the ids of rows that held the address before the create), `cleaned` (the ids deleted after a create error), `deleted` (bool), `error` (the base's answer to the create, or the site's, `''` when it wrote), `read_error` and `delete_error`. In order:
    1. An address `is_email()` refuses: `error` "The check needs an email address.", nothing written.
    2. `cells_for( check_answers( $email ) )` with the names read and not kept; an `unread` linked table, as `write()` has it: `error`, nothing written.
    3. The schema: no schema or no Mentors table, the activation's `mentor_status` not a choice of the Status column (confirmed from the schema, never by writing it: "The activation's status, %s, is not a choice of the Status column."), or `CHECK_STATUS` breaking its rule ("The check row's status, %s, is one the mentors sync, the checker or an automation reads, or no choice of the Status column."): `error`, nothing written.
    4. `find_by_email( $email )`: a refusal, `error`; any row, `found_before` its ids and `error` "Rows with the check address are already in the Mentors table: %s. Delete them by hand, then check again.", nothing written.
    5. `create_records()` with the cells, Status `CHECK_STATUS`. A refusal: `find_by_email( $email )` again, every row it finds deleted (`cleaned`), and `error` the create's answer followed by "The search after it found %s and deleted it." or "The search after it found nothing, so nothing was stored and nothing was deleted."
    6. Written: `get_record()` reads it back, and `delete_records()` deletes it in a `finally`, whatever the read answered.
    7. `kept`: each sent column the read-back holds a value in, and each whose sent value was false or empty, since Airtable leaves a false checkbox and an empty value out of a record it reads; `missing`: the rest.
    The `stops`: no schema, a column the table lacks, a type the site does not write, and a column in `missing` ("%s: sent, and not read back."), each as `left_out_sentence()` or that sentence words it. The `notes`: a name the base does not hold (an unmatched country, language or contribution area), which an activation leaves out by name while it writes the rest. Nothing of the program changes: no application, account, event, Slack message or feed figure (the client's own copies of the schema may be refreshed, as any read refreshes them).
  - `WPCPM_CLI::mentor_airtable_row( $args, $assoc_args )`: `wp wpcredits mentor_airtable_row <reference>`, read-only, and `wp wpcredits mentor_airtable_row --check --email=<address>`, the check row.

**What each cell holds**, by the column's type in the schema (`columns[ name ]['type']`, its `options['choices']`, its `options['linkedTableId']`, and the linked table's `primary`):

| Answer | Sent |
| --- | --- |
| Full Name, WordPress profile, Email | always: the name as typed (`mail_text()`), the profile's address built from the username (`profile_url()`), the address |
| Status | always: the `mentor_status` setting, the status the mentors sync reads |
| country | `multipleRecordLinks`: the linked table's record id whose primary name equals it, trimmed and compared case-insensitively, as a one-item list; `singleSelect`: the name when it is a choice; `singleLineText`: the name |
| languages, expertise | `multipleRecordLinks`: the ids of the names found, in the answer's order; `multipleSelects`: the names among the choices; `singleLineText` or `multilineText`: the names joined with `, ` |
| hours | `number`: the stored text as a float; `singleLineText`: the stored text |
| teacher | `singleSelect`: `Yes` or `No` when it is a choice; `checkbox`: true for Yes, false for No; `singleLineText`: `Yes` or `No` |
| anything | `singleLineText`, `multilineText` or `richText`: the text as typed (`mail_text()`) |
| a column the table lacks | left out, `why` `no-column` |
| another type | left out, `why` `type`, `values` the type |
| names the base does not hold | the rest sent; the missing ones left out, `why` `unmatched`, `values` the names |
| a linked table that cannot be read | left out, `why` `unread`, `values` the error; `write()` then writes nothing |
| no schema, or no Mentors table in it | the four cells alone; `left_out` `array( '*' => array( 'why' => 'no-schema', 'values' => array() ) )` |

An empty answer sends nothing and is not left out. `left_out_sentence()` joins one sentence per entry with a space: `The answers were left out: the token cannot read the base's columns.`; `%s: the Mentors table has no such column.`; `%1$s: the column is a %2$s, which the site does not write.`; `%1$s: not in the base: %2$s.` (the names joined with `, `); `%s: its linked table could not be read.`.

**What `write()` does, in order:**

1. A record id given (the account's own row): `update_records()` setting its Status; written, `reactivated`. Refused with the error data's `status` 404 or `error_type` `ROW_DOES_NOT_EXIST` (the row was deleted in Airtable): on to step 2 as if none was given. Any other refusal: `ok` false.
2. `find_by_email()`. A refusal: `ok` false. Two rows or more: `ok` false, "Airtable holds %d Mentors rows with this address. Keep one, then press Write the Airtable row again.". One row that another account on the site holds as its `META_RECORD_ID`: `ok` false, "The Mentors row with this address is linked to another account on this site, %s." (that account's username). One row otherwise, whatever its Status: its Status set as in step 1; written, `adopted`, with that record.
3. None: `preview()`; any entry left out as `unread`: `ok` false, "%1$s could not be read from Airtable, so nothing was written: %2$s" (the column, the error). Otherwise `create_records( mentors_table, array( array( 'fields' => <the cells> ) ) )`; the first answer `is_record_id()` accepts is the record, `created`; none: `ok` false, "Airtable did not answer with a record ID.".

- [ ] **Step 1: Write the failing test**

`bin/test-mentor-airtable-row.php` runs on `bin/stubs/mentor-applications.php` (as Task 1 extended it) with the real `WPCPM_Mentor_Application`, `WPCPM_Mentors_Sync`, `WPCPM_Typed_Text` and the new class. Stand-ins the suite adds: `WPCPM_Settings` (`get()` with `mentors_table` `tblMENTORS0000001`, `mentor_status` `Active`, `checker_source_status` `Vetted - positive` and `checker_target_status` `Active`, and whatever `$GLOBALS['settings']` sets over them); `WPCPM_Mentor_Application_Form` answering `countries()` with `array( 'Poland' )` and `teams()` with `array( 'Hosting' )`, which the column's choices do not hold; the client `WPCPM_Airtable`, whose `cached_schema()` answers `array( 'schema' => $GLOBALS['schema'], 'age' => 0 )` as the real one does, or the `WP_Error` in `$GLOBALS['schema_error']`; `fetch_all()` answering `$GLOBALS['linked'][ $table ]` and counting each read in `$GLOBALS['reads']`, or the error `$GLOBALS['linked_error'][ $table ]` holds; `fetch_page()` answering the Email search from `$GLOBALS['by_email']` (record ids), or, once a create has been asked, from `$GLOBALS['by_email_after']` when a check sets it, and keeping its arguments in `$GLOBALS['searched']`; `create_records()` and `update_records()` answering the error `$GLOBALS['refuse_create']` or `$GLOBALS['refuse_update']` holds, and otherwise appending what they accept to `$GLOBALS['created']` and `$GLOBALS['updated']` and answering the ids in `$GLOBALS['created_ids']` (`array( 'recNEWMENTOR00001' )` unless a check says), so a refused write is never recorded as made; `get_record()` answering the fields created for that id less the columns in `$GLOBALS['drop_on_read']` and less every false or empty value, as Airtable leaves those out of a record it reads, or the error `$GLOBALS['refuse_read']` holds; `delete_records()` appending the ids it deletes to `$GLOBALS['deleted']`, or answering the error `$GLOBALS['refuse_delete']` holds; and the real `table_in()`, `formula_in()`, `flatten()` and `is_record_id()` copied. Helpers the suite adds: `fresh_client()` empties those globals and the transients; `schema( array $changes = array() )` answers the bare schema of the Mentors table `tblMENTORS0000001` with `Full Name` (singleLineText), `WordPress profile` (url), `Email` (email), `Status` (singleSelect: Interested, Vetted - positive, Active, Inactive), `Country` (multipleRecordLinks to `tblCOUNTRIES00001`), `Languages` (multipleRecordLinks to `tblLANGUAGES00001`), `Contribution Area - Expertise` (multipleSelects: Polyglots, Training), `Available hours per week` (number), `Teacher from a participating institution?` (singleSelect: Yes, No) and `Anything else you’d like us to know?` (multilineText), and the two linked tables with primary `Name`, each entry of `$changes` replacing that column's spec or dropping the column when it is null; `$GLOBALS['linked']` holds Poland `recCOUNTRYPL00001`, English `recLANGENGLISH001` and Polish `recLANGPOLISH0001`; `application( array $more = array() )` stores a selected row whose answers are Ada Example, `ada@example.test`, `ada-example`, Poland, No, `array( 'English', 'Polish' )`, `array( 'Polyglots', 'Training' )`, `'2.5'`, and the free text as the form stores it, `Tom & Jerry's "quote", and a &lt; b, Zoë &amp; co.`; `names()` is `function ( $table, $primary ) { return WPCPM_Mentor_Airtable_Row::link_names( new WPCPM_Airtable(), $table, $primary ); }`.

```php
$anything = "Anything else you\u{2019}d like us to know?";
fresh_client();
$ada = application();
$row = WPCPM_Mentor_Airtable_Row::cells( $ada, schema(), names() );
ck( 'the row an application writes: the four cells the sync reads, then each answer by its column\'s type, the free text as typed',
	$row['cells'],
	array(
		'Full Name'                                 => 'Ada Example',
		'WordPress profile'                         => 'https://profiles.wordpress.org/ada-example/',
		'Email'                                     => 'ada@example.test',
		'Status'                                    => 'Active',
		'Country'                                   => array( 'recCOUNTRYPL00001' ),
		'Languages'                                 => array( 'recLANGENGLISH001', 'recLANGPOLISH0001' ),
		'Contribution Area - Expertise'             => array( 'Polyglots', 'Training' ),
		'Available hours per week'                  => 2.5,
		'Teacher from a participating institution?' => 'No',
		$anything                                   => 'Tom & Jerry\'s "quote", and a < b, Zoë & co.',
	) );
ck( 'and nothing is left out', $row['left_out'], array() );
ck( 'a name typed with quotes, an ampersand and a "<" is sent as typed',
	WPCPM_Mentor_Airtable_Row::cells( application( array( 'name' => 'Zoë "Z" Example & Co &lt;3' ) ), schema(), names() )['cells']['Full Name'], 'Zoë "Z" Example & Co <3' );

$wu = WPCPM_Mentor_Airtable_Row::cells( application( array( 'languages' => array( 'English', 'Wu Chinese (Shanghainese)' ), 'expertise' => array( 'Hosting', 'Polyglots' ) ) ), schema(), names() );
ck( 'a language the base does not hold is left out by name, and the others are linked; so is a contribution area the column does not offer',
	array( $wu['cells']['Languages'], $wu['cells']['Contribution Area - Expertise'], $wu['left_out'] ),
	array( array( 'recLANGENGLISH001' ), array( 'Polyglots' ), array(
		'Languages'                     => array( 'why' => 'unmatched', 'values' => array( 'Wu Chinese (Shanghainese)' ) ),
		'Contribution Area - Expertise' => array( 'why' => 'unmatched', 'values' => array( 'Hosting' ) ),
	) ) );

$no_hours = WPCPM_Mentor_Airtable_Row::cells( $ada, schema( array( 'Available hours per week' => null ) ), names() );
ck( 'an answer whose column the table lacks is left out, and the rest is written', array( array_key_exists( 'Available hours per week', $no_hours['cells'] ), $no_hours['left_out'], count( $no_hours['cells'] ) ), array( false, array( 'Available hours per week' => array( 'why' => 'no-column', 'values' => array() ) ), 9 ) );
ck( 'a column of a type the site does not write is left out with its type',
	WPCPM_Mentor_Airtable_Row::cells( $ada, schema( array( 'Available hours per week' => array( 'type' => 'rating' ) ) ), names() )['left_out'],
	array( 'Available hours per week' => array( 'why' => 'type', 'values' => array( 'rating' ) ) ) );
ck( 'the teacher answer fits its column: a choice, a checkbox, or text',
	array(
		WPCPM_Mentor_Airtable_Row::cells( $ada, schema(), names() )['cells']['Teacher from a participating institution?'],
		WPCPM_Mentor_Airtable_Row::cells( $ada, schema( array( 'Teacher from a participating institution?' => array( 'type' => 'checkbox' ) ) ), names() )['cells']['Teacher from a participating institution?'],
		WPCPM_Mentor_Airtable_Row::cells( application( array( 'teacher' => 'Yes' ) ), schema( array( 'Teacher from a participating institution?' => array( 'type' => 'checkbox' ) ) ), names() )['cells']['Teacher from a participating institution?'],
		WPCPM_Mentor_Airtable_Row::cells( $ada, schema( array( 'Teacher from a participating institution?' => array( 'type' => 'singleLineText' ) ) ), names() )['cells']['Teacher from a participating institution?'],
	),
	array( 'No', false, true, 'No' ) );
ck( 'languages go as text into a text column, and hours stay as typed in one',
	array(
		WPCPM_Mentor_Airtable_Row::cells( $ada, schema( array( 'Languages' => array( 'type' => 'singleLineText' ) ) ), names() )['cells']['Languages'],
		WPCPM_Mentor_Airtable_Row::cells( $ada, schema( array( 'Available hours per week' => array( 'type' => 'singleLineText' ) ) ), names() )['cells']['Available hours per week'],
	),
	array( 'English, Polish', '2.5' ) );
$quiet = WPCPM_Mentor_Airtable_Row::cells( application( array( 'anything' => '', 'expertise' => array() ) ), schema(), names() );
ck( 'an empty answer sends nothing and is not left out', array( array_key_exists( $anything, $quiet['cells'] ), array_key_exists( 'Contribution Area - Expertise', $quiet['cells'] ), $quiet['left_out'] ), array( false, false, array() ) );

$blind = WPCPM_Mentor_Airtable_Row::cells( $ada, null, names() );
ck( 'without the base\'s columns, the four cells alone, and the answers said to be left out',
	array( array_keys( $blind['cells'] ), $blind['left_out'], WPCPM_Mentor_Airtable_Row::left_out_sentence( $blind['left_out'] ) ),
	array( array( 'Full Name', 'WordPress profile', 'Email', 'Status' ), array( '*' => array( 'why' => 'no-schema', 'values' => array() ) ), 'The answers were left out: the token cannot read the base\'s columns.' ) );
ck( 'and a schema without the Mentors table is read as none', WPCPM_Mentor_Airtable_Row::cells( $ada, array( 'tblOTHER000000001' => array( 'name' => 'Other', 'primary' => 'Name', 'columns' => array() ) ), names() )['left_out'], array( '*' => array( 'why' => 'no-schema', 'values' => array() ) ) );
fresh_client();
$GLOBALS['schema'] = schema();
ck( 'preview() reads the schema cached_schema() holds, not its wrapper, and the names through the client', WPCPM_Mentor_Airtable_Row::preview( $ada, new WPCPM_Airtable() ), $row );
$GLOBALS['schema_error'] = new WP_Error( 'airtable', 'This was a schema read, so the token most likely lacks the "schema.bases:read" scope.' );
ck( 'and a schema it cannot read is none', WPCPM_Mentor_Airtable_Row::preview( $ada, new WPCPM_Airtable() )['left_out'], array( '*' => array( 'why' => 'no-schema', 'values' => array() ) ) );
$unread = WPCPM_Mentor_Airtable_Row::cells( $ada, schema(), function ( $table, $primary ) { return 'tblLANGUAGES00001' === $table ? new WP_Error( 'airtable', 'Airtable is out of reach.' ) : WPCPM_Mentor_Airtable_Row::link_names( new WPCPM_Airtable(), $table, $primary ); } );
ck( 'a linked table that cannot be read leaves its answers out with the error, and the other link is made',
	array( $unread['left_out'], $unread['cells']['Country'], WPCPM_Mentor_Airtable_Row::left_out_sentence( $unread['left_out'] ) ),
	array( array( 'Languages' => array( 'why' => 'unread', 'values' => array( 'Airtable is out of reach.' ) ) ), array( 'recCOUNTRYPL00001' ), 'Languages: its linked table could not be read.' ) );
ck( 'every other reason in a sentence of its own',
	WPCPM_Mentor_Airtable_Row::left_out_sentence( array_merge( $wu['left_out'], array( 'Available hours per week' => array( 'why' => 'type', 'values' => array( 'rating' ) ) ) ) ),
	'Languages: not in the base: Wu Chinese (Shanghainese). Contribution Area - Expertise: not in the base: Hosting. Available hours per week: the column is a rating, which the site does not write.' );

echo "\n=== The write ===\n";
fresh_client();
$GLOBALS['schema'] = schema();
$wrote             = WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() );
ck( 'with no row holding the address, write() searches by it, then creates one row from the cells and answers its record id',
	array( $wrote['ok'], $wrote['how'], $wrote['record'], $GLOBALS['searched'][0]['table'], $GLOBALS['created'], $GLOBALS['updated'] ),
	array( true, 'created', 'recNEWMENTOR00001', 'tblMENTORS0000001', array( array( 'fields' => $row['cells'] ) ), array() ) );
WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() );
ck( 'the linked tables are read once a day each, not once a row', $GLOBALS['reads'], array( 'tblCOUNTRIES00001' => 1, 'tblLANGUAGES00001' => 1 ) );
fresh_client();
$back = WPCPM_Mentor_Airtable_Row::write( $ada, 'recRETURNING00001', 70, new WPCPM_Airtable() );
ck( 'for a returning mentor, write() sets their own row\'s Status back to the sync\'s status, searches nothing and creates nothing',
	array( $back['ok'], $back['how'], $back['record'], $GLOBALS['updated'], $GLOBALS['created'], $GLOBALS['searched'] ),
	array( true, 'reactivated', 'recRETURNING00001', array( array( 'id' => 'recRETURNING00001', 'fields' => array( 'Status' => 'Active' ) ) ), array(), array() ) );
fresh_client();
$GLOBALS['by_email'] = array( 'recADOPTED0000001' );
$adopt               = WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() );
ck( 'a row that already holds the address is set back to Active and answered, whatever its Status, and none is created',
	array( $adopt['how'], $adopt['record'], $GLOBALS['updated'], $GLOBALS['created'] ),
	array( 'adopted', 'recADOPTED0000001', array( array( 'id' => 'recADOPTED0000001', 'fields' => array( 'Status' => 'Active' ) ) ), array() ) );
an_account( 71, 'Other Example', 'other@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recADOPTED0000001' ) );
ck( 'but not one another account on the site holds', WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() )['error'], 'The Mentors row with this address is linked to another account on this site, ' . get_userdata( 71 )->user_login . '.' );
$GLOBALS['by_email'] = array( 'recADOPTED0000001', 'recADOPTED0000002' );
ck( 'and two rows with the address are a person\'s to choose between', array( WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() )['error'], count( $GLOBALS['created'] ) ), array( 'Airtable holds 2 Mentors rows with this address. Keep one, then press Write the Airtable row again.', 0 ) );
fresh_client();
$GLOBALS['refuse_update'] = new WP_Error( 'wpcpm_airtable_error', 'Airtable request failed (HTTP 404): NOT_FOUND', array( 'status' => 404, 'error_type' => 'NOT_FOUND' ) );
$gone                     = WPCPM_Mentor_Airtable_Row::write( $ada, 'recRETURNING00001', 70, new WPCPM_Airtable() );
ck( 'a returning mentor whose row was deleted in Airtable is searched for by address, and gets a new row', array( $gone['ok'], $gone['how'], $gone['record'], count( $GLOBALS['searched'] ), $GLOBALS['updated'] ), array( true, 'created', 'recNEWMENTOR00001', 1, array() ) );
fresh_client();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
ck( 'a refusal is answered, never thrown, and nothing is recorded as made', array( WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() )['error'], $GLOBALS['created'] ), array( 'Airtable refused the write.', array() ) );
fresh_client();
$GLOBALS['created_ids'] = array();
ck( 'and an answer with no record id in it is a refusal too', WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() )['error'], 'Airtable did not answer with a record ID.' );
fresh_client();
$GLOBALS['schema']                             = schema();
$GLOBALS['linked_error']['tblLANGUAGES00001'] = new WP_Error( 'airtable', 'Airtable is out of reach.' );
$stop                                          = WPCPM_Mentor_Airtable_Row::write( $ada, '', 70, new WPCPM_Airtable() );
ck( 'a linked table that cannot be read stops the write, so no row is left without its links for good', array( $stop['ok'], $stop['error'], $GLOBALS['created'] ), array( false, 'Languages could not be read from Airtable, so nothing was written: Airtable is out of reach.', array() ) );
ck( 'by_reference() finds an application by the reference it carries, and nothing for one it does not', array( WPCPM_Mentor_Application::by_reference( WPCPM_Mentor_Application::reference_of( $ada ) )->ID, WPCPM_Mentor_Application::by_reference( 'MAPP-2026-9999' ) ), array( $ada->ID, null ) );

echo "\n=== The check row ===\n";
fresh_client();
$GLOBALS['schema'] = schema();
$posts             = count( $GLOBALS['posts'] );
$users             = count( $GLOBALS['users'] );
$check             = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
$expected          = WPCPM_Mentor_Airtable_Row::cells_for( WPCPM_Mentor_Airtable_Row::check_answers( 'owner+check@example.test' ), schema(), names() )['cells'];
$expected['Status'] = 'Inactive';
ck( 'the check row is the row an activation writes but for its Status: Inactive, a choice neither the mentors sync nor the checker reads, and the Active the activation writes is confirmed in the schema, not written',
	array( $GLOBALS['created'][0]['fields'], WPCPM_Mentor_Airtable_Row::CHECK_STATUS, $check['stops'] ),
	array( $expected, 'Inactive', array() ) );
ck( 'it is searched for first, read back and deleted, and says which columns the base kept',
	array( count( $GLOBALS['searched'] ), $check['record'], $check['deleted'], $GLOBALS['deleted'], in_array( 'Full Name', $check['kept'], true ), $check['missing'] ),
	array( 1, 'recNEWMENTOR00001', true, array( 'recNEWMENTOR00001' ), true, array() ) );
ck( 'and nothing on the site changes: no application, no account, no kept names', array( count( $GLOBALS['posts'] ), count( $GLOBALS['users'] ), isset( $GLOBALS['transients'][ WPCPM_Mentor_Airtable_Row::LINKS_TRANSIENT ] ) ), array( $posts, $users, false ) );
ck( 'a contribution area the base does not hold prints as a note and does not stop the sitting', array( $check['notes'], $check['stops'] ), array( array( 'Contribution Area - Expertise: not in the base: Hosting.' ), array() ) );
fresh_client();
$GLOBALS['schema']       = schema();
$GLOBALS['drop_on_read'] = array( 'Available hours per week' );
$short                   = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'a column sent and not read back is a stop', array( $short['missing'], $short['stops'] ), array( array( 'Available hours per week' ), array( 'Available hours per week: sent, and not read back.' ) ) );
fresh_client();
$GLOBALS['schema'] = schema( array( 'Teacher from a participating institution?' => array( 'type' => 'checkbox' ) ) );
$box               = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'a false checkbox, which Airtable leaves out of a read, counts as kept', array( $GLOBALS['created'][0]['fields']['Teacher from a participating institution?'], in_array( 'Teacher from a participating institution?', $box['kept'], true ), $box['stops'] ), array( false, true, array() ) );
fresh_client();
$GLOBALS['schema']   = schema();
$GLOBALS['by_email'] = array( 'recLEFTOVER000001' );
$left                = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'a row already holding the check address refuses the check, names the row to delete by hand, and writes nothing',
	array( $left['found_before'], $left['error'], $GLOBALS['created'] ),
	array( array( 'recLEFTOVER000001' ), 'Rows with the check address are already in the Mentors table: recLEFTOVER000001. Delete them by hand, then check again.', array() ) );
fresh_client();
$GLOBALS['schema']         = schema();
$GLOBALS['refuse_create']  = new WP_Error( 'http_request_failed', 'Operation timed out after 20000 milliseconds.' );
$GLOBALS['by_email_after'] = array( 'recLOSTCHECK00001' );
$lost                      = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'a create whose answer was lost is searched for again, and the row Airtable stored is deleted',
	array( $lost['cleaned'], $GLOBALS['deleted'], $lost['error'] ),
	array( array( 'recLOSTCHECK00001' ), array( 'recLOSTCHECK00001' ), 'Operation timed out after 20000 milliseconds. The search after it found recLOSTCHECK00001 and deleted it.' ) );
fresh_client();
$GLOBALS['schema']        = schema();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$refused                  = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'a create error with nothing found deletes nothing and says so', array( $refused['cleaned'], $GLOBALS['deleted'], $refused['error'] ), array( array(), array(), 'Airtable refused the write. The search after it found nothing, so nothing was stored and nothing was deleted.' ) );
fresh_client();
$GLOBALS['schema']      = schema();
$GLOBALS['refuse_read'] = new WP_Error( 'airtable', 'Airtable is out of reach.' );
$unread_back            = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'the delete runs even when the read-back fails', array( $unread_back['read_error'], $unread_back['deleted'], $GLOBALS['deleted'] ), array( 'Airtable is out of reach.', true, array( 'recNEWMENTOR00001' ) ) );
fresh_client();
$GLOBALS['schema']        = schema();
$GLOBALS['refuse_delete'] = new WP_Error( 'airtable', 'Airtable refused the delete.' );
$stuck                    = WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() );
ck( 'a delete Airtable refuses is said, with the row to delete by hand', array( $stuck['deleted'], $stuck['delete_error'], $stuck['record'] ), array( false, 'Airtable refused the delete.', 'recNEWMENTOR00001' ) );
fresh_client();
$GLOBALS['schema'] = schema( array( 'Status' => array( 'type' => 'singleSelect', 'options' => array( 'choices' => array( array( 'name' => 'Interested' ), array( 'name' => 'Inactive' ) ) ) ) ) );
ck( 'an activation status the column does not offer stops the check with nothing written', array( WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() )['error'], $GLOBALS['created'] ), array( 'The activation\'s status, Active, is not a choice of the Status column.', array() ) );
fresh_client();
$GLOBALS['schema']                          = schema();
$GLOBALS['settings']['checker_source_status'] = 'Inactive';
ck( 'and so does a check status the checker reads', array( WPCPM_Mentor_Airtable_Row::check_row( 'owner+check@example.test', new WPCPM_Airtable() )['error'], $GLOBALS['created'] ), array( 'The check row\'s status, Inactive, is one the mentors sync, the checker or an automation reads, or no choice of the Status column.', array() ) );
unset( $GLOBALS['settings']['checker_source_status'] );
fresh_client();
ck( 'an address that is not one writes nothing', array( WPCPM_Mentor_Airtable_Row::check_row( 'not-an-address', new WPCPM_Airtable() )['error'], $GLOBALS['created'] ), array( 'The check needs an email address.', array() ) );
```

In `bin/test-cli.php`, with `WPCPM_Mentor_Application::by_reference()` stood in (a post for `MAPP-2026-0503`, null otherwise) and `WPCPM_Mentor_Airtable_Row` stood in, its `preview()` answering the three cells below and `Languages` left out as unmatched, its `left_out_sentence()` the real one's sentence for that:

```php
ck( 'mentor_airtable_row prints each cell an application would write, lists values with commas, says what would be left out, and writes nothing',
	cli_lines( 'mentor_airtable_row', array( 'MAPP-2026-0503' ) ),
	array( 'log: Full Name: Ada Example', 'log: Languages: recLANGENGLISH001, recLANGPOLISH0001', 'log: Available hours per week: 2.5', 'log: Languages: not in the base: Wu Chinese (Shanghainese).', 'success: Nothing was written to Airtable.' ) );
ck( 'and refuses a reference no application carries', cli_lines( 'mentor_airtable_row', array( 'MAPP-2026-9999' ) ), array( 'error: No mentor application has the reference MAPP-2026-9999.' ) );
```

With the stand-in's `check_row()` answering, in turn: a row written, read back and deleted with one note and no stop; one with a stop; one a row already held the address of; one whose create was refused; and one whose delete was refused:

```php
ck( 'the check prints what was written and kept, a note as a note, and says the row is gone and nothing on the site changed',
	cli_lines( 'mentor_airtable_row', array(), array( 'check' => true, 'email' => 'owner+check@example.test' ) ),
	array( 'log: Written: Full Name, WordPress profile, Email, Status, Country', 'log: Kept by the base: Full Name, WordPress profile, Email, Status, Country', 'log: Only left out: Contribution Area - Expertise: not in the base: Hosting.', 'success: The check row recNEWMENTOR00001 was written, read back and deleted. Nothing on this site changed.' ) );
ck( 'a stop is a warning, and the check ends in an error that holds the sitting',
	cli_lines( 'mentor_airtable_row', array(), array( 'check' => true, 'email' => 'owner+check@example.test' ) ),
	array( 'log: Written: Full Name, WordPress profile, Email, Status, Country', 'log: Kept by the base: Full Name, WordPress profile, Email, Status', 'warning: Country: sent, and not read back.', 'error: The check row recNEWMENTOR00001 was written, read back and deleted, but the live base would not take the whole row. Put that right before switching Make mentors from applications on.' ) );
ck( 'a row already holding the check address is named, and nothing was written', cli_lines( 'mentor_airtable_row', array(), array( 'check' => true, 'email' => 'owner+check@example.test' ) ), array( 'error: Rows with the check address are already in the Mentors table: recLEFTOVER000001. Delete them by hand, then check again.' ) );
ck( 'a refused create is printed in the base\'s words with what the search after it found', cli_lines( 'mentor_airtable_row', array(), array( 'check' => true, 'email' => 'owner+check@example.test' ) ), array( 'error: Airtable refused the check row: Airtable refused the write. The search after it found nothing, so nothing was stored and nothing was deleted.' ) );
ck( 'a refused delete names the row to delete by hand', cli_lines( 'mentor_airtable_row', array(), array( 'check' => true, 'email' => 'owner+check@example.test' ) ), array( 'log: Written: Full Name, WordPress profile, Email, Status, Country', 'log: Kept by the base: Full Name, WordPress profile, Email, Status, Country', 'error: The check row recNEWMENTOR00001 was written but could not be deleted: Airtable refused the delete. Delete it in Airtable by hand.' ) );
ck( 'and without an address it asks for one', cli_lines( 'mentor_airtable_row', array(), array( 'check' => true ) ), array( 'error: Give the test mailbox\'s address as --email=<address>.' ) );
```

(`cli_lines( $method, $args, $assoc = array() )` is a helper the suite adds: it calls `( new WPCPM_CLI() )->$method( $args, $assoc )`, catches the stand-in's exit, and answers `WP_CLI::$lines`.) In `bin/test-uninstall.php`: `'the linked names the Mentors row was written with go'`, `LINKS_TRANSIENT` deleted.

- [ ] **Step 2: Run it to make sure it fails**

Run: `php bin/test-mentor-airtable-row.php`
Expected: a fatal error, `Class "WPCPM_Mentor_Airtable_Row" not found`.

- [ ] **Step 3: Implement the row, the five column names, `by_reference()` and the command**

`cells()` finds the Mentors table with `WPCPM_Airtable::table_in( $schema, $settings['mentors_table'] )`, a `WP_Error` from it read as no schema, and walks `ANSWERS` by the table above; names are compared `mb_strtolower( trim() )`. `link_names()` reads the linked table's primary field with `fetch_all( $table_id, array( 'fields' => array( $primary ) ) )` and keeps the map in `LINKS_TRANSIENT` (one transient, table id to `read` and `names`, `DAY_IN_SECONDS`); a read error is answered and nothing is kept. `find_by_email()` is one `fetch_page()` of the Mentors table with that formula and the Email field alone. `check_row()` as the Interfaces say. `write()` as the list above; a refusal's data is read with `get_error_data()`. `by_reference()` is one `get_posts()` on `META_REFERENCE`. The command, docblocked as the others, is read-only: each cell as `Column: value` (a list joined with `, `, true and false as `Yes` and `No`), then `left_out_sentence()` when it says anything, then `WP_CLI::success( 'Nothing was written to Airtable.' )`; an unknown reference is `WP_CLI::error()`. With `--check`, it asks `check_row()` for the address `--email` gives and prints the lines the CLI checks pin: what was written, what the base kept, each note as a log line and each stop as a warning, then success while no stop was found, or the error that holds the sitting, or the error for a row already holding the address, a refused create or a refused delete. Its docblock says the preview prints an applicant's answers and is for a test application, and that the check writes one synthetic row and deletes it.

- [ ] **Step 4: Run the tests to make sure they pass**

Run: `php bin/test-mentor-airtable-row.php; php bin/test-cli.php; php bin/test-uninstall.php; php bin/test-students-sync.php; php bin/test-institution-roster.php`
Expected: `ALL PASS` from each.

- [ ] **Step 5: The checks every task ends with, then commit**

```bash
git add includes/modules/class-wpcpm-mentor-airtable-row.php includes/modules/class-wpcpm-mentors-sync.php includes/modules/class-wpcpm-mentor-application.php includes/class-wpcpm-cli.php wpcredits-program-manager.php uninstall.php bin/test-mentor-airtable-row.php bin/test-cli.php bin/test-uninstall.php
git commit -m "Mentor applications: the Mentors row an activation writes, found by the address first, each answer shaped by its column's type, and a command that prints it, or writes and deletes one synthetic row to check the base" -m "<the Co-Authored-By trailer>"
```

---

### Task 3: The activation step, and the switch that holds it

**Files:**
- Create: `includes/modules/class-wpcpm-mentor-activation.php`
- Modify: `bin/stubs/mentor-applications.php` (while `$GLOBALS['write_log']` is an array, `update_post_meta()` and `update_user_meta()` append each key they write, so a check can read the order; no M1 suite sets it), `includes/modules/class-wpcpm-mentor-application.php` (the lock name, four meta, three events, their readers; `lapse()` takes the lock, `daily()` settles before it purges, `purge()` keeps an active row not yet written), `includes/modules/class-wpcpm-mentors-sync.php` (`phase_revoke()`), `includes/class-wpcpm-settings.php` and `includes/class-wpcpm-settings-screen.php` (`mentor_activation_enabled`; the Invitation emails fold), `wpcredits-program-manager.php` and `uninstall.php` (the `require_once`; the uninstaller sweeps the lock prefix with the prefixes it already sweeps)
- Test: `bin/test-mentor-activation.php` (new), `bin/test-mentor-application.php`, `bin/test-settings.php`, `bin/test-uninstall.php`

**Interfaces:**
- Consumes: Task 1's `provision()`, `find_account()`, `USER_ROW_PENDING`, `USER_ACTIVATED_AT`; Task 2's `write()` and `left_out_sentence()`; 1.122.32's `WPCPM_Lock::take( $name, $ttl )` (a token, or `''` while held) and `WPCPM_Lock::release( $name, $token )`; `WPCPM_Mentor_Application::application()`, `state_of()`, `decide()`, `add_event()`, `name_of()`, `email_of()`, `handle_of()`, `profile_url()`, `applications()`, `EVENT_ACTIVATED`; `WPCPM_Mail::queue_invite()`; `WPCPM_Mentor_Checker_Slack::record()` (which keeps the `source` key from Task 5 on; this task's suite stands it in); `WPCPM_Mentors_Sync::META_RECORD_ID`, `META_ACTIVE`; `WPCPM_Roles::ROLE_MENTOR`, `ROLE_ADMIN`; `WPCPM_Settings::get()`, `get_value()`, `is_connected()`; `WPCPM_Typed_Text::mail_text()`.
- Produces:
  - Setting `mentor_activation_enabled`, default `false`, in the `people` scope after `mentor_applications_enabled` and cleaned as it is, drawn under **Mentor applications** on the Students and mentors tab as **Make mentors from applications**: the box "Make a mentor of each selected applicant whose course shows, or for whom The course is completed is pressed", the description "Off until the launch. While it is off, an applicant whose course shows waits under Selected (course pending)."; `WPCPM_Mentor_Activation::enabled(): bool`.
  - The Invitation emails fold gains: "A mentor made from an application is sent theirs whatever this says, since it carries the welcome."
  - `WPCPM_Mentor_Application::LOCK_PREFIX = 'wpcpm_mapp_lock_'`, `LOCK_TTL = 600`, `lock_name( $post_id ): string`: the one lock of an application, which the activation, its retry and the lapse clock take.
  - `WPCPM_Mentor_Application::META_USER = '_wpcpm_mapp_user'`, `META_AIRTABLE = '_wpcpm_mapp_airtable'`, `META_PROBLEM = '_wpcpm_mapp_problem'`, `META_ACTIVATED_AT = '_wpcpm_mapp_activated'` (a Unix time: the index the Activated view orders by, and the moment its invitation line counts from); `EVENT_AIRTABLE_WRITTEN = 'Airtable row written'`, `EVENT_AIRTABLE_REFUSED = 'Airtable row refused'`, `EVENT_ALREADY_MENTOR = 'already a mentor'` (not counted by the feed).
  - `user_of( WP_Post $post ): int`; `airtable_of( WP_Post $post ): array` with `state` (`pending`, `created`, `reactivated`, `adopted`, `found`, `gone`, `already` or `refused`), `how` (`create` or `reactivate`), `record`, `error`, `left_out`, `at`, or `array()`; `problem_of( WP_Post $post ): array` with `at` and `message`, or `array()`.
  - `WPCPM_Mentors_Sync::phase_revoke()` passes by an account holding `USER_ROW_PENDING`, or whose `USER_ACTIVATED_AT` is at or after `$state['started']`.
  - `WPCPM_Mentor_Activation::activate( $post_id, $actor = 0, $note = '' ): array` with `outcome` (`activated`, `already-mentor`, `already`, `off`, `busy`, `not-selected`, `no-account` or `unknown`), `user_id`, `airtable` (`created`, `reactivated`, `adopted`, `refused` or `''`) and `message`. `$actor` 0 is the daily check or WP-CLI.
  - `retry_airtable( $post_id, $actor ): array` with `outcome` (`written`, `refused`, `not-needed`, `busy`, `not-active` or `unknown`) and `message`.
  - `unwritten(): WP_Post[]`, the active applications whose Airtable state is `refused` or `pending` and whose account still holds `USER_ROW_PENDING` (the sync clears it once it links the account to an Active row), oldest first; `unwritten_count(): int`; `activated_posts( $limit ): WP_Post[]`, every unwritten one first, then the other active applications by `META_ACTIVATED_AT`, newest first, the ID descending on a tie, at most `$limit`.
  - `settle( WP_Post $post ): bool`, under the application's lock (a held lock skips the row, as `lapse()` skips one): an active application whose Airtable state is `refused` or `pending` is settled when its account no longer holds `USER_ROW_PENDING` but holds a record id, as `META_AIRTABLE` `found` with that record and `EVENT_AIRTABLE_WRITTEN` by 0 noting "Linked by the mentors sync or by hand; nothing was written from here."; and when `META_USER` names an account that no longer exists, as `gone`, with `EVENT_AIRTABLE_WRITTEN` by 0 noting "The account made for this mentor no longer exists; nothing more is written for it.". Answers whether it settled. `settle_all(): int` settles every active application. `unwritten()` and `activated_posts()` settle each row they read, and each says so in a line of its docblock (it may settle rows as the screen draws them). `WPCPM_Mentor_Application::daily()` runs `lapse()`, then `WPCPM_Mentor_Activation::settle_all()` (while the class is loaded), then `purge()`, so a settled row neither reads as refused nor is kept for ever; `purge()` keeps only `pending` and `refused`.

**`activate()`, in order** (the order the tests pin and the design's "a mentor is never kept waiting on Airtable" asks, section 7):

1. The application, or `unknown`; the switch off, `off`, before anything is read or taken.
2. `$token = WPCPM_Lock::take( lock_name( $id ), LOCK_TTL )`; `'' === $token` is `busy`. The rest runs in `try`, and `WPCPM_Lock::release( lock_name( $id ), $token )` in `finally`.
3. The state read again under the lock: `active` is `already`; anything but `selected` is `not-selected`.
4. `$found = find_account( email_of(), handle_of(), handle_of() )`; the record id is the found account's `META_RECORD_ID`, or `''`.
5. A found account holding the Mentor role, `META_ACTIVE` 1 and a record id is a current mentor (owner item 4, a departure from section 6): `META_USER`, `META_AIRTABLE` `already` with its record, `decide( $id, STATE_ACTIVE, EVENT_ALREADY_MENTOR, $actor, <the note, or "A mentor on this site already: nothing was sent, and nothing was written to Airtable."> )`, and `already-mentor`; nothing queued, written or recorded for Slack.
6. `provision( array( 'record_id' => $record, 'name' => mail_text( name_of() ), 'profile' => profile_url( handle_of() ), 'email' => email_of() ), $report, $found['user'] )`, so the account step 4 found is the one linked. 0: `META_PROBLEM` `array( 'at' => time(), 'message' => $report['notices'][0] )` (or "The account could not be made." with none), and `no-account`.
7. On the account first: `USER_ACTIVATED_AT` now and `USER_ROW_PENDING` 1, so a request that dies before the next write cannot leave `settle()` reading an older record on the account as this row's. Then on the application: `META_USER`, `META_ACTIVATED_AT` now, `META_PROBLEM` deleted, `META_AIRTABLE` `pending` (`how` `reactivate` with a record id, `create` without). Then `decide( $id, STATE_ACTIVE, EVENT_ACTIVATED, $actor, <the note, the provisioning's notices and, for an Administrator, step 8's sentence, joined with a space> )`.
8. The invitation: `WPCPM_Mail::queue_invite( $user_id )` whatever `send_welcome_email` says; for an account holding the Administrator role, nothing is queued, and the activated event's note (step 7) ends "The account is an Administrator's, which signs in already: no invitation was sent." (an Administrator is neither given the Mentor role nor one of the plugin's invitations).
9. `WPCPM_Mentor_Checker_Slack::record( array( 'id' => 'app-' . $id, 'name' => <the name as typed>, 'profile' => <the profile's address>, 'source' => 'site' ) )`.
10. Airtable: not connected (`WPCPM_Settings::is_connected()`), a refusal "Airtable is not connected." with no request; otherwise `write( $post, $record, $user_id, new WPCPM_Airtable() )`. Written: the record on the account's `META_RECORD_ID` (a new or adopted one replaces a stale one), `USER_ROW_PENDING` deleted, `META_AIRTABLE` `created`, `reactivated` or `adopted` with the record and `left_out`, and `EVENT_AIRTABLE_WRITTEN` by 0 with `left_out_sentence()` as its note. Refused: `META_AIRTABLE` `refused` with the error, and `EVENT_AIRTABLE_REFUSED` by 0 with the error as its note.

`retry_airtable()`: the application, the same lock, `active` or `not-active`, `pending` or `refused` or `not-needed`; with `how` `create` and the account now holding a record id, `found` with that record, `USER_ROW_PENDING` deleted and `EVENT_AIRTABLE_WRITTEN` by `$actor` noting "The account already holds its Airtable record; nothing was written.", answered `not-needed`; otherwise step 10 by `$actor`, whose `write()` searches by the address before it creates (so a row a request that died had made is found, not made twice). `lapse()` takes each selected row's lock before it lapses the row (`'' === $token` skips the row tonight) and lets it go after. `purge()` keeps an active row whose Airtable state is `pending` or `refused`, whatever the approved days say.

- [ ] **Step 1: Write the failing tests**

`bin/test-mentor-activation.php` runs on `bin/stubs/mentor-applications.php` (as Task 1 extended it) with the real record, Tasks 1 and 2's classes, `WPCPM_Mentors_Sync`, `WPCPM_Roles`, `WPCPM_Typed_Text`, and the real `WPCPM_Lock` over 1.122.32's `bin/stubs/options-lock-wpdb.php` (a held lock is a row of `$GLOBALS['opts']` holding a Unix time as digits, `(string) ( time() + 600 )`; an expired one `(string) ( time() - 1 )`). Stand-ins the suite adds: Task 2's client, as that suite has it; `WPCPM_Mail::queue_invite()` (the real one's dedupe, appending to `$GLOBALS['queue']`); `WPCPM_Mentor_Checker_Slack::record()` (appending its argument to `$GLOBALS['slack']`); `WPCPM_Settings` (`get()` and `get_value()` from `$GLOBALS['settings']`, `mentor_activation_enabled` true unless a check says; `is_connected()` from `$GLOBALS['connected']`, true unless a check says). Helpers the suite adds: `fresh( array $settings = array() )` empties the stand-ins, the client, `$GLOBALS['opts']` and the lock stand-in's `statements`, and sets `$GLOBALS['insert_error']` back to null; `answers( array $more = array() )` is Task 2's answers as the form stores them; `selected( array $more = array() )` stores an application with those answers, confirmed and selected; `last_events( $id, $n )` answers the newest `$n` event names, oldest of them first; `revoke( $started )` calls `WPCPM_Mentors_Sync::phase_revoke()` by reflection with `array( 'mentors' => array(), 'stats' => WPCPM_Mentors_Sync::empty_stats(), 'started' => $started )` and `array( 'on_inactive' => 'revoke' )`; `lock_row( $id )` reads `$GLOBALS['opts'][ WPCPM_Mentor_Application::lock_name( $id ) ]`, or null.

```php
echo "\n=== The switch ===\n";
fresh( array( 'mentor_activation_enabled' => false ) );
$off = selected();
ck( 'with Make mentors from applications off, nothing is made: no lock taken, no account, no row, no invitation',
	array( WPCPM_Mentor_Activation::activate( $off, 0 )['outcome'], $GLOBALS['wpdb']->statements, count( $GLOBALS['users'] ), $GLOBALS['created'], $GLOBALS['queue'], WPCPM_Mentor_Application::state_of( get_post( $off ) ) ),
	array( 'off', array(), 0, array(), array(), 'selected' ) );

echo "\n=== A new mentor ===\n";
fresh();
$ada = selected();
$r   = WPCPM_Mentor_Activation::activate( $ada, 0 );
$uid = $r['user_id'];
ck( 'the course shown, a selected applicant is made a mentor: an account named after their username, the new row\'s record id on it, the invitation queued once, the application active',
	array( $r['outcome'], $r['airtable'], get_userdata( $uid )->user_login, get_user_meta( $uid, 'wpcpm_mentor_record_id', true ), $GLOBALS['queue'], WPCPM_Mentor_Application::state_of( get_post( $ada ) ), WPCPM_Mentor_Application::user_of( get_post( $ada ) ) ),
	array( 'activated', 'created', 'ada-example', 'recNEWMENTOR00001', array( $uid ), 'active', $uid ) );
ck( 'its history says it was made a mentor by the daily check, then that the row was written; the account is no longer waiting for its row',
	array( last_events( $ada, 2 ), get_user_meta( $uid, WPCPM_Mentor_Provision::USER_ROW_PENDING, true ), get_user_meta( $uid, WPCPM_Mentor_Provision::USER_ACTIVATED_AT, true ) > 0 ),
	array( array( 'activated', 'Airtable row written' ), '', true ) );
ck( 'the row Airtable was sent is the one the answers make, and what is kept of the write says so',
	array( $GLOBALS['created'][0]['fields']['Full Name'], WPCPM_Mentor_Application::airtable_of( get_post( $ada ) )['state'], WPCPM_Mentor_Application::airtable_of( get_post( $ada ) )['record'] ),
	array( 'Ada Example', 'created', 'recNEWMENTOR00001' ) );
ck( 'the Slack message is told of the mentor made, as from an application', $GLOBALS['slack'], array( array( 'id' => 'app-' . $ada, 'name' => 'Ada Example', 'profile' => 'https://profiles.wordpress.org/ada-example/', 'source' => 'site' ) ) );
ck( 'and the lock is let go', lock_row( $ada ), null );
ck( 'a second activation of the same application leaves it alone and says so: no second account, row or invitation',
	array( WPCPM_Mentor_Activation::activate( $ada, 0 )['outcome'], count( $GLOBALS['created'] ), $GLOBALS['queue'], count( $GLOBALS['users'] ) ),
	array( 'already', 1, array( $uid ), 1 ) );
ck( 'an application that is not selected is not made a mentor', WPCPM_Mentor_Activation::activate( WPCPM_Mentor_Application::store( answers(), 'new', array(), array() ), 0 )['outcome'], 'not-selected' );
ck( 'and a number that is no application is nobody', WPCPM_Mentor_Activation::activate( 999999, 0 )['outcome'], 'unknown' );

fresh( array( 'send_welcome_email' => false ) );
$r = WPCPM_Mentor_Activation::activate( selected(), 0 );
ck( 'the invitation is queued with the sync\'s invitation emails off, since it carries the welcome', $GLOBALS['queue'], array( $r['user_id'] ) );

echo "\n=== Somebody the site or the table knows ===\n";
fresh();
an_account( 61, 'Cy Example', 'ada@example.test', array( 'subscriber' ), array( 'wpcpm_mentor_record_id' => 'recRETURNING00001', 'wpcpm_mentor_active' => 0 ) );
$cy = selected();
$r  = WPCPM_Mentor_Activation::activate( $cy, 0 );
ck( 'a former mentor is made a mentor again on their own account and their own row: its Status set back to Active, nothing created',
	array( $r['outcome'], $r['airtable'], $r['user_id'], $GLOBALS['created'], $GLOBALS['updated'], get_user_meta( 61, 'wpcpm_mentor_active', true ), WPCPM_Mentor_Application::airtable_of( get_post( $cy ) )['how'] ),
	array( 'activated', 'reactivated', 61, array(), array( array( 'id' => 'recRETURNING00001', 'fields' => array( 'Status' => 'Active' ) ) ), 1, 'reactivate' ) );
ck( 'and they are sent the invitation with the welcome again', $GLOBALS['queue'], array( 61 ) );
fresh();
an_account( 64, 'Eli Example', 'ada@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recCURRENT0000001', 'wpcpm_mentor_active' => 1 ) );
$eli = selected();
ck( 'a current mentor selected again is filed as already a mentor: nothing sent, nothing written, no Slack line',
	array( WPCPM_Mentor_Activation::activate( $eli, 0 )['outcome'], WPCPM_Mentor_Application::state_of( get_post( $eli ) ), last_events( $eli, 1 ), $GLOBALS['queue'], $GLOBALS['created'], $GLOBALS['updated'], $GLOBALS['slack'], WPCPM_Mentor_Application::airtable_of( get_post( $eli ) )['state'] ),
	array( 'already-mentor', 'active', array( 'already a mentor' ), array(), array(), array(), array(), 'already' ) );
fresh();
$GLOBALS['by_email'] = array( 'recADOPTED0000001' );
$fay                 = selected();
$r                   = WPCPM_Mentor_Activation::activate( $fay, 0 );
ck( 'a row that already holds the address is set back to Active and linked: one row for the person',
	array( $r['airtable'], get_user_meta( $r['user_id'], 'wpcpm_mentor_record_id', true ), $GLOBALS['created'], $GLOBALS['updated'] ),
	array( 'adopted', 'recADOPTED0000001', array(), array( array( 'id' => 'recADOPTED0000001', 'fields' => array( 'Status' => 'Active' ) ) ) ) );
fresh();
an_account( 62, 'Ada Example', 'ada@example.test', array( 'administrator' ) );
$adm = selected();
WPCPM_Mentor_Activation::activate( $adm, 0 );
$adm_made = WPCPM_Mentor_Application::events( $adm );
ck( 'an Administrator who applied keeps their roles and is sent no invitation, and the activated event\'s note says why',
	array( WPCPM_Mentor_Application::user_of( get_post( $adm ) ), get_userdata( 62 )->roles, $GLOBALS['queue'], 'activated' === $adm_made[ count( $adm_made ) - 2 ]['event'] && false !== strpos( $adm_made[ count( $adm_made ) - 2 ]['note'], 'no invitation was sent' ) ),
	array( 62, array( 'administrator' ), array(), true ) );
fresh();
an_account( 63, 'Ada Example', 'ada@example.test', array( 'wpcpm_student' ) );
ck( 'a student\'s account is linked, not duplicated', array( WPCPM_Mentor_Activation::activate( selected(), 0 )['user_id'], get_userdata( 63 )->roles, count( $GLOBALS['users'] ) ), array( 63, array( 'wpcpm_student', 'wpcpm_mentor' ), 1 ) );
fresh();
an_account( 66, 'Hal Example', 'old-address@example.test', array( 'subscriber' ), array( 'wpcpm_mentor_record_id' => 'recRETURNING00001', 'wpcpm_mentor_profile' => 'https://profiles.wordpress.org/ada-example/' ) );
$GLOBALS['users'][66]->user_login = 'hal-old';
ck( 'an account found by its stored profile is the one made a mentor, with its own row: no second account by the new address',
	array( WPCPM_Mentor_Activation::activate( selected(), 0 )['user_id'], count( $GLOBALS['users'] ), $GLOBALS['updated'][0]['id'] ),
	array( 66, 1, 'recRETURNING00001' ) );
fresh();
an_account( 65, 'Gus Example', 'gus@example.test', array(), array() );
$GLOBALS['users'][65]->user_login = 'ada-example';
$gus                              = selected();
WPCPM_Mentor_Activation::activate( $gus, 7, 'Wrote in on Monday.' );
$made = WPCPM_Mentor_Application::events( $gus );
ck( 'the press\'s note and what the provisioning noticed are kept in the history, by who pressed',
	array( $made[ count( $made ) - 2 ]['actor'], $made[ count( $made ) - 2 ]['note'] ),
	array( 7, 'Wrote in on Monday. Added the Mentor role to the existing account "ada-example" (Ada Example). Linked mentor record to the pre-existing account "ada-example" (Ada Example) by username. Confirm it is the same person.' ) );

echo "\n=== Airtable says no ===\n";
fresh();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$bo                       = selected();
$r                        = WPCPM_Mentor_Activation::activate( $bo, 0 );
ck( 'Airtable refusing the row keeps nobody waiting: the account made, the invitation queued, the application active, the refusal on it in Airtable\'s words',
	array( $r['outcome'], $r['airtable'], WPCPM_Mentor_Application::state_of( get_post( $bo ) ), $GLOBALS['queue'], WPCPM_Mentor_Application::airtable_of( get_post( $bo ) )['state'], WPCPM_Mentor_Application::airtable_of( get_post( $bo ) )['error'], last_events( $bo, 1 ) ),
	array( 'activated', 'refused', 'active', array( $r['user_id'] ), 'refused', 'Airtable refused the write.', array( 'Airtable row refused' ) ) );
ck( 'the account holds no record id until the row is written, waits for its row, and is one with no row yet',
	array( get_user_meta( $r['user_id'], 'wpcpm_mentor_record_id', true ), get_user_meta( $r['user_id'], WPCPM_Mentor_Provision::USER_ROW_PENDING, true ), WPCPM_Mentor_Activation::unwritten_count() ),
	array( '', 1, 1 ) );
$GLOBALS['refuse_create'] = null;
ck( 'Write the Airtable row again writes it, stores the record id on the account and says so, by whoever pressed',
	array( WPCPM_Mentor_Activation::retry_airtable( $bo, 7 )['outcome'], get_user_meta( $r['user_id'], 'wpcpm_mentor_record_id', true ), last_events( $bo, 1 ), WPCPM_Mentor_Activation::unwritten_count(), get_user_meta( $r['user_id'], WPCPM_Mentor_Provision::USER_ROW_PENDING, true ) ),
	array( 'written', 'recNEWMENTOR00001', array( 'Airtable row written' ), 0, '' ) );
ck( 'and a second press writes nothing', array( WPCPM_Mentor_Activation::retry_airtable( $bo, 7 )['outcome'], count( $GLOBALS['created'] ) ), array( 'not-needed', 1 ) );
fresh();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'The connection was reset.' );
$died                     = selected();
WPCPM_Mentor_Activation::activate( $died, 0 );
$GLOBALS['refuse_create'] = null;
$GLOBALS['by_email']      = array( 'recDIEDROW0000001' );
ck( 'a retry finds the row a request that died had made, by its address, and creates nothing',
	array( WPCPM_Mentor_Activation::retry_airtable( $died, 7 )['outcome'], $GLOBALS['created'], WPCPM_Mentor_Application::airtable_of( get_post( $died ) )['record'] ),
	array( 'written', array(), 'recDIEDROW0000001' ) );
fresh();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$di                       = selected();
$r                        = WPCPM_Mentor_Activation::activate( $di, 0 );
update_user_meta( $r['user_id'], 'wpcpm_mentor_record_id', 'recBYHAND00000001' );
$GLOBALS['refuse_create'] = null;
ck( 'a row a person made by hand that the sync linked meanwhile is not written twice: the retry finds the account\'s record and writes nothing',
	array( WPCPM_Mentor_Activation::retry_airtable( $di, 7 )['outcome'], $GLOBALS['created'], WPCPM_Mentor_Application::airtable_of( get_post( $di ) )['state'], WPCPM_Mentor_Application::airtable_of( get_post( $di ) )['record'] ),
	array( 'not-needed', array(), 'found', 'recBYHAND00000001' ) );
fresh();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$jo                       = selected();
$r                        = WPCPM_Mentor_Activation::activate( $jo, 0 );
$report                   = array();
WPCPM_Mentor_Provision::provision( array( 'record_id' => 'recBYHAND00000002', 'name' => 'Ada Example', 'profile' => 'https://profiles.wordpress.org/ada-example/', 'email' => 'ada@example.test' ), $report );
ck( 'once the sync links the account to a row, it is no longer one with no row, and the sync may revoke it as any other',
	array( get_user_meta( $r['user_id'], WPCPM_Mentor_Provision::USER_ROW_PENDING, true ), WPCPM_Mentor_Activation::unwritten_count() ), array( '', 0 ) );
ck( 'and the application settles: its Airtable line reads found with that record, and the history says who may have linked it',
	array( WPCPM_Mentor_Application::airtable_of( get_post( $jo ) )['state'], WPCPM_Mentor_Application::airtable_of( get_post( $jo ) )['record'], last_events( $jo, 1 ), end( WPCPM_Mentor_Application::events( $jo ) )['note'] ),
	array( 'found', 'recBYHAND00000002', array( 'Airtable row written' ), 'Linked by the mentors sync or by hand; nothing was written from here.' ) );
$GLOBALS['settings']['application_approved_days'] = 30;
ck( 'so retention no longer keeps it for ever: past the approved days it goes, as any written one does', array( WPCPM_Mentor_Application::purge( time() + 31 * DAY_IN_SECONDS ), get_post( $jo ) ), array( 1, null ) );
fresh();
$GLOBALS['write_log']     = array();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$ky                       = selected();
WPCPM_Mentor_Activation::activate( $ky, 0 );
ck( 'the account is marked as waiting for its row before the application says its row is pending',
	array_slice( array_values( array_intersect( $GLOBALS['write_log'], array( WPCPM_Mentor_Provision::USER_ROW_PENDING, WPCPM_Mentor_Application::META_AIRTABLE ) ) ), 0, 2 ),
	array( WPCPM_Mentor_Provision::USER_ROW_PENDING, WPCPM_Mentor_Application::META_AIRTABLE ) );
delete_user_meta( WPCPM_Mentor_Application::user_of( get_post( $ky ) ), WPCPM_Mentor_Provision::USER_ROW_PENDING );
update_user_meta( WPCPM_Mentor_Application::user_of( get_post( $ky ) ), 'wpcpm_mentor_record_id', 'recBYHAND00000003' );
$GLOBALS['opts'][ WPCPM_Mentor_Application::lock_name( $ky ) ] = (string) ( time() + 600 );
ck( 'settle() leaves an application whose lock another request holds', array( WPCPM_Mentor_Activation::settle( get_post( $ky ) ), WPCPM_Mentor_Application::airtable_of( get_post( $ky ) )['state'] ), array( false, 'refused' ) );
unset( $GLOBALS['opts'][ WPCPM_Mentor_Application::lock_name( $ky ) ] );
fresh();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$lo                       = selected();
WPCPM_Mentor_Activation::activate( $lo, 0 );
unset( $GLOBALS['users'][ WPCPM_Mentor_Application::user_of( get_post( $lo ) ) ] );
$GLOBALS['settings']['application_approved_days'] = 30;
ck( 'an application whose account is gone settles as gone, so retention applies to it',
	array( WPCPM_Mentor_Activation::settle( get_post( $lo ) ), WPCPM_Mentor_Application::airtable_of( get_post( $lo ) )['state'], WPCPM_Mentor_Application::purge( time() + 31 * DAY_IN_SECONDS ), get_post( $lo ) ),
	array( true, 'gone', 1, null ) );
fresh();
$GLOBALS['connected'] = false;
$ed                   = selected();
$r                    = WPCPM_Mentor_Activation::activate( $ed, 0 );
ck( 'with Airtable not connected the mentor is made and the refusal says so, and nothing is asked of Airtable',
	array( $r['outcome'], WPCPM_Mentor_Application::airtable_of( get_post( $ed ) )['error'], $GLOBALS['created'], $GLOBALS['updated'], $GLOBALS['searched'] ),
	array( 'activated', 'Airtable is not connected.', array(), array(), array() ) );
ck( 'Write the Airtable row again is refused on an application that is not active', WPCPM_Mentor_Activation::retry_airtable( selected(), 7 )['outcome'], 'not-active' );

echo "\n=== The mentors sync meanwhile ===\n";
fresh();
an_account( 81, 'Waiting Example', 'w81@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recRETURNING00001', 'wpcpm_mentor_active' => 1, WPCPM_Mentor_Provision::USER_ROW_PENDING => 1 ) );
an_account( 82, 'Fresh Example', 'f82@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recNEWMENTOR00001', 'wpcpm_mentor_active' => 1, WPCPM_Mentor_Provision::USER_ACTIVATED_AT => time() ) );
an_account( 83, 'Gone Example', 'g83@example.test', array( 'wpcpm_mentor' ), array( 'wpcpm_mentor_record_id' => 'recGONE0000000001', 'wpcpm_mentor_active' => 1 ) );
revoke( time() - 60 );
ck( 'the mentors sync leaves an account alone while its row is not written, and one made a mentor during its run, and revokes the rest as before',
	array( get_user_meta( 81, 'wpcpm_mentor_active', true ), get_user_meta( 82, 'wpcpm_mentor_active', true ), get_user_meta( 83, 'wpcpm_mentor_active', true ) ),
	array( 1, 1, 0 ) );

echo "\n=== Two at once ===\n";
fresh();
$fa   = selected();
$held = (string) ( time() + 600 );
$GLOBALS['opts'][ WPCPM_Mentor_Application::lock_name( $fa ) ] = $held;
ck( 'an activation already in hand is refused, and nothing is made', array( WPCPM_Mentor_Activation::activate( $fa, 0 )['outcome'], count( $GLOBALS['users'] ), $GLOBALS['created'], $GLOBALS['queue'], WPCPM_Mentor_Application::state_of( get_post( $fa ) ) ), array( 'busy', 0, array(), array(), 'selected' ) );
ck( 'and the lock another request holds is left to it', lock_row( $fa ), $held );
ck( 'the lapse clock leaves an application whose activation is in hand', array( WPCPM_Mentor_Application::lapse( time() + 91 * DAY_IN_SECONDS ), WPCPM_Mentor_Application::state_of( get_post( $fa ) ) ), array( 0, 'selected' ) );
$GLOBALS['opts'][ WPCPM_Mentor_Application::lock_name( $fa ) ] = (string) ( time() - 1 );
ck( 'a lock left by a request that died is taken over once it has run out, and let go of after', array( WPCPM_Mentor_Activation::activate( $fa, 0 )['outcome'], lock_row( $fa ) ), array( 'activated', null ) );

echo "\n=== No account ===\n";
fresh();
$GLOBALS['insert_error'] = new WP_Error( 'existing_user_login', 'Sorry, that username already exists!' );
$gu                      = selected();
ck( 'an account WordPress will not make leaves the application selected, with why, and nothing goes to Airtable, the queue or Slack',
	array( WPCPM_Mentor_Activation::activate( $gu, 0 )['outcome'], WPCPM_Mentor_Application::state_of( get_post( $gu ) ), WPCPM_Mentor_Application::problem_of( get_post( $gu ) )['message'], $GLOBALS['created'], $GLOBALS['queue'], $GLOBALS['slack'] ),
	array( 'no-account', 'selected', 'Could not create the account "ada-example": Sorry, that username already exists!', array(), array(), array() ) );
$GLOBALS['insert_error'] = null;
ck( 'and once it can be made, the reason goes', array( WPCPM_Mentor_Activation::activate( $gu, 0 )['outcome'], WPCPM_Mentor_Application::problem_of( get_post( $gu ) ) ), array( 'activated', array() ) );

echo "\n=== The Activated list ===\n";
fresh();
$GLOBALS['refuse_create'] = new WP_Error( 'airtable', 'Airtable refused the write.' );
$first                    = selected();
WPCPM_Mentor_Activation::activate( $first, 0 );
$GLOBALS['refuse_create'] = null;
$later                    = array();
for ( $i = 0; $i < 51; $i++ ) {
	$one     = selected( array( 'email' => 'm' . $i . '@example.test', 'profile' => 'mentor-' . $i ) );
	$later[] = $one;
	WPCPM_Mentor_Activation::activate( $one, 0 );
	update_post_meta( $one, WPCPM_Mentor_Application::META_ACTIVATED_AT, time() + $i );
}
$drawn = array_map( function ( $p ) { return $p->ID; }, WPCPM_Mentor_Activation::activated_posts( 50 ) );
ck( 'the Activated list puts the row waiting for its Airtable write first, even behind 51 newer ones, then the newest',
	array( $drawn[0], $drawn[1], count( $drawn ) ), array( $first, end( $later ), 50 ) );
```

In `bin/test-mentor-application.php`, with the real lock over `bin/stubs/options-lock-wpdb.php`: `'the daily run lapses, then settles, then purges'` (the body of `daily()` read from the source, `lapse(`, `settle_all(` and `purge(` in that order), and `'retention keeps an active application whose Airtable row is not written, whatever the approved days say'` (`application_approved_days` 30, an active row 40 days old with `META_AIRTABLE` `refused`, and one `created`: `purge()` answers 1 and the refused one is there). In `bin/test-settings.php`: `'Make mentors from applications is off by default, saved by the Students and mentors tab, and kept off when it is unticked'` (the default false, the key in `scope_keys( 'people' )`, a save without it false), and the fold's new sentence where the suite pins the Invitation emails row. In `bin/test-uninstall.php`: `'a lock an application left behind goes with the rest, and the two account keys of an activation'`, a `wpcpm_mapp_lock_` row swept and both user meta keys deleted.

- [ ] **Step 2: Run them to make sure they fail**

Run: `php bin/test-mentor-activation.php; php bin/test-mentor-application.php; php bin/test-settings.php`
Expected: a fatal error, `Class "WPCPM_Mentor_Activation" not found`, from the first; `FAIL` on the new checks of the other two.

- [ ] **Step 3: Implement the switch, `activate()`, `retry_airtable()`, the two lists, and the record's and the sync's care**

`activate()` and `retry_airtable()` in the orders above; `enabled()` reads `mentor_activation_enabled`. `unwritten()` and `activated_posts()` read the active applications with `applications()` and order them with `array_multisort()` on `META_ACTIVATED_AT` and the ID. In `phase_revoke()`, after the record id check, an account holding `USER_ROW_PENDING`, or whose `USER_ACTIVATED_AT` is at or after `$state['started']`, is passed by, with a comment saying why: a mentor an activation is holding, whose row Airtable has not got or got after this run read the table. The settings row and the fold's sentence as the Interfaces say. The class docblock names design section 3, decisions 6 and 7, and section 7, and the defaults it takes (owner items 1, 3 and 4).

- [ ] **Step 4: Run the tests to make sure they pass**

Run: `php bin/test-mentor-activation.php; php bin/test-mentor-application.php; php bin/test-settings.php; php bin/test-uninstall.php; php bin/test-students-sync.php`
Expected: `ALL PASS` from each.

- [ ] **Step 5: The checks every task ends with, then commit**

```bash
git add includes/modules/class-wpcpm-mentor-activation.php includes/modules/class-wpcpm-mentor-application.php includes/modules/class-wpcpm-mentors-sync.php includes/class-wpcpm-settings.php includes/class-wpcpm-settings-screen.php wpcredits-program-manager.php uninstall.php bin/test-mentor-activation.php bin/test-mentor-application.php bin/test-settings.php bin/test-uninstall.php
git commit -m "Mentor applications: one step makes a selected applicant a mentor, behind a switch that stays off until the launch: the account first, then the invitation, then the Airtable row, which can be written again when Airtable refuses" -m "<the Co-Authored-By trailer>"
```

---

### Task 4: The invitation carries the welcome

`invite-mentor` gains the Welcome's paragraphs after its own lines (design, section 8), and, since the welcome asks the mentor to reply, the mentor's invitation carries the program's Reply-To, as the design's section 8 has every outward email do (invitations carry none today). Sending is 1.122.32's: every invitation, the queue's included, already goes through `send_invite_now()` under the account's lock, so this task adds no lock.

**Files:**
- Modify: `includes/mail/templates/invitations.php` (`invite-mentor`'s `body` and `trigger`), `includes/class-wpcpm-mail.php` (`welcome_email()`'s headers for the mentor's invitation)
- Test: `bin/test-invitation-text.php`, `bin/test-mail-templates.php`

**Interfaces:**
- Consumes: `WPCPM_Mail_Templates::program_contact()`. (The applicant emails' `WPCPM_Mentor_Application_Form::reply_headers()` is the same rule, one line over `program_contact()`; the mail layer reads `program_contact()` itself, so the invitation suite, which loads no form class, stays as it is.)
- Produces: `WPCPM_Mail::welcome_email()` adds `Reply-To: <the address>` to the mentor's invitation while `program_contact()` answers one, and nothing to the other invitations; core hands `headers` as `''`, which becomes `array( 'Reply-To: ...' )`, and a non-empty string is kept as the first line before it. The shipped `invite-mentor` body is `$wpcpm_body( ... )` as it stands, then a blank line, then the welcome's lines below, with no new placeholder; its trigger reads "When a mentor is sent the invitation to their account: when an application to mentor is activated, from the Mentors screen, or as the sync creates the account with invitation emails on.". The invitation stays the mentor's in the catalog (`TYPE_MENTOR`): the account exists when it goes (the question section 3, decision 8, left to M2).

**The welcome, word for word** (the Mentors 3 automation's paragraphs as they stood on 17 October 2026, its private links taken out, US English, no dash, the product names in full; its "today you will receive an invitation email" and "Do you not find your invitation email?" paragraphs are this email's own lines already, set password and "Lost your password?", so they are not repeated; the channel is named by what it is; the site's signature and footer follow it):

```text
Thank you, and congratulations on completing the WordPress Credits Mentor's Course! As soon as the first students are assigned to you, you will receive the introduction email connecting you with them.

Here are a few details you will need for now.

Your Mentor Report Card on this site has your students on it, a triage of who needs a call, your call notes, and a calendar students book directly from. Once you have set your password, check that your account details look right and set your availability: students cannot book a call with you until you have.

Start here:
- Mentors: Welcome to your Mentor Report Card, https://wordpresseducation.org/2026/08/03/welcome-to-your-mentor-report-card/
- Students: Welcome to your Student Report Card, https://wordpresseducation.org/2026/08/03/welcome-to-your-student-report-card/

Access to the student courses, so you always know what is being asked of your mentees (join each course through its invitation link to see it):
- WordPress Credits Foundations (the base course, for 150 hours or more): https://learn.wordpress.org/course/wordpress-credits-foundations/
- WordPress Credits Dev Track: https://learn.wordpress.org/course/wordpress-credits-developer-track/
- WordPress Credits Design Track: https://learn.wordpress.org/course/wordpress-credits-designer-track/
- WordPress Credits: Intro to Contribution (short contributions that are easy to start with, for 50 hours): https://learn.wordpress.org/course/wordpress-credits-intro-to-contribution/
[Each course's invitation link and the mentor resource folder's link are added here in Tools > Emails before this email is sent to mentors.]

Private Slack channel: we will invite you to the program's private Slack channel for mentors, where you can report updates or ask any questions that come up during your mentorship.

Discount codes for students' tools: you will find them in the canvas inside that channel. Please share them only with your students, and only when they ask for each one. Thank you!

We have shared the mentor resource folder with you. Feel free to create your own subfolder to organize your notes on your students and any related resources.

If you have any questions, please reply to this email or write in the private Slack channel.

Thanks so much for your help, and a big hug.
```

Each paragraph, each list line and the bracketed line is a translatable string of its own; a line holding a link takes it as a `%s` with a translator comment. The six links are the only ones in it.

- [ ] **Step 1: Write the failing tests**

In `bin/test-invitation-text.php`, beside `reworded()`, helpers the suite adds: `welcome_lines()` returns the block above as lines (the suite's own copy, so a drift in the template is caught); `welcomed( $message, array $case, &$hits )` appends `"\r\n" . implode( "\r\n", welcome_lines() ) . "\r\n"` to a recorded body when the case's roles hold the Mentor role, whose invitation the mentor's is, counting the case in `$welcomed_in`; `headers_of( array $case )` builds the case's invitation as `invitation()` does, from a core email whose `headers` is `''`, as core's is, and answers the filtered `headers`; and a stand-in `WPCPM_Settings::get_value()` answering `$GLOBALS['contact']` for `mail_contact`, empty unless a check sets it, which keeps the footer as recorded. The loop compares `$got['message']` with `welcomed( reworded( $want['message'] ), $case ) . footer_of()`, so every other invitation still matches the recording byte for byte, and the header docblock adds the second change since the recording, 1.122.33's welcome.

```php
ck( 'and the welcome follows the mentor\'s invitations, once each, and no other invitation',
	$welcomed_in, array( 'mentor-7-1', 'mentor-7-1-no-page', 'mentor-6-5', 'mentor-and-institution-7-1' ) );
$GLOBALS['contact'] = 'programs@example.test';
ck( 'with Contact address in emails set, the mentor\'s invitation carries the program\'s Reply-To, since its welcome asks for a reply, and the student\'s none',
	array( headers_of( $cases['mentor-7-1'] ), headers_of( $cases['student-7-1'] ) ),
	array( array( 'Reply-To: programs@example.test' ), '' ) );
$GLOBALS['contact'] = '';
ck( 'and with none set, the mentor\'s invitation keeps core\'s headers as they came', headers_of( $cases['mentor-7-1'] ), '' );
```

In `bin/test-mail-templates.php`:

```php
$shipped = WPCPM_Mail_Templates::get( 'invite-mentor' );
preg_match_all( '#https?://[^\s]+#', $shipped['body'], $links );
$found = WPCPM_Mail_Templates::placeholders_in( $shipped['body'] );
sort( $found );
ck( 'the mentor\'s invitation holds the six public links of its welcome and no other link', $links[0], array(
	'https://wordpresseducation.org/2026/08/03/welcome-to-your-mentor-report-card/',
	'https://wordpresseducation.org/2026/08/03/welcome-to-your-student-report-card/',
	'https://learn.wordpress.org/course/wordpress-credits-foundations/',
	'https://learn.wordpress.org/course/wordpress-credits-developer-track/',
	'https://learn.wordpress.org/course/wordpress-credits-designer-track/',
	'https://learn.wordpress.org/course/wordpress-credits-intro-to-contribution/',
) );
ck( 'and says once, in brackets, where the private links go', substr_count( $shipped['body'], '[Each course\'s invitation link and the mentor resource folder\'s link are added here in Tools > Emails before this email is sent to mentors.]' ), 1 );
ck( 'and its placeholders are the invitation\'s own: the welcome adds none', $found, array( 'dashboard_link', 'login_url', 'set_password_link', 'username' ) );
```

- [ ] **Step 2: Run them to make sure they fail**

Run: `php bin/test-invitation-text.php; php bin/test-mail-templates.php`
Expected: `FAIL` on the four mentor cases' bodies, the Reply-To and the welcome's checks.

- [ ] **Step 3: Implement the welcome and the Reply-To**

In `invitations.php`, a `$wpcpm_welcome` built once from the lines above and appended to `invite-mentor`'s body after a blank line, joined with `"\n"` as `$wpcpm_body` is (the registry gives the email its `"\r\n"`). In `welcome_email()`, for the `mentor` kind, the headers as the Interfaces say.

- [ ] **Step 4: Run them to make sure they pass**

Run: `php bin/test-invitation-text.php; php bin/test-mail-templates.php; php bin/test-mail.php; php bin/test-emails-templates.php; php bin/test-mail-capture.php`
Expected: `ALL PASS` from each.

- [ ] **Step 5: The checks every task ends with, then commit**

```bash
git add includes/mail/templates/invitations.php includes/class-wpcpm-mail.php bin/test-invitation-text.php bin/test-mail-templates.php
git commit -m "Mentor applications: the mentor's invitation carries the welcome and the program's Reply-To" -m "<the Co-Authored-By trailer>"
```

---

### Task 5: The Mentor Status Checker reads the site

The checker's source becomes the selected applications (design, section 3, decision 5; section 6): newest selection first, each profile checked exactly as today, and a row where the course shows activated through Task 3's step, or, while Make mentors from applications is off, marked as waiting. Airtable's rows at the source status keep being read after the site's until a person ticks **Stop checking mentors in Airtable** (the controller's ruling, a departure from decision 5); the box is a stop, so a save of the checker's screen from a stale page, which leaves it unticked, keeps them read.

**Files:**
- Modify: `includes/tools/class-wpcpm-mentor-checker-runner.php`, `includes/tools/class-wpcpm-mentor-checker.php` (the settings row, `settings_keys()`, `is_ready()`, `status_line()`, the lede, the warning, the buttons, `ajax_promote()`, `ajax_promote_all()`, `render_row()`'s actions), `includes/tools/class-wpcpm-mentor-checker-slack.php` (`record()`, `waiting()`, `message()`), `includes/class-wpcpm-settings.php` (default, scope, flag), `includes/modules/class-wpcpm-mentor-application.php` (`META_CHECKED`, `note_check()`, `last_check()`), `includes/class-wpcpm-cli.php` (`check_mentors`)
- Test: `bin/test-mentor-checker.php`, `bin/test-settings.php`, `bin/test-cli.php`

**Interfaces:**
- Consumes: Task 3's `WPCPM_Mentor_Activation::activate( $post_id, $actor )` and `enabled()`; `WPCPM_Mentor_Application::applications()`, `application()`, `state_of()`, `last_at()`, `name_of()`, `handle_of()`, `profile_url()`, `EVENT_SELECTED`, `STATE_SELECTED`.
- Produces:
  - Setting `checker_skip_airtable`, default `false`, in the `tool:mentor-status-checker` scope and in `settings_keys()`, cleaned in the flags loop with `checker_cron_enabled`; drawn under **Check mentors with status** as the box **Stop checking mentors in Airtable**, described "Tick it once no mentor in Airtable holds the status above: until then the mentors selected in Airtable before applications moved to this site are checked too."; `WPCPM_Mentor_Checker::config()['skip_airtable']`.
  - `WPCPM_Mentor_Checker::is_ready()` is true without the Airtable connection; `status_line()` while not connected says "Airtable is not connected: the applications selected on this site are checked, and Airtable's mentors are not." and otherwise what it says today. The Tools screen and the Overview print that line as they print every tool's.
  - `WPCPM_Mentor_Application::META_CHECKED = '_wpcpm_mapp_checked'`; `note_check( $post_id, $state, $message )` writes `array( 'at' => time(), 'state' => $state, 'message' => $message )`; `last_check( WP_Post $post ): array`, or `array()` for never.
  - `WPCPM_Mentor_Checker_Runner::APP_KEY = 'app-'`; `site_rows(): array`, each `array( 'id' => 'app-<ID>', 'application' => <ID>, 'name' => ..., 'profile' => <the profile's address>, 'status' => 'Selected on this site', 'source' => 'site' )`, by the last selection newest first, the ID descending on a tie; Airtable's rows carry `'source' => 'airtable'` and `'application' => 0`; `application_of( $key ): int`, the ID a key names (`app-` and digits to the end) or 0.
  - `check_mentor()` on a site row: `note_check()` always; completed and applying with the switch on, `activate( <ID>, <0 under cron or WP-CLI, else the current user> )`, the row's action `activated` (note "Made a mentor on this site."), `skipped` for `already` or `already-mentor` ("Already a mentor on this site; left unchanged."), or `failed` with the outcome's message, and its status "Made a mentor"; completed and not applying, or applying with the switch off, `eligible` with "Ready to be made a mentor." (switch on) or "Ready to be made a mentor; it waits until Make mentors from applications is switched on." (switch off). `verify_and_promote( $key )` takes an `app-` key (a row no longer selected is `WP_Error` `wpcpm_checker_not_selected`, "That application is no longer waiting for the course."); `ajax_promote_all()` takes the eligible site rows with the Airtable ones. `summarize()` keys, in this order: `checked`, `completed`, `eligible`, `promoted`, `activated`, `skipped`, `failed`, `problems`. A failed Airtable read with site rows to check keeps the site rows and stores the error in the run as `airtable_error`; with none, `start()` answers the error as today.
  - `run_cron()` runs while the daily check is on, with or without the Airtable connection; Airtable's rows are read only while connected and `skip_airtable` is false.
  - `wp wpcredits check_mentors` opens with "Checking the applications selected on this site and the Airtable mentors with status "%1$s" for "%2$s"%3$s" while Airtable's are read, and "Checking the applications selected on this site for "%2$s"%3$s" while they are not, then, when the reason is the connection, "Airtable is not connected: only the applications selected on this site are checked."; `%3$s` is ` - will promote to "%s".` (the target status) or ` - report only.`, as today.
  - `WPCPM_Mentor_Checker_Slack::record()` keeps `source` (`site`, or `''`); `waiting()` reads it back; `message()` ends a site entry's line with " - from an application on the site" (owner item 6).

- [ ] **Step 1: Write the failing tests**

`bin/test-mentor-checker.php` keeps its own world: `reset_world()`, `WPCPM_Settings::$values`, `WPCPM_Airtable::mentor()` and `$records`, `WPCPM_Mentor_Checker_Profile::$completed`, the signed-in `$GLOBALS['user']` (`new WP_User( 7, 'Ada Admin' )`), `$GLOBALS['doing_cron']`, `ajax( $method )` and `waiting()`. Stand-ins and helpers this task adds to it, each reset by `reset_world()`: a `WP_Post` class with `ID`; `WPCPM_Mentor_Application` with `STATE_SELECTED`, `EVENT_SELECTED` and `public static $selected` (ID to `name`, `handle`, `selected_at`) and `$checked`, answering `applications()` with a post for each ID, `application()`, `state_of()` (`selected` for an ID in `$selected`, `active` otherwise), `last_at()` (the `selected_at`), `name_of()`, `handle_of()`, `profile_url()` (the real one's formula) and `note_check()` (appending `array( $id, $state )` to `$checked`); `WPCPM_Mentor_Activation` with `public static $calls`, `$answer` (`array( 'outcome' => 'activated', 'user_id' => 9, 'airtable' => 'created', 'message' => '' )` by default) and `$enabled` (true), answering `activate()` (appending `array( $id, $actor )` to `$calls`) and `enabled()`; `WPCPM_Settings::$connected` (true), which `is_connected()` answers, and `'checker_skip_airtable' => false` among `get()`'s defaults; `WPCPM_Airtable::$fail_read`, making `fetch_all()` answer `new WP_Error( 'airtable', 'Airtable is out of reach.' )`; `WPCPM_Mentor_Checker_Profile::$cache_used`, the `$use_cache` it was last asked with; and `get_current_user_id()` answering `$GLOBALS['user']->ID`.

```php
echo "\n=== The site's applications ===\n";
reset_world();
WPCPM_Mentor_Application::$selected = array(
	501 => array( 'name' => 'Ada Example', 'handle' => 'ada-example', 'selected_at' => 100 ),
	502 => array( 'name' => 'Bo Example', 'handle' => 'bo-example', 'selected_at' => 300 ),
	503 => array( 'name' => 'Cy Example', 'handle' => 'cy-example', 'selected_at' => 300 ),
);
WPCPM_Airtable::mentor( 'recVETTED00000001', 'Di Example', 'https://profiles.wordpress.org/di-example/' );
WPCPM_Mentor_Checker_Profile::$completed = array( 'bo-example', 'di-example' );
ck( 'Airtable\'s mentors are checked after the site\'s until Stop checking mentors in Airtable is ticked, and the site\'s newest selection first, the higher ID first on a tie',
	array_column( ( new WPCPM_Mentor_Checker_Runner() )->start( false )['queue'], 'record_id' ), array( 'app-503', 'app-502', 'app-501', 'recVETTED00000001' ) );
WPCPM_Settings::$values = array( 'checker_skip_airtable' => true );
ck( 'and once it is ticked, only the site\'s', array_column( ( new WPCPM_Mentor_Checker_Runner() )->start( false )['queue'], 'record_id' ), array( 'app-503', 'app-502', 'app-501' ) );
WPCPM_Settings::$values    = array();
WPCPM_Settings::$connected = false;
$result                    = ( new WPCPM_Mentor_Checker_Runner() )->run_all( true );
ck( 'without the Airtable connection the site\'s applications are checked, and the one whose course shows is made a mentor by whoever ran the check',
	array( count( $result['rows'] ), WPCPM_Mentor_Activation::$calls, $result['summary']['activated'], array_keys( $result['summary'] ) ),
	array( 3, array( array( 502, 7 ) ), 1, array( 'checked', 'completed', 'eligible', 'promoted', 'activated', 'skipped', 'failed', 'problems' ) ) );
ck( 'every site row\'s check is kept on its application', WPCPM_Mentor_Application::$checked, array( array( 503, 'not_completed' ), array( 502, 'completed' ), array( 501, 'not_completed' ) ) );
ck( 'and the row reads what happened', array( $result['rows'][1]['action'], $result['rows'][1]['action_note'], $result['rows'][1]['status'] ), array( 'activated', 'Made a mentor on this site.', 'Made a mentor' ) );
WPCPM_Settings::$connected        = true;
WPCPM_Mentor_Activation::$calls   = array();
WPCPM_Mentor_Activation::$enabled = false;
$waits                            = ( new WPCPM_Mentor_Checker_Runner() )->run_all( true );
ck( 'an application whose course shows waits while Make mentors from applications is off, and nobody is made a mentor',
	array( $waits['rows'][1]['action'], $waits['rows'][1]['action_note'], WPCPM_Mentor_Activation::$calls ),
	array( 'eligible', 'Ready to be made a mentor; it waits until Make mentors from applications is switched on.', array() ) );
WPCPM_Mentor_Activation::$enabled = true;
$dry                              = ( new WPCPM_Mentor_Checker_Runner() )->run_all( false );
ck( 'report only, a site row whose course shows is ready, and nobody is made a mentor', array( $dry['rows'][1]['action'], $dry['rows'][1]['action_note'], WPCPM_Mentor_Activation::$calls ), array( 'eligible', 'Ready to be made a mentor.', array() ) );
WPCPM_Mentor_Activation::$answer = array( 'outcome' => 'already-mentor', 'user_id' => 9, 'airtable' => '', 'message' => '' );
ck( 'Promote on a site row re-checks the profile, never the cache, and an applicant who is a mentor already is skipped',
	array( ( new WPCPM_Mentor_Checker_Runner() )->verify_and_promote( 'app-502' )['action'], WPCPM_Mentor_Checker_Profile::$cache_used, end( WPCPM_Mentor_Activation::$calls ) ),
	array( 'skipped', false, array( 502, 7 ) ) );
unset( WPCPM_Mentor_Application::$selected[503] );
$gone = ( new WPCPM_Mentor_Checker_Runner() )->verify_and_promote( 'app-503' );
ck( 'and on an application no longer waiting for the course it is refused', array( is_wp_error( $gone ) ? $gone->get_error_code() : $gone, is_wp_error( $gone ) ? $gone->get_error_message() : '' ), array( 'wpcpm_checker_not_selected', 'That application is no longer waiting for the course.' ) );
ck( 'a key names an application only as app- and digits', array( WPCPM_Mentor_Checker_Runner::application_of( 'app-502' ), WPCPM_Mentor_Checker_Runner::application_of( 'app-5x' ), WPCPM_Mentor_Checker_Runner::application_of( "app-502\n" ), WPCPM_Mentor_Checker_Runner::application_of( 'recVETTED00000001' ) ), array( 502, 0, 0, 0 ) );
WPCPM_Mentor_Activation::$answer = array( 'outcome' => 'activated', 'user_id' => 9, 'airtable' => 'created', 'message' => '' );
WPCPM_Mentor_Activation::$calls  = array();
( new WPCPM_Mentor_Checker_Runner() )->run_all( false );
$_POST = array( 'nonce' => 'ok' );
$all   = ajax( 'ajax_promote_all' );
ck( 'Promote all eligible makes a mentor of each eligible site row, with the Airtable ones', array( $all->ok, WPCPM_Mentor_Activation::$calls ), array( true, array( array( 502, 7 ) ) ) );
WPCPM_Airtable::$fail_read = true;
$kept                      = ( new WPCPM_Mentor_Checker_Runner() )->start( false );
ck( 'an Airtable read that fails leaves the site\'s applications checked, and the run keeps the error', array( count( $kept['queue'] ), WPCPM_Mentor_Checker_Runner::get_last_run()['airtable_error'] ), array( 2, 'Airtable is out of reach.' ) );

echo "\n=== The daily check without Airtable ===\n";
WPCPM_Airtable::$fail_read      = false;
WPCPM_Settings::$connected      = false;
$GLOBALS['doing_cron']          = true;
WPCPM_Mentor_Activation::$calls = array();
WPCPM_Mentor_Checker_Runner::run_cron();
$GLOBALS['doing_cron'] = false;
ck( 'the daily check runs without the Airtable connection for the site\'s applications, and makes its mentors as the daily check', WPCPM_Mentor_Activation::$calls, array( array( 502, 0 ) ) );
ck( 'and the tool is ready without it, its line saying what is not checked', array( ( new WPCPM_Mentor_Checker() )->is_ready(), ( new WPCPM_Mentor_Checker() )->status_line() ), array( true, 'Airtable is not connected: the applications selected on this site are checked, and Airtable\'s mentors are not.' ) );

echo "\n=== The Slack line ===\n";
reset_world();
WPCPM_Mentor_Checker_Slack::forget();
WPCPM_Mentor_Checker_Slack::record( array( 'id' => 'app-502', 'name' => 'Bo Example', 'profile' => 'https://profiles.wordpress.org/bo-example/', 'source' => 'site' ) );
ck( 'a mentor made from an application is named as the others are, the line saying where they came from',
	explode( "\n", WPCPM_Mentor_Checker_Slack::message( WPCPM_Mentor_Checker_Slack::waiting() ) )[1],
	'• Bo Example - <https://profiles.wordpress.org/bo-example/|@bo-example> - from an application on the site' );
```

(`run_all()` drives `process_batch()` with the suite's batch size of 1, so each row is its own batch, as the suite's other runs are.) In `bin/test-settings.php`: `'Stop checking mentors in Airtable is unticked by default, saved by the checker\'s own form and in its settings keys, and a save of that form without it keeps Airtable\'s mentors checked'` (the default false, the key in `scope_keys( 'tool:mentor-status-checker' )` and in `settings_keys()`, a save without it false). In `bin/test-cli.php`, with the checker's stand-in config holding `skip_airtable` and `is_configured()` set by a check: `'check_mentors names the sources it reads: the site\'s applications and Airtable\'s mentors, or the site\'s alone, and says when Airtable is not connected'`, its first lines `log: Checking the applications selected on this site and the Airtable mentors with status "Vetted - positive" for "The course" - report only.` while both are read, and `log: Checking the applications selected on this site for "The course" - report only.` then `log: Airtable is not connected: only the applications selected on this site are checked.` while not connected.

- [ ] **Step 2: Run them to make sure they fail**

Run: `php bin/test-mentor-checker.php; php bin/test-settings.php; php bin/test-cli.php`
Expected: `FAIL` on the new checks (the queue holds Airtable's row alone, `application_of()` undefined), and `check_mentors` printing `error: Airtable is not configured.`.

- [ ] **Step 3: Implement the site source, the stop, the record's check and the Slack line**

In the runner, `get_mentors_to_check()` is `site_rows()` followed by Airtable's rows while connected and `skip_airtable` is false; `check_mentor()`, `verify_and_promote()`, `summarize()` and `run_cron()` as the Interfaces say. In the module: `ajax_promote()` takes a key `WPCPM_Airtable::is_record_id()` accepts or one `application_of()` reads; `ajax_promote_all()` collects both; `render_row()` gains the action `activated`, labeled "Made a mentor"; the buttons no longer wait on `is_configured()`; the warning while not connected reads "Airtable is not connected: the applications selected on this site are checked, Airtable's mentors are not, and a mentor made from an application gets no Airtable row until it is." with the settings link; the lede reads "Every application selected on this site, and, until Stop checking mentors in Airtable is ticked, every mentor whose Airtable status is %1$s, is looked up on WordPress.org. Where the contribution history records completing %2$s, an application is made a mentor on this site, and an Airtable mentor's status is changed to %3$s."; and the new box with its description. The run's `airtable_error`, when set, prints above the results and in `check_mentors`. In the CLI, `check_mentors` drops its refusal and opens with the lines the Interfaces give.

- [ ] **Step 4: Run them to make sure they pass**

Run: `php bin/test-mentor-checker.php; php bin/test-settings.php; php bin/test-cli.php; php bin/test-settings-choices.php; php bin/test-overview.php`
Expected: `ALL PASS` from each.

- [ ] **Step 5: The checks every task ends with, then commit**

```bash
git add includes/tools/class-wpcpm-mentor-checker-runner.php includes/tools/class-wpcpm-mentor-checker.php includes/tools/class-wpcpm-mentor-checker-slack.php includes/class-wpcpm-settings.php includes/modules/class-wpcpm-mentor-application.php includes/class-wpcpm-cli.php bin/test-mentor-checker.php bin/test-settings.php bin/test-cli.php
git commit -m "Mentor applications: the Mentor Status Checker reads the applications selected on this site, makes a mentor of each whose course shows once that is switched on, and reads Airtable's mentors until a person stops it" -m "<the Co-Authored-By trailer>"
```

---

### Task 6: The course is completed, the Activated view, and Send the acknowledgement

The Selected view shows the checker's last result beside **The course is completed** (design, section 5 and section 6), or, while the switch is off, that the applicant waits; a sixth view, **Activated**, lists the mentors made from applications, every row still waiting for its Airtable write first, with the invitation's fate since the activation and **Write the Airtable row again** (section 7); and **Send the acknowledgement**, the eighth decision the M1 final review recommended, sends a row in Waiting the acknowledgement it never had, without the Mark as spam and Put back detour.

**Files:**
- Modify: `includes/modules/class-wpcpm-mentor-application-queue.php`, `includes/modules/class-wpcpm-mentor-application.php` (`EVENT_ACKNOWLEDGED`), `includes/modules/class-wpcpm-mentors.php` (`render_tab_sync()`)
- Test: `bin/test-mentor-application-queue.php`, `bin/test-mentors-screen.php`

**Interfaces:**
- Consumes: Task 3's `activate()`, `retry_airtable()`, `enabled()`, `unwritten_count()`, `activated_posts()`, `user_of()`, `airtable_of()`, `problem_of()`, `META_ACTIVATED_AT`; Task 5's `last_check()`; `WPCPM_Mentor_Airtable_Row::left_out_sentence()`; `WPCPM_Mentor_Checker_Slack::flush()`; `WPCPM_Mentor_Application_Form::mail_applicant()`; `WPCPM_Mail::queue()`, `last_invited()`, and 1.122.32's `invite_refused_message( 'administrator' )`; `WPCPM_Mail_Log::find( array( 'exact_email' => ..., 'template' => 'invite-mentor' ), 1, 1 )` (rows newest first, `sent_at` a UTC time), `STATUS_SENT`, `STATUS_FAILED`, `STATUS_UNCONFIRMED`.
- Produces:
  - `WPCPM_Mentor_Application_Queue::ACTION_COMPLETE = 'wpcpm_mapp_complete'` (`handle_complete`), `ACTION_AIRTABLE = 'wpcpm_mapp_airtable'` (`handle_airtable`), `ACTION_ACKNOWLEDGE = 'wpcpm_mapp_acknowledge'` (`handle_acknowledge`), in `decisions()`.
  - `views()` gains `'active' => array( 'states' => array( STATE_ACTIVE ), 'label' => 'Activated' )`, between `selected` and `lapsed`; the Activated view draws `activated_posts( QUEUE_MAX + 1 )`, cut at `QUEUE_MAX` with the sentence the other views print when they cut.
  - `invitation_line( WP_Post $post ): string`, the Activated row's invitation sentence, by the rules below.
  - `WPCPM_Mentor_Application::EVENT_ACKNOWLEDGED = 'acknowledgement sent'`; `acknowledged()` counts it as it counts `EVENT_REOPENED_ACKNOWLEDGED`.
  - `event_words()`: `EVENT_ACKNOWLEDGED` "sent the acknowledgement", `EVENT_AIRTABLE_WRITTEN` "wrote the Airtable row", `EVENT_AIRTABLE_REFUSED` "Airtable refused the row", `EVENT_ALREADY_MENTOR` "filed as already a mentor".
  - Flash keys: `mapp-activated`, `mapp-activated-refused`, `mapp-already`, `mapp-already-mentor`, `mapp-off`, `mapp-busy`, `mapp-no-account`, `mapp-airtable-written`, `mapp-airtable-refused`, `mapp-airtable-not-needed`, `mapp-acknowledged`; an activation answering `not-selected` or `unknown` leaves the existing `mapp-state` and `mapp-unknown`.

**The invitation line, in order** (only what happened at or after the application's `META_ACTIVATED_AT` counts, since a refused or skipped send writes no stamp and the account may hold an older one):

1. Filed as already a mentor: no line. `META_USER` naming an account that no longer exists: "The account is gone, so no invitation can reach it." An account holding the Administrator role: "No invitation: the account is an Administrator's, which signs in already."
2. The account in `WPCPM_Mail::queue()`: "The invitation with the welcome waits in the email queue."
3. The newest `invite-mentor` row of the mail log to the account's address, its `sent_at` at or after the activation: `sent`, "The invitation with the welcome was sent on %s."; `failed`, "The invitation with the welcome went to the mail host on %s." then `invite_refused_message( 'administrator' )`, which says the host refused it, then " Resend invite is on the Mentors screen's Accounts tab."; `unconfirmed`, "The invitation with the welcome was handed over on %s, and the mail log does not say whether it went."
4. No such row (the log keeps thirty days) but `last_invited()` at or after the activation: "The invitation with the welcome was sent on %s."
5. Otherwise: "No invitation has gone since this applicant was made a mentor. Resend invite is on the Mentors screen's Accounts tab." (a send the fifteen minutes or another request's lock passed over, or one refused before the log's thirty days).

**The words** (each a translatable string; the notices in `messages()`):

| Where | Text |
| --- | --- |
| `mapp-activated` (success) | The applicant is a mentor now: their account is made, the Airtable row written, and the invitation with the welcome is on its way. |
| `mapp-activated-refused` (warning) | The applicant is a mentor now: their account is made and the invitation with the welcome is on its way. Airtable refused the row, so Write the Airtable row again waits on it under Activated. |
| `mapp-already` (warning) | That application was made a mentor already. Nothing was changed. |
| `mapp-already-mentor` (success) | That applicant is a mentor on this site already: the application is filed under Activated with a note, and nothing was sent or written to Airtable. |
| `mapp-off` (error) | Nothing was done: Make mentors from applications is off, under Mentor applications on the Students and mentors tab of Settings. It is switched on at the launch. |
| `mapp-busy` (warning) | Another request is making that applicant a mentor at this moment. Reload the page in a minute to see where it got to. |
| `mapp-no-account` (error) | The account could not be made, so the application waits under Selected (course pending), with the reason on it. |
| `mapp-airtable-written` (success) | The Airtable row is written, and the mentor's account holds its record. |
| `mapp-airtable-refused` (error) | Airtable refused the row again. The reason is on the application; nothing else changed. |
| `mapp-airtable-not-needed` (success) | Nothing was written: the mentor's account holds its Airtable record already. |
| `mapp-acknowledged` (success) | The acknowledgement is sent, with the link that confirms the address. The application stays where it was. |
| Selected row, switch off | Making mentors from applications is off until the launch: this applicant waits. (and no press) |
| Selected row, never checked | Not checked yet: the daily check reads the profile next. |
| Selected row, checked | Checked on %s: the course does not show on the profile yet. / Checked on %s: the course shows on the profile. / Checked on %1$s: the profile could not be read (%2$s). |
| Selected row, account refused | The account could not be made on %1$s: %2$s |
| The press | **The course is completed**; its note "How you know, for the next Administrator, optional. It is never sent."; it asks "Make %s a mentor now? Their account is made, the Airtable row written and the invitation with the welcome sent." |
| Activated row | Made a mentor on %s. (or Filed as already a mentor on %s.) / Account: %s (the username, linked to the account's profile screen), or, when `META_USER` names an account that no longer exists, Account: the account made for this mentor no longer exists. / `invitation_line()` / Airtable row written on %s. or The mentor's own Airtable row was set back to Active on %s. or The Mentors row that held this address was set back to Active and linked on %s. or The account holds its Airtable record, linked by the mentors sync or by hand; nothing was written from here. (`found`) or The account made for this mentor no longer exists, so no Airtable row is written for it. (`gone`) or A mentor already: nothing was written. or Airtable refused the row on %1$s: %2$s or The Airtable row has not been written yet. / then `left_out_sentence()` when it says anything |
| The retry | **Write the Airtable row again**, on an Activated row whose Airtable state is `refused` or `pending` and whose account still waits for its row; no note, no question |
| The acknowledgement | **Send the acknowledgement**, on an open row whose acknowledgement never went and which holds an address; it asks "Send %s the acknowledgement, with the link that confirms the address?". `unacknowledged_sentence()`'s last sentence becomes "Send the acknowledgement sends it now, once; for an application filed as spam, Put back in the queue does." |
| The Sync tab (warning) | %s mentor made from an application has no Airtable row yet. / %s mentors made from applications have no Airtable row yet. Then: Write the Airtable row again is on each, under Activated on the Waiting for review tab. (linking `view_url( 'active' )`) |

The tab's description gains "The course is completed, or the daily check finding the course on the profile, makes a selected applicant a mentor once Make mentors from applications is on."

- [ ] **Step 1: Write the failing tests**

`bin/test-mentor-application-queue.php` keeps its own helpers as they stand on main: `reset_world()` (the Administrator 9, Ida Admin, signed in), `an_application( $state, $confirmed, $more, $signals )`, `press( $action, $id, array $more )` with `note`, `nonce` and `view`, answering `stop`, `flash`, `state` and `mail`, `last_event( $id )` answering the event, the actor and the note, `tab( $view )`, `view_part()`, `marks_of()`, `row_ids()` and `age_lines()`. Stand-ins and helpers this task adds to it, each reset by `reset_world()`: `WPCPM_Mentor_Activation` with `public static $calls`, `$retries`, `$answer` (`array( 'outcome' => 'activated', 'user_id' => 70, 'airtable' => 'created', 'message' => '' )`), `$retry_answer` (`array( 'outcome' => 'written', 'message' => '' )`), `$enabled` (true), `$unwritten` (0) and `$activated` (posts), answering `activate()` (appending `array( $id, $actor, $note )`), `retry_airtable()` (appending `array( $id, $actor )`), `enabled()`, `unwritten_count()` and `activated_posts()`; `WPCPM_Mentor_Provision::USER_ROW_PENDING`; `WPCPM_Mentor_Checker_Slack` with `public static $flushed` counted by `flush()`; to the suite's `WPCPM_Mail`, `queue()` from `$GLOBALS['invite_queue']`, `last_invited()` from `$GLOBALS['invited_at']` (account to time) and `invite_refused_message( $reader )` answering the real one's administrator sentence; `WPCPM_Mail_Log` with the three status constants and `find()` answering `array( 'rows' => $GLOBALS['log_rows'], 'total' => count( $GLOBALS['log_rows'] ) )`; `count_forms( $html, $action )`, the number of forms posting that action (`substr_count( $html, 'name="action" value="' . $action . '"' )`); and `activated_at( $id, $time )`, which writes `META_ACTIVATED_AT`. The suite's clock is the shared stand-in's, `$GLOBALS['now']`, 16 October 2026 at 10:00 UTC on a site two hours ahead; a log row's `sent_at` is written in UTC.

```php
echo "\n=== The course is completed ===\n";
reset_world();
$sel = an_application( 'selected' );
update_post_meta( $sel, WPCPM_Mentor_Application::META_CHECKED, array( 'at' => $GLOBALS['now'], 'state' => 'not_completed', 'message' => '' ) );
$selected_tab = view_part( tab( 'selected' ) );
ck( 'the Selected view shows the last check and the press, which asks first',
	array( false !== strpos( $selected_tab, 'Checked on October 16, 2026: the course does not show on the profile yet.' ), false !== strpos( $selected_tab, 'data-wpcpm-confirm="Make Ada Okonjo a mentor now?' ), count_forms( $selected_tab, WPCPM_Mentor_Application_Queue::ACTION_COMPLETE ) ),
	array( true, true, 1 ) );
WPCPM_Mentor_Activation::$enabled = false;
$waiting_tab                      = view_part( tab( 'selected' ) );
ck( 'while Make mentors from applications is off, the row says the applicant waits and offers no press',
	array( false !== strpos( $waiting_tab, 'Making mentors from applications is off until the launch: this applicant waits.' ), count_forms( $waiting_tab, WPCPM_Mentor_Application_Queue::ACTION_COMPLETE ) ),
	array( true, 0 ) );
WPCPM_Mentor_Activation::$enabled = true;
$done                             = press( WPCPM_Mentor_Application_Queue::ACTION_COMPLETE, $sel, array( 'note' => 'Wrote in on Monday.', 'view' => 'selected' ) );
ck( 'The course is completed on a selected row makes the applicant a mentor, by who pressed and with the note, sends the Slack message, and comes back to the Selected view saying so',
	array( $done['flash'], $done['stop'], WPCPM_Mentor_Activation::$calls, WPCPM_Mentor_Checker_Slack::$flushed ),
	array( 'mapp-activated', 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue&mapp_view=selected', array( array( $sel, 9, 'Wrote in on Monday.' ) ), 1 ) );
foreach ( array(
	'refused'        => array( 'activated', 'refused', 'mapp-activated-refused' ),
	'already'        => array( 'already', '', 'mapp-already' ),
	'already-mentor' => array( 'already-mentor', '', 'mapp-already-mentor' ),
	'off'            => array( 'off', '', 'mapp-off' ),
	'busy'           => array( 'busy', '', 'mapp-busy' ),
	'no-account'     => array( 'no-account', '', 'mapp-no-account' ),
	'not-selected'   => array( 'not-selected', '', 'mapp-state' ),
	'unknown'        => array( 'unknown', '', 'mapp-unknown' ),
) as $case => $answer ) {
	WPCPM_Mentor_Activation::$answer = array( 'outcome' => $answer[0], 'user_id' => 0, 'airtable' => $answer[1], 'message' => '' );
	ck( 'and says ' . $case . ' in its own words', press( WPCPM_Mentor_Application_Queue::ACTION_COMPLETE, an_application( 'selected' ), array( 'view' => 'selected' ) )['flash'], $answer[2] );
}
ck( 'it is refused on a row that is not selected, and nothing is activated', array( press( WPCPM_Mentor_Application_Queue::ACTION_COMPLETE, an_application( 'new' ) )['flash'], count( WPCPM_Mentor_Activation::$calls ) ), array( 'mapp-state', 9 ) );
$GLOBALS['manage'] = array();
$refused           = press( WPCPM_Mentor_Application_Queue::ACTION_COMPLETE, $sel, array( 'nonce' => 'nonce-wrong' ) );
ck( 'without the capability it is refused before its nonce is read, and nothing is activated', array( 0 === strpos( $refused['stop'], 'wp_die: You do not have permission' ), count( WPCPM_Mentor_Activation::$calls ) ), array( true, 9 ) );
$GLOBALS['manage'] = array( 9 );
ck( 'and a nonce that is not this press\'s for this row is refused', press( WPCPM_Mentor_Application_Queue::ACTION_COMPLETE, $sel, array( 'nonce' => wp_create_nonce( WPCPM_Mentor_Application_Queue::ACTION_SELECT . '_' . $sel ) ) )['stop'], 'nonce refused: wpcpm_mapp_complete_' . $sel );

echo "\n=== Activated ===\n";
reset_world();
$made    = an_application( 'active' );
$refused = an_application( 'active' );
an_account( 70, 'Ada Okonjo', 'ada@example.test', array( 'wpcpm_mentor' ), array( WPCPM_Mentor_Provision::USER_ROW_PENDING => 1 ) );
foreach ( array( $made => 'created', $refused => 'refused' ) as $id => $state ) {
	update_post_meta( $id, WPCPM_Mentor_Application::META_USER, 70 );
	activated_at( $id, $GLOBALS['now'] );
	update_post_meta( $id, WPCPM_Mentor_Application::META_AIRTABLE, array( 'state' => $state, 'how' => 'create', 'record' => 'created' === $state ? 'recNEWMENTOR00001' : '', 'error' => 'refused' === $state ? 'Airtable refused the write.' : '', 'left_out' => array(), 'at' => $GLOBALS['now'] ) );
}
WPCPM_Mentor_Activation::$activated = array( get_post( $refused ), get_post( $made ) );
$active_tab                         = view_part( tab( 'active' ) );
ck( 'the Activated view draws the rows in the order the list gives, the one waiting for its row first, with the retry only there',
	array( row_ids( $active_tab ), count_forms( $active_tab, WPCPM_Mentor_Application_Queue::ACTION_AIRTABLE ) ), array( array( $refused, $made ), 1 ) );
ck( 'each with its Airtable line',
	array( false !== strpos( $active_tab, 'Airtable refused the row on October 16, 2026: Airtable refused the write.' ), false !== strpos( $active_tab, 'Airtable row written on October 16, 2026.' ) ), array( true, true ) );
$GLOBALS['log_rows'] = array( array( 'status' => 'failed', 'error' => 'Could not instantiate mail function.', 'sent_at' => gmdate( 'Y-m-d H:i:s', $GLOBALS['now'] + 60 ) ) );
ck( 'a refused welcome reads as refused, with what to do',
	WPCPM_Mentor_Application_Queue::invitation_line( get_post( $made ) ),
	'The invitation with the welcome went to the mail host on October 16, 2026. ' . WPCPM_Mail::invite_refused_message( 'administrator' ) . ' Resend invite is on the Mentors screen\'s Accounts tab.' );
$GLOBALS['log_rows']       = array( array( 'status' => 'sent', 'error' => '', 'sent_at' => gmdate( 'Y-m-d H:i:s', $GLOBALS['now'] - 30 * DAY_IN_SECONDS ) ) );
$GLOBALS['invited_at'][70] = $GLOBALS['now'] - 30 * DAY_IN_SECONDS;
ck( 'an invitation older than the activation is never read as the welcome: a sent row and a stamp from before it say nothing went since',
	WPCPM_Mentor_Application_Queue::invitation_line( get_post( $made ) ),
	'No invitation has gone since this applicant was made a mentor. Resend invite is on the Mentors screen\'s Accounts tab.' );
$GLOBALS['log_rows']       = array();
$GLOBALS['invited_at'][70] = $GLOBALS['now'] + 120;
ck( 'past the log\'s thirty days, a stamp from after the activation is the welcome', WPCPM_Mentor_Application_Queue::invitation_line( get_post( $made ) ), 'The invitation with the welcome was sent on October 16, 2026.' );
$GLOBALS['log_rows'] = array( array( 'status' => 'unconfirmed', 'error' => '', 'sent_at' => gmdate( 'Y-m-d H:i:s', $GLOBALS['now'] + 60 ) ) );
ck( 'one the mail log could not confirm says so', WPCPM_Mentor_Application_Queue::invitation_line( get_post( $made ) ), 'The invitation with the welcome was handed over on October 16, 2026, and the mail log does not say whether it went.' );
$GLOBALS['invite_queue'] = array( 70 );
ck( 'one still in the queue waits there', WPCPM_Mentor_Application_Queue::invitation_line( get_post( $made ) ), 'The invitation with the welcome waits in the email queue.' );
$GLOBALS['invite_queue'] = array();
an_account( 72, 'Ida Okafor', 'ida.okafor@example.test', array( 'administrator' ) );
update_post_meta( $made, WPCPM_Mentor_Application::META_USER, 72 );
ck( 'and an Administrator\'s account, which gets none, says why', WPCPM_Mentor_Application_Queue::invitation_line( get_post( $made ) ), 'No invitation: the account is an Administrator\'s, which signs in already.' );
update_post_meta( $made, WPCPM_Mentor_Application::META_USER, 999 );
ck( 'an account deleted since the activation reads as gone, on both lines, and nothing fails',
	array( WPCPM_Mentor_Application_Queue::invitation_line( get_post( $made ) ), false !== strpos( view_part( tab( 'active' ) ), 'Account: the account made for this mentor no longer exists.' ) ),
	array( 'The account is gone, so no invitation can reach it.', true ) );
update_post_meta( $made, WPCPM_Mentor_Application::META_AIRTABLE, array( 'state' => 'found', 'how' => 'create', 'record' => 'recBYHAND00000002', 'error' => '', 'left_out' => array(), 'at' => $GLOBALS['now'] ) );
ck( 'and a row the mentors sync linked reads as found and adds no retry: the one form left is the refused row\'s', array( false !== strpos( view_part( tab( 'active' ) ), 'The account holds its Airtable record, linked by the mentors sync or by hand; nothing was written from here.' ), count_forms( view_part( tab( 'active' ) ), WPCPM_Mentor_Application_Queue::ACTION_AIRTABLE ) ), array( true, 1 ) );
$retried = press( WPCPM_Mentor_Application_Queue::ACTION_AIRTABLE, $refused, array( 'view' => 'active' ) );
ck( 'Write the Airtable row again on an activated application writes it, by who pressed, and comes back to Activated',
	array( $retried['flash'], $retried['stop'], WPCPM_Mentor_Activation::$retries ),
	array( 'mapp-airtable-written', 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-mentors&tab=queue&mapp_view=active', array( array( $refused, 9 ) ) ) );
ck( 'and is refused on a row that is not active', press( WPCPM_Mentor_Application_Queue::ACTION_AIRTABLE, an_application( 'selected' ) )['flash'], 'mapp-state' );

echo "\n=== Send the acknowledgement ===\n";
reset_world();
$late = an_application( 'new', false, array(), array( WPCPM_Mentor_Application_Form::SIGNAL_MAIL_CEILING ) );
ck( 'a row in Waiting whose acknowledgement never went offers Send the acknowledgement', count_forms( view_part( tab() ), WPCPM_Mentor_Application_Queue::ACTION_ACKNOWLEDGE ), 1 );
$sent = press( WPCPM_Mentor_Application_Queue::ACTION_ACKNOWLEDGE, $late );
ck( 'the press sends it once, with the confirming link, writes who sent it, and leaves the row in its state',
	array( $sent['flash'], count( $sent['mail'] ), $sent['mail'][0]['context'], last_event( $late ), $sent['state'] ),
	array( 'mapp-acknowledged', 1, 'mentor-application-received', array( 'acknowledgement sent', 9, '' ), 'new' ) );
ck( 'after which the mark is gone, the press is not offered, and a second press sends nothing',
	array( false !== strpos( view_part( tab() ), 'The acknowledgement was not sent' ), count_forms( view_part( tab() ), WPCPM_Mentor_Application_Queue::ACTION_ACKNOWLEDGE ), press( WPCPM_Mentor_Application_Queue::ACTION_ACKNOWLEDGE, $late )['flash'], press( WPCPM_Mentor_Application_Queue::ACTION_ACKNOWLEDGE, $late )['mail'] ),
	array( false, 0, 'mapp-state', array() ) );
reset_world();
an_application( 'new' );
ck( 'a row acknowledged when it came is offered no such press', count_forms( view_part( tab() ), WPCPM_Mentor_Application_Queue::ACTION_ACKNOWLEDGE ), 0 );
$failing               = an_application( 'new', false, array(), array( WPCPM_Mentor_Application_Form::SIGNAL_MAIL_FAILED ) );
$GLOBALS['mail_fails'] = true;
ck( 'a hand-off that fails leaves the row as it was', array( press( WPCPM_Mentor_Application_Queue::ACTION_ACKNOWLEDGE, $failing )['flash'], last_event( $failing )[0] ), array( 'mapp-not-sent', 'submitted' ) );
```

Extend the suite's "every decision from each of the nine states" table with the three new handlers: The course is completed from `selected` only, Write the Airtable row again from `active` only, Send the acknowledgement from the four open states only, `mapp-state` everywhere else with nothing sent and nothing activated. M1's checks that list the five views and the seven decisions, or quote the tab's description or the sentence of a row whose acknowledgement never went, move to six views, ten decisions and the new words.

In `bin/test-mentors-screen.php`, with `WPCPM_Mentor_Activation::unwritten_count()` stood in: `'the Sync tab says how many mentors made from applications have no Airtable row yet, linking the Activated view'` (2: the plural sentence and `view_url( 'active' )`; 0: no notice).

- [ ] **Step 2: Run them to make sure they fail**

Run: `php bin/test-mentor-application-queue.php; php bin/test-mentors-screen.php`
Expected: `FAIL` on the new checks; `press()` with the new actions stops on an undefined index of `decisions()`.

- [ ] **Step 3: Implement the three handlers, the view, the lines and the Sync tab's notice**

Each handler starts with `begin()` and ends in `leave()`, as the seven do. `handle_complete()`: `begin( ACTION_COMPLETE, array( STATE_SELECTED ) )`, the optional note through `note( $post, 'complete', false )`, `activate( $id, get_current_user_id(), $note )`, `WPCPM_Mentor_Checker_Slack::flush()`, then the flash its outcome maps to. `handle_airtable()`: `begin( ACTION_AIRTABLE, array( STATE_ACTIVE ) )` and `retry_airtable( $id, get_current_user_id() )`. `handle_acknowledge()`: `begin( ACTION_ACKNOWLEDGE, open_states() )`, `mapp-state` when `acknowledged()`, `mapp-no-email` with no address, `mapp-not-sent` when `mail_applicant()` answers false, else `add_event( EVENT_ACKNOWLEDGED, get_current_user_id() )`; it does not count against the form's daily ceiling, as Put back in the queue's acknowledgement does not. The Activated view's age line reads `META_ACTIVATED_AT`; `invitation_line()` as the rules above, a log row's time read with `strtotime( $row['sent_at'] . ' UTC' )`.

- [ ] **Step 4: Run them to make sure they pass**

Run: `php bin/test-mentor-application-queue.php; php bin/test-mentors-screen.php; php bin/test-mentor-application-form.php; php bin/test-accounts-table.php`
Expected: `ALL PASS` from each.

- [ ] **Step 5: The checks every task ends with, then commit**

```bash
git add includes/modules/class-wpcpm-mentor-application-queue.php includes/modules/class-wpcpm-mentor-application.php includes/modules/class-wpcpm-mentors.php bin/test-mentor-application-queue.php bin/test-mentors-screen.php
git commit -m "Mentor applications: The course is completed on a selected application, an Activated view with the welcome's fate and Write the Airtable row again, and Send the acknowledgement for an application that never had it" -m "<the Co-Authored-By trailer>"
```

---

### Task 7: The feed counts applications

The design's feed answers "per calendar month since launch, how many applications were received, selected, activated, declined and lapsed" (section 3, decision 10). M1 counts events, so an application selected twice in a month counts twice there (M1 final review, Minor 1), which the Credits Monthly Report would read as two people. Each application now counts at most once per figure in a month; one selected again in a later month, after a lapse or an appeal, counts in each month it was selected, and the guide, the readme and the docblocks say so.

**Files:**
- Modify: `includes/modules/class-wpcpm-mentor-application.php` (`month_counts_of()` and its docblock), `includes/modules/class-wpcpm-mentor-application-feed.php` (its docblock)
- Test: `bin/test-mentor-application.php`, `bin/test-mentor-application-feed.php`

**Interfaces:**
- Consumes: Task 3's `EVENT_ALREADY_MENTOR`, which the feed does not count.
- Produces: `month_counts_of( WP_Post $post )` keeps its shape (month to `received`, `selected`, `activated`, `declined`, `lapsed`), each 0 or 1. Counts already kept from deleted rows (`OPT_KEPT`) are left as they are: the live site holds none from a month an application was selected twice.

- [ ] **Step 1: Write the failing test**

In `bin/test-mentor-application.php`, under "History survives retention", its clock set by the check itself:

```php
fresh();
$GLOBALS['site_offset'] = 2 * HOUR_IN_SECONDS;
$GLOBALS['now']         = gmmktime( 9, 0, 0, 1, 10, 2027 );
$twice                  = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
WPCPM_Mentor_Application::add_event( $twice, 'selected', 7, '', gmmktime( 10, 0, 0, 2, 2, 2027 ) );
WPCPM_Mentor_Application::add_event( $twice, 'withdrawn', 7, '', gmmktime( 10, 0, 0, 2, 9, 2027 ) );
WPCPM_Mentor_Application::add_event( $twice, 'selected', 7, '', gmmktime( 10, 0, 0, 2, 16, 2027 ) );
WPCPM_Mentor_Application::decide( $twice, 'lapsed', 'lapsed', 0, '', gmmktime( 10, 0, 0, 2, 23, 2027 ) );
WPCPM_Mentor_Application::add_event( $twice, 'selected', 7, '', gmmktime( 10, 0, 0, 4, 2, 2027 ) );
ck( 'an application selected twice in a month counts once there, and a withdrawal and a lapse in one month count one lapse; selected again in a later month, it counts there too',
	WPCPM_Mentor_Application::month_counts_of( get_post( $twice ) ),
	array(
		'2027-01' => array( 'received' => 1, 'selected' => 0, 'activated' => 0, 'declined' => 0, 'lapsed' => 0 ),
		'2027-02' => array( 'received' => 0, 'selected' => 1, 'activated' => 0, 'declined' => 0, 'lapsed' => 1 ),
		'2027-04' => array( 'received' => 0, 'selected' => 1, 'activated' => 0, 'declined' => 0, 'lapsed' => 0 ),
	) );
$mentor_already = WPCPM_Mentor_Application::store( answers(), 'new', array(), array() );
WPCPM_Mentor_Application::decide( $mentor_already, 'active', WPCPM_Mentor_Application::EVENT_ALREADY_MENTOR, 7, '', gmmktime( 10, 0, 0, 1, 20, 2027 ) );
ck( 'and an application filed as already a mentor is no activation', WPCPM_Mentor_Application::month_counts_of( get_post( $mentor_already ) )['2027-01']['activated'], 0 );
```

In `bin/test-mentor-application-feed.php`: `'a month\'s figure is the applications that reached the step that month: two applications, one selected twice, are two selections'`, against two planted rows.

- [ ] **Step 2: Run it to make sure it fails**

Run: `php bin/test-mentor-application.php`
Expected: `FAIL` on the first new check (`'selected' => 2` in February).

- [ ] **Step 3: Implement**

In `month_counts_of()`, the closure sets a figure to 1 rather than adding to it, apart from `received`, which a row counts once anyway. The two docblocks say "applications", not "people" or events, that an application selected again in a later month counts in both, and that one filed as already a mentor is not counted as activated.

- [ ] **Step 4: Run them to make sure they pass**

Run: `php bin/test-mentor-application.php; php bin/test-mentor-application-feed.php`
Expected: `ALL PASS` from both; M1's check "a row counts once where it arrived, and each selection, decline, withdrawal and lapse in its month" passes unchanged, since its two lapses fall in two months.

- [ ] **Step 5: The checks every task ends with, then commit**

```bash
git add includes/modules/class-wpcpm-mentor-application.php includes/modules/class-wpcpm-mentor-application-feed.php bin/test-mentor-application.php bin/test-mentor-application-feed.php
git commit -m "Mentor applications: the feed counts each application once per figure in a month, as the design words it" -m "<the Co-Authored-By trailer>"
```

---

### Task 8: The new emails, word for word, through the real senders

The design's emails recording suite for M2: the changed invitation as an activation sends it, and the acknowledgement Send the acknowledgement sends (section 10). A new suite with a recording of its own, made once, as M1's was; M1's recording of the five emails stays as it is and must still pass.

**Files:**
- Create: `bin/test-mentor-activation-text.php`, `bin/fixtures/mentor-activation-1-122-33.json` (recorded by the suite)
- Test: itself, and `bin/test-mentor-applications-text.php` unchanged

**Interfaces:**
- Consumes: Tasks 3 to 6; the real senders `WPCPM_Mentor_Application_Form::handle_submit()` and `handle_verify()`, `WPCPM_Mentor_Application_Queue::handle_select()`, `handle_complete()` and `handle_acknowledge()`, `WPCPM_Mentor_Checker_Runner::run_all()`, `WPCPM_Mentor_Activation::activate()`, and `WPCPM_Mail::drain_queue()` through 1.122.32's `send_invite_now()` with `welcome_email()`; the real mail layer, registry and lock.
- Produces: the recording's sha256, pinned in the suite.

- [ ] **Step 1: Write the suite**

Built as `bin/test-mentor-applications-text.php` is: the shared stand-in, the real `WPCPM_Lock` over `bin/stubs/options-lock-wpdb.php`, `wp_mail()` keeping what it is handed, every address written "at example.test" and every confirming link's signature as a fake before anything is recorded or compared, and core's `wp_new_user_notification()` stood in as core 7.1 builds its message (the keyed link alone, with the fixed fake key `TEST-KEY-NOT-A-REAL-LINK`, `headers` `''`) and passes it through the `wp_new_user_notification_email` filter to `wp_mail()`. The settings hold `mentor_activation_enabled` true and Contact address in emails `programs@example.test`; the Airtable client is Task 2's stand-in; the profile reader answers `completed` for the usernames a case names. Four cases, each applying through the real form first, as M1's suite does:

- `course-shown-to-the-daily-check`: apply, confirm, select; the runner's `run_all( true )` under `wp_doing_cron()`; drain the queue. Records the invitation with the welcome and its Reply-To to the new account.
- `course-completed-pressed`: apply, confirm, select, then The course is completed with a note; drain.
- `returning-mentor`: an account holding `recRETURNING00001`, `wpcpm_mentor_active` 0 and no Mentor role has the applicant's address; apply (held, already a mentor), confirm, select, The course is completed; drain. Records the invitation to that account's username.
- `acknowledged-by-hand`: apply with the form's acknowledgement ceiling spent, so none goes; Send the acknowledgement. Records the acknowledgement.

```php
ck( 'the recording is the one made from 1.122.33', is_file( $fixture ) ? hash_file( 'sha256', $fixture ) : null, '<the sha256 Step 3 prints>' );
ck( 'every case sends the emails it recorded, to the same people, in the same order, byte for byte', $as_sent, $recorded['cases'] );
ck( 'and the welcome reaches each invitation whole, its bracketed line included, with the program\'s Reply-To and no private link', $welcome_checks, array( 3, 3, 3, 0 ) );
```

where `$welcome_checks` counts, across the three invitations, the welcome's first line, its bracketed line, the `Reply-To: programs at example.test` header, and any link outside Task 4's six public ones and the set-password link.

- [ ] **Step 2: Run it to make sure it fails**

Run: `php bin/test-mentor-activation-text.php`
Expected: `FAIL` on "the recording is there", since nothing is recorded yet.

- [ ] **Step 3: Record once, read it, and pin it**

Run: `php bin/test-mentor-activation-text.php --record`
Expected: `Recorded 4 cases, <n> emails, in mentor-activation-1-122-33.json`. Read every recorded email against Task 4's welcome and against M1's recording for the emails M1 sent (the acknowledgement, the notice, the selection: byte for byte M1's but for the addresses and the reference); a difference stops the task. Pin the file's sha256 in the check, and `<n>` in the suite's header docblock.

- [ ] **Step 4: Run it, and M1's, to make sure they pass**

Run: `php bin/test-mentor-activation-text.php; php bin/test-mentor-applications-text.php; php bin/test-fixtures.php`
Expected: `ALL PASS` from each; the fixtures walk passes the new recording.

- [ ] **Step 5: The checks every task ends with, then commit**

```bash
git add bin/test-mentor-activation-text.php bin/fixtures/mentor-activation-1-122-33.json
git commit -m "Mentor applications: the invitation an activation sends and the acknowledgement sent by hand, recorded word for word through the real senders" -m "<the Co-Authored-By trailer>"
```

---

### Task 9: The guide, the version and the changelog

**Files:**
- Modify: `docs/sections/30-admin-wpadmin.md` ("Applications to mentor", and the Mentors row of "The audience screens"), `docs/sections/31-admin-settings.md` ("Accounts", the Invitation emails bullet; "Mentor applications", the new switch), `docs/sections/32-admin-tools.md` ("Mentor Status Checker", its settings table and "The Slack message after a promotion"), `docs/sections/34-admin-operations.md` ("The sync", "Invitations"); then `php bin/build-docs.php` (`docs/administrators.md`, `docs/build/administrators.html`); `wpcredits-program-manager.php`, `readme.txt`, `languages/wpcredits-program-manager.pot`
- Test: `bin/test-handbook.php`, `bin/test-handbook-sources.php`, `bin/test-admin-menu.php` (the retired name refused), `bin/check-spelling.php`

- [ ] **Step 1: The guide**

Section 30: drop "Nothing is written to Airtable in this release ... as before."; say how a selected applicant becomes a mentor once **Make mentors from applications** is on (the daily check finding the course, or **The course is completed**, which asks first), what that makes (the account as the mentors sync makes one; the Airtable row, or the row that already holds the person set back to Active, whatever its Status; the invitation with the welcome; an applicant who is a mentor already filed as such, nothing sent), the six views with **Activated**, its order, its invitation line (counted from the activation on, and what a refused or skipped welcome reads) and its **Write the Airtable row again**, the Selected view's last check, and **Send the acknowledgement** in the decisions' list. Section 31: the Invitation emails bullet gains the fold's new sentence; **Mentor applications** gains **Make mentors from applications**, off until the launch. Section 32: the checker reads the applications selected on this site first, newest first, and Airtable's mentors at its source status until **Stop checking mentors in Airtable** is ticked (a row in the settings table); it runs without the Airtable connection for the site's applications; the Slack line for a mentor made from an application. Section 34: the sync makes and links accounts through the same step an activation uses, and leaves an account an activation holds until its row is written or linked; the mentor's invitation carries the welcome and the program's Reply-To, whose course links and folder link are typed into the template, and is queued on every activation, except to an Administrator. Where section 30 describes the feed, it says each application counts once per figure in a month, and one selected again in a later month counts in both. Then `php bin/build-docs.php`, and a second run changes nothing.

- [ ] **Step 2: The version and the changelog**

1.122.33 in the header, `WPCPM_VERSION` and the Stable tag; then above `= 1.122.32 =`:

```text
= 1.122.33 =

* A selected applicant can become a mentor on this site: once Make mentors from applications is switched on, under Mentor applications on the Students and mentors tab of Settings (it is off until the launch), the daily Mentor Status Checker finding the mentors' course on their WordPress.org profile, or an Administrator pressing The course is completed on their application, makes or links their account as the mentors sync makes one, writes one row to the Airtable Mentors table (or sets the row that already holds them back to Active), and sends them one invitation that carries the welcome. An applicant who is a mentor already is filed as such, and nothing is sent.
* If Airtable refuses the row, the mentor is made and invited all the same, and Write the Airtable row again waits on the application under Activated, a new view of the Waiting for review tab, where those rows come first and each says what became of the welcome since; the Mentors screen's Sync tab says how many are waiting, and the mentors sync leaves those mentors alone meanwhile.
* The Mentor Status Checker reads the applications selected on this site, newest first, and, until Stop checking mentors in Airtable is ticked on its screen, the Airtable mentors at its source status as before. It checks the site's applications without the Airtable connection, and its Slack message names the mentors made from applications.
* Invitation: mentor carries the welcome after its own lines: the two Start here posts, the four student courses, the private Slack channel, the discount codes, the mentor resource folder and the sign-off. The courses' invitation links and the folder's link are typed into the template in Tools > Emails; until then one bracketed line says where they go. A reply to it reaches the address under Contact address in emails.
* Send the acknowledgement, on an application in Waiting whose acknowledgement never went, sends it once, with the link that confirms the address.
* The feed at /wp-json/wpcpm/v1/mentor-applications counts each application once per figure in a month; one selected again in a later month counts in both.
* For developers: the mentors sync makes and links accounts through WPCPM_Mentor_Provision, which WPCPM_Mentor_Activation shares; WPCPM_Mentor_Airtable_Row finds a person's row by address and shapes a new one by each column's type from the base's schema; an activation holds its application through WPCPM_Lock; wp wpcredits mentor_airtable_row <reference> prints the row an application would write and writes nothing.
```

Then `bash bin/make-pot.sh`.

- [ ] **Step 3: Run the checks**

Run: `php bin/test-handbook.php; php bin/test-handbook-sources.php; php bin/test-admin-menu.php; php bin/check-spelling.php | tail -1`, then the checks every task ends with.
Expected: `ALL PASS` from each and `US English throughout.`.

- [ ] **Step 4: Commit**

```bash
git add docs/sections docs/administrators.md docs/build/administrators.html wpcredits-program-manager.php readme.txt languages/wpcredits-program-manager.pot
git commit -m "Mentor applications: the guide, the version and the changelog of 1.122.33" -m "<the Co-Authored-By trailer>"
```

---

### Task 10: Try the activation on the Docker copy

No repository files. Notes and captures go in the session's scratchpad and are backed up under `.superpowers/session-backups/<the day, YYYY-MM-DD>/mentor-applications-m2/` (the scratchpad is swept after three days). The copy has no route out: WordPress.org and Airtable both refuse it, its tokens are blank, its mail is kept in `site/wp-content/local-safety-mail.log` (subjects and recipients only, so the words rest on Task 8's recording) and its cron is off. So, by the trial kit `.superpowers/sdd/2026-10-17-mentor-applications-m2/trial-kit.md` (owner item 1's default): the course is planted where the profile reader keeps its answers, and the Airtable answers are faked by a must-use plugin the kit describes, which logs every request the site makes, so real activations run end to end and the trial reads exactly what they would have written. The kit also holds how to sign in as the copy's local Administrator and how to submit a form whose confirm dialog the built-in browser cannot answer. With the owner's other choice, the Studio copy at port 8883 on a throwaway base runs the same steps with real writes.

- [ ] **Step 1: Snapshot, rollback zip, install the branch's build**

```bash
cd ~/Projects/wpcredits-docker
bin/snapshot before-rehearsal-1.122.33
bin/check | tail -1
bin/wp plugin get wpcredits-program-manager --field=version
cd ~/Projects/wpcredits-release
BRANCH="$(git -C ~/GitHub/wpcredits-program-manager rev-parse feature/mentor-applications-m2)"
python3 release.py check plugin --site local --commit "$BRANCH"
python3 release.py build plugin --commit "$BRANCH"
python3 release.py deploy plugin --site local --version 1.122.33
python3 release.py deploy plugin --site local --version 1.122.33 --yes
```

Before the check, put the zip of the version the copy runs in the copy's `release/` folder, from that version's `.superpowers/session-backups/*/release-plugin-<version>/` folder (the release README, "Rehearsing on the local copy"). Expected: `All checks passed.`; `PASSED: check plugin 1.122.33 ...`; `PASSED: build plugin 1.122.33 ...`; the deploy's plan ending `NOT RUN` (exit 3); `PASSED: deploy plugin 1.122.33 on http://localhost:8900 ...`. `--version` is needed: its default is main's version.

- [ ] **Step 2: The page, the settings and the faked Airtable**

```bash
cd ~/Projects/wpcredits-docker
bin/wp eval 'echo WPCPM_Mentor_Application_Form::ensure_page(), "\n";'
bin/wp eval 'WPCPM_Settings::patch( array( "mentor_applications_enabled" => 1, "mentor_notify" => "notify@example.test", "mentor_course_link" => "https://example.test/mentors-course/?invite=EXAMPLE-NOT-A-REAL-CODE", "mail_contact" => "programs@example.test", "checker_cron_enabled" => 1, "checker_cron_promotes" => 1, "checker_cache_ttl" => 3600 ) ); echo "set\n";'
```

Then the kit's placeholder connection and must-use plugin. Expected: a page ID, `set`, and `bin/wp eval 'var_dump( WPCPM_Settings::is_connected(), WPCPM_Mentor_Activation::enabled() );'` printing `true` and `false`: the switch is still off. A copy with no published privacy policy shows the form to nobody: publish a test page and choose it under Settings > Privacy, on the copy only.

- [ ] **Step 3: Three applications, and the switch off**

As a stranger, apply three times: `ada-example` (`ada@example.test`), `bo-example` (`bo@example.test`), and, after `bin/wp user create cy-example cy@example.test --role=subscriber` and `bin/wp user meta update <its ID> wpcpm_mentor_record_id recRETURNING00001`, `cy-example` with `cy@example.test`. Follow each acknowledgement's confirming link (`WPCPM_Mentor_Application::verify_url()` through `bin/wp eval`), then Select each on the tab. Plant Ada's course by the kit, run `bin/wp wpcredits check_mentors --promote`. Expected: its first line names both sources (the faked connection is there, so no "not connected" line, and Airtable's Vetted - positive rows are read through the fake, which answers none); Ada's row "Ready to be made a mentor; it waits until Make mentors from applications is switched on."; nobody made a mentor; the kit's Airtable log holding the Vetted - positive read and nothing written; the Selected view saying the applicant waits, with no press.

- [ ] **Step 4: The switch on, the daily check finds Ada's course**

`bin/wp eval 'WPCPM_Settings::patch( array( "mentor_activation_enabled" => 1 ) ); var_dump( WPCPM_Mentor_Activation::enabled() );'`, then `bin/wp wpcredits check_mentors --promote`, `bin/wp cron event run wpcpm_drain_invite_queue`, and read the mail log and the kit's Airtable log. Expected: Ada's row `activated`; the Airtable log holding the Email search, the schema and linked-table reads and one POST whose cells are Task 2's for Ada's answers; the mail log the invitation to `ada@example.test`. On the tab, Ada under **Activated**: "Made a mentor on ...", her account `ada-example` linked, "Airtable row written on ...", and "The invitation with the welcome was sent on ...". The Users screen lists `ada-example` with the Mentor role and the faked record id.

- [ ] **Step 5: The press, the returning mentor, a refusal and the retry**

Press **The course is completed** on Bo's row with a note, and on Cy's; drain the queue again. Expected: Bo a new account `bo-example` and a POST in the log; Cy's the existing `cy-example` account, gaining the Mentor role, and a PATCH of `recRETURNING00001`'s Status, no POST. Then set the kit's option `zz_mapp_trial_refuse`, apply, confirm and select a fourth time (`di-example`), and press The course is completed: Di under **Activated**, first in the list, "Airtable refused the row on ...", the Sync tab saying one mentor has no Airtable row yet. Delete the option and press **Write the Airtable row again**: the log shows the Email search before the POST, the line "Airtable row written", and the Sync tab's notice gone. Drain the queue, then press Resend invite for Di on the Accounts tab within the fifteen minutes: refused as too soon, and Di's invitation line still says the welcome was sent on that day.

- [ ] **Step 6: Send the acknowledgement, the feed**

Apply a fifth time (`eve-example`), then `bin/wp eval 'WPCPM_Mentor_Application::add_signal( <its ID>, "mail-ceiling" );'`: its row carries **acknowledgement not sent** and offers **Send the acknowledgement**; press it, and the mail log lists a second acknowledgement to `eve@example.test`, the mark gone, the row still under Waiting. Then `bin/wp wpcredits mentor_airtable_row --check --email=check@example.test`: its success line, and the kit's log holding the POST, the read of that record and its DELETE, with no new application, account or feed figure on the copy. Then `curl -s http://localhost:8900/wp-json/wpcpm/v1/mentor-applications`: this month's figures as pressed (`received` 5, `selected` 4, `activated` 4 on a copy with no earlier rows) and no `@` in the answer.

- [ ] **Step 7: Screenshots, measurements, then put the copy back**

At 1280 and 375 pixels wide: the Selected view with a row's last check and the press, the Activated view with a refused row first and its retry, the checker's screen with the new box and the warning, and the Sync tab's notice. Nothing wider than the screen at 375, and every gap measured as the owner's spacing rule asks: the computed margins and paddings between the new lines (the last check, the invitation and Airtable lines, the left-out sentence) and between them and the forms equal those between M1's lines and forms on the same row, at both widths. Then the rollback commands in the release folder's `rollback-local.txt`, in order, then:

```bash
cd ~/Projects/wpcredits-docker
bin/restore before-rehearsal-1.122.33
bin/wp plugin get wpcredits-program-manager --field=version
bin/check | tail -1
```

Expected: `Restored snapshots/before-rehearsal-1.122.33.tgz`, the version Step 1 read, `All checks passed.`. Back up the notes, the logs and the captures, and give the owner the results.

---

### Task 11: Release, only on the owner's go

The release script's order, as M1's Task 12 ran it: check, build, scan, the mirror's dry run and, on the owner's yes, the mirror; the deploy's dry run and, on the owner's yes, the deploy, which republishes the Administrator guide and purges the caches itself; then read-only looks at the live site that write nothing and print no applicant's answers. Every command runs from `~/Projects/wpcredits-release` unless it says otherwise.

- [ ] **Step 1: Read the live version, and what waits there**

```bash
ssh wpcredits-dashboard 'wp plugin get wpcredits-program-manager --field=version'
ssh wpcredits-dashboard 'wp eval "foreach ( WPCPM_Mentor_Application::states() as \$s ) { echo \$s, \" \", WPCPM_Mentor_Application::count_in( array( \$s ) ), PHP_EOL; } echo \"queued \", WPCPM_Mail::queued(), PHP_EOL;"'
```

Expected: `1.122.32`; the applications by state (the rows the mentor coordinator's trial left, if any: with the switch off by default, none of them is made a mentor at the deploy), and the invitation queue's length. Anything else for the version: say what it reads and wait for the owner.

- [ ] **Step 2: Main holds the branch, on the owner's yes**

```bash
cd ~/GitHub/wpcredits-program-manager
git checkout main
git merge --no-ff feature/mentor-applications-m2 -m "Merge 1.122.33: a selected applicant can become a mentor on the site, with one Airtable row and one invitation that carries the welcome, once a person switches it on" -m "<the Co-Authored-By trailer>"
```

Then the checks every task ends with, `php bin/test-mentor-activation-text.php` and `php bin/test-mentor-applications-text.php` on main. If main moved since the branch was made, merge main into the branch first and run them there.

- [ ] **Step 3: Check, build and scan**

```bash
python3 release.py check plugin
python3 release.py build plugin
python3 release.py scan plugin
```

Expected: the watch report of the public repository's changes since our last push (read it before the mirror), then `PASSED: check plugin 1.122.33 (...) against live 1.122.32.`, `PASSED: build plugin 1.122.33: ...` with the zip's md5 on the line before, and `PASSED: scan plugin 1.122.33: no finding. ...`. A change somebody made in our folder on the mirror stops the scan: port it first (the release README, "Changes made on the mirror").

- [ ] **Step 4: The mirror's message, its dry run, and the owner's go**

`~/Projects/wpcredits-release/messages/1.122.33.txt`, one plain paragraph with no attribution and no names, unless the owner words it otherwise:

```text
A selected applicant can become a mentor on the site, once a person switches Make mentors from applications on at the launch: when the Mentor Status Checker finds the mentors' course on their WordPress.org profile, or when an Administrator presses The course is completed, the account is made or linked as the mentors sync makes one, one row is written to the Airtable Mentors table (or the row that already holds the person is set back to Active), and one invitation goes, carrying the welcome whose private links are typed into the template on the site. When Airtable refuses the row, the mentor is made and invited all the same, and the application offers Write the Airtable row again under a new Activated view, which also says what became of the welcome. The checker reads the applications selected on the site, and Airtable's mentors until a person stops it; Send the acknowledgement sends an application the acknowledgement it never had; and the counts feed counts each application once per figure in a month.
```

`python3 release.py mirror plugin --message messages/1.122.33.txt`. Expected: what `--yes` would do, `the message passes its checks`, and `NOT RUN: ...` (exit 3). Give the owner the version, the md5, the checks' lines, Task 10's results and captures, check's watch report and the message, and wait for an explicit yes. Then `python3 release.py mirror plugin --yes --message messages/1.122.33.txt`; expected `PASSED: mirror plugin 1.122.33: origin/trunk is now <the new commit>`.

- [ ] **Step 5: The welcome's links saved, then the deploy, on the owner's yes**

By owner item 5's default, before the deploy the mentor coordinator saves the whole edited Invitation: mentor in Tools > Emails on the live site (the default welcome above, with each course's invitation link and the folder's link in place of the bracketed line), then reads it back as a test send to the owner's test mailbox. From that save on, every mentor invitation carries the welcome. Read-only, after the save:

```bash
ssh wpcredits-dashboard 'wp eval "print_r( WPCPM_Mail_Templates::state( \"invite-mentor\" ) );"'
```

Expected: the invitation `edited`. Then `python3 release.py deploy plugin` (its plan, `NOT RUN`, exit 3), the owner's yes, and `python3 release.py deploy plugin --yes`. Expected: the probes before and after, page 560 republished from `administrators.html`, `== caches: object cache flushed, edge cache purged`, and `PASSED: deploy plugin 1.122.33 on https://wordpresseducation.org. Rollback, if a person judges it needed: ...`. With the owner's other choice, the deploy waits for a mentors sync to have just finished, the invitation queue to be empty and nobody to press Resend invite, and the coordinator pastes the links within the three hours to the next sync.

- [ ] **Step 6: Read the live site back, writing nothing**

```bash
ssh wpcredits-dashboard 'wp eval "\$s = WPCPM_Settings::get(); var_dump( (bool) \$s[\"mentor_applications_enabled\"], (bool) \$s[\"mentor_activation_enabled\"], \"\" !== \$s[\"mentor_course_link\"], (bool) \$s[\"checker_cron_enabled\"], (bool) \$s[\"checker_cron_promotes\"], (bool) \$s[\"checker_skip_airtable\"] ); print_r( WPCPM_Mail_Templates::state( \"invite-mentor\" ) );"'
```

Expected: the form as it was, **Make mentors from applications** off, a course link set (never printed), the daily check and its promotion on as before, **Stop checking mentors in Airtable** unticked, and the invitation `changed` (the coordinator's edit kept and flagged, since the shipped text changed). No report-only check is run on the live site (it would write the last run, the applications' checks and the profile caches, and send Slack what waits), and `mentor_airtable_row` is never run on the live site for a real applicant's reference; its `--check` waits for the launch sitting (launch step 5).

- [ ] **Step 7: The vault and the memory note**

The design's status line says "M2 shipped as 1.122.33 on <date>"; the plugin note and the memory notes gain the release paragraph, the launch checklist's state, and the runbook lines (the counts by state and the queue before a deploy; the switch off until the launch sitting; the invitation's edit never reset).

---

## Launch: a person's steps, in order

Not code: the design's section 11 as the controller's ruling reorders it (see "Departures from the design"), after 1.122.33 is live. Each step that touches the live site or Airtable is a person's, on the owner's yes, and each save of settings reads its whole scope back; the site cannot see the automations and does nothing about them.

1. **Before anything: the preconditions, read-only.** 1.122.33 is live; the site has a published privacy policy page (`get_privacy_policy_url()` not empty), or the form shows the public nothing; Contact address in emails holds the program's address; Mentors' course invitation link is set (filled by the owner on 17 October 2026: check it is not empty, never print it); the page /become-a-mentor/ exists; the Mentor Status Checker's daily check and its promotion are on; **Send invitation emails** is on, which step 7's overlap relies on; Make mentors from applications is off. A person reads, in Airtable, what sets Mentors 3 - Welcome off (a record that comes to match Status Active, or only a change of the field); it is off before the site writes any row, so the answer decides only what an Airtable-made mentor gets meanwhile.
2. **The mentor coordinator reads the templates in Tools > Emails** (the four to the applicant, and Invitation: mentor as saved before the deploy, whose subject is theirs to change), checks the decline email's link to the repository (M1 Task 3: the address from before the repository was renamed, which still redirects), confirms the eleven questions and their order (design, section 13, item 1) and whether an applicant is told of a second opinion (item 3), and sends each template as a test to the owner's test mailbox. Invitation: mentor shows as changed since the deploy: it is never reset to the shipped text, which would send the bracketed line to the next mentor. The owner fills or confirms the mentors' course invitation link (design, section 11, step 2).
3. **The owner reviews the form page**, sets **Who is mailed** to the people who should hear of each application (empty for every Administrator), switches **Take mentor applications through the form on this site** on, on a freshly loaded Students and mentors tab or through `WPCPM_Settings::patch()` over SSH, and reads that whole scope back. The public links to the Airtable form then point at https://wordpresseducation.org/become-a-mentor/: the handbook's Call for Mentors page, the website, the GitHub repository's pages.
4. **The Mentors table's automations, read-only.** The owner reads every automation on the Mentors table in Airtable and notes any that would fire on a new row or a row set Active, so the check row of step 5 sets off nothing but Mentors 3, which is off by then.
5. **The launch sitting, the same day, once the form shows to the public, away from the daily check's hour and while no mentors sync runs.** First, read-only: `ssh wpcredits-dashboard 'wp eval "var_dump( WPCPM_Mentors_Sync::is_running() ); echo wp_date( \"H:i\", wp_next_scheduled( WPCPM_Mentors_Sync::CRON_DAILY ) ), PHP_EOL;"'`, false and the sync's next run, which leaves the sitting its time. Mentors 3 - Welcome is switched off in Airtable. Then the first real Airtable write (owner item 2's default, the controller's call): `ssh wpcredits-dashboard 'wp wpcredits mentor_airtable_row --check --email=<a plus-address of the test mailbox>'` searches the Mentors table for that address, writes the check row with Status Inactive, reads it back, deletes it, and prints what the live base took. A stop (no schema, a column the table lacks, a type the site does not write, a column not read back) holds the sitting until it is put right; a note (a country, language or contribution area the base does not hold) only informs. Whatever it printed, a person then searches the Mentors table in Airtable for the check address and deletes any row it holds, by hand, before the sync's next run. Then **Make mentors from applications** is switched on and the Students and mentors scope read back; the selected applicants whose course shows are made mentors by the next daily check or a press, and the Slack message names them. From here no applicant on the site waits behind an Airtable row.
6. **The Credits Monthly Report's release that reads the feed is live**, its methodology note saying the mentor figures come from the site's feed since the launch month and that each figure counts applications that reached the step in the month, one selected again later counting in both months. The WPCredits Tracker's move to the feed is a release of that plugin and does not hold the launch.
7. **The Airtable form view "WordPress Credits - Call for Mentors" is closed**, and Mentors 1, 2, 4 and 5 are switched off. **The applications already in Airtable are finished there by hand**, each as the mentor coordinator decides, their emails sent from the site's templates by hand if needed; nothing migrates. During this overlap the checker still promotes Airtable's Vetted - positive mentors daily: one whose account the sync creates gets the site's invitation with the welcome, and one whose account already exists gets nothing until somebody, told by the Slack message, presses Resend invite on the Mentors screen's Accounts tab.
8. **Once no Airtable row holds Vetted - positive** (the mentor coordinator moves each vetted mentor who will never finish the course to Dropped out, so a stale row cannot hold the overlap open), a person ticks **Stop checking mentors in Airtable** on the checker's screen and reads its scope back. The overlap ends.

---

## After the plan

- **Not proved on a scratch branch.** M1's plan carried every block as proved code; this one carries interfaces, tests and the values the design pins, so each task's implementer writes the bodies and each review reads them against the tests and the design. The ledger records where an implementer's reading departs from a step.
- **Reviewed twice on 17 October 2026** (ready after fixes, 1 Critical, 12 Important, 21 Minor; then a scoped re-check, N1 to N16), and revised in place each time. The second revision follows 1.122.32 as merged and live (60238424): the lock's `''` for a held lock and the real lock in the suites over 1.122.32's `$wpdb` stand-in; no queue lock of M2's own, since every invitation already goes through `send_invite_now()`; the Activated view's invitation line counted from the activation on, since a refused send keeps no stamp; the decoupled launch the controller ruled; the departures stated with the design's words; the launch's first real Airtable write made by the check row rather than a live test activation (the controller's ruling, after a third review); an account gone since the activation, and a row the sync linked itself, read truly on the Activated view.
- **The M1 final review's M2 recommendations, decided here:**
  - **The eighth decision, Send the acknowledgement**: built (Task 6). It finishes what the design's section 4 promises ("the acknowledgement goes out at once") for an application the day's ceiling or a failed hand-off stopped, without writing "marked as spam" into its history.
  - **Held rows and retention**: left as M1 Ruling 18 shipped it; owner item 7.
  - **Events or people in the feed**: the design's "how many applications", counted once per figure per month (Task 7); the readme, the guide and the report's methodology note say what one application selected again later does.
  - **A shared base for the three forms**: not in M2, which adds no form; worth a plan of its own before a fourth form.
- **The author's calls this plan makes where the design is silent**, beside the owner's defaults, the controller's ruling and the departures above:
  - The course step runs without the Airtable connection for the site's applications, as nothing built here is shaped for Airtable (design, section 1, item 5); the activation then records "Airtable is not connected." as its refusal.
  - Inside the activation the account comes first, then the application's state and its history, then the queued invitation, then the Airtable write, whose outcome is a history row of its own; a death between the account and the write leaves the row `pending` with the retry offered, and the search by address keeps the retry from making a second row. The account the activation found is the one provisioned, so two lookups never make two accounts.
  - The invitation is queued on every activation, whatever **Send invitation emails** says for the sync's bulk accounts: it carries the welcome, and the welcome is the one email a new mentor gets. An Administrator who applies is linked, given no Mentor role (as the sync does) and sent no invitation.
  - The row's cells are shaped by each column's type, read from the base's schema; what the base cannot take is left out by name and the rest written, with no schema the four cells go alone, and a linked table that cannot be read stops the write until a retry. Country and languages are linked by name through each linked table's primary field, read once a day. The name and the free text go as typed, not as the entities the site stores them with.
  - A returning mentor is the account found by the address, the username or the stored profile, holding a mentor record id; their own row's Status is set back to the sync's status, a row deleted in Airtable is replaced through the search by address, and they are sent the invitation with the welcome again.
  - The Activated view, sixth on the tab, holds the mentors made from applications, those waiting for their row first; it says what became of the welcome from the activation on (the queue, the mail log, the stamp), since that is the only place a lost welcome shows; the Mentors screen's Sync tab says how many have no row yet (the design's "in the sync's notices"), and the sync leaves them alone until it links them itself.
  - The checker's last result for a selected application is kept on the application, so the Selected view shows it whatever ran since.
  - The welcome's two paragraphs about the invitation email itself become the invitation's own lines, the product names are written in full, and the private channel is named by what it is, not by its name. The invitation keeps its subject, "Your mentor account is ready"; the mentor coordinator may change it (launch step 2).
  - The mentor's invitation gains the program's Reply-To, since its welcome asks for a reply; the other invitations stay as they are.
  - The account's own fields an application fills are the ones the sync fills: the display name, the website (the profile) and the mentor meta. The sponsorship facts the Sponsor Dashboard reads are the sync's, rebuilt on its next run from the new row; the activation writes none.
  - The lapse clock takes the application's lock, so it never lapses a row being made a mentor; retention keeps an active application whose row is not written, whatever the approved days say, so the retry is never lost.
  - One command prints the row an application would write and, with `--check`, writes one synthetic row through the same client and column map, reads it back and deletes it: the launch's only real Airtable write before a real mentor's, since the controller ruled out a live test activation (its Slack message, its count in the feed for good, and the account its handle could link).
- **Not in M2:** a mentor made inactive from the site, an edit of a mentor's row from the site, the students' assignments, the student welcome emails, a mentor's own profile page (design, section 14); the sponsor and institution forms' old lockout sentence, which M1 left for a release of its own.
