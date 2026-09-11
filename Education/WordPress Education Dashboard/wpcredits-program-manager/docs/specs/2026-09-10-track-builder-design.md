# Track Builder

**Date:** 10 September 2026
**Component:** `wpcredits-program-manager` 1.99.2, ships in five phases (T1 to T5) from 1.100.0. `version_compare()` orders 1.100.0 after 1.99.2; a string sort would not, which is why this line says so. No `wpcredits-theme` release is needed in any phase (decision 3.10).
**Status:** approved by the product owner on 10 September 2026, after design sessions on 7, 8 and 10 September. Phase T1 shipped as 1.100.0 on 11 September 2026 (`docs/plans/2026-09-10-track-builder-t1.md`); phase T2a is planned in `docs/plans/2026-09-11-track-builder-t2a.md`.
**Depends on:** the Developer Track design of 26 August 2026 and the Designer Track design of 7 September 2026. Their shape - a track is an Airtable `Status` value, one map entry per track, nothing stored per student - is what this module turns into data. It proposes one change to the Learn Link design of 7 September 2026 (section 9).

House rules that apply to every line below: comments explain why and name the decision behind a rule; no em dashes; full product names ("Student Report Card", "Mentor Report Card", "Administrator Dashboard"); US English in every string a person reads; every behavior worth trusting has an assertion in `bin/test-*.php`; `bin/` and `docs/` never ship but are public on the mirror, and `includes/` ships, so nothing personal enters any of them.

Adding a program track is a plugin release today. The Designer Track (1.98.2 to 1.99.2) touched the program map, the Student Report Card form, the settings upgrade, two stylesheets, the fixtures and the guides, and Airtable gained a `Status` choice on two tables, twenty-one columns and two automation changes made by hand. This module lets a Program Administrator do the site's half on the site: create a track (blank, duplicated from another, or started from its Learn course), edit its Student Report Card form question by question, and publish it, with the site creating the Airtable columns the form needs. The four tracks that exist move into the same format, and their hand-written code is deleted only when the product owner says so.

Program Administrators are WordPress Administrators: the plugin grants that role every marker capability plus `wpcpm_manage_program` (`WPCPM_Roles::administrator_caps()`), and every screen below is gated on that capability, like every other wp-admin screen of the plugin.

---

## 1. Settled by the product owner

1. **Program Administrators create new tracks and edit existing ones on the site, and Airtable is kept in step** (7 September 2026, the request).
2. **A track is a post of a private post type. A draft touches nothing; publishing is the one act that writes to Airtable** (7 September 2026, approach A of three).
3. **The site creates the Airtable columns itself**, rather than mapping onto columns somebody made by hand (7 September 2026).
4. **Full parity with the hand-written forms:** every property and control the four tracks use can be authored, rather than a set of lesson templates (7 September 2026).
5. **The four existing tracks move into definitions, and their PHP is deleted only once the new path is confirmed working and the product owner says it can be removed** (7 September 2026).
6. **The module is called Track Builder** (10 September 2026). It was proposed as Track Designer, one word order away from the Designer Track; the product owner kept that name on 7 September 2026 and chose Track Builder on 10 September, so "Designer" on this site names one track and nothing else.
7. **A new track can be duplicated from an existing one** (8 September 2026, flagged important). Columns are shared by default. Rewording a shared question warns and names every other track that writes that column; changing its control, its Airtable type or a select's options gives it a new column of its own, automatically and said in place.
8. **A track connects to a specific Learn course by its link**, as every current track does (8 September 2026).
9. **A track need not count hours** (8 September 2026). A missing target means no target, exactly like the Developer Track's 0, and nothing may require one. `WPCPM_Program::hours_targets()` already says so in its docblock.
10. **A new track reaches the institution import and institution create only once its reports automation includes it** (11 September 2026). Until somebody ticks checklist item 1 (7.3), the institution import form and institution create do not offer a Track Builder track: a student put on it before the automation names its status never gets a report row (2.4). The four built-in tracks are unaffected.

## 2. What the code and the base say

Read on 10 September 2026, against 1.99.2 on `main` and the base `appIzQKfwTn5dyPVp`.

### 2.1 A track in the code

A track is its Airtable status. `WPCPM_Program` holds five maps keyed on it: `labels()`, `courses()`, `hours_targets()`, `track()` (the short key: `150h`, `50h`, `dev`, `design`) and `badge()`, which is derived from `track()` (the 150-hour key paints `sensei`; `paused` and `pending` are two states on no track). Three of the maps are filtered already (`wpcpm_program_labels`, `wpcpm_program_courses`, `wpcpm_program_hours_targets`); `track()` is not, and `badge()` follows it.

