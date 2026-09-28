# Mentored students whose report record the address join missed

Design note, 28 September 2026, for 1.117.5 on the hotfix line. Follows 1.117.4 (WordPress/WPCredits#226, #222).

## The problem

The Students table and Students Reports are joined by email alone, and by nothing else on purpose: `Students.Students Reports` is empty on every row of both tables. When a student's two rows carry different addresses, or her address sits on a second Students row filed under another institution, the join finds no report for her Students row. Since 1.117.4 the roster shows her as Current because her Students row names a mentor, but her hours, team, website and mentor's name never reach the roster, and nothing tells anyone why. The program team learns of it from a school.

Measured on the live site on 28 September 2026: five such rows at five institutions. Four are a report row that matched no Students row at all (the account sits on the same school's "Not yet in the Students table" list, so the school sees the person twice). One has no same-name report row among the rows the sync reads: its report, if it exists, carries a status outside the tracked ones (1.117.6 corrected the `none` sentence to say so).

## What the site does not do

It does not join by name. A name match that binds the wrong report to a row a school can read would show hours and grades to the wrong institution. The pairing below is a pointer for a program manager to check, stored beside the reconciliation counts and shown only in wp-admin. It never reaches an institution's roster, an export, or the fifth list.

It does not write the address. Email is the join key; a wrong write moves an account. A confirmed one-press copy can follow once the list has proved accurate over a few runs.

## What the sync records

At the end of every students run, in `reconciliation()` next to the existing counts, a new key `mentored_without_reports`: one entry per Students row with a tracked current status, a mentor link, and no joined report row. Each entry carries `students_record`, `name`, `email`, `institution`, `status`, and an `outcome` decided by the reports rows whose name reduces to the same key (`name_key()`: letters and digits only, lowercased, the rule the institution import already uses):

| Outcome | Rule | Extra fields |
| --- | --- | --- |
| `unmatched` | a same-name report row matched no Students row by address; one at the same institution wins over one elsewhere | `reports_record`, `reports_email` |
| `elsewhere` | a same-name report row carries the address of another Students row, joined to it or in conflict with it | `reports_record`, `reports_email`, `joined_to` (the other Students record), `joined_to_institution` |
| `none` | no report row of this name among the rows the sync reads, which are the tracked statuses only | (the extra fields are empty strings) |

Every entry has every key, empty strings where the outcome has nothing to say, so a reader never branches on `isset()`. Entries keep the Students table's order.

## Where it shows

1. **The reconciliation card on the Institutions screen.** A row "Mentored students whose report record the address join missed" with the count, and under the table a list, one item per entry: the name, the institution and status muted, then one sentence per outcome with links to the Airtable rows (`WPCPM_Settings::airtable_record_url()`, the base and table IDs the settings already hold):
   - unmatched: "The Students row carries A; a Students Reports row with this name carries B and matched no Students row. Make the two addresses identical and run the students sync."
   - elsewhere: "The Students row carries A; a Students Reports row with this name carries the address of another Students row, filed under I. One of the two Students rows is a duplicate."
   - none: "The Students row carries A, and no Students Reports row with this name is among the rows the sync reads (the tracked statuses): either the automation has not created the record yet, or the record carries a status the sync does not read."
2. **The run report on the Sync tab of the Students screen.** One notice when the list is not empty: "N students have a mentor but no report record under their address. The reconciliation card on the Institutions screen names each row and the address to fix."

The Administrators Dashboard carries no reconciliation figures today, so nothing is added there.

## What stays as it is

The Student Duplicate Finder groups rows by address, so it cannot see this shape; it is not extended. The Institution Dashboard's fifth list and Current group keep showing the same person twice until the address is fixed; labelling the pair on the school's own page is a later step.

## Tests

`bin/test-students-sync.php`: the #222 fixture already builds the `unmatched` case; the suite adds an `elsewhere` case (a second Students row of the same name under a second institution, carrying the report's address) and a `none` case, asserts the three entries and that the unmentored student is not among them, and asserts the notice. `bin/test-institutions-screen.php`: the fixture's reconciliation block gains the three entries and the card's row and list are asserted, links and addresses included. `bin/test-settings.php`: the URL helper.
