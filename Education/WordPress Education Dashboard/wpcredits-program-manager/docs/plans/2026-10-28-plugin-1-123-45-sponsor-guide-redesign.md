# Plugin 1.123.45: the Sponsor guide in the Institution guide's shape, with screenshots

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The Sponsor guide (page /sponsor-guide/, built from `docs/sections/` into `docs/build/sponsors.html`) reads like the Institution guide: short subsections in the order the Sponsor Dashboard shows things, lists for states and steps, and screenshots of the dashboard.

**Architecture:** Two tasks. Task 1 makes an invented sponsor on the Docker copy of the site and captures the screenshots into `docs/images/`. Task 2 rewrites the sponsor sections around those screenshots and rebuilds the guide. Nothing on the live site changes in this plan; the release (version, the images in the live media library, their attachment IDs, deploy) follows the product owner's approval of the draft, through the release script.

**Tech Stack:** Markdown sections built by `bin/build-docs.php` (`{{image:slug|caption}}` becomes an image block); the Docker copy at http://localhost:8900 (`~/Projects/wpcredits-docker`, `bin/wp`, `bin/snapshot`, `bin/restore`); headless Chrome over the DevTools protocol for captures.

**Spec:** None written. The product owner's words: the Sponsor guide is "a wall of text, make it more user friendly, add screenshots", and "Just draft it, follow the Institution guide" (the questions were skipped). The Institution guide is `docs/sections/50-institution-dashboard.md`, `51-institution-students.md`, `52-institution-details.md`; it is the model for shape and tone.

## Global Constraints

- Plain hyphens only: never an em dash or an en dash, in text, captions, comments or commits.
- US English. "Administrators" is the one name for the people who run the program. "Student Report Card" and "Mentor Report Card" in full, never a bare "Report Card". The site is "the Education Directory".
- No real person's name in any doc text or caption; roles only ("your program contact", "an Administrator"). Every name, email, company, code and post title visible in a screenshot is invented.
- `docs/sections/00-signing-in.md` is shared by every guide and is not edited.
- Every fact the four current sponsor sections state is kept, unless the code shows it is wrong; then the text follows the code and the report lists the change with the code line.
- Every string quoted as on-screen text matches what the code prints, character for character.
- Nothing is done on the live site and nothing is pushed.

## Review Focus

1. A condition lost in the restructure: which tier shows which card or box (Friend, Supporter, Backer, Champion, Partner), and what happens before the tiers launch, at the term's end and while a sponsorship is paused. Expected: each condition the current text states is still stated, beside the thing it governs.
2. A quoted on-screen string the code does not print, or prints differently. Expected: none.
3. A screenshot showing a real person, a real email, a real offer code or anything from a real sponsor's private records. Expected: none; the Docker copy holds live data, so only the invented sponsor's page is captured, and cards listing other people are invented or left out of the frame.
4. A screenshot contradicting the text beside it (a card shown at a tier the text says does not have it). Expected: none.
5. A link from the plugin's code into a heading of the Sponsor guide (an anchor) that the new headings no longer produce. Expected: every such anchor still resolves.

---

### Task 1: An invented sponsor on the Docker copy, and the screenshots

