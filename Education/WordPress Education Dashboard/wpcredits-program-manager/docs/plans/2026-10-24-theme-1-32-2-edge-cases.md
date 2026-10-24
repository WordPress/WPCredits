# Theme 1.32.2: the four edge cases left from 1.32.1, implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the four edge cases the 1.32.1 reviews parked: two first requests running a migration at once, a kept revision pruned after it was recorded, the editor and the page picking different levels blocks, and a hidden 1.31 cards block coming back visible.

**Architecture:** Guards inside the existing migration files and the glance block's editor script; no new block, option shape or screen.

**Tech Stack:** WordPress 7.1 block theme PHP and hand-written ES5 editor scripts; the theme's `bin/test-*.php` suites and node harnesses.

**Spec:** the ledger of 1.32.1 (`wpcredits-program-manager/.superpowers/session-backups/2026-10-23/theme-1-32-1-sdd/progress.md`, its parked final-review items); the site's owner said go on 24 October 2026 ("Do those").

**Repository:** /Users/maciejpilarski/GitHub/wpcredits-theme, worktree `.worktrees/theme-1-32-2`, branch `release/1.32.2-edge-cases`, from `main` at `f12b985` (1.32.1, live).

## Global Constraints

- Do not touch the version, the readme's changelog or the `.pot`: the coordinator does those.
- Never use em or en dashes anywhere. US English. Roles, never people's names.
- No build step: editor scripts are ES5 against `window.wp.*`, with hand-maintained `index.asset.php` (update dependencies if a new `wp.*` package is used).
- The migrations already ran on live (options `wpcredits_migrated_1_32_0` and `wpcredits_migrated_1_32_1` exist there): nothing here may make either run again where its option exists, or change a byte of what either writes for live's templates.
- Tests first in the repository's style, red before green; every `bin/test-*.php` and `php bin/check-selectors.php /Users/maciejpilarski/GitHub/wpcredits-program-manager` green; phpcs with `phpcs.xml.dist`, no new errors or warnings.
- No subagents, no network, no ssh, no docker, no stash, no push. One commit per fix, a subject line plus one plain paragraph, ending with the repository's Co-Authored-By trailer, via `git commit -F`.

## Review Focus

1. A lock that a crashed request leaves behind must not stop a migration for ever.
2. A record that says a copy is kept must be true after the write, whatever the site's revisions limit.
3. The editor's table and the page's table name the same levels block in every template the reader can meet.

---

### Task 1: The four guards

**Files:**
- Modify: `inc/migrate-1-32-0.php` and `inc/migrate-1-32-1.php` (the runners, and the copy-and-write helpers they share)
- Modify: `blocks/partner-glance/index.js` (`levelsOf()` and the container lookup)
- Test: `bin/test-migrate-1-32-0.php`, `bin/test-migrate-1-32-1.php`, `bin/test-partners-js.php` and its harness `bin/js/partners-editor-harness.js`

- [ ] Step 1: tests, each red first:
  - **The lock.** Each runner takes a lock before it reads the templates (`add_option()` of a lock option beside its flag, not autoloaded, holding the time); a second request that finds a lock younger than ten minutes does nothing and leaves no record; a lock older than ten minutes is taken over; the lock is deleted once the runner's option is stored, and on the runner's failure path too.
  - **The pruned copy.** After the write, the runner reads the revision it recorded back; when it no longer holds the content as it was (a small `WP_POST_REVISIONS` pruned it), the entry says `kept: record` with `before`, and the notice says the copy is in the record. A site with an unlimited or large limit records `revision` as today.
  - **One levels block.** The glance editor prefers a visible levels block that has no `core/block` or `core/template-part` among its parents (`getBlockParents()`, `getBlockName()`), and only when there is none takes one inside a reference, so it names the block the server's reader names (the template's own tree first). A template with two visible levels blocks, one inside a synced pattern earlier in the markup, gets the same table in the editor as on the page.
  - **A hidden 1.31 cards block.** When the 1.31 Custom HTML block holding the cards, or the table, carries `metadata` (for example `blockVisibility: false`), the block that replaces it carries the same `metadata`, so a hidden part stays hidden; one without `metadata` is replaced exactly as today (live's templates migrate byte for byte as before).
- [ ] Step 2: run red.
- [ ] Step 3: implement.
- [ ] Step 4: green; every suite, `php bin/check-selectors.php /Users/maciejpilarski/GitHub/wpcredits-program-manager`, phpcs; quote the last lines.
- [ ] Step 5: commits, one per guard.

### Task 2 (coordinator): version, trial, deploy

- [ ] Version 1.32.2 in style.css and the readme's Stable tag; the changelog; the `.pot`.
- [ ] Docker: install; the hub and the Partners page render as 1.32.1's; the migrations do not run again (both options exist); put Docker back to 1.32.1.
- [ ] Deploy and mirror only on the owner's yes.
