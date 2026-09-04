# Sponsors module

**Date:** 4 September 2026
**Component:** `wpcredits-program-manager` 1.92.0, ships as 1.93.0 to 1.98.0 in six phases, with `wpcredits-theme` 1.18.0 and 1.19.0
**Status:** approved design, not yet implemented
**Depends on:** the semester report approval design and the Administrator Dashboard design of the same date. The last phase here adds the sponsor queues to that dashboard.

Today the module registers a `wpcpm_sponsor` role and nothing else: `class-wpcpm-sponsors.php` is 55 lines, `is_implemented()` is inherited false, the settings already know the Sponsors table, and the mentors sync reads the table for one lookup. Sponsors themselves live in an Airtable form, a Google Sheet of coupon codes that program managers hand out one row at a time, and email.

This document builds the module along the life of one sponsor: it applies on wordpresseducation.org, a program manager approves it, it gets an account and a dashboard, it describes its offer and pastes its codes, students claim them from the Student Report Card and the sponsor watches the numbers, it writes guides that a manager publishes, it uploads its logo and, if there is one, its agreement, it says what else it would like to sponsor, and its sponsored mentors are on its page. Airtable stays the system of record for who the sponsor is; the site holds what Airtable cannot: codes, claims, posts, files.

House rules that apply to every line below: comments explain why and name the bug or decision behind the rule; no em dashes; full product names ("Student Report Card", "Mentor Report Card", "Institution Dashboard", "Sponsor Dashboard", "Administrator Dashboard"); every behaviour worth trusting has an assertion in `bin/test-*.php`.

---

## 1. Settled by the product owner (4 September 2026)

1. **The Sponsor Dashboard replaces the coupon spreadsheet.** Sponsors enter their code lists on the dashboard; students claim them from a new Tools section of the Student Report Card; every claim is counted so the sponsor sees how popular its offer is.
2. **Sponsors never see a student's name.** Stats are plain numbers.
3. **Current students only** claim, beyond the audiences a sponsor opens. A finished student keeps seeing what they claimed and is offered nothing new. Alumni are not an audience.
4. **A sponsor decides who may use its tools:** students only, or also mentors and program managers.
5. **Two switches control the Tools section:** whether it appears on the Student Report Card, and whether it appears on the Mentor Report Card.
6. **Sponsors write posts in the wp-admin block editor**, with Contributor-level rights, and can never publish. A program manager approves and publishes. Posts live in a category structure of parent "Sponsors" with one child category per sponsor. Approved posts appear in the Tools section.
7. **Published sponsor posts are readable by students, mentors and program managers** by default. A new access level says exactly that.
8. **The sponsor application form moves onto the site** and replaces the Airtable form. Its fields are the Airtable form's eight. The site's approval creates the Airtable record with Status `Approved` and switches the account on together; managers check GPL and trademark compliance before pressing Approve. Airtable's `In review` status stays for hand use.
9. **The fourteen sponsors already Approved in Airtable get their accounts one at a time**, each switched on by a manager. Nothing is provisioned by surprise.
10. **The sponsor agreement is upload only.** No template exists. A signed PDF is stored encrypted like the Collaboration Agreement and accepted by a manager. It is not a gate: a sponsor's dashboard works without one.
11. **Sponsors upload their own logo**, replacing the one in Airtable. Sponsor offering details already in Airtable stay in Airtable and the site keeps them in step.
12. **Sponsors have a main contact person and an assigned program manager**, like institutions.
13. **Sponsors can express interest** in sponsoring mentors, and in sponsoring students to attend WordPress flagship events such as WordCamp Europe and WordCamp Asia.
14. **A scoreboard of the most active, best-rated mentors is a future piece**, so that sponsors can choose whom to support. This module records what it will need and builds none of it.
15. **The approach is copy the shape, share the primitives** (confirmed 4 September 2026): sponsors get their own small classes written to the institution classes' contracts; the generic pieces are extracted once and used by both.

---

## 2. What the data says

Read from the base (`appIzQKfwTn5dyPVp`) on 4 September 2026.

### Sponsors (`tbluji8wknOZr55fa`), 30 records, 27 fields

