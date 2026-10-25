# Plugin 1.123.32: small fixes left from 1.123.30 (Claude access), implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ten small fixes the 1.123.30 reviews set aside, and two checks: `text()` safe without mbstring and without doubled spaces, an answer checked against its schema before it is logged as answered, the PHP log lines of Claude access held to one an hour and free of file paths, a failed answer traced, "No stage" as data, the institutions' order the dashboards', three passages of words, the Security tab's account label; a timed log sweep on the Docker copy and a read-only look at live's application-password filters.

**Architecture:** Changes inside `WPCPM_Abilities`, `WPCPM_Ability_Answers`, `WPCPM_MCP` and the Security tab's renderer. One new public method, `WPCPM_Abilities::trace()`, takes over `WPCPM_MCP::trace()`. One optional argument on `WPCPM_Abilities::answer()`. No new screen, setting or stored value; the trace's hourly places are `WPCPM_Ceiling` rows like any other.

**Tech Stack:** WordPress 7.1 plugin PHP (floor PHP 7.4, WordPress 6.5; the abilities need 6.9), the plugin's `bin/test-*.php` suites.

**Spec:** the triage of 25 October 2026 (the 1.123.30 ledger's deferred minors, `.superpowers/session-backups/2026-10-25/release-plugin-1.123.30/sdd-ledger.md`), approved by the site's owner on 25 October 2026 ("Yes: fix all 10 and run both checks (the live one is read-only), as 1.123.32"). The design is `docs/specs/2026-10-24-abilities-mcp-design.md`; its rulings in that folder's `rulings.md` stand.

**Repository:** ~/GitHub/wpcredits-program-manager, worktree `.worktrees/plugin-1-123-32`, branch `release/1.123.32-mcp-small-fixes`, from `main` at `dead9811` (1.123.30, live). 1.123.31 is Institutions release 2's; whichever lands second merges `main` in first.

## Global Constraints

- Never use em or en dashes anywhere. US English. "Administrators". Full product names.
- No person's name in code, comments, docs, tests or commit messages: roles only. Record ids invented, uppercase letters and digits after `rec`.
- Match the surrounding code: docblocks that say why, escaping, translators comments.
- phpcs: no new errors or warnings (0 errors, 60 warnings in 26 files at `dead9811`).
- Tests first in the repository's style (`bin/test-*.php`), red before green.
- Every sentence below in quotes is copied verbatim.
- One commit per task, a subject line plus one plain paragraph, ending with the repository's Co-Authored-By trailer, via `git commit -F`.
- Before reporting: every `bin/test-*.php`, `php bin/check-references.php`, `php bin/check-spelling.php`, `php bin/test-fixtures.php`, `sh bin/check-standards.sh` (phpcs run with `-d memory_limit=1G`) and `php bin/check-dead-annotations.php`.
- Nothing on live changes. The live check is read-only.

## Review Focus

1. A real answer on real data must not be refused as `invalid_output`: core's validator, not the suite's stand-in, has the last word (the Docker step runs the four builders through `answer()` with their schemas on WordPress 7.1.3).
2. A trace can never take a request down: the hourly claim and the write sit inside a `try`, so a broken options table still leaves the server uncreated and the site answering.
3. A bad answer still counts against the ceilings and still gets the one fixed sentence: nothing of the validator's message goes out.
4. `text()` on WordPress before 6.9 without mbstring: bytes that are not UTF-8 make an empty text, never a fatal and never the raw bytes.
5. The trace line names an anonymous class without the file it was declared in.

---

### Task 1: `text()`, mbstring and spaces

**Files:**
- Modify: `includes/class-wpcpm-abilities.php` (`text()`)
- Modify: `includes/class-wpcpm-ability-answers.php` (`institutions()`: the `mb_stripos()` call)
- Test: `bin/test-abilities.php`, `bin/test-ability-answers.php`

- [ ] Step 1: tests, red first:
  - the abilities suite stands in for core's `wp_scrub_utf8()` as core writes it (the substitute character U+FFFD), and `text( "Caf\xE9 X" )` is `"Caf\u{FFFD} X"`;
  - `text( "A \u{200B} B" )` and `text( "A \x07 B" )` are `'A B'`; `text( str_repeat( 'a', 119 ) . ' bcd' )` is 119 a's, no space at the end;
  - a source pin over both class files: every `mb_` call is `mb_substr()` or `mb_strlen()` (WordPress stands in for those) or sits behind `function_exists( 'mb_...' )` on its line.
- [ ] Step 2: run red.
- [ ] Step 3: `text()` scrubs with `wp_scrub_utf8()` when it exists, else `mb_scrub( $value, 'UTF-8' )` when that exists, else not at all (the `/u` patterns then turn text that is not UTF-8 into ''); after the control and invisible characters go, runs of spaces become one; the cut is trimmed at its end. `institutions()` matches with `mb_stripos()` behind `function_exists()`, `stripos()` otherwise.
- [ ] Step 4: green; commit.

### Task 2: the answer checked before it is logged, and the trace

**Files:**
- Modify: `includes/class-wpcpm-abilities.php` (`definitions()`, `answer()`, new `trace()`)
- Modify: `includes/class-wpcpm-mcp.php` (`create_server()`; `trace()` removed)
- Test: `bin/test-abilities.php`, `bin/test-mcp.php`

**Interfaces:**
- Produces: `WPCPM_Abilities::answer( $ability, $input, $build, $schema = null ): array|WP_Error`; `WPCPM_Abilities::trace( string $kind, string $what, string $ability = '' ): void`, `$kind` `server` or `answer`.

- [ ] Step 1: tests, red first:
  - the abilities suite stands in for `rest_validate_value_from_schema()` with its `misfits()` (required keys only) and keeps each schema it was given;
  - with a schema the answer fits: answered, one row `answered`; with one it does not fit: `wpcpm_mcp_failed`, "The site could not answer this time.", the build ran once, the hour's count went up by one, one row `failed` with reason `invalid_output`; with no schema: answered as before;
  - each ability's `execute_callback` validates against exactly its registered `output_schema`;
  - with the error log pointed at a file: a build that throws a `RuntimeException` leaves "WPCredits MCP: wpcpm/program-counts could not answer (RuntimeException)." and nothing of its message; a second failure in the same hour leaves no second line; a build that answers something else leaves "(not_an_answer)", a bad answer "(invalid_output)"; an anonymous class extending `RuntimeException` leaves "(RuntimeException@anonymous)" and no part of the suite's path; the row's reason stays `error` for the first two;
  - the MCP suite: each existing one-line pin still gets its line (the suite gives the hour's place back before each); two failing creates in one hour leave one line; an anonymous exception class is named without its path; a settings read that throws inside `enabled()` escapes nothing.
- [ ] Step 2: run red.
- [ ] Step 3: implement. `definitions()` passes the output schema it registers to `answer()`. `answer()` checks a well-shaped answer with `rest_validate_value_from_schema( $answer, $schema, 'output' )` when the schema is an array and the function exists. `trace()` claims `WPCPM_Ceiling::key( 'mcp-trace', $kind )`, 1 an hour, and writes "WPCredits MCP: the server could not be created (%s)." or "WPCredits MCP: %1$s could not answer (%2$s)."; the word is cut after `@anonymous` and cleaned to `A-Z a-z 0-9 _ . @ \ -`, the ability to `a-z 0-9 / -`; the claim and the write inside one `try`. `create_server()` moves its preamble inside its `try` and calls `WPCPM_Abilities::trace( 'server', ... )`.
- [ ] Step 4: green; commit.

### Task 3: "No stage" as data, the institutions' order

**Files:**
- Modify: `includes/class-wpcpm-ability-answers.php` (`stage()`, `institutions()`)
- Test: `bin/test-ability-answers.php`

- [ ] Step 1: tests, red first: the suite's `__()` reads a table of translations; with "No stage" translated, the `by_stage` key and a match's `stage` still read "No stage"; matches named "Zeta Example College", "Łódź Example Academy" and "Émile Example School" come back Émile, Łódź, Zeta, the order of `WPCPM_Dashboards::compare_names()` (the suite loads that class and stands in for `remove_accents()`).
- [ ] Step 2: run red.
- [ ] Step 3: `const NO_STAGE = 'No stage';` in `WPCPM_Ability_Answers`, returned by `stage()`; the sort compares with `WPCPM_Dashboards::compare_names()`, then the record id.
- [ ] Step 4: green; commit.

### Task 4: the words and the Security tab's label

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-audit.php` (the docblocks of `META_MCP` and `KIND_MCP_CALL`)
- Modify: `docs/specs/2026-10-24-abilities-mcp-design.md` (decision 9's line, "is deleted after 90 days")
- Modify: `docs/sections/31-admin-settings.md` (What Claude can ask; Latest calls)
- Modify: `includes/class-wpcpm-settings-screen.php` (Who may connect)
- Test: `bin/test-settings.php`

- [ ] Step 1: test, red first: an account with no display name is "admin-three", and one whose display name is its login ("admin-four") is "admin-four"; the others keep "name (login)".
- [ ] Step 2: run red; implement; green.
- [ ] Step 3: the words: the two docblocks say "answered, refused or failed"; the spec says the log "is deleted after the days of the Keep the log for setting, 90 by default"; the guide says each answer says "when its sources were last read (for What waits for an Administrator, when each sync last ran; for Mentor engagement, the moment it counts as of)", and Latest calls adds "A failed answer, and a server the MCP Adapter could not create, also leave one line in PHP's error log, at most one an hour of each, with the error's code or the exception's class and nothing else."
- [ ] Step 4: the docs build and its checks; commit.

### Task 5 (coordinator): version, checks, trial

- [ ] Version 1.123.32 in the plugin header, `WPCPM_VERSION` and the readme's Stable tag; the changelog; the `.pot` (`bin/make-pot.sh`).
- [ ] The battery and the checks.
- [ ] A whole-branch review by a fresh reviewer; fixes until clean.
- [ ] Docker (only while no other session is trialling there): a snapshot; install the build; the four builders through `answer()` with their schemas on real data, each an answer and not `invalid_output`; 3,000 log rows dated 100 days back and one sweep, timed; restore the snapshot and reinstall the version it had.
- [ ] Live, read-only: `has_filter( 'application_password_is_api_request' )` and the callbacks on `rest_pre_dispatch` by priority.
- [ ] `release.py` check, build and scan. Mirror and deploy only on the owner's yes.

## Closing note (25 October 2026)

- The institutions' order (Task 3) landed with Task 1: Task 1's mbstring pin needed the sort's unguarded `mb_strtolower()` gone.
- Added from the live check: on live, Jetpack opens its VideoPress AJAX actions in wp-admin to application passwords (`application_password_is_api_request`), with the VideoPress module on, so a Claude password could sign in there, outside the fence. `fence_xmlrpc()` became `fence_sign_in()`, which fails a Claude password's sign-in in every request that is not a REST request; it is its own commit, so it can be left out.
- Two whole-branch reviews, each "with fixes", all minor: the answer is checked against the registered ability's schema (`checked_schema()`, read back with `wp_get_ability()`), two child runs without mbstring, the options-gone trace check made able to fail, and the wording of the docblocks, the guide and the changelog.
- The sweep, timed on the Docker copy: 3,000 rows dated 100 days back deleted in 7.1 seconds (six batches), the 600 recent rows and every other audit row kept; a full run of 10,000 rows is about 24 seconds.
- The Docker trial, with MCP Adapter 0.7.1 and the switch on: the four abilities through core's own `execute()`, ten calls, all answered on the copy's real data; two forced failures left one line without a path. The copy was put back on 1.123.29 from its snapshot and the adapter deleted.
- After the live deploy: the MCP address must still answer a Claude password (a call through the assistant's own connection), and on the owner's yes a VideoPress AJAX request signed with it must be refused.
