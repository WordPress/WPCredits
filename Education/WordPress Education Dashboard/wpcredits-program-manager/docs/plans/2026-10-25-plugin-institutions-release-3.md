# Plugin and theme: Institutions release 3, the Sponsor Dashboard in the Institution Dashboard's shape, implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The Sponsor Dashboard opens with a strip of the tier and what waits on the sponsor's people, then the help with the program contact, then four sections the company can arrange, in the Institution Dashboard's shape.

**Architecture:** Each card that can wait on a sponsor gains one public reader, used by the card and by the strip, so a tile and its card cannot disagree. `WPCPM_Sponsors_Dashboard` redraws its strip from those readers, moves its help up in the two-column shape, and draws its four groups as modules through a module renderer extracted from the Institution Dashboard into `WPCPM_Module_Order`, with a move handler of its own. Theme 1.32.5 dresses the sponsor modules with the Institution Dashboard's values.

**Tech Stack:** PHP 7.4+ WordPress plugin, prefix `WPCPM_`; FSE theme `wpcredits_`. The no-WordPress suites `bin/test-*.php` in each repository: run each from its root with `php bin/test-x.php`; each ends `ALL PASS` and exits 0. The theme's `php bin/check-selectors.php <plugin path>`.

**Spec:** `docs/specs/2026-10-25-institutions-release-3-design.md` (approved by the product owner on 25 October 2026), with `docs/specs/2026-10-24-institutions-features-design.md` section 5. The chosen mockups are in `.superpowers/session-backups/2026-10-25/institutions-release-3-mockups/` (`glance-strip-chosen-A.html`, `page-order-chosen.html`), and today's screenshots in `.superpowers/session-backups/2026-10-25/institutions-release-3/`. They are private and never mirrored.

**Repositories:**
- Plugin: ~/GitHub/wpcredits-program-manager. Worktree `.worktrees/plugin-institutions-release-3`, branch `release/<version>-institutions-release-3`, from `main` at the commit that adds this plan.
- Theme: ~/GitHub/wpcredits-theme. Worktree `.worktrees/theme-sponsor-modules`, branch `release/<version>-sponsor-modules`, from `main`.
- The versions are the next free numbers when the build starts (1.123.38 and 1.32.5 today).

## Global Constraints

- **Leave the release files alone.** Do not touch either version, `readme.txt`'s changelog or the `.pot`: the coordinator does those.
- **Words:**
  - Never an em or en dash, anywhere.
  - US English, "Administrators", full product names.
  - No person's name in code, comments, docs, tests or commit messages: roles only. Test addresses use `example.test`.
- **Copy:** every user-facing string quoted in this plan is used verbatim; plurals through `_n()`, numbers through `number_format_i18n()`.
- **Code style:** match the surrounding code: docblocks that say why, escaping at output, a translators comment on every placeholder.
- **phpcs:** no new errors or warnings (60 warnings and no errors in the plugin today).
- **No Airtable read or write is added.** Sponsor records live on the site.
- **The Institution Dashboard's output does not change.** Any code shared with it keeps its markup byte for byte; its suite pins it.
- **One source per figure:** a tile counts through the same public reader its card uses. No second query, no copy of a test.
- **No tracked `.csv`, `.json`, `.jsonl` or `.html` file may hold an email address.**
- **Tests first,** in the repository's style, each shown failing before its fix.
- **Before each report,** run all of these and quote their last lines, with `PATH="<scratchpad>/phpcs-1g:$PATH"`:
  - the plugin battery (every `bin/test-*.php`), `php bin/check-references.php`, `php bin/check-spelling.php`, `php bin/test-fixtures.php`, `bash bin/check-standards.sh`, `php bin/check-dead-annotations.php`;
  - for the theme task: the theme battery and `php bin/check-selectors.php ~/GitHub/wpcredits-program-manager/.worktrees/plugin-institutions-release-3`.
