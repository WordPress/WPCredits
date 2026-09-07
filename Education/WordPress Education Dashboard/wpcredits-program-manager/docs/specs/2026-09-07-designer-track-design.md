# Designer Track, a fourth program, and syncs every three hours

**Date:** 7 September 2026
**Component:** `wpcredits-program-manager` 1.98.1, ships as 1.98.2 with `wpcredits-theme` 1.24.2
**Status:** built at the product owner's request of 7 September 2026 ("Add to the site a new course ... the Designer Track" and "update all the Airtable syncs on the site to run every 3h"); the assumptions in section 6 were not confirmed with the owner before building and are listed for them.
**Depends on:** the Developer Track design of 26 August 2026 (`2026-08-26-developer-track-program-design.md`), whose shape this document copies: the track is derived from the Airtable status, one map entry per track, no boolean.

House rules that apply to every line below: comments explain why and name the decision behind a rule; no em dashes; full product names; US English in every string a person reads; `bin/` and `docs/` never ship but are public on the mirror, so nothing personal enters them.

---

## 1. What Airtable already says

Read from the base (`appIzQKfwTn5dyPVp`) on 7 September 2026.

- `Students Reports` (`tbljYkkVGbeoaWEtY`) `Status` has the choice **`Designer Track`** (`sel11bYUeNiZiMxwj`), beside `Developer Track`. The track is a status, as for the Developer Track.
- The grade field **`Beginner WordPress Designer`** (number, one decimal) exists already and is on the 150-hour and Developer Track forms as an optional course.
- Twenty-one new fields carry the Designer Track's practical work, all named `Practical: <lesson> - <part>`:

| Airtable field | Type |
| --- | --- |
| `Practical: Duplicate & Explore WP Design Library - Reflection` | multilineText |
| `Practical: Duplicate & Explore WP Design Library - link` | url |
| `Practical: Duplicate & Explore WP Design Library - image` | multipleAttachments |
| `Practical: Local WordPress Environment for Design Testing - Tool used` | singleSelect: `WordPress Studio`, `MAAMP`, `DevKinsta` |
| `Practical: Local WordPress Environment for Design Testing - Screenshot` | multipleAttachments |
| `Practical: Change Your Site’s Global Styles - Notes` | multilineText |
| `Practical: Change Your Site’s Global Styles - Before Screenshot` | multipleAttachments |
| `Practical: Change Your Site’s Global Styles - After Screenshot` | multipleAttachments |
| `Practical: Style Book - Notes` | multilineText |
| `Practical: Style Book - Screenshot` | multipleAttachments |
| `Practical: Landing Page with Layout Blocks - Note` | multilineText |
| `Practical: Landing Page with Layout Blocks - Screenshot` | multipleAttachments |
| `Practical: Apply Custom CSS in the Site Editor - Notes` | multilineText |
| `Practical: Apply Custom CSS in the Site Editor - CSS` | multilineText |
| `Practical: Apply Custom CSS in the Site Editor - Screenshot` | multipleAttachments |
| `Practical: Submit a Custom Block Pattern - Link` | url |
| `Practical: Submit a Custom Block Pattern - Screenshot` | multipleAttachments |
| `Practical: Test Your Site for Accessibility - Part 1 - Note` | multilineText |
| `Practical: Test Your Site for Accessibility - Part 1 - Screenshot` | multipleAttachments |
| `Practical: Test Your Site for Accessibility - Part 2 - Note` | multilineText |
| `Practical: Test Your Site for Accessibility - Part 2 - Screenshot` | multipleAttachments |

The apostrophe in `Site’s` is the typographic one (U+2019) in the base; the keys must be copied exactly, because a write has to name them. There is no `Designer Track ONLY personal link` formula field: the Fillout forms are legacy since the site's own form replaced them (1.48.0 and 1.63.0), and the Student Report Card no longer links them, so no new link field is needed.

## 2. What Learn says

`https://learn.wordpress.org/course/wordpress-credits-designer-track/` (post 403425, "150 hours"), three modules:

