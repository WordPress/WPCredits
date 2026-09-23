# Sponsors on the site: design

**Date:** 23 September 2026. **Product owner:** Maciej Pilarski. **Ships as:** plugin 1.111.0, no theme change.

The Sponsors module (spec `2026-09-04-sponsors-module-design.md`, six phases, 1.93.0 to 1.98.1) keeps a sponsor's offers, codes, claims, posts, agreement, logo, member accounts and expressions of interest on the site, and reads the sponsor's identity from the Airtable Sponsors table every three hours while writing six things back to it. This document moves the identity onto the site too, so that a sponsor is one record in one place, and nothing about a sponsor is read from or written to Airtable any more.

## 1. Settled by the product owner (23 September 2026)

1. **The site is the record for sponsors, starting with this release.** The program's direction is to bring its records onto wordpresseducation.org; sponsors go first because everything but their identity already lives there. Students, mentors, institutions and feedback stay on Airtable for now, and nothing in this document touches them.
2. **The site's application form is the only intake.** The Airtable interest form and the seven Airtable automations that answered it are retired by the program team; the plugin does not touch them.
3. **The Airtable Sponsors table becomes an archive.** It is renamed by hand, never written again, and the plugin stops reading it. Nothing is deleted there.
4. **Two other tools read that table today** (the WPCredits Tracker plugin and the Credits Monthly Report, both in the same GitHub repository). They switch to a public feed the site publishes, in their own releases, later. Until then they keep reading the archive, whose numbers stop moving.
5. **The read contract stays.** `WPCPM_Sponsors_Index` keeps answering the twenty sponsor classes exactly as it does; only its storage changes.

## 2. What the code and the base say

Read on 23 September 2026.

- **The index** (`wpcpm_sponsors_index`, written by `WPCPM_Sponsors_Sync` every three hours, two hours into the cycle) holds 31 rows: 16 Approved, 8 Not Moving Forward, 4 Paused, 1 In review, 2 blank. Three Approved sponsors have a dashboard account, two have a live offer (three live offers in all), no sponsor post is published. Every row is keyed by its Airtable record id, and so is everything the site keeps about a sponsor: offers (`WPCPM_Sponsor_Offers::create( $record, ... )`), the posts' `_wpcpm_sponsor` meta, the posting flag option, the member accounts' `wpcpm_sponsor_record_id` meta, the logo option, the agreement options, the claims. Forty-two places in the sponsor classes check that a key is an Airtable record id through `WPCPM_Mentors_Sync::is_record_id()` or `WPCPM_Airtable::is_record_id()` (`/^rec[A-Za-z0-9]{14}$/`).
- **Six writes go to the base:** the profile save (`WPCPM_Sponsor_Profile`, eight fields), the primary offer's three fields (`WPCPM_Sponsor_Offers::mirror()`), the logo (`WPCPM_Sponsor_Logo`), each expression of interest appended to `Sponsorship interests` (`WPCPM_Sponsor_Interests`), the three agreement columns (`WPCPM_Sponsor_Agreement`), and the `Dashboard account` flag (`WPCPM_Sponsors::mark_dashboard_account()`). A seventh is the record the approval creates (`WPCPM_Sponsor_Approval::approve()`, step 1 of 8). Each write sends no `typecast`, so a choice spelled differently is a 422 for the whole record.
- **The assigned manager** is a Team Members record (`Person of contact`), resolved at read time by `WPCPM_Sponsors_Index::manager_of()` from the `wpcpm_team_members` option the sponsors sync writes. Nothing else writes that option; the Countries and Institutions code resolve their own contacts.
- **The mentor link** comes from two places: the sponsor row's `mentors` (the Sponsors table's `Mentors` link) and the mentors sync's `sponsorship()` rows, whose `company` is the Mentors table's `Sponsor Company Name` link. `WPCPM_Sponsor_Mentors` shows the first and counts the second as "others". The mentor's own `Sponsored` and `Wants to be in the looking for sponsors list` answers are mentor facts and stay with the mentors sync.
- **The other readers.** `wpcredits-tracker` (`class-wpct-sync.php`) counts Approved sponsors for the public dashboard. `credits-monthly-report` counts sponsor enquiries and approvals by the month the record was created, plus sponsored mentors from the Mentors table.
- **Seven deployed Airtable automations** ("Sponsors 1.1" to "Sponsors 5") send an email when a Sponsors record matches a `Sponsorship options` value and the mentor-type answers. They answer the old interest form. The site's application flow (1.97.0) sends its own acknowledgement and decisions.
- **Settings** hold `sponsors_table` and `sponsors_name_field`; the fixture `bin/fixtures/sponsors-table-fields.json` pins the table's 32 fields, and `bin/test-fixtures.php` and `bin/test-sponsors-sync.php` read it.