The Student Report Card form is `WPCPM_Student_Report_Form::fields( $track )` (line 136): about 580 lines building one array per track, with branches for `50h` (line 541), `design` (559) and `dev` (678), and the 150-hour set for any key it does not know. It is filtered at its end (`wpcpm_report_form_fields`, line 719), and nothing hooks that filter yet. Everything downstream reads the array rather than the branches: `render_body()`, `render_hours()`, `handle_save()`, `handle_image_remove()`, `image_columns()` (which the students sync uses to ask Airtable for the screenshot columns by name) and `WPCPM_Semester_Report::link_labels()`. `image_columns()` and `link_labels()` already find the tracks by walking `labels()` and `track()`, so a track that reaches both maps reaches them too.

Four places know the tracks without asking the map:

| Where | What it holds | What an authored track needs |
| --- | --- | --- |
| `WPCPM_Student_Report_Form::fields()` | the three branches | the filter (3.3) |
| `WPCPM_Settings` | the `student_statuses` default and `status_upgrades()` | publishing appends its status (7.2) |
| `WPCPM_Administrators_Cards::TRACKS` and `render_programs()` | the four tiles of the Administrator Dashboard's Programs running card | both derived from the map (T1) |
| `WPCPM_Institutions::AUTOMATION_STATUSES` | the statuses an Airtable automation watches (2.4) | an accessor that adds confirmed tracks (7.3) |

`WPCPM_Mentors_Sync::link_field()` also names three keys, for the legacy Fillout personal links; any other key falls back to the 150-hour link, which nothing displays any more. It needs nothing.

Two behaviors matter more than they look. **The students sync fetches only the statuses in `student_statuses`**, read from the saved option; `maybe_upgrade()` exists because a new value in a list default never reaches a saved option on its own. And **`WPCPM_Students_Sync::revoke_departed()` treats any linked student its run did not read as departed**: the account is marked inactive and, with "When a student is no longer active" set to revoke, loses the Student role. Taking a track's status out of `student_statuses` would do that to every student on the track at the next run.

### 2.2 The four forms as data

A throwaway probe loaded the form with the stubs `bin/test-report-form.php` uses and passed each track's array through `json_encode()` and back:

| Track | Questions | As JSON | Round trip |
| --- | --- | --- | --- |
| 150h | 24 | 3,883 bytes | identical |
| 50h | 11 | 1,859 bytes | identical |
| dev | 31 | 5,225 bytes | identical |
| design | 49 | 7,994 bytes | identical |

`===` held for all four, key order and value types included, with the three column names that carry the typographic apostrophe in `Site’s`. No value is an object or a closure, and nothing in `fields()` reads a setting, a user or the clock. **So a definition can hold exactly what `fields()` returns, and the migration's proof can be `===`.**

The 115 questions use ten controls: number 40, url 28, textarea 23, image 10, text 4, team 4, email 2, checkbox 2, richtext 1, select 1. They are the ten `render_field()` draws; `WPCPM_Field_Value` also validates `date` and `rating`, which this form never draws. The questions use thirteen properties: `label`, `type` and `group` on all 115; `step`, `min` and `max` on the 40 numbers; `help` 29; `row` 25; `lead` 21; `subgroup` 10; `stack` 8; `maxlength` 4; `required`, `options` and `mono` once each. `note` is drawn by `render_body()` and used by none of the four today.

**Sharing is the norm.** The four forms name 53 distinct columns. 28 of them are asked by two or more tracks, and 10 by all four: `Hours`, `WordPress Profile`, `Slack Name`, the grades for open source basics, decision making and conflict resolution, `Personal Website URL`, `Main Contribution Team`, `Contribution Project Summary` and the community meetings question.

### 2.3 Students Reports

`Students Reports` (`tbljYkkVGbeoaWEtY`) has **73 fields**, and Airtable allows **500 per table** on every plan. The 53 form columns are all in `bin/fixtures/reports-table-fields.json`. The other 20 are the record's identity and program columns, plus five columns renamed `(DELETED)`, which is what removing a column looks like in this base. At the Designer Track's twenty-one new columns, 427 fields of headroom is about twenty tracks that share nothing; tracks share more than half their columns, so the real margin is wider, but it is finite and nothing gives it back.

