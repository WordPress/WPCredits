# Sponsors on the site, 1.111.0: implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the sponsor record off Airtable and onto the site: a private post type is the record, the sync and the seven Airtable writes go, managers edit identity on the Sponsors screen, a public feed replaces the table for the two other readers; ship as plugin 1.111.0 with no theme change.

**Architecture:** `WPCPM_Sponsors_Index` keeps its read contract and stores rows as posts of the type `wpcpm_sponsor_co`; every sponsor keeps its key (a migrated one its Airtable record id, a new one a minted `spo` key of the same length) so nothing keyed by it is rekeyed; a one-time migration on `init` turns the held index into posts; the assigned manager becomes a site user with a booking link on their profile; the Sponsors screen gains Add and Edit; `WPCPM_Sponsors_Sync` and the write-backs are deleted; `GET /wp-json/wpcpm/v1/sponsors` is the feed.

**Tech Stack:** PHP 7.4 compatible WordPress plugin, WordPress coding standards, standalone suites under `bin/test-*.php` (no shared bootstrap: each suite stubs WordPress over `$GLOBALS` arrays and prints `ok`/`FAIL` per check).

**Spec:** `docs/specs/2026-09-23-sponsors-on-the-site-design.md` (read it first; this plan argues from it). The module it changes: `docs/specs/2026-09-04-sponsors-module-design.md`. The inventory the plan was written from (every line number below comes from it, read at `main` = `778d4e3`): `.superpowers/sdd/2026-09-23-sponsors-on-the-site-1-111-0/inventory.md` once the workspace copies it there; until then `/private/tmp/claude-501/-Users-maciejpilarski-GitHub/25b9f120-1b7e-4cf2-9739-9a4891fc924e/scratchpad/plan-1-111-0/inventory.md`.

## Global Constraints

