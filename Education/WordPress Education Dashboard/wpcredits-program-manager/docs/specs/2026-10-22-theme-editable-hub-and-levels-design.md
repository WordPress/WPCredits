# Theme 1.32.0: the Education Hub and the Partners levels edited in the Site Editor

Approved by the site's owner on 22 October 2026 ("Yes, go ahead with 1.32.0, levels as part of it"), from the proposal in chat summarized here.

## Why

Administrators edit the Education Hub template (`page-education-hub`) and the Partners template (`page-partners`) in the Site Editor. Two parts of the hub cannot be edited there: the Program index (a server-rendered block reading a PHP list in the theme) with its WordPress Credits courses band, and "Find where you fit" (a Custom HTML block, editable only as markup). The Partners page's five levels are written in a PHP list that also feeds the plugin's sponsor application ("What each level includes", through the filter `wpcpm_partner_levels`, plugin 1.123.24); once the template is saved in the Site Editor, the page and the form can drift apart.

On live, both templates already have saved copies (two Administrators saved them on 22 October 2026, before this release): full copies with no pattern references, so theme releases no longer change their content.

## Goals

1. Every word of the Education Hub template is edited in the Site Editor, inline where it is shown.
2. The Partners levels are written once, in the Partners template, edited in the Site Editor, and the plugin's sponsor application reads them from there.
3. Live parts stay live: the Sign in block, the sponsors strip, the program numbers (their labels editable where they have words), the decorative art.
4. The saved copies on live are updated in place: only the parts this release replaces change; every other edit the Administrators made stays.

## Non-goals

- No change to how the pages look: same markup classes, same styles, same behavior with and without JavaScript (the filter chips, the anchors `program-<slug>` and `<slug>-courses`, the level anchors `#levels` and `#glance`).
- No build step: the theme's block editor scripts stay hand-written against the `wp.*` globals with hand-maintained `index.asset.php` files.
- No new settings screens: everything is edited where it is shown.
- The rest of the hub (hero, doors, closing band) is already core blocks; untouched.

## Anti-goals

- Losing an Administrator's edit in a saved template.
- A block the editor reports as invalid ("This block contains unexpected or invalid content") after a theme update: the new blocks are dynamic (server-rendered from their attributes, `save` returns inner blocks only), so markup changes never invalidate saved content.
- The sponsor application showing levels the Partners page does not.

## Decisions

- **D1 Program index.** `wpcredits/program-index` stays the container (the chips, their counts and announcements, the no-JavaScript behavior, `supports.multiple` false), now holding `wpcredits/program-card` inner blocks only. The chips and counts are computed from the cards present. A card with status "In development" is shown only where the theme shows planned programs today (`wpcredits_hub_shows_planned()`).
- **D2 Program card** (`wpcredits/program-card`, dynamic): `slug` (the card's id, `program-<slug>`), `name`, `status` (`live` or `development`), `audiences` (any of `student`, `faculty`, `career`), `what`, `time`, `gets` (list of strings), `links` (list of label and address; an address may also be one of the keys `institution_application`, `sponsor_application`, `partners`, resolved by `wpcredits_hub_url()`). Visible texts are edited inline; lists, audiences, status and the slug in the sidebar where inline editing is not practical.
- **D3 Courses band** (`wpcredits/course-band`, dynamic, with `wpcredits/course-card` inner blocks): the band's eyebrow, heading and lede, and per course its name, address, eyebrow (hours or audience) and line. The band keeps the anchor `<program slug>-courses` (an attribute) and sits after the Program index, unaffected by the chips as today.
- **D4 Field of study rows** (`wpcredits/fit-list`, dynamic, with `wpcredits/fit-row` inner blocks): per row the field, the teams (list of strings) and what the student has to show; the list keeps its semantics (`role="list"`, one item per row, the hidden step names for screen readers), the header line, the arrows and the check marks, all drawn by the blocks and the stylesheet.
- **D5 Partner levels** (`wpcredits/partner-levels`, dynamic, with `wpcredits/partner-level` inner blocks): per level `name`, `amount`, `short`, `tag` (optional), `lead`, `items` (list of strings). The container draws the level cards and the "Levels at a glance" comparison table. **The table is derived from the levels' items**: each item is a row, with "Yes" from the level that lists it upward, so the cards and the table can never disagree. The rows read as the items do (for example "Company name on the partner page"), a small change of wording from 1.31.11's separate row list.
- **D6 One source for the plugin.** `wpcredits_partner_levels()` returns the levels read from the Partners template in use (the saved copy if one exists, else the theme's file): parse its blocks, find `wpcredits/partner-levels`, read its levels' attributes. With none found, the theme's built-in defaults (today's texts). The filter `wpcpm_partner_levels` returns the same shape as in 1.31.11 (`name`, `amount`, `lead`, `items`, and `tag` when set). Read once per request.
- **D7 Migration of saved templates.** Once, on the first request after the theme updates to 1.32.0, for each saved `wp_template` of this theme that holds a target: the old Program index (the self-closing block) becomes the container with one card per program from the theme's list (every program, live and in development), followed by the courses band if the template has none; the "Find where you fit" Custom HTML block becomes the fit list, **its rows read from that saved markup** (falling back to the theme's list only when it cannot be read); the expanded levels section of a saved Partners template becomes the levels block, **its texts read from that saved markup** (same fallback). The content before is kept as a revision of the template post. A template whose target cannot be found is left as it is, and the result per template is recorded for Administrators to see. Running it again changes nothing.
- **D8 Docs.** The theme's readme says what is edited where. The administrator guide (plugin) gains a short "Editing the Education Hub and the Partners page" passage in a later plugin release.

## Constraints

WordPress 7.1, PHP 8.4 on live; no em or en dashes; US English; full product names; roles, never people's names, in code, comments, docs and commit messages; the theme's existing test suites (`bin/test-*.php`, the JavaScript scenarios) and `bin/check-selectors.php` green; phpcs with the theme's ruleset, no new errors or warnings.