1. **Onboarding:** Welcome and Essential Communication Guidelines; Share your WordPress profile; Join global Slack; Differences between WordPress.com and WordPress.org; Complete: Open source basics and WordPress; Complete: How decisions are made in the WordPress project; Complete: Community meeting etiquette; Complete: Writing in the WordPress voice; Complete: Basic principles of conflict resolution; Complete: Beginner WordPress Designer Course; From design skills to contribution; Create your portfolio; Reflection: Building Your Portfolio.
2. **Project:** How to contribute to WordPress; The WordPress Design Team Deep Dive; Introduction to Figma for WordPress Design; Design Contribution Pathways for Students; Understand WordPress Design Principles; Practical: Duplicate and Explore the WordPress Design Library; Practical: Set Up a Local WordPress Environment for Design Testing; Explore and practice WordPress design; Practical: Change Your Site's Global Styles; Practical: Customize with the Style Book; Practical: Compose a Landing Page with Layout Blocks; Practical: Apply Custom CSS in the Site Editor; Contribute to a real project; Practical: Create and Submit a Custom Block Pattern; Practical: Test Your Site for Accessibility; Define and begin developing your contribution project; Reflection: Choosing Your Team and Project; Alumni Program: Connect with the community and plan your contribution beyond WP Credits; Complete the first feedback form; Reflection: Your First Contribution; Leave your mid-course feedback; Reflection: Halfway Check-In; Participate at a WordPress Event (online or in person).
3. **Wrap-up:** Prepare and deliver a wrap-up report; Get your certificate; Complete the feedback form.

The Onboarding module has no Beginner, Intermediate or Advanced WordPress User course and no optional developer courses; the Beginner WordPress Designer course is required.

## 3. The track

- `WPCPM_Program`: `const STATUS_DESIGN = 'Designer Track'`; `labels()` gains `'Designer Track' => 'Designer Track'` (with the same comment as the Developer Track's entry: `is_track()` tests membership of this map, which gates the feedback surveys and the course button); `courses()` gains the Learn URL; `track()` returns `'design'`; `hours_targets()` gains `'Designer Track' => 150`, the figure Learn states (assumption 6.1); `badge()` returns `'design'`.
- Settings: the `student_statuses` default gains `'Designer Track'`, and `maybe_upgrade()` merges it into the saved value the way it did for the Developer Track, so the students sync's formula fetches Designer Track students from the first run after the update. No new field-map entry: `WPCPM_Mentors_Sync::link_field( 'design' )` falls back to the 150-hour link, which nothing displays any more.
- The syncs need nothing else: the track is derived from the status at the point of use.
- The Mentor Report Card's track chip renders `design`, styled in the plugin sheet and the theme like `dev`.
- `bin/fixtures/reports-table-fields.json` gains the twenty-one fields above with their types, so the fixture check and the references check know them.

## 4. The report form for the track

`WPCPM_Student_Report_Form::fields( 'design' )`, in the order of the Learn course, grouped as the course is:

**Onboarding:** the contact rows (WordPress.org profile, Slack name); the five `Complete:` grades in the Learn order (Open source basics, How decisions are made, Community meeting etiquette, Writing in the WordPress voice, Basic principles of conflict resolution); then `Beginner WordPress Designer` as a required mark under the lead "Complete the Beginner WordPress Designer course", not under "Optional courses"; no user-level marks and no optional developer courses. Then `Personal Website URL` labeled "Your portfolio site URL" (the lesson is "Create your portfolio") and `Post Reflection: Building Your Personal Website` labeled 'Link to the post "Reflection: Building Your Portfolio"'.

**Project:** `Main Contribution Team`; then the eight practical lessons, each a lead naming the lesson exactly as Learn does, with its fields under it:

| Lead (the Learn lesson) | Fields, in order | Controls |
| --- | --- | --- |
| Practical: Duplicate and Explore the WordPress Design Library | Reflection; link; image | textarea; url; image |
| Practical: Set Up a Local WordPress Environment for Design Testing | Tool used; Screenshot | select (WordPress Studio, MAAMP, DevKinsta); image |
| Practical: Change Your Site's Global Styles | Notes; Before Screenshot; After Screenshot | textarea; image; image |
| Practical: Customize with the Style Book | Notes; Screenshot | textarea; image |
| Practical: Compose a Landing Page with Layout Blocks | Note; Screenshot | textarea; image |
| Practical: Apply Custom CSS in the Site Editor | Notes; CSS; Screenshot | textarea; textarea (monospace, `spellcheck="false"`); image |
| Practical: Create and Submit a Custom Block Pattern | Link; Screenshot | url; image |
| Practical: Test Your Site for Accessibility | Part 1 note; Part 1 screenshot; Part 2 note; Part 2 screenshot | textarea; image; textarea; image |

Then `Contribution Project Summary` ("Define and begin developing your contribution project"), the three reflection posts (Choosing Your Team and Project; Your First Contribution; Halfway Check-In) in the posts group, `Slack/GitHub/Blog WordPress Community meetings/discussions`, and `WP event participation URL`.

**Wrap-up:** `Closing post URL` ("Prepare and deliver a wrap-up report").

Labels are written for the student ("Your reflection", "A link to your copy of the library", "A screenshot of your local site"), not copied from the column names; the column names are the keys.

## 5. Two new controls

**`select`:** one `<select>` with an empty first option ("Choose one"), the options named in the field spec, written to Airtable as the option name, an empty choice clears the cell; `clean()` refuses a value outside the list.

**`image`:** a file input accepting PNG, JPEG and WebP, handled by `WPCPM_Image_Upload::accept()` (the shared rules: at most 4000 pixels a side, the type read from the bytes) and `store()` (the Media Library, authored by the student, titled by the field), at most twenty uploads per student per day through `WPCPM_Ceiling`. The site keeps the map field name to attachment ID in user meta `_wpcpm_report_images`, and writes the attachment to Airtable as `array( array( 'url' => <the file's public URL>, 'filename' => <basename> ) )`, the way `WPCPM_Sponsor_Logo` writes a logo: Airtable fetches the file from that URL and keeps its own copy. On the Student Report Card the control shows the site's copy (a thumbnail linked to the full image) with "Replace" (the file input) and "Remove" (deletes the site's attachment and writes an empty list to the cell). Airtable's own attachment URLs expire within hours, so the card never links them; when the base holds a file the site has no copy of (uploaded in Airtable by hand), the control says "1 file on record in Airtable" and offers Replace. A screenshot is a picture of the student's own site, and the Media Library URL is public, which is what lets Airtable fetch it (assumption 6.3).