Both `Status` fields carry the tracks as choices, with different choice sets and colors: `Students Reports` has twelve (Developer Track `tealLight2`, Designer Track `pinkLight1`), and the `Students` table, where people set a student's status, has fifteen (its Designer Track is `pinkBright`).

### 2.4 What Airtable does on its own

A track is also two kinds of automation, read with the Airtable connector:

- **`Add students to Students Reports and Feedback`** (`wflXg1xFuiCSG0pXZ`, deployed) creates a student's `Students Reports` row and `Feedback` row once a `Students` row has a name, an email, an institution, a mentor and a `Status` that is one of **five** choices: In Sensei, In Sensei Self-onboarding, In Sensei 50h, Developer Track and Designer Track. It copies the status across by name. **A student on a track this condition does not name never gets a report row, so never appears on the site at all.**
- **Each track has its own welcome email**, triggered by the `Students Reports` `Status`: `Email - [EN] - Student Welcome Email`, `Email - [EN] - 50h Student Welcome Email`, `Email - [EN] - Student Welcome Email - Developer Track` and `Email - [EN] - Design Track Student Welcome Email`, all deployed.

`WPCPM_Institutions::AUTOMATION_STATUSES` mirrors the first automation's condition for the Institutions Link control, and lists four statuses: the Designer Track was added to the automation in the base but not to the constant. That is fixed on its own, outside this module (open item 4), and it is also the clearest argument for 7.3.

### 2.5 What the Airtable API allows

- **Creating a field** (`POST /v0/meta/bases/{base}/tables/{table}/fields`) needs the `schema.bases:write` scope **and a token whose owner holds the base creator role** on the base.
- **Updating a field** changes its name, its description and its "type-specific options"; the documentation shows a formula and says nothing about adding a choice to a single select. Unverified: open item 1.
- **No endpoint deletes a field or removes a select choice.** What the site creates stays until somebody removes it by hand in Airtable.
- **No scope a token can hold reads or edits automations.**
- `typecast` on a record write creates a missing select choice, but it is not a route for this module: the plugin never sends `typecast`, by rule (the `WPCPM_Airtable::create_records()` docblock: one typo would add an option rather than fail), and the site never writes `Status`, which is for people to set.

### 2.6 What Learn says

Every track course resolves from its link. `GET https://learn.wordpress.org/wp-json/wp/v2/courses?slug=<slug>` returns the course's post ID and title (the 150-hour course is 297853, the 50-hour 322343, the Developer Track 402893, the Designer Track 403425), and the public, unauthenticated `sensei-internal/v1/course-structure/<id>` returns its modules and lessons with their IDs. **All four courses have exactly three modules, Onboarding, Project and Wrap-up, which are the form's own `groups()`**, with `hours` beside them for the hours question.

Only the Designer Track's form is organized by lesson. Of its 17 headings (`lead` and `subgroup` values), 11 match a lesson title exactly once case and apostrophes are folded. The other three forms match 1 of 5, 0 of 1 and 1 of 8, because their headings are the program's own ("Enter your final grade, 0 to 100", "Your reflection posts").

## 3. The architecture, in ten decisions

**1. A Tool, shown as a Module.** `WPCPM_Track_Builder extends WPCPM_Tool` and is added to `WPCPM_Tools::all()`. The admin menu already lists the Tools under a submenu it calls "Modules", because that is what they are to somebody running the program, so the screen is **WPCredits Program → Modules → Track Builder**: the product owner's own word. It is gated on `wpcpm_manage_program`.

