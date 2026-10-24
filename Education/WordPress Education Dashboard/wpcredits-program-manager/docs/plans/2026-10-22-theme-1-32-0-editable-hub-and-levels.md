# Theme 1.32.0: editable Education Hub and Partners levels, implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every word of the Education Hub template and the Partners levels is edited in the Site Editor, the plugin's sponsor application reads the levels from the Partners template, and the two templates live already saved are updated in place without losing an edit.

**Architecture:** Dynamic blocks (server-rendered from their attributes, inner blocks saved, `save` returning `InnerBlocks.Content` or null) replace the PHP lists: a Program index container of Program cards, a Courses band of Course cards, a Field of study list of rows, a Partner levels container of levels. Patterns insert them pre-filled from today's lists. A one-time migration rewrites the saved `wp_template` posts that still hold the old pieces.

**Tech Stack:** WordPress 7.1 block API (block.json, render.php), hand-written editor scripts against `wp.blocks`, `wp.blockEditor`, `wp.element`, `wp.components`, `wp.i18n` (no build step, hand-maintained `index.asset.php`), PHP 8.4, the theme's PHP test suites.

**Spec:** /Users/maciejpilarski/GitHub/wpcredits-program-manager/docs/specs/2026-10-22-theme-editable-hub-and-levels-design.md

**Repository:** /Users/maciejpilarski/GitHub/wpcredits-theme, worktree `.worktrees/theme-1-32-0`, branch `release/1.32.0-editable-hub`, from the theme 1.31.12 branch `release/1.31.12-no-value-labels` at `a9a829e` (main f564423 plus the phone tables fix; 1.31.12 ships first).

## Scope of 1.32.0 (the site's owner, 22 October 2026: "Editable Education Hub ASAP")

1.32.0 ships Tasks 1, 2 and 3 and Task 5 for the Education Hub template only (`page-education-hub`), as a hotfix. Task 4 (the Partners levels as one source) and Task 5's Partners part follow in 1.32.1. The 1.32.0 migration leaves `page-partners` untouched.

## Global Constraints

- No build step: editor scripts are plain ES5-style JavaScript against the `wp.*` globals (see `blocks/program-index/index.js`), each with a hand-maintained `index.asset.php` listing its dependencies and `WPCREDITS_VERSION`.
- New blocks are dynamic: `render.php` draws them from attributes; `save` returns `wp.blockEditor.InnerBlocks.Content` for containers and `null` for leaves, so a markup change in a later release never invalidates saved content.
- The rendered markup of each section, for today's texts, stays byte for byte what 1.31.11 draws, except where a decision in the spec changes it (D5's table rows); prove it per task by rendering the section before and after into a scratch file and comparing.
- Every visible word is editable in the editor where it is shown (RichText inline); lists, audiences, status, slugs and addresses in the block's sidebar (InspectorControls) where inline editing is not practical.
- Default content comes from today's PHP lists (`wpcredits_hub_programs()`, the fields list in `patterns/hub-programs.php`, `wpcredits_partner_levels()`), translated in the theme's text domain when a pattern inserts it.
- No em or en dashes; US English; roles, never people's names, in code, comments, tests and commit messages ("an Administrator", "the site's owner").
- Tests in the repository's style (`bin/test-*.php`), red before green; `php bin/check-selectors.php /Users/maciejpilarski/GitHub/wpcredits-program-manager` and every `bin/test-*.php` green; phpcs with the theme's `phpcs.xml.dist` (`~/.composer/vendor/bin/phpcs`), no new errors or warnings.
- Do not touch the version, the readme's changelog or the `.pot`: the coordinator does those.
- No subagents, no network, no ssh, no docker, no stash, no push. Commits: subject plus one plain paragraph, ending the repository's Co-Authored-By trailer, via `git commit -F`.

## Review Focus

1. A saved template with an Administrator's edits elsewhere: the migration changes only its targets (Task 5 tests on the two live copies as fixtures).
2. The block editor accepting the new blocks without an "unexpected content" warning, and after a later markup change (dynamic blocks; check the `save` functions).
3. Without JavaScript the hub still lists every live program with no dead controls, and the anchors `program-<slug>`, `<slug>-courses`, `#levels`, `#glance` stay.
4. The plugin's `wpcpm_partner_levels` filter with no saved Partners template, with one, and with one whose levels block was removed: each returns a well-formed list (the defaults in the last case).
5. A level, card or row with an empty field: nothing broken printed (no empty `<li>`, no "→" link with no address).

---

### Task 1: Program cards in an editable Program index