**Files:**
- Create: `docs/images/sponsor-dashboard-top.png`, `docs/images/sponsor-dashboard-cards.png`, `docs/images/sponsor-sponsorship-card.png`, `docs/images/sponsor-offers-card.png`, `docs/images/sponsor-usage-card.png`, `docs/images/sponsor-company-card.png`, and, when the invented sponsor's tier shows the Events card, `docs/images/sponsor-events-card.png`.
- Create, outside every repository: `~/Projects/wpcredits-docker/release/guide-shots/` for the seed script, the capture script and their notes (the copy's WP-CLI sees that folder at the same path). Never inside the plugin checkout: `bin/check-references.php` walks it, ignored folders included.

**Interfaces:**
- Produces: the image slugs above, each a PNG captured at device scale factor 2 from a 1280 CSS pixel wide window (about 2520 pixels wide, like `docs/images/admin-overview.png`), cropped to the element with a 24 CSS pixel margin. Task 2 places them with `{{image:<slug>|<caption>}}`.

- [ ] **Step 1: Snapshot the Docker copy.** Run `~/Projects/wpcredits-docker/bin/snapshot before-guide-shots`. It is on plugin 1.123.44 and theme 1.32.9, as live.
- [ ] **Step 2: Write and run the seed script** (`guide-shots/seed.php`, run as `bin/wp eval-file - < ...`, refusing to run unless `siteurl` is `http://localhost:8900`). It makes, through the plugin's own classes wherever they offer a way (read `includes/class-wpcpm-sponsors.php`, `class-wpcpm-sponsor-tiers.php`, `class-wpcpm-sponsor-members.php`, `class-wpcpm-sponsor-offers.php`, `class-wpcpm-sponsor-codes.php`, `class-wpcpm-sponsor-claims.php`, `class-wpcpm-sponsor-posts.php`, `class-wpcpm-sponsor-mentors.php`, `class-wpcpm-sponsor-agreement.php`, `class-wpcpm-sponsor-profile.php`, `class-wpcpm-sponsors-dashboard.php`):
  - an invented sponsor company (not a test sponsor, so the page draws as a real one), at **Champion**, its term running about five months more;
  - two invented members with addresses at an `.example` domain, and an invented Administrator as the company's program contact, with a booking link at an `.example` address (the invented names are chosen in the seed notes, outside the repository, since this plan is published);
  - an accepted Collaboration Agreement; a company profile with all three details and a simple invented wordmark logo (a PNG rendered from an inline SVG); a partner page description;
  - three offers: one live with plenty of codes, one live and running low, one returned by an Administrator with a short note; invented codes; about a dozen claims spread over the last six months, so the Usage chart has bars;
  - one published post and one waiting for review, with invented titles; one or two invented mentor accounts the company sponsors, each with an invented student count.
  The sign-in cookie for the first member comes from a cookie file outside the checkout, deleted after use and never printed.
- [ ] **Step 3: Capture.** As the first member, open `/sponsor/`. Capture: the top of the page (name, At a glance tiles, help and contact) as `sponsor-dashboard-top`; the sections with every card folded, showing each card's line, as `sponsor-dashboard-cards`; then each card open on its own: Sponsorship, Offers and codes (the offer list with its states and the top of the new offer form), Usage, Company profile and logo, and Events with its planner. Cards that list people who are not invented (for example "Mentors looking for a sponsor", which lists the copy's real mentors) stay folded or out of frame.
- [ ] **Step 4: Check every image.** Open each PNG and list every person's name, email, code, company and post title it shows; each must be one the seed made. Any other one: re-capture without it.
- [ ] **Step 5: Put the copy back.** `bin/restore before-guide-shots` (plugin 1.123.44 is installed, so restore directly), then delete the cookie file. Check `bin/wp post list --post_type=page --name=sponsor-guide --fields=ID` prints 1852.
- [ ] **Step 6: Commit** the PNGs alone: `git add docs/images/sponsor-*.png && git commit -m "Sponsor guide: screenshots of the Sponsor Dashboard, an invented sponsor on the Docker copy"`.

### Task 2: The sponsor sections rewritten in the Institution guide's shape