**2. Two stores: one people edit, one the site runs on.** Definitions are `wpcpm_track` posts: `public`, `show_ui` and `show_in_rest` false, and a capability type nobody is granted, so no generic post screen can ever reach one (the `WPCPM_Institution_Request` pattern); `supports` title and revisions. The definition is the post meta `_wpcpm_track_definition`, registered with `revisions_enabled` (WordPress 6.4; the plugin requires 6.5), so every saved change is a revision. Creating a track stores its definition through the same save, so the history begins with the track as it was created. **The live site never reads a post.** Publishing compiles every live track into `wpcpm_tracks`, an autoloaded option of a few hundred bytes a track (status, key, label, course, hours, hue, source), and one `wpcpm_track_fields_<key>` per track, not autoloaded, holding its form (the Designer Track's is 8 KB). Two reasons. `WPCPM_Program::labels()` runs for every row of every roster, which rules out a query. And editing a published track must not change the form students see until somebody publishes the change.

**3. The definition is the spec.** A question is exactly a `fields()` entry, keyed by its column, plus three keys the form never sees: `airtable_type`, `learn_lesson_id` and `why`. Compiling strips those three. The runtime is then the code that exists: `render_body()`, `WPCPM_Field_Value`, `handle_save()`, the screenshot store and `link_labels()`, all unchanged.

**4. The map stays the only map.** The module adds callbacks to the four filters that exist (`wpcpm_program_labels`, `wpcpm_program_courses`, `wpcpm_program_hours_targets`, `wpcpm_report_form_fields`) and to two new ones in `WPCPM_Program`: `wpcpm_program_tracks` inside `track()`, and `wpcpm_program_course_ids` on a new `course_ids()` map with its `course_id()` accessor, in the shape of `courses()` and `course_url()`, which Learn Link needs (section 9). Nothing else in the plugin learns that a module exists.

**5. Built-in or definition, per track, as a switch.** This is the migration gate. The four migrated tracks are definitions marked built-in, and each runs from its PHP until a Program Administrator flips it, which is allowed only while the definition is identical to the PHP's output at that moment. Flipping back is allowed while the PHP exists and the definition has not been edited since the flip. **A test alone cannot deliver "confirmed working in the new way"; only the live site running on the definitions can.** The product owner's word then deletes the PHP (T5).

**6. The hard-coded places ask the map.** T1 derives `WPCPM_Administrators_Cards::TRACKS` and its tile names from `WPCPM_Program`; publishing appends to `student_statuses`; the Institutions guard reads an accessor (7.3).

**7. Publishing is a job with a preflight and a fixed order** (section 7), and it is the only writer of Airtable's schema and of the runtime stores.

**8. What no token can do is a checklist a person confirms, and the site checks the symptom.** The two automation changes (2.4) are listed on the publish screen, ticked by whoever made them, logged with who and when, and checked afterwards by counting rows (7.4).

**9. Nothing is deleted.** No column, no choice, no status from `student_statuses`, and no track that was ever published: it is unpublished and kept, because it is the record of what was created in the base. Unpublishing is refused while any synced student holds the status.

**10. Badge color from a fixed palette.** A track's chip hue is one of a fixed set of named hues in code, never a color typed on a screen, and defaults to the family of the color Airtable gives its `Status` choice: the Developer Track's teal was chosen for exactly that reason, so a mentor sees the color they see in the base. Slate and amber stay reserved for Paused and Pending graduation. The module prints one `.wpcpm-badge--<key>` rule per authored track through `wp_add_inline_style()` on the plugin's dashboard stylesheet, in the shape that sheet uses (the hue at 0.12 for the background and 0.35 for the border). No theme release is needed: the theme's `.wpcpm-badge` sets only the shape and leaves the Developer and Designer Track chips to the plugin's tint, which is the rule an authored chip gets, and `bin/check-selectors.php` reads the theme's selectors against the source rather than the other way round, so a runtime key cannot fail it. The four built-in tracks keep their hand-written rules.

## 4. The definition

### 4.1 The track

| Property | Rule |
| --- | --- |
| `status` | The Airtable `Status` value: trimmed, one line, at most 100 characters, unique among tracks. Never one of the statuses the program already reads with another meaning, case folded: anything in "Past students" (Graduate and the rest), and the two states on no track, Paused and Pending graduation. A status a manager has already added to "Currently mentoring" for the new track is not refused. When the value already exists as a choice in Airtable, the preflight says so and how many rows hold it. **Locked after the first publish**: every map, both syncs and `student_statuses` key on it. |
| `key` | `a-z`, `0-9` and hyphens, 2 to 20 characters, unique. Never `150h`, `50h`, `dev`, `design`, `sensei`, `paused` or `pending`, which `badge()` already emits. Locked after the first publish. |
| `label` | What every screen calls the track. |
| `course_url` | Optional. A `learn.wordpress.org/course/<slug>/` address, resolved on save to `learn_course_id` and the course title. A course that does not resolve warns; it does not block. |
| `hours_target` | Optional, a whole number of hours. Empty means no target (decision 1.9). |
| `hue` | One of the palette's names (3.10). |
| `schema_version` | 1. |

### 4.2 A question

The column name is the key, verbatim and never trimmed: `key()` hashes it, and an Airtable name can end in a space. One column, one question per track, because the same column in two boxes is the bug the contribution teams had. **Never a column the syncs own:** `Name`, `Email`, `Status`, `Mentor`, `Educational institution`, the two internship dates and the three legacy personal-link columns (the `report_*` entries of `WPCPM_Mentors_Sync::fields()` that are not questions). `handle_save()` writes the columns the form names, so a question bound to `Status` would let a student move themselves to another track; found while planning T1 (10 September 2026). The preflight (7.1) also refuses, from the schema, a computed column (formula, lookup, rollup, count) and any link column but `Main Contribution Team`.

| Property | Used by | Meaning |
| --- | --- | --- |
| `type` | all | One of the ten controls. |
| `label` | all | What the student reads, never copied from the column name. |
| `group` | all | `hours`, `onboarding`, `project` or `wrapup`. |
| `help` | any | A hint under the control, which names it for a screen reader. |
| `lead` | any | A heading before this question: a Learn lesson's name, or the program's own. |
| `subgroup` | any | The same heading treatment, for a run. |
| `note` | any | A sentence after the run ("complete one of the following"). |
| `row` | any | Questions with the same row slug sit side by side. |
| `stack` | inside a row | Shares one column of the pair, in order. |
| `required` | any | The Required mark. Never a browser `required`: a student saves this form a dozen times a term. |
| `min`, `max`, `step` | number | Required for a number. |
| `maxlength` | text, textarea | Optional. |
| `mono` | textarea | Monospace and no spellcheck, for code. |
| `options` | select | The choices, which must match the Airtable column's. |
| `hide_from_institution` | any | Kept off everything an institution reads (section 10). Always on for `email`. |
| `airtable_type` | all | The column's Airtable type, recorded from the schema or chosen for a new column. Not part of the form. |
| `learn_lesson_id` | any | The Learn lesson this question reports on. Not part of the form. |
| `why` | any | A developer's note that no student sees: why a column name looks like a slip. Not part of the form. |

A new column's Airtable type follows its control: text is `singleLineText`, textarea `multilineText`, richtext `richText`, url `url`, email `email`, number `number` with the precision its step implies, checkbox `checkbox` (the check icon in `greenBright`, as the base's existing one), select `singleSelect` with the options as its choices, and image `multipleAttachments`. **`team` never gets a new column.** The control is written for `Main Contribution Team`, the one column all four tracks share: its choices are the rows of `Contribution areas`, and the base's link columns each carry a reverse field in the table they point at, so a second link column would reach into a second table.

