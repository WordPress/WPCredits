# Theme 1.32.1: the Partners levels edited in the Site Editor, implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The Partners page's five levels and its "Levels at a glance" table are edited in the Site Editor, written once, and the plugin's sponsor application reads them from there; live's saved Partners template is updated in place; the 1.32.0 migration's loose ends are tied.

**Architecture:** As 1.32.0: dynamic blocks drawn from their attributes, containers saving `InnerBlocks.Content` and leaves null. A Partner levels container of Partner level blocks draws the cards; a Levels at a glance block draws the table from the levels of the Partners template in use. A second one-time migration (option `wpcredits_migrated_1_32_1`) rewrites saved Partners templates and marks saved Program indexes, through a write that now keeps an exact copy of the template as it was.

**Tech Stack:** WordPress 7.1 block API (block.json, render.php), hand-written editor scripts against the `wp.*` globals, PHP 8.4, the theme's PHP test suites.

**Spec:** /Users/maciejpilarski/GitHub/wpcredits-program-manager/docs/specs/2026-10-22-theme-editable-hub-and-levels-design.md (D5, D6, D7 for the Partners template). The 1.32.0 plan beside this one holds Tasks 1 to 3 and the hub part of Task 5, all live since 23 October 2026; its Task 4 and the Partners part of its Task 5 are Tasks 2 and 4 here.

**Repository:** /Users/maciejpilarski/GitHub/wpcredits-theme, worktree `.worktrees/theme-1-32-1`, branch `release/1.32.1-partners-levels`, from main `9dc9e99` (1.32.0, live).

## Scope and rulings (the site's owner, 23 October 2026: "Yes, start 1.32.1", after the scope below was listed)

1.32.1 is: the Partners levels as one editable source; the migration's revision step fixed before it runs on the Partners template; protection for an emptied Program index; the leftovers both 1.32.0 reviews parked.

- Ruling R1 (D5): two blocks, not one. The cards (section `#levels`) and the table (section `#glance`) sit in two groups, each with its own core heading and anchor, so one block cannot draw both without moving those heads and changing markup. `wpcredits/partner-levels` takes the cards' place; `wpcredits/partner-glance` takes the table's and draws it from the levels of the Partners template in use, so the two still cannot disagree. Cost if wrong: one more block an Administrator can move apart from the cards.
- Ruling R2 (D6): with a saved Partners template that holds neither the levels block nor the 1.31 cards markup, `wpcredits_partner_levels()` returns an empty list, not the defaults: the page shows no levels, and with an empty list the plugin's form names the Partners page instead (`level_hint()`, plugin 1.123.24). Why: the spec's anti-goal "the sponsor application showing levels the Partners page does not". With no saved template the defaults are what the theme's file shows, so they are returned. Cost if wrong: a removed block sends the form to the page instead of listing five levels.
- Ruling R3: an index an Administrator emptied (marked, no cards) draws nothing: no chips, no cards, no band. The page follows the editor.

## Global Constraints

