# Developer Track — a third program

**Date:** 26 August 2026
**Component:** `wpcredits-program-manager`
**Status:** approved, not yet implemented

Add the **Developer Track** as a third WordPress Credits program, with its own report form on the
Student Report Card. Airtable already has everything the track needs; the plugin is what assumes
there are only two.

## What Airtable already says

Everything below was read from the base (`appIzQKfwTn5dyPVp`), not assumed.

| | Students (`tbla8GZg5x6NY7aWt`) | Students Reports (`tbljYkkVGbeoaWEtY`) |
| --- | --- | --- |
| Status choice | `Developer Track` | `Developer Track` |
| Report form view | — | `viwox0pWsBBzbfvYH` "Temporal view for dev track", 34 fields |
| Personal link | — | `Dev Track ONLY personal link` |

The base treats this as a third parallel track, laid out exactly like the other two:

| Track | Report-form view | Fields | Fillout link field |
| --- | --- | --- | --- |
| 150-hour | `viwq0RmkdJWQkkoPo` "Temporal view for the normal course report form" | 26 | `Personal link` |
| 50-hour | `viwuHlJ2dqHOM8RhT` "Temporal view for the 50h course" | 13 | `50h personal link` |
| **Developer** | `viwox0pWsBBzbfvYH` "Temporal view for dev track" | **34** (35 before field 27 was deleted — see below) | `Dev Track ONLY personal link` |

**The dev-track field set is a strict superset of the 150-hour one.** Every field the 150h form
asks for is in the dev view, plus nine more. The only field the dev track does not take that another
track does is `Final Contribution Project Report`, which belongs to the 50-hour form.

The view is currently empty — no student holds the status yet — so nothing here can be derived from
records, and nothing is at risk from getting it wrong on the first pass.

## The one architectural decision

`is_50h` is a boolean, in four files. A third track does not fit a boolean.

**Rejected — add `is_dev` beside it.** Two booleans give four states for three tracks. "Both true"
is representable and meaningless, and every future track adds another flag and another way to be
wrong.

**Rejected — store `'track' => '150h'|'50h'|'dev'` on each synced row.** One value, three states,
but rows already stored by earlier syncs have no `track`, so every reader needs a fallback until a
full re-sync has run.

**Chosen — derive the track from the status, and delete the boolean.** Both syncs already store the
raw Airtable status on every row (`program` in the student sync, `status` in the mentor sync), and
`is_50h` is computed from it with `stripos( $status, '50h' )`. The boolean is duplicated data. A
`WPCPM_Program::track( $status )` returning `150h` / `50h` / `dev` / `''` lets every caller derive
it at the point of use.

No migration, no stale rows, one source of truth. A fourth track later is one map entry rather than
a fourth flag.

## Changes

### 1. `WPCPM_Program`

- `const STATUS_DEV = 'Developer Track'`.
- `labels()` gains `'Developer Track' => 'Developer Track'`.
- `courses()` gains `'Developer Track' => 'https://learn.wordpress.org/course/wordpress-credits-developer-track/'`
  (verified: HTTP 200, titled "WordPress Credits Developer Track").
- New `track( $status )` → `'150h' | '50h' | 'dev' | ''`. Empty for a finished state, which is what
  the existing `is_track()` already distinguishes.

The label maps a status to itself. That looks like a redundant entry and is not: `is_track()` tests
membership of the labels map, and that is what gates the feedback surveys and the course button. It
needs a comment saying so, or somebody will tidy it away and silently turn the surveys off for this
track.

Chosen over "WordPress Credits Program — Developer Track" so that screen and base say the same
thing and nobody has to translate between them.

### 2. Settings

- `student_statuses` default gains `'Developer Track'`.
- The field map gains `'report_link_dev' => 'Dev Track ONLY personal link'`.

**The saved setting on the live site needs it too.** The student sync builds its Airtable formula
from `student_statuses`, so until `Developer Track` is in the saved value, no dev-track student is
fetched at all — the feature would look entirely broken while every line of code was correct.

### 3. Both syncs

`WPCPM_Students_Sync` and `WPCPM_Mentors_Sync` each choose the fillout link by track, three ways
instead of two, and stop writing `is_50h` onto the row.

### 4. The report form

`fields( $is_50h )` becomes `fields( $track )` — three call sites in the class, plus seven in
`bin/test-report-form.php` which pass the boolean directly. The dev-track set is the 150-hour set
plus seven fields, grouped by where the Airtable view itself puts them:

| Airtable field | Group | Control |
| --- | --- | --- |
| `Developer Basics: modules completed` | Onboarding | textarea |
| `Developer basics: Optional modules taken` | Onboarding | textarea |
| `Patch Testing: Trac ticket comments` | Onboarding | textarea |
| `Optional: Additional Contribution Project Summary` | Project | textarea |
| `Contributing beyond WP Credits` | Project | textarea |
| `Alumni program: personal email` | Project | email |
| `Alumni program: mentoring opt-in` | Project | checkbox |

Note the inconsistent capitalisation of `Developer Basics:` and `Developer basics:` — that is how
the base spells them, and the keys are what a write has to name, so both are copied exactly.

Nine of the view's fields appeared in neither existing form. Seven become form fields: `Email` and
field 27 are the two that do not — `Email` for the reason below, field 27 because it was a
duplicate and has since been deleted from the base.

`Email` (field 2 of the view) is **not** a form field. It is the account identity and the key both
syncs join on; letting a student edit it would detach their own record from their account.

The `email` control type does not exist in the form yet and needs adding to `render_field()` and
`clean()` — validate with `sanitize_email()` + `is_email()`, empty string clears.

The `Alumni program: mentoring opt-in` checkbox collects consent, so its label must state what is
being agreed to rather than repeating the column name. Proposed: *"Yes, I'm happy to be contacted
about mentoring future WordPress Credits students."*

### 5. Mentor dashboard

The track chip renders `$is_50h ? '50h' : 'sensei'`. It becomes the track key, with `dev` as a third
value and a matching style.

## Fixed along the way

**`Contribution Project Description` does not exist in Airtable.** The report form reads and writes
that name for both existing tracks; the base's field is `Contribution Project Summary`. Verified
two ways: the name appears nowhere in the table's schema, and 0 of 25 sampled 150-hour records carry
it while 13 carry `Contribution Project Summary`.

So a student's project summary is never loaded into the form. What happens on save could not be
established without writing to a real student's record, which was not done — Airtable validates the
record ID before field names, so the safe probe against a non-existent record could not distinguish
the two. Either the whole PATCH is rejected or the field is dropped; in both cases the answer does
not reach the column.

The fix is renaming the key. It belongs with this work because the new
`Optional: Additional Contribution Project Summary` is the same field family, and shipping a form
with both the correct new name and the broken old one beside it would be worse than fixing it.

A sweep of all 25 field names the form uses found this as the only mismatch.

## Testing

Extending the existing suites rather than adding one:

`bin/test-student-program.php`
- `track()` returns `150h` / `50h` / `dev` for the three statuses and `''` for `Graduate`,
  `Dropped out` and an unknown value.
- `label()` and `course_url()` answer for all three; `is_track()` is true for all three.

`bin/test-report-form.php`
- The dev field set is a strict superset of the 150-hour set — asserted as a set relation, so it
  keeps holding when either form changes.
- The eight new fields are present with the right group and control.
- `Email` is absent from the form's fields.
- Every field-name key in all three sets matches a real Airtable field name, from a committed
  fixture of the table's field names. **This is what would have caught
  `Contribution Project Description`**, and it is the check worth having.
- The `email` control cleans a valid address, rejects a malformed one, and clears on empty.

No test asserts the fillout link by reading Airtable; the link choice is a pure function of the
track and is tested as one.

## Resolved after the spec was written

**Field 27, `Post Reflection: Choosing Your Team and Project copy`,** was a URL field immediately
after `Post Reflection: Choosing Your Team and Project`, carrying the suffix Airtable appends when a
field is duplicated. It was left out of the form pending an answer about whether it was a real
second question.

**Celi Garoe confirmed on 28 August 2026 that it was a duplicate, and deleted it from the base.**
The dev-track view is 34 fields now rather than 35, and the seven the form adds are unchanged — it
was never one of them. `bin/fixtures/reports-table-fields.json` was refreshed to the table's 52
remaining field names, which is what now stops anybody adding it back: a field name the base does
not have fails `every dev field name exists in Airtable`.

**Corrected after review.** The three end-of-programme questions were first put in Wrap-up, on the
reading that that is what they sound like. The view asks them in the middle of Project, between
`Post Reflection: Your First Contribution` and `Post Reflection: Halfway Check-In`, and where a
question is asked is the program's decision rather than something to infer from its wording. Moved
in 1.63.0, with the five-field run asserted in `bin/test-report-form.php`.

## Deliberately not in scope

- Any change to the feedback surveys. Adding the labels entry means dev-track students get the same
  three surveys under the same sequential gating as the other tracks, which is the intended
  behaviour and needs no code.
- The 50-hour and 150-hour field sets, beyond the `Contribution Project Summary` rename.