- **Hands off:** no subagents, no network, no ssh, no docker, no stash, no push.
- **Commits:** one or more per task, on the release branch only. Each is a subject line plus one plain paragraph, ends with the Co-Authored-By trailer naming your own model, and is made via `git commit -F`.
- **Docs:** each plugin task updates the docs/sections paragraphs that describe what it changes (the Sponsor guide's `40-` to `43-`, the Administrator guide's paragraphs on the Sponsor Dashboard), rebuilds with `php bin/build-docs.php`, and commits the built files with their sources.

## Review Focus

1. **One offer that matches two kinds,** a new offer an Administrator returned (it sits in the draft state). It counts once, as returned, never also as not switched on yet (Task 1 pins it).
2. **A live offer past its last day with few codes left,** listed by the card under ended and expired. It draws no "running low" tile (Task 1 pins it).
3. **The tiers switched off.** No tier tile and no tier waiting item; the strip is the waiting tiles, or "Nothing waits for you" alone (Task 2 pins it).
4. **A member of one company posting a move for another company's page, or a saved order holding a key this version does not know.** The move lands on the member's own company only, and the stored order is repaired on read, new modules joining in the default order (Task 4 pins both).
5. **An assigned Administrator who has since lost the program's capability.** The help draws no contact line, and "Talk to your program contact" falls back as today (Task 3 pins it).

---

### Task 1: The waiting readers

**Files:**
- Modify:
  - `includes/modules/class-wpcpm-sponsor-offers.php` (`is_low()`, `waiting_counts()`; `render_offer()` uses `is_low()`);
  - `includes/modules/class-wpcpm-sponsor-posts.php` (`returned_count()`);
  - `includes/modules/class-wpcpm-sponsor-agreement-card.php` (`waiting_state()`);
  - `includes/modules/class-wpcpm-sponsor-profile.php` (`missing()`).
- Test: `bin/test-sponsor-offers.php`, and the suites that cover posts, the agreement card and the profile (find them with `grep -l` on each class name in `bin/`).