**Files:**
- Modify or replace: `docs/sections/40-sponsor-dashboard.md`, `41-sponsor-offers.md`, `42-sponsor-visibility.md`, `43-sponsor-posts.md` (use `git mv` where a file's content moves, so the history follows it).
- Modify: `bin/build-docs.php` (the `sponsors` entry: `intro` and `parts`), `bin/test-handbook.php` (the line pinning the sponsors parts, about line 1231), and any other suite that reads these files (`grep -rn "4[0-9]-sponsor" bin includes`).
- Regenerate: `docs/sponsors.md`, `docs/build/sponsors.html` (`php bin/build-docs.php`, no `--base`).

**Interfaces:**
- Consumes: Task 1's slugs, as `{{image:sponsor-dashboard-top|...}}` and so on. A caption is one plain sentence saying what the picture shows, without a name.
- Produces: the guide's outline below, which the release keeps.

The outline (the product owner asked to follow the Institution guide; the order is the order the Sponsor Dashboard draws things). Read the code before writing each part, and leave out a heading whose subject the code does not draw for sponsors:

1. `40-sponsor-dashboard.md`, **## Your Sponsor Dashboard**: two or three short opening paragraphs (where the page is, who sees it, what is on it, how fresh its figures are), then ### Your company's name, ### At a glance, ### Where to find help, ### Before your agreement is accepted (only if the dashboard closes for a company without an accepted agreement), ### Arranging the page, ### Opening and folding the cards, ### Events from our sponsors (only if the Sponsor Dashboard lists them).
2. `41-sponsor-sponsorship.md`, **## Your sponsorship**: ### Your tier and its term, ### Renewing and moving up, ### Events you co-host, ### Your yearly reports, ### Your badge.
3. `42-sponsor-offers.md`, **## Offers, codes and claims**: ### Making an offer, ### Codes, ### When an Administrator reviews an offer, ### When codes run low, ### Where students and mentors find your offer, ### Usage.
4. `43-sponsor-posts.md`, **## Posts, and how they are approved**: one ### per step a post goes through.
5. `44-sponsor-mentors.md`, **## Mentors**: ### Mentors you sponsor, ### Mentors looking for a sponsor.
6. `45-sponsor-company.md`, **## Your company, your people and your agreement**: ### Company profile and logo, ### Your description on the Partners page, ### People, ### Your Collaboration Agreement.
7. `46-sponsor-visibility.md`, **## What your company can and cannot see**.

Style, as the Institution guide writes:
- Second person, present tense. A paragraph carries one idea and stays under about 120 words.
- A list wherever the text names states, options, conditions by tier, or steps (numbered for steps). State bullets lead with the state in italics, as *Generated.* does in `50-institution-dashboard.md`.
- Buttons, section and card names in bold, as on screen; on-screen sentences in double quotes, exactly as the code prints them.
- A screenshot sits right under the heading of the thing it shows.
- The `sponsors` intro in `bin/build-docs.php` follows the Institution guide's: what the guide covers, then "It is written for the people who act for a sponsor. The program's Administrators have a guide of their own." The lede may stay.

- [ ] **Step 1: Inventory.** Write, in the task report, every fact and every condition the current four files state, one line each (numbered). This is the checklist Step 4 ticks.
- [ ] **Step 2: Anchors.** `grep -rn "sponsor-guide\|SPONSOR_GUIDE" includes blocks assets` and the handbook assistant's links: list any anchor into the Sponsor guide the code builds. Each must still be produced by a heading in the new outline (build-docs makes anchors from heading text).
- [ ] **Step 3: Rewrite** the sections to the outline and the style, placing Task 1's images; update the `sponsors` entry in `bin/build-docs.php` and the pinned parts in `bin/test-handbook.php`.
- [ ] **Step 4: Tick the inventory.** Each numbered fact: where it now lives, or the code line that shows it was wrong. Each quoted string: the code line that prints it.
- [ ] **Step 5: Build and check.** `php bin/build-docs.php`; `git diff --stat` shows only the sponsor files among `docs/*.md` and `docs/build/`; `grep -c "{{image" docs/build/sponsors.html` is 0; every `<img` in it points at `images/sponsor-*.png` and each file exists. Run `php bin/check-spelling.php`, `php bin/check-references.php`, `php bin/test-handbook.php`, `php bin/test-fixtures.php`, then every `bin/test-*.php` (each must exit 0) and `bash bin/check-standards.sh` if it touches the changed files. `grep -nP "[\x{2013}\x{2014}]" docs/sections/4*.md` prints nothing.
- [ ] **Step 6: Commit** the sections, the build, the two bin files and the regenerated guide: `git commit -m "Sponsor guide: rewritten in the Institution guide's shape, short parts in the dashboard's order, with its screenshots"`.

### After the tasks (the controller, not a task)

- Preview on the Docker copy: build with `--base=http://localhost:8900/wp-content/uploads/2026/08` into a copy outside the repo (then the plain build again, so git stays clean), copy the PNGs into the copy's `wp-content/uploads/2026/08/`, update page 1852 with it, and show the product owner the page at desktop and phone widths.
- On the product owner's approval of the draft: version 1.123.45 in every place and the changelog; the PNGs added to the live media library under `uploads/2026/08` and their attachment IDs in `$ids` in `bin/build-docs.php`; then the release script's check, build, scan, mirror and deploy, each live step on the product owner's yes.