- No build step: editor scripts are plain ES5-style JavaScript against the `wp.*` globals (see `blocks/program-card/index.js`), each with a hand-maintained `index.asset.php` listing its dependencies and `WPCREDITS_VERSION`; block.json carries `"textdomain": "wpcredits-theme"`.
- New blocks are dynamic: `render.php` draws them from attributes; `save` returns `wp.blockEditor.InnerBlocks.Content` for containers and `null` for leaves; containers are registered with `skip_inner_blocks` as in `functions.php` today.
- The cards, for today's texts, render byte for byte as 1.31.11's grid; the table differs only in its rows' wording (D5). Prove both by rendering before and after into a scratch file and comparing.
- Every visible word is editable where it is shown (RichText inline, `allowedFormats` as the hub's blocks use them); lists and the table head's short amounts in the block's sidebar.
- The plugin's `wpcpm_partner_levels` contract is unchanged (`name`, `amount`, `lead`, `items`, optional `tag`), and every value handed to it is plain text: tags stripped, entities decoded (`html_entity_decode( wp_strip_all_tags( $v ), ENT_QUOTES | ENT_HTML5, 'UTF-8' )`), since the plugin escapes what it prints. (Ruled during execution, from the whole-branch review: the formula is for the rich fields `name`, `amount`, `lead` and `tag`; items are plain text, so for them only entities are decoded and white space collapsed, and "Up to <5 students" reaches the form whole.)
- No em or en dashes; US English; roles, never people's names, in code, comments, tests, fixtures and commit messages.
- Tests in the repository's style (`bin/test-*.php`), red before green; every `bin/test-*.php` and `php bin/check-selectors.php /Users/maciejpilarski/GitHub/wpcredits-program-manager` green; phpcs with `phpcs.xml.dist`, no new errors or warnings.
- WordPress core sources for reading (never editing) are in `/Users/maciejpilarski/Projects/wpcredits-docker/site/` (7.1.x, with the Gutenberg plugin).
- Do not touch the version, the readme's changelog or the `.pot`: the coordinator does those.
- No subagents, no network, no ssh, no docker, no stash, no push. Commits: subject plus one plain paragraph, ending the repository's Co-Authored-By trailer, via `git commit -F`.

## Review Focus

1. Rich text reaching the plugin: a level's name or item with bold, a link or an apostrophe entity shows in the sponsor form as plain words, never as `<strong>` or `&#039;` (Task 2 tests the filter's output on such values).
2. A level with an empty tag, lead or no items, and a table with no levels: no empty `<li>`, `<ul>`, tag `<span>`, or `<table>` printed (Task 2).
3. The table block placed where there is no levels block, or the levels block removed from a saved template: the table draws nothing and the filter follows R2 (Task 2).
4. A stale editor tab saving the Partners template with the 1.31 Custom HTML after the migration: repaired at once, the Administrator's other edits kept (Task 4).
5. The 1.32.1 runner where 1.32.0's never ran (a fresh install) or failed: each runs once, in order, and neither undoes the other (Task 4).

---

### Task 1: The migration's write keeps an exact copy

**Files:**
- Modify: `inc/migrate-1-32-0.php` (`wpcredits_migrate_1_32_0_write()`, the run, `wpcredits_migrate_1_32_0_on_save()`, the notice, the file docblock)
- Test: `bin/test-migrate-1-32-0.php`, `bin/stubs/hub-wp.php`

**Why:** on live, `wp_save_post_revision()` ran with kses on for a visitor's request, so the newest revision before the migration was the template with every `<svg>` stripped and core saw nothing new to keep; the record said `kept: revision` anyway. Its return is never checked.

**Interfaces:**
- Produces: `wpcredits_migrate_keep_copy( int $id ): array` returning `array( 'kept' => 'revision'|'record', 'revision' => int, 'before' => string )`: the post's current content kept as a revision written with kses off (`kses_remove_filters()`, restored in `finally`), its author the current user or, with none, the template's author; reused when the newest revision already holds exactly that content; read back, and when the read-back is not byte-equal or the write failed, `kept` is `record` and `before` carries the content for the record. Where revisions are off, `record` without trying.
- Produces: `wpcredits_migrate_1_32_0_write( $id, $content, $revision )` uses it and returns `array( 'saved' => true|'update-failed'|'saved-differently', 'kept' => ..., 'revision' => int )`; the run stores `kept`, `revision` and, for `record`, `before`. Task 4 consumes both.

- [ ] Step 1: tests: a template whose newest revision is the content minus its svg (the live case) gains a revision byte-equal to the content; one whose newest revision is already equal gains none and says `revision` with that id; a revision insert that fails, or reads back different, says `record` with `before`; the revision's author is the template's when no user is signed in; a throw from a later `save_post` hook after the database write is reported as saved when the post reads back with the new content; the recursion guard and `on_save()`'s catch each fail a test when removed (a stub write that keeps a target, a `has_term` throw during a save). Red.
- [ ] Step 2: implement; `on_save()`'s `update_option()` inside its try; the notice checks the screen before it reads the option; the file docblock no longer says a failed run retries.
- [ ] Step 3: green; commit.

### Task 2: The Partner levels and the table as editable blocks, one source

