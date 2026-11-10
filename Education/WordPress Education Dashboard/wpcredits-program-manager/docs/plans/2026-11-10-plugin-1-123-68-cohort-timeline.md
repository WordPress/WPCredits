# Cohort timeline (1.123.68): implementation plan

**Goal:** Program managers see, twelve months ahead, how many students are on the program each month,
who is new, and whether the active mentors have the places for them.

**Spec:** `docs/specs/2026-11-10-cohort-timeline-design.md`. It binds.

## Tasks

- [x] **1. The reader.** `includes/modules/class-wpcpm-cohort-timeline.php`: `classify_row()`,
  `classify_registration()`, the pure `compute()` and `read()` over the roster index, the site
  registrations and the mentor engagement summary. Suite: `bin/test-cohort-timeline.php`.
- [x] **2. The drawing.** `includes/modules/class-wpcpm-cohort-timeline-view.php`: the Upcoming cohorts
  card, the view at `?wpcpm_view=cohort-timeline`, the chart in HTML and CSS
  (`assets/css/cohort-timeline.css`), the month table, the intakes and the notes. The same suite draws
  both.
- [x] **3. The page.** `WPCPM_Administrators_Dashboard::render()` draws the view when asked for it,
  after its own gates; the card is the Watch group's first; `collect()` reads the report once. The
  block's description names the card.
- [x] **4. The setting.** `students_per_mentor` (people scope, 3, 1 to 20) and its section,
  Cohort planning. `bin/test-settings.php` holds the probe, the scope, the bounds and the section.
- [x] **5. The rules the site already pins.** The loader and `uninstall.php` require both files
  (`bin/test-roles.php`); the two scroll boxes are positioned, named regions
  (`bin/test-institution-panel.php`); the month table is not an `wpcpm-admin-table`, which is the
  cards' own (`bin/test-administrators-dashboard.php`).
- [x] **6. Release.** Guides (`docs/sections/31`, `34`, rebuilt), readme changelog, version in the
  header, `WPCPM_VERSION`, Stable tag and the .pot header, the .pot's new strings, the README table.

## Checks run

Every `bin/test-*.php` suite passes but `bin/test-report-images.php`, whose two failures were there
before this change. `bin/check-references.php`, `bin/check-spelling.php` and `bin/check-standards.sh`
(no errors) pass. The chart's colors were run through the dataviz palette validator, and the text
colors measure 5:1 or more on white.