## 3. The architecture, in ten decisions

1. **A private post type, `wpcpm_sponsor`,** one post per company. The title is the company name, the slug is the company's public handle, the meta holds every field of section 4. Not public, not searchable, no front-end URL of its own.
2. **`WPCPM_Sponsors_Index` keeps its read contract** (`rows()`, `row()`, `has()`, `approved()`, `status_counts()`, `manager_of()`, `display_logo()` and the rest) over the post type, with one request-level cache built from a single query. `write()`, `patch()` and `insert()` become the store's save path, called by the Sponsors screen, the approval and the migration, and by nothing else.
3. **Keys do not change.** A migrated sponsor keeps its Airtable record id as its key for ever, so offers, codes, claims, posts, flags, members, logos and agreements need no rekeying. A new sponsor gets a key of the same length minted by the site: `spo` followed by fourteen characters from the same alphabet. One check, `WPCPM_Sponsors_Index::is_key()`, accepts both shapes and replaces every record-id check in the sponsor classes; the Airtable check stays where it belongs, in the syncs that still talk to Airtable.
4. **The Sponsors screen owns identity.** Each sponsor gets an edit form there for the fields a manager owns (section 5), and an "Add a sponsor" form for a company that never applied on the site. Status changes keep the five words the base used, so history and the Administrator Dashboard read the same.
5. **The assigned manager is a site user** with the administrator access level, chosen from a list. The booking link a sponsor sees comes from a new field on that user's profile, `wpcpm_booking_url`, which the migration seeds once from the held Team Members option by email address. `manager_of()` returns the same three things it returns today.
6. **The mentor link lives on the sponsor record** as a list of mentor record ids, edited with a picker over the mentors index (name and WordPress.org profile, active mentors first). The mentors sync stops reading `Sponsor Company Name`; `WPCPM_Mentors_Sync::sponsorship()` keeps `sponsored` and `wants`, and its `company` becomes an empty list, so "others" is always zero and the card stops showing it.
7. **The migration is idempotent and counted.** On the first request after the upgrade, every row of the held index becomes a post, the logo record and the copied attachment are kept, the manager is mapped by address, the mentor list is copied, and a sponsor whose row carries offer text but no primary offer gets its primary offer seeded in draft, as the approval does. The index option is removed only when the post count equals the row count; until then, and on any mismatch, the store answers from the option and the Sponsors screen shows the count with a "Run again" button. Every run is logged.
8. **A public feed replaces the table for the other readers.** `GET /wp-json/wpcpm/v1/sponsors` answers with the Approved sponsors and monthly application counts (section 6), cached for fifteen minutes, metered through `WPCPM_Ceiling` like every public route, and holding no contact data.
9. **What goes:** `WPCPM_Sponsors_Sync` and its two cron hooks, the six write-backs and the approval's record creation, the `sponsors_table` and `sponsors_name_field` settings, the Team Members option and its writer, the fixture, the sync's row on the Administrator Dashboard's syncs card, and the sync paragraph in the program managers' guide.
10. **Nothing is announced, created or changed in Airtable by the plugin.** The rename and the automations are the program team's, and the readme names them as the two manual steps.

## 4. The record

| Field | Owner | Notes |
| --- | --- | --- |
| key | the site | the Airtable record id for a migrated sponsor, `spo` + 14 for a new one; never shown to a sponsor |
| name | manager | the post title; also the application's Company Name |
| slug | the site | from the name, unique, kept stable once set |
| website | sponsor or manager | as today |
| status | manager | `Approved`, `In review`, `Paused`, `Not Moving Forward`, `Rejected`, spelled as the base spelled them |
| option, support, product type | sponsor or manager | the sponsorship shape the form and the profile card already hold |
| anything | sponsor | the free text the form asks for |
| contact person, contact email | sponsor or manager | never public; the address is what invitations go to |
| manager | manager | a user id with the administrator level |
| mentors | manager | mentor record ids from the mentors index |
| logo | sponsor | the attachment ids the logo module already keeps |
| consent | the site | the application's privacy line, as today |
| created | the site | the day the company applied or was added |
| agreement, interests, dashboard account, posting flag | the site | already on the site; the record points at them |
| notes | manager | private text, shown on the Sponsors screen only |