**Files:**
- Create: `blocks/partner-levels/`, `blocks/partner-level/`, `blocks/partner-glance/` (block.json, index.js, index.asset.php, render.php each)
- Modify: `inc/partners.php`, `patterns/partners-levels.php` (the two Custom HTML blocks become the two blocks; the heads, the pilot band and the groups stay core blocks), `functions.php` (register the three, the container with `skip_inner_blocks`)
- Test: `bin/test-partners.php`

**Interfaces:**
- Produces: `wpcredits/partner-levels` (container, inner blocks `wpcredits/partner-level` only, `supports.multiple` false) drawing `ul.wpc-partners-levels__grid` with one `li.wpc-partners-level` per level as 1.31.11 (`--program` when the level has a tag), nothing when it holds none. `wpcredits/partner-level` attributes `name`, `amount`, `short`, `tag`, `lead` (strings, rich text where shown on the card; `short` in the sidebar), `items` (array of strings, one per line in the sidebar).
- Produces: `wpcredits/partner-glance` (leaf; attributes `label`, `yes`, `no`, plain strings, the head cell's word and the two cell words) drawing 1.31.11's `div.wpc-partners-glance__scroll` with `aria-labelledby="wpc-partners-glance-title"` and the table: the head one column per level (name and `short`), then one row per item of every level, lowest level first, "Yes" from the level that lists it upward; nothing when there are no levels. Its editor draws the same table from the editor's own Partner levels block (via `wp.data.select( 'core/block-editor' )`), so an edit shows at once, and a short note when the template has none.
- Produces in `inc/partners.php`: `wpcredits_partner_levels_defaults(): array` (today's list, moved, `short` kept); `wpcredits_partner_levels(): array` per R2: no saved Partners template (`get_block_template( get_stylesheet() . '//page-partners' )` with source `theme`) gives the defaults; a saved one gives its levels block's levels (searching inner blocks and `core/pattern` references), else the levels read from 1.31 cards markup in it, else an empty list; computed once per request, with `wpcredits_partner_levels_reset()` for tests; `wpcredits_partner_levels_from_html( string $html ): array` (each `name`, `amount`, `lead`, `items`, `tag` when present; `short` from the table head's `.wpc-partners-glance__amount` by column when the html holds one; empty array when unreadable); `wpcredits_partner_levels_markup( array $levels ): string` and `wpcredits_partner_glance_markup(): string`, the serialized blocks, used by the pattern and Task 4. `wpcredits_partners_levels_for_form()` keeps its shape and hands plain text.
- The pattern calls `wpcredits_partner_levels_defaults()`, never `wpcredits_partner_levels()` (which may read the template, whose file refers to this pattern).

- [ ] Step 1: tests: five levels draw the cards byte for byte as 1.31.11's grid; the table's head and the "Yes" pattern from the items, a row per item in the items' own words; a level with an empty tag draws no tag span, with no items no `ul`; no levels draw nothing for both blocks; the filter with no saved template (the defaults), a saved template with edited levels (those), with an emptied levels block (empty), without the block but with 1.31 cards markup (read from it), with neither (empty); a level name with `<strong>` and an item with `&#039;` reach the filter as plain text. Red.
- [ ] Step 2: implement.
- [ ] Step 3: green; the byte comparison of the cards and the table's row wording before and after in the commit message; commit.

### Task 3: An emptied Program index stays empty

**Files:**
- Modify: `blocks/program-index/block.json` (attribute `editable`, boolean, no default; a default variation, `isDefault: true`, setting it, so an index inserted from the inserter carries it), `blocks/program-index/render.php` and `inc/hub-content.php` (the 1.31 fallback only for an index with no cards and no `editable`; a marked index with no cards draws nothing, R3), `inc/migrate-1-32-0.php` (the repair leaves a marked empty index alone), `inc/hub-content.php`'s `wpcredits_hub_program_cards_markup()` caller in the pattern and the 1.32.0 migration's output (the index written with `"editable":true`)
- Test: `bin/test-hub.php`, `bin/test-migrate-1-32-0.php`

**Interfaces:**
- Produces: `wpcredits_hub_index_is_legacy( array $block ): bool` (no `wpcredits/program-card` inner block and no `editable`), used by the render and by Task 4.

- [ ] Step 1: tests: a self-closing index without `editable` draws the theme's programs and the band as today; `{"editable":true}` with no cards draws nothing and a save of it is not repaired; the pattern's and the 1.32.0 migration's index carry `"editable":true`. Red.
- [ ] Step 2: implement.
- [ ] Step 3: green; commit.

### Task 4: The 1.32.1 migration of the saved templates

**Files:**
- Modify: `inc/migrate-1-32-0.php` (a second runner and the pure function below; the repair and the notice extended), `bin/fixtures/saved-partners.html` (create: live's saved Partners template as read on 23 October 2026, supplied by the coordinator)
- Test: `bin/test-migrate-1-32-0.php`

**Interfaces:**
- Consumes: Task 1's `wpcredits_migrate_keep_copy()` and write; Task 2's `wpcredits_partner_levels_from_html()`, `wpcredits_partner_levels_markup()`, `wpcredits_partner_glance_markup()`, `wpcredits_partner_levels_defaults()`; Task 3's `wpcredits_hub_index_is_legacy()`.
- Produces: `wpcredits_migrate_1_32_1_content( string $content, string $slug ): array` (`content`, `changed`, `skipped`), pure: for `page-partners`, the Custom HTML holding `ul.wpc-partners-levels__grid` becomes the levels block with the texts read from it (the defaults only when unreadable, said in `skipped`), the Custom HTML holding the glance table becomes the glance block; for `page-education-hub`, a container index without `editable` gains `"editable":true` and nothing else changes. Every other byte kept.
- Produces: the runner on `init` once, option `wpcredits_migrated_1_32_1`, after the 1.32.0 runner, for this theme's saved `wp_template` posts `page-partners` and `page-education-hub`, recording per template what changed and how the copy was kept; the repair on `wp_after_insert_post` runs both migrations' content functions for these two slugs; the notice tells both records' sentences, shown until dismissed or a week after the newer run.

- [ ] Step 1: tests on the fixture: the Partners copy's cards become the levels block with the five levels read from it (equal to the defaults for the live copy), the table becomes the glance block, the rest byte-identical; the hub fixture migrated by 1.32.0 gains only `"editable":true`; a second run changes nothing; a template without targets is skipped; a stale save of the 1.31 Partners markup is repaired; both runners on a fresh install run once each, in order. Red.
- [ ] Step 2: implement.
- [ ] Step 3: green; commit.

### Task 5: The leftovers

**Files:**
- Modify: `assets/css/hub.css` (the courses band's 56px also where the index is outside `.wpc-hub`, two classes deep: `.wpc-hub-index .wpc-hub-courses`), the new blocks' block.json (no English text defaults: every text default moves to a translated fallback in render.php and in the editor's value), `blocks/program-index/view.js` (the chips count only cards shown at the current viewport, recounted on resize), and their tests
- Test: `bin/test-hub.php`, `bin/test-hub-js.php`

- [ ] Step 1: tests: a draft page's 1.31 index keeps its gap (selector present); the fit list with no labels saved draws the translated defaults; a card hidden at this viewport by core's block visibility is not counted (read core's visibility markup in the Docker copy first). Red.
- [ ] Step 2: implement.
- [ ] Step 3: green; commit.

### Task 6 (coordinator): version, docs, trial

- [ ] Version 1.32.1 in style.css and the readme's Stable tag, the changelog entry (the levels and the table edited where shown, the table's rows in the items' words, the migration, the index protection), the `.pot`.
- [ ] Docker: live's two saved templates as live has them, snapshot, 1.32.1 installed locally; the migration's record; the Partners page with and without JavaScript at 375px and 1440px against 1.32.0's; the sponsor application's "What each level includes" equal to the page; the blocks valid in the real editor build; a level edited and saved through the REST templates endpoint as an Administrator would, with the page and the form both following.
- [ ] Deploy and mirror only on the owner's yes; read back the live migration result and the Partners page.
