# Plugin 1.123.28: small fixes left from 1.123.27, implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Five small fixes the 1.123.27 reviews set aside: one word of Airtable left in a school-facing message, a doubled "to" on the Administrator Dashboard, the country field's error state for screen readers, a date in the stored format on an Administrators' card, and an unslashed page write.

**Architecture:** Copy and markup fixes in the classes that print them; one optional argument on a shared helper. No new screen, option or stored value.

**Tech Stack:** WordPress 7.1 plugin PHP, the plugin's `bin/test-*.php` suites.

**Spec:** the ledger of 1.123.27 (`.superpowers/session-backups/2026-10-24/plugin-1-123-27-sdd/progress.md`, its deferred minors); the site's owner said go on 24 October 2026 ("Do those").

**Repository:** ~/GitHub/wpcredits-program-manager, worktree `.worktrees/plugin-1-123-28`, branch `release/1.123.28-small-fixes`, from `main` at `c3c35a33` (1.123.27, live).

## Global Constraints

- Do not touch the version, `readme.txt`'s changelog or the `.pot`: the coordinator does those.
- Never use em or en dashes anywhere. US English. "Administrators". Full product names.
- No person's name in code, comments, docs, tests or commit messages: roles only. Test addresses use `example.test`.
- Match the surrounding code: docblocks that explain why, escaping, translators comments.
- phpcs: no new errors or warnings (60 warnings, no errors today).
- Tests first in the repository's style (`bin/test-*.php`), red before green.
- Every user-facing sentence below is copied verbatim.
- No subagents, no network, no ssh, no docker, no stash, no push. One commit per fix, a subject line plus one plain paragraph, ending with the repository's Co-Authored-By trailer, via `git commit -F`.
- Before reporting, the battery (every `bin/test-*.php`), `php bin/check-references.php`, `php bin/check-spelling.php`, `php bin/test-fixtures.php`, `bash bin/check-standards.sh` and `php bin/check-dead-annotations.php`, with `PATH="<scratchpad>/phpcs-1g:$PATH"`.

## Review Focus

1. The health line's translated string changes its words, not its placeholders' order or number.
2. The country field's error state reaches the field a person types into, and only after a refused post; the mentor form's country gets it too.
3. The page write keeps its exact bytes for an English site and a translation holding a backslash.

---

### Task 1: The five fixes

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-students.php` (the `status-graduation-off` message, around line 733; the docblocks at lines 24, 135 and 156 that say program managers record graduations in Airtable)
- Modify: `includes/modules/class-wpcpm-administrators-cards.php` (the health line, around line 2896)
- Modify: `includes/class-wpcpm-dashboards.php` (`render_combo()`), `includes/modules/class-wpcpm-institution-application.php` and `includes/modules/class-wpcpm-mentor-application-form.php` (their country fields pass it), `assets/css/application.css` (the comment at about line 163 stays true)
- Modify: `includes/modules/class-wpcpm-institution-panel.php` (the review block's "Uploaded by %1$s on %2$s, %3$s.", around line 583)
- Modify: `includes/modules/class-wpcpm-institution-application.php` (`ensure_page()`'s `wp_insert_post()`)
- Test: `bin/test-institution-graduate.php` (or the suite that pins the flash), `bin/test-administrators-dashboard.php`, `bin/test-institution-application.php`, `bin/test-mentor-application-form.php`, `bin/test-dashboards.php` if it exists, `bin/test-institution-panel.php`

- [ ] Step 1: tests, each red first:
  - the graduation-off flash reads, verbatim: "Nothing was changed: marking a student as graduated is switched off on this screen for now. Administrators record graduations in the program records."; the three docblocks say the program records too;
  - the health line reads, verbatim: "Last email: %1$s, sent to %2$s on %3$s. Status: %4$s." (its translators comment says what each placeholder is, as now), and the pin at about bin/test-administrators-dashboard.php:1926 reads ", sent to";
  - `WPCPM_Dashboards::render_combo()` takes an optional `invalid` (bool, default false); with it, the visible text field and the hidden select both carry `aria-invalid="true"`; the institution form passes it for a country refused on a post, and the mentor form for its own; with no refusal neither carries it;
  - the review block's date prints through `WPCPM_Dates::day()` in the site's date format, never `Y-m-d`;
  - `ensure_page()` passes its title and content through `wp_slash()`, and a page created with a translated title holding a backslash keeps it (the suite's `wp_insert_post` stand-in unslashes, as core does).
- [ ] Step 2: run red.
- [ ] Step 3: implement.
- [ ] Step 4: green; the battery and the checks; quote their last lines.
- [ ] Step 5: commits, one per fix.

### Task 2 (coordinator): version, trial, deploy

- [ ] Version 1.123.28 in the plugin header, `WPCPM_VERSION` and the readme's Stable tag; the changelog; the `.pot`.
- [ ] Docker: a snapshot; install; a refused post of the institution form with no country shows `aria-invalid` on the field; the Administrator Dashboard's health line; put the snapshot back and reinstall 1.123.27.
- [ ] Deploy and mirror only on the owner's yes.