Identity: `Company Name` (primary), `Website` (url), `Contact Person Full Name`, `Contact Email` (email), `Logo` (attachments; 16 records hold one, two hold a colour and a white version). Relationship: `Status` (single select: `Approved` 14, `Not Moving Forward` 9, `Paused` 4, `In review` 1, and `Rejected` exists unused), `Person of contact` (link to Team Members, the assigned manager), `Calendly link (from Team Members)`, `Mentors` (link to Mentors), `HelpScout`, `Notes`. Sponsorship shape: `Sponsorship options` (single select: `Sponsor one or multiple mentors`, `Sponsor mentors + tools/services`, `Other (please specify)`), `How would you like to support WP Credits?` (multiple select with six options: financial support, sponsor an admin, sponsor mentors, sponsor a scholarship for flagship events, sponsor tools or services, other), `Type of mentors` (`Internal` / `External`), `External Mentors` (decided / open to suggestions), `Number of mentors`. The offer: `Type of product` (`Hosting`, `Plugin`, `Service`), `Offer` (one line: "One year of Weglot's Business Plan for free"), `Brief instructions` (long text, in the sponsor's own words and sometimes language), `More info link` (url), `Coupon code/discount link` (url; for four sponsors it points at the Google Sheet, for two at a checkout link with the promo code in it, for the rest "N/A" or empty), `Anything else you'd like to share.`, `Privacy Policy Compliance`. Bookkeeping: `Lessons`, `Students Reports`, `Students Reports copy`.

The public interest form (`shrKDkjeUdw8Fof34`, "WP Credits - Company Interest Form") asks eight things: Company Name, Website, Contact Person Full Name, Contact Email, How would you like to support WP Credits? (the three `Sponsorship options` choices, with the note that sponsoring tools requires sponsoring at least one mentor at two hours a week), two logo versions (white and colour, transparent, 300 px wide, PNG), Anything else, Privacy Policy Compliance.

### The coupon sheet (the Google Sheet the Airtable `Coupon code/discount link` fields point at; its address is not recorded here because it names students), about 320 code rows

Two models, and the module supports both:

- **A pool of one-time codes.** WordPress.com: 170 rows of one-time codes, each `used` yes or no, date, student name, email, which manager handed it out, the checkout link with the code, notes ("coupon has several problems on checkout, gave new one"). WPBakery: a pool of codes each marked `Available` until claimed. About 70 of 320 rows are used.
- **One shared code or link for everybody.** Smarthost (one hosting code and one domain code, with instructions per country), Cloud86 (a checkout link with the promo code), Novamira ("no coupon required", the licence is sent on sign-up). The sheet lists who was told, by hand.

The header row asks managers to email the sponsor "when coupons are running out (fewer than 10 left)". That sentence becomes a rule (section 6.6). By the product owner's answer of 4 September 2026, the sheet is not imported: sponsors paste their remaining codes, and the sheet stays the historical record.

### Mentors (`tblJmEYgBWYxVuzUw`)

`Sponsored` (`Yes` / `No`), `Sponsor company` (text), `Sponsor Company Name` (link to Sponsors, the inverse of `Sponsors.Mentors`), `Wants to be in the looking for sponsors list` (`Yes` / `No`), `Rating` (lookup of the Feedback form's `Rate your mentor's support`: Excellent, Good, Neutral, Needs improvement, Poor, plus one stray address value). Feedback also holds three star ratings on the mentor's support (F1, F2, F3). Those five columns are the scoreboard's raw material (section 13).

### Team Members (`tblUYWUSEcRLJ5BaR`)

`Name`, `Email`, `Calendly link`, `Sponsors` (the inverse of `Person of contact`). Small, read whole.

---

## 3. The architecture, in seven decisions

**1. Copy the shape, share the primitives.** New classes: `WPCPM_Sponsor_Members`, `WPCPM_Sponsor_Policy`, `WPCPM_Sponsors_Sync`, `WPCPM_Sponsors_Index`, `WPCPM_Sponsors_Dashboard`, `WPCPM_Sponsor_Roster` (which sponsor is acting, the switcher, the metered `claim()`), `WPCPM_Sponsor_Profile`, `WPCPM_Sponsor_Offers`, `WPCPM_Sponsor_Claims`, `WPCPM_Sponsor_Tools`, `WPCPM_Sponsor_Posts`, `WPCPM_Sponsor_Interests`, `WPCPM_Sponsor_Logo`, `WPCPM_Sponsor_Agreement`, `WPCPM_Sponsor_Application`, `WPCPM_Sponsor_Approval`. Extracted for both modules: `WPCPM_Form_Guard` (from the institution application: honeypot, dwell token, per-actor ceiling, site-wide degrade, consent evidence, link scoring, mail ceiling), `WPCPM_Pdf_Check` (from the agreement: magic bytes and the stream scan), `WPCPM_Secret` (from private files: `seal()` and `unseal()` on the site's key), `WPCPM_Image_Upload` (new, section 8.1), `WPCPM_Refusal_Meter` (from the roster: the per-account ceiling that locks an account for the day). The institution classes are refactored to call the extractions and their suites must stay green through it; that is the whole of the "gets cleaner in passing".

**2. A person acts for a sponsor by a stamp on their account, and the fence is one policy function.** `wpcpm_sponsor_record_id` in user meta, read by `WPCPM_Sponsor_Policy::decide()` and by nothing else that decides. Several accounts per sponsor from day one, all with equal power, no owner, no tiers. A manager attaches and removes accounts; sponsors do not invite each other in this release (section 14).

**3. Airtable is the record of who the sponsor is; the site is the record of what it does here.** Name, website, contact, status, the assigned manager, the offer's description and the logo are read from Airtable and, where a sponsor may edit them, written back through an allowlist with an audit row. Codes, claims, posts, uploaded files, flags and interests live on the site, and the only things written to Airtable about them are three agreement fields, one interests field, and the logo. Nothing about a claim ever reaches Airtable.

**4. Codes are sealed in one option per offer, claimed under a lock.** The plugin has no custom table and the code volumes are hundreds, not millions; the `add_option()` test-and-set lock the import and the report generation use is enough for a claim, and the site's private-files key already exists to seal the codes at rest. A custom table becomes the right answer if an offer ever passes a few thousand codes, and section 14 says so.

**5. Sponsor posts use WordPress's own editor, and the plugin enforces the one rule the editor cannot.** Contributor-level capabilities on the member's account (added and removed per account, never on the role), the media modal limited to their own files, and one `wp_insert_post_data` filter that makes a sponsor's post pending, in the sponsor's category, at the students-and-mentors level, whatever the form said. This is the first `wp_insert_post_data` filter in the plugin and the first public content authored by a non-manager account; `WPCPM_Privacy_Guard` already closes the author archive such a post would open.

**6. Two kinds of upload, two stores.** A logo is public by nature and goes into the Media Library through a strict image handler; an agreement is private by nature and goes into `WPCPM_Private_Files` through the PDF check, exactly as the Collaboration Agreement does. Neither ever uses `wp_handle_upload()` on trust.

**7. Sponsors read numbers, managers read names.** Every sponsor-facing statistic is a count over time and offer, with no other dimension. The claimant list exists, for support, on the manager side only.

---

## 4. The authorisation policy

`WPCPM_Sponsor_Policy`, the mirror of `WPCPM_Institution_Policy`: `decide( $action, array $subject, $user = null )` returning `array( 'allowed', 'ground', 'why' )`, one `refusal()` `WP_Error` for every no (not yours, no such record, not a member, unknown action, all byte-identical), an explicit `grounds()` map so an action missing from it fails closed and a suite fails until the map is updated, and the same subject builders (`subject_sponsor( $record )` from the index, `subject_post( $post )` from the post's sponsor meta). No gate: `ungated()` returns every action, because decision 10 makes the agreement optional.

| Action | Grounds |
| --- | --- |
| `ACT_VIEW_DASHBOARD` | manager, member |
| `ACT_EDIT_PROFILE` | manager, member |
| `ACT_MANAGE_OFFERS` | manager, member |
| `ACT_VIEW_STATS` | manager, member |
| `ACT_VIEW_CLAIMANTS` | manager |
| `ACT_WRITE_POSTS` | manager, member (and the per-sponsor posting flag, section 7.1) |
| `ACT_PUBLISH_POST` | manager |
| `ACT_UPLOAD_LOGO` | manager, member |
| `ACT_AGREEMENT` | manager, member |
| `ACT_REVIEW_AGREEMENT` | manager |
| `ACT_EXPRESS_INTEREST` | manager, member |
| `ACT_MANAGE_MEMBERS` | manager |
| `ACT_PROVISION` | manager |

Claiming a tool is not a sponsor action and is not in this map; it is decided by `WPCPM_Sponsor_Tools::may_claim()` on the claimant's own account (section 8.3), because the claimant is never a member of the sponsor.

`WPCPM_Sponsor_Roster::claim( $record )` is the one place a record ID arriving in a URL is turned into an acting sponsor: for a member the stamp wins and the argument is ignored; for a manager the argument is checked against the index; every refusal is metered by `WPCPM_Refusal_Meter` (twenty a day, then the account is locked until tomorrow and the lock logged once, listed on the manager screens' locked-accounts card).

---

## 5. Phase S1: accounts, sync and the dashboard shell (1.93.0, theme 1.18.0)

### 5.1 `WPCPM_Sponsor_Members`

User meta: `META_RECORD_ID = 'wpcpm_sponsor_record_id'`, `META_ACTIVE`, `META_RECORD_ID_WAS`, `META_MEMBERSHIP` (`since`, `by`, `how`). Flags are per sponsor, not per person (section 7.1). `HOW_PROVISIONED`, `HOW_MANAGER`, `HOW_APPROVED`. `attach( $user_id, $record, $how, $by )` refuses, in this order: a malformed record ID; a record not in the index; no such account; an account holding `ROLE_ADMIN` (a manager already reaches every sponsor); an account carrying a student stamp (a student cannot be a sponsor's representative while in the program); an account carrying an institution stamp (one person acting for a school and for a company that sells to its students is a conflict the site does not arbitrate); already a member here; already a member of another sponsor. An account that is a mentor may be attached: sponsored mentors are often the sponsor's own staff, and the account gains `wpcpm_sponsor` as a second role, keeping the first. `detach()` records `META_RECORD_ID_WAS` and the reason. `members_of( $record )` is a `WP_User_Query` on the stamp.

### 5.2 `WPCPM_Sponsors_Sync` and `WPCPM_Sponsors_Index`

`WPCPM_Sponsors` becomes a `WPCPM_Sync_Module` (`ACTION_SYNC = 'wpcpm_sponsors_sync'`, `ACTION_CANCEL`, `ACTION_TICK`, `flash_key() = 'sponsors_admin'`); the sync class exposes `run_tick()`, not `tick()`, so the base class needs no override. Daily cron `wpcpm_sponsors_daily`, tick cron `wpcpm_sponsors_sync_tick`, `BUDGET = 18`, `BUDGET_AJAX = 8`, options `wpcpm_sponsors_state / _report / _last_sync / _last_error / _lock`, the daily run placed four hours after the institutions run the way that one is placed after the students run. Four phases:

1. `team`: the Team Members table read whole into `wpcpm_team_members` (record ID, name, email, Calendly link). Small, and it is what turns `Person of contact` into a name.
2. `records`: the Sponsors table read whole (30 rows, one page) into `wpcpm_sponsors_index`: record ID, name, website, status, sponsorship option, support options, product type, offer, instructions, more-info link, coupon link, contact person, contact email, manager (Team Members record ID, resolved to name and email at read time from the option above), mentors (link IDs), logo (the first attachment's URL, filename, type, size and dimensions, and the attachment ID Airtable gave it), consent, and the three agreement fields of section 12. `fields()` is a plain array like the institutions sync's, no filter. Names are kept untrimmed and trimmed by renderers, the institution rule.
3. `logos`: for every Approved sponsor whose Airtable logo attachment ID differs from the one recorded in `wpcpm_sponsor_logo_<record>`, the file is fetched and sideloaded through `WPCPM_Image_Upload` (section 8.1) into the Media Library, because an Airtable attachment URL expires within hours and a dashboard that hotlinked one would show a broken image by the afternoon. A logo the sponsor uploaded on the site (its own attachment ID recorded as `site`) is never overwritten by Airtable's.
4. `revoke`: accounts of sponsors whose status is no longer `Approved` are handled per `sponsor_on_inactive` (`keep` by default, `revoke` detaches). Nothing is ever provisioned by the sync (decision 9).

### 5.3 Provisioning, one at a time

On the wp-admin Sponsors screen, every Approved sponsor row has *Create account* (`ACTION_PROVISION = 'wpcpm_sponsor_provision'`, manager, nonce keyed to the record). The handler creates a `wpcpm_sponsor` account from `Contact Email` (or attaches the existing account at that address under the rules of 5.1), stamps it, writes `Dashboard account` in Airtable (section 12), queues the welcome through the existing invitation queue (`wpcpm_invite_queue`, the set-password mail every provisioned audience gets), and logs it. The same screen lists each sponsor's accounts with *Attach account* (by email) and *Remove* (`ACTION_MEMBERS`, manager). Only `maciej@a8c.com` is used for any test send.

### 5.4 The Sponsor Dashboard

Slug `sponsor-dashboard`, title **Sponsor Dashboard**, block `wpcpm/sponsor-dashboard`, shortcode `wpcpm_sponsor_dashboard`, `OPT_PAGE = 'wpcpm_sponsor_page_id'`, gated to the `wpcpm_sponsor` level with `metadata_exists()`, `TITLE_VERSION = 1`, `sponsor_home` routing at login (default true, like the others), `assets/js/forms.js` enqueued, a `WPCPM_Dashboards::links()` entry guarded by `class_exists()`. `render()` calls cards by name through `card()` with `class_exists()`, in this order:

1. `WPCPM_Two_Factor::prompt()` for the viewer. Sponsors are not added to `two_factor_roles`: they see no personal data of anyone else.
2. Optional `<h2>`.
3. The switcher, managers only (`WPCPM_Sponsor_Roster::ARG_VIEW`).
4. **Identity**: the logo (the site's attachment if any, else the synced one, else the initials mount the header chip uses), company name, website, product type, and the contact person with email. The contact is shown to members and managers; it is the sponsor's own data.
5. **Your program contact**: the assigned manager's name, email and Calendly link from the index; when the link is empty, the program's general contact block the Institution Dashboard uses. Placed under the name for the reason the Institution Dashboard gives: a sponsor arrives with a question more often than with a task.
6. `WPCPM_Handbook_Assistant::render_resources( 'sponsor', contact block )`: the Updates column (`WPCPM_Updates::levels_for()` gains `sponsor` mapping to the sponsor level, so the column stops falling back to `can_view()` alone) and the guide button, which points at the sponsors guide once S6 ships it and at the program handbook until then.
7. **Profile** card (5.5).
8. **Offers and codes** card (S2).
9. **Usage** card (S2).
10. **Posts** card (S3).
11. **Sponsored mentors** card (5.6).
12. **Interests** card (5.7).
13. **Logo** card (S4).
14. **Agreement** card (S4).
15. **People** card: the sponsor's accounts, read-only for members; attach and remove are manager actions on the wp-admin screen (section 14 for invites).

Every card is the canonical `<details class="wpcpm-group wpcpm-group__disclosure">` with `wpcpm-group__summary`, `wpcpm-group__title`, the toggle span and `wpcpm-group__body`; open when it has something to act on or a flash for it, closed otherwise. Every form has `data-wpcpm-once` and `data-wpcpm-busy`. Every outcome travels by flash on channel `sponsor_dashboard`, taken once at the top.

### 5.5 Profile: the allowlist

`WPCPM_Sponsor_Profile`, `ACTION_SAVE = 'wpcpm_sponsor_profile_save'`, `ACT_EDIT_PROFILE`. The fields a member may write, spelled as the base spells them: `Website`, `Contact Person Full Name`, `Contact Email`, `Type of product`, `Offer`, `Brief instructions`, `More info link`, `Anything else you'd like to share.`. Single selects are validated byte for byte against the choice list pinned in `bin/fixtures/sponsors-table-fields.json` (no `typecast`, so any other spelling is a 422 for the whole PATCH). URLs go through `clean_url()` with the `https://name@host` refusal the feedback form learned. Text is capped at `MAX_TEXT = 4000`. Changing `Contact Email` changes the Airtable field and nothing about accounts, and the form says so. Every applied change writes an audit row through `WPCPM_Institution_Audit`, which gains `META_SPONSOR = '_wpcpm_audit_sponsor'` beside its institution key and a `record_sponsor()` entry point; readers filter by the key they know, so institution audit views never list sponsor rows and the reverse. The class name is not changed: renaming the audit log would touch every institution suite for no behaviour.

### 5.6 Sponsored mentors

From the index's `mentors` link IDs, resolved through the mentors sync's lookups to name, status and WordPress.org profile, with each mentor's number of current and past students from `WPCPM_Mentors_Dashboard::get_mentees()` by record. Names of mentors, numbers of students, never a student's name. Below it, **mentors looking for a sponsor**: Active mentors whose `Wants to be in the looking for sponsors list` is `Yes` and `Sponsored` is `No`, with name, profile link and expertise, and an *I would like to sponsor this mentor* form (`ACTION_INTEREST_MENTOR`, ceiling 5 a day per account) that mails the assigned manager (context `sponsor-interest`) and logs it. The mentors sync's `fields()` gains `mentor_sponsored`, `mentor_wants_sponsor` and `mentor_sponsor_company` for this, asserted against the fixture like every other name.

### 5.7 Interests

`WPCPM_Sponsor_Interests`, `ACTION_SAVE = 'wpcpm_sponsor_interest'`, `ACT_EXPRESS_INTEREST`: the six `How would you like to support WP Credits?` choices as checkboxes, a free-text list of events (one per line, "WordCamp Europe 2027"), and a note (`MAX_TEXT`). Saving writes the multiple select to Airtable, appends one dated line to the new `Sponsorship interests` field (section 12) so the base keeps the history without the site owning it, mails the assigned manager (context `sponsor-interest`, or every manager when no manager is assigned), writes an audit row, and flashes. Ceiling 5 a day per account.

### 5.8 wp-admin Sponsors screen

`render_admin_page()` stops being the placeholder: the sync panel the base class draws, then cards for the index (name, status, product type, manager, accounts, the logo state, links to the dashboard through the switcher), provisioning (5.3), members, the interests log (last 50), the locked-accounts card, and in later phases offers, claimants, agreements and applications. `menu_label()` hangs a bubble with the count that needs a manager (applications waiting plus agreements awaiting review plus pending posts).

### 5.9 Theme 1.18.0

`templates/page-sponsor-dashboard.html` with `wpc-main--sponsor`; `wpcredits_is_sponsor_page()`, `wpcredits_is_dashboard_page()` and the `wpc-sponsor-page` body class edited together; rules for the identity block with a real logo (a 176 px box, `object-fit: contain`, a white ground for logos drawn for dark backgrounds) and the program-contact block.

### 5.10 Tests

`bin/test-sponsor-members.php` (each refusal in 5.1 in order, the mentor case attaching with a second role), `bin/test-sponsor-policy.php` (the map row by row, one refusal, unknown action fails closed, the meter locking on the twenty-first refusal), `bin/test-sponsors-sync.php` (the four phases from fixtures, an expired logo URL never stored as the display source, `keep` versus `revoke`, budget stops mid-phase and resumes), `bin/test-sponsors-dashboard.php` (card order, a member sees no manager form, the switcher only for managers, the profile allowlist refuses a field outside it, choice spelling asserted against the fixture, the audit row written with the sponsor key), `bin/test-sponsors-screen.php` (provision creates or attaches, mails only the queue, refuses an administrator address). `bin/test-roles.php` gains the new class files in both require lists and the post type lengths. `bin/fixtures/sponsors-table-fields.json` and `team-members-table-fields.json` are added and asserted.

---

## 6. Phase S2: offers, codes and the Tools section (1.94.0, theme 1.19.0)

### 6.1 The offer

Post type `wpcpm_offer` (eleven characters), private, `show_ui` false, `show_in_rest` false, a capability type nothing is granted, `map_meta_cap` true, `supports => array( 'title' )`. One post per offer; a sponsor may hold several (Smarthost has a hosting code and a domain code). Meta:

| Key | Holds |
| --- | --- |
| `_wpcpm_offer_sponsor` | the record ID, the queryable key, never read from a form |
| `_wpcpm_offer_kind` | `codes` (a pool of one-time codes) or `shared` (one code or link for everyone) |
| `_wpcpm_offer_state` | `draft`, `live`, `paused`, `ended` |
| `_wpcpm_offer_audience` | array of `mentors`, `managers`; students are always in |
| `_wpcpm_offer_primary` | `1` on the one offer mirrored to Airtable's `Offer`, `Brief instructions`, `More info link` |
| `_wpcpm_offer_text` | what you get, one paragraph, `MAX_OFFER = 500` |
| `_wpcpm_offer_instructions` | how to redeem, `MAX_TEXT = 4000` |
| `_wpcpm_offer_url` | the redeem or more-info link, cleaned |
| `_wpcpm_offer_low` | low-stock threshold, default from `offer_low_stock` (10) |
| `_wpcpm_offer_low_sent` | when the last low-stock mail went, so it goes once per crossing |
| `_wpcpm_offer_expires` | optional last day, `Y-m-d`, read as a string never a timestamp (the cohort rule) |
| `_wpcpm_offer_event` | the last change, for the log |

On provisioning, the first offer is seeded from the index (`Offer` to text, `Brief instructions` to instructions, `More info link` to the URL, kind `shared` when `Coupon code/discount link` is a URL that is not a Google Sheet and `codes` otherwise), in state `draft`, marked primary. The sponsor completes and switches it live. Saving the primary offer's text, instructions and URL writes them to the three Airtable fields, so managers keep seeing the offer in the grid; `Coupon code/discount link` is never written (it is a url field, and the truthful value, "managed on the site", is not a URL), and a manager clears the sheet links by hand once each sponsor is live.

`WPCPM_Sponsor_Offers`: `ACTION_SAVE = 'wpcpm_offer_save'` (create or edit), `ACTION_STATE = 'wpcpm_offer_state'` (live, pause, resume, end), `ACTION_CODES_ADD = 'wpcpm_offer_codes_add'`, `ACTION_CODES_VOID = 'wpcpm_offer_codes_void'` (a sponsor voids unclaimed codes only), all `ACT_MANAGE_OFFERS`, nonces keyed to the post ID, the post's sponsor meta checked against the acting sponsor before anything else. An offer with kind `codes` cannot go live with no available codes; `shared` needs its shared code or link.

### 6.2 The codes

Option `wpcpm_codes_<post_id>`, non-autoloaded, versioned: `array( 'v' => 1, 'shared' => sealed, 'codes' => array( array( 's' => sealed, 'h' => sha256, 'st' => available|claimed|void, 'by' => user_id, 'at' => time ) ) )`. Sealed means `WPCPM_Secret::seal()` on the site's private-files key (`wpcpm_private_key`, AES-256-GCM, one random key per site); the hash is for duplicate detection without unsealing. Paste box: one code per line, or the first column of a CSV; trimmed; up to `LINE_MAX = 200` characters, because a code can be a whole checkout URL; duplicates within the offer refused and named by line number; `CODES_MAX = 5000` per offer, above which the sponsor is told to talk to the program. Adding codes clears `_wpcpm_offer_low_sent`.

Claiming under the lock: `add_option( 'wpcpm_claim_' . $post_id, token )` test-and-set with `LOCK_TIMEOUT = 30`; read, take the first `available`, mark it claimed by the user, write, release. Two students pressing at once get two codes; a student pressing twice gets the same code back, because the claim is recorded on the person first (6.3) and looked up before the lock is taken.

### 6.3 `WPCPM_Sponsor_Claims`

User meta `wpcpm_claims`: offer ID to `array( 'i' => index into the codes array or -1 for shared, 'at' => time )`. `claim( $offer_id, $user )` is refused unless `WPCPM_Sponsor_Tools::may_claim( $user, $offer )` says yes; then the record on the person, then the lock, then the code. `may_claim()` is one function with five clauses, each with a test: the offer is `live` and not past `_wpcpm_offer_expires`; the person is a current student (holds `wpcpm_student`, and the status in `wpcpm_student_program` is in `student_statuses` and not in `past_statuses`), or a mentor (holds `wpcpm_mentor`) when the audience includes mentors, or a manager (`CAP_MANAGE`) when it includes managers; the person has not claimed this offer; for `codes`, one is available. A manager viewing somebody else's card is never a claimant: the section on another person's card draws no claim form (6.5).

Codes are shown to the claimant only, on their own card, unsealed at render time; nothing sends a code by mail. `report_problem( $offer_id, $user )` (`ACTION_PROBLEM = 'wpcpm_claim_problem'`, ceiling 3 a day) mails the sponsor's assigned manager (context `claim-problem`) with the claimant's name, the offer and the code's last four characters, and logs it; the sheet's notes column was exactly this conversation. Managers void a claimed code (`ACTION_CLAIM_VOID`, `ACT_VIEW_CLAIMANTS`, manager), after which the person may claim again and the code stays `void` for the count.

### 6.4 Usage

`WPCPM_Sponsor_Claims::stats( $record )` returns, per offer: claims total, claims this month, a twelve-month series, available, claimed, void, and the totals across offers. Time and offer are the only dimensions; there is no institution, country, track or cohort breakdown, and no list of anything. The Usage card draws it as a small table with the months as a text series, and offers a CSV of the same numbers (`ACTION_STATS_EXPORT`, `ACT_VIEW_STATS`, a `WPCPM_Institution_Export`-style neutralised CSV). `bin/test-sponsor-offers.php` walks the returned structure and asserts no string passes `is_email()` and no key names a person, the snapshot walk the semester report uses.

### 6.5 The Tools section

`WPCPM_Sponsor_Tools::render( $audience, $viewer )` draws `<section class="wpcpm-student__section wpcpm-tools">` "Tools from our sponsors". It is called from three places, each a hand edit in a `render()` that has no registry: `WPCPM_Students_Dashboard::render()` after the report form and before the calendar, when `tools_students` is on and the viewer is the student whose card it is (the `own` audience `render_body()` defaults to, which `audience_for()` never returns); `WPCPM_Mentors_Dashboard::render()` after the past-students group, when `tools_mentors` is on and the viewer is the mentor; `WPCPM_Administrators_Dashboard::render()` after the syncs card, for every manager. On somebody else's card (a manager, mentor or institution viewing a student) the section is not drawn at all, except that a manager sees one muted line, "3 tools claimed", because support needs the count and nobody needs the codes.

Contents: every offer whose state is `live`, whose audience includes the viewer's kind, and which is not past its last day; `codes` offers with nothing available are not shown to students (a shelf of empty boxes is the sheet's "running out" problem shown to the wrong person) and are shown to the sponsor and managers with a warning. Sorted by sponsor name, then title. Each offer is a card: the logo, the sponsor's name and website, the title, what you get, the instructions, the more-info link, the sponsor's published posts under "Guides and demos" (S3), and the claim form, or the claimed code with its link and date and the *Report a problem* form. Below the list, **Your codes**: everything the viewer has claimed, including from offers since paused or ended, because a code once given is theirs.

Settings: `tools_students` (default true), `tools_mentors` (default false). There is no switch for managers; the Administrator Dashboard shows every live offer with its audience.

### 6.6 Low stock

After every claim from a `codes` offer, when available falls below `_wpcpm_offer_low` and `_wpcpm_offer_low_sent` is empty, one mail goes to the sponsor's accounts and one to the assigned manager (context `offer-low-stock`: offer title, available count, a link to the Offers card), and the stamp is written. Adding codes clears the stamp. The Administrator Dashboard's sponsor queues (S6) list offers under their threshold.

### 6.7 Theme 1.19.0

Rules for `.wpcpm-tools`: offer cards in a two-column grid that collapses at 782 px, the logo box, the claimed-code block in a monospaced face with a copy affordance the plugin's `forms.js` provides (select-on-click, no clipboard API needed), and the muted one-line count. Checked by `bin/check-selectors.php`.

### 6.8 Tests

`bin/test-sponsor-offers.php`: meta shape, seeding from the index, the primary mirror writes exactly three fields, `codes` cannot go live empty, paste parsing (lines, CSV, trim, duplicates by line number, the 200-character line, the 5000 cap), sealing round-trips and the option holds no plaintext, two claims under the lock take two different codes, a second claim by the same person returns the same code, void by a sponsor refused on a claimed code, void by a manager frees the person, low stock mails once and resets after adding, the stats walk. `bin/test-sponsor-tools.php`: `may_claim()` clause by clause (a Graduate refused, a Paused student allowed, a mentor allowed only with the audience, a manager only with the audience, a second claim refused, an empty pool refused, an expired offer refused); the section drawn on the student's own card and not on a manager's view of it (one muted line instead), on the mentor's card only with `tools_mentors`, the switches, the sort, the empty-pool rule per viewer, "Your codes" listing a claim from an ended offer, the claim form absent from every non-own render. `bin/test-report-form.php` and the dashboards' suites see the call sites in order.

---

## 7. Phase S3: sponsor posts (1.95.0)

### 7.1 The posting flag and the capabilities

Option `wpcpm_sponsor_flags_<record>`: `array( 'posts' => bool )`, default `true` when the account is created (decision 6 wants sponsors writing), switched by a manager on the wp-admin screen (`ACTION_FLAGS`). Turning it on adds `edit_posts`, `delete_posts` and `upload_files` to each member account with `WP_User::add_cap()`; turning it off removes them; attaching an account applies the flag; detaching removes the three. Never on the role: a role change would reach every sponsor at once and survive the plugin's own switch. The `wpcpm_sponsor` role itself stays Subscriber plus its marker.

### 7.2 The editor, fenced

`WPCPM_Sponsor_Posts`, hooked only when the current user is a sponsor member without `CAP_MANAGE`:

- `wp_insert_post_data` (priority 10, two arguments): for `post_type` `post`, `post_status` becomes `pending` unless it is `draft`, `auto-draft` or `trash`; `post_date` is not allowed in the future (no scheduling); `post_author` is the current user. A manager saving is untouched, so publishing from the editor works.
- `save_post_post` (priority 20): the categories are set to exactly the Sponsors parent and the sponsor's child term; any other term is dropped; `_wpcpm_access_level` is written as `students_mentors` when absent. A manager saving is untouched, so the access box works.
- `pre_get_posts` in wp-admin: the posts list shows the member's own posts only (`author` = self), because Contributors can list every post on the site by default.
- `ajax_query_attachments_args` and `upload_mimes`: the media modal shows the member's own files, and uploads are `png`, `jpg`, `jpeg`, `webp`, `gif` only.
- `remove_meta_box` of the access-level box for members; the level is a manager's decision.
- The toolbar's "+ New" and the admin menu show Posts and Media and nothing else the caps do not already grant.

`WPCPM_Sponsor_Posts::ensure_terms( $record )`: the parent category with slug `sponsors` and name "Sponsors", created if absent; a child named after the company (slug `sanitize_title()` of the name, suffixed `-2` on a clash) recorded in `wpcpm_sponsor_term_<record>`; renamed when the company name changes in a sync. This is the one place the plugin creates taxonomy terms, and it does so because decision 6 spells the structure out. Terms are not deleted on uninstall: a category with posts in it is site content.

### 7.3 The new access level

`WPCPM_Content_Access::levels()` gains `students_mentors`, labelled "Students and mentors", and `can_view()` passes it for `wpcpm_view_student_content`, `wpcpm_view_mentor_content` or `CAP_MANAGE`. `WPCPM_Updates::levels_for()` includes it for the `student` and `mentor` audiences, so a program-wide announcement can be posted once. Sponsor posts default to it (7.2); a manager may widen one to `public` or narrow it in the post's access box. The category archives obey the existing query filter, so a logged-out visitor sees an empty Sponsors category.

### 7.4 Approval

Managers publish from the editor (Pending to Publish, core's own transition) or from the Administrator Dashboard's pending list (S6): *Preview*, *Publish* (`ACTION_POST_PUBLISH`, `ACT_PUBLISH_POST`, nonce keyed to the post, `wp_publish_post()`), *Return* (`ACTION_POST_RETURN`: back to `draft`, the note mailed to the author, context `sponsor-post-returned`). A published post appears under its sponsor's offers in the Tools section (6.5) and in the sponsor's category. Sponsors can never reach `publish_posts`, and `bin/test-sponsor-posts.php` asserts the cap set byte for byte.

### 7.5 Byline

Sponsor posts carry no personal byline. `the_author` and `get_the_author_display_name` return the company name for posts whose author is a sponsor member, and `the_content` at priority 6 (after the access filter at 5) prepends a small banner with the logo and "A guide from <Company>, a sponsor of the WordPress Credits program". The author archive is already closed by `WPCPM_Privacy_Guard`.

### 7.6 The Posts card

On the Sponsor Dashboard: the sponsor's posts with state (draft, pending review, published, returned with the note), edit links into wp-admin, and *Write a post* (`post-new.php`) when the flag is on; when it is off, one sentence saying the program has not enabled posting for this sponsor.

### 7.7 Tests

`bin/test-sponsor-posts.php`: the flag adds and removes exactly three caps per account; a member's insert data comes back `pending` with the author set, a draft stays draft, a future date is refused; the categories are forced and a foreign term dropped; the access level defaults and a manager's save is untouched; the admin list query is scoped to self; the MIME list; `ensure_terms()` creates once, suffixes on a clash, renames; publish and return handlers refuse a member and act for a manager; the byline filter answers the company name only for sponsor-authored posts. `bin/test-content-access.php`: the new level in `levels()`, `can_view()` for each of the three grants and a refusal for an institution account. `bin/test-updates.php`: the level in both audiences' lists.

---

## 8. Phase S4: logo and agreement (1.96.0)

### 8.1 `WPCPM_Image_Upload`

The plugin's first image handler, shared by the logo card, the sync's logo phase and the application form. `accept( array $file, array $rules )`: size at most `logo_max_kb` (1024) kilobytes; the MIME from `finfo` and the type from `getimagesize()` must agree and be `png`, `jpeg` or `webp`; width at least `MIN_WIDTH = 200` and at most `MAX_SIDE = 4000`; the file is re-saved through `wp_get_image_editor()` so that metadata is stripped and the bytes served are bytes WordPress wrote. SVG is refused: it is a document that can carry script, and the sponsors who hold one (two today) convert it. `store( $bytes, $name, $author )` inserts the attachment with `wp_insert_attachment()` and `wp_generate_attachment_metadata()`, author the acting account, title "<Company> logo (colour|white)". Public by nature, and the only thing this class is used for.

`WPCPM_Sponsor_Logo`: `ACTION_UPLOAD = 'wpcpm_sponsor_logo'` (colour and white, each optional, ceiling 5 a day), `ACTION_REMOVE`, `ACT_UPLOAD_LOGO`; `wpcpm_sponsor_logo_<record>` records `array( 'colour' => id, 'white' => id, 'source' => site|airtable, 'airtable_id' => ... )`. An upload writes the attachments' public URLs to Airtable's `Logo` (the array replaced, filename kept), so the base shows the logo the site shows and the WPCredits tracker's sponsor logos can keep reading Airtable. The identity block prefers `colour`.

### 8.2 `WPCPM_Sponsor_Agreement`

Post type `wpcpm_sponsor_agr` (seventeen characters), private, the Collaboration Agreement's shape without the template branch and without the gate. States `submitted`, `accepted`, `returned`, `withdrawn`, `superseded`, `revoked`; kinds `own` (uploaded) and `legacy` (on file with a Drive link, recorded by a manager). Summary states `none`, `submitted`, `returned`, `revoked`, `accepted`, `on_file`. Meta mirrors the institution keys with the `_wpcpm_sagr_` stem. Actions `wpcpm_sponsor_agr_upload`, `_download` (the one `nopriv` arm, so a signed-out link meets the login form), `_accept`, `_return`, `_withdraw`, `_revoke`, `_reinstate`, `_on_file`. The upload goes through `WPCPM_Pdf_Check` (magic bytes, the stream scan with the same refusal list and limits) and `WPCPM_Private_Files::store()`; the download is served as an attachment, never inline; `agreement_max_mb`, `agreement_uploads_per_day`, `agreement_review_days`, `agreement_notify` and `agreement_discard_days` apply as they are. Mails: `sponsor-agreement-received` (to managers), `-accepted`, `-returned`, `-revoked` (to the sponsor's accounts). Accepted, superseded and revoked files are kept on uninstall and listed in the same mailed manifest as the institutions' files; withdrawn and returned files are discarded by the same cron. The Agreement card on the dashboard shows the state, the upload form and the history; the manager side lives on the wp-admin screen and the Administrator Dashboard (S6).

### 8.3 Tests

`bin/test-image-upload.php` (each rule refuses on its own, SVG refused by content not by name, a PNG renamed `.jpg` refused because the two types disagree, the stored bytes are the editor's), `bin/test-sponsor-logo.php` (the record's shape, Airtable's `Logo` written with public URLs, a site logo never overwritten by the sync), `bin/test-sponsor-agreement.php` (every transition, the one refusal, the download's order of checks, discard and keep, the manifest line). `bin/test-pdf-check.php` is the extraction's own suite and `bin/test-institution-agreement.php` must not lose an assertion.

---

## 9. Phase S5: the application form (1.97.0)

### 9.1 `WPCPM_Form_Guard`

Extracted from `WPCPM_Institution_Application` with its order intact, because the order is the design: honeypot, dwell token (mint, judge, single use through `WPCPM_Ceiling`), per-actor ceiling that refuses, site-wide ceiling that degrades and holds, consent as a precondition with the evidence stored (sentence, policy URL and ID, policy modified time), link scoring, mail ceiling. Constants keep their values. The institution form is rewired to call it, and `bin/test-institution-application.php` must pass unchanged, which is the proof the extraction changed nothing.

### 9.2 `WPCPM_Sponsor_Application`

Post type `wpcpm_sponsor_app` (seventeen characters), private. Page slug `sponsor-application`, title "Sponsor the WordPress Credits Program", not gated, `sponsor_applications_enabled` off by default, page ID in `wpcpm_sponsor_application_page_id`. The eight fields of the Airtable form, in its order and with its wording: Company Name, Website, Contact Person Full Name, Contact Email, How would you like to support WP Credits? (three radios, with the two-hours-a-week note as help text), Company logo (two files, colour and white, through `WPCPM_Image_Upload`, stored as attachments with author 0 until approval, deleted with a rejected or spam application by the retention cron), Anything else you'd like to share, Privacy Policy Compliance. `ACTION_SUBMIT = 'wpcpm_sponsor_apply'`. Six states and six decisions exactly as the institution queue has them, on the wp-admin Sponsors screen and on the Administrator Dashboard, with their own nonces keyed to the application ID. Duplicates against the index by name and by website are flagged and never merged. Retention reuses `application_spam_days`, `application_rejected_days`, `application_approved_days`.

### 9.3 `WPCPM_Sponsor_Approval`

Under an `add_option()` lock keyed to the application: the Airtable record is created with `Company Name`, `Website`, `Contact Person Full Name`, `Contact Email`, `Sponsorship options`, `Anything else you'd like to share.`, `Privacy Policy Compliance` true, `Status` `Approved`, `Logo` from the two attachments' public URLs, and `Dashboard account` true; then the account (5.3), the child category (7.2), the logo record (8.1), the seeded primary offer in `draft` (6.1); then the applicant is mailed (the welcome through the invitation queue) and the row logged `application_approved`. If the Airtable write fails nothing else happens and the decision is not recorded, so a retry starts clean. Reject sends the neutral acknowledgement with no reason; spam is silent; info sends the manager's question.

### 9.4 After go-live

The handbook page's form link is changed by hand to the site's page. That is the only manual step, and the readme's changelog names it.

### 9.5 Tests

`bin/test-form-guard.php` (the extraction's own), `bin/test-sponsor-application.php` (each guard stage in order, the eight fields and their requiredness, consent evidence, the two logos through the image handler, duplicates flagged, six decisions, retention deleting the attachments), `bin/test-sponsor-approval.php` (the lock, the Airtable payload byte for byte, the order of the side effects, nothing after a failed write).

---

## 10. Phase S6: guide, audiences and the Administrator Dashboard queues (1.98.0)

- `docs/sections/` gains a sponsors guide (what the dashboard does, how codes and claims work, what sponsors can and cannot see, how posts are approved), composed by `bin/build-docs.php` as the `sponsors` audience and registered in `WPCPM_Handbook_Assistant::guides()` under `sponsor`. The dashboard's guide button points at it.
- The Administrator Dashboard gains, after the mentor requests card and before programs running: **Sponsor applications** (the six decisions), **Sponsor posts pending** (title, sponsor, submitted, Preview, Publish, Return with note), **Sponsor agreements** (awaiting review, returned, revoked, with Download, Accept, Return, Reinstate), **Offers running low** (offer, sponsor, available, threshold), **New interests** (last 30 days: sponsor, what, when), and a **Sponsors** strip (Approved sponsors, with accounts, live offers, claims this semester). The needs-attention strip gains sponsor applications, pending posts, agreements to review and offers running low. The Syncs card gains the sponsors sync.
- Notices: `sponsor` already exists as an audience; nothing to add.
- `readme.txt` describes the module; `docs/sections/34-admin-operations.md`'s data table gains every option below.

Tests: the Administrator Dashboard suite gains the five cards and the strip counts; `bin/test-handbook.php` sees the fourth guide; `bin/build-docs.php` builds it.

---

## 11. Privacy, in one place

- A sponsor account can read: its own index row, its accounts, its offers, its codes (sealed until it asks), counts over time and offer, its posts, its files, the names, statuses and student counts of its linked mentors, and the opted-in mentors looking for a sponsor.
- A sponsor account can never read: a student's name, address, institution, cohort, status, country or profile; a claimant list; any breakdown of a count by anything but month and offer; another sponsor's anything; a manager's notes.
- A student sees a sponsor's public face only: logo, name, website, offer, instructions, posts, and their own code.
- Every refusal is one message. Every ID-keyed route is metered. Every new post type is private, invisible and under twenty-one characters. Nothing in the module writes a person's address into an option or a log except the mail log, which masks it.
- Uninstall deletes the index, the offers, the codes, the claims meta, the flags, the applications, the interests log and the pages; keeps accepted agreement files with the mailed manifest, the Media Library logos and the categories, and never touches a person's account.

---

## 12. New Airtable fields

Created by the developer through the API, each announced first (Institutions spec decision 17), after the fixtures pin the choice names byte for byte.

| Table | Name | Type | Options | Why |
| --- | --- | --- | --- | --- |
| Sponsors | `Agreement Status` | singleSelect | `Not started`, `Awaiting review`, `Accepted`, `Returned`, `On file`, `Revoked` | the base half of the sponsor agreement, hand-editable, empty reads as Not started |
| Sponsors | `Agreement Accepted On` | date | | when a manager accepted it on the site |
| Sponsors | `Agreement Document` | url | | a Drive link to the accepted copy; never an attachment, so the document never leaves the Foundation's Drive |
| Sponsors | `Sponsorship interests` | multilineText | | one dated line per expression of interest, appended by the site, so the history is in the grid |
| Sponsors | `Dashboard account` | checkbox | | whether the sponsor has a site account, written by the site on provisioning and detaching |

Read, not created: `Mentors.Sponsored`, `Mentors.Wants to be in the looking for sponsors list`, `Mentors.Sponsor Company Name`, `Team Members.Email`, `Team Members.Calendly link`.

---

## 13. What the scoreboard will need, recorded now

The future mentor scoreboard ranks mentors by activity and by student-rated quality so that sponsors can choose whom to support. This module does not build it, but it leaves these in place: the mentors sync reads `Sponsored`, `Wants to be in the looking for sponsors list` and `Sponsor Company Name`; the Feedback fixture pins `Rate your mentor's support` and the three star ratings; `WPCPM_Mentors_Dashboard::get_mentees()` already yields per-mentor student counts by record. The scoreboard will need a consent rule of its own (a rating is a student's statement about a person, and the party that benefits from a good one is the mentor) and a decision on whether a mentor opts into being ranked; both are product decisions, not code, and are left open here on purpose.

---

## 14. Deliberately not in scope

- Sponsor-to-sponsor invitations. Accounts are attached by managers; the institution invite class is institution-keyed and generalising it is a later, separate change.
- Importing the coupon sheet (decided against).
- Alumni access to tools (decided against).
- SVG logos.
- A custom database table for codes (section 3, decision 4).
- A per-institution or per-country breakdown of claims for sponsors.
- Sending a code by mail.
- The mentor scoreboard (section 13).
- A sponsor agreement template.
- Two-factor for sponsor accounts.
- Any change to what the WPCredits tracker reads from Airtable.

---

## 15. Phases, versions and what each demonstrates

| Phase | Version | Demonstrates, with a clearly labelled TEST sponsor record and `maciej@a8c.com` as the contact |
| --- | --- | --- |
| S1 | 1.93.0, theme 1.18.0 | the sync indexes 30 sponsors and the team; a manager creates the TEST account and the welcome lands; the dashboard shows logo, name, contact, the assigned manager, the profile form; a saved profile change appears in Airtable with an audit row; an interest mails the manager and appends a line in Airtable; the sponsored-mentors card lists the linked mentor with a student count and no student name; a TEST institution representative visiting the page sees nothing |
| S2 | 1.94.0, theme 1.19.0 | the seeded primary offer edited and switched live writes three Airtable fields; twenty pasted codes; a TEST student claims from the Student Report Card and sees the code; a second claim returns the same code; a graduate sees no claim form; the sponsor's Usage card shows 1 claimed, 19 available and no name; a manager's view of the student's card shows "1 tool claimed"; the threshold set to 19 mails the sponsor and the manager once |
| S3 | 1.95.0 | the TEST sponsor writes a post in wp-admin, it lands pending in Sponsors / TEST Sponsor with the students-and-mentors level; the sponsor cannot publish; a manager publishes; a student and a mentor read it, a logged-out visitor cannot; it appears under the offer in the Tools section with the company byline |
| S4 | 1.96.0 | a PNG logo replaces the Airtable one on the dashboard and in the base; an SVG is refused; a signed PDF is uploaded, reviewed and accepted, the three Airtable fields fill, the file is kept on uninstall in the manifest |
| S5 | 1.97.0 | the application page submits with the two logos, is held, approved, and the record, account, category, logo and seeded offer appear together; a rejected one gets the neutral acknowledgement and its logos are gone after the retention run |
| S6 | 1.98.0 | the Administrator Dashboard shows the five sponsor cards and the strip counts; the sponsors guide opens from the dashboard |

Each phase is a version bump (header, `WPCPM_VERSION`, `readme.txt`, every `block.json`), the suites, `bin/check-references.php`, `bin/check-standards.sh`, `bash bin/build`, a deploy to wordpresseducation.org, a mirror push, and a note in the vault.
