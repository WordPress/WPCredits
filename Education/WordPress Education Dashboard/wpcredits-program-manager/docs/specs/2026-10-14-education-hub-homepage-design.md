# The Education Hub homepage: design

Design note, 14 October 2026; amended the same day with the owner's answers to its first questions (section 2, decisions 6 to 9) and the defaults taken for the rest (decisions 10 to 13). Status: for the owner's review; nothing is built. Ships as two releases: **plugin 1.122.24** (the live outcome figures) first, then **theme 1.24.15** (the page template, its patterns, two blocks and the figure block's extension). Content source: the designer's draft "WordPress Education Hub v1" (`WordPress-Education-Hub-v1.html`), in the version the owner handed over on 14 October 2026 at 19:28, read whole.

That version differs from the earlier one of the same day, on the homepage, in four places, all followed here: WordPress Student Clubs is gone (six programs, not seven); Campus Connect's "You get" is only "Certificate of participation"; the Attendee step no longer unlocks a student club invite; and a field-of-study table, moved from the designer's Hire view and reworded to the student, sits under the program index (section 6.6). The designer's hero, doors, filters, pathway texts, partner levels and closing band are unchanged.

## 1. Purpose

Give wordpresseducation.org an alternative homepage built from the designer's hub: one page that tells students, institutions and companies where they fit, lists every education program, shows the pathway from a first workshop to a first job (and an institution's partner levels), and opens with three outcome figures the site computes itself instead of numbers typed into a draft. It is built inside the WPCredits theme, in the site's own WordPress.org look, and shown on a private preview page; the live homepage does not change until the owner switches it.

### Success criteria