**Files:**
- Create: `blocks/program-card/block.json`, `index.js`, `index.asset.php`, `render.php`
- Modify: `blocks/program-index/block.json` (`allowedBlocks` ["wpcredits/program-card"], inner blocks), `index.js` (InnerBlocks with the card as the only allowed block, a template of one empty card for a new index), `render.php` (cards from `$block->inner_blocks`; the chips, counts and announcements computed from them; the courses band no longer drawn here, see Task 2)
- Modify: `inc/hub-content.php` (register the new block where the theme registers its blocks; `wpcredits_hub_program_cards_markup( array $programs ): string`), `patterns/hub-programs.php` (the index with default cards)
- Test: `bin/test-hub.php` (and `bin/test-hub-js.php` if `view.js` changes)

**Interfaces:**
- Produces: block `wpcredits/program-card` with attributes `slug` (string), `name` (string, rich text), `status` (string: `live`|`development`, default `live`), `audiences` (array of strings from `student`, `faculty`, `career`), `what` (string, rich text), `time` (string, rich text), `gets` (array of strings), `links` (array of objects `{ "label": string, "url": string }`, the url either an address or one of `institution_application`, `sponsor_application`, `partners`).
- Produces: `wpcredits_hub_program_cards_markup( array $programs ): string`, the serialized `<!-- wp:wpcredits/program-card {...} /-->` blocks for programs shaped as `wpcredits_hub_programs()` returns them (courses ignored), used by the pattern and by Task 5.

- [ ] Step 1: tests first: the index rendered from three cards draws three `article.wpc-hub-program` with ids `program-<slug>`, `data-audiences`, the Live or In development tag, name, what, Time, You get (no `dt` when `gets` is empty), links (a key resolved by `wpcredits_hub_url()`, a link with an empty url not drawn); the chips' counts and sentences from the cards; a development card hidden unless `wpcredits_hub_shows_planned()`; today's six programs through the pattern render byte for byte as 1.31.11's cards (minus the courses band, which Task 2 draws). Run: red.
- [ ] Step 2: implement the card block (render from attributes, escaped), the editor (RichText for name, what, time; sidebar: status select, audience checkboxes, slug text, gets textarea one per line, links as label and address pairs with add and remove), the index as a container, `wpcredits_hub_program_cards_markup()`, the pattern.
- [ ] Step 3: green; the byte comparison script and its result in the commit message; commit.

### Task 2: The courses band as editable blocks

**Files:**
- Create: `blocks/course-band/` and `blocks/course-card/` (block.json, index.js, index.asset.php, render.php each)
- Modify: `inc/hub-content.php` (register; `wpcredits_hub_course_band_markup( array $program ): string`), `patterns/hub-programs.php` (the band after the index, for the program with courses)
- Test: `bin/test-hub.php`

**Interfaces:**
- Consumes: Task 1's index no longer draws the band.
- Produces: `wpcredits/course-band` attributes `anchor` (string, the section id, default `<program slug>-courses`), `eyebrow`, `heading`, `lede` (rich text); inner blocks `wpcredits/course-card` only, attributes `name`, `url`, `eyebrow`, `line`.
- Produces: `wpcredits_hub_course_band_markup( array $program ): string` (empty string for a program without courses), used by the pattern and Task 5.

- [ ] Step 1: tests: the band from four course cards draws `section.wpc-hub-courses#<anchor>` with `aria-labelledby`, the head, the `ul.wpc-hub-courses__grid` and each card as today; a card with no url draws its name unlinked; today's band through the pattern byte for byte as 1.31.11's. Red.
- [ ] Step 2: implement (inline RichText for every visible text, url in the sidebar).
- [ ] Step 3: green; byte comparison; commit.

### Task 3: "Find where you fit" as editable rows

**Files:**
- Create: `blocks/fit-list/` and `blocks/fit-row/`
- Modify: `inc/hub-content.php` (register; `wpcredits_hub_fit_rows(): array` holding today's rows moved out of the pattern; `wpcredits_hub_fit_list_markup( array $rows ): string`), `patterns/hub-programs.php` (the list replaces the Custom HTML block; the section's eyebrow, heading and lede stay core blocks)
- Test: `bin/test-hub.php`