### 4.3 Storage

`update_post_meta()` unslashes what it is given, so the JSON is stored as `wp_slash( wp_json_encode( $definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) )`. A label holding a backslash, a quote or U+2019 must come back byte for byte, through a revision as well, and a suite says so.

## 5. Where a track starts

**Blank.** A status, a key and a label, and an empty form.

**A duplicate** (decision 1.7). Every question copies, `why` included. Identity does not: `status`, `key`, `label` and the course start empty, the hue starts as the first one no live track uses, and the copy is a draft that records `duplicated_from`. Every question shares its column. On the copy:

- Rewording a shared question (its label, lead, subgroup, help or note) keeps the column, and warns in place, naming every other track that writes it.
- Changing its `type`, its `airtable_type` or its `options` gives it a column of its own, named `<column> - <key>` and editable until publish, and the question says so where it sits. A different type is a different column.
- A line at the top of the editor, from the same diff the preflight computes, says either "This track needs no new Airtable columns" or "Publishing will create 3 columns".
- Choosing another Learn course matches `learn_lesson_id` again, by exact title, against the new course, and clears and lists the rest.

A duplicate whose questions all share columns needs no column created at all. Its Airtable side is the two `Status` choices and the two automation changes (7.3), so it can be published from T2 on, before any schema token exists.

**From a Learn course link.** The link resolves to the course (2.6), and the editor shows the course's lessons, module by module, beside the form, marking the lessons that already have questions. "Add a question under this lesson" starts a question in the lesson's group, with the lesson's title as its `lead` and its `learn_lesson_id` set. Nothing enters the form without a question, because the form has no heading of its own: `lead` is a property of the first question after it. This is how the Designer Track's form was built by hand, lesson by lesson.

## 6. The screens

All are in wp-admin, all are gated on `wpcpm_manage_program`, and every handler runs the plugin's capability and nonce check before anything else. Forms post and come back, like the Student Report Card's module arrows; JavaScript only makes a move happen in place, and puts it back when the server refuses (`assets/js/modules.js` is the pattern).