1. A private page using the "Education Hub home" template shows every section of section 6 with the content and links listed there, and the live homepage (page 11, the Site Editor's saved front-page template, post 544) renders byte for byte as before.
2. With JavaScript off, the page shows all six programs, the field-of-study table, both pathways with all eleven step and level details, and no control that does nothing.
3. With JavaScript on, the filter chips and the pathway switch and steps work by mouse, touch and keyboard, announce their state, and keep focus where it was.
4. At 375px wide the document never scrolls sideways; the one scroll box is the field-of-study table's, and it is reachable by keyboard.
5. The three hero figures match the public WordPress Credits impact dashboard's definitions exactly; the site shows each with its date, sample size and source, or shows nothing. It never shows a typed number, a zero, or a figure from an incomplete or suspicious read.
6. Every string is translatable in the `wpcredits-theme` (theme) or `wpcredits-program-manager` (plugin) text domain; every text a core block holds is editable in the Site Editor.
7. Nothing on the page, in the theme or in this repository says anything about what sponsorship costs or what a sponsor receives.
8. Every benefit the page states that no public page confirms carries "(planned)" (section 6.11), and the figures are the dashboard's Monday figures, read the same morning.

## 2. The owner's decisions (14 October 2026)

1. **Placement:** in the WPCredits theme, as its own selectable page template, "Education Hub home" (`templates/page-education-hub.html`, registered in `theme.json` `customTemplates`), plus patterns, shown on a private preview page. The live homepage stays as it is until the owner switches it.
2. **Scope:** the designer's whole hub on the homepage: the hero, the three audience doors, the filterable program index, the pathway with the seven student steps and the four institution partner levels (both public: "show both"), and the closing band. The designer's Hire, Partner and Sponsor views are out (separate pages later); the homepage links to existing destinations instead. The placeholder callout in the designer's Sponsor view appears nowhere.
3. **Look:** the site's WordPress.org brand through the theme's `theme.json` tokens, type scale and existing component styles, not the draft's EB Garamond, Inter and IBM Plex Mono or its palette.
4. **Interactive parts:** patterns of core blocks for the static sections, plus two small server-rendered theme blocks (a program index with filter chips; a pathway with the student and institution switch, the rising steps and the step detail) whose markup shows everything without JavaScript, and a small view script for filtering and selecting.
5. **Figures live from the site's own data,** with the public dashboard's definitions, stored with their date and sample sizes, shown with "as of" and the source, falling back to the last good figure or nothing.

The owner's answers to this note's questions (14 October 2026):

6. **Graduation label:** "of WordPress Credits students graduate rather than drop out".
7. **Refresh weekly,** on Mondays right after the public dashboard's weekly build, so the homepage and the dashboard show the same numbers (section 8.3); the hide-after limit and the retries fit a weekly read.
8. **Companies door:** the designer's wording stays, "Browse graduates open to work".
9. **Benefits:** every benefit the student steps, the partner levels and the program cards state as a fact that no public page confirms today reads "planned" (section 6.11 lists each one checked, where, and its state).

Defaults taken for the remaining questions, which the owner can change at review:

10. **Hide-after limit:** the figures stop showing after four Mondays in a row without a good read (section 8.5).
11. **The Sponsor button** goes to `/sponsor-application/`, the site's only public sponsor page.
12. **No mentor line,** as the designer's draft has none. The current homepage has a "Become a mentor" button, so the owner can ask for one.
13. **The preview page is Private;** the designer and the program team see the page through screenshots taken by the coordinating session at desktop width and at 375px.

## 3. Out of scope

- The designer's Hire, Partner and Sponsor views and everything in them, their top navigation and brand bar, the "v1 draft" badge, their footer, their dark color scheme (the theme has none) and the hash routing between their views. The site's own header and the WordPress.org global header and footer stay.
- Any content about sponsorship levels, amounts or benefits.
- Switching the live homepage. Section 6.4 describes the switch; doing it is the owner's act, later.
- Editing the program, step and level texts in the editor: they live in the theme's code as translatable strings (section 7.4). The texts held by core blocks are editable.
- WordPress Student Clubs: the designer removed it in the 14 October version, so it is not on the page.
- The WPCredits-Tracker plugin and the public dashboard: unchanged. The post-graduation activity measure (WordPress/WPCredits#137) is not part of this.
- Per-institution, per-track or trend figures, and any chart.

## 4. What the code and the site say today

Read on 14 October 2026.

- **The theme** (1.24.14 on disk and live) is a block theme with no build step. `templates/front-page.html` is four pattern references (`wpcredits/hero`, `audiences`, `steps`, `cta`) between the WordPress.org global header, the site header part and the global footer. `theme.json` holds the palette (brand #3858e9, brand-dark #213fd4, brand-tint #eff2ff, brand-border #c7d1ff, ink #1e1e1e, ink-70 #40464d, ink-60 #656a71, line #e6e6e6, surface-band #f4f4f4, surface-soft #fcfcfc, white), the type scale (12, 13, 16, 20, 26, 34, 44px; h1 34px, h2 26px, h3 20px), `wideSize` 1240px, radii and status colors in `settings.custom`; `style.css` aliases them as `--wpc-*`. `assets/css/base.css` holds one rule (template parts with `has-display-contents`). The existing components this page reuses: `.wpc-pill`, `.wpc-eyebrow` / `is-style-eyebrow`, `is-style-card`, `.wpc-hero__title` (its `em` in brand blue), the core Button with `wpc-button--large` and `is-style-outline`, the one focus ring (2px brand, 2px offset), the active-filter treatment of `.wpc-triage__item.is-active` (brand tint with an inset 1px brand ring), `--wpc-landing-offset` for anchored targets under the sticky header, and the global `prefers-reduced-motion: reduce` rule.
- **`wpcredits/program-stat`** (`blocks/program-stat`) prints one `<p>` with a live count of accounts holding a program role through `wpcredits_program_count()` (a one-hour transient), and falls back to the number typed into its `fallback` attribute when the count is zero or the plugin is off. It has no editor script.
- **`bin/check-selectors.php`** checks that every `.wpcpm-*` class the theme dresses is printed somewhere, that every `--wpc-*` token read is declared (theme root and `assets/css/*.css`), that shared table selectors carry a dashboard body class, and that no theme file holds an em or en dash.
- **The live site:** `show_on_front` is `page`, `page_on_front` 11 ("Homepage", no page template), and the front page is drawn by the saved `front-page` template, post 544 (modified 28 August 2026). `DISABLE_WP_CRON` is true, and the host's runner fires WordPress's scheduled events (every `wpcpm_*` event was due in the future, none overdue). The institution application is page 751, `/apply/`, read by `WPCPM_Institution_Application::page_url()`; the sponsorship application is page 925, `/sponsor-application/`, read by `WPCPM_Sponsor_Application::page_url()`; no other public sponsors page exists (`/sponsors/` answers 404). The site's date format is `F j, Y`, its time zone Europe/Warsaw, its language en_US.
- **Airtable:** the plugin's base, `appIzQKfwTn5dyPVp`, is its code default and the live setting (compared on the live site without printing the setting). The Feedback table `tblx3TH6fp4edQJDm` is the plugin's `feedback_table` in that same base: the plugin already reads and writes it (`WPCPM_Student_Feedback`, the students sync's course lookup, the semester report), so the site's token reaches it. The Students Reports table `tbljYkkVGbeoaWEtY` is the plugin's `reports_table`, read every three hours by the mentors and students syncs. The public dashboard reads the same base and the same two tables.
- **Where the public figures come from:** the public impact dashboard at wordpress.github.io/WPCredits is built by WordPress/WPCredits' own `scripts/build_dashboard.py` and `scripts/template.html`, run by the workflow "Update Dashboard Data" (`.github/workflows/update-dashboard.yml` on `trunk`, `cron: '0 6 * * 1'`, every Monday at 06:00 UTC, plus manual runs). Read with `gh` on 14 October 2026: the seven scheduled runs from 31 August to 12 October started between 06:04 and 06:09 UTC and finished between 06:09 and 06:19 UTC; the bot's "Update dashboard data" commits landed at 06:18 UTC on 5 and 12 October. Manual runs rebuilt it on other days too (7, 16, 17 and 29 September). WPCredits-Tracker (`includes/class-wpct-sync.php` and `assets/js/dashboard.js`) is that build's PHP port. For the three figures here the two agree line for line, apart from rounding at an exact half (section 8.1). The dashboard built on 12 October 2026 shows 361 graduates and 52 dropouts (87%), 88% of 315 answers would recommend, and 79% of 310 plan to keep contributing. The designer's draft has older figures, 90, 85 and 76.
- **The local copies** refuse every request to Airtable by design, so the figures can only be computed there from fixtures (section 10).

## 5. Architecture

### 5.1 The plugin: outcome figures

A new class, `WPCPM_Outcome_Figures` (`includes/class-wpcpm-outcome-figures.php`), loaded with the plugin's other services:

- **`refresh( array $args )`** reads the two tables through `WPCPM_Airtable`, computes the three figures with `compute()`, applies the guards (section 8.5) and stores the set. `$args`: `dry_run` (compute and report, store nothing) and `accept` (store a held set after a person has checked it).
- **`compute( array $reports, array $feedback )`** is a pure function from Airtable rows to the three figures and their counts. It holds the definitions and nothing else, so it is tested on fixtures, and when the program's records move onto the site only the reader changes.
- **`published()`** returns what may be shown: the stored set, minus any figure under the minimum sample, and nothing at all once the set is older than the maximum age.
- **`status()`** returns the last attempt for the syncs card: when, and whether it was stored, held or failed, with the reason.
- One additive change to `WPCPM_Airtable::fetch_page()`: an optional `by_id` argument that sends `returnFieldsByFieldId=true`, so the figures read the same field IDs the public dashboard reads, whatever the columns are called.
- One row, "Outcome figures", in the syncs data `WPCPM_Administrators_Cards::health()` returns, so the Overview and the Administrator Dashboard both show it (section 9).
- One WP-CLI command, `wp wpcredits outcome_figures`, with `--refresh`, `--dry-run` and `--accept`.

### 5.2 The theme: template, patterns, blocks

- `templates/page-education-hub.html`, registered in `theme.json` `customTemplates` as "Education Hub home" for pages. It holds the same chrome as `front-page.html` (global header, site header part, `main.wpc-main`, global footer) and five pattern references: `wpcredits/hub-hero`, `hub-doors`, `hub-programs`, `hub-pathway`, `hub-cta`. It prints no post content: the page it is assigned to is only its address.
- Five patterns in `patterns/`, category `wpcredits`, made of core blocks, with the two new blocks inside `hub-programs` and `hub-pathway` and the extended figure block inside `hub-hero`.
- Two blocks, `wpcredits/program-index` and `wpcredits/pathway` (`blocks/program-index`, `blocks/pathway`): server-rendered from `render.php`, one `view.js` each with a hand-kept `view.asset.php` (no build step), `supports.multiple` false so their element IDs stay unique.
- `inc/hub-content.php`: the six programs, the seven steps and the four levels as arrays of translated strings and link targets, shared by the two blocks.
- `inc/hub.php`: registers the `wpcredits-hub` stylesheet (`assets/css/hub.css`), enqueues it on a request drawn by the hub template, adds it to the editor styles, and holds the front-page rule of section 6.4 and the table's scroll-box filter of section 6.6. Both blocks name `wpcredits-hub` as their block style, so the sheet also loads wherever a block is placed on its own.
- `wpcredits/program-stat` learns the three outcome figures and a source line (section 8.6), reading `WPCPM_Outcome_Figures::published()` through `wpcredits_outcome_figures()` in `inc/template-tags.php`, guarded on the class and the method, so a theme on a plugin without them shows no figure.

### 5.3 Release order

1. **Plugin 1.122.24:** the class, the `by_id` argument, the syncs row, the CLI command, the weekly event, its tests, the readme changelog, the Administrators' guide's syncs paragraph (one sentence; guide page republished), the translation template. After the deploy, on the owner's yes: one `--dry-run` on the live site for the parity check (section 10.2), then one `--refresh`, so the figures exist before the theme reads them. Nothing public changes in this release.
2. **Theme 1.24.15:** the template, `inc/hub.php`, `inc/hub-content.php`, the five patterns, the two blocks, `hub.css`, the figure block's extension, the selector check's addition, the translation template, the version in every spot and the changelog.
3. **The preview page**, on the owner's yes after the theme deploy: an Administrator creates a page titled "Education Hub (preview)", status **Private**, template "Education Hub home". Private, not password-protected: the template prints no post content, so a password would guard nothing, while a private page is refused to everyone without the right to read private pages before any template runs. Administrators and Editors can open it; the designer and the program team see it through screenshots the coordinating session takes at desktop width and at 375px (decision 13).
4. Each release goes through the release script (check, build, scan, mirror, deploy), its mirror push included, on the owner's yes for that release; the live version is read first.

A theme rollback removes the template, and the private page falls back to the plain page template: harmless. A plugin rollback leaves the theme with no figures to read, and the hero shows none.

## 6. The page

### 6.1 Width, spacing and type

Every section is `alignwide` (1240px inside the theme's 40px root padding) and no text carries a character-width cap: a lede runs to the same right edge as the cards beside it. The root padding stays 40px on a phone, which at 375px would leave 295px; below 640px the hub's own sections take the header's phone gutter instead (`--wpc-chrome-gutter`, 20px), so their edge lines up with the brand above them and a card gets 335px. Sections are padded 48px top and bottom (the hero 56px and 48px, as `wpcredits/hero`) and meet with no block gap between them, so they sit 96px apart (the owner chose this over the live homepage's 116px, 15 Oct 2026), section heads sit 22px above their content, cards are 16px apart. Body text is 16px, card text and controls 14px, eyebrows 12px; nothing is smaller than 12px. Anchored sections carry `scroll-margin-top: var( --wpc-landing-offset )`, so a jump lands below the sticky header. Before shipping, every gap between consecutive blocks is measured at desktop width and at 375px.

### 6.2 Hero (`wpcredits/hub-hero`)

Two columns, 58% and 42% (the designer's 1.25 to 0.9), stacking below 900px.

- Eyebrow (`wpc-pill`): "Education programs from the WordPress project"
- Heading 1 (`wpc-hero__title`, display size, 34px below 900px): "Start learning with WordPress." then, in `<em>` (brand blue, not italic), "Every step counts. Keep climbing." Two strings.
- Lede (16px, ink-70, no width cap): "Students earn academic credit, credentials, and a portfolio employers can check, and each program leads to the next. Institutions can start with one workshop or a small pilot, and every group of students they run moves them up a partner level with more support."
- Buttons: "Find where to start" (primary, `wpc-button--large`) to `#programs`; "Explore the pathway" (outline) to `#pathway`.
- Figures column: a 1px ink rule on top, then three figure rows separated by `--wpc-line-card`, each a `wpcredits/program-stat` block (section 8.6): the number (44px, 700, brand, tabular figures; 34px below 900px), its label (16px, ink-70) and its base (14px, ink-60).
  - Graduation share. Label: "of WordPress Credits students graduate rather than drop out". Base: "361 of 413 students who graduated or dropped out" (figures from the stored set).
  - Would recommend. Label: "of graduates would recommend the program". Base: "315 answers to the final survey".
  - Keep contributing. Label: "of graduates plan to keep contributing to WordPress". Base: "310 answers to the final survey".
  - Source line (14px, ink-60): "As of October 12, 2026, counted by this site from the program's records with the same definitions as the WordPress Credits impact dashboard." with "WordPress Credits impact dashboard" linking to https://wordpress.github.io/WPCredits/.
- When no figure may be shown, the figures column is not displayed (`:has()` on the columns) and the copy column takes the whole width.

### 6.3 Audience doors (`wpcredits/hub-doors`)

A screen-reader-only Heading 2, "Who this is for", then three `is-style-card` groups in a Columns block (one column below 900px). Each card: eyebrow paragraph, Heading 3, paragraph (16px, ink-60), link line (14px, 600, brand) whose link is stretched over the card, with the card's border turning brand blue on hover and the focus ring drawn around the whole card.

| Eyebrow | Heading | Text | Link |
| --- | --- | --- | --- |
| Students | "Build a site, make your first contribution, earn credit." | "Programs matched to your field of study, from translation and design to engineering." | "Browse programs →" to `#programs` |
| Institutions | "Bring a free, ready-to-run program to your students." | "Start with a one-day workshop or a WordPress Credits pilot. Each new group of students builds a stronger partnership." | "Become a partner →" to the institution application |
| Companies | "Hire people with verified WordPress experience." | "Browse graduates open to work, or post internships and entry-level roles." | "Hire from WordPress →" to https://jobs.wordpress.net/ |

### 6.4 The front-page rule and the switch

WordPress draws the front page with the `front-page` template before it looks at the page's own template, so assigning "Education Hub home" to the front page would change nothing. `inc/hub.php` therefore filters `frontpage_template_hierarchy`: when `show_on_front` is `page` and the page set as the homepage uses `page-education-hub`, `page-education-hub.php` goes first. Today the homepage (page 11) uses no template, so the filter returns the hierarchy untouched and post 544 keeps drawing the homepage.

The switch, when the owner decides: publish the preview page (with the title the browser tab and search results should show), choose it under Settings > Reading as the homepage, and purge the edge cache. Switching back is choosing "Homepage" again and setting the hub's page back to Private, so it is not left public at its own address (while it is the homepage, core sends its own address to the home address; after the switch back it would not). Post 544 and page 11 are never edited in either direction.

### 6.5 Programs (`wpcredits/hub-programs`)

A group with the anchor `programs`. Core blocks: eyebrow "All programs"; Heading 2 "Every education program, in one list"; lede "Programs are free unless noted. You don't need to take them in order, but each one is designed to lead into the next." Then the `wpcredits/program-index` block (section 7.2), then the field-of-study part (section 6.6).

The six programs, in the designer's order:

| Program | Audiences (filters) | Status | Step line | What it is | Time | You get | Links |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Campus Connect | students | Live | "Step 1 · Attendee" | "A half or full-day hands-on WordPress workshop on campus. No experience needed, and anyone connected to the institution can organize one." | "Half or full day" | "Certificate of participation" | "Find or host an event" https://events.wordpress.org/campusconnect/ |
| WordPress Credits | students, career | Live | "Steps 3 and 4 · Contributor, Graduate" | "Mentored work on the WordPress open source project, matched to your field of study. Ends with a presentation and a Foundation certificate." | "50h or 150h+, about a semester" | "Certificate"; "Badge"; "Hours transcript" (planned); "Credit" | "Developer track" https://learn.wordpress.org/course/wordpress-credits-developer-track/; "Designer track" https://learn.wordpress.org/course/wordpress-credits-designer-track/; "Impact dashboard" https://wordpress.github.io/WPCredits/ |
| Facilitator Training | faculty | Live | "Step 6 · Community Leader" | "Free, self-guided courses plus step-by-step facilitation guides with timed agendas and activity sheets, so faculty can teach WordPress themselves." | "Self-paced" | "Ready-to-run sessions"; "Facilitator listing" (planned) | "Facilitation guides" https://make.wordpress.org/community/handbook/education/wordpress-facilitator-training-program/; "Courses" https://learn.wordpress.org/ |
| Learn WordPress | students, faculty | Live | "Any step" | "Free courses, lessons, and online workshops for every skill level, from building a first site to developing blocks." | "Self-paced" | "Course completions on your profile" | "Browse courses" https://learn.wordpress.org/ |
| AI Leaders | career | Live | "Step 7 · Credentialed" | "The first Foundation-backed micro-credential. Piloted in 2026 with students from Illinois and Louisiana." | "Cohort-based" | "Micro-credential"; "$1,000 for completers" (planned) | "Learn more" https://ai-leaders.org/ |
| WordPress Builder Certification | career | In development | "Step 7 · Credentialed" | "A certification exam validating the skills to build and run a personal or company website on WordPress." | "Exam" | "Industry certification" (the card's "In development" tag already says it is not offered yet) | "Learn more" https://wordpresscertifications.com/wordpress-builder-certification/ |

Chips: "All", "For students", "For faculty", "Career and credentials". Counts: all 6, students 3, faculty 2, career 3.

The designer wrote each "You get" as one phrase; the card lists its items one to a line, as the step details do, so a "(planned)" mark (section 6.11) belongs to one item and never to the line.

### 6.6 Field of study, inside Programs

New in the 14 October draft, moved from the designer's Hire view and written to the student. Core blocks after the index, 56px below it: eyebrow "Matched to your field of study"; Heading 3 (anchor `wpc-hub-fit`, the theme's 20px) "Find where you fit in WordPress Credits"; lede "You work in the area of WordPress that fits your degree, so a design student shows design work and a CS student shows reviewed code."; a core Table block (class `wpc-hub-fields`, header section on, no fixed layout):

| Field of study | Where you'll work | What you'll have to show |
| --- | --- | --- |
| Computer science, engineering | Core, plugins, testing, accessibility | Merged code and reviewed patches |
| Design, arts, UX | Design, block patterns, themes | Visual work in use on real sites |
| Communications, journalism, marketing | Documentation, Learn WordPress, Marketing | Published work with a byline |
| Translation, languages | Polyglots | Published translations |
| Business, events, tourism | Community, WordCamps | An event you ran, with attendance numbers |
| Education | Learn WordPress lesson plans, Training | Course material in public use |
| Accessibility, social work | Accessibility, testing | Audit work on a global platform |

The table fills the column in a white box (1px `--wpc-line-card`, 8px radius); header cells are eyebrows (12px, 500, uppercase, ink-60), body cells 14px ink with 14px 18px padding (10px 12px below 640px), the first column 600, rows divided by `--wpc-line`. The chips do not filter it.

**At 375px it is a scroll box.** Core's Table block already makes its `<figure>` the scroll container (`overflow-x: auto`); below 640px `hub.css` gives the table `min-width: 520px`, so each column keeps about 170px, the 335px box shows the first column and most of the second (the cut-off column says there is more), and the page itself never widens. A scroll container without a focusable child cannot be reached by keyboard in every browser, so a `render_block_core/table` filter in `inc/hub.php` adds, to a table block carrying `wpc-hub-fields` only, `tabindex="0"`, `role="region"` and `aria-labelledby="wpc-hub-fit"` (the heading names the region, so no new string). The focus ring is the theme's. Above 640px nothing scrolls. This is server-side, so it holds without JavaScript and survives the block's text being edited.

### 6.7 Pathway (`wpcredits/hub-pathway`)

A group with the anchor `pathway`: eyebrow paragraph "The pathway", then the `wpcredits/pathway` block (section 7.3). The block holds the switch ("For students", "For institutions"; group label "Choose a pathway") and both ladders.

**Students.** Heading 2: "Seven steps from first workshop to first job". Lede: "Each step earns a badge on your WordPress.org profile, the same profile employers look at. Enter at whichever step fits." and, printed only for the script to show, "Select a step to see what it leads to." Columns of the detail: "Earned by", "Unlocks", "Academic recognition". Opens on step 3.

| Step | Earned by | Unlocks | Academic recognition | Next |
| --- | --- | --- | --- | --- |
| 1 Attendee | "Attend a Campus Connect event." | "Certificate of participation"; "A seeded WordPress.org profile" (planned); "A profile badge" | "Recognized co-curricular activity or event credit, where the school offers it." | "Find a Campus Connect event" https://events.wordpress.org/campusconnect/ |
| 2 Builder | "Launch a live site with real content." | "Free .blog or .art domain and a year of hosting" (planned); "The full theme and plugin library"; "Showcase eligibility" (planned); "A profile badge" (planned) | "A portfolio piece that can be assessed within an existing course." | "Learn to build a site" https://learn.wordpress.org/ |
| 3 Contributor | "Get your first contribution accepted." | "Contribution badge"; "Make team channels"; "Mentor matching"; "Retention support starts here" (planned) | "Open source work logged toward course or program requirements." | "Start WordPress Credits" `#program-wordpress-credits` |
| 4 Graduate | "Complete a WordPress Credits track." | "Foundation certificate"; "Digital badge"; "Verified hours transcript" (planned); "Alumni directory listing" (planned); "WordCamp ticket support" (planned) | "Credit-bearing." | "See the tracks" `#program-wordpress-credits` |
| 5 Specialist | "Complete a second or third program." | "Deeper specialization" (planned); "Scoped project work" (planned); "Priority mentor matching" (planned); "A specialist badge" (planned) | "Credit-bearing as an advanced elective, independent study, practicum, or capstone." | "Explore programs" `#programs` |
| 6 Community Leader | "Facilitate, mentor, organize, or speak." | "Listing in the facilitator and speaker directory" (planned); "Discounted or free WordCamp access" (planned); "Priority on paid teaching leads" (planned); "Organizing support"; "A profile badge" | "A role the school already records: teaching assistant, peer mentor, or student organization leader." | "Try Facilitator Training" `#program-facilitator-training` |
| 7 Credentialed | "Earn a micro-credential." | "An Open to Work listing on jobs.wordpress.net"; "Credentials shown on your profile" (planned); "Showcase eligibility" (planned) | "A stackable credential accepted toward a degree, certificate, or transcript notation." | "Where this leads: WordPress Jobs" https://jobs.wordpress.net/ |

**Institutions.** Heading 2: "Four partner levels, measured on student outcomes". Lede: "Levels grow with the number of students you put through the programs and get certified, not the number of groups you run. Partners appear in an activity directory sorted by recent activity. We don't rank schools." Columns: "Reached by", "Gets". Opens on level 2.

| Level | Reached by | Gets | Next |
| --- | --- | --- | --- |
| 1 Participating | "Host one Campus Connect event." | "A listing on the WordPress education page" (planned); "A logo kit" (planned) | "Request a Campus Connect event" https://events.wordpress.org/campusconnect/organize/ |
| 2 Partner | "Sign a partnership agreement and complete one WordPress Credits group." | "A partner badge for your site"; "An Institution Dashboard"; "An auto-generated impact report each semester" | "Partner as an institution", the institution application |
| 3 Established | "Run recurring groups with growing student numbers, at least one trained faculty facilitator, and students sitting certification exams." | "Named regional support" (planned); "A WordCamp speaking slot" (planned); "Co-marketing" (planned) | "Try Facilitator Training" `#program-facilitator-training` |
| 4 Flagship | "Integrate WordPress into the curriculum, with faculty mentoring across institutions and sustained numbers of certified students." | "A case study" (planned); "A Foundation announcement" (planned); "Input on the program's direction" (planned); "Event hosting" (planned) | "Talk to the program team", the institution application |

Each detail carries "Step 3 of 7" (or "Level 2 of 4") and a line naming what follows, "Leads to step 4: Graduate" (or "Leads to level 3: Established"); the last says "Leads to work" for students and "The top level" for institutions.

The designer wrote "Unlocks" and "Gets" as one sentence each; the detail prints them as a list, one item to a line, in the designer's words with the first letter capitalized, so a "(planned)" mark sits on the one item it belongs to (section 6.11). "Earned by", "Reached by" and "Academic recognition" stay sentences: they say what a step asks or what a school may grant, not what the program gives, so they carry no mark.

### 6.8 Closing band (`wpcredits/hub-cta`)

Two `is-style-card` groups side by side, stacked below 900px, padded 28px. Each title is a Heading 2 at 20px.

| Eyebrow | Title | Text | Buttons |
| --- | --- | --- | --- |
| Companies | "Looking for WordPress talent?" | "See what graduates are trained to do and where to find them." | "Hire from WordPress" (primary) https://jobs.wordpress.net/ |
| Sponsors and schools | "Help more students reach the next step" | "Fund the programs, or bring them to your campus." | "Sponsor" (outline), the sponsorship application; "Partner as an institution" (outline), the institution application |

### 6.9 Every link

| Where | Label | Points to |
| --- | --- | --- |
| Hero | Find where to start | `#programs` |
| Hero | Explore the pathway | `#pathway` |
| Hero source | WordPress Credits impact dashboard | https://wordpress.github.io/WPCredits/ |
| Door, students | Browse programs → | `#programs` |
| Door, institutions | Become a partner → | the institution application, `WPCPM_Institution_Application::page_url()` (today https://wordpresseducation.org/apply/) |
| Door, companies | Hire from WordPress → | https://jobs.wordpress.net/ |
| Program cards | the nine links of section 6.5 | as listed there (each answered 200 on 14 October 2026) |
| Student steps 1 to 7 | the seven "next" links of section 6.7 | as listed there |
| Institution levels 1 to 4 | the four "next" links of section 6.7 | as listed there |
| Band, companies | Hire from WordPress | https://jobs.wordpress.net/ |
| Band, sponsors | Sponsor | the sponsorship application, `WPCPM_Sponsor_Application::page_url()` (today https://wordpresseducation.org/sponsor-application/) |
| Band, schools | Partner as an institution | the institution application |

The site's own addresses are read when a pattern or block renders. If a page is not published (or the plugin is off), its links point to the WordPress Credits handbook, https://make.wordpress.org/community/handbook/education/credits/, which the live homepage already links. Once someone edits the template in the Site Editor, the pattern's links are saved as they were at that moment. Every link opens in the same tab; the arrows are decorative (`aria-hidden`).

### 6.10 Wording changed from the draft, and why

- "WP Credits" becomes "WordPress Credits" (the door's pilot, the Partner level's group, the source line) and "institution dashboard" becomes "Institution Dashboard": full product names.
- The graduation label "of students who start WordPress Credits graduate" becomes "of WordPress Credits students graduate rather than drop out", because the figure counts graduates among students who graduated or dropped out, never among everyone who started (529 of the 942 who have joined are still in the program, by the dashboard of 12 October 2026). The owner chose this label (decision 6).
- The designer's source line "refreshed weekly" becomes the date of the figures: the site reads them weekly too, on the dashboard's Monday (decision 7), and the date says which Monday.
- The "Unlocks", "Gets" and "You get" sentences become lists of their items, and the items no public page confirms carry "(planned)" (decision 9, section 6.11).
- Step 7's link "Where this leads: Hire from WordPress" pointed at the designer's Hire view, which is out; it now says "Where this leads: WordPress Jobs" and goes to jobs.wordpress.net, where step 7's Open to Work listing lives.
- The ladder switch's group label "Choose a ladder" becomes "Choose a pathway", the section's own name.

### 6.11 Benefits marked planned

**How it shows.** A planned item is followed by "(planned)" in its own span (`wpc-hub-planned`: 14px, ink-60, 5.45:1 on white), one translatable word in parentheses (`_x( '(planned)', 'a benefit not offered yet', 'wpcredits-theme' )`), kept on the item's line with a non-breaking space so it never wraps alone. In the step detail it ends the item's line in the "Unlocks" or "Gets" list; on a card it ends the item's line under "You get". Under each ladder's details one line, ink-60, says what it means: "Items marked planned are not offered yet." The marks are in the server markup, so they show with and without JavaScript, and the item and its mark are read together. The stairs show no benefits, so they carry no mark. A mark comes off with one boolean in `inc/hub-content.php` (`planned`), in a theme release, the day a public page confirms the item.

**The rule.** An item is confirmed when a public page says the program gives it, or that it is open to everyone, as the plugin directory is. A detail the page does not confirm, and which changes what a reader would expect to receive (which domain names, which step it comes at), makes the item planned, because the homepage would state the detail as a fact. A detail of how something is made (that a report is drafted automatically) does not.

**What was checked, 14 October 2026:**

| Where on the page | Item | Checked at | State |
| --- | --- | --- | --- |
| Step 1, Attendee | Certificate of participation | Campus Connect handbook, "Issuing a certificate" (organizers issue it from the program's template, signed by the Executive Director on request) | confirmed |
| Step 1, Attendee | A seeded WordPress.org profile | not found on the Campus Connect pages or events.wordpress.org/campusconnect | planned |
| Step 1, Attendee | A profile badge | Meta handbook, "Profile Badges": WordPress Campus Connect, Participant | confirmed |
| Step 2, Builder | Free .blog or .art domain and a year of hosting | the WordPress Credits handbook page's "Sponsored Products" lists "Domain + 1 year of free hosting" from three hosting sponsors for program students, but names no .blog or .art and does not tie it to launching a site | planned |
| Step 2, Builder | The full theme and plugin library | wordpress.org/themes and wordpress.org/plugins, free to everyone | confirmed |
| Step 2, Builder | Showcase eligibility | wordpress.org/showcase shows no submission route; no program showcase page found | planned |
| Step 2, Builder | A profile badge | "Profile Badges" lists no builder badge | planned |
| Step 3, Contributor | Contribution badge | "Profile Badges": each team's Contributor badge | confirmed |
| Step 3, Contributor | Make team channels | make.wordpress.org/chat, open to everyone; the WordPress Credits page asks students to join | confirmed |
| Step 3, Contributor | Mentor matching | WordPress Credits page: "Each student is paired with a mentor" | confirmed |
| Step 3, Contributor | Retention support starts here | not found | planned |
| Step 4, Graduate | Foundation certificate | WordPress Credits page: a program "by the WordPress Foundation" whose students "receive a certificate" | confirmed |
| Step 4, Graduate | Digital badge | "Profile Badges": WP Credits, Graduate | confirmed |
| Step 4, Graduate | Verified hours transcript | the WordPress Credits page says students track their time; no transcript is described | planned |
| Step 4, Graduate | Alumni directory listing | not found | planned |
| Step 4, Graduate | WordCamp ticket support | not found on any WordPress Credits page | planned |
| Step 5, Specialist | Deeper specialization; Scoped project work; Priority mentor matching; A specialist badge | no public page describes a second or third program as a step; "Profile Badges" lists no specialist badge | all four planned |
| Step 6, Community Leader | Listing in the facilitator and speaker directory | not found on the Facilitator Training pages | planned |
| Step 6, Community Leader | Discounted or free WordCamp access | not found | planned |
| Step 6, Community Leader | Priority on paid teaching leads | Facilitator Training handbook: the program "does not place facilitators in paid roles directly" | planned |
| Step 6, Community Leader | Organizing support | Campus Connect Office Hours and the organizer playbook | confirmed |
| Step 6, Community Leader | A profile badge | "Profile Badges": Campus Connect Organizer, WP Credits Mentor, WordCamp Organizer and Speaker | confirmed |
| Step 7, Credentialed | An Open to Work listing on jobs.wordpress.net | jobs.wordpress.net FAQ: mark yourself open to work on your WordPress.org profile; its "People Open to Work" list shows it | confirmed |
| Step 7, Credentialed | Credentials shown on your profile | profiles show badges and completed courses; no page says a micro-credential is shown | planned |
| Step 7, Credentialed | Showcase eligibility | as at step 2 | planned |
| Level 1, Participating | A listing on the WordPress education page | wordpress.org/education lists the initiatives and news, not institutions | planned |
| Level 1, Participating | A logo kit | the WordPress Credits Logos page is for collaborating WordPress Credits institutions and sponsors, not Campus Connect hosts | planned |
| Level 2, Partner | A partner badge for your site | WordPress Credits Logos page: logos for a collaborating institution's page about the partnership | confirmed |
| Level 2, Partner | An Institution Dashboard | WordPress Credits for institutions: "Institutions will receive a private dashboard" | confirmed |
| Level 2, Partner | An auto-generated impact report each semester | WordPress Credits for institutions: "a final report detailing the contributions made by your cohort" (the site drafts it) | confirmed |
| Level 3, Established | Named regional support | the handbook's Regional/Country Mentor Lead supports mentors, not institutions | planned |
| Level 3, Established | A WordCamp speaking slot; Co-marketing | not found | both planned |
| Level 4, Flagship | A case study; A Foundation announcement; Input on the program's direction; Event hosting | not found | all four planned |
| Card, Campus Connect | Certificate of participation | as at step 1 | confirmed |
| Card, WordPress Credits | Certificate; Badge; Credit | as at step 4; the WordPress Credits pages describe the program as credit, an internship or a module the institution recognizes | confirmed |
| Card, WordPress Credits | Hours transcript | as at step 4 | planned |
| Card, Facilitator Training | Ready-to-run sessions | Facilitator Training, "How the program works": guides with timed agendas and activity sheets | confirmed |
| Card, Facilitator Training | Facilitator listing | as at step 6 | planned |
| Card, Learn WordPress | Course completions on your profile | WordPress.org profiles show a "Completed courses" panel | confirmed |
| Card, AI Leaders | Micro-credential | ai-leaders.org FAQ: "All graduates will receive a micro-credential certificate" | confirmed |
| Card, AI Leaders | $1,000 for completers | not on ai-leaders.org, whose FAQ names coaching, a letter of recommendation and employer connections for the top 40 finalists | planned |
| Card, Builder Certification | Industry certification | wordpresscertifications.com: "Coming Soon"; the card's "In development" tag already says so | no extra mark |

The pages: make.wordpress.org/meta/handbook/documentation/profile-badges/; make.wordpress.org/community/handbook/education/campus-connect/issuing-a-certificate-of-participation/ and .../campus-connect-office-hours/; make.wordpress.org/community/handbook/education/credits/, .../credits/how-to-participate/institutions/ and .../credits/how-to-participate/wordpress-credits-logos/; make.wordpress.org/community/handbook/education/wordpress-facilitator-training-program/ and .../how-the-program-works/; make.wordpress.org/community/handbook/education/credits/program-manager-guide/mentor-management/regional-country-mentor-lead/; wordpress.org/education/; events.wordpress.org/campusconnect/; jobs.wordpress.net/faq/; ai-leaders.org; wordpresscertifications.com/wordpress-builder-certification/.

Of 51 items (28 on the student steps, 12 on the partner levels, 11 on the cards), 21 are confirmed, 29 are marked planned, and 1 is covered by its card's tag.

## 7. The two blocks

### 7.1 Shared rules

- **Server first.** `render.php` prints the whole content. The view script only adds behavior to markup that is already complete, and every control it needs is printed with the `hidden` attribute and unhidden by the script, so without JavaScript there is no control that does nothing (the plugin's Expand all rule).
- **Script.** One `view.js` per block, plain JavaScript in the theme's style (an IIFE, `'use strict'`, no dependency), loaded by `viewScript` only where the block renders; `view.asset.php` returns no dependencies and the theme's version. The script reads no storage and writes nothing to the URL. Every string it shows comes from the server, already translated, in `data-` attributes (plurals through `_n()`), so the script needs no translation layer.
- **Buttons.** Every toggle is a `<button type="button">` with `aria-pressed`, inside a `role="group"` with an `aria-label`. One button of a group is pressed at a time. Tab moves between buttons, Enter and Space press; pressing never moves focus. A polite live region (`role="status"`, visually hidden) says what changed.
- **Look of a toggle.** Not pressed: white, 1px `--wpc-control-border` (3.32:1, the boundary of a control), ink-70 text 14px, 36px tall (above the 24px target minimum), `--wpc-surface-band` on hover. Pressed: brand tint with an inset 1px brand ring and weight 600, the theme's active-filter treatment, so the state is told by weight as well as color. Under `forced-colors: active`, pressed takes `Highlight` and `HighlightText`, as the dashboards' switcher does.
- **Focus** is the theme's ring (2px brand, 2px offset, 5.61:1 on white).
- **Motion.** Nothing animates by default; the one hover lift on a step is declared inside `prefers-reduced-motion: no-preference` only, and the theme's global reduce rule stands. In-page links jump; no smooth scrolling is added.
- **Editor.** Like `program-stat` and `guide-toc`, the blocks ship no editor script. The theme plan's first task checks how the Site Editor on WordPress 7.1 shows a block with none; if it shows a placeholder rather than a preview, each block gets a hand-written editor script with a server-side preview and no settings, as `viewer-chip` has.

### 7.2 `wpcredits/program-index`

Markup without JavaScript:

```html
<div class="wp-block-wpcredits-program-index wpc-hub-index">
  <div class="wpc-hub-chips" role="group" aria-label="Filter programs by audience" hidden>
    <button type="button" class="wpc-hub-chip" data-filter="all" aria-pressed="true"
            data-announce="Showing all 6 programs">All</button>
    <button type="button" class="wpc-hub-chip" data-filter="student" aria-pressed="false"
            data-announce="Showing 3 of 6 programs for students">For students</button>
    ... For faculty ("Showing 2 of 6 programs for faculty"), Career and credentials ("Showing 3 of 6 programs in Career and credentials")
  </div>
  <p class="screen-reader-text" role="status"></p>
  <div class="wpc-hub-programs">
    <article class="wpc-hub-program" id="program-campus-connect" data-audiences="student">
      <p class="wpc-hub-program__meta"><span class="wpc-hub-tag wpc-hub-tag--live">Live</span>
        <span class="wpc-hub-program__step">Step 1 · Attendee</span></p>
      <h3 class="wpc-hub-program__name">Campus Connect</h3>
      <p class="wpc-hub-program__what">A half or full-day hands-on WordPress workshop on campus. ...</p>
      <dl class="wpc-hub-program__facts">
        <div><dt>Time</dt><dd>Half or full day</dd></div>
        <div><dt>You get</dt><dd><ul class="wpc-hub-items"><li>Certificate of participation</li></ul></dd></div>
      </dl>
      <p class="wpc-hub-program__links"><a href="https://events.wordpress.org/campusconnect/">Find or host an event<span aria-hidden="true"> →</span></a></p>
    </article>
    ... five more
  </div>
</div>
```

Cards are white, 1px `--wpc-line-card`, 8px radius, 24px padding, in a grid of `repeat( auto-fill, minmax( min( 100%, 300px ), 1fr ) )` with 16px gaps; one column at 375px. The status tag is 12px uppercase: "Live" in green on green tint (4.86:1), "In development" in amber on amber tint with the amber border (4.90:1; the amber chip color would be 4.30:1, so it is not used for text). The step line is an eyebrow (12px, ink-60). Facts: `dt` eyebrows, `dd` 14px ink; "You get" is a list without bullets, one item to a line, 4px apart. Links: 14px, 600, brand, under a 1px `--wpc-line` rule.

The script's job: unhide the chips; on a press, set `aria-pressed`, give `hidden` to every card whose `data-audiences` lacks the filter (none for "All"), and copy the chip's `data-announce` into the status region. When a link to a program card is followed (a pathway "next" link, or the page opened at `#program-...`), it presses "All" first, so the target is never hidden.

### 7.3 `wpcredits/pathway`

Markup without JavaScript, for each ladder in turn (students, then institutions):

```html
<div class="wp-block-wpcredits-pathway wpc-hub-pathway">
  <div class="wpc-hub-switch" role="group" aria-label="Choose a pathway" hidden>
    <button type="button" aria-pressed="true" aria-controls="wpc-hub-ladder-students">For students</button>
    <button type="button" aria-pressed="false" aria-controls="wpc-hub-ladder-institutions">For institutions</button>
  </div>
  <p class="screen-reader-text" role="status"></p>
  <section class="wpc-hub-ladder" id="wpc-hub-ladder-students" aria-labelledby="wpc-hub-ladder-students-title" data-start="3">
    <h2 id="wpc-hub-ladder-students-title">Seven steps from first workshop to first job</h2>
    <p class="wpc-hub-ladder__lede">Each step earns a badge ... Enter at whichever step fits.
      <span class="wpc-hub-ladder__hint" hidden>Select a step to see what it leads to.</span></p>
    <ol class="wpc-hub-stairs" aria-label="Steps for students" style="--wpc-count:7">
      <li class="wpc-hub-stair" style="--wpc-t:0">
        <a class="wpc-hub-stair__pick" href="#wpc-hub-step-students-1" aria-describedby="wpc-hub-by-students-1"
           data-announce="Step 1 of 7: Attendee">
          <span class="wpc-hub-stair__n">Step 1</span> <span class="wpc-hub-stair__name">Attendee</span></a>
        <span class="wpc-hub-stair__by" id="wpc-hub-by-students-1">Attend a Campus Connect event</span>
      </li>
      ... six more, --wpc-t rising from 0 to 1
    </ol>
    <div class="wpc-hub-details">
      <article class="wpc-hub-detail" id="wpc-hub-step-students-1">
        <p class="wpc-hub-detail__count">Step 1 of 7</p>
        <h3 class="wpc-hub-detail__name">Attendee</h3>
        <dl class="wpc-hub-detail__cols">
          <div><dt>Earned by</dt><dd>Attend a Campus Connect event.</dd></div>
          <div><dt>Unlocks</dt><dd><ul class="wpc-hub-items">
            <li>Certificate of participation</li>
            <li>A seeded WordPress.org profile&nbsp;<span class="wpc-hub-planned">(planned)</span></li>
            <li>A profile badge</li>
          </ul></dd></div>
          <div><dt>Academic recognition</dt><dd>...</dd></div>
        </dl>
        <p class="wpc-hub-detail__next"><span>Leads to step 2: Builder</span>
          <span class="wp-block-button is-style-outline wpc-button--large"><a class="wp-block-button__link wp-element-button" href="https://events.wordpress.org/campusconnect/">Find a Campus Connect event</a></span></p>
      </article>
      ... six more
    </div>
    <p class="wpc-hub-ladder__key">Items marked planned are not offered yet.</p>
  </section>
  ... the institutions ladder, the same shape, levels and two columns
</div>
```

Without JavaScript both ladders show one after the other: each its heading, lede, the staircase as a picture of the climb whose steps are in-page links to their details, then every detail as a card. The "next" buttons are the core Button markup, so the theme's outline button rules dress them unchanged.

The staircase. At 900px and wider: a grid of `--wpc-count` equal columns, 8px apart, aligned to a 2px ink baseline, each step's `min-height` running from 160px (first) to 360px (last) through `--wpc-t`, so it rises and never cuts its text; step backgrounds run from brand tint (#eff2ff) to brand border (#c7d1ff) through `color-mix()`, with ink names (16px, 600) and ink-70 "by" lines (13px; 6.36:1 at the darkest step, 8.55:1 at the lightest). Below 900px: one column of full-width rows, each its text on `--wpc-surface-band` with a 6px brand bar along its bottom whose width runs from 1/`--wpc-count` of the row (first) to the whole row (last) through `--wpc-t`, so the climb still reads, no text crosses a color edge, and nothing scrolls sideways at 375px (the owner's choice, 15 Oct 2026; the pressed row stays brand with its bar in the brand tint). Detail cards: white, 1px `--wpc-line-card`, 8px radius, 28px padding; the count an eyebrow, the name 26px; the columns three (or two) side by side at 900px and wider, stacked below; the next line under a 1px rule.

The script's job: unhide the switch and the hint; mark the block `is-interactive`; replace each step's link with a button of the same content and `aria-describedby`, adding `aria-pressed` and `aria-controls` (its detail's ID); press each ladder's `data-start` step and hide every other detail; on a step press, show that detail, hide the rest, and announce `data-announce`; on a switch press, show that ladder, hide the other (its own pressed step kept), and announce the ladder's heading. Pressed step: brand background, white name (5.61:1) and brand-tint "by" line (5.03:1), the focus ring outside it on white. A page opened at `#wpc-hub-step-institutions-2`, or a link followed to such an address, selects that ladder and step. Without the script the same address simply scrolls to the detail.

### 7.4 Where the texts live

`inc/hub-content.php` returns the programs, steps and levels as arrays: slug, translated strings (each benefit its own string with a `planned` flag) (`__()` with the `wpcredits-theme` domain, translator comments where a string has a placeholder), audiences, status, and each link as either an outside address or a site target (`institution_application`, `sponsor_application`, an in-page anchor) resolved at render. The section heads, ledes, doors, field table and band are core blocks in the patterns, printed with `esc_html_e()`. No string is assembled from parts a translator cannot see whole; counts use `_n()` and `number_format_i18n()`, and the percent sign is a translatable `%s%%`.

## 8. The live outcome figures

### 8.1 Definitions (the public dashboard's, exactly)

**Graduation share.** Over every record of Students Reports (`tbljYkkVGbeoaWEtY`) whose name (`fldyXVlkChJaO9Q47`) is present and not the empty string (not trimmed, as the dashboard's `if not name` does):

- G = records whose Status (`fldCMdqqJGAUQ9nbV`) select name, trimmed and lowercased, is `graduate`;
- D = records whose Status, the same way, is `dropped out`;
- n = G + D; the share = 100 × G / n rounded half up (the dashboard's `Math.round()` in `template.html`, mirrored by the tracker's `outcomesHTML()`).

Students still in the program, "Not moving forward", "Paused", "Fail", "Interested", "SPAM" and a blank status are in neither count. Today: G 361, D 52, n 413, 87%.

**Would recommend.** Over every record of Feedback (`tblx3TH6fp4edQJDm`): N = records whose recommend answer (`fldZXbvOTIGad6r90`), trimmed and lowercased, is not empty; P = those equal to `likely`; the share = 100 × P / N rounded half to even (Python's `round()` in `_pct_positive()`; the tracker's `$pct_positive` uses PHP's default half up, which differs only at an exact half, and this page follows the public dashboard). "Neither likely nor unlikely" and "Unlikely" count in N only. Today 88% of 315.

**Plan to keep contributing.** The same over the keep answer (`fld8RZMdCLYKtwcLN`). Today 79% of 310.

A Feedback row is created for every student whether or not they answer, so rows are never the denominator; each figure carries its own n, as the dashboard's comment insists. The two questions are on the final survey ("Finishing up"), which the dashboard notes only finishing students answer, hence "of graduates". The field IDs are pinned as constants, as in the dashboard; the tables come from the plugin's settings (`reports_table`, `feedback_table`), whose live values are the dashboard's.

### 8.2 Reading

One run makes two reads through `WPCPM_Airtable` with `by_id`:

1. Students Reports, `fields[]` the name and Status IDs, filtered with `formula_in()` on the Status column (lowercased and trimmed, values `Graduate` and `Dropped out`), so about 413 rows in five pages instead of the whole table; the PHP count then applies the definition above to every row returned.
2. Feedback, `fields[]` the two answer IDs, every row (834 rows on 2 September 2026, about nine pages), no formula, so no column name is depended on.

About fifteen requests, under the client's five a second and its shared 429 back-off. A run takes a lock (an option, ten minutes) so the weekly event and a WP-CLI run never overlap. Any request that fails ends the run with nothing stored. The name is read only to test that it is there, as the dashboard does, and is never kept.

### 8.3 Schedule

**Weekly, on the dashboard's Monday (decision 7).** One event, `wpcpm_outcome_figures_weekly`, on WordPress's own `weekly` recurrence, its first run set to the next Monday at 07:00 UTC, scheduled on activation, checked on `init` (and put back on 07:00 UTC Monday if it has drifted), cleared on deactivation. It is set in UTC, as the dashboard's workflow is, so the site's Warsaw clock changes never move it. 07:00 UTC is 41 minutes after the latest finish among the last seven scheduled builds (06:18:51 UTC on 5 October) and an hour after the workflow's own start time. The host fires scheduled events although `DISABLE_WP_CRON` is set, as it does for every `wpcpm_*` event today.

**Waiting for the dashboard.** A run first reads the dashboard's build date: one `wp_safe_remote_get()` of https://wordpress.github.io/WPCredits/ (ten-second timeout), and the `builtAt` date in the data the page carries. When that date is not the run's Monday, the dashboard has not built yet (GitHub runs scheduled workflows late at busy hours), so the run reads nothing from Airtable and tries again an hour later. When the page cannot be read, or the try at 12:00 UTC still finds an older date, the run reads Airtable anyway: the figures belong to the program whether or not the dashboard ran, and their printed date says which Monday they are from.

**Retries.** A run that fails (section 9) tries again an hour later, the last try at 12:00 UTC that Monday; after that, the next Monday. A held run does not retry: a person decides (section 8.5). `wp wpcredits outcome_figures --refresh` runs one at once, without the wait.

**What "the same numbers" can mean.** The dashboard and the site read the same base about an hour apart, so they agree unless a student's record changed in that hour, which the parity check of section 10.2 measures once. A manual build of the dashboard later in the week (there were manual builds on four days in September) moves the dashboard and not the homepage, and the two agree again the next Monday.

### 8.4 Storage

- `wpcpm_outcome_figures` (autoloaded, under 1 KB): the last stored set: for each figure its share, its numerator and n, and the set's read time (one time for all three: a run stores all three or none).
- `wpcpm_outcome_figures_run` (not autoloaded): the last attempt's time, result (`stored`, `held`, `failed`), a reason code and its sentence, the counts it saw, and how many tries that Monday has made.
- Nothing else: no record ID, no name, no address, no answer text. Uninstall deletes both options with the plugin's other data.

### 8.5 Guards: what is never published

A run computes the three figures and stores them only when all of these hold; otherwise it is **held**: the stored set stays, the run option records why, and the syncs row says so.

1. Both reads completed (otherwise the run **failed**, not held).
2. No numerator is zero: a zero is almost always a renamed status or choice, never a program with no graduates.
3. No n and no numerator fell by more than 10% from the stored set: the counts are cumulative and only move down by a few through duplicate clean-ups, so a large fall means a filter, a status or a choice changed.
4. No share moved by more than 10 points from the stored set in one run: with n above 300 a real week moves a share by well under one point.

A person who has checked a held run can store it with `wp wpcredits outcome_figures --refresh --accept`. The first run on a site has nothing to compare with, so guards 3 and 4 apply from the second run on, and the parity check of section 10.2 stands in for them once.

At display, `published()` leaves out any figure whose n is under **30**, and returns nothing once the stored set is more than **29 days** old, which is four Mondays in a row without a good read (decision 10): a set read at 07:00 UTC on a Monday is 28 days and 5 hours old when the fourth Monday after it makes its last try at 12:00 UTC, so 29 days hides the figures the day after that try fails. Thirty days, the first draft's limit, would hide them on a Wednesday, a day that means nothing in a weekly cadence; 29 says the rule in the cadence's own unit. Both are constants with a filter (`wpcpm_outcome_figures_min_sample`, `wpcpm_outcome_figures_max_age`).

### 8.6 Display: `wpcredits/program-stat`, extended

- `stat` gains `graduation`, `recommend`, `keep` and `outcomes-source`. A new attribute, `label` (string, empty by default), replaces the figure's default label when set; the defaults are the three labels of section 6.2, translated.
- For an outcome figure, the block prints, or prints nothing:

  ```html
  <div class="wp-block-wpcredits-program-stat wpc-figure">
    <p class="wpc-figure__value">88%</p>
    <p class="wpc-figure__label">of graduates would recommend the program</p>
    <p class="wpc-figure__base">315 answers to the final survey</p>
  </div>
  ```

  `fallback` is ignored for outcome figures: they are never typed in.
- `outcomes-source` prints the source line of section 6.2 with the stored set's date in the site's date format and time zone ("October 12, 2026"), or nothing when no outcome figure may be shown.
- The four count stats (`mentors`, `students`, `institutions`, `sponsors`) keep their markup and behavior byte for byte, so the live homepage's "mentors currently active" card does not move.
- The label and base live in the block, not in a neighboring paragraph, so a figure that cannot be shown takes its words with it.

### 8.7 Caching

`published()` reads one small autoloaded option and keeps it for the request; the theme adds no transient. The host's edge cache holds the page's HTML for its own lifetime, so a refreshed figure reaches a visitor when the cached copy expires; with a weekly cadence and a printed date that is acceptable, and the plugin purges nothing.

### 8.8 Privacy

Aggregates only, program-wide, each over at least 30 answers or outcomes. The run reads four fields and keeps counts. No figure per institution, mentor, track or semester is computed, stored or shown. The page's figures are the same kind the public dashboard already publishes.

## 9. Error handling

| What happens | What the run does | What the page shows | What an Administrator sees |
| --- | --- | --- | --- |
| No token or base in Settings | does not start | the stored set, else nothing | "Not connected" on the syncs row |
| The dashboard has not built yet that Monday | reads nothing, tries again an hour later; at 12:00 UTC reads Airtable anyway | the stored set | "Waiting for the dashboard's Monday build" |
| The dashboard's page cannot be read | reads Airtable without waiting | the new set, or the stored set if the read fails | none |
| Airtable unreachable, a timeout, a 5xx | fails at the first error, stores nothing, tries again hourly until 12:00 UTC that Monday, then the next Monday | the stored set with its date, until four Mondays in a row have failed (29 days); then nothing | "Failed" with the error's sentence |
| 401 or 403 (token refused or scope lost) | fails, retries as above | as above | "Failed: Airtable refused the token" and the Settings link |
| 404 or 422 (table moved, field ID gone) | fails | as above | "Failed" with Airtable's message |
| 429 | the client's shared back-off; a long wait under cron fails the run | as above | "Failed: Airtable asked the site to wait" |
| A guard of section 8.5 trips | held, nothing stored | the stored set | "Held" with the guard's reason and counts |
| A figure's n under 30 | stored | that figure left out, the others shown | the counts on the row |
| Plugin inactive or older than 1.122.24 | none | no figures, no source line; the copy column takes the width | none |
| JavaScript fails or is off | none | the complete server markup of section 7 | none |

The syncs row is labeled "Outcome figures", its name links to the Airtable section of Settings (where a failure is fixed), "Last run" shows when the stored set was read, and a line under it shows the last attempt when that was held or failed. The Administrator Dashboard's syncs card shows the same row, since both read `health()`; the plan checks its renderer takes the row unchanged.

## 10. Testing and proof

### 10.1 Plugin

- `bin/test-outcome-figures.php`: the definitions on fixture rows shaped as Airtable answers by field ID (missing and empty names left out, a name of one space counted, statuses with stray spaces and capitals matched, every other status in neither count, blank answers outside N); rounding (12.5 gives 12 for a survey share and 13 for the graduation share; 87.5 gives 88 for both); the minimum sample; the 29-day age; the wait for the dashboard (that Monday's `builtAt`, an older one, no page, the 12:00 UTC try); each guard holding with its reason; `--accept`; a failed read leaving the stored set untouched; nothing but counts in either option. Invented record IDs are uppercase letters and digits only, so the release script's scan passes them.
- `bin/test-airtable.php` gains the `by_id` argument (the parameter sent, the default unchanged).
- The syncs row in `bin/test-overview.php` and `bin/test-administrators-dashboard.php`; the CLI command's output; the plugin's standards, spelling and dead-annotation checks; the translation template regenerated.

### 10.2 Parity with the public dashboard

On the owner's yes after the plugin deploy, on a Monday after the dashboard's 06:00 UTC build: `wp wpcredits outcome_figures --refresh --dry-run` on the live site prints G, D, N and P for both survey questions, and the name the base gives each of the four pinned field IDs (from the base's table metadata), which confirms `fldZXbvOTIGad6r90` and `fld8RZMdCLYKtwcLN` are the final survey's recommend and keep questions. The counts must equal the dashboard's blob (`builtAt`, `global.graduates`, `global.dropouts`, `feedback.recommend`, `feedback.keep`), or differ only by records changed between the two reads, which the session names. Then one `--refresh` stores the first set.

### 10.3 Theme

- `php bin/check-selectors.php` passes: classes, tokens (every `--wpc-*` in `hub.css` declared), table scope, no dashes. It gains one lookup: every `.wpc-hub-*` and `.wpc-figure*` selector in the theme's stylesheets is printed by a theme pattern, template, block file or `inc/` file.
- Render checks on the local main-line copy (http://localhost:8883), server-side with the capture recipe the dashboards use (`wp eval-file`, `wp()`, `template_redirect`, `template_include`), as a signed-in Administrator: the preview page holds the six cards with their nine links, the table's seven rows and its `tabindex`, `role` and `aria-labelledby`, both ladders with eleven details and their links, the chips, switch and hint with `hidden`, no content with `hidden`, and every address of section 6.9. The live homepage's markup (template 544) is captured before and after and is identical.
- In the browser on the same copy, with JavaScript on: the chip counts (6, 3, 2, 3) and their announcements, `aria-pressed` on each press, focus kept, a pathway link to a hidden card pressing "All", step and switch presses, the default steps (3 and 2), a deep link to a level, a keyboard-only run through the whole page, the focus ring on every control and on the table's scroll box, `prefers-reduced-motion: reduce` emulated. With JavaScript off: everything of the server check readable, nothing that does nothing.
- At 375px, 640px, 900px and desktop width: `document.documentElement.scrollWidth` equals the window's width; the table scrolls inside its box at 375px; every gap between consecutive blocks measured and listed (the owner's spacing rule).
- Contrast, read from computed styles: ink 16.67:1, ink-70 9.54:1 and ink-60 5.45:1 on white (4.95:1 on the band), brand links 5.61:1, white on brand 5.61:1, brand tint on brand 5.03:1, the "by" lines 6.36:1 to 8.55:1 across the steps, green on green tint 4.86:1, amber on amber tint 4.90:1, control borders 3.32:1.
- Figures: on the local copy, which refuses Airtable, the option is seeded with invented counts through WP-CLI and the hero is checked with three figures, with one under the minimum sample, with a set 30 days old (none shown, the copy column at full width), and with the plugin's class absent.
- Translations: `bin/make-pot.sh` regenerated; every new string in `wpcredits-theme`, none concatenated, plurals through `_n()`.
- Screenshots on the local copy at desktop width and at 375px: the hero with and without figures, each chip pressed, both ladders with a selected step, the no-JavaScript page, the table's scroll box.
- The release script's scan finds no private word and no dash in either release.

## 11. For the owner's review

The first draft's eight questions are settled. Four are the owner's answers (section 2, decisions 6 to 9: the graduation label, the weekly read on the dashboard's Monday, the companies door kept as the designer wrote it, and "(planned)" on every benefit no public page confirms). Four are defaults taken by the coordinating session, which the owner can change at review:

1. **Hide-after limit (decision 10):** the figures stop showing after four Mondays in a row without a good read, 29 days (section 8.5).
2. **The Sponsor button (decision 11)** goes to `/sponsor-application/`, the site's only public sponsor page.
3. **No mentor line (decision 12),** as the designer's draft has none. The current homepage has a "Become a mentor" button (and "Open your Mentor Report Card"); mentors still reach their card from the header's sign-in. The owner can ask for a line in the closing band.
4. **The preview (decision 13):** a Private page; the designer and the program team see it through screenshots the coordinating session takes at desktop width and at 375px.

The benefit list of section 6.11 is the other thing to read at review: each "planned" mark comes off in a theme release once a public page confirms the item, or once the owner confirms it is offered and a page says so.

## 12. The owner's choices during the build, 15 Oct 2026

- **The chips' announcements name the audience.** Two chips said the same sentence ("Showing 3 of 6 programs"), which some screen readers do not repeat; each filtered chip now names its audience: "Showing %1$s of %2$s programs for students", "... for faculty", "... in Career and credentials" (All keeps "Showing all %s programs").
- **The steps on a phone** are plain rows with a rising bar beneath each (section 7.3), chosen over the filled bars of rising width whose text ran past the fill.
- **96px between sections** (section 6.1), the spec's padding without the editor's 20px block gap, chosen over the live homepage's 116px.
- **A sign-in section right under the hero** (the owner: "There still needs to be a clear section to login for students, mentors, sponsors and institution in the new homepage"). A server-rendered block, `wpcredits/sign-in`, placed in the template between the hero and the doors: eyebrow "Already in a program?", Heading 2 "Sign in to your page" ("Open your page" for someone signed in), then a card each, in this order, for students (Student Report Card), mentors (Mentor Report Card), institutions (Institution Dashboard) and sponsors (Sponsor Dashboard). Signed out, a card's link is the login with that page as where to land ("Sign in"); signed in, it is the page itself ("Open"), the same four for everyone, a page the person cannot use saying so itself. A page that is not published leaves its card out; with none, the section is not shown. The cards are the doors' cards, the head the programs section's head; four across from 900px, two from 640px, one below. A block, not a pattern, because the links depend on who is looking and a pattern is frozen into the template when the template is saved in the Site Editor.

## 13. The feedback of 15 and 16 Oct 2026 (theme 1.24.17)

The private preview was shared with the team on 15 Oct 2026. What changed from it, each on the owner's word:

- **The Builder step** unlocks "Showcase eligibility" and "A profile badge", both still marked planned (section 6.11). The free .blog or .art domain with a year of hosting and the full theme and plugin library came off: the designer asked that the step "should just be: showcase eligibility, badge on .org profile", and an Administrator noted that the domain and hosting are a WordPress.com perk while the page names no host.
- **The steps plainly open their details** (the owner: "is it clear to click on the columns to see more", the designer: "more front and center"). The hint of section 6.7 left the lede for a prompt of its own between the lede and the staircase, on both ladders: "Select a step to see what it unlocks." and "Select a level to see what it brings.". Every step and level ends with a cue, "See what it unlocks" or "See what it brings", and "Shown below" on the one pressed, hidden from screen readers, which hear the step as before. A step lifts under the keyboard's focus as under the pointer, and from 900px the detail shown points up at its step.
- **The WordPress Credits courses** (an Administrator, in #wpcredits-admins: list the courses so schools and students can pick one; the designer: on wordpresseducation.org and on wordpress.org/education both). Under the six cards of the index, "Which WordPress Credits course?": a card for each of Intro to Contribution, Foundations, the Developer track and the Designer track, with its hours or who it is for, its name linked to its Learn WordPress page, and one line on what it asks, four across from 900px and two from 640px, 56px under the cards as the field-of-study table is under it. The hours are Intro to Contribution's page's (50) and, for Foundations, the owner's (150 or more); the two tracks' pages give none. Inside the card, the first build of the guide made its row 794px tall and left two neighbors about 430px empty, so the owner chose the band. The card's links are now "Compare the four courses" (to the band), the program page and the impact dashboard (section 6.5), and Step 4's link reads "See the courses" and leads to the band (section 6.7). The filter chips leave the band alone: an institution picks the course.
- **Not changed:** the Attendee step's club invite stays out (the designer removed Student Clubs on 14 Oct); the name of the template, "Education Hub home", stays until the name the team calls the hub ("the Directory" in the designer's document) is settled.
