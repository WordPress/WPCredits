# Plugin 1.123.43: two contributions from the public repository, ported

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring two pull requests a contributor opened on the public repository into the plugin, under the contributor's authorship: the Email Log links a recipient's name to their row on their audience's Accounts tab, where Resend invite is (WordPress/WPCredits#280, for #263), and the Students screen gains a filter by status and a summary of how many students hold each status (#281, for #260). Then the changes the review asked for, with "Status" as this value's one name on the Students screen.

**Architecture:** The contributor's two commits are on the branch as they were written (`f04df786`, `3b1998b3`). One follow-up commit makes the review's changes on top of them; main receives the branch by a merge, never a squash, so the two commits keep their author.

**Tech Stack:** PHP 7.4+, WordPress 7.1; the plugin's `bin/test-*.php` suites.

**Spec:** the product owner's "start the 1.123.43 port" (27 October 2026); the review comments the product owner posted on #280 and #281 that day, whose points are Task 1's steps; and the product owner's decision of that day: the value the Students screen filters is called "Status" there, column, picker and summary alike, while the Institution Dashboard's roster and its export keep "Program", which schools read.

## Global Constraints

- Version: plugin 1.123.43 (live is 1.123.42).
- No em or en dash anywhere; US English; "Administrators"; full product names; no person's name in code, tests, docs, commit messages or the changelog (the changelog says "contributed on the public repository", never a handle).
- The contributor's two commits keep their authorship: no rebase that rewrites them, no squash onto main.
- Every suite stays green: 164 suites.
- The Institution Dashboard's roster and its export keep "Program" for this value.

## Review Focus

1. A recipient whose recorded type no longer holds (a student who is now a mentor and no longer a student): the Log links to the screen of the role the account holds now.
2. A recipient with no account stays plain text; an account with no program role links to the user editor, as the contribution does.
3. The statuses, in the picker and the summary, come in the program's own order: the Settings' current statuses, then its past statuses, then any other value, A to Z by the words shown; a student with no status is counted under none.
4. One value shows the same words in the Status column, the picker and the summary (`WPCPM_Program::status_words()`), and the Institution Dashboard still says "Program".
5. The summary's chip in force says so to screen readers (`aria-current="true"`) and is dressed by `assets/css/admin.css`, with no inline style.

---

### Task 1: The review's changes

**Files:**
- Modify: `includes/mail/class-wpcpm-mail-log-table.php` (`account_link()` and its docblock)
- Modify: `includes/modules/class-wpcpm-students-table.php` (`columns()`, `column_program()`, `status_counts()`, `list_intro()`, the picker in `extra_tablenav()`)
- Modify: `assets/css/admin.css` (the `.wpcpm-waiting` chips)
- Modify: `docs/sections/` lines about the Students screen's Program column or its filters (find them with `grep -rn "Program column\|Filter by institution\|by institution" docs/sections/`), then rebuild with `php bin/build-docs.php`
- Test: `bin/test-emails-screen.php`, `bin/test-students-screen.php`

**Interfaces:**
- Consumes: `WPCPM_Mail_Capture::type_of( WP_User|null $user, string $audience ): string` (the recorded type while the account still holds that role, else the first role it holds, Administrators including anyone with `WPCPM_Roles::CAP_MANAGE`, else `WPCPM_Mail_Catalog::TYPE_OTHER`); `WPCPM_Program::status_words( string $status ): string`; the settings `student_statuses` and `past_statuses` (`WPCPM_Settings::get_value()`).
- Produces: nothing other tasks use.

- [ ] **Step 1: Write the failing tests.**
  - Email Log: a row recorded as `student` whose account now holds the mentor role and not the student role links to `page=wpcpm-mentors&tab=accounts`; an account with no program role but `WPCPM_Roles::CAP_MANAGE` links to `page=wpcpm-administrators&tab=accounts`; every such link ends `#wpcpm-accounts-list` (**Review Focus 1**). The contribution's own checks stay, the plain-text and user-editor ones among them (**Review Focus 2**).
  - Students: the column header reads "Status"; a row shows `status_words()` of its value, as the picker's option for it does; the picker's options and the summary's chips come in the order of Review Focus 3, with a value in neither Settings list placed after both; the chip in force carries `class="current"` and `aria-current="true"` and no `style` attribute; `assets/css/admin.css` holds a `.wpcpm-waiting a.current` rule (**Review Focus 3, 4, 5**).
  - The Institution Dashboard's roster and export still name the column "Program" (an existing check, or one line added to the suite that covers `class-wpcpm-institution-roster-view.php`) (**Review Focus 4**).
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement.**
  - `account_link()` asks `WPCPM_Mail_Capture::type_of( $user, $row['recipient_type'] )` and maps the type to its screen (`student` to `wpcpm-students`, `mentor` to `wpcpm-mentors`, `institution` to `wpcpm-institutions`, `sponsor` to `wpcpm-sponsors`, `admin` to `wpcpm-administrators`); any other type falls back to the user editor. Its own role list goes. The anchor is `WPCPM_Accounts_Table::LIST_ANCHOR` where that class is loaded on the Emails screen; if loading it there would cost a require the screen does not otherwise make, keep the literal and add a test that it equals the constant. The search stays the username; the docblock says the Accounts tab matches it anywhere in the username, the email or the name, so the person is on the first page but not always alone. The display-name fallback goes: every account has a username.
  - The Students table: the column's header becomes "Status" (keep the column key `program`, which sorting and the screen options store); `column_program()` prints `status_words()` of the value. Statuses are ordered as Review Focus 3 says, once, in a helper the picker and the summary share. The chip in force gets `class="current"` and `aria-current="true"`, and its look moves to `.wpcpm-waiting a.current` in `assets/css/admin.css` (weight 600 and the hover's border color, as the inline style had them).
  - The guide's lines that name the Students screen's Program column, its picker or its filters say "Status" and describe the filter by status and the summary.
- [ ] **Step 4: Run every suite, rebuild the guides, `php bin/check-spelling.php`.**
- [ ] **Step 5: Commit.** "Email Log and Students screen: the review's changes to the two contributions, and Status as the Students screen's one word"

### Task 2: Version, trial, release (the coordinator)

- [ ] 1.123.43: header, `WPCPM_VERSION`, readme Stable tag, changelog (the two contributions, "contributed on the public repository", and the review's changes), `.pot`.
- [ ] Docker trial: the Log's link for a student's, a mentor's and an Administrator's row, and for a row with no account; the Students screen's filter by status alone and with an institution, the summary's counts against the picker's, the chip in force, and the Status column's words.
- [ ] `release.py` check, build, scan; merge onto main without a squash; deploy and mirror only on the product owner's yes; then close #280 and #281 with a thank-you comment drafted for the product owner.