**The track list.** One row per track: name, status, key chip, source (built-in or definition), state (draft, published, or "Unpublished changes"), how many students are on it now, its Learn course, who published it last and when, and the Airtable steps still outstanding. New track (blank, or from a Learn course link), Duplicate, Edit, and for a built-in track the equivalence line and the switch.

**The track editor.** The track's properties, then the questions by group, each a disclosure holding its properties. Add a question (choosing its control), move it up or down, remove it (the column and what students wrote in it stay in Airtable, and the confirmation says so). The schema line, the shared-column warnings and the fork notices. **Preview** draws the draft's form as a student sees it, with empty answers and no student's record, through the same renderer. A built-in track that still runs from its PHP is read-only here: it can be duplicated but not edited, or its equivalence would mean nothing.

**History.** The revisions as a question-level diff (added, removed, moved, and which properties changed), and the publish log.

**Publish.** The preflight, the checklist, and the track's name typed to confirm whenever anything will be created in Airtable.

## 7. Publishing

### 7.1 Preflight

Read-only, with the everyday token (`schema.bases:read`):

- Every question's column exists with the recorded type, or will be created, with its Airtable type.
- The table's field count after publishing, against 500: refused over 500, since the API would refuse part-way through and leave half a track, and a warning past 450.
- The `Status` choice on both tables: present, missing, or nearly present (differing by case, spacing or apostrophe, which the syncs would never match).
- `status` and `key` unique; the Learn course reachable (a warning only).
- For a built-in track: equivalence with its PHP.

### 7.2 The order

One run at a time, behind a lock like the syncs'. Every step that lands is recorded on the post, and a failed step stops with Airtable's own message, so pressing Publish again resumes at the first step not recorded:

1. Create the columns, one at a time. A refusal because the name is taken is read again: a column of that name with the right type means somebody made it by hand, and it counts as landed.
2. Compile the runtime stores, and append the status to `student_statuses` through `WPCPM_Settings`, which never removes one.
3. Add the `Status` choice to `Students Reports`, then to `Students`, when the schema token can (open item 1); otherwise both are checklist items.
4. Mark the post published and write the log: who, when, the columns created and the choices added.

The choices come last because they are what lets somebody put a student on the track in the base, and by then everything that student needs already exists.

**The schema token is a second, optional setting**, masked and never sent back to the browser like the Airtable token, and used by steps 1 and 3 and nothing else. The everyday token stays at records plus schema read, so the syncs, which run every three hours, never hold the right to change the base's structure. Without a schema token, step 1 becomes a checklist of the exact columns (name, type, options) for somebody to create, and Publish waits until the preflight finds them. A 403 names both requirements: the `schema.bases:write` scope, and a token owner who holds the base creator role.

### 7.3 The checklist

What the site cannot do is listed with the exact values to use, and each item is ticked by whoever did it and logged:

1. Add the status to the condition of `Add students to Students Reports and Feedback`. Without it, no student on the track gets a report row.
2. Create the track's welcome email automation, as each of the four tracks has one.
3. The two `Status` choices, whenever step 3 cannot add them.

Ticking item 1 adds the status to `WPCPM_Institutions::automation_statuses()`, which is the pinned constant plus every track whose item 1 is ticked. That is the list the Link control's guard reads, and it is the list that went stale for the Designer Track.

An unticked item never blocks publishing, because the site cannot see automations either way, but the track list counts it until it is ticked.

### 7.4 Verify against Airtable

Any time, read-only: every column the definition names still exists with its type (a column renamed in the base otherwise surfaces as a silently empty answer), both `Status` choices exist exactly, and the symptom of a missed item 1 is counted with only the `Status` field requested: "4 students on this track in Students, 0 report rows".

### 7.5 Unpublishing

Refused while any synced student holds the status, with the count. Otherwise the track leaves the runtime stores and the post returns to draft. Airtable is not touched, and the status stays in `student_statuses`: removing it is a manager's decision in Settings, where "Currently mentoring" is edited, because `revoke_departed()` would take the Student role from everybody on the track.

## 8. The migration and the removal gate