The three offer mirror fields (`offer`, `instructions`, `more_info`) leave the record: they were Airtable's copy of the primary offer, and the primary offer is the record now. The profile card keeps letting a sponsor without a primary offer describe what it offers, by seeding one in draft.

## 5. The screens

**Sponsors screen (wp-admin).** The list stays; each row gains Edit. The edit form holds the manager-owned fields of section 4 with the mentor picker and the manager list, and every save is logged with the actor and the changed fields. "Add a sponsor" creates a record in `In review` with the name, website, contact and shape, and nothing else happens until a manager sets `Approved` and provisions an account, the two steps that exist today. The sync box becomes the migration box until the migration is complete, then disappears.

**Sponsor Dashboard.** Unchanged in shape. The profile card's help line stops saying the details are written to the program's records; the Collaboration Agreement card and the interests card lose nothing a sponsor could see.

**Administrator Dashboard.** The syncs card lists three syncs instead of four; the Sponsors strip reads the store.

## 6. The feed

`GET /wp-json/wpcpm/v1/sponsors` returns:

- `sponsors`: for every Approved sponsor, `name`, `slug`, `website`, `logo` (the public attachment address or empty), `mentors` (a count), `since` (the month the company applied, `YYYY-MM`).
- `applications`: per month for the last 24 months, `received` and `approved`, from the application posts and the store's created dates.
- `generated`: the time the answer was built.

No contact person, no address, no notes, no status but Approved, no sponsor below Approved. The route is public, answers `Cache-Control: public, max-age=900`, is metered per address, and its shape is pinned by a suite. The Tracker and the Monthly Report keep the field names of their Airtable readers so their change is a source swap.

## 7. Privacy, in one place

Every promise of the Sponsors module's section 11 stands. Two things change: a sponsor's contact address is no longer copied to a third-party base, and the feed publishes only what the handbook already publishes about a sponsor (its name, logo, website and that it is a sponsor) plus counts. The migration log names sponsors, never people. Uninstall deletes the posts and their meta with the rest of the module's data, and keeps what it keeps today.

## 8. Tests

- `bin/test-sponsors-store.php` (new): the post type is private; a migrated key and a minted key both pass `is_key()`, a third shape fails; the migration turns a held index into posts once, keeps the option on a count mismatch, and seeds the draft primary offer for a row with offer text; `rows()` after the migration equals `rows()` before it, field for field, for the five fields the store keeps.
- `bin/test-sponsors-screen.php`: the edit form saves each manager-owned field, refuses a sponsor key it does not know, logs the changed fields, and the mentor picker writes record ids only.
- `bin/test-sponsor-approval.php`: the eight steps become seven, with no Airtable client in the path; the record's `created` is the approval day.
- `bin/test-sponsor-profile.php`, `test-sponsor-logo.php`, `test-sponsor-offers.php`, `test-sponsor-interests.php`, `test-sponsor-agreement.php`, `test-sponsors-screen.php`: every write-back assertion becomes an assertion that the store changed and no Airtable request was made.
- `bin/test-sponsors-feed.php` (new): the shape, the Approved filter, no contact field in the answer, the cache header, the ceiling.
- `bin/test-sponsors-sync.php` and the Sponsors fixture are removed; `bin/test-fixtures.php` and `bin/check-references.php` stay clean.
- The battery stays silent; `bin/check-standards.sh`, `bin/check-spelling.php`, `bin/check-dead-annotations.php`, `bin/check-temp-litter.php` clean.

## 9. Guides and readme

`docs/sections/34-admin-operations.md` loses the sponsors sync paragraph and gains one on the store, the edit form and the feed; `40-sponsor-dashboard.md` and `42-sponsor-visibility.md` stop saying "written to the program's records" and say the program keeps the details on the site; `php bin/build-docs.php` rebuilds. The readme entry names the two manual steps: rename the Airtable table as an archive, and retire the seven automations.

## 10. Deliberately not in scope

The other modules' records; the Tracker's and the Monthly Report's switch to the feed (their own repositories, later); the handbook's hand-placed logos; any change to what a sponsor can do on its dashboard; anything to do with how sponsors are recognized.

## 11. Release

One release, 1.111.0, no theme change: the header, `WPCPM_VERSION`, the readme's stable tag and entry, every `block.json`, the translation template, `bash bin/build`. Deploy as usual; then read the Sponsors screen's migration box (31 rows, 31 posts, option removed), open the TEST sponsor's dashboard (user 64274511) and the Sponsors strip on the Administrator Dashboard, and call the feed once.

## 12. Open questions for the product owner

None that block the work. Two for the program team: who renames the Airtable table and on which day, and whether the seven automations go the same day.