## 6. Assumptions for the product owner

1. **150 hours.** Learn states the course is 150 hours, so the Designer Track gets a 150-hour target like the long course (the Developer Track has none). If the track is not hours-based, the target becomes 0 like the Developer Track's, one map entry.
2. **The form set is the long course's set plus the practical lessons**, with the user-level marks and the optional developer courses removed because Learn does not list them for designers.
3. **Screenshots are public files.** Each uploaded screenshot is a Media Library file with a public URL (needed for Airtable to fetch it), authored by the student. If screenshots must stay private, Airtable cannot fetch them, and the form would have to ask for a link instead.
4. **No Fillout link** for the track; nothing on the card shows Fillout links any more.

## 7. Syncs every three hours

Today only the students sync runs every three hours (`WPCPM_Students_Sync::EVERY_THREE_HOURS`, thirty minutes past the anchor); the mentors sync, the institutions sync and the sponsors sync run daily at an offset hour. **Corrected on 7 September 2026**, in the fix round of Task 1: this section first said the mentors sync was already on the three-hour recurrence, and it was not - it was scheduled `'daily'` at one hour from whenever it was first registered, which is the reading that made three docblocks untrue before anybody noticed. The three daily syncs move to the same three-hour recurrence, staggered so no two syncs start together: students at +0:30 of the cycle, mentors at +1:00, institutions at +1:30, sponsors at +2:00. The recurrence is registered once, in one place (`WPCPM_Students_Sync::cron_interval()` stays the registrar; the other three syncs reference its constant). On the update, each sync's own `schedule()` reads the recurrence back, clears a daily event and reschedules it on the three-hour recurrence, so the change takes effect without deactivating the plugin and without a settings version deciding whether a cron migration may run. The daily housekeeping crons (the application purge, the agreement discard) stay daily: they are not syncs. The mentor checker stays weekly: it scrapes WordPress.org profiles, not Airtable. The Syncs card on the Administrator Dashboard shows the new cadence through its existing next-run line.