- **`builtin_fields( $track )`** holds today's branches, and `fields()` becomes `apply_filters( 'wpcpm_report_form_fields', self::builtin_fields( $track ), $track )`. A pure move: `bin/test-report-form.php` passes unchanged.
- The three alumni specs gain `hide_from_institution`, in the PHP too, so the equivalence covers the flag (section 10).
- **`wp wpcredits seed_tracks`** writes the four definitions from `builtin_fields()` and the `WPCPM_Program` maps, with each column's `airtable_type` from the schema (or from the fixture, offline), the four course IDs, `learn_lesson_id` only where a heading matches a lesson exactly (2.6), and `why` written by hand for the columns whose names look like slips. At least these: the three `Site’s` columns (U+2019), `Duplicate & Explore WP Design Library` and its lower-case `link` and `image`, `Advance WordPress User - final grade` (the label says Advanced), `MAAMP` among the tool choices, the two portfolio relabels, and the community meetings question that moves on the Developer Track. The result is committed as `includes/tracks/seeds/<key>.json`, which is also what a fresh install seeds from when it has no tracks. (T2a makes this two commands, because a site must never write into its own plugin folder: `bin/build-seeds.php` writes the files offline, and `wp wpcredits seed-tracks` creates the four definitions on a site from them, as a site does once on its own.)
- Publishing a seeded track creates nothing, because every column and both choices exist. **An empty preflight is the proof that the definitions match the base.**
- **`bin/test-track-definitions.php`** asserts `builtin_fields( $key ) ===` the compiled seed for all four, and the Track Builder screen shows the same comparison live for each built-in track, beside its switch (3.5).
- **T5, on the product owner's word only:** the three branches, `builtin_fields()` and the four tracks' rows in `labels()`, `courses()`, `hours_targets()`, `track()` and `course_ids()` go (the `STATUS_*` constants stay, because other code names them), the built-in marks are cleared, and the suite compares the compiled stores with the seed files.

## 9. Learn Link

Learn Link's `learn_courses` setting is global ("comma-separated course IDs, the track course first") and must equal the whitelist Learn holds for the client. With a course per track that cannot hold. The proposal, for the Learn Link design and not made here: `learn_courses` keeps only the foundational courses every student takes, a student's track course comes from `WPCPM_Program::course_id()` for their status, and Learn's whitelist is the union of both. `learn_lesson_id` is recorded so that Learn Link can one day show a lesson's progress beside the question that reports on it; nothing in this module renders it.

## 10. Privacy, in one place

- `for_institution()` drops every `email` question and the three `ALUMNI_FIELDS` by name. From T2 it reads `hide_from_institution` as well, and the constant stays until T5, with its suite holding the list to the promise.
- A new question is visible to institutions unless it is marked. That is today's rule for everything but email and the alumni answers, because an institution sends its students to do this work.
- The verify count requests the `Status` field and nothing else; no name or address is read.
- `includes/tracks/seeds/*.json` ships and is public on the mirror. The four forms hold nothing personal, and `bin/test-fixtures.php` already walks `includes/` for refused names.
- Definitions are content, not code: an authored label is shown as written and is not in the translation template. The plugin ships no translations (`languages/` holds only the `.pot`), so the four tracks lose nothing visible when they leave `__()`.

## 11. Tests

Suites in the house shape, standalone PHP with stubs:

- **T1:** `bin/test-track-definition.php` covers every validation rule in 4.1 and 4.2; the storage round trip (a backslash, a quote and U+2019, through a revision); compiling; an authored track through all five maps and `course_id()`; and the badge rule, printed only for authored keys and only from the palette. `bin/test-report-form.php` holds every rule to the four hand-written forms: each rule accepts all 115 of their questions. `bin/test-student-program.php` gains a track added through `wpcpm_program_tracks`, which gets its key and its badge. The Programs running card shows an authored track's tile.
- **T2:** `bin/test-track-definitions.php` (3.5 and section 8); the switch refused when the definition is not equivalent, and the way back refused after an edit; a duplicate copying no identity; a published track refusing to unpublish while a student holds its status; `student_statuses` appended to and never trimmed.
- **T3:** the editor's handlers (capability and nonce first; move; remove; a fork on a type change and on new options; a warning on rewording; the lesson match on a course change), the preview and the revision diff.
- **T4:** the pipeline against a fake Airtable client: the order, resuming after a failure at every step, the taken-name re-read, the refusal over 500, both 403 messages, the checklist feeding `automation_statuses()`, and no schema call ever made with the everyday token.

## 12. Phases