**Interfaces:**
- Produces: `wpcredits/fit-list` (container, inner blocks `wpcredits/fit-row` only; attributes for the three header labels, default today's "Your field of study", "The team you join", "What you'll have to show") and `wpcredits/fit-row` attributes `field` (rich text), `teams` (array of strings), `proof` (rich text).
- Produces: `wpcredits_hub_fit_rows(): array` (each `field`, `teams`, `proof`) and `wpcredits_hub_fit_list_markup( array $rows ): string`, used by Task 5.

- [ ] Step 1: tests: the list keeps `role="list"` on both lists, one item per row, the visually hidden step names, the aria-hidden header line, arrows and check; a row with no teams draws no empty list; today's rows byte for byte as 1.31.11's Custom HTML. Red.
- [ ] Step 2: implement (field and proof inline; teams in the sidebar one per line, or inline if practical).
- [ ] Step 3: green; byte comparison; commit.

### Task 4: Partner levels as one editable source

**Files:**
- Create: `blocks/partner-levels/` and `blocks/partner-level/`
- Modify: `inc/partners.php` (`wpcredits_partner_levels()` reads the Partners template in use; `wpcredits_partner_levels_defaults()` holds today's list; `wpcredits_partner_levels_markup( array $levels ): string`; the filter unchanged in shape), `patterns/partners-levels.php` (the block pre-filled; the section's heads stay core blocks)
- Test: `bin/test-partners.php`

**Interfaces:**
- Produces: `wpcredits/partner-levels` (container, inner blocks `wpcredits/partner-level` only) drawing the cards grid and the "Levels at a glance" table (`#glance`) with rows derived from the levels' items, "Yes" from the listing level upward; `wpcredits/partner-level` attributes `name`, `amount`, `short`, `tag`, `lead` (rich text), `items` (array of strings).
- Produces: `wpcredits_partner_levels(): array` (each `name`, `amount`, `short`, `lead`, `items`, `tag` when set), read from `get_block_template( get_stylesheet() . '//page-partners' )`'s content, falling back to `wpcredits_partner_levels_defaults()`; static per request. `wpcredits_partner_levels_markup( array $levels ): string` used by the pattern and Task 5. The `wpcpm_partner_levels` filter keeps its 1.31.11 shape.

- [ ] Step 1: tests: the block from five levels draws the cards as 1.31.11 and the table from the items (row text, "Yes" pattern, the head's short amounts); the filter with no saved template (the theme file's block), with a saved template whose levels differ (those levels), with a saved template without the block (the defaults); a level with an empty tag draws none. Red.
- [ ] Step 2: implement.
- [ ] Step 3: green; the cards byte for byte as 1.31.11's; the table's change of row wording listed in the commit message; commit.

### Task 5: Migrating the saved templates

**Files:**
- Create: `inc/migrate-1-32-0.php` (required from `functions.php`), `bin/fixtures/saved-education-hub.html` and `bin/fixtures/saved-partners.html` (the two templates as live has them, supplied by the coordinator in the task's dispatch; no personal data)
- Test: `bin/test-migrate-1-32-0.php`

**Interfaces:**
- Consumes: `wpcredits_hub_program_cards_markup()`, `wpcredits_hub_course_band_markup()`, `wpcredits_hub_fit_list_markup()`, `wpcredits_hub_fit_rows()`, `wpcredits_partner_levels_markup()`, `wpcredits_partner_levels_defaults()`.
- Produces: `wpcredits_migrate_1_32_0_content( string $content, string $slug ): array` (`content`, `changed` list of what was replaced, `skipped` list with reasons), pure and testable; `wpcredits_fit_rows_from_html( string $html ): array` and `wpcredits_partner_levels_from_html( string $html ): array` (empty array when unreadable); the runner on `init` once (option `wpcredits_migrated_1_32_0`), for this theme's saved `wp_template` posts, saving a revision before writing, recording per template what changed or why it was left, and a one-time notice for Administrators on the Site Editor screen listing it.

- [ ] Step 1: tests on the two fixtures: the Education Hub copy's self-closing Program index becomes the container with today's six cards followed by the courses band; its "Find where you fit" Custom HTML becomes the fit list with the rows read from that HTML (equal to today's list for the live copy); every other byte of the template unchanged (compare the content outside the replaced spans); the Partners copy's expanded levels section becomes the levels block with the texts read from it; a template without the targets is unchanged and reported skipped; a second run changes nothing; unreadable markup falls back to the theme's lists and says so. Red.
- [ ] Step 2: implement the pure functions, then the runner (revision first, then `wp_update_post`, the option last).
- [ ] Step 3: green; commit.

### Task 6 (coordinator): version, docs, trial

- [ ] Version 1.32.0 in style.css and the readme's Stable tag, the changelog entry (what is edited where, the migration, the table rows' wording), the `.pot`.
- [ ] Docker: copy live's two saved templates into the Docker copy (read on live, written locally), snapshot, deploy 1.32.0 locally, confirm the migration's result and notice, edit a card, a course, a row and a level in the Site Editor, check the hub and the Partners page with and without JavaScript, at 375px and 1440px, and the sponsor application's "What each level includes" after a level edit.
- [ ] Deploy and mirror on the owner's yes; read back the live migration result.