- WordPress coding standards throughout (tabs, Yoda conditions, spaces inside parentheses, `array()`), PHP 7.4 compatible, every string a person reads in US English, no em dash or en dash anywhere (a plain hyphen), full product names ("Student Report Card", "Mentor Report Card", "Sponsor Dashboard", "Administrator Dashboard", "Collaboration Agreement"), comments explain why and name the decision behind a rule, docblocks true.
- **This release is about the record and nothing else.** Nothing about how sponsors are recognized, ranked or rewarded appears anywhere in it: not in code, comments, strings, docs, tests or commit messages.
- **The literal `wpcpm_sponsor` never appears in a statement that also holds `===`** in any file matching `includes/modules/class-wpcpm-sponsor*.php` (`bin/test-sponsor-policy.php` L244 to L248 fails the suite). Compare through constants (`self::POST_TYPE`, `self::META_KEY`), never through the literal.
- Every behavior lands with a check that fails on the code before the change (prove it, quote it in the report) and passes after. The battery stays silent: `for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done` prints nothing. `php bin/check-references.php`, `php bin/check-spelling.php`, `php bin/check-dead-annotations.php`, `php bin/check-temp-litter.php` clean; `bash bin/check-standards.sh` exits 0.
- `bin/` and `docs/` are public: nothing personal, no real Airtable record id (`bin/test-fixtures.php` L562 to L608 refuses any `rec` + 14 that reads as Airtable's; use `recSEED0000000001`-style ids or `spo` keys with a run of four identical characters), no address outside `maciej@a8c.com`, its plus-addressed forms and `.example` domains.
- Nothing touches the live site, no ssh, no network, no subagents from inside a task. Version numbers move in Task 10 only.
- Every commit message starts with "Sponsors on the site:" and names the task.

## Rulings that bind every task

1. **The post type is `wpcpm_sponsor_co`** (16 characters, "co" for company). `wpcpm_sponsor` is the role slug and the form field name every sponsor form posts, and `bin/test-sponsor-policy.php` greps for it; a post type of the same name would read as the role in every comparison.
2. **A key is `/^(rec|spo)[A-Za-z0-9]{14}$/`.** `WPCPM_Sponsors_Index::is_key()` is the one check; `WPCPM_Sponsors_Index::mint_key()` mints `spo` + 14 characters from `wp_generate_password( 14, false, false )`. The syncs that still talk to Airtable keep `WPCPM_Mentors_Sync::is_record_id()` for their own records; no sponsor class calls it after Task 2.
3. **Posts are the store from the first migration run.** The spec's "answers from the option until then" is replaced by: `rows()` reads posts always; a row the migration could not write is retried on every admin request until the count matches; the old option is deleted only when it does. Simpler, and nothing is lost either way.
4. **`sponsor_on_inactive` stays.** It governed the sync's revoke phase; now the Sponsors screen's status change applies it: a status set to anything but Approved detaches the accounts when the setting is `revoke` (Task 7). Its description changes, its default does not.
5. **`WPCPM_Sponsors` keeps extending `WPCPM_Sync_Module`** for its `verify()`, `redirect_back()`, `taken_status()`, `render_status_notice()` and `notice_sentence()`; `sync_class()` returns `''` with a docblock saying the module has had no sync since 1.111.0, the three sync action constants are removed, and the three sync handlers are no longer hooked. Moving the helpers up to `WPCPM_Module` would touch every module for no gain in this release.
6. **The four offer mirror keys leave the row** (`offer`, `instructions`, `more_info`, `coupon_link`) and so does `logo` (the Airtable attachment facts). The migration reads them off the old option once (Task 3) and seeds a draft primary offer where a row had offer text and no primary offer. `seed()` gains an optional second argument for exactly that (Task 5). Two keys join the row: `slug` and `notes`.
7. **A row's `manager` is a user id as a string** (`'12'`), or `''`. `manager_of()` resolves it through `get_userdata()` and `WPCPM_Manager_Booking::url_for()`; the shape it returns (`name`, `email`, `calendly`) does not change, so the three callers do not either.
8. **Interests history lives in the row's `interests` key**, appended on the site; the day's ceiling stays; the manager mail's last sentence changes to "The full history is on the Sponsors screen."
9. **The agreement's index block is the site's own summary.** `rebuild()` keeps writing `status`, `accepted_on`, `has_document` into the row, computed by the renamed `recorded_status_for()`; the six status words stay as the block's vocabulary (renamed `STATUS_*`); no PATCH, no pending mark, no retry.

---

## File structure

| File | Responsibility after this plan |
| --- | --- |
| `includes/modules/class-wpcpm-sponsors-index.php` | The store: the post type, the read contract, `is_key()`, `mint_key()`, `insert()`, `patch()`, `write()`, `manager_of()`, `delete_all()`, `forget()` |
| `includes/modules/class-wpcpm-sponsors-migration.php` (new) | The one-time migration from the held option, its report, the sync options' clean-up |
| `includes/modules/class-wpcpm-manager-booking.php` (new) | The booking link on a program manager's user profile |
| `includes/modules/class-wpcpm-sponsors-feed.php` (new) | The public REST feed |
| `includes/modules/class-wpcpm-sponsors.php` | The Sponsors screen: Add, Edit, the migration box, provisioning, accounts; no sync |
| `includes/modules/class-wpcpm-sponsor-approval.php` | The approval: mints the key, inserts the row, no Airtable |
| `includes/modules/class-wpcpm-sponsor-profile.php`, `-offers.php`, `-logo.php`, `-interests.php`, `-agreement.php` | The dashboard cards, writing to the store only |
| `includes/modules/class-wpcpm-sponsors-sync.php` | Deleted in Task 8 |
| `bin/test-sponsors-store.php`, `bin/test-sponsors-feed.php`, `bin/test-sync-schedule.php` (new) | The store, the feed, the relocated schedule checks |

---

### Task 1: The store

**Files:**
- Modify: `includes/modules/class-wpcpm-sponsors-index.php` (whole class), `wpcredits-program-manager.php` (nothing yet; the class file keeps its name)
- Create: `bin/test-sponsors-store.php`
- Test: `bin/test-sponsors-store.php`, `bin/test-roles.php` (the 20-character rule finds the new `POST_TYPE`), `bin/test-sponsor-policy.php` (the literal rule)

**Interfaces produced (every later task consumes these; the signatures are binding):**

```php
final class WPCPM_Sponsors_Index {
	const POST_TYPE       = 'wpcpm_sponsor_co';
	const META_KEY        = '_wpcpm_sponsor_key';   // the key, one meta per post, queried by value
	const META_ROW        = '_wpcpm_sponsor_row';   // the whole row, `empty_row()` shape
	const OPT_NAME        = 'wpcpm_sponsors_index'; // the OLD option; read by the migration only
	const OPT_LOGO_PREFIX = 'wpcpm_sponsor_logo_';
	const VERSION         = 2;
	const STATUS_APPROVED = 'Approved';
	const STATUSES        = array( 'Approved', 'In review', 'Paused', 'Not Moving Forward', 'Rejected' );
	const KEY_PATTERN     = '/^(rec|spo)[A-Za-z0-9]{14}$/';

	public static function register_post_type();            // on init; the application's argument set, supports title only, capability_type wpcpm_sponsor_co / wpcpm_sponsor_cos
	public static function is_key( $value );                 // bool
	public static function mint_key();                        // 'spo' + 14 alphanumerics, never one the store holds
	public static function empty_row();                       // see below
	public static function rows();                            // array<key, row>, post ID order
	public static function row( $record );                    // row|null
	public static function has( $record );                    // bool
	public static function approved();                        // array<key, row>
	public static function status_counts();                   // array<status, int>
	public static function insert( array $row );              // bool; creates or replaces by key
	public static function patch( $record, array $fields );   // bool; keys of empty_row() only
	public static function write( array $rows, $read );       // int written; the migration's bulk path (insert each)
	public static function post_id_of( $record );             // int, 0 when none
	public static function manager_of( $record );             // array{name,email,calendly}|null
	public static function logo_option( $record ); logo_record( $record ); write_logo_record( $record, array $logo ); display_logo( $record ); // unchanged
	public static function delete_all();                      // posts, meta, logo options, the migration option
	public static function forget();                          // drop the request cache (tests, writers)
}
```

`empty_row()` returns, in this order: `record_id`, `slug`, `name`, `website`, `status`, `option`, `support` (array), `product_type`, `anything`, `contact_person`, `contact_email`, `manager`, `mentors` (array), `consent` (bool), `created`, `agreement` (array status/accepted_on/has_document), `interests`, `dashboard_account` (bool), `notes`. Gone: `offer`, `instructions`, `more_info`, `coupon_link`, `logo`. `team()` and `write_team()` are removed.

**Rulings:**
- Storage: one post per sponsor, `post_type` `wpcpm_sponsor_co`, `post_status` `private`, `post_title` the trimmed name, `post_name` the slug (`sanitize_title( name )`, made unique with `wp_unique_post_slug()` against the type; kept once set: `insert()` over an existing key never changes `post_name`), `post_author` 0, meta `META_KEY` (the key) and `META_ROW` (the shaped row, with `record_id` = key and `slug` = `post_name`).
- Reads: `rows()` runs one `get_posts()` (`post_type`, `post_status => 'private'`, `numberposts => -1`, `orderby => 'ID'`, `order => 'ASC'`, `update_post_meta_cache => true`, `update_post_term_cache => false`, `suppress_filters => true`) and builds the map from `META_ROW`, skipping a post whose key fails `is_key()` or whose row is not an array; the result is held in a static for the request and dropped by `forget()`, which every writer calls after writing. `row()` returns `null` for a key that fails `is_key()` or is unknown.
- `post_id_of()` queries by `META_KEY` (`meta_key`/`meta_value`, `fields => 'ids'`, `numberposts => 1`, any status but trash).
- `insert()`: refuses a row whose `record_id` fails `is_key()` (false); shapes the row; with an existing post, updates the title and both meta; else `wp_insert_post( wp_slash( array( post_type, post_status, post_title, post_name ) ), true )`, `false` on `WP_Error`; then `update_post_meta( $id, self::META_KEY, $key )` and `update_post_meta( $id, self::META_ROW, wp_slash( $row ) )` (`wp_slash()` walks nested arrays, and `update_post_meta()` unslashes what it stores).
- `patch()`: unknown key or no post: false; merges the allowed keys, reshapes, writes the meta and the title when `name` changed; true.
- `write( $rows, $read )`: for each row with `record_id` = its key, `insert()`; returns how many were written; `$read` is ignored and kept for the signature.
- `manager_of()`: `''` or a non-numeric `manager` gives null; `get_userdata( (int) )` false gives null; else `name` = `display_name`, `email` = `user_email`, `calendly` = `WPCPM_Manager_Booking::url_for( $id )` when that class exists, else `''`. (Task 3 creates the class; until then the third value is `''`.)
- `mint_key()` loops while `post_id_of()` finds the key (at most 5 tries, then returns the last anyway: the collision is astronomically unlikely and the insert would replace, never duplicate).
- `shape()` keeps typing every key; `name` is trimmed now (the site is the record; nothing needs the base's bytes).
- `delete_all()`: every post of the type through `wp_delete_post( $id, true )`, the logo options by prefix as today, `delete_option( self::OPT_NAME )`, `delete_option( 'wpcpm_team_members' )`, `delete_option( WPCPM_Sponsors_Migration::OPT_REPORT )` when that class exists (Task 3 names it `wpcpm_sponsors_migrated`; use the literal here with a comment so this task does not depend on Task 3).
- Nothing in this task touches the twenty callers: `rows()`, `row()`, `has()`, `approved()`, `patch()`, `insert()` and `manager_of()` keep their signatures, so they keep working over the new storage. `bin/test-sponsors-sync.php` will fail after this task (it seeds through `write()` and reads `status_counts()` with the old row shape and `write_team()`): that is expected and it is deleted in Task 8; until then run the battery with it excluded and say so in the report.

**Steps:**
- [ ] Step 1: Write `bin/test-sponsors-store.php` (house style: `if ( 'cli' !== PHP_SAPI ) exit( 1 );`, `ABSPATH`, the `ck()` helper, WordPress stubs over `$GLOBALS['posts']`, `$GLOBALS['pmeta']`, `$GLOBALS['opts']`, `$GLOBALS['users']`: copy the post stubs of `bin/test-track-store.php` L70 to L184 (`wp_insert_post`, `wp_update_post`, `get_post`, `get_posts` honoring `post_type`, `post_status`, `meta_key`/`meta_value`, `fields`, `numberposts`, `orderby ID`, `get_post_meta`, `update_post_meta`, `delete_post_meta`, `wp_delete_post`), plus `sanitize_title`, `wp_unique_post_slug` (append `-2`, `-3` on a clash), `wp_generate_password( $len, $special, $extra )` returning a deterministic alphanumeric string from a counter, `get_userdata()` from `$GLOBALS['users']`, `wp_slash`/`wp_unslash`, `sanitize_text_field`, `is_email`, `trim` helpers as the other suites have them; require the real `includes/modules/class-wpcpm-sponsors-index.php`; stub `WPCPM_Manager_Booking::url_for()` returning `$GLOBALS['booking'][ $id ] ?? ''`). Checks, with these labels:
  1. `the post type name is under the limit and private` (`strlen( POST_TYPE ) <= 20`; after `register_post_type()`, `$GLOBALS['post_types']['wpcpm_sponsor_co']['public'] === false`, `show_ui === false`, `show_in_rest === false`, `capability_type === array( 'wpcpm_sponsor_co', 'wpcpm_sponsor_cos' )`).
  2. `a record id and a minted key both pass, a third shape fails` (`is_key( 'recSEED0000000001' )` true, `is_key( 'spoAAAA0000000001' )` true, `is_key( 'usr...' )` false, `is_key( 'rec12' )` false, `is_key( array() )` false).
  3. `mint_key() is spo plus fourteen characters from the store's alphabet, and never a key the store holds`.
  4. `insert() refuses a malformed key and creates one private post per new key, titled with the trimmed name and slugged from it` (insert two rows; `$GLOBALS['posts']` holds two posts of the type, status private, titles trimmed, `post_name` `weglot` and `dream-host`).
  5. `insert() over a known key replaces the row, keeps the post and keeps the slug` (rename Weglot to "Weglot SAS": same post ID, `post_name` still `weglot`, `row()['name']` new).
  6. `two sponsors with the same name get distinct slugs` (`acme`, `acme-2`).
  7. `rows() is keyed by key in post ID order, every key present and typed` (the two rows equal `empty_row()` merged with what was inserted; `consent` a bool, `mentors` an array, `slug` filled).
  8. `row(), has(), approved() and status_counts() read the store` (one Approved one In review: `approved()` has one, `status_counts()` = `array( 'Approved' => 1, 'In review' => 1 )` in first-appearance order).
  9. `patch() changes only the keys it is given, refuses an unknown key, and renames the title with the name` (patch `contact_email`; other keys unchanged; `patch( 'spoZZZZ0000000009', ... )` false; patch `name` and read `$GLOBALS['posts'][$id]->post_title`).
  10. `the request cache is dropped by every writer` (read `rows()`, `insert()` a third, `rows()` counts three without calling `forget()` by hand).
  11. `manager_of() resolves a user id to the name, the address and the booking link, and answers null for nobody` (`manager` `'7'` with `$GLOBALS['users'][7]` and `$GLOBALS['booking'][7] = 'https://calendly.example/seven'`; `manager` `''` null; `manager` `'99'` unknown null).
  12. `write() inserts every well-formed row and reports the count` (three rows, one malformed: 2).
  13. `delete_all() removes every post of the type, the logo options and the old option, and leaves other posts alone`.
  14. `the row has no offer mirror keys and no logo facts` (`array_keys( empty_row() )` equals the list in the Interfaces block, in that order).
- [ ] Step 2: Run `php bin/test-sponsors-store.php`; every check fails (the class has no `POST_TYPE`, no `is_key()`); quote three failures in the report.
- [ ] Step 3: Rewrite the class as the Interfaces block says. Keep the file header's first sentence true ("what the site knows about each sponsor, one private post per company since 1.111.0"). Register the post type on `init` from a new `init()` the module boots (Task 7 hooks it; for this task, call `register_post_type()` from `WPCPM_Sponsors::boot()` on `init` priority 5 so the type exists before anything queries it: add the one `add_action` line to `boot()` now).
- [ ] Step 4: `php bin/test-sponsors-store.php` PASS; `php bin/test-roles.php` PASS (the constant is found and under 20); `php bin/test-sponsor-policy.php` PASS; the battery silent except `bin/test-sponsors-sync.php` (expected, see the ruling); checks clean.
- [ ] Step 5: Commit: `Sponsors on the site: Task 1 - the store is a private post type, the keys are one check`.

---

### Task 2: One key check everywhere

**Files:**
- Modify: `includes/modules/class-wpcpm-sponsor-agreement.php` L324, L398, L655, L2098; `class-wpcpm-sponsor-approval.php` L303 (L358 goes in Task 4); `class-wpcpm-sponsor-members.php` L90, L191 to L192, L195 to L196, L314, L485; `class-wpcpm-sponsor-offers.php` L304, L361; `class-wpcpm-sponsor-policy.php` L147; `class-wpcpm-sponsor-posts.php` L207, L384, L430, L531, L709, L1011, L1191, L1356, L1385, L1438; `class-wpcpm-sponsor-roster.php` L124 (comment), L132, L203; `class-wpcpm-sponsors.php` L955; `class-wpcpm-institution-audit.php` L181, L341
- Test: `bin/test-sponsor-members.php`, `bin/test-sponsor-posts.php`, `bin/test-sponsor-offers.php`, `bin/test-sponsor-agreement.php`, `bin/test-sponsor-policy.php`, `bin/test-sponsors-screen.php`, `bin/test-institutions.php` or whichever suite exercises `WPCPM_Institution_Audit::record_sponsor()` (grep `record_sponsor` under `bin/`)

**Interfaces consumed:** `WPCPM_Sponsors_Index::is_key()` (Task 1).

**Rulings:**
- Every `WPCPM_Mentors_Sync::is_record_id( $x )` in the files above becomes `WPCPM_Sponsors_Index::is_key( $x )`; the policy's callable becomes `array( 'WPCPM_Sponsors_Index', 'is_key' )`. The sync (`class-wpcpm-sponsors-sync.php`) is left alone: Task 8 deletes it.
- Messages: `class-wpcpm-sponsor-members.php` L192 "That is not an Airtable record ID." becomes "That is not a sponsor key."; L196 "That sponsor is not in the index yet. Run the sponsors sync, then try again." becomes "That sponsor is not on the site yet. Add it on the Sponsors screen, then try again."; `class-wpcpm-sponsors.php` L956 (the `wpcpm_sponsor_bad_record` sentence) says "That is not a sponsor key." too.
- The audit class must not hard-depend on the sponsors index being loaded (the institutions suites load the audit alone): `class_exists( 'WPCPM_Sponsors_Index' ) ? WPCPM_Sponsors_Index::is_key( $sponsor ) : 1 === preg_match( '/^(rec|spo)[A-Za-z0-9]{14}$/', $sponsor )` in both places, with a comment naming the suites.
- Suites: every stub of `WPCPM_Mentors_Sync::is_record_id()` in the sponsor suites (inventory section 2's list) stays for the classes that still call it through the real index? No: after Task 1 the real index no longer calls it, so each sponsor suite either loads the real `class-wpcpm-sponsors-index.php` (most do) or stubs `WPCPM_Sponsors_Index` (members, posts, policy, administrators): those three stubs gain `public static function is_key( $v ) { return 1 === preg_match( '/^(rec|spo)[A-Za-z0-9]{14}$/', (string) $v ); }`. Add one check per suite that a minted key is accepted where a record id is: members ("a minted spo key attaches like a record id"), posts ("pin() stamps a spo key"), offers ("offers_of() lists a spo sponsor"), agreement ("posts_for() finds a spo sponsor's documents"), the audit suite ("record_sponsor() writes a row for a spo key").

**Steps:**
- [ ] Step 1: Add the five checks; run their suites; each new check fails with the old refusal (quote the members one: `wpcpm_sponsor_bad_record`).
- [ ] Step 2: Make the changes; `grep -n "is_record_id" includes/modules/class-wpcpm-sponsor*.php includes/modules/class-wpcpm-institution-audit.php` lists only `class-wpcpm-sponsors-sync.php` lines; suites PASS; battery silent except the sync suite; checks clean.
- [ ] Step 3: Commit: `Sponsors on the site: Task 2 - one sponsor key check, a minted key accepted everywhere a record id was`.

---

### Task 3: The booking link, the migration and the activation

**Files:**
- Create: `includes/modules/class-wpcpm-manager-booking.php`, `includes/modules/class-wpcpm-sponsors-migration.php`
- Modify: `wpcredits-program-manager.php` (two `require_once` lines after L113), `uninstall.php` (the same two after L127), `includes/modules/class-wpcpm-sponsors.php` (`boot()`: `WPCPM_Manager_Booking::init()`; `add_action( 'init', array( 'WPCPM_Sponsors_Migration', 'maybe_run' ), 6 )` right after the post type registration; `activate()` L306: replace `WPCPM_Sponsors_Sync::activate()` with `WPCPM_Sponsors_Migration::maybe_run()` so a fresh activation on a site with an old index migrates at once), `includes/modules/class-wpcpm-sponsor-offers.php` (`seed()` signature, see Task 5: this task calls `seed( $key, $legacy )` and Task 5 makes the second argument real; until then pass it and let it be ignored, which is what PHP does with an extra argument)
- Test: `bin/test-sponsors-store.php` (the migration section), a new section in `bin/test-sponsors-screen.php` is Task 7's; `bin/test-roles.php` (the "every class file is loaded" rule needs the two new files in the main file)

**Interfaces produced:**

```php
final class WPCPM_Manager_Booking {
	const META = 'wpcpm_booking_url';
	public static function init();                 // hooks show_user_profile, edit_user_profile, personal_options_update, edit_user_profile_update
	public static function url_for( $user_id );    // string, '' when none
	public static function set( $user_id, $url );  // bool; https URL or '' clears; refuses anything else
	public static function render_field( $user );  // the profile row, for a user who holds CAP_MANAGE, drawn to the user themself or to somebody who can edit_user
	public static function save_field( $user_id ); // reads $_POST['wpcpm_booking_url'] when the profile form's own nonce passed (WordPress checked it before firing the action) and the actor may edit that user
}

final class WPCPM_Sponsors_Migration {
	const OPT_REPORT = 'wpcpm_sponsors_migrated'; // autoloaded: array( 'complete' => bool, 'rows' => int, 'posts' => int, 'seeded' => int, 'unmapped' => string[], 'at' => int, 'runs' => int )
	public static function maybe_run();  // nothing when complete; otherwise run()
	public static function run();        // the migration, returns the report array
	public static function report();     // the stored report or the empty shape
}
```

**Rulings:**
- `run()` reads `get_option( 'wpcpm_sponsors_index' )` (the old shape: `v` 1, `rows` keyed by record id) and `get_option( 'wpcpm_team_members' )`. With no old option at all (a fresh site), it writes a complete report with zero rows and returns. For each old row: skip a key that fails `is_key()`; map `manager`: the team row under that Team Members id gives an email; `get_user_by( 'email', $email )` gives the user id as a string, else `''` and the sponsor's name joins `unmapped`; build the new row from the old keys that survive (`name`, `website`, `status`, `option`, `support`, `product_type`, `anything`, `contact_person`, `contact_email`, `mentors`, `consent`, `created`, `agreement`, `interests`, `dashboard_account`), `notes` `''`; `insert()`; when it returns true and the old row had a non-empty `offer`, `instructions`, `more_info` or `coupon_link` and `WPCPM_Sponsor_Offers::offers_of( $key )` is empty, call `WPCPM_Sponsor_Offers::seed( $key, array( 'offer' => ..., 'instructions' => ..., 'more_info' => ..., 'coupon_link' => ... ) )` and count `seeded`. Seed booking links: for every team row with an email whose user exists and holds `CAP_MANAGE` and has no booking link yet, `WPCPM_Manager_Booking::set( $id, $calendly )`. Then count the posts of the type; `complete` = every well-formed old row has a post. When complete: `delete_option( 'wpcpm_sponsors_index' )`, `delete_option( 'wpcpm_team_members' )`, delete the six sync options (`wpcpm_sponsors_state`, `wpcpm_sponsors_report`, `wpcpm_sponsors_last_sync`, `wpcpm_sponsors_last_error`, `wpcpm_sponsors_lock`, `wpcpm_sponsors_sync_shrink`, as literals with a comment: the class that declared them goes in Task 8), `wp_clear_scheduled_hook( 'wpcpm_sponsors_daily' )` and `wp_clear_scheduled_hook( 'wpcpm_sponsors_sync_tick' )`. Write the report (autoload true: read on every request until complete, and one autoloaded row is cheaper than a query per request for ever, the same reasoning as `OPT_CAPS_REPAIRED`). The report option is the record of every run; no audit row is written, because the audit log is keyed by an institution or a sponsor and a migration is about neither.
- `maybe_run()` returns at once when the report says complete; otherwise runs (so a failed row is retried on the next request) but at most once a minute (a transient `wpcpm_sponsors_migrating`, 60 s) so a stuck insert does not run on every request.
- Idempotent: running `run()` twice on the same option yields the same posts (Task 1's `insert()` replaces by key) and `seeded` stays 0 the second time (the offers exist).
- The booking field: rendered inside a `<table class="form-table">` with `<th><label for="wpcpm_booking_url">Booking link</label></th><td><input type="url" ...><p class="description">The link a sponsor or an institution presses to book a call with you. An https address.</p></td>`; only for a user who holds `CAP_MANAGE`; saved by `save_field()` for the user themself or an actor who `current_user_can( 'edit_user', $user_id )`.

**Steps:**
- [ ] Step 1: Checks first, in `bin/test-sponsors-store.php` under a heading `=== The migration ===` (stub `get_user_by( 'email' )` over `$GLOBALS['users']`, `user_can()` through `bin/stubs/caps.php`, `wp_clear_scheduled_hook()` recording `$GLOBALS['cleared']`, `WPCPM_Sponsor_Offers` stub with `offers_of()` from `$GLOBALS['offers']` and `seed( $key, $legacy = array() )` recording `$GLOBALS['seeded'][] = array( $key, $legacy )`; the real booking class), and a new `bin/test-manager-booking.php` (the field is drawn for a manager and not for a student; `set()` refuses `http://` and `javascript:`; `save_field()` refuses an actor who may not edit the user; `url_for()` empty by default):
  1. `an old index of three rows becomes three private posts under the same keys, and the report says complete` (rows keyed `recSEED0000000001` to `...3`, one with a `manager` Team Members id whose team row's email matches user 7; after `run()`: `WPCPM_Sponsors_Index::rows()` has the three keys, `row( ...1 )['manager'] === '7'`, `report()['complete'] === true`, `rows === 3`, `posts === 3`).
  2. `a manager the site has no account for is left empty and named in the report` (`unmapped === array( 'DreamHost' )`).
  3. `a row with offer text and no primary offer seeds a draft offer with the four legacy values; a row that has an offer does not` (`$GLOBALS['seeded']` holds one entry with the legacy array; `seeded === 1`).
  4. `the booking link is seeded once from the team by address, and an existing link is kept`.
  5. `when every row is a post, the old option, the team option, the six sync options and the two hooks are gone` (`$GLOBALS['opts']` lacks the eight names; `$GLOBALS['cleared']` = the two hook names).
  6. `a second run changes nothing: same posts, nothing seeded, still complete`.
  7. `a row the store refused keeps the old option and the report says so` (make `wp_insert_post` fail for one title via a `$GLOBALS['insert_fail']` flag: `complete === false`, `posts === 2`, the old option still there; clear the flag, run again: complete).
  8. `a site with no old index reports complete with zero rows` (`$GLOBALS['opts']` empty: `complete === true`, `rows === 0`).
  9. `maybe_run() does nothing once complete and at most once a minute before` (count calls to `get_option( 'wpcpm_sponsors_index' )`).
- [ ] Step 2: Run both suites; the migration checks fail (class not found); quote two.
- [ ] Step 3: The two classes, the require lines, the boot and activation edits.
- [ ] Step 4: Suites PASS; `php bin/test-roles.php` PASS; battery silent except the sync suite; checks clean.
- [ ] Step 5: Commit: `Sponsors on the site: Task 3 - the booking link on a manager's profile, and the migration that turns the held index into posts`.

---

### Task 4: The approval and the account flag, off Airtable

**Files:**
- Modify: `includes/modules/class-wpcpm-sponsor-approval.php` (`approve()` L154 to L178, `create()` L335 to L363 removed, `payload()` L269 to L292 removed, `logo_cells()` L402 to L425 removed if nothing else calls it, `row()` L373 to L394, `stamped_record()` L303, the class docblock), `includes/modules/class-wpcpm-sponsors.php` (`mark_dashboard_account()` L941 to L977; `provision()` L674 and L695 to L701; `handle_members()` L741, L755; `messages()` L455, L458, L467 to L468, L798, L865, L888), `includes/modules/class-wpcpm-sponsor-application.php` (strings L2656, L2665, L2671 to L2672, L2705, L3273, L3281, L3493, L3577, L3759 to L3761, L3970, L4008, L4011, L4016, L4121; `manager_messages()` loses `sapp-airtable`)
- Test: `bin/test-sponsor-approval.php`, `bin/test-sponsors-screen.php`, `bin/test-sponsor-application.php`

**Interfaces consumed:** `WPCPM_Sponsors_Index::mint_key()`, `insert()`, `is_key()`, `patch()` (Task 1).

**Rulings:**
- `approve()` step 1: `if ( '' === $record ) { $record = WPCPM_Sponsors_Index::mint_key(); update_post_meta( ..., META_RECORD, $record ); self::event( ..., EVENT_RECORD_CREATED, ... ); }` with the event's wording "sponsor record created on the site"; step 2 unchanged in shape (`has()` then `insert( self::row( $record, $stored ) )`); the row adds `'slug' => ''` (the store slugs it) and drops `logo`; `created` stays today; `dashboard_account` true. Seven steps now; renumber the comments and the journal the suite reads (`account, category, logo, offer, invite, audit` after the row).
- `mark_dashboard_account( $record, $flag )`: refuses a key that fails `is_key()` (`wpcpm_sponsor_bad_record`), then `return WPCPM_Sponsors_Index::patch( $record, array( 'dashboard_account' => (bool) $flag ) )` (bool: true when the row existed). Its docblock says it is the one writer of that flag. The three callers no longer branch on `airtable-failed`; that status leaves `messages()`; a false return (unknown key) maps to `provision-failed` / `refused`.
- Strings: `provision-no-email` becomes "That sponsor has no contact address. Add one on its Edit form, then try again."; `offer-seeded` "The first offer was created in draft; the sponsor completes it and switches it on from the Sponsor Dashboard."; L798 the same; L865 the seed button "Create the first offer for %s"; L888 "(shown in the program records)" becomes "(primary)". Application: `sapp-approved` "The application is approved. The sponsor record, the account, the category, the logo and the first offer are in place, and the welcome is queued."; `sapp-half-done` "This application's approval is half done: a sponsor record already exists for it. Press Approve again to finish, then decide what you like."; `sapp-account` and L2705 "The sponsor record was created, but the account could not be made..."; L3273 "Create a sponsor record with Status Approved and a site account for %1$s, and email a password-set link to %2$s? The record can be edited on the Sponsors screen afterwards."; L3281 "The site already holds a sponsor with this name or website, and approving creates a second one. If it is the same company, reject this application and use Create account on the Sponsors card instead."; L3493 "An earlier press of Approve created the sponsor record for this application and then stopped, so a record with Status Approved already stands for the company."; L3577 "... its logo files and what the site already holds, then decide it..."; L3759 "The site already holds a company with this name or this website."; L3760 "already on the site"; L3761 "Approving would create a second record; open it and read what the site has."; L3970 "No logo file was sent. The company can upload one on the Sponsor Dashboard after approval."; L4008 "What the site already has"; L4011 "No sponsor on the site carries this name or this website. Approving creates one."; L4016 "These sponsors match on the name or on the website host. Approving creates a second record: if this is the same company, reject the application and use Create account on the Sponsors card instead." (unchanged); L4121 "The site already holds a sponsor with this name or website. Approving creates a second record." The signal name `SIGNAL_IN_BASE` may stay; its label in `render_queue()` reads "already on the site".
- After this task `grep -n "Airtable\|the base" includes/modules/class-wpcpm-sponsor-approval.php includes/modules/class-wpcpm-sponsor-application.php` prints nothing but the application's comment about the retired Airtable form (rewrite that comment to say the form replaced it in 1.97.0).

**Steps:**
- [ ] Step 1: Rewrite the approval suite's Airtable section (L442 to L532 and L552 to L602): the stub `WPCPM_Airtable` is removed and a fatal is the proof it is never constructed (`class_exists( 'WPCPM_Airtable' ) === false` at the end of the suite); new checks: `a press mints a spo key, stamps it and inserts the Approved row with the application's answers` (the stamp passes `is_key()`, starts with `spo`, `row()` holds name, website, status, option, anything, contact_person, contact_email lowercased, consent true, dashboard_account true, created today); `the side effects ran in the spec's order: key, row, account, category, logo, offer, invite, audit`; `a second press creates no second record` (same key); `the half-done path finishes the rest in order`; keep every non-Airtable check. Screen suite: L464 and L504 become `the row's account flag is true at once` / `false at once`, L546 and L548 become `mark_dashboard_account() answers false for a key the store does not hold` / `and refuses a malformed key`; the `$GLOBALS['patched']` stub of `WPCPM_Airtable` goes from both suites. Application suite: the `in-base` label check reads "already on the site".
- [ ] Step 2: Run the three suites; the new checks fail; quote two.
- [ ] Step 3: The changes.
- [ ] Step 4: Suites PASS; battery silent except the sync suite; checks clean; the grep in the last ruling is empty.
- [ ] Step 5: Commit: `Sponsors on the site: Task 4 - the approval mints a key and inserts the row; the account flag is a store patch`.

---

### Task 5: Profile, offers and logo, off Airtable

**Files:**
- Modify: `includes/modules/class-wpcpm-sponsor-profile.php` (`FIELDS` L37 to L70 loses `offer`, `instructions`, `more_info`; `OFFER_FIELDS` L73 and `owned_by_offer()` and the `$owned` branches in `handle_save()` L247 to L255 and `render()` L363 to L379 go; `handle_save()` L282 to L295 becomes `WPCPM_Sponsors_Index::patch()` with `profile-failed` when it returns false; the audit message stays; strings L112 "Your profile was saved.", L115 "Your profile could not be saved right now. Try again later.", L406 "This changes the address the program holds for your company. It does not change who can sign in: ask your program contact for that."; the file header), `includes/modules/class-wpcpm-sponsor-offers.php` (`MIRROR` L77 to L81 and `MIRROR_INDEX` L84 to L88 removed; `mirror()` L832 to L873 removed; `handle_save()` L1101 to L1102 and L1134 to L1136 removed, the audit's `mirrored` datum removed; `offer-mirror-failed` L888 removed; L1381 the primary mark reads "(primary)"; `seed( $record, array $legacy = array() )` L560 to L622: the four values come from `$legacy` (`offer`, `instructions`, `more_info`, `coupon_link`), each `''` when absent; the header comment L20 to L23), `includes/modules/class-wpcpm-sponsor-logo.php` (`write_airtable()` L518 to L547, `clear_airtable()` L555 to L557, `patch_logo()` L566 to L580 removed; upload: L242 to L244 removed; remove: L297 to L310 become an unconditional reset of the logo record, no `patch( 'logo' )`; audit messages "A logo was uploaded on the Sponsor Dashboard." and "The logo was removed."; `data.airtable` gone; `logo-removed-airtable` and `logo-airtable` removed from `messages()`; `logo-saved` "Your logo is saved."; `logo-removed` "Your logo is removed. Upload a new one whenever you like."; `logo-not-site` "There is no uploaded logo to remove here."; the note L371 loses "What you upload here replaces the logo in the program records."; the confirm L426 "Remove the logo you uploaded? The files stay in the Media Library, so anything that already shows them keeps working."; the file header L27)
- Test: `bin/test-sponsor-cards.php` (profile section L371 to L425), `bin/test-sponsor-offers.php` (L313 to L330 seed section, L440 to L449 mirror section, L578 to L579, L621, L723), `bin/test-sponsor-logo.php` (L320 to L392)

**Interfaces consumed:** `WPCPM_Sponsors_Index::patch()`, `row()` (Task 1); the migration's `seed( $key, $legacy )` call (Task 3).

**Rulings:**
- The profile card keeps five editable fields (`website`, `contact_person`, `contact_email`, `product_type`, `anything`) and draws, in place of the three offer fields, one note: "Your offer's text, instructions and link are edited on the Offers card." when the sponsor has any offer, and nothing when it has none (Create account and the approval seed one, so the case is rare).
- `seed()` with an empty legacy array creates the draft primary offer titled with the sponsor's name, kind codes, empty text and instructions, no url, no shared code. With a legacy array the rules of today apply to its four values (the sheet hosts rule included).
- The logo record's `source` keeps its two values; `airtable` can only come from a migrated row and is left as is (the picture is the site's copy already).
- Every suite assertion that inspects `$GLOBALS['patched']` becomes an assertion on the store (`row()`) and a proof that the suite defines no `WPCPM_Airtable` class at all (`class_exists( 'WPCPM_Airtable' )` false at the end of each suite). The offers suite's L723 source scan ("nothing about a claim ever reaches Airtable") widens to the whole module: `grep -L "WPCPM_Airtable" includes/modules/class-wpcpm-sponsor*.php` is every file but the sync (until Task 8 deletes it; the check lists the sync file as the one allowed exception and Task 8 removes the exception).

**Steps:**
- [ ] Step 1: Checks first, in the three suites: profile `the five fields, and no offer field` (`array_keys( WPCPM_Sponsor_Profile::FIELDS )`), `a changed field is written to the store and audited by name`, `an unknown sponsor is refused before anything is written`; offers `seed() with no legacy values makes an empty draft primary offer titled with the sponsor's name`, `seed() with legacy values keeps the old rules` (the existing seed checks re-pointed at `$legacy`), `a save never mirrors: the audit datum is gone and no Airtable class exists`; logo `upload writes the logo record and the audit row, and nothing else`, `remove resets the record unconditionally and says so`, `no Airtable class exists`.
- [ ] Step 2: Run; the new checks fail; quote two.
- [ ] Step 3: The changes.
- [ ] Step 4: Suites PASS; battery silent except the sync suite; checks clean; `grep -n "Airtable\|program records\|the base" includes/modules/class-wpcpm-sponsor-profile.php includes/modules/class-wpcpm-sponsor-offers.php includes/modules/class-wpcpm-sponsor-logo.php` is empty.
- [ ] Step 5: Commit: `Sponsors on the site: Task 5 - the profile, the offers and the logo write to the store alone`.

---

### Task 6: Interests and the agreement, off Airtable

**Files:**
- Modify: `includes/modules/class-wpcpm-sponsor-interests.php` (`handle_save()` L146 to L190: no `get_record()`, no `update_records()`; the history is `trim( (string) $row['interests'] )`, the append the same, `patch( 'interests' [, 'support'] )`, `interest-failed` when the patch returns false; the mail body's last sentence "The full history is on the Sponsors screen."; `FIELD_LOG`/`FIELD_SUPPORT` constants become plain comments or go; `interest-failed` L60 "Your message could not be saved right now. Try again later."), `includes/modules/class-wpcpm-sponsor-agreement.php` (`patch()` L1847 to L1864, `field()` L1833 to L1837, `retry_airtable()` L634 to L676, `clear_pending()` L687 to L689, `META_AIRTABLE_PENDING` L77 and its four writes removed; `airtable_status_for()` renamed `recorded_status_for()`; the `AIRTABLE_*` constants renamed `STATUS_*` with the same six words; every handler's `if ( ! self::patch( ... ) ) { bounce( 'agreement-airtable' ); }` block removed and the `rebuild( $record, array( 'status' => <the status> [, 'accepted_on' => today, 'document' => ...] ), true )` call kept; `agreement-airtable` and `agreement-not-saved` messages removed, `agreement-accepted` "The agreement is accepted, with today's date, and everybody at the company has been emailed."; `agreement-unknown` "Nothing was recorded. That company is not on the site; add it on the Sponsors screen and try again."; `summary()` L362 key `airtable_status` renamed `recorded_status`; the class docblock and comments naming the base and the sync (L24, L76, L381, L392, L613 to L620, L740, L877, L982, L986, L1059)), `includes/modules/class-wpcpm-sponsors.php` (`render_agreement_state()` L1571 to L1580: one sentence, `__( 'This site: %s.' )` with the agreement word; L1533 to L1534 the accept confirm "Accept the signed agreement from %1$s? It is recorded as accepted with today's date and the %2$s person at the company is emailed. ..." with the plural form; L1616 "Put this agreement back in force? Everybody at the company is emailed.")
- Test: `bin/test-sponsor-cards.php` (interests L427 to L487), `bin/test-sponsor-agreement.php` (sections L610 to L660 and L978 to L990 go; every `patched_cells()` assertion becomes a `row()['agreement']` assertion), `bin/test-sponsors-screen.php` (L1576 sentence)

**Rulings:**
- The agreement's index block is written by `rebuild()` exactly as today, so the card and the screen keep reading it; the block's `status` is one of the six words and `recorded_status_for()` computes it from the posts.
- The interests card keeps the ceiling, the mail and the audit row; the order becomes: ceiling, build the line, patch the store, mail, audit, leave.

**Steps:**
- [ ] Step 1: Checks first: interests `an interest appends one dated line to the store's history and mails the manager` (row `interests` after two saves has two lines, newest last), `the support choices are written to the row when ticked and left alone when not`, `a store that refuses is said, and no mail goes`; agreement `an upload records Awaiting review in the row at once`, `accept records Accepted with today's date`, `withdraw records the status the remaining documents give`, `on file records the Drive link as a document held`, `no pending mark exists in the class` (source scan for `airtable` case-insensitive in the file: zero hits), `no Airtable class exists`.
- [ ] Step 2: Run; the new checks fail; quote two.
- [ ] Step 3: The changes.
- [ ] Step 4: Suites PASS; battery silent except the sync suite; checks clean; `grep -in "airtable" includes/modules/class-wpcpm-sponsor-interests.php includes/modules/class-wpcpm-sponsor-agreement.php` is empty.
- [ ] Step 5: Commit: `Sponsors on the site: Task 6 - interests and the agreement keep their record on the site`.

---

### Task 7: The Sponsors screen: Add, Edit, the migration box, the status rule

**Files:**
- Modify: `includes/modules/class-wpcpm-sponsors.php` (constants L24 to L26 removed; `sync_class()` L254 returns `''` with the docblock of ruling 5; `boot()` L279 removed, L284 to L285 and L291 removed, two new handlers hooked; `render_admin_page()` L982 to L1022: no `progress`/`last`, no "Airtable is not connected" notice, no "Last sync error"; the migration box in place of `render_sync_panel()` (which goes, L1065 to L1134); the edit card first when `?edit=<key>` names a known key; `render_index()` L1167 to L1225: empty text "No sponsors yet. Add one below or approve an application.", the Logo column reads `logo_record()['colour'] > 0 ? 'On the site' : 'None'`, an Edit link per row, the "Add a sponsor" form under the table; `messages()` gains `sponsor-added`, `sponsor-saved`, `sponsor-rejected`, `sponsor-unchanged`, `sponsor-detached` and loses `airtable-failed`; `deactivate()` L359 and `uninstall()` L377 to L382, L422 to L423 lose the sync (Task 8 deletes the class; this task removes the calls)), `includes/modules/class-wpcpm-sponsors-dashboard.php` L479 (the group lead: "... what you save here is kept by the program ..."), `includes/modules/class-wpcpm-sponsor-mentors.php` (L292 to L296 "The mentor list refreshes with the next program sync." becomes "The mentor list refreshes with the next mentors sync.")
- Test: `bin/test-sponsors-screen.php` (L448 the contract check, L537 the sync panel check, the new Add/Edit sections, L1576), `bin/test-sponsors-dashboard.php`

**Interfaces produced:**

```php
// class WPCPM_Sponsors
const ACTION_ADD  = 'wpcpm_sponsor_add';   // admin_post; nonce wpcpm_sponsor_add
const ACTION_EDIT = 'wpcpm_sponsor_edit';  // admin_post; nonce wpcpm_sponsor_edit_<key>
const LOG_ADDED   = 'sponsor_added';
const LOG_EDITED  = 'sponsor_edited';
public function handle_add();   // fields wpcpm_name (required), wpcpm_website, wpcpm_status (one of STATUSES, default In review), wpcpm_contact_person, wpcpm_contact_email, wpcpm_option, wpcpm_support[], wpcpm_product_type, wpcpm_manager, wpcpm_notes
public function handle_edit();  // the same fields plus wpcpm_sponsor (the key) and wpcpm_mentors[] (mentor record ids)
public static function clean_fields( array $posted, array $current );  // array{ok: bool, row: array, changed: string[]} shared by both handlers
```

**Rulings:**
- Validation in `clean_fields()`: `name` trimmed, required, at most 200; `website` through `WPCPM_Field_Value::clean_url()` or `''`; `status` must be in `WPCPM_Sponsors_Index::STATUSES`; `contact_email` `sanitize_email` and `is_email` or `''`; `contact_person` at most 200; `option` one of the three `Sponsorship options` words the application form offers (`WPCPM_Sponsor_Application` holds them) or `''`; `support` the intersection with `WPCPM_Sponsor_Interests::CHOICES`; `product_type` one of `WPCPM_Sponsor_Profile::CHOICES['Type of product']` or `''`; `manager` `''` or the id of a user in `WPCPM_Institutions::managers()`; `mentors` the intersection with the keys of `WPCPM_Mentors_Sync::sponsorship()`; `notes` `sanitize_textarea_field`, at most 4000. Anything else refused: `sponsor-rejected`, nothing written.
- `handle_add()`: `insert()` a row with `mint_key()`, `created` today, `consent` false, `dashboard_account` false; audit `sponsor_added` (ground manager, evidence manager, data: the fields set); flash `sponsor-added`; redirect to the edit form of the new key.
- `handle_edit()`: the key must be known (`refused` otherwise); `patch()` the changed keys; audit `sponsor_edited` with the changed names; when `status` changed away from Approved and `WPCPM_Settings::get_value( 'sponsor_on_inactive' )` is `revoke`, detach every live member (`WPCPM_Sponsor_Members::detach( $id, REASON_REVOKED, $actor )`) and `mark_dashboard_account( $key, false )`, flash `sponsor-detached` ("Saved. The sponsor is no longer Approved, so its accounts were detached, as the setting says."); otherwise `sponsor-saved` or `sponsor-unchanged`.
- The edit form draws: name, website, status (select), contact person, contact email, sponsorship shape (option select, support checkboxes, product type select), assigned manager (select of `managers()` with "Nobody yet"), sponsored mentors (checkboxes, active mentors first, each "Name (status)"), notes (textarea), Save; the key and the slug are shown read-only under the form ("Key: spo...; listing handle: weglot").
- The migration box (only while `WPCPM_Sponsors_Migration::report()['complete']` is false): heading "Moving sponsors onto the site", the counts ("N of M rows are posts"), the unmapped names, and a "Run again" form posting `admin_post_wpcpm_sponsors_migrate` (nonce `wpcpm_sponsors_migrate`, handler calls `run()` and redirects with `migrated`).
- The `bin/test-sponsors-screen.php` contract check L448 becomes: the class extends `WPCPM_Sync_Module`, `sync_class()` is `''`, no sync action constant is non-empty, and `boot()` hooks no sync handler (source scan for `handle_sync`, `handle_cancel`, `handle_tick` in `boot()`: none).

**Steps:**
- [ ] Step 1: Checks first: `Add creates an In review sponsor under a minted key with the fields given and lands on its edit form`, `a name is required`, `Edit changes the fields it is given and audits their names`, `an unknown status, a bad address or an unknown manager rejects the whole save`, `mentors are limited to the sponsorship index`, `a status set away from Approved detaches the accounts when the setting says revoke, and not when it says keep`, `the migration box shows while the migration is incomplete and not after`, `the index table links Edit and says Logo On the site or None`, `the sync panel is gone`, `no Airtable notice, no Airtable class`.
- [ ] Step 2: Run; fail; quote two.
- [ ] Step 3: The changes.
- [ ] Step 4: Suites PASS; battery silent except the sync suite; checks clean.
- [ ] Step 5: Commit: `Sponsors on the site: Task 7 - the Sponsors screen owns identity: Add, Edit, the migration box`.

---

### Task 8: The sync goes

**Files:**
- Delete: `includes/modules/class-wpcpm-sponsors-sync.php`, `bin/test-sponsors-sync.php`, `bin/fixtures/sponsors-table-fields.json`
- Create: `bin/test-sync-schedule.php` (the relocated checks: the "five distinct minutes" offset check becomes four, over students 30, mentors 60, institutions 90 and the duplicate scan 150; the mentors sync's schedule checks L867 to L905; the mentors sync's `sponsorship_row()` and option-name checks L910 to L916 with `company` gone; the agreement discard cadence L861)
- Modify: `wpcredits-program-manager.php` L114 and `uninstall.php` L128 (the `require_once` lines go), `includes/modules/class-wpcpm-mentors-sync.php` (`fields()` L188 `mentor_sponsor_company` removed; `sponsorship_row()` L935 `company` removed; `phase_lookups()` L769 `companies` removed; `lookups()` L893 to L905 `companies` removed; the comment L33 and L46), `includes/modules/class-wpcpm-administrators-cards.php` L1098 to L1108 removed, `includes/class-wpcpm-admin.php` (L506 the Team Members row removed; L814 "nothing is created in Airtable until somebody approves it" becomes "nothing becomes a sponsor until somebody approves it"; L831 to L841 the radio's description "The status is the record. Paused and Not Moving Forward sponsors keep their accounts by default, because a pause is often short; choose the other answer to have a status change on the Sponsors screen detach them." and the second label "Detach its accounts when the status changes"; L856 "Applies to the logos sponsors upload. PNG, JPEG and WebP only; never SVG."), `includes/class-wpcpm-settings.php` (defaults `team_members_table` L64, `sponsors_table` L65, `sponsors_name_field` L71 removed; the sanitizer loop L284 loses the two table keys), `includes/tools/class-wpcpm-duplicates-scan.php` (comments L16, L49, L119), `includes/modules/class-wpcpm-sponsor-logo.php` L27 comment, `includes/modules/class-wpcpm-sponsor-application.php` L127, L614 comments, `includes/modules/class-wpcpm-sponsor-offers.php` L75 comment, `includes/modules/class-wpcpm-sponsor-profile.php` L17 comment
- Test: `bin/test-settings.php` (probes L213, L216, L259 removed; L747 and L761 removed), `bin/test-fixtures.php` (L437 to L464 removed; L477 to L481 "the two sponsorship columns and the expertise column exist"; L445 to L446 in the table-id pins), `bin/test-administrators-dashboard.php` (L573 to L578 stub removed, L792 removed, L960 "three syncs with their state" = 3), `bin/test-uninstall.php` (`$other_hooks` L689 loses the two hooks; the pairs L883 to L884 go; `$plugin_types` L634 gains `wpcpm_sponsor_co`; `$named_options` gains `wpcpm_sponsors_migrated`), `bin/test-sponsor-cards.php` L342 to L344 and L373 to L376 (fixture reads go; the product choices are pinned in the suite as the three words), `bin/test-sponsor-approval.php` L442 to L451 (fixture reads go), `bin/test-sponsor-application.php` L595 to L611 (the eight columns pinned in the suite by name; the three `Sponsorship options` words pinned there), `bin/test-semester-report.php` L467 (`companies` key goes), `bin/test-students-sync.php` (the mentors field map assertion loses `mentor_sponsor_company`), `bin/test-sponsors-screen.php` L389 (no longer loads the sync)

**Rulings:**
- `bin/check-references.php` must be clean after the delete: run it first to list every `WPCPM_Sponsors_Sync::` reference left (the inventory's section 5 table is the expected list) and remove each.
- The Administrator Dashboard's syncs card lists three; the Sponsors strip is untouched.
- `bin/test-uninstall.php` L891 "the names seeded above are the ones the classes declare" keeps passing once the two sync pairs are gone.
- The mentors fixture keeps the `Sponsor Company Name` column (the base has it); only the plugin stops reading it.

**Steps:**
- [ ] Step 1: Create `bin/test-sync-schedule.php` from the relocated checks and run it green against the current code (it must pass before the deletion, since it pins existing behavior); adjust the settings, fixtures, dashboard and uninstall suites first and quote their failures.
- [ ] Step 2: Delete the three files, make the changes; `php bin/check-references.php` clean; every suite PASS; the battery silent (no exception any more); `bash bin/check-standards.sh` exit 0; `grep -rn "Sponsors_Sync\|sponsors_table\|sponsors_name_field\|team_members_table" includes/ bin/ wpcredits-program-manager.php uninstall.php` is empty.
- [ ] Step 3: Commit: `Sponsors on the site: Task 8 - the sponsors sync, its settings and its fixture are gone; the schedule checks have a suite of their own`.

---

### Task 9: The feed

**Files:**
- Create: `includes/modules/class-wpcpm-sponsors-feed.php`, `bin/test-sponsors-feed.php`
- Modify: `wpcredits-program-manager.php`, `uninstall.php` (the `require_once`), `includes/modules/class-wpcpm-sponsors.php` (`boot()`: `WPCPM_Sponsors_Feed::init()`), `includes/modules/class-wpcpm-sponsors-index.php` (`forget()` also `delete_transient( WPCPM_Sponsors_Feed::TRANSIENT )` when the class exists)
- Test: `bin/test-sponsors-feed.php`, `bin/test-roles.php`

**Interfaces produced:**

```php
final class WPCPM_Sponsors_Feed {
	const ROUTE     = '/sponsors';           // under wpcpm/v1
	const TRANSIENT = 'wpcpm_sponsors_feed'; // 15 minutes
	const CEILING   = 'sponsors-feed';       // per address: 60 an hour
	public static function init();                          // rest_api_init -> register_route()
	public static function register_route();                // GET, permission_callback '__return_true'
	public static function rest_sponsors( WP_REST_Request $request ); // WP_REST_Response
	public static function build();                         // array{sponsors: array, applications: array, generated: string}
}
```

**Rulings:**
- `rest_sponsors()`: the ceiling first, `WPCPM_Ceiling::claim( WPCPM_Ceiling::key( self::CEILING, wp_hash( WPCPM_Form_Guard::actor_ip() ) ), 60, HOUR_IN_SECONDS )`; refused: status 429 with `array( 'code' => 'wpcpm_feed_busy', 'message' => 'Too many requests. Try again in an hour.' )`. Then the transient or `build()`, stored 15 minutes; the response carries `Cache-Control: public, max-age=900`.
- `build()['sponsors']`: for each `approved()` row, ordered by name case-insensitively: `name`, `slug`, `website`, `logo` (`display_logo()['url']` or `''`), `mentors` (`count( $row['mentors'] )`), `since` (`substr( $row['created'], 0, 7 )` or `''`). Never `contact_person`, `contact_email`, `notes`, `manager`, `interests`, `agreement`, `status`, `key`.
- `build()['applications']`: the last 24 months as `YYYY-MM` keys, each `array( 'received' => n, 'approved' => n )`: received counts `WPCPM_Sponsor_Application::POST_TYPE` posts by `post_date` month (one `get_posts` with `fields => ids` and `date_query` is heavier than needed; read `post_date` off the objects); approved counts `approved()` rows by `created` month.
- `generated`: `gmdate( 'c' )`.

**Steps:**
- [ ] Step 1: The suite: `the route is public and GET only`, `an answer lists the Approved sponsors by name with six public keys and no other`, `a Paused sponsor is absent`, `applications are counted by month for 24 months`, `the answer is cached for fifteen minutes and dropped by a store write`, `the sixty-first request from one address in an hour is refused with 429`, `the header says public, max-age=900`. Run: fails (class missing).
- [ ] Step 2: The class, the require lines, the boot line, the `forget()` line.
- [ ] Step 3: Suite PASS; `php bin/test-roles.php` PASS; battery silent; checks clean.
- [ ] Step 4: Commit: `Sponsors on the site: Task 9 - a public feed of the Approved sponsors and the monthly application counts`.

---

### Task 10: Guides, readme, the release

**Files:**
- Modify: `docs/sections/34-admin-operations.md` (L17 to L27: three syncs; L58 to L61: "their program details are untouched" without "in Airtable"; L87 to L107: the Sponsors section rewritten as below; the data table: the six sync rows, `wpcpm_sponsors_index` and `wpcpm_team_members` rows go, `wpcpm_sponsors_migrated` and the post type join), `docs/sections/30-admin-wpadmin.md` L24 and L36 ("every sponsor with its status..." without "the sponsors sync"; "Add a sponsor and Edit"), `docs/sections/31-admin-settings.md` L49 to L53 (no sponsors sync), `docs/sections/40-sponsor-dashboard.md` L5 ("What you save here is kept by the program, so keep it current"), `docs/sections/41-sponsor-offers.md` L9 ("the one live offer marked *primary* is the offer the program names as yours"), `docs/sections/42-sponsor-visibility.md` L8 ("... are kept on this site, where program managers read them."), `readme.txt` (L3 tags keep `airtable`, the other modules use it; L11 unchanged; L20 "the Sponsor role, the sponsor records kept on the site, one-at-a-time account creation, the Sponsor Dashboard, the public sponsor application form, the sponsors' guide, the public sponsors feed, and the sponsor queues on the Administrator Dashboard. **Built.**"; Stable tag 1.111.0; a `= 1.111.0 =` entry above the newest), `wpcredits-program-manager.php` (header `Version` and `WPCPM_VERSION` 1.111.0), every `block.json` version, `languages/wpcredits-program-manager.pot` (`sh bin/make-pot.sh`)
- Test: the whole battery; `php bin/build-docs.php`; `bash bin/build` (version inside 1.111.0)

**The Sponsors section of the program managers' guide, to write in full:** what the Sponsors screen is now (the record of every sponsor lives on this site since 1.111.0; a sponsor is added by approving an application or with Add a sponsor; Edit holds the name, website, status, contacts, the sponsorship shape, the assigned program manager and their booking link (a field on the manager's own profile), the sponsored mentors and private notes; a status set away from Approved detaches the accounts when the setting says so); the migration box (what it shows, that it disappears when done); the feed (its address, what it lists, that it holds no contact data, that the Tracker and the Monthly Report read it); the two manual steps (rename the Airtable Sponsors table as an archive; retire the seven Airtable automations that answered the old interest form).

**The readme entry** names: the record on the site, the keys kept, the migration and its box, the Sponsors screen's Add and Edit, the manager's booking link, the sync and the write-backs removed, the feed, the two settings removed, the two manual steps, "no theme change".

**Steps:**
- [ ] Step 1: The edits, `php bin/build-docs.php`, `sh bin/make-pot.sh`, the version bumps, the battery, the four checkers, `bash bin/build`; `grep -c -e $'\xe2\x80\x94' -e $'\xe2\x80\x93'` over the diff is 0.
- [ ] Step 2: Commit: `Sponsors on the site: Task 10 - the guides, the readme, the translation template; 1.111.0`.

---

## What this plan leaves to the deploy (not a task)

After the merge: build, deploy as the memory says, then read the Sponsors screen's migration box (31 rows, 31 posts, the box gone on the next load), open the TEST sponsor's dashboard (user 64274511) and the Sponsors strip on the Administrator Dashboard, call `/wp-json/wpcpm/v1/sponsors` once and check the six keys, and tell the owner the two manual steps are theirs.