**Interfaces:**
- Produces:
  - `WPCPM_Sponsor_Offers::is_low( array $offer, array $counts, string $today = '' ): bool`: the offer is live today (`is_live( $offer, $today )`) and `$counts['available'] < (int) $offer['low']`. `render_offer()` draws its "Running low" line on this test.
  - `WPCPM_Sponsor_Offers::waiting_counts( string $record, string $today = '' ): array` with the keys `returned`, `low`, `draft`, each an int, over `offers_of( $record )`. Exclusive, in this order: an offer counts as `returned` when the card shows it as returned with a note (the card's own test, its "Returned by the program with a note" line or a returned change), else as `low` when `is_low()`, else as `draft` when its state is `STATE_DRAFT`.
  - `WPCPM_Sponsor_Posts::returned_count( string $record ): int`: the sponsor's posts whose `state_of()` is `returned`.
  - `WPCPM_Sponsor_Agreement_Card::waiting_state( string $record ): string`: `returned`, `revoked` or `''`, from `WPCPM_Sponsor_Agreement::summary()` through `asks_for_file()`.
  - `WPCPM_Sponsor_Profile::missing( array $row ): int`: `count( self::details() ) - self::filled( $row )`, never below 0.
- Uses as they are: `WPCPM_Sponsor_Event::returned_note( $record )` (a non-empty string means one event returned).

- [ ] **Step 1: Write the failing tests.**
  - **`waiting_counts()`:**
    - a returned new offer (draft state, returned) counts `returned` 1, `draft` 0;
    - a live offer with a returned change counts `returned` 1;
    - a live offer under its low mark counts `low` 1;
    - a live offer past its last day under its low mark counts nothing;
    - a plain draft counts `draft` 1;
    - a paused offer and an ended one count nothing.
  - **`is_low()`** answers as `render_offer()` draws: the card's "Running low" line appears exactly when `is_low()` is true, over the same fixtures.
  - **`returned_count()`** counts a post with a return note and not a draft without one.
  - **`waiting_state()`** answers `returned` and `revoked` for those two summaries and `''` for none, accepted and waiting for review.
  - **`missing()`** answers 3, 1 and 0 over an empty, a two-filled and a complete row.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement.** Move the low test out of `render_offer()` into `is_low()` without changing what the card prints.
- [ ] **Step 4: Run green,** then the battery and the checks.
- [ ] **Step 5: Commit** (no docs: nothing a reader sees changes).

### Task 2: The strip

**Files:**
- Modify: `includes/modules/class-wpcpm-sponsors-dashboard.php` (`render_glance()`, `glance_tiles()`; `glance_event_tile()` and the figure tiles' helpers removed when nothing else calls them); `assets/css/administrator.css` (the waiting mark).
- Test: `bin/test-sponsors-dashboard.php`.

**Interfaces:**
- Consumes: Task 1's five readers and `returned_note()`.
- Produces: the strip's tiles, in this order, each `array( 'card', 'value', 'label', 'zero', 'waiting' )`, drawn through the existing tile markup with the modifier `wpcpm-attention__tile--waiting` when `waiting` is true:
  1. the tier tile, as `glance_term()` words it today, while `WPCPM_Sponsor_Tiers::live()`; `waiting` when `WPCPM_Sponsor_Tier_Card::term_days_left()` is not null and at most `WPCPM_Sponsor_Tiers::NOTICE_DAYS` (an ended term, below 0, included); it opens the Sponsorship card;
  2. "Offer returned with a note" / "Offers returned with a note";
  3. "Offer running low on codes" / "Offers running low on codes";
  4. "Offer not switched on yet" / "Offers not switched on yet";
  5. "Post returned with a note" / "Posts returned with a note";
  6. "Event returned";
  7. "Collaboration Agreement returned" or "Collaboration Agreement revoked";
  8. "Company profile detail missing" / "Company profile details missing";
  - and, only when none of 1 to 8 is waiting, the muted "Nothing waits for you" with the value 0.
- A waiting tile (2 to 8) is drawn only when its count is above 0 and its card is drawn, asked the way the card asks it (as `glance_tiles()` asks today: the Events card's `draws_events()`, the Posts card's `draws()`, and so on). Each opens its card's anchor.
- The figure tiles (the next event, the offers live with claims, the mentors sponsored, the yearly reports) are gone; their figures already show in their cards' title lines.
- A sponsor the store holds no row for draws no strip; the "fewer than two tiles" rule goes.
- `.wpcpm-attention__tile--waiting { border-inline-start: 4px solid #b32d2e; }` in `assets/css/administrator.css`, beside `--zero`.

- [ ] **Step 1: Write the failing tests.**
  - The tier tile comes first and carries `--waiting` at 30 days left, 0 days and an ended term, and not at 31 days or with no term.
  - Each kind draws its tile with its singular and plural label and its count, in the order above; a kind at 0 draws nothing.
  - "Nothing waits for you" is drawn only when no tile, the tier's included, is waiting.
  - With the tiers off: no tier tile; one waiting tile alone makes the strip; nothing waiting makes "Nothing waits for you" alone.
  - The events tile is not drawn while `draws_events()` is false, though `returned_note()` holds a note.
  - None of "Offers live", "mentors sponsored" or the next event's title appears in the strip.
  - No row: no strip.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run green,** then the battery and the checks.
- [ ] **Step 5: Docs** (the Sponsor guide's paragraph on At a glance, the Administrator guide's line about it), then commit.

### Task 3: The help moved up, its contact, and the header

**Files:**
- Modify: `includes/modules/class-wpcpm-sponsors-dashboard.php` (`render()`'s order, `render_help()`, `contact_block()`, `render_identity()`).
- Test: `bin/test-sponsors-dashboard.php`.

**Interfaces:**
- `render()` prints, in this order: the switcher and the flash as today, `render_identity()`, `render_glance()`, `render_help()`, then the sections (Task 4).
- `render_help()` calls `WPCPM_Handbook_Assistant::render_resources( 'sponsor', self::contact_block( $record ) )` with the default two columns, so the contact is drawn under the buttons as on the Institution Dashboard, and no `wpcpm-resources--split-3` is printed.
- `contact_block()` keeps its markup; its sentence becomes "They look after your company's sponsorship. Write to them about anything the guide does not answer." It draws nothing when `WPCPM_Sponsors_Index::manager_of()` is null, as today.
- `render_identity()`'s contact line prints the contact's name only, as the Institution Dashboard's does.

- [ ] **Step 1: Write the failing tests.**
  - The identity header, the strip and the help appear in that order, and the help comes before the first section.
  - The help holds no `wpcpm-resources--split-3`, and "Your contact at the program" follows the Resources buttons.
  - The contact sentence is the one above.
  - An assigned Administrator without the program's capability draws no contact line (Review Focus 5).
  - The header's contact line holds the name and no address.
  - The page prints no "Read from the program records" line (sponsor records live on the site).
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run green,** then the battery and the checks.
- [ ] **Step 5: Docs** (the Sponsor guide's opening section: where help and the contact are now), then commit.

### Task 4: The sections as modules, and the mover

**Files:**
- Modify:
  - `includes/class-wpcpm-module-order.php` (`render_module()`);
  - `includes/modules/class-wpcpm-institutions-dashboard.php` (`render_module()` calls the shared one);
  - `includes/modules/class-wpcpm-sponsors-dashboard.php` (modules, leads, order, move handler, the drawing loop);
  - `uninstall.php` (the saved orders' prefix).
- Test: `bin/test-sponsors-dashboard.php`, `bin/test-institutions-dashboard.php`, `bin/test-uninstall.php`.

**Interfaces:**
- Produces:
  - `WPCPM_Module_Order::render_module( array $args ): void`, `$args` = `key`, `title`, `lead`, `body` (escaped markup), `index`, `count`, `action`, `fields` (the hidden fields the mover posts), `can_move`, `title_class`, `lead_class`: prints exactly what `WPCPM_Institutions_Dashboard::render_module()` prints today, with the two classes taken from the arguments (`wpcpm-institution__module-title` and `wpcpm-institution__module-lead` there).
  - `WPCPM_Sponsors_Dashboard::OPT_MODULES_PREFIX = 'wpcpm_sponsor_modules_'`, `ACTION_MOVE = 'wpcpm_sponsor_module_move'`, `FIELD_SPONSOR = 'wpcpm_sponsor'`.
  - `WPCPM_Sponsors_Dashboard::modules(): array` and `leads(): array`, keyed `sponsorship`, `offer`, `mentors`, `company` (today's group ids), titled "Your sponsorship", "What you offer", "Mentors", "Your company", with today's group leads (the offer lead keeps its two forms by `WPCPM_Sponsor_Posts::draws()`; the sponsorship lead keeps `sponsorship_lead()`).
  - `WPCPM_Sponsors_Dashboard::module_order( string $record ): array`: the saved order repaired with `WPCPM_Module_Order::repair()`, today's order the default.
  - `WPCPM_Sponsors_Dashboard::handle_move(): void` on `admin_post_wpcpm_sponsor_module_move`, as `WPCPM_Institutions_Dashboard::handle_move()`: nonce first; an Administrator may name a sponsor key in `FIELD_SPONSOR` (`WPCPM_Sponsors_Index::is_key()`), anyone else moves only the company `WPCPM_Sponsor_Members::sponsor_of()` gives them, and an account with neither is refused with "Only someone who acts for the company, or an Administrator on their behalf, can arrange a Sponsor Dashboard." (403); answers JSON to the script and redirects otherwise to the page (with the Viewing as argument for an Administrator on another company's page) at `#wpcpm-module-<key>`.
- The sections: each drawn through `WPCPM_Module_Order::render_module()` with `wpcpm-sponsor__module-title` and `wpcpm-sponsor__module-lead`, the bodies first and the page after, as the Institution Dashboard does, so a module whose cards print nothing is left out and the arrows count only what is drawn. The cards inside are drawn as today. The shared script is enqueued for those who may move: the company's members and Administrators.
- `uninstall.php` deletes the options under `wpcpm_sponsor_modules_` beside `wpcpm_institution_modules_`.

- [ ] **Step 1: Write the failing tests.**
  - **The Institution Dashboard's three modules print byte for byte what they print today** (capture before the change and compare).
  - The Sponsor Dashboard draws four modules with their titles, leads and "Move Your sponsorship up" style arrows, in the saved order; the first drawn has Move up disabled and the last Move down.
  - A module whose cards print nothing (the sponsorship module while the tiers are off) is not drawn, and the arrows count only the drawn ones.
  - A saved order with an unknown key and without `company` is repaired: the unknown key dropped, `company` joining in the default place (Review Focus 4).
  - `handle_move()`: a member moves their own company's page; a member posting another company's key moves their own company and not the other; an Administrator moves the named company; an anonymous or unrelated account is refused with the sentence above and 403; the async post answers JSON with the new order.
  - The uninstall suite deletes a `wpcpm_sponsor_modules_` option.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run green,** then the battery and the checks.
- [ ] **Step 5: Docs** (the Sponsor guide: the four sections and the arrows, as the Institution guide's "Arranging the page" says it; the Administrator guide's paragraph on arranging a sponsor's page), then commit.

### Task 5 (theme): the sponsor page in the Institution Dashboard's values

**Files:**
- Modify: `assets/css/dashboard.css` in the theme; `bin/check-selectors.php` (`$kept`).
- Test: the theme battery and `check-selectors.php`, run against the plugin's release worktree.

**Interfaces:**
- Consumes: Task 4's markup (`.wpcpm-module` with `wpcpm-sponsor__module-title` and `wpcpm-sponsor__module-lead` on `.wpc-sponsor-page`), Task 3's help in two columns under the strip, Task 2's `wpcpm-attention__tile--waiting`.
- Every rule the Institution Dashboard's modules take on `.wpc-institution-page` (the module line and padding, the space between modules, the cards inside a module with no line of their own, the module title and lead's padding and margins, their phone values) gains the `.wpc-sponsor-page` selector for the sponsor's module classes and cards (`.wpcpm-sponsor__card` in place of the institution's cards), value for value, in the same rule where the selector lists allow it.
- The rules that placed the help at the foot (`.wpcpm-sponsor:has( > .wpcpm-handbook__resources:last-child )`, `.wpcpm-sponsor__group + .wpcpm-handbook__resources`) give way to the Institution Dashboard's spacing for a help block under the strip.
- `.wpc-sponsor-page .wpcpm-attention__tile--waiting` takes `border-inline-start-color: var( --wpc-red, #b32d2e )`, as the People flash's refusal does.
- The `.wpcpm-sponsor__group*` rules stay for a rollback, listed in `$kept` with plugin 1.123.37 as the last version that printed them.

- [ ] **Step 1: Write the failing check:** run `check-selectors.php` against the plugin's release worktree before the CSS change and note what it reports; after the change it must pass with the sponsor group classes reported as kept, not missed.
- [ ] **Step 2: Change the stylesheet.**
- [ ] **Step 3: Run the theme battery and the selector check against the plugin's release worktree; both pass.**
- [ ] **Step 4: Commit.** The changelog line is the coordinator's.

### Task 6 (coordinator): versions, trial, reviews, launch

- [ ] **The release files:** both versions (the next free numbers), both changelogs, both `.pot` files.
- [ ] **The whole-branch review** (Opus) and **the Codex second opinion** of the plugin diff (read-only, a fresh folder outside the repository with only the diff, the spec and this plan, checked for addresses and names first), in parallel; one fix wave, one scoped re-review.
- [ ] **Docker:** a snapshot first, then both zips. Then check, as a sponsor's member and as an Administrator viewing:
  - the strip with nothing waiting, with each kind made to wait on a test sponsor, with a term in its last 30 days and one ended;
  - the help under the strip, with and without an assigned Administrator;
  - a move by a member, reloaded and seen by a second member, and an Administrator's move for them;
  - the page at 1280 and 375 pixels beside the Institution Dashboard, every gap measured.

  Then restore and reinstall the live versions.
- [ ] **For the owner's yes:** deploy and mirror the plugin, then the theme.
