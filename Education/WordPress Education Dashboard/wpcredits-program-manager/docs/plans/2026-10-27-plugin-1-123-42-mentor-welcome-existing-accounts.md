# Plugin 1.123.42: the mentor welcome for an account that already exists

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A mentor whose account already exists on the site (a returning mentor, or someone who was a student or an institution member) gets the site's mentor invitation, which carries the mentor welcome, when the mentors sync makes that account an active mentor, as a mentor whose account the sync creates already does; once per activation, never on every run.

**Architecture:** `WPCPM_Mentor_Provision::link_existing_user()` reports, in the run's report, whether the account it linked was an active mentor before the link and how it was matched. `WPCPM_Mentors_Sync::provision_mentor()` reads that and queues `WPCPM_Mail::queue_invite()` for an account that was not an active mentor, under the same rules as a created account (the `send_welcome_email` setting, never an Administrator) plus the activation's rule for a match by username alone (held, with a notice), and a brake on a run that would invite many at once. The shared provisioning step sends nothing itself, so the activation of a mentor application, which queues its own invitation, is unchanged.

**Tech Stack:** PHP 7.4+, WordPress 7.1; the plugin's `bin/test-*.php` suites.

**Spec:** the product owner's request of 27 October 2026 ("Do the fix first"), after the finding in the Slack thread of the program's Administrators: the site's mentor invitation carries the welcome, but the sync sends it only when it creates an account, so a mentor linked to an existing account gets no welcome from the site, and the Airtable mentor welcome automation cannot be turned off until it does.

## Global Constraints

- Version: plugin 1.123.42 (live is 1.123.41); no theme release.
- No em or en dash anywhere; US English; "Administrators"; full product names; no person's name in code, tests, docs or commit messages.
- One invitation per activation: a mentor who stays active is never invited again by a later run.
- Nothing changes for a mentor whose account the sync creates, nor for the activation of a mentor application (`WPCPM_Mentor_Activation`), which calls the same provisioning step and queues its own invitation.
- The suites stay green: 163 suites.

## Review Focus

1. A mentor active on the site, read again by the next run: no invitation (the every-three-hours case).
2. An account that is an active mentor because a site application activated it, then read by the sync: no second invitation.
3. A former mentor (the account has the mentor role and its active flag at 0) made Active again: one invitation; an account that is only a student or an institution member made a mentor: one invitation, and the mentor role added as today.
4. An account matched by username alone: no invitation, held as the activation holds it, with the notice that says how to send it.
5. A run that would invite more than `RELINK_INVITE_MAX` linked accounts: none queued, one notice naming how many.

---

### Task 1: The welcome for a linked account

**Files:**
- Modify: `includes/modules/class-wpcpm-mentor-provision.php` (`link_existing_user()`)
- Modify: `includes/modules/class-wpcpm-mentors-sync.php` (`provision_mentor()`, `empty_stats()`, the run's summary that prints `created` and `linked`)
- Modify: `includes/mail/templates/invitations.php` (the `invite-mentor` trigger line)
- Modify: `includes/class-wpcpm-settings-screen.php` (the `send_welcome_email` label and its help)
- Modify: `docs/sections/` lines that describe when a mentor is invited (find them with `grep -rn "invitation" docs/sections/3*.md`), then rebuild with `php bin/build-docs.php`
- Test: `bin/test-mentor-provision.php`, and the mentors sync's suite (find it with `grep -l "provision_mentor\|WPCPM_Mentors_Sync" bin/test-*.php`)

**Interfaces:**
- Produces: in the provisioning report, `$report['linked_accounts'][] = array( 'user_id' => int, 'was_active_mentor' => bool, 'how' => string )` for each account `link_existing_user()` links, written before it sets the active flag and the role; `was_active_mentor` is true when the account held the mentor role and `WPCPM_Mentors_Sync::META_ACTIVE` was 1.
- Produces: `WPCPM_Mentors_Sync::RELINK_INVITE_MAX = 5`; the stat `relinked_invited` (int) beside `invited`.

- [ ] **Step 1: Write the failing tests.**
  - A mentor row whose account is already an active mentor: no queue entry, `relinked_invited` 0.
  - A former mentor (mentor role, active flag 0), matched by record id: one queue entry; a second provisioning of the same row in the same run or the next: none (**Review Focus 1, 3**).
  - An account with the student role only, matched by email: the mentor role added, one queue entry (**Review Focus 3**).
  - An account matched by username alone: no queue entry, `WPCPM_Mentor_Provision::USER_INVITE_HELD` set to `login`, and the notice "No invitation was sent, since a match by username alone may be somebody else: once that is confirmed, press Send invite or Resend invite on its row in the Mentors screen's Accounts tab." (the activation's words; read them in `WPCPM_Mentor_Activation` and reuse the same string) (**Review Focus 4**).
  - An Administrator's account: no queue entry, as for a created one.
  - `send_welcome_email` off: no queue entry for a linked account, as for a created one.
  - An account a site application activated (the active flag already 1, the mentor role): no queue entry (**Review Focus 2**).
  - Six linked accounts that would each be invited in one run: none queued, one notice "6 mentors whose accounts already existed became active in one run, so none was sent the welcome. Send it from their rows in the Mentors screen's Accounts tab if they should have it." (singular and plural through `_n()`), `relinked_invited` 0 (**Review Focus 5**); five: all five queued.
  - `WPCPM_Mentor_Activation`'s suite still passes unchanged.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement.** `link_existing_user()` reads the role and the flag before it writes them and adds the row to `linked_accounts`; it queues nothing. `provision_mentor()` collects the candidates of the run (a linked account that was not an active mentor, the setting on, not an Administrator, not matched by `login`), and the run queues them once its provisioning pass ends, or none when they are more than `RELINK_INVITE_MAX`, with the notice. A match by `login` is held and noted as the activation does. The `invite-mentor` trigger reads: "When a mentor is sent the invitation to their account: when an application to mentor is activated, from the Mentors screen, or as the sync creates the account, or makes an existing account an active mentor, with invitation emails on." The setting's label: "Email each new mentor and student a password-reset link as their account is created, and each mentor whose account already existed when the sync makes them an active mentor"; its help keeps its first sentence and adds: "A mentor whose account already existed gets the same email, once each time they become active."
- [ ] **Step 4: Run every suite; rebuild the guides; `php bin/check-spelling.php`.**
- [ ] **Step 5: Commit.** "Mentors sync: a mentor whose account already existed is sent the welcome when they become active"

### Task 2: Version, trial, release (the coordinator)

- [ ] 1.123.42: header, `WPCPM_VERSION`, readme Stable tag, changelog, `.pot`.
- [ ] Docker trial, with no email leaving the copy: an existing test account (a student account at the test address the program uses for trials) linked by the provisioning step as a mentor row with Status Active: one queue entry; the same row again: none; a former mentor made Active again: one; the queue's contents read back, then emptied, nothing sent.
- [ ] `release.py` check, build, scan; deploy and mirror only on the product owner's yes; then the GitHub reply on WordPress/WPCredits#253, drafted for the product owner, saying the Airtable mentor welcome automation can be turned off.