| Phase | What ships | In Airtable |
| --- | --- | --- |
| **T1**, 1.100.0 | The post type, the definition and its validation, the compiled stores, `wpcpm_program_tracks` and `course_id()`, the module's callbacks, and the Programs running card and `automation_statuses()` asking the map. | Nothing |
| **T2a**, 1.101.0 | `builtin_fields()`, the four seeds and `wp wpcredits seed-tracks`, the equivalence check and the switch, the published copy, a compile that checks what it compiles, and publishing and unpublishing, all without a screen (`docs/plans/2026-09-11-track-builder-t2a.md`). | Nothing |
| **T2b**, 1.102.0 | The Track Builder screen: the track list, track properties, duplication, the read-only preflight, verify, the checklist, and publishing of tracks whose columns all exist. | Reads only. A person adds the choices and changes the automations. |
| **T3** | The question editor at full parity, forks, warnings, Learn lessons, preview and history, and the Track Builder section of `docs/sections/32-admin-tools.md` (followed by `bin/build-docs.php`). | Nothing new |
| **T4** | The schema token, column creation, and choice creation if open item 1 allows it. | Writes columns, and perhaps choices |
| **T5** | The PHP removed, on the product owner's word. T5 keeps a lock for the four switched tracks: once their PHP rows go, `builtin_key()` answers `''` and `validate_key()` refuses their reserved keys, so without the lock every compile would leave them out (the final review of T2a). | Nothing |

Each phase is planned on its own in `docs/plans/`, as the Sponsors phases were, and each bumps the version in every place, gets a changelog entry and a zip, and goes to the mirror.

## 13. Open items

1. **Can `update_field` add a choice to a single select?** Test it against a scratch base, never `appIzQKfwTn5dyPVp`, where a test column could not be removed. Until the answer is yes, the choices stay on the checklist.
2. **Learn Link's `learn_courses`** (section 9): the product owner decides, and the Learn Link design is amended before the October cohort starts.
3. **Who mints the schema token.** It must belong to somebody who holds the base creator role on the base.
4. **`AUTOMATION_STATUSES` lacks the Designer Track** (2.4): a one-line fix with its suite, in a release of its own, ahead of T1.
5. **What `compile()` reads (decision 3.2).** Decision 3.2 asks for two things at once: publishing compiles every live track, and editing a published track must not change the form students see until somebody publishes the change. `compile()` builds each published track from its latest saved definition, so the two hold together only once publishing records the definition it published (a `_wpcpm_track_published` meta, or the ID of the revision that was published) and `compile()` reads that record instead. T2 settles which before anything calls `compile()`, since publishing, unpublishing (7.5), the switch (3.5) and ticking checklist item 1 (7.3) all recompile. The same record gives the track list an "Unpublished changes" state. **Settled in T2a:** the post meta `_wpcpm_track_published`, the definition as published, stored the way the definition is; a revision's ID was set aside because a site may cap how many revisions it keeps.
6. **`compile()` checks what it compiles.** Every compile rebuilds every published track, including one whose surroundings changed after it was published (a renamed sync column, a status added to the past statuses), and the report form's save writes every column a compiled form names. So `compile()` runs `validate()` on each definition and leaves out any that fails, and any later track that claims a status or key already compiled (the first by post ID keeps it), and the track list shows what was left out. A built-in track is checked with `locked` set to its own status and key, which no other track gets. This is T2's first task; 1.100.0 ships with nothing that calls `compile()`. **Settled in T2a:** each published copy is checked against the site as its PHP describes it, the compiled tracks suspended, and the copies compiled before it, in post ID order; what is left out is recorded in `wpcpm_tracks_skipped`. A track's own status leaves the context only when the track is locked to it, which closes a hole in T1's `validation_context()`: a new track could take one of the four built-in statuses under a key of its own.
7. **`maxlength` on a textarea (4.2).** Section 4.2 lets a textarea carry `maxlength`, and `validate()` accepts it, but the live form applies `maxlength` to text inputs only and caps every textarea at its general limit (`WPCPM_Field_Value`), and decision 3.3 keeps that runtime as it is. The product owner decides before T3's editor offers the setting: narrow the rule to text, which the four forms already follow, or have the form honor it on a textarea.

## 14. Deliberately not in scope

- Airtable automations: the site cannot see them, and they email students.
- Deleting or renaming anything in Airtable.
- Editing Learn courses: the site links to them.
- Per-track feedback surveys and per-track groups: the surveys follow `is_track()` for every track, and every course has the same three modules.
- Tables other than `Students Reports`.
- Translating authored strings.
