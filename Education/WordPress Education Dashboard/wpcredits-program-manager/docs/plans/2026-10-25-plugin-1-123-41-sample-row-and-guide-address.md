# Plugin 1.123.41: the sample row is refused, and the Administrator guide moves to /administrator-guide/

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Two of the product owner's answers of 25 October 2026 to the questions left after Institutions release 2: the student import refuses a row whose address is at example.test, the sample list's row, so it cannot become a student; and the Administrator guide, titled "Administrator guide" since 1.123.38, moves from /program-manager-guide/ to /administrator-guide/, the old address answering with a permanent redirect.

**Architecture:** The import's `clean_row()` gains one problem code for an address at example.test, named to the school as the sample row to replace. The guide's path constant changes; a one-time migration renames the page an existing site already has, on the pattern of `WPCPM_Institution_Application::maybe_move_page()`; a 404 at the old address is sent on with a 301, on the pattern of `WPCPM_Institution_Application::redirect_old_address()`.

**Tech Stack:** PHP 7.4+, WordPress 7.1; the plugin's `bin/test-*.php` suites.

**Spec:** the product owner's answers of 25 October 2026 (Owner's queue): "(a) Refuse it: any row whose address is at example.test is refused with a line saying it is the sample row to replace; the other rows import as usual." and "(d) Move to /administrator-guide/: the page and the plugin's links move, and the old address redirects to the new one for good." Not in this release: (b) and (c), which the product owner kept as they are.

## Global Constraints

- Version: plugin 1.123.41 (live is 1.123.40); no theme release.
- No em or en dash anywhere; US English; "Administrators" for the program team; full product names; no person's name in code, tests, docs or commit messages.
- The guide page keeps its ID (560 on the live site), its title and its content; only its slug moves. The release script finds it by ID.
- A link anybody was ever sent to /program-manager-guide/, with or without a fragment or a query, lands on the guide.
- The suites stay green: 162 suites.

## Review Focus

1. The sample list downloaded from the form and uploaded unchanged: one refused row, named as the sample row, nothing created, the file's other rows unaffected.
2. An address at a subdomain of example.test, in capitals, or with spaces around it: refused the same way; an address at example.com or institution.example: not refused by this rule.
3. A site where another page or post already holds `administrator-guide`: the guide is not renamed (no `administrator-guide-2`), and the old address keeps working because the page is still there.
4. /program-manager-guide/#some-section and /program-manager-guide/?x=1: the redirect keeps the query (the fragment stays in the browser).
5. The migration runs once: a later hand edit of the slug is left alone.

---

### Task 1: The import refuses the sample row

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-import.php` (`clean_row()` near line 994)
- Modify: wherever the import's problem codes become sentences for the school (find `email_invalid`'s sentence)
- Modify: `includes/modules/class-wpcpm-institution-export.php` (the sample's docblock near line 53 says the sample always imports)
- Modify: `docs/sections/` lines that describe the sample list or the import's refusals, then rebuild the guides with `php bin/build-docs.php`
- Test: `bin/test-institution-import.php`, and every suite whose fixtures import example.test addresses (`grep -l "example.test" bin/test-*.php`): those test students move to a reserved domain that is not refused (`institution.example`, which the import's own comments use)

**Interfaces:**
- Produces: the problem code `email_sample`.

- [ ] **Step 1: Write the failing tests.** `clean_row()` with `ana@example.test`, `Ana@EXAMPLE.TEST`, ` ana@example.test `, `ana@school.example.test`: verdict `invalid`, problems `array( 'email_sample' )`; with `ana@example.com` and `ana@institution.example`: verdict `ok`. The sample's body (`WPCPM_Institution_Export`'s sample bytes) read by the import's reader: the headers parse, its one row is refused as `email_sample`. The school's sentence for `email_sample` says it is the sample list's example row and to replace it with one of their students.
- [ ] **Step 2: Run them, see them fail.**
- [ ] **Step 3: Implement.** After `is_email()` passes, an address whose domain is `example.test` or ends in `.example.test`, compared in lower case, adds `email_sample` and leaves `email` empty, as a failed address does.
- [ ] **Step 4: Run every suite; rebuild the guides.**
- [ ] **Step 5: Commit.** "Import: the sample list's example row is refused, named as the row to replace"

### Task 2: The Administrator guide moves to /administrator-guide/

**Files:**
- Modify: `includes/class-wpcpm-settings-screen.php` (`GUIDE_PAGE_PATH` near line 51, its docblocks near lines 702 and 729)
- Create or modify: the migration and the redirect, in the class that owns the guide's path (`WPCPM_Settings_Screen`) or a small class of their own if that reads better; hooked as `WPCPM_Institution_Application` hooks its own (find where `maybe_move_page` and `redirect_old_address` are hooked and at which priority)
- Modify: `includes/modules/class-wpcpm-administrators-dashboard.php` (the comment near line 299), `docs/sections/31-admin-settings.md` (line 12), then rebuild the guides
- Test: a suite for the move and the redirect, on the pattern of the institution application's (find it with `grep -l "redirect_old_address\|maybe_move_page" bin/test-*.php`)

**Interfaces:**
- Produces: `WPCPM_Settings_Screen::GUIDE_PAGE_PATH = 'administrator-guide'`; `GUIDE_OLD_PATH = 'program-manager-guide'`; an option flag for the one-time move.

- [ ] **Step 1: Write the failing tests.** The migration: a published page at `program-manager-guide` and none at `administrator-guide` gets the slug `administrator-guide`, keeping its ID, title, content and status; with another page, post or attachment at `administrator-guide`, nothing is renamed; the flag is written before the work, so a second run does nothing, and a slug an Administrator changed afterwards is left alone. The redirect: a 404 whose matched path is exactly `program-manager-guide` (any case) and whose request names it as a page or a post gives a 301 to the guide's permalink with the query string kept; a page that really sits at /program-manager-guide/ (the move skipped) is served, not redirected; /about/program-manager-guide/ is not redirected; with no guide published there is no redirect. `settings_guide()` finds the page at the new path.
- [ ] **Step 2: Run them, see them fail.**
- [ ] **Step 3: Implement**, the migration on the institution application's model (flag first; the slug only while it is the old one and the new one is free; slashed update), the redirect on its model (at 9 on `template_redirect`, a 404 only, the path and the query variables both asked, the query kept, 301).
- [ ] **Step 4: Run every suite; rebuild the guides.**
- [ ] **Step 5: Commit.** "Guides: the Administrator guide moves to /administrator-guide/, the old address redirecting"

### Task 3: Version, trial, release (the coordinator)

- [ ] 1.123.41: header, `WPCPM_VERSION`, readme Stable tag, changelog, `.pot`.
- [ ] Docker trial: the sample list downloaded and uploaded unchanged on the Institution Dashboard (one refused row, its sentence, nothing staged); page 560 renamed on the copy, the old address 301 to the new with a query, the settings screen's intros linking the new address; the release script's guide step still finds page 560 by ID.
- [ ] `release.py` check, build, scan; deploy and mirror only on the product owner's yes; after the deploy, read the live page's slug and the redirect back.
